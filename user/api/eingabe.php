<?php
/**
 * PDB Eingabe API (Grav 2)
 * Neue Pflanzen erfassen und bearbeiten in den Stammdaten (plants.db, 18-Tabellen-Schema).
 * Reine Bibliotheksdatei - wird vom pdb-api-Plugin eingebunden, NIE direkt per HTTP aufgerufen.
 *
 * Zugriff nur fuer Editoren (site.login UND site.pdb_editor, Gruppe "editor"). Der DB-Pfad
 * ist fest (Stammdaten) und kommt nie aus dem Browser. Die alte Version (api/eingabe.php)
 * pruefte keinen Login.
 *
 * Actions: autocomplete_genus, autocomplete_species, check_duplicate, save, load_plant, category_tree
 */

function pdb_eingabe_json($data, int $status = 200): array
{
    return [json_encode($data, JSON_UNESCAPED_UNICODE), 'application/json; charset=utf-8', $status];
}

function pdb_api_eingabe(\Grav\Common\Grav $grav): array
{
    $user = $grav['user'] ?? null;
    if (!$user || empty($user->authenticated) || !$user->authorize('site.login')) {
        return pdb_eingabe_json(['error' => 'Nicht angemeldet.'], 401);
    }
    if (!$user->authorize('site.pdb_editor')) {
        return pdb_eingabe_json(['error' => 'Keine Berechtigung: Die Stammdaten duerfen nur Editoren bearbeiten.'], 403);
    }

    $dbPath = GRAV_ROOT . '/user/data/sqlite/plants.db';
    if (!is_file($dbPath)) {
        return pdb_eingabe_json(['error' => 'Stammdaten-Datenbank nicht gefunden.'], 500);
    }

    $action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

    try {
        $db = new SQLite3($dbPath, SQLITE3_OPEN_READWRITE);
        $db->enableExceptions(true);
        $db->busyTimeout(5000);
        $db->exec('PRAGMA foreign_keys=ON');
    } catch (\Throwable $e) {
        error_log('PDB Eingabe: Datenbank oeffnen fehlgeschlagen: ' . $e->getMessage());
        return pdb_eingabe_json(['error' => 'Datenbankfehler.'], 500);
    }

    try {
        switch ($action) {
            case 'autocomplete_genus':   return pdb_eingabe_autocomplete_genus($db);
            case 'autocomplete_species': return pdb_eingabe_autocomplete_species($db);
            case 'check_duplicate':      return pdb_eingabe_check_duplicate($db);
            case 'save':                 return pdb_eingabe_save($db);
            case 'load_plant':           return pdb_eingabe_load_plant($db);
            case 'category_tree':        return pdb_eingabe_category_tree($db);
            default:
                return pdb_eingabe_json(['error' => 'Unbekannte Aktion.'], 400);
        }
    } catch (\Throwable $e) {
        error_log('PDB Eingabe (' . $action . '): ' . $e->getMessage());
        return pdb_eingabe_json(['error' => 'Serverfehler.'], 500);
    } finally {
        $db->close();
    }
}

// ============================================================
// Lesende Actions
// ============================================================

function pdb_eingabe_autocomplete_genus(SQLite3 $db): array
{
    $term = trim((string)($_GET['term'] ?? ''));
    if ($term === '') {
        return pdb_eingabe_json([]);
    }
    $stmt = $db->prepare("SELECT DISTINCT genus FROM plants WHERE genus LIKE :term ESCAPE '!' ORDER BY genus LIMIT 20");
    $stmt->bindValue(':term', pdb_eingabe_like($term) . '%', SQLITE3_TEXT);
    $res = $stmt->execute();
    $items = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $items[] = $row['genus'];
    }
    return pdb_eingabe_json($items);
}

function pdb_eingabe_autocomplete_species(SQLite3 $db): array
{
    $genus = trim((string)($_GET['genus'] ?? ''));
    $term  = trim((string)($_GET['term'] ?? ''));
    if ($genus === '') {
        return pdb_eingabe_json([]);
    }
    $sql = 'SELECT DISTINCT species FROM plants WHERE genus = :genus';
    if ($term !== '') {
        $sql .= " AND species LIKE :term ESCAPE '!'";
    }
    $sql .= " AND species IS NOT NULL AND species != '' ORDER BY species LIMIT 20";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':genus', $genus, SQLITE3_TEXT);
    if ($term !== '') {
        $stmt->bindValue(':term', pdb_eingabe_like($term) . '%', SQLITE3_TEXT);
    }
    $res = $stmt->execute();
    $items = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $items[] = $row['species'];
    }
    return pdb_eingabe_json($items);
}

function pdb_eingabe_check_duplicate(SQLite3 $db): array
{
    $name = trim((string)($_GET['botanical_name'] ?? ''));
    if ($name === '') {
        return pdb_eingabe_json(['exists' => false]);
    }
    $excludeId = (int)($_GET['exclude_id'] ?? 0);
    if ($excludeId > 0) {
        $stmt = $db->prepare('SELECT id, botanical_name FROM plants WHERE botanical_name = :name AND id != :excl LIMIT 1');
        $stmt->bindValue(':excl', $excludeId, SQLITE3_INTEGER);
    } else {
        $stmt = $db->prepare('SELECT id, botanical_name FROM plants WHERE botanical_name = :name LIMIT 1');
    }
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if ($row) {
        return pdb_eingabe_json(['exists' => true, 'id' => $row['id'], 'name' => $row['botanical_name']]);
    }
    return pdb_eingabe_json(['exists' => false]);
}

function pdb_eingabe_category_tree(SQLite3 $db): array
{
    $res = $db->query('SELECT path FROM categories ORDER BY id');
    $paths = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $paths[] = $row['path'];
    }
    return pdb_eingabe_json($paths);
}

function pdb_eingabe_load_plant(SQLite3 $db): array
{
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        return pdb_eingabe_json(['error' => 'Keine ID']);
    }

    $stmt = $db->prepare('SELECT * FROM plants WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $plant = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$plant) {
        return pdb_eingabe_json(['error' => 'Pflanze nicht gefunden']);
    }

    // Eine Detailzeile laden; nur die erlaubten Felder, leeres Objekt wenn keine Zeile da ist
    $loadRow = function (string $table, array $fields) use ($db, $id) {
        $s = $db->prepare("SELECT * FROM $table WHERE plant_id = :id LIMIT 1");
        $s->bindValue(':id', $id, SQLITE3_INTEGER);
        $row = $s->execute()->fetchArray(SQLITE3_ASSOC);
        if (!$row) {
            return (object)[];
        }
        $out = [];
        foreach ($fields as $f) {
            $out[$f] = $row[$f] ?? null;
        }
        return $out;
    };

    $data = [
        'id'                => $id,
        'genus'             => $plant['genus'] ?? '',
        'species'           => $plant['species'] ?? '',
        'subspecies'        => $plant['subspecies'] ?? '',
        'cultivar'          => $plant['cultivar'] ?? '',
        'ecotype'           => $plant['ecotype'] ?? '',
        'notes'             => $plant['notes'] ?? '',
        'description_short' => $plant['description_short'] ?? '',
        'description_long'  => $plant['description_long'] ?? '',
        'care_instructions' => $plant['care_instructions'] ?? '',
        'meta_text'         => $plant['meta_text'] ?? '',
        'names'   => $loadRow('plant_names',   ['name_de','name_en','name_fr','name_it','registration_name','exhibition_name','trade_name']),
        'site'    => $loadRow('plant_site',    ['hardiness_zone','hardiness_temp_min','hardiness_temp_max','light_requirement','moisture_requirement','soil_type','soil_ph_min','soil_ph_max','nutrient_demand']),
        'flower'  => $loadRow('plant_flower',  ['color','fragrance','size','fullness','form','vase_life','blooming_cycle','notes','bloom_months']),
        'growth'  => $loadRow('plant_growth',  ['growth_cycle','height_cm','width_cm','growth_form_primary','growth_form_secondary','growth_vigor','notes']),
        'origin'  => $loadRow('plant_origin',  ['breeder_name','breeder_company','breeder_country','breeder_year','breeder_reference','introducer_name','introducer_country','introducer_year','hybrid_status','hybrid_parents','ploidy','variety_protection','trademark_protection','origin_region','breeding_method']),
        'fruit'   => $loadRow('plant_fruit',   ['color','size','taste','juiciness','texture','development_days','harvest_months','notes']),
        'leaf'    => $loadRow('plant_leaf',    ['color','form','aroma','size','has_autumn_color','autumn_color','evergreen','notes']),
        'root'    => $loadRow('plant_root',    ['root_depth','root_form','anchorage','taste','notes']),
        'usage'   => $loadRow('plant_usage',   ['plant_parts','purpose','processing','storage','harvest_months','notes']),
        'nursery' => $loadRow('plant_nursery', ['propagation_method','germination_days','germination_temp_min_c','germination_temp_max_c','germination_light','rooting_days','development_days_to_yp','development_days_yp_to_fp','plants_per_sqm','notes']),
        'categories' => [],
        'synonyms'   => [],
    ];

    $res = $db->query("SELECT category_path FROM plant_categories WHERE plant_id = $id ORDER BY sort_order");
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $data['categories'][] = ['category_path' => $row['category_path']];
    }
    $res = $db->query("SELECT synonym_name, language, synonym_type FROM plant_synonyms WHERE plant_id = $id ORDER BY id");
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $data['synonyms'][] = $row;
    }

    return pdb_eingabe_json($data);
}

// ============================================================
// Speichern (neue Pflanze oder Aenderung)
// ============================================================

function pdb_eingabe_save(SQLite3 $db): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return pdb_eingabe_json(['error' => 'POST erforderlich.'], 405);
    }
    // JSON ist Pflicht: einfache Formular-Posts von fremden Seiten werden so abgewiesen
    if (stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') === false) {
        return pdb_eingabe_json(['error' => 'JSON erforderlich.'], 415);
    }
    $raw = file_get_contents('php://input');
    if ($raw !== false && strlen($raw) > 1000000) {
        return pdb_eingabe_json(['error' => 'Daten zu gross.'], 413);
    }
    $input = ($raw === false || $raw === '') ? null : json_decode($raw, true);
    if (!is_array($input)) {
        return pdb_eingabe_json(['error' => 'Keine Daten empfangen']);
    }

    $genus = pdb_eingabe_str($input, 'genus');
    if ($genus === '') {
        return pdb_eingabe_json(['error' => 'Gattung ist ein Pflichtfeld']);
    }

    // --- Namen erzeugen ---
    $genus      = pdb_eingabe_ucfirst(mb_strtolower($genus));
    $species    = mb_strtolower(pdb_eingabe_str($input, 'species'));
    $subspecies = pdb_eingabe_str($input, 'subspecies');
    $ecotype    = pdb_eingabe_str($input, 'ecotype');
    $cultivarOriginal = pdb_eingabe_str($input, 'cultivar');

    foreach ([$genus, $species, $subspecies, $cultivarOriginal, $ecotype] as $part) {
        if (mb_strlen($part) > 120) {
            return pdb_eingabe_json(['error' => 'Ein Namensfeld ist zu lang (max. 120 Zeichen).']);
        }
    }

    // Deutsche Umlaute in der Sorte nach botanischem Standard umschreiben
    $cultivar = pdb_eingabe_convert_umlauts($cultivarOriginal);

    $botanicalName = $genus;
    if ($species !== '')    { $botanicalName .= ' ' . $species; }
    if ($subspecies !== '') { $botanicalName .= ' subsp. ' . mb_strtolower($subspecies); }
    if ($cultivar !== '')   { $botanicalName .= " '" . $cultivar . "'"; }
    if ($ecotype !== '')    { $botanicalName .= ' [' . $ecotype . ']'; }

    $webName   = pdb_eingabe_web_name($genus, $species, $subspecies, $cultivar, $ecotype);
    $matchcode = strtoupper(substr($genus, 0, 3) . substr($species, 0, 3) . substr($cultivar, 0, 3));

    // --- Bearbeiten oder neu? ---
    $editId = (int)($input['id'] ?? 0);
    $isEdit = $editId > 0;

    if ($isEdit) {
        $chk = $db->prepare('SELECT id FROM plants WHERE id = :id LIMIT 1');
        $chk->bindValue(':id', $editId, SQLITE3_INTEGER);
        if (!$chk->execute()->fetchArray()) {
            return pdb_eingabe_json(['error' => 'Pflanze nicht gefunden (ID: ' . $editId . ')']);
        }
        $dup = $db->prepare('SELECT id FROM plants WHERE botanical_name = :name AND id != :id LIMIT 1');
        $dup->bindValue(':id', $editId, SQLITE3_INTEGER);
    } else {
        $dup = $db->prepare('SELECT id FROM plants WHERE botanical_name = :name LIMIT 1');
    }
    $dup->bindValue(':name', $botanicalName, SQLITE3_TEXT);
    if ($dup->execute()->fetchArray()) {
        return pdb_eingabe_json(['error' => $isEdit
            ? 'Dieser botanische Name existiert bereits bei einer anderen Pflanze: ' . $botanicalName
            : 'Diese Pflanze existiert bereits: ' . $botanicalName]);
    }

    $db->exec('BEGIN TRANSACTION');
    try {
        if ($isEdit) {
            $plantId = $editId;
            $stmt = $db->prepare("UPDATE plants SET genus=:genus, species=:species, subspecies=:subspecies, cultivar=:cultivar, ecotype=:ecotype, botanical_name=:botanical_name, web_name=:web_name, matchcode=:matchcode, notes=:notes, description_short=:description_short, description_long=:description_long, care_instructions=:care_instructions, meta_text=:meta_text, updated_at=datetime('now') WHERE id=:id");
            $stmt->bindValue(':id', $plantId, SQLITE3_INTEGER);
        } else {
            $stmt = $db->prepare("INSERT INTO plants (genus, species, subspecies, cultivar, ecotype, botanical_name, web_name, matchcode, notes, description_short, description_long, care_instructions, meta_text, created_at, updated_at) VALUES (:genus, :species, :subspecies, :cultivar, :ecotype, :botanical_name, :web_name, :matchcode, :notes, :description_short, :description_long, :care_instructions, :meta_text, datetime('now'), datetime('now'))");
        }
        $stmt->bindValue(':genus', $genus, SQLITE3_TEXT);
        $stmt->bindValue(':botanical_name', $botanicalName, SQLITE3_TEXT);
        $stmt->bindValue(':web_name', $webName, SQLITE3_TEXT);
        $stmt->bindValue(':matchcode', $matchcode, SQLITE3_TEXT);
        foreach (['notes', 'description_short', 'description_long', 'care_instructions', 'meta_text'] as $f) {
            $v = pdb_eingabe_str($input, $f);
            $stmt->bindValue(':' . $f, $v !== '' ? $v : null, $v !== '' ? SQLITE3_TEXT : SQLITE3_NULL);
        }
        foreach (['species' => $species, 'subspecies' => $subspecies, 'cultivar' => $cultivar, 'ecotype' => $ecotype] as $f => $v) {
            $stmt->bindValue(':' . $f, $v !== '' ? $v : null, $v !== '' ? SQLITE3_TEXT : SQLITE3_NULL);
        }
        $stmt->execute();
        if (!$isEdit) {
            $plantId = $db->lastInsertRowID();
        }

        // Beim Bearbeiten: Detailtabellen leeren, unten neu schreiben
        if ($isEdit) {
            foreach (['plant_names', 'plant_categories', 'plant_site', 'plant_flower', 'plant_growth',
                      'plant_origin', 'plant_fruit', 'plant_leaf', 'plant_root', 'plant_usage',
                      'plant_nursery', 'plant_synonyms'] as $tbl) {
                $db->exec("DELETE FROM $tbl WHERE plant_id = " . (int)$plantId);
            }
        }

        // Detailtabellen mit je einer Zeile
        $detail = [
            'plant_names'   => ['names',   ['name_de', 'name_en', 'name_fr', 'name_it', 'registration_name', 'exhibition_name', 'trade_name']],
            'plant_site'    => ['site',    ['hardiness_zone', 'hardiness_temp_min', 'hardiness_temp_max', 'light_requirement', 'moisture_requirement', 'soil_type', 'soil_ph_min', 'soil_ph_max', 'nutrient_demand']],
            'plant_flower'  => ['flower',  ['color', 'fragrance', 'size', 'fullness', 'form', 'vase_life', 'blooming_cycle', 'notes', 'bloom_months']],
            'plant_growth'  => ['growth',  ['growth_cycle', 'height_cm', 'width_cm', 'growth_form_primary', 'growth_form_secondary', 'growth_vigor', 'notes']],
            'plant_origin'  => ['origin',  ['breeder_name', 'breeder_company', 'breeder_country', 'breeder_year', 'breeder_reference', 'introducer_name', 'introducer_country', 'introducer_year', 'hybrid_status', 'hybrid_parents', 'ploidy', 'variety_protection', 'trademark_protection', 'origin_region', 'breeding_method']],
            'plant_fruit'   => ['fruit',   ['color', 'size', 'taste', 'juiciness', 'texture', 'development_days', 'harvest_months', 'notes']],
            'plant_leaf'    => ['leaf',    ['color', 'form', 'aroma', 'size', 'has_autumn_color', 'autumn_color', 'evergreen', 'notes']],
            'plant_root'    => ['root',    ['root_depth', 'root_form', 'anchorage', 'taste', 'notes']],
            'plant_usage'   => ['usage',   ['plant_parts', 'purpose', 'processing', 'storage', 'harvest_months', 'notes']],
            'plant_nursery' => ['nursery', ['propagation_method', 'germination_days', 'germination_temp_min_c', 'germination_temp_max_c', 'germination_light', 'rooting_days', 'development_days_to_yp', 'development_days_yp_to_fp', 'plants_per_sqm', 'notes']],
        ];
        foreach ($detail as $table => [$key, $fields]) {
            $block = $input[$key] ?? [];
            if (pdb_eingabe_has_value($block)) {
                pdb_eingabe_insert_row($db, $table, (int)$plantId, $block, $fields);
            }
        }

        // Kategorien (mehrfach)
        $categories = $input['categories'] ?? [];
        if (is_array($categories)) {
            $sort = 0;
            foreach ($categories as $cat) {
                $path = is_array($cat) ? pdb_eingabe_str($cat, 'category_path') : '';
                if ($path !== '') {
                    $s = $db->prepare('INSERT INTO plant_categories (plant_id, category_path, sort_order) VALUES (:pid, :path, :sort)');
                    $s->bindValue(':pid', (int)$plantId, SQLITE3_INTEGER);
                    $s->bindValue(':path', $path, SQLITE3_TEXT);
                    $s->bindValue(':sort', ++$sort, SQLITE3_INTEGER);
                    $s->execute();
                }
            }
        }

        // Synonyme (mehrfach)
        $synonyms = $input['synonyms'] ?? [];
        if (is_array($synonyms)) {
            foreach ($synonyms as $syn) {
                $synName = is_array($syn) ? pdb_eingabe_str($syn, 'synonym_name') : '';
                if ($synName !== '') {
                    $lang = pdb_eingabe_str($syn, 'language');
                    $type = pdb_eingabe_str($syn, 'synonym_type');
                    $note = pdb_eingabe_str($syn, 'notes');
                    $s = $db->prepare('INSERT INTO plant_synonyms (plant_id, synonym_name, language, synonym_type, notes) VALUES (:pid, :name, :lang, :type, :notes)');
                    $s->bindValue(':pid', (int)$plantId, SQLITE3_INTEGER);
                    $s->bindValue(':name', $synName, SQLITE3_TEXT);
                    $s->bindValue(':lang', $lang !== '' ? $lang : null, $lang !== '' ? SQLITE3_TEXT : SQLITE3_NULL);
                    $s->bindValue(':type', $type !== '' ? $type : null, $type !== '' ? SQLITE3_TEXT : SQLITE3_NULL);
                    $s->bindValue(':notes', $note !== '' ? $note : null, $note !== '' ? SQLITE3_TEXT : SQLITE3_NULL);
                    $s->execute();
                }
            }
        }

        $db->exec('COMMIT');

        return pdb_eingabe_json([
            'success'        => true,
            'id'             => (int)$plantId,
            'botanical_name' => $botanicalName,
            'web_name'       => $webName,
            'message'        => ($isEdit ? 'Änderungen gespeichert: ' : 'Pflanze gespeichert: ') . $botanicalName,
        ]);
    } catch (\Throwable $e) {
        $db->exec('ROLLBACK');
        error_log('PDB Eingabe save: ' . $e->getMessage());
        return pdb_eingabe_json(['error' => 'Speicherfehler (Details im Server-Log).']);
    }
}

// ============================================================
// Hilfsfunktionen
// ============================================================

/** Skalaren Wert als getrimmten String lesen, sonst leer. */
function pdb_eingabe_str(array $a, string $key): string
{
    $v = $a[$key] ?? '';
    return is_scalar($v) ? trim((string)$v) : '';
}

/** LIKE-Platzhalter im Suchbegriff entschaerfen (ESCAPE '!'). */
function pdb_eingabe_like(string $term): string
{
    return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
}

/** Erster Buchstabe gross (eigene Funktion, da mb_ucfirst erst ab PHP 8.4 existiert). */
function pdb_eingabe_ucfirst(string $s): string
{
    return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
}

function pdb_eingabe_convert_umlauts(string $text): string
{
    return strtr($text, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss']);
}

function pdb_eingabe_translit(string $text): string
{
    return strtr($text, [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u',
        'ý' => 'y', 'ÿ' => 'y',
        'ñ' => 'n', 'ç' => 'c', 'ð' => 'd', 'þ' => 'th',
    ]);
}

/** URL-freundlicher Name (web_name), z.B. achillea-filipendulina-parkers */
function pdb_eingabe_web_name(string $genus, string $species, string $subspecies, string $cultivar, string $ecotype): string
{
    $parts = [mb_strtolower($genus)];
    if ($species !== '')    { $parts[] = $species; }
    if ($subspecies !== '') { $parts[] = mb_strtolower($subspecies); }
    if ($cultivar !== '')   { $parts[] = mb_strtolower($cultivar); }
    if ($ecotype !== '')    { $parts[] = mb_strtolower($ecotype); }

    $name = implode('-', $parts);
    $name = pdb_eingabe_convert_umlauts($name);
    $name = pdb_eingabe_translit($name);
    $name = preg_replace('/[\'\x{2018}\x{2019}"®™©]/u', '', $name);
    $name = preg_replace('/[^a-z0-9]+/', '-', $name);
    return trim($name, '-');
}

/** Hat der Block mindestens einen nicht-leeren einfachen Wert? */
function pdb_eingabe_has_value($data): bool
{
    if (!is_array($data)) {
        return false;
    }
    foreach ($data as $v) {
        if (is_array($v)) {
            continue;
        }
        if ($v !== null && $v !== '' && $v !== false) {
            return true;
        }
    }
    return false;
}

/** INSERT in eine Detailtabelle; nur Felder aus der festen Liste, leere Werte entfallen. */
function pdb_eingabe_insert_row(SQLite3 $db, string $table, int $plantId, $data, array $allowedFields): void
{
    $fields = ['plant_id'];
    $holders = [':plant_id'];
    $values = [':plant_id' => $plantId];

    foreach ($allowedFields as $f) {
        $val = is_array($data) ? ($data[$f] ?? null) : null;
        if (is_bool($val)) {
            $val = $val ? 1 : 0;
        }
        if ($val === null || $val === '' || !is_scalar($val)) {
            continue;
        }
        $fields[]  = $f;
        $holders[] = ':' . $f;
        $values[':' . $f] = $val;
    }
    if (count($fields) <= 1) {
        return; // nur plant_id, nichts zu speichern
    }

    $stmt = $db->prepare("INSERT INTO $table (" . implode(', ', $fields) . ') VALUES (' . implode(', ', $holders) . ')');
    foreach ($values as $key => $val) {
        if ($key === ':plant_id' || is_int($val)) {
            $stmt->bindValue($key, $val, SQLITE3_INTEGER);
        } elseif (is_float($val)) {
            $stmt->bindValue($key, $val, SQLITE3_FLOAT);
        } else {
            $stmt->bindValue($key, (string)$val, SQLITE3_TEXT);
        }
    }
    $stmt->execute();
}

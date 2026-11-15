<?php
/**
 * PDB - Vorschlaege der Gaertnerien pruefen und in die plants.db uebernehmen (Umbau A, Stufe 2).
 * Reine Bibliotheksdatei - nur ueber das pdb-api-Plugin erreichbar, nur fuer Editoren (Recht site.pdb_editor).
 *
 * Aktionen: list (Liste mit Zaehlern), load (Pruefmaske eines Vorschlags), apply (angehakte Felder uebernehmen),
 * reject (ablehnen, mit optionalem Hinweis an die Gaertnerei). Der Datenbankpfad ist fest, nie aus dem Browser.
 */

require_once __DIR__ . '/eingabe.php'; // pdb_eingabe_json, pdb_eingabe_build_names, pdb_eingabe_insert_row

const PDB_VORSCHLAG_STATI = ['offen', 'uebernommen', 'abgelehnt', 'zurueckgezogen'];

function pdb_api_vorschlaege(\Grav\Common\Grav $grav): array
{
    $user = $grav['user'] ?? null;
    if (!$user || empty($user->authenticated) || !$user->authorize('site.login')) {
        return pdb_eingabe_json(['error' => 'Nicht angemeldet.'], 401);
    }
    if (!$user->authorize('site.pdb_editor')) {
        return pdb_eingabe_json(['error' => 'Keine Berechtigung: Vorschläge dürfen nur Editoren prüfen.'], 403);
    }
    $action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

    try {
        switch ($action) {
            case 'list':   return pdb_vorschlaege_list();
            case 'load':   return pdb_vorschlaege_load();
            case 'apply':  return pdb_vorschlaege_apply((string)$user->username);
            case 'reject': return pdb_vorschlaege_reject((string)$user->username);
            default:       return pdb_eingabe_json(['error' => 'Unbekannte Aktion.'], 400);
        }
    } catch (\Throwable $e) {
        error_log('PDB Vorschlaege (' . $action . '): ' . $e->getMessage());
        return pdb_eingabe_json(['error' => 'Serverfehler.'], 500);
    }
}

// ============================================================
// Hilfen
// ============================================================

/** Vorschlag lesen; die Momentaufnahme steht dekodiert unter 'snap'. */
function pdb_vorschlaege_fetch(SQLite3 $v, int $id): ?array
{
    $st = $v->prepare('SELECT * FROM vorschlag WHERE id = :id');
    $st->bindValue(':id', $id, SQLITE3_INTEGER);
    $row = $st->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) {
        return null;
    }
    $snap = json_decode((string)$row['snapshot'], true);
    if (!is_array($snap) || !isset($snap['f'], $snap['cat'], $snap['syn'], $snap['meta'])) {
        return null;
    }
    $row['snap'] = $snap;
    return $row;
}

/** Pflanzen-ID in der plants.db zu einem Vorschlag: die gemerkte, sonst per botanischem Namen; 0 = neue Pflanze. */
function pdb_vorschlaege_resolve_master(SQLite3 $master, array $row): int
{
    $id = (int)($row['master_plant_id'] ?? 0);
    if ($id > 0 && (int)$master->querySingle('SELECT COUNT(*) FROM plants WHERE id = ' . $id) > 0) {
        return $id;
    }
    return pdb_vorschlag_master_id_by_name($master, (string)$row['snap']['meta']['botanical_name']);
}

function pdb_vorschlaege_post_json(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return ['error' => pdb_eingabe_json(['error' => 'POST erforderlich.'], 405)];
    }
    if (stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') === false) {
        return ['error' => pdb_eingabe_json(['error' => 'JSON erforderlich.'], 415)];
    }
    $raw = file_get_contents('php://input');
    if ($raw !== false && strlen($raw) > 1000000) {
        return ['error' => pdb_eingabe_json(['error' => 'Daten zu gross.'], 413)];
    }
    $input = ($raw === false || $raw === '') ? null : json_decode($raw, true);
    if (!is_array($input)) {
        return ['error' => pdb_eingabe_json(['error' => 'Keine Daten empfangen'])];
    }
    return ['input' => $input];
}

// ============================================================
// Liste
// ============================================================

function pdb_vorschlaege_list(): array
{
    $status = (string)($_GET['status'] ?? 'offen');
    if ($status !== 'alle' && !in_array($status, PDB_VORSCHLAG_STATI, true)) {
        $status = 'offen';
    }
    $v = pdb_vorschlag_open(true);
    try {
        $counts = array_fill_keys(PDB_VORSCHLAG_STATI, 0);
        $r = $v->query('SELECT status, COUNT(*) FROM vorschlag GROUP BY status');
        while ($x = $r->fetchArray(SQLITE3_NUM)) {
            $counts[(string)$x[0]] = (int)$x[1];
        }

        $sql = 'SELECT id, nursery_name, botanical_name, master_plant_id, status, submitted_at, updated_at, decided_at, decided_by, decision_note
                FROM vorschlag' . ($status !== 'alle' ? ' WHERE status = :s' : '')
            . " ORDER BY CASE status WHEN 'offen' THEN 0 ELSE 1 END, COALESCE(decided_at, updated_at) DESC, id DESC LIMIT 500";
        $q = $v->prepare($sql);
        if ($status !== 'alle') {
            $q->bindValue(':s', $status, SQLITE3_TEXT);
        }
        $items = [];
        $res = $q->execute();
        while ($x = $res->fetchArray(SQLITE3_ASSOC)) {
            $x['id'] = (int)$x['id'];
            $x['kind'] = ((int)$x['master_plant_id'] > 0) ? 'aenderung' : 'neu';
            unset($x['master_plant_id']);
            $items[] = $x;
        }
        return pdb_eingabe_json(['status' => $status, 'counts' => $counts, 'items' => $items]);
    } finally {
        $v->close();
    }
}

// ============================================================
// Pruefmaske
// ============================================================

function pdb_vorschlaege_load(): array
{
    $id = (int)($_GET['id'] ?? 0);
    $v = pdb_vorschlag_open(true);
    $master = null;
    try {
        $row = $id > 0 ? pdb_vorschlaege_fetch($v, $id) : null;
        if (!$row) {
            return pdb_eingabe_json(['error' => 'Vorschlag nicht gefunden.'], 404);
        }
        $master = pdb_userdb_open_master();
        if (!$master) {
            return pdb_eingabe_json(['error' => 'Stammdaten-Datenbank nicht lesbar.'], 500);
        }
        $snap = $row['snap'];
        $mid = pdb_vorschlaege_resolve_master($master, $row);
        $msnap = $mid > 0 ? pdb_vorschlag_snapshot($master, $mid) : null;
        $d = pdb_vorschlag_diff($snap, $msnap);

        $sections = [];
        foreach ($d['rows'] as $r) {
            $k = $r['section'];
            if (!isset($sections[$k])) {
                $sections[$k] = ['key' => $k, 'title' => $r['section_title'], 'rows' => []];
            }
            unset($r['section'], $r['section_title']);
            $sections[$k]['rows'][] = $r;
        }

        $warnings = [];
        if ($msnap && $row['master_seen_at'] !== null && (string)$msnap['meta']['updated_at'] > (string)$row['master_seen_at']) {
            $warnings[] = 'Die Pflanze wurde in der öffentlichen PDB geändert, seit die Gärtnerei den Vorschlag gemacht hat. Die Spalte «PDB heute» zeigt den aktuellen Stand.';
        }
        if ((int)$row['master_plant_id'] <= 0 && $mid > 0) {
            $warnings[] = 'Diese Pflanze gibt es inzwischen in der öffentlichen PDB; der Vorschlag wird als Änderung dieser Pflanze geprüft.';
        }

        return pdb_eingabe_json([
            'id'            => (int)$row['id'],
            'status'        => $row['status'],
            'editable'      => $row['status'] === 'offen',
            'kind'          => $mid > 0 ? 'aenderung' : 'neu',
            'nursery_name'  => $row['nursery_name'],
            'botanical_name' => $row['botanical_name'],
            'master_plant_id' => $mid > 0 ? $mid : null,
            'submitted_at'  => $row['submitted_at'],
            'updated_at'    => $row['updated_at'],
            'decided_at'    => $row['decided_at'],
            'decided_by'    => $row['decided_by'],
            'decision_note' => $row['decision_note'],
            'decision'      => $row['decision'] ? json_decode($row['decision'], true) : null,
            'warnings'      => $warnings,
            'sections'      => array_values($sections),
            'cats'          => $d['cats'],
            'master_cats'   => $d['master_cats'],
            'syns'          => $d['syns'],
            'same'          => $d['same'],
        ]);
    } finally {
        $v->close();
        if ($master) {
            $master->close();
        }
    }
}

// ============================================================
// Uebernehmen
// ============================================================

/**
 * Wert aus der Pruefmaske pruefen und in die Speicherform bringen.
 * @return array{value?:string,error?:string}
 */
function pdb_vorschlaege_clean(array $def, $raw, string $own): array
{
    if (is_array($raw)) {
        $raw = implode(',', array_map('strval', $raw));
    }
    if (!is_scalar($raw) && $raw !== null) {
        return ['error' => $def['label'] . ': ungültiger Wert.'];
    }
    $val = pdb_vorschlag_norm($raw, $def['type']);
    $label = $def['label'];
    switch ($def['type']) {
        case 'text':
            if (mb_strlen($val) > 500) {
                return ['error' => $label . ': zu lang (max. 500 Zeichen).'];
            }
            break;
        case 'textarea':
            if (mb_strlen($val) > 20000) {
                return ['error' => $label . ': zu lang (max. 20000 Zeichen).'];
            }
            break;
        case 'number':
            if ($val !== '' && !is_numeric($val)) {
                return ['error' => $label . ': Zahl erwartet.'];
            }
            break;
        case 'select':
            if ($val !== '' && !in_array($val, $def['options'] ?? [], true) && $val !== pdb_vorschlag_norm($own, 'select')) {
                return ['error' => $label . ': Wert nicht in der Auswahlliste.'];
            }
            break;
        case 'multi':
            $allowed = array_merge($def['options'] ?? [], pdb_vorschlag_multi_parts($own));
            foreach (pdb_vorschlag_multi_parts($val) as $part) {
                if (!in_array($part, $allowed, true)) {
                    return ['error' => $label . ': «' . $part . '» ist nicht in der Auswahlliste.'];
                }
            }
            break;
    }
    return ['value' => $val];
}

/** Wert fuer die Datenbank: Zahl, 0/1 bei Ankreuzfeldern, sonst Text; leer = NULL (Ankreuzfeld: 0). */
function pdb_vorschlaege_typed(array $def, string $val)
{
    if ($def['type'] === 'checkbox') {
        return $val === '1' ? 1 : 0;
    }
    if ($val === '') {
        return null;
    }
    if ($def['type'] === 'number') {
        return ((string)(int)$val === $val) ? (int)$val : (float)$val;
    }
    return $val;
}

function pdb_vorschlaege_bind(SQLite3Stmt $st, string $ph, $val): void
{
    if ($val === null) {
        $st->bindValue($ph, null, SQLITE3_NULL);
    } elseif (is_int($val)) {
        $st->bindValue($ph, $val, SQLITE3_INTEGER);
    } elseif (is_float($val)) {
        $st->bindValue($ph, $val, SQLITE3_FLOAT);
    } else {
        $st->bindValue($ph, (string)$val, SQLITE3_TEXT);
    }
}

/** Kategorien und Synonyme einer Pflanze der plants.db ergaenzen (vorhandene bleiben unberuehrt). */
function pdb_vorschlaege_add_lists(SQLite3 $db, int $pid, array $cats, array $syns): void
{
    foreach ($cats as $path) {
        $c = $db->prepare('SELECT COUNT(*) FROM plant_categories WHERE plant_id = :p AND category_path = :c');
        $c->bindValue(':p', $pid, SQLITE3_INTEGER);
        $c->bindValue(':c', $path, SQLITE3_TEXT);
        if ((int)$c->execute()->fetchArray(SQLITE3_NUM)[0] > 0) {
            continue;
        }
        $i = $db->prepare('INSERT INTO plant_categories (plant_id, category_path, sort_order)
                           VALUES (:p, :c, COALESCE((SELECT MAX(sort_order) FROM plant_categories WHERE plant_id = :p2), 0) + 1)');
        $i->bindValue(':p', $pid, SQLITE3_INTEGER);
        $i->bindValue(':p2', $pid, SQLITE3_INTEGER);
        $i->bindValue(':c', $path, SQLITE3_TEXT);
        $i->execute();
    }
    foreach ($syns as $s) {
        $c = $db->prepare("SELECT COUNT(*) FROM plant_synonyms WHERE plant_id = :p AND lower(synonym_name) = lower(:n)
                           AND COALESCE(language, '') = :l AND COALESCE(synonym_type, '') = :t");
        $c->bindValue(':p', $pid, SQLITE3_INTEGER);
        $c->bindValue(':n', $s['name'], SQLITE3_TEXT);
        $c->bindValue(':l', $s['lang'], SQLITE3_TEXT);
        $c->bindValue(':t', $s['type'], SQLITE3_TEXT);
        if ((int)$c->execute()->fetchArray(SQLITE3_NUM)[0] > 0) {
            continue;
        }
        $i = $db->prepare('INSERT INTO plant_synonyms (plant_id, synonym_name, language, synonym_type) VALUES (:p, :n, :l, :t)');
        $i->bindValue(':p', $pid, SQLITE3_INTEGER);
        $i->bindValue(':n', $s['name'], SQLITE3_TEXT);
        $i->bindValue(':l', $s['lang'] !== '' ? $s['lang'] : null, $s['lang'] !== '' ? SQLITE3_TEXT : SQLITE3_NULL);
        $i->bindValue(':t', $s['type'] !== '' ? $s['type'] : null, $s['type'] !== '' ? SQLITE3_TEXT : SQLITE3_NULL);
        $i->execute();
    }
}

/** Angehakte Felder in eine bestehende Pflanze der plants.db schreiben (nur diese Spalten, sonst nichts). */
function pdb_vorschlaege_update_plant(SQLite3 $db, int $pid, array $take, array $cats, array $syns): void
{
    $index = pdb_vorschlag_index();
    $byTable = [];
    foreach ($take as $k => $val) {
        $d = $index[$k];
        $byTable[$d['table']][$d['col']] = pdb_vorschlaege_typed($d, $val);
    }
    foreach ($byTable as $table => $cols) {
        $names = array_keys($cols);
        if ($table === 'plants') {
            $set = [];
            foreach ($names as $i => $c) {
                $set[] = '"' . $c . '" = :c' . $i;
            }
            $st = $db->prepare('UPDATE plants SET ' . implode(', ', $set) . ' WHERE id = :pid');
        } elseif ((int)$db->querySingle("SELECT COUNT(*) FROM \"$table\" WHERE plant_id = $pid") > 0) {
            $set = [];
            foreach ($names as $i => $c) {
                $set[] = '"' . $c . '" = :c' . $i;
            }
            $st = $db->prepare("UPDATE \"$table\" SET " . implode(', ', $set) . ' WHERE plant_id = :pid');
        } else {
            if (!array_filter($cols, fn($x) => $x !== null && $x !== 0)) {
                continue; // nur leere Werte fuer eine Detailzeile, die es nicht gibt
            }
            $ph = [];
            foreach ($names as $i => $c) {
                $ph[] = ':c' . $i;
            }
            $st = $db->prepare("INSERT INTO \"$table\" (plant_id, \"" . implode('", "', $names) . '") VALUES (:pid, ' . implode(', ', $ph) . ')');
        }
        foreach ($names as $i => $c) {
            pdb_vorschlaege_bind($st, ':c' . $i, $cols[$c]);
        }
        $st->bindValue(':pid', $pid, SQLITE3_INTEGER);
        $st->execute();
    }
    pdb_vorschlaege_add_lists($db, $pid, $cats, $syns);
    $db->exec("UPDATE plants SET updated_at = datetime('now') WHERE id = " . $pid);
}

/** Neue Pflanze in der plants.db anlegen. Wirft RuntimeException mit einer Meldung fuer den Editor. */
function pdb_vorschlaege_insert_plant(SQLite3 $db, array $take, array $cats, array $syns): int
{
    $nm = pdb_eingabe_build_names([
        'genus'      => $take['plants.genus'] ?? '',
        'species'    => $take['plants.species'] ?? '',
        'subspecies' => $take['plants.subspecies'] ?? '',
        'cultivar'   => $take['plants.cultivar'] ?? '',
        'ecotype'    => $take['plants.ecotype'] ?? '',
    ]);
    if (isset($nm['error'])) {
        throw new \RuntimeException($nm['error']);
    }
    $dup = $db->prepare('SELECT id FROM plants WHERE botanical_name = :n LIMIT 1');
    $dup->bindValue(':n', $nm['botanical_name'], SQLITE3_TEXT);
    if ($dup->execute()->fetchArray()) {
        throw new \RuntimeException('Diese Pflanze gibt es in der öffentlichen PDB schon: ' . $nm['botanical_name']);
    }

    $st = $db->prepare("INSERT INTO plants (genus, species, subspecies, cultivar, ecotype, botanical_name, web_name, matchcode,
                            description_short, description_long, care_instructions, meta_text, created_at, updated_at)
                        VALUES (:genus, :species, :subspecies, :cultivar, :ecotype, :botanical_name, :web_name, :matchcode,
                            :description_short, :description_long, :care_instructions, :meta_text, datetime('now'), datetime('now'))");
    foreach (['genus', 'species', 'subspecies', 'cultivar', 'ecotype', 'botanical_name', 'web_name', 'matchcode'] as $f) {
        $val = $nm[$f];
        $st->bindValue(':' . $f, $val !== '' ? $val : null, $val !== '' ? SQLITE3_TEXT : SQLITE3_NULL);
    }
    foreach (['description_short', 'description_long', 'care_instructions', 'meta_text'] as $f) {
        $val = (string)($take['plants.' . $f] ?? '');
        $st->bindValue(':' . $f, $val !== '' ? $val : null, $val !== '' ? SQLITE3_TEXT : SQLITE3_NULL);
    }
    $st->execute();
    $pid = (int)$db->lastInsertRowID();

    $index = pdb_vorschlag_index();
    $blocks = [];
    $allowed = [];
    foreach ($index as $k => $d) {
        if ($d['table'] === 'plants') {
            continue;
        }
        $allowed[$d['table']][] = $d['col'];
        if (isset($take[$k])) {
            $blocks[$d['table']][$d['col']] = pdb_vorschlaege_typed($d, $take[$k]);
        }
    }
    foreach ($blocks as $table => $block) {
        if (pdb_eingabe_has_value($block)) {
            pdb_eingabe_insert_row($db, $table, $pid, $block, $allowed[$table]);
        }
    }
    pdb_vorschlaege_add_lists($db, $pid, $cats, $syns);
    return $pid;
}

function pdb_vorschlaege_apply(string $username): array
{
    $p = pdb_vorschlaege_post_json();
    if (isset($p['error'])) {
        return $p['error'];
    }
    $input = $p['input'];
    $id = (int)($input['id'] ?? 0);
    $values = is_array($input['values'] ?? null) ? $input['values'] : [];
    $checked = array_values(array_filter(is_array($input['checked'] ?? null) ? $input['checked'] : [], 'is_string'));
    $catSel = is_array($input['cats'] ?? null) ? $input['cats'] : [];
    $synSel = is_array($input['syns'] ?? null) ? $input['syns'] : [];
    $note = mb_substr(trim((string)($input['note'] ?? '')), 0, 500);

    $dbPath = pdb_userdb_master_path();
    if (!is_file($dbPath)) {
        return pdb_eingabe_json(['error' => 'Stammdaten-Datenbank nicht gefunden.'], 500);
    }

    $v = pdb_vorschlag_open(true);
    $master = null;
    try {
        $row = $id > 0 ? pdb_vorschlaege_fetch($v, $id) : null;
        if (!$row) {
            return pdb_eingabe_json(['error' => 'Vorschlag nicht gefunden.'], 404);
        }
        if ($row['status'] !== 'offen') {
            return pdb_eingabe_json(['error' => 'Dieser Vorschlag ist nicht mehr offen (Stand: ' . $row['status'] . ').']);
        }
        $snap = $row['snap'];

        $master = new SQLite3($dbPath, SQLITE3_OPEN_READWRITE);
        $master->enableExceptions(true);
        $master->busyTimeout(5000);
        $master->exec('PRAGMA foreign_keys=ON');

        $mid = pdb_vorschlaege_resolve_master($master, $row);
        $isNew = $mid <= 0;
        $index = pdb_vorschlag_index();

        // Angehakte Felder pruefen; bei neuen Pflanzen gehoeren die Namen immer dazu
        $keys = $checked;
        if ($isNew) {
            foreach ($index as $k => $d) {
                if (!empty($d['taxonomy'])) {
                    $keys[] = $k;
                }
            }
        }
        $take = [];
        foreach (array_unique($keys) as $k) {
            if (!isset($index[$k])) {
                return pdb_eingabe_json(['error' => 'Unbekanntes Feld: ' . $k]);
            }
            $def = $index[$k];
            if (!$isNew && !empty($def['taxonomy'])) {
                continue; // Namen bestehender Pflanzen sind gesperrt
            }
            $own = (string)($snap['f'][$k] ?? '');
            $res = pdb_vorschlaege_clean($def, array_key_exists($k, $values) ? $values[$k] : $own, $own);
            if (isset($res['error'])) {
                return pdb_eingabe_json(['error' => $res['error']]);
            }
            $take[$k] = $res['value'];
        }

        // Kategorien und Synonyme muessen im Vorschlag stehen
        $cats = [];
        foreach ($catSel as $c) {
            if (is_string($c) && in_array($c, $snap['cat'], true)) {
                $cats[] = $c;
            }
        }
        $synMap = [];
        foreach ($snap['syn'] as $s) {
            $synMap[pdb_vorschlag_syn_key($s)] = $s;
        }
        $syns = [];
        foreach ($synSel as $k) {
            if (is_string($k) && isset($synMap[$k])) {
                $syns[] = $synMap[$k];
            }
        }
        if (!$isNew && !$take && !$cats && !$syns) {
            return pdb_eingabe_json(['error' => 'Es ist nichts zum Übernehmen ausgewählt.']);
        }

        $master->exec('BEGIN IMMEDIATE');
        try {
            if ($isNew) {
                $mid = pdb_vorschlaege_insert_plant($master, $take, $cats, $syns);
            } else {
                pdb_vorschlaege_update_plant($master, $mid, $take, $cats, $syns);
            }
            $master->exec('COMMIT');
        } catch (\Throwable $e) {
            try { $master->exec('ROLLBACK'); } catch (\Throwable $e2) { /* nichts offen */ }
            if ($e instanceof \RuntimeException) {
                return pdb_eingabe_json(['error' => $e->getMessage()]);
            }
            throw $e;
        }

        // Entscheidung festhalten (die Pflanze ist schon uebernommen; ein Fehler hier laesst den Vorschlag offen,
        // eine erneute Uebernahme ist unschaedlich, weil nur gleiche Werte geschrieben werden)
        $changed = [];
        foreach ($take as $k => $val) {
            if (!pdb_vorschlag_equal($val, (string)($snap['f'][$k] ?? ''), $index[$k]['type'])) {
                $changed[] = $k;
            }
        }
        $decision = json_encode([
            'felder'     => array_keys($take),
            'geaendert'  => $changed,
            'kategorien' => $cats,
            'synonyme'   => array_column($syns, 'name'),
            'neu'        => $isNew,
        ], JSON_UNESCAPED_UNICODE);
        $u = $v->prepare("UPDATE vorschlag SET status = 'uebernommen', result_master_id = :m, master_plant_id = :m, decision = :d,
                              decision_note = :n, decided_by = :u, decided_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                          WHERE id = :id AND status = 'offen'");
        $u->bindValue(':m', $mid, SQLITE3_INTEGER);
        $u->bindValue(':d', $decision, SQLITE3_TEXT);
        $u->bindValue(':n', $note !== '' ? $note : null, $note !== '' ? SQLITE3_TEXT : SQLITE3_NULL);
        $u->bindValue(':u', $username, SQLITE3_TEXT);
        $u->bindValue(':id', $id, SQLITE3_INTEGER);
        $u->execute();

        $n = count($take) + count($cats) + count($syns);
        return pdb_eingabe_json([
            'success'   => true,
            'master_id' => $mid,
            'message'   => ($isNew ? 'Neue Pflanze in die PDB aufgenommen: ' : 'Übernommen in die PDB: ') . $snap['meta']['botanical_name']
                         . ' (' . $n . ($n === 1 ? ' Angabe)' : ' Angaben)'),
        ]);
    } finally {
        $v->close();
        if ($master) {
            $master->close();
        }
    }
}

// ============================================================
// Ablehnen
// ============================================================

function pdb_vorschlaege_reject(string $username): array
{
    $p = pdb_vorschlaege_post_json();
    if (isset($p['error'])) {
        return $p['error'];
    }
    $id = (int)($p['input']['id'] ?? 0);
    $note = mb_substr(trim((string)($p['input']['note'] ?? '')), 0, 500);

    $v = pdb_vorschlag_open(true);
    try {
        $row = $id > 0 ? pdb_vorschlaege_fetch($v, $id) : null;
        if (!$row) {
            return pdb_eingabe_json(['error' => 'Vorschlag nicht gefunden.'], 404);
        }
        if ($row['status'] !== 'offen') {
            return pdb_eingabe_json(['error' => 'Dieser Vorschlag ist nicht mehr offen (Stand: ' . $row['status'] . ').']);
        }
        $u = $v->prepare("UPDATE vorschlag SET status = 'abgelehnt', decision_note = :n, decided_by = :u,
                              decided_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND status = 'offen'");
        $u->bindValue(':n', $note !== '' ? $note : null, $note !== '' ? SQLITE3_TEXT : SQLITE3_NULL);
        $u->bindValue(':u', $username, SQLITE3_TEXT);
        $u->bindValue(':id', $id, SQLITE3_INTEGER);
        $u->execute();
        return pdb_eingabe_json(['success' => true, 'message' => 'Vorschlag abgelehnt: ' . $row['botanical_name']]);
    } finally {
        $v->close();
    }
}

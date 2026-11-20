<?php
/**
 * PDB Anfrageliste API (Grav 2)
 * Eine Gaertnerei stellt eine Liste von Pflanzen zusammen, die sie zukaufen moechte
 * (Vermehrungsmaterial oder Pflanzen aller Stadien, nicht aus dem eigenen Sortiment) und
 * exportiert sie als CSV oder Excel zum Versand an Anbieter.
 *
 * Die Listen liegen in der Gaertnerei-DB des eingeloggten Kontos (Tabellen request_lists und
 * request_items, werden bei Bedarf angelegt). Ohne Konto (oeffentlich, z.B. Gemuese-, Obst-,
 * Ackerbau) gibt es eine anonyme Liste in einer eigenen kleinen DB pro Besucher, gefunden ueber
 * ein Cookie (siehe pdb_anfrage_anon_*). plants.db wird nur gelesen: Auswahl von Pflanzen
 * und Hinweis "laut PDB verfuegbar bei ...". Der DB-Pfad kommt aus dem Login bzw. aus dem
 * Cookie-Token (nur 32 Hex-Zeichen), nie als Pfad aus dem Browser.
 * Reine Bibliotheksdatei - wird vom pdb-api-Plugin eingebunden, NIE direkt per HTTP aufgerufen.
 *
 * Actions: lists, list_create, list_rename, list_delete, list_set_sender, search, items, add_master, add_free, add_paste,
 *          set_note, remove_item, autocomplete_genus, autocomplete_species, autocomplete_cultivar,
 *          export_csv, export_xlsx
 */

function pdb_anfrage_json($data, int $status = 200): array
{
    return [json_encode($data, JSON_UNESCAPED_UNICODE), 'application/json; charset=utf-8', $status];
}

function pdb_anfrage_html(string $html, int $status = 200): array
{
    return [$html, 'text/html; charset=utf-8', $status];
}

function pdb_anfrage_h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pdb_api_anfrage(\Grav\Common\Grav $grav): array
{
    // Mit Konto (auch Planer, Gruppe "planer"): Listen in der eigenen DB des Kontos.
    // Ohne Konto: anonyme Liste, eine kleine DB pro Besucher, gefunden ueber ein Cookie (siehe pdb_anfrage_anon_*).
    $nursery = pdb_current_nursery($grav, false);
    $anon = isset($nursery['error']);

    $masterPath = GRAV_ROOT . '/user/data/sqlite/plants.db';
    if (!is_file($masterPath)) {
        return pdb_anfrage_json(['error' => 'Stammdaten-Datenbank nicht gefunden.'], 500);
    }

    $action = (string)($_GET['action'] ?? $_POST['action'] ?? '');
    $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    $writeActions = ['list_create', 'list_rename', 'list_delete', 'list_set_sender', 'add_master', 'add_free', 'add_paste', 'set_note', 'remove_item'];
    if (in_array($action, $writeActions, true) && !$isPost) {
        return pdb_anfrage_json(['error' => 'Nur per POST.'], 405);
    }

    // Suche und Autovervollstaendigung lesen nur plants.db und brauchen keine Liste
    $needsDb = !in_array($action, ['search', 'autocomplete_genus', 'autocomplete_species', 'autocomplete_cultivar'], true);

    $dbPath = $anon ? '' : $nursery['path'];
    $token = '';
    $newAnon = false;
    if ($anon && $needsDb) {
        $token = pdb_anfrage_anon_token();
        $existing = $token !== '' ? pdb_anfrage_anon_dir() . '/' . $token . '.db' : '';
        if ($existing !== '' && is_file($existing)) {
            $dbPath = $existing;
            @touch($dbPath); // Aufbewahrungsfrist laeuft ab dem letzten Besuch
        } elseif ($action === 'list_create') {
            $new = pdb_anfrage_anon_new();
            if (isset($new['error'])) {
                return pdb_anfrage_json(['error' => $new['error']], $new['status'] ?? 500);
            }
            $token = $new['token'];
            $dbPath = $new['path'];
            $newAnon = true;
        } elseif ($action === 'lists') {
            return pdb_anfrage_json([]); // noch keine Liste: nichts anlegen
        } else {
            return pdb_anfrage_json(['error' => 'Liste nicht gefunden.'], 404);
        }
        // Grenzen fuer anonyme Listen (Konten bleiben wie bisher)
        $GLOBALS['pdb_anf_max_items'] = 1000;
    }

    $db = null;
    try {
        if ($needsDb) {
            $db = new SQLite3($dbPath, SQLITE3_OPEN_READWRITE | ($newAnon ? SQLITE3_OPEN_CREATE : 0));
            $db->enableExceptions(true);
            $db->busyTimeout(5000);
            $db->exec('PRAGMA foreign_keys=ON');
            pdb_anfrage_schema($db);
        }
        $mdb = new SQLite3($masterPath, SQLITE3_OPEN_READONLY);
        $mdb->enableExceptions(true);
        $mdb->busyTimeout(5000);
    } catch (\Throwable $e) {
        error_log('PDB Anfrage: Datenbank oeffnen fehlgeschlagen: ' . $e->getMessage());
        return pdb_anfrage_json(['error' => 'Datenbankfehler.'], 500);
    }

    try {
        $listId = (int)($_GET['list_id'] ?? $_POST['list_id'] ?? 0);
        switch ($action) {
            case 'lists':                 $res = pdb_anfrage_lists($db); break;
            case 'list_create':           $res = pdb_anfrage_list_create($db, $anon ? 3 : 50); break;
            case 'list_rename':           $res = pdb_anfrage_list_rename($db, $listId); break;
            case 'list_delete':           $res = pdb_anfrage_list_delete($db, $listId); break;
            case 'list_set_sender':       $res = pdb_anfrage_list_set_sender($db, $listId); break;
            case 'search':                $res = pdb_anfrage_search($mdb); break;
            case 'items':                 $res = pdb_anfrage_items($db, $mdb, $listId); break;
            case 'add_master':            $res = pdb_anfrage_add_master($db, $mdb, $listId); break;
            case 'add_free':              $res = pdb_anfrage_add_free($db, $mdb, $listId); break;
            case 'add_paste':             $res = pdb_anfrage_add_paste($db, $mdb, $listId); break;
            case 'set_note':              $res = pdb_anfrage_set_note($db); break;
            case 'remove_item':           $res = pdb_anfrage_remove_item($db); break;
            case 'autocomplete_genus':    $res = pdb_anfrage_autocomplete($mdb, 'genus'); break;
            case 'autocomplete_species':  $res = pdb_anfrage_autocomplete($mdb, 'species'); break;
            case 'autocomplete_cultivar': $res = pdb_anfrage_autocomplete($mdb, 'cultivar'); break;
            case 'export_csv':            $res = pdb_anfrage_export_csv($db, $listId); break;
            case 'export_xlsx':           $res = pdb_anfrage_export_xlsx($db, $listId, $anon ? null : $grav['user']); break;
            default:
                return pdb_anfrage_json(['error' => 'Unbekannte Aktion.'], 400);
        }
        if ($anon && $token !== '') {
            $res[2] = $res[2] ?? 200;
            $res[3] = ($res[3] ?? []) + ['Set-Cookie' => pdb_anfrage_anon_cookie($token)]; // Cookie bei jedem Zugriff verlaengern
        }
        return $res;
    } catch (\Throwable $e) {
        error_log('PDB Anfrage (' . $action . '): ' . $e->getMessage());
        return pdb_anfrage_json(['error' => 'Serverfehler.'], 500);
    } finally {
        if ($db) {
            $db->close();
        }
        $mdb->close();
    }
}

// ============================================================
// Anonyme Listen (ohne Konto): eine kleine DB pro Besucher
// ============================================================
// Der Besucher bekommt beim Anlegen der ersten Liste einen zufaelligen Token (32 Hex-Zeichen) als Cookie.
// Der Dateipfad ergibt sich allein aus diesem Token: kein Zugriff auf fremde Listen, keine ID zum Raten.
// user/data ist per .htaccess gesperrt. Dateien ohne Besuch seit 90 Tagen werden geloescht.

const PDB_ANF_ANON_DAYS = 90;

function pdb_anfrage_anon_dir(): string
{
    return GRAV_ROOT . '/user/data/sqlite/anfrage';
}

/** Token aus dem Cookie, nur wenn er genau die erwartete Form hat (Schutz vor Pfadtricks). */
function pdb_anfrage_anon_token(): string
{
    $t = (string)($_COOKIE['pdb_anf'] ?? '');
    return preg_match('/^[a-f0-9]{32}$/', $t) ? $t : '';
}

function pdb_anfrage_anon_cookie(string $token): string
{
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return 'pdb_anf=' . $token . '; Max-Age=' . (PDB_ANF_ANON_DAYS * 86400) . '; Path=/; HttpOnly; SameSite=Lax' . ($secure ? '; Secure' : '');
}

/** Neuen Besucher anlegen: Tageslimit pro Anschluss (nur ein Tages-Hash der IP, nichts Dauerhaftes), danach Aufraeumen. */
function pdb_anfrage_anon_new(): array
{
    $dir = pdb_anfrage_anon_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return ['error' => 'Speicherort für Listen nicht verfügbar.', 'status' => 500];
    }
    $day  = date('Ymd');
    $rate = $dir . '/_rate-' . $day . '-' . substr(hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $day . '|pdb-anf'), 0, 16) . '.cnt';
    $n = is_file($rate) ? (int)@file_get_contents($rate) : 0;
    if ($n >= 20) {
        return ['error' => 'Von diesem Anschluss wurden heute schon viele Listen angelegt. Bitte morgen wieder versuchen oder mit einem Konto anmelden.', 'status' => 429];
    }
    @file_put_contents($rate, (string)($n + 1), LOCK_EX);
    pdb_anfrage_anon_cleanup($dir);
    $token = bin2hex(random_bytes(16));
    return ['token' => $token, 'path' => $dir . '/' . $token . '.db'];
}

/** Hoechstens einmal pro Tag: alte anonyme Listen und Zaehlerdateien loeschen. */
function pdb_anfrage_anon_cleanup(string $dir): void
{
    $stamp = $dir . '/_cleanup.stamp';
    if (is_file($stamp) && filemtime($stamp) > time() - 86400) {
        return;
    }
    @touch($stamp);
    $old = time() - PDB_ANF_ANON_DAYS * 86400;
    foreach (glob($dir . '/*.db') ?: [] as $f) {
        if (filemtime($f) < $old) {
            @unlink($f);
            @unlink($f . '-wal');
            @unlink($f . '-shm');
            @unlink($f . '-journal');
        }
    }
    foreach (glob($dir . '/_rate-*.cnt') ?: [] as $f) {
        if (filemtime($f) < time() - 2 * 86400) {
            @unlink($f);
        }
    }
}

// ============================================================
// Schema und Hilfsfunktionen
// ============================================================

function pdb_anfrage_schema(SQLite3 $db): void
{
    $db->exec("CREATE TABLE IF NOT EXISTS request_lists (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        title      TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS request_items (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        list_id         INTEGER NOT NULL REFERENCES request_lists(id) ON DELETE CASCADE,
        master_plant_id INTEGER,
        botanical_name  TEXT NOT NULL,
        genus           TEXT NOT NULL,
        species         TEXT NOT NULL DEFAULT '',
        cultivar        TEXT NOT NULL DEFAULT '',
        note            TEXT NOT NULL DEFAULT '',
        created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec('CREATE INDEX IF NOT EXISTS idx_request_items_list ON request_items(list_id)');

    // Betriebsname pro Liste (steht im Kopf der Excel-Datei): Spalte bei aelteren DBs nachruesten
    $hasSender = false;
    $cols = $db->query('PRAGMA table_info(request_lists)');
    while ($c = $cols->fetchArray(SQLITE3_ASSOC)) {
        if ($c['name'] === 'sender_name') {
            $hasSender = true;
        }
    }
    if (!$hasSender) {
        $db->exec("ALTER TABLE request_lists ADD COLUMN sender_name TEXT NOT NULL DEFAULT ''");
    }
}

/** Steuerzeichen raus, Leerraum glaetten, Laenge begrenzen. */
function pdb_anfrage_clean($s, int $max = 80): string
{
    $s = (string)preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string)$s);
    $s = trim((string)preg_replace('/\s+/u', ' ', $s));
    return mb_substr($s, 0, $max);
}

/** Gattung, Art, Sorte normalisieren: Gattung gross, Art klein, Sorte ohne Anfuehrungszeichen. */
function pdb_anfrage_norm(string $g, string $s, string $c): array
{
    $q = '\'"‘’“”«»‹›`´';
    $g = pdb_anfrage_clean($g);
    $s = mb_strtolower(pdb_anfrage_clean($s));
    $c = (string)preg_replace('/^[' . $q . '\s]+|[' . $q . '\s]+$/u', '', pdb_anfrage_clean($c));
    if ($g !== '') {
        $g = mb_strtoupper(mb_substr($g, 0, 1)) . mb_substr($g, 1);
    }
    return [$g, $s, $c];
}

function pdb_anfrage_name(string $g, string $s, string $c): string
{
    $n = trim($g . ($s !== '' ? ' ' . $s : ''));
    if ($c !== '') {
        $n .= " '" . $c . "'";
    }
    return $n;
}

function pdb_anfrage_valid(string $g, string $s): bool
{
    return $g !== ''
        && (bool)preg_match('/^\p{L}[\p{L}\-\.×]*$/u', $g)
        && ($s === '' || (bool)preg_match('/^[\p{L}\-\.×\s]+$/u', $s));
}

/** Passende Pflanze in plants.db (Gattung, Art, Sorte; keine Unterart/Oekotyp-Zeilen). */
function pdb_anfrage_find_master(SQLite3 $mdb, string $g, string $s, string $c): ?int
{
    $st = $mdb->prepare("SELECT id FROM plants
        WHERE genus = :g COLLATE NOCASE AND COALESCE(species,'') = :s COLLATE NOCASE
          AND COALESCE(cultivar,'') = :c COLLATE NOCASE
          AND COALESCE(subspecies,'') = '' AND COALESCE(ecotype,'') = ''
        ORDER BY id LIMIT 1");
    $st->bindValue(':g', $g, SQLITE3_TEXT);
    $st->bindValue(':s', $s, SQLITE3_TEXT);
    $st->bindValue(':c', $c, SQLITE3_TEXT);
    $id = $st->execute()->fetchArray(SQLITE3_NUM);
    return $id ? (int)$id[0] : null;
}

/**
 * Pflanze in die Liste aufnehmen.
 * @return string 'added' | 'duplicate' | 'invalid' | 'limit'
 */
function pdb_anfrage_add(SQLite3 $db, SQLite3 $mdb, int $listId, string $g, string $s, string $c,
                         ?int $masterId = null, ?string $botanical = null): string
{
    [$g, $s, $c] = pdb_anfrage_norm($g, $s, $c);
    if (!pdb_anfrage_valid($g, $s)) {
        return 'invalid';
    }
    $name = $botanical !== null && $botanical !== '' ? pdb_anfrage_clean($botanical, 200) : pdb_anfrage_name($g, $s, $c);

    $dup = $db->prepare('SELECT 1 FROM request_items WHERE list_id = :l AND botanical_name = :n COLLATE NOCASE LIMIT 1');
    $dup->bindValue(':l', $listId, SQLITE3_INTEGER);
    $dup->bindValue(':n', $name, SQLITE3_TEXT);
    if ($dup->execute()->fetchArray(SQLITE3_NUM)) {
        return 'duplicate';
    }

    // Obergrenze nur fuer anonyme Listen (in pdb_api_anfrage gesetzt)
    $maxItems = (int)($GLOBALS['pdb_anf_max_items'] ?? 0);
    if ($maxItems > 0) {
        $cnt = $db->prepare('SELECT COUNT(*) FROM request_items WHERE list_id = :l');
        $cnt->bindValue(':l', $listId, SQLITE3_INTEGER);
        $have = $cnt->execute()->fetchArray(SQLITE3_NUM);
        if ($have && (int)$have[0] >= $maxItems) {
            return 'limit';
        }
    }

    if ($masterId === null) {
        $masterId = pdb_anfrage_find_master($mdb, $g, $s, $c);
    }
    $ins = $db->prepare('INSERT INTO request_items (list_id, master_plant_id, botanical_name, genus, species, cultivar)
                         VALUES (:l, :m, :n, :g, :s, :c)');
    $ins->bindValue(':l', $listId, SQLITE3_INTEGER);
    $ins->bindValue(':m', $masterId, $masterId === null ? SQLITE3_NULL : SQLITE3_INTEGER);
    $ins->bindValue(':n', $name, SQLITE3_TEXT);
    $ins->bindValue(':g', $g, SQLITE3_TEXT);
    $ins->bindValue(':s', $s, SQLITE3_TEXT);
    $ins->bindValue(':c', $c, SQLITE3_TEXT);
    $ins->execute();
    return 'added';
}

function pdb_anfrage_list_exists(SQLite3 $db, int $listId): bool
{
    if ($listId <= 0) {
        return false;
    }
    $st = $db->prepare('SELECT 1 FROM request_lists WHERE id = :l');
    $st->bindValue(':l', $listId, SQLITE3_INTEGER);
    return (bool)$st->execute()->fetchArray(SQLITE3_NUM);
}

function pdb_anfrage_result_message(int $added, int $dup, int $invalid, int $limit = 0): string
{
    $m = $added . ' Pflanze(n) hinzugefügt';
    if ($dup) {
        $m .= ', ' . $dup . ' schon in der Liste';
    }
    if ($invalid) {
        $m .= ', ' . $invalid . ' nicht erkannt';
    }
    if ($limit) {
        $m .= ', ' . $limit . ' nicht aufgenommen (Obergrenze der Liste erreicht)';
    }
    return $m . '.';
}

// ============================================================
// Listen
// ============================================================

function pdb_anfrage_lists(SQLite3 $db): array
{
    $res = $db->query('SELECT l.id, l.title, l.sender_name, l.created_at,
                              (SELECT COUNT(*) FROM request_items i WHERE i.list_id = l.id) AS n
                       FROM request_lists l ORDER BY l.id DESC');
    $out = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
        $out[] = ['id' => (int)$r['id'], 'title' => $r['title'], 'sender' => (string)$r['sender_name'],
                  'count' => (int)$r['n'], 'created_at' => $r['created_at']];
    }
    return pdb_anfrage_json($out);
}

function pdb_anfrage_list_create(SQLite3 $db, int $maxLists = 50): array
{
    $title = pdb_anfrage_clean($_POST['title'] ?? '', 120);
    if ($title === '') {
        $title = 'Anfrage ' . date('d.m.Y');
    }
    if ((int)$db->querySingle('SELECT COUNT(*) FROM request_lists') >= $maxLists) {
        return pdb_anfrage_json(['error' => 'Maximal ' . $maxLists . ' Listen. Bitte zuerst alte Listen löschen.'], 400);
    }
    $st = $db->prepare('INSERT INTO request_lists (title, sender_name) VALUES (:t, :s)');
    $st->bindValue(':t', $title, SQLITE3_TEXT);
    $st->bindValue(':s', pdb_anfrage_clean($_POST['sender'] ?? '', 120), SQLITE3_TEXT);
    $st->execute();
    return pdb_anfrage_json(['success' => true, 'id' => (int)$db->lastInsertRowID(), 'title' => $title]);
}

/** Titel der Liste aendern (darf nicht leer sein). */
function pdb_anfrage_list_rename(SQLite3 $db, int $listId): array
{
    if (!pdb_anfrage_list_exists($db, $listId)) {
        return pdb_anfrage_json(['error' => 'Liste nicht gefunden.'], 404);
    }
    $title = pdb_anfrage_clean($_POST['title'] ?? '', 120);
    if ($title === '') {
        return pdb_anfrage_json(['error' => 'Der Titel darf nicht leer sein.'], 400);
    }
    $st = $db->prepare('UPDATE request_lists SET title = :t WHERE id = :l');
    $st->bindValue(':t', $title, SQLITE3_TEXT);
    $st->bindValue(':l', $listId, SQLITE3_INTEGER);
    $st->execute();
    return pdb_anfrage_json(['success' => true, 'title' => $title]);
}

/** Betriebsname der Liste aendern (optional, darf leer sein). */
function pdb_anfrage_list_set_sender(SQLite3 $db, int $listId): array
{
    if (!pdb_anfrage_list_exists($db, $listId)) {
        return pdb_anfrage_json(['error' => 'Liste nicht gefunden.'], 404);
    }
    $st = $db->prepare('UPDATE request_lists SET sender_name = :s WHERE id = :l');
    $st->bindValue(':s', pdb_anfrage_clean($_POST['sender'] ?? '', 120), SQLITE3_TEXT);
    $st->bindValue(':l', $listId, SQLITE3_INTEGER);
    $st->execute();
    return pdb_anfrage_json(['success' => true]);
}

function pdb_anfrage_list_delete(SQLite3 $db, int $listId): array
{
    if (!pdb_anfrage_list_exists($db, $listId)) {
        return pdb_anfrage_json(['error' => 'Liste nicht gefunden.'], 404);
    }
    $st = $db->prepare('DELETE FROM request_lists WHERE id = :l');
    $st->bindValue(':l', $listId, SQLITE3_INTEGER);
    $st->execute();
    return pdb_anfrage_json(['success' => true]);
}

// ============================================================
// Suche in plants.db und Autovervollstaendigung
// ============================================================

function pdb_anfrage_like(string $s): string
{
    return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $s);
}

function pdb_anfrage_search(SQLite3 $mdb): array
{
    $term = pdb_anfrage_clean($_GET['term'] ?? '', 80);
    if ($term === '') {
        return pdb_anfrage_html(''); // leeres Feld: nichts anzeigen (die Anleitung ueber dem Feld nennt die 2 Zeichen)
    }
    if (mb_strlen($term) < 2) {
        return pdb_anfrage_html('<div class="pdb-search-hint">Mindestens 2 Zeichen eingeben...</div>');
    }

    $words = array_values(array_filter(explode(' ', $term), fn($w) => $w !== ''));
    $where = [];
    $bind  = [];
    foreach ($words as $i => $w) {
        $k = ':w' . $i;
        $where[] = "(p.botanical_name LIKE $k ESCAPE '!' OR n.name_de LIKE $k ESCAPE '!')";
        $bind[$k] = '%' . pdb_anfrage_like($w) . '%';
    }
    $st = $mdb->prepare('SELECT p.id, p.botanical_name, MIN(n.name_de) AS name_de
        FROM plants p LEFT JOIN plant_names n ON n.plant_id = p.id
        WHERE ' . implode(' AND ', $where) . '
        GROUP BY p.id ORDER BY p.botanical_name LIMIT 100');
    foreach ($bind as $k => $v) {
        $st->bindValue($k, $v, SQLITE3_TEXT);
    }
    $res = $st->execute();

    $html = '';
    $n = 0;
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
        $n++;
        $name = pdb_anfrage_h($r['botanical_name']);
        $de   = $r['name_de'] ? ' <span class="pdb-name-de">(' . pdb_anfrage_h($r['name_de']) . ')</span>' : '';
        $html .= '<label class="pdb-search-result"><input type="checkbox" class="pdb-search-cb" value="' . (int)$r['id']
               . '"><span class="pdb-botanical">' . $name . '</span>' . $de . "</label>\n";
    }
    if ($n === 0) {
        return pdb_anfrage_html('<div class="pdb-search-hint">Keine Treffer für «' . pdb_anfrage_h($term) . '». Die Pflanze kann unten frei eingegeben werden.</div>');
    }
    return pdb_anfrage_html($html);
}

function pdb_anfrage_autocomplete(SQLite3 $mdb, string $field): array
{
    $term = pdb_anfrage_clean($_GET['term'] ?? '', 80);
    $genus = pdb_anfrage_clean($_GET['genus'] ?? '', 80);
    $species = pdb_anfrage_clean($_GET['species'] ?? '', 80);

    if ($field === 'genus') {
        if ($term === '') {
            return pdb_anfrage_json([]);
        }
        $sql = "SELECT DISTINCT genus AS v FROM plants WHERE genus LIKE :t ESCAPE '!' AND genus <> '' ORDER BY genus LIMIT 20";
    } elseif ($field === 'species') {
        if ($genus === '') {
            return pdb_anfrage_json([]);
        }
        $sql = "SELECT DISTINCT species AS v FROM plants WHERE genus = :g COLLATE NOCASE
                AND species LIKE :t ESCAPE '!' AND species <> '' ORDER BY species LIMIT 20";
    } else {
        if ($genus === '') {
            return pdb_anfrage_json([]);
        }
        $sql = "SELECT DISTINCT cultivar AS v FROM plants WHERE genus = :g COLLATE NOCASE
                AND COALESCE(species,'') = :s COLLATE NOCASE
                AND cultivar LIKE :t ESCAPE '!' AND cultivar <> '' ORDER BY cultivar LIMIT 20";
    }
    $st = $mdb->prepare($sql);
    $st->bindValue(':t', pdb_anfrage_like($term) . '%', SQLITE3_TEXT);
    if ($field !== 'genus') {
        $st->bindValue(':g', $genus, SQLITE3_TEXT);
    }
    if ($field === 'cultivar') {
        $st->bindValue(':s', mb_strtolower($species), SQLITE3_TEXT);
    }
    $res = $st->execute();
    $out = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
        $out[] = $r['v'];
    }
    return pdb_anfrage_json($out);
}

// ============================================================
// Zeilen der Liste: Anzeige
// ============================================================

/** Oeffentlich gekennzeichnete Verfuegbarkeit einer Pflanze laut plants.db. */
function pdb_anfrage_availability(SQLite3 $mdb, int $plantId): array
{
    $st = $mdb->prepare('SELECT nursery_name, contact_url, pot_size, available_quantity
                         FROM plant_availability WHERE plant_id = :p AND is_public = 1 ORDER BY nursery_name');
    $st->bindValue(':p', $plantId, SQLITE3_INTEGER);
    $res = $st->execute();
    $out = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
        $out[] = $r;
    }
    return $out;
}

/** Link nur fuer http(s) oder blanke Adressen, nie fuer andere Schemata (javascript: usw.). */
function pdb_anfrage_link(string $url): ?string
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }
    if (preg_match('~^[a-z][a-z0-9+.\-]*:~i', $url)) {
        return preg_match('~^https?://~i', $url) ? $url : null;
    }
    return 'https://' . $url;
}

function pdb_anfrage_availability_html(array $rows): string
{
    if (!$rows) {
        return '<span class="pdb-anf-none">-</span>';
    }
    $parts = [];
    foreach ($rows as $r) {
        $name = pdb_anfrage_h($r['nursery_name'] ?? 'Gärtnerei');
        $link = pdb_anfrage_link((string)($r['contact_url'] ?? ''));
        $txt  = $link ? '<a href="' . pdb_anfrage_h($link) . '" target="_blank" rel="noopener noreferrer">' . $name . '</a>' : $name;
        $det = [];
        if (!empty($r['pot_size'])) {
            $det[] = pdb_anfrage_h($r['pot_size']);
        }
        if ($r['available_quantity'] !== null && $r['available_quantity'] !== '') {
            $det[] = (int)$r['available_quantity'] . ' Stk.';
        }
        $parts[] = $txt . ($det ? ' (' . implode(', ', $det) . ')' : '');
    }
    return '<span class="pdb-anf-avail">' . implode('; ', $parts) . '</span>';
}

function pdb_anfrage_items(SQLite3 $db, SQLite3 $mdb, int $listId): array
{
    if (!pdb_anfrage_list_exists($db, $listId)) {
        return pdb_anfrage_html('<thead><tr><th>Anfrageliste</th></tr></thead><tbody data-count="0"><tr><td class="pdb-no-results">Liste nicht gefunden.</td></tr></tbody>', 404);
    }
    $st = $db->prepare('SELECT id, master_plant_id, botanical_name, genus, species, cultivar, note
                        FROM request_items WHERE list_id = :l ORDER BY botanical_name COLLATE NOCASE, id');
    $st->bindValue(':l', $listId, SQLITE3_INTEGER);
    $res = $st->execute();

    $rows = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = $r;
    }

    $html = '<thead><tr><th class="pdb-col-botanical">Botanischer Name</th><th>Bemerkung</th>'
          . '<th>Laut PDB verfügbar bei</th><th></th></tr></thead>';
    $html .= '<tbody data-count="' . count($rows) . '">';
    if (!$rows) {
        $html .= '<tr><td colspan="4" class="pdb-no-results">Die Liste ist noch leer. Pflanzen oben suchen, frei eingeben oder einfügen.</td></tr>';
    }
    $cross = '<svg class="pdb-remove-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';
    foreach ($rows as $r) {
        $id = (int)$r['id'];
        $masterId = $r['master_plant_id'] !== null
            ? (int)$r['master_plant_id']
            : pdb_anfrage_find_master($mdb, $r['genus'], $r['species'], $r['cultivar']);
        $avail = $masterId ? pdb_anfrage_availability($mdb, $masterId) : [];
        $html .= '<tr class="pdb-row" data-item-id="' . $id . '">'
               . '<td class="pdb-cell-botanical">' . pdb_anfrage_h($r['botanical_name']) . '</td>'
               . '<td><input type="text" class="pdb-anf-note" maxlength="300" value="' . pdb_anfrage_h($r['note'])
               . '" placeholder="z.B. Menge, Wunschform" onchange="pdbAnfNote(' . $id . ', this)"></td>'
               . '<td>' . pdb_anfrage_availability_html($avail) . '</td>'
               . '<td class="pdb-cell-actions"><a class="pdb-remove-btn" href="#" title="Aus der Liste entfernen" '
               . 'aria-label="Entfernen" onclick="pdbAnfRemove(' . $id . '); return false;">' . $cross . '</a></td>'
               . '</tr>';
    }
    $html .= '</tbody>';
    return pdb_anfrage_html($html);
}

// ============================================================
// Zeilen der Liste: Aenderungen
// ============================================================

function pdb_anfrage_add_master(SQLite3 $db, SQLite3 $mdb, int $listId): array
{
    if (!pdb_anfrage_list_exists($db, $listId)) {
        return pdb_anfrage_json(['error' => 'Liste nicht gefunden.'], 404);
    }
    $ids = $_POST['plant_ids'] ?? [];
    if (!is_array($ids) || !$ids) {
        return pdb_anfrage_json(['error' => 'Keine Pflanzen ausgewählt.'], 400);
    }
    $added = $dup = $invalid = $limit = 0;
    // Eine Transaktion pro Anfrage statt eines Schreibvorgangs pro Zeile (sonst belegt eine lange Liste den Server sehr lange)
    $db->exec('BEGIN IMMEDIATE');
    try {
        foreach (array_slice($ids, 0, 200) as $pid) {
            $pid = (int)$pid;
            $st = $mdb->prepare('SELECT id, genus, species, cultivar, botanical_name FROM plants WHERE id = :p');
            $st->bindValue(':p', $pid, SQLITE3_INTEGER);
            $p = $st->execute()->fetchArray(SQLITE3_ASSOC);
            if (!$p) {
                $invalid++;
                continue;
            }
            $r = pdb_anfrage_add($db, $mdb, $listId, (string)$p['genus'], (string)($p['species'] ?? ''),
                                 (string)($p['cultivar'] ?? ''), (int)$p['id'], (string)$p['botanical_name']);
            match ($r) { 'added' => $added++, 'duplicate' => $dup++, 'limit' => $limit++, default => $invalid++ };
        }
        $db->exec('COMMIT');
    } catch (\Throwable $e) {
        $db->exec('ROLLBACK');
        throw $e;
    }
    return pdb_anfrage_json(['success' => true, 'added' => $added,
                             'message' => pdb_anfrage_result_message($added, $dup, $invalid, $limit)]);
}

function pdb_anfrage_add_free(SQLite3 $db, SQLite3 $mdb, int $listId): array
{
    if (!pdb_anfrage_list_exists($db, $listId)) {
        return pdb_anfrage_json(['error' => 'Liste nicht gefunden.'], 404);
    }
    $r = pdb_anfrage_add($db, $mdb, $listId, (string)($_POST['genus'] ?? ''), (string)($_POST['species'] ?? ''),
                         (string)($_POST['cultivar'] ?? ''));
    if ($r === 'invalid') {
        return pdb_anfrage_json(['error' => 'Bitte mindestens eine Gattung angeben (nur Buchstaben und Bindestrich).'], 400);
    }
    if ($r === 'duplicate') {
        return pdb_anfrage_json(['error' => 'Diese Pflanze steht schon in der Liste.'], 400);
    }
    if ($r === 'limit') {
        return pdb_anfrage_json(['error' => 'Die Liste hat die Obergrenze erreicht. Bitte eine neue Anfrage anlegen.'], 400);
    }
    return pdb_anfrage_json(['success' => true, 'message' => '1 Pflanze hinzugefügt.']);
}

/**
 * Eine Pflanze pro Zeile. Aus Excel eingefuegte Zeilen (Tabulator) gelten als Gattung, Art, Sorte.
 * Sonst: Gattung, optional Art (klein geschrieben), Rest ist die Sorte.
 */
function pdb_anfrage_parse_line(string $line): ?array
{
    $line = trim($line);
    if ($line === '') {
        return null;
    }
    if (strpos($line, "\t") !== false) {
        $p = array_map('trim', explode("\t", $line));
        return [$p[0] ?? '', $p[1] ?? '', $p[2] ?? ''];
    }
    $line = trim(pdb_anfrage_clean($line, 200));
    $tok = explode(' ', $line);
    $g = array_shift($tok);
    $s = '';
    if ($tok) {
        $first = $tok[0];
        if (in_array($first, ['x', 'X', '×'], true) && isset($tok[1])) {
            $s = '× ' . $tok[1];
            array_shift($tok);
            array_shift($tok);
        } elseif (preg_match('/^\p{Ll}/u', $first)) {
            $s = $first;
            array_shift($tok);
        }
    }
    return [$g, $s, implode(' ', $tok)];
}

function pdb_anfrage_add_paste(SQLite3 $db, SQLite3 $mdb, int $listId): array
{
    if (!pdb_anfrage_list_exists($db, $listId)) {
        return pdb_anfrage_json(['error' => 'Liste nicht gefunden.'], 404);
    }
    $text = mb_substr((string)($_POST['text'] ?? ''), 0, 30000);
    $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
    $added = $dup = $invalid = $limit = 0;
    // Eine Transaktion pro Anfrage statt eines Schreibvorgangs pro Zeile (sonst belegt eine lange Liste den Server sehr lange)
    $db->exec('BEGIN IMMEDIATE');
    try {
        foreach (array_slice($lines, 0, 500) as $line) {
            $parts = pdb_anfrage_parse_line($line);
            if ($parts === null) {
                continue;
            }
            $r = pdb_anfrage_add($db, $mdb, $listId, $parts[0], $parts[1], $parts[2]);
            match ($r) { 'added' => $added++, 'duplicate' => $dup++, 'limit' => $limit++, default => $invalid++ };
        }
        $db->exec('COMMIT');
    } catch (\Throwable $e) {
        $db->exec('ROLLBACK');
        throw $e;
    }
    return pdb_anfrage_json(['success' => true, 'added' => $added,
                             'message' => pdb_anfrage_result_message($added, $dup, $invalid, $limit)]);
}

function pdb_anfrage_set_note(SQLite3 $db): array
{
    $st = $db->prepare('UPDATE request_items SET note = :n WHERE id = :i');
    $st->bindValue(':n', pdb_anfrage_clean($_POST['note'] ?? '', 300), SQLITE3_TEXT);
    $st->bindValue(':i', (int)($_POST['item_id'] ?? 0), SQLITE3_INTEGER);
    $st->execute();
    return pdb_anfrage_json(['success' => $db->changes() > 0]);
}

function pdb_anfrage_remove_item(SQLite3 $db): array
{
    $st = $db->prepare('DELETE FROM request_items WHERE id = :i');
    $st->bindValue(':i', (int)($_POST['item_id'] ?? 0), SQLITE3_INTEGER);
    $st->execute();
    return pdb_anfrage_json(['success' => $db->changes() > 0]);
}

// ============================================================
// Export (CSV und Excel)
// ============================================================

/** @return array{title:string,sender:string,items:array<int,array>}|null */
function pdb_anfrage_load(SQLite3 $db, int $listId): ?array
{
    $st = $db->prepare('SELECT title, sender_name FROM request_lists WHERE id = :l');
    $st->bindValue(':l', $listId, SQLITE3_INTEGER);
    $t = $st->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$t) {
        return null;
    }
    $st = $db->prepare('SELECT botanical_name, genus, species, cultivar, note FROM request_items
                        WHERE list_id = :l ORDER BY botanical_name COLLATE NOCASE, id');
    $st->bindValue(':l', $listId, SQLITE3_INTEGER);
    $res = $st->execute();
    $items = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
        $items[] = $r;
    }
    return ['title' => (string)$t['title'], 'sender' => (string)$t['sender_name'], 'items' => $items];
}

function pdb_anfrage_file_slug(string $title): string
{
    $t = strtr($title, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
                        'ß' => 'ss', 'é' => 'e', 'è' => 'e', 'à' => 'a']);
    $t = trim((string)preg_replace('/[^A-Za-z0-9]+/', '-', $t), '-');
    return $t !== '' ? substr($t, 0, 50) : 'Liste';
}

function pdb_anfrage_export_csv(SQLite3 $db, int $listId): array
{
    $data = pdb_anfrage_load($db, $listId);
    if ($data === null) {
        return pdb_anfrage_json(['error' => 'Liste nicht gefunden.'], 404);
    }
    // Zellen, die mit = + - @ beginnen, wuerde Excel als Formel auswerten
    $safe = function ($v): string {
        $v = (string)$v;
        return ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;
    };

    $fh = fopen('php://temp', 'r+');
    fwrite($fh, "\xEF\xBB\xBF");
    fputcsv($fh, ['Nr.', 'Botanischer Name', 'Gattung', 'Art', 'Sorte', 'Bemerkung Anfragende/r',
                  'Verfügbar (ja/nein)', 'Qualität (Bio/Umstellung/konventionell)',
                  'Form (Samen/Stecklinge/Jungpflanzen/Fertigpflanzen/Anderes)', 'Menge', 'Preis CHF',
                  'Bemerkung Lieferant'], ';', '"', '', "\r\n");
    foreach ($data['items'] as $i => $r) {
        fputcsv($fh, [$i + 1, $safe($r['botanical_name']), $safe($r['genus']), $safe($r['species']),
                      $safe($r['cultivar']), $safe($r['note']), '', '', '', '', '', ''], ';', '"', '', "\r\n");
    }
    rewind($fh);
    $body = stream_get_contents($fh);
    fclose($fh);

    $file = 'Anfrage_' . pdb_anfrage_file_slug($data['title']) . '_' . date('Y-m-d') . '.csv';
    return [$body, 'text/csv; charset=utf-8', 200, ['Content-Disposition' => 'attachment; filename="' . $file . '"']];
}

/**
 * Excel-Datei ohne Zusatzbibliothek (OOXML per ZipArchive). Die Spalten fuer den Anbieter sind gelb
 * hinterlegt (blaue Kopfzeile), "Verfuegbar", "Qualitaet" und "Form" sind Auswahlfelder zum Anklicken.
 */
function pdb_anfrage_export_xlsx(SQLite3 $db, int $listId, $user): array
{
    if (!class_exists('ZipArchive')) {
        return pdb_anfrage_json(['error' => 'Excel-Export ist auf diesem Server nicht verfügbar (PHP-Erweiterung zip fehlt). Bitte CSV verwenden.'], 501);
    }
    $data = pdb_anfrage_load($db, $listId);
    if ($data === null) {
        return pdb_anfrage_json(['error' => 'Liste nicht gefunden.'], 404);
    }

    $esc   = fn($s) => htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $str   = fn($ref, $text, $style) => '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . $esc($text) . '</t></is></c>';
    $num   = fn($ref, $n, $style) => '<c r="' . $ref . '" s="' . $style . '"><v>' . (int)$n . '</v></c>';
    $blank = fn($ref, $style) => '<c r="' . $ref . '" s="' . $style . '"/>';

    // Absender: Betriebsname der Liste. Mit Konto zusaetzlich Ort, Mail und Telefon aus dem Profil
    // (ohne Betriebsname der Profilname); ohne Konto nur der freiwillige Betriebsname.
    $sender = pdb_anfrage_clean($data['sender'] ?? '', 120);
    if ($user) {
        $from = array_filter([
            $sender !== '' ? $sender : pdb_anfrage_clean($user->get('fullname') ?: (string)$user->username, 120),
            pdb_anfrage_clean($user->get('betrieb_ort') ?? '', 80),
            pdb_anfrage_clean($user->get('email') ?? '', 120),
            pdb_anfrage_clean($user->get('betrieb_telefon') ?? '', 40),
        ], fn($v) => $v !== '');
    } else {
        $from = $sender !== '' ? [$sender] : [];
    }

    $n    = count($data['items']);
    $last = 5 + max($n, 1);
    $rows = '';
    $rows .= '<row r="1" ht="24" customHeight="1">' . $str('A1', 'Anfrage: ' . $data['title'], 4) . '</row>';
    $rows .= '<row r="2">' . $str('A2', ($from ? 'Von: ' . implode(' · ', $from) . '   ' : '') . 'Datum: ' . date('d.m.Y'), 5) . '</row>';
    $rows .= '<row r="3">' . $str('A3', 'Bitte pro Zeile in den gelb hinterlegten Feldern antworten: verfügbar ja/nein, Qualität, Form, Menge, Preis.', 5) . '</row>';

    $head = ['Nr.', 'Botanischer Name', 'Gattung', 'Art', 'Sorte', 'Bemerkung Anfragende/r',
             'Verfügbar', 'Qualität', 'Form', 'Menge', 'Preis CHF', 'Bemerkung Lieferant'];
    $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'];
    $rows .= '<row r="5" ht="34" customHeight="1">';
    foreach ($head as $i => $h) {
        $rows .= $str($cols[$i] . '5', $h, $i < 6 ? 1 : 6);
    }
    $rows .= '</row>';
    foreach ($data['items'] as $i => $r) {
        $y = 6 + $i;
        $rows .= '<row r="' . $y . '">'
               . $num('A' . $y, $i + 1, 2)
               . $str('B' . $y, $r['botanical_name'], 2)
               . $str('C' . $y, $r['genus'], 2)
               . $str('D' . $y, $r['species'], 2)
               . $str('E' . $y, $r['cultivar'], 2)
               . $str('F' . $y, $r['note'], 2);
        foreach (['G', 'H', 'I', 'J', 'K', 'L'] as $c) {
            $rows .= $blank($c . $y, 3);
        }
        $rows .= '</row>';
    }

    $widths = [5, 38, 16, 18, 22, 28, 12, 18, 18, 10, 11, 30];
    $colsXml = '';
    foreach ($widths as $i => $w) {
        $colsXml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
    }
    $dv = function (string $col, string $list, string $title) use ($last): string {
        return '<dataValidation type="list" allowBlank="1" showErrorMessage="1" errorTitle="' . $title
             . '" error="Bitte einen Wert aus der Liste wählen." sqref="' . $col . '6:' . $col . $last . '">'
             . '<formula1>"' . $list . '"</formula1></dataValidation>';
    };

    $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $hdr = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $sheet = $hdr . '<worksheet xmlns="' . $ns . '">'
        . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
        . '<dimension ref="A1:L' . $last . '"/>'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/>'
        . '<selection pane="bottomLeft" activeCell="G6" sqref="G6"/></sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="15"/>'
        . '<cols>' . $colsXml . '</cols>'
        . '<sheetData>' . $rows . '</sheetData>'
        . '<autoFilter ref="A5:L' . $last . '"/>'
        . '<dataValidations count="3">'
        . $dv('G', 'ja,nein', 'Verfügbar')
        . $dv('H', 'Bio,Umstellung,konventionell', 'Qualität')
        . $dv('I', 'Samen,Stecklinge,Jungpflanzen,Fertigpflanzen,Anderes', 'Form')
        . '</dataValidations>'
        . '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>'
        . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
        . '</worksheet>';

    $border = '<border><left style="thin"><color rgb="FFBFBFBF"/></left><right style="thin"><color rgb="FFBFBFBF"/></right>'
            . '<top style="thin"><color rgb="FFBFBFBF"/></top><bottom style="thin"><color rgb="FFBFBFBF"/></bottom><diagonal/></border>';
    $styles = $hdr . '<styleSheet xmlns="' . $ns . '">'
        . '<fonts count="4">'
        . '<font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="14"/><name val="Calibri"/></font>'
        . '<font><i/><sz val="10"/><color rgb="FF595959"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="5">'
        . '<fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF4B8223"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFFFF8DC"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF2F5D8A"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>' . $border . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="7">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '<xf numFmtId="0" fontId="1" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';

    $rel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    $parts = [
        '[Content_Types].xml' => $hdr . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>',
        '_rels/.rels' => $hdr . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="' . $rel . '/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml' => $hdr . '<workbook xmlns="' . $ns . '" xmlns:r="' . $rel . '">'
            . '<bookViews><workbookView/></bookViews><sheets><sheet name="Anfrage" sheetId="1" r:id="rId1"/></sheets>'
            . '<definedNames><definedName name="_xlnm.Print_Titles" localSheetId="0">Anfrage!$5:$5</definedName></definedNames>'
            . '</workbook>',
        'xl/_rels/workbook.xml.rels' => $hdr . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="' . $rel . '/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="' . $rel . '/styles" Target="styles.xml"/></Relationships>',
        'xl/worksheets/sheet1.xml' => $sheet,
        'xl/styles.xml' => $styles,
    ];

    $tmp = tempnam(sys_get_temp_dir(), 'anf');
    $zip = new ZipArchive();
    if ($tmp === false || $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        error_log('PDB Anfrage: ZIP konnte nicht angelegt werden.');
        return pdb_anfrage_json(['error' => 'Excel-Datei konnte nicht erstellt werden.'], 500);
    }
    foreach ($parts as $name => $xml) {
        $zip->addFromString($name, $xml);
    }
    $zip->close();
    $body = (string)file_get_contents($tmp);
    @unlink($tmp);

    $file = 'Anfrage_' . pdb_anfrage_file_slug($data['title']) . '_' . date('Y-m-d') . '.xlsx';
    return [$body, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 200,
            ['Content-Disposition' => 'attachment; filename="' . $file . '"']];
}

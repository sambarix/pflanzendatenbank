<?php
/**
 * PDB - Vorschlaege der Gaertnerien an die oeffentliche PDB (Umbau A, Stufe 2).
 * Reine Bibliotheksdatei - wird von _common.php eingebunden, nie direkt per HTTP.
 *
 * Idee: Teilt eine Gaertnerei eine Pflanze (Schalter "share", Standard an), legt jedes Speichern in der
 * Eingabe eine Momentaufnahme der Pflanze in vorschlag.db ab (ein Datensatz pro Gaertnerei und Pflanze).
 * Editoren pruefen die Vorschlaege feldweise (Seite "Vorschlaege") und uebernehmen sie in die plants.db.
 *  - Die Momentaufnahme enthaelt nur die Felder des Eingabeformulars (Katalog _vorschlag_fields.php, generiert)
 *    plus Kategorien und Synonyme. Bestand, Preise und das Feld "Notizen" gehen nie an die PDB.
 *  - Wiedervorlage: Ein abgelehnter oder uebernommener Vorschlag wird nur dann wieder offen, wenn sich der
 *    Inhalt (Hash der Momentaufnahme) danach aendert, nicht schon beim blossen erneuten Speichern.
 *  - Der Pfad der vorschlag.db ist fest; die Gaertnerei wird serverseitig aus dem Login bestimmt.
 */

require_once __DIR__ . '/_vorschlag_fields.php';

/** Formularfelder, die nie an die oeffentliche PDB gehen (bleiben bei der Gaertnerei). */
const PDB_VORSCHLAG_NOT_SHARED = ['plants.notes'];

function pdb_vorschlag_path(): string
{
    return GRAV_ROOT . '/user/data/sqlite/vorschlag.db';
}

/**
 * vorschlag.db oeffnen. $write = true: Datei und Tabellen bei Bedarf anlegen, lesen und schreiben.
 * $write = false: nur lesend, null wenn die Datei noch nicht existiert.
 */
function pdb_vorschlag_open(bool $write = true): ?SQLite3
{
    $p = pdb_vorschlag_path();
    if (!$write) {
        if (!is_file($p)) {
            return null;
        }
        $db = new SQLite3($p, SQLITE3_OPEN_READONLY);
        $db->enableExceptions(true);
        $db->busyTimeout(5000);
        return $db;
    }
    $db = new SQLite3($p, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
    $db->enableExceptions(true);
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode=WAL');
    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS vorschlag (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    nursery_key      TEXT NOT NULL,
    nursery_name     TEXT,
    local_plant_id   INTEGER NOT NULL,
    botanical_name   TEXT,
    master_plant_id  INTEGER,
    master_seen_at   TEXT,
    status           TEXT NOT NULL DEFAULT 'offen',
    content_hash     TEXT,
    snapshot         TEXT NOT NULL,
    result_master_id INTEGER,
    decision         TEXT,
    decision_note    TEXT,
    decided_by       TEXT,
    submitted_at     TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at       TEXT DEFAULT CURRENT_TIMESTAMP,
    decided_at       TEXT,
    UNIQUE (nursery_key, local_plant_id)
)
SQL);
    $db->exec('CREATE INDEX IF NOT EXISTS idx_vorschlag_status ON vorschlag(status)');
    return $db;
}

// ============================================================
// Feldkatalog
// ============================================================

/** Abschnitte und Felder, die vorgeschlagen werden duerfen (generierter Katalog ohne nicht geteilte Felder). */
function pdb_vorschlag_catalog(): array
{
    static $c = null;
    if ($c === null) {
        $c = [];
        foreach (pdb_vorschlag_fields() as $s) {
            $fields = [];
            foreach ($s['fields'] as $f) {
                if (!in_array($f['key'], PDB_VORSCHLAG_NOT_SHARED, true)) {
                    $f['section'] = $s['key'];
                    $fields[] = $f;
                }
            }
            if ($fields) {
                $s['fields'] = $fields;
                $c[] = $s;
            }
        }
    }
    return $c;
}

/** Schluessel (tabelle.spalte) => Felddefinition. */
function pdb_vorschlag_index(): array
{
    static $ix = null;
    if ($ix === null) {
        $ix = [];
        foreach (pdb_vorschlag_catalog() as $s) {
            foreach ($s['fields'] as $f) {
                $ix[$f['key']] = $f;
            }
        }
    }
    return $ix;
}

/** Wert vergleichbar machen (leer = ''); die Form richtet sich nach dem Feldtyp. */
function pdb_vorschlag_norm($v, string $type): string
{
    if ($v === null || is_array($v)) {
        return '';
    }
    if (is_bool($v)) {
        $v = $v ? '1' : '0';
    }
    $s = trim((string)$v);
    if ($type === 'checkbox') {
        return ($s === '1' || strtolower($s) === 'true') ? '1' : '';
    }
    if ($s === '') {
        return '';
    }
    if ($type === 'number') {
        return is_numeric($s) ? (string)(0 + $s) : $s;
    }
    if ($type === 'multi') {
        // Reihenfolge der Auswahl bleibt erhalten (Klick-Reihenfolge im Formular)
        return implode(',', pdb_vorschlag_multi_parts($s));
    }
    return str_replace("\r\n", "\n", $s);
}

/** Einzelwerte eines Mehrfachfelds (kommagetrennt): getrimmt, ohne Leere und Doppelte, Reihenfolge erhalten. */
function pdb_vorschlag_multi_parts(string $s): array
{
    return array_values(array_unique(array_filter(array_map('trim', explode(',', $s)), 'strlen')));
}

/** Zwei normalisierte Werte gleich? Bei Mehrfachfeldern zaehlt die Reihenfolge nicht. */
function pdb_vorschlag_equal(string $a, string $b, string $type): bool
{
    if ($a === $b) {
        return true;
    }
    if ($type !== 'multi') {
        return false;
    }
    $x = pdb_vorschlag_multi_parts($a);
    $y = pdb_vorschlag_multi_parts($b);
    sort($x, SORT_STRING);
    sort($y, SORT_STRING);
    return $x === $y;
}

// ============================================================
// Momentaufnahme und Vergleich
// ============================================================

/**
 * Momentaufnahme einer Pflanze aus einer Datenbank mit dem Pflanzenschema (user.db oder plants.db).
 * @return array{f:array<string,string>,cat:string[],syn:array,meta:array}|null
 */
function pdb_vorschlag_snapshot(SQLite3 $db, int $plantId): ?array
{
    $st = $db->prepare('SELECT * FROM plants WHERE id = :id');
    $st->bindValue(':id', $plantId, SQLITE3_INTEGER);
    $plant = $st->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$plant) {
        return null;
    }

    $rows = ['plants' => $plant];
    $f = [];
    foreach (pdb_vorschlag_index() as $key => $def) {
        $t = $def['table'];
        if (!array_key_exists($t, $rows)) {
            $q = $db->prepare("SELECT * FROM \"$t\" WHERE plant_id = :id ORDER BY id LIMIT 1");
            $q->bindValue(':id', $plantId, SQLITE3_INTEGER);
            $rows[$t] = $q->execute()->fetchArray(SQLITE3_ASSOC) ?: [];
        }
        $v = pdb_vorschlag_norm($rows[$t][$def['col']] ?? null, $def['type']);
        if ($v !== '') {
            $f[$key] = $v;
        }
    }
    ksort($f);

    $cat = [];
    $q = $db->prepare('SELECT category_path FROM plant_categories WHERE plant_id = :id ORDER BY sort_order, id');
    $q->bindValue(':id', $plantId, SQLITE3_INTEGER);
    $r = $q->execute();
    while ($x = $r->fetchArray(SQLITE3_NUM)) {
        $p = trim((string)$x[0]);
        if ($p !== '' && !in_array($p, $cat, true)) {
            $cat[] = $p;
        }
    }

    $syn = [];
    $q = $db->prepare('SELECT synonym_name, language, synonym_type FROM plant_synonyms WHERE plant_id = :id ORDER BY id');
    $q->bindValue(':id', $plantId, SQLITE3_INTEGER);
    $r = $q->execute();
    while ($x = $r->fetchArray(SQLITE3_ASSOC)) {
        $s = ['name' => trim((string)$x['synonym_name']), 'lang' => trim((string)$x['language']), 'type' => trim((string)$x['synonym_type'])];
        if ($s['name'] !== '') {
            $syn[pdb_vorschlag_syn_key($s)] = $s;
        }
    }

    return [
        'f'    => $f,
        'cat'  => $cat,
        'syn'  => array_values($syn),
        'meta' => [
            'botanical_name' => (string)($plant['botanical_name'] ?? ''),
            'web_name'       => (string)($plant['web_name'] ?? ''),
            'updated_at'     => (string)($plant['updated_at'] ?? ''),
        ],
    ];
}

function pdb_vorschlag_syn_key(array $s): string
{
    return mb_strtolower($s['name']) . '|' . $s['lang'] . '|' . $s['type'];
}

/** Inhalts-Hash einer Momentaufnahme (Reihenfolge der Listen spielt keine Rolle). */
function pdb_vorschlag_hash(array $snap): string
{
    $cat = $snap['cat'];
    sort($cat, SORT_STRING);
    $syn = array_map('pdb_vorschlag_syn_key', $snap['syn']);
    sort($syn, SORT_STRING);
    return sha1(json_encode(['f' => $snap['f'], 'cat' => $cat, 'syn' => $syn], JSON_UNESCAPED_UNICODE));
}

/**
 * Vergleicht den Vorschlag mit der Pflanze in der plants.db ($master = null: neue Pflanze).
 * Gibt nur abweichende Felder zurueck. "adoptable" zaehlt, was ein Editor sinnvoll uebernehmen kann:
 * Felder mit einem Vorschlagswert (reines Leeren zaehlt nicht), neue Kategorien und neue Synonyme.
 * Namen bestehender Pflanzen sind gesperrt (sichtbar, aber nicht uebernehmbar).
 */
function pdb_vorschlag_diff(array $snap, ?array $master): array
{
    $isNew = ($master === null);
    $rows = [];
    $adoptable = 0;
    $same = 0;
    foreach (pdb_vorschlag_catalog() as $s) {
        foreach ($s['fields'] as $def) {
            $k = $def['key'];
            $p = $snap['f'][$k] ?? '';
            $m = $isNew ? '' : ($master['f'][$k] ?? '');
            if (pdb_vorschlag_equal($p, $m, $def['type'])) {
                if ($p !== '') {
                    $same++;
                }
                continue;
            }
            $locked = !empty($def['taxonomy']) && !$isNew;
            if (!$locked && $p !== '') {
                $adoptable++;
            }
            $rows[] = [
                'key'          => $k,
                'section'      => $s['key'],
                'section_title' => $s['title'],
                'label'        => $def['label'],
                'type'         => $def['type'],
                'options'      => $def['options'] ?? null,
                'master'       => $m,
                'value'        => $p,
                'locked'       => $locked,
                'required'     => $isNew && !empty($def['taxonomy']),
                // vorab angehakt: in der PDB leer und Vorschlag gefuellt; bei neuen Pflanzen alles Gefuellte
                'checked'      => !$locked && $p !== '' && ($isNew || $m === ''),
            ];
        }
    }

    $masterCats = $isNew ? [] : $master['cat'];
    $cats = [];
    foreach ($snap['cat'] as $c) {
        if (!in_array($c, $masterCats, true)) {
            $cats[] = ['path' => $c, 'checked' => !$masterCats];
            $adoptable++;
        }
    }
    $masterSyn = [];
    if (!$isNew) {
        foreach ($master['syn'] as $s) {
            $masterSyn[pdb_vorschlag_syn_key($s)] = true;
        }
    }
    $syns = [];
    foreach ($snap['syn'] as $s) {
        $k = pdb_vorschlag_syn_key($s);
        if (!isset($masterSyn[$k])) {
            $syns[] = ['key' => $k, 'name' => $s['name'], 'lang' => $s['lang'], 'type' => $s['type'], 'checked' => !$masterSyn];
            $adoptable++;
        }
    }

    return [
        'rows'        => $rows,
        'cats'        => $cats,
        'syns'        => $syns,
        'master_cats' => $masterCats,
        'adoptable'   => $adoptable,
        'same'        => $same,
    ];
}

// ============================================================
// Einreichen / Zurueckziehen (Gaertnerei-Seite)
// ============================================================

/** ID der Pflanze mit diesem botanischen Namen in der plants.db, 0 wenn es sie nicht gibt. */
function pdb_vorschlag_master_id_by_name(SQLite3 $master, string $name): int
{
    if ($name === '') {
        return 0;
    }
    $st = $master->prepare('SELECT id FROM plants WHERE botanical_name = :n LIMIT 1');
    $st->bindValue(':n', $name, SQLITE3_TEXT);
    $row = $st->execute()->fetchArray(SQLITE3_NUM);
    return $row ? (int)$row[0] : 0;
}

/**
 * Nach dem Speichern einer Pflanze in der Gaertnerei-DB: Vorschlag anlegen, aktualisieren oder zurueckziehen.
 * Wirft nie; ein Fehler hier darf das Speichern nicht verhindern.
 *
 * @param array{key:string,name:string} $nursery
 * @return string offen | zurueckgezogen | nicht_geteilt | keine_abweichung | unveraendert | stammdaten_fehlen | fehler
 */
function pdb_vorschlag_sync(SQLite3 $user, array $nursery, int $localId, ?SQLite3 $master): string
{
    try {
        $key = (string)($nursery['key'] ?? '');
        if ($key === '') {
            return 'fehler';
        }
        $loc = $user->querySingle('SELECT master_plant_id, share FROM plant_local WHERE plant_id = ' . $localId, true);
        if (!$loc) {
            return 'fehler';
        }
        $share = ((int)$loc['share']) === 1;

        $v = pdb_vorschlag_open(true);
        try {
            $q = $v->prepare('SELECT * FROM vorschlag WHERE nursery_key = :k AND local_plant_id = :p');
            $q->bindValue(':k', $key, SQLITE3_TEXT);
            $q->bindValue(':p', $localId, SQLITE3_INTEGER);
            $row = $q->execute()->fetchArray(SQLITE3_ASSOC) ?: null;

            $setStatus = function (string $status) use ($v, $row): void {
                $u = $v->prepare("UPDATE vorschlag SET status = :s, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                $u->bindValue(':s', $status, SQLITE3_TEXT);
                $u->bindValue(':id', (int)$row['id'], SQLITE3_INTEGER);
                $u->execute();
            };

            if (!$share) {
                if ($row && $row['status'] === 'offen') {
                    $setStatus('zurueckgezogen');
                    return 'zurueckgezogen';
                }
                return 'nicht_geteilt';
            }

            $snap = pdb_vorschlag_snapshot($user, $localId);
            if (!$snap) {
                return 'fehler';
            }

            // Verknuepfung zur PDB: ueber plant_local, sonst ueber den botanischen Namen
            $masterId = (int)($loc['master_plant_id'] ?? 0);
            if ($masterId <= 0 && $master) {
                $by = pdb_vorschlag_master_id_by_name($master, $snap['meta']['botanical_name']);
                if ($by > 0 && pdb_userdb_local_id_for_master($user, $by) === 0) {
                    pdb_vorschlag_link_one($user, $master, $localId, $by);
                    $masterId = $by;
                }
            }
            $msnap = null;
            if ($masterId > 0) {
                if (!$master) {
                    return 'stammdaten_fehlen';
                }
                $msnap = pdb_vorschlag_snapshot($master, $masterId);
                if (!$msnap) {
                    $masterId = 0; // die PDB kennt die Pflanze nicht (mehr): wie eine neue behandeln
                }
            }

            if ($msnap) {
                $d = pdb_vorschlag_diff($snap, $msnap);
                if ($d['adoptable'] === 0) {
                    if ($row && $row['status'] === 'offen') {
                        $setStatus('zurueckgezogen');
                    }
                    return 'keine_abweichung';
                }
            }

            $hash = pdb_vorschlag_hash($snap);
            $json = json_encode($snap, JSON_UNESCAPED_UNICODE);
            $seen = $msnap ? (string)$msnap['meta']['updated_at'] : null;

            if (!$row) {
                $i = $v->prepare('INSERT INTO vorschlag (nursery_key, nursery_name, local_plant_id, botanical_name, master_plant_id, master_seen_at, status, content_hash, snapshot)
                                  VALUES (:k, :n, :p, :b, :m, :seen, \'offen\', :h, :j)');
                $i->bindValue(':k', $key, SQLITE3_TEXT);
                $i->bindValue(':n', (string)($nursery['name'] ?? ''), SQLITE3_TEXT);
                $i->bindValue(':p', $localId, SQLITE3_INTEGER);
                $i->bindValue(':b', $snap['meta']['botanical_name'], SQLITE3_TEXT);
                $i->bindValue(':m', $masterId > 0 ? $masterId : null, $masterId > 0 ? SQLITE3_INTEGER : SQLITE3_NULL);
                $i->bindValue(':seen', $seen, $seen !== null ? SQLITE3_TEXT : SQLITE3_NULL);
                $i->bindValue(':h', $hash, SQLITE3_TEXT);
                $i->bindValue(':j', $json, SQLITE3_TEXT);
                $i->execute();
                return 'offen';
            }

            $wasOpen = $row['status'] === 'offen';
            if (!$wasOpen && $row['status'] !== 'zurueckgezogen' && $row['content_hash'] === $hash) {
                return 'unveraendert'; // abgelehnt oder uebernommen, Inhalt nicht geaendert: keine Wiedervorlage
            }
            $u = $v->prepare("UPDATE vorschlag SET nursery_name = :n, botanical_name = :b, master_plant_id = :m, master_seen_at = :seen,
                                  status = 'offen', content_hash = :h, snapshot = :j, updated_at = CURRENT_TIMESTAMP"
                . ($wasOpen ? '' : ", submitted_at = CURRENT_TIMESTAMP, decision = NULL, decision_note = NULL, decided_by = NULL, decided_at = NULL, result_master_id = NULL")
                . ' WHERE id = :id');
            $u->bindValue(':n', (string)($nursery['name'] ?? ''), SQLITE3_TEXT);
            $u->bindValue(':b', $snap['meta']['botanical_name'], SQLITE3_TEXT);
            $u->bindValue(':m', $masterId > 0 ? $masterId : null, $masterId > 0 ? SQLITE3_INTEGER : SQLITE3_NULL);
            $u->bindValue(':seen', $seen, $seen !== null ? SQLITE3_TEXT : SQLITE3_NULL);
            $u->bindValue(':h', $hash, SQLITE3_TEXT);
            $u->bindValue(':j', $json, SQLITE3_TEXT);
            $u->bindValue(':id', (int)$row['id'], SQLITE3_INTEGER);
            $u->execute();
            return 'offen';
        } finally {
            $v->close();
        }
    } catch (\Throwable $e) {
        error_log('PDB Vorschlag sync: ' . $e->getMessage());
        return 'fehler';
    }
}

/** Kleines Statusschild fuer die Bestandsliste der Gaertnerei (leer, wenn nichts anzuzeigen ist). */
function pdb_vorschlag_badge_html(array $vs): string
{
    $note = trim((string)($vs['note'] ?? ''));
    switch ($vs['status'] ?? '') {
        case 'offen':
            $label = 'Vorschlag offen';
            $title = 'Ihr Vorschlag wird von den Editoren der öffentlichen PDB geprüft';
            break;
        case 'uebernommen':
            $label = 'in PDB übernommen';
            $title = 'Ihr Vorschlag wurde in die öffentliche PDB übernommen';
            break;
        case 'abgelehnt':
            $label = 'abgelehnt';
            $title = 'Der Vorschlag wurde nicht übernommen' . ($note !== '' ? ': ' . $note : '');
            break;
        default:
            return '';
    }
    return ' <span class="pdb-vs pdb-vs-' . htmlspecialchars((string)$vs['status']) . '" title="' . htmlspecialchars($title) . '">'
        . htmlspecialchars($label) . '</span>';
}

/** Status der Vorschlaege einer Gaertnerei: lokale Pflanzen-ID => [status, note, decided_at]. */
function pdb_vorschlag_status_for(string $nurseryKey): array
{
    $out = [];
    try {
        $v = pdb_vorschlag_open(false);
        if (!$v) {
            return [];
        }
        try {
            $q = $v->prepare('SELECT local_plant_id, status, decision_note, decided_at FROM vorschlag WHERE nursery_key = :k');
            $q->bindValue(':k', $nurseryKey, SQLITE3_TEXT);
            $r = $q->execute();
            while ($x = $r->fetchArray(SQLITE3_ASSOC)) {
                $out[(int)$x['local_plant_id']] = [
                    'status' => (string)$x['status'],
                    'note'   => (string)($x['decision_note'] ?? ''),
                    'at'     => (string)($x['decided_at'] ?? ''),
                ];
            }
        } finally {
            $v->close();
        }
    } catch (\Throwable $e) {
        error_log('PDB Vorschlag Status: ' . $e->getMessage());
    }
    return $out;
}

// ============================================================
// Verknuepfung eigener Pflanzen mit der PDB (Gaertnerei-Seite)
// ============================================================

/**
 * Verknuepft rein eigene Pflanzen (ohne master_plant_id) mit der PDB, sobald es die Pflanze dort gibt:
 * entweder weil ein Editor den Vorschlag uebernommen hat oder weil der botanische Name inzwischen in der
 * plants.db steht. Laeuft bei jedem Zugriff der Gaertnerei (nur wenn es unverknuepfte Pflanzen gibt) und wirft nie.
 */
function pdb_vorschlag_link_plants(SQLite3 $user, string $nurseryKey): void
{
    try {
        if (!pdb_userdb_table_exists($user, 'plant_local')) {
            return;
        }
        $open = [];
        $r = $user->query('SELECT l.plant_id, p.botanical_name FROM plant_local l JOIN plants p ON p.id = l.plant_id WHERE l.master_plant_id IS NULL');
        while ($x = $r->fetchArray(SQLITE3_NUM)) {
            $open[(int)$x[0]] = (string)$x[1];
        }
        if (!$open) {
            return;
        }
        $master = pdb_userdb_open_master();
        if (!$master) {
            return;
        }
        try {
            $adopted = [];
            $v = pdb_vorschlag_open(false);
            if ($v) {
                try {
                    $q = $v->prepare("SELECT local_plant_id, result_master_id FROM vorschlag WHERE nursery_key = :k AND status = 'uebernommen' AND result_master_id IS NOT NULL");
                    $q->bindValue(':k', $nurseryKey, SQLITE3_TEXT);
                    $rr = $q->execute();
                    while ($x = $rr->fetchArray(SQLITE3_NUM)) {
                        $adopted[(int)$x[0]] = (int)$x[1];
                    }
                } finally {
                    $v->close();
                }
            }
            foreach ($open as $localId => $name) {
                $masterId = $adopted[$localId] ?? 0;
                if ($masterId > 0 && (int)$master->querySingle('SELECT COUNT(*) FROM plants WHERE id = ' . $masterId) === 0) {
                    $masterId = 0;
                }
                if ($masterId <= 0) {
                    $masterId = pdb_vorschlag_master_id_by_name($master, $name);
                }
                if ($masterId > 0) {
                    pdb_vorschlag_link_one($user, $master, $localId, $masterId);
                }
            }
        } finally {
            $master->close();
        }
    } catch (\Throwable $e) {
        error_log('PDB Vorschlag Verknuepfung: ' . $e->getMessage());
    }
}

/**
 * Eigene Pflanze mit der Pflanze der PDB verknuepfen. Weicht der Name ab (Editor hat ihn bei der Uebernahme
 * korrigiert), wird er an die PDB angeglichen, sofern er in der eigenen DB nicht schon vergeben ist.
 */
function pdb_vorschlag_link_one(SQLite3 $user, SQLite3 $master, int $localId, int $masterId): void
{
    if (pdb_userdb_local_id_for_master($user, $masterId) > 0) {
        return; // diese PDB-Pflanze ist schon mit einer anderen eigenen Pflanze verknuepft
    }
    $st = $master->prepare('SELECT genus, species, subspecies, cultivar, ecotype, botanical_name, web_name, matchcode, updated_at FROM plants WHERE id = :id');
    $st->bindValue(':id', $masterId, SQLITE3_INTEGER);
    $m = $st->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$m) {
        return;
    }
    $user->exec('BEGIN IMMEDIATE');
    try {
        $u = $user->prepare('UPDATE plant_local SET master_plant_id = :m, master_updated_at = :u, updated_at = CURRENT_TIMESTAMP
                             WHERE plant_id = :p AND master_plant_id IS NULL');
        $u->bindValue(':m', $masterId, SQLITE3_INTEGER);
        $u->bindValue(':u', (string)($m['updated_at'] ?? ''), SQLITE3_TEXT);
        $u->bindValue(':p', $localId, SQLITE3_INTEGER);
        $u->execute();

        $cur = (string)$user->querySingle('SELECT botanical_name FROM plants WHERE id = ' . $localId);
        if ($cur !== (string)$m['botanical_name']) {
            $d = $user->prepare('SELECT COUNT(*) FROM plants WHERE botanical_name = :n AND id != :p');
            $d->bindValue(':n', (string)$m['botanical_name'], SQLITE3_TEXT);
            $d->bindValue(':p', $localId, SQLITE3_INTEGER);
            if ((int)$d->execute()->fetchArray(SQLITE3_NUM)[0] === 0) {
                $n = $user->prepare('UPDATE plants SET genus = :g, species = :s, subspecies = :ss, cultivar = :c, ecotype = :e,
                                         botanical_name = :b, web_name = :w, matchcode = :mc WHERE id = :p');
                foreach ([':g' => 'genus', ':s' => 'species', ':ss' => 'subspecies', ':c' => 'cultivar', ':e' => 'ecotype',
                          ':b' => 'botanical_name', ':w' => 'web_name', ':mc' => 'matchcode'] as $ph => $col) {
                    $val = $m[$col];
                    $n->bindValue($ph, $val, $val === null ? SQLITE3_NULL : SQLITE3_TEXT);
                }
                $n->bindValue(':p', $localId, SQLITE3_INTEGER);
                $n->execute();
            }
        }
        $user->exec('COMMIT');
    } catch (\Throwable $e) {
        try { $user->exec('ROLLBACK'); } catch (\Throwable $e2) { /* nichts offen */ }
        throw $e;
    }
}

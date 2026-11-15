<?php
/**
 * PDB - Pflanzendaten in der Gaertnerei-DB (user.db). Umbau A, Stufe 1.
 * Reine Bibliotheksdatei - wird von _common.php eingebunden, nie direkt per HTTP.
 *
 * Idee: Jede Gaertnerei-DB enthaelt die ganzen Pflanzen, die sie fuehrt (gleiches Schema wie
 * plants.db, siehe _userdb_schema.php). Bestand, Export und Eingabe lesen nur noch die user.db.
 *  - Kopie einer Master-Pflanze: behaelt die Master-ID als lokale ID (so bleiben bestehende
 *    Bestandszeilen gueltig).
 *  - Rein lokal angelegte Pflanze: lokale ID ab PDB_LOCAL_ID_START (kollidiert nie mit Master-IDs).
 *  - Tabelle plant_local haelt pro Pflanze die Verknuepfung zum Master (master_plant_id),
 *    Herkunft ('kopie' | 'neu'), "lokal geaendert" und den Freigabe-Schalter (share, wirkt ab Stufe 2).
 */

require_once __DIR__ . '/_userdb_schema.php';

/** Lokale IDs rein lokal angelegter Pflanzen beginnen hier. */
const PDB_LOCAL_ID_START = 1000000;

function pdb_userdb_master_path(): string
{
    return GRAV_ROOT . '/user/data/sqlite/plants.db';
}

/** Stammdaten-DB nur lesend oeffnen; null, wenn nicht vorhanden oder nicht lesbar. */
function pdb_userdb_open_master(): ?SQLite3
{
    $p = pdb_userdb_master_path();
    if (!is_file($p)) {
        return null;
    }
    try {
        $m = new SQLite3($p, SQLITE3_OPEN_READONLY);
        $m->busyTimeout(3000);
        return $m;
    } catch (\Throwable $e) {
        error_log('PDB: plants.db nicht lesbar: ' . $e->getMessage());
        return null;
    }
}

function pdb_userdb_table_exists(SQLite3 $db, string $table): bool
{
    $st = $db->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :n");
    $st->bindValue(':n', $table, SQLITE3_TEXT);
    return (bool)$st->execute()->fetchArray();
}

/** Tabelle plant_local (Verknuepfung zum Master, Herkunft, Freigabe-Schalter). */
function pdb_userdb_local_ddl(): array
{
    return [
        <<<'SQL'
CREATE TABLE IF NOT EXISTS plant_local (
    plant_id          INTEGER PRIMARY KEY,
    master_plant_id   INTEGER,
    origin            TEXT NOT NULL DEFAULT 'kopie',
    master_updated_at TEXT,
    locally_changed   INTEGER NOT NULL DEFAULT 0,
    share             INTEGER NOT NULL DEFAULT 1,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
)
SQL,
        'CREATE INDEX IF NOT EXISTS idx_plant_local_master ON plant_local(master_plant_id)',
    ];
}

/**
 * Stellt die Pflanzentabellen einer Gaertnerei-DB sicher (laeuft bei jedem Zugriff, ist idempotent):
 *  1. Fehlen die Tabellen, werden sie angelegt (Schema wie plants.db plus plant_local).
 *  2. Bestandszeilen, deren Pflanze noch nicht in der eigenen DB liegt (aeltere Konten), werden aus der
 *     plants.db kopiert - die lokale ID bleibt gleich der Master-ID. Ist die plants.db gerade nicht lesbar,
 *     wird das beim naechsten Zugriff nachgeholt; es geht nichts verloren.
 * Jeder Schritt laeuft atomar in einer eigenen Transaktion.
 */
function pdb_userdb_ensure_plants(SQLite3 $db): void
{
    $db->enableExceptions(true);

    try {
        // 1. Tabellen
        if (!pdb_userdb_table_exists($db, 'plants') || !pdb_userdb_table_exists($db, 'plant_local')) {
            $db->exec('BEGIN IMMEDIATE');
            try {
                foreach (pdb_userdb_plant_ddl() as $sql) {
                    $db->exec($sql);
                }
                foreach (pdb_userdb_local_ddl() as $sql) {
                    $db->exec($sql);
                }
                $db->exec('COMMIT');
            } catch (\Throwable $e) {
                try { $db->exec('ROLLBACK'); } catch (\Throwable $e2) { /* nichts offen */ }
                throw $e;
            }
        }

        // 2. Bestand ohne lokale Pflanze
        $orphans = [];
        foreach (['plant_inventory', 'inventory_overrides'] as $t) {
            if (pdb_userdb_table_exists($db, $t)) {
                $r = $db->query("SELECT DISTINCT x.plant_id FROM $t x
                                 WHERE x.plant_id IS NOT NULL
                                   AND NOT EXISTS (SELECT 1 FROM plants p WHERE p.id = x.plant_id)");
                while ($x = $r->fetchArray(SQLITE3_NUM)) {
                    $orphans[(int)$x[0]] = true;
                }
            }
        }
        if (!$orphans) {
            return;
        }
        $master = pdb_userdb_open_master();
        if (!$master) {
            return; // spaeter nochmals versuchen
        }
        try {
            $db->exec('BEGIN IMMEDIATE');
            try {
                foreach (array_keys($orphans) as $id) {
                    pdb_userdb_copy_plant($db, $master, $id);
                }
                $db->exec('COMMIT');
            } catch (\Throwable $e) {
                try { $db->exec('ROLLBACK'); } catch (\Throwable $e2) { /* nichts offen */ }
                throw $e;
            }
        } finally {
            $master->close();
        }
    } catch (\Throwable $e) {
        error_log('PDB user.db Migration: ' . $e->getMessage());
    }
}

/** Spaltennamen einer Tabelle (leere Ergebnisse werden nicht gemerkt). */
function pdb_userdb_columns(SQLite3 $db, string $table): array
{
    static $cache = [];
    $key = spl_object_id($db) . ':' . $table;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $cols = [];
    $r = $db->query('PRAGMA table_info("' . $table . '")');
    while ($c = $r->fetchArray(SQLITE3_ASSOC)) {
        $cols[$c['name']] = true;
    }
    if ($cols) {
        $cache[$key] = $cols;
    }
    return $cols;
}

/** Eine Zeile einfuegen; nur Spalten, die es in der Zieltabelle gibt. */
function pdb_userdb_insert_row(SQLite3 $db, string $table, array $row): void
{
    $cols = pdb_userdb_columns($db, $table);
    $names = [];
    $holders = [];
    $vals = [];
    foreach ($row as $k => $v) {
        if (isset($cols[$k])) {
            $names[] = '"' . $k . '"';
            $holders[] = ':c_' . $k;
            $vals[':c_' . $k] = $v;
        }
    }
    if (!$names) {
        return;
    }
    $st = $db->prepare('INSERT INTO "' . $table . '" (' . implode(', ', $names) . ') VALUES (' . implode(', ', $holders) . ')');
    foreach ($vals as $k => $v) {
        if ($v === null) {
            $st->bindValue($k, null, SQLITE3_NULL);
        } elseif (is_int($v)) {
            $st->bindValue($k, $v, SQLITE3_INTEGER);
        } elseif (is_float($v)) {
            $st->bindValue($k, $v, SQLITE3_FLOAT);
        } else {
            $st->bindValue($k, (string)$v, SQLITE3_TEXT);
        }
    }
    $st->execute();
}

/**
 * Kopiert eine Master-Pflanze (plants + alle Detailtabellen) in die Gaertnerei-DB.
 * Die lokale ID ist gleich der Master-ID. Gibt die lokale ID zurueck, 0 wenn der Master die Pflanze nicht kennt.
 * Aufrufer sorgt fuer die Transaktion.
 */
function pdb_userdb_copy_plant(SQLite3 $user, SQLite3 $master, int $masterId): int
{
    $st = $master->prepare('SELECT * FROM plants WHERE id = :id');
    $st->bindValue(':id', $masterId, SQLITE3_INTEGER);
    $plant = $st->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$plant) {
        return 0;
    }
    if ((int)$user->querySingle('SELECT COUNT(*) FROM plants WHERE id = ' . $masterId) > 0) {
        return $masterId; // schon da
    }

    static $masterTables = [];
    $mk = spl_object_id($master);
    if (!isset($masterTables[$mk])) {
        $masterTables[$mk] = [];
        $r = $master->query("SELECT name FROM sqlite_master WHERE type = 'table'");
        while ($x = $r->fetchArray(SQLITE3_NUM)) {
            $masterTables[$mk][$x[0]] = true;
        }
    }

    pdb_userdb_insert_row($user, 'plants', $plant);
    foreach (array_slice(pdb_userdb_plant_tables(), 1) as $t) {
        if (empty($masterTables[$mk][$t])) {
            continue;
        }
        $q = $master->prepare("SELECT * FROM \"$t\" WHERE plant_id = :id ORDER BY id");
        $q->bindValue(':id', $masterId, SQLITE3_INTEGER);
        $res = $q->execute();
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            unset($row['id']);
            pdb_userdb_insert_row($user, $t, $row);
        }
    }

    $ins = $user->prepare("INSERT OR REPLACE INTO plant_local (plant_id, master_plant_id, origin, master_updated_at)
                           VALUES (:p, :m, 'kopie', :u)");
    $ins->bindValue(':p', $masterId, SQLITE3_INTEGER);
    $ins->bindValue(':m', $masterId, SQLITE3_INTEGER);
    $ins->bindValue(':u', (string)($plant['updated_at'] ?? ''), SQLITE3_TEXT);
    $ins->execute();

    return $masterId;
}

/** Lokale ID der Kopie einer Master-Pflanze, 0 wenn nicht vorhanden. */
function pdb_userdb_local_id_for_master(SQLite3 $user, int $masterId): int
{
    $st = $user->prepare('SELECT plant_id FROM plant_local WHERE master_plant_id = :m LIMIT 1');
    $st->bindValue(':m', $masterId, SQLITE3_INTEGER);
    $row = $st->execute()->fetchArray(SQLITE3_NUM);
    return $row ? (int)$row[0] : 0;
}

/** Lokale ID einer Pflanze mit diesem botanischen Namen, 0 wenn nicht vorhanden. */
function pdb_userdb_local_id_by_name(SQLite3 $user, string $botanicalName): int
{
    $st = $user->prepare('SELECT id FROM plants WHERE botanical_name = :n LIMIT 1');
    $st->bindValue(':n', $botanicalName, SQLITE3_TEXT);
    $row = $st->execute()->fetchArray(SQLITE3_NUM);
    return $row ? (int)$row[0] : 0;
}

/**
 * "Aufnehmen": holt eine Master-Pflanze in die Gaertnerei-DB.
 * Rueckgabe [lokale ID, Art]: 'kopiert' | 'verknuepft' (gleicher Name war schon als eigene Pflanze da) |
 * 'vorhanden' | 'fehlt' (Master kennt die ID nicht). Aufrufer sorgt fuer die Transaktion.
 */
function pdb_userdb_take_from_master(SQLite3 $user, SQLite3 $master, int $masterId): array
{
    $local = pdb_userdb_local_id_for_master($user, $masterId);
    if ($local > 0) {
        return [$local, 'vorhanden'];
    }

    $st = $master->prepare('SELECT botanical_name, updated_at FROM plants WHERE id = :id');
    $st->bindValue(':id', $masterId, SQLITE3_INTEGER);
    $m = $st->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$m) {
        return [0, 'fehlt'];
    }

    // Gleicher Name schon als eigene Pflanze angelegt? Dann verknuepfen statt doppelt kopieren.
    $byName = pdb_userdb_local_id_by_name($user, (string)$m['botanical_name']);
    if ($byName > 0) {
        $up = $user->prepare("INSERT INTO plant_local (plant_id, master_plant_id, origin, master_updated_at)
                              VALUES (:p, :m, 'neu', :u)
                              ON CONFLICT(plant_id) DO UPDATE SET master_plant_id = excluded.master_plant_id,
                                  master_updated_at = excluded.master_updated_at
                              WHERE plant_local.master_plant_id IS NULL");
        $up->bindValue(':p', $byName, SQLITE3_INTEGER);
        $up->bindValue(':m', $masterId, SQLITE3_INTEGER);
        $up->bindValue(':u', (string)($m['updated_at'] ?? ''), SQLITE3_TEXT);
        $up->execute();
        return [$byName, 'verknuepft'];
    }

    $id = pdb_userdb_copy_plant($user, $master, $masterId);
    return $id > 0 ? [$id, 'kopiert'] : [0, 'fehlt'];
}

/** Naechste freie lokale ID fuer eine rein lokal angelegte Pflanze (immer >= PDB_LOCAL_ID_START). */
function pdb_userdb_next_local_id(SQLite3 $user): int
{
    return max(PDB_LOCAL_ID_START, (int)$user->querySingle('SELECT MAX(id) FROM plants') + 1);
}

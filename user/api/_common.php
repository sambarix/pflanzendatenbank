<?php
/**
 * Gemeinsame Hilfen der PDB-API-Bibliothek.
 * Reine Bibliotheksdatei - wird nur vom pdb-api-Plugin eingebunden, nie direkt per HTTP.
 */

// Pflanzentabellen der Gaertnerei-DB (Umbau A): Schema, Migration, Kopieren aus der plants.db
require_once __DIR__ . '/_userdb.php';
// Vorschlaege an die oeffentliche PDB (Umbau A, Stufe 2)
require_once __DIR__ . '/_vorschlag.php';

/**
 * Ermittelt die Gaertnerei-DB des eingeloggten Benutzers.
 * Der Pfad kommt serverseitig aus dem Login (Account-YAML: db_path),
 * NIE aus einem Browser-Parameter.
 *
 * $needsNursery = true: nur Konten mit dem Recht site.pdb_nursery (Gruppe "user", Gaertnerei).
 * $needsNursery = false: jedes angemeldete Konto mit eigener DB (z.B. Gruppe "planer", nur Anfrageliste).
 *
 * @return array{path?:string,rel?:string,username?:string,error?:string,status?:int}
 */
function pdb_current_nursery(\Grav\Common\Grav $grav, bool $needsNursery = true): array
{
    $user = $grav['user'] ?? null;
    if (!$user || empty($user->authenticated) || !$user->authorize('site.login')) {
        return ['error' => 'Nicht angemeldet.', 'status' => 401];
    }
    if ($needsNursery && !$user->authorize('site.pdb_nursery')) {
        return ['error' => 'Keine Berechtigung (nur für Gärtnereien mit eigenem Bestand).', 'status' => 403];
    }

    $rel = trim((string)$user->get('db_path', ''));
    if (!preg_match('~^user/data/sqlite/[a-z0-9_\-]+\.db$~', $rel)) {
        return ['error' => 'Diesem Konto ist keine Gärtnerei-Datenbank zugeordnet.', 'status' => 403];
    }

    $path = GRAV_ROOT . '/' . $rel;
    if (!is_file($path)) {
        return ['error' => 'Gärtnerei-Datenbank nicht gefunden.', 'status' => 404];
    }

    pdb_migrate_nursery_db($path);

    return ['path' => $path, 'rel' => $rel, 'username' => (string)$user->username];
}

/**
 * Idempotente Schema-Migration einer Gaertnerei-DB: ergaenzt fehlende Spalten
 * in plant_inventory (z.B. die oXs-Felder, die aeltere Live-DBs noch nicht haben).
 * Laeuft bei jedem Zugriff, kostet nur ein PRAGMA.
 */
function pdb_migrate_nursery_db(string $path): void
{
    static $done = [];
    if (isset($done[$path])) {
        return;
    }
    $done[$path] = true;

    $required = [
        'is_public'         => 'INTEGER DEFAULT 0',
        'oxs_offer_type'    => 'TEXT',
        'oxs_prop_method'   => 'TEXT',
        'oxs_source'        => 'TEXT',
        'oxs_source_bio'    => 'TEXT',
        'oxs_source_origin' => 'TEXT',
        'oxs_geo_origin'    => 'TEXT',
        'oxs_offer_bio'     => 'TEXT',
    ];

    try {
        $db = new SQLite3($path, SQLITE3_OPEN_READWRITE);
        $db->busyTimeout(3000);
        $has = [];
        $res = $db->query('PRAGMA table_info(plant_inventory)');
        while ($c = $res->fetchArray(SQLITE3_ASSOC)) {
            $has[$c['name']] = true;
        }
        if ($has) {
            foreach ($required as $col => $def) {
                if (!isset($has[$col])) {
                    $db->exec("ALTER TABLE plant_inventory ADD COLUMN $col $def");
                }
            }
        }
        // Umbau A: Pflanzentabellen anlegen und bereits referenzierte Master-Pflanzen kopieren
        pdb_userdb_ensure_plants($db);
        // Stufe 2: eigene Pflanzen mit der PDB verknuepfen, sobald es sie dort gibt
        pdb_vorschlag_link_plants($db, basename($path));
        $db->close();
    } catch (\Throwable $e) {
        error_log('PDB Migration ' . basename($path) . ': ' . $e->getMessage());
    }
}

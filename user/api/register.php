<?php
/**
 * PDB Registrierung (Grav 2): legt Gaertnerei-Konto (user/accounts/<name>.yaml)
 * und die eigene SQLite-DB (user/data/sqlite/<name>.db) an.
 * Voraussetzung: Zugangscode in dieser Sitzung bestanden (passphrase.php).
 * POST -> {"success":true,"message":...} | {"success":false,"errors":[...]}
 * Reine Bibliotheksdatei - vom pdb-api-Plugin eingebunden, nie direkt per HTTP.
 */

function pdb_api_register(\Grav\Common\Grav $grav): array
{
    require_once __DIR__ . '/_invite.php';

    $fail = fn(array $errors, int $status = 200) => pdb_json(['success' => false, 'errors' => $errors], $status);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return $fail(['POST erforderlich.'], 405);
    }
    if (!pdb_invite_session_ok()) {
        return $fail(['Bitte zuerst den Zugangscode eingeben (Sitzung abgelaufen).'], 403);
    }

    $in = static fn(string $k, int $max = 200): string =>
        mb_substr(trim(strip_tags((string)($_POST[$k] ?? ''))), 0, $max);

    $username = strtolower($in('username', 32));
    $email    = $in('email', 120);
    $fullname = $in('fullname', 120);
    // Kontoart: "gaertnerei" (Gruppe user, eigener Bestand) oder "planer" (Gruppe planer, nur Anfragelisten)
    $typ      = (($_POST['typ'] ?? '') === 'planer') ? 'planer' : 'gaertnerei';
    $password = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password2'] ?? '');

    $errors = [];

    if (!preg_match('~^[a-z0-9][a-z0-9_\-]{2,15}$~', $username)) {
        $errors[] = 'Benutzername: 3–16 Zeichen, nur Kleinbuchstaben, Zahlen, - und _.';
    }
    if (in_array($username, ['admin', 'administrator', 'root', 'plants', 'test'], true)) {
        $errors[] = 'Dieser Benutzername ist reserviert.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Bitte eine gültige E-Mail-Adresse eingeben.';
    }
    if ($fullname === '') {
        $errors[] = 'Bitte Name / Betriebsname angeben.';
    }
    $pwdRegex = (string)$grav['config']->get('system.pwd_regex', '(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,64}');
    if (!preg_match('~^' . str_replace('~', '\~', $pwdRegex) . '$~', $password)) {
        $errors[] = 'Passwort: mindestens 8 Zeichen, Gross- und Kleinbuchstaben und eine Zahl.';
    }
    if ($password !== $password2) {
        $errors[] = 'Die beiden Passwörter stimmen nicht überein.';
    }

    $plz = $in('betrieb_plz', 5);
    if ($plz !== '' && !preg_match('~^\d{4,5}$~', $plz)) {
        $errors[] = 'Bitte eine gültige Postleitzahl eingeben.';
    }
    $land = $in('betrieb_land', 2);
    if (!in_array($land, ['CH', 'DE', 'AT', 'LI'], true)) {
        $land = 'CH';
    }

    $accountFile = GRAV_ROOT . '/user/accounts/' . $username . '.yaml';
    $dbRel       = 'user/data/sqlite/' . $username . '.db';
    $dbFile      = GRAV_ROOT . '/' . $dbRel;
    if ($errors === [] && (file_exists($accountFile) || file_exists($dbFile))) {
        $errors[] = 'Dieser Benutzername ist bereits vergeben.';
    }
    if ($errors !== []) {
        return $fail($errors);
    }

    // 1) Gaertnerei-DB anlegen
    try {
        pdb_register_create_db($dbFile, $fullname, $in('betrieb_ort', 80), $email);
    } catch (\Throwable $e) {
        error_log('PDB Registrierung (DB ' . $username . '): ' . $e->getMessage());
        @unlink($dbFile);
        return $fail(['Die Datenbank konnte nicht angelegt werden. Bitte später nochmals versuchen.'], 500);
    }

    // 2) Konto-Datei schreiben
    $account = [
        'state'           => 'enabled',
        'email'           => $email,
        'fullname'        => $fullname,
        'title'           => $typ === 'planer' ? 'Planer' : 'Gaertnerei',
        'hashed_password' => password_hash($password, PASSWORD_DEFAULT),
        'language'        => 'de',
        'twofa_enabled'   => false,
        'db_path'         => $dbRel,
    ];
    foreach (['betrieb_strasse' => 120, 'betrieb_ort' => 80, 'betrieb_telefon' => 40, 'betrieb_url' => 160,
              'bio_zertifizierung' => 40, 'bio_betriebsnummer' => 40, 'betrieb_notiz' => 500] as $k => $max) {
        if ($typ === 'planer' && str_starts_with($k, 'bio_')) {
            continue; // Bio-Angaben gelten nur fuer Gaertnereien
        }
        $v = $in($k, $max);
        if ($v !== '') {
            $account[$k] = $v;
        }
    }
    if ($plz !== '') {
        $account['betrieb_plz'] = $plz;
    }
    $account['betrieb_land'] = $land;
    // Gruppen (user/config/groups.yaml): "user" = site.login + site.pdb_nursery (Gaertnerei),
    // "planer" = nur site.login (Anfragelisten). Das Editor-Recht vergibt nur der Admin im Backend.
    $account['groups'] = [$typ === 'planer' ? 'planer' : 'user'];

    $yaml = \Grav\Common\Yaml::dump($account);
    if (@file_put_contents($accountFile, $yaml, LOCK_EX) === false) {
        error_log('PDB Registrierung: Konto-Datei nicht schreibbar: ' . $accountFile);
        @unlink($dbFile);
        return $fail(['Das Konto konnte nicht angelegt werden. Bitte später nochmals versuchen.'], 500);
    }

    // Freigabe verbrauchen: pro Zugangscode-Eingabe eine Registrierung
    unset($_SESSION['pdb_invite_ok']);

    return pdb_json([
        'success' => true,
        'message' => 'Registrierung erfolgreich. Sie werden zum Login weitergeleitet.',
    ]);
}

/**
 * Legt eine leere Gaertnerei-DB mit dem Schema der bestehenden Gaertnerei-DBs an.
 * Fehlende oXs-Spalten ergaenzt pdb_migrate_nursery_db() beim ersten Zugriff.
 */
function pdb_register_create_db(string $path, string $name, string $ort, string $email): void
{
    $db = new SQLite3($path, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
    $db->busyTimeout(3000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('BEGIN');

    $db->exec('CREATE TABLE nursery_info (
        id INTEGER PRIMARY KEY, name TEXT NOT NULL, location TEXT, contact TEXT,
        labels TEXT, settings TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
    $db->exec('CREATE TABLE nursery_locations (
        id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, parent_id INTEGER,
        sort_order INTEGER DEFAULT 0, notes TEXT,
        FOREIGN KEY (parent_id) REFERENCES nursery_locations(id))');
    $db->exec('CREATE TABLE pot_sizes_custom (
        id INTEGER PRIMARY KEY AUTOINCREMENT, pot_code TEXT NOT NULL UNIQUE, pot_name TEXT,
        pot_type TEXT, pot_form TEXT, volume_liters REAL, diameter_mm INTEGER, height_mm INTEGER,
        width_mm INTEGER, plants_per_sqm REAL, sort_order INTEGER DEFAULT 0, notes TEXT)');
    $db->exec('CREATE TABLE plant_inventory (
        id INTEGER PRIMARY KEY AUTOINCREMENT, plant_id INTEGER NOT NULL, satz_bezeichnung TEXT,
        pot_size_code TEXT, quantity INTEGER, location_id INTEGER,
        status TEXT DEFAULT \'verkaufsfertig\', price_chf REAL, delivery_week INTEGER,
        delivery_year INTEGER, qr_code TEXT, ean_code TEXT, offer_form TEXT,
        published_organicxseeds INTEGER DEFAULT 0, is_public INTEGER DEFAULT 0,
        production_batch_id INTEGER, source_id INTEGER, notes TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (location_id) REFERENCES nursery_locations(id))');
    $db->exec('CREATE INDEX idx_inv_plant ON plant_inventory(plant_id)');
    $db->exec('CREATE TABLE inventory_overrides (
        id INTEGER PRIMARY KEY AUTOINCREMENT, plant_id INTEGER NOT NULL, field_name TEXT NOT NULL,
        value TEXT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE (plant_id, field_name))');

    $st = $db->prepare('INSERT INTO nursery_info (id, name, location, contact) VALUES (1, :n, :l, :c)');
    $st->bindValue(':n', $name, SQLITE3_TEXT);
    $st->bindValue(':l', $ort !== '' ? $ort : null, $ort !== '' ? SQLITE3_TEXT : SQLITE3_NULL);
    $st->bindValue(':c', $email, SQLITE3_TEXT);
    $st->execute();

    $db->exec("INSERT INTO nursery_locations (name, sort_order) VALUES ('Hauptstandort', 1)");

    $db->exec('COMMIT');
    $db->close();
}

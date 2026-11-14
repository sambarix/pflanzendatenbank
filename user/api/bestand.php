<?php
/**
 * PDB Bestand API (Grav 2)
 * Liest/schreibt die Gaertnerei-DB des eingeloggten Benutzers, gejoint mit plants.db via ATTACH.
 * Reine Bibliotheksdatei - wird vom pdb-api-Plugin eingebunden, NIE direkt per HTTP aufgerufen.
 * Der DB-Pfad kommt aus dem Login (pdb_current_nursery), nicht aus dem Browser.
 *
 * Actions: search, list, aufnehmen, save, load, remove, locations
 */

function pdb_api_bestand(\Grav\Common\Grav $grav): array
{
    ob_start();
    [$ct, $status] = pdb_api_bestand_run($grav);
    $out = ob_get_clean();
    return [$out, $ct, $status];
}

function pdb_api_bestand_run(\Grav\Common\Grav $grav): array
{
    $ct = 'application/json; charset=utf-8';

    $nursery = pdb_current_nursery($grav);
    if (isset($nursery['error'])) {
        echo json_encode(['error' => $nursery['error']]);
        return [$ct, $nursery['status']];
    }
    $nurseryDb    = $nursery['path'];
    $stammdatenDb = GRAV_ROOT . '/user/data/sqlite/plants.db';
    if (!file_exists($stammdatenDb)) {
        echo json_encode(['error' => 'Stammdaten-DB nicht gefunden.']);
        return [$ct, 500];
    }

    $action = $_REQUEST['action'] ?? '';

// ============================================================
// ACTION: search — Suche in plants.db
// ============================================================
if ($action === 'search') {
    $ct = 'text/html; charset=utf-8';
    $term = trim($_GET['term'] ?? '');
    if (strlen($term) < 2) {
        echo '<div class="pdb-search-hint">Mindestens 2 Zeichen eingeben...</div>';
        return [$ct, 200];
    }

    $db = new SQLite3($stammdatenDb, SQLITE3_OPEN_READONLY);
    $db->busyTimeout(3000);

    // Suchbegriff in Wörter aufteilen — jedes Wort muss vorkommen
    $words = array_filter(explode(' ', $term), fn($w) => strlen($w) >= 1);
    $whereParts = [];
    $bindings = [];
    foreach ($words as $i => $word) {
        $like = '%' . $word . '%';
        $key  = ':w' . $i;
        // Jedes Wort muss im botanischen Namen ODER deutschen Namen vorkommen
        $whereParts[] = "(p.botanical_name LIKE $key OR n.name_de LIKE $key OR p.genus LIKE $key)";
        $bindings[$key] = $like;
    }
    $where = implode(' AND ', $whereParts);

    $sql = "SELECT DISTINCT p.id, p.botanical_name, p.genus, p.species, p.cultivar,
               n.name_de
        FROM plants p
        LEFT JOIN plant_names n ON p.id = n.plant_id
        WHERE $where
        ORDER BY p.botanical_name
        LIMIT 300";

    $stmt = $db->prepare($sql);
    foreach ($bindings as $key => $val) {
        $stmt->bindValue($key, $val, SQLITE3_TEXT);
    }
    $res = $stmt->execute();

    $rows = '';
    $count = 0;
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $count++;
        $id   = (int)$row['id'];
        $name = htmlspecialchars($row['botanical_name']);
        $de   = $row['name_de'] ? ' <span class="pdb-name-de">(' . htmlspecialchars($row['name_de']) . ')</span>' : '';
        $rows .= "<label class='pdb-search-result'>
            <input type='checkbox' class='pdb-search-cb' value='$id' data-name='$name'>
            <span class='pdb-botanical'>$name</span>$de
        </label>\n";
    }

    if ($count === 0) {
        echo '<div class="pdb-search-hint">Keine Treffer für "' . htmlspecialchars($term) . '"</div>';
    } else {
        echo $rows;
    }
    $db->close();
    return [$ct, 200];
}

// ============================================================
// ACTION: aufnehmen — Pflanzen ins Sortiment aufnehmen
// ============================================================
if ($action === 'aufnehmen') {
    $ids = $_POST['plant_ids'] ?? [];
    if (empty($ids)) {
        echo json_encode(['error' => 'Keine Pflanzen ausgewählt.']);
        return [$ct, 200];
    }

    $db = new SQLite3($nurseryDb);
    $db->enableExceptions(true);
    $db->exec('PRAGMA foreign_keys=ON');

    $added = 0;
    $skipped = 0;
    foreach ($ids as $pid) {
        $pid = (int)$pid;
        if ($pid <= 0) continue;
        // Prüfen ob bereits vorhanden
        $exists = $db->querySingle(
            "SELECT COUNT(*) FROM plant_inventory WHERE plant_id = $pid"
        );
        if ($exists > 0) {
            $skipped++;
            continue;
        }
        // Eintrag anlegen (leer, wird später befüllt)
        $db->exec(
            "INSERT INTO plant_inventory (plant_id, status, updated_at)
             VALUES ($pid, 'im_sortiment', CURRENT_TIMESTAMP)"
        );
        $added++;
    }
    $db->close();

    echo json_encode([
        'success' => true,
        'added'   => $added,
        'skipped' => $skipped,
        'message' => "$added Pflanze(n) aufgenommen" . ($skipped ? ", $skipped bereits vorhanden" : "") . "."
    ]);
    return [$ct, 200];
}

// ============================================================
// ACTION: list — Mein Sortiment als HTML-Tabelle
// ============================================================
if ($action === 'list') {
    $ct = 'text/html; charset=utf-8';

    try {
        $db = new SQLite3($nurseryDb, SQLITE3_OPEN_READONLY);
        $db->enableExceptions(true);
        $db->busyTimeout(3000);
        $sPath = SQLite3::escapeString($stammdatenDb);
        $db->exec("ATTACH DATABASE '$sPath' AS sd");
    } catch (\Throwable $e) {
        echo '<tbody><tr><td colspan="10" class="pdb-no-results" style="color:#c62828">DB-Fehler: ' . htmlspecialchars($e->getMessage())
           . '<br>plants.db lesbar: ' . (is_readable($stammdatenDb) ? 'JA' : 'NEIN')
           . '</td></tr></tbody>';
        return [$ct, 200];
    }

    $res = $db->query("
        SELECT pi.id, pi.plant_id, pi.pot_size_code, pi.quantity,
               pi.status, pi.price_chf, pi.location_id, pi.is_public,
               pi.oxs_offer_type, pi.oxs_offer_bio, pi.published_organicxseeds,
               sd.plants.botanical_name, sd.plants.web_name,
               sd.plant_names.name_de
        FROM plant_inventory pi
        JOIN sd.plants ON pi.plant_id = sd.plants.id
        LEFT JOIN sd.plant_names ON pi.plant_id = sd.plant_names.plant_id
        GROUP BY pi.id
        ORDER BY sd.plants.botanical_name
    ");

    // Alle Standorte für Pfad-Aufbau vorladen
    $locRows = $db->query("SELECT id, name, parent_id FROM nursery_locations");
    $allLocs = [];
    while ($lr = $locRows->fetchArray(SQLITE3_ASSOC)) {
        $allLocs[$lr['id']] = $lr;
    }
    if (!function_exists('buildLocPath')) {
        function buildLocPath($id, $locs) {
            if (!$id || !isset($locs[$id])) return '';
            $path = [];
            $cur  = $id;
            while ($cur && isset($locs[$cur])) {
                array_unshift($path, $locs[$cur]['name']);
                $cur = $locs[$cur]['parent_id'];
            }
            return implode(' › ', $path);
        }
    }

    $baseUrl = rtrim($grav['uri']->rootUrl(), '/');

    $statusLabels = [
        'verkaufsfertig' => 'Verkaufsfertig',
        'in_kultur'      => 'In Kultur',
        'bestellt'       => 'Bestellt',
        'ausverkauft'    => 'Ausverkauft',
        'im_sortiment'   => 'Im Sortiment',
    ];
    $oxsTypeL = ['JP' => 'JP', 'VM' => 'VM', 'HF' => 'HF', 'FP' => 'FP'];
    $oxsBioL  = ['bio' => 'bio', 'umstellung' => 'Umst.', 'nicht-bio' => 'konv.'];

    $editSvg   = '<svg class="pdb-edit-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 3a2.83 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>';
    $removeSvg = '<svg class="pdb-remove-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>';

    $rowsHtml = '';
    $count = 0;
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $count++;
        $id      = (int)$row['id'];
        $name    = htmlspecialchars($row['botanical_name']);
        $webName = htmlspecialchars($row['web_name'] ?? '');
        $de      = htmlspecialchars($row['name_de'] ?? '');
        $pot     = htmlspecialchars($row['pot_size_code'] ?? '');
        $qty     = $row['quantity'] !== null ? (int)$row['quantity'] : '';
        $status  = preg_replace('/[^a-z_]/', '', (string)($row['status'] ?? ''));
        $statusL = htmlspecialchars($statusLabels[$status] ?? $status);
        $price   = $row['price_chf'] !== null ? number_format((float)$row['price_chf'], 2, '.', "'") : '';
        $loc     = htmlspecialchars(buildLocPath($row['location_id'], $allLocs));
        $isPub   = !empty($row['is_public']);

        // oXs: Handelsform · Bio-Status, plus Hinweis wenn exportiert
        $oxsType = $row['oxs_offer_type'] ?? null;
        $oxsBio  = $row['oxs_offer_bio'] ?? null;
        $oxsTxt  = '';
        if ($oxsType || $oxsBio) {
            $tl = $oxsType ? ($oxsTypeL[$oxsType] ?? $oxsType) : '';
            $bl = $oxsBio  ? ($oxsBioL[$oxsBio] ?? $oxsBio) : '';
            $oxsTxt = htmlspecialchars(implode(' · ', array_filter([$tl, $bl])));
        }
        if (!empty($row['published_organicxseeds'])) {
            $oxsTxt .= ($oxsTxt !== '' ? ' ' : '') . '<span class="pdb-oxs-pub" title="Wird nach oXs exportiert">→ oXs</span>';
        }

        $editLink = '<a class="pdb-edit-btn" href="#" onclick="pdbBestandEdit(' . $id . '); return false;" title="Bearbeiten" aria-label="Bearbeiten">' . $editSvg . '</a> ';
        $nameCell = $webName !== ''
            ? '<a href="' . $baseUrl . '/portrait?plant=' . $webName . '">' . $name . '</a>'
            : $name;
        $removeLink = '<a class="pdb-remove-btn" href="#" onclick="pdbBestandRemove(' . $id . '); return false;" title="Aus dem Sortiment entfernen" aria-label="Entfernen">' . $removeSvg . '</a>';

        $rowsHtml .= '<tr data-inv-id="' . $id . '">'
            . '<td class="pdb-cell-botanical">' . $editLink . $nameCell . '</td>'
            . '<td>' . $de . '</td>'
            . '<td>' . $pot . '</td>'
            . '<td>' . $qty . '</td>'
            . '<td><span class="pdb-st pdb-st-' . $status . '">' . $statusL . '</span></td>'
            . '<td>' . $price . '</td>'
            . '<td>' . $loc . '</td>'
            . '<td class="pdb-cell-center">' . ($isPub ? '<span title="Öffentlich sichtbar">✓</span>' : '') . '</td>'
            . '<td>' . $oxsTxt . '</td>'
            . '<td class="pdb-cell-actions">' . $removeLink . '</td>'
            . "</tr>\n";
    }
    $db->close();

    echo '<thead><tr>'
       . '<th class="pdb-col-botanical">Botanischer Name</th>'
       . '<th>Deutscher Name</th>'
       . '<th>Topf</th>'
       . '<th>Anzahl</th>'
       . '<th>Status</th>'
       . '<th>Preis CHF</th>'
       . '<th>Standort</th>'
       . '<th title="Öffentlich sichtbar">Öff.</th>'
       . '<th>oXs</th>'
       . '<th></th>'
       . '</tr></thead>';
    echo '<tbody data-count="' . $count . '">';
    if ($count === 0) {
        echo '<tr><td colspan="10" class="pdb-no-results">Noch keine Pflanzen im Sortiment. Oben suchen und aufnehmen.</td></tr>';
    } else {
        echo $rowsHtml;
    }
    echo '</tbody>';
    return [$ct, 200];
}

// ============================================================
// ACTION: load — Einen Eintrag für Bearbeitung laden
// ============================================================
if ($action === 'load') {
    $invId = (int)($_GET['inv_id'] ?? 0);
    if (!$invId) { echo json_encode(['error' => 'Keine ID.']); return [$ct, 200]; }

    $db = new SQLite3($nurseryDb, SQLITE3_OPEN_READONLY);
    $sPath = SQLite3::escapeString($stammdatenDb);
    $db->exec("ATTACH DATABASE '$sPath' AS sd");

    $stmt = $db->prepare("
        SELECT pi.*, sd.plants.botanical_name, sd.plant_names.name_de
        FROM plant_inventory pi
        JOIN sd.plants ON pi.plant_id = sd.plants.id
        LEFT JOIN sd.plant_names ON pi.plant_id = sd.plant_names.plant_id
        WHERE pi.id = :id
    ");
    $stmt->bindValue(':id', $invId, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    $db->close();

    if (!$row) { echo json_encode(['error' => 'Eintrag nicht gefunden.']); return [$ct, 200]; }
    echo json_encode(['success' => true, 'data' => $row]);
    return [$ct, 200];
}

// ============================================================
// ACTION: save — Bestandsdaten speichern
// ============================================================
if ($action === 'save') {
    $invId = (int)($_POST['inv_id'] ?? 0);
    if (!$invId) { echo json_encode(['error' => 'Keine ID.']); return [$ct, 200]; }

    // Hilfsfunktion: leerer String → null
    $txt = fn($k) => (isset($_POST[$k]) && $_POST[$k] !== '') ? $_POST[$k] : null;

    $fields = [
        'pot_size_code'          => $txt('pot_size_code'),
        'quantity'               => isset($_POST['quantity']) && $_POST['quantity'] !== ''
                                  ? (int)$_POST['quantity'] : null,
        'status'                 => $_POST['status'] ?? 'im_sortiment',
        'price_chf'              => isset($_POST['price_chf']) && $_POST['price_chf'] !== ''
                                  ? (float)$_POST['price_chf'] : null,
        'delivery_week'          => isset($_POST['delivery_week']) && $_POST['delivery_week'] !== ''
                                  ? (int)$_POST['delivery_week'] : null,
        'delivery_year'          => isset($_POST['delivery_year']) && $_POST['delivery_year'] !== ''
                                  ? (int)$_POST['delivery_year'] : null,
        'location_id'            => isset($_POST['location_id']) && $_POST['location_id'] !== ''
                                  ? (int)$_POST['location_id'] : null,
        'is_public'              => isset($_POST['is_public']) ? (int)$_POST['is_public'] : 0,
        // oXs-Felder
        'oxs_offer_type'         => $txt('oxs_offer_type'),
        'oxs_prop_method'        => $txt('oxs_prop_method'),
        'oxs_source'             => $txt('oxs_source'),
        'oxs_source_bio'         => $txt('oxs_source_bio'),
        'oxs_source_origin'      => $txt('oxs_source_origin'),
        'oxs_geo_origin'         => $txt('oxs_geo_origin'),
        'oxs_offer_bio'          => $txt('oxs_offer_bio'),
        'published_organicxseeds'=> isset($_POST['published_organicxseeds'])
                                  ? (int)$_POST['published_organicxseeds'] : 0,
        'notes'                  => $txt('notes'),
    ];

    $db = new SQLite3($nurseryDb);
    $db->enableExceptions(true);

    $sets = [];
    foreach ($fields as $col => $val) {
        if ($val === null) {
            $sets[] = "$col = NULL";
        } else {
            $v = is_string($val) ? "'" . SQLite3::escapeString($val) . "'" : $val;
            $sets[] = "$col = $v";
        }
    }
    $sets[] = "updated_at = CURRENT_TIMESTAMP";
    $db->exec("UPDATE plant_inventory SET " . implode(', ', $sets) . " WHERE id = $invId");

    // plant_id für Availability-Sync ermitteln
    $row = $db->querySingle("SELECT plant_id FROM plant_inventory WHERE id = $invId", true);
    $plantId = $row ? (int)$row['plant_id'] : 0;

    // Prüfen ob irgendein Inventory-Eintrag dieser Pflanze öffentlich ist
    $anyPublic = $plantId
        ? (int)$db->querySingle("SELECT COUNT(*) FROM plant_inventory WHERE plant_id = $plantId AND is_public = 1")
        : 0;

    $db->close();

    // Availability in plants.db synchronisieren (in eigenem try/catch)
    if ($plantId) {
        try {
            $sourceDb = basename($nurseryDb);
            $pdb = new SQLite3($stammdatenDb);
            $pdb->enableExceptions(true);

            if ($anyPublic > 0) {
                $ndb2 = new SQLite3($nurseryDb, SQLITE3_OPEN_READONLY);
                $nurseryName = $ndb2->querySingle("SELECT name FROM nursery_info WHERE id = 1") ?? $sourceDb;
                $ndb2->close();

                $contactUrl = '';
                $username = preg_replace('/\.db$/', '', $sourceDb);
                $yamlFile = GRAV_ROOT . '/user/accounts/' . $username . '.yaml';
                if (file_exists($yamlFile)) {
                    $yaml = file_get_contents($yamlFile);
                    if (preg_match('/betrieb_url:\s*[\'"]?([^\'"}\n]+)[\'"]?/', $yaml, $m)) {
                        $contactUrl = trim($m[1]);
                    }
                }

                $stmt = $pdb->prepare(
                    "INSERT OR REPLACE INTO plant_availability
                     (plant_id, source_db, nursery_name, contact_url, is_public, last_updated)
                     VALUES (:pid, :src, :name, :url, 1, CURRENT_TIMESTAMP)"
                );
                $stmt->bindValue(':pid',  $plantId,     SQLITE3_INTEGER);
                $stmt->bindValue(':src',  $sourceDb,    SQLITE3_TEXT);
                $stmt->bindValue(':name', $nurseryName, SQLITE3_TEXT);
                $stmt->bindValue(':url',  $contactUrl,  SQLITE3_TEXT);
                $stmt->execute();
            } else {
                $stmt = $pdb->prepare(
                    "DELETE FROM plant_availability WHERE plant_id = :pid AND source_db = :src"
                );
                $stmt->bindValue(':pid', $plantId,  SQLITE3_INTEGER);
                $stmt->bindValue(':src', $sourceDb, SQLITE3_TEXT);
                $stmt->execute();
            }
            $pdb->close();
        } catch (\Throwable $e) {
            // Availability-Fehler loggen aber Hauptspeicherung nicht blockieren
            error_log('PDB availability sync error: ' . $e->getMessage());
        }
    }

    echo json_encode(['success' => true, 'message' => 'Gespeichert.']);
    return [$ct, 200];
}

// ============================================================
// ACTION: remove — Aus Sortiment entfernen
// ============================================================
if ($action === 'remove') {
    $invId = (int)($_POST['inv_id'] ?? 0);
    if (!$invId) { echo json_encode(['error' => 'Keine ID.']); return [$ct, 200]; }

    $db = new SQLite3($nurseryDb);
    $db->enableExceptions(true);
    $db->exec("DELETE FROM plant_inventory WHERE id = $invId");
    $db->close();

    echo json_encode(['success' => true]);
    return [$ct, 200];
}

// ============================================================
// ACTION: locations — Standorte für Dropdown laden
// ============================================================
if ($action === 'locations') {
    $db = new SQLite3($nurseryDb, SQLITE3_OPEN_READONLY);
    $res = $db->query("SELECT id, name, parent_id FROM nursery_locations ORDER BY COALESCE(parent_id,0), sort_order, name");
    $locs = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) $locs[] = $r;
    $db->close();
    echo json_encode($locs);
    return [$ct, 200];
}

echo json_encode(['error' => 'Unbekannte Action: ' . htmlspecialchars($action)]);

    return [$ct, 200];
}

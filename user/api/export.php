<?php
/**
 * PDB Export API - Sortiment der eingeloggten Gaertnerei als CSV
 *
 * Reine Bibliotheksdatei - wird vom pdb-api-Plugin eingebunden.
 * Excel-freundlich: UTF-8 mit BOM, Semikolon als Trenner, Punkt als Dezimalzeichen.
 * Letzte Spalte "PDB-ID" erlaubt spaeter den Abgleich/Re-Import.
 */

function pdb_api_export(\Grav\Common\Grav $grav): array
{
    $json = 'application/json; charset=utf-8';

    $n = pdb_current_nursery($grav);
    if (isset($n['error'])) {
        return [json_encode(['error' => $n['error']]), $json, $n['status']];
    }

    $stammdaten = GRAV_ROOT . '/user/data/sqlite/plants.db';
    if (!is_file($stammdaten)) {
        return [json_encode(['error' => 'Stammdaten-Datenbank nicht gefunden.']), $json, 500];
    }

    try {
        $db = new SQLite3($n['path'], SQLITE3_OPEN_READONLY);
        $db->enableExceptions(true);
        $db->busyTimeout(3000);
        $db->exec("ATTACH DATABASE '" . SQLite3::escapeString($stammdaten) . "' AS sd");

        // Standorte vorladen (fuer den Pfad "Haupt > Unter")
        $locs = [];
        $lr = $db->query('SELECT id, name, parent_id FROM nursery_locations');
        while ($l = $lr->fetchArray(SQLITE3_ASSOC)) {
            $locs[$l['id']] = $l;
        }
        $locPath = function ($id) use ($locs): string {
            $parts = [];
            while ($id && isset($locs[$id])) {
                array_unshift($parts, $locs[$id]['name']);
                $id = $locs[$id]['parent_id'];
            }
            return implode(' > ', $parts);
        };

        $res = $db->query("
            SELECT pi.*,
                   p.botanical_name, p.genus, p.species, p.subspecies, p.cultivar, p.ecotype,
                   (SELECT name_de FROM sd.plant_names WHERE plant_id = p.id LIMIT 1) AS name_de,
                   (SELECT category_path FROM sd.plant_categories WHERE plant_id = p.id
                      ORDER BY sort_order, id LIMIT 1) AS category_path
            FROM plant_inventory pi
            JOIN sd.plants p ON p.id = pi.plant_id
            ORDER BY p.botanical_name, pi.id
        ");
    } catch (\Throwable $e) {
        error_log('PDB Export: ' . $e->getMessage());
        return [json_encode(['error' => 'Export fehlgeschlagen.']), $json, 500];
    }

    $statusLabel = [
        'verkaufsfertig' => 'Verkaufsfertig',
        'in_kultur'      => 'In Kultur',
        'bestellt'       => 'Bestellt',
        'ausverkauft'    => 'Ausverkauft',
        'im_sortiment'   => 'Im Sortiment',
    ];

    // Text gegen Formel-Injection in Excel absichern
    $t = function ($v): string {
        $v = trim((string)($v ?? ''));
        return ($v !== '' && preg_match('/^[=+\-@\t\r]/', $v)) ? "'" . $v : $v;
    };

    $header = [
        'Botanischer Name', 'Gattung', 'Art', 'Unterart', 'Sorte', 'Ökotyp', 'Deutscher Name', 'Kategorie',
        'Topfgrösse', 'Menge', 'Status', 'Preis CHF', 'Lieferwoche', 'Lieferjahr', 'Standort', 'Öffentlich',
        'oXs Angebotstyp', 'oXs Vermehrungsart', 'oXs Quelle', 'oXs Quelle Bio', 'oXs Herkunft Quelle',
        'oXs Geografische Herkunft', 'oXs Angebot Bio', 'Bemerkung', 'Geändert', 'PDB-ID',
    ];

    $fh = fopen('php://temp', 'r+');
    fputcsv($fh, $header, ';', '"', '', "\r\n");

    while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
        fputcsv($fh, [
            $t($r['botanical_name']), $t($r['genus']), $t($r['species']), $t($r['subspecies']),
            $t($r['cultivar']), $t($r['ecotype']), $t($r['name_de']), $t($r['category_path']),
            $t($r['pot_size_code']),
            $r['quantity'] !== null ? (int)$r['quantity'] : '',
            $statusLabel[$r['status'] ?? ''] ?? $t($r['status']),
            $r['price_chf'] !== null ? number_format((float)$r['price_chf'], 2, '.', '') : '',
            $r['delivery_week'] ?? '', $r['delivery_year'] ?? '',
            $t($locPath($r['location_id'])),
            !empty($r['is_public']) ? 'ja' : 'nein',
            $t($r['oxs_offer_type'] ?? ''), $t($r['oxs_prop_method'] ?? ''), $t($r['oxs_source'] ?? ''),
            $t($r['oxs_source_bio'] ?? ''), $t($r['oxs_source_origin'] ?? ''), $t($r['oxs_geo_origin'] ?? ''),
            $t($r['oxs_offer_bio'] ?? ''),
            $t($r['notes']), $t($r['updated_at']), (int)$r['plant_id'],
        ], ';', '"', '', "\r\n");
    }
    $db->close();

    rewind($fh);
    $csv = "\xEF\xBB\xBF" . stream_get_contents($fh);
    fclose($fh);

    $file = 'Sortiment_' . $n['username'] . '_' . date('Y-m-d') . '.csv';

    return [$csv, 'text/csv; charset=utf-8', 200, ['Content-Disposition' => 'attachment; filename="' . $file . '"']];
}

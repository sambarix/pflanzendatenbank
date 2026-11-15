<?php
/**
 * PDB Categories API - Returns unique category paths with counts
 *
 * Reine Bibliotheksdatei - wird vom pdb-api-Plugin eingebunden,
 * NIE direkt per HTTP aufgerufen.
 */

function pdb_api_categories(\Grav\Common\Grav $grav): array
{
    $dbPath = __DIR__ . '/../data/sqlite/plants.db';

    if (!file_exists($dbPath)) {
        return [json_encode([]), 'application/json; charset=utf-8'];
    }

    try {
        $db = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
        $db->enableExceptions(true);
    } catch (\Exception $e) {
        return [json_encode([]), 'application/json; charset=utf-8'];
    }

    // Alle Kategorien, auch Oberkategorien ohne eigene Pflanzen. Zaehler = verschiedene Pflanzen, die in dieser Kategorie
    // oder einer Unterkategorie stehen (egal ob erste oder weitere Kategorie der Pflanze). Leere Kategorien entfallen unten.
    $sql = "SELECT t.path AS path,
                   (SELECT COUNT(DISTINCT pc.plant_id) FROM plant_categories pc
                     WHERE pc.category_path = t.path OR pc.category_path LIKE t.path || '/%') AS count
            FROM (SELECT path FROM categories
                  UNION
                  SELECT category_path FROM plant_categories WHERE category_path IS NOT NULL AND category_path != '') t
            WHERE t.path IS NOT NULL AND t.path != ''
            ORDER BY t.path";

    $result = $db->query($sql);
    $categories = [];

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if ((int)$row['count'] > 0) { $categories[] = $row; }
    }

    $db->close();

    return [json_encode($categories), 'application/json; charset=utf-8'];
}

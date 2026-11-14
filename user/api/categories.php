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

    $sql = "SELECT category_path AS path, COUNT(*) AS count
            FROM plant_categories
            WHERE category_path IS NOT NULL AND category_path != ''
            GROUP BY category_path
            ORDER BY category_path";

    $result = $db->query($sql);
    $categories = [];

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $categories[] = $row;
    }

    $db->close();

    return [json_encode($categories), 'application/json; charset=utf-8'];
}

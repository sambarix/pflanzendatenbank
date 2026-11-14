<?php
/**
 * PDB Standorte-API (Grav 2)
 * Actions: list, add, rename, delete
 * Reine Bibliotheksdatei - wird vom pdb-api-Plugin eingebunden. DB-Pfad aus dem Login.
 */

function pdb_api_standorte(\Grav\Common\Grav $grav): array
{
    ob_start();
    [$ct, $status] = pdb_api_standorte_run($grav);
    $out = ob_get_clean();
    return [$out, $ct, $status];
}

function pdb_api_standorte_run(\Grav\Common\Grav $grav): array
{
    $ct = 'application/json; charset=utf-8';

    $nursery = pdb_current_nursery($grav);
    if (isset($nursery['error'])) {
        echo json_encode(['error' => $nursery['error']]);
        return [$ct, $nursery['status']];
    }
    $dbPath = $nursery['path'];

    $action = $_REQUEST['action'] ?? 'list';

try {
    $db = new SQLite3($dbPath);
    $db->enableExceptions(true);

    // --- LIST ---
    if ($action === 'list') {
        $res = $db->query(
            "SELECT id, name, parent_id, sort_order
             FROM nursery_locations
             ORDER BY COALESCE(parent_id, 0), sort_order, name"
        );
        $locs = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $locs[] = $row;
        }
        echo json_encode($locs);
    }

    // --- ADD ---
    elseif ($action === 'add') {
        $name     = trim($_POST['name'] ?? '');
        $parentId = ($_POST['parent_id'] ?? '') !== '' ? (int)$_POST['parent_id'] : null;

        if (empty($name)) { echo json_encode(['error' => 'Name fehlt.']); return [$ct, 200]; }

        // Tiefe prüfen (max 3 Ebenen)
        if ($parentId !== null) {
            $depth = 1;
            $pid   = $parentId;
            while ($pid !== null && $depth < 5) {
                $s = $db->prepare("SELECT parent_id FROM nursery_locations WHERE id = :id");
                $s->bindValue(':id', $pid, SQLITE3_INTEGER);
                $row = $s->execute()->fetchArray(SQLITE3_ASSOC);
                $pid = $row ? $row['parent_id'] : null;
                $depth++;
            }
            if ($depth > 3) {
                echo json_encode(['error' => 'Maximal 3 Ebenen erlaubt.']); return [$ct, 200];
            }
        }

        $stmt = $db->prepare(
            "INSERT INTO nursery_locations (name, parent_id, sort_order) VALUES (:name, :pid, 0)"
        );
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $parentId !== null
            ? $stmt->bindValue(':pid', $parentId, SQLITE3_INTEGER)
            : $stmt->bindValue(':pid', null,      SQLITE3_NULL);
        $stmt->execute();
        echo json_encode(['ok' => true, 'id' => $db->lastInsertRowID()]);
    }

    // --- RENAME ---
    elseif ($action === 'rename') {
        $id   = (int)($_POST['id']   ?? 0);
        $name = trim($_POST['name'] ?? '');
        if (!$id || empty($name)) { echo json_encode(['error' => 'Fehlende Parameter.']); return [$ct, 200]; }

        $stmt = $db->prepare("UPDATE nursery_locations SET name = :name WHERE id = :id");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':id',   $id,   SQLITE3_INTEGER);
        $stmt->execute();
        echo json_encode(['ok' => true]);
    }

    // --- DELETE ---
    elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID fehlt.']); return [$ct, 200]; }

        // Unterstandorte prüfen
        $s = $db->prepare("SELECT COUNT(*) as n FROM nursery_locations WHERE parent_id = :id");
        $s->bindValue(':id', $id, SQLITE3_INTEGER);
        if ($s->execute()->fetchArray(SQLITE3_ASSOC)['n'] > 0) {
            echo json_encode(['error' => 'Standort hat Unterstandorte — bitte zuerst diese löschen.']); return [$ct, 200];
        }

        // Bestand prüfen
        $s = $db->prepare("SELECT COUNT(*) as n FROM plant_inventory WHERE location_id = :id");
        $s->bindValue(':id', $id, SQLITE3_INTEGER);
        if ($s->execute()->fetchArray(SQLITE3_ASSOC)['n'] > 0) {
            echo json_encode(['error' => 'Standort wird im Bestand verwendet — bitte zuerst dort entfernen.']); return [$ct, 200];
        }

        $stmt = $db->prepare("DELETE FROM nursery_locations WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
        echo json_encode(['ok' => true]);
    }

    $db->close();

} catch (\Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

    return [$ct, 200];
}

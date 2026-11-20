<?php
/**
 * PDB Liste API - Returns HTML fragments for HTMX
 * Queries plants.db (18-table schema)
 *
 * Reine Bibliotheksdatei - wird vom pdb-api-Plugin eingebunden,
 * NIE direkt per HTTP aufgerufen. Deshalb keine header()-Aufrufe hier -
 * der Content-Type wird vom Plugin ueber die Response gesetzt.
 */

function pdb_api_liste(\Grav\Common\Grav $grav): array
{
    ob_start();
    $contentType = pdb_api_liste_render($grav);
    $html = ob_get_clean();
    return [$html, $contentType ?? 'text/html; charset=utf-8'];
}

function pdb_api_liste_render(\Grav\Common\Grav $grav): ?string
{
    // --- Basis-URL ueber Gravs eigenes Uri-Objekt (statt eigener Pfad-Ermittlung) ---
    $baseUrl = rtrim($grav['uri']->rootUrl(), '/');

    // Stift (Stammdaten bearbeiten) nur fuer Editoren
    $pdbUser = $grav['user'] ?? null;
    $canEdit = $pdbUser && !empty($pdbUser->authenticated) && $pdbUser->authorize('site.pdb_editor');

    // Database path
    $dbPath = __DIR__ . '/../data/sqlite/plants.db';

    if (!file_exists($dbPath)) {
        echo '<tr><td colspan="99">Datenbank nicht gefunden: plants.db</td></tr>';
        return null;
    }

    try {
        $db = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
        $db->enableExceptions(true);
        $db->busyTimeout(5000);
        $db->exec('PRAGMA journal_mode=WAL');
        $db->exec('PRAGMA foreign_keys=ON');
    } catch (\Exception $e) {
        echo '<tr><td colspan="99">Datenbankfehler: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
        return null;
    }

    // --- Column Definitions ---
    $allColumns = [
        'botanical_name' => ['sql' => 'p.botanical_name', 'label' => 'Botanischer Name', 'group' => 'Taxonomie'],
        'genus'          => ['sql' => 'p.genus', 'label' => 'Gattung', 'group' => 'Taxonomie'],
        'species'        => ['sql' => 'p.species', 'label' => 'Art', 'group' => 'Taxonomie'],
        'cultivar'       => ['sql' => 'p.cultivar', 'label' => 'Sorte', 'group' => 'Taxonomie'],
        'class_group'    => ['sql' => 'p.class_group', 'label' => 'Klasse', 'group' => 'Taxonomie'],
        'subspecies'     => ['sql' => 'p.subspecies', 'label' => 'Unterart', 'group' => 'Taxonomie'],
        'ecotype'        => ['sql' => 'p.ecotype', 'label' => 'Ökotyp', 'group' => 'Taxonomie'],
        'matchcode'      => ['sql' => 'p.matchcode', 'label' => 'Matchcode', 'group' => 'Taxonomie'],
        'web_name'       => ['sql' => 'p.web_name', 'label' => 'Webname', 'group' => 'Taxonomie'],
        'name_de'        => ['sql' => 'pn.name_de', 'label' => 'Deutscher Name', 'group' => 'Namen'],
        'name_en'        => ['sql' => 'pn.name_en', 'label' => 'English Name', 'group' => 'Namen'],
        'name_fr'        => ['sql' => 'pn.name_fr', 'label' => 'Nom français', 'group' => 'Namen'],
        'name_it'        => ['sql' => 'pn.name_it', 'label' => 'Nome italiano', 'group' => 'Namen'],
        'category_path'  => ['sql' => 'pc.category_path', 'label' => 'Kategorie', 'group' => 'Kategorie'],
        'flower_color'   => ['sql' => 'pf.color', 'label' => 'Blütenfarbe', 'group' => 'Blüte'],
        'flower_scent'   => ['sql' => 'pf.fragrance', 'label' => 'Duft', 'group' => 'Blüte'],
        'growth_cycle'   => ['sql' => 'pg.growth_cycle', 'label' => 'Lebenszyklus', 'group' => 'Wuchs'],
        'height_cm'      => ['sql' => 'pg.height_cm', 'label' => 'Höhe (cm)', 'group' => 'Wuchs'],
        'width_cm'       => ['sql' => 'pg.width_cm', 'label' => 'Breite (cm)', 'group' => 'Wuchs'],
        'growth_form'    => ['sql' => 'pg.growth_form_primary', 'label' => 'Wuchsform', 'group' => 'Wuchs'],
        'hardiness_zone' => ['sql' => 'ps.hardiness_zone', 'label' => 'Winterhärtezone', 'group' => 'Standort'],
        'light'          => ['sql' => 'ps.light_requirement', 'label' => 'Licht', 'group' => 'Standort'],
        'soil_type'      => ['sql' => 'ps.soil_type', 'label' => 'Bodenart', 'group' => 'Standort'],
        'moisture'       => ['sql' => 'ps.moisture_requirement', 'label' => 'Feuchtigkeit', 'group' => 'Standort'],
        'usage_purpose'  => ['sql' => 'pu.purpose', 'label' => 'Verwendung', 'group' => 'Verwendung'],
        'description_short'  => ['sql' => 'p.description_short',  'label' => 'Kurzbeschreibung', 'group' => 'Texte'],
        'description_long'   => ['sql' => 'p.description_long',   'label' => 'Langbeschreibung',  'group' => 'Texte'],
        'care_instructions'  => ['sql' => 'p.care_instructions',  'label' => 'Pflanzhinweise',    'group' => 'Texte'],
        'meta_text'          => ['sql' => 'p.meta_text',          'label' => 'Metatext',          'group' => 'Texte'],
        'notes'              => ['sql' => 'p.notes',              'label' => 'Notizen',           'group' => 'Texte'],
        'registration_name'  => ['sql' => 'pn.registration_name', 'label' => 'Registrierungsname', 'group' => 'Namen'],
        'trade_name'         => ['sql' => 'pn.trade_name',        'label' => 'Handelsname',        'group' => 'Namen'],
        'nutrient_demand'    => ['sql' => 'ps.nutrient_demand',   'label' => 'Nährstoffbedarf',   'group' => 'Standort'],
        'soil_ph_min'        => ['sql' => 'ps.soil_ph_min',       'label' => 'pH min',            'group' => 'Standort'],
        'soil_ph_max'        => ['sql' => 'ps.soil_ph_max',       'label' => 'pH max',            'group' => 'Standort'],
        'flower_size'         => ['sql' => 'pf.size',          'label' => 'Blütengrösse',   'group' => 'Blüte'],
        'flower_fullness'     => ['sql' => 'pf.fullness',      'label' => 'Blütenfüllung',  'group' => 'Blüte'],
        'flower_form'         => ['sql' => 'pf.form',          'label' => 'Blütenform',     'group' => 'Blüte'],
        'flower_blooming'     => ['sql' => 'pf.blooming_cycle','label' => 'Blühzyklus',     'group' => 'Blüte'],
        'growth_vigor'       => ['sql' => 'pg.growth_vigor', 'label' => 'Wuchsstärke', 'group' => 'Wuchs'],
        'fruit_color'        => ['sql' => 'pfr.color',             'label' => 'Fruchtfarbe',    'group' => 'Frucht'],
        'fruit_size'         => ['sql' => 'pfr.size',              'label' => 'Fruchtgrösse',   'group' => 'Frucht'],
        'fruit_taste'        => ['sql' => 'pfr.taste',             'label' => 'Geschmack',      'group' => 'Frucht'],
        'leaf_color'         => ['sql' => 'pl.color',              'label' => 'Blattfarbe',     'group' => 'Blatt'],
        'leaf_form'          => ['sql' => 'pl.form',               'label' => 'Blattform',      'group' => 'Blatt'],
        'leaf_aroma'         => ['sql' => 'pl.aroma',              'label' => 'Blattaroma',     'group' => 'Blatt'],
        'leaf_evergreen'     => ['sql' => 'pl.evergreen',          'label' => 'Immergrün',      'group' => 'Blatt'],
        'breeder_name'       => ['sql' => 'po.breeder_name',       'label' => 'Züchter',        'group' => 'Herkunft'],
        'breeder_year'       => ['sql' => 'po.breeder_year',       'label' => 'Züchtungsjahr',  'group' => 'Herkunft'],
        'hybrid_status'      => ['sql' => 'po.hybrid_status',      'label' => 'Hybridstatus',   'group' => 'Herkunft'],
        'propagation_method' => ['sql' => 'pnu.propagation_method','label' => 'Vermehrung',      'group' => 'Gärtnerei'],
        'germination_days'   => ['sql' => 'pnu.germination_days',  'label' => 'Keimdauer (Tage)','group' => 'Gärtnerei'],
        'germination_light'  => ['sql' => 'pnu.germination_light', 'label' => 'Keimlicht',        'group' => 'Gärtnerei'],
        'plants_per_sqm'     => ['sql' => 'pnu.plants_per_sqm',   'label' => 'Pflanzen/m²',      'group' => 'Gärtnerei'],
        'root_depth'         => ['sql' => 'pr.root_depth',   'label' => 'Wurzeltiefe',   'group' => 'Wurzel'],
        'root_form'          => ['sql' => 'pr.root_form',    'label' => 'Wurzelform',    'group' => 'Wurzel'],
        'root_anchorage'     => ['sql' => 'pr.anchorage',    'label' => 'Verankerung',   'group' => 'Wurzel'],
        'synonyms'           => ['sql' => '(SELECT GROUP_CONCAT(synonym_name, ", ") FROM plant_synonyms WHERE plant_id = p.id)', 'label' => 'Synonyme', 'group' => 'Namen'],
    ];

    // Klasse/Gruppe (plants.class_group) nur anbieten, wenn die Spalte in der DB existiert (Migration)
    $hasClassGroup = false;
    $tiRes = $db->query('PRAGMA table_info(plants)');
    while ($tiRow = $tiRes->fetchArray(SQLITE3_ASSOC)) {
        if ($tiRow['name'] === 'class_group') { $hasClassGroup = true; }
    }
    if (!$hasClassGroup) { unset($allColumns['class_group']); }

    // --- Column Profiles ---
    $profiles = [
        'botanik' => ['label' => 'Botanik', 'columns' => ['botanical_name', 'genus', 'species', 'cultivar', 'name_de', 'category_path', 'growth_cycle']],
        'gaertnerei' => ['label' => 'Gärtnerei', 'columns' => ['botanical_name', 'name_de', 'category_path', 'flower_color', 'hardiness_zone', 'light', 'growth_cycle']],
        'planung' => ['label' => 'Planung', 'columns' => ['botanical_name', 'name_de', 'category_path', 'height_cm', 'width_cm', 'flower_color', 'light', 'hardiness_zone']],
    ];

    // --- Parse Request Parameters ---
    $search    = isset($_GET['search']) ? trim($_GET['search']) : '';
    $page      = max(1, intval($_GET['page'] ?? 1));
    $perPage   = intval($_GET['per_page'] ?? 20);
    $sortCol   = $_GET['sort'] ?? 'botanical_name';
    if (!isset($allColumns[$sortCol])) {
        $sortCol = 'botanical_name';
    }
    $sortDir   = (strtoupper($_GET['dir'] ?? 'ASC') === 'DESC') ? 'DESC' : 'ASC';
    $profile   = $_GET['profile'] ?? 'botanik';
    $category  = isset($_GET['category']) ? trim($_GET['category']) : '';
    $columns   = isset($_GET['columns']) && $_GET['columns'] !== '' ? explode(',', $_GET['columns']) : null;
    $format    = $_GET['format'] ?? 'rows';

    $allowedPerPage = [20, 50, 100, 500];
    if (!in_array($perPage, $allowedPerPage)) {
        $perPage = 20;
    }

    if ($columns !== null) {
        $activeCols = array_filter($columns, function($c) use ($allColumns) {
            return isset($allColumns[$c]);
        });
        if (empty($activeCols)) {
            $activeCols = $profiles['botanik']['columns'];
        }
    } elseif (isset($profiles[$profile])) {
        $activeCols = $profiles[$profile]['columns'];
    } else {
        $activeCols = $profiles['botanik']['columns'];
    }

    if (!in_array('botanical_name', $activeCols)) {
        array_unshift($activeCols, 'botanical_name');
    }

    // --- Determine which JOINs are needed ---
    $needsNames = $needsCategories = $needsFlower = $needsGrowth = $needsSite = false;
    $needsUsage = $needsFruit = $needsLeaf = $needsOrigin = $needsNursery = $needsRoot = false;

    if ($search !== '') { $needsNames = true; $needsCategories = true; }
    if ($category !== '') { $needsCategories = true; }

    $groupFlags = function($group) use (&$needsNames, &$needsCategories, &$needsFlower, &$needsGrowth, &$needsSite, &$needsUsage, &$needsFruit, &$needsLeaf, &$needsOrigin, &$needsNursery, &$needsRoot) {
        switch ($group) {
            case 'Namen': $needsNames = true; break;
            case 'Kategorie': $needsCategories = true; break;
            case 'Blüte': $needsFlower = true; break;
            case 'Wuchs': $needsGrowth = true; break;
            case 'Standort': $needsSite = true; break;
            case 'Verwendung': $needsUsage = true; break;
            case 'Frucht': $needsFruit = true; break;
            case 'Blatt': $needsLeaf = true; break;
            case 'Herkunft': $needsOrigin = true; break;
            case 'Gärtnerei': $needsNursery = true; break;
            case 'Wurzel': $needsRoot = true; break;
        }
    };

    foreach ($activeCols as $col) {
        if (!isset($allColumns[$col])) continue;
        $groupFlags($allColumns[$col]['group']);
    }
    if (isset($allColumns[$sortCol])) {
        $groupFlags($allColumns[$sortCol]['group']);
    }

    // --- Build SELECT columns ---
    $selectParts = ['p.id', 'p.web_name'];
    foreach ($activeCols as $col) {
        if (isset($allColumns[$col]) && $col !== 'web_name') {
            $selectParts[] = $allColumns[$col]['sql'] . ' AS ' . $col;
        }
    }
    $selectSQL = implode(', ', $selectParts);

    // --- Build JOINs ---
    $joins = '';
    if ($needsNames) { $joins .= ' LEFT JOIN plant_names pn ON p.id = pn.plant_id'; }
    if ($needsCategories) { $joins .= ' LEFT JOIN plant_categories pc ON p.id = pc.plant_id AND pc.sort_order = (SELECT MIN(sort_order) FROM plant_categories WHERE plant_id = p.id)'; }
    if ($needsFlower) { $joins .= ' LEFT JOIN plant_flower pf ON p.id = pf.plant_id'; }
    if ($needsGrowth) { $joins .= ' LEFT JOIN plant_growth pg ON p.id = pg.plant_id'; }
    if ($needsSite) { $joins .= ' LEFT JOIN plant_site ps ON p.id = ps.plant_id'; }
    if ($needsUsage) { $joins .= ' LEFT JOIN plant_usage pu ON p.id = pu.plant_id'; }
    if ($needsFruit) { $joins .= ' LEFT JOIN plant_fruit pfr ON p.id = pfr.plant_id'; }
    if ($needsLeaf) { $joins .= ' LEFT JOIN plant_leaf pl ON p.id = pl.plant_id'; }
    if ($needsOrigin) { $joins .= ' LEFT JOIN plant_origin po ON p.id = po.plant_id'; }
    if ($needsNursery) { $joins .= ' LEFT JOIN plant_nursery pnu ON p.id = pnu.plant_id'; }
    if ($needsRoot) { $joins .= ' LEFT JOIN plant_root pr ON p.id = pr.plant_id'; }

    // --- Build WHERE ---
    $where = '1=1';
    $params = [];

    if ($search !== '') {
        // Suche wortweise (alle Woerter muessen vorkommen, Reihenfolge egal), ohne Anfuehrungszeichen und ohne
        // Beachtung der Gross-/Kleinschreibung. SQLites LIKE kennt Gross/Klein nur fuer ASCII (Ae/ae, Oe/oe gehen
        // nicht), darum werden Spalte und Suchwort ueber pdb_fold() (mb_strtolower) verglichen.
        $db->createFunction('pdb_fold', function ($s) { return mb_strtolower((string)$s, 'UTF-8'); }, 1);
        $quotes = '\'"‘’‚‛“”„«»‹›`´';
        $words = [];
        foreach (preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $w) {
            $w = mb_strtolower((string)preg_replace('/[' . $quotes . ']+/u', '', $w), 'UTF-8');
            if ($w !== '') { $words[] = $w; }
        }
        $words = array_slice($words, 0, 8);
        $cols = ['p.botanical_name', 'p.genus', 'p.species', 'p.cultivar'];
        if ($hasClassGroup) { $cols[] = 'p.class_group'; }
        if ($needsNames) { $cols[] = 'pn.name_de'; }
        foreach ($words as $i => $w) {
            $parts = [];
            foreach ($cols as $c) { $parts[] = "pdb_fold($c) LIKE :search$i ESCAPE '!'"; }
            $where .= ' AND (' . implode(' OR ', $parts) . ')';
            $params[':search' . $i] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $w) . '%';
        }
    }

    if ($category !== '') {
        // Pflanze gehoert zur Kategorie, wenn IRGENDEINE ihrer Kategorien (erste oder weitere) genau diese oder eine Unterkategorie ist
        $where .= ' AND EXISTS (SELECT 1 FROM plant_categories pcx WHERE pcx.plant_id = p.id'
                . ' AND (pcx.category_path = :category OR pcx.category_path LIKE :categorysub))';
        $params[':category'] = $category;
        $params[':categorysub'] = $category . '/%';
    }

    // --- Sort ---
    $sortSQL = 'p.botanical_name ASC';
    if (isset($allColumns[$sortCol])) {
        $sortSQL = $allColumns[$sortCol]['sql'] . ' ' . $sortDir;
    }

    // --- Count total ---
    $countSQL = "SELECT COUNT(DISTINCT p.id) as total FROM plants p $joins WHERE $where";
    $stmt = $db->prepare($countSQL);
    foreach ($params as $key => $val) { $stmt->bindValue($key, $val, SQLITE3_TEXT); }
    $result = $stmt->execute();
    $totalRow = $result->fetchArray(SQLITE3_ASSOC);
    $total = intval($totalRow['total']);
    $totalPages = max(1, ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    if ($format === 'count') {
        echo $total;
        return null;
    }

    // --- Main Query ---
    $sql = "SELECT DISTINCT $selectSQL FROM plants p $joins WHERE $where ORDER BY $sortSQL LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $val) { $stmt->bindValue($key, $val, SQLITE3_TEXT); }
    $stmt->bindValue(':limit', $perPage, SQLITE3_INTEGER);
    $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);

    $result = $stmt->execute();
    $rows = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) { $rows[] = $row; }

    if ($format === 'columns') {
        echo json_encode(['columns' => $allColumns, 'profiles' => $profiles, 'active' => $activeCols]);
        return 'application/json; charset=utf-8';
    }

    if ($format === 'info') {
        echo json_encode([
            'total' => $total, 'page' => $page, 'per_page' => $perPage,
            'total_pages' => $totalPages, 'sort' => $sortCol, 'dir' => $sortDir,
            'profile' => $profile, 'active_columns' => $activeCols,
        ]);
        return 'application/json; charset=utf-8';
    }

    $paginationInfo = $total . ' Pflanzen';
    if ($total > $perPage) {
        $paginationInfo .= ' | Seite ' . $page . ' von ' . $totalPages;
    }

    if ($format === 'full') {
        echo '<thead id="pdb-thead"><tr>';
        foreach ($activeCols as $col) {
            $label = $allColumns[$col]['label'];
            $isSorted = ($sortCol === $col);
            $newDir = ($isSorted && $sortDir === 'ASC') ? 'DESC' : 'ASC';
            $arrow = $isSorted ? ($sortDir === 'ASC' ? ' &#9650;' : ' &#9660;') : '';
            $colClass = ($col === 'botanical_name') ? 'pdb-col-botanical' : '';
            echo '<th class="pdb-sortable ' . $colClass . '" ';
            echo 'hx-get="' . $baseUrl . '/pdb-api/liste?format=full&sort=' . $col . '&dir=' . $newDir;
            echo '&search=' . urlencode($search) . '&per_page=' . $perPage . '&page=1';
            echo '&profile=' . urlencode($profile) . '&category=' . urlencode($category);
            if ($columns !== null) { echo '&columns=' . urlencode(implode(',', $activeCols)); }
            echo '" hx-target="#pdb-table" hx-swap="innerHTML">';
            echo htmlspecialchars($label) . $arrow;
            echo '</th>';
        }
        echo '</tr></thead>';
        echo '<tbody id="pdb-tbody">';
    }

    // --- Output rows ---
    if (empty($rows)) {
        echo '<tr><td colspan="' . count($activeCols) . '" class="pdb-no-results">Keine Pflanzen gefunden.</td></tr>';
    } else {
        foreach ($rows as $row) {
            echo '<tr class="pdb-row" data-id="' . intval($row['id']) . '">';
            foreach ($activeCols as $col) {
                $val = $row[$col] ?? '';
                $cellClass = '';

                if ($col === 'botanical_name') {
                    $cellClass = 'pdb-cell-botanical';
                    $webName = $row['web_name'] ?? '';
                    $editIcon = !$canEdit ? '' : '<a class="pdb-edit-btn" href="' . $baseUrl . '/eingabe?edit=' . intval($row['id']) . '" title="Bearbeiten" aria-label="Bearbeiten"><svg class="pdb-edit-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 3a2.83 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg></a> ';
                    if ($webName) {
                        $val = $editIcon . '<a href="' . $baseUrl . '/portrait?plant=' . htmlspecialchars($webName) . '">' . htmlspecialchars($val) . '</a>';
                    } else {
                        $val = $editIcon . '<strong>' . htmlspecialchars($val) . '</strong>';
                    }
                } elseif ($col === 'category_path' && $val !== '') {
                    // nur die unterste Kategorie anzeigen, der volle Pfad steht im Tooltip
                    $catParts = explode('/', $val);
                    $val = '<span title="' . htmlspecialchars($val) . '">' . htmlspecialchars(end($catParts)) . '</span>';
                } else {
                    $val = str_replace(',', ' ', htmlspecialchars($val));
                }

                echo '<td class="' . $cellClass . '">' . $val . '</td>';
            }
            echo '</tr>';
        }
    }

    if ($format === 'full') {
        echo '</tbody>';
    }

    // --- OOB: Update pagination controls ---
    echo '<div id="pdb-pagination" hx-swap-oob="innerHTML">';
    echo pdb_api_liste_build_pagination($baseUrl, $page, $totalPages, $search, $perPage, $sortCol, $sortDir, $profile, $category, $columns !== null ? implode(',', $activeCols) : null);
    echo '</div>';

    // --- OOB: Update result count ---
    echo '<span id="pdb-result-count" hx-swap-oob="innerHTML">' . $paginationInfo . '</span>';

    $db->close();

    return null;
}

// --- Helper: Build Pagination HTML ---
function pdb_api_liste_build_pagination($baseUrl, $current, $total, $search, $perPage, $sort, $dir, $profile, $category, $columns) {
    if ($total <= 1) return '';

    $html = '<nav class="pdb-pagination-nav">';

    $apiUrl = $baseUrl . '/pdb-api/liste?';
    $baseParams = 'format=full&search=' . urlencode($search)
        . '&per_page=' . $perPage
        . '&sort=' . urlencode($sort)
        . '&dir=' . $dir
        . '&profile=' . urlencode($profile)
        . '&category=' . urlencode($category);
    if ($columns !== null) {
        $baseParams .= '&columns=' . urlencode($columns);
    }

    if ($current > 1) {
        $html .= '<button class="pdb-page-btn" hx-get="' . $apiUrl . $baseParams . '&page=' . ($current - 1) . '" hx-target="#pdb-table" hx-swap="innerHTML">&laquo;</button>';
    } else {
        $html .= '<button class="pdb-page-btn" disabled>&laquo;</button>';
    }

    $start = max(1, $current - 3);
    $end = min($total, $start + 6);
    $start = max(1, $end - 6);

    if ($start > 1) {
        $html .= '<button class="pdb-page-btn" hx-get="' . $apiUrl . $baseParams . '&page=1" hx-target="#pdb-table" hx-swap="innerHTML">1</button>';
        if ($start > 2) $html .= '<span class="pdb-page-dots">...</span>';
    }

    for ($i = $start; $i <= $end; $i++) {
        $activeClass = ($i === $current) ? ' pdb-page-active' : '';
        $html .= '<button class="pdb-page-btn' . $activeClass . '" hx-get="' . $apiUrl . $baseParams . '&page=' . $i . '" hx-target="#pdb-table" hx-swap="innerHTML">' . $i . '</button>';
    }

    if ($end < $total) {
        if ($end < $total - 1) $html .= '<span class="pdb-page-dots">...</span>';
        $html .= '<button class="pdb-page-btn" hx-get="' . $apiUrl . $baseParams . '&page=' . $total . '" hx-target="#pdb-table" hx-swap="innerHTML">' . $total . '</button>';
    }

    if ($current < $total) {
        $html .= '<button class="pdb-page-btn" hx-get="' . $apiUrl . $baseParams . '&page=' . ($current + 1) . '" hx-target="#pdb-table" hx-swap="innerHTML">&raquo;</button>';
    } else {
        $html .= '<button class="pdb-page-btn" disabled>&raquo;</button>';
    }

    $html .= '</nav>';
    return $html;
}

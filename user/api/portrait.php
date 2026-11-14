<?php
/**
 * PDB Portrait API (Grav 2) - Pflanzenportrait als JSON (oeffentlich, nur lesend).
 * Reine Bibliotheksdatei - wird vom pdb-api-Plugin eingebunden, NIE direkt per HTTP aufgerufen.
 * Aufruf: /pdb-api/portrait?plant=<web_name>
 */

function pdb_api_portrait(\Grav\Common\Grav $grav): array
{
    $ct = 'application/json; charset=utf-8';
    $json = fn($data) => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

    $dbPath = GRAV_ROOT . '/user/data/sqlite/plants.db';
    $webName = trim((string)($_GET['plant'] ?? ''));
    if ($webName === '') {
        return [$json(['error' => 'Kein Pflanzenname angegeben']), $ct];
    }

    try {
        $db = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
        $db->enableExceptions(true);
        $db->busyTimeout(3000);

        $stmt = $db->prepare("
            SELECT
                p.*,
                pn.name_de, pn.name_en, pn.name_fr, pn.name_it,
                pn.registration_name, pn.exhibition_name, pn.trade_name,
                ps.hardiness_zone, ps.hardiness_temp_min, ps.hardiness_temp_max,
                ps.light_requirement, ps.moisture_requirement,
                ps.soil_type, ps.soil_ph_min, ps.soil_ph_max, ps.nutrient_demand,
                pf.color AS flower_color, pf.fragrance, pf.size AS flower_size,
                pf.fullness, pf.form AS flower_form, pf.vase_life,
                pf.blooming_cycle, pf.bloom_months,
                pg.growth_cycle, pg.height_cm, pg.width_cm,
                pg.growth_form_primary, pg.growth_form_secondary, pg.growth_vigor,
                pl.color AS leaf_color, pl.form AS leaf_form,
                pl.aroma AS leaf_aroma, pl.evergreen,
                pl.has_autumn_color, pl.autumn_color,
                pr.root_depth, pr.root_form, pr.anchorage AS root_anchorage,
                pfr.color AS fruit_color, pfr.size AS fruit_size,
                pfr.taste AS fruit_taste, pfr.juiciness, pfr.texture AS fruit_texture,
                po.breeder_name, po.breeder_company, po.breeder_country,
                po.breeder_year, po.hybrid_status, po.hybrid_parents,
                po.variety_protection, po.origin_region, po.breeding_method,
                pnu.propagation_method, pnu.germination_days,
                pnu.germination_temp_min_c, pnu.germination_temp_max_c,
                pnu.germination_light, pnu.rooting_days,
                pnu.development_days_to_yp, pnu.development_days_yp_to_fp,
                pnu.plants_per_sqm,
                pu.plant_parts, pu.purpose AS usage_purpose,
                pu.processing AS usage_processing, pu.storage AS usage_storage
            FROM plants p
            LEFT JOIN plant_names pn    ON p.id = pn.plant_id
            LEFT JOIN plant_site ps     ON p.id = ps.plant_id
            LEFT JOIN plant_flower pf   ON p.id = pf.plant_id
            LEFT JOIN plant_growth pg   ON p.id = pg.plant_id
            LEFT JOIN plant_leaf pl     ON p.id = pl.plant_id
            LEFT JOIN plant_root pr     ON p.id = pr.plant_id
            LEFT JOIN plant_fruit pfr   ON p.id = pfr.plant_id
            LEFT JOIN plant_origin po   ON p.id = po.plant_id
            LEFT JOIN plant_nursery pnu ON p.id = pnu.plant_id
            LEFT JOIN plant_usage pu    ON p.id = pu.plant_id
            WHERE p.web_name = :web_name
            LIMIT 1
        ");
        $stmt->bindValue(':web_name', $webName, SQLITE3_TEXT);
        $plant = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$plant) {
            $db->close();
            return [$json(['error' => 'Pflanze nicht gefunden']), $ct];
        }

        // Kategorien
        $stmt2 = $db->prepare('SELECT category_path FROM plant_categories WHERE plant_id = :id ORDER BY sort_order');
        $stmt2->bindValue(':id', $plant['id'], SQLITE3_INTEGER);
        $cats = $stmt2->execute();
        $plant['categories'] = [];
        while ($row = $cats->fetchArray(SQLITE3_ASSOC)) {
            $plant['categories'][] = $row['category_path'];
        }

        // Synonyme
        $stmt3 = $db->prepare('SELECT synonym_name, language, synonym_type FROM plant_synonyms WHERE plant_id = :id ORDER BY language');
        $stmt3->bindValue(':id', $plant['id'], SQLITE3_INTEGER);
        $syns = $stmt3->execute();
        $plant['synonyms'] = [];
        while ($row = $syns->fetchArray(SQLITE3_ASSOC)) {
            $plant['synonyms'][] = $row;
        }

        // Bilder (user/images/plants/<web_name>[-1..-5].(jpg|jpeg|png))
        // web_name stammt aus dem Browser: nur sichere Zeichen fuer den Dateipfad zulassen
        $plant['images'] = [];
        if (preg_match('~^[A-Za-z0-9_\-]+$~', $webName)) {
            $imgDir  = GRAV_ROOT . '/user/images/plants/';
            $imgBase = rtrim($grav['uri']->rootUrl(), '/') . '/user/images/plants/';
            for ($i = 1; $i <= 5; $i++) {
                foreach (['jpg', 'jpeg', 'png'] as $ext) {
                    $fname = $webName . '-' . $i . '.' . $ext;
                    if (is_file($imgDir . $fname)) {
                        $plant['images'][] = $imgBase . $fname;
                    }
                }
            }
            foreach (['jpg', 'jpeg', 'png'] as $ext) {
                $fname = $webName . '.' . $ext;
                if (is_file($imgDir . $fname) && !in_array($imgBase . $fname, $plant['images'], true)) {
                    array_unshift($plant['images'], $imgBase . $fname);
                }
            }
        }

        // Verfuegbarkeit: welche Gaertnereien bieten diese Pflanze oeffentlich an?
        $stmt4 = $db->prepare('SELECT nursery_name, contact_url FROM plant_availability WHERE plant_id = :id AND is_public = 1 ORDER BY nursery_name');
        $stmt4->bindValue(':id', $plant['id'], SQLITE3_INTEGER);
        $avail = $stmt4->execute();
        $plant['availability'] = [];
        while ($row = $avail->fetchArray(SQLITE3_ASSOC)) {
            $plant['availability'][] = ['name' => $row['nursery_name'], 'url' => $row['contact_url'] ?? ''];
        }

        $plant = array_filter($plant, fn($v) => $v !== null && $v !== '');
        $db->close();

        return [$json($plant), $ct];
    } catch (\Throwable $e) {
        error_log('PDB Portrait: ' . $e->getMessage());
        return [$json(['error' => 'Portrait konnte nicht geladen werden.']), $ct, 500];
    }
}

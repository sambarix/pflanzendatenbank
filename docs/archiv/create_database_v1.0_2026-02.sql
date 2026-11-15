-- oMioBio Plant Database Schema
-- Version: 1.0
-- Created: 2026-02-03
-- Authors: Peter Müller & Claude Sonnet 4.5

-- ============================================================================
-- TABLE 1: PLANTS - Kern-Taxonomie
-- ============================================================================
CREATE TABLE plants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    
    -- Taxonomie
    genus TEXT NOT NULL,
    species TEXT,
    subspecies TEXT,
    cultivar TEXT,
    ecotype TEXT,
    
    -- Generierte Namen
    botanical_name TEXT NOT NULL UNIQUE,
    web_name TEXT,
    matchcode TEXT,
    qr_code_data TEXT,
    
    -- Notizen
    notes TEXT,
    
    -- Tracking
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_plants_genus ON plants(genus);
CREATE INDEX idx_plants_web_name ON plants(web_name);
CREATE INDEX idx_plants_botanical_name ON plants(botanical_name);

-- ============================================================================
-- TABLE 2: PLANT_NAMES - Übersetzungen & Offizielle Namen
-- ============================================================================
CREATE TABLE plant_names (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id INTEGER NOT NULL,
    
    -- Übersetzungen
    name_de TEXT,
    name_en TEXT,
    name_fr TEXT,
    name_it TEXT,
    
    -- Offizielle Namen
    registration_name TEXT,
    exhibition_name TEXT,
    trade_name TEXT,
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

CREATE INDEX idx_names_plant_id ON plant_names(plant_id);
CREATE INDEX idx_names_de ON plant_names(name_de);
CREATE INDEX idx_names_en ON plant_names(name_en);

-- ============================================================================
-- TABLE 3: PLANT_SYNONYMS - Synonyme
-- ============================================================================
CREATE TABLE plant_synonyms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id INTEGER NOT NULL,
    
    synonym_name TEXT NOT NULL,
    language TEXT,
    synonym_type TEXT,
    notes TEXT,
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

CREATE INDEX idx_synonyms_plant_id ON plant_synonyms(plant_id);
CREATE INDEX idx_synonyms_name ON plant_synonyms(synonym_name);
CREATE INDEX idx_synonyms_language ON plant_synonyms(language);

-- ============================================================================
-- TABLE 4: PLANT_CATEGORIES - Kategorie-Vektorraum
-- ============================================================================
CREATE TABLE plant_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id INTEGER NOT NULL,
    
    category_path TEXT NOT NULL,
    sort_order INTEGER DEFAULT 0,
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

CREATE INDEX idx_categories_plant_id ON plant_categories(plant_id);
CREATE INDEX idx_categories_path ON plant_categories(category_path);

-- ============================================================================
-- TABLE 5: PLANT_ORIGIN - Züchtung & Registrierung
-- ============================================================================
CREATE TABLE plant_origin (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id INTEGER NOT NULL,
    
    -- Züchter (biologisch)
    breeder_name TEXT,
    breeder_company TEXT,
    breeder_country TEXT,
    breeder_year INTEGER,
    breeder_reference TEXT,
    
    -- Introducer (rechtlich)
    introducer_name TEXT,
    introducer_country TEXT,
    introducer_year INTEGER,
    
    -- Hybrid-Details
    hybrid_status TEXT,
    hybrid_parents TEXT,
    ploidy TEXT,
    
    -- Rechtlicher Schutz
    variety_protection BOOLEAN DEFAULT 0,
    trademark_protection BOOLEAN DEFAULT 0,
    
    -- Sonstiges
    origin_region TEXT,
    breeding_method TEXT,
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

CREATE INDEX idx_origin_plant_id ON plant_origin(plant_id);
CREATE INDEX idx_origin_breeder ON plant_origin(breeder_name);
CREATE INDEX idx_origin_company ON plant_origin(breeder_company);
CREATE INDEX idx_origin_introducer ON plant_origin(introducer_name);

-- ============================================================================
-- TABLE 6: PLANT_SITE - Standort-Anforderungen
-- ============================================================================
CREATE TABLE plant_site (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id INTEGER NOT NULL,
    
    -- Winterhärte
    hardiness_zone TEXT,
    hardiness_temp_min REAL,
    hardiness_temp_max REAL,
    
    -- Licht & Feuchtigkeit
    light_requirement TEXT,
    moisture_requirement TEXT,
    
    -- Boden
    soil_type TEXT,
    soil_ph_min REAL,
    soil_ph_max REAL,
    
    -- Nährstoffe
    nutrient_demand TEXT,
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

CREATE INDEX idx_site_plant_id ON plant_site(plant_id);
CREATE INDEX idx_site_zone ON plant_site(hardiness_zone);

-- ============================================================================
-- TABLE 7: PLANT_FLOWER - Blüten-Merkmale
-- ============================================================================
CREATE TABLE plant_flower (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id INTEGER NOT NULL,
    
    -- Eigenschaften
    color TEXT,
    fragrance TEXT,
    size TEXT,
    fullness TEXT,
    form TEXT,
    vase_life TEXT,
    blooming_cycle TEXT,
    
    -- Blütezeit (12 Monate)
    bloom_january BOOLEAN DEFAULT 0,
    bloom_february BOOLEAN DEFAULT 0,
    bloom_march BOOLEAN DEFAULT 0,
    bloom_april BOOLEAN DEFAULT 0,
    bloom_may BOOLEAN DEFAULT 0,
    bloom_june BOOLEAN DEFAULT 0,
    bloom_july BOOLEAN DEFAULT 0,
    bloom_august BOOLEAN DEFAULT 0,
    bloom_september BOOLEAN DEFAULT 0,
    bloom_october BOOLEAN DEFAULT 0,
    bloom_november BOOLEAN DEFAULT 0,
    bloom_december BOOLEAN DEFAULT 0,
    
    -- Notizen
    notes TEXT,
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

CREATE INDEX idx_flower_plant_id ON plant_flower(plant_id);

-- ============================================================================
-- TABLE 8: PLANT_FRUIT - Früchte & Erntegut
-- ============================================================================
CREATE TABLE plant_fruit (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id INTEGER NOT NULL,
    
    -- Eigenschaften
    color TEXT,
    size TEXT,
    taste TEXT,
    juiciness TEXT,
    texture TEXT,
    
    -- Erntezeit (12 Monate)
    harvest_january BOOLEAN DEFAULT 0,
    harvest_february BOOLEAN DEFAULT 0,
    harvest_march BOOLEAN DEFAULT 0,
    harvest_april BOOLEAN DEFAULT 0,
    harvest_may BOOLEAN DEFAULT 0,
    harvest_june BOOLEAN DEFAULT 0,
    harvest_july BOOLEAN DEFAULT 0,
    harvest_august BOOLEAN DEFAULT 0,
    harvest_september BOOLEAN DEFAULT 0,
    harvest_october BOOLEAN DEFAULT 0,
    harvest_november BOOLEAN DEFAULT 0,
    harvest_december BOOLEAN DEFAULT 0,
    
    -- Entwicklung
    development_days INTEGER,
    
    -- Notizen
    notes TEXT,
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

CREATE INDEX idx_fruit_plant_id ON plant_fruit(plant_id);

-- ============================================================================
-- TABLE 9: PLANT_GROWTH - Wuchs
-- ============================================================================
CREATE TABLE plant_growth (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id INTEGER NOT NULL,
    
    -- Zyklus
    growth_cycle TEXT,
    
    -- Größe
    height_cm INTEGER,
    width_cm INTEGER,
    
    -- Form
    growth_form_primary TEXT,
    growth_form_secondary TEXT,
    
    -- Wuchskraft
    growth_vigor TEXT,
    
    -- Notizen
    notes TEXT,
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

CREATE INDEX idx_growth_plant_id ON plant_growth(plant_id);

-- ============================================================================
-- TABLE 10: PLANT_LEAF - Blatt-Merkmale
-- ============================================================================
CREATE TABLE plant_leaf (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id INTEGER NOT NULL,
    
    -- Eigenschaften
    color TEXT,
    form TEXT,
    aroma TEXT,
    size TEXT,
    
    -- Herbstfärbung
    has_autumn_color BOOLEAN DEFAULT 0,
    autumn_color TEXT,
    
    -- Immergrün
    evergreen BOOLEAN DEFAULT 0,
    
    -- Notizen
    notes TEXT,
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

CREATE INDEX idx_leaf_plant_id ON plant_leaf(plant_id);

-- ============================================================================
-- TABLE 11: PLANT_ROOT - Wurzel-Merkmale
-- ============================================================================
CREATE TABLE plant_root (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id INTEGER NOT NULL,
    
    -- Eigenschaften
    root_depth TEXT,
    root_form TEXT,
    anchorage TEXT,
    taste TEXT,
    
    -- Notizen
    notes TEXT,
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

CREATE INDEX idx_root_plant_id ON plant_root(plant_id);

-- ============================================================================
-- TABLE 12: PLANT_NURSERY - Kultur-Daten (UNIVERSELL)
-- ============================================================================
CREATE TABLE plant_nursery (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id INTEGER NOT NULL,
    
    -- Vermehrung
    propagation_method TEXT,
    
    -- Keimung
    germination_days INTEGER,
    germination_temp_min_c INTEGER,
    germination_temp_max_c INTEGER,
    germination_light TEXT,
    
    -- Bewurzelung
    rooting_days INTEGER,
    
    -- Entwicklung
    development_days_to_yp INTEGER,
    development_days_yp_to_fp INTEGER,
    
    -- Platzbedarf
    plants_per_sqm REAL,
    
    -- Notizen
    notes TEXT,
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

CREATE INDEX idx_nursery_plant_id ON plant_nursery(plant_id);

-- ============================================================================
-- TABLE 13: POT_SIZES - Topfgrößen-Definition (PRO GÄRTNEREI)
-- ============================================================================
CREATE TABLE pot_sizes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nursery_id INTEGER,
    
    pot_code TEXT NOT NULL,
    volume_liters REAL,
    plants_per_sqm INTEGER,
    weight_kg_per_1000 REAL,
    sort_order INTEGER DEFAULT 0,
    
    UNIQUE(nursery_id, pot_code)
);

CREATE INDEX idx_pot_sizes_nursery ON pot_sizes(nursery_id);
CREATE INDEX idx_pot_sizes_code ON pot_sizes(pot_code);

-- ============================================================================
-- TABLE 14: PLANT_INVENTORY - Bestand (PRO GÄRTNEREI)
-- ============================================================================
CREATE TABLE plant_inventory (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id INTEGER NOT NULL,
    nursery_id INTEGER NOT NULL,
    
    pot_size TEXT,
    quantity INTEGER,
    status TEXT,
    delivery_week INTEGER,
    delivery_year INTEGER,
    price_chf REAL,
    location TEXT,
    
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

CREATE INDEX idx_inventory_plant_id ON plant_inventory(plant_id);
CREATE INDEX idx_inventory_nursery ON plant_inventory(nursery_id);

-- ============================================================================
-- TABLE 15: PLANT_USAGE - Verwendung
-- ============================================================================
CREATE TABLE plant_usage (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id INTEGER NOT NULL,
    
    -- Verwendung
    plant_parts TEXT,
    purpose TEXT,
    processing TEXT,
    storage TEXT,
    
    -- Erntezeit (12 Monate)
    harvest_january BOOLEAN DEFAULT 0,
    harvest_february BOOLEAN DEFAULT 0,
    harvest_march BOOLEAN DEFAULT 0,
    harvest_april BOOLEAN DEFAULT 0,
    harvest_may BOOLEAN DEFAULT 0,
    harvest_june BOOLEAN DEFAULT 0,
    harvest_july BOOLEAN DEFAULT 0,
    harvest_august BOOLEAN DEFAULT 0,
    harvest_september BOOLEAN DEFAULT 0,
    harvest_october BOOLEAN DEFAULT 0,
    harvest_november BOOLEAN DEFAULT 0,
    harvest_december BOOLEAN DEFAULT 0,
    
    -- Notizen
    notes TEXT,
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

CREATE INDEX idx_usage_plant_id ON plant_usage(plant_id);

-- ============================================================================
-- TABLE 16: FIELD_OPTIONS - Zentrale Dropdown-Verwaltung
-- ============================================================================
CREATE TABLE field_options (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    
    field_name TEXT NOT NULL,
    option_value TEXT NOT NULL,
    sort_order INTEGER DEFAULT 0,
    is_custom BOOLEAN DEFAULT 0,
    usage_count INTEGER DEFAULT 0,
    last_used TIMESTAMP,
    
    UNIQUE(field_name, option_value)
);

CREATE INDEX idx_options_field ON field_options(field_name);
CREATE INDEX idx_options_usage ON field_options(usage_count DESC);

-- ============================================================================
-- TABLE 17: PLANT_AVAILABILITY - Öffentliche Verfügbarkeit
-- ============================================================================
CREATE TABLE plant_availability (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id INTEGER NOT NULL,
    nursery_id INTEGER NOT NULL,
    inventory_id INTEGER,
    
    -- Sichtbarkeit
    is_public BOOLEAN DEFAULT 0,
    
    -- Angebot
    pot_size TEXT,
    available_quantity INTEGER,
    
    -- Preis
    price_chf REAL,
    
    -- Lieferung
    delivery_week INTEGER,
    delivery_year INTEGER,
    delivery_info TEXT,
    
    -- Labels
    labels TEXT,
    
    -- Kontakt
    contact_url TEXT,
    
    -- Tracking
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Notizen
    notes TEXT,
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE,
    FOREIGN KEY (inventory_id) REFERENCES plant_inventory(id) ON DELETE SET NULL
);

CREATE INDEX idx_availability_plant_id ON plant_availability(plant_id);
CREATE INDEX idx_availability_nursery_id ON plant_availability(nursery_id);
CREATE INDEX idx_availability_public ON plant_availability(is_public);
CREATE INDEX idx_availability_inventory ON plant_availability(inventory_id);

-- ============================================================================
-- TABLE 18: PLANT_TRAITS - Flexibles Auffangbecken (EAV)
-- ============================================================================
CREATE TABLE plant_traits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id INTEGER NOT NULL,
    
    -- Eigenschaft
    trait_name TEXT NOT NULL,
    
    -- Wert (flexibel)
    value_text TEXT,
    value_numeric REAL,
    value_unit TEXT,
    
    -- Herkunft
    data_source TEXT,
    
    -- Sichtbarkeit
    visibility TEXT DEFAULT 'public',
    
    -- Tracking
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

CREATE INDEX idx_traits_plant_id ON plant_traits(plant_id);
CREATE INDEX idx_traits_name ON plant_traits(trait_name);
CREATE INDEX idx_traits_visibility ON plant_traits(visibility);

-- ============================================================================
-- ZUSÄTZLICHE TABELLEN (nicht pflanzenbezogen)
-- ============================================================================

-- NURSERIES - Gärtnereien-Stammdaten
CREATE TABLE nurseries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    
    name TEXT NOT NULL,
    location TEXT,
    contact TEXT,
    labels TEXT,
    settings TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- USERS - Benutzer (für später)
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT,
    nursery_id INTEGER,
    role TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (nursery_id) REFERENCES nurseries(id)
);

CREATE INDEX idx_users_username ON users(username);

-- ============================================================================
-- ENDE SCHEMA
-- ============================================================================

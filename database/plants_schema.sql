-- Schema der zentralen Stammdaten-Datenbank plants.db (nur Struktur, keine Daten)
-- Erzeugt am 15.11.2026 aus der laufenden Entwicklungsdatenbank.

CREATE TABLE categories (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    parent_id INTEGER REFERENCES categories(id) ON DELETE CASCADE,
    name      TEXT NOT NULL,
    path      TEXT NOT NULL UNIQUE
);

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

CREATE TABLE plant_availability (
        id               INTEGER PRIMARY KEY AUTOINCREMENT,
        plant_id         INTEGER NOT NULL,
        nursery_id       INTEGER,              -- nullable, nicht mehr verwendet
        source_db        TEXT NOT NULL,        -- z.B. 'peter.db'
        nursery_name     TEXT NOT NULL,
        contact_url      TEXT,
        -- Zukünftige Felder (nullable, für späteren Ausbau)
        pot_size         TEXT,
        available_quantity INTEGER,
        price_chf        REAL,
        delivery_week    INTEGER,
        delivery_year    INTEGER,
        is_public        INTEGER DEFAULT 1,
        last_updated     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
    );

CREATE TABLE plant_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id INTEGER NOT NULL,
    
    category_path TEXT NOT NULL,
    sort_order INTEGER DEFAULT 0,
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

CREATE TABLE plant_content (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id            INTEGER NOT NULL UNIQUE,
    lang                TEXT NOT NULL DEFAULT 'de',
    description_short   TEXT,
    description_medium  TEXT,
    description_long    TEXT,
    meta_title          TEXT,
    meta_description    TEXT,
    meta_keywords       TEXT,
    tags                TEXT,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plant_id) REFERENCES plants(id)
);

CREATE TABLE "plant_flower" (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        plant_id INTEGER NOT NULL,
        color TEXT, fragrance TEXT, size TEXT, fullness TEXT, form TEXT,
        vase_life TEXT, blooming_cycle TEXT,
        bloom_months TEXT,
        notes TEXT,
        FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
    );

CREATE TABLE "plant_fruit" (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        plant_id INTEGER NOT NULL,
        color TEXT, size TEXT, taste TEXT, juiciness TEXT, texture TEXT,
        harvest_months TEXT,
        development_days INTEGER,
        notes TEXT,
        FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
    );

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

CREATE TABLE plant_nursery_content (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id            INTEGER NOT NULL,
    nursery_id          INTEGER NOT NULL,
    lang                TEXT NOT NULL DEFAULT 'de',
    description_short   TEXT,
    description_medium  TEXT,
    description_long    TEXT,
    meta_title          TEXT,
    meta_description    TEXT,
    meta_keywords       TEXT,
    tags                TEXT,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(plant_id, nursery_id, lang),
    FOREIGN KEY (plant_id) REFERENCES plants(id)
);

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

CREATE TABLE plant_synonyms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id INTEGER NOT NULL,
    
    synonym_name TEXT NOT NULL,
    language TEXT,
    synonym_type TEXT,
    notes TEXT,
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

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

CREATE TABLE "plant_usage" (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        plant_id INTEGER NOT NULL,
        plant_parts TEXT, purpose TEXT, processing TEXT, storage TEXT,
        harvest_months TEXT,
        notes TEXT,
        FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
    );

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
, description_short TEXT, description_long TEXT, care_instructions TEXT, meta_text TEXT, class_group TEXT);

CREATE VIRTUAL TABLE plants_search USING fts5(
    genus,
    species,
    cultivar,
    botanical_name,
    notes,
    content='plants',
    content_rowid='id'
);

CREATE TABLE pot_sizes (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    nursery_id          INTEGER,
    pot_code            TEXT NOT NULL,
    pot_name            TEXT,
    pot_type            TEXT,
    pot_form            TEXT,
    volume_liters       REAL,
    diameter_mm         INTEGER,
    height_mm           INTEGER,
    width_mm            INTEGER,
    material            TEXT,
    plants_per_sqm      REAL,
    sort_order          INTEGER DEFAULT 0,
    notes               TEXT,
    UNIQUE(nursery_id, pot_code)
);

CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT,
    nursery_id INTEGER,
    role TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (nursery_id) REFERENCES nurseries(id)
);

CREATE UNIQUE INDEX idx_avail_plant_source
    ON plant_availability(plant_id, source_db)
;

CREATE INDEX idx_categories_parent ON categories(parent_id);

CREATE INDEX idx_categories_path ON plant_categories(category_path);

CREATE INDEX idx_categories_plant_id ON plant_categories(plant_id);

CREATE INDEX idx_flower_plant_id ON plant_flower(plant_id);

CREATE INDEX idx_fruit_plant_id ON plant_fruit(plant_id);

CREATE INDEX idx_growth_plant_id ON plant_growth(plant_id);

CREATE INDEX idx_leaf_plant_id ON plant_leaf(plant_id);

CREATE INDEX idx_names_de ON plant_names(name_de);

CREATE INDEX idx_names_en ON plant_names(name_en);

CREATE INDEX idx_names_plant_id ON plant_names(plant_id);

CREATE INDEX idx_nursery_plant_id ON plant_nursery(plant_id);

CREATE INDEX idx_options_field ON field_options(field_name);

CREATE INDEX idx_options_usage ON field_options(usage_count DESC);

CREATE INDEX idx_origin_breeder ON plant_origin(breeder_name);

CREATE INDEX idx_origin_company ON plant_origin(breeder_company);

CREATE INDEX idx_origin_introducer ON plant_origin(introducer_name);

CREATE INDEX idx_origin_plant_id ON plant_origin(plant_id);

CREATE INDEX idx_plants_botanical_name ON plants(botanical_name);

CREATE INDEX idx_plants_genus ON plants(genus);

CREATE INDEX idx_plants_web_name ON plants(web_name);

CREATE INDEX idx_root_plant_id ON plant_root(plant_id);

CREATE INDEX idx_site_plant_id ON plant_site(plant_id);

CREATE INDEX idx_site_zone ON plant_site(hardiness_zone);

CREATE INDEX idx_synonyms_language ON plant_synonyms(language);

CREATE INDEX idx_synonyms_name ON plant_synonyms(synonym_name);

CREATE INDEX idx_synonyms_plant_id ON plant_synonyms(plant_id);

CREATE INDEX idx_traits_name ON plant_traits(trait_name);

CREATE INDEX idx_traits_plant_id ON plant_traits(plant_id);

CREATE INDEX idx_traits_visibility ON plant_traits(visibility);

CREATE INDEX idx_usage_plant_id ON plant_usage(plant_id);

CREATE INDEX idx_users_username ON users(username);

-- Nach dem Import der Stammdaten den Suchindex (FTS5) neu aufbauen:
-- INSERT INTO plants_search(plants_search) VALUES('rebuild');

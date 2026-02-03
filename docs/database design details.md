# Datenbank-Design Entscheidungen

**Projekt:** Pflanzendatenbank  
**Datum Start:** 27. Januar 2026  
**Letztes Update:** 03. Februar 2026, 22:00 Uhr  
**Version:** Live-Dokument (wird laufend aktualisiert)

---

## KERN-PRINZIPIEN

### 1. Optionale Komplexität
**Problem:** Salatsetzling braucht minimale Daten, Rosen brauchen umfassende Details.

**Lösung:** 
- **PLANTS-Tabelle** = Minimum für ALLE Pflanzen (genus, species, cultivar)
- **Alle anderen Tabellen** = Optional, nur wenn Daten vorhanden
- **Keine NULL-Verschwendung** = Tabellen existieren nur mit Daten

**Beispiele:**
```
Salat: Nur 1 Zeile in plants
Rose:  1 Zeile plants + 1 names + 23 synonyms + 1 origin + flower + growth
```

### 2. THINK FIRST - CODE LATER
- Zuerst durchdenken, dann dokumentieren
- Kein voreiliges Coden
- Claude = Denkpartner + Dokumentations-Maschine

### 3. Hybrid-Ansatz
- **Feste Tabellen** für Standard-Eigenschaften (90% Use-Case)
- **EAV (plant_traits)** nur für Sonderfälle
- **field_options** für zentrale Dropdown-Verwaltung

### 4. Logische Gruppierung
- Taxonomie → plants
- Übersetzungen → plant_names
- Synonyme → plant_synonyms
- Züchtung → plant_origin
- Standort → plant_site
- Blüte → plant_flower
- usw.

---

## FINALISIERTE TABELLEN (1-5)

### ✅ TABELLE 1: PLANTS (Kern-Taxonomie)

**Status:** Finalisiert - 27. Januar 2026

**Felder:**
```sql
CREATE TABLE plants (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    genus               TEXT NOT NULL,              -- Gattung
    species             TEXT,                       -- Art
    subspecies          TEXT,                       -- Unterart
    cultivar            TEXT,                       -- Sorte
    ecotype             TEXT,                       -- Ökotyp
    botanical_name      TEXT NOT NULL UNIQUE,       -- "Rosa damascena 'de Resht'"
    web_name            TEXT,                       -- "rosa-damascena-de-resht"
    matchcode           TEXT,                       -- "ROSDAMRES"
    qr_code_data        TEXT,                       -- QR-Code Inhalt
    notes               TEXT,                       -- Freifeld für alles andere
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indizes
CREATE INDEX idx_plants_genus ON plants(genus);
CREATE INDEX idx_plants_web_name ON plants(web_name);
CREATE INDEX idx_plants_botanical_name ON plants(botanical_name);
```

**Entscheidungen:**
- ❌ KEINE primary_category (gehört in plant_categories)
- ❌ KEIN Status aktiv/veraltet (gehört in plant_nursery/plant_availability)
- ❌ KEIN hybrid_status (gehört in plant_origin - Züchtung!)
- ✅ notes als Freifeld für unvorhergesehene Dinge
- ✅ subspecies und ecotype hinzugefügt

---

### ✅ TABELLE 2: PLANT_NAMES (Übersetzungen)

**Status:** Finalisiert - 27. Januar 2026

**Felder:**
```sql
CREATE TABLE plant_names (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id            INTEGER NOT NULL,
    
    -- Übersetzungen (Haupt-Namen)
    name_de             TEXT,                       -- Deutscher Name
    name_en             TEXT,                       -- Englischer Name
    name_fr             TEXT,                       -- Französischer Name
    name_it             TEXT,                       -- Italienischer Name
    
    -- Offizielle Namen (für Ausstellungen/Handel)
    registration_name   TEXT,                       -- Offizieller Registrierungsname
    exhibition_name     TEXT,                       -- Ausstellungsname
    trade_name          TEXT,                       -- Handelsname
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

-- Indizes
CREATE INDEX idx_names_plant_id ON plant_names(plant_id);
CREATE INDEX idx_names_de ON plant_names(name_de);
CREATE INDEX idx_names_en ON plant_names(name_en);
```

**Quelle:** HelpMeFind.com - Rosa gallica 'Officinalis'

---

### ✅ TABELLE 3: PLANT_SYNONYMS (Viele Synonyme möglich!)

**Status:** Finalisiert - 27. Januar 2026

**Felder:**
```sql
CREATE TABLE plant_synonyms (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id            INTEGER NOT NULL,
    
    synonym_name        TEXT NOT NULL,              -- Der Synonym-Name
    language            TEXT,                       -- "de", "en", "fr", "la", "it"
    synonym_type        TEXT,                       -- "common", "historical", "botanical", "regional", "trade"
    notes               TEXT,                       -- z.B. "(obsolete)" oder "(regional: Süddeutschland)"
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

-- Indizes
CREATE INDEX idx_synonyms_plant_id ON plant_synonyms(plant_id);
CREATE INDEX idx_synonyms_name ON plant_synonyms(synonym_name);
CREATE INDEX idx_synonyms_language ON plant_synonyms(language);
```

**Quelle:** HelpMeFind.com - Rosa gallica 'Officinalis' hat 23 Synonyme!

---

### ✅ TABELLE 4: PLANT_CATEGORIES (Kategorie-Vektorraum)

**Status:** Finalisiert - 27. Januar 2026

**Kern-Konzept:** Multi-dimensionaler Vektorraum statt starre Hierarchie!

**Felder:**
```sql
CREATE TABLE plant_categories (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id            INTEGER NOT NULL,
    category_path       TEXT NOT NULL,              -- "Nutzpflanze/Gemüse/Fruchtgemüse"
    sort_order          INTEGER DEFAULT 0,          -- Wichtigster Pfad = 1
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

-- Indizes
CREATE INDEX idx_categories_plant_id ON plant_categories(plant_id);
CREATE INDEX idx_categories_path ON plant_categories(category_path);
```

**Beispiel:** Lavendel hat 6 verschiedene Kategoriepfade gleichzeitig!

---

### ✅ TABELLE 5: PLANT_ORIGIN (Herkunft/Züchtung)

**Status:** Finalisiert - 28. Januar 2026

**Kern-Konzept:** Trennung zwischen ZÜCHTUNG (biologisch) und REGISTRIERUNG (rechtlich)

**Felder:**
```sql
CREATE TABLE plant_origin (
    id                      INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id                INTEGER NOT NULL,
    
    -- ZÜCHTUNG (Biologischer Akt)
    breeder_name            TEXT,               -- Person: "Evers, Hans Jürgen"
    breeder_company         TEXT,               -- Firma: "Rosen Tantau GmbH"
    breeder_country         TEXT,               -- "DE", "FR", "GB", "AU-HU"
    breeder_year            INTEGER,            -- Jahr der Kreuzung/Züchtung
    breeder_reference       TEXT,               -- Interner Name: "TAN06977", "Meilove"
    
    -- REGISTRIERUNG (Rechtlicher Akt)
    introducer_name         TEXT,               -- Rechtsperson: "Meilland International"
    introducer_country      TEXT,               -- "FR", "US"
    introducer_year         INTEGER,            -- Jahr der Markteinführung/Registrierung
    
    -- HYBRID/ZÜCHTUNGS-DETAILS
    hybrid_status           TEXT,               -- "species", "hybrid", "cultivar", "selection"
    hybrid_parents          TEXT,               -- "Rosa damascena × Rosa gallica"
    ploidy                  TEXT,               -- "diploid", "triploid", "tetraploid", "unknown"
    
    -- RECHTLICHER SCHUTZ
    variety_protection      BOOLEAN DEFAULT 0,  -- Sortenschutz (CPVO, UPOV)
    trademark_protection    BOOLEAN DEFAULT 0,  -- Markenschutz (®, ™)
    
    -- SONSTIGES
    origin_region           TEXT,               -- "Shropshire, England"
    breeding_method         TEXT,               -- "Kreuzung", "Mutation", "Sämling", "Auslese"
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

-- Indizes
CREATE INDEX idx_origin_plant_id ON plant_origin(plant_id);
CREATE INDEX idx_origin_breeder ON plant_origin(breeder_name);
CREATE INDEX idx_origin_breeder_ref ON plant_origin(breeder_reference);
CREATE INDEX idx_origin_company ON plant_origin(breeder_company);
CREATE INDEX idx_origin_introducer ON plant_origin(introducer_name);
```

**Namen-Struktur:**
- **Sorte** (Markenname): plants.cultivar = "Bonica 82"
- **Züchtername** (intern): breeder_reference = "MEIdomonac"
- **Synonyme**: plant_synonyms = "Demon", "Bonica Meidiland"

---

### ✅ TABELLE 6: PLANT_SITE (Standort-Anforderungen)

**Status:** Finalisiert - 03. Februar 2026

**Kern-Konzept:** Hybrid aus menschenlesbaren Zonen und maschinenlesbaren Temperaturen

**Felder:**
```sql
CREATE TABLE plant_site (
    id                      INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id                INTEGER NOT NULL,
    
    -- WINTERHÄRTE (3 gekoppelte Felder!) ✅
    hardiness_zone          TEXT,                   -- "Zone 7a (-17.7 bis -15.0°C)"
    hardiness_temp_min_c    REAL,                   -- -17.7 (kälteste Temperatur)
    hardiness_temp_max_c    REAL,                   -- -15.0 (wärmste Temperatur der Zone)
    
    -- LICHT ✅
    light_requirement       TEXT,                   -- "vollsonnig", "sonnig", "halbschattig", "absonnig", "schattig"
    
    -- FEUCHTIGKEIT ✅
    moisture_requirement    TEXT,                   -- "trocken", "halbtrocken", "ausgeglichen", "feucht", "nass"
    
    -- BODENART ⚠️ DISKUSSION CHALLENGE
    soil_type               TEXT,                   -- "sandig", "lehmig", "tonig", "humos", "kiesig", "anspruchslos"?
    
    -- pH-WERT ⚠️ DISKUSSION CHALLENGE
    soil_ph_min             REAL,                   -- 6.0
    soil_ph_max             REAL,                   -- 7.5
    
    -- NÄHRSTOFFBEDARF ✅
    nutrient_demand         TEXT,                   -- "niedrig", "mittel", "hoch"
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

-- Indizes
CREATE INDEX idx_site_plant_id ON plant_site(plant_id);
CREATE INDEX idx_site_zone ON plant_site(hardiness_zone);
CREATE INDEX idx_site_temp_min ON plant_site(hardiness_temp_min_c);
CREATE INDEX idx_site_temp_max ON plant_site(hardiness_temp_max_c);
CREATE INDEX idx_site_light ON plant_site(light_requirement);
CREATE INDEX idx_site_moisture ON plant_site(moisture_requirement);
```

**Entscheidungen:**

**1. WINTERHÄRTE** ✅ **FINALISIERT**
- **3 gekoppelte Felder**: zone (human) + temp_min/max (machine)
- **CELSIUS-Werte** (nicht Fahrenheit!)
- **Schweizer Zonen korrekt**:
  - Ballwil (LU) = Zone 7a (-17.7 bis -15.0°C)
  - Genf = Zone 8a/8b (-12.2 bis -6.7°C)
  - Tessin Seenähe = Zone 9a (-6.6 bis -3.9°C)
  - St. Moritz = Zone 5a (-28.8 bis -26.2°C)

**field_options für Winterhärte:**
```sql
INSERT INTO field_options (field_name, option_value, sort_order, is_custom) VALUES
('hardiness_zone', 'Zone 2a (-45.6 bis -42.8°C)', 1, 0),
('hardiness_zone', 'Zone 2b (-42.7 bis -40.0°C)', 2, 0),
('hardiness_zone', 'Zone 3a (-39.9 bis -37.3°C)', 3, 0),
('hardiness_zone', 'Zone 3b (-37.2 bis -34.5°C)', 4, 0),
('hardiness_zone', 'Zone 4a (-34.4 bis -31.7°C)', 5, 0),
('hardiness_zone', 'Zone 4b (-31.6 bis -28.9°C)', 6, 0),
('hardiness_zone', 'Zone 5a (-28.8 bis -26.2°C)', 7, 0),
('hardiness_zone', 'Zone 5b (-26.1 bis -23.4°C)', 8, 0),
('hardiness_zone', 'Zone 6a (-23.3 bis -20.6°C)', 9, 0),
('hardiness_zone', 'Zone 6b (-20.5 bis -17.8°C)', 10, 0),
('hardiness_zone', 'Zone 7a (-17.7 bis -15.0°C)', 11, 0),  -- Ballwil ⭐
('hardiness_zone', 'Zone 7b (-14.9 bis -12.3°C)', 12, 0),
('hardiness_zone', 'Zone 8a (-12.2 bis -9.5°C)', 13, 0),   -- Genf
('hardiness_zone', 'Zone 8b (-9.4 bis -6.7°C)', 14, 0),
('hardiness_zone', 'Zone 9a (-6.6 bis -3.9°C)', 15, 0),    -- Tessin
('hardiness_zone', 'Zone 9b (-3.8 bis -1.2°C)', 16, 0);
```

**2. LICHT** ✅ **FINALISIERT**
- **vollsonnig**: Niemals Schatten (z.B. freies Feld, Südseite)
- **sonnig**: 6-8h direkte Sonne
- **halbschattig**: 3-6h Sonne oder gefiltert
- **absonnig**: Niemals direkte Sonne (z.B. Nordseite Haus, hell aber schattig)
- **schattig**: <3h Sonne, dunkel

**field_options für Licht:**
```sql
INSERT INTO field_options (field_name, option_value, sort_order, is_custom) VALUES
('light_requirement', 'vollsonnig', 1, 0),
('light_requirement', 'sonnig', 2, 0),
('light_requirement', 'halbschattig', 3, 0),
('light_requirement', 'absonnig', 4, 0),
('light_requirement', 'schattig', 5, 0);
```

**3. FEUCHTIGKEIT** ✅ **FINALISIERT**
```sql
INSERT INTO field_options (field_name, option_value, sort_order, is_custom) VALUES
('moisture_requirement', 'trocken', 1, 0),
('moisture_requirement', 'halbtrocken', 2, 0),
('moisture_requirement', 'ausgeglichen', 3, 0),
('moisture_requirement', 'feucht', 4, 0),
('moisture_requirement', 'nass', 5, 0);
```

**4. BODENART** ⚠️ **DISKUSSION CHALLENGE**

**Aktueller Vorschlag:**
```sql
INSERT INTO field_options (field_name, option_value, sort_order, is_custom) VALUES
('soil_type', 'sandig', 1, 0),
('soil_type', 'lehmig', 2, 0),
('soil_type', 'tonig', 3, 0),
('soil_type', 'humos', 4, 0),
('soil_type', 'kiesig', 5, 0),
('soil_type', 'anspruchslos', 6, 0);
```

**Offene Fragen für Challenge:**
- Reichen diese 6 Kategorien?
- Brauchen wir Kombinationen ("sandig-lehmig")?
- Oder zwei Felder: soil_texture + soil_character?
- Oder Kategorien wie "durchlässig", "wasserhaltend", "nährstoffreich"?

**5. pH-WERT** ⚠️ **DISKUSSION CHALLENGE**

**Aktuell: Zwei Zahlenfelder (Option A)**
```sql
soil_ph_min             REAL,   -- 6.0
soil_ph_max             REAL,   -- 7.5
```

**Beispiele:**
```
Rose:           6.0 - 7.0
Rhododendron:   4.0 - 5.5
Lavendel:       6.5 - 8.0
Anspruchslos:   NULL - NULL
```

**Offene Fragen für Challenge:**
- Zahlenfelder zu komplex für Erfassung?
- Dropdown mit Standard-Ranges benutzerfreundlicher?
- Oder beides kombinieren (Dropdown mit Override)?

**6. NÄHRSTOFFBEDARF** ✅ **FINALISIERT**
```sql
INSERT INTO field_options (field_name, option_value, sort_order, is_custom) VALUES
('nutrient_demand', 'niedrig', 1, 0),    -- Schwachzehrer
('nutrient_demand', 'mittel', 2, 0),     -- Mittelzehrer (Default!)
('nutrient_demand', 'hoch', 3, 0);       -- Starkzehrer
```

**Beispiel-Daten:**

```sql
-- Rose 'Bonica 82'
INSERT INTO plant_site VALUES (
    NULL, 1,
    'Zone 4b (-31.6 bis -28.9°C)', -31.6, -28.9,
    'sonnig', 'ausgeglichen', 'lehmig',
    6.0, 7.0, 'mittel'
);

-- Lavendel
INSERT INTO plant_site VALUES (
    NULL, 2,
    'Zone 7a (-17.7 bis -15.0°C)', -17.7, -15.0,
    'vollsonnig', 'trocken', 'sandig',
    6.5, 8.0, 'niedrig'
);

-- Rhododendron
INSERT INTO plant_site VALUES (
    NULL, 3,
    'Zone 6a (-23.3 bis -20.6°C)', -23.3, -20.6,
    'halbschattig', 'feucht', 'humos',
    4.0, 5.5, 'mittel'
);

-- Farn (Waldpflanze)
INSERT INTO plant_site VALUES (
    NULL, 4,
    'Zone 5a (-28.8 bis -26.2°C)', -28.8, -26.2,
    'absonnig', 'feucht', 'humos',
    5.5, 6.5, 'niedrig'
);
```

---

### ✅ TABELLE 7: PLANT_FLOWER (Blüten)

**Status:** Finalisiert - 03. Februar 2026

**Kern-Konzept:** Blütenmerkmale für Zierpflanzen, Rosen, Schnittblumen

**Felder:**
```sql
CREATE TABLE plant_flower (
    id                      INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id                INTEGER NOT NULL,
    
    -- BLÜTENFARBE ⚠️ DISKUSSION CHALLENGE
    color                   TEXT,                   -- "rot", "rosa", "weiß", "gelb", etc.
    
    -- DUFT ✅
    fragrance               TEXT,                   -- "nicht duftend", "leicht duftend", "duftend", "stark duftend", "sehr stark duftend"
    
    -- BLÜTENGRÖSSE ✅
    size                    TEXT,                   -- "sehr klein", "klein", "mittelgross", "gross", "sehr gross"
    
    -- BLÜTENFÜLLUNG ✅
    fullness                TEXT,                   -- "einfach", "halbgefüllt", "gefüllt", "sehr gefüllt"
    
    -- BLÜTENFORM ⚠️ DISKUSSION CHALLENGE
    form                    TEXT,                   -- "schalenförmig", "rosettenförmig", "pompon", etc.
    
    -- HALTBARKEIT (Vase) ✅
    vase_life               TEXT,                   -- "kurz", "mittel", "lang"
    
    -- BLÜHZYKLUS ✅
    blooming_cycle          TEXT,                   -- "einmalblühend", "nachblühend", "öfterblühend", "dauerblühend"
    
    -- BLÜTEZEIT (12 BOOLEAN-Felder für Mehrfachauswahl!) ✅
    blooms_january          BOOLEAN DEFAULT 0,
    blooms_february         BOOLEAN DEFAULT 0,
    blooms_march            BOOLEAN DEFAULT 0,
    blooms_april            BOOLEAN DEFAULT 0,
    blooms_may              BOOLEAN DEFAULT 0,
    blooms_june             BOOLEAN DEFAULT 0,
    blooms_july             BOOLEAN DEFAULT 0,
    blooms_august           BOOLEAN DEFAULT 0,
    blooms_september        BOOLEAN DEFAULT 0,
    blooms_october          BOOLEAN DEFAULT 0,
    blooms_november         BOOLEAN DEFAULT 0,
    blooms_december         BOOLEAN DEFAULT 0,
    
    -- ZUSATZFELD ✅
    notes                   TEXT,                   -- Freifeld für Besonderheiten
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

-- Indizes
CREATE INDEX idx_flower_plant_id ON plant_flower(plant_id);
CREATE INDEX idx_flower_color ON plant_flower(color);
CREATE INDEX idx_flower_fragrance ON plant_flower(fragrance);
CREATE INDEX idx_flower_cycle ON plant_flower(blooming_cycle);
CREATE INDEX idx_flower_blooms_may ON plant_flower(blooms_may);
CREATE INDEX idx_flower_blooms_june ON plant_flower(blooms_june);
CREATE INDEX idx_flower_blooms_july ON plant_flower(blooms_july);
```

**Entscheidungen:**

**1. BLÜTENFARBE** ⚠️ **DISKUSSION CHALLENGE**

**Problem:** Freitext = Chaos ("rot", "Rot", "dunkelrot", "tiefdunkelrot")

**Aktueller Vorschlag: Standard-Farben**
```sql
INSERT INTO field_options (field_name, option_value, sort_order, is_custom) VALUES
('flower_color', 'weiß', 1, 0),
('flower_color', 'cremeweiß', 2, 0),
('flower_color', 'gelb', 3, 0),
('flower_color', 'orange', 4, 0),
('flower_color', 'apricot', 5, 0),
('flower_color', 'lachsrosa', 6, 0),
('flower_color', 'rosa', 7, 0),
('flower_color', 'pink', 8, 0),
('flower_color', 'rot', 9, 0),
('flower_color', 'dunkelrot', 10, 0),
('flower_color', 'violett', 11, 0),
('flower_color', 'lila', 12, 0),
('flower_color', 'blau', 13, 0),
('flower_color', 'mehrfarbig', 14, 0),
('flower_color', 'gestreift', 15, 0),
('flower_color', 'geflammt', 16, 0);
```

**Offene Fragen für Challenge:**
- Reichen diese 16 Farben?
- Brauchen wir Kombinationen ("rot-weiß gestreift")?
- Oder zwei Felder: primary_color + color_pattern?
- Oder Mehrfachauswahl?

**2. DUFT** ✅ **FINALISIERT**
```sql
INSERT INTO field_options (field_name, option_value, sort_order, is_custom) VALUES
('flower_fragrance', 'nicht duftend', 1, 0),
('flower_fragrance', 'leicht duftend', 2, 0),
('flower_fragrance', 'duftend', 3, 0),
('flower_fragrance', 'stark duftend', 4, 0),
('flower_fragrance', 'sehr stark duftend', 5, 0);
```

**3. BLÜTENGRÖSSE** ✅ **FINALISIERT**
```sql
INSERT INTO field_options (field_name, option_value, sort_order, is_custom) VALUES
('flower_size', 'sehr klein', 1, 0),        -- <3cm
('flower_size', 'klein', 2, 0),             -- 3-5cm
('flower_size', 'mittelgross', 3, 0),       -- 5-8cm
('flower_size', 'gross', 4, 0),             -- 8-12cm
('flower_size', 'sehr gross', 5, 0);        -- >12cm
```

**Vorteil:** Relativ statt absolut (sehr gross bei Veilchen ≠ sehr gross bei Rose)

**4. BLÜTENFÜLLUNG** ✅ **FINALISIERT**
```sql
INSERT INTO field_options (field_name, option_value, sort_order, is_custom) VALUES
('flower_fullness', 'einfach', 1, 0),           -- 5-8 Blütenblätter
('flower_fullness', 'halbgefüllt', 2, 0),       -- 10-20 Blütenblätter
('flower_fullness', 'gefüllt', 3, 0),           -- 20-40 Blütenblätter
('flower_fullness', 'sehr gefüllt', 4, 0);      -- >40 Blütenblätter
```

**5. BLÜTENFORM** ⚠️ **DISKUSSION CHALLENGE**

**Aktueller Vorschlag (Rosen + allgemein gemischt):**
```sql
INSERT INTO field_options (field_name, option_value, sort_order, is_custom) VALUES
-- Rosen-spezifisch
('flower_form', 'schalenförmig', 1, 0),
('flower_form', 'rosettenförmig', 2, 0),
('flower_form', 'pompon', 3, 0),
('flower_form', 'kelchförmig', 4, 0),
('flower_form', 'ballförmig', 5, 0),
-- Allgemein
('flower_form', 'trichterförmig', 6, 0),
('flower_form', 'glockenförmig', 7, 0),
('flower_form', 'sternförmig', 8, 0),
('flower_form', 'lippenförmig', 9, 0),
('flower_form', 'radförmig', 10, 0),
('flower_form', 'doldenförmig', 11, 0),
('flower_form', 'rispenförmig', 12, 0);
```

**Offene Fragen für Challenge:**
- Eine gemeinsame Liste (wie oben)?
- Oder zwei Felder: rose_form + general_form?
- Oder dynamisch erweiterbar aus field_options?

**6. HALTBARKEIT (Vase)** ✅ **FINALISIERT**
```sql
INSERT INTO field_options (field_name, option_value, sort_order, is_custom) VALUES
('flower_vase_life', 'kurz', 1, 0),         -- 1-3 Tage
('flower_vase_life', 'mittel', 2, 0),       -- 4-7 Tage
('flower_vase_life', 'lang', 3, 0);         -- >7 Tage
```

**7. BLÜHZYKLUS** ✅ **FINALISIERT**
```sql
INSERT INTO field_options (field_name, option_value, sort_order, is_custom) VALUES
('flower_blooming_cycle', 'einmalblühend', 1, 0),      -- Juni, dann fertig
('flower_blooming_cycle', 'nachblühend', 2, 0),        -- Hauptblüte + 1-2 Nachblüten
('flower_blooming_cycle', 'öfterblühend', 3, 0),       -- Mehrere Blütenwellen
('flower_blooming_cycle', 'dauerblühend', 4, 0);       -- Juni-Oktober durchgehend
```

**8. BLÜTEZEIT (12 Monate Mehrfachauswahl)** ✅ **FINALISIERT**

**Design-Entscheidung:** 12 BOOLEAN-Felder statt von/bis

**Warum?**
- ✅ Flexible Blütewellen (Mai-Juni + August-September)
- ✅ Nachblüte abbildbar (Juni-Juli + September)
- ✅ Winterblüher (Dezember-März)
- ✅ Einfachste Queries: WHERE blooms_june = 1
- ✅ UI = 1:1 DB-Mapping (12 Checkboxen)
- ✅ Monate sind stabil (seit Julius Caesar 46 v. Chr. keine neuen!) 😄

**Beispiel-Queries:**
```sql
-- "Zeige alle Pflanzen die im Juni blühen"
SELECT p.botanical_name
FROM plants p
JOIN plant_flower f ON p.id = f.plant_id
WHERE f.blooms_june = 1;

-- "Zeige Winterblüher"
WHERE f.blooms_december = 1 
   OR f.blooms_january = 1 
   OR f.blooms_february = 1;

-- "Zeige Dauerblüher (mindestens 4 Monate)"
WHERE (f.blooms_january + f.blooms_february + ... + f.blooms_december) >= 4;
```

**9. NOTES (Zusatzfeld)** ✅ **FINALISIERT**

Freifeld für:
- Farbverläufe ("verblühend von rosa zu weiß")
- Besonderheiten ("nachtblühend", "winterblühend")
- Alles was in Standard-Felder nicht passt

**Beispiel-Daten:**

```sql
-- Rose 'Bonica 82' (dauerblühend Juni-Oktober)
INSERT INTO plant_flower VALUES (
    NULL, 1,
    'rosa', 'leicht duftend', 'mittelgross', 'halbgefüllt', 'schalenförmig',
    'mittel', 'dauerblühend',
    0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 0, 0,  -- Juni-Oktober
    'Verblüht zu hellrosa'
);

-- Lavendel (Juni-August)
INSERT INTO plant_flower VALUES (
    NULL, 2,
    'violett', 'sehr stark duftend', 'sehr klein', 'einfach', 'lippenförmig',
    'lang', 'dauerblühend',
    0, 0, 0, 0, 0, 1, 1, 1, 0, 0, 0, 0,  -- Juni-August
    NULL
);

-- Rhododendron (nur Mai)
INSERT INTO plant_flower VALUES (
    NULL, 3,
    'rot', 'nicht duftend', 'gross', 'einfach', 'trichterförmig',
    'kurz', 'einmalblühend',
    0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0,  -- Nur Mai
    NULL
);

-- Christrose (Winterblüher Dez-März)
INSERT INTO plant_flower VALUES (
    NULL, 6,
    'weiß', 'nicht duftend', 'mittelgross', 'einfach', 'schalenförmig',
    'lang', 'einmalblühend',
    1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 1,  -- Dez+Jan+Feb+März
    'Winterblüher, auch unter Schnee'
);

-- Rose mit Nachblüte (Mai-Juni + September)
INSERT INTO plant_flower VALUES (
    NULL, 8,
    'rosa', 'stark duftend', 'gross', 'sehr gefüllt', 'rosettenförmig',
    'lang', 'nachblühend',
    0, 0, 0, 0, 1, 1, 0, 0, 1, 0, 0, 0,  -- Mai+Juni+September
    'Hauptblüte Mai-Juni, Nachblüte September'
);
```

---

### ✅ TABELLE 8: PLANT_FRUIT (Früchte/Erntegut)

**Status:** Finalisiert - 03. Februar 2026

```sql
CREATE TABLE plant_fruit (
    id                      INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id                INTEGER NOT NULL,
    
    color                   TEXT,
    size                    TEXT,                   -- "sehr klein", "klein", "mittelgross", "gross", "sehr gross"
    taste                   TEXT,                   -- "süss", "süss-säuerlich", "säuerlich", "herb", "bitter", "mild", "würzig", "scharf", "neutral"
    juiciness               TEXT,                   -- "trocken", "wenig saftig", "saftig", "sehr saftig", "extrem saftig"
    texture                 TEXT,                   -- "fest", "knackig", "weich", "cremig", "mehlig", "faserig", "zart", "knusprig"
    
    harvest_january         BOOLEAN DEFAULT 0,
    harvest_february        BOOLEAN DEFAULT 0,
    harvest_march           BOOLEAN DEFAULT 0,
    harvest_april           BOOLEAN DEFAULT 0,
    harvest_may             BOOLEAN DEFAULT 0,
    harvest_june            BOOLEAN DEFAULT 0,
    harvest_july            BOOLEAN DEFAULT 0,
    harvest_august          BOOLEAN DEFAULT 0,
    harvest_september       BOOLEAN DEFAULT 0,
    harvest_october         BOOLEAN DEFAULT 0,
    harvest_november        BOOLEAN DEFAULT 0,
    harvest_december        BOOLEAN DEFAULT 0,
    
    ripening_days           INTEGER,                -- 30, 45, 60, 90, 120 (nur bei manchen Kulturen!)
    notes                   TEXT,
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);
```

### ✅ TABELLE 9: PLANT_GROWTH (Wuchs)

**Status:** Finalisiert - 03. Februar 2026

```sql
CREATE TABLE plant_growth (
    id                      INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id                INTEGER NOT NULL,
    
    cycle                   TEXT,                   -- "einjährig", "zweijährig", "mehrjährig"
    height_cm               INTEGER,                -- Endhöhe: 10, 15, 20, 25, 30, 40, 50, 60, 80, 100, 125, 150, 175, 200, 225, 250, 275, 300, 350, 400, 450, 500, 600, 800, 1000, 1200, 1500, 2000, 2500, 3000
    width_cm                INTEGER,                -- Endbreite: gleiche Zahlenreihe
    form_primary            TEXT,                   -- "aufrecht", "bogig", "buschig", "kletternd", "kriechend", "schlank", "pyramidal", "kugelförmig", "säulenförmig", "überhängend", "horstig", "rosettenbildend", "ausläuferbildend", "polsterbildend", "teppichartig", "rankend", "schlingend"
    form_secondary          TEXT,                   -- "kompakt", "kann klettern"
    vigor                   TEXT,                   -- "schwach", "mittel", "stark", "sehr stark"
    notes                   TEXT,
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);
```

**ZAHLENREIHE (universell für height_cm, width_cm, Liefergrössen):**
```
10, 15, 20, 25, 30, 40, 50, 60, 80, 100,
125, 150, 175, 200, 225, 250, 275, 300,
350, 400, 450, 500, 600, 800,
1000, 1200, 1500, 2000, 2500, 3000
```

**WUCHSFORM (Option C: Zwei Felder):**

form_primary (Hauptform):
- Rosen: aufrecht, bogig, buschig, kletternd, kriechend, schlank
- Bäume: pyramidal, kugelförmig, säulenförmig, überhängend
- Stauden: horstig, rosettenbildend, ausläuferbildend, polsterbildend, teppichartig
- Kletterpflanzen: rankend, schlingend, kletternd

form_secondary (Zusatz):
- kompakt
- kann klettern

---

### ✅ TABELLE 10: PLANT_LEAF (Blatt-Merkmale)

**Status:** Finalisiert - 03. Februar 2026, 22:11 Uhr

**Kern-Konzept:** Minimal Viable Fields mit Raum für Wachstum

**Felder:**
```sql
CREATE TABLE plant_leaf (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id            INTEGER NOT NULL,
    
    -- FARBE (Freitext mit Standard-Vorschlägen)
    color               TEXT,                   -- "grün", "dunkelgrün", "panaschiert", etc.
    
    -- FORM (Freitext mit Standard-Vorschlägen)
    form                TEXT,                   -- "rund", "länglich", "gefiedert", etc.
    
    -- AROMA (Freitext mit Standard-Vorschlägen - wichtig für Kräuter!)
    aroma               TEXT,                   -- "minzig", "zitronig", "würzig", etc.
    
    -- GRÖSSE ✅
    size                TEXT,                   -- "klein", "mittel", "gross"
    
    -- HERBSTFÄRBUNG (Ja/Nein + welche Farbe) ✅
    has_autumn_color    BOOLEAN DEFAULT 0,      -- TRUE/FALSE
    autumn_color        TEXT,                   -- "gelb", "orange", "rot", "bronze"
    
    -- IMMERGRÜN ✅
    evergreen           BOOLEAN DEFAULT 0,      -- TRUE/FALSE
    
    -- ZUSATZ (für Glanz, Textur, Besonderheiten)
    notes               TEXT,                   -- "glänzend", "matt", "ledrig", "samtig", "behaart"
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

-- Indizes
CREATE INDEX idx_leaf_plant_id ON plant_leaf(plant_id);
CREATE INDEX idx_leaf_evergreen ON plant_leaf(evergreen);
CREATE INDEX idx_leaf_autumn_color ON plant_leaf(has_autumn_color);
```

**field_options:**
- Farbe: grün, hellgrün, dunkelgrün, blaugrün, gelbgrün, rotgrün, bronze, silbrig, panaschiert, mehrfarbig
- Form: rund, oval, länglich, herzförmig, lanzettlich, gefiedert, gelappt, gezähnt, nadelförmig
- Aroma: minzig, zitronig, würzig, scharf, mild, bitter, süsslich, aromatisch, intensiv
- Größe: klein (<5cm), mittel (5-15cm), gross (>15cm)
- Herbstfarbe: gelb, orange, rot, bronze, purpur, mehrfarbig

**Entscheidungen:**
- ✅ Minimal Viable Fields: color, form, aroma, size
- ✅ Herbstfärbung: Zwei Felder (has_autumn_color BOOLEAN + autumn_color TEXT)
- ✅ Immergrün: BOOLEAN (wichtig für Hecken!)
- ✅ Glänzend/Matt/Ledrig: In notes-Feld (Freitext)

---

### ✅ TABELLE 11: PLANT_ROOT (Wurzel-Merkmale)

**Status:** Finalisiert - 03. Februar 2026, 22:13 Uhr

**Kern-Konzept:** Minimal Viable Fields für Wurzel-Charakteristik

**Felder:**
```sql
CREATE TABLE plant_root (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id            INTEGER NOT NULL,
    
    -- WURZELTIEFE ✅
    rooting_depth       TEXT,                   -- "flachwurzelnd", "mitteltiefwurzelnd", "tiefwurzelnd"
    
    -- WURZELFORM ✅
    root_form           TEXT,                   -- "Pfahlwurzel", "Herzwurzel", "Flachwurzel", "Büschelwurzel"
    
    -- VERANKERUNG (Windfestigkeit) ✅
    anchorage           TEXT,                   -- "schwach", "mittel", "gut", "sehr gut"
    
    -- GESCHMACK (nur bei Nutzpflanzen!) ✅
    taste               TEXT,                   -- "süss", "würzig", "scharf", "mild", "bitter", "erdig", "nussig"
    
    -- ZUSATZ
    notes               TEXT,                   -- "Ausläufer bildend", "nicht verpflanzen", etc.
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

-- Indizes
CREATE INDEX idx_root_plant_id ON plant_root(plant_id);
CREATE INDEX idx_root_depth ON plant_root(rooting_depth);
CREATE INDEX idx_root_form ON plant_root(root_form);
```

**field_options:**
- Wurzeltiefe: flachwurzelnd (0-50cm), mitteltiefwurzelnd (50-150cm), tiefwurzelnd (>150cm)
- Wurzelform: Pfahlwurzel, Herzwurzel, Flachwurzel, Büschelwurzel
- Verankerung: schwach, mittel, gut, sehr gut
- Geschmack: süss, würzig, scharf, mild, bitter, erdig, nussig

**Entscheidungen:**
- ✅ Wurzeltiefe: 3 Stufen (wichtig für Trockenheitsresistenz)
- ✅ Wurzelform: 4 botanische Typen
- ✅ Verankerung: Windfestigkeit (wichtig für Bäume!)
- ✅ Geschmack: Nur bei Wurzelgemüse
- ⏳ Für Challenge: Wurzelfarbe? Ausläuferbildung als eigenes Feld?

---

### ✅ TABELLE 12: PLANT_NURSERY (Kultur-Daten - UNIVERSELL)

**Status:** Finalisiert - 03. Februar 2026, 22:18 Uhr

**WICHTIG:** Diese Tabelle enthält **universelle biologische Kulturwerte**, die für ALLE Gärtnereien gelten!

**Kern-Konzept:** Pflanzen-spezifische Produktionsdaten (unabhängig von Gärtnerei)

**Felder:**
```sql
CREATE TABLE plant_nursery (
    id                          INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id                    INTEGER NOT NULL,
    
    -- VERMEHRUNG ✅
    propagation_method          TEXT,           -- "Samen", "Steckling", "Teilung", "Veredelung", "Absenker"
    
    -- KEIMUNG (nur bei Samen-Vermehrung!) ✅
    germination_days            INTEGER,        -- 7, 14, 21 Tage
    germination_temp_min_c      INTEGER,        -- 15°C
    germination_temp_max_c      INTEGER,        -- 25°C
    germination_light           TEXT,           -- "Lichtkeimer", "Dunkelkeimer", "indifferent"
    
    -- BEWURZELUNG (bei Stecklingen) ✅
    rooting_days                INTEGER,        -- 14, 21, 28 Tage
    
    -- ENTWICKLUNGSZEITEN (universell - aber variabel je nach Bedingungen!) ✅
    development_days_to_yp      INTEGER,        -- Samen/Steckling → Jungpflanze
    development_days_yp_to_fp   INTEGER,        -- Jungpflanze → Fertigpflanze
    
    -- PLATZBEDARF (universell - Endabstand!) ✅
    plants_per_sqm              REAL,           -- 16, 25, 36, 64, 121
    
    -- ZUSATZ
    notes                       TEXT,           -- "Pikieren nach 2-3 Wochen"
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

-- Indizes
CREATE INDEX idx_nursery_plant_id ON plant_nursery(plant_id);
CREATE INDEX idx_nursery_propagation ON plant_nursery(propagation_method);
```

**field_options:**
```sql
-- Vermehrungsart
INSERT INTO field_options (field_name, option_value, sort_order, is_custom) VALUES
('propagation_method', 'Samen', 1, 0),
('propagation_method', 'Steckling', 2, 0),
('propagation_method', 'Teilung', 3, 0),
('propagation_method', 'Veredelung', 4, 0),
('propagation_method', 'Absenker', 5, 0),
('propagation_method', 'Wurzelschnittlinge', 6, 0),
('propagation_method', 'Bulben/Zwiebeln', 7, 0);

-- Keimart Licht
INSERT INTO field_options (field_name, option_value, sort_order, is_custom) VALUES
('germination_light', 'Lichtkeimer', 1, 0),
('germination_light', 'Dunkelkeimer', 2, 0),
('germination_light', 'indifferent', 3, 0);
```

**Beispiel-Daten:**
```sql
-- Tomate 'Black Cherry'
INSERT INTO plant_nursery VALUES (
    NULL, 1,
    'Samen',
    7, 20, 28, 'Lichtkeimer',
    NULL,       -- Keine Bewurzelung (ist ja Samen!)
    42,         -- 6 Wochen bis JP
    28,         -- 4 Wochen JP bis FP
    16,         -- 16 Pflanzen pro qm (Endabstand!)
    'Pikieren nach 2-3 Wochen'
);

-- Rose (Steckling)
INSERT INTO plant_nursery VALUES (
    NULL, 2,
    'Steckling',
    NULL, NULL, NULL, NULL,     -- Keine Keimung!
    21,                          -- 3 Wochen Bewurzelung
    180,                         -- 6 Monate bis JP
    365,                         -- 1 Jahr JP bis FP
    4,                           -- 4 Pflanzen pro qm
    'Stecklinge im Spätsommer schneiden'
);
```

**Entscheidungen:**
- ✅ Universelle Kulturwerte (biologische Fakten)
- ✅ Germination: Tage + Temp-Range + Lichtbedarf
- ✅ Development: Zwei Phasen (bis JP, JP bis FP)
- ✅ Plants per sqm: Endabstand (nicht Topf-Dichte!)
- ❌ KEINE Topfgrössen hier (das ist gärtnerei-spezifisch!)
- ❌ KEINE Bestandszahlen hier (das ist plant_inventory!)

---

### ✅ TABELLE 13: POT_SIZES (Topfgrössen-Definition - PRO GÄRTNEREI)

**Status:** Finalisiert - 03. Februar 2026, 22:18 Uhr

**WICHTIG:** Diese Tabelle definiert, welche Topfgrössen eine Gärtnerei verwendet und deren technische Daten!

**Kern-Konzept:** Gärtnerei-Settings für Topfgrössen + automatische Berechnungen

**Felder:**
```sql
CREATE TABLE pot_sizes (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    nursery_id          INTEGER,                -- NULL = Standard-Werte für alle
    
    -- TOPF-CODE ✅
    pot_code            TEXT NOT NULL,          -- "T9", "T12", "C2", "C10"
    
    -- VOLUMEN ✅
    volume_liters       REAL,                   -- 0.7, 1.0, 2.0, 10.0
    
    -- PLATZBEDARF ✅
    plants_per_sqm      INTEGER,                -- 121, 64, 25, 16
    
    -- GEWICHT (für Substrat-Berechnung!) ✅
    weight_kg_per_1000  REAL,                   -- 420 kg (für 1000 T9)
    
    -- SORTIERUNG
    sort_order          INTEGER DEFAULT 0,
    
    UNIQUE(nursery_id, pot_code)
);

-- Indizes
CREATE INDEX idx_pot_sizes_nursery ON pot_sizes(nursery_id);
CREATE INDEX idx_pot_sizes_code ON pot_sizes(pot_code);
```

**Standard-Werte (für alle Gärtnereien verfügbar):**
```sql
-- Standard Töpfe (nursery_id = NULL)
INSERT INTO pot_sizes (nursery_id, pot_code, volume_liters, plants_per_sqm, weight_kg_per_1000, sort_order) VALUES
-- T-Töpfe (rund, Thermoplatten)
(NULL, 'T9', 0.7, 121, 420, 1),
(NULL, 'T11', 0.9, 81, 540, 2),
(NULL, 'T12', 1.0, 64, 640, 3),
(NULL, 'T13', 1.2, 49, 720, 4),
(NULL, 'T14', 1.5, 49, 900, 5),
-- C-Töpfe (Container, rund)
(NULL, 'C1', 1.0, 64, 640, 6),
(NULL, 'C2', 2.0, 36, 1200, 7),
(NULL, 'C3', 3.0, 25, 1800, 8),
(NULL, 'C5', 5.0, 16, 3000, 9),
(NULL, 'C7.5', 7.5, 12, 4500, 10),
(NULL, 'C10', 10.0, 9, 6000, 11),
(NULL, 'C15', 15.0, 6, 9000, 12),
(NULL, 'C20', 20.0, 4, 12000, 13);
```

**oMioBio-spezifische Überschreibungen:**
```sql
-- oMioBio (nursery_id = 1) nutzt leicht andere Töpfe
INSERT INTO pot_sizes (nursery_id, pot_code, volume_liters, plants_per_sqm, weight_kg_per_1000, sort_order) VALUES
(1, 'T9', 0.75, 121, 450, 1),       -- Etwas grössere T9
(1, 'T12', 1.1, 64, 700, 2),
(1, 'T14', 1.6, 49, 960, 3);
```

**Automatische Berechnungen (später in Challenge!):**
```sql
-- Query: "Wieviel Substrat für 1000 T9 Tomaten bei oMioBio?"
SELECT 
    ps.pot_code,
    ps.volume_liters * 1000 AS total_liters,
    ps.weight_kg_per_1000 AS total_weight_kg,
    1000 / ps.plants_per_sqm AS required_sqm
FROM pot_sizes ps
WHERE ps.pot_code = 'T9' 
  AND (ps.nursery_id = 1 OR ps.nursery_id IS NULL)
ORDER BY ps.nursery_id DESC NULLS LAST
LIMIT 1;

-- Ergebnis für oMioBio: 750 Liter, 450 kg, 8.26 qm
```

**Entscheidungen:**
- ✅ Pro Gärtnerei konfigurierbar (nursery_id)
- ✅ NULL = Standard-Werte für alle
- ✅ Überschreiben möglich (oMioBio hat eigene T9-Grösse!)
- ✅ Substrat-Gewicht für Bestellungen
- ✅ Platzbedarf für Flächenplanung
- ⏳ Für Challenge: Automatische Berechnungen in UI!

---

### ✅ TABELLE 14: PLANT_INVENTORY (Bestand - PRO GÄRTNEREI)

**Status:** Finalisiert - 03. Februar 2026, 22:18 Uhr

**WICHTIG:** Diese Tabelle ist der **aktuelle Bestand** einer Gärtnerei. Wird häufig aktualisiert!

**Kern-Konzept:** Was hat DIESE Gärtnerei JETZT auf Lager?

**Felder:**
```sql
CREATE TABLE plant_inventory (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id            INTEGER NOT NULL,
    nursery_id          INTEGER NOT NULL,       -- Welche Gärtnerei?
    
    -- PRODUKT-SPEZIFIKATION ✅
    pot_size            TEXT,                   -- "T9", "C2", "Wurzelnackt"
    
    -- MENGE ✅
    quantity            INTEGER,                -- 500 Stück
    
    -- STATUS ✅
    status              TEXT,                   -- "verkaufsfertig", "in Kultur", "bestellt", "ausverkauft"
    
    -- LIEFERTERMIN ✅
    delivery_week       INTEGER,                -- KW 15 (2026)
    delivery_year       INTEGER,                -- 2026
    
    -- PREIS ✅
    price_chf           REAL,                   -- 3.50
    
    -- LOCATION (optional - wo steht es?) ✅
    location            TEXT,                   -- "Gewächshaus 2", "Freiland Nord", "Tunnel 3"
    
    -- TRACKING
    last_updated        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE,
    FOREIGN KEY (nursery_id) REFERENCES nurseries(id) ON DELETE CASCADE
);

-- Indizes
CREATE INDEX idx_inventory_plant_id ON plant_inventory(plant_id);
CREATE INDEX idx_inventory_nursery_id ON plant_inventory(nursery_id);
CREATE INDEX idx_inventory_status ON plant_inventory(status);
CREATE INDEX idx_inventory_delivery ON plant_inventory(delivery_week, delivery_year);
```

**field_options:**
```sql
-- Status-Werte
INSERT INTO field_options (field_name, option_value, sort_order, is_custom) VALUES
('inventory_status', 'verkaufsfertig', 1, 0),
('inventory_status', 'in Kultur', 2, 0),
('inventory_status', 'bestellt', 3, 0),
('inventory_status', 'ausverkauft', 4, 0),
('inventory_status', 'reserviert', 5, 0);
```

**Beispiel-Daten:**
```sql
-- oMioBio (nursery_id=1) hat:

-- Tomate 'Black Cherry' - verkaufsfertig
INSERT INTO plant_inventory VALUES (
    NULL, 1, 1,
    'T9', 500, 'verkaufsfertig', 15, 2026, 3.50,
    'Gewächshaus 2',
    CURRENT_TIMESTAMP
);

-- Tomate 'Black Cherry' - in Kultur (kommt KW 18)
INSERT INTO plant_inventory VALUES (
    NULL, 1, 1,
    'T9', 1000, 'in Kultur', 18, 2026, 3.50,
    'Gewächshaus 3',
    CURRENT_TIMESTAMP
);

-- Rose 'Bonica 82' - wurzelnackt, ausverkauft
INSERT INTO plant_inventory VALUES (
    NULL, 2, 1,
    'Wurzelnackt', 0, 'ausverkauft', NULL, NULL, 12.00,
    NULL,
    CURRENT_TIMESTAMP
);
```

**Entscheidungen:**
- ✅ Pro Gärtnerei separate Bestände
- ✅ Mehrere Einträge pro Pflanze möglich (verschiedene Grössen/Status)
- ✅ Liefertermin in Kalenderwochen (KW + Jahr)
- ✅ Location-Tracking optional
- ✅ Timestamp für Aktualisierung
- ⏳ Für Challenge: Bestandsverlauf-Tabelle (History)?

---

## NURSERY-SYSTEM ZUSAMMENFASSUNG

**3-Tabellen-System:**

1. **PLANT_NURSERY** (Tabelle 12)
   - Universelle Kulturwerte
   - Gilt für ALLE Gärtnereien
   - Biologische Fakten
   - Beispiel: "Tomate braucht 7 Tage Keimung bei 20-28°C"

2. **POT_SIZES** (Tabelle 13)
   - Gärtnerei-Settings
   - Pro Gärtnerei konfigurierbar
   - Technische Daten (Volumen, Gewicht, Platzbedarf)
   - Beispiel: "oMioBio nutzt T9 mit 0.75L statt Standard 0.7L"

3. **PLANT_INVENTORY** (Tabelle 14)
   - Aktueller Bestand
   - Pro Gärtnerei + Pflanze + Topfgrösse
   - Häufig aktualisiert
   - Beispiel: "500 Stück Tomate in T9, verkaufsfertig, KW 15"

**Workflow:**
```
PLANT_NURSERY (was braucht die Pflanze?)
    ↓
POT_SIZES (welche Töpfe nutzt meine Gärtnerei?)
    ↓
PLANT_INVENTORY (was habe ich gerade?)
```

---

### ✅ TABELLE 15: PLANT_USAGE (Verwendung)

**Status:** Finalisiert - 03. Februar 2026, 22:33 Uhr

**Kern-Konzept:** Minimal Viable Fields für Verwendung von Nutzpflanzen

**Felder:**
```sql
CREATE TABLE plant_usage (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id            INTEGER NOT NULL,
    
    -- PFLANZENTEILE ✅
    plant_parts         TEXT,                   -- "Blätter", "Blüten", "Früchte", "Wurzeln", "Samen", "ganze Pflanze"
    
    -- ZWECK/VERWENDUNG ✅
    purpose             TEXT,                   -- "Küche", "Medizin", "Zierde", "Tee", "Gewürz", "Schnittblume"
    
    -- VERARBEITUNG ✅
    processing          TEXT,                   -- "frisch", "getrocknet", "gekocht", "roh", "eingelegt", "fermentiert"
    
    -- LAGERUNG ✅
    storage             TEXT,                   -- "kühl", "dunkel", "trocken", "einfrieren", "einmachen"
    
    -- ERNTEZEIT (12 Monate Mehrfachauswahl - wie bei Blütezeit!) ✅
    harvest_january     BOOLEAN DEFAULT 0,
    harvest_february    BOOLEAN DEFAULT 0,
    harvest_march       BOOLEAN DEFAULT 0,
    harvest_april       BOOLEAN DEFAULT 0,
    harvest_may         BOOLEAN DEFAULT 0,
    harvest_june        BOOLEAN DEFAULT 0,
    harvest_july        BOOLEAN DEFAULT 0,
    harvest_august      BOOLEAN DEFAULT 0,
    harvest_september   BOOLEAN DEFAULT 0,
    harvest_october     BOOLEAN DEFAULT 0,
    harvest_november    BOOLEAN DEFAULT 0,
    harvest_december    BOOLEAN DEFAULT 0,
    
    -- ZUSATZ
    notes               TEXT,
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

-- Indizes
CREATE INDEX idx_usage_plant_id ON plant_usage(plant_id);
CREATE INDEX idx_usage_harvest_july ON plant_usage(harvest_july);
CREATE INDEX idx_usage_harvest_august ON plant_usage(harvest_august);
CREATE INDEX idx_usage_harvest_september ON plant_usage(harvest_september);
```

**field_options:**
```sql
-- Pflanzenteile
INSERT INTO field_options (field_name, option_value, sort_order, is_custom) VALUES
('usage_plant_parts', 'Blätter', 1, 0),
('usage_plant_parts', 'Blüten', 2, 0),
('usage_plant_parts', 'Früchte', 3, 0),
('usage_plant_parts', 'Wurzeln', 4, 0),
('usage_plant_parts', 'Samen', 5, 0),
('usage_plant_parts', 'Knospen', 6, 0),
('usage_plant_parts', 'Triebe', 7, 0),
('usage_plant_parts', 'Rinde', 8, 0),
('usage_plant_parts', 'ganze Pflanze', 9, 0);

-- Zweck/Verwendung
INSERT INTO field_options (field_name, option_value, sort_order, is_custom) VALUES
('usage_purpose', 'Küche', 1, 0),
('usage_purpose', 'Gewürz', 2, 0),
('usage_purpose', 'Salat', 3, 0),
('usage_purpose', 'Tee', 4, 0),
('usage_purpose', 'Medizin', 5, 0),
('usage_purpose', 'Kosmetik', 6, 0),
('usage_purpose', 'Zierde', 7, 0),
('usage_purpose', 'Schnittblume', 8, 0),
('usage_purpose', 'Duft', 9, 0),
('usage_purpose', 'Bienenweide', 10, 0),
('usage_purpose', 'Gründüngung', 11, 0);

-- Verarbeitung
INSERT INTO field_options (field_name, option_value, sort_order, is_custom) VALUES
('usage_processing', 'frisch', 1, 0),
('usage_processing', 'roh', 2, 0),
('usage_processing', 'gekocht', 3, 0),
('usage_processing', 'gebraten', 4, 0),
('usage_processing', 'gebacken', 5, 0),
('usage_processing', 'getrocknet', 6, 0),
('usage_processing', 'eingelegt', 7, 0),
('usage_processing', 'fermentiert', 8, 0),
('usage_processing', 'entsäftet', 9, 0),
('usage_processing', 'eingefroren', 10, 0);

-- Lagerung
INSERT INTO field_options (field_name, option_value, sort_order, is_custom) VALUES
('usage_storage', 'kühl', 1, 0),
('usage_storage', 'dunkel', 2, 0),
('usage_storage', 'trocken', 3, 0),
('usage_storage', 'luftig', 4, 0),
('usage_storage', 'Kühlschrank', 5, 0),
('usage_storage', 'einfrieren', 6, 0),
('usage_storage', 'einmachen', 7, 0),
('usage_storage', 'einlegen', 8, 0),
('usage_storage', 'trocknen', 9, 0),
('usage_storage', 'nicht lagerbar', 10, 0);
```

**Beispiel-Daten:**
```sql
-- Basilikum (Kräuter)
INSERT INTO plant_usage VALUES (
    NULL, 1,
    'Blätter',
    'Küche, Gewürz',
    'frisch, getrocknet',
    'dunkel, trocken, einfrieren',
    0, 0, 0, 0, 0, 1, 1, 1, 1, 0, 0, 0,  -- Juni-September
    'Blätter vor Blüte ernten für bestes Aroma'
);

-- Tomate (Gemüse)
INSERT INTO plant_usage VALUES (
    NULL, 2,
    'Früchte',
    'Küche, Salat',
    'frisch, gekocht, eingelegt, entsäftet',
    'kühl, dunkel, einmachen',
    0, 0, 0, 0, 0, 0, 1, 1, 1, 0, 0, 0,  -- Juli-September
    NULL
);

-- Lavendel (Multitalent!)
INSERT INTO plant_usage VALUES (
    NULL, 3,
    'Blüten, Blätter',
    'Küche, Tee, Medizin, Kosmetik, Duft, Zierde, Bienenweide',
    'frisch, getrocknet',
    'dunkel, trocken, luftig',
    0, 0, 0, 0, 0, 1, 1, 1, 0, 0, 0, 0,  -- Juni-August
    'Blüten morgens nach Tautrocknung ernten'
);

-- Ringelblume (Heilpflanze)
INSERT INTO plant_usage VALUES (
    NULL, 4,
    'Blüten',
    'Medizin, Kosmetik, Tee, Zierde',
    'frisch, getrocknet',
    'trocken, dunkel',
    0, 0, 0, 0, 0, 1, 1, 1, 1, 0, 0, 0,  -- Juni-September
    'Regelmässiges Ernten fördert Nachblüte'
);

-- Rose (Schnittblume + Hagebutten)
INSERT INTO plant_usage VALUES (
    NULL, 5,
    'Blüten, Früchte',
    'Schnittblume, Zierde, Tee, Küche',
    'frisch, getrocknet, eingemacht',
    'kühl, dunkel',
    0, 0, 0, 0, 1, 1, 0, 0, 0, 1, 0, 0,  -- Mai-Juni (Blüten), Oktober (Hagebutten)
    'Hagebutten nach erstem Frost ernten'
);

-- Kartoffel (Wurzelgemüse)
INSERT INTO plant_usage VALUES (
    NULL, 6,
    'Wurzeln',
    'Küche',
    'gekocht, gebraten, gebacken',
    'kühl, dunkel, trocken',
    0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 0, 0,  -- August-Oktober
    'Vor Frost ernten, Laub vorher entfernen'
);
```

**Entscheidungen:**
- ✅ **plant_parts:** TEXT (Freitext mit Standard-Vorschlägen)
- ✅ **purpose:** TEXT (komma-getrennt für Mehrfachverwendung - "Küche, Medizin, Tee")
- ✅ **processing:** TEXT (komma-getrennt - "frisch, getrocknet")
- ✅ **storage:** TEXT (komma-getrennt - "kühl, dunkel, trocken")
- ✅ **Erntezeit:** 12 BOOLEAN-Felder (konsistent mit Blütezeit!)
- ✅ **Minimal Viable Fields** - einfach, erweiterbar
- ⏳ Für Challenge: Separate Tabelle für Mehrfachverwendung? Erntemethoden?

---

### ✅ TABELLE 16: FIELD_OPTIONS (Zentrale Dropdown-Verwaltung)

**Status:** Finalisiert - 03. Februar 2026, 22:36 Uhr

**Kern-Konzept:** EINE zentrale Tabelle für ALLE Dropdown-Werte im System

**Warum zentral?**
- ✅ Keine Code-Änderungen für neue Werte (einfach DB-Insert!)
- ✅ Automatisches Tracking welche Werte tatsächlich genutzt werden
- ✅ User kann eigene Werte hinzufügen (is_custom=1)
- ✅ Sortierung nach Häufigkeit möglich
- ✅ Einfaches Löschen ungenutzter Werte

**Felder:**
```sql
CREATE TABLE field_options (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    
    -- FELD-ZUORDNUNG ✅
    field_name          TEXT NOT NULL,          -- z.B. "flower_color", "hardiness_zone", "propagation_method"
    
    -- WERT ✅
    option_value        TEXT NOT NULL,          -- z.B. "rot", "Zone 7a (-17.7 bis -15.0°C)", "Samen"
    
    -- SORTIERUNG ✅
    sort_order          INTEGER DEFAULT 0,      -- Manuelle Anzeigereihenfolge (niedrig = oben)
    
    -- HERKUNFT ✅
    is_custom           BOOLEAN DEFAULT 0,      -- 0=Standard (vordefiniert), 1=User-hinzugefügt
    
    -- TRACKING ✅
    usage_count         INTEGER DEFAULT 0,      -- Wie oft wurde dieser Wert benutzt?
    last_used           TIMESTAMP,              -- Wann zuletzt benutzt?
    
    UNIQUE(field_name, option_value)
);

-- Indizes
CREATE INDEX idx_field_options_name ON field_options(field_name);
CREATE INDEX idx_field_options_usage ON field_options(usage_count DESC);
CREATE INDEX idx_field_options_custom ON field_options(is_custom);
```

**Alle field_name Werte (aus Tabellen 1-15):**

```sql
-- TABELLE 4: PLANT_CATEGORIES
category_path                   -- "Nutzpflanzen/Gemüse/Fruchtgemüse"

-- TABELLE 5: PLANT_ORIGIN
breeder_country                 -- "DE", "FR", "GB", "US", "AU-HU"
introducer_country              -- "DE", "FR", "GB", "US"
hybrid_status                   -- "species", "hybrid", "cultivar", "selection"
ploidy                          -- "diploid", "triploid", "tetraploid", "unknown"
breeding_method                 -- "Kreuzung", "Mutation", "Sämling", "Auslese"

-- TABELLE 6: PLANT_SITE
hardiness_zone                  -- "Zone 7a (-17.7 bis -15.0°C)"
light_requirement               -- "vollsonnig", "sonnig", "halbschattig", "absonnig", "schattig"
moisture_requirement            -- "trocken", "halbtrocken", "ausgeglichen", "feucht", "nass"
soil_type                       -- "sandig", "lehmig", "tonig", "humos", "kiesig", "anspruchslos"
nutrient_demand                 -- "niedrig", "mittel", "hoch"

-- TABELLE 7: PLANT_FLOWER
flower_color                    -- "weiss", "gelb", "rosa", "rot", "violett", "blau", "mehrfarbig"
flower_fragrance                -- "nicht duftend", "leicht duftend", "duftend", "stark duftend", "sehr stark duftend"
flower_size                     -- "sehr klein", "klein", "mittelgross", "gross", "sehr gross"
flower_fullness                 -- "einfach", "halbgefüllt", "gefüllt", "sehr gefüllt"
flower_form                     -- "schalenförmig", "rosettenförmig", "pompon", "trichterförmig"
flower_vase_life                -- "kurz", "mittel", "lang"
flower_blooming_cycle           -- "einmalblühend", "nachblühend", "öfterblühend", "dauerblühend"

-- TABELLE 8: PLANT_FRUIT
fruit_taste                     -- "süss", "süss-säuerlich", "säuerlich", "herb", "bitter", "mild"
fruit_juiciness                 -- "trocken", "wenig saftig", "saftig", "sehr saftig", "extrem saftig"
fruit_texture                   -- "fest", "knackig", "weich", "cremig", "mehlig", "faserig", "zart"

-- TABELLE 9: PLANT_GROWTH
growth_cycle                    -- "einjährig", "zweijährig", "mehrjährig"
growth_form_primary             -- "aufrecht", "bogig", "buschig", "kletternd", "kriechend", "horstig"
growth_form_secondary           -- "kompakt", "kann klettern"
growth_vigor                    -- "schwach", "mittel", "stark", "sehr stark"

-- TABELLE 10: PLANT_LEAF
leaf_color                      -- "grün", "hellgrün", "dunkelgrün", "blaugrün", "panaschiert"
leaf_form                       -- "rund", "oval", "länglich", "herzförmig", "gefiedert", "gelappt"
leaf_aroma                      -- "minzig", "zitronig", "würzig", "scharf", "mild", "bitter"
leaf_size                       -- "klein", "mittel", "gross"
leaf_autumn_color               -- "gelb", "orange", "rot", "bronze", "purpur", "mehrfarbig"

-- TABELLE 11: PLANT_ROOT
root_depth                      -- "flachwurzelnd", "mitteltiefwurzelnd", "tiefwurzelnd"
root_form                       -- "Pfahlwurzel", "Herzwurzel", "Flachwurzel", "Büschelwurzel"
root_anchorage                  -- "schwach", "mittel", "gut", "sehr gut"
root_taste                      -- "süss", "würzig", "scharf", "mild", "bitter", "erdig", "nussig"

-- TABELLE 12: PLANT_NURSERY
propagation_method              -- "Samen", "Steckling", "Teilung", "Veredelung", "Absenker"
germination_light               -- "Lichtkeimer", "Dunkelkeimer", "indifferent"

-- TABELLE 13: POT_SIZES
pot_code                        -- "T9", "T12", "C2", "C10" (aber gefüllt aus pot_sizes Tabelle!)

-- TABELLE 14: PLANT_INVENTORY
inventory_status                -- "verkaufsfertig", "in Kultur", "bestellt", "ausverkauft", "reserviert"

-- TABELLE 15: PLANT_USAGE
usage_plant_parts               -- "Blätter", "Blüten", "Früchte", "Wurzeln", "Samen", "ganze Pflanze"
usage_purpose                   -- "Küche", "Gewürz", "Tee", "Medizin", "Zierde", "Schnittblume"
usage_processing                -- "frisch", "roh", "gekocht", "getrocknet", "eingelegt", "fermentiert"
usage_storage                   -- "kühl", "dunkel", "trocken", "einfrieren", "einmachen"
```

**Beispiel-Abfragen:**

```sql
-- Dropdown für Blütenfarbe (sortiert nach Häufigkeit)
SELECT option_value 
FROM field_options 
WHERE field_name = 'flower_color'
ORDER BY usage_count DESC, sort_order ASC;

-- Nur Standard-Werte (keine Custom)
SELECT option_value 
FROM field_options 
WHERE field_name = 'propagation_method' AND is_custom = 0
ORDER BY sort_order ASC;

-- Alle ungenutzten Werte finden
SELECT field_name, option_value 
FROM field_options 
WHERE usage_count = 0;

-- Neuen Custom-Wert hinzufügen (User tippt "koralle" bei Blütenfarbe)
INSERT INTO field_options (field_name, option_value, sort_order, is_custom, usage_count) 
VALUES ('flower_color', 'koralle', 999, 1, 1)
ON CONFLICT(field_name, option_value) DO UPDATE SET usage_count = usage_count + 1;

-- Usage-Count erhöhen wenn Wert benutzt wird
UPDATE field_options 
SET usage_count = usage_count + 1, last_used = CURRENT_TIMESTAMP
WHERE field_name = 'flower_color' AND option_value = 'rot';
```

**Auto-Suggest Logik:**

```sql
-- Dropdown zeigt:
-- 1. Häufigste Werte zuerst (usage_count DESC)
-- 2. Dann alphabetisch
-- 3. Custom-Werte am Ende

SELECT option_value
FROM field_options
WHERE field_name = 'flower_color'
ORDER BY 
    is_custom ASC,              -- Standard vor Custom
    usage_count DESC,           -- Häufige zuerst
    option_value ASC;           -- Dann alphabetisch
```

**Entscheidungen:**
- ✅ **Eine zentrale Tabelle** für ALLE Dropdown-Werte
- ✅ **field_name** identifiziert das Feld (z.B. "flower_color")
- ✅ **option_value** ist der eigentliche Wert (z.B. "rot")
- ✅ **is_custom** trennt Standard von User-Werten
- ✅ **usage_count** trackt Popularität (für intelligente Sortierung)
- ✅ **sort_order** für manuelle Überschreibung
- ✅ **UNIQUE(field_name, option_value)** verhindert Duplikate
- ⏳ Für Challenge: Auto-Cleanup ungenutzter Werte? Synonyme für Suche?

**Alle Standard-Werte werden beim DB-Setup eingefügt!** (Siehe alle INSERT-Statements in Tabellen 5-15)

---

### ✅ TABELLE 17: PLANT_AVAILABILITY (Öffentliche Verfügbarkeit - Federated Marketplace)

**Status:** Finalisiert - 03. Februar 2026, 22:42 Uhr

**Kern-Konzept:** Gärtnereien teilen SELEKTIV ihren Bestand mit dem öffentlichen Netzwerk

**Wichtiger Unterschied:**
- **PLANT_INVENTORY** = Privater Bestand (nur Gärtnerei sieht es)
- **PLANT_AVAILABILITY** = Öffentliches Angebot (Netzwerk sieht es)

**Workflow:**
```
PLANT_INVENTORY (privat)          PLANT_AVAILABILITY (öffentlich)
├─ oMioBio hat 78 Stück      →    ├─ oMioBio teilt 40 Stück
└─ in Gewächshaus 2                └─ sichtbar für Netzwerk
```

**Felder:**
```sql
CREATE TABLE plant_availability (
    id                      INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id                INTEGER NOT NULL,
    nursery_id              INTEGER NOT NULL,
    inventory_id            INTEGER,                -- Verknüpfung zu plant_inventory (optional)
    
    -- SICHTBARKEIT (Kern!) ✅
    is_public               BOOLEAN DEFAULT 0,      -- TRUE = öffentlich im Netzwerk sichtbar
    
    -- ANGEBOT (kommt aus inventory, kann überschrieben werden) ✅
    pot_size                TEXT,                   -- "T9", "T12", "C2"
    available_quantity      INTEGER,                -- 40 (obwohl inventory 78 hat!)
    
    -- PREIS ✅
    price_chf               REAL,                   -- 8.50
    
    -- LIEFERUNG ✅
    delivery_week           INTEGER,                -- KW 15
    delivery_year           INTEGER,                -- 2026
    delivery_info           TEXT,                   -- "Sofort lieferbar", "Ab KW 15"
    
    -- LABELS ✅
    labels                  TEXT,                   -- "Bio Suisse", "ProSpecieRara"
    
    -- KONTAKT ✅
    contact_url             TEXT,                   -- Link zur Gärtnerei-Webseite oder Kontaktformular
    
    -- TRACKING
    last_updated            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- ZUSATZ
    notes                   TEXT,
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE,
    FOREIGN KEY (nursery_id) REFERENCES nurseries(id) ON DELETE CASCADE,
    FOREIGN KEY (inventory_id) REFERENCES plant_inventory(id) ON DELETE SET NULL
);

-- Indizes
CREATE INDEX idx_availability_plant_id ON plant_availability(plant_id);
CREATE INDEX idx_availability_nursery_id ON plant_availability(nursery_id);
CREATE INDEX idx_availability_public ON plant_availability(is_public);
CREATE INDEX idx_availability_inventory ON plant_availability(inventory_id);
```

**Beispiel-Workflow:**

```sql
-- 1. oMioBio hat privaten Bestand (PLANT_INVENTORY)
INSERT INTO plant_inventory VALUES (
    1, 123, 1,                          -- id=1, Tomate 'Black Cherry', oMioBio
    'T12', 78, 'verkaufsfertig', 15, 2026, 8.50,
    'Gewächshaus 2',
    CURRENT_TIMESTAMP
);

-- 2. oMioBio entscheidet: JA, ich teile diese Pflanze! (PLANT_AVAILABILITY)
INSERT INTO plant_availability VALUES (
    NULL, 123, 1, 1,                    -- Verknüpft mit inventory_id=1
    1,                                  -- is_public = TRUE
    'T12', 40,                          -- Nur 40 von 78 öffentlich!
    8.50,
    15, 2026, 'Sofort lieferbar',
    'Bio Suisse',
    'https://omiobio.ch/kontakt',
    CURRENT_TIMESTAMP,
    NULL
);

-- 3. Eulenhof teilt auch diese Pflanze
INSERT INTO plant_availability VALUES (
    NULL, 123, 2, NULL,                 -- Kein inventory_id (manuell)
    1,
    'T9', 80,
    6.00,
    18, 2026, 'Ab KW 18',
    'Bio Suisse, Demeter',
    'https://eulenhof.ch/bestellen',
    CURRENT_TIMESTAMP,
    NULL
);
```

**Query für Pflanzenportraitseite:**

```sql
-- Zeige alle öffentlichen Verfügbarkeiten für Tomate 'Black Cherry'
SELECT 
    n.name AS nursery_name,
    n.location,
    pa.available_quantity,
    pa.pot_size,
    pa.price_chf,
    pa.delivery_info,
    pa.labels,
    pa.contact_url
FROM plant_availability pa
JOIN nurseries n ON pa.nursery_id = n.id
WHERE pa.plant_id = 123           -- Tomate 'Black Cherry'
  AND pa.is_public = 1            -- Nur öffentliche!
ORDER BY pa.price_chf ASC;        -- Günstigste zuerst
```

**Anzeige auf Pflanzenportraitseite:**

```html
<!-- Tomate 'Black Cherry' - Verfügbarkeit -->
<div class="plant-availability">
    <h3>Diese Pflanze ist verfügbar bei:</h3>
    
    <div class="nursery-offer">
        <strong>Eulenhof Gärtnerei</strong> (Ballwil, LU)<br>
        80 Stück | T9 | CHF 6.00/Stück<br>
        Lieferung: Ab KW 18<br>
        <span class="labels">Bio Suisse, Demeter</span><br>
        <a href="https://eulenhof.ch/bestellen" class="btn">Kontakt</a>
    </div>
    
    <div class="nursery-offer">
        <strong>oMioBio GmbH</strong> (Ballwil, LU)<br>
        40 Stück | T12 | CHF 8.50/Stück<br>
        Lieferung: Sofort lieferbar (KW 15)<br>
        <span class="labels">Bio Suisse</span><br>
        <a href="https://omiobio.ch/kontakt" class="btn">Kontakt</a>
    </div>
</div>
```

**Entscheidungen:**
- ✅ **is_public:** Gärtnerei entscheidet pro Pflanze ob sichtbar!
- ✅ **inventory_id:** Optional verknüpft (Daten können aus inventory kommen)
- ✅ **available_quantity:** Kann KLEINER sein als inventory quantity (Kontrolle!)
- ✅ **Preis:** Kann von inventory abweichen (z.B. Netzwerk-Rabatt)
- ✅ **contact_url:** Link zur Gärtnerei (KEINE direkte Bestellung!)
- ✅ **labels:** TEXT komma-getrennt (einfach!)
- ❌ **KEIN E-Commerce:** Nur Transparenz + Kontakt-Links (Federated!)
- ⏳ **Für Challenge:** 
  - Automatische Sync inventory → availability?
  - Benachrichtigung wenn Bestand < available_quantity?
  - Reservierungs-System zwischen Gärtnereien?
  - "Merkliste" für Interessenten?

**Federated Marketplace Prinzip:**
Jede Gärtnerei behält volle Kontrolle über:
- WAS sie teilt (is_public)
- WIEVIEL sie teilt (available_quantity)
- WIE sie kontaktiert wird (contact_url)
- WANN sie liefert (delivery_week)

→ Kein zentraler Shop, sondern **transparentes Netzwerk!** 🌱

---

### ✅ TABELLE 18: PLANT_TRAITS (EAV für Sonderfälle - Flexibles Auffangbecken)

**Status:** Finalisiert - 03. Februar 2026, 22:50 Uhr

**Kern-Konzept:** Flexibles Zusatzfeld-System für ALLES was nicht in die Standard-Tabellen passt

**Wofür?**
- Ungewöhnliche Eigenschaften einzelner Pflanzen
- Experimentelle Daten (Testläufe, Feldversuche)
- Gärtnerei-spezifische Informationen
- Neue Ideen testen (bevor sie eigene Tabelle bekommen)
- Spezielle Auszeichnungen (ADR, Awards)
- Resistenzen/Toleranzen
- Alles was sich nicht standardisieren lässt

**Felder:**
```sql
CREATE TABLE plant_traits (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id            INTEGER NOT NULL,
    
    -- EIGENSCHAFT ✅
    trait_name          TEXT NOT NULL,          -- "ADR-Rose", "Mehltau-Resistenz", "Trockenheitstoleranz_Skala"
    
    -- WERT (flexibel!) ✅
    value_text          TEXT,                   -- "sehr gut", "ja", "9/10"
    value_numeric       REAL,                   -- 9.0, 8.5 (für Berechnungen!)
    value_unit          TEXT,                   -- "/10", "cm", "°C", "Tage"
    
    -- HERKUNFT ✅
    data_source         TEXT,                   -- "oMioBio Beobachtung", "Züchter Meilland", "ADR-Prüfung 2015"
    
    -- SICHTBARKEIT ✅
    visibility          TEXT DEFAULT 'public',  -- "public", "network", "private"
    
    -- TRACKING
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

-- Indizes
CREATE INDEX idx_traits_plant_id ON plant_traits(plant_id);
CREATE INDEX idx_traits_name ON plant_traits(trait_name);
CREATE INDEX idx_traits_visibility ON plant_traits(visibility);
```

**Beispiel-Daten:**

```sql
-- 1. ADR-Auszeichnung (Rose)
INSERT INTO plant_traits VALUES (
    NULL, 123,                      -- Rose 'Bonica 82'
    'ADR-Rose',
    'ja',
    1,                              -- 1=ja, 0=nein (für Queries!)
    NULL,
    'ADR-Prüfung 2015',
    'public',
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
);

-- 2. Mehltau-Resistenz (Rose)
INSERT INTO plant_traits VALUES (
    NULL, 123,                      -- Rose 'Bonica 82'
    'Mehltau-Resistenz',
    'hoch',
    8.5,                            -- Züchter-Skala 1-10
    '/10',
    'Züchter Meilland',
    'public',
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
);

-- 3. Platzfestigkeit (Tomate)
INSERT INTO plant_traits VALUES (
    NULL, 456,                      -- Tomate 'Black Cherry'
    'Platzfestigkeit',
    'sehr gut',
    NULL,
    NULL,
    'oMioBio Beobachtung 2025',
    'public',
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
);

-- 4. Trockenheitstoleranz-Test (Lavendel)
INSERT INTO plant_traits VALUES (
    NULL, 789,                      -- Lavendel
    'Trockenheitstoleranz_Skala',
    '9/10',
    9.0,
    '/10',
    'Feldversuch 2025 oMioBio',
    'private',                      -- Nur für uns!
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
);

-- 5. Bestseller (Gärtnerei-spezifisch)
INSERT INTO plant_traits VALUES (
    NULL, 456,
    'Bestseller_2025',
    'ja',
    1,
    NULL,
    'oMioBio Verkaufsstatistik',
    'private',
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
);

-- 6. Geschmacks-Bewertung (Experimentell)
INSERT INTO plant_traits VALUES (
    NULL, 456,                      -- Tomate 'Black Cherry'
    'Geschmack_Skala',
    'ausgezeichnet',
    9.5,                            -- Blind-Tasting-Score
    '/10',
    'Bio Suisse Degustations-Panel 2025',
    'network',                      -- Netzwerk darf es sehen
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
);

-- 7. Ertrag (Quantitativ)
INSERT INTO plant_traits VALUES (
    NULL, 456,
    'Ertrag_pro_Pflanze',
    '3.5 kg',
    3.5,
    'kg',
    'oMioBio Durchschnitt 2025',
    'public',
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
);
```

**Query-Beispiele:**

```sql
-- Alle ADR-Rosen finden
SELECT p.botanical_name, p.cultivar
FROM plants p
JOIN plant_traits t ON p.id = t.plant_id
WHERE t.trait_name = 'ADR-Rose' 
  AND t.value_numeric = 1;

-- Pflanzen mit Trockenheitstoleranz > 7
SELECT p.botanical_name, t.value_numeric AS toleranz
FROM plants p
JOIN plant_traits t ON p.id = t.plant_id
WHERE t.trait_name = 'Trockenheitstoleranz_Skala' 
  AND t.value_numeric > 7
ORDER BY t.value_numeric DESC;

-- Alle Traits für eine Pflanze
SELECT trait_name, value_text, value_numeric, value_unit, data_source
FROM plant_traits
WHERE plant_id = 123
ORDER BY trait_name ASC;

-- Bestseller 2025 (private Daten)
SELECT p.botanical_name
FROM plants p
JOIN plant_traits t ON p.id = t.plant_id
WHERE t.trait_name = 'Bestseller_2025' 
  AND t.value_numeric = 1
  AND t.visibility = 'private';

-- Durchschnittlicher Ertrag aller Tomaten
SELECT AVG(t.value_numeric) AS avg_ertrag_kg
FROM plant_traits t
JOIN plants p ON t.plant_id = p.id
JOIN plant_categories c ON p.id = c.plant_id
WHERE t.trait_name = 'Ertrag_pro_Pflanze'
  AND c.category_path LIKE 'Nutzpflanzen/Gem%se/Fruchtgem%se/Tomate%';
```

**Use Cases:**

**1. Spezielle Auszeichnungen:**
- ADR-Rose (Allgemeine Deutsche Rosenneuheitenprüfung)
- ProSpecieRara-Sorte
- Award-Gewinner
- Bio-Labels

**2. Resistenzen/Toleranzen:**
- Mehltau-Resistenz
- Krankheitsresistenz
- Trockenheitstoleranz
- Frosttoleranz
- Salztoleranz

**3. Experimentelle/Quantitative Daten:**
- Geschmacks-Skala (1-10)
- Duft-Intensität (1-10)
- Blühdauer in Tagen
- Ertrag pro Pflanze (kg)
- Wuchsgeschwindigkeit (cm/Monat)

**4. Gärtnerei-spezifische Infos:**
- "oMioBio_Liebling" (Ja/Nein)
- "Monika_Empfehlung" (Text)
- "Bestseller_2025" (Ja/Nein)
- "Schwierigkeitsgrad" (1-5)
- "Kundenrückmeldungen" (Text)

**5. Später eigene Tabelle?**
Wenn ein trait_name für 100+ Pflanzen wichtig wird:
- Dann neue Tabelle plant_disease_resistance
- Oder plant_awards
- Aber bis dahin: PLANT_TRAITS ist perfekt!

**Warum nicht einfach notes-Feld?**

✅ **Strukturiert:** trait_name = suchbar!  
✅ **Numerisch:** value_numeric = rechenbar! (Durchschnitte, Vergleiche)  
✅ **Mehrere Werte:** Eine Pflanze kann 20+ Traits haben  
✅ **Data Source:** Nachvollziehbar woher die Info kommt  
✅ **Visibility:** Kontrolle wer es sieht (public/network/private)  
✅ **Querybar:** Komplexe Abfragen möglich  

**Entscheidungen:**
- ✅ **EAV-Pattern:** Entity (plant_id) - Attribute (trait_name) - Value (value_text/numeric)
- ✅ **Dual Values:** Text UND Numeric (flexibel!)
- ✅ **value_unit:** Wichtig für numerische Werte (kg, cm, /10, Tage)
- ✅ **data_source:** Transparenz + Vertrauenswürdigkeit
- ✅ **visibility:** 3 Stufen (public, network, private)
- ✅ **Kein Schema-Lock:** Neue trait_names einfach hinzufügen!
- ⏳ **Für Challenge:** 
  - Auto-Suggest für trait_names (aus field_options)?
  - Validierung für häufige trait_names?
  - Migration: Wenn trait häufig → eigene Tabelle?

---

## 🎉 ALLE TABELLEN FINALISIERT! 🎉

**Stand:** 03. Februar 2026, 22:50 Uhr

**18 Tabellen komplett durchdacht und dokumentiert:**
1. ✅ PLANTS (Kern-Taxonomie)
2. ✅ PLANT_NAMES (Übersetzungen)
3. ✅ PLANT_SYNONYMS (Synonyme - unbegrenzt!)
4. ✅ PLANT_CATEGORIES (Vektorraum)
5. ✅ PLANT_ORIGIN (Züchtung + Registrierung)
6. ✅ PLANT_SITE (Standort - mit Challenges)
7. ✅ PLANT_FLOWER (Blüten - mit Challenges)
8. ✅ PLANT_FRUIT (Früchte - mit Challenges)
9. ✅ PLANT_GROWTH (Wuchs)
10. ✅ PLANT_LEAF (Blatt)
11. ✅ PLANT_ROOT (Wurzel)
12. ✅ PLANT_NURSERY (Kultur-Daten - universell)
13. ✅ POT_SIZES (Topfgrössen - pro Gärtnerei)
14. ✅ PLANT_INVENTORY (Bestand - pro Gärtnerei)
15. ✅ PLANT_USAGE (Verwendung)
16. ✅ FIELD_OPTIONS (Zentrale Dropdown-Verwaltung)
17. ✅ PLANT_AVAILABILITY (Federated Marketplace)
18. ✅ PLANT_TRAITS (EAV Catch-all)

**Zusätzlich benötigt (nicht pflanzenbezogen):**
- NURSERIES (Gärtnereien-Stammdaten)
- USERS (Benutzer/Logins - später)

---

## NÄCHSTE SCHRITTE

1. ✅ Tabellen 1-14 finalisiert (03. Feb 2026, 22:18 Uhr)
   - ⚠️ Tabelle 6: Bodenart + pH-Wert → Challenge-Diskussion
   - ⚠️ Tabelle 7: Blütenfarbe + Blütenform → Challenge-Diskussion
   - ⚠️ Tabelle 8: Fruchtfarbe + Geschmack → Challenge-Diskussion
2. ⏳ Tabellen 15-16 durchgehen
3. ⏳ SQL-Scripts generieren
4. ⏳ Import-Script für CSV

---

**ENDE - Stand 03. Februar 2026, 22:18 Uhr**

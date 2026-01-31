# TAXONOMIE & MULTILANGUAGE LOGIK - oMioBio PDB
**Erstellt:** 31. Januar 2026  
**Zweck:** Präzise Dokumentation der komplexen Namens-Logik  
**Status:** DEFINIERT - Dies ist die verbindliche Spezifikation!

---

## **KERN-PROBLEM**

Botanische Namen müssen **international eindeutig** sein (keine Umlaute!), aber **deutsche Originalnamen** sollen erhalten bleiben. Gleichzeitig brauchen wir **4 Sprachen** (DE/EN/FR/IT) und **URL-freundliche Webnamen**.

---

## **DIE 3 EINGABE-FELDER**

### **Was der User eingibt:**

```
Gattung:  [_________]  ← PFLICHT (z.B. "Rosa", "Achillea")
Art:      [_________]  ← OPTIONAL (z.B. "damascena", "millefolium")
Sorte:    [_________]  ← OPTIONAL (z.B. "König", "Paprika")
```

**Regeln:**
- **Nur Gattung ist Pflicht** - Rest optional
- **Mögliche Kombinationen:**
  - Nur Gattung: "Rosa"
  - Gattung + Art: "Rosa damascena"
  - Gattung + Sorte: "Rosa 'König'"
  - Gattung + Art + Sorte: "Rosa damascena 'Rose de Resht'"

---

## **WAS WIRD GENERIERT**

Aus den 3 Eingabefeldern werden **automatisch** generiert:

### **1. BOTANICAL_NAME (botanisch korrekt formatiert)**

**Format-Regeln:**
- Gattung: **Erster Buchstabe groß**, Rest klein
- Art: **Alles klein**
- Sorte: **In einfachen Anführungszeichen** ('Sorte')
- **Deutsche Umlaute konvertiert** (ö→oe, ä→ae, ü→ue, ß→ss)
- **Französische Accents BLEIBEN** (é, è, ç, â bleiben!)

**Beispiele:**
```
Input: Rosa, damascena, König
→ botanical_name: "Rosa damascena 'Koenig'"  (ö→oe!)

Input: Rosa, , Rose de Resht
→ botanical_name: "Rosa 'Rose de Resht'"  (kein species)

Input: Achillea, millefolium, Paprika
→ botanical_name: "Achillea millefolium 'Paprika'"

Input: Rosa, , Château de Versailles
→ botanical_name: "Rosa 'Château de Versailles'"  (â bleibt!)
```

---

### **2. WEBNAME (URL-freundlich)**

**Format-Regeln:**
- **Alles Kleinbuchstaben**
- **ALLE Sonderzeichen konvertiert** (auch französische!)
- **Leerzeichen → Bindestrich**
- **Nur erlaubt:** a-z, 0-9, Bindestrich (-)
- **Keine Anführungszeichen**

**Konvertierungen:**
```
Deutsch:     ä→ae, ö→oe, ü→ue, ß→ss
Französisch: é→e, è→e, ê→e, à→a, â→a, ç→c, ô→o, î→i
Leerzeichen: →-
Apostrophe:  entfernen oder →- (entscheiden!)
```

**Beispiele:**
```
"Rosa damascena 'Koenig'"
→ webname: "rosa-damascena-koenig"

"Achillea millefolium 'Paprika'"
→ webname: "achillea-millefolium-paprika"

"Rosa 'Château de Versailles'"
→ webname: "rosa-chateau-de-versailles"  (â→a!)

"Rosa 'Rose de Resht'"
→ webname: "rosa-rose-de-resht"
```

---

### **3. CULTIVAR (in Datenbank gespeichert)**

**Das ist der Sortenname wie er in der DB gespeichert wird:**

**Format-Regeln:**
- **NUR deutsche Umlaute konvertieren** (ö→oe, ä→ae, ü→ue, ß→ss)
- **Französische Accents BLEIBEN!**
- **Groß-/Kleinschreibung wie eingegeben**

**Beispiele:**
```
Input: "König"
→ cultivar in DB: "Koenig"  (ö→oe)

Input: "Paprika"
→ cultivar in DB: "Paprika"  (unverändert)

Input: "Château de Versailles"
→ cultivar in DB: "Château de Versailles"  (â bleibt!)

Input: "Rose de Resht"
→ cultivar in DB: "Rose de Resht"  (unverändert)
```

---

## **MULTILANGUAGE NAMEN**

### **WICHTIG: Sortennamen werden NIE übersetzt!**

**Übersetzt wird nur:** Volksname der Gattung/Art  
**Bleibt gleich:** Der Sortenname in Anführungszeichen

---

### **FORMAT pro Sprache:**

```
name_de: "Deutscher Volksname 'Sortenname'"
name_en: "English common name 'Cultivar'"
name_fr: "Nom français 'Cultivar'"
name_it: "Nome italiano 'Cultivar'"
```

**ABER:** Sortenname variiert je nach Sprache wegen Umlauten!

---

### **BEISPIEL 1: Achillea millefolium 'Paprika'**

**Eingabe:**
```
Gattung: Achillea
Art: millefolium
Sorte: Paprika
```

**In Datenbank:**
```sql
genus:          "Achillea"
species:        "millefolium"
cultivar:       "Paprika"
botanical_name: "Achillea millefolium 'Paprika'"
webname:        "achillea-millefolium-paprika"
```

**Anzeige (generiert mit Volksnamen):**
```
name_de: "Schafgarbe 'Paprika'"
name_en: "Yarrow 'Paprika'"
name_fr: "Achillée 'Paprika'"
name_it: "Achillea 'Paprika'"
```

**Sortename 'Paprika' ist ÜBERALL gleich!**

---

### **BEISPIEL 2: Rosa 'König' (MIT UMLAUT!)**

**Eingabe:**
```
Gattung: Rosa
Art: (leer)
Sorte: König  ← MIT Umlaut eingegeben!
```

**In Datenbank:**
```sql
genus:          "Rosa"
species:        ""
cultivar:       "Koenig"              ← ö→oe konvertiert!
botanical_name: "Rosa 'Koenig'"
webname:        "rosa-koenig"
```

**ABER bei Anzeige:**

**DEUTSCH behält Original-Schreibweise:**
```
name_de: "Rose 'König'"  ← MIT Umlaut! (wie eingegeben)
```

**ANDERE Sprachen nutzen konvertierte Form:**
```
name_en: "Rose 'Koenig'"  ← OHNE Umlaut
name_fr: "Rose 'Koenig'"  ← OHNE Umlaut
name_it: "Rosa 'Koenig'"  ← OHNE Umlaut (Rosa vs Rose!)
```

**Warum?**
- Deutsche Umlaute (ö,ä,ü) existieren nur im deutschen Sprachraum
- Im Deutschen soll die Originalschreibweise erhalten bleiben
- International wird konvertiert (botanischer Standard)

---

### **BEISPIEL 3: Rosa damascena 'Rose de Resht'**

**Eingabe:**
```
Gattung: Rosa
Art: damascena
Sorte: Rose de Resht
```

**In Datenbank:**
```sql
genus:          "Rosa"
species:        "damascena"
cultivar:       "Rose de Resht"
botanical_name: "Rosa damascena 'Rose de Resht'"
webname:        "rosa-damascena-rose-de-resht"
```

**Anzeige:**
```
name_de: "Damaszener-Rose 'Rose de Resht'"
name_en: "Damask rose 'Rose de Resht'"
name_fr: "Rose de Damas 'Rose de Resht'"
name_it: "Rosa di Damasco 'Rose de Resht'"
```

**Sortenname 'Rose de Resht' bleibt ÜBERALL gleich!**

---

### **BEISPIEL 4: Rosa 'Wirbelwind' (deutscher Sortenname)**

**Eingabe:**
```
Gattung: Rosa
Art: (leer)
Sorte: Wirbelwind
```

**In Datenbank:**
```sql
genus:          "Rosa"
cultivar:       "Wirbelwind"
botanical_name: "Rosa 'Wirbelwind'"
webname:        "rosa-wirbelwind"
```

**Anzeige:**
```
name_de: "Rose 'Wirbelwind'"
name_en: "Rose 'Wirbelwind'"    ← NICHT übersetzt zu "Whirlwind"!
name_fr: "Rose 'Wirbelwind'"    ← NICHT übersetzt!
name_it: "Rosa 'Wirbelwind'"
```

**Sortennamen werden NIE übersetzt - egal ob deutsch, französisch oder englisch!**

---

### **BEISPIEL 5: Rosa 'Souvenir de Docteur Jamain' (franz. Sortenname)**

**Eingabe:**
```
Gattung: Rosa
Sorte: Souvenir de Docteur Jamain
```

**In Datenbank:**
```sql
genus:          "Rosa"
cultivar:       "Souvenir de Docteur Jamain"
botanical_name: "Rosa 'Souvenir de Docteur Jamain'"
webname:        "rosa-souvenir-de-docteur-jamain"
```

**Anzeige:**
```
name_de: "Rose 'Souvenir de Docteur Jamain'"  ← NICHT übersetzt!
name_en: "Rose 'Souvenir de Docteur Jamain'"
name_fr: "Rose 'Souvenir de Docteur Jamain'"
name_it: "Rosa 'Souvenir de Docteur Jamain'"
```

---

## **WOHER KOMMEN DIE VOLKSNAMEN?**

### **Option A: Separate Übersetzungstabelle**

```sql
CREATE TABLE genus_translations (
    genus TEXT PRIMARY KEY,
    name_de TEXT,
    name_en TEXT,
    name_fr TEXT,
    name_it TEXT
);

-- Beispiel-Einträge:
INSERT INTO genus_translations VALUES
    ('Achillea', 'Schafgarbe', 'Yarrow', 'Achillée', 'Achillea'),
    ('Rosa', 'Rose', 'Rose', 'Rose', 'Rosa'),
    ('Lavandula', 'Lavendel', 'Lavender', 'Lavande', 'Lavanda');
```

**Dann automatisch generieren:**
```javascript
function generateNameDE(genus, species, cultivar, original_cultivar) {
    // Volksname holen
    let volksname = getVolksnameDE(genus);  // "Achillea" → "Schafgarbe"
    
    if (cultivar) {
        // Für Deutsch: Original-Eingabe verwenden (mit Umlaut!)
        return `${volksname} '${original_cultivar}'`;
    } else if (species) {
        return `${volksname} ${species}`;
    } else {
        return volksname;
    }
}

function generateNameEN(genus, species, cultivar) {
    let commonname = getCommonNameEN(genus);  // "Achillea" → "Yarrow"
    
    if (cultivar) {
        // Für Englisch: Konvertierte Form (ohne Umlaut!)
        return `${commonname} '${cultivar}'`;
    } else if (species) {
        return `${commonname} ${species}`;
    } else {
        return commonname;
    }
}
```

---

## **DATENBANK-STRUKTUR (FINAL)**

```sql
CREATE TABLE plants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    
    -- EINGABE (wie vom User eingegeben)
    genus TEXT NOT NULL,              -- "Rosa", "Achillea"
    species TEXT,                     -- "damascena", "millefolium"
    cultivar TEXT,                    -- "Koenig", "Paprika" (konvertiert!)
    
    -- ORIGINAL-EINGABE (nur für deutschen Namen!)
    cultivar_original TEXT,           -- "König" (MIT Umlaut, wie eingegeben)
    
    -- GENERIERT (automatisch beim Speichern)
    botanical_name TEXT NOT NULL,     -- "Rosa damascena 'Koenig'"
    webname TEXT NOT NULL UNIQUE,     -- "rosa-damascena-koenig"
    
    -- VOLKSNAMEN (generiert aus genus_translations + cultivar)
    name_de TEXT,                     -- "Damaszener-Rose 'König'" (Original!)
    name_en TEXT,                     -- "Damask rose 'Koenig'" (konvertiert)
    name_fr TEXT,                     -- "Rose de Damas 'Koenig'" (konvertiert)
    name_it TEXT,                     -- "Rosa di Damasco 'Koenig'" (konvertiert)
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Index für Dubletten-Check
CREATE UNIQUE INDEX idx_botanical_unique ON plants(botanical_name);
```

**WICHTIG:** Wir speichern **cultivar_original** extra für deutschen Namen!

---

## **DUBLETTEN-VERMEIDUNG**

### **Check VOR dem Speichern:**

```sql
-- Prüfe ob botanical_name schon existiert:
SELECT id FROM plants 
WHERE botanical_name = 'Rosa damascena ''Koenig''';

-- Wenn Ergebnis → FEHLER: "Diese Pflanze existiert bereits!"
-- Wenn leer → Speichern erlaubt
```

**Problem:** Was wenn Schreibweise minimal unterschiedlich?

```
User gibt ein: "de Resht"
DB hat schon:  "De Resht"

→ Sind das Dubletten?
```

**Lösung: Normalisierung vor dem Vergleich:**

```javascript
function normalizeCultivar(text) {
    // Für Vergleich:
    let normalized = text.toLowerCase();
    normalized = normalized.replace(/\s+/g, ' ').trim();
    return normalized;
}

// Beim Check:
const input_normalized = normalizeCultivar(user_input);
const existing = db.query(`
    SELECT * FROM plants 
    WHERE LOWER(TRIM(cultivar)) = ?
    AND genus = ?
    AND (species = ? OR (species IS NULL AND ? IS NULL))
`, [input_normalized, genus, species, species]);

if (existing.length > 0) {
    throw new Error("Dublette gefunden: " + existing[0].botanical_name);
}
```

---

## **REPARATUR-FUNKTION**

### **Zweck:**
Namen neu generieren falls Datenbank beschädigt oder Regeln geändert

### **Was wird repariert:**

```javascript
async function repairAllNames() {
    const plants = await db.query("SELECT * FROM plants");
    
    for (let plant of plants) {
        // 1. Genus korrigieren (erster Buchstabe groß)
        let genus = capitalizeFirst(plant.genus);
        
        // 2. Species korrigieren (alles klein)
        let species = plant.species ? plant.species.toLowerCase() : '';
        
        // 3. Cultivar konvertieren (ö→oe)
        let cultivar = plant.cultivar_original 
            ? sanitizeCultivar(plant.cultivar_original)
            : plant.cultivar;
        
        // 4. Botanical Name neu generieren
        let botanical_name = generateBotanicalName(genus, species, cultivar);
        
        // 5. Webname neu generieren
        let webname = generateWebname(genus, species, cultivar);
        
        // 6. Volksnamen neu generieren
        let name_de = generateNameDE(genus, species, plant.cultivar_original || cultivar);
        let name_en = generateNameEN(genus, species, cultivar);
        let name_fr = generateNameFR(genus, species, cultivar);
        let name_it = generateNameIT(genus, species, cultivar);
        
        // 7. Update
        await db.update('plants', plant.id, {
            genus,
            species,
            cultivar,
            botanical_name,
            webname,
            name_de,
            name_en,
            name_fr,
            name_it
        });
    }
    
    console.log(`✓ ${plants.length} Pflanzen repariert`);
}
```

---

## **SORTIERUNG IN LISTE**

### **Anzeige-Optionen:**

**Option A: Nur botanical_name**
```
Rosa 'König'
Rosa damascena 'Rose de Resht'
Rosa gallica 'Officinalis'
```

**Option B: Einzeln (Gattung | Art | Sorte)**
```
Rosa     |            | 'König'
Rosa     | damascena  | 'Rose de Resht'
Rosa     | gallica    | 'Officinalis'
Achillea | millefolium| 'Paprika'
```

### **Sortier-Reihenfolge (bei Einzeln):**

```sql
ORDER BY 
    genus ASC,           -- 1. Gattung (alphabetisch)
    species ASC,         -- 2. Art (alphabetisch)
    cultivar ASC         -- 3. Sorte (alphabetisch)
```

**Ergebnis:**
```
Achillea millefolium 'Paprika'
Achillea millefolium 'Terracotta'
Rosa 'König'
Rosa 'Wirbelwind'
Rosa damascena 'Rose de Resht'
Rosa gallica 'Officinalis'
```

---

## **CODE-FUNKTIONEN (Pseudo-Code)**

### **1. Umlaut-Konvertierung**

```javascript
function sanitizeCultivar(input) {
    if (!input) return '';
    
    let text = input;
    
    // NUR deutsche Umlaute konvertieren
    text = text.replace(/ä/g, 'ae');
    text = text.replace(/Ä/g, 'Ae');
    text = text.replace(/ö/g, 'oe');
    text = text.replace(/Ö/g, 'Oe');
    text = text.replace(/ü/g, 'ue');
    text = text.replace(/Ü/g, 'Ue');
    text = text.replace(/ß/g, 'ss');
    
    // Französische Accents BLEIBEN!
    // Andere Sonderzeichen BLEIBEN!
    
    return text;
}
```

### **2. Webname generieren**

```javascript
function generateWebname(genus, species, cultivar) {
    let parts = [];
    
    if (genus) parts.push(genus);
    if (species) parts.push(species);
    if (cultivar) parts.push(cultivar);
    
    let text = parts.join(' ');
    
    // Alles klein
    text = text.toLowerCase();
    
    // ALLE Sonderzeichen konvertieren
    text = text.replace(/ä/g, 'ae');
    text = text.replace(/ö/g, 'oe');
    text = text.replace(/ü/g, 'ue');
    text = text.replace(/ß/g, 'ss');
    text = text.replace(/é|è|ê/g, 'e');
    text = text.replace(/à|â/g, 'a');
    text = text.replace(/ô/g, 'o');
    text = text.replace(/î/g, 'i');
    text = text.replace(/ç/g, 'c');
    
    // Leerzeichen → Bindestrich
    text = text.replace(/\s+/g, '-');
    
    // Anführungszeichen entfernen
    text = text.replace(/['"]/g, '');
    
    // Nur erlaubt: a-z, 0-9, Bindestrich
    text = text.replace(/[^a-z0-9-]/g, '');
    
    // Mehrfache Bindestriche → einzeln
    text = text.replace(/-+/g, '-');
    
    // Bindestriche am Anfang/Ende entfernen
    text = text.replace(/^-+|-+$/g, '');
    
    return text;
}
```

### **3. Botanical Name generieren**

```javascript
function generateBotanicalName(genus, species, cultivar) {
    let parts = [];
    
    // Gattung: Erster Buchstabe groß
    if (genus) {
        parts.push(capitalizeFirst(genus.toLowerCase()));
    }
    
    // Art: Alles klein
    if (species) {
        parts.push(species.toLowerCase());
    }
    
    // Sorte: In Anführungszeichen, konvertiert
    if (cultivar) {
        parts.push(`'${cultivar}'`);
    }
    
    return parts.join(' ');
}

function capitalizeFirst(text) {
    if (!text) return '';
    return text.charAt(0).toUpperCase() + text.slice(1);
}
```

### **4. Volksnamen generieren**

```javascript
function generateNameDE(genus, species, cultivar_original, cultivar) {
    // Volksname holen
    let volksname = getVolksnameDE(genus);
    if (!volksname) volksname = genus;  // Fallback
    
    if (cultivar_original) {
        // Deutsch: Original-Eingabe mit Umlaut!
        return `${volksname} '${cultivar_original}'`;
    } else if (cultivar) {
        return `${volksname} '${cultivar}'`;
    } else if (species) {
        return `${volksname} ${species}`;
    } else {
        return volksname;
    }
}

function generateNameEN(genus, species, cultivar) {
    let commonname = getCommonNameEN(genus);
    if (!commonname) commonname = genus;
    
    if (cultivar) {
        // Englisch: Konvertierte Form ohne Umlaut!
        return `${commonname} '${cultivar}'`;
    } else if (species) {
        return `${commonname} ${species}`;
    } else {
        return commonname;
    }
}

// Analog für FR und IT
```

---

## **EDGE CASES**

### **1. Nur Gattung, keine Art, keine Sorte**

```
Input: Rosa, , 
→ botanical_name: "Rosa"
→ webname: "rosa"
→ name_de: "Rose"
→ name_en: "Rose"
```

### **2. Mehrere Leerzeichen in Sortenname**

```
Input: Rosa, , Rose  de   Resht
→ cultivar: "Rose de Resht" (normalisiert zu einzelnen Leerzeichen)
→ webname: "rosa-rose-de-resht"
```

### **3. Sonderzeichen in Sortenname**

```
Input: Rosa, , Queen Elizabeth's Rose
→ cultivar: "Queen Elizabeth's Rose"
→ webname: "rosa-queen-elizabeths-rose" (Apostroph → s)
```

### **4. Zahlen in Sortenname**

```
Input: Rosa, , Knockout®
→ cultivar: "Knockout" (® entfernen)
→ botanical_name: "Rosa 'Knockout'"
→ webname: "rosa-knockout"
```

---

## **OFFENE FRAGEN / TODO**

1. **Apostroph im Webname:** Entfernen oder zu Bindestrich?
2. **Warenzeichen-Symbole (®, ™):** Automatisch entfernen?
3. **Genus-Übersetzungstabelle:** Wo pflegen? Manuell oder importieren?
4. **Normalisierung bei Dubletten-Check:** Case-insensitive?
5. **Art-Volksnamen:** Gibt es auch für species Übersetzungen?
   - z.B. "Rosa damascena" → "Damaszener-Rose" (nicht nur "Rose")

---

## **ZUSAMMENFASSUNG**

**Das System braucht:**

1. ✅ **3 Eingabefelder** (Gattung PFLICHT, Art optional, Sorte optional)
2. ✅ **2 DB-Felder für Sorte** (cultivar konvertiert + cultivar_original mit Umlaut)
3. ✅ **Automatische Generierung** (botanical_name, webname)
4. ✅ **Genus-Übersetzungstabelle** für Volksnamen
5. ✅ **4 Sprachen** (DE mit Original, EN/FR/IT konvertiert)
6. ✅ **Dubletten-Check** (auf botanical_name)
7. ✅ **Reparatur-Funktion** (Namen neu generieren)

**Kern-Regel:**
> **Deutsche Umlaute (ö,ä,ü,ß) werden für botanical_name konvertiert,**
> **ABER im deutschen Namen (name_de) bleibt die Original-Schreibweise erhalten!**

---

**ENDE SPEZIFIKATION**

*Dieses Dokument ist die verbindliche Grundlage für die Implementierung!*  
*Bei Unklarheiten: HIER nachlesen, nicht neu diskutieren!*

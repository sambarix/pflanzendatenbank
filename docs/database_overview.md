# Pflanzendatenbank - Architektur-Übersicht

**Projekt:** Gardener AI - Federated Plant Database  
**Version:** 1.0  
**Stand:** 03. Februar 2026

---

## Übersicht

**18 Kern-Tabellen** organisiert in logische Gruppen:

- **Basis** (1-5): Taxonomie, Namen, Kategorien, Herkunft
- **Botanik** (6-11): Standort, Blüte, Frucht, Wuchs, Blatt, Wurzel
- **Gärtnerei** (12-14): Kultur, Töpfe, Bestand
- **Verwendung** (15): Nutzung & Ernte
- **System** (16-18): Dropdowns, Verfügbarkeit, Flexibilität

---

## 1. PLANTS - Kern-Taxonomie

**Zweck:** Minimale Basis-Daten für JEDE Pflanze

**Felder:**
- Gattung (Pflichtfeld)
- Art
- Unterart
- Sorte
- Ökotyp
- Botanischer Name (generiert, eindeutig)
- Web-Name (URL-freundlich)
- Matchcode (Kurzform)
- QR-Code Daten
- Notizen (Freifeld)

**Prinzip:** Jede Pflanze MUSS hier drin sein, alles andere ist optional!

---

## 2. PLANT_NAMES - Übersetzungen & Offizielle Namen

**Zweck:** Mehrsprachige Namen + Handelsnamen

**Felder:**
- Deutscher Name
- Englischer Name
- Französischer Name
- Italienischer Name
- Registrierungsname (offiziell)
- Ausstellungsname
- Handelsname

**Prinzip:** 1:1 Beziehung zu PLANTS, komplett optional

---

## 3. PLANT_SYNONYMS - Unbegrenzt viele Synonyme

**Zweck:** Alle alternativen Namen einer Pflanze

**Felder:**
- Synonym-Name
- Sprache (de, en, fr, la, it)
- Typ (common, historical, botanical, regional, trade)
- Notizen (z.B. "veraltet", "regional: Süddeutschland")

**Prinzip:** 1:n Beziehung - eine Pflanze kann 20+ Synonyme haben!

**Beispiel:** Rosa gallica 'Officinalis' hat 23 Synonyme!

---

## 4. PLANT_CATEGORIES - Kategorie-Vektorraum

**Zweck:** Multi-dimensionale Kategorisierung

**Felder:**
- Kategorie-Pfad (z.B. "Nutzpflanzen/Gemüse/Fruchtgemüse")
- Sortierung (wichtigster Pfad = 1)

**Prinzip:** Materialized Path - eine Pflanze kann in mehreren Kategorien gleichzeitig sein!

**Beispiel:** 
- Lavendel: "Nutzpflanzen/Kräuter/Küchenkräuter"
- Lavendel: "Nutzpflanzen/Kräuter/Heilkräuter"
- Lavendel: "Zierpflanzen/Stauden/Blütenstauden"
- Lavendel: "Wildpflanzen/Bienenweide"

---

## 5. PLANT_ORIGIN - Züchtung & Registrierung

**Zweck:** Herkunft, Züchter, rechtlicher Status

**Felder:**

**Züchtung (biologisch):**
- Züchter Name (Person)
- Züchter Firma
- Züchter Land
- Züchter Jahr
- Züchter-Referenz (interner Code)

**Registrierung (rechtlich):**
- Introducer Name
- Introducer Land  
- Introducer Jahr

**Hybrid-Details:**
- Hybrid-Status (species, hybrid, cultivar, selection)
- Eltern-Pflanzen
- Ploidie (diploid, triploid, tetraploid)

**Rechtlicher Schutz:**
- Sortenschutz (Ja/Nein)
- Markenschutz (Ja/Nein)

**Sonstiges:**
- Ursprungsregion
- Züchtungsmethode

**Prinzip:** Trennung zwischen biologischer Züchtung und rechtlicher Registrierung!

---

## 6. PLANT_SITE - Standort-Anforderungen

**Zweck:** Wo fühlt sich die Pflanze wohl?

**Felder:**

**Winterhärte (3 gekoppelte Felder):**
- Zone (z.B. "Zone 7a (-17.7 bis -15.0°C)")
- Minimale Temperatur (in °C)
- Maximale Temperatur (in °C)

**Licht:**
- Lichtbedarf (vollsonnig, sonnig, halbschattig, absonnig, schattig)

**Feuchtigkeit:**
- Feuchtigkeitsbedarf (trocken, halbtrocken, ausgeglichen, feucht, nass)

**Boden:**
- Bodenart (sandig, lehmig, tonig, humos, kiesig, anspruchslos) ⚠️ Challenge
- pH-Wert Minimum ⚠️ Challenge
- pH-Wert Maximum ⚠️ Challenge

**Nährstoffe:**
- Nährstoffbedarf (niedrig, mittel, hoch)

**⚠️ Challenge-Themen:** Bodenart-Kategorien, pH-Wert Erfassung

---

## 7. PLANT_FLOWER - Blüten-Merkmale

**Zweck:** Alles über die Blüte

**Felder:**
- Farbe ⚠️ Challenge
- Duft (nicht duftend bis sehr stark duftend)
- Größe (sehr klein bis sehr groß)
- Füllung (einfach, halbgefüllt, gefüllt, sehr gefüllt)
- Form (schalenförmig, rosettenförmig, etc.) ⚠️ Challenge
- Haltbarkeit in Vase (kurz, mittel, lang)
- Blühzyklus (einmalblühend, nachblühend, öfterblühend, dauerblühend)

**Blütezeit (12 Monate):**
- Januar bis Dezember (jeweils Ja/Nein)

**Notizen:** Freifeld für Besonderheiten

**⚠️ Challenge-Themen:** Blütenfarben-System, Blütenform-Kategorien

---

## 8. PLANT_FRUIT - Früchte & Erntegut

**Zweck:** Eigenschaften der Früchte

**Felder:**
- Farbe ⚠️ Challenge
- Größe (sehr klein bis sehr groß)
- Geschmack (süss, süss-säuerlich, säuerlich, herb, bitter, mild) ⚠️ Challenge
- Saftigkeit (trocken bis extrem saftig)
- Textur (fest, knackig, weich, cremig, mehlig, faserig)

**Erntezeit (12 Monate):**
- Januar bis Dezember (jeweils Ja/Nein)

**Entwicklung:**
- Tage von Blüte bis Ernte

**Notizen:** Freifeld

**⚠️ Challenge-Themen:** Fruchtfarben, Geschmacks-Kategorien

---

## 9. PLANT_GROWTH - Wuchs

**Zweck:** Wie wächst die Pflanze?

**Felder:**

**Zyklus:**
- Lebenszyklus (einjährig, zweijährig, mehrjährig)

**Größe:**
- Höhe in cm (aus Standard-Zahlenreihe)
- Breite in cm (aus Standard-Zahlenreihe)

**Form:**
- Hauptform (aufrecht, bogig, buschig, kletternd, kriechend, horstig, etc.)
- Zusatzform (kompakt, kann klettern)

**Wuchskraft:**
- Stärke (schwach, mittel, stark, sehr stark)

**Notizen:** Freifeld

**Standard-Zahlenreihe:** 10, 15, 20, 25, 30, 40, 50, 60, 80, 100, 125, 150... bis 3000

---

## 10. PLANT_LEAF - Blatt-Merkmale

**Zweck:** Blatt-Eigenschaften

**Felder:**
- Farbe (grün, hellgrün, dunkelgrün, panaschiert, etc.)
- Form (rund, oval, länglich, herzförmig, gefiedert, etc.)
- Aroma (minzig, zitronig, würzig - wichtig für Kräuter!)
- Größe (klein, mittel, groß)

**Herbstfärbung:**
- Hat Herbstfärbung (Ja/Nein)
- Farbe (gelb, orange, rot, bronze, purpur)

**Immergrün:**
- Immergrün (Ja/Nein)

**Notizen:** Für Glanz, Textur (glänzend, matt, ledrig, samtig, behaart)

---

## 11. PLANT_ROOT - Wurzel-Merkmale

**Zweck:** Wurzel-Charakteristik

**Felder:**
- Wurzeltiefe (flachwurzelnd, mitteltiefwurzelnd, tiefwurzelnd)
- Wurzelform (Pfahlwurzel, Herzwurzel, Flachwurzel, Büschelwurzel)
- Verankerung/Windfestigkeit (schwach, mittel, gut, sehr gut)
- Geschmack (nur bei Wurzelgemüse: süss, würzig, scharf, mild, bitter, erdig, nussig)
- Notizen (z.B. "Ausläufer bildend", "nicht verpflanzen")

---

## 12. PLANT_NURSERY - Kultur-Daten (UNIVERSELL)

**Zweck:** Biologische Kulturwerte für ALLE Gärtnereien

**Felder:**

**Vermehrung:**
- Vermehrungsart (Samen, Steckling, Teilung, Veredelung, Absenker)

**Keimung (nur bei Samen):**
- Keimdauer in Tagen
- Keimtemperatur Minimum (°C)
- Keimtemperatur Maximum (°C)
- Keimart Licht (Lichtkeimer, Dunkelkeimer, indifferent)

**Bewurzelung (bei Stecklingen):**
- Bewurzelungsdauer in Tagen

**Entwicklung:**
- Tage bis Jungpflanze
- Tage Jungpflanze bis Fertigpflanze

**Platzbedarf:**
- Pflanzen pro m² (Endabstand)

**Notizen:** Freifeld

**Prinzip:** Diese Werte gelten für ALLE - sind biologische Fakten!

---

## 13. POT_SIZES - Topfgrössen-Definition (PRO GÄRTNEREI)

**Zweck:** Welche Töpfe nutzt eine Gärtnerei?

**Felder:**
- Gärtnerei (NULL = Standard für alle)
- Topf-Code (T9, T12, C2, C10)
- Volumen in Liter
- Pflanzen pro m² (Dichte)
- Gewicht pro 1000 Töpfe (für Substrat-Berechnung)
- Sortierung

**Prinzip:** 
- Standard-Werte für alle Gärtnereien
- Jede Gärtnerei kann eigene Werte überschreiben
- Ermöglicht automatische Berechnungen (Substrat, Fläche)

**Beispiel:** oMioBio nutzt T9 mit 0.75L statt Standard 0.7L

---

## 14. PLANT_INVENTORY - Bestand (PRO GÄRTNEREI)

**Zweck:** Was hat DIESE Gärtnerei JETZT?

**Felder:**
- Pflanze
- Gärtnerei
- Topfgrösse (T9, C2, Wurzelnackt)
- Menge (Anzahl Stück)
- Status (verkaufsfertig, in Kultur, bestellt, ausverkauft)
- Lieferwoche (KW)
- Lieferjahr
- Preis (CHF)
- Standort (Gewächshaus 2, Freiland Nord, etc.)
- Letzte Aktualisierung

**Prinzip:** 
- PRIVATER Bestand
- Wird häufig aktualisiert
- Mehrere Einträge pro Pflanze möglich (verschiedene Größen/Status)

---

## 15. PLANT_USAGE - Verwendung

**Zweck:** Wie wird die Pflanze genutzt?

**Felder:**
- Pflanzenteile (Blätter, Blüten, Früchte, Wurzeln, Samen, ganze Pflanze)
- Zweck (Küche, Gewürz, Tee, Medizin, Zierde, Schnittblume, Bienenweide)
- Verarbeitung (frisch, roh, gekocht, getrocknet, eingelegt, fermentiert)
- Lagerung (kühl, dunkel, trocken, einfrieren, einmachen)

**Erntezeit (12 Monate):**
- Januar bis Dezember (jeweils Ja/Nein)

**Notizen:** Freifeld

**Prinzip:** Komma-getrennte Werte für Mehrfachverwendung möglich

---

## 16. FIELD_OPTIONS - Zentrale Dropdown-Verwaltung

**Zweck:** EINE Tabelle für ALLE Dropdown-Werte

**Felder:**
- Feld-Name (z.B. "flower_color", "propagation_method")
- Wert (z.B. "rot", "Samen")
- Sortierung (manuelle Reihenfolge)
- Custom (Standard vs. User-hinzugefügt)
- Nutzungszähler (wie oft benutzt?)
- Zuletzt benutzt

**Prinzip:**
- Keine Code-Änderungen für neue Werte
- Automatisches Tracking
- Intelligente Sortierung (häufigste zuerst)
- User kann eigene Werte hinzufügen

**Alle Dropdown-Felder aus Tabellen 4-15 sind hier zentral gespeichert!**

---

## 17. PLANT_AVAILABILITY - Öffentliche Verfügbarkeit (Federated Marketplace)

**Zweck:** Was teilt eine Gärtnerei mit dem Netzwerk?

**Felder:**
- Pflanze
- Gärtnerei
- Verknüpfung zu Inventory (optional)
- Öffentlich sichtbar (Ja/Nein) - DAS IST DER KERN!
- Topfgrösse
- Verfügbare Menge (kann KLEINER sein als Inventory!)
- Preis (CHF)
- Lieferwoche
- Lieferjahr
- Lieferinfo (Text)
- Labels (Bio Suisse, ProSpecieRara)
- Kontakt-URL (Link zur Gärtnerei)
- Letzte Aktualisierung
- Notizen

**Prinzip:**
- Gärtnerei entscheidet PRO PFLANZE ob sie teilt
- Gärtnerei wählt WIEVIEL sie teilt
- KEIN zentraler Shop - nur Transparenz + Kontakt-Links
- Federated Marketplace: Jede Gärtnerei behält Kontrolle

**Beispiel:** oMioBio hat 78 Stück, teilt aber nur 40 öffentlich

---

## 18. PLANT_TRAITS - Flexibles Auffangbecken (EAV)

**Zweck:** Für ALLES was nicht in Standard-Tabellen passt

**Felder:**
- Pflanze
- Eigenschafts-Name (z.B. "ADR-Rose", "Mehltau-Resistenz", "Trockenheitstoleranz_Skala")
- Wert Text (z.B. "sehr gut", "ja", "9/10")
- Wert Numerisch (z.B. 9.0, 8.5 - für Berechnungen!)
- Einheit (z.B. "/10", "kg", "Tage")
- Datenquelle (z.B. "oMioBio Beobachtung", "Züchter Meilland")
- Sichtbarkeit (public, network, private)
- Erstellt am
- Aktualisiert am

**Use Cases:**
- Spezielle Auszeichnungen (ADR-Rose, ProSpecieRara)
- Resistenzen/Toleranzen (Mehltau-Resistenz, Trockenheitstoleranz)
- Experimentelle Daten (Geschmacks-Skala 1-10, Ertrag pro Pflanze)
- Gärtnerei-spezifisch (Bestseller, Lieblinge, Empfehlungen)
- Neue Ideen testen (bevor sie eigene Tabelle bekommen)

**Prinzip:** 
- Entity-Attribute-Value Pattern (EAV)
- Strukturiert + suchbar + rechenbar
- Kein Schema-Lock - beliebig erweiterbar
- Wenn Eigenschaft für 100+ Pflanzen wichtig wird → eigene Tabelle erwägen

---

## Zusätzlich benötigte Tabellen (nicht pflanzenbezogen)

### NURSERIES - Gärtnereien-Stammdaten
- Name
- Standort
- Kontakt
- Labels (Bio Suisse, Demeter, etc.)
- Einstellungen

### USERS - Benutzer (später)
- Login
- Rechte
- Gärtnerei-Zuordnung

---

## Design-Prinzipien

### 1. Optionale Komplexität
- **Minimum:** Nur PLANTS-Tabelle ist Pflicht
- **Maximum:** Alle 18 Tabellen für detaillierte Pflanzen
- Salat: 1 Tabelle, Rose: 10+ Tabellen

### 2. Hybrid-Ansatz
- **Feste Tabellen** für Standard-Eigenschaften (90% der Fälle)
- **EAV (plant_traits)** für Sonderfälle (10%)
- **Zentrale field_options** für alle Dropdowns

### 3. Federated Prinzip
- Jede Gärtnerei behält volle Kontrolle
- Dezentrale Datenhaltung
- Freiwilliges Teilen (is_public)
- Kein zentraler Zwang

### 4. Think First - Code Later
- Architektur vor Implementierung
- Senior-Level Design
- Für 10+ Jahre gebaut

---

## Challenges für Freitag, 07. Februar 2026

**Tabelle 6 - PLANT_SITE:**
- Bodenart: Reichen 6 Kategorien? Oder detaillierter?
- pH-Wert: Zahlenfelder vs. Dropdown?

**Tabelle 7 - PLANT_FLOWER:**
- Blütenfarbe: 16 Standard-Farben genug? Kombinationen?
- Blütenform: Eine gemeinsame Liste oder getrennt (Rosen vs. allgemein)?

**Tabelle 8 - PLANT_FRUIT:**
- Fruchtfarbe: Standard-Farben definieren
- Geschmack: Standard-Kategorien festlegen

---

## Status

**✅ Komplett:** Alle 18 Tabellen finalisiert  
**✅ Dokumentiert:** In DATABASE_DESIGN_DECISIONS.md (nerdy, detailliert)  
**✅ Übersicht:** Dieses Dokument (handlich, schnell)  
**⏳ Nächste Schritte:** SQL-Scripts generieren, Challenges diskutieren  

---

**Entwickelt mit:** Peter Müller & Claude Sonnet 4.5

**Ende Übersichtsdokument**

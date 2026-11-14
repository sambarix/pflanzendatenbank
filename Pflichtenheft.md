# Pflanzendatenbank (PDB) - Pflichtenheft

**Version:** 1.2, Stand 14. November 2026 (Nachführung von Version 1.1 vom 4. Februar 2025, siehe `docs/archiv/`)
**Projektleitung:** Peter Müller (oMioBio), [pm@omiobio.ch](mailto:pm@omiobio.ch)
**Quellcode:** https://github.com/sambarix/pflanzendatenbank, Lizenz MIT
**Live-Beta:** https://pdb.biosoft.ch

Lesehinweis: In der Spalte "Stand" bedeutet *umgesetzt*, dass die Funktion in diesem Stand des Codes vorhanden und getestet ist, *teilweise*, dass eine Grundfunktion vorhanden ist, und *offen*, dass sie noch nicht gebaut ist.

## 1. Projektziel

### 1.1 Vision

Ein freies, nicht-proprietäres System für Pflanzendaten, das

- Bio-Gärtnereien bei Sortiment und Bestand unterstützt,
- Gartenplanern und Gartenbau bei der Pflanzenwahl und der Suche nach verfügbaren Bio-Pflanzen hilft,
- den Austausch zwischen Gärtnereien ermöglicht (Anfragen, später Verfügbarkeiten),
- umfangreiche Pflanzeninformationen öffentlich bereitstellt,
- strukturierte Daten für Webseiten und Webshops liefern kann (später),
- auf normalem Webhosting läuft und von Gärtnereien ohne IT-Kenntnisse bedient werden kann.

### 1.2 Anlass der Anfrageliste (2026)

Gärtnereien, die Vermehrungsmaterial oder Pflanzen zukaufen wollen und nicht wissen, ob es sie in Bioqualität gibt, müssen bei Anbietern nachfragen. Die PDB erleichtert das: Die Gärtnerei stellt eine Liste zusammen und gibt sie an Anbieter weiter, die nur noch ihre Antwort anklicken. Fällt die Antwort negativ aus (nicht in Bioqualität verfügbar), kann die Saatgutstelle daraus eine Ausnahmebewilligung erstellen. Dieser Ablauf ist der erste Anwendungsfall, auf den die Beta ausgerichtet ist.

### 1.3 Zielgruppen

1. **Bio-Gärtnereien** (primär): Sortiment und Bestand führen, Verfügbarkeit veröffentlichen, Anfragelisten für den Zukauf erstellen.
2. **Gartenplaner und Gartenbau** (sekundär): Pflanzen suchen, Anfragelisten erstellen. Kein eigener Bestand.
3. **Öffentlichkeit** (tertiär): Pflanzeninformationen abrufen, sehen, welche Gärtnerei eine Pflanze anbietet.

## 2. Systemarchitektur (Ist-Stand)

### 2.1 Technik

| Schicht | Umsetzung |
|---|---|
| Oberfläche | Grav 2.2 mit Kind-Theme `pdb-theme` (erbt von Quark 2), htmx für dynamische Tabellen und Formulare, Liberation Sans, Grün #4B8223, alle Schriften und Icons lokal |
| Endpunkte | Plugin `pdb-api`: Routen `/pdb-api/<name>` über Gravs Request-Zyklus, Logik in `user/api/*.php` (Funktionsbibliotheken, nie direkt per HTTP erreichbar) |
| Daten | SQLite: zentrale `plants.db` und je Konto eine eigene Datei `user/data/sqlite/<konto>.db` |
| Konten | Grav-Login-Plugin, Rollen über Grav-Gruppen und Rechte |
| Betrieb | Webhosting mit PHP 8.3 oder neuer (live bei cyon, Schweiz), lokal mit XAMPP |

Grav und SQLite sind Standardwerkzeuge. Wo Grav eine Funktion mitbringt (Gruppen, Rechte, Login, Seitenzugriff), wird sie genutzt und nicht nachgebaut.

### 2.2 Datenbanken

**Zentrale Stammdaten (`plants.db`)**, Schema in `database/plants_schema.sql`:

- `plants` (Taxonomie, generierte Namen: botanischer Name eindeutig, Webname, Matchcode), `plant_names` (DE/EN/FR/IT und weitere Namen), `plant_content` (Beschreibungen), `categories` und `plant_categories` (hierarchische Kategorien)
- je ein Detailbereich pro Tabelle: `plant_origin`, `plant_site`, `plant_flower`, `plant_leaf`, `plant_fruit`, `plant_root`, `plant_growth`, `plant_nursery`, `plant_usage`
- `plant_availability` (öffentliche Verfügbarkeit bei Gärtnereien), `pot_sizes`, `field_options`
- `plants_search` (FTS5-Volltextsuche)
- Entwicklungsstand November 2026: 1548 Pflanzen, 151 Kategorien

Abweichung von Version 1.1: Statt der geplanten EAV-Tabelle `plant_sections` gibt es feste Detailtabellen pro Bereich. `plant_traits` und `plant_synonyms` sind angelegt, aber noch nicht in Gebrauch.

**Konto-Datenbank (je Gärtnerei oder Planer)**: `nursery_info`, `nursery_locations` (hierarchische Standorte), `pot_sizes_custom`, `plant_inventory` (Bestand mit Topfgrösse, Menge, Status, Preis, Standort, öffentlich-Kennzeichen und oXs-Feldern), `inventory_overrides`, `request_lists` und `request_items` (Anfragelisten). Die Registrierung legt die Datei an, fehlende Spalten ergänzt eine automatische Migration. Der Pfad steht im Konto und kommt nie aus dem Browser.

### 2.3 Rollen und Rechte

| Rolle | Grav-Gruppe | Darf |
|---|---|---|
| Besucher | keine | Liste, Portrait, Hilfe ansehen |
| Gärtnerei | `user` (Recht `site.pdb_nursery`) | Sortiment, Bestand, Standorte, Export, Anfragelisten, Profil |
| Planer | `planer` | Anfragelisten, Profil (keine Bestandsfunktionen) |
| Editor | `editor` (Recht `site.pdb_editor`) | zusätzlich Stammdaten bearbeiten, kein Backend-Zugang |
| Admin | Grav-Backend | Konten und Gruppen verwalten |

Ein Editor mit eigener Gärtnerei gehört zu beiden Gruppen. Die Registrierung vergibt nie das Editor-Recht.

### 2.4 Sicherheit und Datenschutz

- Registrierung nur mit Zugangscode (als Hash gespeichert), nach acht Fehlversuchen kurze Sperre
- Passwörter nur als Hash, jede Gärtnerei arbeitet in ihrer eigenen Datenbankdatei
- Besucher ohne Konto erhalten kein Cookie (`session.lazy`); es werden keine Inhalte von fremden Servern geladen
- Datenschutzerklärung und Impressum liegen als Seiten bei (Vorlagen, vom Betreiber auszufüllen)

## 3. Funktionale Anforderungen

### 3.1 Öffentlicher Bereich

| Anforderung | Stand |
|---|---|
| Pflanzenliste mit Live-Suche, Kategorie-Filter, Sortierung, Spaltenauswahl | umgesetzt |
| Pflanzenportrait mit allen Bereichen, Bild (falls vorhanden) und Hinweis, welche Gärtnereien die Pflanze öffentlich anbieten | umgesetzt |
| Hilfeseite | umgesetzt |
| Export der Liste (CSV, JSON, YAML) | offen |
| QR-Code im Portrait | offen |
| Gärtnereisuche (Verzeichnis der teilnehmenden Gärtnereien) | offen |

### 3.2 Pflanzenerfassung (Editoren)

| Anforderung | Stand |
|---|---|
| Eingabe mit immer sichtbaren Stammdaten (Gattung ist Pflicht) und aufklappbaren Bereichen, Badge mit Anzahl ausgefüllter Felder | umgesetzt |
| Botanischer Name, Webname und Matchcode werden automatisch erzeugt | umgesetzt |
| Dublettenprüfung über den botanischen Namen | umgesetzt |
| Warnung bei ungespeicherten Änderungen | umgesetzt |
| Bearbeiten bestehender Pflanzen über die Liste (Stift) | umgesetzt |
| Übernahme des Altbestands (rund 1500 Pflanzen) | umgesetzt |
| Grosser Datenimport und Korrektur der Sortennamen mit fehlendem schliessendem Apostroph | offen |

### 3.3 Gärtnereien

| Anforderung | Stand |
|---|---|
| Registrierung mit Zugangscode, Profil mit Betriebsdaten und Bio-Angaben | umgesetzt |
| Sortiment: Pflanzen aus den Stammdaten aufnehmen und entfernen | umgesetzt |
| Bestand je Pflanze: Topfgrösse, Menge, Status, Preis, Standort, öffentlich | umgesetzt |
| Standorte (hierarchisch) verwalten | umgesetzt |
| Export des eigenen Bestands als CSV (gesamter Bestand) | umgesetzt |
| Export mit Auswahlkriterien (Kategorie, Status, öffentlich) und als PDF | offen |
| oXs-Felder am Bestand (Angebotstyp, Vermehrung, Herkunft, Bio) | teilweise: Felder werden erfasst, Meldung an organicxseeds.ch ist offen |
| Öffentliche Verfügbarkeit | teilweise: Portrait zeigt Gärtnerei mit Link, Topf, Menge und Preis noch nicht |
| Eigene Kopie der Stammdaten pro Gärtnerei mit Änderungsvorschlägen und Aktualisierung aus dem Master | offen (geplant) |
| Export in Webshop-Formate (Bagisto, Shopware, Gambio, ...) | offen |
| Etikettendruck (Stecketiketten, Labeldrucker, QR-Code) | offen |

### 3.4 Anfrageliste (Gärtnereien und Planer)

| Anforderung | Stand |
|---|---|
| Mehrere benannte Anfragen pro Konto, gespeichert | umgesetzt |
| Pflanzen hinzufügen: Suche in den Stammdaten (mehrere auf einmal), freie Eingabe von Gattung, Art und Sorte, Liste einfügen (eine Pflanze pro Zeile, Duplikate werden gemeldet) | umgesetzt |
| Bemerkung pro Zeile, Zeilen entfernen, Anfrage löschen | umgesetzt |
| Hinweis "Laut PDB verfügbar bei" für Pflanzen mit öffentlicher Verfügbarkeit | umgesetzt |
| Export als Excel (Auswahlfelder für die Antwort der Anbieter, Absenderzeile) und als CSV | umgesetzt |
| Feinschliff der Spalten und Auswahlwerte für Anbieter (Verfügbar, Qualität, Form, Menge, Preis) mit den Anwendern | offen |
| Anfrage-ID für den Rückweg und Hochladen der beantworteten Datei | offen |
| PDF- oder Druckansicht | offen |

Die PDB versendet keine Anfragen selbst. Die Datei wird von der Gärtnerei per E-Mail weitergegeben.

### 3.5 Spätere Phasen

Netzwerk-Sync zwischen Gärtnereien (Verfügbarkeiten), Änderungshistorie, öffentliche REST-Schnittstelle (`/api/plants`), PDF-Kataloge, Video-Anleitungen.

## 4. Nicht-funktionale Anforderungen

### 4.1 Usability

- Einfachheit vor Funktionsumfang, selbsterklärend, kein Handbuch für Grundfunktionen
- Fehlervermeidung: Dublettenprüfung, Pflichtfelder klar markiert, Warnung bei ungespeicherten Änderungen
- Menü und Seiten richten sich nach der Rolle: Wer eine Funktion nicht nutzen darf, sieht sie nicht

### 4.2 Design

- Schwarze Schrift statt Grau, keine Kursivschrift, überall Liberation Sans
- Grün #4B8223 für Primärelemente, minimalistisch, keine Animationen
- Lesbarkeit geht vor Ästhetik

### 4.3 Performance (Richtwerte, systematische Messung offen)

- Suche unter einer Sekunde bei 1500 Pflanzen, Seitenaufbau unter drei Sekunden
- Skalierung auf 50 000 Pflanzen ohne Umbau

### 4.4 Betrieb

- Webhosting mit PHP 8.3 oder neuer, Erweiterungen `sqlite3`, `zip`, `mbstring`, `gd`
- Lokaler Betrieb mit XAMPP für Entwicklung und Test. Eine USB-Stick-Variante (Version 1.1) ist nicht geprüft.
- Aktualisierungen werden als Nachpakete eingespielt, getrennt von den Konten und Datenbanken des Betriebs

### 4.5 Datenschutz

- Keine Analyse- oder Tracking-Dienste, keine fremden Server für Schriften, Icons oder Skripte
- Cookie nur bei Anmeldung, Anmeldeseite oder Zugangscode, dazu die Hell/Dunkel-Einstellung, die nur im Browser gespeichert wird
- Serverprotokolle liegen beim Hoster, Details in der Datenschutzerklärung

### 4.6 Wartbarkeit

- Open Source auf GitHub, Standardwerkzeuge (Grav, SQLite, htmx), kein Build-Prozess
- So wenig eigener Code wie möglich, Grav-Funktionen bevorzugt
- Dokumentation: README, dieses Pflichtenheft, Schema der Stammdaten-Datenbank, Kommentare im Code

## 5. Schnittstellen

| Richtung | Format | Stand |
|---|---|---|
| Export Bestand | CSV (Semikolon, UTF-8) | umgesetzt |
| Export Anfrageliste | XLSX, CSV | umgesetzt |
| Export Liste und Bestand | JSON, YAML, PDF | offen |
| Export nach organicxseeds.ch (oXs) | Format noch offen | offen |
| Import Stammdaten | CSV, JSON | offen |
| Webshops | Bagisto, Shopware, Gambio, andere | offen |
| Öffentliche REST-Schnittstelle | `/api/plants`, `/api/plants/{id}` | offen |

## 6. Datenfelder

Die vollständige Liste steht im Schema `database/plants_schema.sql`, die fachliche Beschreibung in `docs/`. Die Bereiche der Pflanzenerfassung: Stammdaten (Gattung, Art, Unterart, Sorte, Ökotyp, Kategorie), Namen (DE, EN, FR, IT, Synonyme), Herkunft, Standort, Blüte, Blatt, Frucht, Wurzel, Wuchs, Gärtnereidaten (Vermehrung, Keimung, Entwicklungsdauer) und Verwendung. Bestand und Verfügbarkeit liegen in den Konto-Datenbanken.

## 7. Benutzerszenarien

### 7.1 Editor erfasst eine neue Pflanze

1. Öffnet die Eingabe und füllt die Stammdaten aus (Gattung "Rosa", Art "damascena", Sorte "Rose de Resht").
2. Der botanische Name wird erzeugt, die Dublettenprüfung läuft automatisch.
3. Klappt die Bereiche Blüte und Herkunft auf und trägt Daten ein, das Badge zeigt den Fortschritt.
4. Speichert, die Pflanze erscheint in der öffentlichen Liste.

### 7.2 Gärtnerei erstellt eine Anfrageliste

1. Meldet sich an und öffnet die Anfrageliste, legt eine neue Anfrage an.
2. Sucht Pflanzen in den Stammdaten, gibt fehlende Pflanzen frei ein oder fügt eine Liste ein.
3. Sieht bei Pflanzen mit öffentlicher Verfügbarkeit, welche Gärtnerei sie anbietet.
4. Exportiert die Anfrage als Excel-Datei und schickt sie per E-Mail an Anbieter.
5. Die Anbieter beantworten die Datei mit Auswahlfeldern und schicken sie zurück.

### 7.3 Gartenplaner sucht Stauden

1. Öffnet die Liste, sucht "Lavendel", filtert nach Kategorie, sortiert nach Winterhärte.
2. Öffnet das Portrait und sieht, welche Gärtnereien die Pflanze anbieten.
3. Meldet sich als Planer an und setzt die Pflanzen auf eine Anfrageliste.

## 8. Betrieb und Installation

- Die Installation steht im [README](README.md): Grav 2.2, Ordner `user/` kopieren, Beispiel-Einstellungen übernehmen, Zugangscode setzen, Schema der Stammdaten-Datenbank einspielen.
- Live-Betrieb bei einem Schweizer Hoster mit HTTPS. Updates werden als Pakete mit den geänderten Dateien eingespielt, danach Cache leeren.
- Konten (`user/accounts/`) und Datenbanken (`user/data/`) gehören nie ins Repository und dürfen vom Webserver nicht ausgeliefert werden.

## 9. Testing

- Manuell mit Testkonten für jede Rolle (Gärtnerei, Planer, Editor, Admin), jeweils mit Prüfung der gesperrten Seiten und Endpunkte
- Syntaxprüfung aller PHP-Dateien und Aufrufe der API-Funktionen auf der Kommandozeile
- Excel-Export wird mit einer Tabellenkalkulation geöffnet und kontrolliert
- Automatisierte Tests (Datenbankabfragen, API, Oberfläche): offen

## 10. Planung

| Phase | Inhalt | Stand |
|---|---|---|
| 1 | Pflanzenliste und Portrait, Erfassung für Editoren, Konten, Bestand, Anfrageliste für den Zukauf ohne Bio-Material, Datenschutz | erreicht (Stand November 2026) |
| 2 | Export mit Auswahlkriterien, Rückweg der Anfrageliste, eigene Kopie der Stammdaten mit Vorschlägen, Stammdaten-Import, oXs-Meldung | offen |
| 3 | Etikettendruck, Netzwerk-Sync, Webshop-Schnittstellen, öffentliche REST-Schnittstelle | offen |

## 11. Risiken und Lösungen

| Risiko | Wirkung | Lösung |
|---|---|---|
| Grav 2.x ist jung, Updates ändern Verhalten | mittel | Kind-Theme, wenig eigener Code, Test nach jedem Update |
| Adress- und Kontaktdaten in Konten | hoch | eigene Datenbankdatei je Betrieb, keine Drittanbieter, Datenschutzerklärung |
| Zugangscode wird weitergegeben | mittel | Hash, Sperre nach Fehlversuchen, Konten durch den Admin prüfbar |
| Gärtnereien pflegen dieselben Pflanzen doppelt | mittel | eigene Kopie mit Vorschlägen und Abgleich (Phase 2) |
| Datenqualität der Sortennamen | niedrig | Korrekturskript, zuerst auf einer Kopie |
| Etikettendruck-Treiber | mittel | Phase 3 |

## 12. Erfolgskriterien

**Beta ist erfolgreich, wenn**

1. eine Gärtnerei sich ohne Anleitung registriert und ihr Sortiment erfasst,
2. eine Anfrageliste in wenigen Minuten erstellt und exportiert ist,
3. die Suche in der Liste in einer Sekunde antwortet,
4. Anbieter die Anfrage allein durch Anklicken beantworten können,
5. mehrere Gärtnereien die PDB ausprobieren.

**Langfristig**

1. Zehn oder mehr Gärtnereien nutzen die PDB, die Stammdaten werden gemeinsam gepflegt.
2. Eine aktive Open-Source-Gemeinschaft entsteht.
3. Die PDB ist an bestehende Bio-Systeme angebunden (zum Beispiel organicxseeds.ch).

## 13. Open-Source-Strategie

- Lizenz MIT, kommerzielle Nutzung erlaubt, Namensnennung erforderlich
- Repository: https://github.com/sambarix/pflanzendatenbank (Aufbau siehe README)
- Issues für Fehlerberichte, Pull Requests willkommen
- Der Quellcode wird gemeinsam mit KI-Assistenten geschrieben und von Peter Müller getestet

## 14. Kontakt und Beteiligte

**Projektleitung:** Peter Müller (oMioBio), [pm@omiobio.ch](mailto:pm@omiobio.ch)

**Beteiligte:** Peter Müller (Challenge Owner), KI-Assistenten (Claude von Anthropic, Le Chat von Mistral, OLMo, DeepSeek und weitere), Open Farming Hackday Community

*Ende des Pflichtenhefts, Version 1.2, 14. November 2026*

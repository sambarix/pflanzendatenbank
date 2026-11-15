# Pflanzendatenbank (PDB)

Offene Pflanzendatenbank für Knospe-zertifizierte Gärtnereien (Bio Suisse) und für alle, die wissen wollen, wo sie Biopflanzen finden. Die PDB ist eine Grav-Website mit SQLite-Datenbanken, läuft auf normalem Webhosting und braucht keinen Build-Schritt. Sie ist in der Beta-Phase und läuft live auf [pdb.biosoft.ch](https://pdb.biosoft.ch).

## Funktionen

**Öffentlich, ohne Anmeldung**
- Pflanzenliste mit Live-Suche, Kategorie-Filter und Spaltenauswahl
- Pflanzenportrait mit allen Angaben und dem Hinweis, welche Gärtnereien die Pflanze öffentlich anbieten

**Gärtnereien** (Konto mit eigener Datenbank)
- Sortiment und Bestand verwalten (Topfgrösse, Menge, Preis, Standort, oXs-Felder)
- Export des eigenen Bestands als CSV
- Anfragelisten (siehe unten)

**Gartenplaner und Gartenbau** (Konto ohne eigenen Bestand)
- Anfragelisten erstellen

**Anfrageliste**: Wer Pflanzen oder Vermehrungsmaterial zukaufen will und nicht weiss, ob es sie in Bioqualität gibt, stellt eine Liste zusammen. Pflanzen werden aus der Datenbank gewählt, frei eingegeben oder als Liste eingefügt. Die Liste wird als Excel-Datei mit Auswahlfeldern für die Antwort der Anbieter oder als CSV exportiert. Ist eine Pflanze bei einer Gärtnerei öffentlich verfügbar, erscheint der Hinweis schon bei der Listenerstellung. Die PDB versendet nichts selbst, die Datei wird per E-Mail weitergegeben.

**Editoren** bearbeiten die öffentlichen Stammdaten (Eingabe von Pflanzen mit allen Aufklapp-Bereichen).

**Konten** gibt es nur mit Zugangscode. Die Rollen laufen über Grav-Gruppen: `user` (Gärtnerei), `planer`, `editor`; der technische Admin arbeitet im Grav-Backend. Besucher ohne Konto bekommen kein Cookie, und es werden keine Inhalte von fremden Servern geladen.

**Noch nicht gebaut**: Meldungen an organicxseeds.ch, Etikettendruck, lokale Kopie von Stammdaten mit Änderungsvorschlägen, Hochladen der beantworteten Anfrageliste, PDF-Ausgabe. Der Stand aller Anforderungen steht im [Pflichtenheft](Pflichtenheft.md).

## Technik

- [Grav](https://getgrav.org) 2.2 (PHP 8.3 oder neuer, Erweiterungen `sqlite3`, `zip`, `mbstring`, `gd`)
- Kind-Theme `pdb-theme` von Quark 2, Seiten dynamisch per [htmx](https://htmx.org)
- Plugin `pdb-api`: stellt die Endpunkte `/pdb-api/<name>` über Gravs eigenen Request-Zyklus bereit. Die Dateien in `user/api/` sind reine Funktionsbibliotheken und nicht direkt per HTTP erreichbar.
- SQLite: eine zentrale Stammdaten-Datenbank `plants.db` und pro Konto eine eigene Datei `user/data/sqlite/<konto>.db`. Der Datenbankpfad kommt immer aus dem Login, nie aus dem Browser.
- Excel-Export ohne Zusatzbibliothek (OOXML über `ZipArchive`)

## Installation

1. Grav 2.2 installieren. Das Theme `quark2` und das Plugin `login` (mit `form`, `email`, `shortcode-core`) müssen vorhanden sein, `admin2` ist für das Backend sinnvoll.
2. Den Ordner `user/` aus diesem Repository in das `user/`-Verzeichnis der Grav-Installation kopieren.
3. Die Einstellungen aus `config-beispiele/` in `user/config/` übernehmen: `system.yaml` (Theme, Startseite, `session.lazy`) und `site.yaml` in die bestehenden Dateien einarbeiten, `plugins/login.yaml` und `plugins/pdb-api.yaml` nach `user/config/plugins/` legen.
4. Zugangscode für die Registrierung festlegen und nur als Hash eintragen:
   `php -r "echo password_hash('IHR-CODE', PASSWORD_DEFAULT), PHP_EOL;"` und das Ergebnis als `invite_hash` in `user/config/plugins/pdb-api.yaml`.
5. Stammdaten-Datenbank anlegen: `database/plants_schema.sql` in eine neue Datei `user/data/sqlite/plants.db` einspielen, Stammdaten importieren und danach den Suchindex neu aufbauen (`INSERT INTO plants_search(plants_search) VALUES('rebuild');`). Die Stammdaten selbst (Stand 15.11.2026: 4332 Pflanzen, 57 Kategorien) sind noch nicht Teil dieses Repositorys.
6. Nach der Installation prüfen, dass `user/data/` und `user/accounts/` nicht per HTTP erreichbar sind (zum Beispiel muss `/user/data/sqlite/plants.db` mit 403 antworten).
7. Betreiberangaben anpassen: `user/pages/11.impressum` und `user/pages/12.datenschutz` (beide sind Vorlagen mit Platzhaltern), `user/pages/01.home` und die Fusszeile `user/themes/pdb-theme/templates/partials/footer.html.twig`.

Die Datenbank einer Gärtnerei legt die Registrierung selbst an. Wer das Recht `site.pdb_editor` (Stammdaten bearbeiten) bekommen soll, wird im Backend der Gruppe `editor` zugewiesen.

## Aufbau des Repositorys

```
user/                  Kopiervorlage für das user/-Verzeichnis von Grav
  api/                 Endpunkte (liste, portrait, eingabe, bestand, standorte, export, anfrage, register, ...)
  plugins/pdb-api/     Plugin mit Routen und den Rechten site.pdb_editor und site.pdb_nursery
  themes/pdb-theme/    Kind-Theme: Templates, CSS, JS, lokale Schriften und Icons
  pages/               Seiten (Impressum und Datenschutz als Vorlagen)
  config/              groups.yaml und Theme-Konfiguration
config-beispiele/      Einstellungen zum Übernehmen
database/              plants_schema.sql (aktuelles Schema der Stammdaten-Datenbank)
docs/                  Entwurfsdokumente (Stand Anfang 2025, teils überholt), docs/archiv: frühere Pflichtenhefte und das erste Datenbankschema
Pflichtenheft.md       Anforderungen und Umsetzungsstand
THIRD-PARTY.md         Fremdkomponenten und Lizenzen
```

Das erste Datenbankschema vom Februar 2026 (`create_database_v1.0_2026-02.sql`) und die zugehörige leere Beispiel-Datenbank (`gardener_ai_leer_v1.0_2026-02.db`) liegen in `docs/archiv/` und entsprechen nicht mehr dem heutigen Schema.

## Entstehung

Konzept, Anforderungen und Fachwissen stammen von Peter Müller (oMioBio). Der Quellcode wird gemeinsam mit KI-Assistenten geschrieben (vor allem Claude von Anthropic) und von Peter Müller getestet.

## Mitmachen und Kontakt

Rückmeldungen, Fehlerberichte und Pull Requests sind willkommen, am einfachsten als Issue hier auf GitHub. Kontakt: [pm@omiobio.ch](mailto:pm@omiobio.ch).

## Lizenz

MIT, siehe [LICENSE](LICENSE). Fremdkomponenten: [THIRD-PARTY.md](THIRD-PARTY.md).

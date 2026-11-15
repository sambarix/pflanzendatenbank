---
title: Mein Profil
menu: Profil
visible: true
cache_control: private, no-cache, must-revalidate
access:
    site.login: true

form:
  fields:

    account_section:
      type: section
      title: Konto
      underline: true

    username:
      type: text
      label: Benutzername
      readonly: true
      disabled: true
      help: 'Der Benutzername kann nicht geändert werden.'

    email:
      type: email
      label: E-Mail Adresse
      placeholder: ihre@email.ch
      validate:
        required: true
        message: Bitte eine gültige E-Mail Adresse eingeben.

    fullname:
      type: text
      label: Name / Betriebsname
      placeholder: Bio Gärtnerei Muster

    current_password:
      type: current_password
      label: Aktuelles Passwort
      autocomplete: current-password
      help: 'Nur nötig, wenn Sie das Passwort oder die E-Mail-Adresse ändern.'
      validate:
        type: password

    password:
      type: password
      label: Neues Passwort
      autocomplete: new-password
      help: 'Nur ausfüllen wenn Sie das Passwort ändern möchten.'
      validate:
        message: 'Mindestens 8 Zeichen, Gross- und Kleinbuchstaben, eine Zahl.'
        config-pattern@: system.pwd_regex

    password_confirm:
      type: password
      label: Neues Passwort wiederholen
      autocomplete: new-password

    betrieb_section:
      type: section
      title: Betriebsangaben
      underline: true

    betrieb_strasse:
      type: text
      label: Strasse und Hausnummer
      placeholder: Dorfstrasse 12

    betrieb_plz:
      type: text
      label: PLZ
      placeholder: '6000'
      validate:
        pattern: '^\d{4,5}$'
        message: Bitte eine gültige Postleitzahl eingeben.

    betrieb_ort:
      type: text
      label: Ort
      placeholder: Luzern

    betrieb_land:
      type: select
      label: Land
      default: CH
      options:
        CH: Schweiz
        DE: Deutschland
        AT: Österreich
        LI: Liechtenstein

    betrieb_telefon:
      type: text
      label: Telefon
      placeholder: '+41 41 123 45 67'

    betrieb_url:
      type: text
      label: Webseite
      placeholder: 'https://www.meine-gaertnerei.ch'

    bio_section:
      type: section
      title: Bio-Zertifizierung
      underline: true

    bio_zertifizierung:
      type: text
      label: Bio-Zertifizierungsnummer
      placeholder: 'CH-Bio-006'
      help: 'z.B. CH-Bio-006 (Zertifizierungsstelle)'

    bio_betriebsnummer:
      type: text
      label: Bio-Betriebsnummer
      placeholder: '11502'
      help: 'Ihre Bio-Suisse Betriebsnummer'

    betrieb_notiz:
      type: textarea
      label: Notiz / Beschreibung
      placeholder: 'Kurze Beschreibung des Betriebs (optional)'
      rows: 3

  buttons:
    -
      type: submit
      value: Speichern
    -
      type: reset
      value: Zurücksetzen

  process:
    update_user: true
    message: Profil wurde gespeichert.

---

# Mein Profil

Hier können Sie Ihre Kontodaten und Betriebsangaben verwalten.

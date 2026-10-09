# Eigenständiger Betrieb

Das CMS läuft in zwei Betriebsarten. **Standard ist unverändert der bisherige Betrieb mit Anbindung an das AnMaCha Control Center.** Der eigenständige Betrieb ist eine Einstellung, die nur zusätzlich greift; bestehende Daten (`site.json`, `news.json`, Seiten, Menüs, Widgets, Marken, Apps …) werden dafür nie umgeschrieben.

## Betriebsmodi

| | Mit Control Center (Standard) | Eigenständig |
|---|---|---|
| Anmeldung | lokaler Zugang **oder** Control-Center-Token | nur lokaler Zugang |
| Anfragen an `…/control/cron.php` | ja (Berechtigung, Altdaten, News-Rückfall) | **keine** |
| Studiomail/Voicemail des KI-Assistenten | über das Control Center | „nicht verfügbar im eigenständigen Betrieb“ |
| Altdaten-Übernahme | möglich | nicht verfügbar |

Die Einstellung steht in `cms/data/system.local.json` (wird vom CMS verwaltet, nicht ins Repository einchecken):

```json
{ "control_center": false, "language": "de", "timezone": "Europe/Berlin", "installed_at": "2026-01-01T12:00:00+01:00" }
```

Fehlt die Datei oder das Feld `control_center`, gilt der bisherige Betrieb. Umschalten: Verwaltung → System → **Betrieb & Produkt** (nur Administratoren). Zum Ausschalten der Anbindung muss ein lokaler Administrator unter „Redakteure“ existieren, sonst lehnt das CMS ab (Aussperr-Schutz). Zurückschalten ist jederzeit möglich.

### Wo das Control Center eingebunden ist

Alle diese Stellen beachten den Betriebsmodus:

- `cms/api.php`: `rrw_auth` (Token-Prüfung, HTTP-Berechtigung `radio_cms_access`), `rrw_control_center_json` (Altdaten-Export), `rrw_control_center_db_rows` (lokaler Lesezugriff auf die Rechte-Datenbank), einmalige Migration beim ersten Aufruf, `import_legacy`, News-Rückfall (`news_public_legacy_fallback`), Website-Zustand (`site_health`, Selbstaufruf-Probe).
- `cms/lib/assistant.php`: `studiomail_send`, `voicemsg_send`.
- Oberfläche (`cms/index.php`, `cms/assets/cms-app.js`): Anmeldeknopf „Mit … anmelden“, Rücklink im Kopfbereich, Stylesheet `/control/shared.css`.

Nicht Teil der Verwaltung und von dieser Einstellung **nicht** berührt (öffentliche Portalseite): `assets/js/portal.js` (eingebettete Control-Center-Widgets, Voting, Wunschliste), `assets/js/assistant.js` (`voicemsg_info`), `index.html` (Voting-iframe). Diese Teile erscheinen in einer eigenständigen Installation nur, wenn ihre Widgets/Bereiche im CMS aktiv sind.

## Installation (Ersteinrichtung)

1. Dateien auf den Server legen, PHP 8.1 oder neuer mit `mbstring` (für Datenbankspiegel zusätzlich `pdo_sqlite`, `pdo_mysql` oder `pdo_pgsql`). Der Ordner `cms/data/` muss beschreibbar sein.
2. `https://deine-domain/cms/` aufrufen. Auf einer frischen Installation führt das CMS automatisch zu `cms/install.php`.
3. Der Assistent fragt Website-Name, Sprache und Zeitzone, das Administratorkonto (mindestens 10 Zeichen mit Buchstaben und Ziffern/Sonderzeichen), optional die Datenbank (SQLite ist vorausgewählt; MySQL, MariaDB und PostgreSQL mit **Verbindungstest**, auf Wunsch wird eine fehlende Datenbank angelegt) und optional einen Beispielbeitrag.
4. Nach „Einrichtung abschließen“ ist die Installation eigenständig (`control_center: false`), `cms/data/install.lock` sperrt den Assistenten. Danach ist `install.php` nicht mehr erreichbar und verarbeitet keine Eingaben.

Wann erscheint der Assistent? Nur wenn **alles** zutrifft: keine Sperrdatei, keine `system.local.json`, kein lokaler Zugang, kein Ordner `control/`, `site.json` im ausgelieferten Ausgangszustand (ohne `_meta`) bzw. fehlend, keine Beiträge, kein Aktivitätsprotokoll, keine Kommentare. Im Zweifel (defekte Dateien) erscheint er nicht. Bestehende Installationen sind dadurch nie betroffen.

Sicherheit: CSRF-Schutz (Cookie plus Formularfeld, `SameSite=Strict`), Versuchsbegrenzung je Gegenstelle, Passwortregeln, Passwörter werden nie zurück ins Formular geschrieben oder protokolliert, Zugangsdaten stehen nur gehasht in `local-auth.local.php` (Rechte 0600), atomare Sperrdatei gegen Doppelaufrufe, bei Fehlern werden Zugang, Konfiguration und Inhalte auf den Stand vorher zurückgesetzt. Bis zum Abschluss sollte die Seite nur über HTTPS und möglichst nicht öffentlich erreichbar sein. Auf einer frischen Installation ist außerdem die offene Kontoanlage der Anmeldemaske (`local_auth_setup`) abgeschaltet; das Konto entsteht nur im Assistenten.

Erneut einrichten: Nur durch bewusstes Löschen von `cms/data/install.lock` **und** der Zugangs-/Betriebsdateien; das ist nur auf einer wirklich leeren Installation sinnvoll.

## Produktname ändern

Alle sichtbaren Bezeichnungen der Verwaltung kommen zentral aus `cms/lib/product.php`. In ElvadoPress liefert `cms/lib/product.default.json` die neutralen Standardwerte; `cms/data/product.json` kann sie installationsbezogen überschreiben. Die RicoReWi-Fallbacks in `product.php` dienen ausschließlich älteren integrierten Installationen. Ändern: Verwaltung → System → **Betrieb & Produkt** → Produktname (oder die Datei von Hand anlegen):

```json
{ "name": "MeinCMS", "logo": "/assets/logo.png", "control_center": "Mein Dashboard" }
```

Mit nur `name` folgen Fenstertitel, Überschrift, Anmeldetext und die Generator-Angabe im RSS automatisch (Überschrift: „MeinCMS Verwaltung“). Einzeln überschreibbar: `slug`, `logo`, `title`, `heading`, `access_name`, `generator`, `control_center`. Leere Felder bedeuten „Standard“; „Standard wiederherstellen“ entfernt die Datei. Funktionen für Code: `rrw_product_name()`, `rrw_product_slug()`, `rrw_product_logo()`, `rrw_product_title()`, `rrw_product_heading()`, `rrw_product_generator()`, `rrw_product_control_center()`.

Noch nicht über diese Einstellung geführt: Markdown-Dokumentation unter `cms/docs/*.md`, Theme-Kopfzeilen, Texte der WordPress-Schicht und Inhalte, die ausdrücklich zur Marke der Website gehören (Rechtstexte, Beispielseiten).

## Version und Update

`cms/VERSION` enthält die CMS-Version; sie steht in der Verwaltung unter **Betrieb & Produkt**. Dort lässt sich auf Knopfdruck eine **Prüfsumme** (SHA-256 über Code und Oberfläche, ohne Daten, Medien, Themes und Plugins) berechnen und mit einer Referenz vergleichen.

Das CMS überschreibt seinen Code **nie** selbst. Empfohlener Ablauf für ein Update:

1. Backup erstellen (siehe unten) und `cms/data/` sichern.
2. Neue Dateien über die vorhandenen kopieren; `cms/data/`, `cms/media/`, `cms/wp-content/` und `*.local.*` nicht überschreiben.
3. Version und Prüfsumme in der Verwaltung mit der Referenz der neuen Ausgabe vergleichen.

Ein automatischer Updater ist bewusst nicht enthalten.

## Datensicherung

- **Verwaltung → System → Backups**: ZIP mit `site.json`, `news.json`, Kommentaren, Revisionen, Themes, Plugins, Inhalten und (optional) Medien.
- Zusätzlich von Hand sichern: `cms/data/` (enthält `local-auth.local.php`, `system.local.json`, `product.json`, `database.local.php`, Protokolle) und, falls genutzt, die SQLite-Datei (`cms/data/*.sqlite`). Diese Dateien enthalten Zugangsdaten bzw. Hashes: sicher aufbewahren, nicht ins Repository.
- Wiederherstellen: Backup über die Verwaltung einspielen oder Dateien zurückkopieren; die Sperrdatei `install.lock` muss mitgesichert werden, damit der Assistent nicht erneut erscheint.
- Betriebs- und Produktdateien werden atomar geschrieben (temporäre Datei, dann umbenennen).

Siehe auch [WORDPRESS.md](WORDPRESS.md) (WordPress-Kompatibilitätsschicht) und [DATABASE.md](DATABASE.md) (Datenbanktreiber).

## Eigenständiges Paket (ohne RicoReWi-Inhalte)

Alles, was zum RicoReWi-Radioportal gehört, ist ein **Design-Paket** (`"pack": "ricorewi-radio"` in der `theme.json` der sechs Portal-Themes; Code in `cms/lib/pack.php`):
Radio-Widgets, Portal-Seiten, Alexa-Skill, Radioverzeichnis, Sender-Netzwerk, Apps des Herstellers, Partnerseite, Rechtstexte-Vorlagen und die Marken-Voreinstellungen.

- **Pakete:** ElvadoPress liefert kein Design-Paket aus. Bei der Einrichtung entstehen keine RicoReWi-Inhalte: kein Favoriten-Menü, keine Partnerseite, keine Rechtstexte-Vorlagen, nur das Beitrags-Widget, eine einzige Marke mit dem Namen der Website, neutrale SEO-Angaben.
- **Bauen:** `php scripts/build-standalone.php <Zielordner> [--zip=<Datei.zip>]` nimmt nur verfolgte Dateien aus `cms/`, lässt die Portal-Themes, den RicoReWi-Skill-Katalog, Daten und Marken-Doku weg und legt `index.php`/`.htaccess` für die WordPress-Theme-Auslieferung dazu. Der Workflow „Standalone CMS“ prüft das bei jeder Änderung an `cms/` mit einer echten Testinstallation (`scripts/test-standalone-build.php`) und veröffentlicht bei einem Tag `cms-v<Version>` das ZIP als Release.
- **Einrichtung:** Der Assistent schaltet das neutrale Theme `rrw-classic` ein; die Website erscheint sofort, weitere Themes installierst du im CMS.

### Verbundene Dienste (eigene Dienste)

Unter **System → Verbundene Dienste** trägst du die Dienste ein, die zu deiner Website gehören (Cloud, Podcast, Livestream, Webmail, Statistik, Shop, Wiki, eigene Schnittstellen). Pro Dienst: Name, Adresse (`https://…` oder ein Pfad auf der Website), Art, Notiz und die Option „Erreichbarkeit prüfen“. Mit Vorlagen legst du Einträge schnell an, Reihenfolge und Links lassen sich ändern.

„Status prüfen“ fragt die Dienste auf dem Server parallel ab (nur Administratoren, höchstens 120 Prüfungen pro Stunde). Erreichbar sind Antworten mit 2xx/3xx sowie 401/403 (Zugriff geschützt); Weiterleitungen werden nicht verfolgt. Geprüft werden nur öffentliche Adressen – lokale und private Adressen zeigt das CMS als „nicht prüfbar“. Bis zu 30 Dienste; in der öffentlichen Demo ist die Prüfung gesperrt. Die Liste liegt in `cms/data/site.json` (Abschnitt `services.items`).

### App-Erweiterungen (ohne RicoReWi-Inhalte)

Funktionen, die zu den eigenen Apps gehören, bleiben im eigenständigen CMS erhalten – ohne RicoReWi-Inhalte:

- **Alexa-Skill (Baukasten):** Name und Aufrufname stammen aus deinen Einstellungen (Standard: Website-Name), die Sender legst du selbst an (laut.fm-Kennung); Sprachmodell, Skill-Angaben, README und Store-Texte werden daraus erzeugt (`cms/standalone/alexa-skill/` enthält die neutralen Vorlagen, das Paket ersetzt damit den RicoReWi-Katalog).
- Sichtbar sind sie in der Verwaltung unter **Apps & Kanäle**; mit dem RicoReWi-Paket erscheinen sie nur, solange das RicoReWi-Design ausgeliefert wird (`data-pack-app`).
- **KI-Assistent (frei konfigurierbar, unabhängig vom Radio):** ein Chat-Fenster unten rechts auf deiner Website (bei WordPress-Themes, ohne Cookies, mit Datenschutz-Hinweis). Unter *Einstellungen → KI-Assistent* wählst du:
  - **Art:** *Website-Assistent* (Standard: antwortet aus deinen Beiträgen und dem hinterlegten Wissen, ohne Radio-Bezug) oder *Radio-Assistent* (zusätzlich deine laut.fm-Sender, laufender Titel, Sendeplan). Name, Begrüßung, Wissen, zusätzliche Anweisungen, Antwortlänge und Kreativität (Temperatur) sind einstellbar, ebenso die Live-Recherche (Wetter, Schlagzeilen, Wikipedia).
  - **Anbieter und Modelle:** kostenlose Dienste sind vorbereitet; dazu kommen eigene OpenAI-kompatible Anbieter über Vorlagen (OpenAI, Anthropic Claude, DeepSeek, Together AI, xAI Grok, Perplexity, Fireworks AI, Ollama und LM Studio lokal auf dem Server) oder mit eigener Adresse. Pro Anbieter gibt es ein Hauptmodell und bis zu 20 Reservemodelle; „Modelle laden“ fragt den Anbieter nach seiner Liste, einzelne Modelle lassen sich testen, als Hauptmodell setzen und umsortieren. Die Reihenfolge ist *manuell* (genau wie eingestellt, Standard) oder *automatisch* (kostenlose zuerst, schnelle Modelle vorn). Fällt ein Anbieter aus, übernimmt der nächste; antwortet keiner, antwortet der Assistent direkt aus den Inhalten der Website.
  - **Lokale Modelle:** `http://localhost:…` (Ollama, LM Studio) ist erlaubt, andere Adressen müssen `https` sein. API-Keys bleiben auf dem Server und werden nie an Besucher ausgeliefert. Studiomail und Voicemail des Herstellers gibt es im eigenständigen Betrieb nicht.

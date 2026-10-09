# Betrieb und Installation

ElvadoPress ist ein eigenständiges CMS: Die Anmeldung läuft ausschließlich über den lokalen Zugang (`cms/data/local-auth.local.php`, Sitzungstokens mit Präfix `local_`). Es gibt keine Anbindung an externe Dashboards oder Anmeldedienste und keine Anfragen an Fremdsysteme für Berechtigungen.

Betriebseinstellungen stehen in `cms/data/system.local.json` (wird vom CMS verwaltet, nicht ins Repository einchecken):

```json
{ "language": "de", "timezone": "Europe/Berlin", "installed_at": "2026-01-01T12:00:00+01:00" }
```

Ändern: Verwaltung → System → **Betrieb & Produkt** (nur Administratoren).

## Installation (Ersteinrichtung)

1. Dateien auf den Server legen, PHP 8.1 oder neuer mit `mbstring` (für Datenbankspiegel zusätzlich `pdo_sqlite`, `pdo_mysql` oder `pdo_pgsql`). Der Ordner `cms/data/` muss beschreibbar sein.
2. `https://deine-domain/cms/` aufrufen. Auf einer frischen Installation führt das CMS automatisch zu `cms/install.php`.
3. Der Assistent fragt Website-Name, Sprache und Zeitzone, das Administratorkonto (mindestens 10 Zeichen mit Buchstaben und Ziffern/Sonderzeichen), optional die Datenbank (SQLite ist vorausgewählt; MySQL, MariaDB und PostgreSQL mit **Verbindungstest**, auf Wunsch wird eine fehlende Datenbank angelegt) und optional einen Beispielbeitrag.
4. Nach „Einrichtung abschließen“ legt der Assistent `system.local.json` an, `cms/data/install.lock` sperrt den Assistenten. Danach ist `install.php` nicht mehr erreichbar und verarbeitet keine Eingaben.

Wann erscheint der Assistent? Nur wenn **alles** zutrifft: keine Sperrdatei, keine `system.local.json`, kein lokaler Zugang, `site.json` im ausgelieferten Ausgangszustand (ohne `_meta`) bzw. fehlend, keine Beiträge, kein Aktivitätsprotokoll, keine Kommentare. Im Zweifel (defekte Dateien) erscheint er nicht. Bestehende Installationen sind dadurch nie betroffen.

Sicherheit: CSRF-Schutz (Cookie plus Formularfeld, `SameSite=Strict`), Versuchsbegrenzung je Gegenstelle, Passwortregeln, Passwörter werden nie zurück ins Formular geschrieben oder protokolliert, Zugangsdaten stehen nur gehasht in `local-auth.local.php` (Rechte 0600), atomare Sperrdatei gegen Doppelaufrufe, bei Fehlern werden Zugang, Konfiguration und Inhalte auf den Stand vorher zurückgesetzt. Bis zum Abschluss sollte die Seite nur über HTTPS und möglichst nicht öffentlich erreichbar sein. Auf einer frischen Installation ist außerdem die offene Kontoanlage der Anmeldemaske (`local_auth_setup`) abgeschaltet; das Konto entsteht nur im Assistenten.

Erneut einrichten: Nur durch bewusstes Löschen von `cms/data/install.lock` **und** der Zugangs-/Betriebsdateien; das ist nur auf einer wirklich leeren Installation sinnvoll.

## Produktname ändern

Alle sichtbaren Bezeichnungen der Verwaltung kommen zentral aus `cms/lib/product.php`. In ElvadoPress liefert `cms/lib/product.default.json` die neutralen Standardwerte; `cms/data/product.json` kann sie installationsbezogen überschreiben. Ändern: Verwaltung → System → **Betrieb & Produkt** → Produktname (oder die Datei von Hand anlegen):

```json
{ "name": "MeinCMS", "logo": "/assets/logo.png" }
```

Mit nur `name` folgen Fenstertitel, Überschrift, Anmeldetext und die Generator-Angabe im RSS automatisch (Überschrift: „MeinCMS Verwaltung“). Einzeln überschreibbar: `slug`, `logo`, `title`, `heading`, `access_name`, `generator`. Leere Felder bedeuten „Standard“; „Standard wiederherstellen“ entfernt die Datei. Funktionen für Code: `elvado_product_name()`, `elvado_product_slug()`, `elvado_product_logo()`, `elvado_product_title()`, `elvado_product_heading()`, `elvado_product_generator()`.

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

## Standalone-Paket

- **Inhalte:** Bei der Einrichtung entstehen nur neutrale Inhalte: eine einzige Marke mit dem Namen der Website, das Beitrags-Widget, neutrale SEO-Angaben, kein Favoriten-Menü.
- **Paket:** Ein Tag `v<Version>` löst den Workflow *Release* aus und erzeugt ein Installations-ZIP (`git archive`) samt Prüfsumme.
- **Einrichtung:** Der Assistent schaltet das neutrale Theme `elvado-classic` ein; die Website erscheint sofort, weitere Themes installierst du im CMS.

### Verbundene Dienste (eigene Dienste)

Unter **System → Verbundene Dienste** trägst du die Dienste ein, die zu deiner Website gehören (Cloud, Podcast, Livestream, Webmail, Statistik, Shop, Wiki, eigene Schnittstellen). Pro Dienst: Name, Adresse (`https://…` oder ein Pfad auf der Website), Art, Notiz und die Option „Erreichbarkeit prüfen“. Mit Vorlagen legst du Einträge schnell an, Reihenfolge und Links lassen sich ändern.

„Status prüfen“ fragt die Dienste auf dem Server parallel ab (nur Administratoren, höchstens 120 Prüfungen pro Stunde). Erreichbar sind Antworten mit 2xx/3xx sowie 401/403 (Zugriff geschützt); Weiterleitungen werden nicht verfolgt. Geprüft werden nur öffentliche Adressen – lokale und private Adressen zeigt das CMS als „nicht prüfbar“. Bis zu 30 Dienste; in der öffentlichen Demo ist die Prüfung gesperrt. Die Liste liegt in `cms/data/site.json` (Abschnitt `services.items`).

### App-Erweiterungen

Funktionen, die zu den eigenen Apps gehören, gehören zum CMS:

- **Alexa-Skill (Website-Skill):** Name und Aufrufname stammen aus deinen Einstellungen (Standard: Website-Name). Der Skill liest die neuesten Beiträge deiner Website vor und beantwortet Fragen zu den Themen, die du im CMS anlegst (Öffnungszeiten, Kontakt …); Sprachmodell, Skill-Angaben, README und Store-Texte werden daraus erzeugt (Vorlagen in `cms/lib/alexa-skill/`).
- Sichtbar sind sie in der Verwaltung unter **Apps & Kanäle**.
- **KI-Assistent (frei konfigurierbar):** ein Chat-Fenster unten rechts auf deiner Website (bei WordPress-Themes, ohne Cookies, mit Datenschutz-Hinweis). Unter *Einstellungen → KI-Assistent* wählst du:
  - **Inhalt:** antwortet aus deinen Beiträgen, Seiten und dem hinterlegten Wissen. Name, Begrüßung, Wissen, zusätzliche Anweisungen, Antwortlänge und Kreativität (Temperatur) sind einstellbar, ebenso die Live-Recherche (Wetter, Schlagzeilen, Wikipedia).
  - **Anbieter und Modelle:** kostenlose Dienste sind vorbereitet; dazu kommen eigene OpenAI-kompatible Anbieter über Vorlagen (OpenAI, Anthropic Claude, DeepSeek, Together AI, xAI Grok, Perplexity, Fireworks AI, Ollama und LM Studio lokal auf dem Server) oder mit eigener Adresse. Pro Anbieter gibt es ein Hauptmodell und bis zu 20 Reservemodelle; „Modelle laden“ fragt den Anbieter nach seiner Liste, einzelne Modelle lassen sich testen, als Hauptmodell setzen und umsortieren. Die Reihenfolge ist *manuell* (genau wie eingestellt, Standard) oder *automatisch* (kostenlose zuerst, schnelle Modelle vorn). Fällt ein Anbieter aus, übernimmt der nächste; antwortet keiner, antwortet der Assistent direkt aus den Inhalten der Website.
  - **Lokale Modelle:** `http://localhost:…` (Ollama, LM Studio) ist erlaubt, andere Adressen müssen `https` sein. API-Keys bleiben auf dem Server und werden nie an Besucher ausgeliefert.

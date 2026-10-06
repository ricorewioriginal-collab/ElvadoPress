# Architektur – ElvadoPress

Diese Datei ist die kompakte Architekturübersicht für Entwicklungs-Sessions. Fachdetails bleiben in `cms/docs/`.

## Systembild
ElvadoPress ist ein serverseitiges PHP-CMS ohne erforderlichen Build-Schritt. Die Verwaltung läuft unter `/cms/`; Browser-JavaScript spricht die zentrale PHP-API an. Inhalte werden primär dateibasiert gespeichert. Optional kann ein Datenbankspiegel bzw. die WordPress-Kompatibilitätsschicht SQLite/MySQL/MariaDB nutzen; PostgreSQL wird für den Inhalts-Spiegel unterstützt.

## Hauptkomponenten
- **Öffentliche Auslieferung:** Root-`index.php`, CMS-Publishing/Theme-Schicht, RSS/Sitemap/Robots.
- **Verwaltung:** `cms/index.php` plus Panels aus `cms/views/` und Browserlogik aus `cms/assets/`.
- **API:** `cms/api.php?action=...` bündelt Verwaltungs- und Datenaktionen.
- **Fachlogik:** `cms/lib/*.php` für Auth, Datenbank, Inhalte, Branding, Community, Backups, Apps, KI-Assistent, Demo usw.
- **Native Plugins (Essentials):** `cms/src/Plugin/` (Manager, Manifest, Hooks, Context), Paketbibliothek `cms/official-plugins/` mit prüfsummenbasiertem Katalog, installiert nach `cms/plugins/`, Zustand `cms/data/.plugins/`; Erweiterungspunkte im Core über `cms/lib/nplugins.php` (`rrw_np_do`/`rrw_np_filter`). Server-PHP läuft nur für offizielle, unveränderte Plugins. Core vs. Plugin und Entwicklerdoku: `cms/docs/PLUGIN-ENTWICKLUNG.md`.
- **Themes/Plugins/WordPress-Schicht:** Erweiterbarkeit und Kompatibilität; Fachdetails in `cms/docs/THEMES.md`, `PLUGINS.md`, `WORDPRESS.md`.
- **Tests/Packaging:** `scripts/`, CI und Release-Workflows.

## Datenfluss
1. Verwaltung lädt `cms/index.php`, Panels und Assets.
2. Client-Aktionen gehen an `cms/api.php`.
3. API authentifiziert/autorisiert, ruft Fachmodule aus `cms/lib/` auf und liest/schreibt die betroffenen Bereiche.
4. Primäre CMS-Daten liegen unter `cms/data/`; Veröffentlichungsaktionen aktualisieren je nach Bereich öffentliche Dateien/Feeds/Metadaten und optional den Datenbankspiegel.
5. Bereichsrevisionen (`revs`/`base_rev`) schützen parallele Bearbeitung vor stillem Überschreiben.

## Authentifizierung
Zwei Betriebswege sind dokumentiert:
- lokaler CMS-Zugang mit serverseitig gespeicherten Passwort-Hashes und lokalen Sitzungstokens;
- optional Control-Center-Token im verbundenen Betriebsmodus.
Im eigenständigen Betrieb werden Control-Center-Tokens abgelehnt. Details und konkrete API-Aktionen: `cms/docs/API.md` und `INSTALL.md`.

## Storage / Datenbank
- Standard: JSON/Dateien unter `cms/data/`; Markdown-Spiegel für Seiten/Inhalte.
- Optionaler Spiegel: SQLite, MySQL/MariaDB, PostgreSQL.
- WordPress-`$wpdb`-Schicht: SQLite oder MySQL/MariaDB; bei PostgreSQL bleibt diese Schicht bei SQLite.
- Zugangsdaten liegen in lokalen, nicht zu commitenden Dateien. Details: `cms/docs/DATABASE.md`.

## APIs
- Zentrale API: `/cms/api.php?action=...`.
- Öffentliche Lesepunkte und Verwaltungsaktionen sind in `cms/docs/API.md` dokumentiert.
- RSS: `cms/rss.php` bzw. `/feed/`.

## Frontend / Backend
Es gibt keine getrennte Node-SPA und keinen erforderlichen JS-Build. PHP rendert/koordiniert die Verwaltung; JavaScript-Dateien unter `cms/assets/` liefern die interaktive Clientlogik und sprechen die PHP-API an. Panels liegen modular unter `cms/views/`.

## Externe Dienste
Optionale Integrationen umfassen den verbundenen Control-Center-Betrieb, konfigurierbare KI-Anbieter, App-Build/GitHub-Funktionen, Alexa/Streams und frei konfigurierbare verbundene Dienste. Diese sind nicht für den Kernbetrieb zwingend. Keine Zugangsdaten gehören in die Dokumentation.

## Deployment / Veröffentlichung
- ElvadoPress ist die Hauptquelle.
- CI auf Push/PR prüft PHP-Syntax, Smoke-Test und `scripts/test-*.php`.
- Tag `v<Version>` erzeugt ein Release-ZIP.
- *ricorewi-radio* übernimmt `cms/` per Sync-Workflow; bestimmte Branding-/Produktdateien besitzen dort eigene Fassungen.
- Die öffentliche Demo wird laut vorhandener Doku über das verbundene Projekt bereitgestellt.

## Weiterführende Dokumentation
- `cms/docs/ELVADOPRESS.md` – Entwicklungs-/Sync-/Demo-Ablauf
- `cms/docs/API.md` – API/Auth/Datenflüsse
- `cms/docs/DATABASE.md` – Datenbank/Storage
- `cms/docs/PLUGINS.md`, `THEMES.md`, `WORDPRESS.md` – Erweiterbarkeit
- `cms/docs/APP-BUILDER.md`, `COMMUNITY.md` – optionale Fachmodule

# Projektkarte – ElvadoPress

Nur die wichtigsten Suchorte. Nicht als vollständige Dateiliste gedacht.

| Pfad | Aufgabe |
|---|---|
| `index.php` | Root-Einstieg für die öffentliche CMS-/Theme-Auslieferung. |
| `cms/index.php` | Einstieg der Verwaltungsoberfläche. |
| `cms/api.php` | Zentrale CMS-API und Aktionsrouter. |
| `cms/install.php` | Ersteinrichtungsassistent. |
| `cms/rebuild.php` | Rebuild-/Publizierungs-Einstieg. |
| `cms/rss.php` | RSS-Ausgabe. |
| `cms/lib/` | Serverseitige Fachlogik: Auth, Storage, Inhalte, Themes/Plugins, Branding, Apps, Assistant, Community, Demo usw. |
| `cms/lib/stockmedia.php`, `cms/assets/stock-media.js` | Freie Bildquellen (Pixabay, Pexels, Unsplash, Openverse, Wikimedia) und zentrale Bildauswahl; Doku `cms/docs/MEDIA.md`. |
| `cms/lib/radio.php`, `cms/radio.php` | Radio-Erweiterung: Sender/Quellen (laut.fm, Icecast, Shoutcast), Jetzt läuft, Sendeplan; öffentlicher JSON-Endpunkt. |
| `cms/themes/` | mitgelieferte Themes: `rrw-classic` (Standard), `elvado-baukasten` (Homepage-Baukasten), `elvado-radio` (Radio), `elvado-band` (Band/Musiker), `elvado-creator` (Creator/Influencer). |
| `cms/lib/themeconf.php`, `cms/lib/band.php`, `cms/lib/creator.php` | Theme-Menüs aus Schema (Editor, Speichern, Bereinigung); Band-Schema. |
| `cms/views/` | PHP-Panels/Teilansichten der Verwaltung. |
| `cms/assets/` | JavaScript, CSS, Branding und statische Verwaltungsassets. |
| `cms/assets/cms-app.js` | Zentrale Browserlogik der CMS-Verwaltung. |
| `cms/data/` | Laufzeitdaten einer Installation; überwiegend nicht versioniert. |
| `cms/docs/` | bestehende Fach- und Funktionsdokumentation; vor neuer Doku zuerst hier suchen. |
| `scripts/` | Smoke-, Funktions-, Demo-, Datenbank- und Build-/Packaging-Tests/Skripte. |
| `.github/workflows/ci.yml` | CI: Syntax, Smoke-Test, alle Tests. |
| `.github/workflows/release.yml` | Release-ZIP bei `v*`-Tags. |
| `README.md` | Öffentlicher Projektüberblick. |
| `INSTALL.md` | Umfangreiche Installations-/Betriebsdoku; bekannter Bereinigungsbedarf siehe `KNOWN_ISSUES.md`. |
| `CLAUDE.md` | Verbindliche Arbeitsregeln für Claude Code. |
| `HANDOVER.md` | Aktueller Entwicklungsstand für die nächste Session. |
| `ARCHITECTURE.md` | Kompakte Systemarchitektur. |
| `DEVELOPMENT.md` | Entwicklungsumgebung, Befehle und Tests. |

## Wo bei typischen Aufgaben zuerst suchen?
- **API/Auth/Speichern:** `cms/api.php`, `cms/lib/auth.php`, passende Fachmodule, `cms/docs/API.md`.
- **UI/Verwaltung:** `cms/views/`, `cms/assets/cms-app.js` und bereichsspezifische Assets.
- **Datenbank:** `cms/lib/database.php`, `cms/docs/DATABASE.md`, `scripts/test-db-config.php`.
- **Themes/Plugins/WordPress:** passende `cms/lib/`-Module plus `cms/docs/THEMES.md`, `PLUGINS.md`, `WORDPRESS.md`.
- **Apps/Alexa/KI:** `cms/lib/appbuild.php`, `apps.php`, `alexa.php`, `assistant.php` plus passende Assets/Doku.
- **Demo:** `cms/lib/demo.php`, `scripts/make-demo.php`, `scripts/test-demo.php`, `cms/docs/ELVADOPRESS.md`.
- **Tests:** zuerst nach einem passenden `scripts/test-*.php` suchen, statt automatisch alles auszuführen.

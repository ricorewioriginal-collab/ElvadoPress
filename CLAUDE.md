# Projektregeln – ElvadoPress (Hauptquelle des CMS)

## Session-Start
Für neue Claude-Code-Sessions in dieser Reihenfolge lesen:
1. `CLAUDE.md`
2. `HANDOVER.md`
3. `PROJECT_MAP.md`
4. nur bei Bedarf `ARCHITECTURE.md`
5. nur bei Bedarf `DEVELOPMENT.md`
6. danach ausschließlich die für die Aufgabe relevanten Quellcodedateien

Nicht automatisch das gesamte Repository neu analysieren.

## Projekt und Zweck
ElvadoPress ist ein eigenständiges, erweiterbares PHP-CMS für Websites mit Themes, Plugins, visueller Verwaltung, Datei-Storage, optionalem Datenbankspiegel und WordPress-Kompatibilitätsschicht. Optionale neutrale Erweiterungen umfassen App-Baukasten, Alexa-Skill-Baukasten und KI-Assistent.

Dieses Repository ist die **Hauptquelle des CMS**. Das Entwicklungsprojekt *ricorewi-radio* übernimmt `cms/` per Sync-Workflow und enthält die RicoReWi-spezifischen Teile.

## Architektur in Kürze
- PHP 8.1+; kein Build-Schritt für das CMS.
- `cms/index.php`: Verwaltungsoberfläche; `cms/api.php`: zentrale Verwaltungs-/Daten-API.
- `cms/lib/`: serverseitige Fachmodule.
- `cms/views/`: Panels der Verwaltung.
- `cms/assets/`: Browser-JavaScript, Styles und ElvadoPress-Branding.
- `cms/data/`: Laufzeitdaten; primär JSON/Dateien, optional SQLite/MySQL/MariaDB/PostgreSQL-Spiegel.
- `cms/themes/` und Plugin-/WordPress-Schicht: Darstellung und Erweiterbarkeit.
- `scripts/`: Smoke-, Funktions-, Demo- und Build-Tests.
- Details: [ARCHITECTURE.md](ARCHITECTURE.md), [PROJECT_MAP.md](PROJECT_MAP.md), `cms/docs/`.

## Was wohin gehört
| Art | Beispiele | Repository |
|---|---|---|
| **CMS** | Beiträge, Seiten, Medien, Kommentare, Einstellungen, WordPress-Schicht, Themes/Plugins, Benutzer, Formulare, Community, Demo-Betrieb | **hier** |
| **Radio-Erweiterungen** (neutral) | App-Baukasten, Alexa-Skill-Baukasten, KI-Assistent – mit eigenen Inhalten des Betreibers | **hier** |
| **RicoReWi** | Portal, Portal-Themes, paketgebundene Radio-/Netzwerkfunktionen, Marken-Inhalte | **nur ricorewi-radio** |

## Wichtige Einstiegspunkte und Doku
- `README.md`: menschlicher Projektüberblick.
- `INSTALL.md`: vorhandene ausführliche Betriebs-/Installationsdokumentation; siehe Hinweis in `KNOWN_ISSUES.md`.
- `cms/docs/ELVADOPRESS.md`: Entwicklung, Sync und Demo.
- `cms/docs/API.md`: API und Authentifizierung.
- `cms/docs/DATABASE.md`: Storage/Datenbank.
- `cms/docs/PLUGINS.md`, `THEMES.md`, `APP-BUILDER.md`, `COMMUNITY.md`: Fachbereiche.
- `.github/workflows/ci.yml`, `release.yml`: CI und Release.

## Entwickeln und testen
- PHP-Syntax: `find cms scripts -name '*.php' -print0 | xargs -0 -n1 php -l`
- Smoke-Test: `php scripts/smoke-test.php`
- Alle Tests: `for t in scripts/test-*.php; do php "$t" | tail -1; done`
- Demo: `php scripts/make-demo.php <Kopie>`; Test: `php scripts/test-demo.php`
- Standalone-Paket: `php scripts/build-standalone.php <Zielordner> [--zip=<Datei.zip>]`
- Weitere Voraussetzungen und Testhinweise: [DEVELOPMENT.md](DEVELOPMENT.md).

## Verbindliche Arbeitsweise
- Bestehende Architektur respektieren und funktionierenden Code nicht grundlos verändern.
- Keine unnötigen Refactorings; vorhandene Komponenten/Funktionen wiederverwenden.
- Keine Mock-, Demo- oder Platzhalterimplementierungen als fertige Lösung ausgeben.
- Bei konkreten Aufgaben zuerst gezielt relevante Dateien ermitteln; Suche/Dateisuche vor dem vollständigen Lesen großer Dateien verwenden.
- Nicht automatisch das komplette Repository oder unabhängige Projektbereiche analysieren.
- Vorhandene Dokumentation als Kontextquelle verwenden.
- Generierte Dateien, Abhängigkeiten und Build-Artefakte (`node_modules`, `vendor`, `dist`, `build`, Caches, Binärdateien usw.) nur analysieren, wenn ausdrücklich nötig.
- Bereits dokumentierte Architektur nicht bei jeder Aufgabe rekonstruieren; abgeschlossene Arbeitsverläufe nicht wiederholen.

## Qualität
- Änderungen vollständig implementieren; keine offensichtlichen TODO-Platzhalter zurücklassen.
- Fehlerursachen beheben statt Symptome zu kaschieren.
- Bestehende Funktionalität nicht beschädigen.
- Betroffene Funktionen mit vorhandenen, möglichst gezielten Tests prüfen; vollständigen Testlauf nur wenn erforderlich.
- Antworten und Projektdokumentation auf Deutsch halten, soweit keine bestehende Schnittstelle anderes verlangt.

## Git und Veröffentlichung
- Vor größeren Änderungen `git status` prüfen.
- Fremde/uncommittete Änderungen niemals überschreiben.
- Keine destruktiven Git-Befehle ohne ausdrücklichen Auftrag.
- Vor Abschluss `git diff` kontrollieren.
- Commit/Push nur entsprechend der aktuellen Benutzeranweisung.
- Bevorzugter Ablauf: Branch → Pull Request → CI grün → Merge.
- Release: Tag `v<Version>` erzeugt über Workflow *Release* ein Installations-ZIP.
- `ricorewi-radio` übernimmt CMS-Änderungen anschließend per Sync-Workflow.

## Bestandsschutz / Sync-Besonderheiten
- Keine RicoReWi-Inhalte in dieses Repository aufnehmen.
- Paketabweichungen laufen über `rrw_pack_available()` / `rrw_pack_active()` (`cms/lib/pack.php`), UI-`data-pack` bzw. `data-pack-app` und `window.CMS_PACKS['ricorewi-radio']`.
- Der Sync nach *ricorewi-radio* muss dessen Paket-Tests bestehen.
- Nicht synchronisiert werden dort eigene Fassungen von `cms/lib/product.default.json`, `cms/assets/brand/**` und `cms/lib/alexa-skill/{catalog.json,skill.json,README.md,listing-de.md}`.
- Neue Dateien unter `cms/` werden grundsätzlich synchronisiert; deshalb dort nichts Markenspezifisches hinzufügen.

## Dokumentation nach größeren Aufgaben
Nicht bei Kleinigkeiten alle Dateien umschreiben:
1. `HANDOVER.md` nach größeren abgeschlossenen Entwicklungsaufgaben aktualisieren.
2. `ARCHITECTURE.md` nur bei echter Architekturänderung.
3. `DEVELOPMENT.md` nur bei neuen/geänderten Entwicklungs-, Build- oder Testbefehlen.
4. `PROJECT_MAP.md` nur bei wesentlichen neuen Verzeichnissen/Komponenten.
5. `KNOWN_ISSUES.md` bei tatsächlich festgestellten oder behobenen Problemen pflegen.

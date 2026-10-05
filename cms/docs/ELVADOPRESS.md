# ElvadoPress – Entwicklung, Veröffentlichung, Demo

ElvadoPress ist das eigenständige CMS mit optionalen Radio-Erweiterungen. **Hauptquelle ist das Repository <https://github.com/ricorewioriginal-collab/ElvadoPress>**; das Projekt *ricorewi-radio* (RicoReWi-Portal) übernimmt `cms/` von dort.

## Funktionsarten
| Art | Wo entwickelt |
|---|---|
| CMS (Beiträge, Seiten, WordPress-Schicht, Benutzer, Formulare, Community …) | ElvadoPress |
| Radio-Erweiterungen (App-Baukasten, Alexa-Skill, Radioverzeichnis, KI-Assistent – neutral) | ElvadoPress |
| RicoReWi (Portal, Portal-Themes, Core-Netzwerk, Partnerseite, SenderWelt …) | nur ricorewi-radio |

## Ablauf
1. Änderung in ElvadoPress per Pull Request (CI: Syntax, `scripts/smoke-test.php`, alle `scripts/test-*.php`).
2. In ricorewi-radio holt der Workflow *Sync ElvadoPress* den Stand (Branch `sync/elvadopress`, Pull Request). Dort laufen zusätzlich die Paket-Tests der RicoReWi-Seite (Bestandsschutz).
3. Nach dem Merge dort wird die Live-Seite bereitgestellt; die Demo (`https://elvadopress.ricorewi-radio.de`) folgt dem Stand von ricorewi-radio.

Nicht synchronisiert werden: `cms/lib/product.default.json`, `cms/assets/brand/**`, `cms/lib/alexa-skill/{catalog.json,skill.json,README.md,listing-de.md}` (jedes Repository hat dafür eigene Fassungen).

## Lizenz
GPL-2.0-or-later (Datei `LICENSE`).

## Öffentliche Demo (Testinstanz)
- Vorbereiten: `php scripts/make-demo.php <Paketordner> [--minutes=10]` (Kopie des Repositories; im Entwicklungsprojekt: `scripts/build-standalone.php … --demo`). Das legt `cms/lib/demo.json` an und macht die Instanz zur Demo (`cms/lib/demo.php`, Startseite `demo/` aus `cms/assets/demo-landing.html`).
- Verhalten: Die erste Anfrage richtet die Demo selbst ein (Benutzer `demo`, Beispielbeiträge, neutrales Theme). Nach Ablauf des Zeitfensters (Standard **10 Minuten**) setzt die nächste Anfrage alles zurück (träge, unter Sperre, kein Cron nötig). Eine Leiste mit Zähler und Zugang erscheint auf Website und Verwaltung; `/cms/?demo=1` meldet automatisch an; `/demo/` ist die Info-Seite.
- Gesperrt (Antwort 403 „In der Demo nicht möglich“): Datei-, Plugin- und Theme-Uploads/-Installation, WordPress-Updates, Benutzer, System, Datenbank, Backups, Weiterleitungen, KI-Assistent, App-Build/GitHub-Zugang, Mailversand. Liste: `RRW_DEMO_BLOCKED` in `cms/lib/demo.php`.
- Test: `php scripts/test-demo.php`.
- Bereitstellung: Workflow „Deploy ElvadoPress Demo“ in ricorewi-radio (Secret `ELVADOPRESS_DEMO_PATH` = Webordner einer **eigenen Subdomain**). Ein Unterordner einer bestehenden Domain ist bewusst nicht vorgesehen: Das CMS nutzt Wurzelpfade (`/cms/`, `/wp-admin/`), und Anmeldedaten liegen pro Domain im Browser.

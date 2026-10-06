# ElvadoPress – Entwicklung, Veröffentlichung, Demo

ElvadoPress ist das eigenständige CMS mit optionalen Radio-Erweiterungen. **Hauptquelle ist das Repository <https://github.com/ricorewioriginal-collab/ElvadoPress>**; das Projekt *ricorewi-radio* (RicoReWi-Portal) übernimmt `cms/` von dort.

## Funktionsarten
| Art | Wo entwickelt |
|---|---|
| CMS (Beiträge, Seiten, WordPress-Schicht, Benutzer, Formulare, Community …) | ElvadoPress |
| Radio-Erweiterungen (App-Baukasten, Alexa-Skill, KI-Assistent – neutral) | ElvadoPress |
| RicoReWi (Portal, Portal-Themes, Radioverzeichnis (nur mit Paket aktiv), Core-Netzwerk, Partnerseite, SenderWelt …) | nur ricorewi-radio |

## Ablauf
1. Änderung in ElvadoPress per Pull Request (CI: Syntax, `scripts/smoke-test.php`, alle `scripts/test-*.php`).
2. In ricorewi-radio holt der Workflow *Sync ElvadoPress* den Stand (Branch `sync/elvadopress`, Pull Request). Dort laufen zusätzlich die Paket-Tests der RicoReWi-Seite (Bestandsschutz).
3. Nach dem Merge dort wird die Live-Seite bereitgestellt; die Demo (`https://elvadopress.ricorewi-radio.de`) folgt dem Stand von ricorewi-radio.

Nicht synchronisiert werden: `cms/lib/product.default.json`, `cms/assets/brand/**`, `cms/lib/alexa-skill/{catalog.json,skill.json,README.md,listing-de.md}` (jedes Repository hat dafür eigene Fassungen).

## Lizenz
GPL-2.0-or-later (Datei `LICENSE`).

## Öffentliche Demo (Testinstanz)
- Vorbereiten: `php scripts/make-demo.php <Paketordner> [--minutes=10]` (Kopie des Repositories; im Entwicklungsprojekt: `scripts/build-standalone.php … --demo`). Das legt `cms/lib/demo.json` an und macht die Instanz zur Demo (`cms/lib/demo.php`, Startseite `demo/` aus `cms/assets/demo-landing.html`).
- Verhalten: Die erste Anfrage richtet die Demo selbst ein (Benutzer `demo`, Beispielbeiträge, neutrales Theme). Nach Ablauf des Zeitfensters (Standard **10 Minuten**) setzt die nächste Anfrage alles zurück (träge, unter Sperre, kein Cron nötig). Eine Leiste mit Zähler und Zugang erscheint auf Website und Verwaltung; `/cms/?demo=1` meldet automatisch an; `/demo/` ist die Info-Seite. **Die Demo-Website ist die ElvadoPress-Produkt-Homepage**, gebaut mit ElvadoPress selbst: Theme „Baukasten“ mit Homepage-Baukasten-Layout, Seiten (Funktionen, Themes, Demo-Anleitung, Selbst betreiben), Menü und Beiträge – Texte in `cms/lib/demo-content.php`, Anlegen in `rrw_demo_seed_site()` (`cms/lib/demo.php`). Nach dem Zurücksetzen startet die erste Anfrage per Weiterleitung neu (frischer PHP-Prozess mit dem neuen Theme). Die Info-Seite mit Zugang liegt unter `/demo/`.
- **Seitenleiste und Widgets:** Die Demo-Startseite nutzt die Baukasten-Option „Seitenleiste auch auf der Startseite“ mit elf Demo-Widgets (Logo, Live-Demo-Zugang, Suche, Funktionsliste, Neueste Beiträge, Kategorien, Schlagwörter, Seiten, Themes, Archiv, GitHub) – `rrw_demo_widgets()` in `cms/lib/demo-content.php`; das ElvadoPress-Logo (`cms/assets/brand/elvadopress-logo.png`) steht im Kopf und in der Seitenleiste, die Illustration `cms/assets/demo/homepage-builder.svg` im Abschnitt „Dein Design, deine Regeln“.
- **Alle Funktionen sind frei** (Benutzer, Passwörter, System, Backups, Medien-Upload, KI-Gateway, Lovable, freie Bildquellen, Update-Anzeige …) – nach dem Zurücksetzen ist alles wieder im Ausgangszustand (auch `backups/` und `frontend/`). Gesperrt (Antwort 403 „In der Demo nicht möglich“) ist nur, was den gemeinsamen Server gefährdet: Plugin-/Theme-Upload und -Installation (fremder PHP-Code), WordPress-Updates, CMS-Update einspielen (`update_apply/rollback/config_save`), externe Datenbank-Verbindungen, Mailversand/Assistent mit Zugangsdaten, App-Builds, Weiterleitungen, GitHub-Dateisync (`lovable_sync`) und das Umschalten des Betriebsmodus. Liste: `RRW_DEMO_BLOCKED` in `cms/lib/demo.php`.
- Test: `php scripts/test-demo.php`.
- Bereitstellung: Workflow „Deploy ElvadoPress Demo“ in ricorewi-radio (Secret `ELVADOPRESS_DEMO_PATH` = Webordner einer **eigenen Subdomain**). Ein Unterordner einer bestehenden Domain ist bewusst nicht vorgesehen: Das CMS nutzt Wurzelpfade (`/cms/`, `/wp-admin/`), und Anmeldedaten liegen pro Domain im Browser.

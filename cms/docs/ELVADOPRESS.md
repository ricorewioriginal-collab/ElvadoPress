# ElvadoPress – Entwicklung, Veröffentlichung, Demo

ElvadoPress ist das eigenständige CMS mit optionalen Radio-Erweiterungen. **Hauptquelle ist das Repository <https://github.com/ricorewioriginal-collab/ElvadoPress>**; *RicoReWi Radio* und *Senderwelt* werden unabhängig davon entwickelt (kein Sync).

## Funktionsarten
| Art | Wo entwickelt |
|---|---|
| CMS (Beiträge, Seiten, WordPress-Schicht, Benutzer, Formulare, Community …) | ElvadoPress |
| Erweiterungen (App-Baukasten, Alexa-Skill, KI-Assistent) | ElvadoPress |
| Radio (Sender, Player, Sendeplan) | nicht im Kern, später optionales Plugin |

## Ablauf
1. Änderung in ElvadoPress per Pull Request (CI: Syntax, `scripts/smoke-test.php`, alle `scripts/test-*.php`).
2. Nach dem Merge steht der Stand in `main`; ein Release entsteht über den Tag `v<Version>` (Workflow *Release*).
3. Die Demo (`https://elvadopress.ricorewi-radio.de`) wird serverseitig per Deploy (Projekt `elvadopress`) bei Änderung in `main` bereitgestellt (kein GitHub-Actions-Deploy).

## Lizenz
GPL-2.0-or-later (Datei `LICENSE`).

## Öffentliche Demo (Testinstanz)
- Vorbereiten: `php scripts/make-demo.php <Paketordner> [--minutes=10]` (Kopie des Repositories; im Entwicklungsprojekt: `scripts/build-standalone.php … --demo`). Das legt `cms/lib/demo.json` an und macht die Instanz zur Demo (`cms/lib/demo.php`, Startseite `demo/` aus `cms/assets/demo-landing.html`).
- Verhalten: Die erste Anfrage richtet die Demo selbst ein (Benutzer `demo`, Beispielbeiträge, neutrales Theme). Nach Ablauf des Zeitfensters (Standard **10 Minuten**) setzt die nächste Anfrage alles zurück (träge, unter Sperre, kein Cron nötig). Eine Leiste mit Zähler und Zugang erscheint auf Website und Verwaltung; `/cms/?demo=1` meldet automatisch an; `/demo/` ist die Info-Seite. **Die Demo-Website ist die ElvadoPress-Produkt-Homepage**, gebaut mit ElvadoPress selbst: Theme „Baukasten“ mit Homepage-Baukasten-Layout, Seiten (Funktionen, Themes, Demo-Anleitung, Selbst betreiben), Menü und Beiträge – Texte in `cms/lib/demo-content.php`, Anlegen in `elvado_demo_seed_site()` (`cms/lib/demo.php`). Nach dem Zurücksetzen startet die erste Anfrage per Weiterleitung neu (frischer PHP-Prozess mit dem neuen Theme). Die Info-Seite mit Zugang liegt unter `/demo/`.
- **Seitenleiste und Widgets:** Die Demo-Startseite nutzt die Baukasten-Option „Seitenleiste auch auf der Startseite“ mit elf Demo-Widgets (Logo, Live-Demo-Zugang, Suche, Funktionsliste, Neueste Beiträge, Kategorien, Schlagwörter, Seiten, Themes, Archiv, GitHub) – `elvado_demo_widgets()` in `cms/lib/demo-content.php`; das ElvadoPress-Logo (`cms/assets/brand/elvadopress-logo.png`) steht im Kopf und in der Seitenleiste, die Illustration `cms/assets/demo/homepage-builder.svg` im Abschnitt „Dein Design, deine Regeln“.
- **Alle Funktionen sind frei** (Benutzer, Passwörter, System, Backups, Medien-Upload, KI-Gateway, Lovable, freie Bildquellen, Update-Anzeige …) – nach dem Zurücksetzen ist alles wieder im Ausgangszustand (auch `backups/` und `frontend/`). Gesperrt (Antwort 403 „In der Demo nicht möglich“) ist nur, was den gemeinsamen Server gefährdet: Plugin-/Theme-Upload und -Installation (fremder PHP-Code), WordPress-Updates, CMS-Update einspielen (`update_apply/rollback/config_save`), externe Datenbank-Verbindungen, Mailversand/Assistent mit Zugangsdaten, App-Builds, Weiterleitungen, GitHub-Dateisync (`lovable_sync`) und das Umschalten des Betriebsmodus. Liste: `ELVADO_DEMO_BLOCKED` in `cms/lib/demo.php`.
- **Demo mit echtem WordPress (optional):** Legt der Betreiber `cms/lib/demo-engine.json` an (`{"host":"localhost","name":"<leere Demo-Datenbank>","user":"…","pass":"…","prefix":"wpdemo_"}`, optional `"core_zip":"/pfad/wordpress-X.Y.Z.zip"`; beim Bauen: `php scripts/make-demo.php <Ordner> --engine="host|datenbank|benutzer|passwort|wpdemo_"`), richtet sich die Engine nach der Anmeldung selbst ein (Aktion `engine_demo_setup`: Core von wordpress.org prüfen und einspielen – nur beim ersten Mal –, Demo-Tabellen leeren, WordPress einrichten, aktiv) und alle Engine-Funktionen sind freigeschaltet: Inhalte, Medien, Benutzer, Menüs, Widgets, Blöcke, Plugins/Themes (Liste, Aktivieren), Migration (Trockenlauf, echter Lauf, Rückbau) und Live Builder. Beim Zurücksetzen werden nur Tabellen mit dem Demo-Präfix gelöscht (nie andere); die Core-Dateien bleiben. **Plugins/Themes:** installieren (und löschen) lassen sich nur ausgewählte, bekannte Pakete aus dem WordPress-Verzeichnis – Standard: Plugins `classic-editor`, `hello-dolly`, Themes `twentytwentyfive`, `twentytwentyfour`; der Betreiber ändert die Liste in `demo-engine.json` (`"allow_install":{"plugins":[…],"themes":[…]}`, leere Listen = nichts). **Gesperrt bleiben** ZIP-Upload, alle anderen Pakete und der manuelle Aufbau der Engine (fremder Programmcode auf dem gemeinsamen Server). Ohne `demo-engine.json` verhält sich die Demo wie bisher. Die Datei ist per `.htaccess` gesperrt (0600), das Passwort erscheint in keiner Antwort. Test: `php scripts/test-demo-engine.php` (mit `WPE_TEST_ZIP`/`WPE_TEST_DB` gegen echtes WordPress).
- Test: `php scripts/test-demo.php`.

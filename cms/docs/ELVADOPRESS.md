# ElvadoPress – Veröffentlichung

ElvadoPress ist das eigenständige CMS ohne RicoReWi-Abhängigkeit. Es wird aus diesem Projekt gebaut und in <https://github.com/ricorewioriginal-collab/ElvadoPress> veröffentlicht.

- **Funktionsarten:** CMS, Radio, RicoReWi – siehe `CLAUDE.md` im Wurzelverzeichnis. Nur *RicoReWi* bleibt hier.
- **Bauen:** `php scripts/build-standalone.php <Ordner> --product=ElvadoPress`
- **Branding:** Logo und Icons liegen in `cms/standalone/brand/`; der Produktname kommt aus `cms/lib/product.default.json` (wird beim Bauen erzeugt, kann in der Verwaltung unter *Betrieb & Produktname* überschrieben werden).
- **Prüfen:** `php scripts/test-standalone-build.php`.
- **Veröffentlichen:** Workflow „Publish ElvadoPress“ (manuell oder bei einem Tag `cms-v<Version>`); Secret `ELVADOPRESS_TOKEN` nötig.

## Lizenz
ElvadoPress steht unter **GPL-2.0-or-later** (Datei `LICENSE` kommt aus `cms/standalone/elvadopress/` ins Paket).

## Öffentliche Demo (Testinstanz)
- Bauen: `php scripts/build-standalone.php <Ordner> --product=ElvadoPress --demo [--demo-minutes=10]`. Das legt `cms/lib/demo.json` an und macht die Instanz zur Demo (`cms/lib/demo.php`).
- Verhalten: Die erste Anfrage richtet die Demo selbst ein (Benutzer `demo`, Beispielbeiträge, neutrales Theme). Nach Ablauf des Zeitfensters (Standard **10 Minuten**) setzt die nächste Anfrage alles zurück (träge, unter Sperre – kein Cron nötig). Eine Leiste mit Zähler und Zugang erscheint auf der Website und in der Verwaltung; `/cms/?demo=1` meldet automatisch an; `/demo/` ist die Info-Seite.
- Gesperrt (Antwort 403 „In der Demo nicht möglich“): Datei-, Plugin- und Theme-Uploads/-Installation, WordPress-Updates, Benutzer, System, Datenbank, Backups, Weiterleitungen, KI-Assistent, App-Build/GitHub-Zugang, Mailversand. Liste: `RRW_DEMO_BLOCKED` in `cms/lib/demo.php`.
- Test: `php scripts/test-demo.php`.
- Bereitstellung: Workflow „Deploy ElvadoPress Demo“ (Secret `ELVADOPRESS_DEMO_PATH` = Webordner einer **eigenen Subdomain**, z. B. `elvadopress.ricorewi-radio.de`). Ein Unterordner von ricorewi-radio.de ist bewusst nicht vorgesehen: Das CMS nutzt Wurzelpfade (`/cms/`, `/wp-admin/`), und Anmeldedaten liegen pro Domain im Browser – Demo und echtes CMS würden sich stören, und Demo-Besucher könnten in dessen Herkunft Skripte ausführen.

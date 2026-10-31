# WordPress in ElvadoPress

ElvadoPress arbeitet ausschließlich mit **echtem WordPress** (WordPress-Engine, `cms/src/Wp/`). Die frühere eigene Nachbildung der WordPress-API (`cms/wp/core`, `wp-admin`, `wp-includes` …) ist seit Version 1.4.0 entfernt; vorhandene Installationen werden beim ersten Aufruf automatisch umgestellt. Technische Einzelheiten stehen in [ARCHITECTURE-WORDPRESS.md](ARCHITECTURE-WORDPRESS.md), die Start-Brücke in [WORDPRESS-BRIDGE.md](WORDPRESS-BRIDGE.md).

## Einrichtung
- Bei der ersten Anmeldung eines Superadmins richtet ElvadoPress WordPress selbst ein (`autoSetup()` in `cms/assets/wp-engine.js`): Core laden (`engine_core`, ohne Netz aus `cms/wp-engine/bundle/`), Datenbank (Standard SQLite, wahlweise MySQL/MariaDB), Betriebsart *aktiv*, Website-Auslieferung durch WordPress (`engine_front`).
- Der Core liegt schreibgeschützt in `cms/wp-engine/`, Plugins und Themes in `cms/wp-content/` bzw. `cms/themes/`. CMS-Updates lassen den Core unberührt (`TreeInstaller::PRESERVE`).
- Solange WordPress nicht eingerichtet ist, antworten die `wp_*`-Aktionen der API mit 409 (`engine:false`) und die Website zeigt „Die Website wird gerade eingerichtet“ (RSS, robots.txt und 404-Seiten funktionieren weiter).

## Wer führt welche Daten?
- **ElvadoPress** bleibt führend für Beiträge, Seiten, Medien, Kommentare und Website-Einstellungen (`news.json`, `site.json`, `comments.json`, `cms/media`). Apps, Alexa, RSS, Assistent, SEO und Lovable-Feed lesen diese Daten.
- `Migration\LiveSync` überträgt Änderungen beim nächsten Seitenaufruf nach WordPress (Beiträge, Seiten, Kommentare, SEO-Felder, Logo). Neue Besucher-Kommentare auf der Website landen über `wp_insert_comment` in `comments.json`.
- Einstellungen aus der Verwaltung (Theme, Plugins, Optionen) werden über den Eingang `cms/data/.wp/options.json` vorgemerkt und änderungsbasiert übernommen (`elvado_wpe_adopt_site`).

## Verwaltung
- **In ElvadoPress:** Plugins und Themes (Suche im WordPress-Verzeichnis, Hochladen, Aktivieren, Updates, Sprachpakete), Einstellungen (Allgemein, Schreiben, Lesen, Diskussion, Medien), Link-Struktur, Live-Customizer, Baukasten, Sandbox. Diese Aktionen starten echtes WordPress im selben Prozess (`cms/lib/wp-real-admin.php`) und nutzen dessen Funktionen; die Bausteine liegen in `cms/wp/` (siehe `cms/wp/README.md`).
- **Echte WordPress-Verwaltung:** „WordPress-Verwaltung öffnen“ meldet den Administrator per Einmal-Schlüssel (120 s) in `/wp-admin/` an. `wp-login.php` führt zur ElvadoPress-Anmeldung.
- **Sandbox:** Kopie der WordPress-Datenbank (SQLite: Datei-Kopie, MySQL: Präfix `<präfix>sbx_`) zum gefahrlosen Ausprobieren von Plugins, Themes und Einstellungen; Vergleich, Veröffentlichen mit Sicherung und Rücksprung (`cms/lib/wp-real-sandbox.php`).

## Live-Customizer
- Design → Themes → „Anpassen“ zeigt die Abschnitte und Steuerelemente des echten `WP_Customize_Manager` (inkl. `customize_register` des Themes). Gespeichert wird als `theme_mod` bzw. Option.
- Entwürfe liegen 15 Minuten in `cms/data/.wp/customize-drafts/` und wirken nur in der Vorschau (signierter Vorschau-Schlüssel `elvado_wpra_preview_sig`). Geplante Änderungen werden zur Zeit veröffentlicht.
- Grenzen: Vorschau-Skripte von Themes (`transport: postMessage`) laden die Vorschau neu; eigene JavaScript-Steuerelemente erscheinen nur in der echten WordPress-Verwaltung.

## ElvadoPress-Funktionen auf der WordPress-Website
`cms/wp-engine-front.php` liefert die Website aus und bindet die ElvadoPress-Funktionen ein: native Plugins (Seiten-Cache, SEO, Weiterleitungen …), 404-Weiterleitungen und -Protokoll, App-Modus, Live Builder, Mehrsprachigkeit (Sprachpräfix, `hreflang`), Vorschau, Demo-Betrieb und mehrere Websites (eigene Engine je Website). Die Engine-Schicht (`cms/src/Wp/mu/elvado-engine.php`) ergänzt:
- eingebaute Shortcodes `[forum]`, `[community]`, `[netzwerk]`, `[umfrage id="…"]`, `[faq]`, `[countdown]`, `[karte]`, `[kontakt]`, `[newsletter]`, `[widget …]` (`cms/lib/wp-shortcodes.php`) und das Assistenten-Widget,
- SEO-Felder (`noindex`, kanonische Adresse, Titel), Logo aus dem Branding,
- **KI-Verbinder:** Die Schlüssel der WordPress-Verbinder (Einstellungen → Verbinder, `connectors_ai_<anbieter>_api_key`) kommen aus der KI-Zentrale (`cms/data/.ai/gateway.json`); Speichern in WordPress schreibt dorthin und legt keine Kopie in den WordPress-Optionen ab (`cms/lib/wp-ai-connectors.php`).

## Sicherheit
- Plugins und Themes sind fremder PHP-Code: Installation, Aktivierung und Löschen nur für Administratoren. Das Entpacken verhindert Pfad-Tricks, Symlinks, `.htaccess`/`.user.ini` und begrenzt Größe und Dateianzahl.
- Ein Plugin oder Theme, das beim Start abstürzt, wird über den Absturzschutz der Engine deaktiviert bzw. auf `elvado-classic` zurückgesetzt.
- Keine automatische Telemetrie; `DISALLOW_FILE_EDIT`, WordPress-Cron und Core-Auto-Updates sind abgeschaltet – Updates steuert ElvadoPress.

## Tests
Die WordPress-Tests brauchen ein WordPress-ZIP (`WPE_TEST_ZIP`) und optional die SQLite-Integration (`WPE_TEST_SQLITE_ZIP`); ohne die Variablen werden sie übersprungen. Siehe [DEVELOPMENT.md](../../DEVELOPMENT.md).

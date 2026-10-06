# Handover – ElvadoPress

## Aktueller Stand
ElvadoPress ist die Hauptquelle des eigenständigen CMS. Version laut `cms/VERSION`: **1.0.0**. Das CMS ist PHP-basiert, dateibasiert mit optionalem Datenbankspiegel, besitzt eine WordPress-Kompatibilitätsschicht und optionale App-/Alexa-/KI-Erweiterungen. CI prüft Syntax, Smoke-Test und die vorhandenen Funktionstests.

## Zuletzt abgeschlossen
- Öffentliches ElvadoPress-Branding/README wurde aufgewertet.
- Dauerhafte Claude-Code-Arbeitsstruktur wurde eingerichtet: zentrale Regeln, Handover, Projektkarte, Architektur- und Development-Übersicht sowie bekannte Doku-Probleme.

- Neues Theme `cms/themes/elvado-baukasten` (Homepage-Baukasten: Startseite aus frei sortierbaren Abschnitten, Farben/Schrift/Breiten/Kopf/Fuß über den WordPress-Customizer, `theme_mods`); Doku in `cms/docs/THEMES.md`, Test `scripts/test-baukasten.php`. Smoke-Test erwartet nun beide neutralen Themes.
- Visueller Abschnitts-Editor „Homepage-Baukasten“ im CMS (Design-Menü): `cms/views/panel-baukasten.php`, `cms/assets/home-builder.js`/`baukasten.css`, API `wp_bk_get`/`wp_bk_save` (Option `elvado_bk_layout`, Schema in `cms/themes/elvado-baukasten/inc/layout.php`). Im Browser geprüft (Hinzufügen, Drag & Drop, Speichern, Vorschau).

- Radio-Erweiterung + Theme `cms/themes/elvado-radio` (laut.fm / Icecast / Shoutcast, Jetzt läuft, Verlauf, Sendeplan, Player-Leiste, Shortcodes). Menü „Radio“ im CMS nur bei aktivem Theme. Doku `cms/docs/THEMES.md`, Test `scripts/test-radio.php`. Im Browser geprüft (Theme, Menü sichtbar/versteckt, Panel); echte laut.fm-/Icecast-Server nur mit Fake-Abruf getestet.
- Alexa-Verdrahtung: Radio-Sender → Skill-Sender (`cms/lib/alexa.php`: `rrw_alexa_radio_*`, `radio_sync`), Skill-Backend nutzt `radio_api` für Titel/Sendeplan eigener Streams, Alexa-Menü mit Block „Radio-Theme“, Test `scripts/test-alexa-radio.php`. Das geänderte Backend (`lambda/index.js`) muss bei Amazon neu eingespielt werden.
- Sync nach ricorewi-radio: dort wird `scripts/smoke-test.php` als `test-standalone-build.php` übernommen (Erwartung: drei neutrale Themes). Nichts RicoReWi-Spezifisches im neuen Code.

- Band-Theme `cms/themes/elvado-band` (Poster-Stil) + generische Theme-Konfiguration `cms/lib/themeconf.php`/`band.php` (Menü „Band“ nur bei aktivem Theme, Editor aus Schema `cms/assets/theme-config.js`, API `themeconf_*`). Plugin-Haken in allen drei Themes (Radio, Baukasten, Band). Doku `cms/docs/THEMES.md`, Test `scripts/test-band.php`. Im Browser geprüft (Startseite, Player-Klick, Mobil, Band-Menü/Editor). Smoke-Test erwartet jetzt vier neutrale Themes.

- Freie Bilder in der Mediathek (Pixabay, Pexels, Unsplash, Openverse, Wikimedia Commons): `cms/lib/stockmedia.php`, `cms/assets/stock-media.js` (zentrale Bildauswahl), API `stock_*`, Bildnachweis in `meta.json`, Bild-Knopf im Beitrags-Editor, Anbindung an alle Bildauswahlen. Doku `cms/docs/MEDIA.md`, Test `scripts/test-stockmedia.php`. Echte Anbieter-APIs nur mit Fake-Abruf getestet; Schlüssel für Pixabay/Pexels/Unsplash legt der Betreiber unter Medien an.

- Creator-Theme `cms/themes/elvado-creator` (Influencer: Profil, Link-in-Bio `/links/`, Highlights, Feed, Empfehlungen mit Rabattcodes + Werbekennzeichnung, Drops mit Countdown, Mediakit, FAQ) + Schema `cms/lib/creator.php` (Menü „Creator“ über `themeconf.php`). Neuer Router-Filter `rrw_wp_404_status` (`cms/wp/router.php`) für virtuelle Theme-Seiten. Doku `cms/docs/THEMES.md`, Test `scripts/test-creator.php`. Smoke-Test erwartet jetzt fünf neutrale Themes.
- Kern-Infrastruktur (`cms/src/`, Namensraum `Elvado\`): PDO-Datenbankschicht + `schema.sql`, Multi-Anbieter-KI-Gateway (EvoLink, OpenAI, Anthropic, Google, OpenRouter, DeepSeek), Lovable-Bridge/Provider-Endpunkt/Shortcode, GitHub-Sync mit Webhook, React-Bündel `frontend/` → `cms/assets/react/`, Menü „KI & Lovable“. Doku `cms/docs/KI-LOVABLE.md`, Tests `scripts/test-core-db|ai-gateway|lovable-provider|github-sync|lovable-shortcode.php`. Nur mit Fake-Transport geprüft (DB zusätzlich auf MariaDB 10.11/SQLite); EvoLink-Details und Lovable-Skript-URL vom Betreiber prüfen.
- CMS-Update über GitHub (`cms/src/Update/`, `cms/update-{webhook,cron,rescue,health}.php`, `cms/assets/update-manager.js`, API `update_*`): Suche (Release/Beta nach SemVer, Branch), Einspielen mit Sicherung, Paketprüfung, Gesundheitsprüfung + automatischer Rückschritt, Überwachungsfenster, Downgrade, Notfall-Seite; Webhook/Cron/automatische Suche. Standard-Repository in `cms/lib/product.default.json` (`update_repo`, je Repository eigene Fassung). Workflow *Release* erzeugt bei neuer `cms/VERSION` auf main automatisch Tag, Release, ZIP + `.sha256`. Version 1.1.0. Doku `cms/docs/UPDATE.md`, Test `scripts/test-update.php`. Gegen echtes GitHub ungeprüft (Fake-Transport; Verwaltung im Browser gegen Fake-GitHub geprüft).
- Demo = Produkt-Homepage: Die ElvadoPress-Demo-Website ist jetzt eine echte Homepage von ElvadoPress, gebaut mit dem eigenen Baukasten-Theme (`cms/lib/demo-content.php`, `rrw_demo_seed_site()`), alle Funktionen frei (nur serversichernde Sperren, siehe `cms/docs/ELVADOPRESS.md`), Rücksetzen alle 10 Minuten unverändert. Nebenbei behoben: Baukasten-Karten laufen mobil nicht mehr über (`style.css`), Speicher-Badge der Verwaltung blieb trotz „hidden“ sichtbar. `scripts/test-demo.php` erweitert (48 Prüfungen).
- Fix „News-Magazin zeigt neue Beiträge nicht“ (RicoReWi Radio): Der Editor lieferte `published_at` als `YYYY-MM-DDTHH:MM`, gespeichert wurde roh und per Zeichenkettenvergleich gegen `Y-m-d H:i:s` geprüft („T“ sortiert hinter „ “ → Beitrag bis zum nächsten Tag unsichtbar, auch Sortierung falsch). Jetzt: Helfer `rrw_news_ts/rrw_news_date/rrw_news_cmp_desc` (`cms/lib/publish.php`), Normalisierung beim Speichern, Vergleiche/Sortierungen über Zeitstempel (API, RSS, Assistent, Lovable-Feed). Test `scripts/test-news-dates.php` + Erweiterung `test-demo.php`. **Lehre:** Ein Hotfix direkt in ricorewi-radio (`a9ca223`, geteilte Datei) wurde vom nächsten Sync überschrieben – Korrekturen an geteilten Dateien immer zuerst in ElvadoPress.

## Aktuell in Arbeit
Keine konkrete Anwendungscode-Aufgabe ist in diesem Repository als laufend dokumentiert.

## Bekannte Probleme
- `INSTALL.md` enthält noch historisch gewachsene RicoReWi-/Control-Center-Formulierungen, die teilweise nicht zum aktuellen eigenständigen ElvadoPress-Status passen. Siehe `KNOWN_ISSUES.md`.
- Der in `STATUS.md` dokumentierte offene App-Vorlagen-/App-Baukasten-/Alexa-Ausbau ist noch nicht als abgeschlossen ausgewiesen.

## Nächste sinnvolle Schritte
1. `INSTALL.md` fachlich gegen aktuellen ElvadoPress-Stand bereinigen, ohne weiterhin benötigte Connected-Mode-/Migrationshinweise zu verlieren.
2. Offene Punkte aus `STATUS.md` einzeln verifizieren und nur tatsächlich noch offene Arbeiten übernehmen.
3. Bei der nächsten Feature-Aufgabe gezielt den betroffenen Bereich anhand von `PROJECT_MAP.md` öffnen und die vorhandenen Fachtests verwenden.

## Wichtige Hinweise für die nächste Claude-Code-Session
- Zuerst `CLAUDE.md`, dann diese Datei und `PROJECT_MAP.md` lesen.
- Nicht das gesamte Repository erneut analysieren.
- `cms/docs/` enthält bereits umfangreiche Fach-Doku; keine parallelen Dokumentationen anlegen.
- Keine RicoReWi-spezifischen Inhalte in ElvadoPress einführen; Sync-/Bestandsschutzregeln in `CLAUDE.md` beachten.

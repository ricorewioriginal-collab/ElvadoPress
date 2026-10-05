# Handover – ElvadoPress

## Aktueller Stand
ElvadoPress ist die Hauptquelle des eigenständigen CMS. Version laut `cms/VERSION`: **1.0.0**. Das CMS ist PHP-basiert, dateibasiert mit optionalem Datenbankspiegel, besitzt eine WordPress-Kompatibilitätsschicht und optionale App-/Alexa-/KI-Erweiterungen. CI prüft Syntax, Smoke-Test und die vorhandenen Funktionstests.

## Zuletzt abgeschlossen
- Öffentliches ElvadoPress-Branding/README wurde aufgewertet.
- Dauerhafte Claude-Code-Arbeitsstruktur wurde eingerichtet: zentrale Regeln, Handover, Projektkarte, Architektur- und Development-Übersicht sowie bekannte Doku-Probleme.

- Neues Theme `cms/themes/elvado-baukasten` (Homepage-Baukasten: Startseite aus frei sortierbaren Abschnitten, Farben/Schrift/Breiten/Kopf/Fuß über den WordPress-Customizer, `theme_mods`); Doku in `cms/docs/THEMES.md`, Test `scripts/test-baukasten.php`. Smoke-Test erwartet nun beide neutralen Themes.
- Visueller Abschnitts-Editor „Homepage-Baukasten“ im CMS (Design-Menü): `cms/views/panel-baukasten.php`, `cms/assets/home-builder.js`/`baukasten.css`, API `wp_bk_get`/`wp_bk_save` (Option `elvado_bk_layout`, Schema in `cms/themes/elvado-baukasten/inc/layout.php`). Im Browser geprüft (Hinzufügen, Drag & Drop, Speichern, Vorschau).

- Radio-Erweiterung + Theme `cms/themes/elvado-radio` (laut.fm / Icecast / Shoutcast, Jetzt läuft, Verlauf, Sendeplan, Player-Leiste, Shortcodes). Menü „Radio“ im CMS nur bei aktivem Theme. Doku `cms/docs/THEMES.md`, Test `scripts/test-radio.php`. Im Browser geprüft (Theme, Menü sichtbar/versteckt, Panel); echte laut.fm-/Icecast-Server nur mit Fake-Abruf getestet.
- Sync nach ricorewi-radio: dort wird `scripts/smoke-test.php` als `test-standalone-build.php` übernommen (Erwartung: drei neutrale Themes). Nichts RicoReWi-Spezifisches im neuen Code.

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

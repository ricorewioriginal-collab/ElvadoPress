# Handover – ElvadoPress

## Aktueller Stand
ElvadoPress ist die Hauptquelle des eigenständigen CMS. Version laut `cms/VERSION`: **1.0.0**. Das CMS ist PHP-basiert, dateibasiert mit optionalem Datenbankspiegel, besitzt eine WordPress-Kompatibilitätsschicht und optionale App-/Alexa-/KI-Erweiterungen. CI prüft Syntax, Smoke-Test und die vorhandenen Funktionstests.

## Zuletzt abgeschlossen
- Öffentliches ElvadoPress-Branding/README wurde aufgewertet.
- Dauerhafte Claude-Code-Arbeitsstruktur wurde eingerichtet: zentrale Regeln, Handover, Projektkarte, Architektur- und Development-Übersicht sowie bekannte Doku-Probleme.

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

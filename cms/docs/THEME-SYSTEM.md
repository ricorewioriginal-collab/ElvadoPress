# Theme-System – Überblick

| Art | Was ist das | Gerendert von | Steuerung im Live Builder |
|---|---|---|---|
| **ElvadoPress-Themes** | Mitgelieferte Themes, z. B. „ElvadoPress Baukasten“ (Abschnitte als Komponenten), Radio, Band, Creator | ElvadoPress / WordPress-Laufzeit | Baukasten: Layout aus der Komponenten-Registry (Entwurf, Veröffentlichen, Verlauf, Planung) |
| **WordPress-Themes (echt)** | Themes aus dem WordPress-Verzeichnis oder als ZIP | **Echter WordPress-Core** (Engine aktiv) | Customizer des Themes; Header/Footer/Navigation über Regionen der Vorschau |
| **Portal-Themes eines Projekts** | Eigene Website eines Projekts (z. B. RicoReWi-Portal), `"pack"` in `theme.json` | Das Projekt selbst (eigenes HTML/CSS/JS bleibt unverändert) | **Gebundene Bereiche** (Paket-Komponenten): nur Gestaltung und Sichtbarkeit per CSS |

## Wie Themes und Live Builder zusammenspielen
- **Komponenten-Registry** (`cms/src/Components/`) beschreibt Bereiche (Schema, Regeln, Rendering). Das Baukasten-Theme rendert daraus; gebundene Bereiche (`bind`) rendert die Website selbst.
- **Layouts** liegen in `cms/data/layouts/<bereich>.json` (Entwurf, veröffentlicht, 25 Fassungen, Termin). Bereiche: `home`, `site:<name>`, `page:<slug>`, `post:<id>`.
- **Vorschau:** signierter, kurzlebiger Schlüssel; Vorschau-Brücke per `postMessage` mit Herkunfts-, Fenster- und Sitzungsschlüssel-Prüfung. Änderungen sind zuerst ein Entwurf; erst „Veröffentlichen“ macht sie öffentlich.
- **Theme-Wechsel (WordPress-Themes):** `ExtensionService::switchTheme` über den Wächter (Absturzschutz wie bei Plugins).

## Weiterführend
[THEMES.md](THEMES.md) (Themes im Einzelnen, Customizer), [LIVE-CUSTOMIZER.md](LIVE-CUSTOMIZER.md), [COMPONENTS.md](COMPONENTS.md), [ARCHITECTURE-WORDPRESS.md](ARCHITECTURE-WORDPRESS.md).

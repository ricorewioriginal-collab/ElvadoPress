# RicoReWi Radio CMS – Themes

## Eigenes Theme-System

Ein natives Theme besteht aus:

```
mein-theme/
  theme.json
  theme.css
  screenshot.png   optional
```

`theme.json` beschreibt Name, Version, Varianten, Standardwerte und Customizer-Regler.

## WordPress-Kompatibilität

Ein WordPress-Theme-ZIP kann importiert werden, wenn eine `style.css` vorhanden ist.

Das CMS übernimmt sicher:
- Theme-Metadaten aus `style.css`
- CSS
- Bilder
- Webfonts
- Screenshots
- statische Assets

Nicht ausgeführt werden:
- `functions.php`
- WordPress-PHP-Templates
- WordPress-Hooks
- serverseitige WordPress-Plugins

Warum: Ein vollständiges WordPress-PHP-Theme benötigt den WordPress-Core und dessen Laufzeit-API. Das RicoReWi CMS bleibt bewusst eigenständig und führt fremden PHP-Code nicht ungeprüft aus.

Das Ergebnis ist deshalb eine **WordPress-Kompatibilitätsschicht für Design und Assets**, nicht eine versteckte WordPress-Installation.

## Bootstrap-Kompatibilität

Themes mit Bootstrap-Struktur oder Bootstrap-CSS werden automatisch markiert.

Unterstützt:
- Bootstrap-CSS
- Grid-Klassen
- Buttons
- Karten
- Utilities
- eigene CSS-Varianten

Nicht automatisch geladen wird fremdes JavaScript aus einem ZIP. JavaScript-Erweiterungen gehören in das Plugin-System.

## Homepage-Baukasten (Theme „ElvadoPress Baukasten“)

Das mitgelieferte Theme `cms/themes/elvado-baukasten` ist ein WordPress-Theme (läuft in der WordPress-Schicht, Einstellungen als `theme_mods`) und dient als freier Baukasten für ein eigenes Design:

- **Startseite aus Abschnitten:** Im Live-Customizer (Design → Themes → Baukasten → *Anpassen*) lassen sich bis zu acht Positionen mit Abschnitten belegen: Hero, Textabschnitt, drei Vorteile, Bild + Text, Neueste Beiträge, Aufruf (Call to Action), eigenes HTML/Shortcodes. Jeder Typ erscheint höchstens einmal; die Reihenfolge ist frei.
- **Design:** Akzent-, Hintergrund-, Karten-, Text- und Hero-Farben, Schriftart für Überschriften/Fließtext (Systemschriften, kein Fremd-Server), Schriftgröße, Eckenradius, Inhalts- und Seitenleistenbreite, Abschnittsabstand, Kopfbereich (links/zentriert, fixiert), Seitenleiste links/rechts, Fußzeilentext, zusätzliches CSS.
- **Unterseiten** (Beiträge, Seiten, Archive, Suche, 404) nutzen das normale Layout mit Seitenleiste, Menüs (`primary`, `footer`) und Widgets (`sidebar-1`).
- Alle Werte werden serverseitig begrenzt/bereinigt (Farben als Hex, Zahlen mit Grenzen, HTML über `wp_kses_post`, URLs über `esc_url`); eigenes CSS kann den Style-Tag nicht verlassen.
- Test: `php scripts/test-baukasten.php`.

## Live-Customizer

Der Customizer unterstützt unter anderem:
- Farben
- Hintergründe
- Content-Breite
- Header-Höhe
- Sidebar-Breite
- Typografie-Skalierung
- Kartenstil
- Navigation
- Buttons
- Footer
- eigenes zusätzliches CSS
- Theme-Varianten
- Desktop / Tablet / Mobile Preview

## Mitgelieferte freie Themes

- RicoReWi Neon
- Broadcast Glass
- Clean Air
- Midnight Magazine
- Bootstrap Wave
- Signal Paper

Alle Theme-Pakete im Repository sind für dieses CMS erstellt und nicht aus fremden Themes kopiert.

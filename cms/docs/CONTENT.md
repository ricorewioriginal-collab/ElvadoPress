# ElvadoPress – Dateibasierte Inhalte

Das System ist vom Prinzip dateibasierter CMS wie Grav inspiriert, enthält aber keinen übernommenen Grav-Code.

## Struktur

```
cms/content/pages/
  ueber-uns/
    page.md
  kontakt/
    page.md
```

## Format

```markdown
---
title: "Über uns"
slug: "ueber-uns"
enabled: true
headline: "Über uns"
intro: "..."
---

## Überschrift

Inhalt ...
```

Der Markdown-Spiegel wird aus den CMS-Seiten erzeugt. So bleiben Inhalte:
- lesbar
- versionierbar
- portabel
- backup-freundlich

Die eigentliche öffentliche HTML-Seite wird weiterhin vom CMS generiert.

## Dateien (Git) → Website
Seiten und Beiträge lassen sich auch **als Dateien im Repository** pflegen; das CMS übernimmt neue und geänderte Dateien automatisch:

```
cms/content/pages/<slug>/page.md     # Seite
cms/content/posts/<slug>/post.md     # Beitrag
```

Beitrag:

```markdown
---
title: "Mein Beitrag"
status: published          # published | draft (Vorgabe: draft)
category: "Musik"
tags: "a, b"
excerpt: "Kurztext"
published_at: "2026-01-02 10:00"
---

## Zwischentitel

Text mit **fett**, *kursiv*, [Link](https://example.org), Listen (`- Punkt`), Zitate (`> …`) und Bildern (`![Alt](/pfad.jpg)`). Fertiges HTML ist ebenfalls erlaubt (wird bereinigt).
```

- **Übernahme:** beim Veröffentlichen, beim Neuaufbau im Deploy (`cms/rebuild.php`) und über `api.php?action=content_pull`. Das Manifest `cms/data/content-sync.json` merkt sich den Stand je Datei; nur Neues/Geändertes wird übernommen, der allererste Lauf merkt sich nur den Ist-Stand (nichts wird überschrieben).
- **Schutz:** der Spiegel Website → Datei überschreibt keine Datei, die sich von außen geändert hat. Bei gleichzeitiger Änderung in CMS und Datei gewinnt die Datei.
- Danach mit dem Sichtbarkeits-Check (`cms/docs/SICHTBARKEIT.md`) prüfen, ob alles erscheint.

## Konfiguration über Git: Menüs, Widgets, Themes, Plugins
Auch die Konfiguration lässt sich über Dateien in `cms/content/config/` steuern (gleiche Regeln wie bei Seiten und Beiträgen: Neues/Geändertes wird beim Veröffentlichen, im Deploy-Neuaufbau und über `api.php?action=content_pull` übernommen; der Spiegel Website → Datei überschreibt keine ungeprüfte Änderung von außen; ungültige Dateien werden nicht übernommen und gemeldet):

| Datei | Inhalt |
|---|---|
| `menus.json` | `{"top":[…],"bottom":[…]}` – Menüpunkte (`id`, `label`, `target`, `icon`, `parent_id`, `enabled`) |
| `widgets.json` | Liste der Widgets |
| `widget_areas.json` | Widget-Bereiche mit ihren Widgets |
| `theme.json` | Portal-Theme `{"active":"<id>","variant":"default","settings":{…}}` (Ordner `cms/themes/<id>/` mit `theme.json`) **oder** WordPress-Theme `{"wordpress":"<slug>"}` (Ordner `cms/themes/<slug>/` mit `style.css`) |
| `plugins.json` | `{"enable":["<plugin-id>"],"disable":["<plugin-id>"]}` – offizielle ElvadoPress-Plugins (Abhängigkeiten werden mitinstalliert) |

`menus`, `widgets`, `widget_areas` und `theme` werden zusätzlich aus dem Live-Stand gespiegelt, sodass Entwickler-Werkzeuge immer den aktuellen Zustand sehen. Neue Themes und Plugins (Code) kommen als Ordner ins Repository (`cms/themes/…`); die Aktivierung steuern `theme.json` und `plugins.json`.

**Themes im CMS entfernen:** jedes Theme außer dem aktiven und dem Standard-Theme. Über das CMS hochgeladene Themes werden gelöscht; Themes aus dem Code werden ausgeblendet (sie kämen beim Deploy sonst zurück) und lassen sich unter „Ausgeblendete Themes“ zurückholen.

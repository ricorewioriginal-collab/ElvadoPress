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
headline: "Über RicoReWi Radio"
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

Die eigentliche öffentliche HTML-Seite wird weiterhin vom RicoReWi-CMS generiert.

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

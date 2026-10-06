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

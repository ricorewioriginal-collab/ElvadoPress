# ElvadoPress – Speicher, Datenbank und Backups

## Standard: Datei-CMS

Primärdaten:
- `cms/data/site.json`
- `cms/data/news.json`

Das ist schnell, transparent und leicht sicherbar.

## Optional: SQLite / MySQL

Im CMS unter **System → Datenbank** kann ein Datenbankspiegel aktiviert werden.

Zugangsdaten werden serverseitig in:

`cms/data/database.local.php`

gespeichert. Diese Datei ist in `.gitignore` ausgeschlossen.

Tabellen:
- `rrw_cms_state`
- `rrw_cms_news`

## Backups

Unter **System → Backups** kann ein ZIP erzeugt werden.

Es enthält je nach Einstellung:
- site.json
- news.json
- Themes
- Plugins
- Markdown-Inhalte
- generierte eigene Seiten
- RSS / Sitemap / robots.txt
- optional den Medien-Hub

Restore verlangt Superadmin-Berechtigung und eine zusätzliche Bestätigung.

## Performance

Das Portal lädt Inhalte nicht aus einer Datenbank pro Seitenaufruf. Der veröffentlichte Stand liegt als statischer Snapshot / JSON / generierte Datei vor. Dadurch bleibt die öffentliche Seite schnell, auch wenn optional eine Datenbank angeschlossen ist.

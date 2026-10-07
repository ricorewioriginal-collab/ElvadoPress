# Sichtbarkeit von Seiten, Beiträgen und Menüs

Wenn etwas „nicht erscheint“, liegt es fast immer an einer dieser Bedingungen. Der **Sichtbarkeits-Check** (Verwaltung → Seiten bzw. Menüs → „Sichtbarkeit prüfen“, `api.php?action=visibility_report`, Kommandozeile `php scripts/visibility-report.php [cms/data]`) prüft sie und erklärt jeden Fall mit Lösungshinweis. Er liest nur und ändert nichts.

## Seiten
1. **Aktiviert** (`pages[].enabled` nicht `false`).
2. **Nicht geplant** (`publish_at` leer oder in der Vergangenheit; nur eigene Seiten).
3. **Im Menü** (nur Komfort): ein aktiver Menüpunkt `page:<id|slug>` bzw. `system:<ziel>`, dessen Obermenü ebenfalls aktiv ist. Ohne Menüpunkt ist die Seite per Direktlink erreichbar, aber nicht verlinkt.

## Beiträge
Status `published`, nicht im Papierkorb (`deleted_at`), `published_at` nicht in der Zukunft.

## Menüs
Ein Menüpunkt erscheint, wenn er und sein Obermenü `enabled` sind. Punkte, die auf nicht vorhandene oder deaktivierte Seiten zeigen, meldet der Check als „nicht sichtbar“.

## Inhalte über Git pflegen (Entwickler-Werkzeuge wie Codex/ChatGPT)
Seiten und Beiträge dürfen als **Dateien im Repository** gepflegt werden – sie werden automatisch ins CMS übernommen und nicht mehr überschrieben (Details: `cms/docs/CONTENT.md`):
- `cms/content/pages/<slug>/page.md` – Seite (Front Matter `title`, `slug`, `enabled`, `headline`, `intro`; Text als Markdown).
- `cms/content/posts/<slug>/post.md` – Beitrag (Front Matter `title`, `status: published|draft` – Vorgabe `draft` –, `category`, `tags`, `excerpt`, `published_at`).
- Übernahme beim Veröffentlichen, beim Neuaufbau im Deploy (`cms/rebuild.php`) und über `api.php?action=content_pull`. Danach den **Sichtbarkeits-Check** ausführen.

Menüs, Widgets, Theme und Plugins steuern `cms/content/config/*.json` (siehe CONTENT.md). **Nicht** direkt über Git ändern: `cms/data/*.json` – der Code-Deploy schützt `cms/data/`, und der Rückkanal Live-CMS → GitHub überschreibt solche Änderungen wieder. Weitere Einstellungen über die Verwaltung bzw. authentifizierte API (`save_section`) ändern.

## Warum Änderungen „automatisch zurückgesetzt“ werden konnten
- Der Spiegel Website → `page.md` (bei jedem Veröffentlichen und im Deploy-Neuaufbau) überschrieb Dateien aus Git. Jetzt schreibt er keine Datei, die sich seit dem letzten Abgleich von außen geändert hat; sie wird stattdessen vorher übernommen (Manifest `cms/data/content-sync.json`). Beim allerersten Lauf wird nur der Ist-Stand gemerkt.
- Beim Speichern bereinigt der Server die Daten (`rrw_clean_section`). Vorgaben, die nur einmalig angelegt werden (z. B. Favoriten-Menüpunkt, Partnerseite), werden nicht erneut ergänzt, sobald Menüs/Seiten gespeichert sind; unbekannte Systemseiten bleiben erhalten (bis 300 Seiten).

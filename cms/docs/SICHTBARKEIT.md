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

## Warum Änderungen „automatisch zurückgesetzt“ werden können
- **Inhalte liegen im Live-CMS** (`cms/data/site.json`, `news.json`, `cms/content/`), nicht im Code. Änderungen an diesen Dateien **im Git** erreichen die Live-Website nicht (der Code-Deploy schützt `cms/data/` und `cms/media/`) und werden vom Rückkanal Live-CMS → GitHub wieder überschrieben. Inhalte deshalb **nur über die Verwaltung bzw. die authentifizierte API** ändern (`site_save`/`save_section`, `news_*`), nie per Datei im Repository.
- Beim Speichern bereinigt der Server die Daten (`rrw_clean_section`). Vorgaben, die nur einmalig angelegt werden (z. B. Favoriten-Menüpunkt, Partnerseite), werden nicht erneut ergänzt, sobald Menüs/Seiten gespeichert sind; unbekannte Systemseiten bleiben erhalten (bis 300 Seiten).
- Danach den Check ausführen: er zeigt sofort, ob eine Seite deaktiviert, geplant oder aus dem Menü gefallen ist.

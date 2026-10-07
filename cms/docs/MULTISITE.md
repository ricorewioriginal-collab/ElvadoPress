# Mehrere eigenständige Websites (Multisite)

**Stand:** Grundlage (Registry, Domain-Zuordnung, Anlegen/Kopieren, Pfad-Auflösung) in `cms/lib/sites.php`, getestet mit `scripts/test-sites.php`. Die Bibliothek ist **noch nicht in die Auslieferung und die Verwaltung eingebunden** – ohne weitere Website verändert sie nichts. Die Einbindung folgt in Stufen (unten).

## Begriffe
- **Marke** (Domains & Branding): andere Gestaltung/Texte/Domain auf **denselben Inhalten** einer Website.
- **Website** (dieses Dokument): eigene **Inhalte, Einstellungen, Marken und Medien** – eine eigenständige Homepage. Mehrere Websites teilen sich Code, Benutzer/Anmeldung, Plugins und Erweiterungen.

## Aufbau
- **Hauptwebsite:** `cms/data`, `cms/media`, `cms/generated` – unverändert, ohne Registry.
- **Weitere Websites:** `cms/sites/<kennung>/{data,media,generated}`; `data` ist per `.htaccess` gesperrt.
- **Registry:** `cms/data/sites.json` (`items[]` mit `id`, `name`, `domains[]`, `enabled`, `created`). Ohne Datei gibt es keine weiteren Websites. Höchstens 30; reservierte Kennungen (`main`, `data`, `media`, `generated`, `cms`, `sites` …) sind gesperrt.
- **Zuordnung:** Hostname → Website (mit/ohne `www.`); unbekannte oder deaktivierte Domains gehören zur Hauptwebsite. In der Verwaltung wählt der Kopf-Umschalter die Website (Kontext `RRW_SITE_ID` der Anfrage).
- **Anlegen:** leer oder **als Kopie** einer Website (Einstellungen, Inhalte, Layouts, generierte Dateien; Medien auf Wunsch). Nicht kopiert werden Anmeldung, Sitzungen, Protokoll, Geheimnisse, Zwischenspeicher und alle versteckten Laufzeit-Zustände.
- **Domains:** jede Domain gehört genau einer Website (Konflikt = Fehlermeldung).

## Was je Website getrennt ist – und was gemeinsam bleibt
| Je Website | Gemeinsam (global) |
|---|---|
| `site.json` (Portal, Marken, Menüs, Widgets, Theme-Einstellungen), News, Kommentare, Benachrichtigungen, Layouts des Live Builders, Seiten-Revisionen | Code, Themes, Plugins und deren Zustand |
| Medien (`media`), generierte Dateien (`generated`) | Benutzer, Rollen, Sitzungen (`local-auth`, `local-sessions`) |
| Vorschau-Schlüssel, Marken-Registry, Domains & Branding | Update-/Backup-System, KI-Schlüssel, WordPress-Engine-Zustand |

## Einbindung in Stufen
1. **Grundlage** (fertig): Bibliothek und Tests.
2. **Native Ebene:** Die Pfad-Stellen für Inhalte (`$siteFile`, `$newsFile`, `$commentsFile`, `$revisionsFile`, `$notificationsFile`, `$newsViewsFile`, `$mediaDir`, `$genDir` in `cms/api.php`; `publish.php`, `components.php`/Layouts, `tools.php`, `seo.php`, `rss.php`, `rebuild.php` …) wechseln auf `rrw_site_dir()`; die Verwaltung sendet den Kontext im Kopf `X-EP-Site` (nur für angemeldete Administratoren mit lokaler Sitzung gültig). Die Hauptwebsite bleibt Byte für Byte gleich (Golden-Test, Marken-Fingerabdruck).
3. **Auslieferung:** `index.php`/`wp-front.php` wählen per Domain die Website; die WordPress-Emulation läuft mit `RRW_WP_DATA` je Website (Vorbild: Sandbox, `cms/wp/sandbox.php`). Medien-Adressen je Website (Umschreibregel). Weitere Websites laufen zunächst **ohne** WordPress-Engine im nativen Modus.
4. **WordPress-Engine je Website:** eigener Tabellen-Präfix in derselben Datenbank (Muster: WordPress-Multisite), Migration und Update-Center je Website.
5. **Verwaltung:** Bereich „Websites“ (anlegen, kopieren, Domains, DNS-Prüfung, deaktivieren), Kopf-Umschalter Website → Marke.

## Sicherheit und Stabilität
- Der Website-Kontext aus der Verwaltung wird nie aus Cookies übernommen (kein CSRF), sondern nur aus dem Kopf `X-EP-Site` zusammen mit einer gültigen Administrator-Sitzung; unbekannte Kennungen werden ignoriert.
- Ohne Registry, bei beschädigter Registry und bei deaktivierter Website gilt immer die Hauptwebsite – die Installation fällt nie aus.
- Kein Zugriff über Kennungen auf fremde Ordner: Kennungen werden auf `[a-z0-9-]` bereinigt, Pfade nur aus der Registry gebildet.
- Domains werden nur per DNS-Namensauflösung geprüft (keine Anfrage an fremde Server).

## Grenzen (Stufe 1)
Keine Auslieferung und keine Verwaltung weiterer Websites, solange Stufe 2–3 nicht eingebunden sind; die Bibliothek legt Websites an und löst Pfade auf, mehr nicht.

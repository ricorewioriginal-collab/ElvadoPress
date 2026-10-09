# Mehrere eigenständige Websites (Multisite)

**Stand:** Stufe 1 und 2 sind fertig: Bibliothek `cms/lib/sites.php` (`scripts/test-sites.php`), die API wählt die Website (Domain bzw. Verwaltungs-Kopf `X-EP-Site`, `scripts/test-sites-api.php`) und die Verwaltung hat den Bereich „Websites“ und einen Umschalter im Kopf. Stufe 3 (Auslieferung auf der eigenen Domain mit Theme/WordPress-Emulation, Feed, Medien-Adressen; `scripts/test-sites-front.php`) ist ebenfalls fertig; die WordPress-Engine je Website (Stufe 4) ist ebenfalls eingebunden (`scripts/test-sites-engine.php`, mit echtem WordPress `test-sites-engine-real.php`). Ohne weitere Website verändert sich nichts.

## Begriffe
- **Marke** (Domains & Branding): andere Gestaltung/Texte/Domain auf **denselben Inhalten** einer Website.
- **Website** (dieses Dokument): eigene **Inhalte, Einstellungen, Marken und Medien** – eine eigenständige Homepage. Mehrere Websites teilen sich Code, Benutzer/Anmeldung, Plugins und Erweiterungen.

## Aufbau
- **Hauptwebsite:** `cms/data`, `cms/media`, `cms/generated` – unverändert, ohne Registry.
- **Weitere Websites:** `cms/sites/<kennung>/{data,media,generated}`; `data` ist per `.htaccess` gesperrt.
- **Registry:** `cms/data/sites.json` (`items[]` mit `id`, `name`, `domains[]`, `enabled`, `created`). Ohne Datei gibt es keine weiteren Websites. Höchstens 30; reservierte Kennungen (`main`, `data`, `media`, `generated`, `cms`, `sites` …) sind gesperrt.
- **Zuordnung:** Hostname → Website (mit/ohne `www.`); unbekannte oder deaktivierte Domains gehören zur Hauptwebsite. In der Verwaltung wählt der Kopf-Umschalter die Website (Kontext `ELVADO_SITE_ID` der Anfrage).
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
2. **Native Ebene** (fertig für `cms/api.php` und alles, was darüber läuft; Auslieferung/Emulation folgt in Stufe 3): Die Pfad-Stellen für Inhalte (`$siteFile`, `$newsFile`, `$commentsFile`, `$revisionsFile`, `$notificationsFile`, `$newsViewsFile`, `$mediaDir`, `$genDir` in `cms/api.php`; `publish.php`, `components.php`/Layouts, `tools.php`, `seo.php`, `rss.php`, `rebuild.php` …) wechseln auf `elvado_site_dir()`; die Verwaltung sendet den Kontext im Kopf `X-EP-Site` (nur für angemeldete Administratoren mit lokaler Sitzung gültig). Die Hauptwebsite bleibt Byte für Byte gleich (Golden-Test, Marken-Fingerabdruck).
3. **Auslieferung** (fertig): `wp-front.php` (über `index.php` und die Umschreibregel) wählt per Domain die Website; die WordPress-Emulation läuft mit `ELVADO_WP_DATA = cms/sites/<kennung>/data/.wp` (Theme-Aktivierung `front-on`, Optionen) und liest Beiträge/Seiten/Menüs aus den Daten der Website (`ELVADO_WP_CMS_DATA`). Beim Anlegen werden Theme-Aktivierung und -Optionen der Vorlage übernommen (leere Website: Theme der Hauptwebsite). Medien-Adressen `/cms/media/…` werden in der ausgelieferten Seite auf `/cms/sites/<kennung>/media/…` umgeschrieben (gespeichert bleibt die neutrale Form). `cms/rss.php` liefert den Feed der aufgerufenen Domain. Weitere Websites laufen **ohne** WordPress-Engine im nativen Modus.
4. **WordPress-Engine je Website** (fertig): jede Website hat ihren eigenen Engine-Zustand (`cms/sites/<kennung>/data/.wp-engine`: Betriebsart, Präfix, Schlüssel, Migration) und ihr eigenes Tabellenpräfix (Standard `wpk<6 Zeichen>_`, Hauptwebsite `wpk_`) in der **einen gemeinsamen Datenbank** des CMS (`cms/data/database.local.php`, Zugangsdaten nur einmal). Präfixe dürfen weder der CMS-WordPress-Schicht noch einer anderen Website gehören. Der WordPress-Core (`cms/wp-engine/core-<Version>`) ist gemeinsam; beim Aufräumen bleiben Versionen, die eine andere Website nutzt. Die WordPress-Nachbildung weiterer Websites nutzt eine eigene SQLite-Ablage in `.wp` (keine gemeinsamen MySQL-Tabellen mit der Hauptwebsite).
5. **Verwaltung** (fertig): Bereich „Websites“ (anlegen, kopieren, Domains, DNS-Prüfung, aktivieren/deaktivieren, zum Verwalten wechseln), Kopf-Umschalter Website (gekennzeichnet, wenn nicht die Hauptwebsite) und Marke; „Domains & Branding“ gilt auch im eigenständigen CMS.

## Sicherheit und Stabilität
- Der Website-Kontext aus der Verwaltung wird nie aus Cookies übernommen (kein CSRF), sondern nur aus dem Kopf `X-EP-Site` zusammen mit einer gültigen Administrator-Sitzung; unbekannte Kennungen werden ignoriert.
- Ohne Registry, bei beschädigter Registry und bei deaktivierter Website gilt immer die Hauptwebsite – die Installation fällt nie aus.
- Kein Zugriff über Kennungen auf fremde Ordner: Kennungen werden auf `[a-z0-9-]` bereinigt, Pfade nur aus der Registry gebildet.
- Domains werden nur per DNS-Namensauflösung geprüft (keine Anfrage an fremde Server).

## Grenzen (aktueller Stand)
- Die Vorschau-Rahmen im Live Builder/Customizer zeigen für weitere Websites noch die Hauptwebsite (Vorschau-Schlüssel gelten je Website, Rahmen-Adresse folgt).
- Weitere Entry-Points (`radio.php`, `rest.php`, Sitemap/Robots der Plugins) nutzen noch die Hauptwebsite; Medien in der Verwaltung zeigen Bild-Adressen der Hauptwebsite.
- Einige Bibliotheken leiten ihren Ordner selbst ab (`dirname(__DIR__) . '/data'`, z. B. Plugin-Zustand, Alexa, Backup) und bleiben global, bis sie auf `elvado_site_dir()` umgestellt sind.

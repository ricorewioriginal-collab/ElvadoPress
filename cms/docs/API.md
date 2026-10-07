# ElvadoPress API

Version: 1.1

## Architektur

Das CMS lebt vollständig unter `/cms/`. Login und Berechtigungen kommen entweder vom AnMaCha Control Center oder von einem eigenständigen lokalen CMS-Zugang.

- CMS: `/cms/`
- Verwaltungs-API: `/cms/api.php?action=...`
- Öffentliche, lesende Konfiguration: `/cms/api.php?action=public`
- Dateispeicher: `/cms/data/site.json`, `/cms/data/news.json`
- Markdown-Spiegel: `/cms/content/pages/<slug>/page.md`
- Medien-Hub: `/cms/media/`
- Themes: `/cms/themes/`
- Plugins: `/cms/plugins/`
- Backups: `/cms/backups/`
- RSS live: `/cms/rss.php`
- RSS Kurzadresse: `/feed/`
- RSS statischer Mirror: `/rss.xml`
- Sitemap: `/sitemap.xml`
- Robots: `/robots.txt`

Schreibende (und die meisten lesenden) Aufrufe benötigen einen Sitzungstoken:

```
X-AnMaCha-Token: <session-token>
```

Der Header-Name ist historisch; er trägt sowohl AnMaCha-Control-Center-Tokens als auch
Tokens aus dem lokalen CMS-Login (siehe unten) – letztere sind am Präfix `local_` erkennbar.

## Anmeldung

Es gibt zwei gleichwertige Wege, einen gültigen Token zu bekommen:

1. **AnMaCha Control Center**: Login unter `/statistik/login.html`, der Token landet in
   `localStorage`/`sessionStorage` unter `anmacha_session_token` und wird serverseitig gegen
   `https://www.ricorewi-radio.de/statistik/cron.php?action=radio_cms_access` geprüft.
2. **Lokaler CMS-Zugang** (unabhängig vom Control Center):
   - `GET ?action=local_auth_status` → `{ "configured": bool }`
   - Noch kein Zugang eingerichtet: `POST ?action=local_auth_setup` mit `{ "username", "password" }`
     (Passwort mind. 8 Zeichen) legt den ersten Admin an und liefert direkt einen Token zurück.
   - Danach: `POST ?action=login` mit `{ "username", "password" }` → `{ "token": "local_..." }`
   - Zugangsdaten ändern, wenn bereits eingerichtet: `POST ?action=local_auth_setup` erfordert
     dann einen gültigen Superadmin-Token.
   - `POST ?action=logout` invalidiert den aktuellen lokalen Token.
   - `local_auth_status` meldet zusätzlich `standalone`, `install_needed` und `product`. Auf einer frischen
     Installation (`install_needed: true`) ist `local_auth_setup` gesperrt (HTTP 409); das Konto entsteht im
     Einrichtungsassistenten `cms/install.php`.

Im **eigenständigen Betrieb** (`cms/data/system.local.json`, `control_center: false`) werden Control-Center-Tokens
abgelehnt und keine Anfragen ans Control Center gestellt. Weitere Aktionen: `product` (öffentlich: Produktname),
`system_get` und `system_save` (Administrator: Betriebsmodus, Produktname, Version, `?checksum=1` für die
Prüfsumme). `access` liefert zusätzlich `standalone`, `product` und `version`. Details: [STANDALONE.md](STANDALONE.md).

Lokale Zugangsdaten liegen ausschließlich in `cms/data/local-auth.local.php` (Passwort-Hash,
nicht im Klartext, nicht in `site.json`), Sitzungen in `cms/data/local-sessions.local.json`.
Beide Dateien sind serverseitig erzeugt und nicht Teil des Git-Repos.

Die CMS-Login-Maske (`/cms/`) zeigt automatisch das passende Formular: Einrichtung, falls noch
kein lokaler Zugang existiert, sonst Login – mit einem Link, um stattdessen das AnMaCha Control
Center zu nutzen.

## CMS-Bereiche speichern

`POST ?action=save`

```json
{
  "section": "portal",
  "value": {}
}
```

Unterstützte Bereiche:

- portal
- pages
- menus
- widgets
- widget_areas
- branding
- social
- apps
- legal
- feed_sources
- rss
- theme
- plugins
- seo
- storage
- backup

## Medien

- `media_library_list`
- `media_library_upload`
- `media_library_delete`
- `branding_assign`

Der Medien-Hub kann aus einem Upload mehrere WebP-Größen erzeugen und Branding-Einträge direkt auf Medien-Hub-Dateien verweisen lassen.

## Themes

- `themes_list`
- `theme_upload`
- `theme_activate`
- `theme_customize_save`
- `theme_delete`

Details: [THEMES.md](THEMES.md)

## Plugins

- `plugins_list`
- `plugin_upload`
- `plugin_toggle`
- `plugin_delete`

Details: [PLUGINS.md](PLUGINS.md)

## Backup / Restore

- `backup_list`
- `backup_create`
- `backup_restore`
- `backup_download&name=...`

Restore und Download sind Superadmin-geschützt.

## Optionale Datenbank

- `database_status`
- `database_config_save`
- `database_push`
- `database_pull`

Unterstützt:
- SQLite
- MySQL / MariaDB

Das Datei-CMS bleibt Standard. Die Datenbank kann als synchronisierter Spiegel verwendet werden. Zugangsdaten liegen nur in `cms/data/database.local.php`, nicht in `site.json`.

## SEO

- Bereich `seo` über `save`
- `seo_rebuild`

Erzeugt:
- `/sitemap.xml`
- `/robots.txt`
- statische Meta-Tags in `index.html`

## Dateibasierte Inhalte

- `content_scan`
- `content_sync`

Eigene CMS-Seiten werden zusätzlich als Markdown mit Frontmatter unter `/cms/content/pages/` gespiegelt. Das ist ein eigenes, Grav-inspiriertes Prinzip; es verwendet keinen Grav-Code.

## News

- `news_list`
- `news_get&id=...`
- `news_save`
- `news_delete`
- `news_thumbnail_upload`
- öffentlich: `news_public`

## RSS / externe Quellen

- eigener Live-Feed: `/cms/rss.php`
- Kurzadresse: `/feed/`
- statischer Mirror: `/rss.xml`
- Feed-Test: `feed_test`
- externe Quellen: `feed_sources`

## Publish-Verhalten

Beim Speichern werden automatisch aktualisiert:

1. `cms/data/site.json`
2. CMS-Snapshot in `index.html`
3. eigene `.html`-Seiten
4. Markdown-Spiegel
5. RSS
6. Sitemap + robots.txt
7. optionaler Datenbankspiegel

Die öffentliche Konfiguration enthält keine Tokens, Passwörter oder Benutzerrechte.

## Multi-Domain & Branding

Eine Website, mehrere Marken (siehe `BRANDS.md`). Die wirksame Marke ergibt sich aus dem Hostname
der Anfrage; `?rrw_brand=<kennung>` erzwingt eine Marke (Vorschau).

- `GET api.php?action=brand` – öffentliche Markeninfo: `brand`, `name`, `short_name`, `claim`,
  `hostname`, `primary_domain`, `domains`, `origin`, `logo`, `favicon`, `touch_icon`, `og_image`,
  `title`, `title_suffix`, `description`, `canonical_url`, `legal`, `overrides`.
- `GET api.php?action=brands_public` – aktive Marken mit Domains.
- `GET api.php?action=public` – enthält zusätzlich `brand` und `config.brands` (Registry).
- `POST api.php?action=save` `{section:"brands", value:{default, items:[...]}}` – Registry speichern.
- `POST api.php?action=brand_asset_assign` `{brand_id, kind, path|url, size}` – Asset aus dem
  Medien-Hub zuweisen; `kind` ∈ logo, logo_dark, logo_light, favicon, touch_icon, og_image, social_image.
- `GET /manifest.php` – Manifest der Marke; `GET /` bzw. `/index.html` läuft über `index.php`
  (brandabhängiger `<head>`, `window.__RRW_BRAND__`).

Alle Antworten senden `Vary: Host`.

## Gleichzeitiges Bearbeiten (CMS und Control Center)

Alle Oberflächen (CMS, Control Center, weitere Clients) schreiben über dieselbe Aktion `save` in dieselbe Datei `cms/data/site.json`. Damit sich Änderungen nicht gegenseitig überschreiben:

- `save` sperrt die Datei, liest sie **neu** und ändert nur den gesendeten Bereich. Gleichzeitige Speichervorgänge in verschiedenen Bereichen gehen nicht mehr verloren.
- `get` (und `revs`) liefert `revs`: eine Versionskennung je Bereich. `save` liefert die neue Kennung als `rev` zurück.
- Wer beim Speichern `base_rev` mitschickt (die Kennung, auf der sein Formular beruht), bekommt bei einem inzwischen geänderten Bereich HTTP **409** mit `status: "conflict"` und die aktuelle `rev`, statt still zu überschreiben. Der Client lädt dann neu und zeigt die Änderungen an.
- Ohne `base_rev` verhält sich `save` wie bisher (letzter Speicherstand gewinnt, aber ohne Verlust anderer Bereiche). **Clients im Control Center sollten `base_rev` senden**, sonst ist der Schutz für sie nur teilweise wirksam.
- Das CMS selbst sendet `base_rev` automatisch (`cmsApi` in `cms-app.js`).

## Beitrags-Import

`POST ?action=news_import` (angemeldet) mit `{ "articles": [...], "as_draft": true, "on_duplicate": "skip" | "copy" }` – Gegenstück zu `news_export`.
Höchstens 200 Beiträge je Aufruf; jeder bekommt eine neue ID, Texte werden wie beim Speichern bereinigt, der importierende Benutzer wird Autor. Bei vorhandener Adresse (Slug) wird übersprungen oder mit Suffix `-2` angelegt. Antwort: `{ imported, skipped, invalid }`.


## Stabile REST-API (Version 1): `cms/rest.php`
Für Apps, Frontends und eigene Clients. Intern: **API → Dienst (Content/Media/Navigation) → Adapter → (ElvadoPress-Daten | echter WordPress-Core)** – Clients hängen nie direkt an WordPress.

- **Aufruf:** `/cms/rest.php/<Route>` (oder `?route=/<Route>`). **Anmeldung:** `Authorization: Bearer <Sitzungsschlüssel>` oder `X-AnMaCha-Token` – nie per Cookie (dadurch kein CSRF). Der Schlüssel kommt wie bei der Verwaltung aus `api.php?action=login`.
- **Antwort:** `{"status":"ok","data":…,"meta":{…}}` bzw. `{"status":"error","message":…}` mit passendem HTTP-Code (401, 403, 404, 405, 409, 422). Kopfzeile `X-ElvadoPress-API: 1`.
- **Routen:**

| Methode | Route | Bedeutung |
|---|---|---|
| GET | `/` | Version, Engine-Betriebsart, Routen |
| GET, POST | `/pages`, `/posts` | Liste (`status`, `search`, `category`, `page`, `per_page`) bzw. anlegen (201) |
| GET, PUT/PATCH, DELETE | `/pages/{id}`, `/posts/{id}` | lesen, ändern, löschen (`?force=1` endgültig) |
| GET | `/categories`, `/tags` | Begriffe |
| GET, POST, DELETE | `/media`, `/media/{id}` | Mediathek (Upload: `multipart/form-data`, Feld `file`) |
| GET | `/navigation`, `/navigation/{id}` | Menüs und Menübäume |
| GET | `/components`, `/layouts/{bereich}` | Komponenten-Katalog, Layouts (`home`, `site:<name>`, `page:<name>`, `post:<nr>`) |
| GET | `/status` | Systemstatus (nur Administratoren) |

- **Quelle:** aktive Engine ⇒ WordPress; sonst die ElvadoPress-Daten (**nur lesend**, Schreiben ergibt 409); `?source=native` erzwingt die ElvadoPress-Daten. Rechte wie in der Verwaltung (Administratoren alles; Autoren Inhalte/Medien nach Besitz).
- **Modell:** Beitrag/Seite: `id, type, title, slug, content, excerpt, status (published|draft|scheduled|private|trash), date, modified, author, categories, tags, parent, image, owner`.
- Tests: `scripts/test-wp-engine-api.php` (Rechte, Methoden, Eingaben, CRUD gegen echtes WordPress).

## Systemstatus und Update-Center (Verwaltung)
`engine-api.php?action=system_status` (Einträge mit OK/Warnung/Fehler und deutscher Erklärung), `updates_overview` (zwischengespeicherte Ergebnisse, ohne Netz) und `updates_check` (POST: fragt ElvadoPress, WordPress-Core und – bei aktiver Engine – WordPress-Plugins/-Themes ab). Oberfläche: System → Systemstatus, Updates. Logik: `cms/src/Wp/SystemStatus.php` (Test `scripts/test-wp-engine-status.php`).

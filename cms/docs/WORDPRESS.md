# WordPress-Kompatibilität

Das CMS enthält eine eigene Implementierung der WordPress-API (`cms/wp/`), damit WordPress-**Plugins** (und in späteren Stufen -**Themes**) mit PHP laufen. Es ist kein WordPress-Quelltext enthalten – nur Funktionen, Klassen und Hooks mit denselben Namen und Verhalten.

## Stand (Stufe 1: Kern)
- **Hooks:** `add_action`/`add_filter`/`do_action`/`apply_filters`, Prioritäten, `remove_*`, `has_*`, `did_action`, `doing_*` (auch spät hinzugefügte Hooks laufen wie in WordPress noch).
- **Shortcodes:** `add_shortcode`, `do_shortcode`, `shortcode_atts`, `[tag]…[/tag]`, `[[maskiert]]`, verschachtelt. Aufgelöst werden sie in Beiträgen (`news_public`, RSS), Seiten-Blöcken und HTML-Widgets (beim Veröffentlichen).
- **Eingebaute Shortcodes:** `[forum]`, `[community]`, `[netzwerk]`, `[umfrage id="…"]`, `[faq]Frage⏎Antwort⏎⏎…[/faq]`, `[countdown target="2030-01-01 12:00"]`, `[karte lat lon]`, `[kontakt]`, `[newsletter]`, `[widget type="…" …]` (jedes CMS-Widget), dazu `[caption]`, `[audio]`, `[video]`.
- **Optionen/Transients/Objekt-Cache:** `get_option`, `update_option`, `set_transient` … (Speicherung in `cms/data/.wp/options.json`).
- **Escaping/Sanitizing:** `esc_html/attr/url/js`, `sanitize_*`, `wp_kses` (+ `_post`/`_data`), `wpautop`, `add_query_arg` u. v. m.
- **Übersetzung:** `__`, `_e`, `_n`, `esc_html__` … (Originaltext; Deutsch ist die Zielsprache).
- **Plugins:** Erkennen (Plugin-Header), aktivieren/deaktivieren/löschen, Aktivierungs-Hooks, `wp_enqueue_script/style`, Einstellungs-API (`register_setting` …), Cron (`wp_schedule_event`, `cron/wp-cron.php`), `wp_mail`, `wp_remote_get/post`, Nonces, Rechte (`current_user_can`).
- **Plugin-Verwaltung im CMS:** Einstellungen → *WordPress-Plugins*: Suche im WordPress.org-Verzeichnis, Installation mit einem Klick, ZIP-Upload, aktivieren/deaktivieren/löschen. Nur Administratoren.

## Stufe 2: Datenbank und Inhaltsmodell
- **`$wpdb`:** `prepare` (`%s %d %f %i`), `query`, `get_var/row/col/results` (`OBJECT`, `ARRAY_A`, `ARRAY_N`, `OBJECT_K`), `insert/update/delete/replace`, `esc_like`, `table_exists`, `dbDelta`, `maybe_create_table`. Datenbank: MySQL, wenn unter *Datenbank* im CMS konfiguriert, sonst SQLite (`cms/data/.wp/wp.sqlite`). Für SQLite wird MySQL-SQL übersetzt (`ON DUPLICATE KEY UPDATE`, `INSERT IGNORE`, `SQL_CALC_FOUND_ROWS`/`FOUND_ROWS()`, `SHOW TABLES/COLUMNS`, `CONCAT`, `DATE_SUB(… INTERVAL …)`, `FIND_IN_SET`, `CREATE TABLE` mit `AUTO_INCREMENT`/`KEY` u. v. m.). **Der MySQL-Weg ist noch nicht gegen einen echten MySQL-Server getestet.**
- **Schema:** alle WordPress-Tabellen (`wp_posts`, `wp_postmeta`, `wp_terms`, … `wp_options`) werden beim ersten Bedarf angelegt. IDs der Datenbank-Inhalte beginnen bei 100 000 000.
- **CMS-Inhalte im WordPress-Modell:** Beiträge (news.json) erscheinen als `WP_Post` vom Typ `post`, eigene Seiten als `page`; Kategorie und Schlagwörter der Beiträge als Begriffe (`category`, `post_tag`); Titelbild als Anhang; Autoren als Benutzer. Lesend – bearbeitet wird weiterhin im CMS. Von WordPress-Code angelegte Inhalte (`wp_insert_post`) stehen in `wp_posts` und erscheinen in Abfragen zusammen mit den CMS-Inhalten.
- **`WP_Query`/`get_posts`/Schleife:** `post_type` (auch `any`), `post_status`, `p`, `name`, `pagename`, `post__in/not_in`, `post_parent`, Autor, Suche (mit Phrasen und `-`), Kategorie/Schlagwort (`cat`, `category_name`, `tag`, `tag__and` …), `tax_query`, `meta_key/meta_value`, `meta_query` (alle Vergleiche und Typen), `date_query`, `year/monthnum/day`, `orderby` (mehrere, `meta_value_num`, `post__in`, `rand`), `paged`, `offset`, `fields`, `ignore_sticky_posts`, `found_posts`, `max_num_pages`; Haken `pre_get_posts`, `the_posts`, `posts_pre_query`; bedingte Tags (`is_home`, `is_single` …); `have_posts()/the_post()/wp_reset_postdata()`. Nicht möglich: Plugins, die die SQL-Klauseln (`posts_where`, `posts_join` …) umschreiben – diese Abfrage nutzt kein SQL.
- **Beiträge schreiben:** `wp_insert_post/update/delete/trash`, Slugs, Haken (`save_post`, `transition_post_status` …), Meta-API für Beitrag/Benutzer/Begriff/Kommentar (Arrays bleiben Arrays), `register_post_type`/`register_taxonomy`, `wp_insert_term`, `wp_set_object_terms` u. a.
- **Benutzer:** `get_userdata`, `get_user_by`, `get_users`, `wp_create_user`, Benutzer-Meta, Rollen und Rechte.

## Stufe 3: Theme-Laufzeit (klassische PHP-Themes)
Die Website kann mit einem echten WordPress-Theme ausgeliefert werden (CMS → Einstellungen → **WordPress-Themes**).
- **Ablauf:** `cms/wp-front.php` ist der Front-Controller. Er ist aktiv, solange die Datei `cms/data/.wp/front-on` existiert (Aktivieren eines Themes) oder eine Vorschau läuft. `index.php` übergibt die Startseite, eine Umschreibregel in `.htaccess` alle weiteren nicht vorhandenen Pfade (Beiträge `/slug/`, Seiten, `/category/…`, `/tag/…`, `/author/…`, `/YYYY/MM/`, `/?s=`, `/page/N/`). Ohne aktives Theme ändert sich nichts.
- **Inhalte:** CMS-Beiträge, -Seiten, Kategorien, Schlagwörter, Autoren und Kommentare erscheinen als `WP_Post`/`WP_Query`; die Template-Hierarchie (`single-…`, `page-…`, `category-…`, `archive`, `search`, `404`, `home`, `front-page` …), The Loop, `get_header/footer/sidebar/template_part`, `wp_head/wp_footer`, Titel-Tag, `body_class/post_class`, Pagination und Beitrags-Navigation sind enthalten.
- **Menüs & Widgets:** `wp_nav_menu` liest die CMS-Menüs (erster Ort → Hauptmenü, Fuß-/Social-Orte → Fußmenü), Sidebars liefern die WordPress-Standard-Widgets (Suche, Neueste Beiträge/Kommentare, Archiv, Kategorien, Meta …).
- **Kommentare:** Das Formular sendet an `/wp-comments-post.php` und schreibt nach den CMS-Regeln (Freigabe, Sperrfilter, Rate-Limit) in die CMS-Kommentare.
- **Vorschau:** „Vorschau“ lädt die Website mit einem signierten, 15 Minuten gültigen Schlüssel (`?rrw_wp_preview=…`, Cookie) im Overlay – ohne das Theme zu aktivieren. `?rrw_wp_preview=off` beendet sie.
- **Stabilität:** Ein Fehler im Theme oder Plugin ergibt eine kurze Fehlerseite (HTTP 500, Details in `cms/data/.wp/debug.log`) statt weißer Seite. „Zurück zum CMS-Portal“ schaltet die Theme-Auslieferung jederzeit wieder ab. Nach einem 404 greifen die CMS-Weiterleitungen und das 404-Protokoll.
- **Mitgeliefert:** `cms/themes/rrw-classic` (schlichtes Standard-Theme). Getestet mit Twenty Nineteen/Seventeen/Twenty/Twenty-One und Astra (`php scripts/test-wp-theme.php [theme …]`).
- **Grenzen:** Block-Themes (Full Site Editing) und der Customizer sind nicht enthalten (Customizer-Klassen sind Platzhalter), Theme-Texte erscheinen deutsch, sobald das Sprachpaket geladen ist (sonst Englisch), WooCommerce-Weichen liefern immer „nein“.

## Stufe 4: Plugin-Seiten, REST-API, Ajax, Updates
- **Plugin-Seiten:** CMS → Plugins → **Einstellungen**. Menüs, die Plugins per `add_menu_page`/`add_options_page`/`add_submenu_page` (Hook `admin_menu`) anlegen, erscheinen als Schaltflächen. Die Seite läuft in einem abgeschotteten Rahmen (iframe, `sandbox` ohne `allow-same-origin`): Der Plugin-Code kann weder auf das CMS noch auf dessen Anmeldung zugreifen. Formulare, Links und Ajax-Aufrufe leitet eine kleine Brücke an die CMS-API.
- **Einstellungs-API:** `register_setting`, `add_settings_section/field`, `settings_fields`, `do_settings_sections`, `submit_button` und das Speichern über `options.php` (nur registrierte Optionen, Sicherheitscode, `sanitize_callback`). `admin-post.php` (`admin_post_{action}`) und `wp_redirect(); exit;` funktionieren – ein `exit`/`die` im Plugin beendet nur den Aufruf, nicht das CMS.
- **Ajax:** `admin-ajax.php` für angemeldete Administratoren (`wp_ajax_{action}`, im CMS) und für Besucher (`wp_ajax_nopriv_{action}`, `/wp-admin/admin-ajax.php`, nur bei aktivem Theme). `jQuery` liegt lokal im CMS (kein CDN).
- **REST-API:** `/wp-json/` (und `?rest_route=`) mit `register_rest_route` (Methoden, `args` mit `required`/`default`/`type`/`enum`/`validate_callback`/`sanitize_callback`, `permission_callback`, `WP_REST_Request/Response`, `WP_Error`) sowie `wp/v2/posts`, `pages`, `categories`, `tags`. Besucher sind nicht angemeldet: Routen mit Rechteprüfung antworten 401. Nur bei aktivem WordPress-Theme erreichbar.
- **Widgets:** Unter *Plugin-Seiten → Widgets* (Gruppe „Design“) lassen sich die Widgets der Seitenleisten des aktiven Themes hinzufügen, bearbeiten (`WP_Widget::form/update`), sortieren und entfernen.
- **Updates:** „Nach Updates suchen“ vergleicht installierte Plugins/Themes mit dem WordPress-Verzeichnis (6 h zwischengespeichert) und aktualisiert auf Knopfdruck. Vorher ein Backup anlegen.
- **Menü-Standorte:** *Plugin-Seiten → Menüs* (Gruppe „Design“) ordnet jede Menüposition des Themes dem CMS-Hauptmenü, dem CMS-Fußmenü oder „kein Menü“ zu.
- **Anpassen (Customizer, vereinfacht):** *Plugin-Seiten → Anpassen* zeigt alles, was das Theme über `customize_register` anbietet (Text, Textfeld, Auswahl, Radio, Checkbox, Zahl, Farbe, Bild-Adresse …) als Formular und speichert es als Theme-Optionen (`get_theme_mod`) bzw. Option. Die Live-Ansicht dazu steht im Bereich Design → Themes (Abschnitt „Live-Customizer für WordPress-Themes“). Eigene JavaScript-Steuerelemente von Themes erscheinen nicht.
- **Übersetzungen:** `__()`, `_x()`, `_n()`, `_nx()` und `load_*_textdomain` lesen `.mo`-Dateien aus `cms/wp-content/languages/` (Core `de_DE.mo`, `themes/<slug>-de_DE.mo`, `plugins/<slug>-de_DE.mo`). Das CMS lädt die deutschen Pakete von translate.wordpress.org: automatisch beim Installieren eines Plugins/Themes (und Aktualisieren) sowie über „Übersetzungen laden“. Ohne Paket bleibt der Originaltext.
- Tests: `php scripts/test-wp-admin.php`, `php scripts/test-wp-i18n.php [--live]`.

## Stufe 5: Große Plugins
- Getestet (`php scripts/test-wp-plugins.php`, SQLite und echte MariaDB): Classic Editor, Contact Form 7, Yoast SEO, WooCommerce, Elementor, Shortcodes Ultimate, Hello Dolly.
- WooCommerce: Produkte anlegen/lesen, Preise, `[products]`, Shop- und Produktseite, Store API (die v3-API verlangt Anmeldung). Elementor: Builder-Inhalte werden gerendert. Contact Form 7: Formulare validieren und versenden Mail. Yoast: Meta, Open Graph, Schema.
- Tabellen, die Plugins direkt per SQL nutzen, legt `$wpdb` bei Bedarf an. Mit SQLite übersetzt `RRW_SQL_Translator` MySQL-SQL.
- Aktivierungs-Weiterleitungen der Plugins (Einrichtungsassistenten) werden unterdrückt; `wp_redirect()` beendet im Admin-Bereich die Anfrage über eine Ausnahme statt über `exit`.
- `$wpdb` läuft mit MySQL/MariaDB oder SQLite, PostgreSQL nur für die CMS-Spiegelung.

## Stufe 6: Block-Themes (Full Site Editing)
- Themes mit `templates/*.html` und `theme.json` werden erkannt (`wp_is_block_theme()`), z. B. Twenty Twenty-Four und -Five.
- `theme.json` (Kern-Vorgaben, Eltern- und Kind-Theme) wird zu Global Styles: Presets als CSS-Variablen und Klassen, fluide Schriftgrößen, Elemente, Block-Stile, Layout, Root-Padding, Schriftarten (`fontFace`).
- Vorlagen folgen der Template-Hierarchie; Vorlagenteile (`parts/`) und Muster (`patterns/*.php`) werden aufgelöst.
- Serverseitig gerendert: Vorlagenteil, Muster, Seitentitel/-inhalt/-datum/-auszug/-bild, Autor, Begriffe, Query-Loop mit Seitennummerierung, Navigation (Menü aus dem CMS oder Seitenliste, mobiles Menü), Suche, Kommentare, Archive, Kategorien u. a.
- Block-Bibliothek-CSS liegt in `cms/wp/assets/` (aus WordPress, GPL) und wird unter `/wp-includes/css/dist/block-library/` ausgeliefert.
- Test: `php scripts/test-wp-fse.php`.

## Stufe 7: React-Verwaltungsseiten
- Seiten wie WooCommerce Admin (Startseite, Marketing, Kunden, Bestellungen, Analytics, Einstellungen, Status) und Yoast SEO (Dashboard, Einstellungen, Tools, Redirects …) laufen mit den WordPress-Kernskripten (`wp-element`, `wp-components`, `wp-data`, `wp-api-fetch` …).
- Die Kernressourcen (ca. 10 MB: JS-Pakete, Stile, jQuery UI, `WP_List_Table`) lädt das CMS einmalig von wordpress.org (passend zur gemeldeten WordPress-Version 6.8.3): Button „Jetzt laden“ unter Plugins → Einstellungen. Sie liegen in `cms/wp-core/` (nicht im Repository).
- Der Rahmen bleibt abgeschottet (ohne `allow-same-origin`). Er wird über `cms/wp/frame.php` ausgeliefert (zufälliger Schlüssel, 15 Minuten gültig). Speicher und Cookies gibt es nur im Arbeitsspeicher, Routenwechsel (`history.pushState`) laufen über die Brücke zum CMS.
- REST-Aufrufe der Seiten (`wp.apiFetch`, `fetch('/wp-json/…')`) gehen über die Brücke an `wp_admin_rest` und laufen als angemeldete Administration; Namespaces, die Plugins erst bei Bedarf laden (`rest_pre_dispatch`), werden vorher geladen.
- Zusätzlich: `/wp/v2/users/me` und `/wp/v2/settings`, `$wp_rewrite`, `$wp_locale`, `$wp_roles`, `WP_Screen`, `CONVERT_TZ` für SQLite, Hook-Verhalten wie in WordPress (`do_action` ohne Argumente übergibt `''`).
- Getestet im Browser: alle Yoast-Seiten sowie WooCommerce Start/Einrichtungsassistent, Marketing, Kunden, Bestellungen, Analytics, Einstellungen, Status.
- Test: `php scripts/test-wp-react.php`.

## Stufe 8: Website-Editor (Block-Themes)
- Bei aktivem Block-Theme erscheint unter Plugin-Seiten der Eintrag **Website-Editor**. Er nutzt den WordPress-Blockeditor (aus den Kernressourcen) zum Bearbeiten der Vorlagen und Vorlagenteile; Muster-Verweise werden dafür durch ihren Inhalt ersetzt.
- Änderungen werden als eigene Fassungen in `cms/data/.wp/site-editor/<theme>/` gespeichert. Die Dateien des Themes bleiben unberührt, „Auf Theme zurücksetzen“ verwirft die eigene Fassung.
- „Globale Stile“: Farbpalette des Themes ändern (wirkt sofort auf der ganzen Website).
- Zusätzliche REST-Schnittstellen: `wp/v2/templates`, `template-parts`, `block-renderer`, `themes`, `types`, `OPTIONS`-Abfragen.
- Grenzen: Der Editor arbeitet ohne eingebetteten Rahmen (Stile werden auf den Editor begrenzt), Navigations- und Beitragsblöcke zeigen Platzhalter-Daten, Medienbibliothek und Schriftverwaltung fehlen.

## Stufe 9: Elementor und andere Editoren im eigenen Tab
- Unter Plugin-Verwaltung → **Seiten & Editor** werden die Seiten und Beiträge der WordPress-Datenbank gelistet; „Neue Seite“ legt einen Entwurf an. Bei aktivem Elementor öffnet **Mit Elementor bearbeiten** den Editor in einem neuen Tab (Voraussetzung: ein aktives WordPress-Theme, die Website wird dort live angezeigt).
- Anmeldung: Das CMS stellt dem Superadmin ein Einmal-Token aus (90 s). Die WordPress-Schicht tauscht es gegen ein signiertes Cookie (HttpOnly, SameSite=Lax, 8 Stunden). Ohne Cookie sind alle Besucher unangemeldet. REST-Aufrufe zählen nur mit gültigem `wp_rest`-Nonce als angemeldet.
- Verwaltungsadressen `/wp-admin/post.php?action=…` führen die Plugin-Aktionen (`admin_action_*`) aus; die Vorschau (`?elementor-preview=`) zeigt Entwürfe für die angemeldete Sitzung.
- Elementor bearbeitet nur Inhalte der WordPress-Datenbank (nicht die virtuellen CMS-Seiten). TinyMCE gehört nicht zur Schicht: Textfelder des Editors sind einfache Textbereiche. Schriftarten von Google und Bibliotheken von CDNs benötigen Internetzugang des Browsers.
- Test: `php scripts/test-wp-session.php`.

## Stufe 10: Automatische Updates
- Unter Plugins/Themes → WordPress → **Automatische Updates**: für Plugins und Themes je „Aus“, „Alle automatisch“ oder „Nur ausgewählte“ (dann erscheint je Eintrag ein Schalter „Auto-Update“). Optional werden die Sprachpakete nach Updates neu geladen und die Kernressourcen (React-Seiten) aktuell gehalten.
- Gestartet wird der Lauf durch `cron/wp-cron.php` (z. B. stündlich einrichten; geprüft wird im eingestellten Abstand, 6 Stunden bis wöchentlich) oder beim Öffnen des Bereichs, sobald er fällig ist. „Jetzt prüfen & aktualisieren“ startet ihn von Hand.
- Sicherheit: Vor jedem Update wird die alte Fassung nach `cms/data/.wp/rollback/` verschoben; danach werden alle PHP-Dateien auf Syntaxfehler geprüft. Bei einem Fehler wird die alte Fassung automatisch wiederhergestellt. Im Protokoll lässt sich jedes erfolgreiche Update mit „Zurücksetzen“ zurücknehmen.
- Je Lauf höchstens 5 Updates und 90 Sekunden; ein Sperrdatei verhindert parallele Läufe. Test: `php scripts/test-wp-autoupdate.php`.

## Stufe 11: WordPress-Version nachführen
- Die Schicht meldet Plugins und Themes eine WordPress-Version (Grundstand 6.8.3). Unter **Automatische Updates → WordPress-Version** siehst du die aktuelle und die neueste Version von wordpress.org und übernimmst sie per Knopf oder automatisch: „nur Fehlerkorrekturen“ (6.8.3 → 6.8.4) oder „jede neue Version“. Standard: nur von Hand.
- Ablauf: Paket laden, Prüfsumme (SHA-1) prüfen, Kernressourcen (JavaScript/CSS) neu entpacken, gemeldete Version und Datenbankstand in `cms/data/.wp/wp-version.json` ablegen. Die vorherige Fassung bleibt in `cms/wp-core.prev/` und lässt sich mit „Vorherige Version“ wiederherstellen.
- **Kompatibilitätsbericht:** Beim Wechsel werden nur die Funktionsnamen der neuen Version gelesen (kein Quelltext übernommen) und mit der Schicht verglichen: „N von M Funktionen noch unbekannt“. So siehst du, wie vollständig die Schicht zur neuen Version ist.
- Test: `php scripts/test-wp-version.php`.

## Stufe 12: Einheitliche Inhalte (Schreibbrücke)
Bisher zeigte die WordPress-Schicht CMS-Inhalte (Beiträge aus `news.json`, eigene Seiten und Einstellungen aus `site.json`) nur lesend. Mit der **Schreibbrücke** können WordPress-Plugins, -Themes, Elementor und die REST-API sie auch ändern – ohne die Bestandsdaten zu beschädigen.

**Einschalten.** Standardmäßig ist die Brücke **aus**; dann verhält sich alles wie bisher. Unter CMS → Inhalte → **Alle Inhalte** → „Schreibbrücke WordPress ↔ CMS“ lassen sich die Bereiche einzeln zuschalten: Einstellungen, Beiträge, Seiten, Medien, Menüs (gespeichert in der WordPress-Option `rrw_cms_bridge`, nur Administratoren).

**Gemeinsamer Speicherweg.** Die Brücke schreibt nur über die vorhandenen CMS-Wege (`cms/wp/core/cms-write.php`): Sperrdatei `cms/data/.site.lock` (gleiche wie beim Speichern im CMS), atomares Schreiben, Beitrags-Revisionen (`news-revisions.json`), Seiten-Verlauf, Aktivitätsprotokoll, `rrw_publish()` (index.html-Schnappschuss, Seiten, Sitemap, RSS). Es werden nur die betroffenen Felder geändert; alle übrigen Felder – auch unbekannte Zusatzfelder – bleiben unverändert, Änderungen ohne Wirkung schreiben nichts. Ist `news.json` oder `site.json` nicht lesbar, wird nichts geschrieben (kein Überschreiben mit einer leeren Datei).

| WordPress | CMS |
|---|---|
| `update_option('blogname')` | `site.json` → `portal.site_name` (max. 80 Zeichen) |
| `update_option('blogdescription')` | `portal.tagline` (neues optionales Feld) |
| `update_option('admin_email')` | `legal.email` (neues optionales Feld, nur gültige Adressen) |
| `update_option('timezone_string')` / `update_option('WPLANG')` | `cms/data/system.local.json` → `timezone` / `language` (nur gültige Zeitzonen bzw. Sprachcodes wie `de_DE`; ohne Eintrag gelten weiter `Europe/Berlin` und `de_DE`) |
| `default_comment_status` (`open`/`closed`) | `site.json` → `comments.enabled` |
| `comment_moderation` (`0`/`1`) | `comments.require_approval` |
| `posts_per_rss` (5–100) | `rss.max_items` |
| `blog_public` (`0`/`1`) | `seo.robots` (`0` → `noindex,nofollow`, `1` → `index,follow`; ein vorhandener Wert, der mit `index` beginnt, bleibt bei `1` unverändert) |
| alle anderen Optionen | bleiben in `cms/data/.wp/options.json` |
| `wp_insert_post` (Typ `post`) | neuer Eintrag in `news.json` (nächste freie ID unter 10 000 000) |
| `wp_update_post` / `wp_trash_post` / `wp_untrash_post` / `wp_delete_post` auf CMS-Beitrags-IDs | Titel → `title`, Inhalt → `body_html`, Auszug → `excerpt`, Slug → `slug`, Datum → `published_at`, Autor → `author`, Kategorie → `category`, Schlagwörter → `tags`; `publish` → `published`, `draft` → `draft` (`pending`/`private` zusätzlich als `wp_status`), `future` = `published` mit späterem Datum, Papierkorb → `deleted_at`, endgültig löschen → Eintrag entfernt |
| `set_post_thumbnail` / `_thumbnail_id` | `image_url` |
| `wp_set_post_terms` (`category`, `post_tag`) | `category` (der erste Begriff) bzw. `tags` |
| `wp_update_post` auf CMS-Seiten (`pages[]`, `type=custom`) | Titel → `headline` (sonst `title`), Status `publish` ↔ `enabled`, Slug → `slug` (Menüpunkte `page:<Adresse>` folgen) |
| Seiteninhalt (einteilig) | genau ein HTML-Block (oder noch keiner, dann wird einer angelegt) und keine Text-Blöcke; sonst Fehler `cms_unmappable`, nichts wird geschrieben |
| Seiteninhalt (mehrteilig) | Seiten mit mehreren HTML-/Text-Blöcken erscheinen **für Editoren** (Verwaltung, REST) mit Block-Markern `<!--rrw:block ID-->…<!--/rrw:block-->`; Besucher sehen unverändertes HTML. Beim Speichern wird jeder Block einzeln übernommen, solange Anzahl, Reihenfolge und IDs der Marker unverändert sind, nichts außerhalb der Marker steht und Textblöcke einfacher Text bleiben (`<p>…</p>` ohne Formatierung). Sonst `cms_unmappable` – es wird nichts geschrieben (z. B. wenn Elementor den Inhalt neu erzeugt) |
| `wp_update_nav_menu_item` / `wp_delete_post` auf Menüpunkte | `menus.top` (Menü-ID 1) / `menus.bottom` (2); Ziele: eigene Adresse, Startseite/System, CMS-Seite |
| `media_handle_upload`, `POST /wp/v2/media` | `cms/media/library/<id>/` (Original, WebP-Varianten, `meta.json`) über `rrw_media_library_store()` – kein Duplikat in `wp-content/uploads`, keine Zeile in `wp_posts` |

- **Weiterhin in der Datenbank** (`wp_posts`, IDs ab 100 000 000): von WordPress-Code neu angelegte Seiten (Elementor, REST), eigene Beitragstypen, `auto-draft`s; Beitragsmeta (`wp_postmeta`, an die ID gebunden), nicht abbildbare Felder (Kommentarstatus, Passwort, Menüreihenfolge, Elternseite) sowie alle übrigen Optionen. Seiten und Beiträge aus CMS und Datenbank erscheinen gemeinsam in `WP_Query`, `get_pages()` und in der Verwaltung.
- **Entwürfe und Papierkorb** der CMS-Beiträge sowie deaktivierte CMS-Seiten sind mit eingeschalteter Brücke per ID und über `post_status=draft|any|trash` erreichbar (öffentlich sichtbar bleiben nur veröffentlichte Inhalte).
- **Medien.** Die Bibliothek (`cms/media`) erscheint als Anhänge (`post_type=attachment`, IDs ab 60 000 000, dieselben wie beim Titelbild): `get_post`, `WP_Query`, `wp_get_attachment_url/_image_src/_metadata`, `get_attached_file`, REST `GET /wp/v2/media`. Ein Upload nimmt nur echte HTTP-Uploads an (Bilder bis 20 MB, wie im CMS). Die gemeinsamen Medienfunktionen liegen jetzt in `cms/lib/media.php` (aus `api.php` herausgelöst, Verhalten unverändert).
- **REST** (nur mit eingeschalteter Brücke): `POST/PUT/PATCH/DELETE /wp/v2/posts[/<id>]` und `/wp/v2/pages[/<id>]`, `GET/POST /wp/v2/media`; Rechte wie in WordPress (`edit_posts`, `edit_pages`, `upload_files`).
- **Menüs lesen.** `wp_get_nav_menu_items` löst Menüziele `page:<Adresse>` (so speichert sie der CMS-Menü-Editor) erst mit eingeschalteter Brücke auf; vorher bleiben sie wie bisher `#`.
- **Verwaltung.** Neuer Bereich **Alle Inhalte** (`cms/views/panel-contents.php`, `cms/assets/contents-manager.js`, API `wp_content_list&scope=all`): Beiträge und Seiten aus CMS und Datenbank mit Quelle, Typ, Status, Änderungsdatum und passendem Editor (CMS-Editor, „Mit Elementor bearbeiten“, WordPress-Editor). Die Bereiche „Beiträge / News“ und „Seiten“ bleiben unverändert.
- **Zusatzfelder.** `rrw_clean_section` kennt jetzt die optionalen Felder `portal.tagline` und `legal.email` und das Speichern im CMS lässt sie unberührt (vorhandene Werte gehen beim Speichern des Portal- bzw. Rechtstext-Formulars nicht verloren).
- **Grenzen.** Nur eine Kategorie je CMS-Beitrag (die erste); Blöcke einer mehrteiligen CMS-Seite lassen sich nur bearbeiten, nicht hinzufügen, entfernen oder umstellen (das geht nur im CMS), andere Blocktypen (Bild, Button, Widget …) bleiben unberührt; CMS-Seiten werden nicht über WordPress gelöscht; Beiträge aus dem klassischen Editor (`auto-draft` → Veröffentlichen) bleiben in der Datenbank, weil ihre ID schon vergeben ist. Inhalt ohne Recht `unfiltered_html` wird wie im CMS bereinigt (`rrw_safe_html`).
- Tests: `php scripts/test-cms-unify.php` (Fixtures in temporären Ordnern; belegt u. a., dass nicht berührte Felder und unbekannte Zusatzfelder in `news.json`/`site.json` unverändert bleiben).

## E-Mail-Versand
- `wp_mail()` baut auf dem globalen `$phpmailer` (`PHPMailer\PHPMailer\PHPMailer`, Dateien unter `cms/wp/core/PHPMailer/`: `PHPMailer.php`, `SMTP.php`, `Exception.php`) auf. Die Klassen werden erst beim ersten Versand geladen; ohne Plugin bleibt der Standard-Mailer PHP `mail()`. Wer `class-phpmailer.php` / `class-smtp.php` einbindet, erhält die alten globalen Klassennamen als Alias.
- Filter/Aktionen wie dokumentiert: `wp_mail`, `pre_wp_mail`, `wp_mail_from`, `wp_mail_from_name`, `wp_mail_content_type`, `wp_mail_charset`, `phpmailer_init` (Referenz auf `$phpmailer`), `wp_mail_failed` (mit `to`, `subject`, `message`, `headers`, `attachments`, `phpmailer_exception_code`), `wp_mail_succeeded`. Damit funktionieren SMTP-Plugins (WP Mail SMTP, Post SMTP, FluentSMTP) und Formular-Plugins.
- Header-Auswertung (From, Cc, Bcc, Reply-To, Content-Type mit `charset`/`boundary`, eigene Kopfzeilen), Anhänge als Liste oder `Name => Pfad`, `multipart/alternative` bei gesetztem `AltBody`. Zeilenumbrüche in Betreff, Namen und Kopfzeilen werden entfernt (Schutz vor Header-Injektion); ungültige Empfänger werden übersprungen, ohne gültigen Empfänger schlägt der Versand fehl.
- SMTP: implizites SSL, STARTTLS (auch automatisch), AUTH LOGIN/PLAIN/CRAM-MD5/XOAUTH2, Dot-Stuffing, Zeitlimit, Fehlerinfo in `ErrorInfo`. Grenzen: kein DKIM/S/MIME, keine IDN-Umwandlung.
- Tests: `php scripts/test-wp-mail.php` (SMTP gegen lokalen Fake-Server, kein echtes Netz).

## Live-Customizer für WordPress-Themes
- **Bedienung:** Design → Themes → WordPress-Theme → „Anpassen“ öffnet dasselbe Vollbild-Overlay wie bei Portal-Designs (Einstellungen links, Vorschau rechts, Geräte-Umschalter). „Aktivieren & Veröffentlichen“ (beim aktiven Theme „Veröffentlichen“) speichert die Werte und aktiviert das Theme nach Rückfrage. Nur Administratoren (wie die übrigen `wp_theme_*`-Aktionen).
- **Server** (`cms/wp/customizer-api.php`, Aktionen `wp_theme_customize`, `wp_theme_customize_draft`, `wp_theme_customize_save` in `cms/api.php`): lädt das gewählte Theme (Filter `pre_option_stylesheet/template` wie in der Vorschau), löst `customize_register` aus und liefert Abschnitte mit Steuerelementen. Gespeichert wird als `theme_mod` bzw. Option (auch verschachtelt, z. B. `astra-settings[…]`); Eingaben werden je Typ geprüft und über die `sanitize_callback` des Themes bereinigt (Ablehnung bei `null`). Ein Fehler stoppt das ganze Speichern. Menü-Standorte (`nav_menu_locations`) wählen aus den vorhandenen CMS-Menüs (Hauptmenü/Fußmenü).
- **Unterstützte Steuerelemente:** Text, Textfeld, Checkbox, Radio, Auswahl, Seitenauswahl (`dropdown-pages`), Farbe, Bereich, Zahl, Link/E-Mail (als Text), Bild (Auswahl aus der Mediathek des CMS, Hochladen direkt im Auswahlfenster, „Entfernen“, oder Eingabe einer Adresse). Außerdem Website-Titel und Untertitel, Hintergrundfarbe/-bild und Kopfbild/Kopf-Textfarbe (nur wenn das Theme `custom-background`/`custom-header` anbietet) und Menü-Standorte. Eigene Steuerelement-Klassen werden nach Klasse/Typname (Farbe, Bild, Schalter, Bereich …) zugeordnet; alles andere (z. B. Medien-ID-Steuerelemente, Wiederholer, Typografie-Fenster) erscheint mit dem Hinweis „lässt sich hier nicht bearbeiten“. Logo und Website-Icon gehören dem CMS (Branding & Medien). Block-Themes bieten zusätzlich einen Knopf zum Website-Editor (sobald das Theme aktiv ist).
- **Vorschau:** Das Vorschau-Iframe lädt `/?rrw_wp_preview=<Schlüssel>&rrw_wp_draft=<ID>`. Der Entwurf liegt nur als Datei in `cms/data/.wp/customize-drafts/` (15 Minuten, nur zusammen mit gültigem Vorschau-Schlüssel und nur für dieses Theme wirksam) und wird über `theme_mod_*`/`pre_option_*`-Filter angewendet – gespeichert wird nichts, bis „Veröffentlichen“. Titel und Untertitel erscheinen sofort per `postMessage`; alle anderen Änderungen laden die Vorschau nach 300 ms Pause neu.
- **Grenzen:** Vorschau-Skripte von Themes (`transport: postMessage`) laufen nicht – diese Einstellungen laden die Vorschau neu. Bedingte Anzeige (`active_callback`) wird nicht ausgewertet (alle Einstellungen sind sichtbar). Bildfelder speichern die Adresse des gewählten Bildes (keine WordPress-Anhangs-ID).
- Tests: `php scripts/test-wp-customizer.php`.

## Noch nicht enthalten
- Eigene JavaScript-Steuerelemente und Vorschau-Skripte (transport postMessage) von Themes im Customizer sowie eigene Gutenberg-Blöcke von Plugins. Externe Bilder und Dienste (woocommerce.com, Jetpack) sind nicht erreichbar.
- Cookie-Anmeldung für Besucher im Sinne von WordPress (Kommentare, Mitgliederbereiche): Frontend-Aufrufe laufen als Besucher; nur die Editor-Sitzung ist angemeldet.

Mit `php scripts/wp-missing.php <plugin-ordner>` listet das Repo, welche WordPress-Funktionen ein Plugin aufruft, die noch fehlen. Mit `php scripts/wp-probe.php <plugin-ordner> […]` läuft ein Probelauf: Plugin aktivieren, Schicht starten, Startseite, Beitrag, REST-Index und jede Admin-Seite des Plugins aufrufen und Fehler/Warnungen melden (Plugins liegen unter `cms/wp-content/plugins`, nicht im Repo).

## Eigenständiger Betrieb
Das CMS und die WordPress-Schicht bilden zusammen ein eigenständig installierbares System: Einrichtungsassistent, Betriebsmodus (mit oder ohne Control Center), änderbarer Produktname, Version und Datensicherung sind in [STANDALONE.md](STANDALONE.md) beschrieben. Der Assistent nutzt dieselbe Datenbankschicht (`cms/lib/database.php`) wie die WordPress-Datenbank.

## Sicherheit und Stabilität
- Plugins sind fremder PHP-Code mit den Rechten des CMS: Installation, Aktivierung und Löschen nur für Administratoren.
- `cms/wp-content/.htaccess` sperrt den direkten URL-Aufruf von PHP-Dateien in Plugins/Themes; Bilder, CSS und JS bleiben abrufbar. Das ZIP-Entpacken verhindert Pfad-Tricks, Symlinks sowie `.htaccess`/`.user.ini`, und begrenzt Größe und Dateianzahl.
- Ein Plugin, das beim Laden abstürzt (auch bei nicht abfangbaren Fehlern wie doppelt deklarierten Funktionen), wird beim nächsten Aufruf automatisch deaktiviert; der Grund steht in der Plugin-Liste. Fehler in einzelnen Hook-Funktionen werden protokolliert (`cms/data/.wp/debug.log`), die Seite läuft weiter.
- `wp_remote_*` blockiert interne Adressen (SSRF-Schutz).

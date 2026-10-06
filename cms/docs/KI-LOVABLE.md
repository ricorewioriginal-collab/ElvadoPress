# Kern-Infrastruktur: Datenbank, KI-Engine, Lovable, GitHub-Sync

## KI-Zentrale (eine Stelle für alle KI-Zugänge)
Menü **KI & Lovable → KI-Zentrale** (`cms/assets/ai-center.js`, `cms/views/panel-aicenter.php`): Anbieter (Direkt, kostenlos/günstig, Dienste ohne Schlüssel, eigene OpenAI-kompatible wie Ollama), API-Schlüssel, Modelle (Liste beim Anbieter abrufen), Test, Einsatzzwecke (Standard, Beiträge & Texte, Website-Generator, KI-Entwickler, Alt-Texte), Limit und Nutzung.
- **Speicher:** `cms/data/.ai/gateway.json` (gesperrter Ordner, Rechte 0600). Das ist der einzige Ort für Schlüssel.
- **Gemeinsame Anbieterliste:** `cms/lib/ai-providers.json` (OpenAI-kompatible Vorgaben) wird vom Gateway *und* vom KI-Assistenten gelesen; native Schnittstellen (Claude, Gemini) und EvoLink stehen in `AiGatewayService::catalog()`.
- **KI-Assistent** (Website-Chat, `cms/lib/assistant.php`): legt nur Verhalten, Reihenfolge und Modellwahl fest; Schlüssel und eigene Anbieter kommen zur Laufzeit aus der Zentrale (`rrw_assistant_with_central()`, nie in `site.json` gespeichert). Schlüssel, die früher im Assistenten standen, gelten weiter, bis „Alte Schlüssel übernehmen“ (`ai_migrate_legacy`) sie in die Zentrale verschiebt.
- **WordPress-Verbinder** (`cms/wp/core/ext/ai-central.php`): `get_option('connectors_ai_<anbieter>_api_key')` liefert den zentralen Schlüssel, Speichern in der WP-Oberfläche schreibt dorthin.
- Dienste ohne Schlüssel (Pollinations, LLM7) sind im Gateway standardmäßig **aus** (Eingaben gehen an Dritte); im Assistenten bleiben sie wie bisher an.
- Neue Aktionen: `ai_test`, `ai_models`, `ai_migrate_legacy`; `ai_generate` akzeptiert `purpose`.

Neutrale CMS-Erweiterung (objektorientiert, PSR-4-Namensraum `Elvado\` in `cms/src/`, Autoload `cms/src/autoload.php`). Das übrige CMS bleibt prozedural; es gibt keinen Composer- oder Build-Schritt für PHP.

## KI-Website-Generator
Menü **KI & Lovable → Website-Generator** (`cms/assets/ai-builder.js`, `cms/src/Ai/SiteBuilder.php`, Aktion `ai_site_plan`): Idee beschreiben → Entwurf aus Titel/Untertitel, Farbwelt, Startseite (Abschnitte des Homepage-Baukastens), Seiten und Beiträgen → Vorschau (einzelne Seiten/Beiträge abwählbar) → **Übernehmen** → **Rückgängig**.
- Die KI liefert nur JSON (Anbieter des Einsatzzwecks „Website-Generator“, 150 s Zeitrahmen, bis 6000 Token; Aufgabe `site` ist intern und nicht aus der Oberfläche aufrufbar). `SiteBuilder::normalize()` prüft alles streng: nur bekannte Abschnittstypen, keine Bild-Adressen, kein HTML/Skript, Links nur `#`, `/seite.html` (zu vorhandenen Seiten), https oder mailto, Farben mit Kontrast ≥ 4,5, eindeutige und nicht reservierte Seitenadressen (kein Impressum/Datenschutz).
- Übernehmen läuft über vorhandene Aktionen: `wp_theme_activate`, `wp_theme_customize_save` (Titel, Untertitel, Farben), `wp_bk_save` (Startseite), `save` (pages, menus), `news_save` (Beiträge, standardmäßig Entwurf). Vorher wird der Zustand gesichert (Browser-`localStorage`, Schlüssel `ep_builder_undo`); Rückgängig stellt Titel, Farben, Startseite, Seiten, Menüs, vorheriges Theme wieder her und verschiebt die Beiträge in den Papierkorb.
- **Bilder:** Die KI liefert je Hero, „Bild + Text“ und Beitrag Suchbegriffe (`image_query`). Beim Übernehmen lädt `ai_site_images` (`rrw_stock_fetch_for_plan()` in `cms/lib/stockmedia.php`) passende freie Bilder (Reihenfolge Pixabay, Pexels, Unsplash, Openverse, Wikimedia; bevorzugt ohne Namensnennungspflicht, hohe Auflösung), übernimmt sie in die Mediathek und setzt Alt-Texte (KI mit Bildverständnis, sonst Titel der Bildquelle). Bei Bildern mit Namensnennungspflicht entsteht die Seite „Bildnachweise“ (Fußmenü). Ohne eingerichtete Bildquelle entsteht die Seite ohne Bilder. Beim Rückgängigmachen bleiben die Bilder in der Mediathek. Max. 8 Bilder je Entwurf. Test: `scripts/test-site-images.php`.
- In der Demo gesperrt (`ai_site_plan`, `ai_site_images`, `ai_alt_suggest`, `ai_generate`, `ai_test`, `ai_models`, `ai_config_save`, `ai_migrate_legacy`).
- Test: `scripts/test-ai-sitebuilder.php` (Fake-Transport).

## KI-Entwickler (Plugins, Widgets, Themes)
Menü **KI & Lovable → KI-Entwickler** (`cms/assets/ai-dev.js`, `cms/src/Ai/CodeBuilder.php`, Aktionen `ai_dev_plan`, `ai_dev_check`, `ai_dev_install`): Beschreibung → Entwurf (Dateiblöcke `=== DATEI: pfad ===`) → Code ansehen/ändern → automatische Prüfung → **inaktiv** installieren → erst auf Klick aktivieren (`wp_plugin_activate` / `wp_theme_activate`). „Anpassen mit KI“ überarbeitet den vorhandenen Stand (gleicher Ordner).
- **Arten:** Plugin (`wp-content/plugins/<slug>/<slug>.php` mit Plugin-Kopf), Widget (Plugin mit `WP_Widget` und Shortcode), Theme: *Kindtheme* von „ElvadoPress Baukasten“ (nur `style.css`, kein PHP/JS – sicherste Variante) oder eigenständig (`style.css`, `index.php`, Vorlagen).
- **Sicherheit:** nur Superadmin, in der Demo gesperrt; Installation immer inaktiv; Server prüft beim Installieren erneut (Entwurf vom Browser wird nie vertraut). Prüfung: erlaubte Endungen (php, css, js, json, txt, md, html), Pfade ohne `..`/Dotfiles, ≤ 12 Dateien/120 KB je Datei, PHP-Syntax über den Tokenizer (`token_get_all(…, TOKEN_PARSE)`, kein `exec` nötig), **Fehler** (blockieren): `eval`, Backticks, `exec/system/shell_exec/passthru/popen/proc_open`, `assert`, `fsockopen`, `mail`, include/require von Adressen oder Benutzereingaben, PHP in CSS/JS/HTML, JS `eval/new Function/document.write`, CSS `expression()`; **Hinweise** (Bestätigung nötig): Dateischreib-/Netzwerkfunktionen, `unserialize`, `base64_decode`, `extract`, dynamische Funktionsaufrufe, externe Ressourcen u. a. Fremde Ordner ohne Markierungsdatei `.ai-generated.json` werden nie überschrieben.
- **Grenze:** Die statische Prüfung ist keine vollständige Sicherheitsgarantie – KI-Code vor dem Aktivieren lesen und zuerst auf einer Kopie testen. Aktivierung schaltet bei einem Ladefehler über die vorhandene Plugin-Fehlerbehandlung wieder ab.
- Test: `scripts/test-ai-codebuilder.php` (55 Prüfungen).

## Dateien
| Pfad | Aufgabe |
|---|---|
| `cms/src/Database/DatabaseConnection.php`, `schema.sql` | PDO (Exception-Modus) für MySQL/MariaDB/SQLite; Tabellen `users`, `posts`, `ai_logs`, `lovable_widgets`; Migration `migrateCore()`. Ohne MySQL-Einstellung: `cms/data/cms-core.sqlite`. |
| `cms/src/Repository/` | `PostRepository` (Spiegel der Beiträge), `LovableWidgetRepository`, `AiLogRepository` (ohne Prompt-Text). |
| `cms/src/Support/` | `Http` (cURL, SSRF-Schutz, testbarer Transport), `RateLimiter`. |
| `cms/src/Ai/AiGatewayService.php`, `AiGatewayConfig.php` | KI-Anbieter: EvoLink, OpenAI, Anthropic, Google, OpenRouter, DeepSeek, Groq, Cerebras, Mistral, … sowie eigene (Zentrale). Aufgaben: text, rewrite, translate, summarize, json, layout. |
| `cms/src/Lovable/` | `LovableSettings`, `PostFeed`, `ProviderController`; Endpunkt `cms/api-lovable-provider.php`. |
| `cms/src/GitHub/GitHubSyncService.php`, `WebhookController`; `cms/github-webhook.php` | Synchronisation eines GitHub-Repositorys nach `cms/frontend/lovable/<projekt>/`. |
| `frontend/` | React-18/TypeScript/Tailwind-Quellen (`LovableWidgetBridge.tsx`, `AiContentAssistant.tsx`, Einstellungen). Gebautes Bündel eingecheckt in `cms/assets/react/`. |
| `cms/assets/ai-lovable-admin.js`, `cms/views/panel-ai.php` | Menü „KI & Lovable“ der Verwaltung. |

## API (`cms/api.php`)
`ai_status`, `ai_generate` (jeder angemeldete Benutzer, Rate-Limit je Benutzer/Stunde); `ai_config_get/save`, `ai_logs`, `lovable_get/save`, `lovable_secret_rotate`, `lovable_widget_save/delete`, `lovable_sync`, `core_posts_mirror` (nur Superadmin). Schlüssel sind schreibgeschützt (`__clear__` löscht), werden nie ausgeliefert; fehlen sie, gelten die Schlüssel des KI-Assistenten.

## Ein KI-Menü, Modelle, Bilder und Videos
- Die Seitenleiste hat einen Eintrag **KI**; darin Unterreiter (`assets/ai-nav.js`): Zentrale, Bilder & Videos, Website-Generator, Entwickler, Texte & Lovable, Assistent.
- **Modelle**: „Modelle laden“ fragt live beim Anbieter ab (OpenAI-kompatibel inkl. EvoLink: `GET /models`; Gemini: `GET /v1beta/models`; Claude: `GET /v1/models`), sonst gilt der Katalog. Gemini-Standard ist der Alias `gemini-flash-latest` (überlebt Abschaltungen einzelner Versionen). Meldet ein Anbieter 404, weist die Fehlermeldung auf „Modelle laden“ hin.
- **EvoLink**: ein Schlüssel für Text/Code (Katalog mit vielen Modellen, frei erweiterbar), Bilder und Videos. **fal.ai**: Text über `fal-ai/any-llm`, Bilder/Videos über die Queue (`queue.fal.run`, Kopf `Authorization: Key …`).
- **Bilder & Videos** (`cms/src/Ai/MediaGenerator.php`, `ai_media_providers/start/status/save`): EvoLink (`/v1/images|videos/generations`, Abfrage `/v1/tasks/{id}`), fal.ai-Queue, OpenAI-Bilder. Bilder landen mit Vermerk „KI-generiert“ in der Mediathek, Videos unter `cms/media/videos/`. Limit 30 Aufträge/Stunde/Benutzer; in der Demo gesperrt.
- Modellnamen der Anbieter ändern sich laufend; sie sind im Feld frei eingebbar. Test: `scripts/test-ai-media.php`.

## KI-Bilder im Alltag, Modellwahl im Entwickler, Customizer-Schnellzugriff
- **KI-Bilder**: `AiMedia.dialog()` (Beitragseditor: Beitragsbild „Zauberstab“, Bildblock „Mit KI erzeugen“) und `AiMedia.generate()`; im Website-Generator wählt die Option „Bildquelle“ zwischen freien Bildern und KI-Bildern (Kosten pro Bild beim Anbieter). Bilder landen mit Alt-Text (aus der Beschreibung) in der Mediathek.
- **KI-Entwickler**: Modell pro Aufgabe wählbar (★ = fürs Programmieren geeignet); `ai_dev_plan` akzeptiert `provider` und `model`.
- **Customizer**: Schnellzugriff (Startseite, Menüs, Widgets) und „In Vorschau wählen“ (`assets/customizer-extras.js`): Kopfzeile, Fußzeile, Button, Text oder Bild antippen öffnet den passenden Bereich (Portal- und WordPress-Customizer).

## Lovable-Bridge
Widget (Web-Component) wird per Shortcode `[lovable widget="…" project="…"]` oder React-Bridge eingebunden. Daten: `cms/api-lovable-provider.php` (CORS-Allowlist, ETag, Rate-Limit, nur öffentliche Beiträge). Skript-URL-Vorlage und erlaubte Hosts stellt der Betreiber unter „KI & Lovable“ ein.

## GitHub-Sync
Repo/Branch/Token (schreibgeschützt) in den Einstellungen; Webhook-URL `…/cms/github-webhook.php`, HMAC-SHA256-Geheimnis. Sicheres Entpacken (Endungs-Allowlist, keine PHP-/versteckten Dateien, keine Symlinks/`..`), Sperrdatei, Manifest-Bereinigung.

## Bündel neu bauen
`cd frontend && npm install && npm run build` (nur bei Änderungen an `frontend/src`).

## Tests
`scripts/test-core-db.php`, `test-ai-gateway.php`, `test-lovable-provider.php`, `test-github-sync.php`, `test-lovable-shortcode.php` (Fake-Transport, keine echten Anbieter).

## Nicht mit echten Diensten geprüft
EvoLink-Endpunkt/Modellnamen (in den Einstellungen änderbar), echte Anbieter-APIs, echtes GitHub, Lovable-Skript-Hosting.

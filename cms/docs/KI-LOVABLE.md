# Kern-Infrastruktur: Datenbank, KI-Engine, Lovable, GitHub-Sync

## KI-Zentrale (eine Stelle für alle KI-Zugänge)
Menü **KI & Lovable → KI-Zentrale** (`cms/assets/ai-center.js`, `cms/views/panel-aicenter.php`): Anbieter (Direkt, kostenlos/günstig, Dienste ohne Schlüssel, eigene OpenAI-kompatible wie Ollama), API-Schlüssel, Modelle (Liste beim Anbieter abrufen), Test, Einsatzzwecke (Standard, Beiträge & Texte, Website-Generator, KI-Entwickler), Limit und Nutzung.
- **Speicher:** `cms/data/.ai/gateway.json` (gesperrter Ordner, Rechte 0600). Das ist der einzige Ort für Schlüssel.
- **Gemeinsame Anbieterliste:** `cms/lib/ai-providers.json` (OpenAI-kompatible Vorgaben) wird vom Gateway *und* vom KI-Assistenten gelesen; native Schnittstellen (Claude, Gemini) und EvoLink stehen in `AiGatewayService::catalog()`.
- **KI-Assistent** (Website-Chat, `cms/lib/assistant.php`): legt nur Verhalten, Reihenfolge und Modellwahl fest; Schlüssel und eigene Anbieter kommen zur Laufzeit aus der Zentrale (`rrw_assistant_with_central()`, nie in `site.json` gespeichert). Schlüssel, die früher im Assistenten standen, gelten weiter, bis „Alte Schlüssel übernehmen“ (`ai_migrate_legacy`) sie in die Zentrale verschiebt.
- **WordPress-Verbinder** (`cms/wp/core/ext/ai-central.php`): `get_option('connectors_ai_<anbieter>_api_key')` liefert den zentralen Schlüssel, Speichern in der WP-Oberfläche schreibt dorthin.
- Dienste ohne Schlüssel (Pollinations, LLM7) sind im Gateway standardmäßig **aus** (Eingaben gehen an Dritte); im Assistenten bleiben sie wie bisher an.
- Neue Aktionen: `ai_test`, `ai_models`, `ai_migrate_legacy`; `ai_generate` akzeptiert `purpose`.

Neutrale CMS-Erweiterung (objektorientiert, PSR-4-Namensraum `Elvado\` in `cms/src/`, Autoload `cms/src/autoload.php`). Das übrige CMS bleibt prozedural; es gibt keinen Composer- oder Build-Schritt für PHP.

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

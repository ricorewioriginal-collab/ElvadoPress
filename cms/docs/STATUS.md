# Projektstand ElvadoPress / ricorewi-radio (05.10.2026)

Übergabe zum späteren Weiterarbeiten.

## Aufbau
- **Hauptquelle des CMS ist ElvadoPress** (Repository `ricorewioriginal-collab/ElvadoPress`). Das Projekt `ricorewi-radio` (RicoReWi-Portal, Produktion von ricorewi-radio.de) übernimmt `cms/` per Sync-Workflow und enthält nur die RicoReWi-spezifischen Teile. Regeln: `CLAUDE.md` in beiden Repositories, Ablauf: `cms/docs/ELVADOPRESS.md`.
- **Pack-Konzept** (`cms/lib/pack.php`): RicoReWi-Inhalte hängen an `rrw_pack_available()` / `rrw_pack_active()`; in der Oberfläche `data-pack` bzw. `data-pack-app`, in JavaScript `window.CMS_PACKS['ricorewi-radio']`.
- **Tests:** `scripts/test-*.php` und `scripts/smoke-test.php` (ElvadoPress-CI führt alle aus).

## Was läuft
| Was | Wo | Wie |
|---|---|---|
| Live-Seite RicoReWi | ricorewi-radio.de | Workflow `deploy-production.yml` (ricorewi-radio) bei Push auf `main` |
| Öffentliche Demo | https://elvadopress.ricorewi-radio.de (Info `/demo/`, Verwaltung `/cms/?demo=1`) | Workflow „Deploy ElvadoPress Demo“ (ricorewi-radio), Secret `ELVADOPRESS_DEMO_PATH` |
| CMS-Stand in ricorewi-radio | `cms/` | Workflow „Sync ElvadoPress“ (Pull Request `sync/elvadopress`) |

Die Demo nutzt Benutzer `demo`, setzt alle 10 Minuten zurück und sperrt riskante Aktionen (`RRW_DEMO_BLOCKED` in `cms/lib/demo.php`). Sie liegt bewusst auf einer eigenen Subdomain (Wurzelpfade des CMS, Anmeldedaten pro Domain).

## Secrets (ricorewi-radio → Settings → Secrets → Actions)
`DEPLOY_SSH_*` (vorhanden), `ELVADOPRESS_DEMO_PATH` (Webordner der Demo-Subdomain).

## Offen (nur der Inhaber kann es tun)
1. Repository `elvadopress-app-template` anlegen, danach mit `scripts/export-app-template.sh` bzw. dem Workflow `export-app-template.yml` (ricorewi-radio) befüllen.
2. Zwei offengelegte KI-Schlüssel rotieren.
3. Echter SMTP-Test mit einem Formular-Plugin.

## Nächste Entwicklungsschritte
- App-Baukasten/Alexa: eigene App-Inhalte, eigener Alexa-Skill, beliebige Stream-Adressen, eigene Branding-Dateien, ausführlichere Verwaltung.
- Umbenennung interner `rrw_`-Namen (groß, nur bei Bedarf).
- Unterordner-Betrieb des CMS (Wurzelpfade `/cms/`, `/wp-admin/`) ist nicht unterstützt; bei Bedarf als eigene Aufgabe.

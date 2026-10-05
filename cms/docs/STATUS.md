# Projektstand ElvadoPress / ricorewi-radio (05.10.2026)

Übergabe zum späteren Weiterarbeiten. Entwickelt wird **nur in ricorewi-radio**; das Repository ElvadoPress ist reine Ausgabe (wird bei jeder Veröffentlichung überschrieben).

## Aufbau
- **CMS / Radio / RicoReWi:** Einteilung und Regeln stehen in `CLAUDE.md` (Pack-Konzept: `cms/lib/pack.php`, `data-pack` / `data-pack-app`).
- **Eigenständiges Paket:** `php scripts/build-standalone.php <Ordner> --product=ElvadoPress` (Tests: `scripts/test-standalone-build.php`, `test-pack.php`, `test-cms-standalone.php`).
- **Demo-Build:** zusätzlich `--demo [--demo-minutes=10]` (`cms/lib/demo.php`, Test `scripts/test-demo.php`). Details: `cms/docs/ELVADOPRESS.md`.

## Was läuft
| Was | Wo | Wie |
|---|---|---|
| Live-Seite RicoReWi | ricorewi-radio.de | Workflow `deploy-production.yml` bei Push auf `main` |
| ElvadoPress-Repository | github.com/ricorewioriginal-collab/ElvadoPress | Workflow „Publish ElvadoPress“ (manuell oder Tag `cms-v*`), Secret `ELVADOPRESS_TOKEN` |
| Öffentliche Demo | https://elvadopress.ricorewi-radio.de (Info: `/demo/`, Verwaltung: `/cms/?demo=1`) | Workflow „Deploy ElvadoPress Demo“ (Push auf `main` mit Änderungen in `cms/**`, oder manuell), Secret `ELVADOPRESS_DEMO_PATH` |

- Demo: Benutzer `demo`, Zugang steht auf der Info-Seite; Daten werden nach 10 Minuten bei der nächsten Anfrage zurückgesetzt. Gesperrte Aktionen: `RRW_DEMO_BLOCKED` in `cms/lib/demo.php`.
- Die Demo liegt bewusst auf einer **eigenen Subdomain** (Wurzelpfade des CMS, Anmeldedaten pro Domain im Browser); ein Unterordner von ricorewi-radio.de ist nicht vorgesehen.
- Lizenz ElvadoPress: **GPL-2.0-or-later** (`cms/standalone/elvadopress/LICENSE`).

## Secrets (Repository ricorewi-radio → Settings → Secrets → Actions)
`DEPLOY_SSH_*` (vorhanden), `ELVADOPRESS_TOKEN` (fein abgestufter Token, nur Repo ElvadoPress, Contents: Read and write, läuft am 04.11.2026 ab), `ELVADOPRESS_DEMO_PATH` (Webordner der Demo-Subdomain).

## Offen (nur der Inhaber kann es tun)
1. Repository `elvadopress-app-template` anlegen (dem Assistenten fehlt das Recht), danach mit `scripts/export-app-template.sh` bzw. dem Workflow `export-app-template.yml` befüllen.
2. Zwei offengelegte KI-Schlüssel rotieren.
3. Echter SMTP-Test mit einem Formular-Plugin.
4. About-Text und Website-Feld im Repository ElvadoPress setzen (Demo-Adresse).
5. Token vor dem 04.11.2026 erneuern.

## Mögliche nächste Schritte
- Umbenennung der internen `rrw_`-Namen (groß, nur wenn nötig).
- Restliche RicoReWi-Zeichenketten in JS/CSS-Kennungen neutralisieren.
- Demo: weitere Beispielinhalte oder Themes; Zähler-Banner im Customizer prüfen.
- Das CMS unterstützt keinen Unterordner-Betrieb (Wurzelpfade `/cms/`, `/wp-admin/`); bei Bedarf als eigene Aufgabe planen.

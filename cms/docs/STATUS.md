# Projektstand ElvadoPress

## Aufbau
- **Hauptquelle des CMS ist ElvadoPress** (Repository `ricorewioriginal-collab/ElvadoPress`); kein Sync mit anderen Projekten. Regeln: `CLAUDE.md`, Ablauf: `cms/docs/ELVADOPRESS.md`.
- **Pack-Konzept** (`cms/lib/pack.php`): nur ein generischer Mechanismus für Theme-Pakete (`elvado_pack_available($pack)`: Komponenten, `admin.js`). ElvadoPress liefert kein Paket aus.
- **Tests:** `scripts/test-*.php` und `scripts/smoke-test.php` (die CI führt alle aus).

## Was läuft
| Was | Wo | Wie |
|---|---|---|
| Öffentliche Demo | https://elvadopress.ricorewi-radio.de (Info `/demo/`, Verwaltung `/cms/?demo=1`) | serverseitiges Deploy bei Änderung in `main` (Projekt `elvadopress`) |

Die Demo nutzt Benutzer `demo`, setzt alle 10 Minuten zurück und sperrt riskante Aktionen (`ELVADO_DEMO_BLOCKED` in `cms/lib/demo.php`). Sie liegt bewusst auf einer eigenen Subdomain (Wurzelpfade des CMS, Anmeldedaten pro Domain).

## Offen (nur der Inhaber kann es tun)
1. Zwei offengelegte KI-Schlüssel rotieren.
2. Echter SMTP-Test mit einem Formular-Plugin.

## Nächste Entwicklungsschritte
- Optionales Plugin „Elvado Radio / Audio“ (nicht im Kern).
- App-Baukasten/Alexa: eigene App-Inhalte, eigene Branding-Dateien, ausführlichere Verwaltung.
- Unterordner-Betrieb des CMS (Wurzelpfade `/cms/`, `/wp-admin/`) ist nicht unterstützt; bei Bedarf als eigene Aufgabe.

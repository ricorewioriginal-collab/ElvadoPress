# Architektur: ElvadoPress mit echter WordPress-Engine

Stand Phase 2 (Fundament). Die WordPress-Engine ist **standardmäßig aus**; ohne Aktivierung ändert sich an ElvadoPress und an öffentlichen Seiten nichts.

## Schichten
```
ElvadoPress-Oberfläche  →  Dienste/API  →  Content-Adapter  →  (Native Daten | echter WordPress-Core)
```
- Nutzer sehen nur ElvadoPress. WordPress ist Technik im Hintergrund (optional später ein Expertenmodus).
- Der **Content-Adapter** (`cms/src/Wp/Adapter/`) kapselt den Zugriff: `NativeAdapter` (eigene JSON/Markdown-Daten), `WordPressAdapter` (echter Core). Die Oberfläche kennt nur die Adapter-Schnittstelle.

## Messung: warum nicht beides im selben Prozess
Die eigene WordPress-Emulation (`cms/wp`, ca. 33 000 Zeilen, 2 927 Funktionen) deckt gegenüber WordPress 7.1.3 (4 590 Funktionen) nur 52 % ab; 2 374 Funktionsnamen sind identisch. Emulation und echter Core können daher **nicht** im selben PHP-Prozess laufen (`Bridge::prepare` verweigert den Start, wenn die Emulation geladen ist). Der übrige ElvadoPress-Code hat keine Namenskollisionen mit WordPress.
Folge: Die Engine hat einen eigenen Einstieg (`cms/engine-api.php`); WordPress wird dort im globalen Scope über `cms/wp-engine-boot.php` gestartet.

## Betriebsarten
`off` (Standard) → `installed` (Core + Datenbank eingerichtet, ungenutzt) → `active`. `active` verlangt eine eingerichtete Datenbank. Umschalten und Entfernen sind jederzeit möglich; Entfernen behält die Datenbanktabellen.

## Speicherorte
| Was | Wo | Schutz |
|---|---|---|
| Zustand, Zugangsdaten, Schlüssel, Log | `cms/data/.wp-engine/` | .htaccess gesperrt, `db.json`/`keys.json` 0600, nicht versioniert |
| WordPress-Core | `cms/wp-engine/core-<Version>/` | .htaccess gesperrt, nicht versioniert; aktueller + vorheriger Core bleiben |
| Plugins, Themes, Uploads | `cms/wp-content/` | wie bisher |

## Sicherheit der Core-Installation
Core wird bei der Installation von wordpress.org geladen: Version von uns gebildet (`^\d+\.\d+(\.\d+)?$`), feste Hosts (`api.wordpress.org`, `downloads.wordpress.org`, `wordpress.org`), SHA-1 aus `…zip.sha1`, Dateityp-Allowlist, keine Verknüpfungen/versteckten Dateien/Pfade außerhalb, Größenlimits (30 MB je Datei, 300 MB, 12 000 Dateien), PHP-Syntaxprüfung aller Dateien, atomares Verschieben. `wp-content/` des Pakets wird nicht übernommen. Cron, Auto-Updates und der Datei-Editor von WordPress sind abgeschaltet; Updates steuert ElvadoPress.

## Datenbank
Zuerst MySQL/MariaDB (SQLite später). Bestehende WordPress-Tabellen mit gleichem Präfix werden erkannt und nicht überschrieben (Meldung „leere Datenbank nötig“).

## Phasen
1 Analyse · **2 Engine-Fundament (dieser Stand)** · 3 Seiten/Beiträge/Taxonomien über Adapter · 4 Medien, Benutzer · 5 Plugins/Themes (echt) · 6 Komponenten-Register · 7 Live-Customizer · 8 Navigation/Widgets/Blöcke · 9 Migration · 10 RicoReWi-Paket · 11 Tests/Sicherheit/Doku.
Nicht „fertig“ nennen, solange zentrale Pfade Platzhalter sind. Tests: `scripts/test-wp-engine.php`.

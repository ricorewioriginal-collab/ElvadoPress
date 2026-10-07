# Architektur: ElvadoPress mit echter WordPress-Engine

Stand Phase 5 (Plugins und Themes, Absturzschutz). Die WordPress-Engine ist **standardmäßig aus**; ohne Aktivierung ändert sich an ElvadoPress und an öffentlichen Seiten nichts.

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
1 Analyse · 2 Engine-Fundament · **3 Seiten/Beiträge/Taxonomien über Adapter** · **4 Medien, Benutzer, Rechte** · **5 Plugins/Themes (echt, dieser Stand)** · 6 Komponenten-Register · 7 Live-Customizer · 8 Navigation/Widgets/Blöcke · 9 Migration · 10 RicoReWi-Paket · 11 Tests/Sicherheit/Doku.
Nicht „fertig“ nennen, solange zentrale Pfade Platzhalter sind. Tests: `scripts/test-wp-engine.php`.

## Phase 3: Inhalte über Dienst und Adapter
`ContentService` (`cms/src/Wp/ContentService.php`) prüft und bereinigt alle Eingaben serverseitig (Titel, Slug, Status, Datum, Kategorien/Schlagwörter, Bildadresse, Größen) und ruft den `ContentAdapter` auf. Einheitliches Inhaltsmodell: id, type, title, slug, content, excerpt, status (published/draft/scheduled/private/trash), date, modified, author, categories, tags, parent, image.
- `WordPressAdapter`: lesen und schreiben mit den echten WordPress-Funktionen (WP_Query, wp_insert_post, wp_set_object_terms …). Administratoren dürfen HTML ungefiltert speichern; ohne dieses Recht filtert WordPress (kses).
- `NativeAdapter`: liest den bisherigen ElvadoPress-Bestand (Beiträge aus `news.json`, Seiten aus `site.json`-Blöcken und Markdown) – Quelle für die spätere Migration; schreibgeschützt, die bisherige Verwaltung bleibt unverändert.
- API (`cms/engine-api.php`, nur Administratoren): `content_list|get|save|delete`, `term_list|save|delete`; Quelle `?source=native|wordpress` (Standard: wordpress, wenn die Engine aktiv ist).
- Noch nicht umgestellt: die bestehenden Panels „Beiträge“/„Seiten“ nutzen weiter die eigene Verwaltung; die Umstellung erfolgt mit der Migration (Phase 9). Medien und Benutzer: Phase 4 (Bilder werden bis dahin als Adresse gespeichert).
Tests: `scripts/test-wp-engine-content.php` (Eingabeprüfung, NativeAdapter; mit `WPE_TEST_ZIP`/`WPE_TEST_DB` zusätzlich 31 Prüfungen gegen echtes WordPress).

## Phase 4: Medien, Benutzer, Rechte
- **Rechte** (`Actor`, `Roles`): Rolle `admin` darf alles; `autor` legt Beiträge an und ändert/löscht nur **eigene** Beiträge und Medien (Besitzer = Anmeldename, in WordPress als Meta `_elvado_owner`), schreibt nur gefiltertes HTML (kses), darf weder Seiten noch Begriffe noch Benutzer noch die Engine verwalten. Herrenlose Inhalte ändern nur Administratoren. Die Dienste werfen `PermissionException` (API: 403).
- **Medien** (`MediaService` + `MediaAdapter`): erlaubt sind Bilder (JPEG, PNG, GIF, WebP, AVIF), PDF, Audio (MP3, OGG, WAV, M4A), Video (MP4, WebM) bis 32 MB. Geprüft werden Endung, **echter Inhalt** (finfo), Bildgültigkeit, PDF-Kopf, eingebetteter PHP-Code; Namen werden bereinigt (`a.php.png` → `a_php.png`). SVG, ZIP, HTML und Ausführbares werden abgelehnt. WordPress legt Anhänge samt Größen unter `cms/wp-content/uploads` ab (dort PHP-Ausführung gesperrt, keine Ordnerliste).
- **Benutzer** (`UserService` + `UserAdapter`): Anmeldung und Rechte bleiben bei ElvadoPress (lokale Benutzer). Die WordPress-Benutzer sind ein **Spiegel** (Autorenschaft, Kompatibilität); `user_sync` legt fehlende an und aktualisiert Name/E-Mail/Rolle, löscht nie und überträgt nie Passwörter (WordPress-Benutzer erhalten ein zufälliges, unbenutztes Passwort).
- **API** (`cms/engine-api.php`): `media_list|get|upload|update|delete`, `user_list`, `user_sync` (Quelle `?source=native|wordpress` wie bei Inhalten). Angemeldete Personen dürfen Inhalte und Medien nach ihren Rechten, alles andere nur Administratoren.
Tests: `scripts/test-wp-engine-media-users.php` (46 Prüfungen ohne Netz, mit `WPE_TEST_ZIP`/`WPE_TEST_DB` 26 weitere gegen echtes WordPress).

## Phase 5: Plugins und Themes (echt) mit Absturzschutz
- **Installation** (`ExtensionSource`, `ExtensionInstaller`, `ExtensionService`): aus dem offiziellen Verzeichnis (wordpress.org, nur feste Hosts über HTTPS; Slug und Version werden validiert, die Download-Adresse wird selbst gebaut) oder als ZIP. wordpress.org veröffentlicht für Plugins/Themes **keine Prüfsummen** – Schutz sind TLS, die Strukturprüfung (ein Hauptordner, keine Pfade nach außen, keine Verknüpfungen, Dateityp-Liste, Größen- und Dateilimits, versteckte Dateien werden nicht übernommen, Kopfzeile „Plugin Name“/„Theme Name“, PHP-Syntaxprüfung jeder PHP-Datei) und die SHA-256 im Protokoll. Atomar; eine vorhandene Fassung wird beim Update/Entfernen im Zustandsordner gesichert (die letzte je Eintrag). Ein Plugin/Theme ist Code, der mit den Rechten von ElvadoPress läuft: Installieren, Aktivieren und Entfernen nur für Administratoren.
- **Zustand** (`WordPressExtensionAdapter`): echte WordPress-Funktionen (get_plugins, activate_plugin mit WordPress-Sandbox und Aktivierungs-Hook, deactivate/uninstall_plugin, wp_get_themes, switch_theme). Das aktive Theme (und dessen Eltern-Theme) lässt sich nicht löschen.
- **Absturzschutz:** Vor Aktivieren/Theme-Wechsel wird ein Wächter geschrieben (`guard.json`). Der nächste Start von WordPress ist ein **Probelauf** unter Dateisperre (gleichzeitige Anfragen greifen nicht fälschlich ein); gelingt er, wird der Wächter gelöscht. Stürzt er ab, startet der Folgelauf **abgesichert** (keine Plugins, kein Theme) und macht die Änderung rückgängig (Plugin deaktiviert, früheres Theme gesetzt) – der Vorfall wird gemeldet. Ein schwerer Fehler **in einem Plugin/Theme beim Start** wird auch ohne vorherigen Wechsel erkannt (Shutdown-Handler: Datei unter `plugins/<x>` bzw. `themes/<x>`) und führt zum gleichen automatischen Abschalten des Verursachers. Der API-Aufruf antwortet bei einem Absturz mit sauberem JSON-Fehler (`crashed:true`). Zusätzlich gibt es einen **abgesicherten Modus** zum Ein-/Ausschalten.
- **Engine-Schicht** (`cms/src/Wp/mu/elvado-engine.php`, immer zuerst geladen): setzt den abgesicherten Modus um, lädt danach die Must-Use-Plugins aus `cms/wp-content/mu-plugins` und unterbindet Hintergrund-Updateabfragen.
- **API** (`ext_list|search|install|upload|activate|deactivate|delete|safe`, nur Administratoren) und Oberfläche **Plugins → Plugins & Themes (Engine)**.
Tests: `scripts/test-wp-engine-extensions.php` (57 Prüfungen ohne Netz, mit `WPE_TEST_ZIP`/`WPE_TEST_DB` 21 weitere gegen echtes WordPress inkl. provozierter Abstürze und abgesichertem Modus).
- Noch offen: Auslieferung der Website über das aktive WordPress-Theme (Phase 7/9); die bisherigen Plugin-/Theme-Panels der Nachbildung bleiben bis zur Migration unverändert.

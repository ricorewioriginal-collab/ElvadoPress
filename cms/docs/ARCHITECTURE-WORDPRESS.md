# Architektur: ElvadoPress mit echter WordPress-Engine

Stand Phase 11 (Tests, Sicherheit, Performance, Dokumentation). Die WordPress-Engine ist **standardmäßig aus**; ohne Aktivierung ändert sich an ElvadoPress und an öffentlichen Seiten nichts.

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
1 Analyse · 2 Engine-Fundament · **3 Seiten/Beiträge/Taxonomien über Adapter** · **4 Medien, Benutzer, Rechte** · **5 Plugins/Themes (echt)** · **6 Komponenten-Register** · **7 Live-Customizer/Preview Bridge** · **8 Navigation/Widgets/Blöcke (dieser Stand)** · 9 Migration · 10 Themenpakete · 11 Tests/Sicherheit/Doku.
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

## Phase 6: Komponenten-Registry
Siehe `COMPONENTS.md`. Kurz: zentrale Registry (`cms/src/Components/`) mit Schema, Regeln (locked/sortable/droppable/repeatable/slot), Rechten, Sichtbarkeit, responsiven Werten, nativer Darstellung und Erweiterungen (Hook `components_register`); `LayoutStore` mit Entwurf, Veröffentlichen, Revisionen, Rollback, Terminplanung; API `cms/components-api.php`. Das Baukasten-Theme bezieht sein Schema aus der Registry, sein Frontend bleibt unverändert. Noch offen: Preview-Bridge mit Klick-auf-Element (Phase 7), Anbindung von Header/Footer/Navigation der bestehenden Frontends, Migration.

## Phase 7: Live Customizer mit Preview Bridge
Siehe `LIVE-CUSTOMIZER.md`. Kurz: sichere Brücke Builder ↔ Vorschau (Herkunft + Frame + Sitzungsschlüssel), Klick auf Komponenten, Werkzeugleiste, Auswahl in beide Richtungen; Baum mit Verschachtelung; Entwurf/Veröffentlichen/Verlauf/Rollback/Termin über den `LayoutStore`; das Theme gibt jetzt alle nativen und WordPress-Komponenten aus. Noch offen: Header/Footer/Navigation als Komponenten des bestehenden Frontends, Layouts je Seite, Migration.

## Phase 8: Navigation, Widgets, Blöcke
- **Menüs** (`NavigationService` + `NavigationAdapter`): echte WordPress-Menüs (nav_menu, nav_menu_item). Der ganze Baum wird atomar gespeichert (neu anlegen, ändern, verschieben, fehlende entfernen); Einträge: eigener Link, Seite, Beitrag, Kategorie, Schlagwort (Adresse liefert WordPress), Ziel/rel/Klassen bereinigt, höchstens 4 Ebenen und 200 Einträge; fremde Eintrags-IDs werden als neu behandelt. Orte des Themes zuweisen/lösen; Menüs der bisherigen ElvadoPress-Verwaltung lassen sich als neues Menü übernehmen (`nav_import`).
- **Widgets** (`WidgetService` + `WidgetAdapter`, `WidgetSchemas`): echte Seitenleisten des Themes, Widget-Typen aus Core und Plugins, Einstellungen je Instanz. Kern-Widgets haben ein Formular-Schema (serverseitig bereinigt), Plugin-Widgets bearbeitet man als begrenztes JSON, das das Widget selbst prüft (`WP_Widget::update`). Anordnen und zwischen Bereichen/Ablage verschieben, nichts doppelt. Die Komponente „Plugin-Widget“ des Live Builders gibt Widgets über `the_widget` aus.
- **Blöcke** (`BlockService`, `Elvado\Blocks\Converter`): registrierte Block-Typen (Core und Plugins), Block-Markup lesen (`parse_blocks`) und erzeugen (`serialize_blocks`, mit Prüfung von Namen, Tiefe, Anzahl, Größe und – ohne Administratorrecht – kses), Ausgabe wie auf der Website, Prüfung auf unbekannte/dynamische Blöcke. **Fallback:** unbekannte Blöcke bleiben unverändert erhalten. **Umwandlung** ElvadoPress ↔ WordPress: schlichte Blöcke (Absatz, Überschrift, Liste, Zitat, Code, Trennlinie, Abstand, Bild, Spalten, Shortcode, HTML) werden echte Gutenberg-Blöcke; alles mit eigenen Stilen oder ohne Entsprechung wird ein HTML-Block mit dem unveränderten HTML – nichts geht verloren.
- **API** (`cms/engine-api.php`): `nav_list|get|create|rename|delete|save|assign|import`, `widgets_overview|add|update|move|delete`, `blocks_registry|parse|check|render|serialize|convert`. Ändern nur Administratoren; Block-Werkzeuge und Menü-Lesen auch Autoren. Oberfläche: Website-Bereich → „Menüs, Widgets & Blöcke (Engine)“.
Tests: `scripts/test-wp-engine-nav-widgets-blocks.php` (43 Prüfungen ohne Netz, mit `WPE_TEST_ZIP`/`WPE_TEST_DB` 46 weitere gegen echtes WordPress).

## Phase 9: Migration (nur Trockenlauf)
`cms/src/Wp/Migration/` (`Planner`, `ReportStore`, `TargetProbe` mit `NullProbe`/`WordPressProbe`), API `migration_plan|migration_report`, Karte im Engine-Panel. Der Trockenlauf liest nur und schreibt allein den Bericht; die echte Migration folgt erst nach ausdrücklicher Freigabe. Details: [MIGRATION.md](MIGRATION.md).

## Phase 10: Pakete / Projekt-Kompatibilität
Neutral im Kern: Komponenten mit `bind` („bound“, siehe [COMPONENTS.md](COMPONENTS.md)), Paket-Lader `cms/packs/<paket>/components.php`, Bearbeitungsziele im Live Builder, Vorschau-Schlüssel und `elvado_components_inject()`. Projektspezifische Komponenten liegen ausschließlich im jeweiligen Projekt-Repository; der Kern enthält keine Projektinhalte.

## Phase 11: Wer ist wofür zuständig, wer führt die Daten?
| Bereich | ElvadoPress | WordPress (Engine aktiv) |
|---|---|---|
| Oberfläche, Anmeldung, Rollen (Administrator/Autor), API, Live Builder, Komponenten, Layouts, Migration, Updates, KI, Apps | **ja** | – |
| Beiträge, Seiten, Kategorien/Schlagwörter, Medien, Menüs, Widgets, Blöcke | Adapter/Dienste, Rechteprüfung | **Datenquelle** (über `WordPressAdapter` und Co.) |
| WordPress-Plugins/-Themes | Verwaltung, Absturzschutz | **führt sie aus** |
| Native Plugins, JS-Plugins, Pakete | **ja** | – |

**Führende Datenquelle:** solange die Engine nicht aktiv ist oder nicht migriert wurde, führen die bisherigen ElvadoPress-Daten (`cms/data`, `cms/media`). Die Migration kopiert nur (Bestand bleibt unverändert) und schaltet **nicht** um; das Umschalten der bisherigen Verwaltungs-Panels auf die Engine ist ein eigener, noch ausstehender Schritt (siehe `KNOWN_ISSUES.md`). Bestehende Websites ohne Aktivierung bleiben unverändert.

## Sicherheit der API (geprüft durch `scripts/test-wp-engine-api.php`)
- **Anmeldung:** Sitzungsschlüssel nur im Header `X-ElvadoPress-Token` (oder `_tok` im Body/Query) – **nie per Cookie**. Damit gibt es keinen Cookie-Login und somit kein CSRF; fremde Seiten können keine Aktionen mit der Sitzung der Person auslösen.
- **Rechte:** Administratoren alles; Autoren nur Inhalte/Medien nach Besitz und Lesen von Menüs/Blöcken; alle Engine-, Plugin-, Theme-, Benutzer-, Migrations- und Layout-Aktionen für Websites/Bereiche nur Administratoren (403). Die Dienste prüfen zusätzlich selbst (`Actor::can`, `PermissionException`).
- **Methoden:** schreibende Aktionen nur per POST (405 sonst); Migration nur mit Bestätigung `MIGRIEREN`, Rückbau mit Bestätigung; Demo-Betrieb sperrt alles außer dem Status.
- **Eingaben:** Typen/Kennungen/Bereiche werden serverseitig geprüft (4xx mit Meldung, keine Pfade oder Stack-Traces nach außen); Layout-Bereiche nur `home|site:*|page:*|post:*`; Berichts- und Lauf-Kennungen nur im festen Format.
- **Ausgabe:** Inhalte werden bereinigt (kses ohne Recht auf ungefiltertes HTML), Komponentenwerte typgeprüft (`Sanitizer`), URLs nur harmlose Schemata, CSS-Eigenschaften aus einer Liste; die Oberfläche maskiert Ausgaben.
- **Dateien/Geheimnisse:** `cms/data/.wp-engine/` 0700, `db.json`/`keys.json`/Berichte/Protokolle 0600; Datenbank-Passwort steht in keiner API-Antwort und in keinem Protokoll; Hochladen nur Bilder/PDF/Audio/Video mit Inhaltsprüfung (kein SVG/PHP).
- **Updates/Installationen:** Core nur von festen WordPress-Hosts mit SHA-1-Prüfung; Plugins/Themes mit Größen-, Dateityp- und Syntaxprüfung; Wächter + abgesicherter Modus gegen Abstürze.

## Performance
- **WordPress wird nur gestartet, wenn die Anfrage es braucht.** Öffentliche Seiten, Status, Komponenten-Katalog, Layout-API, native Quellen, Trockenlauf-Bericht und alle **unberechtigten** Anfragen (401/403) starten WordPress nie – auch nicht bei aktiver Engine (Test mit Probe und echtem WordPress).
- Die Engine hat einen eigenen Einstieg (`engine-api.php`); WordPress läuft dort im globalen Gültigkeitsbereich ohne den Ballast der Verwaltung.
- Kalter Start von WordPress < 4 s, erneuter < 3 s auf der Testumgebung (gemessen im Test); die Verwaltungs-API ohne WordPress antwortet < 2,5 s (bester von zwei Aufrufen).
- Gebundene Bereiche (Pakete) kosten die öffentliche Seite nichts, solange nichts veröffentlicht ist (Datei-Prüfung vor dem Laden).

## Testmatrix (Masterprompt, Abschnitt 32)
| Thema | Test |
|---|---|
| WordPress-Verbindung, Core, Datenbank, Wächter | `test-wp-engine.php` (+ echtes WordPress) |
| Adapter, Beiträge, Seiten, Begriffe | `test-wp-engine-content.php` |
| Medien, Benutzer, Rollen/Rechte | `test-wp-engine-media-users.php` |
| Plugins, Themes | `test-wp-engine-extensions.php` |
| Navigation, Widgets, Blöcke | `test-wp-engine-nav-widgets-blocks.php` |
| Komponenten, Entwurf/Veröffentlichen, Verlauf, Termin, Vorschau | `test-components.php`, `test-baukasten.php`, `test-components-packs.php` |
| Migration (Trockenlauf, echter Lauf, Rückbau) | `test-wp-engine-migration.php` |
| API, Sicherheit, Performance | `test-wp-engine-api.php` |
| Oberfläche (Menü, Kopfleiste, Live Builder) | `test-shell-ui.php` |
Bestehende Tests laufen unverändert weiter; keiner wurde abgeschwächt.

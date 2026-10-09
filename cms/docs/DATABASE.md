# Datenbank

Das CMS arbeitet standardmäßig mit Dateien (`cms/data/*.json`). Eine Datenbank ist optional und wird im CMS unter **Einstellungen → Datenbank** verbunden (nur Administratoren). Sie dient

1. als **Spiegel** der Inhalte (Beiträge, Einstellungen) und
2. als **Speicher der WordPress-Schicht** (`$wpdb`: Plugins, Beiträge aus Plugins, Benutzer-/Kommentar-/Term-Tabellen).

## Unterstützte Datenbanken
| Treiber | Spiegel | WordPress-Schicht (`$wpdb`) | Hinweis |
|---|---|---|---|
| SQLite | ja | ja (über SQL-Übersetzer) | Standard für die WordPress-Schicht: `cms/data/.wp/wp.sqlite` |
| MySQL | ja | ja (nativ) | getestet mit MariaDB-kompatiblem Funktionsumfang |
| MariaDB | ja | ja (nativ) | getestet mit MariaDB 10.11 |
| PostgreSQL | ja | nein | die WordPress-Schicht bleibt dann bei SQLite |

Weitere Datenbanken lassen sich über `elvado_db_drivers()` in `cms/lib/database.php` ergänzen.

## Einstellungen
Host/Port oder **Socket**, Datenbankname, Benutzer, Passwort, **Tabellen-Präfix** (WordPress-Schicht, Standard `wp_`), Zeichensatz. „Datenbank anlegen, falls sie fehlt“ legt die Datenbank beim Testen an (Benutzer braucht das Recht dazu). Das Passwort liegt nur in `cms/data/database.local.php` (Rechte 0600, vom Webserver gesperrt) und wird nie an den Browser gesendet; leer lassen behält das bisherige.

**Verbindung testen** prüft Erreichbarkeit, Zugang, Version (MySQL/MariaDB/PostgreSQL) und die Rechte (Tabelle anlegen, schreiben, löschen) und nennt Fehler verständlich („Zugang abgelehnt“, „Datenbank existiert nicht“, „Server nicht erreichbar“).

## Für den geplanten Installer
Der Installer bei einer frischen Installation nutzt dieselben Funktionen aus `cms/lib/database.php`:
- `elvado_db_clean_input($eingaben)` – prüft/bereinigt die Eingaben
- `elvado_db_test($konfiguration, $anlegen)` – Verbindungstest, legt optional die Datenbank an
- `elvado_db_write_config($eingaben)` – speichert `cms/data/database.local.php`
- `elvado_db_connect()` / `elvado_db_pdo($konfiguration)` – Verbindung

## Tests
`php scripts/test-db-config.php` (SQLite immer; Server über `ELVADO_TEST_MYSQL="host|port|user|passwort"` und `ELVADO_TEST_PGSQL=…`). Die WordPress-Tests laufen mit `ELVADO_TEST_MYSQL=…` gegen einen echten MySQL-/MariaDB-Server.

## Eine Datenbank für CMS und WordPress-Kern
Es gibt **eine** Datenbank-Verbindung: die Einstellungen unter *System → Datenbank* (`cms/data/database.local.php`, Treiber MySQL/MariaDB). Sie nutzen gemeinsam
- der **CMS-Datenbankspiegel** (Tabellen `users`, `posts`, `ai_logs`, `lovable_widgets` ohne Präfix) und die **WordPress-Schicht des CMS** (Tabellenpräfix, Standard `wp_`),
- der **echte WordPress-Kern** (Website → WordPress-Engine): eigene Tabellen mit **eigenem Präfix** (Standard `wpk_`), gespeichert in `cms/data/.wp-engine/db.json` – dort steht nur noch das Präfix, keine Zugangsdaten.

**Regeln**
- Die Präfixe müssen verschieden sein (sonst würden sich die Tabellen der WordPress-Schicht und des Kerns überschneiden); beide Seiten prüfen das beim Speichern/Einrichten.
- Die Engine-Einrichtung fragt die Verbindung nur, wenn es noch keine gemeinsame Datenbank gibt; sie wird dann zur gemeinsamen Datenbank des CMS. Vorhandene Datenbank-Einstellungen werden nie überschrieben.
- Ältere Einrichtungen mit eigener, vollständiger `db.json` laufen unverändert weiter; „Gemeinsame Datenbank verwenden“ (Website → WordPress-Engine, Aktion `engine_db_unify`) übernimmt die Verbindung in die gemeinsamen Einstellungen. Die WordPress-Schicht des CMS bekommt dabei ein eigenes Präfix (`wpl_`, wenn die Engine `wp_` nutzt), bestehende Tabellen bleiben unberührt.
- Die Demo behält ihre abgeschottete eigene Verbindung (sie räumt ihre Tabellen selbst auf).
- Das Passwort verlässt den Server nie (nicht in Antworten, nicht im Log).
- Test: `scripts/test-db-shared.php`.


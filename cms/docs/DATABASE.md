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

Weitere Datenbanken lassen sich über `rrw_db_drivers()` in `cms/lib/database.php` ergänzen.

## Einstellungen
Host/Port oder **Socket**, Datenbankname, Benutzer, Passwort, **Tabellen-Präfix** (WordPress-Schicht, Standard `wp_`), Zeichensatz. „Datenbank anlegen, falls sie fehlt“ legt die Datenbank beim Testen an (Benutzer braucht das Recht dazu). Das Passwort liegt nur in `cms/data/database.local.php` (Rechte 0600, vom Webserver gesperrt) und wird nie an den Browser gesendet; leer lassen behält das bisherige.

**Verbindung testen** prüft Erreichbarkeit, Zugang, Version (MySQL/MariaDB/PostgreSQL) und die Rechte (Tabelle anlegen, schreiben, löschen) und nennt Fehler verständlich („Zugang abgelehnt“, „Datenbank existiert nicht“, „Server nicht erreichbar“).

## Für den geplanten Installer
Der Installer bei einer frischen Installation nutzt dieselben Funktionen aus `cms/lib/database.php`:
- `rrw_db_clean_input($eingaben)` – prüft/bereinigt die Eingaben
- `rrw_db_test($konfiguration, $anlegen)` – Verbindungstest, legt optional die Datenbank an
- `rrw_db_write_config($eingaben)` – speichert `cms/data/database.local.php`
- `rrw_db_connect()` / `rrw_db_pdo($konfiguration)` – Verbindung

## Tests
`php scripts/test-db-config.php` (SQLite immer; Server über `RRW_TEST_MYSQL="host|port|user|passwort"` und `RRW_TEST_PGSQL=…`). Die WordPress-Tests laufen mit `RRW_TEST_MYSQL=…` gegen einen echten MySQL-/MariaDB-Server.

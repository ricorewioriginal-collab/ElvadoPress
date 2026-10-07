# WordPress-Bridge

Die Bridge (`cms/src/Wp/Bridge.php`) startet den echten WordPress-Core im ElvadoPress-Prozess der Engine-API.

- `prepare()`: definiert `ABSPATH`, `WP_CONTENT_DIR` (= `cms/wp-content`), Datenbank-Konstanten, Schlüssel/Salze, `DISABLE_WP_CRON`, `AUTOMATIC_UPDATER_DISABLED`, `DISALLOW_FILE_EDIT`; verweigert den Start, wenn die Emulation geladen ist.
- `snapshot()`/`restore()`: WordPress verändert `$_GET/$_POST/$_COOKIE/$_REQUEST`, Zeitzone, Fehlerstufe, `display_errors` und die mbstring-Kodierung. Diese Werte werden vor dem Start gesichert und danach wiederhergestellt.
- `installSchema()`: richtet die Tabellen ein (`wp_install`, ohne E-Mail-Versand), legt den Benutzer `elvadopress` mit Zufallspasswort an und entfernt Beispielinhalte.
- WordPress überschreibt globale Variablen (z. B. `$action`): Code im selben Scope nutzt präfixierte Namen (`$rrwEngine…`).
- Ausgabeschutz: `rrw_wpe_guard_output()` wandelt HTML-Abbrüche von WordPress in JSON-Fehler.

API (nur Administratoren, in der Demo gesperrt außer `engine_status`): Inhalte `content_list|get|save|delete`, `term_list|save|delete` (siehe ARCHITECTURE-WORDPRESS.md, Phase 3); Verwaltung `engine_status`, `engine_prepare`, `engine_core`, `engine_db_test`, `engine_db_install`, `engine_analyze`, `engine_mode`, `engine_remove`. Oberfläche: Verwaltung → System → WordPress-Engine.

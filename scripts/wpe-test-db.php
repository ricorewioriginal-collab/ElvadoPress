<?php
// Gemeinsamer Helfer der Engine-Tests mit echtem WordPress: Datenbank aus WPE_TEST_DB.
// WPE_TEST_DB="host|name|benutzer|passwort" → MySQL/MariaDB (leere Datenbank); WPE_TEST_DB=sqlite → SQLite ohne Datenbankserver
// (SQLite-Integration aus WPE_TEST_SQLITE_ZIP, sonst von wordpress.org).
declare(strict_types=1);

function wpe_test_is_sqlite(string $dbs): bool { return strtolower(trim($dbs)) === 'sqlite'; }

/** Verbindungsangaben für DbConfig; bei SQLite wird vorher die Integration in die Test-Engine eingespielt. */
function wpe_test_cfg(\Elvado\Wp\Engine $eng, string $dbs, string $prefix): array
{
    if (wpe_test_is_sqlite($dbs)) {
        $r = (new \Elvado\Wp\SqliteIntegration($eng))->install((string)(getenv('WPE_TEST_SQLITE_ZIP') ?: ''));
        if (!$r['ok']) {
            echo 'Hinweis: SQLite-Integration nicht verfügbar: ' . $r['message'] . "\n";
        }
        return ['driver' => 'sqlite', 'prefix' => $prefix];
    }
    [$h, $name, $u, $pw] = array_pad(explode('|', $dbs), 4, '');
    return ['host' => $h, 'name' => $name, 'user' => $u, 'pass' => $pw, 'prefix' => $prefix];
}

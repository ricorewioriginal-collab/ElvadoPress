<?php
// WordPress-Engine je Website mit echtem WordPress + MariaDB/MySQL: gleiche Datenbank, getrennte Tabellen (eigenes Präfix), eigener Zustand.
// Nur mit WPE_TEST_ZIP und WPE_TEST_DB ("host:port|db|benutzer|passwort", leere Datenbank) wie test-wp-engine.php. Aufruf: php scripts/test-sites-engine-real.php
declare(strict_types=1);
$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
function rmrf(string $d): void { if (!is_dir($d)) return; foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') continue; $p = "$d/$f"; is_dir($p) && !is_link($p) ? rmrf($p) : @unlink($p); } @rmdir($d); }
// Kindprozess: Tabellen einer Website einrichten (echtes WordPress im globalen Gültigkeitsbereich)
if (($argv[1] ?? '') === '--child') {
    $c = (string)$argv[2]; $siteId = (string)$argv[3]; $title = (string)$argv[4];
    define('RRW_SITES_BASE', $c);
    require __DIR__ . '/../cms/lib/sites.php';
    require __DIR__ . '/../cms/src/autoload.php';
    rrw_site_use($siteId);
    $GLOBALS['rrw_wpe_engine'] = new Elvado\Wp\Engine($c, $siteId === '' ? "$c/data" : rrw_site_dir('data'));
    $GLOBALS['rrw_wpe_db'] = new Elvado\Wp\DbConfig($GLOBALS['rrw_wpe_engine']);
    $GLOBALS['rrw_wpe_opts'] = ['installing' => true];
    $_SERVER['HTTP_HOST'] = 'example.test';
    require __DIR__ . '/../cms/wp-engine-boot.php';
    $r = Elvado\Wp\Bridge::installSchema($title, 'test@example.invalid');
    exit($r['ok'] ? 0 : 1);
}
$zip = (string)getenv('WPE_TEST_ZIP'); $dbs = (string)getenv('WPE_TEST_DB');
if ($zip === '' || $dbs === '' || !is_file($zip)) { echo "Hinweis: übersprungen (WPE_TEST_ZIP/WPE_TEST_DB nicht gesetzt).\n"; exit(0); }
[$h, $name, $u, $pw] = array_pad(explode('|', $dbs), 4, '');
[$host, $port] = array_pad(explode(':', $h), 2, '3306');

$tmp = sys_get_temp_dir() . '/sites-eng-' . bin2hex(random_bytes(4));
$cms = "$tmp/cms";
@mkdir("$cms/data", 0755, true); @mkdir("$cms/wp-content", 0755, true);
$pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $u, $pw, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$prefixes = [];
register_shutdown_function(function () use ($pdo, &$prefixes, $tmp) {
    foreach ($prefixes as $p) { foreach ($pdo->query("SHOW TABLES LIKE '" . $p . "%'")->fetchAll(PDO::FETCH_COLUMN) as $tb) { $pdo->exec('DROP TABLE `' . $tb . '`'); } }
    rmrf($tmp);
});
define('RRW_SITES_BASE', $cms);
require __DIR__ . '/../cms/lib/sites.php';
require __DIR__ . '/../cms/src/autoload.php';
use Elvado\Wp\{Engine, DbConfig, CoreInstaller};
$z = new ZipArchive(); $z->open($zip);
preg_match('/\$wp_version\s*=\s*\'([^\']+)\'/', (string)$z->getFromName('wordpress/wp-includes/version.php'), $m); $z->close();

// Gemeinsame Verbindung des CMS
file_put_contents("$cms/data/database.local.php", '<?php return ' . var_export(['driver' => 'mariadb', 'host' => $host, 'port' => (int)$port, 'database' => $name, 'user' => $u, 'password' => $pw, 'prefix' => 'wpl_'], true) . ';');
rrw_site_create(['name' => 'Zweite', 'domains' => ['zweite.test'], 'enabled' => true]);

// Hauptwebsite
$main = new Engine($cms, "$cms/data");
$inst = (new CoreInstaller($main))->install($zip, $m[1], sha1_file($zip));
$md = new DbConfig($main); $mp = 'wptm_'; $prefixes[] = $mp;
$md->save(['prefix' => $mp]); $main->save(['db' => ['ready' => true]]);
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --child ' . escapeshellarg($cms) . ' "" "Hauptseite" 2>&1', $o, $rc);
t('Hauptwebsite: Core und WordPress-Tabellen', $inst['ok'] && $rc === 0, ($inst['message'] ?? '') . implode("\n", $o));

// Zweite Website: eigener Zustand, eigenes Präfix, gleiche Datenbank
rrw_site_use('zweite');
$sec = new Engine($cms, rrw_site_dir('data'));
$sd = new DbConfig($sec); $sp = $sd->defaultPrefix(); $prefixes[] = $sp;
$inst2 = (new CoreInstaller($sec))->install($zip, $m[1], sha1_file($zip));
$sd->save(['prefix' => $sp]); $sec->save(['db' => ['ready' => true]]);
$o = [];
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --child ' . escapeshellarg($cms) . ' zweite "Zweite Seite" 2>&1', $o, $rc2);
t('Zweite Website: Core erkannt und eigene Tabellen angelegt', $inst2['ok'] && $rc2 === 0, ($inst2['message'] ?? '') . implode("\n", $o));
rrw_site_use('');

$has = fn(string $p): bool => (bool)$pdo->query("SHOW TABLES LIKE '" . $p . "posts'")->fetchColumn();
t('Beide Websites haben eigene Tabellen in derselben Datenbank', $has($mp) && $has($sp) && $mp !== $sp);
$opt = fn(string $p): string => (string)$pdo->query('SELECT option_value FROM `' . $p . "options` WHERE option_name='blogname'")->fetchColumn();
t('Inhalte sind getrennt (Titel je Website)', $opt($mp) === 'Hauptseite' && $opt($sp) === 'Zweite Seite', $opt($mp) . ' / ' . $opt($sp));
t('Zustand getrennt: eigener Engine-Ordner und eigenes Präfix je Website', is_file("$cms/data/.wp-engine/db.json") && is_file("$cms/sites/zweite/data/.wp-engine/db.json")
    && json_decode((string)file_get_contents("$cms/sites/zweite/data/.wp-engine/db.json"), true)['prefix'] === $sp && json_decode((string)file_get_contents("$cms/data/.wp-engine/db.json"), true)['prefix'] === $mp);
t('Zugangsdaten stehen nur einmal (gemeinsame Datei), nicht je Website', !is_file("$cms/sites/zweite/data/database.local.php") && !str_contains((string)file_get_contents("$cms/sites/zweite/data/.wp-engine/db.json"), $pw));
echo $fail ? "$fail von $n Prüfungen fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n";
exit($fail ? 1 : 0);

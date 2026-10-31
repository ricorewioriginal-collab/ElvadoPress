<?php
// WordPress-Engine mit SQLite (ohne Datenbankserver): DbConfig, Drop-in, Bridge-Konstanten – ohne Netz.
// Mit WPE_TEST_ZIP=<wordpress-X.Y.Z.zip> (und optional WPE_TEST_SQLITE_ZIP=<sqlite-database-integration.zip>, sonst von wordpress.org) zusätzlich echtes WordPress auf SQLite.
// Die übrigen Engine-Tests laufen mit WPE_TEST_DB=sqlite ebenfalls komplett gegen SQLite.
declare(strict_types=1);
require __DIR__ . '/../cms/src/autoload.php';
use Elvado\Wp\{Engine, DbConfig, SqliteIntegration, CoreInstaller, Requirements, Bridge, ContentService};
use Elvado\Wp\Adapter\WordPressAdapter;

$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
function rmrf(string $d): void { if (!is_dir($d)) return; foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') continue; $p = "$d/$f"; is_dir($p) && !is_link($p) ? rmrf($p) : @unlink($p); } @rmdir($d); }

// ───────── Kindprozess: echtes WordPress auf SQLite ─────────
if (($argv[1] ?? '') === '--child') {
    $tmp = (string)$argv[2];
    $GLOBALS['elvado_wpe_engine'] = new Engine("$tmp/cms", "$tmp/cms/data");
    $GLOBALS['elvado_wpe_db'] = new DbConfig($GLOBALS['elvado_wpe_engine']);
    $GLOBALS['elvado_wpe_opts'] = ($argv[3] ?? '') === 'install' ? ['installing' => true] : [];
    $_SERVER['HTTP_HOST'] = 'example.test';
    require __DIR__ . '/../cms/wp-engine-boot.php';
    if (($argv[3] ?? '') === 'install') {
        $r = Bridge::installSchema('Test', 'test@example.invalid');
        t('Tabellen in SQLite angelegt', $r['ok'], $r['message']);
    }
    global $wpdb;
    t('WordPress nutzt die SQLite-Schicht', DB_ENGINE === 'sqlite' && $wpdb instanceof WP_SQLite_DB, get_class($wpdb));
    $svc = new ContentService(new WordPressAdapter());
    $p = $svc->save('post', ['title' => 'Hallo SQLite', 'content' => '<p>Text</p>', 'status' => 'published', 'categories' => ['News']], true);
    t('Beitrag gespeichert und gelesen', $svc->get('post', $p['id'])['title'] === 'Hallo SQLite');
    t('Tabellen mit Präfix', count((array)$wpdb->get_col("SHOW TABLES LIKE 'wpk\\_%'")) >= 12);
    echo "KIND: " . ($fail ? "$fail Fehler" : "$n von $n bestanden") . "\n";
    exit($fail ? 1 : 0);
}

$tmp = sys_get_temp_dir() . '/wpe-sqlite-' . bin2hex(random_bytes(4));
@mkdir("$tmp/cms/data", 0755, true); @mkdir("$tmp/cms/wp-content", 0755, true);
$e = new Engine("$tmp/cms", "$tmp/cms/data");
$db = new DbConfig($e);

// DbConfig
$v = $db->validate(['driver' => 'sqlite', 'prefix' => 'wpk_']);
t('SQLite braucht weder Server noch Zugangsdaten', $v['ok'] && $v['cfg']['driver'] === 'sqlite' && $v['cfg']['host'] === '' && str_ends_with($v['cfg']['path'], '/.wp-engine/sqlite/wordpress.sqlite'));
t('Ungültiges Präfix wird auch bei SQLite abgelehnt', !$db->validate(['driver' => 'sqlite', 'prefix' => 'Böse;'])['ok']);
$tr = $db->test(['driver' => 'sqlite', 'prefix' => 'wpk_']);
t('SQLite-Test meldet bereit (pdo_sqlite vorhanden)', extension_loaded('pdo_sqlite') ? $tr['ok'] && str_starts_with($tr['server'], 'SQLite') : !$tr['ok'], $tr['message']);
t('Datenbankordner liegt im gesperrten Zustandsordner', is_dir("$tmp/cms/data/.wp-engine/sqlite") && is_file("$tmp/cms/data/.wp-engine/.htaccess"));
$db->save(['driver' => 'sqlite', 'prefix' => 'wpk_']);
$saved = json_decode((string)file_get_contents("$tmp/cms/data/.wp-engine/db.json"), true);
t('db.json enthält nur Treiber und Präfix', $saved === ['driver' => 'sqlite', 'prefix' => 'wpk_'], json_encode($saved));
t('Engine liest SQLite-Angaben', $db->isSqlite() && ($db->get()['driver'] ?? '') === 'sqlite' && $db->get()['prefix'] === 'wpk_');
$red = $db->redacted();
t('Anzeige ohne Pfad und Passwort', $red['driver'] === 'sqlite' && !isset($red['path']) && $red['has_password'] === false);
t('Gemeinsame CMS-Datenbank überschreibt SQLite nicht', $db->withShared(['driver' => 'sqlite', 'prefix' => 'x_'])['driver'] === 'sqlite');
t('Übersicht nennt Treiber und Verfügbarkeit', $db->overview()['driver'] === 'sqlite' && array_key_exists('sqlite_available', $db->overview()));
// vorhandene Tabellen werden erkannt (nichts überschreiben)
$pdo = new PDO('sqlite:' . $db->sqlitePath()); $pdo->exec('CREATE TABLE wpk_options (option_id INTEGER)'); $pdo = null;
$tr2 = $db->test(['driver' => 'sqlite', 'prefix' => 'wpk_']);
t('Vorhandene WordPress-Tabellen mit gleichem Präfix: needs_empty', $tr2['ok'] && $tr2['needs_empty'] && $tr2['wp_tables'] === 1);
t('Anderes Präfix ist frei', !$db->test(['driver' => 'sqlite', 'prefix' => 'wpz_'])['needs_empty']);
@unlink($db->sqlitePath());

// Drop-in
$si = new SqliteIntegration($e);
file_put_contents("$tmp/cms/wp-content/db.php", "<?php // fremder Drop-in\n");
t('Fremder Drop-in wird erkannt und nie überschrieben', $si->foreignDropin() && (function () use ($si) { try { $si->writeDropin(); return false; } catch (RuntimeException) { return true; } })() && str_contains((string)file_get_contents("$tmp/cms/wp-content/db.php"), 'fremder'));
unlink("$tmp/cms/wp-content/db.php");
$si->writeDropin();
$code = (string)file_get_contents("$tmp/cms/wp-content/db.php");
t('Eigener Drop-in wirkt nur mit DB_ENGINE sqlite', str_contains($code, SqliteIntegration::MARKER) && str_contains($code, "DB_ENGINE === 'sqlite'") && !$si->foreignDropin());
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg("$tmp/cms/wp-content/db.php"), $o, $rc);
t('Drop-in ist gültiges PHP', $rc === 0);
t('Ohne Integration gilt SQLite als nicht eingerichtet', !$si->present());
// Bridge verweigert den Start ohne Integration (statt mit einem Datenbankfehler)
@mkdir($e->coreRoot() . '/core-6.0', 0750, true); file_put_contents($e->coreRoot() . '/core-6.0/wp-settings.php', '<?php');
$e->save(['core' => 'core-6.0', 'mode' => 'installed']);
$msg = ''; try { Bridge::prepare($e, $db); } catch (RuntimeException $x) { $msg = $x->getMessage(); }
t('Start ohne SQLite-Integration bricht verständlich ab', str_contains($msg, 'SQLite-Integration'), $msg);
rmrf($e->coreRoot() . '/core-6.0'); $e->save(['core' => '', 'mode' => 'off']);

// Systemprüfung: ohne mysqli ist SQLite der Ausweg (nur Warnung)
$req = Requirements::check($e);
$my = array_values(array_filter($req['items'], fn($i) => $i['id'] === 'ext_mysqli'))[0] ?? null;
t('Systemprüfung führt die Datenbank-Erweiterung', $my !== null && (extension_loaded('mysqli') ? $my['status'] === 'ok' : (extension_loaded('pdo_sqlite') ? $my['status'] === 'warn' : $my['status'] === 'fail')));

// ───────── echtes WordPress auf SQLite (optional) ─────────
$zip = (string)getenv('WPE_TEST_ZIP');
if ($zip === '' || !is_file($zip) || !extension_loaded('pdo_sqlite')) {
    echo "Hinweis: Teil mit echtem WordPress übersprungen (WPE_TEST_ZIP nicht gesetzt).\n";
} else {
    $z = new ZipArchive(); $z->open($zip);
    preg_match('/\$wp_version\s*=\s*\'([^\']+)\'/', (string)$z->getFromName('wordpress/wp-includes/version.php'), $m); $z->close();
    $r = (new CoreInstaller($e))->install($zip, $m[1], sha1_file($zip));
    t('Echter Core eingespielt', $r['ok'], $r['message']);
    unlink("$tmp/cms/wp-content/db.php");
    $s = $si->install((string)(getenv('WPE_TEST_SQLITE_ZIP') ?: ''));
    t('SQLite-Integration geprüft eingespielt (außerhalb von wp-content/plugins)', $s['ok'] && $si->present() && !is_dir("$tmp/cms/wp-content/plugins/sqlite-database-integration") && $si->version() !== '', $s['message']);
    $db->save(['driver' => 'sqlite', 'prefix' => 'wpk_']); $e->save(['db' => ['ready' => true]]);
    foreach (['install', ''] as $step) {
        $out = []; exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --child ' . escapeshellarg($tmp) . ' ' . $step . ' 2>&1', $out, $rc);
        foreach ($out as $l) { echo $l . "\n"; }
        t('Kindprozess ' . ($step ?: 'Folgestart') . ' erfolgreich', $rc === 0);
    }
    t('Datenbankdatei geschützt', is_file($db->sqlitePath()) && (fileperms(dirname($db->sqlitePath())) & 0007) === 0);
    require_once __DIR__ . '/../cms/lib/wpengine.php';
    $rm = elvado_wpe_remove($e);
    t('Entfernen räumt Integration und Drop-in weg, Datenbankdatei bleibt', $rm['ok'] && !is_dir($si->dir()) && !is_file("$tmp/cms/wp-content/db.php") && is_file($db->sqlitePath()));
}
rmrf($tmp);
echo $fail ? "$fail von $n Prüfungen fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n";
exit($fail ? 1 : 0);

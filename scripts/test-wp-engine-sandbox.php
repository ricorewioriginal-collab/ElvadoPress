<?php
// Sandbox auf echtem WordPress (cms/lib/wp-real-sandbox.php): Kopie der Datenbank (SQLite-Datei bzw. eigenes Präfix in MySQL/MariaDB), Unterschiede,
// Live stellen mit Sicherung, Zurückrollen, Start von WordPress auf der Sandbox-Datenbank (Rollen vorhanden).
// Aufruf: WPE_TEST_ZIP=<wordpress.zip> WPE_TEST_DB=sqlite|"host|name|user|pass" php scripts/test-wp-engine-sandbox.php (ohne beides: übersprungen)
declare(strict_types=1);
require __DIR__ . '/../cms/src/autoload.php';
require_once __DIR__ . '/wpe-test-db.php';
require_once __DIR__ . '/../cms/lib/wp-real-sandbox.php';
use Elvado\Wp\{Engine, CoreInstaller, DbConfig, Bridge};

$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
function rmrf(string $d): void { if (!is_dir($d)) return; foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') continue; $p = "$d/$f"; is_dir($p) && !is_link($p) ? rmrf($p) : @unlink($p); } @rmdir($d); }

// Kindprozess: WordPress starten (install = Tabellen anlegen; sandbox = auf der Sandbox-Datenbank) und Werte ausgeben
if (($argv[1] ?? '') === '--child') {
    $tmp = (string)$argv[2];
    $e = new Engine("$tmp/cms", "$tmp/cms/data");
    $db = new DbConfig($e);
    $GLOBALS['elvado_wpe_engine'] = $e;
    $GLOBALS['elvado_wpe_db'] = $db;
    $GLOBALS['elvado_wpe_opts'] = ($argv[3] ?? '') === 'install' ? ['installing' => true] : [];
    if (($argv[3] ?? '') === 'sandbox') {
        $GLOBALS['elvado_wpe_db_override'] = elvado_wprs_override($db->get(), "$tmp/cms/data");
    }
    $_SERVER['HTTP_HOST'] = 'example.test';
    require __DIR__ . '/../cms/wp-engine-boot.php';
    if (($argv[3] ?? '') === 'install') {
        $r = Bridge::installSchema('Sandbox-Test', 'test@example.invalid');
        echo json_encode(['ok' => $r['ok']]);
        exit;
    }
    echo json_encode(['stylesheet' => get_option('stylesheet'), 'roles' => wp_roles()->is_role('administrator'), 'blogname' => get_option('blogname')]);
    exit;
}
$zip = (string)getenv('WPE_TEST_ZIP');
$dbs = (string)getenv('WPE_TEST_DB');
if ($zip === '' || !is_file($zip) || $dbs === '') {
    echo "Hinweis: übersprungen (WPE_TEST_ZIP/WPE_TEST_DB nicht gesetzt).\n0 von 0 Prüfungen bestanden\n";
    exit(0);
}
$tmp = sys_get_temp_dir() . '/wpe-sbx-' . bin2hex(random_bytes(4));
@mkdir("$tmp/cms/data", 0755, true); @mkdir("$tmp/cms/wp-content", 0755, true);
register_shutdown_function(fn() => rmrf($tmp));
$e = new Engine("$tmp/cms", "$tmp/cms/data");
$z = new ZipArchive(); $z->open($zip);
preg_match('/\$wp_version\s*=\s*\'([^\']+)\'/', (string)$z->getFromName('wordpress/wp-includes/version.php'), $m); $z->close();
$r = (new CoreInstaller($e))->install($zip, $m[1], sha1_file($zip));
t('Core eingespielt', $r['ok'], $r['message']);
$prefix = 'sbt' . substr(bin2hex(random_bytes(2)), 0, 3) . '_';
$db = new DbConfig($e);
$db->save(wpe_test_cfg($e, $dbs, $prefix));
$e->save(['db' => ['ready' => true]]);
$child = function (string $step) use ($tmp): array {
    $out = [];
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --child ' . escapeshellarg($tmp) . ' ' . $step . ' 2>&1', $out, $rc);
    $j = json_decode((string)end($out), true);
    return is_array($j) ? $j : ['error' => implode("\n", array_slice($out, -5))];
};
t('Tabellen angelegt', ($child('install')['ok'] ?? false) === true);
$live = $db->get();
$data = "$tmp/cms/data";

t('Sandbox anlegen (Kopie der Datenbank)', elvado_wprs_create($live, $data) === null && elvado_wprs_exists($data));
t('Ohne Änderung keine Unterschiede', elvado_wprs_diff($live, $data)['changed'] === false);
$sc = elvado_wprs_db($live, $data, true);
elvado_wprs_apply($sc, ['stylesheet' => ['sandbox-theme', 'on'], 'widget_text' => [serialize([2 => ['text' => 'Sandbox']]), 'auto']]);
$d = elvado_wprs_diff($live, $data);
t('Unterschiede: Theme und Widget', $d['changed'] && in_array('stylesheet', $d['options'], true) && in_array('widget_text', $d['options'], true) && $d['sandbox_theme'] === 'sandbox-theme', json_encode($d));
$sb = $child('sandbox');
t('WordPress startet auf der Sandbox-Datenbank (Rollen vorhanden)', ($sb['stylesheet'] ?? '') === 'sandbox-theme' && ($sb['roles'] ?? false) === true, json_encode($sb));
$lv = $child('live');
t('Live-Website bleibt unverändert', ($lv['stylesheet'] ?? '') !== 'sandbox-theme' && ($lv['roles'] ?? false) === true, json_encode($lv));
$p = elvado_wprs_publish($live, $data);
t('Live stellen mit Sicherung', $p['ok'] && count(elvado_wprs_backups($data)) === 1, json_encode($p));
t('Live nutzt jetzt die Sandbox-Werte', ($child('live')['stylesheet'] ?? '') === 'sandbox-theme');
t('Zurückrollen stellt den alten Live-Stand her', elvado_wprs_rollback($live, $data, (string)$p['backup']) === null && ($child('live')['stylesheet'] ?? '') === ($lv['stylesheet'] ?? 'x'));
$tok = (string)elvado_wprs_meta($data)['token'];
t('Link erneuern macht den alten ungültig', elvado_wprs_rotate($data) === null && !elvado_wprs_token_ok($data, $tok) && elvado_wprs_token_ok($data, (string)elvado_wprs_meta($data)['token']));
elvado_wprs_delete($live, $data);
t('Löschen entfernt die Sandbox, Live bleibt', !elvado_wprs_exists($data) && ($child('live')['roles'] ?? false) === true);
if (!wpe_test_is_sqlite($dbs)) {   // MySQL/MariaDB: Testtabellen beider Präfixe aufräumen
    $c = elvado_wprs_db($live, $data, false);
    foreach ($c['pdo']->query("SHOW TABLES LIKE '" . str_replace('_', '\\_', $prefix) . "%'")->fetchAll(PDO::FETCH_COLUMN) as $tb) {
        $c['pdo']->exec('DROP TABLE `' . $tb . '`');
    }
}
echo $fail ? "$fail von $n Prüfungen fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n";
exit($fail ? 1 : 0);

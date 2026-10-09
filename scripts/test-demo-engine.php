<?php
// Demo mit echtem WordPress: Aufbau (engine_demo_setup), Sperren (Plugin-/Theme-Installation, Upload, manueller Aufbau), Funktionen (Inhalte, Migration), Zurücksetzen (Tabellen leer, Core bleibt).
// Ohne Datenbank (WPE_TEST_ZIP/WPE_TEST_DB) nur die statischen Prüfungen und das Verhalten ohne demo-engine.json. Aufruf: php scripts/test-demo-engine.php
declare(strict_types=1);
$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
function rmrf(string $d): void { if (!is_dir($d)) return; foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') continue; $p = "$d/$f"; is_dir($p) && !is_link($p) ? rmrf($p) : @unlink($p); } @rmdir($d); }
function cp(string $from, string $to, array $skip): void { @mkdir($to, 0755, true); foreach (scandir($from) as $f) { if ($f === '.' || $f === '..' || in_array($f, $skip, true)) continue; $a = "$from/$f"; is_dir($a) ? cp($a, "$to/$f", []) : copy($a, "$to/$f"); } }
if (!function_exists('proc_open')) { echo "übersprungen: proc_open nicht verfügbar\n"; exit(0); }
$zip = (string)getenv('WPE_TEST_ZIP'); $dbs = (string)getenv('WPE_TEST_DB');
$real = $zip !== '' && $dbs !== '' && is_file($zip);
[$h, $dbn, $du, $dp] = array_pad(explode('|', $dbs), 4, '');

$tmp = sys_get_temp_dir() . '/demo-eng-' . bin2hex(random_bytes(4)); $site = "$tmp/site"; $cms = "$site/cms";
mkdir($site, 0755, true);
cp(__DIR__ . '/../cms', $cms, ['data', 'wp-engine', 'uploads', 'demo-state']);
foreach (['data', 'demo-state'] as $d) { mkdir("$cms/$d", 0755, true); }
passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/make-demo.php') . ' ' . escapeshellarg($site) . ' --minutes=5' . ($real ? ' --engine=' . escapeshellarg("$h|$dbn|$du|$dp|wpdemo_") : '') . ' > /dev/null');
if ($real) { $j = json_decode((string)file_get_contents("$cms/lib/demo-engine.json"), true); $j['core_zip'] = $zip; file_put_contents("$cms/lib/demo-engine.json", json_encode($j)); }

// ───────── statisch ─────────
$mk = (string)file_get_contents("$cms/lib/.htaccess");
t('demo-engine.json ist nicht abrufbar (.htaccess)', str_contains($mk, 'demo-engine.json') && str_contains($mk, 'Require all denied'));
if ($real) { t('demo-engine.json nur für den Besitzer lesbar (0600)', (fileperms("$cms/lib/demo-engine.json") & 0077) === 0); }
$api = (string)file_get_contents("$cms/engine-api.php");
t('Gesperrte Demo-Aktionen: Aufbau von Hand, Installation, Upload, Löschen', preg_match('/RRW_DEMO_ENGINE_BLOCKED/', $api) === 1 && str_contains((string)file_get_contents("$cms/lib/demo.php"), "'ext_upload'") && str_contains((string)file_get_contents("$cms/lib/demo.php"), "'engine_db_install'"));
t('Zurücksetzen löscht nur Tabellen mit dem Demo-Präfix', str_contains((string)file_get_contents("$cms/lib/demo.php"), "SHOW TABLES LIKE") && str_contains((string)file_get_contents("$cms/lib/demo.php"), '$c[\'prefix\']'));

$sock = stream_socket_server('tcp://127.0.0.1:0', $en, $es); $port = (int)explode(':', (string)stream_socket_get_name($sock, false))[1]; fclose($sock);
$proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $site], [1 => ['file', "$tmp/s.out", 'w'], 2 => ['file', "$tmp/s.err", 'w']], $pipes);
register_shutdown_function(function () use ($proc, $tmp) { if (is_resource($proc)) { proc_terminate($proc); proc_close($proc); } rmrf($tmp); });
for ($i = 0; $i < 50; $i++) { if (@fsockopen('127.0.0.1', $port)) break; usleep(100000); }
function http(string $method, string $path, ?string $tok = null, ?array $body = null): array {
    global $port;
    $h = ['Content-Type: application/json']; if ($tok !== null) { $h[] = 'X-ElvadoPress-Token: ' . $tok; }
    $ctx = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $h), 'content' => $body === null ? '' : json_encode($body), 'ignore_errors' => true, 'timeout' => 180]]);
    $b = (string)@file_get_contents("http://127.0.0.1:$port$path", false, $ctx); $code = 0;
    foreach ($http_response_header ?? [] as $l) { if (preg_match('#^HTTP/\S+ (\d+)#', $l, $m)) { $code = (int)$m[1]; } }
    return ['code' => $code, 'body' => $b, 'json' => json_decode($b, true) ?: null];
}
$ds = http('GET', '/cms/api.php?action=demo_status');
t('Demo läuft und meldet, ob die Engine vorbereitet ist', ($ds['json']['status'] ?? '') === 'ok' && ($ds['json']['engine'] ?? null) === $real, $ds['body']);
$login = http('POST', '/cms/api.php?action=login', null, ['username' => 'demo', 'password' => 'ElvadoPress-Demo1']);
$tok = (string)($login['json']['token'] ?? '');
t('Demo-Anmeldung', str_starts_with($tok, 'local_'), $login['body']);
$E = '/cms/engine-api.php?action=';
t('Engine-Status in der Demo lesbar (Betriebsart aus, solange nicht aufgebaut)', ($j = http('GET', $E . 'engine_status', $tok))['code'] === 200 && ($j['json']['engine']['mode'] ?? '') === 'off', $j['body']);

if (!$real) {
    $r = http('POST', $E . 'engine_demo_setup', $tok, []); $c = http('POST', $E . 'engine_core', $tok, []);
    t('Ohne demo-engine.json: Aufbau und alle Engine-Aktionen gesperrt (wie bisher)', $r['code'] === 403 && $c['code'] === 403 && http('GET', $E . 'content_list&type=post', $tok)['code'] === 403);
    echo "Hinweis: Teil mit echtem WordPress übersprungen (WPE_TEST_ZIP/WPE_TEST_DB nicht gesetzt).\n";
} else {
    foreach (['engine_core', 'engine_db_install', 'engine_db_test', 'engine_mode', 'engine_remove', 'ext_upload'] as $a) { $c = http('POST', $E . $a, $tok, ['mode' => 'off', 'slug' => 'x']); if ($c['code'] !== 403) { t("Demo sperrt $a", false, (string)$c['code']); } }
    t('Demo sperrt Aufbau von Hand und ZIP-Upload (403)', true);
    $bad = http('POST', $E . 'ext_install', $tok, ['kind' => 'plugin', 'slug' => 'irgendein-fremdes-plugin']); $badT = http('POST', $E . 'ext_install', $tok, ['kind' => 'theme', 'slug' => 'astra']); $badD = http('POST', $E . 'ext_delete', $tok, ['kind' => 'plugin', 'id' => 'woocommerce/woocommerce.php']);
    t('Demo: nicht freigegebene Plugins/Themes lassen sich weder installieren noch löschen', $bad['code'] === 403 && $badT['code'] === 403 && $badD['code'] === 403 && str_contains($bad['body'], 'ausgewählte') && ($bad['json']['demo'] ?? false) === true, $bad['body']);
    $okI = http('POST', $E . 'ext_install', $tok, ['kind' => 'plugin', 'slug' => 'classic-editor']);
    t('Demo: freigegebene Plugins/Themes sind nicht von der Demo gesperrt (ob der Download klappt, hängt vom Netz ab)', !(($okI['json']['demo'] ?? false) === true && $okI['code'] === 403), $okI['body']);
    t('Aufbau verlangt POST', http('GET', $E . 'engine_demo_setup', $tok)['code'] === 405);
    $t0 = microtime(true); $s = http('POST', $E . 'engine_demo_setup', $tok, []); $ms = (int)round((microtime(true) - $t0) * 1000);
    t('Aufbau: Core eingespielt, Tabellen angelegt, Engine aktiv', ($s['json']['status'] ?? '') === 'ok' && ($s['json']['engine']['mode'] ?? '') === 'active', $s['body']);
    $again = http('POST', $E . 'engine_demo_setup', $tok, []);
    t('Aufbau ist wiederholbar (schon bereit)', ($again['json']['status'] ?? '') === 'ok' && ($again['json']['engine']['mode'] ?? '') === 'active');
    $c = http('POST', $E . 'content_save&type=post', $tok, ['type' => 'post', 'item' => ['title' => 'Demo-Beitrag', 'content' => '<p>Hallo</p>', 'status' => 'published']]);
    t('Funktion: Beitrag in WordPress anlegen', ($c['json']['status'] ?? '') === 'ok', $c['body']);
    t('Funktion: Liste zeigt ihn', ($l = http('GET', $E . 'content_list&type=post', $tok))['code'] === 200 && ($l['json']['total'] ?? 0) === 1, $l['body']);
    t('Funktion: Plugins & Themes sind lesbar (aktivieren ja, installieren nein)', http('GET', $E . 'ext_list&kind=plugin', $tok)['code'] === 200 && http('GET', $E . 'blocks_registry', $tok)['code'] === 200 && http('GET', $E . 'nav_list', $tok)['code'] === 200 && http('GET', $E . 'widgets_overview', $tok)['code'] === 200);
    $pl = http('POST', $E . 'migration_plan', $tok, []);
    t('Funktion: Migration (Trockenlauf) prüft das Ziel', ($pl['json']['report']['engine']['target_checked'] ?? false) === true, $pl['body']);
    t('Funktion: Layout im Live Builder speicherbar', http('POST', '/cms/components-api.php?action=layout_save_draft&scope=home', $tok, ['layout' => []])['code'] === 200);
    $m = @new mysqli($h === 'localhost' ? '127.0.0.1' : explode(':', $h)[0], $du, $dp, $dbn, (int)(explode(':', $h)[1] ?? 3306));
    $cnt = fn() => (int)$m->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'wpdemo\\_%'")->fetch_row()[0];
    t('Datenbank: Tabellen mit Demo-Präfix vorhanden', $cnt() >= 10, (string)$cnt());
    $m->query('CREATE TABLE IF NOT EXISTS keep_me (id INT)');   // fremde Tabelle (anderes Präfix) darf das Zurücksetzen nie berühren
    // Zurücksetzen erzwingen
    file_put_contents("$cms/demo-state/state.json", json_encode(['started' => time() - 99999, 'resets' => 1]));
    $r = http('GET', '/cms/api.php?action=demo_status');
    $st = http('GET', $E . 'engine_status', http('POST', '/cms/api.php?action=login', null, ['username' => 'demo', 'password' => 'ElvadoPress-Demo1'])['json']['token'] ?? '');
    t('Zurücksetzen: Engine-Zustand weg, Core-Dateien bleiben', ($st['json']['engine']['mode'] ?? '') === 'off' && count(glob("$cms/wp-engine/core-*", GLOB_ONLYDIR) ?: []) === 1, $st['body']);
    t('Zurücksetzen: Demo-Tabellen gelöscht, fremde Tabelle unberührt', $cnt() === 0 && (int)$m->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'keep_me'")->fetch_row()[0] === 1, (string)$cnt());
    $tok2 = (string)(http('POST', '/cms/api.php?action=login', null, ['username' => 'demo', 'password' => 'ElvadoPress-Demo1'])['json']['token'] ?? '');
    $t1 = microtime(true); $s2 = http('POST', $E . 'engine_demo_setup', $tok2, []); $ms2 = (int)round((microtime(true) - $t1) * 1000);
    t('Nach dem Zurücksetzen richtet sich die Engine neu ein – ohne erneuten Download, sauber leer', ($s2['json']['engine']['mode'] ?? '') === 'active' && (http('GET', $E . 'content_list&type=post', $tok2)['json']['total'] ?? -1) === 0, $s2['body']);
    t('Einrichtung dauert vertretbar (erstmals < 60 s, erneut < 30 s)', $ms < 60000 && $ms2 < 30000, "$ms / $ms2 ms");
    $logs = (string)@file_get_contents("$tmp/s.err") . (string)@file_get_contents("$cms/data/.wp-engine/log.txt");
    t('Demo-Datenbankpasswort steht in keiner Antwort/Protokolldatei', !str_contains($s['body'] . $s2['body'] . $logs, $dp) || $dp === '');
    // ohne demo-engine.json wieder gesperrt
    unlink("$cms/lib/demo-engine.json");
    $tok3 = (string)(http('POST', '/cms/api.php?action=login', null, ['username' => 'demo', 'password' => 'ElvadoPress-Demo1'])['json']['token'] ?? '');
    t('Ohne demo-engine.json: Engine in der Demo wieder gesperrt', http('GET', $E . 'content_list&type=post', $tok3)['code'] === 403 && http('POST', $E . 'engine_demo_setup', $tok3, [])['code'] === 403);
    $m->query('DROP TABLE IF EXISTS keep_me');
    $res = $m->query("SHOW TABLES LIKE 'wpdemo\\\\_%'"); while ($res && ($row = $res->fetch_row())) { $m->query('DROP TABLE `' . $m->real_escape_string($row[0]) . '`'); }
}
echo $fail ? "$fail von $n Prüfungen fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n";
exit($fail ? 1 : 0);

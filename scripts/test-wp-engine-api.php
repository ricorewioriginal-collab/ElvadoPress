<?php
// API, Sicherheit und Performance der WordPress-Engine und der Komponenten-/Layout-API – gegen einen echten PHP-Server (php -S) mit Kopie der Verwaltung.
// Geprüft: ohne/mit falscher Anmeldung nichts (401), Cookie allein genügt nicht (kein CSRF), Autoren nur nach ihren Rechten (403), Methoden (405), Eingabeprüfung (4xx statt Absturz),
// keine Geheimnisse in Antworten und Protokollen, Dateirechte, und: WordPress wird NICHT gestartet, wenn die Anfrage es nicht braucht.
// Aufruf: php scripts/test-wp-engine-api.php   (Teil mit echtem WordPress nur mit WPE_TEST_ZIP/WPE_TEST_DB wie in den anderen Engine-Tests)
declare(strict_types=1);
$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
function rmrf(string $d): void { if (!is_dir($d)) return; foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') continue; $p = "$d/$f"; is_dir($p) && !is_link($p) ? rmrf($p) : @unlink($p); } @rmdir($d); }
function cp(string $from, string $to, array $skip): void { @mkdir($to, 0755, true); foreach (scandir($from) as $f) { if ($f === '.' || $f === '..' || in_array($f, $skip, true)) continue; $a = "$from/$f"; is_dir($a) ? cp($a, "$to/$f", []) : copy($a, "$to/$f"); } }
// Kindprozess: WordPress-Tabellen einrichten (echtes WordPress im globalen Gültigkeitsbereich)
if (($argv[1] ?? '') === '--child') {
    require __DIR__ . '/../cms/src/autoload.php';
    $c = (string)$argv[2];
    $GLOBALS['elvado_wpe_engine'] = new Elvado\Wp\Engine($c, "$c/data");
    $GLOBALS['elvado_wpe_db'] = new Elvado\Wp\DbConfig($GLOBALS['elvado_wpe_engine']);
    $GLOBALS['elvado_wpe_opts'] = ['installing' => true];
    $_SERVER['HTTP_HOST'] = 'example.test';
    require __DIR__ . '/../cms/wp-engine-boot.php';
    $r = Elvado\Wp\Bridge::installSchema('Test', 'test@example.invalid');
    exit($r['ok'] ? 0 : 1);
}
if (!function_exists('proc_open')) { echo "übersprungen: proc_open nicht verfügbar\n"; exit(0); }

$tmp = sys_get_temp_dir() . '/wpe-api-' . bin2hex(random_bytes(4));
$site = "$tmp/site"; $cms = "$site/cms";
mkdir($site, 0755, true);
cp(__DIR__ . '/../cms', $cms, ['data', 'wp-engine', 'uploads']);
mkdir("$cms/data", 0755, true);
file_put_contents("$cms/data/local-auth.local.php", '<?php return ' . var_export(['users' => [
    ['username' => 'chef', 'password_hash' => password_hash('Test-Passwort-123!', PASSWORD_DEFAULT), 'role' => 'admin', 'display_name' => 'Chef', 'created_at' => date(DATE_ATOM)],
    ['username' => 'anna', 'password_hash' => password_hash('Test-Passwort-456!', PASSWORD_DEFAULT), 'role' => 'autor', 'display_name' => 'Anna', 'created_at' => date(DATE_ATOM)],
]], true) . ';');
file_put_contents("$cms/data/news.json", json_encode([['id' => 1, 'slug' => 'hallo', 'title' => 'Hallo Welt', 'category' => 'Radio', 'body_html' => '<p>x</p>', 'status' => 'published', 'published_at' => '2024-01-01 10:00:00']]));
$log = "$tmp/probe.log";
file_put_contents("$tmp/probe.php", '<?php register_shutdown_function(function () { file_put_contents(' . var_export($log, true) . ', json_encode(["uri" => $_SERVER["REQUEST_URI"] ?? "", "abspath" => defined("ABSPATH"), "wp" => count(array_filter(get_included_files(), fn($x) => str_contains($x, "/wp-engine/core-") || str_contains($x, "wp-engine-boot.php")))]) . "\n", FILE_APPEND); });');
$sock = stream_socket_server('tcp://127.0.0.1:0', $en, $es); $port = (int)explode(':', (string)stream_socket_get_name($sock, false))[1]; fclose($sock);
$proc = proc_open([PHP_BINARY, '-d', 'auto_prepend_file=' . "$tmp/probe.php", '-S', "127.0.0.1:$port", '-t', $site], [1 => ['file', "$tmp/server.out", 'w'], 2 => ['file', "$tmp/server.err", 'w']], $pipes);
register_shutdown_function(function () use ($proc, $tmp) { if (is_resource($proc)) { proc_terminate($proc); proc_close($proc); } rmrf($tmp); });
for ($i = 0; $i < 50; $i++) { if (@fsockopen('127.0.0.1', $port)) break; usleep(100000); }

/** @return array{code:int,body:string,json:?array,headers:list<string>,ms:int} */
function http(string $method, string $path, ?string $tok = null, ?array $body = null, array $hdr = []): array {
    global $port;
    $h = ['Content-Type: application/json'] + $hdr;
    if ($tok !== null) { $h[] = 'X-ElvadoPress-Token: ' . $tok; }
    $ctx = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $h), 'content' => $body === null ? '' : json_encode($body), 'ignore_errors' => true, 'timeout' => 60]]);
    $t0 = microtime(true);
    $b = (string)@file_get_contents("http://127.0.0.1:$port$path", false, $ctx);
    $code = 0; foreach ($http_response_header ?? [] as $l) { if (preg_match('#^HTTP/\S+ (\d+)#', $l, $m)) { $code = (int)$m[1]; } }
    return ['code' => $code, 'body' => $b, 'json' => json_decode($b, true) ?: null, 'headers' => $http_response_header ?? [], 'ms' => (int)round((microtime(true) - $t0) * 1000)];
}
function login(string $u, string $p): string { $r = http('POST', '/cms/api.php?action=login', null, ['username' => $u, 'password' => $p]); return (string)($r['json']['token'] ?? ''); }
$admin = login('chef', 'Test-Passwort-123!'); $autor = login('anna', 'Test-Passwort-456!');
t('Anmeldung: Administrator und Autor erhalten Sitzungen', str_starts_with($admin, 'local_') && str_starts_with($autor, 'local_') && $admin !== $autor);
t('Falsches Passwort wird abgelehnt', http('POST', '/cms/api.php?action=login', null, ['username' => 'chef', 'password' => 'falsch'])['code'] === 401);

$ENG = '/cms/engine-api.php?action='; $CMP = '/cms/components-api.php?action=';
$adminOnlyGet = ['engine_prepare', 'engine_analyze', 'user_list', 'ext_list', 'ext_search', 'nav_import', 'widgets_overview', 'migration_runs', 'migration_report'];
$adminOnlyPost = ['engine_core', 'engine_db_test', 'engine_db_install', 'engine_mode', 'engine_remove', 'ext_install', 'ext_upload', 'ext_activate', 'ext_deactivate', 'ext_delete', 'ext_safe', 'user_sync', 'nav_create', 'nav_save', 'nav_delete', 'nav_assign', 'widgets_add', 'widgets_update', 'widgets_move', 'widgets_delete', 'migration_plan', 'migration_run', 'migration_rollback'];
$open = array_merge(['engine_status', 'content_list', 'content_get', 'term_list', 'media_list', 'nav_list', 'blocks_registry'], $adminOnlyGet, $adminOnlyPost);

// ───────── 1) ohne Anmeldung: nichts ─────────
$leak = [];
foreach ($open as $a) { $r = http(in_array($a, $adminOnlyPost, true) ? 'POST' : 'GET', $ENG . $a, null, in_array($a, $adminOnlyPost, true) ? [] : null); if ($r['code'] !== 401) { $leak[] = "$a:{$r['code']}"; } }
t('Engine-API ohne Anmeldung: überall 401', $leak === [], json_encode($leak));
$leak = [];
foreach (['components_catalog', 'layout_get', 'layout_revisions', 'layout_preview'] as $a) { if (http('GET', $CMP . $a)['code'] !== 401) { $leak[] = $a; } }
foreach (['layout_save_draft', 'layout_publish', 'layout_discard', 'layout_rollback', 'layout_render'] as $a) { if (http('POST', $CMP . $a, null, [])['code'] !== 401) { $leak[] = $a; } }
t('Komponenten-/Layout-API ohne Anmeldung: überall 401', $leak === [], json_encode($leak));
t('Gefälschte/abgelaufene Sitzung: 401', http('GET', $ENG . 'engine_status', 'local_' . str_repeat('ab', 24))['code'] === 401 && http('GET', $ENG . 'engine_status', 'irgendwas')['code'] === 401);
$r = http('GET', $ENG . 'engine_status', null, null, ['Cookie: elvadopress_session_token=' . $admin . '; token=' . $admin . '; PHPSESSID=x']);
t('Nur ein Cookie reicht nie (kein Cookie-Login ⇒ kein CSRF)', $r['code'] === 401);
t('SQL-/Pfad-Zeichen im Token: 401 statt Fehler', http('GET', $ENG . 'engine_status', "local_' OR 1=1 --")['code'] === 401 && http('GET', $ENG . 'engine_status', "../../etc/passwd")['code'] === 401);

// ───────── 2) Autor: nur nach Rechten ─────────
$wrong = [];
foreach ($adminOnlyGet as $a) { $c = http('GET', $ENG . $a, $autor)['code']; if ($c !== 403) { $wrong[] = "$a:$c"; } }
foreach ($adminOnlyPost as $a) { $c = http('POST', $ENG . $a, $autor, [])['code']; if ($c !== 403) { $wrong[] = "$a:$c"; } }
t('Autor: alle Verwaltungs-Aktionen der Engine ⇒ 403', $wrong === [], json_encode($wrong));
t('Autor: Engine-Status/Verwaltung nicht einsehbar', http('GET', $ENG . 'engine_status', $autor)['code'] === 403);
$c = http('GET', $CMP . 'layout_preview&scope=site:x', $autor)['code'];
t('Autor: Vorschau-Schlüssel für Bereiche nur Administratoren', $c === 403, (string)$c);
t('Autor: Layout der Startseite/Website nicht speicherbar', http('POST', $CMP . 'layout_save_draft&scope=home', $autor, ['layout' => []])['code'] === 403 && http('POST', $CMP . 'layout_publish&scope=site:portal', $autor, [])['code'] === 403);
t('Autor: eigenes Seitenlayout speicherbar', in_array(http('POST', $CMP . 'layout_save_draft&scope=page:mein-test', $autor, ['layout' => []])['code'], [200], true));
t('Administrator darf Layouts speichern/veröffentlichen', http('POST', $CMP . 'layout_save_draft&scope=home', $admin, ['layout' => []])['code'] === 200 && http('POST', $CMP . 'layout_publish&scope=home', $admin, [])['code'] === 200);


// ───────── 2b) Stabile REST-API (cms/rest.php) ─────────
$R = '/cms/rest.php';
$leak = [];
foreach (['/', '/pages', '/posts', '/posts/1', '/categories', '/tags', '/media', '/navigation', '/components', '/layouts/home', '/status'] as $rt) { if (http('GET', $R . $rt)['code'] !== 401) { $leak[] = $rt; } }
t('REST ohne Anmeldung: überall 401', $leak === [], json_encode($leak));
$bearer = function (string $method, string $path, string $tok, ?array $body = null) { global $port; $ctx = stream_context_create(['http' => ['method' => $method, 'header' => "Authorization: Bearer $tok\r\nContent-Type: application/json", 'content' => $body === null ? '' : json_encode($body), 'ignore_errors' => true, 'timeout' => 60]]); $b = (string)@file_get_contents("http://127.0.0.1:$port$path", false, $ctx); $c = 0; foreach ($http_response_header ?? [] as $l) { if (preg_match('#^HTTP/\S+ (\d+)#', $l, $m)) { $c = (int)$m[1]; } } return ['code' => $c, 'json' => json_decode($b, true), 'body' => $b, 'headers' => $http_response_header ?? []]; };
$d = $bearer('GET', $R . '/', $admin);
t('REST: Anmeldung per „Authorization: Bearer“, Wurzel nennt Version und Routen', $d['code'] === 200 && ($d['json']['data']['version'] ?? 0) === 1 && in_array('pages', $d['json']['data']['routes'] ?? [], true) && str_contains(implode("\n", $d['headers']), 'X-ElvadoPress-API: 1'), $d['body']);
t('REST: Cookie allein reicht nie', http('GET', $R . '/posts', null, null, ['Cookie: elvadopress_session_token=' . $admin])['code'] === 401);
$p = $bearer('GET', $R . '/posts', $admin);
t('REST: Beiträge lesen (native Daten), mit Seitenangaben', $p['code'] === 200 && ($p['json']['meta']['total'] ?? 0) === 1 && ($p['json']['data'][0]['title'] ?? '') === 'Hallo Welt' && ($p['json']['meta']['source'] ?? '') === 'native' && isset($p['json']['meta']['pages']), $p['body']);
t('REST: einzelner Beitrag, unbekannter 404, Seite und Medien lesbar', $bearer('GET', $R . '/posts/1', $admin)['code'] === 200 && $bearer('GET', $R . '/posts/999', $admin)['code'] === 404 && $bearer('GET', $R . '/pages', $admin)['code'] === 200 && $bearer('GET', $R . '/media', $admin)['code'] === 200 && $bearer('GET', $R . '/categories', $admin)['code'] === 200 && $bearer('GET', $R . '/navigation', $admin)['code'] === 200);
t('REST: Komponenten und Layouts', ($c = $bearer('GET', $R . '/components', $admin))['code'] === 200 && count($c['json']['data']['components'] ?? []) > 5 && $bearer('GET', $R . '/layouts/home', $admin)['code'] === 200 && in_array($bearer('GET', $R . '/layouts/' . rawurlencode("x';y"), $admin)['code'], [404, 422], true) && $bearer('GET', $R . '/layouts/page:ok', $admin)['code'] === 200 && $bearer('GET', $R . '/layouts/foo', $admin)['code'] === 422);
t('REST: Native Daten sind schreibgeschützt (409), Autoren dürfen nichts Fremdes', $bearer('POST', $R . '/posts', $admin, ['title' => 'x'])['code'] === 409 && $bearer('DELETE', $R . '/posts/1', $admin)['code'] === 409 && $bearer('GET', $R . '/status', $autor)['code'] === 403 && $bearer('GET', $R . '/status', $admin)['code'] === 200);
t('REST: Routen- und Eingabeprüfung (404/405/400/422)', $bearer('GET', $R . '/gibtsnicht', $admin)['code'] === 404 && $bearer('GET', $R . '/posts/a/b', $admin)['code'] === 404 && $bearer('GET', $R . '/posts/' . rawurlencode('../x'), $admin)['code'] === 404 && $bearer('POST', $R . '/components', $admin, [])['code'] === 405 && $bearer('GET', $R . '/posts?source=evil', $admin)['code'] === 400 && $bearer('GET', $R . '/posts?status=evil', $admin)['code'] === 422 && $bearer('GET', $R . '/posts?source=wordpress', $admin)['code'] === 409);
t('REST: Fehlerantworten ohne PHP-Meldungen oder Pfade', !str_contains($bearer('GET', $R . '/posts?status=evil', $admin)['body'], '.php'));

// ───────── 3) Administrator: Verhalten, Methoden, Eingaben ─────────
$st = http('GET', $ENG . 'engine_status', $admin);
t('Administrator: Status 200 mit Betriebsart „aus“ (Standard)', $st['code'] === 200 && ($st['json']['engine']['mode'] ?? '') === 'off', $st['body']);
$wrong = [];
foreach (['migration_plan', 'migration_run', 'migration_rollback', 'ext_install', 'ext_activate', 'user_sync', 'engine_db_install', 'engine_remove', 'widgets_add'] as $a) { $c = http('GET', $ENG . $a, $admin)['code']; if (!in_array($c, [405, 409, 422, 400], true)) { $wrong[] = "$a:$c"; } }
t('Schreibende Aktionen verlangen POST (GET wird nicht ausgeführt)', $wrong === [], json_encode($wrong));
t('Migration startet nicht ohne Bestätigung', http('POST', $ENG . 'migration_run', $admin, [])['code'] === 400 && http('POST', $ENG . 'migration_run', $admin, ['confirm' => 'ja'])['code'] === 400);
t('Rückbau ohne Bestätigung abgelehnt', http('POST', $ENG . 'migration_rollback', $admin, ['run' => 'x'])['code'] === 400);
t('Migration/Rückbau ohne aktive Engine: 409', http('POST', $ENG . 'migration_run', $admin, ['confirm' => 'MIGRIEREN'])['code'] === 409 && http('POST', $ENG . 'migration_rollback', $admin, ['run' => '20260101-000000-abcdef', 'confirm' => true])['code'] === 409);
$r = http('POST', $ENG . 'migration_plan', $admin, []);
t('Trockenlauf liefert Bericht, blockiert ohne aktive Engine, schreibt nur den Bericht', $r['code'] === 200 && ($r['json']['report']['dry_run'] ?? false) === true && ($r['json']['report']['verdict'] ?? '') === 'blocked' && ($r['json']['report']['wrote_anything'] ?? true) === false);
$rep = glob("$cms/data/.wp-engine/migration/report-*.json") ?: [];
t('Bericht liegt geschützt (0600) im Zustandsordner', count($rep) === 1 && (fileperms($rep[0]) & 0777) === 0600 && (fileperms("$cms/data/.wp-engine") & 0777) === 0700, $rep ? decoct(fileperms($rep[0]) & 0777) : 'kein Bericht');
t('Systemstatus/Updates: nur Administratoren, Status ohne WordPress, ohne Geheimnisse', http('GET', $ENG . 'system_status', $autor)['code'] === 403 && http('GET', $ENG . 'updates_overview', $autor)['code'] === 403 && http('POST', $ENG . 'updates_check', $autor, [])['code'] === 403 && http('GET', $ENG . 'system_status', null)['code'] === 401 && ($ss = http('GET', $ENG . 'system_status', $admin))['code'] === 200 && ($ss['json']['system']['summary']['fail'] ?? 1) <= 1 && http('GET', $ENG . 'updates_overview', $admin)['code'] === 200 && http('GET', $ENG . 'updates_check', $admin)['code'] === 405);
$bad = [];
foreach (['content_save' => [['type' => '../x', 'item' => ['title' => 'x']], 'POST'], 'content_get&type=gibtsnicht&id=1' => [null, 'GET'], 'nav_get&id=' . rawurlencode('../../etc') => [null, 'GET'], 'term_list&taxonomy=evil' => [null, 'GET']] as $a => [$b, $m]) {
    $r = http($m, $ENG . $a, $admin, $b); if ($r['code'] < 400 || $r['code'] >= 500 || str_contains($r['body'], 'Stack trace') || str_contains($r['body'], '.php on line')) { $bad[] = "$a:{$r['code']}"; }
}
t('Ungültige Eingaben: 4xx mit Meldung, kein Absturz, keine Pfade/Stack-Traces', $bad === [], json_encode($bad));
t('Ungültiger Layout-Bereich wird abgelehnt', in_array(http('GET', $CMP . 'layout_get&scope=' . rawurlencode("x';DROP"), $admin)['code'], [400, 422], true) && in_array(http('GET', $CMP . 'layout_get&scope=' . rawurlencode('../../site'), $admin)['code'], [400, 422], true));
t('Unbekannte Aktion: 404 (kein Absturz)', http('GET', $ENG . 'gibt_es_nicht', $admin)['code'] === 404);
$r = http('POST', $ENG . 'content_save', $admin, null, []); $r2 = (string)@file_get_contents("http://127.0.0.1:$port{$ENG}content_list", false, stream_context_create(['http' => ['method' => 'POST', 'header' => "X-ElvadoPress-Token: $admin\r\nContent-Type: application/json", 'content' => '{kaputt', 'ignore_errors' => true]]));
t('Kaputtes JSON: keine PHP-Fehlermeldung nach außen', !str_contains($r2, 'Warning') && !str_contains($r2, 'Fatal') && !str_contains($r2, $tmp));
t('Antworten sind JSON', str_contains(implode("\n", $st['headers']), 'application/json'));

// ───────── 4) Geheimnisse ─────────
$secret = 'Geheim-DB-Passwort-9xQ!';
@mkdir("$cms/data/.wp-engine", 0700, true);
file_put_contents("$cms/data/.wp-engine/db.json", json_encode(['host' => 'localhost', 'name' => 'x', 'user' => 'u', 'pass' => $secret, 'prefix' => 'wp_']));
chmod("$cms/data/.wp-engine/db.json", 0600);
$bodies = '';
foreach (['engine_status', 'engine_prepare', 'migration_runs', 'migration_report', 'user_list'] as $a) { $bodies .= http('GET', $ENG . $a, $admin)['body']; }
$bodies .= http('POST', $ENG . 'migration_plan', $admin, [])['body'];
t('Datenbank-Passwort steht in keiner API-Antwort', !str_contains($bodies, $secret));
$logs = ''; foreach (glob("$cms/data/.wp-engine/*.txt") ?: [] as $f) { $logs .= file_get_contents($f); } foreach (glob("$cms/data/*.json") ?: [] as $f) { $logs .= file_get_contents($f); }
t('…und in keinem Protokoll', !str_contains($logs, $secret) && !str_contains((string)@file_get_contents("$tmp/server.err"), $secret));
t('Passwort-Hashes verlassen die Benutzerliste nicht', !str_contains(http('GET', $ENG . 'user_list&source=native', $admin)['body'], 'password_hash') && !str_contains(http('GET', '/cms/api.php?action=users_list', $admin)['body'], 'password_hash'));
$wpd = "$cms/data/.wp-engine"; $bad = [];
foreach (glob("$wpd/*.json") ?: [] as $f) { if ((fileperms($f) & 0077) !== 0) { $bad[] = basename($f); } }
t('Zustandsdateien der Engine nicht für andere lesbar', $bad === [], json_encode($bad));
t('Zustandsordner geschützt (.htaccess/Rechte)', (fileperms($wpd) & 0077) === 0 || is_file("$wpd/.htaccess"));

// ───────── 5) Performance: WordPress nur starten, wenn nötig ─────────
@unlink($log);
$probeUris = [
    ['/cms/engine-api.php?action=engine_status', $admin], ['/cms/engine-api.php?action=content_list&source=native&type=post', $admin], ['/cms/engine-api.php?action=media_list&source=native', $admin],
    ['/cms/engine-api.php?action=nav_list&source=native', $admin], ['/cms/engine-api.php?action=migration_report', $admin], ['/cms/components-api.php?action=components_catalog', $admin],
    ['/cms/components-api.php?action=layout_get&scope=home', $admin], ['/cms/api.php?action=news_list', $admin], ['/cms/index.php', null],
];
$times = [];
foreach ($probeUris as [$u, $tok]) { $r = http('GET', $u, $tok); $r2 = http('GET', $u, $tok); $times[$u] = min($r['ms'], $r2['ms']); }   // zweimal, der bessere Wert zählt (der erste Aufruf wärmt Dateisystem und Zwischenspeicher auf – auf geteilten CI-Rechnern schwankt er stark)
$rows = array_filter(array_map(fn($l) => json_decode($l, true), file($log, FILE_IGNORE_NEW_LINES) ?: []));
$boot = array_filter($rows, fn($x) => $x['abspath'] || $x['wp'] > 0);
t('Ohne aktive Engine startet keine dieser Anfragen WordPress', $boot === [] && count($rows) >= count($probeUris), json_encode([count($rows), count($probeUris), array_column($rows, 'uri')]));
$slow = array_filter($times, fn($ms) => $ms > 2500);
t('Antwortzeiten der Verwaltungs-API (ohne WordPress) unter 2,5 s', $slow === [], json_encode($slow));
$ab = http('GET', $ENG . 'engine_status', $admin)['ms'];
t('Statusabfrage der Engine schnell (Zwischenspeicher/leichte Prüfung)', $ab < 800, "{$ab} ms");
t('Kein Aufruf von WordPress aus der öffentlichen Auslieferung (Quelltext)', !preg_match('/wp-engine-boot|Bridge::/', (string)file_get_contents(__DIR__ . '/../cms/wp-front.php')) && substr_count((string)file_get_contents(__DIR__ . '/../cms/lib/wpengine.php'), 'wp-engine-boot') <= 1);

// ───────── 6) Mit echtem WordPress (optional) ─────────
$zip = (string)getenv('WPE_TEST_ZIP'); $dbs = (string)getenv('WPE_TEST_DB');
if ($zip === '' || $dbs === '' || !is_file($zip)) {
    echo "Hinweis: Teil mit echtem WordPress übersprungen (WPE_TEST_ZIP/WPE_TEST_DB nicht gesetzt).\n";
} else {
    require_once __DIR__ . '/../cms/src/autoload.php';
    [$h, $name, $u, $pw] = array_pad(explode('|', $dbs), 4, '');
    $z = new ZipArchive(); $z->open($zip);
    preg_match('/\$wp_version\s*=\s*\'([^\']+)\'/', (string)$z->getFromName('wordpress/wp-includes/version.php'), $m); $z->close();
    @mkdir("$cms/data", 0755, true); @mkdir("$cms/wp-content", 0755, true);
    $eng = new Elvado\Wp\Engine($cms, "$cms/data");
    $inst = (new Elvado\Wp\CoreInstaller($eng))->install($zip, $m[1], sha1_file($zip));
    $dbc = new Elvado\Wp\DbConfig($eng); $cfg = ['host' => $h, 'name' => $name, 'user' => $u, 'pass' => $pw, 'prefix' => 'wptest_'];
    $tr = $dbc->test($cfg);
    t('Echter Core eingespielt, Datenbank leer und erreichbar', $inst['ok'] && $tr['ok'], ($inst['message'] ?? '') . ($tr['message'] ?? ''));
    if ($inst['ok'] && $tr['ok']) {
        $dbc->save($cfg); $eng->save(['db' => ['ready' => true]]);
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --child ' . escapeshellarg($cms) . ' 2>&1', $o, $rc);
        t('WordPress-Tabellen eingerichtet', $rc === 0, implode("\n", $o));
        $eng->save(['mode' => 'active']);
        @unlink($log);
        $un = [http('GET', $ENG . 'content_list', null), http('POST', $ENG . 'migration_plan', null, []), http('GET', $ENG . 'ext_list', $autor), http('GET', $CMP . 'components_catalog', null), http('GET', $ENG . 'content_list', 'local_' . str_repeat('cd', 24))];
        $rows = array_filter(array_map(fn($l) => json_decode($l, true), file($log, FILE_IGNORE_NEW_LINES) ?: []));
        t('Aktive Engine: unberechtigte Anfragen (401/403) starten WordPress nicht', array_column($un, 'code') === [401, 401, 403, 401, 401] && array_filter($rows, fn($x) => $x['abspath'] || $x['wp'] > 0) === [], json_encode([array_column($un, 'code'), $rows]));
        @unlink($log);
        $nat = http('GET', $ENG . 'content_list&source=native&type=post', $admin); $stat = http('GET', $ENG . 'engine_status', $admin); $cat = http('GET', $CMP . 'components_catalog', $admin);
        $rows = array_filter(array_map(fn($l) => json_decode($l, true), file($log, FILE_IGNORE_NEW_LINES) ?: []));
        t('Aktive Engine: Status, native Quelle und Komponenten-Katalog starten WordPress nicht', $nat['code'] === 200 && $stat['code'] === 200 && $cat['code'] === 200 && array_filter($rows, fn($x) => $x['abspath'] || $x['wp'] > 0) === [], json_encode($rows));
        @unlink($log);
        $wp = http('GET', $ENG . 'content_list&type=post', $admin);
        $rows = array_values(array_filter(array_map(fn($l) => json_decode($l, true), file($log, FILE_IGNORE_NEW_LINES) ?: [])));
        t('WordPress startet erst, wenn die Anfrage es braucht (und liefert Daten)', $wp['code'] === 200 && ($wp['json']['status'] ?? '') === 'ok' && ($rows[0]['abspath'] ?? false) === true, $wp['body']);
        $again = http('GET', $ENG . 'content_list&type=post', $admin);
        t('Start von WordPress dauert vertretbar (< 4 s kalt, < 3 s erneut)', $wp['ms'] < 4000 && $again['ms'] < 3000, "{$wp['ms']} / {$again['ms']} ms");
        $w = $bearer('POST', $R . '/posts', $admin, ['title' => 'REST-Beitrag', 'content' => '<p>Hallo</p>', 'status' => 'published', 'categories' => ['Radio']]);
        $wid = (string)($w['json']['data']['id'] ?? '');
        t('REST mit WordPress: Beitrag anlegen (201), lesen, ändern, löschen', $w['code'] === 201 && $wid !== '' && ($bearer('GET', $R . '/posts/' . $wid, $admin)['json']['data']['title'] ?? '') === 'REST-Beitrag' && ($bearer('PATCH', $R . '/posts/' . $wid, $admin, ['title' => 'Geändert'])['json']['data']['title'] ?? '') === 'Geändert' && ($bearer('GET', $R . '/posts', $admin)['json']['meta']['source'] ?? '') === 'wordpress' && $bearer('DELETE', $R . '/posts/' . $wid . '?force=1', $admin)['code'] === 200 && $bearer('GET', $R . '/posts/' . $wid, $admin)['code'] === 404, $w['body']);
        t('REST mit WordPress: Autor schreibt nur nach Rechten (Seiten nicht)', $bearer('POST', $R . '/pages', $autor, ['title' => 'Nein'])['code'] === 403, '');
        t('REST mit WordPress: Medien und Navigation lesbar', $bearer('GET', $R . '/media', $admin)['code'] === 200 && $bearer('GET', $R . '/navigation', $admin)['code'] === 200 && $bearer('GET', $R . '/categories', $admin)['code'] === 200);
        @unlink($log);
        $bearer('GET', $R . '/components', $admin); $bearer('GET', $R . '/posts?source=native', $admin); $bearer('GET', $R . '/posts', null ?? 'x');
        $rows = array_values(array_filter(array_map(fn($l) => json_decode($l, true), file($log, FILE_IGNORE_NEW_LINES) ?: [])));
        t('REST: Komponenten, native Quelle und unberechtigte Anfragen starten WordPress nicht', count($rows) === 3 && array_filter($rows, fn($x) => $x['abspath'] || $x['wp'] > 0) === [], json_encode($rows));
        $su = http('POST', $ENG . 'updates_check', $admin, []);
        t('Update-Suche mit aktiver Engine: Antwort mit allen Arten (Netz kann fehlen, dann Hinweis statt Fehler)', ($su['json']['status'] ?? '') === 'ok' && count($su['json']['updates'] ?? []) === 6, $su['body']);
        $pl = http('POST', $ENG . 'migration_plan', $admin, []);
        t('Trockenlauf mit aktiver Engine prüft das Ziel und verändert WordPress nicht', ($pl['json']['report']['engine']['target_checked'] ?? false) === true, $pl['body']);
        $mysqli = @new mysqli($h === 'localhost' ? '127.0.0.1' : explode(':', $h)[0], $u, $pw, $name, (int)(explode(':', $h)[1] ?? 3306));
        if (!$mysqli->connect_errno) { $res = $mysqli->query("SHOW TABLES LIKE 'wptest\\_%'"); while ($res && ($row = $res->fetch_row())) { $mysqli->query('DROP TABLE `' . $mysqli->real_escape_string($row[0]) . '`'); } }
    }
}

echo $fail ? "$fail von $n Prüfungen fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n";
exit($fail ? 1 : 0);

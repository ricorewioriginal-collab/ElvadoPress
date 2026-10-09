<?php
// Websites (Multisite), Auslieferung: Domain → eigener Inhalt, eigenes Theme-Flag, Medien-Adressen; Hauptwebsite unverändert.
// Echter PHP-Server (php -S) mit Kopie von index.php und cms/. Aufruf: php scripts/test-sites-front.php
declare(strict_types=1);
$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
function rmrf(string $d): void { if (!is_dir($d)) return; foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') continue; $p = "$d/$f"; is_dir($p) && !is_link($p) ? rmrf($p) : @unlink($p); } @rmdir($d); }
function cp(string $from, string $to, array $skip): void { @mkdir($to, 0755, true); foreach (scandir($from) as $f) { if ($f === '.' || $f === '..' || in_array($f, $skip, true)) continue; is_dir("$from/$f") ? cp("$from/$f", "$to/$f", []) : copy("$from/$f", "$to/$f"); } }
if (is_dir(__DIR__ . '/../cms/packs')) { echo "übersprungen: Paket-Installation (eigener Einstieg index.php)\n"; exit(0); }   // z. B. RicoReWi-Portal: Auslieferung über das Portal, nicht über wp-front.php
if (!function_exists('proc_open')) { echo "übersprungen: proc_open nicht verfügbar\n"; exit(0); }

$tmp = sys_get_temp_dir() . '/sites-front-' . bin2hex(random_bytes(4));
$site = "$tmp/site"; $cms = "$site/cms";
mkdir($site, 0755, true);
copy(__DIR__ . '/../index.php', "$site/index.php");
cp(__DIR__ . '/../cms', $cms, ['data', 'wp-engine', 'uploads', 'sites', 'media', 'generated']);
foreach (['data', 'media', 'generated'] as $d) { mkdir("$cms/$d", 0755, true); }
mkdir("$cms/data/.wp", 0775, true);
file_put_contents("$cms/data/install.lock", 'x');
file_put_contents("$cms/data/.wp/front-on", 'x');
file_put_contents("$cms/data/.wp/options.json", json_encode(['stylesheet' => ['v' => serialize('rrw-classic')], 'template' => ['v' => serialize('rrw-classic')]]));
$news = fn(string $t, string $body) => [['id' => 1, 'slug' => 'beitrag', 'title' => $t, 'category' => 'x', 'tags' => '', 'excerpt' => '', 'body_html' => $body, 'status' => 'published', 'published_at' => '2024-01-01 10:00:00', 'created_at' => '2024-01-01 10:00:00', 'updated_at' => '2024-01-01 10:00:00']];
file_put_contents("$cms/data/site.json", json_encode(['portal' => ['site_name' => 'Hauptseite']], JSON_UNESCAPED_UNICODE));
file_put_contents("$cms/data/news.json", json_encode($news('Haupt-Beitrag', '<p><img src="/cms/media/library/a.png"></p>')));
define('RRW_SITES_BASE', $cms);
require __DIR__ . '/../cms/lib/sites.php';
$res = rrw_site_create(['name' => 'Zweite', 'domains' => ['zweite.test'], 'enabled' => true, 'copy_from' => 'main']);
$z = "$cms/sites/zweite/data";
t('Kopie nimmt Theme-Aktivierung und -Optionen mit', is_file("$z/.wp/front-on") && is_file("$z/.wp/options.json"));
file_put_contents("$z/site.json", json_encode(['portal' => ['site_name' => 'Zweite Seite']], JSON_UNESCAPED_UNICODE));
file_put_contents("$z/news.json", json_encode($news('Zweiter-Beitrag', '<p><img src="/cms/media/library/b.png"></p>')));
$leer = rrw_site_create(['name' => 'Leer', 'domains' => ['leer.test'], 'enabled' => true]);
t('Leere Website bekommt das Theme der Hauptwebsite', is_file("$cms/sites/leer/data/.wp/front-on"));

$sock = stream_socket_server('tcp://127.0.0.1:0', $en, $es); $port = (int)explode(':', (string)stream_socket_get_name($sock, false))[1]; fclose($sock);
$proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $site], [1 => ['file', "$tmp/server.out", 'w'], 2 => ['file', "$tmp/server.err", 'w']], $pi);
register_shutdown_function(function () use ($proc, $tmp) { if (is_resource($proc)) { proc_terminate($proc); proc_close($proc); } rmrf($tmp); });
for ($i = 0; $i < 50; $i++) { if (@fsockopen('127.0.0.1', $port)) break; usleep(100000); }
function get(string $host, string $path = '/'): array {
    global $port;
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'header' => "Host: $host\r\n", 'ignore_errors' => true, 'timeout' => 20]]);
    $b = (string)@file_get_contents("http://127.0.0.1:$port$path", false, $ctx);
    return ['code' => (int)preg_replace('/^HTTP\/\S+ (\d+).*/', '$1', (string)($http_response_header[0] ?? 'HTTP/1.1 0')), 'body' => $b];
}
$m = get('haupt.test');
$mb = get('haupt.test', '/beitrag/');
t('Hauptwebsite: Startseite mit eigenem Inhalt', $m['code'] === 200 && str_contains($m['body'], 'Haupt-Beitrag') && !str_contains($m['body'], 'Zweiter-Beitrag'), 'HTTP ' . $m['code']);
t('Hauptwebsite: Medien-Adressen unverändert', str_contains($mb['body'], '/cms/media/library/a.png') && !str_contains($mb['body'], '/cms/sites/'), 'HTTP ' . $mb['code']);
$z2 = get('zweite.test');
$zb = get('zweite.test', '/beitrag/');
t('Zweite Website: eigener Inhalt, nicht der der Hauptwebsite', $z2['code'] === 200 && str_contains($z2['body'], 'Zweiter-Beitrag') && !str_contains($z2['body'], 'Haupt-Beitrag'), 'HTTP ' . $z2['code']);
t('Zweite Website: Medien-Adressen zeigen auf eigene Medien', str_contains($zb['body'], '/cms/sites/zweite/media/library/b.png') && !str_contains($zb['body'], '"/cms/media/'), 'HTTP ' . $zb['code']);
$z3 = get('www.zweite.test', '/beitrag/');
t('Zweite Website (www): Beitragsseite', $z3['code'] === 200 && str_contains($z3['body'], 'Zweiter-Beitrag'), 'HTTP ' . $z3['code']);
$rs = get('zweite.test', '/cms/rss.php');
t('Feed der zweiten Website: eigener Beitrag', str_contains($rs['body'], 'Zweiter-Beitrag') && !str_contains($rs['body'], 'Haupt-Beitrag'), 'HTTP ' . $rs['code']);
$rm = get('haupt.test', '/cms/rss.php');
t('Feed der Hauptwebsite unverändert', str_contains($rm['body'], 'Haupt-Beitrag') && !str_contains($rm['body'], 'Zweiter-Beitrag'));
$l = get('leer.test');
t('Leere Website: wird ausgeliefert (kein Absturz), ohne Inhalte der Hauptwebsite', $l['code'] === 200 && !str_contains($l['body'], 'Haupt-Beitrag'), 'HTTP ' . $l['code']);
rrw_site_update('zweite', ['enabled' => false]);
$d = get('zweite.test');
t('Deaktivierte Website: Domain fällt auf die Hauptwebsite zurück', str_contains($d['body'], 'Haupt-Beitrag'));
$serverErr = (string)@file_get_contents("$tmp/server.err");
$serverErr = preg_replace('/^.*JIT is incompatible with third party extensions.*$/m', '', $serverErr);   // Umgebungsmeldung des CI-Runners (Erweiterung überschreibt zend_execute_ex), kein CMS-Fehler
preg_match_all('/^.*(Fatal|Warning|Parse error).*$/m', $serverErr, $errLines);
t('Server-Fehlerprotokoll ohne PHP-Fehler', !$errLines[0], implode(' | ', array_slice($errLines[0], 0, 5)));
echo $fail ? "$fail von $n Prüfungen fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n";
exit($fail ? 1 : 0);

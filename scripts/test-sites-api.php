<?php
// Websites (Multisite) in der API – gegen einen echten PHP-Server (php -S) mit Kopie der Verwaltung:
// Domain → eigene Inhalte, Verwaltungs-Kopf „X-EP-Site“ nur mit Administrator-Sitzung, Hauptwebsite unverändert.
// Aufruf: php scripts/test-sites-api.php
declare(strict_types=1);
$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
function rmrf(string $d): void { if (!is_dir($d)) return; foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') continue; $p = "$d/$f"; is_dir($p) && !is_link($p) ? rmrf($p) : @unlink($p); } @rmdir($d); }
function cp(string $from, string $to, array $skip): void { @mkdir($to, 0755, true); foreach (scandir($from) as $f) { if ($f === '.' || $f === '..' || in_array($f, $skip, true)) continue; $a = "$from/$f"; is_dir($a) ? cp($a, "$to/$f", []) : copy($a, "$to/$f"); } }
if (!function_exists('proc_open')) { echo "übersprungen: proc_open nicht verfügbar\n"; exit(0); }

$tmp = sys_get_temp_dir() . '/sites-api-' . bin2hex(random_bytes(4));
$site = "$tmp/site"; $cms = "$site/cms";
mkdir($site, 0755, true);
cp(__DIR__ . '/../cms', $cms, ['data', 'wp-engine', 'uploads', 'sites', 'media', 'generated']);
foreach (['data', 'media', 'generated'] as $d) { mkdir("$cms/$d", 0755, true); }
file_put_contents("$cms/data/local-auth.local.php", '<?php return ' . var_export(['users' => [
    ['username' => 'chef', 'password_hash' => password_hash('Test-Passwort-123!', PASSWORD_DEFAULT), 'role' => 'admin', 'display_name' => 'Chef', 'created_at' => date(DATE_ATOM)],
    ['username' => 'anna', 'password_hash' => password_hash('Test-Passwort-456!', PASSWORD_DEFAULT), 'role' => 'autor', 'display_name' => 'Anna', 'created_at' => date(DATE_ATOM)],
]], true) . ';');
// Hauptwebsite mit Beispielinhalten; zweite Website als Kopie, danach eigene Inhalte
file_put_contents("$cms/data/site.json", json_encode(['portal' => ['site_name' => 'Hauptseite', 'news_title' => 'Haupt-News']], JSON_UNESCAPED_UNICODE));
file_put_contents("$cms/data/news.json", json_encode([['id' => 1, 'slug' => 'haupt', 'title' => 'Haupt-Beitrag', 'category' => 'x', 'body_html' => '<p>x</p>', 'status' => 'published', 'published_at' => '2026-01-01T00:00:00+00:00']]));
define('RRW_SITES_BASE', $cms);
require __DIR__ . '/../cms/lib/sites.php';
rrw_site_create(['name' => 'Zweite', 'domains' => ['zweite.test'], 'enabled' => true, 'copy_from' => 'main']);
$z = "$cms/sites/zweite/data";
file_put_contents("$z/site.json", json_encode(['portal' => ['site_name' => 'Zweite Seite', 'news_title' => 'Zweite-News']], JSON_UNESCAPED_UNICODE));
file_put_contents("$z/news.json", '[]');

$sock = stream_socket_server('tcp://127.0.0.1:0', $en, $es); $port = (int)explode(':', (string)stream_socket_get_name($sock, false))[1]; fclose($sock);
$proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $site], [1 => ['file', "$tmp/server.out", 'w'], 2 => ['file', "$tmp/server.err", 'w']], $pi);
register_shutdown_function(function () use ($proc, $tmp) { if (is_resource($proc)) { proc_terminate($proc); proc_close($proc); } rmrf($tmp); });
for ($i = 0; $i < 50; $i++) { if (@fsockopen('127.0.0.1', $port)) break; usleep(100000); }
/** @return array{code:int,json:?array} */
function http(string $method, string $path, ?string $tok = null, ?array $body = null, array $hdr = []): array {
    global $port;
    $h = array_merge(['Content-Type: application/json'], $hdr);
    if ($tok !== null) { $h[] = 'X-ElvadoPress-Token: ' . $tok; }
    $ctx = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $h), 'content' => $body === null ? '' : json_encode($body), 'ignore_errors' => true, 'timeout' => 60]]);
    $b = (string)@file_get_contents("http://127.0.0.1:$port$path", false, $ctx);
    $code = 0; foreach ($http_response_header ?? [] as $l) { if (preg_match('#^HTTP/\S+ (\d+)#', $l, $m)) { $code = (int)$m[1]; } }
    return ['code' => $code, 'json' => json_decode($b, true) ?: null];
}
function name(array $r): string { return (string)($r['json']['config']['portal']['site_name'] ?? '?'); }
function login(string $u, string $p): string { return (string)(http('POST', '/cms/api.php?action=login', null, ['username' => $u, 'password' => $p])['json']['token'] ?? ''); }
$admin = login('chef', 'Test-Passwort-123!'); $autor = login('anna', 'Test-Passwort-456!');
t('Anmeldung: Administrator und Autor', str_starts_with($admin, 'local_') && str_starts_with($autor, 'local_'));

// 1) Auslieferung je Domain
$P = '/cms/api.php?action=public';
t('Hauptwebsite (unbekannte Domain/localhost): eigene Inhalte', name(http('GET', $P)) === 'Hauptseite');
t('Hauptwebsite: auch mit anderem Host', name(http('GET', $P, null, null, ['Host: haupt.example'])) === 'Hauptseite');
t('Zweite Website per Domain: eigene Inhalte', name(http('GET', $P, null, null, ['Host: zweite.test'])) === 'Zweite Seite');
t('Zweite Website mit www und Port', name(http('GET', $P, null, null, ['Host: www.zweite.test:8080'])) === 'Zweite Seite');

// 2) Verwaltungs-Kopf
t('Kopf ohne Anmeldung wird ignoriert', name(http('GET', $P, null, null, ['X-EP-Site: zweite'])) === 'Hauptseite');
t('Kopf mit Autor-Sitzung wird ignoriert', name(http('GET', $P, $autor, null, ['X-EP-Site: zweite'])) === 'Hauptseite');
t('Kopf mit Administrator-Sitzung wählt die Website', name(http('GET', $P, $admin, null, ['X-EP-Site: zweite'])) === 'Zweite Seite');
t('Kopf „main“ wählt die Hauptwebsite (auch auf der Domain der zweiten)', name(http('GET', $P, $admin, null, ['X-EP-Site: main', 'Host: zweite.test'])) === 'Hauptseite');
t('Unbekannte Kennung im Kopf wird ignoriert', name(http('GET', $P, $admin, null, ['X-EP-Site: gibt-es-nicht'])) === 'Hauptseite' && name(http('GET', $P, $admin, null, ['X-EP-Site: ../data'])) === 'Hauptseite');
t('Cookie allein wählt keine Website (kein CSRF)', name(http('GET', $P, null, null, ['Cookie: ep_site=zweite; X-EP-Site=zweite'])) === 'Hauptseite');

// 3) Schreiben landet in der gewählten Website
$mainBefore = file_get_contents("$cms/data/site.json");
$r = http('POST', '/cms/api.php?action=save', $admin, ['section' => 'portal', 'value' => ['site_name' => 'Zweite geändert', 'news_title' => 'Z-News']], ['X-EP-Site: zweite']);
t('Speichern mit Kopf: Antwort ok', ($r['json']['status'] ?? '') === 'ok', json_encode($r['json']));
t('Speichern mit Kopf: nur die zweite Website geändert, Hauptwebsite Byte-gleich', str_contains((string)file_get_contents("$z/site.json"), 'Zweite geändert') && file_get_contents("$cms/data/site.json") === $mainBefore);
t('Danach liefert die zweite Domain den neuen Stand', name(http('GET', $P, null, null, ['Host: zweite.test'])) === 'Zweite geändert');
$r = http('POST', '/cms/api.php?action=save', $admin, ['section' => 'portal', 'value' => ['site_name' => 'Haupt geändert', 'news_title' => 'H-News']]);
t('Speichern ohne Kopf: nur die Hauptwebsite geändert', ($r['json']['status'] ?? '') === 'ok' && str_contains((string)file_get_contents("$cms/data/site.json"), 'Haupt geändert') && str_contains((string)file_get_contents("$z/site.json"), 'Zweite geändert'));

// 4) News je Website
$N = '/cms/api.php?action=news_public';
$cnt = fn(array $r): int => count((array)($r['json']['articles'] ?? $r['json']['items'] ?? $r['json']['news'] ?? []));
t('News: Hauptwebsite hat ihren Beitrag, zweite Website keinen', $cnt(http('GET', $N)) === 1 && $cnt(http('GET', $N, null, null, ['Host: zweite.test'])) === 0, json_encode([http('GET', $N)['json'], http('GET', $N, null, null, ['Host: zweite.test'])['json']]));

// 4b) Verwaltung der Websites (nur Administratoren)
$L = http('GET', '/cms/api.php?action=sites_list', $admin);
t('sites_list: Administrator sieht Hauptwebsite und weitere Websites', ($L['json']['status'] ?? '') === 'ok' && count($L['json']['sites'] ?? []) === 1 && ($L['json']['main']['name'] ?? '') === 'Haupt geändert', json_encode($L['json']));
t('sites_list/create/update: Autor und ohne Anmeldung abgelehnt', http('GET', '/cms/api.php?action=sites_list', $autor)['code'] === 403 && http('POST', '/cms/api.php?action=sites_create', $autor, ['name' => 'X'])['code'] === 403 && http('POST', '/cms/api.php?action=sites_update', $autor, ['id' => 'zweite', 'enabled' => false])['code'] === 403 && http('GET', '/cms/api.php?action=sites_list')['code'] === 401);
$C = http('POST', '/cms/api.php?action=sites_create', $admin, ['name' => 'Dritte', 'domains' => ['dritte.test'], 'enabled' => true, 'copy_from' => 'zweite']);
t('sites_create: Kopie der zweiten Website mit eigener Domain', ($C['json']['status'] ?? '') === 'ok' && is_file("$cms/sites/dritte/data/site.json") && count($C['json']['sites'] ?? []) === 2, json_encode($C['json']));
t('Neue Website liefert per Domain den kopierten Stand', name(http('GET', $P, null, null, ['Host: dritte.test'])) === 'Zweite geändert');
t('sites_create: Domain-Konflikt und ungültige Domain → 400 mit Meldung', http('POST', '/cms/api.php?action=sites_create', $admin, ['name' => 'Vierte', 'domains' => ['www.dritte.test']])['code'] === 400 && http('POST', '/cms/api.php?action=sites_create', $admin, ['name' => 'Fünfte', 'domains' => ['a b']])['code'] === 400 && http('POST', '/cms/api.php?action=sites_create', $admin, ['name' => 'Dritte'])['code'] === 400);
$U = http('POST', '/cms/api.php?action=sites_update', $admin, ['id' => 'dritte', 'name' => 'Dritte Seite', 'domains' => ['dritte.test', 'neu.dritte.test']]);
t('sites_update: Name und Domains ändern', ($U['json']['status'] ?? '') === 'ok' && ($U['json']['site']['name'] ?? '') === 'Dritte Seite' && name(http('GET', $P, null, null, ['Host: neu.dritte.test'])) === 'Zweite geändert');
t('sites_update: Domain einer anderen Website → 400, unbekannte Website → 400', http('POST', '/cms/api.php?action=sites_update', $admin, ['id' => 'dritte', 'domains' => ['zweite.test']])['code'] === 400 && http('POST', '/cms/api.php?action=sites_update', $admin, ['id' => 'gibt-es-nicht', 'name' => 'x'])['code'] === 400);
$ui = (string)file_get_contents(__DIR__ . '/../cms/assets/cms-app.js') . (string)file_get_contents(__DIR__ . '/../cms/assets/sites-manager.js') . (string)file_get_contents(__DIR__ . '/../cms/assets/shell.js') . (string)file_get_contents(__DIR__ . '/../cms/views/sidebar.php') . (string)file_get_contents(__DIR__ . '/../cms/index.php');
t('Oberfläche: Website-Kopf in allen Anfragen, Umschalter, Bereich „Websites“, Seitenleiste', str_contains($ui, 'X-EP-Site') && str_contains($ui, 'epSiteBtn') && str_contains($ui, 'SitesManager?.load()') && str_contains($ui, 'panel-sites.php') && str_contains($ui, 'sites-manager.js'));
// 5) Deaktivieren: Domain fällt auf die Hauptwebsite zurück
$it = rrw_sites_registry(true); foreach ($it as $k => $x) { $it[$k]['enabled'] = false; } rrw_sites_save($it);
t('Deaktivierte Website: Domain zeigt die Hauptwebsite', name(http('GET', $P, null, null, ['Host: zweite.test'])) === 'Haupt geändert');
@unlink("$cms/data/sites.json");
t('Ohne Registry: alles wie bisher', name(http('GET', $P, null, null, ['Host: zweite.test'])) === 'Haupt geändert' && name(http('GET', $P, $admin, null, ['X-EP-Site: zweite'])) === 'Haupt geändert');
file_put_contents("$cms/data/sites.json", '{kaputt');
t('Beschädigte Registry: Hauptwebsite, kein Absturz', name(http('GET', $P, null, null, ['Host: zweite.test'])) === 'Haupt geändert');
echo $fail ? "$fail von $n fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n"; exit($fail ? 1 : 0);

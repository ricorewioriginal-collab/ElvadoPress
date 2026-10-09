<?php
// Themes entfernen: hochgeladene löschen, Themes aus dem Code ausblenden/zurückholen, aktives und Standard-Theme geschützt – gegen einen echten PHP-Server.
// Aufruf: php scripts/test-theme-remove.php
declare(strict_types=1);
$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
function rmrf(string $d): void { if (!is_dir($d)) return; foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') continue; $p = "$d/$f"; is_dir($p) && !is_link($p) ? rmrf($p) : @unlink($p); } @rmdir($d); }
function cp(string $from, string $to, array $skip): void { @mkdir($to, 0755, true); foreach (scandir($from) as $f) { if ($f === '.' || $f === '..' || in_array($f, $skip, true)) continue; is_dir("$from/$f") ? cp("$from/$f", "$to/$f", []) : copy("$from/$f", "$to/$f"); } }
if (!function_exists('proc_open')) { echo "übersprungen: proc_open nicht verfügbar\n"; exit(0); }
$tmp = sys_get_temp_dir() . '/theme-rm-' . bin2hex(random_bytes(4)); $site = "$tmp/site"; $cms = "$site/cms";
mkdir($site, 0755, true); cp(__DIR__ . '/../cms', $cms, ['data', 'wp-engine', 'uploads', 'sites', 'media', 'generated']);
foreach (['data', 'media', 'generated'] as $d) { mkdir("$cms/$d", 0755, true); }
file_put_contents("$cms/data/install.lock", 'x');
file_put_contents("$cms/data/local-auth.local.php", '<?php return ' . var_export(['users' => [['username' => 'chef', 'password_hash' => password_hash('Test-Passwort-123!', PASSWORD_DEFAULT), 'role' => 'admin', 'display_name' => 'Chef', 'created_at' => date(DATE_ATOM)]]], true) . ';');
foreach (['code-theme' => false, 'upload-theme' => true, 'aktiv-theme' => false] as $id => $up) {
    mkdir("$cms/themes/$id", 0755, true); file_put_contents("$cms/themes/$id/theme.json", json_encode(['id' => $id, 'name' => ucfirst($id)]));
    file_put_contents("$cms/themes/$id/theme.css", 'body{}'); mkdir("$cms/themes/$id/img", 0755, true); file_put_contents("$cms/themes/$id/img/a.png", 'x');
    if ($up) { file_put_contents("$cms/themes/$id/.uploaded", 'x'); }
}
file_put_contents("$cms/data/site.json", json_encode(['theme' => ['active' => 'aktiv-theme', 'variant' => 'default', 'settings' => []]]));
$sock = stream_socket_server('tcp://127.0.0.1:0', $en, $es); $port = (int)explode(':', (string)stream_socket_get_name($sock, false))[1]; fclose($sock);
$proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $site], [1 => ['file', "$tmp/s.out", 'w'], 2 => ['file', "$tmp/s.err", 'w']], $pi);
register_shutdown_function(function () use ($proc, $tmp) { if (is_resource($proc)) { proc_terminate($proc); proc_close($proc); } rmrf($tmp); });
for ($i = 0; $i < 50; $i++) { if (@fsockopen('127.0.0.1', $port)) break; usleep(100000); }
function api(string $action, ?array $body = null, string $tok = ''): array {
    global $port; $h = "Content-Type: application/json\r\n" . ($tok !== '' ? "X-ElvadoPress-Token: $tok\r\n" : '');
    $ctx = stream_context_create(['http' => ['method' => $body === null ? 'GET' : 'POST', 'header' => $h, 'content' => $body === null ? '' : json_encode($body), 'ignore_errors' => true, 'timeout' => 30]]);
    $j = json_decode((string)@file_get_contents("http://127.0.0.1:$port/cms/api.php?action=$action", false, $ctx), true); return is_array($j) ? $j : [];
}
$tok = (string)(api('login', ['username' => 'chef', 'password' => 'Test-Passwort-123!'])['token'] ?? '');
$ids = fn() => array_column(api('themes_list', null, $tok)['themes'] ?? [], 'id');
t('Ausgangslage: drei Themes', count(array_intersect($ids(), ['code-theme', 'upload-theme', 'aktiv-theme'])) === 3);
$r = api('theme_delete', ['id' => 'aktiv-theme'], $tok);
t('Aktives Theme lässt sich nicht entfernen', ($r['status'] ?? '') === 'error' && is_dir("$cms/themes/aktiv-theme"));
$r = api('theme_delete', ['id' => 'upload-theme'], $tok);
t('Hochgeladenes Theme wird samt Unterordnern gelöscht', ($r['status'] ?? '') === 'ok' && empty($r['hidden']) && !is_dir("$cms/themes/upload-theme") && !in_array('upload-theme', $ids(), true));
$r = api('theme_delete', ['id' => 'code-theme'], $tok);
t('Theme aus dem Code wird ausgeblendet, Dateien bleiben', ($r['status'] ?? '') === 'ok' && !empty($r['hidden']) && is_dir("$cms/themes/code-theme") && !in_array('code-theme', $ids(), true));
$l = api('themes_list', null, $tok);
t('Ausgeblendete Themes werden gelistet', array_column($l['hidden_themes'] ?? [], 'id') === ['code-theme']);
$r = api('theme_unhide', ['id' => 'code-theme'], $tok);
t('Zurückholen blendet es wieder ein', ($r['status'] ?? '') === 'ok' && in_array('code-theme', $ids(), true) && (api('themes_list', null, $tok)['hidden_themes'] ?? []) === []);
// WordPress-Themes (Theme-Laufzeit): Themes aus dem Code werden ausgeblendet, das Standard-Theme bleibt
$wl = fn() => array_column(api('wp_themes', null, $tok)['themes'] ?? [], 'slug');
$before = $wl();
t('WordPress-Themes werden gelistet (mitgelieferte vorhanden)', in_array('rrw-classic', $before, true) && in_array('elvado-band', $before, true), json_encode($before));
$r = api('wp_theme_delete', ['slug' => 'rrw-classic'], $tok);
t('WordPress: Standard-Theme lässt sich nicht entfernen', ($r['status'] ?? '') === 'error');
$r = api('wp_theme_delete', ['slug' => 'elvado-band'], $tok);
t('WordPress: mitgeliefertes Theme wird ausgeblendet (Dateien bleiben)', ($r['status'] ?? '') === 'ok' && !empty($r['hidden']) && !in_array('elvado-band', $wl(), true) && is_dir("$cms/themes/elvado-band"));
t('WordPress: Ausgeblendete werden gelistet', in_array('elvado-band', array_column(api('wp_themes', null, $tok)['hidden_themes'] ?? [], 'slug'), true));
api('wp_theme_unhide', ['slug' => 'elvado-band'], $tok);
t('WordPress: Zurückholen blendet es wieder ein', in_array('elvado-band', $wl(), true));
t('Unbekanntes Theme: 404', (api('theme_delete', ['id' => 'gibt-es-nicht'], $tok)['status'] ?? '') === 'error');
t('Ohne Anmeldung nichts', (api('theme_delete', ['id' => 'code-theme'])['status'] ?? '') === 'error' && is_dir("$cms/themes/code-theme"));
$ui = (string)file_get_contents(__DIR__ . '/../cms/assets/theme-manager.js');
t('Oberfläche (WordPress-Themes): Ausblenden und Zurückholen', str_contains((string)file_get_contents(__DIR__ . '/../cms/assets/wpthemes-manager.js'), 'wp_theme_unhide') && str_contains((string)file_get_contents(__DIR__ . '/../cms/views/panel-themes.php'), 'wtHidden'));
t('Oberfläche: Entfernen für alle außer aktivem/Standard-Theme, Ausgeblendete zurückholen', str_contains($ui, 't.active||t.default?') && str_contains($ui, 'unhide') && str_contains((string)file_get_contents(__DIR__ . '/../cms/views/panel-themes.php'), 'themeHidden'));
echo $fail ? "$fail von $n Prüfungen fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n";
exit($fail ? 1 : 0);

<?php
// Prüft Plugins/Themes der WordPress-Engine: ExtensionSource (wordpress.org, mit nachgestelltem Netzwerk), ExtensionInstaller (Paketprüfung), ExtensionService (Rechte, Wächter),
// Engine-Wächter/Absturzschutz und – mit WPE_TEST_ZIP/WPE_TEST_DB – echte Plugins/Themes in echtem WordPress einschließlich provozierter Abstürze und abgesichertem Modus.
// Aufruf: php scripts/test-wp-engine-extensions.php
declare(strict_types=1);
require __DIR__ . '/../cms/src/autoload.php';
use Elvado\Support\{Http, HttpResponse};
use Elvado\Wp\{Actor, Engine, ExtensionSource, ExtensionInstaller, ExtensionService, CoreInstaller, DbConfig, Bridge, PermissionException};
use Elvado\Wp\Adapter\{ExtensionAdapter, WordPressExtensionAdapter};

$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
function rmrf(string $d): void { if (!is_dir($d)) return; foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') continue; $p = "$d/$f"; is_dir($p) && !is_link($p) ? rmrf($p) : @unlink($p); } @rmdir($d); }
function throws(callable $f, string $cls = \Throwable::class): bool { try { $f(); } catch (\Throwable $e) { return $e instanceof $cls; } return false; }
function mkzip(string $file, array $files): string {
    $z = new ZipArchive(); $z->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($files as $p => $c) { $z->addFromString($p, (string)$c); if ($c === '@link') { $z->setExternalAttributesName($p, ZipArchive::OPSYS_UNIX, (0120777 << 16)); } }
    $z->close(); return $file;
}
const PLUGIN_CODE = "<?php\n/**\n * Plugin Name: ETest Plugin\n * Version: 1.2.3\n * Author: Test\n */\nif (!defined('ABSPATH')) exit;\nfunction etest_plugin_loaded() { return true; }\nregister_activation_hook(__FILE__, function () { update_option('etest_activated', '1'); });\nif (get_option('etest_bomb') === '1') { etest_undefined_function(); }\n";
function theme(string $name, bool $bomb = false): array {   // $bomb: stürzt beim Laden ab, sobald die Option etest_theme_bomb gesetzt ist
    return ["$name/style.css" => "/*\nTheme Name: $name\nVersion: 2.0\n*/\n", "$name/index.php" => "<?php // $name\n", "$name/functions.php" => "<?php\nif (!function_exists('etest_theme_fn')) { function etest_theme_fn() {} }\n" . ($bomb ? "if (get_option('etest_theme_bomb') === '1') { etest_undefined_theme_function(); }\n" : '')];
}

// ───────── Kindprozess ─────────
if (($argv[1] ?? '') === '--child') {
    $tmp = (string)$argv[2]; $stage = (string)$argv[3];
    $eng = new Engine("$tmp/cms", "$tmp/cms/data");
    $GLOBALS['elvado_wpe_engine'] = $eng; $GLOBALS['elvado_wpe_db'] = new DbConfig($eng);
    $GLOBALS['elvado_wpe_opts'] = $stage === 'setup' ? ['installing' => true] : [];
    $_SERVER['HTTP_HOST'] = 'example.test';
    if (in_array($stage, ['safe-on', 'safe-off'], true)) { $eng->setSafe($stage === 'safe-on'); echo "ok\n"; exit(0); }
    require __DIR__ . '/../cms/wp-engine-boot.php';
    $admin = new Actor('chef', 'admin');
    $svc = fn() => new ExtensionService(new WordPressExtensionAdapter(), new ExtensionInstaller($eng), $eng, $admin);
    $out = fn(string $k, mixed $v) => print(json_encode([$k => $v]) . "\n");
    switch ($stage) {
        case 'setup':
            $r = Bridge::installSchema('Test', 'test@example.invalid'); $out('install', $r['ok']);
            $in = new ExtensionInstaller($eng);
            foreach (['etest-plugin' => ['etest-plugin/etest-plugin.php' => PLUGIN_CODE], 'a' => theme('etest-a'), 'b' => theme('etest-b', true)] as $k => $files) {
                $kind = $k === 'etest-plugin' ? 'plugin' : 'theme';
                $z = mkzip("$tmp/$k.zip", $files); $res = $in->install($kind, $z); $out("inst-$k", $res['ok'] ? 'ok' : $res['message']);
            }
            break;
        case 'activate':   // Plugin und Theme A aktivieren
            $s = $svc(); $s->activate('plugin', 'etest-plugin/etest-plugin.php'); $s->activate('theme', 'etest-a');
            $out('list', array_map(fn($p) => [$p['slug'], $p['active']], $s->list('plugin')));
            $out('activated_option', get_option('etest_activated'));
            $out('guard', $eng->guard() !== null);
            break;
        case 'check':      // Neustart: Plugin und Theme geladen, Wächter nach erfolgreichem Start gelöscht
            $out('plugin_loaded', function_exists('etest_plugin_loaded')); $out('theme_loaded', function_exists('etest_theme_fn'));
            $out('guard', $eng->guard() !== null); $out('safe_const', defined('ELVADO_ENGINE_SAFE') && ELVADO_ENGINE_SAFE);
            $out('stylesheet', get_stylesheet());
            break;
        case 'bomb-plugin':   // Plugin ist aktiv und stürzt beim nächsten Start ab
            update_option('etest_bomb', '1'); $svc()->activate('plugin', 'etest-plugin/etest-plugin.php'); $out('guard', $eng->guard() !== null); break;
        case 'bomb-theme':    // Theme B aktivieren, dann beim nächsten Start abstürzen lassen
            update_option('etest_bomb', '0'); $svc()->activate('theme', 'etest-b'); update_option('etest_theme_bomb', '1'); $out('stylesheet', get_stylesheet()); $out('guard', $eng->guard()); break;
        case 'recover':
            $out('recovering', Bridge::recovering()); $inc = Bridge::recover($eng); $out('incident', $inc['what'] ?? null);
            global $wpdb; $out('plugin_active', str_contains((string)$wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='active_plugins'"), 'etest-plugin')); $out('stylesheet_db', (string)$wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='stylesheet'")); $out('guard', $eng->guard() !== null);
            $out('safe_const', defined('ELVADO_ENGINE_SAFE') && ELVADO_ENGINE_SAFE); $out('plugin_loaded', function_exists('etest_plugin_loaded'));
            break;
        case 'reactivate':   // Bombe entschärfen, Plugin erneut aktivieren (neuer Wächter)
            update_option('etest_bomb', '0'); $svc()->activate('plugin', 'etest-plugin/etest-plugin.php'); $out('guard', $eng->guard() !== null); break;
        case 'bomb-late':    // Plugin wird nachträglich defekt – ohne vorherigen Wechsel, also ohne Wächter
            update_option('etest_bomb', '1'); $out('guard', $eng->guard() !== null); break;
        case 'plugins-list':
            $out('active', array_values(array_map(fn($p) => $p['slug'], array_filter($svc()->list('plugin'), fn($p) => $p['active']))));
            $out('plugin_loaded', function_exists('etest_plugin_loaded')); break;
        case 'delete':
            $s = $svc(); $s->delete('plugin', 'etest-plugin/etest-plugin.php'); $s->delete('theme', 'etest-b');
            $out('plugins', count($s->list('plugin'))); $out('themes', array_map(fn($t) => $t['slug'], $s->list('theme')));
            $out('active_theme_delete_refused', throws(fn() => $s->delete('theme', 'etest-a'), RuntimeException::class)); break;
    }
    exit(0);
}

$tmp = sys_get_temp_dir() . '/wpx-test-' . bin2hex(random_bytes(4));
@mkdir("$tmp/cms/data", 0755, true); @mkdir("$tmp/cms/wp-content", 0755, true);
register_shutdown_function(function () use ($tmp) { Http::useTransport(null); rmrf($tmp); });
$eng = new Engine("$tmp/cms", "$tmp/cms/data");
$admin = new Actor('chef', 'admin'); $aut = new Actor('schreiber', 'autor');

// ───────── 1) ExtensionSource ─────────
t('Kennungen', ExtensionSource::validSlug('akismet') && ExtensionSource::validSlug('a-b_c1') && !ExtensionSource::validSlug('A') && !ExtensionSource::validSlug('../x') && !ExtensionSource::validSlug('a/b') && !ExtensionSource::validSlug('') && !ExtensionSource::validSlug(str_repeat('a', 81)));
t('Versionen', ExtensionSource::validVersion('1.7.2') && ExtensionSource::validVersion('2.0-beta1') && !ExtensionSource::validVersion('1/2') && !ExtensionSource::validVersion('') && !ExtensionSource::validVersion('1.0;rm'));
t('Download-Adresse selbst gebaut', ExtensionSource::zipUrl('plugin', 'hello-dolly', '1.7.2') === 'https://downloads.wordpress.org/plugin/hello-dolly.1.7.2.zip' && ExtensionSource::zipUrl('theme', 'x', '2.0') === 'https://downloads.wordpress.org/theme/x.2.0.zip');
$calls = [];
$pluginZip = mkzip("$tmp/srv.zip", ['etest-plugin/etest-plugin.php' => PLUGIN_CODE]);
Http::useTransport(function (string $m, string $url, array $h, ?string $b, array $o) use (&$calls, $pluginZip): HttpResponse {
    $calls[] = $url;
    if (str_contains($url, 'action=query_plugins')) {
        return new HttpResponse(200, json_encode(['info' => ['pages' => 3], 'plugins' => [
            ['slug' => 'etest-plugin', 'name' => 'ETest &amp; <b>Plugin</b>', 'version' => '1.2.3', 'author' => '<a href="x">Autor</a>', 'short_description' => '<i>Kurz</i>', 'rating' => 92.4, 'active_installs' => 1000, 'requires_php' => '7.4'],
            ['slug' => '../böse', 'name' => 'X', 'version' => '1.0'], ['slug' => 'ok-slug', 'name' => 'Y', 'version' => '1;rm'],
        ]]));
    }
    if (str_contains($url, 'action=query_themes')) { return new HttpResponse(200, json_encode(['info' => ['pages' => 1], 'themes' => [['slug' => 'etest-a', 'name' => 'A', 'version' => '2.0', 'author' => ['display_name' => 'Wer']]]])); }
    if (str_contains($url, 'plugin_information')) {
        $slug = str_contains($url, 'etest-plugin') ? 'etest-plugin' : (str_contains($url, 'mismatch') ? 'andere' : 'unbekannt');
        return $slug === 'unbekannt' ? new HttpResponse(404, '{"error":"Plugin not found."}') : new HttpResponse(200, json_encode(['slug' => $slug, 'version' => '1.2.3', 'name' => 'ETest Plugin']));
    }
    if (str_contains($url, 'downloads.wordpress.org/plugin/')) { if (isset($o['save_to'])) { copy($pluginZip, $o['save_to']); } return new HttpResponse(200, ''); }
    return new HttpResponse(404, '');
});
$s = ExtensionSource::search('plugin', 'etest', 2);
t('Suche: Treffer bereinigt, ungültige verworfen', $s['ok'] && count($s['items']) === 1 && $s['items'][0]['name'] === 'ETest & Plugin' && $s['items'][0]['author'] === 'Autor' && $s['items'][0]['description'] === 'Kurz' && $s['items'][0]['rating'] === 92 && $s['pages'] === 3, json_encode($s));
t('Suche: Parameter in der Adresse', str_contains($calls[0], 'request%5Bsearch%5D=etest') && str_contains($calls[0], 'request%5Bpage%5D=2') && str_starts_with($calls[0], 'https://api.wordpress.org/plugins/info/1.2/'));
t('Suche Themes (Autor als Objekt)', ExtensionSource::search('theme', '', 1)['items'][0]['author'] === 'Wer' && str_contains(end($calls), 'browse%5D=popular') || str_contains(end($calls), 'browse'));
t('Suche: ungültige Art', ExtensionSource::search('widget', 'x')['ok'] === false);
t('Info: ok', ExtensionSource::info('plugin', 'etest-plugin') === ['ok' => true, 'message' => '', 'version' => '1.2.3', 'name' => 'ETest Plugin']);
t('Info: unbekannt und abweichender Slug', !ExtensionSource::info('plugin', 'unbekannt')['ok'] && !ExtensionSource::info('plugin', 'mismatch')['ok'] && !ExtensionSource::info('plugin', '../x')['ok']);
$dl = ExtensionSource::download('plugin', 'etest-plugin', '1.2.3', "$tmp/dl.zip");
t('Download: Datei und SHA-256', $dl['ok'] && $dl['sha256'] === hash_file('sha256', $pluginZip) && !ExtensionSource::download('plugin', '../x', '1.0', "$tmp/x.zip")['ok'] && !ExtensionSource::download('plugin', 'x', '1/0', "$tmp/x.zip")['ok']);
Http::useTransport(null);
t('Host-Sperre (kein Transport nötig)', Http::request('GET', 'https://evil.example/x.zip', [], null, ['hosts' => ExtensionSource::HOSTS])->error === 'Host nicht erlaubt');

// ───────── 2) ExtensionInstaller ─────────
$in = new ExtensionInstaller($eng);
$base = ['etest-plugin/etest-plugin.php' => PLUGIN_CODE];
$r = $in->install('plugin', mkzip("$tmp/p.zip", $base + ['etest-plugin/readme.txt' => 'x', 'etest-plugin/.git/config' => 'x', 'etest-plugin/.env' => 'GEHEIM', '__MACOSX/etest-plugin/._x' => 'x', 'etest-plugin/assets/a.png' => 'x', 'etest-plugin/LICENSE' => 'GPL']));
t('Plugin installiert (versteckte und Mac-Dateien übersprungen)', $r['ok'] && $r['slug'] === 'etest-plugin' && $r['name'] === 'ETest Plugin' && $r['version'] === '1.2.3' && $r['files'] === 4 && $r['skipped'] === 3, json_encode($r));
$pd = "$tmp/cms/wp-content/plugins/etest-plugin";
t('Dateien am Platz, nichts Verstecktes', is_file("$pd/etest-plugin.php") && is_file("$pd/assets/a.png") && !file_exists("$pd/.env") && !is_dir("$pd/.git") && glob("$tmp/cms/wp-content/plugins/.staging-*") === []);
t('Schon installiert ohne Update-Erlaubnis abgelehnt', !$in->install('plugin', mkzip("$tmp/p2.zip", $base))['ok']);
file_put_contents("$pd/marker.txt", 'alt');
$r2 = $in->install('plugin', mkzip("$tmp/p3.zip", ['etest-plugin/etest-plugin.php' => str_replace('1.2.3', '1.3.0', PLUGIN_CODE)]), true);
t('Update ersetzt und sichert die alte Fassung', $r2['ok'] && $r2['replaced'] && $r2['version'] === '1.3.0' && !is_file("$pd/marker.txt") && count(glob("$tmp/cms/data/.wp-engine/backups/plugin-etest-plugin-*")) === 1 && is_file(glob("$tmp/cms/data/.wp-engine/backups/plugin-etest-plugin-*")[0] . '/marker.txt'));
t('Entfernen sichert, nur die letzte Sicherung bleibt', $in->remove('plugin', 'etest-plugin') && !is_dir($pd) && count(glob("$tmp/cms/data/.wp-engine/backups/plugin-etest-plugin-*")) === 1 && !$in->remove('plugin', 'etest-plugin') && !$in->remove('plugin', '../x') && !$in->remove('widget', 'x'));
$th = $in->install('theme', mkzip("$tmp/t.zip", theme('etest-a')));
t('Theme installiert', $th['ok'] && $th['name'] === 'etest-a' && $th['version'] === '2.0' && is_file("$tmp/cms/wp-content/themes/etest-a/style.css"), json_encode($th));
t('Block-Theme (templates/index.html) genügt', $in->install('theme', mkzip("$tmp/t2.zip", ['blk/style.css' => "/*\nTheme Name: Blk\n*/", 'blk/templates/index.html' => '<p>x</p>']))['ok']);
$bad = [
    'zip-slip' => ['etest-plugin/../../evil.php' => '<?php'], 'absolut' => ['/etc/x.php' => '<?php'], 'lose Dateien' => ['a.php' => '<?php'], 'zwei Hauptordner' => $base + ['andere/x.php' => '<?php'],
    'Verknüpfung' => $base + ['etest-plugin/l.php' => '@link'], 'exe' => $base + ['etest-plugin/x.exe' => 'MZ'], 'phar' => $base + ['etest-plugin/x.phar' => 'x'], 'shell' => $base + ['etest-plugin/x.sh' => '#!/bin/sh'],
    'Rückwärts-Schrägstrich' => $base + ['etest-plugin\\x.php' => '<?php'], 'Großbuchstaben im Ordner' => ['Etest/etest.php' => PLUGIN_CODE], 'ohne Kopfzeile' => ['p2/p2.php' => '<?php // nix'],
    'Syntaxfehler' => $base + ['etest-plugin/kaputt.php' => '<?php function ('], 'leer' => [], 'nur Ordner' => ['etest-plugin/' => ''],
];
foreach ($bad as $label => $files) {
    $z = $label === 'leer' ? (function () use ($tmp) { $zz = new ZipArchive(); $zz->open("$tmp/e.zip", ZipArchive::CREATE | ZipArchive::OVERWRITE); $zz->addEmptyDir('x'); $zz->close(); return "$tmp/e.zip"; })() : mkzip("$tmp/b.zip", $files);
    $rr = $in->install('plugin', $z, true);
    t("Plugin-Paket abgelehnt: $label", !$rr['ok'] && !is_dir("$tmp/cms/wp-content/plugins/etest-plugin") && !file_exists("$tmp/cms/evil.php") && !file_exists("$tmp/evil.php") && glob("$tmp/cms/wp-content/plugins/.staging-*") === [], json_encode($rr));
}
t('Theme ohne style.css/Vorlage abgelehnt', !$in->install('theme', mkzip("$tmp/b.zip", ['x1/index.php' => '<?php']))['ok'] && !$in->install('theme', mkzip("$tmp/b.zip", ['x2/style.css' => "/*\nTheme Name: X\n*/"]))['ok']);
file_put_contents("$tmp/k.zip", 'kein zip');
t('Kein ZIP / unbekannte Art / fehlende Datei', !$in->install('plugin', "$tmp/k.zip")['ok'] && !$in->install('widget', $pluginZip)['ok'] && !$in->install('plugin', "$tmp/gibtsnicht.zip")['ok']);
t('Aktuelle Installation nach Fehlversuchen unverändert', is_dir("$tmp/cms/wp-content/themes/etest-a"));

// ───────── 3) Engine: Wächter, abgesicherter Modus ─────────
t('Kein Wächter am Anfang', $eng->guard() === null && !$eng->safe() && $eng->incident() === null);
$eng->guardSet('theme', 'etest-b', ['template' => 'a', 'stylesheet' => 'a']);
t('Wächter gesetzt und gelesen', $eng->guard()['kind'] === 'theme' && $eng->guard()['id'] === 'etest-b' && $eng->guard()['previous'] === ['template' => 'a', 'stylesheet' => 'a'] && $eng->guard()['probing'] === false);
$eng->guardSet('plugin', 'x/x.php', [], true);
t('Probelauf-Markierung', $eng->guard()['probing'] === true);
$eng->guardClear(); t('Wächter gelöscht', $eng->guard() === null);
file_put_contents($eng->stateDir() . '/guard.json', '{"kind":"hack","id":"x"}');
t('Ungültiger Wächter wird ignoriert', $eng->guard() === null); @unlink($eng->stateDir() . '/guard.json');
$eng->setSafe(true); t('Abgesicherter Modus an/aus', $eng->safe() === true && (new Engine("$tmp/cms", "$tmp/cms/data"))->safe() === true); $eng->setSafe(false); t('…aus', !$eng->safe());

// ───────── 4) ExtensionService (Zwischenspeicher-Adapter) ─────────
final class MemExt implements ExtensionAdapter {
    public array $pl = []; public array $th = []; public array $theme = ['template' => 'a', 'stylesheet' => 'a']; public array $log = [];
    public function plugins(): array { return $this->pl; }
    public function themes(): array { return $this->th; }
    public function activatePlugin(string $id): void { $this->log[] = "act:$id"; foreach ($this->pl as &$p) { if ($p['id'] === $id) { $p['active'] = true; } } }
    public function deactivatePlugin(string $id): void { $this->log[] = "deact:$id"; foreach ($this->pl as &$p) { if ($p['id'] === $id) { $p['active'] = false; } } }
    public function uninstallPlugin(string $id): void { $this->log[] = "uninstall:$id"; }
    public function activeTheme(): array { return $this->theme; }
    public function switchTheme(string $slug): void { $this->log[] = "switch:$slug"; $this->theme = ['template' => $slug, 'stylesheet' => $slug]; }
}
$mem = new MemExt();
$mem->pl = [['id' => 'etest-plugin/etest-plugin.php', 'slug' => 'etest-plugin', 'name' => 'E', 'version' => '1', 'author' => '', 'description' => '', 'uri' => '', 'active' => false, 'requires_php' => '', 'requires_wp' => ''], ['id' => 'einzel.php', 'slug' => 'einzel', 'name' => 'Einzel', 'version' => '1', 'author' => '', 'description' => '', 'uri' => '', 'active' => false, 'requires_php' => '', 'requires_wp' => '']];
$mem->th = [['id' => 'a', 'slug' => 'a', 'name' => 'A', 'version' => '', 'author' => '', 'description' => '', 'active' => true, 'parent' => '', 'block_theme' => false, 'error' => ''], ['id' => 'etest-b', 'slug' => 'etest-b', 'name' => 'B', 'version' => '', 'author' => '', 'description' => '', 'active' => false, 'parent' => 'a', 'block_theme' => false, 'error' => '']];
$sv = new ExtensionService($mem, $in, $eng, $admin);
$sa = new ExtensionService($mem, $in, $eng, $aut);
t('Autor: nichts erlaubt', throws(fn() => $sa->list('plugin'), PermissionException::class) && throws(fn() => $sa->activate('plugin', 'einzel.php'), PermissionException::class) && throws(fn() => $sa->installUpload('plugin', "$tmp/p.zip"), PermissionException::class) && throws(fn() => $sa->setSafe(true), PermissionException::class) && throws(fn() => $sa->search('plugin', 'x', 1), PermissionException::class));
$sv->activate('plugin', 'einzel.php');
t('Plugin aktivieren: Wächter vorher gesetzt', $mem->log === ['act:einzel.php'] && $eng->guard()['kind'] === 'plugin' && $eng->guard()['id'] === 'einzel.php');
$sv->activate('theme', 'etest-b');
t('Theme wechseln: Wächter mit vorherigem Theme', $eng->guard()['kind'] === 'theme' && $eng->guard()['previous'] === ['template' => 'a', 'stylesheet' => 'a'] && $mem->theme['stylesheet'] === 'etest-b');
$cnt = count($mem->log); $sv->activate('theme', 'etest-b');
t('Aktives Theme erneut wählen ändert nichts', count($mem->log) === $cnt);
t('Unbekannte/ungültige Kennungen', throws(fn() => $sv->activate('plugin', 'gibts/nicht.php'), RuntimeException::class) && throws(fn() => $sv->activate('plugin', '../x.php'), InvalidArgumentException::class) && throws(fn() => $sv->activate('widget', 'x'), InvalidArgumentException::class) && throws(fn() => $sv->deactivate('a b'), InvalidArgumentException::class));
$sv->deactivate('einzel.php'); t('Deaktivieren', end($mem->log) === 'deact:einzel.php');
$mem->log = [];
t('Einzeldatei-Plugin wird nicht automatisch gelöscht (und vorher nicht angefasst)', throws(fn() => $sv->delete('plugin', 'einzel.php'), RuntimeException::class) && $mem->log === []);
$mem->th[1]['active'] = true; $mem->th[0]['active'] = false;
t('Aktives Theme und dessen Eltern lassen sich nicht löschen', throws(fn() => $sv->delete('theme', 'etest-b'), RuntimeException::class));
$mem->th[1]['active'] = true; $mem->th[0]['active'] = false; $mem->th[1]['parent'] = ''; $mem->th[0]['parent'] = '';
$mem->th[1]['active'] = false; $mem->th[0]['active'] = true;
$mem->th[1]['parent'] = 'a';
t('Theme löschen: Kind-Theme des aktiven (Eltern = aktiv) ist löschbar, das aktive selbst nicht', throws(fn() => $sv->delete('theme', 'a'), RuntimeException::class));
$in->install('plugin', mkzip("$tmp/p4.zip", $base));
$mem->log = []; $sv->delete('plugin', 'etest-plugin/etest-plugin.php', true);
t('Plugin löschen: deaktivieren, Daten aufräumen, Ordner sichern', $mem->log === ['deact:etest-plugin/etest-plugin.php', 'uninstall:etest-plugin/etest-plugin.php'] && !is_dir("$tmp/cms/wp-content/plugins/etest-plugin"));
$sv->setSafe(true); t('Abgesicherter Modus über den Dienst', $eng->safe()); $sv->setSafe(false);
// Installation aus dem Verzeichnis (nachgestelltes wordpress.org)
Http::useTransport(function (string $m, string $url, array $h, ?string $b, array $o) use ($pluginZip): HttpResponse {
    if (str_contains($url, 'plugin_information')) { return new HttpResponse(200, json_encode(['slug' => str_contains($url, 'falsch') ? 'etest-plugin' : 'etest-plugin', 'version' => '1.2.3', 'name' => 'ETest Plugin'])); }
    if (isset($o['save_to'])) { copy($pluginZip, $o['save_to']); return new HttpResponse(200, ''); }
    return new HttpResponse(404, '');
});
$ri = $sv->installFromDirectory('plugin', 'etest-plugin');
t('Aus dem Verzeichnis installiert, Zwischendatei weg', $ri['slug'] === 'etest-plugin' && is_dir("$tmp/cms/wp-content/plugins/etest-plugin") && glob("$tmp/cms/data/.wp-engine/dl-*") === []);
t('Zweite Installation ohne Update abgelehnt, mit Update erlaubt', throws(fn() => $sv->installFromDirectory('plugin', 'etest-plugin'), RuntimeException::class) && $sv->installFromDirectory('plugin', 'etest-plugin', true)['replaced'] === true);
$sv->delete('plugin', 'etest-plugin/etest-plugin.php');
Http::useTransport(function (string $m, string $url, array $h, ?string $b, array $o) use ($pluginZip): HttpResponse {   // Server liefert ein Paket mit anderem Ordner
    if (str_contains($url, 'plugin_information')) { return new HttpResponse(200, json_encode(['slug' => 'angefragt', 'version' => '1.0', 'name' => 'X'])); }
    if (isset($o['save_to'])) { copy($pluginZip, $o['save_to']); return new HttpResponse(200, ''); }
    return new HttpResponse(404, '');
});
t('Paket mit anderem Ordner als angefragt wird verworfen', throws(fn() => $sv->installFromDirectory('plugin', 'angefragt'), RuntimeException::class) && !is_dir("$tmp/cms/wp-content/plugins/etest-plugin"));
Http::useTransport(null);
t('Ungültige Kennung bei der Installation', throws(fn() => $sv->installFromDirectory('plugin', '../x'), InvalidArgumentException::class));

// ───────── 5) Echtes WordPress (optional) ─────────
$zip = (string)getenv('WPE_TEST_ZIP'); $dbs = (string)getenv('WPE_TEST_DB');
if ($zip === '' || $dbs === '' || !is_file($zip)) {
    echo "Hinweis: Integrationsteil mit echtem WordPress übersprungen (WPE_TEST_ZIP/WPE_TEST_DB nicht gesetzt).\n";
} else {
    [$h, $name, $u, $pw] = array_pad(explode('|', $dbs), 4, '');
    $z = new ZipArchive(); $z->open($zip);
    preg_match('/\$wp_version\s*=\s*\'([^\']+)\'/', (string)$z->getFromName('wordpress/wp-includes/version.php'), $m); $z->close();
    $wp = "$tmp/wp"; @mkdir("$wp/cms/data", 0755, true); @mkdir("$wp/cms/wp-content", 0755, true);
    $we = new Engine("$wp/cms", "$wp/cms/data");
    $r = (new CoreInstaller($we))->install($zip, $m[1], sha1_file($zip));
    $db = new DbConfig($we); $cfg = ['host' => $h, 'name' => $name, 'user' => $u, 'pass' => $pw, 'prefix' => 'wptest_'];
    $tr = $db->test($cfg);
    t('Echter Core und leere Datenbank', $r['ok'] && $tr['ok'] && !$tr['needs_empty'], $r['message'] . ' ' . $tr['message']);
    if ($r['ok'] && $tr['ok']) {
        $db->save($cfg); $we->save(['db' => ['ready' => true]]);
        $run = function (string $stage) use ($wp): array {
            $o = []; exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=' . (getenv('WPX_ERR') ? '1' : '0') . ' ' . escapeshellarg(__FILE__) . ' --child ' . escapeshellarg($wp) . ' ' . $stage . ' 2>>' . (getenv('WPX_ERR') ?: '/dev/null'), $o, $rc);
            $d = []; foreach ($o as $l) { $j = json_decode($l, true); if (is_array($j)) { $d += $j; } }
            return ['rc' => $rc, 'd' => $d];
        };
        $a = $run('setup');
        t('Einrichten und Pakete installieren', $a['rc'] === 0 && $a['d']['install'] === true && $a['d']['inst-etest-plugin'] === 'ok' && $a['d']['inst-a'] === 'ok' && $a['d']['inst-b'] === 'ok', json_encode($a));
        $a = $run('activate');
        t('Plugin und Theme aktiviert (Aktivierungs-Hook lief, Wächter gesetzt)', $a['rc'] === 0 && $a['d']['activated_option'] === '1' && $a['d']['guard'] === true && in_array(['etest-plugin', true], $a['d']['list'], true), json_encode($a));
        $a = $run('check');
        t('Neustart: Plugin und Theme geladen, Probelauf gelöscht den Wächter', $a['rc'] === 0 && $a['d']['plugin_loaded'] === true && $a['d']['theme_loaded'] === true && $a['d']['guard'] === false && $a['d']['stylesheet'] === 'etest-a' && $a['d']['safe_const'] === false, json_encode($a));
        // abgesicherter Modus
        $run('safe-on'); $a = $run('check');
        t('Abgesicherter Modus: weder Plugin noch Theme geladen', $a['rc'] === 0 && $a['d']['plugin_loaded'] === false && $a['d']['theme_loaded'] === false && $a['d']['safe_const'] === true, json_encode($a));
        $run('safe-off'); $a = $run('check');
        t('Abgesicherter Modus aus: alles wieder da', $a['d']['plugin_loaded'] === true && $a['d']['theme_loaded'] === true, json_encode($a));
        // Absturz eines Plugins nach der Aktivierung
        $a = $run('bomb-plugin');
        t('Plugin aktiv und scharf gestellt', $a['rc'] === 0 && $a['d']['guard'] === true, json_encode($a));
        $a = $run('check');
        t('Probelauf stürzt ab (Plugin-Fehler beim Start)', $a['rc'] !== 0, json_encode($a));
        $a = $run('recover');
        t('Folgelauf: abgesichert, Plugin automatisch deaktiviert, Vorfall gemeldet', $a['rc'] === 0 && $a['d']['recovering'] === true && $a['d']['safe_const'] === true && str_contains((string)$a['d']['incident'], 'etest-plugin') && $a['d']['plugin_active'] === false && $a['d']['guard'] === false && $a['d']['plugin_loaded'] === false, json_encode($a));
        $a = $run('plugins-list');
        t('Danach läuft alles normal ohne das Plugin', $a['rc'] === 0 && $a['d']['active'] === [] && $a['d']['plugin_loaded'] === false, json_encode($a));
        // Plugin wird später defekt, ohne dass ein Wechsel vorausging (kein Wächter): Absturz wird erkannt, Verursacher abgeschaltet
        $run('reactivate'); $a = $run('check');
        t('Plugin wieder aktiv und geladen', $a['rc'] === 0 && $a['d']['plugin_loaded'] === true && $a['d']['guard'] === false, json_encode($a));
        $a = $run('bomb-late');
        t('Defekt ohne Wächter vorbereitet', $a['rc'] === 0 && $a['d']['guard'] === false, json_encode($a));
        $a = $run('check');
        t('Start stürzt ab', $a['rc'] !== 0, json_encode($a));
        t('Absturz im Plugin hat einen Wächter hinterlassen', is_file("$wp/cms/data/.wp-engine/guard.json") && str_contains((string)file_get_contents("$wp/cms/data/.wp-engine/guard.json"), 'etest-plugin') && str_contains((string)file_get_contents("$wp/cms/data/.wp-engine/log.txt"), 'Absturz beim Start in plugin'));
        $a = $run('recover');
        t('Folgelauf deaktiviert den Verursacher automatisch', $a['rc'] === 0 && $a['d']['recovering'] === true && $a['d']['plugin_active'] === false && str_contains((string)$a['d']['incident'], 'etest-plugin') && $a['d']['guard'] === false, json_encode($a));
        $a = $run('plugins-list');
        t('Danach normaler Start', $a['rc'] === 0 && $a['d']['active'] === [], json_encode($a));
        // Absturz eines Themes nach dem Wechsel
        $a = $run('bomb-theme');
        t('Theme B aktiv und scharf gestellt', $a['rc'] === 0 && $a['d']['stylesheet'] === 'etest-b' && is_array($a['d']['guard']) && $a['d']['guard']['previous']['stylesheet'] === 'etest-a', json_encode($a));
        $a = $run('check');
        t('Probelauf stürzt ab (Theme-Fehler beim Start)', $a['rc'] !== 0, json_encode($a));
        $a = $run('recover');
        t('Folgelauf: früheres Theme wieder aktiv, Vorfall gemeldet', $a['rc'] === 0 && $a['d']['recovering'] === true && $a['d']['stylesheet_db'] === 'etest-a' && str_contains((string)$a['d']['incident'], 'etest-b') && $a['d']['guard'] === false, json_encode($a));
        $a = $run('check');
        t('Danach normaler Start mit dem früheren Theme', $a['rc'] === 0 && $a['d']['stylesheet'] === 'etest-a' && $a['d']['theme_loaded'] === true && $a['d']['safe_const'] === false, json_encode($a));
        $a = $run('delete');
        t('Löschen: Plugin und inaktives Theme weg, aktives Theme geschützt', $a['rc'] === 0 && $a['d']['plugins'] === 0 && $a['d']['themes'] === ['etest-a'] && $a['d']['active_theme_delete_refused'] === true, json_encode($a));
        [$host, $port] = array_pad(explode(':', $h), 2, '3306');
        $my = @new mysqli($host === 'localhost' ? '127.0.0.1' : $host, $u, $pw, $name, (int)$port);
        if (!$my->connect_errno) { $res = $my->query("SHOW TABLES LIKE 'wptest\\_%'"); while ($res && ($row = $res->fetch_row())) { $my->query('DROP TABLE `' . $my->real_escape_string($row[0]) . '`'); } }
    }
}
echo $fail ? "$fail von $n Prüfungen fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n";
exit($fail ? 1 : 0);

<?php
// Prüft die WordPress-Engine (cms/src/Wp): Zustand, Voraussetzungen, Core-Installer (Sicherheit), Datenbank-Angaben, Bridge, Adapter.
// Ohne Netzwerk und ohne Datenbank. Aufruf: php scripts/test-wp-engine.php
declare(strict_types=1);
require __DIR__ . '/../cms/src/autoload.php';
use Elvado\Wp\{Engine, Requirements, CoreSource, CoreInstaller, DbConfig, Bridge};
use Elvado\Wp\Adapter\NativeAdapter;

$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
function rmrf(string $d): void { if (!is_dir($d)) return; foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') continue; $p = "$d/$f"; is_dir($p) && !is_link($p) ? rmrf($p) : @unlink($p); } @rmdir($d); }
$tmp = sys_get_temp_dir() . '/wpe-test-' . bin2hex(random_bytes(4));
mkdir("$tmp/cms/data", 0755, true);
register_shutdown_function(fn() => rmrf($tmp));

// Paket bauen: $files = [Pfad => Inhalt]; Werte "@link" erzeugen eine symbolische Verknüpfung
function mkzip(string $file, array $files): string {
    $z = new ZipArchive(); $z->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($files as $p => $c) {
        $z->addFromString($p, (string)$c);
        if ($c === '@link') { $z->setExternalAttributesName($p, ZipArchive::OPSYS_UNIX, (0120777 << 16)); }
    }
    $z->close(); return $file;
}
function good(string $v = '9.9.9'): array {
    return [
        'wordpress/wp-load.php' => '<?php // load', 'wordpress/wp-settings.php' => '<?php // settings',
        'wordpress/wp-includes/version.php' => "<?php\n\$wp_version = '$v';\n",
        'wordpress/wp-includes/class-wpdb.php' => '<?php class wpdb {}',
        'wordpress/wp-admin/includes/upgrade.php' => '<?php // upgrade', 'wordpress/wp-includes/plugin.php' => '<?php // plugin',
        'wordpress/wp-content/plugins/hello.php' => '<?php // darf nicht übernommen werden',
        'wordpress/readme.html' => '<p>x</p>', 'wordpress/license.txt' => 'GPL',
    ];
}
$eng = fn() => new Engine("$tmp/cms", "$tmp/cms/data");

// 1) Engine: Zustand
$e = $eng();
t('Standard ist off', $e->mode() === 'off' && !$e->isInstalled() && !$e->isActive());
t('Pfade', $e->stateDir() === "$tmp/cms/data/.wp-engine" && $e->coreRoot() === "$tmp/cms/wp-engine" && $e->contentDir() === "$tmp/cms/wp-content");
t('Modus ohne Core abgelehnt', $e->setMode('installed')['ok'] === false);
t('Unbekannter Modus abgelehnt', $e->setMode('xyz')['ok'] === false);
file_put_contents("$tmp/cms/data/.wp-engine-x", '');
@mkdir($e->stateDir(), 0755, true); file_put_contents($e->stateDir() . '/state.json', '{"mode":"active","core":"core-1.2.3"}');
t('Zustand ohne Core-Ordner → off', $e->state()['mode'] === 'off');
file_put_contents($e->stateDir() . '/state.json', '{"mode":"active","core":"../../etc"}');
t('Manipulierter Core-Name wird verworfen', $e->state()['core'] === '');
@unlink($e->stateDir() . '/state.json');

// 2) CoreSource
t('Version gültig', CoreSource::validVersion('7.1.3') && CoreSource::validVersion('6.9'));
foreach (['', '7', '7.1.3.1', '../1.2', '7.1.3;x', 'a.b.c', '7.1.3/../x'] as $bad) t("Version ungültig: $bad", !CoreSource::validVersion($bad));
t('Download-URL nur von wordpress.org', CoreSource::zipUrl('7.1.3') === 'https://downloads.wordpress.org/release/wordpress-7.1.3.zip');
t('Host-Liste', CoreSource::HOSTS === ['api.wordpress.org', 'downloads.wordpress.org', 'wordpress.org']);
t('Download mit ungültiger Version', CoreSource::download('x', "$tmp/x.zip", str_repeat('a', 40))['ok'] === false);

// 3) CoreInstaller
$inst = new CoreInstaller($e);
$zip = mkzip("$tmp/good.zip", good());
$sha = sha1_file($zip);
$r = $inst->install($zip, '9.9.9', str_repeat('0', 40));
t('Falsche Prüfsumme abgelehnt', !$r['ok'] && !is_dir($e->coreRoot() . '/core-9.9.9'));
$r = $inst->install($zip, '9.9.8', $sha);
t('Version passt nicht zum Paket', !$r['ok'] && str_contains($r['message'], 'Version'));
t('Nach Fehler kein Zwischenordner', glob($e->coreRoot() . '/.staging-*') === []);
$r = $inst->install($zip, '9.9.9', $sha);
t('Installation ok', $r['ok'] && $r['core'] === 'core-9.9.9' && $r['files'] === 8 && $r['skipped'] === 1, json_encode($r));
t('wp-content des Pakets nicht übernommen', !is_dir($e->coreRoot() . '/core-9.9.9/wp-content'));
t('Core vorhanden und im Zustand', $e->corePath() !== null && $e->state()['version'] === '9.9.9');
t('Zweite Installation ist idempotent', $inst->install($zip, '9.9.9', $sha)['ok'] === true);
t('Core-Ordner ist gesperrt', is_file($e->coreRoot() . '/.htaccess') && str_contains((string)file_get_contents($e->coreRoot() . '/.htaccess'), 'Require all denied'));
t('Zustandsordner ist gesperrt', is_file($e->stateDir() . '/.htaccess'));
t('Modus installed möglich', $e->setMode('installed')['ok'] && $e->mode() === 'installed');
t('Modus active braucht Datenbank', $e->setMode('active')['ok'] === false);
$e->save(['db' => ['ready' => true]]);
t('Modus active mit Datenbank', $e->setMode('active')['ok'] && $e->isActive());

$cases = [
    'Pfad außerhalb (zip-slip)' => ['wordpress/../evil.php' => '<?php'],
    'ohne wordpress/-Ordner' => ['other/x.php' => '<?php'],
    'versteckte Datei' => ['wordpress/.env' => 'x'],
    'verbotener Dateityp' => ['wordpress/shell.phtml' => 'x'],
    'Verknüpfung' => ['wordpress/wp-includes/link.php' => '@link'],
    'Rückwärts-Schrägstrich' => ['wordpress/a\\b.php' => 'x'],
    'Syntaxfehler in PHP' => ['wordpress/wp-includes/broken.php' => '<?php function ('],
];
foreach ($cases as $label => $extra) {
    $f = ($label === 'Syntaxfehler in PHP' || $label === 'Verknüpfung') ? array_merge(good('9.9.7'), $extra) : array_merge($extra, good('9.9.7'));
    $z = mkzip("$tmp/c.zip", $f);
    $rr = $inst->install($z, '9.9.7', sha1_file($z));
    t("abgelehnt: $label", !$rr['ok'] && !is_dir($e->coreRoot() . '/core-9.9.7') && !file_exists("$tmp/cms/evil.php") && !file_exists("$tmp/evil.php"), json_encode($rr));
}
$f = good('9.9.7'); unset($f['wordpress/wp-settings.php']);
$z = mkzip("$tmp/c.zip", $f); $rr = $inst->install($z, '9.9.7', sha1_file($z));
t('wichtige Datei fehlt', !$rr['ok'] && str_contains($rr['message'], 'wp-settings.php'));
file_put_contents("$tmp/c.zip", 'kein zip'); $rr = $inst->install("$tmp/c.zip", '9.9.7', sha1_file("$tmp/c.zip"));
t('kein ZIP', !$rr['ok']);
t('Aktueller Core nach Fehlversuchen unverändert', $e->state()['core'] === 'core-9.9.9' && $e->corePath() !== null);
// Aktualisierung: aktueller + vorheriger bleiben, älterer wird entfernt
foreach (['9.9.10', '9.9.11'] as $v) { $z = mkzip("$tmp/u.zip", good($v)); t("Update $v", $inst->install($z, $v, sha1_file($z))['ok']); }
$dirs = array_map('basename', glob($e->coreRoot() . '/core-*', GLOB_ONLYDIR));
sort($dirs);
t('nur aktueller und vorheriger Core bleiben', $dirs === ['core-9.9.10', 'core-9.9.11'], json_encode($dirs));
t('Zustand: previous', $e->state()['previous'] === ['core-9.9.10'] && $e->state()['core'] === 'core-9.9.11');

// 4) Voraussetzungen
$res = Requirements::check($e); $req = $res['items'];
$ids = array_column($req, 'id');
t('Voraussetzungen liefern Einträge', count($req) >= 6 && in_array('php', $ids, true), json_encode($ids));
t('Einträge haben Status/Text', !array_filter($req, fn($i) => !in_array($i['status'], ['ok', 'warn', 'fail'], true) || $i['label'] === ''));
t('Bytes-Umrechnung', Requirements::bytes('128M') === 134217728 && Requirements::bytes('1G') === 1073741824 && Requirements::bytes('-1') === -1);

// 5) DbConfig
$db = new DbConfig($e);
$ok = ['host' => 'localhost:3307', 'name' => 'wp_db', 'user' => 'u', 'pass' => 'p"a$s', 'prefix' => 'wp_'];
t('Angaben gültig', $db->validate($ok)['ok']);
foreach (['host' => 'a b;c', 'name' => 'x`y', 'user' => '', 'prefix' => 'WP_', 'pass' => "a\0b"] as $k => $bad) t("ungültig: $k", !$db->validate(array_merge($ok, [$k => $bad]))['ok']);
t('Präfix muss auf _ enden', !$db->validate(array_merge($ok, ['prefix' => 'wp']))['ok']);
t('Host-Zerlegung', DbConfig::hostParts('db.example:3307') === ['db.example', 3307, null] || is_array(DbConfig::hostParts('db.example:3307')));
t('Ohne Datei kein Ergebnis', $db->get() === null && $db->redacted() === null);
$db->save($ok);
t('Passwort wird gespeichert, aber nicht ausgegeben', $db->get()['pass'] === 'p"a$s' && !array_key_exists('pass', $db->redacted()) && $db->redacted()['has_password'] === true);
t('db.json nur für den Eigentümer', (fileperms($e->stateDir() . '/db.json') & 0777) === 0600);
try { $db->save(array_merge($ok, ['prefix' => 'WP'])); t('Ungültiges Speichern wirft', false); } catch (RuntimeException) { t('Ungültiges Speichern wirft', true); }
$k1 = $db->keys(); $k2 = $db->keys();
t('Schlüssel: 8 Stück, stabil, lang genug', count($k1) === 8 && $k1 === $k2 && strlen($k1['AUTH_KEY']) >= 64 && count(array_unique($k1)) === 8);
t('keys.json nur für den Eigentümer', (fileperms($e->stateDir() . '/keys.json') & 0777) === 0600);
$tr = $db->test(['host' => '127.0.0.1:1', 'name' => 'x', 'user' => 'u', 'pass' => 'p', 'prefix' => 'wp_']);
t('Nicht erreichbare Datenbank: verständliche Meldung', $tr['ok'] === false && $tr['message'] !== '' && !str_contains($tr['message'], 'Stack trace'), $tr['message']);

// 6) Bridge: Sicherung/Wiederherstellung der Anfrage-Daten
$_GET = ['a' => '1']; $_POST = ['b' => '2']; $_COOKIE = ['c' => '3']; $_REQUEST = ['a' => '1']; date_default_timezone_set('Europe/Berlin');
$snap = Bridge::snapshot();
$_GET = []; $_POST = ['x' => 'y']; $_COOKIE = []; $_REQUEST = []; date_default_timezone_set('UTC'); error_reporting(0);
Bridge::restore($snap);
t('Superglobals wiederhergestellt', $_GET === ['a' => '1'] && $_POST === ['b' => '2'] && $_COOKIE === ['c' => '3'] && $_REQUEST === ['a' => '1']);
t('Zeitzone und Fehlerstufe wiederhergestellt', date_default_timezone_get() === 'Europe/Berlin' && error_reporting() === $snap['er']);
t('WordPress nicht gestartet', Bridge::booted() === false);

// 7) NativeAdapter
$cms = "$tmp/cms";
file_put_contents("$cms/data/news.json", json_encode([
    ['status' => 'published', 'category' => 'A', 'tags' => 'x, y'], ['status' => 'draft', 'category' => 'a', 'tags' => 'Y'],
    ['status' => 'published', 'deleted_at' => '2020-01-01'],
]));
mkdir("$cms/content/pages/one", 0755, true); file_put_contents("$cms/content/pages/one/page.md", '# x');
mkdir("$cms/media", 0755, true); file_put_contents("$cms/media/a.png", 'x'); file_put_contents("$cms/media/.htaccess", 'x');
$c = (new NativeAdapter($cms, "$cms/data"))->counts();
t('Zählung Beiträge/Entwürfe/Seiten/Medien', $c['posts'] === 1 && $c['drafts'] === 1 && $c['pages'] === 1 && $c['media'] === 1, json_encode($c));
t('Zählung Kategorien/Tags ohne Doppelte', $c['categories'] === 1 && $c['tags'] === 2, json_encode($c));
t('Kaputte Daten stören nicht', (function () use ($cms) { file_put_contents("$cms/data/news.json", '{kaputt'); return (new NativeAdapter($cms, "$cms/data"))->counts()['posts'] === 0; })());

// 8) Der Engine-Standard verändert nichts an einer ElvadoPress-Anfrage
$api = (string)file_get_contents(__DIR__ . '/../cms/engine-api.php');
t('engine-api verweigert Gäste und beschränkt Nicht-Administratoren auf Inhalte/Medien', str_contains($api, 'rrw_auth(false)') && str_contains($api, '!$rrwActor->isAdmin() && !in_array($rrwEngineAction, [') && !preg_match("/'engine_[a-z_]+'.*'content_list'/", (string)preg_replace('/\n/', ' ', substr($api, (int)strpos($api, '!$rrwActor->isAdmin()'), 400))));
t('Demo sperrt Schreib-Aktionen', (bool)preg_match('/demo/i', (string)file_get_contents(__DIR__ . '/../cms/engine-api.php')));

echo $fail ? "$fail von $n Prüfungen fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n";
exit($fail ? 1 : 0);

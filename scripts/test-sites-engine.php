<?php
// WordPress-Engine je Website (Multisite Stufe 4): eigener Engine-Zustand und eigenes Tabellenpräfix in der gemeinsamen Datenbank.
// Ohne echtes WordPress/Datenbank (Konfigurationsebene). Aufruf: php scripts/test-sites-engine.php
declare(strict_types=1);
$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
function rmrf(string $d): void { if (!is_dir($d)) return; foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') continue; $p = "$d/$f"; is_dir($p) && !is_link($p) ? rmrf($p) : @unlink($p); } @rmdir($d); }
$tmp = sys_get_temp_dir() . '/sites-engine-' . bin2hex(random_bytes(4));
register_shutdown_function(fn() => rmrf($tmp));
mkdir("$tmp/data", 0755, true);
define('RRW_SITES_BASE', $tmp);
require __DIR__ . '/../cms/lib/sites.php';
require __DIR__ . '/../cms/src/autoload.php';
use Elvado\Wp\{Engine, DbConfig};

file_put_contents("$tmp/data/database.local.php", '<?php return ' . var_export(['driver' => 'mariadb', 'host' => '127.0.0.1', 'port' => 3306, 'database' => 'cmsdb', 'user' => 'u', 'password' => 'p', 'prefix' => 'wpl_'], true) . ';');
rrw_site_create(['name' => 'Zweite', 'domains' => ['zweite.test'], 'enabled' => true]);
rrw_site_create(['name' => 'Dritte', 'domains' => ['dritte.test'], 'enabled' => true]);

// Hauptwebsite
$main = new DbConfig(new Engine($tmp, "$tmp/data"));
t('Hauptwebsite: Standard-Präfix wpk_', $main->defaultPrefix() === 'wpk_' && ($main->overview()['site'] ?? 'x') === '');
t('Hauptwebsite: gemeinsame Verbindung gefunden', ($main->shared()['name'] ?? '') === 'cmsdb');

// Weitere Website
rrw_site_use('zweite');
$z = new Engine($tmp, rrw_site_dir('data'));
$zd = new DbConfig($z);
t('Zweite: Engine-Zustand unter den Daten der Website', str_starts_with($z->stateDir(), "$tmp/sites/zweite/data/"), $z->stateDir());
$zp = $zd->defaultPrefix();
t('Zweite: eigenes Präfix, gültig und ungleich wpk_/wp_', $zp !== 'wpk_' && preg_match('/^[a-z][a-z0-9]{0,18}_$/', $zp) === 1 && $zd->validate(['host' => 'h', 'name' => 'd', 'user' => 'u', 'prefix' => $zp])['ok']);
t('Zweite: nutzt dieselbe Datenbankverbindung wie die Hauptwebsite', ($zd->shared()['name'] ?? '') === 'cmsdb' && $zd->sharedFile() === "$tmp/data/database.local.php");
$cfg = $zd->withShared([]);
t('Zweite: withShared setzt Verbindung der Hauptwebsite + eigenes Präfix', $cfg['name'] === 'cmsdb' && $cfg['prefix'] === $zp);
$zd->save(['prefix' => $zp]);
t('Zweite: Präfix wird in der Website gespeichert, nicht in der Hauptwebsite', $zd->enginePrefix() === $zp && !is_file("$tmp/data/.wp-engine/db.json"));
t('Zweite: Verbindung kommt nicht ins Website-Verzeichnis', !is_file("$tmp/sites/zweite/data/database.local.php"));
t('Zweite: Präfix der WordPress-Schicht des CMS bleibt gesperrt', $zd->prefixConflict('wpl_') !== null);

// Dritte darf das Präfix der zweiten nicht nutzen
rrw_site_use('dritte');
$dd = new DbConfig(new Engine($tmp, rrw_site_dir('data')));
t('Dritte: anderes Standard-Präfix als die zweite', $dd->defaultPrefix() !== $zp);
t('Dritte: Präfix der zweiten Website wird abgelehnt', $dd->prefixConflict($zp) !== null);
t('Dritte: eigenes Präfix frei', $dd->prefixConflict($dd->defaultPrefix()) === null);
rrw_site_use('');
$m2 = new DbConfig(new Engine($tmp, "$tmp/data"));
t('Hauptwebsite: Präfix einer weiteren Website wird abgelehnt', $m2->prefixConflict($zp) !== null);
t('Hauptwebsite: wpk_ frei', $m2->prefixConflict('wpk_') === null);

// Gemeinsamer Core: Aufräumen darf keine Version löschen, die eine andere Website noch nutzt
$ea = new Engine($tmp, "$tmp/data");
foreach (['core-6.0.0', 'core-6.1.0', 'core-5.9.0'] as $c) { @mkdir($ea->coreRoot() . '/' . $c, 0755, true); }
$ea->save(['core' => 'core-6.1.0']);
rrw_site_use('zweite');
(new Engine($tmp, rrw_site_dir('data')))->save(['core' => 'core-6.0.0']);
rrw_site_use('');
$ci = new \Elvado\Wp\CoreInstaller($ea);
$rm = new ReflectionMethod($ci, 'prune'); $rm->setAccessible(true); $rm->invoke($ci, 'core-6.1.0', []);
t('Core-Aufräumen: Version einer anderen Website bleibt, ungenutzte wird entfernt', is_dir($ea->coreRoot() . '/core-6.0.0') && is_dir($ea->coreRoot() . '/core-6.1.0') && !is_dir($ea->coreRoot() . '/core-5.9.0'));

// Verdrahtung
$w = (string)file_get_contents(__DIR__ . '/../cms/lib/wpengine.php');
t('rrw_wpe wählt den Zustandsordner der Website', str_contains($w, 'rrw_site_dir(\'data\')') && str_contains($w, "lib/sites.php"));
$db = (string)file_get_contents(__DIR__ . '/../cms/wp/core/wpdb.php');
t('Emulation weiterer Websites nutzt keine gemeinsamen MySQL-Tabellen', str_contains($db, "rrw_site_current()!==''"));
echo $fail ? "$fail von $n Prüfungen fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n";
exit($fail ? 1 : 0);

<?php
declare(strict_types=1);
// scripts/test-db-shared.php – EINE Datenbank für CMS und WordPress-Kern: gemeinsame Verbindung (cms/data/database.local.php), eigenes Präfix der Engine (db.json),
// Präfix-Konflikt, Zusammenführen älterer Einrichtungen. Aufruf: php scripts/test-db-shared.php
require __DIR__ . '/../cms/src/autoload.php';
use Elvado\Wp\{Engine, DbConfig};
$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
function rmrf(string $d): void { if (!is_dir($d)) return; foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') continue; $p = "$d/$f"; is_dir($p) && !is_link($p) ? rmrf($p) : @unlink($p); } @rmdir($d); }
function fresh(): array { $tmp = sys_get_temp_dir() . '/dbs-' . bin2hex(random_bytes(4)); mkdir("$tmp/cms/data", 0755, true); $e = new Engine("$tmp/cms", "$tmp/cms/data"); return [$tmp, $e, new DbConfig($e)]; }
$conn = ['host' => 'db.example:3307', 'name' => 'meine_db', 'user' => 'benutzer', 'pass' => 'ge"heim$1', 'prefix' => 'wpk_'];

// 1) Nichts eingerichtet
[$t1, $e1, $d1] = fresh();
t('Nichts eingerichtet: keine Verbindung, Übersicht ohne gemeinsame Datenbank', $d1->get() === null && $d1->shared() === null && $d1->overview()['shared'] === false && $d1->overview()['legacy'] === false && $d1->overview()['suggest_prefix'] === 'wpk_');
t('Standard-Präfix der Engine unterscheidet sich vom Präfix der WordPress-Schicht', DbConfig::DEFAULT_PREFIX !== 'wp_');

// 2) Erste Verbindung wird zur gemeinsamen Datenbank
$d1->save($conn);
$sf = "$t1/cms/data/database.local.php";
$cfg = is_file($sf) ? include $sf : [];
t('Gemeinsame Datenbank angelegt (MySQL, Server/Port, Name, Benutzer, Passwort), nur für den Eigentümer', ($cfg['driver'] ?? '') === 'mysql' && ($cfg['host'] ?? '') === 'db.example' && ($cfg['port'] ?? 0) === 3307 && ($cfg['database'] ?? '') === 'meine_db' && ($cfg['user'] ?? '') === 'benutzer' && ($cfg['password'] ?? '') === 'ge"heim$1' && (fileperms($sf) & 0777) === 0600, json_encode($cfg));
t('Präfix der WordPress-Schicht des CMS weicht vom Präfix der Engine ab', ($cfg['prefix'] ?? '') !== 'wpk_' && ($cfg['prefix'] ?? '') !== '');
$j = json_decode((string)file_get_contents("$t1/cms/data/.wp-engine/db.json"), true);
t('db.json enthält nur noch das Präfix der Engine (keine Zugangsdaten doppelt)', $j === ['prefix' => 'wpk_'], json_encode($j));
$g = $d1->get();
t('Die Engine bekommt Verbindung (gemeinsam) + eigenes Präfix', $g === ['host' => 'db.example:3307', 'name' => 'meine_db', 'user' => 'benutzer', 'pass' => 'ge"heim$1', 'prefix' => 'wpk_'], json_encode($g));
t('Verwaltung sieht kein Passwort, aber „gemeinsam“', !array_key_exists('pass', $d1->redacted()) && $d1->redacted()['shared'] === true && $d1->redacted()['has_password'] === true && !str_contains(json_encode($d1->overview()), 'heim'));

// 3) Mit gemeinsamer Datenbank zählt nur das Präfix
$w = $d1->withShared(['host' => 'anderer-server', 'name' => 'andere_db', 'user' => 'x', 'pass' => 'y', 'prefix' => 'abc_']);
t('Eingaben mit anderer Verbindung werden ignoriert, das Präfix gilt', $w === ['host' => 'db.example:3307', 'name' => 'meine_db', 'user' => 'benutzer', 'pass' => 'ge"heim$1', 'prefix' => 'abc_'], json_encode($w));
t('Ohne Präfix in der Eingabe: bisheriges Präfix der Engine', $d1->withShared([])['prefix'] === 'wpk_');
$d1->save(['prefix' => 'neu_']);
t('Nur das Präfix speichern ändert nur db.json, nicht die gemeinsame Datenbank', $d1->enginePrefix() === 'neu_' && (include $sf) === $cfg);
try { $d1->save(['prefix' => 'WP']); t('Ungültiges Präfix wirft', false); } catch (RuntimeException) { t('Ungültiges Präfix wirft', true); }
$cmsPrefix = (string)$cfg['prefix'];
t('Präfix-Konflikt mit der WordPress-Schicht wird erkannt (Groß/Klein egal)', $d1->prefixConflict($cmsPrefix) !== null && $d1->prefixConflict(strtoupper($cmsPrefix)) !== null && $d1->prefixConflict('frei_') === null && str_contains((string)$d1->prefixConflict($cmsPrefix), 'wpk_'));
try { $d1->save(['prefix' => $cmsPrefix]); t('Speichern mit dem Präfix der WordPress-Schicht wirft', false); } catch (RuntimeException $x) { t('Speichern mit dem Präfix der WordPress-Schicht wirft', str_contains($x->getMessage(), 'WordPress-Schicht')); }

// 4) Bestehende gemeinsame Datenbank wird nie überschrieben
[$t2, $e2, $d2] = fresh();
$mine = ['driver' => 'mariadb', 'host' => '10.0.0.5', 'port' => 3306, 'socket' => '', 'database' => 'cms_db', 'user' => 'cms', 'password' => 'geheim', 'prefix' => 'wp_', 'charset' => 'utf8mb4', 'sqlite_path' => 'cms.sqlite'];
file_put_contents("$t2/cms/data/database.local.php", "<?php\nreturn " . var_export($mine, true) . ";\n");
t('Vorhandene MariaDB-Einstellungen des CMS werden als gemeinsame Datenbank erkannt', $d2->shared() === ['host' => '10.0.0.5', 'name' => 'cms_db', 'user' => 'cms', 'pass' => 'geheim', 'cms_prefix' => 'wp_']);
$d2->save(['host' => 'egal', 'name' => 'egal', 'user' => 'egal', 'pass' => 'egal', 'prefix' => 'wpk_']);
t('Speichern der Engine lässt die gemeinsamen Einstellungen unverändert', (include "$t2/cms/data/database.local.php") === $mine && $d2->get()['name'] === 'cms_db' && $d2->get()['prefix'] === 'wpk_');
t('Standardpräfix „wp_“ der WordPress-Schicht ist für die Engine gesperrt', $d2->prefixConflict('wp_') !== null);
t('SQLite/keine Datenbank im CMS zählt nicht als gemeinsame MySQL-Datenbank', (function () { [$t, $e, $d] = fresh(); file_put_contents("$t/cms/data/database.local.php", "<?php\nreturn " . var_export(['driver' => 'sqlite', 'sqlite_path' => 'cms.sqlite'], true) . ";\n"); $r = $d->shared() === null; rmrf($t); return $r; })());
t('Socket-Verbindung des CMS wird für die Engine übersetzt', (function () { [$t, $e, $d] = fresh(); file_put_contents("$t/cms/data/database.local.php", "<?php\nreturn " . var_export(['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'socket' => '/var/run/mysqld/mysqld.sock', 'database' => 'a', 'user' => 'b', 'password' => 'c', 'prefix' => 'wp_'], true) . ";\n"); $s = $d->shared(); rmrf($t); return ($s['host'] ?? '') === 'localhost:/var/run/mysqld/mysqld.sock' && DbConfig::hostParts($s['host'])[2] === '/var/run/mysqld/mysqld.sock'; })());

// 5) Ältere Einrichtung (vollständige db.json) → weiter nutzbar, dann zusammenführen
[$t3, $e3, $d3] = fresh();
$e3->protect();
file_put_contents($e3->stateDir() . '/db.json', json_encode(['host' => 'localhost', 'name' => 'alt_db', 'user' => 'alt', 'pass' => 'altpw', 'prefix' => 'wp_']));
t('Ältere db.json läuft unverändert weiter und wird als „getrennt“ gemeldet', $d3->get()['name'] === 'alt_db' && $d3->overview()['legacy'] === true && $d3->overview()['shared'] === false && $d3->redacted()['shared'] === false);
$u = $d3->unify();
$sc = (include "$t3/cms/data/database.local.php");
t('Zusammenführen: Verbindung wird gemeinsame Datenbank, db.json behält nur das Präfix', $u['ok'] && ($sc['database'] ?? '') === 'alt_db' && ($sc['user'] ?? '') === 'alt' && ($sc['password'] ?? '') === 'altpw' && json_decode((string)file_get_contents($e3->stateDir() . '/db.json'), true) === ['prefix' => 'wp_'], json_encode([$u, $sc]));
t('Zusammenführen: Präfix der WordPress-Schicht weicht vom bisherigen Engine-Präfix ab (keine Kollision)', ($sc['prefix'] ?? 'wp_') !== 'wp_' && $d3->prefixConflict('wp_') === null);
t('Danach: gleiche Verbindung für die Engine, nichts mehr getrennt', $d3->get()['name'] === 'alt_db' && $d3->get()['prefix'] === 'wp_' && $d3->overview()['legacy'] === false && !$d3->unify()['ok']);
// Zusammenführen mit abweichender gemeinsamer Datenbank wird verweigert
[$t4, $e4, $d4] = fresh();
$e4->protect();
file_put_contents($e4->stateDir() . '/db.json', json_encode(['host' => 'localhost', 'name' => 'alt_db', 'user' => 'alt', 'pass' => 'pw', 'prefix' => 'wp_']));
file_put_contents("$t4/cms/data/database.local.php", "<?php\nreturn " . var_export($mine, true) . ";\n");
$u4 = $d4->unify();
t('Zusammenführen: gemeinsame Datenbank zeigt woandershin → verweigert, nichts verändert', !$u4['ok'] && str_contains($u4['message'], 'cms_db') && (include "$t4/cms/data/database.local.php") === $mine && isset(json_decode((string)file_get_contents($e4->stateDir() . '/db.json'), true)['name']));
// ohne Schreibrechte


// 6) Schnittstelle und Oberfläche
$api = (string)file_get_contents(__DIR__ . '/../cms/engine-api.php'); $lib = (string)file_get_contents(__DIR__ . '/../cms/lib/wpengine.php'); $js = (string)file_get_contents(__DIR__ . '/../cms/assets/wp-engine.js');
t('API: Test und Einrichtung nutzen die gemeinsame Verbindung und prüfen den Präfix-Konflikt', substr_count($api, 'withShared') >= 2 && str_contains($api, 'prefixConflict') && str_contains($lib, 'prefixConflict'));
t('API: Zusammenführen nur per POST (und für Administratoren wie alle Engine-Aktionen), in der Demo gesperrt', str_contains($api, "'engine_db_unify'") && str_contains($api, "Diese Aktion verlangt POST") && str_contains((string)file_get_contents(__DIR__ . '/../cms/lib/demo.php'), "'engine_db_unify'"));
t('Oberfläche: Engine zeigt die gemeinsame Datenbank (keine zweite Verbindungsmaske), Zusammenführen-Knopf', str_contains($js, 'overview') && str_contains($js, 'dbUnify') && str_contains($js, 'Gemeinsame Datenbank'));
foreach ([$t1, $t2, $t3, $t4] as $x) { rmrf($x); }
echo $fail ? "$fail von $n fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n"; exit($fail ? 1 : 0);

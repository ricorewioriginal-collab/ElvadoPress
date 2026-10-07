<?php
declare(strict_types=1);
// scripts/test-sites.php – Websites (Multisite): Registry, Domain-Zuordnung, Anlegen/Kopieren, Pfad-Auflösung (Aufruf: php scripts/test-sites.php)
$tmp = sys_get_temp_dir() . '/ep-sites-' . bin2hex(random_bytes(4));
mkdir($tmp . '/data', 0775, true); mkdir($tmp . '/media', 0775, true); mkdir($tmp . '/generated', 0775, true);
define('RRW_SITES_BASE', $tmp);
require_once dirname(__DIR__) . '/cms/lib/sites.php';
$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
function thrown(callable $f): string { try { $f(); } catch (InvalidArgumentException $e) { return $e->getMessage(); } return ''; }

// 1) Ohne Registry: alles wie bisher
t('Ohne Registry keine weiteren Websites', rrw_sites_registry() === [] && rrw_site_for_host('beispiel.de') === '');
t('Hauptwebsite: Ordner unverändert', rrw_site_dir('data') === $tmp . '/data' && rrw_site_dir('media') === $tmp . '/media' && rrw_site_dir('generated') === $tmp . '/generated');
t('Unbekannte Ordner-Art wird abgelehnt', thrown(fn() => null) === '' && (function () { try { rrw_site_dir('users'); } catch (InvalidArgumentException) { return true; } return false; })());

// 2) Kennungen und Domains
t('Kennung bereinigt', rrw_site_id_clean(' Mein Blog!! ') === 'mein-blog' && rrw_site_id_clean('../../etc') === 'etc' && rrw_site_id_clean(str_repeat('a', 50)) === str_repeat('a', 30));
t('Domain bereinigt', rrw_site_domain_clean('https://Mein-Blog.DE/pfad') === 'mein-blog.de' && rrw_site_domain_clean('kein domain') === '' && rrw_site_domain_clean('x.de:8080') === 'x.de');

// 3) Website anlegen
file_put_contents($tmp . '/data/site.json', json_encode(['portal' => ['site_name' => 'Haupt']]));
file_put_contents($tmp . '/data/news.json', '[{"id":1}]');
file_put_contents($tmp . '/data/local-auth.local.php', '<?php return ["secret"];');
file_put_contents($tmp . '/data/activity-log.json', '[]');
mkdir($tmp . '/data/layouts', 0775, true); file_put_contents($tmp . '/data/layouts/home.json', '{}');
mkdir($tmp . '/data/.wp', 0775, true); file_put_contents($tmp . '/data/.wp/front-on', '1');
file_put_contents($tmp . '/media/bild.png', 'PNG'); file_put_contents($tmp . '/generated/index.html', '<html>');
$r = rrw_site_create(['name' => 'Mein Blog', 'domains' => ['www.Mein-Blog.de', 'mein-blog.de'], 'enabled' => true]);
t('Anlegen: Kennung aus dem Namen, Domains bereinigt (ohne Doppelte)', $r['site']['id'] === 'mein-blog' && $r['site']['domains'] === ['www.mein-blog.de', 'mein-blog.de'] && $r['copied'] === 0);
t('Anlegen: Ordner data/media/generated vorhanden, data geschützt', is_dir($tmp . '/sites/mein-blog/data') && is_dir($tmp . '/sites/mein-blog/media') && is_dir($tmp . '/sites/mein-blog/generated') && is_file($tmp . '/sites/mein-blog/data/.htaccess'));
t('Registry gespeichert und lesbar', count(rrw_sites_registry(true)) === 1 && rrw_site_exists('mein-blog'));
t('Hauptwebsite bleibt unverändert', rrw_site_dir('data', '') === $tmp . '/data' && file_get_contents($tmp . '/data/news.json') === '[{"id":1}]');

// 4) Domain → Website
t('Domain mit/ohne www wird der Website zugeordnet', rrw_site_for_host('mein-blog.de') === 'mein-blog' && rrw_site_for_host('WWW.Mein-Blog.de:443') === 'mein-blog');
t('Unbekannte Domain gehört zur Hauptwebsite', rrw_site_for_host('haupt.de') === '' && rrw_site_for_host('') === '');
t('Pfad einer Website', rrw_site_dir('data', 'mein-blog') === $tmp . '/sites/mein-blog/data');
$GLOBALS['RRW_SITE_ID'] = null; unset($GLOBALS['RRW_SITE_ID']);
t('CLI: ohne Kontext Hauptwebsite', rrw_site_current() === '');
t('Kontext setzen: nur bekannte Websites', rrw_site_use('mein-blog') && rrw_site_current() === 'mein-blog' && rrw_site_dir('data') === $tmp . '/sites/mein-blog/data' && !rrw_site_use('gibt-es-nicht') && rrw_site_current() === 'mein-blog' && rrw_site_use('') && rrw_site_dir('data') === $tmp . '/data');

// 5) Kopie
$c = rrw_site_create(['name' => 'Kopie', 'domains' => ['kopie.example'], 'copy_from' => 'main', 'copy_media' => true]);
$cd = $tmp . '/sites/kopie';
t('Kopie der Hauptwebsite: Einstellungen, Inhalte, Layouts, Medien', is_file($cd . '/data/site.json') && is_file($cd . '/data/news.json') && is_file($cd . '/data/layouts/home.json') && is_file($cd . '/media/bild.png') && is_file($cd . '/generated/index.html') && $c['copied'] >= 5, (string)$c['copied']);
t('Kopie: Anmeldung, Protokoll, versteckte Zustände (.wp nur mit Theme-Aktivierung/Optionen) und Registry gehen nicht mit', !file_exists($cd . '/data/local-auth.local.php') && !file_exists($cd . '/data/activity-log.json') && !file_exists($cd . '/data/.wp/sess') && array_diff(scandir($cd . '/data/.wp') ?: [], ['.', '..', 'front-on', 'options.json']) === [] && !file_exists($cd . '/data/sites.json'));
$c2 = rrw_site_create(['name' => 'Ohne Medien', 'copy_from' => 'kopie']);
t('Kopie einer weiteren Website, Medien nur auf Wunsch', is_file($tmp . '/sites/ohne-medien/data/site.json') && !file_exists($tmp . '/sites/ohne-medien/media/bild.png'));

// 6) Fehlerfälle
t('Doppelte Kennung abgelehnt', str_contains(thrown(fn() => rrw_site_create(['name' => 'Mein Blog'])), 'gibt es schon'));
t('Reservierte Kennung abgelehnt', str_contains(thrown(fn() => rrw_site_create(['id' => 'main', 'name' => 'x'])), 'reserviert') && str_contains(thrown(fn() => rrw_site_create(['id' => 'data'])), 'reserviert'));
t('Leere Eingabe abgelehnt', thrown(fn() => rrw_site_create([])) !== '');
t('Ungültige Domain abgelehnt', str_contains(thrown(fn() => rrw_site_create(['name' => 'D', 'domains' => ['a b']])), 'ungültig'));
t('Domain einer anderen Website abgelehnt', str_contains(thrown(fn() => rrw_site_create(['name' => 'E', 'domains' => ['mein-blog.de']])), 'nur zu einer Website'));
t('Unbekannte Vorlage abgelehnt', str_contains(thrown(fn() => rrw_site_create(['name' => 'F', 'copy_from' => 'gibt-es-nicht'])), 'gibt es nicht'));
t('Ordner-Kollision verhindert', (function () use ($tmp) { @mkdir($tmp . '/sites/kollision', 0775, true); return str_contains(thrown(fn() => rrw_site_create(['name' => 'Kollision'])), 'existiert bereits'); })());
t('Deaktivierte Website wird per Domain nicht ausgeliefert', (function () { $it = rrw_sites_registry(true); $it[0]['enabled'] = false; rrw_sites_save($it); return rrw_site_for_host('mein-blog.de') === ''; })());

// 7) Beschädigte Registry
file_put_contents(rrw_sites_registry_file(), '{kaputt'); rrw_sites_registry(true);
t('Beschädigte Registry: keine weiteren Websites, kein Fehler', rrw_sites_registry(true) === [] && rrw_site_for_host('mein-blog.de') === '');
system('rm -rf ' . escapeshellarg($tmp));
echo $fail ? "$fail von $n fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n"; exit($fail ? 1 : 0);

<?php
// Systemstatus und Update-Übersicht (reine Logik, ohne Netz/WordPress): Einträge, Schweregrade, Erklärungen, Update-Zeilen. Aufruf: php scripts/test-wp-engine-status.php
declare(strict_types=1);
require __DIR__ . '/../cms/src/autoload.php';
use Elvado\Wp\SystemStatus as S;
$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
$by = fn(array $r, string $id) => array_values(array_filter($r['items'], fn($i) => $i['id'] === $id))[0] ?? null;

$ok = ['cms_version' => '1.1.0', 'cms_update' => ['available' => false, 'checked_at' => '2026-10-07T12:00:00+00:00'], 'php' => '8.3.6', 'memory_limit' => 268435456, 'disk_free' => 5 * 1073741824,
    'data_writable' => true, 'content_writable' => true, 'media_writable' => true, 'state_perms_bad' => [], 'engine' => ['mode' => 'off'], 'rest_ok' => true, 'opcache' => true,
    'plugins' => ['native_total' => 4, 'native_active' => 3, 'native_unverified' => 0], 'theme' => 'elvado-baukasten', 'ai_providers' => 1, 'app_builder' => true, 'jobs' => ['migration_failed' => 0, 'migration_partial' => 0, 'overdue_layouts' => 0], 'packs' => ['ricorewi-radio']];
$r = S::build($ok);
t('Gesunde Installation: keine Warnung, kein Fehler', $r['summary']['warn'] === 0 && $r['summary']['fail'] === 0 && $r['summary']['ok'] >= 8, json_encode($r['summary']));
t('Jeder Eintrag hat Gruppe, Beschriftung und deutsche Erklärung', count(array_filter($r['items'], fn($i) => $i['group'] !== '' && $i['label'] !== '' && $i['detail'] !== '' && in_array($i['status'], ['ok', 'warn', 'fail', 'info'], true))) === count($r['items']));
t('Engine aus ist Information, kein Fehler', ($by($r, 'engine')['status'] ?? '') === 'info');

$w = S::build(['php' => '8.0.30'] + $ok);
t('PHP unter 8.1: Fehler mit Handlungshinweis', ($by($w, 'php')['status'] ?? '') === 'fail' && str_contains($by($w, 'php')['detail'], 'Hoster'));
t('Wenig Speicher: Warnung; unbegrenzt: ok', ($by(S::build(['memory_limit' => 67108864] + $ok), 'memory')['status'] ?? '') === 'warn' && ($by(S::build(['memory_limit' => -1] + $ok), 'memory')['status'] ?? '') === 'ok');
t('Festplatte: knapp = Warnung, fast voll = Fehler', ($by(S::build(['disk_free' => 100 * 1048576] + $ok), 'disk')['status'] ?? '') === 'warn' && ($by(S::build(['disk_free' => 10 * 1048576] + $ok), 'disk')['status'] ?? '') === 'fail');
t('Nicht beschreibbarer Datenordner: Fehler', ($by(S::build(['data_writable' => false] + $ok), 'perms')['status'] ?? '') === 'fail');
t('Zu offene Zugangsdatei: Warnung', ($by(S::build(['state_perms_bad' => ['db.json']] + $ok), 'perms')['status'] ?? '') === 'warn');
t('Neue ElvadoPress-Version: Warnung mit Verweis auf Updates', ($e = $by(S::build(['cms_update' => ['available' => true, 'latest' => '1.2.0']] + $ok), 'cms'))['status'] === 'warn' && str_contains($e['detail'], '1.2.0'));
t('REST-Datei fehlt: Fehler', ($by(S::build(['rest_ok' => false] + $ok), 'rest')['status'] ?? '') === 'fail');
t('Unverändertes Plugin nicht mehr geprüft: Warnung', ($by(S::build(['plugins' => ['native_total' => 2, 'native_active' => 2, 'native_unverified' => 1]] + $ok), 'plugins')['status'] ?? '') === 'warn');
t('KI nicht eingerichtet: Information (optional)', ($by(S::build(['ai_providers' => 0] + $ok), 'ai')['status'] ?? '') === 'info');
t('Cron abgeschaltet ohne Server-Cron: Warnung', ($by(S::build(['cron' => ['wp_cron_disabled' => true, 'server_cron' => false]] + $ok), 'cron')['status'] ?? '') === 'warn');
t('Unvollständiger Migrationslauf: Warnung', ($by(S::build(['jobs' => ['migration_partial' => 1]] + $ok), 'jobs')['status'] ?? '') === 'warn');

$act = ['engine' => ['mode' => 'active', 'version' => '7.1.3', 'latest' => '7.1.3', 'db_ok' => true, 'db_server' => 'MariaDB 10.11'], 'wp_updates' => []] + $ok;
$ra = S::build($act);
t('Engine aktiv und aktuell: ok, Datenbank ok', ($by($ra, 'engine')['status'] ?? '') === 'ok' && ($by($ra, 'db')['status'] ?? '') === 'ok');
t('Neuer WordPress-Core: Warnung mit Version', ($x = $by(S::build(['engine' => ['latest' => '7.2.0'] + $act['engine']] + $act), 'engine'))['status'] === 'warn' && str_contains($x['detail'], '7.2.0'));
t('Abgesicherter Modus / Absturz: Warnung mit Erklärung', ($x = $by(S::build(['engine' => ['safe' => true] + $act['engine']] + $act), 'engine'))['status'] === 'warn' && str_contains($x['detail'], 'Abgesicherter Modus'));
t('Datenbank nicht erreichbar: Fehler mit Meldung', ($x = $by(S::build(['engine' => ['db_ok' => false, 'db_message' => 'Zugriff verweigert'] + $act['engine']] + $act), 'db'))['status'] === 'fail' && str_contains($x['detail'], 'Zugriff verweigert'));

$u = S::updates(['native_updates' => [['name' => 'Elvado SEO', 'installed' => '1.0.0', 'latest' => '1.1.0']], 'wp_updates' => ['plugins' => [['name' => 'X', 'installed' => '1', 'latest' => '2']], 'themes' => [], 'checked_at' => '2026-10-07'], 'engine' => ['latest' => '7.2.0'] + $act['engine'], 'cms_update' => ['available' => true, 'latest' => '1.2.0'], 'cms_version' => '1.1.0', 'packs' => ['ricorewi-radio']]);
$id = array_column($u, null, 'id');
t('Update-Center: getrennt für Core, WordPress, ElvadoPress-Plugins, WP-Plugins, WP-Themes, Pakete', array_keys($id) === ['cms', 'wordpress', 'native_plugins', 'wp_plugins', 'wp_themes', 'packs']);
t('Updates erkannt je Art', $id['cms']['available'] && $id['wordpress']['available'] && $id['native_plugins']['available'] && $id['wp_plugins']['available'] && !$id['wp_themes']['available'] && !$id['packs']['available']);
t('Einzelne Plugins mit Versionen aufgeführt', $id['native_plugins']['items'][0]['latest'] === '1.1.0' && $id['wp_plugins']['items'][0]['name'] === 'X');
t('Ohne aktive Engine: WordPress-Plugins/-Themes nennen die Voraussetzung', str_contains(S::updates($ok)[3]['detail'], 'aktiver Engine'));
echo $fail ? "$fail von $n Prüfungen fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n";
exit($fail ? 1 : 0);

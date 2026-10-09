<?php
// Sichtbarkeits-Check: warum erscheinen Seiten, Beiträge und Menüpunkte (nicht)? Aufruf: php scripts/test-visibility.php
declare(strict_types=1);
$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
require __DIR__ . '/../cms/lib/visibility.php';
$now = strtotime('2026-10-07 12:00:00');
$site = ['pages' => [
    ['id' => 'a', 'slug' => 'a', 'title' => 'Aktiv im Menü', 'type' => 'custom', 'enabled' => true],
    ['id' => 'b', 'slug' => 'b', 'title' => 'Aus', 'type' => 'custom', 'enabled' => false],
    ['id' => 'c', 'slug' => 'c', 'title' => 'Geplant', 'type' => 'custom', 'enabled' => true, 'publish_at' => '2026-12-01 10:00'],
    ['id' => 'd', 'slug' => 'd', 'title' => 'Ohne Menü', 'type' => 'custom', 'enabled' => true],
    ['id' => 'e', 'slug' => 'start', 'title' => 'Start', 'type' => 'system', 'system_target' => 'start', 'enabled' => true],
    ['id' => 'f', 'slug' => 'f', 'title' => 'Altes Untermenü', 'type' => 'custom', 'enabled' => true],
], 'menus' => ['top' => [
    ['id' => 'm1', 'label' => 'A', 'target' => 'page:a', 'enabled' => true, 'parent_id' => ''],
    ['id' => 'm2', 'label' => 'Start', 'target' => 'system:start', 'enabled' => true, 'parent_id' => ''],
    ['id' => 'm3', 'label' => 'Zu B', 'target' => 'page:b', 'enabled' => true, 'parent_id' => ''],
    ['id' => 'm4', 'label' => 'Ins Nichts', 'target' => 'page:gibtsnicht', 'enabled' => true, 'parent_id' => ''],
    ['id' => 'm5', 'label' => 'Obermenü aus', 'target' => 'page:x', 'enabled' => false, 'parent_id' => ''],
    ['id' => 'm6', 'label' => 'Kind', 'target' => 'page:f', 'enabled' => true, 'parent_id' => 'm5'],
], 'bottom' => []]];
$news = [
    ['id' => 1, 'title' => 'Live', 'status' => 'published', 'published_at' => '2026-01-01 10:00:00'],
    ['id' => 2, 'title' => 'Entwurf', 'status' => 'draft'],
    ['id' => 3, 'title' => 'Geplant', 'status' => 'published', 'published_at' => '2027-01-01 10:00:00'],
    ['id' => 4, 'title' => 'Papierkorb', 'status' => 'published', 'deleted_at' => '2026-05-01 10:00:00'],
];
$r = elvado_visibility_report($site, $news, $now);
$find = fn(string $kind, string $id) => array_values(array_filter($r['items'], fn($i) => $i['kind'] === $kind && $i['id'] === $id))[0] ?? null;
t('Aktive Seite im Menü: kein Eintrag', $find('page', 'a') === null);
t('Systemseite im Menü: kein Eintrag', $find('page', 'e') === null);
t('Deaktivierte Seite: nicht sichtbar', ($find('page', 'b')['level'] ?? '') === 'blocker');
t('Geplante Seite: nicht sichtbar mit Datum', str_contains($find('page', 'c')['problem'] ?? '', '01.12.2026'));
t('Seite ohne Menü: nur Hinweis', ($find('page', 'd')['level'] ?? '') === 'info');
t('Seite nur unter ausgeblendetem Obermenü: Hinweis „nicht im Menü“', ($find('page', 'f')['level'] ?? '') === 'info');
t('Menüpunkt auf nicht vorhandene Seite: nicht sichtbar', ($find('menu', 'm4')['level'] ?? '') === 'blocker');
t('Menüpunkt auf deaktivierte Seite: nicht sichtbar', ($find('menu', 'm3')['level'] ?? '') === 'blocker');
t('Ausgeblendetes Obermenü wird erklärt', str_contains($find('menu', 'm5')['problem'] ?? '', 'ausgeblendet'));
t('Kind unter ausgeblendetem Obermenü wird erklärt', str_contains($find('menu', 'm6')['problem'] ?? '', 'Obermenü'));
t('Beitrag live: kein Eintrag', $find('post', '1') === null);
t('Entwurf, geplant und Papierkorb werden erklärt', ($find('post', '2')['level'] ?? '') === 'blocker' && str_contains($find('post', '3')['problem'] ?? '', '01.01.2027') && ($find('post', '4')['level'] ?? '') === 'info');
t('Zusammenfassung zählt', $r['summary']['pages'] === 6 && $r['summary']['posts'] === 4 && $r['summary']['blocker'] >= 5);
t('Leere/kaputte Daten: kein Absturz', elvado_visibility_report([], [])['summary']['blocker'] === 0 && elvado_visibility_report(['pages' => 'x', 'menus' => 5], [1, 'a'])['summary']['pages'] === 0);
// Seiten dürfen beim Speichern nicht stillschweigend verschwinden
$pub = (string)file_get_contents(__DIR__ . '/../cms/lib/publish.php');
t('Seiten-Bereinigung: unbekannte Systemseiten bleiben erhalten, Grenze 300', str_contains($pub, 'unbekannte Systemseiten bleiben erhalten') && str_contains($pub, 'array_slice((array)$value,0,300) as $p){'));
t('API und Verwaltung eingebunden', str_contains((string)file_get_contents(__DIR__ . '/../cms/api.php'), "'visibility_report'") && str_contains((string)file_get_contents(__DIR__ . '/../cms/views/panel-pages.php'), 'data-vischeck') && str_contains((string)file_get_contents(__DIR__ . '/../cms/index.php'), 'visibility-check.js'));
echo $fail ? "$fail von $n Prüfungen fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n";
exit($fail ? 1 : 0);

<?php
// Sichtbarkeits-Check auf der Kommandozeile: warum erscheinen Seiten, Beiträge oder Menüpunkte (nicht)?
// Aufruf: php scripts/visibility-report.php [<Datenordner>]   (Standard: cms/data; liest nur site.json und news.json, ändert nichts)
declare(strict_types=1);
require __DIR__ . '/../cms/lib/visibility.php';
$dir = rtrim($argv[1] ?? __DIR__ . '/../cms/data', '/');
$rd = static function (string $f): array { $j = is_file($f) ? json_decode((string)file_get_contents($f), true) : null; return is_array($j) ? $j : []; };
$r = rrw_visibility_report($rd($dir . '/site.json'), $rd($dir . '/news.json'));
echo "Seiten: {$r['summary']['pages']} · Beiträge: {$r['summary']['posts']} · nicht sichtbar: {$r['summary']['blocker']} · Hinweise: {$r['summary']['info']}\n";
foreach ($r['items'] as $i) {
    echo ($i['level'] === 'blocker' ? '✗ ' : '· ') . "[{$i['kind']}] {$i['title']} ({$i['id']}): {$i['problem']} → {$i['hint']}\n";
}
exit($r['summary']['blocker'] > 0 ? 1 : 0);

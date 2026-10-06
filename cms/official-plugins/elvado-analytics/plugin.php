<?php
declare(strict_types=1);
if (!isset($np) || !($np instanceof \Elvado\Plugin\Context)) {
    http_response_code(403);   // direkter Aufruf der Datei im Browser: nichts ausführen
    exit;
}
// Elvado Analytics – Einstiegspunkt (nur als offizielles, unverändertes Plugin ausgeführt).
require_once __DIR__ . '/lib/Stats.php';

use ElvadoPlugin\Analytics\Stats;

/** @var \Elvado\Plugin\Context $np */
$st = new Stats($np);

$np->on('front_response', [$st, 'count']);
$np->on('front_output', [$st, 'inject'], 60);
$np->on('tick', [$st, 'tick']);

$np->api('overview', function () use ($st, $np): array {
    $b = [];
    $ext = $st->external();
    if ($np->setting('internal') === false) {
        $b[] = ['type' => 'notice', 'level' => 'info', 'text' => 'Die interne Statistik ist ausgeschaltet.'];
    }
    $days = $st->days(30);
    $today = $st->days(1);
    $d7 = Stats::sum(array_slice($days, -7, 7, true));
    $d30 = Stats::sum($days);
    $t = Stats::sum($today);
    $b[] = ['type' => 'stats', 'items' => [
        ['label' => 'Heute: Aufrufe', 'value' => $t['v']], ['label' => 'Heute: Besucher', 'value' => $t['u']],
        ['label' => '7 Tage: Aufrufe', 'value' => $d7['v']], ['label' => '30 Tage: Aufrufe', 'value' => $d30['v']],
    ]];
    $b[] = ['type' => 'bars', 'title' => 'Aufrufe der letzten 14 Tage', 'items' => array_map(static fn($d, $x) => ['label' => date('d.m.', strtotime($d)), 'value' => (int)$x['v']], array_keys(array_slice($days, -14, 14, true)), array_values(array_slice($days, -14, 14, true)))];
    $rows = [];
    foreach (array_slice($d30['p'], 0, 15, true) as $p => $n) {
        $rows[] = [(string)$p, (string)$n];
    }
    $b[] = ['type' => 'table', 'title' => 'Beliebteste Seiten (30 Tage)', 'columns' => ['Seite', 'Aufrufe'], 'rows' => $rows, 'empty' => 'Noch keine Aufrufe gezählt.'];
    $rows = [];
    foreach (array_slice($d30['r'], 0, 10, true) as $p => $n) {
        $rows[] = [(string)$p, (string)$n];
    }
    $b[] = ['type' => 'table', 'title' => 'Herkunft (30 Tage)', 'columns' => ['Quelle', 'Aufrufe'], 'rows' => $rows, 'empty' => 'Keine Daten.'];
    $names = ['desktop' => 'Computer', 'mobile' => 'Smartphone', 'tablet' => 'Tablet', 'app' => 'App'];
    $b[] = ['type' => 'bars', 'title' => 'Geräte (30 Tage)', 'items' => array_map(static fn($k, $v) => ['label' => $names[$k] ?? $k, 'value' => (int)$v], array_keys($d30['d']), array_values($d30['d']))];
    $b[] = ['type' => 'checks', 'title' => 'Datenschutz und externe Dienste', 'items' => [
        ['label' => 'Interne Statistik', 'status' => 'ok', 'text' => 'Ohne Cookies, ohne gespeicherte IP-Adressen; Besucher nur als täglich wechselnder Hash, der nach dem Tag gelöscht wird.'],
        ['label' => 'Matomo', 'status' => $ext['matomo'] ? 'warn' : 'ok', 'text' => $ext['matomo'] ? 'Aktiv für ' . $ext['matomo']['url'] . ' (Website-ID ' . $ext['matomo']['id'] . '). Datenschutzerklärung beachten.' : 'Aus.'],
        ['label' => 'Google Analytics', 'status' => $ext['ga'] ? 'warn' : 'ok', 'text' => $ext['ga'] ? 'Aktiv (' . $ext['ga'] . '). Datenschutzerklärung und Einwilligung beachten.' : 'Aus.'],
    ]];
    if (!$np->setting('ack') && (trim((string)$np->setting('ga_id')) !== '' || (int)$np->setting('matomo_site_id') > 0)) {
        $b[] = ['type' => 'notice', 'level' => 'warn', 'text' => 'Ein externer Dienst ist eingetragen, wird aber nicht ausgeliefert: Bitte erst „Ich habe die Datenschutzerklärung angepasst“ bestätigen.'];
    }
    return ['blocks' => $b];
});
$np->api('action_purge', function () use ($st): array { $st->purge(); return ['ok' => true, 'message' => 'Alle Statistikdaten gelöscht.']; });

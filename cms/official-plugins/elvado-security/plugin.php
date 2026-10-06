<?php
declare(strict_types=1);
// Elvado Security – Einstiegspunkt. Läuft nur als offizielles, unverändertes Plugin (siehe cms/src/Plugin/PluginManager.php).
require_once __DIR__ . '/lib/Guard.php';

use ElvadoPlugin\Security\Guard;

/** @var \Elvado\Plugin\Context $np */
$g = new Guard($np);

$np->on('boot', [$g, 'sendHeaders'], 1);
$np->on('login_check', [$g, 'loginCheck']);
$np->on('login_result', [$g, 'loginResult']);
$np->on('tick', [$g, 'tick']);

$np->api('overview', function () use ($g, $np): array {
    $b = [];
    $log = $g->logEntries(1000);
    $fails24 = count(array_filter($log, static fn($e) => empty($e['ok']) && ($e['t'] ?? '') >= date('Y-m-d H:i:s', time() - 86400)));
    $ok24 = count(array_filter($log, static fn($e) => !empty($e['ok']) && ($e['t'] ?? '') >= date('Y-m-d H:i:s', time() - 86400)));
    $b[] = ['type' => 'stats', 'items' => [
        ['label' => 'Fehlversuche (24 h)', 'value' => $fails24, 'level' => $fails24 > 20 ? 'warn' : 'ok'],
        ['label' => 'Anmeldungen (24 h)', 'value' => $ok24],
        ['label' => 'Aktive Sperren', 'value' => $g->lockoutCount()],
    ]];
    $b[] = ['type' => 'checks', 'title' => 'Sicherheitsübersicht', 'items' => $g->checks()];
    $bi = $g->baselineInfo();
    $b[] = ['type' => 'text', 'title' => 'Dateiänderungs-Prüfung', 'text' => $bi ? 'Prüf-Basis vom ' . date('d.m.Y H:i', strtotime($bi['created']) ?: time()) . ' (' . $bi['count'] . ' Dateien, Version ' . $bi['version'] . '). Mit „Dateien jetzt prüfen“ vergleichst du den aktuellen Stand.' : 'Noch keine Prüf-Basis. Erstelle sie, wenn die Website in einem Zustand ist, dem du vertraust – spätere Änderungen an PHP-Dateien werden dann gemeldet.'];
    $rows = [];
    foreach (array_slice($g->logEntries(30), 0, 30) as $e) {
        $rows[] = [(string)$e['t'], (string)$e['u'], !empty($e['ok']) ? 'erfolgreich' : 'fehlgeschlagen', (string)$e['ip']];
    }
    $b[] = ['type' => 'table', 'title' => 'Letzte Anmeldungen', 'columns' => ['Zeit', 'Benutzer', 'Ergebnis', 'Adresse (gekürzt)'], 'rows' => $rows, 'empty' => 'Noch keine Anmeldungen protokolliert.'];
    return ['blocks' => $b];
});
$np->api('action_integrity_baseline', fn() => $g->baseline());
$np->api('action_integrity_check', function () use ($g): array {
    $r = $g->check();
    $lines = array_merge(array_map(fn($f) => 'geändert: ' . $f, $r['changed']), array_map(fn($f) => 'neu: ' . $f, $r['added']), array_map(fn($f) => 'fehlt: ' . $f, $r['removed']));
    return ['ok' => $r['ok'], 'message' => $r['message'], 'details' => $lines];
});
$np->api('action_access_test', fn() => $g->accessTest());
$np->api('action_clear_lockouts', function () use ($g): array { $g->clearLockouts(); return ['ok' => true, 'message' => 'Alle Sperren aufgehoben.']; });
$np->api('action_clear_log', function () use ($g): array { $g->clearLog(); return ['ok' => true, 'message' => 'Login-Protokoll geleert.']; });

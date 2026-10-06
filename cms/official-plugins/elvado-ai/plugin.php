<?php
declare(strict_types=1);
if (!isset($np) || !($np instanceof \Elvado\Plugin\Context)) {
    http_response_code(403);   // direkter Aufruf der Datei im Browser: nichts ausführen
    exit;
}
// Elvado AI – Einstiegspunkt (nur als offizielles, unverändertes Plugin ausgeführt). Alle Anbieter, Schlüssel und Modelle kommen aus der KI-Zentrale des Core.
require_once __DIR__ . '/lib/Ai.php';

use ElvadoPlugin\Ai\Ai;

/** @var \Elvado\Plugin\Context $np */
$ai = new Ai($np);
$who = static fn(): string => (string)($GLOBALS['rrw_np_user'] ?? 'plugin');

$np->api('status', function () use ($ai, $np): array {
    return $ai->status() + ['editor_tools' => (bool)$np->setting('editor_tools'), 'app_tools' => (bool)$np->setting('app_tools'), 'text_actions' => Ai::TEXT_ACTIONS];
}, 'editor');
$np->api('text', function (array $a) use ($ai, $np, $who): array {
    if (!$np->setting('editor_tools')) {
        return ['ok' => false, 'message' => 'Die KI-Werkzeuge im Editor sind in den Plugin-Einstellungen ausgeschaltet.'];
    }
    return $ai->text((string)($a['action'] ?? ''), (string)($a['text'] ?? ''), (string)($a['extra'] ?? ''), $who());
}, 'editor');
$np->api('seo', function (array $a) use ($ai, $np, $who): array {
    if (!$np->setting('editor_tools')) {
        return ['ok' => false, 'message' => 'Die KI-Werkzeuge im Editor sind in den Plugin-Einstellungen ausgeschaltet.'];
    }
    return $ai->seo((string)($a['title'] ?? ''), (string)($a['text'] ?? ''), $who());
}, 'editor');
$np->api('app_tabs', function (array $a) use ($ai, $np, $who): array {
    return $np->setting('app_tools') ? $ai->appTabs((string)($a['app'] ?? ''), (string)($a['brief'] ?? ''), (array)($a['pages'] ?? []), $who()) : ['ok' => false, 'message' => 'Die KI-Vorschläge im App-Bereich sind ausgeschaltet.'];
});
$np->api('app_notice', fn(array $a) => $np->setting('app_tools') ? $ai->appNotice((string)($a['app'] ?? ''), (string)($a['brief'] ?? ''), $who()) : ['ok' => false, 'message' => 'Die KI-Vorschläge im App-Bereich sind ausgeschaltet.']);
$np->api('app_store', fn(array $a) => $np->setting('app_tools') ? $ai->appStoreTexts((string)($a['app'] ?? ''), (string)($a['brief'] ?? ''), $who()) : ['ok' => false, 'message' => 'Die KI-Vorschläge im App-Bereich sind ausgeschaltet.']);
$np->api('overview', function () use ($ai): array {
    $s = $ai->status();
    $b = [];
    if (!$s['usable']) {
        $b[] = ['type' => 'notice', 'level' => 'warn', 'text' => 'Noch kein KI-Anbieter nutzbar. Trage in der KI-Zentrale einen API-Schlüssel ein (oder aktiviere einen Anbieter ohne Schlüssel) – bis dahin bleiben die KI-Werkzeuge ohne Funktion.'];
    }
    $rows = array_map(static fn($p) => [$p['label'], $p['model'], $p['free'] ? 'kostenlos' : 'Schlüssel hinterlegt'], $s['providers']);
    $b[] = ['type' => 'table', 'title' => 'Nutzbare Anbieter (aus der KI-Zentrale)', 'columns' => ['Anbieter', 'Modell', 'Zugang'], 'rows' => $rows, 'empty' => 'Keiner.'];
    $b[] = ['type' => 'notice', 'level' => 'info', 'text' => 'Datenschutz: KI-Anbieter sind externe Dienste. Gesendet wird nur der Text, den du im Editor bzw. App-Bereich bewusst an die KI schickst – keine Schlüssel, keine Benutzerdaten. Anbieter, Modelle und Schlüssel stellst du in der KI-Zentrale ein.'];
    return ['blocks' => $b];
});

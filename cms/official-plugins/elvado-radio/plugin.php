<?php
declare(strict_types=1);
if (!isset($np) || !($np instanceof \Elvado\Plugin\Context)) {
    http_response_code(403);   // direkter Aufruf der Datei im Browser: nichts ausführen
    exit;
}
// Elvado Radio / Audio – Einstiegspunkt (nur als offizielles, unverändertes Plugin ausgeführt).
require_once __DIR__ . '/lib/Radio.php';

use ElvadoPlugin\Radio\Radio;

/** @var \Elvado\Plugin\Context $np */

// Shortcode für Seiten, Beiträge und Widgets: [elvado_radio station="kennung"] oder [elvado_radio url="https://…" title="Name"]
$np->on('wp_ready', function () use ($np): void {
    add_shortcode('elvado_radio', static function ($atts) use ($np): string {
        [$stations] = Radio::parse((string)$np->setting('stations'));
        $a = shortcode_atts(['station' => '', 'url' => '', 'title' => '', 'logo' => ''], is_array($atts) ? $atts : []);
        return Radio::render($stations, $a, (bool)$np->setting('show_title'));
    });
});

$np->api('overview', function () use ($np): array {
    [$stations, $notes] = Radio::parse((string)$np->setting('stations'));
    $rows = [];
    foreach ($stations as $s) {
        $rows[] = [$s['name'], $s['id'], '[elvado_radio station="' . $s['id'] . '"]'];
    }
    $b = [['type' => 'stats', 'items' => [['label' => 'Sender', 'value' => count($stations)], ['label' => 'Fehlerhafte Zeilen', 'value' => count($notes), 'level' => $notes ? 'warn' : 'ok']]]];
    foreach ($notes as $n) {
        $b[] = ['type' => 'notice', 'level' => 'warn', 'text' => $n];
    }
    $b[] = ['type' => 'table', 'title' => 'Sender', 'columns' => ['Name', 'Kennung', 'Shortcode'], 'rows' => $rows, 'empty' => 'Noch kein Sender – unter „Einstellungen“ eintragen.'];
    $b[] = ['type' => 'text', 'text' => 'Der Stream wird erst nach dem Klick auf „Abspielen“ geladen. Es werden keine Daten an Dritte gesendet und kein Fremd-Skript eingebunden. Direkt ohne Senderliste: [elvado_radio url="https://…" title="Name"].'];
    return ['blocks' => $b];
});

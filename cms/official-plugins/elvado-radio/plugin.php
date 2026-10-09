<?php
declare(strict_types=1);
if (!isset($np) || !($np instanceof \Elvado\Plugin\Context)) {
    http_response_code(403);   // direkter Aufruf der Datei im Browser: nichts ausführen
    exit;
}
// Elvado Radio / Audio – Einstiegspunkt (nur als offizielles, unverändertes Plugin ausgeführt).
require_once __DIR__ . '/lib/Radio.php';
require_once __DIR__ . '/lib/Schedule.php';

use ElvadoPlugin\Radio\Radio;
use ElvadoPlugin\Radio\Schedule;

/** @var \Elvado\Plugin\Context $np */

// Shortcode für Seiten, Beiträge und Widgets: [elvado_radio station="kennung"] oder [elvado_radio url="https://…" title="Name"]
$np->on('wp_ready', function () use ($np): void {
    add_shortcode('elvado_radio', static function ($atts) use ($np): string {
        [$stations] = Radio::parse((string)$np->setting('stations'));
        $a = shortcode_atts(['station' => '', 'url' => '', 'title' => '', 'logo' => ''], is_array($atts) ? $atts : []);
        return Radio::render($stations, $a, (bool)$np->setting('show_title'));
    });
    // Sendeplan und „Jetzt läuft“: [elvado_radio_schedule station="kennung"], [elvado_radio_now station="kennung"] (station optional)
    add_shortcode('elvado_radio_schedule', static function ($atts) use ($np): string {
        [$entries] = Schedule::parse((string)$np->setting('schedule'));
        $a = shortcode_atts(['station' => ''], is_array($atts) ? $atts : []);
        return Schedule::renderTable($entries, strtolower(trim((string)$a['station'])));
    });
    add_shortcode('elvado_radio_now', static function ($atts) use ($np): string {
        [$entries] = Schedule::parse((string)$np->setting('schedule'));
        $a = shortcode_atts(['station' => ''], is_array($atts) ? $atts : []);
        return Schedule::renderNow($entries, new \DateTimeImmutable('now'), strtolower(trim((string)$a['station'])));
    });
});

$np->api('overview', function () use ($np): array {
    [$stations, $notes] = Radio::parse((string)$np->setting('stations'));
    [$entries, $planNotes] = Schedule::parse((string)$np->setting('schedule'));
    $notes = array_merge($notes, $planNotes);
    $rows = [];
    foreach ($stations as $s) {
        $rows[] = [$s['name'], $s['id'], '[elvado_radio station="' . $s['id'] . '"]'];
    }
    $b = [['type' => 'stats', 'items' => [['label' => 'Sender', 'value' => count($stations)], ['label' => 'Sendungen im Plan', 'value' => count($entries)], ['label' => 'Fehlerhafte Zeilen', 'value' => count($notes), 'level' => $notes ? 'warn' : 'ok']]]];
    foreach ($notes as $n) {
        $b[] = ['type' => 'notice', 'level' => 'warn', 'text' => $n];
    }
    $b[] = ['type' => 'table', 'title' => 'Sender', 'columns' => ['Name', 'Kennung', 'Shortcode'], 'rows' => $rows, 'empty' => 'Noch kein Sender – unter „Einstellungen“ eintragen.'];
    $b[] = ['type' => 'text', 'text' => 'Der Stream wird erst nach dem Klick auf „Abspielen“ geladen. Es werden keine Daten an Dritte gesendet und kein Fremd-Skript eingebunden. Direkt ohne Senderliste: [elvado_radio url="https://…" title="Name"]. Sendeplan: [elvado_radio_schedule], aktuelle Sendung: [elvado_radio_now] (optional mit station="kennung").'];
    return ['blocks' => $b];
});

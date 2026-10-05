<?php
declare(strict_types=1);
// cms/update-health.php – Gesundheitsprüfung nach einem Update (nur Kommandozeile; wird vom Update-Dienst in einem eigenen PHP-Prozess gestartet).
// Startet das (neue) CMS wirklich: lädt cms/api.php mit der öffentlichen Aktion „update_ping“ und prüft Antwort und Version. Gibt eine JSON-Zeile aus.
// Aufruf: php update-health.php <erwartete-Version>
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$expected = (string)($argv[1] ?? '');
$_GET['action'] = 'update_ping';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
ob_start(static function (string $body) use ($expected): string {
    $j = json_decode($body, true);
    if (!is_array($j) || ($j['status'] ?? '') !== 'ok') {
        return json_encode(['ok' => false, 'message' => 'Unerwartete Antwort: ' . mb_substr(trim(strip_tags($body)), 0, 160)]) . "\n";
    }
    if ($expected !== '' && ($j['version'] ?? '') !== $expected) {
        return json_encode(['ok' => false, 'message' => 'Version ' . ($j['version'] ?? '?') . ' statt ' . $expected]) . "\n";
    }
    return json_encode(['ok' => true, 'version' => $j['version'] ?? '']) . "\n";
});
require __DIR__ . '/api.php';

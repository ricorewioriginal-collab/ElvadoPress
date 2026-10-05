<?php
declare(strict_types=1);
// cms/update-webhook.php – Webhook für automatische Update-Suche. In GitHub (Repository → Settings → Webhooks):
// Payload URL https://<deine-domain>/cms/update-webhook.php, Content type application/json, Secret = Geheimnis aus dem CMS (System → Version & Update),
// Ereignisse: „Releases“ (neue Version), optional „Pushes“ (Kanal „main“) und „Branch or tag creation“. Die Signatur (HMAC-SHA256) wird geprüft.
// Danach: neueste Version suchen und – nur bei aktivierter Automatik – einspielen (mit Gesundheitsprüfung und automatischem Rückschritt).
require_once __DIR__ . '/src/autoload.php';

use Elvado\Support\RateLimiter;
use Elvado\Update\UpdateService;

$dataDir = __DIR__ . '/data';
$svc = UpdateService::forCms(__DIR__, $dataDir);
$raw = (string)file_get_contents('php://input', false, null, 0, 1_048_577);
if (!(new RateLimiter($dataDir . '/.update/ratelimit'))->hit('upd-webhook:' . (string)($_SERVER['REMOTE_ADDR'] ?? ''), 30, 60)) {
    $res = ['status' => 429, 'body' => ['status' => 'error', 'message' => 'Zu viele Anfragen.'], 'run' => false];
} else {
    $res = $svc->handleWebhook($_SERVER, $raw);
}
http_response_code($res['status']);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$json = (string)json_encode($res['body'], JSON_UNESCAPED_UNICODE);
header('Content-Length: ' . strlen($json));
echo $json;
if ($res['run']) {
    ignore_user_abort(true);
    @set_time_limit(300);
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        @ob_end_flush();
        @flush();
    }
    try {
        $svc->automatic();
    } catch (Throwable $e) {
        error_log('ElvadoPress Update-Webhook: ' . get_class($e) . ': ' . $e->getMessage());
    }
}

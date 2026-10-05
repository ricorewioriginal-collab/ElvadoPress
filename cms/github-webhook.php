<?php
declare(strict_types=1);
// cms/github-webhook.php – Webhook-Endpunkt für das verknüpfte GitHub-Repository (Lovable „Publish“ → Push auf GitHub → diese Adresse).
// In GitHub: Repository → Settings → Webhooks → Payload URL = https://<deine-domain>/cms/github-webhook.php, Content type application/json,
// Secret = das im CMS (Menü „KI & Lovable“) erzeugte Geheimnis, Ereignis „Just the push event“. Die Signatur (HMAC-SHA256) wird geprüft.
// Logik: cms/src/GitHub/WebhookController.php und GitHubSyncService.php.
require_once __DIR__ . '/src/autoload.php';

use Elvado\GitHub\GitHubSyncException;
use Elvado\GitHub\GitHubSyncService;
use Elvado\GitHub\WebhookController;
use Elvado\Lovable\LovableSettings;
use Elvado\Support\RateLimiter;

$dataDir = __DIR__ . '/data';
$service = new GitHubSyncService(LovableSettings::load($dataDir), __DIR__ . '/frontend/lovable', $dataDir);
$raw = (string)file_get_contents('php://input', false, null, 0, 1_048_577);   // höchstens 1 MB + 1 Byte (Überlänge wird abgelehnt)
$result = (new WebhookController($service, new RateLimiter($dataDir . '/.lovable/ratelimit')))->handle($_SERVER, $raw);

http_response_code($result['status']);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$json = (string)json_encode($result['body'], JSON_UNESCAPED_UNICODE);
header('Content-Length: ' . strlen($json));
echo $json;

if ($result['run_sync']) {
    // Antwort sofort abschließen, die Synchronisation läuft danach weiter
    ignore_user_abort(true);
    @set_time_limit(180);
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        @ob_end_flush();
        @flush();
    }
    try {
        $service->sync();
    } catch (GitHubSyncException $e) {
        error_log('ElvadoPress GitHub-Sync: ' . $e->getMessage());
    } catch (Throwable $e) {
        error_log('ElvadoPress GitHub-Sync unerwartet: ' . get_class($e));
    }
}

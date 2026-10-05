<?php
declare(strict_types=1);
// cms/src/GitHub/WebhookController.php
//
// Logik des GitHub-Webhook-Endpunkts (cms/github-webhook.php): Methode, Ratenbegrenzung, Kopfzeilen → GitHubSyncService.
// Die eigentliche Synchronisation läuft nach der Antwort (GitHub erwartet innerhalb weniger Sekunden eine Antwort).

namespace Elvado\GitHub;

use Elvado\Support\RateLimiter;

final class WebhookController
{
    public function __construct(
        private readonly GitHubSyncService $sync,
        private readonly ?RateLimiter $limiter = null,
    ) {
    }

    /**
     * @param array<string,mixed> $server $_SERVER
     * @return array{status:int,body:array<string,mixed>,run_sync:bool}
     */
    public function handle(array $server, string $rawBody): array
    {
        if (strtoupper((string)($server['REQUEST_METHOD'] ?? '')) !== 'POST') {
            return ['status' => 405, 'body' => ['status' => 'error', 'message' => 'Nur POST ist erlaubt.'], 'run_sync' => false];
        }
        if ($this->limiter !== null && !$this->limiter->hit('gh-webhook:' . (string)($server['REMOTE_ADDR'] ?? ''), 60, 60)) {
            return ['status' => 429, 'body' => ['status' => 'error', 'message' => 'Zu viele Anfragen.'], 'run_sync' => false];
        }
        $headers = [];
        foreach (['HTTP_X_HUB_SIGNATURE_256' => 'x-hub-signature-256', 'HTTP_X_GITHUB_EVENT' => 'x-github-event', 'HTTP_X_GITHUB_DELIVERY' => 'x-github-delivery'] as $k => $name) {
            $headers[$name] = (string)($server[$k] ?? '');
        }
        return $this->sync->handleWebhook($headers, $rawBody);
    }
}

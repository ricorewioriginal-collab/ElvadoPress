<?php
declare(strict_types=1);
// cms/src/Lovable/ProviderController.php
//
// Logik des öffentlichen Beitrags-Providers (cms/api-lovable-provider.php): Parameter prüfen, Widget-Vorgaben anwenden, filtern, CORS und Caching.
// Gibt [Status, Kopfzeilen, Rumpf] zurück; die Endpunkt-Datei gibt sie nur aus (so bleibt die Logik testbar).

namespace Elvado\Lovable;

use Elvado\Database\DatabaseConnection;
use Elvado\Repository\LovableWidgetRepository;
use Elvado\Repository\PostRepository;
use Elvado\Support\RateLimiter;

final class ProviderController
{
    /** @param \Closure(string):string|null $sanitizeHtml Bereinigung des Beitragstexts (nur bei include=body) */
    public function __construct(
        private readonly string $dataDir,
        private readonly string $origin,
        private readonly LovableSettings $settings,
        private readonly ?RateLimiter $limiter = null,
        private readonly ?\Closure $sanitizeHtml = null,
        private readonly ?DatabaseConnection $db = null,
    ) {
    }

    /**
     * @param array<string,mixed>  $server  $_SERVER (REQUEST_METHOD, HTTP_ORIGIN, HTTP_IF_NONE_MATCH, REMOTE_ADDR)
     * @param array<string,mixed>  $query   $_GET
     * @return array{0:int,1:array<string,string>,2:string}
     */
    public function handle(array $server, array $query): array
    {
        $method = strtoupper((string)($server['REQUEST_METHOD'] ?? 'GET'));
        $headers = ['Content-Type' => 'application/json; charset=utf-8', 'X-Content-Type-Options' => 'nosniff', 'Vary' => 'Origin'];
        $origin = trim((string)($server['HTTP_ORIGIN'] ?? ''));
        // CORS: nur ausdrücklich erlaubte Herkunft (oder die eigene Website); nie "*", nie mit Zugangsdaten
        if ($origin !== '' && (rtrim(strtolower($origin), '/') === rtrim(strtolower($this->origin), '/') || $this->settings->originAllowed($origin))) {
            $headers['Access-Control-Allow-Origin'] = $origin;
            $headers['Access-Control-Allow-Methods'] = 'GET, OPTIONS';
            $headers['Access-Control-Allow-Headers'] = 'Accept, If-None-Match';
            $headers['Access-Control-Max-Age'] = '600';
        }
        if ($method === 'OPTIONS') {
            return [204, $headers, ''];
        }
        if ($method !== 'GET' && $method !== 'HEAD') {
            return $this->error(405, 'Nur GET ist erlaubt.', $headers + ['Allow' => 'GET, OPTIONS']);
        }
        $ip = (string)($server['REMOTE_ADDR'] ?? '');
        if ($this->limiter !== null && !$this->limiter->hit('lovable-provider:' . $ip, 120, 60)) {
            return $this->error(429, 'Zu viele Anfragen.', $headers + ['Retry-After' => '30']);
        }

        // Widget-Vorgaben (Filter) aus der Tabelle lovable_widgets
        $widgetName = trim((string)($query['widget'] ?? ''));
        $filters = [];
        $widget = null;
        if ($widgetName !== '') {
            if (!LovableWidgetRepository::validComponent($widgetName)) {
                return $this->error(400, 'Ungültiger Widget-Name.', $headers);
            }
            $project = trim((string)($query['project'] ?? ''));
            try {
                $repo = new LovableWidgetRepository($this->db());
                $widget = $repo->findByComponent($widgetName, $project !== '' && LovableWidgetRepository::validProject($project) ? $project : null);
            } catch (\Throwable) {
                return $this->error(503, 'Widget-Konfiguration derzeit nicht verfügbar.', $headers);
            }
            if ($widget === null || !$widget['enabled']) {
                return $this->error(404, 'Widget nicht gefunden.', $headers);
            }
            $filters = array_filter($widget['config']['posts'], static fn($v) => $v !== '' && $v !== null);
            $filters['q'] = $filters['search'] ?? '';
            unset($filters['search']);
        }
        // Anfrageparameter überschreiben Vorgaben (außer: das Limit eines Widgets ist eine Obergrenze)
        $req = [
            'limit' => isset($query['limit']) ? (int)$query['limit'] : ($filters['limit'] ?? 10),
            'offset' => (int)($query['offset'] ?? 0),
            'category' => isset($query['category']) ? (string)$query['category'] : ($filters['category'] ?? ''),
            'tag' => isset($query['tag']) ? (string)$query['tag'] : ($filters['tag'] ?? ''),
            'q' => isset($query['q']) ? (string)$query['q'] : ($filters['q'] ?? ''),
            'featured' => !empty($query['featured']) && $query['featured'] !== '0' && $query['featured'] !== 'false',
            'include_body' => ($query['include'] ?? '') === 'body',
        ];
        if (isset($filters['limit'])) {
            $req['limit'] = min($req['limit'], (int)$filters['limit']);
        }

        $feed = new PostFeed($this->rows(), $this->origin, is_file($this->dataDir . '/.wp/front-on'));
        $res = $feed->query($req, $this->sanitizeHtml);
        $payload = [
            'status' => 'ok',
            'generated_at' => gmdate('c'),
            'total' => $res['total'],
            'count' => $res['count'],
            'items' => $res['items'],
        ];
        if ($widget !== null) {
            $payload['widget'] = ['component_name' => $widget['component_name'], 'project_id' => $widget['project_id'], 'attributes' => $widget['config']['attributes'] ?: new \stdClass()];
        }
        // ETag über den Inhalt ohne Zeitstempel: unveränderte Daten → 304
        $etag = '"' . md5(json_encode([$payload['total'], $payload['items'], $payload['widget'] ?? null], JSON_UNESCAPED_UNICODE)) . '"';
        $headers += ['ETag' => $etag, 'Cache-Control' => 'public, max-age=60'];
        if (trim((string)($server['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            return [304, $headers, ''];
        }
        return [200, $headers, (string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
    }

    /** @return list<array<string,mixed>> */
    private function rows(): array
    {
        $src = (string)$this->settings->bridge()['post_source'];
        // „auto“ nutzt die Datenbank nur, wenn es schon eine gibt (kein Anlegen von Dateien durch öffentliche Anfragen)
        $dbExists = $this->db !== null || is_file($this->dataDir . '/cms-core.sqlite') || is_file($this->dataDir . '/database.local.php');
        if ($src === 'db' || ($src === 'auto' && $dbExists)) {
            try {
                $posts = new PostRepository($this->db());
                if ($src === 'db' || $posts->count() > 0) {
                    return $posts->published();
                }
            } catch (\Throwable) {
                if ($src === 'db') {
                    return [];
                }
            }
        }
        return PostFeed::rowsFromNewsFile($this->dataDir . '/news.json');
    }

    private function db(): DatabaseConnection
    {
        $db = $this->db ?? DatabaseConnection::fromCmsSettings($this->dataDir);
        $db->migrateCore();
        return $db;
    }

    /** @return array{0:int,1:array<string,string>,2:string} */
    private function error(int $status, string $message, array $headers): array
    {
        return [$status, $headers + ['Cache-Control' => 'no-store'], (string)json_encode(['status' => 'error', 'message' => $message], JSON_UNESCAPED_UNICODE)];
    }
}

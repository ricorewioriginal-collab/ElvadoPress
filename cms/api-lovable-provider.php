<?php
declare(strict_types=1);
// cms/api-lovable-provider.php – öffentlicher, nur lesender JSON-Endpunkt: CMS-Beiträge für Lovable-Komponenten (siehe frontend/src/components/LovableWidgetBridge.tsx).
//   GET /cms/api-lovable-provider.php?limit=10&category=News&tag=musik&q=suchwort&offset=0&featured=1&include=body
//   GET /cms/api-lovable-provider.php?widget=news-grid[&project=<id>]   (Filter und Limit aus dem Widget-Eintrag, Menü „KI & Lovable“)
// Nur veröffentlichte Beiträge; kein Roh-HTML (Text nur mit include=body und bereinigt). CORS nur für eingetragene Herkunft. Die Logik steht in cms/src/Lovable/ProviderController.php.
require_once __DIR__ . '/src/autoload.php';
require_once __DIR__ . '/lib/tools.php';
require_once __DIR__ . '/lib/pack.php';

use Elvado\Lovable\LovableSettings;
use Elvado\Lovable\ProviderController;
use Elvado\Support\RateLimiter;

$dataDir = __DIR__ . '/data';
$site = is_file($dataDir . '/site.json') ? (json_decode((string)file_get_contents($dataDir . '/site.json'), true) ?: []) : [];
$origin = elvado_site_origin(is_array($site) ? $site : []);
if ($origin === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $origin = $scheme . '://' . preg_replace('/[^a-z0-9.:-]/i', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
}
$sanitize = static function (string $html): string {
    if (!function_exists('elvado_safe_html')) {
        require_once __DIR__ . '/lib/publish.php';
    }
    return elvado_safe_html($html);
};
$controller = new ProviderController($dataDir, $origin, LovableSettings::load($dataDir), new RateLimiter($dataDir . '/.lovable/ratelimit'), $sanitize);
[$status, $headers, $body] = $controller->handle($_SERVER, $_GET);
http_response_code($status);
foreach ($headers as $name => $value) {
    header($name . ': ' . $value);
}
if ($status !== 204 && $status !== 304 && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
    echo $body;
}

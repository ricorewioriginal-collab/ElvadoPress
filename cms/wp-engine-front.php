<?php
// Website-Auslieferung durch echtes WordPress (WordPress-Engine, Schalter „Website mit WordPress ausliefern“ unter System → WordPress-Engine).
// Wird von cms/wp-front.php im globalen Gültigkeitsbereich eingebunden, bevor die WordPress-Nachbildung lädt. Greift nur, wenn die Engine aktiv ist und der Schalter an ist;
// sonst kehrt die Datei sofort zurück und ElvadoPress liefert die Website wie bisher aus. Nicht für Vorschauen, Sandbox, Demo und weitere Websites (Multisite).
//
// Aufgaben: statische Core-Dateien (wp-includes, wp-admin/css|js|images) aus dem gesperrten Core-Ordner ausliefern, wp-login/wp-admin zur ElvadoPress-Verwaltung umleiten,
// Besucher-Endpunkte (admin-ajax, admin-post, wp-comments-post) an WordPress geben und alle übrigen Adressen mit dem aktiven WordPress-Theme ausliefern.
if (!isset($cmsDir) || !empty($sbx) || (isset($siteId) && $siteId !== '') || is_file($cmsDir . '/lib/demo.json')
    || isset($_GET['elvado_wp_preview']) || isset($_GET['elvado_ep_preview']) || isset($_COOKIE['elvado_wp_preview']) || isset($_GET['elvado_wp_login'])) {
    return;
}
require_once $cmsDir . '/lib/wpengine.php';
$elvadoFrontEngine = elvado_wpe();
if (!$elvadoFrontEngine->front() || $elvadoFrontEngine->corePath() === null) {
    return;
}
$elvadoFrontPath = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

// Statische Dateien des Cores (keine PHP-Dateien, nur bekannte Typen, kein Pfad nach außen)
if (preg_match('#^/(wp-includes|wp-admin/(?:css|js|images))/([A-Za-z0-9._/-]+)$#', $elvadoFrontPath, $elvadoFrontM)) {
    $elvadoFrontTypes = ['css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png', 'gif' => 'image/gif', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'svg' => 'image/svg+xml', 'webp' => 'image/webp', 'ico' => 'image/x-icon',
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'eot' => 'application/vnd.ms-fontobject', 'json' => 'application/json', 'map' => 'application/json', 'txt' => 'text/plain; charset=utf-8'];
    $elvadoFrontExt = strtolower(pathinfo($elvadoFrontM[2], PATHINFO_EXTENSION));
    $elvadoFrontFile = $elvadoFrontEngine->corePath() . $elvadoFrontM[1] . '/' . $elvadoFrontM[2];
    if (!str_contains($elvadoFrontM[2], '..') && isset($elvadoFrontTypes[$elvadoFrontExt]) && is_file($elvadoFrontFile)) {
        header('Content-Type: ' . $elvadoFrontTypes[$elvadoFrontExt]);
        header('Cache-Control: public, max-age=604800');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . (string)filesize($elvadoFrontFile));
        readfile($elvadoFrontFile);
        exit;
    }
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Nicht gefunden';
    exit;
}
// Anmeldung und Verwaltung gehören zu ElvadoPress
if ($elvadoFrontPath === '/wp-login.php' || preg_match('#^/wp-admin(/(?!admin-ajax\.php$|admin-post\.php$).*)?$#', $elvadoFrontPath)) {
    header('Location: /cms/', true, 302);
    exit;
}
try {
    require_once $cmsDir . '/lib/tools.php';
    elvado_maint_gate($root);   // Wartungsmodus gilt auch hier
} catch (Throwable $e) {
}
// wp-load.php (von admin-ajax.php & Co. eingebunden) verlangt eine wp-config.php: Die Engine setzt alles selbst, die Datei ist nur ein Platzhalter.
$elvadoFrontCfg = $elvadoFrontEngine->corePath() . 'wp-config.php';
if (!is_file($elvadoFrontCfg)) {
    @file_put_contents($elvadoFrontCfg, "<?php\n// ElvadoPress: Die Konfiguration setzt die WordPress-Engine (cms/src/Wp/Bridge.php). Diese Datei ist nur ein Platzhalter für wp-load.php.\nif (!defined('ELVADO_ENGINE_SAFE')) {\n    http_response_code(404);\n    exit;\n}\n");
}
$GLOBALS['elvado_wpe_engine'] = $elvadoFrontEngine;
$GLOBALS['elvado_wpe_db'] = new \Elvado\Wp\DbConfig($elvadoFrontEngine);
$GLOBALS['elvado_wpe_opts'] = ['themes' => true, 'front' => true];
require $cmsDir . '/wp-engine-boot.php';
elvado_wpe_after_boot($elvadoFrontEngine);
$elvadoFrontEntry = ['/wp-admin/admin-ajax.php' => 'wp-admin/admin-ajax.php', '/wp-admin/admin-post.php' => 'wp-admin/admin-post.php', '/wp-comments-post.php' => 'wp-comments-post.php'][$elvadoFrontPath] ?? '';
if ($elvadoFrontEntry !== '') {
    require ABSPATH . $elvadoFrontEntry;
    exit;
}
if (!wp_get_theme()->exists()) {   // ohne Theme bliebe die Seite leer
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Website</title><body style="font:16px system-ui;max-width:640px;margin:12vh auto;padding:0 16px"><h1>Die Website wird gerade eingerichtet</h1><p>WordPress hat kein aktives Theme. Bitte in der Verwaltung unter Plugins → WordPress-Plugins ein Theme aktivieren.</p></body>';
    exit;
}
// Beiträge und Seiten aus der ElvadoPress-Verwaltung übernehmen, wenn sich dort etwas geändert hat (Abgleich nur bei Änderungen, siehe LiveSync)
try {
    (new \Elvado\Wp\Migration\LiveSync($cmsDir, $siteData))->maybeSync();
} catch (Throwable $e) {
    $elvadoFrontEngine->log('Abgleich mit WordPress fehlgeschlagen: ' . $e->getMessage());
}
// Geplante Aufgaben (z. B. geplante Beiträge): WordPress ruft sich dafür sonst selbst über HTTP auf (abgeschaltet). Nach der Antwort höchstens einmal pro Minute ausführen.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && empty($_POST)) {
    register_shutdown_function(static function (): void {
        if ((int)get_option('elvado_cron_last', 0) > time() - 60 || !function_exists('wp_get_ready_cron_jobs') || wp_get_ready_cron_jobs() === []) {
            return;
        }
        update_option('elvado_cron_last', time(), false);
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
        ignore_user_abort(true);
        try {
            require ABSPATH . 'wp-cron.php';
        } catch (Throwable) {
        }
    });
}
wp();
require ABSPATH . WPINC . '/template-loader.php';
exit;

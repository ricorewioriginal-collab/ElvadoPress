<?php
// Kindprozess von scripts/wpe-render.php: startet echtes WordPress für die Testinstallation und führt einen Schritt aus.
// Aufruf: php wpe-render-child.php <cmsDir> install|php|get <adresse> <codedatei>
declare(strict_types=1);
[$cms, $mode, $uri, $codeFile] = [(string)$argv[1], (string)$argv[2], (string)$argv[3], (string)$argv[4]];
require_once $cms . '/src/autoload.php';
require_once $cms . '/lib/wpengine.php';
$_SERVER['HTTP_HOST'] = 'example.test';
$_SERVER['SERVER_NAME'] = 'example.test';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = $uri !== '' ? $uri : '/';
$q = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
parse_str($q, $_GET);
$elvadoE = elvado_wpe($cms, $cms . '/data', true);
$GLOBALS['elvado_wpe_engine'] = $elvadoE;
$GLOBALS['elvado_wpe_db'] = new \Elvado\Wp\DbConfig($elvadoE);
$GLOBALS['elvado_wpe_opts'] = $mode === 'install' ? ['installing' => true] : ['themes' => true, 'front' => true];
$elvadoResult = [];
ob_start();
try {
    require $cms . '/wp-engine-boot.php';
    if ($mode === 'install') {
        $r = \Elvado\Wp\Bridge::installSchema('Testseite', 'test@example.invalid');
        update_option('permalink_structure', '/%postname%/');
        elvado_wpe_sync_site();
        $elvadoResult = ['ok' => $r['ok']];
    } else {
        // ElvadoPress-Inhalte und vorgemerkte Einstellungen wie auf der Website übernehmen
        elvado_wpe_adopt_site($elvadoE, true);
        (new \Elvado\Wp\Migration\LiveSync($cms, $cms . '/data'))->maybeSync();
        $out = null;
        include $codeFile;
        if ($mode === 'php') {
            $elvadoResult = ['out' => $out];
        } else {
            $GLOBALS['elvadoRenderLevel'] = ob_get_level();
            register_shutdown_function(static function (): void {   // Seiten dürfen mit exit enden (Weiterleitung, wp_die): Ergebnis trotzdem melden
                if (!empty($GLOBALS['elvadoRenderDone'])) {
                    return;
                }
                $b = '';
                while (ob_get_level() > 0) {
                    $b = (string)ob_get_clean() . $b;
                }
                $loc = '';
                foreach (headers_list() as $h) {
                    if (stripos($h, 'Location:') === 0) {
                        $loc = trim(substr($h, 9));
                    }
                }
                echo "\n@@WPE@@" . json_encode(['status' => (int)(http_response_code() ?: 200), 'body' => $b, 'location' => $loc], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            });
            ob_start();
            wp();
            require ABSPATH . WPINC . '/template-loader.php';
            $body = (string)ob_get_clean();
            $elvadoResult = ['status' => (int)(http_response_code() ?: 200), 'body' => $body];
            $GLOBALS['elvadoRenderDone'] = true;
        }
    }
} catch (Throwable $e) {
    $elvadoResult = ['error' => get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'];
}
while (ob_get_level() > 0) {
    ob_end_clean();
}
$GLOBALS['elvadoRenderDone'] = true;
echo "\n@@WPE@@" . json_encode($elvadoResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

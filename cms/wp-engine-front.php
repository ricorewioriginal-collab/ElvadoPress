<?php
// Website-Auslieferung durch echtes WordPress (WordPress-Engine, Schalter „Website mit WordPress ausliefern“ unter System → WordPress-Engine).
// Wird von cms/wp-front.php im globalen Gültigkeitsbereich eingebunden, bevor die WordPress-Nachbildung lädt. Greift nur, wenn die Engine aktiv ist und der Schalter an ist;
// sonst kehrt die Datei sofort zurück und ElvadoPress liefert die Website wie bisher aus. Nicht für Sandbox, Demo und weitere Websites (Multisite).
// Vorschauen (Theme-Vorschau, Customizer-Entwurf, Live Builder) zeigt ebenfalls echtes WordPress (signierte Schlüssel, siehe lib/wp-real-admin.php).
//
// Aufgaben: statische Core-Dateien (wp-includes, wp-admin/css|js|images) aus dem gesperrten Core-Ordner ausliefern, wp-login/wp-admin zur ElvadoPress-Verwaltung umleiten,
// Besucher-Endpunkte (admin-ajax, admin-post, wp-comments-post) an WordPress geben und alle übrigen Adressen mit dem aktiven WordPress-Theme ausliefern.
if (!isset($cmsDir) || !empty($sbx) || (isset($siteId) && $siteId !== '') || is_file($cmsDir . '/lib/demo.json')
    || isset($_GET['elvado_wp_login'])) {
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
// Anmeldung gehört zu ElvadoPress. Die echte WordPress-Verwaltung (wp-admin, z. B. Einstellungsseiten von Plugins) öffnen Superadmins aus ElvadoPress heraus
// („In WordPress öffnen“, Einmal-Schlüssel elvado_sso); ohne WordPress-Anmeldung führt sie zur ElvadoPress-Anmeldung.
if ($elvadoFrontPath === '/wp-login.php') {
    if (($_GET['action'] ?? '') === 'logout') {
        foreach (array_keys($_COOKIE) as $elvadoFrontC) {
            if (str_starts_with((string)$elvadoFrontC, 'wordpress_')) {
                foreach (['/', '/wp-admin', '/wp-content/plugins'] as $elvadoFrontCp) {
                    setcookie((string)$elvadoFrontC, '', ['expires' => 1, 'path' => $elvadoFrontCp]);
                }
            }
        }
    }
    header('Location: /cms/', true, 302);
    exit;
}
$elvadoFrontAdmin = '';
if (preg_match('#^/wp-admin(?:/((?:network/|user/)?[a-z0-9-]+\.php)?)?/?$#', $elvadoFrontPath, $elvadoFrontAm) && !in_array($elvadoFrontAm[1] ?? '', ['admin-ajax.php', 'admin-post.php'], true)) {
    $elvadoFrontAdmin = ($elvadoFrontAm[1] ?? '') !== '' ? $elvadoFrontAm[1] : 'index.php';
    if (!is_file($elvadoFrontEngine->corePath() . 'wp-admin/' . $elvadoFrontAdmin) || in_array(basename($elvadoFrontAdmin), ['load-scripts.php', 'load-styles.php', 'install.php', 'setup-config.php', 'upgrade.php'], true)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Nicht gefunden';
        exit;
    }
} elseif (str_starts_with($elvadoFrontPath, '/wp-admin/') && !in_array($elvadoFrontPath, ['/wp-admin/admin-ajax.php', '/wp-admin/admin-post.php'], true)) {
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
$elvadoFrontNpUri = (string)($_SERVER['REQUEST_URI'] ?? '/');   // Plugins (z. B. Seiten-Cache) sehen die vollständige Adresse mit Sprachpräfix
$elvadoFrontNpPath = $elvadoFrontPath;
$elvadoFrontMethod = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
// Mehrsprachige Website: /en/… → Sprache merken und WordPress den Pfad ohne Präfix geben (Filter in lib/multilang.php, geladen von der Engine-Schicht)
try {
    require_once $cmsDir . '/lib/multilang.php';
    $elvadoMl = elvado_ml_config((array)(json_decode((string)@file_get_contents($siteData . '/site.json'), true) ?: []));
    if ($elvadoMl['enabled']) {
        [$elvadoMlLang, $elvadoMlPath] = elvado_ml_detect($elvadoFrontPath, $elvadoMl);
        if ($elvadoMlLang !== $elvadoMl['default']) {
            $elvadoMlQ = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
            $_SERVER['REQUEST_URI'] = $elvadoMlPath . ($elvadoMlQ !== '' ? '?' . $elvadoMlQ : '');
            $elvadoFrontPath = $elvadoMlPath;
        }
        $GLOBALS['elvado_ml'] = ['lang' => $elvadoMlLang, 'cfg' => $elvadoMl];
    }
} catch (Throwable $e) {
}
// Vorschau: Live Builder (signierter Schlüssel, Bereich „Website“) und Theme-Vorschau/Customizer-Entwurf (signierter Schlüssel → Cookie, 15 Minuten)
require_once $cmsDir . '/lib/wp-real-admin.php';
$elvadoFrontEp = false;
if (isset($_GET['elvado_ep_preview']) && is_string($_GET['elvado_ep_preview'])) {
    require_once $cmsDir . '/lib/components.php';
    $elvadoFrontEp = elvado_components_preview_ok($_GET['elvado_ep_preview'], 'site:website', $siteData);
}
if (($_GET['elvado_wp_preview'] ?? '') === 'off') {
    foreach (['elvado_wp_preview', 'elvado_wp_draft'] as $elvadoFrontC) {
        setcookie($elvadoFrontC, '', ['expires' => 1, 'path' => '/']);
    }
    header('Location: /', true, 302);
    exit;
}
$elvadoFrontPvTok = $elvadoFrontEp ? (string)($_GET['elvado_wp_preview'] ?? '') : (string)($_GET['elvado_wp_preview'] ?? $_COOKIE['elvado_wp_preview'] ?? '');
$elvadoFrontPreview = $elvadoFrontPvTok !== '' ? elvado_wpra_preview_ok($siteData, $elvadoFrontPvTok) : '';
if ($elvadoFrontPreview !== '' && !elvado_wpra_use_theme($cmsDir, $elvadoFrontPreview)) {
    $elvadoFrontPreview = '';
}
if ($elvadoFrontPreview !== '' && isset($_GET['elvado_wp_preview'])) {
    setcookie('elvado_wp_preview', $elvadoFrontPvTok, ['expires' => (int)explode('.', $elvadoFrontPvTok)[1], 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
}
$elvadoFrontIsPreview = $elvadoFrontPreview !== '' || $elvadoFrontEp;
$elvadoFrontSso = '';
if ($elvadoFrontAdmin !== '') {   // WordPress-Verwaltung: Kontext vor dem Start festlegen (is_admin(), $pagenow), keine zusammengefassten Skripte (load-scripts.php)
    define('WP_ADMIN', true);
    define('CONCATENATE_SCRIPTS', false);
    $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'] = '/wp-admin/' . $elvadoFrontAdmin;
    $elvadoFrontSso = elvado_wpra_sso_consume($siteData, (string)($_GET['elvado_sso'] ?? ''));
    $elvadoFrontIsPreview = true;   // nie Seiten-Cache, keine Statistik
    header('Cache-Control: no-store');
}
if ($elvadoFrontIsPreview) {
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
}
// Native Plugins (Essentials) wie bei der bisherigen Auslieferung: Start (z. B. Sicherheits-Header), frühe Anfragen (Seiten-Cache liefert ohne Start von WordPress aus)
require_once $cmsDir . '/lib/nplugins.php';
elvado_np_boot();
if (!$elvadoFrontIsPreview) {   // Vorschauen nie aus dem Seiten-Cache
    elvado_np_do('front_request', $elvadoFrontNpUri, $elvadoFrontNpPath, $elvadoFrontMethod);
}
$GLOBALS['elvado_wpe_engine'] = $elvadoFrontEngine;
$GLOBALS['elvado_wpe_db'] = new \Elvado\Wp\DbConfig($elvadoFrontEngine);
$GLOBALS['elvado_wpe_opts'] = ['themes' => true, 'front' => true];
require $cmsDir . '/wp-engine-boot.php';
elvado_wpe_after_boot($elvadoFrontEngine);
elvado_np_do('wp_ready');   // native Plugins registrieren jetzt ihre WordPress-Hooks und Shortcodes (z. B. [elvado_form])
if ($elvadoFrontAdmin !== '') {
    if ($elvadoFrontSso !== '') {   // Einmal-Schlüssel: als WordPress-Administrator anmelden (Benutzer gleichen Namens, sonst der erste Administrator)
        $elvadoFrontU = get_user_by('login', $elvadoFrontSso);
        if (!$elvadoFrontU || !user_can($elvadoFrontU, 'manage_options')) {
            $elvadoFrontAdmins = get_users(['role' => 'administrator', 'number' => 1]);
            $elvadoFrontU = $elvadoFrontAdmins[0] ?? null;
        }
        if ($elvadoFrontU) {
            wp_set_auth_cookie((int)$elvadoFrontU->ID, false, is_ssl());
        }
        header('Location: ' . remove_query_arg('elvado_sso'), true, 302);
        exit;
    }
    require ABSPATH . 'wp-admin/' . $elvadoFrontAdmin;
    exit;
}
// Vorschau-Brücke für Live Builder/Customizer und ungespeicherte Customizer-Entwürfe (nur mit gültigem Vorschau-Schlüssel, nichts wird gespeichert)
$elvadoFrontLiveCz = false;
if ($elvadoFrontPreview !== '') {
    add_action('wp_footer', static function (): void {
        echo '<script src="/cms/assets/preview-bridge.js?v=1" defer></script>' . "\n";
    }, 99);
    $elvadoFrontDraft = (string)($_GET['elvado_wp_draft'] ?? $_COOKIE['elvado_wp_draft'] ?? '');
    if (preg_match('/^[a-f0-9]{32}$/', $elvadoFrontDraft)) {
        require_once $cmsDir . '/lib/wp-real-compat.php';
        require_once $cmsDir . '/wp/customizer-api.php';
        $elvadoFrontLiveCz = true;
        $elvadoFrontDd = elvado_wpc_draft_load($elvadoFrontDraft, $elvadoFrontPreview);
        if ($elvadoFrontDd !== null) {
            elvado_wpc_draft_apply($elvadoFrontDd);
        }
        if (isset($_GET['elvado_wp_draft'])) {
            setcookie('elvado_wp_draft', $elvadoFrontDraft, ['expires' => time() + 900, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        }
    }
} elseif (is_file($siteData . '/.wp/customize-changesets/future.idx')) {   // geplante Customizer-Änderungen
    try {
        require_once $cmsDir . '/lib/wp-real-compat.php';
        require_once $cmsDir . '/wp/customizer-api.php';
        elvado_wpc_cs_run_due();
    } catch (Throwable $e) {
    }
}
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
    // Design-Einstellungen aus der Verwaltung (Theme, Theme-Einstellungen, Widgets, Optionen) übernehmen, wenn sie sich geändert haben
    $elvadoOptFile = $siteData . '/.wp/options.json';
    clearstatcache(true, $elvadoOptFile);
    $elvadoOptFp = is_file($elvadoOptFile) ? filemtime($elvadoOptFile) . ':' . filesize($elvadoOptFile) : '-';
    if ((string)get_option('elvado_opts_fp', '') !== $elvadoOptFp) {
        elvado_wpe_adopt_site($elvadoFrontEngine, true);
        update_option('elvado_opts_fp', $elvadoOptFp, false);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !headers_sent()) {   // neues Theme/neue Plugins greifen erst beim nächsten Start von WordPress
            header('Location: ' . (string)($_SERVER['REQUEST_URI'] ?? '/'), true, 302);
            header('Cache-Control: no-store');
            exit;
        }
    }
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
ob_start();
wp();
require ABSPATH . WPINC . '/template-loader.php';
$elvadoFrontBody = (string)ob_get_clean();
$elvadoFrontStatus = (int)http_response_code() ?: 200;
$elvadoFrontType = 'text/html';
foreach (headers_list() as $elvadoFrontH) {
    if (stripos($elvadoFrontH, 'Content-Type:') === 0) {
        $elvadoFrontType = trim(substr($elvadoFrontH, 13));
    }
}
$elvadoFrontHtml = stripos($elvadoFrontType, 'html') !== false;
elvado_np_tick();   // geplante Plugin-Aufgaben (höchstens einmal pro Minute)
// Weiterleitungen aus der Verwaltung (Werkzeuge) haben Vorrang vor der 404-Seite des Themes; unbekannte Pfade werden protokolliert
if ($elvadoFrontStatus === 404) {
    try {
        $elvadoFrontRules = elvado_tools_read(elvado_tools_dir($siteData) . '/redirects.json', ['rules' => []])['rules'] ?? [];
        $elvadoFrontHit = is_array($elvadoFrontRules) ? elvado_redirect_match($elvadoFrontRules, $elvadoFrontNpPath) : null;
        if ($elvadoFrontHit) {
            $elvadoFrontCode = (int)($elvadoFrontHit['code'] ?? 301);
            header_remove('Content-Type');
            if ($elvadoFrontCode === 410) {
                http_response_code(410);
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Diese Seite wurde dauerhaft entfernt.';
                exit;
            }
            header('Location: ' . str_replace(["\r", "\n"], '', (string)$elvadoFrontHit['to']), true, $elvadoFrontCode);
            exit;
        }
        elvado_404_log($siteData, $elvadoFrontNpPath, (string)($_SERVER['HTTP_REFERER'] ?? ''));
    } catch (Throwable $e) {
    }
}
if ($elvadoFrontHtml && $elvadoFrontMethod === 'GET') {
    // App-Modus (Baukasten-App): Kopf und Fuß der Website in der App ausblenden (siehe lib/appmode.php)
    if ($elvadoFrontStatus === 200) {
        try {
            require_once $cmsDir . '/lib/appmode.php';
            $elvadoFrontAm = elvado_appmode_detect();
            if ($elvadoFrontAm !== null) {
                header('Vary: User-Agent, Cookie', false);
                require_once $cmsDir . '/lib/apps.php';
                if (elvado_appmode_hide($elvadoFrontAm, elvado_apps_own($siteData), (array)(json_decode((string)@file_get_contents($siteData . '/site.json'), true) ?: []))) {
                    $elvadoFrontBody = elvado_appmode_inject($elvadoFrontBody, true);
                }
            }
        } catch (Throwable $e) {
        }
    }
    if (!$elvadoFrontIsPreview) {
        $elvadoFrontBody = (string)elvado_np_filter('front_output', $elvadoFrontBody, $elvadoFrontStatus);   // Plugins: SEO, Leistung, Statistik
    }
    if ($elvadoFrontLiveCz && ($elvadoFrontP = strripos($elvadoFrontBody, '</body>')) !== false) {
        $elvadoFrontBody = substr($elvadoFrontBody, 0, $elvadoFrontP) . elvado_wpc_live_script() . substr($elvadoFrontBody, $elvadoFrontP);
    }
    // Live Builder (themeunabhängig): veröffentlichte Gestaltung des Bereichs „Website“ (site:website)
    if ($elvadoFrontStatus === 200 && ($elvadoFrontEp || is_file($siteData . '/layouts/site__website.json'))) {
        try {
            require_once $cmsDir . '/lib/components.php';
            $elvadoFrontBody = elvado_components_inject($elvadoFrontBody, 'site:website', $elvadoFrontEp ? $_GET : [], $siteData);
        } catch (Throwable $e) {
        }
    }
}
if (!$elvadoFrontIsPreview) {
    elvado_np_do('front_response', $elvadoFrontStatus, $elvadoFrontNpPath, $elvadoFrontType);   // z. B. Statistik (Vorschauen zählen nicht)
}
echo $elvadoFrontBody;
exit;

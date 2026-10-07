<?php
declare(strict_types=1);
// Verwaltungs-API der WordPress-Engine (eigener Einstieg, damit echtes WordPress in einem sauberen globalen Gültigkeitsbereich startet und nicht in den Variablen von api.php).
// Aktionen: engine_status, engine_prepare, engine_core, engine_db_test, engine_db_install, engine_analyze, engine_mode, engine_remove. Alle nur für Administratoren.
// Die Anmeldeprüfung kommt aus api.php (RRW_API_LIB_ONLY); in der Demo sind alle Aktionen außer engine_status gesperrt.
define('RRW_API_LIB_ONLY', true);
require __DIR__ . '/api.php';
require_once __DIR__ . '/src/autoload.php';
require_once __DIR__ . '/lib/wpengine.php';

$rrwEngineAction = (string)($_GET['action'] ?? 'engine_status');   // eigene Namen: WordPress überschreibt beim Start globale Variablen wie $action
$rrwEngineSiteFile = $siteFile;
$rrwEngineU = rrw_auth(true);
if (function_exists('rrw_demo_enabled') && rrw_demo_enabled() && $rrwEngineAction !== 'engine_status') {
    rrw_json(['status' => 'error', 'message' => 'In der Demo gesperrt: Die WordPress-Engine lässt sich nur in einer eigenen ElvadoPress-Installation einrichten.'], 403);
}
$rrwEngineB = $_SERVER['REQUEST_METHOD'] === 'POST' ? rrw_body() : [];
$rrwEngine = rrw_wpe();
$rrwEngineDb = new \Elvado\Wp\DbConfig($rrwEngine);
$rrwEngineLog = static function (string $what) use ($rrwEngineU, $activityLogFile): void {
    if (function_exists('rrw_log_activity')) {
        rrw_log_activity($activityLogFile, $rrwEngineU, 'wp_engine', $what);
    }
};
@set_time_limit(300);

if ($rrwEngineAction === 'engine_status') {
    rrw_json(['status' => 'ok'] + rrw_wpe_status($rrwEngine, $rrwEngineDb));
}
if ($rrwEngineAction === 'engine_prepare') {
    rrw_json(['status' => 'ok'] + rrw_wpe_prepare($rrwEngine));
}
if ($rrwEngineAction === 'engine_core') {
    $r = rrw_wpe_install_core($rrwEngine, (string)($rrwEngineB['version'] ?? ''));
    if ($r['ok']) {
        $rrwEngineLog('WordPress ' . $r['version'] . ' eingespielt');
    }
    rrw_json(['status' => $r['ok'] ? 'ok' : 'error'] + $r + rrw_wpe_status($rrwEngine, $rrwEngineDb), $r['ok'] ? 200 : 422);
}
if ($rrwEngineAction === 'engine_db_test') {
    $t = $rrwEngineDb->test((array)($rrwEngineB['db'] ?? []));
    rrw_json(['status' => $t['ok'] ? 'ok' : 'error'] + $t, $t['ok'] ? 200 : 422);
}
if ($rrwEngineAction === 'engine_db_install') {
    $pre = rrw_wpe_pre_install($rrwEngine, $rrwEngineDb, (array)($rrwEngineB['db'] ?? []));
    if (!$pre['ok']) {
        rrw_json(['status' => 'error', 'message' => $pre['message']], 422);
    }
    // WordPress im Installationsmodus starten (globaler Gültigkeitsbereich) und die Tabellen anlegen
    $GLOBALS['rrw_wpe_engine'] = $rrwEngine;
    $GLOBALS['rrw_wpe_db'] = $rrwEngineDb;
    $GLOBALS['rrw_wpe_opts'] = ['installing' => true];
    rrw_wpe_guard_output();
    require __DIR__ . '/wp-engine-boot.php';
    $rrwSite = rrw_read_json($rrwEngineSiteFile, []);
    $res = \Elvado\Wp\Bridge::installSchema((string)($rrwSite['portal']['site_name'] ?? ($rrwEngineB['title'] ?? '')), (string)($rrwEngineB['email'] ?? 'admin@example.invalid'));
    if (!$res['ok']) {
        rrw_json(['status' => 'error', 'message' => $res['message']], 422);
    }
    $rrwEngine->save(['mode' => 'installed', 'db' => ['ready' => true, 'checked_at' => date('c'), 'prefix' => $pre['prefix'], 'server' => $pre['server']]]);
    $rrwEngine->log('WordPress-Tabellen angelegt (Präfix ' . $pre['prefix'] . ')');
    $rrwEngineLog('WordPress-Datenbank eingerichtet');
    rrw_json(['status' => 'ok', 'message' => 'WordPress ist eingerichtet. Die Website selbst bleibt unverändert.'] + rrw_wpe_status($rrwEngine, $rrwEngineDb));
}
if ($rrwEngineAction === 'engine_analyze' || ($rrwEngineAction === 'engine_mode' && (string)($rrwEngineB['mode'] ?? '') === 'active')) {
    if (!$rrwEngine->isInstalled() || empty($rrwEngine->state()['db']['ready'])) {
        rrw_json(['status' => 'error', 'message' => 'Die WordPress-Engine ist noch nicht eingerichtet.'], 422);
    }
    $t0 = microtime(true);
    $GLOBALS['rrw_wpe_engine'] = $rrwEngine;
    $GLOBALS['rrw_wpe_db'] = $rrwEngineDb;
    $GLOBALS['rrw_wpe_opts'] = [];
    rrw_wpe_guard_output();
    require __DIR__ . '/wp-engine-boot.php';
    $ms = (int)round((microtime(true) - $t0) * 1000);
    if (!\Elvado\Wp\Bridge::booted()) {
        rrw_json(['status' => 'error', 'message' => 'WordPress ließ sich nicht starten.'], 500);
    }
    $wp = new \Elvado\Wp\Adapter\WordPressAdapter();
    if ($rrwEngineAction === 'engine_mode') {
        $m = $rrwEngine->setMode('active');
        if ($m['ok']) {
            $rrwEngineLog('WordPress-Engine aktiviert');
        }
        rrw_json(['status' => $m['ok'] ? 'ok' : 'error', 'message' => $m['ok'] ? 'Die WordPress-Engine ist aktiv.' : $m['message'], 'boot_ms' => $ms] + rrw_wpe_status($rrwEngine, $rrwEngineDb), $m['ok'] ? 200 : 422);
    }
    $native = new \Elvado\Wp\Adapter\NativeAdapter(__DIR__, __DIR__ . '/data');
    rrw_json(['status' => 'ok', 'boot_ms' => $ms, 'native' => ['name' => $native->name(), 'counts' => $native->counts()], 'wordpress' => ['name' => $wp->name(), 'counts' => $wp->counts(), 'info' => $wp->info()]]);
}
if ($rrwEngineAction === 'engine_mode') {
    $mode = (string)($rrwEngineB['mode'] ?? '');
    if ($mode === 'active') {
        rrw_json(['status' => 'error', 'message' => 'Unerwarteter Pfad.'], 500);
    }
    $m = $rrwEngine->setMode($mode);
    if ($m['ok']) {
        $rrwEngineLog('WordPress-Engine: Betriebsart ' . $mode);
    }
    rrw_json(['status' => $m['ok'] ? 'ok' : 'error', 'message' => $m['message']] + rrw_wpe_status($rrwEngine, $rrwEngineDb), $m['ok'] ? 200 : 422);
}
if ($rrwEngineAction === 'engine_remove') {
    if (empty($rrwEngineB['confirm'])) {
        rrw_json(['status' => 'error', 'message' => 'Bitte das Entfernen bestätigen.'], 400);
    }
    $r = rrw_wpe_remove($rrwEngine);
    $rrwEngineLog('WordPress-Engine entfernt');
    rrw_json(['status' => 'ok', 'message' => $r['message']] + rrw_wpe_status($rrwEngine, $rrwEngineDb));
}
rrw_json(['status' => 'error', 'message' => 'Unbekannte Aktion'], 404);

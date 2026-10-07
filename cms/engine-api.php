<?php
declare(strict_types=1);
// Verwaltungs-API der WordPress-Engine (eigener Einstieg, damit echtes WordPress in einem sauberen globalen Gültigkeitsbereich startet und nicht in den Variablen von api.php).
// Aktionen: engine_status, engine_prepare, engine_core, engine_db_test, engine_db_install, engine_analyze, engine_mode, engine_remove;
// Inhalte über den Dienst/Adapter: content_list, content_get, content_save, content_delete, term_list, term_save, term_delete; Medien media_list|get|upload|update|delete; Benutzer user_list, user_sync; Plugins/Themes ext_list|search|install|upload|activate|deactivate|delete|safe; Menüs nav_list|get|create|rename|delete|save|assign|import; Widgets widgets_overview|add|update|move|delete; Blöcke blocks_registry|parse|check|render|serialize|convert.
// Rechte: Administratoren alles; Autoren Inhalte/Medien nur eigene (Dienste prüfen), Seiten, Begriffe, Benutzer und Engine nur Administratoren. Quelle: ?source=native (nur lesen) oder wordpress (Standard, wenn die Engine aktiv ist).
// Die Anmeldeprüfung kommt aus api.php (RRW_API_LIB_ONLY); in der Demo sind alle Aktionen außer engine_status gesperrt.
define('RRW_API_LIB_ONLY', true);
require __DIR__ . '/api.php';
require_once __DIR__ . '/src/autoload.php';
require_once __DIR__ . '/lib/wpengine.php';

$rrwEngineAction = (string)($_GET['action'] ?? 'engine_status');   // eigene Namen: WordPress überschreibt beim Start globale Variablen wie $action
$rrwEngineSiteFile = $siteFile;
$rrwEngineU = rrw_auth(false);
$rrwActor = \Elvado\Wp\Actor::fromAuth($rrwEngineU);
// Inhalte und Medien dürfen angemeldete Personen nach ihren Rechten (Dienst prüft); alles andere – Engine-Verwaltung, Benutzer, Begriffe ändern – nur Administratoren.
if (!$rrwActor->isAdmin() && !in_array($rrwEngineAction, ['content_list', 'content_get', 'content_save', 'content_delete', 'term_list', 'term_save', 'term_delete', 'media_list', 'media_get', 'media_upload', 'media_update', 'media_delete', 'nav_list', 'nav_get', 'blocks_registry', 'blocks_parse', 'blocks_serialize', 'blocks_render', 'blocks_check', 'blocks_convert'], true)) {
    rrw_json(['status' => 'error', 'message' => 'Nur Administratoren dürfen diese Aktion ausführen'], 403);
}
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
    rrw_wpe_after_boot($rrwEngine);
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
if (preg_match('/^(content|term|media|user)_/', $rrwEngineAction) === 1) {
    $rrwSource = (string)($_GET['source'] ?? ($rrwEngine->isActive() ? 'wordpress' : 'native'));
    if ($rrwSource === 'wordpress') {
        if (!$rrwEngine->isActive()) {
            rrw_json(['status' => 'error', 'message' => 'Die WordPress-Engine ist nicht aktiv.'], 409);
        }
        $GLOBALS['rrw_wpe_engine'] = $rrwEngine;
        $GLOBALS['rrw_wpe_db'] = $rrwEngineDb;
        $GLOBALS['rrw_wpe_opts'] = [];
        rrw_wpe_guard_output();
        require __DIR__ . '/wp-engine-boot.php';
        if (!\Elvado\Wp\Bridge::booted()) {
            rrw_json(['status' => 'error', 'message' => 'WordPress ließ sich nicht starten.'], 500);
        }
        rrw_wpe_after_boot($rrwEngine);
        $rrwAdapter = new \Elvado\Wp\Adapter\WordPressAdapter();
    } elseif ($rrwSource === 'native') {
        $rrwAdapter = new \Elvado\Wp\Adapter\NativeAdapter(__DIR__, __DIR__ . '/data');
    } else {
        rrw_json(['status' => 'error', 'message' => 'Unbekannte Quelle.'], 400);
    }
    if (str_starts_with($rrwEngineAction, 'media_') || str_starts_with($rrwEngineAction, 'user_')) {
        try {
            if (str_starts_with($rrwEngineAction, 'media_')) {
                $rrwMedia = new \Elvado\Wp\MediaService($rrwSource === 'wordpress' ? new \Elvado\Wp\Adapter\WordPressMediaAdapter() : new \Elvado\Wp\Adapter\NativeMediaAdapter(__DIR__), $rrwActor);
                if ($rrwEngineAction === 'media_list') {
                    rrw_json(['status' => 'ok', 'source' => $rrwSource] + $rrwMedia->list($_GET));
                }
                if ($rrwEngineAction === 'media_get') {
                    $rrwItem = $rrwMedia->get((string)($_GET['id'] ?? ''));
                    rrw_json($rrwItem === null ? ['status' => 'error', 'message' => 'Nicht gefunden.'] : ['status' => 'ok', 'item' => $rrwItem], $rrwItem === null ? 404 : 200);
                }
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    rrw_json(['status' => 'error', 'message' => 'Diese Aktion verlangt POST.'], 405);
                }
                if ($rrwEngineAction === 'media_upload') {
                    $rrwF = $_FILES['file'] ?? null;
                    if (!is_array($rrwF) || ($rrwF['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$rrwF['tmp_name'])) {
                        rrw_json(['status' => 'error', 'message' => 'Der Upload ist fehlgeschlagen (Datei fehlt oder ist zu groß).'], 422);
                    }
                    $rrwItem = $rrwMedia->upload((string)$rrwF['tmp_name'], (string)$rrwF['name'], ['title' => $_POST['title'] ?? '', 'alt' => $_POST['alt'] ?? '', 'caption' => $_POST['caption'] ?? '']);
                    $rrwEngineLog('Medium hochgeladen: ' . mb_substr((string)($rrwItem['name'] ?? ''), 0, 80));
                    rrw_json(['status' => 'ok', 'item' => $rrwItem]);
                }
                if ($rrwEngineAction === 'media_update') {
                    rrw_json(['status' => 'ok', 'item' => $rrwMedia->update((string)($rrwEngineB['id'] ?? ''), (array)($rrwEngineB['item'] ?? []))]);
                }
                if ($rrwEngineAction === 'media_delete') {
                    $rrwOk = $rrwMedia->delete((string)($rrwEngineB['id'] ?? ''));
                    $rrwEngineLog('Medium gelöscht (#' . (string)($rrwEngineB['id'] ?? '') . ')');
                    rrw_json($rrwOk ? ['status' => 'ok'] : ['status' => 'error', 'message' => 'Nicht gefunden.'], $rrwOk ? 200 : 404);
                }
            } else {
                $rrwNativeUsers = new \Elvado\Wp\Adapter\NativeUserAdapter(__DIR__ . '/data');
                if ($rrwEngineAction === 'user_list') {
                    $rrwUsers = new \Elvado\Wp\UserService($rrwSource === 'wordpress' ? new \Elvado\Wp\Adapter\WordPressUserAdapter() : $rrwNativeUsers, $rrwActor);
                    rrw_json(['status' => 'ok', 'source' => $rrwSource, 'items' => $rrwUsers->list()]);
                }
                if ($rrwEngineAction === 'user_sync') {
                    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                        rrw_json(['status' => 'error', 'message' => 'Diese Aktion verlangt POST.'], 405);
                    }
                    if ($rrwSource !== 'wordpress') {
                        rrw_json(['status' => 'error', 'message' => 'Der Abgleich braucht die aktive WordPress-Engine.'], 409);
                    }
                    $rrwRes = (new \Elvado\Wp\UserService(new \Elvado\Wp\Adapter\WordPressUserAdapter(), $rrwActor))->sync($rrwNativeUsers);
                    $rrwEngineLog('Benutzer abgeglichen (' . count($rrwRes['created']) . ' neu, ' . count($rrwRes['updated']) . ' aktualisiert)');
                    rrw_json(['status' => 'ok'] + $rrwRes);
                }
            }
        } catch (\Elvado\Wp\PermissionException $e) {
            rrw_json(['status' => 'error', 'message' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            rrw_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
        rrw_json(['status' => 'error', 'message' => 'Unbekannte Aktion'], 404);
    }
    $rrwContent = new \Elvado\Wp\ContentService($rrwAdapter, $rrwActor);
    $rrwType = (string)($_GET['type'] ?? $rrwEngineB['type'] ?? 'post');
    $rrwTax = (string)($_GET['taxonomy'] ?? $rrwEngineB['taxonomy'] ?? 'category');
    try {
        if ($rrwEngineAction === 'content_list') {
            rrw_json(['status' => 'ok', 'source' => $rrwSource] + $rrwContent->list($rrwType, $_GET));
        }
        if ($rrwEngineAction === 'content_get') {
            $rrwItem = $rrwContent->get($rrwType, (string)($_GET['id'] ?? ''));
            rrw_json($rrwItem === null ? ['status' => 'error', 'message' => 'Nicht gefunden.'] : ['status' => 'ok', 'item' => $rrwItem], $rrwItem === null ? 404 : 200);
        }
        if ($rrwEngineAction === 'term_list') {
            rrw_json(['status' => 'ok', 'source' => $rrwSource, 'items' => $rrwContent->terms($rrwTax)]);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            rrw_json(['status' => 'error', 'message' => 'Diese Aktion verlangt POST.'], 405);
        }
        if ($rrwEngineAction === 'content_save') {
            $rrwItem = $rrwContent->save($rrwType, (array)($rrwEngineB['item'] ?? []), !empty($rrwEngineU['superadmin']));
            $rrwEngineLog(($rrwType === 'page' ? 'Seite' : 'Beitrag') . ' gespeichert: ' . mb_substr((string)($rrwItem['title'] ?? ''), 0, 80));
            rrw_json(['status' => 'ok', 'item' => $rrwItem]);
        }
        if ($rrwEngineAction === 'content_delete') {
            $rrwOk = $rrwContent->delete($rrwType, (string)($rrwEngineB['id'] ?? ''), !empty($rrwEngineB['force']));
            $rrwEngineLog(($rrwType === 'page' ? 'Seite' : 'Beitrag') . ' ' . (!empty($rrwEngineB['force']) ? 'gelöscht' : 'in den Papierkorb') . ' (#' . (string)($rrwEngineB['id'] ?? '') . ')');
            rrw_json($rrwOk ? ['status' => 'ok'] : ['status' => 'error', 'message' => 'Nicht gefunden.'], $rrwOk ? 200 : 404);
        }
        if ($rrwEngineAction === 'term_save') {
            rrw_json(['status' => 'ok', 'item' => $rrwContent->saveTerm($rrwTax, (array)($rrwEngineB['item'] ?? []))]);
        }
        if ($rrwEngineAction === 'term_delete') {
            $rrwOk = $rrwContent->deleteTerm($rrwTax, (string)($rrwEngineB['id'] ?? ''));
            rrw_json($rrwOk ? ['status' => 'ok'] : ['status' => 'error', 'message' => 'Nicht gefunden oder nicht löschbar (z. B. Standard-Kategorie).'], $rrwOk ? 200 : 404);
        }
    } catch (\Elvado\Wp\PermissionException $e) {
        rrw_json(['status' => 'error', 'message' => $e->getMessage()], 403);
    } catch (\InvalidArgumentException $e) {
        rrw_json(['status' => 'error', 'message' => $e->getMessage()], 422);
    } catch (\RuntimeException $e) {
        rrw_json(['status' => 'error', 'message' => $e->getMessage()], 422);
    }
}
if (str_starts_with($rrwEngineAction, 'ext_')) {
    try {
        if ($rrwEngineAction === 'ext_safe') {
            (new \Elvado\Wp\ExtensionService(new class implements \Elvado\Wp\Adapter\ExtensionAdapter {
                public function plugins(): array { return []; }
                public function themes(): array { return []; }
                public function activatePlugin(string $id): void {}
                public function deactivatePlugin(string $id): void {}
                public function uninstallPlugin(string $id): void {}
                public function activeTheme(): array { return ['template' => '', 'stylesheet' => '']; }
                public function switchTheme(string $slug): void {}
            }, new \Elvado\Wp\ExtensionInstaller($rrwEngine), $rrwEngine, $rrwActor))->setSafe(!empty($rrwEngineB['on']));
            rrw_json(['status' => 'ok'] + rrw_wpe_status($rrwEngine, $rrwEngineDb));
        }
        if (!$rrwEngine->isActive()) {
            rrw_json(['status' => 'error', 'message' => 'Die WordPress-Engine ist nicht aktiv.'], 409);
        }
        $GLOBALS['rrw_wpe_engine'] = $rrwEngine;
        $GLOBALS['rrw_wpe_db'] = $rrwEngineDb;
        $GLOBALS['rrw_wpe_opts'] = [];
        rrw_wpe_guard_output();
        require __DIR__ . '/wp-engine-boot.php';
        if (!\Elvado\Wp\Bridge::booted()) {
            rrw_json(['status' => 'error', 'message' => 'WordPress ließ sich nicht starten.'], 500);
        }
        $rrwIncident = rrw_wpe_after_boot($rrwEngine) ?? $rrwEngine->incident();
        $rrwExt = new \Elvado\Wp\ExtensionService(new \Elvado\Wp\Adapter\WordPressExtensionAdapter(), new \Elvado\Wp\ExtensionInstaller($rrwEngine), $rrwEngine, $rrwActor);
        $rrwKind = (string)($_GET['kind'] ?? $rrwEngineB['kind'] ?? 'plugin');
        $rrwSafe = ['safe_mode' => $rrwEngine->safe(), 'incident' => $rrwIncident];
        if ($rrwEngineAction === 'ext_list') {
            rrw_json(['status' => 'ok', 'kind' => $rrwKind, 'items' => $rrwExt->list($rrwKind)] + $rrwSafe);
        }
        if ($rrwEngineAction === 'ext_search') {
            $rrwR = $rrwExt->search($rrwKind, (string)($_GET['q'] ?? ''), (int)($_GET['page'] ?? 1));
            rrw_json(['status' => $rrwR['ok'] ? 'ok' : 'error'] + $rrwR, $rrwR['ok'] ? 200 : 502);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            rrw_json(['status' => 'error', 'message' => 'Diese Aktion verlangt POST.'], 405);
        }
        @set_time_limit(300);
        if ($rrwEngineAction === 'ext_install') {
            $rrwR = $rrwExt->installFromDirectory($rrwKind, (string)($rrwEngineB['slug'] ?? ''), !empty($rrwEngineB['update']));
            $rrwEngineLog(($rrwKind === 'plugin' ? 'Plugin' : 'Theme') . ' installiert: ' . $rrwR['slug'] . ' ' . $rrwR['version']);
            rrw_json(['status' => 'ok', 'result' => $rrwR, 'items' => $rrwExt->list($rrwKind)] + $rrwSafe);
        }
        if ($rrwEngineAction === 'ext_upload') {
            $rrwF = $_FILES['file'] ?? null;
            if (!is_array($rrwF) || ($rrwF['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$rrwF['tmp_name'])) {
                rrw_json(['status' => 'error', 'message' => 'Der Upload ist fehlgeschlagen (Datei fehlt oder ist zu groß).'], 422);
            }
            $rrwR = $rrwExt->installUpload((string)($_POST['kind'] ?? $rrwKind), (string)$rrwF['tmp_name'], !empty($_POST['update']));
            $rrwEngineLog(($rrwKind === 'plugin' ? 'Plugin' : 'Theme') . ' hochgeladen: ' . $rrwR['slug'] . ' ' . $rrwR['version']);
            rrw_json(['status' => 'ok', 'result' => $rrwR, 'items' => $rrwExt->list((string)($_POST['kind'] ?? $rrwKind))] + $rrwSafe);
        }
        $rrwId = (string)($rrwEngineB['id'] ?? '');
        if ($rrwEngineAction === 'ext_activate') {
            $rrwExt->activate($rrwKind, $rrwId);
            $rrwEngineLog(($rrwKind === 'plugin' ? 'Plugin aktiviert: ' : 'Theme aktiviert: ') . $rrwId);
            rrw_json(['status' => 'ok', 'items' => $rrwExt->list($rrwKind)] + $rrwSafe);
        }
        if ($rrwEngineAction === 'ext_deactivate') {
            $rrwExt->deactivate($rrwId);
            $rrwEngineLog('Plugin deaktiviert: ' . $rrwId);
            rrw_json(['status' => 'ok', 'items' => $rrwExt->list('plugin')] + $rrwSafe);
        }
        if ($rrwEngineAction === 'ext_delete') {
            $rrwExt->delete($rrwKind, $rrwId, !empty($rrwEngineB['purge']));
            $rrwEngineLog(($rrwKind === 'plugin' ? 'Plugin entfernt: ' : 'Theme entfernt: ') . $rrwId);
            rrw_json(['status' => 'ok', 'items' => $rrwExt->list($rrwKind)] + $rrwSafe);
        }
    } catch (\Elvado\Wp\PermissionException $e) {
        rrw_json(['status' => 'error', 'message' => $e->getMessage()], 403);
    } catch (\InvalidArgumentException | \RuntimeException $e) {
        rrw_json(['status' => 'error', 'message' => $e->getMessage()], 422);
    }
    rrw_json(['status' => 'error', 'message' => 'Unbekannte Aktion'], 404);
}
if (preg_match('/^(nav|widgets|blocks)_/', $rrwEngineAction) === 1) {
    $rrwSource = (string)($_GET['source'] ?? ($rrwEngine->isActive() ? 'wordpress' : 'native'));
    $rrwNeedsWp = $rrwSource === 'wordpress' || str_starts_with($rrwEngineAction, 'blocks_') || $rrwEngineAction === 'nav_import';
    if (!in_array($rrwSource, ['native', 'wordpress'], true)) {
        rrw_json(['status' => 'error', 'message' => 'Unbekannte Quelle.'], 400);
    }
    if ($rrwNeedsWp) {
        if (!$rrwEngine->isActive()) {
            rrw_json(['status' => 'error', 'message' => 'Die WordPress-Engine ist nicht aktiv.'], 409);
        }
        $GLOBALS['rrw_wpe_engine'] = $rrwEngine;
        $GLOBALS['rrw_wpe_db'] = $rrwEngineDb;
        $GLOBALS['rrw_wpe_opts'] = [];
        rrw_wpe_guard_output();
        require __DIR__ . '/wp-engine-boot.php';
        if (!\Elvado\Wp\Bridge::booted()) {
            rrw_json(['status' => 'error', 'message' => 'WordPress ließ sich nicht starten.'], 500);
        }
        rrw_wpe_after_boot($rrwEngine);
    }
    $rrwIsPost = $_SERVER['REQUEST_METHOD'] === 'POST';
    try {
        if (str_starts_with($rrwEngineAction, 'nav_')) {
            $rrwNavAdapter = $rrwSource === 'wordpress' ? new \Elvado\Wp\Adapter\WordPressNavigationAdapter() : new \Elvado\Wp\Adapter\NativeNavigationAdapter(__DIR__ . '/data');
            $rrwNav = new \Elvado\Wp\NavigationService($rrwNavAdapter, $rrwActor);
            if ($rrwEngineAction === 'nav_list') {
                rrw_json(['status' => 'ok', 'source' => $rrwSource, 'menus' => $rrwNav->menus(), 'locations' => $rrwNav->locations()]);
            }
            if ($rrwEngineAction === 'nav_get') {
                $rrwT = $rrwNav->tree((string)($_GET['menu'] ?? ''));
                rrw_json($rrwT === null ? ['status' => 'error', 'message' => 'Menü nicht gefunden.'] : ['status' => 'ok', 'menu' => $rrwT], $rrwT === null ? 404 : 200);
            }
            if (!$rrwIsPost) {
                rrw_json(['status' => 'error', 'message' => 'Diese Aktion verlangt POST.'], 405);
            }
            if ($rrwEngineAction === 'nav_create') {
                $rrwM = $rrwNav->create((string)($rrwEngineB['name'] ?? ''));
                $rrwEngineLog('Menü angelegt: ' . $rrwM['name']);
                rrw_json(['status' => 'ok', 'menu' => $rrwM]);
            }
            if ($rrwEngineAction === 'nav_rename') {
                $rrwNav->rename((string)($rrwEngineB['menu'] ?? ''), (string)($rrwEngineB['name'] ?? ''));
                rrw_json(['status' => 'ok']);
            }
            if ($rrwEngineAction === 'nav_delete') {
                $rrwOk = $rrwNav->delete((string)($rrwEngineB['menu'] ?? ''));
                $rrwEngineLog('Menü gelöscht (#' . (string)($rrwEngineB['menu'] ?? '') . ')');
                rrw_json($rrwOk ? ['status' => 'ok'] : ['status' => 'error', 'message' => 'Nicht gefunden.'], $rrwOk ? 200 : 404);
            }
            if ($rrwEngineAction === 'nav_save') {
                $rrwItems = $rrwNav->save((string)($rrwEngineB['menu'] ?? ''), $rrwEngineB['items'] ?? []);
                $rrwEngineLog('Menü gespeichert (#' . (string)($rrwEngineB['menu'] ?? '') . ', ' . count($rrwItems) . ' Einträge oben)');
                rrw_json(['status' => 'ok', 'items' => $rrwItems]);
            }
            if ($rrwEngineAction === 'nav_assign') {
                $rrwNav->assign((string)($rrwEngineB['location'] ?? ''), (string)($rrwEngineB['menu'] ?? ''));
                rrw_json(['status' => 'ok', 'locations' => $rrwNav->locations()]);
            }
            if ($rrwEngineAction === 'nav_import') {
                $r = $rrwNav->importFrom(new \Elvado\Wp\Adapter\NativeNavigationAdapter(__DIR__ . '/data'), (string)($rrwEngineB['from'] ?? ''), (string)($rrwEngineB['name'] ?? ''));
                $rrwEngineLog('Menü übernommen: ' . $r['menu']['name'] . ' (' . $r['items'] . ' Einträge)');
                rrw_json(['status' => 'ok'] + $r);
            }
        } elseif (str_starts_with($rrwEngineAction, 'widgets_')) {
            $rrwWd = new \Elvado\Wp\WidgetService($rrwSource === 'wordpress' ? new \Elvado\Wp\Adapter\WordPressWidgetAdapter() : new \Elvado\Wp\Adapter\NativeWidgetAdapter(__DIR__ . '/data'), $rrwActor);
            if ($rrwEngineAction === 'widgets_overview') {
                rrw_json(['status' => 'ok', 'source' => $rrwSource] + $rrwWd->overview());
            }
            if (!$rrwIsPost) {
                rrw_json(['status' => 'error', 'message' => 'Diese Aktion verlangt POST.'], 405);
            }
            if ($rrwEngineAction === 'widgets_add') {
                rrw_json(['status' => 'ok', 'widget' => $rrwWd->add((string)($rrwEngineB['area'] ?? ''), (string)($rrwEngineB['id_base'] ?? ''), $rrwEngineB['settings'] ?? [])]);
            }
            if ($rrwEngineAction === 'widgets_update') {
                rrw_json(['status' => 'ok', 'widget' => $rrwWd->update((string)($rrwEngineB['id'] ?? ''), $rrwEngineB['settings'] ?? [])]);
            }
            if ($rrwEngineAction === 'widgets_move') {
                $rrwWd->move($rrwEngineB['layout'] ?? null);
                rrw_json(['status' => 'ok'] + $rrwWd->overview());
            }
            if ($rrwEngineAction === 'widgets_delete') {
                $rrwOk = $rrwWd->delete((string)($rrwEngineB['id'] ?? ''));
                rrw_json($rrwOk ? ['status' => 'ok'] + $rrwWd->overview() : ['status' => 'error', 'message' => 'Nicht gefunden.'], $rrwOk ? 200 : 404);
            }
        } else {
            $rrwBl = new \Elvado\Wp\BlockService($rrwActor);
            if ($rrwEngineAction === 'blocks_registry') {
                rrw_json(['status' => 'ok', 'blocks' => $rrwBl->registry()]);
            }
            if (!$rrwIsPost) {
                rrw_json(['status' => 'error', 'message' => 'Diese Aktion verlangt POST.'], 405);
            }
            $rrwMk = (string)($rrwEngineB['markup'] ?? '');
            if ($rrwEngineAction === 'blocks_parse') {
                rrw_json(['status' => 'ok', 'blocks' => $rrwBl->parse($rrwMk), 'check' => $rrwBl->check($rrwMk)]);
            }
            if ($rrwEngineAction === 'blocks_check') {
                rrw_json(['status' => 'ok'] + $rrwBl->check($rrwMk));
            }
            if ($rrwEngineAction === 'blocks_render') {
                rrw_json(['status' => 'ok', 'html' => $rrwBl->render($rrwMk)]);
            }
            if ($rrwEngineAction === 'blocks_serialize') {
                rrw_json(['status' => 'ok', 'markup' => $rrwBl->serialize($rrwEngineB['blocks'] ?? [])]);
            }
            if ($rrwEngineAction === 'blocks_convert') {
                rrw_json(['status' => 'ok'] + $rrwBl->convert($rrwMk, (string)($rrwEngineB['to'] ?? 'wp')));
            }
        }
    } catch (\Elvado\Wp\PermissionException $e) {
        rrw_json(['status' => 'error', 'message' => $e->getMessage()], 403);
    } catch (\InvalidArgumentException | \RuntimeException $e) {
        rrw_json(['status' => 'error', 'message' => $e->getMessage()], 422);
    }
    rrw_json(['status' => 'error', 'message' => 'Unbekannte Aktion'], 404);
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

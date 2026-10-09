<?php
declare(strict_types=1);
// Verwaltungs-API der WordPress-Engine (eigener Einstieg, damit echtes WordPress in einem sauberen globalen Gültigkeitsbereich startet und nicht in den Variablen von api.php).
// Aktionen: engine_status, engine_prepare, engine_core, engine_db_test, engine_db_install, engine_db_unify, engine_analyze, engine_mode, engine_remove;
// Inhalte über den Dienst/Adapter: content_list, content_get, content_save, content_delete, term_list, term_save, term_delete; Medien media_list|get|upload|update|delete; Benutzer user_list, user_sync; Plugins/Themes ext_list|search|install|upload|activate|deactivate|delete|safe; Menüs nav_list|get|create|rename|delete|save|assign|import; Widgets widgets_overview|add|update|move|delete; Blöcke blocks_registry|parse|check|render|serialize|convert; Migration (nur Trockenlauf, schreibt nichts außer dem Bericht) migration_plan, migration_report; echte Migration (nur mit Bestätigung, protokolliert, zurückbaubar) migration_run, migration_rollback, migration_runs; Demo: engine_demo_setup; Systemstatus system_status, updates_overview, updates_check.
// Rechte: Administratoren alles; Autoren Inhalte/Medien nur eigene (Dienste prüfen), Seiten, Begriffe, Benutzer und Engine nur Administratoren. Quelle: ?source=native (nur lesen) oder wordpress (Standard, wenn die Engine aktiv ist).
// Die Anmeldeprüfung kommt aus api.php (ELVADO_API_LIB_ONLY); in der Demo sind alle Aktionen außer engine_status gesperrt.
define('ELVADO_API_LIB_ONLY', true);
require __DIR__ . '/api.php';
require_once __DIR__ . '/src/autoload.php';
require_once __DIR__ . '/lib/wpengine.php';

$elvadoEngineAction = (string)($_GET['action'] ?? 'engine_status');   // eigene Namen: WordPress überschreibt beim Start globale Variablen wie $action
$elvadoEngineSiteFile = $siteFile;
$elvadoEngineU = elvado_auth(false);
$elvadoActor = \Elvado\Wp\Actor::fromAuth($elvadoEngineU);
// Inhalte und Medien dürfen angemeldete Personen nach ihren Rechten (Dienst prüft); alles andere – Engine-Verwaltung, Benutzer, Begriffe ändern – nur Administratoren.
if (!$elvadoActor->isAdmin() && !in_array($elvadoEngineAction, ['content_list', 'content_get', 'content_save', 'content_delete', 'term_list', 'term_save', 'term_delete', 'media_list', 'media_get', 'media_upload', 'media_update', 'media_delete', 'nav_list', 'nav_get', 'blocks_registry', 'blocks_parse', 'blocks_serialize', 'blocks_render', 'blocks_check', 'blocks_convert'], true)) {
    elvado_json(['status' => 'error', 'message' => 'Nur Administratoren dürfen diese Aktion ausführen'], 403);
}
if (function_exists('elvado_demo_enabled') && elvado_demo_enabled() && $elvadoEngineAction !== 'engine_status') {
    // Demo: ohne demo-engine.json gesperrt; mit ihr läuft die Engine (echter Core, alle Funktionen) – gesperrt bleiben nur Aufbau von Hand und fremder Programmcode (Plugin-/Theme-Installation, -Upload, -Löschen)
    if (!function_exists('elvado_demo_engine_enabled') || !elvado_demo_engine_enabled()) {
        elvado_json(['status' => 'error', 'message' => 'In der Demo gesperrt: Die WordPress-Engine lässt sich nur in einer eigenen ElvadoPress-Installation einrichten.'], 403);
    }
    if (in_array($elvadoEngineAction, ['ext_install', 'ext_delete'], true)) {   // nur die freigegebenen, bekannten Pakete aus dem WordPress-Verzeichnis
        $elvadoDK = (string)($_GET['kind'] ?? (elvado_body()['kind'] ?? 'plugin'));
        $elvadoDB = elvado_body();
        $elvadoDS = (string)($elvadoDB['slug'] ?? $elvadoDB['id'] ?? '');
        if (!elvado_demo_engine_allowed($elvadoDK, preg_replace('#/.*$#', '', $elvadoDS))) {
            elvado_json(['status' => 'error', 'message' => 'In der Demo lassen sich nur ausgewählte, bekannte Plugins und Themes aus dem WordPress-Verzeichnis installieren (die Demo teilt sich den Server mit anderen Websites). Eigene ZIP-Dateien und andere Pakete sind gesperrt.', 'demo' => true], 403);
        }
    }
    if (in_array($elvadoEngineAction, ELVADO_DEMO_ENGINE_BLOCKED, true)) {
        elvado_json(['status' => 'error', 'message' => in_array($elvadoEngineAction, ['ext_install', 'ext_upload', 'ext_delete'], true)
            ? 'In der Demo gesperrt: Plugins und Themes lassen sich nur in einer eigenen ElvadoPress-Installation installieren (die Demo teilt sich den Server mit anderen Websites). Vorinstallierte lassen sich aktivieren.'
            : 'In der Demo ist die Engine bereits vorbereitet (Aufbau, Datenbank und Betriebsart verwaltet die Demo selbst).', 'demo' => true], 403);
    }
}
$elvadoEngineB = $_SERVER['REQUEST_METHOD'] === 'POST' ? elvado_body() : [];
$elvadoEngine = elvado_wpe();
$elvadoEngineDb = new \Elvado\Wp\DbConfig($elvadoEngine);
$elvadoEngineLog = static function (string $what) use ($elvadoEngineU, $activityLogFile): void {
    if (function_exists('elvado_log_activity')) {
        elvado_log_activity($activityLogFile, $elvadoEngineU, 'wp_engine', $what);
    }
};
@set_time_limit(300);

if ($elvadoEngineAction === 'engine_status') {
    elvado_json(['status' => 'ok'] + elvado_wpe_status($elvadoEngine, $elvadoEngineDb));
}
if ($elvadoEngineAction === 'engine_prepare') {
    elvado_json(['status' => 'ok'] + elvado_wpe_prepare($elvadoEngine));
}
if ($elvadoEngineAction === 'engine_demo_setup') {
    // Nur Demo mit demo-engine.json: Core (falls nötig), eigene Datenbank leeren, WordPress-Tabellen anlegen, Betriebsart „aktiv“. Wiederholbar; nach dem Zurücksetzen der Demo läuft es erneut.
    if (!function_exists('elvado_demo_engine_enabled') || !elvado_demo_engine_enabled()) {
        elvado_json(['status' => 'error', 'message' => 'Diese Aktion gibt es nur in der Demo mit vorbereiteter Engine.'], 403);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        elvado_json(['status' => 'error', 'message' => 'Diese Aktion verlangt POST.'], 405);
    }
    $elvadoDc = elvado_demo_engine_config();
    $elvadoLock = @fopen($elvadoEngine->stateDir() . '/demo-setup.lock', 'c') ?: @fopen(sys_get_temp_dir() . '/ep-demo-setup-' . md5(__DIR__) . '.lock', 'c');
    if ($elvadoLock) {
        @flock($elvadoLock, LOCK_EX);
    }
    $elvadoEngine->protect();
    if ($elvadoEngine->isActive() && !empty($elvadoEngine->state()['db']['ready'])) {
        elvado_json(['status' => 'ok', 'message' => 'Die Demo-Engine ist bereit.'] + elvado_wpe_status($elvadoEngine, $elvadoEngineDb));
    }
    @set_time_limit(300);
    if ($elvadoEngine->corePath() === null) {
        $elvadoCores = glob($elvadoEngine->coreRoot() . '/core-*', GLOB_ONLYDIR) ?: [];
        usort($elvadoCores, fn($a, $b) => version_compare(substr(basename($b), 5), substr(basename($a), 5)));
        if ($elvadoCores !== []) {   // Core-Dateien liegen schon da (nur der Zustand wurde zurückgesetzt)
            $elvadoEngine->save(['core' => basename($elvadoCores[0]), 'installed_at' => date('c')]);
        } else {
            $r = elvado_wpe_install_core($elvadoEngine, '', $elvadoDc['core_zip']);
            if (!$r['ok']) {
                elvado_json(['status' => 'error', 'message' => 'Der WordPress-Core ließ sich nicht einspielen: ' . $r['message']], 502);
            }
            $elvadoEngineLog('Demo: WordPress ' . $r['version'] . ' eingespielt');
        }
    }
    if (!elvado_demo_engine_drop_tables($elvadoDc)) {
        elvado_json(['status' => 'error', 'message' => 'Die Demo-Datenbank ließ sich nicht leeren (Zugang prüfen).'], 502);
    }
    $pre = elvado_wpe_pre_install($elvadoEngine, $elvadoEngineDb, ['host' => $elvadoDc['host'], 'name' => $elvadoDc['name'], 'user' => $elvadoDc['user'], 'pass' => $elvadoDc['pass'], 'prefix' => $elvadoDc['prefix']]);
    if (!$pre['ok']) {
        elvado_json(['status' => 'error', 'message' => $pre['message']], 502);
    }
    $GLOBALS['elvado_wpe_engine'] = $elvadoEngine;
    $GLOBALS['elvado_wpe_db'] = $elvadoEngineDb;
    $GLOBALS['elvado_wpe_opts'] = ['installing' => true];
    elvado_wpe_guard_output();
    require __DIR__ . '/wp-engine-boot.php';
    $res = \Elvado\Wp\Bridge::installSchema(elvado_demo_config()['site_name'] ?? 'ElvadoPress', 'demo@example.invalid');
    if (!$res['ok']) {
        elvado_json(['status' => 'error', 'message' => $res['message']], 502);
    }
    $elvadoEngine->save(['mode' => 'installed', 'db' => ['ready' => true, 'checked_at' => date('c'), 'prefix' => $pre['prefix'], 'server' => $pre['server']]]);
    $elvadoEngine->setMode('active');
    $elvadoEngine->log('Demo: Engine eingerichtet und aktiv');
    elvado_json(['status' => 'ok', 'message' => 'Die Demo-Engine ist eingerichtet und aktiv.'] + elvado_wpe_status($elvadoEngine, $elvadoEngineDb));
}
if ($elvadoEngineAction === 'engine_core') {
    $r = elvado_wpe_install_core($elvadoEngine, (string)($elvadoEngineB['version'] ?? ''));
    if ($r['ok']) {
        $elvadoEngineLog('WordPress ' . $r['version'] . ' eingespielt');
    }
    elvado_json(['status' => $r['ok'] ? 'ok' : 'error'] + $r + elvado_wpe_status($elvadoEngine, $elvadoEngineDb), $r['ok'] ? 200 : 422);
}
if ($elvadoEngineAction === 'engine_db_test') {
    // Eine Datenbank für alles: ist die gemeinsame Datenbank (System → Datenbank) eingerichtet, zählt von der Anfrage nur das Präfix
    $c = $elvadoEngineDb->withShared((array)($elvadoEngineB['db'] ?? []));
    $t = $elvadoEngineDb->test($c);
    if ($t['ok'] && ($pc = $elvadoEngineDb->prefixConflict((string)($c['prefix'] ?? '')))) {
        $t = ['ok' => false, 'message' => $pc] + $t;
    }
    elvado_json(['status' => $t['ok'] ? 'ok' : 'error'] + $t, $t['ok'] ? 200 : 422);
}
if ($elvadoEngineAction === 'engine_db_unify') {   // eigene Verbindung einer älteren Engine-Einrichtung → gemeinsame Datenbank des CMS
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        elvado_json(['status' => 'error', 'message' => 'Diese Aktion verlangt POST.'], 405);
    }
    $u = $elvadoEngineDb->unify();
    if ($u['ok']) {
        $elvadoEngineLog('Datenbank-Verbindung der Engine mit der gemeinsamen Datenbank zusammengeführt');
    }
    elvado_json(['status' => $u['ok'] ? 'ok' : 'error', 'message' => $u['message']] + elvado_wpe_status($elvadoEngine, $elvadoEngineDb), $u['ok'] ? 200 : 422);
}
if ($elvadoEngineAction === 'engine_db_install') {
    $pre = elvado_wpe_pre_install($elvadoEngine, $elvadoEngineDb, $elvadoEngineDb->withShared((array)($elvadoEngineB['db'] ?? [])));
    if (!$pre['ok']) {
        elvado_json(['status' => 'error', 'message' => $pre['message']], 422);
    }
    // WordPress im Installationsmodus starten (globaler Gültigkeitsbereich) und die Tabellen anlegen
    $GLOBALS['elvado_wpe_engine'] = $elvadoEngine;
    $GLOBALS['elvado_wpe_db'] = $elvadoEngineDb;
    $GLOBALS['elvado_wpe_opts'] = ['installing' => true];
    elvado_wpe_guard_output();
    require __DIR__ . '/wp-engine-boot.php';
    $elvadoSite = elvado_read_json($elvadoEngineSiteFile, []);
    $res = \Elvado\Wp\Bridge::installSchema((string)($elvadoSite['portal']['site_name'] ?? ($elvadoEngineB['title'] ?? '')), (string)($elvadoEngineB['email'] ?? 'admin@example.invalid'));
    if (!$res['ok']) {
        elvado_json(['status' => 'error', 'message' => $res['message']], 422);
    }
    $elvadoEngine->save(['mode' => 'installed', 'db' => ['ready' => true, 'checked_at' => date('c'), 'prefix' => $pre['prefix'], 'server' => $pre['server']]]);
    $elvadoEngine->log('WordPress-Tabellen angelegt (Präfix ' . $pre['prefix'] . ')');
    $elvadoEngineLog('WordPress-Datenbank eingerichtet');
    elvado_json(['status' => 'ok', 'message' => 'WordPress ist eingerichtet. Die Website selbst bleibt unverändert.'] + elvado_wpe_status($elvadoEngine, $elvadoEngineDb));
}
if ($elvadoEngineAction === 'engine_analyze' || ($elvadoEngineAction === 'engine_mode' && (string)($elvadoEngineB['mode'] ?? '') === 'active')) {
    if (!$elvadoEngine->isInstalled() || empty($elvadoEngine->state()['db']['ready'])) {
        elvado_json(['status' => 'error', 'message' => 'Die WordPress-Engine ist noch nicht eingerichtet.'], 422);
    }
    $t0 = microtime(true);
    $GLOBALS['elvado_wpe_engine'] = $elvadoEngine;
    $GLOBALS['elvado_wpe_db'] = $elvadoEngineDb;
    $GLOBALS['elvado_wpe_opts'] = [];
    elvado_wpe_guard_output();
    require __DIR__ . '/wp-engine-boot.php';
    $ms = (int)round((microtime(true) - $t0) * 1000);
    if (!\Elvado\Wp\Bridge::booted()) {
        elvado_json(['status' => 'error', 'message' => 'WordPress ließ sich nicht starten.'], 500);
    }
    elvado_wpe_after_boot($elvadoEngine);
    $wp = new \Elvado\Wp\Adapter\WordPressAdapter();
    if ($elvadoEngineAction === 'engine_mode') {
        $m = $elvadoEngine->setMode('active');
        if ($m['ok']) {
            $elvadoEngineLog('WordPress-Engine aktiviert');
        }
        elvado_json(['status' => $m['ok'] ? 'ok' : 'error', 'message' => $m['ok'] ? 'Die WordPress-Engine ist aktiv.' : $m['message'], 'boot_ms' => $ms] + elvado_wpe_status($elvadoEngine, $elvadoEngineDb), $m['ok'] ? 200 : 422);
    }
    $native = new \Elvado\Wp\Adapter\NativeAdapter(__DIR__, __DIR__ . '/data');
    elvado_json(['status' => 'ok', 'boot_ms' => $ms, 'native' => ['name' => $native->name(), 'counts' => $native->counts()], 'wordpress' => ['name' => $wp->name(), 'counts' => $wp->counts(), 'info' => $wp->info()]]);
}
if (preg_match('/^(content|term|media|user)_/', $elvadoEngineAction) === 1) {
    $elvadoSource = (string)($_GET['source'] ?? ($elvadoEngine->isActive() ? 'wordpress' : 'native'));
    if ($elvadoSource === 'wordpress') {
        if (!$elvadoEngine->isActive()) {
            elvado_json(['status' => 'error', 'message' => 'Die WordPress-Engine ist nicht aktiv.'], 409);
        }
        $GLOBALS['elvado_wpe_engine'] = $elvadoEngine;
        $GLOBALS['elvado_wpe_db'] = $elvadoEngineDb;
        $GLOBALS['elvado_wpe_opts'] = [];
        elvado_wpe_guard_output();
        require __DIR__ . '/wp-engine-boot.php';
        if (!\Elvado\Wp\Bridge::booted()) {
            elvado_json(['status' => 'error', 'message' => 'WordPress ließ sich nicht starten.'], 500);
        }
        elvado_wpe_after_boot($elvadoEngine);
        $elvadoAdapter = new \Elvado\Wp\Adapter\WordPressAdapter();
    } elseif ($elvadoSource === 'native') {
        $elvadoAdapter = new \Elvado\Wp\Adapter\NativeAdapter(__DIR__, __DIR__ . '/data');
    } else {
        elvado_json(['status' => 'error', 'message' => 'Unbekannte Quelle.'], 400);
    }
    if (str_starts_with($elvadoEngineAction, 'media_') || str_starts_with($elvadoEngineAction, 'user_')) {
        try {
            if (str_starts_with($elvadoEngineAction, 'media_')) {
                $elvadoMedia = new \Elvado\Wp\MediaService($elvadoSource === 'wordpress' ? new \Elvado\Wp\Adapter\WordPressMediaAdapter() : new \Elvado\Wp\Adapter\NativeMediaAdapter(__DIR__), $elvadoActor);
                if ($elvadoEngineAction === 'media_list') {
                    elvado_json(['status' => 'ok', 'source' => $elvadoSource] + $elvadoMedia->list($_GET));
                }
                if ($elvadoEngineAction === 'media_get') {
                    $elvadoItem = $elvadoMedia->get((string)($_GET['id'] ?? ''));
                    elvado_json($elvadoItem === null ? ['status' => 'error', 'message' => 'Nicht gefunden.'] : ['status' => 'ok', 'item' => $elvadoItem], $elvadoItem === null ? 404 : 200);
                }
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    elvado_json(['status' => 'error', 'message' => 'Diese Aktion verlangt POST.'], 405);
                }
                if ($elvadoEngineAction === 'media_upload') {
                    $elvadoF = $_FILES['file'] ?? null;
                    if (!is_array($elvadoF) || ($elvadoF['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$elvadoF['tmp_name'])) {
                        elvado_json(['status' => 'error', 'message' => 'Der Upload ist fehlgeschlagen (Datei fehlt oder ist zu groß).'], 422);
                    }
                    $elvadoItem = $elvadoMedia->upload((string)$elvadoF['tmp_name'], (string)$elvadoF['name'], ['title' => $_POST['title'] ?? '', 'alt' => $_POST['alt'] ?? '', 'caption' => $_POST['caption'] ?? '']);
                    $elvadoEngineLog('Medium hochgeladen: ' . mb_substr((string)($elvadoItem['name'] ?? ''), 0, 80));
                    elvado_json(['status' => 'ok', 'item' => $elvadoItem]);
                }
                if ($elvadoEngineAction === 'media_update') {
                    elvado_json(['status' => 'ok', 'item' => $elvadoMedia->update((string)($elvadoEngineB['id'] ?? ''), (array)($elvadoEngineB['item'] ?? []))]);
                }
                if ($elvadoEngineAction === 'media_delete') {
                    $elvadoOk = $elvadoMedia->delete((string)($elvadoEngineB['id'] ?? ''));
                    $elvadoEngineLog('Medium gelöscht (#' . (string)($elvadoEngineB['id'] ?? '') . ')');
                    elvado_json($elvadoOk ? ['status' => 'ok'] : ['status' => 'error', 'message' => 'Nicht gefunden.'], $elvadoOk ? 200 : 404);
                }
            } else {
                $elvadoNativeUsers = new \Elvado\Wp\Adapter\NativeUserAdapter(__DIR__ . '/data');
                if ($elvadoEngineAction === 'user_list') {
                    $elvadoUsers = new \Elvado\Wp\UserService($elvadoSource === 'wordpress' ? new \Elvado\Wp\Adapter\WordPressUserAdapter() : $elvadoNativeUsers, $elvadoActor);
                    elvado_json(['status' => 'ok', 'source' => $elvadoSource, 'items' => $elvadoUsers->list()]);
                }
                if ($elvadoEngineAction === 'user_sync') {
                    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                        elvado_json(['status' => 'error', 'message' => 'Diese Aktion verlangt POST.'], 405);
                    }
                    if ($elvadoSource !== 'wordpress') {
                        elvado_json(['status' => 'error', 'message' => 'Der Abgleich braucht die aktive WordPress-Engine.'], 409);
                    }
                    $elvadoRes = (new \Elvado\Wp\UserService(new \Elvado\Wp\Adapter\WordPressUserAdapter(), $elvadoActor))->sync($elvadoNativeUsers);
                    $elvadoEngineLog('Benutzer abgeglichen (' . count($elvadoRes['created']) . ' neu, ' . count($elvadoRes['updated']) . ' aktualisiert)');
                    elvado_json(['status' => 'ok'] + $elvadoRes);
                }
            }
        } catch (\Elvado\Wp\PermissionException $e) {
            elvado_json(['status' => 'error', 'message' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            elvado_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
        elvado_json(['status' => 'error', 'message' => 'Unbekannte Aktion'], 404);
    }
    $elvadoContent = new \Elvado\Wp\ContentService($elvadoAdapter, $elvadoActor);
    $elvadoType = (string)($_GET['type'] ?? $elvadoEngineB['type'] ?? 'post');
    $elvadoTax = (string)($_GET['taxonomy'] ?? $elvadoEngineB['taxonomy'] ?? 'category');
    try {
        if ($elvadoEngineAction === 'content_list') {
            elvado_json(['status' => 'ok', 'source' => $elvadoSource] + $elvadoContent->list($elvadoType, $_GET));
        }
        if ($elvadoEngineAction === 'content_get') {
            $elvadoItem = $elvadoContent->get($elvadoType, (string)($_GET['id'] ?? ''));
            elvado_json($elvadoItem === null ? ['status' => 'error', 'message' => 'Nicht gefunden.'] : ['status' => 'ok', 'item' => $elvadoItem], $elvadoItem === null ? 404 : 200);
        }
        if ($elvadoEngineAction === 'term_list') {
            elvado_json(['status' => 'ok', 'source' => $elvadoSource, 'items' => $elvadoContent->terms($elvadoTax)]);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            elvado_json(['status' => 'error', 'message' => 'Diese Aktion verlangt POST.'], 405);
        }
        if ($elvadoEngineAction === 'content_save') {
            $elvadoItem = $elvadoContent->save($elvadoType, (array)($elvadoEngineB['item'] ?? []), !empty($elvadoEngineU['superadmin']));
            $elvadoEngineLog(($elvadoType === 'page' ? 'Seite' : 'Beitrag') . ' gespeichert: ' . mb_substr((string)($elvadoItem['title'] ?? ''), 0, 80));
            elvado_json(['status' => 'ok', 'item' => $elvadoItem]);
        }
        if ($elvadoEngineAction === 'content_delete') {
            $elvadoOk = $elvadoContent->delete($elvadoType, (string)($elvadoEngineB['id'] ?? ''), !empty($elvadoEngineB['force']));
            $elvadoEngineLog(($elvadoType === 'page' ? 'Seite' : 'Beitrag') . ' ' . (!empty($elvadoEngineB['force']) ? 'gelöscht' : 'in den Papierkorb') . ' (#' . (string)($elvadoEngineB['id'] ?? '') . ')');
            elvado_json($elvadoOk ? ['status' => 'ok'] : ['status' => 'error', 'message' => 'Nicht gefunden.'], $elvadoOk ? 200 : 404);
        }
        if ($elvadoEngineAction === 'term_save') {
            elvado_json(['status' => 'ok', 'item' => $elvadoContent->saveTerm($elvadoTax, (array)($elvadoEngineB['item'] ?? []))]);
        }
        if ($elvadoEngineAction === 'term_delete') {
            $elvadoOk = $elvadoContent->deleteTerm($elvadoTax, (string)($elvadoEngineB['id'] ?? ''));
            elvado_json($elvadoOk ? ['status' => 'ok'] : ['status' => 'error', 'message' => 'Nicht gefunden oder nicht löschbar (z. B. Standard-Kategorie).'], $elvadoOk ? 200 : 404);
        }
    } catch (\Elvado\Wp\PermissionException $e) {
        elvado_json(['status' => 'error', 'message' => $e->getMessage()], 403);
    } catch (\InvalidArgumentException $e) {
        elvado_json(['status' => 'error', 'message' => $e->getMessage()], 422);
    } catch (\RuntimeException $e) {
        elvado_json(['status' => 'error', 'message' => $e->getMessage()], 422);
    }
}
if (str_starts_with($elvadoEngineAction, 'ext_')) {
    try {
        if ($elvadoEngineAction === 'ext_safe') {
            (new \Elvado\Wp\ExtensionService(new class implements \Elvado\Wp\Adapter\ExtensionAdapter {
                public function plugins(): array { return []; }
                public function themes(): array { return []; }
                public function activatePlugin(string $id): void {}
                public function deactivatePlugin(string $id): void {}
                public function uninstallPlugin(string $id): void {}
                public function activeTheme(): array { return ['template' => '', 'stylesheet' => '']; }
                public function switchTheme(string $slug): void {}
            }, new \Elvado\Wp\ExtensionInstaller($elvadoEngine), $elvadoEngine, $elvadoActor))->setSafe(!empty($elvadoEngineB['on']));
            elvado_json(['status' => 'ok'] + elvado_wpe_status($elvadoEngine, $elvadoEngineDb));
        }
        if (!$elvadoEngine->isActive()) {
            elvado_json(['status' => 'error', 'message' => 'Die WordPress-Engine ist nicht aktiv.'], 409);
        }
        $GLOBALS['elvado_wpe_engine'] = $elvadoEngine;
        $GLOBALS['elvado_wpe_db'] = $elvadoEngineDb;
        $GLOBALS['elvado_wpe_opts'] = [];
        elvado_wpe_guard_output();
        require __DIR__ . '/wp-engine-boot.php';
        if (!\Elvado\Wp\Bridge::booted()) {
            elvado_json(['status' => 'error', 'message' => 'WordPress ließ sich nicht starten.'], 500);
        }
        $elvadoIncident = elvado_wpe_after_boot($elvadoEngine) ?? $elvadoEngine->incident();
        $elvadoExt = new \Elvado\Wp\ExtensionService(new \Elvado\Wp\Adapter\WordPressExtensionAdapter(), new \Elvado\Wp\ExtensionInstaller($elvadoEngine), $elvadoEngine, $elvadoActor);
        $elvadoKind = (string)($_GET['kind'] ?? $elvadoEngineB['kind'] ?? 'plugin');
        $elvadoSafe = ['safe_mode' => $elvadoEngine->safe(), 'incident' => $elvadoIncident];
        if ($elvadoEngineAction === 'ext_list') {
            elvado_json(['status' => 'ok', 'kind' => $elvadoKind, 'items' => $elvadoExt->list($elvadoKind)] + $elvadoSafe);
        }
        if ($elvadoEngineAction === 'ext_search') {
            $elvadoR = $elvadoExt->search($elvadoKind, (string)($_GET['q'] ?? ''), (int)($_GET['page'] ?? 1));
            elvado_json(['status' => $elvadoR['ok'] ? 'ok' : 'error'] + $elvadoR, $elvadoR['ok'] ? 200 : 502);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            elvado_json(['status' => 'error', 'message' => 'Diese Aktion verlangt POST.'], 405);
        }
        @set_time_limit(300);
        if ($elvadoEngineAction === 'ext_install') {
            $elvadoR = $elvadoExt->installFromDirectory($elvadoKind, (string)($elvadoEngineB['slug'] ?? ''), !empty($elvadoEngineB['update']));
            $elvadoEngineLog(($elvadoKind === 'plugin' ? 'Plugin' : 'Theme') . ' installiert: ' . $elvadoR['slug'] . ' ' . $elvadoR['version']);
            elvado_json(['status' => 'ok', 'result' => $elvadoR, 'items' => $elvadoExt->list($elvadoKind)] + $elvadoSafe);
        }
        if ($elvadoEngineAction === 'ext_upload') {
            $elvadoF = $_FILES['file'] ?? null;
            if (!is_array($elvadoF) || ($elvadoF['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$elvadoF['tmp_name'])) {
                elvado_json(['status' => 'error', 'message' => 'Der Upload ist fehlgeschlagen (Datei fehlt oder ist zu groß).'], 422);
            }
            $elvadoR = $elvadoExt->installUpload((string)($_POST['kind'] ?? $elvadoKind), (string)$elvadoF['tmp_name'], !empty($_POST['update']));
            $elvadoEngineLog(($elvadoKind === 'plugin' ? 'Plugin' : 'Theme') . ' hochgeladen: ' . $elvadoR['slug'] . ' ' . $elvadoR['version']);
            elvado_json(['status' => 'ok', 'result' => $elvadoR, 'items' => $elvadoExt->list((string)($_POST['kind'] ?? $elvadoKind))] + $elvadoSafe);
        }
        $elvadoId = (string)($elvadoEngineB['id'] ?? '');
        if ($elvadoEngineAction === 'ext_activate') {
            $elvadoExt->activate($elvadoKind, $elvadoId);
            $elvadoEngineLog(($elvadoKind === 'plugin' ? 'Plugin aktiviert: ' : 'Theme aktiviert: ') . $elvadoId);
            elvado_json(['status' => 'ok', 'items' => $elvadoExt->list($elvadoKind)] + $elvadoSafe);
        }
        if ($elvadoEngineAction === 'ext_deactivate') {
            $elvadoExt->deactivate($elvadoId);
            $elvadoEngineLog('Plugin deaktiviert: ' . $elvadoId);
            elvado_json(['status' => 'ok', 'items' => $elvadoExt->list('plugin')] + $elvadoSafe);
        }
        if ($elvadoEngineAction === 'ext_delete') {
            $elvadoExt->delete($elvadoKind, $elvadoId, !empty($elvadoEngineB['purge']));
            $elvadoEngineLog(($elvadoKind === 'plugin' ? 'Plugin entfernt: ' : 'Theme entfernt: ') . $elvadoId);
            elvado_json(['status' => 'ok', 'items' => $elvadoExt->list($elvadoKind)] + $elvadoSafe);
        }
    } catch (\Elvado\Wp\PermissionException $e) {
        elvado_json(['status' => 'error', 'message' => $e->getMessage()], 403);
    } catch (\InvalidArgumentException | \RuntimeException $e) {
        elvado_json(['status' => 'error', 'message' => $e->getMessage()], 422);
    }
    elvado_json(['status' => 'error', 'message' => 'Unbekannte Aktion'], 404);
}
if (preg_match('/^(nav|widgets|blocks)_/', $elvadoEngineAction) === 1) {
    $elvadoSource = (string)($_GET['source'] ?? ($elvadoEngine->isActive() ? 'wordpress' : 'native'));
    $elvadoNeedsWp = $elvadoSource === 'wordpress' || str_starts_with($elvadoEngineAction, 'blocks_') || $elvadoEngineAction === 'nav_import';
    if (!in_array($elvadoSource, ['native', 'wordpress'], true)) {
        elvado_json(['status' => 'error', 'message' => 'Unbekannte Quelle.'], 400);
    }
    if ($elvadoNeedsWp) {
        if (!$elvadoEngine->isActive()) {
            elvado_json(['status' => 'error', 'message' => 'Die WordPress-Engine ist nicht aktiv.'], 409);
        }
        $GLOBALS['elvado_wpe_engine'] = $elvadoEngine;
        $GLOBALS['elvado_wpe_db'] = $elvadoEngineDb;
        $GLOBALS['elvado_wpe_opts'] = [];
        elvado_wpe_guard_output();
        require __DIR__ . '/wp-engine-boot.php';
        if (!\Elvado\Wp\Bridge::booted()) {
            elvado_json(['status' => 'error', 'message' => 'WordPress ließ sich nicht starten.'], 500);
        }
        elvado_wpe_after_boot($elvadoEngine);
    }
    $elvadoIsPost = $_SERVER['REQUEST_METHOD'] === 'POST';
    try {
        if (str_starts_with($elvadoEngineAction, 'nav_')) {
            $elvadoNavAdapter = $elvadoSource === 'wordpress' ? new \Elvado\Wp\Adapter\WordPressNavigationAdapter() : new \Elvado\Wp\Adapter\NativeNavigationAdapter(__DIR__ . '/data');
            $elvadoNav = new \Elvado\Wp\NavigationService($elvadoNavAdapter, $elvadoActor);
            if ($elvadoEngineAction === 'nav_list') {
                elvado_json(['status' => 'ok', 'source' => $elvadoSource, 'menus' => $elvadoNav->menus(), 'locations' => $elvadoNav->locations()]);
            }
            if ($elvadoEngineAction === 'nav_get') {
                $elvadoT = $elvadoNav->tree((string)($_GET['menu'] ?? ''));
                elvado_json($elvadoT === null ? ['status' => 'error', 'message' => 'Menü nicht gefunden.'] : ['status' => 'ok', 'menu' => $elvadoT], $elvadoT === null ? 404 : 200);
            }
            if (!$elvadoIsPost) {
                elvado_json(['status' => 'error', 'message' => 'Diese Aktion verlangt POST.'], 405);
            }
            if ($elvadoEngineAction === 'nav_create') {
                $elvadoM = $elvadoNav->create((string)($elvadoEngineB['name'] ?? ''));
                $elvadoEngineLog('Menü angelegt: ' . $elvadoM['name']);
                elvado_json(['status' => 'ok', 'menu' => $elvadoM]);
            }
            if ($elvadoEngineAction === 'nav_rename') {
                $elvadoNav->rename((string)($elvadoEngineB['menu'] ?? ''), (string)($elvadoEngineB['name'] ?? ''));
                elvado_json(['status' => 'ok']);
            }
            if ($elvadoEngineAction === 'nav_delete') {
                $elvadoOk = $elvadoNav->delete((string)($elvadoEngineB['menu'] ?? ''));
                $elvadoEngineLog('Menü gelöscht (#' . (string)($elvadoEngineB['menu'] ?? '') . ')');
                elvado_json($elvadoOk ? ['status' => 'ok'] : ['status' => 'error', 'message' => 'Nicht gefunden.'], $elvadoOk ? 200 : 404);
            }
            if ($elvadoEngineAction === 'nav_save') {
                $elvadoItems = $elvadoNav->save((string)($elvadoEngineB['menu'] ?? ''), $elvadoEngineB['items'] ?? []);
                $elvadoEngineLog('Menü gespeichert (#' . (string)($elvadoEngineB['menu'] ?? '') . ', ' . count($elvadoItems) . ' Einträge oben)');
                elvado_json(['status' => 'ok', 'items' => $elvadoItems]);
            }
            if ($elvadoEngineAction === 'nav_assign') {
                $elvadoNav->assign((string)($elvadoEngineB['location'] ?? ''), (string)($elvadoEngineB['menu'] ?? ''));
                elvado_json(['status' => 'ok', 'locations' => $elvadoNav->locations()]);
            }
            if ($elvadoEngineAction === 'nav_import') {
                $r = $elvadoNav->importFrom(new \Elvado\Wp\Adapter\NativeNavigationAdapter(__DIR__ . '/data'), (string)($elvadoEngineB['from'] ?? ''), (string)($elvadoEngineB['name'] ?? ''));
                $elvadoEngineLog('Menü übernommen: ' . $r['menu']['name'] . ' (' . $r['items'] . ' Einträge)');
                elvado_json(['status' => 'ok'] + $r);
            }
        } elseif (str_starts_with($elvadoEngineAction, 'widgets_')) {
            $elvadoWd = new \Elvado\Wp\WidgetService($elvadoSource === 'wordpress' ? new \Elvado\Wp\Adapter\WordPressWidgetAdapter() : new \Elvado\Wp\Adapter\NativeWidgetAdapter(__DIR__ . '/data'), $elvadoActor);
            if ($elvadoEngineAction === 'widgets_overview') {
                elvado_json(['status' => 'ok', 'source' => $elvadoSource] + $elvadoWd->overview());
            }
            if (!$elvadoIsPost) {
                elvado_json(['status' => 'error', 'message' => 'Diese Aktion verlangt POST.'], 405);
            }
            if ($elvadoEngineAction === 'widgets_add') {
                elvado_json(['status' => 'ok', 'widget' => $elvadoWd->add((string)($elvadoEngineB['area'] ?? ''), (string)($elvadoEngineB['id_base'] ?? ''), $elvadoEngineB['settings'] ?? [])]);
            }
            if ($elvadoEngineAction === 'widgets_update') {
                elvado_json(['status' => 'ok', 'widget' => $elvadoWd->update((string)($elvadoEngineB['id'] ?? ''), $elvadoEngineB['settings'] ?? [])]);
            }
            if ($elvadoEngineAction === 'widgets_move') {
                $elvadoWd->move($elvadoEngineB['layout'] ?? null);
                elvado_json(['status' => 'ok'] + $elvadoWd->overview());
            }
            if ($elvadoEngineAction === 'widgets_delete') {
                $elvadoOk = $elvadoWd->delete((string)($elvadoEngineB['id'] ?? ''));
                elvado_json($elvadoOk ? ['status' => 'ok'] + $elvadoWd->overview() : ['status' => 'error', 'message' => 'Nicht gefunden.'], $elvadoOk ? 200 : 404);
            }
        } else {
            $elvadoBl = new \Elvado\Wp\BlockService($elvadoActor);
            if ($elvadoEngineAction === 'blocks_registry') {
                elvado_json(['status' => 'ok', 'blocks' => $elvadoBl->registry()]);
            }
            if (!$elvadoIsPost) {
                elvado_json(['status' => 'error', 'message' => 'Diese Aktion verlangt POST.'], 405);
            }
            $elvadoMk = (string)($elvadoEngineB['markup'] ?? '');
            if ($elvadoEngineAction === 'blocks_parse') {
                elvado_json(['status' => 'ok', 'blocks' => $elvadoBl->parse($elvadoMk), 'check' => $elvadoBl->check($elvadoMk)]);
            }
            if ($elvadoEngineAction === 'blocks_check') {
                elvado_json(['status' => 'ok'] + $elvadoBl->check($elvadoMk));
            }
            if ($elvadoEngineAction === 'blocks_render') {
                elvado_json(['status' => 'ok', 'html' => $elvadoBl->render($elvadoMk)]);
            }
            if ($elvadoEngineAction === 'blocks_serialize') {
                elvado_json(['status' => 'ok', 'markup' => $elvadoBl->serialize($elvadoEngineB['blocks'] ?? [])]);
            }
            if ($elvadoEngineAction === 'blocks_convert') {
                elvado_json(['status' => 'ok'] + $elvadoBl->convert($elvadoMk, (string)($elvadoEngineB['to'] ?? 'wp')));
            }
        }
    } catch (\Elvado\Wp\PermissionException $e) {
        elvado_json(['status' => 'error', 'message' => $e->getMessage()], 403);
    } catch (\InvalidArgumentException | \RuntimeException $e) {
        elvado_json(['status' => 'error', 'message' => $e->getMessage()], 422);
    }
    elvado_json(['status' => 'error', 'message' => 'Unbekannte Aktion'], 404);
}
if (in_array($elvadoEngineAction, ['system_status', 'updates_overview', 'updates_check'], true)) {
    // Systemstatus und Update-Übersicht (nur Administratoren). system_status/updates_overview starten WordPress nie; updates_check fragt die Quellen ab (Netz) und startet WordPress nur für dessen Plugin-/Theme-Updates.
    $elvadoFacts = static function () use ($elvadoEngine, $elvadoEngineDb): array {
        $f = elvado_wpe_facts($elvadoEngine, $elvadoEngineDb, __DIR__ . '/data', __DIR__);
        try {
            $u = \Elvado\Update\UpdateService::forCms(__DIR__, __DIR__ . '/data')->status();
            $f['cms_update'] = ['available' => !empty($u['update_available']), 'latest' => (string)($u['latest']['version'] ?? ''), 'checked_at' => (string)($u['checked_at'] ?? '')];
        } catch (\Throwable $e) {
            $f['cms_update'] = [];
        }
        try {
            $rows = function_exists('elvado_np') ? elvado_np()->rows() : [];
            $avail = array_filter($rows, fn($r) => ($r['status'] ?? '') !== 'planned');
            $inst = array_filter($avail, fn($r) => in_array($r['status'], ['installed', 'active'], true));
            $f['plugins'] = ['native_total' => count($inst), 'native_active' => count(array_filter($inst, fn($r) => $r['status'] === 'active')), 'native_unverified' => count(array_filter($inst, fn($r) => empty($r['official']))), 'wp_active' => null];
            $f['native_updates'] = array_values(array_map(fn($r) => ['name' => (string)$r['name'], 'installed' => (string)$r['version'], 'latest' => (string)$r['available_version']], array_filter($inst, fn($r) => !empty($r['update_available']))));
        } catch (\Throwable $e) {
            $f['plugins'] = [];
        }
        try {
            $site = elvado_read_json(__DIR__ . '/data/site.json', []);
            $f['theme'] = (string)($site['theme']['active'] ?? '');
            $cfg = \Elvado\Ai\AiGatewayConfig::load(__DIR__ . '/data', $site);
            $f['ai_providers'] = count((new \Elvado\Ai\AiGatewayService($cfg))->usableProviders());
        } catch (\Throwable $e) {
        }
        $f['app_builder'] = is_dir(dirname(__DIR__) . '/app-template') || is_file(__DIR__ . '/lib/appbuild.php');
        return $f;
    };
    if ($elvadoEngineAction !== 'updates_check') {
        $f = $elvadoFacts();
        elvado_json(['status' => 'ok', 'generated_at' => date('c')] + ($elvadoEngineAction === 'system_status' ? ['system' => \Elvado\Wp\SystemStatus::build($f)] : ['updates' => \Elvado\Wp\SystemStatus::updates($f)]));
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        elvado_json(['status' => 'error', 'message' => 'Diese Aktion verlangt POST.'], 405);
    }
    @set_time_limit(120);
    $elvadoCacheFile = $elvadoEngine->stateDir() . '/updates.json';
    $elvadoCache = is_file($elvadoCacheFile) ? (json_decode((string)@file_get_contents($elvadoCacheFile), true) ?: []) : [];
    $elvadoErr = [];
    $l = \Elvado\Wp\CoreSource::latest();
    if ($l['ok']) {
        $elvadoCache['core'] = ['version' => $l['version'], 'php' => $l['php'], 'mysql' => $l['mysql']];
    } else {
        $elvadoErr[] = 'WordPress: ' . $l['message'];
    }
    try {
        \Elvado\Update\UpdateService::forCms(__DIR__, __DIR__ . '/data')->check();
    } catch (\Throwable $e) {
        $elvadoErr[] = 'ElvadoPress: ' . mb_substr($e->getMessage(), 0, 160);
    }
    $elvadoCache['checked_at'] = date('c');
    if ($elvadoEngine->isActive()) {
        $GLOBALS['elvado_wpe_engine'] = $elvadoEngine;
        $GLOBALS['elvado_wpe_db'] = $elvadoEngineDb;
        $GLOBALS['elvado_wpe_opts'] = [];
        elvado_wpe_guard_output();
        require __DIR__ . '/wp-engine-boot.php';
        if (\Elvado\Wp\Bridge::booted()) {
            elvado_wpe_after_boot($elvadoEngine);
            require_once ABSPATH . 'wp-admin/includes/update.php';
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            require_once ABSPATH . 'wp-includes/update.php';
            wp_update_plugins();
            wp_update_themes();
            $up = (array)get_site_transient('update_plugins');
            $ut = (array)get_site_transient('update_themes');
            $pl = [];
            foreach ((array)($up['response'] ?? []) as $file => $o) {
                $d = get_plugin_data(WP_PLUGIN_DIR . '/' . $file, false, false);
                $pl[] = ['name' => (string)($d['Name'] ?? $file), 'installed' => (string)($d['Version'] ?? ''), 'latest' => (string)($o->new_version ?? '')];
            }
            $th = [];
            foreach ((array)($ut['response'] ?? []) as $slug => $o) {
                $t = wp_get_theme((string)$slug);
                $th[] = ['name' => (string)($t->exists() ? $t->get('Name') : $slug), 'installed' => (string)($t->exists() ? $t->get('Version') : ''), 'latest' => (string)($o['new_version'] ?? '')];
            }
            $elvadoCache['wp'] = ['plugins' => $pl, 'themes' => $th, 'checked_at' => date('c')];
        } else {
            $elvadoErr[] = 'WordPress ließ sich nicht starten.';
        }
    }
    $elvadoEngine->protect();
    @file_put_contents($elvadoCacheFile, json_encode($elvadoCache, JSON_UNESCAPED_UNICODE), LOCK_EX);
    @chmod($elvadoCacheFile, 0600);
    $elvadoEngineLog('Updates geprüft' . ($elvadoErr ? ' (mit Hinweisen)' : ''));
    $f = $elvadoFacts();
    elvado_json(['status' => 'ok', 'notes' => $elvadoErr, 'generated_at' => date('c'), 'updates' => \Elvado\Wp\SystemStatus::updates($f), 'system' => \Elvado\Wp\SystemStatus::build($f)]);
}
if (in_array($elvadoEngineAction, ['migration_runs', 'migration_run', 'migration_rollback'], true)) {
    // Echte Migration (nur mit ausdrücklicher Bestätigung): legt Inhalte in WordPress an, verändert die bisherigen Daten nie; jeder Lauf ist protokolliert und zurückbaubar.
    $elvadoRuns = new \Elvado\Wp\Migration\RunStore($elvadoEngine->stateDir());
    if ($elvadoEngineAction === 'migration_runs') {
        $elvadoRun = isset($_GET['id']) ? $elvadoRuns->load((string)$_GET['id']) : null;
        elvado_json(['status' => 'ok', 'ids' => $elvadoRuns->ids(), 'run' => $elvadoRun ?? (($elvadoRuns->ids()[0] ?? '') !== '' ? $elvadoRuns->load($elvadoRuns->ids()[0]) : null)]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        elvado_json(['status' => 'error', 'message' => 'Diese Aktion verlangt POST.'], 405);
    }
    if ($elvadoEngineAction === 'migration_run' && (string)($elvadoEngineB['confirm'] ?? '') !== 'MIGRIEREN') {
        elvado_json(['status' => 'error', 'message' => 'Die Migration startet nur mit ausdrücklicher Bestätigung.'], 400);
    }
    if ($elvadoEngineAction === 'migration_rollback' && empty($elvadoEngineB['confirm'])) {
        elvado_json(['status' => 'error', 'message' => 'Bitte den Rückbau bestätigen.'], 400);
    }
    if (!$elvadoEngine->isActive()) {
        elvado_json(['status' => 'error', 'message' => 'Die WordPress-Engine ist nicht aktiv.'], 409);
    }
    $GLOBALS['elvado_wpe_engine'] = $elvadoEngine;
    $GLOBALS['elvado_wpe_db'] = $elvadoEngineDb;
    $GLOBALS['elvado_wpe_opts'] = [];
    elvado_wpe_guard_output();
    require __DIR__ . '/wp-engine-boot.php';
    if (!\Elvado\Wp\Bridge::booted()) {
        elvado_json(['status' => 'error', 'message' => 'WordPress ließ sich nicht starten.'], 500);
    }
    elvado_wpe_after_boot($elvadoEngine);
    @set_time_limit(600);
    $elvadoMig = new \Elvado\Wp\Migration\Migrator(__DIR__, __DIR__ . '/data', $elvadoEngine->stateDir(), $elvadoActor);
    try {
        if ($elvadoEngineAction === 'migration_run') {
            $elvadoRun = $elvadoMig->run();
            $elvadoEngineLog('Migration ausgeführt: ' . $elvadoRun['status'] . ' (Lauf ' . $elvadoRun['id'] . ')');
            elvado_json(['status' => 'ok', 'run' => $elvadoRun]);
        }
        $elvadoN = $elvadoMig->rollback((string)($elvadoEngineB['run'] ?? ''));
        $elvadoEngineLog('Migration zurückgebaut (Lauf ' . (string)($elvadoEngineB['run'] ?? '') . ')');
        elvado_json(['status' => 'ok', 'removed' => $elvadoN, 'run' => $elvadoRuns->load((string)($elvadoEngineB['run'] ?? ''))]);
    } catch (\RuntimeException | \InvalidArgumentException $e) {
        elvado_json(['status' => 'error', 'message' => $e->getMessage()], 422);
    }
}
if ($elvadoEngineAction === 'migration_plan' || $elvadoEngineAction === 'migration_report') {
    // Trockenlauf: liest ElvadoPress-Daten und (falls aktiv) WordPress nur lesend; schreibt allein den Bericht in den geschützten Zustandsordner.
    $elvadoStore = new \Elvado\Wp\Migration\ReportStore($elvadoEngine->stateDir());
    if ($elvadoEngineAction === 'migration_report') {
        $elvadoRep = $elvadoStore->load((string)($_GET['file'] ?? ''));
        elvado_json(['status' => 'ok', 'report' => $elvadoRep, 'files' => $elvadoStore->names()]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        elvado_json(['status' => 'error', 'message' => 'Diese Aktion verlangt POST.'], 405);
    }
    $elvadoProbe = new \Elvado\Wp\Migration\NullProbe();
    if ($elvadoEngine->isActive()) {
        $GLOBALS['elvado_wpe_engine'] = $elvadoEngine;
        $GLOBALS['elvado_wpe_db'] = $elvadoEngineDb;
        $GLOBALS['elvado_wpe_opts'] = [];
        elvado_wpe_guard_output();
        require __DIR__ . '/wp-engine-boot.php';
        if (\Elvado\Wp\Bridge::booted()) {
            elvado_wpe_after_boot($elvadoEngine);
            $elvadoProbe = new \Elvado\Wp\Migration\WordPressProbe();
        }
    }
    try {
        $elvadoRep = (new \Elvado\Wp\Migration\Planner(__DIR__, __DIR__ . '/data', $elvadoProbe, $elvadoEngine->mode()))->plan();
        $elvadoRep['file'] = $elvadoStore->save($elvadoRep);
    } catch (\RuntimeException $e) {
        elvado_json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
    $elvadoEngineLog('Migration: Trockenlauf (' . $elvadoRep['verdict'] . ') – es wurde nichts geändert');
    elvado_json(['status' => 'ok', 'report' => $elvadoRep, 'files' => $elvadoStore->names()]);
}
if ($elvadoEngineAction === 'engine_mode') {
    $mode = (string)($elvadoEngineB['mode'] ?? '');
    if ($mode === 'active') {
        elvado_json(['status' => 'error', 'message' => 'Unerwarteter Pfad.'], 500);
    }
    $m = $elvadoEngine->setMode($mode);
    if ($m['ok']) {
        $elvadoEngineLog('WordPress-Engine: Betriebsart ' . $mode);
    }
    elvado_json(['status' => $m['ok'] ? 'ok' : 'error', 'message' => $m['message']] + elvado_wpe_status($elvadoEngine, $elvadoEngineDb), $m['ok'] ? 200 : 422);
}
if ($elvadoEngineAction === 'engine_remove') {
    if (empty($elvadoEngineB['confirm'])) {
        elvado_json(['status' => 'error', 'message' => 'Bitte das Entfernen bestätigen.'], 400);
    }
    $r = elvado_wpe_remove($elvadoEngine);
    $elvadoEngineLog('WordPress-Engine entfernt');
    elvado_json(['status' => 'ok', 'message' => $r['message']] + elvado_wpe_status($elvadoEngine, $elvadoEngineDb));
}
elvado_json(['status' => 'error', 'message' => 'Unbekannte Aktion'], 404);

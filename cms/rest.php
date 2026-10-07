<?php
declare(strict_types=1);
// Stabile ElvadoPress-REST-API (Version 1) für Apps, Frontends und eigene Clients. Intern: API → Dienst (Content/Media/Navigation) → Adapter → (ElvadoPress-Daten | echter WordPress-Core).
// Clients hängen nie direkt an WordPress. Aufruf: /cms/rest.php/<Route> (oder ?route=/<Route>); Anmeldung: Header „Authorization: Bearer <Sitzungsschlüssel>“ oder „X-AnMaCha-Token“ – nie per Cookie (kein CSRF).
// Routen: GET /  ·  GET|POST /pages, /posts  ·  GET|PUT|PATCH|DELETE /pages/{id}, /posts/{id}  ·  GET /categories, /tags  ·  GET /media, /media/{id}  ·  GET /navigation, /navigation/{id}  ·  GET /components  ·  GET /layouts/{bereich}  ·  GET /status
// Antwort: {"status":"ok","data":…,"meta":{…}} bzw. {"status":"error","message":…}. Quelle: aktive Engine → WordPress, sonst ElvadoPress-Daten (nur lesend); ?source=native erzwingt die ElvadoPress-Daten (nur lesend).
// Rechte: wie in der Verwaltung (Administratoren alles; Autoren Inhalte/Medien nach Besitz). In der Demo ohne Engine nur lesend.
define('RRW_API_LIB_ONLY', true);
if (empty($_SERVER['HTTP_X_ANMACHA_TOKEN']) && preg_match('/^Bearer\s+(\S+)$/i', (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''), $m)) {
    $_SERVER['HTTP_X_ANMACHA_TOKEN'] = $m[1];
}
require __DIR__ . '/api.php';
require_once __DIR__ . '/src/autoload.php';
require_once __DIR__ . '/lib/wpengine.php';
require_once __DIR__ . '/lib/components.php';

$rrwRU = rrw_auth(false);
$rrwActor = \Elvado\Wp\Actor::fromAuth($rrwRU);
header('X-ElvadoPress-API: 1');
$rrwOk = static function (mixed $data, array $meta = [], int $code = 200): never { rrw_json(['status' => 'ok', 'data' => $data, 'meta' => (object)$meta], $code); };
$rrwErr = static function (string $msg, int $code): never { rrw_json(['status' => 'error', 'message' => $msg], $code); };

$rrwPath = (string)($_SERVER['PATH_INFO'] ?? $_GET['route'] ?? '/');
$rrwPath = '/' . trim((string)preg_replace('#/+#', '/', $rrwPath), '/');
$rrwParts = $rrwPath === '/' ? [] : explode('/', ltrim($rrwPath, '/'));
$rrwMethod = $_SERVER['REQUEST_METHOD'];
if (!in_array($rrwMethod, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    $rrwErr('Diese Methode wird nicht unterstützt.', 405);
}
$rrwBody = in_array($rrwMethod, ['POST', 'PUT', 'PATCH'], true) ? rrw_body() : [];
$rrwRes = $rrwParts[0] ?? '';
$rrwId = $rrwParts[1] ?? '';
if (count($rrwParts) > 2 || ($rrwId !== '' && preg_match('/^[A-Za-z0-9_.:\-]{1,80}$/', $rrwId) !== 1)) {
    $rrwErr('Unbekannte Route.', 404);
}
$rrwDemo = function_exists('rrw_demo_enabled') && rrw_demo_enabled();
$rrwEngine = rrw_wpe();
$rrwEngineDb = new \Elvado\Wp\DbConfig($rrwEngine);

if ($rrwRes === '') {
    $rrwOk(['api' => 'ElvadoPress REST', 'version' => 1, 'cms_version' => rrw_cms_version(), 'engine' => $rrwEngine->mode(), 'routes' => ['pages', 'posts', 'categories', 'tags', 'media', 'navigation', 'components', 'layouts/{bereich}', 'status']]);
}
if (!in_array($rrwRes, ['pages', 'posts', 'categories', 'tags', 'media', 'navigation', 'components', 'layouts', 'status'], true)) {
    $rrwErr('Unbekannte Route.', 404);
}

// Quelle: aktive Engine ⇒ WordPress; sonst ElvadoPress-Daten (nur lesend)
$rrwSource = (string)($_GET['source'] ?? ($rrwEngine->isActive() ? 'wordpress' : 'native'));
if (!in_array($rrwSource, ['native', 'wordpress'], true)) {
    $rrwErr('Unbekannte Quelle.', 400);
}
if ($rrwSource === 'wordpress' && !$rrwEngine->isActive()) {
    $rrwErr('Die WordPress-Engine ist nicht aktiv.', 409);
}
if ($rrwDemo && $rrwSource === 'wordpress' && (!function_exists('rrw_demo_engine_enabled') || !rrw_demo_engine_enabled())) {
    $rrwErr('In der Demo gesperrt.', 403);
}
$rrwNeedsWp = $rrwSource === 'wordpress' && in_array($rrwRes, ['pages', 'posts', 'categories', 'tags', 'media', 'navigation'], true);
if ($rrwNeedsWp) {
    $GLOBALS['rrw_wpe_engine'] = $rrwEngine;
    $GLOBALS['rrw_wpe_db'] = $rrwEngineDb;
    $GLOBALS['rrw_wpe_opts'] = [];
    rrw_wpe_guard_output();
    require __DIR__ . '/wp-engine-boot.php';
    if (!\Elvado\Wp\Bridge::booted()) {
        $rrwErr('WordPress ließ sich nicht starten.', 500);
    }
    rrw_wpe_after_boot($rrwEngine);
}

try {
    if ($rrwRes === 'status') {
        if (!$rrwActor->isAdmin()) {
            throw new \Elvado\Wp\PermissionException('Dafür fehlt die Berechtigung.');
        }
        $rrwOk(\Elvado\Wp\SystemStatus::build(rrw_wpe_facts($rrwEngine, $rrwEngineDb, __DIR__ . '/data', __DIR__)));
    }
    if ($rrwRes === 'components') {
        if ($rrwMethod !== 'GET') {
            $rrwErr('Diese Route ist nur lesbar.', 405);
        }
        $rrwOk(rrw_components()->catalog(['admin' => $rrwActor->isAdmin(), 'features' => rrw_components_features()]));
    }
    if ($rrwRes === 'layouts') {
        if ($rrwMethod !== 'GET') {
            $rrwErr('Layouts werden über die Verwaltung (Live Builder) geändert.', 405);
        }
        if ($rrwId === '' || !\Elvado\Components\LayoutStore::validScope($rrwId)) {
            $rrwErr('Ungültiger Layout-Bereich (home, site:<name>, page:<name>, post:<nummer>).', 422);
        }
        $s = rrw_components_store()->get($rrwId);
        $rrwOk(['scope' => $rrwId, 'published' => $s['published'], 'draft' => $rrwActor->isAdmin() ? $s['draft'] : null]);
    }

    $rrwAdapter = $rrwSource === 'wordpress' ? new \Elvado\Wp\Adapter\WordPressAdapter() : new \Elvado\Wp\Adapter\NativeAdapter(__DIR__, __DIR__ . '/data');
    if ($rrwRes === 'media') {
        $svc = new \Elvado\Wp\MediaService($rrwSource === 'wordpress' ? new \Elvado\Wp\Adapter\WordPressMediaAdapter() : new \Elvado\Wp\Adapter\NativeMediaAdapter(__DIR__), $rrwActor);
        if ($rrwMethod === 'GET' && $rrwId === '') {
            $r = $svc->list($_GET);
            $rrwOk($r['items'], ['total' => $r['total'], 'source' => $rrwSource]);
        }
        if ($rrwMethod === 'GET') {
            $it = $svc->get($rrwId);
            $it === null ? $rrwErr('Nicht gefunden.', 404) : $rrwOk($it, ['source' => $rrwSource]);
        }
        if ($rrwMethod === 'DELETE' && $rrwId !== '') {
            $svc->delete($rrwId) ? $rrwOk(['deleted' => $rrwId]) : $rrwErr('Nicht gefunden.', 404);
        }
        if ($rrwMethod === 'POST' && $rrwId === '') {
            $f = $_FILES['file'] ?? null;
            if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$f['tmp_name'])) {
                $rrwErr('Der Upload ist fehlgeschlagen (Datei fehlt oder ist zu groß). Erwartet: multipart/form-data mit dem Feld „file“.', 422);
            }
            $rrwOk($svc->upload((string)$f['tmp_name'], (string)$f['name'], ['title' => $_POST['title'] ?? '', 'alt' => $_POST['alt'] ?? '', 'caption' => $_POST['caption'] ?? '']), [], 201);
        }
        $rrwErr('Diese Methode gilt für diese Route nicht.', 405);
    }
    if ($rrwRes === 'navigation') {
        $nav = new \Elvado\Wp\NavigationService($rrwSource === 'wordpress' ? new \Elvado\Wp\Adapter\WordPressNavigationAdapter() : new \Elvado\Wp\Adapter\NativeNavigationAdapter(__DIR__ . '/data'), $rrwActor);
        if ($rrwMethod !== 'GET') {
            $rrwErr('Menüs werden über die Verwaltung geändert.', 405);
        }
        if ($rrwId === '') {
            $rrwOk($nav->menus(), ['source' => $rrwSource]);
        }
        $t = $nav->tree($rrwId);
        $t === null ? $rrwErr('Nicht gefunden.', 404) : $rrwOk($t, ['source' => $rrwSource]);
    }

    $content = new \Elvado\Wp\ContentService($rrwAdapter, $rrwActor);
    if (in_array($rrwRes, ['categories', 'tags'], true)) {
        if ($rrwMethod !== 'GET' || $rrwId !== '') {
            $rrwErr('Begriffe sind lesbar (GET); geändert werden sie über die Verwaltung.', 405);
        }
        $rrwOk($content->terms($rrwRes === 'tags' ? 'tag' : 'category'), ['source' => $rrwSource]);
    }
    $type = $rrwRes === 'pages' ? 'page' : 'post';
    if ($rrwMethod === 'GET' && $rrwId === '') {
        $r = $content->list($type, $_GET);
        $rrwOk($r['items'], ['total' => $r['total'], 'page' => $r['page'], 'per_page' => $r['per_page'], 'pages' => (int)ceil($r['total'] / max(1, $r['per_page'])), 'source' => $rrwSource]);
    }
    if ($rrwMethod === 'GET') {
        $it = $content->get($type, $rrwId);
        $it === null ? $rrwErr('Nicht gefunden.', 404) : $rrwOk($it, ['source' => $rrwSource]);
    }
    if (!$rrwAdapter->writable()) {
        $rrwErr('Schreiben ist nur mit aktiver WordPress-Engine möglich (die ElvadoPress-Daten sind hier schreibgeschützt).', 409);
    }
    if ($rrwMethod === 'POST' && $rrwId === '') {
        $rrwOk($content->save($type, $rrwBody, !empty($rrwRU['superadmin'])), [], 201);
    }
    if (in_array($rrwMethod, ['PUT', 'PATCH'], true) && $rrwId !== '') {
        $rrwOk($content->save($type, ['id' => $rrwId] + $rrwBody, !empty($rrwRU['superadmin'])));
    }
    if ($rrwMethod === 'DELETE' && $rrwId !== '') {
        $content->delete($type, $rrwId, !empty($_GET['force'])) ? $rrwOk(['deleted' => $rrwId]) : $rrwErr('Nicht gefunden.', 404);
    }
    $rrwErr('Diese Methode gilt für diese Route nicht.', 405);
} catch (\Elvado\Wp\PermissionException $e) {
    $rrwErr($e->getMessage(), 403);
} catch (\InvalidArgumentException | \RuntimeException $e) {
    $rrwErr($e->getMessage(), 422);
}

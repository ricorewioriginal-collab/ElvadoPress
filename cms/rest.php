<?php
declare(strict_types=1);
// Stabile ElvadoPress-REST-API (Version 1) für Apps, Frontends und eigene Clients. Intern: API → Dienst (Content/Media/Navigation) → Adapter → (ElvadoPress-Daten | echter WordPress-Core).
// Clients hängen nie direkt an WordPress. Aufruf: /cms/rest.php/<Route> (oder ?route=/<Route>); Anmeldung: Header „Authorization: Bearer <Sitzungsschlüssel>“ oder „X-ElvadoPress-Token“ – nie per Cookie (kein CSRF).
// Routen: GET /  ·  GET|POST /pages, /posts  ·  GET|PUT|PATCH|DELETE /pages/{id}, /posts/{id}  ·  GET /categories, /tags  ·  GET /media, /media/{id}  ·  GET /navigation, /navigation/{id}  ·  GET /components  ·  GET /layouts/{bereich}  ·  GET /status
// Antwort: {"status":"ok","data":…,"meta":{…}} bzw. {"status":"error","message":…}. Quelle: aktive Engine → WordPress, sonst ElvadoPress-Daten (nur lesend); ?source=native erzwingt die ElvadoPress-Daten (nur lesend).
// Rechte: wie in der Verwaltung (Administratoren alles; Autoren Inhalte/Medien nach Besitz). In der Demo ohne Engine nur lesend.
define('ELVADO_API_LIB_ONLY', true);
if (empty($_SERVER['HTTP_X_ELVADOPRESS_TOKEN']) && preg_match('/^Bearer\s+(\S+)$/i', (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''), $m)) {
    $_SERVER['HTTP_X_ELVADOPRESS_TOKEN'] = $m[1];
}
require __DIR__ . '/api.php';
require_once __DIR__ . '/src/autoload.php';
require_once __DIR__ . '/lib/wpengine.php';
require_once __DIR__ . '/lib/components.php';

$elvadoRU = elvado_auth(false);
$elvadoActor = \Elvado\Wp\Actor::fromAuth($elvadoRU);
header('X-ElvadoPress-API: 1');
$elvadoOk = static function (mixed $data, array $meta = [], int $code = 200): never { elvado_json(['status' => 'ok', 'data' => $data, 'meta' => (object)$meta], $code); };
$elvadoErr = static function (string $msg, int $code): never { elvado_json(['status' => 'error', 'message' => $msg], $code); };

$elvadoPath = (string)($_SERVER['PATH_INFO'] ?? $_GET['route'] ?? '/');
$elvadoPath = '/' . trim((string)preg_replace('#/+#', '/', $elvadoPath), '/');
$elvadoParts = $elvadoPath === '/' ? [] : explode('/', ltrim($elvadoPath, '/'));
$elvadoMethod = $_SERVER['REQUEST_METHOD'];
if (!in_array($elvadoMethod, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    $elvadoErr('Diese Methode wird nicht unterstützt.', 405);
}
$elvadoBody = in_array($elvadoMethod, ['POST', 'PUT', 'PATCH'], true) ? elvado_body() : [];
$elvadoRes = $elvadoParts[0] ?? '';
$elvadoId = $elvadoParts[1] ?? '';
if (count($elvadoParts) > 2 || ($elvadoId !== '' && preg_match('/^[A-Za-z0-9_.:\-]{1,80}$/', $elvadoId) !== 1)) {
    $elvadoErr('Unbekannte Route.', 404);
}
$elvadoDemo = function_exists('elvado_demo_enabled') && elvado_demo_enabled();
$elvadoEngine = elvado_wpe();
$elvadoEngineDb = new \Elvado\Wp\DbConfig($elvadoEngine);

if ($elvadoRes === '') {
    $elvadoOk(['api' => 'ElvadoPress REST', 'version' => 1, 'cms_version' => elvado_cms_version(), 'engine' => $elvadoEngine->mode(), 'routes' => ['pages', 'posts', 'categories', 'tags', 'media', 'navigation', 'components', 'layouts/{bereich}', 'status']]);
}
if (!in_array($elvadoRes, ['pages', 'posts', 'categories', 'tags', 'media', 'navigation', 'components', 'layouts', 'status'], true)) {
    $elvadoErr('Unbekannte Route.', 404);
}

// Quelle: aktive Engine ⇒ WordPress; sonst ElvadoPress-Daten (nur lesend)
$elvadoSource = (string)($_GET['source'] ?? ($elvadoEngine->isActive() ? 'wordpress' : 'native'));
if (!in_array($elvadoSource, ['native', 'wordpress'], true)) {
    $elvadoErr('Unbekannte Quelle.', 400);
}
if ($elvadoSource === 'wordpress' && !$elvadoEngine->isActive()) {
    $elvadoErr('Die WordPress-Engine ist nicht aktiv.', 409);
}
if ($elvadoDemo && $elvadoSource === 'wordpress' && (!function_exists('elvado_demo_engine_enabled') || !elvado_demo_engine_enabled())) {
    $elvadoErr('In der Demo gesperrt.', 403);
}
$elvadoNeedsWp = $elvadoSource === 'wordpress' && in_array($elvadoRes, ['pages', 'posts', 'categories', 'tags', 'media', 'navigation'], true);
if ($elvadoNeedsWp) {
    $GLOBALS['elvado_wpe_engine'] = $elvadoEngine;
    $GLOBALS['elvado_wpe_db'] = $elvadoEngineDb;
    $GLOBALS['elvado_wpe_opts'] = [];
    elvado_wpe_guard_output();
    require __DIR__ . '/wp-engine-boot.php';
    if (!\Elvado\Wp\Bridge::booted()) {
        $elvadoErr('WordPress ließ sich nicht starten.', 500);
    }
    elvado_wpe_after_boot($elvadoEngine);
}

try {
    if ($elvadoRes === 'status') {
        if (!$elvadoActor->isAdmin()) {
            throw new \Elvado\Wp\PermissionException('Dafür fehlt die Berechtigung.');
        }
        $elvadoOk(\Elvado\Wp\SystemStatus::build(elvado_wpe_facts($elvadoEngine, $elvadoEngineDb, __DIR__ . '/data', __DIR__)));
    }
    if ($elvadoRes === 'components') {
        if ($elvadoMethod !== 'GET') {
            $elvadoErr('Diese Route ist nur lesbar.', 405);
        }
        $elvadoOk(elvado_components()->catalog(['admin' => $elvadoActor->isAdmin(), 'features' => elvado_components_features()]));
    }
    if ($elvadoRes === 'layouts') {
        if ($elvadoMethod !== 'GET') {
            $elvadoErr('Layouts werden über die Verwaltung (Live Builder) geändert.', 405);
        }
        if ($elvadoId === '' || !\Elvado\Components\LayoutStore::validScope($elvadoId)) {
            $elvadoErr('Ungültiger Layout-Bereich (home, site:<name>, page:<name>, post:<nummer>).', 422);
        }
        $s = elvado_components_store()->get($elvadoId);
        $elvadoOk(['scope' => $elvadoId, 'published' => $s['published'], 'draft' => $elvadoActor->isAdmin() ? $s['draft'] : null]);
    }

    $elvadoAdapter = $elvadoSource === 'wordpress' ? new \Elvado\Wp\Adapter\WordPressAdapter() : new \Elvado\Wp\Adapter\NativeAdapter(__DIR__, __DIR__ . '/data');
    if ($elvadoRes === 'media') {
        $svc = new \Elvado\Wp\MediaService($elvadoSource === 'wordpress' ? new \Elvado\Wp\Adapter\WordPressMediaAdapter() : new \Elvado\Wp\Adapter\NativeMediaAdapter(__DIR__), $elvadoActor);
        if ($elvadoMethod === 'GET' && $elvadoId === '') {
            $r = $svc->list($_GET);
            $elvadoOk($r['items'], ['total' => $r['total'], 'source' => $elvadoSource]);
        }
        if ($elvadoMethod === 'GET') {
            $it = $svc->get($elvadoId);
            $it === null ? $elvadoErr('Nicht gefunden.', 404) : $elvadoOk($it, ['source' => $elvadoSource]);
        }
        if ($elvadoMethod === 'DELETE' && $elvadoId !== '') {
            $svc->delete($elvadoId) ? $elvadoOk(['deleted' => $elvadoId]) : $elvadoErr('Nicht gefunden.', 404);
        }
        if ($elvadoMethod === 'POST' && $elvadoId === '') {
            $f = $_FILES['file'] ?? null;
            if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$f['tmp_name'])) {
                $elvadoErr('Der Upload ist fehlgeschlagen (Datei fehlt oder ist zu groß). Erwartet: multipart/form-data mit dem Feld „file“.', 422);
            }
            $elvadoOk($svc->upload((string)$f['tmp_name'], (string)$f['name'], ['title' => $_POST['title'] ?? '', 'alt' => $_POST['alt'] ?? '', 'caption' => $_POST['caption'] ?? '']), [], 201);
        }
        $elvadoErr('Diese Methode gilt für diese Route nicht.', 405);
    }
    if ($elvadoRes === 'navigation') {
        $nav = new \Elvado\Wp\NavigationService($elvadoSource === 'wordpress' ? new \Elvado\Wp\Adapter\WordPressNavigationAdapter() : new \Elvado\Wp\Adapter\NativeNavigationAdapter(__DIR__ . '/data'), $elvadoActor);
        if ($elvadoMethod !== 'GET') {
            $elvadoErr('Menüs werden über die Verwaltung geändert.', 405);
        }
        if ($elvadoId === '') {
            $elvadoOk($nav->menus(), ['source' => $elvadoSource]);
        }
        $t = $nav->tree($elvadoId);
        $t === null ? $elvadoErr('Nicht gefunden.', 404) : $elvadoOk($t, ['source' => $elvadoSource]);
    }

    $content = new \Elvado\Wp\ContentService($elvadoAdapter, $elvadoActor);
    if (in_array($elvadoRes, ['categories', 'tags'], true)) {
        if ($elvadoMethod !== 'GET' || $elvadoId !== '') {
            $elvadoErr('Begriffe sind lesbar (GET); geändert werden sie über die Verwaltung.', 405);
        }
        $elvadoOk($content->terms($elvadoRes === 'tags' ? 'tag' : 'category'), ['source' => $elvadoSource]);
    }
    $type = $elvadoRes === 'pages' ? 'page' : 'post';
    if ($elvadoMethod === 'GET' && $elvadoId === '') {
        $r = $content->list($type, $_GET);
        $elvadoOk($r['items'], ['total' => $r['total'], 'page' => $r['page'], 'per_page' => $r['per_page'], 'pages' => (int)ceil($r['total'] / max(1, $r['per_page'])), 'source' => $elvadoSource]);
    }
    if ($elvadoMethod === 'GET') {
        $it = $content->get($type, $elvadoId);
        $it === null ? $elvadoErr('Nicht gefunden.', 404) : $elvadoOk($it, ['source' => $elvadoSource]);
    }
    if (!$elvadoAdapter->writable()) {
        $elvadoErr('Schreiben ist nur mit aktiver WordPress-Engine möglich (die ElvadoPress-Daten sind hier schreibgeschützt).', 409);
    }
    if ($elvadoMethod === 'POST' && $elvadoId === '') {
        $elvadoOk($content->save($type, $elvadoBody, !empty($elvadoRU['superadmin'])), [], 201);
    }
    if (in_array($elvadoMethod, ['PUT', 'PATCH'], true) && $elvadoId !== '') {
        $elvadoOk($content->save($type, ['id' => $elvadoId] + $elvadoBody, !empty($elvadoRU['superadmin'])));
    }
    if ($elvadoMethod === 'DELETE' && $elvadoId !== '') {
        $content->delete($type, $elvadoId, !empty($_GET['force'])) ? $elvadoOk(['deleted' => $elvadoId]) : $elvadoErr('Nicht gefunden.', 404);
    }
    $elvadoErr('Diese Methode gilt für diese Route nicht.', 405);
} catch (\Elvado\Wp\PermissionException $e) {
    $elvadoErr($e->getMessage(), 403);
} catch (\InvalidArgumentException | \RuntimeException $e) {
    $elvadoErr($e->getMessage(), 422);
}

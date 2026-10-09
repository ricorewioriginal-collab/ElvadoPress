<?php
declare(strict_types=1);
// API der Komponenten-Registry und der Layouts. Aktionen: components_catalog, layout_get, layout_save_draft, layout_publish, layout_discard, layout_revisions, layout_rollback, layout_render, layout_preview (Pakete).
// Angemeldete Personen dürfen lesen; Schreiben nach Rechten (home/site:* nur Administratoren, page:*/post:* auch Autoren, geschützte Komponenten bleiben). In der Demo ist Schreiben freigegeben (Daten werden zurückgesetzt).
define('ELVADO_API_LIB_ONLY', true);
require __DIR__ . '/api.php';
require_once __DIR__ . '/lib/components.php';

use Elvado\Components\{Layout, LayoutStore, Renderer};
use Elvado\Wp\{Actor, PermissionException};

$elvadoCAction = (string)($_GET['action'] ?? 'components_catalog');
$elvadoCUser = elvado_auth(false);
$elvadoCActor = Actor::fromAuth($elvadoCUser);
$elvadoCBody = $_SERVER['REQUEST_METHOD'] === 'POST' ? elvado_body() : [];
$elvadoCWrite = in_array($elvadoCAction, ['layout_save_draft', 'layout_publish', 'layout_discard', 'layout_rollback'], true);
// Demo: Layouts sind freigegeben (reine Daten im Demo-Ordner, werden mit der Demo zurückgesetzt)
if ($elvadoCWrite && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    elvado_json(['status' => 'error', 'message' => 'Diese Aktion verlangt POST.'], 405);
}
$elvadoCReg = elvado_components();
$elvadoCStore = elvado_components_store();
$elvadoCScope = (string)($_GET['scope'] ?? $elvadoCBody['scope'] ?? 'home');
try {
    if ($elvadoCAction === 'components_catalog') {
        elvado_json(['status' => 'ok', 'targets' => $elvadoCActor->isAdmin() ? elvado_components_targets() : []] + $elvadoCReg->catalog(['admin' => $elvadoCActor->isAdmin(), 'features' => elvado_components_features()]));
    }
    if ($elvadoCAction === 'layout_preview') {   // Vorschau-Adresse eines Bearbeitungsziels (Paket): signierter, kurzlebiger Schlüssel
        $t = elvado_components_target_for_scope($elvadoCScope);
        if ($t === null || !$elvadoCActor->isAdmin()) {
            throw new PermissionException('Für diesen Bereich gibt es keine Vorschau.');
        }
        elvado_json(['status' => 'ok', 'url' => $t['preview'] . (str_contains($t['preview'], '?') ? '&' : '?') . 'elvado_ep_preview=' . elvado_components_preview_token($elvadoCScope), 'expires_in' => 900]);
    }
    if ($elvadoCAction === 'layout_get') {
        $s = $elvadoCStore->get($elvadoCScope);
        elvado_json(['status' => 'ok', 'scope' => $elvadoCScope, 'published' => $s['published'], 'draft' => $s['draft'], 'revisions' => $elvadoCStore->revisions($elvadoCScope)]);
    }
    if ($elvadoCAction === 'layout_revisions') {
        elvado_json(['status' => 'ok', 'items' => $elvadoCStore->revisions($elvadoCScope)]);
    }
    if ($elvadoCAction === 'layout_save_draft') {
        $r = $elvadoCStore->saveDraft($elvadoCScope, $elvadoCBody['layout'] ?? [], $elvadoCActor, (string)($elvadoCBody['publish_at'] ?? ''));
        elvado_json(['status' => 'ok'] + $r);
    }
    if ($elvadoCAction === 'layout_discard') {
        $elvadoCStore->discard($elvadoCScope, $elvadoCActor);
        elvado_json(['status' => 'ok']);
    }
    if ($elvadoCAction === 'layout_publish') {
        $p = $elvadoCStore->publish($elvadoCScope, $elvadoCActor, (string)($elvadoCBody['label'] ?? ''), (array)($elvadoCBody['changes'] ?? []));
        if (function_exists('elvado_log_activity')) {
            elvado_log_activity($activityLogFile, $elvadoCUser, 'layout', 'Layout veröffentlicht: ' . $elvadoCScope . ' (Fassung ' . $p['n'] . ')' . (($p['changes'] ?? []) ? ' – ' . implode('; ', array_slice($p['changes'], 0, 6)) : ''));
        }
        elvado_json(['status' => 'ok', 'published' => $p]);
    }
    if ($elvadoCAction === 'layout_rollback') {
        $p = $elvadoCStore->rollback($elvadoCScope, (int)($elvadoCBody['n'] ?? 0), $elvadoCActor);
        if (function_exists('elvado_log_activity')) {
            elvado_log_activity($activityLogFile, $elvadoCUser, 'layout', 'Layout zurückgesetzt: ' . $elvadoCScope . ' auf Fassung ' . (int)($elvadoCBody['n'] ?? 0));
        }
        elvado_json(['status' => 'ok', 'published' => $p]);
    }
    if ($elvadoCAction === 'layout_render') {   // Vorschau: bereinigt und gibt die nativen Komponenten aus (andere als Platzhalter)
        $lay = new Layout($elvadoCReg);
        $clean = $lay->clean($elvadoCBody['layout'] ?? [], ['admin' => $elvadoCActor->isAdmin(), 'unfiltered' => false]);
        elvado_json(['status' => 'ok', 'issues' => $lay->issues] + (new Renderer($elvadoCReg))->render($clean, ['placeholders' => true]));
    }
} catch (PermissionException $e) {
    elvado_json(['status' => 'error', 'message' => $e->getMessage()], 403);
} catch (\InvalidArgumentException | \RuntimeException $e) {
    elvado_json(['status' => 'error', 'message' => $e->getMessage()], 422);
}
elvado_json(['status' => 'error', 'message' => 'Unbekannte Aktion'], 404);

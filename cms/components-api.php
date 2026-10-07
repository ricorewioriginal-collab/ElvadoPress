<?php
declare(strict_types=1);
// API der Komponenten-Registry und der Layouts. Aktionen: components_catalog, layout_get, layout_save_draft, layout_publish, layout_discard, layout_revisions, layout_rollback, layout_render, layout_preview (Pakete).
// Angemeldete Personen dürfen lesen; Schreiben nach Rechten (home/site:* nur Administratoren, page:*/post:* auch Autoren, geschützte Komponenten bleiben). In der Demo ist Schreiben freigegeben (Daten werden zurückgesetzt).
define('RRW_API_LIB_ONLY', true);
require __DIR__ . '/api.php';
require_once __DIR__ . '/lib/components.php';

use Elvado\Components\{Layout, LayoutStore, Renderer};
use Elvado\Wp\{Actor, PermissionException};

$rrwCAction = (string)($_GET['action'] ?? 'components_catalog');
$rrwCUser = rrw_auth(false);
$rrwCActor = Actor::fromAuth($rrwCUser);
$rrwCBody = $_SERVER['REQUEST_METHOD'] === 'POST' ? rrw_body() : [];
$rrwCWrite = in_array($rrwCAction, ['layout_save_draft', 'layout_publish', 'layout_discard', 'layout_rollback'], true);
// Demo: Layouts sind freigegeben (reine Daten im Demo-Ordner, werden mit der Demo zurückgesetzt)
if ($rrwCWrite && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    rrw_json(['status' => 'error', 'message' => 'Diese Aktion verlangt POST.'], 405);
}
$rrwCReg = rrw_components();
$rrwCStore = rrw_components_store();
$rrwCScope = (string)($_GET['scope'] ?? $rrwCBody['scope'] ?? 'home');
try {
    if ($rrwCAction === 'components_catalog') {
        rrw_json(['status' => 'ok', 'targets' => $rrwCActor->isAdmin() ? rrw_components_targets() : []] + $rrwCReg->catalog(['admin' => $rrwCActor->isAdmin(), 'features' => rrw_components_features()]));
    }
    if ($rrwCAction === 'layout_preview') {   // Vorschau-Adresse eines Bearbeitungsziels (Paket): signierter, kurzlebiger Schlüssel
        $t = rrw_components_target_for_scope($rrwCScope);
        if ($t === null || !$rrwCActor->isAdmin()) {
            throw new PermissionException('Für diesen Bereich gibt es keine Vorschau.');
        }
        rrw_json(['status' => 'ok', 'url' => $t['preview'] . (str_contains($t['preview'], '?') ? '&' : '?') . 'rrw_ep_preview=' . rrw_components_preview_token($rrwCScope), 'expires_in' => 900]);
    }
    if ($rrwCAction === 'layout_get') {
        $s = $rrwCStore->get($rrwCScope);
        rrw_json(['status' => 'ok', 'scope' => $rrwCScope, 'published' => $s['published'], 'draft' => $s['draft'], 'revisions' => $rrwCStore->revisions($rrwCScope)]);
    }
    if ($rrwCAction === 'layout_revisions') {
        rrw_json(['status' => 'ok', 'items' => $rrwCStore->revisions($rrwCScope)]);
    }
    if ($rrwCAction === 'layout_save_draft') {
        $r = $rrwCStore->saveDraft($rrwCScope, $rrwCBody['layout'] ?? [], $rrwCActor, (string)($rrwCBody['publish_at'] ?? ''));
        rrw_json(['status' => 'ok'] + $r);
    }
    if ($rrwCAction === 'layout_discard') {
        $rrwCStore->discard($rrwCScope, $rrwCActor);
        rrw_json(['status' => 'ok']);
    }
    if ($rrwCAction === 'layout_publish') {
        $p = $rrwCStore->publish($rrwCScope, $rrwCActor, (string)($rrwCBody['label'] ?? ''));
        if (function_exists('rrw_log_activity')) {
            rrw_log_activity($activityLogFile, $rrwCUser, 'layout', 'Layout veröffentlicht: ' . $rrwCScope . ' (Fassung ' . $p['n'] . ')');
        }
        rrw_json(['status' => 'ok', 'published' => $p]);
    }
    if ($rrwCAction === 'layout_rollback') {
        $p = $rrwCStore->rollback($rrwCScope, (int)($rrwCBody['n'] ?? 0), $rrwCActor);
        if (function_exists('rrw_log_activity')) {
            rrw_log_activity($activityLogFile, $rrwCUser, 'layout', 'Layout zurückgesetzt: ' . $rrwCScope . ' auf Fassung ' . (int)($rrwCBody['n'] ?? 0));
        }
        rrw_json(['status' => 'ok', 'published' => $p]);
    }
    if ($rrwCAction === 'layout_render') {   // Vorschau: bereinigt und gibt die nativen Komponenten aus (andere als Platzhalter)
        $lay = new Layout($rrwCReg);
        $clean = $lay->clean($rrwCBody['layout'] ?? [], ['admin' => $rrwCActor->isAdmin(), 'unfiltered' => false]);
        rrw_json(['status' => 'ok', 'issues' => $lay->issues] + (new Renderer($rrwCReg))->render($clean, ['placeholders' => true]));
    }
} catch (PermissionException $e) {
    rrw_json(['status' => 'error', 'message' => $e->getMessage()], 403);
} catch (\InvalidArgumentException | \RuntimeException $e) {
    rrw_json(['status' => 'error', 'message' => $e->getMessage()], 422);
}
rrw_json(['status' => 'error', 'message' => 'Unbekannte Aktion'], 404);

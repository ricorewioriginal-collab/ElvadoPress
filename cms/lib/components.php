<?php
declare(strict_types=1);
// Klebstoff zur Komponenten-Registry (cms/src/Components/): gemeinsame Registry dieser Anfrage, Erweiterungen, Layout-Speicher.
// Doku: cms/docs/COMPONENTS.md

use Elvado\Components\{Registry, CoreComponents, LayoutStore};

require_once __DIR__ . '/../src/autoload.php';

/** Gemeinsame Registry: Kern-Komponenten, Radio-Komponenten (nur sichtbar mit Merkmal „radio“) und die Komponenten aktiver Erweiterungen (Hook „components_register“). */
function rrw_components(bool $fresh = false): Registry
{
    static $r = null;
    if ($r === null || $fresh) {
        $r = new Registry();
        CoreComponents::register($r);
        CoreComponents::registerRadio($r);
        rrw_components_packs($r);
        if (function_exists('rrw_np_boot')) {
            try {
                rrw_np_boot();
                rrw_np_do('components_register', $r);
            } catch (\Throwable $e) {
                error_log('[ElvadoPress] Komponenten-Erweiterung: ' . $e->getMessage());
            }
        }
    }
    return $r;
}

/**
 * Komponenten von Paketen: cms/packs/<paket>/components.php (liefert fn(Registry): void). Nur wenn das Paket in dieser Installation vorhanden ist (rrw_pack_available);
 * im eigenständigen CMS gibt es keinen Ordner cms/packs – dann entsteht nichts. Paket-Komponenten tragen die Quelle „pack:<paket>“.
 */
function rrw_components_packs(Registry $r, ?string $packsDir = null): void
{
    $GLOBALS['rrw_components_targets'] = [];
    foreach (glob(($packsDir ?? dirname(__DIR__) . '/packs') . '/*/components.php') ?: [] as $f) {
        $pack = basename(dirname($f));
        if (preg_match('/^[a-z0-9-]{1,40}$/', $pack) !== 1 || !function_exists('rrw_pack_available') || !rrw_pack_available($pack)) {
            continue;
        }
        try {
            $def = (static function (string $f) { return include $f; })($f);
            $fn = is_array($def) ? ($def['register'] ?? null) : $def;
            if (is_callable($fn)) {
                $fn($r, 'pack:' . $pack);
            }
            $t = is_array($def) && is_array($def['target'] ?? null) ? $def['target'] : null;
            if ($t !== null && preg_match('/^[a-z0-9_-]{1,40}$/', (string)($t['id'] ?? '')) === 1 && LayoutStore::validScope((string)($t['scope'] ?? ''))) {
                $GLOBALS['rrw_components_targets'][(string)$t['id']] = [
                    'id' => (string)$t['id'], 'label' => mb_substr((string)($t['label'] ?? $t['id']), 0, 60), 'scope' => (string)$t['scope'],
                    'preview' => str_starts_with((string)($t['preview'] ?? '/'), '/') && !str_starts_with((string)($t['preview'] ?? '/'), '//') ? (string)($t['preview'] ?? '/') : '/', 'source' => 'pack:' . $pack,
                ];
            }
        } catch (\Throwable $e) {
            error_log('[ElvadoPress] Paket-Komponenten ' . $pack . ': ' . $e->getMessage());
        }
    }
}

/**
 * CSS der veröffentlichten Gestaltung für gebundene Bereiche (Komponenten mit „bind“) eines Bereichs, z. B. 'site:portal'. Leer, wenn nichts veröffentlicht ist.
 * Die Seite bindet es in <head> ein; ihr eigenes HTML bleibt unverändert.
 */
function rrw_components_bound_css(string $scope, ?string $dataDir = null): string
{
    if (!LayoutStore::validScope($scope)) {
        return '';
    }
    try {
        $layout = rrw_components_store($dataDir)->published($scope);
        return $layout === null || $layout === [] ? '' : (new \Elvado\Components\Renderer(rrw_components()))->boundCss($layout);
    } catch (\Throwable $e) {
        return '';   // die Website muss immer ausgeliefert werden
    }
}

function rrw_components_store(?string $dataDir = null): LayoutStore
{
    return new LayoutStore(($dataDir ?? (defined('RRW_DATA_DIR') ? (string)RRW_DATA_DIR : dirname(__DIR__) . '/data')) . '/layouts', rrw_components());
}

/** Aktive Merkmale für den Katalog (z. B. „radio“). @return list<string> */
function rrw_components_features(?string $dataDir = null): array
{
    $f = [];
    $dir = $dataDir ?? (defined('RRW_DATA_DIR') ? (string)RRW_DATA_DIR : dirname(__DIR__) . '/data');
    if (function_exists('rrw_radio_theme_active') && rrw_radio_theme_active($dir)) {
        $f[] = 'radio';
    }
    return $f;
}

/** Bearbeitungsziele des Live Builders, die Pakete mitbringen (Komponenten, die an bestehende Bereiche der Website gebunden sind). @return list<array{id:string,label:string,scope:string,preview:string,source:string}> */
function rrw_components_targets(): array
{
    rrw_components();
    return array_values((array)($GLOBALS['rrw_components_targets'] ?? []));
}

function rrw_components_target_for_scope(string $scope): ?array
{
    foreach (rrw_components_targets() as $t) {
        if ($t['scope'] === $scope) {
            return $t;
        }
    }
    return null;
}

/** Geheimnis für Vorschau-Schlüssel (liegt in cms/data, 0600; entsteht beim ersten Gebrauch). */
function rrw_components_preview_secret(?string $dataDir = null): string
{
    $dir = $dataDir ?? (defined('RRW_DATA_DIR') ? (string)RRW_DATA_DIR : dirname(__DIR__) . '/data');
    $f = $dir . '/.preview-secret';
    $s = is_file($f) ? trim((string)@file_get_contents($f)) : '';
    if (strlen($s) < 32) {
        $s = bin2hex(random_bytes(32));
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        @file_put_contents($f, $s, LOCK_EX);
        @chmod($f, 0600);
    }
    return $s;
}

function rrw_components_preview_token(string $scope, int $ttl = 900, ?string $dataDir = null, ?int $now = null): string
{
    $exp = ($now ?? time()) + $ttl;
    return $exp . '.' . hash_hmac('sha256', $scope . '|' . $exp, rrw_components_preview_secret($dataDir));
}

function rrw_components_preview_ok(string $token, string $scope, ?string $dataDir = null, ?int $now = null): bool
{
    if (preg_match('/^([0-9]{10})\.([a-f0-9]{64})$/', $token, $m) !== 1 || (int)$m[1] < ($now ?? time())) {
        return false;
    }
    return hash_equals(hash_hmac('sha256', $scope . '|' . $m[1], rrw_components_preview_secret($dataDir)), $m[2]);
}

/**
 * Für Seiten, die ihr HTML selbst ausliefern: setzt die veröffentlichte Gestaltung gebundener Bereiche als <style> ein.
 * Mit gültigem Vorschau-Schlüssel (?rrw_ep_preview=…, nur im Live Builder) gilt der Entwurf, die Seite wird nicht indexiert und die Vorschau-Brücke geladen.
 * Ohne Layout und ohne Schlüssel bleibt $html bytegleich. Fehler lassen $html unverändert.
 * @param array<string,mixed> $query meist $_GET
 */
function rrw_components_inject(string $html, string $scope, array $query = [], ?string $dataDir = null): string
{
    try {
        $tok = (string)($query['rrw_ep_preview'] ?? '');
        $preview = $tok !== '' && rrw_components_preview_ok($tok, $scope, $dataDir);
        $store = rrw_components_store($dataDir);
        $css = '';
        if ($preview) {
            $st = $store->get($scope);
            $lay = $st['draft']['layout'] ?? $st['published']['layout'] ?? [];
        } else {
            $lay = $store->published($scope) ?? [];
        }
        if ($lay !== []) {
            $css = (new \Elvado\Components\Renderer(rrw_components()))->boundCss((array)$lay);
        }
        $head = $css !== '' ? '<style id="ep-bound-css">' . $css . '</style>' : '';
        $tail = '';
        if ($preview) {
            if (!headers_sent()) {
                header('X-Robots-Tag: noindex, nofollow');
                header('Cache-Control: no-store');
            }
            $tail = '<script src="/cms/assets/preview-bridge.js?v=1" defer></script>';
        }
        if ($head === '' && $tail === '') {
            return $html;
        }
        if ($head !== '') {
            $p = stripos($html, '</head>');
            $html = $p === false ? $head . $html : substr($html, 0, $p) . $head . substr($html, $p);
        }
        if ($tail !== '') {
            $p = strripos($html, '</body>');
            $html = $p === false ? $html . $tail : substr($html, 0, $p) . $tail . substr($html, $p);
        }
        return $html;
    } catch (\Throwable $e) {
        return $html;
    }
}

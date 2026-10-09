<?php
declare(strict_types=1);
// Klebstoff zur Komponenten-Registry (cms/src/Components/): gemeinsame Registry dieser Anfrage, Erweiterungen, Layout-Speicher.
// Doku: cms/docs/COMPONENTS.md

use Elvado\Components\{Registry, CoreComponents, LayoutStore};

require_once __DIR__ . '/../src/autoload.php';

/** Gemeinsame Registry: Kern-Komponenten, Radio-Komponenten (nur sichtbar mit Merkmal „radio“) und die Komponenten aktiver Erweiterungen (Hook „components_register“). */
function elvado_components(bool $fresh = false): Registry
{
    static $r = null;
    if ($r === null || $fresh) {
        $r = new Registry();
        CoreComponents::register($r);
        elvado_components_packs($r);
        if (function_exists('elvado_np_boot')) {
            try {
                elvado_np_boot();
                elvado_np_do('components_register', $r);
            } catch (\Throwable $e) {
                error_log('[ElvadoPress] Komponenten-Erweiterung: ' . $e->getMessage());
            }
        }
    }
    return $r;
}

/**
 * Komponenten von Paketen: cms/packs/<paket>/components.php (liefert fn(Registry): void). Nur wenn das Paket in dieser Installation vorhanden ist (elvado_pack_available);
 * im eigenständigen CMS gibt es keinen Ordner cms/packs – dann entsteht nichts. Paket-Komponenten tragen die Quelle „pack:<paket>“.
 */
function elvado_components_packs(Registry $r, ?string $packsDir = null): void
{
    $GLOBALS['elvado_components_targets'] = [];
    foreach (glob(($packsDir ?? dirname(__DIR__) . '/packs') . '/*/components.php') ?: [] as $f) {
        $pack = basename(dirname($f));
        if (preg_match('/^[a-z0-9-]{1,40}$/', $pack) !== 1 || !function_exists('elvado_pack_available') || !elvado_pack_available($pack)) {
            continue;
        }
        try {
            $def = (static function (string $f) { return include $f; })($f);
            $fn = is_array($def) ? ($def['register'] ?? null) : $def;
            if (is_callable($fn)) {
                $fn($r, 'pack:' . $pack);
            }
            // Ein Paket kann ein Ziel („target“) oder mehrere („targets“, z. B. je Marke) anbieten.
            $list = is_array($def) ? (is_array($def['targets'] ?? null) ? array_values($def['targets']) : (is_array($def['target'] ?? null) ? [$def['target']] : [])) : [];
            foreach ($list as $t) {
                if (is_array($t) && preg_match('/^[a-z0-9_-]{1,40}$/', (string)($t['id'] ?? '')) === 1 && LayoutStore::validScope((string)($t['scope'] ?? ''))) {
                    $GLOBALS['elvado_components_targets'][(string)$t['id']] = [
                        'id' => (string)$t['id'], 'label' => mb_substr((string)($t['label'] ?? $t['id']), 0, 60), 'scope' => (string)$t['scope'],
                        'preview' => str_starts_with((string)($t['preview'] ?? '/'), '/') && !str_starts_with((string)($t['preview'] ?? '/'), '//') ? (string)($t['preview'] ?? '/') : '/', 'source' => 'pack:' . $pack,
                        'brand' => preg_match('/^[a-z0-9_-]{1,40}$/', (string)($t['brand'] ?? '')) === 1 ? (string)$t['brand'] : '',
                    ];
                }
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
function elvado_components_bound_css(string $scope, ?string $dataDir = null): string
{
    if (!LayoutStore::validScope($scope)) {
        return '';
    }
    try {
        $layout = elvado_components_store($dataDir)->published($scope);
        return $layout === null || $layout === [] ? '' : (new \Elvado\Components\Renderer(elvado_components()))->boundCss($layout);
    } catch (\Throwable $e) {
        return '';   // die Website muss immer ausgeliefert werden
    }
}

function elvado_components_store(?string $dataDir = null): LayoutStore
{
    return new LayoutStore(($dataDir ?? (defined('ELVADO_DATA_DIR') ? (string)ELVADO_DATA_DIR : dirname(__DIR__) . '/data')) . '/layouts', elvado_components());
}

/** Aktive Merkmale für den Katalog (Erweiterungen können sie über Pakete einführen). @return list<string> */
function elvado_components_features(?string $dataDir = null): array
{
    return [];
}

/** Bearbeitungsziele des Live Builders, die Pakete mitbringen (Komponenten, die an bestehende Bereiche der Website gebunden sind). @return list<array{id:string,label:string,scope:string,preview:string,source:string}> */
function elvado_components_targets(): array
{
    elvado_components();
    $t = array_values((array)($GLOBALS['elvado_components_targets'] ?? []));
    if ($t === []) {   // ohne Paket-Ziel (eigenständiges CMS): die Website selbst, unabhängig vom Theme
        $t[] = ['id' => 'website', 'label' => 'Website (alle Themes)', 'scope' => 'site:website', 'preview' => '/', 'source' => 'core', 'brand' => ''];
    }
    return $t;
}

function elvado_components_target_for_scope(string $scope): ?array
{
    foreach (elvado_components_targets() as $t) {
        if ($t['scope'] === $scope) {
            return $t;
        }
    }
    return null;
}

/** Geheimnis für Vorschau-Schlüssel (liegt in cms/data, 0600; entsteht beim ersten Gebrauch). */
function elvado_components_preview_secret(?string $dataDir = null): string
{
    $dir = $dataDir ?? (defined('ELVADO_DATA_DIR') ? (string)ELVADO_DATA_DIR : dirname(__DIR__) . '/data');
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

function elvado_components_preview_token(string $scope, int $ttl = 900, ?string $dataDir = null, ?int $now = null): string
{
    $exp = ($now ?? time()) + $ttl;
    return $exp . '.' . hash_hmac('sha256', $scope . '|' . $exp, elvado_components_preview_secret($dataDir));
}

function elvado_components_preview_ok(string $token, string $scope, ?string $dataDir = null, ?int $now = null): bool
{
    if (preg_match('/^([0-9]{10})\.([a-f0-9]{64})$/', $token, $m) !== 1 || (int)$m[1] < ($now ?? time())) {
        return false;
    }
    return hash_equals(hash_hmac('sha256', $scope . '|' . $m[1], elvado_components_preview_secret($dataDir)), $m[2]);
}

/**
 * Festes Skript, das die Inhalts-Änderungen anwendet: Texte (nur Textknoten, nie HTML) und Reihenfolge (Elemente werden nur innerhalb ihres Containers an die Plätze der gelisteten Elemente gesetzt).
 * Wendet nach Änderungen der Seite erneut an (Seiten, die ihre Inhalte per Skript nachladen). Fehler bleiben still.
 */
function elvado_components_overrides_js(): string
{
    return '(function(){try{var d=JSON.parse(document.getElementById("ep-overrides-data").textContent),busy=false,t=null,BAD={SCRIPT:1,STYLE:1,TEXTAREA:1,INPUT:1,SELECT:1,IFRAME:1,OBJECT:1};'
        . 'function q(s,r){try{return(r||document).querySelector(s)}catch(e){return null}}'
        . 'function txt(){(d.texts||[]).forEach(function(x){var el=q(x.s);if(!el||BAD[el.tagName])return;var n=el.childNodes[x.k];if(n&&n.nodeType===3&&n.nodeValue!==x.t)n.nodeValue=x.t})}'
        . 'function ord(){(d.order||[]).forEach(function(o){var c=q(o.c);if(!c)return;var els=[];o.o.forEach(function(s){var e=q(s);if(e&&e.parentNode===c&&els.indexOf(e)<0)els.push(e)});if(els.length<2)return;'
        . 'var cur=[].filter.call(c.children,function(k){return els.indexOf(k)>=0}),same=true;for(var i=0;i<els.length;i++)if(cur[i]!==els[i]){same=false;break}if(same)return;'
        . 'var ph=cur.map(function(k){var m=document.createComment("ep");c.insertBefore(m,k);return m});cur.forEach(function(k){c.removeChild(k)});els.forEach(function(e,i){c.insertBefore(e,ph[i])});ph.forEach(function(m){c.removeChild(m)})})}'
        . 'function run(){if(busy)return;busy=true;try{txt();ord()}catch(e){}busy=false}'
        . 'run();if(window.MutationObserver)new MutationObserver(function(){clearTimeout(t);t=setTimeout(run,60)}).observe(document.documentElement,{childList:true,subtree:true});'
        . '}catch(e){}})();';
}

/**
 * Für Seiten, die ihr HTML selbst ausliefern: setzt die veröffentlichte Gestaltung gebundener Bereiche als <style> ein.
 * Mit gültigem Vorschau-Schlüssel (?elvado_ep_preview=…, nur im Live Builder) gilt der Entwurf, die Seite wird nicht indexiert und die Vorschau-Brücke geladen.
 * Ohne Layout und ohne Schlüssel bleibt $html bytegleich. Fehler lassen $html unverändert.
 * @param array<string,mixed> $query meist $_GET
 */
function elvado_components_inject(string $html, string $scope, array $query = [], ?string $dataDir = null): string
{
    try {
        $tok = (string)($query['elvado_ep_preview'] ?? '');
        $preview = $tok !== '' && elvado_components_preview_ok($tok, $scope, $dataDir);
        $dir = $dataDir ?? (defined('ELVADO_DATA_DIR') ? (string)ELVADO_DATA_DIR : dirname(__DIR__) . '/data');
        if (!$preview && (!LayoutStore::validScope($scope) || !is_file($dir . '/layouts/' . str_replace(':', '__', $scope) . '.json'))) {
            return $html;   // nichts gespeichert: die Seite bleibt unberührt (und kostet nichts)
        }
        $store = elvado_components_store($dataDir);
        $css = '';
        if ($preview) {
            $st = $store->get($scope);
            $lay = $st['draft']['layout'] ?? $st['published']['layout'] ?? [];
        } else {
            $lay = $store->published($scope) ?? [];
        }
        if ($lay !== []) {
            $css = (new \Elvado\Components\Renderer(elvado_components()))->boundCss((array)$lay);
        }
        $head = $css !== '' ? '<style id="ep-bound-css">' . $css . '</style>' : '';
        $tail = '';
        // Inhalts-Änderungen (Texte, Reihenfolge) am echten Seiten-HTML: Daten als JSON (ohne Möglichkeit, das Skript zu verlassen) + kleines festes Skript
        $ov = $lay !== [] ? (new \Elvado\Components\Renderer(elvado_components()))->overrides((array)$lay) : ['texts' => [], 'order' => []];
        if ($ov['texts'] !== [] || $ov['order'] !== []) {
            $tail .= '<script id="ep-overrides-data" type="application/json">' . json_encode($ov, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>'
                . '<script id="ep-overrides">' . elvado_components_overrides_js() . '</script>';
        }
        if ($preview) {
            if (!headers_sent()) {
                header('X-Robots-Tag: noindex, nofollow');
                header('Cache-Control: no-store');
            }
            $tail .= '<script src="/cms/assets/preview-bridge.js?v=2" defer></script>';
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

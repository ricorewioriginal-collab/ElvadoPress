<?php
// Prüft die Paket-Erweiterung der Komponenten: gebundene Bereiche („bind“), CSS dafür, Paket-Lader, Bearbeitungsziele, Vorschau-Schlüssel und das Einsetzen in fremdes HTML.
// Aufruf: php scripts/test-components-packs.php
declare(strict_types=1);
function elvado_pack_available(string $pack = 'demo-pack', ?string $d = null): bool { return $pack === 'demo-pack'; }   // nur dieses Paket „ist vorhanden“
require __DIR__ . '/../cms/lib/components.php';
use Elvado\Components\{Registry, Component, Layout, Renderer, LayoutStore};
use Elvado\Wp\Actor;

$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
function rmrf(string $d): void { if (!is_dir($d)) return; foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') continue; $p = "$d/$f"; is_dir($p) && !is_link($p) ? rmrf($p) : @unlink($p); } @rmdir($d); }
function throws(callable $f, string $cls = \Throwable::class): bool { try { $f(); } catch (\Throwable $e) { return $e instanceof $cls; } return false; }
$tmp = sys_get_temp_dir() . '/comp-pack-' . bin2hex(random_bytes(4));
register_shutdown_function(fn() => rmrf($tmp));
mkdir("$tmp/data", 0755, true);

$bound = static fn(Registry $r, string $src = 'pack:demo-pack') => [
    $r->register(['id' => 'demo_header', 'name' => 'Kopfbereich', 'category' => 'structure', 'bind' => '#main-header', 'rules' => ['locked' => true, 'repeatable' => false], 'fields' => [
        ['k' => 'sticky', 'label' => 'Suche', 'type' => 'checkbox', 'default' => true, 'group' => 'behavior', 'css' => ['hide' => '#hdr-search', 'when' => 'off']],
        ['k' => 'bg', 'label' => 'Hintergrund', 'type' => 'color', 'default' => '', 'group' => 'design', 'css' => ['prop' => 'background-color']],
        ['k' => 'height', 'label' => 'Höhe', 'type' => 'number', 'min' => 0, 'max' => 300, 'default' => 0, 'group' => 'design', 'responsive' => true, 'css' => ['prop' => 'min-height', 'unit' => 'px', 'skip_zero' => true]],
    ]], $src),
    $r->register(['id' => 'demo_hero', 'name' => 'Hero', 'category' => 'content', 'bind' => '#sec-start', 'fields' => [
        ['k' => 'pad', 'label' => 'Abstand', 'type' => 'number', 'min' => 0, 'max' => 200, 'default' => 0, 'group' => 'design', 'css' => ['prop' => 'padding', 'unit' => 'px', 'skip_zero' => true, 'target' => ' .inner']]]], $src),
];

// ───────── 1) Definition ─────────
$r = new Registry(); [$h, $hero] = $bound($r);
t('bind setzt Rendering-Art „bound“', $h->renderer === 'bound' && $h->bind === '#main-header' && $h->toArray()['bind'] === '#main-header');
t('Ungültiger Selektor wird abgelehnt', throws(fn() => $r->register(['id' => 'bad_sel', 'name' => 'x', 'bind' => 'body > script'], 'pack:demo-pack'), InvalidArgumentException::class) && throws(fn() => $r->register(['id' => 'bad_sel2', 'name' => 'x', 'bind' => '#a{}'], 'pack:demo-pack'), InvalidArgumentException::class));
t('Gliederungs-Elemente als Selektor erlaubt, andere Tags nicht', $r->register(['id' => 'ok_footer', 'name' => 'F', 'bind' => 'footer'], 'pack:demo-pack')->bind === 'footer' && throws(fn() => $r->register(['id' => 'bad_tag', 'name' => 'x', 'bind' => 'script'], 'pack:demo-pack')) && throws(fn() => $r->register(['id' => 'bad_tag2', 'name' => 'x', 'bind' => 'body'], 'pack:demo-pack')));
t('„bound“ ohne bind und umgekehrt abgelehnt', throws(fn() => $r->register(['id' => 'bad_b1', 'name' => 'x', 'renderer' => 'bound'], 'pack:demo-pack')) && throws(fn() => $r->register(['id' => 'bad_b2', 'name' => 'x', 'bind' => '#a', 'renderer' => 'runtime'], 'pack:demo-pack')));
t('Katalog nennt bind und Quelle', (function () use ($r) { foreach ($r->catalog(['admin' => true])['components'] as $c) { if ($c['id'] === 'demo_header') { return $c['bind'] === '#main-header' && $c['source'] === 'pack:demo-pack'; } } return false; })());

// ───────── 2) Layout und CSS ─────────
$lay = new Layout($r);
$clean = $lay->clean([
    ['id' => 'a1', 'type' => 'demo_header', 'props' => ['bg' => '#112233', 'height' => 80, 'sticky' => false], 'responsive' => ['mobile' => ['height' => 60]]],
    ['id' => 'a2', 'type' => 'demo_hero', 'props' => ['pad' => 24]],
], ['admin' => true]);
t('Layout mit gebundenen Bereichen bleibt erhalten', count($clean) === 2 && $clean[0]['type'] === 'demo_header');
$rd = new Renderer($r);
$out = $rd->render($clean);
t('Normales Rendern gibt für gebundene Bereiche nichts aus (Website rendert selbst)', trim($out['html']) === '' && !str_contains($out['css'], '#main-header'));
$css = $rd->boundCss($clean);
t('CSS mit Selektor der Website', str_contains($css, '#main-header{background-color:#112233!important;}') && str_contains($css, '#main-header{min-height:80px!important;}'), $css);
t('Selektoren mit mehr Gewicht als die Website-Regeln (html body …)', str_starts_with($css, 'html body #main-header') && !preg_match('/(^|\})#main-header/', $css));
t('Schalter blendet Teil aus', str_contains($css, '#main-header #hdr-search{display:none!important}'));
t('Mobil-Wert als Media-Query', str_contains($css, '@media (max-width:640px){html body #main-header{min-height:60px!important;}}'));
t('Ziel-Teilselektor', str_contains($css, '#sec-start .inner{padding:24px!important;}'));
t('Keine data-ep-id im CSS', !str_contains($css, 'data-ep-id'));
t('Vorgaben erzeugen kein CSS (Seite bleibt unverändert)', $rd->boundCss($lay->clean([['id' => 'b1', 'type' => 'demo_header', 'props' => ['sticky' => true]], ['id' => 'b2', 'type' => 'demo_hero', 'props' => []]], ['admin' => true])) === '');
t('Fehlende Props im gespeicherten Layout = Vorgabe (kein ungewolltes Ausblenden)', $rd->boundCss([['id' => 'z1', 'type' => 'demo_header', 'props' => []]]) === '' && str_contains($rd->boundCss([['id' => 'z1', 'type' => 'demo_header', 'props' => ['bg' => '#111111']]]), '#main-header{background-color:#111111!important;}') && !str_contains($rd->boundCss([['id' => 'z1', 'type' => 'demo_header', 'props' => ['bg' => '#111111']]]), 'display:none'));
$hid = $lay->clean([['id' => 'c1', 'type' => 'demo_header', 'hidden' => true, 'props' => []], ['id' => 'c2', 'type' => 'demo_hero', 'props' => [], 'visibility' => ['devices' => ['desktop']]]], ['admin' => true]);
$hc = $rd->boundCss($hid);
t('Ausgeblendet = display:none', str_contains($hc, '#main-header{display:none!important}'));
t('Nicht auf allen Geräten = Media-Queries', str_contains($hc, '@media (min-width:641px) and (max-width:1024px){html body #sec-start{display:none!important}}') && str_contains($hc, '@media (max-width:640px){html body #sec-start{display:none!important}}') && !str_contains($hc, '@media (min-width:1025px){#sec-start'));
$sched = $lay->clean([['id' => 'd1', 'type' => 'demo_hero', 'props' => [], 'visibility' => ['from' => '2999-01-01 00:00']]], ['admin' => true]);
t('Zeitplan: noch nicht sichtbar → ausgeblendet', str_contains($rd->boundCss($sched, ['now' => 1000]), '#sec-start{display:none!important}') && !str_contains($rd->boundCss($sched, ['now' => 32503680000 + 10]), 'display:none'));
t('Unbekannter Typ im Layout erzeugt nichts', $rd->boundCss([['id' => 'x', 'type' => 'gibt_es_nicht', 'props' => []]]) === '');

// ───────── 3) Paket-Lader und Ziele ─────────
mkdir("$tmp/packs/demo-pack", 0755, true); mkdir("$tmp/packs/other-pack", 0755, true); mkdir("$tmp/packs/BAD_NAME", 0755, true);
file_put_contents("$tmp/packs/demo-pack/components.php", '<?php return ["register" => function ($r, $src) { $r->register(["id" => "pk_one", "name" => "Eins", "bind" => "#one"], $src); }, "target" => ["id" => "demo", "label" => "Demo-Portal", "scope" => "site:demo", "preview" => "/"]];');
file_put_contents("$tmp/packs/other-pack/components.php", '<?php return function ($r, $src) { $r->register(["id" => "pk_other", "name" => "Anderes"], $src); };');
file_put_contents("$tmp/packs/BAD_NAME/components.php", '<?php return function ($r, $src) { $r->register(["id" => "pk_bad", "name" => "x"], $src); };');
$pr = new Registry(); elvado_components_packs($pr, "$tmp/packs");
t('Nur vorhandene Pakete laden', $pr->has('pk_one') && !$pr->has('pk_other') && !$pr->has('pk_bad'));
t('Paket-Komponenten tragen die Quelle „pack:<paket>“', $pr->get('pk_one')->source === 'pack:demo-pack' && $pr->get('pk_one')->bind === '#one');
t('Bearbeitungsziel wird gemeldet', ($GLOBALS['elvado_components_targets']['demo']['scope'] ?? '') === 'site:demo' && $GLOBALS['elvado_components_targets']['demo']['source'] === 'pack:demo-pack');
// Mehrere Ziele je Paket (z. B. eine Marke je Ziel)
file_put_contents("$tmp/packs/demo-pack/components.php", '<?php return ["register" => function ($r, $src) { $r->register(["id" => "pk_one", "name" => "Eins", "bind" => "#one"], $src); }, "targets" => [["id" => "m1", "label" => "Marke 1", "scope" => "site:m1", "preview" => "/", "brand" => "marke-1"], ["id" => "m2", "label" => "Marke 2", "scope" => "site:m2", "preview" => "/?elvado_brand=m2"], ["id" => "bad id", "scope" => "site:x"], ["id" => "m3", "scope" => "ungueltig"]]];');
$pm = new Registry(); elvado_components_packs($pm, "$tmp/packs");
t('Mehrere Ziele eines Pakets werden gemeldet, ungültige verworfen', ($GLOBALS['elvado_components_targets']['m1']['scope'] ?? '') === 'site:m1' && ($GLOBALS['elvado_components_targets']['m2']['preview'] ?? '') === '/?elvado_brand=m2' && !isset($GLOBALS['elvado_components_targets']['m3']) && count($GLOBALS['elvado_components_targets']) === 2 && ($GLOBALS['elvado_components_targets']['m1']['brand'] ?? '') === 'marke-1' && ($GLOBALS['elvado_components_targets']['m2']['brand'] ?? 'x') === '');
file_put_contents("$tmp/packs/demo-pack/components.php", '<?php throw new RuntimeException("kaputt");');
$pr2 = new Registry(); elvado_components_packs($pr2, "$tmp/packs");
t('Fehler in einem Paket stoppt nichts', !$pr2->has('pk_one'));
$pr3 = new Registry(); elvado_components_packs($pr3, "$tmp/gibt-es-nicht");
t('Ohne Paketordner (eigenständiges CMS) entsteht nichts', $pr3->all() === [] && ($GLOBALS['elvado_components_targets'] ?? []) === []);

// ───────── 4) Vorschau-Schlüssel ─────────
$d = "$tmp/data"; $now = 1_800_000_000;
$tok = elvado_components_preview_token('site:demo', 900, $d, $now);
t('Schlüssel gültig für Bereich und Zeit', elvado_components_preview_ok($tok, 'site:demo', $d, $now + 100));
t('Schlüssel gilt nicht für anderen Bereich', !elvado_components_preview_ok($tok, 'site:andere', $d, $now + 100));
t('Schlüssel läuft ab', !elvado_components_preview_ok($tok, 'site:demo', $d, $now + 901));
[$exp, $sig] = explode('.', $tok);
t('Manipulierter Schlüssel abgelehnt', !elvado_components_preview_ok(($exp + 9999) . '.' . $sig, 'site:demo', $d, $now) && !elvado_components_preview_ok($exp . '.' . strrev($sig), 'site:demo', $d, $now) && !elvado_components_preview_ok('', 'site:demo', $d) && !elvado_components_preview_ok('abc', 'site:demo', $d));
t('Geheimnis geschützt gespeichert', (fileperms("$d/.preview-secret") & 0777) === 0600 && strlen(trim((string)file_get_contents("$d/.preview-secret"))) === 64);

// ───────── 5) Einsetzen in fremdes HTML ─────────
$shared = elvado_components();   // gemeinsame Registry dieser Anfrage: gebundene Test-Komponenten ergänzen
$bound($shared);
define('ELVADO_DATA_DIR', $d);
$page = "<html><head><title>x</title></head><body><header id=\"main-header\"></header></body></html>";
t('Ohne Layout und Schlüssel: Ausgabe bytegleich', elvado_components_inject($page, 'site:demo', [], $d) === $page);
t('Ungültiger Scope: unverändert', elvado_components_inject($page, "evil'; x", [], $d) === $page);
$store = elvado_components_store($d); $admin = new Actor('a', 'admin');
$store->saveDraft('site:demo', [['id' => 'h1', 'type' => 'demo_header', 'props' => ['bg' => '#abcdef']]], $admin);
t('Nur Entwurf: öffentliche Seite bleibt unverändert', elvado_components_inject($page, 'site:demo', [], $d) === $page);
$pv = elvado_components_inject($page, 'site:demo', ['elvado_ep_preview' => elvado_components_preview_token('site:demo', 900, $d)], $d);
t('Vorschau (gültiger Schlüssel): Entwurf als <style> im Kopf + Brücke vor </body>', str_contains($pv, '<style id="ep-bound-css">html body #main-header{background-color:#abcdef!important;}</style></head>') && str_contains($pv, '<script src="/cms/assets/preview-bridge.js?v=2" defer></script></body>'), $pv);
t('Vorschau mit falschem Schlüssel: wie öffentlich', elvado_components_inject($page, 'site:demo', ['elvado_ep_preview' => '1.' . str_repeat('a', 64)], $d) === $page);
$store->publish('site:demo', $admin);
$pub = elvado_components_inject($page, 'site:demo', [], $d);
t('Veröffentlicht: CSS öffentlich, keine Brücke', str_contains($pub, '#main-header{background-color:#abcdef!important;}') && !str_contains($pub, 'preview-bridge'));
$store->saveDraft('site:demo', [['id' => 'h1', 'type' => 'demo_header', 'props' => ['bg' => '#000001']]], $admin);
t('Entwurf neben Veröffentlichtem: Besucher sehen die veröffentlichte Fassung, die Vorschau den Entwurf', str_contains(elvado_components_inject($page, 'site:demo', [], $d), '#abcdef') && str_contains(elvado_components_inject($page, 'site:demo', ['elvado_ep_preview' => elvado_components_preview_token('site:demo', 900, $d)], $d), '#000001'));
t('Ohne </head>/</body> wird trotzdem eingefügt', str_contains(elvado_components_inject('<div id="main-header"></div>', 'site:demo', [], $d), '<style id="ep-bound-css">'));
t('Nur Administratoren speichern Layouts des Bereichs', throws(fn() => $store->saveDraft('site:demo', [], new Actor('autor1', 'autor')), \Elvado\Wp\PermissionException::class));

// ───────── 5b) Ansicht wählen (css.variants) ─────────
$rv = new Registry();
$vc = $rv->register(['id' => 'demo_grid', 'name' => 'Raster', 'category' => 'content', 'bind' => '#grid-area', 'fields' => [
    ['k' => 'view', 'label' => 'Ansicht', 'type' => 'select', 'options' => ['' => 'Standard', 'list' => 'Liste'], 'default' => '', 'group' => 'design',
        'css' => ['variants' => ['list' => [[' .card', 'flex-direction:row;gap:4px'], [' .card', 'x{y:1}']]]]],
]], 'pack:demo-pack');
$rend = new \Elvado\Components\Renderer($rv);
$cssV = $rend->css($vc, ['id' => 'g1', 'props' => ['view' => 'list']], 'data-ep-id', '#grid-area');
t('Variante: Regeln der gewählten Ansicht, gebunden mit !important', str_contains($cssV, '#grid-area .card{flex-direction:row!important;gap:4px!important;}'));
t('Variante: ungültige Deklaration wird verworfen', !str_contains($cssV, 'x{y'));
t('Variante: Standard und unbekannter Wert erzeugen keine Regeln', $rend->css($vc, ['id' => 'g1', 'props' => ['view' => '']], 'data-ep-id', '#grid-area') === '' && $rend->css($vc, ['id' => 'g1', 'props' => ['view' => 'evil']], 'data-ep-id', '#grid-area') === '');

// ───────── 6) Verdrahtung ─────────
$api = (string)file_get_contents(__DIR__ . '/../cms/components-api.php');
t('API: Vorschau-Aktion nur für Administratoren und Ziele', str_contains($api, "'layout_preview'") && str_contains($api, '!$elvadoCActor->isAdmin()'));
$js = (string)file_get_contents(__DIR__ . '/../cms/assets/live-builder.js');
t('Builder: Bereiche der Website nur ausblenden/gestalten (kein Verschieben/Löschen)', str_contains($js, "target&&op!=='hide'") && str_contains($js, 'function move(loc,to){if(target)return;'));
echo $fail ? "$fail von $n Prüfungen fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n";
exit($fail ? 1 : 0);

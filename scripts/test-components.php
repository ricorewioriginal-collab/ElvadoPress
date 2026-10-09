<?php
// Prüft die Komponenten-Registry (cms/src/Components): Schema/Bereinigung, Regeln (locked/sortable/droppable/repeatable/slot), Rechte, Sichtbarkeit, responsive Werte,
// native Darstellung samt CSS, Erweiterungen, Layout-Speicher (Entwurf, Veröffentlichen, Revisionen, Rollback, Termin, geschützte Komponenten). Aufruf: php scripts/test-components.php
declare(strict_types=1);
require __DIR__ . '/../cms/src/autoload.php';
require __DIR__ . '/../cms/lib/htmlsafe.php';
use Elvado\Components\{Registry, CoreComponents, Component, Layout, Renderer, Sanitizer, LayoutStore};
use Elvado\Wp\{Actor, PermissionException};

$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
function rmrf(string $d): void { if (!is_dir($d)) return; foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') continue; $p = "$d/$f"; is_dir($p) && !is_link($p) ? rmrf($p) : @unlink($p); } @rmdir($d); }
function throws(callable $f, string $cls = \Throwable::class): bool { try { $f(); } catch (\Throwable $e) { return $e instanceof $cls; } return false; }
$tmp = sys_get_temp_dir() . '/comp-test-' . bin2hex(random_bytes(4));
register_shutdown_function(fn() => rmrf($tmp));

// ───────── 1) Sanitizer ─────────
$S = fn(array $f, mixed $v, array $c = []) => Sanitizer::value($f, $v, $c);
t('text: Tags/Steuerzeichen/Länge', $S(['type' => 'text'], "  <b>Hi</b>\n\t  Welt\x00 ") === 'Hi Welt' && mb_strlen($S(['type' => 'text', 'max' => 5], 'äöüäöüäö')) === 5 && $S(['type' => 'text'], ['x']) === '');
t('textarea: Zeilen bleiben, Tags nicht', $S(['type' => 'textarea'], "a\n<b>b</b>\x07") === "a\nb");
t('html: Skripte raus, Format bleibt', ($h = $S(['type' => 'html'], '<p onclick="x()">A<script>alert(1)</script><strong>B</strong></p>')) !== '' && !str_contains($h, 'script') && !str_contains($h, 'onclick') && str_contains($h, '<strong>B</strong>'), $h);
$urls = ['/seite' => '/seite', 'https://a.de/x?y=1' => 'https://a.de/x?y=1', 'mailto:a@b.de' => 'mailto:a@b.de', 'tel:+4912' => 'tel:+4912', '#top' => '#top', 'http://a.de' => 'http://a.de',
    'javascript:alert(1)' => '', 'data:text/html,x' => '', '//evil.de/x' => '', 'https://a.de/ x' => '', "https://a.de/\"x" => '', 'ftp://a.de' => '', str_repeat('a', 1300) => ''];
$bad = []; foreach ($urls as $in => $out) { if ($S(['type' => 'url'], $in) !== $out) { $bad[] = substr($in, 0, 30); } }
t('url: nur harmlose Adressen', $bad === [], json_encode($bad));
t('image: nur https oder /', $S(['type' => 'image'], 'https://a.de/a.png') === 'https://a.de/a.png' && $S(['type' => 'image'], '/m/a.png') === '/m/a.png' && $S(['type' => 'image'], 'http://a.de/a.png') === '' && $S(['type' => 'image'], 'mailto:a@b.de') === '' && $S(['type' => 'image'], '#x') === '');
t('checkbox', $S(['type' => 'checkbox'], 'true') === true && $S(['type' => 'checkbox'], '0') === false && $S(['type' => 'checkbox'], null) === false && $S(['type' => 'checkbox', 'default' => true], null) === true);
t('number: Grenzen, Text', $S(['type' => 'number', 'min' => 1, 'max' => 12], 99) === 12 && $S(['type' => 'number', 'min' => 1, 'max' => 12], -5) === 1 && $S(['type' => 'number', 'min' => 0, 'max' => 9], 'abc') === 0 && $S(['type' => 'number', 'min' => 0, 'max' => 9], '7') === 7);
t('select: nur Optionen, sonst Vorgabe', $S(['type' => 'select', 'options' => ['a' => 'A', 'b' => 'B'], 'default' => 'b'], 'zzz') === 'b' && $S(['type' => 'select', 'options' => ['a' => 'A', '0' => 'Null']], '0') === '0' && $S(['type' => 'select', 'options' => ['a' => 'A']], 'a') === 'a');
t('color', $S(['type' => 'color'], '#AbC') === '#abc' && $S(['type' => 'color'], '#12345678') === '' && $S(['type' => 'color'], 'red') === '' && $S(['type' => 'color', 'default' => '#000000'], 'url(x)') === '#000000');
$items = ['type' => 'items', 'max_items' => 2, 'item' => [['k' => 'title', 'type' => 'text', 'max' => 5], ['k' => 'url', 'type' => 'url']]];
t('items: Unterfelder, leere weg, Obergrenze', $S($items, [['title' => 'Langer Titel', 'url' => 'javascript:x'], ['title' => '', 'url' => ''], 'kaputt', ['title' => 'B'], ['title' => 'C']]) === [['title' => 'Lange', 'url' => ''], ['title' => 'B', 'url' => '']]);

// ───────── 2) Component-Definition ─────────
$ok = ['id' => 'demo_box', 'name' => 'Demo', 'category' => 'content', 'fields' => [['k' => 'title', 'type' => 'text'], ['k' => 'n', 'type' => 'number', 'default' => 5]]];
$c = Component::from($ok, 'ext', fn($p) => '<b>' . $p['title'] . '</b>');
t('Definition: Vorgaben und Standardwerte', $c->defaults === ['title' => '', 'n' => 5] && $c->rules['repeatable'] === true && $c->rules['droppable'] === false && $c->rules['use'] === 'autor' && $c->renderer === 'native' && $c->fields[0]['group'] === 'content' && $c->fields[0]['responsive'] === false && $c->icon === 'cube');
$bads = [
    'Kennung' => ['id' => 'Bad-ID'] + $ok, 'Kategorie' => ['category' => 'x'] + $ok, 'Rendering-Art' => ['renderer' => 'magie'] + $ok, 'Feldtyp' => ['fields' => [['k' => 'a', 'type' => 'datei']]] + $ok,
    'Feldname' => ['fields' => [['k' => 'A b', 'type' => 'text']]] + $ok, 'doppeltes Feld' => ['fields' => [['k' => 'a', 'type' => 'text'], ['k' => 'a', 'type' => 'text']]] + $ok,
    'Auswahl ohne Optionen' => ['fields' => [['k' => 'a', 'type' => 'select']]] + $ok, 'Liste ohne Unterfelder' => ['fields' => [['k' => 'a', 'type' => 'items']]] + $ok,
    'Slot' => ['rules' => ['slot' => 'mitte']] + $ok, 'Rechtestufe' => ['rules' => ['use' => 'jeder']] + $ok,
];
foreach ($bads as $label => $d) { t("Definition abgelehnt: $label", throws(fn() => Component::from($d, 'ext', fn() => ''), InvalidArgumentException::class)); }
t('native ohne Darstellung abgelehnt', throws(fn() => Component::from(['renderer' => 'native'] + $ok), InvalidArgumentException::class));
t('Ohne Darstellung = runtime', Component::from($ok)->renderer === 'runtime' && !Component::from($ok)->hasRenderer());

// ───────── 3) Registry ─────────
$reg = new Registry(); CoreComponents::register($reg);
$need = ['header', 'footer', 'hero', 'navigation', 'text', 'image', 'gallery', 'button', 'container', 'columns', 'posts', 'audio', 'video', 'form', 'newsletter', 'widget_area', 'wp_block', 'wp_shortcode', 'plugin_widget', 'html'];
t('Kern enthält die geforderten Komponenten', array_diff($need, array_keys($reg->all())) === [], json_encode(array_diff($need, array_keys($reg->all()))));
t('Keine Produkt-/Markenkomponenten im neutralen Kern', array_intersect(['partner', 'social_wall', 'sender', 'ricorewi', 'radio_player', 'podcast'], array_keys($reg->all())) === []);
$brand = false; foreach ($reg->all() as $comp) { if (stripos(json_encode($comp->toArray()), 'ricorewi') !== false) { $brand = true; } }
t('Keine Markeninhalte in Definitionen', !$brand);
$cat = $reg->catalog(['admin' => false, 'features' => []]); $ids = array_column($cat['components'], 'id');
t('Katalog Autor: keine Admin-Komponenten', !in_array('header', $ids, true) && !in_array('wp_block', $ids, true) && in_array('text', $ids, true) && in_array('button', $ids, true));
$cat2 = $reg->catalog(['admin' => true, 'features' => []]); $ids2 = array_column($cat2['components'], 'id');
t('Katalog Administrator', in_array('header', $ids2, true) && in_array('wp_shortcode', $ids2, true));
t('Katalog: Kategorien, Gruppen, keine Funktionen', isset($cat['categories']['structure']) && isset($cat['groups']['design']) && !str_contains(json_encode($cat), 'Closure') && json_decode(json_encode($cat), true) === $cat);
$cats = array_keys($reg->byCategory()); t('Katalog nach Kategorien geordnet', $cats === array_values(array_intersect(array_keys(Component::CATEGORIES), $cats)));
$reg->register($ok, 'plugin-x', fn($p) => '<b>' . htmlspecialchars((string)$p['title']) . '</b>');
t('Erweiterung registriert', $reg->has('demo_box') && $reg->get('demo_box')->source === 'plugin-x');
t('Erweiterung darf Kern nicht überschreiben', throws(fn() => $reg->register(['id' => 'hero', 'name' => 'x', 'category' => 'content', 'fields' => []], 'plugin-x', fn() => ''), InvalidArgumentException::class) && $reg->get('hero')->source === 'core');
$reg->unregister('demo_box'); t('Abmelden', !$reg->has('demo_box'));
$reg->register($ok, 'plugin-x', fn($p) => '<b>' . htmlspecialchars((string)$p['title']) . '</b>');
$leg = $reg->legacySchema(['hero', 'text', 'features', 'nix']);
t('Baukasten-Schema (ältere Form)', array_keys($leg) === ['hero', 'text', 'features'] && $leg['text']['fields'][1]['type'] === 'textarea' && $leg['features']['fields'][1]['max'] === 6 && $leg['hero']['icon'] === 'fa-image' && $leg['hero']['fields'][7]['max'] === 900);

// ───────── 4) Layout: Bereinigung und Regeln ─────────
$lay = new Layout($reg); $ctxA = ['admin' => true]; $ctxU = ['admin' => false];
$out = $lay->clean([
    ['type' => 'footer', 'props' => ['text' => '<p>F</p>']], ['type' => 'text', 'id' => 'a', 'props' => ['title' => 'T']], ['type' => 'header', 'props' => ['title' => 'S']],
    ['type' => 'text', 'id' => 'a'], ['type' => 'zukunft', 'props' => ['x' => 1]], 'quatsch', ['type' => 'header'], ['id' => 'ohne-typ'],
], $ctxA);
t('Feste Plätze: Header zuerst, Footer zuletzt; nur ein Header', array_column($out, 'type') === ['header', 'text', 'text', 'zukunft', 'footer'] || array_column($out, 'type') === ['header', 'text', 'zukunft', 'text', 'footer'], json_encode(array_column($out, 'type')));
$issues = array_column($lay->issues, 'issue', 'type');
t('Meldungen: unbekannt bleibt, zweiter Header nicht wiederholbar', ($issues['zukunft'] ?? '') === 'missing' && ($issues['header'] ?? '') === 'not_repeatable', json_encode($lay->issues));
$ids = array_column($out, 'id'); t('IDs eindeutig', count($ids) === count(array_unique($ids)) && in_array('a', $ids, true));
$miss = array_values(array_filter($out, fn($s) => !empty($s['missing'])))[0] ?? null;
t('Unbekannte Komponente bleibt als „missing“ mit ihren Werten', $miss && $miss['props'] === ['x' => 1] && $miss['type'] === 'zukunft');
t('Header/Footer fest verankert (locked)', array_values(array_filter($out, fn($s) => $s['type'] === 'header'))[0]['locked'] === true);
$u = $lay->clean([['type' => 'header'], ['type' => 'wp_shortcode', 'props' => ['shortcode' => '[x]']], ['type' => 'text', 'props' => ['title' => 'ok']]], $ctxU);
t('Ohne Admin-Recht: Header/Shortcode abgelehnt', array_column($u, 'type') === ['text'] && count(array_filter($lay->issues, fn($i) => $i['issue'] === 'forbidden')) === 2);
// Verschachtelung
$nest = $lay->clean([['type' => 'columns', 'children' => [['type' => 'button', 'props' => ['label' => 'A']], ['type' => 'header'], ['type' => 'columns', 'children' => [['type' => 'text']]], ['type' => 'text']]],
    ['type' => 'text', 'children' => [['type' => 'button']]]], $ctxA);
t('Spalten nehmen erlaubte Kinder, lehnen Header ab, Text ohne Kinder', count($nest[0]['children']) >= 2 && !in_array('header', array_column($nest[0]['children'], 'type'), true) && !isset($nest[1]['children']), json_encode($nest));
$deep = ['type' => 'container', 'children' => []]; $cur = &$deep['children'];
for ($i = 0; $i < 6; $i++) { $cur[] = ['type' => 'container', 'children' => []]; $cur = &$cur[0]['children']; } unset($cur);
$d = $lay->clean([$deep], $ctxA); $depth = 0; $p = $d[0]; while (!empty($p['children'])) { $p = $p['children'][0]; $depth++; }
t('Tiefe begrenzt', $depth <= Layout::MAX_DEPTH, (string)$depth);
t('Gesamtzahl begrenzt', count($lay->clean(array_fill(0, 300, ['type' => 'text']), $ctxA)) === Layout::MAX_TOTAL);
t('Eingabe kein Array', $lay->clean('x', $ctxA) === [] && $lay->clean(null, $ctxA) === []);
// Werte, responsive, Sichtbarkeit
$one = $lay->clean([['type' => 'columns', 'props' => ['columns' => 99, 'gap' => '12'], 'responsive' => ['tablet' => ['columns' => 3, 'unbekannt' => 1], 'mobile' => ['columns' => 0, 'gap' => 'x'], 'desktop' => ['columns' => 9]],
    'visibility' => ['devices' => ['mobile', 'tablet', 'fax'], 'audience' => 'members', 'from' => '2030-01-02T08:00', 'until' => 'kaputt']]], $ctxA)[0];
t('Werte bereinigt (Grenzen)', $one['props']['columns'] === 4 && $one['props']['gap'] === 12);
t('Responsive: nur erlaubte Felder und Geräte', $one['responsive'] === ['tablet' => ['columns' => 3], 'mobile' => ['columns' => 1, 'gap' => 0]], json_encode($one['responsive']));
t('Sichtbarkeit bereinigt', $one['visibility'] === ['devices' => ['tablet', 'mobile'], 'audience' => 'members', 'from' => '2030-01-02 08:00', 'until' => ''], json_encode($one['visibility']));
t('Responsive: nur bei Feldern mit „responsive“ (Container-Farbe nicht)', $lay->clean([['type' => 'container', 'responsive' => ['mobile' => ['bg' => 'dark', 'padding' => 5]]]], $ctxA)[0]['responsive'] === ['mobile' => ['padding' => 5]]);
t('Sichtbarkeit Vorgabe', Layout::visibility(null) === ['devices' => ['desktop', 'tablet', 'mobile'], 'audience' => 'all', 'from' => '', 'until' => '']);
$base = ['type' => 'text', 'id' => 'x'];
$now = strtotime('2030-06-01 12:00:00');
t('visible: ausgeblendet/missing', !Layout::visible($base + ['hidden' => true]) && !Layout::visible($base + ['missing' => true]) && Layout::visible($base));
t('visible: Zeitfenster', !Layout::visible($base + ['visibility' => ['from' => '2030-06-02 00:00']], ['now' => $now]) && Layout::visible($base + ['visibility' => ['from' => '2030-06-01 12:00']], ['now' => $now]) && !Layout::visible($base + ['visibility' => ['until' => '2030-06-01 12:00']], ['now' => $now]) && Layout::visible($base + ['visibility' => ['until' => '2030-06-01 12:01']], ['now' => $now]));
t('visible: Zielgruppe', Layout::visible($base + ['visibility' => ['audience' => 'members']], ['member' => true]) && !Layout::visible($base + ['visibility' => ['audience' => 'members']], ['member' => false]) && Layout::visible($base + ['visibility' => ['audience' => 'guests']]) && !Layout::visible($base + ['visibility' => ['audience' => 'guests']], ['member' => true]));
t('visible: kein Gerät = unsichtbar; Geräteklassen', !Layout::visible($base + ['visibility' => ['devices' => []]]) && Layout::hideClasses($base + ['visibility' => ['devices' => ['desktop']]]) === ['ep-hide-tablet', 'ep-hide-mobile'] && Layout::hideClasses($base) === []);
$inst = ['props' => ['a' => 1, 'b' => 2, 'c' => 3], 'responsive' => ['tablet' => ['a' => 10, 'b' => 20], 'mobile' => ['a' => 100]]];
t('Werte je Gerät (Mobil erbt von Tablet)', Layout::resolve($inst, 'desktop') === ['a' => 1, 'b' => 2, 'c' => 3] && Layout::resolve($inst, 'tablet') === ['a' => 10, 'b' => 20, 'c' => 3] && Layout::resolve($inst, 'mobile') === ['a' => 100, 'b' => 20, 'c' => 3]);
$reg->register(['id' => 'limit_box', 'name' => 'L', 'category' => 'content', 'rules' => ['max_instances' => 2], 'fields' => [['k' => 'a', 'type' => 'text']]], 'plugin-x', fn() => 'x');
t('Höchstzahl je Ebene', count($lay->clean(array_fill(0, 5, ['type' => 'limit_box']), $ctxA)) === 2 && $lay->issues[0]['issue'] === 'too_many');
$reg->register(['id' => 'cell_only', 'name' => 'Z', 'category' => 'content', 'rules' => ['parents' => ['columns']], 'fields' => [['k' => 'a', 'type' => 'text']]], 'plugin-x', fn() => 'z');
$lc = $lay->clean([['type' => 'cell_only'], ['type' => 'columns', 'children' => [['type' => 'cell_only']]]], $ctxA);
t('Nur in bestimmtem Elternelement erlaubt', count($lc) === 1 && count($lc[0]['children']) === 1 && $lay->issues[0]['issue'] === 'needs_parent');

// ───────── 5) Renderer ─────────
$rd = new Renderer($reg);
$lo = $lay->clean([
    ['type' => 'header', 'props' => ['title' => 'Mein & <i>Radio</i>', 'items' => [['label' => 'Start', 'url' => '/'], ['label' => 'X"onmouseover="a', 'url' => 'javascript:evil()']], 'sticky' => true, 'bg' => '#112233', 'height' => 80], 'responsive' => ['mobile' => ['height' => 50]]],
    ['type' => 'columns', 'props' => ['columns' => 3, 'gap' => 10], 'responsive' => ['tablet' => ['columns' => 2], 'mobile' => ['columns' => 1]], 'visibility' => ['devices' => ['desktop', 'tablet']], 'children' => [
        ['type' => 'image', 'props' => ['image' => 'https://a.de/a.png', 'alt' => 'A"B', 'caption' => '<i>C</i>', 'link' => '/ziel', 'width' => 50]],
        ['type' => 'video', 'props' => ['src' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title' => 'V']],
        ['type' => 'video', 'props' => ['src' => 'https://evil.de/x.php']], ['type' => 'audio', 'props' => ['src' => 'https://a.de/a.mp3', 'title' => 'Ton']],
        ['type' => 'button', 'props' => ['label' => '<b>Los</b>', 'url' => 'javascript:x', 'style' => 'outline']], ['type' => 'gallery', 'props' => ['items' => [['image' => 'https://a.de/1.png', 'alt' => 'eins'], ['image' => 'javascript:x']], 'columns' => 2]],
    ]],
    ['type' => 'hero', 'props' => ['title' => 'Theme-Komponente']], ['type' => 'form', 'props' => ['form_id' => 'kontakt']], ['type' => 'text', 'hidden' => true], ['type' => 'demo_box', 'props' => ['title' => '<script>x</script>']],
    ['type' => 'zukunft', 'props' => ['x' => 1]], ['type' => 'footer', 'props' => ['text' => '<p>F<script>x</script></p>', 'items' => [['label' => 'Impressum', 'url' => '/impressum']]]],
], $ctxA);
$res = $rd->render($lo); $html = $res['html']; $css = $res['css'];
t('Render: Reihenfolge Header … Footer', strpos($html, 'ep-c-header') < strpos($html, 'ep-c-columns') && strpos($html, 'ep-c-columns') < strpos($html, 'ep-c-footer'));
t('Render: Texte maskiert, gefährliche Links entfernt', str_contains($html, 'Mein &amp; Radio') && !str_contains($html, 'javascript:') && !str_contains($html, 'onmouseover="a') && str_contains($html, 'X&quot;onmouseover=&quot;a'));
t('Render: Bild/Video/Audio/Button/Galerie', str_contains($html, 'alt="A&quot;B"') && str_contains($html, '<figcaption>C</figcaption>') && str_contains($html, 'youtube-nocookie.com/embed/dQw4w9WgXcQ') && !str_contains($html, 'evil.de') && str_contains($html, '<audio controls') && str_contains($html, 'ep-btn-outline') && substr_count($html, '<figure><img') === 1);
t('Render: Skripte aus HTML/Erweiterung entfernt', !str_contains($html, '<script') && !str_contains($html, 'alert') && str_contains($html, '<b>x</b>'));
t('Render: ausgeblendete, fremd gerenderte und fehlende Komponenten fehlen', !str_contains($html, 'Theme-Komponente') && !str_contains($html, 'ep-c-form') && !str_contains($html, 'ep-c-text') && !str_contains($html, 'zukunft'));
t('Render: Klassen und Erkennungsdaten', preg_match('/class="ep-c ep-c-columns ep-hide-mobile" data-ep-id="[a-z0-9_-]+" data-ep-type="columns"/', $html) === 1);
$ph = $rd->render($lo, ['placeholders' => true])['html'];
t('Platzhalter für Nicht-Native auf Wunsch', str_contains($ph, '<!-- ep:hero (theme) -->') && str_contains($ph, '<!-- ep:form (runtime) -->') && str_contains($ph, '<!-- ep:zukunft') === false);
$host = $rd->render($lo, ['renderers' => ['hero' => fn($p) => '<h1>' . htmlspecialchars($p['title']) . '</h1>']])['html'];
t('Host rendert Nicht-Native', str_contains($host, '<h1>Theme-Komponente</h1>'));
t('CSS: Grundwerte', str_contains($css, 'background-color:#112233;') && str_contains($css, 'min-height:80px;') && str_contains($css, 'repeat(3,minmax(0,1fr))') && str_contains($css, 'gap:10px;') && str_contains($css, 'max-width:50%;'));
t('CSS: Tablet/Mobil in @media', str_contains($css, '@media (max-width:1024px){') && str_contains($css, 'repeat(2,minmax(0,1fr))') && str_contains($css, '@media (max-width:640px){') && str_contains($css, 'min-height:50px;') && str_contains($css, 'repeat(1,minmax(0,1fr))'));
t('CSS: Geräte-Sichtbarkeit', str_contains($css, '.ep-hide-mobile{display:none!important}') && str_contains($css, '.ep-hide-desktop{display:none!important}'));
$ho = $reg->get('header'); $evil = ['id' => 'x"]{}', 'props' => ['bg' => 'red;background:url(x)', 'height' => 0], 'responsive' => []];
t('CSS: nichts Unsicheres, Null bei „skip_zero“ weglassen', !str_contains($rd->css($ho, $evil), 'url(') && !str_contains($rd->css($ho, $evil), 'min-height') && str_contains($rd->css($ho, ['id' => 'ab', 'props' => [], 'responsive' => []]), '') );
t('CSS: Attributname nur aus Liste', str_contains($rd->css($reg->get('hero'), ['id' => 'q', 'props' => ['height' => 400]], 'data-bk'), '[data-bk="q"]{min-height:400px;}') && str_contains($rd->css($reg->get('hero'), ['id' => 'q', 'props' => ['height' => 400]], 'onclick'), '[data-ep-id="q"]'));

// ───────── 6) Layout-Speicher ─────────
$store = new LayoutStore("$tmp/layouts", $reg);
$adm = new Actor('chef', 'admin'); $aut = new Actor('schreiber', 'autor');
t('Bereiche', LayoutStore::validScope('home') && LayoutStore::validScope('site:footer') && LayoutStore::validScope('page:ueber-uns') && LayoutStore::validScope('post:12') && !LayoutStore::validScope('../x') && !LayoutStore::validScope('page:') && !LayoutStore::validScope('post:abc') && !LayoutStore::validScope('site:A') && !LayoutStore::validScope('home/x'));
t('Ungültiger Bereich', throws(fn() => $store->get('../etc'), InvalidArgumentException::class));
t('Leer am Anfang', $store->get('home')['published'] === null && $store->published('home') === null && $store->revisions('home') === []);
$r1 = $store->saveDraft('home', [['type' => 'text', 'props' => ['title' => 'Eins']]], $adm);
t('Entwurf gespeichert und bereinigt', count($r1['layout']) === 1 && $store->get('home')['draft']['by'] === 'chef' && $store->published('home') === null);
t('Nichts ohne Entwurf zu veröffentlichen', throws(fn() => $store->publish('page:neu', $adm), RuntimeException::class));
$p1 = $store->publish('home', $adm, 'Start');
t('Veröffentlicht: Fassung 1, Entwurf weg', $p1['n'] === 1 && $store->published('home')[0]['props']['title'] === 'Eins' && $store->get('home')['draft'] === null);
$store->saveDraft('home', [['type' => 'text', 'props' => ['title' => 'Zwei']]], $adm); $p2 = $store->publish('home', $adm);
$store->saveDraft('home', [['type' => 'text', 'props' => ['title' => 'Drei']]], $adm); $p3 = $store->publish('home', $adm, 'Neu');
$rev = $store->revisions('home');
t('Revisionen: neueste zuerst, mit Beschriftung', array_column($rev, 'n') === [3, 2, 1] && $rev[0]['label'] === 'Neu' && $rev[2]['label'] === 'Start' && $rev[0]['by'] === 'chef');
$store->saveDraft('home', [['type' => 'text', 'props' => ['title' => 'Entwurf']]], $adm); $store->discard('home', $adm);
t('Verwerfen: Entwurf weg, Veröffentlichtes bleibt', $store->get('home')['draft'] === null && $store->published('home')[0]['props']['title'] === 'Drei');
$rb = $store->rollback('home', 1, $adm);
t('Rollback: alte Fassung wird neue Fassung 4, nichts geht verloren', $rb['n'] === 4 && $store->published('home')[0]['props']['title'] === 'Eins' && array_column($store->revisions('home'), 'n') === [4, 3, 2, 1] && $store->revisions('home')[0]['label'] === 'Zurück auf Fassung 1');
t('Rollback auf fehlende Fassung', throws(fn() => $store->rollback('home', 99, $adm), RuntimeException::class));
$future = date('Y-m-d H:i', time() + 3600);
$store->saveDraft('home', [['type' => 'text', 'props' => ['title' => 'Geplant']]], $adm, $future);
t('Geplant: noch nicht veröffentlicht', $store->get('home')['draft']['publish_at'] === $future && $store->published('home')[0]['props']['title'] === 'Eins');
$due = $store->get('home', time() + 7200);
t('Termin erreicht: wird beim Lesen veröffentlicht', $due['published']['props'] ?? true ? $due['published']['layout'][0]['props']['title'] === 'Geplant' && $due['published']['label'] === 'zeitgesteuert' && $due['draft'] === null && $due['published']['n'] === 5 : false, json_encode($due['published'] ?? null));
t('Ungültiger Termin', throws(fn() => $store->saveDraft('home', [], $adm, 'morgen'), InvalidArgumentException::class));
for ($i = 0; $i < LayoutStore::MAX_REVISIONS + 5; $i++) { $store->saveDraft('page:x', [['type' => 'text', 'props' => ['title' => "V$i"]]], $adm); $store->publish('page:x', $adm); }
t('Revisionen begrenzt', count($store->revisions('page:x')) === LayoutStore::MAX_REVISIONS + 1);
t('Datei nicht öffentlich lesbar', is_file("$tmp/layouts/.htaccess") && str_contains((string)file_get_contents("$tmp/layouts/.htaccess"), 'Require all denied') && is_file("$tmp/layouts/home.json") && is_file("$tmp/layouts/page__x.json"));
$bigList = array_fill(0, 120, ['type' => 'html', 'props' => ['code' => '<p>' . str_repeat('x', 19900) . '</p>']]);
t('Zu großes Layout abgelehnt', throws(fn() => $store->saveDraft('page:gross', $bigList, $adm), RuntimeException::class) && $store->get('page:gross')['draft'] === null);
// Rechte
t('Autor: nicht auf home/site, aber auf page/post', throws(fn() => $store->saveDraft('home', [], $aut), PermissionException::class) && throws(fn() => $store->publish('site:header', $aut), PermissionException::class) && throws(fn() => $store->discard('home', $aut), PermissionException::class) && throws(fn() => $store->rollback('home', 1, $aut), PermissionException::class));
$store->saveDraft('page:mix', [['type' => 'header', 'props' => ['title' => 'Kopf']], ['type' => 'text', 'id' => 'tx', 'props' => ['title' => 'A']], ['type' => 'wp_shortcode', 'id' => 'sc', 'props' => ['shortcode' => '[a]']]], $adm); $store->publish('page:mix', $adm);
$store->saveDraft('page:mix', [['type' => 'header', 'props' => ['title' => 'Kopf']], ['type' => 'text', 'id' => 'tx', 'props' => ['title' => 'A']], ['type' => 'wp_shortcode', 'id' => 'sc', 'props' => ['shortcode' => '[a]']]], $adm); $store->publish('page:mix', $adm);
$ra = $store->saveDraft('page:mix', [['type' => 'text', 'id' => 'tx', 'props' => ['title' => 'Vom Autor', 'body' => '<script>x</script><p>ok</p>']], ['type' => 'button', 'props' => ['label' => 'Neu']], ['type' => 'wp_shortcode', 'id' => 'neu', 'props' => ['shortcode' => '[evil]']]], $aut);
$types = array_column($ra['layout'], 'type'); $byId = array_column($ra['layout'], null, 'id');
t('Autor ändert eigene Teile, neue Admin-Komponente abgelehnt', in_array('button', $types, true) && ($byId['tx']['props']['title'] ?? '') === 'Vom Autor' && !isset($byId['neu']), json_encode($types));
t('Autor: Header und Admin-Komponenten bleiben erhalten (geschützt)', $types[0] === 'header' && isset($byId['sc']) && $byId['sc']['props']['shortcode'] === '[a]', json_encode($types));
t('Autor: HTML wird gefiltert', !str_contains(json_encode($ra['layout']), '<script'));
$rb2 = $store->saveDraft('page:mix', [], $aut);
t('Autor kann Geschütztes nicht löschen (leeres Layout behält Header und Shortcode)', array_column($rb2['layout'], 'type') === ['header', 'wp_shortcode'] || in_array('header', array_column($rb2['layout'], 'type'), true) && in_array('wp_shortcode', array_column($rb2['layout'], 'type'), true), json_encode(array_column($rb2['layout'], 'type')));
$raw = '<!-- ep:html --><b onclick="x()">roh</b><!-- /ep:html -->';
$ru = $store->saveDraft('page:roh', [['type' => 'html', 'props' => ['code' => $raw]]], $aut); $rv = $store->saveDraft('home', [['type' => 'html', 'props' => ['code' => $raw]]], $adm);
t('Roh-HTML-Block nur für Administratoren unverändert', str_contains(json_encode($rv['layout']), 'onclick') && !str_contains(json_encode($ru['layout']), 'onclick'));

// ───────── 7) Erweiterung über Hook-Funktion ─────────
$reg2 = new Registry(); CoreComponents::register($reg2);
$ext = function (Registry $r): void {   // so registriert ein Plugin (Hook „components_register“) eigene Komponenten
    $r->register(['id' => 'partner_wall', 'name' => 'Partner', 'category' => 'extension', 'fields' => [['k' => 'items', 'type' => 'items', 'max_items' => 4, 'item' => [['k' => 'name', 'type' => 'text'], ['k' => 'logo', 'type' => 'image']]]]], 'mein-plugin',
        fn($p) => '<ul>' . implode('', array_map(fn($i) => '<li>' . htmlspecialchars($i['name']) . '</li>', $p['items'])) . '</ul>');
};
t('Kern kennt keine Partner-Komponente', !$reg2->has('partner_wall'));
$ext($reg2);
$o2 = (new Renderer($reg2))->render((new Layout($reg2))->clean([['type' => 'partner_wall', 'props' => ['items' => [['name' => 'A&B', 'logo' => 'https://x/l.png']]]]], ['admin' => true]))['html'];
t('Komponente einer Erweiterung wird gerendert', str_contains($o2, '<li>A&amp;B</li>') && str_contains($o2, 'ep-c-partner_wall') && $reg2->get('partner_wall')->source === 'mein-plugin');
$lay3 = new Layout($reg2); $saved = $lay3->clean([['type' => 'partner_wall', 'props' => ['items' => [['name' => 'X']]]]], ['admin' => true]);
$lay4 = new Layout($reg);   // Registry ohne die Erweiterung (Plugin abgeschaltet)
$again = $lay4->clean($saved, ['admin' => true]);
t('Plugin abgeschaltet: Werte bleiben erhalten (missing), nichts wird ausgegeben', ($again[0]['missing'] ?? false) === true && $again[0]['props']['items'][0]['name'] === 'X' && (new Renderer($reg))->render($again)['html'] === '');
t('…und kommen mit dem Plugin zurück', (new Layout($reg2))->clean($again, ['admin' => true])[0]['props']['items'][0]['name'] === 'X' && empty((new Layout($reg2))->clean($again, ['admin' => true])[0]['missing']));

// ───────── 8) Vorschau-Brücke (statische Sicherheitsprüfung des Skripts; Verhalten im Browser geprüft) ─────────
$pb = (string)file_get_contents(__DIR__ . '/../cms/assets/preview-bridge.js');
$lbJs = (string)file_get_contents(__DIR__ . '/../cms/assets/live-builder.js');
t('Brücke: nur Eltern-Fenster und gleiche Herkunft, Schlüssel für Befehle', str_contains($pb, 'e.source!==PARENT||e.origin!==ORIGIN') && str_contains($pb, 'd.token!==token') && str_contains($pb, 'PARENT.postMessage(m,ORIGIN)') && !str_contains($pb, "postMessage(m,'*')") && !str_contains($pb, 'eval('));
t('Builder: nur der Vorschau-Frame, gleiche Herkunft, Schlüssel; kein „*“ als Ziel', str_contains($lbJs, 'e.source!==f.contentWindow||e.origin!==location.origin') && str_contains($lbJs, 'd.token!==bridge.token') && !str_contains($lbJs, ",'*')") && str_contains($lbJs, 'getRandomValues'));
$wf = (string)file_get_contents(__DIR__ . '/../cms/wp-front.php');
t('Brücke wird nur mit gültigem Vorschau-Schlüssel eingebunden', preg_match('/elvado_wp_preview_theme.*preview-bridge\.js/s', $wf) === 1 && substr_count($wf, 'preview-bridge.js') === 1);
// Änderungsprotokoll je Fassung (Live Builder dokumentiert jede Änderung)
$store->saveDraft('home', [['type' => 'text', 'props' => ['title' => 'Vier']]], $adm);
$p4 = $store->publish('home', $adm, 'Titel', ['Text – Titel: «Drei» → «Vier»', '<b>Fett</b> Zeile', '', str_repeat('x', 300)]);
t('Fassung speichert das Änderungsprotokoll (bereinigt, gekürzt)', ($p4['changes'][1] ?? '') === 'Fett Zeile' && count($p4['changes']) === 3 && mb_strlen($p4['changes'][2]) === 160 && ($p4['changes'][0] ?? '') === 'Text – Titel: «Drei» → «Vier»');
t('Verlauf liefert das Protokoll; ältere Fassungen ohne Protokoll bleiben gültig', ($store->revisions('home')[0]['changes'] ?? null) === $p4['changes'] && ($store->revisions('home')[1]['changes'] ?? null) === []);
$store->saveDraft('home', [['type' => 'text', 'props' => ['title' => 'Fünf']]], $adm); $store->publish('home', $adm, 'x', array_fill(0, 80, 'Zeile'));
t('Höchstens 40 Protokollzeilen je Fassung', count($store->revisions('home')[0]['changes']) === 40);
$apiSrc = (string)file_get_contents(__DIR__ . '/../cms/api.php'); $lbSrc = (string)file_get_contents(__DIR__ . '/../cms/assets/live-builder.js');
t('Protokoll: Veröffentlichen (Startseite, Bereiche) und Customizer schreiben die Änderungen ins Aktivitätslog', str_contains($apiSrc, "(array)(\$b['changes']??[])") && str_contains($apiSrc, "'Customizer „'") && str_contains((string)file_get_contents(__DIR__ . '/../cms/components-api.php'), "implode('; ', array_slice(\$p['changes']"));
t('Live Builder: Rückgängig/Wiederholen (Strg+Z/Y), Änderungsliste und Protokoll im Verlauf', str_contains($lbSrc, 'function histGo') && str_contains($lbSrc, 'function describe') && str_contains($lbSrc, "kind==='changes'") && str_contains($lbSrc, 'lb-chg') && str_contains((string)file_get_contents(__DIR__ . '/../cms/views/panel-livebuilder.php'), 'id="lbUndo"'));
t('Live Builder: Paket-Bereich ist das Standardziel; Änderungsliste vergleicht ohne Veröffentlichung mit den Vorgaben', str_contains($lbSrc, 'ist dessen Bereich das Standardziel') && str_contains($lbSrc, 'Vergleich mit den Vorgaben der Website'));
if (getenv("DBG")) { echo $html, "\n", $css, "\n"; }
echo $fail ? "$fail von $n Prüfungen fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n";
exit($fail ? 1 : 0);

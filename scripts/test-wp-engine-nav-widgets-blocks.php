<?php
// Prüft Menüs, Widgets und Blöcke der WordPress-Engine: Dienste (Eingabeprüfung, Rechte), Native-Adapter, Block-Converter (ElvadoPress ↔ WordPress) und – mit WPE_TEST_ZIP/WPE_TEST_DB –
// echte WordPress-Menüs, -Widgets und -Blöcke. Aufruf: php scripts/test-wp-engine-nav-widgets-blocks.php
declare(strict_types=1);
require __DIR__ . '/../cms/src/autoload.php';
require __DIR__ . '/../cms/lib/htmlsafe.php';
use Elvado\Blocks\Converter;
use Elvado\Wp\{Actor, Engine, NavigationService, WidgetService, WidgetSchemas, BlockService, CoreInstaller, DbConfig, Bridge, PermissionException};
use Elvado\Wp\Adapter\{NavigationAdapter, WidgetAdapter, NativeNavigationAdapter, NativeWidgetAdapter, WordPressNavigationAdapter, WordPressWidgetAdapter};

$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
function rmrf(string $d): void { if (!is_dir($d)) return; foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') continue; $p = "$d/$f"; is_dir($p) && !is_link($p) ? rmrf($p) : @unlink($p); } @rmdir($d); }
function throws(callable $f, string $cls = \Throwable::class): bool { try { $f(); } catch (\Throwable $e) { return $e instanceof $cls; } return false; }

// ───────── Kindprozess: echtes WordPress ─────────
if (($argv[1] ?? '') === '--child') {
    $tmp = (string)$argv[2];
    $GLOBALS['elvado_wpe_engine'] = new Engine("$tmp/cms", "$tmp/cms/data");
    $GLOBALS['elvado_wpe_db'] = new DbConfig($GLOBALS['elvado_wpe_engine']);
    $GLOBALS['elvado_wpe_opts'] = ['installing' => true];
    $_SERVER['HTTP_HOST'] = 'example.test';
    require __DIR__ . '/../cms/wp-engine-boot.php';
    $r = Bridge::installSchema('Test', 'test@example.invalid'); t('WordPress eingerichtet', $r['ok'], $r['message'] ?? '');
    wp_widgets_init();   // im Installationsmodus startet WordPress die Widgets nicht von selbst
    $adm = new Actor('chef', 'admin'); $aut = new Actor('schreiber', 'autor');
    register_sidebar(['id' => 'seite', 'name' => 'Seitenleiste', 'description' => 'Rechts']); register_sidebar(['id' => 'fuss', 'name' => 'Fußbereich']);
    register_nav_menu('haupt', 'Hauptmenü'); register_nav_menu('fuss', 'Fußmenü');
    $pg = wp_insert_post(['post_type' => 'page', 'post_title' => 'Über uns', 'post_status' => 'publish']); $po = wp_insert_post(['post_type' => 'post', 'post_title' => 'Ein Beitrag', 'post_status' => 'publish']);
    $cat = wp_insert_term('Radio', 'category')['term_id'];

    // Menüs
    $nv = new NavigationService(new WordPressNavigationAdapter(), $adm);
    $m = $nv->create('Hauptmenü');
    t('Menü angelegt', ctype_digit($m['id']) && $m['name'] === 'Hauptmenü' && count($nv->menus()) === 1);
    $items = [
        ['type' => 'page', 'object_id' => (string)$pg, 'label' => '', 'children' => [['type' => 'custom', 'label' => 'Impressum', 'url' => '/impressum', 'target' => '_blank', 'rel' => 'noopener nofollow<x>', 'classes' => 'a b!']]],
        ['type' => 'category', 'object_id' => (string)$cat, 'label' => 'Radio-News'], ['type' => 'post', 'object_id' => (string)$po, 'label' => 'Lesen'], ['type' => 'custom', 'label' => 'Extern', 'url' => 'https://example.org/x'],
    ];
    $saved = $nv->save($m['id'], $items);
    t('Baum gespeichert, Reihenfolge und Untermenü', count($saved) === 4 && $saved[0]['label'] === 'Über uns' && $saved[0]['type'] === 'page' && count($saved[0]['children']) === 1 && $saved[0]['children'][0]['label'] === 'Impressum' && $saved[1]['type'] === 'category' && $saved[1]['label'] === 'Radio-News' && $saved[3]['url'] === 'https://example.org/x', json_encode($saved));
    t('Einstellungen des Eintrags (Ziel, rel, Klassen bereinigt)', $saved[0]['children'][0]['target'] === '_blank' && $saved[0]['children'][0]['rel'] === 'noopener nofollow' && $saved[0]['children'][0]['classes'] === 'a b' && $saved[0]['children'][0]['url'] === '/impressum', json_encode($saved[0]['children'][0]));
    t('Verlinkte Seite: Adresse liefert WordPress', str_contains($saved[0]['url'], 'page_id=' . $pg) && str_contains($saved[2]['url'], 'p=' . $po) && $saved[2]['object_id'] === (string)$po, $saved[0]['url'] . ' | ' . $saved[2]['url']);
    // Umbauen: verschieben, neu, entfernen
    $reorder = [$saved[3], ['id' => $saved[0]['id'], 'type' => 'page', 'object_id' => (string)$pg, 'label' => 'Wir', 'children' => [['id' => $saved[2]['id'], 'type' => 'post', 'object_id' => (string)$po, 'label' => 'Lesen']]]];
    $s2 = $nv->save($m['id'], $reorder);
    t('Umbau: Eintrag verschoben (ID bleibt), umbenannt, andere entfernt', count($s2) === 2 && $s2[0]['label'] === 'Extern' && $s2[1]['id'] === $saved[0]['id'] && $s2[1]['label'] === 'Wir' && $s2[1]['children'][0]['id'] === $saved[2]['id'] && count(wp_get_nav_menu_items((int)$m['id'])) === 3, json_encode($s2));
    t('Entfernte Einträge sind wirklich gelöscht', get_post((int)$saved[1]['id']) === null && get_post((int)$saved[0]['children'][0]['id']) === null);
    t('Fremde Eintrags-ID wird als neu behandelt (nichts anderer Menüs wird verändert)', (function () use ($nv, $m, $adm) { $m2 = $nv->create('Zweit'); $other = $nv->save($m2['id'], [['type' => 'custom', 'label' => 'Fremd', 'url' => '/f']]); $x = $nv->save($m['id'], [['id' => $other[0]['id'], 'type' => 'custom', 'label' => 'Gekapert', 'url' => '/g']]); return $nv->tree($m2['id'])['items'][0]['label'] === 'Fremd' && $x[0]['id'] !== $other[0]['id']; })());
    t('Nicht vorhandene Seite wird abgelehnt', throws(fn() => $nv->save($m['id'], [['type' => 'page', 'object_id' => '999999']]), RuntimeException::class));
    t('Typ passt nicht (Beitrag als Seite)', throws(fn() => $nv->save($m['id'], [['type' => 'page', 'object_id' => (string)$po]]), RuntimeException::class));
    t('Orte des Themes und Zuweisung', array_column($nv->locations(), 'menu', 'id') === ['haupt' => '', 'fuss' => ''] && ($nv->assign('haupt', $m['id']) ?? true) && array_column($nv->locations(), 'menu', 'id')['haupt'] === $m['id'] && in_array('haupt', array_column($nv->menus(), 'locations', 'id')[$m['id']], true));
    t('Ort wieder lösen, unbekannter Ort', ($nv->assign('haupt', '') ?? true) && array_column($nv->locations(), 'menu', 'id')['haupt'] === '' && throws(fn() => $nv->assign('nirgends', $m['id']), RuntimeException::class));
    $nv->rename($m['id'], 'Haupt'); t('Umbenennen', $nv->tree($m['id'])['name'] === 'Haupt');
    t('Autor darf Menüs nicht ändern', throws(fn() => (new NavigationService(new WordPressNavigationAdapter(), $aut))->save($m['id'], []), PermissionException::class) && count((new NavigationService(new WordPressNavigationAdapter(), $aut))->menus()) === 2);
    // Übernahme aus ElvadoPress
    $natDir = "$tmp/nat"; @mkdir($natDir, 0755, true);
    file_put_contents("$natDir/site.json", json_encode(['menus' => ['top' => [['id' => 'a', 'label' => 'Start', 'target' => '/'], ['id' => 'b', 'label' => 'Radio', 'target' => '/radio', 'parent_id' => 'a'], ['id' => 'c', 'label' => 'Aus', 'target' => '/x', 'enabled' => false]], 'bottom' => []]]));
    $imp = $nv->importFrom(new NativeNavigationAdapter($natDir), 'top', 'Aus ElvadoPress');
    $it = $nv->tree($imp['menu']['id'])['items'];
    t('Übernahme: Struktur und Namen, deaktivierte Einträge nicht', $imp['items'] === 2 && count($it) === 1 && $it[0]['label'] === 'Start' && $it[0]['children'][0]['label'] === 'Radio' && $it[0]['children'][0]['type'] === 'custom');
    t('Menü löschen', $nv->delete($m['id']) && $nv->tree($m['id']) === null && !$nv->delete($m['id']));

    // Widgets
    $wd = new WidgetService(new WordPressWidgetAdapter(), $adm);
    $ov = $wd->overview();
    $types = array_column($ov['types'], null, 'id_base');
    t('Widget-Typen aus WordPress samt Schema', isset($types['text'], $types['search'], $types['recent-posts'], $types['nav_menu']) && $types['text']['schema'][0]['k'] === 'title' && $types['text']['name'] !== '');
    t('Bereiche des Themes plus Ablage', array_column($ov['areas'], 'id') === ['seite', 'fuss', 'wp_inactive_widgets'] && $ov['areas'][2]['inactive'] === true);
    $w1 = $wd->add('seite', 'text', ['title' => 'Hallo <b>Welt</b>', 'text' => '<p>Inhalt <script>x()</script></p>', 'filter' => true, 'unbekannt' => 'x']);
    t('Widget angelegt (Titel bereinigt, unbekannte Felder verworfen)', preg_match('/^text-\d+$/', $w1['id']) === 1 && $w1['title'] === 'Hallo Welt' && !isset($w1['settings']['unbekannt']), json_encode($w1));
    $w2 = $wd->add('seite', 'recent-posts', ['title' => 'Neu', 'number' => 99, 'show_date' => true]);
    t('Zahlen begrenzt, Häkchen 0/1', $w2['settings']['number'] === 20 && !empty($w2['settings']['show_date']));
    $w3 = $wd->add('fuss', 'search', ['title' => 'Suche']);
    $ar = array_column($wd->overview()['areas'], 'widgets', 'id');
    t('Widgets stehen in den Bereichen, in Reihenfolge', array_column($ar['seite'], 'id') === [$w1['id'], $w2['id']] && array_column($ar['fuss'], 'id') === [$w3['id']]);
    $up = $wd->update($w1['id'], ['title' => 'Geändert', 'text' => '<p>Neu</p>']);
    t('Widget geändert', $up['title'] === 'Geändert' && str_contains($up['settings']['text'], 'Neu'));
    $wd->move(['seite' => [$w2['id']], 'fuss' => [$w3['id'], $w1['id']]]);
    $ar = array_column($wd->overview()['areas'], 'widgets', 'id');
    t('Verschoben: von Seitenleiste in den Fußbereich, Reihenfolge neu', array_column($ar['seite'], 'id') === [$w2['id']] && array_column($ar['fuss'], 'id') === [$w3['id'], $w1['id']]);
    $wd->move(['wp_inactive_widgets' => [$w2['id']]]);
    $ar = array_column($wd->overview()['areas'], 'widgets', 'id');
    t('In die Ablage gelegt, nirgends doppelt', array_column($ar['wp_inactive_widgets'], 'id') === [$w2['id']] && $ar['seite'] === []);
    t('Unbekannter Bereich/Widget', throws(fn() => $wd->move(['nirgends' => []]), RuntimeException::class) && throws(fn() => $wd->move(['seite' => ['text-9999']]), RuntimeException::class) && throws(fn() => $wd->add('seite', 'gibtsnicht', []), RuntimeException::class) && throws(fn() => $wd->add('nirgends', 'text', []), RuntimeException::class) && throws(fn() => $wd->update('text-9999', []), RuntimeException::class));
    $wd->move(['seite' => [$w2['id'], $w2['id']]]); t('Doppelte Kennung wird nicht doppelt gesetzt', count(array_column($wd->overview()['areas'], 'widgets', 'id')['seite']) === 1);
    t('Widget löschen (auch aus den Einstellungen)', $wd->delete($w3['id']) && !in_array($w3['id'], array_merge(...array_map(fn($a) => array_column($a['widgets'], 'id'), $wd->overview()['areas'])), true) && !$wd->delete($w3['id']) && !isset(get_option('widget_search', [])[(int)explode('-', $w3['id'])[1]]));
    $aw = new WidgetService(new WordPressWidgetAdapter(), $aut);
    t('Autor darf Widgets nicht ändern, aber ansehen', throws(fn() => $aw->add('seite', 'text', []), PermissionException::class) && throws(fn() => $aw->delete($w1['id']), PermissionException::class) && count($aw->overview()['areas']) === 3);
    // Plugin-Widget ohne Schema (JSON)
    class_exists('WP_Widget') && eval('class Etest_Widget extends WP_Widget { function __construct(){ parent::__construct("etest", "ETest-Widget", ["description"=>"Ein Test"]); } function widget($a,$i){ echo $a["before_widget"] . "ETW:" . esc_html($i["wert"] ?? "") . $a["after_widget"]; } function update($n,$o){ return ["wert" => sanitize_text_field($n["wert"] ?? ""), "zahl" => (int)($n["zahl"] ?? 0)]; } function form($i){} }');
    register_widget('Etest_Widget');
    $pw = $wd->add('seite', 'etest', ['wert' => '<i>x</i> y', 'zahl' => '7', 'fremd' => 'gedroppt']);
    t('Plugin-Widget ohne Schema: JSON, Widget bereinigt selbst', $pw['id_base'] === 'etest' && $pw['settings'] === ['wert' => 'x y', 'zahl' => 7] && WidgetSchemas::for('etest') === null && array_column($wd->overview()['types'], 'schema', 'id_base')['etest'] === null, json_encode($pw));
    t('Plugin-Widget zu tief/groß abgelehnt', throws(fn() => $wd->add('seite', 'etest', ['a' => ['b' => ['c' => ['d' => ['e' => ['f' => ['g' => 1]]]]]]]), InvalidArgumentException::class) && throws(fn() => $wd->add('seite', 'etest', ['wert' => str_repeat('x', 30000)]), InvalidArgumentException::class));
    // Autor-HTML in Text-Widgets wird gefiltert, Administrator darf (unfiltered)
    $wa = $wd->add('seite', 'text', ['text' => '<p onclick="x()">A</p><iframe src="https://e.org"></iframe>']);
    t('Text-Widget HTML nach Recht: Administrator (die Schicht bereinigt zusätzlich)', !str_contains($wa['settings']['text'], 'onclick'));

    // Blöcke
    $bl = new BlockService($adm);
    $reg = array_column($bl->registry(), null, 'name');
    t('Block-Typen aus WordPress', isset($reg['core/paragraph'], $reg['core/heading'], $reg['core/columns']) && $reg['core/paragraph']['core'] === true && $reg['core/latest-posts']['dynamic'] === true && $reg['core/heading']['title'] !== '');
    register_block_type('etest/plugin-block', ['title' => 'ETest-Block', 'render_callback' => fn() => '<b>DYN</b>']);
    $reg = array_column($bl->registry(), null, 'name');
    t('Block eines Plugins erscheint (nicht „core“)', isset($reg['etest/plugin-block']) && $reg['etest/plugin-block']['core'] === false && $reg['etest/plugin-block']['dynamic'] === true);
    $mk = "<!-- wp:paragraph {\"align\":\"center\"} -->\n<p class=\"has-text-align-center\">Mitte</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:etest/plugin-block /-->\n\n<!-- wp:fremd/unbekannt {\"x\":1} -->\n<div>Roh</div>\n<!-- /wp:fremd/unbekannt -->\n\n<!-- wp:columns --><div class=\"wp-block-columns\"><!-- wp:column --><div class=\"wp-block-column\"><!-- wp:paragraph --><p>Links</p><!-- /wp:paragraph --></div><!-- /wp:column --></div><!-- /wp:columns -->\n\nLoser Text";
    $tree = $bl->parse($mk); $names = array_map(fn($b) => $b['blockName'], $tree);
    t('Parse: Blöcke, verschachtelt, loser Text als Freiform', in_array('core/paragraph', $names, true) && in_array('etest/plugin-block', $names, true) && in_array('core/columns', $names, true) && in_array(null, $names, true));
    $byName = []; foreach ($tree as $b) { $byName[(string)$b['blockName']] = $b; }
    t('Parse: bekannt, dynamisch, unbekannt (Fallback)', $byName['core/paragraph']['known'] === true && $byName['etest/plugin-block']['dynamic'] === true && $byName['fremd/unbekannt']['known'] === false && $byName['core/columns']['innerBlocks'][0]['blockName'] === 'core/column');
    $ck = $bl->check($mk);
    t('Prüfung: Zahl, unbekannte, dynamische, Freiform', $ck['blocks'] >= 6 && $ck['unknown'] === ['fremd/unbekannt'] && $ck['dynamic'] === ['etest/plugin-block'] && $ck['freeform'] === true, json_encode($ck));
    $back = $bl->serialize(json_decode(json_encode($tree), true));
    t('Rundlauf: parse → serialize → parse ergibt dieselben Blöcke (auch unbekannte bleiben erhalten)', array_map(fn($b) => $b['blockName'], $bl->parse($back)) === $names && str_contains($back, '<!-- wp:fremd/unbekannt {"x":1} -->') && str_contains($back, '<div>Roh</div>') && str_contains($back, 'Mitte'), $back);
    $html = $bl->render($mk);
    t('Ausgabe wie auf der Website (Absatz, dynamischer Block, Spalten)', str_contains($html, 'has-text-align-center') && str_contains($html, '<b>DYN</b>') && str_contains($html, 'wp-block-columns') && str_contains($html, 'Links'), $html);
    t('Ausgabe für Autoren bereinigt, für Administratoren nicht', !str_contains((new BlockService($aut))->render('<!-- wp:html --><script>x()</script><p>ok</p><!-- /wp:html -->'), '<script') && str_contains($bl->render('<!-- wp:html --><script>x()</script><!-- /wp:html -->'), '<script'));
    t('Serialize: Autor wird gefiltert, Namen/Größe geprüft', !str_contains((new BlockService($aut))->serialize([['blockName' => 'core/html', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '<p onclick="x()">a</p>', 'innerContent' => ['<p onclick="x()">a</p>']]]), 'onclick') && throws(fn() => $bl->serialize([['blockName' => 'böse name', 'innerHTML' => 'x']]), InvalidArgumentException::class) && throws(fn() => $bl->parse(str_repeat('x', BlockService::MAX_BYTES + 1)), InvalidArgumentException::class));
    t('Serialize: Tiefe und Anzahl begrenzt', throws(fn() => $bl->serialize(array_fill(0, 600, ['blockName' => 'core/paragraph', 'innerHTML' => '<p>x</p>', 'innerContent' => ['<p>x</p>']])), InvalidArgumentException::class) && (function () use ($bl) { $d = ['blockName' => 'core/group', 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]; for ($i = 0; $i < 12; $i++) { $d = ['blockName' => 'core/group', 'innerBlocks' => [$d], 'innerHTML' => '', 'innerContent' => [null]]; } return throws(fn() => $bl->serialize([$d]), InvalidArgumentException::class); })());
    // ElvadoPress → WordPress
    $ep = '<!-- ep:paragraph {"align":"center"} --><p style="text-align:center">Hallo <b>Welt</b></p><!-- /ep:paragraph -->' . "\n" . '<!-- ep:heading {"level":3} --><h3>Titel</h3><!-- /ep:heading -->' . "\n" . '<!-- ep:list {"ordered":true} --><ol><li>A</li><li>B</li></ol><!-- /ep:list -->' . "\n" . '<!-- ep:quote --><blockquote style="x"><p>Zitat</p><cite>Quelle</cite></blockquote><!-- /ep:quote -->' . "\n" . '<!-- ep:code --><pre><code>a &lt; b</code></pre><!-- /ep:code -->' . "\n" . '<!-- ep:paragraph {"color":"#f00"} --><p style="color:#f00">Rot</p><!-- /ep:paragraph -->' . "\n" . '<!-- ep:columns {"gap":24} --><div style="display:flex"><!-- ep:column --><div><!-- ep:paragraph --><p>Links</p><!-- /ep:paragraph --></div><!-- /ep:column --><!-- ep:column --><div><!-- ep:paragraph --><p>Rechts</p><!-- /ep:paragraph --></div><!-- /ep:column --></div><!-- /ep:columns -->';
    $cv = $bl->convert($ep, 'wp');
    $ct = array_map(fn($b) => $b['blockName'], array_values(array_filter($bl->parse($cv['markup']), fn($b) => !$b['freeform'])));
    t('Umwandlung → WordPress: echte Core-Blöcke, bekannt, ohne Fremdes', $ct === ['core/paragraph', 'core/heading', 'core/list', 'core/quote', 'core/code', 'core/html', 'core/columns'] && $cv['check']['unknown'] === [] && $cv['converted'] >= 7 && $cv['fallback'] === 1, json_encode([$ct, $cv['converted'], $cv['fallback']]));
    $vis = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($bl->render($cv['markup'])))));
    t('Umwandlung → WordPress: gleicher sichtbarer Text, Ausgabe mit WordPress-Klassen', str_contains($vis, 'Hallo Welt') && str_contains($vis, 'Titel') && str_contains($vis, 'Zitat') && str_contains($vis, 'a < b') && str_contains($vis, 'Rot') && str_contains($vis, 'Links') && str_contains($vis, 'Rechts') && str_contains($bl->render($cv['markup']), 'wp-block-heading'), $vis);
    t('Gestylter Block bleibt unverändert als HTML-Block (nichts geht verloren)', str_contains($cv['markup'], '<!-- wp:html --><p style="color:#f00">Rot</p><!-- /wp:html -->'));
    $rt = $bl->convert($cv['markup'], 'ep');
    t('Umwandlung → ElvadoPress: einfache Blöcke zurück, Rest bleibt WordPress', str_contains($rt['markup'], '<!-- ep:paragraph {"align":"center"} -->') && str_contains($rt['markup'], '<!-- ep:heading {"level":3} -->') && str_contains($rt['markup'], '<!-- ep:list {"ordered":true} -->') && str_contains($rt['markup'], '<!-- ep:quote -->') && str_contains($rt['markup'], '<!-- ep:code -->') && str_contains($rt['markup'], '<!-- wp:columns'), $rt['markup']);
    t('Umwandlung: ungültiges Ziel', throws(fn() => $bl->convert('x', 'html'), InvalidArgumentException::class));
    echo $fail ? "KIND: $fail von $n fehlgeschlagen\n" : "KIND: $n von $n bestanden\n";
    exit($fail ? 1 : 0);
}

$tmp = sys_get_temp_dir() . '/wpnwb-test-' . bin2hex(random_bytes(4));
mkdir("$tmp/nat", 0755, true);
register_shutdown_function(fn() => rmrf($tmp));
$adm = new Actor('chef', 'admin'); $aut = new Actor('schreiber', 'autor');

// ───────── 1) Converter (ohne WordPress) ─────────
$ep = '<!-- ep:paragraph {"align":"center"} --><p style="text-align:center">Hallo</p><!-- /ep:paragraph -->';
$c = Converter::toWp($ep);
t('Converter: Absatz mit Ausrichtung', $c['markup'] === '<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Hallo</p><!-- /wp:paragraph -->' && $c['converted'] === 1 && $c['fallback'] === 0, $c['markup']);
t('Converter: Überschrift Ebene 2 ohne Attribut, Ebene 4 mit', str_contains(Converter::toWp('<!-- ep:heading {"level":2} --><h2>A</h2><!-- /ep:heading -->')['markup'], '<!-- wp:heading --><h2 class="wp-block-heading">A</h2>') && str_contains(Converter::toWp('<!-- ep:heading {"level":4,"align":"right"} --><h4>A</h4><!-- /ep:heading -->')['markup'], '{"textAlign":"right","level":4}'));
t('Converter: Trennlinie und Abstand', str_contains(Converter::toWp('<!-- ep:divider {"style":"solid","thickness":2,"width":100,"color":"#94a3b8"} --><hr style="x"><!-- /ep:divider -->')['markup'], 'wp:separator') && str_contains(Converter::toWp('<!-- ep:spacer {"height":80} --><div style="height:80px"></div><!-- /ep:spacer -->')['markup'], '{"height":"80px"}'));
t('Converter: eigene Stile → HTML-Block unverändert', Converter::toWp('<!-- ep:divider {"style":"dashed","thickness":4} --><hr style="y"><!-- /ep:divider -->')['markup'] === '<!-- wp:html --><hr style="y"><!-- /wp:html -->');
t('Converter: Shortcode, HTML, unbekannter Typ', str_contains(Converter::toWp('<!-- ep:shortcode --><p>[galerie id="3"]</p><!-- /ep:shortcode -->')['markup'], '<!-- wp:shortcode -->[galerie id="3"]<!-- /wp:shortcode -->') && Converter::toWp('<!-- ep:html --><div>x</div><!-- /ep:html -->')['markup'] === '<!-- wp:html --><div>x</div><!-- /wp:html -->' && Converter::toWp('<!-- ep:gallery {"images":[1]} --><div>g</div><!-- /ep:gallery -->')['fallback'] === 1);
t('Converter: Text außerhalb von Blöcken bleibt', Converter::toWp("Vorher\n" . $ep . "\nNachher")['markup'] === "Vorher\n" . Converter::toWp($ep)['markup'] . "\nNachher");
t('Converter: unbalancierte Kommentare brechen nichts', Converter::toWp('<!-- ep:paragraph --><p>x</p>')['markup'] === '<!-- ep:paragraph --><p>x</p>' && Converter::toWp('<!-- /ep:paragraph -->')['markup'] === '<!-- /ep:paragraph -->');
t('Converter: Rückweg für Liste/Zitat/Code/Trennlinie/Abstand/HTML', (function () {
    $wp = '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>A</li><!-- /wp:list-item --></ul><!-- /wp:list --><!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator --><!-- wp:spacer {"height":"60px"} --><div style="height:60px" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer --><!-- wp:html --><div>x</div><!-- /wp:html -->';
    $r = Converter::toEp($wp)['markup'];
    return str_contains($r, '<!-- ep:list --><ul><li>A</li></ul><!-- /ep:list -->') && str_contains($r, '<!-- ep:divider --><hr><!-- /ep:divider -->') && str_contains($r, '<!-- ep:spacer {"height":60} -->') && str_contains($r, '<!-- ep:html --><div>x</div><!-- /ep:html -->');
})());
t('Converter: unbekannte WordPress-Blöcke bleiben unverändert', Converter::toEp('<!-- wp:latest-posts {"x":1} /-->')['markup'] === '<!-- wp:latest-posts {"x":1} /-->' && Converter::toEp('<!-- wp:paragraph {"fontSize":"large"} --><p class="has-large-font-size">A</p><!-- /wp:paragraph -->')['fallback'] === 1);
$w = Converter::scan('A<!-- wp:a {"k":"v"} --><!-- wp:b /--><!-- wp:c -->x<!-- /wp:c --><!-- /wp:a -->B', 'wp');
t('Scan: verschachtelt, selbstschließend, Text davor/danach', count($w) === 3 && $w[0]['text'] === 'A' && $w[1]['name'] === 'a' && $w[1]['attrs'] === ['k' => 'v'] && str_contains($w[1]['inner'], 'wp:b /') && $w[2]['text'] === 'B');
$big = str_repeat('<!-- ep:paragraph --><p>x</p><!-- /ep:paragraph -->', 400); $t0 = microtime(true);
t('Converter: 400 Blöcke schnell', Converter::toWp($big)['converted'] === 400 && microtime(true) - $t0 < 2.0);

// ───────── 2) NavigationService (Zwischenspeicher-Adapter) ─────────
final class MemNav implements NavigationAdapter {
    public array $saved = []; public bool $rw = true; public array $assigned = [];
    public function writable(): bool { return $this->rw; }
    public function menus(): array { return [['id' => '1', 'name' => 'M', 'slug' => 'm', 'count' => 0, 'locations' => []]]; }
    public function tree(string $id): ?array { return $id === '1' ? ['id' => '1', 'name' => 'M', 'items' => $this->saved] : null; }
    public function create(string $name): array { return ['id' => '2', 'name' => $name, 'slug' => 'x', 'count' => 0, 'locations' => []]; }
    public function rename(string $id, string $name): void { $this->saved = ['umbenannt:' . $name]; }
    public function delete(string $id): bool { return $id === '1'; }
    public function saveTree(string $id, array $items): array { $this->saved = $items; return $items; }
    public function locations(): array { return []; }
    public function assign(string $l, string $m): void { $this->assigned = [$l, $m]; }
}
$mn = new MemNav(); $ns = new NavigationService($mn, $adm);
$ns->save('1', [['type' => 'custom', 'label' => '  <b>Start</b>  ', 'url' => '/', 'rel' => 'NoFollow<>', 'classes' => 'a"b c', 'target' => 'x', 'id' => '12', 'children' => [['type' => 'page', 'object_id' => '5', 'label' => '']]], 'quatsch']);
t('Menü bereinigt: Text, rel, Klassen, Ziel, ID', $mn->saved[0]['label'] === 'Start' && $mn->saved[0]['rel'] === 'nofollow' && $mn->saved[0]['classes'] === 'ab c' && $mn->saved[0]['target'] === '' && $mn->saved[0]['id'] === '12' && $mn->saved[0]['children'][0]['object_id'] === '5' && count($mn->saved) === 1, json_encode($mn->saved));
$badNav = [
    'Art' => [['type' => 'script', 'label' => 'x']], 'javascript-Link' => [['type' => 'custom', 'label' => 'x', 'url' => 'javascript:alert(1)']], 'Link ohne Text' => [['type' => 'custom', 'label' => '', 'url' => '/x']],
    'Link ohne Adresse' => [['type' => 'custom', 'label' => 'x', 'url' => '']], 'Inhalt ohne Kennung' => [['type' => 'page', 'object_id' => 'abc']],
    'zu tief' => [['type' => 'custom', 'label' => 'a', 'url' => '/', 'children' => [['type' => 'custom', 'label' => 'b', 'url' => '/', 'children' => [['type' => 'custom', 'label' => 'c', 'url' => '/', 'children' => [['type' => 'custom', 'label' => 'd', 'url' => '/', 'children' => [['type' => 'custom', 'label' => 'e', 'url' => '/']]]]]]]]]],
    'zu viele' => array_fill(0, NavigationService::MAX_ITEMS + 1, ['type' => 'custom', 'label' => 'x', 'url' => '/']),
];
foreach ($badNav as $label => $items) { t("Menü abgelehnt: $label", throws(fn() => $ns->save('1', $items), InvalidArgumentException::class)); }
t('Vier Ebenen sind erlaubt', (function () use ($ns) { $d = ['type' => 'custom', 'label' => 'd', 'url' => '/']; $c = ['type' => 'custom', 'label' => 'c', 'url' => '/', 'children' => [$d]]; $b = ['type' => 'custom', 'label' => 'b', 'url' => '/', 'children' => [$c]]; $r = $ns->save('1', [['type' => 'custom', 'label' => 'a', 'url' => '/', 'children' => [$b]]]); return $r[0]['children'][0]['children'][0]['children'][0]['label'] === 'd'; })());
t('Menü-Kennungen und Namen', throws(fn() => $ns->save('../x', []), InvalidArgumentException::class) && throws(fn() => $ns->create(''), InvalidArgumentException::class) && throws(fn() => $ns->create(str_repeat('a', 81)), InvalidArgumentException::class) && $ns->create(' <i>Neu</i> ')['name'] === 'Neu' && $ns->tree('x y') === null && !$ns->delete('x y') && throws(fn() => $ns->assign('a b', '1'), InvalidArgumentException::class));
$ns->assign('haupt', '1'); t('Zuweisen und Lösen', $mn->assigned === ['haupt', '1'] && ($ns->assign('haupt', '') ?? true) && $mn->assigned === ['haupt', '']);
t('Autor und schreibgeschützt', throws(fn() => (new NavigationService($mn, $aut))->save('1', []), PermissionException::class) && throws(fn() => (new NavigationService($mn, $aut))->create('x'), PermissionException::class) && (function () use ($mn, $adm) { $mn->rw = false; $r = throws(fn() => (new NavigationService($mn, $adm))->save('1', []), RuntimeException::class); $mn->rw = true; return $r; })());
// Native
file_put_contents("$tmp/nat/site.json", json_encode(['menus' => ['top' => [['id' => 'a', 'label' => 'Start', 'target' => '/'], ['id' => 'b', 'label' => 'Radio', 'target' => '/radio', 'parent_id' => 'a'], ['id' => 'c', 'label' => 'Versteckt', 'target' => '/v', 'enabled' => false], ['id' => 'd', 'label' => 'Waise', 'target' => '/w', 'parent_id' => 'gibtsnicht']], 'bottom' => [['id' => 'z', 'label' => 'Unten', 'target' => '#unten']]]]));
$nn = new NativeNavigationAdapter("$tmp/nat"); $ntree = $nn->tree('top');
t('Native Menüs: Baum, deaktivierte weg, Waisen oben', array_column($nn->menus(), 'count', 'id') === ['top' => 4, 'bottom' => 1] && count($ntree['items']) === 2 && $ntree['items'][0]['children'][0]['label'] === 'Radio' && $ntree['items'][1]['label'] === 'Waise' && $nn->tree('x') === null);
t('Native Menüs schreibgeschützt', !$nn->writable() && throws(fn() => (new NavigationService($nn, $adm))->save('top', []), RuntimeException::class) && throws(fn() => (new NavigationService($nn, $adm))->create('x'), RuntimeException::class));
t('Native: kein Site-Datei', (new NativeNavigationAdapter("$tmp/leer"))->tree('top')['items'] === [] && count((new NativeNavigationAdapter("$tmp/leer"))->menus()) === 2);

// ───────── 3) WidgetService (Zwischenspeicher-Adapter) ─────────
final class MemWidgets implements WidgetAdapter {
    public array $last = []; public bool $rw = true;
    public function writable(): bool { return $this->rw; }
    public function areas(): array { return []; }
    public function types(): array { return [['id_base' => 'text', 'name' => 'Text', 'description' => ''], ['id_base' => 'plugin-x', 'name' => 'P', 'description' => '']]; }
    public function add(string $a, string $b, array $s, bool $u): array { $this->last = [$a, $b, $s, $u]; return ['id' => $b . '-1', 'id_base' => $b, 'number' => 1, 'title' => '', 'summary' => '', 'settings' => $s]; }
    public function update(string $i, array $s, bool $u): array { $this->last = [$i, $s, $u]; return ['id' => $i, 'id_base' => '', 'number' => 1, 'title' => '', 'summary' => '', 'settings' => $s]; }
    public function move(array $l): void { $this->last = $l; }
    public function delete(string $i): bool { $this->last = [$i]; return true; }
}
$mw = new MemWidgets(); $ws = new WidgetService($mw, $adm);
$ws->add('seite', 'text', ['title' => '<b>T</b>', 'text' => '<p onclick="x()">a</p><script>b</script>', 'filter' => 'on', 'evil' => 1]);
t('Widget nach Schema bereinigt (Skript/Ereignis raus, Häkchen 1, fremde Felder weg)', $mw->last[2]['title'] === 'T' && !str_contains($mw->last[2]['text'], 'script') && !str_contains($mw->last[2]['text'], 'onclick') && $mw->last[2]['filter'] === 1 && !isset($mw->last[2]['evil']) && $mw->last[3] === true);
$ws->add('seite', 'plugin-x', ['a' => 1, 'b' => ['c' => 2]]); t('Plugin-Widget: Einstellungen unverändert weitergereicht', $mw->last[2] === ['a' => 1, 'b' => ['c' => 2]]);
$ws->update('text-4', ['title' => 'N']); t('Update leitet Kennung und Einstellungen durch', $mw->last[0] === 'text-4' && $mw->last[1]['title'] === 'N');
$ws->move(['seite' => ['text-1', 'plugin-x-2'], 'wp_inactive_widgets' => []]); t('Anordnung durchgereicht', $mw->last === ['seite' => ['text-1', 'plugin-x-2'], 'wp_inactive_widgets' => []]);
$badW = ['Bereich' => fn() => $ws->add('a b', 'text', []), 'Typ' => fn() => $ws->add('a', 'Text!', []), 'Kennung' => fn() => $ws->update('text', []), 'Löschen' => fn() => $ws->delete('../x-1'), 'Anordnung' => fn() => $ws->move('x'),
    'Anordnung-ID' => fn() => $ws->move(['a' => ['x y']]), 'zu viele' => fn() => $ws->move(['a' => array_map(fn($i) => "text-$i", range(1, 501))]), 'Plugin zu groß' => fn() => $ws->add('a', 'plugin-x', ['x' => str_repeat('a', 21000)]),
    'Plugin zu tief' => fn() => $ws->add('a', 'plugin-x', ['a' => ['b' => ['c' => ['d' => ['e' => ['f' => 1]]]]]])];
foreach ($badW as $label => $fn) { t("Widget abgelehnt: $label", throws($fn, InvalidArgumentException::class)); }
t('Widget-Übersicht: Typen mit Schema oder null', array_column($ws->overview()['types'], 'schema', 'id_base')['plugin-x'] === null && array_column($ws->overview()['types'], 'schema', 'id_base')['text'][0]['k'] === 'title');
t('Widgets: Autor und schreibgeschützt', throws(fn() => (new WidgetService($mw, $aut))->add('a', 'text', []), PermissionException::class) && throws(fn() => (new WidgetService($mw, $aut))->move([]), PermissionException::class) && (function () use ($mw, $adm) { $mw->rw = false; $r = throws(fn() => (new WidgetService($mw, $adm))->delete('text-1'), RuntimeException::class); $mw->rw = true; return $r; })());
$schemaKeys = []; foreach (['text', 'custom_html', 'block', 'search', 'meta', 'calendar', 'recent-posts', 'recent-comments', 'archives', 'categories', 'pages', 'nav_menu', 'tag_cloud'] as $b) { $s = WidgetSchemas::for($b); $schemaKeys[$b] = $s !== null && $s !== []; }
t('Schemas für alle Kern-Widgets, keines für unbekannte', !in_array(false, $schemaKeys, true) && WidgetSchemas::for('unbekannt') === null);
file_put_contents("$tmp/nat/site.json", json_encode(['widgets' => [['id' => 'w1', 'name' => 'Letzte News', 'builtin' => 'news-latest', 'title' => 'News'], ['id' => 'w2', 'name' => 'Eigen', 'type' => 'html']], 'widget_areas' => [['id' => 'sb', 'name' => 'Seitenleiste', 'widgets' => ['w1', ['widget' => 'w2'], 'fehlt']]]]));
$nw = new NativeWidgetAdapter("$tmp/nat"); $na = $nw->areas();
t('Native Widgets lesbar, schreibgeschützt', count($na) === 1 && $na[0]['name'] === 'Seitenleiste' && count($na[0]['widgets']) === 3 && $na[0]['widgets'][0]['title'] === 'News' && $nw->types()[0]['id_base'] === 'news-latest' && !$nw->writable() && throws(fn() => (new WidgetService($nw, $adm))->add('sb', 'text', []), RuntimeException::class));

// ───────── 4) Echtes WordPress (optional) ─────────
$zip = (string)getenv('WPE_TEST_ZIP'); $dbs = (string)getenv('WPE_TEST_DB');
if ($zip === '' || $dbs === '' || !is_file($zip)) {
    echo "Hinweis: Integrationsteil mit echtem WordPress übersprungen (WPE_TEST_ZIP/WPE_TEST_DB nicht gesetzt).\n";
} else {
    [$h, $name, $u, $pw] = array_pad(explode('|', $dbs), 4, '');
    $z = new ZipArchive(); $z->open($zip);
    preg_match('/\$wp_version\s*=\s*\'([^\']+)\'/', (string)$z->getFromName('wordpress/wp-includes/version.php'), $m); $z->close();
    @mkdir("$tmp/wp/cms/data", 0755, true); @mkdir("$tmp/wp/cms/wp-content", 0755, true);
    $eng = new Engine("$tmp/wp/cms", "$tmp/wp/cms/data");
    $r = (new CoreInstaller($eng))->install($zip, $m[1], sha1_file($zip));
    $db = new DbConfig($eng); $cfg = ['host' => $h, 'name' => $name, 'user' => $u, 'pass' => $pw, 'prefix' => 'wptest_'];
    $tr = $db->test($cfg);
    t('Echter Core und leere Datenbank', $r['ok'] && $tr['ok'] && !$tr['needs_empty'], $r['message'] . ' ' . $tr['message']);
    if ($r['ok'] && $tr['ok']) {
        $db->save($cfg); $eng->save(['db' => ['ready' => true]]);
        $out = []; exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --child ' . escapeshellarg("$tmp/wp") . ' 2>&1', $out, $rc);
        foreach ($out as $l) { echo $l . "\n"; }
        t('Kindprozess (echtes WordPress) erfolgreich', $rc === 0);
        [$host, $port] = array_pad(explode(':', $h), 2, '3306');
        $my = @new mysqli($host === 'localhost' ? '127.0.0.1' : $host, $u, $pw, $name, (int)$port);
        if (!$my->connect_errno) { $res = $my->query("SHOW TABLES LIKE 'wptest\\_%'"); while ($res && ($row = $res->fetch_row())) { $my->query('DROP TABLE `' . $my->real_escape_string($row[0]) . '`'); } }
    }
}
echo $fail ? "$fail von $n Prüfungen fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n";
exit($fail ? 1 : 0);

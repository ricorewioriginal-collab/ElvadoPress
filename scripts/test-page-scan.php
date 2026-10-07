<?php
declare(strict_types=1);
// scripts/test-page-scan.php – Seitenstruktur live erkennen (Scanner der Vorschau-Brücke), erkannte Bereiche gestalten (ep_area), Anbindung in Live Builder und Customizer.
// Der Browser-Teil (Scanner auf einer Testseite) läuft nur mit Node + Playwright + Chromium, sonst wird er übersprungen. Aufruf: php scripts/test-page-scan.php
$root = dirname(__DIR__);
require_once $root . '/cms/lib/components.php';
use Elvado\Components\{Registry, CoreComponents, Layout, Renderer, Component};
$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }

// 1) Komponente „Erkannter Bereich“ (Server)
$reg = new Registry(); CoreComponents::register($reg);
$c = $reg->get('ep_area');
t('ep_area ist registriert (bound, Selektor pro Instanz)', $c !== null && $c->renderer === 'bound' && $c->bind === Component::BIND_ANY);
t('Selektor-Prüfung: Kennung, Klasse, Gliederungs-Element erlaubt', Component::validSelector('#kopf') && Component::validSelector('.karte-1') && Component::validSelector('footer') && Component::validSelector('SECTION'));
foreach (['div > a', '#a b', '.a{x}', 'body', 'a[href]', '', '#' . str_repeat('a', 70), '#a;b', "#a'b", '*'] as $bad) { t('Selektor abgelehnt: ' . json_encode($bad), !Component::validSelector($bad)); }
$lay = new Layout($reg);
$mk = fn(array $props, array $extra = []) => $lay->clean([array_merge(['id' => 'a1', 'type' => 'ep_area', 'props' => $props], $extra)], ['admin' => true]);
$rd = new Renderer($reg);
$css = $rd->boundCss($mk(['selector' => '#sec-shows', 'bg' => '#ff0000', 'height' => 120, 'mt' => 8]));
t('Gestaltung: CSS für den Selektor der Instanz', str_contains($css, 'html body #sec-shows{') && str_contains($css, 'background-color:#ff0000!important') && str_contains($css, 'min-height:120px!important') && str_contains($css, 'margin-top:8px!important'), $css);
t('Ohne Änderungen entsteht kein CSS (Seite bleibt gleich)', $rd->boundCss($mk(['selector' => '#sec-shows'])) === '');
t('Ungültiger Selektor erzeugt nie CSS', $rd->boundCss($mk(['selector' => 'div > a{x}', 'bg' => '#ff0000'])) === '' && $rd->boundCss($mk(['selector' => 'body', 'bg' => '#ff0000'])) === '' && $rd->boundCss($mk(['selector' => '</style><script>', 'bg' => '#ff0000'])) === '');
t('Ausgeblendet: display:none für den Bereich', str_contains($rd->boundCss($mk(['selector' => '.hero'], ['hidden' => true])), 'html body .hero{display:none!important}'));
t('Geräte: Bereich nur am Desktop sichtbar blendet Tablet/Mobil aus', str_contains($rd->boundCss($mk(['selector' => '.hero'], ['visibility' => ['devices' => ['desktop']]])), '@media'));
$cat = $reg->catalog(['admin' => true]);
t('Katalog nennt ep_area nur Administratoren', in_array('ep_area', array_column($cat['components'], 'id'), true) && !in_array('ep_area', array_column($reg->catalog(['admin' => false])['components'], 'id'), true));

// 1b) Inhalts-Änderungen: Pfad-Selektoren, Texte, Reihenfolge (Server)
foreach (['#sec-start > h1:nth-of-type(1)', 'body > div:nth-of-type(2) > .hero', '#a', 'body'] as $ok) { t('Pfad gültig: ' . $ok, Component::validPath($ok)); }
foreach (['div > a', '#a > h1; x', '#a b', 'body > ' . str_repeat('div > ', 9) . 'p', '#a > h1:nth-of-type(x)', "#a > p'", '#a > p{x}', '', '#a > '. str_repeat('x', 12)] as $bad) { t('Pfad abgelehnt: ' . json_encode($bad), !Component::validPath($bad)); }
$mkT = fn(array $props, array $extra = []) => array_merge(['id' => 't' . substr(md5(json_encode($props)), 0, 6), 'type' => 'ep_text', 'props' => $props], $extra);
$ovl = $rd->overrides($lay->clean([
    $mkT(['selector' => '#hero > h1:nth-of-type(1)', 'node' => 2, 'text' => 'Neuer Titel']),
    $mkT(['selector' => 'div > a', 'text' => 'ungültig']),
    $mkT(['selector' => '#x > p:nth-of-type(1)', 'text' => 'ausgeblendet'], ['hidden' => true]),
    ['id' => 'o1', 'type' => 'ep_order', 'props' => ['container' => '#liste', 'items' => [['selector' => '#c'], ['selector' => '#a'], ['selector' => 'div > x'], ['selector' => '.b']]]],
    ['id' => 'o2', 'type' => 'ep_order', 'props' => ['container' => '#leer', 'items' => [['selector' => '#a']]]],
], ['admin' => true]));
t('Inhalts-Änderungen: nur geprüfte Texte (nicht ausgeblendet, nicht ungültig)', count($ovl['texts']) === 1 && $ovl['texts'][0] === ['s' => '#hero > h1:nth-of-type(1)', 'k' => 2, 't' => 'Neuer Titel'], json_encode($ovl['texts']));
t('Inhalts-Änderungen: Reihenfolge mit gültigen Einträgen, mindestens zwei', count($ovl['order']) === 1 && $ovl['order'][0]['c'] === '#liste' && $ovl['order'][0]['o'] === ['#c', '#a', '.b'], json_encode($ovl['order']));
t('Inhalts-Änderungen erzeugen kein CSS und keine Fehler ohne Instanzen', $rd->boundCss($lay->clean([$mkT(['selector' => '#a > p:nth-of-type(1)', 'text' => 'x'])], ['admin' => true])) === '' && $rd->overrides([]) === ['texts' => [], 'order' => []]);
t('Katalog: ep_text und ep_order nur für Administratoren', in_array('ep_text', array_column($cat['components'], 'id'), true) && in_array('ep_order', array_column($cat['components'], 'id'), true) && !in_array('ep_text', array_column($reg->catalog(['admin' => false])['components'], 'id'), true));
// Auslieferung: Daten sicher eingebettet (kein Ausbrechen aus dem Skript-Element), Skript fest, ohne Änderungen byte-gleich
$td = sys_get_temp_dir() . '/ep-ov-' . bin2hex(random_bytes(4)); mkdir($td . '/layouts', 0775, true);
define('RRW_DATA_DIR', $td);
$store = rrw_components_store($td); $adm = Elvado\Wp\Actor::fromAuth(['role' => 'admin', 'username' => 'a']);
$evil = '</script><img src=x onerror=alert(1)> & "q" \'x\'';
$store->saveDraft('site:website', $lay->clean([$mkT(['selector' => '#hero > h1:nth-of-type(1)', 'node' => 0, 'text' => $evil, 'orig' => 'Alt'])], ['admin' => true]), $adm); $store->publish('site:website', $adm);
$html = '<!doctype html><html><head><title>x</title></head><body><div id="hero"><h1>Alt</h1></div></body></html>';
$outH = rrw_components_inject($html, 'site:website', [], $td);
preg_match('~<script id="ep-overrides-data" type="application/json">(.*?)</script>~s', $outH, $mm);
t('Auslieferung: Daten-Element vorhanden, Text enthält kein rohes </script>, < > & \' " sind maskiert', isset($mm[1]) && !str_contains($mm[1], '<') && !str_contains($mm[1], '>') && !str_contains($mm[1], '&') && json_decode($mm[1], true)['texts'][0]['t'] === '& "q" \'x\'' && !str_contains(strtolower($mm[1]), 'script') && !str_contains(strtolower($mm[1]), 'onerror'), $mm[1] ?? '');
t('Auslieferung: festes Skript eingebettet, Seite sonst unverändert', str_contains($outH, '<script id="ep-overrides">') && str_contains($outH, '<div id="hero"><h1>Alt</h1></div>') && substr_count($outH, '<script') === 2);
$store->saveDraft('site:website', [], $adm); $store->publish('site:website', $adm);
t('Ohne Änderungen: Seite bleibt byte-gleich', rrw_components_inject($html, 'site:website', [], $td) === $html);
system('rm -rf ' . escapeshellarg($td));

// 2) Anbindung (statisch)
$br = (string)file_get_contents($root . '/cms/assets/preview-bridge.js');
$lb = (string)file_get_contents($root . '/cms/assets/live-builder.js');
$tm = (string)file_get_contents($root . '/cms/assets/theme-manager.js');
t('Brücke: Scanner meldet „structure“, reagiert auf Änderungen der Seite', str_contains($br, "type:'structure'") && str_contains($br, 'MutationObserver') && str_contains($br, 'function scan()'));
t('Brücke: Selektoren werden vor jeder Nutzung geprüft', substr_count($br, 'SEL_RE.test') >= 4);
t('Brücke: Nachrichten nur vom Eltern-Fenster mit Sitzungsschlüssel', str_contains($br, 'e.source!==PARENT||e.origin!==ORIGIN') && str_contains($br, 'd.token!==token'));
t('Live Builder: erkannte Struktur, „Gestalten“ legt ep_area an, Zielliste ohne automatische ep_area', str_contains($lb, 'function addDetected') && str_contains($lb, "type:'ep_area'") && str_contains($lb, '!/^ep_/.test(t)') && str_contains($lb, "d.type==='structure'"));
t('Customizer: Struktur live, Hervorheben/Auswählen, Zuordnung zu Abschnitten', str_contains($tm, 'Seitenstruktur (live erkannt)') && str_contains($tm, 'czSectionFor') && str_contains($tm, "edit:false") && str_contains($tm, 'preview-bridge.js'));
t('Panel enthält die Liste der erkannten Struktur', str_contains((string)file_get_contents($root . '/cms/views/panel-livebuilder.php'), 'id="lbDetList"'));

// 3) Scanner im Browser (optional)
$pw = trim((string)@shell_exec('node -e "try{require(\'playwright\');console.log(1)}catch(e){console.log(0)}" 2>/dev/null'));
$chrome = null; foreach ([getenv('CHROMIUM') ?: '', '/opt/pw-browsers/chromium', '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome'] as $p) { if ($p !== '' && is_file($p)) { $chrome = $p; break; } }
if ($pw !== '1' || $chrome === null) {
    echo "Hinweis: Browser-Teil übersprungen (Playwright/Chromium nicht verfügbar)\n";
} else {
    $tmp = sys_get_temp_dir() . '/ep-scan-' . getmypid(); mkdir($tmp, 0755, true);
    copy($root . '/cms/assets/preview-bridge.js', $tmp . '/bridge.js');
    file_put_contents($tmp . '/frame.html', '<!doctype html><html><body><header id="kopf" style="height:80px">Kopf</header><main><section class="hero-box" style="height:300px"><h2>Willkommen</h2></section>'
        . '<div class="wrap"><div class="wrap" style="height:200px"><div id="liste" style="height:160px">Liste</div></div></div>'
        . '<div class="card" style="height:90px">A</div><div class="card" style="height:90px">B</div><div class="col-6 p-3" style="height:100px">Raster</div><div id="a1b2c3d4e5f6a7" style="height:100px">Zufall</div>'
        . '<div id="versteckt" style="display:none;height:100px">weg</div><div id="klein" style="height:10px">klein</div></main><footer style="height:120px">Fuß</footer><script src="/bridge.js"></script></body></html>');
    file_put_contents($tmp . '/index.html', '<!doctype html><iframe id="f" src="/frame.html" width="1000" height="800"></iframe><script>window.__nodes=null;window.addEventListener("message",function(e){var d=e.data;if(!d||d.ep!==1)return;if(d.type==="hello"){document.getElementById("f").contentWindow.postMessage({ep:1,type:"init",token:"0123456789abcdef0123",edit:false,regions:[],labels:{},selected:"",scroll:0},location.origin)}if(d.type==="structure"&&d.token==="0123456789abcdef0123")window.__nodes=d.nodes;});</script>');
    file_put_contents($tmp . '/run.js', 'const {chromium}=require("playwright");(async()=>{const b=await chromium.launch({executablePath:process.argv[3],args:["--no-sandbox"]});const p=await b.newPage();await p.goto("http://127.0.0.1:"+process.argv[2]+"/index.html");await p.waitForFunction(()=>window.__nodes,null,{timeout:8000}).catch(()=>{});console.log(JSON.stringify(await p.evaluate(()=>window.__nodes)));await b.close()})();');
    $port = 22000 + random_int(0, 9000);
    $proc = proc_open(['php', '-S', '127.0.0.1:' . $port, '-t', $tmp], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes); usleep(700000);
    $out = trim((string)shell_exec('cd ' . escapeshellarg($tmp) . ' && NODE_PATH=' . escapeshellarg((string)getenv('NODE_PATH')) . ' node run.js ' . $port . ' ' . escapeshellarg($chrome) . ' 2>&1'));
    if (is_resource($proc)) { proc_terminate($proc); }
    $nodes = json_decode($out, true);
    if (!is_array($nodes)) { t('Scanner liefert eine Liste', false, substr($out, 0, 200)); }
    else {
        $by = array_column($nodes, null, 's');
        t('Scanner: Kopf (#kopf), Haupt-Bereich, Fuß erkannt', isset($by['#kopf'], $by['main'], $by['footer']));
        t('Scanner: Abschnitt über eindeutige Klasse und Überschrift benannt', isset($by['.hero-box']) && $by['.hero-box']['l'] === 'Willkommen', json_encode($by['.hero-box'] ?? null));
        t('Scanner: verschachtelte Bereiche mit Eltern und Einrückung', isset($by['#liste']) && $by['#liste']['d'] >= 1 && $by['#liste']['p'] !== '');
        t('Scanner: wiederholte Karten (nicht eindeutig) werden nicht angeboten', !isset($by['.card']));
        t('Scanner: Raster-Hilfsklassen, Zufalls-Kennungen, versteckte und winzige Elemente fehlen', !isset($by['.col-6']) && !isset($by['#a1b2c3d4e5f6a7']) && !isset($by['#versteckt']) && !isset($by['#klein']));
        $allOk = true; foreach ($nodes as $nd) { if (!Component::validSelector((string)$nd['s'])) { $allOk = false; } }
        t('Scanner: alle gemeldeten Selektoren sind gültig', $allOk);
    }
    system('rm -rf ' . escapeshellarg($tmp));
    // Skript der Inhalts-Änderungen im Browser: Text (nur Textknoten), Reihenfolge, nie HTML
    $tmp2 = sys_get_temp_dir() . '/ep-ovjs-' . getmypid(); mkdir($tmp2, 0755, true);
    $data = json_encode(['texts' => [['s' => '#t', 'k' => 0, 't' => '<img src=x onerror="window.__x=1"> Neu '], ['s' => '#t > em:nth-of-type(1)', 'k' => 0, 't' => 'Erde']], 'order' => [['c' => '#c', 'o' => ['#d', '#b', '#a']]]], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    file_put_contents($tmp2 . '/index.html', '<!doctype html><body><div id="c"><div id="a">A</div><p id="mitte">M</p><div id="b">B</div><div id="d">D</div></div><h1 id="t">Hallo <em>Welt</em></h1><script id="ep-overrides-data" type="application/json">' . $data . '</script><script>' . rrw_components_overrides_js() . '</script></body>');
    file_put_contents($tmp2 . '/run.js', 'const {chromium}=require("playwright");(async()=>{const b=await chromium.launch({executablePath:process.argv[3],args:["--no-sandbox"]});const p=await b.newPage();await p.goto("http://127.0.0.1:"+process.argv[2]+"/index.html");await p.waitForTimeout(400);console.log(JSON.stringify(await p.evaluate(()=>({h1:document.getElementById("t").innerHTML,order:[...document.getElementById("c").children].map(x=>x.id),x:window.__x||0,imgs:document.querySelectorAll("img").length}))));await b.close()})();');
    $port2 = 22000 + random_int(0, 9000);
    $proc2 = proc_open(['php', '-S', '127.0.0.1:' . $port2, '-t', $tmp2], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes2); usleep(700000);
    $o2 = json_decode(trim((string)shell_exec('cd ' . escapeshellarg($tmp2) . ' && NODE_PATH=' . escapeshellarg((string)getenv('NODE_PATH')) . ' node run.js ' . $port2 . ' ' . escapeshellarg($chrome) . ' 2>&1')), true);
    if (is_resource($proc2)) { proc_terminate($proc2); }
    t('Skript: Text ersetzt nur den Textknoten (kein HTML, Elemente bleiben), zweite Textstelle im Unterelement', is_array($o2) && str_contains($o2['h1'], '&lt;img') && str_contains($o2['h1'], '<em>Erde</em>') && $o2['imgs'] === 0 && $o2['x'] === 0, json_encode($o2));
    t('Skript: Reihenfolge nur innerhalb des Containers, nicht gelistete Elemente behalten ihren Platz', is_array($o2) && $o2['order'] === ['d', 'mitte', 'b', 'a'], json_encode($o2));
    system('rm -rf ' . escapeshellarg($tmp2));
}
echo $fail ? "$fail von $n fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n"; exit($fail ? 1 : 0);

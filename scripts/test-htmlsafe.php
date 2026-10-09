<?php
// Prüft die HTML-Bereinigung (cms/lib/htmlsafe.php): Block-Editor-Inhalt bleibt, alles Aktive fliegt raus; Raw-HTML-Blöcke nur ungefiltert (Admins).
// Aufruf: php scripts/test-htmlsafe.php
declare(strict_types=1);
require __DIR__.'/../cms/lib/htmlsafe.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
$s=fn(string $h,bool $u=false)=>elvado_html_sanitize($h,$u);
// Erhalten
t('Text, Umlaute, Emoji bleiben',$s('<p>Hallo <b>Welt</b> äöü € 😀</p>')==='<p>Hallo <b>Welt</b> äöü € 😀</p>',$s('<p>Hallo <b>Welt</b> äöü € 😀</p>'));
t('Block-Kommentare (ep:/wp:) bleiben, fremde Kommentare entfallen',$s('<!-- ep:paragraph {"align":"center"} --><p>x</p><!-- /ep:paragraph --><!-- böse --><!-- wp:heading --><h2>y</h2><!-- /wp:heading -->')==='<!-- ep:paragraph {"align":"center"} --><p>x</p><!-- /ep:paragraph --><!-- wp:heading --><h2>y</h2><!-- /wp:heading -->');
t('Tabelle, h5, pre/code, Details bleiben',str_contains($s('<table><tbody><tr><td colspan="2">1</td></tr></tbody></table><h5>h</h5><pre><code>a &lt; b</code></pre><details open><summary>S</summary>T</details>'),'<td colspan="2">1</td>')&&str_contains($s('<h5>h</h5>'),'<h5>h</h5>')&&str_contains($s('<pre><code>a &lt; b</code></pre>'),'a &lt; b')&&str_contains($s('<details open><summary>S</summary>T</details>'),'<details open>'));
t('Harmlose Styles bleiben (Farbe, Ausrichtung, Flex)',$s('<div style="display:flex;gap:10px;color:#ff0000;text-align:center">x</div>')==='<div style="display:flex;gap:10px;color:#ff0000;text-align:center">x</div>');
t('Bild mit relativer Quelle, Video, Audio',str_contains($s('<img src="/a.png" alt="A" width="10">'),'src="/a.png"')&&str_contains($s('<video src="/a.mp4" controls></video>'),'<video src="/a.mp4" controls>')&&str_contains($s('<audio src="https://x.example/a.mp3" controls></audio>'),'<audio'));
t('Player bekannter Anbieter (YouTube, Spotify) als iframe',str_contains($s('<iframe src="https://www.youtube.com/embed/abc" allowfullscreen></iframe>'),'youtube.com/embed/abc')&&str_contains($s('<iframe src="https://open.spotify.com/embed/track/1"></iframe>'),'open.spotify.com'));
t('Link mit target=_blank bekommt rel=noopener noreferrer',str_contains($s('<a href="https://e.com" target="_blank">z</a>'),'rel="noopener noreferrer"'));
// Entfernt
t('Skripte, Ereignis-Attribute, style/form/svg entfallen samt Inhalt',$s('<p onclick="x()">a</p><script>alert(1)</script><style>*{}</style><form><input name="x"></form><svg onload="x()"><circle/></svg><object data="x"></object>')==='<p>a</p>');
t('javascript:-Adressen entfallen (auch mit Umbruch/Entität)',!str_contains($s('<a href="javascript:alert(1)">x</a><a href="  jav&#x09;ascript:alert(1)">y</a><a href="vbscript:x">z</a>'),'script:')&&$s('<a href="javascript:alert(1)">x</a>')==='<a>x</a>');
t('data:-Adressen nur für kleine Bilder; svg+xml nicht; Bild ohne erlaubte Quelle entfällt',!str_contains($s('<img src="data:image/svg+xml;base64,AAAA">'),'<img')&&str_contains($s('<img src="data:image/png;base64,iVBORw0KGgo=" alt="">'),'data:image/png'));
t('Fremde iframes, javascript-iframes entfallen',$s('<iframe src="https://evil.example/x"></iframe><iframe src="javascript:alert(1)"></iframe><iframe src="http://www.youtube.com/embed/a"></iframe><iframe src="https://www.google.com/other"></iframe>')==='');
t('Gefährliche CSS-Angaben entfallen (url, expression, position)',$s('<div style="background:url(javascript:1);width:expression(alert(1));position:fixed;color:red">x</div>')==='<div style="color:red">x</div>');
t('Unbekannte Tags werden entpackt (Text bleibt)',$s('<blink>weiter</blink><custom-tag>text</custom-tag>')==='weitertext');
t('Kaputtes HTML wird repariert (kein Absturz)',$s('<p>offen <b>fett <i>schief</p></b>')!==''&&!str_contains($s('<p>offen <b>fett'),'<script'));
// Raw-HTML-Blöcke
$raw='<!-- ep:html --><script>alert(2)</script><b onclick="x()">x</b><!-- /ep:html -->';
t('Raw-HTML-Block ohne Recht: Inhalt wird bereinigt (Block-Marker bleiben)',$s($raw,false)==='<!-- ep:html --><b>x</b><!-- /ep:html -->',$s($raw,false));
t('Raw-HTML-Block mit Recht (Administrator): unverändert',$s($raw,true)===$raw,$s($raw,true));
t('Wirkt nur im Raw-Block: Skripte außerhalb bleiben auch mit Recht draußen',!str_contains($s('<script>x</script>'.$raw,true),'<script>x</script>')&&str_contains($s('<script>x</script>'.$raw,true),'<script>alert(2)</script>'));
t('Eingeschleuste Platzhalter-Kommentare ohne Recht wirkungslos',!str_contains($s('<!--epraw-0--><p>a</p>',false),'epraw')&&$s('<!--epraw-0--><p>a</p>',false)==='<p>a</p>');
$GLOBALS['elvado_html_unfiltered']=true;t('Anfrage-Schalter $GLOBALS[elvado_html_unfiltered] wirkt',elvado_html_sanitize($raw)===$raw);
define('ELVADO_DEMO',true);t('In der Demo nie ungefiltert',elvado_html_sanitize($raw)!==$raw);
t('Sehr lange Eingabe wird begrenzt',strlen(elvado_html_sanitize(str_repeat('<p>x</p>',100000),false,2000))<2400);
t('Rückfall ohne DOM filtert ebenfalls',!str_contains(elvado_html_sanitize_basic('<p onclick="x()">a</p><script>alert(1)</script><a href="javascript:x">b</a>'),'script'));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

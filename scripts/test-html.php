<?php
// Prüft die HTML-Bereinigung für Beiträge (elvado_safe_html in cms/lib/publish.php). Aufruf: php scripts/test-html.php
declare(strict_types=1);
require __DIR__.'/../cms/lib/publish.php';
$fail=0;$n=0;
function t(string $name,bool $ok): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name\n";} }
$attacks=[
 'unquoted'=>['<p>ok</p><img src=x onerror=alert(2)>','onerror'],
 'double'=>['<img src=x onerror="alert(1)">','onerror'],
 'single'=>["<img src=x onerror='alert(1)'>",'onerror'],
 'slash'=>['<img/src=x/onerror=alert(1)>','onerror'],
 'spaces'=>['<div onclick = alert(1)>x</div>','onclick'],
 'javascript'=>['<a href="javascript:alert(1)">x</a>','javascript'],
 'script'=>['<script>alert(1)</script><p>a</p>','<script'],
 'doppelt'=>['<img src=x onerror=onerror=alert(1)>','onerror'],
];
foreach($attacks as $name=>[$in,$needle])t("Angriff $name entfernt",stripos(elvado_safe_html($in),$needle)===false);
$keep='<p class="x">Text <b>fett</b> <a href="https://a.de/?on=1&amp;b=2">Link</a> <img src="/a.png" alt="Bild"> online=ja</p>';
t('legitimes HTML bleibt',elvado_safe_html($keep)===$keep);
t('Text mit „on“ im Wort bleibt',elvado_safe_html('<p>Das ist ein Ton = gut, Konto=1</p>')==='<p>Das ist ein Ton = gut, Konto=1</p>');
echo $fail===0?"$n von $n Prüfungen bestanden\n":"$fail von $n Prüfungen FEHLGESCHLAGEN\n";
exit($fail===0?0:1);

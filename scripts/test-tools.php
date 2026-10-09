<?php
// Prüft Wartungsmodus, Weiterleitungen und 404-Protokoll (cms/lib/tools.php). Aufruf: php scripts/test-tools.php
declare(strict_types=1);
require __DIR__.'/../cms/lib/tools.php';
$fail=0;$n=0;
function t(string $name,bool $ok): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name\n";} }

// Wartungsmodus: Entscheidung
$cfg=elvado_maint_clean(['enabled'=>true]);
t('Schlüssel erzeugt',(bool)preg_match('/^[a-f0-9]{16}$/',$cfg['key']));
t('aus = allow',elvado_maint_decide(['enabled'=>false,'key'=>'x'],[],[])==='allow');
t('an ohne Schlüssel = block',elvado_maint_decide($cfg,[],[])==='block');
t('falscher Schlüssel = block',elvado_maint_decide($cfg,[],['vorschau'=>'0000000000000000'])==='block');
t('richtiger Schlüssel = bypass',elvado_maint_decide($cfg,[],['vorschau'=>$cfg['key']])==='bypass');
t('Cookie = allow',elvado_maint_decide($cfg,['elvado_maint'=>$cfg['key']],[])==='allow');
t('Array-Parameter = block',elvado_maint_decide($cfg,[],['vorschau'=>[$cfg['key']]])==='block');
t('Schlüssel bleibt beim Speichern',elvado_maint_clean(['enabled'=>false],$cfg['key'])['key']===$cfg['key']);
t('manipulierter Schlüssel wird ersetzt',elvado_maint_clean(['key'=>'../../x'])['key']!=='../../x');
t('Text gekürzt',mb_strlen(elvado_maint_clean(['text'=>str_repeat('a',900)])['text'])===600);
t('Seite maskiert HTML',!str_contains(elvado_maint_page(elvado_maint_clean(['title'=>'<b>x</b>','text'=>'<script>']),'S'),'<script>'));

// Weiterleitungen: Bereinigung
$r=elvado_redirects_clean([
 ['from'=>'/alt.html','to'=>'/neu.html','code'=>301],
 ['from'=>'alt2','to'=>'/x'],                       // kein führender Schrägstrich
 ['from'=>'//evil.com','to'=>'/x'],                 // protokollrelativ
 ['from'=>'/a','to'=>'//evil.com'],                 // Ziel protokollrelativ
 ['from'=>'/b','to'=>'javascript:alert(1)'],        // Schema
 ['from'=>'/c','to'=>'https://example.org/z','code'=>302],
 ['from'=>'/ALT.html/','to'=>'/andere'],            // Duplikat (Groß/Klein, Schrägstrich)
 ['from'=>'/loop','to'=>'/loop/'],                  // Schleife
 ['from'=>'/weg','to'=>'','code'=>410],
 ['from'=>'/d','to'=>'/e','code'=>999],             // ungültiger Code -> 301
 ['from'=>"/x\r\nSet-Cookie: a=b",'to'=>'/y'],      // Header-Injektion
]);
$froms=array_column($r,'from');
t('gültige Regeln bleiben',in_array('/alt.html',$froms,true)&&in_array('/c',$froms,true)&&in_array('/weg',$froms,true));
t('ungültige fliegen raus',count($r)===4);
t('ungültiger Code -> 301',($r[3]['code']??0)===301);
t('410 ohne Ziel',(function() use($r){foreach($r as $x)if($x['from']==='/weg')return $x['to']===''&&$x['code']===410;return false;})());

// Weiterleitungen: Zuordnung
$rules=elvado_redirects_clean([['from'=>'/alt.html','to'=>'/neu.html'],['from'=>'/blog/*','to'=>'/news.html'],['from'=>'/Podcasts','to'=>'/podcast.html']]);
t('exakt',elvado_redirect_match($rules,'/alt.html')['to']==='/neu.html');
t('Groß/Klein + Schrägstrich',elvado_redirect_match($rules,'/ALT.html/')!==null);
t('Query ignoriert',elvado_redirect_match($rules,'/alt.html?x=1')!==null);
t('Präfix',elvado_redirect_match($rules,'/blog/2020/post')['to']==='/news.html');
t('Präfix-Basis',elvado_redirect_match($rules,'/blog')!==null);
t('Präfix nicht über Wortgrenze',elvado_redirect_match($rules,'/blogger')===null);
t('Podcasts',elvado_redirect_match($rules,'/podcasts')['to']==='/podcast.html');
t('kein Treffer',elvado_redirect_match($rules,'/nix')===null);
t('prozentcodiert',elvado_redirect_match($rules,'/%61lt.html')!==null);

// 404-Protokoll
$tmp=sys_get_temp_dir().'/elvado-tools-'.bin2hex(random_bytes(4));mkdir($tmp);
elvado_404_log($tmp,'/fehlt.html','https://x.test/');elvado_404_log($tmp,'/fehlt.html');elvado_404_log($tmp,'/bild.png');elvado_404_log($tmp,'/favicon.ico');
$log=elvado_tools_read(elvado_tools_dir($tmp).'/404.json',['items'=>[]])['items'];
t('404 gezählt',count($log)===1&&$log[0]['count']===2);
t('Bilder/Icons nicht protokolliert',count($log)===1);
for($i=0;$i<320;$i++)elvado_404_log($tmp,'/p'.$i);
t('Protokoll begrenzt',count(elvado_tools_read(elvado_tools_dir($tmp).'/404.json',['items'=>[]])['items'])===300);
$rm=function(string $d) use (&$rm){foreach(array_diff(scandir($d),['.','..']) as $f){$p=$d.'/'.$f;is_dir($p)?$rm($p):unlink($p);}rmdir($d);};$rm($tmp);

echo $fail===0?"$n von $n Prüfungen bestanden\n":"$fail von $n Prüfungen FEHLGESCHLAGEN\n";
exit($fail===0?0:1);

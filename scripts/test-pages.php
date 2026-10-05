<?php
// Prüft Seiten-Status/Planung, SEO-Felder und Versionen (cms/lib/tools.php, cms/lib/publish.php). Aufruf: php scripts/test-pages.php
declare(strict_types=1);
require __DIR__.'/../cms/lib/publish.php';
$fail=0;$n=0;
function t(string $name,bool $ok): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name\n";} }

// Zeitpunkt
t('Format T',rrw_page_publish_at('2030-05-06T07:08')==='2030-05-06 07:08:00');
t('Format Leerzeichen',rrw_page_publish_at('2030-05-06 07:08:09')==='2030-05-06 07:08:00');
t('ungültiger Tag',rrw_page_publish_at('2030-02-31T10:00')==='');
t('ungültige Stunde',rrw_page_publish_at('2030-02-10T25:00')==='');
t('Müll',rrw_page_publish_at('morgen')===''&&rrw_page_publish_at(['x'])==='');
$now=strtotime('2030-01-01 12:00:00');
t('live ohne Zeit',rrw_page_is_live(['enabled'=>true],$now));
t('Entwurf nie live',!rrw_page_is_live(['enabled'=>false,'publish_at'=>'2020-01-01 00:00:00'],$now));
t('geplant noch nicht',!rrw_page_is_live(['enabled'=>true,'publish_at'=>'2030-06-01 00:00:00'],$now));
t('geplant erreicht',rrw_page_is_live(['enabled'=>true,'publish_at'=>'2029-12-31 00:00:00'],$now));

// Bereinigung
$clean=rrw_clean_section('pages',[
  ['id'=>'a','slug'=>'test','title'=>'Test','type'=>'custom','enabled'=>true,'meta_title'=>'<b>Titel</b>','meta_description'=>str_repeat('x',400),'noindex'=>1,'publish_at'=>'2031-01-02T03:04'],
  ['id'=>'b','slug'=>'start','title'=>'Start','type'=>'system','system_target'=>'start','enabled'=>true,'publish_at'=>'2031-01-02T03:04'],
]);
t('Meta-Titel ohne HTML',$clean[0]['meta_title']==='Titel');
t('Meta-Beschreibung gekürzt',mb_strlen($clean[0]['meta_description'])===300);
t('noindex bool',$clean[0]['noindex']===true);
t('Zeitpunkt normalisiert',$clean[0]['publish_at']==='2031-01-02 03:04:00');
t('Systemseite ohne Planung',$clean[1]['publish_at']==='');

// Planung und Vorschau
$tmp=sys_get_temp_dir().'/rrw-pages-'.bin2hex(random_bytes(4));mkdir($tmp);
rrw_page_schedule_write($tmp,[['type'=>'custom','slug'=>'bald','enabled'=>true,'publish_at'=>date('Y-m-d H:i:s',time()+3600)],['type'=>'custom','slug'=>'schon','enabled'=>true,'publish_at'=>date('Y-m-d H:i:s',time()-3600)],['type'=>'custom','slug'=>'aus','enabled'=>false,'publish_at'=>date('Y-m-d H:i:s',time()+3600)]]);
t('geplante Seite verborgen',rrw_page_hidden_until_due($tmp,'bald'));
t('fällige Seite sichtbar',!rrw_page_hidden_until_due($tmp,'schon'));
t('unbekannte Seite sichtbar',!rrw_page_hidden_until_due($tmp,'nix'));
t('Entwurf nicht im Plan',!rrw_page_hidden_until_due($tmp,'aus'));
$cfg=rrw_maint_clean(['enabled'=>false]);rrw_tools_write(rrw_tools_dir($tmp),'maintenance.json',$cfg);
$_GET=[];$_COOKIE=[];t('Vorschau ohne Schlüssel nein',!rrw_preview_key_ok($tmp));
$_GET['vorschau']='0000000000000000';t('falscher Schlüssel nein',!rrw_preview_key_ok($tmp));
$_GET['vorschau']=$cfg['key'];t('richtiger Schlüssel ja',rrw_preview_key_ok($tmp));
$_GET=[];$_COOKIE['rrw_maint']=$cfg['key'];t('Cookie ja',rrw_preview_key_ok($tmp));

// Versionen
$old=[['id'=>'a','title'=>'Alt','blocks_before'=>[]],['id'=>'b','title'=>'Gleich']];
$new=[['id'=>'a','title'=>'Neu','blocks_before'=>[]],['id'=>'b','title'=>'Gleich'],['id'=>'c','title'=>'Neu angelegt']];
t('nur geänderte Seite',rrw_page_revisions_record($tmp,$old,$new,'tester')===1);
$st=rrw_tools_read(rrw_page_revisions_file($tmp),['pages'=>[]])['pages'];
t('alte Fassung gespeichert',($st['a'][0]['page']['title']??'')==='Alt'&&($st['a'][0]['user']??'')==='tester');
t('unveränderte/neue Seiten ohne Version',!isset($st['b'])&&!isset($st['c']));
for($i=0;$i<14;$i++){$o=[['id'=>'a','title'=>'v'.$i]];$nw=[['id'=>'a','title'=>'v'.($i+1)]];rrw_page_revisions_record($tmp,$o,$nw,'x');}
t('höchstens 10 Versionen',count(rrw_tools_read(rrw_page_revisions_file($tmp),['pages'=>[]])['pages']['a'])===10);
t('ohne Änderung keine Version',rrw_page_revisions_record($tmp,$new,$new,'x')===0);

$rm=function(string $d) use (&$rm){foreach(array_diff(scandir($d),['.','..']) as $f){$p=$d.'/'.$f;is_dir($p)?$rm($p):unlink($p);}rmdir($d);};$rm($tmp);
echo $fail===0?"$n von $n Prüfungen bestanden\n":"$fail von $n Prüfungen FEHLGESCHLAGEN\n";
exit($fail===0?0:1);

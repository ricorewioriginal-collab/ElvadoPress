<?php
// Prüft die eigenen Dienste des eigenständigen CMS (cms/lib/services.php): Aufruf  php scripts/test-services.php
declare(strict_types=1);
require_once __DIR__.'/../cms/lib/feeds.php';
require_once __DIR__.'/../cms/lib/services.php';
$fail=0;$n=0;
function t(string $name,callable $fn){global $fail,$n;$n++;try{$fn();echo "  ok  $name\n";}catch(Throwable $e){$fail++;echo "FAIL  $name: ".$e->getMessage()."\n";}}
function eq($a,$b,string $m=''){if($a!==$b)throw new RuntimeException(($m?$m.': ':'').'erwartet '.json_encode($b).', war '.json_encode($a));}

t('Adressen: https, http oder Pfad; ohne Leerzeichen, Zugangsdaten, fremde Schemata',function(){
    foreach(['https://cloud.example.org','http://stat.example.org/x?a=1','/hilfe/'] as $ok)eq(rrw_services_url_clean($ok),$ok);
    foreach(['','javascript:alert(1)','ftp://x.example.org','//evil.example.org','https://u:p@x.example.org','https://a b.example.org','https://x.example.org/"><script>','mailto:a@b.de','cloud.example.org'] as $bad)eq(rrw_services_url_clean($bad),'',$bad);
});
t('Bereinigen: Pflichtangaben, eindeutige Kennungen, Arten, Grenzen',function(){
    $c=rrw_services_clean(['items'=>[
        ['name'=>'Meine Cloud','url'=>'https://cloud.example.org','kind'=>'media','note'=>'<b>Notiz</b>'],
        ['name'=>'Meine Cloud','url'=>'https://cloud2.example.org','kind'=>'gibtsnicht','check'=>false],
        ['name'=>'','url'=>'https://x.example.org'],['name'=>'Ohne Adresse','url'=>'ftp://x'],'kein Array',
        ['name'=>'Über Größe','url'=>'/hilfe/'],
    ]])['items'];
    eq(array_column($c,'id'),['meine-cloud','meine-cloud-2','ueber-groesse']);
    eq($c[0]['note'],'Notiz');eq($c[0]['check'],true,'Standard: prüfen');eq($c[1]['kind'],'other');eq($c[1]['check'],false);
    $many=[];for($i=0;$i<80;$i++)$many[]=['name'=>"D$i",'url'=>"https://x$i.example.org"];eq(count(rrw_services_clean(['items'=>$many])['items']),30,'höchstens 30');
    eq(rrw_services_clean('murks'),['items'=>[]]);eq(mb_strlen(rrw_services_clean(['items'=>[['name'=>str_repeat('n',200),'url'=>'/a']]])['items'][0]['name']),60);
});
t('Zustände aus HTTP-Code und Fehler',function(){
    eq(rrw_services_state(200,0)[0],'online');eq(rrw_services_state(301,0),['online','erreichbar (Weiterleitung)']);eq(rrw_services_state(403,0)[0],'online');
    eq(rrw_services_state(500,0),['offline','Fehler HTTP 500']);eq(rrw_services_state(0,6)[1],'Adresse nicht auflösbar');eq(rrw_services_state(0,28)[1],'Zeitüberschreitung');
});
t('Prüfung: übersprungen, gesperrt (nicht öffentlich), online/offline über die Abfrage',function(){
    $items=rrw_services_clean(['items'=>[
        ['name'=>'Aus','url'=>'https://aus.example.org','check'=>false],
        ['name'=>'Lokal','url'=>'http://127.0.0.1/status'],['name'=>'Privat','url'=>'http://10.0.0.5/'],
        ['name'=>'Gut','url'=>'https://gut.example.org'],['name'=>'Kaputt','url'=>'https://kaputt.example.org'],['name'=>'Pfad','url'=>'/hilfe/'],
    ]])['items'];
    $asked=[];
    $r=rrw_services_probe($items,'https://www.beispiel.de',5,function(string $u) use(&$asked){ $asked[]=$u;return str_contains($u,'kaputt')?[503,0,12]:[200,0,34]; });
    $by=[];foreach($r as $x)$by[$x['id']]=$x;
    eq($by['aus']['state'],'skipped');eq($by['lokal']['state'],'blocked');eq($by['privat']['state'],'blocked');
    eq($by['gut']['state'],'online');eq($by['gut']['ms'],34);eq($by['kaputt']['state'],'offline');eq($by['kaputt']['http'],503);
    eq($by['pfad']['state'],'online');
    sort($asked);eq($asked,['https://gut.example.org','https://kaputt.example.org','https://www.beispiel.de/hilfe/'],'nur öffentliche, zu prüfende Adressen werden abgefragt');
});
t('Reihenfolge und Felder der Ergebnisse bleiben erhalten',function(){
    $items=rrw_services_clean(['items'=>[['name'=>'B','url'=>'https://b.example.org','kind'=>'api','note'=>'n'],['name'=>'A','url'=>'https://a.example.org']]])['items'];
    $r=rrw_services_probe($items,'https://x.example.org',5,fn($u)=>[204,0,1]);
    eq(array_column($r,'name'),['B','A']);eq($r[0]['kind'],'api');eq($r[0]['note'],'n');eq(array_keys($r[0]),['id','name','url','kind','note','configured','check','state','message','http','ms']);
});
echo "\n".($n-$fail)." von $n Prüfungen bestanden\n";
exit($fail?1:0);

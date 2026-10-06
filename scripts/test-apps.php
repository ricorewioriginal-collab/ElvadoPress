<?php
// Prüft die Update-Sicherheit der App-Verwaltung (cms/lib/apps.php): Aufruf  php scripts/test-apps.php
declare(strict_types=1);
if(!defined('RRW_DATA_DIR')){$td=sys_get_temp_dir().'/apps-data-'.bin2hex(random_bytes(4));mkdir($td,0777,true);define('RRW_DATA_DIR',$td);register_shutdown_function(fn()=>system('rm -rf '.escapeshellarg($td)));}   // Zwischenspeicher nicht im echten cms/data anlegen
require_once __DIR__.'/../cms/lib/publish.php';
require_once __DIR__.'/../cms/lib/apps.php';
$fail=0;$n=0;
function t(string $name,callable $fn){global $fail,$n;$n++;try{$fn();echo "  ok  $name\n";}catch(Throwable $e){$fail++;echo "FAIL  $name: ".$e->getMessage()."\n";}}
function eq($a,$b,string $m=''){if($a!==$b)throw new RuntimeException(($m?$m.': ':'').'erwartet '.json_encode($b).', war '.json_encode($a));}

$root=sys_get_temp_dir().'/apps-test-'.bin2hex(random_bytes(4));mkdir($root.'/downloads',0777,true);
$apk=str_repeat('A',2_000_000);file_put_contents($root.'/downloads/App.apk',$apk);$sha=hash('sha256',$apk);
$CERT=str_repeat('ab',32);
function build(string $root,array $over=[]): void {
    global $sha,$CERT;
    $d=array_merge(['version'=>'3.1.0','version_code'=>120,'package'=>'de.ricorewi.radio.dev','filename'=>'App.apk','signing'=>'stable','size_bytes'=>2_000_000,'sha256'=>$sha,'cert_sha256'=>$CERT,'built_at'=>'2026-10-04T10:00:00Z'],$over);
    file_put_contents($root.'/downloads/android-latest.json',json_encode($d));
}
function site(array $entry=[]): array { return ['apps'=>['android_enabled'=>true,'windows_enabled'=>true,'managed'=>['ricorewi-radio:android'=>$entry]]]; }
$brand=['brand'=>'ricorewi-radio','origin'=>'https://www.ricorewi-radio.de','directory'=>false];
function pub(string $root,array $site,array $brand,string $ver='3.0.0',int $code=0): array { return rrw_apps_public(rrw_apps_clean_site($site),$root,$brand,'android',$ver,'',str_repeat('s',32),$code); }
function rrw_apps_clean_site(array $site): array { $site['apps']=rrw_apps_clean($site['apps']);return $site; }

t('Gesunder, stabil signierter Build wird angeboten (mit URL, Paket, Code)',function() use($root,$brand){
    build($root);$o=pub($root,site(),$brand)['update'];
    eq($o['available'],true);eq($o['url'],'https://www.ricorewi-radio.de/downloads/App.apk');eq($o['package'],'de.ricorewi.radio.dev');eq($o['latest_code'],120);
});
t('Debug-Signatur: kein Update, keine URL (Installation würde scheitern)',function() use($root,$brand){
    build($root,['signing'=>'debug']);$o=pub($root,site(),$brand)['update'];eq($o['available'],false);eq($o['url'],'');
});
t('Prüfsumme weicht ab (Datei verändert): wird nicht angeboten, Gesundheitsprüfung meldet es',function() use($root,$brand,$sha){
    build($root,['sha256'=>str_repeat('0',64)]);$o=pub($root,site(),$brand)['update'];eq($o['available'],false);eq($o['url'],'');
    $m=rrw_apps_meta($root,'ricorewi-radio','ricorewi-radio','android');$codes=array_column(rrw_apps_health($m,rrw_apps_entry(site(),'ricorewi-radio','android'),'android'),'code');
    eq(in_array('sha',$codes,true),true,'Fehler sha');
});
t('Datei fehlt / falsche Größe: nicht angeboten',function() use($root,$brand){
    build($root,['size_bytes'=>1234]);eq(pub($root,site(),$brand)['update']['available'],false);
    build($root,['filename'=>'gibtsnicht.apk']);eq(pub($root,site(),$brand)['update']['url'],'');
});
t('Zertifikat-Pin: gleiches Zertifikat wird angeboten, anderes nicht',function() use($root,$brand,$CERT){
    build($root);eq(pub($root,site(['cert_pin'=>$CERT]),$brand)['update']['available'],true,'passend');
    build($root,['cert_sha256'=>str_repeat('cd',32)]);$o=pub($root,site(['cert_pin'=>$CERT]),$brand)['update'];eq($o['available'],false,'abweichend');eq($o['url'],'');
    $m=rrw_apps_meta($root,'ricorewi-radio','ricorewi-radio','android');$h=rrw_apps_health($m,rrw_apps_entry(site(['cert_pin'=>$CERT]),'ricorewi-radio','android'),'android');
    eq(in_array('cert_changed',array_column($h,'code'),true),true);
});
t('Mindestversion über der neuesten Version sperrt niemanden aus',function() use($root,$brand){
    build($root);$r=pub($root,site(['min_version'=>'9.0.0']),$brand);eq($r['update']['required'],false);
    eq(pub($root,site(['min_version'=>'3.1.0']),$brand)['update']['required'],true,'erreichbare Mindestversion zwingt');
    $m=rrw_apps_meta($root,'ricorewi-radio','ricorewi-radio','android');
    eq(in_array('min_above_latest',array_column(rrw_apps_health($m,rrw_apps_entry(site(['min_version'=>'9.0.0']),'ricorewi-radio','android'),'android'),'code'),true),true);
});
t('Gesperrte Versionen müssen aktualisieren, andere nicht',function() use($root,$brand){
    build($root);$s=site(['blocked'=>['3.0.1']]);
    eq(pub($root,$s,$brand,'3.0.1')['update']['required'],true);eq(pub($root,$s,$brand,'3.0.2')['update']['required'],false);
    build($root,['version'=>'3.0.1']);eq(pub($root,$s,$brand,'3.0.1')['update']['required'],false,'ohne neuere Version kein Zwang');
});
t('Gleicher Versionsname, neuerer Build: Update, wenn die App ihren Code schickt',function() use($root,$brand){
    build($root,['version'=>'3.1.0-dev','version_code'=>130]);
    eq(pub($root,site(),$brand,'3.1.0-dev',129)['update']['available'],true);eq(pub($root,site(),$brand,'3.1.0-dev',130)['update']['available'],false);eq(pub($root,site(),$brand,'3.1.0-dev',0)['update']['available'],false,'ohne Code wie bisher');
});
t('Update-Text kommt an; Bereinigung von Pin, Sperrliste und Text',function() use($root,$brand){
    build($root);eq(pub($root,site(['notes'=>'<b>Neu</b> Dunkelmodus']),$brand)['update']['notes'],'Neu Dunkelmodus');
    $c=rrw_apps_clean(['managed'=>['ricorewi-radio:android'=>['cert_pin'=>'../../x','blocked'=>'3.0.1, 3.0.2;x 3.0.1 <script>','notes'=>str_repeat('x',900)]]])['managed']['ricorewi-radio:android'];
    eq($c['cert_pin'],'');eq($c['blocked'],['3.0.1','3.0.2']);eq(mb_strlen($c['notes']),600);
    eq(rrw_apps_clean(['managed'=>['ricorewi-radio:android'=>['cert_pin'=>strtoupper(str_repeat('ab',32))]]])['managed']['ricorewi-radio:android']['cert_pin'],str_repeat('ab',32),'Großschreibung wird normalisiert');
});
t('Änderungsprotokoll fasst Änderungen zusammen (und schweigt bei keiner)',function(){
    $a=rrw_apps_clean(['managed'=>['ricorewi-radio:android'=>['rollout'=>100]]]);$b=rrw_apps_clean(['managed'=>['ricorewi-radio:android'=>['rollout'=>20,'maintenance'=>['enabled'=>true],'blocked'=>['3.0.1']]]]);
    $s=rrw_apps_change_summary('apps',$a,$b);foreach(['Rollout 100 % → 20 %','Wartungsmodus an','3.0.1'] as $x)if(strpos($s,$x)===false)throw new RuntimeException("fehlt '$x' in: $s");
    eq(rrw_apps_change_summary('apps',$a,$a),'');
});
t('App-Builder: eigene Sender (https-Stream) werden bereinigt und ausgeliefert',function(){
    $bld=fn($custom)=>rrw_apps_clean(['managed'=>['meinradio:android'=>['builder'=>['enabled'=>true,'stations'=>['order'=>['aa'],'custom'=>$custom]]]]])['managed']['meinradio:android']['builder']['stations'];
    $c=$bld([
        ['title'=>'Mein Stream','stream'=>'https://stream.example.org/live.mp3','logo'=>'https://example.org/l.png'],
        ['title'=>'Ohne https','stream'=>'http://stream.example.org/live'],
        ['title'=>'','stream'=>'https://stream.example.org/x'],
        ['title'=>'Mein Stream','stream'=>'https://stream.example.org/zwei'],   // gleiche Kennung: entfällt
        ['id'=>'../x','title'=>'Böse','stream'=>'https://stream.example.org/y'],
        'kein Array',
    ])['custom'];
    eq(count($c),1);eq($c[0]['id'],'mein-stream');eq($c[0]['stream'],'https://stream.example.org/live.mp3');eq($c[0]['logo'],'https://example.org/l.png');
    eq(count($bld(array_fill(0,40,['title'=>'xx','stream'=>'https://a.example.org/s']))['custom']),1,'doppelte Kennungen');
    $many=[];for($i=0;$i<40;$i++)$many[]=['title'=>"Sender $i",'stream'=>"https://a.example.org/$i"];eq(count($bld($many)['custom']),20,'höchstens 20');
});
t('App-Builder: ohne eigene Sender bleibt die Ausgabe unverändert (kein custom-Schlüssel)',function(){
    $st=rrw_apps_clean(['managed'=>['meinradio:android'=>['builder'=>['enabled'=>true,'stations'=>['order'=>['aa'],'hidden'=>['bb']]]]]])['managed']['meinradio:android']['builder']['stations'];
    eq($st,['order'=>['aa'],'hidden'=>['bb']]);
});
// ---- Eigene Apps des Build-Assistenten im laufenden Betrieb verwalten (Hinweis, Wartung, Funktionen, Layout) – auch im eigenständigen CMS
$dd=$root.'/data';@mkdir($dd.'/.apps',0777,true);
file_put_contents($dd.'/.apps/build.json',json_encode(['repo'=>'me/x','brands'=>[
    ['id'=>'meinshop','appName'=>'Mein Shop','type'=>'web','platforms'=>['android','windows'],'site'=>'https://shop.example'],
    ['id'=>'meinradio','appName'=>'Mein Radio','type'=>'radio','platforms'=>['android'],'site'=>'https://radio.example'],
    ['id'=>'xx','appName'=>'zu kurz','type'=>'web','platforms'=>['android'],'site'=>'https://x.example']]]));
t('Eigene Apps werden aus dem Build-Assistenten gelesen (ungültige Kennungen entfallen)',function() use($dd){
    $own=rrw_apps_own($dd);eq(array_keys($own),['meinshop','meinradio']);eq($own['meinshop']['platforms'],['android','windows']);eq($own['meinradio']['type'],'radio');
});
t('Übersicht im eigenständigen Betrieb: nur eigene Apps, je Plattform eine Zeile, mit Typ',function() use($dd,$root){
    $ov=rrw_apps_overview(['apps'=>[]],$root,rrw_apps_own($dd),true);
    eq(array_map(fn($r)=>$r['brand'].':'.$r['platform'],$ov['items']),['meinshop:android','meinshop:windows','meinradio:android']);
    eq($ov['items'][0]['own'],true);eq($ov['items'][0]['type'],'web');eq($ov['items'][2]['type'],'radio');eq($ov['items'][0]['origin'],'https://shop.example');
});
t('Übersicht mit Hersteller-Marken: eigene Apps kommen zusätzlich dazu',function() use($dd,$root){
    $ov=rrw_apps_overview(['apps'=>[]],$root,rrw_apps_own($dd),false);$own=array_filter($ov['items'],fn($r)=>$r['own']);
    eq(count($own),3);eq(count($ov['items'])>3,true);
});
t('app_config?brand=<eigene App>: Hinweis und Wartung dieser App, nicht der Hauptmarke',function() use($dd,$root){
    $own=rrw_apps_own($dd);$b=rrw_apps_own_brand($own,'MeinShop');eq($b['brand'],'meinshop');
    eq(rrw_apps_own_brand($own,'unbekannt'),null);eq(rrw_apps_own_brand($own,'../x'),null);
    $site=['apps'=>rrw_apps_clean(['managed'=>[
        'meinshop:android'=>['notice'=>['enabled'=>true,'level'=>'warn','title'=>'Hallo','text'=>'Neuer Katalog'],'maintenance'=>['enabled'=>true,'title'=>'','text'=>'']],
        'meinradio:android'=>['notice'=>['enabled'=>false]]]])];
    $o=rrw_apps_public($site,$root,$b,'android','1.0.0','',str_repeat('s',32));
    eq($o['brand'],'meinshop');eq($o['notice']['title'],'Hallo');eq($o['maintenance']['title'],'Wartungsarbeiten');eq($o['update']['available'],false);eq($o['update']['required'],false);
    $o2=rrw_apps_public($site,$root,rrw_apps_own_brand($own,'meinradio'),'android','1.0.0','',str_repeat('s',32));eq($o2['notice'],null);eq($o2['maintenance'],null);eq($o2['features']['directory'],false);
});
echo "\n".($n-$fail)." von $n Prüfungen bestanden\n";
exec('rm -rf '.escapeshellarg($root));
exit($fail?1:0);

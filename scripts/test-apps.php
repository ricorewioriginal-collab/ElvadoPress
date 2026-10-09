<?php
// Prüft die Update-Sicherheit der App-Verwaltung (cms/lib/apps.php): Aufruf  php scripts/test-apps.php
declare(strict_types=1);
if(!defined('ELVADO_DATA_DIR')){$td=sys_get_temp_dir().'/apps-data-'.bin2hex(random_bytes(4));mkdir($td,0777,true);define('ELVADO_DATA_DIR',$td);register_shutdown_function(fn()=>system('rm -rf '.escapeshellarg($td)));}   // Zwischenspeicher nicht im echten cms/data anlegen
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
    $d=array_merge(['version'=>'3.1.0','version_code'=>120,'package'=>'de.beispiel.app.dev','filename'=>'App.apk','signing'=>'stable','size_bytes'=>2_000_000,'sha256'=>$sha,'cert_sha256'=>$CERT,'built_at'=>'2026-10-04T10:00:00Z'],$over);
    file_put_contents($root.'/downloads/android-latest.json',json_encode($d));
}
function site(array $entry=[]): array { return ['apps'=>['android_enabled'=>true,'windows_enabled'=>true,'managed'=>['portalapp:android'=>$entry]]]; }
$brand=['brand'=>'portalapp','origin'=>'https://www.beispiel.de','directory'=>false];
function pub(string $root,array $site,array $brand,string $ver='3.0.0',int $code=0): array { return elvado_apps_public(elvado_apps_clean_site($site),$root,$brand,'android',$ver,'',str_repeat('s',32),$code); }
function elvado_apps_clean_site(array $site): array { $site['apps']=elvado_apps_clean($site['apps']);return $site; }

t('Gesunder, stabil signierter Build wird angeboten (mit URL, Paket, Code)',function() use($root,$brand){
    build($root);$o=pub($root,site(),$brand)['update'];
    eq($o['available'],true);eq($o['url'],'https://www.beispiel.de/downloads/App.apk');eq($o['package'],'de.beispiel.app.dev');eq($o['latest_code'],120);
});
t('Debug-Signatur: kein Update, keine URL (Installation würde scheitern)',function() use($root,$brand){
    build($root,['signing'=>'debug']);$o=pub($root,site(),$brand)['update'];eq($o['available'],false);eq($o['url'],'');
});
t('Prüfsumme weicht ab (Datei verändert): wird nicht angeboten, Gesundheitsprüfung meldet es',function() use($root,$brand,$sha){
    build($root,['sha256'=>str_repeat('0',64)]);$o=pub($root,site(),$brand)['update'];eq($o['available'],false);eq($o['url'],'');
    $m=elvado_apps_meta($root,'portalapp','portalapp','android');$codes=array_column(elvado_apps_health($m,elvado_apps_entry(site(),'portalapp','android'),'android'),'code');
    eq(in_array('sha',$codes,true),true,'Fehler sha');
});
t('Datei fehlt / falsche Größe: nicht angeboten',function() use($root,$brand){
    build($root,['size_bytes'=>1234]);eq(pub($root,site(),$brand)['update']['available'],false);
    build($root,['filename'=>'gibtsnicht.apk']);eq(pub($root,site(),$brand)['update']['url'],'');
});
t('Zertifikat-Pin: gleiches Zertifikat wird angeboten, anderes nicht',function() use($root,$brand,$CERT){
    build($root);eq(pub($root,site(['cert_pin'=>$CERT]),$brand)['update']['available'],true,'passend');
    build($root,['cert_sha256'=>str_repeat('cd',32)]);$o=pub($root,site(['cert_pin'=>$CERT]),$brand)['update'];eq($o['available'],false,'abweichend');eq($o['url'],'');
    $m=elvado_apps_meta($root,'portalapp','portalapp','android');$h=elvado_apps_health($m,elvado_apps_entry(site(['cert_pin'=>$CERT]),'portalapp','android'),'android');
    eq(in_array('cert_changed',array_column($h,'code'),true),true);
});
t('Mindestversion über der neuesten Version sperrt niemanden aus',function() use($root,$brand){
    build($root);$r=pub($root,site(['min_version'=>'9.0.0']),$brand);eq($r['update']['required'],false);
    eq(pub($root,site(['min_version'=>'3.1.0']),$brand)['update']['required'],true,'erreichbare Mindestversion zwingt');
    $m=elvado_apps_meta($root,'portalapp','portalapp','android');
    eq(in_array('min_above_latest',array_column(elvado_apps_health($m,elvado_apps_entry(site(['min_version'=>'9.0.0']),'portalapp','android'),'android'),'code'),true),true);
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
    $c=elvado_apps_clean(['managed'=>['portalapp:android'=>['cert_pin'=>'../../x','blocked'=>'3.0.1, 3.0.2;x 3.0.1 <script>','notes'=>str_repeat('x',900)]]])['managed']['portalapp:android'];
    eq($c['cert_pin'],'');eq($c['blocked'],['3.0.1','3.0.2']);eq(mb_strlen($c['notes']),600);
    eq(elvado_apps_clean(['managed'=>['portalapp:android'=>['cert_pin'=>strtoupper(str_repeat('ab',32))]]])['managed']['portalapp:android']['cert_pin'],str_repeat('ab',32),'Großschreibung wird normalisiert');
});
t('Änderungsprotokoll fasst Änderungen zusammen (und schweigt bei keiner)',function(){
    $a=elvado_apps_clean(['managed'=>['portalapp:android'=>['rollout'=>100]]]);$b=elvado_apps_clean(['managed'=>['portalapp:android'=>['rollout'=>20,'maintenance'=>['enabled'=>true],'blocked'=>['3.0.1']]]]);
    $s=elvado_apps_change_summary('apps',$a,$b);foreach(['Rollout 100 % → 20 %','Wartungsmodus an','3.0.1'] as $x)if(strpos($s,$x)===false)throw new RuntimeException("fehlt '$x' in: $s");
    eq(elvado_apps_change_summary('apps',$a,$a),'');
});
t('Inhalte der App: nur Tab-Leiste und Kopf/Fuß werden gespeichert, Radio-Builder-Felder entfallen',function(){
    $b=elvado_apps_clean(['managed'=>['meinshop:android'=>['builder'=>['enabled'=>true,'stations'=>['order'=>['aa']],'home'=>[['type'=>'hero']],'theme'=>['accent'=>'#ff0000'],'tabs'=>[['title'=>'Start','icon'=>'home','url'=>'/']],'chrome'=>'hide']]]])['managed']['meinshop:android']['builder'];
    eq(array_keys($b),['tabs','chrome']);eq($b['chrome'],'hide');eq(count($b['tabs']),1);
});
// ---- Eigene Apps des Build-Assistenten im laufenden Betrieb verwalten (Hinweis, Wartung, Funktionen, Layout) – auch im eigenständigen CMS
$dd=$root.'/data';@mkdir($dd.'/.apps',0777,true);
file_put_contents($dd.'/.apps/build.json',json_encode(['repo'=>'me/x','brands'=>[
    ['id'=>'meinshop','appName'=>'Mein Shop','type'=>'web','platforms'=>['android','windows'],'site'=>'https://shop.example'],
    ['id'=>'meinradio','appName'=>'Mein Radio','type'=>'radio','platforms'=>['android'],'site'=>'https://radio.example'],   // alter Typ „radio“ gilt als Website-App
    ['id'=>'xx','appName'=>'zu kurz','type'=>'web','platforms'=>['android'],'site'=>'https://x.example']]]));
t('Eigene Apps werden aus dem Build-Assistenten gelesen (ungültige Kennungen entfallen)',function() use($dd){
    $own=elvado_apps_own($dd);eq(array_keys($own),['meinshop','meinradio']);eq($own['meinshop']['platforms'],['android','windows']);eq($own['meinradio']['type'],'web');
});
t('Übersicht im eigenständigen Betrieb: nur eigene Apps, je Plattform eine Zeile, mit Typ',function() use($dd,$root){
    $ov=elvado_apps_overview(['apps'=>[]],$root,elvado_apps_own($dd));
    eq(array_map(fn($r)=>$r['brand'].':'.$r['platform'],$ov['items']),['meinshop:android','meinshop:windows','meinradio:android']);
    eq($ov['items'][0]['own'],true);eq($ov['items'][0]['type'],'web');eq($ov['items'][2]['type'],'web');eq($ov['items'][0]['origin'],'https://shop.example');
});
t('app_config?brand=<eigene App>: Hinweis und Wartung dieser App, nicht der Hauptmarke',function() use($dd,$root){
    $own=elvado_apps_own($dd);$b=elvado_apps_own_brand($own,'MeinShop');eq($b['brand'],'meinshop');
    eq(elvado_apps_own_brand($own,'unbekannt'),null);eq(elvado_apps_own_brand($own,'../x'),null);
    $site=['apps'=>elvado_apps_clean(['managed'=>[
        'meinshop:android'=>['notice'=>['enabled'=>true,'level'=>'warn','title'=>'Hallo','text'=>'Neuer Katalog'],'maintenance'=>['enabled'=>true,'title'=>'','text'=>'']],
        'meinradio:android'=>['notice'=>['enabled'=>false]]]])];
    $o=elvado_apps_public($site,$root,$b,'android','1.0.0','',str_repeat('s',32));
    eq($o['brand'],'meinshop');eq($o['notice']['title'],'Hallo');eq($o['maintenance']['title'],'Wartungsarbeiten');eq($o['update']['available'],false);eq($o['update']['required'],false);
    $o2=elvado_apps_public($site,$root,elvado_apps_own_brand($own,'meinradio'),'android','1.0.0','',str_repeat('s',32));eq($o2['notice'],null);eq($o2['maintenance'],null);eq(array_keys($o2['features']),['assistant']);
});
t('Baukasten-App: Tab-Leiste wird bereinigt und über app_config geliefert',function() use($root){
    $tabs=elvado_apps_tabs_clean([['title'=>'Start','icon'=>'home','url'=>'/'],['title'=>'Shop','icon'=>'unbekannt','url'=>'https://shop.example.org/x'],
        ['title'=>'Böse','icon'=>'star','url'=>'javascript:alert(1)'],['title'=>'Protokoll','icon'=>'star','url'=>'//evil.example/'],['title'=>'','icon'=>'star','url'=>'/leer/'],
        ['title'=>str_repeat('x',40),'icon'=>'info','url'=>'/lang/'],['title'=>'6','icon'=>'info','url'=>'/6/'],['title'=>'7','icon'=>'info','url'=>'/7/']]);
    eq(count($tabs),5);eq($tabs[0]['url'],'/');eq($tabs[1]['icon'],'star');eq(mb_strlen($tabs[2]['title']),16);
    $site=['apps'=>elvado_apps_clean(['managed'=>['meinshop:android'=>['builder'=>['tabs'=>[['title'=>'A','icon'=>'home','url'=>'/'],['title'=>'B','icon'=>'shop','url'=>'/shop/']]]]]])];
    $o=elvado_apps_public($site,$root,['brand'=>'meinshop'],'android','1.0.0','',str_repeat('s',32));
    eq(count($o['tabs']),2);eq($o['tabs'][1]['url'],'/shop/');
    eq(elvado_apps_public(['apps'=>elvado_apps_clean([])],$root,['brand'=>'meinshop'],'android','1.0.0','',str_repeat('s',32))['tabs'],[]);
});
t('App-Modus: Erkennung am User-Agent, Einstellung je App, Body-Klasse und Stile',function() use($root){
    require_once __DIR__.'/../cms/lib/appmode.php';
    $_GET=[];$_COOKIE=[];$_SERVER['HTTP_USER_AGENT']='Mozilla/5.0';eq(elvado_appmode_detect(),null);
    $_SERVER['HTTP_USER_AGENT']='Mozilla/5.0 (Linux) ElvadoPressApp/1.0 (brand=meinshop; platform=windows)';$a=elvado_appmode_detect();eq($a['brand'],'meinshop');eq($a['platform'],'windows');
    $_SERVER['HTTP_USER_AGENT']='ElvadoPressApp/1.0 (brand=../x; platform=android)';eq(elvado_appmode_detect(),null);
    $_SERVER['HTTP_USER_AGENT']='ElvadoPressApp/1.0';eq(elvado_appmode_detect(),null);   // Hintergrundabfragen der App tragen keine Marke
    $own=['meinshop'=>['type'=>'content'],'meinweb'=>['type'=>'web']];$site=['apps'=>['managed'=>[]]];
    eq(elvado_appmode_hide(['brand'=>'meinshop','platform'=>'android'],$own,$site),true);   // Baukasten-App: automatisch ausblenden
    eq(elvado_appmode_hide(['brand'=>'meinweb','platform'=>'android'],$own,$site),false);   // Website-App: unverändert
    eq(elvado_appmode_hide(['brand'=>'fremd','platform'=>'android'],$own,$site),false);     // unbekannte Marke: nichts ändern
    $site['apps']['managed']['meinweb:android']['builder']['chrome']='hide';eq(elvado_appmode_hide(['brand'=>'meinweb','platform'=>'android'],$own,$site),true);
    $site['apps']['managed']['meinshop:android']['builder']['chrome']='keep';eq(elvado_appmode_hide(['brand'=>'meinshop','platform'=>'android'],$own,$site),false);
    eq(elvado_apps_builder_clean(['chrome'=>'quatsch'])['chrome'],'auto');eq(elvado_apps_builder_clean(['chrome'=>'hide'])['chrome'],'hide');
    $h=elvado_appmode_inject('<html><head><title>x</title></head><body id="a" class="home blog"><header class="site-header"></header></body></html>',true);
    eq(str_contains($h,'<body id="a" class="home blog elvado-app">'),true);eq(str_contains($h,'.site-header'),true);eq(strpos($h,'elvado-app-css')<strpos($h,'</head>'),true);
    eq(str_contains(elvado_appmode_inject('<html><head></head><body><p>x</p></body></html>',true),'<body class="elvado-app">'),true);
});
echo "\n".($n-$fail)." von $n Prüfungen bestanden\n";
exec('rm -rf '.escapeshellarg($root));
exit($fail?1:0);

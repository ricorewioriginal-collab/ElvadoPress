<?php
// Prüft den Alexa-Skill-Baukasten: eigene Sender des Betreibers, Überschreibungen, öffentliche Skill-Konfiguration, Sprachmodell
// und – falls node vorhanden – die Sendeplan-Berechnung im Skill-Backend. Aufruf: php scripts/test-alexa.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/rrw-alx-'.bin2hex(random_bytes(4));mkdir($tmp);
define('RRW_DATA_DIR',$tmp);
require __DIR__.'/../cms/lib/system.php';require __DIR__.'/../cms/lib/alexa.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
$site=['portal'=>['site_name'=>'Mein Radio'],'alexa'=>['stations'=>[
 'ice'=>['enabled'=>true,'title'=>'Ice Radio','stream'=>'https://ice.example/live'],
 'testfm'=>['enabled'=>true,'title'=>'Test FM'],
],'order'=>['ice','testfm']]];$GLOBALS['RRW_SITE']=$site;
t('Baukasten-Modus ist immer aktiv',rrw_alexa_neutral());
$defs=rrw_alexa_station_defs($site);
t('Senderliste: nur vom Betreiber hinzugefügte Sender, Reihenfolge, Titel',array_keys($defs)===['ice','testfm']&&$defs['ice']['title']==='Ice Radio'&&$defs['ice']['enabled']);
$site2=['alexa'=>['stations'=>['ice'=>['enabled'=>false,'title'=>'Eis','extra'=>['eis radio'],'stream'=>'https://eis.example/x']],'order'=>['ice']]];
$d2=rrw_alexa_station_defs($site2);
t('Überschreibung: Name, Stream, ausgeschaltet',$d2['ice']['title']==='Eis'&&!$d2['ice']['enabled']&&$d2['ice']['stream']==='https://eis.example/x'&&in_array('eis radio',$d2['ice']['extra'],true));
$pub=rrw_alexa_public($site,$tmp,'https://radio.example');
$st=array_column($pub['stations'],null,'id');
t('Öffentlich: Stream nur bei eigenen Streams',$st['ice']['stream']==='https://ice.example/live'&&!isset($st['testfm']['stream']));
t('Öffentlich: keine Verweise auf ein Radio-Erweiterungs-API',!array_key_exists('radio_api',$pub)&&!isset($st['ice']['radio']));
t('Startsender ist ein aktiver Sender',in_array($pub['default'],['ice','testfm'],true));
$m=rrw_alexa_model($site);$vals=array_column($m['interactionModel']['languageModel']['types'][0]['values'],null,'id');
t('Sprachmodell kennt die Sender',isset($vals['ice'],$vals['testfm'])&&$vals['ice']['name']['value']==='ice');
t('Modell ändert sich mit den Sendern (Neu-Einspielen-Hinweis)',rrw_alexa_model_rev($site)!==rrw_alexa_model_rev($site2));
t('Keine Radio-Verdrahtung mehr in der Bereinigung',!array_key_exists('radio_sync',rrw_alexa_clean(['radio_sync'=>1])));
if(trim((string)shell_exec('command -v node'))!==''){
    $js=<<<'JS'
const M=require('module'),orig=M._load;const chain=new Proxy({}, {get:(t,k)=>k==='lambda'?()=>()=>{}:()=>chain});
M._load=function(r,...a){ if(r==='ask-sdk-core')return {SkillBuilders:{custom:()=>chain},getRequestType:()=>'',getIntentName:()=>''}; if(r==='./cms.json')return {base:'https://x.example',token:'t'}; if(r==='./fallback.json')return {status:'ok',stations:[{id:'a',title:'A',enabled:true}],texts:{},stream_url:'https://s/{id}'}; return orig.call(this,r,...a); };
const sk=require(process.argv[2]);const i=sk.__internals;
console.log(typeof i.showsAt==='function'&&typeof i.dayShows==='function'&&typeof i.berlinNow==='function'&&!('radioToRaw' in i)?'OK':'FALSCH');
JS;
    $f=$tmp.'/t.js';file_put_contents($f,$js);
    $out=trim((string)shell_exec('node '.escapeshellarg($f).' '.escapeshellarg(realpath(__DIR__.'/../cms/lib/alexa-skill/lambda/index.js')).' 2>&1'));
    t('Skill-Backend lädt, Sendeplan-Funktionen vorhanden, kein Radio-Erweiterungs-Code',$out==='OK',$out);
}
$api=(string)file_get_contents(__DIR__.'/../cms/api.php');$ui=(string)file_get_contents(__DIR__.'/../cms/assets/alexa-manager.js');
t('Skill-Icon-Upload: Endpunkt vorhanden und nur für Admins',str_contains($api,"alexa_icon_upload") && preg_match("/alexa_icon_upload'\)\{\s*rrw_auth\(true\)/",$api)===1);
t('Skill-Icon-Upload: Oberfläche ruft den Endpunkt auf',str_contains($ui,'alexa_icon_upload') && str_contains($ui,'uploadIcon'));
system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

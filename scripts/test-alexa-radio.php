<?php
// Prüft die Verdrahtung Radio-Theme ↔ Alexa-Skill: Sender aus dem Radio-Menü werden Skill-Sender, https-Pflicht, Schalter radio_sync,
// Sprachmodell, öffentliche Skill-Konfiguration (radio_api) und – falls node vorhanden – die Sendeplan-Umrechnung im Skill-Backend. Aufruf: php scripts/test-alexa-radio.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/rrw-alx-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/.wp');
define('RRW_DATA_DIR',$tmp);
require __DIR__.'/../cms/lib/system.php';require __DIR__.'/../cms/lib/alexa.php';require __DIR__.'/../cms/lib/radio.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
$site=['portal'=>['site_name'=>'Mein Radio'],'alexa'=>[]];$GLOBALS['RRW_SITE']=$site;
t('Baukasten-Modus (kein Paket)',rrw_alexa_neutral());
t('Ohne Radio-Konfiguration: keine Verdrahtung',!rrw_alexa_radio_sync($site)&&rrw_alexa_radio_defs($site)===[]);
rrw_radio_save($tmp,['stations'=>[
 ['id'=>'main','name'=>'Test FM','source'=>'lautfm','lautfm_id'=>'testfm'],
 ['id'=>'ice','name'=>'Ice Radio','source'=>'icecast','status_url'=>'https://ice.example/status-json.xsl','mount'=>'/live','stream_url'=>'https://ice.example/live'],
 ['id'=>'alt','name'=>'Altmodisch','source'=>'shoutcast','status_url'=>'http://old.example','stream_url'=>'http://old.example/stream'],
 ['id'=>'stat','name'=>'Nur Stream','source'=>'static','stream_url'=>'https://stat.example/s'],
],'default'=>'ice','schedule'=>[['day'=>1,'from'=>'08:00','to'=>'10:30','title'=>'Morgen']]]);
t('Radio-Sender eingerichtet, Theme inaktiv: aus (Bestand bleibt)',!rrw_alexa_radio_sync($site));
t('Schalter radio_sync=true schaltet ein',rrw_alexa_radio_sync(['alexa'=>['radio_sync'=>true]]));
// Theme aktivieren
touch($tmp.'/.wp/front-on');file_put_contents($tmp.'/.wp/options.json',json_encode(['stylesheet'=>['v'=>serialize('elvado-radio')]]));
t('Radio-Theme aktiv → Verdrahtung an',rrw_alexa_radio_sync($site));
t('Schalter radio_sync=false hat Vorrang',!rrw_alexa_radio_sync(['alexa'=>['radio_sync'=>false]])&&rrw_alexa_station_defs(['alexa'=>['radio_sync'=>false]])===[]);
$sk=[];$d=rrw_alexa_radio_defs($site,$sk);
t('Standardsender zuerst, laut.fm über Kennung',array_keys($d)===['ice','testfm','stat']);
t('laut.fm: kein eigener Stream (Titel/Sendeplan wie bisher)',!isset($d['testfm']['stream'])&&!isset($d['testfm']['radio']));
t('Eigener https-Stream mit Radio-Verweis',$d['ice']['stream']==='https://ice.example/live'&&$d['ice']['radio']==='ice'&&$d['stat']['radio']==='stat');
t('http-Stream wird übersprungen und gemeldet',!isset($d['alt'])&&count($sk)===1&&$sk[0]['name']==='Altmodisch'&&str_contains($sk[0]['reason'],'https'));
$defs=rrw_alexa_station_defs($site);
t('Senderliste im Skill: Reihenfolge, Titel, aktiv',array_keys($defs)===['ice','testfm','stat']&&$defs['ice']['title']==='Ice Radio'&&$defs['ice']['enabled']);
// Überschreibungen aus dem Skill-Menü gelten weiter, Stream aber nicht
$site2=['alexa'=>['stations'=>['ice'=>['enabled'=>false,'title'=>'Eis','extra'=>['eis radio'],'stream'=>'https://boese.example/x']]]];
$d2=rrw_alexa_station_defs($site2);
t('Überschreibung: Name, ausgeschaltet; Radio-Stream bleibt',$d2['ice']['title']==='Eis'&&!$d2['ice']['enabled']&&$d2['ice']['stream']==='https://ice.example/live'&&in_array('eis radio',$d2['ice']['extra'],true));
// Öffentliche Konfiguration
$pub=rrw_alexa_public($site,$tmp,'https://radio.example');
$st=array_column($pub['stations'],null,'id');
t('Öffentlich: Stream + radio-Kennung nur bei eigenen Streams',$st['ice']['stream']==='https://ice.example/live'&&$st['ice']['radio']==='ice'&&!isset($st['testfm']['stream']));
t('Öffentlich: radio_api bei https',($pub['radio_api']??'')==='https://radio.example/cms/radio.php');
t('Öffentlich: kein radio_api ohne https',!isset(rrw_alexa_public($site,$tmp,'http://radio.example')['radio_api']));
t('Startsender = Standard des Radios',$pub['default']==='ice');
$pub2=rrw_alexa_public(['alexa'=>['radio_sync'=>false]],$tmp,'https://radio.example');t('Ohne Verdrahtung: nichts Radio-bezogenes',!isset($pub2['radio_api'])&&$pub2['stations']===[]);
// Sprachmodell
$m=rrw_alexa_model($site);$vals=array_column($m['interactionModel']['languageModel']['types'][0]['values'],null,'id');
t('Sprachmodell kennt die Radio-Sender',isset($vals['ice'],$vals['testfm'],$vals['stat'])&&$vals['ice']['name']['value']==='ice radio');
t('Modell ändert sich mit den Sendern (Neu-Einspielen-Hinweis)',rrw_alexa_model_rev($site)!==rrw_alexa_model_rev(['alexa'=>['radio_sync'=>false]]));
// Bereinigung
t('radio_sync wird gespeichert',rrw_alexa_clean(['radio_sync'=>1])['radio_sync']===true&&!array_key_exists('radio_sync',rrw_alexa_clean([])));
// Skill-Backend (Node, mit Attrappen für ask-sdk-core und die Konfigurationsdateien)
if(trim((string)shell_exec('command -v node'))!==''){
    $js=<<<'JS'
const M=require('module'),orig=M._load;const chain=new Proxy({}, {get:(t,k)=>k==='lambda'?()=>()=>{}:()=>chain});
M._load=function(r,...a){ if(r==='ask-sdk-core')return {SkillBuilders:{custom:()=>chain},getRequestType:()=>'',getIntentName:()=>''}; if(r==='./cms.json')return {base:'https://x.example',token:'t'}; if(r==='./fallback.json')return {status:'ok',stations:[{id:'a',title:'A',enabled:true}],texts:{},stream_url:'https://s/{id}'}; return orig.call(this,r,...a); };
const sk=require(process.argv[2]);const i=sk.__internals;
const raw=i.radioToRaw({schedule:[{day:1,from:'08:00',to:'10:30',title:'Morgen'},{day:7,from:'22:00',to:'00:00',title:'Nacht'},{day:9,from:'1:00',to:'2:00',title:'Kaputt'},{day:2,from:'x',to:'y',title:'Kaputt2'},{day:3,from:'06:00',to:'07:00',title:''}]});
const ok=raw.length===2&&raw[0].day==='mon'&&raw[0].hour===8&&raw[0].end_time===11&&raw[1].day==='sun'&&raw[1].hour===22&&raw[1].end_time===0;
console.log(ok?'OK':'FALSCH '+JSON.stringify(raw));
JS;
    $f=$tmp.'/t.js';file_put_contents($f,$js);
    $out=trim((string)shell_exec('node '.escapeshellarg($f).' '.escapeshellarg(realpath(__DIR__.'/../cms/lib/alexa-skill/lambda/index.js')).' 2>&1'));
    t('Skill-Backend: Radio-Sendeplan wird umgerechnet',$out==='OK',$out);
}
system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

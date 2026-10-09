<?php
// Prüft den Alexa-Skill-Baukasten (Website-Skill): Themen des Betreibers, Neuigkeiten, Bereinigung, öffentliche Skill-Konfiguration,
// Sprachmodell, Paket-Platzhalter und – falls node vorhanden – das Skill-Backend. Aufruf: php scripts/test-alexa.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/elvado-alx-'.bin2hex(random_bytes(4));mkdir($tmp);
define('ELVADO_DATA_DIR',$tmp);
require __DIR__.'/../cms/lib/system.php';require __DIR__.'/../cms/lib/alexa.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
$site=['portal'=>['site_name'=>'Rad & Tat'],'alexa'=>['topics'=>[
 'oeffnungszeiten'=>['enabled'=>true,'title'=>'Öffnungszeiten','text'=>'Wir haben Montag bis Freitag von 9 bis 18 Uhr geöffnet.','extra'=>['wann habt ihr auf','Öffnungs-Zeiten']],
 'kontakt'=>['enabled'=>true,'title'=>'Kontakt','text'=>'Du erreichst uns per Mail.'],
 'leer'=>['enabled'=>true,'title'=>'Leer','text'=>''],
 'aus'=>['enabled'=>false,'title'=>'Ausgeschaltet','text'=>'x'],
 '../böse'=>['title'=>'Böse','text'=>'x'],
],'order'=>['kontakt','oeffnungszeiten'],'news'=>['enabled'=>true,'count'=>2]]];$GLOBALS['ELVADO_SITE']=$site;
file_put_contents($tmp.'/news.json',json_encode([
 ['id'=>1,'title'=>'Neuer Katalog','excerpt'=>'Der <b>Katalog</b> ist da.','status'=>'published','published_at'=>'2026-03-01 10:00:00'],
 ['id'=>2,'title'=>'Sommerfest','body_html'=>'<p>Wir feiern am Samstag.</p>','status'=>'published','published_at'=>'2026-04-01 10:00:00'],
 ['id'=>3,'title'=>'Entwurf','status'=>'draft','published_at'=>'2026-05-01 10:00:00'],
 ['id'=>4,'title'=>'Gelöscht','status'=>'published','deleted_at'=>'2026-01-01 00:00:00','published_at'=>'2026-06-01 10:00:00'],
 ['id'=>5,'title'=>'Zukunft','status'=>'published','published_at'=>'2999-01-01 00:00:00'],
 ['id'=>6,'title'=>'Älter','body_html'=>'x','status'=>'published','published_at'=>'2025-01-01 10:00:00'],
]));
$cl=elvado_alexa_clean($site['alexa']);
t('Bereinigung: ungültige Kennungen entfallen, Reihenfolge und Grenzen',array_keys((array)$cl['topics'])===['oeffnungszeiten','kontakt','leer','aus']&&$cl['order']===['kontakt','oeffnungszeiten']&&$cl['news']['count']===2);
t('Bereinigung: Aussprachen werden normalisiert',$cl['topics']['oeffnungszeiten']['extra']===['wann habt ihr auf','öffnungs zeiten']);
t('Bereinigung: keine Radio-Felder mehr',!array_key_exists('stations',$cl)&&!array_key_exists('default_station',$cl)&&!array_key_exists('radio_sync',elvado_alexa_clean(['radio_sync'=>1]))&&!array_key_exists('schedule',$cl));
t('Bereinigung: Grenzen für Neuigkeiten',elvado_alexa_clean(['news'=>['count'=>99]])['news']['count']===10&&elvado_alexa_clean(['news'=>['count'=>0]])['news']['count']===1&&elvado_alexa_clean([])['news']['enabled']===true);
$defs=elvado_alexa_topic_defs($site);
t('Themen: Reihenfolge, Titel, leere Antwort gilt als ausgeschaltet',array_keys($defs)===['kontakt','oeffnungszeiten','leer','aus']&&$defs['kontakt']['enabled']&&!$defs['leer']['enabled']&&!$defs['aus']['enabled']);
$news=elvado_alexa_news($tmp,2);
t('Neuigkeiten: neueste veröffentlichte zuerst, ohne Entwurf/Gelöschtes/Zukunft, Text ohne HTML',array_column($news,'title')===['Sommerfest','Neuer Katalog']&&$news[1]['text']==='Der Katalog ist da.'&&$news[0]['text']==='Wir feiern am Samstag.');
$pub=elvado_alexa_public($site,$tmp,'https://rad.example');
t('Öffentlich: Name, Themen (mit Texten), Neuigkeiten, Texte',$pub['name']==='Rad & Tat'&&count($pub['topics'])===4&&$pub['topics'][0]['id']==='kontakt'&&count($pub['news'])===2&&str_contains($pub['texts']['welcome'],'Rad & Tat'));
t('Öffentlich: keine Radio-Felder',!array_key_exists('stations',$pub)&&!array_key_exists('radio_api',$pub)&&!array_key_exists('stream_url',$pub)&&!preg_match('/ricorewi|anmacha|senderwelt|laut\.fm/i',json_encode($pub)));
$off=elvado_alexa_public(['alexa'=>['news'=>['enabled'=>false]]],$tmp,'https://rad.example');
t('Öffentlich: Neuigkeiten abschaltbar',$off['news']===[]);
t('Öffentlich: Wartung',elvado_alexa_public(['alexa'=>['maintenance'=>['enabled'=>true,'text'=>'Pause']]],$tmp,'https://x')['maintenance']==='Pause'&&$pub['maintenance']===null);
$m=elvado_alexa_model($site,$w);$lm=$m['interactionModel']['languageModel'];$names=array_column($lm['intents'],'name');
$vals=array_column($lm['types'][0]['values'],null,'id');
t('Sprachmodell: Aufrufname aus dem Website-Namen, Themen als Slot-Werte',$lm['invocationName']==='rad tat'&&array_keys($vals)===['kontakt','oeffnungszeiten']&&$vals['oeffnungszeiten']['name']['value']==='öffnungszeiten');
t('Sprachmodell: Befehle für Neuigkeiten, Beitrag, Thema, Themenliste, keine Radio-Befehle',in_array('NewsIntent',$names,true)&&in_array('ReadNewsIntent',$names,true)&&in_array('TopicIntent',$names,true)&&in_array('ListTopicsIntent',$names,true)&&!preg_match('/Play|NowPlaying|Show|Schedule|Station|Pause|Resume|Loop|Shuffle|Next|Previous/',implode(' ',$names)));
$none=elvado_alexa_model(['alexa'=>['news'=>['enabled'=>false]]]);$nn=array_column($none['interactionModel']['languageModel']['intents'],'name');
t('Sprachmodell ohne Themen und ohne Neuigkeiten: nur Standardbefehle, kein leerer Slot-Typ',!isset($none['interactionModel']['languageModel']['types'])&&!in_array('TopicIntent',$nn,true)&&!in_array('NewsIntent',$nn,true));
$dup=elvado_alexa_model(['alexa'=>['topics'=>['aa'=>['title'=>'Preise','text'=>'x'],'bb'=>['title'=>'Preise','text'=>'y']]]],$w2);
t('Sprachmodell: doppelte Titel werden gemeldet',count($w2)===1&&count($dup['interactionModel']['languageModel']['types'][0]['values'])===1);
t('Modell ändert sich mit den Themen (Neu-Einspielen-Hinweis), nicht mit Antworttexten',elvado_alexa_model_rev($site)!==elvado_alexa_model_rev(['portal'=>$site['portal'],'alexa'=>['topics'=>['kontakt'=>['title'=>'Kontakt','text'=>'a']]]])&&elvado_alexa_model_rev($site)===elvado_alexa_model_rev(array_replace_recursive($site,['alexa'=>['topics'=>['kontakt'=>['text'=>'ganz anders']]]])));
t('Paket-Platzhalter: Name, Aufrufname, Themen, Beispiel',elvado_alexa_fill('{{NAME}}|{{INVOCATION}}|{{THEMEN}}|{{EXAMPLE}}|{{ORIGIN}}',$site,'https://rad.example/')==='Rad & Tat|rad tat|Kontakt, Öffnungszeiten|Kontakt|https://rad.example');
$mf=elvado_alexa_manifest($site,'https://rad.example');$loc=$mf['manifest']['publishingInformation']['locales']['de-DE'];
t('Manifest: Name, Beispielsätze, keine Audio-Schnittstelle, keine Musik-Kategorie',$loc['name']==='Rad & Tat'&&str_contains(implode(' ',$loc['examplePhrases']),'rad tat')&&!isset($mf['manifest']['apis']['custom']['interfaces'])&&$mf['manifest']['publishingInformation']['category']!=='MUSIC_AND_AUDIO');
$files=elvado_alexa_package_files($site,$tmp,'https://rad.example',$tmp);
$all=implode("\n",array_filter($files,fn($k)=>!str_ends_with($k,'.png'),ARRAY_FILTER_USE_KEY));
t('Paket: enthält Modell, Manifest, Backend, Anleitung – ohne Radio-/Hersteller-Bezug',isset($files['skill-package/skill.json'],$files['skill-package/interactionModels/custom/de-DE.json'],$files['lambda/index.js'],$files['lambda/fallback.json'],$files['README.md'])&&!preg_match('/ricorewi|rico rewi|anmacha|senderwelt|laut\.fm|audioplayer|audio player/i',$all),(function() use($all){ preg_match('/.{30}(ricorewi|rico rewi|anmacha|senderwelt|laut\.fm|audioplayer|audio player).{30}/is',$all,$x);return $x[0]??''; })());
t('Zähler: Thema und Befehlsname, keine Sender',(function() use($tmp){ require_once __DIR__.'/../cms/lib/apps.php';
    elvado_alexa_stat_add($tmp,'topic','kontakt','');elvado_alexa_stat_add($tmp,'intent','','TopicIntent');elvado_alexa_stat_add($tmp,'play','x','');
    $s=elvado_alexa_stats($tmp);return ($s['topics']['kontakt']??0)===1&&($s['intents']['TopicIntent']??0)===1&&$s['total']===1&&!isset($s['plays']); })());
if(trim((string)shell_exec('command -v node'))!==''){
    $js=<<<'JS'
const M=require('module'),orig=M._load;const chain=new Proxy({}, {get:(t,k)=>k==='lambda'?()=>()=>{}:()=>chain});
M._load=function(r,...a){ if(r==='ask-sdk-core')return {SkillBuilders:{custom:()=>chain},getRequestType:()=>'',getIntentName:()=>''}; if(r==='./cms.json')return {base:'https://x.example',token:'t'}; if(r==='./fallback.json')return {status:'ok',topics:[],news:[],texts:{}}; return orig.call(this,r,...a); };
const i=require(process.argv[2]).__internals;
const cfg={topics:[{id:'a',title:'Öffnungszeiten & Preise',text:'x',enabled:true},{id:'b',title:'Aus',text:'y',enabled:false},{id:'c',title:'Kontakt',text:'z',enabled:true}]};
const ok=[
 i.esc('A & B')==='A und B',
 i.esc('<speak>')==='speak',
 i.fill('Hallo {name} {x}',{name:'Welt'})==='Hallo Welt {x}',
 i.join(['a','b','c'])==='a, b und c' && i.join(['a'])==='a' && i.join([])==='',
 i.examples(cfg)==='Öffnungszeiten und Preise, Kontakt',
 i.topicFromSlot(cfg,{value:'kontakt'}).id==='c',
 i.topicFromSlot(cfg,{value:'aus'})===null,
 i.topicFromSlot(cfg,{value:'x',resolutions:{resolutionsPerAuthority:[{status:{code:'ER_SUCCESS_MATCH'},values:[{value:{id:'a'}}]}]}}).id==='a',
 i.topicFromSlot(cfg,null)===null
];
console.log(ok.every(Boolean)?'OK':'FALSCH '+JSON.stringify(ok));
JS;
    $f=$tmp.'/t.js';file_put_contents($f,$js);
    $out=trim((string)shell_exec('node '.escapeshellarg($f).' '.escapeshellarg(realpath(__DIR__.'/../cms/lib/alexa-skill/lambda/index.js')).' 2>&1'));
    t('Skill-Backend: Textaufbereitung, Themen-Auflösung, Beispiele',$out==='OK',$out);
}
$api=(string)file_get_contents(__DIR__.'/../cms/api.php');$ui=(string)file_get_contents(__DIR__.'/../cms/assets/alexa-manager.js');
t('Skill-Icon-Upload: Endpunkt vorhanden und nur für Admins',str_contains($api,"alexa_icon_upload") && preg_match("/alexa_icon_upload'\)\{\s*elvado_auth\(true\)/",$api)===1);
t('Skill-Icon-Upload: Oberfläche ruft den Endpunkt auf',str_contains($ui,'alexa_icon_upload') && str_contains($ui,'uploadIcon'));
t('Verwaltung: Themen statt Sender',str_contains($ui,'addTopic')&&!str_contains($ui,'addStation')&&!preg_match('/laut\.fm|Sendeplan|Audio Player/',$ui));
system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

<?php
// Prüft den KI-Assistenten (cms/lib/assistant.php): Vorgaben, Anbieter- und Modellreihenfolge, Temperatur,
// Modellliste, lokale Anbieter, Website-Suche und -Kontext, zentrale KI-Konfiguration. Aufruf: php scripts/test-assistant.php
declare(strict_types=1);
require_once __DIR__.'/../cms/lib/pack.php';
require_once __DIR__.'/../cms/lib/alexa.php';require_once __DIR__.'/../cms/lib/assistant.php';require_once __DIR__.'/../cms/src/autoload.php';
$fail=0;$n=0;
function t(string $name,callable $fn){global $fail,$n;$n++;try{$fn();echo "  ok  $name\n";}catch(Throwable $e){$fail++;echo "FAIL  $name: ".$e->getMessage()."\n";}}
function eq($a,$b,string $m=''){if($a!==$b)throw new RuntimeException(($m?$m.': ':'').'erwartet '.json_encode($b).', war '.json_encode($a));}
$tmp=sys_get_temp_dir().'/as-'.bin2hex(random_bytes(4));mkdir($tmp);register_shutdown_function(function() use($tmp){ exec('rm -rf '.escapeshellarg($tmp)); });

t('Vorgaben: manuelle Reihenfolge, allgemeine Texte, keine Radio-Funktionen',function(){
    $c=rrw_assistant_clean([]);eq($c['order_mode'],'manual');eq($c['temperature'],0.2);eq($c['name'],'Assistent');
    if(preg_match('/radio|sender|sendeplan|ricorewi/i',$c['greeting']))throw new RuntimeException('Radio-Bezug in der Begrüßung: '.$c['greeting']);
    eq($c['features']['pages'],true);eq($c['features']['research'],true);eq(array_keys($c['features']),['news','pages','research']);
    eq(array_key_exists('mode',$c),false);eq(array_key_exists('stations',$c),false);
});
t('Temperatur und Reihenfolge werden begrenzt und geprüft',function(){
    eq(rrw_assistant_clean(['temperature'=>9])['temperature'],1.5);eq(rrw_assistant_clean(['temperature'=>-1])['temperature'],0.0);eq(rrw_assistant_clean(['temperature'=>'0.7'])['temperature'],0.7);eq(rrw_assistant_clean(['temperature'=>'x'])['temperature'],0.2);
    eq(rrw_assistant_clean(['order_mode'=>'auto'])['order_mode'],'auto');eq(rrw_assistant_clean(['order_mode'=>'manual'])['order_mode'],'manual');
});
t('Eigener Anbieter: https oder lokal (Ollama), bis zu 20 Modelle, ungültige Adressen entfallen',function(){
    $mk=fn($id,$base,$extra=[])=>['id'=>$id,'label'=>$id,'base_url'=>$base,'model'=>'m0','api_key'=>'','enabled'=>true]+$extra;
    $c=rrw_assistant_clean(['providers'=>[
        $mk('eigen','https://api.example.org/v1/',['models'=>array_map(fn($i)=>"modell-$i",range(1,30))]),
        $mk('lokal','http://localhost:11434/v1'),$mk('lokal2','http://127.0.0.1:1234/v1'),
        $mk('fremd','http://api.example.org/v1'),$mk('intern','http://192.168.1.5/v1'),$mk('evil','https://x.example.org/v1" onload="'),
    ]]);
    $by=[];foreach($c['providers'] as $p)$by[$p['id']]=$p;
    eq($by['eigen']['base_url'],'https://api.example.org/v1');eq(count($by['eigen']['models']),20);eq($by['lokal']['base_url'],'http://localhost:11434/v1');eq(isset($by['lokal2']),true);
    foreach(['fremd','intern','evil'] as $x)eq(isset($by[$x]),false,"$x muss entfallen");
    eq(rrw_assistant_base_url_ok('http://localhost.evil.com/v1'),false);eq(rrw_assistant_base_url_ok('http://localhost@evil.com/v1'),false);
});
t('Anbieter-Reihenfolge: manuell = wie eingestellt, automatisch = kostenlose mit Key, Community-Dienste, kostenpflichtige',function(){
    $mk=fn($id,$free,$key)=>['id'=>$id,'label'=>$id,'base_url'=>'https://'.$id.'.example.org/v1','model'=>'m','api_key'=>$key,'enabled'=>true,'free'=>$free,'needs_key'=>$key!==''];
    $providers=[$mk('bezahlt',false,'k'),$mk('gratisohnekey',true,''),$mk('gratismitkey',true,'k')];
    $base=['providers'=>$providers,'order_mode'=>'manual'];
    $ready=fn($cfg)=>array_column(rrw_assistant_providers_ready($cfg,sys_get_temp_dir()),'id');
    eq($ready($base),['bezahlt','gratisohnekey','gratismitkey'],'manuell');
    eq($ready(['order_mode'=>'auto']+$base),['gratismitkey','gratisohnekey','bezahlt'],'automatisch');
});
t('Modelle eines Anbieters: manuell in der eingestellten Reihenfolge, automatisch nach Leistung',function() use($tmp){
    $p=['id'=>'x','model'=>'haupt','models'=>['b','a','c']];
    $m=rrw_assistant_expand_provider($p+['manual'=>true],$tmp,20);
    eq(array_column($m,'model'),['haupt','b','a','c']);eq($m[1]['bid'],'x:b');
    eq(count(rrw_assistant_expand_provider($p+['manual'=>true],$tmp,2)),2,'Grenze');
    eq(count(rrw_assistant_expand_provider($p,$tmp,20)),4);
});
t('Modellliste lesen: OpenAI-Format, Liste, Ollama; nur Chat-Modelle; kostenlos erkannt',function(){
    $o=rrw_assistant_parse_models(['data'=>[['id'=>'gpt-4o-mini'],['id'=>'text-embedding-3-small'],['id'=>'whisper-1'],['id'=>'dall-e-3'],['id'=>'meta/free-70b:free','pricing'=>['prompt'=>'0','completion'=>'0'],'context_length'=>32000],['id'=>'bezahlt','pricing'=>['prompt'=>'0.001','completion'=>'0.002']],['id'=>'böse"<x>'],['id'=>'gpt-4o-mini']]]);
    eq(array_column($o,'id'),['bezahlt','gpt-4o-mini','meta/free-70b:free']);
    $by=[];foreach($o as $x)$by[$x['id']]=$x;eq($by['meta/free-70b:free']['free'],true);eq($by['bezahlt']['free'],false);eq($by['gpt-4o-mini']['free'],null);eq($by['meta/free-70b:free']['ctx'],32000);
    eq(array_column(rrw_assistant_parse_models(['models'=>[['name'=>'llama3.2:3b'],['name'=>'mistral']]]),'id'),['llama3.2:3b','mistral']);
    eq(array_column(rrw_assistant_parse_models(['a','b']),'id'),['a','b']);eq(rrw_assistant_parse_models('murks'),[]);
});
t('Modellliste abrufen: Basis-URL wird geprüft',function(){
    $r=rrw_assistant_models_list([],['base_url'=>'http://evil.example.org/v1'],sys_get_temp_dir());eq($r['ok'],false);
    $r=rrw_assistant_models_list([],[],sys_get_temp_dir());eq($r['ok'],false);
});
t('Website-Suche: Beiträge nach Titel, Auszug und Text, nur Veröffentlichtes',function() use($tmp){
    $f=$tmp.'/news.json';file_put_contents($f,json_encode([
        ['id'=>1,'slug'=>'oeffnungszeiten','title'=>'Neue Öffnungszeiten','excerpt'=>'Ab Montag haben wir länger geöffnet.','body_html'=>'<p>Montag bis Freitag 9–18 Uhr, Samstag 10–14 Uhr.</p>','status'=>'published','published_at'=>'2026-01-05 10:00:00'],
        ['id'=>2,'slug'=>'versand','title'=>'Versand und Lieferung','body_html'=>'<p>Wir liefern in 2–3 Werktagen.</p>','status'=>'published','published_at'=>'2026-02-01 10:00:00'],
        ['id'=>3,'slug'=>'entwurf','title'=>'Öffnungszeiten Entwurf','body_html'=>'x','status'=>'draft'],
        ['id'=>4,'slug'=>'geloescht','title'=>'Öffnungszeiten alt','body_html'=>'x','status'=>'published','deleted_at'=>'2026-01-01 00:00:00'],
        ['id'=>5,'slug'=>'zukunft','title'=>'Öffnungszeiten Zukunft','body_html'=>'x','status'=>'published','published_at'=>'2999-01-01 00:00:00'],
    ]));
    $r=rrw_assistant_site_search($f,'Wann habt ihr geöffnet? Öffnungszeiten?','https://www.beispiel.de');
    eq(array_column($r,'title'),['Neue Öffnungszeiten']);eq($r[0]['url'],'https://www.beispiel.de/oeffnungszeiten/');
    eq(str_contains($r[0]['text'],'Montag bis Freitag'),true);
    $r=rrw_assistant_site_search($f,'Wie lange dauert die Lieferung?','https://x.example.org');eq(array_column($r,'title'),['Versand und Lieferung']);
    eq(rrw_assistant_site_search($f,'Völlig anderes Thema Quantenphysik','https://x.example.org'),[]);
    eq(rrw_assistant_search_terms('Was ist das für ein Shop und wie geht es?'),['shop','geht']);
});
{
t('Website-Prompt und Kontext: ohne Radio-Bezug, mit Inhalten und Wissen',function() use($tmp){
    $cfg=rrw_assistant_clean(['name'=>'Berater','knowledge'=>'Wir verkaufen Fahrräder.','system_prompt'=>'Duze die Besucher.']);
    $site=['portal'=>['site_name'=>'Rad & Tat']];
    $b=rrw_assistant_website_context($cfg,$site,'Öffnungszeiten',$tmp.'/news.json','https://rad.example.org');
    $p=rrw_assistant_website_prompt($cfg,$site,$b['context']);
    foreach(['Berater','Rad & Tat','Neue Öffnungszeiten','https://rad.example.org/oeffnungszeiten/','Wir verkaufen Fahrräder.','Duze die Besucher.'] as $x)if(!str_contains($p,$x))throw new RuntimeException("fehlt: $x");
    if(preg_match('/sendeplan|laut\.fm|ricorewi|anmacha|senderwelt|radio/i',$p))throw new RuntimeException('Radio-Bezug im Prompt');
    eq($b['cards'][0]['type'],'pages');
    $off=rrw_assistant_website_offline($b);if(!str_contains($off,'Neue Öffnungszeiten'))throw new RuntimeException($off);
    if(!str_contains(rrw_assistant_website_offline(['hits'=>[],'research'=>[]]),'nichts Passendes'))throw new RuntimeException('Leerantwort');
    $k=rrw_assistant_website_context($cfg,$site,'Verkauft ihr Fahrräder?',$tmp.'/news.json','https://rad.example.org');
    eq($k['knowledge'],['Wir verkaufen Fahrräder.']);if(!str_contains(rrw_assistant_website_offline($k),'Wir verkaufen Fahrräder.'))throw new RuntimeException('Wissen fehlt in der Antwort ohne KI');
});
t('Aktive Alexa-Themen dienen dem Assistenten als Wissen; ausgeschaltete nicht',function() use($tmp){
    $cfg=rrw_assistant_clean([]);
    $site=['alexa'=>['topics'=>['oeffnung'=>['title'=>'Öffnungszeiten','text'=>'Montag bis Freitag 9 bis 18 Uhr.'],'intern'=>['enabled'=>false,'title'=>'Intern','text'=>'Geheim']]]];
    $k=rrw_assistant_knowledge($cfg,$site);
    if(!str_contains($k,'Öffnungszeiten: Montag bis Freitag 9 bis 18 Uhr.')||str_contains($k,'Geheim'))throw new RuntimeException($k);
    $b=rrw_assistant_website_context($cfg,$site,'Wann habt ihr Öffnungszeiten?',$tmp.'/news.json','https://rad.example.org');
    if(!$b['knowledge']||!str_contains($b['knowledge'][0],'9 bis 18'))throw new RuntimeException(json_encode($b['knowledge']));
    eq(rrw_assistant_knowledge($cfg,['alexa'=>['enabled'=>false,'topics'=>['a1'=>['title'=>'X','text'=>'Y']]]]),'','Alexa aus → kein Wissen');
});
t('Chat (ohne Anbieter): Antwort aus den Beiträgen, keine Radio-Inhalte',function() use($tmp){
    $site=['assistant'=>['providers'=>array_map(fn($p)=>['id'=>$p['id'],'enabled'=>false],rrw_assistant_provider_presets()),'features'=>['research'=>false]],'portal'=>['site_name'=>'Rad & Tat'],'seo'=>['canonical_base'=>'https://rad.example.org']];
    $r=rrw_assistant_chat($site,[],['messages'=>[['role'=>'user','content'=>'Wann habt ihr geöffnet?']]],$tmp,$tmp.'/news.json');
    eq($r['status'],'ok');eq($r['provider'],'offline');eq(str_contains($r['reply'],'Neue Öffnungszeiten'),true,$r['reply']);eq($r['cards'][0]['type']??'','pages');
    if(preg_match('/sender|sendeplan|radio/i',$r['reply']))throw new RuntimeException('Radio-Bezug: '.$r['reply']);
});
}
t('Test eines einzelnen Modells: Name wird geprüft, unbekannter Anbieter gemeldet',function() use($tmp){
    eq(rrw_assistant_test([],'gibtsnicht',$tmp,'m')['ok'],false);
});
// ---- Zentrale KI-Konfiguration (KI-Zentrale): Schlüssel und eigene Anbieter kommen von dort
if(!function_exists('rrw_data_dir')){function rrw_data_dir():string{return $GLOBALS['tmpData'];}}
$tmpData=$tmp.'/zentral';mkdir($tmpData);
t('Ohne zentrale Einstellung bleibt alles wie bisher (Schlüssel des Assistenten gelten)',function(){
    $c=rrw_assistant_with_central(rrw_assistant_clean(['providers'=>[['id'=>'groq','api_key'=>'alt-key-123456']]]));
    foreach($c['providers'] as $p)if($p['id']==='groq'){eq($p['api_key'],'alt-key-123456');eq($p['key_source'],'assistant');return;}
    throw new RuntimeException('groq fehlt');
});
t('Zentraler Schlüssel hat Vorrang, Google heißt im Assistenten „gemini“',function() use($tmpData){
    \Elvado\Ai\AiGatewayConfig::load($tmpData)->save(['providers'=>['groq'=>['api_key'=>'zentral-groq-key-1'],'google'=>['api_key'=>'zentral-google-key-1']]]);
    $c=rrw_assistant_with_central(rrw_assistant_clean(['providers'=>[['id'=>'groq','api_key'=>'alt-key-123456']]]));$k=[];foreach($c['providers'] as $p)$k[$p['id']]=[$p['api_key'],$p['key_source']];
    eq($k['groq'],['zentral-groq-key-1','central']);eq($k['gemini'],['zentral-google-key-1','central']);eq($k['openrouter'][1],'');
});
t('Eigene Anbieter der Zentrale erscheinen im Assistenten (mit Markierung, ohne Speicherung in site.json)',function() use($tmpData){
    \Elvado\Ai\AiGatewayConfig::load($tmpData)->save(['custom'=>[['id'=>'ollama','label'=>'Ollama (lokal)','base_url'=>'http://localhost:11434/v1','model'=>'llama3.2','needs_key'=>false]]]);
    $c=rrw_assistant_with_central(rrw_assistant_clean([]));$o=null;foreach($c['providers'] as $p)if($p['id']==='ollama')$o=$p;
    if(!$o)throw new RuntimeException('ollama fehlt');eq($o['central'],true);eq($o['needs_key'],false);eq($o['model'],'llama3.2');
    foreach(rrw_assistant_clean([])['providers'] as $p)if($p['id']==='ollama')throw new RuntimeException('Anbieter der Zentrale darf nicht in die gespeicherte Konfiguration');
    $v=rrw_assistant_admin_view([]);foreach($v['providers'] as $p)eq($p['api_key'],'','Admin-Sicht ohne Schlüssel');
    $m=rrw_assistant_merge_keys([],['providers'=>[['id'=>'ollama','central'=>true,'base_url'=>'http://localhost:11434/v1','model'=>'x'],['id'=>'groq']]]);
    eq(array_column($m['providers'],'id'),['groq'],'zentrale Zeilen werden beim Speichern nicht übernommen');
});
t('Anbieter ohne Schlüsselpflicht aus der Zentrale sind im Betrieb bereit; die öffentliche Sicht verrät keine Schlüssel',function() use($tmp){
    $cfg=rrw_assistant_with_central(rrw_assistant_clean([]));$ids=array_column(rrw_assistant_providers_ready($cfg,$tmp),'id');
    if(!in_array('ollama',$ids,true)||!in_array('groq',$ids,true))throw new RuntimeException('bereit: '.implode(',',$ids));
    if(str_contains(json_encode(rrw_assistant_public([])),'zentral-groq'))throw new RuntimeException('Schlüssel in öffentlicher Sicht');
});
echo "\n".($n-$fail)." von $n Prüfungen bestanden\n";
exit($fail?1:0);

<?php
// Prüft die Radio-Erweiterung (cms/lib/radio.php) und das Theme elvado-radio: Quellen (laut.fm, Icecast, Shoutcast) mit Fake-Abruf, Bereinigung, Sendeplan, Cache, Theme-Ausgabe, Shortcodes. Aufruf: php scripts/test-radio.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/rrw-rd-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/cms');mkdir($tmp.'/wp-content/themes');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('RRW_WP_DATA',$tmp.'/cms/.wp');define('RRW_WP_CMS_DATA',$tmp.'/cms');$_SERVER['HTTP_HOST']='example.test';$_SERVER['REMOTE_ADDR']='203.0.113.5';
$themesSrc=__DIR__.'/../cms/wp-content/themes';
$news=[];
for($i=1;$i<=5;$i++)$news[]=['id'=>$i,'slug'=>"beitrag-$i",'title'=>"Beitrag $i".($i===2?' <script>alert(1)</script>':''),'category'=>$i%2?'News':'Events','tags'=>$i<3?'musik, radio':'','excerpt'=>"Auszug $i",'body_html'=>"<p>Inhalt von Beitrag $i [forum]</p>",'status'=>'published','published_at'=>"2026-0$i-10 10:00:00",'author'=>'Anna Autor','image_url'=>$i===1?'/img/a.jpg':''];
file_put_contents($tmp.'/cms/news.json',json_encode($news));
file_put_contents($tmp.'/cms/site.json',json_encode(['portal'=>['site_name'=>'Mein Radio','tagline'=>'Hör rein'],'comments'=>['enabled'=>true,'require_approval'=>true],'branding'=>['portal_logo'=>'/logo.png'],
 'menus'=>['top'=>[['id'=>'m1','label'=>'Start','target'=>'system:start','enabled'=>true,'parent_id'=>''],['id'=>'m2','label'=>'Über uns','target'=>'page:ueber','enabled'=>true,'parent_id'=>''],['id'=>'m3','label'=>'Sender','target'=>'system:sender','enabled'=>true,'parent_id'=>'']],'bottom'=>[['id'=>'b1','label'=>'Impressum','target'=>'url:https://example.com/impressum','enabled'=>true,'parent_id'=>'']]],
 'pages'=>[['id'=>'ueber','slug'=>'ueber-uns','title'=>'Über uns','type'=>'custom','enabled'=>true,'blocks_before'=>[['type'=>'html','html'=>'<p>Wir sind ein Radio.</p>']],'blocks_after'=>[]],['id'=>'sender','type'=>'system','system_target'=>'sender','enabled'=>true,'headline'=>'Unsere Sender','intro'=>'Alle Sender im Überblick.','blocks_before'=>[],'blocks_after'=>[]]]]));
mkdir($tmp.'/themes-native');
// das mitgelieferte Standard-Theme bereitstellen
define('RRW_WP_TEST_NATIVE',dirname(__DIR__).'/cms/themes');
require __DIR__."/_testdb.php";
require __DIR__.'/../cms/wp/load.php';require __DIR__.'/../cms/wp/router.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
function page(string $uri): array { return rrw_wp_dispatch($uri,'GET',[],[]); }
require_once __DIR__.'/../cms/lib/radio.php';
$data=$tmp.'/cms';
// Fake-Abruf: kein Netz
$calls=[];
$fake=function(string $u) use(&$calls){
    $calls[]=$u;
    if(str_contains($u,'api.laut.fm/station/testfm/current_song'))return json_encode(['title'=>'Song A','artist'=>['name'=>'Band A']]);
    if(str_contains($u,'api.laut.fm/station/testfm/last_songs'))return json_encode([['title'=>'Song A','artist'=>['name'=>'Band A']],['title'=>'Song B','artist'=>['name'=>'Band B']],['title'=>'Song C','artist'=>['name'=>'Band C']]]);
    if(str_contains($u,'api.laut.fm/station/testfm/playlists'))return json_encode([['name'=>'Morgenshow','airtimes'=>[['day'=>'monday','hour'=>'6','end_time'=>'10'],['day'=>'friday','hour'=>'22','end_time'=>'2']]]]);
    if(str_contains($u,'api.laut.fm/station/testfm'))return json_encode(['display_name'=>'Test FM','description'=>'Hallo','images'=>['station_120x120'=>'https://img.example/t.png'],'genres'=>['Rock']]);
    if(str_contains($u,'itunes.apple.com'))return json_encode(['results'=>[['artworkUrl100'=>'https://cdn.example/a/100x100bb.jpg']]]);
    if(str_contains($u,'ice.example/status-json.xsl'))return json_encode(['icestats'=>['source'=>[['listenurl'=>'http://ice.example/other','title'=>'X'],['listenurl'=>'http://ice.example/live','title'=>'Ice Band - Ice Song','listeners'=>7,'server_name'=>'Ice']]]]);
    if(str_contains($u,'sc.example/stats?'))return json_encode(['songtitle'=>'SC Band - SC Song','currentlisteners'=>3,'songhistory'=>[['title'=>'H1 - T1']],'servertitle'=>'SC']);
    if(str_contains($u,'old.example/7.html'))return '<html><body>1,1,5,100,1,128,Old Band - Old Song</body></html>';
    return '';
};
$GLOBALS['rrw_radio_http']=$fake;
// Bereinigung
$c=rrw_radio_clean(['stations'=>[
  ['id'=>'Main!','name'=>'<b>Test FM</b>','source'=>'lautfm','lautfm_id'=>'TestFM ','logo'=>'javascript:alert(1)','website'=>'https://example.org'],
  ['id'=>'ice','name'=>'Ice','source'=>'icecast','status_url'=>'http://ice.example/status-json.xsl','mount'=>'/live','stream_url'=>'http://ice.example/live'],
  ['id'=>'sc','source'=>'shoutcast','status_url'=>'http://sc.example:8000/','sid'=>500,'stream_url'=>'ftp://x'],
  ['id'=>'bad','source'=>'weird','name'=>'Roh'],
 ],'default'=>'ice','history_count'=>99,'poll_seconds'=>1,
 'schedule'=>[['day'=>9,'from'=>'25:00','to'=>'10:30','title'=>'Show <i>1</i>'],['day'=>1,'from'=>'06:00','to'=>'10:00','title'=>''],['day'=>2,'from'=>'8:05','to'=>'9:00','title'=>'Zwei','host'=>'Max']],
 'links'=>[['label'=>'Insta','url'=>'https://insta.example/x'],['label'=>'Böse','url'=>'javascript:alert(1)']],'show'=>['news'=>false]]);
t('Sender-IDs bereinigt',$c['stations'][0]['id']==='main'&&count($c['stations'])===4);
t('Name ohne Tags, laut.fm-ID klein/bereinigt, Stream automatisch',$c['stations'][0]['name']==='Test FM'&&$c['stations'][0]['lautfm_id']==='testfm'&&$c['stations'][0]['stream_url']==='https://stream.laut.fm/testfm');
t('Unsichere Logo-URL entfernt',$c['stations'][0]['logo']==='');
t('Unbekannte Quelle → static; ftp-Stream entfernt; SID begrenzt',$c['stations'][3]['source']==='static'&&$c['stations'][2]['stream_url']===''&&$c['stations'][2]['sid']===99);
t('Standard-Sender übernommen',$c['default']==='ice'&&rrw_radio_station($c)['id']==='ice'&&rrw_radio_station($c,'nix')===null);
t('Grenzen Verlauf/Aktualisierung',$c['history_count']===20&&$c['poll_seconds']===10);
t('Sendeplan bereinigt/sortiert (leerer Titel entfällt)',count($c['schedule'])===2&&$c['schedule'][0]['day']===2&&$c['schedule'][0]['from']==='08:05'&&$c['schedule'][1]['day']===7&&$c['schedule'][1]['from']==='00:00'&&$c['schedule'][1]['title']==='Show 1');
t('Links: unsichere entfernt',count($c['links'])===1);
t('Anzeige-Schalter',$c['show']['news']===false&&$c['show']['history']===true);
t('Leere Konfiguration gültig',rrw_radio_clean(null)['stations']===[]&&rrw_radio_clean(['stations'=>'x'])['default']==='');
// laut.fm
$cfg=rrw_radio_clean(['stations'=>[['id'=>'a','name'=>'Test FM','source'=>'lautfm','lautfm_id'=>'testfm','logo'=>'https://img.example/logo.png']],'cover_lookup'=>true,'history_count'=>5]);$s=$cfg['stations'][0];
$np=rrw_radio_now($data,$cfg,$s);
t('laut.fm: Jetzt läuft',$np['ok']&&$np['artist']==='Band A'&&$np['title']==='Song A');
t('laut.fm: laufender Titel nicht doppelt im Verlauf',count($np['history'])===2&&$np['history'][0]['title']==='Song B');
t('Cover über iTunes (300px) und Cache',str_contains($np['cover'],'300x300bb'));
$before=count($calls);rrw_radio_now($data,$cfg,$s);t('Zwischenspeicher: kein erneuter Abruf',count($calls)===$before);
$cfg2=$cfg;$cfg2['cover_lookup']=false;$np2=rrw_radio_now($data,$cfg2,$s);t('Ohne Cover-Suche: Sender-Logo',$np2['cover']==='https://img.example/logo.png');
// Cache-only (Seitenaufbau): ohne Cache kein Abruf
$GLOBALS['rrw_radio_cache_only']=true;$before=count($calls);$s2=$s;$s2['lautfm_id']='neu';$npc=rrw_radio_now($data,$cfg,$s2);unset($GLOBALS['rrw_radio_cache_only']);
t('Cache-only: kein Netzabruf, leere Anzeige',count($calls)===$before&&!$npc['ok']);
// Ausfall → letzter Stand
$GLOBALS['rrw_radio_http']=fn($u)=>'';
foreach(glob($data.'/.tools/radio-cache/*.json') as $f){ $j=json_decode(file_get_contents($f),true);$j['t']=1;file_put_contents($f,json_encode($j)); }
$npf=rrw_radio_now($data,$cfg,$s);t('Quelle down: letzter bekannter Stand',$npf['ok']&&$npf['artist']==='Band A');
$GLOBALS['rrw_radio_http']=$fake;

// Icecast / Shoutcast
$ci=rrw_radio_clean(['stations'=>[['id'=>'ice','source'=>'icecast','status_url'=>'http://ice.example/status-json.xsl','mount'=>'/live','stream_url'=>'http://ice.example/live'],
  ['id'=>'sc','source'=>'shoutcast','status_url'=>'http://sc.example/','sid'=>1,'stream_url'=>'http://sc.example/stream'],['id'=>'old','source'=>'shoutcast','status_url'=>'http://old.example','stream_url'=>'http://old.example/s']]]);
$r=rrw_radio_fetch_raw($ci['stations'][0]);t('Icecast: richtiger Mount, Titel getrennt, Hörer',$r&&$r['artist']==='Ice Band'&&$r['title']==='Ice Song'&&$r['listeners']===7);
$r=rrw_radio_fetch_raw($ci['stations'][1]);t('Shoutcast v2: Titel, Hörer, Verlauf',$r&&$r['artist']==='SC Band'&&$r['listeners']===3&&$r['history'][0]===['H1','T1']);
$r=rrw_radio_fetch_raw($ci['stations'][2]);t('Shoutcast v1 (7.html)',$r&&$r['artist']==='Old Band'&&$r['title']==='Old Song'&&$r['listeners']===1);
$bad=$ci['stations'][0];$bad['mount']='/gibtsnicht';t('Icecast: unbekannter Mount → keine Daten',rrw_radio_fetch_raw($bad)===null);
t('Verbindungstest meldet Titel',rrw_radio_test($ci['stations'][0])['ok']&&str_contains(rrw_radio_test($ci['stations'][0])['message'],'Ice Band – Ice Song'));
$none=$ci['stations'][0];$none['status_url']='http://down.example/status-json.xsl';$none['mount']='/x';t('Verbindungstest: Fehler verständlich',!rrw_radio_test($none)['ok']);
t('Nur-Stream: Test ok',rrw_radio_test(rrw_radio_clean(['stations'=>[['source'=>'static','stream_url'=>'http://x.example/s']]])['stations'][0])['ok']);
t('Titel ohne Trennzeichen',rrw_radio_split_song('Nur Titel')===['','Nur Titel']&&rrw_radio_split_song('A - B - C')[0]==='A');
t('SSRF: private Adresse wird nicht abgerufen',(function(){ unset($GLOBALS['rrw_radio_http']);$r=rrw_radio_http('http://127.0.0.1/status-json.xsl');$GLOBALS['rrw_radio_http']=$GLOBALS['fake_keep']??null;return $r===''; })());
$GLOBALS['rrw_radio_http']=$fake;
// Sendeplan
$sc=rrw_radio_schedule($data,$cfg,$s);
t('laut.fm-Sendeplan aus Playlists',count($sc)===2&&$sc[0]['day']===1&&$sc[0]['from']==='06:00'&&$sc[0]['title']==='Morgenshow');
$mon=new DateTimeImmutable('2026-10-05 07:30',rrw_radio_tz());$fri=new DateTimeImmutable('2026-10-09 23:00',rrw_radio_tz());$sat=new DateTimeImmutable('2026-10-10 01:00',rrw_radio_tz());$sun=new DateTimeImmutable('2026-10-11 12:00',rrw_radio_tz());
t('Aktuelle Sendung: im Fenster',rrw_radio_current_show($sc,$mon)==='Morgenshow');
t('Aktuelle Sendung: über Mitternacht (Fr 22–02 → Sa 01:00)',rrw_radio_current_show($sc,$fri)==='Morgenshow'&&rrw_radio_current_show($sc,$sat)==='Morgenshow');
t('Keine Sendung → leer',rrw_radio_current_show($sc,$sun)==='');
$cm=rrw_radio_clean(['stations'=>[['id'=>'a','source'=>'lautfm','lautfm_id'=>'testfm']],'schedule'=>[['day'=>7,'from'=>'10:00','to'=>'12:00','title'=>'Eigene']]]);
t('Manueller Sendeplan hat Vorrang',rrw_radio_schedule($data,$cm,$cm['stations'][0])[0]['title']==='Eigene');
// Speichern + Theme aktiv
$saved=rrw_radio_save($data,['stations'=>[['id'=>'main','name'=>'Test FM','source'=>'lautfm','lautfm_id'=>'testfm','logo'=>'https://img.example/logo.png','tagline'=>'Der Beste','genre'=>'Rock','website'=>'https://example.org'],['id'=>'zwei','name'=>'Zwei','source'=>'static','stream_url'=>'https://stream.example/zwei']],'default'=>'main','links'=>[['label'=>'Insta','url'=>'https://insta.example/x']],
  'schedule'=>[['day'=>1,'from'=>'06:00','to'=>'10:00','title'=>'Morgenshow','host'=>'Max']]]);
t('Speichern/Laden',rrw_radio_load($data)==$saved&&count(glob($data.'/.tools/radio-cache/*.json')?:[])===0);
t('Konfig-Ordner gesperrt',is_file($data.'/.tools/.htaccess'));
t('Theme inaktiv ohne Flag',!rrw_radio_theme_active($data));
// Theme
update_option('stylesheet','elvado-radio');update_option('template','elvado-radio');
@mkdir(RRW_WP_DATA,0775,true);touch(RRW_WP_DATA.'/front-on');
t('Theme aktiv erkannt (Flag + Option)',rrw_radio_theme_active($data));
rrw_wp_boot(['theme'=>true]);
t('Theme geladen',get_stylesheet()==='elvado-radio');
$r=page('/');$b=$r['body'];
t('Startseite 200',$r['status']===200,(string)$r['status']);
t('Hero mit Sender, Slogan, Player-Knopf',str_contains($b,'rd-hero')&&str_contains($b,'Der Beste')&&str_contains($b,'data-radio-play')&&str_contains($b,'data-stream="https://stream.laut.fm/testfm"'));
t('Player-Leiste mit Endpunkt',str_contains($b,'data-radio-bar')&&str_contains($b,'/cms/radio.php')&&str_contains($b,'player.js'));
t('Sendeplan heute + Woche, Links',str_contains($b,'Sendeplan heute')&&str_contains($b,'Morgenshow')&&str_contains($b,'Insta'));
t('Senderliste (2 Sender) mit Hören-Knopf',str_contains($b,'Unsere Sender')&&str_contains($b,'data-radio-select'));
t('News-Abschnitt',str_contains($b,'Aktuelles')&&str_contains($b,'Beitrag 5'));
t('Seitenaufbau ohne Netzabruf (Cache leer → Browser lädt nach)',true);
set_theme_mod('rd_accent','#ff0000');set_theme_mod('rd_title','Mein <b>Radio</b>');
$b=page('/')['body'];t('Farbe und Überschrift aus dem Customizer',str_contains($b,'--accent:#ff0000')&&str_contains($b,'Mein &lt;b&gt;Radio&lt;/b&gt;'));
// Erweiterbarkeit: Plugin-Haken und Zusatzbereich
add_action('elvado_rd_after_hero',function(){ echo '<!--rd-plugin-hero-->'; });add_action('elvado_rd_after_sections',function(){ echo '<!--rd-plugin-ende-->'; });
$bp=page('/')['body'];t('Plugin-Haken auf der Startseite (nach Hero, nach Abschnitten)',str_contains($bp,'<!--rd-plugin-hero-->')&&strpos($bp,'<!--rd-plugin-hero-->')<strpos($bp,'<!--rd-plugin-ende-->')&&isset($GLOBALS['wp_registered_sidebars']['front-extra']));
// Alexa-Hinweis nur, wenn der Skill läuft (hat die Einstellungen abgerufen)
t('Kein Alexa-Hinweis ohne verbundenen Skill',!str_contains(page('/')['body'],'rd-alexa'));
require_once __DIR__.'/../cms/lib/alexa.php';$GLOBALS['RRW_SITE']['alexa']=['invocation'=>'mein radio'];rrw_alexa_note_fetch($data);
t('Alexa-Hinweis mit Aufrufname bei verbundenem Skill (nur Baukasten-Modus; mit Herstellerpaket bleibt der Bestand)',!rrw_alexa_neutral()||str_contains(page('/')['body'],'Alexa, öffne mein radio'));
$GLOBALS['RRW_SITE']['alexa']['enabled']=false;t('Kein Hinweis bei abgeschaltetem Skill',!str_contains(page('/')['body'],'rd-alexa'));unset($GLOBALS['RRW_SITE']['alexa']);
// Schalter
rrw_radio_save($data,['stations'=>$saved['stations'],'default'=>'main','schedule'=>$saved['schedule'],'show'=>['news'=>false,'schedule'=>false,'history'=>false,'stations'=>false]]);
elvado_rd_cfg(true);
$b3=page('/')['body'];t('Anzeige-Schalter wirken (News/Sendeplan/Verlauf/Senderliste aus)',!str_contains($b3,'Aktuelles')&&!str_contains($b3,'Sendeplan heute')&&!str_contains($b3,'Zuletzt gespielt')&&!str_contains($b3,'Unsere Sender')&&str_contains($b3,'rd-hero'));
// Unterseite und Shortcodes
$s2=page('/beitrag-1/');t('Beitrag im Radio-Layout',$s2['status']===200&&str_contains($s2['body'],'site-content')&&str_contains($s2['body'],'data-radio-bar'));
$sc1=do_shortcode('[radio_player][radio_schedule today="1"][radio_stations][radio_history][radio_nowplaying]');
t('Shortcodes liefern Player, Sendeplan, Sender',str_contains($sc1,'np-card')&&str_contains($sc1,'sched-row')&&str_contains($sc1,'st-grid'));
t('Shortcode: unbekannter Sender → leer/Hinweis',do_shortcode('[radio_nowplaying station="gibtsnicht"]')===''&&str_contains(do_shortcode('[radio_player station="gibtsnicht"]'),'Noch kein Sender'));
foreach(['/ueber-uns/','/category/news/','/?s=Beitrag','/gibtsnicht/'] as $u){ $x=page($u);t("Unterseite $u",in_array($x['status'],[200,404],true)&&str_contains($x['body'],'site-footer'),(string)$x['status']); }
// Customizer
require_once __DIR__.'/../cms/wp/customizer-api.php';$ids=array_keys(rrw_wpc_items()['items']);
t('Customizer: Radio-Einstellungen',in_array('rd_accent',$ids,true)&&in_array('rd_title',$ids,true)&&in_array('rd_news_count',$ids,true));
system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

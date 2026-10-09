<?php
// Prüft die Theme-Konfiguration (cms/lib/themeconf.php, band.php) und das Theme elvado-band: Bereinigung nach Schema, Menü-Zustand, Ausgabe (Konzerte, Musik, Player nach Klick, Strukturdaten), Erweiterbarkeit durch Plugins. Aufruf: php scripts/test-band.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/elvado-bd-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/cms');mkdir($tmp.'/wp-content/themes');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('ELVADO_WP_DATA',$tmp.'/cms/.wp');define('ELVADO_WP_CMS_DATA',$tmp.'/cms');$_SERVER['HTTP_HOST']='example.test';$_SERVER['REMOTE_ADDR']='203.0.113.5';
$themesSrc=__DIR__.'/../cms/wp-content/themes';
$news=[];
for($i=1;$i<=5;$i++)$news[]=['id'=>$i,'slug'=>"beitrag-$i",'title'=>"Beitrag $i".($i===2?' <script>alert(1)</script>':''),'category'=>$i%2?'News':'Events','tags'=>$i<3?'musik, radio':'','excerpt'=>"Auszug $i",'body_html'=>"<p>Inhalt von Beitrag $i [forum]</p>",'status'=>'published','published_at'=>"2026-0$i-10 10:00:00",'author'=>'Anna Autor','image_url'=>$i===1?'/img/a.jpg':''];
file_put_contents($tmp.'/cms/news.json',json_encode($news));
file_put_contents($tmp.'/cms/site.json',json_encode(['portal'=>['site_name'=>'Mein Radio','tagline'=>'Hör rein'],'comments'=>['enabled'=>true,'require_approval'=>true],'branding'=>['portal_logo'=>'/logo.png'],
 'menus'=>['top'=>[['id'=>'m1','label'=>'Start','target'=>'system:start','enabled'=>true,'parent_id'=>''],['id'=>'m2','label'=>'Über uns','target'=>'page:ueber','enabled'=>true,'parent_id'=>''],['id'=>'m3','label'=>'Sender','target'=>'system:sender','enabled'=>true,'parent_id'=>'']],'bottom'=>[['id'=>'b1','label'=>'Impressum','target'=>'url:https://example.com/impressum','enabled'=>true,'parent_id'=>'']]],
 'pages'=>[['id'=>'ueber','slug'=>'ueber-uns','title'=>'Über uns','type'=>'custom','enabled'=>true,'blocks_before'=>[['type'=>'html','html'=>'<p>Wir sind ein Radio.</p>']],'blocks_after'=>[]],['id'=>'sender','type'=>'system','system_target'=>'sender','enabled'=>true,'headline'=>'Unsere Sender','intro'=>'Alle Sender im Überblick.','blocks_before'=>[],'blocks_after'=>[]]]]));
mkdir($tmp.'/themes-native');
// das mitgelieferte Standard-Theme bereitstellen
define('ELVADO_WP_TEST_NATIVE',dirname(__DIR__).'/cms/themes');
require __DIR__."/_testdb.php";
require __DIR__.'/../cms/wp/load.php';require __DIR__.'/../cms/wp/router.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
function page(string $uri): array { return elvado_wp_dispatch($uri,'GET',[],[]); }
$GLOBALS['ELVADO_SITE']=json_decode((string)file_get_contents($tmp.'/cms/site.json'),true);
require_once __DIR__.'/../cms/lib/themeconf.php';
$data=$tmp.'/cms';
$d=fn($n)=>date('Y-m-d',strtotime(($n>=0?'+':'').$n.' days'));
// ── Konfiguration: Registrierung, Schema, Bereinigung
$reg=elvado_tc_registry();
t('Band-Konfiguration registriert',isset($reg['band'])&&$reg['band']['theme']==='elvado-band'&&$reg['band']['menu']==='Band');
$sc=elvado_tc_public_schema('band');
t('Schema für den Editor (ohne Funktionen)',$sc['id']==='band'&&count($sc['sections'])>=8&&json_encode($sc)!==false&&$sc['sections'][1]['id']==='shows');
$in=['band'=>['name'=>'  <b>Die</b> Beispiel Band ','tagline'=>str_repeat('x',500),'hero_image'=>'javascript:alert(1)','press_photo'=>'/cms/media/library/x/orig.jpg','bio'=>"Absatz <script>x</script>1\n\n\n\nAbsatz 2",'ignored'=>'x'],
 'shows'=>[
   ['date'=>$d(20),'time'=>'20:00','venue'=>'Club <i>A</i>','city'=>'Berlin','status'=>'onsale','ticket_url'=>'https://tickets.example/a','note'=>'mit Support'],
   ['date'=>$d(5),'time'=>'9:05','venue'=>'Halle B','city'=>'Hamburg','status'=>'soldout','ticket_url'=>'ftp://x'],
   ['date'=>'2026-13-45','venue'=>'Kaputt'],
   ['date'=>$d(-30),'venue'=>'Alter Club','city'=>'Köln','status'=>'onsale'],
   ['date'=>$d(40),'venue'=>'Abgesagt','city'=>'Wien','status'=>'cancelled'],
   ['date'=>$d(60),'venue'=>'Offen','city'=>'Bern','status'=>'frei-erfunden'],
 ],
 'releases'=>[['title'=>'Album Eins','type'=>'album','date'=>'2025-05-01','cover'=>'https://img.example/c.jpg','spotify'=>'https://open.spotify.com/album/1A2B3C4D5E6F7G8H9I0J1K','embed'=>'https://www.youtube.com/watch?v=dQw4w9WgXcQ','description'=>'Beschreibung'],
   ['title'=>'','type'=>'ep'],['title'=>'Single Zwei','type'=>'single','date'=>'2026-01-10','bandcamp'=>'https://band.example/track','embed'=>'https://evil.example/x']],
 'videos'=>[['title'=>'Live in X','url'=>'https://youtu.be/abcdefghijk'],['title'=>'Ohne Player','url'=>'https://other.example/v'],['title'=>'Leer','url'=>'']],
 'members'=>[['name'=>'Anna','role'=>'Gesang','bio'=>'Kurz'],['name'=>'','role'=>'x']],
 'gallery'=>[['image'=>'https://img.example/1.jpg','caption'=>'Bild 1'],['image'=>'']],
 'links'=>[['type'=>'spotify','label'=>'','url'=>'https://open.spotify.com/artist/x'],['type'=>'unbekannt','url'=>'https://x.example'],['type'=>'instagram','url'=>'javascript:1']],
 'booking'=>['heading'=>'Booking','text'=>'Schreib uns','email'=>'booking@band.example','presskit_url'=>'https://band.example/presskit.zip','phone'=>'+49 30 1'],
 'display'=>['show_news'=>false,'past_shows'=>true]];
$c=elvado_tc_clean(elvado_band_schema(),$in);
t('Text: Tags/Leerraum entfernt, Länge begrenzt',$c['band']['name']==='Die Beispiel Band'&&mb_strlen($c['band']['tagline'])===160);
t('Bild: unsicher → leer, Pfad auf eigener Seite ok',$c['band']['hero_image']===''&&$c['band']['press_photo']==='/cms/media/library/x/orig.jpg');
t('Langtext: Absätze bleiben, Tags weg, unbekannte Felder entfallen',!str_contains($c['band']['bio'],'<script>')&&str_contains($c['band']['bio'],"\n\nAbsatz 2")&&!isset($c['band']['ignored']));
t('Liste: Pflichtfeld Datum, ungültige Einträge entfallen',count($c['shows'])===5&&array_search('Kaputt',array_column($c['shows'],'venue'))===false);
t('Uhrzeit normalisiert, Ticket-URL nur http(s), Status-Auswahl',$c['shows'][1]['time']==='09:05'&&$c['shows'][1]['ticket_url']===''&&$c['shows'][4]['status']==='onsale'&&$c['shows'][0]['venue']==='Club A');
t('Releases: ohne Titel entfallen',count($c['releases'])===2);
t('Videos: ohne Link entfallen; Mitglieder/Galerie ohne Pflichtfeld entfallen',count($c['videos'])===2&&count($c['members'])===1&&count($c['gallery'])===1);
t('Links: Dienst-Auswahl, unsichere URL entfällt',count($c['links'])===2&&$c['links'][1]['type']==='spotify');
t('Anzeige-Schalter: Vorgabe an, gesetzte übernommen',$c['display']['show_news']===false&&$c['display']['show_shows']===true&&$c['display']['past_shows']===true);
t('Leere Eingabe ergibt vollständige Struktur',count(elvado_tc_clean(elvado_band_schema(),null))===count(elvado_band_schema())&&elvado_tc_clean(elvado_band_schema(),'x')['shows']===[]);
$big=['shows'=>array_fill(0,300,['date'=>$d(3),'venue'=>'x'])];t('Listen sind begrenzt',count(elvado_tc_clean(elvado_band_schema(),$big)['shows'])===200);
// Speichern/Laden, Menü-Zustand
elvado_tc_save($data,'band',$in);
t('Speichern/Laden',elvado_tc_load($data,'band')==$c&&is_file($data.'/.tools/.htaccess'));
try{ elvado_tc_save($data,'gibtsnicht',[]);$e=false; }catch(Throwable $x){ $e=true; }t('Unbekannte Konfiguration abgelehnt',$e&&elvado_tc_entry('gibtsnicht')===null&&elvado_tc_load($data,'../x')===[]);
t('Menü inaktiv ohne aktives Theme',elvado_tc_state($data)['band']['active']===false);
@mkdir(ELVADO_WP_DATA,0775,true);touch(ELVADO_WP_DATA.'/front-on');update_option('stylesheet','elvado-band');update_option('template','elvado-band');
t('Menü aktiv bei aktivem Theme (Flag + Option)',elvado_tc_state($data)['band']['active']===true&&elvado_tc_theme_active($data,'elvado-band')&&!elvado_tc_theme_active($data,'elvado-baukasten'));
// ── Theme
elvado_wp_boot(['theme'=>true]);
t('Theme geladen',get_stylesheet()==='elvado-band');
$r=page('/');$b=$r['body'];
t('Startseite 200',$r['status']===200,(string)$r['status']);
t('Name als Schlagzeile (maskiert)',str_contains($b,'<h1>Die Beispiel Band</h1>'));
t('Konzerte: kommende sortiert, vergangene als Archiv',strpos($b,'Halle B')<strpos($b,'Club A')&&str_contains($b,'Vergangene Konzerte')&&str_contains($b,'Alter Club'));
t('Ticket-Link nur bei Vorverkauf, Status-Hinweise',str_contains($b,'https://tickets.example/a')&&str_contains($b,'Ausverkauft')&&str_contains($b,'Abgesagt'));
t('Hero-Knopf zum nächsten Termin mit Tickets',str_contains($b,'Tickets Berlin')||str_contains($b,'Live-Termine'));
t('Musik: Neuestes zuerst, Streaming-Links',strpos($b,'Single Zwei')<strpos($b,'Album Eins')&&str_contains($b,'open.spotify.com/album/')&&str_contains($b,'band.example/track'));
t('Player erst nach Klick (nocookie, kein iframe im Quelltext)',str_contains($b,'data-embed="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"')&&!str_contains($b,'<iframe')&&str_contains($b,'Daten an'));
t('Fremder Player-Anbieter wird nicht eingebettet',!str_contains($b,'evil.example')&&!str_contains($b,'other.example'));
t('Videos mit Player, Mitglieder, Galerie',str_contains($b,'youtube-nocookie.com/embed/abcdefghijk')&&str_contains($b,'bd-member')&&str_contains($b,'Bild 1'));
t('News ausgeschaltet',!str_contains($b,'id="news"'));
t('Booking mit E-Mail und Presskit',str_contains($b,'mailto:booking@band.example')&&str_contains($b,'presskit.zip'));
t('Fuß: Name groß, Social-Link',str_contains($b,'bd-foot-name')&&str_contains($b,'open.spotify.com/artist/x'));
preg_match('~<script type="application/ld\+json">(.*?)</script>~s',$b,$ldm);$ld=json_decode($ldm[1]??'',true);
t('Strukturdaten: Gruppe + Konzerte, ohne abgesagte',is_array($ld)&&$ld['@graph'][0]['@type']==='MusicGroup'&&count($ld['@graph'])===4&&!str_contains($ldm[1],'Wien')&&str_contains($ldm[1],'"MusicEvent"'));
t('Strukturdaten brechen nicht aus dem Script aus',substr_count($b,'application/ld+json')===1);
t('Laufband mit nächster Show',str_contains($b,'bd-ticker')&&str_contains($b,'Nächste Show'));
ob_start();elvado_bd_fallback_menu();$fm=ob_get_clean();
t('Standard-Menü ohne zugewiesenes Menü: Anker auf vorhandene Abschnitte',str_contains($fm,'/#shows')&&str_contains($fm,'/#music')&&str_contains($fm,'/#booking')&&!str_contains($fm,'/#news'));
t('Papier-Grundton, Akzentfarbe-Variablen',str_contains($b,'scheme-paper')&&str_contains($b,'--accent:#e4361b'));
set_theme_mod('bd_scheme','ink');set_theme_mod('bd_accent','#00aa55');set_theme_mod('bd_font','mono');
$b2=page('/')['body'];t('Customizer: dunkel, Akzent, Schrift',str_contains($b2,'scheme-ink')&&str_contains($b2,'font-mono')&&str_contains($b2,'--accent:#00aa55')&&str_contains($b2,'--on-accent:#ffffff'));
set_theme_mod('bd_accent','#ffee00');t('Textfarbe auf heller Akzentfarbe dunkel',str_contains(page('/')['body'],'--on-accent:#111111'));
// Anzeige-Schalter und Inhalte
$in2=$in;$in2['display']=['show_shows'=>false,'show_music'=>false,'show_booking'=>false,'show_band'=>false,'show_videos'=>false,'show_gallery'=>false,'show_news'=>false,'show_newsletter'=>false];elvado_tc_save($data,'band',$in2);elvado_bd_cfg(true);
$b3=page('/')['body'];t('Alle Abschnitte abschaltbar (Hero bleibt)',!str_contains($b3,'id="shows"')&&!str_contains($b3,'id="music"')&&!str_contains($b3,'id="booking"')&&str_contains($b3,'bd-hero'));
elvado_tc_save($data,'band',['band'=>['name'=>'Nur Name']]);elvado_bd_cfg(true);
$b4=page('/')['body'];t('Nur Name eingetragen: Seite funktioniert ohne leere Abschnitte',str_contains($b4,'<h1>Nur Name</h1>')&&!str_contains($b4,'id="shows"')&&!str_contains($b4,'id="music"')&&!str_contains($b4,'id="band"'));
// ── Erweiterbarkeit durch Plugins (WordPress-Haken)
elvado_tc_save($data,'band',$in);elvado_bd_cfg(true);
add_filter('elvado_bd_sections',function($s){ array_splice($s,2,0,['merch']);return $s; });
add_filter('elvado_bd_section_merch',fn($h)=>'<section id="merch">MERCH-PLUGIN</section>');
$seen=[];add_action('elvado_bd_before_section',function($id) use(&$seen){ $seen[]=$id; });
add_action('wp_footer',function(){ echo '<!--footer-plugin-->'; });
add_filter('elvado_bd_section_shows',fn($h)=>$h.'<!--nach-shows-->');
$b5=page('/')['body'];
t('Plugin ergänzt eigenen Abschnitt an gewünschter Stelle',strpos($b5,'MERCH-PLUGIN')>strpos($b5,'bd-ticker')&&strpos($b5,'MERCH-PLUGIN')<strpos($b5,'id="shows"'));
t('Plugin kann Abschnitts-HTML ändern, Aktion läuft je Abschnitt',str_contains($b5,'<!--nach-shows-->')&&in_array('merch',$seen,true)&&in_array('hero',$seen,true));
t('wp_footer-Haken und Zusatz-Widgetbereich',str_contains($b5,'<!--footer-plugin-->')&&isset($GLOBALS['wp_registered_sidebars']['front-extra']));
t('Skripte/Stile über wp_enqueue',str_contains($b5,'elvado-band-css')&&str_contains($b5,'band.js'));
// Unterseiten
$s2=page('/beitrag-1/');t('Beitrag im Band-Layout',$s2['status']===200&&str_contains($s2['body'],'site-content')&&!str_contains($s2['body'],'bd-hero'));
foreach(['/ueber-uns/','/category/news/','/?s=Beitrag','/gibtsnicht/'] as $u){ $x=page($u);t("Unterseite $u",in_array($x['status'],[200,404],true)&&str_contains($x['body'],'site-footer'),(string)$x['status']); }
// Customizer
require_once __DIR__.'/../cms/wp/customizer-api.php';$ids=array_keys(elvado_wpc_items()['items']);
t('Customizer: Band-Einstellungen',in_array('bd_accent',$ids,true)&&in_array('bd_scheme',$ids,true)&&in_array('bd_font',$ids,true));
system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

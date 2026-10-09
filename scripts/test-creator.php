<?php
// Prüft das Creator-Theme (elvado-creator) und seine Konfiguration (cms/lib/creator.php): Bereinigung, Link-in-Bio mit Zeitfenstern, Werbekennzeichnung, Empfehlungen, Drops, Link-Seite /links/, Strukturdaten, Erweiterbarkeit. Aufruf: php scripts/test-creator.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/elvado-cr-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/cms');mkdir($tmp.'/wp-content/themes');
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
$reg=elvado_tc_registry();
t('Creator-Konfiguration registriert (neben Band)',isset($reg['creator'],$reg['band'])&&$reg['creator']['theme']==='elvado-creator'&&$reg['creator']['menu']==='Creator');
$sc=elvado_tc_public_schema('creator');t('Schema für den Editor',count($sc['sections'])===15&&json_encode($sc)!==false);
$in=['profile'=>['name'=>' <b>Mia</b> Muster ','handle'=>'@mia','tagline'=>'Reisen & Leben','bio'=>"Hallo <script>x</script>!\n\n\n\nZweiter Absatz",'niches'=>'Reisen, Mode , Fitness','avatar'=>'https://img.example/a.jpg','cover'=>'javascript:alert(1)','cta_label'=>'Anfragen'],
 'stats'=>[['value'=>'1,2 Mio.','label'=>'Follower'],['value'=>'','label'=>'leer']],
 'platforms'=>[['platform'=>'instagram','handle'=>'@mia','followers'=>'800K','url'=>'https://insta.example/mia'],['platform'=>'erfunden','url'=>'https://x.example/m'],['platform'=>'tiktok','url'=>'javascript:1']],
 'links'=>[
   ['title'=>'Normal','url'=>'https://a.example/1','icon'=>'star'],
   ['title'=>'Heiß <i>jetzt</i>','url'=>'https://a.example/hot','highlight'=>true,'badge'=>'NEU','subtitle'=>'nur kurz'],
   ['title'=>'Werbung','url'=>'https://shop.example/aff','sponsored'=>true,'icon'=>'cart'],
   ['title'=>'Abgelaufen','url'=>'https://a.example/old','until'=>$d(-3)],
   ['title'=>'Bald','url'=>'https://a.example/soon','from'=>$d(5)],
   ['title'=>'Aktion','url'=>'https://a.example/now','from'=>$d(-1),'until'=>$d(4),'icon'=>'unbekannt'],
   ['title'=>'Ohne Ziel','url'=>'']],
 'highlights'=>[['title'=>'Paris','image'=>'https://img.example/p.jpg','url'=>'https://insta.example/s/1'],['title'=>'x','image'=>'']],
 'feed'=>[['image'=>'https://img.example/f1.jpg','url'=>'https://insta.example/p/1','kind'=>'reel','caption'=>'Strand'],['image'=>'https://img.example/f2.jpg','kind'=>'foto-falsch']],
 'videos'=>[['title'=>'Vlog 1','url'=>'https://youtu.be/abcdefghijk'],['title'=>'Fremd','url'=>'https://other.example/v']],
 'favorites'=>[['title'=>'Creme','brand'=>'Pflege Co','image'=>'https://img.example/c.jpg','url'=>'https://shop.example/c','code'=>'MIA15','discount'=>'−15 %','sponsored'=>true,'note'=>'Mein Favorit'],['title'=>'Buch','url'=>'https://shop.example/b','sponsored'=>false]],
 'drops'=>[['title'=>'Launch','date'=>$d(10),'time'=>'18:00','url'=>'https://a.example/launch','description'=>'Neue Kollektion'],['title'=>'Später','date'=>$d(30)],['title'=>'Vorbei','date'=>$d(-5)]],
 'collabs'=>[['name'=>'Marke A','url'=>'https://a.example'],['name'=>'Marke B','logo'=>'https://img.example/b.png']],
 'mediakit'=>['heading'=>'Mediakit','intro'=>'Für Marken','audience'=>"Alter 18–34 · 68 %\nWeiblich · 74 %",'download_url'=>'https://a.example/kit.pdf'],
 'packages'=>[['title'=>'Story-Paket','price'=>'ab 500 €','description'=>'3 Stories']],
 'faq'=>[['q'=>'Wie buche ich dich?','a'=>'Per E-Mail.'],['q'=>'','a'=>'x']],
 'contact'=>['heading'=>'Kontakt','text'=>'Schreib mir','email'=>'mia@example.org','agency'=>'Agentur X'],
 'display'=>['show_news'=>false]];
$c=elvado_tc_clean(elvado_creator_schema(),$in);
t('Text bereinigt, Bild unsicher → leer',$c['profile']['name']==='Mia Muster'&&$c['profile']['cover']===''&&!str_contains($c['profile']['bio'],'<script>'));
t('Zahlen/Plattformen: Pflichtfelder, Auswahl, URL',count($c['stats'])===1&&count($c['platforms'])===2&&$c['platforms'][1]['platform']==='instagram');
t('Links: ohne Ziel entfallen, Symbol-Auswahl',count($c['links'])===6&&$c['links'][5]['icon']==='link'&&$c['links'][1]['title']==='Heiß jetzt');
t('Disclosure-Vorgabe vorhanden',str_contains($c['contact']['disclosure'],'Affiliate'));
t('Listen ohne Pflichtfeld entfallen (Highlight, Feed-Art, FAQ)',count($c['highlights'])===1&&$c['feed'][1]['kind']==='photo'&&count($c['faq'])===1);
elvado_tc_save($data,'creator',$in);t('Speichern/Laden',elvado_tc_load($data,'creator')==$c);
t('Menü inaktiv ohne Theme',elvado_tc_state($data)['creator']['active']===false);
@mkdir(ELVADO_WP_DATA,0775,true);touch(ELVADO_WP_DATA.'/front-on');update_option('stylesheet','elvado-creator');update_option('template','elvado-creator');
t('Menü aktiv bei aktivem Theme, Band-Menü nicht',elvado_tc_state($data)['creator']['active']===true&&elvado_tc_state($data)['band']['active']===false);
elvado_wp_boot(['theme'=>true]);
t('Theme geladen',get_stylesheet()==='elvado-creator');
$r=page('/');$b=$r['body'];
t('Startseite 200',$r['status']===200,(string)$r['status']);
t('Profil: Name, Handle, Themen, Bio, Knopf',str_contains($b,'>Mia Muster</h1>')&&str_contains($b,'@mia')&&str_contains($b,'cr-chip')&&str_contains($b,'Zweiter Absatz')&&str_contains($b,'Anfragen'));
t('Zahlen und Plattformen',str_contains($b,'1,2 Mio.')&&str_contains($b,'cr-plat')&&str_contains($b,'insta.example/mia'));
t('Highlights, Feed (Reel-Etikett)',str_contains($b,'cr-hl')&&str_contains($b,'Paris')&&str_contains($b,'cr-post')&&str_contains($b,'>Reel<'));
t('Links: hervorgehoben zuerst, Zeitfenster beachtet',strpos($b,'Heiß jetzt')<strpos($b,'>Normal<')&&str_contains($b,'Aktion')&&!str_contains($b,'Abgelaufen')&&!str_contains($b,'a.example/soon')&&str_contains($b,'class="cr-link hot"'));
t('Werbung: Anzeige-Etikett, rel=sponsored, Hinweistext',str_contains($b,'rel="sponsored nofollow noopener"')&&str_contains($b,'>Anzeige<')&&str_contains($b,'Affiliate-Links'));
t('Normale Links ohne sponsored',str_contains($b,'href="https://a.example/1" rel="noopener"'));
t('Empfehlungen: Code mit Kopieren-Knopf, Vorteil; nicht beworbenes Produkt ohne Anzeige',str_contains($b,'data-copy="MIA15"')&&str_contains($b,'−15 %')&&str_contains($b,'shop.example/b" rel="noopener"'));
t('Videos: Player erst nach Klick, Fremdanbieter nicht',str_contains($b,'data-embed="https://www.youtube-nocookie.com/embed/abcdefghijk"')&&!str_contains($b,'<iframe')&&!str_contains($b,'other.example'));
t('Drops: nächster mit Countdown, vergangener fehlt, weiterer in Liste',str_contains($b,'data-countdown=')&&str_contains($b,'Launch')&&str_contains($b,'Später')&&!str_contains($b,'Vorbei'));
t('Kooperationen, Mediakit (Zielgruppe, Paket, Download), FAQ',str_contains($b,'Marke A')&&str_contains($b,'Alter 18–34')&&str_contains($b,'Story-Paket')&&str_contains($b,'kit.pdf')&&str_contains($b,'<summary>Wie buche ich dich?'));
t('Kontakt mit E-Mail',str_contains($b,'mailto:mia@example.org'));
t('News ausgeschaltet',!str_contains($b,'id="news"'));
preg_match('~<script type="application/ld\+json">(.*?)</script>~s',$b,$m);$ld=json_decode($m[1]??'',true);
t('Strukturdaten: Person mit sameAs + FAQ',is_array($ld)&&$ld['@graph'][0]['@type']==='Person'&&count($ld['@graph'][0]['sameAs'])===2&&$ld['@graph'][1]['@type']==='FAQPage');
t('Handy-Leiste mit Schnellzugriff',str_contains($b,'cr-mnav')&&str_contains($b,'/#links')&&str_contains($b,'/#kontakt')&&str_contains($b,'has-mnav'));
t('Standard: Sunset, keine eigene Akzentfarbe',!str_contains($b,'scheme-')&&!str_contains($b,'elvado-creator-vars'));
set_theme_mod('cr_scheme','night');set_theme_mod('cr_shape','sharp');set_theme_mod('cr_font','editorial');set_theme_mod('cr_accent','#ffee00');
$b2=page('/')['body'];t('Customizer: Farbwelt, Form, Schrift, Akzent mit dunkler Textfarbe',str_contains($b2,'scheme-night')&&str_contains($b2,'shape-sharp')&&str_contains($b2,'font-editorial')&&str_contains($b2,'--accent:#ffee00')&&str_contains($b2,'--on-accent:#111111'));
// Link-Seite
$lp=page('/links/');
t('Link-Seite /links/ (auch ohne CMS-Seite) mit Status 200',$lp['status']===200,(string)$lp['status']);
t('Link-Seite: Profil, Plattformen, Knopfliste, ohne Seitenkopf',str_contains($lp['body'],'cr-lp')&&str_contains($lp['body'],'Mia Muster')&&str_contains($lp['body'],'cr-link')&&!str_contains($lp['body'],'site-header')&&str_contains($lp['body'],'link-page'));
t('Link-Seite: Titel',str_contains($lp['body'],'<title>Mia Muster – Links</title>'));
t('Link-Seite ohne Handy-Leiste und ohne Fremdinhalte',!str_contains($lp['body'],'cr-mnav')&&!str_contains($lp['body'],'<iframe'));
// Zusammenspiel mit leerer Konfiguration
elvado_tc_save($data,'creator',['profile'=>['name'=>'Nur Name']]);elvado_cr_cfg(true);
$b3=page('/')['body'];t('Nur Name: Seite funktioniert, keine leeren Abschnitte',str_contains($b3,'>Nur Name</h1>')&&!str_contains($b3,'id="links"')&&!str_contains($b3,'id="feed"')&&!str_contains($b3,'cr-mnav')&&!str_contains($b3,'cr-stats'));
t('Leere Link-Seite ohne Fehler',page('/links/')['status']===200);
// Erweiterbarkeit
elvado_tc_save($data,'creator',$in);elvado_cr_cfg(true);
add_filter('elvado_cr_sections',function($s){ array_splice($s,2,0,['shoplink']);return $s; });
add_filter('elvado_cr_section_shoplink',fn($h)=>'<section id="shoplink">SHOP-PLUGIN</section>');
$seen=[];add_action('elvado_cr_after_section',function($id) use(&$seen){ $seen[]=$id; });add_action('wp_footer',function(){ echo '<!--cr-footer-plugin-->'; });
$b4=page('/')['body'];
t('Plugin-Abschnitt an gewünschter Stelle, Haken je Abschnitt, wp_footer',strpos($b4,'SHOP-PLUGIN')>strpos($b4,'id="highlights"')&&strpos($b4,'SHOP-PLUGIN')<strpos($b4,'id="links"')&&in_array('shoplink',$seen,true)&&str_contains($b4,'<!--cr-footer-plugin-->')&&isset($GLOBALS['wp_registered_sidebars']['front-extra']));
$s2=page('/beitrag-1/');t('Beitrag im Creator-Layout',$s2['status']===200&&str_contains($s2['body'],'site-content')&&str_contains($s2['body'],'cr-mnav'));
foreach(['/ueber-uns/','/category/news/','/?s=Beitrag','/gibtsnicht/'] as $u){ $x=page($u);t("Unterseite $u",in_array($x['status'],[200,404],true)&&str_contains($x['body'],'site-footer'),(string)$x['status']); }
require_once __DIR__.'/../cms/wp/customizer-api.php';$ids=array_keys(elvado_wpc_items()['items']);
t('Customizer: Creator-Einstellungen',in_array('cr_scheme',$ids,true)&&in_array('cr_accent',$ids,true)&&in_array('cr_shape',$ids,true)&&in_array('cr_font',$ids,true));
system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

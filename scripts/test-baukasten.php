<?php
// Prüft das Baukasten-Theme (elvado-baukasten): Abschnitte der Startseite, Reihenfolge, Customizer-Werte, Bereinigung. Aufruf: php scripts/test-baukasten.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/rrw-bk-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/cms');mkdir($tmp.'/wp-content/themes');
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
$GLOBALS['RRW_SITE']=json_decode((string)file_get_contents($tmp.'/cms/site.json'),true);
update_option('stylesheet','elvado-baukasten');update_option('template','elvado-baukasten');
rrw_wp_boot(['theme'=>true]);
t('Theme aktiv',get_stylesheet()==='elvado-baukasten');
$r=page('/');
t('Startseite 200',$r['status']===200,(string)$r['status']);
t('Standard: Hero, Vorteile, Beiträge, Aufruf in Reihenfolge',(function($b){ $p=[strpos($b,'bk-hero'),strpos($b,'bk-features'),strpos($b,'bk-posts'),strpos($b,'bk-cta')];return !in_array(false,$p,true)&&$p===array_values(array_unique($p))&&$p===(function($x){sort($x);return $x;})($p); })($r['body']));
t('Hero nimmt Website-Titel',str_contains($r['body'],'<h1>Mein Radio</h1>'));
t('Beiträge im Abschnitt (3)',substr_count($r['body'],'class="post-card')===3&&str_contains($r['body'],'Beitrag 5'));
t('Kein Seitenleisten-Raster auf der Baukasten-Startseite',!str_contains($r['body'],'site-content'));
t('CSS-Variablen',str_contains($r['body'],'--accent:#2563eb')&&str_contains($r['body'],'--max:1120px'));
// Reihenfolge ändern, Abschnitte entfernen, Werte setzen
foreach(['slot_1'=>'cta','slot_2'=>'text','slot_3'=>'text','slot_4'=>'none','color_accent'=>'#ff0000','content_width'=>'5000','cta_title'=>'<b>Jetzt</b>','text_body'=>'Hallo <script>alert(1)</script><em>Welt</em>','custom_css'=>'a{color:red}</style><script>x</script>','font_head'=>'gibtsnicht','hero_btn_url'=>'javascript:alert(1)'] as $k=>$v)set_theme_mod($k,$v);
$r=page('/');if(getenv('BK_DUMP'))file_put_contents(getenv('BK_DUMP'),$r['body']);
t('Neue Reihenfolge: Aufruf vor Text, Text nur einmal',strpos($r['body'],'bk-cta')<strpos($r['body'],'Über uns</h2>')&&substr_count($r['body'],'Hallo')===1&&!str_contains($r['body'],'bk-hero')&&!str_contains($r['body'],'bk-features'));
t('Akzentfarbe übernommen',str_contains($r['body'],'--accent:#ff0000'));
t('Breite begrenzt (1600)',str_contains($r['body'],'--max:1600px'));
t('Unbekannte Schrift fällt zurück',str_contains($r['body'],'--head-font:system-ui'));
t('Aufruf-Titel ohne Tags',str_contains($r['body'],'>Jetzt</h2>')&&!str_contains($r['body'],'<b>'));
t('Text: Script entfernt, Formatierung bleibt',!str_contains($r['body'],'<script>alert')&&str_contains($r['body'],'<em>Welt</em>'));
t('Eigenes CSS kann Style-Tag nicht verlassen',!str_contains($r['body'],'</style><script>x'));
set_theme_mod('slot_1','hero');set_theme_mod('hero_btn_label','Los');
$r=page('/');
t('Unsichere Button-URL wird entschärft',!str_contains($r['body'],'javascript:'));
// Unterseiten behalten das normale Layout
$s=page('/beitrag-1/');
t('Beitrag mit Seitenleistenraster',$s['status']===200&&str_contains($s['body'],'site-content')&&str_contains($s['body'],'<h1 class="entry-title">Beitrag 1</h1>'));
foreach(['/ueber-uns/','/category/news/','/?s=Beitrag','/gibtsnicht/'] as $u){ $x=page($u);t("Unterseite $u",in_array($x['status'],[200,404],true)&&str_contains($x['body'],'site-footer'),(string)$x['status']); }
// Customizer kennt die Baukasten-Einstellungen
require_once __DIR__.'/../cms/wp/customizer-api.php';
$ids=array_keys(rrw_wpc_items()['items']);
t('Customizer: Abschnittspositionen und Farben',in_array('slot_1',$ids,true)&&in_array('slot_8',$ids,true)&&in_array('color_accent',$ids,true)&&in_array('custom_css',$ids,true),implode(',',array_slice($ids,0,5)));
$res=rrw_wpc_save(['slot_2'=>'features','color_accent'=>'#00ff00','content_width'=>'900']);
t('Customizer speichert',!empty($res['ok'])||isset($res['saved']),json_encode($res));
t('Gespeicherter Wert wirkt',get_theme_mod('color_accent')==='#00ff00'&&str_contains(page('/')['body'],'--accent:#00ff00'));
// Visueller Editor: gespeichertes Layout (mehrfach gleiche Typen, ausgeblendet, bereinigt)
$lay=elvado_bk_save_layout([
 ['id'=>'a1','type'=>'text','props'=>['title'=>'Eins <i>x</i>','body'=>'<p>Alpha</p><script>x()</script>','bg'=>'alt']],
 ['id'=>'a1','type'=>'text','props'=>['title'=>'Zwei','body'=>'Beta','bg'=>'boese','align'=>'center']],
 ['id'=>'h','type'=>'text','hidden'=>true,'props'=>['title'=>'Versteckt','body'=>'Gamma']],
 ['id'=>'f','type'=>'features','props'=>['title'=>'Karten','items'=>[['title'=>'K1','text'=>'T1'],['title'=>'','text'=>''],['title'=>'K2','text'=>'T2']],'columns'=>'9']],
 ['id'=>'sp','type'=>'spacer','props'=>['height'=>9999]],
 ['id'=>'p','type'=>'posts','props'=>['count'=>2,'category'=>'News']],
 ['type'=>'unbekannt'],['id'=>'b','type'=>'cta','props'=>['btn_label'=>'Go','btn_url'=>'javascript:alert(1)']],
]);
t('Layout bereinigt: unbekannter Typ entfällt, IDs eindeutig',count($lay)===7&&count(array_unique(array_column($lay,'id')))===7);
t('Layout: Auswahl/Zahl/Karten begrenzt',$lay[1]['props']['bg']==='default'&&$lay[4]['props']['height']===400&&count($lay[3]['props']['items'])===2&&$lay[3]['props']['columns']==='0');
$r=page('/');$b=$r['body'];
t('Gespeichertes Layout ersetzt die Customizer-Positionen',!str_contains($b,'bk-hero')&&str_contains($b,'>Eins x</h2>')&&str_contains($b,'>Zwei</h2>')&&str_contains($b,'K2'));
t('Gleicher Typ mehrfach, Reihenfolge',strpos($b,'Alpha')<strpos($b,'Beta')&&strpos($b,'Beta')<strpos($b,'K1'));
t('Ausgeblendeter Abschnitt fehlt',!str_contains($b,'Versteckt')&&!str_contains($b,'Gamma'));
t('Abschnitte mit Anker/data-bk',str_contains($b,'data-bk="a1"')&&str_contains($b,'bk-bg-alt'));
t('Script im Text entfernt',!str_contains($b,'<script>x()'));
t('Beitragskategorie-Filter, Anzahl',substr_count($b,'class="post-card')<=2);
t('Unsichere Button-URL im Aufruf entfernt',!str_contains($b,'javascript:'));
t('Spalten-Raster unbegrenzt nur 0 (automatisch)',!str_contains($b,'grid-template-columns:repeat(9'));
update_option('elvado_bk_layout',[]);
t('Leeres gespeichertes Layout: Startseite im Normallayout',str_contains(page('/')['body'],'site-content'));
delete_option('elvado_bk_layout');
t('Ohne gespeichertes Layout gelten die Positionen wieder',str_contains(page('/')['body'],'bk-hero'));
// Seitenleiste auf der Startseite (Customizer „Seitenleiste auch auf der Startseite“)
update_option('elvado_bk_layout',elvado_bk_clean_layout([['id'=>'h','type'=>'hero','props'=>['title'=>'Kopf']],['id'=>'t','type'=>'text','props'=>['body'=>'Mittelteil']],['id'=>'c','type'=>'cta','props'=>['title'=>'Ruf']]]));
update_option('sidebars_widgets',['sidebar-1'=>['search-1'],'array_version'=>3]);update_option('widget_search',[1=>['title'=>'Suche'],'_multiwidget'=>1]);
$off=page('/')['body'];t('Seitenleiste auf der Startseite standardmäßig aus',!str_contains($off,'bk-home-grid'));
set_theme_mod('home_sidebar',true);$on=page('/')['body'];
t('Seitenleiste an: Hero volle Breite, danach Raster mit Hauptspalte und Seitenleiste',strpos($on,'bk-hero')!==false&&strpos($on,'bk-hero')<strpos($on,'bk-home-grid')&&strpos($on,'bk-home-main')<strpos($on,'Mittelteil')&&strpos($on,'Mittelteil')<strpos($on,'id="sidebar"')&&str_contains($on,'widget_search'));
t('Seitenleiste an: Aufruf liegt in der Hauptspalte',strpos($on,'bk-cta')<strpos($on,'id="sidebar"')&&strpos($on,'bk-cta')>strpos($on,'bk-home-main'));
update_option('sidebars_widgets',['sidebar-1'=>[],'array_version'=>3]);
t('Ohne aktive Widgets kein Raster, auch wenn eingeschaltet',!str_contains(page('/')['body'],'bk-home-grid'));
set_theme_mod('home_sidebar',false);
// Erweiterbarkeit: Plugin-Haken je Abschnitt
$hk=[];add_action('elvado_bk_after_section',function($sec) use(&$hk){ $hk[]=$sec['type']; });add_action('elvado_bk_after_sections',function(){ echo '<!--bk-plugin-->'; });
update_option('elvado_bk_layout',elvado_bk_clean_layout([['type'=>'text','props'=>['body'=>'Hallo']],['type'=>'cta','props'=>['title'=>'X']]]));
$bq=page('/')['body'];t('Plugin-Haken je Abschnitt und am Ende',$hk===['text','cta']&&str_contains($bq,'<!--bk-plugin-->'));
// Entwurf (Live Builder): nur in der geprüften Vorschau sichtbar, öffentlich unverändert; Veröffentlichen/Verwerfen
update_option('elvado_bk_layout',elvado_bk_clean_layout([['type'=>'text','props'=>['title'=>'Öffentlich','body'=>'A']]]));
t('Ohne Entwurf kein Entwurfsstand',elvado_bk_draft_layout()===null);
$dr=elvado_bk_save_draft([['type'=>'text','props'=>['title'=>'Nur Entwurf','body'=>'B']]]);
t('Entwurf gespeichert und bereinigt',count($dr)===1&&elvado_bk_draft_layout()[0]['props']['title']==='Nur Entwurf');
t('Öffentlich bleibt der veröffentlichte Stand',elvado_bk_active_layout()[0]['props']['title']==='Öffentlich'&&str_contains(page('/')['body'],'Öffentlich')&&!str_contains(page('/')['body'],'Nur Entwurf'));
$GLOBALS['rrw_wp_preview_theme']='elvado-baukasten';
t('Geprüfte Vorschau zeigt den Entwurf',elvado_bk_active_layout()[0]['props']['title']==='Nur Entwurf'&&str_contains(page('/')['body'],'Nur Entwurf'));
$GLOBALS['rrw_wp_preview_theme']='anderes-theme';
t('Vorschau eines anderen Themes zeigt den Entwurf nicht',elvado_bk_active_layout()[0]['props']['title']==='Öffentlich');
unset($GLOBALS['rrw_wp_preview_theme']);
elvado_bk_discard_draft();t('Verwerfen entfernt den Entwurf',elvado_bk_draft_layout()===null);
system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

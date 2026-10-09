<?php
// Prüft das Baukasten-Theme (elvado-baukasten): Abschnitte der Startseite, Reihenfolge, Customizer-Werte, Bereinigung. Aufruf: php scripts/test-baukasten.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/elvado-bk-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/cms');mkdir($tmp.'/wp-content/themes');
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
update_option('stylesheet','elvado-baukasten');update_option('template','elvado-baukasten');
elvado_wp_boot(['theme'=>true]);
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
$ids=array_keys(elvado_wpc_items()['items']);
t('Customizer: Abschnittspositionen und Farben',in_array('slot_1',$ids,true)&&in_array('slot_8',$ids,true)&&in_array('color_accent',$ids,true)&&in_array('custom_css',$ids,true),implode(',',array_slice($ids,0,5)));
$res=elvado_wpc_save(['slot_2'=>'features','color_accent'=>'#00ff00','content_width'=>'900']);
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
elvado_bk_save_layout([]);
t('Leeres gespeichertes Layout: Startseite im Normallayout',str_contains(page('/')['body'],'site-content'));
elvado_bk_store()->unpublish('home',elvado_bk_actor());
t('Ohne gespeichertes Layout gelten die Positionen wieder',str_contains(page('/')['body'],'bk-hero'));
// Seitenleiste auf der Startseite (Customizer „Seitenleiste auch auf der Startseite“)
elvado_bk_save_layout(elvado_bk_clean_layout([['id'=>'h','type'=>'hero','props'=>['title'=>'Kopf']],['id'=>'t','type'=>'text','props'=>['body'=>'Mittelteil']],['id'=>'c','type'=>'cta','props'=>['title'=>'Ruf']]]));
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
elvado_bk_save_layout(elvado_bk_clean_layout([['type'=>'text','props'=>['body'=>'Hallo']],['type'=>'cta','props'=>['title'=>'X']]]));
$bq=page('/')['body'];t('Plugin-Haken je Abschnitt und am Ende',$hk===['text','cta']&&str_contains($bq,'<!--bk-plugin-->'));
// Entwurf (Live Builder): nur in der geprüften Vorschau sichtbar, öffentlich unverändert; Veröffentlichen/Verwerfen
elvado_bk_save_layout(elvado_bk_clean_layout([['type'=>'text','props'=>['title'=>'Öffentlich','body'=>'A']]]));
t('Ohne Entwurf kein Entwurfsstand',elvado_bk_draft_layout()===null);
$dr=elvado_bk_save_draft([['type'=>'text','props'=>['title'=>'Nur Entwurf','body'=>'B']]]);
t('Entwurf gespeichert und bereinigt',count($dr)===1&&elvado_bk_draft_layout()[0]['props']['title']==='Nur Entwurf');
t('Öffentlich bleibt der veröffentlichte Stand',elvado_bk_active_layout()[0]['props']['title']==='Öffentlich'&&str_contains(page('/')['body'],'Öffentlich')&&!str_contains(page('/')['body'],'Nur Entwurf'));
$GLOBALS['elvado_wp_preview_theme']='elvado-baukasten';
t('Geprüfte Vorschau zeigt den Entwurf',elvado_bk_active_layout()[0]['props']['title']==='Nur Entwurf'&&str_contains(page('/')['body'],'Nur Entwurf'));
$GLOBALS['elvado_wp_preview_theme']='anderes-theme';
t('Vorschau eines anderen Themes zeigt den Entwurf nicht',elvado_bk_active_layout()[0]['props']['title']==='Öffentlich');
unset($GLOBALS['elvado_wp_preview_theme']);
elvado_bk_discard_draft();t('Verwerfen entfernt den Entwurf',elvado_bk_draft_layout()===null);
// Komponenten-Registry: Schema kommt von dort; Sichtbarkeit je Gerät, Zeitfenster, Zielgruppe und geräteabhängige Werte (nur wenn gesetzt – sonst bleibt die Ausgabe unverändert)
$plain=elvado_bk_clean_layout([['id'=>'p1','type'=>'text','props'=>['title'=>'Plain','body'=>'x']]]);
t('Ohne Sichtbarkeit/Responsive keine neuen Schlüssel und kein Zusatz-CSS',!isset($plain[0]['visibility'])&&!isset($plain[0]['responsive']));
elvado_bk_save_layout($plain);$h0=page('/')['body'];if(getenv('DBG'))echo substr($h0,strpos($h0,'bk-responsive')-200,500),"\n";t('Ohne Zusätze kein bk-responsive-Block',!str_contains($h0,'bk-responsive')&&!str_contains($h0,'ep-hide-'));
$vis=elvado_bk_clean_layout([['id'=>'v1','type'=>'text','props'=>['title'=>'Nur Desktop','body'=>'x'],'visibility'=>['devices'=>['desktop']]],
  ['id'=>'v2','type'=>'hero','props'=>['title'=>'Hero','height'=>400],'responsive'=>['mobile'=>['height'=>150],'tablet'=>['height'=>250]]],
  ['id'=>'v3','type'=>'text','props'=>['title'=>'Zukunft','body'=>'x'],'visibility'=>['from'=>'2999-01-01 00:00']],
  ['id'=>'v4','type'=>'text','props'=>['title'=>'Nur Mitglieder','body'=>'x'],'visibility'=>['audience'=>'members']],
  ['id'=>'v5','type'=>'text','props'=>['title'=>'Nirgends','body'=>'x'],'visibility'=>['devices'=>[]]]]);
t('Sichtbarkeit und Responsive werden gespeichert',($vis[0]['visibility']['devices']??null)===['desktop']&&($vis[1]['responsive']['mobile']['height']??0)===150&&isset($vis[2]['visibility']));
elvado_bk_save_layout($vis);$hv=page('/')['body'];
t('Nur Desktop: Klassen für Tablet und Mobil',preg_match('/<section class="ep-hide-tablet ep-hide-mobile bk-section[^"]*" id="bk-v1"/',$hv)===1);
t('Geräte-CSS und geräteabhängige Hero-Höhe',str_contains($hv,'<style id="bk-responsive">')&&str_contains($hv,'.ep-hide-mobile{display:none!important}')&&str_contains($hv,'[data-bk="v2"]{min-height:400px;}')&&str_contains($hv,'@media (max-width:640px){[data-bk="v2"]{min-height:150px;}}')&&str_contains($hv,'@media (max-width:1024px){[data-bk="v2"]{min-height:250px;}}'));
t('Zeitfenster, Zielgruppe und „kein Gerät“ blenden aus',!str_contains($hv,'Zukunft')&&!str_contains($hv,'Nur Mitglieder')&&!str_contains($hv,'Nirgends'));
t('Das Schema des Themes kommt aus der Registry (die acht eigenen zuerst, dann native Komponenten)',elvado_bk_schema()===elvado_bk_registry()->legacySchema(elvado_bk_supported())&&array_slice(array_keys(elvado_bk_schema()),0,8)===ELVADO_BK_TYPES&&isset(elvado_bk_schema()['button'],elvado_bk_schema()['columns'])&&!isset(elvado_bk_schema()['header'],elvado_bk_schema()['footer'],elvado_bk_schema()['form']));
// Phase 7: native und WordPress-Komponenten im Baukasten, Layout-Speicher (Entwurf, Fassungen, Termin)
add_shortcode('etest',fn($a)=>'<i>SC-OK</i>');
$mix=elvado_bk_clean_layout([
  ['id'=>'c1','type'=>'columns','props'=>['columns'=>2],'children'=>[['id'=>'k1','type'=>'button','props'=>['label'=>'Los','url'=>'/x']],['id'=>'k2','type'=>'text','props'=>['title'=>'Im Container','body'=>'<p>Kind</p>']],['type'=>'header','props'=>['title'=>'Nein']]]],
  ['id'=>'b1','type'=>'button','props'=>['label'=>'Einzeln','url'=>'javascript:x','style'=>'outline']],['id'=>'s1','type'=>'wp_shortcode','props'=>['shortcode'=>'[etest foo="1"]']],['id'=>'s2','type'=>'wp_shortcode','props'=>['shortcode'=>'kein shortcode']],
  ['id'=>'w1','type'=>'wp_block','props'=>['markup'=>'<!-- wp:paragraph --><p>BLOCK-OK</p><!-- /wp:paragraph -->']],
  ['type'=>'header','props'=>['title'=>'Kopf']],['type'=>'footer'],['type'=>'form','props'=>['form_id'=>'x']],['id'=>'t1','type'=>'text','props'=>['title'=>'Alt','body'=>'x']]]);
$types=array_column($mix,'type');
t('Nicht unterstützte Komponenten (Header, Footer, Formular) fallen heraus',!in_array('header',$types,true)&&!in_array('footer',$types,true)&&!in_array('form',$types,true)&&in_array('columns',$types,true)&&in_array('wp_shortcode',$types,true));
t('Kinder: nur ausgebbare bleiben, Header-Kind fällt heraus',array_column($mix[0]['children'],'type')===['button','text']);
elvado_bk_save_layout($mix);$hm=page('/')['body'];
t('Native Komponenten werden vom Theme ausgegeben (Button maskiert, javascript: entfernt)',str_contains($hm,'class="ep-btn ep-btn-outline" href="#">Einzeln</a>')&&!str_contains($hm,'javascript:'));
t('Spalten mit Kindern (Button und Baukasten-Text im Container)',preg_match('/ep-c-columns.*ep-c-button.*Los.*bk-text.*Im Container/s',$hm)===1);
t('Shortcode und Block werden mit WordPress ausgegeben, ungültiger Shortcode nicht',str_contains($hm,'<i>SC-OK</i>')&&str_contains($hm,'BLOCK-OK')&&!str_contains($hm,'kein shortcode'));
t('Basis-CSS der Komponenten im bk-responsive-Block',str_contains($hm,'<style id="bk-responsive">')&&str_contains($hm,'.ep-btn{')&&str_contains($hm,'grid-template-columns:repeat(2,minmax(0,1fr))'));
// Layout-Speicher
$st=elvado_bk_store();$ac=elvado_bk_actor();
elvado_bk_save_layout([['id'=>'a','type'=>'text','props'=>['title'=>'Fassung A','body'=>'x']]],'A');elvado_bk_save_layout([['id'=>'a','type'=>'text','props'=>['title'=>'Fassung B','body'=>'x']]],'B');
$rv=$st->revisions('home');t('Fassungen werden geführt',count($rv)>=2&&$rv[0]['label']==='B');
$nA=0;foreach($rv as $r)if($r['label']==='A')$nA=$r['n'];
$st->rollback('home',$nA,$ac);t('Rollback stellt Fassung A wieder her',elvado_bk_saved_layout()[0]['props']['title']==='Fassung A'&&str_contains(page('/')['body'],'Fassung A'));
elvado_bk_save_draft([['id'=>'a','type'=>'text','props'=>['title'=>'Geplante Fassung','body'=>'x']]],date('Y-m-d H:i',time()+3600));
t('Geplanter Entwurf ist öffentlich noch nicht sichtbar',!str_contains(page('/')['body'],'Geplante Fassung')&&elvado_bk_draft_layout()!==null);
$due=$st->get('home',time()+7200);t('Termin erreicht: Entwurf wird veröffentlicht',($due['published']['layout'][0]['props']['title']??'')==='Geplante Fassung'&&$due['draft']===null);
t('Ungültiger Termin',(function(){try{elvado_bk_save_draft([],'bald');return false;}catch(\InvalidArgumentException $e){return true;}})());
$st->unpublish('home',$ac);t('Zurücknehmen: kein veröffentlichtes Layout, Customizer-Positionen gelten wieder',$st->published('home')===null&&elvado_bk_saved_layout()===null&&count(elvado_bk_active_layout())>0);
update_option('elvado_bk_layout',elvado_bk_clean_layout([['id'=>'alt','type'=>'text','props'=>['title'=>'Altbestand','body'=>'x']]]));
t('Ältere Installationen (Option) werden weiter gelesen, solange nichts über den Speicher veröffentlicht wurde',elvado_bk_saved_layout()[0]['props']['title']==='Altbestand'&&str_contains(page('/')['body'],'Altbestand'));
elvado_bk_save_layout([['id'=>'neu','type'=>'text','props'=>['title'=>'Neu im Speicher','body'=>'x']]]);
t('Danach hat der Speicher Vorrang',elvado_bk_saved_layout()[0]['props']['title']==='Neu im Speicher');
system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

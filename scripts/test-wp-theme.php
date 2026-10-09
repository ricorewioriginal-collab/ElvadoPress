<?php
// Prüft die Theme-Laufzeit: Routing, Template-Hierarchie, Standard-Theme und (falls installiert) echte WordPress-Themes. Aufruf: php scripts/test-wp-theme.php [theme-slug …]
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/elvado-wpt-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/cms');mkdir($tmp.'/wp-content/themes');
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
function page(string $uri,string $m='GET',array $post=[]): array { return elvado_wp_dispatch($uri,$m,[],$post); }
$GLOBALS['ELVADO_SITE']=json_decode((string)file_get_contents($tmp.'/cms/site.json'),true);
update_option('posts_per_page',2);
// Kindprozess: ein echtes Theme in frischer Laufzeit prüfen (functions.php wird nur beim Start geladen)
$child=($argv[1]??'')==='--child'?($argv[2]??''):'';
if($child!==''){
    $dst=WP_CONTENT_DIR.'/themes/'.$child;system('cp -r '.escapeshellarg($themesSrc.'/'.$child).' '.escapeshellarg($dst));
    update_option('stylesheet',$child);update_option('template',$child);
}
elvado_wp_boot(['theme'=>true]);
if($child!==''){
    foreach(['/'=>'Startseite','/beitrag-1/'=>'Beitrag','/ueber-uns/'=>'Seite','/category/news/'=>'Archiv','/?s=Beitrag'=>'Suche','/gibtsnicht/'=>'404'] as $u=>$label){
        $rr=page($u);if(getenv('WPT_DUMP')){ @mkdir(getenv('WPT_DUMP'),0775,true);file_put_contents(getenv('WPT_DUMP').'/'.$child.'-'.preg_replace('/[^a-z0-9]+/i','_',$label).'.html',$rr['body']); }$ok=in_array($rr['status'],[200,404],true)&&strlen($rr['body'])>500&&str_contains($rr['body'],'</html>');
        $ok2=$u!=='/'||str_contains($rr['body'],'Beitrag 5');
        if(getenv("WPT_DEBUG")&&strlen($rr["body"])<=500)echo "BODY[$u]: ".$rr["body"]."\n";
        t("Theme $child: $label",$ok&&$ok2,'Status '.$rr['status'].' Länge '.strlen($rr['body']));
    }
    $log=@file_get_contents(ELVADO_WP_DATA.'/debug.log');if(getenv("WPT_DEBUG"))echo $log;if($log&&preg_match_all('/Seite .*|Hook .*|Theme .*/',$log,$mm))echo "  Protokoll ($child): ".substr(implode(' | ',array_slice($mm[0],-3)),0,300)."\n";
    system('rm -rf '.escapeshellarg($tmp));
    echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);
}
t('Standard-Theme aktiv',get_stylesheet()==='elvado-classic'&&is_file(get_stylesheet_directory().'/index.php'));

$r=page('/');
t('Startseite 200',$r['status']===200,(string)$r['status']);
t('Titel-Tag',str_contains($r['body'],'<title>Mein Radio'),'');
t('Beiträge sichtbar (neueste zuerst)',strpos($r['body'],'Beitrag 5')!==false&&strpos($r['body'],'Beitrag 5')<strpos($r['body'],'Beitrag 4'));
t('Seitenaufteilung (2 pro Seite)',substr_count($r['body'],'class="post-card')===2&&str_contains($r['body'],'page-numbers'));
t('Hauptmenü aus CMS',str_contains($r['body'],'Über uns')&&str_contains($r['body'],'menu-item'));
t('Stylesheet eingebunden',str_contains($r['body'],'elvado-classic-css')&&str_contains($r['body'],'style.css'));
t('Logo',str_contains($r['body'],'custom-logo'));
t('Fußmenü',str_contains($r['body'],'Impressum'));
t('Sprache',str_contains($r['body'],'lang="de-DE"'));
t('Seitenleiste mit Standard-Widgets',str_contains($r['body'],'widget_search')&&str_contains($r['body'],'widget_recent_entries'));
$r2=page('/page/2/');t('Seite 2',$r2['status']===200&&preg_match('/entry-title"><a[^>]*>Beitrag 3/',$r2['body'])&&!preg_match('/entry-title"><a[^>]*>Beitrag 5/',$r2['body']));
t('Seite jenseits des Endes → 404',page('/page/9/')['status']===404);

$s=page('/beitrag-1/');
t('Beitrag 200',$s['status']===200&&str_contains($s['body'],'<h1 class="entry-title">Beitrag 1</h1>'));
t('Shortcode im Beitrag aufgelöst',str_contains($s['body'],'data-elvado-widget')&&!str_contains($s['body'],'[forum]'));
t('Autor/Datum',str_contains($s['body'],'Anna Autor')&&str_contains($s['body'],'10.01.2026'));
t('Kategorie-Link',str_contains($s['body'],'/category/news/'));
t('Titelbild',str_contains($s['body'],'/img/a.jpg'));
t('Kommentarformular',str_contains($s['body'],'wp-comments-post.php')&&str_contains($s['body'],'name="comment"'));
t('Beitrags-Navigation',str_contains($s['body'],'post-navigation'));
$x=page('/beitrag-2/');t('Titel wird escaped (kein <script>)',!str_contains($x['body'],'<script>alert(1)')&&str_contains($x['body'],'&lt;script&gt;'));
t('Seite aus CMS',(function(){ $p=page('/ueber-uns/');return $p['status']===200&&str_contains($p['body'],'Wir sind ein Radio.')&&str_contains($p['body'],'<h1 class="entry-title">Über uns</h1>'); })());
t('.html-Endung bei Seiten',page('/ueber-uns.html')['status']===200);
t('Portal-Bereich als Theme-Seite (/sender/) mit Verweis ins Portal',(function(){ $p=page('/sender/');return $p['status']===200&&str_contains($p['body'],'Unsere Sender')&&str_contains($p['body'],'Alle Sender im Überblick.')&&str_contains($p['body'],'href="http://example.test/#sender"'); })());
t('Menüpunkt „Sender“ führt auf die Theme-Seite',str_contains(page('/')['body'],'href="http://example.test/sender/"'));

$c=page('/category/news/');$cm=preg_match('#<main.*?</main>#s',$c['body'],$mm1)?$mm1[0]:'';t('Kategorie-Archiv',$c['status']===200&&str_contains($c['body'],'Kategorie: News')&&str_contains($cm,'Beitrag 5')&&!str_contains($cm,'Beitrag 4'));
t('unbekannte Kategorie → 404',page('/category/gibtsnicht/')['status']===404);
$tg=page('/tag/musik/');$tm=preg_match('#<main.*?</main>#s',$tg['body'],$mm2)?$mm2[0]:'';t('Schlagwort-Archiv',$tg['status']===200&&str_contains($tm,'Beitrag 2')&&!str_contains($tm,'Beitrag 5'));
$au=page('/author/anna-autor/');t('Autor-Archiv',$au['status']===200&&str_contains($au['body'],'Autor: Anna Autor'));
$se=page('/?s=Beitrag+4');t('Suche',$se['status']===200&&str_contains($se['body'],'Suchergebnisse')&&str_contains($se['body'],'Beitrag 4'));
$se0=page('/?s=nichtvorhanden');t('Suche ohne Treffer',$se0['status']===200&&str_contains($se0['body'],'Nichts gefunden'));
$d=page('/2026/03/');t('Datum-Archiv',$d['status']===200&&str_contains($d['body'],'Beitrag 3'));
$nf=page('/gibtsnicht/');t('404-Seite',$nf['status']===404&&str_contains($nf['body'],'Seite nicht gefunden'));
t('?p=ID',page('/?p=3')['status']===200&&str_contains(page('/?p=3')['body'],'Beitrag 3'));
t('Feed-Weiterleitung',page('/feed/')['status']===302);

/* Kommentare */
$cm=page('/wp-comments-post.php','POST',['comment_post_ID'=>'1','author'=>'Gast','comment'=>'Toller Beitrag!','comment_parent'=>'0','hp'=>'']);
t('Kommentar senden → Weiterleitung',$cm['status']===303&&str_contains($cm['headers']['Location'],'/beitrag-1/'));
$saved=json_decode((string)file_get_contents($tmp.'/cms/comments.json'),true);t('Kommentar gespeichert (zur Prüfung)',count($saved)===1&&$saved[0]['status']==='pending'&&$saved[0]['name']==='Gast');
t('ohne Name/Text abgelehnt',page('/wp-comments-post.php','POST',['comment_post_ID'=>'1','author'=>'','comment'=>''])['status']===400);
t('Honeypot',(function(){ $b=count(json_decode((string)file_get_contents($tmp=ELVADO_WP_CMS_DATA.'/comments.json'),true));page('/wp-comments-post.php','POST',['comment_post_ID'=>'1','author'=>'Bot','comment'=>'Spam','hp'=>'x']);return $b===count(json_decode((string)file_get_contents(ELVADO_WP_CMS_DATA.'/comments.json'),true)); })());
$all=json_decode((string)file_get_contents($tmp.'/cms/comments.json'),true);$all[0]['status']='approved';file_put_contents($tmp.'/cms/comments.json',json_encode($all));elvado_wp_cms_reset();
t('freigegebener Kommentar erscheint',str_contains(page('/beitrag-1/')['body'],'Toller Beitrag!'));
t('Kommentarzahl',get_comments_number(1)===1);

/* Fehlerisolierung */
mkdir($tmp.'/wp-content/themes/kaputt');
file_put_contents($tmp.'/wp-content/themes/kaputt/style.css',"/*\nTheme Name: Kaputt\nVersion: 1\n*/");
file_put_contents($tmp.'/wp-content/themes/kaputt/index.php',"<?php\nthrow new Exception('Vorlagenfehler');\n");
update_option('stylesheet','kaputt');update_option('template','kaputt');
$e=page('/');t('Theme-Fehler → 500-Seite statt weißer Seite',$e['status']===500&&str_contains($e['body'],'konnte nicht erstellt werden'));
update_option('stylesheet','elvado-classic');update_option('template','elvado-classic');
t('danach wieder normal',page('/')['status']===200);

/* Theme-Verwaltung: Liste, Aktivierung (Flag), Vorschau-Schlüssel */
require_once __DIR__.'/../cms/wp/installer.php';
$lt=array_column(elvado_wpi_list_themes(),null,'slug');
t('Theme-Liste enthält Standard-Theme',isset($lt['elvado-classic'])&&$lt['elvado-classic']['bundled']&&$lt['elvado-classic']['name']==='ElvadoPress Classic');
t('Aktivieren unbekannter Themes scheitert',elvado_wpi_activate_theme('gibts-nicht')!==null);
t('Aktivieren setzt Optionen und Flag',elvado_wpi_activate_theme('elvado-classic')===null&&is_file(ELVADO_WP_DATA.'/front-on')&&get_option('stylesheet')==='elvado-classic');
elvado_wpi_deactivate_theme();t('Deaktivieren entfernt Flag',!is_file(ELVADO_WP_DATA.'/front-on'));
$tok=elvado_wpi_preview_token('elvado-classic',1000);[$ts,$te,$tg]=explode('.',$tok);
t('Vorschau-Schlüssel signiert',$ts==='elvado-classic'&&$te==='1900'&&hash_equals(hash_hmac('sha256','elvado-classic|1900',wp_salt('preview')),$tg));
$_SERVER['REQUEST_URI']='/index.html';t('index.html wird zur Startseite',elvado_wp_dispatch('/index.html')['status']===200);

/* Echte WordPress-Themes (falls lokal installiert) */
$want=array_slice($argv,1);$found=[];
if(is_dir($themesSrc))foreach(scandir($themesSrc) as $d)if($d[0]!=='.'&&is_file($themesSrc.'/'.$d.'/style.css')&&(!$want||in_array($d,$want,true)))$found[]=$d;
foreach($found as $slug){
    echo "Theme $slug:\n";passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' --child '.escapeshellarg($slug),$rc);if($rc)$fail++;
}
system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

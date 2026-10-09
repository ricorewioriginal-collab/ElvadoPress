<?php
// Prüft die Link-Formen (Einfach ?p=, Beitragsname, Datum, eigene Struktur mit Basis, Hash-Form), Kategorie-/Schlagwort-Basis,
// Umleitung alter Adressen und die Portal-Bereiche als Theme-Seiten. Aufruf: php scripts/test-wp-links.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/elvado-wpl-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/cms');mkdir($tmp.'/wp-content/themes');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('ELVADO_WP_DATA',$tmp.'/cms/.wp');define('ELVADO_WP_CMS_DATA',$tmp.'/cms');$_SERVER['HTTP_HOST']='example.test';$_SERVER['REMOTE_ADDR']='203.0.113.5';
$news=[['id'=>1,'slug'=>'erster','title'=>'Erster','category'=>'News','tags'=>'musik','excerpt'=>'A','body_html'=>'<p>Inhalt eins</p>','status'=>'published','published_at'=>'2026-03-10 10:00:00','author'=>'Anna'],
       ['id'=>2,'slug'=>'zweiter','title'=>'Zweiter','category'=>'Events','tags'=>'','excerpt'=>'B','body_html'=>'<p>Inhalt zwei</p>','status'=>'published','published_at'=>'2026-04-02 10:00:00','author'=>'Anna']];
file_put_contents($tmp.'/cms/news.json',json_encode($news));
file_put_contents($tmp.'/cms/site.json',json_encode(['portal'=>['site_name'=>'Mein Radio'],'menus'=>['top'=>[],'bottom'=>[]],'pages'=>[['id'=>'start','slug'=>'willkommen','title'=>'Willkommen','type'=>'custom','enabled'=>true,'blocks_before'=>[['type'=>'html','html'=>'<p>Hallo Startseite</p>']],'blocks_after'=>[]],['id'=>'blogp','slug'=>'neuigkeiten','title'=>'Neuigkeiten','type'=>'custom','enabled'=>true,'blocks_before'=>[['type'=>'html','html'=>'<p>Seitentext Blog</p>']],'blocks_after'=>[]]]]));
mkdir($tmp.'/themes-native');
require __DIR__.'/_testdb.php';
require __DIR__.'/../cms/wp/load.php';require __DIR__.'/../cms/wp/router.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
$GLOBALS['ELVADO_SITE']=json_decode((string)file_get_contents($tmp.'/cms/site.json'),true);
update_option('stylesheet','elvado-classic');update_option('template','elvado-classic');
elvado_wp_boot(['theme'=>true]);
$posts=elvado_wp_cms_posts();$by=[];foreach($posts as $p)$by[$p->post_name]=$p;
$a=$by['erster'];$b=$by['zweiter'];
$H='http://example.test';
$get=fn(string $u)=>elvado_wp_dispatch($u,'GET',[],[]);
$getq=fn(string $path,array $q)=>elvado_wp_dispatch($path,'GET',$q,[]);

/* Standard */
t('Standard: /beitragsname/',get_permalink($a)===$H.'/erster/');
t('Standard: Beitrag erreichbar',$get('/erster/')['status']===200);

/* Datum und Name */
update_option('permalink_structure','/%year%/%monthnum%/%day%/%postname%/');
t('Datum+Name: Adresse',get_permalink($a)===$H.'/2026/03/10/erster/',get_permalink($a));
$r=$get('/2026/03/10/erster/');t('Datum+Name: erreichbar mit Inhalt',$r['status']===200&&str_contains($r['body'],'Inhalt eins'));
elvado_wp_dispatch('/2026/05/10/erster/','GET',[],[]);
t('Datum+Name: falsches Datum → Umleitung auf das richtige',elvado_wp_canonical_redirect('/2026/05/10/erster/')==='/2026/03/10/erster/');
t('Datum+Name: Monatsarchiv geht weiter',in_array($get('/2026/03/')['status'],[200,404],true));
update_option('permalink_structure','/%year%/%monthnum%/%postname%/');
t('Monat+Name: Adresse und Aufruf',get_permalink($b)===$H.'/2026/04/zweiter/'&&$get('/2026/04/zweiter/')['status']===200);

/* Numerisch, Basis und Kategorie */
update_option('permalink_structure','/archives/%post_id%');
t('Numerisch: Adresse',get_permalink($a)===$H.'/archives/'.$a->ID);
t('Numerisch: erreichbar',$get('/archives/'.$a->ID)['status']===200&&str_contains($get('/archives/'.$a->ID)['body'],'Inhalt eins'));
update_option('permalink_structure','/verzeichnis/%category%/%postname%/');
t('Verzeichnis/Kategorie/Name: Adresse',get_permalink($a)===$H.'/verzeichnis/news/erster/',get_permalink($a));
t('Verzeichnis/Kategorie/Name: erreichbar',$get('/verzeichnis/news/erster/')['status']===200);
update_option('permalink_structure','/blog/%postname%/');
t('Basis /blog/: Adresse und Aufruf',get_permalink($b)===$H.'/blog/zweiter/'&&$get('/blog/zweiter/')['status']===200);
t('Alte Adresse bleibt erreichbar (Umleitung folgt im Front-Controller)',$get('/zweiter/')['status']===200);
$GLOBALS['wp_query']=$GLOBALS['wp_the_query'];elvado_wp_dispatch('/zweiter/','GET',[],[]);
t('Alte Adresse → Umleitung auf /blog/zweiter/',elvado_wp_canonical_redirect('/zweiter/')==='/blog/zweiter/');
elvado_wp_dispatch('/blog/zweiter/','GET',[],[]);
t('Richtige Adresse → keine Umleitung',elvado_wp_canonical_redirect('/blog/zweiter/')===null);

/* Einfach (?p=) */
update_option('permalink_structure','');
t('Einfach: ?p=ID',get_permalink($a)===$H.'/?p='.$a->ID);
$r=$getq('/',['p'=>(string)$a->ID]);t('Einfach: ?p= liefert den Beitrag',$r['status']===200&&str_contains($r['body'],'Inhalt eins'));
$cat=get_term_by('slug','news','category');
t('Einfach: Kategorie ?cat=ID',$cat&&get_term_link($cat)===$H.'/?cat='.$cat->term_id);
$r=$getq('/',['cat'=>(string)$cat->term_id]);t('Einfach: ?cat= liefert die Kategorie',$r['status']===200&&str_contains($r['body'],'Erster'));
$r=$getq('/',['tag'=>'musik']);t('Einfach: ?tag= liefert das Schlagwort',$r['status']===200&&str_contains($r['body'],'Erster'));
elvado_wp_dispatch('/','GET',['p'=>(string)$a->ID],[]);
t('Einfach: keine Umleitung',elvado_wp_canonical_redirect('/?p='.$a->ID)===null);

/* Kategorie-/Schlagwort-Basis */
update_option('permalink_structure','/%postname%/');update_option('category_base','themen');update_option('tag_base','stichwort');
t('Kategorie-Basis in Adresse',get_term_link($cat)===$H.'/themen/news/');
t('Kategorie-Basis erreichbar',$get('/themen/news/')['status']===200&&str_contains($get('/themen/news/')['body'],'Erster'));
t('Alte Kategorie-Adresse bleibt erreichbar',$get('/category/news/')['status']===200);
$tg=get_term_by('slug','musik','post_tag');t('Schlagwort-Basis',$tg&&get_term_link($tg)===$H.'/stichwort/musik/'&&$get('/stichwort/musik/')['status']===200);
update_option('category_base','');update_option('tag_base','');

/* Hash-Form */
update_option('elvado_link_hash',1);
t('Hash-Form: Beitrag /#/erster/',get_permalink($a)===$H.'/#/erster/',get_permalink($a));
t('Hash-Form: Kategorie',get_term_link($cat)===$H.'/#/category/news/');
t('Hash-Form: Dateien bleiben',elvado_wp_link_wrap($H.'/bild.jpg')===$H.'/bild.jpg'&&elvado_wp_link_wrap($H.'/?p=3')===$H.'/?p=3');
$h=$get('/');t('Hash-Form: Weiterleitungs-Skript im Seitenkopf',str_contains($h['body'],'location.replace(b+h.slice(2))'));
update_option('elvado_link_hash',0);
t('Ohne Hash-Form kein Skript',!str_contains($get('/')['body'],'location.replace(b+h.slice(2))'));

/* Eigene Struktur → Alt-Adressen: Seiten bleiben unberührt */
update_option('permalink_structure','/blog/%postname%/');
t('Struktur wirkt nur auf Beiträge (Seiten unverändert)',elvado_wp_link_custom()&&elvado_wp_structure_match('/blog/x/')['postname']==='x'&&elvado_wp_structure_match('/x/')===null);
update_option('permalink_structure','/%postname%/');
t('Standard-Struktur: kein eigener Aufbau',!elvado_wp_link_custom()&&elvado_wp_structure_match('/blog/x/')===null);

/* Einstellungsseite: Prüfung und Speichern */
require __DIR__.'/../cms/wp/links-api.php';
t('Struktur ohne %postname%/%post_id% abgelehnt',elvado_wpl_check_structure('/blog/')!==null);
t('Unbekannter Platzhalter, ../ und // abgelehnt',elvado_wpl_check_structure('/%foo%/%postname%/')!==null&&elvado_wpl_check_structure('/../%postname%/')!==null&&elvado_wpl_check_structure('//%postname%/')!==null);
t('Reservierter erster Teil (cms, wp-admin) abgelehnt',elvado_wpl_check_structure('/cms/%postname%/')!==null&&elvado_wpl_check_structure('/wp-admin/%postname%/')!==null);
t('Gültige Strukturen angenommen',elvado_wpl_check_structure('')===null&&elvado_wpl_check_structure('/verzeichnis/%category%/%postname%/')===null&&elvado_wpl_check_structure('/archives/%post_id%')===null);
t('Basen: ungültig/reserviert abgelehnt, gültig angenommen',elvado_wpl_check_base('Große Basis','x')!==null&&elvado_wpl_check_base('cms','x')!==null&&elvado_wpl_check_base('themen','x')===null&&elvado_wpl_check_base('a/b','x')===null);
$r=elvado_wpl_save(['preset'=>'month']);t('Speichern über Voreinstellung',$r['ok']&&get_option('permalink_structure')==='/%year%/%monthnum%/%postname%/'&&$r['preset']==='month');
$r=elvado_wpl_save(['structure'=>'/blog/%postname%','hash'=>true,'category_base'=>'themen','tag_base'=>'stichwort']);
t('Eigene Struktur: Schrägstrich am Ende ergänzt, Hash und Basen gespeichert',$r['ok']&&get_option('permalink_structure')==='/blog/%postname%/'&&(int)get_option('elvado_link_hash')===1&&get_option('category_base')==='themen'&&$r['preset']==='custom');
$bad=elvado_wpl_save(['structure'=>'/nur-text/']);t('Fehler → nichts geändert',!$bad['ok']&&get_option('permalink_structure')==='/blog/%postname%/');
$bad=elvado_wpl_save(['structure'=>'/%postname%/','category_base'=>'x','tag_base'=>'x']);t('Gleiche Kategorie-/Schlagwort-Basis abgelehnt',!$bad['ok']);
$r=elvado_wpl_save(['preset'=>'plain']);t('Einfach: Struktur leer',$r['ok']&&get_option('permalink_structure')===''&&str_contains($r['sample'],'?p='));
elvado_wpl_save(['preset'=>'post']);
/* Startseite: statische Seite oder Beitragsübersicht, mit eigener Beitragsseite */
$pgs=array_column(elvado_wpl_reading_get()['pages'],'id','title');$wid=(int)$pgs['Willkommen'];$nid=(int)$pgs['Neuigkeiten'];
t('Standard: Startseite zeigt die neuesten Beiträge',elvado_wpl_reading_get()['show_on_front']==='posts'&&str_contains($get('/')['body'],'Erster'));
$r=elvado_wpl_reading_save(['show_on_front'=>'page','page_on_front'=>$wid,'page_for_posts'=>$nid]);
t('Statische Startseite gespeichert',$r['ok']&&get_option('show_on_front')==='page'&&(int)get_option('page_on_front')===$wid);
$h=$get('/');t('Startseite zeigt die gewählte Seite',$h['status']===200&&str_contains($h['body'],'Hallo Startseite')&&!str_contains($h['body'],'Inhalt eins'));
$bp=$get('/neuigkeiten/');t('Beitragsseite zeigt die Beitragsübersicht',$bp['status']===200&&str_contains($bp['body'],'Erster')&&str_contains($bp['body'],'Zweiter')&&!str_contains($bp['body'],'Seitentext Blog'));
t('Beitragsseite ist „Blog“, Startseite nicht',(function() use($get){ $get('/neuigkeiten/');$a=is_home()&&!is_front_page();$get('/');return $a&&is_front_page()&&!is_home(); })());
t('Beiträge bleiben einzeln erreichbar',str_contains($get('/erster/')['body'],'Inhalt eins'));
t('Menü „News“ führt auf die Beitragsseite',elvado_wp_system_url('news')===$H.'/neuigkeiten/');
t('Startseite = Beitragsseite abgelehnt',!elvado_wpl_reading_save(['show_on_front'=>'page','page_on_front'=>$wid,'page_for_posts'=>$wid])['ok']);
t('Statisch ohne Startseite abgelehnt',!elvado_wpl_reading_save(['show_on_front'=>'page','page_on_front'=>0])['ok']);
t('Unbekannte Seite abgelehnt',!elvado_wpl_reading_save(['show_on_front'=>'page','page_on_front'=>999])['ok']);
$r=elvado_wpl_reading_save(['show_on_front'=>'posts']);
t('Zurück zu „Neueste Beiträge“ (Seitenwahl bleibt gemerkt)',$r['ok']&&str_contains($get('/')['body'],'Erster')&&(int)get_option('page_on_front')===$wid);
t('Ohne Beitragsseite führt „News“ auf die Startseite',(function() use($H,$wid){ elvado_wpl_reading_save(['show_on_front'=>'page','page_on_front'=>$wid,'page_for_posts'=>0]);$u=elvado_wp_system_url('news');elvado_wpl_reading_save(['show_on_front'=>'posts']);return $u===$H.'/'; })());

$api=file_get_contents(__DIR__.'/../cms/api.php');$posAuth=strpos($api,'$wpUser=elvado_auth(true);');$posL=strpos($api,"'wp_permalinks_save'");
t('API-Aktionen stehen hinter der Administrator-Prüfung',$posAuth!==false&&$posL!==false&&$posAuth<$posL);

/* Plugin-Seiten-Rahmen: cms/wp ist per .htaccess gesperrt, frame.php muss aber erreichbar bleiben (sonst „Forbidden“ im CMS) */
$ht=(string)file_get_contents(__DIR__.'/../cms/wp/.htaccess');
t('cms/wp bleibt gesperrt, nur frame.php ist freigegeben',str_contains($ht,'Require all denied')&&preg_match('~<Files "frame\.php">\s*Require all granted\s*</Files>~',$ht)===1&&substr_count($ht,'Require all granted')===1);

system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

<?php
// Prüft die WordPress-Einstellungen im CMS (Allgemein, Schreiben, Lesen, Diskussion, Medien): Lesen, Prüfen, Speichern, Schreibbrücke ins CMS.
// Aufruf: php scripts/test-wp-settings.php
declare(strict_types=1);
foreach(['publish','feeds','seo','content','media'] as $__l)require_once __DIR__.'/../cms/lib/'.$__l.'.php';
require_once __DIR__.'/wpe-render.php';
$siteJson=json_encode(elvado_ensure_site_defaults(['portal'=>['site_name'=>'Mein Radio','tagline'=>'Hören'],'legal'=>['email'=>'a@example.test'],'comments'=>['enabled'=>false,'require_approval'=>true],'rss'=>['max_items'=>30],'seo'=>['robots'=>'index,follow'],'menus'=>['top'=>[],'bottom'=>[]],'pages'=>[]]),JSON_UNESCAPED_UNICODE);
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
$env=wpe_render_env(['news.json'=>'[]','site.json'=>$siteJson]);
if(!$env){ echo "Hinweis: übersprungen (WPE_TEST_ZIP nicht gesetzt).\n0 von 0 Prüfungen bestanden\n";exit(0); }
$body=<<<'BODY'
foreach(['publish','feeds','seo','content','media','system'] as $__l)require_once ELVADO_CMS_DIR.'/lib/'.$__l.'.php';
require_once ELVADO_CMS_DIR.'/lib/wp-real-compat.php';require_once ELVADO_CMS_DIR.'/wp/settings-api.php';
$GLOBALS['site']=json_decode((string)file_get_contents(dirname(ELVADO_WP_DATA).'/site.json'),true);$GLOBALS['siteFile']=dirname(ELVADO_WP_DATA).'/site.json';$GLOBALS['genDir']=ELVADO_CMS_DIR.'/generated';$GLOBALS['root']=dirname(ELVADO_CMS_DIR);
$val=function(array $g,string $k){ foreach($g['fields'] as $f)if($f['name']===$k)return $f['value']; return null; };
$siteNow=fn()=>json_decode((string)file_get_contents(dirname(ELVADO_WP_DATA).'/site.json'),true);

/* Lesen */
$g=elvado_wps_get('general');
t('Allgemein: Titel aus CMS',$val($g,'blogname')==='Mein Radio');
t('Allgemein: Untertitel',$val($g,'blogdescription')==='Hören');
t('Allgemein: E-Mail',$val($g,'admin_email')==='a@example.test');
t('Allgemein: Zeitzone Standard',$val($g,'timezone_string')==='Europe/Berlin');
t('Allgemein: Wochenstart Montag',$val($g,'start_of_week')==='1');
t('Unbekannter Bereich',elvado_wps_get('nix')===null&&!elvado_wps_save('nix',[])['ok']);
t('Schreiben: Standardkategorie News',$val(elvado_wps_get('writing'),'elvado_default_category')==='News');
t('Diskussion: Kommentare standardmäßig aus',$val(elvado_wps_get('discussion'),'default_comment_status')===0);
t('Diskussion: Freigabe an',$val(elvado_wps_get('discussion'),'comment_moderation')===1);
t('Lesen: Feed-Länge aus CMS',$val(elvado_wps_get('reading'),'posts_per_rss')===30);
t('Lesen: Suchmaschinen erlaubt',$val(elvado_wps_get('reading'),'blog_public')===1);
t('Medien: Standardgrößen',$val(elvado_wps_get('media'),'medium_size_w')===300&&$val(elvado_wps_get('media'),'large_size_w')===1024);

/* Prüfen */
t('Leerer Titel abgelehnt',!elvado_wps_save('general',['blogname'=>' '])['ok']);
t('Ungültige E-Mail abgelehnt',!elvado_wps_save('general',['admin_email'=>'kein-mail'])['ok']);
t('Ungültige Zeitzone abgelehnt',!elvado_wps_save('general',['timezone_string'=>'Mars/Olympus'])['ok']);
t('Zu langer Titel abgelehnt',!elvado_wps_save('general',['blogname'=>str_repeat('x',81)])['ok']);
t('Zahl außerhalb abgelehnt',!elvado_wps_save('reading',['posts_per_page'=>0])['ok']&&!elvado_wps_save('reading',['posts_per_page'=>101])['ok']&&!elvado_wps_save('reading',['posts_per_page'=>'abc'])['ok']);
t('Kommazahl abgelehnt',!elvado_wps_save('reading',['posts_per_page'=>'5.5'])['ok']);
t('Fehler ändern nichts',$siteNow()['portal']['site_name']==='Mein Radio'&&$siteNow()['legal']['email']==='a@example.test');

/* Speichern → CMS-Daten */
$r=elvado_wps_save('general',['blogname'=>'<b>Neues</b>  Radio','blogdescription'=>'Neu','admin_email'=>'b@example.test','timezone_string'=>'Europe/Vienna','WPLANG'=>'en_US','date_format'=>'Y-m-d','start_of_week'=>'0']);
t('Allgemein gespeichert',$r['ok']===true,json_encode($r));
$s=$siteNow();
t('Titel in site.json (bereinigt)',($s['portal']['site_name']??'')==='Neues Radio');
t('Untertitel in site.json',($s['portal']['tagline']??'')==='Neu');
t('E-Mail in site.json',($s['legal']['email']??'')==='b@example.test');
t('Datumsformat als Option',get_option('date_format')==='Y-m-d');
t('Wochenstart als Zahl',get_option('start_of_week')===0);
t('Rückgabe zeigt neuen Titel',$val($r,'blogname')==='Neues Radio');
t('Unbeteiligte Daten bleiben',($s['rss']['max_items']??0)===30&&($s['comments']['enabled']??null)===false);

$r=elvado_wps_save('discussion',['default_comment_status'=>true,'comment_moderation'=>false,'comments_per_page'=>20,'thread_comments'=>'1']);
t('Diskussion gespeichert',$r['ok']===true,json_encode($r));
t('Kommentare in site.json an',$siteNow()['comments']['enabled']===true);
t('Freigabe in site.json aus',$siteNow()['comments']['require_approval']===false);
t('Kommentare pro Seite',(int)get_option('comments_per_page')===20&&(int)get_option('thread_comments')===1);
t('Lesen zeigt Kommentare an',$val(elvado_wps_get('discussion'),'default_comment_status')===1);

$r=elvado_wps_save('reading',['posts_per_page'=>7,'posts_per_rss'=>12,'blog_public'=>false]);
t('Lesen gespeichert',$r['ok']===true,json_encode($r));
t('Feed-Länge in site.json',$siteNow()['rss']['max_items']===12);
t('Suchmaschinen gesperrt',str_starts_with($siteNow()['seo']['robots'],'noindex'));
t('Beiträge pro Seite',(int)get_option('posts_per_page')===7);
$r=elvado_wps_save('reading',['blog_public'=>true]);
t('Suchmaschinen wieder erlaubt',!str_starts_with($siteNow()['seo']['robots'],'noindex'));

$r=elvado_wps_save('media',['medium_size_w'=>400,'medium_size_h'=>0]);
t('Medien gespeichert',$r['ok']===true&&(int)get_option('medium_size_w')===400&&(int)get_option('medium_size_h')===0);
t('Medien: nur übergebene Felder',(int)get_option('large_size_w',1024)===1024);

/* Schreiben → Standardwerte */
t('Standard ohne Option',elvado_wps_default('elvado_default_category','News',sys_get_temp_dir().'/nix-'.bin2hex(random_bytes(3)).'.json')==='News');
$r=elvado_wps_save('writing',['elvado_default_category'=>'Radio','elvado_default_status'=>'published']);
t('Schreiben gespeichert',$r['ok']===true);
t('Standardkategorie lesbar (auch ohne Start von WordPress, aus der Datenbank)',elvado_wps_default('elvado_default_category','News')==='Radio'&&elvado_wps_default('elvado_default_status','draft')==='published');
t('Ungültiger Status abgelehnt',!elvado_wps_save('writing',['elvado_default_status'=>'x'])['ok']);
$r=elvado_wps_save('writing',['elvado_default_category'=>'']);
t('Leere Kategorie → News',$r['ok']&&elvado_wps_default('elvado_default_category','x')==='News');

BODY;
foreach(wpe_render_suite($env,$body) as [$nm,$ok,$ex])t($nm,$ok,$ex);
echo $fail?"$fail von $n Prüfungen fehlgeschlagen\n":"Alle $n Prüfungen bestanden\n";exit($fail?1:0);

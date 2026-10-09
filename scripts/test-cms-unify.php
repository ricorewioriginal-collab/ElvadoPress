<?php
// Prüft die Schreibbrücke (Stufe 12): WordPress-Code schreibt Optionen, Beiträge, Seiten, Menüs und Medien in die CMS-Dateien (news.json, site.json,
// cms/media), ohne Bestandsdaten zu beschädigen. Aufruf: php scripts/test-cms-unify.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/rrw-unify-'.bin2hex(random_bytes(4));
foreach(['','/wp-content','/cms','/cms/data','/cms/media','/cms/media/library','/content'] as $d)mkdir($tmp.$d,0775,true);
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('RRW_WP_DATA',$tmp.'/cms/.wp');define('RRW_WP_CMS_DATA',$tmp.'/cms');define('RRW_WP_CMS_ROOT',$tmp);define('RRW_MEDIA_DIR',$tmp.'/cms/media');define('RRW_CONTENT_DIR',$tmp.'/content');
define('RRW_SYSTEM_FILE',$tmp.'/cms/system.local.json');
$_SERVER['HTTP_HOST']='example.test';
register_shutdown_function(function() use($tmp){ $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f)$f->isDir()?@rmdir($f->getPathname()):@unlink($f->getPathname());@rmdir($tmp); });
foreach(['publish','feeds','seo','content','media'] as $__l)require __DIR__.'/../cms/lib/'.$__l.'.php';
$J=JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES;
file_put_contents($tmp.'/index.html',"<!doctype html><html><head><title>x</title></head><body>Start</body></html>\n");

/* Bestandsdaten mit Zusatzfeldern, die das CMS nicht kennt */
$news=[
 ['id'=>1,'slug'=>'erster','title'=>'Erster Beitrag','category'=>'News','excerpt'=>'Kurz eins','image_url'=>'/img/a.jpg','image_mode'=>'thumbnail','tags'=>'radio,musik','status'=>'published','published_at'=>'2026-01-10 10:00:00','body_html'=>'<p>Hallo Welt</p>','author'=>'Anna Autor','author_user'=>'anna','seo_title'=>'Seo','updated_at'=>'2026-01-11 10:00:00','created_at'=>'2026-01-10 09:00:00','x_unbekannt'=>['a'=>1,'b'=>[true,null]],'featured'=>1],
 ['id'=>2,'slug'=>'zweiter','title'=>'Zweiter','category'=>'Events','excerpt'=>'','tags'=>'musik','status'=>'published','published_at'=>'2026-02-10 10:00:00','body_html'=>'<p>Konzert</p>','author'=>'Ben Bauer','x_zusatz'=>'bleibt'],
 ['id'=>3,'slug'=>'dritter','title'=>'Dritter','category'=>'News','status'=>'published','published_at'=>'2026-03-10 10:00:00','body_html'=>'<p>Text</p>','author'=>'Anna Autor'],
 ['id'=>4,'slug'=>'entwurf','title'=>'Entwurf','category'=>'News','status'=>'draft','published_at'=>'2026-03-11 10:00:00','body_html'=>'<p>nur Entwurf</p>','x_zusatz'=>'draft'],
];
$site=rrw_ensure_site_defaults(['portal'=>['site_name'=>'Mein Radio','news_title'=>'News','x_portal_extra'=>'bleibt'],'legal'=>['imprint_mode'=>'link'],
 'pages'=>[
  ['id'=>'ueber','slug'=>'ueber-uns','title'=>'Über uns','type'=>'custom','system_target'=>'','enabled'=>true,'native_enabled'=>true,'headline'=>'Über uns','intro'=>'','text_overrides'=>[],'blocks_before'=>[['id'=>'b1','type'=>'html','enabled'=>true,'html'=>'<p>Wir sind ein Radio.</p>']],'blocks_after'=>[['id'=>'w1','type'=>'widget','enabled'=>true,'widget_id'=>'abc']],'meta_title'=>'','meta_description'=>'','noindex'=>false,'publish_at'=>'','x_notiz'=>'nur hier'],
  ['id'=>'mehr','slug'=>'mehrteilig','title'=>'Mehrteilig','type'=>'custom','system_target'=>'','enabled'=>true,'native_enabled'=>true,'headline'=>'','intro'=>'','text_overrides'=>[],'blocks_before'=>[['id'=>'m1','type'=>'html','enabled'=>true,'html'=>'<p>Eins</p>'],['id'=>'m2','type'=>'html','enabled'=>true,'html'=>'<p>Zwei</p>']],'blocks_after'=>[],'meta_title'=>'','meta_description'=>'','noindex'=>false,'publish_at'=>''],
  ['id'=>'leer','slug'=>'leer','title'=>'Leere Seite','type'=>'custom','system_target'=>'','enabled'=>true,'native_enabled'=>true,'headline'=>'','intro'=>'','text_overrides'=>[],'blocks_before'=>[],'blocks_after'=>[],'meta_title'=>'','meta_description'=>'','noindex'=>false,'publish_at'=>''],
 ],
 'menus'=>['top'=>[['id'=>'m-ueber','label'=>'Über uns','target'=>'page:ueber-uns','icon'=>'fa-circle','parent_id'=>'','enabled'=>true,'x_menu'=>'ja']],'bottom'=>[]],
 'x_top_unbekannt'=>['k'=>'v']]);
$site['pages']=array_values($site['pages']);
file_put_contents($tmp.'/cms/news.json',json_encode($news,$J)."\n");
file_put_contents($tmp.'/cms/site.json',json_encode($site,$J)."\n");
file_put_contents($tmp.'/impressum.html',"<html>fest</html>");   // feste Seite der Website (nicht vom CMS erzeugt)
$siteNow=fn()=>json_decode((string)file_get_contents($tmp.'/cms/site.json'),true);
$newsNow=fn()=>json_decode((string)file_get_contents($tmp.'/cms/news.json'),true);
$rowOf=function(array $all,int $id){ foreach($all as $r)if((int)($r['id']??0)===$id)return $r;return null; };

require __DIR__."/_testdb.php";
unset($GLOBALS['RRW_SITE']);   // wie in einer reinen WordPress-Anfrage
require __DIR__.'/../cms/wp/load.php';require_once __DIR__.'/../cms/wp/rest.php';
$fail=0;$n=0;
function t(string $name,bool $ok): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name\n";} }
rrw_wp_boot(['user'=>['id'=>1,'login'=>'admin','name'=>'Admin Tester','email'=>'admin@example.test','role'=>'administrator'],'admin'=>true]);
rrw_wp_cms_lib();

/* ───── 0. Standard: Brücke aus – nichts ändert sich ───── */
$h0=md5_file($tmp.'/cms/news.json').md5_file($tmp.'/cms/site.json');
t('Brücke ist standardmäßig aus',rrw_wp_bridge_parts()===[]);
t('Aus: update_option blogname bleibt in der WordPress-Ablage',update_option('blogname','Nur WP')&&md5_file($tmp.'/cms/site.json').''!==''&&($siteNow())['portal']['site_name']==='Mein Radio'&&get_option('blogname')==='Nur WP');
t('Aus: CMS-Beitrag nicht änderbar',wp_update_post(['ID'=>1,'post_title'=>'x'])===0&&wp_delete_post(1,true)===false);
t('Aus: Entwurf nicht per ID sichtbar',get_post(4)===null);
$neu=wp_insert_post(['post_title'=>'DB-Beitrag','post_status'=>'publish']);
t('Aus: neuer Beitrag geht in die Datenbank',$neu>=RRW_WP_ID_DB_MIN&&count($newsNow())===4);
t('Aus: Dateien unverändert',$h0===md5_file($tmp.'/cms/news.json').md5_file($tmp.'/cms/site.json'));
delete_option('blogname');wp_delete_post($neu,true);
t('Aus: Standard-Optionen lesen ohne CMS-Bezug (wie bisher)',get_option('blogname')==='WordPress');

/* ───── 1. Einschalten ───── */
$view=fn()=>(function(){ $a=$GLOBALS['rrw_wp_is_admin']??false;$GLOBALS['rrw_wp_is_admin']=false;try{ return ($GLOBALS['__v'])(); }finally{ $GLOBALS['rrw_wp_is_admin']=$a; } })();
$GLOBALS['__v']=fn()=>json_encode([array_map(fn($p)=>[$p->ID,$p->post_title,$p->post_content,$p->post_status,$p->post_date,$p->post_name],rrw_wp_cms_posts()),array_map(fn($p)=>[$p->ID,$p->post_title,$p->post_content,$p->post_status,$p->post_name],rrw_wp_cms_pages()),array_column(get_categories(),'name')]);
$viewOff=$view();
rrw_wp_bridge_set(['options','posts','pages','media','menus']);
rrw_wp_cms_reset();
t('Einschalten ändert die Ausgabe nicht (Beiträge, Seiten, Kategorien)',$view()===$viewOff);
t('Brücke einschaltbar',rrw_wp_bridge_parts()===['options','posts','pages','media','menus']&&rrw_wp_bridge('posts'));

/* ───── 2. Optionen ───── */
$before=$siteNow();
t('blogname → portal.site_name',update_option('blogname','Neues Radio')===true&&$siteNow()['portal']['site_name']==='Neues Radio');
t('get_option liest den CMS-Wert',get_option('blogname')==='Neues Radio'&&get_bloginfo('name')==='Neues Radio');
t('gleicher Wert → false, keine Schreibaktion',(function() use($tmp){ $m=filemtime($tmp.'/cms/site.json');$h=md5_file($tmp.'/cms/site.json');return update_option('blogname','Neues Radio')===false&&md5_file($tmp.'/cms/site.json')===$h; })());
t('blogdescription → portal.tagline',update_option('blogdescription','Dein Sender')&&$siteNow()['portal']['tagline']==='Dein Sender'&&get_option('blogdescription')==='Dein Sender');
t('admin_email → legal.email',update_option('admin_email','chef@example.test')&&$siteNow()['legal']['email']==='chef@example.test');
t('ungültige E-Mail abgelehnt',update_option('admin_email','kein-mail')===false&&$siteNow()['legal']['email']==='chef@example.test');
t('Alte WordPress-Fassung wurde übernommen und entfernt',rrw_wp_opts_get_raw('blogname')[0]===false);
t('andere Optionen bleiben in der WordPress-Ablage',update_option('rrw_test_opt','x')&&!array_key_exists('rrw_test_opt',$siteNow())&&rrw_wp_opts_get_raw('rrw_test_opt')[0]===true);
$after=$siteNow();
$exp=$before;$exp['portal']['site_name']='Neues Radio';$exp['portal']['tagline']='Dein Sender';$exp['legal']['email']='chef@example.test';
t('Rundlauf Optionen: übrige site.json unverändert (auch Unbekanntes)',$after==$exp&&$after['x_top_unbekannt']===['k'=>'v']&&$after['portal']['x_portal_extra']==='bleibt');
t('index.html-Schnappschuss neu geschrieben',str_contains((string)file_get_contents($tmp.'/index.html'),'Neues Radio'));
t('Aktivitätsprotokoll geführt',(function() use($tmp){ $l=json_decode((string)file_get_contents($tmp.'/cms/activity-log.json'),true);return is_array($l)&&$l&&$l[count($l)-1]['user']==='Admin Tester'; })());
t('CMS-Speichern behält tagline/email (clean_section)',(function() use($after){ $p=rrw_clean_section('portal',['site_name'=>'A']+$after['portal']);$q=rrw_clean_section('portal',['site_name'=>'A']);$l=rrw_clean_section('legal',$after['legal']);return ($p['tagline']??'')==='Dein Sender'&&!array_key_exists('tagline',$q)&&($l['email']??'')==='chef@example.test'&&!array_key_exists('email',rrw_clean_section('legal',['imprint_mode'=>'link'])); })());
add_option('rrw_neu_opt','a');t('add_option unbeteiligt',get_option('rrw_neu_opt')==='a');
t('add_option auf belegtes blogname → false',add_option('blogname','Anders')===false);

/* ───── 3. Beiträge ───── */
$h=function() use($tmp){ return md5_file($tmp.'/cms/news.json'); };
$hBefore=$h();
t('Entwurf per ID sichtbar (Brücke an)',get_post(4)instanceof WP_Post&&get_post(4)->post_status==='draft');
t('Rundlauf ohne Änderung schreibt nichts',(function() use($h,$hBefore){ $p=get_post(1);$r=wp_update_post(['ID'=>1,'post_title'=>$p->post_title,'post_content'=>$p->post_content,'post_excerpt'=>$p->post_excerpt,'post_status'=>$p->post_status,'post_name'=>$p->post_name]);return $r===1&&$h()===$hBefore; })());
$saved=[];add_action('save_post',function($pid,$post,$upd) use(&$saved){ $saved[]=[$pid,$upd]; },10,3);
$orig=$newsNow();
t('wp_update_post ändert Titel/Inhalt/Auszug',wp_update_post(['ID'=>1,'post_title'=>'Neu & Titel','post_content'=>'<p>Neuer Text</p>','post_excerpt'=>'Neuer Auszug'])===1);
$all=$newsNow();$r1=$rowOf($all,1);
t('Felder in news.json',$r1['title']==='Neu & Titel'&&$r1['body_html']==='<p>Neuer Text</p>'&&$r1['excerpt']==='Neuer Auszug'&&$r1['updated_at']>$orig[0]['updated_at']);
t('Unbekanntes und nicht berührtes am Beitrag bleibt',$r1['x_unbekannt']===['a'=>1,'b'=>[true,null]]&&$r1['featured']===1&&$r1['seo_title']==='Seo'&&$r1['slug']==='erster'&&$r1['author_user']==='anna'&&$r1['tags']==='radio,musik'&&$r1['published_at']==='2026-01-10 10:00:00'&&$r1['created_at']==='2026-01-10 09:00:00');
t('andere Beiträge bit-identisch',$rowOf($all,2)===$orig[1]&&$rowOf($all,3)===$orig[2]&&$rowOf($all,4)===$orig[3]&&count($all)===4);
t('WordPress liest den Titel maskiert zurück',get_post(1)->post_title==='Neu &amp; Titel'&&get_the_title(1)==='Neu &amp; Titel');
t('Titel-Rundlauf über WordPress ohne doppelte Maskierung',(function() use($newsNow){ $p=get_post(1);wp_update_post(['ID'=>1,'post_title'=>$p->post_title.'!']);return $newsNow()[0]['title']==='Neu & Titel!'; })());
t('Revision des CMS angelegt',(function() use($tmp){ $rv=json_decode((string)file_get_contents($tmp.'/cms/news-revisions.json'),true);return is_array($rv)&&count($rv)>=1&&$rv[0]['article_id']===1&&$rv[0]['article']['title']==='Erster Beitrag'; })());
t('save_post-Hook feuert (update)',in_array([1,true],$saved,true));
t('RSS neu erzeugt',is_file($tmp.'/rss.xml'));
wp_update_post(['ID'=>1,'post_status'=>'draft']);
t('Status draft → status=draft, nicht mehr öffentlich',$rowOf($newsNow(),1)['status']==='draft'&&get_post_status(1)==='draft'&&!in_array(1,array_map(fn($p)=>$p->ID,(new WP_Query(['post_type'=>'post']))->posts),true));
t('Entwurf per post_status=draft abfragbar',in_array(1,array_map(fn($p)=>$p->ID,(new WP_Query(['post_type'=>'post','post_status'=>'draft']))->posts),true));
wp_update_post(['ID'=>1,'post_status'=>'publish']);
t('zurück auf publish',$rowOf($newsNow(),1)['status']==='published'&&get_post_status(1)==='publish');
t('Papierkorb: wp_trash_post setzt deleted_at',(function() use($newsNow,$rowOf){ $p=wp_trash_post(2);$r=$rowOf($newsNow(),2);return $p instanceof WP_Post&&!empty($r['deleted_at'])&&$r['status']==='published'&&get_post_status(2)==='trash'&&$r['x_zusatz']==='bleibt'; })());
t('im Papierkorb nicht mehr öffentlich',!in_array(2,array_map(fn($p)=>$p->ID,(new WP_Query(['post_type'=>'post']))->posts),true));
t('wp_untrash_post stellt wieder her',(function() use($newsNow,$rowOf){ $p=wp_untrash_post(2);$r=$rowOf($newsNow(),2);return $p instanceof WP_Post&&empty($r['deleted_at'])&&$r['status']==='published'; })());
t('wp_delete_post (force) entfernt nur diese Zeile',(function() use($newsNow,$rowOf){ $b=$newsNow();$x=wp_delete_post(3,true);$a=$newsNow();return $x instanceof WP_Post&&count($a)===count($b)-1&&$rowOf($a,3)===null&&$rowOf($a,2)==$rowOf($b,2)&&$rowOf($a,4)==$rowOf($b,4); })());
t('wp_delete_post ohne force → Papierkorb',(function() use($newsNow,$rowOf){ $x=wp_delete_post(4);$r=$rowOf($newsNow(),4);return $x instanceof WP_Post&&!empty($r['deleted_at']); })());

/* Neue Beiträge */
$cat=get_category_by_slug('events');
$id=wp_insert_post(['post_title'=>'Brandneu','post_content'=>'<p>Frisch &amp; neu</p><script>alert(1)</script>','post_excerpt'=>'Teaser','post_status'=>'publish','post_date'=>'2026-04-01 12:00:00','post_category'=>[$cat->term_id],'tags_input'=>['Alpha','Beta'],'meta_input'=>['_mein_meta'=>'wert']],true);
t('neuer Beitrag: ID im CMS-Bereich, nächste freie',is_int($id)&&$id===5);
$r5=$rowOf($newsNow(),5);
t('neuer Beitrag: Felder in news.json',$r5&&$r5['title']==='Brandneu'&&$r5['slug']==='brandneu'&&$r5['status']==='published'&&$r5['published_at']==='2026-04-01 12:00:00'&&$r5['category']==='Events'&&$r5['tags']==='Alpha,Beta'&&$r5['excerpt']==='Teaser'&&$r5['author']==='Admin Tester'&&$r5['author_user']==='admin'&&$r5['image_mode']==='thumbnail'&&isset($r5['created_at']));
t('neuer Beitrag: Admin darf unfiltered_html (Inhalt unverändert)',str_contains($r5['body_html'],'<script>'));
t('Beitragsmeta bleibt in wp_postmeta',get_post_meta(5,'_mein_meta',true)==='wert'&&!array_key_exists('_mein_meta',$r5));
t('neuer Beitrag in WP_Query und get_post',get_post(5)->post_title==='Brandneu'&&in_array(5,array_map(fn($p)=>$p->ID,(new WP_Query(['post_type'=>'post']))->posts),true)&&has_category('events',5));
t('Slug eindeutig',(function() use($newsNow){ $i=wp_insert_post(['post_title'=>'Brandneu','post_status'=>'draft']);return $newsNow()[count($newsNow())-1]['slug']==='brandneu-2'&&$i===6; })());
t('Entwurf ohne Datum → jetzt, pending → wp_status',(function() use($newsNow,$rowOf){ $i=wp_insert_post(['post_title'=>'Wartet','post_status'=>'pending']);$r=$rowOf($newsNow(),$i);return $r['status']==='draft'&&$r['wp_status']==='pending'&&get_post_status($i)==='pending'&&strlen($r['published_at'])===19; })());
t('geplant (Zukunft) → published + späteres Datum, Status future',(function() use($newsNow,$rowOf){ $i=wp_insert_post(['post_title'=>'Später','post_status'=>'publish','post_date'=>'2090-01-01 10:00:00']);$r=$rowOf($newsNow(),$i);return $r['status']==='published'&&get_post_status($i)==='future'&&!rrw_news_is_live($r); })());
t('leerer Beitrag abgewiesen',wp_insert_post(['post_title'=>'','post_content'=>''])===0&&is_wp_error(wp_insert_post(['post_title'=>'','post_content'=>''],true)));
t('auto-draft bleibt in der Datenbank',wp_insert_post(['post_title'=>'Auto','post_status'=>'auto-draft'])>=RRW_WP_ID_DB_MIN);
t('eigener Beitragstyp bleibt in der Datenbank',(function(){ register_post_type('buch',['public'=>true]);return wp_insert_post(['post_title'=>'Buch','post_type'=>'buch','post_status'=>'publish'])>=RRW_WP_ID_DB_MIN; })());
t('Seite neu → Datenbank (IDs ab 100 Mio.)',(function(){ $i=wp_insert_post(['post_title'=>'WP-Seite','post_type'=>'page','post_status'=>'publish','post_name'=>'wp-seite']);return $i>=RRW_WP_ID_DB_MIN&&get_post($i)->post_type==='page'; })());
t('Kategorie/Schlagwörter setzen',(function() use($newsNow,$rowOf){ wp_set_post_terms(5,'Gamma, Delta','post_tag');$a=$rowOf($newsNow(),5)['tags'];wp_set_post_terms(5,['Eps'],'post_tag',true);$b=$rowOf($newsNow(),5)['tags'];wp_set_post_categories(5,[get_category_by_slug('news')->term_id]);return $a==='Gamma,Delta'&&$b==='Gamma,Delta,Eps'&&$rowOf($newsNow(),5)['category']==='News'; })());
t('Titelbild setzen/entfernen',(function() use($newsNow,$rowOf){ $a=rrw_wp_cms_attachment_id('/img/b.jpg');$ok=set_post_thumbnail(5,$a);$u=$rowOf($newsNow(),5)['image_url'];$t=get_post_thumbnail_id(5);$ok2=delete_post_thumbnail(5);return $ok&&$u==='/img/b.jpg'&&$t===$a&&get_the_post_thumbnail_url(5)===false&&$rowOf($newsNow(),5)['image_url']===''; })());
t('Autor wechseln',(function() use($newsNow,$rowOf){ wp_update_post(['ID'=>5,'post_author'=>1]);return $rowOf($newsNow(),5)['author']==='Admin Tester'; })());
t('Zusatzfelder anderer Beiträge nach allen Schreibzugriffen unberührt',$rowOf($newsNow(),2)['x_zusatz']==='bleibt'&&$rowOf($newsNow(),1)['x_unbekannt']===['a'=>1,'b'=>[true,null]]);
t('Beitrag ohne Recht unfiltered_html wird bereinigt',(function() use($newsNow,$rowOf){ $g=$GLOBALS['rrw_wp_user'];$GLOBALS['rrw_wp_user']['caps']=rrw_wp_caps_for_role('author');$i=wp_insert_post(['post_title'=>'Autor','post_content'=>'<p>ok</p><script>x()</script>','post_status'=>'draft']);$GLOBALS['rrw_wp_user']=$g;$b=$rowOf($newsNow(),$i)['body_html'];return str_contains($b,'<p>ok</p>')&&!str_contains($b,'script'); })());

/* ───── 4. Seiten ───── */
$pid=rrw_wp_hash_id('ueber',RRW_WP_ID_PAGE_BASE);$mid=rrw_wp_hash_id('mehr',RRW_WP_ID_PAGE_BASE);$lid=rrw_wp_hash_id('leer',RRW_WP_ID_PAGE_BASE);
$s0=$siteNow();
t('CMS-Seite per ID, Inhalt aus dem Block',get_post($pid)->post_content==='<p>Wir sind ein Radio.</p>');
t('Seite ändern: Titel, Inhalt',wp_update_post(['ID'=>$pid,'post_title'=>'Wir über uns','post_content'=>'<p>Neuer Seitentext</p>'])===$pid);
$s1=$siteNow();$pg=$s1['pages'][0];
t('Seitenfelder in site.json',$pg['headline']==='Wir über uns'&&$pg['title']==='Über uns'&&$pg['blocks_before'][0]['html']==='<p>Neuer Seitentext</p>'&&$pg['blocks_before'][0]['id']==='b1');
t('Seite: Unbekanntes und Widget-Block unverändert',$pg['x_notiz']==='nur hier'&&$pg['blocks_after']===$s0['pages'][0]['blocks_after']&&$pg['slug']==='ueber-uns');
t('Übrige Seiten/Bereiche unverändert',$s1['pages'][1]==$s0['pages'][1]&&$s1['pages'][2]==$s0['pages'][2]&&$s1['menus']==$s0['menus']&&$s1['x_top_unbekannt']===['k'=>'v']&&$s1['portal']==$s0['portal']);
t('Seiten-Verlauf des CMS (vorherige Fassung)',(function() use($tmp){ $v=rrw_tools_read(rrw_page_revisions_file($tmp.'/cms'),['pages'=>[]]);return ($v['pages']['ueber'][0]['page']['headline']??'')==='Über uns'&&($v['pages']['ueber'][0]['page']['blocks_before'][0]['html']??'')==='<p>Wir sind ein Radio.</p>'; })());
t('generierte Seite im Website-Root',is_file($tmp.'/ueber-uns.html')&&str_contains((string)file_get_contents($tmp.'/ueber-uns.html'),'Neuer Seitentext'));
t('mehrteilige Seite: Inhalt nicht eindeutig → Fehler, nichts geschrieben',(function() use($mid,$siteNow,$tmp){ $h=md5_file($tmp.'/cms/site.json');$e=wp_update_post(['ID'=>$mid,'post_content'=>'<p>neu</p>'],true);return is_wp_error($e)&&$e->get_error_code()==='cms_unmappable'&&md5_file($tmp.'/cms/site.json')===$h; })());
t('mehrteilige Seite: Titel änderbar, Blöcke bleiben',(function() use($mid,$siteNow,$s0){ wp_update_post(['ID'=>$mid,'post_title'=>'Mehrteilig neu']);$p=$siteNow()['pages'][1];return $p['title']==='Mehrteilig neu'&&$p['blocks_before']===$s0['pages'][1]['blocks_before']; })());
/* ───── mehrteilige Seiten: Block-Marker (nur Editoren) ───── */
$GLOBALS['rrw_wp_is_admin']=false;$vis=get_post($mid)->post_content;$GLOBALS['rrw_wp_is_admin']=true;$ed=get_post($mid)->post_content;
t('mehrteilige Seite: Besucher sehen unverändertes HTML, Editoren Block-Marker',$vis==='<p>Eins</p><p>Zwei</p>'&&$ed==='<!--rrw:block m1--><p>Eins</p><!--/rrw:block--><!--rrw:block m2--><p>Zwei</p><!--/rrw:block-->');
t('mehrteilige Seite: Block bearbeiten, Reihenfolge und IDs bleiben',(function() use($mid,$siteNow){ $r=wp_update_post(['ID'=>$mid,'post_content'=>'<!--rrw:block m1--><p>Eins neu</p><!--/rrw:block--><!--rrw:block m2--><p>Zwei</p><!--/rrw:block-->'],true);$b=$siteNow()['pages'][1]['blocks_before'];return $r===$mid&&count($b)===2&&$b[0]['id']==='m1'&&$b[0]['html']==='<p>Eins neu</p>'&&$b[1]['id']==='m2'&&$b[1]['html']==='<p>Zwei</p>'&&$b[0]['enabled']===true; })());
foreach(['fehlender Marker'=>'<!--rrw:block m1--><p>x</p><!--/rrw:block-->','vertauscht'=>'<!--rrw:block m2--><p>b</p><!--/rrw:block--><!--rrw:block m1--><p>a</p><!--/rrw:block-->','Fremdinhalt'=>'<p>vorn</p><!--rrw:block m1--><p>a</p><!--/rrw:block--><!--rrw:block m2--><p>b</p><!--/rrw:block-->','ohne Marker'=>'<p>a</p><p>b</p>'] as $nm=>$c)
    t('mehrteilige Seite: '.$nm.' → Fehler, nichts geschrieben',(function() use($mid,$tmp,$c){ $h=md5_file($tmp.'/cms/site.json');$e=wp_update_post(['ID'=>$mid,'post_content'=>$c],true);return is_wp_error($e)&&$e->get_error_code()==='cms_unmappable'&&md5_file($tmp.'/cms/site.json')===$h; })());
t('mehrteilige Seite: Text- und HTML-Block (direkt)',(function(){  $pg=['intro'=>'','blocks_before'=>[['id'=>'t1','type'=>'text','enabled'=>true,'text'=>'Hallo & Welt','x'=>1],['id'=>'h1','type'=>'html','enabled'=>true,'html'=>'<p>a</p>']],'blocks_after'=>[['id'=>'w','type'=>'widget','enabled'=>true,'widget_id'=>'z']]];
    $GLOBALS['rrw_wp_is_admin']=true;$html=rrw_wp_cms_page_html($pg);$e=rrw_wp_cms_page_content_set($pg,str_replace('Hallo &amp; Welt','Neu &amp; &quot;Text&quot;',$html));
    $bad=$pg;$e2=rrw_wp_cms_page_content_set($bad,str_replace('<p>Neu','<p><b>Neu</b>',rrw_wp_cms_page_html($pg)));
    return $e===null&&$pg['blocks_before'][0]['text']==='Neu & "Text"'&&$pg['blocks_before'][0]['x']===1&&$pg['blocks_after'][0]['widget_id']==='z'&&$pg['blocks_before'][1]['html']==='<p>a</p>'&&$e2!==null; })());
/* ───── Zeitzone und Sprache ↔ system.local.json ───── */
t('Zeitzone/Sprache ohne Eintrag: Standardwerte wie bisher',get_option('timezone_string')==='Europe/Berlin'&&get_option('WPLANG')==='de_DE'&&!is_file($tmp.'/cms/system.local.json'));
t('Zeitzone ungültig wird abgelehnt',update_option('timezone_string','Mars/Olympus')===false&&!is_file($tmp.'/cms/system.local.json'));
t('Zeitzone schreiben → system.local.json, andere Felder bleiben',(function() use($tmp){ file_put_contents($tmp.'/cms/system.local.json',json_encode(['language'=>'de','installed_at'=>'2026-01-01','x_extra'=>'bleibt']));$GLOBALS['rrw_wp_opts_cache']=null;
    $r=update_option('timezone_string','Europe/Vienna');$j=json_decode((string)file_get_contents($tmp.'/cms/system.local.json'),true);
    return $r&&$j['timezone']==='Europe/Vienna'&&$j['language']==='de'&&$j['installed_at']==='2026-01-01'&&$j['x_extra']==='bleibt'&&get_option('timezone_string')==='Europe/Vienna'; })());
t('Sprache schreiben und lesen',update_option('WPLANG','en_US')&&get_option('WPLANG')==='en_US'&&json_decode((string)file_get_contents($tmp.'/cms/system.local.json'),true)['language']==='en_US'&&update_option('WPLANG','Deutsch')===false);
/* ───── Kommentare, Feed-Länge, Suchmaschinen-Sichtbarkeit ───── */
t('Kommentare: lesen aus site.json und schreiben (Rest der Abschnitte bleibt)',(function() use($siteNow){ $b=$siteNow();
    update_option('default_comment_status','open');$r1=update_option('default_comment_status','closed');$a=$siteNow();$r2=update_option('default_comment_status','open');$c=$siteNow();$bad=update_option('default_comment_status','maybe');
    return $r1&&$a['comments']['enabled']===false&&$a['comments']['require_approval']===$b['comments']['require_approval']&&$r2&&$c['comments']['enabled']===true&&$bad===false&&get_option('default_comment_status')==='open'; })());
t('Kommentar-Freigabe schreiben',(function() use($siteNow){ $r=update_option('comment_moderation','0');$a=$siteNow();$ok=$r&&$a['comments']['require_approval']===false&&(int)get_option('comment_moderation')===0;update_option('comment_moderation','1');return $ok&&$siteNow()['comments']['require_approval']===true&&update_option('comment_moderation','2')===false; })());
t('Feed-Länge schreiben, Grenzen 5–100',(function() use($siteNow){ $b=$siteNow()['rss'];$r=update_option('posts_per_rss','20');$a=$siteNow()['rss'];
    return $r&&$a['max_items']===20&&array_diff_key($a,['max_items'=>1])===array_diff_key($b,['max_items'=>1])&&update_option('posts_per_rss','3')===false&&update_option('posts_per_rss','500')===false&&(int)get_option('posts_per_rss')===20; })());
t('Suchmaschinen-Sichtbarkeit (blog_public) ↔ seo.robots',(function() use($siteNow){ $b=$siteNow()['seo'];
    $r0=update_option('blog_public','0');$a=$siteNow()['seo'];$g0=(int)get_option('blog_public');$r1=update_option('blog_public','1');$c=$siteNow()['seo'];
    return $r0&&$a['robots']==='noindex,nofollow'&&$g0===0&&$r1&&$c['robots']==='index,follow'&&array_diff_key($c,['robots'=>1])===array_diff_key($b,['robots'=>1])&&(int)get_option('blog_public')===1; })());
t('blog_public: bestehender Wert „index,nofollow“ bleibt bei 1 unverändert',(function() use($siteNow,$tmp){ $f=$tmp.'/cms/site.json';$j=json_decode((string)file_get_contents($f),true);$j['seo']['robots']='index,nofollow';file_put_contents($f,json_encode($j));
    update_option('blog_public','1');return $siteNow()['seo']['robots']==='index,nofollow'; })());
t('leere Seite: Inhalt wird neuer HTML-Block',(function() use($lid,$siteNow){ wp_update_post(['ID'=>$lid,'post_content'=>'<p>Jetzt Text</p>']);$p=$siteNow()['pages'][2];return count($p['blocks_before'])===1&&$p['blocks_before'][0]['type']==='html'&&$p['blocks_before'][0]['html']==='<p>Jetzt Text</p>'&&get_post($lid)->post_content==='<p>Jetzt Text</p>'; })());
t('Seite: Entwurf (deaktiviert) und zurück',(function() use($pid,$siteNow){ wp_update_post(['ID'=>$pid,'post_status'=>'draft']);$a=$siteNow()['pages'][0]['enabled']===false&&get_post_status($pid)==='draft'&&!in_array($pid,array_map(fn($p)=>$p->ID,get_pages()),true);wp_update_post(['ID'=>$pid,'post_status'=>'publish']);return $a&&$siteNow()['pages'][0]['enabled']===true; })());
t('Slug ändern: Datei, Menü folgt',(function() use($pid,$siteNow,$tmp){ $r=wp_update_post(['ID'=>$pid,'post_name'=>'wir']);$s=$siteNow();return $r===$pid&&$s['pages'][0]['slug']==='wir'&&$s['menus']['top'][0]['target']==='page:wir'&&$s['menus']['top'][0]['x_menu']==='ja'&&is_file($tmp.'/wir.html')&&!is_file($tmp.'/ueber-uns.html')&&get_post($pid)->post_name==='wir'; })());
t('Slug einer festen Website-Seite wird abgelehnt',(function() use($pid,$siteNow){ $e=wp_update_post(['ID'=>$pid,'post_name'=>'impressum'],true);return is_wp_error($e)&&$e->get_error_code()==='slug_reserved'&&$siteNow()['pages'][0]['slug']==='wir'; })());
t('Slug einer anderen Seite wird abgelehnt',(function() use($pid){ $e=wp_update_post(['ID'=>$pid,'post_name'=>'leer'],true);return is_wp_error($e)&&$e->get_error_code()==='slug_taken'; })());
t('CMS-Seiten werden nicht über WordPress gelöscht',wp_delete_post($pid,true)===false&&count($siteNow()['pages'])===count($s0['pages']));
t('Seiten aus CMS und Datenbank gemeinsam in get_pages / WP_Query',(function(){ $t=array_map(fn($p)=>$p->post_title,get_pages());return in_array('Wir über uns',$t,true)&&in_array('WP-Seite',$t,true); })());

/* ───── 5. Menüs ───── */
$m0=$siteNow()['menus'];
t('Menüs lesen: page:<Adresse> wird aufgelöst',(function(){ $it=wp_get_nav_menu_items('top');return $it&&$it[0]->object==='page'&&str_contains($it[0]->url,'/wir/'); })());
$mi=wp_update_nav_menu_item(1,0,['menu-item-title'=>'Extern','menu-item-url'=>'https://example.org/x','menu-item-type'=>'custom','menu-item-status'=>'publish']);
t('Menüpunkt anlegen',$mi>=3000000&&(function() use($siteNow){ $t=$siteNow()['menus']['top'];$l=end($t);return $l['label']==='Extern'&&$l['target']==='url:https://example.org/x'&&$l['enabled']===true&&$l['icon']==='fa-circle'; })());
t('Menüpunkt in wp_get_nav_menu_items',(function() use($mi){ foreach(wp_get_nav_menu_items('top') as $i)if((int)$i->ID===$mi)return $i->url==='https://example.org/x'&&$i->title==='Extern';return false; })());
t('Menüpunkt ändern (Titel)',wp_update_nav_menu_item(1,$mi,['menu-item-title'=>'Extern 2','menu-item-url'=>'https://example.org/y','menu-item-type'=>'custom'])===$mi&&(function() use($siteNow){ $t=$siteNow()['menus']['top'];$l=end($t);return $l['label']==='Extern 2'&&$l['target']==='url:https://example.org/y'; })());
t('Menüpunkt auf CMS-Seite',(function() use($siteNow,$lid){ $i=wp_update_nav_menu_item(2,0,['menu-item-title'=>'Leer','menu-item-type'=>'post_type','menu-item-object'=>'page','menu-item-object-id'=>$lid]);$b=$siteNow()['menus']['bottom'];return $i>0&&$b[count($b)-1]['target']==='page:leer'; })());
t('Menüpunkt mit unbekanntem Ziel wird nicht geschrieben',(function() use($siteNow){ $c=count($siteNow()['menus']['top']);return wp_update_nav_menu_item(1,0,['menu-item-title'=>'X','menu-item-type'=>'taxonomy','menu-item-object'=>'category','menu-item-object-id'=>5])===0&&count($siteNow()['menus']['top'])===$c; })());
t('Menüpunkt löschen',(function() use($mi,$siteNow){ $r=wp_delete_post($mi,true);foreach($siteNow()['menus']['top'] as $m)if($m['label']==='Extern 2')return false;return $r!==false&&$r!==null; })());
t('Übrige Menüpunkte (inkl. Zusatzfeld) unverändert',(function() use($siteNow){ $m=$siteNow()['menus']['top'][0];return $m['id']==='m-ueber'&&$m['x_menu']==='ja'&&$m['label']==='Über uns'; })());

/* ───── 6. Medien ───── */
if(function_exists('imagecreatetruecolor')){ $im=imagecreatetruecolor(400,300);imagefill($im,0,0,imagecolorallocate($im,10,120,200));imagepng($im,$tmp.'/up.png');imagedestroy($im); }
else file_put_contents($tmp.'/up.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
$_FILES['f']=['name'=>'Titelbild.png','type'=>'image/png','tmp_name'=>$tmp.'/up.png','error'=>0,'size'=>filesize($tmp.'/up.png')];
$aid=media_handle_upload('f',0,[],['rrw_mover'=>'rename']);
t('media_handle_upload legt in die CMS-Bibliothek',is_int($aid)&&$aid>=RRW_WP_ID_ATT_BASE&&count(glob($tmp.'/cms/media/library/*/meta.json'))===1);
$url=(string)wp_get_attachment_url($aid);
t('Anhang-URL zeigt in die Bibliothek',str_starts_with($url,'/cms/media/library/')&&str_ends_with($url,'/original.png')&&is_file($tmp.rtrim('/cms/media/library','/').substr($url,strlen('/cms/media/library'))));
t('get_post: Anhang aus der Bibliothek',(function() use($aid){ $p=get_post($aid);return $p&&$p->post_type==='attachment'&&$p->post_mime_type==='image/png'&&$p->post_title==='Titelbild'; })());
t('Anhang in WP_Query (attachment)',in_array($aid,array_map(fn($p)=>$p->ID,get_posts(['post_type'=>'attachment','post_status'=>'any','numberposts'=>-1])),true));
t('Nicht doppelt: keine Datei in wp-content/uploads, keine Zeile in wp_posts',!is_dir($tmp.'/wp-content/uploads')&&(function(){ global $wpdb;return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment'")===0; })());
t('Bildgrößen aus Varianten',(function() use($aid){ $s=wp_get_attachment_image_src($aid,'thumbnail');$f=wp_get_attachment_image_src($aid,'full');$m=wp_get_attachment_metadata($aid);return $s&&$f&&$f[1]>=1&&$m&&isset($m['sizes'])&&(!function_exists('imagewebp')||str_contains($s[0],'w')); })());
t('get_attached_file zeigt auf die Bibliotheksdatei',is_file((string)get_attached_file($aid)));
t('Titelbild eines Beitrags aus der Bibliothek',set_post_thumbnail(5,$aid)&&$rowOf($newsNow(),5)['image_url']===$url&&get_post_thumbnail_id(5)===$aid);
t('Medien der Bibliothek: ID stabil und ohne Registrierung auffindbar',(function() use($aid){ unset($GLOBALS['rrw_wp_cms_attachments'],$GLOBALS['rrw_wp_cms_media_map']);return wp_get_attachment_url($aid)!==false; })());
t('Bibliothek des CMS listet das Medium (gemeinsamer Speicherweg)',(function() use($url){ foreach(rrw_media_library_items([]) as $it)if($it['url']===$url)return true;return false; })());
t('Upload ohne echten Upload abgelehnt',is_wp_error(media_handle_upload('f',0,[],[])));
$_FILES['f']['tmp_name']=$tmp.'/nope.png';
t('Upload ohne Datei → Fehler',is_wp_error(media_handle_upload('f',0,[],['rrw_mover'=>'rename'])));

/* ───── 7. REST ───── */
$req=function(string $method,string $route,array $body=[],array $files=[],array $query=[]) { $_FILES=$files;$r=rrw_wp_rest_dispatch($method,$route,$query,$body?json_encode($body):'',$body?['content-type'=>'application/json']:[]);return [$r['status'],json_decode($r['body'],true)]; };
[$st,$o]=$req('POST','/wp/v2/posts',['title'=>'Per REST','content'=>'<p>REST-Inhalt</p>','status'=>'publish']);
t('REST: Beitrag anlegen (201, News-ID)',$st===201&&$o['id']<10000000&&$o['title']['rendered']==='Per REST'&&$rowOf($newsNow(),(int)$o['id'])['body_html']==='<p>REST-Inhalt</p>');
$rid=(int)$o['id'];
[$st,$o]=$req('POST','/wp/v2/posts/'.$rid,['title'=>'REST geändert','status'=>'draft']);
t('REST: Beitrag ändern',$st===200&&$rowOf($newsNow(),$rid)['title']==='REST geändert'&&$rowOf($newsNow(),$rid)['status']==='draft');
[$st,$o]=$req('DELETE','/wp/v2/posts/'.$rid);
t('REST: DELETE → Papierkorb',$st===200&&!empty($rowOf($newsNow(),$rid)['deleted_at']));
[$st,$o]=$req('DELETE','/wp/v2/posts/'.$rid,[],[],['force'=>'true']);
t('REST: DELETE force → Zeile entfernt',$st===200&&!empty($o['deleted'])&&$rowOf($newsNow(),$rid)===null);
[$st,$o]=$req('POST','/wp/v2/pages/'.$pid,['title'=>'Seite via REST']);
t('REST: CMS-Seite ändern',$st===200&&$siteNow()['pages'][0]['headline']==='Seite via REST');
[$st,$o]=$req('POST','/wp/v2/pages',['title'=>'Neue REST-Seite','status'=>'draft']);
t('REST: neue Seite → Datenbank',$st===201&&$o['id']>=RRW_WP_ID_DB_MIN);
file_put_contents($tmp.'/up2.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
$files=['file'=>['name'=>'rest.png','type'=>'image/png','tmp_name'=>$tmp.'/up2.png','error'=>0,'size'=>filesize($tmp.'/up2.png')]];
[$st,$o]=$req('POST','/wp/v2/media',[],$files);
t('REST: Upload ohne echten Upload → 400',$st===400);
[$st,$o]=$req('GET','/wp/v2/media/'.$aid);
t('REST: Medium lesen',$st===200&&$o['id']===$aid&&str_ends_with($o['source_url'],'original.png')&&$o['mime_type']==='image/png');
[$st,$o]=$req('GET','/wp/v2/media');
t('REST: Medienliste',$st===200&&count($o)===1);
rrw_wp_bridge_set(['options']);$GLOBALS['rrw_wp_rest_routes']=[];
t('Teilweise Brücke: Schreibzugriffe auf Beiträge laufen wieder über die Datenbank',(function() use($newsNow){ $c=count($newsNow());$i=wp_insert_post(['post_title'=>'Nur DB','post_status'=>'publish']);return $i>=RRW_WP_ID_DB_MIN&&count($newsNow())===$c&&wp_update_post(['ID'=>1,'post_title'=>'zz'])===0; })());
rrw_wp_bridge_set(['options','posts','pages','media','menus']);

/* ───── 8. Verwaltung: einheitliche Liste ───── */
$ov=rrw_wp_cms_content_overview();
$src=array_count_values(array_column($ov,'source'));
t('Alle Inhalte: Quellen cms-news, cms-page, wp',($src['cms-news']??0)>=5&&($src['cms-page']??0)>=3&&($src['wp']??0)>=2);
t('Alle Inhalte: Felder',(function() use($ov){ foreach($ov as $it)if(!isset($it['key'],$it['source'],$it['id'],$it['type'],$it['title'],$it['status'],$it['modified']))return false;return true; })());
t('Alle Inhalte: Entwürfe und Papierkorb enthalten',(function() use($ov){ $s=array_column($ov,'status');return in_array('draft',$s,true)&&in_array('trash',$s,true)&&in_array('publish',$s,true); })());

/* ───── 9. Schutz vor Datenverlust ───── */
file_put_contents($tmp.'/cms/news.json','{kaputt');
$c=file_get_contents($tmp.'/cms/news.json');
t('Defekte news.json wird nicht überschrieben',wp_insert_post(['post_title'=>'Verloren?','post_status'=>'publish'])===0&&file_get_contents($tmp.'/cms/news.json')===$c);
t('Fehler als WP_Error mit Grund',is_wp_error($e=wp_insert_post(['post_title'=>'Verloren?','post_status'=>'publish'],true))&&$e->get_error_code()==='cms_write');
t('Sperrdatei wird verwendet',is_file($tmp.'/cms/.site.lock'));

echo $fail?"\n$fail von $n Prüfungen fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";
exit($fail?1:0);

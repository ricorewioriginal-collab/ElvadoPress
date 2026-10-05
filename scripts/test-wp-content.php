<?php
// Prüft das WordPress-Inhaltsmodell: CMS-Beiträge/-Seiten als WP_Post, WP_Query, Taxonomien, Meta, Benutzer, eigene Beitragstypen. Aufruf: php scripts/test-wp-content.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/rrw-wpc-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/cms');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('RRW_WP_DATA',$tmp.'/cms/.wp');define('RRW_WP_CMS_DATA',$tmp.'/cms');$_SERVER['HTTP_HOST']='example.test';
$news=[
 ['id'=>1,'slug'=>'erster','title'=>'Erster Beitrag','category'=>'News','tags'=>'radio, musik','excerpt'=>'Kurz eins','body_html'=>'<p>Hallo Welt vom Radio</p>','status'=>'published','published_at'=>'2026-01-10 10:00:00','author'=>'Anna Autor','image_url'=>'/img/a.jpg','updated_at'=>'2026-01-11 10:00:00'],
 ['id'=>2,'slug'=>'zweiter','title'=>'Zweiter Beitrag','category'=>'Events','tags'=>'musik','excerpt'=>'','body_html'=>'<p>Konzert im Park</p>','status'=>'published','published_at'=>'2026-02-10 10:00:00','author'=>'Ben Bauer'],
 ['id'=>3,'slug'=>'dritter','title'=>'Alpha Dritter','category'=>'News','tags'=>'','excerpt'=>'','body_html'=>'<p>Nachrichten</p>','status'=>'published','published_at'=>'2026-03-10 10:00:00','author'=>'Anna Autor'],
 ['id'=>4,'slug'=>'entwurf','title'=>'Entwurf','category'=>'News','status'=>'draft','published_at'=>'2026-03-11 10:00:00','body_html'=>'x'],
 ['id'=>5,'slug'=>'zukunft','title'=>'Zukunft','category'=>'News','status'=>'published','published_at'=>'2099-01-01 00:00:00','body_html'=>'x'],
];
file_put_contents($tmp.'/cms/news.json',json_encode($news));
file_put_contents($tmp.'/cms/site.json',json_encode(['pages'=>[['id'=>'start','slug'=>'start','title'=>'Start','type'=>'system','enabled'=>true],['id'=>'ueber','slug'=>'ueber-uns','title'=>'Über uns','headline'=>'Über uns','type'=>'custom','enabled'=>true,'blocks_before'=>[['type'=>'html','html'=>'<p>Wir sind ein Radio.</p>']],'blocks_after'=>[]],['id'=>'aus','slug'=>'aus','title'=>'Aus','type'=>'custom','enabled'=>false]]]));
require __DIR__."/_testdb.php";
require __DIR__.'/../cms/wp/load.php';
$fail=0;$n=0;
function t(string $name,bool $ok): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name\n";} }
rrw_wp_boot();

/* CMS-Beiträge als WP_Post */
$q=new WP_Query(['post_type'=>'post']);
t('nur veröffentlichte, vergangene Beiträge',$q->post_count===3&&!in_array('entwurf',array_column($q->posts,'post_name'),true)&&!in_array('zukunft',array_column($q->posts,'post_name'),true));
t('neueste zuerst',$q->posts[0]->post_name==='dritter'&&$q->posts[2]->post_name==='erster');
$p=get_post(1);
t('get_post(ID)',$p instanceof WP_Post&&$p->post_title==='Erster Beitrag'&&$p->post_type==='post'&&$p->post_status==='publish'&&$p->post_name==='erster');
t('Inhalt/Auszug/Datum',$p->post_content==='<p>Hallo Welt vom Radio</p>'&&$p->post_excerpt==='Kurz eins'&&$p->post_date==='2026-01-10 10:00:00');
t('get_post für Entwurf → nichts',get_post(4)===null);
t('get_post_field/status/type',get_post_field('post_title',1)==='Erster Beitrag'&&get_post_status(1)==='publish'&&get_post_type(1)==='post');
t('Titelbild',has_post_thumbnail(1)&&get_the_post_thumbnail_url(1)==='/img/a.jpg'&&!has_post_thumbnail(2));
t('Seite aus CMS',(function(){ $pg=get_page_by_path('ueber-uns');return $pg&&$pg->post_type==='page'&&str_contains($pg->post_content,'Wir sind ein Radio')&&$pg->post_title==='Über uns'; })());
t('deaktivierte/System-Seiten nicht sichtbar',count(get_pages())===1);
t('Seiten-ID stabil',get_page_by_path('ueber-uns')->ID===get_page_by_path('ueber-uns')->ID&&get_page_by_path('ueber-uns')->ID>=RRW_WP_ID_PAGE_BASE);

/* Taxonomien */
t('Kategorien',array_column(get_categories(),'name')===['Events','News']||array_column(get_terms(['taxonomy'=>'category']),'name')===['Events','News']);
t('Schlagwörter mit Zähler',(function(){ foreach(get_tags() as $t)if($t->name==='musik')return $t->count===2;return false; })());
t('get_the_category',get_the_category(1)[0]->name==='News'&&get_the_category(1)[0]->slug==='news');
t('get_the_tags',count(get_the_tags(1))===2&&get_the_tags(3)===false);
t('Abfrage nach Kategorie-Slug',(function(){ $x=new WP_Query(['category_name'=>'events']);return $x->post_count===1&&$x->posts[0]->ID===2; })());
t('Abfrage nach Kategorie-ID',(function(){ $c=get_category_by_slug('news');$x=new WP_Query(['cat'=>$c->term_id]);return $x->post_count===2; })());
t('Abfrage nach Schlagwort',(new WP_Query(['tag'=>'musik']))->post_count===2);
t('tag__and',(new WP_Query(['tag_slug__and'=>['radio','musik']]))->post_count===1);
t('tax_query NOT IN',(new WP_Query(['tax_query'=>[['taxonomy'=>'category','field'=>'slug','terms'=>['news'],'operator'=>'NOT IN']]]))->post_count===1);
t('has_term / has_category',has_term('musik','post_tag',1)&&has_category('news',1)&&!has_category('events',1));
t('get_term_by',get_term_by('slug','events','category')->name==='Events');
t('Kategorie-Link',str_contains(get_category_link(get_term_by('slug','news','category')->term_id),'/category/news/'));

/* WP_Query: Filter, Suche, Sortierung, Seiten */
t('Suche',(new WP_Query(['s'=>'konzert']))->post_count===1&&(new WP_Query(['s'=>'radio -konzert']))->post_count===1&&(new WP_Query(['s'=>'"hallo welt"']))->post_count===1);
t('Sortierung nach Titel',(new WP_Query(['orderby'=>'title','order'=>'ASC']))->posts[0]->post_name==='dritter');
t('Sortierung ID aufsteigend',(new WP_Query(['orderby'=>'ID','order'=>'ASC']))->posts[0]->ID===1);
t('post__in',(new WP_Query(['post__in'=>[1,2],'orderby'=>'post__in']))->posts[0]->ID===1&&(new WP_Query(['post__in'=>[2,1],'orderby'=>'post__in']))->posts[0]->ID===2);
t('post__not_in',(new WP_Query(['post__not_in'=>[3]]))->post_count===2);
t('name',(new WP_Query(['name'=>'zweiter']))->post_count===1);
t('Autor-Filter',(new WP_Query(['author'=>rrw_wp_cms_author_id('Anna Autor')]))->post_count===2);
t('Datum year/monthnum',(new WP_Query(['year'=>2026,'monthnum'=>2]))->post_count===1);
t('date_query',(new WP_Query(['date_query'=>[['after'=>'2026-02-01','inclusive'=>true]]]))->post_count===2);
$pq=new WP_Query(['posts_per_page'=>2,'paged'=>1]);t('Seitenaufteilung',$pq->post_count===2&&$pq->found_posts===3&&$pq->max_num_pages===2);
$pq2=new WP_Query(['posts_per_page'=>2,'paged'=>2]);t('Seite 2',$pq2->post_count===1&&$pq2->posts[0]->ID===1);
t('posts_per_page=-1',(new WP_Query(['posts_per_page'=>-1]))->post_count===3);
t('offset',(new WP_Query(['posts_per_page'=>1,'offset'=>1]))->posts[0]->ID===2);
t('fields=ids',(new WP_Query(['fields'=>'ids']))->posts===[3,2,1]);
t('get_posts numberposts',count(get_posts(['numberposts'=>2]))===2&&get_posts(['numberposts'=>1])[0]->ID===3);
t('get_posts include',array_column(get_posts(['include'=>[1,3]]),'ID')===[3,1]);
t('post_status any zeigt keine CMS-Entwürfe (sie gehören nicht zum Modell)',(new WP_Query(['post_status'=>'any']))->post_count===3);
t('wp_count_posts',wp_count_posts('post')->publish>=3);

/* Eigene Inhalte in der Datenbank */
$id=wp_insert_post(['post_title'=>'Mein Post','post_content'=>'Inhalt [forum]','post_status'=>'publish','post_type'=>'post','post_date'=>'2026-04-01 10:00:00']);
t('wp_insert_post (DB-ID ab 100 Mio.? ',$id>0);
t('Datenbank-Beitrag in Abfrage zusammen mit CMS',(new WP_Query(['post_type'=>'post']))->post_count===4&&(new WP_Query(['post_type'=>'post']))->posts[0]->ID===$id);
t('get_post für DB-Beitrag',get_post($id)->post_title==='Mein Post'&&get_post($id)->post_name==='mein-post');
t('eindeutiger Slug (CMS-Slug belegt)',get_post(wp_insert_post(['post_title'=>'Erster','post_name'=>'erster','post_status'=>'publish']))->post_name!=='erster');
t('wp_update_post',wp_update_post(['ID'=>$id,'post_title'=>'Neu'])===$id&&get_post($id)->post_title==='Neu'&&get_post($id)->post_content==='Inhalt [forum]');
t('CMS-Beitrag nicht änderbar',is_wp_error(wp_update_post(['ID'=>1,'post_title'=>'x'],true))||wp_update_post(['ID'=>1,'post_title'=>'x'])===0);
t('CMS-Beitrag nicht löschbar',wp_delete_post(1,true)===false);
t('Leerer Beitrag abgewiesen',wp_insert_post(['post_title'=>'','post_content'=>''])===0);
$saved=[];add_action('save_post',function($pid,$post,$update) use(&$saved){ $saved[]=[$pid,$update]; },10,3);$id2=wp_insert_post(['post_title'=>'Hook','post_status'=>'draft']);wp_update_post(['ID'=>$id2,'post_title'=>'Hook2']);
t('save_post feuert',$saved===[[$id2,false],[$id2,true]]);
t('Entwurf nicht öffentlich',!in_array($id2,array_column(get_posts(['numberposts'=>-1]),'ID'),true)&&in_array($id2,array_column(get_posts(['numberposts'=>-1,'post_status'=>'draft']),'ID'),true));
t('Papierkorb',wp_trash_post($id2)&&get_post_status($id2)==='trash'&&wp_untrash_post($id2)&&get_post_status($id2)==='draft');
t('wp_delete_post force',wp_delete_post($id2,true)!==false&&get_post($id2)===null);

/* Meta */
t('Meta hinzufügen/lesen',add_post_meta($id,'farbe','rot')>0&&get_post_meta($id,'farbe',true)==='rot'&&get_post_meta($id,'farbe')===['rot']);
t('Meta unique',add_post_meta($id,'farbe','blau',true)===false);
t('Meta Array bleibt Array',update_post_meta($id,'liste',['a'=>1,'b'=>[2,3]])&&get_post_meta($id,'liste',true)===['a'=>1,'b'=>[2,3]]);
t('Meta aktualisieren',update_post_meta($id,'farbe','grün')&&get_post_meta($id,'farbe',true)==='grün'&&!update_post_meta($id,'farbe','grün'));
t('Meta auch für CMS-Beiträge',update_post_meta(1,'sterne',5)&&get_post_meta(1,'sterne',true)==='5'||get_post_meta(1,'sterne',true)===5);
t('Meta löschen',delete_post_meta($id,'farbe')&&get_post_meta($id,'farbe',true)==='');
t('get_post_meta ohne Schlüssel',isset(get_post_meta($id)['liste']));
t('meta_query',(function() use($id){ add_post_meta($id,'preis','15');$x=new WP_Query(['post_type'=>'post','meta_query'=>[['key'=>'preis','value'=>10,'compare'=>'>','type'=>'NUMERIC']]]);return $x->post_count===1&&$x->posts[0]->ID===$id; })());
t('meta_key + orderby meta_value_num',(function() use($id){ add_post_meta(1,'preis','5');$x=new WP_Query(['post_type'=>'post','meta_key'=>'preis','orderby'=>'meta_value_num','order'=>'ASC']);return $x->post_count===2&&$x->posts[0]->ID===1; })());
t('meta EXISTS / NOT EXISTS',(new WP_Query(['post_type'=>'post','meta_query'=>[['key'=>'preis','compare'=>'EXISTS']]]))->post_count===2&&(new WP_Query(['post_type'=>'post','meta_query'=>[['key'=>'preis','compare'=>'NOT EXISTS']]]))->post_count>=2);

/* Eigener Beitragstyp + Taxonomie (typischer Plugin-Fall) */
register_post_type('buch',['label'=>'Bücher','public'=>true,'supports'=>['title','editor','thumbnail']]);
register_taxonomy('genre','buch',['label'=>'Genres','hierarchical'=>true]);
t('post_type_exists / get_post_types',post_type_exists('buch')&&in_array('buch',get_post_types(['public'=>true])));
t('taxonomy_exists / get_object_taxonomies',taxonomy_exists('genre')&&in_array('genre',get_object_taxonomies('buch')));
$b=wp_insert_post(['post_title'=>'Das Buch','post_type'=>'buch','post_status'=>'publish']);
$t=wp_insert_term('Krimi','genre');t('wp_insert_term',!is_wp_error($t)&&$t['term_id']>0);
t('doppelter Begriff',is_wp_error(wp_insert_term('Krimi','genre')));
wp_set_object_terms($b,['krimi','Thriller'],'genre');
t('wp_set_object_terms legt neue an',count(wp_get_object_terms($b,'genre'))===2&&term_exists('Thriller','genre')!==null);
t('Abfrage CPT',(new WP_Query(['post_type'=>'buch']))->post_count===1&&(new WP_Query(['post_type'=>'buch','tax_query'=>[['taxonomy'=>'genre','field'=>'slug','terms'=>'krimi']]]))->post_count===1&&(new WP_Query(['post_type'=>'buch','tax_query'=>[['taxonomy'=>'genre','field'=>'slug','terms'=>'andere']]]))->post_count===0);
t('Begriffs-Zähler',get_term_by('slug','krimi','genre')->count===1);
t('Begriff aktualisieren/löschen',!is_wp_error(wp_update_term($t['term_id'],'genre',['name'=>'Kriminalroman']))&&get_term($t['term_id'],'genre')->name==='Kriminalroman'&&wp_delete_term($t['term_id'],'genre')&&get_term($t['term_id'],'genre')===null);
t('CMS-Begriff nicht änderbar',is_wp_error(wp_update_term(get_term_by('slug','news','category')->term_id,'category',['name'=>'x'])));
t('Termmeta',(function() use($b){ $tt=get_term_by('slug','thriller','genre');return add_term_meta($tt->term_id,'icon','x')>0&&get_term_meta($tt->term_id,'icon',true)==='x'; })());
t('any enthält öffentliche Typen',(new WP_Query(['post_type'=>'any']))->post_count>=5);

/* Hooks der Abfrage */
add_action('pre_get_posts',function($q){ if($q->get('rrw_test')==='1')$q->set('posts_per_page',1); });
t('pre_get_posts',(new WP_Query(['rrw_test'=>'1','post_type'=>'post']))->post_count===1);
add_filter('the_posts',function($posts,$q){ if($q->get('rrw_test')==='2')return array_slice($posts,0,2);return $posts; },10,2);
t('the_posts',(new WP_Query(['rrw_test'=>'2','post_type'=>'post']))->post_count===2);

/* Die Schleife */
$GLOBALS['wp_query']=new WP_Query(['post_type'=>'post','posts_per_page'=>3]);$titles=[];$seen=null;
while(have_posts()){ the_post(); $titles[]=$GLOBALS['post']->post_title;$seen=$GLOBALS['id']; }
t('The Loop (have_posts/the_post)',count($titles)===3&&$seen!==null);
t('Schleife wiederholbar nach Ende',(function(){ $c=0;while(have_posts()){the_post();$c++;}return $c===0||$c===3; })());
t('Bedingungen: Standard = Startseite',(function(){ $GLOBALS['wp_query']=new WP_Query(['post_type'=>'post']);return is_home()&&!is_single()&&!is_page()&&!is_archive()&&!is_search(); })());
t('Bedingungen: Suche',(function(){ $GLOBALS['wp_query']=new WP_Query(['s'=>'radio']);return is_search()&&!is_home(); })());
t('Bedingungen: Seite',(function(){ $GLOBALS['wp_query']=new WP_Query(['pagename'=>'ueber-uns','post_type'=>'page']);return is_page()&&is_singular()&&get_queried_object_id()>0; })());
t('Bedingungen: Kategorie-Archiv',(function(){ $GLOBALS['wp_query']=new WP_Query(['category_name'=>'news']);return is_category()&&is_archive(); })());

/* Benutzer */
t('Autoren aus Beiträgen',get_user_by('login','anna-autor')!==false&&get_userdata(rrw_wp_cms_author_id('Ben Bauer'))->display_name==='Ben Bauer');
t('get_users',count(get_users())>=2);
$uid=wp_create_user('neuling','geheim-123','n@example.com');
t('wp_create_user',!is_wp_error($uid)&&get_user_by('email','n@example.com')!==false&&username_exists('neuling')===$uid);
t('doppelter Benutzer',is_wp_error(wp_create_user('neuling','x','x@example.com')));
t('Benutzer-Meta',update_user_meta($uid,'first_name','Nina')&&get_user_meta($uid,'first_name',true)==='Nina');
t('Rechte nach Rolle',(function(){ $u=new WP_User(0);$u->init((object)['ID'=>9,'user_login'=>'x','role'=>'editor']);return $u->has_cap('edit_others_posts')&&!$u->has_cap('manage_options'); })());
t('Avatar-URL',str_contains(get_avatar_url('a@b.de'),'gravatar.com/avatar/'));

system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

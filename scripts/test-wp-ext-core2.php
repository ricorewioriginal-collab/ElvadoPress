<?php
// Prüft die ergänzenden Kern-/Klassen-Funktionen der WordPress-Schicht (cms/wp/core/ext/core2-*.php): Menüs, Blogroll, Feeds, HTTPS, Schriften, Sitemaps, Wiederherstellung,
// Dateisystem/PclZip, Upgrader-Skins, Listen-Tabellen, Site Health, Kernklassen, Sitzungen, Übersetzungsdateien, Blöcke, REST/oEmbed, Widgets, HTTP, XML-RPC, Bild-Editoren.
// Aufruf: php scripts/test-wp-ext-core2.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/rrw-extcore2-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/wp-content/plugins');mkdir($tmp.'/wp-content/languages');mkdir($tmp.'/cms');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('RRW_WP_DATA',$tmp.'/cms/.wp');define('RRW_WP_CMS_DATA',$tmp.'/cms');define('RRW_WP_TESTING',true);
$_SERVER['HTTP_HOST']='example.test';file_put_contents($tmp.'/cms/site.json','{}');$GLOBALS['RRW_SITE']=[];
require __DIR__.'/_testdb.php';
require __DIR__.'/../cms/wp/load.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
function as_user(int $id,string $login,string $role): void { $GLOBALS['rrw_wp_user']=['id'=>$id,'login'=>$login,'name'=>$login,'email'=>$login.'@example.test','role'=>$role,'caps'=>rrw_wp_caps_for_role($role)]; }
rrw_wp_boot(['theme'=>false,'user'=>['id'=>1,'login'=>'admin','name'=>'Administration','email'=>'a@example.test','role'=>'administrator']]);
$GLOBALS['rrw_wp_die_throws']=true;
function out(callable $f): string { ob_start();try{ $f(); }finally{ $s=ob_get_clean(); }return $s; }
function rm_rf(string $d): void { if(!is_dir($d))return;foreach(scandir($d) as $x){ if($x==='.'||$x==='..')continue;is_dir("$d/$x")&&!is_link("$d/$x")?rm_rf("$d/$x"):@unlink("$d/$x"); }@rmdir($d); }

/* ───────── Vollständigkeit: alle Funktionen und Klassen der Liste ───────── */
// Ausnahme: class_alias ist eine PHP-Funktion (steht in der Liste unter den Klassen)
$funcs=preg_split('/\s+/',trim(<<<'LISTE'
is_nav_menu_item wp_create_nav_menu wp_delete_nav_menu wp_update_nav_menu_object _is_valid_nav_menu_item update_menu_item_cache wp_setup_nav_menu_item
wp_get_associated_nav_menu_items _wp_delete_post_menu_item _wp_delete_tax_menu_item _wp_auto_add_pages_to_menu
_wp_delete_customize_changeset_dependent_auto_drafts _wp_menus_changed wp_map_nav_menu_locations _wp_reset_invalid_menu_item_parent fetch_rss
_fetch_remote_file _response_to_rss init is_info is_success is_redirect is_error is_client_error is_server_error parse_w3cdtf wp_rss get_rss
wp_print_font_faces wp_print_font_faces_from_style_variations wp_register_font_collection wp_unregister_font_collection wp_get_font_dir wp_font_dir
_wp_filter_font_directory _wp_after_delete_font_family _wp_before_delete_font_face _wp_register_default_font_collections wp_is_using_https
wp_is_home_url_using_https wp_is_site_url_using_https wp_is_https_supported wp_get_https_detection_errors wp_is_local_html_output get_bookmark
get_bookmark_field get_bookmarks sanitize_bookmark sanitize_bookmark_field clean_bookmark_cache wp_initial_constants wp_plugin_directory_constants
wp_cookie_constants wp_ssl_constants wp_functionality_constants wp_templating_constants wp_paused_plugins wp_paused_themes
wp_get_extension_error_description wp_register_fatal_error_handler wp_is_fatal_error_handler_enabled wp_recovery_mode wp_sitemaps_get_server
wp_get_sitemap_providers wp_register_sitemap_provider wp_sitemaps_get_max_urls get_sitemap_url _wp_can_use_pcre_u _is_utf8_charset _mb_substr
_mb_strlen read_big_endian read skip wp_get_speculation_rules_configuration wp_get_speculation_rules wp_print_speculation_rules
wp_should_replace_insecure_home_url wp_update_urls_to_https wp_update_https_migration_required _walk_bookmarks wp_list_bookmarks get_file class_alias
LISTE));
$classes=preg_split('/\s+/',trim(<<<'LISTE'
WP_Site_Icon Theme_Upgrader_Skin WP_Privacy_Policy_Content WP_Privacy_Data_Removal_Requests_List_Table WP_Themes_List_Table WP_Plugins_List_Table
WP_Application_Passwords_List_Table WP_Internal_Pointers Custom_Background WP_Comments_List_Table WP_Site_Health_Auto_Updates WP_Filesystem_SSH2
Plugin_Upgrader_Skin Walker_Nav_Menu_Checklist ftp_base Walker_Category_Checklist WP_Users_List_Table PclZip WP_Links_List_Table
WP_Filesystem_ftpsockets Custom_Image_Header WP_Privacy_Requests_Table WP_Site_Health WP_Filesystem_FTPext WP_Posts_List_Table ftp_pure
WP_Plugin_Install_List_Table Walker_Nav_Menu_Edit Bulk_Plugin_Upgrader_Skin WP_Debug_Data _WP_List_Table_Compat File_Upload_Upgrader
Bulk_Theme_Upgrader_Skin ftp_sockets WP_Media_List_Table WP_Theme_Install_List_Table Language_Pack_Upgrader_Skin
WP_Privacy_Data_Export_Requests_List_Table WP_Post_Comments_List_Table WP_Community_Events wp_xmlrpc_server WP_HTTP_Requests_Response AtomFeed
AtomEntry AtomParser Tile Prop Dim_Prop Chan_Prop Features Box Parser WP_Object_Cache WP_Customize_Nav_Menus WP_Dependencies WP_Block_Parser_Block
WP_Theme_JSON_Data WP_Exception WP_oEmbed WP MagpieRSS RSSCache WP_Speculation_Rules Services_JSON WP_REST_Post_Search_Handler WP_REST_Search_Handler
WP_REST_Term_Search_Handler WP_REST_Post_Format_Search_Handler WP_REST_User_Meta_Fields WP_REST_Meta_Fields WP_REST_Post_Meta_Fields
WP_REST_Comment_Meta_Fields WP_REST_Term_Meta_Fields WP_REST_Template_Autosaves_Controller WP_REST_Global_Styles_Revisions_Controller WP_PHPMailer
POP3 WP_Text_Diff_Renderer_Table Snoopy WP_Block_Supports _WP_Dependency WP_Application_Passwords WP_Navigation_Fallback WP_HTTP_IXR_Client
WP_Image_Editor_GD WP_Image_Editor WP_HTTP_Requests_Hooks WP_Recovery_Mode_Link_Service WP_Customize_Widgets PasswordHash WP_HTTP_Fsockopen WP_Site
WP_Block_Bindings_Source WP_oEmbed_Controller WP_SimplePie_File WP_Feed_Cache_Transient WP_Block_Parser_Frame WP_Text_Diff_Renderer_inline
Walker_PageDropdown WP_Locale_Switcher WP_Recovery_Mode WP_URL_Pattern_Prefixer WP_Translation_File_MO WP_Translations WP_Translation_Controller
WP_Translation_File WP_Translation_File_PHP WP_Fatal_Error_Handler WP_Site_Query WP_Textdomain_Registry WP_Recovery_Mode_Email_Service
WP_Block_Bindings_Registry WP_Duotone WP_Feed_Cache WP_Image_Editor_Imagick WP_Block_Parser WP_Token_Map _WP_Editors WP_List_Util WP_Theme_JSON_Schema
Requests WP_Hook WP_MatchesMapRegex WP_Paused_Extensions_Storage WP_Block_Pattern_Categories_Registry WP_Block_List WP_Plugin_Dependencies
WP_Classic_To_Block_Menu_Converter WP_SimplePie_Sanitize_KSES Walker_CategoryDropdown WP_Recovery_Mode_Cookie_Service WP_Http_Encoding
WP_Session_Tokens WP_Recovery_Mode_Key_Service WP_User_Meta_Session_Tokens WP_Widget_Media_Video WP_Widget_Media_Gallery WP_Widget_Media
WP_Widget_Links WP_Widget_Calendar WP_Widget_RSS WP_Widget_Media_Image WP_Widget_Media_Audio WP_Widget_Block WP_Block_Templates_Registry
LISTE));
$mf=[];foreach($funcs as $f)if(!function_exists($f))$mf[]=$f;
$mc=[];foreach($classes as $c)if(!class_exists($c)&&!interface_exists($c)&&!trait_exists($c))$mc[]=$c;
t('alle '.count($funcs).' Funktionen der Liste vorhanden',count($funcs)===84&&!$mf,implode(',',$mf));
t('alle '.count($classes).' Klassen der Liste vorhanden (class_alias ist eine PHP-Funktion)',count($classes)===145&&!$mc,implode(',',$mc));

/* ───────── Navigationsmenüs ───────── */
t('is_nav_menu_item: kein Menüpunkt',!is_nav_menu_item(987654));
t('_is_valid_nav_menu_item',_is_valid_nav_menu_item(['a'=>1])&&!_is_valid_nav_menu_item(['_invalid'=>true]));
t('wp_create_nav_menu: leerer Name',wp_create_nav_menu('')->get_error_code()==='menu_name_empty');
t('wp_create_nav_menu: Name vorhanden',wp_create_nav_menu('top')->get_error_code()==='menu_exists');
t('wp_create_nav_menu: Menüs liegen im CMS',wp_create_nav_menu('Neu')->get_error_code()==='menus_cms');
t('wp_delete_nav_menu: unbekanntes Menü false',wp_delete_nav_menu('gibts-nicht')===false&&is_wp_error(wp_delete_nav_menu('top')));
$mi=wp_setup_nav_menu_item((object)['ID'=>5,'title'=>'A','url'=>'/a']);
t('wp_setup_nav_menu_item füllt Felder',$mi->db_id===5&&$mi->classes===[]&&$mi->type==='custom'&&$mi->menu_item_parent===0);
t('wp_map_nav_menu_locations: gleiche Positionen',wp_map_nav_menu_locations(['primary'=>0,'footer'=>0],['primary'=>7,'footer'=>9])===['primary'=>7,'footer'=>9]);
t('wp_map_nav_menu_locations: ähnliche Positionen',wp_map_nav_menu_locations(['haupt'=>0,'fuss-footer'=>0],['x'=>3,'unten'=>4])===['haupt'=>3,'fuss-footer'=>4]);
$it=[(object)['db_id'=>1,'menu_item_parent'=>0],(object)['db_id'=>2,'menu_item_parent'=>77]];
t('_wp_reset_invalid_menu_item_parent',_wp_reset_invalid_menu_item_parent(1,$it)[1]->menu_item_parent===0);
t('wp_get_associated_nav_menu_items: nichts',wp_get_associated_nav_menu_items(5)===[]);
t('Menü-Aufräumfunktionen sind No-ops',_wp_menus_changed()===null&&_wp_delete_post_menu_item(1)===null);
t('Convert: unbekanntes Menü',WP_Classic_To_Block_Menu_Converter::convert('nix')->get_error_code()==='invalid_menu');
t('Convert: Menü ohne Einträge',WP_Classic_To_Block_Menu_Converter::convert('top')==='');
$w=new Walker_Category_Checklist();$o='';$w->start_el($o,(object)['term_id'=>4,'name'=>'Musik','parent'=>0,'count'=>1],0,['selected_cats'=>[4],'taxonomy'=>'category']);
t('Walker_Category_Checklist',str_contains($o,'name="post_category[]"')&&str_contains($o,'checked')&&str_contains($o,'Musik'));
$w=new Walker_CategoryDropdown();$o='';$w->start_el($o,(object)['term_id'=>4,'name'=>'Musik','parent'=>0,'count'=>3],1,['selected'=>4,'show_count'=>true]);
t('Walker_CategoryDropdown',str_contains($o,'selected="selected"')&&str_contains($o,'&nbsp;&nbsp;&nbsp;Musik')&&str_contains($o,'(3)'));
$w=new Walker_PageDropdown();$o='';$w->start_el($o,(object)['ID'=>9,'post_title'=>'','post_parent'=>0],0,['selected'=>1]);
t('Walker_PageDropdown: Titel leer',str_contains($o,'#9 (ohne Titel)')&&str_contains($o,'value="9"'));
$w=new Walker_Nav_Menu_Checklist();$o='';$w->start_el($o,(object)['ID'=>3,'title'=>'X','url'=>'/x','type'=>'custom','object_id'=>0],0);
t('Walker_Nav_Menu_Checklist/Edit',str_contains($o,'menu-item[3]')&&str_contains((function(){ $w=new Walker_Nav_Menu_Edit();$o='';$w->start_el($o,(object)['ID'=>3,'title'=>'Y','url'=>'/y'],0);return $o; })(),'menu-item-settings-3'));
t('WP_Customize_Nav_Menus: Nonces/Typen',isset((new WP_Customize_Nav_Menus(null))->filter_nonces([])['customize-menus'])&&is_array((new WP_Customize_Nav_Menus(null))->available_item_types()));
t('WP_Navigation_Fallback ohne Navigationsbeitrag',WP_Navigation_Fallback::get_fallback()===null);

/* ───────── Blogroll ───────── */
global $wpdb;
$wpdb->insert($wpdb->links,['link_url'=>'https://b.example/','link_name'=>'Bravo','link_description'=>'zweiter','link_visible'=>'Y','link_rating'=>5,'link_rel'=>'friend','link_notes'=>'']);$lb=(int)$wpdb->insert_id;
$wpdb->insert($wpdb->links,['link_url'=>'https://a.example/','link_name'=>'Alpha','link_description'=>'erster','link_visible'=>'Y','link_rating'=>9,'link_notes'=>'']);$la=(int)$wpdb->insert_id;
$wpdb->insert($wpdb->links,['link_url'=>'https://c.example/','link_name'=>'Charlie','link_visible'=>'N','link_notes'=>'']);$lc=(int)$wpdb->insert_id;
wp_cache_flush();
$all=get_bookmarks();
t('get_bookmarks: sichtbare, nach Name',array_map(fn($x)=>$x->link_name,$all)===['Alpha','Bravo']);
t('get_bookmarks: hide_invisible=0',count(get_bookmarks(['hide_invisible'=>0]))===3);
t('get_bookmarks: orderby rating DESC',get_bookmarks(['orderby'=>'rating','order'=>'DESC'])[0]->link_name==='Alpha');
t('get_bookmarks: limit',count(get_bookmarks(['limit'=>1]))===1);
t('get_bookmarks: Suche',count(get_bookmarks(['search'=>'zweiter']))===1&&get_bookmarks(['search'=>'zweiter'])[0]->link_name==='Bravo');
t('get_bookmarks: include/exclude',count(get_bookmarks(['include'=>"$lb"]))===1&&count(get_bookmarks(['exclude'=>"$lb"]))===1);
t('get_bookmarks: random behält Menge',count(get_bookmarks(['orderby'=>'rand']))===2);
$b=get_bookmark($la);
t('get_bookmark: Objekt/Array',$b->link_name==='Alpha'&&get_bookmark($la,ARRAY_A)['link_url']==='https://a.example/'&&get_bookmark(999999)===null);
t('get_bookmark_field',get_bookmark_field('link_name',$la)==='Alpha'&&get_bookmark_field('link_url',$la)==='https://a.example/'&&get_bookmark_field('nix',$la)==='');
t('sanitize_bookmark_field: Kontexte',sanitize_bookmark_field('link_rating','7x',1,'display')===7&&sanitize_bookmark_field('link_name','a"b',1,'attribute')==='a&quot;b'&&sanitize_bookmark_field('link_url','javascript:alert(1)',1,'db')==='');
t('sanitize_bookmark: Objekt und Array',sanitize_bookmark((object)['link_url'=>'https://x.example/a b','link_rating'=>'3'])->link_rating===3&&is_array(sanitize_bookmark(['link_id'=>'4'])));
$h=wp_list_bookmarks(['echo'=>0,'categorize'=>0,'title_li'=>'Freunde']);
t('wp_list_bookmarks: Liste',str_contains($h,'Freunde')&&str_contains($h,'>Alpha</a>')&&str_contains($h,'rel="friend"')&&!str_contains($h,'Charlie'));
t('_walk_bookmarks: Beschreibung',str_contains(_walk_bookmarks([$b],['show_description'=>1,'show_images'=>0]),'erster'));
$wpdb->update($wpdb->links,['link_name'=>'Alpha 2'],['link_id'=>$la]);clean_bookmark_cache($la);
t('clean_bookmark_cache: neuer Name sichtbar',get_bookmarks()[0]->link_name==='Alpha 2');

/* ───────── Feeds (MagpieRSS, Atom, Snoopy …) ───────── */
t('HTTP-Status-Hilfen',is_info(101)&&is_success(204)&&is_redirect(302)&&is_error(404)&&is_client_error(404)&&is_server_error(503)&&!is_error(200));
t('parse_w3cdtf',parse_w3cdtf('2024-05-06T07:08:09Z')===gmmktime(7,8,9,5,6,2024)&&parse_w3cdtf('2024-05-06T09:08:09+02:00')===gmmktime(7,8,9,5,6,2024)&&parse_w3cdtf('quatsch')===-1);
init();t('init: Magpie-Konstanten',defined('MAGPIE_CACHE_AGE'));
$rss2='<?xml version="1.0"?><rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/"><channel><title>Radio</title><link>https://r.example/</link><item><title>Eins</title><link>https://r.example/1</link><description>Text eins</description><pubDate>Mon, 06 May 2024 07:08:09 GMT</pubDate><dc:creator>Ric</dc:creator></item><item><title>Zwei</title><link>https://r.example/2</link></item></channel></rss>';
$m=new MagpieRSS($rss2);
t('MagpieRSS: RSS 2',$m->feed_type==='rss'&&$m->channel['title']==='Radio'&&count($m->items)===2&&$m->items[0]['dc:creator']==='Ric'&&$m->items[0]['date_timestamp']===1714979289&&$m->items[0]['summary']==='Text eins');
$atom='<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"><title>A-Feed</title><link href="https://a.example/"/><entry><title>E1</title><id>x1</id><link href="https://a.example/e1"/><updated>2024-05-06T07:08:09Z</updated><summary>Zus</summary></entry></feed>';
$m2=new MagpieRSS($atom);
t('MagpieRSS: Atom',$m2->feed_type==='atom'&&$m2->channel['title']==='A-Feed'&&$m2->items[0]['link']==='https://a.example/e1'&&$m2->items[0]['date_timestamp']===1714979289);
t('MagpieRSS: ungültig',(new MagpieRSS('kein xml'))->ERROR!==''&&(new MagpieRSS(''))->ERROR!=='');
$ap=new AtomParser();$okp=$ap->parse($atom);
t('AtomParser',$okp&&$ap->feed->title==='A-Feed'&&$ap->feed->entries[0]->links[0]['href']==='https://a.example/e1'&&!(new AtomParser())->parse('nix'));
$hits=0;add_filter('pre_http_request',function($pre,$args,$url) use(&$hits,$rss2){ $hits++;return ['headers'=>['etag'=>'"e1"'],'body'=>$rss2,'response'=>['code'=>200,'message'=>'OK'],'cookies'=>[],'filename'=>null]; },10,3);
$r=_fetch_remote_file('https://r.example/feed');
t('_fetch_remote_file',$r->status===200&&str_contains($r->results,'<rss')&&$r->headers!==[]);
t('_response_to_rss',_response_to_rss($r)->items[1]['title']==='Zwei');
$f1=fetch_rss('https://r.example/feed');$f2=fetch_rss('https://r.example/feed');
t('fetch_rss: Zwischenspeicher',$f1->channel['title']==='Radio'&&$f2->channel['title']==='Radio'&&$hits===2&&fetch_rss('')===false);
$cache=new RSSCache('',100);
t('RSSCache: MISS/HIT',$cache->check_cache('https://nie.example/')==='MISS'&&$cache->check_cache('https://r.example/feed')==='HIT');
t('get_rss/wp_rss Ausgabe',str_contains(out(fn()=>get_rss('https://r.example/feed',1)),'>Eins</a>')&&!str_contains(out(fn()=>get_rss('https://r.example/feed',1)),'Zwei')&&str_contains(out(fn()=>wp_rss('https://r.example/feed')),'rsswidget'));
$sn=new Snoopy();$sok=$sn->fetch('https://r.example/feed');
t('Snoopy: fetch/fetchlinks/striptext',$sok&&$sn->status===200&&$sn->striptext('<p>Hi <b>du</b></p><script>x</script>')==='Hi du'&&$sn->striplinks('<a href="/x">x</a>')===['/x']);
$sj=new Services_JSON();
t('Services_JSON',$sj->encode(['a'=>1])==='{"a":1}'&&$sj->decode('{"a":2}')->a===2&&(new Services_JSON(SERVICES_JSON_LOOSE_TYPE))->decode('{"a":2}')['a']===2);
$fc=(new WP_Feed_Cache())->create('x','abc','spc');
t('WP_Feed_Cache(_Transient)',$fc instanceof WP_Feed_Cache_Transient&&$fc->save('daten')&&$fc->load()==='daten'&&$fc->mtime()>0&&$fc->unlink()&&$fc->load()===false);
remove_all_filters('pre_http_request');
$sf=new WP_SimplePie_File('https://nicht-vorhanden.invalid/');
t('WP_SimplePie_File: Fehler ohne Netz',$sf->success===false||$sf->status_code===0);
t('WP_SimplePie_Sanitize_KSES',(new WP_SimplePie_Sanitize_KSES())->sanitize(' <b>x</b><script>1</script> ',2)==='<b>x</b>1');

/* ───────── HTTPS ───────── */
update_option('home','http://example.test');update_option('siteurl','http://example.test');
t('HTTPS: Website mit http',!wp_is_using_https()&&!wp_is_home_url_using_https()&&!wp_is_site_url_using_https());
t('HTTPS-Unterstützung unbekannt: false',wp_is_https_supported()===false);
update_option('https_detection_errors',[]);t('HTTPS-Unterstützung aus Option',wp_is_https_supported()===true);
update_option('https_detection_errors',['https_request_failed'=>['x']]);t('HTTPS-Unterstützung: Fehler',wp_is_https_supported()===false);
add_filter('pre_wp_get_https_detection_errors',fn($p)=>new WP_Error('abc','x'));
t('wp_get_https_detection_errors: Filter',wp_get_https_detection_errors()->get_error_code()==='abc');remove_all_filters('pre_wp_get_https_detection_errors');
t('wp_is_local_html_output',wp_is_local_html_output('<link rel="https://api.w.org/" href="http://example.test/wp-json/">')===true&&wp_is_local_html_output('<link rel="https://api.w.org/" href="http://anders.example/wp-json/">')===false&&wp_is_local_html_output('<p>x</p>')===null);
wp_update_https_migration_required('http://example.test','https://example.test');
t('https_migration_required (frische Website: 0)',get_option('https_migration_required')==='0');
wp_insert_post(['post_title'=>'Hallo','post_status'=>'publish','post_content'=>'x']);
wp_update_https_migration_required('http://example.test','https://example.test');
t('https_migration_required (mit Inhalt: 1)',get_option('https_migration_required')==='1');
t('wp_update_urls_to_https',wp_update_urls_to_https()===true&&get_option('home')==='https://example.test'&&get_option('siteurl')==='https://example.test'&&wp_update_urls_to_https()===true);
add_filter('home_url',fn($u)=>preg_replace('~^http://~','https://',$u));add_filter('site_url',fn($u)=>preg_replace('~^http://~','https://',$u));
t('wp_is_using_https über home_url/site_url',wp_is_home_url_using_https()&&wp_is_site_url_using_https()&&wp_is_using_https());
remove_all_filters('home_url');remove_all_filters('site_url');t('wp_is_using_https zurück auf http',!wp_is_using_https());
t('wp_should_replace_insecure_home_url',wp_should_replace_insecure_home_url()===false);

/* ───────── Schriften ───────── */
$fd=wp_get_font_dir();
t('wp_get_font_dir',str_ends_with($fd['path'],'/fonts')&&str_ends_with($fd['url'],'/fonts')&&$fd['error']===false);
$fd2=wp_font_dir(true);t('wp_font_dir legt Ordner an',is_dir($fd2['path']));
t('_wp_filter_font_directory',_wp_filter_font_directory(['basedir'=>'/b','baseurl'=>'http://x/u'])['path']==='/b/fonts');
$css=out(fn()=>wp_print_font_faces(['Mein Font'=>[['font-family'=>'Mein Font','font-weight'=>'700','src'=>['https://x.example/a.woff2?v=1']]]]));
t('wp_print_font_faces',str_contains($css,'@font-face')&&str_contains($css,'font-family:"Mein Font"')&&str_contains($css,'format("woff2")')&&str_contains($css,'font-weight:700')&&out(fn()=>wp_print_font_faces([]))==='');
$col=wp_register_font_collection('Meine Schriften',['name'=>'M','font_families'=>[['x'=>1]]]);
t('wp_register_font_collection',$col instanceof WP_Font_Collection&&$col->slug==='meine-schriften'&&$col->get_data()['font_families']===[['x'=>1]]);
t('Schriftensammlung doppelt/leer',wp_register_font_collection('meine-schriften',[])->get_error_code()==='font_collection_already_registered'&&wp_register_font_collection('',[])->get_error_code()==='font_collection_missing_slug');
t('wp_unregister_font_collection',wp_unregister_font_collection('meine-schriften')&&!wp_unregister_font_collection('meine-schriften'));
_wp_register_default_font_collections();t('Standard-Schriftensammlung',isset($GLOBALS['rrw_font_collections']['google-fonts']));
$fam=wp_insert_post(['post_type'=>'wp_font_family','post_title'=>'Fam','post_status'=>'publish','post_content'=>'{}']);
$face=wp_insert_post(['post_type'=>'wp_font_face','post_title'=>'Face','post_status'=>'publish','post_parent'=>$fam,'post_content'=>'{}']);
$ffile=wp_get_font_dir()['path'].'/test-face.woff2';file_put_contents($ffile,'x');add_post_meta($face,'_wp_font_face_file','test-face.woff2');
_wp_before_delete_font_face($face,get_post($face));t('_wp_before_delete_font_face löscht Datei',!is_file($ffile));
if($fam&&$face){ _wp_after_delete_font_family($fam,get_post($fam));t('_wp_after_delete_font_family löscht Schriftschnitte',get_post($face)===null||!get_post($face)); }else t('Schriftbeiträge angelegt',false);

/* ───────── Konstanten, UTF-8, Dateien ───────── */
wp_initial_constants();wp_plugin_directory_constants();wp_cookie_constants();wp_ssl_constants();wp_functionality_constants();wp_templating_constants();
t('Konstanten',defined('WP_CONTENT_URL')&&defined('WPMU_PLUGIN_URL')&&defined('USER_COOKIE')&&defined('COOKIEPATH')&&defined('TEMPLATEPATH')&&defined('WP_CRON_LOCK_TIMEOUT')&&defined('RECOVERY_MODE_COOKIE'));
t('UTF-8-Hilfen',_is_utf8_charset('UTF-8')&&_is_utf8_charset('utf8')&&!_is_utf8_charset('latin1')&&_mb_substr('äöüß',1,2)==='öü'&&_mb_strlen('äöüß')===4&&_wp_can_use_pcre_u()===true);
file_put_contents($tmp.'/z.txt',"a\nb\n");file_put_contents($tmp.'/z.gz',gzencode("g1\ng2\n"));
t('get_file: Text/gz/fehlt',get_file($tmp.'/z.txt')===["a\n","b\n"]&&get_file($tmp.'/z.gz')===["g1\n","g2\n"]&&get_file($tmp.'/nix.txt')===false);
t('read_big_endian',read_big_endian("\x01\x02",2)===258&&read_big_endian("\x00\x00\x01\x00",4)===256&&read_big_endian("\x01",2)===false&&read_big_endian("\x01",0)===false);
$h=fopen('php://memory','w+');fwrite($h,'abcdef');rewind($h);
t('read/skip',read($h,2)==='ab'&&skip($h,2)&&read($h,2)==='ef'&&read($h,1)===false);

/* ───────── AVIF-Leser ───────── */
$box=fn(string $type,string $body)=>pack('N',8+strlen($body)).$type.$body;
$avif=$box('ftyp','avif'."\0\0\0\0".'avif').$box('meta',"\0\0\0\0".$box('pitm',"\0\0\0\0\0\x01").$box('iprp',$box('ipco',$box('ispe',"\0\0\0\0".pack('NN',640,480)).$box('pixi',"\0\0\0\0\x03\x08\x08\x08"))));
file_put_contents($tmp.'/t.avif',$avif);$ah=fopen($tmp.'/t.avif','rb');$p=new Parser($ah);
$st=$p->parse_file();
t('AVIF Parser',$st==='ok'&&$p->features->primary_item_features->width===640&&$p->features->primary_item_features->height===480&&$p->features->primary_item_features->num_channels===3&&$p->features->has_primary_item);
fclose($ah);$bh=fopen('php://memory','w+');fwrite($bh,'kein avif');rewind($bh);t('AVIF Parser: ungültig',(new Parser($bh))->parse_file()!=='ok');
$bh2=fopen('php://memory','w+');fwrite($bh2,$box('free','abcd'));rewind($bh2);$bx=new Box($bh2,100);t('Box liest Kopf',$bx->size===12&&$bx->type==='free'&&$bx->content_size===4);
t('AVIF-Hilfsklassen',(new Dim_Prop())->width===0&&(new Chan_Prop())->num_channels===0&&(new Prop())->type===''&&(new Tile())->width===0&&(new Features())->has_alpha===false);

/* ───────── Speculation Rules ───────── */
update_option('permalink_structure','');
t('Speculation: ohne schöne Links null',wp_get_speculation_rules_configuration()===null&&wp_get_speculation_rules()===null&&out(fn()=>wp_print_speculation_rules())==='');
update_option('permalink_structure','/%postname%/');
$cfg=wp_get_speculation_rules_configuration();
t('Speculation: Konfiguration',$cfg===['mode'=>'auto','eagerness'=>'moderate']);
add_filter('wp_speculation_rules_configuration',fn($c)=>['mode'=>'prerender','eagerness'=>'unsinn']);t('Speculation: Filter prüft Werte',wp_get_speculation_rules_configuration()==['mode'=>'prerender','eagerness'=>'moderate']);remove_all_filters('wp_speculation_rules_configuration');
$sr=wp_get_speculation_rules();$sj=$sr->jsonSerialize();
t('Speculation: Regeln',isset($sj['prefetch'][0]['where']['and'])&&$sj['prefetch'][0]['eagerness']==='moderate');
$pr=out(fn()=>wp_print_speculation_rules());t('Speculation: Ausgabe',str_contains($pr,'<script type="speculationrules">')&&str_contains($pr,'"prefetch"'));
$r=new WP_Speculation_Rules();
t('WP_Speculation_Rules: add_rule',$r->add_rule('prerender','a-b',['urls'=>['/x']])&&!$r->add_rule('prerender','a-b',['urls'=>['/y']])&&!$r->add_rule('nix','abc',[])&&!$r->add_rule('prefetch','X',[])&&!$r->add_rule('prefetch','okay',['eagerness'=>'zz'])&&$r->jsonSerialize()['prerender'][0]['source']==='list');
$pf=new WP_URL_Pattern_Prefixer(['home'=>'/blog/','site'=>'/']);
t('WP_URL_Pattern_Prefixer',$pf->prefix_path_pattern('/*')==='/blog/*'&&$pf->prefix_path_pattern('/wp-*.php','site')==='/wp-*.php'&&WP_URL_Pattern_Prefixer::escape_pattern_string('a(b)?')==='a\\(b\\)\\?');

/* ───────── Sitemaps ───────── */
t('Sitemap: Index-Adresse',get_sitemap_url('index')===home_url('/wp-sitemap.xml'));
t('Sitemap: Beitrags-Adresse',get_sitemap_url('posts','post',1)===home_url('/wp-sitemap-posts-post-1.xml')&&get_sitemap_url('posts','post',0)===home_url('/wp-sitemap-posts-post-1.xml'));
t('Sitemap: unbekannt',get_sitemap_url('nix')===false&&get_sitemap_url('posts','gibts-nicht')===false);
t('Sitemap-Anbieter',isset(wp_get_sitemap_providers()['posts'],wp_get_sitemap_providers()['users'])&&wp_sitemaps_get_server() instanceof WP_Sitemaps);
add_filter('wp_sitemaps_max_urls',fn($m)=>5);t('wp_sitemaps_get_max_urls: Filter',wp_sitemaps_get_max_urls('post')===5);remove_all_filters('wp_sitemaps_max_urls');t('wp_sitemaps_get_max_urls',wp_sitemaps_get_max_urls('post')===2000);
$pl=wp_get_sitemap_providers()['posts'];$ul=$pl->get_url_list(1,'post');
t('Sitemap: URL-Liste der Beiträge',count($ul)>=1&&str_contains($ul[0]['loc'],'example.test')&&$pl->get_max_num_pages('post')===1);
class T_Prov extends WP_Sitemaps_Provider { protected $name='eigen';protected $object_type='x'; public function get_url_list($p,$s=''){ return [['loc'=>'https://x.example/']]; } public function get_max_num_pages($s=''){ return 1; } public function get_object_subtypes(){ return []; } }
t('wp_register_sitemap_provider',wp_register_sitemap_provider('eigen',new T_Prov())&&get_sitemap_url('eigen','',2)===home_url('/wp-sitemap-eigen-2.xml'));

/* ───────── Pausierte Erweiterungen, schwere Fehler, Wiederherstellungsmodus ───────── */
$ps=wp_paused_plugins();
t('Pausierte Plugins',$ps===wp_paused_plugins()&&$ps->set('mein-plugin/m.php',['type'=>1,'message'=>'x'])&&$ps->get('mein-plugin/m.php')['message']==='x'&&isset($ps->get_all()['mein-plugin'])&&$ps->delete('mein-plugin/m.php')&&$ps->get('mein-plugin/m.php')===null);
$pt=wp_paused_themes();$pt->set('thema',['message'=>'t']);$ps->set('einzel.php',['message'=>'e']);
t('Pausierte Themes und Einzeldatei-Plugins',$pt->get('thema')['message']==='t'&&isset($ps->get_all()['einzel.php'])&&$pt->delete_all()&&$pt->get_all()===[]&&$ps->delete_all());
t('wp_get_extension_error_description',str_contains(wp_get_extension_error_description(['type'=>E_ERROR,'line'=>7,'file'=>'/a.php','message'=>'Boom']),'E_ERROR')&&str_contains(wp_get_extension_error_description(['type'=>E_ERROR,'line'=>7,'file'=>'/a.php','message'=>'Boom']),'Boom'));
t('wp_is_fatal_error_handler_enabled',wp_is_fatal_error_handler_enabled()===true);
add_filter('wp_fatal_error_handler_enabled','__return_false');t('Fehlerbehandlung abschaltbar',!wp_is_fatal_error_handler_enabled());remove_all_filters('wp_fatal_error_handler_enabled');
$fh=new WP_Fatal_Error_Handler();$rf=new ReflectionMethod($fh,'detect_error');
t('WP_Fatal_Error_Handler: kein Fehler',$rf->invoke($fh)===null);
$rt=new ReflectionMethod($fh,'display_default_error_template');$died=false;try{ $rt->invoke($fh,['type'=>E_ERROR,'message'=>'x'],false); }catch(RRW_WP_Die $d){ $died=true; }
t('WP_Fatal_Error_Handler: Fehlerseite per wp_die',$died);
wp_register_fatal_error_handler();t('wp_register_fatal_error_handler ist wiederholbar',true);
$ks=new WP_Recovery_Mode_Key_Service();$tok=$ks->generate_recovery_mode_token();$key=$ks->generate_and_store_recovery_mode_key($tok);
t('Wiederherstellungsschlüssel: gültig',$ks->validate_recovery_mode_key($tok,$key,3600)===true);
t('Wiederherstellungsschlüssel: nur einmal',$ks->validate_recovery_mode_key($tok,$key,3600)->get_error_code()==='token_not_found');
$tok=$ks->generate_recovery_mode_token();$key=$ks->generate_and_store_recovery_mode_key($tok);
t('Wiederherstellungsschlüssel: falscher Schlüssel',$ks->validate_recovery_mode_key($tok,'falsch',3600)->get_error_code()==='hash_mismatch');
$tok=$ks->generate_recovery_mode_token();$key=$ks->generate_and_store_recovery_mode_key($tok);
t('Wiederherstellungsschlüssel: abgelaufen',$ks->validate_recovery_mode_key($tok,$key,-10)->get_error_code()==='key_expired');
$tok=$ks->generate_recovery_mode_token();$ks->generate_and_store_recovery_mode_key($tok);$ks->clean_expired_keys(-1);t('clean_expired_keys',get_option('recovery_keys')===[]);
$cs=new WP_Recovery_Mode_Cookie_Service();
t('Wiederherstellungs-Cookie: nicht gesetzt',!$cs->is_cookie_set()&&$cs->validate_cookie()->get_error_code()==='no_cookie');
$cv=@$cs->set_cookie();$_COOKIE[RECOVERY_MODE_COOKIE]=$cv;
t('Wiederherstellungs-Cookie: gültig',$cs->is_cookie_set()&&$cs->validate_cookie()===true&&is_string($cs->get_session_id_from_cookie()));
$_COOKIE[RECOVERY_MODE_COOKIE]=substr($cv,0,-3).'abc';t('Wiederherstellungs-Cookie: manipuliert',is_wp_error($cs->validate_cookie()));
$cs->clear_cookie();t('Wiederherstellungs-Cookie löschen',!$cs->is_cookie_set());
$ls=new WP_Recovery_Mode_Link_Service($cs,$ks);$lu=$ls->generate_url();
t('Wiederherstellungs-Link',str_contains($lu,'action=enter_recovery_mode')&&str_contains($lu,'rm_token=')&&str_contains($lu,'rm_key='));
$mails=[];add_filter('pre_wp_mail',function($pre,$atts) use(&$mails){ $mails[]=$atts;return true; },10,2);delete_option('recovery_mode_email_last_sent');
$es=new WP_Recovery_Mode_Email_Service($ls);
t('Wiederherstellungs-E-Mail: Versand',$es->maybe_send_recovery_mode_email(3600,['type'=>E_ERROR,'message'=>'Boom','file'=>'/p.php','line'=>3],['slug'=>'p','type'=>'plugin'])===true&&count($mails)===1&&str_contains($mails[0]['message'],'enter_recovery_mode'));
t('Wiederherstellungs-E-Mail: Begrenzung',$es->maybe_send_recovery_mode_email(3600,['type'=>E_ERROR],['slug'=>'p','type'=>'plugin'])->get_error_code()==='email_sent_already'&&count($mails)===1);
remove_all_filters('pre_wp_mail');
$rm=wp_recovery_mode();$rg=new ReflectionMethod($rm,'get_extension_for_error');
t('WP_Recovery_Mode: Erweiterung zum Fehler',$rg->invoke($rm,['file'=>WP_PLUGIN_DIR.'/abc/x.php'])==['slug'=>'abc','type'=>'plugin']&&$rg->invoke($rm,['file'=>WP_PLUGIN_DIR.'/einzel.php'])==['slug'=>'einzel.php','type'=>'plugin']&&$rg->invoke($rm,['file'=>WP_CONTENT_DIR.'/themes/th/functions.php'])==['slug'=>'th','type'=>'theme']&&$rg->invoke($rm,['file'=>'/fremd/x.php'])===false);
t('WP_Recovery_Mode: Grundzustand',$rm===wp_recovery_mode()&&!$rm->is_active()&&!$rm->is_initialized()&&$rm->exit_recovery_mode()===false);
$rm->initialize();t('WP_Recovery_Mode: initialisiert',$rm->is_initialized()&&!$rm->is_active());
$mails=[];add_filter('pre_wp_mail',function($pre,$atts) use(&$mails){ $mails[]=$atts;return true; },10,2);delete_option('recovery_mode_email_last_sent');
t('WP_Recovery_Mode: Fehler pausiert Plugin und meldet',$rm->handle_error(['type'=>E_ERROR,'message'=>'Kaputt','file'=>WP_PLUGIN_DIR.'/kaputt/k.php','line'=>1])===true&&wp_paused_plugins()->get('kaputt/k.php')['message']==='Kaputt'&&count($mails)===1);
t('WP_Recovery_Mode: fremder Fehler wird nicht behandelt',$rm->handle_error(['type'=>E_ERROR,'message'=>'x','file'=>'/fremd.php'])===false);
remove_all_filters('pre_wp_mail');wp_paused_plugins()->delete_all();

/* ───────── Dateisystem-Hüllen, FTP, PclZip ───────── */
$ssh=new WP_Filesystem_SSH2(['hostname'=>'h']);
t('WP_Filesystem_SSH2/FTP: nicht unterstützt',$ssh->connect()===false&&$ssh->errors->has_errors()&&$ssh->get_contents('/x')===false&&(new WP_Filesystem_FTPext())->put_contents('/x','y')===false&&(new WP_Filesystem_ftpsockets())->exists('/x')===false&&$ssh instanceof WP_Filesystem_Base);
$fp=new ftp_pure();
t('ftp_base/pure/sockets',$fp->SetType(2)&&!$fp->SetType(99)&&$fp->connect('h')===false&&$fp->PopError()['code']==='connect'&&$fp->put('a','b')===false&&(new ftp_sockets())->login('u','p')===false&&$fp->quit());
$zdir=$tmp.'/zip';mkdir($zdir);file_put_contents($zdir.'/a.txt','Alpha');mkdir($zdir.'/sub');file_put_contents($zdir.'/sub/b.txt','Beta');
$pz=new PclZip($tmp.'/t.zip');
$cr=$pz->create($zdir,PCLZIP_OPT_REMOVE_PATH,$tmp);
t('PclZip::create',is_array($cr)&&count($cr)>=3&&is_file($tmp.'/t.zip')&&$pz->errorCode()===PCLZIP_ERR_NO_ERROR);
$lc=$pz->listContent();$names=array_column($lc,'stored_filename');
t('PclZip::listContent',in_array('zip/a.txt',$names,true)&&in_array('zip/sub/b.txt',$names,true)&&$lc[0]['status']==='ok'&&$pz->properties()['nb']===count($lc));
$ex=$tmp.'/ex';$er=$pz->extract(PCLZIP_OPT_PATH,$ex);
t('PclZip::extract',is_array($er)&&file_get_contents($ex.'/zip/a.txt')==='Alpha'&&file_get_contents($ex.'/zip/sub/b.txt')==='Beta');
$er=$pz->extract(PCLZIP_OPT_EXTRACT_AS_STRING,PCLZIP_OPT_BY_NAME,'zip/a.txt');
t('PclZip::extract als Text / nach Namen',is_array($er)&&count($er)===1&&$er[0]['content']==='Alpha');
$er=$pz->extract(PCLZIP_OPT_PATH,$tmp.'/ex2',PCLZIP_OPT_REMOVE_PATH,'zip');
t('PclZip::extract mit REMOVE_PATH',is_file($tmp.'/ex2/a.txt')&&is_file($tmp.'/ex2/sub/b.txt'));
$zz=new ZipArchive();$zz->open($tmp.'/evil.zip',ZipArchive::CREATE);$zz->addFromString('../evil.txt','x');$zz->addFromString('ok.txt','y');$zz->close();
$evil=(new PclZip($tmp.'/evil.zip'))->extract(PCLZIP_OPT_PATH,$tmp.'/ex3');
t('PclZip: Pfade außerhalb des Ziels werden nicht geschrieben',!is_file($tmp.'/evil.txt')&&!is_file($tmp.'/ex3/../evil.txt')&&is_file($tmp.'/ex3/ok.txt')&&in_array('filtered',array_column($evil,'status'),true));
$pa=new PclZip($tmp.'/t.zip');$ad=$pa->add([[PCLZIP_ATT_FILE_NAME=>'neu.txt',PCLZIP_ATT_FILE_CONTENT=>'Neu']]);
t('PclZip::add (Inhalt)',is_array($ad)&&in_array('neu.txt',array_column($pa->listContent(),'filename'),true));
$pd=$pa->delete(PCLZIP_OPT_BY_NAME,'neu.txt');t('PclZip::delete',is_array($pd)&&!in_array('neu.txt',array_column($pd,'filename'),true));
$bad=new PclZip($tmp.'/gibts.zip');t('PclZip: Fehler',$bad->listContent()===0&&$bad->errorCode()===PCLZIP_ERR_MISSING_FILE&&str_contains($bad->errorInfo(),'Archiv fehlt')&&$bad->errorName()==='PCLZIP_ERR_MISSING_FILE');

/* ───────── Upgrader-Skins ───────── */
$ps=new Plugin_Upgrader_Skin(['plugin'=>'x/x.php','url'=>'u']);
t('Plugin_Upgrader_Skin',$ps->plugin==='x/x.php'&&$ps->options['url']==='u'&&$ps instanceof WP_Upgrader_Skin);
$up=new Plugin_Upgrader($ps);$ps->set_upgrader($up);$up->strings['hallo']='Hallo %s';$ps->feedback('hallo','Welt');$ps->feedback('einfach');$ps->error('Fehler 1');$ps->error(new WP_Error('e','Fehler 2'));
t('Skin sammelt Meldungen',$ps->get_upgrade_messages()===['Hallo Welt','einfach','Fehler 1','Fehler 2']);
$ts=new Theme_Upgrader_Skin(['theme'=>'tt']);t('Theme_Upgrader_Skin',$ts->theme==='tt'&&$ts->options['title']!=='');
$bp=new Bulk_Plugin_Upgrader_Skin();$bt=new Bulk_Theme_Upgrader_Skin();$bp->before();$bt->before();
t('Bulk-Skins',$bp->in_loop&&$bt->in_loop&&($bp->after()||!$bp->in_loop)&&$bp instanceof WP_Upgrader_Skin);
$lp=new Language_Pack_Upgrader_Skin(['language_update'=>(object)['language'=>'de_DE']]);t('Language_Pack_Upgrader_Skin',$lp->language_update->language==='de_DE'&&$lp->options['title']!=='');
$_FILES=[];$_GET=[];$thrown=false;try{ new File_Upload_Upgrader('pluginzip','package'); }catch(RRW_WP_Die $d){ $thrown=true; }
t('File_Upload_Upgrader ohne Datei: wp_die',$thrown);
file_put_contents(wp_upload_dir(null,false)['basedir'].'/pak.zip','x');@mkdir(wp_upload_dir(null,false)['basedir'].'/upgrade',0775,true);file_put_contents(wp_upload_dir(null,false)['basedir'].'/upgrade/pak.zip','x');
$_GET['package']='pak.zip';$fu=new File_Upload_Upgrader('pluginzip','package');$_GET=[];
t('File_Upload_Upgrader mit Paketname',basename($fu->package)==='pak.zip'&&$fu->cleanup()&&!is_file($fu->package));

/* ───────── Listen-Tabellen ───────── */
$p1=wp_insert_post(['post_title'=>'Erster Beitrag','post_status'=>'publish','post_content'=>'a']);$p2=wp_insert_post(['post_title'=>'Zweiter Entwurf','post_status'=>'draft','post_content'=>'b']);
$cm=wp_insert_comment(['comment_post_ID'=>$p1,'comment_author'=>'Gast','comment_author_email'=>'g@example.test','comment_content'=>'Schön','comment_approved'=>1]);
$cm2=wp_insert_comment(['comment_post_ID'=>$p1,'comment_author'=>'Spam','comment_content'=>'Wartet','comment_approved'=>0]);
$uid=wp_create_user('lena','geheim-123','lena@example.test');
$_REQUEST=[];$_GET=[];
$lt=new WP_Posts_List_Table();$lt->prepare_items();
t('WP_Posts_List_Table: Einträge',$lt instanceof WP_List_Table&&count($lt->items)>=2&&$lt->get_pagination_arg('total_items')>=2);
$ids=array_map(fn($x)=>$x->ID,$lt->items);
t('WP_Posts_List_Table: Spalten',isset($lt->get_columns()['title'],$lt->get_columns()['date'])&&in_array($p1,$ids,true)&&isset($lt->get_sortable_columns()['title']));
$html=out(fn()=>$lt->column_title(get_post($p1)));t('WP_Posts_List_Table: column_title',str_contains($html,'Erster Beitrag')&&str_contains($html,'<strong>'));
$html=out(fn()=>$lt->column_title(get_post($p2)));t('WP_Posts_List_Table: Status im Titel',str_contains($html,'post-state'));
$_REQUEST['post_status']='draft';$lt=new WP_Posts_List_Table();$lt->prepare_items();
t('WP_Posts_List_Table: Statusfilter',count($lt->items)===1&&$lt->items[0]->ID===$p2);
$_REQUEST=['s'=>'Erster'];$lt=new WP_Posts_List_Table();$lt->prepare_items();t('WP_Posts_List_Table: Suche',count($lt->items)===1&&$lt->items[0]->ID===$p1);
$_REQUEST=[];$lt=new WP_Posts_List_Table();$lt->prepare_items();
$dis=out(fn()=>$lt->display());t('WP_Posts_List_Table: display()',str_contains($dis,'<table')&&str_contains($dis,'Erster Beitrag'));
$v=(new ReflectionMethod($lt,'get_views'))->invoke($lt);t('WP_Posts_List_Table: Ansichten',isset($v['all'],$v['publish'],$v['draft']));
$ct=new WP_Comments_List_Table();$ct->prepare_items();
t('WP_Comments_List_Table',count($ct->items)===2&&isset($ct->get_columns()['comment']));
$_REQUEST=['comment_status'=>'moderated'];$ct=new WP_Comments_List_Table();$ct->prepare_items();t('WP_Comments_List_Table: ausstehend',count($ct->items)===1&&$ct->items[0]->comment_author==='Spam');
$_REQUEST=[];$ct=new WP_Comments_List_Table();$ct->prepare_items();
t('WP_Comments_List_Table: Spalten',str_contains(out(fn()=>$ct->column_author(get_comment($cm))),'Gast')&&str_contains(out(fn()=>$ct->column_comment(get_comment($cm))),'Schön')&&str_contains(out(fn()=>$ct->column_response(get_comment($cm))),'Erster Beitrag'));
$pc=new WP_Post_Comments_List_Table();t('WP_Post_Comments_List_Table',array_keys($pc->get_columns())===['author','comment']&&$pc instanceof WP_Comments_List_Table);
$ut=new WP_Users_List_Table();$ut->prepare_items();
t('WP_Users_List_Table',count($ut->items)>=1&&isset($ut->get_columns()['username'])&&str_contains(out(fn()=>$ut->column_email(get_userdata($uid))),'lena@example.test')&&str_contains(out(fn()=>$ut->column_username(get_userdata($uid))),'lena'));
$_REQUEST=['s'=>'lena'];$ut=new WP_Users_List_Table();$ut->prepare_items();t('WP_Users_List_Table: Suche',count($ut->items)===1);
$_REQUEST=[];
$lk=new WP_Links_List_Table();$lk->prepare_items();
t('WP_Links_List_Table',count($lk->items)===3&&str_contains(out(fn()=>$lk->column_url($lk->items[0])),'a.example')&&str_contains(out(fn()=>$lk->display()),'Alpha 2'));
$mt=new WP_Media_List_Table();$mt->prepare_items();t('WP_Media_List_Table: leer',$mt->items===[]&&isset($mt->get_columns()['parent']));
file_put_contents($tmp.'/wp-content/plugins/demo.php',"<?php\n/* Plugin Name: Demo Plugin\nVersion: 1.2\nDescription: Beschreibung */");
$pl=new WP_Plugins_List_Table();$pl->prepare_items();$ph=out(fn()=>$pl->display_rows());
t('WP_Plugins_List_Table',isset($pl->items['demo.php'])&&str_contains($ph,'Demo Plugin')&&str_contains($ph,'class="inactive"'));
$_REQUEST=['plugin_status'=>'active'];$pl=new WP_Plugins_List_Table();$pl->prepare_items();t('WP_Plugins_List_Table: aktiv-Filter',$pl->items===[]);$_REQUEST=[];
$th=new WP_Themes_List_Table();$th->prepare_items();t('WP_Themes_List_Table',is_array($th->items)&&isset($th->get_columns()['name']));
$pi=new WP_Plugin_Install_List_Table();$pi->prepare_items();$ti=new WP_Theme_Install_List_Table();$ti->prepare_items();
t('Install-Tabellen: Verzeichnis nicht angebunden',$pi->items===[]&&$pi->error instanceof WP_Error&&$ti->error instanceof WP_Error&&str_contains(out(fn()=>$ti->no_items()),'CMS-Installer'));
as_user(1,'admin','administrator');$GLOBALS['user_id']=get_current_user_id();
$ap=WP_Application_Passwords::create_new_application_password($GLOBALS['user_id'],['name'=>'Test-App']);
$apt=new WP_Application_Passwords_List_Table();$apt->prepare_items();
t('WP_Application_Passwords_List_Table',is_array($ap)&&count($apt->items)===1&&str_contains(out(fn()=>$apt->column_name($apt->items[0])),'Test-App')&&str_contains(out(fn()=>$apt->column_revoke($apt->items[0])),'data-uuid'));
$rq=wp_create_user_request('lena@example.test','export_personal_data');
$ex=new WP_Privacy_Data_Export_Requests_List_Table();$ex->prepare_items();
t('WP_Privacy_Data_Export_Requests_List_Table',count($ex->items)===1&&$ex instanceof WP_Privacy_Requests_Table&&str_contains(out(fn()=>$ex->column_next_steps($ex->items[0])),'Bestätigung')&&str_contains(out(fn()=>$ex->column_email($ex->items[0])),'lena@example.test'));
$rm=new WP_Privacy_Data_Removal_Requests_List_Table();$rm->prepare_items();t('WP_Privacy_Data_Removal_Requests_List_Table: nur eigene Art',$rm->items===[]);
$cp=new _WP_List_Table_Compat('edit-post',['a'=>'Spalte A','b'=>'Spalte B']);
t('_WP_List_Table_Compat',$cp->get_columns()===['a'=>'Spalte A','b'=>'Spalte B']&&$cp->get_column_info()[0]===$cp->get_columns()&&$cp->get_column_info()[3]==='a');

/* ───────── Datenschutz, Website-Icon, Verwaltungsklassen ───────── */
WP_Privacy_Policy_Content::add('Mein Plugin','<p>Wir speichern X.</p>');WP_Privacy_Policy_Content::add('Mein Plugin','<p>Wir speichern X.</p>');WP_Privacy_Policy_Content::add('','x');
$sug=WP_Privacy_Policy_Content::get_suggested_policy_text();$nm=array_column($sug,'plugin_name');
t('WP_Privacy_Policy_Content: Vorschlag',$nm[0]==='Website'&&count(array_keys($nm,'Mein Plugin'))===1&&count($nm)===2);
t('WP_Privacy_Policy_Content: Standardtext',str_contains(WP_Privacy_Policy_Content::get_default_content(),'<!-- wp:heading -->')&&!str_contains(WP_Privacy_Policy_Content::get_default_content(false,false),'wp:heading')&&WP_Privacy_Policy_Content::get_default_content(true)!=='');
$pp=wp_insert_post(['post_title'=>'Datenschutz','post_type'=>'page','post_status'=>'publish','post_content'=>'x']);update_option('wp_page_for_privacy_policy',$pp);
t('Datenschutz: Änderungserkennung',WP_Privacy_Policy_Content::text_change_check()===false);
WP_Privacy_Policy_Content::_policy_page_updated($pp);t('Datenschutz: nach Speichern unverändert',WP_Privacy_Policy_Content::text_change_check()===false);
WP_Privacy_Policy_Content::add('Neues Plugin','<p>Neu</p>');t('Datenschutz: Vorschlag geändert',WP_Privacy_Policy_Content::text_change_check()===true);
t('Datenschutz: Hinweis und Leitfaden',str_contains(out(fn()=>WP_Privacy_Policy_Content::policy_text_changed_notice()),'policy-text-updated')&&str_contains(out(fn()=>WP_Privacy_Policy_Content::privacy_policy_guide()),'Neues Plugin'));
$si=new WP_Site_Icon();
t('WP_Site_Icon: Größen',array_keys($si->additional_sizes())===['site_icon-512','site_icon-192','site_icon-180','site_icon-32']&&in_array('site_icon-32',$si->intermediate_image_sizes(['thumbnail']),true));
update_option('site_icon',77);
t('WP_Site_Icon: Kontext und Löschen',$si->get_post_metadata(null,77,'_wp_attachment_context',true)==='site-icon'&&$si->get_post_metadata('x',78,'_wp_attachment_context',true)==='x'&&($si->delete_attachment_data(77)||true)&&get_option('site_icon')===false);
t('WP_Site_Icon: Anhangsobjekt',$si->create_attachment_object('/x/ico.png',0)['context']==='site-icon');
t('Oberflächen-Klassen (No-ops)',WP_Internal_Pointers::enqueue_scripts('index.php')===null&&(new Custom_Background('cb'))->init()===null&&(new Custom_Image_Header('ch'))->admin_page()===null&&is_array((new Custom_Image_Header('ch'))->get_default_header_images()));
t('_WP_Editors geladen',class_exists('_WP_Editors')&&str_contains(out(fn()=>_WP_Editors::editor('Hallo','eid',['textarea_name'=>'tn'])),'name="tn"'));

/* ───────── Site Health, Debug-Daten, Community-Ereignisse ───────── */
$sh=WP_Site_Health::get_instance();$tests=$sh->get_tests();
t('WP_Site_Health: Tests',$sh===WP_Site_Health::get_instance()&&isset($tests['direct']['php_version'],$tests['async']['dotorg_communication']));
$res=[];foreach($tests['direct'] as $k=>$tt){ $res[$k]=$sh->perform_test($tt['test']); }
$okfmt=true;foreach($res as $k=>$r)if(!isset($r['label'],$r['status'],$r['badge']['color'],$r['description'])||!in_array($r['status'],['good','recommended','critical'],true)||$r['test']!==$k)$okfmt=false;
t('WP_Site_Health: alle direkten Tests liefern gültige Ergebnisse',$okfmt&&count($res)===14);
t('WP_Site_Health: PHP-Version/HTTPS',$res['php_version']['status']==='good'&&$res['https_status']['status']==='recommended'&&$res['is_in_debug_mode']['status']==='good');
$cnt=$sh->wp_cron_scheduled_check();t('WP_Site_Health: Zeitplan-Prüfung',array_sum($cnt)===14&&json_decode((string)get_option('health-check-site-status-result'),true)===$cnt);
$sh->maybe_create_scheduled_event();t('WP_Site_Health: Ereignis geplant',(bool)wp_next_scheduled('wp_site_health_scheduled_check'));
$au=new WP_Site_Health_Auto_Updates();$aur=$au->run_tests();
t('WP_Site_Health_Auto_Updates',is_array($aur)&&count($aur)>=5&&isset($aur[0]['description'],$aur[0]['severity'],$aur[0]['test'])&&$au->test_accepts_minor_updates()['severity']==='pass');
$di=WP_Debug_Data::debug_data();
t('WP_Debug_Data: Bereiche',isset($di['wp-core']['fields']['version'],$di['wp-server'],$di['wp-database'],$di['wp-constants'],$di['wp-filesystem'])&&$di['wp-core']['fields']['version']['value']===wp_get_wp_version());
$fmt=WP_Debug_Data::format($di,'debug');t('WP_Debug_Data::format',str_contains($fmt,'### WordPress ###')&&str_contains($fmt,'Version: '.wp_get_wp_version())&&str_contains($fmt,'Startseiten-Adresse: ***'));
$sz=WP_Debug_Data::get_sizes();t('WP_Debug_Data: Größen',isset($sz['total_size']['raw'])&&$sz['total_size']['raw']>0&&is_int(WP_Debug_Data::get_database_size()));
$ce=new WP_Community_Events(1);
t('WP_Community_Events',$ce->get_events()['events']===[]&&(new ReflectionMethod($ce,'get_events_transient_key'))->invoke($ce,['description'=>'Berlin'])==='community-events-'.md5('Berlin')&&(new ReflectionMethod($ce,'get_events_transient_key'))->invoke($ce,[])===false);
$_SERVER['REMOTE_ADDR']='203.0.113.77';t('WP_Community_Events: Client-IP anonymisiert',(new ReflectionMethod($ce,'get_unsafe_client_ip'))->invoke($ce)==='203.0.113.0');

/* ───────── Kernklassen: WP_Hook, Objekt-Cache, Abhängigkeiten, WP, WP_Site ───────── */
$hk=new WP_Hook();$trace=[];
$hk->add_filter('t',function($v) use(&$trace){ $trace[]='p20';return $v.'B'; },20,1);
$hk->add_filter('t',function($v) use(&$trace){ $trace[]='p5';return $v.'A'; },5,1);
$hk->add_filter('t',function($v,$x) use(&$trace){ $trace[]='p5b';return $v.$x; },5,2);
t('WP_Hook::apply_filters: Reihenfolge',$hk->apply_filters('-',['-','!'])==='-A!B'&&$trace===['p5','p5b','p20']);
$cb='strtoupper';$hk->add_filter('t',$cb,30,1);
t('WP_Hook: has_filter/remove_filter',$hk->has_filter('t',$cb)===30&&$hk->has_filters()&&$hk->remove_filter('t',$cb,30)&&$hk->has_filter('t',$cb)===false&&!$hk->remove_filter('t',$cb,30));
$acts=[];$ha=new WP_Hook();$ha->add_filter('a',function($a,$b=null) use(&$acts){ $acts[]=[$a,$b]; },10,2);$ha->do_action(['eins','zwei']);
t('WP_Hook::do_action',$acts===[['eins','zwei']]);
$hn=new WP_Hook();$seen=[];
$hn->add_filter('n',function($v) use(&$seen,$hn){ $seen[]='a'; $hn->add_filter('n',function($v2) use(&$seen){ $seen[]='spät';return $v2; },11,1);return $v; },10,1);
$hn->apply_filters('x',['x']);t('WP_Hook: während des Durchlaufs hinzugefügte Callbacks laufen',$seen===['a','spät']);
$pre=WP_Hook::build_preinitialized_hooks(['x'=>[10=>['id1'=>['function'=>'strrev','accepted_args'=>1]]]]);
t('WP_Hook::build_preinitialized_hooks',$pre['x'] instanceof WP_Hook&&$pre['x']->apply_filters('abc',['abc'])==='cba'&&isset($pre['x'][10])&&iterator_count($pre['x'])===1);
$hk2=new WP_Hook();$hk2->add_filter('c',fn()=>1,10,0);t('WP_Hook: Iterator/ArrayAccess',iterator_to_array($hk2)!==[]&&isset($hk2[10])&&!isset($hk2[99]));
$oc=new WP_Object_Cache();
t('WP_Object_Cache: add/get/delete',$oc->add('k','v','g')&&!$oc->add('k','x','g')&&$oc->get('k','g')==='v'&&$oc->delete('k','g')&&$oc->get('k','g',false,$found)===false&&$found===false&&!$oc->delete('k','g'));
$oc->set('n',5,'g');t('WP_Object_Cache: incr/decr/replace',$oc->incr('n',3,'g')===8&&$oc->decr('n',20,'g')===0&&$oc->replace('n',1,'g')&&!$oc->replace('nie',1,'g'));
$oc->set('o',new ArrayObject([1]),'g');$o1=$oc->get('o','g');$o1[]=2;t('WP_Object_Cache: Objekte werden kopiert',count($oc->get('o','g'))===1);
$oc->set_multiple(['a'=>1,'b'=>2],'m');t('WP_Object_Cache: Mehrfach/Gruppen leeren',$oc->get_multiple(['a','b'],'m')===['a'=>1,'b'=>2]&&$oc->flush_group('m')&&$oc->get('a','m')===false&&$oc->flush()&&$oc->get('n','g')===false&&$oc->cache_hits>0);
$dp=new WP_Dependencies();
$dp->add('a','a.js',['b']);$dp->add('b','b.js');$dp->add('c','c.js',['fehlt']);
t('WP_Dependencies: Registrierung',$dp->registered['a'] instanceof _WP_Dependency&&!$dp->add('a','x')&&$dp->query('b')->src==='b.js'&&$dp->query('zz')===false);
$dp->enqueue(['a','c']);t('WP_Dependencies: Warteschlange',$dp->queue===['a','c']&&$dp->query('a','enqueued'));
$dp->all_deps($dp->queue);t('WP_Dependencies: Auflösung',$dp->to_do===['b','a']);
t('WP_Dependencies::do_items',$dp->do_items()===['b','a']&&$dp->query('a','done')&&$dp->dequeue('c')===null&&$dp->queue===['a']);
$dp->add_data('a','extra','x');t('WP_Dependencies: Daten',$dp->get_data('a','extra')==='x'&&$dp->get_data('a','nix')===false&&(function() use($dp){ $dp->remove('a');return !isset($dp->registered['a']); })());
$wpo=new WP();$wpo->add_query_var('meinvar');$_GET=['meinvar'=>'1','s'=>'suche','fremd'=>'x'];$wpo->parse_request();
t('WP::parse_request',$wpo->query_vars==['meinvar'=>'1','s'=>'suche']&&$wpo->build_query_string()==='s=suche&meinvar=1');
$wpo->set_query_var('p',5);$wpo->remove_query_var('meinvar');t('WP: set/remove_query_var',$wpo->query_vars['p']===5&&!in_array('meinvar',$wpo->public_query_vars,true));$_GET=[];
$s=WP_Site::get_instance(1);
t('WP_Site',$s instanceof WP_Site&&$s->id===1&&$s->network_id===1&&$s->domain==='example.test'&&$s->path==='/'&&$s->blogname===get_option('blogname')&&WP_Site::get_instance(2)===false&&$s->to_array()['blog_id']==='1'&&!isset($s->to_array()['details']));
$sq=new WP_Site_Query(['number'=>5]);$sl=$sq->get_sites();
t('WP_Site_Query',count($sl)===1&&$sl[0] instanceof WP_Site&&(new WP_Site_Query(['fields'=>'ids']))->get_sites()===[1]&&(new WP_Site_Query(['site__not_in'=>[1]]))->get_sites()===[]&&(new WP_Site_Query(['count'=>true]))->get_sites()===1&&(new WP_Site_Query(['domain'=>'anders.test']))->get_sites()===[]);

/* ───────── Listen-/Token-Hilfen, Plugin-Abhängigkeiten ───────── */
$lu=new WP_List_Util([['id'=>1,'n'=>'b','t'=>'x'],['id'=>2,'n'=>'a','t'=>'y'],['id'=>3,'n'=>'c','t'=>'x']]);
t('WP_List_Util::filter',array_keys($lu->filter(['t'=>'x']))===[0,2]&&count($lu->get_input())===3);
t('WP_List_Util: NOT/OR',count((new WP_List_Util($lu->get_input()))->filter(['t'=>'x','n'=>'a'],'OR'))===3&&count((new WP_List_Util($lu->get_input()))->filter(['t'=>'x'],'NOT'))===1&&(new WP_List_Util([]))->filter(['a'=>1],'XOR')===[]);
t('WP_List_Util::pluck',(new WP_List_Util($lu->get_input()))->pluck('n')===['b','a','c']&&(new WP_List_Util($lu->get_input()))->pluck('n','id')===[1=>'b',2=>'a',3=>'c']);
t('WP_List_Util::sort',array_column((new WP_List_Util($lu->get_input()))->sort('n'),'id')===[2,1,3]&&array_column((new WP_List_Util($lu->get_input()))->sort(['t'=>'ASC','n'=>'DESC']),'id')===[3,1,2]&&array_keys((new WP_List_Util($lu->get_input()))->sort('n','ASC',true))===[1,0,2]);
$tm=WP_Token_Map::from_array(['&amp;'=>'&','&amp'=>'&','&lt;'=>'<','AB'=>'x']);
t('WP_Token_Map',$tm->contains('&lt;')&&!$tm->contains('lt')&&$tm->contains('ab','ascii-case-insensitive')&&$tm->read_token('x&amp;y',1,$len)==='&'&&$len===5&&$tm->read_token('abc',0,$l2)===null&&$tm->get_mapping('&lt;')==='<'&&count($tm->to_array())===4);
t('WP_Token_Map: vorberechnete Tabelle',WP_Token_Map::from_precomputed_table(['key_length'=>2,'map'=>['a'=>'b']])->get_mapping('a')==='b'&&str_contains($tm->precomputed_php_source_table(),'from_precomputed_table'));
t('WP_MatchesMapRegex',WP_MatchesMapRegex::apply('index.php?x=$matches[1]&y=$matches[2]',['a','eins','zwei'])==='index.php?x=eins&y=zwei');
file_put_contents($tmp.'/wp-content/plugins/basis.php',"<?php\n/* Plugin Name: Basis */");
mkdir($tmp.'/wp-content/plugins/abh');file_put_contents($tmp.'/wp-content/plugins/abh/abh.php',"<?php\n/* Plugin Name: Abhängig\nRequires Plugins: basis, fehlt */");
$wpp=[];try{ $r=new ReflectionClass('WP_Plugin_Dependencies');$r->getMethod('reset')->invoke(null); }catch(Throwable $e){}
wp_cache_flush();
t('WP_Plugin_Dependencies: Abhängigkeiten',WP_Plugin_Dependencies::get_dependencies('abh/abh.php')===['basis','fehlt']&&WP_Plugin_Dependencies::get_dependents('basis')===['abh/abh.php']&&WP_Plugin_Dependencies::has_dependents('basis.php')&&!WP_Plugin_Dependencies::has_dependents('abh/abh.php'));
t('WP_Plugin_Dependencies: unerfüllt',WP_Plugin_Dependencies::has_unmet_dependencies('abh/abh.php')&&!WP_Plugin_Dependencies::has_unmet_dependencies('basis.php')&&WP_Plugin_Dependencies::get_dependency_filepaths()===['basis'=>'basis.php','fehlt'=>false]&&WP_Plugin_Dependencies::get_dependency_names('abh/abh.php')==['basis'=>'Basis','fehlt'=>'fehlt']);
t('WP_Plugin_Dependencies: aktive Abhängige',!WP_Plugin_Dependencies::has_active_dependents('basis.php'));

/* ───────── Sitzungs-Token und Anwendungspasswörter ───────── */
$uid2=wp_create_user('sess','pw-1234567','s@example.test');
$sm=WP_Session_Tokens::get_instance($uid2);$tk=$sm->create(time()+3600);
t('WP_Session_Tokens: erzeugen/prüfen',$sm instanceof WP_User_Meta_Session_Tokens&&$sm->verify($tk)&&isset($sm->get($tk)['login'])&&$sm->get($tk)['expiration']>time()&&!$sm->verify('falsch'));
$tk2=$sm->create(time()+3600);t('WP_Session_Tokens: mehrere',count($sm->get_all())===2);
$sm->destroy_others($tk);t('WP_Session_Tokens: destroy_others',$sm->verify($tk)&&!$sm->verify($tk2)&&count($sm->get_all())===1);
$sm->update('abgelaufen',['expiration'=>time()-10]);t('WP_Session_Tokens: abgelaufen',!$sm->verify('abgelaufen'));
$sm->destroy($tk);t('WP_Session_Tokens: destroy',!$sm->verify($tk));
$sm->create(time()+60);$sm->destroy_all();t('WP_Session_Tokens: destroy_all',$sm->get_all()===[]&&get_user_meta($uid2,'session_tokens',true)==='');
$sm->create(time()+60);WP_Session_Tokens::destroy_all_for_all_users();t('WP_Session_Tokens::destroy_all_for_all_users',WP_Session_Tokens::get_instance($uid2)->get_all()===[]);
t('Sitzungs-Token passen zu den Anmeldefunktionen',(function() use($uid2){ $tok=WP_Session_Tokens::get_instance($uid2)->create(time()+60);return isset(get_user_meta($uid2,'session_tokens',true)[hash('sha256',$tok)]); })());
t('WP_Application_Passwords: Name nötig',WP_Application_Passwords::create_new_application_password($uid2,[])->get_error_code()==='application_password_empty_name');
$res=WP_Application_Passwords::create_new_application_password($uid2,['name'=>'Mobil','app_id'=>'abc']);
t('WP_Application_Passwords: anlegen',is_array($res)&&strlen($res[0])===24&&$res[1]['name']==='Mobil'&&wp_check_password($res[0],$res[1]['password'])&&WP_Application_Passwords::is_in_use());
$uuid=$res[1]['uuid'];
t('WP_Application_Passwords: lesen',count(WP_Application_Passwords::get_user_application_passwords($uid2))===1&&WP_Application_Passwords::get_user_application_password($uid2,$uuid)['app_id']==='abc'&&WP_Application_Passwords::get_user_application_password($uid2,'x')===null&&WP_Application_Passwords::application_name_exists_for_user($uid2,'MOBIL'));
t('WP_Application_Passwords: ändern',WP_Application_Passwords::update_application_password($uid2,$uuid,['name'=>'Tablet'])===true&&WP_Application_Passwords::get_user_application_password($uid2,$uuid)['name']==='Tablet'&&WP_Application_Passwords::update_application_password($uid2,'x',[])->get_error_code()==='application_password_not_found');
t('WP_Application_Passwords: Nutzung',WP_Application_Passwords::record_application_password_usage($uid2,$uuid)===true&&WP_Application_Passwords::get_user_application_password($uid2,$uuid)['last_used']>0&&WP_Application_Passwords::record_application_password_usage($uid2,'x')->get_error_code()==='application_password_not_found');
t('WP_Application_Passwords::chunk_password',WP_Application_Passwords::chunk_password('abcdefgh1234')==='abcd efgh 1234'&&WP_Application_Passwords::hash_password('x')!=='x');
t('WP_Application_Passwords: löschen',WP_Application_Passwords::delete_application_password($uid2,$uuid)===true&&WP_Application_Passwords::get_user_application_passwords($uid2)===[]&&WP_Application_Passwords::delete_application_password($uid2,$uuid)->get_error_code()==='application_password_not_found');
WP_Application_Passwords::create_new_application_password($uid2,['name'=>'A']);WP_Application_Passwords::create_new_application_password($uid2,['name'=>'B']);
t('WP_Application_Passwords: alle löschen',WP_Application_Passwords::delete_all_application_passwords($uid2)===2&&WP_Application_Passwords::delete_all_application_passwords($uid2)===0);

/* ───────── PasswordHash ───────── */
$ph=new PasswordHash(8,true);$h=$ph->HashPassword('geheim');
t('PasswordHash: portabel',str_starts_with($h,'$P$')&&strlen($h)===34&&$ph->CheckPassword('geheim',$h)&&!$ph->CheckPassword('falsch',$h));
t('PasswordHash: bekannter Wert',$ph->CheckPassword('test12345','$P$9IQRaTwmfeRo7ud9Fh4E2PdI0S3r.L0')&&!$ph->CheckPassword('test1234','$P$9IQRaTwmfeRo7ud9Fh4E2PdI0S3r.L0'));
$pb=new PasswordHash(8,false);$hb=$pb->HashPassword('geheim');
t('PasswordHash: Blowfish',str_starts_with($hb,'$2a$08$')&&strlen($hb)===60&&$pb->CheckPassword('geheim',$hb)&&!$pb->CheckPassword('x',$hb));
t('PasswordHash: Grenzen',$ph->HashPassword(str_repeat('a',4097))==='*'&&!$ph->CheckPassword(str_repeat('a',4097),$h)&&str_starts_with($ph->gensalt_private('abcdef'),'$P$')&&strlen($ph->encode64('abcdef',6))===8&&$ph->iteration_count_log2===8&&(new PasswordHash(99,false))->iteration_count_log2===8);

/* ───────── Übersetzungsdateien ───────── */
function mk_mo(array $e): string { ksort($e);$ids='';$strs='';$oi=[];$ti=[];foreach($e as $k=>$v){ $oi[]=[strlen($k),strlen($ids)];$ids.=$k."\0";$ti[]=[strlen($v),strlen($strs)];$strs.=$v."\0"; }
    $n=count($e);$ks=28+$n*16;$vs=$ks+strlen($ids);$o=pack('V7',0x950412de,0,$n,28,28+$n*8,0,0);foreach($oi as [$l,$of])$o.=pack('VV',$l,$ks+$of);foreach($ti as [$l,$of])$o.=pack('VV',$l,$vs+$of);return $o.$ids.$strs; }
$mo=mk_mo([''=>"Project-Id-Version: x\nLanguage: de_DE\nPlural-Forms: nplurals=2; plural=(n != 1);\n",'Hello'=>'Hallo',"menu\x04Hello"=>'Hallo (Menü)',"One\0Many"=>"Eins\0Viele"]);
file_put_contents($tmp.'/de_DE.mo',$mo);
$tf=WP_Translation_File::create($tmp.'/de_DE.mo');
t('WP_Translation_File_MO',$tf instanceof WP_Translation_File_MO&&$tf->exists()&&$tf->error()===null&&$tf->headers()['language']==='de_DE'&&$tf->get_language()==='de_DE'&&$tf->translate('Hello')==='Hallo'&&$tf->translate('Hello','menu')==='Hallo (Menü)'&&$tf->translate('Nix')===false);
t('WP_Translation_File_MO: Mehrzahl',$tf->translate_plural(['One','Many'],1)==='Eins'&&$tf->translate_plural(['One','Many'],5)==='Viele'&&$tf->translate_plural(['Gibt','es nicht'],2)===false);
t('WP_Translation_File: Formate/Fehler',WP_Translation_File::create($tmp.'/x.txt')===false&&WP_Translation_File::create($tmp.'/gibts.mo')->error()!==null&&!WP_Translation_File::create($tmp.'/gibts.mo')->exists());
file_put_contents($tmp.'/kaputt.mo','nope-nope-nope-nope-nope-nope-nope');t('WP_Translation_File_MO: ungültig',WP_Translation_File::create($tmp.'/kaputt.mo')->error()!==null);
file_put_contents($tmp.'/fr_FR.l10n.php','<?php return '.var_export(['domain'=>'d','plural-forms'=>'nplurals=2; plural=n > 1;','language'=>'fr_FR','messages'=>['Hello'=>'Bonjour',"One\0Many"=>"Un\0Plusieurs"]],true).';');
$pf=WP_Translation_File::create($tmp.'/fr_FR.l10n.php');
t('WP_Translation_File_PHP',$pf instanceof WP_Translation_File_PHP&&$pf->translate('Hello')==='Bonjour'&&$pf->get_language()==='fr_FR'&&$pf->translate_plural(['One','Many'],0)==='Un'&&$pf->translate_plural(['One','Many'],2)==='Plusieurs');
t('Plural-Formeln',RRW_C2_Plural::index('nplurals=1; plural=0;',5)===0&&RRW_C2_Plural::index('nplurals=3; plural=(n==1 ? 0 : n>=2 && n<=4 ? 1 : 2);',3)===1&&RRW_C2_Plural::index('nplurals=3; plural=(n==1 ? 0 : n>=2 && n<=4 ? 1 : 2);',7)===2&&RRW_C2_Plural::index('nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : 1);',21)===0&&RRW_C2_Plural::index('quatsch',1)===0);
$tr=new WP_Translations($tf,'dom');t('WP_Translations',$tr->translate('Hello')==='Hallo'&&$tr->translate('Fremd')==='Fremd'&&$tr->translate_plural('One','Many',3)==='Viele'&&$tr->translate_plural('A','B',1)==='A');
$tc=WP_Translation_Controller::get_instance();
t('WP_Translation_Controller',$tc===WP_Translation_Controller::get_instance()&&$tc->load_file($tmp.'/de_DE.mo','dom','de_DE')&&$tc->is_textdomain_loaded('dom','de_DE')&&$tc->translate('Hello',null,'dom','de_DE')==='Hallo'&&$tc->translate('Hello',null,'andere','de_DE')===false&&$tc->translate_plural(['One','Many'],2,null,'dom','de_DE')==='Viele'&&!$tc->load_file($tmp.'/gibts.mo','dom','de_DE'));
t('WP_Translation_Controller: Einträge und Entladen',isset($tc->get_entries('dom','de_DE')['Hello'])&&$tc->get_headers('dom','de_DE')['language']==='de_DE'&&$tc->unload_textdomain('dom','de_DE')&&!$tc->is_textdomain_loaded('dom','de_DE'));
$tr2=new WP_Textdomain_Registry();$tr2->set('abc',$tmp.'/x');$tr2->set_custom_path('cust',$tmp.'/c');
t('WP_Textdomain_Registry',$tr2->has('abc')&&$tr2->get('abc','de_DE')===$tmp.'/x/'&&$tr2->get('cust','de_DE')===$tmp.'/c/'&&$tr2->get('default','de_DE')===trailingslashit(WP_LANG_DIR)&&$tr2->get('nix','de_DE')===false&&!$tr2->reset()&&!$tr2->has('abc'));
$ls=new WP_Locale_Switcher();$ls->init();$orig=get_locale();
t('WP_Locale_Switcher: wechseln',!$ls->switch_to_locale($orig)&&$ls->switch_to_locale('fr_FR',5)&&get_locale()==='fr_FR'&&$ls->is_switched()&&$ls->get_switched_locale()==='fr_FR'&&$ls->get_switched_user_id()===5);
t('WP_Locale_Switcher: verschachtelt zurück',$ls->switch_to_locale('it_IT')&&get_locale()==='it_IT'&&$ls->restore_previous_locale()==='fr_FR'&&get_locale()==='fr_FR'&&$ls->restore_previous_locale()===$orig&&get_locale()===$orig&&!$ls->is_switched()&&$ls->restore_previous_locale()===false);
$ls->switch_to_locale('es_ES');$ls->switch_to_locale('pt_PT');t('WP_Locale_Switcher::restore_current_locale',$ls->restore_current_locale()===$orig&&get_locale()===$orig&&$ls->restore_current_locale()===false);remove_all_filters('locale');

/* ───────── Diff-Darstellung ───────── */
$dt=new WP_Text_Diff_Renderer_Table();
$rd=$dt->render([['type'=>'copy','orig'=>['gleich'],'final'=>['gleich']],['type'=>'change','orig'=>['Hallo Welt'],'final'=>['Hallo Radio']],['type'=>'add','orig'=>[],'final'=>['neu <b>']],['type'=>'delete','orig'=>['alt'],'final'=>[]]]);
t('WP_Text_Diff_Renderer_Table',str_contains($rd,'diff-context')&&str_contains($rd,'<del>Welt</del>')&&str_contains($rd,'<ins>Radio</ins>')&&str_contains($rd,'neu &lt;b&gt;')&&str_contains($rd,'diff-deletedline')&&substr_count($rd,'<tr>')===4);
$dsingle=new WP_Text_Diff_Renderer_Table(['_show_split_view'=>false]);t('WP_Text_Diff_Renderer_Table: einspaltig',substr_count($dsingle->_added(['x']),'<td')===1);
$di=new WP_Text_Diff_Renderer_inline();
t('WP_Text_Diff_Renderer_inline',$di->_changed(['a b c'],['a x c'])==='a <del>b</del> c'.'a <ins>x</ins> c'&&$di->_splitOnWords("ein Wort\n",'@')===['ein',' ','Wort','@']&&str_contains($di->_added(['<x>']),'<ins>&lt;x&gt;</ins>'));

/* ───────── Blöcke ───────── */
$doc='<!-- wp:paragraph {"align":"center"} --><p>Hi</p><!-- /wp:paragraph --><!-- wp:group --><div><!-- wp:heading --><h2>T</h2><!-- /wp:heading --></div><!-- /wp:group -->';
$bp=new WP_Block_Parser();$pb=$bp->parse($doc);
t('WP_Block_Parser',$pb[0]['blockName']==='core/paragraph'&&$pb[0]['attrs']['align']==='center'&&$pb[1]['innerBlocks'][0]['blockName']==='core/heading'&&$bp->parse('')===[]);
t('WP_Block_Parser_Block/Frame',(new WP_Block_Parser_Block('core/x',[],[],'','' ))->blockName==='core/x'&&(new WP_Block_Parser_Frame(new WP_Block_Parser_Block('a',[],[],'',''),10,5))->prev_offset===15);
$bl=new WP_Block_List($pb);
t('WP_Block_List',count($bl)===2&&$bl[0] instanceof WP_Block&&$bl[1]->name==='core/group'&&isset($bl[1])&&!isset($bl[5])&&iterator_count($bl)===2&&$bl[5]===null);
register_block_type('t/x',[]);$bs=WP_Block_Supports::get_instance();
$bs->register('demo',['apply'=>fn($type,$attrs)=>['class'=>'has-demo','style'=>'color:red']]);$bs->register('zwei',['apply'=>fn($type,$attrs)=>['class'=>'zwei','style'=>'a:b']]);$bs->register('ohne',[]);
WP_Block_Supports::$block_to_render=['blockName'=>'t/x','attrs'=>[]];
t('WP_Block_Supports',$bs===WP_Block_Supports::get_instance()&&$bs->apply_block_supports()===['class'=>'has-demo zwei','style'=>'color:red;a:b']);
WP_Block_Supports::$block_to_render=['blockName'=>'t/unbekannt'];t('WP_Block_Supports: unbekannter Block',$bs->apply_block_supports()===[]);WP_Block_Supports::$block_to_render=null;
$br=WP_Block_Bindings_Registry::get_instance();
$src=$br->register('t/src',['label'=>'L','get_value_callback'=>fn($a)=>'WERT:'.($a['k']??'')]);
t('WP_Block_Bindings_Registry',$src instanceof WP_Block_Bindings_Source&&$src->get_value(['k'=>'1'],null,'content')==='WERT:1'&&$br->is_registered('t/src')&&isset($br->get_all_registered()['t/src'])&&$br->register('t/src',['label'=>'L','get_value_callback'=>'strlen'])===false&&$br->register('ungültig',['label'=>'L'])===false);
t('WP_Block_Bindings_Registry: abmelden',$br->unregister('t/src') instanceof WP_Block_Bindings_Source&&!$br->is_registered('t/src')&&$br->unregister('t/src')===false);
$pc=WP_Block_Pattern_Categories_Registry::get_instance();$pc->register('cat-x',['label'=>'X']);
t('WP_Block_Pattern_Categories_Registry',$pc->is_registered('cat-x')&&$pc->get_registered('cat-x')['label']==='X'&&in_array('cat-x',array_column($pc->get_all_registered(),'name'),true)&&$pc->unregister('cat-x')&&!$pc->unregister('cat-x')&&$pc->get_registered('cat-x')===null);
$tpr=WP_Block_Templates_Registry::get_instance();$tpl=$tpr->register('plug/tmpl',['title'=>'T','content'=>'<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->']);
t('WP_Block_Templates_Registry',$tpl->slug==='tmpl'&&$tpr->is_registered('plug/tmpl')&&$tpr->get_registered('plug/tmpl')->title==='T'&&$tpr->get_by_slug('tmpl')->name==='plug/tmpl'&&isset($tpr->get_all_registered()['plug/tmpl']));
t('WP_Block_Templates_Registry: Fehler/abmelden',is_wp_error($tpr->register('plug/tmpl'))&&is_wp_error($tpr->register('ohne-slash'))&&$tpr->unregister('plug/tmpl')->slug==='tmpl'&&!$tpr->is_registered('plug/tmpl')&&$tpr->unregister('plug/tmpl')===false);
$svg=WP_Duotone::get_filter_svg('f1',['#000000','#ff0000']);
t('WP_Duotone',WP_Duotone::is_preset('var:preset|duotone|blau')&&WP_Duotone::get_slug_from_attribute('var:preset|duotone|blau')==='blau'&&!WP_Duotone::is_preset('#fff')&&WP_Duotone::get_css_var('Blau Rot')==='var(--wp--preset--duotone--blau-rot)'&&str_contains($svg,'id="f1"')&&str_contains($svg,'tableValues="0 1"')&&str_contains(WP_Duotone::get_filter_css_property_value_from_preset(['slug'=>'blau']),'url(#wp-duotone-blau)')&&WP_Duotone::render_duotone_support('<p>x</p>',[])==='<p>x</p>');
$jd=new WP_Theme_JSON_Data(['version'=>3,'settings'=>['color'=>['palette'=>[1]],'layout'=>['a'=>1]]],'theme');$jd2=$jd->update_with(['settings'=>['layout'=>['b'=>2]],'styles'=>['x'=>1]]);
t('WP_Theme_JSON_Data',$jd->get_data()['settings']['layout']===['a'=>1]&&$jd2->get_data()['settings']['layout']===['a'=>1,'b'=>2]&&$jd2->get_data()['styles']==['x'=>1]&&$jd2->get_theme_json() instanceof WP_Theme_JSON);
$mg=WP_Theme_JSON_Schema::migrate(['settings'=>['spacing'=>['customMargin'=>true],'blocks'=>['core/group'=>['border'=>['customRadius'=>true]]]]]);
t('WP_Theme_JSON_Schema::migrate',$mg['version']===3&&$mg['settings']['spacing']==['margin'=>true]&&$mg['settings']['blocks']['core/group']['border']==['radius'=>true]&&WP_Theme_JSON_Schema::migrate(['version'=>3,'x'=>1])==['version'=>3,'x'=>1]);

/* ───────── REST: Suche, Meta-Felder, Controller ───────── */
$rq=new WP_REST_Request('GET','/wp/v2/search');$rq->set_param('search','Erster');
$ph=new WP_REST_Post_Search_Handler();$sr=$ph->search_items($rq);
t('WP_REST_Post_Search_Handler',$ph->get_type()==='post'&&in_array('post',$ph->get_subtypes(),true)&&$sr[WP_REST_Search_Handler::RESULT_IDS]===[$p1]&&$sr[WP_REST_Search_Handler::RESULT_TOTAL]===1);
$it=$ph->prepare_item($p1,['id','title','url','type','subtype']);t('WP_REST_Post_Search_Handler: Element/Verweise',$it['id']===$p1&&$it['title']==='Erster Beitrag'&&$it['type']==='post'&&$it['subtype']==='post'&&str_contains($ph->prepare_item_links($p1)['self']['href'],'/wp/v2/')&&$ph->prepare_item($p1,['id'])===['id'=>$p1]);
$rq2=new WP_REST_Request('GET','/wp/v2/search');$rq2->set_param('search','gibt-es-sicher-nicht');t('WP_REST_Post_Search_Handler: keine Treffer',$ph->search_items($rq2)[WP_REST_Search_Handler::RESULT_TOTAL]===0);
$term=wp_insert_term('Musikwelt','category');$tid=is_array($term)?(int)$term['term_id']:0;
$th=new WP_REST_Term_Search_Handler();$rq3=new WP_REST_Request('GET','/wp/v2/search');$rq3->set_param('search','Musik');$ts=$th->search_items($rq3);
t('WP_REST_Term_Search_Handler',$th->get_type()==='term'&&in_array('category',$th->get_subtypes(),true)&&in_array($tid,$ts[WP_REST_Search_Handler::RESULT_IDS],true)&&$th->prepare_item($tid,['id','title','subtype'])==['id'=>$tid,'title'=>'Musikwelt','subtype'=>'category']&&str_contains($th->prepare_item_links($tid)['about']['href'],'taxonomies/category'));
$fh=new WP_REST_Post_Format_Search_Handler();$rq4=new WP_REST_Request('GET','/wp/v2/search');$rq4->set_param('search','vid');$fs=$fh->search_items($rq4);
t('WP_REST_Post_Format_Search_Handler',$fh->get_type()==='post-format'&&$fs[WP_REST_Search_Handler::RESULT_IDS]===['video']&&$fh->prepare_item('video',['id','title','subtype'])['subtype']==='video'&&$fh->prepare_item_links('video')===[]);
register_meta('post','mein_meta',['object_subtype'=>'post','single'=>true,'type'=>'string','default'=>'std','show_in_rest'=>true]);
register_meta('post','liste_meta',['object_subtype'=>'post','single'=>false,'type'=>'string','show_in_rest'=>['name'=>'liste']]);
register_meta('post','geheim_meta',['object_subtype'=>'post','single'=>true,'type'=>'string','show_in_rest'=>false]);
$mf=new WP_REST_Post_Meta_Fields('post');$mv=$mf->get_value($p1);
t('WP_REST_Post_Meta_Fields: lesen',$mv->mein_meta==='std'&&$mv->liste===[]&&!isset($mv->geheim_meta));
$ur=$mf->update_value(['mein_meta'=>'neu','liste'=>['a','b']],$p1);$mv=$mf->get_value($p1);
t('WP_REST_Post_Meta_Fields: schreiben',$ur===null&&$mv->mein_meta==='neu'&&$mv->liste===['a','b']&&get_post_meta($p1,'mein_meta',true)==='neu');
$mf->update_value(['mein_meta'=>null],$p1);t('WP_REST_Post_Meta_Fields: null löscht',$mf->get_value($p1)->mein_meta==='std'&&$mf->update_value(['unbekannt'=>'x'],$p1)->get_error_code()==='rest_invalid_stored_value');
$sch=$mf->get_field_schema();t('WP_REST_Meta_Fields: Schema',$sch['type']==='object'&&$sch['properties']['mein_meta']['type']==='string'&&$sch['properties']['liste']['type']==='array');
$rtype=fn($o)=>(new ReflectionMethod($o,'get_rest_field_type'))->invoke($o);
t('WP_REST_*_Meta_Fields: Feldtypen',$rtype(new WP_REST_Post_Meta_Fields('page'))==='page'&&$rtype(new WP_REST_Comment_Meta_Fields())==='comment'&&$rtype(new WP_REST_Term_Meta_Fields('post_tag'))==='tag'&&$rtype(new WP_REST_Term_Meta_Fields('category'))==='category'&&$rtype(new WP_REST_User_Meta_Fields())==='user'&&$mf instanceof WP_REST_Meta_Fields);
$ta=new WP_REST_Template_Autosaves_Controller('wp_template');$gs=new WP_REST_Global_Styles_Revisions_Controller();
$ta->register_routes();$gs->register_routes();
t('REST-Controller: Autosaves/Revisionen ohne Entsprechung',$ta->get_items(null)->get_data()===[]&&$ta->create_item(null)->get_error_code()==='rest_not_supported'&&$ta->get_item(null)->get_error_data()['status']===404&&$gs->get_items(null)->get_data()===[]&&$gs->get_item_schema()['title']==='global-styles-revision');

/* ───────── oEmbed ───────── */
$oe=new WP_oEmbed();
t('WP_oEmbed::get_provider',$oe->get_provider('https://www.youtube.com/watch?v=abc')==='https://www.youtube.com/oembed'&&str_ends_with((string)$oe->get_provider('https://vimeo.com/123'),'/oembed.json')&&$oe->get_provider('https://unbekannt.example/x')===false);
t('WP_oEmbed::data2html',str_contains((string)$oe->data2html((object)['type'=>'photo','url'=>'https://i.example/a.jpg','width'=>10,'height'=>5,'title'=>'T'],'https://x.example/'),'<img')&&$oe->data2html((object)['type'=>'video','html'=>'<iframe></iframe>'],'u')==='<iframe></iframe>'&&str_contains((string)$oe->data2html((object)['type'=>'link','title'=>'Titel'],'https://x.example/'),'>Titel</a>')&&$oe->data2html((object)['type'=>'rich'],'u')===false&&$oe->data2html((object)[],'u')===false);
$fetched=[];add_filter('pre_http_request',function($pre,$args,$url) use(&$fetched){ $fetched[]=$url;
    if(str_contains($url,'seite.example')&&!str_contains($url,'oembed'))return ['headers'=>[],'body'=>'<html><head><link rel="alternate" type="application/json+oembed" href="https://seite.example/oembed?url=x&amp;format=json"></head></html>','response'=>['code'=>200,'message'=>'OK'],'cookies'=>[],'filename'=>null];
    return ['headers'=>[],'body'=>json_encode(['type'=>'video','html'=>'<iframe src="v"></iframe>']),'response'=>['code'=>200,'message'=>'OK'],'cookies'=>[],'filename'=>null]; },10,3);
t('WP_oEmbed::get_html',$oe->get_html('https://www.youtube.com/watch?v=abc')==='<iframe src="v"></iframe>'&&str_contains($fetched[0],'url=')&&str_contains($fetched[0],'format=json')&&$oe->get_html('https://unbekannt.example/x')===false);
t('WP_oEmbed::discover',$oe->discover('https://seite.example/')==='https://seite.example/oembed?url=x&format=json'&&$oe->get_html('https://seite.example/',['discover'=>true])==='<iframe src="v"></iframe>');
remove_all_filters('pre_http_request');
$oc2=new WP_oEmbed_Controller();$oc2->register_routes();
t('WP_oEmbed_Controller',is_wp_error($oc2->get_item(['url'=>'https://example.test/nie-da/','maxwidth'=>600]))&&$oc2->get_item(['url'=>'https://example.test/nie-da/','maxwidth'=>600])->get_error_code()==='oembed_invalid_url');

/* ───────── Widgets ───────── */
$args=['before_widget'=>'<div class="w">','after_widget'=>'</div>','before_title'=>'<h2>','after_title'=>'</h2>'];
$wi=new WP_Widget_Media_Image();
$h=out(fn()=>$wi->widget($args,['url'=>'https://i.example/a.jpg','alt'=>'Alt','link_type'=>'custom','link_url'=>'https://l.example/','caption'=>'Bildtext','title'=>'Bilder','link_target_blank'=>true]));
t('WP_Widget_Media_Image: Ausgabe',str_contains($h,'<h2>Bilder</h2>')&&str_contains($h,'<img src="https://i.example/a.jpg"')&&str_contains($h,'href="https://l.example/"')&&str_contains($h,'target="_blank"')&&str_contains($h,'<figcaption class="wp-caption-text">Bildtext'));
t('WP_Widget_Media_Image: ohne Inhalt nichts',out(fn()=>$wi->widget($args,['title'=>'x']))==='');
$up=$wi->update(['url'=>'javascript:alert(1)','link_type'=>'bogus','attachment_id'=>-5,'alt'=>'<b>Alt</b>','caption'=>'<script>x</script><b>ok</b>','link_target_blank'=>'1','title'=>'T'],['link_type'=>'file']);
t('WP_Widget_Media: Bereinigung',$up['url']===''&&$up['link_type']==='file'&&!isset($up['attachment_id'])&&$up['alt']==='Alt'&&$up['caption']==='x<b>ok</b>'&&$up['link_target_blank']===true&&$up['title']==='T');
$wv=new WP_Widget_Media_Video();$va=new WP_Widget_Media_Audio();
t('WP_Widget_Media_Video/Audio',str_contains($wv->render_media(['url'=>'https://v.example/a.mp4']),'<video')&&str_contains($va->render_media(['url'=>'https://v.example/a.mp3']),'<audio')&&$wv->render_media([])==='');
$wg=new WP_Widget_Media_Gallery();t('WP_Widget_Media_Gallery',!$wg->has_content(['ids'=>[]])&&$wg->has_content(['ids'=>[3]])&&$wg->update(['ids'=>['4','x',5],'columns'=>3],[])['ids']===[4,0,5]&&is_string($wg->render_media(['ids'=>[999999]])));
t('WP_Widget_Media: Formular/Schema',str_contains(out(fn()=>$wi->form(['title'=>'F'])),'value="F"')&&isset($wi->get_instance_schema()['link_type'])&&$wi->id_base==='media_image'&&$wi instanceof WP_Widget);
$wl=new WP_Widget_Links();$h=out(fn()=>$wl->widget($args,['title'=>'Freunde','images'=>0,'orderby'=>'name']));
t('WP_Widget_Links',str_contains($h,'<ul class="xoxo blogroll">')&&str_contains($h,'>Alpha 2</a>')&&str_contains($h,'<h2>Freunde</h2>')&&$wl->update(['title'=>'<b>T</b>','rating'=>1,'orderby'=>'nix','limit'=>'3'],[])==['title'=>'T','description'=>0,'rating'=>1,'images'=>0,'name'=>0,'category'=>0,'orderby'=>'name','limit'=>3]);
$wc=new WP_Widget_Calendar();t('WP_Widget_Calendar',str_contains(out(fn()=>$wc->widget($args,['title'=>'Kal'])),'calendar_wrap')&&$wc->update(['title'=>'<i>x</i>'],[])['title']==='x');
add_filter('pre_fetch_feed',fn($p,$u)=>new WP_Error('simplepie-error','kaputt'),10,2);$wr=new WP_Widget_RSS();
$ru=$wr->update(['url'=>'https://f.example/rss','items'=>99,'show_date'=>1,'title'=>'F'],[]);
t('WP_Widget_RSS: Aktualisierung',$ru['url']==='https://f.example/rss'&&$ru['items']===20&&$ru['show_date']===1&&$ru['error']==='kaputt'&&out(fn()=>$wr->widget($args,$ru))===''&&str_contains(out(fn()=>$wr->form(['url'=>'https://f.example/rss'])),'https://f.example/rss'));
remove_all_filters('pre_fetch_feed');
$wb=new WP_Widget_Block();$bh=out(fn()=>$wb->widget($args,['content'=>'<!-- wp:paragraph --><p>Blockinhalt</p><!-- /wp:paragraph -->']));
t('WP_Widget_Block',str_contains($bh,'Blockinhalt')&&str_starts_with($bh,'<div class="w">')&&$wb->update(['content'=>'<script>x</script><p>ok</p>'],[])['content']!==''&&str_contains(out(fn()=>$wb->form(['content'=>'abc'])),'>abc</textarea>'));
$cw=new WP_Customize_Widgets(null);
t('WP_Customize_Widgets',WP_Customize_Widgets::get_setting_id('text-3')==='widget_text-3'&&$cw->get_widget_setting_id('text-3')==='widget_text[3]'&&isset($cw->filter_nonces([])['update-widget'])&&is_array($cw->get_selective_refreshable_widgets()));

/* ───────── HTTP: Requests, Kodierung, Socket-Transport, E-Mail, POP3 ───────── */
$ok200=fn($body,$h=[])=>['headers'=>$h+['content-type'=>'text/plain'],'body'=>$body,'response'=>['code'=>200,'message'=>'OK'],'cookies'=>[],'filename'=>null];
add_filter('pre_http_request',function($pre,$args,$url) use($ok200){ return $url==='https://r.example/fehler'?['headers'=>[],'body'=>'nein','response'=>['code'=>500,'message'=>'x'],'cookies'=>[],'filename'=>null]:$ok200('antwort:'.$args['method'].':'.(is_array($args['body'])?json_encode($args['body']):(string)$args['body'])); },10,3);
$rs=Requests::get('https://r.example/x');
t('Requests::get',$rs instanceof Requests_Response&&$rs->body==='antwort:GET:'&&$rs->status_code===200&&$rs->success&&$rs->headers['content-type']==='text/plain');
$rp=Requests::post('https://r.example/x',[],['a'=>1]);t('Requests::post/put/delete',$rp->body==='antwort:POST:{"a":1}'&&Requests::delete('https://r.example/x')->body==='antwort:DELETE:'&&Requests::put('https://r.example/x',[],'roh')->body==='antwort:PUT:roh'&&Requests::head('https://r.example/x')->success);
$rf=Requests::get('https://r.example/fehler');$thr=false;try{ $rf->throw_for_status(); }catch(Exception $e){ $thr=true; }
t('Requests: Fehlerstatus',$rf->status_code===500&&!$rf->success&&$thr&&Requests::flatten(['A'=>'b'])===['A: b']&&Requests::get_default_options()['timeout']===10&&Requests::VERSION!==''&&Requests::GET==='GET');
$mr=Requests::request_multiple([['url'=>'https://r.example/1'],['url'=>'https://r.example/2','type'=>'POST']]);t('Requests::request_multiple',count($mr)===2&&$mr[1]->body==='antwort:POST:[]');
$hs=[];$hooks=new WP_HTTP_Requests_Hooks('https://r.example/',['a'=>1]);$hooks->register('curl.before_send',function() use(&$hs){ $hs[]='b'; });add_action('requests-curl.before_send',function() use(&$hs){ $hs[]='act'; });
$curl='x';t('WP_HTTP_Requests_Hooks',$hooks->dispatch('curl.before_send',[&$curl])===true&&$hs===['b','act']&&$hooks->dispatch('unbekannt')===false);
$rr=new Requests_Response();$rr->body='B';$rr->status_code=404;$rr->headers=['Content-Type'=>'text/html'];$wr=new WP_HTTP_Requests_Response($rr,'/tmp/f');
$ra=$wr->to_array();t('WP_HTTP_Requests_Response',$wr->get_status()===404&&$wr->get_response_object()===$rr&&$ra['response']['code']===404&&$ra['response']['message']!==''&&$ra['body']==='B'&&$ra['filename']==='/tmp/f'&&$ra['http_response']===$wr&&isset($wr->get_headers()['content-type']));
remove_all_filters('pre_http_request');
$cz=WP_Http_Encoding::compress('Hallo Hallo Hallo');
t('WP_Http_Encoding: Kompression',WP_Http_Encoding::decompress($cz)==='Hallo Hallo Hallo'&&WP_Http_Encoding::decompress(gzencode('Gzip-Text'))==='Gzip-Text'&&WP_Http_Encoding::decompress(gzcompress('Zlib'))==='Zlib'&&WP_Http_Encoding::decompress('')===''&&WP_Http_Encoding::decompress('kein gzip')==='kein gzip');
t('WP_Http_Encoding: Aushandlung',WP_Http_Encoding::compatible_gzinflate(gzencode('xyz'))==='xyz'&&WP_Http_Encoding::compatible_gzinflate('xyz')===false&&str_contains(WP_Http_Encoding::accept_encoding('u',[]),'deflate')&&!str_contains(WP_Http_Encoding::accept_encoding('u',['decompress'=>false]),'deflate')&&WP_Http_Encoding::should_decode(['Content-Encoding'=>'gzip'])&&!WP_Http_Encoding::should_decode(['a'=>'b'])&&WP_Http_Encoding::is_available()&&WP_Http_Encoding::content_encoding()==='deflate');
t('WP_HTTP_Fsockopen: Verfügbarkeit und Schutz',WP_HTTP_Fsockopen::test()&&(new WP_HTTP_Fsockopen())->request('http://127.0.0.1:9/')->get_error_code()==='http_request_failed'&&(new WP_HTTP_Fsockopen())->request('ftp://x/')->get_error_code()==='http_request_failed');
$pm=new WP_PHPMailer();
t('WP_PHPMailer: Adressen',$pm->addAddress('a@example.test','Anna')&&!$pm->addAddress('kaputt')&&str_contains($pm->ErrorInfo,'Ungültige')&&$pm->addCC('c@example.test')&&$pm->addReplyTo('r@example.test')&&$pm->setFrom('von@example.test','Absender')&&!$pm->setFrom('nope')&&$pm->From==='von@example.test');
$pm->isHTML(true);$pm->addCustomHeader('X-Test','1');$hd=$pm->createHeader();
t('WP_PHPMailer: Kopfzeilen',str_contains($hd,'From: =?UTF-8?B?')&&str_contains($hd,'Cc: c@example.test')&&str_contains($hd,'Content-Type: text/html; charset=UTF-8')&&str_contains($hd,'X-Test: 1')&&str_contains($hd,'Reply-To: r@example.test'));
$pm2=new WP_PHPMailer();t('WP_PHPMailer::send: Fehlerfälle',$pm2->send()===false&&str_contains($pm2->ErrorInfo,'Empfänger')&&($pm2->addAddress('x@example.test')&&$pm2->addAttachment(__FILE__)&&$pm2->send()===false)&&str_contains($pm2->ErrorInfo,'Anhänge'));
$po=new POP3();
t('POP3: Hilfen',$po->parse_banner('+OK ready <1896.697@mail.example>')==='<1896.697@mail.example>'&&$po->parse_banner('+OK')===''&&$po->is_ok('+OK fein')&&!$po->is_ok('-ERR')&&$po->strip_clf("a\r\nb")==='ab'&&$po->connect('')===false);
t('POP3: Verbindungsfehler',(new POP3('127.0.0.1',1))->connect('127.0.0.1',1)===false&&str_contains($po->ERROR,'POP3'));

/* ───────── XML-RPC (IXR) ───────── */
$rq=new IXR_Request('demo.test',[1,'a<b',true,1.5,['x'=>2],[1,2],new IXR_Date(1714979289),new IXR_Base64('bin')]);$xml=$rq->getXml();
t('IXR_Request: XML',str_starts_with($xml,'<?xml')&&str_contains($xml,'<methodName>demo.test</methodName>')&&str_contains($xml,'<int>1</int>')&&str_contains($xml,'a&lt;b')&&str_contains($xml,'<boolean>1</boolean>')&&str_contains($xml,'<double>1.5</double>')&&str_contains($xml,'<name>x</name>')&&str_contains($xml,'<array>')&&str_contains($xml,'<dateTime.iso8601>20240506T07:08:09')&&str_contains($xml,'<base64>'.base64_encode('bin'))&&$rq->getLength()===strlen($xml));
$im=new IXR_Message($xml);
t('IXR_Message: Rundlauf',$im->parse()&&$im->messageType==='methodCall'&&$im->methodName==='demo.test'&&$im->params[0]===1&&$im->params[1]==='a<b'&&$im->params[2]===true&&$im->params[3]===1.5&&$im->params[4]==['x'=>2]&&$im->params[5]===[1,2]&&$im->params[6] instanceof IXR_Date&&$im->params[6]->getTimestamp()===1714979289&&$im->params[7]==='bin');
t('IXR_Message: Fehler/Antwort',!(new IXR_Message('keine xml'))->parse()&&(function(){ $m=new IXR_Message((new IXR_Error(42,'Ups'))->getXml());return $m->parse()&&$m->messageType==='fault'&&$m->faultCode===42&&$m->faultString==='Ups'; })());
t('IXR_Value: Typen',(new IXR_Value(5))->type==='int'&&(new IXR_Value('s'))->type==='string'&&(new IXR_Value([1]))->type==='array'&&(new IXR_Value(['a'=>1]))->type==='struct'&&(new IXR_Value((object)['a'=>1]))->type==='struct'&&(new IXR_Value([]))->type==='array'&&(new IXR_Value(false))->getXml()==='<boolean>0</boolean>');
t('IXR_Date',(new IXR_Date('20240506T07:08:09'))->getIso()==='20240506T07:08:09'&&(new IXR_Date('2024-05-06T07:08:09Z'))->getTimestamp()===1714979289&&(new IXR_Date(0))->getIso()==='19700101T00:00:00');
$srv=new IXR_Server(['test.sum'=>'array_sum'],false,true);
$call=fn($m,$p)=>(new IXR_Request($m,$p))->getXml();
$o=out(fn()=>$srv->serve($call('test.sum',[2,3])));
t('IXR_Server: Aufruf',str_contains($o,'<methodResponse>')&&str_contains($o,'<int>5</int>'));
t('IXR_Server: unbekannte Methode/Parsefehler',str_contains(out(fn()=>$srv->serve($call('gibts.nicht',[]))),'<int>-32601</int>')&&str_contains(out(fn()=>$srv->serve('Quatsch')),'<int>-32700</int>')&&str_contains(out(fn()=>$srv->serve('<methodResponse><params/></methodResponse>')),'<int>-32600</int>'));
$o=out(fn()=>$srv->serve($call('system.listMethods',[])));t('IXR_Server: system.listMethods',str_contains($o,'test.sum')&&str_contains($o,'system.multicall')&&$srv->hasMethod('test.sum')&&!$srv->hasMethod('nein'));
$o=out(fn()=>$srv->serve($call('system.multicall',[[['methodName'=>'test.sum','params'=>[[1,2]]],['methodName'=>'x.nein','params'=>[]]]])));
t('IXR_Server: system.multicall',str_contains($o,'<int>3</int>')&&str_contains($o,'faultCode'));
$last=null;add_filter('pre_http_request',function($pre,$args,$url) use(&$last){ $last=[$url,$args];
    if(str_contains($url,'fehler'))return ['headers'=>[],'body'=>'','response'=>['code'=>500,'message'=>'x'],'cookies'=>[],'filename'=>null];
    $m=new IXR_Message($args['body']);$m->parse();
    $resp=$m->methodName==='boese'?(new IXR_Error(7,'schlecht'))->getXml():'<methodResponse><params><param><value>'.(new IXR_Value(['echo'=>$m->params]))->getXml().'</value></param></params></methodResponse>';
    return ['headers'=>[],'body'=>'<?xml version="1.0"?>'.$resp,'response'=>['code'=>200,'message'=>'OK'],'cookies'=>[],'filename'=>null]; },10,3);
$ic=new IXR_Client('https://xr.example/rpc.php?a=1');
t('IXR_Client: Aufruf',$ic->query('m.echo','x',2)&&$ic->getResponse()==['echo'=>['x',2]]&&$last[0]==='https://xr.example/rpc.php?a=1'&&!$ic->isError());
t('IXR_Client: Fehlerantwort',$ic->query('boese')===false&&$ic->isError()&&$ic->getErrorCode()===7&&$ic->getErrorMessage()==='schlecht');
$wc=new WP_HTTP_IXR_Client('https://xr.example/xmlrpc.php');
t('WP_HTTP_IXR_Client',$wc->query('m.echo',1)&&$wc->getResponse()==['echo'=>[1]]&&$wc->scheme==='https'&&$last[0]==='https://xr.example:443/xmlrpc.php'&&$last[1]['headers']['Content-Type']==='text/xml');
$wf=new WP_HTTP_IXR_Client('https://fehler.example/x.php');t('WP_HTTP_IXR_Client: HTTP-Fehler',$wf->query('m.echo')===false&&$wf->getErrorCode()===-32301);
remove_all_filters('pre_http_request');
$xu=wp_create_user('xmluser','pw-123456','xml@example.test');$GLOBALS['rrw_wp_user']=null;
$xs=new wp_xmlrpc_server();$xs->callbacks=$xs->methods;$xs->setCallbacks();
t('wp_xmlrpc_server: Methoden',isset($xs->methods['wp.getPost'],$xs->methods['demo.sayHello'])&&$xs instanceof IXR_Server&&isset($xs->blog_options['blog_title']));
t('wp_xmlrpc_server: demo.*',str_contains(out(fn()=>$xs->serve($call('demo.sayHello',[]))),'<string>Hallo!</string>')&&str_contains(out(fn()=>$xs->serve($call('demo.addTwoNumbers',[[4,5]]))),'<int>9</int>'));
$o=out(fn()=>$xs->serve($call('wp.getUsersBlogs',['xmluser','falsch'])));t('wp_xmlrpc_server: falsches Passwort',str_contains($o,'<int>403</int>'));
$o=out(fn()=>$xs->serve($call('wp.getUsersBlogs',['xmluser','pw-123456'])));t('wp_xmlrpc_server: wp.getUsersBlogs',str_contains($o,'<name>blogid</name>')&&str_contains($o,'xmlrpc.php'));
as_user(1,'admin','administrator');
$o=out(fn()=>$xs->serve($call('wp.newPost',[1,'xmluser','pw-123456',['post_title'=>'Per XML-RPC','post_content'=>'Inhalt','post_status'=>'draft']])));
preg_match('~<string>(\d+)</string>~',$o,$mm);$nid=(int)($mm[1]??0);
t('wp_xmlrpc_server: wp.newPost',$nid>0&&get_post($nid)->post_title==='Per XML-RPC');
$o=out(fn()=>$xs->serve($call('wp.getPost',[1,'xmluser','pw-123456',$nid])));t('wp_xmlrpc_server: wp.getPost',str_contains($o,'Per XML-RPC')&&str_contains($o,'<name>post_status</name>'));
$o=out(fn()=>$xs->serve($call('wp.editPost',[1,'xmluser','pw-123456',$nid,['post_title'=>'Geändert']])));t('wp_xmlrpc_server: wp.editPost',str_contains($o,'<boolean>1</boolean>')&&get_post($nid)->post_title==='Geändert');
$o=out(fn()=>$xs->serve($call('wp.getPosts',[1,'xmluser','pw-123456',['post_status'=>'draft','number'=>5]])));t('wp_xmlrpc_server: wp.getPosts',str_contains($o,'Geändert'));
$o=out(fn()=>$xs->serve($call('wp.getOptions',[1,'xmluser','pw-123456',['blog_title']])));t('wp_xmlrpc_server: wp.getOptions',str_contains($o,'<name>blog_title</name>')&&!str_contains($o,'software_name'));
$o=out(fn()=>$xs->serve($call('wp.deletePost',[1,'xmluser','pw-123456',$nid])));t('wp_xmlrpc_server: wp.deletePost',str_contains($o,'<boolean>1</boolean>')&&!get_post($nid));
update_option('enable_xmlrpc',false);$o=out(fn()=>$xs->serve($call('wp.getUsersBlogs',['xmluser','pw-123456'])));t('wp_xmlrpc_server: abschaltbar',str_contains($o,'<int>405</int>'));update_option('enable_xmlrpc',true);
$esc=['x'=>"a'b",'y'=>['z'=>'c"d']];(new wp_xmlrpc_server())->escape($esc);t('wp_xmlrpc_server::escape',$esc['x']==="a\\'b"&&$esc['y']['z']==='c\\"d');

/* ───────── Bild-Editoren ───────── */
$img=imagecreatetruecolor(100,60);imagefill($img,0,0,imagecolorallocate($img,200,30,30));imagepng($img,$tmp.'/b.png');imagedestroy($img);
$ed=WP_Image_Editor::get_instance($tmp.'/b.png');
t('WP_Image_Editor::get_instance (GD)',$ed instanceof WP_Image_Editor_GD&&$ed->get_size()==['width'=>100,'height'=>60]&&WP_Image_Editor_GD::test(['rrw_direct'=>true])&&!WP_Image_Editor_GD::test()&&WP_Image_Editor_GD::supports_mime_type('image/png')&&!WP_Image_Editor_GD::supports_mime_type('image/xyz')&&$ed->get_suffix()==='100x60');
add_filter('rrw_wp_image_editor_enabled','__return_true');t('Bild-Editor: Aktivierung per Filter',WP_Image_Editor_GD::test()&&_wp_image_editor_choose()==='WP_Image_Editor_GD');remove_all_filters('rrw_wp_image_editor_enabled');t('Bild-Editor: Standard ist aus',_wp_image_editor_choose()===false);
t('WP_Image_Editor: Fehlerfälle',WP_Image_Editor::get_instance($tmp.'/gibts.png') instanceof WP_Error&&!WP_Image_Editor_Imagick::test()&&WP_Image_Editor_Imagick::supports_mime_type('image/png')===false&&is_wp_error($ed->set_quality(0))&&$ed->set_quality(70)===true&&$ed->get_quality()===70);
t('WP_Image_Editor_GD::resize',$ed->resize(50,30)===true&&$ed->get_size()==['width'=>50,'height'=>30]&&$ed->resize(50,30)===true);
$sv=$ed->save($tmp.'/b-klein.png');
t('WP_Image_Editor_GD::save',is_array($sv)&&is_file($tmp.'/b-klein.png')&&$sv['width']===50&&$sv['height']===30&&$sv['mime-type']==='image/png'&&$sv['file']==='b-klein.png'&&getimagesize($tmp.'/b-klein.png')[0]===50);
$ed2=WP_Image_Editor::get_instance($tmp.'/b.png');
t('WP_Image_Editor_GD::crop/rotate/flip',$ed2->crop(10,10,40,20)===true&&$ed2->get_size()==['width'=>40,'height'=>20]&&$ed2->rotate(90)===true&&$ed2->get_size()==['width'=>20,'height'=>40]&&$ed2->flip(true,false)===true);
$ed3=WP_Image_Editor::get_instance($tmp.'/b.png');$mr=$ed3->multi_resize(['thumb'=>['width'=>20,'height'=>20,'crop'=>true],'breit'=>['width'=>80,'height'=>0],'leer'=>['width'=>0,'height'=>0]]);
t('WP_Image_Editor_GD::multi_resize',isset($mr['thumb'],$mr['breit'])&&!isset($mr['leer'])&&$mr['thumb']['width']===20&&$mr['thumb']['height']===20&&is_file($tmp.'/b-20x20.png')&&$mr['breit']['width']===80&&$mr['breit']['height']===48&&!isset($mr['thumb']['path'])&&$ed3->get_size()==['width'=>100,'height'=>60]);
$ed4=WP_Image_Editor::get_instance($tmp.'/b.png');$jp=$ed4->save($tmp.'/b.jpg','image/jpeg');
t('WP_Image_Editor_GD: JPEG',$jp['mime-type']==='image/jpeg'&&is_file($tmp.'/b.jpg')&&getimagesize($tmp.'/b.jpg')['mime']==='image/jpeg');
t('WP_Image_Editor: Dateiname',(function() use($tmp){ $e=WP_Image_Editor::get_instance($tmp.'/b.png');return $e->generate_filename('neu',null,'JPG')===$tmp.'/b-neu.jpg'&&$e->generate_filename(null,$tmp)===$tmp.'/b-100x60.png'; })());
file_put_contents($tmp.'/kein-bild.png','kein bild');t('WP_Image_Editor_GD: ungültiges Bild',WP_Image_Editor::get_instance($tmp.'/kein-bild.png')->get_error_code()==='invalid_image');

rm_rf($tmp);
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

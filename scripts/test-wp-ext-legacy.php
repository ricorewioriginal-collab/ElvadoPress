<?php
// Prüft die veralteten WordPress-Funktionen der Kompatibilitätsschicht (cms/wp/core/ext/legacy-*.php):
// Vorhandensein aller Listenfunktionen/-klassen, Hook deprecated_function_run, Delegation an Ersatzfunktionen, Randfälle. Aufruf: php scripts/test-wp-ext-legacy.php
$tmp=sys_get_temp_dir().'/elvado-xl-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/cms');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('ELVADO_WP_DATA',$tmp.'/cms/.wp');define('ELVADO_WP_CMS_DATA',$tmp.'/cms');define('ELVADO_WP_TEST',1);$_SERVER['HTTP_HOST']='example.test';
file_put_contents($tmp.'/cms/news.json',json_encode([['id'=>1,'slug'=>'erster','title'=>'Erster Beitrag','category'=>'News','status'=>'published','published_at'=>'2026-01-10 10:00:00','author'=>'Anna Autor','body_html'=>'<p>Hallo</p>']]));
file_put_contents($tmp.'/cms/site.json',json_encode(['comments'=>['enabled'=>true,'require_approval'=>true]]));
require __DIR__.'/_testdb.php';
require __DIR__.'/../cms/wp/load.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
elvado_wp_boot(['theme'=>false,'user'=>['id'=>1,'login'=>'admin','name'=>'Administration','email'=>'admin@example.test','role'=>'administrator']]);
$GLOBALS['elvado_wp_die_throws']=true;
function as_user(int $id,string $login,string $role): void { $GLOBALS['elvado_wp_user']=['id'=>$id,'login'=>$login,'name'=>$login,'email'=>$login.'@example.test','role'=>$role,'caps'=>elvado_wp_caps_for_role($role)]; }

/* ───────── Alle Funktionen und Klassen der Liste vorhanden ───────── */
$list=file('/tmp/claude-0/lists/G1-legacy.txt')?:[];
$names=[];$classes=[];
if(!$list){   // Liste nicht vorhanden (z. B. anderer Rechner): eingebaute Stichprobe
    $names=['get_postdata','get_settings','wp_login','is_taxonomy','_c','get_themes','wp_tinycolor_string_to_rgb','is_main_blog','get_userdatabylogin'];$classes=['WP_User_Search','WP_Privacy_Data_Export_Requests_Table'];
} else foreach($list as $l){ $p=explode("\t",trim($l)); if(count($p)<2)continue; if($p[0]==='C')$classes[]=$p[1]; else $names[]=$p[1]; }
$missing=array_filter($names,fn($f)=>!function_exists($f));$missingC=array_filter($classes,fn($c)=>!class_exists($c));
t('alle Funktionen der Liste vorhanden (keine Ausnahmen)',!$missing,implode(',',$missing));
t('alle Klassen der Liste vorhanden',!$missingC,implode(',',$missingC));

/* ───────── Hinweis-Hook ───────── */
$dep=[];add_action('deprecated_function_run',function($f,$r,$v) use(&$dep){ $dep[]=[$f,$r,$v]; },10,3);
t('get_settings liefert Option',(function() use(&$dep){ update_option('blogname','Testradio');$v=get_settings('blogname');return $v==='Testradio'&&end($dep)[0]==='get_settings'&&end($dep)[1]==='get_option()'&&end($dep)[2]==='2.1.0'; })());
t('Hinweis ohne WP_DEBUG still',(function(){ ob_start();elvado_ext_lg_dep('x_test','1.0','y()');return ob_get_clean()===''; })());

/* ───────── Autoren-Funktionen ───────── */
$bob=wp_create_user('bob','geheim-123','bob@example.test');
update_user_meta($bob,'first_name','Robert');update_user_meta($bob,'last_name','Baumeister');update_user_meta($bob,'description','Moderator');
t('get_the_author_firstname/lastname',get_the_author_firstname($bob)==='Robert'&&get_the_author_lastname($bob)==='Baumeister');
t('get_the_author_description und _login',get_the_author_description($bob)==='Moderator'&&get_the_author_login($bob)==='bob');
t('get_the_author_email / get_author_name',get_the_author_email($bob)==='bob@example.test'&&get_author_name($bob)==='bob');
t('get_the_author_ID',(int)get_the_author_ID($bob)===$bob);
t('get_usermeta / update_usermeta / delete_usermeta',(function() use($bob){
    update_usermeta($bob,'lieblingssender','Rock FM');$a=get_usermeta($bob,'lieblingssender');
    update_usermeta($bob,'lieblingssender','');$b=get_usermeta($bob,'lieblingssender');
    return $a==='Rock FM'&&$b==='';
})());
t('update_usermeta/delete_usermeta: nicht numerische ID',update_usermeta('x','k','v')===false&&delete_usermeta('x','k')===false);
t('get_usernumposts zählt Beiträge',get_usernumposts($bob)===0);
t('get_users_of_blog enthält user_id',(function() use($bob){ foreach(get_users_of_blog() as $u)if((int)$u->user_id===$bob)return true;return false; })());
t('create_user legt an',is_int(create_user('carla','geheim-123','carla@example.test')));
t('get_userdatabylogin / get_user_by_email',get_userdatabylogin('bob')->ID===$bob&&get_user_by_email('bob@example.test')->ID===$bob);
t('wp_login: richtig/falsch',wp_login('bob','geheim-123')===true&&wp_login('bob','nein')===false);
t('user_pass_ok',user_pass_ok('bob','geheim-123')&&!user_pass_ok('bob','x'));
t('set_current_user / get_currentuserinfo delegieren',set_current_user(1)->ID===wp_get_current_user()->ID&&get_currentuserinfo()->ID===wp_get_current_user()->ID);
t('get_profile liest Metafeld',get_profile('first_name','bob')==='Robert');

/* ───────── Rechte ───────── */
$boss=wp_insert_user(['user_login'=>'boss','user_pass'=>'geheim-123','user_email'=>'boss@example.test','role'=>'administrator']);
t('user_can_create_post für Administrator',user_can_create_post($boss)===true);
t('user_can_edit_user: Administrator darf',user_can_edit_user($boss,$bob)===true);
t('user_can_edit_user: Abonnent darf nicht',(function() use($bob){ $s=wp_create_user('sub','geheim-123','sub@example.test');return user_can_edit_user($s,$bob)===false&&user_can_create_post($s)===false; })());
t('get_author_user_ids enthält Administrator, get_nonauthor_user_ids nicht',in_array($boss,get_author_user_ids(),true)&&!in_array($boss,get_nonauthor_user_ids(),true));
t('get_editable_user_ids: Administrator sieht Autoren, Abonnent nur sich',in_array($boss,get_editable_user_ids($boss),true)&&in_array($bob,get_editable_user_ids($boss,false),true)&&get_editable_user_ids($bob)===[]&&get_editable_user_ids($bob,false)===[$bob]);
$ann=wp_insert_user(['user_login'=>'ann','user_pass'=>'geheim-123','user_email'=>'ann@example.test','role'=>'author']);

/* ───────── Text / Escaping ───────── */
t('clean_url / js_escape / attribute_escape / wp_specialchars',clean_url('http://example.test/a b')===esc_url('http://example.test/a b')&&js_escape("a'b")===esc_js("a'b")&&attribute_escape('"x"')===esc_attr('"x"')&&wp_specialchars('<b>')==='&lt;b&gt;');
t('clean_url Kontext db',clean_url('http://example.test/?a=1&b=2',null,'db')===esc_url_raw('http://example.test/?a=1&b=2'));
t('like_escape',like_escape('50%_x')==='50\\%\\_x');
t('addslashes_strings_only',addslashes_strings_only("o'k")==="o\\'k"&&addslashes_strings_only(5)===5);
t('popuplinks',popuplinks('<a href="/x">y</a>')==='<a href="/x" target=\'_blank\' rel=\'external\'>y</a>');
t('wp_kses_js_entities',wp_kses_js_entities('a&{alert(1)};b')==='ab');
t('_search_terms_tidy',_search_terms_tidy(" \"test'\n")==='test');
t('wp_htmledit_pre / wp_richedit_pre',wp_htmledit_pre('<p>"x"</p>')==='&lt;p&gt;"x"&lt;/p&gt;'&&str_contains(wp_richedit_pre('a'),'&lt;p&gt;a'));
t('clean_pre entfernt br',clean_pre(['','<pre>','a<br />b'])==='<pre>ab</pre>');
t('make_url_footnote',(function(){ $r=make_url_footnote('Siehe <a href="http://a.test/x">Link</a>.');return str_contains($r,'Link [1]')&&str_contains($r,'[1] http://a.test/x'); })());
t('funky_javascript_callback',funky_javascript_callback([0,'41'])==='&#65;');
t('_c / translate_with_context: Kontext nach „|“ entfernt',_c('Datei|Menü')==='Datei'&&translate_with_context('Plain')==='Plain'&&_nc('ein Hörer','Hörer',2)==='Hörer');
t('__ngettext_noop',(function(){ $r=__ngettext_noop('eins','viele');return $r['singular']==='eins'&&$r['plural']==='viele'; })());
t('wp_convert_bytes_to_hr',wp_convert_bytes_to_hr(2048)==='2KB'&&wp_convert_bytes_to_hr(0)==='0B'&&wp_convert_bytes_to_hr(1048576)==='1MB');
t('wp_blacklist_check delegiert',wp_blacklist_check('a','a@b.test','','Hallo','127.0.0.1','x')===false);
t('wp_unregister_GLOBALS und debug_f*',wp_unregister_GLOBALS()===null&&debug_fopen('x','r')===false);
t('default_topic_count_text / format_to_post',default_topic_count_text(1234)===number_format_i18n(1234)&&format_to_post('x')==='x');

/* ───────── Taxonomie / Kategorien ───────── */
t('is_taxonomy / is_term',is_taxonomy('category')&&!is_taxonomy('gibtsnicht')&&!is_term('Gibt es nicht','category'));
$cat=wp_insert_term('Musik','category');$sub=wp_insert_term('Rock','category',['parent'=>$cat['term_id']]);
t('get_all_category_ids enthält neue Kategorie',in_array((int)$cat['term_id'],get_all_category_ids(),true));
t('get_category_children als Zeichenkette',get_category_children($cat['term_id'])==='/'.$sub['term_id']&&get_category_children(0)==='');
t('get_catname',get_catname($cat['term_id'])==='Musik');
t('_usort_terms_by_ID / _name',_usort_terms_by_ID((object)['term_id'=>1],(object)['term_id'=>2])===-1&&_usort_terms_by_name((object)['name'=>'b'],(object)['name'=>'a'])>0);
t('is_plugin_page / wp_timezone_supported / update_category_cache',!is_plugin_page()&&wp_timezone_supported()&&update_category_cache()===true);

/* ───────── Beiträge ───────── */
$pid=wp_insert_post(['post_title'=>'Alt','post_content'=>'Text','post_status'=>'publish','post_author'=>$bob]);
t('get_postdata',(function() use($pid,$bob){ $d=get_postdata($pid);return $d['ID']===$pid&&$d['Title']==='Alt'&&(int)$d['Author_ID']===$bob; })());
t('wp_get_single_post',wp_get_single_post($pid)->post_title==='Alt');
t('wp_get_post_cats / wp_set_post_cats',(function() use($pid,$cat){ wp_set_post_cats('1',$pid,[$cat['term_id']]);return in_array((int)$cat['term_id'],wp_get_post_cats('1',$pid)); })());
t('the_category_ID liefert ID',(function() use($pid,$cat){ $GLOBALS['post']=get_post($pid);setup_postdata($GLOBALS['post']);return (int)the_category_ID(false)===(int)$cat['term_id']; })());
t('get_usernumposts nach Beitrag',get_usernumposts($bob)===1);
t('get_others_drafts ohne Entwürfe leer',(function() use($boss){ return get_others_drafts($boss)===[]; })());
wp_insert_post(['post_title'=>'Entwurf','post_content'=>'x','post_status'=>'draft','post_author'=>$ann]);
t('get_others_drafts findet fremden Entwurf',(function() use($boss){ $r=get_others_drafts($boss);return is_array($r)&&count($r)===1&&$r[0]->post_title==='Entwurf'; })());
t('get_post_to_edit',get_post_to_edit($pid)->ID===$pid);
t('_relocate_children setzt Eltern um',(function() use($pid){
    $c=wp_insert_post(['post_title'=>'Kind','post_content'=>'x','post_status'=>'publish','post_parent'=>$pid]);
    $p2=wp_insert_post(['post_title'=>'Neu','post_content'=>'x','post_status'=>'publish']);
    _relocate_children($pid,$p2);clean_post_cache($c);return (int)get_post($c)->post_parent===$p2;
})());
t('sticky_class ohne Sticky leer',(function() use($pid){ ob_start();sticky_class($pid);return ob_get_clean()===''; })());

/* ───────── Links, Feeds, Rel-Links ───────── */
t('Link-Funktionen liefern leere Standardwerte',get_linkobjects()===[]&&wp_get_links()===''&&get_autotoggle()===0&&get_link(1)===null&&get_linkcatname(1)===''&&get_linkrating((object)['link_rating'=>3])===3);
t('get_index_rel_link',str_contains(get_index_rel_link(),"rel='index'"));
t('get_category_rss_link',str_contains(get_category_rss_link(false,$cat['term_id']),'feed'));
t('get_author_link echo/Rückgabe',(function() use($bob){ ob_start();$l=get_author_link(true,$bob);return ob_get_clean()===$l&&$l!==''; })());

/* ───────── Themes / Widgets / Hilfen ───────── */
t('get_themes / get_current_theme',is_array(get_themes())&&is_string(get_current_theme()));
t('get_theme unbekannt',get_theme('gibtsnicht')===null);
t('automatic_feed_links schaltet Theme-Unterstützung',(function(){ automatic_feed_links(true);$a=current_theme_supports('automatic-feed-links');automatic_feed_links(false);return $a&&!current_theme_supports('automatic-feed-links'); })());
t('add_custom_background / remove_custom_background',(function(){ add_custom_background();$a=current_theme_supports('custom-background');remove_custom_background();return $a&&!current_theme_supports('custom-background'); })());
t('register_sidebar_widget / unregister_sidebar_widget melden Hinweis',(function() use(&$dep){ $c=count($dep);register_sidebar_widget('Alt Widget','__return_empty_string');unregister_sidebar_widget('alt-widget');return count($dep)===$c+2&&$dep[$c][0]==='register_sidebar_widget'; })());
t('wp_no_robots / wp_sensitive_page_meta',(function(){ ob_start();wp_no_robots();wp_sensitive_page_meta();$o=ob_get_clean();return str_contains($o,'noindex')&&str_contains($o,'noarchive')&&str_contains($o,'strict-origin'); })());
t('get_comments_popup_template ohne Datei leer',get_comments_popup_template()==='');
t('wp_explain_nonce',is_string(wp_explain_nonce('x')));
t('url-Funktion get_shortcut_link per Filter',(function(){ add_filter('shortcut_link',$f=fn()=>'x');$r=get_shortcut_link();remove_filter('shortcut_link',$f);return $r==='x'; })());
t('Press-This-Handler melden Fehler (JSON)',(function(){ ob_start();try{ wp_ajax_press_this_save_post(); }catch(ELVADO_WP_Die $e){} $o=ob_get_clean();return str_contains($o,'Press This'); })());

/* ───────── Anhänge / Bilder ───────── */
$f=$tmp.'/b.png';file_put_contents($f,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
t('wp_load_image: Datei, Fehler',(function() use($f){ $i=wp_load_image($f);return (is_object($i)||is_resource($i))&&is_string(wp_load_image($f.'.nein')); })());
t('gd_edit_image_support',gd_edit_image_support('image/png')===(bool)(function_exists('imagetypes')&&(imagetypes()&IMG_PNG))&&gd_edit_image_support('text/plain')===false);
t('wp_get_attachment_thumb_file ohne Anhang',wp_get_attachment_thumb_file(999999)===false);
t('get_the_attachment_link ohne Anhang',get_the_attachment_link(999999)===__('Missing Attachment'));
t('wp_shrink_dimensions / get_udims',get_udims(1000,500)===wp_constrain_dimensions(1000,500,128,96)&&wp_shrink_dimensions(1000,500,64,64)===[64,32]);

/* ───────── Block-/Duotone-/Farbhelfer ───────── */
t('wp_tinycolor_string_to_rgb: Hex',wp_tinycolor_string_to_rgb('#ff8000')===['r'=>255,'g'=>128,'b'=>0,'a'=>1]&&wp_tinycolor_string_to_rgb('#f00')['r']===255);
t('wp_tinycolor_string_to_rgb: rgb/rgba/transparent',(function(){ $a=wp_tinycolor_string_to_rgb('rgba(10, 20, 30, 0.5)');return round($a['r'])===10.0&&round($a['b'])===30.0&&$a['a']===0.5&&wp_tinycolor_string_to_rgb('transparent')['a']===0; })());
t('wp_tinycolor_string_to_rgb: hsl',(function(){ $a=wp_tinycolor_string_to_rgb('hsl(0, 100%, 50%)');return round($a['r'])==255.0&&round($a['g'])==0.0; })());
t('wp_tinycolor_bound01',wp_tinycolor_bound01(50,100)===0.5&&wp_tinycolor_bound01('100%',255)===1.0&&wp_tinycolor_bound01(-5,100)===0.0);
t('_wp_tinycolor_bound_alpha',_wp_tinycolor_bound_alpha('0.3')===0.3&&_wp_tinycolor_bound_alpha(5)===1);
t('Duotone-ID/-Eigenschaft',wp_get_duotone_filter_id(['slug'=>'blau'])==='wp-duotone-blau'&&wp_get_duotone_filter_property(['slug'=>'blau'])==="url('#wp-duotone-blau')"&&wp_get_duotone_filter_property(['colors'=>'grayscale(1)'])==='grayscale(1)');
t('Duotone-SVG',(function(){ $s=wp_get_duotone_filter_svg(['slug'=>'rb','colors'=>['#000000','#ffffff']]);return str_contains($s,'id="wp-duotone-rb"')&&str_contains($s,'tableValues="0 1"'); })());
t('Duotone-Support registriert Attribut „style“',(function(){ $bt=new stdClass;$bt->supports=['filter'=>['duotone'=>true]];$bt->attributes=[];wp_register_duotone_support($bt);return isset($bt->attributes['style']); })());
t('wp_skip_*_serialization',(function(){ $bt=(object)['supports'=>['spacing'=>['__experimentalSkipSerialization'=>true],'__experimentalBorder'=>['__experimentalSkipSerialization'=>true]]];return wp_skip_spacing_serialization($bt)&&wp_skip_border_serialization($bt)&&!wp_skip_dimensions_serialization($bt); })());
t('Theme-Attribut in Template-Teilen',(function(){
    $in='<!-- wp:template-part {"slug":"header"} /-->';
    $out=_inject_theme_attribute_in_block_template_content($in);
    return str_contains($out,'"theme"')&&!str_contains(_remove_theme_attribute_in_block_template_content($out),'"theme"');
})());
t('wp_create_block_style_variation_instance_name',(function(){ $r=wp_create_block_style_variation_instance_name(['blockName'=>'x'],'dunkel');return str_starts_with($r,'dunkel--')&&strlen($r)===8+32; })());
t('wp_img_tag_add_loading_attr / decoding',(function(){ $i='<img src="a.png" />';$d=wp_img_tag_add_decoding_attr($i,'the_content');return str_contains($d,'decoding="async"')&&wp_img_tag_add_decoding_attr($d,'x')===$d&&str_contains(wp_img_tag_add_loading_attr('<img src="a.png" loading="eager" />','x'),'eager'); })());
t('wp_typography_get_css_variable_inline_style',wp_typography_get_css_variable_inline_style(['style'=>['typography'=>['fontFamily'=>'var:preset|font-family|sans-serif']]],'fontFamily','font-family')==='font-family: var(--wp--preset--font-family--sans-serif);'&&wp_typography_get_css_variable_inline_style([],'fontFamily','font-family')===null);
t('block_core_navigation_submenu_build_css_colors',(function(){ $c=block_core_navigation_submenu_build_css_colors(['textColor'=>'rot','customBackgroundColor'=>'#123456'],[]);return in_array('has-rot-color',$c['css_classes'],true)&&str_contains($c['inline_styles'],'background-color: #123456'); })());
t('_excerpt_render_inner_columns_blocks',_excerpt_render_inner_columns_blocks(['innerBlocks'=>[]],['core/paragraph'])==='');
t('_resolve_home_block_template mit statischer Startseite',(function(){ update_option('show_on_front','page');update_option('page_on_front',77);$r=_resolve_home_block_template();update_option('show_on_front','posts');return $r===['postType'=>'page','postId'=>77]; })());
t('Interaktivität/Elemente/Duotone-Render geben durch',wp_render_elements_support('<p>x</p>',[])==='<p>x</p>'&&wp_render_duotone_support('a',[])==='a'&&wp_interactivity_process_directives_of_interactive_blocks('t')==='t');

/* ───────── Admin-Reste ───────── */
t('screen_icon / get_screen_icon',str_contains(get_screen_icon(),'icon32')&&(function(){ ob_start();screen_icon();return str_contains(ob_get_clean(),'icon32'); })());
t('add_contextual_help speichert Hilfetext',(function(){ add_contextual_help('seite','Hilfe');return ($GLOBALS['_wp_contextual_help']['seite']??'')==='Hilfe'; })());
t('screen_options/layout/favorite_actions leer',screen_options('x')===''&&screen_layout('x')===''&&favorite_actions()==='');
t('add_object_page / add_utility_page',(function(){ add_object_page('T','T','read','legacy-obj','__return_true');add_utility_page('U','U','read','legacy-util','__return_true');return true; })());
t('wp_update_plugin ohne Upgrader: WP_Error',class_exists('Plugin_Upgrader')||is_wp_error(wp_update_plugin('x/x.php')));
t('get_real_file_to_edit',str_starts_with(get_real_file_to_edit('themes/x.css'),WP_CONTENT_DIR));
t('the_attachment_links ohne Anhang still',(function(){ ob_start();the_attachment_links(999999);return ob_get_clean()===''; })());
t('Dashboard-Reste ohne Ausgabe',(function(){ ob_start();wp_dashboard_secondary();wp_dashboard_plugins();wp_tiny_mce();wp_quicktags();return ob_get_clean()===''; })());

/* ───────── Alte Benutzerliste ───────── */
t('WP_User_Search findet Benutzer',(function() use($bob){ $s=new WP_User_Search('bob');return in_array($bob,$s->get_results())&&$s->is_search()&&!$s->results_are_paged(); })());
t('WP_User_Search ohne Treffer: Fehlerobjekt',(function(){ $s=new WP_User_Search('zzzgibtsnicht');return $s->get_results()===[]&&is_wp_error($s->search_errors); })());
t('Datenschutz-Tabellen: Klassen instanziierbar',(function(){ $a=new WP_Privacy_Data_Export_Requests_Table();$b=new WP_Privacy_Data_Removal_Requests_Table();return $a instanceof WP_Privacy_Data_Export_Requests_Table&&$b instanceof WP_Privacy_Data_Removal_Requests_Table; })());
t('Datenschutz-Tabellen: prepare_items ohne Anfragen leer',(function(){ $a=new WP_Privacy_Data_Export_Requests_Table();$a->prepare_items();return $a->items===[]; })());
t('Basisklasse passt zur Umgebung',class_exists('WP_List_Table')===(new WP_Privacy_Data_Export_Requests_Table() instanceof WP_List_Table));

/* ───────── Multisite-Reste (Einzelseite) ───────── */
t('is_main_blog / is_site_admin / validate_email',is_main_blog()&&is_site_admin()&&validate_email('a@b.test')&&!validate_email('kein'));
t('get_blog_list liefert genau eine Website',(function(){ $l=get_blog_list(0,10);return count($l)===1&&$l[0]['blog_id']===1&&get_blog_list(5,10)===[]; })());
t('wp_get_sites',count(wp_get_sites())===1&&wp_get_sites(['offset'=>1])===[]);
t('get_most_active_blogs mit Ausgabe',(function(){ ob_start();$b=get_most_active_blogs(5,true);$o=ob_get_clean();return count($b)===1&&str_contains($o,'<li>'); })());
t('get_user_id_from_string',get_user_id_from_string('bob')===$bob&&get_user_id_from_string('bob@example.test')===$bob&&get_user_id_from_string('42')===42&&get_user_id_from_string('niemand')===0);
t('wpmu_admin_redirect_add_updated_param',wpmu_admin_redirect_add_updated_param('a.php')==='a.php?updated=true'&&wpmu_admin_redirect_add_updated_param('a.php?x=1')==='a.php?x=1&updated=true'&&wpmu_admin_redirect_add_updated_param('a.php?updated=true')==='a.php?updated=true');
t('create_empty_blog / insert_blog legen nichts an',is_wp_error(create_empty_blog('a.test','/','T'))&&insert_blog('a.test','/',1)===false);
t('Netzwerk-Plugins/-Themes leer',!is_wpmu_sitewide_plugin('a/a.php')&&activate_sitewide_plugin()===false&&get_site_allowed_themes()===[]&&wpmu_get_blog_allowedthemes()===[]);
t('global_terms / global_terms_enabled / mu_options',global_terms(5)===5&&global_terms_enabled()===false&&mu_options(['a'])===['a']);
t('sync_category_tag_slugs unverändert ohne globale Begriffe',(function(){ $t=(object)['name'=>'Eins','slug'=>'x'];return sync_category_tag_slugs($t,'category')->slug==='x'; })());
t('update_user_status setzt Spalte',(function() use($bob){ global $wpdb;update_user_status($bob,'user_status',3);return (int)$wpdb->get_var($wpdb->prepare("SELECT user_status FROM {$wpdb->users} WHERE ID = %d",$bob))===3; })());
t('is_user_option_local ohne Option false',is_user_option_local('gibtsnicht')===false);
t('get_dashboard_blog',is_object(get_dashboard_blog()));
t('get_blogaddress_by_domain',str_contains(get_blogaddress_by_domain('x.test','/blog/'),'x.test/blog/'));
t('generate_random_password Länge',strlen(generate_random_password(12))===12);

echo $fail===0?"OK: $n Prüfungen bestanden\n":"$fail von $n Prüfungen fehlgeschlagen\n";
exit($fail===0?0:1);

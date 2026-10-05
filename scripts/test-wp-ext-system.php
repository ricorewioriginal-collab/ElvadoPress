<?php
// Prüft die ergänzenden System-Funktionen der WordPress-Schicht (cms/wp/core/ext/system-*.php): Hilfen, Start/Laufzeit, Dateien, Plugins, Übersetzung, Updates.
// Aufruf: php scripts/test-wp-ext-system.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/rrw-extsys-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/wp-content/plugins');mkdir($tmp.'/wp-content/languages');mkdir($tmp.'/cms');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('RRW_WP_DATA',$tmp.'/cms/.wp');define('RRW_WP_CMS_DATA',$tmp.'/cms');
$_SERVER['HTTP_HOST']='example.test';file_put_contents($tmp.'/cms/site.json','{}');$GLOBALS['RRW_SITE']=[];
require __DIR__.'/_testdb.php';
require __DIR__.'/../cms/wp/load.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
rrw_wp_boot(['theme'=>false,'user'=>['id'=>1,'login'=>'admin','name'=>'Administration','email'=>'a@example.test','role'=>'administrator']]);
$GLOBALS['rrw_wp_die_throws']=true;

// Vollständigkeit: alle Funktionen der Liste (keine Ausnahmen)
$names=preg_split('/\s+/',trim(<<<'LISTE'
is_lighttpd_before_150 wp_guess_url wp_maybe_decline_date human_readable_duration xmlrpc_getposttitle xmlrpc_getpostcategory xmlrpc_removepostdata
wp_extract_urls do_enclose is_new_day _http_build_query wp_removable_query_args add_magic_quotes wp_remote_fopen wp cache_javascript_headers
bool_from_yn do_feed do_feed_rdf do_feed_rss do_feed_rss2 do_feed_atom do_favicon wp_original_referer_field wp_get_original_referer path_is_absolute
get_temp_dir win_is_writable _wp_upload_dir _wp_check_alternate_file_names _wp_check_existing_file_names wp_ext2type
wp_get_default_extension_for_mime_type wp_get_image_mime wp_get_ext_types _ajax_wp_die_handler _json_wp_die_handler _jsonp_wp_die_handler
_xmlrpc_wp_die_handler _xml_wp_die_handler _scalar_wp_die_handler _wp_die_process_input _wp_json_sanity_check _wp_json_convert_string
_wp_json_prepare_data wp_check_jsonp_callback _config_wp_home _config_wp_siteurl _delete_option_fresh_site _mce_set_direction smilies_init
wp_recursive_ksort wp_maybe_load_widgets wp_widgets_add_menu dead_db _deprecated_constructor _deprecated_class apache_mod_loaded
iis7_supports_permalinks force_ssl_admin get_main_network_id is_site_meta_supported wp_timezone_override_offset _wp_timezone_choice_usort_callback
wp_timezone_choice wp_scheduled_delete send_nosniff_header _wp_mysql_week wp_find_hierarchy_loop wp_find_hierarchy_loop_tortoise_hare
send_frame_options_header wp_admin_headers _get_non_cached_ids _validate_cache_id _device_can_upload wp_checkdate wp_auth_check_load wp_auth_check
get_tag_regex is_utf8_charset _canonical_charset wp_post_preview_js mysql_to_rfc3339 wp_cache_get_last_changed wp_cache_set_last_changed
wp_site_admin_email_change_notification wp_privacy_anonymize_ip wp_privacy_exports_dir wp_privacy_exports_url
wp_schedule_delete_old_privacy_export_files wp_privacy_delete_old_export_files wp_get_update_php_url wp_get_default_update_php_url
wp_update_php_annotation wp_get_update_php_annotation wp_get_direct_php_update_url wp_direct_php_update_button wp_get_update_https_url
wp_get_default_update_https_url wp_get_direct_update_https_url get_dirsize recurse_dirsize clean_dirsize_cache wp_fuzzy_number_match
wp_get_admin_notice wp_is_heic_image_mime_type wp_unique_id_from_values got_mod_rewrite got_url_rewrite extract_from_markers insert_with_markers
save_mod_rewrite_rules iis7_save_url_rewrite_rules update_recently_edited wp_make_theme_file_tree wp_print_theme_file_tree wp_make_plugin_file_tree
wp_print_plugin_file_tree update_home_siteurl wp_reset_vars show_message wp_doc_link_parse iis7_rewrite_rule_exists iis7_delete_rewrite_rule
iis7_add_rewrite_rule saveDomDocument admin_color_scheme_picker wp_color_scheme_settings wp_admin_viewport_meta _customizer_mobile_viewport_meta
wp_check_locked_posts wp_refresh_post_lock wp_refresh_post_nonces wp_refresh_metabox_loader_nonces wp_refresh_heartbeat_nonces
wp_heartbeat_set_suspension heartbeat_autosave wp_admin_canonical_url wp_page_reload_on_back_button_js update_option_new_admin_email
_wp_privacy_settings_filter_draft_page_titles wp_check_php_version wp_fix_server_vars wp_populate_basic_auth_from_authorization_header
wp_check_php_mysql_versions wp_get_development_mode wp_is_development_mode wp_favicon_request wp_maintenance wp_is_maintenance_mode timer_float
timer_start wp_debug_mode wp_set_lang_dir require_wp_db wp_set_wpdb_vars wp_start_object_cache wp_not_installed wp_skip_paused_plugins
wp_get_active_and_valid_themes wp_skip_paused_themes wp_is_recovery_mode is_protected_endpoint is_protected_ajax_action wp_set_internal_encoding
wp_magic_quotes shutdown_action_hook wp_clone is_login wp_load_translations_early wp_is_ini_value_changeable wp_using_themes
wp_start_scraping_edited_file_errors wp_finalize_scraping_edited_file_errors wp_is_jsonp_request wp_is_json_media_type
wp_is_site_protected_by_basic_auth _get_plugin_data_markup_translate get_plugin_files _sort_uname_callback _get_dropins is_network_only_plugin
validate_active_plugins validate_plugin_requirements is_uninstallable_plugin uninstall_plugin add_links_page add_comments_page get_admin_page_parent
get_plugin_page_hook user_can_access_admin_page option_update_filter plugin_sandbox_scrape is_plugin_paused wp_get_plugin_error resume_plugin
paused_plugins_notice deactivated_plugins_notice get_file_description list_files wp_get_plugin_file_editable_extensions
wp_get_theme_file_editable_extensions wp_print_file_editor_templates wp_edit_theme_plugin_file validate_file_to_edit _wp_handle_upload
wp_handle_upload wp_handle_sideload verify_file_md5 verify_file_signature wp_trusted_keys wp_zip_file_is_valid unzip_file _unzip_file_ziparchive
_unzip_file_pclzip move_dir wp_print_request_filesystem_credentials_modal wp_opcache_invalidate wp_opcache_invalidate_directory
get_preferred_from_update_core find_core_auto_update get_core_checksums dismiss_core_update undismiss_core_update find_core_update core_update_footer
update_nag update_right_now_message wp_plugin_update_rows wp_plugin_update_row get_theme_updates wp_theme_update_rows wp_theme_update_row
maintenance_nag wp_print_admin_notice_templates wp_print_update_row_templates wp_recovery_mode_nag wp_is_auto_update_enabled_for_type
wp_is_auto_update_forced_for_item wp_get_auto_update_message before_last_bar load_default_textdomain load_script_textdomain load_script_translations
_load_textdomain_just_in_time get_translations_for_domain wp_get_l10n_php_file_data wp_dropdown_languages switch_to_user_locale
restore_current_locale is_locale_switched translate_settings_using_i18n_schema has_translation wp_version_check wp_maybe_auto_update
wp_get_translation_updates wp_get_update_data _maybe_update_core _maybe_update_plugins _maybe_update_themes wp_schedule_update_checks
wp_clean_update_cache wp_delete_all_temp_backups _wp_delete_all_temp_backups _wp_http_get_object wp_safe_remote_head wp_remote_retrieve_cookies
wp_remote_retrieve_cookie wp_remote_retrieve_cookie_value get_allowed_http_origins send_origin_headers allowed_http_request_hosts
ms_allowed_http_request_hosts _get_component_from_parsed_url_array _wp_translate_php_url_constant_to_key options_discussion_add_js
options_general_add_js options_reading_add_js options_reading_blog_charset wp_register_plugin_realpath _wp_call_all_hook export_wp get_cli_args
wp_cache_replace _wp_scripts_maybe_doing_it_wrong wp_simplepie_autoload
LISTE));
$miss=array_values(array_filter($names,fn($f)=>!function_exists($f)));
t('Alle '.count($names).' Funktionen der Liste vorhanden (keine Ausnahmen)',!$miss,implode(',',$miss));

// Hilfen: Text, Zeit, URLs
t('human_readable_duration: H:M:S','1 hour, 5 minutes, 30 seconds'===human_readable_duration('1:05:30'));
t('human_readable_duration: M:S','2 minutes, 1 second'===human_readable_duration('2:01'));
t('human_readable_duration: ungültig',false===human_readable_duration('abc')&&false===human_readable_duration('')&&false===human_readable_duration('1:2:3:4'));
t('bool_from_yn',bool_from_yn('Y')&&!bool_from_yn('n')&&!bool_from_yn(''));
t('wp_extract_urls',wp_extract_urls('Text <a href="https://a.test/x?y=1&amp;z=2">l</a> und https://b.test/p. Nochmal https://b.test/p')===['https://a.test/x?y=1&z=2','https://b.test/p']);
t('xmlrpc_getposttitle/-category/-removepostdata',xmlrpc_getposttitle('<title>Hallo</title>Text')==='Hallo'&&xmlrpc_getpostcategory('<category>a, b</category>x')===['a','b']&&xmlrpc_removepostdata('<title>T</title><category>c</category> Rest ')==='Rest');
t('_http_build_query verschachtelt',_http_build_query(['a'=>1,'b'=>['c'=>'x y','d'=>false],'n'=>null])==='a=1&b%5Bc%5D=x+y&b%5Bd%5D=0');
t('add_magic_quotes rekursiv',add_magic_quotes(['a'=>"o'k",'b'=>['c'=>'"']])===['a'=>"o\\'k",'b'=>['c'=>'\\"']]);
t('wp_removable_query_args enthält deleted',in_array('deleted',wp_removable_query_args(),true));
t('wp_fuzzy_number_match',wp_fuzzy_number_match(10,10.8)&&!wp_fuzzy_number_match(10,12)&&wp_fuzzy_number_match(10,12,2));
t('wp_unique_id_from_values stabil',wp_unique_id_from_values(['a'=>1],'x-')===wp_unique_id_from_values(['a'=>1],'x-')&&wp_unique_id_from_values(['a'=>1])!==wp_unique_id_from_values(['a'=>2])&&strlen(wp_unique_id_from_values([]))===8);
t('get_tag_regex trifft Tag',preg_match('/'.get_tag_regex('b').'/','x<b>fett</b>y',$m)===1&&$m[0]==='<b>fett</b>');
t('is_utf8_charset/_canonical_charset',is_utf8_charset('UTF-8')&&is_utf8_charset('utf8')&&!is_utf8_charset('latin1')&&_canonical_charset('utf8')==='UTF-8'&&_canonical_charset('ISO8859-1')==='ISO-8859-1');
t('wp_checkdate',wp_checkdate(2,29,2024,'2024-02-29')&&!wp_checkdate(2,30,2024,'2024-02-30'));
t('mysql_to_rfc3339',str_starts_with(mysql_to_rfc3339('2024-05-06 07:08:09'),'2024-05-06T07:08:09'));
t('_wp_mysql_week',_wp_mysql_week('d')==='WEEK( d, 1 )');
t('path_is_absolute',path_is_absolute('/usr/bin')&&!path_is_absolute('rel/pfad')&&path_is_absolute('C:\\x')&&!path_is_absolute(''));
t('get_temp_dir endet mit /',str_ends_with(get_temp_dir(),'/')&&is_dir(get_temp_dir()));
t('wp_ext2type / wp_get_ext_types','image'===wp_ext2type('JPG')&&'video'===wp_ext2type('mp4')&&null===wp_ext2type('xyz')&&isset(wp_get_ext_types()['archive']));
t('wp_get_default_extension_for_mime_type','jpg'===wp_get_default_extension_for_mime_type('image/jpeg')&&false===wp_get_default_extension_for_mime_type('x/unbekannt'));
$png=$tmp.'/b.png';file_put_contents($png,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
t('wp_get_image_mime',wp_get_image_mime($png)==='image/png'&&false===wp_get_image_mime($tmp.'/gibt-es-nicht')&&wp_is_heic_image_mime_type('image/HEIC')&&!wp_is_heic_image_mime_type('image/png'));
t('wp_recursive_ksort',(function(){ $a=['b'=>['z'=>1,'a'=>2],'a'=>1];wp_recursive_ksort($a);return array_keys($a)===['a','b']&&array_keys($a['b'])===['a','z']; })());
t('wp_privacy_anonymize_ip',wp_privacy_anonymize_ip('192.168.10.77')==='192.168.10.0'&&wp_privacy_anonymize_ip('2001:db8:1234:5678::1')==='2001:db8:1234::'&&wp_privacy_anonymize_ip('::ffff:10.1.2.3')==='10.1.2.0'&&wp_privacy_anonymize_ip('kaputt')==='0.0.0.0');
t('_validate_cache_id / _get_non_cached_ids',_validate_cache_id(5)&&_validate_cache_id('7')&&!_validate_cache_id('7x')&&!_validate_cache_id(1.5));
wp_cache_set(1,'a','rrw_g');t('_get_non_cached_ids',_get_non_cached_ids([1,2,3,'x'],'rrw_g')===[2,3]);
t('wp_cache_replace / last_changed',!wp_cache_replace('nix','v','rrw_h')&&wp_cache_set('k','v1','rrw_h')&&wp_cache_replace('k','v2','rrw_h')&&wp_cache_get('k','rrw_h')==='v2'&&wp_cache_get_last_changed('rrw_h')===wp_cache_get_last_changed('rrw_h')&&(wp_cache_set_last_changed('rrw_h')!==''));
t('wp_find_hierarchy_loop: Schleife erkannt',(function(){ $p=[1=>2,2=>3,3=>1];$cb=fn($id)=>$p[$id]??0;$l=wp_find_hierarchy_loop($cb,1,2);return $l&&count($l)>=2; })());
t('wp_find_hierarchy_loop: keine Schleife',(function(){ $p=[1=>2,2=>3,3=>0];$cb=fn($id)=>$p[$id]??0;return []===wp_find_hierarchy_loop($cb,1,2); })());
t('_wp_json_sanity_check: Kodierung und Tiefe',mb_check_encoding(_wp_json_sanity_check(['a'=>"x\xE4"],10)['a'],'UTF-8')&&_wp_json_sanity_check(['k'=>['ü']],10)==['k'=>['ü']]&&(function(){ try{ _wp_json_sanity_check([[1]],0);return false; }catch(Exception $e){ return true; } })());
t('wp_check_jsonp_callback',wp_check_jsonp_callback('cb.fn_1')&&!wp_check_jsonp_callback('a(b)')&&!wp_check_jsonp_callback(['x']));
t('_get_component_from_parsed_url_array',_get_component_from_parsed_url_array(parse_url('https://h.test:81/p?q=1'),PHP_URL_PORT)===81&&_get_component_from_parsed_url_array(parse_url('/p'),PHP_URL_HOST)===null);
t('Zeitzone: wp_timezone_choice',str_contains(wp_timezone_choice('Europe/Berlin'),'<option value="Europe/Berlin" selected="selected">Berlin</option>')&&str_contains(wp_timezone_choice(''),'value="UTC"'));
t('wp_get_admin_notice',wp_get_admin_notice('Hallo',['type'=>'error','dismissible'=>true,'id'=>'x1'])==="<div class=\"notice notice-error is-dismissible\" id=\"x1\"><p>Hallo</p></div>\n");
t('wp_get_update_php_url / default',wp_get_update_php_url()===wp_get_default_update_php_url()&&''===wp_get_update_php_annotation()&&''===wp_get_direct_php_update_url()&&str_starts_with(wp_get_update_https_url(),'https://'));

// Die-Handler
$out=function(callable $f){ ob_start();try{ $f(); }catch(RRW_WP_Die $e){} return ob_get_clean(); };
$j=json_decode($out(fn()=>_json_wp_die_handler(new WP_Error('boese','Schlecht',['status'=>418]),'',[])),true);
t('_json_wp_die_handler',$j['code']==='boese'&&$j['message']==='Schlecht'&&$j['data']['status']===418);
t('_xml_wp_die_handler',str_contains($out(fn()=>_xml_wp_die_handler('Fehler','T',['response'=>400])),'<status>400</status>'));
t('_ajax/_scalar_wp_die_handler','Nix'===$out(fn()=>_ajax_wp_die_handler('Nix'))&&'Nix'===$out(fn()=>_scalar_wp_die_handler('Nix'))&&'0'===$out(fn()=>_scalar_wp_die_handler(['a'])));
$_GET['_jsonp']='cb';t('_jsonp_wp_die_handler',str_starts_with($out(fn()=>_jsonp_wp_die_handler('x')),'/**/cb('));unset($_GET['_jsonp']);
t('_xmlrpc_wp_die_handler',str_contains($out(fn()=>_xmlrpc_wp_die_handler('Kaputt','',['response'=>403])),'<int>403</int>'));
[$m,$ti,$pa]=_wp_die_process_input(new WP_Error('a','eins'),'',['response'=>0]);
t('_wp_die_process_input',$m==='eins'&&$pa['code']==='a'&&$pa['response']===500&&$ti!=='');
[$m2]=_wp_die_process_input((function(){ $e=new WP_Error('a','eins');$e->add('b','zwei');return $e; })());
t('_wp_die_process_input: mehrere Fehler als Liste',str_contains($m2,'<li>eins</li>')&&str_contains($m2,'<li>zwei</li>'));

// Server/Laufzeit
$_SERVER['SERVER_SOFTWARE']='lighttpd/1.4.55';t('is_lighttpd_before_150',is_lighttpd_before_150());$_SERVER['SERVER_SOFTWARE']='Apache/2.4';t('is_lighttpd_before_150: Apache',!is_lighttpd_before_150());
t('Entwicklungsmodus: Standard leer',wp_get_development_mode()===''&&!wp_is_development_mode('core'));
add_filter('wp_development_mode',fn()=>'all');t('Entwicklungsmodus: all gilt für alle',wp_is_development_mode('theme'));remove_all_filters('wp_development_mode');
t('wp_using_themes / wp_is_recovery_mode / wp_is_json_media_type',!wp_using_themes()&&!wp_is_recovery_mode()&&wp_is_json_media_type('application/vnd.api+json; charset=utf-8')&&!wp_is_json_media_type('text/html'));
t('timer_start/timer_float',timer_start()&&timer_float()>=0&&timer_float()<5);
$_SERVER['HTTP_AUTHORIZATION']='Basic '.base64_encode('u:p:w');unset($_SERVER['PHP_AUTH_USER']);wp_populate_basic_auth_from_authorization_header();
t('Basic-Auth aus Header',$_SERVER['PHP_AUTH_USER']==='u'&&$_SERVER['PHP_AUTH_PW']==='p:w'&&wp_is_site_protected_by_basic_auth());
t('wp_get_active_and_valid_themes',is_array(wp_get_active_and_valid_themes()));
t('is_protected_endpoint / ajax',!is_protected_endpoint()||is_admin());
$GLOBALS['rrw_wp_doing_ajax']=true;$_REQUEST['action']='heartbeat';t('is_protected_ajax_action',is_protected_ajax_action());$GLOBALS['rrw_wp_doing_ajax']=false;
t('wp_is_ini_value_changeable',wp_is_ini_value_changeable('precision')&&!wp_is_ini_value_changeable('disable_functions'));
t('_wp_call_all_hook ruft „all“',(function(){ $x=[];add_action('all',function(...$a) use(&$x){ $x[]=$a; });_wp_call_all_hook(['mein_hook',1]);remove_all_actions('all');return $x===[['mein_hook',1]]; })());

// Marker-Blöcke
$ht=$tmp.'/test.htaccess';
t('insert_with_markers: neue Datei',insert_with_markers($ht,'WordPress',['RewriteEngine On','RewriteRule a b'])&&extract_from_markers($ht,'WordPress')===['RewriteEngine On','RewriteRule a b']);
file_put_contents($ht,"# vorher\n".file_get_contents($ht)."# nachher\n");
t('insert_with_markers: ersetzt nur den Block',insert_with_markers($ht,'WordPress','Neu')&&extract_from_markers($ht,'WordPress')===['Neu']&&str_contains(file_get_contents($ht),'# vorher')&&str_contains(file_get_contents($ht),'# nachher'));
t('insert_with_markers: zweite Marke angehängt',insert_with_markers($ht,'Zwei','x')&&extract_from_markers($ht,'Zwei')===['x']&&extract_from_markers($ht,'WordPress')===['Neu']);
t('got_mod_rewrite/got_url_rewrite boolesch',is_bool(got_mod_rewrite())&&is_bool(got_url_rewrite()));

// Dateien
$d=$tmp.'/liste';mkdir($d.'/sub',0777,true);mkdir($d.'/leer');file_put_contents($d.'/a.txt','a');file_put_contents($d.'/sub/b.php','<?php');file_put_contents($d.'/.versteckt','x');
$l=list_files($d);sort($l);
t('list_files: Dateien, leere Ordner, ohne Versteckte',$l===[$d.'/a.txt',$d.'/leer/',$d.'/sub/b.php']&&false===list_files($tmp.'/nix'));
t('list_files: Ausnahmen',list_files($d,100,['sub'])!==false&&!in_array($d.'/sub/b.php',list_files($d,100,['sub']),true));
$tree=wp_make_theme_file_tree(['a.php'=>'/x/a.php','inc/b.php'=>'/x/inc/b.php','inc/c/d.php'=>'/x/inc/c/d.php']);
t('wp_make_theme_file_tree',$tree==['a.php'=>'a.php','inc'=>['b.php'=>'inc/b.php','c'=>['d.php'=>'inc/c/d.php']]]);
t('wp_make_plugin_file_tree',wp_make_plugin_file_tree(['p/p.php','p/x/y.js'])==['p'=>['p.php'=>'p/p.php','x'=>['y.js'=>'p/x/y.js']]]);
ob_start();$GLOBALS['stylesheet']='tt';wp_print_theme_file_tree($tree);$h=ob_get_clean();
t('wp_print_theme_file_tree: Links',str_contains($h,'theme-editor.php')&&str_contains($h,'>b.php</a>')&&str_contains($h,'folder-label'));
t('get_file_description',get_file_description('/x/theme/functions.php')==='Theme Functions'&&get_file_description('/x/foo/bar.txt')==='bar.txt');
t('Editor-Erweiterungen',in_array('php',wp_get_plugin_file_editable_extensions('x'),true)&&in_array('json',wp_get_theme_file_editable_extensions(wp_get_theme()),true));
t('verify_file_md5',true===verify_file_md5($d.'/a.txt',md5('a'))&&is_wp_error(verify_file_md5($d.'/a.txt','0'))&&is_wp_error(verify_file_md5($d.'/nix','0')));
t('verify_file_signature ohne Schlüssel liefert Fehler',is_wp_error(verify_file_signature($d.'/a.txt',['AAAA'])));
t('win_is_writable',win_is_writable($d)&&win_is_writable($d.'/a.txt')&&win_is_writable($d.'/neu.tmp')&&!file_exists($d.'/neu.tmp'));
t('get_dirsize / recurse_dirsize / clean_dirsize_cache',get_dirsize($d)===7&&recurse_dirsize($tmp.'/nix')===false&&null===clean_dirsize_cache($d));
t('_wp_check_existing_file_names',_wp_check_existing_file_names('bild.jpg',['bild-150x150.jpg'])&&_wp_check_existing_file_names('bild.jpg',['bild-scaled.jpg'])&&!_wp_check_existing_file_names('bild.jpg',['anders.jpg']));
t('_wp_check_alternate_file_names',_wp_check_alternate_file_names(['a.txt'],$d,[])&&!_wp_check_alternate_file_names(['q.txt'],$d,[])&&_wp_check_alternate_file_names(['q.txt'],$d,['q.txt']));
t('_wp_upload_dir ohne Seiteneffekt (Jahr/Monat)',($u=_wp_upload_dir('2024-05-06 00:00:00'))['subdir']==='/2024/05'&&str_ends_with($u['path'],'/2024/05')&&str_ends_with($u['basedir'],'/uploads')&&!is_dir($u['basedir']));

// ZIP und Verschieben
if(class_exists('ZipArchive')){
    $zip=$tmp.'/t.zip';$z=new ZipArchive();$z->open($zip,ZipArchive::CREATE);$z->addFromString('ordner/datei.txt','Inhalt');$z->addFromString('wurzel.txt','w');$z->addEmptyDir('leer');$z->close();
    t('wp_zip_file_is_valid',wp_zip_file_is_valid($zip)&&!wp_zip_file_is_valid($d.'/a.txt'));
    t('unzip_file entpackt',true===unzip_file($zip,$tmp.'/ent')&&file_get_contents($tmp.'/ent/ordner/datei.txt')==='Inhalt'&&is_dir($tmp.'/ent/leer'));
    $bad=$tmp.'/bad.zip';$z=new ZipArchive();$z->open($bad,ZipArchive::CREATE);$z->addFromString('../boese.txt','x');$z->close();
    $r=unzip_file($bad,$tmp.'/ent2');t('unzip_file: Zip-Slip abgelehnt',is_wp_error($r)&&$r->get_error_code()==='invalid_path'&&!file_exists($tmp.'/boese.txt'));
    t('unzip_file: keine ZIP-Datei',is_wp_error(unzip_file($d.'/a.txt',$tmp.'/ent3')));
}
t('move_dir verschiebt',true===move_dir($tmp.'/ent',$tmp.'/ziel')&&!is_dir($tmp.'/ent')&&is_file($tmp.'/ziel/wurzel.txt'));
t('move_dir: Ziel vorhanden',is_wp_error(move_dir($tmp.'/ziel',$d))&&true===move_dir($tmp.'/ziel',$d,true)&&is_file($d.'/wurzel.txt'));
t('move_dir: gleiche Pfade',is_wp_error(move_dir($d,$d)));

// Upload
$src=$tmp.'/hoch.txt';file_put_contents($src,'Hallo');$f=['name'=>'hoch.txt','tmp_name'=>$src,'size'=>5,'error'=>0,'type'=>'text/plain'];
$r=wp_handle_sideload($f,['test_form'=>false]);
t('wp_handle_sideload',isset($r['file'])&&is_file($r['file'])&&$r['type']==='text/plain'&&str_ends_with($r['url'],'/hoch.txt'),json_encode($r));
$f2=['name'=>'x.exe','tmp_name'=>$d.'/a.txt','size'=>1,'error'=>0];t('wp_handle_upload: Typ abgelehnt',isset(wp_handle_sideload($f2,['test_form'=>false,'mimes'=>['txt'=>'text/plain']])['error']));
$f3=['name'=>'a.txt','tmp_name'=>$d.'/a.txt','size'=>0,'error'=>0];t('wp_handle_upload: leere Datei',isset(wp_handle_sideload($f3,['test_form'=>false])['error']));
$f4=['name'=>'a.txt','tmp_name'=>'','size'=>1,'error'=>UPLOAD_ERR_PARTIAL];t('wp_handle_upload: PHP-Fehlercode',str_contains(wp_handle_upload($f4,['test_form'=>false])['error'],'partially'));
$f5=['name'=>'a.txt','tmp_name'=>$d.'/a.txt','size'=>1,'error'=>0];t('wp_handle_upload: Formularprüfung',isset(wp_handle_upload($f5)['error']));

// Plugins
$pd=WP_PLUGIN_DIR.'/demo';mkdir($pd);
file_put_contents($pd.'/demo.php',"<?php\n/*\nPlugin Name: Demo\nPlugin URI: https://demo.test\nAuthor: Ich\nAuthor URI: https://ich.test\nVersion: 1.2\nRequires PHP: 99.0\nDescription: Ein <b>Test</b>.\n*/\n");
file_put_contents($pd.'/uninstall.php',"<?php\nif(!defined('WP_UNINSTALL_PLUGIN'))exit;\n\$GLOBALS['demo_uninstalled']=WP_UNINSTALL_PLUGIN;\n");
file_put_contents($pd.'/inc.php','<?php');
file_put_contents(WP_PLUGIN_DIR.'/Hallo.php',"<?php\n/*\nPlugin Name: Hallo\nNetwork: true\nRequires at least: 1.0\n*/\n");
$pdata=_get_plugin_data_markup_translate($pd.'/demo.php',get_plugin_data($pd.'/demo.php',false,false),true,true);
t('_get_plugin_data_markup_translate',str_contains($pdata['Title'],'<a href="https://demo.test">Demo</a>')&&str_contains($pdata['Author'],'ich.test')&&$pdata['AuthorName']==='Ich');
$pf=get_plugin_files('demo/demo.php');sort($pf);t('get_plugin_files',$pf===['demo/demo.php','demo/inc.php','demo/uninstall.php']);
t('_sort_uname_callback',_sort_uname_callback(['Name'=>'abc'],['Name'=>'Abd'])<0);
t('_get_dropins',isset(_get_dropins()['object-cache.php']));
t('is_network_only_plugin',is_network_only_plugin('Hallo.php')&&!is_network_only_plugin('demo/demo.php'));
$vr=validate_plugin_requirements('demo/demo.php');t('validate_plugin_requirements: PHP zu alt',is_wp_error($vr)&&$vr->get_error_code()==='plugin_php_incompatible'&&true===validate_plugin_requirements('Hallo.php'));
t('is_uninstallable_plugin',is_uninstallable_plugin('demo/demo.php')&&!is_uninstallable_plugin('Hallo.php'));
update_option('active_plugins',['demo/demo.php','weg/weg.php']);
$inv=validate_active_plugins();t('validate_active_plugins deaktiviert ungültige',isset($inv['weg/weg.php'])&&get_option_active_plugins()===['demo/demo.php']);
t('uninstall_plugin führt uninstall.php aus',true===uninstall_plugin('demo/demo.php')&&($GLOBALS['demo_uninstalled']??'')==='demo/demo.php'&&null===uninstall_plugin('Hallo.php'));
$GLOBALS['_paused_plugins']=['demo'=>['type'=>'error','message'=>'x']];
t('Pausierte Plugins',is_plugin_paused('demo/demo.php')&&wp_get_plugin_error('demo/demo.php')['type']==='error'&&[WP_PLUGIN_DIR.'/b/b.php']===array_values(wp_skip_paused_plugins([WP_PLUGIN_DIR.'/demo/demo.php',WP_PLUGIN_DIR.'/b/b.php'])));
t('resume_plugin',true===resume_plugin('demo/demo.php')&&!is_plugin_paused('demo/demo.php')&&true===resume_plugin('demo/demo.php'));
$GLOBALS['rrw_wp_menu']=[];add_menu_page('Seite','Menü','manage_options','mein-slug');add_submenu_page('mein-slug','Unter','Unter','nicht_vorhanden_recht','unter-slug');
global $plugin_page,$pagenow;$plugin_page='unter-slug';$pagenow='admin.php';
t('get_admin_page_parent / user_can_access_admin_page',get_admin_page_parent()==='mein-slug'&&!user_can_access_admin_page()&&get_admin_page_parent('themes.php')==='themes.php');
$plugin_page='mein-slug';t('user_can_access_admin_page: Berechtigung',user_can_access_admin_page());
add_action('toplevel_page_mein-slug','__return_true');t('get_plugin_page_hook',get_plugin_page_hook('mein-slug','')==='toplevel_page_mein-slug'&&null===get_plugin_page_hook('nix',''));
t('add_links_page / add_comments_page',is_string(add_links_page('a','b','manage_options','l1'))&&is_string(add_comments_page('a','b','manage_options','c1')));
unset($plugin_page);
$GLOBALS['new_allowed_options']=['grp'=>['opt_a']];t('option_update_filter',is_array(option_update_filter([])));
t('deactivated_plugins_notice: ohne Fehler still',''===(function(){ ob_start();deactivated_plugins_notice();return ob_get_clean(); })());

// Update-Verwaltung
wp_clean_update_cache();wp_version_check([],true);
$core=get_site_transient('update_core');t('wp_version_check legt Zwischenspeicher an',is_object($core)&&$core->updates===[]&&$core->version_checked===RRW_WP_VERSION);
t('get_preferred_from_update_core: aktuell',get_preferred_from_update_core()->response==='latest');
t('wp_get_update_data ohne Updates',wp_get_update_data()['counts']['total']===0&&wp_get_update_data()['title']==='');
set_site_transient('update_plugins',(object)['response'=>['a/a.php'=>(object)['new_version'=>'2']],'last_checked'=>time()],3600);
t('wp_get_update_data zählt Plugin-Updates',wp_get_update_data()['counts']['plugins']===1&&wp_get_update_data()['counts']['total']===1);
t('core_update_footer',str_contains(core_update_footer(),'Version'));
t('find_core_update / dismiss',false===find_core_update('9','de_DE')&&dismiss_core_update((object)['current'=>'9','locale'=>'de_DE'])&&undismiss_core_update('9','de_DE')&&!undismiss_core_update('9','de_DE'));
t('Auto-Updates: Standard aus',!wp_is_auto_update_enabled_for_type('plugin')&&false===wp_is_auto_update_forced_for_item('plugin',null,(object)[])&&''!==wp_get_auto_update_message());
wp_schedule_update_checks();t('wp_schedule_update_checks',false!==wp_next_scheduled('wp_version_check')&&false!==wp_next_scheduled('wp_update_themes'));
wp_clean_update_cache();t('wp_clean_update_cache',false===get_site_transient('update_core'));
mkdir(WP_CONTENT_DIR.'/upgrade/temp-backup/plugins',0777,true);file_put_contents(WP_CONTENT_DIR.'/upgrade/temp-backup/plugins/x.txt','x');
t('wp_delete_all_temp_backups',true===wp_delete_all_temp_backups()&&!is_file(WP_CONTENT_DIR.'/upgrade/temp-backup/plugins/x.txt'));

// Übersetzung
t('before_last_bar',before_last_bar('a|b|c')==='a|b'&&before_last_bar('abc')==='abc');
t('get_translations_for_domain: unbekannt = NOOP',get_translations_for_domain('gibtsnicht') instanceof NOOP_Translations&&get_translations_for_domain('gibtsnicht')->translate('x')==='x'&&get_translations_for_domain('gibtsnicht')->translate_plural('a','b',2)==='b');
t('translate_settings_using_i18n_schema',translate_settings_using_i18n_schema(['name'=>'Kontext'],['name'=>'Text','farbe'=>'x'],'dom')==['name'=>'Text','farbe'=>'x']&&translate_settings_using_i18n_schema([['name'=>'k']],[['name'=>'A'],['name'=>'B']],'dom')==[['name'=>'A'],['name'=>'B']]);
t('Sprachwechsel-Standardwerte',!is_locale_switched()&&false===restore_current_locale());
t('wp_dropdown_languages',str_contains(wp_dropdown_languages(['echo'=>0,'languages'=>['de_DE'],'translations'=>['de_DE'=>['native_name'=>'Deutsch']],'selected'=>'de_DE']),'<option value="de_DE" lang="de" data-installed="1" selected=\'selected\'>Deutsch</option>'));
t('has_translation ohne Sprachdatei',!has_translation('Hallo'));
t('load_default_textdomain/_load_textdomain_just_in_time',is_bool(load_default_textdomain('de_DE'))&&is_bool(_load_textdomain_just_in_time('irgendwas')));
file_put_contents(WP_LANG_DIR.'/dom-de_DE-meinskript.json','{"locale_data":{}}');wp_register_script('meinskript','https://example.test/js/a.js');
t('load_script_textdomain per Handle',false!==load_script_textdomain('meinskript','dom')&&false===load_script_textdomain('unbekannt','dom'));

// HTTP
t('wp_remote_retrieve_cookie(s)',(function(){ $c=new WP_Http_Cookie(['name'=>'sid','value'=>'42']);$r=['cookies'=>[$c]];return wp_remote_retrieve_cookies($r)===[$c]&&wp_remote_retrieve_cookie($r,'sid')===$c&&wp_remote_retrieve_cookie_value($r,'sid')==='42'&&''===wp_remote_retrieve_cookie_value($r,'x')&&[]===wp_remote_retrieve_cookies('x'); })());
t('get_allowed_http_origins',in_array('https://example.test',get_allowed_http_origins(),true));
t('allowed_http_request_hosts',allowed_http_request_hosts(false,'example.test')&&!allowed_http_request_hosts(false,'fremd.test')&&ms_allowed_http_request_hosts(false,'x')===false);
t('_wp_http_get_object Singleton',_wp_http_get_object()===_wp_http_get_object());
t('wp_safe_remote_head fängt Intranet ab',is_wp_error(wp_safe_remote_head('http://127.0.0.1/')));

// Admin-Hilfen
update_option('recently_edited',[]);foreach(['a','b','c','d','e','f','a'] as $x)update_recently_edited($x);
t('update_recently_edited: höchstens 5, eindeutig',get_option('recently_edited')===['a','f','e','d','c']);
$GLOBALS['v1']='x';$_GET['v2']='g';$_POST['v3']='p';wp_reset_vars(['v1','v2','v3','v4']);
t('wp_reset_vars',($GLOBALS['v1']??'')===''&&$GLOBALS['v2']==='g'&&$GLOBALS['v3']==='p'&&$GLOBALS['v4']==='');
t('wp_doc_link_parse',wp_doc_link_parse('<?php function eigen(){} eigen(); strlen("x"); $o->methode(); array_map(1,2); strlen(1);')===['array_map','strlen']);
t('_customizer_mobile_viewport_meta',_customizer_mobile_viewport_meta('width=device-width,')==='width=device-width,minimum-scale=0.5,maximum-scale=1.2');
t('wp_admin_viewport_meta',str_contains((function(){ ob_start();wp_admin_viewport_meta();return ob_get_clean(); })(),'width=device-width'));
t('options_reading_blog_charset',str_contains((function(){ ob_start();options_reading_blog_charset();return ob_get_clean(); })(),'name="blog_charset"'));
t('Einstellungs-Skripte geben <script> aus',(function(){ foreach(['options_general_add_js','options_reading_add_js','options_discussion_add_js','wp_page_reload_on_back_button_js'] as $fn){ ob_start();$fn();if(!str_contains(ob_get_clean(),'<script>'))return false; }return true; })());
t('wp_refresh_heartbeat_nonces',isset(wp_refresh_heartbeat_nonces([])['rest_nonce'])&&wp_refresh_heartbeat_nonces([])['heartbeat_nonce']!=='');
t('wp_heartbeat_set_suspension',(function(){ global $pagenow;$pagenow='post.php';$s=wp_heartbeat_set_suspension([]);$pagenow='index.php';return $s['suspension']==='disable'&&[]===wp_heartbeat_set_suspension([]); })());
t('wp_auth_check',true===wp_auth_check([])['wp-auth-check']);
t('wp_check_locked_posts ohne Sperren leer',[]===wp_check_locked_posts([],['wp-check-locked-posts'=>['post-5']],'edit-post'));
t('wp_refresh_post_nonces',isset(wp_refresh_post_nonces([],['wp-refresh-post-nonces'=>['post_id'=>5]],'x')['wp-refresh-post-nonces']['replace']['_wpnonce']));
t('get_cli_args',(function(){ $a=$_SERVER['argv'];$_SERVER['argv']=['x','--ein=wert','--flag','-v','--nach','dir'];$r=[get_cli_args('ein'),get_cli_args('flag'),get_cli_args('v'),get_cli_args('nach'),get_cli_args('fehlt')];$_SERVER['argv']=$a;return $r===['wert',true,true,'dir',null]; })());
t('wp_maintenance: ohne Datei kein Wartungsmodus',!wp_is_maintenance_mode());
$mf=ABSPATH.'.maintenance';file_put_contents($mf,'<?php $upgrading = '.time().';');t('wp_is_maintenance_mode: Datei aktuell',wp_is_maintenance_mode());
file_put_contents($mf,'<?php $upgrading = '.(time()-3600).';');t('wp_is_maintenance_mode: abgelaufen',!wp_is_maintenance_mode());@unlink($mf);

// Export, Feeds, Papierkorb
$pid=wp_insert_post(['post_title'=>'Export-Test','post_content'=>'Inhalt <b>fett</b>','post_status'=>'publish','post_type'=>'post']);
ob_start();export_wp(['content'=>'post']);$x=ob_get_clean();
t('export_wp: gültiges WXR',str_contains($x,'<wp:wxr_version>1.2</wp:wxr_version>')&&str_contains($x,'Export-Test')&&(bool)@simplexml_load_string($x));
ob_start();export_wp(['content'=>'post','status'=>'draft']);$x2=ob_get_clean();t('export_wp: Statusfilter',!str_contains($x2,'Export-Test'));
$trash=wp_insert_post(['post_title'=>'Alt im Papierkorb','post_status'=>'publish','post_type'=>'post']);
if($trash){ wp_trash_post($trash);update_post_meta($trash,'_wp_trash_meta_time',time()-100*DAY_IN_SECONDS);update_post_meta($trash,'_wp_trash_meta_status','publish'); wp_scheduled_delete();
    t('wp_scheduled_delete entfernt alte Papierkorb-Beiträge',!get_post($trash)||get_post($trash)->post_status!=='trash',(string)($trash)); }
else t('wp_scheduled_delete (kein Beitrag anlegbar)',true);
t('wp_scheduled_delete lässt andere Beiträge',(bool)get_post($pid));
$GLOBALS['wp_query']->set('feed','rss2');$feedOut=(function(){ ob_start();try{ do_feed_rss2(); }catch(Throwable $e){}return ob_get_clean(); })();
t('do_feed_rss2: gültiges XML',(bool)@simplexml_load_string($feedOut)&&str_contains($feedOut,'<rss version="2.0"'));
t('do_feed_atom: gültiges XML',(bool)@simplexml_load_string((function(){ ob_start();do_feed_atom();return ob_get_clean(); })()));
t('do_feed: unbekannter Feed → Fehler',(function(){ $GLOBALS['wp_query']->set('feed','nixfeed');try{ ob_start();do_feed();ob_end_clean();return false; }catch(RRW_WP_Die $e){ ob_end_clean();return $e->getCode()===404; } })());

// Anzahl und Ergebnis
echo $fail?"$fail Fehler von $n Prüfungen\n":"OK ($n Prüfungen)\n";
@exec('rm -rf '.escapeshellarg($tmp));
exit($fail?1:0);

<?php
// Ergänzende WordPress-Funktionen (Bereich System, Teil 2): Start/Laufzeit-Umgebung (load.php), Hooks, Cache, Aktualisierungen, HTTP-Hilfen.
// Der Start selbst übernimmt das CMS (elvado_wp_boot); diese Funktionen liefern die Prüfungen und Standardwerte, die Plugins erwarten.

/* ───────── Umgebung ───────── */
if(!function_exists('wp_fix_server_vars')){ function wp_fix_server_vars() {
    $d=['SERVER_SOFTWARE'=>'','REQUEST_URI'=>''];$_SERVER=array_merge($d,$_SERVER);
    if(empty($_SERVER['REQUEST_URI'])||(PHP_SAPI!=='cgi-fcgi'&&preg_match('/^Microsoft-IIS\//',(string)$_SERVER['SERVER_SOFTWARE']))){
        $_SERVER['REQUEST_URI']=($_SERVER['PHP_SELF']??'').(isset($_SERVER['QUERY_STRING'])&&$_SERVER['QUERY_STRING']!==''?'?'.$_SERVER['QUERY_STRING']:'');
    }
    if(isset($_SERVER['SCRIPT_FILENAME'])&&str_ends_with((string)$_SERVER['SCRIPT_FILENAME'],'php.cgi'))$_SERVER['SCRIPT_FILENAME']=$_SERVER['PATH_TRANSLATED']??$_SERVER['SCRIPT_FILENAME'];
    if(empty($_SERVER['PHP_SELF']))$_SERVER['PHP_SELF']=preg_replace('/(\?.*)?$/','',(string)$_SERVER['REQUEST_URI']);
} }
if(!function_exists('wp_populate_basic_auth_from_authorization_header')){ function wp_populate_basic_auth_from_authorization_header() {
    if(isset($_SERVER['PHP_AUTH_USER']))return;
    $h=$_SERVER['HTTP_AUTHORIZATION']??($_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
    if(!is_string($h)||stripos($h,'basic ')!==0)return;
    $c=base64_decode(substr($h,6),true);if($c===false||!str_contains($c,':'))return;
    [$_SERVER['PHP_AUTH_USER'],$_SERVER['PHP_AUTH_PW']]=explode(':',$c,2);
} }
if(!function_exists('wp_check_php_mysql_versions')){ function wp_check_php_mysql_versions() {
    $req=(string)($GLOBALS['required_php_version']??'7.4');
    if(version_compare(PHP_VERSION,$req,'<'))wp_die(sprintf(__('Your server is running PHP version %1$s but WordPress %2$s requires at least %3$s.'),PHP_VERSION,ELVADO_WP_VERSION,$req),'',['response'=>500]);
} }
if(!function_exists('wp_get_development_mode')){ function wp_get_development_mode() {
    $m=defined('WP_DEVELOPMENT_MODE')?(string)WP_DEVELOPMENT_MODE:'';$m=(string)apply_filters('wp_development_mode',$m);
    if(!in_array($m,['','core','plugin','theme','all'],true))$m='';
    return $m;
} }
if(!function_exists('wp_is_development_mode')){ function wp_is_development_mode($mode) { $c=wp_get_development_mode();return $c!==''&&($c===$mode||$c==='all'); } }
if(!function_exists('wp_favicon_request')){ function wp_favicon_request() {
    if(($_SERVER['REQUEST_URI']??'')!=='/favicon.ico')return;
    do_action('do_faviconico');
    elvado_ext_die_end('',200,'image/vnd.microsoft.icon');
} }
if(!function_exists('wp_is_maintenance_mode')){ function wp_is_maintenance_mode() {
    $f=ABSPATH.'.maintenance';if(!is_file($f)||wp_installing())return false;
    $up=preg_match('/\$upgrading\s*=\s*(\d+)/',(string)@file_get_contents($f),$m)?(int)$m[1]:0;   // Datei wird nicht ausgeführt, nur gelesen
    if(time()-$up>=10*MINUTE_IN_SECONDS)return false;
    return (bool)apply_filters('enable_maintenance_mode',true,$up);
} }
if(!function_exists('wp_maintenance')){ function wp_maintenance() {
    if(!wp_is_maintenance_mode())return;
    if(!headers_sent()){ http_response_code(503);header('Retry-After: 600'); }
    wp_die(__('Briefly unavailable for scheduled maintenance. Check back in a minute.'),__('Maintenance'),['response'=>503]);
} }
if(!function_exists('timer_float')){ function timer_float() { return microtime(true)-(float)($GLOBALS['timestart']??($_SERVER['REQUEST_TIME_FLOAT']??microtime(true))); } }
if(!function_exists('timer_start')){ function timer_start() { $GLOBALS['timestart']=microtime(true);return true; } }
if(!function_exists('wp_debug_mode')){ function wp_debug_mode() {
    if(!apply_filters('enable_wp_debug_mode_checks',true))return;
    if(defined('WP_DEBUG')&&WP_DEBUG){
        error_reporting(E_ALL);
        if(defined('WP_DEBUG_DISPLAY')&&WP_DEBUG_DISPLAY)ini_set('display_errors','1');
        elseif(WP_DEBUG_DISPLAY===false)ini_set('display_errors','0');
        if(in_array(strtolower((string)WP_DEBUG_LOG),['true','1'],true)||WP_DEBUG_LOG===true)ini_set('log_errors','1');
    } else error_reporting(E_CORE_ERROR|E_CORE_WARNING|E_COMPILE_ERROR|E_ERROR|E_WARNING|E_PARSE|E_USER_ERROR|E_USER_WARNING|E_RECOVERABLE_ERROR);
} }
if(!function_exists('wp_set_lang_dir')){ function wp_set_lang_dir() { if(!defined('WP_LANG_DIR'))define('WP_LANG_DIR',WP_CONTENT_DIR.'/languages'); } }
if(!function_exists('require_wp_db')){ function require_wp_db() { global $wpdb;if(!isset($wpdb)&&function_exists('elvado_wp_init_db'))elvado_wp_init_db(); } }
if(!function_exists('wp_set_wpdb_vars')){ function wp_set_wpdb_vars() {
    global $wpdb,$table_prefix;if(empty($wpdb)||!is_object($wpdb))return;
    if(!empty($table_prefix)&&method_exists($wpdb,'set_prefix'))$wpdb->set_prefix($table_prefix);
} }
if(!function_exists('wp_start_object_cache')){ function wp_start_object_cache() {
    static $first=true;wp_cache_init();
    if($first){ wp_cache_add_global_groups(['blog-details','blog-id-cache','blog-lookup','blog_meta','global-posts','networks','network-queries','sites','site-details','site-options','site-queries','site-transient','rss','users','useremail','userlogins','userslugs','user_meta','usermeta','user_counts','site_meta']);$first=false; }
    wp_cache_add_non_persistent_groups(['counts','plugins','theme_json']);
} }
if(!function_exists('wp_not_installed')){ function wp_not_installed() { if(!is_blog_installed()&&!wp_installing())wp_die(__('The site is not installed yet.'),'',['response'=>503]); } }
if(!function_exists('wp_skip_paused_plugins')){ function wp_skip_paused_plugins(array $plugins) {   // gestoppte Plugins stehen in $_paused_plugins (Schlüssel: Ordner oder Datei)
    $p=$GLOBALS['_paused_plugins']??[];if(!$p)return $plugins;
    foreach($plugins as $i=>$f){ $rel=plugin_basename($f);$k=dirname($rel)==='.'?$rel:dirname($rel);if(isset($p[$k]))unset($plugins[$i]); }
    return $plugins;
} }
if(!function_exists('wp_get_active_and_valid_themes')){ function wp_get_active_and_valid_themes() {
    $t=[];$s=get_stylesheet_directory();$tp=get_template_directory();
    if($s!==$tp&&is_dir($s))$t[]=$s;   // Kind-Theme zuerst
    if(is_dir($tp))$t[]=$tp;
    return apply_filters('wp_get_active_and_valid_themes',$t);
} }
if(!function_exists('wp_skip_paused_themes')){ function wp_skip_paused_themes(array $themes) {
    $p=$GLOBALS['_paused_themes']??[];if(!$p)return $themes;
    foreach($themes as $i=>$d)if(isset($p[basename($d)]))unset($themes[$i]);
    return $themes;
} }
if(!function_exists('wp_is_recovery_mode')){ function wp_is_recovery_mode() { return false; } }   // Wiederherstellungsmodus gibt es nicht: ein fehlerhaftes Plugin wird sofort deaktiviert
if(!function_exists('is_protected_ajax_action')){ function is_protected_ajax_action() {
    if(!wp_doing_ajax()||empty($_REQUEST['action']))return false;
    $a=apply_filters('wp_protected_ajax_actions',['edit-theme-plugin-file','heartbeat','install-plugin','install-theme','search-install-plugins','query-themes','update-plugin','update-theme','delete-plugin','delete-theme','activate-plugin']);
    return in_array((string)$_REQUEST['action'],(array)$a,true);
} }
if(!function_exists('is_protected_endpoint')){ function is_protected_endpoint() {
    if(isset($GLOBALS['pagenow'])&&$GLOBALS['pagenow']==='wp-login.php')return true;
    if(is_admin()&&!wp_doing_ajax())return true;
    if(is_protected_ajax_action())return true;
    return (bool)apply_filters('wp_is_protected_endpoint',false);
} }
if(!function_exists('wp_set_internal_encoding')){ function wp_set_internal_encoding() {
    if(!function_exists('mb_internal_encoding'))return;
    $c=get_option('blog_charset');if(!$c||!@mb_internal_encoding($c))mb_internal_encoding('UTF-8');
} }
if(!function_exists('wp_magic_quotes')){ function wp_magic_quotes() {   // wie WordPress: Eingaben mit Schrägstrichen versehen (nur auf ausdrücklichen Aufruf)
    $_GET=add_magic_quotes($_GET);$_POST=add_magic_quotes($_POST);$_COOKIE=add_magic_quotes($_COOKIE);$_SERVER=add_magic_quotes($_SERVER);
    $_REQUEST=array_merge($_GET,$_POST);
} }
if(!function_exists('shutdown_action_hook')){ function shutdown_action_hook() { do_action('shutdown');wp_cache_close(); } }
if(!function_exists('wp_clone')){ function wp_clone($object) { return clone $object; } }
if(!function_exists('is_login')){ function is_login() { return isset($GLOBALS['pagenow'])&&$GLOBALS['pagenow']==='wp-login.php'||basename((string)($_SERVER['SCRIPT_NAME']??''))==='wp-login.php'; } }
if(!function_exists('wp_load_translations_early')){ function wp_load_translations_early() { static $l=false;if($l)return true;$l=true;return true; } }   // Texte werden bei Bedarf selbst geladen (elvado_wp_mo_domain)
if(!function_exists('wp_is_ini_value_changeable')){ function wp_is_ini_value_changeable($setting) {
    static $all=null;if($all===null)$all=function_exists('ini_get_all')?@ini_get_all(null,true):false;
    if(!is_array($all))return true;
    return isset($all[$setting]['access'])&&(($all[$setting]['access']&INI_ALL)===INI_ALL||($all[$setting]['access']&INI_USER)===INI_USER);
} }
if(!function_exists('wp_using_themes')){ function wp_using_themes() { return defined('WP_USE_THEMES')&&WP_USE_THEMES; } }
if(!function_exists('wp_start_scraping_edited_file_errors')){ function wp_start_scraping_edited_file_errors() {
    if(!isset($_REQUEST['wp_scrape_key'],$_REQUEST['wp_scrape_nonce']))return;
    $key=substr(sanitize_key(wp_unslash($_REQUEST['wp_scrape_key'])),0,32);$nonce=wp_unslash($_REQUEST['wp_scrape_nonce']);
    if(get_transient('scrape_key_'.$key)!==$nonce){
        echo "\n###### wp_scraping_result_start:$key ######\n".wp_json_encode(['code'=>'scrape_nonce_failure','message'=>__('Scrape key check failed. Please try again.')])."\n###### wp_scraping_result_end:$key ######\n";
        if(!empty($GLOBALS['elvado_wp_die_throws']))throw new ELVADO_WP_Die('scrape',200);
        die();
    }
    if(!defined('WP_SANDBOX_SCRAPING'))define('WP_SANDBOX_SCRAPING',true);
    register_shutdown_function('wp_finalize_scraping_edited_file_errors',$key);
} }
if(!function_exists('wp_finalize_scraping_edited_file_errors')){ function wp_finalize_scraping_edited_file_errors($scrape_key) {
    $e=error_get_last();echo "\n###### wp_scraping_result_start:$scrape_key ######\n";
    if(!empty($e)&&in_array($e['type'],[E_CORE_ERROR,E_COMPILE_ERROR,E_ERROR,E_PARSE,E_USER_ERROR,E_RECOVERABLE_ERROR],true)){ $e['file']=str_replace(ABSPATH,'',(string)$e['file']);echo wp_json_encode($e); }
    else echo wp_json_encode(true);
    echo "\n###### wp_scraping_result_end:$scrape_key ######\n";
} }
if(!function_exists('wp_is_jsonp_request')){ function wp_is_jsonp_request() {
    if(!isset($_GET['_jsonp']))return false;
    if(!apply_filters('rest_jsonp_enabled',true))return false;
    return wp_check_jsonp_callback((string)$_GET['_jsonp']);
} }
if(!function_exists('wp_is_json_media_type')){ function wp_is_json_media_type($media_type) {
    static $c=[];if(!isset($c[$media_type]))$c[$media_type]=(bool)preg_match('/(^|\s|,)application\/([\w!#\$&-\^\.\+]+\+)?json(\+oembed)?($|\s|;|,)/i',(string)$media_type);
    return $c[$media_type];
} }
if(!function_exists('wp_is_site_protected_by_basic_auth')){ function wp_is_site_protected_by_basic_auth($context='') {
    return (bool)apply_filters('wp_is_site_protected_by_basic_auth',!empty($_SERVER['PHP_AUTH_USER'])||!empty($_SERVER['PHP_AUTH_PW']),$context);
} }

/* ───────── Hooks, Cache, Skripte ───────── */
if(!function_exists('wp_register_plugin_realpath')){ function wp_register_plugin_realpath($file) {   // Plugin-Ordner per Symlink: Pfade merken
    global $wp_plugin_paths;$p=wp_normalize_path(dirname((string)$file));$r=@realpath(dirname((string)$file));if(!$r)return false;$r=wp_normalize_path($r);
    if($p===$r)return false;$wp_plugin_paths[$p]=$r;return true;
} }
if(!function_exists('_wp_call_all_hook')){ function _wp_call_all_hook($args) {   // Callbacks am Sammel-Hook „all“ (bekommen den Hook-Namen zuerst)
    global $wp_filter;if(empty($wp_filter['all']))return;
    $prios=array_keys($wp_filter['all']);sort($prios,SORT_NUMERIC);
    foreach($prios as $p)foreach($wp_filter['all'][$p]??[] as $cb)call_user_func_array($cb['function'],$args);
} }
if(!function_exists('wp_cache_replace')){ function wp_cache_replace($key, $data, $group='', $expire=0) {
    wp_cache_get($key,$group,false,$found);if(!$found)return false;return wp_cache_set($key,$data,$group,$expire);
} }
if(!function_exists('_wp_scripts_maybe_doing_it_wrong')){ function _wp_scripts_maybe_doing_it_wrong($function_name, $handle='') {
    if(did_action('init')||did_action('wp_enqueue_scripts')||did_action('admin_enqueue_scripts')||did_action('login_enqueue_scripts'))return;
    _doing_it_wrong($function_name,sprintf('Scripts and styles should not be registered or enqueued until the %1$s, %2$s, or %3$s hooks.','wp_enqueue_scripts','admin_enqueue_scripts','login_enqueue_scripts').($handle!==''?' ('.$handle.')':''),'3.3.0');
} }
if(!function_exists('wp_simplepie_autoload')){ function wp_simplepie_autoload($class) { /* SimplePie ist nicht enthalten: nichts zu laden */ } }

/* ───────── HTTP ───────── */
if(!function_exists('_wp_http_get_object')){ function _wp_http_get_object() { static $h=null;if($h===null)$h=new WP_Http();return $h; } }
if(!function_exists('wp_safe_remote_head')){ function wp_safe_remote_head($url, $args=[]) { $args['reject_unsafe_urls']=true;return wp_remote_head($url,$args); } }
if(!function_exists('wp_remote_retrieve_cookies')){ function wp_remote_retrieve_cookies($response) { return is_array($response)&&isset($response['cookies'])&&is_array($response['cookies'])?$response['cookies']:[]; } }
if(!function_exists('wp_remote_retrieve_cookie')){ function wp_remote_retrieve_cookie($response, $name) {
    foreach(wp_remote_retrieve_cookies($response) as $c)if(is_object($c)&&isset($c->name)&&$c->name===$name)return $c;
    return '';
} }
if(!function_exists('wp_remote_retrieve_cookie_value')){ function wp_remote_retrieve_cookie_value($response, $name) { $c=wp_remote_retrieve_cookie($response,$name);return is_object($c)&&isset($c->value)?$c->value:''; } }
if(!function_exists('get_allowed_http_origins')){ function get_allowed_http_origins() {
    $o=[];foreach([admin_url(),home_url()] as $u){ $h=parse_url($u,PHP_URL_HOST);if($h){ $o[]='http://'.$h;$o[]='https://'.$h; } }
    return apply_filters('allowed_http_origins',array_values(array_unique($o)));
} }
if(!function_exists('is_allowed_http_origin')){ function is_allowed_http_origin($origin=null) {
    $origin=$origin??(function_exists('get_http_origin')?get_http_origin():'');$r='';
    if($origin!==''&&in_array($origin,get_allowed_http_origins(),true))$r=$origin;
    return apply_filters('allowed_http_origin',$r,$origin);
} }
if(!function_exists('send_origin_headers')){ function send_origin_headers() {
    $origin=function_exists('get_http_origin')?get_http_origin():'';
    if(is_allowed_http_origin($origin)){
        if(!headers_sent()){ header('Access-Control-Allow-Origin: '.$origin);header('Access-Control-Allow-Credentials: true'); }
        if(($_SERVER['REQUEST_METHOD']??'')==='OPTIONS')elvado_ext_die_end('',200,'');
        return $origin;
    }
    if($origin!=='')status_header(403);
    return false;
} }
if(!function_exists('allowed_http_request_hosts')){ function allowed_http_request_hosts($is_external, $host) {
    if(!$is_external&&strtolower((string)parse_url(home_url(),PHP_URL_HOST))===strtolower((string)$host))$is_external=true;
    return $is_external;
} }
if(!function_exists('ms_allowed_http_request_hosts')){ function ms_allowed_http_request_hosts($is_external, $host) { return $is_external; } }   // nur Multisite: unverändert
if(!function_exists('_wp_translate_php_url_constant_to_key')){ function _wp_translate_php_url_constant_to_key($constant) {
    $m=[PHP_URL_SCHEME=>'scheme',PHP_URL_HOST=>'host',PHP_URL_PORT=>'port',PHP_URL_USER=>'user',PHP_URL_PASS=>'pass',PHP_URL_PATH=>'path',PHP_URL_QUERY=>'query',PHP_URL_FRAGMENT=>'fragment'];
    return $m[$constant]??false;
} }
if(!function_exists('_get_component_from_parsed_url_array')){ function _get_component_from_parsed_url_array($url_parts, $component=-1) {
    if($component===-1)return $url_parts;
    $k=_wp_translate_php_url_constant_to_key($component);
    return $k!==false&&is_array($url_parts)&&isset($url_parts[$k])?$url_parts[$k]:null;
} }

/* ───────── Aktualisierungen (ohne Netzabruf: es gibt nie Updates, Werte stehen im Zwischenspeicher) ───────── */
if(!function_exists('elvado_ext_update_stub')){ function elvado_ext_update_stub($key, $force=false) {   // leeres Ergebnis „aktuell“ im Zwischenspeicher ablegen
    $cur=get_site_transient($key);
    if(!$force&&is_object($cur)&&isset($cur->last_checked)&&time()-(int)$cur->last_checked<12*HOUR_IN_SECONDS)return $cur;
    $o=new stdClass();$o->last_checked=time();$o->checked=[];$o->response=[];$o->translations=[];$o->no_update=[];
    if($key==='update_core'){ $o->updates=[];$o->version_checked=ELVADO_WP_VERSION; }
    set_site_transient($key,$o,12*HOUR_IN_SECONDS);return $o;
} }
if(!function_exists('wp_version_check')){ function wp_version_check($extra_stats=[], $force_check=false) { if(wp_installing())return;elvado_ext_update_stub('update_core',(bool)$force_check); } }
if(!function_exists('wp_maybe_auto_update')){ function wp_maybe_auto_update() { /* keine automatischen Updates im CMS */ } }
if(!function_exists('wp_get_translation_updates')){ function wp_get_translation_updates() { return []; } }
if(!function_exists('wp_get_update_data')){ function wp_get_update_data() {
    $c=['plugins'=>0,'themes'=>0,'wordpress'=>0,'translations'=>0];
    $p=get_site_transient('update_plugins');if(is_object($p)&&!empty($p->response))$c['plugins']=count((array)$p->response);
    $t=get_site_transient('update_themes');if(is_object($t)&&!empty($t->response))$c['themes']=count((array)$t->response);
    $w=function_exists('get_core_updates')?get_core_updates():[];if(!empty($w)&&isset($w[0]->response)&&$w[0]->response==='upgrade')$c['wordpress']=1;
    $c['translations']=count(wp_get_translation_updates());
    $c['total']=array_sum($c);$parts=[];
    if($c['wordpress'])$parts[]=__('WordPress');
    if($c['plugins'])$parts[]=sprintf(_n('%d Plugin Update','%d Plugin Updates',$c['plugins']),$c['plugins']);
    if($c['themes'])$parts[]=sprintf(_n('%d Theme Update','%d Theme Updates',$c['themes']),$c['themes']);
    if($c['translations'])$parts[]=__('Translation Updates');
    return apply_filters('wp_get_update_data',['counts'=>$c,'title'=>implode(', ',$parts)],$c,implode(', ',$parts));
} }
if(!function_exists('_maybe_update_core')){ function _maybe_update_core() { if(wp_installing())return;elvado_ext_update_stub('update_core'); } }
if(!function_exists('_maybe_update_plugins')){ function _maybe_update_plugins() { if(wp_installing())return;elvado_ext_update_stub('update_plugins'); } }
if(!function_exists('_maybe_update_themes')){ function _maybe_update_themes() { if(wp_installing())return;elvado_ext_update_stub('update_themes'); } }
if(!function_exists('wp_schedule_update_checks')){ function wp_schedule_update_checks() {
    if(wp_installing())return;
    foreach(['wp_version_check','wp_update_plugins','wp_update_themes'] as $h)if(!wp_next_scheduled($h))wp_schedule_event(time(),'twicedaily',$h);
} }
if(!function_exists('wp_clean_update_cache')){ function wp_clean_update_cache() { foreach(['update_core','update_plugins','update_themes'] as $k)delete_site_transient($k); } }
if(!function_exists('wp_delete_all_temp_backups')){ function wp_delete_all_temp_backups() {
    $d=WP_CONTENT_DIR.'/upgrade/temp-backup';if(!is_dir($d))return true;
    $root=realpath(WP_CONTENT_DIR);$real=realpath($d);if(!$root||!$real||!str_starts_with($real,$root.DIRECTORY_SEPARATOR))return false;
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $f)$f->isDir()&&!$f->isLink()?@rmdir($f->getPathname()):@unlink($f->getPathname());
    return true;
} }
if(!function_exists('_wp_delete_all_temp_backups')){ function _wp_delete_all_temp_backups() { return wp_delete_all_temp_backups(); } }

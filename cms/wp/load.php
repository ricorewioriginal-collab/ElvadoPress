<?php
// WordPress-Kompatibilitätsschicht des CMS – Einstieg. Stellt die WordPress-API (Hooks, Shortcodes, Optionen, Escaping, Plugins …)
// bereit, damit WordPress-Plugins und -Themes laufen. Eigenständige Implementierung; kein WordPress-Quelltext.
// Aufruf: require_once __DIR__.'/wp/load.php'; elvado_wp_boot();
if(defined('ELVADO_WP_LOADED'))return;
define('ELVADO_WP_LOADED',true);

if(!defined('ABSPATH'))define('ABSPATH',__DIR__.'/');
if(!defined('WPINC'))define('WPINC','core');
if(!defined('WP_CONTENT_DIR'))define('WP_CONTENT_DIR',dirname(__DIR__).'/wp-content');
if(!defined('WP_PLUGIN_DIR'))define('WP_PLUGIN_DIR',WP_CONTENT_DIR.'/plugins');
if(!defined('WPMU_PLUGIN_DIR'))define('WPMU_PLUGIN_DIR',WP_CONTENT_DIR.'/mu-plugins');
if(!defined('WP_LANG_DIR'))define('WP_LANG_DIR',WP_CONTENT_DIR.'/languages');
if(!defined('ELVADO_WP_NATIVE_THEMES'))define('ELVADO_WP_NATIVE_THEMES',dirname(__DIR__).'/themes');
if(!defined('ELVADO_WP_DATA'))define('ELVADO_WP_DATA',dirname(__DIR__).'/data/.wp');
// Gemeldete WordPress-Version: Grundstand 6.8.3; nach einem Versionswechsel (Einstellung „WordPress-Version nachführen“) steht die neue in data/.wp/wp-version.json
define('ELVADO_WP_BASE_VERSION','6.8.3');
$elvado_wpv=['version'=>ELVADO_WP_BASE_VERSION,'db'=>57155];
if(is_file(ELVADO_WP_DATA.'/wp-version.json')){ $elvado_j=json_decode((string)@file_get_contents(ELVADO_WP_DATA.'/wp-version.json'),true);
    if(is_array($elvado_j)&&preg_match('/^\d{1,2}\.\d{1,2}(\.\d{1,2})?$/',(string)($elvado_j['version']??'')))$elvado_wpv=['version'=>(string)$elvado_j['version'],'db'=>max(1,(int)($elvado_j['db']??57155))];
    unset($elvado_j); }
define('ELVADO_WP_VERSION',$elvado_wpv['version']);
$GLOBALS['wp_version']=ELVADO_WP_VERSION;$GLOBALS['wp_db_version']=$elvado_wpv['db'];$GLOBALS['required_php_version']='7.4';unset($elvado_wpv);
foreach(['WP_DEBUG'=>false,'WP_DEBUG_LOG'=>false,'WP_DEBUG_DISPLAY'=>false,'SCRIPT_DEBUG'=>false,'WP_MEMORY_LIMIT'=>'256M','WP_MAX_MEMORY_LIMIT'=>'512M','WP_CACHE'=>false,'CONCATENATE_SCRIPTS'=>true,'COMPRESS_SCRIPTS'=>false,'COMPRESS_CSS'=>false,'FORCE_SSL_ADMIN'=>false,'MULTISITE'=>false,'WP_ALLOW_MULTISITE'=>false,'DISALLOW_FILE_EDIT'=>true,'DISALLOW_FILE_MODS'=>false,'COOKIEHASH'=>'elvadowp','EMPTY_TRASH_DAYS'=>30,'WP_POST_REVISIONS'=>true,'AUTOSAVE_INTERVAL'=>60,
    'MINUTE_IN_SECONDS'=>60,'HOUR_IN_SECONDS'=>3600,'DAY_IN_SECONDS'=>86400,'WEEK_IN_SECONDS'=>604800,'MONTH_IN_SECONDS'=>2592000,'YEAR_IN_SECONDS'=>31536000,
    'KB_IN_BYTES'=>1024,'MB_IN_BYTES'=>1048576,'GB_IN_BYTES'=>1073741824,'TB_IN_BYTES'=>1099511627776,'OBJECT'=>'OBJECT','OBJECT_K'=>'OBJECT_K','ARRAY_A'=>'ARRAY_A','ARRAY_N'=>'ARRAY_N'] as $k=>$v)if(!defined($k))define($k,$v);

$elvado_wp_core=__DIR__.'/core/';
if(is_file(dirname(__DIR__).'/wp-core/php/class-wp-list-table.php'))require_once dirname(__DIR__).'/wp-core/php/class-wp-list-table.php';   // echte WP_List_Table aus den Kernressourcen
foreach(['hooks','shortcodes','formatting','options','functions','i18n','script-loader','plugin','general','cron','admin-api','theme','widgets','wpdb','schema','post-types','class-post','cms-content','cms-write','cms-media','meta','user','post','taxonomy','query','template','navigation','sidebars','comments','defaults','compat','customize','extras','blocks','fse','block-supports','core-blocks','shortcodes-builtin'] as $f)require_once $elvado_wp_core.$f.'.php';
// Ergänzende Funktionen (ext/*.php), alphabetisch; sie ergänzen nur, was der Kern nicht definiert
foreach(glob($elvado_wp_core.'ext/*.php')?:[] as $f)require_once $f;
unset($elvado_wp_core);
elvado_wp_init_db();   // $wpdb gibt es ab dem Laden (Verbindung entsteht erst bei der ersten Abfrage)

add_filter('the_content','do_shortcode',11);
add_filter('widget_text','do_shortcode',11);
add_filter('the_excerpt','do_shortcode',11);

/**
 * Laufzeit starten (idempotent). $opts: user (['id','login','name','email','role']) für Rechte, admin (bool) für is_admin().
 * Lädt aktive Plugins in geschütztem Modus und löst plugins_loaded / init / wp_loaded aus.
 */
function elvado_wp_boot(array $opts=[]): array {
    static $errors=null;
    if(!empty($opts['user'])){ $u=$opts['user']; $u['caps']=elvado_wp_caps_for_role((string)($u['role']??'administrator')); $GLOBALS['elvado_wp_user']=$u; }
    if(array_key_exists('admin',$opts))$GLOBALS['elvado_wp_is_admin']=(bool)$opts['admin'];
    if($errors!==null)return $errors;
    if(!is_dir(ELVADO_WP_DATA)){@mkdir(ELVADO_WP_DATA,0775,true);}
    elvado_wp_protect_dir(ELVADO_WP_DATA);
    elvado_sc_register_builtin();
    elvado_wp_init_db();
    elvado_wp_register_default_types();
    $GLOBALS['wp_query']=$GLOBALS['wp_the_query']=new WP_Query();
    elvado_wp_add_default_filters();
    $errors=elvado_wp_load_plugins();
    do_action('setup_theme');   // wie in WordPress: vor dem Laden der functions.php des Themes
    if(!empty($opts['theme']))$errors+=elvado_wp_load_theme();
    do_action('after_setup_theme');
    // Wie in WordPress läuft widgets_init innerhalb von init (Priorität 1): Plugins, die danach auf init hören, finden die Seitenleisten schon vor
    add_action('init',function(){ elvado_wp_register_core_widgets();do_action('widgets_init'); },1);
    do_action('init');
    do_action('wp_loaded');
    if(function_exists('elvado_np_do'))elvado_np_do('wp_ready');   // native Plugins dürfen jetzt WordPress-Hooks (add_action/add_filter) registrieren
    return $errors;
}
/** Shortcodes (eingebaut + Plugins) in HTML-Inhalt auflösen. Ohne „[“ im Text passiert nichts (und es werden keine Plugins geladen). */
function elvado_wp_expand_content(string $html): string {
    if(!str_contains($html,'['))return $html;
    elvado_wp_boot();
    $GLOBALS['elvado_wp_raw_html']=true;   // CMS-HTML: kein wpautop
    try{ return (string)apply_filters('the_content',$html); } finally { $GLOBALS['elvado_wp_raw_html']=false; }
}

/** Theme laden: Eltern-Theme zuerst, dann das aktive; ein Fehler im Theme bricht die Seite nicht ab (wird zurückgemeldet). */
function elvado_wp_load_theme(): array {
    elvado_wp_define_missing_conditionals();
    $errors=[];$dirs=[];$t=get_template();$s=get_stylesheet();
    if($t!==$s)$dirs[]=get_template_directory();$dirs[]=get_stylesheet_directory();
    foreach(array_unique($dirs) as $dir){
        $f=$dir.'/functions.php';if(!is_file($f))continue;
        try{ require_once $f; }catch(Throwable $e){ $errors['theme:'.basename($dir)]=$e->getMessage().' ('.basename($e->getFile()).':'.$e->getLine().')';elvado_wp_log('Theme '.basename($dir).': '.$errors['theme:'.basename($dir)]); }
    }
    return $errors;
}

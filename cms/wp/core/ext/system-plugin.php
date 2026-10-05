<?php
// Ergänzende WordPress-Funktionen (Bereich System, Teil 4): Plugin-Verwaltung (Daten, Dateien, Anforderungen, Deinstallation, Pausierung, Menüs).

if(!function_exists('_get_plugin_data_markup_translate')){ function _get_plugin_data_markup_translate($plugin_file, $plugin_data, $markup=true, $translate=true) {
    $rel=plugin_basename($plugin_file);$d=(string)($plugin_data['TextDomain']??'');
    if($translate&&$d!==''){
        load_plugin_textdomain($d,false,dirname($rel).(string)($plugin_data['DomainPath']??''));
        foreach(['Name','PluginURI','Description','Author','AuthorURI','Version'] as $f)if(isset($plugin_data[$f])&&$plugin_data[$f]!=='')$plugin_data[$f]=translate($plugin_data[$f],$d);
    }
    $tags=['a'=>['href'=>true,'title'=>true],'abbr'=>['title'=>true],'acronym'=>['title'=>true],'code'=>true,'em'=>true,'strong'=>true];
    $plugin_data['AuthorName']=$plugin_data['Author']??'';
    if($markup){
        $plugin_data['Title']=!empty($plugin_data['PluginURI'])&&!empty($plugin_data['Name'])?'<a href="'.esc_url($plugin_data['PluginURI']).'">'.$plugin_data['Name'].'</a>':($plugin_data['Name']??'');
        if(!empty($plugin_data['AuthorURI'])&&!empty($plugin_data['Author']))$plugin_data['Author']='<a href="'.esc_url($plugin_data['AuthorURI']).'">'.$plugin_data['Author'].'</a>';
        $plugin_data['Description']=wp_kses(wptexturize((string)($plugin_data['Description']??'')),$tags);
        $plugin_data['Author']=wp_kses((string)$plugin_data['Author'],$tags);
        $plugin_data['Title']=wp_kses((string)$plugin_data['Title'],$tags);
    } else $plugin_data['Title']=$plugin_data['Name']??'';
    return $plugin_data;
} }
if(!function_exists('get_plugin_files')){ function get_plugin_files($plugin) {
    $f=WP_PLUGIN_DIR.'/'.$plugin;$dir=dirname($f);$files=[plugin_basename($f)];
    if(is_dir($dir)&&wp_normalize_path($dir)!==wp_normalize_path(WP_PLUGIN_DIR))$files=array_values(array_unique(array_merge($files,array_map('plugin_basename',list_files($dir)?:[]))));
    return $files;
} }
if(!function_exists('_sort_uname_callback')){ function _sort_uname_callback($a, $b) { return strnatcasecmp((string)(is_array($a)?$a['Name']:$a->Name),(string)(is_array($b)?$b['Name']:$b->Name)); } }
if(!function_exists('_get_dropins')){ function _get_dropins() { return [
    'advanced-cache.php'=>[__('Advanced caching plugin.'),'WP_CACHE'],'db.php'=>[__('Custom database class.'),true],'db-error.php'=>[__('Custom database error message.'),true],
    'install.php'=>[__('Custom installation script.'),true],'maintenance.php'=>[__('Custom maintenance message.'),true],'object-cache.php'=>[__('External object cache.'),true],
    'php-error.php'=>[__('Custom PHP error message.'),true],'fatal-error-handler.php'=>[__('Custom PHP fatal error handler.'),true]]; } }
if(!function_exists('is_network_only_plugin')){ function is_network_only_plugin($plugin) {
    $f=WP_PLUGIN_DIR.'/'.$plugin;if(!is_file($f))return false;
    return strtolower((string)get_plugin_data($f,false,false)['Network'])==='true';
} }
if(!function_exists('validate_active_plugins')){ function validate_active_plugins() {
    $bad=[];foreach(get_option_active_plugins() as $p){ $r=validate_plugin($p);if(is_wp_error($r)){ $bad[$p]=$r;deactivate_plugins($p,true); } }
    return $bad;
} }
if(!function_exists('validate_plugin_requirements')){ function validate_plugin_requirements($plugin) {
    $h=get_plugin_data(WP_PLUGIN_DIR.'/'.$plugin,false,false);
    $okwp=is_wp_version_compatible($h['RequiresWP']??'');$okphp=is_php_version_compatible($h['RequiresPHP']??'');$n=$h['Name']??$plugin;
    if(!$okwp&&!$okphp)return new WP_Error('plugin_wp_php_incompatible','<strong>Error:</strong> Current versions of WordPress and PHP do not meet minimum requirements for '.$n.'.');
    if(!$okwp)return new WP_Error('plugin_wp_incompatible','<strong>Error:</strong> Current WordPress version does not meet minimum requirements for '.$n.'.');
    if(!$okphp)return new WP_Error('plugin_php_incompatible','<strong>Error:</strong> Current PHP version does not meet minimum requirements for '.$n.'.');
    return true;
} }
if(!function_exists('is_uninstallable_plugin')){ function is_uninstallable_plugin($plugin) {
    $f=plugin_basename($plugin);$u=(array)get_option('uninstall_plugins',[]);
    return isset($u[$f])||is_file(WP_PLUGIN_DIR.'/'.dirname($f).'/uninstall.php')&&dirname($f)!=='.'||(bool)has_action('uninstall_'.$f);
} }
if(!function_exists('uninstall_plugin')){ function uninstall_plugin($plugin) {
    $f=plugin_basename($plugin);$u=(array)get_option('uninstall_plugins',[]);$dir=dirname($f);
    $script=$dir!=='.'?WP_PLUGIN_DIR.'/'.$dir.'/uninstall.php':'';
    if($script!==''&&is_file($script)){
        $real=realpath($script);$root=realpath(WP_PLUGIN_DIR);
        if(!$real||!$root||!str_starts_with($real,$root.DIRECTORY_SEPARATOR))return false;
        if(isset($u[$f])){ unset($u[$f]);update_option('uninstall_plugins',$u); }
        if(!defined('WP_UNINSTALL_PLUGIN'))define('WP_UNINSTALL_PLUGIN',$f);
        wp_register_plugin_realpath(WP_PLUGIN_DIR.'/'.$f);
        include $real;return true;
    }
    if(has_action('uninstall_'.$f)||isset($u[$f])){
        if(isset($u[$f])){ unset($u[$f]);update_option('uninstall_plugins',$u); }
        wp_register_plugin_realpath(WP_PLUGIN_DIR.'/'.$f);
        if(is_file(WP_PLUGIN_DIR.'/'.$f))rrw_wp_include_plugin($f);
        do_action('uninstall_'.$f);return true;
    }
    return null;
} }
if(!function_exists('add_links_page')){ function add_links_page($page_title, $menu_title, $capability, $menu_slug, $callback='', $position=null) { return add_submenu_page('link-manager.php',$page_title,$menu_title,$capability,$menu_slug,$callback,$position); } }
if(!function_exists('add_comments_page')){ function add_comments_page($page_title, $menu_title, $capability, $menu_slug, $callback='', $position=null) { return add_submenu_page('edit-comments.php',$page_title,$menu_title,$capability,$menu_slug,$callback,$position); } }
if(!function_exists('get_admin_page_parent')){ function get_admin_page_parent($parent='') {
    global $pagenow,$plugin_page;$menu=$GLOBALS['rrw_wp_menu']??[];
    if(!empty($parent)&&$parent!=='admin.php')return $parent;
    $slug=(string)($plugin_page??'');
    if($slug!==''&&isset($menu[$slug]))return $menu[$slug]['parent']!==''?$menu[$slug]['parent']:$slug;
    return (string)($pagenow??'');
} }
if(!function_exists('get_plugin_page_hook')){ function get_plugin_page_hook($plugin_page, $parent_page) {
    foreach([$GLOBALS['rrw_wp_menu'][$plugin_page]['hook']??'',get_plugin_page_hookname($plugin_page,$parent_page)] as $h)if($h!==''&&has_action($h))return $h;
    return null;
} }
if(!function_exists('user_can_access_admin_page')){ function user_can_access_admin_page() {
    global $plugin_page;$menu=$GLOBALS['rrw_wp_menu']??[];
    if(empty($plugin_page))return true;
    return isset($menu[$plugin_page])&&current_user_can($menu[$plugin_page]['cap']);
} }
if(!function_exists('option_update_filter')){ function option_update_filter($options) { global $new_allowed_options;if(is_array($new_allowed_options))$options=add_allowed_options($new_allowed_options,$options);return $options; } }
if(!function_exists('plugin_sandbox_scrape')){ function plugin_sandbox_scrape($plugin) {
    if(is_wp_error(validate_plugin($plugin)))return;
    if(!defined('WP_SANDBOX_SCRAPING'))define('WP_SANDBOX_SCRAPING',true);
    wp_register_plugin_realpath(WP_PLUGIN_DIR.'/'.$plugin);
    include_once WP_PLUGIN_DIR.'/'.$plugin;
} }
if(!function_exists('is_plugin_paused')){ function is_plugin_paused($plugin) {
    if(empty($GLOBALS['_paused_plugins'])||!is_plugin_active($plugin))return false;
    $k=dirname($plugin)==='.'?$plugin:dirname($plugin);return array_key_exists($k,$GLOBALS['_paused_plugins']);
} }
if(!function_exists('wp_get_plugin_error')){ function wp_get_plugin_error($plugin) {
    if(!is_plugin_paused($plugin))return false;$k=dirname($plugin)==='.'?$plugin:dirname($plugin);return $GLOBALS['_paused_plugins'][$k];
} }
if(!function_exists('resume_plugin')){ function resume_plugin($plugin, $redirect='') {
    if(!is_plugin_paused($plugin))return true;
    $v=validate_plugin($plugin);if(is_wp_error($v))return $v;
    $k=dirname($plugin)==='.'?$plugin:dirname($plugin);unset($GLOBALS['_paused_plugins'][$k]);
    $e=(array)get_option('rrw_wp_plugin_errors',[]);if(isset($e[$plugin])){ unset($e[$plugin]);update_option('rrw_wp_plugin_errors',$e); }
    return true;
} }
if(!function_exists('paused_plugins_notice')){ function paused_plugins_notice() {
    if(empty($GLOBALS['_paused_plugins'])||!current_user_can('resume_plugins')&&!current_user_can('activate_plugins'))return;
    echo wp_get_admin_notice(sprintf(__('One or more plugins failed to load properly. You can find more details and make changes on the <a href="%s">Plugins screen</a>.'),esc_url(admin_url('plugins.php'))),['type'=>'error']);
} }
if(!function_exists('deactivated_plugins_notice')){ function deactivated_plugins_notice() {
    $e=(array)get_option('rrw_wp_plugin_errors',[]);if(!$e||!current_user_can('activate_plugins'))return;
    $l='';foreach($e as $p=>$m)$l.='<li><strong>'.esc_html(basename((string)dirname((string)$p)==='.'?(string)$p:dirname((string)$p))).'</strong>: '.esc_html((string)$m).'</li>';
    echo wp_get_admin_notice(__('The following plugins were deactivated because of an error:').'<ul>'.$l.'</ul>',['type'=>'error','paragraph_wrap'=>false]);
} }

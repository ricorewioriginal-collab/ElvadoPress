<?php
// WordPress-kompatible Plugin-Verwaltung und -Laufzeit: Erkennen (Plugin-Header), Aktivieren/Deaktivieren/Löschen,
// sicheres Laden (ein abstürzendes Plugin wird automatisch deaktiviert), Aktivierungs-/Deaktivierungs-Hooks.

function plugin_basename($file) {
    $file=wp_normalize_path((string)$file);$root=wp_normalize_path(WP_PLUGIN_DIR);
    $real=@realpath($file);if($real)$file=wp_normalize_path($real);$rr=@realpath($root);if($rr)$root=wp_normalize_path($rr);
    if(str_starts_with($file,$root.'/'))return substr($file,strlen($root)+1);
    if($file!==''&&$file[0]!=='/'&&!preg_match('#^[A-Za-z]:/#',$file))return trim($file,'/');// relativ (z. B. elementor/elementor.php): unverändert, wie WordPress
    return basename($file);
}
function plugin_dir_path($file) { return trailingslashit(dirname((string)$file)); }
function plugin_dir_url($file) { return trailingslashit(plugins_url('',(string)$file)); }
function register_activation_hook($file, $callback) { add_action('activate_'.plugin_basename($file),$callback); }
function register_deactivation_hook($file, $callback) { add_action('deactivate_'.plugin_basename($file),$callback); }
function register_uninstall_hook($file, $callback) { $o=(array)get_option('uninstall_plugins',[]);$o[plugin_basename($file)]=$callback; /* Callbacks nicht speichern (Closures); Deinstallation läuft über uninstall.php oder den Hook beim Laden */ add_action('uninstall_'.plugin_basename($file),$callback); }

function get_plugin_data($plugin_file, $markup=true, $translate=true) {
    $h=get_file_data($plugin_file,['Name'=>'Plugin Name','PluginURI'=>'Plugin URI','Version'=>'Version','Description'=>'Description','Author'=>'Author','AuthorURI'=>'Author URI','TextDomain'=>'Text Domain','DomainPath'=>'Domain Path','Network'=>'Network','RequiresWP'=>'Requires at least','RequiresPHP'=>'Requires PHP','UpdateURI'=>'Update URI','RequiresPlugins'=>'Requires Plugins']);
    $h['Title']=$h['Name'];$h['AuthorName']=$h['Author'];
    return $h;
}
function get_plugins($plugin_folder='') {
    $root=WP_PLUGIN_DIR.($plugin_folder?'/'.ltrim($plugin_folder,'/'):'');$out=[];
    if(!is_dir($root))return [];
    foreach(@scandir($root)?:[] as $e){
        if($e==='.'||$e==='..'||$e[0]==='.')continue;
        $p=$root.'/'.$e;
        if(is_file($p)&&str_ends_with($e,'.php')&&$e!=='index.php'){ $d=get_plugin_data($p,false,false);if($d['Name']!=='')$out[plugin_basename($p)]=$d; }
        elseif(is_dir($p)&&!is_link($p)){
            foreach(@scandir($p)?:[] as $f){ if(!str_ends_with($f,'.php')||$f==='index.php'||$f[0]==='.')continue;
                $fp=$p.'/'.$f;if(!is_file($fp))continue;$d=get_plugin_data($fp,false,false);if($d['Name']!==''){ $out[plugin_basename($fp)]=$d; } }
        }
    }
    ksort($out);return apply_filters('all_plugins',$out);
}
function get_option_active_plugins(): array { return array_values(array_filter(array_map('strval',(array)get_option('active_plugins',[])))); }
function is_plugin_active($plugin) { return in_array($plugin,get_option_active_plugins(),true); }
function is_plugin_inactive($plugin) { return !is_plugin_active($plugin); }
function is_plugin_active_for_network($p) { return false; }
function validate_plugin($plugin) {
    $plugin=wp_normalize_path((string)$plugin);
    if(str_contains($plugin,'..')||!str_ends_with($plugin,'.php')||$plugin[0]==='/')return new WP_Error('plugin_invalid','Ungültiger Plugin-Pfad.');
    $f=WP_PLUGIN_DIR.'/'.$plugin;if(!is_file($f))return new WP_Error('plugin_not_found','Plugin-Datei nicht gefunden.');
    $real=realpath($f);$root=realpath(WP_PLUGIN_DIR);if(!$real||!$root||!str_starts_with(wp_normalize_path($real),wp_normalize_path($root).'/'))return new WP_Error('plugin_invalid','Ungültiger Plugin-Pfad.');
    if(get_plugin_data($f)['Name']==='')return new WP_Error('no_plugin_header','Die Datei enthält keinen Plugin-Header.');
    return 0;
}

/** Datei eines Plugins geschützt laden. Löst bei Abbruch (Fatal) eine automatische Deaktivierung aus. */
function rrw_wp_include_plugin(string $plugin): ?string {
    static $failed=[];
    if(isset($failed[$plugin]))return $failed[$plugin];         // include_once würde einen abgebrochenen Ladeversuch sonst als Erfolg werten
    $GLOBALS['rrw_wp_loading']=$plugin;
    try{ include_once WP_PLUGIN_DIR.'/'.$plugin; $err=null; }
    catch(Throwable $e){ $err=$e->getMessage().' ('.basename($e->getFile()).':'.$e->getLine().')'; $failed[$plugin]=$err; }
    $GLOBALS['rrw_wp_loading']=null;
    return $err;
}
function rrw_wp_register_crash_guard(): void {
    static $done=false;if($done)return;$done=true;
    register_shutdown_function(function(){
        $cur=$GLOBALS['rrw_wp_loading']??null;if(!$cur)return;
        $e=error_get_last();
        if($e&&in_array($e['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR],true)){
            rrw_wp_log('Plugin '.$cur.' abgestürzt und automatisch deaktiviert: '.$e['message']);
            $list=array_values(array_diff(get_option_active_plugins(),[$cur]));update_option('active_plugins',$list);
            update_option('rrw_wp_plugin_errors',array_merge((array)get_option('rrw_wp_plugin_errors',[]),[$cur=>mb_substr($e['message'],0,300)]));
        }
    });
}
function rrw_wp_load_plugins(): array {
    static $loaded=[];$errors=[];
    rrw_wp_register_crash_guard();
    foreach(get_option_active_plugins() as $pl){
        if(isset($loaded[$pl]))continue;
        $v=validate_plugin($pl);if(is_wp_error($v)){$errors[$pl]=$v->get_error_message();continue;}
        $err=rrw_wp_include_plugin($pl);
        if($err!==null){ $errors[$pl]=$err; rrw_wp_log('Plugin '.$pl.' konnte nicht geladen werden: '.$err); update_option('active_plugins',array_values(array_diff(get_option_active_plugins(),[$pl]))); update_option('rrw_wp_plugin_errors',array_merge((array)get_option('rrw_wp_plugin_errors',[]),[$pl=>mb_substr($err,0,300)])); continue; }
        $loaded[$pl]=true;
    }
    do_action('plugins_loaded');
    return $errors;
}
function activate_plugin($plugin, $redirect='', $network_wide=false, $silent=false) {
    $plugin=wp_normalize_path((string)$plugin);$v=validate_plugin($plugin);if(is_wp_error($v))return $v;
    if(is_plugin_active($plugin))return null;
    // Anforderungen prüfen
    $d=get_plugin_data(WP_PLUGIN_DIR.'/'.$plugin);
    if($d['RequiresPHP']!==''&&version_compare(PHP_VERSION,$d['RequiresPHP'],'<'))return new WP_Error('php_version','Dieses Plugin benötigt PHP '.$d['RequiresPHP'].'.');
    rrw_wp_register_crash_guard();
    $GLOBALS['rrw_wp_loading']=$plugin;
    try{
        ob_start();
        $err=rrw_wp_include_plugin($plugin);
        $GLOBALS['rrw_wp_loading']=$plugin;
        if($err===null){ do_action('activate_plugin',$plugin,$network_wide); do_action('activate_'.$plugin,$network_wide); }
        $out=ob_get_clean();
    } catch(Throwable $e){ if(ob_get_level())ob_end_clean(); $GLOBALS['rrw_wp_loading']=null; return new WP_Error('plugin_activation_failed',$e->getMessage()); }
    $GLOBALS['rrw_wp_loading']=null;
    if($err!==null)return new WP_Error('plugin_activation_failed',$err);
    $list=get_option_active_plugins();$list[]=$plugin;update_option('active_plugins',array_values(array_unique($list)));
    $errs=(array)get_option('rrw_wp_plugin_errors',[]);unset($errs[$plugin]);update_option('rrw_wp_plugin_errors',$errs);
    do_action('activated_plugin',$plugin,$network_wide);
    rrw_wp_drop_activation_redirects();
    return null;
}
/** Plugins leiten nach der Aktivierung per wp_redirect()+exit auf eigene Assistenten um; das würde in der Plugin-Seiten-Umgebung die Anfrage beenden. */
function rrw_wp_drop_activation_redirects(): void {
    global $wpdb;
    if(!isset($wpdb))return;
    $rows=(array)$wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%activation!_redirect%' ESCAPE '!' OR option_name LIKE '%!_redirect!_on!_activation%' ESCAPE '!' OR option_name LIKE '%redirect!_after!_activation%' ESCAPE '!'");
    foreach($rows as $n){ delete_option($n);if(strpos($n,'_transient_')===0)delete_transient(preg_replace('/^_transient_(timeout_)?/','',$n)); }
    wp_cache_flush();
}
function deactivate_plugins($plugins, $silent=false, $network_wide=null) {
    $list=get_option_active_plugins();
    foreach((array)$plugins as $pl){
        if(!in_array($pl,$list,true))continue;
        if(!$silent){ try{ do_action('deactivate_plugin',$pl,$network_wide); do_action('deactivate_'.$pl,$network_wide); }catch(Throwable $e){ rrw_wp_log('Deaktivierungs-Hook '.$pl.': '.$e->getMessage()); } }
        $list=array_values(array_diff($list,[$pl]));
        if(!$silent)do_action('deactivated_plugin',$pl,$network_wide);
    }
    update_option('active_plugins',$list);
}
function delete_plugins($plugins, $deprecated='') {
    foreach((array)$plugins as $pl){
        $v=validate_plugin($pl);if(is_wp_error($v))return $v;
        if(is_plugin_active($pl))return new WP_Error('plugin_active','Aktive Plugins können nicht gelöscht werden.');
        $dir=dirname(WP_PLUGIN_DIR.'/'.$pl);
        if(realpath($dir)===realpath(WP_PLUGIN_DIR))@unlink(WP_PLUGIN_DIR.'/'.$pl); else rrw_wp_rmdir($dir);
        do_action('deleted_plugin',$pl,true);
    }
    return true;
}
function rrw_wp_rmdir(string $dir): void {
    $real=realpath($dir);$root=realpath(WP_PLUGIN_DIR);$root2=realpath(WP_CONTENT_DIR.'/themes');
    $ok=false;foreach([$root,$root2] as $r)if($r&&$real&&str_starts_with($real,$r.DIRECTORY_SEPARATOR))$ok=true;
    if(!$ok||is_link($dir))return;
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $f){ $f->isDir()&&!$f->isLink()?@rmdir($f->getPathname()):@unlink($f->getPathname()); }
    @rmdir($real);
}

/* ───────── Benutzer/Rechte (an CMS-Anmeldung gekoppelt; Stufe 2 erweitert das Modell) ───────── */
function wp_get_current_user() {
    $u=new WP_User(0);$g=$GLOBALS['rrw_wp_user']??null;
    if($g){ $u->init((object)['ID'=>(int)($g['id']??1),'user_login'=>$g['login']??'','display_name'=>$g['name']??($g['login']??''),'user_email'=>$g['email']??'','role'=>$g['role']??'administrator']);
        if(!empty($g['caps']))$u->allcaps=$g['caps']; }
    return $u;
}
function get_current_user_id() { return wp_get_current_user()->ID; }
function is_user_logged_in() { return wp_get_current_user()->exists(); }
function rrw_wp_caps_for_role(string $role, bool $custom=true): array {
    if($custom&&function_exists('rrw_wp_roles_custom')){ $cr=rrw_wp_roles_custom();if(isset($cr[$role]))return array_filter((array)$cr[$role]['capabilities']); }
    $admin=['manage_options','activate_plugins','install_plugins','delete_plugins','edit_plugins','update_plugins','switch_themes','edit_theme_options','install_themes','delete_themes','edit_themes','update_themes','manage_categories','moderate_comments','upload_files','import','export','unfiltered_html','edit_users','list_users','delete_users','create_users','promote_users','remove_users','edit_dashboard','read','edit_posts','edit_others_posts','edit_published_posts','publish_posts','delete_posts','delete_others_posts','delete_published_posts','edit_pages','edit_others_pages','edit_published_pages','publish_pages','delete_pages','delete_others_pages','read_private_posts','read_private_pages','edit_private_posts','edit_private_pages','manage_links','administrator'];
    if($role==='administrator'||$role==='admin')return array_fill_keys($admin,true);
    $author=['read','edit_posts','edit_published_posts','publish_posts','delete_posts','delete_published_posts','upload_files'];
    if($role==='editor'||$role==='redakteur')return array_fill_keys(array_merge($author,['edit_others_posts','edit_pages','edit_others_pages','edit_published_pages','publish_pages','delete_pages','delete_others_pages','manage_categories','moderate_comments','read_private_posts','read_private_pages']),true);
    if($role==='author'||$role==='autor')return array_fill_keys($author,true);
    return ['read'=>true];
}
function current_user_can($capability, ...$args) {
    $u=wp_get_current_user();if(!$u->exists())return (bool)apply_filters('user_has_cap',false,[$capability],[$capability],$u);
    $caps=$u->allcaps;return (bool)apply_filters('user_has_cap',rrw_wp_has_cap($caps,$capability),[$capability],array_merge([$capability],$args),$u);
}
/** Primitive Berechtigung prüfen; Meta-Rechte (edit_post …) werden auf die allgemeinen Rechte abgebildet. */
function rrw_wp_has_cap(array $caps, string $c): bool {
    static $meta=['edit_post'=>'edit_posts','edit_page'=>'edit_pages','delete_post'=>'delete_posts','delete_page'=>'delete_pages','publish_post'=>'publish_posts','read_post'=>'read','read_page'=>'read','edit_comment'=>'moderate_comments'];
    if(isset($meta[$c]))$c=$meta[$c];
    return !empty($caps[$c]);
}
function user_can($user, $capability, ...$args) { return $user instanceof WP_User?rrw_wp_has_cap((array)$user->allcaps,(string)$capability):false; }
function current_user_can_for_blog($b, $c) { return current_user_can($c); }
function is_admin() { return !empty($GLOBALS['rrw_wp_is_admin']); }
function is_super_admin($uid=false) { return current_user_can('administrator'); }
function wp_set_current_user($id, $name='') { return wp_get_current_user(); }
function wp_login_url($redirect='', $force=false) { return admin_url('index.php'); }
function wp_logout_url($redirect='') { return home_url('/'); }
function admin_url_page($p) { return admin_url($p); }

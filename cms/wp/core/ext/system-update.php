<?php
// Ergänzende WordPress-Funktionen (Bereich System, Teil 7): Update-Verwaltung der Oberfläche. Es gibt keine automatischen Updates
// und keinen Abruf bei wordpress.org: Zwischenspeicher (update_core/plugins/themes) werden gelesen, Listen sind sonst leer.

if(!function_exists('get_preferred_from_update_core')){ function get_preferred_from_update_core() {
    $u=get_core_updates();if(!is_array($u))return false;
    return empty($u)?(object)['response'=>'latest']:$u[0];
} }
if(!function_exists('find_core_auto_update')){ function find_core_auto_update() {
    $c=get_site_transient('update_core');if(!is_object($c)||empty($c->updates))return false;
    foreach($c->updates as $u)if(!empty($u->autoupdate)&&($u->response??'')==='upgrade')return $u;
    return false;
} }
if(!function_exists('get_core_checksums')){ function get_core_checksums($version, $locale) { return false; } }   // Prüfsummen kommen von wordpress.org (kein Abruf)
if(!function_exists('find_core_update')){ function find_core_update($version, $locale) {
    $c=get_site_transient('update_core');if(!is_object($c)||empty($c->updates)||!is_array($c->updates))return false;
    foreach($c->updates as $u)if(($u->current??'')===$version&&($u->locale??'')===$locale)return $u;
    return false;
} }
if(!function_exists('dismiss_core_update')){ function dismiss_core_update($update) { $d=(array)get_site_option('dismissed_update_core',[]);$d[($update->current??'').'|'.($update->locale??'')]=true;return update_site_option('dismissed_update_core',$d); } }
if(!function_exists('undismiss_core_update')){ function undismiss_core_update($version, $locale) {
    $d=(array)get_site_option('dismissed_update_core',[]);$k=$version.'|'.$locale;if(!isset($d[$k]))return false;unset($d[$k]);return update_site_option('dismissed_update_core',$d);
} }
if(!function_exists('core_update_footer')){ function core_update_footer($msg='') {
    $v=get_bloginfo('version','display');if(!current_user_can('update_core'))return sprintf(__('Version %s'),$v);
    $c=get_preferred_from_update_core();$r=is_object($c)?($c->response??''):'';
    if($r==='upgrade')return sprintf('<strong><a href="%s">%s</a></strong>',esc_url(admin_url('update-core.php')),sprintf(__('Get Version %s'),$c->current??''));
    return sprintf(__('Version %s'),$v);
} }
if(!function_exists('update_nag')){ function update_nag() {
    global $pagenow;if($pagenow==='update-core.php')return false;
    $c=get_preferred_from_update_core();if(!is_object($c)||($c->response??'')!=='upgrade')return false;
    $msg=current_user_can('update_core')?sprintf(__('<a href="https://wordpress.org/documentation/wordpress-version/version-%1$s/">WordPress %2$s</a> is available! <a href="%3$s" aria-label="Please update WordPress now">Please update now</a>.'),sanitize_title($c->current),$c->current,esc_url(admin_url('update-core.php'))):sprintf(__('<a href="https://wordpress.org/documentation/wordpress-version/version-%1$s/">WordPress %2$s</a> is available! Please notify the site administrator.'),sanitize_title($c->current),$c->current);
    echo wp_get_admin_notice($msg,['type'=>'warning','additional_classes'=>['update-nag','inline'],'paragraph_wrap'=>false]);
} }
if(!function_exists('update_right_now_message')){ function update_right_now_message() {
    $t=wp_get_theme();$msg=sprintf(__('WordPress %1$s running %2$s theme.'),get_bloginfo('version','display'),'<span class="b">'.esc_html($t->get('Name')?:get_stylesheet()).'</span>');
    echo "<span id='wp-version-message'>$msg</span>";
} }
if(!function_exists('wp_plugin_update_rows')){ function wp_plugin_update_rows() {
    $c=get_site_transient('update_plugins');if(!is_object($c)||empty($c->response))return;
    foreach(array_keys((array)$c->response) as $f)add_action("after_plugin_row_$f",'wp_plugin_update_row',10,2);
} }
if(!function_exists('wp_plugin_update_row')){ function wp_plugin_update_row($file, $plugin_data) {
    $c=get_site_transient('update_plugins');if(!is_object($c)||!isset($c->response[$file]))return false;
    $r=(object)$c->response[$file];$name=$plugin_data['Name']??$file;
    echo '<tr class="plugin-update-tr"><td colspan="4" class="plugin-update colspanchange"><div class="update-message notice inline notice-warning notice-alt"><p>'.sprintf(__('There is a new version of %1$s available. <a href="%2$s">View version %3$s details</a>.'),esc_html($name),esc_url($r->url??'#'),esc_html($r->new_version??'')).'</p></div></td></tr>';
} }
if(!function_exists('get_theme_updates')){ function get_theme_updates() {   // je Theme ein Objekt mit ->update und ->theme (WP_Theme kennt keine freien Eigenschaften)
    $c=get_site_transient('update_themes');if(!is_object($c)||empty($c->response))return [];
    $o=[];foreach((array)$c->response as $slug=>$u){ $t=wp_get_theme($slug);$o[$slug]=(object)['stylesheet'=>$slug,'theme'=>$t,'update'=>$u,'name'=>$t->get('Name')]; }
    return $o;
} }
if(!function_exists('wp_theme_update_rows')){ function wp_theme_update_rows() {
    $c=get_site_transient('update_themes');if(!is_object($c)||empty($c->response))return;
    foreach(array_keys((array)$c->response) as $s)add_action("after_theme_row_$s",'wp_theme_update_row',10,2);
} }
if(!function_exists('wp_theme_update_row')){ function wp_theme_update_row($theme_key, $theme) {
    $c=get_site_transient('update_themes');if(!is_object($c)||!isset($c->response[$theme_key]))return false;
    $r=(array)$c->response[$theme_key];$name=is_object($theme)&&method_exists($theme,'get')?$theme->get('Name'):$theme_key;
    echo '<tr class="plugin-update-tr"><td colspan="3" class="plugin-update colspanchange"><div class="update-message notice inline notice-warning notice-alt"><p>'.sprintf(__('There is a new version of %1$s available. <a href="%2$s">View version %3$s details</a>.'),esc_html((string)$name),esc_url($r['url']??'#'),esc_html($r['new_version']??'')).'</p></div></td></tr>';
} }
if(!function_exists('maintenance_nag')){ function maintenance_nag() {
    if(!wp_is_maintenance_mode()||!current_user_can('update_core'))return false;
    echo wp_get_admin_notice(__('An automated WordPress update has failed to complete - <a href="">please attempt the update again now</a>.'),['type'=>'warning','additional_classes'=>['update-nag','inline'],'paragraph_wrap'=>false]);
} }
if(!function_exists('wp_print_admin_notice_templates')){ function wp_print_admin_notice_templates() {
    echo '<script id="tmpl-wp-updates-admin-notice" type="text/html"><div <# if ( data.id ) { #>id="{{ data.id }}"<# } #> class="notice {{ data.className }}"><p>{{{ data.message }}}</p></div></script>'."\n";
    echo '<script id="tmpl-wp-bulk-updates-admin-notice" type="text/html"><div id="{{ data.id }}" class="{{ data.className }} notice <# if ( data.errors ) { #>notice-error<# } else { #>notice-success<# } #>"><p>{{{ data.message }}}</p></div></script>'."\n";
    echo '<script id="tmpl-wp-installs-admin-notice" type="text/html"><div class="notice notice-success"><p>{{{ data.message }}}</p></div></script>'."\n";
} }
if(!function_exists('wp_print_update_row_templates')){ function wp_print_update_row_templates() {
    echo '<script id="tmpl-item-update-row" type="text/template"><tr class="plugin-update-tr update" id="{{ data.slug }}-update" data-slug="{{ data.slug }}"><td colspan="{{ data.colspan }}" class="plugin-update colspanchange">{{{ data.content }}}</td></tr></script>'."\n";
} }
if(!function_exists('wp_recovery_mode_nag')){ function wp_recovery_mode_nag() { /* kein Wiederherstellungsmodus */ } }
if(!function_exists('wp_is_auto_update_enabled_for_type')){ function wp_is_auto_update_enabled_for_type($type) {   // Standard aus: das CMS führt keine automatischen Updates aus (per Filter überschreibbar)
    if(!in_array($type,['plugin','theme'],true))return false;
    if(defined('AUTOMATIC_UPDATER_DISABLED')&&AUTOMATIC_UPDATER_DISABLED||defined('DISALLOW_FILE_MODS')&&DISALLOW_FILE_MODS)return false;
    return (bool)apply_filters("{$type}s_auto_update_enabled",false);
} }
if(!function_exists('wp_is_auto_update_forced_for_item')){ function wp_is_auto_update_forced_for_item($type, $update, $item) {
    if(!wp_is_auto_update_enabled_for_type($type))return false;
    return apply_filters("auto_update_{$type}",$update,$item);
} }
if(!function_exists('wp_get_auto_update_message')){ function wp_get_auto_update_message() {
    $next=wp_next_scheduled('wp_version_check');
    if(!$next)return __('Automatic update not scheduled. There may be a problem with WP-Cron.');
    return sprintf(__('Automatic update scheduled for %s.'),wp_date(get_option('date_format','Y-m-d').' '.get_option('time_format','H:i'),$next));
} }

<?php
// Admin-Hilfen und Einstellungs-API (register_setting, add_settings_section/-field, settings_fields …), damit Plugin-Einstellungsseiten funktionieren.

function checked($checked, $current=true, $display=true) { return __checked_selected_helper($checked,$current,$display,'checked'); }
function selected($selected, $current=true, $display=true) { return __checked_selected_helper($selected,$current,$display,'selected'); }
function disabled($disabled, $current=true, $display=true) { return __checked_selected_helper($disabled,$current,$display,'disabled'); }
function readonly_wp($r, $current=true, $display=true) { return __checked_selected_helper($r,$current,$display,'readonly'); }
function wp_readonly($r, $current=true, $display=true) { return __checked_selected_helper($r,$current,$display,'readonly'); }
function __checked_selected_helper($helper, $current, $display, $type) {
    $r=((string)$helper===(string)$current)?" $type='$type'":'';
    if($display)echo $r;return $r;
}
function path_join($base, $path) { return preg_match('#^/|^[a-z]:[\\\\/]#i',(string)$path)?(string)$path:rtrim((string)$base,'/').'/'.ltrim((string)$path,'/'); }
function wp_basename($path, $suffix='') { return urldecode(basename(str_replace(['%2F','%5C'],'/',urlencode((string)$path)),$suffix)); }
function current_datetime() { return new DateTimeImmutable('now',wp_timezone()); }
function wp_get_current_commenter() { return ['comment_author'=>'','comment_author_email'=>'','comment_author_url'=>'']; }
function get_current_screen() { return $GLOBALS['elvado_wp_screen']??null; }
function get_admin_page_title() { return (string)($GLOBALS['title']??''); }
function self_admin_url($path='', $scheme='admin') { return admin_url($path,$scheme); }
function network_admin_url($path='', $scheme='admin') { return admin_url($path,$scheme); }
function get_admin_url($blog_id=null, $path='', $scheme='admin') { return admin_url($path,$scheme); }
function is_network_admin() { return false; }
function is_blog_admin() { return is_admin(); }
function is_user_admin() { return false; }
function menu_page_url($menu_slug, $display=true) { $u=admin_url('index.php?page='.rawurlencode((string)$menu_slug));if($display)echo esc_url($u);return $u; }
function get_plugin_page_hookname($plugin_page, $parent_page) { return 'toplevel_page_'.$plugin_page; }
function add_thickbox() {}
function wp_enqueue_media($args=[]) {}
function wp_enqueue_code_editor($args) { return false; }
function wp_enqueue_editor() {}
function wp_editor($content, $editor_id, $settings=[]) { echo '<textarea id="'.esc_attr($editor_id).'" name="'.esc_attr($settings['textarea_name']??$editor_id).'" rows="'.(int)($settings['textarea_rows']??10).'" style="width:100%">'.esc_textarea($content).'</textarea>'; }
function wp_admin_css($file='wp-admin', $force=false) {}
function wp_print_media_templates() {}
function wp_localize_jquery_ui_datepicker() {}
function get_available_languages($dir=null) { return []; }
function wp_get_installed_translations($type) { return []; }
function wp_get_pomo_file_data($f) { return []; }
function wp_http_supports($capabilities=[], $url=null) { return function_exists('curl_init'); }
function links_add_target($content, $target='_blank', $tags=['a']) { return preg_replace('/<a /','<a target="'.esc_attr($target).'" ',(string)$content); }
function wp_filesystem_dummy() {}
function get_filesystem_method($args=[], $context='', $allow_relaxed_file_ownership=false) { return 'direct'; }
function request_filesystem_credentials($form_post, $type='', $error=false, $context='', $extra_fields=null, $allow_relaxed_file_ownership=false) { return true; }
function WP_Filesystem($args=false, $context=false, $allow_relaxed_file_ownership=false) { if(class_exists('WP_Filesystem_Direct'))$GLOBALS['wp_filesystem']=new WP_Filesystem_Direct();return true; }

/* Einstellungs-API */
$GLOBALS['elvado_wp_settings']=$GLOBALS['elvado_wp_settings']??['registered'=>[],'sections'=>[],'fields'=>[]];
function register_setting($option_group, $option_name, $args=[]) {
    if(is_callable($args))$args=['sanitize_callback'=>$args];
    $GLOBALS['elvado_wp_settings']['registered'][$option_group][$option_name]=$args;
    if(!empty($args['sanitize_callback']))add_filter("sanitize_option_{$option_name}",$args['sanitize_callback'],10,3);
    if(is_array($args)&&array_key_exists('default',$args))add_filter("default_option_{$option_name}",fn($d)=>$args['default']);
}
function unregister_setting($option_group, $option_name, $deprecated='') { unset($GLOBALS['elvado_wp_settings']['registered'][$option_group][$option_name]); }
function get_registered_settings() { $o=[];foreach($GLOBALS['elvado_wp_settings']['registered'] as $g=>$s)foreach($s as $n=>$a)$o[$n]=$a;return $o; }
function add_settings_section($id, $title, $callback, $page, $args=[]) { $GLOBALS['elvado_wp_settings']['sections'][$page][$id]=['id'=>$id,'title'=>$title,'callback'=>$callback]; }
function add_settings_field($id, $title, $callback, $page, $section='default', $args=[]) { $GLOBALS['elvado_wp_settings']['fields'][$page][$section][$id]=['id'=>$id,'title'=>$title,'callback'=>$callback,'args'=>$args]; }
function settings_fields($option_group) {
    echo "<input type='hidden' name='option_page' value='".esc_attr($option_group)."' />";echo '<input type="hidden" name="action" value="update" />';wp_nonce_field("{$option_group}-options");
}
function do_settings_sections($page) {
    $s=$GLOBALS['elvado_wp_settings'];if(!isset($s['sections'][$page]))return;
    foreach($s['sections'][$page] as $sec){
        if($sec['title'])echo "<h2>{$sec['title']}</h2>\n";
        if($sec['callback'])call_user_func($sec['callback'],$sec);
        if(!isset($s['fields'][$page][$sec['id']]))continue;
        echo '<table class="form-table" role="presentation">';do_settings_fields($page,$sec['id']);echo '</table>';
    }
}
function do_settings_fields($page, $section) {
    $s=$GLOBALS['elvado_wp_settings'];if(!isset($s['fields'][$page][$section]))return;
    foreach($s['fields'][$page][$section] as $f){
        echo '<tr><th scope="row">'.($f['args']['label_for']??false?'<label for="'.esc_attr($f['args']['label_for']).'">'.$f['title'].'</label>':$f['title']).'</th><td>';
        call_user_func($f['callback'],$f['args']);echo '</td></tr>';
    }
}
function submit_button($text=null, $type='primary', $name='submit', $wrap=true, $other_attributes=null) {
    $text=$text??'Änderungen speichern';$attrs='';if(is_array($other_attributes))foreach($other_attributes as $k=>$v)$attrs.=' '.esc_attr($k).'="'.esc_attr($v).'"';
    $b='<input type="submit" name="'.esc_attr($name).'" id="'.esc_attr($name).'" class="button button-'.esc_attr(is_array($type)?implode(' button-',$type):$type).'" value="'.esc_attr($text).'"'.$attrs.' />';
    echo $wrap?'<p class="submit">'.$b.'</p>':$b;
}
/** Speichern von options.php-Formularen (Einstellungs-API): nur registrierte Optionen der angegebenen Gruppe. */
function elvado_wp_save_settings(array $post): array|WP_Error {
    $group=(string)($post['option_page']??'');if($group==='')return new WP_Error('no_group','Keine Optionsgruppe.');
    if(!wp_verify_nonce($post['_wpnonce']??'',"{$group}-options"))return new WP_Error('nonce','Der Sicherheitscode ist abgelaufen – bitte Seite neu laden.');
    $reg=$GLOBALS['elvado_wp_settings']['registered'][$group]??null;if(!$reg)return new WP_Error('unknown_group','Unbekannte Optionsgruppe.');
    $saved=[];
    foreach($reg as $name=>$args){
        $val=$post[$name]??null;
        if(is_string($val))$val=wp_unslash($val);
        if($val===null&&(($args['type']??'')==='boolean'))$val=false;
        update_option($name,$val);$saved[]=$name;
    }
    return $saved;
}

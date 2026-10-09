<?php
// Allgemeine Template-/Admin-Funktionen, die Plugins früh brauchen: Blog-Infos, Admin-Menüs (Registrierung), Einstellungs-API (Registrierung).

function get_bloginfo($show='', $filter='raw') {
    $m=['name'=>get_option('blogname'),'description'=>get_option('blogdescription'),'url'=>home_url(),'wpurl'=>site_url(),'siteurl'=>site_url(),'home'=>home_url(),'admin_email'=>get_option('admin_email'),
        'charset'=>'UTF-8','version'=>ELVADO_WP_VERSION,'html_type'=>'text/html','text_direction'=>'ltr','language'=>get_bloginfo_locale(),'stylesheet_url'=>'','template_url'=>'','pingback_url'=>site_url('/xmlrpc.php'),
        'rss2_url'=>home_url('/feed/'),'atom_url'=>home_url('/feed/atom/'),'rdf_url'=>home_url('/feed/rdf/'),'rss_url'=>home_url('/feed/rss/'),'comments_rss2_url'=>home_url('/comments/feed/'),'stylesheet_directory'=>'','template_directory'=>''];
    $v=$m[$show]??'';
    return apply_filters('bloginfo',$v,$show);
}
function bloginfo($show='') { echo get_bloginfo($show,'display'); }
function get_site_icon_url($size=512, $url='', $blog_id=0) { return (string)$url; }
function has_site_icon() { return false; }
function wp_get_wp_version() { return ELVADO_WP_VERSION; }

/* Admin-Menüs: werden registriert, damit Plugins laden; die Seiten rendert das CMS (Plugin-Einstellungen) bei Bedarf. */
$GLOBALS['elvado_wp_menu']=$GLOBALS['elvado_wp_menu']??[];
// Wie in WordPress gespiegelt: global $menu (Hauptpunkte) und $submenu (Unterpunkte) – viele Plugins lesen oder ändern sie (Reihenfolge, Umbenennen, Aufräumen).
$GLOBALS['menu']=$GLOBALS['menu']??[];$GLOBALS['submenu']=$GLOBALS['submenu']??[];
function add_menu_page($page_title, $menu_title, $capability, $menu_slug, $callback='', $icon_url='', $position=null) {
    $GLOBALS['elvado_wp_menu'][(string)$menu_slug]=['title'=>(string)$page_title,'menu'=>(string)$menu_title,'cap'=>(string)$capability,'cb'=>$callback,'parent'=>'','plugin'=>$GLOBALS['elvado_wp_loading']??'','hook'=>'toplevel_page_'.$menu_slug];
    foreach($GLOBALS['menu'] as $it)if(($it[2]??null)===(string)$menu_slug)return 'toplevel_page_'.$menu_slug;   // doppelter Aufruf (admin_menu mehrfach)
    $pos=$position!==null?(string)$position:null;if($pos===null||isset($GLOBALS['menu'][$pos])){ $pos=(string)($position!==null?(float)$position+0.001:100);while(isset($GLOBALS['menu'][$pos]))$pos=(string)((float)$pos+0.001); }
    $GLOBALS['menu'][$pos]=[(string)$menu_title,(string)$capability,(string)$menu_slug,(string)$page_title,'menu-top toplevel_page_'.$menu_slug,'toplevel_page_'.$menu_slug,(string)$icon_url];
    return 'toplevel_page_'.$menu_slug;
}
function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback='', $position=null) {
    $pre=['options-general.php'=>'settings_page_','tools.php'=>'tools_page_','themes.php'=>'appearance_page_','plugins.php'=>'plugins_page_','users.php'=>'users_page_','index.php'=>'dashboard_page_','edit.php'=>'posts_page_','upload.php'=>'media_page_'][(string)$parent_slug]??'';
    $hook=$pre!==''?$pre.$menu_slug:(isset($GLOBALS['elvado_wp_menu'][(string)$parent_slug])?sanitize_title((string)$GLOBALS['elvado_wp_menu'][(string)$parent_slug]['menu']).'_page_'.$menu_slug:'admin_page_'.$menu_slug);
    $GLOBALS['elvado_wp_menu'][(string)$menu_slug]=['title'=>(string)$page_title,'menu'=>(string)$menu_title,'cap'=>(string)$capability,'cb'=>$callback,'parent'=>(string)$parent_slug,'plugin'=>$GLOBALS['elvado_wp_loading']??'','hook'=>$hook];
    $row=[(string)$menu_title,(string)$capability,(string)$menu_slug,(string)$page_title];
    if(!isset($GLOBALS['submenu'][(string)$parent_slug]))$GLOBALS['submenu'][(string)$parent_slug]=[];
    foreach($GLOBALS['submenu'][(string)$parent_slug] as $it)if(($it[2]??null)===(string)$menu_slug)return $hook;
    if($position!==null&&(int)$position<count($GLOBALS['submenu'][(string)$parent_slug])){ array_splice($GLOBALS['submenu'][(string)$parent_slug],(int)$position,0,[$row]); }
    else $GLOBALS['submenu'][(string)$parent_slug][]=$row;
    return $hook;
}
function add_options_page($t,$m,$c,$s,$cb='',$p=null) { return add_submenu_page('options-general.php',$t,$m,$c,$s,$cb,$p); }
function add_management_page($t,$m,$c,$s,$cb='',$p=null) { return add_submenu_page('tools.php',$t,$m,$c,$s,$cb,$p); }
function add_theme_page($t,$m,$c,$s,$cb='',$p=null) { return add_submenu_page('themes.php',$t,$m,$c,$s,$cb,$p); }
function add_plugins_page($t,$m,$c,$s,$cb='',$p=null) { return add_submenu_page('plugins.php',$t,$m,$c,$s,$cb,$p); }
function add_users_page($t,$m,$c,$s,$cb='',$p=null) { return add_submenu_page('users.php',$t,$m,$c,$s,$cb,$p); }
function add_dashboard_page($t,$m,$c,$s,$cb='',$p=null) { return add_submenu_page('index.php',$t,$m,$c,$s,$cb,$p); }
function add_posts_page($t,$m,$c,$s,$cb='',$p=null) { return add_submenu_page('edit.php',$t,$m,$c,$s,$cb,$p); }
function add_pages_page($t,$m,$c,$s,$cb='',$p=null) { return add_submenu_page('edit.php?post_type=page',$t,$m,$c,$s,$cb,$p); }
function add_media_page($t,$m,$c,$s,$cb='',$p=null) { return add_submenu_page('upload.php',$t,$m,$c,$s,$cb,$p); }
function remove_menu_page($s) { if(isset($GLOBALS['elvado_wp_menu'][$s]))$GLOBALS['elvado_wp_menu'][$s]['hidden']=true;foreach((array)($GLOBALS['menu']??[]) as $k=>$it)if(($it[2]??null)===$s){ unset($GLOBALS['menu'][$k]);return $it; }return []; }   // nur aus dem Menü nehmen – die Seite bleibt (wie in WordPress) aufrufbar
function remove_submenu_page($p,$s) { if(isset($GLOBALS['elvado_wp_menu'][$s]))$GLOBALS['elvado_wp_menu'][$s]['hidden']=true;foreach((array)($GLOBALS['submenu'][$p]??[]) as $k=>$it)if(($it[2]??null)===$s){ unset($GLOBALS['submenu'][$p][$k]);return $it; }return []; }
function add_meta_box($id,$title,$callback,$screen=null,$context='advanced',$priority='default',$args=null) {}
function remove_meta_box($id,$screen,$context) {}
function add_settings_error($setting,$code,$message,$type='error') { $GLOBALS['elvado_wp_settings_errors'][]=compact('setting','code','message','type'); }
function get_settings_errors($setting='',$sanitize=false) { return $GLOBALS['elvado_wp_settings_errors']??[]; }
function settings_errors($setting='',$sanitize=false,$hide_on_update=false) {
    foreach(get_settings_errors() as $e)echo '<div class="notice notice-'.esc_attr($e['type']==='updated'?'success':$e['type']).'"><p>'.wp_kses_post($e['message']).'</p></div>';
}
function add_action_admin_notices_dummy() {}

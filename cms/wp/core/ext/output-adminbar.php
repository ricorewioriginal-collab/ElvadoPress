<?php
// Ergänzende Admin-Leisten-Funktionen (Bereich Ausgabe): Knoten der WordPress-Werkzeugleiste. Die Funktionen fügen Knoten in ein WP_Admin_Bar-Objekt ein;
// die Leiste selbst wird vom CMS nicht angezeigt (is_admin_bar_showing() = false), Plugins können die Knoten aber lesen. Multisite-Menüs sind bewusst leer.

if(!class_exists('WP_Admin_Bar')){
    /** Schlanke Werkzeugleiste: Knoten sammeln (add_node/add_group/remove_node) und als verschachtelte Liste ausgeben. */
    class WP_Admin_Bar {
        public $nodes=[];public $user;
        public function __get($n) { return $n==='proto'?(is_ssl()?'https://':'http://'):null; }
        public function initialize() { $this->user=new stdClass();$this->user->blogs=[];$this->nodes=[]; }
        public function add_menu($args) { $this->add_node($args); }
        public function add_node($args) {
            $args=(array)$args;if(empty($args['id']))return;
            $id=$args['id'];$old=$this->nodes[$id]??null;
            $this->nodes[$id]=(object)array_merge(['id'=>$id,'title'=>'','parent'=>'','href'=>'','group'=>false,'meta'=>[]],$old?(array)$old:[],$args);
        }
        public function add_group($args) { $args=(array)$args;$args['group']=true;$this->add_node($args); }
        public function remove_node($id) { unset($this->nodes[$id]); }
        public function get_node($id) { return $this->nodes[$id]??null; }
        public function get_nodes() { return $this->nodes; }
        public function add_menus() { do_action('add_admin_bar_menus'); }
        public function render() { do_action_ref_array('admin_bar_menu',[&$this]);$this->output(''); }
        public function output($parent) {
            $kids=array_filter($this->nodes,fn($n)=>$n->parent===$parent);if(!$kids)return;
            echo '<ul>';foreach($kids as $n){ echo '<li id="wp-admin-bar-'.esc_attr($n->id).'">'.($n->href?'<a href="'.esc_url($n->href).'">'.$n->title.'</a>':$n->title);$this->output($n->id);echo '</li>'; }echo '</ul>';
        }
    }
}
if(!function_exists('_wp_admin_bar_init')){
    function _wp_admin_bar_init() {
        global $wp_admin_bar;if(!is_admin_bar_showing())return false;
        $class=apply_filters('wp_admin_bar_class','WP_Admin_Bar');if(!class_exists($class))return false;
        $wp_admin_bar=new $class();$wp_admin_bar->initialize();$wp_admin_bar->add_menus();return true;
    }
}
if(!function_exists('_get_admin_bar_pref')){
    function _get_admin_bar_pref($context='front', $user=0) { $p=get_user_option("show_admin_bar_{$context}",$user);return $p===false?true:$p==='true'; }
}
if(!function_exists('wp_admin_bar_add_secondary_groups')){
    function wp_admin_bar_add_secondary_groups($wp_admin_bar) {
        $wp_admin_bar->add_group(['id'=>'top-secondary','meta'=>['class'=>'ab-top-secondary']]);
        $wp_admin_bar->add_group(['parent'=>'wp-logo','id'=>'wp-logo-external','meta'=>['class'=>'ab-sub-secondary']]);
    }
}
if(!function_exists('wp_admin_bar_wp_menu')){
    function wp_admin_bar_wp_menu($wp_admin_bar) {
        $about=current_user_can('read')?self_admin_url('about.php'):false;
        $a=['id'=>'wp-logo','title'=>'<span class="ab-icon" aria-hidden="true"></span><span class="screen-reader-text">Über WordPress</span>','href'=>$about,'meta'=>['menu_title'=>'Über WordPress']];
        if(!$about)$a['meta']=['tabindex'=>0];
        $wp_admin_bar->add_node($a);
        if($about)$wp_admin_bar->add_node(['parent'=>'wp-logo','id'=>'about','title'=>'Über WordPress','href'=>$about]);
        $wp_admin_bar->add_node(['parent'=>'wp-logo-external','id'=>'wporg','title'=>'WordPress.org','href'=>'https://de.wordpress.org/']);
        $wp_admin_bar->add_node(['parent'=>'wp-logo-external','id'=>'documentation','title'=>'Dokumentation','href'=>'https://developer.wordpress.org/']);
        $wp_admin_bar->add_node(['parent'=>'wp-logo-external','id'=>'support-forums','title'=>'Support','href'=>'https://de.wordpress.org/support/forums/']);
    }
}
if(!function_exists('wp_admin_bar_sidebar_toggle')){
    function wp_admin_bar_sidebar_toggle($wp_admin_bar) { if(is_admin())$wp_admin_bar->add_node(['id'=>'menu-toggle','title'=>'<span class="ab-icon" aria-hidden="true"></span><span class="screen-reader-text">Menü</span>','href'=>'#']); }
}
if(!function_exists('wp_admin_bar_my_account_item')){
    function wp_admin_bar_my_account_item($wp_admin_bar) {
        $uid=get_current_user_id();if(!$uid)return;$u=wp_get_current_user();
        $profile=current_user_can('read')?get_edit_profile_url($uid):false;$avatar=get_avatar($uid,26);
        $wp_admin_bar->add_node(['id'=>'my-account','parent'=>'top-secondary','title'=>sprintf('Hallo, %s','<span class="display-name">'.$u->display_name.'</span>').$avatar,'href'=>$profile,'meta'=>['class'=>$avatar?'with-avatar':'','menu_title'=>$u->display_name,'tabindex'=>$profile!==false?'':0]]);
    }
}
if(!function_exists('wp_admin_bar_my_account_menu')){
    function wp_admin_bar_my_account_menu($wp_admin_bar) {
        $uid=get_current_user_id();if(!$uid)return;$u=wp_get_current_user();$profile=current_user_can('read')?get_edit_profile_url($uid):false;
        $wp_admin_bar->add_group(['parent'=>'my-account','id'=>'user-actions']);
        $info=get_avatar($uid,64).'<span class="display-name">'.$u->display_name.'</span>';if($u->display_name!==$u->user_login)$info.='<span class="username">'.$u->user_login.'</span>';
        $wp_admin_bar->add_node(['parent'=>'user-actions','id'=>'user-info','title'=>$info,'href'=>$profile]);
        if($profile)$wp_admin_bar->add_node(['parent'=>'user-actions','id'=>'edit-profile','title'=>'Profil bearbeiten','href'=>$profile]);
        $wp_admin_bar->add_node(['parent'=>'user-actions','id'=>'logout','title'=>'Abmelden','href'=>wp_logout_url()]);
    }
}
if(!function_exists('wp_admin_bar_site_menu')){
    function wp_admin_bar_site_menu($wp_admin_bar) {
        if(!is_user_logged_in())return;
        $name=get_bloginfo('name');if(!$name)$name=preg_replace('#^(https?://)?(www.)?#','',get_home_url());
        $wp_admin_bar->add_node(['id'=>'site-name','title'=>$name,'href'=>(is_admin()||!current_user_can('read'))?home_url('/'):admin_url()]);
        if(is_admin())$wp_admin_bar->add_node(['parent'=>'site-name','id'=>'view-site','title'=>'Website anzeigen','href'=>home_url('/')]);
        elseif(current_user_can('read')){
            $wp_admin_bar->add_node(['parent'=>'site-name','id'=>'dashboard','title'=>'Dashboard','href'=>admin_url()]);
            wp_admin_bar_appearance_menu($wp_admin_bar);
            if(current_user_can('activate_plugins'))$wp_admin_bar->add_node(['parent'=>'site-name','id'=>'plugins','title'=>'Plugins','href'=>admin_url('plugins.php')]);
        }
    }
}
if(!function_exists('wp_admin_bar_edit_site_menu')){
    function wp_admin_bar_edit_site_menu($wp_admin_bar) {
        if(is_admin()||!wp_is_block_theme()||!current_user_can('edit_theme_options'))return;
        $wp_admin_bar->add_node(['id'=>'site-editor','title'=>'Website bearbeiten','href'=>admin_url('site-editor.php')]);
    }
}
if(!function_exists('wp_admin_bar_customize_menu')){
    function wp_admin_bar_customize_menu($wp_admin_bar) {
        if(is_admin()||!current_user_can('customize'))return;
        $cur=(is_ssl()?'https://':'http://').($_SERVER['HTTP_HOST']??'localhost').($_SERVER['REQUEST_URI']??'/');
        $wp_admin_bar->add_node(['id'=>'customize','title'=>'Anpassen','href'=>add_query_arg('url',urlencode($cur),admin_url('customize.php')),'meta'=>['class'=>'hide-if-no-customize']]);
    }
}
if(!function_exists('wp_admin_bar_my_sites_menu')){ function wp_admin_bar_my_sites_menu($wp_admin_bar) { /* Multisite: Einzelwebsite hat kein „Meine Websites“ */ } }
if(!function_exists('wp_admin_bar_shortlink_menu')){
    function wp_admin_bar_shortlink_menu($wp_admin_bar) {
        $s=wp_get_shortlink(0,'query');if(empty($s))return;
        $wp_admin_bar->add_node(['id'=>'get-shortlink','title'=>'Kurzlink','href'=>$s,'meta'=>['html'=>'<input class="shortlink-input" type="text" readonly="readonly" value="'.esc_attr($s).'" aria-label="Kurzlink" />']]);
    }
}
if(!function_exists('wp_admin_bar_edit_menu')){
    /** Nur die Seitenansicht: „Bearbeiten“ für das abgefragte Objekt (Beitrag, Begriff, Benutzer); in der Verwaltung ohne Wirkung. */
    function wp_admin_bar_edit_menu($wp_admin_bar) {
        if(is_admin())return;
        $o=get_queried_object();if(empty($o))return;$title=null;$link=null;
        if(!empty($o->post_type)){ $pt=get_post_type_object($o->post_type);$link=get_edit_post_link($o->ID);if($pt&&$link&&current_user_can('edit_post',$o->ID)&&($pt->show_in_admin_bar??true))$title=$pt->labels->edit_item??'Bearbeiten'; }
        elseif(!empty($o->taxonomy)){ $tx=get_taxonomy($o->taxonomy);$link=get_edit_term_link($o->term_id,$o->taxonomy);if($tx&&$link)$title=$tx->labels->edit_item??'Bearbeiten'; }
        elseif($o instanceof WP_User&&current_user_can('edit_user',$o->ID)){ $link=get_edit_user_link($o->ID);$title='Benutzer bearbeiten'; }
        if($title!==null&&$link)$wp_admin_bar->add_node(['id'=>'edit','title'=>$title,'href'=>$link]);
    }
}
if(!function_exists('wp_admin_bar_new_content_menu')){
    function wp_admin_bar_new_content_menu($wp_admin_bar) {
        $actions=[];$cpts=(array)get_post_types(['show_in_admin_bar'=>true],'objects');
        if(!$cpts)foreach(['post','page'] as $t)if($o=get_post_type_object($t))$cpts[$t]=$o;   // Vorgabe, wenn die Typen kein show_in_admin_bar melden
        $can=fn($o)=>current_user_can($o->cap->create_posts??'edit_posts');
        if(isset($cpts['post'])&&$can($cpts['post']))$actions['post-new.php']=[($cpts['post']->labels->name_admin_bar??'Beitrag'),'new-post'];
        if(isset($cpts['attachment'])&&current_user_can('upload_files'))$actions['media-new.php']=['Mediendatei','new-media'];
        if(isset($cpts['page'])&&$can($cpts['page']))$actions['post-new.php?post_type=page']=[($cpts['page']->labels->name_admin_bar??'Seite'),'new-page'];
        unset($cpts['post'],$cpts['page'],$cpts['attachment']);
        foreach($cpts as $c)if($can($c))$actions['post-new.php?post_type='.$c->name]=[$c->labels->name_admin_bar??$c->label??$c->name,'new-'.$c->name];
        if(current_user_can('create_users'))$actions['user-new.php']=['Benutzer','new-user'];
        if(!$actions)return;
        $wp_admin_bar->add_node(['id'=>'new-content','title'=>'<span class="ab-icon" aria-hidden="true"></span><span class="ab-label">Neu</span>','href'=>admin_url(current(array_keys($actions))),'meta'=>['menu_title'=>'Neu']]);
        foreach($actions as $link=>[$t,$id])$wp_admin_bar->add_node(['parent'=>'new-content','id'=>$id,'title'=>$t,'href'=>admin_url($link)]);
    }
}
if(!function_exists('wp_admin_bar_comments_menu')){
    function wp_admin_bar_comments_menu($wp_admin_bar) {
        if(!current_user_can('edit_posts'))return;
        $n=(int)(wp_count_comments()->moderated??0);$txt=sprintf($n===1?'%s Kommentar in Moderation':'%s Kommentare in Moderation',number_format_i18n($n));
        $title='<span class="ab-icon" aria-hidden="true"></span><span class="ab-label awaiting-mod pending-count count-'.$n.'" aria-hidden="true">'.number_format_i18n($n).'</span><span class="screen-reader-text comments-in-moderation-text">'.$txt.'</span>';
        $wp_admin_bar->add_node(['id'=>'comments','title'=>$title,'href'=>admin_url('edit-comments.php')]);
    }
}
if(!function_exists('wp_admin_bar_appearance_menu')){
    function wp_admin_bar_appearance_menu($wp_admin_bar) {
        $wp_admin_bar->add_group(['parent'=>'site-name','id'=>'appearance']);
        if(current_user_can('switch_themes'))$wp_admin_bar->add_node(['parent'=>'appearance','id'=>'themes','title'=>'Themes','href'=>admin_url('themes.php')]);
        if(!current_user_can('edit_theme_options'))return;
        if(current_theme_supports('widgets'))$wp_admin_bar->add_node(['parent'=>'appearance','id'=>'widgets','title'=>'Widgets','href'=>admin_url('widgets.php')]);
        if(current_theme_supports('menus')||current_theme_supports('widgets'))$wp_admin_bar->add_node(['parent'=>'appearance','id'=>'menus','title'=>'Menüs','href'=>admin_url('nav-menus.php')]);
        if(current_theme_supports('custom-background'))$wp_admin_bar->add_node(['parent'=>'appearance','id'=>'background','title'=>'Hintergrund','href'=>admin_url('themes.php?page=custom-background'),'meta'=>['class'=>'hide-if-customize']]);
        if(current_theme_supports('custom-header'))$wp_admin_bar->add_node(['parent'=>'appearance','id'=>'header','title'=>'Kopfzeile','href'=>admin_url('themes.php?page=custom-header'),'meta'=>['class'=>'hide-if-customize']]);
    }
}
if(!function_exists('wp_admin_bar_updates_menu')){
    /** Updates kennt die Schicht nicht (wp_get_update_data fehlt) – dann kein Knoten. */
    function wp_admin_bar_updates_menu($wp_admin_bar) {
        if(!function_exists('wp_get_update_data'))return;
        $d=wp_get_update_data();if(empty($d['counts']['total']))return;
        $wp_admin_bar->add_node(['id'=>'updates','title'=>'<span class="ab-icon" aria-hidden="true"></span><span class="ab-label">'.number_format_i18n($d['counts']['total']).'</span>','href'=>network_admin_url('update-core.php')]);
    }
}
if(!function_exists('wp_admin_bar_search_menu')){
    function wp_admin_bar_search_menu($wp_admin_bar) {
        if(is_admin())return;
        $f='<form action="'.esc_url(home_url('/')).'" method="get" id="adminbarsearch"><input class="adminbar-input" name="s" id="adminbar-search" type="text" value="" maxlength="150" /><label for="adminbar-search" class="screen-reader-text">Suche</label><input type="submit" class="adminbar-button" value="Suche" /></form>';
        $wp_admin_bar->add_node(['parent'=>'top-secondary','id'=>'search','title'=>$f,'meta'=>['class'=>'admin-bar-search','tabindex'=>-1]]);
    }
}
if(!function_exists('wp_admin_bar_recovery_mode_menu')){
    /** Wiederherstellungsmodus gibt es nicht (wp_is_recovery_mode fehlt) – dann kein Knoten. */
    function wp_admin_bar_recovery_mode_menu($wp_admin_bar) {
        if(!function_exists('wp_is_recovery_mode')||!wp_is_recovery_mode())return;
        $wp_admin_bar->add_node(['parent'=>'top-secondary','id'=>'recovery-mode','title'=>'Wiederherstellungsmodus beenden','href'=>wp_nonce_url(add_query_arg('action','exit_recovery_mode',wp_login_url()),'exit_recovery_mode')]);
    }
}
if(!function_exists('wp_enqueue_admin_bar_header_styles')){
    function wp_enqueue_admin_bar_header_styles() {
        if(!wp_style_is('admin-bar','registered'))wp_register_style('admin-bar',false);
        wp_add_inline_style('admin-bar','@media print { #wpadminbar { display:none; } }');
    }
}
if(!function_exists('wp_enqueue_admin_bar_bump_styles')){
    function wp_enqueue_admin_bar_bump_styles() {
        $cb='_admin_bar_bump_cb';
        if(current_theme_supports('admin-bar')){ $s=get_theme_support('admin-bar');$cb=$s[0]['callback']??$cb; }
        if($cb!=='_admin_bar_bump_cb')return;
        if(!wp_style_is('admin-bar','registered'))wp_register_style('admin-bar',false);
        wp_add_inline_style('admin-bar','@media screen { html { margin-top: 32px !important; } } @media screen and ( max-width: 782px ) { html { margin-top: 46px !important; } }');
    }
}

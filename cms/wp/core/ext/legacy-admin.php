<?php
// Veraltete WordPress-Funktionen (wp-admin/includes/deprecated.php): Editor, Medien, Dashboard, Bildschirm, Benutzerlisten, Aktualisierung.
// Reine Oberflächenreste sind No-ops; der Rest delegiert an den Ersatz. Meldung über rrw_ext_lg_dep() (legacy-core.php).

if(!function_exists('rrw_ext_lg_users_cap')){
    /** IDs der Benutzer mit (oder ohne) einer Fähigkeit. */
    function rrw_ext_lg_users_cap(string $cap,bool $has=true): array {
        $out=[];
        foreach(get_users() as $u)if(rrw_ext_lg_can($u->ID,$cap)===$has)$out[]=(int)$u->ID;
        return $out;
    }
}

/* ───────── Editor / Codepress / Bildschirm (No-ops) ───────── */
if(!function_exists('tinymce_include')){
    function tinymce_include(){ rrw_ext_lg_dep(__FUNCTION__,'2.1.0','wp_editor()'); }
}
if(!function_exists('documentation_link')){
    function documentation_link(){ rrw_ext_lg_dep(__FUNCTION__,'2.5.0'); }
}
if(!function_exists('codepress_get_lang')){
    function codepress_get_lang($filename){ rrw_ext_lg_dep(__FUNCTION__,'3.0.0');return ''; }
}
if(!function_exists('codepress_footer_js')){
    function codepress_footer_js(){ rrw_ext_lg_dep(__FUNCTION__,'3.0.0'); }
}
if(!function_exists('use_codepress')){
    function use_codepress(){ rrw_ext_lg_dep(__FUNCTION__,'3.0.0'); }
}
if(!function_exists('wp_tiny_mce')){
    function wp_tiny_mce($teeny=false,$settings=false){ rrw_ext_lg_dep(__FUNCTION__,'3.3.0','wp_editor()'); }
}
if(!function_exists('wp_preload_dialogs')){
    function wp_preload_dialogs($init){ rrw_ext_lg_dep(__FUNCTION__,'3.3.0','wp_editor()'); }
}
if(!function_exists('wp_print_editor_js')){
    function wp_print_editor_js(){ rrw_ext_lg_dep(__FUNCTION__,'3.3.0','wp_editor()'); }
}
if(!function_exists('wp_quicktags')){
    function wp_quicktags(){ rrw_ext_lg_dep(__FUNCTION__,'3.3.0','wp_editor()'); }
}
if(!function_exists('screen_layout')){
    function screen_layout($screen){ rrw_ext_lg_dep(__FUNCTION__,'3.3.0','WP_Screen');return ''; }
}
if(!function_exists('screen_options')){
    function screen_options($screen){ rrw_ext_lg_dep(__FUNCTION__,'3.3.0','WP_Screen');return ''; }
}
if(!function_exists('screen_meta')){
    function screen_meta($screen){ rrw_ext_lg_dep(__FUNCTION__,'3.3.0','WP_Screen'); }
}
if(!function_exists('favorite_actions')){
    function favorite_actions($screen=null){ rrw_ext_lg_dep(__FUNCTION__,'3.2.0','WP_Admin_Bar');return ''; }
}
if(!function_exists('add_contextual_help')){
    function add_contextual_help($screen,$help){
        rrw_ext_lg_dep(__FUNCTION__,'3.3.0','get_current_screen()->add_help_tab()');
        global $_wp_contextual_help;
        $id=is_object($screen)?($screen->id??''):(string)$screen;
        $_wp_contextual_help[$id]=$help;
    }
}
if(!function_exists('screen_icon')){
    function screen_icon(){ rrw_ext_lg_dep(__FUNCTION__,'3.8.0');echo get_screen_icon(); }
}
if(!function_exists('get_screen_icon')){
    function get_screen_icon($screen=''){ rrw_ext_lg_dep(__FUNCTION__,'3.8.0');return '<div id="icon-options-general" class="icon32"><br /></div>'; }
}
if(!function_exists('post_form_autocomplete_off')){
    function post_form_autocomplete_off(){ rrw_ext_lg_dep(__FUNCTION__,'3.4.0'); }
}
if(!function_exists('options_permalink_add_js')){
    function options_permalink_add_js(){ rrw_ext_lg_dep(__FUNCTION__,'3.4.0'); }
}
if(!function_exists('_wp_privacy_requests_screen_options')){
    function _wp_privacy_requests_screen_options(){ rrw_ext_lg_dep(__FUNCTION__,'5.3.0'); }
}
if(!function_exists('wp_nav_menu_locations_meta_box')){
    function wp_nav_menu_locations_meta_box(){ rrw_ext_lg_dep(__FUNCTION__,'3.6.0'); }
}

/* ───────── Maße / Dateien / Bilder ───────── */
if(!function_exists('wp_shrink_dimensions')){
    function wp_shrink_dimensions($width,$height,$wmax=128,$hmax=96){ rrw_ext_lg_dep(__FUNCTION__,'3.0.0','wp_constrain_dimensions()');return wp_constrain_dimensions($width,$height,$wmax,$hmax); }
}
if(!function_exists('get_udims')){
    function get_udims($width,$height){ rrw_ext_lg_dep(__FUNCTION__,'3.0.0','wp_constrain_dimensions()');return wp_constrain_dimensions($width,$height,128,96); }
}
if(!function_exists('get_real_file_to_edit')){
    function get_real_file_to_edit($file){ rrw_ext_lg_dep(__FUNCTION__,'2.9.0');return rtrim(WP_CONTENT_DIR,'/').'/'.ltrim((string)$file,'/'); }
}
if(!function_exists('wp_create_thumbnail')){
    function wp_create_thumbnail($file,$max_side,$deprecated=''){
        rrw_ext_lg_dep(__FUNCTION__,'3.5.0','image_resize()');
        $r=image_resize($file,$max_side,$max_side);
        return is_wp_error($r)?$r->get_error_message():$r;
    }
}

/* ───────── Kategorien-Auswahl / Optionen ───────── */
if(!function_exists('dropdown_categories')){
    function dropdown_categories($default=0,$parent=0,$popular_ids=[]){
        global $post_ID;
        rrw_ext_lg_dep(__FUNCTION__,'2.6.0','wp_category_checklist()');
        if(function_exists('wp_category_checklist'))wp_category_checklist($post_ID);
    }
}
if(!function_exists('dropdown_link_categories')){
    function dropdown_link_categories($default=0){
        global $link_id;
        rrw_ext_lg_dep(__FUNCTION__,'2.6.0','wp_link_category_checklist()');
        if(function_exists('wp_link_category_checklist'))wp_link_category_checklist($link_id);
    }
}
if(!function_exists('wp_dropdown_cats')){
    function wp_dropdown_cats($currentcat=0,$currentparent=0,$parent=0,$level=0,$categories=0){
        rrw_ext_lg_dep(__FUNCTION__,'3.0.0','wp_dropdown_categories()');
        return wp_dropdown_categories(['selected'=>$currentcat,'child_of'=>$parent,'hide_empty'=>0,'echo'=>1]);
    }
}
if(!function_exists('add_option_update_handler')){
    function add_option_update_handler($option_group,$option_name,$sanitize_callback=''){
        rrw_ext_lg_dep(__FUNCTION__,'3.0.0','register_setting()');
        if(function_exists('register_setting'))register_setting($option_group,$option_name,$sanitize_callback);
    }
}
if(!function_exists('remove_option_update_handler')){
    function remove_option_update_handler($option_group,$option_name,$sanitize_callback=''){
        rrw_ext_lg_dep(__FUNCTION__,'3.0.0','unregister_setting()');
        if(function_exists('unregister_setting'))unregister_setting($option_group,$option_name,$sanitize_callback);
    }
}

/* ───────── Benutzer / Autoren ───────── */
if(!function_exists('get_author_user_ids')){
    function get_author_user_ids(){ rrw_ext_lg_dep(__FUNCTION__,'3.1.0','get_users()');return rrw_ext_lg_users_cap('edit_posts'); }
}
if(!function_exists('get_editable_user_ids')){
    function get_editable_user_ids($user_id,$exclude_zeros=true,$post_type='post'){
        rrw_ext_lg_dep(__FUNCTION__,'3.1.0','get_users()');
        $o=get_post_type_object($post_type);
        $others=$o->cap->edit_others_posts??'edit_others_posts';$edit=$o->cap->edit_posts??'edit_posts';
        if(!rrw_ext_lg_can($user_id,$others))return (rrw_ext_lg_can($user_id,$edit)||!$exclude_zeros)?[(int)$user_id]:[];
        return $exclude_zeros?rrw_ext_lg_users_cap($edit):array_map(fn($u)=>(int)$u->ID,get_users());
    }
}
if(!function_exists('get_editable_authors')){
    function get_editable_authors($user_id){
        rrw_ext_lg_dep(__FUNCTION__,'3.1.0','get_users()');
        $ids=get_editable_user_ids($user_id);
        return $ids?get_users(['include'=>$ids,'orderby'=>'display_name']):false;
    }
}
if(!function_exists('get_nonauthor_user_ids')){
    function get_nonauthor_user_ids(){ rrw_ext_lg_dep(__FUNCTION__,'3.1.0','get_users()');return rrw_ext_lg_users_cap('edit_posts',false); }
}
if(!function_exists('get_others_unpublished_posts')){
    function get_others_unpublished_posts($user_id,$type='any'){
        global $wpdb;
        rrw_ext_lg_dep(__FUNCTION__,'3.1.0','get_posts()');
        $ids=array_values(array_filter(get_editable_user_ids($user_id),fn($i)=>$i!=(int)$user_id));
        if(!$ids)return '';
        $st=in_array($type,['draft','pending'],true)?[$type]:['draft','pending'];
        $dir='pending'===$type?'ASC':'DESC';
        $q="SELECT * FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status IN ('".implode("','",$st)."') AND post_author IN (".implode(',',array_map('intval',$ids)).") ORDER BY post_modified $dir";
        return $wpdb->get_results($q);
    }
}
if(!function_exists('get_others_drafts')){
    function get_others_drafts($user_id){ rrw_ext_lg_dep(__FUNCTION__,'3.1.0');return get_others_unpublished_posts($user_id,'draft'); }
}
if(!function_exists('get_others_pending')){
    function get_others_pending($user_id){ rrw_ext_lg_dep(__FUNCTION__,'3.1.0');return get_others_unpublished_posts($user_id,'pending'); }
}
if(!function_exists('_relocate_children')){
    function _relocate_children($old_ID,$new_ID){
        global $wpdb;
        rrw_ext_lg_dep(__FUNCTION__,'3.1.0');
        foreach($wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d",(int)$old_ID)) as $id)
            $wpdb->update($wpdb->posts,['post_parent'=>(int)$new_ID],['ID'=>(int)$id]);
    }
}

/* ───────── Beiträge / Medien-Upload ───────── */
if(!function_exists('get_post_to_edit')){
    function get_post_to_edit($id){
        rrw_ext_lg_dep(__FUNCTION__,'3.5.0','get_post()');
        $post=get_post($id,OBJECT,'edit');
        if($post&&$post->post_type==='page')$post->page_template=get_post_meta($id,'_wp_page_template',true);
        return $post;
    }
}
if(!function_exists('get_default_page_to_edit')){
    function get_default_page_to_edit(){
        rrw_ext_lg_dep(__FUNCTION__,'3.5.0','get_default_post_to_edit()');
        $p=get_default_post_to_edit('page');
        $p->post_type='page';
        return $p;
    }
}
if(!function_exists('media_upload_image')){
    function media_upload_image(){ rrw_ext_lg_dep(__FUNCTION__,'3.3.0','wp_media_upload_handler()');return wp_media_upload_handler(); }
}
if(!function_exists('media_upload_audio')){
    function media_upload_audio(){ rrw_ext_lg_dep(__FUNCTION__,'3.3.0','wp_media_upload_handler()');return wp_media_upload_handler(); }
}
if(!function_exists('media_upload_video')){
    function media_upload_video(){ rrw_ext_lg_dep(__FUNCTION__,'3.3.0','wp_media_upload_handler()');return wp_media_upload_handler(); }
}
if(!function_exists('media_upload_file')){
    function media_upload_file(){ rrw_ext_lg_dep(__FUNCTION__,'3.3.0','wp_media_upload_handler()');return wp_media_upload_handler(); }
}
if(!function_exists('type_url_form_image')){
    function type_url_form_image(){ rrw_ext_lg_dep(__FUNCTION__,'3.3.0','wp_media_insert_url_form(\'image\')');return wp_media_insert_url_form('image'); }
}
if(!function_exists('type_url_form_audio')){
    function type_url_form_audio(){ rrw_ext_lg_dep(__FUNCTION__,'3.3.0','wp_media_insert_url_form(\'audio\')');return wp_media_insert_url_form('audio'); }
}
if(!function_exists('type_url_form_video')){
    function type_url_form_video(){ rrw_ext_lg_dep(__FUNCTION__,'3.3.0','wp_media_insert_url_form(\'video\')');return wp_media_insert_url_form('video'); }
}
if(!function_exists('type_url_form_file')){
    function type_url_form_file(){ rrw_ext_lg_dep(__FUNCTION__,'3.3.0','wp_media_insert_url_form(\'file\')');return wp_media_insert_url_form('file'); }
}
if(!function_exists('_insert_into_post_button')){
    function _insert_into_post_button($type){ rrw_ext_lg_dep(__FUNCTION__,'3.3.0');return ''; }
}
if(!function_exists('_media_button')){
    function _media_button($title,$icon,$type,$id){ rrw_ext_lg_dep(__FUNCTION__,'3.5.0');return ''; }
}
if(!function_exists('the_attachment_links')){
    function the_attachment_links($id=false){
        rrw_ext_lg_dep(__FUNCTION__,'3.7.0');
        $p=get_post($id);
        if(!$p||'attachment'!=$p->post_type)return;
        echo "<form id='the-attachment-links'><textarea readonly='readonly'>".esc_textarea((string)wp_get_attachment_url($p->ID))."</textarea></form>\n";
    }
}
if(!function_exists('image_attachment_fields_to_save')){
    function image_attachment_fields_to_save($post,$attachment){ rrw_ext_lg_dep(__FUNCTION__,'3.5.0');return $post; }
}

/* ───────── Themes / Aktualisierung ───────── */
if(!function_exists('get_allowed_themes')){
    function get_allowed_themes(){ rrw_ext_lg_dep(__FUNCTION__,'3.4.0','wp_get_themes()');return get_themes(); }
}
if(!function_exists('get_broken_themes')){
    function get_broken_themes(){ rrw_ext_lg_dep(__FUNCTION__,'3.4.0','wp_get_themes()');return []; }
}
if(!function_exists('current_theme_info')){
    function current_theme_info(){ rrw_ext_lg_dep(__FUNCTION__,'3.4.0','wp_get_theme()');return wp_get_theme(); }
}
if(!function_exists('wp_update_core')){
    function wp_update_core($current,$feedback=''){
        rrw_ext_lg_dep(__FUNCTION__,'3.7.0','new Core_Upgrader()');
        return class_exists('Core_Upgrader')?(new Core_Upgrader())->upgrade($current):new WP_Error('not_supported',__('Updates are not supported here.'));
    }
}
if(!function_exists('wp_update_plugin')){
    function wp_update_plugin($plugin,$feedback=''){
        rrw_ext_lg_dep(__FUNCTION__,'3.7.0','new Plugin_Upgrader()');
        return class_exists('Plugin_Upgrader')?(new Plugin_Upgrader())->upgrade($plugin):new WP_Error('not_supported',__('Updates are not supported here.'));
    }
}
if(!function_exists('wp_update_theme')){
    function wp_update_theme($theme,$feedback=''){
        rrw_ext_lg_dep(__FUNCTION__,'3.7.0','new Theme_Upgrader()');
        return class_exists('Theme_Upgrader')?(new Theme_Upgrader())->upgrade($theme):new WP_Error('not_supported',__('Updates are not supported here.'));
    }
}

/* ───────── Menüs / Dashboard ───────── */
if(!function_exists('add_object_page')){
    function add_object_page($page_title,$menu_title,$capability,$menu_slug,$callback='',$icon_url=''){
        rrw_ext_lg_dep(__FUNCTION__,'4.5.0','add_menu_page()');
        return add_menu_page($page_title,$menu_title,$capability,$menu_slug,$callback,$icon_url,'3.99');
    }
}
if(!function_exists('add_utility_page')){
    function add_utility_page($page_title,$menu_title,$capability,$menu_slug,$callback='',$icon_url=''){
        rrw_ext_lg_dep(__FUNCTION__,'4.5.0','add_menu_page()');
        return add_menu_page($page_title,$menu_title,$capability,$menu_slug,$callback,$icon_url,'99.99');
    }
}
if(!function_exists('wp_dashboard_quick_press_output')){
    function wp_dashboard_quick_press_output(){ rrw_ext_lg_dep(__FUNCTION__,'3.2.0','wp_dashboard_quick_press()');if(function_exists('wp_dashboard_quick_press'))wp_dashboard_quick_press(); }
}
if(!function_exists('wp_dashboard_incoming_links_output')){
    function wp_dashboard_incoming_links_output(){ rrw_ext_lg_dep(__FUNCTION__,'3.8.0'); }
}
if(!function_exists('wp_dashboard_secondary_output')){
    function wp_dashboard_secondary_output(){ rrw_ext_lg_dep(__FUNCTION__,'3.8.0'); }
}
if(!function_exists('wp_dashboard_incoming_links')){
    function wp_dashboard_incoming_links(){ rrw_ext_lg_dep(__FUNCTION__,'3.8.0'); }
}
if(!function_exists('wp_dashboard_incoming_links_control')){
    function wp_dashboard_incoming_links_control(){ rrw_ext_lg_dep(__FUNCTION__,'3.8.0'); }
}
if(!function_exists('wp_dashboard_plugins')){
    function wp_dashboard_plugins(){ rrw_ext_lg_dep(__FUNCTION__,'3.8.0'); }
}
if(!function_exists('wp_dashboard_primary_control')){
    function wp_dashboard_primary_control(){ rrw_ext_lg_dep(__FUNCTION__,'3.8.0'); }
}
if(!function_exists('wp_dashboard_recent_comments_control')){
    function wp_dashboard_recent_comments_control(){ rrw_ext_lg_dep(__FUNCTION__,'3.8.0'); }
}
if(!function_exists('wp_dashboard_secondary')){
    function wp_dashboard_secondary(){ rrw_ext_lg_dep(__FUNCTION__,'3.8.0'); }
}
if(!function_exists('wp_dashboard_secondary_control')){
    function wp_dashboard_secondary_control(){ rrw_ext_lg_dep(__FUNCTION__,'3.8.0'); }
}
if(!function_exists('wp_dashboard_plugins_output')){
    function wp_dashboard_plugins_output(){ rrw_ext_lg_dep(__FUNCTION__,'3.8.0'); }
}

/* ───────── Benutzerliste (alte Klasse) ───────── */
if(!class_exists('WP_User_Search')){
    class WP_User_Search {
        public $results=[];public $search_term;public $page;public $role;public $raw_page;public $users_per_page=50;public $first_user=0;public $last_user=0;
        public $total_users_for_query=0;public $too_many_total_users=false;public $search_errors;public $paging_text='';public $query_orderby='';public $query_limit='';
        public function __construct($search_term='',$page='',$role=''){
            rrw_ext_lg_dep(__CLASS__,'3.1.0','WP_User_Query');
            $this->search_term=wp_unslash($search_term);$this->raw_page=($page==='')?false:(int)$page;$this->page=(int)($page?:1);$this->role=$role;
            $this->prepare_query();$this->query();$this->prepare_vars_for_template_usage();$this->do_paging();
        }
        public function prepare_query(){ $this->first_user=($this->page-1)*$this->users_per_page; }
        public function query(){
            $a=['number'=>$this->users_per_page,'offset'=>$this->first_user,'count_total'=>true,'fields'=>'ID'];
            if($this->search_term!=='')$a['search']='*'.$this->search_term.'*';
            if($this->role)$a['role']=$this->role;
            $q=new WP_User_Query($a);
            $this->results=array_map('intval',(array)$q->get_results());$this->total_users_for_query=(int)$q->get_total();
            if(!$this->results&&$this->search_term!=='')$this->search_errors=new WP_Error('no_matching_users_found',__('No users found.'));
        }
        public function prepare_vars_for_template_usage(){}
        public function do_paging(){
            if($this->total_users_for_query>$this->users_per_page){
                $this->paging_text=paginate_links(['total'=>ceil($this->total_users_for_query/$this->users_per_page),'current'=>$this->page,'base'=>add_query_arg('userspage','%#%'),'format'=>'']);
            }
        }
        public function get_results(){ return (array)$this->results; }
        public function page_links(){ echo $this->paging_text; }
        public function results_are_paged(){ return (bool)$this->paging_text; }
        public function is_search(){ return $this->search_term!==''; }
    }
}

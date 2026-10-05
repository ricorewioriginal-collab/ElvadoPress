<?php
// Veraltete WordPress-Funktionen: Multisite (ms-deprecated), pluggable-deprecated und alte Listen-Klassen.
// Einzelseiten-Betrieb: es gibt genau eine Website (ID 1); Funktionen, die Sites anlegen/löschen würden, liefern false/WP_Error.
// Meldung über rrw_ext_lg_dep() (legacy-core.php).

if(!function_exists('rrw_ext_lg_site')){
    /** Die eine Website als Array im alten get_blog_list()-Format. */
    function rrw_ext_lg_site(): array {
        $host=(string)(parse_url(home_url(),PHP_URL_HOST)?:($_SERVER['HTTP_HOST']??'localhost'));
        $path=(string)(parse_url(home_url(),PHP_URL_PATH)?:'/');
        return ['blog_id'=>1,'site_id'=>1,'domain'=>$host,'path'=>trailingslashit($path),'registered'=>'0000-00-00 00:00:00','last_updated'=>'0000-00-00 00:00:00','public'=>1,'archived'=>0,'mature'=>0,'spam'=>0,'deleted'=>0,'lang_id'=>0];
    }
}

/* ───────── ms-deprecated (wp-includes) ───────── */
if(!function_exists('get_dashboard_blog')){
    function get_dashboard_blog(){ rrw_ext_lg_dep(__FUNCTION__,'3.1.0','get_site()');return get_blog_details(1); }
}
if(!function_exists('generate_random_password')){
    function generate_random_password($len=8){ rrw_ext_lg_dep(__FUNCTION__,'3.0.0','wp_generate_password()');return wp_generate_password((int)$len); }
}
if(!function_exists('is_site_admin')){
    function is_site_admin($user_login=''){
        rrw_ext_lg_dep(__FUNCTION__,'3.0.0','is_super_admin()');
        if($user_login==='')return is_super_admin();
        $u=get_user_by('login',$user_login);
        return $u?is_super_admin($u->ID):false;
    }
}
if(!function_exists('graceful_fail')){
    function graceful_fail($message){ rrw_ext_lg_dep(__FUNCTION__,'3.0.0','wp_die()');wp_die(apply_filters('graceful_fail',$message)); }
}
if(!function_exists('get_user_details')){
    function get_user_details($username){ rrw_ext_lg_dep(__FUNCTION__,'3.0.0','get_user_by()');return get_user_by('login',$username); }
}
if(!function_exists('clear_global_post_cache')){
    function clear_global_post_cache($post_id){ rrw_ext_lg_dep(__FUNCTION__,'3.0.0','clean_post_cache()');clean_post_cache($post_id); }
}
if(!function_exists('is_main_blog')){
    function is_main_blog(){ rrw_ext_lg_dep(__FUNCTION__,'3.0.0','is_main_site()');return is_main_site(); }
}
if(!function_exists('validate_email')){
    function validate_email($email,$check_domain=true){ rrw_ext_lg_dep(__FUNCTION__,'3.0.0','is_email()');return is_email($email,$check_domain); }
}
if(!function_exists('get_blog_list')){
    function get_blog_list($start=0,$num=10,$deprecated=''){
        rrw_ext_lg_dep(__FUNCTION__,'3.0.0','get_sites()');
        return ((int)$start>0||$num===0||$num==='0')?[]:[rrw_ext_lg_site()];
    }
}
if(!function_exists('get_most_active_blogs')){
    function get_most_active_blogs($num=10,$display=true){
        rrw_ext_lg_dep(__FUNCTION__,'3.0.0','wp_get_sites()');
        $b=rrw_ext_lg_site();$b['postcount']=(int)(wp_count_posts()->publish??0);
        $blogs=array_slice([$b],0,max(0,(int)$num));
        if($display){
            echo '<ul>';
            foreach($blogs as $x)echo '<li><a href="'.esc_url(home_url()).'">'.esc_html($x['path']).'</a> ('.(int)$x['postcount'].' posts)</li>';
            echo '</ul>';
        }
        return $blogs;
    }
}
if(!function_exists('wpmu_admin_redirect_add_updated_param')){
    function wpmu_admin_redirect_add_updated_param($sendback=''){
        rrw_ext_lg_dep(__FUNCTION__,'3.3.0','add_query_arg()');
        if(!str_contains($sendback,'updated=true'))$sendback.=(str_contains($sendback,'?')?'&':'?').'updated=true';
        return $sendback;
    }
}
if(!function_exists('wpmu_admin_do_redirect')){
    function wpmu_admin_do_redirect($url=''){
        rrw_ext_lg_dep(__FUNCTION__,'3.3.0','wp_redirect()');
        $ref=(string)($_POST['ref']??$_GET['ref']??'');
        if($ref===''&&!empty($_SERVER['HTTP_REFERER']))$ref=(string)$_SERVER['HTTP_REFERER'];
        wp_redirect(wpmu_admin_redirect_add_updated_param($ref!==''?$ref:$url));
        exit;
    }
}
if(!function_exists('get_user_id_from_string')){
    function get_user_id_from_string($string){
        rrw_ext_lg_dep(__FUNCTION__,'3.6.0','get_user_by()');
        if(is_email($string))$u=get_user_by('email',$string);
        elseif(is_numeric($string))return (int)$string;
        else $u=get_user_by('login',$string);
        return $u?(int)$u->ID:0;
    }
}
if(!function_exists('get_blogaddress_by_domain')){
    function get_blogaddress_by_domain($domain,$path){
        rrw_ext_lg_dep(__FUNCTION__,'3.0.0','get_home_url()');
        return esc_url((is_ssl()?'https://':'http://').$domain.$path);
    }
}
if(!function_exists('create_empty_blog')){
    function create_empty_blog($domain,$path,$weblog_title,$site_id=1){ rrw_ext_lg_dep(__FUNCTION__,'3.0.0','wp_insert_site()');return new WP_Error('ms_unsupported',__('Multisite is not supported.')); }
}
if(!function_exists('get_admin_users_for_domain')){
    function get_admin_users_for_domain($sitedomain='',$path=''){
        rrw_ext_lg_dep(__FUNCTION__,'3.0.0','get_users()');
        $out=[];
        foreach(get_users(['role'=>'administrator']) as $u)$out[]=['user_id'=>$u->ID,'ID'=>$u->ID,'user_login'=>$u->user_login,'user_email'=>$u->user_email];
        return $out;
    }
}
if(!function_exists('wp_get_sites')){
    function wp_get_sites($args=[]){
        rrw_ext_lg_dep(__FUNCTION__,'4.6.0','get_sites()');
        $r=wp_parse_args($args,['offset'=>0,'limit'=>100]);
        return ((int)$r['offset']>0||(int)$r['limit']<1)?[]:[rrw_ext_lg_site()];
    }
}
if(!function_exists('is_user_option_local')){
    function is_user_option_local($key,$user_id=0,$blog_id=0){
        global $wpdb;
        rrw_ext_lg_dep(__FUNCTION__,'4.0.0');
        $u=wp_get_current_user();
        $k=$wpdb->get_blog_prefix($blog_id?:get_current_blog_id()).$key;
        return isset($u->$k);
    }
}
if(!function_exists('insert_blog')){
    function insert_blog($domain,$path,$site_id){ rrw_ext_lg_dep(__FUNCTION__,'5.1.0','wp_insert_site()');return false; }
}
if(!function_exists('install_blog')){
    function install_blog($blog_id,$blog_title=''){ rrw_ext_lg_dep(__FUNCTION__,'5.1.0','wp_install()'); }
}
if(!function_exists('install_blog_defaults')){
    function install_blog_defaults($blog_id,$user_id){ rrw_ext_lg_dep(__FUNCTION__,'5.1.0','wp_install_defaults()'); }
}
if(!function_exists('update_user_status')){
    function update_user_status($id,$pref,$value,$deprecated=null){
        global $wpdb;
        rrw_ext_lg_dep(__FUNCTION__,'5.3.0','wp_update_user()');
        $wpdb->update($wpdb->users,[sanitize_key($pref)=>$value],['ID'=>(int)$id]);
        clean_user_cache((int)$id);
        if('spam'==$pref)do_action($value==1?'make_spam_user':'make_ham_user',$id);
        return $value;
    }
}
if(!function_exists('global_terms')){
    function global_terms($term_id,$deprecated=''){ rrw_ext_lg_dep(__FUNCTION__,'6.1.0');return $term_id; }
}

/* ───────── ms-deprecated (wp-admin) ───────── */
if(!function_exists('wpmu_menu')){
    function wpmu_menu(){ rrw_ext_lg_dep(__FUNCTION__,'3.0.0'); }
}
if(!function_exists('wpmu_checkAvailableSpace')){
    function wpmu_checkAvailableSpace(){ rrw_ext_lg_dep(__FUNCTION__,'3.0.0','is_upload_space_available()'); }
}
if(!function_exists('mu_options')){
    function mu_options($options){ rrw_ext_lg_dep(__FUNCTION__,'3.0.0');return $options; }
}
if(!function_exists('activate_sitewide_plugin')){
    function activate_sitewide_plugin(){ rrw_ext_lg_dep(__FUNCTION__,'3.0.0','activate_plugin()');return false; }
}
if(!function_exists('deactivate_sitewide_plugin')){
    function deactivate_sitewide_plugin($plugin=false){ rrw_ext_lg_dep(__FUNCTION__,'3.0.0','deactivate_plugins()'); }
}
if(!function_exists('is_wpmu_sitewide_plugin')){
    function is_wpmu_sitewide_plugin($file){ rrw_ext_lg_dep(__FUNCTION__,'3.0.0','is_network_only_plugin()');return false; }
}
if(!function_exists('get_site_allowed_themes')){
    function get_site_allowed_themes(){ rrw_ext_lg_dep(__FUNCTION__,'3.4.0','WP_Theme::get_allowed_on_network()');return []; }
}
if(!function_exists('wpmu_get_blog_allowedthemes')){
    function wpmu_get_blog_allowedthemes($blog_id=0){ rrw_ext_lg_dep(__FUNCTION__,'3.4.0','WP_Theme::get_allowed_on_site()');return []; }
}
if(!function_exists('ms_deprecated_blogs_file')){
    function ms_deprecated_blogs_file(){}
}
if(!function_exists('install_global_terms')){
    function install_global_terms(){ rrw_ext_lg_dep(__FUNCTION__,'6.1.0'); }
}
if(!function_exists('sync_category_tag_slugs')){
    function sync_category_tag_slugs($term,$taxonomy){
        rrw_ext_lg_dep(__FUNCTION__,'6.1.0');
        if(global_terms_enabled()&&in_array($taxonomy,['category','post_tag'],true)){
            if(is_object($term))$term->slug=sanitize_title($term->name);else $term['slug']=sanitize_title($term['name']);
        }
        return $term;
    }
}

/* ───────── pluggable-deprecated ───────── */
if(!function_exists('set_current_user')){
    function set_current_user($id,$name=''){ rrw_ext_lg_dep(__FUNCTION__,'3.0.0','wp_set_current_user()');return wp_set_current_user($id,$name); }
}
if(!function_exists('get_currentuserinfo')){
    function get_currentuserinfo(){ rrw_ext_lg_dep(__FUNCTION__,'4.5.0','wp_get_current_user()');return wp_get_current_user(); }
}
if(!function_exists('get_userdatabylogin')){
    function get_userdatabylogin($user_login){ rrw_ext_lg_dep(__FUNCTION__,'3.3.0','get_user_by()');return get_user_by('login',$user_login); }
}
if(!function_exists('get_user_by_email')){
    function get_user_by_email($email){ rrw_ext_lg_dep(__FUNCTION__,'3.3.0','get_user_by()');return get_user_by('email',$email); }
}
if(!function_exists('wp_setcookie')){
    function wp_setcookie($username,$password='',$already_md5=false,$home='',$siteurl='',$remember=false){
        rrw_ext_lg_dep(__FUNCTION__,'2.5.0','wp_set_auth_cookie()');
        $u=get_user_by('login',$username);
        if($u)wp_set_auth_cookie($u->ID,$remember);
    }
}
if(!function_exists('wp_clearcookie')){
    function wp_clearcookie(){ rrw_ext_lg_dep(__FUNCTION__,'2.5.0','wp_clear_auth_cookie()');wp_clear_auth_cookie(); }
}
if(!function_exists('wp_get_cookie_login')){
    function wp_get_cookie_login(){ rrw_ext_lg_dep(__FUNCTION__,'2.5.0');return false; }
}
if(!function_exists('wp_login')){
    function wp_login($username,$password,$deprecated=''){
        rrw_ext_lg_dep(__FUNCTION__,'2.5.0','wp_signon()');
        return !is_wp_error(wp_authenticate($username,$password));
    }
}

/* ───────── Datenschutz-Tabellen (alte Klassen) ───────── */
if(!class_exists('RRW_Ext_Lg_Table')){
    // Basis: echte WP_List_Table, falls geladen; sonst schlanke Hülle
    if(class_exists('WP_List_Table')){
        class RRW_Ext_Lg_Table extends WP_List_Table {
            protected $rrw_action='';
            public function __construct($args=[]){ parent::__construct(is_array($args)?$args:[]); }
            public function prepare_items(){ $this->items=$this->rrw_requests(); }
            protected function rrw_requests(){ return get_posts(['post_type'=>'user_request','post_name__in'=>[$this->rrw_action],'post_status'=>'any','numberposts'=>-1]); }
        }
    } else {
        class RRW_Ext_Lg_Table {
            public $items=[];protected $rrw_action='';protected $_args=[];
            public function __construct($args=[]){ $this->_args=is_array($args)?$args:[]; }
            public function prepare_items(){ $this->items=$this->rrw_requests(); }
            protected function rrw_requests(){ return get_posts(['post_type'=>'user_request','post_name__in'=>[$this->rrw_action],'post_status'=>'any','numberposts'=>-1]); }
            public function get_columns(){ return []; }
        }
    }
}
if(!class_exists('WP_Privacy_Data_Export_Requests_Table')){
    class WP_Privacy_Data_Export_Requests_Table extends RRW_Ext_Lg_Table {
        protected $rrw_action='export_personal_data';
        public function __construct($args=[]){ rrw_ext_lg_dep(__CLASS__,'5.3.0','WP_Privacy_Data_Export_Requests_List_Table');parent::__construct($args); }
    }
}
if(!class_exists('WP_Privacy_Data_Removal_Requests_Table')){
    class WP_Privacy_Data_Removal_Requests_Table extends RRW_Ext_Lg_Table {
        protected $rrw_action='remove_personal_data';
        public function __construct($args=[]){ rrw_ext_lg_dep(__CLASS__,'5.3.0','WP_Privacy_Data_Removal_Requests_List_Table');parent::__construct($args); }
    }
}

<?php
// Ergänzende WordPress-Funktionen (Bereich Multisite, Teil 3): Verwaltungs-Hilfen (wp-admin/includes/ms.php, network.php) und Listen-Tabellen.
// Einzelseiten-Betrieb: Netzwerk-Einrichtung ist nicht möglich (Hinweis statt Formular), Benutzer/Sites-Listen zeigen die eine Website.

/* ───────── Speicherplatz und Uploads ───────── */
if(!function_exists('upload_is_user_over_quota')){
    function upload_is_user_over_quota($display_message=true) {
        if(get_network_option(1,'upload_space_check_disabled',1))return false;
        $allowed=get_space_allowed();if(!is_numeric($allowed))$allowed=10;
        if(($allowed-get_space_used())<0){
            if($display_message)printf(__('Sorry, you have used your space allocation of %s. Please delete some files to upload more files.'),size_format($allowed*MB_IN_BYTES));
            return true;
        }
        return false;
    }
}
if(!function_exists('check_upload_size')){
    function check_upload_size($file) {
        if(get_network_option(1,'upload_space_check_disabled',1))return $file;
        if(!empty($file['error'])&&$file['error']>0)return $file;
        $left=get_upload_space_available();$size=is_file((string)($file['tmp_name']??''))?filesize($file['tmp_name']):0;
        if($left<$size)$file['error']=sprintf(__('Not enough space to upload. %s KB needed.'),number_format(($size-$left)/KB_IN_BYTES));
        if($size>KB_IN_BYTES*(int)get_network_option(1,'fileupload_maxk',1500))$file['error']=sprintf(__('This file is too big. Files must be less than %s KB in size.'),get_network_option(1,'fileupload_maxk',1500));
        if(upload_is_user_over_quota(false))$file['error']=__('You have used your space quota. Please delete files before uploading.');
        if(!empty($file['error'])&&$file['error']>0&&!isset($_POST['html-upload'])&&!wp_doing_ajax())wp_die($file['error'].' <a href="javascript:history.go(-1)">'.__('Back').'</a>');
        return $file;
    }
}
if(!function_exists('display_space_usage')){
    function display_space_usage() {
        $a=get_space_allowed();$u=get_space_used();$p=$a>0?($u/$a)*100:0;
        echo '<strong>'.sprintf(__('Used: %1$s%% of %2$s'),number_format($p),size_format($a*MB_IN_BYTES)).'</strong>';
    }
}
if(!function_exists('fix_import_form_size')){
    function fix_import_form_size($size) { return upload_is_user_over_quota(false)?0:min($size,get_upload_space_available()); }
}
if(!function_exists('upload_space_setting')){
    function upload_space_setting($id) {
        $q=get_option('blog_upload_space');if(!$q)$q='';
        echo '<tr><th><label for="blog-upload-space-number">'.esc_html__('Site Upload Space Quota').'</label></th><td><input type="number" step="1" min="0" style="width: 100px" name="option[blog_upload_space]" id="blog-upload-space-number" value="'.esc_attr($q).'" /> <span id="blog-upload-space-desc"><span class="screen-reader-text">'.esc_html__('Size in megabytes').'</span> '.esc_html__('MB (Leave blank for network default)').'</span></td></tr>';
    }
}

/* ───────── Benutzer und Sites ───────── */
if(!function_exists('wpmu_delete_blog')){
    // Die einzige Site lässt sich nicht löschen; andere gibt es nicht.
    function wpmu_delete_blog($blog_id,$drop=false) { return (int)$blog_id===1?new WP_Error('site_main',__('Sorry, you cannot delete the main site.')):new WP_Error('site_not_exist',__('Site does not exist.')); }
}
if(!function_exists('wpmu_delete_user')){
    function wpmu_delete_user($id) {
        $user=get_userdata((int)$id);if(!$user)return false;
        do_action('wpmu_delete_user',(int)$id,$user);
        return (bool)wp_delete_user((int)$id);
    }
}
if(!function_exists('refresh_user_details')){ function refresh_user_details($id) { $id=(int)$id;clean_user_cache($id);return $id; } }
if(!function_exists('check_import_new_users')){ function check_import_new_users($permission) { return current_user_can('manage_network_users')?$permission:false; } }
if(!function_exists('can_edit_network')){
    function can_edit_network($network_id) { return (bool)apply_filters('can_edit_network',get_current_network_id()==$network_id,$network_id); }
}
if(!function_exists('wp_ensure_editable_role')){
    function wp_ensure_editable_role($role) {
        $roles=function_exists('get_editable_roles')?get_editable_roles():wp_roles()->roles;
        if(!isset($roles[$role]))wp_die(__('Sorry, you are not allowed to give users that role.'),403);
    }
}
if(!function_exists('_access_denied_splash')){
    // Wer eingeloggt ist, aber auf dieser Site keine Rolle hat, bekommt eine Meldung (bei der einen Site praktisch nie).
    function _access_denied_splash() {
        if(!is_user_logged_in()||(function_exists('is_network_admin')&&is_network_admin()))return;
        if(get_blogs_of_user(get_current_user_id())||current_user_can('read'))return;
        wp_die(sprintf(__('<strong>Error:</strong> You do not have any roles on %s.'),esc_html(get_bloginfo('name'))),403);
    }
}
if(!function_exists('site_admin_notice')){
    function site_admin_notice() {
        if(!current_user_can('upgrade_network'))return false;
        if(get_network_option(1,'wpmu_upgrade_site')!=($GLOBALS['wp_db_version']??0))echo "<div class='update-nag'>".__('Thank you for Updating! Please visit the Upgrade Network page to update all your sites.')."</div>";
    }
}
if(!function_exists('avoid_blog_page_permalink_collision')){
    function avoid_blog_page_permalink_collision($data,$postarr) {
        if(is_subdomain_install()||($data['post_type']??'')!=='page'||empty($data['post_name'])||!is_main_site()||!empty($data['post_parent']))return $data;
        $name=$data['post_name'];$c=0;
        while($c<10&&get_id_from_blogname($name)){ $name.=mt_rand(1,10);$c++; }
        if($name!==$data['post_name'])$data['post_name']=$name;
        return $data;
    }
}
if(!function_exists('choose_primary_blog')){
    function choose_primary_blog() {
        $blogs=get_blogs_of_user(get_current_user_id());
        if(!$blogs&&is_user_logged_in())$blogs=[1=>(object)['userblog_id'=>1,'domain'=>rrw_ms_site_row()['domain'],'path'=>rrw_ms_site_row()['path']]];   // angemeldet ohne Eintrag in wp_users: die eine Site
        echo '<table class="form-table" role="presentation"><tr><th scope="row">'.esc_html__('Primary Site').'</th><td>';
        if(count($blogs)<=1){ $b=reset($blogs);if($b)echo '<span>'.esc_html($b->domain.$b->path).'</span>'; }
        else{ echo '<select name="primary_blog" id="primary_blog">';foreach($blogs as $b)echo '<option value="'.(int)$b->userblog_id.'">'.esc_html($b->domain.$b->path).'</option>';echo '</select>'; }
        echo '</td></tr></table>';
    }
}
if(!function_exists('confirm_delete_users')){
    function confirm_delete_users($users) {
        if(!is_array($users)||!$users)return false;
        $me=wp_get_current_user();
        echo '<h1>'.esc_html__('Users').'</h1><p>'.(count($users)===1?esc_html__('You have chosen to delete the user from all networks and sites.'):esc_html__('You have chosen to delete the following users from all networks and sites.')).'</p>';
        echo '<form action="users.php?action=dodelete" method="post"><input type="hidden" name="dodelete" />';wp_nonce_field('ms-users-delete');
        $admins=function_exists('get_super_admins')?(array)get_super_admins():[];
        foreach($users as $id){
            $u=get_userdata((int)$id);if(!$u)continue;
            if(in_array($u->user_login,$admins,true))wp_die(sprintf(__('Warning! User %s cannot be deleted.'),$u->user_login));
            echo '<input type="hidden" name="user[]" value="'.(int)$id.'" /><fieldset><legend>'.sprintf(esc_html__('What should happen to posts and other content owned by %s?'),'<em>'.esc_html($u->user_login).'</em>').'</legend>';
            echo '<label><input type="radio" name="delete['.(int)$id.']" value="delete" checked="checked" /> '.esc_html__('Delete all content.').'</label><br />';
            echo '<label><input type="radio" name="delete['.(int)$id.']" value="reassign" /> '.esc_html__('Attribute all content to:').'</label> <select name="blog['.(int)$id.']"><option value="'.(int)$me->ID.'">'.esc_html((string)$me->user_login).'</option></select></fieldset>';
        }
        echo '<p><input type="submit" class="button button-primary" value="'.esc_attr__('Confirm Deletion').'" /></p></form>';
        return true;
    }
}

/* ───────── Ausgaben für Einstellungsseiten ───────── */
if(!function_exists('_thickbox_path_admin_subfolder')){
    function _thickbox_path_admin_subfolder() { echo '<script type="text/javascript">var tb_pathToImage = "'.esc_js(includes_url('js/thickbox/loadingAnimation.gif','relative')).'";</script>'; }
}
if(!function_exists('network_settings_add_js')){
    function network_settings_add_js() {
        echo '<script type="text/javascript">jQuery(function($){var l=$("#WPLANG");$("form").on("submit",function(){if(!l.find("option:selected").data("installed")){$("#submit",this).after(\'<span class="spinner language-install-spinner is-active" />\');}});});</script>';
    }
}
if(!function_exists('network_edit_site_nav')){
    function network_edit_site_nav($args=[]) {
        $a=wp_parse_args($args,['blog_id'=>isset($_GET['blog_id'])?(int)$_GET['blog_id']:0]);$id=(int)$a['blog_id']?:1;
        $links=['site-info'=>['label'=>__('Info'),'url'=>'site-info.php','cap'=>'manage_sites'],'site-users'=>['label'=>__('Users'),'url'=>'site-users.php','cap'=>'manage_sites'],
            'site-themes'=>['label'=>__('Themes'),'url'=>'site-themes.php','cap'=>'manage_sites'],'site-settings'=>['label'=>__('Settings'),'url'=>'site-settings.php','cap'=>'manage_sites']];
        $links=apply_filters('network_edit_site_nav_links',$links);
        echo '<nav class="nav-tab-wrapper wp-clearfix" aria-label="'.esc_attr__('Secondary menu').'">';
        foreach($links as $l){
            if(!current_user_can($l['cap'],$id))continue;
            $on=($GLOBALS['pagenow']??'')===$l['url'];
            echo '<a href="'.esc_url(add_query_arg(['id'=>$id],network_admin_url($l['url']))).'" class="nav-tab'.($on?' nav-tab-active':'').'"'.($on?' aria-current="page"':'').'>'.esc_html($l['label']).'</a>';
        }
        echo '</nav>';
    }
}
if(!function_exists('get_site_screen_help_tab_args')){
    function get_site_screen_help_tab_args() {
        return ['id'=>'overview','title'=>__('Overview'),'content'=>'<p>'.__('The menu is for editing information specific to individual sites, particularly if the admin area of a site is unavailable.').'</p>'
            .'<p>'.__('<strong>Info</strong> &mdash; The site URL is rarely edited as this can cause the site to not work properly. The Registered date and Last Updated date are displayed. Network admins can mark a site as archived, spam, deleted and mature, to remove from public listings or disable.').'</p>'
            .'<p>'.__('<strong>Users</strong> &mdash; This displays the users associated with this site. You can also change their role, reset their password, or remove them from the site.').'</p>'
            .'<p>'.__('<strong>Themes</strong> &mdash; This area shows themes that are not already enabled across the network. Enabling a theme in this menu makes it accessible to this site.').'</p>'
            .'<p>'.__('<strong>Settings</strong> &mdash; This page shows a list of all settings associated with this site. Some are created by WordPress and others are created by plugins you activate.').'</p>'];
    }
}
if(!function_exists('get_site_screen_help_sidebar_content')){
    function get_site_screen_help_sidebar_content() {
        return '<p><strong>'.__('For more information:').'</strong></p><p>'.__('<a href="https://wordpress.org/documentation/article/network-admin-sites-screen/">Documentation on Site Management</a>').'</p><p>'.__('<a href="https://wordpress.org/support/forum/multisite/">Support forums</a>').'</p>';
    }
}
if(!function_exists('format_code_lang')){
    function format_code_lang($code='') {
        static $map=null;
        if($map===null){
            $map=[];
            foreach(explode('|','aa:Afar|ab:Abkhazian|af:Afrikaans|ak:Akan|sq:Albanian|am:Amharic|ar:Arabic|an:Aragonese|hy:Armenian|as:Assamese|av:Avaric|ae:Avestan|ay:Aymara|az:Azerbaijani|ba:Bashkir|bm:Bambara|eu:Basque|be:Belarusian|bn:Bengali|bh:Bihari|bi:Bislama|bs:Bosnian|br:Breton|bg:Bulgarian|my:Burmese|ca:Catalan|ch:Chamorro|ce:Chechen|zh:Chinese|cu:Church Slavic|cv:Chuvash|kw:Cornish|co:Corsican|cr:Cree|cs:Czech|da:Danish|dv:Divehi|nl:Dutch|dz:Dzongkha|en:English|eo:Esperanto|et:Estonian|ee:Ewe|fo:Faroese|fj:Fijian|fi:Finnish|fr:French|fy:Western Frisian|ff:Fulah|ka:Georgian|de:German|gd:Gaelic|ga:Irish|gl:Galician|gv:Manx|el:Greek|gn:Guarani|gu:Gujarati|ht:Haitian|ha:Hausa|he:Hebrew|hz:Herero|hi:Hindi|ho:Hiri Motu|hr:Croatian|hu:Hungarian|ig:Igbo|is:Icelandic|io:Ido|ii:Sichuan Yi|iu:Inuktitut|ie:Interlingue|ia:Interlingua|id:Indonesian|ik:Inupiaq|it:Italian|jv:Javanese|ja:Japanese|kl:Kalaallisut|kn:Kannada|ks:Kashmiri|kr:Kanuri|kk:Kazakh|km:Central Khmer|ki:Kikuyu|rw:Kinyarwanda|ky:Kirghiz|kv:Komi|kg:Kongo|ko:Korean|kj:Kuanyama|ku:Kurdish|lo:Lao|la:Latin|lv:Latvian|li:Limburgan|ln:Lingala|lt:Lithuanian|lb:Luxembourgish|lu:Luba-Katanga|lg:Ganda|mk:Macedonian|mh:Marshallese|ml:Malayalam|mi:Maori|mr:Marathi|ms:Malay|mg:Malagasy|mt:Maltese|mn:Mongolian|na:Nauru|nv:Navajo|nr:South Ndebele|nd:North Ndebele|ng:Ndonga|ne:Nepali|nn:Norwegian Nynorsk|nb:Norwegian Bokmål|no:Norwegian|ny:Chichewa|oc:Occitan|oj:Ojibwa|or:Oriya|om:Oromo|os:Ossetian|pa:Panjabi|fa:Persian|pi:Pali|pl:Polish|pt:Portuguese|ps:Pushto|qu:Quechua|rm:Romansh|ro:Romanian|rn:Rundi|ru:Russian|sg:Sango|sa:Sanskrit|si:Sinhala|sk:Slovak|sl:Slovenian|se:Northern Sami|sm:Samoan|sn:Shona|sd:Sindhi|so:Somali|st:Southern Sotho|es:Spanish|sc:Sardinian|sr:Serbian|ss:Swati|su:Sundanese|sw:Swahili|sv:Swedish|ty:Tahitian|ta:Tamil|tt:Tatar|te:Telugu|tg:Tajik|tl:Tagalog|th:Thai|bo:Tibetan|ti:Tigrinya|to:Tonga|tn:Tswana|ts:Tsonga|tk:Turkmen|tr:Turkish|tw:Twi|ug:Uighur|uk:Ukrainian|ur:Urdu|uz:Uzbek|ve:Venda|vi:Vietnamese|vo:Volapük|cy:Welsh|wa:Walloon|wo:Wolof|xh:Xhosa|yi:Yiddish|yo:Yoruba|za:Zhuang|zu:Zulu') as $e){ [$k,$v]=explode(':',$e,2);$map[$k]=$v; }
        }
        $code=strtolower(substr((string)$code,0,2));
        return apply_filters('format_code_lang',$map[$code]??$code,$code);
    }
}
if(!function_exists('mu_dropdown_languages')){
    function mu_dropdown_languages($lang_files=[],$current='') {
        $flag=false;$out=[];
        foreach((array)$lang_files as $val){
            $c=basename((string)$val,'.mo');
            if($c==='en_US'){ $flag=true;$n=__('American English');$out[$n]='<option value="'.esc_attr($c).'"'.selected($current,$c,false).'> '.$n.'</option>'; }
            elseif($c==='en_GB'){ $flag=true;$n=__('British English');$out[$n]='<option value="'.esc_attr($c).'"'.selected($current,$c,false).'> '.$n.'</option>'; }
            else{ $n=format_code_lang(substr($c,0,2));$out[$n]='<option value="'.esc_attr($c).'"'.selected($current,$c,false).'> '.esc_html($n).'</option>'; }
        }
        if(!$flag)$out[]='<option>'.__('American English').'</option>';
        uksort($out,'strnatcasecmp');
        $out=apply_filters('mu_dropdown_languages',$out,$lang_files,$current);
        echo implode("\n\t",$out);
    }
}

/* ───────── Netzwerk einrichten (network.php) ───────── */
if(!function_exists('network_domain_check')){
    // Ohne eingerichtetes Netzwerk gibt es keine gespeicherte Netzwerk-Domain: false.
    function network_domain_check() { return false; }
}
if(!function_exists('allow_subdomain_install')){
    function allow_subdomain_install() {
        $home=(string)get_option('home');$domain=preg_replace('|https?://([^/]+)|','$1',$home);
        return !(parse_url($home,PHP_URL_PATH)||$domain==='localhost'||preg_match('|^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$|',$domain));
    }
}
if(!function_exists('allow_subdirectory_install')){
    function allow_subdirectory_install() {
        global $wpdb;
        if(apply_filters('allow_subdirectory_install',false)||(defined('ALLOW_SUBDIRECTORY_INSTALL')&&ALLOW_SUBDIRECTORY_INSTALL))return true;
        if(!rrw_wp_db_ready())return true;
        $post=$wpdb->get_row($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_date < %s AND post_status = 'publish' LIMIT 1",gmdate('Y-m-d H:i:s',strtotime('-1 month'))));
        return empty($post);
    }
}
if(!function_exists('get_clean_basedomain')){
    function get_clean_basedomain() {
        $d=network_domain_check();if($d)return $d;
        $d=preg_replace('|https?://|','',(string)get_option('siteurl'));$s=strpos($d,'/');
        return $s?substr($d,0,$s):$d;
    }
}
if(!function_exists('network_step1')){
    function network_step1($errors=false) {
        echo '<div class="wrap"><h1>'.esc_html__('Create a Network of Sites').'</h1>';
        if(defined('DO_NOT_UPGRADE_GLOBAL_TABLES'))echo '<div class="error"><p><strong>'.__('Error:').'</strong> '.__('The constant DO_NOT_UPGRADE_GLOBAL_TABLES cannot be defined when creating a network.').'</p></div></div>';
        elseif(get_option('active_plugins'))echo '<div class="error"><p>'.__('Please deactivate your plugins before enabling the Network feature.').'</p></div><p>'.__('Once the network is created, you may reactivate your plugins.').'</p></div>';
        else echo '<div class="notice notice-warning"><p>'.esc_html__('This installation runs as a single site; creating a network is not supported.').'</p></div></div>';
    }
}
if(!function_exists('network_step2')){
    function network_step2($errors=false) {
        echo '<div class="wrap"><h1>'.esc_html__('Enabling the Network').'</h1><div class="notice notice-warning"><p>'.esc_html__('This installation runs as a single site; creating a network is not supported.').'</p></div></div>';
    }
}

/* ───────── Listen-Tabellen ───────── */
if(!class_exists('WP_List_Table',false)){ class WP_List_Table { public $items=[];public function __construct($args=[]) {} } }   // Sicherheitsnetz; der Kern definiert normalerweise eine schlanke oder die echte Klasse
if(!class_exists('WP_MS_Sites_List_Table')){
class WP_MS_Sites_List_Table extends WP_List_Table {
    public function __construct($args=[]) { parent::__construct(['plural'=>'sites','singular'=>'site','screen'=>$args['screen']??'sites-network']); }
    public function ajax_user_can() { return current_user_can('manage_sites'); }
    public function prepare_items() {
        $s=trim((string)($_REQUEST['s']??''));$row=rrw_ms_site_row();$row['users']=function_exists('get_user_count')&&get_user_count()>=0?get_user_count():count(get_users(['fields'=>'ids']));
        $this->items=($s===''||stripos($row['domain'].$row['path'],$s)!==false)?[$row]:[];
        $this->set_pagination_args(['total_items'=>count($this->items),'per_page'=>20]);
    }
    public function get_columns() { return apply_filters('wpmu_blogs_columns',['cb'=>'<input type="checkbox" />','blogname'=>__('URL'),'lastupdated'=>__('Last Updated'),'registered'=>__('Registered'),'users'=>__('Users')]); }
    public function get_sortable_columns() { return ['blogname'=>'domain','lastupdated'=>'last_updated','registered'=>'id']; }
    protected function get_default_primary_column_name() { return 'blogname'; }
    public function column_cb($item) { echo '<input type="checkbox" name="allblogs[]" value="'.(int)$item['blog_id'].'" />'; }
    public function column_blogname($item) { echo '<strong><a href="'.esc_url(home_url('/')).'">'.esc_html($item['domain'].$item['path']).'</a></strong>'; }
    public function column_default($item,$column_name) { $v=$item[$column_name]??'';return esc_html($column_name==='lastupdated'?($item['last_updated']??''):(string)$v); }
}
}
if(!class_exists('WP_MS_Users_List_Table')){
class WP_MS_Users_List_Table extends WP_List_Table {
    public function __construct($args=[]) { parent::__construct(['plural'=>'users','singular'=>'user','screen'=>$args['screen']??'users-network']); }
    public function ajax_user_can() { return current_user_can('manage_network_users'); }
    public function prepare_items() {
        $per=20;$paged=$this->get_pagenum();$s=trim((string)($_REQUEST['s']??''));
        $all=get_users(['search'=>$s,'orderby'=>(string)($_REQUEST['orderby']??'login'),'order'=>strtoupper((string)($_REQUEST['order']??'ASC'))==='DESC'?'DESC':'ASC']);
        $this->items=array_slice($all,($paged-1)*$per,$per);$this->set_pagination_args(['total_items'=>count($all),'per_page'=>$per]);
    }
    public function get_columns() { return apply_filters('wpmu_users_columns',['cb'=>'<input type="checkbox" />','username'=>__('Username'),'name'=>__('Name'),'email'=>__('Email'),'registered'=>__('Registered'),'blogs'=>__('Sites')]); }
    public function get_sortable_columns() { return ['username'=>'login','name'=>'name','email'=>'email','registered'=>'id']; }
    protected function get_default_primary_column_name() { return 'username'; }
    public function column_cb($user) { echo '<input type="checkbox" name="allusers[]" value="'.(int)$user->ID.'" />'; }
    public function column_username($user) { echo '<strong>'.esc_html($user->user_login).'</strong>'; }
    public function column_name($user) { echo esc_html(trim((string)get_user_meta($user->ID,'first_name',true).' '.(string)get_user_meta($user->ID,'last_name',true))); }
    public function column_email($user) { echo '<a href="'.esc_url('mailto:'.$user->user_email).'">'.esc_html($user->user_email).'</a>'; }
    public function column_registered($user) { echo esc_html((string)$user->user_registered); }
    public function column_blogs($user) { $l=[];foreach(get_blogs_of_user($user->ID) as $b)$l[]='<a href="'.esc_url(home_url('/')).'">'.esc_html($b->domain.$b->path).'</a>';echo implode(', ',$l); }
    public function column_default($user,$column_name) { return ''; }
}
}
if(!class_exists('WP_MS_Themes_List_Table')){
class WP_MS_Themes_List_Table extends WP_List_Table {
    public function __construct($args=[]) { parent::__construct(['plural'=>'themes','singular'=>'theme','screen'=>$args['screen']??'themes-network']); }
    public function ajax_user_can() { return current_user_can('manage_network_themes'); }
    public function prepare_items() {
        $s=trim((string)($_REQUEST['s']??''));$t=function_exists('wp_get_themes')?wp_get_themes():[];
        if($s!=='')$t=array_filter($t,fn($x)=>stripos((string)$x->get('Name').' '.(string)$x->get('Description'),$s)!==false);
        uasort($t,fn($a,$b)=>strcasecmp((string)$a->get('Name'),(string)$b->get('Name')));
        $this->items=$t;$this->set_pagination_args(['total_items'=>count($t),'per_page'=>max(1,count($t))]);
    }
    public function get_columns() { return ['cb'=>'<input type="checkbox" />','name'=>__('Theme'),'description'=>__('Description')]; }
    public function get_sortable_columns() { return ['name'=>'name']; }
    protected function get_default_primary_column_name() { return 'name'; }
    public function column_cb($theme) { echo '<input type="checkbox" name="checked[]" value="'.esc_attr($theme->get_stylesheet()).'" />'; }
    public function column_name($theme) { echo '<strong>'.esc_html((string)$theme->get('Name')).'</strong>'; }
    public function column_description($theme) { echo '<p>'.esc_html((string)$theme->get('Description')).'</p><div class="theme-version-author-uri">'.sprintf(__('Version %s'),esc_html((string)$theme->get('Version'))).' | '.sprintf(__('By %s'),esc_html(wp_strip_all_tags((string)$theme->get('Author')))).'</div>'; }
    public function column_default($theme,$column_name) { return ''; }
}
}
if(!class_exists('WP_Terms_List_Table')){
class WP_Terms_List_Table extends WP_List_Table {
    public $callback_args=[];
    public function __construct($args=[]) {
        $tax=(string)($GLOBALS['taxonomy']??($_REQUEST['taxonomy']??'post_tag'));$GLOBALS['taxonomy']=$tax;
        parent::__construct(['plural'=>'tags','singular'=>'tag','screen'=>$args['screen']??'edit-tags']);
    }
    protected function rrw_tax(): string { return (string)($GLOBALS['taxonomy']??'post_tag'); }
    public function ajax_user_can() { $t=get_taxonomy($this->rrw_tax());return $t&&current_user_can($t->cap->manage_terms??'manage_categories'); }
    public function prepare_items() {
        $per=20;$paged=$this->get_pagenum();$s=trim((string)($_REQUEST['s']??''));
        $all=get_terms(['taxonomy'=>$this->rrw_tax(),'hide_empty'=>false,'search'=>$s]);
        $all=is_array($all)?$all:[];$this->items=array_slice($all,($paged-1)*$per,$per);
        $this->set_pagination_args(['total_items'=>count($all),'per_page'=>$per]);
    }
    public function get_columns() { return apply_filters('manage_'.$this->rrw_tax().'_columns',['cb'=>'<input type="checkbox" />','name'=>_x('Name','term name'),'description'=>__('Description'),'slug'=>__('Slug'),'posts'=>_x('Count','Number/count of items')]); }
    public function get_sortable_columns() { return ['name'=>'name','description'=>'description','slug'=>'slug','posts'=>'count']; }
    protected function get_default_primary_column_name() { return 'name'; }
    public function column_cb($term) { echo '<input type="checkbox" name="delete_tags[]" value="'.(int)$term->term_id.'" />'; }
    public function column_name($term) { echo '<strong>'.esc_html((string)$term->name).'</strong>'; }
    public function column_description($term) { echo $term->description?esc_html((string)$term->description):'<span aria-hidden="true">&#8212;</span>'; }
    public function column_slug($term) { echo esc_html((string)$term->slug); }
    public function column_posts($term) { echo (int)$term->count; }
    public function column_default($term,$column_name) { return (string)apply_filters('manage_'.$this->rrw_tax().'_custom_column','',$column_name,$term->term_id); }
}
}

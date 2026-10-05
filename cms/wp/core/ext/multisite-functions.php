<?php
// Ergänzende WordPress-Funktionen (Bereich Multisite, Teil 2): Anmeldung neuer Benutzer/Sites, Benachrichtigungen, Speicherplatz, Netzwerk-Zähler (ms-functions).
// Einzelseiten-Betrieb: Anmeldungen („signups“) liegen in der Option rrw_ms_signups; Benutzer-Anmeldungen lassen sich aktivieren,
// Site-Anmeldungen nicht (es gibt keine weiteren Sites: wpmu_create_blog liefert einen WP_Error).

/* ───────── Hilfen ───────── */
if(!function_exists('rrw_ms_signups')){ function rrw_ms_signups(): array { $s=get_option('rrw_ms_signups');return is_array($s)?$s:[]; } }
if(!function_exists('rrw_ms_signups_save')){ function rrw_ms_signups_save(array $s): void { update_option('rrw_ms_signups',$s,false); } }
if(!function_exists('rrw_ms_dirsize')){
    /** Größe eines Verzeichnisses in Byte (rekursiv; fehlendes Verzeichnis = 0). */
    function rrw_ms_dirsize(string $dir): int {
        if(function_exists('get_dirsize'))return (int)get_dirsize($dir);
        if(!is_dir($dir))return 0;$n=0;
        try{ foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS)) as $f)if($f->isFile())$n+=$f->getSize(); }catch(Throwable $e){}
        return $n;
    }
}
if(!function_exists('rrw_ms_set_role')){
    /** Rolle eines Datenbank-Benutzers setzen (CMS-Redakteure behalten ihre Rolle). */
    function rrw_ms_set_role(int $user_id,string $role): bool {
        global $wpdb;if($user_id<RRW_WP_ID_DB_MIN||!get_role($role))return false;
        update_user_meta($user_id,$wpdb->prefix.'rrw_role',$role);rrw_wp_users_all(true);return true;
    }
}
if(!function_exists('rrw_ms_site_name')){ function rrw_ms_site_name(): string { $n=get_option('site_name');return (string)($n?:get_option('blogname','WordPress')); } }

/* ───────── Statistik und Zuordnung ───────── */
if(!function_exists('get_sitestats')){
    function get_sitestats() { $u=get_user_count();return ['blogs'=>get_blog_count(),'users'=>$u<0?(int)(count_users()['total_users']??0):$u]; }
}
if(!function_exists('get_active_blog_for_user')){
    function get_active_blog_for_user($user_id) {
        if(!get_userdata((int)$user_id))return null;
        $s=WP_Site::get_instance(1);return $s&&$s->deleted!=='1'&&$s->spam!=='1'?$s:null;
    }
}
if(!function_exists('get_blog_post')){ function get_blog_post($blog_id,$post_id) { return (int)$blog_id===1?get_post($post_id):null; } }
if(!function_exists('get_blog_permalink')){ function get_blog_permalink($blog_id,$post_id) { return (int)$blog_id===1?get_permalink($post_id):false; } }
if(!function_exists('get_blog_id_from_url')){
    function get_blog_id_from_url($domain,$path='/') {
        $domain=strtolower((string)$domain);$path=trailingslashit(strtolower((string)$path));
        $pre=apply_filters('pre_get_blog_id_from_url',null,$domain,$path);if($pre!==null)return (int)$pre;
        $s=rrw_ms_site_row();return $domain===$s['domain']&&$path===strtolower($s['path'])?1:0;
    }
}
if(!function_exists('domain_exists')){
    function domain_exists($domain,$path,$network_id=1) {
        $s=rrw_ms_site_row();$r=(int)$network_id===1&&strtolower((string)$domain)===$s['domain']&&trailingslashit((string)$path)===$s['path']?1:null;
        return apply_filters('domain_exists',$r,$domain,$path,$network_id);
    }
}
if(!function_exists('get_current_site')){
    function get_current_site() { $n=$GLOBALS['current_site']??null;return $n instanceof WP_Network?$n:WP_Network::get_instance(1); }
}
if(!function_exists('remove_user_from_blog')){
    // Die einzige Site behält ihre Benutzer: nur Aktion und Prüfung, keine Löschung.
    function remove_user_from_blog($user_id,$blog_id='',$reassign=0) {
        $user_id=(int)$user_id;do_action('remove_user_from_blog',$user_id,$blog_id,$reassign);
        return get_userdata($user_id)?true:new WP_Error('user_does_not_exist',__('That user does not exist.'));
    }
}
if(!function_exists('get_most_recent_post_of_user')){
    function get_most_recent_post_of_user($user_id) {
        global $wpdb;if(!get_blogs_of_user((int)$user_id)||!rrw_wp_db_ready())return [];
        $r=$wpdb->get_row($wpdb->prepare("SELECT ID, post_date_gmt FROM {$wpdb->posts} WHERE post_author = %d AND post_type = 'post' AND post_status = 'publish' ORDER BY post_date_gmt DESC LIMIT 1",(int)$user_id),ARRAY_A);
        return $r?['blog_id'=>1,'post_id'=>(int)$r['ID'],'post_date_gmt'=>$r['post_date_gmt'],'post_gmt_ts'=>(int)strtotime($r['post_date_gmt'].' UTC')]:[];
    }
}
if(!function_exists('update_posts_count')){
    function update_posts_count($deprecated='') { update_option('post_count',(int)(wp_count_posts('post')->publish??0)); }
}
if(!function_exists('wpmu_log_new_registrations')){
    function wpmu_log_new_registrations($blog_id,$user_id) {
        $u=get_userdata((int)$user_id);if(!$u)return;$l=(array)get_option('rrw_ms_registration_log',[]);
        $l[]=['blog_id'=>(int)$blog_id,'email'=>$u->user_email,'IP'=>(string)($_SERVER['REMOTE_ADDR']??''),'date_registered'=>gmdate('Y-m-d H:i:s')];
        update_option('rrw_ms_registration_log',array_slice($l,-200),false);
    }
}

/* ───────── Prüfungen ───────── */
if(!function_exists('is_email_address_unsafe')){
    function is_email_address_unsafe($user_email) {
        $banned=get_network_option(1,'banned_email_domains');if($banned&&!is_array($banned))$banned=explode("\n",(string)$banned);
        $unsafe=false;
        if(is_array($banned)&&$banned&&str_contains((string)$user_email,'@')){
            $domain=strtolower(substr((string)$user_email,1+strpos((string)$user_email,'@')));
            foreach($banned as $b){ $b=strtolower(trim((string)$b));if($b==='')continue;if($domain===$b||str_ends_with($domain,'.'.$b)){$unsafe=true;break;} }
        }
        return apply_filters('is_email_address_unsafe',$unsafe,$user_email);
    }
}
if(!function_exists('is_user_spammy')){
    function is_user_spammy($user=null) {
        if(!($user instanceof WP_User))$user=$user===null?wp_get_current_user():get_user_by('login',(string)$user);
        return $user&&isset($user->spam)&&1==$user->spam;
    }
}
if(!function_exists('get_subdirectory_reserved_names')){
    function get_subdirectory_reserved_names() {
        $n=['page','comments','blog','files','feed','wp-admin','wp-content','wp-includes','wp-json','embed'];
        return apply_filters('subdirectory_reserved_names',$n);
    }
}
if(!function_exists('wpmu_validate_user_signup')){
    function wpmu_validate_user_signup($user_name,$user_email) {
        $errors=new WP_Error();$orig_username=$user_name;$user_name=preg_replace('/\s+/','',sanitize_user((string)$user_name,true));
        if($user_name!==$orig_username||preg_match('/[^a-z0-9]/',$user_name)){ $errors->add('user_name',__('Usernames can only contain lowercase letters (a-z) and numbers.'));$user_name=$orig_username; }
        $user_email=sanitize_email((string)$user_email);
        if($user_name==='')$errors->add('user_name',__('Please enter a username.'));
        $illegal=get_network_option(1,'illegal_names');if(!is_array($illegal))$illegal=['www','web','root','admin','main','invite','administrator'];
        if(in_array($user_name,$illegal,true))$errors->add('user_name',__('Sorry, that username is not allowed.'));
        if(!is_email($user_email))$errors->add('user_email',__('Please enter a valid email address.'));
        elseif(is_email_address_unsafe($user_email))$errors->add('user_email',__('You cannot use that email address to signup. We are having problems with them blocking some of our email. Please use another email provider.'));
        if(strlen($user_name)<4)$errors->add('user_name',__('Username must be at least 4 characters.'));
        if(str_contains($user_name,'_'))$errors->add('user_name',__('Sorry, usernames may not contain the character &#8220;_&#8221;!'));
        if(preg_match('/^[0-9]*$/',$user_name))$errors->add('user_name',__('Sorry, usernames must have letters too!'));
        $limited=get_network_option(1,'limited_email_domains');
        if(is_array($limited)&&$limited){ $d=strtolower(substr((string)strrchr($user_email,'@'),1));if(!in_array($d,array_map('strtolower',$limited),true))$errors->add('user_email',__('Sorry, that email address is not allowed!')); }
        if(username_exists($user_name))$errors->add('user_name',__('Sorry, that username already exists!'));
        if(email_exists($user_email))$errors->add('user_email',__('Sorry, that email address is already used!'));
        foreach(rrw_ms_signups() as $k=>$s){   // Offene Anmeldungen blockieren Namen/Adresse 2 Tage lang
            if(!empty($s['active']))continue;$expired=strtotime($s['registered'].' UTC')+2*DAY_IN_SECONDS<time();
            if($s['user_login']===$user_name&&!$expired)$errors->add('user_name',__('That username is currently reserved but may be available in a couple of days.'));
            if($s['user_email']===$user_email&&!$expired)$errors->add('user_email',__('That email address has already been used. Please check your inbox for an activation email. It will become available in a couple of days if you do nothing.'));
        }
        $result=['user_name'=>$user_name,'orig_username'=>$orig_username,'user_email'=>$user_email,'errors'=>$errors];
        return apply_filters('wpmu_validate_user_signup',$result);
    }
}
if(!function_exists('wpmu_validate_blog_signup')){
    function wpmu_validate_blog_signup($blogname,$blog_title,$user='') {
        $net=rrw_ms_network_row();$base=$net['path'];$blog_title=strip_tags((string)$blog_title);$errors=new WP_Error();
        $illegal=get_network_option(1,'illegal_names');if(!is_array($illegal))$illegal=['www','web','root','admin','main','invite','administrator'];
        if(!is_subdomain_install())$illegal=array_merge($illegal,get_subdirectory_reserved_names());
        if($blogname==='')$errors->add('blogname',__('Please enter a site name.'));
        if(preg_match('/[^a-z0-9]+/',(string)$blogname))$errors->add('blogname',__('Site names can only contain lowercase letters (a-z) and numbers.'));
        if(in_array($blogname,$illegal,true))$errors->add('blogname',__('That name is not allowed.'));
        $min=(int)apply_filters('minimum_site_name_length',4);
        if(strlen((string)$blogname)<$min)$errors->add('blogname',sprintf(_n('Site name must be at least %s character.','Site name must be at least %s characters.',$min),number_format_i18n($min)));
        if(preg_match('/^[0-9]*$/',(string)$blogname))$errors->add('blogname',__('Sorry, site names must have letters too!'));
        $blogname=apply_filters('newblogname',$blogname);$blog_title=wp_unslash($blog_title);
        if($blog_title==='')$errors->add('blog_title',__('Please enter a site title.'));
        if(is_subdomain_install()){ $domain=$blogname.'.'.preg_replace('|^www\.|','',$net['domain']);$path=$base; }
        else{ $domain=$net['domain'];$path=$base.$blogname.'/'; }
        if(domain_exists($domain,$path,1))$errors->add('blogname',__('Sorry, that site already exists!'));
        if(username_exists($blogname)&&(!is_object($user)||$user->user_login!==$blogname))$errors->add('blogname',__('Sorry, that site is reserved!'));
        foreach(rrw_ms_signups() as $s){
            if(empty($s['domain'])||$s['domain']!==$domain||$s['path']!==$path||!empty($s['active']))continue;
            if(strtotime($s['registered'].' UTC')+2*DAY_IN_SECONDS>=time())$errors->add('blogname',__('That site is currently reserved but may be available in a couple days.'));
        }
        $result=['domain'=>$domain,'path'=>$path,'blogname'=>$blogname,'blog_title'=>$blog_title,'user'=>$user,'errors'=>$errors];
        return apply_filters('wpmu_validate_blog_signup',$result);
    }
}

/* ───────── Anmeldungen (signups) ───────── */
if(!function_exists('wpmu_signup_user')){
    function wpmu_signup_user($user,$user_email,$meta=[]) {
        $user=preg_replace('/\s+/','',sanitize_user((string)$user,true));$user_email=sanitize_email((string)$user_email);
        $key=substr(md5(time().wp_rand().$user_email),0,16);$meta=apply_filters('signup_user_meta',$meta,$user,$user_email,$key);
        $s=rrw_ms_signups();$s[$key]=['domain'=>'','path'=>'','title'=>'','user_login'=>$user,'user_email'=>$user_email,'registered'=>gmdate('Y-m-d H:i:s'),'activated'=>'0000-00-00 00:00:00','active'=>0,'activation_key'=>$key,'meta'=>(array)$meta];
        rrw_ms_signups_save($s);do_action('after_signup_user',$user,$user_email,$key,$meta);
    }
}
if(!function_exists('wpmu_signup_blog')){
    function wpmu_signup_blog($domain,$path,$title,$user,$user_email,$meta=[]) {
        $key=substr(md5(time().wp_rand().$domain),0,16);$meta=apply_filters('signup_site_meta',$meta,$domain,$path,$title,$user,$user_email,$key);
        $s=rrw_ms_signups();$s[$key]=['domain'=>(string)$domain,'path'=>(string)$path,'title'=>(string)$title,'user_login'=>(string)$user,'user_email'=>sanitize_email((string)$user_email),'registered'=>gmdate('Y-m-d H:i:s'),'activated'=>'0000-00-00 00:00:00','active'=>0,'activation_key'=>$key,'meta'=>(array)$meta];
        rrw_ms_signups_save($s);do_action('after_signup_site',$domain,$path,$title,$user,$user_email,$key,$meta);
    }
}
if(!function_exists('wpmu_signup_user_notification')){
    function wpmu_signup_user_notification($user_login,$user_email,$key,$meta=[]) {
        if(!apply_filters('wpmu_signup_user_notification',$user_login,$user_email,$key,$meta))return false;
        $name=rrw_ms_site_name();$admin=get_network_option(1,'admin_email');
        $h="From: \"{$name}\" <{$admin}>\nContent-Type: text/plain; charset=\"".get_option('blog_charset','UTF-8')."\"\n";
        $msg=sprintf(apply_filters('wpmu_signup_user_notification_email',__("To activate your user, please click the following link:\n\n%s\n\nAfter you activate, you will receive *another email* with your login."),$user_login,$user_email,$key,$meta),site_url("wp-activate.php?key=$key"));
        $subject=sprintf(apply_filters('wpmu_signup_user_notification_subject',__('[%1$s] Activate %2$s'),$user_login,$user_email,$key,$meta),$name,$user_login);
        wp_mail($user_email,wp_specialchars_decode($subject),$msg,$h);return true;
    }
}
if(!function_exists('wpmu_signup_blog_notification')){
    function wpmu_signup_blog_notification($domain,$path,$title,$user_login,$user_email,$key,$meta=[]) {
        if(!apply_filters('wpmu_signup_blog_notification',$domain,$path,$title,$user_login,$user_email,$key,$meta))return false;
        $name=rrw_ms_site_name();$admin=get_network_option(1,'admin_email');
        $h="From: \"{$name}\" <{$admin}>\nContent-Type: text/plain; charset=\"".get_option('blog_charset','UTF-8')."\"\n";
        $msg=sprintf(apply_filters('wpmu_signup_blog_notification_email',__("To activate your site, please click the following link:\n\n%1\$s\n\nAfter you activate, you will receive *another email* with your login.\n\nAfter you activate, you can visit your site here:\n\n%2\$s"),$domain,$path,$title,$user_login,$user_email,$key,$meta),site_url("wp-activate.php?key=$key"),esc_url("http://{$domain}{$path}"));
        $subject=sprintf(apply_filters('wpmu_signup_blog_notification_subject',__('[%1$s] Activate %2$s'),$domain,$path,$title,$user_login,$user_email,$key,$meta),$name,esc_url('http://'.$domain.$path));
        wp_mail($user_email,wp_specialchars_decode($subject),$msg,$h);return true;
    }
}
if(!function_exists('wpmu_create_user')){
    function wpmu_create_user($user_name,$password,$email) {
        $id=wp_create_user((string)$user_name,(string)$password,(string)$email);if(is_wp_error($id)||!$id)return false;
        do_action('wpmu_new_user',$id);return $id;
    }
}
if(!function_exists('wpmu_create_blog')){
    function wpmu_create_blog($domain,$path,$title,$user_id,$options=[],$network_id=1) {
        if(domain_exists($domain,$path,$network_id))return new WP_Error('blog_taken',__('Sorry, that site already exists!'));
        return new WP_Error('multisite_unsupported',__('Creating additional sites is not supported.'));
    }
}
if(!function_exists('wpmu_activate_signup')){
    function wpmu_activate_signup($key) {
        $all=rrw_ms_signups();$sg=$all[$key]??null;
        if(!$sg)return new WP_Error('invalid_key',__('Invalid activation key.'));
        if(!empty($sg['active']))return new WP_Error('already_active',empty($sg['domain'])?__('The user is already active.'):__('The site is already active.'),(object)$sg);
        if(!empty($sg['domain']))return new WP_Error('multisite_unsupported',__('Creating additional sites is not supported.'),(object)$sg);
        $meta=(array)$sg['meta'];$password=wp_generate_password(12,false);$exists=username_exists($sg['user_login']);
        $user_id=$exists?:wpmu_create_user($sg['user_login'],$password,$sg['user_email']);
        if(!$user_id)return new WP_Error('create_user',__('Could not create user'),(object)$sg);
        $all[$key]['active']=1;$all[$key]['activated']=gmdate('Y-m-d H:i:s');rrw_ms_signups_save($all);
        if($exists)return new WP_Error('user_already_exists',__('That username is already activated.'),(object)$sg);
        do_action('wpmu_activate_user',$user_id,$password,$meta);
        return ['user_id'=>$user_id,'password'=>$password,'meta'=>$meta];
    }
}
if(!function_exists('wp_delete_signup_on_user_delete')){
    function wp_delete_signup_on_user_delete($id) {
        $u=get_userdata((int)$id);if(!$u)return;$s=rrw_ms_signups();$n=count($s);
        foreach($s as $k=>$r)if($r['user_login']===$u->user_login)unset($s[$k]);
        if(count($s)!==$n)rrw_ms_signups_save($s);
    }
}
if(!function_exists('signup_nonce_fields')){
    function signup_nonce_fields() { $id=mt_rand();echo "<input type='hidden' name='signup_form_id' value='{$id}' />";wp_nonce_field('signup_form_'.$id,'_signup_form',false); }
}
if(!function_exists('signup_nonce_check')){
    function signup_nonce_check($result) {
        if(!str_contains((string)($_SERVER['PHP_SELF']??''),'wp-signup.php'))return $result;
        if(!wp_verify_nonce($_POST['_signup_form']??'','signup_form_'.($_POST['signup_form_id']??'')))wp_die(__('Please try again.'));
        return $result;
    }
}
if(!function_exists('maybe_add_existing_user_to_blog')){
    function maybe_add_existing_user_to_blog() {
        $uri=(string)($_SERVER['REQUEST_URI']??'');if(!str_contains($uri,'/newbloguser/'))return false;
        $parts=explode('/',(string)parse_url($uri,PHP_URL_PATH));$key=array_pop($parts);if($key==='')$key=array_pop($parts);
        $details=get_option('new_user_'.$key);
        if(!is_array($details)||is_wp_error(add_existing_user_to_blog($details)))wp_die(sprintf(__('An error occurred adding you to this site. Go to the <a href="%s">homepage</a>.'),home_url()));
        delete_option('new_user_'.$key);
        wp_die(sprintf(__('You have been added to this site. Please visit the <a href="%1$s">homepage</a> or <a href="%2$s">log in</a> using your username and password.'),home_url(),admin_url()),__('WordPress &rsaquo; Success'),['response'=>200]);
    }
}
if(!function_exists('add_existing_user_to_blog')){
    function add_existing_user_to_blog($details=false) {
        $result=null;
        if(is_array($details)){
            $id=(int)($details['user_id']??0);$role=(string)($details['role']??'');
            if(!get_userdata($id))$result=new WP_Error('user_does_not_exist',__('That user does not exist.'));
            elseif(!get_role($role))$result=new WP_Error('invalid_role',__('Invalid role.'));
            else{ rrw_ms_set_role($id,$role);$result=true; }
            do_action('added_existing_user',$id,$result);
        }
        return $result;
    }
}
if(!function_exists('add_new_user_to_blog')){
    function add_new_user_to_blog($user_id,$password,$meta) {
        if(empty($meta['add_to_blog']))return;
        $blog_id=(int)$meta['add_to_blog'];$role=(string)($meta['new_role']??'subscriber');
        if($blog_id!==1)return;
        $r=add_existing_user_to_blog(['user_id'=>(int)$user_id,'role'=>$role]);
        if(!is_wp_error($r))update_user_meta((int)$user_id,'primary_blog',$blog_id);
    }
}

/* ───────── Benachrichtigungen ───────── */
if(!function_exists('newblog_notify_siteadmin')){
    function newblog_notify_siteadmin($blog_id,$deprecated='') {
        if(get_network_option(1,'registrationnotification')!=='yes')return false;
        $email=get_network_option(1,'admin_email');if(!is_email($email))return false;
        $name=get_option('blogname');$url=site_url();
        $msg=sprintf(__("New Site: %1\$s\nURL: %2\$s\nRemote IP address: %3\$s\n\nDisable these notifications: %4\$s"),$name,$url,wp_unslash($_SERVER['REMOTE_ADDR']??''),esc_url(network_admin_url('settings.php')));
        $msg=apply_filters('newblog_notify_siteadmin',$msg,$blog_id);
        wp_mail($email,sprintf(__('New Site Registration: %s'),$url),$msg);return true;
    }
}
if(!function_exists('newuser_notify_siteadmin')){
    function newuser_notify_siteadmin($user_id) {
        if(get_network_option(1,'registrationnotification')!=='yes')return false;
        $email=get_network_option(1,'admin_email');if(!is_email($email))return false;
        $u=get_userdata((int)$user_id);if(!$u)return false;
        $msg=sprintf(__("New User: %1\$s\nRemote IP address: %2\$s\n\nDisable these notifications: %3\$s"),$u->user_login,wp_unslash($_SERVER['REMOTE_ADDR']??''),esc_url(network_admin_url('settings.php')));
        $msg=apply_filters('newuser_notify_siteadmin',$msg,$u);
        wp_mail($email,sprintf(__('New User Registration: %s'),$u->user_login),$msg);return true;
    }
}
if(!function_exists('wpmu_new_site_admin_notification')){
    function wpmu_new_site_admin_notification($site_id,$user_id) {
        $site=WP_Site::get_instance((int)$site_id);$user=get_userdata((int)$user_id);$email=get_network_option(1,'admin_email');
        if(!$site||!$user||!$email||!apply_filters('send_new_site_email',true,$site,$user))return false;
        $msg=sprintf(__("New site created by %1\$s\n\nAddress: %2\$s\nName: %3\$s"),$user->user_login,get_site_url((int)$site_id),get_option('blogname'));
        wp_mail($email,sprintf(__('[%s] New Site Created'),rrw_ms_site_name()),$msg,'Content-Type: text/plain; charset="'.get_option('blog_charset','UTF-8').'"');return true;
    }
}
if(!function_exists('welcome_user_msg_filter')){
    // Standardtext für die Willkommens-E-Mail an neue Benutzer, wenn keiner hinterlegt ist.
    function welcome_user_msg_filter($text) {
        if($text)return $text;
        return str_replace('SITE_NAME',rrw_ms_site_name(),__("Howdy USERNAME,\n\nYour new account is set up.\n\nYou can log in with the following information:\nUsername: USERNAME\nPassword: PASSWORD\nLOGINLINK\n\nThanks!\n\n--The Team @ SITE_NAME"));
    }
}
if(!function_exists('wpmu_welcome_notification')){
    function wpmu_welcome_notification($blog_id,$user_id,$password,$title,$meta=[]) {
        if(!apply_filters('wpmu_welcome_notification',$blog_id,$user_id,$password,$title,$meta))return false;
        $user=get_userdata((int)$user_id);if(!$user)return false;
        $t=get_network_option(1,'welcome_email');
        if(!$t)$t=__("Howdy USERNAME,\n\nYour new SITE_NAME site has been successfully set up at:\nBLOG_URL\n\nYou can log in to the administrator account with the following information:\n\nUsername: USERNAME\nPassword: PASSWORD\nLog in here: BLOG_URLwp-login.php\n\nWe hope you enjoy your new site. Thanks!\n\n--The Team @ SITE_NAME");
        $t=apply_filters('update_welcome_email',$t,$blog_id,$user_id,$password,$title,$meta);
        $t=str_replace(['SITE_NAME','BLOG_URL','USERNAME','PASSWORD'],[rrw_ms_site_name(),trailingslashit(home_url()),$user->user_login,$password],$t);
        $subject=apply_filters('update_welcome_subject',sprintf(__('New %1$s Site: %2$s'),rrw_ms_site_name(),wp_unslash($title)));
        wp_mail($user->user_email,wp_specialchars_decode($subject),$t);return true;
    }
}
if(!function_exists('wpmu_welcome_user_notification')){
    function wpmu_welcome_user_notification($user_id,$password,$meta=[]) {
        if(!apply_filters('wpmu_welcome_user_notification',$user_id,$password,$meta))return false;
        $user=get_userdata((int)$user_id);if(!$user)return false;
        $t=get_network_option(1,'welcome_user_email');$t=welcome_user_msg_filter($t);
        $t=apply_filters('update_welcome_user_email',$t,$user_id,$password,$meta);
        $t=str_replace(['SITE_NAME','USERNAME','PASSWORD','LOGINLINK'],[rrw_ms_site_name(),$user->user_login,$password,wp_login_url()],$t);
        $subject=apply_filters('update_welcome_user_subject',sprintf(__('New %1$s User: %2$s'),rrw_ms_site_name(),$user->user_login));
        wp_mail($user->user_email,wp_specialchars_decode($subject),$t);return true;
    }
}
if(!function_exists('update_network_option_new_admin_email')){
    function update_network_option_new_admin_email($old_value,$value) {
        if($value===get_network_option(1,'admin_email')||!is_email($value))return;
        $hash=md5($value.time().mt_rand());
        update_network_option(1,'network_admin_hash',['hash'=>$hash,'newemail'=>$value]);
        $msg=sprintf(__("Howdy ###USERNAME###,\n\nYou recently requested to have the network admin email address on your network changed.\n\nIf this is correct, please click on the following link to change it:\n###ADMIN_URL###\n\nYou can safely ignore and delete this email if you do not want to take this action.\n\nThis email has been sent to ###EMAIL###"));
        $msg=str_replace(['###USERNAME###','###ADMIN_URL###','###EMAIL###'],[wp_get_current_user()->user_login??'',esc_url(network_admin_url('settings.php?network_admin_hash='.$hash)),$value],$msg);
        wp_mail($value,sprintf(__('[%s] New Network Admin Email Address'),wp_specialchars_decode(rrw_ms_site_name())),$msg);
    }
}
if(!function_exists('wp_network_admin_email_change_notification')){
    function wp_network_admin_email_change_notification($option_name,$new_email,$old_email,$network_id) {
        if(!apply_filters('send_network_admin_email_change_email',true,$old_email,$new_email,$network_id))return;
        $msg=sprintf(__("Hi,\n\nThis notice confirms that the network admin email address was changed on %1\$s.\n\nThe new network admin email address is %2\$s.\n\nThis email has been sent to %3\$s"),rrw_ms_site_name(),$new_email,$old_email);
        wp_mail($old_email,sprintf(__('[%s] Network Admin Email Changed'),wp_specialchars_decode(rrw_ms_site_name())),$msg);
    }
}

/* ───────── Sonstige Filter ───────── */
if(!function_exists('check_upload_mimes')){
    function check_upload_mimes($mimes) {
        $exts=explode(' ',(string)get_network_option(1,'upload_filetypes','jpg jpeg png gif'));
        foreach($mimes as $ext=>$mime)if(!array_intersect(explode('|',(string)$ext),$exts))unset($mimes[$ext]);
        return $mimes;
    }
}
if(!function_exists('upload_is_file_too_big')){
    function upload_is_file_too_big($upload) {
        if(!is_array($upload)||defined('WP_IMPORTING')||get_network_option(1,'upload_space_check_disabled',1)||!isset($upload['bits']))return $upload;
        $max=(int)get_network_option(1,'fileupload_maxk',1500);
        return strlen((string)$upload['bits'])>KB_IN_BYTES*$max?sprintf(__('This file is too big. Files must be less than %d KB in size.'),$max).'<br />':$upload;
    }
}
if(!function_exists('redirect_this_site')){
    function redirect_this_site($deprecated='') { return (array)apply_filters('redirect_this_site',[rrw_ms_network_row()['domain']]); }
}
if(!function_exists('maybe_redirect_404')){
    function maybe_redirect_404() {
        if(!is_main_site()||!is_404()||!defined('NOBLOGREDIRECT'))return;
        $dest=apply_filters('blog_redirect_404',NOBLOGREDIRECT);if(!$dest)return;
        if($dest==='%siteurl%')$dest=network_home_url();
        wp_redirect($dest);exit;
    }
}
if(!function_exists('fix_phpmailer_messageid')){ function fix_phpmailer_messageid($phpmailer) { $phpmailer->Hostname=rrw_ms_network_row()['domain']; } }
if(!function_exists('update_blog_public')){ function update_blog_public($old_value,$value) { update_blog_status(1,'public',(int)$value); } }
if(!function_exists('users_can_register_signup_filter')){
    function users_can_register_signup_filter() { $r=get_network_option(1,'registration');return 'all'===$r||'user'===$r; }
}
if(!function_exists('force_ssl_content')){
    function force_ssl_content($force='') { static $forced=false;if($force!=='')$forced=(bool)$force;return $forced; }
}
if(!function_exists('filter_SSL')){
    function filter_SSL($url) {
        if(!is_string($url))return get_bloginfo('url');
        return force_ssl_content()&&is_ssl()?set_url_scheme($url,'https'):$url;
    }
}

/* ───────── Netzwerk-Zähler ───────── */
if(!function_exists('wp_is_large_network')){
    function wp_is_large_network($using='sites',$network_id=null) {
        if('users'===$using){ $c=get_user_count($network_id);return (bool)apply_filters('wp_is_large_network',$c>10000,'users',$c,$network_id); }
        $c=get_blog_count($network_id);return (bool)apply_filters('wp_is_large_network',$c>10000,'sites',$c,$network_id);
    }
}
if(!function_exists('wp_update_network_site_counts')){
    function wp_update_network_site_counts($network_id=null) { $c=wp_count_sites($network_id);update_network_option((int)$network_id?:1,'blog_count',($c['archived']||$c['spam']||$c['deleted'])?0:1); }
}
if(!function_exists('wp_update_network_user_counts')){
    function wp_update_network_user_counts($network_id=null) { update_network_option((int)$network_id?:1,'user_count',(int)(count_users()['total_users']??0)); }
}
if(!function_exists('wp_update_network_counts')){
    function wp_update_network_counts($network_id=null) { wp_update_network_user_counts($network_id);wp_update_network_site_counts($network_id); }
}
if(!function_exists('wp_maybe_update_network_site_counts')){
    function wp_maybe_update_network_site_counts($network_id=null) {
        if(!apply_filters('enable_live_network_counts',!wp_is_large_network('sites',$network_id),'sites'))return;
        wp_update_network_site_counts($network_id);
    }
}
if(!function_exists('wp_maybe_update_network_user_counts')){
    function wp_maybe_update_network_user_counts($network_id=null) {
        if(!apply_filters('enable_live_network_counts',!wp_is_large_network('users',$network_id),'users'))return;
        wp_update_network_user_counts($network_id);
    }
}
if(!function_exists('wp_schedule_update_network_counts')){
    function wp_schedule_update_network_counts() {
        if(wp_installing()||wp_next_scheduled('update_network_counts'))return;
        wp_schedule_event(time(),'twicedaily','update_network_counts');
    }
}

/* ───────── Speicherplatz ───────── */
if(!function_exists('get_space_used')){
    function get_space_used() {
        $used=apply_filters('pre_get_space_used',false);if(false!==$used)return $used;
        $d=wp_upload_dir(null,false);return rrw_ms_dirsize((string)$d['basedir'])/MB_IN_BYTES;
    }
}
if(!function_exists('get_space_allowed')){
    function get_space_allowed() {
        $a=get_option('blog_upload_space');if(!is_numeric($a))$a=get_network_option(1,'blog_upload_space');if(!is_numeric($a))$a=100;
        return apply_filters('get_space_allowed',$a);
    }
}
if(!function_exists('get_upload_space_available')){
    function get_upload_space_available() {
        $allowed=max(0,get_space_allowed())*MB_IN_BYTES;
        if(get_network_option(1,'upload_space_check_disabled',1))return $allowed;
        $left=$allowed-get_space_used()*MB_IN_BYTES;return $left<=0?0:$left;
    }
}
if(!function_exists('is_upload_space_available')){
    function is_upload_space_available() { return get_network_option(1,'upload_space_check_disabled',1)?true:(bool)get_upload_space_available(); }
}
if(!function_exists('upload_size_limit_filter')){
    function upload_size_limit_filter($size) {
        $max=KB_IN_BYTES*(int)get_network_option(1,'fileupload_maxk',1500);
        return get_network_option(1,'upload_space_check_disabled',1)?min($size,$max):min($size,$max,get_upload_space_available());
    }
}

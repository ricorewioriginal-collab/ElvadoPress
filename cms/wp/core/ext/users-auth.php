<?php
// Ergänzung (Benutzer): Anmeldung/Authentifizierung, Passwörter, Cookies, Sitzungen und Passwort-Zurücksetzen (user.php, pluggable.php).
// Nur Einzelseiten-Betrieb. Benutzer aus der Tabelle wp_users sind änderbar; reine CMS-Redakteure haben kein Passwort in der WordPress-Schicht.

/** Standard-Filter der Anmeldung einmalig anmelden (statt beim Laden). */
function rrw_wpx_auth_defaults(): void {
    static $done=false;if($done)return;$done=true;
    foreach([['wp_authenticate_username_password',20],['wp_authenticate_email_password',20],['wp_authenticate_application_password',20],['wp_authenticate_cookie',30],['wp_authenticate_spam_check',99]] as [$f,$p])
        if(!has_filter('authenticate',$f))add_filter('authenticate',$f,$p,3);
}
/** Schneller Hash für Schlüssel (Zurücksetzen, Datenschutzanfragen). */
function rrw_wpx_fast_hash(string $s): string { return '$generic$'.hash_hmac('sha256',$s,wp_salt('auth')); }
function rrw_wpx_fast_verify(string $s, string $h): bool { return $h!==''&&hash_equals($h,rrw_wpx_fast_hash($s)); }
/** phpass-Prüfsumme (portable Hashes „$P$“/„$H$“ älterer WordPress-Installationen). */
function rrw_wpx_phpass(string $password, string $setting): string {
    $itoa='./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    if(!in_array(substr($setting,0,3),['$P$','$H$'],true))return '*0';
    $c=strpos($itoa,$setting[3]??'');if($c===false||$c<7||$c>30)return '*0';
    $salt=substr($setting,4,8);if(strlen($salt)!==8)return '*0';
    $count=1<<$c;$h=md5($salt.$password,true);do{ $h=md5($h.$password,true); }while(--$count);
    $out=substr($setting,0,12);$i=0;$n=16;
    do{ $v=ord($h[$i++]);$out.=$itoa[$v&0x3f];if($i<$n)$v|=ord($h[$i])<<8;$out.=$itoa[($v>>6)&0x3f];if($i++>=$n)break;
        if($i<$n)$v|=ord($h[$i])<<16;$out.=$itoa[($v>>12)&0x3f];if($i++>=$n)break;$out.=$itoa[($v>>18)&0x3f]; }while($i<$n);
    return $out;
}
/** Sitzungen eines Benutzers (Benutzer-Meta „session_tokens“, Schlüssel = SHA-256 des Tokens), abgelaufene werden verworfen. */
function rrw_wpx_sess_load(int $uid): array {
    $s=get_user_meta($uid,'session_tokens',true);if(!is_array($s))return [];$now=time();
    return array_filter($s,fn($x)=>is_array($x)&&(int)($x['expiration']??0)>=$now);
}
function rrw_wpx_sess_save(int $uid, array $s): void { if($s)update_user_meta($uid,'session_tokens',$s);else delete_user_meta($uid,'session_tokens'); }

/* ───────── Passwörter (pluggable.php) ───────── */
if(!function_exists('wp_hash_password')){
    /** bcrypt über einen SHA-384-Vorhash (Präfix „$wp“, kein 72-Byte-Limit). */
    function wp_hash_password($password) {
        $pre=base64_encode(hash_hmac('sha384',trim((string)$password),'wp-sha384',true));
        return '$wp'.password_hash($pre,PASSWORD_BCRYPT,(array)apply_filters('wp_hash_password_options',[]));
    }
}
if(!function_exists('wp_check_password')){
    /** Prüft $wp-, bcrypt/argon-, phpass- und alte MD5-Hashes. Bei Erfolg wird ein veralteter Hash des Datenbank-Benutzers erneuert. */
    function wp_check_password($password, $hash, $user_id='') {
        $password=(string)$password;$hash=(string)$hash;
        if(str_starts_with($hash,'$wp'))$check=password_verify(base64_encode(hash_hmac('sha384',trim($password),'wp-sha384',true)),substr($hash,3));
        elseif(str_starts_with($hash,'$P$')||str_starts_with($hash,'$H$'))$check=hash_equals($hash,rrw_wpx_phpass($password,$hash));
        elseif(str_starts_with($hash,'$'))$check=password_verify($password,$hash);
        else $check=strlen($hash)<=32&&hash_equals($hash,md5($password));
        if($check&&$user_id&&(int)$user_id>=RRW_WP_ID_DB_MIN&&wp_password_needs_rehash($hash,$user_id))wp_set_password($password,(int)$user_id);
        return (bool)apply_filters('check_password',$check,$password,$hash,$user_id);
    }
}
if(!function_exists('wp_password_needs_rehash')){
    function wp_password_needs_rehash($hash, $user_id='') {
        $hash=(string)$hash;
        $needs=!str_starts_with($hash,'$wp')||password_needs_rehash(substr($hash,3),PASSWORD_BCRYPT,(array)apply_filters('wp_hash_password_options',[]));
        return (bool)apply_filters('password_needs_rehash',$needs,$hash,$user_id);
    }
}
if(!function_exists('wp_set_password')){
    /** Neues Passwort speichern (nur Benutzer der Tabelle wp_users); entwertet den Zurücksetzen-Schlüssel. */
    function wp_set_password($password, $user_id) {
        global $wpdb;if(!rrw_wp_db_ready()||(int)$user_id<RRW_WP_ID_DB_MIN)return;
        $old=get_userdata((int)$user_id);
        $wpdb->update($wpdb->users,['user_pass'=>wp_hash_password($password),'user_activation_key'=>''],['ID'=>(int)$user_id]);
        clean_user_cache((int)$user_id);
        do_action('wp_set_password',$password,(int)$user_id,$old);
    }
}

/* ───────── Authentifizierung (user.php, pluggable.php) ───────── */
if(!function_exists('wp_authenticate')){
    function wp_authenticate($username, $password) {
        rrw_wpx_auth_defaults();
        $username=sanitize_user((string)$username);$password=trim((string)$password);
        $user=apply_filters('authenticate',null,$username,$password);
        if(null==$user)$user=new WP_Error('authentication_failed','<strong>Fehler:</strong> Ungültiger Benutzername, ungültige E-Mail-Adresse oder falsches Passwort.');
        if(is_wp_error($user)&&!in_array($user->get_error_code(),['empty_username','empty_password'],true))do_action('wp_login_failed',$username,$user);
        return $user;
    }
}
if(!function_exists('wp_signon')){
    /** Anmeldung prüfen; setzt über wp_set_auth_cookie() den Cookie (hier ohne Wirkung – die Sitzung führt das CMS). */
    function wp_signon($credentials=[], $secure_cookie='') {
        if(empty($credentials))$credentials=['user_login'=>wp_unslash($_POST['log']??''),'user_password'=>$_POST['pwd']??'','remember'=>!empty($_POST['rememberme'])];
        $credentials['remember']=!empty($credentials['remember']);
        do_action('wp_authenticate',$credentials['user_login']??'',$credentials['user_password']??'');
        $user=wp_authenticate($credentials['user_login']??'',$credentials['user_password']??'');
        if(is_wp_error($user))return $user;
        wp_set_auth_cookie($user->ID,$credentials['remember'],$secure_cookie);
        do_action('wp_login',$user->user_login,$user);
        return $user;
    }
}
if(!function_exists('wp_authenticate_username_password')){
    function wp_authenticate_username_password($user, $username, $password) {
        if($user instanceof WP_User)return $user;
        if(empty($username)||empty($password)){
            if(is_wp_error($user))return $user;
            $e=new WP_Error();
            if(empty($username))$e->add('empty_username','<strong>Fehler:</strong> Das Feld „Benutzername“ ist leer.');
            if(empty($password))$e->add('empty_password','<strong>Fehler:</strong> Das Feld „Passwort“ ist leer.');
            return $e;
        }
        $user=get_user_by('login',$username);
        if(!$user)return new WP_Error('invalid_username','<strong>Fehler:</strong> Der Benutzername ist nicht registriert.');
        $user=apply_filters('wp_authenticate_user',$user,$password);
        if(is_wp_error($user))return $user;
        if(!wp_check_password($password,(string)$user->user_pass,$user->ID))return new WP_Error('incorrect_password','<strong>Fehler:</strong> Das Passwort für den Benutzernamen '.esc_html($username).' ist falsch.');
        return $user;
    }
}
if(!function_exists('wp_authenticate_email_password')){
    function wp_authenticate_email_password($user, $email, $password) {
        if($user instanceof WP_User)return $user;
        if(empty($email)||empty($password)){
            if(is_wp_error($user))return $user;
            $e=new WP_Error();
            if(empty($email))$e->add('empty_username','<strong>Fehler:</strong> Das Feld „E-Mail-Adresse“ ist leer.');
            if(empty($password))$e->add('empty_password','<strong>Fehler:</strong> Das Feld „Passwort“ ist leer.');
            return $e;
        }
        if(!is_email($email))return $user;
        $user=get_user_by('email',$email);
        if(!$user)return new WP_Error('invalid_email','<strong>Fehler:</strong> Unbekannte E-Mail-Adresse.');
        $user=apply_filters('wp_authenticate_user',$user,$password);
        if(is_wp_error($user))return $user;
        if(!wp_check_password($password,(string)$user->user_pass,$user->ID))return new WP_Error('incorrect_password','<strong>Fehler:</strong> Das Passwort für die E-Mail-Adresse '.esc_html($email).' ist falsch.');
        return $user;
    }
}
if(!function_exists('wp_authenticate_cookie')){
    function wp_authenticate_cookie($user, $username, $password) {
        if($user instanceof WP_User)return $user;
        if(empty($username)&&empty($password)){ $id=wp_validate_auth_cookie();if($id)return new WP_User($id); }
        return $user;
    }
}
if(!function_exists('wp_authenticate_application_password')){
    /** Anwendungspasswörter (Benutzer-Meta „_application_passwords“); in dieser Schicht nur aktiv, wenn wp_is_application_passwords_available() wahr liefert (derzeit nie). */
    function wp_authenticate_application_password($input_user, $username, $password) {
        if(!wp_is_application_passwords_available()||!apply_filters('application_password_is_api_request',defined('REST_REQUEST')&&REST_REQUEST))return $input_user;
        $user=is_email($username)?get_user_by('email',$username):get_user_by('login',$username);
        if(!$user)return new WP_Error('invalid_username','<strong>Fehler:</strong> Der Benutzername ist nicht registriert.');
        if(!wp_is_application_passwords_available_for_user($user))return new WP_Error('application_passwords_disabled_for_user','Anwendungspasswörter sind für diesen Benutzer nicht verfügbar.');
        $plain=preg_replace('/[^a-z\d]/i','',(string)$password);$items=get_user_meta($user->ID,'_application_passwords',true);
        foreach(is_array($items)?$items:[] as $k=>$it){
            if(!empty($it['password'])&&wp_check_password($plain,(string)$it['password'])){
                $items[$k]['last_used']=time();$items[$k]['last_ip']=$_SERVER['REMOTE_ADDR']??'';update_user_meta($user->ID,'_application_passwords',$items);
                return $user;
            }
        }
        return new WP_Error('incorrect_password','<strong>Fehler:</strong> Das Anwendungspasswort ist falsch.');
    }
}
if(!function_exists('wp_validate_application_password')){
    /** Prüft HTTP-Basic-Zugangsdaten gegen Anwendungspasswörter; liefert die Benutzer-ID oder den übergebenen Wert zurück. */
    function wp_validate_application_password($input_user) {
        if(!empty($input_user)||!wp_is_application_passwords_available()||!isset($_SERVER['PHP_AUTH_USER']))return $input_user;
        $u=wp_authenticate_application_password(null,(string)$_SERVER['PHP_AUTH_USER'],(string)($_SERVER['PHP_AUTH_PW']??''));
        return $u instanceof WP_User?$u->ID:$input_user;
    }
}
if(!function_exists('wp_authenticate_spam_check')){
    /** Einzelseite: es gibt keine als Spam markierten Konten – der Benutzer wird unverändert durchgereicht. */
    function wp_authenticate_spam_check($user) { return $user; }
}
if(!function_exists('wp_validate_logged_in_cookie')){
    function wp_validate_logged_in_cookie($user_id) {
        if($user_id)return $user_id;
        $name=defined('LOGGED_IN_COOKIE')?LOGGED_IN_COOKIE:'wordpress_logged_in_'.COOKIEHASH;
        if(is_admin()||empty($_COOKIE[$name]))return false;
        return wp_validate_auth_cookie($_COOKIE[$name],'logged_in');
    }
}
if(!function_exists('wp_generate_auth_cookie')){
    /** Cookie-Wert „Login|Ablauf|Token|HMAC“ (Format wie in WordPress); der Token wird als Sitzung gespeichert. */
    function wp_generate_auth_cookie($user_id, $expiration, $scheme='auth', $token='') {
        $user=get_userdata((int)$user_id);if(!$user)return '';
        if($token===''){ $token=bin2hex(random_bytes(16));$s=rrw_wpx_sess_load((int)$user_id);
            $s[hash('sha256',$token)]=['expiration'=>(int)$expiration+12*HOUR_IN_SECONDS,'ip'=>$_SERVER['REMOTE_ADDR']??'','ua'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,255),'login'=>time()];rrw_wpx_sess_save((int)$user_id,$s); }
        $frag=substr((string)$user->user_pass,8,4);
        $key=wp_hash($user->user_login.'|'.$frag.'|'.$expiration.'|'.$token,$scheme);
        $hash=hash_hmac('sha256',$user->user_login.'|'.$expiration.'|'.$token,$key);
        return apply_filters('auth_cookie',$user->user_login.'|'.$expiration.'|'.$token.'|'.$hash,$user_id,$expiration,$scheme,$token);
    }
}
if(!function_exists('auth_redirect')){
    /** Nicht angemeldete Besucher zur Anmeldeadresse umleiten (wp_redirect beendet im Admin-Betrieb per Ausnahme). */
    function auth_redirect() {
        if(is_user_logged_in()){ do_action('auth_redirect',get_current_user_id());return; }
        nocache_headers();
        $url=(is_ssl()?'https://':'http://').($_SERVER['HTTP_HOST']??'localhost').($_SERVER['REQUEST_URI']??'/');
        wp_redirect(wp_login_url($url),302);
        if(empty($GLOBALS['rrw_wp_is_admin'])&&!defined('RRW_WP_TEST'))exit;
    }
}
if(!function_exists('_wp_sanitize_utf8_in_redirect')){
    /** Rückruf für preg_replace_callback: nicht-ASCII-Treffer prozentcodieren. */
    function _wp_sanitize_utf8_in_redirect($matches) { return urlencode((string)$matches[0]); }
}
if(!function_exists('cache_users')){
    function cache_users($user_ids) { foreach(array_filter(array_map('intval',(array)$user_ids)) as $id){ $u=get_userdata($id);if($u)wp_cache_set($id,$u,'users'); } }
}

/* ───────── Benutzer-Hilfen (user.php) ───────── */
if(!function_exists('get_user')){ function get_user($user_id) { return get_userdata($user_id); } }
if(!function_exists('wp_cache_set_users_last_changed')){ function wp_cache_set_users_last_changed() { wp_cache_set('last_changed',microtime(),'users'); } }
if(!function_exists('clean_user_cache')){
    function clean_user_cache($user) {
        $u=is_numeric($user)?get_userdata((int)$user):$user;if(!$u instanceof WP_User)return;
        wp_cache_delete($u->ID,'users');wp_cache_delete($u->user_login,'userlogins');wp_cache_delete($u->user_email,'useremail');wp_cache_delete($u->user_nicename,'userslugs');
        rrw_wp_users_all(true);wp_cache_set_users_last_changed();
        do_action('clean_user_cache',$u->ID,$u);
    }
}
if(!function_exists('update_user_caches')){
    function update_user_caches($user) {
        if(!$user instanceof WP_User)return false;
        wp_cache_add($user->ID,$user,'users');wp_cache_add($user->user_login,$user->ID,'userlogins');wp_cache_add($user->user_email,$user->ID,'useremail');wp_cache_add($user->user_nicename,$user->ID,'userslugs');
        return true;
    }
}
if(!function_exists('wp_get_user_contact_methods')){
    /** Seit WordPress 3.6 gibt es keine Standard-Kontaktfelder mehr; Plugins ergänzen über den Filter. */
    function wp_get_user_contact_methods($user=null) { return (array)apply_filters('user_contactmethods',[],$user); }
}
if(!function_exists('_wp_get_user_contactmethods')){ function _wp_get_user_contactmethods($user=null) { return wp_get_user_contact_methods($user); } }
if(!function_exists('_get_additional_user_keys')){
    function _get_additional_user_keys($user) {
        $keys=['nickname','description','rich_editing','syntax_highlighting','comment_shortcuts','admin_color','use_ssl','show_admin_bar_front','locale'];
        return array_merge($keys,array_keys(wp_get_user_contact_methods($user)));
    }
}
if(!function_exists('sanitize_user_field')){
    function sanitize_user_field($field, $value, $user_id, $context) {
        if($field==='ID')$value=(int)$value;
        if($context==='raw')return $value;
        if(!is_string($value)&&!is_numeric($value))return $value;
        $pre=str_contains($field,'user_');
        if($context==='edit'){
            $value=apply_filters($pre?"edit_{$field}":"edit_user_{$field}",$value,$user_id);
            return $field==='description'?esc_html($value):esc_attr($value);
        }
        if($context==='db')return apply_filters($pre?"pre_{$field}":"pre_user_{$field}",$value);
        $value=$pre?apply_filters("{$field}",$value,$user_id,$context):apply_filters("user_{$field}",$value,$user_id,$context);
        if($field==='user_url')$value=esc_url($value);
        if($context==='attribute')$value=esc_attr($value);elseif($context==='js')$value=esc_js($value);
        return $value;
    }
}
if(!function_exists('count_user_posts')){
    function count_user_posts($userid, $post_type='post', $public_only=false) {
        $st=['publish'];if(!$public_only&&get_current_user_id()===(int)$userid)$st[]='private';
        $ids=get_posts(['author'=>(int)$userid,'post_type'=>$post_type,'post_status'=>$st,'numberposts'=>-1,'fields'=>'ids','suppress_filters'=>true]);
        return (int)apply_filters('get_usernumposts',count((array)$ids),$userid,$post_type,$public_only);
    }
}
if(!function_exists('count_many_users_posts')){
    function count_many_users_posts($users, $post_type='post', $public_only=false) {
        $out=[];foreach(array_map('intval',(array)$users) as $id)$out[$id]=count_user_posts($id,$post_type,$public_only);
        return apply_filters('pre_count_many_users_posts',$out,$users,$post_type,$public_only);
    }
}
if(!function_exists('get_blogs_of_user')){
    /** Einzelseite: genau die eine Website, sofern der Benutzer existiert. */
    function get_blogs_of_user($user_id, $all=false) {
        if(!get_userdata((int)$user_id))return [];
        $h=(string)parse_url(home_url(),PHP_URL_HOST);
        return [1=>(object)['userblog_id'=>1,'blogname'=>get_option('blogname',''),'domain'=>$h,'path'=>'/','site_id'=>1,'siteurl'=>site_url(),'archived'=>0,'spam'=>0,'deleted'=>0]];
    }
}
if(!function_exists('wp_update_user_counts')){
    function wp_update_user_counts($network_id=null) { update_option('user_count',count(rrw_wp_users_all(true)));do_action('wp_update_user_counts'); }
}
if(!function_exists('get_user_count')){
    /** Zwischengespeicherte Benutzerzahl; -1, wenn noch nie ermittelt. */
    function get_user_count($network_id=null) { return (int)get_option('user_count',-1); }
}
if(!function_exists('wp_maybe_update_user_counts')){
    function wp_maybe_update_user_counts() { if(get_user_count()<0)wp_update_user_counts(); }
}
if(!function_exists('wp_schedule_update_user_counts')){
    function wp_schedule_update_user_counts() { if(!wp_next_scheduled('wp_update_user_counts')&&!wp_installing())wp_schedule_event(time(),'twicedaily','wp_update_user_counts'); }
}
if(!function_exists('wp_is_large_user_count')){
    function wp_is_large_user_count($network_id=null) { $c=get_user_count($network_id);if($c<0){wp_update_user_counts($network_id);$c=get_user_count($network_id);} return (bool)apply_filters('wp_is_large_user_count',$c>10000,$c,$network_id); }
}
if(!function_exists('setup_userdata')){
    function setup_userdata($for_user_id='') {
        global $user_login,$userdata,$user_level,$user_ID,$user_email,$user_url,$user_identity;
        if($for_user_id==='')$for_user_id=get_current_user_id();
        $user=get_userdata((int)$for_user_id);
        if(!$user){ $user_ID=0;$user_level=0;$userdata=null;$user_login=$user_email=$user_url=$user_identity='';return; }
        $user_ID=(int)$user->ID;$user_level=(int)get_user_meta($user->ID,'user_level',true);$userdata=$user;
        $user_login=$user->user_login;$user_email=$user->user_email;$user_url=$user->user_url;$user_identity=$user->display_name;
    }
}
if(!function_exists('wp_get_users_with_no_role')){
    function wp_get_users_with_no_role($site_id=null) { $o=[];foreach(rrw_wp_users_all() as $u)if(($u['role']??'')===''||$u['role']==='none')$o[]=(int)$u['ID'];return $o; }
}
if(!function_exists('_wp_get_current_user')){ function _wp_get_current_user() { return wp_get_current_user(); } }
if(!function_exists('wp_is_password_reset_allowed_for_user')){
    function wp_is_password_reset_allowed_for_user($user) {
        if(!$user instanceof WP_User)$user=get_userdata((int)$user);
        if(!$user||!$user->exists())return false;
        return apply_filters('allow_password_reset',true,$user->ID);
    }
}
if(!function_exists('wp_register_persisted_preferences_meta')){
    function wp_register_persisted_preferences_meta() {
        global $wpdb;
        register_meta('user',$wpdb->prefix.'persisted_preferences',['type'=>'object','single'=>true,'show_in_rest'=>['name'=>'persisted_preferences','type'=>'object','schema'=>['type'=>'object','context'=>['edit'],'properties'=>['_modified'=>['description'=>'Zeitpunkt der letzten Änderung.','type'=>'string','format'=>'date-time','readonly'=>true]],'additionalProperties'=>true]]]);
    }
}

/* ───────── Sitzungen ───────── */
if(!function_exists('wp_get_session_token')){
    function wp_get_session_token() {
        $name=defined('LOGGED_IN_COOKIE')?LOGGED_IN_COOKIE:'wordpress_logged_in_'.COOKIEHASH;
        $p=explode('|',(string)($_COOKIE[$name]??''));
        return count($p)===4?(string)$p[2]:rrw_wp_session_token();
    }
}
if(!function_exists('wp_get_all_sessions')){
    function wp_get_all_sessions() { $u=get_current_user_id();return $u?array_values(rrw_wpx_sess_load($u)):[]; }
}
if(!function_exists('wp_destroy_current_session')){
    function wp_destroy_current_session() { $u=get_current_user_id();$t=wp_get_session_token();if(!$u||$t==='')return;$s=rrw_wpx_sess_load($u);unset($s[hash('sha256',$t)]);rrw_wpx_sess_save($u,$s); }
}
if(!function_exists('wp_destroy_other_sessions')){
    function wp_destroy_other_sessions() { $u=get_current_user_id();$t=wp_get_session_token();if(!$u||$t==='')return;$k=hash('sha256',$t);$s=rrw_wpx_sess_load($u);rrw_wpx_sess_save($u,isset($s[$k])?[$k=>$s[$k]]:[]); }
}
if(!function_exists('wp_destroy_all_sessions')){
    function wp_destroy_all_sessions() { $u=get_current_user_id();if($u)delete_user_meta($u,'session_tokens'); }
}

/* ───────── Passwort zurücksetzen, Registrierung, Benachrichtigungen ───────── */
/** Zurücksetzen-Schlüssel erzeugen und gehasht speichern („Zeit:Hash“ in user_activation_key). Nur für Benutzer der Tabelle wp_users. */
function rrw_wpx_reset_key(WP_User $user) {
    global $wpdb;
    if(!rrw_wp_db_ready()||$user->ID<RRW_WP_ID_DB_MIN)return new WP_Error('no_reset','Passwort-Zurücksetzen läuft über das CMS.');
    $key=wp_generate_password(20,false);do_action('retrieve_password_key',$user->user_login,$key);
    $wpdb->update($wpdb->users,['user_activation_key'=>time().':'.rrw_wpx_fast_hash($key)],['ID'=>$user->ID]);rrw_wp_users_all(true);
    return $key;
}
/** Kurzform: Mail über wp_mail mit entschlüsseltem Betreff. */
function rrw_wpx_mail(array $m): bool { return !empty($m['to'])&&(bool)wp_mail($m['to'],wp_specialchars_decode((string)($m['subject']??'')),(string)($m['message']??''),$m['headers']??''); }
function rrw_wpx_site_name(): string { return wp_specialchars_decode((string)get_option('blogname',''),ENT_QUOTES); }

if(!function_exists('check_password_reset_key')){
    function check_password_reset_key($key, $login) {
        $bad=new WP_Error('invalid_key','Ungültiger Schlüssel.');
        $key=preg_replace('/[^a-z0-9]/i','',(string)$key);
        if($key===''||!is_string($login)||$login==='')return $bad;
        $user=get_user_by('login',$login);if(!$user)return $bad;
        $act=(string)$user->user_activation_key;if($act===''||!str_contains($act,':'))return $bad;
        [$time,$hash]=explode(':',$act,2);
        if(!rrw_wpx_fast_verify($key,$hash))return $bad;
        if(time()>(int)$time+(int)apply_filters('password_reset_expiration',DAY_IN_SECONDS))return new WP_Error('expired_key','Ungültiger Schlüssel.');
        return apply_filters('check_password_reset_key',$user,$key);
    }
}
if(!function_exists('reset_password')){
    function reset_password($user, $new_pass) {
        do_action('password_reset',$user,$new_pass);
        wp_set_password($new_pass,$user->ID);update_user_meta($user->ID,'default_password_nag',false);
        do_action('after_password_reset',$user,$new_pass);
    }
}
if(!function_exists('retrieve_password')){
    function retrieve_password($user_login=null) {
        $errors=new WP_Error();$user=false;
        if($user_login===null)$user_login=$_POST['user_login']??'';
        $user_login=trim(wp_unslash((string)$user_login));
        if($user_login==='')$errors->add('empty_username','<strong>Fehler:</strong> Bitte gib einen Benutzernamen oder eine E-Mail-Adresse ein.');
        elseif(str_contains($user_login,'@')){ $user=get_user_by('email',$user_login);if(!$user)$errors->add('invalid_email','<strong>Fehler:</strong> Es ist kein Konto mit dieser E-Mail-Adresse registriert.'); }
        else{ $user=get_user_by('login',$user_login);if(!$user)$errors->add('invalidcombo','<strong>Fehler:</strong> Es gibt kein Konto mit diesem Benutzernamen oder dieser E-Mail-Adresse.'); }
        do_action('lostpassword_post',$errors,$user);
        $errors=apply_filters('lostpassword_errors',$errors,$user);
        if($errors->has_errors())return $errors;
        if(!$user)return new WP_Error('invalidcombo','<strong>Fehler:</strong> Es gibt kein Konto mit diesem Benutzernamen oder dieser E-Mail-Adresse.');
        $login=$user->user_login;do_action('retrieve_password',$login);
        $allow=wp_is_password_reset_allowed_for_user($user);
        if(is_wp_error($allow))return $allow;
        if(!$allow)return new WP_Error('no_password_reset','Für diesen Benutzer ist das Zurücksetzen des Passworts nicht erlaubt.');
        $key=rrw_wpx_reset_key($user);
        if(is_wp_error($key))return $key;
        $site=rrw_wpx_site_name();
        $msg="Jemand hat das Zurücksetzen des Passworts für das folgende Konto angefordert:\n\nWebsite: ".network_home_url('/')."\nBenutzername: $login\n\nFalls das ein Irrtum war, ignoriere diese E-Mail einfach – es passiert nichts.\n\nZum Zurücksetzen des Passworts öffne diese Adresse:\n\n".network_site_url('wp-login.php?login='.rawurlencode($login).'&key='.$key.'&action=rp','login')."\n";
        $title=apply_filters('retrieve_password_title','['.$site.'] Passwort zurücksetzen',$login,$user);
        $msg=apply_filters('retrieve_password_message',$msg,$key,$login,$user);
        $mail=apply_filters('retrieve_password_notification_email',['to'=>$user->user_email,'subject'=>$title,'message'=>$msg,'headers'=>''],$key,$login,$user);
        if(!is_array($mail)||!rrw_wpx_mail($mail))return new WP_Error('retrieve_password_email_failure','<strong>Fehler:</strong> Die E-Mail konnte nicht gesendet werden.');
        return true;
    }
}
if(!function_exists('register_new_user')){
    function register_new_user($user_login, $user_email) {
        $errors=new WP_Error();$san=sanitize_user((string)$user_login);
        $email=apply_filters('user_registration_email',$user_email);
        if($san==='')$errors->add('empty_username','<strong>Fehler:</strong> Bitte gib einen Benutzernamen ein.');
        elseif(!validate_username($user_login)){ $errors->add('invalid_username','<strong>Fehler:</strong> Dieser Benutzername ist ungültig, weil er unzulässige Zeichen enthält.');$san=''; }
        elseif(username_exists($san))$errors->add('username_exists','<strong>Fehler:</strong> Dieser Benutzername ist bereits registriert.');
        else{ $ill=array_map('strtolower',(array)apply_filters('illegal_user_logins',[]));if(in_array(strtolower($san),$ill,true))$errors->add('invalid_username','<strong>Fehler:</strong> Dieser Benutzername ist nicht erlaubt.'); }
        if((string)$email==='')$errors->add('empty_email','<strong>Fehler:</strong> Bitte gib deine E-Mail-Adresse ein.');
        elseif(!is_email($email)){ $errors->add('invalid_email','<strong>Fehler:</strong> Die E-Mail-Adresse ist ungültig.');$email=''; }
        elseif(email_exists($email))$errors->add('email_exists','<strong>Fehler:</strong> Diese E-Mail-Adresse ist bereits registriert.');
        do_action('register_post',$san,$email,$errors);
        $errors=apply_filters('registration_errors',$errors,$san,$email);
        if($errors->has_errors())return $errors;
        $id=wp_create_user($san,wp_generate_password(12,false),$email);
        if(!$id||is_wp_error($id))return new WP_Error('registerfail','<strong>Fehler:</strong> Die Registrierung konnte nicht abgeschlossen werden.');
        update_user_meta($id,'default_password_nag',true);
        wp_new_user_notification($id,null,'both');
        return $id;
    }
}
if(!function_exists('wp_send_new_user_notifications')){
    function wp_send_new_user_notifications($user_id, $notify='both') { wp_new_user_notification($user_id,null,$notify); }
}
if(!function_exists('wp_new_user_notification')){
    function wp_new_user_notification($user_id, $deprecated=null, $notify='') {
        $user=get_userdata((int)$user_id);if(!$user)return;
        $site=rrw_wpx_site_name();
        if($notify!=='user'){
            $m=apply_filters('wp_new_user_notification_email_admin',['to'=>get_option('admin_email'),'subject'=>'['.$site.'] Neue Benutzerregistrierung','message'=>"Neuer Benutzer auf $site:\n\nBenutzername: {$user->user_login}\n\nE-Mail: {$user->user_email}\n",'headers'=>''],$user,$site);
            if(is_array($m))rrw_wpx_mail($m);
        }
        if($notify==='admin'||($deprecated===null&&$notify===''))return;
        $key=rrw_wpx_reset_key($user);if(is_wp_error($key))return;
        $m=apply_filters('wp_new_user_notification_email',['to'=>$user->user_email,'subject'=>'['.$site.'] Dein Benutzername und Passwort','message'=>"Benutzername: {$user->user_login}\n\nUm dein Passwort festzulegen, öffne diese Adresse:\n\n".network_site_url('wp-login.php?action=rp&key='.$key.'&login='.rawurlencode($user->user_login),'login')."\n\n".wp_login_url()."\n",'headers'=>''],$user,$site);
        if(is_array($m))rrw_wpx_mail($m);
    }
}
if(!function_exists('wp_password_change_notification')){
    function wp_password_change_notification($user) {
        if(strcasecmp((string)$user->user_email,(string)get_option('admin_email'))===0)return;
        $site=rrw_wpx_site_name();
        $m=apply_filters('wp_password_change_notification_email',['to'=>get_option('admin_email'),'subject'=>'['.$site.'] Passwort geändert','message'=>"Das Passwort des Benutzers {$user->user_login} wurde geändert.\n",'headers'=>''],$user,$site);
        if(is_array($m))rrw_wpx_mail($m);
    }
}
if(!function_exists('send_confirmation_on_profile_email')){
    /** Profil: Bei geänderter E-Mail-Adresse zuerst eine Bestätigung senden und die alte Adresse behalten. */
    function send_confirmation_on_profile_email() {
        global $errors;$cur=wp_get_current_user();
        if(!is_wp_error($errors))$errors=new WP_Error();
        if((int)($_POST['user_id']??0)!==$cur->ID)return false;
        $new=(string)($_POST['email']??'');if($new===''||$cur->user_email===$new)return;
        if(!is_email($new)){ $errors->add('user_email','<strong>Fehler:</strong> Die E-Mail-Adresse ist ungültig.',['form-field'=>'email']);unset($_POST['email']);return; }
        $other=email_exists($new);
        if($other&&(int)$other!==$cur->ID){ $errors->add('user_email','<strong>Fehler:</strong> Diese E-Mail-Adresse wird bereits verwendet.',['form-field'=>'email']);unset($_POST['email']);return; }
        $hash=md5($new.time().wp_rand());update_user_meta($cur->ID,'_new_email',['hash'=>$hash,'newemail'=>$new]);
        $site=rrw_wpx_site_name();
        $m=apply_filters('new_user_email_content',"Hallo ###USERNAME###,\n\nDu hast deine E-Mail-Adresse geändert. Zur Bestätigung öffne:\n###ADMIN_URL###\n\nDie E-Mail-Adresse bleibt bis dahin unverändert.\n\n###SITENAME###\n###SITEURL###",['hash'=>$hash,'newemail'=>$new]);
        $m=str_replace(['###USERNAME###','###ADMIN_URL###','###EMAIL###','###SITENAME###','###SITEURL###'],[$cur->user_login,esc_url(admin_url('profile.php?newuseremail='.$hash)),$new,$site,home_url()],$m);
        wp_mail($new,'['.$site.'] E-Mail-Adresse bestätigen',$m);
        $_POST['email']=$cur->user_email;
    }
}
if(!function_exists('new_user_email_admin_notice')){
    function new_user_email_admin_notice() {
        if(str_contains((string)($_SERVER['PHP_SELF']??''),'profile.php')&&isset($_GET['updated'])&&($e=get_user_meta(get_current_user_id(),'_new_email',true))&&!empty($e['newemail']))
            echo '<div class="notice notice-info"><p>Du hast eine Änderung deiner E-Mail-Adresse auf <code>'.esc_html($e['newemail']).'</code> angefordert. Sie wird erst nach der Bestätigung wirksam.</p></div>';
    }
}
if(!function_exists('wp_text_diff')){
    /** Zeilenweiser Textvergleich als HTML-Tabelle (Klassen wie bei WordPress); leer, wenn beide Texte gleich sind. */
    function wp_text_diff($left_string, $right_string, $args=null) {
        $a=wp_parse_args($args,['title'=>'','title_left'=>'','title_right'=>'','show_split_view'=>true]);
        $l=explode("\n",str_replace("\r\n","\n",(string)$left_string));$r=explode("\n",str_replace("\r\n","\n",(string)$right_string));
        if($l===$r)return '';
        $n=count($l);$m=count($r);$t=array_fill(0,$n+1,array_fill(0,$m+1,0));
        for($i=$n-1;$i>=0;$i--)for($j=$m-1;$j>=0;$j--)$t[$i][$j]=$l[$i]===$r[$j]?$t[$i+1][$j+1]+1:max($t[$i+1][$j],$t[$i][$j+1]);
        $rows='';$i=$j=0;
        $cell=fn($cls,$sign,$txt)=>'<td class="'.$cls.'">'.($sign!==''?'<span class="diff-sign">'.$sign.'</span>':'').esc_html($txt).'</td>';
        while($i<$n||$j<$m){
            if($i<$n&&$j<$m&&$l[$i]===$r[$j]){ $rows.='<tr>'.$cell('diff-context','',$l[$i]).$cell('diff-context','',$r[$j]).'</tr>';$i++;$j++; }
            elseif($j<$m&&($i>=$n||$t[$i][$j+1]>=$t[$i+1][$j])){ $rows.='<tr><td class="diff-deletedline"></td>'.$cell('diff-addedline','+',$r[$j]).'</tr>';$j++; }
            else{ $rows.='<tr>'.$cell('diff-deletedline','-',$l[$i]).'<td class="diff-addedline"></td></tr>';$i++; }
        }
        $head=$a['title']!==''?'<caption class="diff-title">'.esc_html($a['title']).'</caption>':'';
        if($a['title_left']!==''||$a['title_right']!=='')$head.='<thead><tr class="diff-sub-title"><th>'.esc_html($a['title_left']).'</th><th>'.esc_html($a['title_right']).'</th></tr></thead>';
        return '<table class="diff"><colgroup><col class="content diffsplit left" /><col class="content diffsplit middle" /></colgroup>'.$head.'<tbody>'.$rows.'</tbody></table>';
    }
}

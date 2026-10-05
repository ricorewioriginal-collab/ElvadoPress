<?php
// Ergänzende WordPress-Funktionen (Kern 2, Teil 4): pausierte Erweiterungen, Fehlerbehandlung bei schweren Fehlern, Wiederherstellungsmodus.
// Eigenständig umgesetzt; alles wirkt nur beim Aufruf (keine Handler beim Laden).

/* ───────── Pausierte Erweiterungen ───────── */
if(!class_exists('WP_Paused_Extensions_Storage')){
class WP_Paused_Extensions_Storage {
    protected $type;
    public function __construct($extension_type) { $this->type=(string)$extension_type; }
    protected function get_option_name() { return 'wp_paused_extensions'; }
    protected function id($extension) { return $this->type==='plugin'&&str_contains((string)$extension,'/')?dirname((string)$extension):(string)$extension; }   // Plugins: Ordnername, sonst Dateiname
    public function set($extension, $error) {
        if(!$this->is_api_loaded())return false;
        $o=get_option($this->get_option_name(),[]);$o=is_array($o)?$o:[];$id=$this->id($extension);
        if(isset($o[$this->type][$id])&&$o[$this->type][$id]==$error)return true;
        $o[$this->type][$id]=$error;return (bool)update_option($this->get_option_name(),$o)||get_option($this->get_option_name())==$o;
    }
    public function delete($extension) {
        if(!$this->is_api_loaded())return false;
        $o=get_option($this->get_option_name(),[]);$id=$this->id($extension);
        if(!is_array($o)||!isset($o[$this->type][$id]))return true;
        unset($o[$this->type][$id]);if(empty($o[$this->type]))unset($o[$this->type]);
        return empty($o)?delete_option($this->get_option_name()):(bool)update_option($this->get_option_name(),$o);
    }
    public function get($extension) {
        if(!$this->is_api_loaded())return null;
        $o=get_option($this->get_option_name(),[]);return is_array($o)?($o[$this->type][$this->id($extension)]??null):null;
    }
    public function get_all() {
        if(!$this->is_api_loaded())return [];
        $o=get_option($this->get_option_name(),[]);return is_array($o)?($o[$this->type]??[]):[];
    }
    public function delete_all() {
        if(!$this->is_api_loaded())return false;
        $o=get_option($this->get_option_name(),[]);if(!is_array($o)||!isset($o[$this->type]))return true;
        unset($o[$this->type]);return empty($o)?delete_option($this->get_option_name()):(bool)update_option($this->get_option_name(),$o);
    }
    protected function is_api_loaded() { return function_exists('get_option'); }
}
}
if(!function_exists('wp_paused_plugins')){ function wp_paused_plugins() { static $s=null;return $s??($s=new WP_Paused_Extensions_Storage('plugin')); } }
if(!function_exists('wp_paused_themes')){ function wp_paused_themes() { static $s=null;return $s??($s=new WP_Paused_Extensions_Storage('theme')); } }
if(!function_exists('wp_get_extension_error_description')){ function wp_get_extension_error_description($error) {
    $c=get_defined_constants(true)['Core']??[];$types=array_flip(array_filter($c,fn($k)=>str_starts_with($k,'E_'),ARRAY_FILTER_USE_KEY));
    $constants=[E_ERROR=>'E_ERROR',E_PARSE=>'E_PARSE',E_USER_ERROR=>'E_USER_ERROR',E_COMPILE_ERROR=>'E_COMPILE_ERROR',E_RECOVERABLE_ERROR=>'E_RECOVERABLE_ERROR'];
    $type=$constants[(int)($error['type']??0)]??($types[(int)($error['type']??0)]??'E_ERROR');
    return sprintf('Ein Fehler vom Typ %1$s trat in Zeile %2$s der Datei %3$s auf. Fehlermeldung: %4$s',$type,$error['line']??0,$error['file']??'',$error['message']??'');
} }

/* ───────── Behandlung schwerer Fehler ───────── */
if(!function_exists('wp_is_fatal_error_handler_enabled')){ function wp_is_fatal_error_handler_enabled() {
    $e=!defined('WP_DISABLE_FATAL_ERROR_HANDLER')||!WP_DISABLE_FATAL_ERROR_HANDLER;
    return (bool)apply_filters('wp_fatal_error_handler_enabled',$e);
} }
if(!class_exists('WP_Fatal_Error_Handler')){
class WP_Fatal_Error_Handler {
    public function handle() {
        if(defined('WP_SANDBOX_SCRAPING')&&WP_SANDBOX_SCRAPING)return;
        try{
            $error=$this->detect_error();if(!$error)return;
            $handled=false;
            if(function_exists('wp_recovery_mode')&&wp_recovery_mode()->is_initialized())$handled=wp_recovery_mode()->handle_error($error);
            $this->display_default_error_template($error,$handled);
        }catch(RRW_WP_Die $d){ throw $d; }
        catch(Exception $e){ /* Fehlerbehandlung darf selbst nie fatal enden */ }
    }
    protected function detect_error() {
        $e=error_get_last();
        if($e===null||!in_array($e['type'],[E_ERROR,E_PARSE,E_USER_ERROR,E_COMPILE_ERROR,E_RECOVERABLE_ERROR],true))return null;
        return $e;
    }
    protected function display_default_error_template($error, $handled) {
        $msg=$handled?'Auf dieser Website ist ein kritischer Fehler aufgetreten. Die Administration wurde per E-Mail informiert.':'Auf dieser Website ist ein kritischer Fehler aufgetreten.';
        $msg=apply_filters('wp_php_error_message',$msg,$error);
        $args=apply_filters('wp_php_error_args',['response'=>500,'exit'=>false,'link_url'=>'','link_text'=>''],$error);
        if(!is_array($args))$args=['response'=>500,'exit'=>false];
        wp_die('<p>'.$msg.'</p>','Kritischer Fehler',$args);
    }
}
}
if(!function_exists('wp_register_fatal_error_handler')){ function wp_register_fatal_error_handler() {
    static $done=false;
    if($done||!wp_is_fatal_error_handler_enabled())return;
    $h=new WP_Fatal_Error_Handler();$h=apply_filters('wp_fatal_error_handler_instance',$h);
    if(!is_object($h)||!method_exists($h,'handle'))return;
    $done=true;register_shutdown_function([$h,'handle']);
} }

/* ───────── Wiederherstellungsmodus ───────── */
if(!class_exists('WP_Recovery_Mode_Cookie_Service')){
class WP_Recovery_Mode_Cookie_Service {
    public function is_cookie_set() { return !empty($_COOKIE[defined('RECOVERY_MODE_COOKIE')?RECOVERY_MODE_COOKIE:'wordpress_rec']); }
    public function set_cookie() {
        $v=$this->generate_cookie();$ttl=(int)apply_filters('recovery_mode_cookie_length',WEEK_IN_SECONDS);$exp=time()+$ttl;
        if(!headers_sent())setcookie(defined('RECOVERY_MODE_COOKIE')?RECOVERY_MODE_COOKIE:'wordpress_rec',$v,['expires'=>$exp,'path'=>defined('COOKIEPATH')?COOKIEPATH:'/','secure'=>is_ssl(),'httponly'=>true,'samesite'=>'Lax']);
        return $v;
    }
    public function clear_cookie() {
        $n=defined('RECOVERY_MODE_COOKIE')?RECOVERY_MODE_COOKIE:'wordpress_rec';
        if(!headers_sent())setcookie($n,' ',time()-YEAR_IN_SECONDS,defined('COOKIEPATH')?COOKIEPATH:'/');
        unset($_COOKIE[$n]);
    }
    public function validate_cookie($cookie='') {
        if(!$cookie){ $n=defined('RECOVERY_MODE_COOKIE')?RECOVERY_MODE_COOKIE:'wordpress_rec';if(empty($_COOKIE[$n]))return new WP_Error('no_cookie','Es ist kein Cookie vorhanden.');$cookie=$_COOKIE[$n]; }
        $p=$this->parse_cookie($cookie);if(is_wp_error($p))return $p;
        [$a,$created,$random,$sig]=$p;
        if(!hash_equals($this->recovery_mode_hash($a.'|'.$created.'|'.$random),$sig))return new WP_Error('invalid_signature','Die Signatur des Cookies ist ungültig.');
        $ttl=(int)apply_filters('recovery_mode_cookie_length',WEEK_IN_SECONDS);
        if(time()>(int)$created+$ttl)return new WP_Error('expired','Das Cookie ist abgelaufen.');
        return true;
    }
    public function get_session_id_from_cookie($cookie='') {
        if(!$cookie){ $n=defined('RECOVERY_MODE_COOKIE')?RECOVERY_MODE_COOKIE:'wordpress_rec';if(empty($_COOKIE[$n]))return new WP_Error('no_cookie','Es ist kein Cookie vorhanden.');$cookie=$_COOKIE[$n]; }
        $p=$this->parse_cookie($cookie);return is_wp_error($p)?$p:$p[2];
    }
    private function parse_cookie($cookie) {
        $d=base64_decode(strtr((string)$cookie,'-_','+/'),true);$x=$d===false?[]:explode('|',$d);
        return count($x)===4?$x:new WP_Error('invalid_format','Das Cookie hat ein ungültiges Format.');
    }
    private function generate_cookie() {
        $a='recovery_mode';$c=time();$r=wp_generate_password(20,false);
        $s=$this->recovery_mode_hash($a.'|'.$c.'|'.$r);return rtrim(strtr(base64_encode($a.'|'.$c.'|'.$r.'|'.$s),'+/','-_'),'=');
    }
    private function recovery_mode_hash($data) { return hash_hmac('sha1',$data,wp_hash($data,'secure_auth')); }
}
}
if(!class_exists('WP_Recovery_Mode_Key_Service')){
class WP_Recovery_Mode_Key_Service {
    public function generate_recovery_mode_token() { return wp_generate_password(22,false); }
    public function generate_and_store_recovery_mode_key($token) {
        $key=wp_generate_password(22,false);$keys=get_option('recovery_keys',[]);$keys=is_array($keys)?$keys:[];
        $keys[$token]=['hashed_key'=>wp_hash_password($key),'created_at'=>time()];update_option('recovery_keys',$keys);
        return $key;
    }
    public function validate_recovery_mode_key($token, $key, $ttl) {
        $keys=get_option('recovery_keys',[]);
        if(!is_array($keys)||!isset($keys[$token]))return new WP_Error('token_not_found','Der Wiederherstellungsschlüssel wurde nicht gefunden.');
        $r=$keys[$token];unset($keys[$token]);update_option('recovery_keys',$keys);
        if(empty($r['hashed_key'])||empty($r['created_at']))return new WP_Error('invalid_recovery_key_format','Der Wiederherstellungsschlüssel hat ein ungültiges Format.');
        if(!wp_check_password($key,$r['hashed_key']))return new WP_Error('hash_mismatch','Der Wiederherstellungsschlüssel ist ungültig.');
        if(time()>(int)$r['created_at']+(int)$ttl)return new WP_Error('key_expired','Der Wiederherstellungsschlüssel ist abgelaufen.');
        return true;
    }
    public function clean_expired_keys($ttl) {
        $keys=get_option('recovery_keys',[]);if(!is_array($keys))return;
        foreach($keys as $t=>$k)if(!is_array($k)||empty($k['created_at'])||time()>(int)$k['created_at']+(int)$ttl)unset($keys[$t]);
        update_option('recovery_keys',$keys);
    }
}
}
if(!class_exists('WP_Recovery_Mode_Link_Service')){
class WP_Recovery_Mode_Link_Service {
    const LOGIN_ACTION_ENTER='enter_recovery_mode';const LOGIN_ACTION_ENTERED='entered_recovery_mode';
    private $key_service;private $cookie_service;
    public function __construct($cookie_service, $key_service) { $this->cookie_service=$cookie_service;$this->key_service=$key_service; }
    public function generate_url() {
        $t=$this->key_service->generate_recovery_mode_token();$k=$this->key_service->generate_and_store_recovery_mode_key($t);
        return $this->get_recovery_mode_begin_url($t,$k);
    }
    public function handle_begin_link($ttl) {
        if(!isset($GLOBALS['pagenow'])||'wp-login.php'!==$GLOBALS['pagenow']||!isset($_GET['action'],$_GET['rm_token'],$_GET['rm_key'])||self::LOGIN_ACTION_ENTER!==$_GET['action'])return;
        if(!function_exists('wp_generate_password'))return;
        $v=$this->key_service->validate_recovery_mode_key((string)$_GET['rm_token'],(string)$_GET['rm_key'],$ttl);
        if(is_wp_error($v))wp_die($v,'',['response'=>403]);
        $this->cookie_service->set_cookie();
        $url=add_query_arg('action',self::LOGIN_ACTION_ENTERED,wp_login_url());
        wp_redirect($url);
        if(!defined('RRW_WP_TESTING'))exit;
    }
    private function get_recovery_mode_begin_url($token, $key) {
        $url=add_query_arg(['action'=>self::LOGIN_ACTION_ENTER,'rm_token'=>$token,'rm_key'=>$key],wp_login_url());
        return apply_filters('recovery_mode_begin_url',$url,$token,$key);
    }
}
}
if(!class_exists('WP_Recovery_Mode_Email_Service')){
class WP_Recovery_Mode_Email_Service {
    const RATE_LIMIT_OPTION='recovery_mode_email_last_sent';
    private $link_service;
    public function __construct($link_service) { $this->link_service=$link_service; }
    public function maybe_send_recovery_mode_email($rate_limit, $error, $extension) {
        $last=get_option(self::RATE_LIMIT_OPTION);
        if(!$last||time()>(int)$last+(int)$rate_limit){ if(!update_option(self::RATE_LIMIT_OPTION,time()))return new WP_Error('storage_error','Die Zeit des letzten Versands konnte nicht gespeichert werden.');
            return $this->send_recovery_mode_email($rate_limit,$error,$extension); }
        $n=human_time_diff((int)$last+(int)$rate_limit);
        return new WP_Error('email_sent_already',sprintf('Die E-Mail zum Wiederherstellungsmodus wurde bereits versendet. Nächster Versand frühestens in %s.',$n));
    }
    private function send_recovery_mode_email($rate_limit, $error, $extension) {
        $url=$this->link_service->generate_url();$to=apply_filters('recovery_mode_email_to',get_option('admin_email'));
        $name=(string)get_option('blogname');
        $subj=apply_filters('recovery_mode_email_subject',sprintf('[%s] Kritischer Fehler auf Ihrer Website',$name));
        $body="Hallo,\n\nauf Ihrer Website ist ein kritischer Fehler aufgetreten.\n\n";
        if($extension)$body.=sprintf("Ausgelöst durch: %s (%s)\n",$extension['slug']??'',$extension['type']??'')."\n";
        $body.="Wiederherstellungsmodus starten:\n$url\n\nFehlerdetails:\n".wp_get_extension_error_description((array)$error)."\n";
        $email=apply_filters('recovery_mode_email',['to'=>$to,'subject'=>$subj,'message'=>$body,'headers'=>'','attachments'=>''],$url);
        $ok=wp_mail($email['to'],wp_specialchars_decode($email['subject']),$email['message'],$email['headers'],$email['attachments']);
        if($ok)update_option(self::RATE_LIMIT_OPTION,time());
        return $ok?true:new WP_Error('mail_failed','Die E-Mail konnte nicht gesendet werden.');
    }
}
}
if(!class_exists('WP_Recovery_Mode')){
class WP_Recovery_Mode {
    const EXIT_ACTION='exit_recovery_mode';
    private $cookie_service;private $key_service;private $link_service;private $email_service;private $is_initialized=false;private $is_active=false;private $session_id='';
    public function __construct() {
        $this->cookie_service=new WP_Recovery_Mode_Cookie_Service();$this->key_service=new WP_Recovery_Mode_Key_Service();
        $this->link_service=new WP_Recovery_Mode_Link_Service($this->cookie_service,$this->key_service);$this->email_service=new WP_Recovery_Mode_Email_Service($this->link_service);
    }
    public function initialize() {
        $this->is_initialized=true;
        $rate=(int)apply_filters('recovery_mode_email_rate_limit',DAY_IN_SECONDS);$ttl=(int)apply_filters('recovery_mode_key_ttl',DAY_IN_SECONDS);
        $this->key_service->clean_expired_keys($ttl);
        if($this->cookie_service->is_cookie_set()){ $this->handle_cookie();return; }
        $this->link_service->handle_begin_link($ttl);
    }
    public function is_active() { return $this->is_active; }
    public function is_initialized() { return $this->is_initialized; }
    public function get_session_id() { return $this->session_id; }
    public function handle_error($error) {
        $ext=$this->get_extension_for_error($error);
        if(!$ext||$this->is_network_plugin($ext))return false;
        $this->store_error($error);
        if($ext['type']==='plugin'&&wp_paused_plugins()->set($ext['slug'],$error)===false)return false;
        if($ext['type']==='theme'&&wp_paused_themes()->set($ext['slug'],$error)===false)return false;
        if(!$this->is_active()){ $r=$this->email_service->maybe_send_recovery_mode_email((int)apply_filters('recovery_mode_email_rate_limit',DAY_IN_SECONDS),$error,$ext);return !is_wp_error($r); }
        return true;
    }
    public function exit_recovery_mode() {
        if(!$this->is_active())return false;
        $this->delete_session();
        wp_paused_plugins()->delete_all();wp_paused_themes()->delete_all();
        $this->cookie_service->clear_cookie();$this->is_active=false;$this->session_id='';
        return true;
    }
    public function handle_exit_recovery_mode() {
        $redirect=wp_get_referer()?:home_url('/');
        if(!$this->is_active())wp_safe_redirect($redirect);
        else{ if(!empty($_GET['action'])&&self::EXIT_ACTION===$_GET['action']&&wp_verify_nonce($_GET['_wpnonce']??'',self::EXIT_ACTION))$this->exit_recovery_mode();wp_safe_redirect($redirect); }
        if(!defined('RRW_WP_TESTING'))exit;
    }
    public function clean_expired_keys() { $this->key_service->clean_expired_keys((int)apply_filters('recovery_mode_key_ttl',DAY_IN_SECONDS)); }
    protected function is_network_plugin($extension) {
        if($extension['type']!=='plugin')return false;
        return false;   // Einzelseite: es gibt keine netzwerkweit aktiven Plugins
    }
    protected function get_extension_for_error($error) {
        $f=wp_normalize_path((string)($error['file']??''));if($f==='')return false;
        foreach([['plugin',wp_normalize_path(WP_PLUGIN_DIR)],['plugin',wp_normalize_path(WPMU_PLUGIN_DIR)],['theme',wp_normalize_path(WP_CONTENT_DIR.'/themes')]] as [$type,$dir]){
            $dir=trailingslashit($dir);
            if(str_starts_with($f,$dir)){ $rel=substr($f,strlen($dir));$slug=explode('/',$rel)[0];
                if($type==='plugin'&&!str_contains($rel,'/'))return ['slug'=>$rel,'type'=>'plugin'];
                return ['slug'=>$slug,'type'=>$type]; }
        }
        return false;
    }
    private function store_error($error) {
        $o=get_option('recovery_mode_last_error',[]);update_option('recovery_mode_last_error',array_slice(array_merge((array)$o,[$error]),-5));
    }
    private function handle_cookie() {
        $v=$this->cookie_service->validate_cookie();
        if(is_wp_error($v)){ $this->cookie_service->clear_cookie();return; }
        $sid=$this->cookie_service->get_session_id_from_cookie();
        if(is_wp_error($sid)){ $this->cookie_service->clear_cookie();return; }
        $this->is_active=true;$this->session_id=$sid;
    }
    private function delete_session() { delete_option('recovery_mode_last_error'); }
}
}
if(!function_exists('wp_recovery_mode')){ function wp_recovery_mode() { static $r=null;return $r??($r=new WP_Recovery_Mode()); } }

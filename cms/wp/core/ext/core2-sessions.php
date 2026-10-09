<?php
// Ergänzende WordPress-Funktionen (Kern 2, Teil 9): Sitzungs-Token, Anwendungspasswörter, PasswordHash (phpass), Übersetzungsdateien (MO/PHP), Sprachwechsel, Diff-Darstellung.
// Eigenständig umgesetzt; Sitzungen und Anwendungspasswörter nutzen dasselbe Benutzer-Meta wie die Anmeldefunktionen der Schicht.

/* ───────── Sitzungs-Token ───────── */
if(!class_exists('WP_Session_Tokens')){
abstract class WP_Session_Tokens {
    protected $user_id;
    protected function __construct($user_id) { $this->user_id=$user_id; }
    final public static function get_instance($user_id) {
        $m=apply_filters('session_token_manager','WP_User_Meta_Session_Tokens');
        return new $m($user_id);
    }
    private function hash_token($token) { return hash('sha256',(string)$token); }
    final public function get($token) { return $this->get_session($this->hash_token($token)); }
    final public function verify($token) { return (bool)$this->get_session($this->hash_token($token)); }
    final public function create($expiration) {
        $s=apply_filters('attach_session_information',[],$this->user_id);$s['expiration']=$expiration;
        if(!empty($_SERVER['REMOTE_ADDR']))$s['ip']=$_SERVER['REMOTE_ADDR'];
        if(!empty($_SERVER['HTTP_USER_AGENT']))$s['ua']=wp_unslash($_SERVER['HTTP_USER_AGENT']);
        $s['login']=time();$token=wp_generate_password(43,false,false);
        $this->update($token,$s);return $token;
    }
    final public function update($token, $session) { $this->update_session($this->hash_token($token),$session); }
    final public function destroy($token) { $this->update_session($this->hash_token($token),null); }
    final public function destroy_others($token_to_keep) { $this->destroy_other_sessions($this->hash_token($token_to_keep)); }
    final protected function is_still_valid($session) { return $session['expiration']>=time(); }
    final public function destroy_all() { $this->destroy_all_sessions(); }
    final public static function destroy_all_for_all_users() { $m=apply_filters('session_token_manager','WP_User_Meta_Session_Tokens');call_user_func([$m,'drop_sessions']); }
    final public function get_all() { return array_values($this->get_sessions()); }
    abstract protected function get_sessions();
    abstract protected function get_session($verifier);
    abstract protected function update_session($verifier, $session=null);
    abstract protected function destroy_other_sessions($verifier);
    abstract protected function destroy_all_sessions();
    public static function drop_sessions() {}
}
}
if(!class_exists('WP_User_Meta_Session_Tokens')){
class WP_User_Meta_Session_Tokens extends WP_Session_Tokens {
    protected function get_sessions() {
        $s=get_user_meta($this->user_id,'session_tokens',true);if(!is_array($s))return [];
        return array_filter(array_map([$this,'prepare_session'],$s),[$this,'is_still_valid']);
    }
    protected function prepare_session($session) { return is_int($session)?['expiration'=>$session]:$session; }
    protected function get_session($verifier) { return $this->get_sessions()[$verifier]??null; }
    protected function update_session($verifier, $session=null) {
        $s=$this->get_sessions();if($session)$s[$verifier]=$session;else unset($s[$verifier]);
        $this->update_sessions($s);
    }
    protected function update_sessions($sessions) { if($sessions)update_user_meta($this->user_id,'session_tokens',$sessions);else delete_user_meta($this->user_id,'session_tokens'); }
    protected function destroy_other_sessions($verifier) { $s=$this->get_session($verifier);$this->update_sessions($s?[$verifier=>$s]:[]); }
    protected function destroy_all_sessions() { $this->update_sessions([]); }
    public static function drop_sessions() { global $wpdb;$wpdb->delete($wpdb->usermeta,['meta_key'=>'session_tokens']); }
}
}

/* ───────── Anwendungspasswörter ───────── */
if(!class_exists('WP_Application_Passwords')){
class WP_Application_Passwords {
    const USERMETA_KEY_APPLICATION_PASSWORDS='_application_passwords';const OPTION_KEY_IN_USE='using_application_passwords';const PW_LENGTH=24;
    public static function is_in_use() { return (bool)get_option(self::OPTION_KEY_IN_USE); }
    public static function create_new_application_password($user_id, $args=[]) {
        if(!empty($args['name']))$args['name']=sanitize_text_field($args['name']);
        if(empty($args['name']))return new WP_Error('application_password_empty_name','Ein neues Anwendungspasswort braucht einen Namen.',['status'=>400]);
        $pw=wp_generate_password(self::PW_LENGTH,false);
        $item=['uuid'=>wp_generate_uuid4(),'app_id'=>empty($args['app_id'])?'':$args['app_id'],'name'=>$args['name'],'password'=>self::hash_password($pw),'created'=>time(),'last_used'=>null,'last_ip'=>null];
        $item=apply_filters('wp_application_password_item',$item,$args,$user_id);
        $all=self::get_user_application_passwords($user_id);$all[]=$item;
        if(!self::set_user_application_passwords($user_id,$all))return new WP_Error('db_error','Das Anwendungspasswort konnte nicht gespeichert werden.');
        $item['uuid']=$item['uuid'];
        update_option(self::OPTION_KEY_IN_USE,true,'no');
        do_action('wp_create_application_password',$user_id,$item,$pw,$args);
        return [$pw,$item];
    }
    public static function get_user_application_passwords($user_id) {
        $p=get_user_meta($user_id,self::USERMETA_KEY_APPLICATION_PASSWORDS,true);if(!is_array($p))return [];
        $out=[];foreach(array_values($p) as $i=>$x){ if(!is_array($x))continue;
            $out[]=array_merge(['uuid'=>'','app_id'=>'','name'=>'','password'=>'','created'=>null,'last_used'=>null,'last_ip'=>null],$x); }
        return $out;
    }
    public static function get_user_application_password($user_id, $uuid) { foreach(self::get_user_application_passwords($user_id) as $p)if($p['uuid']===$uuid)return $p;return null; }
    public static function application_name_exists_for_user($user_id, $name) { foreach(self::get_user_application_passwords($user_id) as $p)if(strtolower($p['name'])===strtolower((string)$name))return true;return false; }
    public static function update_application_password($user_id, $uuid, $update=[]) {
        $all=self::get_user_application_passwords($user_id);
        foreach($all as $k=>$item){ if($item['uuid']!==$uuid)continue;
            foreach(['name','app_id'] as $f)if(isset($update[$f]))$all[$k][$f]=$f==='name'?sanitize_text_field($update[$f]):$update[$f];
            $saved=self::set_user_application_passwords($user_id,$all);
            if(!$saved)return new WP_Error('db_error','Das Anwendungspasswort konnte nicht aktualisiert werden.');
            do_action('wp_update_application_password',$user_id,$all[$k],$update);return true; }
        return new WP_Error('application_password_not_found','Es wurde kein Anwendungspasswort mit dieser UUID gefunden.',['status'=>404]);
    }
    public static function record_application_password_usage($user_id, $uuid) {
        $all=self::get_user_application_passwords($user_id);
        foreach($all as $k=>$item){ if($item['uuid']!==$uuid)continue;
            if($item['last_used']&&$item['last_used']+DAY_IN_SECONDS>time())return true;   // höchstens einmal täglich schreiben
            $all[$k]['last_used']=time();$all[$k]['last_ip']=$_SERVER['REMOTE_ADDR']??'';
            return (bool)self::set_user_application_passwords($user_id,$all); }
        return new WP_Error('application_password_not_found','Es wurde kein Anwendungspasswort mit dieser UUID gefunden.',['status'=>404]);
    }
    public static function delete_application_password($user_id, $uuid) {
        $all=self::get_user_application_passwords($user_id);
        foreach($all as $k=>$item){ if($item['uuid']!==$uuid)continue;
            unset($all[$k]);$saved=self::set_user_application_passwords($user_id,array_values($all));
            if(!$saved)return new WP_Error('db_error','Das Anwendungspasswort konnte nicht gelöscht werden.');
            do_action('wp_delete_application_password',$user_id,$item);return true; }
        return new WP_Error('application_password_not_found','Es wurde kein Anwendungspasswort mit dieser UUID gefunden.',['status'=>404]);
    }
    public static function delete_all_application_passwords($user_id) {
        $all=self::get_user_application_passwords($user_id);if(!$all)return 0;
        if(!delete_user_meta($user_id,self::USERMETA_KEY_APPLICATION_PASSWORDS))return new WP_Error('db_error','Die Anwendungspasswörter konnten nicht gelöscht werden.');
        do_action('wp_delete_all_application_passwords',$user_id);return count($all);
    }
    protected static function set_user_application_passwords($user_id, $passwords) { return update_user_meta($user_id,self::USERMETA_KEY_APPLICATION_PASSWORDS,$passwords)!==false; }
    public static function chunk_password($raw_password) { return trim(chunk_split(preg_replace('/[^a-z\d]/i','',(string)$raw_password),4,' ')); }
    public static function hash_password($password) { return wp_hash_password($password); }
}
}

/* ───────── PasswordHash (phpass) ───────── */
if(!class_exists('PasswordHash')){
class PasswordHash {
    public $itoa64='./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';public $iteration_count_log2;public $portable_hashes;public $random_state='';
    public function __construct($iteration_count_log2, $portable_hashes) {
        $iteration_count_log2=(int)$iteration_count_log2;if($iteration_count_log2<4||$iteration_count_log2>31)$iteration_count_log2=8;
        $this->iteration_count_log2=$iteration_count_log2;$this->portable_hashes=(bool)$portable_hashes;
    }
    public function PasswordHash($iteration_count_log2, $portable_hashes) { self::__construct($iteration_count_log2,$portable_hashes); }
    public function get_random_bytes($count) { return random_bytes(max(1,(int)$count)); }
    public function encode64($input, $count) {
        $o='';$i=0;
        do{ $v=ord($input[$i++]);$o.=$this->itoa64[$v&0x3f];
            if($i<$count)$v|=ord($input[$i])<<8;$o.=$this->itoa64[($v>>6)&0x3f];
            if($i++>=$count)break;
            if($i<$count)$v|=ord($input[$i])<<16;$o.=$this->itoa64[($v>>12)&0x3f];
            if($i++>=$count)break;
            $o.=$this->itoa64[($v>>18)&0x3f];
        }while($i<$count);
        return $o;
    }
    public function gensalt_private($input) { return '$P$'.$this->itoa64[min($this->iteration_count_log2+5,30)].$this->encode64($input,6); }
    public function crypt_private($password, $setting) { return elvado_wpx_phpass((string)$password,(string)$setting); }
    public function gensalt_blowfish($input) {
        $it='./ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $o='$2a$'.chr(ord('0')+intdiv($this->iteration_count_log2,10)).chr(ord('0')+$this->iteration_count_log2%10).'$';$i=0;
        do{ $c1=ord($input[$i++]);$o.=$it[$c1>>2];$c1=($c1&0x03)<<4;
            if($i>=16){ $o.=$it[$c1];break; }
            $c2=ord($input[$i++]);$c1|=$c2>>4;$o.=$it[$c1];$c1=($c2&0x0f)<<2;
            $c2=ord($input[$i++]);$c1|=$c2>>6;$o.=$it[$c1];$o.=$it[$c2&0x3f];
        }while(1);
        return $o;
    }
    public function HashPassword($password) {
        if(strlen((string)$password)>4096)return '*';
        $random='';
        if(!$this->portable_hashes){ $random=$this->get_random_bytes(16);$h=crypt((string)$password,$this->gensalt_blowfish($random));if(strlen($h)===60)return $h; }
        if(strlen($random)<6)$random=$this->get_random_bytes(6);
        $h=$this->crypt_private((string)$password,$this->gensalt_private($random));
        return strlen($h)===34?$h:'*';
    }
    public function CheckPassword($password, $stored_hash) {
        if(strlen((string)$password)>4096)return false;
        $h=$this->crypt_private((string)$password,(string)$stored_hash);
        if($h[0]==='*')$h=crypt((string)$password,(string)$stored_hash);
        return hash_equals((string)$stored_hash,(string)$h);
    }
}
}

/* ───────── Übersetzungsdateien (MO und PHP) ───────── */
if(!class_exists('ELVADO_C2_Plural')){
/** Kleiner Auswerter für „plural=…“-Ausdrücke aus Sprachdatei-Köpfen (n, Zahlen, % == != < > <= >= && || ! ?: und Klammern). */
final class ELVADO_C2_Plural {
    private $t=[];private $i=0;private $n=0;
    public static function index($forms, $n) {
        if(!preg_match('/plural\s*=\s*([^;]+)/',(string)$forms,$m))return (int)$n===1?0:1;
        $o=new self();preg_match_all('/\d+|n|&&|\|\||==|!=|<=|>=|[%<>!?:()]/',$m[1],$tk);$o->t=$tk[0];$o->n=(int)$n;
        try{ $v=$o->tern(); }catch(Throwable $e){ return (int)$n===1?0:1; }
        return max(0,(int)$v);
    }
    private function peek() { return $this->t[$this->i]??null; }
    private function eat() { return $this->t[$this->i++]??null; }
    private function tern() {
        $c=$this->p_or();
        if($this->peek()==='?'){ $this->eat();$a=$this->tern();if($this->eat()!==':')throw new Exception('x');$b=$this->tern();return $c?$a:$b; }
        return $c;
    }
    private function p_or() { $l=$this->p_and();while($this->peek()==='||'){ $this->eat();$r=$this->p_and();$l=($l||$r)?1:0; }return $l; }
    private function p_and() { $l=$this->p_eq();while($this->peek()==='&&'){ $this->eat();$r=$this->p_eq();$l=($l&&$r)?1:0; }return $l; }
    private function p_eq() { $l=$this->p_rel();while(in_array($this->peek(),['==','!='],true)){ $o=$this->eat();$r=$this->p_rel();$l=($o==='=='?$l==$r:$l!=$r)?1:0; }return $l; }
    private function p_rel() { $l=$this->p_mod();while(in_array($this->peek(),['<','>','<=','>='],true)){ $o=$this->eat();$r=$this->p_mod();$l=(match($o){'<'=>$l<$r,'>'=>$l>$r,'<='=>$l<=$r,default=>$l>=$r})?1:0; }return $l; }
    private function p_mod() { $l=$this->p_un();while($this->peek()==='%'){ $this->eat();$r=$this->p_un();$l=$r?$l%$r:0; }return $l; }
    private function p_un() { if($this->peek()==='!'){ $this->eat();return $this->p_un()?0:1; }return $this->prim(); }
    private function prim() {
        $x=$this->eat();
        if($x==='('){ $v=$this->tern();if($this->eat()!==')')throw new Exception('x');return $v; }
        if($x==='n')return $this->n;
        if($x!==null&&ctype_digit($x))return (int)$x;
        throw new Exception('x');
    }
}
}
if(!class_exists('WP_Translation_File')){
abstract class WP_Translation_File {
    protected $file;protected $entries=[];protected $headers=[];protected $error=null;protected $parsed=false;
    protected function __construct($file, $context='default') { $this->file=(string)$file; }
    public static function create($file, $context='default') {
        $ext=strtolower(pathinfo((string)$file,PATHINFO_EXTENSION));
        if($ext==='mo')return new WP_Translation_File_MO($file,$context);
        if($ext==='php')return new WP_Translation_File_PHP($file,$context);
        return false;
    }
    abstract protected function parse_file();
    protected function ensure_parsed() { if($this->parsed)return;$this->parsed=true;if(!is_file($this->file)||!is_readable($this->file)){ $this->error='Die Sprachdatei konnte nicht gelesen werden.';return; }$this->parse_file(); }
    public function get_file() { return $this->file; }
    public function exists() { return is_file($this->file)&&is_readable($this->file); }
    public function error() { $this->ensure_parsed();return $this->error; }
    public function headers() { $this->ensure_parsed();return $this->headers; }
    public function entries() { $this->ensure_parsed();return $this->entries; }
    public function get_language() { $h=$this->headers();return $h['language']??(preg_match('/([a-z]{2,3}(?:_[A-Z]{2})?)\.(?:mo|l10n\.php)$/',basename($this->file),$m)?$m[1]:null); }
    public function translate($text, $context=null) {
        $e=$this->entries();$k=($context!==null&&$context!==''?$context."\x04":'').$text;
        return isset($e[$k])?explode("\0",$e[$k])[0]:false;
    }
    public function translate_plural($plurals, $number, $context=null) {
        $e=$this->entries();$k=($context!==null&&$context!==''?$context."\x04":'').implode("\0",array_slice((array)$plurals,0,2));
        if(!isset($e[$k]))return false;
        $forms=explode("\0",$e[$k]);$i=ELVADO_C2_Plural::index($this->headers()['plural-forms']??'nplurals=2; plural=(n != 1);',(int)$number);
        return $forms[$i]??$forms[0];
    }
}
}
if(!class_exists('WP_Translation_File_MO')){
class WP_Translation_File_MO extends WP_Translation_File {
    protected function parse_file() {
        $d=(string)file_get_contents($this->file);
        if(strlen($d)<28){ $this->error='Die MO-Datei ist zu kurz.';return; }
        $magic=unpack('V',substr($d,0,4))[1];$f=$magic===0x950412de?'V':($magic===0xde120495?'N':'');
        if($f===''){ $this->error='Die MO-Datei hat eine ungültige Kennung.';return; }
        $h=unpack("{$f}1magic/{$f}1rev/{$f}1n/{$f}1o/{$f}1t",substr($d,0,20));$n=(int)$h['n'];$o=(int)$h['o'];$t=(int)$h['t'];
        if($n<0||$n>500000||$o+8*$n>strlen($d)||$t+8*$n>strlen($d)){ $this->error='Die MO-Datei ist beschädigt.';return; }
        for($i=0;$i<$n;$i++){
            $a=unpack("{$f}1l/{$f}1p",substr($d,$o+8*$i,8));$b=unpack("{$f}1l/{$f}1p",substr($d,$t+8*$i,8));
            if($a['p']+$a['l']>strlen($d)||$b['p']+$b['l']>strlen($d))continue;
            $orig=substr($d,$a['p'],$a['l']);$tr=substr($d,$b['p'],$b['l']);
            if($orig===''){ foreach(preg_split('/\r?\n/',$tr) as $line){ if(str_contains($line,':')){ [$k,$v]=explode(':',$line,2);$this->headers[strtolower(trim($k))]=trim($v); } }continue; }
            if($tr!=='')$this->entries[$orig]=$tr;
        }
        if(isset($this->headers['language']))$this->headers['language']=str_replace('-','_',$this->headers['language']);
    }
}
}
if(!class_exists('WP_Translation_File_PHP')){
class WP_Translation_File_PHP extends WP_Translation_File {
    protected function parse_file() {
        $d=(static function($f){ return include $f; })($this->file);
        if(!is_array($d)||!isset($d['messages'])||!is_array($d['messages'])){ $this->error='Die PHP-Sprachdatei hat ein ungültiges Format.';return; }
        foreach($d as $k=>$v)if($k!=='messages'&&is_scalar($v))$this->headers[strtolower((string)$k)]=(string)$v;
        foreach($d['messages'] as $k=>$v)if(is_string($v)&&$v!=='')$this->entries[(string)$k]=$v;
    }
}
}
if(!class_exists('WP_Translations')){
class WP_Translations {
    protected $file;protected $textdomain;
    public function __construct($file, $textdomain='default') { $this->file=$file;$this->textdomain=(string)$textdomain; }
    public function translate($text, $context=null) { $r=$this->file->translate((string)$text,$context);return $r===false?(string)$text:$r; }
    public function translate_plural($singular, $plural, $count, $context=null) {
        $r=$this->file->translate_plural([$singular,$plural],(int)$count,$context);
        return $r===false?((int)$count===1?$singular:$plural):$r;
    }
    public function get_file() { return $this->file; }
}
}
if(!class_exists('WP_Translation_Controller')){
class WP_Translation_Controller {
    private static $instance=null;private $current_locale='';private $loaded_files=[];private $textdomain_files=[];
    public static function get_instance() { return self::$instance??(self::$instance=new self()); }
    public function get_locale() { return $this->current_locale?:get_locale(); }
    public function set_locale($locale) { $this->current_locale=(string)$locale; }
    public function load_file($file, $textdomain='default', $locale=null) {
        $locale=$locale?:$this->get_locale();$f=WP_Translation_File::create($file);
        if(!$f||!$f->exists()||$f->error())return false;
        $this->loaded_files[$file][$locale][$textdomain]=$f;$this->textdomain_files[$textdomain][$locale][$file]=$f;return true;
    }
    public function unload_file($file, $textdomain='default', $locale=null) {
        foreach(array_keys($this->loaded_files[$file]??[]) as $l){ if($locale!==null&&$l!==$locale)continue;unset($this->loaded_files[$file][$l][$textdomain],$this->textdomain_files[$textdomain][$l][$file]); }
        return true;
    }
    public function unload_textdomain($textdomain='default', $locale=null) {
        foreach(array_keys($this->textdomain_files[$textdomain]??[]) as $l)if($locale===null||$l===$locale)unset($this->textdomain_files[$textdomain][$l]);
        return true;
    }
    public function is_textdomain_loaded($textdomain='default', $locale=null) { return !empty($this->textdomain_files[$textdomain][$locale?:$this->get_locale()]); }
    public function translate($text, $context=null, $textdomain='default', $locale=null) {
        foreach($this->textdomain_files[$textdomain][$locale?:$this->get_locale()]??[] as $f){ $r=$f->translate((string)$text,$context);if($r!==false)return $r; }
        return false;
    }
    public function translate_plural($plurals, $number, $context=null, $textdomain='default', $locale=null) {
        foreach($this->textdomain_files[$textdomain][$locale?:$this->get_locale()]??[] as $f){ $r=$f->translate_plural($plurals,$number,$context);if($r!==false)return $r; }
        return false;
    }
    public function get_entries($textdomain='default', $locale=null) { $o=[];foreach($this->textdomain_files[$textdomain][$locale?:$this->get_locale()]??[] as $f)$o+=$f->entries();return $o; }
    public function get_headers($textdomain='default', $locale=null) { $o=[];foreach($this->textdomain_files[$textdomain][$locale?:$this->get_locale()]??[] as $f)$o+=$f->headers();return $o; }
}
}
if(!class_exists('WP_Textdomain_Registry')){
class WP_Textdomain_Registry {
    protected $all=[];protected $custom_paths=[];
    public function has($textdomain) { return isset($this->all[$textdomain])||isset($this->custom_paths[$textdomain]); }
    public function set($textdomain, $path) { $this->all[$textdomain]=rtrim((string)$path,'/').'/'; }
    public function set_custom_path($textdomain, $path) { $this->custom_paths[$textdomain]=rtrim((string)$path,'/').'/'; }
    public function get($textdomain, $locale) {
        if(isset($this->custom_paths[$textdomain]))return $this->custom_paths[$textdomain];
        if($textdomain==='default')return trailingslashit(WP_LANG_DIR);
        foreach(['plugins','themes'] as $s)if(is_file(trailingslashit(WP_LANG_DIR).$s.'/'.$textdomain.'-'.$locale.'.mo'))return trailingslashit(WP_LANG_DIR).$s.'/';
        return $this->all[$textdomain]??false;
    }
    public function reset() { $this->all=[];$this->custom_paths=[]; }
}
}
if(!class_exists('WP_Locale_Switcher')){
class WP_Locale_Switcher {
    private $locales=[];private $original_locale='';
    public function init() { add_filter('locale',[$this,'filter_locale']); }
    public function switch_to_locale($locale, $user_id=false) {
        $current=determine_locale();if($current===$locale)return false;
        if(!$this->original_locale)$this->original_locale=$current;
        $this->locales[]=['locale'=>$locale,'user_id'=>$user_id];$this->reload($locale);
        do_action('switch_locale',$locale,$user_id);return true;
    }
    public function restore_previous_locale() {
        $prev=array_pop($this->locales);if($prev===null)return false;
        $locale=end($this->locales);$loc=$locale?$locale['locale']:$this->original_locale;
        if(!$locale){ $this->original_locale=''; }
        $this->reload($loc);do_action('restore_previous_locale',$loc,$prev['locale']);return $loc;
    }
    public function restore_current_locale() {
        if(!$this->original_locale)return false;
        $this->locales=[];$o=$this->original_locale;$this->original_locale='';$this->reload($o);
        do_action('restore_previous_locale',$o,'');return $o;
    }
    public function is_switched() { return !empty($this->locales); }
    public function get_switched_locale() { $l=end($this->locales);return $l?$l['locale']:false; }
    public function get_switched_user_id() { $l=end($this->locales);return $l?$l['user_id']:false; }
    public function filter_locale($locale) { $l=end($this->locales);return $l?$l['locale']:$locale; }
    private function reload($locale) {
        $GLOBALS['elvado_wp_mo']=[];$GLOBALS['elvado_wp_mo_tried']=[];
        if(isset($GLOBALS['wp_locale'])&&method_exists($GLOBALS['wp_locale'],'init'))$GLOBALS['wp_locale']->init(true);
    }
}
}

/* ───────── Diff-Darstellung (Tabelle und Fließtext) ───────── */
if(!class_exists('WP_Text_Diff_Renderer_Table')){
class WP_Text_Diff_Renderer_Table {
    public $_leading_context_lines=10000;public $_trailing_context_lines=10000;public $_diff_threshold=0.6;public $inline_diff_renderer='WP_Text_Diff_Renderer_inline';public $_show_split_view=true;protected $compat_fields=['_show_split_view','inline_diff_renderer','_diff_threshold'];
    public function __construct($params=[]) { foreach((array)$params as $k=>$v)if(property_exists($this,$k))$this->$k=$v; }
    public function addedLine($line) { return "<td class='diff-addedline'><span aria-hidden=\"true\" class=\"dashicons dashicons-plus\"></span><span class=\"screen-reader-text\">Hinzugefügt:</span> {$line}</td>"; }
    public function deletedLine($line) { return "<td class='diff-deletedline'><span aria-hidden=\"true\" class=\"dashicons dashicons-minus\"></span><span class=\"screen-reader-text\">Gelöscht:</span> {$line}</td>"; }
    public function contextLine($line) { return "<td class='diff-context'><span class=\"screen-reader-text\">Unverändert:</span> {$line}</td>"; }
    public function emptyLine() { return '<td>&nbsp;</td>'; }
    public function _startBlock($header) { return ''; }
    public function _lines($lines, $prefix=' ', $encode=true) {
        $o='';foreach((array)$lines as $l){ if($encode)$l=htmlspecialchars((string)$l);$o.='<tr>'.($prefix==='+'?$this->emptyLine().$this->addedLine($l):($prefix==='-'?$this->deletedLine($l).$this->emptyLine():$this->contextLine($l).$this->contextLine($l))).'</tr>'; }
        return $o;
    }
    public function _added($lines, $encode=true) {
        $o='';foreach((array)$lines as $l){ if($encode)$l=htmlspecialchars((string)$l);$o.='<tr>'.($this->_show_split_view?$this->emptyLine().$this->addedLine($l):$this->addedLine($l)).'</tr>'; }
        return $o;
    }
    public function _deleted($lines, $encode=true) {
        $o='';foreach((array)$lines as $l){ if($encode)$l=htmlspecialchars((string)$l);$o.='<tr>'.($this->_show_split_view?$this->deletedLine($l).$this->emptyLine():$this->deletedLine($l)).'</tr>'; }
        return $o;
    }
    public function _context($lines, $encode=true) {
        $o='';foreach((array)$lines as $l){ if($encode)$l=htmlspecialchars((string)$l);$o.='<tr>'.$this->contextLine($l).($this->_show_split_view?$this->contextLine($l):'').'</tr>'; }
        return $o;
    }
    public function _changed($orig, $final) {
        $o='';$orig=array_values((array)$orig);$final=array_values((array)$final);$n=max(count($orig),count($final));
        $inl=class_exists($this->inline_diff_renderer)?new $this->inline_diff_renderer():null;
        for($i=0;$i<$n;$i++){
            $a=$orig[$i]??null;$b=$final[$i]??null;
            if($a!==null&&$b!==null&&$inl){ [$da,$db]=$inl->diff_pair((string)$a,(string)$b);$o.='<tr>'.$this->deletedLine($da).$this->addedLine($db).'</tr>'; }
            elseif($a!==null)$o.=$this->_deleted([$a]);else $o.=$this->_added([$b]);
        }
        return $o;
    }
    /** Arbeitet eine Liste von Änderungen ab: je Eintrag [type=>copy|add|delete|change, orig=>[…], final=>[…]] oder Objekt mit gleichen Feldern. */
    public function render($diff) {
        $edits=is_object($diff)&&method_exists($diff,'getDiff')?$diff->getDiff():(array)$diff;$o='';
        foreach($edits as $e){ $e=(array)$e;$ty=$e['type']??'';$or=$e['orig']??[];$fi=$e['final']??[];
            $o.=match($ty){'copy'=>$this->_context($or),'add'=>$this->_added($fi),'delete'=>$this->_deleted($or),'change'=>$this->_changed($or,$fi),default=>''}; }
        return $o;
    }
}
}
if(!class_exists('WP_Text_Diff_Renderer_inline')){
class WP_Text_Diff_Renderer_inline {
    public $_leading_context_lines=10000;public $_trailing_context_lines=10000;
    public function _splitOnWords($string, $newlineEscape="\n") {
        $w=preg_split('/(?<=\s)(?=\S)|(?<=\S)(?=\s)/u',str_replace("\0",'',(string)$string),-1,PREG_SPLIT_NO_EMPTY);
        return array_map(fn($x)=>str_replace("\n",$newlineEscape,$x),$w?:[]);
    }
    private function tokens($s) { preg_match_all('/\s+|[^\s]+/u',(string)$s,$m);return $m[0]; }
    /** Wortweiser Vergleich zweier Zeilen; liefert beide Zeilen als HTML mit <del>/<ins> um die Unterschiede. */
    public function diff_pair($a, $b) {
        $x=$this->tokens($a);$y=$this->tokens($b);$n=count($x);$m=count($y);
        $t=array_fill(0,$n+1,array_fill(0,$m+1,0));
        for($i=$n-1;$i>=0;$i--)for($j=$m-1;$j>=0;$j--)$t[$i][$j]=$x[$i]===$y[$j]?$t[$i+1][$j+1]+1:max($t[$i+1][$j],$t[$i][$j+1]);
        $ra=$rb='';$i=$j=0;$e=fn($s)=>htmlspecialchars($s);
        $da=$db='';
        $flush=function() use(&$da,&$db,&$ra,&$rb){ if($da!==''){ $ra.='<del>'.$da.'</del>';$da=''; }if($db!==''){ $rb.='<ins>'.$db.'</ins>';$db=''; } };
        while($i<$n||$j<$m){
            if($i<$n&&$j<$m&&$x[$i]===$y[$j]){ $flush();$ra.=$e($x[$i]);$rb.=$e($y[$j]);$i++;$j++; }
            elseif($j>=$m||($i<$n&&$t[$i+1][$j]>=$t[$i][$j+1])){ $da.=$e($x[$i]);$i++; }
            else{ $db.=$e($y[$j]);$j++; }
        }
        $flush();return [$ra,$rb];
    }
    public function _changed($orig, $final) { [$a,$b]=$this->diff_pair(implode("\n",(array)$orig),implode("\n",(array)$final));return $a.$b; }
    public function _lines($lines, $prefix, $encode=true) { return $encode?htmlspecialchars(implode("\n",(array)$lines)):implode("\n",(array)$lines); }
    public function _added($lines, $encode=true) { return '<ins>'.$this->_lines($lines,'+',$encode).'</ins>'; }
    public function _deleted($lines, $encode=true) { return '<del>'.$this->_lines($lines,'-',$encode).'</del>'; }
}
}

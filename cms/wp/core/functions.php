<?php
// Allgemeine WordPress-Funktionen: Übersetzung, Fehlerobjekt, URLs, Nonces, Weiterleitungen, Remote-Aufrufe, Logging.

/* ───────── Übersetzung (Deutsch als Zielsprache; ohne .mo-Datei Originaltext) ───────── */
function _n_noop($singular, $plural, $domain=null) { return ['singular'=>$singular,'plural'=>$plural,0=>$singular,1=>$plural,'context'=>null,'domain'=>$domain]; }
function translate_nooped_plural($nooped, $count, $domain='default') { return _n($nooped['singular'],$nooped['plural'],$count,$domain); }
function esc_html__($text, $domain='default') { return esc_html(__($text,$domain)); }
function esc_attr__($text, $domain='default') { return esc_attr(__($text,$domain)); }
function esc_html_e($text, $domain='default') { echo esc_html(__($text,$domain)); }
function esc_attr_e($text, $domain='default') { echo esc_attr(__($text,$domain)); }
function esc_html_x($text, $context, $domain='default') { return esc_html(_x($text,$context,$domain)); }
function esc_attr_x($text, $context, $domain='default') { return esc_attr(_x($text,$context,$domain)); }
function determine_locale() { return get_locale(); }
function get_locale() { return apply_filters('locale',(string)get_option('WPLANG','de_DE')?:'de_DE'); }
function get_user_locale($user=0) { return get_locale(); }
function is_rtl() { return false; }
function get_bloginfo_locale() { return str_replace('_','-',get_locale()); }

/* ───────── WP_Error ───────── */
if(!class_exists('WP_Error')){
class WP_Error {
    public $errors=[];public $error_data=[];protected $additional_data=[];
    public function __construct($code='', $message='', $data='') { if(empty($code))return; $this->add($code,$message,$data); }
    public function get_error_codes() { return array_keys($this->errors); }
    public function get_error_code() { $c=$this->get_error_codes();return $c[0]??''; }
    public function get_error_messages($code='') { if(empty($code)){$all=[];foreach($this->errors as $m)$all=array_merge($all,$m);return $all;} return $this->errors[$code]??[]; }
    public function get_error_message($code='') { if(empty($code))$code=$this->get_error_code(); $m=$this->get_error_messages($code);return $m[0]??''; }
    public function get_error_data($code='') { if(empty($code))$code=$this->get_error_code(); return $this->error_data[$code]??null; }
    public function has_errors() { return !empty($this->errors); }
    public function add($code, $message, $data='') { $this->errors[$code][]=$message; if(!empty($data))$this->error_data[$code]=$data; }
    public function add_data($data, $code='') { if(empty($code))$code=$this->get_error_code(); $this->error_data[$code]=$data; }
    public function remove($code) { unset($this->errors[$code],$this->error_data[$code]); }
    public function merge_from($e) { foreach($e->errors as $c=>$ms)foreach($ms as $m)$this->add($c,$m,$e->error_data[$c]??''); }
}
}
function is_wp_error($thing) { return $thing instanceof WP_Error; }

/* ───────── Protokoll ───────── */
function elvado_wp_log(string $msg): void {
    if(!defined('ELVADO_WP_DATA'))return;
    $f=rtrim(ELVADO_WP_DATA,'/').'/debug.log';
    if(!is_dir(ELVADO_WP_DATA)){@mkdir(ELVADO_WP_DATA,0775,true);elvado_wp_protect_dir(ELVADO_WP_DATA);}
    if(is_file($f)&&filesize($f)>262144)@file_put_contents($f,substr((string)file_get_contents($f),-65536));
    @file_put_contents($f,'['.gmdate('Y-m-d H:i:s').'] '.str_replace(["\r","\n"],' ',$msg)."\n",FILE_APPEND|LOCK_EX);
}
function elvado_wp_protect_dir(string $dir): void {
    if(function_exists('elvado_protect_dir')){elvado_protect_dir($dir);return;}
    if(is_dir($dir)&&!is_file($dir.'/.htaccess'))@file_put_contents($dir.'/.htaccess',"Require all denied\n");
}
function error_log_wp($m) { elvado_wp_log((string)$m); }

/* ───────── URLs ───────── */
function elvado_wp_home_url(): string {
    static $u=null;if($u!==null)return $u;
    $base='';
    if(function_exists('elvado_seo_defaults')&&!empty($GLOBALS['ELVADO_SITE'])){$base=(string)(elvado_seo_defaults($GLOBALS['ELVADO_SITE'])['canonical_base']??'');}
    if($base===''&&!empty($_SERVER['HTTP_HOST'])){$https=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https';$base=($https?'https://':'http://').preg_replace('/[^A-Za-z0-9.\-:\[\]]/','',(string)$_SERVER['HTTP_HOST']);}
    // Anfrage-Host übernehmen, wenn er zur Website gehört (kanonische Domain, Marken-Domains, lokale Entwicklung) – so stimmen Vorschau und Zweitdomains
    $rh=strtolower(preg_replace('/:\d+$/','',(string)($_SERVER['HTTP_HOST']??'')));
    if($rh!==''&&preg_match('/^[a-z0-9.\-]+$/',$rh)){
        $known=in_array($rh,['localhost','127.0.0.1'],true);
        $strip=fn($h)=>preg_replace('/^www\./','',strtolower((string)$h));
        $ch=(string)parse_url($base,PHP_URL_HOST);if($ch!==''&&$strip($ch)===$strip($rh))$known=true;
        if(!$known&&function_exists('elvado_brands_registry')&&!empty($GLOBALS['ELVADO_SITE'])){
            try{foreach(elvado_brands_registry($GLOBALS['ELVADO_SITE'])['items'] as $b)foreach(array_merge([$b['primary_domain']??''],(array)($b['domains']??[])) as $d)if($d!==''&&$strip($d)===$strip($rh))$known=true;}catch(Throwable $e){}
        }
        if($known){$https=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https';$base=($https?'https://':'http://').(string)$_SERVER['HTTP_HOST'];}
    }
    return $u=rtrim($base,'/');
}
function home_url($path='', $scheme=null) { return apply_filters('home_url',elvado_wp_home_url().($path!==''&&$path[0]!=='/'?'/':'').$path,$path,$scheme,null); }
function site_url($path='', $scheme=null) { return apply_filters('site_url',elvado_wp_home_url().($path!==''&&$path[0]!=='/'?'/':'').$path,$path,$scheme,null); }
function admin_url($path='', $scheme='admin') { $p=ltrim((string)$path,'/');$base=in_array(strtok($p,'?'),['admin-ajax.php','admin-post.php'],true)?'/wp-admin/':'/cms/';return apply_filters('admin_url',elvado_wp_home_url().$base.$p,$path,null); }
function network_home_url($p='') { return home_url($p); }
function network_site_url($p='') { return site_url($p); }
function content_url($path='') { return elvado_wp_home_url().'/cms/wp-content'.($path!==''?'/'.ltrim($path,'/'):''); }
function includes_url($path='') { return elvado_wp_home_url().'/cms/wp/core'.($path!==''?'/'.ltrim($path,'/'):''); }
function plugins_url($path='', $plugin='') {
    $base=content_url('plugins');
    if($plugin){ $pdir=wp_normalize_path(dirname((string)$plugin)); $root=wp_normalize_path(WP_PLUGIN_DIR); if(str_starts_with($pdir,$root))$base.=substr($pdir,strlen($root)); }
    return $base.($path!==''?'/'.ltrim($path,'/'):'');
}
function wp_normalize_path($path) { $p=str_replace('\\','/',(string)$path);$p=preg_replace('|(?<=.)/+|','/',$p);return preg_match('#^[a-zA-Z]:#',$p)?ucfirst($p):$p; }
function get_home_url($blog_id=null, $path='', $scheme=null) { return home_url($path,$scheme); }
function get_site_url($blog_id=null, $path='', $scheme=null) { return site_url($path,$scheme); }
function wp_get_upload_dir() { $d=WP_CONTENT_DIR.'/uploads';return ['path'=>$d,'url'=>content_url('uploads'),'subdir'=>'','basedir'=>$d,'baseurl'=>content_url('uploads'),'error'=>false]; }
function wp_upload_dir($time=null, $create_dir=true, $refresh_cache=false) { $u=wp_get_upload_dir();if($create_dir&&!is_dir($u['path']))@mkdir($u['path'],0775,true);return $u; }
function is_ssl() { return (!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https'||str_starts_with(elvado_wp_home_url(),'https://'); }
function set_url_scheme($url, $scheme=null) { return (string)$url; }
function wp_get_referer() { $r=$_SERVER['HTTP_REFERER']??'';return $r!==''?esc_url_raw($r):false; }
function wp_validate_redirect($location, $fallback_url='') {
    $location=trim((string)$location);if($location==='')return $fallback_url;
    if(str_starts_with($location,'//'))$location='http:'.$location;
    $p=parse_url($location);if(!$p)return $fallback_url;
    if(empty($p['host']))return $location;
    $home=parse_url(elvado_wp_home_url(),PHP_URL_HOST);
    return strtolower($p['host'])===strtolower((string)$home)?$location:$fallback_url;
}
function wp_redirect($location, $status=302, $x_redirect_by='WordPress') {
    $location=apply_filters('wp_redirect',$location,$status);if(!$location)return false;
    $location=preg_replace('/[\r\n]/','',(string)$location);
    if(!headers_sent())header('Location: '.$location,true,$status);
    if(!empty($GLOBALS['elvado_wp_is_admin'])){ $GLOBALS['elvado_wp_admin_redirect']=$location;throw new ELVADO_WP_Die('',$status); }   // Plugins rufen danach exit auf: stattdessen die Anfrage sauber beenden
    return true;
}
function wp_safe_redirect($location, $status=302, $x_redirect_by='WordPress') { return wp_redirect(wp_validate_redirect($location,home_url()),$status,$x_redirect_by); }
function status_header($code, $description='') { if(!headers_sent())http_response_code((int)$code); }
function nocache_headers() { if(!headers_sent()){header('Cache-Control: no-cache, must-revalidate, max-age=0');header('Pragma: no-cache');} }
function wp_get_http_headers() { return []; }
function wp_is_mobile() { $ua=$_SERVER['HTTP_USER_AGENT']??'';return $ua!==''&&(bool)preg_match('/Mobile|Android|Silk\/|Kindle|BlackBerry|Opera Mini|Opera Mobi/i',$ua); }

/* ───────── Nonces (HMAC mit Seitensalz) ───────── */
function wp_salt($scheme='auth') {
    static $salt=null;
    if($salt===null){
        $f=rtrim(ELVADO_WP_DATA,'/').'/salt.json';
        $d=is_file($f)?json_decode((string)file_get_contents($f),true):null;
        if(!is_array($d)||empty($d['s'])){$d=['s'=>bin2hex(random_bytes(32))];if(!is_dir(ELVADO_WP_DATA)){@mkdir(ELVADO_WP_DATA,0775,true);elvado_wp_protect_dir(ELVADO_WP_DATA);}@file_put_contents($f,json_encode($d),LOCK_EX);}
        $salt=(string)$d['s'];
    }
    return hash_hmac('sha256',(string)$scheme,$salt);
}
function wp_hash($data, $scheme='auth') { return hash_hmac('md5',(string)$data,wp_salt($scheme)); }
function wp_nonce_tick() { return (int)ceil(time()/(12*3600/2)); }
function wp_create_nonce($action=-1) { $uid=(int)get_current_user_id();return substr(wp_hash(wp_nonce_tick().'|'.$action.'|'.$uid.'|'.elvado_wp_session_token(),'nonce'),-12,10); }
function wp_verify_nonce($nonce, $action=-1) {
    $nonce=(string)$nonce;if($nonce==='')return false;$uid=(int)get_current_user_id();$tick=wp_nonce_tick();
    foreach([1=>$tick,2=>$tick-1] as $r=>$t){ $e=substr(wp_hash($t.'|'.$action.'|'.$uid.'|'.elvado_wp_session_token(),'nonce'),-12,10); if(hash_equals($e,$nonce))return $r; }
    return false;
}
function elvado_wp_session_token(): string { return (string)($GLOBALS['elvado_wp_session_token']??''); }
function wp_nonce_field($action=-1, $name='_wpnonce', $referer=true, $display=true) {
    $f='<input type="hidden" id="'.esc_attr($name).'" name="'.esc_attr($name).'" value="'.esc_attr(wp_create_nonce($action)).'" />';
    if($referer)$f.=wp_referer_field(false);
    if($display)echo $f;return $f;
}
function wp_referer_field($display=true) { $f='<input type="hidden" name="_wp_http_referer" value="'.esc_attr($_SERVER['REQUEST_URI']??'').'" />';if($display)echo $f;return $f; }
function check_admin_referer($action=-1, $query_arg='_wpnonce') { $ok=wp_verify_nonce($_REQUEST[$query_arg]??'',$action);if(!$ok){wp_nonce_ays($action);}return $ok; }
function check_ajax_referer($action=-1, $query_arg=false, $stop=true) { $q=$query_arg?:'_ajax_nonce';$n=$_REQUEST[$q]??($_REQUEST['_wpnonce']??'');$ok=wp_verify_nonce($n,$action);if(!$ok&&$stop)wp_die('-1',403);return $ok; }
function wp_nonce_ays($action) { wp_die(__('Der Link ist abgelaufen. Bitte versuche es erneut.'),__('Fehler'),['response'=>403]); }

/* ───────── Beenden / JSON ───────── */
class ELVADO_WP_Die extends Exception {}
function wp_die($message='', $title='', $args=[]) {
    if(is_wp_error($message))$message=$message->get_error_message();
    $args=wp_parse_args($args,['response'=>is_int($title)?$title:(wp_doing_ajax()?200:500),'exit'=>true]);if(is_int($title))$title='';
    if(!empty($GLOBALS['elvado_wp_die_throws']))throw new ELVADO_WP_Die((string)$message,(int)$args['response']);
    if(!headers_sent()){http_response_code((int)$args['response']);header('Content-Type: text/html; charset=utf-8');}
    echo '<!doctype html><meta charset="utf-8"><title>'.esc_html($title?:'Fehler').'</title><body style="font:16px system-ui;max-width:640px;margin:10vh auto;padding:0 20px"><p>'.wp_kses_post((string)$message).'</p></body>';
    if($args['exit'])exit;
}
function wp_send_json($response, $status_code=null, $flags=0) {
    if(!headers_sent()){header('Content-Type: application/json; charset=utf-8');if($status_code)http_response_code((int)$status_code);}
    echo wp_json_encode($response,$flags);
    if(!empty($GLOBALS['elvado_wp_die_throws']))throw new ELVADO_WP_Die('json',(int)($status_code?:200));
    exit;
}
function wp_send_json_success($data=null, $status_code=null) { $r=['success'=>true];if(isset($data))$r['data']=$data;wp_send_json($r,$status_code); }
function wp_send_json_error($data=null, $status_code=null) { $r=['success'=>false];if(isset($data))$r['data']=is_wp_error($data)?['code'=>$data->get_error_code(),'message'=>$data->get_error_message()]:$data;wp_send_json($r,$status_code); }
function wp_doing_ajax() { return !empty($GLOBALS['elvado_wp_doing_ajax']); }
function wp_doing_cron() { return false; }
function wp_is_json_request() { return str_contains((string)($_SERVER['HTTP_ACCEPT']??''),'json'); }
function wp_is_xml_request() { return false; }

/* ───────── Remote (SSRF-Schutz: nur öffentliche Hosts) ───────── */
function elvado_wp_host_public(string $host): bool {
    if($host===''||strcasecmp($host,'localhost')===0)return false;
    $ips=filter_var($host,FILTER_VALIDATE_IP)?[$host]:array_merge((array)@gethostbynamel($host)?:[]);
    if(!$ips)return false;
    foreach($ips as $ip)if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))return false;
    return true;
}
function wp_remote_request($url, $args=[]) {
    $args=wp_parse_args($args,['method'=>'GET','timeout'=>8,'headers'=>[],'body'=>null,'redirection'=>3,'user-agent'=>'WordPress/6.5; '.elvado_wp_home_url()]);
    $pre=apply_filters('pre_http_request',false,$args,$url);if(false!==$pre)return $pre;
    $p=parse_url((string)$url);
    if(!$p||!in_array($p['scheme']??'',['http','https'],true)||!elvado_wp_host_public((string)($p['host']??'')))return new WP_Error('http_request_failed','Ungültige oder nicht erlaubte Adresse.');
    if(!function_exists('curl_init'))return new WP_Error('http_request_failed','curl fehlt.');
    $hdrs=[];foreach((array)$args['headers'] as $k=>$v)$hdrs[]=$k.': '.$v;
    $respHeaders=[];$ch=curl_init((string)$url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>strtoupper((string)$args['method']),CURLOPT_TIMEOUT=>(int)$args['timeout'],CURLOPT_CONNECTTIMEOUT=>6,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>(int)$args['redirection'],CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_USERAGENT=>(string)$args['user-agent'],CURLOPT_HTTPHEADER=>$hdrs,
        CURLOPT_HEADERFUNCTION=>function($c,$h) use(&$respHeaders){ $t=explode(':',$h,2);if(count($t)===2)$respHeaders[strtolower(trim($t[0]))]=trim($t[1]);return strlen($h); }]);
    if($args['body']!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,is_array($args['body'])?http_build_query($args['body']):$args['body']);
    $body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);
    if($body===false)return new WP_Error('http_request_failed',$err?:'Anfrage fehlgeschlagen');
    return ['headers'=>$respHeaders,'body'=>(string)$body,'response'=>['code'=>$code,'message'=>''],'cookies'=>[],'filename'=>null];
}
function wp_remote_get($url, $args=[]) { return wp_remote_request($url,['method'=>'GET']+(array)$args); }
function wp_remote_post($url, $args=[]) { return wp_remote_request($url,['method'=>'POST']+(array)$args); }
function wp_remote_head($url, $args=[]) { return wp_remote_request($url,['method'=>'HEAD']+(array)$args); }
function wp_remote_retrieve_body($r) { return is_array($r)?(string)($r['body']??''):''; }
function wp_remote_retrieve_response_code($r) { return is_array($r)?(int)($r['response']['code']??0):''; }
function wp_remote_retrieve_response_message($r) { return is_array($r)?(string)($r['response']['message']??''):''; }
function wp_remote_retrieve_headers($r) { return is_array($r)?($r['headers']??[]):[]; }
function wp_remote_retrieve_header($r, $header) { return is_array($r)?($r['headers'][strtolower($header)]??''):''; }

/* ───────── Verschiedenes ───────── */
function wp_generate_password($length=12, $special_chars=true, $extra_special_chars=false) {
    $c='abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'.($special_chars?'!@#$%^&*()':'').($extra_special_chars?'-_ []{}<>~`+=,.;:/?|':'');
    $p='';for($i=0;$i<$length;$i++)$p.=$c[random_int(0,strlen($c)-1)];return $p;
}
function wp_rand($min=0, $max=0) { return random_int((int)$min,(int)($max?:mt_getrandmax())); }
function wp_unique_id($prefix='') { static $n=0;return $prefix.(++$n); }
function wp_unique_prefixed_id($prefix='') { return wp_unique_id($prefix); }
function wp_generate_uuid4() { $d=random_bytes(16);$d[6]=chr(ord($d[6])&0x0f|0x40);$d[8]=chr(ord($d[8])&0x3f|0x80);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4)); }
function wp_is_numeric_array($d) { return is_array($d)&&array_keys($d)===range(0,count($d)-1); }
function is_multisite() { return false; }
function is_main_site($id=null) { return true; }
function get_current_blog_id() { return 1; }
function switch_to_blog($id) { return true; }
function restore_current_blog() { return true; }
function wp_get_environment_type() { return 'production'; }
function wp_debug_backtrace_summary($ignore=null, $skip=0, $pretty=true) { return ''; }
function wp_get_theme_dummy() { return null; }
function get_num_queries() { return 0; }
function timer_stop($display=0, $precision=3) { return number_format(microtime(true)-($_SERVER['REQUEST_TIME_FLOAT']??microtime(true)),$precision); }
function wp_ob_end_flush_all() { while(@ob_end_flush()); }
function wp_raise_memory_limit($ctx='admin') { return false; }
function wp_convert_hr_to_bytes($v) { $v=strtolower(trim((string)$v));$n=(int)$v;switch(substr($v,-1)){case 'g':$n*=1024;case 'm':$n*=1024;case 'k':$n*=1024;}return $n; }
function wp_max_upload_size() { return min(wp_convert_hr_to_bytes(ini_get('upload_max_filesize')),wp_convert_hr_to_bytes(ini_get('post_max_size'))); }
function wp_check_filetype($filename, $mimes=null) {
    $ext=strtolower(pathinfo((string)$filename,PATHINFO_EXTENSION));
    $types=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','svg'=>'image/svg+xml','ico'=>'image/x-icon','pdf'=>'application/pdf','mp3'=>'audio/mpeg','mp4'=>'video/mp4','zip'=>'application/zip','txt'=>'text/plain','css'=>'text/css','js'=>'application/javascript','json'=>'application/json','csv'=>'text/csv'];
    return isset($types[$ext])?['ext'=>$ext,'type'=>$types[$ext]]:['ext'=>false,'type'=>false];
}
function get_file_data($file, $default_headers, $context='') {
    $fp=@fopen($file,'r');if(!$fp)return array_fill_keys(array_keys($default_headers),'');
    $data=fread($fp,8192);fclose($fp);$data=str_replace("\r","\n",$data);$out=[];
    foreach($default_headers as $field=>$regex){ $out[$field]=preg_match('/^(?:[ \t]*<\?php)?[ \t\/*#@]*'.preg_quote($regex,'/').':(.*)$/mi',$data,$m)&&$m[1]?trim(preg_replace('/\s*(?:\*\/|\?>).*/','',$m[1])):''; }
    return $out;
}

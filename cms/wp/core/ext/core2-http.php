<?php
// Ergänzende WordPress-Funktionen (Kern 2, Teil 13): HTTP-Klassen (Requests-Fassade, Kodierung, Socket-Transport), E-Mail (WP_PHPMailer), POP3.
// Eigenständig umgesetzt. Netzwerkzugriffe laufen nur beim Aufruf; Anfragen gehen, wo möglich, durch die HTTP-API der Schicht (Filter pre_http_request).

/* ───────── Requests (alte Bibliothek) als Fassade über die HTTP-API ───────── */
if(!class_exists('Requests_Response')){
#[AllowDynamicProperties]
class Requests_Response {
    public $body='';public $raw='';public $headers=[];public $status_code=false;public $protocol_version=false;public $success=false;public $redirects=0;public $url='';public $history=[];public $cookies=[];
    public function is_redirect() { return in_array($this->status_code,[300,301,302,303,307,308],true)&&isset($this->headers['location']); }
    public function throw_for_status($allow_redirects=true) {
        if($this->status_code>=400||(!$allow_redirects&&$this->is_redirect()))throw new Exception('HTTP-Status '.$this->status_code);
    }
}
}
if(!class_exists('Requests_Hooks')){
class Requests_Hooks {
    protected $hooks=[];
    public function register($hook, $callback, $priority=0) { $this->hooks[$hook][(int)$priority][]=$callback; }
    public function dispatch($hook, $parameters=[]) {
        if(empty($this->hooks[$hook]))return false;
        ksort($this->hooks[$hook]);
        foreach($this->hooks[$hook] as $cbs)foreach($cbs as $cb)call_user_func_array($cb,$parameters);
        return true;
    }
}
}
if(!class_exists('WP_HTTP_Requests_Hooks')){
class WP_HTTP_Requests_Hooks extends Requests_Hooks {
    protected $url;protected $request=[];
    public function __construct($url, $request) { $this->url=$url;$this->request=$request; }
    public function dispatch($hook, $parameters=[]) {
        $r=parent::dispatch($hook,$parameters);
        if('curl.before_send'===$hook&&isset($parameters[0]))do_action_ref_array('http_api_curl',[&$parameters[0],$this->request,$this->url]);
        do_action_ref_array("requests-{$hook}",array_merge($parameters,[$this->request,$this->url]));
        return $r;
    }
}
}
if(!class_exists('Requests')){
class Requests {
    const POST='POST';const PUT='PUT';const GET='GET';const HEAD='HEAD';const DELETE='DELETE';const OPTIONS='OPTIONS';const TRACE='TRACE';const PATCH='PATCH';const BUFFER_SIZE=1160;const VERSION='2.0.0';
    public static $transport=[];public static $certificate_path='';
    public static function register_autoloader() {}
    public static function set_certificate_path($path) { self::$certificate_path=$path; }
    public static function get($url, $headers=[], $options=[]) { return self::request($url,$headers,null,self::GET,$options); }
    public static function head($url, $headers=[], $options=[]) { return self::request($url,$headers,null,self::HEAD,$options); }
    public static function delete($url, $headers=[], $options=[]) { return self::request($url,$headers,null,self::DELETE,$options); }
    public static function trace($url, $headers=[], $options=[]) { return self::request($url,$headers,null,self::TRACE,$options); }
    public static function post($url, $headers=[], $data=[], $options=[]) { return self::request($url,$headers,$data,self::POST,$options); }
    public static function put($url, $headers=[], $data=[], $options=[]) { return self::request($url,$headers,$data,self::PUT,$options); }
    public static function options($url, $headers=[], $data=[], $options=[]) { return self::request($url,$headers,$data,self::OPTIONS,$options); }
    public static function patch($url, $headers, $data=[], $options=[]) { return self::request($url,$headers,$data,self::PATCH,$options); }
    public static function get_default_options($multirequest=false) { return ['timeout'=>10,'connect_timeout'=>10,'useragent'=>'PHP-Requests/'.self::VERSION,'redirects'=>10,'follow_redirects'=>true,'blocking'=>true,'type'=>self::GET,'filename'=>false,'auth'=>false,'proxy'=>false,'cookies'=>false,'max_bytes'=>false,'idn'=>true,'hooks'=>null,'transport'=>null,'verify'=>self::$certificate_path,'verifyname'=>true]; }
    public static function flatten($array) { $o=[];foreach((array)$array as $k=>$v)$o[]=$k.': '.$v;return $o; }
    public static function request($url, $headers=[], $data=[], $type=self::GET, $options=[]) {
        $o=array_merge(self::get_default_options(),(array)$options);
        $r=wp_remote_request((string)$url,['method'=>$type,'headers'=>(array)$headers,'body'=>$data,'timeout'=>$o['timeout'],'redirection'=>$o['follow_redirects']?$o['redirects']:0,'user-agent'=>$o['useragent']]);
        if(is_wp_error($r))throw new Exception($r->get_error_message());
        $res=new Requests_Response();$res->url=(string)$url;$res->body=wp_remote_retrieve_body($r);$res->status_code=wp_remote_retrieve_response_code($r);
        $res->headers=array_change_key_case((array)wp_remote_retrieve_headers($r),CASE_LOWER);$res->success=$res->status_code>=200&&$res->status_code<300;
        return $res;
    }
    public static function request_multiple($requests, $options=[]) {
        $o=[];foreach((array)$requests as $k=>$q){ try{ $o[$k]=self::request($q['url'],$q['headers']??[],$q['data']??[],$q['type']??self::GET,array_merge($options,$q['options']??[])); }catch(Exception $e){ $o[$k]=$e; } }
        return $o;
    }
}
}
if(!class_exists('WP_HTTP_Requests_Response')){
class WP_HTTP_Requests_Response extends WP_HTTP_Response {
    protected $response;protected $filename;
    public function __construct($response, $filename='') {
        $this->response=$response;$this->filename=$filename;
        parent::__construct($response->body??'',(int)($response->status_code??0),(array)($response->headers??[]));
    }
    public function get_response_object() { return $this->response; }
    public function get_headers() { return array_change_key_case((array)$this->headers,CASE_LOWER); }
    public function set_headers($headers) { $this->headers=(array)$headers;return $this; }
    public function header($key, $value, $replace=true) { $k=strtolower($key);if($replace||!isset($this->headers[$k]))$this->headers[$k]=$value;else $this->headers[$k].=', '.$value; }
    public function get_status() { return (int)$this->status; }
    public function get_cookies() { $o=[];foreach((array)($this->response->cookies??[]) as $n=>$c)$o[]=new WP_Http_Cookie(['name'=>$n,'value'=>is_object($c)?($c->value??''):$c]);return $o; }
    public function to_array() {
        return ['headers'=>$this->get_headers(),'body'=>$this->data,'response'=>['code'=>$this->get_status(),'message'=>get_status_header_desc($this->get_status())],'cookies'=>$this->get_cookies(),'filename'=>$this->filename,'http_response'=>$this];
    }
}
}

/* ───────── Kodierung ───────── */
if(!class_exists('WP_Http_Encoding')){
class WP_Http_Encoding {
    public static function compress($raw, $level=9, $supports=null) { return gzdeflate($raw,$level); }
    public static function decompress($compressed, $length=null) {
        if(empty($compressed))return $compressed;
        if(false!==($d=@gzinflate($compressed)))return $d;
        if(false!==($d=self::compatible_gzinflate($compressed)))return $d;
        if(false!==($d=@gzuncompress($compressed)))return $d;
        if(function_exists('gzdecode')&&false!==($d=@gzdecode($compressed)))return $d;
        return $compressed;
    }
    public static function compatible_gzinflate($gzData) {
        if(!str_starts_with($gzData,"\x1f\x8b\x08"))return false;
        $i=10;$flg=ord(substr($gzData,3,1));
        if($flg>0){
            if($flg&4){ $c=unpack('v',substr($gzData,$i,2));$i+=2+$c[1]; }
            if($flg&8)$i=strpos($gzData,"\0",$i)+1;
            if($flg&16)$i=strpos($gzData,"\0",$i)+1;
            if($flg&2)$i+=2;
        }
        $d=@gzinflate(substr($gzData,$i,-8));
        return $d!==false?$d:false;
    }
    public static function accept_encoding($url, $args) {
        $types=[];
        if(function_exists('gzinflate')&&(!isset($args['decompress'])||$args['decompress']))$types[]='deflate';
        if(function_exists('gzuncompress'))$types[]='compress';
        if(function_exists('gzdecode'))$types[]='gzip';
        return implode(', ',(array)apply_filters('wp_http_accept_encoding',$types,$url,$args));
    }
    public static function content_encoding() { return 'deflate'; }
    public static function should_decode($headers) {
        if(is_array($headers)){ foreach(array_keys($headers) as $k)if(strtolower((string)$k)==='content-encoding')return true; return false; }
        return is_string($headers)&&stripos($headers,'content-encoding:')!==false;
    }
    public static function is_available() { return function_exists('gzuncompress')||function_exists('gzdeflate')||function_exists('gzinflate'); }
}
}

/* ───────── Socket-Transport ───────── */
if(!class_exists('WP_HTTP_Fsockopen')){
class WP_HTTP_Fsockopen {
    public function request($url, $args=[]) {
        $d=['method'=>'GET','timeout'=>5,'redirection'=>5,'headers'=>[],'body'=>null,'user-agent'=>'WordPress','decompress'=>true];
        $r=wp_parse_args($args,$d);$p=parse_url((string)$url);
        if(!$p||empty($p['host'])||!in_array($p['scheme']??'http',['http','https'],true))return new WP_Error('http_request_failed','Ungültige Adresse.');
        if(!elvado_wp_host_public($p['host']))return new WP_Error('http_request_failed','Der Zielhost ist nicht erlaubt.');
        $ssl=($p['scheme']??'http')==='https';$port=(int)($p['port']??($ssl?443:80));
        $fp=@stream_socket_client(($ssl?'ssl://':'tcp://').$p['host'].':'.$port,$en,$es,(float)$r['timeout']);
        if(!$fp)return new WP_Error('http_request_failed',$es?:'Verbindung fehlgeschlagen.');
        stream_set_timeout($fp,(int)$r['timeout']);
        $path=($p['path']??'/').(isset($p['query'])?'?'.$p['query']:'');$body=is_array($r['body'])?http_build_query($r['body']):(string)$r['body'];
        $h=array_change_key_case((array)$r['headers'],CASE_LOWER);
        $req=strtoupper($r['method'])." $path HTTP/1.1\r\nHost: ".$p['host'].($port!==80&&$port!==443?":$port":'')."\r\nUser-Agent: ".$r['user-agent']."\r\nConnection: close\r\nAccept-Encoding: ".WP_Http_Encoding::accept_encoding($url,$r)."\r\n";
        foreach($h as $k=>$v)$req.=$k.': '.$v."\r\n";
        if($body!==''){ $req.='Content-Length: '.strlen($body)."\r\n"; }
        fwrite($fp,$req."\r\n".$body);
        $raw='';while(!feof($fp)){ $c=fread($fp,8192);if($c===false||$c==='')break;$raw.=$c; }
        fclose($fp);
        [$head,$bodyOut]=array_pad(explode("\r\n\r\n",$raw,2),2,'');
        $lines=explode("\r\n",$head);$status=array_shift($lines);
        if(!preg_match('~^HTTP/\S+\s+(\d{3})\s*(.*)$~',(string)$status,$m))return new WP_Error('http_request_failed','Ungültige Antwort.');
        $hdr=[];foreach($lines as $l){ if(str_contains($l,':')){ [$k,$v]=explode(':',$l,2);$hdr[strtolower(trim($k))]=trim($v); } }
        if(isset($hdr['transfer-encoding'])&&stripos($hdr['transfer-encoding'],'chunked')!==false){
            $dec='';$rest=$bodyOut;while($rest!==''&&($pos=strpos($rest,"\r\n"))!==false){ $len=hexdec(trim(substr($rest,0,$pos)));if($len<=0)break;$dec.=substr($rest,$pos+2,$len);$rest=substr($rest,$pos+2+$len+2); }
            $bodyOut=$dec;
        }
        if($r['decompress']&&WP_Http_Encoding::should_decode($hdr))$bodyOut=WP_Http_Encoding::decompress($bodyOut);
        return ['headers'=>$hdr,'body'=>$bodyOut,'response'=>['code'=>(int)$m[1],'message'=>trim($m[2])],'cookies'=>[],'filename'=>null];
    }
    public static function test($args=[]) { return function_exists('stream_socket_client')&&apply_filters('use_fsockopen_transport',true,$args); }
}
}

/* ───────── E-Mail ───────── */
if(!class_exists('WP_PHPMailer')){
#[AllowDynamicProperties]
class WP_PHPMailer {
    public $CharSet='UTF-8';public $ContentType='text/plain';public $Encoding='8bit';public $From='root@localhost';public $FromName='Root User';public $Subject='';public $Body='';public $AltBody='';public $Mailer='mail';public $ErrorInfo='';public $XMailer='';public $MessageID='';public $Priority=null;public $Sender='';
    public $to=[];public $cc=[];public $bcc=[];public $ReplyTo=[];public $attachments=[];public $CustomHeader=[];public $Host='localhost';public $Port=25;
    public function isHTML($isHtml=true) { $this->ContentType=$isHtml?'text/html':'text/plain'; }
    public function isMail() { $this->Mailer='mail'; } public function isSMTP() { $this->Mailer='smtp'; } public function isSendmail() { $this->Mailer='sendmail'; }
    private function add(array &$list, $address, $name='') { $a=trim((string)$address);if(!filter_var($a,FILTER_VALIDATE_EMAIL)){ $this->ErrorInfo='Ungültige Adresse: '.$a;return false; }$list[strtolower($a)]=[$a,(string)$name];return true; }
    public function addAddress($address, $name='') { return $this->add($this->to,$address,$name); }
    public function addCC($address, $name='') { return $this->add($this->cc,$address,$name); }
    public function addBCC($address, $name='') { return $this->add($this->bcc,$address,$name); }
    public function addReplyTo($address, $name='') { return $this->add($this->ReplyTo,$address,$name); }
    public function setFrom($address, $name='', $auto=true) {
        $a=trim((string)$address);if(!filter_var($a,FILTER_VALIDATE_EMAIL)){ $this->ErrorInfo='Ungültige Absenderadresse: '.$a;return false; }
        $this->From=$a;$this->FromName=(string)$name;if($this->Sender==='')$this->Sender=$a;return true;
    }
    public function addAttachment($path, $name='', $encoding='base64', $type='', $disposition='attachment') { if(!is_file($path))return false;$this->attachments[]=[$path,$name?:basename($path)];return true; }
    public function addCustomHeader($name, $value=null) { $this->CustomHeader[]=$value===null?[trim((string)$name)]:[trim((string)$name),trim((string)$value)];return true; }
    public function clearAllRecipients() { $this->to=$this->cc=$this->bcc=[]; }
    public function clearAddresses() { $this->to=[]; } public function clearAttachments() { $this->attachments=[]; } public function clearCustomHeaders() { $this->CustomHeader=[]; }
    private function fmt($e) { return $e[1]!==''?'=?UTF-8?B?'.base64_encode($e[1]).'?= <'.$e[0].'>':$e[0]; }
    public function createHeader() {
        $h=['From: '.$this->fmt([$this->From,$this->FromName])];
        if($this->cc)$h[]='Cc: '.implode(', ',array_map([$this,'fmt'],$this->cc));
        if($this->ReplyTo)$h[]='Reply-To: '.implode(', ',array_map([$this,'fmt'],$this->ReplyTo));
        $h[]='MIME-Version: 1.0';$h[]='Content-Type: '.$this->ContentType.'; charset='.$this->CharSet;$h[]='Content-Transfer-Encoding: '.$this->Encoding;
        foreach($this->CustomHeader as $c)$h[]=isset($c[1])?$c[0].': '.$c[1]:$c[0];
        return implode("\r\n",$h);
    }
    public function send() {
        if(!$this->to&&!$this->cc&&!$this->bcc){ $this->ErrorInfo='Es wurde kein Empfänger angegeben.';return false; }
        if($this->attachments){ $this->ErrorInfo='Anhänge werden vom einfachen Versand nicht unterstützt.';return false; }
        if(!function_exists('mail')){ $this->ErrorInfo='mail() ist nicht verfügbar.';return false; }
        $to=implode(', ',array_map([$this,'fmt'],$this->to+$this->bcc));
        $ok=@mail($to,'=?UTF-8?B?'.base64_encode($this->Subject).'?=',$this->Body,$this->createHeader());
        if(!$ok)$this->ErrorInfo='E-Mail konnte nicht gesendet werden.';
        return $ok;
    }
}
}

/* ───────── POP3 ───────── */
if(!class_exists('POP3')){
#[AllowDynamicProperties]
class POP3 {
    public $ERROR='';public $TIMEOUT=60;public $COUNT=-1;public $BUFFER=512;public $FP='';public $MAILSERVER='';public $DEBUG=false;public $BANNER='';public $ALLOWED_APOP_TYPES=['APOP','CRAM-MD5'];
    public function __construct($server='', $timeout='') { settype($this->BUFFER,'integer');if(!empty($server))$this->MAILSERVER=$server;if(!empty($timeout)){ settype($timeout,'integer');$this->TIMEOUT=$timeout; } }
    public function update_timer() { if($this->FP)stream_set_timeout($this->FP,$this->TIMEOUT);return true; }
    public function connect($server, $port=110) {
        if(empty($server)){ $this->ERROR='POP3 connect: Kein Server angegeben.';return false; }
        $this->MAILSERVER=$server;
        $fp=@fsockopen($server,$port,$en,$es,$this->TIMEOUT);
        if(!$fp){ $this->ERROR="POP3 connect: Fehler [$en] [$es]";return false; }
        $this->FP=$fp;$this->update_timer();
        $r=fgets($fp,$this->BUFFER);$r=$this->strip_clf($r);$this->BANNER=$this->parse_banner($r);
        if(!$this->is_ok($r)){ $this->ERROR='POP3 connect: '.$r;unset($this->FP);return false; }
        return true;
    }
    private function cmd($c) { fwrite($this->FP,$c."\r\n");return $this->strip_clf(fgets($this->FP,$this->BUFFER)); }
    public function user($user='') {
        if(empty($user)){ $this->ERROR='POP3 user: Kein Benutzername angegeben.';return false; }
        if(!$this->FP){ $this->ERROR='POP3 user: Keine Verbindung.';return false; }
        $r=$this->cmd("USER $user");if(!$this->is_ok($r)){ $this->ERROR='POP3 user: '.$r;return false; }
        return true;
    }
    public function pass($pass='') {
        if(!$this->FP){ $this->ERROR='POP3 pass: Keine Verbindung.';return false; }
        $r=$this->cmd("PASS $pass");if(!$this->is_ok($r)){ $this->ERROR='POP3 pass: '.$r;return false; }
        if(preg_match('/(\d+)/',$r,$m)){ $this->COUNT=-1; }
        return true;
    }
    public function apop($login, $pass) {
        if(!$this->FP){ $this->ERROR='POP3 apop: Keine Verbindung.';return false; }
        if(empty($this->BANNER)){ $this->ERROR='POP3 apop: Kein Banner.';return false; }
        $r=$this->cmd('APOP '.$login.' '.md5($this->BANNER.$pass));
        if(!$this->is_ok($r)){ $this->ERROR='POP3 apop: '.$r;return false; }
        return true;
    }
    public function login($login='', $pass='') {
        if(!$this->FP){ $this->ERROR='POP3 login: Keine Verbindung.';return false; }
        if(!empty($this->BANNER)&&preg_match('/<.+@.+>/',$this->BANNER))return $this->apop($login,$pass);
        return $this->user($login)&&$this->pass($pass);
    }
    public function top($msgNum, $numLines='0') {
        if(!$this->FP||empty($msgNum))return false;
        $r=$this->cmd("TOP $msgNum $numLines");if(!$this->is_ok($r)){ $this->ERROR='POP3 top: '.$r;return false; }
        $out=[];while(($l=fgets($this->FP,$this->BUFFER))!==false){ $l=rtrim($l,"\r\n");if($l==='.')break;$out[]=$l."\r\n"; }
        return $out;
    }
    public function pop_list($msgNum='') {
        if(!$this->FP)return false;
        $r=$this->cmd($msgNum===''?'LIST':"LIST $msgNum");if(!$this->is_ok($r)){ $this->ERROR='POP3 pop_list: '.$r;return false; }
        if($msgNum!=='')return $r;
        $out=[];while(($l=fgets($this->FP,$this->BUFFER))!==false){ $l=rtrim($l);if($l==='.')break;$out[]=$l; }
        return $out;
    }
    public function get($msgNum) {
        if(!$this->FP||empty($msgNum))return false;
        $r=$this->cmd("RETR $msgNum");if(!$this->is_ok($r)){ $this->ERROR='POP3 get: '.$r;return false; }
        $out=[];while(($l=fgets($this->FP,$this->BUFFER))!==false){ $l=rtrim($l,"\r\n");if($l==='.')break;if(str_starts_with($l,'..'))$l=substr($l,1);$out[]=$l."\r\n"; }
        return $out;
    }
    public function last($type='count') { $r=$this->cmd('STAT');if(!$this->is_ok($r))return false;$p=explode(' ',$r);return $type==='count'?(int)$p[1]:(int)$p[2]; }
    public function reset() { $r=$this->cmd('RSET');return $this->is_ok($r); }
    public function delete($msgNum='') { if(!$this->FP||$msgNum==='')return false;return $this->is_ok($this->cmd("DELE $msgNum")); }
    public function quit() { if($this->FP){ $this->cmd('QUIT');fclose($this->FP);$this->FP=''; }return true; }
    public function is_ok($cmd='') { return !empty($cmd)&&strtolower(substr($cmd,0,3))==='+ok'; }
    public function strip_clf($text='') { return empty($text)?$text:str_replace(["\r","\n"],'',$text); }
    public function parse_banner($server_text) {
        return preg_match('/(<.+@.+>)/',(string)$server_text,$m)?$m[1]:'';
    }
}
}

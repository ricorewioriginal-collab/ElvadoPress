<?php
// Ergänzende WordPress-Funktionen (Kern 2, Teil 14): XML-RPC – IXR-Bibliothek (Wert, Nachricht, Anfrage, Fehler, Client, Server) und wp_xmlrpc_server mit einer Auswahl der Methoden.
// Eigenständig umgesetzt; der Client nutzt die HTTP-API der Schicht. Der Server wird nur bei Aufruf von serve() tätig.

if(!class_exists('IXR_Date')){
class IXR_Date {
    public $year;public $month;public $day;public $hour;public $minute;public $second;public $timezone='';
    public function __construct($time) { if(is_numeric($time))$this->parseTimestamp((int)$time);else $this->parseIso((string)$time); }
    public function parseTimestamp($timestamp) { $this->year=gmdate('Y',$timestamp);$this->month=gmdate('m',$timestamp);$this->day=gmdate('d',$timestamp);$this->hour=gmdate('H',$timestamp);$this->minute=gmdate('i',$timestamp);$this->second=gmdate('s',$timestamp);$this->timezone=''; }
    public function parseIso($iso) {
        if(preg_match('/^(\d{4})-?(\d{2})-?(\d{2})T(\d{2}):(\d{2}):(\d{2})(Z|[+-]\d{2}:?\d{2})?$/',$iso,$m)){ [$_,$this->year,$this->month,$this->day,$this->hour,$this->minute,$this->second]=$m;$this->timezone=$m[7]??''; }
    }
    public function getIso() { return $this->year.$this->month.$this->day.'T'.$this->hour.':'.$this->minute.':'.$this->second.$this->timezone; }
    public function getXml() { return '<dateTime.iso8601>'.$this->getIso().'</dateTime.iso8601>'; }
    public function getTimestamp() { return gmmktime((int)$this->hour,(int)$this->minute,(int)$this->second,(int)$this->month,(int)$this->day,(int)$this->year); }
}
}
if(!class_exists('IXR_Base64')){
class IXR_Base64 {
    public $data;
    public function __construct($data) { $this->data=$data; }
    public function getXml() { return '<base64>'.base64_encode($this->data).'</base64>'; }
}
}
if(!class_exists('IXR_Value')){
class IXR_Value {
    public $data;public $type;
    public function __construct($data, $type=false) { $this->data=$data;$this->type=$type?:$this->calculateType(); if($this->type==='struct'){ foreach($this->data as $k=>$v)$this->data[$k]=new IXR_Value($v); } if($this->type==='array'){ for($i=0,$n=count($this->data);$i<$n;$i++)$this->data[$i]=new IXR_Value($this->data[$i]); } }
    public function calculateType() {
        if($this->data===true||$this->data===false)return 'boolean';
        if(is_int($this->data))return 'int';
        if(is_float($this->data))return 'double';
        if($this->data instanceof IXR_Date)return 'date';
        if($this->data instanceof IXR_Base64)return 'base64';
        if(is_object($this->data)){ $this->data=get_object_vars($this->data);return 'struct'; }
        if(!is_array($this->data))return 'string';
        return array_keys($this->data)===range(0,count($this->data)-1)||!$this->data?'array':'struct';
    }
    public function getXml() {
        switch($this->type){
            case 'boolean': return '<boolean>'.($this->data?'1':'0').'</boolean>';
            case 'int': return '<int>'.$this->data.'</int>';
            case 'double': return '<double>'.$this->data.'</double>';
            case 'string': return '<string>'.htmlspecialchars((string)$this->data,ENT_XML1|ENT_COMPAT,'UTF-8').'</string>';
            case 'array': $r='<array><data>'."\n";foreach($this->data as $v)$r.='<value>'.$v->getXml()."</value>\n";return $r.'</data></array>';
            case 'struct': $r='<struct>'."\n";foreach($this->data as $k=>$v)$r.='<member><name>'.htmlspecialchars((string)$k,ENT_XML1|ENT_COMPAT,'UTF-8').'</name><value>'.$v->getXml()."</value></member>\n";return $r.'</struct>';
            case 'date': case 'base64': return $this->data->getXml();
        }
        return false;
    }
    public function isStruct($array) { return array_keys($array)!==range(0,count($array)-1); }
}
}
if(!class_exists('IXR_Error')){
class IXR_Error {
    public $code;public $message;
    public function __construct($code, $message) { $this->code=$code;$this->message=htmlspecialchars((string)$message,ENT_XML1|ENT_COMPAT,'UTF-8'); }
    public function getXml() { return "<methodResponse>\n  <fault>\n    <value>\n      <struct>\n        <member>\n          <name>faultCode</name>\n          <value><int>{$this->code}</int></value>\n        </member>\n        <member>\n          <name>faultString</name>\n          <value><string>{$this->message}</string></value>\n        </member>\n      </struct>\n    </value>\n  </fault>\n</methodResponse>\n"; }
}
}
if(!class_exists('IXR_Request')){
class IXR_Request {
    public $method;public $args;public $xml;
    public function __construct($method, $args) {
        $this->method=$method;$this->args=$args;
        $this->xml="<?xml version=\"1.0\"?>\n<methodCall>\n<methodName>".htmlspecialchars((string)$method,ENT_XML1|ENT_COMPAT,'UTF-8')."</methodName>\n<params>\n";
        foreach($args as $a)$this->xml.='<param><value>'.(new IXR_Value($a))->getXml()."</value></param>\n";
        $this->xml.='</params></methodCall>';
    }
    public function getLength() { return strlen($this->xml); }
    public function getXml() { return $this->xml; }
}
}
if(!class_exists('IXR_Message')){
class IXR_Message {
    public $message=false;public $messageType=false;public $faultCode=false;public $faultString=false;public $methodName='';public $params=[];
    public function __construct($message) { $this->message=(string)$message; }
    public function parse() {
        $prev=libxml_use_internal_errors(true);$x=simplexml_load_string(preg_replace('/^[^<]*/','',trim($this->message)),'SimpleXMLElement',LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($prev);
        if(!$x)return false;
        $root=$x->getName();
        if($root==='methodCall'){ $this->messageType='methodCall';$this->methodName=trim((string)$x->methodName);foreach($x->params->param??[] as $p)$this->params[]=$this->value($p->value);return true; }
        if($root==='methodResponse'){
            if(isset($x->fault)){ $this->messageType='fault';$f=$this->value($x->fault->value);$this->faultCode=$f['faultCode']??0;$this->faultString=$f['faultString']??'';return true; }
            $this->messageType='methodResponse';foreach($x->params->param??[] as $p)$this->params[]=$this->value($p->value);return true;
        }
        return false;
    }
    private function value($v) {
        $c=null;foreach($v->children() as $c)break;
        if($c===null)return trim((string)$v);
        switch($c->getName()){
            case 'int': case 'i4': return (int)trim((string)$c);
            case 'double': return (float)trim((string)$c);
            case 'boolean': return trim((string)$c)==='1';
            case 'string': return (string)$c;
            case 'dateTime.iso8601': return new IXR_Date(trim((string)$c));
            case 'base64': return base64_decode((string)$c);
            case 'array': $o=[];foreach($c->data->value??[] as $e)$o[]=$this->value($e);return $o;
            case 'struct': $o=[];foreach($c->member as $m)$o[(string)$m->name]=$this->value($m->value);return $o;
        }
        return (string)$c;
    }
}
}
if(!class_exists('IXR_Client')){
class IXR_Client {
    public $server;public $port;public $path;public $useragent;public $response;public $message=false;public $debug=false;public $timeout;public $headers=[];public $error=false;
    public function __construct($server, $path=false, $port=80, $timeout=15, $timeout_io=null) {
        if(!$path){ $p=parse_url((string)$server);$this->server=$p['host']??'';$this->port=$p['port']??80;$this->path=$p['path']??'/';if(!empty($p['query']))$this->path.='?'.$p['query'];$this->scheme=$p['scheme']??'http'; }
        else{ $this->server=$server;$this->path=$path;$this->port=$port;$this->scheme='http'; }
        $this->useragent='The Incutio XML-RPC PHP Library';$this->timeout=$timeout;
    }
    protected function endpoint() { return ($this->scheme??'http').'://'.$this->server.($this->port&&!in_array((int)$this->port,[80,443],true)?':'.$this->port:'').$this->path; }
    public function query(...$args) {
        $method=array_shift($args);$req=new IXR_Request($method,$args);
        $r=wp_remote_post($this->endpoint(),['headers'=>array_merge(['Content-Type'=>'text/xml','User-Agent'=>$this->useragent],$this->headers),'body'=>$req->getXml(),'timeout'=>$this->timeout]);
        if(is_wp_error($r)){ $this->error=new IXR_Error(-32300,'transport error: '.$r->get_error_message());return false; }
        if((int)wp_remote_retrieve_response_code($r)!==200){ $this->error=new IXR_Error(-32301,'transport error - HTTP status code was not 200 ('.wp_remote_retrieve_response_code($r).')');return false; }
        $this->message=new IXR_Message(wp_remote_retrieve_body($r));
        if(!$this->message->parse()){ $this->error=new IXR_Error(-32700,'parse error. not well formed');return false; }
        if($this->message->messageType==='fault'){ $this->error=new IXR_Error($this->message->faultCode,$this->message->faultString);return false; }
        return true;
    }
    public function getResponse() { return $this->message?($this->message->params[0]??null):null; }
    public function isError() { return is_object($this->error); }
    public function getErrorCode() { return $this->error->code; }
    public function getErrorMessage() { return $this->error->message; }
}
}
if(!class_exists('WP_HTTP_IXR_Client')){
class WP_HTTP_IXR_Client extends IXR_Client {
    public $scheme;
    public function __construct($server, $path=false, $port=false, $timeout=15) {
        if(!$path){ $u=parse_url((string)$server);if(!$u)return;$this->scheme=$u['scheme']??'http';$this->server=$u['host']??'';$this->path=$u['path']??'/';if(!empty($u['query']))$this->path.='?'.$u['query'];$this->port=$u['port']??($this->scheme==='https'?443:80); }
        else{ $this->scheme='http';$this->server=$server;$this->path=$path;$this->port=$port?:80; }
        $this->useragent='The Incutio XML-RPC PHP Library';$this->timeout=$timeout;
    }
    public function query(...$args) {
        $method=array_shift($args);$request=new IXR_Request($method,$args);$xml=$request->getXml();
        $url=$this->scheme.'://'.$this->server.':'.$this->port.$this->path;
        $a=['headers'=>array_merge(['Content-Type'=>'text/xml'],$this->headers),'user-agent'=>$this->useragent,'body'=>$xml,'timeout'=>$this->timeout];
        $a=apply_filters('xmlrpc_http_args_ixr',$a);   // Platz für Plugins
        $r=wp_remote_post($url,$a);
        if(is_wp_error($r)){ $this->error=new IXR_Error(-32300,$r->get_error_message());return false; }
        if((int)wp_remote_retrieve_response_code($r)!==200){ $this->error=new IXR_Error(-32301,'transport error - HTTP status code was not 200 ('.wp_remote_retrieve_response_code($r).')');return false; }
        $this->message=new IXR_Message(wp_remote_retrieve_body($r));
        if(!$this->message->parse()){ $this->error=new IXR_Error(-32700,'parse error. not well formed');return false; }
        if($this->message->messageType==='fault'){ $this->error=new IXR_Error($this->message->faultCode,$this->message->faultString);return false; }
        return true;
    }
}
}
if(!class_exists('IXR_Server')){
class IXR_Server {
    public $data;public $callbacks=[];public $message;public $capabilities;
    public function __construct($callbacks=false, $data=false, $wait=false) { $this->setCapabilities();if($callbacks)$this->callbacks=$callbacks;$this->setCallbacks();if(!$wait)$this->serve($data); }
    public function serve($data=false) {
        if(!$data){
            if(isset($_SERVER['REQUEST_METHOD'])&&$_SERVER['REQUEST_METHOD']!=='POST'){ if(!headers_sent())header('Content-Type: text/plain');die('XML-RPC server accepts POST requests only.'); }
            $data=file_get_contents('php://input');
        }
        $this->message=new IXR_Message($data);
        if(!$this->message->parse())$this->error(-32700,'parse error. not well formed');
        elseif($this->message->messageType!=='methodCall')$this->error(-32600,'server error. invalid xml-rpc. not conforming to spec. Request must be a methodCall');
        else{ $result=$this->call($this->message->methodName,$this->message->params);
            if(is_a($result,'IXR_Error'))$this->error($result);
            else{ $r=new IXR_Value($result);$this->output("<methodResponse>\n  <params>\n    <param>\n      <value>".$r->getXml()."</value>\n    </param>\n  </params>\n</methodResponse>\n"); } }
    }
    public function call($methodname, $args) {
        if(!$this->hasMethod($methodname))return new IXR_Error(-32601,'server error. requested method '.$methodname.' does not exist.');
        $method=$this->callbacks[$methodname];
        if(count($args)===1)$args=$args[0];
        if(is_string($method)&&str_starts_with($method,'this:')){ $m=substr($method,5);
            if(!method_exists($this,$m))return new IXR_Error(-32601,'server error. requested class method "'.$m.'" does not exist.');
            return $this->$m($args); }
        if(is_array($method)||(is_string($method)&&function_exists($method)))return call_user_func($method,$args);
        return new IXR_Error(-32601,'server error. requested function "'.(is_string($method)?$method:'?').'" does not exist.');
    }
    public function error($error, $message=false) {
        if($message&&!is_object($error))$error=new IXR_Error($error,$message);
        $this->output($error->getXml());
    }
    public function output($xml) {
        $xml='<?xml version="1.0"?>'."\n".$xml;
        if(!headers_sent()){ header('Connection: close');header('Content-Length: '.strlen($xml));header('Content-Type: text/xml');header('Date: '.gmdate('r')); }
        echo $xml;
        if(!defined('ELVADO_WP_TESTING'))exit;   // im Testbetrieb (ELVADO_WP_TESTING) nicht beenden
    }
    public function hasMethod($method) { return in_array($method,array_keys($this->callbacks),true); }
    public function setCapabilities() {
        $this->capabilities=['xmlrpc'=>['specUrl'=>'http://www.xmlrpc.com/spec','specVersion'=>1],'faults_interop'=>['specUrl'=>'http://xmlrpc-epi.sourceforge.net/specs/rfc.fault_codes.php','specVersion'=>20010516],'system.multicall'=>['specUrl'=>'http://www.xmlrpc.com/discuss/msgReader$1208','specVersion'=>1]];
    }
    public function getCapabilities($args) { return $this->capabilities; }
    public function setCallbacks() {
        $this->callbacks['system.getCapabilities']='this:getCapabilities';$this->callbacks['system.listMethods']='this:listMethods';$this->callbacks['system.multicall']='this:multiCall';
    }
    public function listMethods($args) { return array_reverse(array_keys($this->callbacks)); }
    public function multiCall($methodcalls) {
        $return=[];
        foreach((array)$methodcalls as $c){ $m=$c['methodName']??'';$p=$c['params']??[];
            if($m==='system.multicall'){ $r=new IXR_Error(-32600,'Recursive calls to system.multicall are forbidden'); }
            else $r=$this->call($m,$p);
            $return[]=is_a($r,'IXR_Error')?['faultCode'=>$r->code,'faultString'=>$r->message]:[$r]; }
        return $return;
    }
}
}

/* ───────── wp_xmlrpc_server (Auswahl der Methoden) ───────── */
if(!class_exists('wp_xmlrpc_server')){
class wp_xmlrpc_server extends IXR_Server {
    public $methods;public $error;
    public function __construct() {
        $this->methods=['wp.getUsersBlogs'=>'this:wp_getUsersBlogs','wp.getPost'=>'this:wp_getPost','wp.getPosts'=>'this:wp_getPosts','wp.newPost'=>'this:wp_newPost','wp.editPost'=>'this:wp_editPost','wp.deletePost'=>'this:wp_deletePost','wp.getOptions'=>'this:wp_getOptions','blogger.getUsersBlogs'=>'this:blogger_getUsersBlogs','demo.sayHello'=>'this:sayHello','demo.addTwoNumbers'=>'this:addTwoNumbers'];
        $this->initialise_blog_option_info();
        $this->methods=apply_filters('xmlrpc_methods',$this->methods);
    }
    public function serve_request() { parent::__construct($this->methods); }
    public function initialise_blog_option_info() {
        $this->blog_options=['software_name'=>['desc'=>'Software-Name','readonly'=>true,'value'=>'WordPress'],'software_version'=>['desc'=>'Software-Version','readonly'=>true,'value'=>wp_get_wp_version()],'blog_url'=>['desc'=>'Website-Adresse','readonly'=>true,'option'=>'siteurl'],'blog_title'=>['desc'=>'Titel der Website','readonly'=>false,'option'=>'blogname'],'blog_tagline'=>['desc'=>'Untertitel','readonly'=>false,'option'=>'blogdescription'],'time_zone'=>['desc'=>'Zeitzone','readonly'=>false,'option'=>'gmt_offset'],'date_format'=>['desc'=>'Datumsformat','readonly'=>false,'option'=>'date_format'],'time_format'=>['desc'=>'Zeitformat','readonly'=>false,'option'=>'time_format']];
        $this->blog_options=apply_filters('xmlrpc_blog_options',$this->blog_options);
    }
    public $blog_options=[];
    public function escape(&$data) { if(!is_array($data))return wp_slash($data);foreach($data as &$v){ if(is_array($v))$this->escape($v);elseif(!is_object($v))$v=wp_slash($v); }return $data; }
    public function login($username, $password) {
        if(!(bool)get_option('enable_xmlrpc',true)||(defined('XMLRPC_DISABLED')&&XMLRPC_DISABLED)){ $this->error=new IXR_Error(405,'XML-RPC-Dienste sind auf dieser Website deaktiviert.');return false; }
        $user=wp_authenticate($username,$password);
        if(is_wp_error($user)){ $this->error=new IXR_Error(403,'Benutzername oder Passwort ist falsch.');return false; }
        wp_set_current_user($user->ID);return $user;
    }
    public function login_pass_ok($username, $password) { return (bool)$this->login($username,$password); }
    public function sayHello($args) { return 'Hallo!'; }
    public function addTwoNumbers($args) { return (int)($args[0]??0)+(int)($args[1]??0); }
    public function blogger_getUsersBlogs($args) { return $this->wp_getUsersBlogs([$args[1]??'',$args[2]??'']); }
    public function wp_getUsersBlogs($args) {
        $this->escape($args);if(!$u=$this->login($args[0]??'',$args[1]??''))return $this->error;
        return [['isAdmin'=>current_user_can('manage_options'),'url'=>home_url('/'),'blogid'=>'1','blogName'=>get_option('blogname'),'xmlrpc'=>site_url('xmlrpc.php','rpc')]];
    }
    protected function prepare_post($post) {
        return ['post_id'=>(string)$post->ID,'post_title'=>$post->post_title,'post_date'=>new IXR_Date(mysql2date('U',$post->post_date_gmt,false)),'post_date_gmt'=>new IXR_Date(mysql2date('U',$post->post_date_gmt,false)),'post_modified'=>new IXR_Date(mysql2date('U',$post->post_modified_gmt,false)),'post_status'=>$post->post_status,'post_type'=>$post->post_type,'post_name'=>$post->post_name,'post_author'=>(string)$post->post_author,'post_password'=>$post->post_password,'post_excerpt'=>$post->post_excerpt,'post_content'=>$post->post_content,'link'=>get_permalink($post->ID),'guid'=>$post->guid,'comment_status'=>$post->comment_status,'ping_status'=>$post->ping_status,'post_parent'=>(string)$post->post_parent,'sticky'=>is_sticky($post->ID)];
    }
    public function wp_getPost($args) {
        $this->escape($args);if(!$this->login($args[1]??'',$args[2]??''))return $this->error;
        $p=get_post((int)($args[3]??0));if(!$p)return new IXR_Error(404,'Ungültige Beitrags-ID.');
        if(!current_user_can('edit_post',$p->ID))return new IXR_Error(401,'Sie dürfen diesen Beitrag nicht bearbeiten.');
        return $this->prepare_post($p);
    }
    public function wp_getPosts($args) {
        $this->escape($args);if(!$this->login($args[1]??'',$args[2]??''))return $this->error;
        $f=(array)($args[3]??[]);$type=$f['post_type']??'post';
        if(!current_user_can(get_post_type_object($type)->cap->edit_posts??'edit_posts'))return new IXR_Error(401,'Sie dürfen diese Beiträge nicht bearbeiten.');
        $q=['post_type'=>$type,'post_status'=>$f['post_status']??'any','posts_per_page'=>(int)($f['number']??10),'offset'=>(int)($f['offset']??0),'orderby'=>$f['orderby']??'date','order'=>$f['order']??'DESC'];
        if(!empty($f['s']))$q['s']=$f['s'];
        return array_map([$this,'prepare_post'],get_posts($q));
    }
    public function wp_newPost($args) {
        $this->escape($args);if(!$this->login($args[1]??'',$args[2]??''))return $this->error;
        $c=(array)($args[3]??[]);$type=$c['post_type']??'post';
        if(!current_user_can(get_post_type_object($type)->cap->publish_posts??'publish_posts'))return new IXR_Error(401,'Sie dürfen hier keine Beiträge veröffentlichen.');
        $d=['post_type'=>$type,'post_status'=>$c['post_status']??'draft','post_title'=>$c['post_title']??'','post_content'=>$c['post_content']??'','post_excerpt'=>$c['post_excerpt']??'','post_author'=>get_current_user_id()];
        $id=wp_insert_post($d,true);
        return is_wp_error($id)?new IXR_Error(500,$id->get_error_message()):(string)$id;
    }
    public function wp_editPost($args) {
        $this->escape($args);if(!$this->login($args[1]??'',$args[2]??''))return $this->error;
        $id=(int)($args[3]??0);if(!get_post($id))return new IXR_Error(404,'Ungültige Beitrags-ID.');
        if(!current_user_can('edit_post',$id))return new IXR_Error(401,'Sie dürfen diesen Beitrag nicht bearbeiten.');
        $c=(array)($args[4]??[]);$d=['ID'=>$id];
        foreach(['post_title','post_content','post_excerpt','post_status','post_name'] as $k)if(isset($c[$k]))$d[$k]=$c[$k];
        $r=wp_update_post($d,true);return is_wp_error($r)?new IXR_Error(500,$r->get_error_message()):true;
    }
    public function wp_deletePost($args) {
        $this->escape($args);if(!$this->login($args[1]??'',$args[2]??''))return $this->error;
        $id=(int)($args[3]??0);if(!get_post($id))return new IXR_Error(404,'Ungültige Beitrags-ID.');
        if(!current_user_can('delete_post',$id))return new IXR_Error(401,'Sie dürfen diesen Beitrag nicht löschen.');
        return (bool)wp_delete_post($id,true);
    }
    public function wp_getOptions($args) {
        $this->escape($args);if(!$this->login($args[1]??'',$args[2]??''))return $this->error;
        $want=(array)($args[3]??[]);$out=[];
        foreach($this->blog_options as $k=>$o){ if($want&&!in_array($k,$want,true))continue;
            if(isset($o['option'])&&!current_user_can('manage_options')&&!in_array($k,['blog_url'],true))continue;
            $out[$k]=['desc'=>$o['desc'],'readonly'=>$o['readonly'],'value'=>isset($o['option'])?get_option($o['option']):$o['value']]; }
        return $out;
    }
}
}

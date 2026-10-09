<?php
// Ergänzende WordPress-Funktionen (Kern 2, Teil 2): ältere Feed-Schicht (MagpieRSS, Atom-Parser, Snoopy, Services_JSON, Feed-Zwischenspeicher).
// Eigenständig umgesetzt; Netzwerkzugriffe laufen über die HTTP-API der Schicht und nur beim Aufruf.

/* ───────── HTTP-Statushilfen und Zeit (rss.php) ───────── */
if(!function_exists('is_info')){ function is_info($sc) { return $sc>=100&&$sc<200; } }
if(!function_exists('is_success')){ function is_success($sc) { return $sc>=200&&$sc<300; } }
if(!function_exists('is_redirect')){ function is_redirect($sc) { return $sc>=300&&$sc<400; } }
if(!function_exists('is_error')){ function is_error($sc) { return $sc>=400&&$sc<600; } }   // HTTP-Status (nicht is_wp_error)
if(!function_exists('is_client_error')){ function is_client_error($sc) { return $sc>=400&&$sc<500; } }
if(!function_exists('is_server_error')){ function is_server_error($sc) { return $sc>=500&&$sc<600; } }
if(!function_exists('init')){ function init() {   // Magpie-Standardwerte als Konstanten
    foreach(['MAGPIE_INITALIZED'=>1,'MAGPIE_CACHE_ON'=>1,'MAGPIE_CACHE_AGE'=>3600,'MAGPIE_CACHE_FRESH_ONLY'=>0,'MAGPIE_DEBUG'=>0,'MAGPIE_CACHE_DIR'=>'/tmp/magpie_cache'] as $k=>$v)if(!defined($k))define($k,$v);
} }
if(!function_exists('parse_w3cdtf')){ function parse_w3cdtf($date_str) {   // W3C-Datum (ISO 8601) in Zeitstempel, -1 bei Fehler
    if(!preg_match('~^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2}))?(?:\.\d+)?(Z|[+-]\d{2}:\d{2})$~',trim((string)$date_str),$m))return -1;
    $ts=gmmktime((int)$m[4],(int)$m[5],(int)($m[6]?:0),(int)$m[2],(int)$m[3],(int)$m[1]);
    if($m[7]!=='Z'){ $sign=$m[7][0]==='-'?-1:1;$ts-=$sign*((int)substr($m[7],1,2)*3600+(int)substr($m[7],4,2)*60); }
    return $ts;
} }

/* ───────── MagpieRSS und Zwischenspeicher ───────── */
if(!class_exists('MagpieRSS')){
class MagpieRSS {
    public $parser;public $current_item=[];public $items=[];public $channel=[];public $textinput=[];public $image=[];public $feed_type='';public $feed_version='';public $encoding='';public $_source_encoding='';
    public $ERROR='';public $WARNING='';public $_CONTENT_CONSTRUCTS=['content','summary','info','title','tagline','copyright'];public $_KNOWN_ENCODINGS=['UTF-8','US-ASCII','ISO-8859-1'];
    public function __construct($source, $output_encoding='UTF-8', $input_encoding=null, $detect_encoding=true) {
        $this->encoding=$output_encoding;$source=(string)$source;
        if(trim($source)===''){ $this->ERROR='Leere Feed-Quelle.';return; }
        $prev=libxml_use_internal_errors(true);
        $x=simplexml_load_string($source,'SimpleXMLElement',LIBXML_NOCDATA|LIBXML_NONET);
        libxml_clear_errors();libxml_use_internal_errors($prev);
        if(!$x){ $this->ERROR='Der Feed ist kein gültiges XML.';return; }
        $root=strtolower($x->getName());
        if($root==='rss'){ $this->feed_type='rss';$this->feed_version=(string)($x['version']??'');$this->parse_rss($x->channel); }
        elseif($root==='rdf'){ $this->feed_type='rss';$this->feed_version='1.0';$this->parse_rss($x,$x->channel); }
        elseif($root==='feed'){ $this->feed_type='atom';$this->feed_version=(string)($x['version']??'1.0');$this->parse_atom($x); }
        else $this->ERROR='Unbekanntes Feed-Format.';
    }
    private function flat($node): array {
        $o=[];$ns=['dc'=>'http://purl.org/dc/elements/1.1/','content'=>'http://purl.org/rss/1.0/modules/content/'];
        foreach($node->children() as $k=>$v)$o[strtolower($k)]=trim((string)$v);
        foreach($ns as $p=>$u)foreach($node->children($u) as $k=>$v)$o[$p.':'.strtolower($k)]=trim((string)$v);
        return $o;
    }
    private function parse_rss($channel, $rdf=null): void {
        $c=$this->flat($channel);$this->channel=$c;
        if(isset($channel->image))$this->image=$this->flat($channel->image);
        if(isset($channel->textInput))$this->textinput=$this->flat($channel->textInput);
        $nodes=$rdf?$rdf->item:$channel->item;
        foreach($nodes as $it){ $i=$this->flat($it);
            if(isset($i['pubdate']))$i['date_timestamp']=strtotime($i['pubdate']);elseif(isset($i['dc:date']))$i['date_timestamp']=parse_w3cdtf($i['dc:date']);
            if(isset($i['description'])&&!isset($i['summary']))$i['summary']=$i['description'];
            $this->items[]=$i; }
    }
    private function parse_atom($feed): void {
        $this->channel=['title'=>trim((string)$feed->title),'tagline'=>trim((string)$feed->subtitle),'modified'=>trim((string)$feed->updated)];
        $l=$feed->link;foreach($l as $lk)if(!isset($lk['rel'])||(string)$lk['rel']==='alternate'){ $this->channel['link']=(string)$lk['href'];break; }
        foreach($feed->entry as $e){
            $i=['title'=>trim((string)$e->title),'id'=>trim((string)$e->id),'summary'=>trim((string)$e->summary),'content'=>trim((string)$e->content),'issued'=>trim((string)($e->published?:$e->updated)),'modified'=>trim((string)$e->updated)];
            foreach($e->link as $lk)if(!isset($lk['rel'])||(string)$lk['rel']==='alternate'){ $i['link']=(string)$lk['href'];break; }
            if($i['issued']!=='')$i['date_timestamp']=parse_w3cdtf($i['issued']);
            if($i['summary']==='')$i['summary']=$i['content'];
            $this->items[]=$i; }
    }
}
}
if(!class_exists('RSSCache')){
class RSSCache {
    public $BASE_CACHE='';public $MAX_AGE=43200;public $ERROR='';
    public function __construct($base='', $age='') { $this->BASE_CACHE=$base;if($age!=='')$this->MAX_AGE=(int)$age; }
    public function serialize($rss) { return serialize($rss); }
    public function unserialize($data) { return unserialize($data); }
    public function file_name($url) { return md5((string)$url); }
    public function set($url, $rss) { return set_transient('elvado_magpie_'.$this->file_name($url),['t'=>time(),'d'=>$this->serialize($rss)],$this->MAX_AGE*2)?$this->file_name($url):0; }
    public function get($url) { $c=get_transient('elvado_magpie_'.$this->file_name($url));return is_array($c)?$this->unserialize($c['d']):0; }
    public function check_cache($url) {
        $c=get_transient('elvado_magpie_'.$this->file_name($url));
        if(!is_array($c))return 'MISS';
        return (time()-(int)$c['t'])<$this->MAX_AGE?'HIT':'STALE';
    }
    public function cache_age($url) { $c=get_transient('elvado_magpie_'.$this->file_name($url));return is_array($c)?time()-(int)$c['t']:false; }
    public function error($errormsg, $lvl=E_USER_WARNING) { $this->ERROR=$errormsg; }
}
}
if(!function_exists('_response_to_rss')){ function _response_to_rss($resp) {
    $body=is_object($resp)?($resp->results??''):(is_array($resp)?($resp['body']??''):'');
    $rss=new MagpieRSS($body);
    if(is_object($resp)&&isset($resp->headers)&&is_array($resp->headers)){ foreach($resp->headers as $h){ if(preg_match('/^(etag|last-modified):\s*(.*)$/i',$h,$m))$rss->{strtolower($m[1])==='etag'?'etag':'last_modified'}=trim($m[2]); } }
    return $rss;
} }
if(!function_exists('_fetch_remote_file')){ function _fetch_remote_file($url, $headers='') {
    $r=wp_safe_remote_request($url,['headers'=>$headers?:[],'timeout'=>10]);
    $o=new stdClass();
    if(is_wp_error($r)){ $o->status=500;$o->response_code=0;$o->error=$r->get_error_message();$o->results='';$o->headers=[];return $o; }
    $o->status=(int)wp_remote_retrieve_response_code($r);$o->response_code=$o->status;$o->results=wp_remote_retrieve_body($r);$o->headers=[];
    foreach((array)wp_remote_retrieve_headers($r) as $k=>$v)$o->headers[]=$k.': '.(is_array($v)?implode(', ',$v):$v);
    return $o;
} }
if(!function_exists('fetch_rss')){ function fetch_rss($url) {
    init();
    if(empty($url))return false;
    $cache=new RSSCache('',MAGPIE_CACHE_AGE);$st=$cache->check_cache($url);
    if($st==='HIT')return $cache->get($url);
    $resp=_fetch_remote_file($url);
    if(is_success($resp->status)){ $rss=_response_to_rss($resp);if($rss&&!$rss->ERROR){ $cache->set($url,$rss);return $rss; } }
    return $st==='STALE'?$cache->get($url):false;
} }
if(!function_exists('get_rss')){ function get_rss($uri, $num=5) {   // Liste ausgeben; true bei Erfolg
    $rss=fetch_rss($uri);if(!$rss)return false;
    $items=array_slice($rss->items,0,(int)$num);
    echo "<ul>\n";foreach($items as $i)echo '<li><a href="'.esc_url($i['link']??'').'" title="'.esc_attr($i['description']??'').'">'.esc_html($i['title']??'')."</a></li>\n";echo "</ul>\n";
    return true;
} }
if(!function_exists('wp_rss')){ function wp_rss($url, $num_items=-1) {
    $rss=fetch_rss($url);
    if(!$rss){ echo '<p><strong>Der Feed konnte nicht geladen werden.</strong></p>';return; }
    $items=$num_items>0?array_slice($rss->items,0,(int)$num_items):$rss->items;
    echo '<ul>';foreach($items as $i)echo '<li><a class="rsswidget" href="'.esc_url($i['link']??'').'" title="'.esc_attr($i['description']??'').'">'.esc_html($i['title']??'').'</a></li>';echo '</ul>';
} }

/* ───────── Atom ───────── */
if(!class_exists('AtomEntry')){
class AtomEntry { public $links=[];public $categories=[];public $title='';public $id='';public $updated='';public $published='';public $summary='';public $content='';public $author=null; }
}
if(!class_exists('AtomFeed')){
class AtomFeed { public $links=[];public $categories=[];public $entries=[];public $title='';public $id='';public $updated='';public $subtitle=''; }
}
if(!class_exists('AtomParser')){
class AtomParser {
    public $NS='http://www.w3.org/2005/Atom';public $ATOM_CONTENT_ELEMENTS=['content','summary','title','subtitle','rights'];public $ATOM_SIMPLE_ELEMENTS=['id','updated','published','draft'];
    public $debug=false;public $depth=0;public $indent=2;public $in_content=false;public $ns_contexts=[];public $ns_decls=[];public $content_ns_decls=[];public $content_ns_contexts=[];
    public $is_xhtml=false;public $is_html=false;public $is_text=true;public $skipped_div=false;public $FILE='php://input';public $feed;public $current;public $error=null;
    public function __construct() { $this->feed=new AtomFeed();$this->current=null; }
    /** Liest $FILE (oder den übergebenen XML-Text) und füllt $feed; false bei ungültigem XML. */
    public function parse($xml=null) {
        $src=$xml!==null?(string)$xml:(string)@file_get_contents($this->FILE);
        $prev=libxml_use_internal_errors(true);$x=simplexml_load_string($src,'SimpleXMLElement',LIBXML_NOCDATA|LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($prev);
        if(!$x||strtolower($x->getName())!=='feed'){ $this->error='Kein gültiger Atom-Feed.';return false; }
        $f=$this->feed=new AtomFeed();
        foreach(['title','id','updated','subtitle'] as $k)$f->$k=trim((string)$x->$k);
        foreach($x->link as $l)$f->links[]=['href'=>(string)$l['href'],'rel'=>(string)($l['rel']??'alternate')];
        foreach($x->category as $c)$f->categories[]=(string)$c['term'];
        foreach($x->entry as $e){ $en=new AtomEntry();
            foreach(['title','id','updated','published','summary','content'] as $k)$en->$k=trim((string)$e->$k);
            foreach($e->link as $l)$en->links[]=['href'=>(string)$l['href'],'rel'=>(string)($l['rel']??'alternate')];
            foreach($e->category as $c)$en->categories[]=(string)$c['term'];
            if(isset($e->author))$en->author=trim((string)$e->author->name);
            $f->entries[]=$en; }
        return true;
    }
}
}

/* ───────── Snoopy (einfacher HTTP-Client, nur Bibliotheksschnittstelle) ───────── */
if(!class_exists('Snoopy')){
class Snoopy {
    public $scheme='http';public $host='localhost';public $port=80;public $proxy_host='';public $proxy_port='';public $proxy_user='';public $proxy_pass='';public $agent='Snoopy v1.2.4';public $referer='';public $cookies=[];public $rawheaders=[];public $maxredirs=5;public $lastredirectaddr='';public $offsiteok=true;public $maxframes=0;public $expandlinks=true;public $passcookies=true;
    public $user='';public $pass='';public $accept='image/gif, image/x-xbitmap, image/jpeg, image/pjpeg, */*';public $results='';public $error='';public $response_code='';public $headers=[];public $maxlength=500000;public $read_timeout=0;public $timed_out=false;public $status=0;public $temp_dir='/tmp';public $curl_path=false;
    public $_httpmethod='GET';public $_submit_method='POST';public $_submit_type='application/x-www-form-urlencoded';
    private function request(string $method, string $uri, $body=null, $type=null): bool {
        $h=['User-Agent'=>$this->agent,'Accept'=>$this->accept]+($this->referer!==''?['Referer'=>$this->referer]:[])+$this->rawheaders;
        if($this->user!=='')$h['Authorization']='Basic '.base64_encode($this->user.':'.$this->pass);
        if($type)$h['Content-Type']=$type;
        if($this->cookies){ $c=[];foreach($this->cookies as $k=>$v)$c[]=$k.'='.$v;$h['Cookie']=implode('; ',$c); }
        $r=wp_safe_remote_request($uri,['method'=>$method,'headers'=>$h,'body'=>$body,'redirection'=>$this->maxredirs,'timeout'=>$this->read_timeout?:10]);
        if(is_wp_error($r)){ $this->error=$r->get_error_message();$this->status=0;return false; }
        $this->status=$this->response_code=(int)wp_remote_retrieve_response_code($r);$this->results=wp_remote_retrieve_body($r);
        $this->headers=[];foreach((array)wp_remote_retrieve_headers($r) as $k=>$v)$this->headers[]=$k.': '.(is_array($v)?implode(', ',$v):$v);
        return true;
    }
    public function fetch($URI) { return $this->request('GET',$URI); }
    public function submit($URI, $formvars='', $formfiles='') { return $this->request($this->_submit_method,$URI,is_array($formvars)?http_build_query($formvars):$formvars,$this->_submit_type); }
    public function fetchtext($URI) { if($this->fetch($URI)){ $this->results=$this->striptext($this->results);return true; }return false; }
    public function submittext($URI, $formvars='', $formfiles='') { if($this->submit($URI,$formvars,$formfiles)){ $this->results=$this->striptext($this->results);return true; }return false; }
    public function fetchlinks($URI) { if($this->fetch($URI)){ $this->results=$this->striplinks($this->results);return true; }return false; }
    public function submitlinks($URI, $formvars='', $formfiles='') { if($this->submit($URI,$formvars,$formfiles)){ $this->results=$this->striplinks($this->results);return true; }return false; }
    public function set_submit_normal() { $this->_submit_type='application/x-www-form-urlencoded'; }
    public function set_submit_multipart() { $this->_submit_type='multipart/form-data'; }
    public function striptext($document) { return trim(html_entity_decode(strip_tags(preg_replace('~<(script|style)\b.*?</\1>~is',' ',(string)$document)),ENT_QUOTES,'UTF-8')); }
    public function striplinks($document) { preg_match_all('~<a\s[^>]*href\s*=\s*(["\']?)([^"\'\s>]+)\1~i',(string)$document,$m);return $m[2]; }
}
}

/* ───────── Services_JSON (alte JSON-Klasse, nutzt die eingebauten Funktionen) ───────── */
foreach(['SERVICES_JSON_SLICE'=>1,'SERVICES_JSON_IN_STR'=>2,'SERVICES_JSON_IN_ARR'=>3,'SERVICES_JSON_IN_OBJ'=>4,'SERVICES_JSON_IN_CMT'=>5,'SERVICES_JSON_LOOSE_TYPE'=>16,'SERVICES_JSON_SUPPRESS_ERRORS'=>32,'SERVICES_JSON_USE_TO_JSON'=>64] as $__k=>$__v)if(!defined($__k))define($__k,$__v);
unset($__k,$__v);
if(!class_exists('Services_JSON')){
class Services_JSON {
    public $use=0;
    public function __construct($use=0) { $this->use=(int)$use; }
    public function encode($var) { return wp_json_encode($var); }
    public function encodeUnsafe($var) { return wp_json_encode($var); }
    public function decode($str) { return json_decode((string)$str,(bool)($this->use&SERVICES_JSON_LOOSE_TYPE)); }
    public function decodeUnsafe($str) { return $this->decode($str); }
    public function reduce_string($str) { return trim(preg_replace(['#^\s*//(.+)$#m','#^\s*/\*(.+)\*/#Us','#/\*(.+)\*/\s*$#Us'],'',(string)$str)); }
    public function name_value($name, $value) { return $this->encode((string)$name).':'.$this->encode($value); }
    public function utf162utf8($utf16) { return mb_convert_encoding((string)$utf16,'UTF-8','UTF-16BE'); }
    public function utf82utf16($utf8) { return mb_convert_encoding((string)$utf8,'UTF-16BE','UTF-8'); }
    public function isError($data, $code=null) { return $data instanceof WP_Error; }
}
}

/* ───────── Feed-Zwischenspeicher (SimplePie-Anbindung) ───────── */
if(!class_exists('WP_Feed_Cache_Transient')){
class WP_Feed_Cache_Transient {
    public $name;public $mod_name;public $lifetime=43200;
    public function __construct($location, $filename, $extension) {
        $this->name='feed_'.$filename;$this->mod_name='feed_mod_'.$filename;
        $this->lifetime=(int)apply_filters('wp_feed_cache_transient_lifetime',$this->lifetime,$filename);
    }
    public function save($data) { set_transient($this->name,$data,$this->lifetime);set_transient($this->mod_name,time(),$this->lifetime);return true; }
    public function load() { return get_transient($this->name); }
    public function mtime() { return get_transient($this->mod_name); }
    public function touch() { return set_transient($this->mod_name,time(),$this->lifetime); }
    public function unlink() { delete_transient($this->name);delete_transient($this->mod_name);return true; }
}
}
if(!class_exists('WP_Feed_Cache')){
class WP_Feed_Cache {
    public function create($location, $filename, $extension) { return new WP_Feed_Cache_Transient($location,$filename,$extension); }
}
}
if(!class_exists('WP_SimplePie_File')){
class WP_SimplePie_File extends stdClass {
    public $url;public $useragent;public $success=true;public $headers=[];public $body='';public $status_code=0;public $redirects=0;public $error;public $method=1;
    public function __construct($url, $timeout=10, $redirects=5, $headers=null, $useragent=null, $force_fsockopen=false, $curl_options=[]) {
        $this->url=$url;$this->timeout=$timeout;$this->redirects=$redirects;$this->headers=(array)$headers;$this->useragent=$useragent;
        $args=['timeout'=>$timeout,'redirection'=>$redirects,'headers'=>(array)$headers];if($useragent)$args['user-agent']=$useragent;
        $res=wp_safe_remote_request($url,$args);
        if(is_wp_error($res)){ $this->error='WP HTTP Error: '.$res->get_error_message();$this->success=false;return; }
        $this->headers=wp_remote_retrieve_headers($res);$this->body=wp_remote_retrieve_body($res);$this->status_code=(int)wp_remote_retrieve_response_code($res);
    }
    public function headers() { return $this->headers; }
    public function body() { return $this->body; }
    public function status_code() { return $this->status_code; }
}
}
if(!class_exists('WP_SimplePie_Sanitize_KSES')){
class WP_SimplePie_Sanitize_KSES {
    public function sanitize($data, $type, $base='') {
        $data=trim((string)$data);
        if($type&2)$data=wp_kses_post($data);   // HTML-Inhalt: nur erlaubte Tags
        return $data;
    }
}
}

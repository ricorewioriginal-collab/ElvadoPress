<?php
// Ergänzende WordPress-Funktionen (Kern 2, Teil 8): Kernklassen – WP_Hook, WP_Object_Cache, WP_Dependencies, WP, WP_Site, Listen-/Token-Hilfen, Plugin-Abhängigkeiten.
// Eigenständig umgesetzt. Die Klassen sind unabhängig von den Arrays der Schicht (z. B. $wp_filter) lauffähig; nichts wird beim Laden ausgeführt.

if(!class_exists('WP_Exception')){ class WP_Exception extends Exception {} }

/* ───────── WP_Hook ───────── */
if(!class_exists('WP_Hook')){
#[AllowDynamicProperties]
class WP_Hook implements Iterator, ArrayAccess {
    public $callbacks=[];protected $priorities=[];private $iterations=[];private $current_priority=[];private $nesting_level=0;private $doing_action=false;
    public function add_filter($hook_name, $callback, $priority, $accepted_args) {
        $idx=_wp_filter_build_unique_id($hook_name,$callback,$priority);$priority=(int)$priority;$existed=isset($this->callbacks[$priority]);
        $this->callbacks[$priority][$idx]=['function'=>$callback,'accepted_args'=>(int)$accepted_args];
        if(!$existed&&count($this->callbacks)>1)ksort($this->callbacks,SORT_NUMERIC);
        $this->priorities=array_keys($this->callbacks);
    }
    public function remove_filter($hook_name, $callback, $priority) {
        $idx=_wp_filter_build_unique_id($hook_name,$callback,$priority);$priority=(int)$priority;
        if(!isset($this->callbacks[$priority][$idx]))return false;
        unset($this->callbacks[$priority][$idx]);
        if(!$this->callbacks[$priority]){ unset($this->callbacks[$priority]);$this->priorities=array_keys($this->callbacks); }
        return true;
    }
    public function has_filter($hook_name='', $callback=false) {
        if($callback===false)return $this->has_filters();
        $idx=_wp_filter_build_unique_id($hook_name,$callback,false);
        foreach($this->callbacks as $p=>$cbs)if(isset($cbs[$idx]))return $p;
        return false;
    }
    public function has_filters() { foreach($this->callbacks as $cbs)if($cbs)return true;return false; }
    public function remove_all_filters($priority=false) {
        if(!$this->callbacks)return;
        if($priority===false)$this->callbacks=[];elseif(isset($this->callbacks[$priority]))unset($this->callbacks[$priority]);
        $this->priorities=array_keys($this->callbacks);
    }
    public function apply_filters($value, $args) {
        if(!$this->callbacks)return $value;
        $lvl=$this->nesting_level++;$n=count($args);$last=null;
        try{
            while(true){
                $next=null;foreach($this->priorities as $p)if($last===null||$p>$last){ $next=$p;break; }
                if($next===null)break;
                $last=$this->current_priority[$lvl]=$next;$seen=[];
                while(true){
                    $id=null;foreach($this->callbacks[$next]??[] as $k=>$_)if(!isset($seen[$k])){ $id=$k;break; }
                    if($id===null)break;
                    $seen[$id]=1;$cb=$this->callbacks[$next][$id];
                    if(!$this->doing_action)$args[0]=$value;
                    $c=$cb['accepted_args'];
                    $value=$c==0?call_user_func_array($cb['function'],[]):($c>=$n?call_user_func_array($cb['function'],$args):call_user_func_array($cb['function'],array_slice($args,0,$c)));
                }
            }
        } finally { unset($this->current_priority[$lvl]);$this->nesting_level--; }
        return $value;
    }
    public function do_action($args) { $this->doing_action=true;$this->apply_filters('',$args);if(!$this->nesting_level)$this->doing_action=false; }
    public function do_all_hook(&$args) {
        $lvl=$this->nesting_level++;
        foreach($this->callbacks as $cbs)foreach($cbs as $cb)call_user_func_array($cb['function'],$args);
        $this->nesting_level=$lvl;
    }
    public function current_priority() { return $this->current_priority?end($this->current_priority):false; }
    public static function build_preinitialized_hooks($filters) {
        $out=[];
        foreach((array)$filters as $tag=>$groups){
            if($groups instanceof WP_Hook){ $out[$tag]=$groups;continue; }
            $h=new WP_Hook();
            foreach((array)$groups as $prio=>$cbs)foreach((array)$cbs as $cb)$h->add_filter($tag,$cb['function'],$prio,$cb['accepted_args']);
            $out[$tag]=$h;
        }
        return $out;
    }
    public function offsetExists($offset): bool { return isset($this->callbacks[$offset]); }
    #[\ReturnTypeWillChange] public function offsetGet($offset) { return $this->callbacks[$offset]??null; }
    public function offsetSet($offset, $value): void { if($offset===null)$this->callbacks[]=$value;else $this->callbacks[$offset]=$value; }
    public function offsetUnset($offset): void { unset($this->callbacks[$offset]); }
    #[\ReturnTypeWillChange] public function current() { return current($this->callbacks); }
    #[\ReturnTypeWillChange] public function next() { return next($this->callbacks); }
    #[\ReturnTypeWillChange] public function key() { return key($this->callbacks); }
    public function valid(): bool { return key($this->callbacks)!==null; }
    public function rewind(): void { reset($this->callbacks); }
}
}

/* ───────── WP_Object_Cache ───────── */
if(!class_exists('WP_Object_Cache')){
#[AllowDynamicProperties]
class WP_Object_Cache {
    private $cache=[];private $global_groups=[];private $non_persistent=[];private $blog_prefix='';public $cache_hits=0;public $cache_misses=0;
    public function __construct() { $this->blog_prefix=''; }
    protected function key($key, $group) { $g=$group===''||$group===null?'default':(string)$group;return [$g,(in_array($g,$this->global_groups,true)?'':$this->blog_prefix).$key]; }
    protected function valid_key($key) { return (is_string($key)&&$key!=='')||(is_int($key)); }
    public function add_global_groups($groups) { $this->global_groups=array_unique(array_merge($this->global_groups,(array)$groups)); }
    public function add_non_persistent_groups($groups) { $this->non_persistent=array_unique(array_merge($this->non_persistent,(array)$groups)); }
    public function switch_to_blog($blog_id) { $this->blog_prefix=''; }
    public function exists($key, $group='default') { [$g,$k]=$this->key($key,$group);return isset($this->cache[$g])&&array_key_exists($k,$this->cache[$g]); }
    public function add($key, $data, $group='default', $expire=0) { if(!$this->valid_key($key)||$this->exists($key,$group))return false;return $this->set($key,$data,$group,$expire); }
    public function add_multiple(array $data, $group='', $expire=0) { $r=[];foreach($data as $k=>$v)$r[$k]=$this->add($k,$v,$group,$expire);return $r; }
    public function replace($key, $data, $group='default', $expire=0) { return $this->exists($key,$group)?$this->set($key,$data,$group,$expire):false; }
    public function set($key, $data, $group='default', $expire=0) {
        if(!$this->valid_key($key))return false;[$g,$k]=$this->key($key,$group);
        $this->cache[$g][$k]=is_object($data)?clone $data:$data;return true;
    }
    public function set_multiple(array $data, $group='', $expire=0) { $r=[];foreach($data as $k=>$v)$r[$k]=$this->set($k,$v,$group,$expire);return $r; }
    public function get($key, $group='default', $force=false, &$found=null) {
        if(!$this->valid_key($key)){ $found=false;return false; }
        if($this->exists($key,$group)){ [$g,$k]=$this->key($key,$group);$found=true;$this->cache_hits++;$v=$this->cache[$g][$k];return is_object($v)?clone $v:$v; }
        $found=false;$this->cache_misses++;return false;
    }
    public function get_multiple($keys, $group='default', $force=false) { $r=[];foreach($keys as $k)$r[$k]=$this->get($k,$group,$force);return $r; }
    public function delete($key, $group='default', $deprecated=false) { if(!$this->valid_key($key)||!$this->exists($key,$group))return false;[$g,$k]=$this->key($key,$group);unset($this->cache[$g][$k]);return true; }
    public function delete_multiple(array $keys, $group='') { $r=[];foreach($keys as $k)$r[$k]=$this->delete($k,$group);return $r; }
    public function incr($key, $offset=1, $group='default') { if(!$this->exists($key,$group))return false;[$g,$k]=$this->key($key,$group);$v=max(0,(int)$this->cache[$g][$k]+(int)$offset);$this->cache[$g][$k]=$v;return $v; }
    public function decr($key, $offset=1, $group='default') { return $this->incr($key,-(int)$offset,$group); }
    public function flush() { $this->cache=[];return true; }
    public function flush_runtime() { return $this->flush(); }
    public function flush_group($group) { unset($this->cache[$group]);return true; }
    public function stats() { echo '<p><strong>Treffer:</strong> '.(int)$this->cache_hits.'<br /><strong>Fehlgriffe:</strong> '.(int)$this->cache_misses.'</p>'; }
    public function __get($name) { return $name==='cache'?$this->cache:null; }
}
}

/* ───────── Abhängigkeiten (Skripte/Stile) ───────── */
if(!class_exists('_WP_Dependency')){
#[AllowDynamicProperties]
class _WP_Dependency {
    public $handle;public $src;public $deps=[];public $ver;public $args;public $extra=[];public $textdomain;public $translations_path;
    public function __construct(...$args) { [$this->handle,$this->src,$this->deps,$this->ver,$this->args]=array_pad($args,5,null);$this->deps=is_array($this->deps)?$this->deps:[]; }
    public function add_data($name, $data) { if(!is_scalar($name))return false;$this->extra[$name]=$data;return true; }
    public function set_translations($domain, $path='') { if(!is_string($domain))return false;$this->textdomain=$domain;$this->translations_path=$path;return true; }
}
}
if(!class_exists('WP_Dependencies')){
#[AllowDynamicProperties]
class WP_Dependencies {
    public $registered=[];public $queue=[];public $to_do=[];public $done=[];public $args=[];public $groups=[];public $group=0;
    public function add($handle, $src, $deps=[], $ver=false, $args=null) {
        if(isset($this->registered[$handle]))return false;
        $this->registered[$handle]=new _WP_Dependency($handle,$src,$deps,$ver,$args);
        if(str_contains((string)$handle,'?'))return false;
        return true;
    }
    public function add_data($handle, $key, $value) { if(!isset($this->registered[$handle]))return false;return $this->registered[$handle]->add_data($key,$value); }
    public function get_data($handle, $key) { return $this->registered[$handle]->extra[$key]??false; }
    public function remove($handles) { foreach((array)$handles as $h)unset($this->registered[$h]); }
    public function enqueue($handles) {
        foreach((array)$handles as $h){ $h=explode('?',(string)$h);
            if(!in_array($h[0],$this->queue,true)&&isset($this->registered[$h[0]])){ $this->queue[]=$h[0];if(isset($h[1]))$this->args[$h[0]]=$h[1]; } }
    }
    public function dequeue($handles) { foreach((array)$handles as $h){ $h=explode('?',(string)$h);$k=array_search($h[0],$this->queue,true);if($k!==false){ unset($this->queue[$k],$this->args[$h[0]]);$this->queue=array_values($this->queue); } } }
    public function query($handle, $status='registered') {
        switch($status){ case 'registered': case 'scripts': return $this->registered[$handle]??false;
            case 'enqueued': case 'queue': return in_array($handle,$this->queue,true);
            case 'to_do': case 'to_print': return in_array($handle,$this->to_do,true);
            case 'done': case 'printed': return in_array($handle,$this->done,true); }
        return false;
    }
    public function set_group($handle, $recursion, $group) { $g=(int)$group;if(isset($this->groups[$handle])&&$this->groups[$handle]<=$g)return false;$this->groups[$handle]=$g;return true; }
    public function all_deps($handles, $recursion=false, $group=false) {
        $handles=(array)$handles;if(!$handles)return false;
        foreach($handles as $handle){
            $h=explode('?',(string)$handle);$handle=$h[0];$queued=in_array($handle,$this->to_do,true);
            if(in_array($handle,$this->done,true))continue;
            $moved=$this->set_group($handle,$recursion,$group);$new_group=$this->groups[$handle]??0;
            if($queued&&!$moved)continue;
            $keep=true;
            if(!isset($this->registered[$handle]))$keep=false;
            elseif($this->registered[$handle]->deps&&array_diff($this->registered[$handle]->deps,array_keys($this->registered)))$keep=false;
            elseif($this->registered[$handle]->deps&&!$this->all_deps($this->registered[$handle]->deps,true,$new_group))$keep=false;
            if(!$keep){ if($recursion)return false;continue; }
            if($queued)continue;
            if(isset($h[1]))$this->args[$handle]=$h[1];
            $this->to_do[]=$handle;
        }
        return true;
    }
    public function do_item($handle, $group=false) { return isset($this->registered[$handle]); }
    public function do_items($handles=false, $group=false) {
        $handles=$handles===false?$this->queue:(array)$handles;$this->all_deps($handles);
        foreach($this->to_do as $k=>$h){
            if(in_array($h,$this->done,true)||!isset($this->registered[$h]))continue;
            if($this->do_item($h,$group))$this->done[]=$h;
            unset($this->to_do[$k]);
        }
        return $this->done;
    }
    public function reset() { $this->to_do=[];$this->groups=[];$this->group=0; }
}
}

/* ───────── WP (Anfrage-Klasse) ───────── */
if(!class_exists('WP')){
#[AllowDynamicProperties]
class WP {
    public $public_query_vars=['m','p','posts','w','cat','withcomments','withoutcomments','s','search','exact','sentence','calendar','page','paged','more','tb','pb','author','order','orderby','year','monthnum','day','hour','minute','second','name','category_name','tag','feed','author_name','pagename','page_id','error','attachment','attachment_id','subpost','subpost_id','preview','robots','favicon','taxonomy','term','cpage','post_type','embed'];
    public $private_query_vars=['offset','posts_per_page','posts_per_archive_page','showposts','nopaging','post_type','post_status','category__in','category__not_in','category__and','tag__in','tag__not_in','tag__and','tag_slug__in','tag_slug__and','tag_id','post_mime_type','perm','comments_per_page','post__in','post__not_in','post_parent','post_parent__in','post_parent__not_in','title','fields'];
    public $extra_query_vars=[];public $query_vars=[];public $query_string='';public $request='';public $matched_rule;public $matched_query;public $did_permalink=false;
    public function add_query_var($qv) { if(!in_array($qv,$this->public_query_vars,true))$this->public_query_vars[]=$qv; }
    public function remove_query_var($name) { $this->public_query_vars=array_diff($this->public_query_vars,[$name]); }
    public function set_query_var($key, $value) { $this->query_vars[$key]=$value; }
    public function parse_request($extra_query_vars='') {
        $this->query_vars=[];$pv=apply_filters('query_vars',$this->public_query_vars);
        if(is_array($extra_query_vars))$this->extra_query_vars=$extra_query_vars;elseif($extra_query_vars!=='')parse_str((string)$extra_query_vars,$this->extra_query_vars);
        foreach($pv as $wpvar){
            if(isset($this->extra_query_vars[$wpvar]))$this->query_vars[$wpvar]=$this->extra_query_vars[$wpvar];
            elseif(isset($_GET[$wpvar]))$this->query_vars[$wpvar]=wp_unslash($_GET[$wpvar]);
            elseif(isset($_POST[$wpvar]))$this->query_vars[$wpvar]=wp_unslash($_POST[$wpvar]);
            if(!empty($this->query_vars[$wpvar])){ if(is_array($this->query_vars[$wpvar])){ foreach($this->query_vars[$wpvar] as $k=>$v)if(is_scalar($v))$this->query_vars[$wpvar][$k]=(string)$v; }else $this->query_vars[$wpvar]=(string)$this->query_vars[$wpvar]; }
        }
        foreach($this->private_query_vars as $v)if(isset($this->extra_query_vars[$v]))$this->query_vars[$v]=$this->extra_query_vars[$v];
        $this->query_vars=apply_filters('request',$this->query_vars);
        do_action_ref_array('parse_request',[&$this]);
        return true;
    }
    public function send_headers() {
        $h=[];
        if(is_user_logged_in())$h=array_merge($h,wp_get_nocache_headers());
        $h=apply_filters('wp_headers',$h,$this);
        if(!headers_sent())foreach((array)$h as $k=>$v)@header("$k: $v");
        do_action_ref_array('send_headers',[&$this]);
    }
    public function build_query_string() {
        $this->query_string='';
        foreach(array_keys($this->query_vars) as $wpvar)if($this->query_vars[$wpvar]!==''&&$this->query_vars[$wpvar]!==null)$this->query_string.=($this->query_string===''?'':'&').$wpvar.'='.rawurlencode(is_array($this->query_vars[$wpvar])?implode(',',$this->query_vars[$wpvar]):(string)$this->query_vars[$wpvar]);
        return $this->query_string;
    }
    public function register_globals() {
        global $wp_query;
        if(!$wp_query)return;
        foreach((array)$wp_query->query_vars as $k=>$v)$GLOBALS[$k]=$v;
        $GLOBALS['query_string']=$this->query_string;$GLOBALS['posts']=$wp_query->posts??[];$GLOBALS['post']=$wp_query->post??null;
        $GLOBALS['request']=$this->request;
        if($wp_query->is_single()||$wp_query->is_page()){ $GLOBALS['more']=1;$GLOBALS['single']=1; }
    }
    public function init() { wp_get_current_user(); }
    public function query_posts() { global $wp_the_query;$this->build_query_string();$wp_the_query->query($this->query_vars); }
    public function handle_404() {
        global $wp_query;
        if(apply_filters('pre_handle_404',false,$wp_query))return;
        if(is_admin()||$wp_query->is_robots()||$wp_query->is_favicon()||is_404()||$wp_query->posts)return;
        if(!$wp_query->posts&&!$wp_query->is_home())$wp_query->set_404();
    }
    public function main($query_args='') {
        $this->init();$this->parse_request($query_args);$this->send_headers();$this->query_posts();$this->handle_404();$this->register_globals();
        do_action_ref_array('wp',[&$this]);
    }
}
}

/* ───────── WP_Site, WP_Site_Query (Einzelseite: genau eine Website) ───────── */
// WP_Site: vollständigere Fassung in multisite-site.php
if(!class_exists('WP_Site_Query')){
class WP_Site_Query {
    public $query_vars=[];public $found_sites=0;public $max_num_pages=0;public $sites=null;
    public function __construct($query='') { if(!empty($query))$this->query($query); }
    public function parse_query($query='') { $this->query_vars=wp_parse_args($query,['ID'=>'','site__in'=>'','site__not_in'=>'','number'=>100,'offset'=>'','no_found_rows'=>true,'orderby'=>'id','order'=>'ASC','fields'=>'','count'=>false,'domain'=>'','path'=>'','public'=>null,'archived'=>null,'mature'=>null,'spam'=>null,'deleted'=>null,'search'=>'','network_id'=>1,'lang_id'=>null]); }
    public function query($query) { $this->parse_query($query);return $this->get_sites(); }
    public function get_sites() {
        $q=$this->query_vars;$s=WP_Site::get_instance(1);$ok=true;
        $in=array_map('intval',(array)($q['site__in']?:$q['ID']?:[]));if($in&&!in_array(1,$in,true))$ok=false;
        if(in_array(1,array_map('intval',(array)$q['site__not_in']),true))$ok=false;
        if($q['domain']!==''&&$q['domain']!==$s->domain)$ok=false;
        if($q['path']!==''&&$q['path']!==$s->path)$ok=false;
        foreach(['public','archived','mature','spam','deleted'] as $f)if($q[$f]!==null&&(string)(int)$q[$f]!==(string)$s->$f)$ok=false;
        if($q['search']!==''&&stripos($s->domain.$s->path,trim((string)$q['search'],'*'))===false)$ok=false;
        $r=$ok?[$s]:[];$this->found_sites=count($r);$this->max_num_pages=$r?1:0;
        if($q['count'])return $this->found_sites;
        if(($q['fields']??'')==='ids')$r=array_map(fn($x)=>1,$r);
        return $this->sites=$r;
    }
}
}

/* ───────── Listen-, Token- und Textersetzungs-Hilfen ───────── */
if(!class_exists('WP_List_Util')){
class WP_List_Util {
    private $input=[];private $output=[];private $orderby=[];
    public function __construct($input) { $this->output=$this->input=(array)$input; }
    public function get_input() { return $this->input; }
    public function get_output() { return $this->output; }
    public function filter($args=[], $operator='AND') {
        if(empty($args))return $this->output;
        $op=strtoupper((string)$operator);if(!in_array($op,['AND','OR','NOT'],true)){ $this->output=[];return $this->output; }
        $count=count($args);$f=[];
        foreach($this->output as $k=>$item){
            $m=0;foreach($args as $key=>$v){ $iv=is_object($item)?($item->$key??null):($item[$key]??null);$has=is_object($item)?isset($item->$key):isset($item[$key]);if($has&&$iv==$v)$m++; }
            if(($op==='AND'&&$m===$count)||($op==='OR'&&$m>0)||($op==='NOT'&&$m===0))$f[$k]=$item;
        }
        return $this->output=$f;
    }
    public function pluck($field, $index_key=null) {
        $newlist=[];
        $get=fn($it,$k)=>is_object($it)?($it->$k??null):($it[$k]??null);$has=fn($it,$k)=>is_object($it)?isset($it->$k):isset($it[$k]);
        if(!$index_key){ foreach($this->output as $k=>$v)if($has($v,$field))$newlist[$k]=$get($v,$field);return $this->output=$newlist; }
        foreach($this->output as $v){ if($has($v,$field)){ if($has($v,$index_key))$newlist[$get($v,$index_key)]=$get($v,$field);else $newlist[]=$get($v,$field); } }
        return $this->output=$newlist;
    }
    public function sort($orderby=[], $order='ASC', $preserve_keys=false) {
        if(empty($orderby))return $this->output;
        if(is_string($orderby))$orderby=[$orderby=>$order];
        foreach($orderby as $f=>$o){ $o=strtoupper((string)$o);$orderby[$f]=$o==='DESC'?'DESC':'ASC'; }
        $this->orderby=$orderby;
        $cmp=function($a,$b){ foreach($this->orderby as $f=>$o){ $x=is_object($a)?($a->$f??null):($a[$f]??null);$y=is_object($b)?($b->$f??null):($b[$f]??null);
            if($x==$y)continue;$r=$x<=>$y;return $o==='DESC'?-$r:$r; }return 0; };
        if($preserve_keys)uasort($this->output,$cmp);else{ usort($this->output,$cmp); }
        return $this->output;
    }
}
}
if(!class_exists('WP_Token_Map')){
class WP_Token_Map {
    private $map=[];private $key_length=2;
    public static function from_array($mappings, $key_length=2) { $m=new WP_Token_Map();$m->key_length=(int)$key_length;foreach((array)$mappings as $t=>$v)$m->map[(string)$t]=(string)$v;uksort($m->map,fn($a,$b)=>strlen($b)<=>strlen($a));return $m; }
    public static function from_precomputed_table($state) {
        if(!is_array($state)||!isset($state['map']))return null;$m=new WP_Token_Map();$m->key_length=(int)($state['key_length']??2);$m->map=(array)$state['map'];return $m;
    }
    public function contains($word, $case_sensitivity='case-sensitive') {
        if($case_sensitivity==='ascii-case-insensitive'){ foreach($this->map as $t=>$_)if(strcasecmp($t,(string)$word)===0)return true;return false; }
        return isset($this->map[(string)$word]);
    }
    public function read_token($text, $offset=0, &$matched_token_byte_length=null, $case_sensitivity='case-sensitive') {
        $ci=$case_sensitivity==='ascii-case-insensitive';
        foreach($this->map as $t=>$v){ $l=strlen($t);if($l===0)continue;
            $seg=substr((string)$text,(int)$offset,$l);
            if($seg!==''&&($ci?strcasecmp($seg,$t)===0:$seg===$t)){ $matched_token_byte_length=$l;return $v; } }
        $matched_token_byte_length=null;return null;
    }
    public function get_mapping($token, $case_sensitivity='case-sensitive') {
        foreach($this->map as $t=>$v)if($case_sensitivity==='ascii-case-insensitive'?strcasecmp($t,(string)$token)===0:$t===(string)$token)return $v;
        return null;
    }
    public function to_array() { return $this->map; }
    public function precomputed_php_source_table($indent="\t") { return "WP_Token_Map::from_precomputed_table(\n".$indent.var_export(['key_length'=>$this->key_length,'map'=>$this->map],true)."\n)"; }
}
}
if(!class_exists('WP_MatchesMapRegex')){
class WP_MatchesMapRegex {
    private $_matches;public $output;private $_subject;public $_pattern='(\$matches\[[1-9]+[0-9]*\])';
    public function __construct($subject, $matches) { $this->_subject=$subject;$this->_matches=$matches;$this->output=$this->_map(); }
    public static function apply($subject, $matches) { $o=new WP_MatchesMapRegex($subject,$matches);return $o->output; }
    public function _map() { return preg_replace_callback('~'.$this->_pattern.'~',[$this,'callback'],(string)$this->_subject); }
    public function callback($matches) { $i=(int)substr($matches[0],9,-1);return $this->_matches[$i]??''; }
}
}

/* ───────── Plugin-Abhängigkeiten ("Requires Plugins") ───────── */
if(!class_exists('WP_Plugin_Dependencies')){
class WP_Plugin_Dependencies {
    private static $deps=null;private static $files=null;
    public static function initialize() {
        if(self::$deps!==null)return;
        self::$deps=[];self::$files=[];
        foreach(get_plugins() as $file=>$d){ self::$files[self::slug($file)]=$file;
            $r=array_filter(array_map('trim',explode(',',(string)($d['RequiresPlugins']??$d['Requires Plugins']??''))));
            $r=array_values(array_filter(array_map(fn($s)=>preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/',$s)?$s:'',$r)));
            if($r)self::$deps[$file]=$r; }
    }
    public static function reset() { self::$deps=null;self::$files=null; }
    public static function slug($file) { $f=(string)$file;return str_contains($f,'/')?dirname($f):basename($f,'.php'); }
    public static function get_dependencies($plugin_file) { self::initialize();return self::$deps[$plugin_file]??[]; }
    public static function get_dependents($slug) { self::initialize();$o=[];foreach(self::$deps as $f=>$s)if(in_array($slug,$s,true))$o[]=$f;return $o; }
    public static function has_dependents($plugin_file) { return (bool)self::get_dependents(self::slug($plugin_file)); }
    public static function has_active_dependents($plugin_file) { foreach(self::get_dependents(self::slug($plugin_file)) as $f)if(is_plugin_active($f))return true;return false; }
    public static function has_unmet_dependencies($plugin_file) {
        foreach(self::get_dependencies($plugin_file) as $s){ $f=self::$files[$s]??null;if(!$f||!is_plugin_active($f))return true; }
        return false;
    }
    public static function get_dependency_filepaths() { self::initialize();$o=[];foreach(self::$deps as $list)foreach($list as $s)$o[$s]=self::$files[$s]??false;return $o; }
    public static function get_dependency_names($plugin_file) {
        self::initialize();$all=get_plugins();$o=[];
        foreach(self::get_dependencies($plugin_file) as $s)$o[$s]=isset(self::$files[$s])?($all[self::$files[$s]]['Name']??$s):$s;
        return $o;
    }
    public static function is_required_plugin($plugin_file) { return self::has_dependents($plugin_file); }
}
}

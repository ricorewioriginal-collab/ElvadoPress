<?php
// Block-Grundlagen: Block-Kommentare lesen (parse_blocks), serverseitig rendern (render_block, do_blocks), Block-Typen registrieren
// (register_block_type mit render_callback oder block.json), WP_Block, WP_HTML_Tag_Processor (vereinfachte Fassung).
class WP_Block_Type {
    public $name;public $title='';public $category=null;public $parent=null;public $icon=null;public $description='';public $keywords=[];public $textdomain=null;public $attributes=null;public $provides_context=null;public $uses_context=[];public $supports=null;public $render_callback=null;public $editor_script=null;public $script=null;public $view_script=null;public $editor_style=null;public $style=null;public $api_version=1;public $path='';
    public function __construct($name,$args=[]) { $this->name=(string)$name;foreach((array)$args as $k=>$v)$this->$k=$v; }
    public function is_dynamic() { return is_callable($this->render_callback); }
    public function render($attributes=[],$content='') { return $this->is_dynamic()?call_user_func($this->render_callback,$attributes,$content):''; }
    public function prepare_attributes_for_render($attributes) { foreach((array)$this->attributes as $k=>$d)if(!isset($attributes[$k])&&is_array($d)&&array_key_exists('default',$d))$attributes[$k]=$d['default'];return $attributes; }
}
class WP_Block_Type_Registry {
    private static $inst=null;private $types=[];
    public static function get_instance() { return self::$inst=self::$inst??new self(); }
    public function register($name,$args=[]) { $t=$name instanceof WP_Block_Type?$name:new WP_Block_Type($name,$args);$this->types[$t->name]=$t;return $t; }
    public function unregister($name) { $n=$name instanceof WP_Block_Type?$name->name:$name;$t=$this->types[$n]??false;unset($this->types[$n]);return $t; }
    public function get_registered($name) { return $this->types[$name]??null; }
    public function get_all_registered() { return $this->types; }
    public function is_registered($name) { return isset($this->types[$name]); }
}
function register_block_type($block_type,$args=[]) {
    $reg=WP_Block_Type_Registry::get_instance();
    if($block_type instanceof WP_Block_Type)return $reg->register($block_type);
    if(is_string($block_type)&&!str_contains($block_type,'/')||is_dir((string)$block_type)||is_file((string)$block_type)){
        $f=is_dir((string)$block_type)?rtrim($block_type,'/').'/block.json':(string)$block_type;
        if(is_file($f)){
            $m=json_decode((string)file_get_contents($f),true);if(!is_array($m)||empty($m['name']))return false;
            $a=['title'=>$m['title']??'','category'=>$m['category']??null,'attributes'=>$m['attributes']??null,'supports'=>$m['supports']??null,'uses_context'=>$m['usesContext']??[],'provides_context'=>$m['providesContext']??null,'api_version'=>$m['apiVersion']??1,'path'=>dirname($f)];
            if(!empty($m['render'])&&str_starts_with((string)$m['render'],'file:')){
                $rf=dirname($f).'/'.ltrim(substr((string)$m['render'],5),'./');
                if(is_file($rf))$a['render_callback']=function($attributes,$content,$block=null) use($rf){ ob_start();(function() use($attributes,$content,$block,$rf){ include $rf; })();return ob_get_clean(); };
            }
            $a=array_merge($a,(array)$args);return $reg->register($m['name'],$a);
        }
        if(is_dir((string)$block_type))return false;
    }
    return $reg->register((string)$block_type,$args);
}
function register_block_type_from_metadata($file_or_folder,$args=[]) { return register_block_type($file_or_folder,$args); }
function unregister_block_type($name) { return WP_Block_Type_Registry::get_instance()->unregister($name); }
function get_dynamic_block_names() { $o=[];foreach(WP_Block_Type_Registry::get_instance()->get_all_registered() as $n=>$t)if($t->is_dynamic())$o[]=$n;return $o; }
function remove_block_asset_path_prefix($p) { return $p; }

/** Block-Kommentare in einen Baum übersetzen. */
function parse_blocks($content) {
    $content=(string)$content;if($content==='')return [];
    preg_match_all('/<!--\s+(\/)?wp:([a-z][a-z0-9_-]*\/)?([a-z][a-z0-9_-]*)\s+(\{(?:(?!\}\s+\/?-->).)*+\}\s+)?(\/)?-->/s',$content,$m,PREG_SET_ORDER|PREG_OFFSET_CAPTURE);
    $root=['blockName'=>null,'attrs'=>[],'innerBlocks'=>[],'innerHTML'=>'','innerContent'=>[]];$stack=[$root];$last=0;
    $add=function(string $html) use(&$stack){ if($html==='')return;$top=&$stack[count($stack)-1];$top['innerHTML'].=$html;$top['innerContent'][]=$html; };
    foreach($m as $x){
        $off=$x[0][1];$add(substr($content,$last,$off-$last));$last=$off+strlen($x[0][0]);
        $closing=$x[1][1]>=0&&$x[1][0]==='/';$selfClose=isset($x[5])&&$x[5][1]>=0&&$x[5][0]==='/';
        $ns=($x[2][1]>=0&&$x[2][0]!=='')?$x[2][0]:'core/';$name=$ns.$x[3][0];
        $attrs=(isset($x[4])&&$x[4][1]>=0)?json_decode(trim($x[4][0]),true):[];if(!is_array($attrs))$attrs=[];
        if($closing){
            if(count($stack)>1){ $b=array_pop($stack);$top=&$stack[count($stack)-1];$top['innerBlocks'][]=$b;$top['innerContent'][]=null; }
        } elseif($selfClose){ $b=['blockName'=>$name,'attrs'=>$attrs,'innerBlocks'=>[],'innerHTML'=>'','innerContent'=>[]];$top=&$stack[count($stack)-1];$top['innerBlocks'][]=$b;$top['innerContent'][]=null; }
        else $stack[]=['blockName'=>$name,'attrs'=>$attrs,'innerBlocks'=>[],'innerHTML'=>'','innerContent'=>[]];
    }
    $add(substr($content,$last));
    while(count($stack)>1){ $b=array_pop($stack);$top=&$stack[count($stack)-1];$top['innerBlocks'][]=$b;$top['innerContent'][]=null; }   // ungeschlossene Blöcke abschließen
    $r=$stack[0];$out=[];$ic=$r['innerContent'];$bi=0;
    foreach($ic as $part){ if($part===null){ $out[]=$r['innerBlocks'][$bi++]; } elseif(trim($part)!==''||$out===[]&&false){ $out[]=['blockName'=>null,'attrs'=>[],'innerBlocks'=>[],'innerHTML'=>$part,'innerContent'=>[$part]]; } }
    return $out;
}
class WP_Block {
    public $parsed_block;public $name;public $block_type;public $context=[];public $available_context=[];public $attributes=[];public $inner_blocks=[];public $inner_html='';public $inner_content=[];
    public function __construct($block,$available_context=[],$registry=null) {
        $this->parsed_block=$block;$this->name=$block['blockName']??null;$reg=$registry??WP_Block_Type_Registry::get_instance();$this->block_type=$this->name?$reg->get_registered($this->name):null;
        $a=(array)($block['attrs']??[]);$this->attributes=$this->block_type?$this->block_type->prepare_attributes_for_render($a):$a;
        $uses=$this->block_type?(array)$this->block_type->uses_context:[];$this->context=$uses?array_intersect_key($available_context,array_flip($uses)):[];
        $prov=$available_context;
        if($this->block_type&&is_array($this->block_type->provides_context))foreach($this->block_type->provides_context as $ctx=>$attr)if(array_key_exists($attr,$this->attributes))$prov[$ctx]=$this->attributes[$attr];
        $this->available_context=$prov;
        $this->inner_html=(string)($block['innerHTML']??'');$this->inner_content=(array)($block['innerContent']??[]);
        $this->inner_blocks=array_map(fn($b)=>new WP_Block($b,$prov,$reg),(array)($block['innerBlocks']??[]));
    }
    public function render($options=[]) {
        $prev=$GLOBALS['rrw_wp_current_block']??null;$GLOBALS['rrw_wp_current_block']=$this;
        try{
            $content='';$bi=0;
            foreach($this->inner_content as $part){ $content.=$part===null?(isset($this->inner_blocks[$bi])?$this->inner_blocks[$bi++]->render():''):$part; }
            if($this->block_type&&$this->block_type->is_dynamic())$out=(string)call_user_func($this->block_type->render_callback,$this->attributes,$content,$this);
            else $out=$content;
            return (string)apply_filters('render_block',$out,$this->parsed_block,$this);
        } finally { $GLOBALS['rrw_wp_current_block']=$prev; }
    }
}
function render_block($parsed_block) {
    $pre=apply_filters('pre_render_block',null,$parsed_block,null);if($pre!==null)return (string)$pre;
    if(empty($parsed_block['blockName']))return (string)($parsed_block['innerHTML']??'');
    $b=new WP_Block($parsed_block);return $b->render();
}
function do_blocks($content) { $content=(string)$content;if(!str_contains($content,'<!-- wp:'))return $content;$o='';foreach(parse_blocks($content) as $b)$o.=render_block($b);return $o; }
function serialize_block($block) {
    $name=$block['blockName']??null;if($name===null)return (string)($block['innerHTML']??'');
    $short=str_starts_with($name,'core/')?substr($name,5):$name;$attrs=!empty($block['attrs'])?' '.wp_json_encode($block['attrs'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):'';
    if(empty($block['innerContent'])&&empty($block['innerBlocks'])&&($block['innerHTML']??'')==='')return "<!-- wp:$short$attrs /-->";
    $o="<!-- wp:$short$attrs -->";$bi=0;foreach((array)$block['innerContent'] as $p){ $o.=$p===null?serialize_block($block['innerBlocks'][$bi++]??['blockName'=>null,'innerHTML'=>'']):$p; }
    return $o."<!-- /wp:$short -->";
}
function serialize_blocks($blocks) { return implode('',array_map('serialize_block',(array)$blocks)); }
function traverse_and_serialize_blocks($blocks,$pre=null,$post=null) { return serialize_blocks($blocks); }
function traverse_and_serialize_block($block,$pre=null,$post=null) { return serialize_block($block); }
function filter_block_content($text,$allowed_html='',$allowed_protocols=[]) { return wp_kses_post($text); }
function excerpt_remove_blocks($content) { return strip_tags(do_blocks($content)); }
function strip_core_block_namespace($n) { return str_starts_with((string)$n,'core/')?substr($n,5):$n; }
function get_block_wrapper_attributes($extra_attributes=[]) {
    $b=$GLOBALS['rrw_wp_current_block']??null;$classes=[];$styles=[];$id='';
    if($b&&$b->name){
        $classes[]='wp-block-'.str_replace('/','-',strip_core_block_namespace($b->name));
        $sup=function_exists('rrw_wp_block_supports')?rrw_wp_block_supports($b):['class'=>[],'style'=>[],'id'=>''];
        $classes=array_merge($classes,$sup['class']);$styles=$sup['style'];$id=$sup['id'];
    }
    if(!empty($extra_attributes['class']))$classes[]=$extra_attributes['class'];
    if(!empty($b->attributes['className']))$classes[]=$b->attributes['className'];
    $st=rrw_wp_decl_css($styles);if(!empty($extra_attributes['style']))$st.=rtrim((string)$extra_attributes['style'],';').';';
    $o=[];if($id!=='')$o[]='id="'.esc_attr($id).'"';
    $cl=array_unique(array_filter(array_merge(...array_map(fn($c)=>preg_split('/\s+/',trim((string)$c)),$classes?:['']))));
    if($cl)$o[]='class="'.esc_attr(implode(' ',$cl)).'"';
    if($st!=='')$o[]='style="'.esc_attr($st).'"';
    foreach($extra_attributes as $k=>$v)if(!in_array($k,['class','style'],true)&&is_scalar($v))$o[]=esc_attr($k).'="'.esc_attr((string)$v).'"';
    return implode(' ',$o);
}
function gutenberg_is_fse_theme() { return wp_is_block_theme(); } function gutenberg_can_edit_post_type($t) { return false; } function use_block_editor_for_post($p) { return false; }  
function get_hooked_blocks($a=null,$b=null) { return []; } function make_before_block_visitor($a,$b=null) { return fn()=>''; } function make_after_block_visitor($a,$b=null) { return fn()=>''; }
function build_comment_query_vars_from_block($block) { return []; } function build_query_vars_from_query_block($block,$page) { return []; }
class WP_Theme_JSON { public function __construct($d=[],$origin='theme') {} public function get_settings() { return []; } public function get_raw_data() { return []; } public static function get_element_class_name($e) { return 'wp-element-'.$e; } public function get_stylesheet($t=[],$o=[],$a=[]) { return ''; } public function get_data() { return []; } }
class WP_Theme_JSON_Resolver { public static function get_merged_data($o='custom') { return new WP_Theme_JSON(); } public static function get_theme_data($d=[],$o=[]) { return new WP_Theme_JSON(); } }

/** Vereinfachte Fassung von WP_HTML_Tag_Processor: Start-Tags suchen, Attribute und Klassen lesen/ändern. */
class WP_HTML_Tag_Processor {
    protected $html;protected $pos=0;protected $cur=null;   // cur: [start,end,tag,attrs(array name=>value|true)]
    protected $matches=0;
    public function __construct($html) { $this->html=(string)$html; }
    protected function tagMatch($t,$q): bool {
        if($q===null)return true;
        if(is_string($q))return strtolower($t[2])===strtolower($q);
        if(!empty($q['tag_name'])&&strtolower($t[2])!==strtolower($q['tag_name']))return false;
        if(!empty($q['class_name'])){ $cl=preg_split('/\s+/',trim((string)($t[3]['class']??'')));if(!in_array($q['class_name'],$cl,true))return false; }
        return true;
    }
    public function next_tag($query=null) {
        if(is_array($query)&&isset($query['tag_closers'])&&$query['tag_closers']==='visit'){}
        $want=is_array($query)&&isset($query['match_offset'])?(int)$query['match_offset']:1;$this->matches=0;$p=$this->cur?$this->cur[1]:$this->pos;
        while(preg_match('/<([a-zA-Z][a-zA-Z0-9:-]*)((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/s',$this->html,$m,PREG_OFFSET_CAPTURE,$p)){
            $start=$m[0][1];$end=$start+strlen($m[0][0]);$t=[$start,$end,$m[1][0],$this->parseAttrs($m[2][0])];$p=$end;
            if($this->tagMatch($t,$query)&&++$this->matches>=$want){ $this->cur=$t;return true; }
        }
        $this->cur=null;return false;
    }
    protected function parseAttrs(string $s): array {
        $a=[];preg_match_all('/([^\s"\'<>\/=]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?/',$s,$mm,PREG_SET_ORDER);
        foreach($mm as $x){ $k=strtolower($x[1]);if(isset($a[$k]))continue;$a[$k]=isset($x[4])&&$x[4]!==''?$x[4]:(($x[3]??'')!==''?$x[3]:(($x[2]??'')!==''?$x[2]:(array_key_exists(2,$x)||array_key_exists(3,$x)||isset($x[4])?'':true))); }
        return $a;
    }
    public function get_tag() { return $this->cur?strtoupper($this->cur[2]):null; }
    public function get_attribute($name) { if(!$this->cur)return null;$k=strtolower((string)$name);if(!array_key_exists($k,$this->cur[3]))return null;$v=$this->cur[3][$k];return $v===true?true:html_entity_decode((string)$v,ENT_QUOTES|ENT_HTML5); }
    public function get_attribute_names_with_prefix($prefix) { return $this->cur?array_values(array_filter(array_keys($this->cur[3]),fn($k)=>str_starts_with($k,strtolower($prefix)))):null; }
    protected function rewrite(): void {
        if(!$this->cur)return;[$s,$e,$tag,$attrs]=$this->cur;$o='<'.$tag;
        foreach($attrs as $k=>$v)$o.=$v===true?' '.$k:' '.$k.'="'.esc_attr(html_entity_decode((string)$v,ENT_QUOTES|ENT_HTML5)).'"';
        $self=substr($this->html,$e-2,2)==='/>'?' /':'';$o.=$self.'>';
        $this->html=substr($this->html,0,$s).$o.substr($this->html,$e);$this->cur[1]=$s+strlen($o);
    }
    public function set_attribute($name,$value) { if(!$this->cur)return false;$k=strtolower((string)$name);$this->cur[3][$k]=$value===true?true:($value===false?null:(string)$value);if($value===false)unset($this->cur[3][$k]);$this->rewrite();return true; }
    public function remove_attribute($name) { if(!$this->cur)return false;unset($this->cur[3][strtolower((string)$name)]);$this->rewrite();return true; }
    public function has_class($c) { if(!$this->cur)return null;return in_array($c,preg_split('/\s+/',trim((string)($this->cur[3]['class']??''))),true); }
    public function add_class($c) { if(!$this->cur)return false;$cl=array_values(array_filter(preg_split('/\s+/',trim((string)($this->cur[3]['class']??'')))));if(!in_array($c,$cl,true))$cl[]=$c;return $this->set_attribute('class',implode(' ',$cl)); }
    public function remove_class($c) { if(!$this->cur)return false;$cl=array_values(array_diff(array_filter(preg_split('/\s+/',trim((string)($this->cur[3]['class']??'')))),[$c]));return $cl?$this->set_attribute('class',implode(' ',$cl)):$this->remove_attribute('class'); }
    public function class_list() { return $this->cur?array_values(array_filter(preg_split('/\s+/',trim((string)($this->cur[3]['class']??'')))))??[]:[]; }
    public function next_token() { return $this->next_tag(); }
    public function is_tag_closer() { return false; } public function get_token_type() { return $this->cur?'#tag':null; } public function get_token_name() { return $this->get_tag(); }
    public function set_bookmark($n) { return true; } public function seek($n) { $this->cur=null;$this->pos=0;return true; } public function release_bookmark($n) { return true; }
    public function get_updated_html() { return $this->html; }
    public function __toString() { return $this->html; }
}
class WP_HTML_Processor extends WP_HTML_Tag_Processor { public static function create_fragment($html,$ctx='<body>',$enc='UTF-8') { return new self($html); } public function get_breadcrumbs() { return []; } }

/* ───────── Dateisystem, Listen, Upgrader (Platzhalter bzw. einfache Fassung) ───────── */
abstract class WP_Filesystem_Base {
    public $verbose=false;public $errors=null;public $options=[];
    public function abspath() { return rtrim(ABSPATH,'/').'/'; } public function wp_content_dir() { return rtrim(WP_CONTENT_DIR,'/').'/'; } public function wp_plugins_dir() { return rtrim(WP_PLUGIN_DIR,'/').'/'; } public function wp_themes_dir($theme=false) { return rtrim(WP_CONTENT_DIR,'/').'/themes/'; }
    public function find_folder($folder) { return rtrim($folder,'/').'/'; } public function find_base_dir($base='.',$verbose=false) { return rtrim(ABSPATH,'/').'/'; }
    public function gethchmod($file) { return '0644'; } public function getnumchmodfromh($mode) { return 0644; }
    public function connect() { return true; } public function setDefaultPermissions() {}
}
class WP_Filesystem_Direct extends WP_Filesystem_Base {
    public function __construct($arg=null) { $this->errors=new WP_Error(); }
    public function get_contents($file) { return @file_get_contents($file); } public function get_contents_array($file) { return @file($file); }
    public function put_contents($file,$contents,$mode=false) { $ok=@file_put_contents($file,$contents)!==false;if($ok&&$mode)@chmod($file,$mode);return $ok; }
    public function cwd() { return getcwd(); } public function chdir($dir) { return @chdir($dir); } public function chgrp($f,$g,$r=false) { return true; } public function chmod($f,$m=false,$r=false) { return $m?@chmod($f,$m):true; } public function chown($f,$o,$r=false) { return true; }
    public function owner($f) { return fileowner($f); } public function getchmod($f) { return substr(sprintf('%o',fileperms($f)),-3); } public function group($f) { return filegroup($f); }
    public function copy($s,$d,$overwrite=false,$mode=false) { if(!$overwrite&&file_exists($d))return false;return @copy($s,$d); }
    public function move($s,$d,$overwrite=false) { if(!$overwrite&&file_exists($d))return false;return @rename($s,$d); }
    public function delete($file,$recursive=false,$type=false) { if(is_dir($file)){ if($recursive)rrw_wp_rmdir($file);else return @rmdir($file);return !is_dir($file); }return @unlink($file); }
    public function exists($f) { return file_exists($f); } public function is_file($f) { return is_file($f); } public function is_dir($p) { return is_dir($p); } public function is_readable($f) { return is_readable($f); } public function is_writable($f) { return is_writable($f); }
    public function atime($f) { return fileatime($f); } public function mtime($f) { return filemtime($f); } public function size($f) { return filesize($f); } public function touch($f,$t=0,$a=0) { return @touch($f,$t?:time()); }
    public function mkdir($path,$chmod=false,$chown=false,$chgrp=false) { return is_dir($path)||@mkdir($path,$chmod?:0775,true); } public function rmdir($path,$recursive=false) { return $this->delete($path,$recursive); }
    public function dirlist($path,$include_hidden=true,$recursive=false) { if(!is_dir($path))return false;$o=[];foreach(scandir($path) as $f){ if($f==='.'||$f==='..'||(!$include_hidden&&$f[0]==='.'))continue;$full=rtrim($path,'/').'/'.$f;$o[$f]=['name'=>$f,'type'=>is_dir($full)?'d':'f','size'=>is_file($full)?filesize($full):0,'lastmodunix'=>filemtime($full),'files'=>$recursive&&is_dir($full)?$this->dirlist($full,$include_hidden,true):false]; }return $o; }
}

class WP_Upgrader_Skin { public $result=false;public $upgrader=null;public function __construct($args=[]) {} public function set_upgrader(&$u) { $this->upgrader=$u; } public function header() {} public function footer() {} public function feedback($s,...$a) {} public function error($e) {} public function request_filesystem_credentials($e=false,$c='',$ex=false) { return true; } public function before() {} public function after() {} public function get_upgrade_messages() { return []; } public function set_result($r) { $this->result=$r; } }
class Automatic_Upgrader_Skin extends WP_Upgrader_Skin {} class WP_Ajax_Upgrader_Skin extends WP_Upgrader_Skin { public function get_errors() { return new WP_Error(); } public function get_error_messages() { return ''; } }
class Plugin_Installer_Skin extends WP_Upgrader_Skin {} class Theme_Installer_Skin extends WP_Upgrader_Skin {} class Bulk_Upgrader_Skin extends WP_Upgrader_Skin {}
class WP_Upgrader { public $skin;public $strings=[];public $result=[];public function __construct($skin=null) { $this->skin=$skin??new WP_Upgrader_Skin(); } public function init() {} public function generic_strings() {} public function fs_connect($d=[],$c=false) { return true; } public function install_package($a=[]) { return new WP_Error('unsupported','Installationen laufen über den CMS-Installer.'); } public function run($o) { return new WP_Error('unsupported','Installationen laufen über den CMS-Installer.'); } public static function release_lock($n) { return true; } public static function create_lock($n,$t=3600) { return true; } }
class Plugin_Upgrader extends WP_Upgrader { public function install($package,$args=[]) { return new WP_Error('unsupported','Installationen laufen über den CMS-Installer.'); } public function upgrade($plugin,$args=[]) { return new WP_Error('unsupported','Aktualisierungen laufen über den CMS-Installer.'); } public function plugin_info() { return false; } }
class Theme_Upgrader extends WP_Upgrader { public function install($package,$args=[]) { return new WP_Error('unsupported','Installationen laufen über den CMS-Installer.'); } public function upgrade($theme,$args=[]) { return new WP_Error('unsupported','Aktualisierungen laufen über den CMS-Installer.'); } }
class Core_Upgrader extends WP_Upgrader {} class Language_Pack_Upgrader extends WP_Upgrader {} class WP_Automatic_Updater { public function is_vcs_checkout($d) { return false; } }
function plugins_api($action,$args=[]) { return new WP_Error('plugins_api_failed','Das Plugin-Verzeichnis wird über den CMS-Installer genutzt.'); } function themes_api($action,$args=[]) { return new WP_Error('themes_api_failed','Das Theme-Verzeichnis wird über den CMS-Installer genutzt.'); } function translations_api($type,$args=null) { return new WP_Error('translations_api_failed','Sprachpakete lädt das CMS selbst.'); }
function install_plugin_install_status($api,$loop=false) { return ['status'=>'install','url'=>'','version'=>'']; }
if(!class_exists('WP_List_Table',false)){
class WP_List_Table {
    public $items=[];public $_args=[];public $_pagination_args=[];protected $screen=null;public $_column_headers;
    public function __construct($args=[]) { $this->_args=wp_parse_args($args,['plural'=>'','singular'=>'','ajax'=>false,'screen'=>null]); }
    public function ajax_user_can() { return true; } public function prepare_items() {} public function get_columns() { return []; } public function get_sortable_columns() { return []; } protected function get_default_primary_column_name() { return ''; } protected function get_views() { return []; } protected function get_bulk_actions() { return []; }
    public function set_pagination_args($args) { $this->_pagination_args=$args; } public function get_pagination_arg($k) { return $this->_pagination_args[$k]??0; }
    public function has_items() { return !empty($this->items); } public function no_items() { echo 'Keine Einträge gefunden.'; }
    public function search_box($text,$input_id) {} public function views() {} public function pagination($which) {} public function current_action() { return $_REQUEST['action']??false; } public function get_pagenum() { return max(1,(int)($_REQUEST['paged']??1)); }
    public function display() { echo '<table class="wp-list-table widefat"><thead><tr>';foreach($this->get_columns() as $k=>$t)echo '<th>'.esc_html((string)$t).'</th>';echo '</tr></thead><tbody>';$this->display_rows_or_placeholder();echo '</tbody></table>'; }
    public function display_rows_or_placeholder() { if($this->has_items())$this->display_rows();else{ echo '<tr><td colspan="'.count($this->get_columns()).'">';$this->no_items();echo '</td></tr>'; } }
    public function display_rows() { foreach($this->items as $item){ echo '<tr>';foreach($this->get_columns() as $k=>$_){ echo '<td>';if(method_exists($this,'column_'.$k))echo $this->{'column_'.$k}($item);elseif(method_exists($this,'column_default'))echo $this->column_default($item,$k);echo '</td>'; }echo '</tr>'; } }
    public function get_column_info() { return [$this->get_columns(),[],$this->get_sortable_columns(),'']; }
}
}
class WP_Importer { public function __construct() {} public function dispatch() {} }
class WP_Screen { public $id='';public $base='';public $post_type='';public static function get($hook_name='') { return null; } public function in_admin() { return true; } public function is_block_editor() { return false; } }

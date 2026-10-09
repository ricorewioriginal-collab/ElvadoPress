<?php
// Ergänzende WordPress-Funktionen (Kern 2, Teil 3): HTTPS-Erkennung, Schriften, Konstanten, UTF-8-Hilfen, Speculation Rules, Sitemaps, Bookmarks-Rest.
// Eigenständig umgesetzt; nichts wird beim Laden ausgeführt, Netzwerkzugriffe nur beim Aufruf.

/* ───────── HTTPS ───────── */
if(!function_exists('wp_is_home_url_using_https')){ function wp_is_home_url_using_https() { return 'https'===wp_parse_url(home_url(),PHP_URL_SCHEME); } }
if(!function_exists('wp_is_site_url_using_https')){ function wp_is_site_url_using_https() {
    $u=site_url();return 'https'===wp_parse_url($u,PHP_URL_SCHEME);
} }
if(!function_exists('wp_is_using_https')){ function wp_is_using_https() { return wp_is_home_url_using_https()&&wp_is_site_url_using_https(); } }
if(!function_exists('wp_is_local_html_output')){ function wp_is_local_html_output($html) {
    // Eigene Ausgabe erkennen: Link auf die REST-API (api.w.org) mit gleichem Host wie die Website
    if(!preg_match('~<link\s[^>]*rel=["\']https://api\.w\.org/["\'][^>]*href=["\']([^"\']+)["\']~i',(string)$html,$m)&&!preg_match('~<link\s[^>]*href=["\']([^"\']+)["\'][^>]*rel=["\']https://api\.w\.org/["\']~i',(string)$html,$m))return null;
    $a=wp_parse_url(html_entity_decode($m[1]));$b=wp_parse_url(home_url());
    return isset($a['host'],$b['host'])&&strcasecmp($a['host'],$b['host'])===0;
} }
if(!function_exists('wp_get_https_detection_errors')){ function wp_get_https_detection_errors() {
    $pre=apply_filters('pre_wp_get_https_detection_errors',null);
    if($pre instanceof WP_Error)return $pre;
    $err=new WP_Error();
    $r=wp_remote_request(preg_replace('~^http://~i','https://',home_url('/')),['headers'=>['Cache-Control'=>'no-cache'],'sslverify'=>true,'timeout'=>5]);
    if(is_wp_error($r)){ $err->add($r->get_error_code(),$r->get_error_message());return $err; }
    $code=(int)wp_remote_retrieve_response_code($r);
    if($code<200||$code>=400){ $err->add('https_http_code','Der HTTPS-Aufruf lieferte den Status '.$code.'.');return $err; }
    if(wp_is_local_html_output(wp_remote_retrieve_body($r))===false)$err->add('bad_response_source','Die HTTPS-Adresse liefert nicht diese Website aus.');
    return $err;
} }
if(!function_exists('wp_is_https_supported')){ function wp_is_https_supported() {
    $e=get_option('https_detection_errors');
    if(!is_array($e))return false;   // noch nicht geprüft: nicht raten und kein Netzwerkzugriff
    return empty($e)||(count($e)===1&&isset($e['https_detection_errors'])&&empty($e['https_detection_errors']));
} }
if(!function_exists('wp_should_replace_insecure_home_url')){ function wp_should_replace_insecure_home_url() {
    $s=wp_is_using_https()&&get_option('https_migration_required')&&'http'===wp_parse_url((string)get_option('home'),PHP_URL_SCHEME);
    return (bool)apply_filters('wp_should_replace_insecure_home_url',$s);
} }
if(!function_exists('wp_update_urls_to_https')){ function wp_update_urls_to_https() {
    $old=['home'=>(string)get_option('home'),'siteurl'=>(string)get_option('siteurl')];$new=[];
    foreach($old as $k=>$u)$new[$k]=preg_replace('~^http://~i','https://',$u);   // set_url_scheme der Schicht ändert nichts
    if($new===$old)return true;
    foreach($new as $k=>$u)if($u!==$old[$k]&&!update_option($k,$u)){ foreach($old as $k2=>$u2)update_option($k2,$u2);return false; }
    return true;
} }
if(!function_exists('wp_update_https_migration_required')){ function wp_update_https_migration_required($old_url, $new_url) {
    if($old_url===$new_url)return;
    $fresh=!(int)(function_exists('wp_count_posts')?(wp_count_posts('post')->publish??0):0)&&!(int)(wp_count_posts('page')->publish??0);
    $o=wp_parse_url((string)$old_url);$n=wp_parse_url((string)$new_url);
    $schemeOnly=($o['host']??'')===($n['host']??'')&&($o['path']??'')===($n['path']??'')&&($o['scheme']??'')==='http'&&($n['scheme']??'')==='https';
    update_option('https_migration_required',$fresh||!$schemeOnly?'0':'1');
} }

/* ───────── Schriften ───────── */
if(!function_exists('wp_get_font_dir')){ function wp_get_font_dir($font_dir=[]) {
    $u=wp_upload_dir(null,false);$base=untrailingslashit($u['basedir']);$burl=untrailingslashit($u['baseurl']);
    $d=['path'=>$base.'/fonts','url'=>$burl.'/fonts','subdir'=>'','basedir'=>$base.'/fonts','baseurl'=>$burl.'/fonts','error'=>false];
    return apply_filters('font_dir',$d);
} }
if(!function_exists('wp_font_dir')){ function wp_font_dir($create_dir=true) {
    $d=wp_get_font_dir();
    if($create_dir&&!empty($d['path'])&&!is_dir($d['path'])&&!@mkdir($d['path'],0775,true)&&!is_dir($d['path']))$d['error']='Der Schriftenordner konnte nicht angelegt werden.';
    return $d;
} }
if(!function_exists('_wp_filter_font_directory')){ function _wp_filter_font_directory($font_dir) {
    if(doing_filter('font_dir'))return $font_dir;   // Endlosschleife vermeiden
    return ['path'=>untrailingslashit($font_dir['basedir']).'/fonts','url'=>untrailingslashit($font_dir['baseurl']).'/fonts','subdir'=>'','basedir'=>untrailingslashit($font_dir['basedir']).'/fonts','baseurl'=>untrailingslashit($font_dir['baseurl']).'/fonts','error'=>false];
} }
if(!function_exists('_wp_after_delete_font_family')){ function _wp_after_delete_font_family($post_id, $post) {
    if(!$post||'wp_font_family'!==$post->post_type)return;
    foreach(get_children(['post_parent'=>$post_id,'post_type'=>'wp_font_face','fields'=>'ids','numberposts'=>-1],ARRAY_A)?:[] as $id)wp_delete_post((int)$id,true);
} }
if(!function_exists('_wp_before_delete_font_face')){ function _wp_before_delete_font_face($post_id, $post) {
    if(!$post||'wp_font_face'!==$post->post_type)return;
    $files=get_post_meta($post_id,'_wp_font_face_file',false);$dir=wp_get_font_dir()['path'];
    foreach((array)$files as $f){ $p=$dir.'/'.basename((string)$f);if(is_file($p))@unlink($p); }
} }
if(!class_exists('WP_Font_Collection')){
class WP_Font_Collection {
    public $slug;private $config;
    public function __construct($slug, $args) { $this->slug=sanitize_title($slug);$this->config=$args; }
    public function get_config() { return array_merge(['slug'=>$this->slug],is_array($this->config)?$this->config:['font_families'=>$this->config]); }
    public function get_data() {
        $c=$this->get_config();
        if(isset($c['font_families'])&&is_array($c['font_families']))return ['name'=>$c['name']??'','description'=>$c['description']??'','font_families'=>$c['font_families'],'categories'=>$c['categories']??[]];
        return new WP_Error('font_collection_no_data','Die Schriftensammlung enthält keine Daten.');
    }
}
}
if(!function_exists('wp_register_font_collection')){ function wp_register_font_collection($slug_or_args, $args=[]) {
    $slug=is_array($slug_or_args)?(string)($slug_or_args['slug']??''):(string)$slug_or_args;$cfg=is_array($slug_or_args)?$slug_or_args:$args;
    if($slug==='')return new WP_Error('font_collection_missing_slug','Die Schriftensammlung braucht einen Slug.');
    $slug=sanitize_title($slug);
    if(isset($GLOBALS['elvado_font_collections'][$slug]))return new WP_Error('font_collection_already_registered','Die Schriftensammlung ist bereits registriert.');
    return $GLOBALS['elvado_font_collections'][$slug]=new WP_Font_Collection($slug,$cfg);
} }
if(!function_exists('wp_unregister_font_collection')){ function wp_unregister_font_collection($slug) {
    if(!isset($GLOBALS['elvado_font_collections'][$slug]))return false;
    unset($GLOBALS['elvado_font_collections'][$slug]);return true;
} }
if(!function_exists('_wp_register_default_font_collections')){ function _wp_register_default_font_collections() {
    wp_register_font_collection('google-fonts',['name'=>'Google Fonts','description'=>'Schriften von Google Fonts.','font_families'=>'https://s.w.org/images/fonts/wp-6.7/collections/google-fonts-with-preview.json','categories'=>[]]);
} }
if(!function_exists('elvado_c2_font_face_css')){ function elvado_c2_font_face_css(array $fonts): string {   // fonts: Familie => Liste von Schriftschnitten (Schlüssel wie in CSS)
    $css='';
    foreach($fonts as $family=>$faces)foreach((array)$faces as $f){
        if(!is_array($f))continue;
        $f=array_merge(['font-family'=>is_string($family)?$family:'','font-style'=>'normal','font-weight'=>'400','font-display'=>'fallback'],$f);
        $src=[];foreach((array)($f['src']??[]) as $s){ $fmt=['woff2'=>'woff2','woff'=>'woff','ttf'=>'truetype','otf'=>'opentype'][strtolower(pathinfo((string)strtok($s,'?'),PATHINFO_EXTENSION))]??'';$src[]='url("'.esc_url($s).'")'.($fmt?' format("'.$fmt.'")':''); }
        if(!$src)continue;unset($f['src']);
        $fam=$f['font-family'];if(str_contains($fam,' ')&&!preg_match('/^["\']/',$fam))$fam='"'.$fam.'"';
        $decl='font-family:'.$fam.';';foreach($f as $k=>$v){ if($k==='font-family'||!is_scalar($v)||!preg_match('/^[a-z-]+$/',$k))continue;$decl.=$k.':'.preg_replace('/[;{}<>]/','',(string)$v).';'; }
        $css.='@font-face{'.$decl.'src:'.implode(', ',$src).';}';
    }
    return $css;
} }
if(!function_exists('elvado_c2_theme_fonts')){ function elvado_c2_theme_fonts(array $json): array {   // Schriften aus einem theme.json-Array (settings.typography.fontFamilies) einsammeln
    $out=[];$base=get_stylesheet_directory_uri();
    foreach((array)($json['settings']['typography']['fontFamilies']['theme']??$json['settings']['typography']['fontFamilies']??[]) as $fam){
        if(!is_array($fam)||empty($fam['fontFace']))continue;$name=(string)($fam['fontFamily']??$fam['name']??'');
        foreach($fam['fontFace'] as $face){ $src=[];foreach((array)($face['src']??[]) as $s)$src[]=str_starts_with((string)$s,'file:./')?trailingslashit($base).substr((string)$s,7):(string)$s;
            $out[$name][]=['font-family'=>(string)($face['fontFamily']??$name),'font-style'=>$face['fontStyle']??'normal','font-weight'=>$face['fontWeight']??'400','font-display'=>$face['fontDisplay']??'fallback','src'=>$src]; }
    }
    return $out;
} }
if(!function_exists('wp_print_font_faces')){ function wp_print_font_faces($fonts=[]) {
    if(empty($fonts)){ $f=get_stylesheet_directory().'/theme.json';$j=is_file($f)?json_decode((string)file_get_contents($f),true):null;$fonts=is_array($j)?elvado_c2_theme_fonts($j):[]; }
    $css=elvado_c2_font_face_css((array)$fonts);
    if($css==='')return;
    echo '<style class="wp-fonts-local" type="text/css">'."\n".$css."\n</style>\n";
} }
if(!function_exists('wp_print_font_faces_from_style_variations')){ function wp_print_font_faces_from_style_variations() {
    $fonts=[];
    foreach(glob(get_stylesheet_directory().'/styles/*.json')?:[] as $file){ $j=json_decode((string)file_get_contents($file),true);if(is_array($j))$fonts=array_merge_recursive($fonts,elvado_c2_theme_fonts($j)); }
    if($fonts)wp_print_font_faces($fonts);
} }

/* ───────── Konstanten ───────── */
if(!function_exists('elvado_c2_def')){ function elvado_c2_def($k, $v) { if(!defined($k))define($k,$v); } }
if(!function_exists('wp_initial_constants')){ function wp_initial_constants() {
    foreach(['WP_MEMORY_LIMIT'=>'256M','WP_MAX_MEMORY_LIMIT'=>'512M','WP_CONTENT_DIR'=>ABSPATH.'wp-content','WP_DEBUG'=>false,'WP_DEBUG_LOG'=>false,'WP_DEBUG_DISPLAY'=>true,'SCRIPT_DEBUG'=>false,'WP_CACHE'=>false,
        'MINUTE_IN_SECONDS'=>60,'HOUR_IN_SECONDS'=>3600,'DAY_IN_SECONDS'=>86400,'WEEK_IN_SECONDS'=>604800,'MONTH_IN_SECONDS'=>2592000,'YEAR_IN_SECONDS'=>31536000] as $k=>$v)elvado_c2_def($k,$v);
} }
if(!function_exists('wp_plugin_directory_constants')){ function wp_plugin_directory_constants() {
    elvado_c2_def('WP_CONTENT_URL',content_url());
    elvado_c2_def('WP_PLUGIN_DIR',WP_CONTENT_DIR.'/plugins');elvado_c2_def('WP_PLUGIN_URL',WP_CONTENT_URL.'/plugins');elvado_c2_def('PLUGINDIR','wp-content/plugins');
    elvado_c2_def('WPMU_PLUGIN_DIR',WP_CONTENT_DIR.'/mu-plugins');elvado_c2_def('WPMU_PLUGIN_URL',WP_CONTENT_URL.'/mu-plugins');elvado_c2_def('MUPLUGINDIR','wp-content/mu-plugins');
} }
if(!function_exists('wp_cookie_constants')){ function wp_cookie_constants() {
    $h=md5((string)get_option('siteurl'));elvado_c2_def('COOKIEHASH',$h);
    elvado_c2_def('USER_COOKIE','wordpressuser_'.COOKIEHASH);elvado_c2_def('PASS_COOKIE','wordpresspass_'.COOKIEHASH);
    elvado_c2_def('AUTH_COOKIE','wordpress_'.COOKIEHASH);elvado_c2_def('SECURE_AUTH_COOKIE','wordpress_sec_'.COOKIEHASH);
    elvado_c2_def('LOGGED_IN_COOKIE','wordpress_logged_in_'.COOKIEHASH);elvado_c2_def('TEST_COOKIE','wordpress_test_cookie');
    $p=(string)wp_parse_url(site_url(),PHP_URL_PATH);$p=$p===''?'/':trailingslashit($p);
    elvado_c2_def('COOKIEPATH',$p);elvado_c2_def('SITECOOKIEPATH',$p);elvado_c2_def('ADMIN_COOKIE_PATH',$p.'wp-admin');elvado_c2_def('PLUGINS_COOKIE_PATH',(string)preg_replace('#https?://[^/]+#i','',WP_PLUGIN_URL));
    elvado_c2_def('COOKIE_DOMAIN',false);elvado_c2_def('RECOVERY_MODE_COOKIE','wordpress_rec_'.COOKIEHASH);
} }
if(!function_exists('wp_ssl_constants')){ function wp_ssl_constants() { elvado_c2_def('FORCE_SSL_ADMIN',false); } }
if(!function_exists('wp_functionality_constants')){ function wp_functionality_constants() {
    elvado_c2_def('AUTOSAVE_INTERVAL',60);elvado_c2_def('EMPTY_TRASH_DAYS',30);elvado_c2_def('WP_POST_REVISIONS',true);elvado_c2_def('WP_CRON_LOCK_TIMEOUT',60);
} }
if(!function_exists('wp_templating_constants')){ function wp_templating_constants() {
    elvado_c2_def('TEMPLATEPATH',get_template_directory());elvado_c2_def('STYLESHEETPATH',get_stylesheet_directory());elvado_c2_def('WP_DEFAULT_THEME','twentytwentyfive');
} }

/* ───────── UTF-8-Hilfen ───────── */
if(!function_exists('_wp_can_use_pcre_u')){ function _wp_can_use_pcre_u($set=null) {
    static $u='undefined';
    if(is_bool($set))$u=$set;
    if($u==='undefined')$u=@preg_match('/^./u','a')===1&&@preg_match('/^\pL/u','a')===1;
    return $u;
} }
if(!function_exists('_is_utf8_charset')){ function _is_utf8_charset($charset_slug) { return in_array(strtolower((string)$charset_slug),['utf8','utf-8'],true); } }
if(!function_exists('_mb_substr')){ function _mb_substr($str, $start, $length=null, $encoding=null) {
    if(!_is_utf8_charset($encoding??get_option('blog_charset','UTF-8'))&&function_exists('mb_substr'))return mb_substr((string)$str,(int)$start,$length,$encoding);
    $c=preg_split('//u',(string)$str,-1,PREG_SPLIT_NO_EMPTY);if($c===false)return substr((string)$str,(int)$start,$length);
    return implode('',array_slice($c,(int)$start,$length));
} }
if(!function_exists('_mb_strlen')){ function _mb_strlen($str, $encoding=null) {
    if(!_is_utf8_charset($encoding??get_option('blog_charset','UTF-8'))&&function_exists('mb_strlen'))return mb_strlen((string)$str,$encoding);
    $c=preg_split('//u',(string)$str,-1,PREG_SPLIT_NO_EMPTY);return $c===false?strlen((string)$str):count($c);
} }
if(!function_exists('get_file')){ function get_file($path) {   // Zeilen einer Datei (auch .gz oder http/https) als Liste; false bei Fehler
    $path=(string)$path;
    if(preg_match('~^https?://~i',$path)){ $r=wp_remote_get($path);if(is_wp_error($r))return false;$b=wp_remote_retrieve_body($r);return preg_split('/(?<=\n)/',$b,-1,PREG_SPLIT_NO_EMPTY); }
    if(!is_file($path)||!is_readable($path))return false;
    $l=str_ends_with(strtolower($path),'.gz')&&function_exists('gzfile')?@gzfile($path):@file($path);
    return $l===false?false:$l;
} }

/* ───────── AVIF-Leser (Tile, Prop, Dim_Prop, Chan_Prop, Features, Box, Parser) ───────── */
if(!function_exists('read_big_endian')){ function read_big_endian($input, $num_bytes) {   // 1–4 Byte lange Zahl, Big-Endian
    $num_bytes=(int)$num_bytes;$input=(string)$input;
    if($num_bytes<1||$num_bytes>4||strlen($input)<$num_bytes)return false;
    $v=0;for($i=0;$i<$num_bytes;$i++)$v=($v<<8)|ord($input[$i]);
    return $v;
} }
if(!function_exists('read')){ function read($handle, $num_bytes) {   // exakt $num_bytes lesen oder false
    if($num_bytes<=0)return '';
    $d=is_resource($handle)?fread($handle,(int)$num_bytes):false;
    return is_string($d)&&strlen($d)===(int)$num_bytes?$d:false;
} }
if(!function_exists('skip')){ function skip($handle, $num_bytes) { return is_resource($handle)&&fseek($handle,(int)$num_bytes,SEEK_CUR)===0; } }
if(!class_exists('Tile')){ class Tile { public $tile_item_id=0;public $parent_item_id=0;public $subtile_count=0;public $width=0;public $height=0;public $bit_depth=0;public $num_channels=0; } }
if(!class_exists('Prop')){ class Prop { public $type='';public $size=0;public $property_index=0; } }
if(!class_exists('Dim_Prop')){ class Dim_Prop { public $width=0;public $height=0; } }
if(!class_exists('Chan_Prop')){ class Chan_Prop { public $bit_depth=0;public $num_channels=0; } }
if(!class_exists('Features')){ class Features { public $has_primary_item=false;public $primary_item_id=0;public $has_alpha=false;public $primary_item_features=null;public $tiles=[]; public function __construct() { $this->primary_item_features=new Tile(); } } }
if(!class_exists('Box')){
class Box {
    public $size=0;public $type='';public $content_size=0;public $header_size=8;
    public function __construct($handle, $num_bytes_left) {
        $h=read($handle,8);if($h===false)return;
        $this->size=(int)read_big_endian(substr($h,0,4),4);$this->type=substr($h,4,4);
        if($this->size===1){ $e=read($handle,8);if($e===false)return;$this->size=(int)(read_big_endian(substr($e,0,4),4)*4294967296+read_big_endian(substr($e,4,4),4));$this->header_size=16; }
        elseif($this->size===0)$this->size=(int)$num_bytes_left;
        $this->content_size=max(0,$this->size-$this->header_size);
    }
}
}
if(!class_exists('Parser')){
class Parser {
    public $handle;public $num_bytes;public $features;
    public function __construct($handle, $num_bytes=PHP_INT_MAX) { $this->handle=$handle;$this->num_bytes=$num_bytes;$this->features=new Features(); }
    /** 'ok', 'invalid' oder 'truncated'. */
    public function parse_ftyp() {
        $b=new Box($this->handle,$this->num_bytes);if($b->type!=='ftyp')return 'invalid';
        $c=read($this->handle,$b->content_size);return $c===false?'truncated':(preg_match('/avif|avis/',substr($c,0,4).substr($c,8))?'ok':'invalid');
    }
    public function parse_file() {
        rewind($this->handle);$st=$this->parse_ftyp();if($st!=='ok')return $st;
        $data=stream_get_contents($this->handle,min($this->num_bytes,1048576));if(!is_string($data))return 'truncated';
        $f=$this->features;
        if(($i=strpos($data,'pitm'))!==false&&strlen($data)>=$i+10){ $f->has_primary_item=true;$f->primary_item_id=(int)read_big_endian(substr($data,$i+8,2),2); }
        if(($i=strpos($data,'ispe'))!==false&&strlen($data)>=$i+16){ $f->primary_item_features->width=(int)read_big_endian(substr($data,$i+8,4),4);$f->primary_item_features->height=(int)read_big_endian(substr($data,$i+12,4),4); }
        if(($i=strpos($data,'pixi'))!==false&&strlen($data)>=$i+9){ $n=ord($data[$i+8]);$f->primary_item_features->num_channels=$n;if($n>0&&strlen($data)>$i+9)$f->primary_item_features->bit_depth=ord($data[$i+9]); }
        $f->has_alpha=strpos($data,'urn:mpeg:mpegB:cicp:systems:auxiliary:alpha')!==false||strpos($data,'urn:mpeg:hevc:2015:auxid:1')!==false;
        return $f->primary_item_features->width>0?'ok':'invalid';
    }
}
}

/* ───────── Speculation Rules ───────── */
if(!class_exists('WP_URL_Pattern_Prefixer')){
class WP_URL_Pattern_Prefixer {
    private $contexts;
    public function __construct($contexts=null) { $this->contexts=$contexts??self::get_default_contexts(); }
    public static function get_default_contexts() {
        return ['home'=>trailingslashit((string)wp_parse_url(home_url('/'),PHP_URL_PATH)?:'/'),'site'=>trailingslashit((string)wp_parse_url(site_url('/'),PHP_URL_PATH)?:'/'),'uploads'=>trailingslashit((string)wp_parse_url(wp_upload_dir(null,false)['baseurl'],PHP_URL_PATH)?:'/'),
            'content'=>trailingslashit((string)wp_parse_url(content_url('/'),PHP_URL_PATH)?:'/'),'plugins'=>trailingslashit((string)wp_parse_url(plugins_url('/'),PHP_URL_PATH)?:'/')];
    }
    public function prefix_path_pattern($path_pattern, $context='home') {
        $c=$this->contexts[$context]??'/';
        return rtrim($c,'/').'/'.ltrim((string)$path_pattern,'/');
    }
    public static function escape_pattern_string($str) { return (string)preg_replace('/([+*?:{}()\\\\])/','\\\\$1',(string)$str); }
}
}
if(!class_exists('WP_Speculation_Rules')){
class WP_Speculation_Rules implements JsonSerializable {
    private $rules_by_mode=[];
    public static function is_valid_mode($mode) { return in_array($mode,['prefetch','prerender'],true); }
    public static function is_valid_eagerness($eagerness) { return in_array($eagerness,['immediate','eager','moderate','conservative'],true); }
    public static function is_valid_id($id) { return is_string($id)&&(bool)preg_match('/^[a-z][a-z0-9_-]+$/',$id); }
    public function add_rule($mode, $id, $rule) {
        if(!self::is_valid_mode($mode)||!self::is_valid_id($id)||!is_array($rule))return false;
        if(isset($rule['eagerness'])&&!self::is_valid_eagerness($rule['eagerness']))return false;
        if(isset($this->rules_by_mode[$mode][$id]))return false;
        if(!isset($rule['source']))$rule['source']=isset($rule['urls'])?'list':'document';
        $this->rules_by_mode[$mode][$id]=$rule;return true;
    }
    public function get_rules($mode='') { return $mode===''?$this->rules_by_mode:($this->rules_by_mode[$mode]??[]); }
    public function jsonSerialize(): array { $o=[];foreach($this->rules_by_mode as $m=>$rules)$o[$m]=array_values($rules);return $o; }
}
}
if(!function_exists('wp_get_speculation_rules_configuration')){ function wp_get_speculation_rules_configuration() {
    if(!(int)get_option('blog_public',1)||!get_option('permalink_structure')){ return null; }   // nur bei öffentlichen Websites mit schönen Links
    $c=['mode'=>'auto','eagerness'=>'moderate'];
    $f=apply_filters('wp_speculation_rules_configuration',$c);
    if($f===null)return null;
    if(!is_array($f))return $c;
    if(!isset($f['mode'])||!in_array($f['mode'],['auto','prefetch','prerender'],true))$f['mode']='auto';
    if(!isset($f['eagerness'])||!WP_Speculation_Rules::is_valid_eagerness($f['eagerness']))$f['eagerness']='moderate';
    return $f;
} }
if(!function_exists('wp_get_speculation_rules')){ function wp_get_speculation_rules() {
    $c=wp_get_speculation_rules_configuration();if(!is_array($c))return null;
    $mode=$c['mode']==='auto'?'prefetch':$c['mode'];
    $p=new WP_URL_Pattern_Prefixer();
    $ex=apply_filters('wp_speculation_rules_href_exclude_paths',[$p->prefix_path_pattern('/wp-*.php','site'),$p->prefix_path_pattern('/wp-admin/*','site'),$p->prefix_path_pattern('/*\\?(.+)','home'),$p->prefix_path_pattern('/wp-content/*','content')],$mode);
    $rules=new WP_Speculation_Rules();
    $rules->add_rule($mode,'auto',['source'=>'document','where'=>['and'=>[['href_matches'=>$p->prefix_path_pattern('/*')],['not'=>['href_matches'=>array_values((array)$ex)]],['not'=>['selector_matches'=>'a[rel~="nofollow"]']],['not'=>['selector_matches'=>'.no-prefetch, .no-prefetch a']]]],'eagerness'=>$c['eagerness']]);
    do_action('wp_load_speculation_rules',$rules);
    return $rules;
} }
if(!function_exists('wp_print_speculation_rules')){ function wp_print_speculation_rules() {
    $r=wp_get_speculation_rules();if(!$r)return;
    $j=wp_json_encode($r,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG);
    if(!is_string($j)||$j==='[]'||$j==='{}')return;
    echo '<script type="speculationrules">'.$j."</script>\n";
} }

/* ───────── Sitemaps (schlanke Fassung) ───────── */
if(!class_exists('WP_Sitemaps_Provider')){
abstract class WP_Sitemaps_Provider {
    protected $name='';protected $object_type='';
    public function get_object_type() { return $this->object_type; }
    public function get_name() { return $this->name; }
    abstract public function get_url_list($page_num, $object_subtype='');
    abstract public function get_max_num_pages($object_subtype='');
    abstract public function get_object_subtypes();
    public function get_sitemap_type_data() {
        $o=[];$sub=$this->get_object_subtypes();
        foreach($sub?array_keys($sub):[''] as $s)$o[]=['name'=>$s,'pages'=>$this->get_max_num_pages($s)];
        return $o;
    }
    public function get_sitemap_url($name, $page) { return get_sitemap_url($this->name,$name,$page); }
}
}
if(!class_exists('WP_Sitemaps_Posts')){
class WP_Sitemaps_Posts extends WP_Sitemaps_Provider {
    protected $name='posts';protected $object_type='post';
    public function get_object_subtypes() { $t=get_post_types(['public'=>true],'objects');unset($t['attachment']);return apply_filters('wp_sitemaps_post_types',$t); }
    public function get_url_list($page_num, $object_subtype='') {
        $q=new WP_Query(['post_type'=>$object_subtype?:'post','post_status'=>'publish','posts_per_page'=>wp_sitemaps_get_max_urls($this->object_type),'paged'=>max(1,(int)$page_num),'orderby'=>'ID','order'=>'ASC','no_found_rows'=>true]);
        $o=[];foreach($q->posts as $p)$o[]=['loc'=>get_permalink($p),'lastmod'=>mysql2date('c',$p->post_modified_gmt,false)];
        return $o;
    }
    public function get_max_num_pages($object_subtype='') {
        $q=new WP_Query(['post_type'=>$object_subtype?:'post','post_status'=>'publish','posts_per_page'=>1,'fields'=>'ids']);
        return (int)ceil(max(0,(int)$q->found_posts)/wp_sitemaps_get_max_urls($this->object_type));
    }
}
}
if(!class_exists('WP_Sitemaps_Taxonomies')){
class WP_Sitemaps_Taxonomies extends WP_Sitemaps_Provider {
    protected $name='taxonomies';protected $object_type='term';
    public function get_object_subtypes() { return apply_filters('wp_sitemaps_taxonomies',get_taxonomies(['public'=>true],'objects')); }
    public function get_url_list($page_num, $object_subtype='') {
        $max=wp_sitemaps_get_max_urls($this->object_type);$terms=get_terms(['taxonomy'=>$object_subtype?:'category','hide_empty'=>true,'number'=>$max,'offset'=>($page_num-1)*$max]);
        $o=[];foreach(is_wp_error($terms)?[]:$terms as $t)$o[]=['loc'=>get_term_link($t)];return $o;
    }
    public function get_max_num_pages($object_subtype='') { $n=get_terms(['taxonomy'=>$object_subtype?:'category','hide_empty'=>true,'fields'=>'count']);return (int)ceil((is_wp_error($n)?0:(int)$n)/wp_sitemaps_get_max_urls($this->object_type)); }
}
}
if(!class_exists('WP_Sitemaps_Users')){
class WP_Sitemaps_Users extends WP_Sitemaps_Provider {
    protected $name='users';protected $object_type='user';
    public function get_object_subtypes() { return []; }
    public function get_url_list($page_num, $object_subtype='') {
        $max=wp_sitemaps_get_max_urls($this->object_type);$u=get_users(['has_published_posts'=>true,'number'=>$max,'offset'=>($page_num-1)*$max,'orderby'=>'ID']);
        $o=[];foreach($u as $x)$o[]=['loc'=>get_author_posts_url($x->ID)];return $o;
    }
    public function get_max_num_pages($object_subtype='') { $n=count(get_users(['has_published_posts'=>true,'fields'=>'ID']));return (int)ceil($n/wp_sitemaps_get_max_urls($this->object_type)); }
}
}
if(!class_exists('WP_Sitemaps_Registry')){
class WP_Sitemaps_Registry {
    private $providers=[];
    public function add_provider($name, $provider) { if(!$provider instanceof WP_Sitemaps_Provider)return false;$this->providers[$name]=$provider;return true; }
    public function get_provider($name) { return $this->providers[$name]??null; }
    public function get_providers() { return $this->providers; }
}
}
if(!class_exists('WP_Sitemaps')){
class WP_Sitemaps {
    public $registry;
    public function __construct() { $this->registry=new WP_Sitemaps_Registry(); }
    public function init() {
        foreach(['posts'=>'WP_Sitemaps_Posts','taxonomies'=>'WP_Sitemaps_Taxonomies','users'=>'WP_Sitemaps_Users'] as $n=>$c)
            if(apply_filters('wp_sitemaps_register_default_provider',true,$n))$this->registry->add_provider($n,new $c());
    }
}
}
if(!function_exists('wp_sitemaps_get_server')){ function wp_sitemaps_get_server() {
    global $wp_sitemaps;
    if(!$wp_sitemaps instanceof WP_Sitemaps){ if(!apply_filters('wp_sitemaps_enabled',(bool)get_option('blog_public',1)))return null;$wp_sitemaps=new WP_Sitemaps();$wp_sitemaps->init(); }
    return $wp_sitemaps;
} }
if(!function_exists('wp_get_sitemap_providers')){ function wp_get_sitemap_providers() { $s=wp_sitemaps_get_server();return $s?$s->registry->get_providers():[]; } }
if(!function_exists('wp_register_sitemap_provider')){ function wp_register_sitemap_provider($name, $provider) { $s=wp_sitemaps_get_server();return $s?$s->registry->add_provider($name,$provider):false; } }
if(!function_exists('wp_sitemaps_get_max_urls')){ function wp_sitemaps_get_max_urls($object_type) { return (int)apply_filters('wp_sitemaps_max_urls',2000,$object_type); } }
if(!function_exists('get_sitemap_url')){ function get_sitemap_url($name, $subtype_name='', $page=1) {
    $s=wp_sitemaps_get_server();if(!$s)return false;
    if($name==='index')return home_url('/wp-sitemap.xml');
    $p=$s->registry->get_provider($name);if(!$p)return false;
    if($subtype_name!==''&&!isset($p->get_object_subtypes()[$subtype_name]))return false;
    $page=absint($page);if($page<1)$page=1;
    return home_url('/wp-sitemap-'.$name.($subtype_name!==''?'-'.$subtype_name:'').'-'.$page.'.xml');
} }

<?php
// WordPress-kompatible Optionen, Transients und Objekt-Cache. Speicherung: cms/data/.wp/options.json
// (Werte PHP-serialisiert, damit Arrays/Objekte/Typen unverändert zurückkommen). Stufe 2 kann auf $wpdb umstellen.

function rrw_wp_opts_file(): string { return rtrim(RRW_WP_DATA,'/').'/options.json'; }
function rrw_wp_opts_load(bool $reload=false): array {
    static $cache=null;
    if($cache===null||$reload){
        $cache=[];$f=rrw_wp_opts_file();
        if(is_file($f)){$d=json_decode((string)@file_get_contents($f),true);if(is_array($d))$cache=$d;}
    }
    return $cache;
}
function rrw_wp_opts_mutate(callable $fn) {
    $dir=RRW_WP_DATA;if(!is_dir($dir)){@mkdir($dir,0775,true);rrw_wp_protect_dir($dir);}
    $h=@fopen($dir.'/.options.lock','c');if($h)@flock($h,LOCK_EX);
    try{
        $all=rrw_wp_opts_load(true);$result=$fn($all);
        $tmp=rrw_wp_opts_file().'.'.bin2hex(random_bytes(3)).'.tmp';
        if(@file_put_contents($tmp,json_encode($all,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))===false)return false;
        @rename($tmp,rrw_wp_opts_file());
        rrw_wp_opts_load(true);
        return $result;
    } finally { if($h){@flock($h,LOCK_UN);@fclose($h);} }
}
function rrw_wp_opts_get_raw(string $name) {
    $all=rrw_wp_opts_load();if(!isset($all[$name]))return [false,null];
    $v=@unserialize((string)$all[$name]['v']);
    return [true,$v];
}
function rrw_wp_default_option(string $name) {
    $site=$GLOBALS['RRW_SITE']??[];$home=rrw_wp_home_url();
    if(!$site&&function_exists('rrw_wp_bridge')&&rrw_wp_bridge('options'))$site=rrw_wp_cms_data()['site'];   // Schreibbrücke: Einstellungen direkt aus site.json lesen
    $map=[
        'siteurl'=>$home,'home'=>$home,'blogname'=>(string)($site['portal']['site_name']??'WordPress'),
        'blogdescription'=>(string)($site['portal']['tagline']??''),'admin_email'=>(string)($site['legal']['email']??''),
        'timezone_string'=>'Europe/Berlin','gmt_offset'=>0,'date_format'=>'d.m.Y','time_format'=>'H:i','start_of_week'=>1,
        'posts_per_page'=>10,'posts_per_rss'=>10,'show_on_front'=>'posts','page_on_front'=>0,'page_for_posts'=>0,
        'permalink_structure'=>'/%postname%/','blog_charset'=>'UTF-8','WPLANG'=>'de_DE','blog_public'=>1,'users_can_register'=>0,
        'default_role'=>'subscriber','template'=>'rrw-classic','stylesheet'=>'rrw-classic',
        'comment_registration'=>0,'thread_comments'=>0,'page_comments'=>0,'comments_per_page'=>50,'default_comment_status'=>'open',
        'use_smilies'=>0,'uploads_use_yearmonth_folders'=>1,'category_base'=>'','tag_base'=>'','upload_path'=>'','db_version'=>57155,
        'active_plugins'=>[],'sidebars_widgets'=>[],'widget_text'=>[],'rewrite_rules'=>'','image_default_link_type'=>'none',
    ];
    if(function_exists('rrw_wp_bridge')&&isset(RRW_WP_BRIDGE_TYPED[$name])&&rrw_wp_bridge('options')&&$site)return rrw_wp_bridge_typed_get($name,$site);   // Schreibbrücke: Kommentare, Feed-Länge, Suchmaschinen-Sichtbarkeit aus site.json
    if(($name==='timezone_string'||$name==='WPLANG')&&function_exists('rrw_wp_bridge_sys_read')&&rrw_wp_bridge('options')){   // Schreibbrücke: Zeitzone/Sprache aus system.local.json (nur, wenn dort gesetzt)
        $v=rrw_wp_bridge_sys_read($name);if($v!=='')return $v;
    }
    return array_key_exists($name,$map)?$map[$name]:false;
}
function get_option($option, $default_value=false) {
    $option=trim((string)$option);if($option==='')return false;
    $pre=apply_filters("pre_option_{$option}",false,$option,$default_value);
    if(false!==$pre)return $pre;
    [$found,$value]=rrw_wp_opts_get_raw($option);
    if(!$found){
        $def=rrw_wp_default_option($option);
        $value=$def!==false?$def:apply_filters("default_option_{$option}",$default_value,$option,false);
    }
    if(is_object($value))$value=clone $value;
    return apply_filters("option_{$option}",$value,$option);
}
function add_option($option, $value='', $deprecated='', $autoload='yes') {
    $option=trim((string)$option);if($option==='')return false;
    [$found]=rrw_wp_opts_get_raw($option);if($found)return false;
    $value=sanitize_option($option,$value);
    if((isset(RRW_WP_BRIDGE_OPTS[$option])||isset(RRW_WP_BRIDGE_SYS[$option])||isset(RRW_WP_BRIDGE_TYPED[$option]))&&rrw_wp_bridge('options')){   // Schreibbrücke: blogname, blogdescription, admin_email liegen in site.json
        if(trim((string)get_option($option,''))!=='')return false;
        do_action('add_option',$option,$value);
        if(!rrw_wp_bridge_option_write($option,$value))return false;
        do_action("add_option_{$option}",$option,$value);do_action('added_option',$option,$value);return true;
    }
    do_action('add_option',$option,$value);
    rrw_wp_opts_mutate(function(&$all) use($option,$value,$autoload){ $all[$option]=['v'=>serialize($value),'a'=>$autoload===false||$autoload==='no'?'no':'yes']; });
    do_action("add_option_{$option}",$option,$value);
    do_action('added_option',$option,$value);
    return true;
}
function update_option($option, $value, $autoload=null) {
    $option=trim((string)$option);if($option==='')return false;
    $value=sanitize_option($option,$value);
    $old=get_option($option);
    $value=apply_filters("pre_update_option_{$option}",$value,$old,$option);
    $value=apply_filters('pre_update_option',$value,$option,$old);
    if((isset(RRW_WP_BRIDGE_OPTS[$option])||isset(RRW_WP_BRIDGE_SYS[$option])||isset(RRW_WP_BRIDGE_TYPED[$option]))&&rrw_wp_bridge('options')){   // Schreibbrücke
        if(rrw_wp_bridge_option_value($option,$value)===null)return false;   // ungültige E-Mail-Adresse: Wert bleibt
        if($value===$old||maybe_serialize($value)===maybe_serialize($old)){ [$legacy]=rrw_wp_opts_get_raw($option);if(!$legacy)return false; }
        do_action('update_option',$option,$old,$value);
        if(!rrw_wp_bridge_option_write($option,$value))return false;
        do_action("update_option_{$option}",$old,$value,$option);do_action('updated_option',$option,$old,$value);return true;
    }
    if($value===$old||maybe_serialize($value)===maybe_serialize($old)){ [$found]=rrw_wp_opts_get_raw($option); if($found)return false; }
    [$found]=rrw_wp_opts_get_raw($option);
    if(!$found)return add_option($option,$value,'',$autoload??'yes');
    do_action('update_option',$option,$old,$value);
    rrw_wp_opts_mutate(function(&$all) use($option,$value,$autoload){ $all[$option]['v']=serialize($value); if($autoload!==null)$all[$option]['a']=($autoload===false||$autoload==='no')?'no':'yes'; });
    do_action("update_option_{$option}",$old,$value,$option);
    do_action('updated_option',$option,$old,$value);
    return true;
}
function delete_option($option) {
    $option=trim((string)$option);if($option==='')return false;
    [$found]=rrw_wp_opts_get_raw($option);if(!$found)return false;
    do_action('delete_option',$option);
    rrw_wp_opts_mutate(function(&$all) use($option){ unset($all[$option]); });
    do_action("delete_option_{$option}",$option);
    do_action('deleted_option',$option);
    return true;
}
function sanitize_option($option, $value) { return apply_filters("sanitize_option_{$option}",$value,$option,$value); }
function wp_load_alloptions($force_cache=false) {
    $o=[];foreach(rrw_wp_opts_load() as $k=>$x)if(($x['a']??'yes')==='yes')$o[$k]=@unserialize((string)$x['v']);return $o;
}
function get_site_option($option, $default_value=false, $deprecated=true) { return get_option($option,$default_value); }
function add_site_option($option, $value) { return add_option($option,$value); }
function update_site_option($option, $value) { return update_option($option,$value); }
function delete_site_option($option) { return delete_option($option); }

/* Transients */
function set_transient($transient, $value, $expiration=0) {
    $expiration=(int)$expiration;
    $value=apply_filters("pre_set_transient_{$transient}",$value,$expiration,$transient);
    update_option("_transient_{$transient}",$value,'no');
    update_option("_transient_timeout_{$transient}",$expiration>0?time()+$expiration:0,'no');
    do_action("set_transient_{$transient}",$value,$expiration,$transient);
    return true;
}
function get_transient($transient) {
    $pre=apply_filters("pre_transient_{$transient}",false,$transient);if(false!==$pre)return $pre;
    $timeout=get_option("_transient_timeout_{$transient}",false);
    if($timeout!==false&&(int)$timeout>0&&(int)$timeout<time()){delete_transient($transient);return false;}
    return apply_filters("transient_{$transient}",get_option("_transient_{$transient}",false),$transient);
}
function delete_transient($transient) {
    $a=delete_option("_transient_{$transient}");delete_option("_transient_timeout_{$transient}");
    if($a)do_action("deleted_transient",$transient);return $a;
}
function set_site_transient($t,$v,$e=0) { return set_transient($t,$v,$e); }
function get_site_transient($t) { return get_transient($t); }
function delete_site_transient($t) { return delete_transient($t); }

/* Objekt-Cache (pro Anfrage, im Arbeitsspeicher) */
function wp_cache_init() {}
function wp_cache_add($key,$data,$group='',$expire=0) { global $rrw_wp_cache; if(isset($rrw_wp_cache[$group][$key]))return false; $rrw_wp_cache[$group][$key]=$data; return true; }
function wp_cache_set($key,$data,$group='',$expire=0) { global $rrw_wp_cache; $rrw_wp_cache[$group][$key]=$data; return true; }
function wp_cache_get($key,$group='',$force=false,&$found=null) { global $rrw_wp_cache; $found=isset($rrw_wp_cache[$group])&&array_key_exists($key,$rrw_wp_cache[$group]); return $found?$rrw_wp_cache[$group][$key]:false; }
function wp_cache_delete($key,$group='') { global $rrw_wp_cache; unset($rrw_wp_cache[$group][$key]); return true; }
function wp_cache_flush() { global $rrw_wp_cache; $rrw_wp_cache=[]; return true; }
function wp_cache_incr($key,$offset=1,$group='') { global $rrw_wp_cache; $v=(int)($rrw_wp_cache[$group][$key]??0)+$offset; $rrw_wp_cache[$group][$key]=$v; return $v; }
function wp_cache_decr($key,$offset=1,$group='') { return wp_cache_incr($key,-$offset,$group); }
function wp_cache_add_global_groups($groups) {}
function wp_cache_add_non_persistent_groups($groups) {}
function wp_suspend_cache_addition($suspend=null) { return false; }
function wp_using_ext_object_cache($using=null) { return false; }

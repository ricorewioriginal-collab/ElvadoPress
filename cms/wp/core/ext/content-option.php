<?php
// Ergänzende Options-Funktionen (wp-includes/option.php): Autoload-Werte, Sammel-Abfragen, Transient-Aufräumen, Benutzer-Einstellungen, Standard-Einstellungen.
// Optionen liegen in options.json (Feld „a“ = Autoload yes/no); Caches gibt es nicht, deshalb sind die „prime“-Funktionen leer.

if(!function_exists('wp_autoload_values_to_autoload')){
    function wp_autoload_values_to_autoload() { return apply_filters('wp_autoload_values_to_autoload',['yes','on','auto-on','auto']); }
}
if(!function_exists('wp_protect_special_option')){
    function wp_protect_special_option($option) {
        if('alloptions'===$option||'notoptions'===$option)wp_die(sprintf('%s ist eine geschützte WordPress-Option und darf nicht geändert werden.',esc_html($option)));
    }
}
if(!function_exists('get_options')){
    function get_options($options) { $r=[];foreach((array)$options as $o)$r[$o]=get_option($o);return $r; }
}
if(!function_exists('wp_prime_option_caches_by_group')){ function wp_prime_option_caches_by_group($group) {} }   // kein Options-Cache
if(!function_exists('wp_prime_site_option_caches')){ function wp_prime_site_option_caches(array $options) {} }
if(!function_exists('wp_prime_network_option_caches')){ function wp_prime_network_option_caches($network_id, array $options) {} }
if(!function_exists('wp_load_core_site_options')){ function wp_load_core_site_options($network_id=null) {} }   // nur für Multisite
if(!function_exists('form_option')){ function form_option($option) { $v=get_option($option);echo esc_attr(is_scalar($v)?(string)$v:maybe_serialize($v)); } }
if(!function_exists('wp_set_option_autoload_values')){
    /** @param array $options Option => Autoload (true/false, yes/no, on/off, auto …). @return bool[] Option => wurde geändert. */
    function wp_set_option_autoload_values(array $options) {
        $res=[];$yes=wp_autoload_values_to_autoload();$todo=[];
        foreach($options as $o=>$al){
            wp_protect_special_option($o);$res[$o]=false;
            $new=(is_bool($al)?($al?'yes':'no'):(in_array(strtolower((string)$al),$yes,true)?'yes':'no'));
            [$found]=elvado_wp_opts_get_raw((string)$o);if(!$found)continue;
            $cur=(elvado_wp_opts_load()[$o]['a']??'yes')==='no'?'no':'yes';
            if($cur!==$new)$todo[$o]=$new;
        }
        if($todo){ elvado_wp_opts_mutate(function(&$all) use($todo){ foreach($todo as $o=>$n)if(isset($all[$o]))$all[$o]['a']=$n; });foreach($todo as $o=>$n)$res[$o]=true; }
        return $res;
    }
}
if(!function_exists('wp_set_options_autoload')){
    function wp_set_options_autoload(array $options, $autoload) { return wp_set_option_autoload_values(array_fill_keys($options,$autoload)); }
}
if(!function_exists('wp_set_option_autoload')){
    function wp_set_option_autoload($option, $autoload) { $r=wp_set_option_autoload_values([$option=>$autoload]);return $r[$option]??false; }
}
if(!function_exists('wp_determine_option_autoload_value')){
    function wp_determine_option_autoload_value($option, $value, $serialized_value, $autoload) {
        if(is_bool($autoload))return $autoload?'on':'off';
        if(null===$autoload||'auto'===$autoload){
            $d=apply_filters('wp_default_autoload_value',null,$option,$value,$serialized_value);
            return is_bool($d)?($d?'on':'off'):'auto';
        }
        return $autoload;
    }
}
if(!function_exists('wp_filter_default_autoload_value_via_option_size')){
    function wp_filter_default_autoload_value_via_option_size($default, $option, $value, $serialized_value) {
        $max=(int)apply_filters('wp_max_autoloaded_option_size',150000);
        return strlen((string)$serialized_value)>$max?false:$default;
    }
}
if(!function_exists('delete_expired_transients')){
    function delete_expired_transients($force_db=false) {
        $n=0;$all=elvado_wp_opts_load(true);$now=time();
        foreach($all as $k=>$row){
            if(!str_starts_with($k,'_transient_timeout_'))continue;
            $t=(int)@unserialize((string)$row['v']);
            if($t>0&&$t<$now){ delete_transient(substr($k,strlen('_transient_timeout_')));$n++; }
        }
        return $n;
    }
}

/* ───────── Benutzer-Einstellungen (Option „user-settings“ als name=wert&…) ───────── */
if(!function_exists('get_all_user_settings')){
    function get_all_user_settings() {
        global $all_user_settings;
        if(is_array($all_user_settings))return $all_user_settings;
        $uid=get_current_user_id();if(!$uid)return $all_user_settings=[];
        $s=get_user_option('user-settings',$uid);$out=[];
        if(is_string($s)&&preg_match('/^[A-Za-z0-9=&_-]+$/',$s))parse_str($s,$out);
        return $all_user_settings=is_array($out)?$out:[];
    }
}
if(!function_exists('wp_set_all_user_settings')){
    function wp_set_all_user_settings($user_settings) {
        global $all_user_settings;$uid=get_current_user_id();if(!$uid)return false;
        $s='';
        foreach((array)$user_settings as $n=>$v){ $n=preg_replace('/[^A-Za-z0-9_-]+/','',(string)$n);$v=preg_replace('/[^A-Za-z0-9_-]+/','',(string)$v);if($n!=='')$s.=$n.'='.$v.'&'; }
        $s=rtrim($s,'&');parse_str($s,$out);$all_user_settings=$out;
        update_user_option($uid,'user-settings',$s,false);update_user_option($uid,'user-settings-time',time(),false);
        return true;
    }
}
if(!function_exists('delete_user_setting')){
    function delete_user_setting($names) {
        $all=get_all_user_settings();$del=false;
        foreach((array)$names as $n)if(isset($all[$n])){ unset($all[$n]);$del=true; }
        if($del)wp_set_all_user_settings($all);
    }
}
if(!function_exists('delete_all_user_settings')){
    function delete_all_user_settings() {
        global $all_user_settings;$uid=get_current_user_id();if(!$uid)return;
        update_user_option($uid,'user-settings','',false);$all_user_settings=[];
    }
}
if(!function_exists('wp_user_settings')){
    // Das Einstellungs-Cookie wird nicht gesetzt (Einstellungen liegen in der Benutzer-Option); die Funktion lädt sie nur vor.
    function wp_user_settings() { if(!is_admin()||wp_doing_ajax())return;get_all_user_settings(); }
}

/* ───────── Standard-Einstellungen ───────── */
if(!function_exists('register_initial_settings')){
    function register_initial_settings() {
        $s=[['general','blogname','string',''],['general','blogdescription','string',''],['general','admin_email','string',''],['general','users_can_register','boolean',false],['general','default_role','string','subscriber'],
            ['general','timezone_string','string',''],['general','date_format','string','d.m.Y'],['general','time_format','string','H:i'],['general','start_of_week','integer',1],['general','WPLANG','string',''],
            ['reading','posts_per_page','integer',10],['reading','show_on_front','string','posts'],['reading','page_on_front','integer',0],['reading','page_for_posts','integer',0],
            ['discussion','default_comment_status','string','open'],['discussion','default_ping_status','string','open'],['writing','default_category','integer',0]];
        foreach($s as [$g,$n,$t,$d])register_setting($g,$n,['type'=>$t,'default'=>$d,'show_in_rest'=>in_array($n,['blogname','blogdescription','admin_email','timezone_string','date_format','time_format','start_of_week','posts_per_page','default_category'],true)]);
    }
}
if(!function_exists('filter_default_option')){
    function filter_default_option($default_value, $option, $passed_default) {
        if($passed_default)return $default_value;
        $reg=get_registered_settings();
        return isset($reg[$option])&&is_array($reg[$option])&&array_key_exists('default',$reg[$option])?$reg[$option]['default']:$default_value;
    }
}

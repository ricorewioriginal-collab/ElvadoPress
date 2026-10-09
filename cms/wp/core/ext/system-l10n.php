<?php
// Ergänzende WordPress-Funktionen (Bereich System, Teil 6): Übersetzung (l10n) – Textdomänen, Skript-Übersetzungen, Sprachwahl.

if(!class_exists('NOOP_Translations')){
class NOOP_Translations {
    public $entries=[];public $headers=[];
    public function add_entry($entry) { return true; }
    public function set_header($header, $value) {}
    public function set_headers($headers) {}
    public function get_header($header) { return false; }
    public function translate_entry(&$entry) { return false; }
    public function translate($singular, $context=null) { return $singular; }
    public function select_plural_form($count) { return (int)$count===1?0:1; }
    public function get_plural_forms_count() { return 2; }
    public function translate_plural($singular, $plural, $count, $context=null) { return (int)$count===1?$singular:$plural; }
    public function merge_with(&$other) {}
}
}
if(!class_exists('ELVADO_Ext_Translations')){
/** Übersetzungsobjekt einer geladenen Textdomäne (liest über die Übersetzungs-Schicht des CMS). */
class ELVADO_Ext_Translations extends NOOP_Translations {
    public $domain;
    public function __construct($domain='default') { $this->domain=(string)$domain; }
    public function translate($singular, $context=null) { return elvado_wp_tr((string)$singular,$this->domain,$context);}
    public function translate_plural($singular, $plural, $count, $context=null) { return elvado_wp_trn((string)$singular,(string)$plural,(int)$count,$this->domain,$context); }
}
}

if(!function_exists('before_last_bar')){ function before_last_bar($text) { $p=strrpos((string)$text,'|');return $p===false?(string)$text:substr((string)$text,0,$p); } }
if(!function_exists('load_default_textdomain')){ function load_default_textdomain($locale=null) {
    $locale=$locale?:determine_locale();if($locale==='en_US')return true;
    return load_textdomain('default',WP_LANG_DIR.'/'.$locale.'.mo',$locale);
} }
if(!function_exists('_load_textdomain_just_in_time')){ function _load_textdomain_just_in_time($domain) { return elvado_wp_mo_domain((string)$domain)!==null; } }
if(!function_exists('get_translations_for_domain')){ function get_translations_for_domain($domain) {
    return isset($GLOBALS['elvado_wp_mo'][$domain])||elvado_wp_mo_domain((string)$domain)?new ELVADO_Ext_Translations((string)$domain):new NOOP_Translations();
} }
if(!function_exists('has_translation')){ function has_translation($text, $domain='default', $context='') {   // gibt es für den Text eine Übersetzung in der geladenen Sprache?
    if(!elvado_wp_locale_active())return false;
    $mo=elvado_wp_mo_domain($domain===''?'default':(string)$domain);if(!$mo)return false;
    $r=$mo->get(($context!==''?$context."\x04":'').$text);return $r!==null&&($r[0]??'')!=='';
} }
if(!function_exists('load_script_translations')){ function load_script_translations($file, $handle, $domain) {
    $pre=apply_filters('pre_load_script_translations',null,$file,$handle,$domain);if($pre!==null)return $pre;
    $file=apply_filters('load_script_translation_file',$file,$handle,$domain);
    if(!$file||!is_readable($file))return false;
    return apply_filters('load_script_translations',(string)file_get_contents($file),$file,$handle,$domain);
} }
if(!function_exists('load_script_textdomain')){ function load_script_textdomain($handle, $domain='default', $path='') {
    $src=(string)($GLOBALS['elvado_wp_scripts']['reg'][$handle]['src']??'');if($src===''||!is_string($src))return false;
    $locale=determine_locale();if($locale==='en_US')return false;
    $dir=untrailingslashit($path?:WP_LANG_DIR);$base=trailingslashit(site_url());
    $rel=str_starts_with($src,$base)?substr($src,strlen($base)):ltrim((string)parse_url($src,PHP_URL_PATH),'/');
    $rel=preg_replace('/\.min\.js$/','.js',$rel);
    $domain=(string)$domain;
    foreach([$dir.'/'.$domain.'-'.$locale.'-'.$handle.'.json',$dir.'/'.$domain.'-'.$locale.'-'.md5($rel).'.json'] as $f){ $r=load_script_translations($f,$handle,$domain);if($r)return $r; }
    return false;
} }
if(!function_exists('wp_get_l10n_php_file_data')){ function wp_get_l10n_php_file_data($file) {   // .l10n.php laden (nur aus Sprach-, Plugin- und Theme-Ordnern)
    $real=@realpath((string)$file);if(!$real||!str_ends_with($real,'.l10n.php'))return false;
    $ok=false;foreach([WP_LANG_DIR,WP_PLUGIN_DIR,WP_CONTENT_DIR.'/themes'] as $r){ $rr=realpath($r);if($rr&&str_starts_with($real,$rr.DIRECTORY_SEPARATOR)){ $ok=true;break; } }
    if(!$ok)return false;
    try{ $d=include $real; }catch(Throwable $e){ return false; }
    return is_array($d)?$d:false;
} }
if(!function_exists('wp_dropdown_languages')){ function wp_dropdown_languages($args=[]) {
    $a=wp_parse_args($args,['id'=>'locale','name'=>'locale','languages'=>[],'translations'=>[],'selected'=>'','echo'=>1,'show_available_translations'=>true,'show_option_site_default'=>false,'show_option_en_us'=>true,'explicit_option_en_us'=>false]);
    $o=[];$sel=(string)$a['selected'];
    if($a['show_option_site_default'])$o[]='<option value="site-default" data-installed="1"'.selected('site-default',$sel,false).'>'.esc_html_x('Site Default','default site language').'</option>';
    if($a['show_option_en_us'])$o[]='<option value="'.($a['explicit_option_en_us']?'en_US':'').'" lang="en" data-installed="1"'.selected($a['explicit_option_en_us']?'en_US':'',$sel,false).'>English (United States)</option>';
    foreach((array)$a['languages'] as $loc){ $n=$a['translations'][$loc]['native_name']??$loc;$o[]='<option value="'.esc_attr($loc).'" lang="'.esc_attr(substr((string)$loc,0,2)).'" data-installed="1"'.selected($loc,$sel,false).'>'.esc_html($n).'</option>'; }
    if($a['show_available_translations'])foreach((array)$a['translations'] as $loc=>$t){ if(in_array($loc,(array)$a['languages'],true))continue;$o[]='<option value="'.esc_attr($loc).'" lang="'.esc_attr(substr((string)$loc,0,2)).'"'.selected($loc,$sel,false).'>'.esc_html($t['native_name']??$loc).'</option>'; }
    $h='<select name="'.esc_attr($a['name']).'" id="'.esc_attr($a['id']).'">'.implode("\n",$o).'</select>';
    if($a['echo'])echo $h;return $h;
} }
if(!function_exists('switch_to_user_locale')){ function switch_to_user_locale($user_id) { return switch_to_locale(get_user_locale($user_id)); } }
if(!function_exists('restore_current_locale')){ function restore_current_locale() { return false; } }   // es gibt keinen Sprachwechsel-Stapel: nichts zurückzusetzen
if(!function_exists('is_locale_switched')){ function is_locale_switched() { return false; } }
if(!function_exists('translate_settings_using_i18n_schema')){ function translate_settings_using_i18n_schema($i18n_schema, $settings, $textdomain) {
    if(empty($i18n_schema)||empty($settings)||empty($textdomain))return $settings;
    if(is_string($i18n_schema)&&is_string($settings))return translate_with_gettext_context($settings,$i18n_schema,$textdomain);
    if(is_array($i18n_schema)&&is_array($settings)){
        $out=[];
        foreach($settings as $k=>$v){
            if(is_int($k)&&isset($i18n_schema[0]))$out[$k]=translate_settings_using_i18n_schema($i18n_schema[0],$v,$textdomain);
            elseif(isset($i18n_schema[$k]))$out[$k]=translate_settings_using_i18n_schema($i18n_schema[$k],$v,$textdomain);
            elseif(isset($i18n_schema['*']))$out[$k]=translate_settings_using_i18n_schema($i18n_schema['*'],$v,$textdomain);
            else $out[$k]=$v;
        }
        return $out;
    }
    return $settings;
} }

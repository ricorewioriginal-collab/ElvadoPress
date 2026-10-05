<?php
// Ergänzende WordPress-Funktionen (Version 7.1, Teil Abilities): schlanke Registrierung im Arbeitsspeicher (Fähigkeiten und Kategorien).
// Die Registrierungen entstehen beim ersten Zugriff (Actions wp_abilities_api_categories_init / wp_abilities_api_init); beim Laden passiert nichts.

if(!class_exists('WP_Ability_Category')){ class WP_Ability_Category {
    private $slug;private $props;
    public function __construct($slug, $props=[]) { $this->slug=(string)$slug;$this->props=(array)$props; }
    public function get_slug() { return $this->slug; }
    public function get_label() { return (string)($this->props['label']??''); }
    public function get_description() { return (string)($this->props['description']??''); }
    public function get_meta() { return (array)($this->props['meta']??[]); }
} }
if(!class_exists('WP_Ability')){ class WP_Ability {
    private $name;private $props;
    public function __construct($name, $props=[]) { $this->name=(string)$name;$this->props=(array)$props; }
    public function get_name() { return $this->name; }
    public function get_label() { return (string)($this->props['label']??''); }
    public function get_description() { return (string)($this->props['description']??''); }
    public function get_category() { return (string)($this->props['category']??''); }
    public function get_input_schema() { return (array)($this->props['input_schema']??[]); }
    public function get_output_schema() { return (array)($this->props['output_schema']??[]); }
    public function get_meta() { return (array)($this->props['meta']??[]); }
    public function get_meta_item($key, $default=null) { $m=$this->get_meta();return array_key_exists($key,$m)?$m[$key]:$default; }
    public function check_permissions($input=null) {   // ohne permission_callback: erlaubt
        $cb=$this->props['permission_callback']??null;if(!is_callable($cb))return true;
        return $cb($input);
    }
    public function execute($input=null) {
        $perm=$this->check_permissions($input);
        if($perm instanceof WP_Error)return $perm;
        if(!$perm)return new WP_Error('ability_invalid_permissions',sprintf('Die Fähigkeit „%s“ darf nicht ausgeführt werden.',$this->name));
        do_action('wp_before_execute_ability',$this->name,$input);
        $r=call_user_func($this->props['execute_callback'],$input);
        do_action('wp_after_execute_ability',$this->name,$input,$r);
        return $r;
    }
} }

if(!function_exists('_rrw_abilities_state')){ function _rrw_abilities_state() {   // lazy: Actions genau einmal
    $s=&$GLOBALS['rrw_wp_abilities'];
    if(!isset($s)){ $s=['cats'=>[],'items'=>[],'init'=>false]; }
    if(!$s['init']){ $s['init']=true;do_action('wp_abilities_api_categories_init');do_action('wp_abilities_api_init'); }
    return $s;
} }
if(!function_exists('wp_register_ability_category')){ function wp_register_ability_category($slug, $args=[]) {
    $s=&$GLOBALS['rrw_wp_abilities'];if(!isset($s))$s=['cats'=>[],'items'=>[],'init'=>false];
    $slug=(string)$slug;
    if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug)||isset($s['cats'][$slug])||!is_array($args)||empty($args['label'])||!isset($args['description'])){ _doing_it_wrong(__FUNCTION__,'Ungültige oder doppelte Kategorie.','6.9.0');return null; }
    return $s['cats'][$slug]=new WP_Ability_Category($slug,$args);
} }
if(!function_exists('wp_unregister_ability_category')){ function wp_unregister_ability_category($slug) {
    $s=&$GLOBALS['rrw_wp_abilities'];if(!isset($s,$s['cats'][$slug]))return null;$c=$s['cats'][$slug];unset($s['cats'][$slug]);return $c;
} }
if(!function_exists('wp_has_ability_category')){ function wp_has_ability_category($slug) { $s=_rrw_abilities_state();return isset($s['cats'][(string)$slug]); } }
if(!function_exists('wp_get_ability_category')){ function wp_get_ability_category($slug) { $s=_rrw_abilities_state();return $s['cats'][(string)$slug]??null; } }
if(!function_exists('wp_get_ability_categories')){ function wp_get_ability_categories() { $s=_rrw_abilities_state();return $s['cats']; } }
if(!function_exists('wp_register_ability')){ function wp_register_ability($name, $args=[]) {   // Name „namespace/ability“; Pflicht: label, description, execute_callback
    $s=&$GLOBALS['rrw_wp_abilities'];if(!isset($s))$s=['cats'=>[],'items'=>[],'init'=>false];
    $name=(string)$name;$args=is_array($args)?$args:[];
    if(!preg_match('/^[a-z0-9-]+\/[a-z0-9-]+$/',$name)||isset($s['items'][$name])||empty($args['label'])||empty($args['description'])||!is_callable($args['execute_callback']??null)){ _doing_it_wrong(__FUNCTION__,'Ungültige oder doppelte Fähigkeit.','6.9.0');return null; }
    $args=apply_filters('wp_register_ability_args',$args,$name);
    return $s['items'][$name]=new WP_Ability($name,$args);
} }
if(!function_exists('wp_unregister_ability')){ function wp_unregister_ability($name) {
    $s=&$GLOBALS['rrw_wp_abilities'];if(!isset($s,$s['items'][$name]))return null;$a=$s['items'][$name];unset($s['items'][$name]);return $a;
} }
if(!function_exists('wp_has_ability')){ function wp_has_ability($name) { $s=_rrw_abilities_state();return isset($s['items'][(string)$name]); } }
if(!function_exists('wp_get_ability')){ function wp_get_ability($name) { $s=_rrw_abilities_state();return $s['items'][(string)$name]??null; } }
if(!function_exists('_wp_get_abilities_match_meta')){ function _wp_get_abilities_match_meta($ability_meta, $query_meta) {   // alle Schlüssel der Abfrage müssen (rekursiv) übereinstimmen
    foreach((array)$query_meta as $k=>$v){
        if(!is_array($ability_meta)||!array_key_exists($k,$ability_meta))return false;
        if(is_array($v)){ if(!_wp_get_abilities_match_meta($ability_meta[$k],$v))return false; }
        elseif($ability_meta[$k]!==$v)return false;
    }
    return true;
} }
if(!function_exists('wp_get_abilities')){ function wp_get_abilities($args=[]) {   // Filter: category (Slug), meta (Teilmenge)
    $s=_rrw_abilities_state();$out=[];$args=(array)$args;
    foreach($s['items'] as $n=>$a){
        if(isset($args['category'])&&$a->get_category()!==(string)$args['category'])continue;
        if(!empty($args['meta'])&&!_wp_get_abilities_match_meta($a->get_meta(),$args['meta']))continue;
        $out[$n]=$a;
    }
    return $out;
} }

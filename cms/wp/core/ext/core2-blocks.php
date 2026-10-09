<?php
// Ergänzende WordPress-Funktionen (Kern 2, Teil 10): Block-Klassen – Parser, Blockliste, Block-Unterstützung, Bindungen, Muster-Kategorien, Vorlagen-Register, Duotone, Theme-JSON-Hilfen.
// Eigenständig umgesetzt; die Register nutzen dieselben Speicher wie die Funktionen der Schicht (register_block_pattern_category, register_block_bindings_source …).

/* ───────── Parser ───────── */
if(!class_exists('WP_Block_Parser_Block')){
class WP_Block_Parser_Block {
    public $blockName;public $attrs;public $innerBlocks;public $innerHTML;public $innerContent;
    public function __construct($name, $attrs, $innerBlocks, $innerHTML, $innerContent) { $this->blockName=$name;$this->attrs=$attrs;$this->innerBlocks=$innerBlocks;$this->innerHTML=$innerHTML;$this->innerContent=$innerContent; }
}
}
if(!class_exists('WP_Block_Parser_Frame')){
class WP_Block_Parser_Frame {
    public $block;public $token_start;public $token_length;public $prev_offset;public $leading_html_start;
    public function __construct($block, $token_start, $token_length, $prev_offset=null, $leading_html_start=null) {
        $this->block=$block;$this->token_start=$token_start;$this->token_length=$token_length;$this->prev_offset=$prev_offset??$token_start+$token_length;$this->leading_html_start=$leading_html_start;
    }
}
}
if(!class_exists('WP_Block_Parser')){
class WP_Block_Parser {
    public $document;public $offset;public $output;public $stack;public $empty_attrs;
    /** Zerlegt einen Beitrag in Blöcke (Felder blockName, attrs, innerBlocks, innerHTML, innerContent); nutzt die Schicht-Funktion parse_blocks(). */
    public function parse($document) { $this->document=(string)$document;$this->offset=0;$this->output=parse_blocks($this->document);$this->stack=[];$this->empty_attrs=[];return $this->output; }
}
}

/* ───────── Blockliste ───────── */
if(!class_exists('WP_Block_List')){
class WP_Block_List implements Iterator, ArrayAccess, Countable {
    protected $blocks;protected $available_context;protected $registry;
    public function __construct($blocks, $available_context=[], $registry=null) {
        $this->blocks=is_array($blocks)?$blocks:[];$this->available_context=$available_context;$this->registry=$registry??WP_Block_Type_Registry::get_instance();
    }
    public function offsetExists($offset): bool { return isset($this->blocks[$offset]); }
    #[\ReturnTypeWillChange] public function offsetGet($offset) {
        $b=$this->blocks[$offset]??null;if($b===null)return null;
        if(is_array($b)){ $b=new WP_Block($b,$this->available_context,$this->registry);$this->blocks[$offset]=$b; }
        return $b;
    }
    public function offsetSet($offset, $value): void { if($offset===null)$this->blocks[]=$value;else $this->blocks[$offset]=$value; }
    public function offsetUnset($offset): void { unset($this->blocks[$offset]); }
    public function rewind(): void { reset($this->blocks); }
    #[\ReturnTypeWillChange] public function current() { return $this->offsetGet(key($this->blocks)); }
    #[\ReturnTypeWillChange] public function key() { return key($this->blocks); }
    public function next(): void { next($this->blocks); }
    public function valid(): bool { return key($this->blocks)!==null; }
    public function count(): int { return count($this->blocks); }
}
}

/* ───────── Block-Unterstützung (Stil-/Klassen-Ausgabe pro Block) ───────── */
if(!class_exists('WP_Block_Supports')){
class WP_Block_Supports {
    public static $block_to_render=null;private $block_supports=[];private static $instance=null;
    public static function get_instance() { return self::$instance??(self::$instance=new self()); }
    public static function init() { $i=self::get_instance();return $i; }
    public function register($block_support_name, $block_support_config) {
        $this->block_supports[$block_support_name]=array_merge($block_support_config,['name'=>$block_support_name]);
    }
    public function get_registered() { return $this->block_supports; }
    /** Sammelt die Attribute (class, style …) aller Unterstützungen für den aktuell dargestellten Block. */
    public function apply_block_supports() {
        $b=self::$block_to_render;if(!$b)return [];
        $type=WP_Block_Type_Registry::get_instance()->get_registered($b['blockName']??'');if(!$type)return [];
        $attrs=(array)($b['attrs']??[]);$out=[];
        foreach($this->block_supports as $s){
            if(!isset($s['apply'])||!is_callable($s['apply']))continue;
            $r=call_user_func($s['apply'],$type,$attrs);
            foreach((array)$r as $k=>$v){ if($v===''||$v===null)continue;
                $out[$k]=isset($out[$k])?($k==='style'?rtrim($out[$k],';').';'.$v:$out[$k].' '.$v):$v; }
        }
        return $out;
    }
}
}

/* ───────── Block-Bindungen und Register ───────── */
if(!class_exists('WP_Block_Bindings_Source')){
final class WP_Block_Bindings_Source {
    public $name;public $label;public $uses_context=[];private $get_value_callback;
    public function __construct($name, array $source_properties) {
        $this->name=(string)$name;$this->label=(string)($source_properties['label']??'');$this->uses_context=(array)($source_properties['uses_context']??[]);$this->get_value_callback=$source_properties['get_value_callback']??null;
    }
    public function get_value($source_args, $block_instance, $attribute_name) {
        return is_callable($this->get_value_callback)?call_user_func($this->get_value_callback,$source_args,$block_instance,$attribute_name):null;
    }
}
}
if(!class_exists('WP_Block_Bindings_Registry')){
final class WP_Block_Bindings_Registry {
    private static $instance=null;
    public static function get_instance() { return self::$instance??(self::$instance=new self()); }
    private function sources(): array { $o=[];foreach((array)($GLOBALS['elvado_wp_bindings']??[]) as $n=>$p)$o[$n]=new WP_Block_Bindings_Source($n,(array)$p);return $o; }
    public function register($source_name, array $source_properties) {
        if(!is_string($source_name)||$source_name===''||!preg_match('/^[a-z0-9-]+\/[a-z0-9-]+$/',$source_name))return false;
        if($this->is_registered($source_name))return false;
        if(empty($source_properties['label'])||!is_callable($source_properties['get_value_callback']??null))return false;
        register_block_bindings_source($source_name,$source_properties);return $this->get_registered($source_name);
    }
    public function unregister($source_name) { $s=$this->get_registered($source_name);if(!$s)return false;unregister_block_bindings_source($source_name);return $s; }
    public function get_all_registered() { return $this->sources(); }
    public function get_registered($source_name) { return $this->sources()[$source_name]??null; }
    public function is_registered($source_name) { return isset($GLOBALS['elvado_wp_bindings'][$source_name]); }
}
}
if(!class_exists('WP_Block_Pattern_Categories_Registry')){
final class WP_Block_Pattern_Categories_Registry {
    private static $instance=null;
    public static function get_instance() { return self::$instance??(self::$instance=new self()); }
    public function register($category_name, $category_properties) { $GLOBALS['elvado_wp_pattern_categories'][$category_name]=array_merge(['name'=>$category_name],(array)$category_properties);return true; }
    public function unregister($category_name) { if(!$this->is_registered($category_name))return false;unset($GLOBALS['elvado_wp_pattern_categories'][$category_name]);return true; }
    public function get_registered($category_name) { $c=$GLOBALS['elvado_wp_pattern_categories'][$category_name]??null;return $c?array_merge(['name'=>$category_name],(array)$c):null; }
    public function get_all_registered() { $o=[];foreach((array)($GLOBALS['elvado_wp_pattern_categories']??[]) as $n=>$c)$o[]=array_merge(['name'=>$n],(array)$c);return $o; }
    public function is_registered($category_name) { return isset($GLOBALS['elvado_wp_pattern_categories'][$category_name]); }
}
}
if(!class_exists('WP_Block_Templates_Registry')){
final class WP_Block_Templates_Registry {
    private static $instance=null;
    public static function get_instance() { return self::$instance??(self::$instance=new self()); }
    public function register($template_name, $args=[]) {
        if(!is_string($template_name)||!preg_match('/^[a-z0-9-]+\/[a-z0-9-]+$/',$template_name)||$this->is_registered($template_name))return new WP_Error('template_name_invalid','Der Vorlagenname ist ungültig oder bereits vergeben.');
        register_block_template($template_name,(array)$args);return (object)array_merge(['name'=>$template_name,'slug'=>explode('/',$template_name)[1]],(array)$args);
    }
    public function unregister($template_name) { if(!$this->is_registered($template_name))return false;$t=$this->get_registered($template_name);unset($GLOBALS['elvado_wp_registered_block_templates'][$template_name]);return $t; }
    public function get_registered($template_name) { $a=$GLOBALS['elvado_wp_registered_block_templates'][$template_name]??null;return $a===null?null:(object)array_merge(['name'=>$template_name,'slug'=>explode('/',$template_name)[1]??$template_name],(array)$a); }
    public function get_all_registered() { $o=[];foreach(array_keys((array)($GLOBALS['elvado_wp_registered_block_templates']??[])) as $n)$o[$n]=$this->get_registered($n);return $o; }
    public function get_by_slug($template_slug) { foreach($this->get_all_registered() as $t)if($t->slug===$template_slug)return $t;return null; }
    public function is_registered($template_name) { return isset($GLOBALS['elvado_wp_registered_block_templates'][$template_name]); }
}
}

/* ───────── Duotone ───────── */
if(!class_exists('WP_Duotone')){
class WP_Duotone {
    public static function is_preset($duotone_attr) { return is_string($duotone_attr)&&str_starts_with($duotone_attr,'var:preset|duotone|'); }
    public static function get_slug_from_attribute($duotone_attr) { return self::is_preset($duotone_attr)?substr($duotone_attr,strlen('var:preset|duotone|')):''; }
    public static function get_css_var($slug) { return 'var(--wp--preset--duotone--'.sanitize_title($slug).')'; }
    public static function get_filter_id($slug) { return 'wp-duotone-'.sanitize_title($slug); }
    public static function get_filter_css_property_value_from_preset($preset) {
        if(isset($preset['colors'])&&is_string($preset['colors'])&&str_starts_with($preset['colors'],'var:'))return $preset['colors'];
        return 'url(#'.self::get_filter_id($preset['slug']??'').')';
    }
    private static function rgb($c) {
        $c=ltrim((string)$c,'#');if(strlen($c)===3)$c=$c[0].$c[0].$c[1].$c[1].$c[2].$c[2];
        return strlen($c)===6&&ctype_xdigit($c)?[hexdec(substr($c,0,2))/255,hexdec(substr($c,2,2))/255,hexdec(substr($c,4,2))/255]:[0,0,0];
    }
    public static function get_filter_svg($filter_id, $colors) {
        $rgb=array_map([self::class,'rgb'],array_values((array)$colors));if(!$rgb)return '';
        $t=['r'=>[],'g'=>[],'b'=>[]];foreach($rgb as $c){ $t['r'][]=round($c[0],4);$t['g'][]=round($c[1],4);$t['b'][]=round($c[2],4); }
        $svg='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 0 0" width="0" height="0" focusable="false" role="none" style="visibility:hidden;position:absolute;left:-9999px;overflow:hidden"><defs><filter id="'.esc_attr($filter_id).'">'
            .'<feColorMatrix color-interpolation-filters="sRGB" type="matrix" values=".299 .587 .114 0 0 .299 .587 .114 0 0 .299 .587 .114 0 0 0 0 0 1 0" />'
            .'<feComponentTransfer color-interpolation-filters="sRGB"><feFuncR type="table" tableValues="'.implode(' ',$t['r']).'" /><feFuncG type="table" tableValues="'.implode(' ',$t['g']).'" /><feFuncB type="table" tableValues="'.implode(' ',$t['b']).'" /><feFuncA type="table" tableValues="'.implode(' ',array_fill(0,count($rgb),1)).'" /></feComponentTransfer>'
            .'<feComposite in2="SourceGraphic" operator="in" /></filter></defs></svg>';
        return $svg;
    }
    public static function get_filter_svg_from_preset($preset) { return self::get_filter_svg(self::get_filter_id($preset['slug']??''),$preset['colors']??[]); }
    public static function register_duotone_support($block_type) {}   // Duotone-Attribut wird in der Schicht nicht gesondert angelegt
    public static function render_duotone_support($block_content, $block, $wp_block=null) { return $block_content; }
    public static function output_footer_assets() {}
}
}

/* ───────── Theme-JSON-Hilfen ───────── */
if(!class_exists('WP_Theme_JSON_Data')){
class WP_Theme_JSON_Data {
    private $data;private $origin;
    public function __construct($data=[], $origin='theme') { $this->data=is_array($data)?$data:[];$this->origin=(string)$origin; }
    public function update_with($new_data) {
        $m=function($a,$b) use(&$m){ foreach($b as $k=>$v)$a[$k]=(is_array($v)&&isset($a[$k])&&is_array($a[$k])&&$v!==[]&&array_keys($v)!==range(0,count($v)-1))?$m($a[$k],$v):$v;return $a; };
        $c=clone $this;$c->data=$m($this->data,is_array($new_data)?$new_data:[]);return $c;
    }
    public function get_data() { return $this->data; }
    public function get_theme_json() { return new WP_Theme_JSON($this->data,$this->origin); }
}
}
if(!class_exists('WP_Theme_JSON_Schema')){
class WP_Theme_JSON_Schema {
    const V1_TO_V2_RENAMED_PATHS=['border.customRadius'=>'border.radius','spacing.customMargin'=>'spacing.margin','spacing.customPadding'=>'spacing.padding','typography.customLineHeight'=>'typography.lineHeight'];
    /** Hebt ein theme.json auf Schema-Version 3 an: veraltete Namen (Version 1) werden umbenannt, die Versionsnummer gesetzt. */
    public static function migrate($theme_json, $origin='theme') {
        if(!is_array($theme_json)||!isset($theme_json['version']))$theme_json=['version'=>1]+(array)$theme_json;
        $v=(int)$theme_json['version'];
        if($v<2){ $theme_json=self::migrate_v1_to_v2($theme_json);$v=2; }
        if($v<3){ $theme_json['version']=3; }
        return $theme_json;
    }
    private static function migrate_v1_to_v2($t) {
        if(isset($t['settings'])&&is_array($t['settings'])){
            $walk=function(&$settings) use(&$walk){
                foreach(self::V1_TO_V2_RENAMED_PATHS as $old=>$new){
                    [$og,$ok]=explode('.',$old);[$ng,$nk]=explode('.',$new);
                    if(isset($settings[$og][$ok])){ $settings[$ng][$nk]=$settings[$og][$ok];unset($settings[$og][$ok]);if(empty($settings[$og]))unset($settings[$og]); }
                }
                if(isset($settings['blocks']))foreach($settings['blocks'] as &$b)$walk($b);
            };
            $walk($t['settings']);
        }
        $t['version']=2;return $t;
    }
}
}

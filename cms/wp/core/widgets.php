<?php
// WordPress-Widget-API (Klasse WP_Widget, register_widget, the_widget). Die Anbindung an die Widget-Bereiche des Portals folgt mit der Theme-Laufzeit.
$GLOBALS['wp_registered_widgets']=$GLOBALS['wp_registered_widgets']??[];
$GLOBALS['elvado_wp_widget_classes']=$GLOBALS['elvado_wp_widget_classes']??[];

if(!class_exists('WP_Widget')){
class WP_Widget {
    public $id_base;public $name;public $widget_options;public $control_options;public $number=false;public $id=false;public $updated=false;public $option_name;
    public function __construct($id_base='', $name='', $widget_options=[], $control_options=[]) {
        $this->id_base=empty($id_base)?strtolower(get_class($this)):strtolower($id_base);$this->name=$name;
        $this->option_name='widget_'.$this->id_base;$this->widget_options=wp_parse_args($widget_options,['classname'=>str_replace('\\','_',$this->option_name),'customize_selective_refresh'=>false]);
        $this->control_options=wp_parse_args($control_options,['id_base'=>$this->id_base]);
    }
    public function widget($args, $instance) { die('function WP_Widget::widget() must be overridden in a subclass.'); }
    public function update($new_instance, $old_instance) { return $new_instance; }
    public function form($instance) { echo '<p class="no-options-widget">Dieses Widget hat keine Einstellungen.</p>';return 'noform'; }
    public function get_field_id($field_name) { return 'widget-'.$this->id_base.'-'.$this->number.'-'.trim(str_replace(['[]','[',']'],['','-',''],$field_name),'-'); }
    public function get_field_name($field_name) { $pos=strpos($field_name,'[');return 'widget-'.$this->id_base.'['.$this->number.']'.($pos===false?'['.$field_name.']':'['.substr($field_name,0,$pos).']'.substr($field_name,$pos)); }
    public function _register() {}
    public function _set($number) { $this->number=$number;$this->id=$this->id_base.'-'.$number; }
    public function _get_display_callback() { return [$this,'display_callback']; }
    public function display_callback($args, $widget_args=1) {
        $instance=is_array($widget_args)?$widget_args:[];
        $instance=apply_filters('widget_display_callback',$instance,$this,$args);
        if(false===$instance)return;
        $args=wp_parse_args($args,['before_widget'=>'<section class="widget '.esc_attr($this->widget_options['classname']).'">','after_widget'=>'</section>','before_title'=>'<h2 class="widgettitle">','after_title'=>'</h2>']);
        $this->widget($args,$instance);
    }
    public function is_preview() { return false; }
    public function get_settings() { $s=get_option($this->option_name);return is_array($s)?$s:[]; }
    public function save_settings($settings) { update_option($this->option_name,$settings); }
    public function get_settings_all() { return $this->get_settings(); }
}
}
class WP_Widget_Factory {
    public $widgets=[];
    public function register($widget_class) { $this->widgets[$widget_class]=new $widget_class();$GLOBALS['elvado_wp_widget_classes'][$widget_class]=$this->widgets[$widget_class]; }
    public function unregister($widget_class) { unset($this->widgets[$widget_class],$GLOBALS['elvado_wp_widget_classes'][$widget_class]); }
}
$GLOBALS['wp_widget_factory']=$GLOBALS['wp_widget_factory']??new WP_Widget_Factory();
function register_widget($widget) { $GLOBALS['wp_widget_factory']->register(is_object($widget)?get_class($widget):$widget); do_action('register_widget',$widget); }
function unregister_widget($widget) { $GLOBALS['wp_widget_factory']->unregister(is_object($widget)?get_class($widget):$widget); }
function the_widget($widget, $instance=[], $args=[]) {
    $f=$GLOBALS['wp_widget_factory'];$w=$f->widgets[$widget]??null;if(!$w){if(!class_exists($widget))return;$w=new $widget();}
    $w->_set(2);$args=wp_parse_args($args,['before_widget'=>'<div class="widget '.esc_attr($w->widget_options['classname']).'">','after_widget'=>'</div>','before_title'=>'<h2 class="widgettitle">','after_title'=>'</h2>']);
    $w->widget($args,wp_parse_args($instance));
}

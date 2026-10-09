<?php
// Customizer (vereinfacht): Themes registrieren Einstellungen/Steuerelemente über den Hook customize_register; das CMS zeigt sie als Formular
// (Seite „Anpassen“ unter Design) und speichert sie als Theme-Optionen (theme_mods) bzw. Optionen. Eine Live-Vorschau im Customizer-Stil gibt es nicht.
#[AllowDynamicProperties]
class WP_Customize_Setting {
    public $manager;public $id;public $type='theme_mod';public $default='';public $transport='refresh';public $capability='edit_theme_options';public $sanitize_callback='';public $sanitize_js_callback='';public $theme_supports='';
    public function __construct($manager=null,$id='',$args=[]) { $this->manager=$manager;$this->id=(string)$id;foreach((array)$args as $k=>$v)$this->$k=$v; }
    public function value() { return $this->type==='option'?get_option($this->id,$this->default):get_theme_mod($this->id,$this->default); }
    public function post_value($default=null) { return $default; }
    public function preview() { return true; }
    public function js_value() { return $this->value(); }
}
#[AllowDynamicProperties]
class WP_Customize_Control {
    public $manager;public $id;public $settings='default';public $setting;public $label='';public $description='';public $section='';public $type='text';public $choices=[];public $input_attrs=[];public $priority=10;public $capability='edit_theme_options';public $active_callback='';public $allow_addition=false;
    public function __construct($manager=null,$id='',$args=[]) { $this->manager=$manager;$this->id=(string)$id;foreach((array)$args as $k=>$v)$this->$k=$v; if(is_array($this->settings)&&isset($this->settings['default']))$this->settings=$this->settings['default']; }
    public function value() { $s=$this->setting_obj();return $s?$s->value():''; }
    public function setting_obj() { $sid=is_string($this->settings)&&$this->settings!=='default'?$this->settings:$this->id;return $this->manager?$this->manager->get_setting($sid):null; }
    public function active() { return true; }
}
class WP_Customize_Section { public $manager;public $id;public $title='';public $description='';public $panel='';public $priority=160;public $capability='edit_theme_options';public $active_callback='';
    public function __construct($manager=null,$id='',$args=[]) { $this->manager=$manager;$this->id=(string)$id;foreach((array)$args as $k=>$v)$this->$k=$v; } }
class WP_Customize_Panel { public $manager;public $id;public $title='';public $description='';public $priority=160;public $capability='edit_theme_options';
    public function __construct($manager=null,$id='',$args=[]) { $this->manager=$manager;$this->id=(string)$id;foreach((array)$args as $k=>$v)$this->$k=$v; } }
class WP_Customize_Color_Control extends WP_Customize_Control { public $type='color'; }
class WP_Customize_Image_Control extends WP_Customize_Control { public $type='image'; }
class WP_Customize_Upload_Control extends WP_Customize_Control { public $type='image'; }
class WP_Customize_Media_Control extends WP_Customize_Control { public $type='image'; }
class WP_Customize_Cropped_Image_Control extends WP_Customize_Image_Control { }
class WP_Customize_Background_Image_Control extends WP_Customize_Image_Control { }
class WP_Customize_Header_Image_Control extends WP_Customize_Image_Control { }
class WP_Customize_Site_Icon_Control extends WP_Customize_Image_Control { }
class WP_Customize_Nav_Menu_Control extends WP_Customize_Control { public $type='hidden'; }
class WP_Customize_Selective_Refresh { public function add_partial(...$a) {} public function remove_partial($id) {} public function is_render_partials_request() { return false; } public function partials() { return []; } }
#[AllowDynamicProperties]
class WP_Customize_Manager {
    public $settings=[];public $controls=[];public $sections=[];public $panels=[];public $selective_refresh;
    public function __construct($args=[]) { $this->selective_refresh=new WP_Customize_Selective_Refresh(); }
    public function add_setting($id,$args=[]) { $o=$id instanceof WP_Customize_Setting?$id:new WP_Customize_Setting($this,(string)$id,$args);$o->manager=$this;$this->settings[$o->id]=$o;return $o; }
    public function add_control($id,$args=[]) { $o=$id instanceof WP_Customize_Control?$id:new WP_Customize_Control($this,(string)$id,$args);$o->manager=$this;$this->controls[$o->id]=$o;return $o; }
    public function add_section($id,$args=[]) { $o=$id instanceof WP_Customize_Section?$id:new WP_Customize_Section($this,(string)$id,$args);$o->manager=$this;$this->sections[$o->id]=$o;return $o; }
    public function add_panel($id,$args=[]) { $o=$id instanceof WP_Customize_Panel?$id:new WP_Customize_Panel($this,(string)$id,$args);$o->manager=$this;$this->panels[$o->id]=$o;return $o; }
    public function get_setting($id) { return $this->settings[$id]??null; } public function get_control($id) { return $this->controls[$id]??null; }
    public function get_section($id) { return $this->sections[$id]??null; } public function get_panel($id) { return $this->panels[$id]??null; }
    public function remove_setting($id) { unset($this->settings[$id]); } public function remove_control($id) { unset($this->controls[$id]); }
    public function remove_section($id) { unset($this->sections[$id]); } public function remove_panel($id) { unset($this->panels[$id]); }
    public function selective_refresh() { return $this->selective_refresh; }
    public function register_control_type($c) {} public function register_section_type($c) {} public function register_panel_type($c) {}
    public function add_partial(...$a) {} public function is_preview() { return false; } public function get_preview_url() { return home_url('/'); }
    public function sections() { return $this->sections; } public function controls() { return $this->controls; } public function settings() { return $this->settings; }
}
/** Manager einmal aufbauen: customize_register auslösen (Fehler eines Themes stoppen die Seite nicht). */
function elvado_wp_customizer(): WP_Customize_Manager {
    static $m=null;if($m)return $m;
    $m=new WP_Customize_Manager();$GLOBALS['wp_customize']=$m;
    // Kerneinstellungen, auf die Themes zugreifen (z. B. get_setting('blogname')->transport = …)
    foreach(['title_tagline'=>'Website-Informationen','colors'=>'Farben','header_image'=>'Headerbild','background_image'=>'Hintergrundbild','static_front_page'=>'Startseite'] as $sid=>$t)$m->add_section($sid,['title'=>$t]);
    foreach(['blogname'=>['option',get_option('blogname','')],'blogdescription'=>['option',get_option('blogdescription','')],'site_icon'=>['option',''],'custom_logo'=>['theme_mod',''],'header_image'=>['theme_mod',''],'header_textcolor'=>['theme_mod','#000000'],'background_color'=>['theme_mod','ffffff'],'background_image'=>['theme_mod',''],'nav_menu_locations'=>['theme_mod',[]]] as $id=>[$ty,$df])$m->add_setting($id,['type'=>$ty,'default'=>$df]);
    foreach(['blogname'=>['Titel der Website','title_tagline','text'],'blogdescription'=>['Untertitel','title_tagline','text'],'custom_logo'=>['Logo','title_tagline','image'],'site_icon'=>['Website-Icon','title_tagline','image'],'header_textcolor'=>['Textfarbe der Kopfzeile','colors','color'],'background_color'=>['Hintergrundfarbe','colors','color'],'header_image'=>['Headerbild','header_image','image'],'background_image'=>['Hintergrundbild','background_image','image']] as $id=>[$lb,$sec,$ty])$m->add_control($id,['label'=>$lb,'section'=>$sec,'type'=>$ty,'settings'=>$id]);
    try{ do_action('customize_register',$m); }catch(Throwable $e){ elvado_wp_log('customize_register: '.get_class($e).': '.$e->getMessage().' ('.basename($e->getFile()).':'.$e->getLine().')'); }
    return $m;
}
function wp_get_custom_css($stylesheet='') { return (string)get_theme_mod('custom_css_post_id_text',''); }

<?php
// WordPress-kompatible Theme-API (Grundlagen): aktives Theme, Pfade/URLs, Theme-Daten (style.css-Header), Theme-Support, Theme-Mods.
// WordPress-Themes liegen in cms/wp-content/themes/<slug>; die eingebauten CMS-Themes (cms/themes/<id>) erscheinen als Themes ohne PHP-Vorlagen.

function get_theme_root($stylesheet_or_template='') { return apply_filters('theme_root',defined('ELVADO_WP_SANDBOX_THEMES')?ELVADO_WP_SANDBOX_THEMES:WP_CONTENT_DIR.'/themes'); }
function get_theme_root_uri($stylesheet_or_template='', $theme_root='') { return apply_filters('theme_root_uri',defined('ELVADO_WP_SANDBOX_THEMES')?elvado_wp_home_url().'/cms/wp-sandbox/themes':content_url('themes')); }
/** Theme-Verzeichnisse in Suchreihenfolge. In der Sandbox zuerst deren eigenes Verzeichnis, danach die Themes der Live-Seite (nur lesend), zuletzt die mitgelieferten. */
function elvado_wp_theme_roots(): array {
    $r=[];$h=elvado_wp_home_url();
    if(defined('ELVADO_WP_SANDBOX_THEMES'))$r[]=['dir'=>ELVADO_WP_SANDBOX_THEMES,'url'=>$h.'/cms/wp-sandbox/themes','rel'=>'wp-sandbox/themes','kind'=>'sandbox'];
    $r[]=['dir'=>WP_CONTENT_DIR.'/themes','url'=>$h.'/cms/wp-content/themes','rel'=>'wp-content/themes','kind'=>'live'];
    $r[]=['dir'=>ELVADO_WP_NATIVE_THEMES,'url'=>$h.'/cms/themes','rel'=>'themes','kind'=>'native'];
    return $r;
}
function get_raw_theme_root($s, $skip_cache=false) { return '/themes'; }
function get_stylesheet() { return apply_filters('stylesheet',(string)get_option('stylesheet','default')); }
function get_template() { return apply_filters('template',(string)get_option('template',get_stylesheet())); }
function _elvado_wp_theme_dir(string $slug): string {
    $slug=preg_replace('/[^A-Za-z0-9_.-]/','',$slug);$wp=get_theme_root().'/'.$slug;
    if($slug!==''&&is_dir($wp))return $wp;
    if($slug!==''&&defined('ELVADO_WP_SANDBOX_THEMES')&&is_dir(WP_CONTENT_DIR.'/themes/'.$slug))return WP_CONTENT_DIR.'/themes/'.$slug;   // Sandbox: Themes der Live-Seite
    $native=ELVADO_WP_NATIVE_THEMES.'/'.$slug;return $slug!==''&&is_dir($native)?$native:$wp;
}
function get_stylesheet_directory($stylesheet_or_template=false, $theme_root=false) { return apply_filters('stylesheet_directory',_elvado_wp_theme_dir($stylesheet_or_template?:get_stylesheet()),get_stylesheet(),get_theme_root()); }
function get_template_directory($template=false, $theme_root=false) { return apply_filters('template_directory',_elvado_wp_theme_dir($template?:get_template()),get_template(),get_theme_root()); }
function _elvado_wp_theme_uri(string $dir): string {
    $dir=wp_normalize_path($dir);$wp=wp_normalize_path(get_theme_root());
    if(str_starts_with($dir,$wp))return get_theme_root_uri().substr($dir,strlen($wp));
    if(defined('ELVADO_WP_SANDBOX_THEMES')){ $lv=wp_normalize_path(WP_CONTENT_DIR.'/themes');if(str_starts_with($dir,$lv))return elvado_wp_home_url().'/cms/wp-content/themes'.substr($dir,strlen($lv)); }
    $nat=wp_normalize_path(ELVADO_WP_NATIVE_THEMES);return str_starts_with($dir,$nat)?home_url('/cms/themes').substr($dir,strlen($nat)):get_theme_root_uri().'/'.basename($dir);
}
function get_stylesheet_directory_uri($s=false, $r=false) { return apply_filters('stylesheet_directory_uri',_elvado_wp_theme_uri(get_stylesheet_directory($s)),get_stylesheet(),get_theme_root_uri()); }
function get_template_directory_uri($t=false, $r=false) { return apply_filters('template_directory_uri',_elvado_wp_theme_uri(get_template_directory($t)),get_template(),get_theme_root_uri()); }
function get_stylesheet_uri() { $d=get_stylesheet_directory();$u=is_file($d.'/style.css')?get_stylesheet_directory_uri().'/style.css':(is_file($d.'/theme.css')?get_stylesheet_directory_uri().'/theme.css':'');return apply_filters('stylesheet_uri',$u,get_stylesheet_directory_uri()); }
function get_locale_stylesheet_uri() { return ''; }
function is_child_theme() { return get_template()!==get_stylesheet(); }
function get_parent_theme_file_path($file='') { return get_template_directory().($file!==''?'/'.ltrim($file,'/'):''); }
function get_theme_file_path($file='') { $c=get_stylesheet_directory().($file!==''?'/'.ltrim($file,'/'):'');return apply_filters('theme_file_path',file_exists($c)?$c:get_parent_theme_file_path($file),$file); }
function get_parent_theme_file_uri($file='') { return get_template_directory_uri().($file!==''?'/'.ltrim($file,'/'):''); }
function get_theme_file_uri($file='') { $c=get_stylesheet_directory().'/'.ltrim($file,'/');return apply_filters('theme_file_uri',($file!==''&&file_exists($c))?get_stylesheet_directory_uri().'/'.ltrim($file,'/'):get_parent_theme_file_uri($file),$file); }

if(!class_exists('WP_Theme')){
class WP_Theme implements ArrayAccess {
    private $headers=[];private $dir;private $slug;private $parentSlug='';
    public function __construct($slug, $theme_root=null) {
        $this->slug=(string)$slug;$this->dir=_elvado_wp_theme_dir($this->slug);
        $css=$this->dir.'/style.css';$h=is_file($css)?get_file_data($css,['Name'=>'Theme Name','ThemeURI'=>'Theme URI','Description'=>'Description','Author'=>'Author','AuthorURI'=>'Author URI','Version'=>'Version','Template'=>'Template','Status'=>'Status','Tags'=>'Tags','TextDomain'=>'Text Domain','DomainPath'=>'Domain Path','RequiresWP'=>'Requires at least','RequiresPHP'=>'Requires PHP']):[];
        if(!$h||$h['Name']===''){ $j=is_file($this->dir.'/theme.json')?json_decode((string)file_get_contents($this->dir.'/theme.json'),true):null; $h=['Name'=>is_array($j)?(string)($j['name']??$this->slug):$this->slug,'ThemeURI'=>'','Description'=>is_array($j)?(string)($j['description']??''):'','Author'=>is_array($j)?(string)($j['author']??''):'','AuthorURI'=>'','Version'=>is_array($j)?(string)($j['version']??''):'','Template'=>'','Status'=>'publish','Tags'=>'','TextDomain'=>'','DomainPath'=>'','RequiresWP'=>'','RequiresPHP'=>'']; }
        $this->headers=$h;$this->parentSlug=(string)$h['Template'];
    }
    public function get($header) { return $this->headers[$header]??false; }
    public function display($header, $markup=true, $translate=true) { return esc_html((string)$this->get($header)); }
    public function exists() { return is_dir($this->dir); }
    public function get_stylesheet() { return $this->slug; }
    public function get_template() { return $this->parentSlug!==''?$this->parentSlug:$this->slug; }
    public function get_stylesheet_directory() { return $this->dir; }
    public function get_template_directory() { return _elvado_wp_theme_dir($this->get_template()); }
    public function get_stylesheet_directory_uri() { return _elvado_wp_theme_uri($this->dir); }
    public function get_template_directory_uri() { return _elvado_wp_theme_uri($this->get_template_directory()); }
    public function get_theme_root() { return dirname($this->dir); }
    public function get_theme_root_uri() { return get_theme_root_uri(); }
    public function parent() { return $this->parentSlug!==''?new WP_Theme($this->parentSlug):false; }
    public function get_screenshot($uri='uri') { foreach(['screenshot.png','screenshot.jpg','screenshot.jpeg','screenshot.webp','screenshot.gif'] as $f)if(is_file($this->dir.'/'.$f))return $uri==='relative'?$f:_elvado_wp_theme_uri($this->dir).'/'.$f;return false; }
    public function get_page_templates($post=null, $post_type='page') { return []; }
    public function is_block_theme() { return is_file($this->dir.'/templates/index.html')||is_file($this->dir.'/block-templates/index.html'); }
    public function errors() { return false; }
    public function get_files($type=null, $depth=0, $search_parent=false) { return []; }
    public function __get($n) { return match($n){'name'=>$this->headers['Name'],'version'=>$this->headers['Version'],'template'=>$this->get_template(),'stylesheet'=>$this->slug,'theme_root'=>$this->get_theme_root(),default=>null}; }
    public function offsetExists($o): bool { return isset($this->headers[$o]); }
    public function offsetGet($o): mixed { return $this->headers[$o]??null; }
    public function offsetSet($o,$v): void {}
    public function offsetUnset($o): void {}
}
}
function wp_get_theme($stylesheet='', $theme_root='') { return new WP_Theme($stylesheet!==''?(string)$stylesheet:get_stylesheet()); }
function wp_get_themes($args=[]) {
    $out=[];foreach(array_column(elvado_wp_theme_roots(),'dir') as $root){
        if(!is_dir($root))continue;
        foreach(scandir($root)?:[] as $e){ if($e[0]==='.'||!is_dir($root.'/'.$e)||isset($out[$e]))continue; $t=new WP_Theme($e);if($t->exists())$out[$e]=$t; }
    }
    return $out;
}
function switch_theme($stylesheet) { update_option('stylesheet',$stylesheet);update_option('template',$stylesheet);do_action('switch_theme',$stylesheet); }
function validate_current_theme() { return true; }
function get_theme_data($f) { return []; }

/* Theme-Support und Theme-Mods */
function add_theme_support($feature, ...$args) {
    global $_wp_theme_features;
    if($feature==='custom-background')$args[0]=wp_parse_args($args[0]??[],['default-image'=>'','default-preset'=>'default','default-position-x'=>'left','default-position-y'=>'top','default-size'=>'auto','default-repeat'=>'repeat','default-attachment'=>'scroll','default-color'=>'','wp-head-callback'=>'_custom_background_cb','admin-head-callback'=>'','admin-preview-callback'=>'']);
    elseif($feature==='custom-header')$args[0]=wp_parse_args($args[0]??[],['default-image'=>'','random-default'=>false,'width'=>0,'height'=>0,'flex-height'=>false,'flex-width'=>false,'default-text-color'=>'','header-text'=>true,'uploads'=>true,'wp-head-callback'=>'','admin-head-callback'=>'','admin-preview-callback'=>'','video'=>false]);
    $_wp_theme_features[$feature]=$args?:true;return true;
}
function remove_theme_support($feature) { global $_wp_theme_features; unset($_wp_theme_features[$feature]);return true; }
function current_theme_supports($feature, ...$args) { global $_wp_theme_features; return isset($_wp_theme_features[$feature]); }
function get_theme_support($feature, ...$args) { global $_wp_theme_features; return $_wp_theme_features[$feature]??false; }
function get_theme_mods() { $m=get_option('theme_mods_'.get_option('stylesheet'),[]);return is_array($m)?$m:[]; }
function get_theme_mod($name, $default_value=false) { $m=get_theme_mods();$v=$m[$name]??(is_callable($default_value)?false:$default_value);return apply_filters("theme_mod_{$name}",$v!==false?$v:$default_value); }
function set_theme_mod($name, $value) { $m=get_theme_mods();$m[$name]=apply_filters("pre_set_theme_mod_{$name}",$value,$m[$name]??false);return update_option('theme_mods_'.get_option('stylesheet'),$m); }
function remove_theme_mod($name) { $m=get_theme_mods();unset($m[$name]);return update_option('theme_mods_'.get_option('stylesheet'),$m); }
function remove_theme_mods() { return delete_option('theme_mods_'.get_option('stylesheet')); }
function is_customize_preview() { return false; }
function get_blog_option($id, $option, $default=false) { return get_option($option,$default); }
function get_blog_details($id=null, $get_all=true) { return (object)['blog_id'=>1,'blogname'=>get_option('blogname'),'siteurl'=>site_url(),'home'=>home_url()]; }
function get_blog_count() { return 1; }
function get_network() { return (object)['id'=>1,'domain'=>parse_url(home_url(),PHP_URL_HOST),'path'=>'/']; }
function get_super_admins() { return ['admin']; }
function wp_get_raw_referer() { return wp_get_referer(); }

<?php
// Ergänzende Theme-Funktionen (wp-includes/theme.php): Theme-Verzeichnisse, Kopfbild/-video, Hintergrund, eigenes CSS, Editor-Stile,
// Theme-Features, Wechsel-Nachbereitung. Der Customizer ist vereinfacht (siehe core/customize.php); die dafür gedachten
// _wp_customize_*-Funktionen sind bewusst Standardwerte/No-ops (dokumentiert am jeweiligen Eintrag). Hilfsfunktionen beginnen mit _elvado_m_.

/** Option eines Theme-Features (Eintrag [0] von add_theme_support), z. B. _elvado_m_th('custom-header','video'). */
if(!function_exists('_elvado_m_th')){ function _elvado_m_th($feature,$key=null) {
    $s=get_theme_support($feature);if(!is_array($s))return $s;$o=$s[0]??null;
    return $key===null?$o:(is_array($o)?($o[$key]??false):false);
} }

/* ───────── Theme-Verzeichnisse und -Anforderungen ───────── */
if(!function_exists('register_theme_directory')){ function register_theme_directory($directory) {
    global $wp_theme_directories;
    if(!file_exists($directory)){ $directory=WP_CONTENT_DIR.'/'.$directory;if(!file_exists($directory))return false; }
    if(!is_array($wp_theme_directories))$wp_theme_directories=[];
    $u=untrailingslashit($directory);if(!empty($u)&&!in_array($u,$wp_theme_directories,true))$wp_theme_directories[]=$u;
    return true;
} }
if(!function_exists('search_theme_directories')){ function search_theme_directories($force=false) {
    global $wp_theme_directories;$found=[];
    foreach(array_unique(array_merge(array_column(elvado_wp_theme_roots(),'dir'),(array)$wp_theme_directories)) as $root){
        if(!is_dir($root))continue;
        foreach(scandir($root)?:[] as $e){ if($e[0]==='.')continue;$d=$root.'/'.$e;if(!is_dir($d))continue;
            if(is_file($d.'/style.css')||is_file($d.'/theme.json')){ $found[$e]??=['theme_file'=>$e.'/style.css','theme_root'=>$root];continue; }
            foreach(scandir($d)?:[] as $s)if($s[0]!=='.'&&is_file("$d/$s/style.css"))$found["$e/$s"]??=['theme_file'=>"$e/$s/style.css",'theme_root'=>$root];   // eine Ebene tiefer
        }
    }
    return $found?:false;
} }
if(!function_exists('get_theme_roots')){ function get_theme_roots() {
    global $wp_theme_directories;
    if(count(array_unique(array_merge([get_theme_root()],(array)$wp_theme_directories)))<=1)return '/themes';   // das Standardverzeichnis zählt immer mit
    $r=[];foreach((array)search_theme_directories() as $slug=>$i)$r[$slug]=str_replace(WP_CONTENT_DIR,'',$i['theme_root']);
    return $r;
} }
if(!function_exists('locale_stylesheet')){ function locale_stylesheet() {
    $s=get_locale_stylesheet_uri();if(empty($s))return;
    printf('<link rel="stylesheet" href="%s"%s media="screen" />',$s,current_theme_supports('html5','style')?'':' type="text/css"');
} }
if(!function_exists('validate_theme_requirements')){ function validate_theme_requirements($stylesheet) {
    $t=wp_get_theme($stylesheet);$wp=(string)($t->get('RequiresWP')?:'');$php=(string)($t->get('RequiresPHP')?:'');
    $okw=is_wp_version_compatible($wp);$okp=is_php_version_compatible($php);
    if(!$okw&&!$okp)return new WP_Error('theme_wp_php_incompatible',sprintf('<strong>Fehler:</strong> Dieses Theme benötigt WordPress %1$s und PHP %2$s.',$wp,$php));
    if(!$okp)return new WP_Error('theme_php_incompatible',sprintf('<strong>Fehler:</strong> Dieses Theme benötigt PHP %s.',$php));
    if(!$okw)return new WP_Error('theme_wp_incompatible',sprintf('<strong>Fehler:</strong> Dieses Theme benötigt WordPress %s.',$wp));
    return true;
} }

/* ───────── Kopfbild und Kopfvideo ───────── */
if(!function_exists('get_header_image_tag')){ function get_header_image_tag($attr=[]) {
    $h=get_custom_header();$h->url=get_header_image();if(!$h->url)return '';
    $w=absint($h->width?:_elvado_m_th('custom-header','width'));$ht=absint($h->height?:_elvado_m_th('custom-header','height'));$alt='';
    if(!empty($h->attachment_id)){ $a=get_post_meta($h->attachment_id,'_wp_attachment_image_alt',true);if(is_string($a))$alt=$a; }
    $attr=wp_parse_args($attr,['src'=>$h->url,'width'=>$w,'height'=>$ht,'alt'=>$alt]);
    if(!empty($h->attachment_id)&&empty($attr['srcset'])&&($meta=wp_get_attachment_metadata($h->attachment_id))&&is_array($meta)){
        $size=[(int)$attr['width'],(int)$attr['height']];$ss=wp_calculate_image_srcset($size,$attr['src'],$meta,$h->attachment_id)?:_elvado_m_srcset($size,$attr['src'],$meta,$h->attachment_id);
        if($ss){ $attr['srcset']=$ss;$attr['sizes']=wp_calculate_image_sizes($size,$attr['src'],$meta,$h->attachment_id)?:sprintf('(max-width: %1$dpx) 100vw, %1$dpx',(int)$attr['width']); } }
    $attr=array_map('esc_attr',array_filter($attr,fn($v,$k)=>$k==='alt'||($v!==''&&$v!==0&&$v!==null),ARRAY_FILTER_USE_BOTH));   // leeres alt bleibt erhalten
    $html='<img';foreach($attr as $k=>$v)$html.=' '.$k.'="'.$v.'"';$html.=' />';
    return apply_filters('get_header_image_tag',$html,$h,$attr);
} }
if(!function_exists('_get_random_header_data')){ function _get_random_header_data() {
    static $rand=null;global $_wp_default_headers;
    if(empty($rand)){
        $mod=get_theme_mod('header_image','');$headers=[];
        if($mod==='random-uploaded-image')$headers=get_uploaded_header_images();
        elseif(!empty($_wp_default_headers)){ if($mod==='random-default-image'||_elvado_m_th('custom-header','random-default'))$headers=$_wp_default_headers; }
        if(empty($headers))return new stdClass;
        $rand=(object)$headers[array_rand($headers)];
        $rand->url=sprintf((string)($rand->url??''),get_template_directory_uri(),get_stylesheet_directory_uri());
        $rand->thumbnail_url=sprintf((string)($rand->thumbnail_url??''),get_template_directory_uri(),get_stylesheet_directory_uri());
    }
    return $rand;
} }
if(!function_exists('get_random_header_image')){ function get_random_header_image() { $r=_get_random_header_data();return empty($r->url)?'':$r->url; } }
if(!function_exists('is_random_header_image')){ function is_random_header_image($type='any') {
    $mod=get_theme_mod('header_image',_elvado_m_th('custom-header','default-image'));
    if($type==='any'){ return $mod==='random-default-image'||$mod==='random-uploaded-image'||(get_random_header_image()!==''&&empty($mod)); }
    return "random-$type-image"===$mod||($type==='default'&&empty($mod)&&get_random_header_image()!=='');
} }
if(!function_exists('header_image')){ function header_image() { $i=get_header_image();if($i)echo esc_url($i); } }
if(!function_exists('unregister_default_headers')){ function unregister_default_headers($header) {
    global $_wp_default_headers;
    if(is_array($header)){ array_map('unregister_default_headers',$header);return true; }
    if(isset($_wp_default_headers[$header])){ unset($_wp_default_headers[$header]);return true; }
    return false;
} }
if(!function_exists('get_header_video_url')){ function get_header_video_url() {
    $id=absint(get_theme_mod('header_video'));$url=$id?wp_get_attachment_url($id):get_theme_mod('external_header_video');
    return apply_filters('get_header_video_url',$url);
} }
if(!function_exists('has_header_video')){ function has_header_video() { return (bool)get_header_video_url(); } }
if(!function_exists('the_header_video_url')){ function the_header_video_url() { $v=get_header_video_url();if($v)echo esc_url($v); } }
if(!function_exists('get_header_video_settings')){ function get_header_video_settings() {
    $h=get_custom_header();$url=(string)get_header_video_url();$type=wp_check_filetype($url,wp_get_mime_types());
    $s=['mimeType'=>'','posterUrl'=>get_header_image(),'videoUrl'=>$url,'width'=>absint($h->width?:_elvado_m_th('custom-header','width')),'height'=>absint($h->height?:_elvado_m_th('custom-header','height')),'minWidth'=>900,'minHeight'=>500,
        'l10n'=>['pause'=>'Pause','play'=>'Wiedergabe','pauseSpeak'=>'Das Video ist angehalten.','playSpeak'=>'Das Video läuft.']];
    if(preg_match('#^https?://(?:www\.)?(?:youtube\.com/watch|youtu\.be/)#',$url))$s['mimeType']='video/x-youtube';elseif(!empty($type['type']))$s['mimeType']=$type['type'];
    return apply_filters('header_video_settings',$s);
} }
if(!function_exists('is_header_video_active')){ function is_header_video_active() {
    if(!_elvado_m_th('custom-header','video'))return false;
    $cb=_elvado_m_th('custom-header','video-active-callback');
    return apply_filters('is_header_video_active',empty($cb)||!is_callable($cb)?true:call_user_func($cb));
} }
if(!function_exists('get_custom_header_markup')){ function get_custom_header_markup() {
    if(!get_header_image()&&!(has_header_video()&&is_header_video_active()))return '';
    return sprintf('<div id="wp-custom-header" class="wp-custom-header">%s</div>',get_header_image_tag());
} }
if(!function_exists('background_image')){ function background_image() { echo get_background_image(); } }
if(!function_exists('background_color')){ function background_color() { echo get_background_color(); } }

/* ───────── Eigenes CSS und Editor-Stile ───────── */
if(!function_exists('wp_custom_css_cb')){ function wp_custom_css_cb() {
    $css=wp_get_custom_css();
    if($css||is_customize_preview())echo '<style id="wp-custom-css">'."\n".strip_tags($css)."\n</style>\n";
} }
if(!function_exists('wp_get_custom_css_post')){ function wp_get_custom_css_post($stylesheet='') {
    if(empty($stylesheet))$stylesheet=get_stylesheet();
    $q=['post_type'=>'custom_css','post_status'=>'any','name'=>sanitize_title($stylesheet),'posts_per_page'=>1,'no_found_rows'=>true];$post=null;
    if(get_stylesheet()===$stylesheet){ $pid=get_theme_mod('custom_css_post_id');
        if($pid>0&&get_post($pid))$post=get_post($pid);
        elseif($pid!==-1){ $r=(new WP_Query($q))->posts;$post=$r[0]??null;set_theme_mod('custom_css_post_id',$post?$post->ID:-1); }
    } else { $r=(new WP_Query($q))->posts;$post=$r[0]??null; }
    return $post;
} }
if(!function_exists('wp_update_custom_css_post')){ function wp_update_custom_css_post($css,$args=[]) {
    $args=wp_parse_args($args,['preprocessed'=>'','stylesheet'=>get_stylesheet()]);
    $data=apply_filters('update_custom_css_data',['css'=>$css,'preprocessed'=>$args['preprocessed']],array_merge($args,compact('css')));
    $pd=['post_title'=>$args['stylesheet'],'post_name'=>sanitize_title($args['stylesheet']),'post_type'=>'custom_css','post_status'=>'publish','post_content'=>$data['css'],'post_content_filtered'=>$data['preprocessed']];
    $post=wp_get_custom_css_post($args['stylesheet']);
    if($post){ $pd['ID']=$post->ID;$r=wp_update_post(wp_slash($pd),true); }
    else { $r=wp_insert_post(wp_slash($pd),true);if(!is_wp_error($r)&&get_stylesheet()===$args['stylesheet'])set_theme_mod('custom_css_post_id',$r); }
    return is_wp_error($r)?$r:get_post($r);
} }
if(!function_exists('remove_editor_styles')){ function remove_editor_styles() {
    if(!current_theme_supports('editor-style'))return false;
    _remove_theme_support('editor-style');if(is_admin())$GLOBALS['editor_styles']=[];return true;
} }
if(!function_exists('get_editor_stylesheets')){ function get_editor_stylesheets() {
    $sheets=[];
    if(!empty($GLOBALS['editor_styles'])&&is_array($GLOBALS['editor_styles'])){
        $es=array_unique(array_filter($GLOBALS['editor_styles']));$uri=get_stylesheet_directory_uri();$dir=get_stylesheet_directory();
        foreach($es as $k=>$f)if(preg_match('~^(https?:)?//~',$f)){ $sheets[]=sanitize_url($f);unset($es[$k]); }
        if(is_child_theme()){ $tu=get_template_directory_uri();$td=get_template_directory();foreach($es as $f)if($f&&file_exists("$td/$f"))$sheets[]="$tu/$f"; }
        foreach($es as $f)if($f&&file_exists("$dir/$f"))$sheets[]="$uri/$f";
    }
    return apply_filters('editor_stylesheets',$sheets);
} }
if(!function_exists('get_theme_starter_content')){ /** Vereinfacht: gibt die vom Theme angegebenen Startinhalte (add_theme_support('starter-content')) geordnet zurück; WordPress-eigene Vorgaben fehlen. */
function get_theme_starter_content() {
    $s=get_theme_support('starter-content');$cfg=is_array($s)&&!empty($s[0])&&is_array($s[0])?$s[0]:[];
    $c=array_merge(['widgets'=>[],'nav_menus'=>[],'options'=>[],'posts'=>[],'theme_mods'=>[],'attachments'=>[]],array_intersect_key($cfg,['widgets'=>1,'nav_menus'=>1,'options'=>1,'posts'=>1,'theme_mods'=>1,'attachments'=>1]));
    return apply_filters('get_theme_starter_content',$c,$cfg);
} }
if(!function_exists('_custom_header_background_just_in_time')){ function _custom_header_background_just_in_time() {
    foreach(['custom-header','custom-background'] as $f)if(current_theme_supports($f)){ $cb=_elvado_m_th($f,'wp-head-callback');if($cb&&is_callable($cb))add_action('wp_head',$cb); }
} }
if(!function_exists('_custom_logo_header_styles')){ function _custom_logo_header_styles() {
    if(!current_theme_supports('custom-header','header-text')&&_elvado_m_th('custom-logo','header-text')&&(($m=get_theme_mods())&&array_key_exists('header_text',$m)&&!$m['header_text'])){   // gespeichertes „false“ (get_theme_mod gäbe den Standardwert zurück)
        $c='.'.implode(', .',array_map('sanitize_html_class',(array)_elvado_m_th('custom-logo','header-text')));
        echo '<style id="custom-logo-css">'.$c.' { position: absolute; clip-path: inset(50%); }</style>'."\n";
    }
} }

/* ───────── Theme-Features ───────── */
if(!function_exists('_remove_theme_support')){ function _remove_theme_support($feature) {
    global $_wp_theme_features;
    switch($feature){
        case 'custom-header-uploads':return _remove_theme_support('custom-header');
        case 'post-formats':remove_post_type_support('post','post-formats');break;
        case 'custom-header':case 'custom-background':
            $cb=_elvado_m_th($feature,'wp-head-callback');if($cb){ remove_action('wp_head',$cb);remove_action('admin_head',$cb); }break;
    }
    unset($_wp_theme_features[$feature]);return true;
} }
if(!function_exists('require_if_theme_supports')){ function require_if_theme_supports($feature,$include) {
    if(current_theme_supports($feature)){ require $include;return true; }return false;
} }
if(!function_exists('register_theme_feature')){ function register_theme_feature($feature,$args=[]) {
    global $_wp_registered_theme_features;if(!is_array($_wp_registered_theme_features))$_wp_registered_theme_features=[];
    $args=wp_parse_args($args,['type'=>'boolean','variadic'=>false,'description'=>'','show_in_rest'=>false]);
    if($args['show_in_rest']===true)$args['show_in_rest']=[];
    if(is_array($args['show_in_rest']))$args['show_in_rest']=wp_parse_args($args['show_in_rest'],['schema'=>[],'name'=>$feature,'prepare_callback'=>null]);
    if(!in_array($args['type'],['string','boolean','integer','number','array','object'],true))return new WP_Error('invalid_type','Der „type“ des Features ist kein gültiger JSON-Schema-Typ.');
    if($args['variadic']===true&&$args['type']!=='array')return new WP_Error('variadic_must_be_array','Ein variadisches Feature muss den Typ „array“ haben.');
    $_wp_registered_theme_features[$feature]=$args;return true;
} }
if(!function_exists('get_registered_theme_features')){ function get_registered_theme_features() { global $_wp_registered_theme_features;return is_array($_wp_registered_theme_features)?$_wp_registered_theme_features:[]; } }
if(!function_exists('get_registered_theme_feature')){ function get_registered_theme_feature($feature) { return get_registered_theme_features()[$feature]??null; } }
if(!function_exists('create_initial_theme_features')){ function create_initial_theme_features() {
    foreach(['align-wide','automatic-feed-links','block-templates','block-template-parts','customize-selective-refresh-widgets','dark-editor-style','disable-custom-colors','disable-custom-font-sizes','disable-custom-gradients','disable-layout-styles','editor-styles','featured-content','responsive-embeds','starter-content','title-tag','widgets','widgets-block-editor','wp-block-styles'] as $f)
        register_theme_feature($f,['show_in_rest'=>true]);
    foreach(['custom-background','custom-header','custom-logo'] as $f)register_theme_feature($f,['type'=>'object','show_in_rest'=>true]);
    foreach(['editor-color-palette','editor-font-sizes','editor-gradient-presets','editor-spacing-sizes'] as $f)register_theme_feature($f,['type'=>'array','show_in_rest'=>true]);
    foreach(['html5','post-formats'] as $f)register_theme_feature($f,['type'=>'array','variadic'=>true,'show_in_rest'=>true]);
    register_theme_feature('post-thumbnails',['type'=>'array','variadic'=>true,'show_in_rest'=>true]);
} }
if(!function_exists('_add_default_theme_supports')){ function _add_default_theme_supports() {
    if(!wp_is_block_theme())return;
    add_theme_support('post-thumbnails');add_theme_support('responsive-embeds');add_theme_support('editor-styles');add_theme_support('html5',['style','script']);add_theme_support('automatic-feed-links');
} }
if(!function_exists('wp_theme_get_element_class_name')){ function wp_theme_get_element_class_name($element) { return ['button'=>'wp-element-button','caption'=>'wp-element-caption'][$element]??''; } }
if(!function_exists('_delete_attachment_theme_mod')){ function _delete_attachment_theme_mod($id) {
    $url=wp_get_attachment_url($id);$hi=get_theme_mod('header_image');$bi=get_theme_mod('background_image');$logo=get_theme_mod('custom_logo');
    if($logo&&$logo==$id)remove_theme_mod('custom_logo');
    if($hi&&$hi===$url)remove_theme_mod('header_image');
    if($bi&&$bi===$url)remove_theme_mod('background_image');
} }
if(!function_exists('check_theme_switched')){ function check_theme_switched() {
    $old=get_option('theme_switched');if(!$old)return;
    $t=wp_get_theme($old);
    if(get_option('theme_switched_via_customizer')){ remove_action('after_switch_theme','_wp_menus_changed');remove_action('after_switch_theme','_wp_sidebars_changed');update_option('theme_switched_via_customizer',false); }
    do_action('after_switch_theme',$t->exists()?$t->get('Name'):$old,$t);
    flush_rewrite_rules();update_option('theme_switched',false);
} }

/* ───────── Customizer: bewusst Standardwerte/No-ops (Customizer ist vereinfacht, ohne Live-Vorschau/Änderungssätze) ───────── */
if(!function_exists('_wp_customize_include')){ function _wp_customize_include() {} }
if(!function_exists('_wp_customize_publish_changeset')){ function _wp_customize_publish_changeset($new_status,$old_status,$changeset_post) {} }
if(!function_exists('_wp_customize_changeset_filter_insert_post_data')){ function _wp_customize_changeset_filter_insert_post_data($post_data,$supplied_post_data) { return $post_data; } }
if(!function_exists('_wp_keep_alive_customize_changeset_dependent_auto_drafts')){ function _wp_keep_alive_customize_changeset_dependent_auto_drafts($new_status,$old_status,$post) {} }
if(!function_exists('_wp_customize_loader_settings')){ function _wp_customize_loader_settings() {
    $a=wp_parse_url(admin_url());$h=wp_parse_url(home_url());
    $s=['url'=>esc_url(admin_url('customize.php')),'isCrossDomain'=>strtolower((string)($a['host']??''))!==strtolower((string)($h['host']??'')),
        'browser'=>['mobile'=>wp_is_mobile(),'ios'=>wp_is_mobile()&&preg_match('/iPad|iPod|iPhone/',(string)($_SERVER['HTTP_USER_AGENT']??''))],
        'l10n'=>['saveAlert'=>'Die angezeigten Änderungen werden nicht gespeichert.','mainIframeTitle'=>'Customizer']];
    wp_add_inline_script('customize-loader','var _wpCustomizeLoaderSettings = '.wp_json_encode($s).';','before');
} }
if(!function_exists('wp_customize_support_script')){ function wp_customize_support_script() {
    $a=wp_parse_url(admin_url());$h=wp_parse_url(home_url());$cross=strtolower((string)($a['host']??''))!==strtolower((string)($h['host']??''));
    echo '<script>(function(){ var request, b = document.body, c = "className", cs = "customize-support", rcs = new RegExp("(^|\\\\s+)(no-)?"+cs+"(\\\\s+|$)");'
        .($cross?' request = ( "XMLHttpRequest" in window ) && ( "withCredentials" in new XMLHttpRequest );':' request = true;')
        .' b[c] = b[c].replace( rcs, " " ); b[c] += ( window.postMessage && request ? " " : " no-" ) + cs; }());</script>'."\n";
} }

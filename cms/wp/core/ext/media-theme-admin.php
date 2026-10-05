<?php
// Ergänzende Theme-Verwaltung (wp-admin/includes/theme.php), Block-Vorlagen-Hilfen (theme-templates.php) und Theme-Vorschau (theme-previews.php).
// Pausierte Themes werden in der Option „rrw_paused_themes“ (Slug => Fehler) geführt; es gibt keinen Wiederherstellungsmodus, der sie selbst einträgt.

if(!function_exists('delete_theme')){ /** Löscht ein Theme aus wp-content/themes (nicht das aktive/übergeordnete, keine mitgelieferten CMS-Themes). */
function delete_theme($stylesheet,$redirect='') {
    if(empty($stylesheet))return false;
    $slug=(string)$stylesheet;
    if($slug!==preg_replace('/[^A-Za-z0-9_.-]/','',$slug)||str_contains($slug,'..'))return new WP_Error('invalid_theme','Ungültiger Theme-Name.');
    $dir=get_theme_root().'/'.$slug;if(!is_dir($dir))return new WP_Error('theme_not_found','Das Theme wurde nicht gefunden.');
    if($slug===get_stylesheet()||$slug===get_template())return new WP_Error('cannot_delete_active_theme','Das aktive Theme kann nicht gelöscht werden.');
    do_action('delete_theme',$slug);
    rrw_wp_rmdir($dir);$deleted=!is_dir($dir);
    do_action('deleted_theme',$slug,$deleted);
    if(!$deleted)return new WP_Error('could_not_remove_theme','Das Theme konnte nicht gelöscht werden.');
    delete_site_transient('update_themes');return true;
} }
if(!function_exists('_get_template_edit_filename')){ function _get_template_edit_filename($fullpath,$containingfolder) { return str_replace(dirname($containingfolder,2),'',$fullpath); } }
if(!function_exists('get_theme_update_available')){ function get_theme_update_available($theme) {
    if(!current_user_can('update_themes')||!($theme instanceof WP_Theme))return false;
    $u=get_site_transient('update_themes');$s=$theme->get_stylesheet();
    if(!is_object($u)||!isset($u->response[$s]))return false;
    $r=$u->response[$s];$r=is_array($r)?$r:(array)$r;$name=$theme->display('Name');
    return sprintf('<strong>Es ist eine neue Version von %1$s verfügbar.</strong> Version %2$s',$name,esc_html((string)($r['new_version']??'')));
} }
if(!function_exists('theme_update_available')){ function theme_update_available($theme) { echo get_theme_update_available($theme); } }
if(!function_exists('get_theme_feature_list')){ /** Feste Liste der Theme-Merkmale (ohne Abfrage bei wordpress.org). */
function get_theme_feature_list($api=true) {
    return ['Layout'=>['grid-layout'=>'Raster-Layout','one-column'=>'Eine Spalte','two-columns'=>'Zwei Spalten','three-columns'=>'Drei Spalten','four-columns'=>'Vier Spalten','left-sidebar'=>'Linke Seitenleiste','right-sidebar'=>'Rechte Seitenleiste'],
        'Features'=>['accessibility-ready'=>'Barrierefrei','block-patterns'=>'Block-Muster','custom-background'=>'Eigener Hintergrund','custom-colors'=>'Eigene Farben','custom-header'=>'Eigener Kopf','custom-logo'=>'Eigenes Logo','editor-style'=>'Editor-Stil','featured-images'=>'Beitragsbilder','full-site-editing'=>'Website-Editor','microformats'=>'Microformats','post-formats'=>'Beitragsformate','rtl-language-support'=>'RTL-Sprachen','sticky-post'=>'Angeheftete Beiträge','threaded-comments'=>'Verschachtelte Kommentare','translation-ready'=>'Übersetzungsbereit'],
        'Subject'=>['blog'=>'Blog','e-commerce'=>'E-Commerce','education'=>'Bildung','entertainment'=>'Unterhaltung','food-and-drink'=>'Essen und Trinken','holiday'=>'Feiertage','news'=>'Nachrichten','photography'=>'Fotografie','portfolio'=>'Portfolio']];
} }
if(!function_exists('wp_prepare_themes_for_js')){ function wp_prepare_themes_for_js($themes=null) {
    $cur=get_stylesheet();$themes=$themes??wp_get_themes();$out=[];
    foreach($themes as $t){ $slug=$t->get_stylesheet();$enc=rawurlencode($slug);$p=$t->parent();
        $out[$slug]=['id'=>$slug,'name'=>$t->display('Name'),'screenshot'=>[$t->get_screenshot()],'description'=>$t->display('Description'),'author'=>$t->display('Author'),'authorAndUri'=>$t->display('Author'),'tags'=>$t->display('Tags'),
            'version'=>$t->get('Version'),'compatibleWP'=>is_wp_version_compatible((string)$t->get('RequiresWP')),'compatiblePHP'=>is_php_version_compatible((string)$t->get('RequiresPHP')),'parent'=>$p?$p->get('Name'):false,
            'active'=>$slug===$cur,'hasUpdate'=>(bool)get_theme_update_available($t),'update'=>get_theme_update_available($t),
            'actions'=>['activate'=>current_user_can('switch_themes')?wp_nonce_url(admin_url('themes.php?action=activate&stylesheet='.$enc),'switch-theme_'.$slug):null,
                'customize'=>current_user_can('edit_theme_options')?admin_url('customize.php?theme='.$enc):null,'delete'=>current_user_can('delete_themes')?wp_nonce_url(admin_url('themes.php?action=delete&stylesheet='.$enc),'delete-theme_'.$slug):null]];
    }
    if(isset($out[$cur])){ $c=$out[$cur];unset($out[$cur]);$out=[$cur=>$c]+$out; }   // aktives Theme zuerst
    return apply_filters('wp_prepare_themes_for_js',array_values($out));
} }
if(!function_exists('customize_themes_print_templates')){ /** No-op: Der Customizer ist vereinfacht und kennt die Theme-Auswahlvorlagen nicht. */
function customize_themes_print_templates() {} }
if(!function_exists('is_theme_paused')){ function is_theme_paused($theme) { return array_key_exists((string)$theme,(array)get_option('rrw_paused_themes',[])); } }
if(!function_exists('wp_get_theme_error')){ function wp_get_theme_error($theme) { $p=(array)get_option('rrw_paused_themes',[]);return $p[(string)$theme]??false; } }
if(!function_exists('resume_theme')){ function resume_theme($theme,$redirect='') {
    $r=validate_theme_requirements($theme);if(is_wp_error($r))return $r;
    $p=(array)get_option('rrw_paused_themes',[]);if(!isset($p[$theme]))return true;
    unset($p[$theme]);update_option('rrw_paused_themes',$p);return true;
} }
if(!function_exists('paused_themes_notice')){ function paused_themes_notice() {
    if(($GLOBALS['pagenow']??'')==='themes.php'||!(current_user_can('resume_themes')||current_user_can('manage_options')))return;
    if(!(array)get_option('rrw_paused_themes',[]))return;
    printf('<div class="notice notice-error"><p><strong>%s</strong><br>%s</p><p><a href="%s">%s</a></p></div>','Mindestens ein Theme wurde wegen eines Fehlers pausiert.','Bitte prüfen Sie die Themes und setzen Sie die Ausführung fort, sobald der Fehler behoben ist.',esc_url(admin_url('themes.php')),'Zu den Themes');
} }

/* ───────── Block-Vorlagen (theme-templates.php) ───────── */
if(!function_exists('wp_enable_block_templates')){ function wp_enable_block_templates() { if(wp_is_block_theme()||wp_theme_has_theme_json())add_theme_support('block-templates'); } }
if(!function_exists('wp_filter_wp_template_unique_post_slug')){ function wp_filter_wp_template_unique_post_slug($override_slug,$slug,$post_id,$post_status,$post_type) {
    if($post_type!=='wp_template'&&$post_type!=='wp_template_part')return $override_slug;
    if(!$override_slug)$override_slug=$slug;
    $exists=function(string $name) use($post_type,$post_id):bool{ foreach(get_posts(['post_type'=>$post_type,'name'=>$name,'post_status'=>'any','numberposts'=>1,'exclude'=>[(int)$post_id]]) as $p)return true;return false; };
    if($exists($override_slug)){ $n=2;do{ $alt=mb_substr($override_slug,0,200-(strlen((string)$n)+1))."-$n";$n++; }while($exists($alt));$override_slug=$alt; }   // Vorlagen-Slugs sind je Typ eindeutig
    return $override_slug;
} }
if(!function_exists('wp_set_unique_slug_on_create_template_part')){ function wp_set_unique_slug_on_create_template_part($post_id) {
    $post=get_post($post_id);if(!$post||$post->post_status!=='auto-draft')return;
    $name=$post->post_name?:'untitled';
    wp_update_post(['ID'=>$post_id,'post_name'=>wp_unique_post_slug($name,$post_id,$post->post_status,$post->post_type,$post->post_parent)]);
} }
if(!function_exists('wp_enqueue_block_template_skip_link')){ function wp_enqueue_block_template_skip_link() {
    if(!wp_is_block_theme())return;
    remove_action('wp_footer','the_block_template_skip_link');
    wp_register_style('wp-block-template-skip-link',false);
    wp_add_inline_style('wp-block-template-skip-link','.skip-link.screen-reader-text{border:0;clip-path:inset(50%);height:1px;margin:-1px;overflow:hidden;padding:0;position:absolute;width:1px;word-wrap:normal!important}.skip-link.screen-reader-text:focus{background-color:#eee;clip-path:none;color:#444;display:block;font-size:1em;height:auto;left:5px;line-height:normal;padding:15px 23px 14px;text-decoration:none;top:5px;width:auto;z-index:100000}');
    wp_enqueue_style('wp-block-template-skip-link');
    add_action('wp_footer',function(){ echo '<a class="skip-link screen-reader-text" href="#wp--skip-link--target">Zum Inhalt springen</a>'."\n"; },1);
} }

/* ───────── Theme-Vorschau (theme-previews.php) ───────── */
if(!function_exists('wp_get_theme_preview_path')){ /** Als Filter für „stylesheet“/„template“ gedacht: liefert den Slug des Vorschau-Themes (?wp_theme_preview=…), sonst den übergebenen Wert. */
function wp_get_theme_preview_path($current='') {
    if(!current_user_can('switch_themes')||empty($_GET['wp_theme_preview']))return $current;
    $slug=sanitize_text_field(wp_unslash($_GET['wp_theme_preview']));
    return wp_get_theme($slug)->exists()?$slug:$current;
} }
if(!function_exists('wp_attach_theme_preview_middleware')){ function wp_attach_theme_preview_middleware() {
    if(!current_user_can('switch_themes')||empty($_GET['wp_theme_preview']))return;
    wp_add_inline_script('wp-api-fetch',sprintf('wp.apiFetch.use( wp.apiFetch.createThemePreviewMiddleware( %s ) );',wp_json_encode(sanitize_text_field(wp_unslash($_GET['wp_theme_preview'])))),'after');
} }
if(!function_exists('wp_block_theme_activate_nonce')){ function wp_block_theme_activate_nonce() {
    $slug=!empty($_GET['wp_theme_preview'])?sanitize_text_field(wp_unslash($_GET['wp_theme_preview'])):get_stylesheet();
    echo '<script>window.WP_BLOCK_THEME_ACTIVATE_NONCE = '.wp_json_encode(wp_create_nonce('switch-theme_'.$slug)).';</script>'."\n";
} }
if(!function_exists('wp_initialize_theme_preview_hooks')){ function wp_initialize_theme_preview_hooks() {
    if(empty($_GET['wp_theme_preview']))return;
    add_filter('stylesheet','wp_get_theme_preview_path');add_filter('template','wp_get_theme_preview_path');
    add_action('init','wp_attach_theme_preview_middleware');add_action('enqueue_block_editor_assets','wp_block_theme_activate_nonce');
} }

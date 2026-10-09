<?php
// Veraltete WordPress-Funktionen (wp-includes/deprecated.php, Teil 2): Widgets, Themes, Cache, HTTP, Roboter-Meta, Block-/Duotone-/Farbhelfer.
// Nutzt elvado_ext_lg_dep() aus legacy-core.php (Dateien werden alphabetisch geladen; legacy-core kommt vor legacy-theme).

/* ───────── Widgets / Theme-Unterstützung ───────── */
if(!function_exists('register_sidebar_widget')){
    function register_sidebar_widget($name,$output_callback,$classname='',...$params){
        elvado_ext_lg_dep(__FUNCTION__,'2.8.0','wp_register_sidebar_widget()');
        $opt=(!empty($classname)&&is_string($classname))?['classname'=>$classname]:[];
        return wp_register_sidebar_widget(sanitize_title($name),$name,$output_callback,$opt,...$params);
    }
}
if(!function_exists('unregister_sidebar_widget')){
    function unregister_sidebar_widget($id){ elvado_ext_lg_dep(__FUNCTION__,'2.8.0','wp_unregister_sidebar_widget()');return wp_unregister_sidebar_widget($id); }
}
if(!function_exists('register_widget_control')){
    function register_widget_control($name,$control_callback,$width='',$height='',...$params){
        elvado_ext_lg_dep(__FUNCTION__,'2.8.0','wp_register_widget_control()');
        $opt=[];
        if(is_numeric($width))$opt['width']=$width;
        if(is_numeric($height))$opt['height']=$height;
        return wp_register_widget_control(sanitize_title($name),$name,$control_callback,$opt,...$params);
    }
}
if(!function_exists('unregister_widget_control')){
    function unregister_widget_control($id){ elvado_ext_lg_dep(__FUNCTION__,'2.8.0','wp_unregister_widget_control()');return wp_unregister_widget_control(sanitize_title($id)); }
}
if(!function_exists('automatic_feed_links')){
    function automatic_feed_links($add=true){
        elvado_ext_lg_dep(__FUNCTION__,'3.0.0','add_theme_support(\'automatic-feed-links\')');
        return $add?add_theme_support('automatic-feed-links'):remove_theme_support('automatic-feed-links');
    }
}
if(!function_exists('add_custom_image_header')){
    function add_custom_image_header($wp_head_callback,$admin_head_callback,$admin_preview_callback=''){
        elvado_ext_lg_dep(__FUNCTION__,'3.4.0','add_theme_support(\'custom-header\')');
        $args=['wp-head-callback'=>$wp_head_callback,'admin-head-callback'=>$admin_head_callback];
        if($admin_preview_callback)$args['admin-preview-callback']=$admin_preview_callback;
        return add_theme_support('custom-header',$args);
    }
}
if(!function_exists('remove_custom_image_header')){
    function remove_custom_image_header(){ elvado_ext_lg_dep(__FUNCTION__,'3.4.0','remove_theme_support(\'custom-header\')');return remove_theme_support('custom-header'); }
}
if(!function_exists('add_custom_background')){
    function add_custom_background($wp_head_callback='',$admin_head_callback='',$admin_preview_callback=''){
        elvado_ext_lg_dep(__FUNCTION__,'3.4.0','add_theme_support(\'custom-background\')');
        $args=[];
        if($wp_head_callback)$args['wp-head-callback']=$wp_head_callback;
        if($admin_head_callback)$args['admin-head-callback']=$admin_head_callback;
        if($admin_preview_callback)$args['admin-preview-callback']=$admin_preview_callback;
        return add_theme_support('custom-background',$args);
    }
}
if(!function_exists('remove_custom_background')){
    function remove_custom_background(){ elvado_ext_lg_dep(__FUNCTION__,'3.4.0','remove_theme_support(\'custom-background\')');return remove_theme_support('custom-background'); }
}
if(!function_exists('get_themes')){
    function get_themes(){
        elvado_ext_lg_dep(__FUNCTION__,'3.4.0','wp_get_themes()');
        $out=[];
        foreach(wp_get_themes() as $slug=>$t){
            $tpl=(string)$t->get_template();$ss=(string)$t->get_stylesheet();
            $out[(string)$t->get('Name')]=['Name'=>$t->get('Name'),'Title'=>$t->get('Name'),'Description'=>$t->get('Description'),'Author'=>$t->get('Author'),'Author Name'=>$t->get('Author'),'Author URI'=>$t->get('AuthorURI'),
                'Version'=>$t->get('Version'),'Template'=>$tpl,'Stylesheet'=>$ss,'Status'=>'publish','Template Files'=>[],'Stylesheet Files'=>[],'Template Dir'=>$t->get_template_directory(),'Stylesheet Dir'=>$t->get_stylesheet_directory(),
                'Screenshot'=>$t->get_screenshot(),'Tags'=>(array)$t->get('Tags'),'Theme Root'=>$t->get_theme_root(),'Theme Root URI'=>$t->get_theme_root_uri(),'Parent Theme'=>$tpl!==$ss?$tpl:''];
        }
        return $out;
    }
}
if(!function_exists('get_theme')){
    function get_theme($theme){ elvado_ext_lg_dep(__FUNCTION__,'3.4.0','wp_get_theme()');$all=get_themes();return $all[$theme]??null; }
}
if(!function_exists('get_current_theme')){
    function get_current_theme(){ elvado_ext_lg_dep(__FUNCTION__,'3.4.0','wp_get_theme()');return (string)wp_get_theme()->get('Name'); }
}
if(!function_exists('preview_theme')){
    function preview_theme(){
        elvado_ext_lg_dep(__FUNCTION__,'4.3.0','add_action(\'customize_register\')');
        if(!isset($_GET['template'])||!current_user_can('switch_themes')||is_admin())return;
        add_filter('template','_preview_theme_template_filter');
        if(isset($_GET['stylesheet']))add_filter('stylesheet','_preview_theme_stylesheet_filter');
    }
}
if(!function_exists('_preview_theme_template_filter')){
    function _preview_theme_template_filter(){ elvado_ext_lg_dep(__FUNCTION__,'4.3.0');return isset($_GET['template'])?preg_replace('|[^a-z0-9_.\-/]|i','',(string)$_GET['template']):''; }
}
if(!function_exists('_preview_theme_stylesheet_filter')){
    function _preview_theme_stylesheet_filter(){ elvado_ext_lg_dep(__FUNCTION__,'4.3.0');return isset($_GET['stylesheet'])?preg_replace('|[^a-z0-9_.\-/]|i','',(string)$_GET['stylesheet']):''; }
}
if(!function_exists('preview_theme_ob_filter')){
    function preview_theme_ob_filter($content){ elvado_ext_lg_dep(__FUNCTION__,'4.3.0');return $content; }
}
if(!function_exists('preview_theme_ob_filter_callback')){
    function preview_theme_ob_filter_callback($matches){ elvado_ext_lg_dep(__FUNCTION__,'4.3.0');return is_array($matches)?(string)($matches[0]??''):(string)$matches; }
}
if(!function_exists('get_comments_popup_template')){
    function get_comments_popup_template(){
        elvado_ext_lg_dep(__FUNCTION__,'4.5.0');
        $t='';
        foreach([get_stylesheet_directory(),get_template_directory()] as $d)if(is_file($d.'/comments-popup.php')){ $t=$d.'/comments-popup.php';break; }
        return apply_filters('comments_popup_template',$t);
    }
}
if(!function_exists('comments_popup_script')){
    function comments_popup_script($width=400,$height=400,$file=''){
        elvado_ext_lg_dep(__FUNCTION__,'4.5.0');
        $GLOBALS['wpcommentspopupfile']=$file;$GLOBALS['wpcommentsjavascript']=1;
    }
}
if(!function_exists('wp_embed_handler_googlevideo')){
    function wp_embed_handler_googlevideo($matches,$attr,$url,$rawattr){ elvado_ext_lg_dep(__FUNCTION__,'4.6.0');return ''; }
}
if(!function_exists('get_shortcut_link')){
    function get_shortcut_link(){ elvado_ext_lg_dep(__FUNCTION__,'4.9.0');return apply_filters('shortcut_link',''); }
}
if(!function_exists('wp_ajax_press_this_save_post')){
    function wp_ajax_press_this_save_post(){
        elvado_ext_lg_dep(__FUNCTION__,'4.9.0','the Press This plugin');
        wp_send_json_error(['errorMessage'=>__('The Press This plugin is required.')]);
    }
}
if(!function_exists('wp_ajax_press_this_add_category')){
    function wp_ajax_press_this_add_category(){
        elvado_ext_lg_dep(__FUNCTION__,'4.9.0','the Press This plugin');
        wp_send_json_error(['errorMessage'=>__('The Press This plugin is required.')]);
    }
}
if(!function_exists('the_editor')){
    function the_editor($content,$id='content',$prev_id='title',$media_buttons=true,$tab_index=2,$extended=true){
        elvado_ext_lg_dep(__FUNCTION__,'3.3.0','wp_editor()');
        wp_editor($content,$id,['media_buttons'=>$media_buttons,'tabindex'=>$tab_index]);
    }
}
if(!function_exists('rich_edit_exists')){
    function rich_edit_exists(){ elvado_ext_lg_dep(__FUNCTION__,'3.9.0');return (bool)apply_filters('rich_edit_exists',false); }
}

/* ───────── Taxonomie / Cache / Sonstiges ───────── */
if(!function_exists('is_taxonomy')){
    function is_taxonomy($taxonomy){ elvado_ext_lg_dep(__FUNCTION__,'3.0.0','taxonomy_exists()');return taxonomy_exists($taxonomy); }
}
if(!function_exists('is_term')){
    function is_term($term,$taxonomy='',$parent=0){ elvado_ext_lg_dep(__FUNCTION__,'3.0.0','term_exists()');return term_exists($term,$taxonomy,$parent); }
}
if(!function_exists('is_plugin_page')){
    function is_plugin_page(){ global $plugin_page;elvado_ext_lg_dep(__FUNCTION__,'3.1.0');return !empty($plugin_page); }
}
if(!function_exists('update_category_cache')){
    function update_category_cache(){ elvado_ext_lg_dep(__FUNCTION__,'3.1.0');return true; }
}
if(!function_exists('wp_timezone_supported')){
    function wp_timezone_supported(){ elvado_ext_lg_dep(__FUNCTION__,'3.2.0');return true; }
}
if(!function_exists('update_page_cache')){
    function update_page_cache(&$pages){ elvado_ext_lg_dep(__FUNCTION__,'3.4.0','update_post_cache()');update_post_cache($pages); }
}
if(!function_exists('clean_page_cache')){
    function clean_page_cache($id){ elvado_ext_lg_dep(__FUNCTION__,'3.4.0','clean_post_cache()');clean_post_cache($id); }
}
if(!function_exists('wp_explain_nonce')){
    function wp_explain_nonce($action){ elvado_ext_lg_dep(__FUNCTION__,'3.4.1','wp_nonce_ays()');return __('Are you sure you want to do this?'); }
}
if(!function_exists('_usort_terms_by_ID')){
    function _usort_terms_by_ID($a,$b){ elvado_ext_lg_dep(__FUNCTION__,'4.5.0','wp_list_sort()');return $a->term_id<=>$b->term_id; }
}
if(!function_exists('_usort_terms_by_name')){
    function _usort_terms_by_name($a,$b){ elvado_ext_lg_dep(__FUNCTION__,'4.5.0','wp_list_sort()');return strcmp($a->name,$b->name); }
}
if(!function_exists('_sort_nav_menu_items')){
    function _sort_nav_menu_items($a,$b){
        global $_menu_item_sort_prop;
        elvado_ext_lg_dep(__FUNCTION__,'4.7.0','wp_list_sort()');
        $p=$_menu_item_sort_prop;
        if(empty($p)||!isset($a->$p)||!isset($b->$p))return 0;
        $x=(int)$a->$p;$y=(int)$b->$p;
        if($x==$y)return 0;
        return ($a->$p==$x&&$b->$p==$y)?$x-$y:strcmp($a->$p,$b->$p);
    }
}
if(!function_exists('wp_unregister_GLOBALS')){
    function wp_unregister_GLOBALS(){ elvado_ext_lg_dep(__FUNCTION__,'5.2.0'); }
}
if(!function_exists('_wp_register_meta_args_whitelist')){
    function _wp_register_meta_args_whitelist($args,$default_args){
        elvado_ext_lg_dep(__FUNCTION__,'5.5.0','_wp_register_meta_args_allowed_list()');
        return array_intersect_key($args,$default_args);
    }
}
if(!function_exists('remove_option_whitelist')){
    function remove_option_whitelist($del_options,$options=''){
        elvado_ext_lg_dep(__FUNCTION__,'5.5.0','remove_allowed_options()');
        return remove_allowed_options($del_options,$options);
    }
}
if(!function_exists('debug_fopen')){
    function debug_fopen($filename,$mode){ elvado_ext_lg_dep(__FUNCTION__,'3.4.0','error_log()');return false; }
}
if(!function_exists('debug_fwrite')){
    function debug_fwrite($fp,$message){ elvado_ext_lg_dep(__FUNCTION__,'3.4.0','error_log()');if(!empty($GLOBALS['debug'])&&is_string($message))error_log($message); }
}
if(!function_exists('debug_fclose')){
    function debug_fclose($fp){ elvado_ext_lg_dep(__FUNCTION__,'3.4.0','error_log()'); }
}

/* ───────── HTTP / SSL ───────── */
if(!function_exists('url_is_accessable_via_ssl')){
    function url_is_accessable_via_ssl($url){
        elvado_ext_lg_dep(__FUNCTION__,'4.0.0');
        $r=wp_remote_get(set_url_scheme($url,'https'));
        if(is_wp_error($r))return false;
        $c=wp_remote_retrieve_response_code($r);
        return $c==200||$c==401;
    }
}
if(!function_exists('wp_get_http')){
    function wp_get_http($url,$file_path=false,$red=1){
        elvado_ext_lg_dep(__FUNCTION__,'4.4.0','WP_Http');
        if($red>5)return false;
        $opt=['redirection'=>5];
        if($file_path===false)$opt['method']='HEAD';else{ $opt['stream']=true;$opt['filename']=$file_path; }
        $resp=wp_safe_remote_request($url,$opt);
        if(is_wp_error($resp))return false;
        $h=wp_remote_retrieve_headers($resp);
        $h=is_object($h)&&method_exists($h,'getAll')?$h->getAll():(array)$h;
        $h['response']=wp_remote_retrieve_response_code($resp);
        if($file_path===false)return $h;
        if($h['response']!=200){ @unlink($file_path);return false; }
        return $h;
    }
}
if(!function_exists('force_ssl_login')){
    function force_ssl_login($force=null){ elvado_ext_lg_dep(__FUNCTION__,'4.4.0','force_ssl_admin()');return force_ssl_admin($force); }
}

/* ───────── Roboter-Meta / Ausgaben ───────── */
if(!function_exists('wp_no_robots')){
    function wp_no_robots(){
        elvado_ext_lg_dep(__FUNCTION__,'5.7.0','wp_robots_no_robots()');
        echo get_option('blog_public')?"<meta name='robots' content='noindex,follow' />\n":"<meta name='robots' content='noindex,nofollow' />\n";
    }
}
if(!function_exists('wp_sensitive_page_meta')){
    function wp_sensitive_page_meta(){
        elvado_ext_lg_dep(__FUNCTION__,'5.7.0','wp_robots_sensitive_page()');
        echo "<meta name='robots' content='noindex,noarchive' />\n<meta name='referrer' content='strict-origin-when-cross-origin' />\n";
    }
}
if(!function_exists('print_embed_styles')){
    function print_embed_styles(){ elvado_ext_lg_dep(__FUNCTION__,'6.4.0','wp_enqueue_embed_styles()'); }
}
if(!function_exists('print_emoji_styles')){
    function print_emoji_styles(){
        elvado_ext_lg_dep(__FUNCTION__,'6.4.0','wp_enqueue_emoji_styles()');
        echo "<style>img.wp-smiley,img.emoji{display:inline!important;border:none!important;box-shadow:none!important;height:1em!important;width:1em!important;margin:0 .07em!important;vertical-align:-.1em!important;background:none!important;padding:0!important}</style>\n";
    }
}
if(!function_exists('wp_admin_bar_header')){
    function wp_admin_bar_header(){ elvado_ext_lg_dep(__FUNCTION__,'6.4.0','wp_enqueue_admin_bar_header_styles()');echo "<style media='print'>#wpadminbar{display:none}</style>\n"; }
}
if(!function_exists('wp_update_https_detection_errors')){
    function wp_update_https_detection_errors(){ elvado_ext_lg_dep(__FUNCTION__,'6.4.0'); }
}
if(!function_exists('the_block_template_skip_link')){
    function the_block_template_skip_link(){
        elvado_ext_lg_dep(__FUNCTION__,'6.4.0','wp_enqueue_block_template_skip_link()');
        echo '<a class="skip-link screen-reader-text" href="#wp--skip-link--target">'.esc_html__('Skip to content')."</a>\n";
    }
}
if(!function_exists('global_terms_enabled')){
    function global_terms_enabled(){ elvado_ext_lg_dep(__FUNCTION__,'6.1.0');return false; }
}
if(!function_exists('_filter_query_attachment_filenames')){
    function _filter_query_attachment_filenames($clauses){ elvado_ext_lg_dep(__FUNCTION__,'6.0.0');remove_filter('posts_clauses','_filter_query_attachment_filenames');return $clauses; }
}
if(!function_exists('wp_queue_comments_for_comment_meta_lazyload')){
    function wp_queue_comments_for_comment_meta_lazyload($comments){ elvado_ext_lg_dep(__FUNCTION__,'6.3.0','wp_lazyload_comment_meta()'); }
}
if(!function_exists('wp_get_loading_attr_default')){
    function wp_get_loading_attr_default($context){
        elvado_ext_lg_dep(__FUNCTION__,'6.3.0','wp_get_loading_optimization_attributes()');
        $a=wp_get_loading_optimization_attributes('img',[],$context);
        return $a['loading']??false;
    }
}
if(!function_exists('wp_img_tag_add_loading_attr')){
    function wp_img_tag_add_loading_attr($image,$context){
        elvado_ext_lg_dep(__FUNCTION__,'6.3.0','wp_get_loading_optimization_attributes()');
        if(preg_match('/\sloading\s*=/i',$image))return $image;
        $v=apply_filters('wp_img_tag_add_loading_attr',wp_get_loading_attr_default($context),$image,$context);
        if(!$v)return $image;
        if(!in_array($v,['lazy','eager'],true))$v='lazy';
        return str_replace('<img','<img loading="'.esc_attr($v).'"',$image);
    }
}
if(!function_exists('wp_img_tag_add_decoding_attr')){
    function wp_img_tag_add_decoding_attr($image,$context){
        elvado_ext_lg_dep(__FUNCTION__,'6.4.0','wp_get_loading_optimization_attributes()');
        if(preg_match('/\sdecoding\s*=/i',$image))return $image;
        $v=apply_filters('wp_img_tag_add_decoding_attr','async',$image,$context);
        return in_array($v,['async','sync','auto'],true)?str_replace('<img','<img decoding="'.esc_attr($v).'"',$image):$image;
    }
}

/* ───────── Blöcke / Block-Themes ───────── */
if(!function_exists('elvado_ext_lg_walk')){
    /** Blockbaum rekursiv durchlaufen; $fn(array &$block): bool meldet eine Änderung. */
    function elvado_ext_lg_walk(array &$blocks,callable $fn): bool {
        $changed=false;
        foreach($blocks as &$b){
            if($fn($b))$changed=true;
            if(!empty($b['innerBlocks'])&&elvado_ext_lg_walk($b['innerBlocks'],$fn))$changed=true;
        }
        return $changed;
    }
}
if(!function_exists('_inject_theme_attribute_in_block_template_content')){
    function _inject_theme_attribute_in_block_template_content($template_content){
        elvado_ext_lg_dep(__FUNCTION__,'6.4.0','traverse_and_serialize_blocks()');
        if(!function_exists('parse_blocks')||!function_exists('serialize_block'))return $template_content;
        $blocks=parse_blocks($template_content);
        $ch=elvado_ext_lg_walk($blocks,function(&$b){ if('core/template-part'===($b['blockName']??'')&&!isset($b['attrs']['theme'])){ $b['attrs']['theme']=get_stylesheet();return true; }return false; });
        return $ch?implode('',array_map('serialize_block',$blocks)):$template_content;
    }
}
if(!function_exists('_remove_theme_attribute_in_block_template_content')){
    function _remove_theme_attribute_in_block_template_content($template_content){
        elvado_ext_lg_dep(__FUNCTION__,'6.4.0','traverse_and_serialize_blocks()');
        if(!function_exists('parse_blocks')||!function_exists('serialize_block'))return $template_content;
        $blocks=parse_blocks($template_content);
        $ch=elvado_ext_lg_walk($blocks,function(&$b){ if('core/template-part'===($b['blockName']??'')&&isset($b['attrs']['theme'])){ unset($b['attrs']['theme']);return true; }return false; });
        return $ch?implode('',array_map('serialize_block',$blocks)):$template_content;
    }
}
if(!function_exists('_resolve_home_block_template')){
    function _resolve_home_block_template(){
        elvado_ext_lg_dep(__FUNCTION__,'6.2.0');
        $front=(int)get_option('page_on_front');
        if('page'===get_option('show_on_front')&&$front)return ['postType'=>'page','postId'=>$front];
        if(!function_exists('resolve_block_template'))return null;
        $t=resolve_block_template('home',['front-page','home','index'],'');
        return ($t&&isset($t->id))?['postType'=>'wp_template','postId'=>$t->id]:null;
    }
}
if(!function_exists('_excerpt_render_inner_columns_blocks')){
    function _excerpt_render_inner_columns_blocks($columns,$allowed_blocks){
        elvado_ext_lg_dep(__FUNCTION__,'5.8.0','_excerpt_render_inner_blocks()');
        $out='';
        foreach($columns['innerBlocks']??[] as $col)foreach($col['innerBlocks']??[] as $b)
            if(in_array($b['blockName']??'',$allowed_blocks,true)&&empty($b['innerBlocks']))$out.=render_block($b);
        return $out;
    }
}
if(!function_exists('_wp_multiple_block_styles')){
    function _wp_multiple_block_styles($metadata){ elvado_ext_lg_dep(__FUNCTION__,'6.1.0');return $metadata; }
}
if(!function_exists('wp_typography_get_css_variable_inline_style')){
    function wp_typography_get_css_variable_inline_style($attributes,$feature,$css_property){
        elvado_ext_lg_dep(__FUNCTION__,'6.1.0','wp_style_engine_get_styles()');
        if(!isset($attributes['style']['typography'][$feature]))return null;
        $v=$attributes['style']['typography'][$feature];
        if(!is_string($v)||!str_contains($v,'var:preset|'))return null;
        $slug=strtolower(trim(preg_replace('/([a-z])([A-Z0-9])/','$1-$2',substr($v,strrpos($v,'|')+1)),'-'));
        return sprintf('%s: var(--wp--preset--%s--%s);',$css_property,$css_property,$slug);
    }
}
if(!function_exists('elvado_ext_lg_skip')){
    /** Block-Support mit „SkipSerialization“ (alte und neue Schlüssel). */
    function elvado_ext_lg_skip($block_type,array $keys): bool {
        $sup=is_object($block_type)?($block_type->supports??[]):[];
        foreach($keys as $k)if(is_array($sup[$k]??null)&&!empty($sup[$k]['__experimentalSkipSerialization']))return true;
        return false;
    }
}
if(!function_exists('wp_skip_border_serialization')){
    function wp_skip_border_serialization($block_type){ elvado_ext_lg_dep(__FUNCTION__,'6.0.0','wp_should_skip_block_supports_serialization()');return elvado_ext_lg_skip($block_type,['__experimentalBorder','border']); }
}
if(!function_exists('wp_skip_dimensions_serialization')){
    function wp_skip_dimensions_serialization($block_type){ elvado_ext_lg_dep(__FUNCTION__,'6.0.0','wp_should_skip_block_supports_serialization()');return elvado_ext_lg_skip($block_type,['dimensions','__experimentalDimensions']); }
}
if(!function_exists('wp_skip_spacing_serialization')){
    function wp_skip_spacing_serialization($block_type){ elvado_ext_lg_dep(__FUNCTION__,'6.0.0','wp_should_skip_block_supports_serialization()');return elvado_ext_lg_skip($block_type,['spacing']); }
}
if(!function_exists('wp_add_iframed_editor_assets_html')){
    function wp_add_iframed_editor_assets_html(){ elvado_ext_lg_dep(__FUNCTION__,'6.0.0'); }
}
if(!function_exists('wp_add_editor_classic_theme_styles')){
    function wp_add_editor_classic_theme_styles($editor_settings){ elvado_ext_lg_dep(__FUNCTION__,'6.5.0');return $editor_settings; }
}
if(!function_exists('_wp_theme_json_webfonts_handler')){
    function _wp_theme_json_webfonts_handler(){ elvado_ext_lg_dep(__FUNCTION__,'6.4.0','wp_print_font_faces()'); }
}
if(!function_exists('block_core_query_ensure_interactivity_dependency')){
    function block_core_query_ensure_interactivity_dependency(){ elvado_ext_lg_dep(__FUNCTION__,'6.5.0','wp_register_script_module()'); }
}
if(!function_exists('block_core_file_ensure_interactivity_dependency')){
    function block_core_file_ensure_interactivity_dependency(){ elvado_ext_lg_dep(__FUNCTION__,'6.5.0','wp_register_script_module()'); }
}
if(!function_exists('block_core_image_ensure_interactivity_dependency')){
    function block_core_image_ensure_interactivity_dependency(){ elvado_ext_lg_dep(__FUNCTION__,'6.5.0','wp_register_script_module()'); }
}
if(!function_exists('wp_render_elements_support')){
    function wp_render_elements_support($block_content,$block){ elvado_ext_lg_dep(__FUNCTION__,'6.6.0','wp_render_elements_support_styles()');return $block_content; }
}
if(!function_exists('wp_interactivity_process_directives_of_interactive_blocks')){
    function wp_interactivity_process_directives_of_interactive_blocks($tags){ elvado_ext_lg_dep(__FUNCTION__,'6.6.0');return $tags; }
}
if(!function_exists('wp_enqueue_global_styles_custom_css')){
    function wp_enqueue_global_styles_custom_css(){ elvado_ext_lg_dep(__FUNCTION__,'6.7.0','wp_enqueue_global_styles()'); }
}
if(!function_exists('wp_create_block_style_variation_instance_name')){
    function wp_create_block_style_variation_instance_name($block,$variation){ elvado_ext_lg_dep(__FUNCTION__,'6.7.0');return $variation.'--'.md5(serialize($block)); }
}
if(!function_exists('block_core_navigation_submenu_build_css_colors')){
    function block_core_navigation_submenu_build_css_colors($context,$attributes,$is_sub_menu=false){
        elvado_ext_lg_dep(__FUNCTION__,'6.7.0');
        $c=['css_classes'=>[],'inline_styles'=>''];
        foreach(['text'=>['color','Text','has-text-color'],'background'=>['background-color','Background','has-background']] as $t=>[$prop,$cap,$flag]){
            $slug=$is_sub_menu?($context['overlay'.$cap.'Color']??null):($context[$t.'Color']??null);
            $cust=$is_sub_menu?($context['customOverlay'.$cap.'Color']??null):($context['custom'.$cap.'Color']??null);
            if($slug){ $c['css_classes'][]=$flag;$c['css_classes'][]='has-'.$slug.'-'.$prop; }
            elseif($cust){ $c['css_classes'][]=$flag;$c['inline_styles'].=$prop.': '.esc_attr($cust).';'; }
        }
        return $c;
    }
}

/* ───────── Duotone / Farbhelfer (tinycolor-Verhalten) ───────── */
if(!function_exists('wp_tinycolor_bound01')){
    function wp_tinycolor_bound01($n,$max){
        elvado_ext_lg_dep(__FUNCTION__,'6.3.0');
        if(is_string($n)&&str_contains($n,'.')&&1.0===(float)$n)$n='100%';
        $pct=is_string($n)&&str_contains($n,'%');
        $n=min($max,max(0,(float)$n));
        if($pct)$n=(int)($n*$max)/100;
        if(abs($n-$max)<0.000001)return 1.0;
        return fmod($n,$max)/(float)$max;
    }
}
if(!function_exists('_wp_tinycolor_bound_alpha')){
    function _wp_tinycolor_bound_alpha($n){
        elvado_ext_lg_dep(__FUNCTION__,'6.3.0');
        if(is_numeric($n)){ $n=(float)$n;if($n>=0&&$n<=1)return $n; }
        return 1;
    }
}
if(!function_exists('wp_tinycolor_rgb_to_rgb')){
    function wp_tinycolor_rgb_to_rgb($rgb_color){
        elvado_ext_lg_dep(__FUNCTION__,'6.3.0');
        return ['r'=>wp_tinycolor_bound01($rgb_color['r'],255)*255,'g'=>wp_tinycolor_bound01($rgb_color['g'],255)*255,'b'=>wp_tinycolor_bound01($rgb_color['b'],255)*255];
    }
}
if(!function_exists('wp_tinycolor_hue_to_rgb')){
    function wp_tinycolor_hue_to_rgb($p,$q,$t){
        elvado_ext_lg_dep(__FUNCTION__,'6.3.0');
        if($t<0)$t+=1;
        if($t>1)$t-=1;
        if($t<1/6)return $p+($q-$p)*6*$t;
        if($t<1/2)return $q;
        if($t<2/3)return $p+($q-$p)*(2/3-$t)*6;
        return $p;
    }
}
if(!function_exists('wp_tinycolor_hsl_to_rgb')){
    function wp_tinycolor_hsl_to_rgb($hsl_color){
        elvado_ext_lg_dep(__FUNCTION__,'6.3.0');
        $h=wp_tinycolor_bound01($hsl_color['h'],360);$s=wp_tinycolor_bound01($hsl_color['s'],100);$l=wp_tinycolor_bound01($hsl_color['l'],100);
        if(0.0===$s){ $r=$g=$b=$l; }
        else{
            $q=$l<.5?$l*(1+$s):$l+$s-$l*$s;$p=2*$l-$q;
            $r=wp_tinycolor_hue_to_rgb($p,$q,$h+1/3);$g=wp_tinycolor_hue_to_rgb($p,$q,$h);$b=wp_tinycolor_hue_to_rgb($p,$q,$h-1/3);
        }
        return ['r'=>$r*255,'g'=>$g*255,'b'=>$b*255];
    }
}
if(!function_exists('wp_tinycolor_string_to_rgb')){
    /** Farbstring (transparent, #rgb, #rrggbb, rgb(a)(), hsl(a)()) in ['r','g','b','a']; unbekannt: Schwarz. */
    function wp_tinycolor_string_to_rgb($color_str){
        elvado_ext_lg_dep(__FUNCTION__,'6.3.0');
        $color_str=strtolower(trim((string)$color_str));
        if('transparent'===$color_str)return ['r'=>0,'g'=>0,'b'=>0,'a'=>0];
        if(preg_match('/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/',$color_str,$m)){
            $h=$m[1];
            if(strlen($h)<=4)$h=preg_replace('/(.)/','$1$1',$h);
            return ['r'=>hexdec(substr($h,0,2)),'g'=>hexdec(substr($h,2,2)),'b'=>hexdec(substr($h,4,2)),'a'=>strlen($h)===8?round(hexdec(substr($h,6,2))/255,2):1];
        }
        if(preg_match('/^(rgb|hsl)a?\(\s*([\d.]+%?)[\s,]+([\d.]+%?)[\s,]+([\d.]+%?)(?:[\s,\/]+([\d.]+%?))?\s*\)$/',$color_str,$m)){
            $a=isset($m[5])&&$m[5]!==''?_wp_tinycolor_bound_alpha(str_ends_with($m[5],'%')?(float)$m[5]/100:$m[5]):1;
            $rgb=$m[1]==='rgb'?wp_tinycolor_rgb_to_rgb(['r'=>$m[2],'g'=>$m[3],'b'=>$m[4]]):wp_tinycolor_hsl_to_rgb(['h'=>$m[2],'s'=>$m[3],'l'=>$m[4]]);
            return ['r'=>$rgb['r'],'g'=>$rgb['g'],'b'=>$rgb['b'],'a'=>$a];
        }
        return ['r'=>0,'g'=>0,'b'=>0,'a'=>1];
    }
}
if(!function_exists('wp_get_duotone_filter_id')){
    function wp_get_duotone_filter_id($preset){
        elvado_ext_lg_dep(__FUNCTION__,'6.3.0','WP_Duotone::get_filter_id_from_preset()');
        return (isset($preset['slug'])&&is_string($preset['slug']))?'wp-duotone-'.$preset['slug']:'';
    }
}
if(!function_exists('wp_get_duotone_filter_property')){
    function wp_get_duotone_filter_property($preset){
        elvado_ext_lg_dep(__FUNCTION__,'6.3.0','WP_Duotone::get_filter_css_property_value_from_preset()');
        if(isset($preset['colors'])&&is_string($preset['colors']))return $preset['colors'];
        return "url('#".wp_get_duotone_filter_id($preset)."')";
    }
}
if(!function_exists('wp_get_duotone_filter_svg')){
    function wp_get_duotone_filter_svg($preset){
        elvado_ext_lg_dep(__FUNCTION__,'6.3.0','WP_Duotone::get_filter_svg_from_preset()');
        if(!isset($preset['colors'])||!is_array($preset['colors']))return '';
        $v=['r'=>[],'g'=>[],'b'=>[]];
        foreach($preset['colors'] as $c){ $rgb=wp_tinycolor_string_to_rgb($c);foreach(['r','g','b'] as $k)$v[$k][]=round($rgb[$k]/255,4); }
        $t=fn($k)=>esc_attr(implode(' ',$v[$k]));
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 0 0" width="0" height="0" focusable="false" role="none" style="visibility:hidden;position:absolute;left:-9999px;overflow:hidden"><defs><filter id="'.esc_attr(wp_get_duotone_filter_id($preset)).'">'
            .'<feColorMatrix color-interpolation-filters="sRGB" type="matrix" values=".299 .587 .114 0 0 .299 .587 .114 0 0 .299 .587 .114 0 0 0 0 0 1 0" />'
            .'<feComponentTransfer color-interpolation-filters="sRGB"><feFuncR type="table" tableValues="'.$t('r').'" /><feFuncG type="table" tableValues="'.$t('g').'" /><feFuncB type="table" tableValues="'.$t('b').'" /></feComponentTransfer>'
            .'<feComposite in2="SourceGraphic" operator="in" /></filter></defs></svg>';
    }
}
if(!function_exists('wp_render_duotone_filter_preset')){
    function wp_render_duotone_filter_preset($preset){ elvado_ext_lg_dep(__FUNCTION__,'5.9.1','wp_get_duotone_filter_svg()');echo wp_get_duotone_filter_svg($preset); }
}
if(!function_exists('wp_register_duotone_support')){
    function wp_register_duotone_support($block_type){
        elvado_ext_lg_dep(__FUNCTION__,'6.3.0','WP_Duotone::register_duotone_support()');
        if(!is_object($block_type)||!property_exists($block_type,'supports'))return;
        $sup=$block_type->supports;
        if(!is_array($sup)||!(isset($sup['filter']['duotone'])&&$sup['filter']['duotone']||!empty($sup['color']['__experimentalDuotone'])))return;
        if(!is_array($block_type->attributes??null))$block_type->attributes=[];
        if(!array_key_exists('style',$block_type->attributes))$block_type->attributes['style']=['type'=>'object'];
    }
}
if(!function_exists('wp_render_duotone_support')){
    function wp_render_duotone_support($block_content,$block){ elvado_ext_lg_dep(__FUNCTION__,'6.3.0','WP_Duotone::render_duotone_support()');return $block_content; }
}
if(!function_exists('wp_get_global_styles_svg_filters')){
    function wp_get_global_styles_svg_filters(){ elvado_ext_lg_dep(__FUNCTION__,'6.3.0','WP_Duotone::get_svg_filters()');return ''; }
}
if(!function_exists('wp_global_styles_render_svg_filters')){
    function wp_global_styles_render_svg_filters(){ elvado_ext_lg_dep(__FUNCTION__,'6.3.0','WP_Duotone::output_svg_filters()'); }
}

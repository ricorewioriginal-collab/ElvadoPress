<?php
// Ergänzender Skript-Lader (wp-includes/script-loader.php): Registrierung der WordPress-Kernskripte und -Stile, Drucken von Kopf-/Fußskripten,
// Blockstile, Globale Stile, Skript-Tags, Skript-Module. Die Registrierung nutzt die vorhandene Schicht (wp_register_script/_style); die
// Ausgabe übernehmen wp_print_styles/wp_print_head_scripts/wp_print_footer_scripts. Verketten/Komprimieren von Dateien gibt es hier nicht
// (script_concat_settings setzt nur die Globals, _print_scripts/_print_styles sind bewusst leer). Hilfsfunktionen beginnen mit _rrw_m_.

if(!function_exists('_rrw_m_reg')){ /** Registriert eine Liste [Handle=>[Pfad, Abhängigkeiten, Version, Fuß]] (Pfad relativ zu wp-includes/ oder absolut). */
function _rrw_m_reg($scripts,array $list,string $kind='script') {
    foreach($list as $h=>$d){ $src=$d[0]??'';if($src!==''&&!preg_match('#^(https?:)?//#',$src))$src=includes_url($src);
        if($kind==='script')wp_register_script($h,$src,$d[1]??[],$d[2]??false,!empty($d[3]));else wp_register_style($h,$src,$d[1]??[],$d[2]??false,$d[3]??'all'); }
} }

/* ───────── Registrierung ───────── */
if(!function_exists('wp_default_scripts')){ function wp_default_scripts($scripts) {
    $l=['jquery-core'=>['js/jquery/jquery.min.js',[],'3.7.1'],'jquery-migrate'=>['js/jquery/jquery-migrate.min.js',[],'3.4.1'],'jquery'=>['',['jquery-core','jquery-migrate'],'3.7.1'],
        'underscore'=>['js/underscore.min.js',[],'1.13.7',1],'backbone'=>['js/backbone.min.js',['underscore','jquery'],'1.6.0',1],'wp-util'=>['js/wp-util.min.js',['underscore','jquery'],false,1],'wp-backbone'=>['js/wp-backbone.min.js',['backbone'],false,1],
        'hoverIntent'=>['js/hoverIntent.min.js',['jquery'],'1.10.2',1],'clipboard'=>['js/clipboard.min.js',[],'2.0.11',1],'imagesloaded'=>['js/imagesloaded.min.js',[],'5.0.0',1],'masonry'=>['js/masonry.min.js',['imagesloaded'],'4.2.2',1],
        'jquery-masonry'=>['js/jquery/jquery.masonry.min.js',['jquery','masonry'],'3.1.2b',1],'json2'=>['js/json2.min.js',[],'2015-05-03'],'comment-reply'=>['js/comment-reply.min.js',[],false,1],'thickbox'=>['js/thickbox/thickbox.js',['jquery'],'3.1-20121105',1],
        'shortcode'=>['js/shortcode.min.js',['underscore'],false,1],'word-count'=>['js/word-count.min.js',[],false,1],'wp-embed'=>['js/wp-embed.min.js',[],false,1],'wp-emoji'=>['js/wp-emoji.min.js',[],false,1],
        'mediaelement'=>['js/mediaelement/mediaelement-and-player.min.js',['jquery'],'4.2.17',1],'mediaelement-vimeo'=>['js/mediaelement/renderers/vimeo.min.js',['mediaelement'],'4.2.17',1],
        'wp-mediaelement'=>['js/mediaelement/wp-mediaelement.min.js',['mediaelement'],false,1],'wp-playlist'=>['js/mediaelement/wp-playlist.min.js',['wp-util','backbone','mediaelement'],false,1],
        'plupload'=>['js/plupload/plupload.full.min.js',[],'2.1.9'],'wp-plupload'=>['js/plupload/wp-plupload.min.js',['plupload','jquery','json2','media-models'],false,1],
        'media-models'=>['js/media-models.min.js',['wp-backbone'],false,1],'media-views'=>['js/media-views.min.js',['utils','media-models','wp-plupload','jquery-ui-sortable','wp-mediaelement','wp-api-request','wp-a11y','clipboard'],false,1],
        'media-editor'=>['js/media-editor.min.js',['shortcode','media-views'],false,1],'media-audiovideo'=>['js/media-audiovideo.min.js',['media-editor'],false,1],'mce-view'=>['js/mce-view.min.js',['shortcode','jquery','media-views','media-audiovideo'],false,1],
        'customize-loader'=>['js/customize-loader.min.js',['jquery','wp-backbone'],false,1],'wp-api-request'=>['js/api-request.min.js',['jquery'],false,1],'utils'=>['js/utils.min.js',['jquery'],false,1],
        'heartbeat'=>['js/heartbeat.min.js',['jquery','wp-hooks'],false,1],'wp-ajax-response'=>['js/wp-ajax-response.min.js',['jquery','wp-a11y'],false,1],'community-events'=>['js/community-events.min.js',['jquery','wp-util','wp-a11y'],false,1]];
    _rrw_m_reg($scripts,$l);
    // jQuery UI (Kern, Widgets, Interaktionen, Effekte): ein Satz mit gemeinsamen Abhängigkeiten
    $ui=['core'=>[],'widget'=>['jquery-ui-core'],'mouse'=>['jquery-ui-core','jquery-ui-widget'],'position'=>['jquery-ui-core'],'sortable'=>['jquery-ui-mouse'],'draggable'=>['jquery-ui-mouse'],'droppable'=>['jquery-ui-draggable'],'resizable'=>['jquery-ui-mouse'],'selectable'=>['jquery-ui-mouse'],
        'accordion'=>['jquery-ui-core','jquery-ui-widget'],'tabs'=>['jquery-ui-core','jquery-ui-widget'],'datepicker'=>['jquery-ui-core'],'dialog'=>['jquery-ui-resizable','jquery-ui-draggable','jquery-ui-button'],'button'=>['jquery-ui-core','jquery-ui-widget'],
        'autocomplete'=>['jquery-ui-menu','wp-a11y'],'menu'=>['jquery-ui-core','jquery-ui-widget','jquery-ui-position'],'slider'=>['jquery-ui-mouse'],'progressbar'=>['jquery-ui-widget'],'tooltip'=>['jquery-ui-core','jquery-ui-widget','jquery-ui-position'],'spinner'=>['jquery-ui-button']];
    foreach($ui as $n=>$deps)wp_register_script('jquery-ui-'.$n,includes_url('js/jquery/ui/'.$n.'.min.js'),array_merge(['jquery'],$deps),'1.13.3',true);
    wp_default_packages($scripts);
    do_action('wp_default_scripts',$scripts);
} }
if(!function_exists('wp_default_styles')){ function wp_default_styles($styles) {
    _rrw_m_reg($styles,['dashicons'=>['css/dashicons.min.css'],'admin-bar'=>['css/admin-bar.min.css',['dashicons']],'buttons'=>['css/buttons.min.css'],'common'=>['../wp-admin/css/common.min.css'],'forms'=>['../wp-admin/css/forms.min.css'],
        'mediaelement'=>['js/mediaelement/mediaelementplayer-legacy.min.css'],'wp-mediaelement'=>['js/mediaelement/wp-mediaelement.min.css',['mediaelement']],'media-views'=>['css/media-views.min.css',['buttons','dashicons','wp-mediaelement']],
        'thickbox'=>['js/thickbox/thickbox.css',['dashicons']],'imgareaselect'=>['js/imgareaselect/imgareaselect.css'],'wp-pointer'=>['css/wp-pointer.min.css',['dashicons']],'editor-buttons'=>['css/editor.min.css',['dashicons']],
        'wp-embed-template-ie'=>['css/wp-embed-template-ie.min.css'],'wp-auth-check'=>['css/wp-auth-check.min.css',['dashicons']],'customize-controls'=>['../wp-admin/css/customize-controls.min.css',['wp-admin','colors','imgareaselect']],
        'wp-block-library'=>['css/dist/block-library/style.min.css'],'wp-block-library-theme'=>['css/dist/block-library/theme.min.css'],'classic-theme-styles'=>['css/classic-themes.min.css'],
        'wp-components'=>['css/dist/components/style.min.css',['dashicons']],'wp-edit-blocks'=>['css/dist/block-library/editor.min.css',['wp-components','wp-editor','wp-block-library']],'wp-reset-editor-styles'=>['css/dist/block-library/reset.min.css',['common','forms']],
        'wp-editor'=>['css/dist/editor/style.min.css',['wp-components','wp-block-editor','wp-reusable-blocks','wp-patterns']],'wp-block-editor'=>['css/dist/block-editor/style.min.css',['wp-components']],'wp-format-library'=>['css/dist/format-library/style.min.css',['wp-block-editor']]],'style');
    do_action('wp_default_styles',$styles);
} }
if(!function_exists('wp_default_packages')){ function wp_default_packages($scripts) {
    wp_default_packages_vendor($scripts);wp_register_development_scripts($scripts);wp_register_tinymce_scripts($scripts);wp_default_packages_scripts($scripts);
    if(did_action('init'))wp_default_packages_inline_scripts($scripts);
} }
if(!function_exists('wp_default_packages_vendor')){ function wp_default_packages_vendor($scripts) {
    $v=['react'=>[['wp-polyfill'],'18.3.1'],'react-dom'=>[['react'],'18.3.1'],'react-jsx-runtime'=>[['react'],'18.3.1'],'regenerator-runtime'=>[[],'0.14.1'],'moment'=>[[],'2.30.1'],'lodash'=>[[],'4.17.21'],
        'wp-polyfill-fetch'=>[[],'3.6.20'],'wp-polyfill-formdata'=>[[],'4.0.10'],'wp-polyfill-node-contains'=>[[],'4.8.0'],'wp-polyfill-url'=>[[],'3.6.4'],'wp-polyfill-dom-rect'=>[[],'4.8.0'],
        'wp-polyfill-element-closest'=>[[],'3.0.2'],'wp-polyfill-object-fit'=>[[],'2.3.5'],'wp-polyfill-inert'=>[[],'3.1.2'],'wp-polyfill'=>[['regenerator-runtime'],'3.15.0']];
    foreach($v as $h=>[$deps,$ver])wp_register_script($h,includes_url("js/dist/vendor/$h.min.js"),$deps,$ver,true);
    if(did_action('init'))wp_add_inline_script('wp-polyfill',wp_get_script_polyfill($scripts,['window.fetch'=>'wp-polyfill-fetch','window.FormData && window.FormData.prototype.keys'=>'wp-polyfill-formdata','Element.prototype.matches && Element.prototype.closest'=>'wp-polyfill-element-closest',
        "'objectFit' in document.documentElement.style"=>'wp-polyfill-object-fit',"typeof Node === 'function' && Node.prototype.contains"=>'wp-polyfill-node-contains','document.contains'=>'wp-polyfill-node-contains','window.DOMRect'=>'wp-polyfill-dom-rect',
        'window.URL && window.URL.prototype && window.URLSearchParams'=>'wp-polyfill-url','window.inert'=>'wp-polyfill-inert']),'after');
} }
if(!function_exists('wp_get_script_polyfill')){ /** JavaScript, das fehlende Browser-Funktionen (Test => Handle) bei Bedarf nachlädt. */
function wp_get_script_polyfill($scripts,$tests) {
    $reg=$scripts->registered;$o='';
    foreach($tests as $test=>$handle){ if(!isset($reg[$handle]))continue;$src=(string)$reg[$handle]->src;$ver=$reg[$handle]->ver;
        if(!preg_match('|^(https?:)?//|',$src))$src=site_url($src);if(!empty($ver))$src=add_query_arg('ver',$ver,$src);
        $src=apply_filters('script_loader_src',$src,$handle);if(!$src)continue;
        $o.='( '.$test.' )||document.write( \'<script src="'.$src.'"></scr\' + \'ipt>\' );'; }
    return $o;
} }
if(!function_exists('wp_register_development_scripts')){ function wp_register_development_scripts($scripts) {
    if(!defined('SCRIPT_DEBUG')||!SCRIPT_DEBUG||!isset($scripts->registered['react']))return;
    wp_register_script('wp-react-refresh-runtime',includes_url('js/dist/development/react-refresh-runtime.min.js'),[],'0.14.0');
    wp_register_script('wp-react-refresh-entry',includes_url('js/dist/development/react-refresh-entry.min.js'),['wp-react-refresh-runtime'],'0.14.0');
} }
if(!function_exists('wp_register_tinymce_scripts')){ function wp_register_tinymce_scripts($scripts,$force_uncompressed=false) {
    $v='49110-20201110';
    wp_register_script('wp-tinymce-root',includes_url('js/tinymce/tinymce.min.js'),[],$v,true);
    wp_register_script('wp-tinymce',includes_url('js/tinymce/plugins/compat3x/plugin.min.js'),['wp-tinymce-root'],$v,true);
    wp_register_script('wp-tinymce-lists',includes_url('js/tinymce/plugins/lists/plugin.min.js'),['wp-tinymce'],$v,true);
    wp_tinymce_inline_scripts();
} }
if(!function_exists('wp_tinymce_inline_scripts')){ function wp_tinymce_inline_scripts() {
    $js='window.wpEditorL10n = {tinymce: {baseURL: '.wp_json_encode(includes_url('js/tinymce')).', suffix: '.(defined('SCRIPT_DEBUG')&&SCRIPT_DEBUG?'""':'".min"').', settings: {}}};';
    wp_add_inline_script('wp-tinymce-root',$js,'before');
} }
if(!function_exists('wp_default_packages_scripts')){ function wp_default_packages_scripts($scripts) {
    $assets=null;$files=[];
    if(function_exists('rrw_wp_core_dir'))$files[]=rrw_wp_core_dir().'/assets/script-loader-packages.php';
    $files[]=ABSPATH.WPINC.'/assets/script-loader-packages.php';
    foreach($files as $f)if(is_file($f)){ $a=include $f;if(is_array($a)){ $assets=$a;break; } }
    if($assets===null){   // ohne Kernressourcen: Paketnamen mit den wichtigsten Abhängigkeiten
        $assets=[];$std=['wp-polyfill'];
        foreach(['hooks'=>[],'i18n'=>['wp-hooks'],'dom-ready'=>[],'escape-html'=>[],'url'=>[],'html-entities'=>[],'is-shallow-equal'=>[],'deprecated'=>['wp-hooks'],'element'=>['react','react-dom','wp-escape-html'],'a11y'=>['wp-dom-ready','wp-i18n'],
            'api-fetch'=>['wp-i18n','wp-url'],'compose'=>['wp-element','wp-deprecated','wp-is-shallow-equal'],'data'=>['wp-compose','wp-deprecated','wp-element','wp-is-shallow-equal'],'dom'=>[],'keycodes'=>['wp-i18n'],'primitives'=>['wp-element'],
            'components'=>['wp-element','wp-i18n','wp-compose','wp-dom','wp-keycodes','wp-primitives'],'blocks'=>['wp-data','wp-element','wp-i18n','wp-hooks'],'block-editor'=>['wp-blocks','wp-components','wp-data'],'editor'=>['wp-block-editor','wp-data'],
            'notices'=>['wp-data'],'date'=>['moment','wp-deprecated'],'core-data'=>['wp-data','wp-api-fetch','wp-url'],'format-library'=>['wp-block-editor'],'block-library'=>['wp-blocks','wp-block-editor'],'rich-text'=>['wp-data','wp-element'],
            'wordcount'=>[],'viewport'=>['wp-compose','wp-data'],'warning'=>[],'token-list'=>[],'shortcode'=>[],'style-engine'=>[],'private-apis'=>[]] as $n=>$d)
            $assets["$n.min.js"]=['dependencies'=>array_merge($std,$d),'version'=>$GLOBALS['wp_version']];
    }
    foreach($assets as $file=>$d){ $name=str_replace('.min.js','',basename((string)$file));$h='wp-'.$name;
        $deps=array_values(array_filter((array)($d['dependencies']??[]),fn($x)=>is_string($x)));
        wp_register_script($h,includes_url('js/dist/'.$file),$deps,$d['version']??false,true); }
} }
if(!function_exists('wp_default_packages_inline_scripts')){ function wp_default_packages_inline_scripts($scripts) {
    global $wp_locale;
    wp_add_inline_script('wp-api-fetch',sprintf('wp.apiFetch.use( wp.apiFetch.createRootURLMiddleware( "%s" ) );',sanitize_url(get_rest_url())),'after');
    wp_add_inline_script('wp-api-fetch',implode("\n",[sprintf('wp.apiFetch.nonceMiddleware = wp.apiFetch.createNonceMiddleware( "%s" );',wp_installing()?'':wp_create_nonce('wp_rest')),'wp.apiFetch.use( wp.apiFetch.nonceMiddleware );','wp.apiFetch.use( wp.apiFetch.mediaUploadMiddleware );',sprintf('wp.apiFetch.nonceEndpoint = "%s";',admin_url('admin-ajax.php?action=rest-nonce'))]),'after');
    wp_add_inline_script('wp-i18n',sprintf('wp.i18n.setLocaleData( { "text direction\\u0004ltr": [ "%s" ] } );',is_rtl()?'rtl':'ltr'),'after');
    $tz=wp_timezone_string();$off=(float)get_option('gmt_offset',0);
    $settings=['l10n'=>['locale'=>get_user_locale(),'months'=>['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'],'monthsShort'=>['Jan','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'],
        'weekdays'=>['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'],'weekdaysShort'=>['So','Mo','Di','Mi','Do','Fr','Sa'],'meridiem'=>['am'=>'am','pm'=>'pm','AM'=>'AM','PM'=>'PM'],'relative'=>['future'=>'%s ab jetzt','past'=>'vor %s']],
        'formats'=>['time'=>get_option('time_format','H:i'),'date'=>get_option('date_format','d.m.Y'),'datetime'=>get_option('date_format','d.m.Y').' '.get_option('time_format','H:i'),'datetimeAbbreviated'=>'j. M Y H:i'],
        'timezone'=>['offset'=>$off,'offsetFormatted'=>(string)$off,'string'=>$tz,'abbr'=>$tz]];
    wp_add_inline_script('wp-date','wp.date.setSettings( '.wp_json_encode($settings).' );','after');
} }
if(!function_exists('wp_prototype_before_jquery')){ function wp_prototype_before_jquery($js_array) {
    $p=array_search('prototype',$js_array,true);if($p===false)return $js_array;
    $j=array_search('jquery',$js_array,true);if($j===false||$p<$j)return $js_array;
    unset($js_array[$p]);array_splice($js_array,$j,0,'prototype');return $js_array;
} }
if(!function_exists('wp_just_in_time_script_localization')){ function wp_just_in_time_script_localization() {
    $tags=!empty($GLOBALS['shortcode_tags'])?array_keys($GLOBALS['shortcode_tags']):[];
    wp_localize_script('mce-view','mceViewL10n',['shortcodes'=>$tags]);
    wp_localize_script('word-count','wordCountL10n',['type'=>wp_get_word_count_type(),'shortcodes'=>$tags]);
} }
if(!function_exists('wp_localize_community_events')){ /** Vereinfacht: nur Nonce und Zeitformat (kein Ereignis-Cache der Veranstaltungsabfrage). */
function wp_localize_community_events() {
    if(!wp_script_is('community-events','registered'))return;
    wp_localize_script('community-events','communityEventsData',['nonce'=>wp_create_nonce('community_events'),'cache'=>null,'time_format'=>get_option('time_format','H:i')]);
} }
if(!function_exists('wp_style_loader_src')){ function wp_style_loader_src($src,$handle) {
    if(defined('WP_INSTALLING'))return preg_replace('#^wp-admin/#','./',(string)$src);
    if($handle==='colors'){ $c=get_user_option('admin_color');if(empty($c)||$c==='fresh')return false; }
    return $src;
} }

/* ───────── Ausgabe ───────── */
if(!function_exists('script_concat_settings')){ function script_concat_settings() {
    global $concatenate_scripts,$compress_scripts,$compress_css;
    $out=ini_get('zlib.output_compression')||ini_get('output_handler')==='ob_gzhandler';$can=!wp_installing()&&get_site_option('can_compress_scripts');
    if(!isset($concatenate_scripts)){ $concatenate_scripts=defined('CONCATENATE_SCRIPTS')?CONCATENATE_SCRIPTS:true;if((!is_admin()&&!did_action('login_init'))||(defined('SCRIPT_DEBUG')&&SCRIPT_DEBUG))$concatenate_scripts=false; }
    if(!isset($compress_scripts)){ $compress_scripts=defined('COMPRESS_SCRIPTS')?COMPRESS_SCRIPTS:true;if($compress_scripts&&(!$can||$out))$compress_scripts=false; }
    if(!isset($compress_css)){ $compress_css=defined('COMPRESS_CSS')?COMPRESS_CSS:true;if($compress_css&&(!$can||$out))$compress_css=false; }
} }
if(!function_exists('print_head_scripts')){ function print_head_scripts() {
    if(!did_action('wp_print_scripts'))do_action('wp_print_scripts');script_concat_settings();
    return apply_filters('print_head_scripts',true)?wp_print_head_scripts():[];
} }
if(!function_exists('print_footer_scripts')){ function print_footer_scripts() { script_concat_settings();return apply_filters('print_footer_scripts',true)?wp_print_footer_scripts():[]; } }
if(!function_exists('_print_scripts')){ /** Bewusst leer: Das Drucken geschieht schon in wp_print_head_scripts/wp_print_footer_scripts (kein Verketten). */
function _print_scripts() {} }
if(!function_exists('_wp_footer_scripts')){ function _wp_footer_scripts() { print_late_styles();print_footer_scripts(); } }
if(!function_exists('print_admin_styles')){ function print_admin_styles() { script_concat_settings();return apply_filters('print_admin_styles',true)?wp_print_styles():[]; } }
if(!function_exists('print_late_styles')){ function print_late_styles() { script_concat_settings();return apply_filters('print_late_styles',true)?wp_print_styles():[]; } }
if(!function_exists('_print_styles')){ /** Bewusst leer (siehe _print_scripts). */
function _print_styles() {} }
if(!function_exists('wp_sanitize_script_attributes')){ function wp_sanitize_script_attributes($attributes) {
    $html5=!is_admin()&&!current_theme_supports('html5','script');$s='';
    foreach($attributes as $n=>$v){
        if(is_bool($v)){ if($v)$s.=$html5?sprintf(' %1$s="%2$s"',esc_attr($n),esc_attr($n)):' '.esc_attr($n); }
        else $s.=sprintf(' %1$s="%2$s"',esc_attr($n),esc_attr($v));
    }
    return $s;
} }
if(!function_exists('wp_get_script_tag')){ function wp_get_script_tag($attributes) {
    if(!isset($attributes['type'])&&!is_admin()&&!current_theme_supports('html5','script'))$attributes['type']='text/javascript';
    $attributes=apply_filters('wp_script_attributes',$attributes);
    return sprintf("<script%s></script>\n",wp_sanitize_script_attributes($attributes));
} }
if(!function_exists('wp_print_script_tag')){ function wp_print_script_tag($attributes) { echo wp_get_script_tag($attributes); } }
if(!function_exists('wp_remove_surrounding_empty_script_tags')){ function wp_remove_surrounding_empty_script_tags($contents) {
    if(preg_match('~^\s*<script>(.*)</script>\s*$~s',(string)$contents,$m))return trim($m[1]);
    _doing_it_wrong(__FUNCTION__,'Die Zeichenfolge muss mit einem Skript-Tag ohne Attribute beginnen und mit einem Skript-Tag enden (Leerraum erlaubt).','4.9.0');
    return $contents;
} }
if(!function_exists('wp_maybe_inline_styles')){ /** Bewusst ohne Wirkung: Stile werden nicht eingebettet (kein „path“-Datensatz je Stil). */
function wp_maybe_inline_styles() {} }
if(!function_exists('_wp_normalize_relative_css_links')){ function _wp_normalize_relative_css_links($css,$stylesheet_url) {
    if(!preg_match_all('#url\s*\(\s*[\'"]?\s*([^\'"\)]+)#',(string)$css,$m))return $css;
    foreach($m[1] as $i=>$u){ if(str_starts_with($u,'http')||str_starts_with($u,'//')||str_starts_with($u,'#')||str_starts_with($u,'data:'))continue;
        $abs=str_replace('/./','/',dirname($stylesheet_url).'/'.$u);$css=str_replace($m[0][$i],str_replace($u,$abs,$m[0][$i]),$css); }
    return $css;
} }

/* ───────── Block- und Globale Stile ───────── */
if(!function_exists('wp_common_block_scripts_and_styles')){ function wp_common_block_scripts_and_styles() {
    if(is_admin()&&!wp_should_load_block_editor_scripts_and_styles())return;
    wp_enqueue_style('wp-block-library');
    if(current_theme_supports('wp-block-styles'))wp_enqueue_style('wp-block-library-theme');
    do_action('enqueue_block_assets');
} }
if(!function_exists('wp_filter_out_block_nodes')){ function wp_filter_out_block_nodes($nodes) { return array_filter($nodes,static fn($n)=>!in_array('blocks',(array)($n['path']??[]),true)); } }
if(!function_exists('wp_should_load_block_editor_scripts_and_styles')){ function wp_should_load_block_editor_scripts_and_styles() {
    $s=$GLOBALS['current_screen']??null;$is=is_object($s)&&method_exists($s,'is_block_editor')&&$s->is_block_editor();
    return (bool)apply_filters('should_load_block_editor_scripts_and_styles',$is);
} }
if(!function_exists('wp_should_load_block_assets_on_demand')){ function wp_should_load_block_assets_on_demand() {
    if(is_admin()||is_feed()||wp_is_json_request())return false;
    return (bool)apply_filters('should_load_block_assets_on_demand',wp_should_load_separate_core_block_assets());
} }
if(!function_exists('wp_enqueue_registered_block_scripts_and_styles')){ function wp_enqueue_registered_block_scripts_and_styles() {
    if(wp_should_load_block_assets_on_demand())return;$editor=is_admin()&&wp_should_load_block_editor_scripts_and_styles();
    foreach(WP_Block_Type_Registry::get_instance()->get_all_registered() as $bt){
        foreach(array_filter((array)($bt->style_handles??$bt->style??[])) as $h)wp_enqueue_style($h);
        foreach(array_filter((array)($bt->script_handles??$bt->script??[])) as $h)wp_enqueue_script($h);
        if($editor){ foreach(array_filter((array)($bt->editor_style_handles??$bt->editor_style??[])) as $h)wp_enqueue_style($h);foreach(array_filter((array)($bt->editor_script_handles??$bt->editor_script??[])) as $h)wp_enqueue_script($h); }
    }
} }
if(!function_exists('enqueue_block_styles_assets')){ function enqueue_block_styles_assets() {
    foreach((array)($GLOBALS['rrw_wp_block_styles']??[]) as $block=>$styles)foreach($styles as $sp){
        if(!empty($sp['style_handle']))wp_enqueue_style($sp['style_handle']);
        if(!empty($sp['inline_style'])){ $h='wp-block-library';if(!wp_style_is($h,'registered')){ $h='rrw-block-style-inline';wp_register_style($h,false);wp_enqueue_style($h); }wp_add_inline_style($h,$sp['inline_style']); }
    }
} }
if(!function_exists('enqueue_editor_block_styles_assets')){ function enqueue_editor_block_styles_assets() {
    $l=['( function() {'];
    foreach((array)($GLOBALS['rrw_wp_block_styles']??[]) as $block=>$styles)foreach($styles as $sp){
        $s=['name'=>$sp['name'],'label'=>$sp['label']??$sp['name']];if(isset($sp['is_default']))$s['isDefault']=$sp['is_default'];
        $l[]=sprintf("\twp.blocks.registerBlockStyle( '%s', %s );",$block,wp_json_encode($s)); }
    $l[]='} )();';
    wp_register_script('wp-block-styles',false,['wp-blocks'],true,true);wp_add_inline_script('wp-block-styles',implode("\n",$l));wp_enqueue_script('wp-block-styles');
} }
if(!function_exists('wp_enqueue_editor_block_directory_assets')){ function wp_enqueue_editor_block_directory_assets() { wp_enqueue_script('wp-block-directory');wp_enqueue_style('wp-block-directory'); } }
if(!function_exists('wp_enqueue_editor_format_library_assets')){ function wp_enqueue_editor_format_library_assets() { wp_enqueue_script('wp-format-library');wp_enqueue_style('wp-format-library'); } }
if(!function_exists('wp_enqueue_global_styles')){ function wp_enqueue_global_styles() {
    $css=wp_get_global_stylesheet();if(empty($css))return;
    wp_register_style('global-styles',false);wp_add_inline_style('global-styles',$css);wp_enqueue_style('global-styles');
} }
if(!function_exists('wp_enqueue_global_styles_css_custom_properties')){ function wp_enqueue_global_styles_css_custom_properties() {
    wp_register_style('global-styles-css-custom-properties',false);wp_add_inline_style('global-styles-css-custom-properties',wp_get_global_stylesheet(['variables']));wp_enqueue_style('global-styles-css-custom-properties');
} }
if(!function_exists('wp_enqueue_block_support_styles')){ function wp_enqueue_block_support_styles($style,$priority=10) {
    add_action(wp_is_block_theme()?'wp_head':'wp_footer',static function() use($style){ echo "<style>$style</style>\n"; },$priority);
} }
if(!function_exists('wp_enqueue_stored_styles')){ function wp_enqueue_stored_styles($options=[]) {
    $css=implode('',array_values((array)($GLOBALS['rrw_wp_block_support_css']??[])));if($css==='')return;
    $pretty=isset($options['prettify'])?$options['prettify']===true:defined('SCRIPT_DEBUG')&&SCRIPT_DEBUG;
    wp_register_style('core-block-supports',false);wp_add_inline_style('core-block-supports',($pretty?"/**\n * Core styles: block-supports\n */\n":'').$css);wp_enqueue_style('core-block-supports');
} }
if(!function_exists('wp_enqueue_classic_theme_styles')){ function wp_enqueue_classic_theme_styles() {
    if(wp_theme_has_theme_json())return;
    wp_register_style('classic-theme-styles',includes_url('css/classic-themes.min.css'));wp_enqueue_style('classic-theme-styles');
} }

/* ───────── Skript-Module ───────── */
if(!class_exists('WP_Script_Modules')){
class WP_Script_Modules {
    private $registered=[];
    public function register($id,$src,$deps=[],$version=false) { if(isset($this->registered[$id]))return;$this->registered[$id]=['src'=>$src,'deps'=>array_values((array)$deps),'version'=>$version]; }
    public function enqueue($id,$src='',$deps=[],$version=false) { if($src!=='')$this->register($id,$src,$deps,$version);if(isset($this->registered[$id]))$GLOBALS['rrw_wp_script_modules'][$id]=true; }
    public function dequeue($id) { unset($GLOBALS['rrw_wp_script_modules'][$id]); }
    public function deregister($id) { unset($this->registered[$id],$GLOBALS['rrw_wp_script_modules'][$id]); }
    public function get_registered() { return $this->registered; }
    public function is_registered($id) { return isset($this->registered[$id]); }
    private function url($m) { $s=(string)$m['src'];return $m['version']!==false&&$m['version']!==null?add_query_arg('ver',$m['version'],$s):$s; }
    public function print_import_map() {
        $im=[];foreach($this->registered as $id=>$m)if(!empty($GLOBALS['rrw_wp_script_modules'][$id])||$this->is_dep_of_enqueued($id))$im[$id]=$this->url($m);
        if($im)echo '<script type="importmap" id="wp-importmap">'.wp_json_encode(['imports'=>$im],JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP)."</script>\n";
    }
    public function print_enqueued_script_modules() {
        foreach(array_keys(array_filter((array)($GLOBALS['rrw_wp_script_modules']??[]))) as $id)if(isset($this->registered[$id]))
            echo '<script type="module" src="'.esc_url($this->url($this->registered[$id])).'" id="'.esc_attr($id).'-js-module"></script>'."\n";
    }
    private function is_dep_of_enqueued($id) { foreach(array_keys(array_filter((array)($GLOBALS['rrw_wp_script_modules']??[]))) as $q)if(in_array($id,$this->registered[$q]['deps']??[],true))return true;return false; }
}
}
if(!function_exists('wp_script_modules')){ function wp_script_modules() { static $m=null;return $m??($m=new WP_Script_Modules()); } }
if(!function_exists('wp_deregister_script_module')){ function wp_deregister_script_module($id) { wp_script_modules()->deregister($id); } }
if(!function_exists('wp_default_script_modules')){ function wp_default_script_modules() {
    foreach(['@wordpress/interactivity'=>['interactivity/index',[]],'@wordpress/interactivity-router'=>['interactivity-router/index',['@wordpress/interactivity']],'@wordpress/a11y'=>['a11y/index',[]],'@wordpress/block-library/navigation/view'=>['block-library/navigation/view',['@wordpress/interactivity']]] as $id=>[$p,$deps])
        wp_script_modules()->register($id,includes_url("js/dist/script-modules/$p.min.js"),$deps,$GLOBALS['wp_version']);
} }

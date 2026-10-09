<?php
// Ergänzende WordPress-Funktionen (Version 7.1, Teil Medien/Skripte): Bild-Alternativtext und -Qualität, Browser-Erkennung, Daten-Attribut-Namen,
// Skriptmodul-Übersetzungen, View-Transitions, Speculative Loading. Nicht Abbildbares (Cross-Origin-Isolation, clientseitige Medienverarbeitung,
// Befehlspalette, Hoisting später Styles) ist als dokumentierter Standardwert/No-op umgesetzt und verändert die bestehende Ausgabe nicht.

if(!function_exists('wp_get_image_alttext')){ function wp_get_image_alttext($file) {   // Alt-Text aus XMP (Iptc4xmpCore:AltTextAccessibility / ExtDescrAccessibility), sonst ''
    if(!is_string($file)||!is_readable($file))return '';
    $fh=@fopen($file,'rb');if(!$fh)return '';$head=(string)fread($fh,262144);fclose($fh);
    if(($a=strpos($head,'<x:xmpmeta'))===false)return '';$xmp=substr($head,$a,((int)strpos($head,'</x:xmpmeta>',$a)?:strlen($head))-$a);
    foreach(['AltTextAccessibility','ExtDescrAccessibility'] as $tag){
        if(preg_match('#<Iptc4xmpCore:'.$tag.'\b[^>]*>(.*?)</Iptc4xmpCore:'.$tag.'>#s',$xmp,$m)){
            $t=preg_match('#<rdf:li\b[^>]*>(.*?)</rdf:li>#s',$m[1],$li)?$li[1]:$m[1];
            $t=trim(wp_strip_all_tags(html_entity_decode($t,ENT_QUOTES|ENT_XML1,'UTF-8')));if($t!=='')return wp_scrub_utf8($t);
        }
    }
    return '';
} }
if(!function_exists('wp_get_image_encode_quality')){ function wp_get_image_encode_quality($mime_type, $size=null) {   // Standard je Format, filterbar wie wp_editor_set_quality
    $mime=(string)$mime_type;$q=match($mime){'image/webp','image/avif'=>86,'image/png'=>82,default=>82};
    $q=(int)apply_filters('wp_editor_set_quality',$q,$mime,$size);
    if($mime==='image/jpeg')$q=(int)apply_filters('jpeg_quality',$q,'image_resize');
    return max(1,min(100,$q));
} }
if(!function_exists('wp_get_chromium_major_version')){ function wp_get_chromium_major_version() {   // aus dem User-Agent (Chrome/Chromium/Edge/Opera); sonst null
    $ua=(string)($_SERVER['HTTP_USER_AGENT']??'');
    return preg_match('#\b(?:Chrome|Chromium|CriOS)/(\d+)#',$ua,$m)?(int)$m[1]:null;
} }
if(!function_exists('wp_enqueue_img_auto_sizes_contain_css_fix')){ function wp_enqueue_img_auto_sizes_contain_css_fix() {   // Inline-Style für sizes="auto"-Bilder; nur bei Aufruf
    $h='wp-img-auto-sizes-contain-fix';
    if(!isset($GLOBALS['elvado_wp_styles']['reg'][$h]))wp_register_style($h,false);
    wp_add_inline_style($h,'img:is([sizes="auto" i], [sizes^="auto," i]) { contain-intrinsic-size: 3000px 1500px; }');
    wp_enqueue_style($h);
} }
if(!function_exists('wp_is_client_side_media_processing_enabled')){ function wp_is_client_side_media_processing_enabled() {   // Medienverarbeitung im Browser: nicht vorgesehen (Standard false)
    return (bool)apply_filters('wp_client_side_media_processing_enabled',false);
} }
if(!function_exists('wp_set_client_side_media_processing_flag')){ function wp_set_client_side_media_processing_flag() { return null; } }   // No-op: ohne clientseitige Medienverarbeitung gibt es kein Flag
if(!function_exists('wp_set_up_cross_origin_isolation')){ function wp_set_up_cross_origin_isolation() { return false; } }   // No-op: keine Cross-Origin-Isolation (Standard aus)
if(!function_exists('wp_start_cross_origin_isolation_output_buffer')){ function wp_start_cross_origin_isolation_output_buffer() { return false; } }   // No-op: kein Ausgabepuffer
if(!function_exists('wp_add_crossorigin_attributes')){ function wp_add_crossorigin_attributes($html) { return (string)$html; } }   // ohne Isolation bleibt das HTML unverändert

/* ───────── Befehlspalette, Styles, Ansichtsübergänge ───────── */
if(!function_exists('wp_enqueue_command_palette_assets')){ function wp_enqueue_command_palette_assets() { return null; } }   // No-op: reine Editor-/Admin-Oberfläche
if(!function_exists('wp_admin_bar_command_palette_menu')){ function wp_admin_bar_command_palette_menu($wp_admin_bar=null) { return null; } }   // No-op: kein Eintrag in der Admin-Leiste
if(!function_exists('wp_load_classic_theme_block_styles_on_demand')){ function wp_load_classic_theme_block_styles_on_demand() { return false; } }   // No-op: Block-Styles laden wie bisher
if(!function_exists('wp_hoist_late_printed_styles')){ function wp_hoist_late_printed_styles() { return false; } }   // No-op: keine Umsortierung spät ausgegebener Styles
if(!function_exists('wp_get_view_transitions_admin_css')){ function wp_get_view_transitions_admin_css() { return '@view-transition { navigation: auto; }'; } }
if(!function_exists('wp_enqueue_view_transitions_admin_css')){ function wp_enqueue_view_transitions_admin_css() {
    $h='wp-view-transitions-admin';
    if(!isset($GLOBALS['elvado_wp_styles']['reg'][$h]))wp_register_style($h,false);
    wp_add_inline_style($h,wp_get_view_transitions_admin_css());wp_enqueue_style($h);
} }

/* ───────── Daten-Attribute ───────── */
if(!function_exists('wp_js_dataset_name')){ function wp_js_dataset_name($html_name) {   // "data-foo-bar" -> "fooBar"; ohne data--Präfix null
    $n=strtolower((string)$html_name);if(!str_starts_with($n,'data-')||strlen($n)<=5)return null;
    return lcfirst(preg_replace_callback('/-([a-z])/',static fn($m)=>strtoupper($m[1]),substr($n,5)));
} }
if(!function_exists('wp_html_custom_data_attribute_name')){ function wp_html_custom_data_attribute_name($js_name) {   // "fooBar" -> "data-foo-bar"; ungültig (z. B. '-' + Kleinbuchstabe) null
    $n=(string)$js_name;if($n===''||preg_match('/-[a-z]|[^A-Za-z0-9_.:\x80-\xFF-]/',$n))return null;
    return 'data-'.preg_replace_callback('/[A-Z]/',static fn($m)=>'-'.strtolower($m[0]),$n);
} }

/* ───────── Skript-Argumente, Skriptmodul-Übersetzungen ───────── */
if(!function_exists('_wp_scripts_add_args_data')){ function _wp_scripts_add_args_data($wp_scripts, $handle, $args=[]) {   // strategy / in_footer / fetchpriority eines Skripts vermerken
    $h=(string)$handle;$g=&$GLOBALS['elvado_wp_scripts'];if(!isset($g['reg'][$h])||!is_array($args))return false;
    foreach(['strategy','fetchpriority'] as $k)if(isset($args[$k])&&is_string($args[$k]))$g['reg'][$h]['data'][$k]=$args[$k];
    if(!empty($args['in_footer']))$g['reg'][$h]['extra']=true;
    return true;
} }
if(!function_exists('wp_set_script_module_translations')){ function wp_set_script_module_translations($id, $domain='default', $path='') {   // vermerkt Textdomain/Pfad; nur für Module, die im Browser wp-i18n nutzen
    $id=(string)$id;if($id==='')return false;
    $GLOBALS['elvado_wp_script_module_l10n'][$id]=['domain'=>(string)$domain,'path'=>(string)$path];return true;
} }
if(!function_exists('wp_enqueue_block_editor_script_modules')){ function wp_enqueue_block_editor_script_modules() { return null; } }   // No-op: der Block-Editor lädt Module selbst
if(!function_exists('_load_script_textdomain_from_src')){ function _load_script_textdomain_from_src($src, $domain='default', $path='', $handle='') {   // JSON-Übersetzung zu einer Quell-URL (Relativpfad, ohne .min) aus dem Sprachordner
    $src=(string)$src;$locale=determine_locale();if($src===''||$locale==='en_US')return false;
    $dir=untrailingslashit($path?:WP_LANG_DIR);$base=trailingslashit(site_url());
    $rel=str_starts_with($src,$base)?substr($src,strlen($base)):ltrim((string)parse_url($src,PHP_URL_PATH),'/');
    $rel=preg_replace('/\.min\.js$/','.js',$rel);
    $files=[$dir.'/'.$domain.'-'.$locale.'-'.md5($rel).'.json'];if($handle!=='')array_unshift($files,$dir.'/'.$domain.'-'.$locale.'-'.$handle.'.json');
    foreach($files as $f){ $r=function_exists('load_script_translations')?load_script_translations($f,$handle,$domain):false;if($r)return $r; }
    return false;
} }
if(!function_exists('load_script_module_textdomain')){ function load_script_module_textdomain($id, $domain='default', $path='') {   // Übersetzungen (JSON) eines Skriptmoduls oder false
    $id=(string)$id;$src=(string)($GLOBALS['elvado_wp_scripts']['reg'][$id]['src']??'');
    if($src==='')return false;
    return _load_script_textdomain_from_src($src,(string)$domain,(string)$path,$id);
} }

/* ───────── Speculative Loading (nicht aktiv) ───────── */
if(!function_exists('wp_get_speculation_rules_default_configuration')){ function wp_get_speculation_rules_default_configuration() {   // Standard laut Dokumentation: mode auto, eagerness conservative
    return ['mode'=>'auto','eagerness'=>'conservative'];
} }
if(!function_exists('wp_get_speculative_loading_override')){ function wp_get_speculative_loading_override() {   // kein Override; Filter erlaubt eines. Die Schicht gibt selbst keine Speculation Rules aus.
    return apply_filters('wp_speculative_loading_override',null);
} }

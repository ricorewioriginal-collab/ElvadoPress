<?php
// Ergänzende WordPress-Funktionen (Version 7.1, Teil Sonstiges): gesalzene Cache-Werte, Tooltip/Toggletip-HTML, JSON-Schema für Clients,
// Template-Enhancement (aus), Block-Bindings, Sitemap-Abfrage, Datenbank-Upgrade 7.0. Nur Definitionen beim Laden.

/* ───────── Cache mit Salz (über den vorhandenen Objekt-Cache) ───────── */
if(!function_exists('_elvado_cache_salt')){ function _elvado_cache_salt($salt) { return is_array($salt)?implode('|',array_map('strval',$salt)):(string)$salt; } }
if(!function_exists('wp_cache_set_salted')){ function wp_cache_set_salted($key, $data, $group='', $salt='', $expire=0) { return wp_cache_set($key,['data'=>$data,'salt'=>_elvado_cache_salt($salt)],$group,$expire); } }
if(!function_exists('wp_cache_get_salted')){ function wp_cache_get_salted($key, $group='', $salt='', $force=false, &$found=null) {   // anderes Salz -> false (Treffer verworfen)
    $v=wp_cache_get($key,$group,$force,$found);
    if(!is_array($v)||!array_key_exists('data',$v)||($v['salt']??null)!==_elvado_cache_salt($salt)){ $found=false;return false; }
    return $v['data'];
} }
if(!function_exists('wp_cache_set_multiple_salted')){ function wp_cache_set_multiple_salted($data, $group='', $salt='', $expire=0) { $o=[];foreach((array)$data as $k=>$v)$o[$k]=wp_cache_set_salted($k,$v,$group,$salt,$expire);return $o; } }
if(!function_exists('wp_cache_get_multiple_salted')){ function wp_cache_get_multiple_salted($keys, $group='', $salt='', $force=false) { $o=[];foreach((array)$keys as $k)$o[$k]=wp_cache_get_salted($k,$group,$salt,$force);return $o; } }
if(!function_exists('wp_cache_switch_to_blog_fallback')){ function wp_cache_switch_to_blog_fallback($blog_id) { return null; } }   // Einzelseite: der Cache gilt für die eine Website, nichts umzuschalten

/* ───────── Tooltip / Toggletip ───────── */
// Tooltip: Auslöser mit aria-describedby; Toggletip: Schaltfläche mit aria-expanded und Live-Bereich (role="status").
if(!function_exists('wp_get_tooltip_helper')){ function wp_get_tooltip_helper($type, $content, $args=[]) {
    $a=wp_parse_args($args,['label'=>'','id'=>'','class'=>'','position'=>'top']);
    $id=$a['id']!==''?sanitize_html_class((string)$a['id']):wp_unique_id('wp-'.$type.'-');
    $cls='wp-'.$type.($a['class']!==''?' '.implode(' ',array_map('sanitize_html_class',explode(' ',(string)$a['class']))):'');
    $pos=in_array($a['position'],['top','bottom','left','right'],true)?$a['position']:'top';
    $label=(string)$a['label']!==''?esc_html($a['label']):'<span aria-hidden="true">?</span>';
    $body=wp_kses_post((string)$content);
    if($type==='toggletip')
        return '<span class="'.esc_attr($cls).'" data-position="'.$pos.'"><button type="button" class="wp-toggletip__trigger" aria-expanded="false" aria-controls="'.esc_attr($id).'">'.$label.'</button><span id="'.esc_attr($id).'" class="wp-toggletip__content" role="status" hidden>'.$body.'</span></span>';
    return '<span class="'.esc_attr($cls).'" data-position="'.$pos.'"><button type="button" class="wp-tooltip__trigger" aria-describedby="'.esc_attr($id).'">'.$label.'</button><span id="'.esc_attr($id).'" class="wp-tooltip__content" role="tooltip">'.$body.'</span></span>';
} }
if(!function_exists('wp_get_tooltip')){ function wp_get_tooltip($content, $args=[]) { return wp_get_tooltip_helper('tooltip',$content,$args); } }
if(!function_exists('wp_get_toggletip')){ function wp_get_toggletip($content, $args=[]) { return wp_get_tooltip_helper('toggletip',$content,$args); } }

/* ───────── JSON-Schema für Clients ───────── */
if(!function_exists('wp_get_json_schema_allowed_keywords')){ function wp_get_json_schema_allowed_keywords() {
    return (array)apply_filters('wp_json_schema_allowed_keywords',function_exists('rest_get_allowed_schema_keywords')?rest_get_allowed_schema_keywords():['title','description','default','type','format','enum','items','properties','additionalProperties','minimum','maximum','minLength','maxLength','pattern','minItems','maxItems','uniqueItems','anyOf','oneOf','allOf','required']);
} }
if(!function_exists('_wp_prepare_json_schema_for_client_with_allowed_keywords')){ function _wp_prepare_json_schema_for_client_with_allowed_keywords($schema, $allowed) {   // entfernt unbekannte Schlüssel (callbacks, arg_options …) rekursiv
    if(!is_array($schema))return $schema;
    $out=[];
    foreach($schema as $k=>$v){
        if(!in_array($k,$allowed,true))continue;
        if(in_array($k,['properties','patternProperties'],true)&&is_array($v)){ $m=[];foreach($v as $pk=>$ps)$m[$pk]=_wp_prepare_json_schema_for_client_with_allowed_keywords($ps,$allowed);$out[$k]=$m; }
        elseif(in_array($k,['items','additionalProperties','not'],true))$out[$k]=_wp_prepare_json_schema_for_client_with_allowed_keywords($v,$allowed);
        elseif(in_array($k,['anyOf','oneOf','allOf'],true)&&is_array($v))$out[$k]=array_map(static fn($s)=>_wp_prepare_json_schema_for_client_with_allowed_keywords($s,$allowed),$v);
        else $out[$k]=$v;
    }
    return $out;
} }
if(!function_exists('wp_prepare_json_schema_for_client')){ function wp_prepare_json_schema_for_client($schema) { return _wp_prepare_json_schema_for_client_with_allowed_keywords($schema,wp_get_json_schema_allowed_keywords()); } }

/* ───────── Template-Enhancement (nicht aktiv) ───────── */
if(!function_exists('_wp_is_template_path_allowed')){ function _wp_is_template_path_allowed($path) {   // nur Dateien unterhalb der Theme-Ordner (Block-Template-Verzeichnisse)
    $real=@realpath((string)$path);if(!$real)return false;
    foreach(array_unique([get_stylesheet_directory(),get_template_directory()]) as $d){ $r=@realpath($d);if($r&&str_starts_with($real,rtrim($r,'/\\').DIRECTORY_SEPARATOR))return true; }
    return false;
} }
if(!function_exists('wp_should_output_buffer_template_for_enhancement')){ function wp_should_output_buffer_template_for_enhancement() { return (bool)apply_filters('wp_should_output_buffer_template_for_enhancement',false); } }   // Standard aus: Ausgabe bleibt unverändert
if(!function_exists('wp_start_template_enhancement_output_buffer')){ function wp_start_template_enhancement_output_buffer() { return false; } }   // No-op: kein Ausgabepuffer
if(!function_exists('wp_finalize_template_enhancement_output_buffer')){ function wp_finalize_template_enhancement_output_buffer($output) { return (string)$output; } }

/* ───────── Blöcke ───────── */
if(!function_exists('get_block_bindings_supported_attributes')){ function get_block_bindings_supported_attributes($block_type) {   // bindbare Attribute je Block (filterbar)
    $map=['core/paragraph'=>['content'],'core/heading'=>['content'],'core/image'=>['id','url','title','alt'],'core/button'=>['url','text','linkTarget','rel']];
    $a=$map[(string)$block_type]??[];
    return (array)apply_filters('block_bindings_supported_attributes_'.$block_type,$a,$block_type);
} }
if(!function_exists('_block_template_add_skip_link')){ function _block_template_add_skip_link() { return null; } }   // No-op: Die Schicht gibt bereits ihr eigenes Gerüst aus
if(!function_exists('_wp_apply_block_content_filters')){ function _wp_apply_block_content_filters($content) {   // Blöcke auswerten, Inhalts-Tags filtern, Shortcodes ausführen
    $c=function_exists('do_blocks')?do_blocks((string)$content):(string)$content;
    if(function_exists('wp_filter_content_tags'))$c=wp_filter_content_tags($c);
    return function_exists('do_shortcode')?do_shortcode($c):$c;
} }
if(!function_exists('_wp_enqueue_auto_register_blocks')){ function _wp_enqueue_auto_register_blocks() { return null; } }   // No-op: automatische Block-Registrierung per Ordner gibt es nicht

/* ───────── Abfrage, Upgrade ───────── */
if(!function_exists('is_sitemap')){ function is_sitemap() { global $wp_query;return (bool)($wp_query&&(!empty($wp_query->is_sitemap)||(function_exists('get_query_var')&&(string)get_query_var('sitemap')!==''))); } }
if(!function_exists('upgrade_700')){ function upgrade_700() { return null; } }   // Datenbank-Upgrade 7.0: keine Schemaänderung nötig (die Schicht pflegt ihre Tabellen selbst)

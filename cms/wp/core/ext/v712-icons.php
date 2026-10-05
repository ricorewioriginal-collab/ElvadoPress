<?php
// Ergänzende WordPress-Funktionen (Version 7.1, Teil Icons und Ansichts-Konfiguration): Icon-Sammlungen im Arbeitsspeicher, Standard-Konfiguration für Listen/Formulare.
// Standard-Icons werden erst beim ersten Zugriff eingetragen (Action wp_icons_init); beim Laden passiert nichts.

/* ───────── Icons ───────── */
if(!function_exists('_rrw_icons_state')){ function _rrw_icons_state() {
    $s=&$GLOBALS['rrw_wp_icons'];
    if(!isset($s))$s=['collections'=>[],'icons'=>[],'init'=>false];
    if(!$s['init']){ $s['init']=true;_wp_register_default_icon_collections();_wp_register_default_icons();do_action('wp_icons_init'); }
    return $s;
} }
if(!function_exists('wp_register_icon_collection')){ function wp_register_icon_collection($slug, $args=[]) {   // args: label
    $s=&$GLOBALS['rrw_wp_icons'];if(!isset($s))$s=['collections'=>[],'icons'=>[],'init'=>false];
    $slug=(string)$slug;if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug)||isset($s['collections'][$slug]))return false;
    $s['collections'][$slug]=['label'=>(string)(((array)$args)['label']??$slug)];return true;
} }
if(!function_exists('wp_unregister_icon_collection')){ function wp_unregister_icon_collection($slug) {   // entfernt auch alle Icons der Sammlung
    $s=&$GLOBALS['rrw_wp_icons'];if(!isset($s,$s['collections'][$slug]))return false;
    unset($s['collections'][$slug]);foreach(array_keys($s['icons']) as $n)if(str_starts_with($n,$slug.'/'))unset($s['icons'][$n]);return true;
} }
if(!function_exists('wp_register_icon')){ function wp_register_icon($name, $args=[]) {   // name „sammlung/icon“; args: label, content (SVG) oder filePath
    $s=&$GLOBALS['rrw_wp_icons'];if(!isset($s))$s=['collections'=>[],'icons'=>[],'init'=>false];
    $name=(string)$name;$args=(array)$args;
    if(!preg_match('/^([a-z0-9-]+)\/[a-z0-9-]+$/',$name,$m)||isset($s['icons'][$name])||!isset($s['collections'][$m[1]])&&$m[1]!=='core')return false;
    $svg=(string)($args['content']??'');
    if($svg===''&&!empty($args['filePath'])&&is_readable($args['filePath']))$svg=(string)file_get_contents($args['filePath']);
    if(stripos($svg,'<svg')===false)return false;
    $s['icons'][$name]=['name'=>$name,'label'=>(string)($args['label']??$name),'content'=>$svg];return true;
} }
if(!function_exists('wp_unregister_icon')){ function wp_unregister_icon($name) { $s=&$GLOBALS['rrw_wp_icons'];if(!isset($s,$s['icons'][$name]))return false;unset($s['icons'][$name]);return true; } }
if(!function_exists('_wp_register_default_icon_collections')){ function _wp_register_default_icon_collections() { wp_register_icon_collection('core',['label'=>'Kern']); } }
if(!function_exists('_wp_register_default_icons')){ function _wp_register_default_icons() {   // kleiner eigener Grundsatz einfacher Symbole (24x24)
    $p=['check'=>['Häkchen','M5 12.5l4.5 4.5L19 7.5'],'close'=>['Schließen','M6 6l12 12M18 6L6 18'],'plus'=>['Plus','M12 5v14M5 12h14'],'minus'=>['Minus','M5 12h14'],'arrow-right'=>['Pfeil rechts','M5 12h14M13 6l6 6-6 6'],'arrow-left'=>['Pfeil links','M19 12H5M11 6l-6 6 6 6']];
    foreach($p as $k=>$d)wp_register_icon('core/'.$k,['label'=>$d[0],'content'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="'.$d[1].'"/></svg>']);
} }
if(!function_exists('wp_get_icon')){ function wp_get_icon($name) { $s=_rrw_icons_state();return $s['icons'][(string)$name]??null; } }

/* ───────── Ansichts-Konfiguration (Listen/Formulare im Editor) ───────── */
if(!function_exists('wp_get_entity_view_config_hook_name')){ function wp_get_entity_view_config_hook_name($kind, $name) { return 'wp_entity_view_config_'.sanitize_key((string)$kind).'_'.sanitize_key((string)$name); } }
if(!function_exists('_wp_get_default_posttype_form')){ function _wp_get_default_posttype_form($post_type='post') {
    $f=['featured_media','title','excerpt','status','date','slug','author'];
    if(is_post_type_hierarchical_safe($post_type))$f[]='parent';
    return ['layout'=>['type'=>'regular'],'fields'=>$f];
} }
if(!function_exists('is_post_type_hierarchical_safe')){ function is_post_type_hierarchical_safe($pt) { return $pt==='page'||($pt!==''&&function_exists('is_post_type_hierarchical')&&is_post_type_hierarchical($pt)); } }
if(!function_exists('_wp_get_entity_view_config_posttype_page')){ function _wp_get_entity_view_config_posttype_page() {
    return ['default_view'=>['type'=>'table','perPage'=>20,'sort'=>['field'=>'date','direction'=>'desc'],'fields'=>['title','author','status','date']],'default_layouts'=>['table'=>['layout'=>['primaryField'=>'title']]],'view_list'=>[['title'=>'Alle Seiten','slug'=>'all'],['title'=>'Veröffentlicht','slug'=>'publish'],['title'=>'Entwürfe','slug'=>'draft']],'form'=>_wp_get_default_posttype_form('page')];
} }
if(!function_exists('_wp_get_entity_view_config_posttype_wp_block')){ function _wp_get_entity_view_config_posttype_wp_block() {
    return ['default_view'=>['type'=>'grid','perPage'=>20,'sort'=>['field'=>'title','direction'=>'asc'],'fields'=>['title','date']],'default_layouts'=>['grid'=>['layout'=>['primaryField'=>'title']]],'view_list'=>[['title'=>'Alle Muster','slug'=>'all']],'form'=>['layout'=>['type'=>'regular'],'fields'=>['title','status']]];
} }
if(!function_exists('_wp_get_entity_view_config_posttype_wp_template_part')){ function _wp_get_entity_view_config_posttype_wp_template_part() {
    return ['default_view'=>['type'=>'grid','perPage'=>20,'sort'=>['field'=>'title','direction'=>'asc'],'fields'=>['title','area']],'default_layouts'=>['grid'=>['layout'=>['primaryField'=>'title']]],'view_list'=>[['title'=>'Alle Vorlagenteile','slug'=>'all']],'form'=>['layout'=>['type'=>'regular'],'fields'=>['title','area']]];
} }
if(!function_exists('_wp_get_entity_view_config_posttype_wp_template')){ function _wp_get_entity_view_config_posttype_wp_template() {
    return ['default_view'=>['type'=>'grid','perPage'=>20,'sort'=>['field'=>'title','direction'=>'asc'],'fields'=>['title','author']],'default_layouts'=>['grid'=>['layout'=>['primaryField'=>'title']]],'view_list'=>[['title'=>'Alle Vorlagen','slug'=>'all']],'form'=>['layout'=>['type'=>'regular'],'fields'=>['title','description']]];
} }
if(!function_exists('wp_get_entity_view_config')){ function wp_get_entity_view_config($kind, $name) {   // Standard je Beitragstyp; Filter pro Entität
    $kind=(string)$kind;$name=(string)$name;
    $fn='_wp_get_entity_view_config_posttype_'.preg_replace('/[^a-z0-9_]/','_',strtolower($name));
    if($kind==='postType'&&function_exists($fn))$cfg=$fn();
    else $cfg=['default_view'=>['type'=>'table','perPage'=>20,'sort'=>['field'=>'date','direction'=>'desc'],'fields'=>['title','author','date']],'default_layouts'=>[],'view_list'=>[],'form'=>$kind==='postType'?_wp_get_default_posttype_form($name):['layout'=>['type'=>'regular'],'fields'=>[]]];
    $r=apply_filters(wp_get_entity_view_config_hook_name($kind,$name),$cfg,$kind,$name);
    return is_array($r)?$r:$cfg;
} }

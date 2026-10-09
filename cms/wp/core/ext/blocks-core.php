<?php
// Ergänzende Block-Funktionen: Asset-Handles, Metadaten-Sammlungen, Block-Hooks (hooked blocks), Serialisierung, KSES für Blöcke,
// Auszüge, Fußnoten, Pagination-Pfeile. Rendern/Parsen selbst liegt in core/blocks.php.

/* ───────── Metadaten-Sammlungen (Manifest mit Metadaten mehrerer Blöcke) ───────── */
if(!class_exists('WP_Block_Metadata_Registry')){
class WP_Block_Metadata_Registry {
    private static $collections=[];private static $loaded=[];
    public static function register_collection($path,$manifest) {
        $p=rtrim(wp_normalize_path((string)$path),'/');
        if($p===''||!is_file((string)$manifest))return false;
        self::$collections[$p]=(string)$manifest;unset(self::$loaded[$p]);return true;
    }
    private static function data(string $p): array { if(!isset(self::$loaded[$p])){ $d=include self::$collections[$p];self::$loaded[$p]=is_array($d)?$d:[]; } return self::$loaded[$p]; }
    private static function collection_for(string $file): ?string { $f=wp_normalize_path($file);foreach(array_keys(self::$collections) as $p)if(str_starts_with($f,$p.'/'))return $p;return null; }
    /** Pfade der block.json aller Blöcke einer Sammlung (aus dem Manifest). */
    public static function get_collection_block_metadata_files($path) {
        $p=rtrim(wp_normalize_path((string)$path),'/');if(!isset(self::$collections[$p]))return [];
        return array_map(fn($n)=>$p.'/'.$n.'/block.json',array_keys(self::data($p)));
    }
    public static function get_metadata($file_or_folder) {
        $f=wp_normalize_path((string)$file_or_folder);$p=self::collection_for($f);if($p===null)return null;
        $dir=basename($f)==='block.json'?dirname($f):rtrim($f,'/');$name=basename($dir);
        $d=self::data($p);return $d[$name]??null;
    }
    public static function has_metadata($file_or_folder) { return self::get_metadata($file_or_folder)!==null; }
}
}
if(!function_exists('wp_register_block_metadata_collection')){
function wp_register_block_metadata_collection($path,$manifest) { WP_Block_Metadata_Registry::register_collection($path,$manifest); }
}
/** WP_Block_Type-Argumente aus Block-Metadaten (wie beim Lesen einer block.json). */
if(!function_exists('elvado_wp_block_metadata_args')){
function elvado_wp_block_metadata_args(array $m, string $dir): array {
    $a=['title'=>$m['title']??'','category'=>$m['category']??null,'parent'=>$m['parent']??null,'icon'=>$m['icon']??null,'description'=>$m['description']??'','keywords'=>$m['keywords']??[],'textdomain'=>$m['textdomain']??null,
        'attributes'=>$m['attributes']??null,'supports'=>$m['supports']??null,'uses_context'=>$m['usesContext']??[],'provides_context'=>$m['providesContext']??null,'api_version'=>$m['apiVersion']??1,'path'=>$dir];
    if(!empty($m['render'])&&str_starts_with((string)$m['render'],'file:')){
        $rf=$dir.'/'.ltrim(substr((string)$m['render'],5),'./');
        if(is_file($rf))$a['render_callback']=function($attributes,$content,$block=null) use($rf){ ob_start();(function() use($attributes,$content,$block,$rf){ include $rf; })();return ob_get_clean(); };
    }
    return $a;
}}
if(!function_exists('wp_register_block_types_from_metadata_collection')){
function wp_register_block_types_from_metadata_collection($path,$manifest='') {
    if($manifest!=='')wp_register_block_metadata_collection($path,$manifest);
    foreach(WP_Block_Metadata_Registry::get_collection_block_metadata_files($path) as $f){
        if(is_file($f)){ register_block_type_from_metadata($f);continue; }
        $m=WP_Block_Metadata_Registry::get_metadata($f);   // nur im Manifest vorhanden
        if(is_array($m)&&!empty($m['name'])&&!WP_Block_Type_Registry::get_instance()->is_registered($m['name']))WP_Block_Type_Registry::get_instance()->register($m['name'],elvado_wp_block_metadata_args($m,dirname($f)));
    }
}}
if(!function_exists('get_block_metadata_i18n_schema')){
function get_block_metadata_i18n_schema() {
    return json_decode('{"title":"block title","description":"block description","keywords":["block keyword"],"styles":[{"label":"block style label"}],"variations":[{"title":"block variation title","description":"block variation description","keywords":["block variation keyword"]}]}');
}}

/* ───────── Asset-Handles und -Adressen ───────── */
if(!function_exists('generate_block_asset_handle')){
function generate_block_asset_handle($block_name,$field_name,$index=0) {
    if(str_starts_with($block_name,'core/')){
        $h=str_replace('core/','wp-block-',$block_name);
        if(str_starts_with($field_name,'editor'))$h.='-editor';
        if(str_starts_with($field_name,'view'))$h.='-view';
        if($index>0)$h.='-'.($index+1);
        return $h;
    }
    $map=['editorScript'=>'editor-script','editorStyle'=>'editor-style','script'=>'script','style'=>'style','viewScript'=>'view-script','viewScriptModule'=>'view-script-module','viewStyle'=>'view-style'];
    $h=str_replace('/','-',$block_name).'-'.($map[$field_name]??strtolower(preg_replace('/([a-z])([A-Z])/','$1-$2',$field_name)));
    if($index>0)$h.='-'.($index+1);
    return $h;
}}
if(!function_exists('get_block_asset_url')){
function get_block_asset_url($path) {
    if(empty($path))return false;
    $p=wp_normalize_path((string)$path);$content=rtrim(wp_normalize_path(WP_CONTENT_DIR),'/').'/';$abs=rtrim(wp_normalize_path(ABSPATH),'/').'/';
    if(str_starts_with($p,$content))return content_url(substr($p,strlen($content)));
    if(str_starts_with($p,$abs))return site_url(substr($p,strlen($abs)));
    return plugins_url(basename($p),$p);
}}
/** „file:./build/index.js“ → „./build/index.js“; Handles bleiben unverändert (remove_block_asset_path_prefix des Kerns gibt alles unverändert zurück). */
if(!function_exists('elvado_wp_block_asset_path')){
function elvado_wp_block_asset_path($v): string { $v=(string)$v;return str_starts_with($v,'file:')?substr($v,5):$v; }
}
/** Gemeinsame Auflösung für Skripte, Module und Stile: [Handle-Name, Datei, Adresse, Asset-Daten, Version] oder null. */
if(!function_exists('elvado_wp_block_asset_resolve')){
function elvado_wp_block_asset_resolve(array $metadata, string $field, int $index, string $ext): ?array {
    if(empty($metadata[$field])||empty($metadata['file']))return null;
    $v=$metadata[$field];if(is_array($v)){ if(empty($v[$index]))return null;$v=$v[$index]; }
    if(!str_starts_with((string)$v,'file:'))return null;
    $rel=ltrim(elvado_wp_block_asset_path($v),'./');$dir=dirname((string)$metadata['file']);$file=$dir.'/'.$rel;$real=realpath($file);$file=$real!==false?$real:$file;
    $assetFile=$dir.'/'.preg_replace('/\.'.$ext.'$/','.asset.php',$rel);$asset=[];
    if(is_file($assetFile)){ $a=(function($f){ return include $f; })($assetFile);if(is_array($a))$asset=$a; }
    $ver=$asset['version']??($metadata['version']??(is_file($file)?filemtime($file):false));
    return ['handle'=>generate_block_asset_handle((string)$metadata['name'],$field,$index),'file'=>$file,'uri'=>get_block_asset_url($file),'asset'=>$asset,'version'=>$ver];
}}
if(!function_exists('register_block_script_module_id')){
function register_block_script_module_id($metadata,$field_name,$index=0) {
    if(empty($metadata[$field_name]))return false;
    $v=$metadata[$field_name];if(is_array($v)){ if(empty($v[$index]))return false;$v=$v[$index]; }
    if(!str_starts_with((string)$v,'file:'))return $v;   // bereits eine Modul-ID
    $r=elvado_wp_block_asset_resolve($metadata,$field_name,(int)$index,'js');if(!$r)return false;
    wp_register_script_module($r['handle'],$r['uri'],(array)($r['asset']['dependencies']??[]),$r['version']);
    return $r['handle'];
}}
if(!function_exists('register_block_script_handle')){
function register_block_script_handle($metadata,$field_name,$index=0) {
    if(empty($metadata[$field_name]))return false;
    $v=$metadata[$field_name];if(is_array($v)){ if(empty($v[$index]))return false;$v=$v[$index]; }
    if(!str_starts_with((string)$v,'file:'))return $v;   // bereits ein Skript-Handle
    $r=elvado_wp_block_asset_resolve($metadata,$field_name,(int)$index,'js');if(!$r)return false;
    $deps=(array)($r['asset']['dependencies']??[]);
    $args=[];if($field_name==='viewScript'&&str_starts_with((string)$metadata['name'],'core/'))$args['strategy']='defer';
    if(!wp_register_script($r['handle'],$r['uri'],$deps,$r['version'],$args))return false;
    if(!empty($metadata['textdomain'])&&in_array('wp-i18n',$deps,true))wp_set_script_translations($r['handle'],$metadata['textdomain']);
    return $r['handle'];
}}
if(!function_exists('register_block_style_handle')){
function register_block_style_handle($metadata,$field_name,$index=0) {
    if(empty($metadata[$field_name]))return false;
    $v=$metadata[$field_name];if(is_array($v)){ if(empty($v[$index]))return false;$v=$v[$index]; }
    if(!str_starts_with((string)$v,'file:'))return $v;   // bereits ein Stil-Handle
    $name=generate_block_asset_handle((string)$metadata['name'],$field_name,(int)$index);
    if(wp_style_is($name,'registered'))return $name;
    $r=elvado_wp_block_asset_resolve($metadata,$field_name,(int)$index,'css');if(!$r)return false;
    if(!wp_register_style($name,$r['uri'],[],$r['version']))return false;
    if(is_file($r['file']))wp_style_add_data($name,'path',$r['file']);
    return $name;
}}

/* ───────── Block-Hooks (hooked blocks) ───────── */
if(!function_exists('serialize_block_attributes')){
function serialize_block_attributes($block_attributes) {
    $e=wp_json_encode($block_attributes,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    return str_replace(['--','<','>','&','\\"'],['\\u002d\\u002d','\\u003c','\\u003e','\\u0026','\\u0022'],(string)$e);
}}
if(!function_exists('get_comment_delimited_block_content')){
function get_comment_delimited_block_content($block_name,$block_attributes,$block_content) {
    if($block_name===null)return (string)$block_content;
    $n=strip_core_block_namespace($block_name);$a=empty($block_attributes)?'':serialize_block_attributes($block_attributes).' ';
    if($block_content===''||$block_content===null)return sprintf('<!-- wp:%s %s/-->',$n,$a);
    return sprintf('<!-- wp:%s %s-->%s<!-- /wp:%s -->',$n,$a,$block_content,$n);
}}
/** Hooks-fähige Fassung von traverse_and_serialize_blocks (der Kern-Platzhalter ruft die Besucher nicht auf). Besucher: fn(&$block,$parent,$prev|$next). */
if(!function_exists('elvado_wp_traverse_blocks')){
function elvado_wp_traverse_blocks($blocks,$pre=null,$post=null): string {
    $out='';$blocks=array_values((array)$blocks);$n=count($blocks);$none=null;
    foreach($blocks as $i=>$block){
        if(is_callable($pre))$out.=(string)call_user_func_array($pre,[&$block,&$none,$i===0?null:$blocks[$i-1]]);
        $after=is_callable($post)?(string)call_user_func_array($post,[&$block,&$none,$i===$n-1?null:$blocks[$i+1]]):'';
        $out.=elvado_wp_traverse_block($block,$pre,$post).$after;
    }
    return $out;
}}
if(!function_exists('elvado_wp_traverse_block')){
function elvado_wp_traverse_block($block,$pre=null,$post=null): string {
    $content='';$bi=0;$inner_blocks=array_values((array)($block['innerBlocks']??[]));$n=count($inner_blocks);
    foreach((array)($block['innerContent']??[]) as $chunk){
        if(is_string($chunk)){ $content.=$chunk;continue; }
        if(!isset($inner_blocks[$bi])){ $bi++;continue; }
        $inner=$inner_blocks[$bi];
        if(is_callable($pre))$content.=(string)call_user_func_array($pre,[&$inner,&$block,$bi===0?null:$inner_blocks[$bi-1]]);
        $after=is_callable($post)?(string)call_user_func_array($post,[&$inner,&$block,$bi===$n-1?null:$inner_blocks[$bi+1]]):'';
        $content.=elvado_wp_traverse_block($inner,$pre,$post).$after;$bi++;
    }
    return get_comment_delimited_block_content($block['blockName']??null,$block['attrs']??[],$content);
}}
if(!function_exists('elvado_wp_hooks_before_visitor')){
function elvado_wp_hooks_before_visitor($hooked_blocks,$context,$callback='insert_hooked_blocks') {
    return function(&$block,&$parent_block=null,$previous_block=null) use($hooked_blocks,$context,$callback){
        _inject_theme_attribute_in_template_part_block($block);$markup='';
        if($parent_block&&!$previous_block)$markup.=call_user_func_array($callback,[&$parent_block,'first_child',$hooked_blocks,$context]);
        return $markup.(string)call_user_func_array($callback,[&$block,'before',$hooked_blocks,$context]);
    };
}}
if(!function_exists('elvado_wp_hooks_after_visitor')){
function elvado_wp_hooks_after_visitor($hooked_blocks,$context,$callback='insert_hooked_blocks') {
    return function(&$block,&$parent_block=null,$next_block=null) use($hooked_blocks,$context,$callback){
        $markup=(string)call_user_func_array($callback,[&$block,'after',$hooked_blocks,$context]);
        if($parent_block&&!$next_block)$markup.=call_user_func_array($callback,[&$parent_block,'last_child',$hooked_blocks,$context]);
        return $markup;
    };
}}
/** Block-Typen, die an Anker-Block und Position eingehängt werden sollen (Filter hooked_block_types), je mit Filter hooked_block(_{typ}). */
if(!function_exists('elvado_wp_hooked_candidates')){
function elvado_wp_hooked_candidates(array $anchor, string $pos, $hooked_blocks, $context): array {
    $type=$anchor['blockName']??'';
    $types=(array)apply_filters('hooked_block_types',$hooked_blocks[$type][$pos]??[],$pos,$type,$context);$out=[];
    foreach($types as $t){
        $b=['blockName'=>$t,'attrs'=>[],'innerBlocks'=>[],'innerContent'=>[]];
        $b=apply_filters('hooked_block',$b,$t,$pos,$anchor,$context);$b=apply_filters("hooked_block_{$t}",$b,$t,$pos,$anchor,$context);
        if($b!==null)$out[$t]=$b;
    }
    return $out;
}}
if(!function_exists('insert_hooked_blocks')){
function insert_hooked_blocks(&$parsed_anchor_block,$relative_position,$hooked_blocks,$context) {
    $ignored=(array)($parsed_anchor_block['attrs']['metadata']['ignoredHookedBlocks']??[]);$markup='';
    foreach(elvado_wp_hooked_candidates($parsed_anchor_block,$relative_position,$hooked_blocks,$context) as $t=>$b)if(!in_array($t,$ignored,true))$markup.=serialize_block($b);
    return $markup;
}}
if(!function_exists('set_ignored_hooked_blocks_metadata')){
/** Vermerkt eingehängte Block-Typen in metadata.ignoredHookedBlocks des Ankers (per Referenz); gibt immer '' zurück. */
function set_ignored_hooked_blocks_metadata(&$parsed_anchor_block,$relative_position,$hooked_blocks,$context) {
    $types=array_keys(elvado_wp_hooked_candidates($parsed_anchor_block,$relative_position,$hooked_blocks,$context));if(!$types)return '';
    $prev=(array)($parsed_anchor_block['attrs']['metadata']['ignoredHookedBlocks']??[]);
    $parsed_anchor_block['attrs']['metadata']['ignoredHookedBlocks']=array_values(array_unique(array_merge($prev,$types)));
    return '';
}}
if(!function_exists('insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata')){
function insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata(&$parsed_anchor_block,$relative_position,$hooked_blocks,$context) {
    $markup=insert_hooked_blocks($parsed_anchor_block,$relative_position,$hooked_blocks,$context);
    return $markup.set_ignored_hooked_blocks_metadata($parsed_anchor_block,$relative_position,$hooked_blocks,$context);
}}
if(!function_exists('apply_block_hooks_to_content')){
function apply_block_hooks_to_content($content,$context=null,$callback='insert_hooked_blocks') {
    $hooked=get_hooked_blocks();$before='_inject_theme_attribute_in_template_part_block';$after=null;
    if(!empty($hooked)||has_filter('hooked_block_types')){ $before=elvado_wp_hooks_before_visitor($hooked,$context,$callback);$after=elvado_wp_hooks_after_visitor($hooked,$context,$callback); }
    return elvado_wp_traverse_blocks(parse_blocks((string)$content),$before,$after);
}}
if(!function_exists('apply_block_hooks_to_content_from_post_object')){
function apply_block_hooks_to_content_from_post_object($content,$post=null,$callback='insert_hooked_blocks') {
    $post=$post===null?get_post():get_post($post);
    return apply_block_hooks_to_content($content,$post instanceof WP_Post?$post:null,$callback);
}}
if(!function_exists('remove_serialized_parent_block')){
/** Entfernt die Kommentar-Klammer des äußersten Blocks, behält den Inhalt. */
function remove_serialized_parent_block($serialized_block) {
    $s=(string)$serialized_block;$start=strpos($s,'-->');$end=strrpos($s,'<!--');
    if($start===false||$end===false||$end<$start+3)return $s;
    $start+=3;return substr($s,$start,$end-$start);
}}
if(!function_exists('extract_serialized_parent_block')){
/** Das Gegenstück: nur die Kommentar-Klammer des äußersten Blocks, ohne Inhalt. */
function extract_serialized_parent_block($serialized_block) {
    $s=(string)$serialized_block;$start=strpos($s,'-->');$end=strrpos($s,'<!--');
    if($start===false||$end===false||$end<$start+3)return $s;
    $start+=3;return substr_replace($s,'',$start,$end-$start);
}}
if(!function_exists('update_ignored_hooked_blocks_postmeta')){
function update_ignored_hooked_blocks_postmeta($post) {
    if(empty($post->post_content))return $post;
    $attrs=[];$prev=get_post_meta($post->ID,'_wp_ignored_hooked_blocks',true);
    if(!empty($prev)){ $prev=json_decode($prev,true);if(is_array($prev))$attrs['metadata']=['ignoredHookedBlocks'=>$prev]; }
    $content=get_comment_delimited_block_content('core/post-content',$attrs,$post->post_content);
    $content=apply_block_hooks_to_content($content,$post,'set_ignored_hooked_blocks_metadata');
    $first=parse_blocks($content)[0]['attrs']??[];$ignored=(array)($first['metadata']['ignoredHookedBlocks']??[]);
    if(!empty($ignored)){
        $existing=get_post_meta($post->ID,'_wp_ignored_hooked_blocks',true);
        if(!empty($existing)){ $existing=json_decode($existing,true);if(is_array($existing))$ignored=array_values(array_unique(array_merge($ignored,$existing))); }
        update_post_meta($post->ID,'_wp_ignored_hooked_blocks',wp_json_encode($ignored));
    }
    $post->post_content=remove_serialized_parent_block($content);
    return $post;
}}
if(!function_exists('insert_hooked_blocks_into_rest_response')){
function insert_hooked_blocks_into_rest_response($response,$post) {
    if(empty($response->data['content']['raw']))return $response;
    $response->data['content']['raw']=apply_block_hooks_to_content_from_post_object($response->data['content']['raw'],$post);
    if(empty($response->data['content']['rendered']))return $response;
    $prio=has_filter('the_content','apply_block_hooks_to_content_from_post_object');   // oben schon angewendet: nicht erneut als Filter
    if($prio!==false)remove_filter('the_content','apply_block_hooks_to_content_from_post_object',$prio);
    $response->data['content']['rendered']=apply_filters('the_content',$response->data['content']['raw']);
    if($prio!==false)add_filter('the_content','apply_block_hooks_to_content_from_post_object',$prio);
    return $response;
}}
if(!function_exists('inject_ignored_hooked_blocks_metadata_attributes')){
/** Vor dem Speichern einer Vorlage: eingehängte Blöcke im Inhalt als ignoriert vermerken. */
function inject_ignored_hooked_blocks_metadata_attributes($changes,$deprecated=null) {
    if(empty($changes->post_content)||(empty(get_hooked_blocks())&&!has_filter('hooked_block_types')))return $changes;
    $changes->post_content=apply_block_hooks_to_content($changes->post_content,$changes,'set_ignored_hooked_blocks_metadata');
    return $changes;
}}

/* ───────── Muster in Blöcken auflösen ───────── */
if(!function_exists('resolve_pattern_blocks')){
function resolve_pattern_blocks($blocks) {
    static $seen=[];$out=[];
    foreach((array)$blocks as $b){
        if(($b['blockName']??null)==='core/pattern'){
            $slug=$b['attrs']['slug']??'';
            if($slug===''){ $out[]=$b;continue; }
            if(isset($seen[$slug]))continue;   // rekursive Muster überspringen
            $p=WP_Block_Patterns_Registry::get_instance()->get_registered($slug);
            $content=$p?(string)($p['content']??''):'';if($content===''&&function_exists('elvado_wp_pattern_content'))$content=(string)elvado_wp_pattern_content($slug);
            if(!$p&&$content===''){ $out[]=$b;continue; }   // unbekanntes Muster bleibt stehen
            $seen[$slug]=true;$out=array_merge($out,resolve_pattern_blocks(parse_blocks($content)));unset($seen[$slug]);continue;
        }
        if(!empty($b['innerBlocks'])){
            $new=[];$ic=[];$bi=0;$inner=array_values($b['innerBlocks']);
            foreach((array)($b['innerContent']??[]) as $chunk){
                if($chunk!==null){ $ic[]=$chunk;continue; }
                $res=resolve_pattern_blocks([$inner[$bi++]??[]]);foreach($res as $r){ $new[]=$r;$ic[]=null; }
            }
            $b['innerBlocks']=$new;$b['innerContent']=$ic;
        }
        $out[]=$b;
    }
    return $out;
}}

/* ───────── KSES für Blöcke ───────── */
if(!function_exists('_filter_block_content_callback')){
function _filter_block_content_callback($matches) { return '<!--'.rtrim($matches[1],'-').'-->'; }
}
if(!function_exists('filter_block_kses_value')){
function filter_block_kses_value($value,$allowed_html,$allowed_protocols=[],$block_context=null) {
    if(is_array($value)){ foreach($value as $k=>$v)$value[$k]=filter_block_kses_value($v,$allowed_html,$allowed_protocols,$block_context);return $value; }
    return is_string($value)?wp_kses($value,$allowed_html,$allowed_protocols):$value;
}}
if(!function_exists('filter_block_core_template_part_attributes')){
/** Template-Part-Attribute: Slug auf sichere Zeichen, tagName nur aus erlaubten Elementen. */
function filter_block_core_template_part_attributes($attributes,$block_name,$allowed_html=null,$allowed_protocols=[]) {
    if($block_name!=='core/template-part'||!is_array($attributes))return $attributes;
    foreach(['slug','theme'] as $k)if(isset($attributes[$k]))$attributes[$k]=preg_replace('/[^A-Za-z0-9_\/-]/','',(string)$attributes[$k]);
    if(isset($attributes['tagName'])&&!in_array($attributes['tagName'],['header','main','section','article','aside','footer','div'],true))unset($attributes['tagName']);
    return $attributes;
}}
if(!function_exists('filter_block_kses')){
function filter_block_kses($block,$allowed_html,$allowed_protocols=[]) {
    $block['attrs']=filter_block_kses_value($block['attrs']??[],$allowed_html,$allowed_protocols,$block);
    $block['attrs']=filter_block_core_template_part_attributes($block['attrs'],(string)($block['blockName']??''),$allowed_html,$allowed_protocols);
    foreach((array)($block['innerBlocks']??[]) as $i=>$inner)$block['innerBlocks'][$i]=filter_block_kses($inner,$allowed_html,$allowed_protocols);
    foreach((array)($block['innerContent']??[]) as $i=>$c)if(is_string($c))$block['innerContent'][$i]=wp_kses($c,$allowed_html,$allowed_protocols);
    return $block;
}}

/* ───────── Auszüge ───────── */
if(!function_exists('excerpt_remove_footnotes')){
function excerpt_remove_footnotes($content) {
    if(!str_contains((string)$content,'data-fn='))return $content;
    return preg_replace('_<sup data-fn="[^"]+" class="[^"]+">\s*<a href="[^"]+" id="[^"]+">\d+</a>\s*</sup>_','',$content);
}}
if(!function_exists('_excerpt_render_inner_blocks')){
function _excerpt_render_inner_blocks($parsed_block,$allowed_blocks) {
    $o='';
    foreach((array)($parsed_block['innerBlocks']??[]) as $inner){
        if(!in_array($inner['blockName'],$allowed_blocks,true))continue;
        $o.=empty($inner['innerBlocks'])?render_block($inner):_excerpt_render_inner_blocks($inner,$allowed_blocks);
    }
    return $o;
}}
if(!function_exists('_restore_wpautop_hook')){
function _restore_wpautop_hook($content) {
    $p=has_filter('the_content','wpautop');
    if($p!==false){ add_filter('the_content','wpautop',$p-1);remove_filter('the_content','_restore_wpautop_hook',$p+1); }
    return $content;
}}

/* ───────── Block-Hilfen ───────── */
if(!function_exists('block_version')){
function block_version($content) { return str_contains((string)$content,'<!-- wp:')?1:0; }
}
if(!function_exists('block_has_support')){
function block_has_support($block_type,$feature,$default_value=false) {
    $support=$default_value;
    if($block_type instanceof WP_Block_Type){
        $sup=(array)$block_type->supports;
        if(is_array($feature)&&count($feature)===1)$feature=$feature[0];
        if(is_array($feature))$support=_wp_array_get($sup,$feature,$default_value);
        elseif(isset($sup[$feature]))$support=$sup[$feature];
    }
    return $support===true||is_array($support);
}}
if(!function_exists('wp_migrate_old_typography_shape')){
function wp_migrate_old_typography_shape($metadata) {
    if(!isset($metadata['supports']))return $metadata;
    foreach(['__experimentalFontFamily','__experimentalFontStyle','__experimentalFontWeight','__experimentalLetterSpacing','__experimentalTextDecoration','__experimentalTextTransform','fontSize','lineHeight'] as $k){
        $v=_wp_array_get($metadata['supports'],[$k],null);
        if($v!==null){ _doing_it_wrong('register_block_type_from_metadata()',sprintf('Der Block %1$s verwendet die alte Form supports.%2$s; verwende supports.typography.%2$s.',$metadata['name']??'',$k),'5.8.0');_wp_array_set($metadata['supports'],['typography',$k],$v);unset($metadata['supports'][$k]); }
    }
    return $metadata;
}}
if(!function_exists('get_query_pagination_arrow')){
function get_query_pagination_arrow($block,$is_next) {
    $map=['none'=>'','arrow'=>['next'=>'→','previous'=>'←'],'chevron'=>['next'=>'»','previous'=>'«']];
    $a=$block->context['paginationArrow']??'';
    if($a!==''&&!empty($map[$a])){ $t=$is_next?'next':'previous';return "<span class='wp-block-query-pagination-$t-arrow is-arrow-$a' aria-hidden='true'>{$map[$a][$t]}</span>"; }
    return null;
}}
if(!function_exists('get_comments_pagination_arrow')){
function get_comments_pagination_arrow($block,$pagination_type='next') {
    $map=['none'=>'','arrow'=>['next'=>'→','previous'=>'←'],'chevron'=>['next'=>'»','previous'=>'«']];
    $a=$block->context['comments/paginationArrow']??'';
    if($a!==''&&!empty($map[$a])&&isset($map[$a][$pagination_type]))return "<span class='wp-block-comments-pagination-$pagination_type-arrow is-arrow-$a' aria-hidden='true'>{$map[$a][$pagination_type]}</span>";
    return null;
}}

/* ───────── Fußnoten ───────── */
if(!function_exists('_wp_filter_post_meta_footnotes')){
function _wp_filter_post_meta_footnotes($footnotes) {
    $dec=json_decode((string)$footnotes,true);if(!is_array($dec))return '';$out=[];
    foreach($dec as $f)if(!empty($f['content'])&&!empty($f['id']))$out[]=['id'=>$f['id'],'content'=>wp_unslash(wp_filter_post_kses(wp_slash($f['content'])))];
    return wp_json_encode($out);
}}
if(!function_exists('_wp_footnotes_kses_init_filters')){
function _wp_footnotes_kses_init_filters() { add_filter('sanitize_post_meta_footnotes','_wp_filter_post_meta_footnotes'); }
}
if(!function_exists('_wp_footnotes_remove_filters')){
function _wp_footnotes_remove_filters() { remove_filter('sanitize_post_meta_footnotes','_wp_filter_post_meta_footnotes'); }
}
if(!function_exists('_wp_footnotes_kses_init')){
function _wp_footnotes_kses_init() { _wp_footnotes_remove_filters();if(!current_user_can('unfiltered_html'))_wp_footnotes_kses_init_filters(); }
}
if(!function_exists('_wp_footnotes_force_filtered_html_on_import_filter')){
function _wp_footnotes_force_filtered_html_on_import_filter($arg) { if($arg)_wp_footnotes_kses_init_filters();return $arg; }
}

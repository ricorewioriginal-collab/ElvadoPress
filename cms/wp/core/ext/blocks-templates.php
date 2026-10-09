<?php
// Ergänzende Funktionen für Block-Vorlagen (Template-Utils, Auflösung, Template-Teile) und die Einstellungen des Block-Editors.
// Die eigentliche Vorlagen-Auswahl für die Ausgabe liegt in core/fse.php; hier die dokumentierten Hilfsfunktionen darum herum.

foreach(['WP_TEMPLATE_PART_AREA_HEADER'=>'header','WP_TEMPLATE_PART_AREA_FOOTER'=>'footer','WP_TEMPLATE_PART_AREA_SIDEBAR'=>'sidebar','WP_TEMPLATE_PART_AREA_UNCATEGORIZED'=>'uncategorized'] as $k=>$v)if(!defined($k))define($k,$v);

if(!class_exists('WP_Block_Template')){
#[AllowDynamicProperties]
class WP_Block_Template {
    public $type;public $theme;public $slug;public $id;public $title='';public $content='';public $description='';public $source='theme';public $origin;public $status='publish';public $wp_id;public $has_theme_file;public $is_custom=true;public $author;public $post_types;public $area;public $modified;
}
}
if(!class_exists('WP_Block_Editor_Context')){
class WP_Block_Editor_Context {
    public $name='core/edit-post';public $post=null;
    public function __construct(array $settings=[]) { if(isset($settings['name']))$this->name=(string)$settings['name'];if(isset($settings['post']))$this->post=$settings['post']; }
}
}

/* ───────── Ordner, Bereiche, Standardtypen ───────── */
if(!function_exists('get_block_theme_folders')){
function get_block_theme_folders($theme_stylesheet=null) {
    $def=['wp_template'=>'templates','wp_template_part'=>'parts'];
    $theme=wp_get_theme($theme_stylesheet);if(!$theme->exists())return $def;
    $dir=$theme->get_stylesheet_directory();
    // ältere Namen nur, wenn die neuen fehlen
    if(!is_dir($dir.'/templates')&&!is_dir($dir.'/parts')&&(is_dir($dir.'/block-templates')||is_dir($dir.'/block-template-parts')))return ['wp_template'=>'block-templates','wp_template_part'=>'block-template-parts'];
    return $def;
}}
if(!function_exists('get_allowed_block_template_part_areas')){
function get_allowed_block_template_part_areas() {
    return apply_filters('default_wp_template_part_areas',[
        ['area'=>WP_TEMPLATE_PART_AREA_UNCATEGORIZED,'label'=>'Allgemein','description'=>'Allgemeine Vorlagenteile ohne bestimmten Bereich.','icon'=>'layout','area_tag'=>'div'],
        ['area'=>WP_TEMPLATE_PART_AREA_HEADER,'label'=>'Kopfbereich','description'=>'Der Kopfbereich enthält meist Titel, Logo und Hauptnavigation.','icon'=>'header','area_tag'=>'header'],
        ['area'=>WP_TEMPLATE_PART_AREA_FOOTER,'label'=>'Fußbereich','description'=>'Der Fußbereich enthält meist Verweise, Impressum und Urheberrecht.','icon'=>'footer','area_tag'=>'footer'],
    ]);
}}
if(!function_exists('get_default_block_template_types')){
function get_default_block_template_types() {
    $t=[
        'index'=>['title'=>'Index','description'=>'Zeigt Inhalte, wenn keine passendere Vorlage vorhanden ist.'],
        'home'=>['title'=>'Blog-Startseite','description'=>'Zeigt die neuesten Beiträge als Startseite oder als Beitragsseite.'],
        'front-page'=>['title'=>'Startseite','description'=>'Zeigt die Startseite der Website.'],
        'singular'=>['title'=>'Einzelne Inhalte','description'=>'Zeigt einen einzelnen Inhalt, wenn keine passendere Vorlage vorhanden ist.'],
        'single'=>['title'=>'Einzelne Beiträge','description'=>'Zeigt einen einzelnen Beitrag.'],
        'page'=>['title'=>'Seiten','description'=>'Zeigt eine einzelne Seite.'],
        'archive'=>['title'=>'Archive','description'=>'Zeigt Archive, wenn keine passendere Vorlage vorhanden ist.'],
        'author'=>['title'=>'Autorenarchive','description'=>'Zeigt die Beiträge einer Autorin oder eines Autors.'],
        'category'=>['title'=>'Kategoriearchive','description'=>'Zeigt die Beiträge einer Kategorie.'],
        'taxonomy'=>['title'=>'Taxonomie','description'=>'Zeigt die Einträge einer Taxonomie.'],
        'date'=>['title'=>'Datumsarchive','description'=>'Zeigt die Beiträge eines Zeitraums.'],
        'tag'=>['title'=>'Schlagwortarchive','description'=>'Zeigt die Beiträge eines Schlagworts.'],
        'attachment'=>['title'=>'Medien','description'=>'Zeigt einen einzelnen Medieneintrag.'],
        'search'=>['title'=>'Suchergebnisse','description'=>'Zeigt die Ergebnisse einer Suche.'],
        'privacy-policy'=>['title'=>'Datenschutzerklärung','description'=>'Zeigt die Datenschutzerklärung.'],
        '404'=>['title'=>'Seite: 404','description'=>'Zeigt den Hinweis, wenn nichts gefunden wurde.'],
    ];
    return apply_filters('default_template_types',$t);
}}
if(!function_exists('_filter_block_template_part_area')){
function _filter_block_template_part_area($type) {
    if(in_array($type,array_column(get_allowed_block_template_part_areas(),'area'),true))return $type;
    _doing_it_wrong(__FUNCTION__,sprintf('"%s" ist kein gültiger Bereich für Vorlagenteile; verwendet wird der Allgemeine Bereich.',$type),'5.9.0');
    return WP_TEMPLATE_PART_AREA_UNCATEGORIZED;
}}

/* ───────── theme.json: eigene Vorlagen und Vorlagenteile ───────── */
if(!function_exists('wp_get_theme_data_custom_templates')){
function wp_get_theme_data_custom_templates() {
    $o=[];foreach((array)(elvado_wp_theme_json()['theme']['customTemplates']??[]) as $t)if(is_array($t)&&!empty($t['name']))$o[$t['name']]=['title'=>$t['title']??$t['name'],'postTypes'=>$t['postTypes']??['page']];
    return $o;
}}
if(!function_exists('wp_get_theme_data_template_parts')){
function wp_get_theme_data_template_parts() {
    $o=[];foreach((array)(elvado_wp_theme_json()['theme']['templateParts']??[]) as $t)if(is_array($t)&&!empty($t['name']))$o[$t['name']]=['title'=>$t['title']??$t['name'],'area'=>$t['area']??WP_TEMPLATE_PART_AREA_UNCATEGORIZED];
    return $o;
}}

/* ───────── Vorlagen-Dateien ───────── */
if(!function_exists('_get_block_templates_paths')){
function _get_block_templates_paths($base_directory) {
    $o=[];if(!is_dir($base_directory))return $o;
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base_directory,FilesystemIterator::SKIP_DOTS));
    foreach($it as $f)if($f->isFile()&&strtolower($f->getExtension())==='html')$o[]=wp_normalize_path($f->getPathname());
    sort($o);return $o;
}}
if(!function_exists('_add_block_template_info')){
function _add_block_template_info($template_item) {
    if(!wp_theme_has_theme_json())return $template_item;
    $d=wp_get_theme_data_custom_templates();
    if(isset($d[$template_item['slug']])){ $template_item['title']=$d[$template_item['slug']]['title'];$template_item['postTypes']=$d[$template_item['slug']]['postTypes']; }
    return $template_item;
}}
if(!function_exists('_add_block_template_part_area_info')){
function _add_block_template_part_area_info($template_info) {
    if(wp_theme_has_theme_json()){
        $d=wp_get_theme_data_template_parts();
        if(isset($d[$template_info['slug']]['area'])){ $template_info['title']=$d[$template_info['slug']]['title'];$template_info['area']=_filter_block_template_part_area($d[$template_info['slug']]['area']);return $template_info; }
    }
    $template_info['area']=WP_TEMPLATE_PART_AREA_UNCATEGORIZED;return $template_info;
}}
if(!function_exists('_get_block_template_file')){
function _get_block_template_file($template_type,$slug) {
    if($template_type!=='wp_template'&&$template_type!=='wp_template_part')return null;
    foreach([get_stylesheet()=>get_stylesheet_directory(),get_template()=>get_template_directory()] as $theme=>$dir){
        $f=get_block_theme_folders($theme);$path=$dir.'/'.$f[$template_type].'/'.$slug.'.html';
        if(!file_exists($path))continue;
        $item=['slug'=>$slug,'path'=>$path,'theme'=>$theme,'type'=>$template_type];
        return $template_type==='wp_template_part'?_add_block_template_part_area_info($item):_add_block_template_info($item);
    }
    return null;
}}
if(!function_exists('_get_block_templates_files')){
function _get_block_templates_files($template_type,$query=[]) {
    if($template_type!=='wp_template'&&$template_type!=='wp_template_part')return null;
    $out=[];$seen=[];
    foreach([get_stylesheet()=>get_stylesheet_directory(),get_template()=>get_template_directory()] as $theme=>$dir){
        $f=get_block_theme_folders($theme);$base=wp_normalize_path($dir.'/'.$f[$template_type]);
        foreach(_get_block_templates_paths($base) as $file){
            $slug=substr($file,strlen($base)+1,-5);
            if(isset($seen[$slug]))continue;   // das Kind-Theme gewinnt
            if(!empty($query['slug__in'])&&!in_array($slug,(array)$query['slug__in'],true))continue;
            if(!empty($query['slug__not_in'])&&in_array($slug,(array)$query['slug__not_in'],true))continue;
            $item=['slug'=>$slug,'path'=>$file,'theme'=>$theme,'type'=>$template_type];
            $item=$template_type==='wp_template_part'?_add_block_template_part_area_info($item):_add_block_template_info($item);
            if(!empty($query['area'])&&($item['area']??'')!==$query['area'])continue;
            if(!empty($query['post_type'])&&isset($item['postTypes'])&&!in_array($query['post_type'],(array)$item['postTypes'],true))continue;
            $seen[$slug]=true;$out[]=$item;
        }
    }
    return $out;
}}

/* ───────── Block-Baum-Hilfen ───────── */
if(!function_exists('_flatten_blocks')){
/** Alle Blöcke breitenweise als Referenzen (Änderungen wirken im Original). */
function _flatten_blocks(&$blocks) {
    $all=[];$queue=[];
    foreach($blocks as &$b)$queue[]=&$b;
    unset($b);
    while($queue){
        $block=&$queue[0];array_shift($queue);$all[]=&$block;
        if(!empty($block['innerBlocks']))foreach($block['innerBlocks'] as &$inner)$queue[]=&$inner;
        unset($inner);unset($block);
    }
    return $all;
}}
if(!function_exists('_inject_theme_attribute_in_template_part_block')){
function _inject_theme_attribute_in_template_part_block(&$block) {
    if(($block['blockName']??null)==='core/template-part'&&isset($block['attrs']['slug'])&&!isset($block['attrs']['theme']))$block['attrs']['theme']=get_stylesheet();
}}
if(!function_exists('_remove_theme_attribute_from_template_part_block')){
function _remove_theme_attribute_from_template_part_block(&$block) {
    if(($block['blockName']??null)==='core/template-part'&&isset($block['attrs']['theme']))unset($block['attrs']['theme']);
}}

/* ───────── Vorlagen-Objekte ───────── */
if(!function_exists('_build_block_template_result_from_file')){
function _build_block_template_result_from_file($template_file,$template_type) {
    $defaults=get_default_block_template_types();$content=(string)file_get_contents($template_file['path']);
    $t=new WP_Block_Template();
    $t->id=$template_file['theme'].'//'.$template_file['slug'];$t->theme=$template_file['theme'];$t->slug=$template_file['slug'];$t->type=$template_type;
    $t->source='theme';$t->status='publish';$t->has_theme_file=true;$t->is_custom=true;$t->modified=@filemtime($template_file['path'])?:null;
    $t->title=!empty($template_file['title'])?$template_file['title']:$template_file['slug'];
    $t->description=!empty($template_file['description'])?$template_file['description']:'';
    if($template_type==='wp_template'&&isset($defaults[$template_file['slug']])){ $t->description=$defaults[$template_file['slug']]['description'];$t->title=$defaults[$template_file['slug']]['title'];$t->is_custom=false; }
    if($template_type==='wp_template'&&isset($template_file['postTypes']))$t->post_types=$template_file['postTypes'];
    if($template_type==='wp_template_part'&&isset($template_file['area']))$t->area=$template_file['area'];
    // Template-Parts bekommen das Theme-Attribut (und eingehängte Blöcke), nur wenn nötig
    $t->content=(str_contains($content,'wp:template-part')||has_filter('hooked_block_types'))?apply_block_hooks_to_content($content,$t,'insert_hooked_blocks'):$content;
    return $t;
}}
if(!function_exists('_wp_build_title_and_description_for_single_post_type_block_template')){
function _wp_build_title_and_description_for_single_post_type_block_template($post_type,$slug,WP_Block_Template $template) {
    $pt=get_post_type_object($post_type);if(!$pt)return false;
    $q=new WP_Query(['name'=>$slug,'post_type'=>$post_type,'post_status'=>'publish','posts_per_page'=>1,'ignore_sticky_posts'=>true,'no_found_rows'=>true]);
    if(empty($q->posts)){ $template->title=sprintf('Einzelner Eintrag: %s',$slug);return false; }
    $title=$q->posts[0]->post_title;
    $template->title=sprintf('Einzelner Eintrag: %s',$title);$template->description=sprintf('Vorlage für %s',$title);
    return true;
}}
if(!function_exists('_wp_build_title_and_description_for_taxonomy_block_template')){
function _wp_build_title_and_description_for_taxonomy_block_template($taxonomy,$slug,WP_Block_Template $template) {
    $tax=get_taxonomy($taxonomy);if(!$tax)return false;
    $label=$tax->labels->singular_name??$tax->label??$taxonomy;$term=get_term_by('slug',$slug,$taxonomy);
    if(!$term||is_wp_error($term)){ $template->title=sprintf('%1$s: %2$s',$label,$slug);return false; }
    $template->title=sprintf('%1$s: %2$s',$label,$term->name);$template->description=sprintf('Vorlage für %s',$term->name);
    return true;
}}
if(!function_exists('_build_block_template_object_from_post_object')){
function _build_block_template_object_from_post_object($post,$terms=[],$meta=[]) {
    if(empty($terms['wp_theme']))return new WP_Error('template_missing_theme','Für diese Vorlage ist kein Theme festgelegt.');
    $theme=$terms['wp_theme'];$defaults=get_default_block_template_types();$file=_get_block_template_file($post->post_type,$post->post_name);
    $t=new WP_Block_Template();
    $t->wp_id=$post->ID;$t->id=$theme.'//'.$post->post_name;$t->theme=$theme;$t->content=$post->post_content;$t->slug=$post->post_name;$t->source='custom';
    $t->origin=!empty($meta['origin'])?$meta['origin']:null;$t->type=$post->post_type;$t->description=$post->post_excerpt;$t->title=$post->post_title;$t->status=$post->post_status;
    $t->has_theme_file=get_stylesheet()===$theme&&$file!==null;$t->is_custom=true;$t->author=$post->post_author;
    if($post->post_type==='wp_template'&&isset($defaults[$t->slug]))$t->is_custom=false;
    if($post->post_type==='wp_template'&&isset($meta['is_wp_suggestion'])&&$meta['is_wp_suggestion']!=='')$t->is_custom=!$meta['is_wp_suggestion'];
    if($post->post_type==='wp_template_part'){ $a=get_the_terms($post,'wp_template_part_area');if($a&&!is_wp_error($a))$t->area=$a[0]->name; }
    if($post->post_type==='wp_template_part'&&get_stylesheet()===$theme)$t->content=apply_block_hooks_to_content($t->content,$t,'insert_hooked_blocks');
    return $t;
}}
if(!function_exists('_build_block_template_result_from_post')){
function _build_block_template_result_from_post($post) {
    $terms=get_the_terms($post,'wp_theme');if(is_wp_error($terms))return $terms;
    // die Schicht führt die Taxonomie wp_theme nicht: ohne Eintrag gilt das aktive Theme
    $theme=$terms?$terms[0]->name:get_stylesheet();
    return _build_block_template_object_from_post_object($post,['wp_theme'=>$theme],['origin'=>get_post_meta($post->ID,'origin',true),'is_wp_suggestion'=>get_post_meta($post->ID,'is_wp_suggestion',true)]);
}}

/* ───────── Template-Teile ausgeben ───────── */
if(!function_exists('block_template_part')){
function block_template_part($part) {
    $tp=get_block_template(get_stylesheet().'//'.$part,'wp_template_part');
    if(!$tp||empty($tp->content))return;
    echo do_blocks($tp->content);
}}
if(!function_exists('block_header_area')){
function block_header_area() { block_template_part('header'); }
}
if(!function_exists('block_footer_area')){
function block_footer_area() { block_template_part('footer'); }
}

/* ───────── Export und Hierarchie ───────── */
if(!function_exists('wp_is_theme_directory_ignored')){
function wp_is_theme_directory_ignored($path) {
    foreach(['.DS_Store','.svn','.git','.hg','.bzr','node_modules','vendor'] as $d)if(str_starts_with((string)$path,$d))return true;
    return false;
}}
if(!function_exists('wp_generate_block_templates_export_file')){
/** Theme als ZIP (mit eigenen Vorlagen aus dem Website-Editor); gibt den Pfad der temporären Datei zurück. */
function wp_generate_block_templates_export_file() {
    if(!class_exists('ZipArchive'))return new WP_Error('missing_zip_package','Die PHP-Erweiterung zip fehlt.');
    $file=wp_tempnam(get_stylesheet().'.zip');$zip=new ZipArchive();
    if($zip->open($file,ZipArchive::OVERWRITE)!==true)return new WP_Error('unable_to_create_zip','Die ZIP-Datei konnte nicht angelegt werden.');
    $dir=rtrim(wp_normalize_path(get_stylesheet_directory()),'/');$custom=[];$slug=preg_replace('/[^a-z0-9_-]/i','-',get_stylesheet());
    foreach(['templates','parts'] as $sub)foreach(glob(ELVADO_WP_DATA.'/site-editor/'.$slug.'/'.$sub.'/*.html')?:[] as $f)$custom[$sub.'/'.basename($f)]=$f;
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
    foreach($it as $f){
        $rel=substr(wp_normalize_path($f->getPathname()),strlen($dir)+1);
        if(wp_is_theme_directory_ignored($rel)||!$f->isFile()||isset($custom[$rel]))continue;
        if($rel==='theme.json'){ $user=get_option('elvado_wp_global_styles',[]);if(is_array($user)&&$user){ $zip->addFromString($rel,wp_json_encode(elvado_wp_tj_merge(elvado_wp_json_file($f->getPathname()),$user),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));continue; } }
        $zip->addFile($f->getPathname(),$rel);
    }
    foreach($custom as $rel=>$src)$zip->addFromString($rel,elvado_wp_traverse_blocks(parse_blocks((string)file_get_contents($src)),'_remove_theme_attribute_from_template_part_block'));
    $zip->close();
    return $file;
}}
if(!function_exists('get_template_hierarchy')){
function get_template_hierarchy($slug,$is_custom=false,$template_prefix='') {
    if($slug==='index')return ['index'];
    if($is_custom)return ['page','singular','index'];
    if($slug==='front-page')return ['front-page','home','index'];
    if($slug==='privacy-policy')return ['privacy-policy','page','singular','index'];
    $h=[$slug];$type=$slug;
    if($template_prefix!==''){   // z. B. category-news: Präfix „category“ ergänzt die Kette
        [$type]=explode('-',$template_prefix);
        if($slug!==$template_prefix)$h[]=$template_prefix;
        if($type!==$template_prefix&&$type!==$slug)$h[]=$type;
    }
    if(in_array($type,['date','author','category','tag','taxonomy'],true))$h[]='archive';
    if($type==='attachment')$h[]='single';
    if(in_array($type,['single','page','attachment'],true))$h[]='singular';
    $h[]='index';
    return array_values(array_unique($h));
}}

/* ───────── Vorlagen-Auflösung (block-template.php) ───────── */
if(!function_exists('_strip_template_file_suffix')){
function _strip_template_file_suffix($template_file) { return preg_replace('/\.(php|html)$/','',(string)$template_file); }
}
if(!function_exists('_block_template_render_title_tag')){
function _block_template_render_title_tag() { echo '<title>'.wp_get_document_title().'</title>'."\n"; }
}
if(!function_exists('_block_template_viewport_meta_tag')){
function _block_template_viewport_meta_tag() { echo '<meta name="viewport" content="width=device-width, initial-scale=1" />'."\n"; }
}
if(!function_exists('_block_template_render_without_post_block_context')){
function _block_template_render_without_post_block_context($context) {
    // Vorlagen sind nur Struktur: ihr Beitragskontext würde Post-Content-Blöcke endlos verschachteln
    if(isset($context['postType'])&&$context['postType']==='wp_template')return [];
    return $context;
}}
if(!function_exists('resolve_block_template')){
function resolve_block_template($template_type,$template_hierarchy,$fallback_template) {
    if(!$template_type)return null;
    if(empty($template_hierarchy))$template_hierarchy=[$template_type];
    $slugs=array_map('_strip_template_file_suffix',(array)$template_hierarchy);
    $limit=count($slugs);   // eine vorhandene PHP-Vorlage gewinnt gegen gleich oder weniger spezifische Block-Vorlagen
    if($fallback_template){ $i=array_search(_strip_template_file_suffix(basename((string)$fallback_template)),$slugs,true);if($i!==false)$limit=$i; }
    $t=null;
    foreach(array_slice($slugs,0,$limit) as $slug){
        $f=elvado_wp_block_template_file($slug);if($f==='')continue;
        $t=_build_block_template_result_from_file(['slug'=>$slug,'path'=>$f,'theme'=>get_stylesheet(),'type'=>'wp_template'],'wp_template');
        if(str_contains(wp_normalize_path($f),'/site-editor/')){ $t->source='custom';$t->has_theme_file=elvado_wp_block_template_file($slug)!==''; }
        break;
    }
    if($t&&trim((string)$t->content)===''&&is_user_logged_in())$t->content=sprintf('Leere Vorlage: %s',$t->title);
    return $t;
}}
if(!function_exists('locate_block_template')){
function locate_block_template($template,$type,array $templates) {
    unset($GLOBALS['_wp_current_template_id'],$GLOBALS['_wp_current_template_content']);
    $bt=resolve_block_template($type,$templates,$template);
    if($bt){
        $GLOBALS['_wp_current_template_id']=$bt->id;$GLOBALS['_wp_current_template_content']=$bt->content;
        // virtueller Pfad: die Ausgabe übernimmt elvado_wp_render_block_template()
        return ABSPATH.WPINC.'/template-canvas.php';
    }
    return $template;
}}
if(!function_exists('_add_template_loader_filters')){
function _add_template_loader_filters() {
    if(!current_theme_supports('block-templates'))return;
    foreach(array_keys(get_default_block_template_types()) as $t){ if($t==='embed')continue;add_filter(str_replace('-','',$t).'_template','locate_block_template',20,3); }
}}
if(!function_exists('wp_render_empty_block_template_warning')){
function wp_render_empty_block_template_warning($content) {
    if(trim(wp_strip_all_tags((string)$content))!==''||!current_user_can('edit_theme_options'))return $content;
    return '<div class="wp-site-blocks"><p>'.esc_html('Diese Block-Vorlage ist leer. Füge Blöcke im Website-Editor hinzu.').'</p></div>';
}}
if(!function_exists('_resolve_template_for_new_post')){
/** Für einen neuen Entwurf (auto-draft) die passende Vorlage ermitteln; vereinfacht: setzt die aktuelle Vorlage. */
function _resolve_template_for_new_post($wp_query) {
    if(!$wp_query->is_main_query())return;
    remove_filter('pre_get_posts','_resolve_template_for_new_post');
    $id=$wp_query->query['page_id']??($wp_query->query['p']??null);$post=$id?get_post($id):null;
    if(!$post||$post->post_status!=='auto-draft'||!current_user_can('edit_post',$post->ID))return;
    $slugs=$post->post_type==='page'?['page','singular','index']:['single-'.$post->post_type,'single','singular','index'];
    $bt=resolve_block_template('wp_template',$slugs,'');
    if($bt){ $GLOBALS['_wp_current_template_id']=$bt->id;$GLOBALS['_wp_current_template_content']=$bt->content; }
}}
if(!function_exists('unregister_block_template')){
function unregister_block_template($template_name) {
    $r=$GLOBALS['elvado_wp_registered_block_templates'][$template_name]??null;
    if($r===null)return new WP_Error('template_not_registered',sprintf('Die Vorlage „%s“ ist nicht registriert.',$template_name));
    unset($GLOBALS['elvado_wp_registered_block_templates'][$template_name]);
    $t=new WP_Block_Template();$t->id=$template_name;$t->slug=(string)(explode('//',$template_name,2)[1]??$template_name);$t->title=(string)($r['title']??'');$t->description=(string)($r['description']??'');
    $t->content=(string)($r['content']??'');$t->post_types=$r['post_types']??null;$t->source='plugin';$t->type='wp_template';
    return $t;
}}

/* ───────── Block-Editor: Kategorien und Einstellungen ───────── */
if(!function_exists('get_default_block_categories')){
function get_default_block_categories() {
    return [['slug'=>'text','title'=>'Text'],['slug'=>'media','title'=>'Medien'],['slug'=>'design','title'=>'Design'],['slug'=>'widgets','title'=>'Widgets'],['slug'=>'theme','title'=>'Theme'],['slug'=>'embed','title'=>'Einbettungen'],['slug'=>'reusable','title'=>'Muster']];
}}
if(!function_exists('get_block_categories')){
function get_block_categories($post_or_block_editor_context) {
    $ctx=$post_or_block_editor_context instanceof WP_Post?new WP_Block_Editor_Context(['post'=>$post_or_block_editor_context]):$post_or_block_editor_context;
    $c=apply_filters('block_categories_all',get_default_block_categories(),$ctx);
    if(!empty($ctx->post))$c=apply_filters('block_categories',$c,$ctx->post);
    return $c;
}}
if(!function_exists('get_allowed_block_types')){
function get_allowed_block_types($block_editor_context) {
    $a=apply_filters('allowed_block_types_all',true,$block_editor_context);
    if(!empty($block_editor_context->post))$a=apply_filters('allowed_block_types',$a,$block_editor_context->post);
    return $a;
}}
if(!function_exists('get_default_block_editor_settings')){
function get_default_block_editor_settings() {
    $names=apply_filters('image_size_names_choose',['thumbnail'=>'Vorschaubild','medium'=>'Mittel','large'=>'Groß','full'=>'Originalgröße']);
    $sizes=[];foreach($names as $slug=>$n)$sizes[]=['slug'=>$slug,'name'=>$n];
    $dims=[];foreach(['thumbnail','medium','large'] as $s)$dims[$s]=['width'=>(int)get_option($s.'_size_w',$s==='thumbnail'?150:($s==='medium'?300:1024)),'height'=>(int)get_option($s.'_size_h',$s==='thumbnail'?150:($s==='medium'?300:1024))];
    return [
        'alignWide'=>get_theme_support('align-wide'),'allowedBlockTypes'=>true,'allowedMimeTypes'=>get_allowed_mime_types(),
        'defaultEditorStyles'=>[['css'=>'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:18px;line-height:1.5;}']],
        'blockCategories'=>get_default_block_categories(),'isRTL'=>function_exists('is_rtl')&&is_rtl(),'imageDefaultSize'=>get_option('image_default_size','large'),'imageDimensions'=>$dims,
        'imageEditing'=>true,'imageSizes'=>$sizes,'maxUploadFileSize'=>(int)wp_max_upload_size(),'__unstableGalleryWithImageBlocks'=>true,
    ];
}}
if(!function_exists('get_legacy_widget_block_editor_settings')){
function get_legacy_widget_block_editor_settings() {
    return ['widgetTypesToHideFromLegacyWidgetBlock'=>apply_filters('widget_types_to_hide_from_legacy_widget_block',['pages','calendar','archives','media_audio','media_image','media_gallery','media_video','search','text','categories','recent-posts','recent-comments','rss','tag_cloud','custom_html','block'])];
}}
if(!function_exists('_wp_get_iframed_editor_assets')){
function _wp_get_iframed_editor_assets() { /* Standardwert: Der Editor lädt seine Assets selbst (keine Admin-Oberfläche in der Schicht). */ return ['styles'=>'','scripts'=>'']; }
}
if(!function_exists('wp_get_first_block')){
function wp_get_first_block($blocks,$block_name) {
    foreach((array)$blocks as $b){
        if(($b['blockName']??null)===$block_name)return $b;
        if(!empty($b['innerBlocks'])){ $f=wp_get_first_block($b['innerBlocks'],$block_name);if(!empty($f))return $f; }
    }
    return [];
}}
if(!function_exists('wp_get_post_content_block_attributes')){
function wp_get_post_content_block_attributes() {
    if(!isset($GLOBALS['_wp_current_template_content']))return null;
    $b=wp_get_first_block(parse_blocks((string)$GLOBALS['_wp_current_template_content']),'core/post-content');
    return isset($b['attrs'])?$b['attrs']:null;
}}
if(!function_exists('get_block_editor_theme_styles')){
function get_block_editor_theme_styles() {
    $styles=[];$es=$GLOBALS['editor_styles']??null;
    if($es&&current_theme_supports('editor-styles'))foreach($es as $s){
        if(preg_match('~^(https?:)?//~',(string)$s)){ $r=wp_remote_get($s);if(!is_wp_error($r))$styles[]=['css'=>wp_remote_retrieve_body($r),'__unstableType'=>'theme','isGlobalStyles'=>false]; }
        else{ $f=get_theme_file_path($s);if(is_file($f))$styles[]=['css'=>file_get_contents($f),'baseURL'=>get_theme_file_uri($s),'__unstableType'=>'theme','isGlobalStyles'=>false]; }
    }
    return $styles;
}}
if(!function_exists('get_classic_theme_supports_block_editor_settings')){
function get_classic_theme_supports_block_editor_settings() {
    $s=['disableCustomColors'=>get_theme_support('disable-custom-colors'),'disableCustomFontSizes'=>get_theme_support('disable-custom-font-sizes'),'disableCustomGradients'=>get_theme_support('disable-custom-gradients'),'disableLayoutStyles'=>get_theme_support('disable-layout-styles'),
        'enableCustomLineHeight'=>get_theme_support('custom-line-height'),'enableCustomSpacing'=>get_theme_support('custom-spacing'),'enableCustomUnits'=>get_theme_support('custom-units')];
    foreach(['colors'=>'editor-color-palette','fontSizes'=>'editor-font-sizes','gradients'=>'editor-gradient-presets'] as $k=>$feature){ $v=get_theme_support($feature);if(is_array($v)&&isset($v[0]))$s[$k]=$v[0]; }
    $s['alignWide']=get_theme_support('align-wide');
    return array_filter($s,fn($v)=>$v!==false);
}}
if(!function_exists('get_block_editor_settings')){
function get_block_editor_settings(array $custom_settings,$block_editor_context) {
    $e=array_merge(get_default_block_editor_settings(),$custom_settings);$gs=[];
    foreach([['variables','presets'],['presets','presets']] as [$css,$type]){ $c=wp_get_global_stylesheet([$css]);if($c!=='')$gs[]=['css'=>$c,'__unstableType'=>$type,'isGlobalStyles'=>true]; }
    $theme=wp_theme_has_theme_json();$c=wp_get_global_stylesheet([$theme?'styles':'base-layout-styles']);
    if($c!=='')$gs[]=['css'=>$c,'__unstableType'=>$theme?'theme':'base-layout','isGlobalStyles'=>true];
    $e['styles']=array_merge($gs,get_block_editor_theme_styles());
    $e['__experimentalFeatures']=wp_get_global_settings();
    // Paletten, Verläufe und Schriftgrößen (Standard und Theme zusammen)
    foreach(['colors'=>'color.palette','gradients'=>'color.gradients','fontSizes'=>'typography.fontSizes'] as $k=>$path){ $p=elvado_wp_tj_presets($path);$list=array_merge($p['default'],$p['theme']);if($list)$e[$k]=$list; }
    $e['__unstableResolvedAssets']=_wp_get_iframed_editor_assets();$e['localAutosaveInterval']=15;
    $e['disableLayoutStyles']=(bool)current_theme_supports('disable-layout-styles');
    $e=apply_filters('block_editor_settings_all',$e,$block_editor_context);
    if(!empty($block_editor_context->post))$e=apply_filters('block_editor_settings',$e,$block_editor_context->post);
    return $e;
}}
if(!function_exists('block_editor_rest_api_preload')){
function block_editor_rest_api_preload(array $preload_paths,$block_editor_context) {
    $preload_paths=apply_filters('block_editor_rest_api_preload_paths',$preload_paths,$block_editor_context);
    if(!empty($block_editor_context->post))$preload_paths=apply_filters('block_editor_preload_paths',$preload_paths,$block_editor_context->post);
    $backup=$GLOBALS['post']??null;
    foreach($preload_paths as &$p){
        if(is_string($p)&&!str_starts_with($p,'/')){ $p='/'.$p;continue; }
        if(is_array($p)&&is_string($p[0]??null)&&!str_starts_with($p[0],'/'))$p[0]='/'.$p[0];
    }
    unset($p);
    $data=array_reduce($preload_paths,'rest_preload_api_request',[]);
    $GLOBALS['post']=$backup;
    wp_add_inline_script('wp-api-fetch',sprintf('wp.apiFetch.use( wp.apiFetch.createPreloadingMiddleware( %s ) );',wp_json_encode($data)),'after');
}}
if(!function_exists('wp_initialize_site_preview_hooks')){
function wp_initialize_site_preview_hooks($new_site=null) { /* Standardwert: Website-Vorschau im Netzwerk gibt es im Einzelbetrieb nicht. */ }
}

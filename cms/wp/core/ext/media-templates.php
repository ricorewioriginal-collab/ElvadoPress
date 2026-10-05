<?php
// Ergänzende Vorlagen-Hierarchie (wp-includes/template.php): get_*_template() liefern den Pfad der passenden Theme-Vorlage (leer, wenn keine existiert).
// Die vorhandene get_query_template wendet die Filter „{$type}_template“ nicht an; die Funktionen hier nutzen den Hilfsaufruf _rrw_m_qt mit Filtern.

if(!function_exists('_rrw_m_qt')){ function _rrw_m_qt($type,$templates=[]) {
    $type=preg_replace('|[^a-z0-9-]+|','',(string)$type);if(empty($templates))$templates=["{$type}.php"];
    $templates=apply_filters("{$type}_template_hierarchy",$templates);
    return apply_filters("{$type}_template",locate_template($templates),$type,$templates);
} }
if(!function_exists('get_index_template')){ function get_index_template() { return _rrw_m_qt('index'); } }
if(!function_exists('get_404_template')){ function get_404_template() { return _rrw_m_qt('404'); } }
if(!function_exists('get_archive_template')){ function get_archive_template() {
    $pt=array_filter((array)get_query_var('post_type'));$t=[];if(count($pt)===1)$t[]='archive-'.reset($pt).'.php';$t[]='archive.php';return _rrw_m_qt('archive',$t);
} }
if(!function_exists('get_post_type_archive_template')){ function get_post_type_archive_template() {
    $pt=get_query_var('post_type');if(is_array($pt))$pt=reset($pt);$o=get_post_type_object($pt);
    if(!is_object($o)||!$o->has_archive)return '';return get_archive_template();
} }
if(!function_exists('get_author_template')){ function get_author_template() {
    $a=get_queried_object();$t=[];if($a instanceof WP_User){ $t[]="author-{$a->user_nicename}.php";$t[]="author-{$a->ID}.php"; }$t[]='author.php';return _rrw_m_qt('author',$t);
} }
if(!function_exists('get_category_template')){ function get_category_template() {
    $c=get_queried_object();$t=[];
    if(!empty($c->slug)){ $dec=urldecode($c->slug);if($dec!==$c->slug)$t[]="category-{$dec}.php";$t[]="category-{$c->slug}.php";$t[]="category-{$c->term_id}.php"; }
    $t[]='category.php';return _rrw_m_qt('category',$t);
} }
if(!function_exists('get_tag_template')){ function get_tag_template() {
    $g=get_queried_object();$t=[];
    if(!empty($g->slug)){ $dec=urldecode($g->slug);if($dec!==$g->slug)$t[]="tag-{$dec}.php";$t[]="tag-{$g->slug}.php";$t[]="tag-{$g->term_id}.php"; }
    $t[]='tag.php';return _rrw_m_qt('tag',$t);
} }
if(!function_exists('get_taxonomy_template')){ function get_taxonomy_template() {
    $term=get_queried_object();$t=[];
    if(!empty($term->slug)){ $tax=$term->taxonomy;$dec=urldecode($term->slug);if($dec!==$term->slug)$t[]="taxonomy-$tax-{$dec}.php";$t[]="taxonomy-$tax-{$term->slug}.php";$t[]="taxonomy-$tax.php"; }
    $t[]='taxonomy.php';return _rrw_m_qt('taxonomy',$t);
} }
if(!function_exists('get_date_template')){ function get_date_template() { return _rrw_m_qt('date'); } }
if(!function_exists('get_home_template')){ function get_home_template() { return _rrw_m_qt('home',['home.php','index.php']); } }
if(!function_exists('get_front_page_template')){ function get_front_page_template() { return _rrw_m_qt('front_page',['front-page.php']); } }
if(!function_exists('get_privacy_policy_template')){ function get_privacy_policy_template() { return _rrw_m_qt('privacy_policy',['privacy-policy.php']); } }
if(!function_exists('get_page_template')){ function get_page_template() {
    $id=get_queried_object_id();$tpl=get_page_template_slug();$name=get_query_var('pagename');
    if(!$name&&$id){ $p=get_queried_object();if($p)$name=$p->post_name; }
    $t=[];if($tpl&&validate_file($tpl)===0)$t[]=$tpl;
    if($name){ $dec=urldecode($name);if($dec!==$name)$t[]="page-{$dec}.php";$t[]="page-{$name}.php"; }
    if($id)$t[]="page-{$id}.php";$t[]='page.php';return _rrw_m_qt('page',$t);
} }
if(!function_exists('get_search_template')){ function get_search_template() { return _rrw_m_qt('search'); } }
if(!function_exists('get_single_template')){ function get_single_template() {
    $o=get_queried_object();$t=[];
    if(!empty($o->post_type)){ $tpl=get_page_template_slug($o);if($tpl&&validate_file($tpl)===0)$t[]=$tpl;
        $dec=urldecode((string)$o->post_name);if($dec!==$o->post_name)$t[]="single-{$o->post_type}-{$dec}.php";$t[]="single-{$o->post_type}-{$o->post_name}.php";$t[]="single-{$o->post_type}.php"; }
    $t[]='single.php';return _rrw_m_qt('single',$t);
} }
if(!function_exists('get_embed_template')){ function get_embed_template() {
    $o=get_queried_object();$t=[];
    if(!empty($o->post_type)){ $f=get_post_format($o);if($f)$t[]="embed-{$o->post_type}-{$f}.php";$t[]="embed-{$o->post_type}.php"; }
    $t[]='embed.php';return _rrw_m_qt('embed',$t);
} }
if(!function_exists('get_singular_template')){ function get_singular_template() { return _rrw_m_qt('singular',['singular.php']); } }
if(!function_exists('get_attachment_template')){ function get_attachment_template() {
    $a=get_queried_object();$t=[];
    if($a){ $mime=(string)($a->post_mime_type??'');[$type,$sub]=str_contains($mime,'/')?explode('/',$mime,2):[$mime,''];
        if($sub!==''){ $t[]="{$type}-{$sub}.php";$t[]="{$sub}.php"; }$t[]="{$type}.php"; }
    $t[]='attachment.php';return _rrw_m_qt('attachment',$t);
} }
if(!function_exists('wp_set_template_globals')){ /** Setzt die Globals zur gewählten Vorlage: Pfad ($template) bzw. Block-Vorlage (id/content in $_wp_current_template_*). */
function wp_set_template_globals($template=null) {
    if(is_string($template)&&$template!==''){ $GLOBALS['template']=$template;return; }
    if(is_object($template)){ $GLOBALS['_wp_current_template_id']=$template->id??null;$GLOBALS['_wp_current_template_content']=$template->content??''; }
} }

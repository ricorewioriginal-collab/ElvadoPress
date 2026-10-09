<?php
// Begriffe (Kategorien, Schlagwörter, eigene Taxonomien). Tabellen wp_terms/wp_term_taxonomy/wp_term_relationships, ergänzt um die
// virtuellen Begriffe der CMS-Beiträge (Kategorie und Schlagwörter aus news.json) für „category“ und „post_tag“.

function elvado_wp_term_row_to_obj($r): WP_Term { $r->filter='raw';return new WP_Term($r); }
function elvado_wp_db_terms(string $taxonomy): array {
    global $wpdb;if(!$wpdb||!$wpdb->ready)return [];elvado_wp_install_schema();
    $rows=$wpdb->get_results($wpdb->prepare("SELECT t.term_id, t.name, t.slug, t.term_group, tt.term_taxonomy_id, tt.taxonomy, tt.description, tt.parent, tt.count FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id WHERE tt.taxonomy = %s",$taxonomy));
    return array_map('elvado_wp_term_row_to_obj',is_array($rows)?$rows:[]);
}
/** Alle Begriffe einer Taxonomie (Tabelle + virtuelle CMS-Begriffe, Tabelle hat Vorrang bei gleichem Slug). */
function elvado_wp_all_terms(string $taxonomy): array {
    $out=[];foreach(elvado_wp_db_terms($taxonomy) as $t)$out[$t->slug]=$t;
    if(in_array($taxonomy,['category','post_tag'],true))foreach(elvado_wp_cms_terms($taxonomy) as $t){ if(!isset($out[$t->slug]))$out[$t->slug]=$t;else $out[$t->slug]->count+=$t->count; }
    return array_values($out);
}
function get_term($term, $taxonomy='', $output=OBJECT, $filter='raw') {
    if($term instanceof WP_Term)$t=$term;
    elseif(is_object($term))$t=new WP_Term($term);
    else{ $id=(int)$term;$t=null;
        foreach($taxonomy!==''?[$taxonomy]:array_keys($GLOBALS['wp_taxonomies']) as $tx)foreach(elvado_wp_all_terms($tx) as $c)if((int)$c->term_id===$id){$t=$c;break 2;}
    }
    if(!$t)return null;$t=apply_filters('get_term',$t,$t->taxonomy);
    if($output===ARRAY_A)return $t->to_array();if($output===ARRAY_N)return array_values($t->to_array());return $t;
}
function get_term_by($field, $value, $taxonomy='', $output=OBJECT, $filter='raw') {
    $field=in_array($field,['id','ID','term_id'],true)?'term_id':$field;
    foreach($taxonomy!==''?[$taxonomy]:array_keys($GLOBALS['wp_taxonomies']) as $tx)foreach(elvado_wp_all_terms($tx) as $t){
        $v=match($field){'slug'=>$t->slug,'name'=>$t->name,'term_taxonomy_id'=>$t->term_taxonomy_id,default=>$t->term_id};
        if((string)$v===(string)$value||($field==='slug'&&$v===sanitize_title((string)$value)))return $output===ARRAY_A?$t->to_array():$t;
    }
    return false;
}
function get_terms($args=[], $deprecated='') {
    if(is_string($args)||(is_array($args)&&isset($args[0]))){ $args=['taxonomy'=>$args]; }
    $r=wp_parse_args($args,['taxonomy'=>'category','orderby'=>'name','order'=>'ASC','hide_empty'=>true,'include'=>[],'exclude'=>[],'number'=>0,'offset'=>0,'fields'=>'all','name'=>'','slug'=>'','search'=>'','parent'=>'','object_ids'=>null,'name__like'=>'','get'=>'','childless'=>false]);
    $r=array_merge($r,['hide_empty'=>$r['hide_empty']]);
    $tax=(array)$r['taxonomy'];$terms=[];foreach($tax as $tx){ if(taxonomy_exists($tx))$terms=array_merge($terms,elvado_wp_all_terms($tx)); }
    $inc=wp_parse_id_list($r['include']);$exc=wp_parse_id_list($r['exclude']);
    if($r['object_ids']!==null){ $ids=array_map('intval',(array)$r['object_ids']);$allowed=[];foreach($ids as $oid)foreach($tax as $tx)foreach((array)wp_get_object_terms($oid,$tx) as $t)$allowed[$t->term_id]=1;$terms=array_filter($terms,fn($t)=>isset($allowed[$t->term_id])); }
    $terms=array_values(array_filter($terms,function($t) use($r,$inc,$exc){
        if($inc&&!in_array((int)$t->term_id,$inc,true))return false;if($exc&&in_array((int)$t->term_id,$exc,true))return false;
        if($r['hide_empty']&&$r['get']!=='all'&&(int)$t->count<1)return false;
        if($r['name']!==''&&!in_array($t->name,(array)$r['name'],true))return false;
        if($r['slug']!==''&&!in_array($t->slug,(array)$r['slug'],true))return false;
        if($r['search']!==''&&stripos($t->name.' '.$t->slug,(string)$r['search'])===false)return false;
        if($r['name__like']!==''&&stripos($t->name,(string)$r['name__like'])===false)return false;
        if($r['parent']!==''&&(int)$t->parent!==(int)$r['parent'])return false;
        return true;
    }));
    $ob=(string)$r['orderby'];$key=match($ob){'count'=>'count','id','term_id'=>'term_id','slug'=>'slug','parent'=>'parent',default=>'name'};
    usort($terms,fn($a,$b)=>strtoupper((string)$r['order'])==='DESC'?(is_numeric($a->$key)?$b->$key<=>$a->$key:strcasecmp((string)$b->$key,(string)$a->$key)):(is_numeric($a->$key)?$a->$key<=>$b->$key:strcasecmp((string)$a->$key,(string)$b->$key)));
    if((int)$r['number']>0)$terms=array_slice($terms,(int)$r['offset'],(int)$r['number']);
    $f=(string)$r['fields'];
    if($f==='ids'||$f==='tt_ids')$terms=array_map(fn($t)=>(int)($f==='ids'?$t->term_id:$t->term_taxonomy_id),$terms);
    elseif($f==='names')$terms=array_map(fn($t)=>$t->name,$terms);elseif($f==='slugs')$terms=array_map(fn($t)=>$t->slug,$terms);elseif($f==='count')return count($terms);
    elseif($f==='id=>name')$terms=array_column(array_map(fn($t)=>['i'=>$t->term_id,'n'=>$t->name],$terms),'n','i');
    return apply_filters('get_terms',$terms,$tax,$r,null);
}
function wp_count_terms($args=[], $deprecated='') { $a=is_array($args)?$args:['taxonomy'=>$args];return count(get_terms($a+['hide_empty'=>false])); }
function term_exists($term, $taxonomy='', $parent=null) {
    $f=is_numeric($term)?'term_id':'slug';$t=get_term_by($f,$term,$taxonomy)?:get_term_by('name',$term,$taxonomy);
    if(!$t)return $taxonomy===''?null:null;return ['term_id'=>(int)$t->term_id,'term_taxonomy_id'=>(int)$t->term_taxonomy_id];
}
function category_exists($cat_name, $category_parent=null) { $t=term_exists($cat_name,'category');return $t?$t['term_id']:null; }
function wp_insert_term($term, $taxonomy, $args=[]) {
    global $wpdb;if(!taxonomy_exists($taxonomy))return new WP_Error('invalid_taxonomy','Ungültige Taxonomie.');
    if(!$wpdb||!$wpdb->ready)return new WP_Error('no_database','Keine Datenbank verfügbar.');elvado_wp_install_schema();
    $term=trim(wp_unslash((string)$term));if($term==='')return new WP_Error('empty_term_name','Ein Name ist erforderlich.');
    $a=wp_parse_args($args,['alias_of'=>'','description'=>'','parent'=>0,'slug'=>'']);$slug=$a['slug']!==''?sanitize_title($a['slug']):sanitize_title($term);
    $ex=get_term_by('slug',$slug,$taxonomy);if($ex){ return new WP_Error('term_exists','Ein Begriff mit diesem Namen existiert bereits.',(int)$ex->term_id); }
    $wpdb->insert($wpdb->terms,['name'=>$term,'slug'=>$slug,'term_group'=>0]);$tid=(int)$wpdb->insert_id;
    $wpdb->insert($wpdb->term_taxonomy,['term_id'=>$tid,'taxonomy'=>$taxonomy,'description'=>$a['description'],'parent'=>(int)$a['parent'],'count'=>0]);$ttid=(int)$wpdb->insert_id;
    do_action('created_term',$tid,$ttid,$taxonomy,$a);do_action("created_$taxonomy",$tid,$ttid,$a);
    return ['term_id'=>$tid,'term_taxonomy_id'=>$ttid];
}
function wp_update_term($term_id, $taxonomy, $args=[]) {
    global $wpdb;$t=get_term((int)$term_id,$taxonomy);if(!$t)return new WP_Error('invalid_term','Ungültiger Begriff.');
    if((int)$term_id>=ELVADO_WP_ID_CAT_BASE&&(int)$term_id<ELVADO_WP_ID_CAT_BASE+2000000)return new WP_Error('cms_term','Kategorien und Schlagwörter der CMS-Beiträge werden im CMS bearbeitet.');
    $a=wp_parse_args($args,['name'=>$t->name,'slug'=>$t->slug,'description'=>$t->description,'parent'=>$t->parent]);
    $wpdb->update($wpdb->terms,['name'=>$a['name'],'slug'=>sanitize_title($a['slug'])],['term_id'=>(int)$term_id]);
    $wpdb->update($wpdb->term_taxonomy,['description'=>$a['description'],'parent'=>(int)$a['parent']],['term_id'=>(int)$term_id,'taxonomy'=>$taxonomy]);
    do_action('edited_term',(int)$term_id,(int)$t->term_taxonomy_id,$taxonomy,$a);do_action("edited_$taxonomy",(int)$term_id,(int)$t->term_taxonomy_id,$a);
    return ['term_id'=>(int)$term_id,'term_taxonomy_id'=>(int)$t->term_taxonomy_id];
}
function wp_delete_term($term, $taxonomy, $args=[]) {
    global $wpdb;$t=get_term((int)$term,$taxonomy);if(!$t)return false;if((int)$term>=ELVADO_WP_ID_CAT_BASE&&(int)$term<ELVADO_WP_ID_CAT_BASE+2000000)return false;
    do_action('pre_delete_term',$term,$taxonomy);
    $wpdb->delete($wpdb->term_relationships,['term_taxonomy_id'=>(int)$t->term_taxonomy_id]);$wpdb->delete($wpdb->term_taxonomy,['term_taxonomy_id'=>(int)$t->term_taxonomy_id]);$wpdb->delete($wpdb->terms,['term_id'=>(int)$term]);
    do_action('delete_term',$term,(int)$t->term_taxonomy_id,$taxonomy,$t,[]);do_action('deleted_term',$term,(int)$t->term_taxonomy_id,$taxonomy,$t,[]);
    return true;
}
/** Begriffe eines Objekts (Beitrag) – CMS-Beiträge: virtuelle Begriffe, sonst Tabelle. */
function wp_get_object_terms($object_ids, $taxonomies, $args=[]) {
    global $wpdb;$a=wp_parse_args($args,['orderby'=>'name','order'=>'ASC','fields'=>'all']);$ids=array_map('intval',(array)$object_ids);$out=[];
    foreach((array)$taxonomies as $tx){
        foreach($ids as $oid){
            $p=elvado_wp_cms_find_post($oid);
            if($p){ foreach(elvado_wp_cms_post_terms($p,$tx) as $t)$out[$t->term_id]=$t; }
            if($wpdb&&$wpdb->ready&&elvado_wp_db_ready()){
                $rows=$wpdb->get_results($wpdb->prepare("SELECT t.term_id, t.name, t.slug, t.term_group, tt.term_taxonomy_id, tt.taxonomy, tt.description, tt.parent, tt.count FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy = %s AND tr.object_id = %d",$tx,$oid));
                foreach((array)$rows as $r)$out[(int)$r->term_id]=elvado_wp_term_row_to_obj($r);
            }
        }
    }
    $terms=array_values($out);$ob=$a['orderby'];$key=match($ob){'count'=>'count','term_id','id'=>'term_id','slug'=>'slug',default=>'name'};
    usort($terms,fn($x,$y)=>strtoupper($a['order'])==='DESC'?strcasecmp((string)$y->$key,(string)$x->$key):strcasecmp((string)$x->$key,(string)$y->$key));
    switch($a['fields']){ case 'ids':return array_map(fn($t)=>(int)$t->term_id,$terms);case 'names':return array_map(fn($t)=>$t->name,$terms);case 'slugs':return array_map(fn($t)=>$t->slug,$terms);case 'tt_ids':return array_map(fn($t)=>(int)$t->term_taxonomy_id,$terms); }
    return $terms;
}
function elvado_wp_db_ready(): bool { global $wpdb;static $ok=null;if($ok===null)$ok=(bool)($wpdb&&$wpdb->ready&&elvado_wp_install_schema());return $ok; }
function wp_get_post_terms($post_id=0, $taxonomy='post_tag', $args=[]) { return wp_get_object_terms($post_id,$taxonomy,$args); }
function wp_get_post_categories($post_id=0, $args=[]) { return wp_get_object_terms($post_id,'category',wp_parse_args($args,['fields'=>'ids'])); }
function wp_get_post_tags($post_id=0, $args=[]) { return wp_get_object_terms($post_id,'post_tag',$args); }
function get_the_terms($post, $taxonomy) { $p=get_post($post);if(!$p)return false;$t=wp_get_object_terms($p->ID,$taxonomy);return $t?:false; }
function get_the_category($post_id=false) { $p=get_post($post_id);if(!$p)return [];$t=wp_get_object_terms($p->ID,'category');foreach($t as $c){$c->cat_ID=$c->term_id;$c->cat_name=$c->name;$c->category_nicename=$c->slug;$c->category_description=$c->description;$c->category_parent=$c->parent;}return $t; }
function get_the_tags($post_id=0) { $p=get_post($post_id);$t=$p?wp_get_object_terms($p->ID,'post_tag'):[];return $t?:false; }
function has_term($term='', $taxonomy='', $post=null) { $p=get_post($post);if(!$p)return false;$have=wp_get_object_terms($p->ID,$taxonomy?:array_keys(get_object_taxonomies($p->post_type)));if(!$term)return (bool)$have;foreach($have as $t)foreach((array)$term as $x)if($t->term_id==$x||$t->slug===$x||$t->name===$x)return true;return false; }
function has_category($category='', $post=null) { return has_term($category,'category',$post); }
function has_tag($tag='', $post=null) { return has_term($tag,'post_tag',$post); }
function wp_set_object_terms($object_id, $terms, $taxonomy, $append=false) {
    if(($cmsRes=elvado_wp_cms_try_set_terms((int)$object_id,$terms,(string)$taxonomy,(bool)$append))!==null)return $cmsRes;   // Schreibbrücke: Kategorie/Schlagwörter eines CMS-Beitrags
    global $wpdb;if(!taxonomy_exists($taxonomy))return new WP_Error('invalid_taxonomy','Ungültige Taxonomie.');if(!$wpdb||!$wpdb->ready)return new WP_Error('no_database','Keine Datenbank verfügbar.');elvado_wp_install_schema();
    $object_id=(int)$object_id;$terms=array_filter((array)$terms,fn($t)=>$t!==''&&$t!==null);$ttids=[];
    foreach($terms as $t){
        $tt=null;
        if(is_numeric($t)){ $x=get_term((int)$t,$taxonomy);if($x)$tt=(int)$x->term_taxonomy_id; }
        if(!$tt){ $x=get_term_by('slug',sanitize_title((string)$t),$taxonomy)?:get_term_by('name',(string)$t,$taxonomy);
            if(!$x||(int)$x->term_id>=ELVADO_WP_ID_CAT_BASE&&(int)$x->term_id<ELVADO_WP_ID_CAT_BASE+2000000){ $r=wp_insert_term((string)$t,$taxonomy);if(is_wp_error($r)){ $d=$r->get_error_data();$x=$d?get_term((int)$d,$taxonomy):null; if(!$x)continue; $tt=(int)$x->term_taxonomy_id; } else $tt=(int)$r['term_taxonomy_id']; }
            else $tt=(int)$x->term_taxonomy_id; }
        $ttids[]=$tt;
    }
    $old=$wpdb->get_col($wpdb->prepare("SELECT tr.term_taxonomy_id FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tr.object_id = %d AND tt.taxonomy = %s",$object_id,$taxonomy));
    $old=array_map('intval',(array)$old);
    if(!$append)foreach(array_diff($old,$ttids) as $rm){ $wpdb->delete($wpdb->term_relationships,['object_id'=>$object_id,'term_taxonomy_id'=>$rm]);$wpdb->query($wpdb->prepare("UPDATE {$wpdb->term_taxonomy} SET count = count - 1 WHERE term_taxonomy_id = %d AND count > 0",$rm)); }
    foreach(array_diff($ttids,$old) as $add){ $wpdb->insert($wpdb->term_relationships,['object_id'=>$object_id,'term_taxonomy_id'=>$add,'term_order'=>0]);$wpdb->query($wpdb->prepare("UPDATE {$wpdb->term_taxonomy} SET count = count + 1 WHERE term_taxonomy_id = %d",$add)); }
    do_action('set_object_terms',$object_id,$terms,$ttids,$taxonomy,$append,$old);
    return $ttids;
}
function wp_set_post_terms($post_id=0, $terms=[], $taxonomy='post_tag', $append=false) { if(is_string($terms))$terms=array_filter(array_map('trim',explode(',',$terms)));return wp_set_object_terms($post_id,$terms,$taxonomy,$append); }
function wp_set_post_categories($post_id=0, $post_categories=[], $append=false) { $c=array_filter(array_map('intval',(array)$post_categories));if(!$c)$c=[(int)get_option('default_category',0)];return wp_set_object_terms($post_id,array_filter($c),'category',$append); }
function wp_set_post_tags($post_id=0, $tags='', $append=false) { return wp_set_post_terms($post_id,$tags,'post_tag',$append); }
function wp_remove_object_terms($object_id, $terms, $taxonomy) { global $wpdb;foreach((array)$terms as $t){$x=is_numeric($t)?get_term((int)$t,$taxonomy):get_term_by('slug',$t,$taxonomy);if($x){$wpdb->delete($wpdb->term_relationships,['object_id'=>(int)$object_id,'term_taxonomy_id'=>(int)$x->term_taxonomy_id]);}}return true; }
function get_categories($args='') { return get_terms(wp_parse_args($args,['taxonomy'=>'category','hide_empty'=>false])); }
function get_category($category, $output=OBJECT, $filter='raw') { $c=get_term($category,'category',$output);return $c; }
function get_category_by_slug($slug) { return get_term_by('slug',$slug,'category'); }
function get_tags($args='') { return get_terms(wp_parse_args($args,['taxonomy'=>'post_tag','hide_empty'=>false])); }
function get_tag($tag, $output=OBJECT, $filter='raw') { return get_term($tag,'post_tag',$output); }
function get_cat_name($id) { $c=get_term((int)$id,'category');return $c?$c->name:''; }
function get_cat_ID($name) { $c=get_term_by('name',$name,'category');return $c?(int)$c->term_id:0; }
function get_term_children($term_id, $taxonomy) { $o=[];foreach(elvado_wp_all_terms($taxonomy) as $t)if((int)$t->parent===(int)$term_id)$o[]=(int)$t->term_id;return $o; }
function get_term_link($term, $taxonomy='') {
    $t=is_object($term)?$term:(is_numeric($term)?get_term((int)$term,$taxonomy):get_term_by('slug',$term,$taxonomy));if(!$t||is_wp_error($t))return new WP_Error('invalid_term','Ungültiger Begriff.');
    if(elvado_wp_link_structure()===''&&in_array($t->taxonomy,['category','post_tag'],true))return apply_filters('term_link',home_url('/?'.($t->taxonomy==='category'?'cat='.(int)$t->term_id:'tag='.rawurlencode((string)$t->slug))),$t,$t->taxonomy);   // einfache Link-Form
    $base=match($t->taxonomy){'category'=>trim((string)get_option('category_base',''),'/')?:'category','post_tag'=>trim((string)get_option('tag_base',''),'/')?:'tag',default=>$t->taxonomy};
    return apply_filters('term_link',elvado_wp_link_wrap(home_url('/'.$base.'/'.$t->slug.'/')),$t,$t->taxonomy);
}
function get_category_link($category) { $l=get_term_link(is_object($category)?$category:(int)$category,'category');return is_wp_error($l)?'':$l; }
function get_tag_link($tag) { $l=get_term_link(is_object($tag)?$tag:(int)$tag,'post_tag');return is_wp_error($l)?'':$l; }
function get_term_field($field, $term, $taxonomy='', $context='display') { $t=get_term($term,$taxonomy);return $t&&isset($t->$field)?$t->$field:''; }
function get_the_taxonomies($post=0, $args=[]) { $p=get_post($post);$o=[];if($p)foreach(get_object_taxonomies($p->post_type) as $tx){$t=get_the_terms($p,$tx);if($t)$o[$tx]=implode(', ',wp_list_pluck($t,'name'));}return $o; }
function wp_tag_cloud($args='') { return ''; }
function wp_list_categories($args='') { return ''; }
function _update_term_count($terms, $taxonomy) { return true; }
function wp_update_term_count($terms, $taxonomy, $do_deferred=false) { return true; }
function clean_term_cache($ids, $taxonomy='', $clean_taxonomy=true) {}
function clean_post_cache($post) { $p=is_object($post)?$post->ID:(int)$post;elvado_wp_post_cache_clear($p); }
function clean_object_term_cache($ids, $object_type) {}
function update_post_caches(&$posts, $post_type='post', $update_term_cache=true, $update_meta_cache=true) {}
function update_postmeta_cache($post_ids) { return []; }
function _prime_post_caches($ids, $update_term_cache=true, $update_meta_cache=true) {}

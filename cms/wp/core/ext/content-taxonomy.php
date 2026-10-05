<?php
// Ergänzende Begriffs-Funktionen: Taxonomien (wp-includes/taxonomy.php), Kategorien, Kategorie-Vorlagen, Beitragsformate, Meta-Registrierung, Rechte.
// Geteilte Begriffe (shared terms) kennt diese Schicht nicht: Jede Taxonomie hat eigene term_id, die „split“-Funktionen sind deshalb schlank.

/* ───────── Taxonomien: Grundlagen ───────── */
if(!function_exists('create_initial_taxonomies')){ function create_initial_taxonomies() { if(!taxonomy_exists('category'))rrw_wp_register_default_types(); } }
if(!function_exists('get_tax_sql')){
    function get_tax_sql($tax_query, $primary_table, $primary_id_column) { $q=new WP_Tax_Query($tax_query);return $q->get_sql($primary_table,$primary_id_column); }
}
if(!function_exists('wp_check_term_meta_support_prefilter')){ function wp_check_term_meta_support_prefilter($check) { return $check; } }
if(!function_exists('has_term_meta')){
    function has_term_meta($term_id) {
        global $wpdb;$c=wp_check_term_meta_support_prefilter(null);if(null!==$c)return $c;
        if(!$wpdb||!rrw_wp_db_ready())return [];
        return $wpdb->get_results($wpdb->prepare("SELECT meta_key, meta_value, meta_id, term_id FROM {$wpdb->termmeta} WHERE term_id = %d ORDER BY meta_key, meta_id",$term_id),ARRAY_A);
    }
}
if(!function_exists('unregister_term_meta')){ function unregister_term_meta($taxonomy, $meta_key) { return unregister_meta_key('term',$meta_key,$taxonomy); } }
if(!function_exists('wp_lazyload_term_meta')){ function wp_lazyload_term_meta($check, $term_id) { return $check; } }   // Term-Meta wird bei Bedarf gelesen
if(!function_exists('is_term_publicly_viewable')){
    function is_term_publicly_viewable($term) { $t=get_term($term);return $t&&!is_wp_error($t)&&is_taxonomy_viewable($t->taxonomy); }
}
if(!function_exists('wp_cache_set_terms_last_changed')){ function wp_cache_set_terms_last_changed() { wp_cache_set('last_changed',microtime(),'terms'); } }

/* ───────── Bereinigen ───────── */
if(!function_exists('sanitize_term_field')){
    function sanitize_term_field($field, $value, $term_id, $taxonomy, $context) {
        $ints=['parent','term_id','count','term_group','term_taxonomy_id','object_id'];
        if(in_array($field,$ints,true)){ $value=(int)$value;if($value<0)$value=0; }
        $context=strtolower((string)$context);
        if('raw'===$context)return $value;
        if('edit'===$context){
            $value=apply_filters("edit_term_{$field}",$value,$term_id,$taxonomy);$value=apply_filters("edit_{$taxonomy}_{$field}",$value,$term_id);
            $value='description'===$field?esc_textarea((string)$value):esc_attr((string)$value);
        }elseif('db'===$context){
            $value=apply_filters("pre_term_{$field}",$value,$taxonomy);$value=apply_filters("pre_{$taxonomy}_{$field}",$value);
            if('slug'===$field)$value=apply_filters('pre_category_nicename',$value);
        }elseif('rss'===$context){
            $value=apply_filters("term_{$field}_rss",$value,$taxonomy);$value=apply_filters("{$taxonomy}_{$field}_rss",$value);
        }else{
            $value=apply_filters("term_{$field}",$value,$term_id,$taxonomy,$context);$value=apply_filters("{$taxonomy}_{$field}",$value,$term_id,$context);
        }
        if('attribute'===$context)$value=esc_attr((string)$value);elseif('js'===$context)$value=esc_js((string)$value);
        if(in_array($field,$ints,true))$value=(int)$value;
        return $value;
    }
}
if(!function_exists('sanitize_term')){
    function sanitize_term($term, $taxonomy, $context='display') {
        $fields=['term_id','name','description','slug','count','parent','term_group','term_taxonomy_id','object_id'];
        $obj=is_object($term);$id=$obj?($term->term_id??0):($term['term_id']??0);
        foreach($fields as $f){
            if($obj){ if(isset($term->$f))$term->$f=sanitize_term_field($f,$term->$f,$id,$taxonomy,$context); }
            elseif(isset($term[$f]))$term[$f]=sanitize_term_field($f,$term[$f],$id,$taxonomy,$context);
        }
        if($obj)$term->filter=$context;else $term['filter']=$context;
        return $term;
    }
}
if(!function_exists('get_term_to_edit')){
    function get_term_to_edit($id, $taxonomy) { $t=get_term($id,$taxonomy);if(is_wp_error($t))return $t;if(!is_object($t))return '';return sanitize_term($t,$taxonomy,'edit'); }
}
if(!function_exists('sanitize_category')){ function sanitize_category($category, $context='display') { return sanitize_term($category,'category',$context); } }
if(!function_exists('sanitize_category_field')){ function sanitize_category_field($field, $value, $cat_id, $context) { return sanitize_term_field($field,$value,$cat_id,'category',$context); } }

/* ───────── Hierarchie ───────── */
if(!function_exists('get_ancestors')){
    function get_ancestors($object_id=0, $object_type='', $resource_type='') {
        $object_id=(int)$object_id;$a=[];
        if($object_id){
            if(!$resource_type){ if(is_taxonomy_hierarchical($object_type))$resource_type='taxonomy';elseif(post_type_exists($object_type))$resource_type='post_type'; }
            if('taxonomy'===$resource_type){
                $t=get_term($object_id,$object_type);
                while($t&&!is_wp_error($t)&&!empty($t->parent)&&!in_array((int)$t->parent,$a,true)){ $a[]=(int)$t->parent;$t=get_term((int)$t->parent,$object_type); }
            }elseif('post_type'===$resource_type)$a=get_post_ancestors($object_id);
        }
        return apply_filters('get_ancestors',$a,$object_id,$object_type,$resource_type);
    }
}
if(!function_exists('wp_get_term_taxonomy_parent_id')){
    function wp_get_term_taxonomy_parent_id($term_id, $taxonomy) { $t=get_term($term_id,$taxonomy);return $t&&!is_wp_error($t)?(int)$t->parent:false; }
}
if(!function_exists('wp_check_term_hierarchy_for_loops')){
    /** 0, wenn $parent den Begriff selbst oder einen Nachfahren bezeichnet (Schleife), sonst $parent. */
    function wp_check_term_hierarchy_for_loops($parent, $term_id, $taxonomy) {
        if(!$parent||(int)$parent===(int)$term_id)return 0;
        $seen=[];$cur=(int)$parent;
        while($cur&&!isset($seen[$cur])){ if($cur===(int)$term_id)return 0;$seen[$cur]=1;$cur=(int)wp_get_term_taxonomy_parent_id($cur,$taxonomy); }
        return (int)$parent;
    }
}
if(!function_exists('term_is_ancestor_of')){
    function term_is_ancestor_of($term1, $term2, $taxonomy) {
        if(!is_object($term1)||!isset($term1->term_id))$term1=get_term($term1,$taxonomy);
        if(!is_object($term2)||!isset($term2->term_id))$term2=get_term($term2,$taxonomy);
        if(!$term1||!$term2||is_wp_error($term1)||is_wp_error($term2))return false;
        return in_array((int)$term1->term_id,get_ancestors((int)$term2->term_id,$taxonomy,'taxonomy'),true);
    }
}
if(!function_exists('_get_term_children')){
    /** Alle Nachfahren von $term_id aus der Liste $terms (Objekte oder IDs), tief zuerst. */
    function _get_term_children($term_id, $terms, $taxonomy='category', &$ancestors=[]) {
        if(empty($terms))return [];
        $map=[];$byId=[];
        foreach((array)$terms as $t){ $o=is_object($t)?$t:get_term($t,$taxonomy);if(!$o||is_wp_error($o))continue;$byId[(int)$o->term_id]=[$t,$o];$map[(int)$o->parent][]=(int)$o->term_id; }
        $out=[];$seen=$ancestors?:[(int)$term_id=>1];
        $walk=function($pid) use(&$walk,&$out,&$seen,$map,$byId){ foreach($map[$pid]??[] as $c){ if(isset($seen[$c]))continue;$seen[$c]=1;$out[]=is_object($byId[$c][0])?$byId[$c][1]:$c;$walk($c); } };
        $walk((int)$term_id);
        return $out;
    }
}
if(!function_exists('_pad_term_counts')){
    // Addiert die Zähler der Unterbegriffe zum Oberbegriff (einfache Summe, ohne Doppelzählung von Beiträgen in mehreren Unterbegriffen).
    function _pad_term_counts(&$terms, $taxonomy) {
        if(!is_taxonomy_hierarchical($taxonomy)||empty($terms))return;
        $by=[];$kids=[];foreach($terms as $t)if(is_object($t)){ $by[(int)$t->term_id]=$t;$kids[(int)$t->parent][]=(int)$t->term_id; }
        $own=[];foreach($by as $id=>$t)$own[$id]=(int)$t->count;$seen=[];
        $sum=function($id) use(&$sum,&$seen,$kids,$own){ if(isset($seen[$id]))return 0;$seen[$id]=1;$n=$own[$id]??0;foreach($kids[$id]??[] as $c)$n+=$sum($c);return $n; };
        foreach($by as $id=>$t){ $seen=[];$t->count=$sum($id); }
    }
}
if(!function_exists('wp_unique_term_slug')){
    function wp_unique_term_slug($slug, $term, $args=[]) {
        $orig=$slug;$tax=$term->taxonomy??'';$id=(int)($term->term_id??0);
        $taken=function($s) use($tax,$id){ $t=get_term_by('slug',$s,$tax);return $t&&(int)$t->term_id!==$id; };
        if($taken($slug)){
            if(is_taxonomy_hierarchical($tax)&&!empty($term->parent)){ $p=get_term((int)$term->parent,$tax);if($p&&!is_wp_error($p)&&!$taken($s2=$slug.'-'.$p->slug))$slug=$s2; }
            if($taken($slug)){ $base=$orig;$i=2;while($taken($slug=$base.'-'.$i))$i++; }
        }
        return apply_filters('wp_unique_term_slug',$slug,$term,$orig);
    }
}

/* ───────── Zuordnung Objekt ↔ Begriffe ───────── */
if(!function_exists('wp_add_object_terms')){ function wp_add_object_terms($object_id, $terms, $taxonomy) { return wp_set_object_terms($object_id,$terms,$taxonomy,true); } }
if(!function_exists('wp_delete_object_term_relationships')){
    function wp_delete_object_term_relationships($object_id, $taxonomies) {
        global $wpdb;$oid=(int)$object_id;if(!$wpdb||!rrw_wp_db_ready())return;
        foreach((array)$taxonomies as $tax){
            $tt=array_map('intval',(array)wp_get_object_terms($oid,$tax,['fields'=>'tt_ids']));if(!$tt)continue;
            do_action('delete_term_relationships',$oid,$tt,$tax);
            foreach($tt as $x)if($wpdb->delete($wpdb->term_relationships,['object_id'=>$oid,'term_taxonomy_id'=>$x]))$wpdb->query($wpdb->prepare("UPDATE {$wpdb->term_taxonomy} SET count = count - 1 WHERE term_taxonomy_id = %d AND count > 0",$x));
            wp_cache_delete($oid,"{$tax}_relationships");clean_object_term_cache($oid,$tax);
            do_action('deleted_term_relationships',$oid,$tt,$tax);
        }
    }
}
if(!function_exists('is_object_in_term')){
    function is_object_in_term($object_id, $taxonomy, $terms=null) {
        $object_id=(int)$object_id;if(!$object_id)return new WP_Error('invalid_object','Ungültige Objekt-ID.');
        $have=wp_get_object_terms($object_id,$taxonomy);   // ohne Cache: der Kern leert ihn beim Ändern nicht
        if(is_wp_error($have))return $have;
        if(empty($have))return false;
        if(empty($terms))return !empty($have);
        $terms=(array)$terms;$ints=array_filter($terms,'is_int');$strs=$ints?array_diff($terms,$ints):$terms;
        foreach($have as $t){
            if($ints&&in_array((int)$t->term_id,$ints,true))return true;
            if($strs){
                $num=array_map('intval',array_filter($strs,'is_numeric'));
                if(in_array((int)$t->term_id,$num,true)||in_array($t->name,$strs)||in_array($t->slug,$strs))return true;
            }
        }
        return false;
    }
}
if(!function_exists('get_post_taxonomies')){ function get_post_taxonomies($post=0) { $p=get_post($post);return $p?get_object_taxonomies($p->post_type):[]; } }
if(!function_exists('the_taxonomies')){
    function the_taxonomies($args=[]) {
        $a=wp_parse_args($args,['post'=>0,'before'=>'','sep'=>' ','after'=>'']);
        echo $a['before'].implode($a['sep'],get_the_taxonomies($a['post'],$a)).$a['after'];
    }
}
if(!function_exists('wp_delete_category')){
    function wp_delete_category($cat_ID) {
        global $wpdb;$cat_ID=(int)$cat_ID;$default=(int)get_option('default_category');
        if($cat_ID===$default)return 0;
        $t=get_term($cat_ID,'category');if(!$t||is_wp_error($t))return false;
        $objs=[];
        if($wpdb&&rrw_wp_db_ready()){
            $objs=array_map('intval',(array)$wpdb->get_col($wpdb->prepare("SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d",(int)$t->term_taxonomy_id)));
            $wpdb->update($wpdb->term_taxonomy,['parent'=>(int)$t->parent],['parent'=>$cat_ID,'taxonomy'=>'category']);   // Unterkategorien rücken nach oben
        }
        $r=wp_delete_term($cat_ID,'category',['default'=>$default]);
        if($r&&$default>0&&get_term($default,'category'))foreach($objs as $o)if(!wp_get_object_terms($o,'category',['fields'=>'ids']))wp_set_object_terms($o,[$default],'category');
        return $r;
    }
}

/* ───────── Caches und Zähler ───────── */
if(!function_exists('clean_taxonomy_cache')){
    function clean_taxonomy_cache($taxonomy) { wp_cache_delete('all_ids',$taxonomy);wp_cache_delete('get',$taxonomy);wp_cache_set_terms_last_changed();do_action('clean_taxonomy_cache',$taxonomy); }
}
if(!function_exists('get_object_term_cache')){
    function get_object_term_cache($id, $taxonomy) {
        $ids=wp_cache_get($id,"{$taxonomy}_relationships",false,$found);if(!$found)return false;
        $o=[];foreach((array)$ids as $i){ $t=get_term((int)$i,$taxonomy);if($t)$o[]=$t; }return $o;
    }
}
if(!function_exists('update_object_term_cache')){
    function update_object_term_cache($object_ids, $object_type) {
        if(empty($object_ids))return;$ids=is_array($object_ids)?$object_ids:preg_split('/[\s,]+/',(string)$object_ids,-1,PREG_SPLIT_NO_EMPTY);
        foreach(get_object_taxonomies($object_type) as $tax)foreach($ids as $id)wp_cache_set((int)$id,wp_get_object_terms((int)$id,$tax,['fields'=>'ids']),"{$tax}_relationships");
    }
}
if(!function_exists('update_term_cache')){ function update_term_cache($terms, $taxonomy='') { foreach((array)$terms as $t)if(is_object($t))wp_cache_add($t->term_id,$t,'terms'); } }
if(!function_exists('_prime_term_caches')){ function _prime_term_caches($term_ids, $update_meta_cache=true) {} }   // Begriffe werden bei Bedarf gelesen
if(!function_exists('_update_post_term_count')){
    /** Zähler = veröffentlichte Beiträge (Anhänge: „inherit“ mit veröffentlichtem Elternbeitrag) der Typen der Taxonomie. */
    function _update_post_term_count($terms, $taxonomy) {
        global $wpdb;if(!$wpdb||!rrw_wp_db_ready())return;
        $types=array_values(array_filter((array)$taxonomy->object_type,'post_type_exists'));if(!$types)return;
        $in=implode(',',array_map(fn($t)=>"'".esc_sql($t)."'",array_diff($types,['attachment'])));
        foreach((array)$terms as $tt){
            $tt=(int)$tt;$n=0;
            if(in_array('attachment',$types,true))$n+=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE tr.term_taxonomy_id = %d AND p.post_type = 'attachment' AND (p.post_status = 'publish' OR (p.post_status = 'inherit' AND p.post_parent > 0 AND (SELECT q.post_status FROM {$wpdb->posts} q WHERE q.ID = p.post_parent) = 'publish'))",$tt));
            if($in!=='')$n+=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE tr.term_taxonomy_id = %d AND p.post_status = 'publish' AND p.post_type IN ($in)",$tt));
            do_action('edit_term_taxonomy',$tt,$taxonomy->name);$wpdb->update($wpdb->term_taxonomy,['count'=>$n],['term_taxonomy_id'=>$tt]);do_action('edited_term_taxonomy',$tt,$taxonomy->name);
        }
    }
}
if(!function_exists('_update_generic_term_count')){
    function _update_generic_term_count($terms, $taxonomy) {
        global $wpdb;if(!$wpdb||!rrw_wp_db_ready())return;
        foreach((array)$terms as $tt){ $tt=(int)$tt;$n=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d",$tt));
            do_action('edit_term_taxonomy',$tt,$taxonomy->name);$wpdb->update($wpdb->term_taxonomy,['count'=>$n],['term_taxonomy_id'=>$tt]);do_action('edited_term_taxonomy',$tt,$taxonomy->name); }
    }
}

/* ───────── Geteilte Begriffe (hier ohne Aufteilung nötig) ───────── */
if(!function_exists('_split_shared_term')){ function _split_shared_term($term_id, $term_taxonomy_id, $record=true) { return $term_id; } }   // jede Taxonomie hat eigene term_id
if(!function_exists('_wp_batch_split_terms')){ function _wp_batch_split_terms() {} }
if(!function_exists('_wp_check_for_scheduled_split_terms')){ function _wp_check_for_scheduled_split_terms() {} }
if(!function_exists('wp_get_split_terms')){ function wp_get_split_terms($old_term_id) { $s=get_option('_split_terms',[]);return is_array($s)&&isset($s[$old_term_id])?$s[$old_term_id]:[]; } }
if(!function_exists('wp_term_is_shared')){
    function wp_term_is_shared($term_id) {
        global $wpdb;if(get_option('finished_splitting_shared_terms')||!$wpdb||!rrw_wp_db_ready())return false;
        return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE term_id = %d",$term_id))>1;
    }
}
if(!function_exists('_wp_check_split_default_terms')){
    function _wp_check_split_default_terms($term_id, $new_term_id, $term_taxonomy_id, $taxonomy) {
        if('category'!==$taxonomy)return;
        foreach(['default_category','default_link_category','default_email_category'] as $o)if((int)get_option($o,-1)===(int)$term_id)update_option($o,$new_term_id);
    }
}
if(!function_exists('_wp_check_split_terms_in_menus')){
    function _wp_check_split_terms_in_menus($term_id, $new_term_id, $term_taxonomy_id, $taxonomy) {
        global $wpdb;if(!$wpdb||!rrw_wp_db_ready())return;
        $ids=$wpdb->get_col($wpdb->prepare("SELECT m1.post_id FROM {$wpdb->postmeta} m1 INNER JOIN {$wpdb->postmeta} m2 ON m2.post_id = m1.post_id INNER JOIN {$wpdb->postmeta} m3 ON m3.post_id = m1.post_id WHERE m1.meta_key = '_menu_item_type' AND m1.meta_value = 'taxonomy' AND m2.meta_key = '_menu_item_object' AND m2.meta_value = %s AND m3.meta_key = '_menu_item_object_id' AND m3.meta_value = %d",$taxonomy,$term_id));
        foreach((array)$ids as $id)update_post_meta((int)$id,'_menu_item_object_id',$new_term_id,$term_id);
    }
}
if(!function_exists('_wp_check_split_nav_menu_terms')){
    function _wp_check_split_nav_menu_terms($term_id, $new_term_id, $term_taxonomy_id, $taxonomy) {
        if('nav_menu'!==$taxonomy||!function_exists('get_nav_menu_locations')||!function_exists('set_theme_mod'))return;
        $loc=get_nav_menu_locations();foreach($loc as $k=>$m)if((int)$m===(int)$term_id)$loc[$k]=$new_term_id;
        set_theme_mod('nav_menu_locations',$loc);
    }
}

/* ───────── Kategorien (wp-includes/category.php) ───────── */
if(!function_exists('_make_cat_compat')){
    function _make_cat_compat(&$category) {
        if(is_object($category)&&!is_wp_error($category)){
            $category->cat_ID=$category->term_id;$category->category_count=$category->count;$category->category_description=$category->description;
            $category->cat_name=$category->name;$category->category_nicename=$category->slug;$category->category_parent=$category->parent;
        }elseif(is_array($category)&&isset($category['term_id'])){
            $category['cat_ID']=$category['term_id'];$category['category_count']=$category['count']??0;$category['category_description']=$category['description']??'';
            $category['cat_name']=$category['name']??'';$category['category_nicename']=$category['slug']??'';$category['category_parent']=$category['parent']??0;
        }
    }
}
if(!function_exists('cat_is_ancestor_of')){ function cat_is_ancestor_of($cat1, $cat2) { return term_is_ancestor_of($cat1,$cat2,'category'); } }
if(!function_exists('clean_category_cache')){ function clean_category_cache($id) { clean_term_cache($id,'category'); } }
if(!function_exists('get_category_by_path')){
    function get_category_by_path($category_path, $full_match=true, $output=OBJECT) {
        $path=str_replace(['%2F','%20'],['/',' '],rawurlencode(urldecode((string)$category_path)));
        $paths='/'.trim($path,'/');$leaf=sanitize_title(basename($paths));$full='';
        foreach(explode('/',$paths) as $d)$full.=($d?'/':'').sanitize_title($d);
        $cats=get_terms(['taxonomy'=>'category','get'=>'all','slug'=>$leaf]);if(empty($cats))return null;
        foreach($cats as $c){
            $p='/'.$leaf;$cur=$c;
            while($cur->parent&&(int)$cur->parent!==(int)$cur->term_id){ $cur=get_term((int)$cur->parent,'category');if(!$cur||is_wp_error($cur))return $cur;$p='/'.$cur->slug.$p; }
            if($p===$full){ $r=get_term($c->term_id,'category',$output);_make_cat_compat($r);return $r; }
        }
        if(!$full_match){ $r=get_term(reset($cats)->term_id,'category',$output);_make_cat_compat($r);return $r; }
        return null;
    }
}

/* ───────── Kategorie-Vorlagen (wp-includes/category-template.php) ───────── */
if(!function_exists('in_category')){ function in_category($category, $post=null) { return empty($category)?false:has_category($category,$post); } }
if(!function_exists('category_description')){
    function category_description($category=0) {
        if(!$category){ $o=get_queried_object();if($o&&isset($o->term_id))$category=$o->term_id; }
        return term_description($category,'category');
    }
}
if(!function_exists('tag_description')){
    function tag_description($tag=0) {
        if(!$tag){ $o=get_queried_object();if($o&&isset($o->term_id))$tag=$o->term_id; }
        return term_description($tag,'post_tag');
    }
}
if(!function_exists('default_topic_count_scale')){ function default_topic_count_scale($count) { return (int)round(log10($count+1)*100); } }
if(!function_exists('_wp_object_name_sort_cb')){ function _wp_object_name_sort_cb($a, $b) { return strnatcasecmp($a->name,$b->name); } }
if(!function_exists('_wp_object_count_sort_cb')){ function _wp_object_count_sort_cb($a, $b) { return $a->count<=>$b->count; } }
if(!function_exists('wp_generate_tag_cloud')){
    function wp_generate_tag_cloud($tags, $args='') {
        $d=['smallest'=>8,'largest'=>22,'unit'=>'pt','number'=>0,'format'=>'flat','separator'=>"\n",'orderby'=>'name','order'=>'ASC','topic_count_text'=>null,'topic_count_text_callback'=>null,'topic_count_scale_callback'=>'default_topic_count_scale','filter'=>1,'show_count'=>0];
        $args=wp_parse_args($args,$d);$ret='array'===$args['format']?[]:'';
        if(empty($tags))return $ret;
        if(isset($args['topic_count_text']))$noop=$args['topic_count_text'];
        elseif(!empty($args['topic_count_text_callback']))$noop=null;
        else $noop=_n_noop('%s Eintrag','%s Einträge');
        $sorted=apply_filters('tag_cloud_sort',$tags,$args);
        if(empty($sorted))return $ret;
        if($sorted!==$tags)$tags=$sorted;
        elseif('RAND'===$args['order'])shuffle($tags);
        else{ uasort($tags,'name'===$args['orderby']?'_wp_object_name_sort_cb':'_wp_object_count_sort_cb');if('DESC'===$args['order'])$tags=array_reverse($tags,true); }
        if($args['number']>0)$tags=array_slice($tags,0,(int)$args['number']);
        $counts=[];$real=[];foreach((array)$tags as $k=>$t){ $real[$k]=$t->count;$counts[$k]=call_user_func($args['topic_count_scale_callback'],$t->count); }
        $min=min($counts);$spread=max($counts)-$min;if($spread<=0)$spread=1;
        $fs=$args['largest']-$args['smallest'];if($fs<0)$fs=1;$step=$fs/$spread;
        $aria=!isset($args['topic_count_text'])&&!isset($args['topic_count_text_callback'])&&$args['smallest']!==$args['largest'];
        $data=[];
        foreach($tags as $k=>$t){
            $id=$t->id??$k;$link=$t->link??(function_exists('get_term_link')&&isset($t->taxonomy)&&!is_wp_error($l=get_term_link($t))?$l:'#');
            $fc=$noop?sprintf(translate_nooped_plural($noop,$real[$k]),number_format_i18n($real[$k])):call_user_func($args['topic_count_text_callback'],$real[$k],$t,$args);
            $data[]=['id'=>$id,'url'=>$link,'role'=>'#'!==$link?'':' role="button"','name'=>$t->name,'formatted_count'=>$fc,'slug'=>$t->slug,'real_count'=>$real[$k],'class'=>'tag-cloud-link tag-link-'.$id,
                'font_size'=>$args['smallest']+($counts[$k]-$min)*$step,'aria_label'=>$aria?sprintf(' aria-label="%1$s (%2$s)"',esc_attr($t->name),esc_attr($fc)):'','show_count'=>$args['show_count']?'<span class="tag-link-count"> ('.$real[$k].')</span>':''];
        }
        $data=apply_filters('wp_generate_tag_cloud_data',$data);$a=[];
        foreach($data as $k=>$x)$a[]=sprintf('<a href="%1$s"%2$s class="%3$s" style="font-size: %4$s;"%5$s>%6$s%7$s</a>',esc_url($x['url']),$x['role'],esc_attr($x['class'].' tag-link-position-'.($k+1)),esc_attr(str_replace(',','.',(string)$x['font_size']).$args['unit']),$x['aria_label'],esc_html($x['name']),$x['show_count']);
        switch($args['format']){
            case 'array': $ret=&$a;break;
            case 'list': $ret="<ul class='wp-tag-cloud' role='list'>\n\t<li>".implode("</li>\n\t<li>",$a)."</li>\n</ul>\n";break;
            default: $ret=implode($args['separator'],$a);
        }
        return $args['filter']?apply_filters('wp_generate_tag_cloud',$ret,$tags,$args):$ret;
    }
}
if(!function_exists('rrw_wp_x_term_tree')){
    /** [Eltern-ID => [Begriffe]] aus einer Liste; Begriffe ohne Eltern in der Liste gelten als oberste Ebene (Schlüssel 0). */
    function rrw_wp_x_term_tree(array $terms, string $parent='parent', string $id='term_id'): array {
        $ids=[];foreach($terms as $t)$ids[(int)$t->$id]=1;$m=[];
        foreach($terms as $t){ $p=(int)$t->$parent;$m[($p&&isset($ids[$p])&&$p!==(int)$t->$id)?$p:0][]=$t; }
        return $m;
    }
}
if(!function_exists('walk_category_tree')){
    function walk_category_tree(...$args) {
        $r=(array)($args[2]??[]);$w=$r['walker']??null;
        if($w instanceof Walker&&get_class($w)!=='Walker_Category')return $w->walk(...$args);
        $depth=(int)($args[1]??0);$map=rrw_wp_x_term_tree((array)($args[0]??[]));$list=($r['style']??'list')==='list';$cur=(int)($r['current_category']??0);
        $render=function($pid,$lvl) use(&$render,$map,$depth,$r,$list,$cur){
            $o='';
            foreach($map[$pid]??[] as $t){
                $l=get_term_link($t);$l=is_wp_error($l)?'#':$l;
                $title=!empty($r['use_desc_for_title'])&&$t->description!==''?$t->description:'';
                $a='<a href="'.esc_url($l).'"'.($title!==''?' title="'.esc_attr($title).'"':'').'>'.esc_html($t->name).'</a>'.(!empty($r['show_count'])?' ('.number_format_i18n($t->count).')':'');
                $kids=($depth==0||$depth>$lvl+1)&&!empty($map[(int)$t->term_id])?$render((int)$t->term_id,$lvl+1):'';
                if($list)$o.="\t<li class=\"cat-item cat-item-".(int)$t->term_id.((int)$t->term_id===$cur?' current-cat':'').'">'.$a.($kids!==''?"<ul class='children'>\n$kids</ul>\n":'')."</li>\n";
                else $o.=$a."<br />\n".$kids;
            }
            return $o;
        };
        return $render(0,0);
    }
}
if(!function_exists('walk_category_dropdown_tree')){
    function walk_category_dropdown_tree(...$args) {
        $r=(array)($args[2]??[]);$w=$r['walker']??null;
        if($w instanceof Walker&&get_class($w)!=='Walker_Category')return $w->walk(...$args);
        $depth=(int)($args[1]??0);$map=rrw_wp_x_term_tree((array)($args[0]??[]));$vf=$r['value_field']??'term_id';$sel=$r['selected']??0;
        $render=function($pid,$lvl) use(&$render,$map,$depth,$r,$vf,$sel){
            $o='';
            foreach($map[$pid]??[] as $t){
                $v=$t->$vf??$t->term_id;
                $o.="\t<option class=\"level-$lvl\" value=\"".esc_attr((string)$v).'"'.((string)$v===(string)$sel?' selected="selected"':'').'>'.str_repeat('&nbsp;&nbsp;&nbsp;',$lvl).esc_html($t->name).(!empty($r['show_count'])?'&nbsp;&nbsp;('.number_format_i18n($t->count).')':'')."</option>\n";
                if($depth==0||$depth>$lvl+1)$o.=$render((int)$t->term_id,$lvl+1);
            }
            return $o;
        };
        return $render(0,0);
    }
}

/* ───────── Beitragsformate ───────── */
if(!function_exists('get_post_format_strings')){
    function get_post_format_strings() { return ['standard'=>'Standard','aside'=>'Notiz','chat'=>'Chat','gallery'=>'Galerie','link'=>'Link','image'=>'Bild','quote'=>'Zitat','status'=>'Status','video'=>'Video','audio'=>'Audio']; }
}
if(!function_exists('get_post_format_slugs')){ function get_post_format_slugs() { $s=array_keys(get_post_format_strings());return array_combine($s,$s); } }
if(!function_exists('get_post_format_string')){
    function get_post_format_string($slug) { $s=get_post_format_strings();return !$slug?$s['standard']:($s[$slug]??''); }
}
if(!function_exists('has_post_format')){
    function has_post_format($post_format=[], $post=null) {
        $p=[];foreach((array)$post_format as $f)$p[]='post-format-'.sanitize_key($f);
        return has_term($p,'post_format',$post);
    }
}
if(!function_exists('get_post_format_link')){
    function get_post_format_link($format) { $t=get_term_by('slug','post-format-'.$format,'post_format');return $t&&!is_wp_error($t)?get_term_link($t):false; }
}
if(!function_exists('_post_format_request')){
    function _post_format_request($qvs) {
        if(empty($qvs['post_format']))return $qvs;
        $s=get_post_format_slugs();if(isset($s[$qvs['post_format']]))$qvs['post_format']='post-format-'.$s[$qvs['post_format']];
        $tax=get_taxonomy('post_format');if(!is_admin()&&$tax)$qvs['post_type']=$tax->object_type;
        return $qvs;
    }
}
if(!function_exists('_post_format_link')){
    function _post_format_link($link, $term, $taxonomy) {
        if('post_format'!==$taxonomy)return $link;
        return add_query_arg('post_format',str_replace('post-format-','',$term->slug),remove_query_arg('post_format',$link));
    }
}
if(!function_exists('_post_format_get_term')){
    function _post_format_get_term($term) { if(is_object($term)&&isset($term->slug))$term->name=get_post_format_string(str_replace('post-format-','',$term->slug));return $term; }
}
if(!function_exists('_post_format_get_terms')){
    function _post_format_get_terms($terms, $taxonomies, $args) {
        if(in_array('post_format',(array)$taxonomies,true)){
            if(isset($args['fields'])&&'names'===$args['fields'])foreach($terms as $i=>$n)$terms[$i]=get_post_format_string(str_replace('post-format-','',$n));
            else foreach($terms as $i=>$t)if(isset($t->taxonomy)&&'post_format'===$t->taxonomy)$terms[$i]=_post_format_get_term($t);
        }
        return $terms;
    }
}
if(!function_exists('_post_format_wp_get_object_terms')){
    function _post_format_wp_get_object_terms($terms) { foreach((array)$terms as $i=>$t)if(isset($t->taxonomy)&&'post_format'===$t->taxonomy)$terms[$i]=_post_format_get_term($t);return $terms; }
}

/* ───────── Meta: Registrierung und Bereinigung (wp-includes/meta.php) ───────── */
if(!class_exists('WP_Metadata_Lazyloader')){
    class WP_Metadata_Lazyloader {
        protected $pending_objects=[];
        public function queue_objects($object_type, $object_ids) { foreach((array)$object_ids as $i)$this->pending_objects[$object_type][$i]=1; }
        public function reset_queue($object_type) { unset($this->pending_objects[$object_type]); }
        public function lazyload_term_meta($check) { return $check; }
        public function lazyload_comment_meta($check) { return $check; }
    }
}
if(!function_exists('wp_metadata_lazyloader')){ function wp_metadata_lazyloader() { static $l=null;return $l=$l??new WP_Metadata_Lazyloader(); } }
if(!function_exists('get_metadata_default')){
    function get_metadata_default($meta_type, $meta_key, $single, $object_id=0) {
        $v=$single?'':[];$v=apply_filters("default_{$meta_type}_metadata",$v,$object_id,$meta_key,$single,$meta_type);
        if(!$single&&$v!==[]&&!wp_is_numeric_array($v))$v=[$v];return $v;
    }
}
if(!function_exists('get_meta_sql')){
    function get_meta_sql($meta_query, $type, $primary_table, $primary_id_column, $context=null) { $q=new WP_Meta_Query($meta_query);return $q->get_sql($type,$primary_table,$primary_id_column,$context); }
}
if(!function_exists('sanitize_meta')){
    function sanitize_meta($meta_key, $meta_value, $object_type, $object_subtype='') {
        if(!empty($object_subtype)&&has_filter("sanitize_{$object_type}_meta_{$meta_key}_for_{$object_subtype}"))return apply_filters("sanitize_{$object_type}_meta_{$meta_key}_for_{$object_subtype}",$meta_value,$meta_key,$object_type,$object_subtype);
        return apply_filters("sanitize_{$object_type}_meta_{$meta_key}",$meta_value,$meta_key,$object_type,$object_subtype);
    }
}
if(!function_exists('get_registered_meta_keys')){
    function get_registered_meta_keys($object_type, $object_subtype='') { global $wp_meta_keys;return $wp_meta_keys[$object_type][$object_subtype]??[]; }
}
if(!function_exists('registered_meta_key_exists')){
    function registered_meta_key_exists($object_type, $meta_key, $object_subtype='') { return isset(get_registered_meta_keys($object_type,$object_subtype)[$meta_key]); }
}
if(!function_exists('get_object_subtype')){
    function get_object_subtype($object_type, $object_id) {
        $object_id=(int)$object_id;$sub='';
        switch($object_type){
            case 'post': $t=get_post_type($object_id);if(!empty($t))$sub=$t;break;
            case 'term': $t=get_term($object_id);if($t instanceof WP_Term)$sub=$t->taxonomy;break;
            case 'comment': if(get_comment($object_id))$sub='comment';break;
            case 'user': if(get_user_by('id',$object_id))$sub='user';break;
        }
        return apply_filters("get_object_subtype_{$object_type}",$sub,$object_id);
    }
}
if(!function_exists('get_registered_metadata')){
    function get_registered_metadata($object_type, $object_id, $meta_key='') {
        $sub=get_object_subtype($object_type,$object_id);$keys=get_registered_meta_keys($object_type,$sub);
        if(!empty($meta_key)){ if(!isset($keys[$meta_key]))return false;return get_metadata($object_type,$object_id,$meta_key,!empty($keys[$meta_key]['single'])); }
        $data=get_metadata($object_type,$object_id);if(!$data)return [];$out=[];
        foreach($keys as $k=>$a)if(isset($data[$k]))$out[$k]=!empty($a['single'])?$data[$k][0]:$data[$k];
        return $out;
    }
}
if(!function_exists('_wp_register_meta_args_allowed_list')){ function _wp_register_meta_args_allowed_list($args, $default_args) { return array_intersect_key($args,$default_args); } }
if(!function_exists('filter_default_metadata')){
    function filter_default_metadata($value, $object_id, $meta_key, $single, $meta_type) {
        global $wp_meta_keys;if(wp_installing()||!is_array($wp_meta_keys)||!isset($wp_meta_keys[$meta_type]))return $value;
        $defs=[];foreach($wp_meta_keys[$meta_type] as $sub=>$data)foreach($data as $k=>$a)if($k===$meta_key&&array_key_exists('default',$a))$defs[$sub]=$a;
        if(!$defs)return $value;
        if(isset($defs['']))$m=$defs[''];else{ $st=get_object_subtype($meta_type,$object_id);if(!isset($defs[$st]))return $value;$m=$defs[$st]; }
        return $single?$m['default']:[$m['default']];
    }
}

/* ───────── Rechte (wp-includes/capabilities.php) – Einzelseiten-Betrieb ───────── */
if(!function_exists('current_user_can_for_site')){ function current_user_can_for_site($site_id, $capability, ...$args) { return current_user_can($capability,...$args); } }
if(!function_exists('user_can_for_site')){ function user_can_for_site($user, $site_id, $capability, ...$args) { return user_can($user,$capability,...$args); } }
if(!function_exists('grant_super_admin')){ function grant_super_admin($user_id) { return false; } }    // Multisite gibt es nicht
if(!function_exists('revoke_super_admin')){ function revoke_super_admin($user_id) { return false; } }
if(!function_exists('wp_maybe_grant_install_languages_cap')){
    function wp_maybe_grant_install_languages_cap($allcaps) { if(!empty($allcaps['update_core'])&&!empty($allcaps['install_plugins'])&&!empty($allcaps['install_themes']))$allcaps['install_languages']=true;return $allcaps; }
}
if(!function_exists('wp_maybe_grant_resume_extensions_caps')){
    function wp_maybe_grant_resume_extensions_caps($allcaps) { if(!empty($allcaps['edit_themes']))$allcaps['resume_themes']=true;if(!empty($allcaps['edit_plugins']))$allcaps['resume_plugins']=true;return $allcaps; }
}
if(!function_exists('wp_maybe_grant_site_health_caps')){
    function wp_maybe_grant_site_health_caps($allcaps, $caps=[], $args=[], $user=null) { if(!empty($allcaps['install_plugins']))$allcaps['view_site_health_checks']=true;return $allcaps; }
}

<?php
// Ergänzende WordPress-Funktionen (Kern 2, Teil 1): Navigationsmenüs (Menüs liegen im CMS, Schreibzugriffe sind No-ops), Blogroll (Links), Walker-Klassen.
// Eigenständig umgesetzt; Einzelseiten-Betrieb.

/* ───────── Navigationsmenüs ───────── */
if(!function_exists('is_nav_menu_item')){ function is_nav_menu_item($menu_item_id=0) { return !is_wp_error($menu_item_id)&&get_post_type($menu_item_id)==='nav_menu_item'; } }
if(!function_exists('_is_valid_nav_menu_item')){ function _is_valid_nav_menu_item($item) { return empty($item['_invalid']); } }
// Menüs verwaltet das CMS (oben/unten): Anlegen, Ändern und Löschen über die WordPress-API ist bewusst ein No-op mit Fehler bzw. false
if(!function_exists('wp_update_nav_menu_object')){ function wp_update_nav_menu_object($menu_id=0, $menu_data=[]) {
    $name=trim(wp_unslash((string)($menu_data['menu-name']??'')));
    if($name==='')return new WP_Error('menu_name_empty','Der Menüname darf nicht leer sein.');
    $ex=wp_get_nav_menu_object($name);if(!$ex)$ex=wp_get_nav_menu_object(sanitize_title($name));
    if($ex&&(int)$ex->term_id!==(int)$menu_id)return new WP_Error('menu_exists',sprintf('Der Menüname %s ist bereits vergeben.',esc_html($name)));
    return new WP_Error('menus_cms','Menüs werden im CMS verwaltet.');
} }
if(!function_exists('wp_create_nav_menu')){ function wp_create_nav_menu($menu_name) { return wp_update_nav_menu_object(0,['menu-name'=>$menu_name]); } }
if(!function_exists('wp_delete_nav_menu')){ function wp_delete_nav_menu($menu) { return wp_get_nav_menu_object($menu)?new WP_Error('menus_cms','Menüs werden im CMS verwaltet.'):false; } }
if(!function_exists('update_menu_item_cache')){ function update_menu_item_cache($menu_items, $update_term_cache=true) {} }   // kein Zwischenspeicher nötig
if(!function_exists('wp_setup_nav_menu_item')){ function wp_setup_nav_menu_item($menu_item) {
    if(!is_object($menu_item))return $menu_item;
    foreach(['ID'=>0,'db_id'=>0,'menu_item_parent'=>0,'menu_order'=>0,'title'=>'','url'=>'','target'=>'','attr_title'=>'','description'=>'','classes'=>[],'xfn'=>'','type'=>'custom','object'=>'custom','object_id'=>0,'type_label'=>'Eigener Link'] as $k=>$v)if(!isset($menu_item->$k))$menu_item->$k=$v;
    if(!$menu_item->db_id)$menu_item->db_id=(int)$menu_item->ID;
    $menu_item->classes=array_values(array_filter((array)$menu_item->classes,'strlen'));
    return apply_filters('wp_setup_nav_menu_item',$menu_item);
} }
if(!function_exists('wp_get_associated_nav_menu_items')){ function wp_get_associated_nav_menu_items($object_id=0, $object_type='post_type', $taxonomy='') {
    $ids=[];foreach(['top','bottom'] as $m)foreach(rrw_wp_cms_menu_items($m) as $it)
        if((int)$it->object_id===(int)$object_id&&$it->type===$object_type&&($object_type!=='taxonomy'||$it->object===$taxonomy))$ids[]=(int)$it->db_id;
    return array_values(array_unique($ids));
} }
// Menüpunkte liegen nicht in der Datenbank: nichts aufzuräumen
if(!function_exists('_wp_delete_post_menu_item')){ function _wp_delete_post_menu_item(...$a) {} }
if(!function_exists('_wp_delete_tax_menu_item')){ function _wp_delete_tax_menu_item(...$a) {} }
if(!function_exists('_wp_auto_add_pages_to_menu')){ function _wp_auto_add_pages_to_menu(...$a) {} }
if(!function_exists('_wp_delete_customize_changeset_dependent_auto_drafts')){ function _wp_delete_customize_changeset_dependent_auto_drafts(...$a) {} }
if(!function_exists('_wp_menus_changed')){ function _wp_menus_changed(...$a) {} }
if(!function_exists('wp_map_nav_menu_locations')){ function wp_map_nav_menu_locations($new_nav_menu_locations, $old_nav_menu_locations) {
    $out=[];$old=array_keys((array)$old_nav_menu_locations);$new=array_keys((array)$new_nav_menu_locations);
    foreach($new as $loc){ if(isset($old_nav_menu_locations[$loc])){ $out[$loc]=$old_nav_menu_locations[$loc];$old=array_values(array_diff($old,[$loc])); } }
    foreach($new as $i=>$loc){ if(isset($out[$loc]))continue; $match=null;
        foreach($old as $k=>$o){ if(preg_match('/(foot|bottom|unten)/i',$loc)===preg_match('/(foot|bottom|unten)/i',$o)){ $match=$k;break; } }
        if($match!==null){ $out[$loc]=$old_nav_menu_locations[$old[$match]];unset($old[$match]);$old=array_values($old); } }
    return $out;
} }
if(!function_exists('_wp_reset_invalid_menu_item_parent')){ function _wp_reset_invalid_menu_item_parent($menu_id=0, $menu_items=[]) {   // Eltern-IDs, die es nicht gibt, auf 0 setzen
    $ids=[];foreach($menu_items as $i)$ids[(int)($i->db_id??$i->ID??0)]=1;
    foreach($menu_items as $i)if(!empty($i->menu_item_parent)&&!isset($ids[(int)$i->menu_item_parent]))$i->menu_item_parent=0;
    return $menu_items;
} }

/* ───────── Blogroll (Tabelle wp_links) ───────── */
if(!function_exists('sanitize_bookmark_field')){ function sanitize_bookmark_field($field, $value, $bookmark_id, $context) {
    $int=['link_id','link_rating','link_owner'];$url=['link_url','link_image','link_rss'];
    if(in_array($field,$int,true))$value=(int)$value;
    if($context==='raw')return $value;
    if(in_array($field,$int,true))return $value;
    if($context==='edit'){ $value=apply_filters("edit_{$field}",$value,$bookmark_id);
        return in_array($field,['link_notes','link_description','link_name'],true)?format_to_edit((string)$value):esc_attr((string)$value); }
    if($context==='db'){ $value=apply_filters("pre_{$field}",$value);return in_array($field,$url,true)?esc_url_raw((string)$value):$value; }
    $value=apply_filters($field,$value,$bookmark_id,$context);
    if($context==='attribute')return esc_attr((string)$value);
    if($context==='js')return esc_js((string)$value);
    return in_array($field,$url,true)?esc_url((string)$value):$value;
} }
if(!function_exists('sanitize_bookmark')){ function sanitize_bookmark($bookmark, $context='display') {
    $fields=['link_id','link_url','link_name','link_image','link_target','link_category','link_description','link_visible','link_owner','link_rating','link_updated','link_rel','link_notes','link_rss'];
    $isObj=is_object($bookmark);$id=(int)($isObj?($bookmark->link_id??0):($bookmark['link_id']??0));
    foreach($fields as $f){
        if($isObj){ if(isset($bookmark->$f))$bookmark->$f=sanitize_bookmark_field($f,$bookmark->$f,$id,$context); }
        elseif(isset($bookmark[$f]))$bookmark[$f]=sanitize_bookmark_field($f,$bookmark[$f],$id,$context);
    }
    return $bookmark;
} }
if(!function_exists('get_bookmark')){ function get_bookmark($bookmark, $output=OBJECT, $filter='raw') {
    global $wpdb;$row=null;
    if(empty($bookmark)){ $row=$GLOBALS['link']??null; }
    elseif(is_object($bookmark)){ $row=$bookmark; }
    else{ $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->links} WHERE link_id = %d",(int)$bookmark)); }
    if(!$row)return $output===OBJECT?null:null;
    $row=clone $row;
    if(taxonomy_exists('link_category')&&!isset($row->link_category))$row->link_category=array_map('intval',(array)wp_get_object_terms((int)$row->link_id,'link_category',['fields'=>'ids']));
    $row=sanitize_bookmark($row,$filter);
    if($output===ARRAY_A)return get_object_vars($row);
    if($output===ARRAY_N)return array_values(get_object_vars($row));
    return $row;
} }
if(!function_exists('get_bookmark_field')){ function get_bookmark_field($field, $bookmark, $context='display') {
    $b=get_bookmark($bookmark);if(!$b||!isset($b->$field))return '';
    return sanitize_bookmark_field($field,$b->$field,(int)$b->link_id,$context);
} }
if(!function_exists('get_bookmarks')){ function get_bookmarks($args='') {
    global $wpdb;
    $r=wp_parse_args($args,['orderby'=>'name','order'=>'ASC','limit'=>-1,'category'=>'','category_name'=>'','hide_invisible'=>1,'show_updated'=>0,'include'=>'','exclude'=>'','search'=>'']);
    $key=md5(serialize($r));$c=wp_cache_get('get_bookmarks','bookmark');if(is_array($c)&&isset($c[$key]))return apply_filters('get_bookmarks',$c[$key],$r);
    $where=['1=1'];$join='';
    $inc=wp_parse_id_list($r['include']);$exc=wp_parse_id_list($r['exclude']);
    if($inc)$where[]='l.link_id IN ('.implode(',',$inc).')';elseif($exc)$where[]='l.link_id NOT IN ('.implode(',',$exc).')';
    $cats=wp_parse_id_list($r['category']);
    if($r['category_name']!==''){ $t=get_term_by('name',$r['category_name'],'link_category');$cats=$t?[(int)$t->term_id]:[0]; }
    if($cats){ $join=" INNER JOIN {$wpdb->term_relationships} tr ON l.link_id = tr.object_id INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id";
        $where[]="tt.taxonomy = 'link_category' AND tt.term_id IN (".implode(',',$cats).')'; }
    if($r['hide_invisible'])$where[]="l.link_visible = 'Y'";
    if($r['search']!==''){ $like='%'.$wpdb->esc_like(wp_unslash((string)$r['search'])).'%';$where[]=$wpdb->prepare('(l.link_url LIKE %s OR l.link_name LIKE %s OR l.link_description LIKE %s)',$like,$like,$like); }
    $ob=strtolower(preg_replace('/^link_/','',(string)$r['orderby']));$rand=$ob==='rand';
    $col=['url'=>'l.link_url','visible'=>'l.link_visible','rating'=>'l.link_rating','owner'=>'l.link_owner','updated'=>'l.link_updated','id'=>'l.link_id','description'=>'l.link_description','length'=>'LENGTH(l.link_name)'][$ob]??'l.link_name';
    $dir=strtoupper((string)$r['order'])==='DESC'?'DESC':'ASC';
    $sql="SELECT DISTINCT l.* FROM {$wpdb->links} l$join WHERE ".implode(' AND ',$where).($rand?'':" ORDER BY $col $dir");
    if(!$rand&&(int)$r['limit']>0)$sql.=' LIMIT '.(int)$r['limit'];
    $rows=$wpdb->get_results($sql);$rows=is_array($rows)?$rows:[];
    if($rand){ shuffle($rows);if((int)$r['limit']>0)$rows=array_slice($rows,0,(int)$r['limit']); }
    $c=is_array($c)?$c:[];$c[$key]=$rows;wp_cache_set('get_bookmarks',$c,'bookmark');
    return apply_filters('get_bookmarks',$rows,$r);
} }
if(!function_exists('clean_bookmark_cache')){ function clean_bookmark_cache($bookmark_id) {
    wp_cache_delete($bookmark_id,'bookmark');wp_cache_delete('get_bookmarks','bookmark');
    if(function_exists('clean_object_term_cache'))clean_object_term_cache($bookmark_id,'link');
} }
if(!function_exists('_walk_bookmarks')){ function _walk_bookmarks($bookmarks, $args='') {
    $r=wp_parse_args($args,['show_updated'=>0,'show_description'=>0,'show_images'=>1,'show_name'=>0,'before'=>'<li>','after'=>'</li>','between'=>"\n",'show_rating'=>0,'link_before'=>'','link_after'=>'']);
    $o='';
    foreach((array)$bookmarks as $b){
        $b=sanitize_bookmark(clone $b,'display');$name=esc_attr($b->link_name);$title=$b->link_description;$rel=$b->link_rel!==''?' rel="'.esc_attr($b->link_rel).'"':'';
        $target=$b->link_target!==''?' target="'.esc_attr($b->link_target).'"':'';$desc=esc_attr($title);
        $o.=$r['before'].'<a href="'.esc_url($b->link_url).'"'.$rel.$target.($desc!==''?' title="'.$desc.'"':'').'>';
        if($b->link_image!==''&&$r['show_images']){ $o.='<img src="'.esc_url($b->link_image).'" alt="'.($r['show_name']?'':$name).'"'.($desc!==''?' title="'.$desc.'"':'').' />'.($r['show_name']?$r['link_before'].$name.$r['link_after']:''); }
        else $o.=$r['link_before'].$name.$r['link_after'];
        $o.='</a>';
        if($r['show_description']&&$title!=='')$o.=$r['between'].$title;
        if($r['show_rating'])$o.=$r['between'].(int)$b->link_rating;
        $o.=$r['after']."\n";
    }
    return apply_filters('walk_bookmarks',$o,$bookmarks,$r);
} }
if(!function_exists('wp_list_bookmarks')){ function wp_list_bookmarks($args='') {
    $r=wp_parse_args($args,['orderby'=>'name','order'=>'ASC','limit'=>-1,'category'=>'','exclude_category'=>'','category_name'=>'','hide_invisible'=>1,'show_updated'=>0,'echo'=>1,'categorize'=>1,'title_li'=>'Lesezeichen','title_before'=>'<h2>','title_after'=>'</h2>','category_orderby'=>'name','category_order'=>'ASC','class'=>'linkcat','category_before'=>'<li id="%id" class="%class">','category_after'=>'</li>']);
    $o='';$cats=[];
    if($r['categorize']&&taxonomy_exists('link_category')){
        $cats=get_terms(['taxonomy'=>'link_category','name__like'=>$r['category_name'],'include'=>$r['category'],'exclude'=>$r['exclude_category'],'orderby'=>$r['category_orderby'],'order'=>$r['category_order'],'hierarchical'=>0]);
        $cats=is_wp_error($cats)?[]:$cats;
    }
    if($cats){
        foreach($cats as $c){
            $bm=get_bookmarks(array_merge($r,['category'=>$c->term_id,'category_name'=>'']));if(!$bm)continue;
            $o.=str_replace(['%id','%class'],['linkcat-'.$c->term_id,$r['class']],$r['category_before']).$r['title_before'].esc_html($c->name).$r['title_after']."\n\t<ul class='xoxo blogroll'>\n"._walk_bookmarks($bm,$r)."\n\t</ul>\n".$r['category_after']."\n";
        }
    }else{
        $bm=get_bookmarks($r);
        if($bm){ $o.=str_replace(['%id','%class'],['linkcat-0',$r['class']],$r['category_before']);
            if($r['title_li']!=='')$o.=$r['title_before'].esc_html($r['title_li']).$r['title_after']."\n";
            $o.="\n\t<ul class='xoxo blogroll'>\n"._walk_bookmarks($bm,$r)."\n\t</ul>\n".$r['category_after']."\n"; }
    }
    $o=apply_filters('wp_list_bookmarks',$o);
    if(!$r['echo'])return $o;
    echo $o;
} }

/* ───────── Walker-Klassen ───────── */
if(!class_exists('Walker_Category_Checklist')){
class Walker_Category_Checklist extends Walker {
    public $tree_type='category';public $db_fields=['parent'=>'parent','id'=>'term_id'];
    public function start_lvl(&$output, $depth=0, $args=[]) { $output.=str_repeat("\t",$depth)."<ul class='children'>\n"; }
    public function end_lvl(&$output, $depth=0, $args=[]) { $output.=str_repeat("\t",$depth)."</ul>\n"; }
    public function start_el(&$output, $data_object, $depth=0, $args=[], $current_object_id=0) {
        $args=(array)$args;$tax=$args['taxonomy']??'category';$name=$tax==='category'?'post_category':'tax_input['.$tax.']';
        $sel=array_map('intval',(array)($args['selected_cats']??[]));$pop=array_map('intval',(array)($args['popular_cats']??[]));
        $cls=in_array((int)$data_object->term_id,$pop,true)?' class="popular-category"':'';
        $dis=!empty($args['disabled'])?' disabled="disabled"':'';
        $output.="\n<li id='{$tax}-{$data_object->term_id}'$cls><label class='selectit'><input value=\"".(int)$data_object->term_id.'" type="checkbox" name="'.esc_attr($name).'[]" id="in-'.$tax.'-'.(int)$data_object->term_id.'"'.checked(in_array((int)$data_object->term_id,$sel,true),true,false).$dis.' /> '.esc_html(apply_filters('the_category',$data_object->name,'','')).'</label>';
    }
    public function end_el(&$output, $data_object, $depth=0, $args=[]) { $output.="</li>\n"; }
}
}
if(!class_exists('Walker_CategoryDropdown')){
class Walker_CategoryDropdown extends Walker {
    public $tree_type='category';public $db_fields=['parent'=>'parent','id'=>'term_id'];
    public function start_el(&$output, $data_object, $depth=0, $args=[], $current_object_id=0) {
        $args=(array)$args;$vf=$args['value_field']??'term_id';$v=$data_object->$vf??$data_object->term_id;$pad=str_repeat('&nbsp;',$depth*3);
        $name=apply_filters('list_cats',$data_object->name,$data_object);
        $output.="\t<option class=\"level-$depth\" value=\"".esc_attr((string)$v).'"'.((string)$v===(string)($args['selected']??'')?' selected="selected"':'').'>'.$pad.esc_html($name).(!empty($args['show_count'])?'&nbsp;&nbsp;('.number_format_i18n((int)$data_object->count).')':'')."</option>\n";
    }
}
}
if(!class_exists('Walker_PageDropdown')){
class Walker_PageDropdown extends Walker {
    public $tree_type='page';public $db_fields=['parent'=>'post_parent','id'=>'ID'];
    public function start_el(&$output, $data_object, $depth=0, $args=[], $current_object_id=0) {
        $args=(array)$args;$vf=$args['value_field']??'ID';$v=$data_object->$vf??$data_object->ID;$pad=str_repeat('&nbsp;',$depth*3);
        $t=$data_object->post_title===''?sprintf('#%d (ohne Titel)',$data_object->ID):$data_object->post_title;
        $output.="\t<option class=\"level-$depth\" value=\"".esc_attr((string)$v).'"'.((string)$v===(string)($args['selected']??'')?' selected="selected"':'').'>'.$pad.esc_html(apply_filters('list_pages',$t,$data_object))."</option>\n";
    }
}
}
if(!class_exists('Walker_Nav_Menu_Checklist')){
class Walker_Nav_Menu_Checklist extends Walker_Nav_Menu {
    public $db_fields=['parent'=>'menu_item_parent','id'=>'db_id'];
    public function start_lvl(&$output, $depth=0, $args=null) { $output.="\n".str_repeat("\t",$depth)."<ul class=\"children\">\n"; }
    public function start_el(&$output, $data_object, $depth=0, $args=null, $current_object_id=0) {
        $i=$data_object;$id=(int)($i->ID??0);$output.=str_repeat("\t",$depth).'<li><label class="menu-item-title"><input type="checkbox" class="menu-item-checkbox" name="menu-item['.$id.'][menu-item-object-id]" value="'.(int)($i->object_id??0).'" /> '.esc_html($i->title??'').'</label>'
            .'<input type="hidden" name="menu-item['.$id.'][menu-item-type]" value="'.esc_attr($i->type??'custom').'" /><input type="hidden" name="menu-item['.$id.'][menu-item-title]" value="'.esc_attr($i->title??'').'" /><input type="hidden" name="menu-item['.$id.'][menu-item-url]" value="'.esc_url($i->url??'').'" />';
    }
}
}
if(!class_exists('Walker_Nav_Menu_Edit')){
class Walker_Nav_Menu_Edit extends Walker_Nav_Menu {
    public function start_el(&$output, $data_object, $depth=0, $args=null, $current_object_id=0) {
        $i=$data_object;$id=(int)($i->ID??0);
        $output.='<li id="menu-item-'.$id.'" class="menu-item menu-item-depth-'.(int)$depth.'"><div class="menu-item-bar">'.esc_html($i->title??'').'</div><div class="menu-item-settings" id="menu-item-settings-'.$id.'">'
            .'<input type="text" name="menu-item-title['.$id.']" value="'.esc_attr($i->title??'').'" /><input type="text" name="menu-item-url['.$id.']" value="'.esc_url($i->url??'').'" />'
            .'<input type="hidden" name="menu-item-db-id['.$id.']" value="'.$id.'" /><input type="hidden" name="menu-item-parent-id['.$id.']" value="'.(int)($i->menu_item_parent??0).'" /></div>';
    }
}
}

/* ───────── Navigation (Block) ───────── */
if(!class_exists('WP_Classic_To_Block_Menu_Converter')){
class WP_Classic_To_Block_Menu_Converter {
    /** Klassisches Menü in Navigationsblock-Markup umwandeln. */
    public static function convert($menu) {
        $obj=wp_get_nav_menu_object($menu);if(!$obj)return new WP_Error('invalid_menu','Das Menü wurde nicht gefunden.');
        $items=wp_get_nav_menu_items($obj);if(!$items)return '';
        $by=[];foreach($items as $i)$by[(int)$i->menu_item_parent][]=$i;
        return static::build($by,0);
    }
    private static function build(array $by, int $parent): string {
        $o='';
        foreach($by[$parent]??[] as $i){
            $a=['label'=>(string)$i->title,'type'=>$i->type==='custom'?'custom':(string)$i->object,'url'=>(string)$i->url,'kind'=>$i->type==='taxonomy'?'taxonomy':($i->type==='post_type'?'post-type':'custom')];
            if($i->object_id)$a['id']=(int)$i->object_id;
            $json=wp_json_encode($a,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            if(!empty($by[(int)$i->db_id]))$o.="<!-- wp:navigation-submenu $json -->".static::build($by,(int)$i->db_id)."<!-- /wp:navigation-submenu -->";
            else $o.="<!-- wp:navigation-link $json /-->";
        }
        return $o;
    }
}
}
if(!class_exists('WP_Navigation_Fallback')){
class WP_Navigation_Fallback {
    /** Vorhandenen Navigationsbeitrag (wp_navigation) liefern; legt nichts an. */
    public static function get_fallback() {
        $f=apply_filters('wp_navigation_should_create_fallback',true);
        $p=static::get_most_recently_published_navigation();if($p)return $p;
        return $f?null:null;
    }
    private static function get_most_recently_published_navigation() {
        $q=get_posts(['post_type'=>'wp_navigation','post_status'=>'publish','posts_per_page'=>1,'orderby'=>'date','order'=>'DESC','suppress_filters'=>true]);
        return $q?$q[0]:null;
    }
    public static function create_classic_menu_fallback() { return null; }   // Menüs kommen aus dem CMS, es wird nichts angelegt
}
}
if(!class_exists('WP_Customize_Nav_Menus')){
class WP_Customize_Nav_Menus {
    public $manager;public $previewed_menus=[];
    public function __construct($manager=null) { $this->manager=$manager; }
    public function filter_nonces($nonces) { $nonces['customize-menus']=wp_create_nonce('customize-menus');return $nonces; }
    public function available_item_types() {
        $t=[];foreach(get_post_types(['show_in_nav_menus'=>true],'objects') as $pt)$t[]=['title'=>$pt->labels->name??$pt->name,'type'=>'post_type','object'=>$pt->name];
        foreach(get_taxonomies(['show_in_nav_menus'=>true],'objects') as $tx)$t[]=['title'=>$tx->labels->name??$tx->name,'type'=>'taxonomy','object'=>$tx->name];
        return $t;
    }
    public function load_available_items_query($type='post_type', $object='page', $page=0) {
        if($type==='post_type'){ $posts=get_posts(['post_type'=>$object,'posts_per_page'=>10,'offset'=>10*(int)$page,'post_status'=>'publish']);$o=[];
            foreach($posts as $p)$o[]=['id'=>$object.'-'.$p->ID,'title'=>$p->post_title,'type'=>'post_type','type_label'=>$object,'object'=>$object,'object_id'=>(int)$p->ID,'url'=>get_permalink($p)];return $o; }
        return [];
    }
    public function enqueue_scripts() {} public function register($manager=null) {} public function customize_register($manager=null) {}
}
}

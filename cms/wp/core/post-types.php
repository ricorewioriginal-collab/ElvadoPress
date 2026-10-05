<?php
// Beitragstypen und Taxonomien (register_post_type, register_taxonomy …) samt Standard-Typen von WordPress.
$GLOBALS['wp_post_types']=$GLOBALS['wp_post_types']??[];
$GLOBALS['wp_taxonomies']=$GLOBALS['wp_taxonomies']??[];
$GLOBALS['_wp_post_type_features']=$GLOBALS['_wp_post_type_features']??[];

if(!class_exists('WP_Post_Type')){
#[AllowDynamicProperties]
class WP_Post_Type {
    public $name;public $label='';public $labels;public $description='';public $public=false;public $hierarchical=false;public $exclude_from_search=null;public $publicly_queryable=null;
    public $show_ui=null;public $show_in_menu=null;public $show_in_nav_menus=null;public $show_in_admin_bar=null;public $show_in_rest=false;public $menu_position=null;public $menu_icon=null;
    public $capability_type='post';public $cap;public $map_meta_cap=false;public $supports=[];public $register_meta_box_cb=null;public $taxonomies=[];public $has_archive=false;public $rewrite=true;public $query_var=true;
    public $can_export=true;public $delete_with_user=null;public $_builtin=false;public $_edit_link='post.php?post=%d';
    public function __construct($post_type, $args=[]) { $this->name=(string)$post_type;$this->set_props($args); }
    public function set_props($args) {
        $args=wp_parse_args($args,['labels'=>[],'description'=>'','public'=>false,'hierarchical'=>false,'exclude_from_search'=>null,'publicly_queryable'=>null,'show_ui'=>null,'show_in_menu'=>null,'show_in_nav_menus'=>null,'show_in_admin_bar'=>null,'show_in_rest'=>false,'menu_position'=>null,'menu_icon'=>null,'capability_type'=>'post','map_meta_cap'=>null,'supports'=>[],'register_meta_box_cb'=>null,'taxonomies'=>[],'has_archive'=>false,'rewrite'=>true,'query_var'=>true,'can_export'=>true,'delete_with_user'=>null,'_builtin'=>false]);
        foreach($args as $k=>$v)$this->$k=$v;
        if($this->publicly_queryable===null)$this->publicly_queryable=$this->public;
        if($this->show_ui===null)$this->show_ui=$this->public;
        if($this->exclude_from_search===null)$this->exclude_from_search=!$this->public;
        if($this->show_in_menu===null)$this->show_in_menu=$this->show_ui;
        if($this->show_in_nav_menus===null)$this->show_in_nav_menus=$this->public;
        $this->label=(string)($args['label']??($args['labels']['name']??ucfirst($this->name)));
        $this->labels=(object)array_merge(['name'=>$this->label,'singular_name'=>$args['labels']['singular_name']??$this->label,'add_new'=>'Neu hinzufügen','add_new_item'=>'Neu hinzufügen','edit_item'=>'Bearbeiten','new_item'=>'Neu','view_item'=>'Ansehen','search_items'=>'Suchen','not_found'=>'Nichts gefunden','all_items'=>$this->label,'menu_name'=>$this->label,'name_admin_bar'=>$this->label],(array)($args['labels']??[]));
        $this->cap=(object)['edit_post'=>'edit_'.$this->capability_type,'read_post'=>'read_'.$this->capability_type,'delete_post'=>'delete_'.$this->capability_type,'edit_posts'=>'edit_'.$this->capability_type.'s','edit_others_posts'=>'edit_others_'.$this->capability_type.'s','publish_posts'=>'publish_'.$this->capability_type.'s','read_private_posts'=>'read_private_'.$this->capability_type.'s','delete_posts'=>'delete_'.$this->capability_type.'s','read'=>'read'];
    }
}
class WP_Taxonomy {
    public $name;public $label='';public $labels;public $description='';public $public=true;public $publicly_queryable=true;public $hierarchical=false;public $show_ui=true;public $show_in_menu=true;public $show_in_nav_menus=true;public $show_tagcloud=true;public $show_in_rest=false;
    public $object_type=[];public $rewrite=true;public $query_var;public $update_count_callback='';public $meta_box_cb=null;public $_builtin=false;public $cap;public $default_term=null;
    public function __construct($taxonomy, $object_type, $args=[]) {
        $this->name=(string)$taxonomy;$this->object_type=array_values((array)$object_type);
        $args=wp_parse_args($args,['labels'=>[],'description'=>'','public'=>true,'hierarchical'=>false,'rewrite'=>true,'query_var'=>$this->name,'show_ui'=>null,'show_in_menu'=>null,'show_in_nav_menus'=>null,'show_tagcloud'=>null,'show_in_rest'=>false,'update_count_callback'=>'','_builtin'=>false,'meta_box_cb'=>null,'default_term'=>null]);
        foreach($args as $k=>$v)$this->$k=$v;
        if($this->publicly_queryable===null)$this->publicly_queryable=$this->public;
        if($this->show_ui===null)$this->show_ui=$this->public;if($this->show_in_menu===null)$this->show_in_menu=$this->show_ui;if($this->show_in_nav_menus===null)$this->show_in_nav_menus=$this->public;if($this->show_tagcloud===null)$this->show_tagcloud=$this->show_ui;
        $this->label=(string)($args['label']??($args['labels']['name']??ucfirst($this->name)));
        $this->labels=(object)array_merge(['name'=>$this->label,'singular_name'=>$args['labels']['singular_name']??$this->label,'search_items'=>'Suchen','all_items'=>'Alle','edit_item'=>'Bearbeiten','add_new_item'=>'Neu hinzufügen','not_found'=>'Nichts gefunden','menu_name'=>$this->label],(array)($args['labels']??[]));
        $this->cap=(object)['manage_terms'=>'manage_categories','edit_terms'=>'manage_categories','delete_terms'=>'manage_categories','assign_terms'=>'edit_posts'];
    }
}
}

function register_post_type($post_type, $args=[]) {
    global $wp_post_types;
    $post_type=sanitize_key($post_type);if($post_type===''||strlen($post_type)>20)return new WP_Error('post_type_length_invalid','Beitragstyp-Namen müssen zwischen 1 und 20 Zeichen lang sein.');
    $args=apply_filters('register_post_type_args',is_array($args)?$args:[],$post_type);
    $o=new WP_Post_Type($post_type,$args);$wp_post_types[$post_type]=$o;
    foreach((array)$o->supports?:['title','editor'] as $f){ if(is_string($f))add_post_type_support($post_type,$f); }
    if($o->supports===[]||$o->supports===false)add_post_type_support($post_type,['title','editor']);
    foreach((array)$o->taxonomies as $tax)register_taxonomy_for_object_type($tax,$post_type);
    do_action('registered_post_type',$post_type,$o);
    return $o;
}
function unregister_post_type($post_type) { global $wp_post_types;if(!isset($wp_post_types[$post_type]))return new WP_Error('invalid_post_type','Ungültiger Beitragstyp.');unset($wp_post_types[$post_type]);return true; }
function get_post_type_object($post_type) { global $wp_post_types;return is_scalar($post_type)&&isset($wp_post_types[$post_type])?$wp_post_types[$post_type]:null; }
function post_type_exists($post_type) { return (bool)get_post_type_object($post_type); }
function get_post_types($args=[], $output='names', $operator='and') {
    global $wp_post_types;$out=[];$field=$output==='names'?'name':false;
    foreach(wp_filter_object_list($wp_post_types,$args,$operator) as $k=>$o)$out[$k]=$field?$o->$field:$o;
    return apply_filters('get_post_types',$out,$args,$output,$operator);
}
function wp_filter_object_list($input_list, $args=[], $operator='and', $field=false) {
    if(!is_array($input_list))return [];$o=[];if(!is_array($args))$args=[];
    foreach($input_list as $k=>$obj){
        $match=0;foreach($args as $f=>$v){ if(isset($obj->$f)&&$obj->$f==$v)$match++; elseif(!isset($obj->$f)&&$v===false)$match++; }
        $ok=empty($args)||(strtolower($operator)==='and'?$match===count($args):(strtolower($operator)==='or'?$match>0:$match===0));
        if($ok)$o[$k]=$field?$obj->$field:$obj;
    }
    return $o;
}
function post_type_supports($post_type, $feature) { return isset($GLOBALS['_wp_post_type_features'][$post_type][$feature]); }
function add_post_type_support($post_type, $feature, ...$args) { foreach((array)$feature as $f)$GLOBALS['_wp_post_type_features'][$post_type][$f]=$args?:true; }
function remove_post_type_support($post_type, $feature) { unset($GLOBALS['_wp_post_type_features'][$post_type][$feature]); }
function get_all_post_type_supports($post_type) { return $GLOBALS['_wp_post_type_features'][$post_type]??[]; }
function is_post_type_hierarchical($post_type) { $o=get_post_type_object($post_type);return $o&&$o->hierarchical; }
function is_post_type_viewable($post_type) { $o=is_scalar($post_type)?get_post_type_object($post_type):$post_type;return $o&&($o->publicly_queryable||($o->_builtin&&$o->public)); }
function get_post_type_labels($o) { return $o->labels; }
function get_post_types_by_support($feature, $operator='and') { $o=[];foreach($GLOBALS['_wp_post_type_features'] as $t=>$f)if(isset($f[$feature]))$o[]=$t;return $o; }
function get_post_stati($args=[], $output='names', $operator='and') { $s=['publish'=>'publish','future'=>'future','draft'=>'draft','pending'=>'pending','private'=>'private','trash'=>'trash','auto-draft'=>'auto-draft','inherit'=>'inherit'];return $output==='names'?$s:array_map(fn($n)=>(object)['name'=>$n,'label'=>$n,'public'=>$n==='publish','private'=>$n==='private'],$s); }
function get_post_status_object($s) { return (object)['name'=>$s,'label'=>$s,'public'=>$s==='publish','private'=>$s==='private','protected'=>false,'internal'=>in_array($s,['auto-draft','inherit','trash'],true)]; }
function register_post_status($post_status, $args=[]) { return (object)['name'=>$post_status]; }

/* Taxonomien */
function register_taxonomy($taxonomy, $object_type, $args=[]) {
    global $wp_taxonomies;
    $taxonomy=sanitize_key($taxonomy);if($taxonomy===''||strlen($taxonomy)>32)return new WP_Error('taxonomy_length_invalid','Taxonomie-Namen müssen zwischen 1 und 32 Zeichen lang sein.');
    $args=apply_filters('register_taxonomy_args',is_array($args)?$args:[],$taxonomy,(array)$object_type);
    $t=new WP_Taxonomy($taxonomy,$object_type,$args);$wp_taxonomies[$taxonomy]=$t;
    foreach($t->object_type as $ot)register_taxonomy_for_object_type($taxonomy,$ot);
    do_action('registered_taxonomy',$taxonomy,$object_type,(array)$t);
    return $t;
}
function unregister_taxonomy($taxonomy) { global $wp_taxonomies;if(!isset($wp_taxonomies[$taxonomy]))return new WP_Error('invalid_taxonomy','Ungültige Taxonomie.');unset($wp_taxonomies[$taxonomy]);return true; }
function get_taxonomy($taxonomy) { global $wp_taxonomies;return isset($wp_taxonomies[$taxonomy])?$wp_taxonomies[$taxonomy]:false; }
function taxonomy_exists($taxonomy) { return isset($GLOBALS['wp_taxonomies'][$taxonomy]); }
function get_taxonomies($args=[], $output='names', $operator='and') {
    $out=[];foreach(wp_filter_object_list($GLOBALS['wp_taxonomies'],$args,$operator) as $k=>$o)$out[$k]=$output==='names'?$o->name:$o;return $out;
}
function register_taxonomy_for_object_type($taxonomy, $object_type) {
    global $wp_taxonomies;if(!isset($wp_taxonomies[$taxonomy])||!get_post_type_object($object_type))return false;
    if(!in_array($object_type,$wp_taxonomies[$taxonomy]->object_type,true))$wp_taxonomies[$taxonomy]->object_type[]=$object_type;
    return true;
}
function unregister_taxonomy_for_object_type($taxonomy, $object_type) { global $wp_taxonomies;if(!isset($wp_taxonomies[$taxonomy]))return false;$wp_taxonomies[$taxonomy]->object_type=array_values(array_diff($wp_taxonomies[$taxonomy]->object_type,[$object_type]));return true; }
function get_object_taxonomies($object_type, $output='names') {
    if(is_object($object_type))$object_type=$object_type->post_type??($object_type->name??'');
    $r=[];foreach($GLOBALS['wp_taxonomies'] as $t)if(in_array($object_type,$t->object_type,true))$r[$t->name]=$output==='names'?$t->name:$t;return $r;
}
function is_object_in_taxonomy($object_type, $taxonomy) { return isset(get_object_taxonomies($object_type)[$taxonomy]); }
function is_taxonomy_hierarchical($taxonomy) { $t=get_taxonomy($taxonomy);return $t&&$t->hierarchical; }
function is_taxonomy_viewable($t) { $x=is_scalar($t)?get_taxonomy($t):$t;return $x&&$x->publicly_queryable; }

/** Standard-Typen und -Taxonomien von WordPress (beim Start). */
function rrw_wp_register_default_types() {
    $L=fn($n,$s)=>['name'=>$n,'singular_name'=>$s];
    register_post_type('post',['labels'=>$L('Beiträge','Beitrag'),'public'=>true,'_builtin'=>true,'capability_type'=>'post','hierarchical'=>false,'rewrite'=>false,'query_var'=>false,'delete_with_user'=>true,'supports'=>['title','editor','author','thumbnail','excerpt','trackbacks','custom-fields','comments','revisions','post-formats']]);
    register_post_type('page',['labels'=>$L('Seiten','Seite'),'public'=>true,'_builtin'=>true,'capability_type'=>'page','hierarchical'=>true,'rewrite'=>false,'query_var'=>false,'delete_with_user'=>true,'supports'=>['title','editor','author','thumbnail','page-attributes','custom-fields','comments','revisions']]);
    register_post_type('attachment',['labels'=>$L('Medien','Medium'),'public'=>true,'show_ui'=>true,'_builtin'=>true,'capability_type'=>'post','hierarchical'=>false,'rewrite'=>false,'query_var'=>false,'exclude_from_search'=>false,'supports'=>['title','author','comments']]);
    register_post_type('revision',['labels'=>$L('Revisionen','Revision'),'public'=>false,'_builtin'=>true,'capability_type'=>'post','rewrite'=>false,'query_var'=>false,'supports'=>['author']]);
    register_post_type('nav_menu_item',['labels'=>$L('Menüpunkte','Menüpunkt'),'public'=>false,'_builtin'=>true,'hierarchical'=>false,'rewrite'=>false,'query_var'=>false,'supports'=>[]]);
    register_taxonomy('category','post',['hierarchical'=>true,'query_var'=>'category_name','rewrite'=>['hierarchical'=>true],'public'=>true,'show_ui'=>true,'_builtin'=>true,'show_in_nav_menus'=>true,'labels'=>$L('Kategorien','Kategorie')]);
    register_taxonomy('post_tag','post',['hierarchical'=>false,'query_var'=>'tag','rewrite'=>true,'public'=>true,'show_ui'=>true,'_builtin'=>true,'show_in_nav_menus'=>true,'labels'=>$L('Schlagwörter','Schlagwort')]);
    register_taxonomy('nav_menu','nav_menu_item',['public'=>false,'hierarchical'=>false,'show_ui'=>false,'_builtin'=>true,'query_var'=>false,'rewrite'=>false,'labels'=>$L('Menüs','Menü')]);
    register_taxonomy('link_category','link',['hierarchical'=>false,'public'=>false,'show_ui'=>false,'_builtin'=>true,'rewrite'=>false,'labels'=>$L('Link-Kategorien','Link-Kategorie')]);
    register_taxonomy('post_format','post',['public'=>true,'hierarchical'=>false,'show_ui'=>false,'_builtin'=>true,'query_var'=>'post_format','rewrite'=>true,'show_in_nav_menus'=>false,'labels'=>$L('Formate','Format')]);
}

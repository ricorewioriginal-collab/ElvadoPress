<?php
// Meta-API (get/add/update/delete_post_meta, user-, term-, comment-meta) auf den Tabellen wp_postmeta usw.

function _elvado_meta_table(string $type): array {
    global $wpdb;
    $tbl=$type==='user'?$wpdb->usermeta:$wpdb->prefix.$type.'meta';$col=$type.'_id';$idcol=$type==='user'?'umeta_id':'meta_id';
    return [$tbl,$col,$idcol];
}
function get_metadata_raw($meta_type, $object_id, $meta_key='') { return elvado_wp_meta_rows($meta_type,(int)$object_id,$meta_key); }
function elvado_wp_meta_rows(string $type, int $id, string $key=''): array {
    global $wpdb;if(!$wpdb||$id<=0||!elvado_wp_db_ready())return [];[$tbl,$col]=_elvado_meta_table($type);
    $rows=$key===''?$wpdb->get_results($wpdb->prepare("SELECT meta_key, meta_value FROM %i WHERE %i = %d ORDER BY 1 ASC",$tbl,$col,$id),ARRAY_A):$wpdb->get_results($wpdb->prepare("SELECT meta_key, meta_value FROM %i WHERE %i = %d AND meta_key = %s ORDER BY 1 ASC",$tbl,$col,$id,$key),ARRAY_A);
    return is_array($rows)?$rows:[];
}
function get_metadata($meta_type, $object_id, $meta_key='', $single=false) {
    $object_id=absint($object_id);if(!$meta_type||!$object_id)return false;
    $check=apply_filters("get_{$meta_type}_metadata",null,$object_id,$meta_key,$single,$meta_type);
    if(null!==$check)return $single&&is_array($check)?$check[0]:$check;
    $rows=elvado_wp_meta_rows($meta_type,$object_id,(string)$meta_key);
    if($meta_key===''||$meta_key===null){ $all=[];foreach($rows as $r)$all[$r['meta_key']][]=$r['meta_value'];return $all; }
    if(!$rows)return $single?'':[];
    $vals=array_map(fn($r)=>maybe_unserialize($r['meta_value']),$rows);
    return $single?$vals[0]:$vals;
}
function metadata_exists($meta_type, $object_id, $meta_key) { return (bool)elvado_wp_meta_rows($meta_type,absint($object_id),(string)$meta_key); }
function get_metadata_by_mid($meta_type, $meta_id) {
    global $wpdb;[$tbl,$col,$idc]=_elvado_meta_table($meta_type);$r=$wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE %i = %d",$tbl,$idc,$meta_id));
    if(!$r)return false;$r->meta_value=maybe_unserialize($r->meta_value);return $r;
}
function add_metadata($meta_type, $object_id, $meta_key, $meta_value, $unique=false) {
    global $wpdb;$object_id=absint($object_id);if(!$meta_type||!is_numeric($object_id)||!$object_id||!$wpdb)return false;
    $meta_key=wp_unslash($meta_key);$meta_value=wp_unslash($meta_value);
    $check=apply_filters("add_{$meta_type}_metadata",null,$object_id,$meta_key,$meta_value,$unique);if(null!==$check)return $check;
    if($unique&&elvado_wp_meta_rows($meta_type,$object_id,(string)$meta_key))return false;
    [$tbl,$col]=_elvado_meta_table($meta_type);
    do_action("add_{$meta_type}_meta",$object_id,$meta_key,$meta_value);
    $r=$wpdb->insert($tbl,[$col=>$object_id,'meta_key'=>$meta_key,'meta_value'=>maybe_serialize($meta_value)]);
    if(!$r)return false;$mid=(int)$wpdb->insert_id;
    do_action("added_{$meta_type}_meta",$mid,$object_id,$meta_key,$meta_value);return $mid;
}
function update_metadata($meta_type, $object_id, $meta_key, $meta_value, $prev_value='') {
    global $wpdb;$object_id=absint($object_id);if(!$meta_type||!$object_id||!$wpdb)return false;
    $meta_key=wp_unslash($meta_key);$passed=$meta_value;$meta_value=wp_unslash($meta_value);
    $check=apply_filters("update_{$meta_type}_metadata",null,$object_id,$meta_key,$meta_value,$prev_value);if(null!==$check)return (bool)$check;
    $rows=elvado_wp_meta_rows($meta_type,$object_id,(string)$meta_key);
    if(!$rows)return add_metadata($meta_type,$object_id,$meta_key,$passed);
    [$tbl,$col]=_elvado_meta_table($meta_type);
    $old=array_map(fn($r)=>maybe_unserialize($r['meta_value']),$rows);
    if($prev_value===''&&count($old)===1&&$old[0]===$meta_value)return false;
    do_action("update_{$meta_type}_meta",0,$object_id,$meta_key,$meta_value);
    $where=[$col=>$object_id,'meta_key'=>$meta_key];if($prev_value!=='')$where['meta_value']=maybe_serialize($prev_value);
    $r=$wpdb->update($tbl,['meta_value'=>maybe_serialize($meta_value)],$where);
    if(!$r&&$r!==0)return false;
    do_action("updated_{$meta_type}_meta",0,$object_id,$meta_key,$meta_value);return true;
}
function delete_metadata($meta_type, $object_id, $meta_key, $meta_value='', $delete_all=false) {
    global $wpdb;if(!$meta_type||!$wpdb||(!$meta_key&&!$delete_all)||(!is_numeric($object_id)&&!$delete_all))return false;
    $object_id=absint($object_id);if(!$object_id&&!$delete_all)return false;
    $check=apply_filters("delete_{$meta_type}_metadata",null,$object_id,$meta_key,$meta_value,$delete_all);if(null!==$check)return (bool)$check;
    [$tbl,$col]=_elvado_meta_table($meta_type);
    $where=[];if(!$delete_all)$where[$col]=$object_id;if($meta_key!=='')$where['meta_key']=$meta_key;if($meta_value!=='')$where['meta_value']=maybe_serialize($meta_value);
    do_action("delete_{$meta_type}_meta",[],$object_id,$meta_key,$meta_value);
    if(!$where)return false;
    $r=$wpdb->delete($tbl,$where);if(!$r)return false;
    do_action("deleted_{$meta_type}_meta",[],$object_id,$meta_key,$meta_value);return true;
}
function delete_metadata_by_mid($meta_type, $meta_id) { global $wpdb;[$tbl,$col,$idc]=_elvado_meta_table($meta_type);return (bool)$wpdb->delete($tbl,[$idc=>$meta_id]); }
function update_metadata_by_mid($meta_type, $meta_id, $meta_value, $meta_key=false) { global $wpdb;[$tbl,$col,$idc]=_elvado_meta_table($meta_type);$d=['meta_value'=>maybe_serialize($meta_value)];if($meta_key!==false)$d['meta_key']=$meta_key;return $wpdb->update($tbl,$d,[$idc=>$meta_id])!==false; }
/** Meta-Schlüssel registrieren ($wp_meta_keys[Objekttyp][Untertyp][Schlüssel]); die Filter sanitize_{typ}_meta_{key} nimmt sanitize_meta() auf. */
function register_meta($object_type, $meta_key, $args=[], $deprecated=null) {
    global $wp_meta_keys;if(!is_array($wp_meta_keys))$wp_meta_keys=[];
    if(!is_array($args))$args=[];
    $had_default=array_key_exists('default',$args);
    $args=array_merge(['object_subtype'=>'','type'=>'string','label'=>'','description'=>'','default'=>'','single'=>false,'sanitize_callback'=>null,'auth_callback'=>null,'show_in_rest'=>false,'revisions_enabled'=>false],$args);
    $sub=(string)$args['object_subtype'];
    if(!$had_default)unset($args['default']);
    if(is_callable($args['sanitize_callback'])){
        $cb=$args['sanitize_callback'];$n=4;   // PHP-Funktionen (z. B. absint) vertragen nur so viele Argumente, wie sie deklarieren
        try{ $rf=is_array($cb)?new ReflectionMethod($cb[0],$cb[1]):((is_string($cb)&&function_exists($cb))||$cb instanceof Closure?new ReflectionFunction($cb):null);if($rf&&!$rf->isVariadic())$n=max(1,min(4,$rf->getNumberOfParameters())); }catch(Throwable $e){}
        add_filter(!$sub?"sanitize_{$object_type}_meta_{$meta_key}":"sanitize_{$object_type}_meta_{$meta_key}_for_{$sub}",$cb,10,$n);
    }
    $wp_meta_keys[$object_type][$sub][$meta_key]=$args;return true;
}
function register_post_meta($post_type, $meta_key, $args) { return register_meta('post',$meta_key,array_merge((array)$args,['object_subtype'=>$post_type])); }
function register_term_meta($taxonomy, $meta_key, $args) { return register_meta('term',$meta_key,array_merge((array)$args,['object_subtype'=>$taxonomy])); }
function unregister_meta_key($object_type, $meta_key, $object_subtype='') {
    global $wp_meta_keys;if(!isset($wp_meta_keys[$object_type][$object_subtype][$meta_key]))return false;
    unset($wp_meta_keys[$object_type][$object_subtype][$meta_key]);return true;
}

function get_post_meta($post_id, $key='', $single=false) { return get_metadata('post',$post_id,$key,$single); }
function add_post_meta($post_id, $meta_key, $meta_value, $unique=false) { return add_metadata('post',$post_id,$meta_key,$meta_value,$unique); }
function update_post_meta($post_id, $meta_key, $meta_value, $prev_value='') { return update_metadata('post',$post_id,$meta_key,$meta_value,$prev_value); }
function delete_post_meta($post_id, $meta_key, $meta_value='') { return delete_metadata('post',$post_id,$meta_key,$meta_value); }
function get_post_custom($post_id=0) { $post_id=$post_id?:(int)(get_post()->ID??0);return get_post_meta($post_id); }
function get_post_custom_keys($post_id=0) { $c=get_post_custom($post_id);return is_array($c)&&$c?array_keys($c):null; }
function get_post_custom_values($key='', $post_id=0) { $c=get_post_custom($post_id);return $c[$key]??null; }
function get_user_meta($user_id, $key='', $single=false) { return get_metadata('user',$user_id,$key,$single); }
function add_user_meta($user_id, $meta_key, $meta_value, $unique=false) { return add_metadata('user',$user_id,$meta_key,$meta_value,$unique); }
function update_user_meta($user_id, $meta_key, $meta_value, $prev_value='') { return update_metadata('user',$user_id,$meta_key,$meta_value,$prev_value); }
function delete_user_meta($user_id, $meta_key, $meta_value='') { return delete_metadata('user',$user_id,$meta_key,$meta_value); }
function get_term_meta($term_id, $key='', $single=false) { return get_metadata('term',$term_id,$key,$single); }
function add_term_meta($term_id, $meta_key, $meta_value, $unique=false) { return add_metadata('term',$term_id,$meta_key,$meta_value,$unique); }
function update_term_meta($term_id, $meta_key, $meta_value, $prev_value='') { return update_metadata('term',$term_id,$meta_key,$meta_value,$prev_value); }
function delete_term_meta($term_id, $meta_key, $meta_value='') { return delete_metadata('term',$term_id,$meta_key,$meta_value); }
function get_comment_meta($comment_id, $key='', $single=false) { return get_metadata('comment',$comment_id,$key,$single); }
function add_comment_meta($comment_id, $meta_key, $meta_value, $unique=false) { return add_metadata('comment',$comment_id,$meta_key,$meta_value,$unique); }
function update_comment_meta($comment_id, $meta_key, $meta_value, $prev_value='') { return update_metadata('comment',$comment_id,$meta_key,$meta_value,$prev_value); }
function delete_comment_meta($comment_id, $meta_key, $meta_value='') { return delete_metadata('comment',$comment_id,$meta_key,$meta_value); }

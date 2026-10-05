<?php
// Ergänzende WordPress-Funktionen (Bereich Admin, Teil 7): Linkverwaltung (bookmark.php) – Links in der Tabelle wp_links, Kategorien als Taxonomie link_category.
// Die Eingaben werden wie bei WordPress als „geslasht“ erwartet ($_POST) und hier entslasht.

if(!function_exists('rrw_adm_link_row')){ function rrw_adm_link_row($id, $out=OBJECT) {   // Linkzeile aus der Datenbank (null, wenn es sie nicht gibt)
    global $wpdb;$id=(int)$id;if($id<=0)return null;
    $r=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->links} WHERE link_id = %d LIMIT 1",$id),ARRAY_A);
    if(!$r)return null;
    return $out===ARRAY_A?$r:(object)$r;
} }
if(!function_exists('wp_link_manager_disabled_message')){ function wp_link_manager_disabled_message() {   // Linkverwaltung ist standardmäßig abgeschaltet
    global $pagenow;
    if(!in_array($pagenow,['link-manager.php','link-add.php','link.php'],true))return;
    if(get_option('link_manager_enabled'))return;
    wp_die('Die Linkverwaltung ist deaktiviert. Aktiviere sie mit dem Plugin „Link Manager“.','',['response'=>403]);
} }
if(!function_exists('get_default_link_to_edit')){ function get_default_link_to_edit() {
    $l=new stdClass();
    $l->link_url=isset($_GET['linkurl'])?esc_url(wp_unslash((string)$_GET['linkurl'])):'';
    $l->link_name=isset($_GET['name'])?esc_attr(wp_unslash((string)$_GET['name'])):'';
    $l->link_visible='Y';return $l;
} }
if(!function_exists('get_link_to_edit')){ function get_link_to_edit($link) {   // Link für das Bearbeitungsformular (Textfelder maskiert)
    $r=rrw_adm_link_row(is_object($link)?($link->link_id??0):$link);if(!$r)return null;
    foreach(['link_name','link_description','link_notes','link_rel','link_rss','link_image','link_target'] as $f)$r->$f=format_to_edit((string)$r->$f);
    $r->link_url=esc_url((string)$r->link_url);return $r;
} }
if(!function_exists('wp_set_link_cats')){ function wp_set_link_cats($link_id=0, $link_categories=[]) {
    $c=array_values(array_filter(array_map('intval',(array)$link_categories)));
    if(!$c){   // Standard-Linkkategorie („Blogroll“) bei Bedarf anlegen
        $d=(int)get_option('default_link_category');
        if(!$d||!get_term($d,'link_category')){ $e=term_exists('Blogroll','link_category')?:wp_insert_term('Blogroll','link_category');$d=(int)(is_array($e)?$e['term_id']:$e);if($d)update_option('default_link_category',$d); }
        $c=[$d];
    }
    return wp_set_object_terms((int)$link_id,$c,'link_category');
} }
if(!function_exists('wp_get_link_cats')){ function wp_get_link_cats($link_id=0) {
    $c=wp_get_object_terms((int)$link_id,'link_category',['fields'=>'ids']);
    return is_wp_error($c)?[]:array_values(array_unique(array_map('intval',(array)$c)));
} }
if(!function_exists('wp_insert_link')){ function wp_insert_link($linkdata, $wp_error=false) {   // @return int|WP_Error Link-ID, 0 bei Fehler
    global $wpdb;
    $a=wp_unslash(wp_parse_args($linkdata,['link_id'=>0,'link_name'=>'','link_url'=>'','link_rating'=>0]));
    $id=(int)$a['link_id'];$update=$id>0;$name=trim((string)$a['link_name']);$url=trim((string)$a['link_url']);
    if($name===''){ if($url!=='')$name=$url; else return $wp_error?new WP_Error('link_name_empty','Es wurde kein Linkname angegeben.'):0; }
    if($url==='')return $wp_error?new WP_Error('link_url_empty','Es wurde keine Link-URL angegeben.'):0;
    $data=['link_url'=>esc_url_raw($url),'link_name'=>sanitize_text_field($name),'link_image'=>esc_url_raw((string)($a['link_image']??'')),'link_target'=>in_array(($a['link_target']??''),['_blank','_top','_none'],true)?(string)$a['link_target']:'',
        'link_description'=>sanitize_text_field((string)($a['link_description']??'')),'link_visible'=>(($a['link_visible']??'Y')==='N')?'N':'Y','link_owner'=>!empty($a['link_owner'])?(int)$a['link_owner']:(get_current_user_id()?:1),
        'link_rating'=>max(0,min(10,(int)($a['link_rating']??0))),'link_rel'=>sanitize_text_field((string)($a['link_rel']??'')),'link_notes'=>wp_kses_post((string)($a['link_notes']??'')),'link_rss'=>esc_url_raw((string)($a['link_rss']??''))];
    $data=apply_filters('wp_insert_link_data',$data,$a,$update);
    if($update){
        if(!rrw_adm_link_row($id))return $wp_error?new WP_Error('invalid_link','Ungültige Link-ID.'):0;
        if(false===$wpdb->update($wpdb->links,$data,['link_id'=>$id]))return $wp_error?new WP_Error('db_update_error','Der Link konnte nicht aktualisiert werden.',$wpdb->last_error):0;
    } else {
        $data['link_updated']=gmdate('Y-m-d H:i:s');
        if(false===$wpdb->insert($wpdb->links,$data))return $wp_error?new WP_Error('db_insert_error','Der Link konnte nicht eingefügt werden.',$wpdb->last_error):0;
        $id=(int)$wpdb->insert_id;
    }
    if(isset($a['link_category'])&&is_array($a['link_category'])&&$a['link_category'])wp_set_link_cats($id,$a['link_category']);
    elseif(!$update)wp_set_link_cats($id,[]);
    do_action($update?'edit_link':'add_link',$id);
    return $id;
} }
if(!function_exists('wp_update_link')){ function wp_update_link($linkdata) {   // vorhandene Felder bleiben, wenn sie nicht übergeben werden
    $id=(int)($linkdata['link_id']??0);$old=rrw_adm_link_row($id,ARRAY_A);if(!$old)return 0;
    $old=wp_slash($old);$old['link_category']=wp_get_link_cats($id);
    return wp_insert_link(array_merge($old,wp_parse_args($linkdata)));
} }
if(!function_exists('wp_delete_link')){ function wp_delete_link($link_id) {
    global $wpdb;$link_id=(int)$link_id;if(!rrw_adm_link_row($link_id))return false;
    do_action('delete_link',$link_id);
    wp_delete_object_term_relationships($link_id,'link_category');
    $wpdb->delete($wpdb->links,['link_id'=>$link_id]);
    do_action('deleted_link',$link_id);return true;
} }
if(!function_exists('edit_link')){ function edit_link($link_id=0) {   // Link aus $_POST speichern
    $_POST['link_url']=esc_url((string)($_POST['link_url']??''));$_POST['link_name']=esc_html((string)($_POST['link_name']??''));
    $_POST['link_image']=esc_html((string)($_POST['link_image']??''));$_POST['link_rss']=esc_url((string)($_POST['link_rss']??''));
    if(!isset($_POST['link_visible'])||'N'!==$_POST['link_visible'])$_POST['link_visible']='Y';
    if(!empty($link_id)){ $_POST['link_id']=$link_id;return wp_update_link($_POST); }
    return wp_insert_link($_POST);
} }
if(!function_exists('add_link')){ function add_link() { return edit_link(); } }

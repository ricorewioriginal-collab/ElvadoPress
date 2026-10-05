<?php
// Beiträge: get_post, get_posts, wp_insert_post/update/delete, Seiten, Anhänge. CMS-Inhalte (Beiträge/Seiten) werden lesend eingeblendet;
// von WordPress-Code angelegte Inhalte liegen in wp_posts (IDs ab 100 000 000).

function rrw_wp_post_cache_get(int $id) { return $GLOBALS['rrw_wp_pc'][$id]??null; }
function rrw_wp_post_cache_set(int $id, $val) { $GLOBALS['rrw_wp_pc'][$id]=$val;return $val; }
function rrw_wp_post_cache_clear(?int $id=null): void { if($id===null)$GLOBALS['rrw_wp_pc']=[];else unset($GLOBALS['rrw_wp_pc'][$id]); }
function rrw_wp_db_post(int $id): ?WP_Post {
    global $wpdb;if(!$wpdb||$id<RRW_WP_ID_DB_MIN)return null;
    if(($c=rrw_wp_post_cache_get($id))!==null)return $c?:null;
    $r=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID = %d LIMIT 1",$id));
    if(!$r){rrw_wp_post_cache_set($id,false);return null;}
    $r->filter='raw';$p=new WP_Post($r);rrw_wp_post_cache_set($id,$p);return $p;
}
function get_post($post=null, $output=OBJECT, $filter='raw') {
    if(empty($post)&&isset($GLOBALS['post']))$post=$GLOBALS['post'];
    if($post instanceof WP_Post)$_post=$post;
    elseif(is_object($post)){ if(empty($post->filter)){ $_post=($post->ID??0)?(rrw_wp_cms_find_post((int)$post->ID)?:rrw_wp_db_post((int)$post->ID)?:new WP_Post($post)):new WP_Post($post); } else $_post=new WP_Post($post); }
    elseif(is_array($post))$_post=new WP_Post((object)$post);
    elseif(is_numeric($post)){ $id=(int)$post;$_post=rrw_wp_cms_find_post($id)?:rrw_wp_db_post($id); }
    else $_post=null;
    if(!$_post)return null;
    $_post=apply_filters('get_post',$_post,$output,$filter);   // WordPress ruft den Filter über sanitize_post auf; so bleibt die Erweiterbarkeit
    if($output===ARRAY_A)return $_post->to_array();if($output===ARRAY_N)return array_values($_post->to_array());
    return $_post;
}
function get_post_field($field, $post=null, $context='display') { $p=get_post($post);return $p&&isset($p->$field)?$p->$field:''; }
function get_post_status($post=null) { $p=get_post($post);return $p?$p->post_status:false; }
function get_post_type($post=null) { $p=get_post($post);return $p?$p->post_type:false; }
function get_post_mime_type($post=null) { $p=get_post($post);return $p?$p->post_mime_type:false; }
function get_post_ancestors($post) { $p=get_post($post);if(!$p||!$p->post_parent||$p->post_parent==$p->ID)return [];$a=[];$cur=$p;while($cur->post_parent&&!in_array((int)$cur->post_parent,$a,true)){$a[]=(int)$cur->post_parent;$cur=get_post($cur->post_parent);if(!$cur)break;}return $a; }
function get_post_parent($post=null) { $p=get_post($post);return $p&&$p->post_parent?get_post($p->post_parent):null; }
function has_post_parent($post=null) { return (bool)get_post_parent($post); }
function get_children($args='', $output=OBJECT) { $r=wp_parse_args($args,['post_parent'=>0,'post_type'=>'any','post_status'=>'any','numberposts'=>-1]);if(!$r['post_parent'])return [];return get_posts($r); }
function is_sticky($post_id=0) { $s=get_option('sticky_posts',[]);return in_array((int)($post_id?:(get_post()->ID??0)),array_map('intval',(array)$s),true); }
function stick_post($post_id) { $s=(array)get_option('sticky_posts',[]);if(!in_array((int)$post_id,$s,true)){$s[]=(int)$post_id;update_option('sticky_posts',$s);} }
function unstick_post($post_id) { $s=array_values(array_diff((array)get_option('sticky_posts',[]),[(int)$post_id]));update_option('sticky_posts',$s); }
function get_posts($args=null) {
    $d=['numberposts'=>5,'category'=>0,'orderby'=>'date','order'=>'DESC','include'=>[],'exclude'=>[],'meta_key'=>'','meta_value'=>'','post_type'=>'post','suppress_filters'=>true];
    $r=wp_parse_args($args,$d);
    if(empty($r['post_status']))$r['post_status']=($r['post_type']==='attachment')?'inherit':'publish';
    if(!empty($r['numberposts'])&&empty($r['posts_per_page']))$r['posts_per_page']=$r['numberposts'];
    if(!empty($r['category']))$r['cat']=$r['category'];
    if(!empty($r['include'])){ $inc=wp_parse_id_list($r['include']);$r['posts_per_page']=count($inc);$r['post__in']=$inc; }
    elseif(!empty($r['exclude']))$r['post__not_in']=wp_parse_id_list($r['exclude']);
    $r['ignore_sticky_posts']=true;$r['no_found_rows']=true;
    $q=new WP_Query();return $q->query($r);
}
function wp_count_posts($type='post', $perm='') {
    $cnt=['publish'=>0,'future'=>0,'draft'=>0,'pending'=>0,'private'=>0,'trash'=>0,'auto-draft'=>0,'inherit'=>0];
    $q=new WP_Query(['post_type'=>$type,'post_status'=>'any','posts_per_page'=>-1,'no_found_rows'=>true,'suppress_filters'=>true]);
    foreach($q->posts as $p)$cnt[$p->post_status]=($cnt[$p->post_status]??0)+1;
    return (object)$cnt;
}

/* Anlegen / Ändern / Löschen (nur wp_posts) */
function wp_unique_post_slug($slug, $post_id, $post_status, $post_type, $post_parent) {
    global $wpdb;$orig=$slug;$i=2;
    while($wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s AND ID != %d LIMIT 1",$slug,$post_type,$post_id))||($post_type==='post'||$post_type==='page'?rrw_wp_cms_slug_taken($slug,$post_type):false)){ $slug=substr($orig,0,190).'-'.$i++; }
    return apply_filters('wp_unique_post_slug',$slug,$post_id,$post_status,$post_type,$post_parent,$orig);
}
function rrw_wp_cms_slug_taken(string $slug, string $type): bool {
    $any=function_exists('rrw_wp_bridge')&&rrw_wp_bridge($type==='post'?'posts':'pages');   // mit Schreibbrücke zählen auch Entwürfe
    foreach($type==='post'?($any?rrw_wp_cms_posts_any():rrw_wp_cms_posts()):($any?rrw_wp_cms_pages_any():rrw_wp_cms_pages()) as $p)if($p->post_name===$slug)return true;return false;
}
function wp_insert_post($postarr, $wp_error=false, $fire_after_hooks=true) {
    global $wpdb;
    $postarr=is_object($postarr)?get_object_vars($postarr):(array)$postarr;
    if(($cmsRes=rrw_wp_cms_try_insert($postarr,(bool)$wp_error))!==null)return $cmsRes;   // Schreibbrücke: CMS-Beiträge und neue Beiträge → news.json, CMS-Seiten → site.json
    if(!$wpdb||!$wpdb->ready){ return $wp_error?new WP_Error('no_database','Keine Datenbank verfügbar.'):0; }
    rrw_wp_install_schema();
    $d=['post_author'=>get_current_user_id()?:1,'post_date'=>'','post_date_gmt'=>'','post_content'=>'','post_content_filtered'=>'','post_title'=>'','post_excerpt'=>'','post_status'=>'draft','post_type'=>'post','comment_status'=>'','ping_status'=>'','post_password'=>'','post_name'=>'','to_ping'=>'','pinged'=>'','post_parent'=>0,'menu_order'=>0,'post_mime_type'=>'','guid'=>''];
    $update=!empty($postarr['ID']);$before=null;
    if($update){ $id=(int)$postarr['ID'];if(rrw_wp_is_cms_id($id))return $wp_error?new WP_Error('cms_content','CMS-Beiträge und -Seiten werden im CMS bearbeitet.'):0;
        $before=get_post($id);if(!$before)return $wp_error?new WP_Error('invalid_post','Ungültige Beitrags-ID.'):0; $d=array_merge($d,$before->to_array()); }
    $a=array_merge($d,array_intersect_key($postarr,$d+['ID'=>1]));
    if($a['post_status']==='')$a['post_status']='draft';
    $a['post_title']=(string)$a['post_title'];
    if($a['post_title']===''&&$a['post_content']===''&&$a['post_excerpt']===''&&!in_array($a['post_type'],['attachment','nav_menu_item'],true)&&!apply_filters('wp_insert_post_empty_content',false,$a))return $wp_error?new WP_Error('empty_content','Inhalt, Titel und Auszug sind leer.'):0;
    $now=current_time('mysql');$gmt=current_time('mysql',1);
    if(empty($a['post_date']))$a['post_date']=$update?$a['post_date']:$now; if(empty($a['post_date_gmt']))$a['post_date_gmt']=get_gmt_from_date($a['post_date'])?:$gmt;
    if($a['post_status']==='publish'&&strtotime($a['post_date_gmt'].' UTC')>time()+60)$a['post_status']='future';
    $a['post_modified']=$now;$a['post_modified_gmt']=$gmt;
    if($a['comment_status']==='')$a['comment_status']=get_option('default_comment_status','open');if($a['ping_status']==='')$a['ping_status']='closed';
    if($a['post_name']===''&&!in_array($a['post_status'],['draft','pending','auto-draft'],true))$a['post_name']=sanitize_title($a['post_title']);
    elseif($a['post_name']!=='')$a['post_name']=sanitize_title($a['post_name']);
    if($a['post_name']!=='')$a['post_name']=wp_unique_post_slug($a['post_name'],$update?(int)$postarr['ID']:0,$a['post_status'],$a['post_type'],(int)$a['post_parent']);
    $data=apply_filters('wp_insert_post_data',$a,$postarr,[], $update);
    $cols=['post_author','post_date','post_date_gmt','post_content','post_content_filtered','post_title','post_excerpt','post_status','post_type','comment_status','ping_status','post_password','post_name','to_ping','pinged','post_modified','post_modified_gmt','post_parent','menu_order','post_mime_type','guid'];
    $row=[];foreach($cols as $c)$row[$c]=$data[$c]??'';
    foreach(['post_author','post_parent','menu_order','comment_count'] as $ic)if(isset($row[$ic]))$row[$ic]=(int)$row[$ic];   // MySQL im strengen Modus akzeptiert keinen Leerstring für Zahlenspalten
    do_action('pre_post_update',$update?(int)$postarr['ID']:0,$data);
    if($update){ $id=(int)$postarr['ID'];$wpdb->update($wpdb->posts,$row,['ID'=>$id]); }
    else{ $row['guid']=$row['guid']; $wpdb->insert($wpdb->posts,$row);$id=(int)$wpdb->insert_id;
        if($id<=0)return $wp_error?new WP_Error('db_insert_error','Der Beitrag konnte nicht gespeichert werden.',$wpdb->last_error):0;
        if($row['guid']==='')$wpdb->update($wpdb->posts,['guid'=>home_url('/?p='.$id)],['ID'=>$id]); }
    rrw_wp_post_cache_clear($id);
    foreach((array)($postarr['meta_input']??[]) as $k=>$v)update_post_meta($id,$k,$v);
    foreach((array)($postarr['tax_input']??[]) as $tax=>$terms)if(taxonomy_exists($tax))wp_set_post_terms($id,$terms,$tax);
    if(!empty($postarr['post_category'])&&$data['post_type']==='post')wp_set_post_categories($id,$postarr['post_category']);
    if(!empty($postarr['tags_input']))wp_set_post_terms($id,$postarr['tags_input'],'post_tag');
    $post=get_post($id);
    $old_status=$before?$before->post_status:'new';
    if($old_status!==$data['post_status']){ wp_transition_post_status($data['post_status'],$old_status,$post); }
    do_action("edit_post_{$data['post_type']}",$id,$post);do_action('edit_post',$id,$post);
    if($update)do_action('post_updated',$id,$post,$before);
    do_action("save_post_{$data['post_type']}",$id,$post,$update);do_action('save_post',$id,$post,$update);do_action('wp_insert_post',$id,$post,$update);
    return $id;
}
function wp_transition_post_status($new_status, $old_status, $post) {
    do_action('transition_post_status',$new_status,$old_status,$post);
    do_action("{$old_status}_to_{$new_status}",$post);do_action("{$new_status}_{$post->post_type}",$post->ID,$post);
}
function wp_update_post($postarr=[], $wp_error=false, $fire_after_hooks=true) {
    if(is_object($postarr))$postarr=get_object_vars($postarr);
    $id=(int)($postarr['ID']??0);$post=get_post($id,ARRAY_A);if(!$post)return $wp_error?new WP_Error('invalid_post','Ungültige Beitrags-ID.'):0;
    return wp_insert_post(array_merge($post,$postarr),$wp_error,$fire_after_hooks);
}
function wp_delete_post($postid=0, $force_delete=false) {
    global $wpdb;
    if(is_numeric($postid)&&($mi=rrw_wp_cms_menu_item_delete((int)$postid))!==null)return $mi;   // Schreibbrücke: CMS-Menüpunkt
    $post=get_post($postid);if(!$post||(rrw_wp_is_cms_id((int)$post->ID)&&!rrw_wp_cms_bridged_id((int)$post->ID)))return false;
    if(!$force_delete&&in_array($post->post_type,['post','page'],true)&&$post->post_status!=='trash'){ return wp_trash_post($postid); }
    if(rrw_wp_is_cms_id((int)$post->ID))return $post->rrw_source==='news'?rrw_wp_cms_news_delete($post):false;   // CMS-Seiten werden nur im CMS gelöscht
    $pre=apply_filters('pre_delete_post',null,$post,$force_delete);if(null!==$pre)return $pre;
    do_action('before_delete_post',$postid,$post);
    $wpdb->delete($wpdb->postmeta,['post_id'=>$post->ID]);$wpdb->delete($wpdb->term_relationships,['object_id'=>$post->ID]);
    $wpdb->update($wpdb->posts,['post_parent'=>$post->post_parent],['post_parent'=>$post->ID]);
    do_action('delete_post',$postid,$post);$wpdb->delete($wpdb->posts,['ID'=>$post->ID]);rrw_wp_post_cache_clear((int)$post->ID);
    do_action('deleted_post',$postid,$post);do_action('after_delete_post',$postid,$post);
    return $post;
}
function wp_trash_post($post_id=0) { $p=get_post($post_id);if(!$p||(rrw_wp_is_cms_id((int)$p->ID)&&!rrw_wp_cms_bridged_id((int)$p->ID))||$p->post_status==='trash')return false;add_post_meta($p->ID,'_wp_trash_meta_status',$p->post_status);do_action('wp_trash_post',$post_id);wp_update_post(['ID'=>$p->ID,'post_status'=>'trash']);do_action('trashed_post',$post_id);return get_post($p->ID); }
function wp_untrash_post($post_id=0) { $p=get_post($post_id);if(!$p||$p->post_status!=='trash')return false;$st=get_post_meta($p->ID,'_wp_trash_meta_status',true)?:'draft';delete_post_meta($p->ID,'_wp_trash_meta_status');wp_update_post(['ID'=>$p->ID,'post_status'=>$st]);do_action('untrashed_post',$post_id);return get_post($p->ID); }
function wp_publish_post($post) { $p=get_post($post);if(!$p||$p->post_status==='publish')return;wp_update_post(['ID'=>$p->ID,'post_status'=>'publish']); }
function wp_set_post_lock($post_id) { return false; }
function wp_is_post_revision($post) { $p=get_post($post);return $p&&$p->post_type==='revision'?(int)$p->post_parent:false; }
function wp_is_post_autosave($post) { return false; }
function wp_save_post_revision($post_id) { return null; }
function wp_get_post_revisions($post=0, $args=null) { return []; }
function post_exists($title, $content='', $date='', $type='', $status='') { global $wpdb;return (int)$wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_title = %s LIMIT 1",wp_unslash($title))); }

/* Seiten */
function get_pages($args=[]) {
    $r=wp_parse_args($args,['child_of'=>0,'sort_order'=>'ASC','sort_column'=>'post_title','hierarchical'=>1,'exclude'=>[],'include'=>[],'meta_key'=>'','meta_value'=>'','authors'=>'','parent'=>-1,'exclude_tree'=>[],'number'=>'','offset'=>0,'post_type'=>'page','post_status'=>'publish']);
    $q=new WP_Query(['post_type'=>$r['post_type'],'post_status'=>$r['post_status'],'posts_per_page'=>-1,'no_found_rows'=>true,'suppress_filters'=>true]);$pages=$q->posts;
    $inc=wp_parse_id_list($r['include']);$exc=wp_parse_id_list($r['exclude']);
    $pages=array_values(array_filter($pages,function($p) use($r,$inc,$exc){ if($inc&&!in_array((int)$p->ID,$inc,true))return false;if($exc&&in_array((int)$p->ID,$exc,true))return false;if($r['parent']>=0&&(int)$p->post_parent!==(int)$r['parent'])return false;if($r['child_of']&&!in_array((int)$r['child_of'],get_post_ancestors($p),true))return false;return true; }));
    $col=preg_replace('/[^a-z_]/','',strtolower((string)$r['sort_column']));$col=in_array($col,['post_title','post_date','post_modified','menu_order','id','post_name'],true)?($col==='id'?'ID':$col):'post_title';
    usort($pages,fn($a,$b)=>strtoupper((string)$r['sort_order'])==='DESC'?($b->$col<=>$a->$col):($a->$col<=>$b->$col));
    if($r['number']!=='')$pages=array_slice($pages,(int)$r['offset'],(int)$r['number']);
    return apply_filters('get_pages',$pages,$r);
}
function get_page_by_path($page_path, $output=OBJECT, $post_type='page') {
    $path=trim(rawurldecode((string)$page_path),'/');$last=basename($path);$types=(array)$post_type;
    foreach(get_posts(['post_type'=>$types,'name'=>$last,'post_status'=>'any','posts_per_page'=>1,'suppress_filters'=>true]) as $p)return $output===OBJECT?$p:($output===ARRAY_A?$p->to_array():array_values($p->to_array()));
    return null;
}
function get_page_by_title($page_title, $output=OBJECT, $post_type='page') { foreach(get_posts(['post_type'=>$post_type,'post_status'=>'any','posts_per_page'=>-1,'suppress_filters'=>true]) as $p)if($p->post_title===$page_title)return $p;return null; }
function get_page_uri($page=0) { $p=get_post($page);if(!$p)return false;$parts=[$p->post_name];foreach(get_post_ancestors($p) as $a){$x=get_post($a);if($x)array_unshift($parts,$x->post_name);}return implode('/',$parts); }
function get_page($p) { return get_post($p); }

/* Anhänge */
function wp_get_attachment_url($attachment_id=0) { $u=rrw_wp_cms_attachment_url((int)$attachment_id);if($u!==null)return $u;$p=get_post($attachment_id);if(!$p||$p->post_type!=='attachment')return false;$f=get_post_meta($p->ID,'_wp_attached_file',true);return $f?content_url('uploads/'.ltrim($f,'/')):($p->guid?:false); }
function wp_get_attachment_image_src($attachment_id, $size='thumbnail', $icon=false) { if(($cm=rrw_wp_cms_media_image((int)$attachment_id,$size))!==null)return $cm;$u=wp_get_attachment_url($attachment_id);if(!$u)return false;$m=get_post_meta($attachment_id,'_wp_attachment_metadata',true);return [$u,(int)($m['width']??0),(int)($m['height']??0),false]; }
function wp_get_attachment_metadata($attachment_id=0, $unfiltered=false) { if(($cm=rrw_wp_cms_media_meta((int)$attachment_id))!==null)return $cm;$m=get_post_meta((int)$attachment_id,'_wp_attachment_metadata',true);return $m?:false; }
function wp_update_attachment_metadata($attachment_id, $data) { return update_post_meta($attachment_id,'_wp_attachment_metadata',$data); }
function get_attachment_link($post=null) { return (string)wp_get_attachment_url(is_object($post)?$post->ID:$post); }
function wp_attachment_is_image($post=null) { $p=get_post($post);return $p&&strpos((string)$p->post_mime_type,'image/')===0||rrw_wp_cms_attachment_url((int)($p->ID??0))!==null; }
function get_intermediate_image_sizes() { return ['thumbnail','medium','medium_large','large']; }
function wp_get_registered_image_subsizes() { return ['thumbnail'=>['width'=>150,'height'=>150,'crop'=>true],'medium'=>['width'=>300,'height'=>300,'crop'=>false],'medium_large'=>['width'=>768,'height'=>0,'crop'=>false],'large'=>['width'=>1024,'height'=>1024,'crop'=>false]]; }
function add_image_size($name, $width=0, $height=0, $crop=false) { $GLOBALS['_wp_additional_image_sizes'][$name]=compact('width','height','crop'); }
function set_post_thumbnail_size($w=0,$h=0,$crop=false) {}
function wp_get_attachment_thumb_url($id=0) { return wp_get_attachment_url($id); }
function wp_get_attachment_image($attachment_id, $size='thumbnail', $icon=false, $attr='') { $s=wp_get_attachment_image_src($attachment_id,$size);if(!$s)return '';return '<img src="'.esc_url($s[0]).'" alt="'.esc_attr((string)(get_post_meta($attachment_id,'_wp_attachment_image_alt',true))).'" loading="lazy" />'; }
function wp_get_attachment_caption($post_id=0) { $p=get_post($post_id);return $p?$p->post_excerpt:false; }
function wp_attachment_is($type, $post=null) { return $type==='image'&&wp_attachment_is_image($post); }
function get_attached_file($attachment_id, $unfiltered=false) { if(($cm=rrw_wp_cms_media_file((int)$attachment_id))!==null)return $cm;$f=get_post_meta($attachment_id,'_wp_attached_file',true);return $f?WP_CONTENT_DIR.'/uploads/'.ltrim($f,'/'):false; }

/* Beitrags-Formate, Titelbild */
function has_post_thumbnail($post=null) { return (bool)get_post_thumbnail_id($post); }
function get_post_thumbnail_id($post=null) {
    $p=get_post($post);if(!$p)return 0;
    if($p->rrw_source==='news'){ $u=(string)($p->rrw_data['image_url']??'');return $u!==''&&($p->rrw_data['image_mode']??'thumbnail')!=='none'?rrw_wp_cms_attachment_id($u):0; }
    return (int)get_post_meta($p->ID,'_thumbnail_id',true);
}
function set_post_thumbnail($post, $thumbnail_id) { $p=get_post($post);return $p&&update_post_meta($p->ID,'_thumbnail_id',$thumbnail_id); }   // bei CMS-Beiträgen schreibt der Filter update_post_metadata (cms-write.php) ins Feld image_url
function delete_post_thumbnail($post) { $p=get_post($post);return $p&&delete_post_meta($p->ID,'_thumbnail_id'); }
function get_the_post_thumbnail_url($post=null, $size='post-thumbnail') { $id=get_post_thumbnail_id($post);return $id?wp_get_attachment_url($id):false; }
function get_the_post_thumbnail($post=null, $size='post-thumbnail', $attr='') { $id=get_post_thumbnail_id($post);if(!$id)return '';$u=wp_get_attachment_url($id);$a=is_array($attr)?$attr:[];return '<img src="'.esc_url($u).'" class="'.esc_attr($a['class']??('attachment-'.(is_string($size)?$size:'post-thumbnail').' wp-post-image')).'" alt="'.esc_attr($a['alt']??get_the_title($post)).'" loading="lazy" />'; }
function get_post_format($post=null) { return false; }
function set_post_format($post,$format) { return true; }

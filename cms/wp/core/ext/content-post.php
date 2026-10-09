<?php
// Ergänzende Beitrags-Funktionen (wp-includes/post.php): Typen/Rechte, Anhänge, MIME, Pings, Seitenhierarchie, Caches, Statusübergänge.
// Nur wp_posts wird geschrieben; CMS-Inhalte (news.json/site.json) bleiben schreibgeschützt.

/* ───────── Typen, Rechte, Beschriftungen ───────── */
if(!function_exists('create_initial_post_types')){ function create_initial_post_types() { if(!post_type_exists('post'))elvado_wp_register_default_types(); } }
if(!function_exists('get_page_statuses')){ function get_page_statuses() { return ['draft'=>'Entwurf','private'=>'Privat','publish'=>'Veröffentlicht']; } }
if(!function_exists('_wp_privacy_statuses')){ function _wp_privacy_statuses() { return apply_filters('_wp_privacy_statuses',['request-pending'=>'Ausstehend','request-confirmed'=>'Bestätigt','request-failed'=>'Fehlgeschlagen','request-completed'=>'Abgeschlossen']); } }
if(!function_exists('get_post_type_capabilities')){
    function get_post_type_capabilities($args) {
        $args=(object)(array)$args;$ct=$args->capability_type??'post';
        if(!is_array($ct))$ct=[$ct,$ct.'s'];
        [$s,$p]=array_pad(array_values($ct),2,null);if(!$p)$p=$s.'s';
        $caps=['edit_post'=>"edit_$s",'read_post'=>"read_$s",'delete_post'=>"delete_$s",'edit_posts'=>"edit_$p",'edit_others_posts'=>"edit_others_$p",'delete_posts'=>"delete_$p",'publish_posts'=>"publish_$p",'read_private_posts'=>"read_private_$p"];
        if(!empty($args->map_meta_cap))$caps+=['read'=>'read','delete_private_posts'=>"delete_private_$p",'delete_published_posts'=>"delete_published_$p",'delete_others_posts'=>"delete_others_$p",'edit_private_posts'=>"edit_private_$p",'edit_published_posts'=>"edit_published_$p"];
        $caps=array_merge($caps,(array)($args->capabilities??[]));
        if(!isset($caps['create_posts']))$caps['create_posts']=$caps['edit_posts'];   // Anlegen entspricht standardmäßig edit_posts
        return (object)$caps;
    }
}
if(!function_exists('_post_type_meta_capabilities')){
    function _post_type_meta_capabilities($capabilities=null) {
        global $post_type_meta_caps;if(!is_array($post_type_meta_caps))$post_type_meta_caps=[];
        foreach((array)$capabilities as $core=>$custom)if(in_array($core,['read_post','delete_post','edit_post'],true))$post_type_meta_caps[$custom]=$core;
    }
}
if(!function_exists('_get_custom_object_labels')){
    /** Ergänzt $data_object->labels um Vorgaben: $defaults = [Schlüssel=>[nicht hierarchisch, hierarchisch]]. */
    function _get_custom_object_labels($data_object, $defaults=[]) {
        $l=(array)($data_object->labels??[]);
        if(isset($data_object->label)&&empty($l['name']))$l['name']=$data_object->label;
        if(!isset($l['singular_name'])&&isset($l['name']))$l['singular_name']=$l['name'];
        if(!isset($l['name_admin_bar'])&&isset($l['singular_name']))$l['name_admin_bar']=$l['singular_name'];
        if(!isset($l['menu_name'])&&isset($l['name']))$l['menu_name']=$l['name'];
        $d=[];foreach((array)$defaults as $k=>$v)$d[$k]=is_array($v)?(!empty($data_object->hierarchical)?($v[1]??$v[0]):$v[0]):$v;
        $l=array_merge($d,array_filter($l,fn($x)=>$x!==null&&$x!==''));
        $data_object->labels=(object)$l;$data_object->label=$l['name']??($data_object->label??'');
        return $data_object->labels;
    }
}
if(!function_exists('_add_post_type_submenus')){
    // Legt Untermenüs für Beitragstypen an, deren show_in_menu ein Menü-Slug (Text) ist.
    function _add_post_type_submenus() {
        foreach(get_post_types(['show_ui'=>true],'objects') as $pt){
            if(!is_string($pt->show_in_menu)||$pt->show_in_menu==='')continue;
            add_submenu_page($pt->show_in_menu,$pt->labels->name,$pt->labels->all_items??$pt->label,$pt->cap->edit_posts,'edit.php?post_type='.$pt->name);
        }
    }
}
if(!function_exists('set_post_type')){
    function set_post_type($post_id=0, $post_type='post') {
        global $wpdb;$post_type=sanitize_key($post_type);$id=(int)$post_id;
        if($id<ELVADO_WP_ID_DB_MIN||!$wpdb||!elvado_wp_db_ready())return 0;
        $r=$wpdb->update($wpdb->posts,['post_type'=>$post_type],['ID'=>$id]);clean_post_cache($id);return $r;
    }
}
if(!function_exists('is_post_publicly_viewable')){
    function is_post_publicly_viewable($post=null) {
        $p=get_post($post);if(!$p)return false;
        $s=get_post_status_object($p->post_status);
        return is_post_type_viewable($p->post_type)&&$s&&(!empty($s->publicly_queryable)||!empty($s->public));
    }
}
if(!function_exists('is_post_embeddable')){
    function is_post_embeddable($post=null) {
        $p=get_post($post);if(!$p)return false;$pt=get_post_type_object($p->post_type);
        return (bool)apply_filters('is_post_embeddable',is_post_publicly_viewable($p)&&($pt&&(!isset($pt->embeddable)||$pt->embeddable)),$p);
    }
}
if(!function_exists('unregister_post_meta')){ function unregister_post_meta($post_type, $meta_key) { return unregister_meta_key('post',$meta_key,$post_type); } }
if(!function_exists('_count_posts_cache_key')){
    function _count_posts_cache_key($type='post', $perm='') {
        $k='posts-'.$type;
        if('readable'===$perm&&is_user_logged_in()){ $o=get_post_type_object($type);if($o&&!current_user_can($o->cap->read_private_posts))$k.='_'.$perm.'_'.get_current_user_id(); }
        return $k;
    }
}
if(!function_exists('wp_cache_set_posts_last_changed')){ function wp_cache_set_posts_last_changed() { wp_cache_set('last_changed',microtime(),'posts'); } }

/* ───────── Anhänge und MIME ───────── */
if(!function_exists('_wp_relative_upload_path')){
    function _wp_relative_upload_path($path) {
        $new=$path;$u=wp_get_upload_dir();
        if(!empty($u['basedir'])&&str_starts_with($new,$u['basedir']))$new=ltrim(substr($new,strlen($u['basedir'])),'/\\');
        return apply_filters('_wp_relative_upload_path',$new,$path);
    }
}
if(!function_exists('update_attached_file')){
    function update_attached_file($attachment_id, $file) {
        if(!get_post($attachment_id))return false;
        $file=apply_filters('update_attached_file',$file,$attachment_id);
        if(!$file=_wp_relative_upload_path($file))return delete_post_meta($attachment_id,'_wp_attached_file');
        return update_post_meta($attachment_id,'_wp_attached_file',$file);
    }
}
if(!function_exists('get_post_mime_types')){
    function get_post_mime_types() {
        $t=['image'=>['Bilder','Bilder verwalten',_n_noop('Bild <span class="count">(%s)</span>','Bilder <span class="count">(%s)</span>')],
            'audio'=>['Audio','Audio verwalten',_n_noop('Audio <span class="count">(%s)</span>','Audio <span class="count">(%s)</span>')],
            'video'=>['Video','Videos verwalten',_n_noop('Video <span class="count">(%s)</span>','Videos <span class="count">(%s)</span>')],
            'document'=>['Dokumente','Dokumente verwalten',_n_noop('Dokument <span class="count">(%s)</span>','Dokumente <span class="count">(%s)</span>')],
            'spreadsheet'=>['Tabellen','Tabellen verwalten',_n_noop('Tabelle <span class="count">(%s)</span>','Tabellen <span class="count">(%s)</span>')],
            'archive'=>['Archive','Archive verwalten',_n_noop('Archiv <span class="count">(%s)</span>','Archive <span class="count">(%s)</span>')]];
        return apply_filters('post_mime_types',$t);
    }
}
if(!function_exists('wp_match_mime_types')){
    /** Ordnet echte MIME-Typen den Platzhaltern zu (z. B. „image/*“, „image“, „image/png“) → [Platzhalter=>[Typen]]. */
    function wp_match_mime_types($wildcard_mime_types, $real_mime_types) {
        $w=is_array($wildcard_mime_types)?$wildcard_mime_types:array_filter(array_map('trim',explode(',',(string)$wildcard_mime_types)));
        $r=is_array($real_mime_types)?$real_mime_types:array_filter(array_map('trim',explode(',',(string)$real_mime_types)));
        $out=[];
        foreach($w as $card){
            if($card==='*'||$card==='*/*')$re='#^.+$#i';
            elseif(!str_contains($card,'/'))$re='#^'.preg_quote($card,'#').'/#i';
            else{ [$a,$b]=explode('/',$card,2);$re='#^'.preg_quote($a,'#').'/'.($b==='*'?'.+':preg_quote($b,'#')).'$#i'; }
            foreach($r as $m)if(preg_match($re,$m))$out[$card][]=$m;
        }
        return $out;
    }
}
if(!function_exists('wp_post_mime_type_where')){
    function wp_post_mime_type_where($post_mime_types, $table_alias='') {
        global $wpdb;$types=is_array($post_mime_types)?$post_mime_types:array_filter(array_map('trim',explode(',',(string)$post_mime_types)));
        $col=($table_alias?$table_alias.'.':'').'post_mime_type';$cl=[];
        foreach($types as $t){
            $t=preg_replace('/[^-*.+a-zA-Z0-9\/_]/','',(string)$t);if($t===''||$t==='*'||$t==='*/*')continue;
            if(!str_contains($t,'/'))$like=$t.'/%';else{ [$a,$b]=explode('/',$t,2);$like=$a.'/'.($b===''||$b==='*'?'%':str_replace('*','%',$b)); }
            $cl[]=$wpdb->prepare("$col LIKE %s",$like);
        }
        return $cl?' AND ('.implode(' OR ',$cl).')':'';
    }
}
if(!function_exists('wp_count_attachments')){
    function wp_count_attachments($mime_type='') {
        global $wpdb;$and=wp_post_mime_type_where($mime_type);$c=[];
        if($wpdb&&elvado_wp_db_ready()){
            foreach((array)$wpdb->get_results("SELECT post_mime_type, COUNT(*) AS num_posts FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status != 'trash' $and GROUP BY post_mime_type",ARRAY_A) as $r)$c[$r['post_mime_type']]=(int)$r['num_posts'];
            $c['trash']=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'trash' $and");
        }
        return apply_filters('wp_count_attachments',(object)$c,$mime_type);
    }
}
if(!function_exists('get_available_post_mime_types')){
    function get_available_post_mime_types($type='attachment') {
        global $wpdb;if(!$wpdb||!elvado_wp_db_ready())return [];
        $t=$wpdb->get_col($wpdb->prepare("SELECT DISTINCT post_mime_type FROM {$wpdb->posts} WHERE post_type = %s",$type));
        return apply_filters('get_available_post_mime_types',array_values(array_filter((array)$t)),$type);
    }
}
if(!function_exists('is_local_attachment')){
    function is_local_attachment($url) {
        if(!str_contains($url,home_url()))return false;
        if(str_contains($url,home_url('/?attachment_id=')))return true;
        $id=url_to_postid($url);if($id){ $p=get_post($id);if($p&&'attachment'===$p->post_type)return true; }
        return false;
    }
}
if(!function_exists('wp_delete_attachment_files')){
    function wp_delete_attachment_files($post_id, $meta, $backup_sizes, $file) {
        $u=wp_get_upload_dir();$ok=true;$file=(string)$file;
        $del=function($rel) use($u,$file,&$ok){ if($rel==='')return;$p=path_join($u['basedir'],$rel);if(is_file($p)&&!wp_delete_file_from_directory($p,$u['basedir']))$ok=false; };
        if(is_array($meta)){
            $dir=dirname($file);
            foreach((array)($meta['sizes']??[]) as $s)if(!empty($s['file']))$del(($dir==='.'?'':$dir.'/').$s['file']);
            if(!empty($meta['original_image']))$del(($dir==='.'?'':$dir.'/').$meta['original_image']);
        }
        if(is_array($backup_sizes)){ $dir=dirname($file);foreach($backup_sizes as $s)if(!empty($s['file']))$del(($dir==='.'?'':$dir.'/').$s['file']); }
        if($file!=='')$del($file);
        return $ok;
    }
}
if(!function_exists('wp_mime_type_icon')){
    // Es werden keine Symbolbilder mitgeliefert: Standard ist false; Themes/Plugins können über den Filter eine Adresse liefern.
    function wp_mime_type_icon($mime=0, $preferred_size='64x64') { return apply_filters('wp_mime_type_icon',false,$mime,is_numeric($mime)?(int)$mime:0); }
}
if(!function_exists('clean_attachment_cache')){
    function clean_attachment_cache($id, $clean_terms=false) { clean_post_cache((int)$id);do_action('clean_attachment_cache',(int)$id); }
}
if(!function_exists('wp_get_original_image_path')){
    function wp_get_original_image_path($attachment_id, $unfiltered=false) {
        if(!wp_attachment_is_image($attachment_id))return false;
        $m=wp_get_attachment_metadata($attachment_id);$f=get_attached_file($attachment_id,$unfiltered);
        $o=empty($m['original_image'])?$f:path_join(dirname((string)$f),$m['original_image']);
        return apply_filters('wp_get_original_image_path',$o,$attachment_id);
    }
}
if(!function_exists('wp_get_original_image_url')){
    function wp_get_original_image_url($attachment_id) {
        if(!wp_attachment_is_image($attachment_id))return false;
        $u=wp_get_attachment_url($attachment_id);if(!$u)return false;
        $m=wp_get_attachment_metadata($attachment_id);
        if(!empty($m['original_image']))$u=path_join(dirname($u),$m['original_image']);
        return apply_filters('wp_get_original_image_url',$u,$attachment_id);
    }
}

/* ───────── Kommentare, Startseiten-Einstellungen ───────── */
if(!function_exists('_reset_front_page_settings_for_post')){
    function _reset_front_page_settings_for_post($post_id) {
        $p=get_post($post_id);if(!$p)return;
        if('page'===$p->post_type){
            if((int)get_option('page_on_front')===(int)$p->ID){ update_option('show_on_front','posts');update_option('page_on_front',0); }
            if((int)get_option('page_for_posts')===(int)$p->ID)update_option('page_for_posts',0);
        }
        unstick_post($p->ID);
    }
}
if(!function_exists('wp_trash_post_comments')){
    function wp_trash_post_comments($post=null) {
        global $wpdb;$p=get_post($post);if(!$p)return false;$id=(int)$p->ID;if(!elvado_wp_db_ready())return false;
        do_action('trash_post_comments',$id);
        $rows=$wpdb->get_results($wpdb->prepare("SELECT comment_ID, comment_approved FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_approved NOT IN ('trash','post-trashed','spam')",$id),ARRAY_A);
        $st=[];foreach((array)$rows as $r)$st[(int)$r['comment_ID']]=$r['comment_approved'];
        if($st){ add_post_meta($id,'_wp_trash_meta_comments_status',$st);
            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->comments} SET comment_approved = 'post-trashed' WHERE comment_post_ID = %d AND comment_approved NOT IN ('trash','post-trashed','spam')",$id)); }
        do_action('trashed_post_comments',$id,$st);return true;
    }
}
if(!function_exists('wp_untrash_post_comments')){
    function wp_untrash_post_comments($post=null) {
        global $wpdb;$p=get_post($post);if(!$p)return;$id=(int)$p->ID;if(!elvado_wp_db_ready())return;
        $st=get_post_meta($id,'_wp_trash_meta_comments_status',true);if(empty($st)||!is_array($st))return true;
        do_action('untrash_post_comments',$id);
        $by=[];foreach($st as $cid=>$s)$by[$s][]=(int)$cid;
        foreach($by as $s=>$ids)$wpdb->query($wpdb->prepare("UPDATE {$wpdb->comments} SET comment_approved = %s WHERE comment_ID IN (".implode(',',array_map('intval',$ids)).") AND comment_post_ID = %d",$s,$id));
        delete_post_meta($id,'_wp_trash_meta_comments_status');
        do_action('untrashed_post_comments',$id);return true;
    }
}

/* ───────── Abfragen, Veröffentlichen, Slugs, Datum ───────── */
if(!function_exists('wp_get_recent_posts')){
    function wp_get_recent_posts($args=[], $output=ARRAY_A) {
        $d=['numberposts'=>10,'offset'=>0,'category'=>0,'orderby'=>'post_date','order'=>'DESC','include'=>'','exclude'=>'','meta_key'=>'','meta_value'=>'','post_type'=>'post','post_status'=>'draft, publish, future, pending, private','suppress_filters'=>true];
        $r=wp_parse_args($args,$d);
        if(is_string($r['post_status']))$r['post_status']=array_values(array_filter(array_map('trim',explode(',',$r['post_status']))));
        $res=get_posts($r);if(!is_array($res))return false;
        return ARRAY_A===$output?array_map(fn($p)=>$p->to_array(),$res):$res;
    }
}
if(!function_exists('check_and_publish_future_post')){
    function check_and_publish_future_post($post) {
        $p=get_post($post);if(!$p||'future'!==$p->post_status)return;
        $t=strtotime($p->post_date_gmt.' GMT');
        if($t>time()){ wp_clear_scheduled_hook('publish_future_post',[$p->ID]);wp_schedule_single_event($t,'publish_future_post',[$p->ID]);return; }
        wp_publish_post($p);
    }
}
if(!function_exists('wp_resolve_post_date')){
    function wp_resolve_post_date($post_date='', $post_date_gmt='') {
        $z='0000-00-00 00:00:00';
        if(empty($post_date)||$z===$post_date)$post_date=(empty($post_date_gmt)||$z===$post_date_gmt)?current_time('mysql'):get_date_from_gmt($post_date_gmt);
        if(false===strtotime($post_date))return false;
        if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})/',$post_date,$m)||!checkdate((int)$m[2],(int)$m[3],(int)$m[1]))return false;
        return $post_date;
    }
}
if(!function_exists('_truncate_post_slug')){
    function _truncate_post_slug($slug, $length=200) {
        if(strlen($slug)<=$length)return $slug;
        $dec=urldecode($slug);
        if($dec===$slug)return rtrim(substr($slug,0,$length),'-');
        $out='';foreach(preg_split('//u',$dec,-1,PREG_SPLIT_NO_EMPTY) as $c){ $e=rawurlencode($c);if(strlen($out)+strlen($e)>$length)break;$out.=$e; }
        return rtrim($out,'-');
    }
}
if(!function_exists('wp_add_post_tags')){ function wp_add_post_tags($post_id=0, $tags='') { return wp_set_post_tags($post_id,$tags,true); } }
if(!function_exists('wp_after_insert_post')){
    function wp_after_insert_post($post, $update, $post_before) { $p=get_post($post);if(!$p)return;do_action('wp_after_insert_post',$p->ID,$p,$update,$post_before); }
}
if(!function_exists('wp_check_for_changed_slugs')){
    function wp_check_for_changed_slugs($post_id, $post, $post_before) {
        if(!strlen((string)$post->post_name)||$post->post_name===$post_before->post_name)return;
        if(!('publish'===$post->post_status||('attachment'===$post->post_type&&'inherit'===$post->post_status))||is_post_type_hierarchical($post->post_type))return;
        $old=(array)get_post_meta($post_id,'_wp_old_slug');
        if(!empty($post_before->post_name)&&!in_array($post_before->post_name,$old,true))add_post_meta($post_id,'_wp_old_slug',$post_before->post_name);
        if(in_array($post->post_name,$old,true))delete_post_meta($post_id,'_wp_old_slug',$post->post_name);
    }
}
if(!function_exists('wp_check_for_changed_dates')){
    function wp_check_for_changed_dates($post_id, $post, $post_before) {
        $prev=gmdate('Y-m-d',(int)strtotime($post_before->post_date));$new=gmdate('Y-m-d',(int)strtotime($post->post_date));
        if($prev===$new)return;
        if(!('publish'===$post->post_status||('attachment'===$post->post_type&&'inherit'===$post->post_status))||is_post_type_hierarchical($post->post_type))return;
        $old=(array)get_post_meta($post_id,'_wp_old_date');
        if(!empty($post_before->post_date)&&!in_array($prev,$old,true))add_post_meta($post_id,'_wp_old_date',$prev);
        if(in_array($new,$old,true))delete_post_meta($post_id,'_wp_old_date',$new);
    }
}
if(!function_exists('wp_check_post_hierarchy_for_loops')){
    /** Gibt 0 zurück, wenn $post_parent den Beitrag selbst oder einen seiner Nachfahren bezeichnet (Schleife), sonst $post_parent. */
    function wp_check_post_hierarchy_for_loops($post_parent, $post_ID) {
        if(!$post_parent||(int)$post_parent===(int)$post_ID)return 0;
        $seen=[];$cur=(int)$post_parent;
        while($cur&&!isset($seen[$cur])){ if($cur===(int)$post_ID)return 0;$seen[$cur]=1;$cur=(int)wp_get_post_parent_id($cur); }
        return (int)$post_parent;
    }
}
if(!function_exists('wp_delete_auto_drafts')){
    function wp_delete_auto_drafts() {
        global $wpdb;if(!$wpdb||!elvado_wp_db_ready())return;
        $ids=$wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_status = 'auto-draft' AND post_date < %s",gmdate('Y-m-d H:i:s',time()-7*DAY_IN_SECONDS)));
        foreach((array)$ids as $id)wp_delete_post((int)$id,true);
    }
}
if(!function_exists('wp_add_trashed_suffix_to_post_name_for_post')){
    function wp_add_trashed_suffix_to_post_name_for_post($post) {
        global $wpdb;$post=get_post($post);if(!$post)return '';$n=(string)$post->post_name;
        if(str_ends_with($n,'__trashed'))return $n;
        add_post_meta($post->ID,'_wp_desired_post_slug',$n);$n.='__trashed';
        $wpdb->update($wpdb->posts,['post_name'=>$n],['ID'=>(int)$post->ID]);clean_post_cache($post->ID);return $n;
    }
}
if(!function_exists('wp_add_trashed_suffix_to_post_name_for_trashed_posts')){
    function wp_add_trashed_suffix_to_post_name_for_trashed_posts($post_name, $post_id=0) {
        $p=get_post($post_id);return $p&&'trash'===$p->post_status?wp_add_trashed_suffix_to_post_name_for_post($p):$post_name;
    }
}
if(!function_exists('wp_untrash_post_set_previous_status')){ function wp_untrash_post_set_previous_status($new_status, $post_id, $previous_status) { return $previous_status; } }
if(!function_exists('wp_create_initial_post_meta')){
    // Registriert die vom Kern vorgesehenen Meta-Schlüssel (Fußnoten).
    function wp_create_initial_post_meta() {
        foreach(['post','page'] as $t)register_post_meta($t,'footnotes',['type'=>'string','single'=>true,'show_in_rest'=>true,'revisions_enabled'=>true]);
    }
}

/* ───────── Pings, Anhänge (enclosures) ───────── */
if(!function_exists('add_ping')){
    function add_ping($post_id, $uri) {
        global $wpdb;$p=get_post($post_id);if(!$p||(int)$p->ID<ELVADO_WP_ID_DB_MIN)return false;
        $pung=preg_split('/\s/',trim((string)$p->pinged),-1,PREG_SPLIT_NO_EMPTY);
        $pung=is_array($uri)?array_merge($pung,$uri):array_merge($pung,[$uri]);
        $new=apply_filters('add_ping',implode("\n",$pung));
        $r=$wpdb->update($wpdb->posts,['pinged'=>$new],['ID'=>(int)$p->ID]);clean_post_cache($p->ID);return $r;
    }
}
if(!function_exists('get_enclosed')){
    function get_enclosed($post=0) {
        $p=get_post($post);if(!$p)return [];$out=[];
        foreach((array)get_post_meta($p->ID,'enclosure') as $enc){ $l=explode("\n",(string)$enc);$out[]=trim($l[0]); }
        return apply_filters('get_enclosed',$out,$p->ID);
    }
}
if(!function_exists('get_pung')){
    function get_pung($post) { $p=get_post($post);if(!$p)return false;return apply_filters('get_pung',preg_split('/\s/',trim((string)$p->pinged),-1,PREG_SPLIT_NO_EMPTY)); }
}
if(!function_exists('get_to_ping')){
    function get_to_ping($post) {
        $p=get_post($post);if(!$p)return false;$u=[];
        foreach(preg_split('/\s/',(string)$p->to_ping,-1,PREG_SPLIT_NO_EMPTY) as $x){ $x=esc_url_raw($x);if($x!=='')$u[]=$x; }
        return apply_filters('get_to_ping',$u);
    }
}
if(!function_exists('trackback_url_list')){
    // Trackbacks werden nicht gesendet (kein ausgehender Versand); es gibt nur die Möglichkeit, über eine vorhandene trackback()-Funktion zu senden.
    function trackback_url_list($tb_list, $post_id) {
        if(empty($tb_list)||!function_exists('trackback'))return;
        $d=get_post($post_id,ARRAY_A);if(!$d)return;
        $ex=strip_tags($d['post_excerpt']?:$d['post_content']);if(strlen($ex)>255)$ex=substr($ex,0,252).'&hellip;';
        foreach(explode(',',(string)$tb_list) as $u)trackback(trim($u),wp_unslash($d['post_title']),$ex,$post_id);
    }
}

/* ───────── Seiten-Hierarchie ───────── */
if(!function_exists('get_all_page_ids')){
    function get_all_page_ids() {
        $ids=wp_cache_get('all_page_ids','posts');
        if(!is_array($ids)){ $ids=array_map(fn($p)=>(int)$p->ID,get_posts(['post_type'=>'page','post_status'=>'any','numberposts'=>-1]));wp_cache_add('all_page_ids',$ids,'posts'); }
        return $ids;
    }
}
if(!function_exists('get_page_children')){
    function get_page_children($page_id, $pages) {
        $by=[];foreach((array)$pages as $p)$by[(int)$p->post_parent][]=$p;
        $out=[];$seen=[];$walk=function($id) use(&$walk,&$out,&$seen,$by){ foreach($by[$id]??[] as $c){ if(isset($seen[$c->ID]))continue;$seen[$c->ID]=1;$out[]=$c;$walk((int)$c->ID); } };
        $walk((int)$page_id);return $out;
    }
}
if(!function_exists('_page_traverse_name')){
    function _page_traverse_name($page_id, &$children, &$result) {
        foreach((array)($children[$page_id]??[]) as $c){ if(isset($result[$c->ID]))continue;$result[$c->ID]=$c->post_name;_page_traverse_name($c->ID,$children,$result); }
    }
}
if(!function_exists('get_page_hierarchy')){
    function get_page_hierarchy(&$pages, $page_id=0) {
        if(empty($pages))return [];$ch=[];foreach((array)$pages as $p)$ch[(int)$p->post_parent][]=$p;
        $r=[];_page_traverse_name((int)$page_id,$ch,$r);return $r;
    }
}

/* ───────── SQL-Bausteine für Rechte, letzte Beiträge ───────── */
if(!function_exists('get_posts_by_author_sql')){
    function get_posts_by_author_sql($post_type, $full=true, $post_author=null, $public_only=false) {
        global $wpdb;$types=is_array($post_type)?$post_type:[$post_type];$cl=[];
        foreach($types as $t){
            $o=get_post_type_object($t);if(!$o)continue;
            $cap=apply_filters('pub_priv_sql_capability','');if(!$cap)$cap=current_user_can($o->cap->read_private_posts);
            $st="post_status = 'publish'";
            if(false===$public_only){
                if($cap)$st.=" OR post_status = 'private'";
                elseif(is_user_logged_in()){ $uid=get_current_user_id();
                    if(null===$post_author||!$full)$st.=" OR post_status = 'private' AND post_author = $uid";elseif($uid==(int)$post_author)$st.=" OR post_status = 'private'"; }
            }
            $cl[]="( post_type = '".esc_sql($t)."' AND ( $st ) )";
        }
        if(!$cl)return $full?'WHERE 1 = 0':'1 = 0';
        $sql='( '.implode(' OR ',$cl).' )';
        if(null!==$post_author)$sql.=$wpdb->prepare(' AND post_author = %d',$post_author);
        if($full)$sql='WHERE '.$sql;
        return apply_filters('posts_by_author_sql',$sql,$post_type,$full,$post_author,$public_only);
    }
}
if(!function_exists('get_private_posts_cap_sql')){ function get_private_posts_cap_sql($post_type) { return get_posts_by_author_sql($post_type,false); } }
if(!function_exists('_get_last_post_time')){
    /** Zeitpunkt des jüngsten veröffentlichten Beitrags (wp_posts und CMS-Beiträge). $timezone: server|blog|gmt, $field: date|modified. */
    function _get_last_post_time($timezone, $field, $post_type='any') {
        global $wpdb;if(!in_array($field,['date','modified'],true))return false;
        $tz=strtolower((string)$timezone);$key="lastpost{$field}:$tz".('any'!==$post_type?':'.sanitize_key(is_array($post_type)?implode(',',$post_type):$post_type):'');
        $c=wp_cache_get($key,'timeinfo');if(false!==$c)return $c;
        $types='any'===$post_type?array_values(get_post_types(['public'=>true])):(array)$post_type;
        $col='blog'===$tz?"post_{$field}":"post_{$field}_gmt";$best='';
        if($wpdb&&elvado_wp_db_ready()&&$types)$best=(string)$wpdb->get_var("SELECT $col FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN (".implode(',',array_map(fn($t)=>"'".esc_sql($t)."'",$types)).") ORDER BY $col DESC LIMIT 1");
        $cms=[];if(in_array('post',$types,true))$cms=array_merge($cms,elvado_wp_cms_posts());if(in_array('page',$types,true))$cms=array_merge($cms,elvado_wp_cms_pages());
        foreach($cms as $p)if($p->$col>$best)$best=$p->$col;
        if($best==='')return false;
        if('server'===$tz)$best=date('Y-m-d H:i:s',(int)strtotime($best.' UTC'));   // GMT → Zeitzone des Servers
        wp_cache_set($key,$best,'timeinfo');return $best;
    }
}
if(!function_exists('get_lastpostdate')){
    function get_lastpostdate($timezone='server', $post_type='any') { return apply_filters('get_lastpostdate',_get_last_post_time($timezone,'date',$post_type),$timezone,$post_type); }
}
if(!function_exists('get_lastpostmodified')){
    function get_lastpostmodified($timezone='server', $post_type='any') {
        $m=_get_last_post_time($timezone,'modified',$post_type);$d=get_lastpostdate($timezone,$post_type);
        if($d&&$d>$m)$m=$d;
        return apply_filters('get_lastpostmodified',$m,$timezone,$post_type);
    }
}

/* ───────── Caches (die Schicht cached nur wp_posts-Zeilen im Arbeitsspeicher) ───────── */
if(!function_exists('update_post_cache')){
    function update_post_cache(&$posts) { if(!$posts)return;foreach((array)$posts as $p)if($p instanceof WP_Post&&(int)$p->ID>=ELVADO_WP_ID_DB_MIN)elvado_wp_post_cache_set((int)$p->ID,$p); }
}
if(!function_exists('update_post_author_caches')){ function update_post_author_caches($posts) {} }   // Benutzer werden nicht zwischengespeichert
if(!function_exists('update_post_parent_caches')){ function update_post_parent_caches($posts) {} }
if(!function_exists('_prime_post_parent_id_caches')){ function _prime_post_parent_id_caches(array $ids) {} }
if(!function_exists('wp_queue_posts_for_term_meta_lazyload')){ function wp_queue_posts_for_term_meta_lazyload($posts) {} }   // Meta wird bei Bedarf gelesen, nichts vorzuladen

/* ───────── Statusübergänge (Hook-Funktionen) ───────── */
if(!function_exists('_transition_post_status')){
    function _transition_post_status($new_status, $old_status, $post) {
        global $wpdb;
        if('publish'!==$old_status&&'publish'===$new_status&&(int)$post->ID>=ELVADO_WP_ID_DB_MIN&&$wpdb&&''===get_the_guid($post->ID))$wpdb->update($wpdb->posts,['guid'=>get_permalink($post->ID)],['ID'=>(int)$post->ID]);
        if('publish'===$new_status||'publish'===$old_status)foreach(['server','gmt','blog'] as $tz){ wp_cache_delete("lastpostmodified:$tz",'timeinfo');wp_cache_delete("lastpostdate:$tz",'timeinfo');wp_cache_delete("lastpostdate:$tz:{$post->post_type}",'timeinfo'); }
        if($new_status!==$old_status){ wp_cache_delete(_count_posts_cache_key($post->post_type),'counts');wp_cache_delete(_count_posts_cache_key($post->post_type,'readable'),'counts'); }
        wp_cache_set_posts_last_changed();
    }
}
if(!function_exists('_future_post_hook')){
    function _future_post_hook($deprecated, $post) { wp_clear_scheduled_hook('publish_future_post',[$post->ID]);wp_schedule_single_event(strtotime($post->post_date_gmt.' GMT'),'publish_future_post',[$post->ID]); }
}
if(!function_exists('_publish_post_hook')){
    function _publish_post_hook($post_id) {
        if(defined('XMLRPC_REQUEST'))do_action('xmlrpc_publish_post',$post_id);
        if(defined('WP_IMPORTING'))return;
        if(get_option('default_pingback_flag'))add_post_meta($post_id,'_pingme','1',true);
        add_post_meta($post_id,'_encloseme','1',true);
        if(get_to_ping($post_id))add_post_meta($post_id,'_trackbackme','1');
        if(!wp_next_scheduled('do_pings'))wp_schedule_single_event(time(),'do_pings');
    }
}
if(!function_exists('_update_term_count_on_transition_post_status')){
    function _update_term_count_on_transition_post_status($new_status, $old_status, $post) {
        foreach((array)get_object_taxonomies($post->post_type) as $tax){
            $tt=wp_get_object_terms($post->ID,$tax,['fields'=>'tt_ids']);
            if($tt&&!is_wp_error($tt)){ $o=get_taxonomy($tax);if($o)_update_post_term_count($tt,$o); }
        }
    }
}

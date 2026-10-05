<?php
// Ergänzende Verwaltungs-Funktionen für Beiträge, Meta und Begriffe (wp-admin/includes/post.php, taxonomy.php). Es gibt kein Beitrags-Bearbeitungsformular
// im Stil von WordPress; die Funktionen arbeiten auf $_POST/Übergabe-Arrays und schreiben über wp_insert_post()/wp_update_post() in wp_posts.
// Die Rechte-Namen add_/edit_/delete_post_meta werden auf edit_post abgebildet.

/* ───────── Formulardaten in Beitragsdaten ───────── */
if(!function_exists('_wp_get_allowed_postdata')){
    function _wp_get_allowed_postdata($post_data=null) {
        if(empty($post_data))$post_data=$_POST;
        if(is_wp_error($post_data))return $post_data;
        return array_diff_key($post_data,array_flip(['meta_input','file','guid']));
    }
}
if(!function_exists('_wp_translate_postdata')){
    function _wp_translate_postdata($update=false, $post_data=null) {
        if(empty($post_data))$post_data=&$_POST;
        if($update)$post_data['ID']=(int)($post_data['ID']??0);
        $pt=get_post_type_object($post_data['post_type']??'post');if(!$pt)return new WP_Error('invalid_post_type','Ungültiger Beitragstyp.');
        if($update&&!current_user_can('edit_post',$post_data['ID']))return new WP_Error('edit_others_posts','Sie dürfen diesen Eintrag nicht bearbeiten.');
        if(!$update&&!current_user_can($pt->cap->create_posts??$pt->cap->edit_posts))return new WP_Error('edit_posts','Sie dürfen keine Einträge dieses Typs anlegen.');
        foreach(['content'=>'post_content','excerpt'=>'post_excerpt','trackback_url'=>'to_ping'] as $f=>$t)if(isset($post_data[$f]))$post_data[$t]=$post_data[$f];
        if(isset($post_data['parent_id']))$post_data['post_parent']=(int)$post_data['parent_id'];
        $post_data['user_ID']=get_current_user_id();
        $post_data['post_author']=!empty($post_data['post_author_override'])?(int)$post_data['post_author_override']:(!empty($post_data['post_author'])?(int)$post_data['post_author']:(int)$post_data['user_ID']);
        if($post_data['post_author']!=$post_data['user_ID']&&!current_user_can($pt->cap->edit_others_posts))
            return new WP_Error('edit_others_'.($update?'posts':'pages'),'Sie dürfen Einträge anderer Benutzer nicht '.($update?'bearbeiten':'anlegen').'.');
        foreach(['saveasdraft'=>'draft','saveasprivate'=>'private','advanced'=>'draft','pending'=>'pending'] as $btn=>$st)if(isset($post_data[$btn])&&''!==$post_data[$btn])$post_data['post_status']=$st;
        if(!empty($post_data['publish'])&&(!isset($post_data['post_status'])||'private'!==$post_data['post_status']))$post_data['post_status']='publish';
        if(isset($post_data['post_status'])&&'publish'===$post_data['post_status']&&!current_user_can($pt->cap->publish_posts))$post_data['post_status']='pending';   // ohne Recht nur zur Prüfung einreichen
        if(!empty($post_data['edit_date'])||!empty($post_data['aa'])){
            foreach(['aa'=>date('Y'),'mm'=>date('m'),'jj'=>date('d'),'hh'=>date('H'),'mn'=>date('i'),'ss'=>date('s')] as $k=>$def)$post_data[$k]=isset($post_data[$k])&&$post_data[$k]!==''?$post_data[$k]:$def;
            $post_data['jj']=min((int)$post_data['jj'],31);$post_data['ss']=min((int)$post_data['ss'],59);
            if(!checkdate((int)$post_data['mm'],(int)$post_data['jj'],(int)$post_data['aa']))return new WP_Error('invalid_date','Ungültiges Datum.');
            $post_data['post_date']=sprintf('%04d-%02d-%02d %02d:%02d:%02d',$post_data['aa'],$post_data['mm'],$post_data['jj'],$post_data['hh'],$post_data['mn'],$post_data['ss']);
            $post_data['post_date_gmt']=get_gmt_from_date($post_data['post_date']);
        }
        return $post_data;
    }
}

/* ───────── Meta (benutzerdefinierte Felder) ───────── */
if(!function_exists('get_post_meta_by_id')){ function get_post_meta_by_id($mid) { return get_metadata_by_mid('post',$mid); } }
if(!function_exists('delete_meta')){ function delete_meta($mid) { return delete_metadata_by_mid('post',$mid); } }
if(!function_exists('update_meta')){
    function update_meta($meta_id, $meta_key, $meta_value) { return update_metadata_by_mid('post',$meta_id,wp_unslash($meta_value),wp_unslash($meta_key)); }
}
if(!function_exists('has_meta')){
    function has_meta($postid) {
        global $wpdb;if(!$wpdb||!rrw_wp_db_ready())return [];
        return $wpdb->get_results($wpdb->prepare("SELECT meta_key, meta_value, meta_id, post_id FROM {$wpdb->postmeta} WHERE post_id = %d ORDER BY meta_key, meta_id",$postid),ARRAY_A);
    }
}
if(!function_exists('get_meta_keys')){
    function get_meta_keys() { global $wpdb;if(!$wpdb||!rrw_wp_db_ready())return [];return $wpdb->get_col("SELECT DISTINCT meta_key FROM {$wpdb->postmeta} ORDER BY meta_key"); }
}
if(!function_exists('add_meta')){
    function add_meta($post_id) {
        $post_id=(int)$post_id;
        $sel=isset($_POST['metakeyselect'])?wp_unslash(trim((string)$_POST['metakeyselect'])):'';$inp=isset($_POST['metakeyinput'])?wp_unslash(trim((string)$_POST['metakeyinput'])):'';
        $val=isset($_POST['metavalue'])?wp_unslash($_POST['metavalue']):'';if(is_string($val))$val=trim($val);
        if(('0'===$val||!empty($val))&&((!empty($sel)&&'#NONE#'!==$sel)||!empty($inp))){
            $key='#NONE#'!==$sel?$sel:'';if($inp)$key=$inp;
            if(is_protected_meta($key,'post')||!current_user_can('edit_post',$post_id))return false;
            return add_post_meta($post_id,wp_slash($key),$val);
        }
        return false;
    }
}

/* ───────── Beiträge bearbeiten / anlegen ───────── */
if(!function_exists('_fix_attachment_links')){
    function _fix_attachment_links($post) {
        $post=get_post($post,ARRAY_A);if(!$post)return;$c=$post['post_content'];
        if(!get_option('permalink_structure')||!in_array($post['post_status'],['publish','future','private'],true))return;
        if(!strpos($c,'?attachment_id=')||!preg_match_all('/<a ([^>]+)>[\s\S]+?<\/a>/',$c,$lm))return;
        $site=get_bloginfo('url');$site=substr($site,(int)strpos($site,'://'));$changed=false;
        foreach($lm[1] as $k=>$v){
            if(!strpos($v,'?attachment_id=')||!strpos($v,'wp-att-')||!preg_match('/href=(["\'])[^"\']*\?attachment_id=(\d+)[^"\']*\\1/',$v,$u)||!preg_match('/rel=["\'][^"\']*wp-att-(\d+)/',$v,$r))continue;
            if(!(int)$u[2]||(int)$u[2]!==(int)$r[1]||!str_contains($u[0],$site))continue;
            $c=str_replace($lm[0][$k],str_replace($u[0],'href='.$u[1].get_attachment_link((int)$u[2]).$u[1],$lm[0][$k]),$c);$changed=true;
        }
        if($changed){ $post['post_content']=$c;return wp_update_post($post); }
    }
}
if(!function_exists('edit_post')){
    /** Bestehenden Beitrag aus Formulardaten ($_POST) ändern. Gibt die Beitrags-ID zurück, bei fehlendem Recht oder Fehler 0. */
    function edit_post($post_data=null) {
        if(empty($post_data))$post_data=&$_POST;
        unset($post_data['filter']);
        $id=(int)($post_data['post_ID']??$post_data['ID']??0);$post=get_post($id);if(!$post)return 0;
        $post_data['ID']=$id;$post_data['post_type']=$post->post_type;$post_data['post_mime_type']=$post->post_mime_type;
        if(!empty($post_data['post_status'])){ $post_data['post_status']=sanitize_key($post_data['post_status']);if('inherit'===$post_data['post_status'])unset($post_data['post_status']); }
        $pt=get_post_type_object($post->post_type);
        if(!current_user_can('edit_post',$id))return 0;
        foreach((array)($post_data['meta']??[]) as $mid=>$v){
            $m=get_post_meta_by_id($mid);if(!$m||(int)$m->post_id!==$id||is_protected_meta($m->meta_key,'post')||is_protected_meta($v['key']??'','post'))continue;
            update_meta($mid,$v['key'],$v['value']);
        }
        foreach((array)($post_data['deletemeta']??[]) as $mid=>$_){ $m=get_post_meta_by_id($mid);if($m&&(int)$m->post_id===$id&&!is_protected_meta($m->meta_key,'post'))delete_meta($mid); }
        $t=_wp_translate_postdata(true,$post_data);if(is_wp_error($t))return 0;$post_data=$t;
        if(isset($post_data['visibility'])){
            switch($post_data['visibility']){
                case 'public': $post_data['post_password']='';break;
                case 'password': unset($post_data['sticky']);break;
                case 'private': $post_data['post_status']='private';$post_data['post_password']='';break;
            }
        }
        if(isset($post_data['post_format']))set_post_format($id,$post_data['post_format']);
        if(isset($post_data['_thumbnail_id'])){ (int)$post_data['_thumbnail_id']>0?set_post_thumbnail($id,(int)$post_data['_thumbnail_id']):delete_post_thumbnail($id); }
        if(!isset($post_data['tax_input']))$post_data['tax_input']=[];
        if(isset($post_data['post_category'])&&'post'===$post->post_type)$post_data['post_category']=array_filter(array_map('intval',(array)$post_data['post_category']));
        $ok=wp_update_post(_wp_get_allowed_postdata($post_data),true);
        if(!$ok||is_wp_error($ok))return 0;
        _fix_attachment_links($id);wp_set_post_lock($id);
        if($pt&&current_user_can($pt->cap->edit_others_posts)&&current_user_can($pt->cap->publish_posts)){ !empty($post_data['sticky'])?stick_post($id):unstick_post($id); }
        return $id;
    }
}
if(!function_exists('bulk_edit_posts')){
    /** Sammelbearbeitung: $post_data['post_ID'][] = IDs; leere Felder / -1 bleiben unverändert. @return array{updated:int[],skipped:int[],locked:int[]} */
    function bulk_edit_posts($post_data=null) {
        if(empty($post_data))$post_data=&$_POST;
        $ids=array_map('intval',(array)($post_data['post_ID']??$post_data['post']??[]));$r=['updated'=>[],'skipped'=>[],'locked'=>[]];
        $pt=get_post_type_object($post_data['post_type']??'post');if(!$pt||!$ids)return $r;
        foreach(['post_author','comment_status','ping_status','post_status','post_format','_status','sticky','post_parent','page_template'] as $k)if(isset($post_data[$k])&&(-1==$post_data[$k]||''===$post_data[$k]))unset($post_data[$k]);
        if(!empty($post_data['_status'])&&empty($post_data['post_status']))$post_data['post_status']=$post_data['_status'];
        foreach($ids as $id){
            $p=get_post($id);if(!$p||!current_user_can('edit_post',$id)||(int)$p->ID<RRW_WP_ID_DB_MIN){ $r['skipped'][]=$id;continue; }
            if(wp_check_post_lock($id)){ $r['locked'][]=$id;continue; }
            $new=['ID'=>$id];
            foreach(['post_author','comment_status','ping_status','post_status','post_parent'] as $k)if(isset($post_data[$k]))$new[$k]=$post_data[$k];
            if(isset($new['post_author'])&&!current_user_can($pt->cap->edit_others_posts)&&(int)$new['post_author']!==get_current_user_id())unset($new['post_author']);
            if(isset($new['post_status'])&&'publish'===$new['post_status']&&!current_user_can($pt->cap->publish_posts))$new['post_status']='pending';
            $res=count($new)>1?wp_update_post($new,true):$id;
            if(!$res||is_wp_error($res)){ $r['skipped'][]=$id;continue; }
            if(isset($post_data['post_format']))set_post_format($id,$post_data['post_format']);
            if(isset($post_data['sticky']))'sticky'===$post_data['sticky']?stick_post($id):unstick_post($id);
            foreach((array)($post_data['tax_input']??[]) as $tax=>$terms){ if(!taxonomy_exists($tax))continue;
                if(is_string($terms))$terms=array_filter(array_map('trim',explode(',',$terms)));
                if($terms)wp_set_post_terms($id,array_values(array_filter((array)$terms,fn($x)=>$x!==0&&$x!=='0')),$tax,true); }
            if(!empty($post_data['post_category'])&&'post'===$p->post_type)wp_set_post_categories($id,array_filter(array_map('intval',(array)$post_data['post_category'])),true);
            $r['updated'][]=$id;
        }
        return $r;
    }
}
if(!function_exists('get_default_post_to_edit')){
    function get_default_post_to_edit($post_type='post', $create_in_db=false) {
        $title=!empty($_REQUEST['post_title'])?esc_html(wp_unslash($_REQUEST['post_title'])):'';
        $content=!empty($_REQUEST['content'])?esc_html(wp_unslash($_REQUEST['content'])):'';
        $excerpt=!empty($_REQUEST['excerpt'])?esc_html(wp_unslash($_REQUEST['excerpt'])):'';
        if($create_in_db){
            $id=wp_insert_post(['post_title'=>'Automatischer Entwurf','post_type'=>$post_type,'post_status'=>'auto-draft'],false,false);$post=get_post($id);
            if($post&&post_type_supports($post->post_type,'post-formats')&&get_option('default_post_format'))set_post_format($post,get_option('default_post_format'));
        }else{
            $post=new WP_Post((object)['ID'=>0,'post_author'=>'','post_date'=>'','post_date_gmt'=>'','post_password'=>'','post_name'=>'','post_type'=>$post_type,'post_status'=>'draft','to_ping'=>'','pinged'=>'',
                'comment_status'=>get_option('default_comment_status','open'),'ping_status'=>get_option('default_ping_status','open'),'post_parent'=>0,'menu_order'=>0,'filter'=>'raw']);
        }
        if($post){ $post->post_content=apply_filters('default_content',$content,$post);$post->post_title=apply_filters('default_title',$title,$post);$post->post_excerpt=apply_filters('default_excerpt',$excerpt,$post); }
        return $post;
    }
}
if(!function_exists('wp_write_post')){
    function wp_write_post() {
        $pt=get_post_type_object($_POST['post_type']??'post');
        if(!$pt||!current_user_can($pt->cap->edit_posts))return new WP_Error('edit_posts','Sie dürfen hier keine Einträge anlegen.');
        $_POST['post_mime_type']='';unset($_POST['filter']);
        if(isset($_POST['post_ID']))return edit_post();
        $t=_wp_translate_postdata(false);if(is_wp_error($t))return $t;
        $id=wp_insert_post(_wp_get_allowed_postdata($t),true);
        if(is_wp_error($id))return $id;if(empty($id))return 0;
        add_meta($id);_fix_attachment_links($id);wp_set_post_lock($id);return $id;
    }
}
if(!function_exists('write_post')){
    function write_post() { $r=wp_write_post();if(is_wp_error($r))wp_die($r->get_error_message());return $r; }
}
if(!function_exists('redirect_post')){
    function redirect_post($post_id='') {
        if(isset($_POST['save'])||isset($_POST['publish'])){
            $m=['pending'=>8,'future'=>9,'draft'=>10][get_post_status($post_id)]??(isset($_POST['publish'])?6:1);
            $loc=add_query_arg('message',$m,get_edit_post_link($post_id,'url')?:admin_url('post.php?post='.(int)$post_id.'&action=edit'));
        }elseif(!empty($_POST['addmeta']))$loc=explode('#',add_query_arg('message',2,wp_get_referer()))[0].'#postcustom';
        elseif(!empty($_POST['deletemeta']))$loc=explode('#',add_query_arg('message',3,wp_get_referer()))[0].'#postcustom';
        else $loc=add_query_arg('message',4,get_edit_post_link($post_id,'url')?:admin_url('post.php?post='.(int)$post_id.'&action=edit'));
        wp_redirect(apply_filters('redirect_post_location',$loc,$post_id));exit;
    }
}

/* ───────── Listen-Abfragen, Oberfläche ───────── */
if(!function_exists('get_available_post_statuses')){
    function get_available_post_statuses($type='post') { return array_keys(get_object_vars(wp_count_posts($type))); }
}
if(!function_exists('wp_edit_posts_query')){
    /** Führt die Abfrage der Beitragsliste aus ($wp_query) und gibt [alle Status, vorhandene Status] zurück. */
    function wp_edit_posts_query($q=false) {
        global $wp_query;if(false===$q)$q=$_GET;
        $stati=get_post_stati();$type=isset($q['post_type'])&&in_array($q['post_type'],get_post_types(),true)?$q['post_type']:'post';
        $avail=get_available_post_statuses($type);$status='';$perm='';
        if(isset($q['post_status'])&&in_array($q['post_status'],$stati,true)){ $status=$q['post_status'];$perm='readable'; }
        $ob=$q['orderby']??(isset($q['post_status'])&&in_array($q['post_status'],['pending','draft'],true)?'modified':'');
        $order=$q['order']??(isset($q['post_status'])&&in_array($q['post_status'],['pending','draft'],true)?'DESC':'');
        $per=(int)get_user_option("edit_{$type}_per_page");if($per<1)$per=20;$per=(int)apply_filters("edit_{$type}_per_page",$per);
        $query=['post_type'=>$type,'post_status'=>$status?:'any','orderby'=>$ob,'order'=>$order,'posts_per_page'=>$per,'paged'=>max(1,(int)($q['paged']??1))];
        if(!empty($q['s']))$query['s']=wp_unslash($q['s']);if(!empty($q['author']))$query['author']=(int)$q['author'];if(!empty($q['cat']))$query['cat']=(int)$q['cat'];
        $wp_query=new WP_Query($query);return [$stati,$avail];
    }
}
if(!function_exists('wp_edit_attachments_query_vars')){
    function wp_edit_attachments_query_vars($q=false) {
        if(false===$q)$q=$_GET;
        $q['m']=isset($q['m'])?(int)$q['m']:0;$q['cat']=isset($q['cat'])?(int)$q['cat']:0;$q['post_type']='attachment';
        $pt=get_post_type_object('attachment');$st='inherit';if(current_user_can($pt->cap->read_private_posts))$st.=',private';
        $q['post_status']=isset($q['attachment-filter'])&&'trash'===$q['attachment-filter']?'trash':$st;
        $per=(int)get_user_option('upload_per_page');if(empty($per)||$per<1)$per=20;$q['posts_per_page']=apply_filters('upload_per_page',$per);
        $types=get_post_mime_types();
        if(isset($q['post_mime_type'])&&!array_intersect((array)$q['post_mime_type'],array_keys($types)))unset($q['post_mime_type']);
        foreach(array_keys($types) as $t)if(isset($q['attachment-filter'])&&"post_mime_type:$t"===$q['attachment-filter']){ $q['post_mime_type']=$t;break; }
        return $q;
    }
}
if(!function_exists('wp_edit_attachments_query')){
    function wp_edit_attachments_query($q=false) {
        global $wp_query;$v=wp_edit_attachments_query_vars($q);foreach(['m','cat'] as $k)if(empty($v[$k]))unset($v[$k]);   // 0 = „alle“
        $wp_query=new WP_Query($v);
        return [get_post_mime_types(),get_available_post_mime_types('attachment')];
    }
}
if(!function_exists('postbox_classes')){
    function postbox_classes($box_id, $screen_id) {
        $closed=get_user_option('closedpostboxes_'.$screen_id);
        $c=is_array($closed)?[in_array($box_id,$closed,true)?'closed':'']:[''];
        return implode(' ',apply_filters("postbox_classes_{$screen_id}_{$box_id}",$c));
    }
}
if(!function_exists('get_sample_permalink_html')){
    function get_sample_permalink_html($post, $new_title=null, $new_slug=null) {
        $p=get_post($post);if(!$p)return '';[$permalink]=get_sample_permalink($p->ID,$new_title,$new_slug);$view=false;$target='';
        if(current_user_can('read_post',$p->ID)){
            if('draft'===$p->post_status||empty($p->post_name)){ $view=get_preview_post_link($p);$target=" target='wp-preview-{$p->ID}'"; }
            else $view='publish'===$p->post_status||'attachment'===$p->post_type?get_permalink($p):str_replace(['%pagename%','%postname%'],$p->post_name,$permalink);
        }
        $r='<strong>Permalink:</strong>'."\n";
        $r.=false!==$view?'<a id="sample-permalink" href="'.esc_url($view).'"'.$target.'>'.esc_html(urldecode($view)).'</a>'."\n":'<span id="sample-permalink">'.esc_html((string)$permalink).'</span>'."\n";
        return apply_filters('get_sample_permalink_html',$r,$p->ID,$new_title,$new_slug);
    }
}
if(!function_exists('_wp_post_thumbnail_html')){
    function _wp_post_thumbnail_html($thumbnail_id=null, $post=null) {
        $p=get_post($post);if(!$p)return '';
        $link='<p class="hide-if-no-js"><a href="'.esc_url(admin_url('media-upload.php?post_id='.(int)$p->ID.'&type=image&tab=library')).'" id="set-post-thumbnail" class="thickbox">%s</a></p>';
        $c=sprintf($link,esc_html('Beitragsbild festlegen'));
        if($thumbnail_id&&get_post($thumbnail_id)){
            $img=wp_get_attachment_image($thumbnail_id,'post-thumbnail');
            if(!empty($img)){ $c=sprintf($link,$img).'<p class="hide-if-no-js howto" id="set-post-thumbnail-desc">Zum Bearbeiten oder Ersetzen auf das Bild klicken</p><p class="hide-if-no-js"><a href="#" id="remove-post-thumbnail">Beitragsbild entfernen</a></p>'; }
        }
        $c.='<input type="hidden" id="_thumbnail_id" name="_thumbnail_id" value="'.esc_attr($thumbnail_id?:'-1').'" />';
        return apply_filters('admin_post_thumbnail_html',$c,$p->ID,$thumbnail_id);
    }
}
if(!function_exists('_admin_notice_post_locked')){ function _admin_notice_post_locked() {} }   // Bearbeitungssperren gibt es nicht (wp_check_post_lock() ist immer false)
if(!function_exists('wp_autosave_post_revisioned_meta_fields')){
    function wp_autosave_post_revisioned_meta_fields($new_autosave) {
        foreach(wp_post_revision_meta_keys(get_post_type($new_autosave['post_parent'])) as $k)
            if(isset($_POST[$k])&&get_post_meta($new_autosave['ID'],$k,true)!==wp_unslash($_POST[$k]))update_post_meta($new_autosave['ID'],$k,wp_unslash($_POST[$k]));
    }
}
if(!function_exists('wp_autosave')){
    function wp_autosave($post_data) {
        if(!defined('DOING_AUTOSAVE'))define('DOING_AUTOSAVE',true);
        $id=(int)($post_data['post_id']??0);$post_data['ID']=$post_data['post_ID']=$id;
        if(false===wp_verify_nonce($post_data['_wpnonce']??'','update-post_'.$id))return new WP_Error('invalid_nonce','Fehler beim Speichern.');
        $post=get_post($id);if(!$post||!current_user_can('edit_post',$post->ID))return new WP_Error('edit_posts','Sie dürfen diesen Eintrag nicht bearbeiten.');
        if('auto-draft'===$post->post_status)$post_data['post_status']='draft';
        if('page'!==($post_data['post_type']??'')&&!empty($post_data['catslist']))$post_data['post_category']=explode(',',$post_data['catslist']);
        if(!wp_check_post_lock($post->ID)&&get_current_user_id()==$post->post_author&&in_array($post->post_status,['auto-draft','draft'],true))return edit_post($post_data);
        return wp_create_post_autosave($post_data);
    }
}
if(!function_exists('post_preview')){
    function post_preview() {
        $id=(int)($_POST['post_ID']??0);$_POST['ID']=$id;$post=get_post($id);
        if(!$post||!current_user_can('edit_post',$post->ID))wp_die('Sie dürfen diesen Eintrag nicht bearbeiten.');
        $auto=false;
        if(!wp_check_post_lock($post->ID)&&get_current_user_id()==$post->post_author&&in_array($post->post_status,['draft','auto-draft'],true))$saved=edit_post();
        else{ $auto=true;if(isset($_POST['post_status'])&&'auto-draft'===$_POST['post_status'])$_POST['post_status']='draft';$saved=wp_create_post_autosave($post->ID); }
        if(is_wp_error($saved))wp_die($saved->get_error_message());
        $qa=[];if($auto&&$saved){ $qa['preview_id']=$post->ID;$qa['preview_nonce']=wp_create_nonce('post_preview_'.$post->ID);if(isset($_POST['_thumbnail_id']))$qa['_thumbnail_id']=(int)$_POST['_thumbnail_id']<=0?'-1':(int)$_POST['_thumbnail_id']; }
        return get_preview_post_link($post,$qa);
    }
}
if(!function_exists('taxonomy_meta_box_sanitize_cb_checkboxes')){ function taxonomy_meta_box_sanitize_cb_checkboxes($taxonomy, $terms) { return array_map('intval',(array)$terms); } }
if(!function_exists('taxonomy_meta_box_sanitize_cb_input')){
    function taxonomy_meta_box_sanitize_cb_input($taxonomy, $terms) {
        if(is_string($terms))$terms=explode(',',trim($terms," \n\t\r\0\x0B,"));
        $clean=[];
        foreach(array_map(fn($x)=>is_string($x)?trim($x):$x,(array)$terms) as $term){
            if(empty($term))continue;
            $t=get_terms(['name'=>$term,'taxonomy'=>$taxonomy,'fields'=>'ids','hide_empty'=>false]);
            $clean[]=!empty($t)?(int)$t[0]:$term;   // unbekannter Begriff: Name durchreichen, er wird neu angelegt
        }
        return $clean;
    }
}

/* ───────── Block-Editor-Hilfen ───────── */
if(!function_exists('get_block_editor_server_block_settings')){
    function get_block_editor_server_block_settings() {
        $out=[];$map=['api_version'=>'apiVersion','title'=>'title','description'=>'description','icon'=>'icon','attributes'=>'attributes','provides_context'=>'providesContext','uses_context'=>'usesContext','supports'=>'supports','category'=>'category','styles'=>'styles','textdomain'=>'textdomain','parent'=>'parent','ancestor'=>'ancestor','keywords'=>'keywords','example'=>'example','variations'=>'variations'];
        foreach(WP_Block_Type_Registry::get_instance()->get_all_registered() as $n=>$bt){
            $s=[];foreach($map as $prop=>$key)if(!empty($bt->$prop)||(isset($bt->$prop)&&$bt->$prop===0))$s[$key]=$bt->$prop;
            $out[$n]=$s;
        }
        return $out;
    }
}
if(!function_exists('the_block_editor_meta_boxes')){ function the_block_editor_meta_boxes() {} }   // Meta-Boxen-Bereich des Block-Editors: keine Oberfläche hier
if(!function_exists('the_block_editor_meta_box_post_form_hidden_fields')){
    function the_block_editor_meta_box_post_form_hidden_fields($post) {
        $p=get_post($post);if(!$p)return;$ref=wp_get_referer();
        wp_nonce_field('update-post_'.$p->ID);
        echo '<input type="hidden" id="user-id" name="user_ID" value="'.(int)get_current_user_id().'" /><input type="hidden" id="hiddenaction" name="action" value="editpost" /><input type="hidden" id="originalaction" name="originalaction" value="editpost" />'
            .'<input type="hidden" id="post_author" name="post_author" value="'.esc_attr($p->post_author).'" /><input type="hidden" id="post_type" name="post_type" value="'.esc_attr($p->post_type).'" /><input type="hidden" id="original_post_status" name="original_post_status" value="'.esc_attr($p->post_status).'" />'
            .'<input type="hidden" id="referredby" name="referredby" value="'.($ref?esc_url($ref):'').'" /><input type="hidden" id="post_ID" name="post_ID" value="'.(int)$p->ID.'" />';
    }
}
if(!function_exists('_disable_block_editor_for_navigation_post_type')){
    function _disable_block_editor_for_navigation_post_type($value, $post_type) { return 'wp_navigation'===$post_type?false:$value; }
}
if(!function_exists('_disable_content_editor_for_navigation_post_type')){
    function _disable_content_editor_for_navigation_post_type($post) { $t=get_post_type($post);if('wp_navigation'===$t)remove_post_type_support($t,'editor'); }
}
if(!function_exists('_enable_content_editor_for_navigation_post_type')){
    function _enable_content_editor_for_navigation_post_type($post) { $t=get_post_type($post);if('wp_navigation'===$t)add_post_type_support($t,'editor'); }
}

/* ───────── Kategorien und Schlagwörter anlegen (wp-admin/includes/taxonomy.php) ───────── */
if(!function_exists('get_category_to_edit')){
    function get_category_to_edit($id) { $c=get_term((int)$id,'category');if(!$c||is_wp_error($c))return $c;$c=sanitize_term($c,'category','edit');_make_cat_compat($c);return $c; }
}
if(!function_exists('wp_create_category')){
    function wp_create_category($cat_name, $category_parent=0) {
        $id=category_exists($cat_name,$category_parent);if($id)return $id;
        return wp_insert_category(['cat_name'=>$cat_name,'category_parent'=>$category_parent]);
    }
}
if(!function_exists('wp_create_categories')){
    function wp_create_categories($categories, $post_id='') {
        $ids=[];
        foreach((array)$categories as $c){ $id=category_exists($c)?:wp_create_category($c);if($id)$ids[]=$id; }
        if($post_id)wp_set_post_categories($post_id,$ids);
        return $ids;
    }
}
if(!function_exists('wp_insert_category')){
    function wp_insert_category($catarr, $wp_error=false) {
        $c=wp_parse_args($catarr,['cat_ID'=>0,'taxonomy'=>'category','cat_name'=>'','category_description'=>'','category_nicename'=>'','category_parent'=>'']);
        if(''===trim((string)$c['cat_name']))return $wp_error?new WP_Error('cat_name','Es wurde kein Kategoriename angegeben.'):0;
        $c['cat_ID']=(int)$c['cat_ID'];$update=!empty($c['cat_ID']);$parent=(int)$c['category_parent'];
        if($parent<0||!$parent||!term_exists($parent,$c['taxonomy'])||($c['cat_ID']&&term_is_ancestor_of($c['cat_ID'],$parent,$c['taxonomy'])))$parent=0;
        $args=['name'=>$c['cat_name'],'slug'=>$c['category_nicename'],'parent'=>$parent,'description'=>$c['category_description']];
        $r=$update?wp_update_term($c['cat_ID'],$c['taxonomy'],$args):wp_insert_term($c['cat_name'],$c['taxonomy'],$args);
        if(is_wp_error($r))return $wp_error?$r:0;
        return $r['term_id'];
    }
}
if(!function_exists('wp_update_category')){
    function wp_update_category($catarr) {
        $id=(int)($catarr['cat_ID']??0);
        if(isset($catarr['category_parent'])&&$id===(int)$catarr['category_parent'])return false;
        $cat=get_term($id,'category',ARRAY_A);if(!$cat||is_wp_error($cat))return false;
        _make_cat_compat($cat);
        return wp_insert_category(array_merge($cat,$catarr));
    }
}
if(!function_exists('tag_exists')){ function tag_exists($tag_name) { return term_exists($tag_name,'post_tag'); } }
if(!function_exists('wp_create_term')){
    function wp_create_term($tag_name, $taxonomy='post_tag') { $id=term_exists($tag_name,$taxonomy);return $id?:wp_insert_term($tag_name,$taxonomy); }
}
if(!function_exists('wp_create_tag')){ function wp_create_tag($tag_name, $taxonomy='post_tag') { return wp_create_term($tag_name,$taxonomy); } }
if(!function_exists('get_terms_to_edit')){
    function get_terms_to_edit($post_id, $taxonomy='post_tag') {
        $post_id=(int)$post_id;if(!$post_id)return false;
        $terms=wp_get_object_terms($post_id,$taxonomy);
        if(is_wp_error($terms))return $terms;if(!$terms)return false;
        return apply_filters('terms_to_edit',esc_attr(implode(',',wp_list_pluck($terms,'name'))),$taxonomy);
    }
}
if(!function_exists('get_tags_to_edit')){ function get_tags_to_edit($post_id, $taxonomy='post_tag') { return get_terms_to_edit($post_id,$taxonomy); } }

<?php
// Ergänzende Revisions- und Beitrags-Vorlagen-Funktionen (wp-includes/revision.php, post-template.php, wp-admin/includes/revision.php).
// Revisionen sind Zeilen in wp_posts (post_type „revision“, post_status „inherit“, post_parent = Beitrag). Die Revisionen werden hier direkt aus der
// Tabelle gelesen, unabhängig von den Platzhaltern wp_get_post_revisions()/wp_revisions_enabled() des Kerns.

if(!function_exists('rrw_wp_x_revisions')){
    /** Revisionen/Autospeicherungen eines Beitrags, neueste zuerst. @return WP_Post[] */
    function rrw_wp_x_revisions($post_id, bool $autosaves=true): array {
        global $wpdb;if(!$wpdb||!rrw_wp_db_ready())return [];
        $ids=$wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'revision' AND post_status = 'inherit' ORDER BY post_date DESC, ID DESC",(int)$post_id));
        $o=[];foreach((array)$ids as $id){ $p=get_post((int)$id);if($p&&($autosaves||!str_contains((string)$p->post_name,'-autosave-v')))$o[(int)$p->ID]=$p; }
        return $o;
    }
}
if(!function_exists('rrw_wp_x_text_diff')){
    /** Zeilenweiser Vergleich als Tabelle (links alt, rechts neu); leer, wenn identisch. */
    function rrw_wp_x_text_diff($from, $to, bool $always=false): string {
        $a=(string)$from===''?[]:preg_split('/\R/',(string)$from);$b=(string)$to===''?[]:preg_split('/\R/',(string)$to);
        if($from===$to&&!$always)return '';
        $n=count($a);$m=count($b);$ops=[];
        if($n*$m>250000){ foreach($a as $x)$ops[]=['-',$x];foreach($b as $x)$ops[]=['+',$x]; }
        else{
            $l=array_fill(0,$n+1,array_fill(0,$m+1,0));
            for($i=$n-1;$i>=0;$i--)for($j=$m-1;$j>=0;$j--)$l[$i][$j]=$a[$i]===$b[$j]?$l[$i+1][$j+1]+1:max($l[$i+1][$j],$l[$i][$j+1]);
            $i=$j=0;
            while($i<$n&&$j<$m){ if($a[$i]===$b[$j]){$ops[]=['=',$a[$i]];$i++;$j++;}elseif($l[$i+1][$j]>=$l[$i][$j+1]){$ops[]=['-',$a[$i++]];}else{$ops[]=['+',$b[$j++]];} }
            while($i<$n)$ops[]=['-',$a[$i++]];while($j<$m)$ops[]=['+',$b[$j++]];
        }
        $r='';foreach($ops as [$k,$t]){ $e=esc_html($t);
            $r.=$k==='='?"<tr><td>$e</td><td>$e</td></tr>":($k==='-'?"<tr><td class=\"diff-deletedline\"><del>$e</del></td><td></td></tr>":"<tr><td></td><td class=\"diff-addedline\"><ins>$e</ins></td></tr>"); }
        return $r===''?'':'<table class="diff"><tbody>'.$r.'</tbody></table>';
    }
}

/* ───────── Revisionen anlegen und lesen ───────── */
if(!function_exists('_wp_post_revision_fields')){
    function _wp_post_revision_fields($post=[], $deprecated=false) {
        if(!is_array($post))$post=get_post($post,ARRAY_A);
        $f=apply_filters('_wp_post_revision_fields',['post_title'=>'Titel','post_content'=>'Inhalt','post_excerpt'=>'Auszug'],$post);
        foreach(['ID','post_name','post_parent','post_date','post_date_gmt','post_status','post_type','comment_count','post_author'] as $p)unset($f[$p]);   // intern, nicht filterbar
        return $f;
    }
}
if(!function_exists('_wp_post_revision_data')){
    function _wp_post_revision_data($post=[], $autosave=false) {
        if(!is_array($post))$post=get_post($post,ARRAY_A);
        $d=[];foreach(array_intersect(array_keys($post),array_keys(_wp_post_revision_fields($post))) as $f)$d[$f]=$post[$f];
        $d['post_parent']=$post['ID'];$d['post_status']='inherit';$d['post_type']='revision';
        $d['post_name']=$autosave?"{$post['ID']}-autosave-v1":"{$post['ID']}-revision-v1";
        $d['post_date']=$post['post_modified']??'';$d['post_date_gmt']=$post['post_modified_gmt']??'';
        return $d;
    }
}
if(!function_exists('_wp_put_post_revision')){
    function _wp_put_post_revision($post=null, $autosave=false) {
        if(is_object($post))$post=get_object_vars($post);elseif(!is_array($post))$post=get_post($post,ARRAY_A);
        if(!$post||empty($post['ID']))return new WP_Error('invalid_post','Ungültige Beitrags-ID.');
        if(isset($post['post_type'])&&'revision'===$post['post_type'])return new WP_Error('post_type','Von einer Revision kann keine Revision angelegt werden.');
        $id=wp_insert_post(_wp_post_revision_data($post,$autosave),true);
        if(is_wp_error($id))return $id;
        if($id)do_action('_wp_put_post_revision',$id);
        return $id;
    }
}
if(!function_exists('wp_save_post_revision_on_insert')){
    function wp_save_post_revision_on_insert($post_id, $post, $update) {
        if(!$update||!has_action('post_updated','wp_save_post_revision'))return;
        wp_save_post_revision($post_id);remove_action('post_updated','wp_save_post_revision');
    }
}
if(!function_exists('wp_get_post_revision')){
    function wp_get_post_revision($post, $output=OBJECT, $filter='raw') {
        $r=get_post($post,OBJECT,$filter);if(!$r)return $r;if('revision'!==$r->post_type)return null;
        return ARRAY_A===$output?$r->to_array():(ARRAY_N===$output?array_values($r->to_array()):$r);
    }
}
if(!function_exists('wp_post_revision_meta_keys')){
    function wp_post_revision_meta_keys($post_type) {
        $reg=array_merge(get_registered_meta_keys('post'),get_registered_meta_keys('post',$post_type));$k=[];
        foreach($reg as $n=>$a)if(!empty($a['revisions_enabled']))$k[$n]=true;
        return apply_filters('wp_post_revision_meta_keys',array_keys($k),$post_type);
    }
}
if(!function_exists('_wp_copy_post_meta')){
    function _wp_copy_post_meta($source_post_id, $target_post_id, $meta_key) {
        delete_post_meta($target_post_id,$meta_key);
        foreach((array)get_post_meta($source_post_id,$meta_key,false) as $v)add_post_meta($target_post_id,$meta_key,wp_slash($v));
    }
}
if(!function_exists('wp_save_revisioned_meta_fields')){
    function wp_save_revisioned_meta_fields($revision_id, $post_id) {
        $t=get_post_type($post_id);if(!$t)return;
        foreach(wp_post_revision_meta_keys($t) as $k)if(metadata_exists('post',$post_id,$k))_wp_copy_post_meta($post_id,$revision_id,$k);
    }
}
if(!function_exists('wp_restore_post_revision_meta')){
    function wp_restore_post_revision_meta($post, $revision_id) {
        $p=get_post($post);if(!$p)return;
        foreach(wp_post_revision_meta_keys($p->post_type) as $k){ delete_post_meta($p->ID,$k);if(metadata_exists('post',$revision_id,$k))_wp_copy_post_meta($revision_id,$p->ID,$k); }
    }
}
if(!function_exists('wp_check_revisioned_meta_fields_have_changed')){
    function wp_check_revisioned_meta_fields_have_changed($post_has_changed, WP_Post $last_revision, WP_Post $post) {
        foreach(wp_post_revision_meta_keys($post->post_type) as $k)if(get_post_meta($post->ID,$k)!==get_post_meta($last_revision->ID,$k)){ $post_has_changed=true;break; }
        return $post_has_changed;
    }
}
if(!function_exists('wp_restore_post_revision')){
    function wp_restore_post_revision($revision, $fields=null) {
        $revision=wp_get_post_revision($revision,ARRAY_A);if(!$revision)return $revision;
        if(!is_array($fields))$fields=array_keys(_wp_post_revision_fields($revision));
        $u=[];foreach(array_intersect(array_keys($revision),$fields) as $f)$u[$f]=$revision[$f];
        if(!$u)return false;
        $u['ID']=$revision['post_parent'];$id=wp_update_post($u);
        if(!$id||is_wp_error($id))return $id;
        update_post_meta($id,'_edit_last',get_current_user_id());
        do_action('wp_restore_post_revision',$id,$revision['ID']);return $id;
    }
}
if(!function_exists('wp_get_latest_revision_id_and_total_count')){
    function wp_get_latest_revision_id_and_total_count($post=0) {
        $p=get_post($post);if(!$p)return new WP_Error('invalid_post','Ungültiger Beitrag.');
        $r=array_keys(rrw_wp_x_revisions($p->ID));
        return $r?['latest_id'=>$r[0],'count'=>count($r)]:['latest_id'=>0,'count'=>0];
    }
}
if(!function_exists('wp_get_post_revisions_url')){
    function wp_get_post_revisions_url($post=0) {
        $p=get_post($post);if(!($p instanceof WP_Post))return null;
        if('revision'===$p->post_type)return get_edit_post_link($p);
        $r=wp_get_latest_revision_id_and_total_count($p->ID);
        return is_wp_error($r)||0===$r['count']?null:get_edit_post_link($r['latest_id']);
    }
}
if(!function_exists('wp_revisions_to_keep')){
    function wp_revisions_to_keep($post) {
        $n=defined('WP_POST_REVISIONS')?WP_POST_REVISIONS:true;$n=true===$n?-1:(int)$n;
        if(!post_type_supports($post->post_type,'revisions'))$n=0;
        return (int)apply_filters('wp_revisions_to_keep',$n,$post);
    }
}
if(!function_exists('_wp_get_post_revision_version')){
    function _wp_get_post_revision_version($post) {
        if(is_array($post))$post=(object)$post;
        return preg_match('/^\d+-(?:autosave|revision)-v(\d+)$/',(string)($post->post_name??''),$m)?(int)$m[1]:0;
    }
}
if(!function_exists('_wp_upgrade_revisions_of_post')){
    // Vereinfacht: setzt bei alten Revisionen (ohne Versionsangabe im Namen) den Namen auf „…-revision-v1“ und das Datum auf die Änderungszeit.
    function _wp_upgrade_revisions_of_post($post, $revisions) {
        global $wpdb;$lock="revision-upgrade-{$post->ID}";$now=time();
        if(!add_option($lock,"$now:0",'','no')){ $l=get_option($lock);if(!$l||(int)$l>$now-3600)return false; }
        update_option($lock,"$now:".get_current_user_id());
        foreach((array)$revisions as $r){
            if(_wp_get_post_revision_version($r)>=1)continue;
            $wpdb->update($wpdb->posts,['post_name'=>"{$r->post_parent}-revision-v1",'post_date'=>$r->post_modified,'post_date_gmt'=>$r->post_modified_gmt],['ID'=>(int)$r->ID]);clean_post_cache($r->ID);
        }
        delete_option($lock);return true;
    }
}

/* ───────── Vorschau ───────── */
if(!function_exists('_set_preview')){
    function _set_preview($post) {
        if(!is_object($post))return $post;
        $pv=wp_get_post_autosave($post->ID);if(!is_object($pv))return $post;
        $post->post_content=$pv->post_content;$post->post_title=$pv->post_title;$post->post_excerpt=$pv->post_excerpt;
        add_filter('get_the_terms','_wp_preview_terms_filter',10,3);add_filter('get_post_metadata','_wp_preview_post_thumbnail_filter',10,3);add_filter('get_post_metadata','_wp_preview_meta_filter',10,4);
        return $post;
    }
}
if(!function_exists('_show_post_preview')){
    function _show_post_preview() {
        if(isset($_GET['preview_id'],$_GET['preview_nonce'])){
            $id=(int)$_GET['preview_id'];
            if(false===wp_verify_nonce($_GET['preview_nonce'],'post_preview_'.$id))wp_die('Entwürfe dürfen hier nicht in der Vorschau angezeigt werden.',403);
            add_filter('the_preview','_set_preview');
        }
    }
}
if(!function_exists('_wp_preview_terms_filter')){
    function _wp_preview_terms_filter($terms, $post_id, $taxonomy) {
        $p=get_post();if(!$p)return $terms;
        if(empty($_REQUEST['post_format'])||$p->ID!==$post_id||'post_format'!==$taxonomy||'revision'===$p->post_type)return $terms;
        if('standard'===$_REQUEST['post_format'])return [];
        $t=get_term_by('slug','post-format-'.sanitize_key($_REQUEST['post_format']),'post_format');return $t?[$t]:$terms;
    }
}
if(!function_exists('_wp_preview_post_thumbnail_filter')){
    function _wp_preview_post_thumbnail_filter($value, $post_id, $meta_key) {
        $p=get_post();if(!$p)return $value;
        if(empty($_REQUEST['_thumbnail_id'])||empty($_REQUEST['preview_id'])||$p->ID!=$post_id||'_thumbnail_id'!=$meta_key||'revision'==$p->post_type||$post_id!=$_REQUEST['preview_id'])return $value;
        $t=(int)$_REQUEST['_thumbnail_id'];return $t<=0?'':(string)$t;
    }
}
if(!function_exists('_wp_preview_meta_filter')){
    function _wp_preview_meta_filter($value, $post_id, $meta_key, $single) {
        $p=get_post();if(!$p)return $value;
        if(empty($_REQUEST['preview_id'])||$p->ID!=$post_id||'revision'==$p->post_type||$post_id!=$_REQUEST['preview_id'])return $value;
        $pv=wp_get_post_autosave($p->ID);return false===$pv?$value:get_post_meta($pv->ID,$meta_key,$single);
    }
}

/* ───────── Beitrags-Vorlagen (wp-includes/post-template.php) ───────── */
if(!function_exists('get_the_guid')){
    function get_the_guid($post=0) { $p=get_post($post);return apply_filters('get_the_guid',$p->guid??'',$p->ID??0); }
}
if(!function_exists('the_guid')){
    function the_guid($post=0) { $p=get_post($post);echo esc_url(apply_filters('the_guid',get_the_guid($p),$p?$p->ID:0)); }
}
if(!function_exists('_wp_link_page')){
    function _wp_link_page($i) {
        global $wp_rewrite;$post=get_post();$qa=[];
        if(1==$i)$url=get_permalink();
        elseif(!get_option('permalink_structure')||in_array($post->post_status,['draft','pending'],true))$url=add_query_arg('page',$i,get_permalink());
        elseif('page'===get_option('show_on_front')&&get_option('page_on_front')==$post->ID)$url=trailingslashit(get_permalink()).user_trailingslashit("$wp_rewrite->pagination_base/".$i,'single_paged');
        else $url=trailingslashit(get_permalink()).user_trailingslashit($i,'single_paged');
        if(is_preview()){
            if('draft'!==$post->post_status&&isset($_GET['preview_id'],$_GET['preview_nonce'])){ $qa['preview_id']=wp_unslash($_GET['preview_id']);$qa['preview_nonce']=wp_unslash($_GET['preview_nonce']); }
            $url=get_preview_post_link($post,$qa,$url);
        }
        return '<a href="'.esc_url($url).'" class="post-page-numbers">';
    }
}
if(!function_exists('post_custom')){
    function post_custom($key='') { $c=get_post_custom();if(!isset($c[$key]))return false;return 1==count($c[$key])?$c[$key][0]:$c[$key]; }
}
if(!function_exists('the_meta')){
    function the_meta() {
        $keys=get_post_custom_keys();if(!$keys)return;$li='';
        foreach((array)$keys as $key){
            $k=trim($key);if(is_protected_meta($k,'post'))continue;
            $v=implode(', ',array_map('trim',(array)get_post_custom_values($key)));
            $li.=apply_filters('the_meta_key',sprintf("<li><span class='post-meta-key'>%s</span> %s</li>\n",esc_html($key).':',$v),$key,$v);
        }
        if($li)echo "<ul class='post-meta'>\n{$li}</ul>\n";
    }
}
if(!function_exists('rrw_wp_x_page_tree')){
    /** Baum aus Seiten: [Eltern-ID => [Seiten]], oberste Ebene unter 0. */
    function rrw_wp_x_page_tree(array $pages): array { return rrw_wp_x_term_tree($pages,'post_parent','ID'); }
}
if(!function_exists('walk_page_tree')){
    function walk_page_tree($pages, $depth, $current_page, $args) {
        $w=$args['walker']??null;
        if($w instanceof Walker&&get_class($w)!=='Walker_Page'){ foreach((array)$pages as $p)if($p->post_parent)$args['pages_with_children'][$p->post_parent]=true;return $w->walk($pages,$depth,$args,$current_page); }
        $map=rrw_wp_x_page_tree((array)$pages);$depth=(int)$depth;
        $render=function($pid,$lvl) use(&$render,$map,$depth,$current_page,$args){
            $o='';
            foreach($map[$pid]??[] as $p){
                $cls=['page_item','page-item-'.$p->ID];$kids=!empty($map[(int)$p->ID]);if($kids)$cls[]='page_item_has_children';
                if($current_page&&(int)$current_page===(int)$p->ID)$cls[]='current_page_item';
                $sub=$kids&&($depth==0||$depth>$lvl+1)?"<ul class='children'>\n".$render((int)$p->ID,$lvl+1)."</ul>\n":'';
                $o.='<li class="'.esc_attr(implode(' ',$cls)).'"><a href="'.esc_url(get_permalink($p)).'">'.apply_filters('the_title',$p->post_title,$p->ID).'</a>'.$sub."</li>\n";
            }
            return $o;
        };
        return $render(0,0);
    }
}
if(!function_exists('walk_page_dropdown_tree')){
    function walk_page_dropdown_tree(...$args) {
        $r=(array)($args[2]??[]);$w=$r['walker']??null;
        if($w instanceof Walker&&get_class($w)!=='Walker_PageDropdown')return $w->walk(...$args);
        $map=rrw_wp_x_page_tree((array)($args[0]??[]));$depth=(int)($args[1]??0);$vf=$r['value_field']??'ID';$sel=$r['selected']??0;
        $render=function($pid,$lvl) use(&$render,$map,$depth,$vf,$sel){
            $o='';
            foreach($map[$pid]??[] as $p){
                $v=$p->$vf??$p->ID;
                $o.="\t<option class=\"level-$lvl\" value=\"".esc_attr((string)$v).'"'.((string)$v===(string)$sel?' selected="selected"':'').'>'.str_repeat('&nbsp;&nbsp;&nbsp;',$lvl).esc_html($p->post_title===''?'#'.$p->ID.' (ohne Titel)':$p->post_title)."</option>\n";
                if($depth==0||$depth>$lvl+1)$o.=$render((int)$p->ID,$lvl+1);
            }
            return $o;
        };
        return $render(0,0);
    }
}
if(!function_exists('wp_dropdown_pages')){
    function wp_dropdown_pages($args='') {
        $a=wp_parse_args($args,['depth'=>0,'child_of'=>0,'selected'=>0,'echo'=>1,'name'=>'page_id','id'=>'','class'=>'','show_option_none'=>'','show_option_no_change'=>'','option_none_value'=>'','value_field'=>'ID']);
        $pages=get_pages($a);$o='';if(empty($a['id']))$a['id']=$a['name'];
        if(!empty($pages)){
            $cls=!empty($a['class'])?" class='".esc_attr($a['class'])."'":'';
            $o="<select name='".esc_attr($a['name'])."'$cls id='".esc_attr($a['id'])."'>\n";
            if($a['show_option_no_change'])$o.="\t<option value=\"-1\">".$a['show_option_no_change']."</option>\n";
            if($a['show_option_none'])$o.="\t<option value=\"".esc_attr($a['option_none_value']).'">'.$a['show_option_none']."</option>\n";
            $o.=walk_page_dropdown_tree($pages,$a['depth'],$a)."</select>\n";
        }
        $html=apply_filters('wp_dropdown_pages',$o,$a,$pages);
        if($a['echo'])echo $html;
        return $html;
    }
}
if(!function_exists('the_attachment_link')){
    function the_attachment_link($post=0, $fullsize=false, $deprecated=false, $permalink=false) { echo wp_get_attachment_link($post,$fullsize?'full':'thumbnail',$permalink); }
}
if(!function_exists('wp_post_revision_title')){
    function wp_post_revision_title($revision, $link=true) {
        $r=get_post($revision);if(!$r)return $r;if(!in_array($r->post_type,['post','page','revision'],true))return false;
        $author=get_the_author_meta('display_name',$r->post_author);$date=date_i18n('d.m.Y @ H:i:s',strtotime($r->post_modified));
        $el=get_edit_post_link($r->ID);if($link&&current_user_can('edit_post',$r->ID)&&$el)$date="<a href='$el'>$date</a>";
        $t=sprintf('%1$s, vor %2$s (%3$s)',$author,human_time_diff((int)strtotime($r->post_modified_gmt.' UTC')),$date);
        if(!wp_is_post_revision($r))$t.=' [Aktuelle Revision]';elseif(str_contains((string)$r->post_name,'-autosave-v'))$t.=' [Autospeicherung]';
        return apply_filters('wp_post_revision_title',$t,$r,$link);
    }
}
if(!function_exists('wp_post_revision_title_expanded')){
    function wp_post_revision_title_expanded($revision, $link=true) {
        $r=get_post($revision);if(!$r)return $r;if(!in_array($r->post_type,['post','page','revision'],true))return false;
        $author=get_the_author_meta('display_name',$r->post_author);$date=date_i18n('d.m.Y @ H:i:s',strtotime($r->post_modified));
        $el=get_edit_post_link($r->ID);if($link&&current_user_can('edit_post',$r->ID)&&$el)$date="<a href='$el'>$date</a>";
        $t=sprintf('%1$s %2$s, vor %3$s (%4$s)',get_avatar($r->post_author,24),esc_html($author),human_time_diff((int)strtotime($r->post_modified_gmt.' UTC')),$date);
        if(!wp_is_post_revision($r))$t.=' [Aktuelle Revision]';elseif(str_contains((string)$r->post_name,'-autosave-v'))$t.=' [Autospeicherung]';
        return apply_filters('wp_post_revision_title_expanded',$t,$r,$link);
    }
}
if(!function_exists('wp_list_post_revisions')){
    function wp_list_post_revisions($post=0, $type='all') {
        $p=get_post($post);if(!$p)return;$rows='';
        foreach(rrw_wp_x_revisions($p->ID) as $r){
            if(!current_user_can('read_post',$r->ID))continue;$auto=str_contains((string)$r->post_name,'-autosave-v');
            if(('revision'===$type&&$auto)||('autosave'===$type&&!$auto))continue;
            $rows.="\t<li>".wp_post_revision_title_expanded($r)."</li>\n";
        }
        if($rows==='')return;
        echo "<ul class='post-revisions'>\n".$rows.'</ul>';
    }
}
if(!function_exists('the_post_thumbnail_caption')){
    function the_post_thumbnail_caption($post=null) { echo apply_filters('the_post_thumbnail_caption',get_the_post_thumbnail_caption($post)); }
}

/* ───────── Revisions-Oberfläche (wp-admin/includes/revision.php) ───────── */
if(!function_exists('wp_get_revision_ui_diff')){
    function wp_get_revision_ui_diff($post, $compare_from, $compare_to) {
        $post=get_post($post);$to=get_post($compare_to);if(!$post||!$to)return false;
        $from=$compare_from?get_post($compare_from):false;if($compare_from&&!$from)return false;
        $ret=[];
        foreach(_wp_post_revision_fields($post) as $f=>$name){
            $a=$from?apply_filters("_wp_post_revision_field_{$f}",$from->$f,$f,$from,'from'):'';$b=apply_filters("_wp_post_revision_field_{$f}",$to->$f,$f,$to,'to');
            $d=rrw_wp_x_text_diff($a,$b,'post_title'===$f);   // Der Titel wird auch ohne Änderung gezeigt
            if($d!=='')$ret[]=['id'=>$f,'name'=>$name,'diff'=>$d];
        }
        return apply_filters('wp_get_revision_ui_diff',$ret,$compare_from,$compare_to);
    }
}
if(!function_exists('wp_prepare_revisions_for_js')){
    function wp_prepare_revisions_for_js($post, $selected_revision_id, $from=null) {
        $post=get_post($post);if(!$post)return [];$authors=[];$now=time();$out=[];
        $list=rrw_wp_x_revisions($post->ID);$list=[$post->ID=>$post]+$list;
        foreach($list as $r){
            $mod=(int)strtotime($r->post_modified);$gmt=(int)strtotime($r->post_modified_gmt.' UTC');
            $authors[$r->post_author]=$authors[$r->post_author]??['id'=>(int)$r->post_author,'avatar'=>get_avatar($r->post_author,32),'name'=>get_the_author_meta('display_name',$r->post_author)];
            $restore=current_user_can('edit_post',$r->ID)?wp_nonce_url(add_query_arg(['revision'=>$r->ID,'action'=>'restore'],admin_url('revision.php')),"restore-post_{$r->ID}"):false;
            $out[$r->ID]=['id'=>(int)$r->ID,'title'=>get_the_title($post->ID),'author'=>$authors[$r->post_author],'date'=>date_i18n('d.m.Y @ H:i',$mod),'dateShort'=>date_i18n('j.n. @ H:i',$mod),'timeAgo'=>sprintf('vor %s',human_time_diff($gmt,$now)),
                'autosave'=>str_contains((string)$r->post_name,'-autosave-v'),'current'=>(int)$r->ID===(int)$post->ID,'restoreUrl'=>(int)$r->ID===(int)$post->ID?false:$restore];
        }
        return $out;
    }
}
if(!function_exists('wp_print_revision_templates')){ function wp_print_revision_templates() {} }   // Vorlagen der Revisions-Oberfläche (JavaScript) gibt es hier nicht

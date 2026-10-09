<?php
// Ergänzung (Benutzer): Verwaltungsfunktionen für Benutzer und Kommentare (wp-admin/includes/user.php, comment.php) sowie Autoren-Vorlagen (author-template.php).
// Reine Oberflächen-Helfer geben HTML aus oder liefern Standardwerte; die Daten gehen über wp_insert_user/wp_update_user und $wpdb.

/** Kommentar per ID aus der Tabelle wp_comments (zuerst), sonst CMS-Kommentar; Objekte werden durchgereicht. */
function elvado_wpx_comment($c) {
    global $wpdb;
    if($c instanceof WP_Comment)return $c;
    if(is_object($c))return new WP_Comment($c);
    $id=(int)($c?:($GLOBALS['comment']->comment_ID??0));if($id<=0)return null;
    if(elvado_wp_db_ready()){ $r=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->comments} WHERE comment_ID = %d LIMIT 1",$id));if($r)return new WP_Comment($r); }
    return get_comment($id);
}

/* ───────── Benutzer (wp-admin/includes/user.php) ───────── */
if(!function_exists('add_user')){ function add_user() { return edit_user(); } }
if(!function_exists('edit_user')){
    /** Benutzer aus $_POST anlegen (ID 0) oder aktualisieren. @return int|WP_Error */
    function edit_user($user_id=0) {
        $user=new stdClass();$user_id=(int)$user_id;$update=$user_id>0;
        if($update){ $old=get_userdata($user_id);$user->ID=$user_id;$user->user_login=$old?$old->user_login:''; }
        else $user->user_login=isset($_POST['user_login'])?sanitize_user((string)wp_unslash($_POST['user_login']),true):'';
        $p1=isset($_POST['pass1'])?trim((string)$_POST['pass1']):'';$p2=isset($_POST['pass2'])?trim((string)$_POST['pass2']):'';
        if(isset($_POST['role'])&&current_user_can('promote_users')){ $r=sanitize_text_field((string)$_POST['role']);if(isset(get_editable_roles()[$r]))$user->role=$r; }
        if(isset($_POST['email']))$user->user_email=sanitize_text_field(wp_unslash((string)$_POST['email']));
        if(isset($_POST['url'])){ $u=trim((string)$_POST['url']);$user->user_url=($u===''||$u==='http://')?'':(preg_match('#^[a-z][a-z0-9+.-]*:#i',$u)?esc_url_raw($u):esc_url_raw('http://'.$u)); }
        foreach(['first_name','last_name','nickname','display_name'] as $k)if(isset($_POST[$k]))$user->$k=sanitize_text_field(wp_unslash((string)$_POST[$k]));
        if(isset($_POST['description']))$user->description=trim((string)$_POST['description']);
        foreach(wp_get_user_contact_methods($user) as $m=>$n)if(isset($_POST[$m]))$user->$m=sanitize_text_field(wp_unslash((string)$_POST[$m]));
        if($update){
            $user->rich_editing=(isset($_POST['rich_editing'])&&$_POST['rich_editing']==='false')?'false':'true';
            $user->syntax_highlighting=(isset($_POST['syntax_highlighting'])&&$_POST['syntax_highlighting']==='false')?'false':'true';
            $user->admin_color=isset($_POST['admin_color'])?sanitize_text_field((string)$_POST['admin_color']):'fresh';
            $user->show_admin_bar_front=isset($_POST['admin_bar_front'])?'true':'false';
            $user->use_ssl=empty($_POST['use_ssl'])?0:1;
        }
        $e=new WP_Error();
        if($user->user_login==='')$e->add('user_login','<strong>Fehler:</strong> Bitte gib einen Benutzernamen ein.');
        if($update&&empty($user->nickname))$e->add('nickname','<strong>Fehler:</strong> Bitte gib einen Spitznamen ein.');
        do_action_ref_array('check_passwords',[$user->user_login,&$p1,&$p2]);
        if(!$update&&$p1==='')$e->add('pass','<strong>Fehler:</strong> Bitte gib ein Passwort ein.',['form-field'=>'pass1']);
        if($p1!==''&&$p1!==$p2)$e->add('pass','<strong>Fehler:</strong> Die Passwörter stimmen nicht überein.',['form-field'=>'pass1']);
        if($p1!=='')$user->user_pass=$p1;
        if(!$update&&isset($_POST['user_login'])&&!validate_username((string)$_POST['user_login']))$e->add('user_login','<strong>Fehler:</strong> Dieser Benutzername ist ungültig, weil er unzulässige Zeichen enthält.');
        if(!$update&&$user->user_login!==''&&username_exists($user->user_login))$e->add('user_login','<strong>Fehler:</strong> Dieser Benutzername ist bereits registriert.');
        if(empty($user->user_email))$e->add('empty_email','<strong>Fehler:</strong> Bitte gib eine E-Mail-Adresse ein.',['form-field'=>'email']);
        elseif(!is_email($user->user_email))$e->add('invalid_email','<strong>Fehler:</strong> Die E-Mail-Adresse ist ungültig.',['form-field'=>'email']);
        else{ $o=email_exists($user->user_email);if($o&&(!$update||(int)$o!==$user_id))$e->add('email_exists','<strong>Fehler:</strong> Diese E-Mail-Adresse wird bereits verwendet.',['form-field'=>'email']); }
        do_action_ref_array('user_profile_update_errors',[&$e,$update,&$user]);
        if($e->has_errors())return $e;
        if($update){
            $id=wp_update_user($user);
            if(!is_wp_error($id)){ global $wpdb;
                foreach(['first_name','last_name','nickname','description','rich_editing','syntax_highlighting','admin_color','show_admin_bar_front','use_ssl'] as $k)if(isset($user->$k))update_user_meta($id,$k,$user->$k);
                if(isset($user->role)&&$id>=ELVADO_WP_ID_DB_MIN)update_user_meta($id,$wpdb->prefix.'elvado_role',$user->role);
                elvado_wp_users_all(true); }
            return $id;
        }
        $id=wp_insert_user($user);
        if(!is_wp_error($id))do_action('edit_user_created_user',$id,isset($_POST['send_user_notification'])?'both':'admin');
        return $id;
    }
}
if(!function_exists('get_user_to_edit')){
    function get_user_to_edit($user_id) { $u=get_userdata((int)$user_id);if(!$u)return false;$u->filter='edit';return $u; }
}
if(!function_exists('get_users_drafts')){
    function get_users_drafts($user_id) {
        global $wpdb;if(!elvado_wp_db_ready())return [];
        $q=$wpdb->prepare("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'draft' AND post_author = %d ORDER BY post_modified DESC",(int)$user_id);
        return $wpdb->get_results((string)apply_filters('get_users_drafts',$q));
    }
}
if(!function_exists('wp_revoke_user')){
    /** Nimmt einem Benutzer der Tabelle wp_users die Rolle (Rolle „none“: nur Leserecht, kein Eintrag mehr in Rollenlisten). */
    function wp_revoke_user($id) {
        global $wpdb;$id=(int)$id;
        if($id>=ELVADO_WP_ID_DB_MIN&&elvado_wp_db_ready()){ update_user_meta($id,$wpdb->prefix.'elvado_role','none');elvado_wp_users_all(true); }
        do_action('wp_revoke_user',$id);
    }
}
if(!function_exists('default_password_nag_handler')){
    function default_password_nag_handler($errors=false) {
        global $user_ID;
        if(!get_user_option('default_password_nag'))return;
        if(isset($_GET['default_password_nag'])&&$_GET['default_password_nag']=='0')update_user_meta($user_ID?:get_current_user_id(),'default_password_nag',false);
    }
}
if(!function_exists('default_password_nag_edit_user')){
    function default_password_nag_edit_user($user_ID, $old_data) {
        $new=get_userdata((int)$user_ID);
        if($new&&$old_data&&$new->user_pass!==$old_data->user_pass)update_user_meta((int)$user_ID,'default_password_nag',false);
    }
}
if(!function_exists('default_password_nag')){
    function default_password_nag() {
        global $pagenow;
        if(!get_user_option('default_password_nag')||($pagenow??'')==='profile.php')return;
        echo '<div class="error default-password-nag"><p><strong>Hinweis:</strong> Du verwendest das automatisch erzeugte Passwort. Möchtest du es ändern? <a href="'.esc_url(admin_url('profile.php')).'#password">Ja, zum Profil</a> | <a href="'.esc_url(add_query_arg('default_password_nag','0')).'">Nein, nicht mehr nachfragen</a></p></div>';
    }
}
if(!function_exists('delete_users_add_js')){
    function delete_users_add_js() { echo '<script>jQuery(function($){var s=$("#submit").prop("disabled",true);$("input[name=delete_option]").one("change",function(){s.prop("disabled",false);});$("#reassign_user").focus(function(){$("#delete_option1").prop("checked",true).trigger("change");});});</script>'."\n"; }
}
if(!function_exists('use_ssl_preference')){
    function use_ssl_preference($user) {
        echo '<tr class="user-use-ssl-wrap"><th scope="row">HTTPS verwenden</th><td><label for="use_ssl"><input name="use_ssl" type="checkbox" id="use_ssl" value="1"'.(((string)($user->use_ssl??'')==='1')?' checked="checked"':'').' /> Beim Anmelden und im Administrationsbereich immer HTTPS verwenden</label></td></tr>';
    }
}
if(!function_exists('admin_created_user_email')){
    function admin_created_user_email($text) {
        $roles=get_editable_roles();$r=$roles[$_REQUEST['role']??'']??null;$role=is_array($r)?($r['name']??''):'';
        $site=get_bloginfo('name')!==''?wp_specialchars_decode((string)get_bloginfo('name'),ENT_QUOTES):(string)parse_url(home_url(),PHP_URL_HOST);
        return sprintf("Hallo,\n\ndu wurdest eingeladen, „%1\$s“ unter\n%2\$s mit der Rolle %3\$s beizutreten.\nFalls du nicht beitreten möchtest, ignoriere diese E-Mail.\nDie Einladung läuft in wenigen Tagen ab.\n\nBitte öffne diese Adresse, um dein Benutzerkonto zu aktivieren:\n%%s",$site,home_url(),$role);
    }
}
if(!function_exists('wp_is_authorize_application_redirect_url_valid')){
    /** Weiterleitungsadresse der Anwendungspasswort-Freigabe: https oder eigenes App-Schema; http nur lokal. */
    function wp_is_authorize_application_redirect_url_valid($url) {
        $url=(string)$url;if($url==='')return true;
        $scheme=strtolower((string)parse_url($url,PHP_URL_SCHEME));$host=strtolower((string)parse_url($url,PHP_URL_HOST));
        if($scheme===''||in_array($scheme,['javascript','data','vbscript','php','file'],true))return new WP_Error('invalid_redirect_scheme','Das Schema der Weiterleitungsadresse ist ungültig.');
        if($scheme==='http'&&!in_array($host,['localhost','127.0.0.1','[::1]'],true)&&wp_get_environment_type()==='production')return new WP_Error('invalid_redirect_scheme','Die Weiterleitungsadresse muss HTTPS verwenden.');
        return true;
    }
}
if(!function_exists('wp_is_authorize_application_password_request_valid')){
    function wp_is_authorize_application_password_request_valid($request, $user) {
        $e=new WP_Error();
        foreach(['success_url','reject_url'] as $k)if(isset($request[$k])){ $v=wp_is_authorize_application_redirect_url_valid($request[$k]);if(is_wp_error($v))$e->merge_from($v); }
        if(!empty($request['app_id'])&&!wp_is_uuid($request['app_id']))$e->add('invalid_app_id','Die Anwendungs-ID muss eine UUID sein.');
        if(!wp_is_application_passwords_available_for_user($user))$e->add('application_passwords_disabled_for_user','Anwendungspasswörter sind für diesen Benutzer nicht verfügbar.');
        do_action('wp_authorize_application_password_request_errors',$e,$request,$user);
        return $e->has_errors()?$e:true;
    }
}

/* ───────── Kommentare (wp-admin/includes/comment.php) ───────── */
if(!function_exists('comment_exists')){
    /** Beitrags-ID des Kommentars mit diesem Autor und Zeitpunkt (Blogzeit), sonst null. */
    function comment_exists($comment_author, $comment_date, $timezone='blog') {
        global $wpdb;if(!elvado_wp_db_ready())return null;
        $col=$timezone==='gmt'?'comment_date_gmt':'comment_date';
        return $wpdb->get_var($wpdb->prepare("SELECT comment_post_ID FROM {$wpdb->comments} WHERE comment_author = %s AND {$col} = %s LIMIT 1",stripslashes((string)$comment_author),stripslashes((string)$comment_date)));
    }
}
if(!function_exists('edit_comment')){
    function edit_comment() {
        if(!current_user_can('edit_comment',(int)($_POST['comment_ID']??0)))wp_die('Du darfst diesen Kommentar nicht bearbeiten.');
        foreach(['newcomment_author'=>'comment_author','newcomment_author_email'=>'comment_author_email','newcomment_author_url'=>'comment_author_url','comment_status'=>'comment_approved','content'=>'comment_content'] as $from=>$to)if(isset($_POST[$from]))$_POST[$to]=$_POST[$from];
        if(isset($_POST['comment_ID']))$_POST['comment_ID']=(int)$_POST['comment_ID'];
        foreach(['aa','mm','jj','hh','mn'] as $u)if(!empty($_POST['hidden_'.$u])&&$_POST['hidden_'.$u]!=($_POST[$u]??null)){ $_POST['edit_date']='1';break; }
        if(!empty($_POST['edit_date'])){
            $_POST['comment_date']=sprintf('%04d-%02d-%02d %02d:%02d:%02d',(int)($_POST['aa']??0),(int)($_POST['mm']??1),min(31,(int)($_POST['jj']??1)),min(23,(int)($_POST['hh']??0)),min(59,(int)($_POST['mn']??0)),min(59,(int)($_POST['ss']??0)));
        }
        return wp_update_comment($_POST);
    }
}
if(!function_exists('get_comment_to_edit')){
    function get_comment_to_edit($id) {
        $src=elvado_wpx_comment($id);if(!$src)return false;$c=clone $src;
        $c->comment_ID=(int)$c->comment_ID;$c->comment_post_ID=(int)$c->comment_post_ID;
        $c->comment_content=esc_textarea((string)apply_filters('comment_edit_pre',$c->comment_content));
        $c->comment_author=esc_attr((string)apply_filters('comment_author_edit_pre',$c->comment_author));
        $c->comment_author_email=esc_attr((string)$c->comment_author_email);
        $c->comment_author_url=esc_url((string)$c->comment_author_url);
        return $c;
    }
}
if(!function_exists('get_pending_comments_num')){
    /** Anzahl unfreigegebener Kommentare: Zahl bei einer ID, Array ID => Anzahl bei mehreren. */
    function get_pending_comments_num($post_id) {
        global $wpdb;$single=!is_array($post_id);$ids=array_values(array_unique(array_map('intval',(array)$post_id)));
        $out=array_fill_keys($ids,0);
        if($ids&&elvado_wp_db_ready()){
            $rows=$wpdb->get_results("SELECT comment_post_ID, COUNT(comment_ID) AS num_comments FROM {$wpdb->comments} WHERE comment_post_ID IN (".implode(',',$ids).") AND comment_approved = '0' GROUP BY comment_post_ID",ARRAY_A);
            foreach((array)$rows as $r)$out[(int)$r['comment_post_ID']]=absint($r['num_comments']);
        }
        return $single?(int)reset($out):$out;
    }
}
if(!function_exists('floated_admin_avatar')){
    function floated_admin_avatar($name) { global $comment;return get_avatar($comment,32,'mystery').' '.$name; }
}
if(!function_exists('enqueue_comment_hotkeys_js')){
    function enqueue_comment_hotkeys_js() { if(get_user_option('comment_shortcuts')==='true')wp_enqueue_script('jquery-table-hotkeys'); }
}
if(!function_exists('comment_footer_die')){
    function comment_footer_die($msg) { echo "<div class='wrap'><p>$msg</p></div>";wp_die('','',['response'=>200]); }
}

/* ───────── Autoren (author-template.php) ───────── */
if(!function_exists('get_the_modified_author')){
    /** Anzeigename des Benutzers, der den Beitrag zuletzt bearbeitet hat (Meta _edit_last); sonst null. */
    function get_the_modified_author() {
        $p=get_post();$last=$p?get_post_meta($p->ID,'_edit_last',true):0;if(!$last)return null;
        $u=get_userdata((int)$last);return apply_filters('the_modified_author',$u?$u->display_name:'');
    }
}
if(!function_exists('the_modified_author')){ function the_modified_author() { echo apply_filters('the_modified_author',(string)get_the_modified_author()); } }
if(!function_exists('wp_list_authors')){
    function wp_list_authors($args='') {
        $a=wp_parse_args($args,['orderby'=>'name','order'=>'ASC','number'=>'','optioncount'=>false,'exclude_admin'=>true,'show_fullname'=>false,'hide_empty'=>true,'feed'=>'','feed_image'=>'','feed_type'=>'','echo'=>true,'style'=>'list','html'=>true,'exclude'=>'','include'=>'']);
        $out='';
        foreach(get_users(['orderby'=>$a['orderby'],'order'=>$a['order'],'number'=>$a['number'],'exclude'=>$a['exclude'],'include'=>$a['include'],'fields'=>'ids']) as $id){
            $u=get_userdata((int)$id);if(!$u)continue;
            if($a['exclude_admin']&&$u->display_name==='admin')continue;
            $n=count_user_posts((int)$id,'post',true);if(!$n&&$a['hide_empty'])continue;
            $name=($a['show_fullname']&&$u->first_name&&$u->last_name)?$u->first_name.' '.$u->last_name:$u->display_name;
            if(!$a['html']){ $out.=$name.', ';continue; }
            $list=$a['style']==='list';
            $out.=($list?'<li>':'').'<a href="'.esc_url(get_author_posts_url($u->ID,$u->user_nicename)).'" title="'.esc_attr(sprintf('Beiträge von %s',$u->display_name)).'">'.esc_html($name).'</a>';
            if(!empty($a['feed'])||!empty($a['feed_image'])){ $furl=trailingslashit(get_author_posts_url($u->ID,$u->user_nicename)).'feed/';
                $out.=' <a href="'.esc_url($furl).'"'.($a['feed']!==''?' title="'.esc_attr($a['feed']).'"':'').'>'.($a['feed_image']!==''?'<img src="'.esc_url($a['feed_image']).'" alt="'.esc_attr($a['feed']).'" />':esc_html($a['feed'])).'</a>'; }
            if($a['optioncount'])$out.=' ('.$n.')';
            $out.=$list?'</li>':', ';
        }
        $out=rtrim($out,', ');
        if(!$a['echo'])return $out;
        echo $out;
    }
}
if(!function_exists('__clear_multi_author_cache')){ function __clear_multi_author_cache() { delete_transient('is_multi_author'); } }

/* ───────── Auswahllisten ───────── */
if(!function_exists('wp_dropdown_users')){
    /** Auswahlliste der Benutzer (<select>); mit echo=0 wird der HTML-Text zurückgegeben. */
    function wp_dropdown_users($args='') {
        $r=wp_parse_args($args,['show_option_all'=>'','show_option_none'=>'','hide_if_only_one_author'=>'','orderby'=>'display_name','order'=>'ASC','include'=>'','exclude'=>'','multi'=>0,'show'=>'display_name','echo'=>1,'selected'=>0,'name'=>'user','class'=>'','id'=>'','include_selected'=>false,'option_none_value'=>-1,'role'=>'','role__in'=>[]]);
        $q=array_intersect_key($r,array_flip(['include','exclude','orderby','order','role','role__in']));
        $users=get_users(apply_filters('wp_dropdown_users_args',$q,$r));
        $out='';
        if($users){
            if($r['hide_if_only_one_author']&&count($users)<=1)return '';
            $name=esc_attr($r['name']);$id=($r['multi']&&!$r['id'])?'':" id='".esc_attr($r['id']?:$name)."'";
            $out="<select name='{$name}'{$id} class='".esc_attr($r['class'])."'>\n";
            if($r['show_option_all']!=='')$out.="\t<option value='0'>".esc_html($r['show_option_all'])."</option>\n";
            if($r['show_option_none']!=='')$out.="\t<option value='".esc_attr($r['option_none_value'])."'".((string)$r['selected']===(string)$r['option_none_value']?" selected='selected'":'').'>'.esc_html($r['show_option_none'])."</option>\n";
            if($r['include_selected']&&(int)$r['selected']>0&&!in_array((int)$r['selected'],array_map(fn($u)=>(int)$u->ID,$users),true)){ $s=get_userdata((int)$r['selected']);if($s)$users[]=$s; }
            foreach($users as $u){
                $show=$r['show']?:'display_name';
                $disp=$show==='display_name_with_login'?sprintf('%1$s (%2$s)',$u->display_name,$u->user_login):(!empty($u->$show)?$u->$show:'('.$u->user_login.')');
                $out.="\t<option value='".(int)$u->ID."'".((int)$u->ID===(int)$r['selected']?" selected='selected'":'').'>'.esc_html($disp)."</option>\n";
            }
            $out.='</select>';
        }
        $html=apply_filters('wp_dropdown_users',$out);
        if($r['echo'])echo $html;
        return $html;
    }
}
if(!function_exists('wp_list_users')){
    /** Liste der Benutzer als <ul>-Liste (oder kommagetrennt); mit echo=false als Text zurückgeben. */
    function wp_list_users($args='') {
        $a=wp_parse_args($args,['orderby'=>'name','order'=>'ASC','number'=>'','role'=>'','include'=>'','exclude'=>'','show_fullname'=>false,'optioncount'=>false,'echo'=>true,'style'=>'list','html'=>true]);
        $out='';
        foreach(get_users(['orderby'=>$a['orderby'],'order'=>$a['order'],'number'=>$a['number'],'role'=>$a['role'],'include'=>$a['include'],'exclude'=>$a['exclude']]) as $u){
            $name=($a['show_fullname']&&$u->first_name&&$u->last_name)?$u->first_name.' '.$u->last_name:$u->display_name;
            $n=$a['optioncount']?' ('.count_user_posts((int)$u->ID,'post',true).')':'';
            if(!$a['html']){ $out.=$name.$n.', ';continue; }
            $list=$a['style']==='list';
            $out.=($list?'<li>':'').'<a href="'.esc_url(get_author_posts_url($u->ID,$u->user_nicename)).'">'.esc_html($name).'</a>'.$n.($list?'</li>':', ');
        }
        $out=rtrim($out,', ');
        if(!$a['echo'])return $out;
        echo $out;
    }
}

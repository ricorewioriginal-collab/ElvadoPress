<?php
// Ergänzende WordPress-Funktionen (Bereich Admin, Teil 1): Ajax-Handler wp_ajax_* für Inhalte – Kommentare, Begriffe, Beiträge, Meta, Benutzereinstellungen,
// Menüs, Heartbeat. Die Handler enden mit wp_die()/wp_send_json_*; der Router (admin.php: rrw_wp_ajax) fängt RRW_WP_Die ab. Sie werden hier an wp_ajax_{aktion} gehängt.

if(!function_exists('rrw_adm_comment_row')){ function rrw_adm_comment_row($c) {   // schlanke Tabellenzeile eines Kommentars für Ajax-Antworten
    $c=is_object($c)?$c:rrw_adm_comment($c);if(!$c)return '';
    return '<tr id="comment-'.(int)$c->comment_ID.'" class="comment '.esc_attr((string)rrw_adm_cstatus($c)).'"><td class="author">'.esc_html($c->comment_author).'</td><td class="comment">'.wp_kses_post($c->comment_content).'</td><td>'.esc_html($c->comment_date).'</td></tr>';
} }

/* ───────── Allgemein: angemeldet, Passwort, REST-Nonce, Datums-/Zeitformat ───────── */
if(!function_exists('wp_ajax_logged_in')){ function wp_ajax_logged_in() { wp_die(1); } }
if(!function_exists('wp_ajax_generate_password')){ function wp_ajax_generate_password() { wp_die(wp_generate_password(24)); } }
if(!function_exists('wp_ajax_nopriv_generate_password')){ function wp_ajax_nopriv_generate_password() { wp_ajax_generate_password(); } }
if(!function_exists('wp_ajax_rest_nonce')){ function wp_ajax_rest_nonce() { wp_die(wp_create_nonce('wp_rest')); } }
if(!function_exists('wp_ajax_date_format')){ function wp_ajax_date_format() { wp_die(date_i18n(sanitize_option('date_format',rrw_adm_req('date')))); } }
if(!function_exists('wp_ajax_time_format')){ function wp_ajax_time_format() { wp_die(date_i18n(sanitize_option('time_format',rrw_adm_req('date')))); } }
if(!function_exists('wp_ajax_wp_compression_test')){ function wp_ajax_wp_compression_test() {   // Komprimierungstest; das Ergebnis wird als Option gemerkt
    rrw_adm_need('manage_options');
    if(!empty($_GET['test'])){
        $t=(string)$_GET['test'];
        if($t==='no'||$t==='yes'){ update_site_option('can_compress_scripts',$t==='yes'?1:0);wp_die($t); }
        header('Content-Type: text/plain; charset=UTF-8');wp_die(str_repeat('wpCompressionTest',32));
    }
    wp_die(-1);
} }
if(!function_exists('wp_ajax_dismiss_wp_pointer')){ function wp_ajax_dismiss_wp_pointer() {
    $p=rrw_adm_req('pointer');if($p!==sanitize_key($p))wp_die(0);
    $d=array_filter(explode(',',(string)get_user_meta(get_current_user_id(),'dismissed_wp_pointers',true)));
    if(in_array($p,$d,true))wp_die(0);
    $d[]=$p;update_user_meta(get_current_user_id(),'dismissed_wp_pointers',implode(',',$d));
    do_action('dismiss_wp_pointer',$p);wp_die(1);
} }

/* ───────── Heartbeat ───────── */
if(!function_exists('wp_ajax_heartbeat')){ function wp_ajax_heartbeat() {   // angemeldet: Nonce prüfen, Daten über Filter beantworten
    if(empty($_POST['_nonce']))wp_send_json_error();
    $r=[];$data=isset($_POST['data'])?wp_unslash((array)$_POST['data']):[];$screen=isset($_POST['screen_id'])?sanitize_key($_POST['screen_id']):'front';
    if(!check_ajax_referer('heartbeat-nonce','_nonce',false)){ $r['nonces_expired']=true;wp_send_json($r); }
    if(!empty($data))$r=apply_filters('heartbeat_received',$r,$data,$screen);
    $r=apply_filters('heartbeat_send',$r,$screen);do_action('heartbeat_tick',$r,$screen);
    $r['server_time']=time();wp_send_json($r);
} }
if(!function_exists('wp_ajax_nopriv_heartbeat')){ function wp_ajax_nopriv_heartbeat() {   // Besucher: eigene Filter, nur mit Nonce-Feld
    if(empty($_POST['_nonce']))wp_send_json_error();
    $r=[];$data=isset($_POST['data'])?wp_unslash((array)$_POST['data']):[];$screen=isset($_POST['screen_id'])?sanitize_key($_POST['screen_id']):'front';
    if(!empty($data))$r=apply_filters('heartbeat_nopriv_received',$r,$data,$screen);
    $r=apply_filters('heartbeat_nopriv_send',$r,$screen);do_action('heartbeat_nopriv_tick',$r,$screen);
    $r['server_time']=time();wp_send_json($r);
} }

/* ───────── Listen, Schlagwort-Suche, Benutzer-Vorschläge ───────── */
if(!function_exists('wp_ajax_fetch_list')){ function wp_ajax_fetch_list() {   // Listen-Tabelle neu abrufen (nur wenn die Klasse da ist)
    $a=(array)($_GET['list_args']??[]);$cls=isset($a['class'])?preg_replace('/[^A-Za-z0-9_]/','',(string)wp_unslash($a['class'])):'';
    if($cls===''||!class_exists($cls)||!method_exists($cls,'ajax_user_can'))wp_die(0);
    $t=new $cls($a);if(!$t->ajax_user_can())wp_die(-1);
    $t->prepare_items();ob_start();$t->display();wp_send_json_success(['rows'=>ob_get_clean()]);
} }
if(!function_exists('wp_ajax_ajax_tag_search')){ function wp_ajax_ajax_tag_search() {
    if(!isset($_GET['tax']))wp_die(0);
    $tx=get_taxonomy(sanitize_key($_GET['tax']));if(!$tx)wp_die(0);
    if(!current_user_can($tx->cap->assign_terms??'edit_posts'))wp_die(-1);
    $s=wp_unslash((string)($_GET['q']??''));$c=_x(',','tag delimiter');if(','!==$c)$s=str_replace($c,',',$s);
    if(false!==strpos($s,',')){ $s=explode(',',$s);$s=$s[count($s)-1]; }
    $s=trim($s);if(strlen($s)<(int)apply_filters('term_search_min_chars',2,$tx,$s))wp_die();
    $r=get_terms(['taxonomy'=>$tx->name,'name__like'=>$s,'fields'=>'names','hide_empty'=>false]);
    echo implode("\n",is_wp_error($r)?[]:$r);wp_die();
} }
if(!function_exists('wp_ajax_autocomplete_user')){ function wp_ajax_autocomplete_user() {   // JSON-Liste {label,value} für Benutzer-Eingabefelder
    if(!is_multisite()||!current_user_can('promote_users')||wp_is_large_network('users')){ if(!current_user_can('list_users')&&!current_user_can('create_users'))wp_die(-1); }
    $term=rrw_adm_req('term');$type=rrw_adm_req('autocomplete_type','add');if(strlen($term)<2)wp_die();
    $out=[];
    foreach(get_users(['search'=>'*'.$term.'*','search_columns'=>['user_login','user_email','display_name'],'number'=>10]) as $u){
        if($type==='search'&&!current_user_can('list_users'))continue;
        $out[]=['label'=>sprintf('%1$s (%2$s)',$u->user_login,$u->user_email),'value'=>$u->user_login];
    }
    wp_die(wp_json_encode($out));
} }
if(!function_exists('wp_ajax_get_community_events')){ function wp_ajax_get_community_events() {   // keine Verbindung zu wordpress.org: leere Ereignisliste
    check_ajax_referer('community_events');
    wp_send_json_success(['location'=>['description'=>get_bloginfo('name')],'events'=>[],'error'=>null]);
} }
if(!function_exists('wp_ajax_dashboard_widgets')){ function wp_ajax_dashboard_widgets() {
    require_once ABSPATH.'wp-admin/includes/dashboard.php';
    $w=rrw_adm_req('widget');$pagenow=rrw_adm_req('pagenow','dashboard');
    if($pagenow==='dashboard-user'||$pagenow==='dashboard-network'||$pagenow==='dashboard')set_current_screen($pagenow);
    switch($w){
        case 'dashboard_primary': wp_dashboard_primary();break;
        case 'dashboard_quick_press': wp_dashboard_quick_press();break;
        default: wp_die(0);
    }
    wp_die();
} }

/* ───────── Kommentare ───────── */
if(!function_exists('_wp_ajax_delete_comment_response')){ function _wp_ajax_delete_comment_response($comment_id, $delta=-1) {   // XML-Antwort mit Zählern
    $c=rrw_adm_comment((int)$comment_id);$counts=wp_count_comments();
    $x=new WP_Ajax_Response(['what'=>'comment','id'=>(int)$comment_id,'supplemental'=>['status'=>$c?rrw_adm_cstatus($c):'','postId'=>$c?$c->comment_post_ID:'','time'=>time(),'in_moderation'=>$counts->moderated,
        'i18n_comments_text'=>sprintf('%s Kommentare',number_format_i18n($counts->approved)),'i18n_moderation_text'=>sprintf('%s in der Moderation',number_format_i18n($counts->moderated)),'delta'=>$delta]]);
    $x->send();
} }
if(!function_exists('wp_ajax_delete_comment')){ function wp_ajax_delete_comment() {
    $id=isset($_POST['id'])?(int)$_POST['id']:0;$c=rrw_adm_comment($id);if(!$c)wp_die(time());
    if(!current_user_can('edit_comment',$c->comment_ID))wp_die(-1);
    check_ajax_referer("delete-comment_$id");
    $st=rrw_adm_cstatus($id);$delta=-1;
    if(isset($_POST['trash'])&&'1'==$_POST['trash']){ if('trash'===$st)wp_die(time());update_comment_meta($id,'_wp_trash_meta_status',(string)$c->comment_approved);$r=wp_trash_comment($id); }
    elseif(isset($_POST['untrash'])&&'1'==$_POST['untrash']){
        if('trash'!==$st)wp_die(time());
        $r=wp_set_comment_status($id,get_comment_meta($id,'_wp_trash_meta_status',true)==='0'?'hold':'approve');delete_comment_meta($id,'_wp_trash_meta_status');   // vorherigen Status wiederherstellen
        if(!isset($_POST['comment_status'])||'trash'!==$_POST['comment_status'])$delta=1;
    }
    elseif(isset($_POST['spam'])&&'1'==$_POST['spam']){ if('spam'===$st)wp_die(time());update_comment_meta($id,'_wp_trash_meta_status',(string)$c->comment_approved);$r=wp_set_comment_status($id,'spam'); }
    elseif(isset($_POST['unspam'])&&'1'==$_POST['unspam']){ if('spam'!==$st)wp_die(time());$r=wp_unspam_comment($id);if(!isset($_POST['comment_status'])||'spam'!==$_POST['comment_status'])$delta=1; }
    elseif(isset($_POST['delete'])&&'1'==$_POST['delete'])$r=wp_delete_comment($id);
    else wp_die(-1);
    if($r)_wp_ajax_delete_comment_response($id,$delta);
    wp_die(0);
} }
if(!function_exists('wp_ajax_dim_comment')){ function wp_ajax_dim_comment() {   // freigeben/zurückstellen
    $id=isset($_POST['id'])?(int)$_POST['id']:0;$c=rrw_adm_comment($id);
    if(!$c){ $x=new WP_Ajax_Response(['what'=>'comment','id'=>new WP_Error('invalid_comment',sprintf('Kommentar %d existiert nicht.',$id))]);$x->send(); }
    if(!current_user_can('edit_comment',$c->comment_ID)&&!current_user_can('moderate_comments'))wp_die(-1);
    $cur=rrw_adm_cstatus($id);
    if(isset($_POST['new'])&&$_POST['new']==$cur)wp_die(time());
    check_ajax_referer("approve-comment_$id");
    $r=in_array($cur,['unapproved','spam'],true)?wp_set_comment_status($id,'approve',true):wp_set_comment_status($id,'hold',true);
    if(is_wp_error($r)){ $x=new WP_Ajax_Response(['what'=>'comment','id'=>$r]);$x->send(); }
    _wp_ajax_delete_comment_response($c->comment_ID);
    wp_die(0);
} }
if(!function_exists('wp_ajax_get_comments')){ function wp_ajax_get_comments() {
    $pid=(int)rrw_adm_req('p',rrw_adm_req('post_ID',0));check_ajax_referer('get-comments','_ajax_nonce-post');
    rrw_adm_need('edit_post',$pid);
    $rows='';foreach(rrw_wpx_comment_rows("SELECT comment_ID FROM {$GLOBALS['wpdb']->comments} WHERE comment_post_ID = %d ORDER BY comment_date_gmt ASC",[$pid]) as $r)$rows.=rrw_adm_comment_row((int)$r['comment_ID']);
    if($rows==='')foreach(get_comments(['post_id'=>$pid]) as $c)$rows.=rrw_adm_comment_row($c);   // CMS-Beiträge: Kommentare aus dem CMS-Speicher
    if($rows==='')wp_die(1);
    $x=new WP_Ajax_Response();$x->add(['what'=>'comments','data'=>$rows]);$x->send();
} }
if(!function_exists('wp_ajax_replyto_comment')){ function wp_ajax_replyto_comment() {   // Antwort auf einen Kommentar anlegen
    check_ajax_referer('replyto-comment','_ajax_nonce-replyto-comment');
    $pid=(int)rrw_adm_req('comment_post_ID');$post=get_post($pid);if(!$post)wp_die(-1);
    rrw_adm_need('edit_post',$pid);
    if(empty($post->post_status))wp_die(1);
    if(in_array($post->post_status,['draft','pending','trash'],true))wp_die('Fehler: Auf einen Entwurf kann nicht geantwortet werden.');
    $u=wp_get_current_user();
    if(!$u->exists())wp_die(-1);
    $data=['comment_post_ID'=>$pid,'comment_author'=>$u->display_name,'comment_author_email'=>$u->user_email,'comment_author_url'=>$u->user_url,'comment_content'=>trim((string)rrw_adm_req('content')),
        'comment_type'=>'comment','comment_parent'=>(int)rrw_adm_req('comment_ID',0),'user_id'=>$u->ID,'comment_approved'=>1,'comment_date'=>current_time('mysql'),'comment_date_gmt'=>current_time('mysql',1)];
    if($data['comment_content']==='')wp_die('Fehler: Bitte gib einen Kommentar ein.');
    if($data['comment_parent']&&'unapproved'===rrw_adm_cstatus($data['comment_parent']))wp_set_comment_status($data['comment_parent'],'approve');   // Antwort auf einen wartenden Kommentar gibt diesen frei (wie WordPress)
    $cid=wp_insert_comment(wp_slash($data));if(!$cid)wp_die('Fehler: Der Kommentar konnte nicht gespeichert werden.');
    $c=rrw_adm_comment($cid);if(!$c)wp_die(1);
    $x=new WP_Ajax_Response();$x->add(['what'=>'comment','id'=>$c->comment_ID,'data'=>rrw_adm_comment_row($c),'position'=>-1]);$x->send();
} }
if(!function_exists('wp_ajax_edit_comment')){ function wp_ajax_edit_comment() {   // Kommentar im Schnellzugriff speichern
    check_ajax_referer('replyto-comment','_ajax_nonce-replyto-comment');
    $id=(int)rrw_adm_req('comment_ID');rrw_adm_need('edit_comment',$id);
    if(rrw_adm_req('content')!=='')$_POST['comment_content']=$_POST['content'];
    if(!empty($_POST['status']))$_POST['comment_status']=$_POST['status'];
    $r=edit_comment();if(is_wp_error($r))wp_die($r->get_error_message());
    $c=rrw_adm_comment($id);if(!$c)wp_die(1);
    $x=new WP_Ajax_Response();$x->add(['what'=>'edit_comment','id'=>$c->comment_ID,'data'=>rrw_adm_comment_row($c),'position'=>-1]);$x->send();
} }

/* ───────── Begriffe und Link-Kategorien ───────── */
if(!function_exists('_wp_ajax_add_hierarchical_term')){ function _wp_ajax_add_hierarchical_term() {   // Aktion „add-{taxonomie}“: neue Begriffe als Checkbox-Zeilen
    $action=sanitize_key($_POST['action']??'');$tx=get_taxonomy(substr($action,4));if(!$tx)wp_die(0);
    check_ajax_referer($action,'_ajax_nonce-add-'.$tx->name);
    if(!current_user_can($tx->cap->edit_terms??'manage_categories'))wp_die(-1);
    $names=explode(',',(string)rrw_adm_req('new'.$tx->name));$parent=isset($_POST['new'.$tx->name.'_parent'])?max(0,(int)$_POST['new'.$tx->name.'_parent']):0;
    $x=new WP_Ajax_Response();
    foreach($names as $n){
        $n=trim($n);if(sanitize_title($n)==='')continue;
        $r=wp_insert_term($n,$tx->name,['parent'=>$parent]);
        if(is_wp_error($r)){ $e=term_exists($n,$tx->name,$parent);if(!$e)continue;$id=(int)(is_array($e)?$e['term_id']:$e); } else $id=(int)$r['term_id'];
        $field=$tx->name==='category'?'post_category[]':'tax_input['.$tx->name.'][]';
        $x->add(['what'=>$tx->name,'id'=>$id,'data'=>"<li id='{$tx->name}-$id'><label class='selectit'><input value='$id' type='checkbox' name='$field' checked='checked'/> ".esc_html($n).'</label></li>','position'=>-1]);
    }
    $x->send();
} }
if(!function_exists('wp_ajax_add_link_category')){ function wp_ajax_add_link_category($action='') {
    if(empty($action))$action='add-link-category';
    check_ajax_referer($action);
    $tx=get_taxonomy('link_category');if(!$tx||!current_user_can($tx->cap->manage_terms??'manage_categories'))wp_die(-1);
    $x=new WP_Ajax_Response();
    foreach(explode(',',(string)rrw_adm_req('newcat')) as $n){
        $n=trim($n);if(sanitize_title($n)==='')continue;
        $r=wp_insert_term($n,'link_category');if(is_wp_error($r)){ $e=term_exists($n,'link_category');if(!$e)continue;$id=(int)(is_array($e)?$e['term_id']:$e); } else $id=(int)$r['term_id'];
        $x->add(['what'=>'link-category','id'=>$id,'data'=>"<li id='link-category-$id'><label class='selectit'><input value='$id' type='checkbox' name='link_category[]' checked='checked'/> ".esc_html($n).'</label></li>','position'=>-1]);
    }
    $x->send();
} }
if(!function_exists('wp_ajax_add_tag')){ function wp_ajax_add_tag() {
    check_ajax_referer('add-tag','_wpnonce_add-tag');
    $tn=sanitize_key($_POST['taxonomy']??'post_tag');$tx=get_taxonomy($tn);
    if(!$tx||!current_user_can($tx->cap->edit_terms??'manage_categories'))wp_die(-1);
    $r=wp_insert_term(wp_unslash((string)($_POST['tag-name']??'')),$tn,wp_unslash($_POST));
    if($r&&!is_wp_error($r)){
        $t=get_term($r['term_id'],$tn);
        $x=new WP_Ajax_Response(['what'=>'taxonomy','data'=>'<tr id="tag-'.(int)$t->term_id.'"><td>'.esc_html($t->name).'</td><td>'.esc_html($t->slug).'</td><td>'.(int)$t->count.'</td></tr>','supplemental'=>['name'=>$t->name,'slug'=>$t->slug]]);
    } else {
        wp_die(is_wp_error($r)?$r->get_error_message():0);
    }
    $x->send();
} }
if(!function_exists('wp_ajax_delete_tag')){ function wp_ajax_delete_tag() {
    $id=(int)($_POST['tag_ID']??0);$tn=sanitize_key($_POST['taxonomy']??'post_tag');$tx=get_taxonomy($tn);
    check_ajax_referer("delete-tag_$id");
    if(!$tx||!current_user_can($tx->cap->delete_terms??'manage_categories'))wp_die(-1);
    $t=get_term($id,$tn);if(!$t||is_wp_error($t))wp_die(1);
    if(wp_delete_term($id,$tn))wp_die(1);
    wp_die(0);
} }
if(!function_exists('wp_ajax_get_tagcloud')){ function wp_ajax_get_tagcloud() {
    if(!isset($_POST['tax']))wp_die(0);
    $tx=get_taxonomy(sanitize_key($_POST['tax']));if(!$tx)wp_die(0);
    if(!current_user_can($tx->cap->assign_terms??'edit_posts'))wp_die(-1);
    $tags=get_terms(['taxonomy'=>$tx->name,'number'=>45,'orderby'=>'count','order'=>'DESC']);
    if(empty($tags)||is_wp_error($tags))wp_die($tx->labels->not_found??'Keine Begriffe gefunden.');
    foreach($tags as $t)$t->link='#';
    echo wp_generate_tag_cloud($tags,['filter'=>0,'format'=>'list']);wp_die();
} }
if(!function_exists('wp_ajax_inline_save_tax')){ function wp_ajax_inline_save_tax() {
    check_ajax_referer('taxinlineeditnonce','_inline_edit');
    $tn=sanitize_key($_POST['taxonomy']??'');$tx=get_taxonomy($tn);if(!$tx)wp_die(0);
    if(!isset($_POST['tax_ID'])||!(int)$_POST['tax_ID'])wp_die(-1);
    $id=(int)$_POST['tax_ID'];if(!current_user_can($tx->cap->edit_terms??'manage_categories'))wp_die(-1);
    $t=get_term($id,$tn);if(!$t||is_wp_error($t))wp_die(0);
    $r=wp_update_term($id,$tn,wp_unslash($_POST));if(is_wp_error($r))wp_die($r->get_error_message());
    $t=get_term($id,$tn);
    echo '<tr id="tag-'.$id.'"><td>'.esc_html($t->name).'</td><td>'.esc_html($t->slug).'</td><td>'.(int)$t->count.'</td></tr>';wp_die();
} }

/* ───────── Beiträge, Seiten, Links, Eigene Felder ───────── */
if(!function_exists('wp_ajax_delete_link')){ function wp_ajax_delete_link() {
    $id=(int)($_POST['id']??0);check_ajax_referer("delete-bookmark_$id");
    rrw_adm_need('manage_links');
    if(function_exists('wp_delete_link')&&wp_delete_link($id))wp_die(1);
    wp_die(0);
} }
if(!function_exists('wp_ajax_delete_meta')){ function wp_ajax_delete_meta() {
    $id=(int)($_POST['id']??0);check_ajax_referer("delete-meta_$id");
    $m=get_metadata_by_mid('post',$id);if(!$m)wp_die(1);
    if(is_protected_meta($m->meta_key,'post')||!current_user_can('edit_post',$m->post_id))wp_die(-1);
    if(delete_meta($id))wp_die(1);
    wp_die(0);
} }
if(!function_exists('wp_ajax_delete_post')){ function wp_ajax_delete_post($action='') {
    if(empty($action))$action='delete-post';
    $id=(int)($_POST['id']??0);check_ajax_referer("{$action}_$id");
    $post=get_post($id);if(!$post)wp_die(1);
    if(!current_user_can('delete_post',$id))wp_die(-1);
    if('trash-post'===$action)$r=wp_trash_post($id);
    elseif('untrash-post'===$action)$r=wp_untrash_post($id);
    else $r=wp_delete_post($id,true);
    wp_die($r?1:0);
} }
if(!function_exists('wp_ajax_trash_post')){ function wp_ajax_trash_post($action='') { if(empty($action))$action='trash-post';wp_ajax_delete_post($action); } }
if(!function_exists('wp_ajax_untrash_post')){ function wp_ajax_untrash_post($action='') { if(empty($action))$action='untrash-post';wp_ajax_delete_post($action); } }
if(!function_exists('wp_ajax_delete_page')){ function wp_ajax_delete_page($action='') { if(empty($action))$action='delete-page';wp_ajax_delete_post($action); } }
if(!function_exists('wp_ajax_add_meta')){ function wp_ajax_add_meta() {
    check_ajax_referer('add-meta','_ajax_nonce-add-meta');$c=0;$pid=(int)($_POST['post_id']??0);
    if(isset($_POST['metakeyselect'])||isset($_POST['metakeyinput'])){
        rrw_adm_need('edit_post',$pid);
        if(isset($_POST['metakeyselect'])&&'#NONE#'==$_POST['metakeyselect']&&empty($_POST['metakeyinput']))wp_die(1);
        $mid=add_meta($pid);if(!$mid)wp_die('Bitte gib einen Wert für das Feld an.');
        $m=get_metadata_by_mid('post',$mid);
        $x=new WP_Ajax_Response(['what'=>'meta','id'=>$mid,'data'=>_list_meta_row(['meta_id'=>$mid,'meta_key'=>$m->meta_key,'meta_value'=>$m->meta_value],$c),'position'=>1,'supplemental'=>['postid'=>(int)$m->post_id]]);
    } else {
        $mid=(int)key((array)($_POST['meta']??[]));if(!$mid)wp_die(0);
        $key=wp_unslash($_POST['meta'][$mid]['key']??'');$val=wp_unslash($_POST['meta'][$mid]['value']??'');
        if(''==trim($key))wp_die('Bitte gib einen Namen für das Feld an.');
        $m=get_metadata_by_mid('post',$mid);if(!$m)wp_die(0);
        if(!current_user_can('edit_post',$m->post_id)||is_protected_meta($key,'post'))wp_die(-1);
        if($m->meta_value!=$val||$m->meta_key!=$key){ if(!update_metadata_by_mid('post',$mid,$val,$key))wp_die(0); }
        $x=new WP_Ajax_Response(['what'=>'meta','id'=>$mid,'old_id'=>$mid,'data'=>_list_meta_row(['meta_id'=>$mid,'meta_key'=>$key,'meta_value'=>$val],$c),'position'=>0,'supplemental'=>['postid'=>(int)$m->post_id]]);
    }
    $x->send();
} }
if(!function_exists('wp_ajax_add_user')){ function wp_ajax_add_user($action='') {
    if(empty($action))$action='add-user';
    check_ajax_referer($action,'_wpnonce_create-user');
    rrw_adm_need('create_users');
    $id=add_user();
    if(is_wp_error($id))wp_die($id->get_error_message());
    if(!$id)wp_die(0);
    $u=get_userdata($id);
    $x=new WP_Ajax_Response(['what'=>'user','id'=>$id,'data'=>'<tr id="user-'.(int)$id.'"><td>'.esc_html($u->user_login).'</td><td>'.esc_html($u->user_email).'</td></tr>','supplemental'=>['edit_link'=>get_edit_user_link($id)]]);
    $x->send();
} }
if(!function_exists('wp_ajax_inline_save')){ function wp_ajax_inline_save() {   // Schnellbearbeitung eines Beitrags
    check_ajax_referer('inlineeditnonce','_inline_edit');
    $pid=(int)($_POST['post_ID']??0);if(!$pid)wp_die();
    $post=get_post($pid);if(!$post)wp_die(-1);
    rrw_adm_need('edit_post',$pid);
    if(!empty($post->post_status)&&'locked'===wp_check_post_lock($pid))wp_die('Fehler: Der Eintrag wird gerade bearbeitet.');
    $_POST['ID']=$pid;
    if(isset($_POST['_status']))$_POST['post_status']=$_POST['_status'];
    if(empty($_POST['post_name']))$_POST['post_name']=$post->post_name;
    if(!empty($_POST['post_parent']))$_POST['parent_id']=$_POST['post_parent'];
    $r=edit_post();if(is_wp_error($r))wp_die($r->get_error_message());
    $p=get_post($pid);
    echo '<tr id="post-'.(int)$pid.'" class="'.esc_attr('status-'.$p->post_status).'"><td>'.esc_html(get_the_title($p)).'</td><td>'.esc_html($p->post_status).'</td><td>'.esc_html($p->post_date).'</td></tr>';wp_die();
} }
if(!function_exists('wp_ajax_find_posts')){ function wp_ajax_find_posts() {
    check_ajax_referer('find-posts');
    $types=get_post_types(['public'=>true],'objects');unset($types['attachment']);
    $args=['post_type'=>array_keys($types),'post_status'=>'any','posts_per_page'=>50];
    $s=trim((string)wp_unslash($_POST['ps']??''));if($s!=='')$args['s']=$s;
    $posts=get_posts($args);if(!$posts)wp_send_json_error('Keine Einträge gefunden.');
    $h='<table class="widefat"><thead><tr><th>Titel</th><th>Typ</th><th>Datum</th><th>Status</th></tr></thead><tbody>';
    foreach($posts as $p)$h.='<tr><td><input type="radio" name="found_post_id" value="'.(int)$p->ID.'"> '.esc_html(trim($p->post_title)?:'(kein Titel)').'</td><td>'.esc_html($p->post_type).'</td><td>'.esc_html(mysql2date('Y/m/d',$p->post_date)).'</td><td>'.esc_html($p->post_status).'</td></tr>';
    wp_send_json_success($h.'</tbody></table>');
} }
if(!function_exists('wp_ajax_get_permalink')){ function wp_ajax_get_permalink() {
    check_ajax_referer('getpermalink','getpermalinknonce');
    wp_die(get_preview_post_link((int)($_POST['post_id']??0))?:get_permalink((int)($_POST['post_id']??0)));
} }
if(!function_exists('wp_ajax_sample_permalink')){ function wp_ajax_sample_permalink() {
    check_ajax_referer('samplepermalink','samplepermalinknonce');
    wp_die(get_sample_permalink_html((int)($_POST['post_id']??0),isset($_POST['new_title'])?(string)$_POST['new_title']:null,isset($_POST['new_slug'])?(string)$_POST['new_slug']:null));
} }
if(!function_exists('wp_ajax_wp_fullscreen_save_post')){ function wp_ajax_wp_fullscreen_save_post() {   // Speichern aus dem Vollbild-Editor (JSON)
    $pid=(int)($_POST['post_ID']??0);$post=get_post($pid);
    check_ajax_referer('update-post_'.$pid,'_wpnonce');
    rrw_adm_need('edit_post',$pid);
    $_POST['ID']=$pid;$r=edit_post();
    if(is_wp_error($r))wp_send_json_error(['message'=>$r->get_error_message()]);
    $p=get_post($pid);
    wp_send_json(['last_edited'=>$p?sprintf('Zuletzt bearbeitet am %s',mysql2date('j. F Y, H:i',$p->post_modified)):'','message'=>'Gespeichert.']);
} }
if(!function_exists('wp_ajax_wp_remove_post_lock')){ function wp_ajax_wp_remove_post_lock() {
    if(empty($_POST['post_ID'])||empty($_POST['active_post_lock']))wp_die(0);
    $pid=(int)$_POST['post_ID'];$post=get_post($pid);if(!$post)wp_die(0);
    check_ajax_referer('update-post_'.$pid);rrw_adm_need('edit_post',$pid);
    $a=array_map('absint',explode(':',(string)wp_unslash($_POST['active_post_lock'])));
    if(!isset($a[1])||$a[1]!=get_current_user_id())wp_die(0);
    $new=(time()-(int)apply_filters('wp_check_post_lock_window',150)+5).':'.$a[1];
    update_post_meta($pid,'_edit_lock',$new,implode(':',$a));wp_die(1);
} }
if(!function_exists('wp_ajax_get_revision_diffs')){ function wp_ajax_get_revision_diffs() {
    require_once ABSPATH.'wp-admin/includes/revision.php';
    $post=get_post((int)($_REQUEST['post_id']??0));if(!$post)wp_send_json_error();
    if(!current_user_can('edit_post',$post->ID))wp_send_json_error();
    $r=[];foreach((array)($_REQUEST['compare']??[]) as $pair){
        $p=explode(':',(string)wp_unslash($pair));if(count($p)!==2)continue;
        $d=wp_get_revision_ui_diff($post,(int)$p[0],(int)$p[1]);if($d)$r[]=['id'=>$pair,'fields'=>$d];
    }
    wp_send_json_success($r);
} }

/* ───────── Benutzereinstellungen und Bildschirmoptionen ───────── */
if(!function_exists('wp_ajax_closed_postboxes')){ function wp_ajax_closed_postboxes() {
    check_ajax_referer('closedpostboxes','closedpostboxesnonce');
    $closed=isset($_POST['closed'])?array_filter(explode(',',(string)$_POST['closed'])):[];$closed=array_map('sanitize_key',$closed);
    $hidden=isset($_POST['hidden'])?array_filter(explode(',',(string)$_POST['hidden'])):[];$hidden=array_map('sanitize_key',$hidden);
    $page=isset($_POST['page'])?(string)$_POST['page']:'';if($page!=sanitize_key($page))wp_die(0);
    $u=wp_get_current_user();if(!$u->exists())wp_die(-1);
    if(is_array($closed))update_user_option($u->ID,"closedpostboxes_$page",$closed,true);
    if(is_array($hidden)){ $hidden=array_diff($hidden,['submitdiv','linksubmitdiv','manage-menu','create-menu']);update_user_option($u->ID,"metaboxhidden_$page",$hidden,true); }
    wp_die(1);
} }
if(!function_exists('wp_ajax_hidden_columns')){ function wp_ajax_hidden_columns() {
    check_ajax_referer('screen-options-nonce','screenoptionnonce');
    $page=isset($_POST['page'])?(string)$_POST['page']:'';if($page!=sanitize_key($page))wp_die(0);
    $u=wp_get_current_user();if(!$u->exists())wp_die(-1);
    $hidden=!empty($_POST['hidden'])?array_map('sanitize_key',explode(',',(string)$_POST['hidden'])):[];
    update_user_option($u->ID,"manage{$page}columnshidden",$hidden,true);wp_die(1);
} }
if(!function_exists('wp_ajax_update_welcome_panel')){ function wp_ajax_update_welcome_panel() {
    check_ajax_referer('welcome-panel-nonce','welcomepanelnonce');rrw_adm_need('edit_theme_options');
    update_user_meta(get_current_user_id(),'show_welcome_panel',empty($_POST['visible'])?0:1);wp_die(1);
} }
if(!function_exists('wp_ajax_meta_box_order')){ function wp_ajax_meta_box_order() {
    check_ajax_referer('meta-box-order');
    $order=isset($_POST['order'])?(array)$_POST['order']:false;$cols=isset($_POST['page_columns'])?(string)$_POST['page_columns']:'';
    $page=isset($_POST['page'])?(string)$_POST['page']:'';if($page!=sanitize_key($page))wp_die(0);
    $u=wp_get_current_user();if(!$u->exists())wp_die(-1);
    if($order)update_user_option($u->ID,"meta-box-order_$page",$order,true);
    if($cols)update_user_option($u->ID,"screen_layout_$page",$cols,true);
    wp_send_json_success();
} }
if(!function_exists('wp_ajax_save_user_color_scheme')){ function wp_ajax_save_user_color_scheme() {
    global $_wp_admin_css_colors;
    check_ajax_referer('save-color-scheme','color-nonce');
    $c=sanitize_key($_POST['color_scheme']??'');
    if(is_array($_wp_admin_css_colors)&&!isset($_wp_admin_css_colors[$c]))wp_send_json_error();
    $prev=get_user_meta(get_current_user_id(),'admin_color',true);
    update_user_meta(get_current_user_id(),'admin_color',$c);
    wp_send_json_success(['previousScheme'=>'admin-color-'.$prev,'currentScheme'=>'admin-color-'.$c]);
} }
if(!function_exists('wp_ajax_destroy_sessions')){ function wp_ajax_destroy_sessions() {
    $u=get_userdata((int)($_POST['user_id']??0));if(!$u)wp_send_json_error(['message'=>'Der Benutzer wurde nicht gefunden.']);
    check_ajax_referer('destroy-sessions-'.$u->ID,'nonce');
    if(!rrw_adm_can_user($u->ID))wp_send_json_error(['message'=>'Du darfst die Sitzungen dieses Benutzers nicht beenden.']);
    if($u->ID===get_current_user_id()){ wp_destroy_other_sessions();$m='Du bist jetzt überall sonst abgemeldet.'; }
    else { delete_user_meta($u->ID,'session_tokens');$m=sprintf('%s wurde überall abgemeldet.',$u->display_name); }
    wp_send_json_success(['message'=>$m]);
} }
if(!function_exists('wp_ajax_save_wporg_username')){ function wp_ajax_save_wporg_username() {   // ohne Abfrage bei wordpress.org: Name wird so gespeichert
    $uid=(int)($_REQUEST['user_id']??0);check_ajax_referer("save_wporg_username_{$uid}");
    if(!rrw_adm_can_user($uid))wp_send_json_error();
    $n=trim((string)rrw_adm_req('username'));if($n==='')wp_send_json_error();
    update_user_meta($uid,'wporg_favorites',$n);wp_send_json_success($n);
} }
if(!function_exists('wp_ajax_send_password_reset')){ function wp_ajax_send_password_reset() {
    $u=get_userdata((int)($_POST['user_id']??0));if(!$u)wp_send_json_error('Der Benutzer wurde nicht gefunden.');
    check_ajax_referer('reset-password-for-'.$u->user_login);
    if(!rrw_adm_can_user($u->ID))wp_send_json_error('Du darfst diesen Benutzer nicht bearbeiten.');
    $r=retrieve_password($u->user_login);
    if(is_wp_error($r))wp_send_json_error($r->get_error_message());
    wp_send_json_success(sprintf('Ein Link zum Zurücksetzen des Passworts wurde an %s gesendet.',$u->user_email));
} }

/* ───────── Menüs ───────── */
if(!function_exists('wp_ajax_add_menu_item')){ function wp_ajax_add_menu_item() {
    check_ajax_referer('add-menu_item','menu-settings-column-nonce');rrw_adm_need('edit_theme_options');
    $items=isset($_POST['menu-item'])?wp_unslash((array)$_POST['menu-item']):[];
    foreach($items as $i=>$it){ if(empty($it['menu-item-type']))unset($items[$i]); }
    $ids=wp_save_nav_menu_items(0,$items);
    if(is_wp_error($ids))wp_die(0);
    foreach((array)$ids as $id){ $p=get_post($id);if($p)echo '<li id="menu-item-'.(int)$id.'" class="menu-item"><span class="item-title">'.esc_html($p->post_title).'</span></li>'; }
    wp_die();
} }
if(!function_exists('wp_ajax_menu_get_metabox')){ function wp_ajax_menu_get_metabox() {
    rrw_adm_need('edit_theme_options');
    $t=sanitize_key($_POST['item-type']??'');$o=sanitize_key($_POST['item-object']??'');
    if($t==='post_type'&&get_post_type_object($o)){ $obj=get_post_type_object($o);ob_start();wp_nav_menu_item_post_type_meta_box(null,['args'=>$obj,'id'=>'add-post-type-'.$o]);wp_die(ob_get_clean()); }
    if($t==='taxonomy'&&get_taxonomy($o)){ $obj=get_taxonomy($o);ob_start();wp_nav_menu_item_taxonomy_meta_box(null,['args'=>$obj,'id'=>'add-'.$o]);wp_die(ob_get_clean()); }
    wp_die(0);
} }
if(!function_exists('wp_ajax_menu_locations_save')){ function wp_ajax_menu_locations_save() {
    rrw_adm_need('edit_theme_options');check_ajax_referer('add-menu_item','menu-settings-column-nonce');
    if(!isset($_POST['menu-locations']))wp_die(0);
    set_theme_mod('nav_menu_locations',array_map('absint',(array)$_POST['menu-locations']));wp_die(1);
} }
if(!function_exists('wp_ajax_menu_quick_search')){ function wp_ajax_menu_quick_search() { rrw_adm_need('edit_theme_options');_wp_ajax_menu_quick_search($_POST);wp_die(); } }
if(!function_exists('wp_ajax_wp_link_ajax')){ function wp_ajax_wp_link_ajax() {   // Verknüpfungssuche des Editors: Beiträge und Seiten nach Titel
    check_ajax_referer('internal-linking','_ajax_linking_nonce');
    $s=isset($_POST['search'])?wp_unslash((string)$_POST['search']):'';$page=max(1,(int)($_POST['page']??1));
    $args=['post_type'=>get_post_types(['public'=>true]),'post_status'=>'publish','posts_per_page'=>20,'paged'=>$page,'orderby'=>'post_date','order'=>'DESC'];unset($args['post_type']['attachment']);
    $args['post_type']=array_values($args['post_type']);if($s!=='')$args['s']=$s;
    $r=[];foreach(get_posts($args) as $p)$r[]=['ID'=>$p->ID,'title'=>trim(esc_html(strip_tags(get_the_title($p)))),'permalink'=>get_permalink($p->ID),'info'=>mysql2date('Y/m/d',$p->post_date)];
    wp_die(wp_json_encode($r)."\n");
} }

/* ───────── Registrierung der Aktionen (Namen mit Bindestrich wie bei WordPress) ───────── */
rrw_adm_hook(['logged-in'=>'wp_ajax_logged_in','generate-password'=>'wp_ajax_generate_password','rest-nonce'=>'wp_ajax_rest_nonce','date_format'=>'wp_ajax_date_format','time_format'=>'wp_ajax_time_format',
    'wp-compression-test'=>'wp_ajax_wp_compression_test','dismiss-wp-pointer'=>'wp_ajax_dismiss_wp_pointer','heartbeat'=>'wp_ajax_heartbeat','fetch-list'=>'wp_ajax_fetch_list','ajax-tag-search'=>'wp_ajax_ajax_tag_search',
    'autocomplete-user'=>'wp_ajax_autocomplete_user','get-community-events'=>'wp_ajax_get_community_events','dashboard-widgets'=>'wp_ajax_dashboard_widgets','delete-comment'=>'wp_ajax_delete_comment',
    'dim-comment'=>'wp_ajax_dim_comment','get-comments'=>'wp_ajax_get_comments','replyto-comment'=>'wp_ajax_replyto_comment','edit-comment'=>'wp_ajax_edit_comment','add-link-category'=>'wp_ajax_add_link_category',
    'add-tag'=>'wp_ajax_add_tag','delete-tag'=>'wp_ajax_delete_tag','get-tagcloud'=>'wp_ajax_get_tagcloud','inline-save-tax'=>'wp_ajax_inline_save_tax','delete-link'=>'wp_ajax_delete_link','delete-meta'=>'wp_ajax_delete_meta',
    'delete-post'=>'wp_ajax_delete_post','trash-post'=>'wp_ajax_trash_post','untrash-post'=>'wp_ajax_untrash_post','delete-page'=>'wp_ajax_delete_page','add-meta'=>'wp_ajax_add_meta','add-user'=>'wp_ajax_add_user',
    'inline-save'=>'wp_ajax_inline_save','find_posts'=>'wp_ajax_find_posts','get-permalink'=>'wp_ajax_get_permalink','sample-permalink'=>'wp_ajax_sample_permalink','wp-fullscreen-save-post'=>'wp_ajax_wp_fullscreen_save_post',
    'wp-remove-post-lock'=>'wp_ajax_wp_remove_post_lock','get-revision-diffs'=>'wp_ajax_get_revision_diffs','closed-postboxes'=>'wp_ajax_closed_postboxes','hidden-columns'=>'wp_ajax_hidden_columns',
    'update-welcome-panel'=>'wp_ajax_update_welcome_panel','meta-box-order'=>'wp_ajax_meta_box_order','save-user-color-scheme'=>'wp_ajax_save_user_color_scheme','destroy-sessions'=>'wp_ajax_destroy_sessions',
    'save-wporg-username'=>'wp_ajax_save_wporg_username','send-password-reset'=>'wp_ajax_send_password_reset','add-menu-item'=>'wp_ajax_add_menu_item','menu-get-metabox'=>'wp_ajax_menu_get_metabox',
    'menu-locations-save'=>'wp_ajax_menu_locations_save','menu-quick-search'=>'wp_ajax_menu_quick_search','wp-link-ajax'=>'wp_ajax_wp_link_ajax']);
if(!has_action('wp_ajax_add-category'))add_action('wp_ajax_add-category','_wp_ajax_add_hierarchical_term',1);
if(!has_action('wp_ajax_nopriv_heartbeat'))add_action('wp_ajax_nopriv_heartbeat','wp_ajax_nopriv_heartbeat',1);
if(!has_action('wp_ajax_nopriv_generate-password'))add_action('wp_ajax_nopriv_generate-password','wp_ajax_nopriv_generate_password',1);

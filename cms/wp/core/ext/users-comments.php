<?php
// Ergänzung (Benutzer/Kommentare): Kommentar-Logik von wp-includes/comment.php – Prüfung/Moderation, Flood-Schutz, Statuswechsel, Zähler,
// Benachrichtigungen, Pingbacks/Trackbacks, Datenschutz. Datenquelle: Tabelle wp_comments (und die freigegebenen CMS-Kommentare, wo gelesen wird).
// Funktionen, die Kommentare ändern, wirken nur auf die Tabelle wp_comments (CMS-Kommentare moderiert das CMS).

/** Standard-Haken der Kommentarprüfung einmalig anmelden (statt beim Laden). */
function rrw_wpx_comment_defaults(): void {
    static $done=false;if($done)return;$done=true;
    if(!has_action('check_comment_flood','check_comment_flood_db'))add_action('check_comment_flood','check_comment_flood_db',10,4);
    if(!has_filter('comment_flood_filter','wp_throttle_comment_flood'))add_filter('comment_flood_filter','wp_throttle_comment_flood',10,3);
    if(!has_action('transition_comment_status','_clear_modified_cache_on_transition_comment_status'))add_action('transition_comment_status','_clear_modified_cache_on_transition_comment_status',10,2);
}
/** Prüft, ob einer der Zeilen-Begriffe (Groß-/Kleinschreibung egal) in einem der Felder vorkommt. */
function rrw_wpx_words_match(string $keys, array $fields): bool {
    foreach(explode("\n",$keys) as $w){ $w=trim($w);if($w==='')continue;$p='#'.preg_quote($w,'#').'#i';foreach($fields as $f)if(preg_match($p,(string)$f))return true; }
    return false;
}
/** Beitrags-ID-Zahl je Status aus wp_comments (leer ohne Datenbank). */
function rrw_wpx_comment_rows(string $sql, array $args=[]): array {
    global $wpdb;if(!rrw_wp_db_ready())return [];
    return (array)$wpdb->get_results($args?$wpdb->prepare($sql,...$args):$sql,ARRAY_A);
}

/* ───────── Prüfung und Moderation ───────── */
if(!function_exists('check_comment')){
    /** Wahr, wenn der Kommentar ohne Moderation freigegeben werden darf. */
    function check_comment($author, $email, $url, $comment, $user_ip, $user_agent, $comment_type='') {
        global $wpdb;
        if(1==get_option('comment_moderation',0))return false;
        $comment=apply_filters('comment_text',$comment,null,[]);
        $max=(int)get_option('comment_max_links',2);
        if($max){ $n=(int)apply_filters('comment_max_links_url',preg_match_all('/<a [^>]*href/i',(string)$comment),$url,$comment);if($n>=$max)return false; }
        $mod=trim((string)get_option('moderation_keys',''));
        if($mod!==''&&rrw_wpx_words_match($mod,[$author,$email,$url,$comment,$user_ip,$user_agent]))return false;
        if(1==get_option('comment_previously_approved',1)){
            if($comment_type==='trackback'||$comment_type==='pingback'||$author===''||$email===''||!rrw_wp_db_ready())return false;
            $cu=get_user_by('email',wp_unslash($email));
            $ok=$cu&&!empty($cu->ID)
                ?$wpdb->get_var($wpdb->prepare("SELECT comment_approved FROM {$wpdb->comments} WHERE user_id = %d AND comment_approved = '1' LIMIT 1",$cu->ID))
                :$wpdb->get_var($wpdb->prepare("SELECT comment_approved FROM {$wpdb->comments} WHERE comment_author = %s AND comment_author_email = %s AND comment_approved = '1' LIMIT 1",wp_unslash($author),wp_unslash($email)));
            return 1==$ok&&($mod===''||!str_contains((string)$email,$mod));
        }
        return true;
    }
}
if(!function_exists('wp_check_comment_disallowed_list')){
    function wp_check_comment_disallowed_list($author, $email, $url, $comment, $user_ip, $user_agent) {
        do_action('wp_check_comment_disallowed_list',$author,$email,$url,$comment,$user_ip,$user_agent);
        $keys=trim((string)get_option('disallowed_keys',''));if($keys==='')return false;
        return rrw_wpx_words_match($keys,[$author,$email,$url,$comment,wp_strip_all_tags((string)$comment),$user_ip,$user_agent]);
    }
}
if(!function_exists('wp_check_comment_data')){
    /** Freigabe-Entscheidung: 1, 0 (Moderation), „trash“ oder „spam“. */
    function wp_check_comment_data($comment_data) {
        $d=(array)$comment_data;$user=!empty($d['user_id'])?get_userdata((int)$d['user_id']):false;
        $post=get_post((int)($d['comment_post_ID']??0));
        if($user&&((int)$d['user_id']===(int)($post->post_author??-1)||$user->has_cap('moderate_comments')))return 1;
        $args=[(string)($d['comment_author']??''),(string)($d['comment_author_email']??''),(string)($d['comment_author_url']??''),(string)($d['comment_content']??''),(string)($d['comment_author_IP']??''),(string)($d['comment_agent']??'')];
        $approved=check_comment($args[0],$args[1],$args[2],$args[3],$args[4],$args[5],(string)($d['comment_type']??'comment'))?1:0;
        if(wp_check_comment_disallowed_list(...$args))$approved=EMPTY_TRASH_DAYS?'trash':'spam';
        return $approved;
    }
}
if(!function_exists('wp_get_comment_fields_max_lengths')){
    function wp_get_comment_fields_max_lengths() { return apply_filters('wp_get_comment_fields_max_lengths',['comment_author'=>245,'comment_author_email'=>100,'comment_author_url'=>200,'comment_content'=>65525]); }
}
if(!function_exists('check_comment_flood_db')){
    /** Rückwärtskompatibler Haken: meldet die eigentliche Flood-Prüfung (wp_check_comment_flood) beim Filter wp_is_comment_flood an. */
    function check_comment_flood_db(...$args) { if(!has_filter('wp_is_comment_flood','wp_check_comment_flood'))add_filter('wp_is_comment_flood','wp_check_comment_flood',10,5); }
}
if(!function_exists('wp_check_comment_flood')){
    function wp_check_comment_flood($is_flood, $ip, $email, $date, $avoid_die=false) {
        global $wpdb;
        if($is_flood===true)return true;
        if(current_user_can('manage_options')||current_user_can('moderate_comments')||!rrw_wp_db_ready())return false;
        $hour_ago=gmdate('Y-m-d H:i:s',time()-HOUR_IN_SECONDS);
        if(is_user_logged_in()){ $who=get_current_user_id();$col='user_id'; } else { $who=$ip;$col='comment_author_IP'; }
        $last=$wpdb->get_var($wpdb->prepare("SELECT comment_date_gmt FROM {$wpdb->comments} WHERE comment_date_gmt >= %s AND ( {$col} = %s OR comment_author_email = %s ) ORDER BY comment_date_gmt DESC LIMIT 1",$hour_ago,$who,$email));
        if($last){
            $t1=(int)mysql2date('U',$last,false);$t2=(int)mysql2date('U',$date,false);
            if(apply_filters('comment_flood_filter',false,$t1,$t2)){
                do_action('comment_flood_trigger',$t1,$t2);
                if($avoid_die)return true;
                wp_die('Du schreibst Kommentare zu schnell. Bitte warte einen Moment.',429);
            }
        }
        return false;
    }
}
if(!function_exists('wp_throttle_comment_flood')){
    function wp_throttle_comment_flood($block, $time_lastcomment, $time_newcomment) { return $block?$block:($time_newcomment-$time_lastcomment)<15; }
}
if(!function_exists('wp_allow_comment')){
    /** Doppelte/zu schnelle Kommentare ablehnen, sonst Freigabestatus (1, 0, „spam“, „trash“) liefern. */
    function wp_allow_comment($commentdata, $wp_error=false) {
        global $wpdb;rrw_wpx_comment_defaults();
        $c=wp_parse_args($commentdata,['comment_post_ID'=>0,'comment_author'=>'','comment_author_email'=>'','comment_author_url'=>'','comment_content'=>'','comment_parent'=>0,'comment_author_IP'=>'','comment_agent'=>'','comment_type'=>'comment','user_id'=>0,'comment_date_gmt'=>current_time('mysql',1)]);
        $dupe=null;
        if(rrw_wp_db_ready()){
            $q="SELECT comment_ID FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_parent = %d AND comment_approved != 'trash' AND ( comment_author = %s";$a=[(int)$c['comment_post_ID'],(int)$c['comment_parent'],wp_unslash($c['comment_author'])];
            if($c['comment_author_email']!==''){ $q.=' OR comment_author_email = %s';$a[]=wp_unslash($c['comment_author_email']); }
            $dupe=$wpdb->get_var($wpdb->prepare($q.') AND comment_content = %s LIMIT 1',...array_merge($a,[wp_unslash($c['comment_content'])])));
        }
        $dupe=apply_filters('duplicate_comment_id',$dupe,$c);
        if($dupe){
            do_action('comment_duplicate_trigger',$c);$msg='Doppelter Kommentar erkannt – du hast das offenbar schon geschrieben.';
            if($wp_error)return new WP_Error('comment_duplicate',$msg,409);
            wp_die($msg,409);
        }
        do_action('check_comment_flood',$c['comment_author_IP'],$c['comment_author_email'],$c['comment_date_gmt'],$wp_error);
        if(apply_filters('wp_is_comment_flood',false,$c['comment_author_IP'],$c['comment_author_email'],$c['comment_date_gmt'],$wp_error))
            return new WP_Error('comment_flood','Du schreibst Kommentare zu schnell. Bitte warte einen Moment.',429);
        return apply_filters('pre_comment_approved',wp_check_comment_data($c),$c);
    }
}
if(!function_exists('separate_comments')){
    function separate_comments(&$comments) {
        $by=['comment'=>[],'trackback'=>[],'pingback'=>[],'pings'=>[]];
        foreach((array)$comments as $c){ $t=$c->comment_type?:'comment';$by[$t][]=$c;if($t==='trackback'||$t==='pingback')$by['pings'][]=$c; }
        return $by;
    }
}
if(!function_exists('wp_get_unapproved_comment_author_email')){
    function wp_get_unapproved_comment_author_email() {
        $mail='';
        if(!empty($_GET['unapproved'])&&!empty($_GET['moderation-hash'])){
            $c=rrw_wpx_comment((int)$_GET['unapproved']);
            if($c&&hash_equals((string)$_GET['moderation-hash'],wp_hash($c->comment_date_gmt)))$mail=$c->comment_author_email;
        }
        return $mail?:(string)(wp_get_current_commenter()['comment_author_email']??'');
    }
}

/* ───────── Abfragen und Zähler ───────── */
if(!function_exists('get_approved_comments')){
    function get_approved_comments($post_id, $args=[]) {
        if(!$post_id)return [];
        return get_comments(wp_parse_args($args,['status'=>1,'orderby'=>'comment_date_gmt','order'=>'ASC','post_id'=>$post_id]));
    }
}
if(!function_exists('get_comment_statuses')){
    function get_comment_statuses() { return ['hold'=>'Ausstehend','approve'=>'Genehmigt','spam'=>'Spam','trash'=>'Papierkorb']; }
}
if(!function_exists('get_default_comment_status')){
    function get_default_comment_status($post_type='post', $comment_type='comment') {
        [$supports,$opt]=in_array($comment_type,['pingback','trackback'],true)?['trackbacks','ping']:['comments','comment'];
        $status=post_type_supports($post_type,$supports)?get_option("default_{$opt}_status",'open'):'closed';
        return apply_filters('get_default_comment_status',$status,$post_type,$comment_type);
    }
}
if(!function_exists('get_lastcommentmodified')){
    /** Zeitpunkt des jüngsten freigegebenen Kommentars („server“/„gmt“ = UTC, „blog“ = Blogzeit); false ohne Kommentare. */
    function get_lastcommentmodified($timezone='server') {
        $max='';foreach(rrw_wp_cms_comments() as $c)if($c->comment_date_gmt>$max)$max=$c->comment_date_gmt;
        $r=rrw_wpx_comment_rows("SELECT MAX(comment_date_gmt) AS m FROM {$GLOBALS['wpdb']->comments} WHERE comment_approved = '1'");
        if(!empty($r[0]['m'])&&$r[0]['m']>$max)$max=$r[0]['m'];
        $tz=strtolower((string)$timezone);
        $d=$max===''?false:($tz==='blog'?get_date_from_gmt($max):$max);
        return apply_filters('get_lastcommentmodified',$d,$timezone);
    }
}
if(!function_exists('get_comment_count')){
    function get_comment_count($post_id=0) {
        global $wpdb;$post_id=(int)$post_id;
        $n=['approved'=>0,'awaiting_moderation'=>0,'spam'=>0,'trash'=>0,'post-trashed'=>0,'total_comments'=>0,'all'=>0];
        foreach(rrw_wp_cms_comments() as $c)if(!$post_id||(int)$c->comment_post_ID===$post_id){ $n['approved']++;$n['total_comments']++;$n['all']++; }
        $rows=rrw_wpx_comment_rows("SELECT comment_approved, COUNT(*) AS total FROM {$wpdb->comments}".($post_id?' WHERE comment_post_ID = %d':'').' GROUP BY comment_approved',$post_id?[$post_id]:[]);
        foreach($rows as $r){ $t=(int)$r['total'];
            switch((string)$r['comment_approved']){
                case 'trash':$n['trash']+=$t;break;
                case 'post-trashed':$n['post-trashed']+=$t;break;
                case 'spam':$n['spam']+=$t;$n['total_comments']+=$t;break;
                case '1':$n['approved']+=$t;$n['total_comments']+=$t;$n['all']+=$t;break;
                case '0':$n['awaiting_moderation']+=$t;$n['total_comments']+=$t;$n['all']+=$t;break;
            }
        }
        return $n;
    }
}
if(!function_exists('get_page_of_comment')){
    /** Seite der Kommentarliste, auf der der Kommentar steht (nur freigegebene Hauptkommentare zählen). */
    function get_page_of_comment($comment_id, $args=[]) {
        $orig=$args;$c=rrw_wpx_comment($comment_id);if(!$c)return null;
        $a=wp_parse_args($args,['type'=>'all','page'=>'','per_page'=>'','max_depth'=>'']);
        $per=$a['per_page'];
        if($per==='')$per=function_exists('get_query_var')?get_query_var('comments_per_page'):'';
        if($per===''||!$per)$per=get_option('comments_per_page',50);
        if(empty($per)){ $per=0;$a['page']=0; }
        if($per<1)$page=1;
        else{
            $max=$a['max_depth'];if($max==='')$max=get_option('thread_comments',1)?get_option('thread_comments_depth',5):-1;
            if($max>1&&(int)$c->comment_parent!==0){ $a['max_depth']=$max;return get_page_of_comment((int)$c->comment_parent,$a); }
            $older=0;
            foreach(get_comments(['post_id'=>(int)$c->comment_post_ID,'parent'=>0,'order'=>'ASC']) as $o){
                $t=$o->comment_type?:'comment';
                if($a['type']==='pings'?!in_array($t,['trackback','pingback'],true):($a['type']!=='all'&&$t!==$a['type']))continue;
                if($o->comment_date_gmt<$c->comment_date_gmt||($o->comment_date_gmt===$c->comment_date_gmt&&(int)$o->comment_ID<(int)$c->comment_ID))$older++;
            }
            $page=(int)ceil(($older+1)/$per);
        }
        return apply_filters('get_page_of_comment',(int)$page,$a,$orig,$comment_id);
    }
}
if(!function_exists('wp_lazyload_comment_meta')){
    function wp_lazyload_comment_meta(array $comments) { $ids=array_filter(array_map(fn($c)=>(int)($c->comment_ID??0),$comments));if($ids)update_meta_cache('comment',$ids); }
}
if(!function_exists('wp_cache_set_comments_last_changed')){ function wp_cache_set_comments_last_changed() { wp_cache_set('last_changed',microtime(),'comment'); } }
if(!function_exists('clean_comment_cache')){
    function clean_comment_cache($ids) {
        $ids=array_map('intval',(array)$ids);wp_cache_delete_multiple($ids,'comment');
        foreach($ids as $id)do_action('clean_comment_cache',$id);
        wp_cache_set_comments_last_changed();
    }
}
if(!function_exists('update_comment_cache')){
    function update_comment_cache($comments, $update_meta_cache=true) {
        $ids=[];foreach((array)$comments as $c){ wp_cache_add($c->comment_ID,$c,'comment');$ids[]=(int)$c->comment_ID; }
        if($update_meta_cache&&$ids)update_meta_cache('comment',$ids);
    }
}
if(!function_exists('_prime_comment_caches')){
    function _prime_comment_caches($comment_ids, $update_meta_cache=true) {
        global $wpdb;$miss=[];
        foreach(array_unique(array_filter(array_map('intval',(array)$comment_ids))) as $id){ wp_cache_get($id,'comment',false,$found);if(!$found)$miss[]=$id; }
        if($miss&&rrw_wp_db_ready()){
            $rows=$wpdb->get_results("SELECT * FROM {$wpdb->comments} WHERE comment_ID IN (".implode(',',$miss).')');
            update_comment_cache(array_map(fn($r)=>new WP_Comment($r),(array)$rows),$update_meta_cache);
        }
    }
}
if(!function_exists('_clear_modified_cache_on_transition_comment_status')){
    function _clear_modified_cache_on_transition_comment_status($new_status, $old_status) {
        if($new_status==='approved'||$old_status==='approved')wp_cache_delete_multiple(['lastcommentmodified:server','lastcommentmodified:gmt','lastcommentmodified:blog'],'timeinfo');
    }
}

/* ───────── Cookies und Formular ───────── */
if(!function_exists('wp_set_comment_cookies')){
    function wp_set_comment_cookies($comment, $user, $cookies_consent=true) {
        if($user->exists())return;
        $path=defined('COOKIEPATH')?COOKIEPATH:'/';$dom=defined('COOKIE_DOMAIN')?COOKIE_DOMAIN:'';
        $set=function($n,$v,$t) use($path,$dom){ if(!headers_sent())@setcookie($n,$v,$t,$path,$dom,is_ssl()); };
        $f=['comment_author_'=>$comment->comment_author,'comment_author_email_'=>$comment->comment_author_email,'comment_author_url_'=>$comment->comment_author_url];
        if($cookies_consent===false){ foreach($f as $k=>$v)$set($k.COOKIEHASH,' ',time()-YEAR_IN_SECONDS);return; }
        $life=time()+(int)apply_filters('comment_cookie_lifetime',30000000);
        foreach($f as $k=>$v)$set($k.COOKIEHASH,(string)$v,$life);
    }
}
if(!function_exists('sanitize_comment_cookies')){
    function sanitize_comment_cookies() {
        foreach(['comment_author_'=>['pre_comment_author_name','esc_attr'],'comment_author_email_'=>['pre_comment_author_email','esc_attr'],'comment_author_url_'=>['pre_comment_author_url','esc_url']] as $k=>[$flt,$esc]){
            $n=$k.COOKIEHASH;
            if(isset($_COOKIE[$n]))$_COOKIE[$n]=$esc(wp_unslash(apply_filters($flt,$_COOKIE[$n])));
        }
    }
}

/* ───────── Statuswechsel ───────── */
if(!function_exists('wp_transition_comment_status')){
    function wp_transition_comment_status($new_status, $old_status, $comment) {
        rrw_wpx_comment_defaults();
        $map=[0=>'unapproved','0'=>'unapproved','hold'=>'unapproved',1=>'approved','1'=>'approved','approve'=>'approved'];
        $new_status=$map[$new_status]??$new_status;$old_status=$map[$old_status]??$old_status;
        if($new_status!==$old_status){ do_action('transition_comment_status',$new_status,$old_status,$comment);do_action("comment_{$old_status}_to_{$new_status}",$comment); }
        do_action("comment_{$new_status}_{$comment->comment_type}",$comment->comment_ID,$comment);
    }
}
if(!function_exists('wp_unspam_comment')){
    function wp_unspam_comment($comment_id) {
        $c=rrw_wpx_comment($comment_id);if(!$c)return false;
        do_action('unspam_comment',$c->comment_ID,$c);
        $st=(string)get_comment_meta($c->comment_ID,'_wp_trash_meta_status',true);if($st==='')$st='0';
        if(wp_set_comment_status($c->comment_ID,$st==='1'?'approve':'hold')){ delete_comment_meta($c->comment_ID,'_wp_trash_meta_status');do_action('unspammed_comment',$c->comment_ID,$c);return true; }
        return false;
    }
}
if(!function_exists('wp_update_comment_count_now')){
    /** Zähler comment_count des Beitrags neu aus den freigegebenen Kommentaren bestimmen (Beiträge in wp_posts). */
    function wp_update_comment_count_now($post_id) {
        global $wpdb;$post_id=(int)$post_id;if(!$post_id)return false;
        $post=get_post($post_id);if(!$post)return false;
        $old=(int)$post->comment_count;$new=apply_filters('pre_wp_update_comment_count_now',null,$old,$post_id);
        if($new===null){ $r=rrw_wpx_comment_rows("SELECT COUNT(*) AS n FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_approved = '1'",[$post_id]);$new=(int)($r[0]['n']??0); }
        $new=(int)$new;
        if($post_id>=RRW_WP_ID_DB_MIN)$wpdb->update($wpdb->posts,['comment_count'=>$new],['ID'=>$post_id]);
        clean_post_cache($post);
        do_action('wp_update_comment_count',$post_id,$new,$old);do_action("edit_post_{$post->post_type}",$post_id,$post);do_action('edit_post',$post_id,$post);
        return true;
    }
}

/* ───────── Benachrichtigungen ───────── */
if(!function_exists('wp_notify_postauthor')){
    function wp_notify_postauthor($comment_id, $deprecated=null) {
        $c=rrw_wpx_comment($comment_id);if(!$c)return false;
        $post=get_post((int)$c->comment_post_ID);$author=$post?get_userdata((int)$post->post_author):false;
        if(!$post||!$author||$author->user_email===''||(int)$c->user_id===(int)$post->post_author)return false;
        $site=rrw_wpx_site_name();$type=$c->comment_type?:'comment';
        $kind=['trackback'=>'Trackback','pingback'=>'Pingback'][$type]??'Kommentar';
        $msg=sprintf("Neuer %s zu deinem Beitrag „%s“\n\nAutor: %s (IP: %s)\nE-Mail: %s\nWebsite: %s\n\n%s\n\nAnzeigen: %s\n",$kind,$post->post_title,$c->comment_author,$c->comment_author_IP,$c->comment_author_email,$c->comment_author_url,$c->comment_content,get_comment_link($c));
        $subject=sprintf('[%s] %s: „%s“',$site,$kind,$post->post_title);
        $to=apply_filters('comment_notification_recipients',[$author->user_email],$c->comment_ID);
        $msg=apply_filters('comment_notification_text',$msg,$c->comment_ID);$subject=apply_filters('comment_notification_subject',$subject,$c->comment_ID);
        $headers=apply_filters('comment_notification_headers',"Content-Type: text/plain; charset=\"UTF-8\"\n",$c->comment_ID);
        foreach(array_unique((array)$to) as $t)if($t!=='')wp_mail($t,wp_specialchars_decode($subject),$msg,$headers);
        return true;
    }
}
if(!function_exists('wp_notify_moderator')){
    function wp_notify_moderator($comment_id) {
        $maybe=apply_filters('notify_moderator',get_option('moderation_notify',1),$comment_id);if(!$maybe)return true;
        $c=rrw_wpx_comment($comment_id);if(!$c||$c->comment_approved!=='0')return false;
        $post=get_post((int)$c->comment_post_ID);$site=rrw_wpx_site_name();
        $pending=get_pending_comments_num((int)$c->comment_post_ID);
        $msg=sprintf("Ein neuer Kommentar zum Beitrag „%s“ wartet auf Freigabe.\n\nAutor: %s (IP: %s)\nE-Mail: %s\nWebsite: %s\n\n%s\n\nFreigeben: %s\nInsgesamt warten %d Kommentare auf Freigabe.\n",$post->post_title??'',$c->comment_author,$c->comment_author_IP,$c->comment_author_email,$c->comment_author_url,$c->comment_content,admin_url('comment.php?action=approve&c='.$c->comment_ID),$pending);
        $subject=sprintf('[%s] Bitte freigeben: „%s“',$site,$post->post_title??'');
        $to=apply_filters('comment_moderation_recipients',[get_option('admin_email')],$c->comment_ID);
        $msg=apply_filters('comment_moderation_text',$msg,$c->comment_ID);$subject=apply_filters('comment_moderation_subject',$subject,$c->comment_ID);
        $headers=apply_filters('comment_moderation_headers',"Content-Type: text/plain; charset=\"UTF-8\"\n",$c->comment_ID);
        foreach(array_unique((array)$to) as $t)if($t)wp_mail($t,wp_specialchars_decode($subject),$msg,$headers);
        return true;
    }
}
if(!function_exists('wp_new_comment_notify_moderator')){
    function wp_new_comment_notify_moderator($comment_id) {
        $c=rrw_wpx_comment($comment_id);if(!$c)return false;
        $maybe=apply_filters('notify_moderator',(string)$c->comment_approved==='0',$comment_id);
        return $maybe?wp_notify_moderator($comment_id):false;
    }
}
if(!function_exists('wp_new_comment_notify_postauthor')){
    function wp_new_comment_notify_postauthor($comment_id) {
        $c=rrw_wpx_comment($comment_id);if(!$c)return false;
        $maybe=apply_filters('notify_post_author',get_option('comments_notify',1),$comment_id);
        if(!$maybe||(string)$c->comment_approved!=='1')return false;
        return wp_notify_postauthor($comment_id);
    }
}

/* ───────── Alte Beiträge, Hintergrundaufträge ───────── */
if(!function_exists('_close_comments_for_old_posts')){
    function _close_comments_for_old_posts($posts, $query) {
        $single=is_object($query)&&(method_exists($query,'is_singular')?$query->is_singular():!empty($query->is_singular));
        if(empty($posts)||!$single||!get_option('close_comments_for_old_posts'))return $posts;
        if(!in_array($posts[0]->post_type,(array)apply_filters('close_comments_for_post_types',['post']),true))return $posts;
        $days=(int)get_option('close_comments_days_old');if(!$days)return $posts;
        if(time()-strtotime($posts[0]->post_date_gmt.' UTC')>$days*DAY_IN_SECONDS){ $posts[0]->comment_status='closed';$posts[0]->ping_status='closed'; }
        return $posts;
    }
}
if(!function_exists('_close_comments_for_old_post')){
    function _close_comments_for_old_post($open, $post_id) {
        if(!$open||!get_option('close_comments_for_old_posts'))return $open;
        $days=(int)get_option('close_comments_days_old');if(!$days)return $open;
        $p=get_post($post_id);if(!$p||!in_array($p->post_type,(array)apply_filters('close_comments_for_post_types',['post']),true))return $open;
        if($p->post_date_gmt==='0000-00-00 00:00:00')return $open;
        return time()-strtotime($p->post_date_gmt.' UTC')>$days*DAY_IN_SECONDS?false:$open;
    }
}
if(!function_exists('_wp_batch_update_comment_type')){
    /** Setzt leere Kommentartypen stapelweise auf „comment“ und plant bei Bedarf den nächsten Durchlauf. */
    function _wp_batch_update_comment_type() {
        global $wpdb;if(!rrw_wp_db_ready())return;
        $size=max(1,(int)apply_filters('wp_update_comment_type_batch_size',100));
        $ids=array_map('intval',(array)$wpdb->get_col($wpdb->prepare("SELECT comment_ID FROM {$wpdb->comments} WHERE comment_type = '' ORDER BY comment_ID DESC LIMIT %d",$size)));
        if($ids){ $wpdb->query("UPDATE {$wpdb->comments} SET comment_type = 'comment' WHERE comment_ID IN (".implode(',',$ids).')');clean_comment_cache($ids); }
        if(count($ids)>=$size){ if(!wp_next_scheduled('wp_update_comment_type_batch'))wp_schedule_single_event(time()+MINUTE_IN_SECONDS,'wp_update_comment_type_batch');return; }
        update_option('finished_updating_comment_type',true);wp_clear_scheduled_hook('wp_update_comment_type_batch');
    }
}
if(!function_exists('_wp_check_for_scheduled_update_comment_type')){
    function _wp_check_for_scheduled_update_comment_type() { if(!get_option('finished_updating_comment_type')&&!wp_next_scheduled('wp_update_comment_type_batch'))wp_schedule_single_event(time()+MINUTE_IN_SECONDS,'wp_update_comment_type_batch'); }
}

/* ───────── Kommentar absenden (Formular) ───────── */
if(!function_exists('wp_handle_comment_submission')){
    /** Prüft ein Kommentarformular und speichert den Kommentar. @return WP_Comment|WP_Error */
    function wp_handle_comment_submission($comment_data) {
        $d=(array)$comment_data;$pid=(int)($d['comment_post_ID']??0);$parent=(int)($d['comment_parent']??0);
        $author=isset($d['author'])&&is_string($d['author'])?trim($d['author']):null;$email=isset($d['email'])&&is_string($d['email'])?trim($d['email']):null;
        $url=isset($d['url'])&&is_string($d['url'])?trim($d['url']):null;$text=isset($d['comment'])&&is_string($d['comment'])?trim($d['comment']):null;
        $post=get_post($pid);
        if(empty($post->comment_status)){ do_action('comment_id_not_found',$pid);return new WP_Error('comment_id_not_found','Dieser Beitrag existiert nicht.',404); }
        $status=get_post_status($post);$so=get_post_status_object($status);
        if(!comments_open($pid)){ do_action('comment_closed',$pid);return new WP_Error('comment_closed','Die Kommentare sind hier geschlossen.',403); }
        if($status==='trash'){ do_action('comment_on_trash',$pid);return new WP_Error('comment_on_trash','Der Beitrag ist im Papierkorb.',403); }
        if(!$so->public&&!$so->private){ do_action('comment_on_draft',$pid);return new WP_Error('comment_on_draft','Der Beitrag ist noch nicht veröffentlicht.',403); }
        if(post_password_required($pid)){ do_action('comment_on_password_protected',$pid);return new WP_Error('comment_on_password_protected','Der Beitrag ist passwortgeschützt.',403); }
        do_action('pre_comment_on_post',$pid);
        $user=wp_get_current_user();$uid=0;
        if($user->exists()){ $uid=$user->ID;$author=$user->display_name;$email=$user->user_email;$url=$user->user_url; }
        elseif(get_option('comment_registration'))return new WP_Error('not_logged_in','Zum Kommentieren musst du angemeldet sein.',403);
        $author=(string)$author;$email=(string)$email;$url=(string)$url;
        if(get_option('require_name_email',1)&&!$user->exists()){
            if(strlen($email)<6||$author==='')return new WP_Error('require_name_email','<strong>Fehler:</strong> Bitte fülle die Pflichtfelder Name und E-Mail-Adresse aus.',200);
            if(!is_email($email))return new WP_Error('require_valid_email','<strong>Fehler:</strong> Bitte gib eine gültige E-Mail-Adresse ein.',200);
        }
        if($text===null||$text==='')return new WP_Error('require_valid_comment','<strong>Fehler:</strong> Bitte gib einen Kommentar ein.',200);
        $max=wp_get_comment_fields_max_lengths();
        foreach(['comment_author'=>[$author,'Dein Name ist zu lang.'],'comment_author_email'=>[$email,'Deine E-Mail-Adresse ist zu lang.'],'comment_author_url'=>[$url,'Die Website-Adresse ist zu lang.'],'comment_content'=>[$text,'Dein Kommentar ist zu lang.']] as $col=>[$v,$m])
            if(mb_strlen($v)>$max[$col])return new WP_Error($col.'_column_length','<strong>Fehler:</strong> '.$m,200);
        $cd=['comment_post_ID'=>$pid,'comment_author'=>$author,'comment_author_email'=>$email,'comment_author_url'=>$url,'comment_content'=>$text,'comment_type'=>'comment','comment_parent'=>$parent,'user_id'=>$uid,
             'comment_author_IP'=>(string)($_SERVER['REMOTE_ADDR']??''),'comment_agent'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,254)];
        if(($post->rrw_source??'')==='news'){   // CMS-Beiträge: Speicher und Regeln des CMS
            $r=wp_new_comment(['comment_post_ID'=>$pid,'author'=>$author,'comment'=>$text,'comment_parent'=>$parent,'hp'=>(string)($d['hp']??'')],true);
            if(is_wp_error($r))return $r;
            $cd['comment_ID']=(int)($r['id']??0);$cd['comment_approved']=!empty($r['pending'])?'0':'1';
            return new WP_Comment((object)($cd+['comment_date'=>current_time('mysql'),'comment_date_gmt'=>current_time('mysql',1)]));   // nicht über get_comment(): CMS- und Tabellen-IDs können kollidieren
        }
        $cd['comment_date']=current_time('mysql');$cd['comment_date_gmt']=current_time('mysql',1);
        if(!current_user_can('unfiltered_html'))$cd['comment_content']=wp_kses_data((string)apply_filters('pre_comment_content',$cd['comment_content']));
        $ok=wp_allow_comment($cd,true);if(is_wp_error($ok))return $ok;
        $cd['comment_approved']=(string)$ok;
        $id=wp_insert_comment($cd);
        if(!$id)return new WP_Error('comment_save_error','<strong>Fehler:</strong> Der Kommentar konnte nicht gespeichert werden.',500);
        wp_update_comment_count_now((int)$pid);
        do_action('comment_post',$id,$ok,$cd);
        return rrw_wpx_comment($id);
    }
}

/* ───────── Pingbacks und Trackbacks ───────── */
/** Einfacher XML-RPC-Aufruf per HTTP-Post; liefert den Antworttext oder false bei Fehler/Fault. */
function rrw_wpx_xmlrpc(string $url, string $method, array $params) {
    $x='<?xml version="1.0"?><methodCall><methodName>'.esc_html($method).'</methodName><params>';
    foreach($params as $p)$x.='<param><value><string>'.esc_html((string)$p).'</string></value></param>';
    $r=wp_remote_post($url,['timeout'=>5,'headers'=>['Content-Type'=>'text/xml'],'body'=>$x.'</params></methodCall>']);
    if(is_wp_error($r))return false;
    $b=wp_remote_retrieve_body($r);return str_contains($b,'<fault>')?false:$b;
}
/** Zeilen-/Leerzeichen-getrennte Adressliste (Spalten to_ping/pinged) als Array. */
function rrw_wpx_url_list($s): array { return array_values(array_filter(preg_split('/\s+/',trim((string)$s))?:[])); }

if(!function_exists('discover_pingback_server_uri')){
    function discover_pingback_server_uri($url, $deprecated='') {
        $url=(string)$url;$p=parse_url($url);
        if(!$p||empty($p['host'])||!in_array(strtolower($p['scheme']??''),['http','https'],true))return false;
        $h=wp_remote_head($url,['timeout'=>2,'httpversion'=>'1.0']);
        if(is_wp_error($h))return false;
        $hdr=wp_remote_retrieve_header($h,'x-pingback');if($hdr)return (string)$hdr;
        if(preg_match('#(image|audio|video|model)/#i',(string)wp_remote_retrieve_header($h,'content-type')))return false;
        $g=wp_remote_get($url,['timeout'=>2]);if(is_wp_error($g))return false;
        $body=substr(wp_remote_retrieve_body($g),0,153600);
        return preg_match('#<link rel="pingback" href="([^"]+)" ?/?>#i',$body,$m)?esc_url_raw(html_entity_decode($m[1])):false;
    }
}
if(!function_exists('pingback_ping_source_uri')){
    function pingback_ping_source_uri($source_uri) { return (string)wp_http_validate_url((string)$source_uri); }
}
if(!function_exists('xmlrpc_pingback_error')){
    /** Nur den Fehler „bereits registriert“ (Code 48) weiterreichen, alle anderen unterdrücken. */
    function xmlrpc_pingback_error($ixr_error) { return (is_object($ixr_error)&&(int)($ixr_error->code??0)===48)?$ixr_error:''; }
}
if(!function_exists('privacy_ping_filter')){
    function privacy_ping_filter($sites) { return get_option('blog_public')!='0'?$sites:''; }
}
if(!function_exists('pingback')){
    /** Sendet Pingbacks für alle Links des Beitragsinhalts (ohne Selbstverweise und bereits benachrichtigte). */
    function pingback($content, $post) {
        global $wpdb;$post=get_post($post);if(!$post)return;
        if($content===null)$content=$post->post_content;
        $pung=rrw_wpx_url_list($post->pinged);$self=get_permalink($post);$links=[];
        if(preg_match_all('#https?://[^\s"\'<>\]\)]+#i',(string)$content,$m))foreach($m[0] as $l){ $l=rtrim($l,'.,;');if($l!==$self&&!in_array($l,$pung,true)&&url_to_postid($l)!==(int)$post->ID)$links[]=$l; }
        $links=array_unique($links);
        do_action_ref_array('pre_ping',[&$links,&$pung,$post->ID]);
        foreach($links as $to){
            $srv=discover_pingback_server_uri($to);if(!$srv)continue;
            if(function_exists('set_time_limit'))@set_time_limit(60);
            if(rrw_wpx_xmlrpc($srv,'pingback.ping',[$self,$to])!==false&&(int)$post->ID>=RRW_WP_ID_DB_MIN&&rrw_wp_db_ready()){
                $pung[]=$to;$wpdb->update($wpdb->posts,['pinged'=>implode("\n",$pung)],['ID'=>(int)$post->ID]);rrw_wp_post_cache_clear((int)$post->ID);
            }
        }
    }
}
if(!function_exists('trackback')){
    function trackback($trackback_url, $title, $excerpt, $ID) {
        global $wpdb;if(empty($trackback_url))return;
        $r=wp_safe_remote_post($trackback_url,['timeout'=>10,'body'=>['title'=>$title,'url'=>get_permalink($ID),'blog_name'=>get_option('blogname'),'excerpt'=>$excerpt]]);
        if(is_wp_error($r)||!rrw_wp_db_ready()||(int)$ID<RRW_WP_ID_DB_MIN)return;
        $p=get_post((int)$ID);if(!$p)return;
        $wpdb->update($wpdb->posts,['pinged'=>trim($p->pinged."\n".$trackback_url),'to_ping'=>trim(str_replace($trackback_url,'',(string)$p->to_ping))],['ID'=>(int)$ID]);rrw_wp_post_cache_clear((int)$ID);
        return true;
    }
}
if(!function_exists('weblog_ping')){
    /** Aktualisierungs-Ping (weblogUpdates) an einen Ping-Dienst. */
    function weblog_ping($server='', $path='') {
        $server=trim((string)$server);if($server==='')return;
        if(!preg_match('#^https?://#i',$server))$server='http://'.$server;
        if($path!=='')$server=rtrim($server,'/').'/'.ltrim((string)$path,'/');
        $home=trailingslashit(home_url());
        if(rrw_wpx_xmlrpc($server,'weblogUpdates.extendedPing',[get_option('blogname'),$home,get_bloginfo('rss2_url')])===false)rrw_wpx_xmlrpc($server,'weblogUpdates.ping',[get_option('blogname'),$home]);
    }
}
if(!function_exists('generic_ping')){
    function generic_ping($post_id=0) {
        foreach(explode("\n",(string)get_option('ping_sites','')) as $s){ $s=trim($s);if($s!=='')weblog_ping($s); }
        return $post_id;
    }
}
if(!function_exists('do_trackbacks')){
    function do_trackbacks($post) {
        global $wpdb;$post=get_post($post);if(!$post)return;
        $to=rrw_wpx_url_list($post->to_ping);$pinged=rrw_wpx_url_list($post->pinged);
        if(!$to){ if((int)$post->ID>=RRW_WP_ID_DB_MIN&&rrw_wp_db_ready()){ $wpdb->update($wpdb->posts,['to_ping'=>''],['ID'=>(int)$post->ID]);rrw_wp_post_cache_clear((int)$post->ID); } return; }
        $ex=$post->post_excerpt===''?apply_filters('the_content',$post->post_content,$post->ID):apply_filters('the_excerpt',$post->post_excerpt);
        $ex=wp_html_excerpt(str_replace(']]>',']]&gt;',(string)$ex),252,'&#8230;');
        $title=strip_tags((string)apply_filters('the_title',$post->post_title,$post->ID));
        foreach($to as $u){
            if(!in_array($u,$pinged,true)){ trackback($u,$title,$ex,$post->ID);$pinged[]=$u; }
            elseif((int)$post->ID>=RRW_WP_ID_DB_MIN&&rrw_wp_db_ready()){ $wpdb->update($wpdb->posts,['to_ping'=>trim(str_replace($u,'',(string)$post->to_ping))],['ID'=>(int)$post->ID]);rrw_wp_post_cache_clear((int)$post->ID); }
        }
    }
}
if(!function_exists('do_all_pingbacks')){
    function do_all_pingbacks() { foreach(rrw_wpx_comment_rows("SELECT post_id FROM {$GLOBALS['wpdb']->postmeta} WHERE meta_key = '_pingme'") as $r){ delete_post_meta((int)$r['post_id'],'_pingme');pingback(null,(int)$r['post_id']); } }
}
if(!function_exists('do_all_enclosures')){
    function do_all_enclosures() {
        foreach(rrw_wpx_comment_rows("SELECT post_id FROM {$GLOBALS['wpdb']->postmeta} WHERE meta_key = '_encloseme'") as $r){ delete_post_meta((int)$r['post_id'],'_encloseme');if(function_exists('do_enclose'))do_enclose(null,(int)$r['post_id']); }
    }
}
if(!function_exists('do_all_trackbacks')){
    function do_all_trackbacks() { foreach(rrw_wpx_comment_rows("SELECT ID FROM {$GLOBALS['wpdb']->posts} WHERE to_ping <> '' AND post_status = 'publish'") as $r)do_trackbacks((int)$r['ID']); }
}
if(!function_exists('do_all_pings')){
    function do_all_pings() { do_all_pingbacks();do_all_enclosures();do_all_trackbacks();generic_ping(); }
}

/* ───────── Datenschutz (Export/Löschen) ───────── */
/** Kommentare mit E-Mail-Adresse aus wp_comments, seitenweise (500). */
function rrw_wpx_comments_by_email(string $email, int $page): array {
    global $wpdb;$n=500;
    return rrw_wp_db_ready()?(array)$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->comments} WHERE comment_author_email = %s ORDER BY comment_ID ASC LIMIT %d OFFSET %d",$email,$n,($page-1)*$n)):[];
}
if(!function_exists('wp_register_comment_personal_data_exporter')){
    function wp_register_comment_personal_data_exporter($exporters) { $exporters['wordpress-comments']=['exporter_friendly_name'=>'WordPress-Kommentare','callback'=>'wp_comments_personal_data_exporter'];return $exporters; }
}
if(!function_exists('wp_comments_personal_data_exporter')){
    function wp_comments_personal_data_exporter($email_address, $page=1) {
        $email=trim((string)$email_address);$page=max(1,(int)$page);if($email==='')return ['data'=>[],'done'=>true];
        $labels=['comment_author'=>'Kommentarautor','comment_author_email'=>'E-Mail des Kommentarautors','comment_author_url'=>'Website des Kommentarautors','comment_author_IP'=>'IP-Adresse des Kommentarautors','comment_agent'=>'Browser des Kommentarautors','comment_date'=>'Kommentardatum','comment_content'=>'Kommentar','comment_link'=>'Adresse des Kommentars'];
        $rows=rrw_wpx_comments_by_email($email,$page);$data=[];
        foreach($rows as $c){
            $items=[];foreach($labels as $k=>$l){ $v=$k==='comment_link'?get_comment_link(new WP_Comment($c)):($c->$k??'');if($v!=='')$items[]=['name'=>$l,'value'=>(string)$v]; }
            $data[]=['group_id'=>'comments','group_label'=>'Kommentare','group_description'=>'Mit dieser E-Mail-Adresse abgegebene Kommentare.','item_id'=>'comment-'.$c->comment_ID,'data'=>$items];
        }
        return ['data'=>$data,'done'=>count($rows)<500];
    }
}
if(!function_exists('wp_register_comment_personal_data_eraser')){
    function wp_register_comment_personal_data_eraser($erasers) { $erasers['wordpress-comments']=['eraser_friendly_name'=>'WordPress-Kommentare','callback'=>'wp_comments_personal_data_eraser'];return $erasers; }
}
if(!function_exists('wp_comments_personal_data_eraser')){
    /** Anonymisiert die Kommentare einer E-Mail-Adresse (Name, Adresse, IP, Browser werden ersetzt). */
    function wp_comments_personal_data_eraser($email_address, $page=1) {
        global $wpdb;$email=trim((string)$email_address);$page=max(1,(int)$page);
        $res=['items_removed'=>false,'items_retained'=>false,'messages'=>[],'done'=>true];if($email==='')return $res;
        $rows=rrw_wpx_comments_by_email($email,$page);
        foreach($rows as $c){
            $anon=['comment_agent'=>'','comment_author'=>wp_privacy_anonymize_data('text'),'comment_author_email'=>wp_privacy_anonymize_data('email'),'comment_author_IP'=>wp_privacy_anonymize_data('ip'),'comment_author_url'=>wp_privacy_anonymize_data('url'),'user_id'=>0];
            $ok=apply_filters('wp_anonymize_comment',true,$c,$anon);
            if($ok!==true){ $res['messages'][]=is_string($ok)?esc_html($ok):'Der Kommentar '.(int)$c->comment_ID.' konnte nicht entfernt werden.';$res['items_retained']=true;continue; }
            $wpdb->update($wpdb->comments,$anon,['comment_ID'=>(int)$c->comment_ID]);clean_comment_cache((int)$c->comment_ID);$res['items_removed']=true;
            do_action('anonymize_comment',$c,$anon);
        }
        $res['done']=count($rows)<500;return $res;
    }
}

<?php
// Ergänzende WordPress-Funktionen (Version 7.1, Teil Kommentare): Anmerkungen (Notes) mit @-Erwähnungen, kses-Hilfen, Pings je Umgebung,
// Datenschutz-Bereinigung, Datenschutzseite. Eigenständig; nur Definitionen beim Laden, DB/Mails erst beim Aufruf.

/* ───────── Anmerkungen (Kommentartyp „note“) ───────── */
// Erwähnung: <span class="note-mention" data-user-id="5">@Name</span>; Inline-Markierung im Beitrag: <mark|span class="note-marker" data-note-id="…">…</mark>
if(!function_exists('wp_get_note_mentioned_user_ids')){ function wp_get_note_mentioned_user_ids($content) {   // eindeutige Benutzer-IDs aller Erwähnungen
    $ids=[];
    if(preg_match_all('/<span\b[^>]*\bclass\s*=\s*(["\'])[^"\']*\bnote-mention\b[^"\']*\1[^>]*>/i',(string)$content,$m)){
        foreach($m[0] as $tag)if(preg_match('/\bdata-user-id\s*=\s*(["\'])(\d+)\1/i',$tag,$u)&&(int)$u[2]>0)$ids[(int)$u[2]]=true;
    }
    return array_keys($ids);
} }
if(!function_exists('wp_strip_inline_note_markers')){ function wp_strip_inline_note_markers($content) {   // entfernt Anmerkungs-Markierungen, behält den markierten Text
    return preg_replace_callback('/<(mark|span)\b([^>]*)>(.*?)<\/\1>/is',static function($m){
        return (preg_match('/\bclass\s*=\s*(["\'])[^"\']*\bnote-marker\b[^"\']*\1/i',$m[2])||preg_match('/\bdata-note-id\s*=/i',$m[2]))?wp_strip_inline_note_markers($m[3]):$m[0];
    },(string)$content);
} }
if(!function_exists('wp_create_initial_comment_meta')){ function wp_create_initial_comment_meta($comment_id, $comment=null) {   // Anfangs-Metadaten: Anmerkungen starten „offen“
    $c=is_object($comment)?$comment:get_comment((int)$comment_id);
    if(!$c||(string)($c->comment_type??'')!=='note')return false;
    if(get_comment_meta((int)$comment_id,'_wp_note_status',true)==='')add_comment_meta((int)$comment_id,'_wp_note_status','open',true);
    return true;
} }
if(!function_exists('wp_send_note_notification')){ function wp_send_note_notification($comment_id) {   // Mail an den Beitragsautor über eine neue Anmerkung
    $c=get_comment((int)$comment_id);if(!$c||(string)$c->comment_type!=='note')return false;
    $post=get_post((int)$c->comment_post_ID);if(!$post)return false;
    $author=get_userdata((int)$post->post_author);if(!$author||!is_email($author->user_email)||(int)$author->ID===(int)$c->user_id)return false;
    if(!apply_filters('wp_send_note_notification',true,(int)$comment_id,$c))return false;
    $subj=sprintf('[%1$s] Neue Anmerkung zu „%2$s“',wp_specialchars_decode(get_option('blogname'),ENT_QUOTES),$post->post_title);
    $text=sprintf("Neue Anmerkung von %1\$s zu „%2\$s“:\n\n%3\$s\n\n%4\$s",$c->comment_author,$post->post_title,wp_strip_all_tags((string)$c->comment_content),get_permalink($post));
    return (bool)wp_mail($author->user_email,$subj,$text);
} }
if(!function_exists('wp_notify_note_mentions')){ function wp_notify_note_mentions($comment_id) {   // Mail an erwähnte Benutzer; liefert die Zahl versandter Mails
    $c=get_comment((int)$comment_id);if(!$c||(string)$c->comment_type!=='note')return 0;
    $post=get_post((int)$c->comment_post_ID);$sent=0;
    foreach(wp_get_note_mentioned_user_ids((string)$c->comment_content) as $uid){
        $u=get_userdata($uid);if(!$u||!is_email($u->user_email)||$uid===(int)$c->user_id)continue;
        if(!apply_filters('wp_notify_note_mention',true,$uid,(int)$comment_id))continue;
        $subj=sprintf('[%1$s] Sie wurden in einer Anmerkung erwähnt',wp_specialchars_decode(get_option('blogname'),ENT_QUOTES));
        $text=sprintf("%1\$s hat Sie in einer Anmerkung erwähnt%2\$s:\n\n%3\$s",$c->comment_author,$post?' („'.$post->post_title.'“)':'',wp_strip_all_tags((string)$c->comment_content));
        if(wp_mail($u->user_email,$subj,$text))$sent++;
    }
    return $sent;
} }
if(!function_exists('wp_new_comment_via_rest_notify_postauthor')){ function wp_new_comment_via_rest_notify_postauthor($comment) {   // nach Anlegen per REST: Anmerkung -> Note-Mails, sonst übliche Benachrichtigung
    $id=is_object($comment)?(int)($comment->comment_ID??0):(int)$comment;if($id<=0)return false;
    $c=is_object($comment)?$comment:get_comment($id);if(!$c)return false;
    if((string)$c->comment_type==='note'){ wp_send_note_notification($id);wp_notify_note_mentions($id);return true; }
    if(function_exists('wp_new_comment_notify_postauthor')&&(int)$c->comment_approved===1)return (bool)wp_new_comment_notify_postauthor($id);
    return false;
} }

/* ───────── kses-Hilfen für Erwähnungen ───────── */
if(!function_exists('_wp_kses_allow_note_mention_span')){ function _wp_kses_allow_note_mention_span($tags, $context='') {   // Filter für wp_kses_allowed_html: span mit class/data-user-id erlauben
    if(!is_array($tags)||($context!=='post'&&$context!=='data'&&$context!=='note'))return $tags;
    $tags['span']=array_merge(is_array($tags['span']??null)?$tags['span']:[],['class'=>true,'data-user-id'=>true]);return $tags;
} }
if(!function_exists('_wp_kses_sanitize_note_mention_classes')){ function _wp_kses_sanitize_note_mention_classes($content) {   // Erwähnungs-Spans: nur Klasse note-mention, ID numerisch
    return preg_replace_callback('/<span\b([^>]*)>/i',static function($m){
        if(!preg_match('/\bclass\s*=\s*(["\'])([^"\']*)\1/i',$m[1],$c)||!preg_match('/(?:^|\s)note-mention(?:\s|$)/',$c[2]))return $m[0];
        $id=preg_match('/\bdata-user-id\s*=\s*(["\'])(\d+)\1/i',$m[1],$u)?(int)$u[2]:0;
        return '<span class="note-mention"'.($id>0?' data-user-id="'.$id.'"':'').'>';
    },(string)$content);
} }

/* ───────── Pings je Umgebung ───────── */
if(!function_exists('wp_should_disable_pings_for_environment')){ function wp_should_disable_pings_for_environment() {   // außerhalb der Produktion keine ausgehenden Pings
    return (bool)apply_filters('wp_should_disable_pings_for_environment',wp_get_environment_type()!=='production');
} }
if(!function_exists('wp_maybe_disable_outgoing_pings_for_environment')){ function wp_maybe_disable_outgoing_pings_for_environment() {
    if(!wp_should_disable_pings_for_environment())return false;
    add_filter('pre_option_default_ping_status',static fn()=>'closed');add_filter('pre_option_default_pingback_flag',static fn()=>0);return true;
} }
if(!function_exists('wp_maybe_disable_trackback_for_environment')){ function wp_maybe_disable_trackback_for_environment() {
    if(!wp_should_disable_pings_for_environment())return false;
    add_filter('pings_open','__return_false',99);return true;
} }
if(!function_exists('wp_maybe_disable_xmlrpc_pingback_for_environment')){ function wp_maybe_disable_xmlrpc_pingback_for_environment() {
    if(!wp_should_disable_pings_for_environment())return false;
    add_filter('xmlrpc_methods',static function($m){ unset($m['pingback.ping'],$m['pingback.extensions.getPingbacks']);return $m; });return true;
} }

/* ───────── Datenschutz ───────── */
if(!function_exists('wp_schedule_personal_data_cleanup_requests')){ function wp_schedule_personal_data_cleanup_requests() {   // täglich alte Anfragen bereinigen
    if(!wp_next_scheduled('wp_privacy_personal_data_cleanup_requests'))return wp_schedule_event(time(),'daily','wp_privacy_personal_data_cleanup_requests');
    return true;
} }
if(!function_exists('wp_privacy_personal_data_cleanup_requests')){ function wp_privacy_personal_data_cleanup_requests() {
    if(function_exists('_wp_personal_data_cleanup_requests'))_wp_personal_data_cleanup_requests();
} }
if(!function_exists('_reset_privacy_policy_page_for_post')){ function _reset_privacy_policy_page_for_post($post_id) {   // Datenschutzseite gelöscht/in den Papierkorb: Zuordnung zurücksetzen
    $post=get_post($post_id);
    if(!$post||$post->post_type!=='page'||(int)get_option('wp_page_for_privacy_policy')!==(int)$post->ID)return false;
    return update_option('wp_page_for_privacy_policy',0);
} }

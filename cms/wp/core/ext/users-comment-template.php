<?php
// Ergänzung (Benutzer/Kommentare): Vorlagen-Funktionen von wp-includes/comment-template.php (Autor-Daten, Auszug, Trackback, Antwort-Links, Formularfelder).

if(!function_exists('_get_comment_reply_id')){
    function _get_comment_reply_id() { return isset($_GET['replytocom'])&&is_numeric($_GET['replytocom'])?(int)$_GET['replytocom']:0; }
}
if(!function_exists('comment_author_email')){
    function comment_author_email($comment_id=0) { $c=get_comment($comment_id?:null);echo apply_filters('author_email',get_comment_author_email($comment_id),$c?$c->comment_ID:0); }
}
if(!function_exists('get_comment_author_email_link')){
    function get_comment_author_email_link($linktext='', $before='', $after='', $comment=null) {
        $c=get_comment($comment);$email=apply_filters('comment_email',$c?$c->comment_author_email:'',$c);
        if(!empty($email)&&$email!=='@'){ $d=$linktext!==''?$linktext:$email;return $before.sprintf('<a href="%1$s">%2$s</a>',esc_url('mailto:'.$email),esc_html($d)).$after; }
        return '';
    }
}
if(!function_exists('comment_author_email_link')){
    function comment_author_email_link($linktext='', $before='', $after='', $comment=null) { $l=get_comment_author_email_link($linktext,$before,$after,$comment);if($l)echo $l; }
}
if(!function_exists('get_comment_author_IP')){
    function get_comment_author_IP($comment_id=0) { $c=get_comment($comment_id?:null);return apply_filters('get_comment_author_IP',$c?$c->comment_author_IP:'',$c?$c->comment_ID:0,$c); }
}
if(!function_exists('comment_author_IP')){ function comment_author_IP($comment_id=0) { echo esc_html(get_comment_author_IP($comment_id)); } }
if(!function_exists('comment_author_url')){
    function comment_author_url($comment_id=0) { $c=get_comment($comment_id?:null);$u=$c&&$c->comment_author_url!=='http://'?esc_url($c->comment_author_url,['http','https']):'';echo apply_filters('comment_url',$u,$c?$c->comment_ID:0); }
}
if(!function_exists('get_comment_author_url_link')){
    function get_comment_author_url_link($linktext='', $before='', $after='', $comment=0) {
        $u=$comment===0||$comment===null?get_comment_author_url():get_comment_author_url($comment);
        $u=($u==='http://')?'':esc_url($u,['http','https']);
        $d=$linktext!==''?$linktext:$u;$d=str_replace(['http://www.','http://'],'',$d);if(str_ends_with($d,'/'))$d=substr($d,0,-1);
        return apply_filters('get_comment_author_url_link',$before.'<a href="'.$u.'" rel="external">'.esc_html($d).'</a>'.$after);
    }
}
if(!function_exists('comment_author_url_link')){
    function comment_author_url_link($linktext='', $before='', $after='', $comment=0) { echo get_comment_author_url_link($linktext,$before,$after,$comment); }
}
if(!function_exists('get_comment_excerpt')){
    function get_comment_excerpt($comment_id=0) {
        $c=get_comment($comment_id?:null);if(!$c)return '';
        $t=!post_password_required($c->comment_post_ID)?strip_tags(str_replace(["\n","\r"],' ',$c->comment_content)):'Passwortgeschützt';
        $x=wp_trim_words($t,(int)apply_filters('comment_excerpt_length',20),'&hellip;');
        return apply_filters('get_comment_excerpt',$x,$c->comment_ID,$c);
    }
}
if(!function_exists('comment_excerpt')){
    function comment_excerpt($comment_id=0) { $c=get_comment($comment_id?:null);echo apply_filters('comment_excerpt',get_comment_excerpt($comment_id),$c?$c->comment_ID:0); }
}
if(!function_exists('get_comments_number_text')){
    function get_comments_number_text($zero=false, $one=false, $more=false, $post=0) {
        $n=get_comments_number($post);
        if($n>1)$out=$more===false?number_format_i18n($n).' Kommentare':str_replace('%',number_format_i18n($n),$more);
        elseif($n==0)$out=$zero===false?'Keine Kommentare':$zero;
        else $out=$one===false?'1 Kommentar':$one;
        return apply_filters('comments_number',$out,$n);
    }
}
if(!function_exists('get_trackback_url')){
    function get_trackback_url() {
        $id=in_the_loop()||!function_exists('get_queried_object_id')?(int)(get_post()->ID??0):(int)get_queried_object_id();
        $u=get_option('permalink_structure')?trailingslashit(get_permalink($id)).user_trailingslashit('trackback','single_trackback'):home_url('/wp-trackback.php?p='.$id);
        return apply_filters('trackback_url',$u);
    }
}
if(!function_exists('trackback_url')){
    function trackback_url($deprecated_echo=true) { $u=get_trackback_url();if($deprecated_echo)echo $u;else return $u; }
}
if(!function_exists('trackback_rdf')){
    /** RDF-Block für Trackback-Erkennung (als Kommentar ausgegeben; nicht für den W3C-Validator). */
    function trackback_rdf($deprecated='') {
        if(isset($_SERVER['HTTP_USER_AGENT'])&&stripos($_SERVER['HTTP_USER_AGENT'],'W3C_Validator')!==false)return;
        echo '<!--'."\n".'<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:trackback="http://madskills.com/public/xml/rss/module/trackback/">'
            .'<rdf:Description rdf:about="'.esc_url(get_permalink()).'" dc:identifier="'.esc_url(get_permalink()).'" dc:title="'.str_replace('--','&#x2d;&#x2d;',esc_attr(strip_tags(get_the_title()))).'" trackback:ping="'.esc_url(get_trackback_url()).'" />'
            .'</rdf:RDF>'."\n".'-->'."\n";
    }
}
if(!function_exists('wp_comment_form_unfiltered_html_nonce')){
    function wp_comment_form_unfiltered_html_nonce() {
        $p=get_post();$pid=$p?$p->ID:0;
        if(current_user_can('unfiltered_html')){
            wp_nonce_field('unfiltered-html-comment_'.$pid,'_wp_unfiltered_html_comment_disabled',false);
            echo "<script>(function(){if(window===window.parent){document.getElementById('_wp_unfiltered_html_comment_disabled').name='_wp_unfiltered_html_comment';}})();</script>\n";
        }
    }
}
if(!function_exists('get_post_reply_link')){
    function get_post_reply_link($args=[], $post=null) {
        $a=wp_parse_args($args,['add_below'=>'post','respond_id'=>'respond','reply_text'=>'Kommentieren','login_text'=>'Zum Kommentieren anmelden','before'=>'','after'=>'']);
        $post=get_post($post);if(!$post||!comments_open($post->ID))return false;
        if(get_option('comment_registration')&&!is_user_logged_in())$link=sprintf('<a rel="nofollow" class="comment-reply-login" href="%s">%s</a>',esc_url(wp_login_url(get_permalink())),$a['login_text']);
        else{
            $onclick=sprintf('return addComment.moveForm( "%1$s-%2$s", "0", "%3$s", "%2$s" )',$a['add_below'],$post->ID,$a['respond_id']);
            $link=sprintf("<a rel='nofollow' class='comment-reply-link' href='%s' onclick='%s'>%s</a>",get_permalink($post->ID).'#'.$a['respond_id'],$onclick,$a['reply_text']);
        }
        return apply_filters('post_comments_link',$a['before'].$link.$a['after'],$post);
    }
}
if(!function_exists('post_reply_link')){
    function post_reply_link($args=[], $post=null) { echo get_post_reply_link($args,$post); }
}
if(!function_exists('get_comment_id_fields')){
    function get_comment_id_fields($post_id=0) {
        if(empty($post_id))$post_id=(int)(get_post()->ID??0);
        $reply=_get_comment_reply_id();
        $r="<input type='hidden' name='comment_post_ID' value='".(int)$post_id."' id='comment_post_ID' />\n<input type='hidden' name='comment_parent' id='comment_parent' value='".(int)$reply."' />\n";
        return apply_filters('comment_id_fields',$r,$post_id,$reply);
    }
}
if(!function_exists('comment_form_title')){
    function comment_form_title($no_reply_text=false, $reply_text=false, $link_to_parent=true) {
        global $comment;
        if($no_reply_text===false)$no_reply_text='Hinterlasse eine Antwort';
        if($reply_text===false)$reply_text='Antworte auf %s';
        $id=_get_comment_reply_id();
        if($id===0){ echo $no_reply_text;return; }
        $comment=get_comment($id);if(!$comment){ echo $no_reply_text;return; }
        $author=$link_to_parent?'<a href="#comment-'.get_comment_ID().'">'.get_comment_author($comment).'</a>':get_comment_author($comment);
        printf($reply_text,$author);
    }
}

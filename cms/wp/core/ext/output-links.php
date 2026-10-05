<?php
// Ergänzende Link-Funktionen (Bereich Ausgabe): Feed-Adressen, Bearbeiten-Links für Begriffe/Links, Nachbarbeiträge (rel), Kommentar-Navigation,
// Kurzlinks, Avatare und interne Hosts. Das CMS kennt nur einen Feed (rss.xml); Archiv-Feeds folgen dem WordPress-Muster „…/feed/“.

if(!function_exists('rrw_ext_feed_suffix')){
    /** Pfadende „feed/“ bzw. „feed/atom/“ für ein Archiv. */
    function rrw_ext_feed_suffix($feed) { $feed=(string)$feed;return ($feed===''||$feed===get_default_feed())?'feed/':'feed/'.$feed.'/'; }
}
if(!function_exists('permalink_anchor')){
    function permalink_anchor($mode='id') {
        $post=get_post();if(!$post)return;
        echo strtolower((string)$mode)==='title'?'<a id="'.sanitize_title($post->post_title).'-'.$post->ID.'"></a>':'<a id="post-'.$post->ID.'"></a>';
    }
}
if(!function_exists('wp_force_plain_post_permalink')){
    /** true, wenn für den Beitrag (noch) kein „schöner“ Permalink gilt (Entwurf, ausstehend, Vorschau). */
    function wp_force_plain_post_permalink($post=null, $sample=null) {
        if($sample===null&&is_object($post)&&isset($post->filter)&&$post->filter==='sample')$sample=true; else { $post=get_post($post);$sample=$sample??false; }
        if(!$post)return true;
        $st=get_post_status_object(get_post_status($post));if(!$st)return true;
        if(is_post_status_viewable($post->post_status)||(!empty($st->private)&&current_user_can('read_post',$post->ID))||(!empty($st->protected)&&$sample))return false;
        return true;
    }
}
if(!function_exists('_get_page_link')){
    function _get_page_link($post=false, $leavename=false, $sample=false) {
        $post=get_post($post);if(!$post)return false;
        $link=((int)get_option('page_on_front')===(int)$post->ID&&get_option('show_on_front')==='page')?home_url('/'):home_url('/'.get_page_uri($post).'/');
        return apply_filters('_get_page_link',$link,$post->ID);
    }
}

/* ───────── Feed-Adressen ───────── */
if(!function_exists('the_feed_link')){ function the_feed_link($anchor, $feed='') { echo apply_filters('the_feed_link','<a href="'.esc_url(get_feed_link($feed)).'">'.$anchor.'</a>',$feed); } }
if(!function_exists('post_comments_feed_link')){
    function post_comments_feed_link($link_text='', $post=0, $feed='') {
        $url=get_post_comments_feed_link($post,$feed);if(empty($link_text))$link_text='Kommentar-Feed';
        echo apply_filters('post_comments_feed_link_html','<a href="'.esc_url($url).'">'.$link_text.'</a>',$post,$feed);
    }
}
if(!function_exists('get_author_feed_link')){
    function get_author_feed_link($author_id, $feed='') {
        $author_id=(int)$author_id;if(!get_userdata($author_id))return '';
        return apply_filters('author_feed_link',trailingslashit(get_author_posts_url($author_id)).rrw_ext_feed_suffix($feed),$feed);
    }
}
if(!function_exists('get_term_feed_link')){
    function get_term_feed_link($term_id, $taxonomy='category', $feed='') {
        $term=get_term((int)$term_id,$taxonomy);if(empty($term)||is_wp_error($term))return false;
        $link=get_term_link($term);if(is_wp_error($link)||!$link)return false;
        $link=trailingslashit($link).rrw_ext_feed_suffix($feed);
        $f=$term->taxonomy==='category'?'category_feed_link':($term->taxonomy==='post_tag'?'tag_feed_link':'taxonomy_feed_link');
        return apply_filters('term_feed_link',apply_filters($f,$link,$feed),$feed,$term->taxonomy);
    }
}
if(!function_exists('get_category_feed_link')){ function get_category_feed_link($cat, $feed='') { return get_term_feed_link($cat,'category',$feed); } }
if(!function_exists('get_tag_feed_link')){ function get_tag_feed_link($tag, $feed='') { return get_term_feed_link($tag,'post_tag',$feed); } }
if(!function_exists('get_search_feed_link')){
    function get_search_feed_link($search_query='', $feed='') {
        if(empty($feed))$feed=get_default_feed();
        return apply_filters('search_feed_link',add_query_arg('feed',$feed,get_search_link($search_query)),$feed,'posts');
    }
}
if(!function_exists('get_search_comments_feed_link')){
    function get_search_comments_feed_link($search_query='', $feed='') {
        if(empty($feed))$feed=get_default_feed();
        return apply_filters('search_feed_link',add_query_arg('feed','comments-'.$feed,get_search_link($search_query)),$feed,'comments');
    }
}
if(!function_exists('get_post_type_archive_feed_link')){
    function get_post_type_archive_feed_link($post_type, $feed='') {
        if($post_type==='post')return get_feed_link($feed);
        $link=get_post_type_archive_link($post_type);if(!$link)return false;
        return apply_filters('post_type_archive_feed_link',trailingslashit($link).rrw_ext_feed_suffix($feed),$feed);
    }
}

/* ───────── Bearbeiten-Links ───────── */
if(!function_exists('rrw_ext_can_edit_term')){
    /** Darf der aktuelle Benutzer den Begriff bearbeiten? (Meta-Recht „edit_term“ oder Recht der Taxonomie) */
    function rrw_ext_can_edit_term($term) { $tx=get_taxonomy($term->taxonomy);return current_user_can('edit_term',$term->term_id)||($tx&&current_user_can($tx->cap->edit_terms??'manage_categories')); }
}
if(!function_exists('get_edit_term_link')){
    function get_edit_term_link($term, $taxonomy='', $object_type='') {
        $term=get_term($term,$taxonomy);if(!$term||is_wp_error($term))return null;
        if(!get_taxonomy($term->taxonomy)||!rrw_ext_can_edit_term($term))return null;
        $args=['taxonomy'=>$term->taxonomy,'tag_ID'=>$term->term_id];if($object_type)$args['post_type']=$object_type;
        return apply_filters('get_edit_term_link',add_query_arg($args,admin_url('term.php')),$term->term_id,$term->taxonomy,$object_type);
    }
}
if(!function_exists('edit_term_link')){
    function edit_term_link($link='', $before='', $after='', $term=null, $display=true) {
        if($term===null)$term=get_queried_object();
        if(!$term||empty($term->taxonomy)||!rrw_ext_can_edit_term($term))return;
        if(empty($link))$link='Bearbeiten';
        $out=$before.apply_filters('edit_term_link','<a href="'.esc_url((string)get_edit_term_link($term->term_id,$term->taxonomy)).'">'.$link.'</a>',$term->term_id).$after;
        if($display)echo $out;else return $out;
    }
}
if(!function_exists('get_edit_tag_link')){ function get_edit_tag_link($tag, $taxonomy='post_tag') { return apply_filters('get_edit_tag_link',get_edit_term_link($tag,$taxonomy)); } }
if(!function_exists('edit_tag_link')){ function edit_tag_link($link='', $before='', $after='', $tag=null) { $l=edit_term_link($link,'','',$tag,false);echo $before.apply_filters('edit_tag_link',$l).$after; } }
if(!function_exists('get_edit_bookmark_link')){
    /** Blogroll (Links) gibt es im CMS nicht: ohne get_bookmark() immer null. */
    function get_edit_bookmark_link($link=0) {
        if(!function_exists('get_bookmark')||!current_user_can('manage_links'))return null;
        $l=get_bookmark($link);if(!$l)return null;
        return apply_filters('get_edit_bookmark_link',admin_url('link.php?action=edit&link='.(int)$l->link_id),$l->link_id);
    }
}
if(!function_exists('edit_bookmark_link')){
    function edit_bookmark_link($link='', $before='', $after='', $bookmark=null) {
        $url=get_edit_bookmark_link($bookmark);if(!$url)return;
        if(empty($link))$link='Link bearbeiten';
        echo $before.apply_filters('edit_bookmark_link','<a href="'.esc_url($url).'">'.$link.'</a>',$bookmark->link_id??0).$after;
    }
}

/* ───────── Nachbarbeiträge ───────── */
if(!function_exists('get_adjacent_post_rel_link')){
    function get_adjacent_post_rel_link($title='%title', $in_same_term=false, $excluded_terms='', $previous=true, $taxonomy='category') {
        $post=get_adjacent_post($in_same_term,$excluded_terms,$previous,$taxonomy);if(empty($post))return;
        $pt=trim(strip_tags((string)$post->post_title));if($pt==='')$pt=$previous?'Vorheriger Beitrag':'Nächster Beitrag';
        $title=str_replace(['%title','%date'],[$pt,mysql2date(get_option('date_format','d.m.Y'),$post->post_date)],$title);
        $link=($previous?"<link rel='prev' title='":"<link rel='next' title='").esc_attr($title)."' href='".get_permalink($post)."' />\n";
        return apply_filters(($previous?'previous':'next').'_post_rel_link',$link);
    }
}
if(!function_exists('adjacent_posts_rel_link')){
    function adjacent_posts_rel_link($title='%title', $in_same_term=false, $excluded_terms='', $taxonomy='category') {
        echo get_adjacent_post_rel_link($title,$in_same_term,$excluded_terms,true,$taxonomy);
        echo get_adjacent_post_rel_link($title,$in_same_term,$excluded_terms,false,$taxonomy);
    }
}
if(!function_exists('adjacent_posts_rel_link_wp_head')){ function adjacent_posts_rel_link_wp_head() { if(!is_single()||is_attachment())return;adjacent_posts_rel_link(); } }
if(!function_exists('next_post_rel_link')){ function next_post_rel_link($title='%title', $in_same_term=false, $excluded_terms='', $taxonomy='category') { echo get_adjacent_post_rel_link($title,$in_same_term,$excluded_terms,false,$taxonomy); } }
if(!function_exists('prev_post_rel_link')){ function prev_post_rel_link($title='%title', $in_same_term=false, $excluded_terms='', $taxonomy='category') { echo get_adjacent_post_rel_link($title,$in_same_term,$excluded_terms,true,$taxonomy); } }
if(!function_exists('get_boundary_post')){
    /** Erster ($start) bzw. letzter Beitrag der Reihenfolge nach Datum, gleicher Typ; optional im selben Begriff. Liefert ein Array mit einem Beitrag. */
    function get_boundary_post($in_same_term=false, $excluded_terms='', $start=true, $taxonomy='category') {
        $post=get_post();if(!$post||!is_single()||is_attachment())return null;
        $args=['post_type'=>$post->post_type,'numberposts'=>1,'orderby'=>'date','order'=>$start?'ASC':'DESC','no_found_rows'=>true,'ignore_sticky_posts'=>true,'suppress_filters'=>true];
        if($in_same_term){ $ids=wp_get_object_terms($post->ID,$taxonomy,['fields'=>'ids']);if(is_array($ids)&&$ids)$args['tax_query']=[['taxonomy'=>$taxonomy,'field'=>'term_id','terms'=>$ids]]; }
        return get_posts($args);
    }
}
if(!function_exists('get_previous_post_link')){ function get_previous_post_link($format='&laquo; %link', $link='%title', $in_same_term=false, $excluded_terms='', $taxonomy='category') { return get_adjacent_post_link($format,$link,$in_same_term,$excluded_terms,true,$taxonomy); } }
if(!function_exists('get_next_post_link')){ function get_next_post_link($format='%link &raquo;', $link='%title', $in_same_term=false, $excluded_terms='', $taxonomy='category') { return get_adjacent_post_link($format,$link,$in_same_term,$excluded_terms,false,$taxonomy); } }
if(!function_exists('adjacent_post_link')){ function adjacent_post_link($format, $link, $in_same_term=false, $excluded_terms='', $previous=true, $taxonomy='category') { echo get_adjacent_post_link($format,$link,$in_same_term,$excluded_terms,$previous,$taxonomy); } }
if(!function_exists('get_posts_nav_link')){
    function get_posts_nav_link($args=[]) {
        global $wp_query;$return='';
        if(!is_singular()&&$wp_query){
            $a=wp_parse_args($args,['sep'=>' &#8212; ','prelabel'=>'&laquo; Vorherige Seite','nxtlabel'=>'Nächste Seite &raquo;']);
            $max=(int)$wp_query->max_num_pages;$paged=(int)get_query_var('paged');
            if($paged<2||$paged>=$max)$a['sep']='';
            if($max>1)$return=get_previous_posts_link($a['prelabel']).preg_replace('/&([^#])(?![a-z]{1,8};)/i','&#038;$1',$a['sep']).get_next_posts_link($a['nxtlabel']);
        }
        return $return;
    }
}
if(!function_exists('_navigation_markup')){
    function _navigation_markup($links, $css_class='posts-navigation', $screen_reader_text='', $aria_label='') {
        if(empty($screen_reader_text))$screen_reader_text='Beitrags-Navigation';
        if(empty($aria_label))$aria_label=$screen_reader_text;
        $tpl=apply_filters('navigation_markup_template','<nav class="navigation %1$s" aria-label="%4$s"><h2 class="screen-reader-text">%2$s</h2><div class="nav-links">%3$s</div></nav>',$css_class);
        return sprintf($tpl,sanitize_html_class($css_class),esc_html($screen_reader_text),$links,esc_attr($aria_label));
    }
}

/* ───────── Kommentar-Navigation ───────── */
if(!function_exists('rrw_ext_comments_page_link')){ function rrw_ext_comments_page_link($n) { $u=get_permalink();if($n>1)$u=trailingslashit($u).'comment-page-'.(int)$n.'/';return $u.'#comments'; } }
if(!function_exists('get_next_comments_link')){
    function get_next_comments_link($label='', $max_page=0) {
        global $wp_query;if(!is_singular())return;
        $page=(int)get_query_var('cpage')?:1;$next=$page+1;
        if(empty($max_page))$max_page=(int)($wp_query->max_num_comment_pages??0);
        if(empty($max_page))$max_page=get_comment_pages_count();
        if($next>$max_page)return;
        if(empty($label))$label='Neuere Kommentare &raquo;';
        return sprintf('<a href="%1$s" %2$s>%3$s</a>',esc_url(rrw_ext_comments_page_link($next)),apply_filters('next_comments_link_attributes',''),preg_replace('/&([^#])(?![a-z]{1,8};)/i','&#038;$1',$label));
    }
}
if(!function_exists('get_previous_comments_link')){
    function get_previous_comments_link($label='') {
        if(!is_singular())return;
        $page=(int)get_query_var('cpage');if($page<=1)return;
        if(empty($label))$label='&laquo; Ältere Kommentare';
        return sprintf('<a href="%1$s" %2$s>%3$s</a>',esc_url(rrw_ext_comments_page_link($page-1)),apply_filters('previous_comments_link_attributes',''),preg_replace('/&([^#])(?![a-z]{1,8};)/i','&#038;$1',$label));
    }
}
if(!function_exists('get_the_comments_navigation')){
    function get_the_comments_navigation($args=[]) {
        if(get_comment_pages_count()<=1)return '';
        $a=wp_parse_args($args,['prev_text'=>'','next_text'=>'','screen_reader_text'=>'Kommentar-Navigation','aria_label'=>'Kommentare','class'=>'comment-navigation']);
        if(empty($a['prev_text']))$a['prev_text']='Ältere Kommentare';
        if(empty($a['next_text']))$a['next_text']='Neuere Kommentare';
        $nav='';$p=get_previous_comments_link($a['prev_text']);$n=get_next_comments_link($a['next_text']);
        if($p)$nav.='<div class="nav-previous">'.$p.'</div>';if($n)$nav.='<div class="nav-next">'.$n.'</div>';
        return $nav===''?'':_navigation_markup($nav,$a['class'],$a['screen_reader_text'],$a['aria_label']);
    }
}
if(!function_exists('get_the_comments_pagination')){
    function get_the_comments_pagination($args=[]) {
        $a=wp_parse_args($args,['screen_reader_text'=>'Kommentar-Navigation','aria_label'=>'Kommentare','class'=>'comments-pagination']);$a['echo']=false;
        $links=paginate_comments_links($a);
        return $links?_navigation_markup($links,$a['class'],$a['screen_reader_text'],$a['aria_label']):'';
    }
}

/* ───────── Verwaltung / Kanonisch / Kurzlink ───────── */
if(!function_exists('user_admin_url')){
    function user_admin_url($path='', $scheme='admin') { $url=site_url('wp-admin/user/',$scheme);if($path&&is_string($path))$url.=ltrim($path,'/');return apply_filters('user_admin_url',$url,$path); }
}
if(!function_exists('get_edit_profile_url')){
    function get_edit_profile_url($user_id=0, $scheme='admin') {
        $user_id=$user_id?(int)$user_id:get_current_user_id();
        $url=get_dashboard_url($user_id,'profile.php',$scheme);
        return apply_filters('edit_profile_url',$url,$user_id,$scheme);
    }
}
if(!function_exists('wp_get_canonical_url')){
    function wp_get_canonical_url($post=null) {
        $post=get_post($post);if(!$post||$post->post_status!=='publish')return false;
        $url=get_permalink($post);
        if(get_queried_object_id()===(int)$post->ID){
            $page=(int)get_query_var('page',0);if($page>=2)$url=trailingslashit($url).$page.'/';
            $cpage=(int)get_query_var('cpage',0);if($cpage)$url=rrw_ext_comments_page_link($cpage);
        }
        return apply_filters('get_canonical_url',$url,$post);
    }
}
if(!function_exists('wp_get_shortlink')){
    function wp_get_shortlink($id=0, $context='post', $allow_slugs=true) {
        $s=apply_filters('pre_get_shortlink',false,$id,$context,$allow_slugs);if($s!==false)return $s;
        $post_id=0;$post=null;
        if($context==='query'&&is_singular()){ $post_id=get_queried_object_id();$post=get_post($post_id); }
        elseif($context==='post'){ $post=get_post($id);if(!empty($post->ID))$post_id=$post->ID; }
        $s='';
        if($post_id&&$post){
            $pt=get_post_type_object($post->post_type);
            if($post->post_type==='page'&&(int)get_option('page_on_front')===(int)$post_id&&get_option('show_on_front')==='page')$s=home_url('/');
            elseif($pt&&$pt->public)$s=home_url('?p='.$post_id);
        }
        return apply_filters('get_shortlink',$s,$id,$context,$allow_slugs);
    }
}
if(!function_exists('wp_shortlink_header')){
    function wp_shortlink_header() { if(headers_sent())return;$s=wp_get_shortlink(0,'query');if(empty($s))return;header('Link: <'.$s.'>; rel=shortlink',false); }
}
if(!function_exists('the_shortlink')){
    function the_shortlink($text='', $title='', $before='', $after='') {
        $post=get_post();if(!$post)return;
        if(empty($text))$text='Dies ist der Kurzlink.';if(empty($title))$title=esc_attr(strip_tags($post->post_title));
        $s=wp_get_shortlink($post->ID);if(empty($s))return;
        echo $before,apply_filters('the_shortlink','<a rel="shortlink" href="'.esc_url($s).'" title="'.$title.'">'.$text.'</a>',$s,$text,$title),$after;
    }
}

/* ───────── Avatare ───────── */
if(!function_exists('is_avatar_comment_type')){
    function is_avatar_comment_type($comment_type) { return in_array($comment_type?:'comment',(array)apply_filters('get_avatar_comment_types',['comment']),true); }
}
if(!function_exists('get_avatar_data')){
    /** Avatar-Angaben (url, size, found_avatar …) aus Benutzer, E-Mail oder Kommentar; nur URL-Aufbau, kein Netzzugriff. */
    function get_avatar_data($id_or_email, $args=null) {
        $a=wp_parse_args($args,['size'=>96,'height'=>null,'width'=>null,'default'=>get_option('avatar_default','mystery'),'force_default'=>false,'rating'=>get_option('avatar_rating','G'),'scheme'=>null,'processed_args'=>null,'extra_attr'=>'']);
        if(empty($a['height']))$a['height']=$a['size'];if(empty($a['width']))$a['width']=$a['size'];
        $a['size']=max(1,(int)$a['size']);
        if(is_object($id_or_email)&&isset($id_or_email->comment_ID))$id_or_email=get_comment($id_or_email);
        $a=apply_filters('pre_get_avatar_data',$a,$id_or_email);
        if(isset($a['url']))return apply_filters('get_avatar_data',$a,$id_or_email);
        $a['found_avatar']=false;$hash='';$user=false;$email='';
        if(is_numeric($id_or_email))$user=get_user_by('id',absint($id_or_email));
        elseif(is_string($id_or_email)){ if(str_contains($id_or_email,'@md5.gravatar.com'))[$hash]=explode('@',$id_or_email);else $email=$id_or_email; }
        elseif($id_or_email instanceof WP_User)$user=$id_or_email;
        elseif($id_or_email instanceof WP_Post)$user=get_user_by('id',(int)$id_or_email->post_author);
        elseif($id_or_email instanceof WP_Comment){
            if(!is_avatar_comment_type($id_or_email->comment_type??'comment')){ $a['url']=false;return apply_filters('get_avatar_data',$a,$id_or_email); }
            if(!empty($id_or_email->user_id))$user=get_user_by('id',(int)$id_or_email->user_id);
            if((!$user||is_wp_error($user))&&!empty($id_or_email->comment_author_email))$email=$id_or_email->comment_author_email;
        }
        if(!$hash){ if($user&&!is_wp_error($user))$email=$user->user_email; if($email)$hash=md5(strtolower(trim($email))); }
        if($hash)$a['found_avatar']=true;
        $d=(string)$a['default'];
        if(in_array($d,['mystery','mm','mysteryman'],true))$d='mm'; elseif($d==='gravatar_default')$d=''; elseif($d==='blank')$d='blank';
        elseif(!in_array($d,['identicon','wavatar','monsterid','retro','robohash','404'],true))$d=($d!==''&&preg_match('#^https?://#i',$d))?$d:'mm';
        if($a['force_default'])$d=$d===''?'mm':$d;
        $q=array_filter(['s'=>$a['size'],'d'=>$d,'f'=>$a['force_default']?'y':'','r'=>strtolower((string)$a['rating'])]);
        $a['url']=add_query_arg(rawurlencode_deep($q),'https://secure.gravatar.com/avatar/'.($hash?:md5('')));
        $a['url']=apply_filters('get_avatar_url',$a['url'],$id_or_email,$a);
        return apply_filters('get_avatar_data',$a,$id_or_email);
    }
}

/* ───────── Interne Adressen ───────── */
if(!function_exists('wp_internal_hosts')){
    function wp_internal_hosts() {
        static $hosts=null;
        if(empty($hosts))$hosts=array_values(array_unique(array_map('strtolower',(array)apply_filters('wp_internal_hosts',[wp_parse_url(home_url(),PHP_URL_HOST)]))));
        return $hosts;
    }
}
if(!function_exists('wp_is_internal_link')){
    function wp_is_internal_link($link) {
        $link=strtolower((string)$link);
        return in_array(wp_parse_url($link,PHP_URL_SCHEME),wp_allowed_protocols(),true)&&in_array(wp_parse_url($link,PHP_URL_HOST),wp_internal_hosts(),true);
    }
}

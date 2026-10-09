<?php
// Veraltete WordPress-Funktionen (wp-includes/deprecated.php, Teil 1): Beitrags-, Kategorie-, Autoren-, Link- und Textfunktionen.
// Jede Funktion meldet sich über elvado_ext_lg_dep() und delegiert an den Ersatz. Keine Ausgabe/DB beim Laden.

if(!function_exists('elvado_ext_lg_dep')){
    /** Hinweis auf veraltete Funktion: Hook deprecated_function_run; bei WP_DEBUG ein Logeintrag (sonst nichts). */
    function elvado_ext_lg_dep(string $fn,string $ver,string $repl=''): void {
        do_action('deprecated_function_run',$fn,$repl,$ver);
        if(defined('WP_DEBUG')&&WP_DEBUG&&apply_filters('deprecated_function_trigger_error',true)&&function_exists('elvado_wp_log'))
            elvado_wp_log($repl!==''?sprintf('%s ist seit %s veraltet, nutze %s',$fn,$ver,$repl):sprintf('%s ist seit %s veraltet',$fn,$ver));
    }
}
if(!function_exists('elvado_ext_lg_author')){
    /** Gemeinsamer Zugriff der alten the_author_*-Funktionen. */
    function elvado_ext_lg_author(string $fn,string $ver,string $repl,string $key,$uid=false,bool $echo=false){
        elvado_ext_lg_dep($fn,$ver,$repl);
        if(in_array($key,['login','email','url'],true))$key='user_'.$key;   // Kernfelder heißen user_*
        $v=get_the_author_meta($key,$uid);
        if($echo){ echo $v;return null; }
        return $v;
    }
}
if(!function_exists('elvado_ext_lg_can')){
    /** Recht eines Benutzers per ID oder WP_User prüfen (user_can des Kerns kennt nur Objekte). */
    function elvado_ext_lg_can($user,string $cap,...$args): bool {
        $u=$user instanceof WP_User?$user:get_userdata((int)$user);
        return $u?(bool)user_can($u,$cap,...$args):false;
    }
}
if(!function_exists('elvado_ext_lg_before_bar')){
    function elvado_ext_lg_before_bar(string $t): string { $p=strrpos($t,'|');return $p===false?$t:substr($t,0,$p); }
}

/* ───────── Beitrag / Schleife ───────── */
if(!function_exists('get_postdata')){
    function get_postdata($post_id){
        elvado_ext_lg_dep(__FUNCTION__,'1.5.1','get_post()');
        $post=get_post($post_id);if(!$post)return [];
        $cat=get_the_category($post->ID);
        return ['ID'=>$post->ID,'Author_ID'=>$post->post_author,'Date'=>$post->post_date,'Content'=>$post->post_content,'Excerpt'=>$post->post_excerpt,'Title'=>$post->post_title,
            'Category'=>$cat?$cat[0]->term_id:0,'Last_Modified'=>$post->post_modified,'Status'=>$post->post_status,'Name'=>$post->post_name,'Pings'=>$post->to_ping??''];
    }
}
if(!function_exists('start_wp')){
    function start_wp(){
        global $wp_query;
        elvado_ext_lg_dep(__FUNCTION__,'1.5.0','new WP_Query()');
        if($wp_query&&method_exists($wp_query,'have_posts')&&$wp_query->have_posts())$wp_query->the_post();
    }
}
if(!function_exists('the_category_ID')){
    function the_category_ID($echo=true){
        elvado_ext_lg_dep(__FUNCTION__,'0.71','get_the_category()');
        $cat=get_the_category();$id=$cat?$cat[0]->term_id:0;
        if($echo)echo $id;
        return $id;
    }
}
if(!function_exists('the_category_head')){
    function the_category_head($before='',$after=''){
        global $currentcat,$previouscat;
        elvado_ext_lg_dep(__FUNCTION__,'0.71','get_the_category_by_ID()');
        $cat=get_the_category();$currentcat=$cat?$cat[0]->term_id:0;
        if($currentcat!=$previouscat){ echo $before.get_cat_name($currentcat).$after;$previouscat=$currentcat; }
    }
}
if(!function_exists('previous_post')){
    function previous_post($format='%',$previous='previous post: ',$title='yes',$in_same_cat='no',$limitprev=1,$excluded_categories=''){
        elvado_ext_lg_dep(__FUNCTION__,'2.0.0','previous_post_link()');
        $post=get_previous_post(!(empty($in_same_cat)||'no'==$in_same_cat),$excluded_categories);
        if(!$post)return;
        $s='<a href="'.get_permalink($post->ID).'">'.$previous;
        if('yes'==$title)$s.=apply_filters('the_title',$post->post_title,$post->ID);
        echo str_replace('%',$s.'</a>',$format);
    }
}
if(!function_exists('next_post')){
    function next_post($format='%',$next='next post: ',$title='yes',$in_same_cat='no',$limitnext=1,$excluded_categories=''){
        elvado_ext_lg_dep(__FUNCTION__,'2.0.0','next_post_link()');
        $post=get_next_post(!(empty($in_same_cat)||'no'==$in_same_cat),$excluded_categories);
        if(!$post)return;
        $s='<a href="'.get_permalink($post->ID).'">'.$next;
        if('yes'==$title)$s.=apply_filters('the_title',$post->post_title,$post->ID);
        echo str_replace('%',$s.'</a>',$format);
    }
}
if(!function_exists('sticky_class')){
    function sticky_class($post_id=null){
        elvado_ext_lg_dep(__FUNCTION__,'3.5.0','post_class()');
        if(!is_sticky($post_id))return;
        echo ' sticky';
    }
}
if(!function_exists('_get_post_ancestors')){
    function _get_post_ancestors(&$post){
        elvado_ext_lg_dep(__FUNCTION__,'3.5.0');
        if(is_object($post))$post->ancestors=get_post_ancestors($post);
    }
}
if(!function_exists('wp_get_single_post')){
    function wp_get_single_post($postid=0,$mode=OBJECT){
        elvado_ext_lg_dep(__FUNCTION__,'3.5.0','get_post()');
        return get_post($postid,$mode);
    }
}
if(!function_exists('_save_post_hook')){
    function _save_post_hook(){ elvado_ext_lg_dep(__FUNCTION__,'5.6.0'); }
}
if(!function_exists('get_paged_template')){
    function get_paged_template(){ elvado_ext_lg_dep(__FUNCTION__,'4.7.0','get_query_template()');return get_query_template('paged'); }
}
if(!function_exists('permalink_link')){
    function permalink_link(){ elvado_ext_lg_dep(__FUNCTION__,'1.2.0','the_permalink()');the_permalink(); }
}
if(!function_exists('permalink_single_rss')){
    function permalink_single_rss($deprecated=''){ elvado_ext_lg_dep(__FUNCTION__,'2.3.0','the_permalink_rss()');echo esc_url(get_permalink()); }
}

/* ───────── Rechte (alte Benutzerstufen auf Fähigkeiten abgebildet) ───────── */
if(!function_exists('user_can_create_post')){
    function user_can_create_post($user_id,$blog_id=1,$category_id='None'){ elvado_ext_lg_dep(__FUNCTION__,'2.0.0','current_user_can()');return elvado_ext_lg_can($user_id,'publish_posts'); }
}
if(!function_exists('user_can_create_draft')){
    function user_can_create_draft($user_id,$blog_id=1,$category_id='None'){ elvado_ext_lg_dep(__FUNCTION__,'2.0.0','current_user_can()');return elvado_ext_lg_can($user_id,'edit_posts'); }
}
if(!function_exists('user_can_edit_post')){
    function user_can_edit_post($user_id,$post_id,$blog_id=1){ elvado_ext_lg_dep(__FUNCTION__,'2.0.0','current_user_can()');return elvado_ext_lg_can($user_id,'edit_post',$post_id); }
}
if(!function_exists('user_can_delete_post')){
    function user_can_delete_post($user_id,$post_id,$blog_id=1){ elvado_ext_lg_dep(__FUNCTION__,'2.0.0','current_user_can()');return elvado_ext_lg_can($user_id,'delete_post',$post_id); }
}
if(!function_exists('user_can_set_post_date')){
    function user_can_set_post_date($user_id,$blog_id=1,$category_id='None'){ elvado_ext_lg_dep(__FUNCTION__,'2.0.0','current_user_can()');return elvado_ext_lg_can($user_id,'publish_posts'); }
}
if(!function_exists('user_can_edit_post_date')){
    function user_can_edit_post_date($user_id,$post_id,$blog_id=1){ elvado_ext_lg_dep(__FUNCTION__,'2.0.0','current_user_can()');return elvado_ext_lg_can($user_id,'edit_post',$post_id); }
}
if(!function_exists('user_can_edit_post_comments')){
    function user_can_edit_post_comments($user_id,$post_id,$blog_id=1){ elvado_ext_lg_dep(__FUNCTION__,'2.0.0','current_user_can()');return elvado_ext_lg_can($user_id,'edit_post',$post_id); }
}
if(!function_exists('user_can_delete_post_comments')){
    function user_can_delete_post_comments($user_id,$post_id,$blog_id=1){ elvado_ext_lg_dep(__FUNCTION__,'2.0.0','current_user_can()');return elvado_ext_lg_can($user_id,'edit_post',$post_id); }
}
if(!function_exists('user_can_edit_user')){
    function user_can_edit_user($user_id,$other_user){ elvado_ext_lg_dep(__FUNCTION__,'2.0.0','current_user_can()');return (int)$user_id===(int)$other_user||elvado_ext_lg_can($user_id,'edit_users'); }
}

/* ───────── Links (Linkverwaltung gibt es nicht: leere Ergebnisse) ───────── */
if(!function_exists('get_linksbyname')){
    function get_linksbyname($cat_name='noname',$before='',$after='<br />',$between=' ',$show_images=true,$orderby='id',$show_description=true,$show_rating=false,$limit=-1,$show_updated=0){ elvado_ext_lg_dep(__FUNCTION__,'2.1.0','get_bookmarks()'); }
}
if(!function_exists('wp_get_linksbyname')){
    function wp_get_linksbyname($category,$args=''){ elvado_ext_lg_dep(__FUNCTION__,'2.1.0','wp_list_bookmarks()');return ''; }
}
if(!function_exists('get_linkobjectsbyname')){
    function get_linkobjectsbyname($cat_name='noname',$orderby='name',$limit=-1){ elvado_ext_lg_dep(__FUNCTION__,'2.1.0','get_bookmarks()');return []; }
}
if(!function_exists('get_linkobjects')){
    function get_linkobjects($category=0,$orderby='name',$limit=0){ elvado_ext_lg_dep(__FUNCTION__,'2.1.0','get_bookmarks()');return []; }
}
if(!function_exists('get_linksbyname_withrating')){
    function get_linksbyname_withrating($cat_name='noname',$before='',$after='<br />',$between=' ',$show_images=true,$orderby='id',$show_description=true,$limit=-1,$show_updated=0){ elvado_ext_lg_dep(__FUNCTION__,'2.1.0','get_bookmarks()'); }
}
if(!function_exists('get_links_withrating')){
    function get_links_withrating($category=-1,$before='',$after='<br />',$between=' ',$show_images=true,$orderby='id',$show_description=true,$limit=-1,$show_updated=0){ elvado_ext_lg_dep(__FUNCTION__,'2.1.0','get_bookmarks()'); }
}
if(!function_exists('get_autotoggle')){
    function get_autotoggle($id=0){ elvado_ext_lg_dep(__FUNCTION__,'2.1.0');return 0; }
}
if(!function_exists('wp_get_links')){
    function wp_get_links($args=''){ elvado_ext_lg_dep(__FUNCTION__,'2.1.0','wp_list_bookmarks()');return ''; }
}
if(!function_exists('get_links')){
    function get_links($category=-1,$before='',$after='<br />',$between=' ',$show_images=true,$orderby='name',$show_description=true,$show_rating=false,$limit=-1,$show_updated=1,$echo=true){ elvado_ext_lg_dep(__FUNCTION__,'2.1.0','get_bookmarks()');return $echo?null:''; }
}
if(!function_exists('get_links_list')){
    function get_links_list($order='name'){ elvado_ext_lg_dep(__FUNCTION__,'2.1.0','wp_list_bookmarks()'); }
}
if(!function_exists('links_popup_script')){
    function links_popup_script($text='Links',$width=400,$height=400,$file='links.all.php',$count=true){ elvado_ext_lg_dep(__FUNCTION__,'2.1.0'); }
}
if(!function_exists('get_linkrating')){
    function get_linkrating($link){ elvado_ext_lg_dep(__FUNCTION__,'2.1.0','sanitize_bookmark_field()');return is_object($link)?(int)($link->link_rating??0):0; }
}
if(!function_exists('get_linkcatname')){
    function get_linkcatname($id=0){ elvado_ext_lg_dep(__FUNCTION__,'2.1.0','get_category()');return ''; }
}
if(!function_exists('get_link')){
    function get_link($bookmark_id,$output=OBJECT,$filter='raw'){ elvado_ext_lg_dep(__FUNCTION__,'2.1.0','get_bookmark()');return null; }
}

/* ───────── Kategorien / Archive / Autorenlisten ───────── */
if(!function_exists('get_all_category_ids')){
    function get_all_category_ids(){
        elvado_ext_lg_dep(__FUNCTION__,'4.0.0','get_terms()');
        $ids=get_categories(['fields'=>'ids','hide_empty'=>0,'get'=>'all']);
        return is_array($ids)?array_map('intval',$ids):[];
    }
}
if(!function_exists('get_category_children')){
    function get_category_children($id,$before='/',$after='',$visited=[]){
        elvado_ext_lg_dep(__FUNCTION__,'2.1.0','get_term_children()');
        if(0==$id)return '';
        $chain='';
        foreach(get_all_category_ids() as $cat_id){
            if($cat_id==$id)continue;
            $c=get_category($cat_id);
            if(is_wp_error($c)||!$c)return $c;
            if($c->parent==$id&&!in_array($c->term_id,$visited)){
                $visited[]=$c->term_id;
                $chain.=$before.$c->term_id.$after.get_category_children($c->term_id,$before,$after,$visited);
            }
        }
        return $chain;
    }
}
if(!function_exists('get_catname')){
    function get_catname($cat_ID){ elvado_ext_lg_dep(__FUNCTION__,'2.8.0','get_cat_name()');return get_cat_name($cat_ID); }
}
if(!function_exists('wp_list_cats')){
    function wp_list_cats($args=''){
        elvado_ext_lg_dep(__FUNCTION__,'2.1.0','wp_list_categories()');
        $r=wp_parse_args($args);
        foreach(['sort_column'=>'orderby','sort_order'=>'order','optioncount'=>'show_count']as $o=>$n)if(isset($r[$o])){ $r[$n]=$r[$o];unset($r[$o]); }
        if(array_key_exists('optionall',$r)){ $r['show_option_all']=$r['optionall']?($r['all']??'All'):'';unset($r['optionall'],$r['all']); }
        if(isset($r['orderby']))$r['orderby']=strtolower($r['orderby'])==='id'?'ID':$r['orderby'];
        return wp_list_categories($r);
    }
}
if(!function_exists('list_cats')){
    function list_cats($optionall=1,$all='All',$sort_column='ID',$sort_order='asc',$file='',$list=true,$optiondates=0,$optioncount=0,$hide_empty=1,$use_desc_for_title=1,$children=false,$child_of=0,$categories=0,$recurse=0,$feed='',$feed_image='',$exclude='',$hierarchical=false){
        elvado_ext_lg_dep(__FUNCTION__,'2.1.0','wp_list_categories()');
        return wp_list_cats(['optionall'=>$optionall,'all'=>$all,'sort_column'=>$sort_column,'sort_order'=>$sort_order,'optioncount'=>$optioncount,'hide_empty'=>$hide_empty,'use_desc_for_title'=>$use_desc_for_title,
            'child_of'=>$child_of,'feed'=>$feed,'feed_image'=>$feed_image,'exclude'=>$exclude,'hierarchical'=>$hierarchical]);
    }
}
if(!function_exists('dropdown_cats')){
    function dropdown_cats($optionall=1,$all='All',$orderby='ID',$order='asc',$show_last_update=0,$show_count=0,$hide_empty=1,$optionnone=false,$selected=0,$exclude=0){
        elvado_ext_lg_dep(__FUNCTION__,'2.1.0','wp_dropdown_categories()');
        return wp_dropdown_categories(['show_option_all'=>$optionall?$all:'','orderby'=>strtolower($orderby)==='id'?'ID':$orderby,'order'=>$order,'show_count'=>$show_count,'hide_empty'=>$hide_empty,
            'show_option_none'=>$optionnone?:'','selected'=>$selected,'exclude'=>$exclude]);
    }
}
if(!function_exists('list_authors')){
    function list_authors($optioncount=false,$exclude_admin=true,$show_fullname=false,$hide_empty=true,$feed='',$feed_image=''){
        elvado_ext_lg_dep(__FUNCTION__,'2.1.0','wp_list_authors()');
        return wp_list_authors(compact('optioncount','exclude_admin','show_fullname','hide_empty','feed','feed_image'));
    }
}
if(!function_exists('wp_get_post_cats')){
    function wp_get_post_cats($blogid='1',$post_ID=0){ elvado_ext_lg_dep(__FUNCTION__,'2.1.0','wp_get_post_categories()');return wp_get_post_categories((int)$post_ID); }
}
if(!function_exists('wp_set_post_cats')){
    function wp_set_post_cats($blogid='1',$post_ID=0,$post_categories=[]){ elvado_ext_lg_dep(__FUNCTION__,'2.1.0','wp_set_post_categories()');return wp_set_post_categories($post_ID,$post_categories); }
}
if(!function_exists('get_archives')){
    function get_archives($type='',$limit='',$format='html',$before='',$after='',$show_post_count=false){
        elvado_ext_lg_dep(__FUNCTION__,'2.1.0','wp_get_archives()');
        return wp_get_archives(compact('type','limit','format','before','after','show_post_count'));
    }
}
if(!function_exists('get_author_link')){
    function get_author_link($echo,$author_id,$author_nicename=''){
        elvado_ext_lg_dep(__FUNCTION__,'2.1.0','get_author_posts_url()');
        $link=get_author_posts_url((int)$author_id,$author_nicename);
        if($echo)echo $link;
        return $link;
    }
}
if(!function_exists('link_pages')){
    function link_pages($before='<br />',$after='<br />',$next_or_number='number',$nextpagelink='next page',$previouspagelink='previous page',$pagelink='%',$more_file=''){
        elvado_ext_lg_dep(__FUNCTION__,'2.1.0','wp_link_pages()');
        return wp_link_pages(compact('before','after','next_or_number','nextpagelink','previouspagelink','pagelink'));
    }
}
if(!function_exists('get_settings')){
    function get_settings($option){ elvado_ext_lg_dep(__FUNCTION__,'2.1.0','get_option()');return get_option($option); }
}

/* ───────── Feeds ───────── */
if(!function_exists('comments_rss_link')){
    function comments_rss_link($link_text='Comments RSS'){ elvado_ext_lg_dep(__FUNCTION__,'2.5.0','post_comments_feed_link()');post_comments_feed_link($link_text); }
}
if(!function_exists('get_category_rss_link')){
    function get_category_rss_link($echo=false,$cat_ID=1){
        elvado_ext_lg_dep(__FUNCTION__,'2.5.0','get_category_feed_link()');
        $link=get_category_feed_link($cat_ID,'rss2');
        if($echo)echo $link;
        return $link;
    }
}
if(!function_exists('get_author_rss_link')){
    function get_author_rss_link($echo=false,$author_id=1){
        elvado_ext_lg_dep(__FUNCTION__,'2.5.0','get_author_feed_link()');
        $link=get_author_feed_link($author_id);
        if($echo)echo $link;
        return $link;
    }
}
if(!function_exists('comments_rss')){
    function comments_rss(){ elvado_ext_lg_dep(__FUNCTION__,'2.5.0','get_post_comments_feed_link()');return esc_url(get_post_comments_feed_link()); }
}
if(!function_exists('the_content_rss')){
    function the_content_rss($more_link_text='(more...)',$stripteaser=0,$more_file='',$cut=0,$encode_html=0){
        elvado_ext_lg_dep(__FUNCTION__,'2.9.0','the_content_feed()');
        $content=apply_filters('the_content_rss',get_the_content($more_link_text,$stripteaser));
        if($cut&&!$encode_html)$encode_html=2;
        if(1==$encode_html){ $content=esc_html($content);$cut=0; }
        elseif(0==$encode_html)$content=make_url_footnote($content);
        elseif(2==$encode_html)$content=strip_tags($content);
        if($cut){
            $w=explode(' ',$content);$k=min($cut,count($w));
            for($i=0;$i<$k;$i++)echo $w[$i].' ';
            echo count($w)>$cut?'...':'';
        } else echo $content;
    }
}
if(!function_exists('make_url_footnote')){
    function make_url_footnote($content){
        elvado_ext_lg_dep(__FUNCTION__,'2.9.0');
        preg_match_all('/<a(.+?)href=\"(.+?)\"(.*?)>(.+?)<\/a>/',$content,$m);
        $summary='';
        for($i=0,$c=count($m[0]);$i<$c;$i++){
            $summary=$summary===''?"\n":$summary;
            $n='['.($i+1).']';
            $url=('http'!==substr($m[2][$i],0,4))?get_option('home').$m[2][$i]:$m[2][$i];
            $summary.="\n".$n.' '.$url;
            $content=str_replace($m[0][$i],$m[4][$i].' '.$n,$content);
        }
        return strip_tags($content).$summary;
    }
}
if(!function_exists('get_boundary_post_rel_link')){
    function get_boundary_post_rel_link($title='%title',$in_same_cat=false,$excluded_categories='',$start=true){
        elvado_ext_lg_dep(__FUNCTION__,'3.3.0');
        $posts=get_boundary_post($in_same_cat,$excluded_categories,$start);
        if(empty($posts))return null;
        $post=$posts[0];
        $t=str_replace('%title',$post->post_title,$title!==''?$title:'%title');
        $type=$start?'start':'end';
        return apply_filters($type.'_post_rel_link',"<link rel='$type' title='".esc_attr(apply_filters('the_title',$t,$post->ID))."' href='".esc_url(get_permalink($post))."' />");
    }
}
if(!function_exists('start_post_rel_link')){
    function start_post_rel_link($title='%title',$in_same_cat=false,$excluded_categories=''){ elvado_ext_lg_dep(__FUNCTION__,'3.3.0');echo get_boundary_post_rel_link($title,$in_same_cat,$excluded_categories,true); }
}
if(!function_exists('get_index_rel_link')){
    function get_index_rel_link(){
        elvado_ext_lg_dep(__FUNCTION__,'3.3.0');
        return apply_filters('index_rel_link',"<link rel='index' title='".esc_attr(get_bloginfo('name'))."' href='".esc_url(user_trailingslashit(get_bloginfo('url')))."' />");
    }
}
if(!function_exists('index_rel_link')){
    function index_rel_link(){ elvado_ext_lg_dep(__FUNCTION__,'3.3.0');echo get_index_rel_link(); }
}
if(!function_exists('get_parent_post_rel_link')){
    function get_parent_post_rel_link($title='%title'){
        elvado_ext_lg_dep(__FUNCTION__,'3.3.0');
        $p=get_post();
        if(!$p||empty($p->post_parent))return null;
        $parent=get_post($p->post_parent);
        if(!$parent)return null;
        $t=str_replace('%title',$parent->post_title,$title!==''?$title:'%title');
        return apply_filters('parent_post_rel_link',"<link rel='up' title='".esc_attr(apply_filters('the_title',$t,$parent->ID))."' href='".esc_url(get_permalink($parent))."' />");
    }
}
if(!function_exists('parent_post_rel_link')){
    function parent_post_rel_link($title='%title'){ elvado_ext_lg_dep(__FUNCTION__,'3.3.0');echo get_parent_post_rel_link($title); }
}

/* ───────── Benutzer / Autoren ───────── */
if(!function_exists('create_user')){
    function create_user($username,$password,$email){ elvado_ext_lg_dep(__FUNCTION__,'2.0.0','wp_create_user()');return wp_create_user($username,$password,$email); }
}
if(!function_exists('gzip_compression')){
    function gzip_compression(){ elvado_ext_lg_dep(__FUNCTION__,'2.5.0');return false; }
}
if(!function_exists('get_commentdata')){
    function get_commentdata($comment_ID,$no_cache=0,$include_unapproved=false){ elvado_ext_lg_dep(__FUNCTION__,'2.7.0','get_comment()');return get_comment($comment_ID,ARRAY_A); }
}
if(!function_exists('get_author_name')){
    function get_author_name($auth_id=false){ elvado_ext_lg_dep(__FUNCTION__,'2.8.0','get_the_author_meta(\'display_name\')');return get_the_author_meta('display_name',$auth_id); }
}
if(!function_exists('get_the_author_description')){
    function get_the_author_description($auth_id=false){ return elvado_ext_lg_author(__FUNCTION__,'2.8.0',"get_the_author_meta('description')",'description',$auth_id); }
}
if(!function_exists('the_author_description')){
    function the_author_description(){ elvado_ext_lg_author(__FUNCTION__,'2.8.0',"the_author_meta('description')",'description',false,true); }
}
if(!function_exists('get_the_author_login')){
    function get_the_author_login($auth_id=false){ return elvado_ext_lg_author(__FUNCTION__,'2.8.0',"get_the_author_meta('login')",'login',$auth_id); }
}
if(!function_exists('the_author_login')){
    function the_author_login(){ elvado_ext_lg_author(__FUNCTION__,'2.8.0',"the_author_meta('login')",'login',false,true); }
}
if(!function_exists('get_the_author_firstname')){
    function get_the_author_firstname($auth_id=false){ return elvado_ext_lg_author(__FUNCTION__,'2.8.0',"get_the_author_meta('first_name')",'first_name',$auth_id); }
}
if(!function_exists('the_author_firstname')){
    function the_author_firstname(){ elvado_ext_lg_author(__FUNCTION__,'2.8.0',"the_author_meta('first_name')",'first_name',false,true); }
}
if(!function_exists('get_the_author_lastname')){
    function get_the_author_lastname($auth_id=false){ return elvado_ext_lg_author(__FUNCTION__,'2.8.0',"get_the_author_meta('last_name')",'last_name',$auth_id); }
}
if(!function_exists('the_author_lastname')){
    function the_author_lastname(){ elvado_ext_lg_author(__FUNCTION__,'2.8.0',"the_author_meta('last_name')",'last_name',false,true); }
}
if(!function_exists('get_the_author_nickname')){
    function get_the_author_nickname($auth_id=false){ return elvado_ext_lg_author(__FUNCTION__,'2.8.0',"get_the_author_meta('nickname')",'nickname',$auth_id); }
}
if(!function_exists('the_author_nickname')){
    function the_author_nickname(){ elvado_ext_lg_author(__FUNCTION__,'2.8.0',"the_author_meta('nickname')",'nickname',false,true); }
}
if(!function_exists('get_the_author_email')){
    function get_the_author_email($auth_id=false){ return elvado_ext_lg_author(__FUNCTION__,'2.8.0',"get_the_author_meta('email')",'email',$auth_id); }
}
if(!function_exists('the_author_email')){
    function the_author_email(){ elvado_ext_lg_author(__FUNCTION__,'2.8.0',"the_author_meta('email')",'email',false,true); }
}
if(!function_exists('get_the_author_icq')){
    function get_the_author_icq($auth_id=false){ return elvado_ext_lg_author(__FUNCTION__,'2.8.0',"get_the_author_meta('icq')",'icq',$auth_id); }
}
if(!function_exists('the_author_icq')){
    function the_author_icq(){ elvado_ext_lg_author(__FUNCTION__,'2.8.0',"the_author_meta('icq')",'icq',false,true); }
}
if(!function_exists('get_the_author_yim')){
    function get_the_author_yim($auth_id=false){ return elvado_ext_lg_author(__FUNCTION__,'2.8.0',"get_the_author_meta('yim')",'yim',$auth_id); }
}
if(!function_exists('the_author_yim')){
    function the_author_yim(){ elvado_ext_lg_author(__FUNCTION__,'2.8.0',"the_author_meta('yim')",'yim',false,true); }
}
if(!function_exists('get_the_author_msn')){
    function get_the_author_msn($auth_id=false){ return elvado_ext_lg_author(__FUNCTION__,'2.8.0',"get_the_author_meta('msn')",'msn',$auth_id); }
}
if(!function_exists('the_author_msn')){
    function the_author_msn(){ elvado_ext_lg_author(__FUNCTION__,'2.8.0',"the_author_meta('msn')",'msn',false,true); }
}
if(!function_exists('get_the_author_aim')){
    function get_the_author_aim($auth_id=false){ return elvado_ext_lg_author(__FUNCTION__,'2.8.0',"get_the_author_meta('aim')",'aim',$auth_id); }
}
if(!function_exists('the_author_aim')){
    function the_author_aim(){ elvado_ext_lg_author(__FUNCTION__,'2.8.0',"the_author_meta('aim')",'aim',false,true); }
}
if(!function_exists('get_the_author_url')){
    function get_the_author_url($auth_id=false){ return elvado_ext_lg_author(__FUNCTION__,'2.8.0',"get_the_author_meta('url')",'url',$auth_id); }
}
if(!function_exists('the_author_url')){
    function the_author_url(){ elvado_ext_lg_author(__FUNCTION__,'2.8.0',"the_author_meta('url')",'url',false,true); }
}
if(!function_exists('get_the_author_ID')){
    function get_the_author_ID($auth_id=false){ return elvado_ext_lg_author(__FUNCTION__,'2.8.0',"get_the_author_meta('ID')",'ID',$auth_id); }
}
if(!function_exists('the_author_ID')){
    function the_author_ID(){ elvado_ext_lg_author(__FUNCTION__,'2.8.0',"the_author_meta('ID')",'ID',false,true); }
}
if(!function_exists('delete_usermeta')){
    function delete_usermeta($user_id,$meta_key,$meta_value=''){
        elvado_ext_lg_dep(__FUNCTION__,'3.0.0','delete_user_meta()');
        if(!is_numeric($user_id))return false;
        $meta_key=preg_replace('|[^a-z0-9_]|i','',$meta_key);
        if(is_array($meta_value)||is_object($meta_value))$meta_value=serialize($meta_value);
        return delete_user_meta($user_id,$meta_key,is_string($meta_value)?trim($meta_value):$meta_value);
    }
}
if(!function_exists('get_usermeta')){
    function get_usermeta($user_id,$meta_key=''){
        elvado_ext_lg_dep(__FUNCTION__,'3.0.0','get_user_meta()');
        $user_id=(int)$user_id;
        if(!$user_id)return false;
        if($meta_key==='')return array_map(fn($v)=>maybe_unserialize($v[0]),get_user_meta($user_id));
        return get_user_meta($user_id,preg_replace('|[^a-z0-9_]|i','',$meta_key),true);
    }
}
if(!function_exists('update_usermeta')){
    function update_usermeta($user_id,$meta_key,$meta_value){
        elvado_ext_lg_dep(__FUNCTION__,'3.0.0','update_user_meta()');
        if(!is_numeric($user_id))return false;
        $meta_key=preg_replace('|[^a-z0-9_]|i','',$meta_key);
        if($meta_value===''||$meta_value===null)return delete_usermeta($user_id,$meta_key);
        return update_user_meta($user_id,$meta_key,$meta_value);
    }
}
if(!function_exists('get_users_of_blog')){
    function get_users_of_blog($id=''){
        elvado_ext_lg_dep(__FUNCTION__,'3.1.0','get_users()');
        $out=[];
        foreach(get_users() as $u){ $o=clone $u->data;$o->user_id=$u->ID;$out[]=$o; }
        return $out;
    }
}
if(!function_exists('get_profile')){
    function get_profile($field,$user=false){
        elvado_ext_lg_dep(__FUNCTION__,'3.0.0','get_the_author_meta()');
        $id=$user?(int)(get_user_by('login',$user)->ID??0):get_current_user_id();
        return $id?get_user_meta($id,$field,true):false;
    }
}
if(!function_exists('get_usernumposts')){
    function get_usernumposts($userid){ elvado_ext_lg_dep(__FUNCTION__,'3.0.0','count_user_posts()');return count_user_posts((int)$userid); }
}
if(!function_exists('get_user_metavalues')){
    function get_user_metavalues($ids){
        elvado_ext_lg_dep(__FUNCTION__,'3.2.0');
        $out=[];
        foreach(array_map('intval',(array)$ids) as $id){
            $out[$id]=[];
            foreach(get_user_meta($id) as $k=>$vals)foreach($vals as $v)$out[$id][]=(object)['user_id'=>$id,'meta_key'=>$k,'meta_value'=>$v];
        }
        return $out;
    }
}
if(!function_exists('sanitize_user_object')){
    function sanitize_user_object($user,$context='display'){
        elvado_ext_lg_dep(__FUNCTION__,'3.3.0','sanitize_user_field()');
        if(is_object($user)){
            if(!isset($user->ID))$user->ID=0;
            if(!($user instanceof WP_User))foreach(array_keys(get_object_vars($user)) as $f)if(is_string($user->$f)||is_numeric($user->$f))$user->$f=sanitize_user_field($f,$user->$f,$user->ID,$context);
            $user->filter=$context;
        } else {
            if(!isset($user['ID']))$user['ID']=0;
            foreach(array_keys($user) as $f)$user[$f]=sanitize_user_field($f,$user[$f],$user['ID'],$context);
            $user['filter']=$context;
        }
        return $user;
    }
}
if(!function_exists('is_blog_user')){
    function is_blog_user($blog_id=0){ elvado_ext_lg_dep(__FUNCTION__,'3.3.0','is_user_member_of_blog()');return is_user_member_of_blog(get_current_user_id(),$blog_id); }
}
if(!function_exists('user_pass_ok')){
    function user_pass_ok($user_login,$user_pass){ elvado_ext_lg_dep(__FUNCTION__,'3.5.0','wp_authenticate()');return !is_wp_error(wp_authenticate($user_login,$user_pass)); }
}
if(!function_exists('wp_get_user_request_data')){
    function wp_get_user_request_data($request_id){ elvado_ext_lg_dep(__FUNCTION__,'5.4.0','wp_get_user_request()');return wp_get_user_request($request_id); }
}
if(!function_exists('wp_admin_bar_dashboard_view_site_menu')){
    function wp_admin_bar_dashboard_view_site_menu($wp_admin_bar){
        elvado_ext_lg_dep(__FUNCTION__,'3.3.0');
        if(is_object($wp_admin_bar)&&method_exists($wp_admin_bar,'add_node'))$wp_admin_bar->add_node(['id'=>'view-site','title'=>__('Visit Site'),'href'=>home_url()]);
    }
}

/* ───────── Übersetzung ───────── */
if(!function_exists('_c')){
    function _c($text,$domain='default'){ elvado_ext_lg_dep(__FUNCTION__,'2.9.0','_x()');return elvado_ext_lg_before_bar(translate($text,$domain)); }
}
if(!function_exists('translate_with_context')){
    function translate_with_context($text,$domain='default'){ elvado_ext_lg_dep(__FUNCTION__,'2.9.0','_x()');return elvado_ext_lg_before_bar(translate($text,$domain)); }
}
if(!function_exists('_nc')){
    function _nc($single,$plural,$number,$domain='default'){ elvado_ext_lg_dep(__FUNCTION__,'2.9.0','_nx()');return elvado_ext_lg_before_bar(_n($single,$plural,$number,$domain)); }
}
if(!function_exists('__ngettext_noop')){
    function __ngettext_noop($single,$plural,$number=1,$domain='default'){ elvado_ext_lg_dep(__FUNCTION__,'2.8.0','_n_noop()');return _n_noop($single,$plural,$domain); }
}
if(!function_exists('_get_path_to_translation')){
    function _get_path_to_translation($domain,$reset=false){
        elvado_ext_lg_dep(__FUNCTION__,'6.6.0','WP_Textdomain_Registry');
        return _get_path_to_translation_from_lang_dir($domain);
    }
}
if(!function_exists('_get_path_to_translation_from_lang_dir')){
    function _get_path_to_translation_from_lang_dir($domain){
        elvado_ext_lg_dep(__FUNCTION__,'6.6.0','WP_Textdomain_Registry');
        $loc=determine_locale();
        foreach(['','/plugins','/themes'] as $sub){ $f=WP_LANG_DIR.$sub.'/'.$domain.'-'.$loc.'.mo';if(is_file($f))return $f; }
        return false;
    }
}

/* ───────── Optionen / Anhänge ───────── */
if(!function_exists('get_alloptions')){
    function get_alloptions(){ elvado_ext_lg_dep(__FUNCTION__,'3.0.0','wp_load_alloptions()');return wp_load_alloptions(); }
}
if(!function_exists('get_attachment_icon_src')){
    function get_attachment_icon_src($id=0,$fullsize=false){
        elvado_ext_lg_dep(__FUNCTION__,'2.5.0','wp_get_attachment_image_src()');
        $post=get_post($id);
        if(!$post)return false;
        if(wp_attachment_is_image($post->ID)){
            $src=$fullsize?wp_get_attachment_url($post->ID):(wp_get_attachment_image_src($post->ID,'thumbnail')[0]??'');
            return $src?[$src,(string)get_attached_file($post->ID)]:false;
        }
        $src=wp_mime_type_icon($post->ID);
        return $src?[$src,'']:false;
    }
}
if(!function_exists('get_attachment_icon')){
    function get_attachment_icon($id=0,$fullsize=false,$max_dims=false){
        elvado_ext_lg_dep(__FUNCTION__,'2.5.0','wp_get_attachment_image()');
        $post=get_post($id);$s=get_attachment_icon_src($id,$fullsize);
        if(!$post||!$s)return false;
        return apply_filters('attachment_icon',"<img src='".esc_url($s[0])."' title='".esc_attr($post->post_title)."' alt='".esc_attr($post->post_title)."' />",$post->ID);
    }
}
if(!function_exists('get_attachment_innerHTML')){
    function get_attachment_innerHTML($id=0,$fullsize=false,$max_dims=false){
        elvado_ext_lg_dep(__FUNCTION__,'2.5.0','wp_get_attachment_image()');
        $icon=get_attachment_icon($id,$fullsize,$max_dims);
        if($icon)return $icon;
        $post=get_post($id);
        return $post?esc_html($post->post_title):'';
    }
}
if(!function_exists('get_the_attachment_link')){
    function get_the_attachment_link($id=0,$fullsize=false,$max_dims=false){
        elvado_ext_lg_dep(__FUNCTION__,'2.5.0','wp_get_attachment_link()');
        $post=get_post((int)$id);
        if(!$post||'attachment'!=$post->post_type||!($url=wp_get_attachment_url($post->ID)))return __('Missing Attachment');
        return "<a href='".esc_url($url)."' title='".esc_attr($post->post_title)."'>".get_attachment_innerHTML($post->ID,$fullsize,$max_dims).'</a>';
    }
}
if(!function_exists('wp_get_attachment_thumb_file')){
    function wp_get_attachment_thumb_file($post_id=0){
        elvado_ext_lg_dep(__FUNCTION__,'6.1.0');
        $post=get_post((int)$post_id);
        if(!$post)return false;
        $meta=wp_get_attachment_metadata($post->ID);$file=get_attached_file($post->ID);
        if(!is_array($meta)||!$file||empty($meta['thumb']))return false;
        $thumb=str_replace(wp_basename($file),$meta['thumb'],$file);
        return is_file($thumb)?apply_filters('wp_get_attachment_thumb_file',$thumb,$post->ID):false;
    }
}
if(!function_exists('wp_load_image')){
    function wp_load_image($file){
        elvado_ext_lg_dep(__FUNCTION__,'3.5.0','wp_get_image_editor()');
        if(is_numeric($file))$file=get_attached_file($file);
        if(!is_string($file)||!is_file($file))return sprintf(__('File &#8220;%s&#8221; doesn&#8217;t exist?'),(string)$file);
        if(!function_exists('imagecreatefromstring'))return __('The GD image library is not installed.');
        $img=@imagecreatefromstring((string)file_get_contents($file));
        return $img?$img:sprintf(__('File &#8220;%s&#8221; is not an image.'),$file);
    }
}
if(!function_exists('image_resize')){
    function image_resize($file,$max_w,$max_h,$crop=false,$suffix=null,$dest_path=null,$jpeg_quality=90){
        elvado_ext_lg_dep(__FUNCTION__,'3.5.0','wp_get_image_editor()');
        $ed=wp_get_image_editor($file);
        if(is_wp_error($ed))return $ed;
        $ed->set_quality($jpeg_quality);
        $r=$ed->resize($max_w,$max_h,$crop);
        if(is_wp_error($r))return $r;
        $saved=$ed->save($ed->generate_filename($suffix,$dest_path));
        return is_wp_error($saved)?$saved:$saved['path'];
    }
}
if(!function_exists('gd_edit_image_support')){
    function gd_edit_image_support($mime_type){
        elvado_ext_lg_dep(__FUNCTION__,'3.5.0','wp_image_editor_supports()');
        if(!function_exists('imagetypes'))return false;
        $map=['image/jpeg'=>'IMG_JPG','image/png'=>'IMG_PNG','image/gif'=>'IMG_GIF','image/webp'=>'IMG_WEBP'];
        return isset($map[$mime_type])&&defined($map[$mime_type])&&(imagetypes()&constant($map[$mime_type]));
    }
}
if(!function_exists('wp_convert_bytes_to_hr')){
    function wp_convert_bytes_to_hr($bytes){
        elvado_ext_lg_dep(__FUNCTION__,'3.6.0','size_format()');
        $u=[0=>'B',1=>'KB',2=>'MB',3=>'GB',4=>'TB'];
        $p=$bytes>0?(int)(log($bytes,1024)):0;
        if(!isset($u[$p]))$p=0;
        return ($bytes>0?pow(1024,-$p)*$bytes:$bytes).$u[$p];
    }
}

/* ───────── Escaping / Text ───────── */
if(!function_exists('clean_url')){
    function clean_url($url,$protocols=null,$context='display'){
        elvado_ext_lg_dep(__FUNCTION__,'3.0.0','esc_url()');
        return $context==='db'?esc_url_raw($url,$protocols):esc_url($url,$protocols);
    }
}
if(!function_exists('js_escape')){
    function js_escape($text){ elvado_ext_lg_dep(__FUNCTION__,'2.8.0','esc_js()');return esc_js($text); }
}
if(!function_exists('wp_specialchars')){
    function wp_specialchars($string,$quote_style=ENT_NOQUOTES,$charset=false,$double_encode=false){
        elvado_ext_lg_dep(__FUNCTION__,'2.8.0','esc_html()');
        if(func_num_args()>1&&function_exists('_wp_specialchars'))return _wp_specialchars($string,$quote_style,$charset,$double_encode);
        return esc_html($string);
    }
}
if(!function_exists('attribute_escape')){
    function attribute_escape($text){ elvado_ext_lg_dep(__FUNCTION__,'2.8.0','esc_attr()');return esc_attr($text); }
}
if(!function_exists('funky_javascript_callback')){
    function funky_javascript_callback($matches){ elvado_ext_lg_dep(__FUNCTION__,'3.0.0');return '&#'.base_convert($matches[1],16,10).';'; }
}
if(!function_exists('funky_javascript_fix')){
    function funky_javascript_fix($text){
        elvado_ext_lg_dep(__FUNCTION__,'3.0.0');
        global $is_macIE,$is_winIE;
        if($is_winIE||$is_macIE)$text=preg_replace_callback("/\%u([0-9A-F]{4,4})/",'funky_javascript_callback',$text);
        return $text;
    }
}
if(!function_exists('clean_pre')){
    function clean_pre($matches){
        elvado_ext_lg_dep(__FUNCTION__,'3.4.0');
        $t=is_array($matches)?$matches[1].$matches[2].'</pre>':$matches;
        return str_replace(['<br />','<br/>','<br>'],'',$t);
    }
}
if(!function_exists('_search_terms_tidy')){
    function _search_terms_tidy($t){ elvado_ext_lg_dep(__FUNCTION__,'3.7.0');return trim($t,"\"'\n\r "); }
}
if(!function_exists('default_topic_count_text')){
    function default_topic_count_text($count){ elvado_ext_lg_dep(__FUNCTION__,'3.9.0');return number_format_i18n($count); }
}
if(!function_exists('format_to_post')){
    function format_to_post($content){ elvado_ext_lg_dep(__FUNCTION__,'3.9.0');return apply_filters('format_to_post',$content); }
}
if(!function_exists('like_escape')){
    function like_escape($text){ elvado_ext_lg_dep(__FUNCTION__,'4.0.0','wpdb::esc_like()');return str_replace(['%','_'],['\\%','\\_'],$text); }
}
if(!function_exists('wp_richedit_pre')){
    function wp_richedit_pre($text){
        elvado_ext_lg_dep(__FUNCTION__,'4.3.0','format_for_editor()');
        if(empty($text))return apply_filters('richedit_pre','');
        $out=htmlspecialchars(wpautop(convert_chars($text)),ENT_NOQUOTES,get_option('blog_charset'));
        return apply_filters('richedit_pre',$out);
    }
}
if(!function_exists('wp_htmledit_pre')){
    function wp_htmledit_pre($output){
        elvado_ext_lg_dep(__FUNCTION__,'4.3.0','format_for_editor()');
        if(!empty($output))$output=htmlspecialchars($output,ENT_NOQUOTES,get_option('blog_charset'));
        return apply_filters('htmledit_pre',$output);
    }
}
if(!function_exists('popuplinks')){
    function popuplinks($text){ elvado_ext_lg_dep(__FUNCTION__,'4.5.0');return preg_replace('/<a (.+?)>/i',"<a $1 target='_blank' rel='external'>",$text); }
}
if(!function_exists('wp_kses_js_entities')){
    function wp_kses_js_entities($content){ elvado_ext_lg_dep(__FUNCTION__,'4.7.0');return preg_replace('%&\s*\{[^}]*(\}\s*;?|$)%','',$content); }
}
if(!function_exists('addslashes_strings_only')){
    function addslashes_strings_only($value){ elvado_ext_lg_dep(__FUNCTION__,'5.3.0','wp_slash()');return is_string($value)?addslashes($value):$value; }
}
if(!function_exists('wp_blacklist_check')){
    function wp_blacklist_check($author,$email,$url,$comment,$user_ip,$user_agent){ elvado_ext_lg_dep(__FUNCTION__,'5.5.0','wp_check_comment_disallowed_list()');return wp_check_comment_disallowed_list($author,$email,$url,$comment,$user_ip,$user_agent); }
}

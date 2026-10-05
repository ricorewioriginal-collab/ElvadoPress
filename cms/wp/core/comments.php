<?php
// Kommentare: Quelle sind die CMS-Kommentare (comments.json, nur freigegebene) und die Tabelle wp_comments. Neue Kommentare gehen an den CMS-Kommentarspeicher (Freigabe-Einstellungen des CMS gelten).
if(!class_exists('WP_Comment')){
#[AllowDynamicProperties]
final class WP_Comment {
    public $comment_ID=0;public $comment_post_ID=0;public $comment_author='';public $comment_author_email='';public $comment_author_url='';public $comment_author_IP='';public $comment_date='';public $comment_date_gmt='';public $comment_content='';
    public $comment_karma=0;public $comment_approved='1';public $comment_agent='';public $comment_type='comment';public $comment_parent=0;public $user_id=0;
    public function __construct($c) { foreach(get_object_vars((object)$c) as $k=>$v)$this->$k=$v; }
    public static function get_instance($id) { return get_comment($id); }
    public function to_array() { return get_object_vars($this); }
    public function get_children($args=[]) { return []; }
}
}
function rrw_wp_cms_comments(): array {
    $d=rrw_wp_cms_dir();$all=rrw_wp_read_json($d.'/comments.json',[]);$out=[];
    foreach($all as $c){ if(!is_array($c)||($c['status']??'')!=='approved')continue;
        $dt=(string)($c['created_at']??date('Y-m-d H:i:s'));
        $out[]=new WP_Comment((object)['comment_ID'=>(int)$c['id'],'comment_post_ID'=>(int)$c['article_id'],'comment_author'=>(string)($c['name']??''),'comment_date'=>$dt,'comment_date_gmt'=>get_gmt_from_date($dt)?:$dt,'comment_content'=>(string)($c['text']??''),'comment_approved'=>'1','comment_parent'=>(int)($c['parent_id']??0),'comment_type'=>'comment','user_id'=>0]); }
    return $out;
}
function get_comments($args='') {
    $a=wp_parse_args($args,['post_id'=>0,'status'=>'all','number'=>'','offset'=>0,'order'=>'DESC','orderby'=>'comment_date_gmt','parent'=>'','count'=>false,'type'=>'','fields'=>'','post__in'=>[],'author_email'=>'','user_id'=>'']);
    $list=rrw_wp_cms_comments();
    global $wpdb;if($wpdb&&$wpdb->ready&&rrw_wp_db_ready()){ foreach((array)$wpdb->get_results("SELECT * FROM {$wpdb->comments} WHERE comment_approved = '1'") as $r)$list[]=new WP_Comment($r); }
    $list=array_values(array_filter($list,function($c) use($a){ if($a['post_id']&&(int)$c->comment_post_ID!==(int)$a['post_id'])return false; if($a['post__in']&&!in_array((int)$c->comment_post_ID,array_map('intval',(array)$a['post__in']),true))return false; if($a['parent']!==''&&(int)$c->comment_parent!==(int)$a['parent'])return false; if($a['type']!==''&&$c->comment_type!==$a['type'])return false; return true; }));
    usort($list,fn($x,$y)=>strtoupper((string)$a['order'])==='ASC'?strcmp($x->comment_date_gmt,$y->comment_date_gmt):strcmp($y->comment_date_gmt,$x->comment_date_gmt));
    if($a['count'])return count($list);
    if($a['number']!=='')$list=array_slice($list,(int)$a['offset'],(int)$a['number']);
    if($a['fields']==='ids')return array_map(fn($c)=>(int)$c->comment_ID,$list);
    return $list;
}
function get_comment($comment=null, $output=OBJECT) { if(is_object($comment))return $comment instanceof WP_Comment?$comment:new WP_Comment($comment);$id=(int)($comment??($GLOBALS['comment']->comment_ID??0));foreach(get_comments() as $c)if((int)$c->comment_ID===$id)return $c;return null; }
function comments_open($post=null) {
    $p=get_post($post);if(!$p)return false;
    if($p->rrw_source==='news'){ $o=!empty(rrw_wp_cms_data()['site']['comments']['enabled']); }
    elseif($p->rrw_source==='page')$o=false;
    else $o=$p->comment_status==='open';
    return (bool)apply_filters('comments_open',$o,$p->ID);
}
function pings_open($post=null) { return false; }
function get_comments_number($post=0) { $p=get_post($post);if(!$p)return 0;return (int)apply_filters('get_comments_number',get_comments(['post_id'=>$p->ID,'count'=>true]),$p->ID); }
function comments_number($zero=false, $one=false, $more=false) { $n=get_comments_number();echo $n===0?($zero!==false?$zero:'Keine Kommentare'):($n===1?($one!==false?$one:'1 Kommentar'):str_replace('%',(string)$n,$more!==false?$more:'% Kommentare')); }
function get_comments_link($post=0) { return get_permalink($post).'#comments'; }
function comments_link($deprecated='', $deprecated2='') { echo esc_url(get_comments_link()); }
function comments_popup_link($zero=false, $one=false, $more=false, $css_class='', $none=false) { echo '<a href="'.esc_url(get_comments_link()).'">';comments_number($zero,$one,$more);echo '</a>'; }
function have_comments() { global $wp_query;return !empty($wp_query->comments)&&$wp_query->comment_count>0; }
function comments_template($file='/comments.php', $separate_comments=false) {
    global $wp_query,$comment,$post;$p=get_post();if(!$p||!(is_single()||is_page()||$wp_query->is_singular))return;
    $comments=get_comments(['post_id'=>$p->ID,'order'=>'ASC']);$wp_query->comments=$comments;$wp_query->comment_count=count($comments);$GLOBALS['comments']=$comments;
    $tpl=locate_template([ltrim($file,'/')]);
    if($tpl==='')$tpl=ABSPATH.'core/default-comments.php';
    $GLOBALS['rrw_wp_comment_post']=$p;load_template($tpl,false);
}
function comment_id_fields($post=null) { $p=get_post($post);echo '<input type="hidden" name="comment_post_ID" value="'.(int)($p->ID??0).'" id="comment_post_ID" /><input type="hidden" name="comment_parent" id="comment_parent" value="0" />'."\n"; }
function cancel_comment_reply_link($text='') { return '<a rel="nofollow" id="cancel-comment-reply-link" href="#respond" style="display:none;">'.esc_html($text?:'Antwort abbrechen').'</a>'; }
function get_cancel_comment_reply_link($text='') { return cancel_comment_reply_link($text); }
function comment_form($args=[], $post_id=null) {
    $p=get_post($post_id);$pid=(int)($p->ID??0);if(!comments_open($pid))return;
    $req=true;
    $fields=['author'=>'<p class="comment-form-author"><label for="author">Name <span class="required">*</span></label> <input id="author" name="author" type="text" value="" size="30" maxlength="245" required /></p>',
        'email'=>'<p class="comment-form-email"><label for="email">E-Mail (wird nicht veröffentlicht)</label> <input id="email" name="email" type="email" value="" size="30" maxlength="100" /></p>',
        'url'=>'<p class="comment-form-url"><label for="url">Website</label> <input id="url" name="url" type="url" value="" size="30" maxlength="200" /></p>'];
    $d=['fields'=>apply_filters('comment_form_default_fields',$fields),'comment_field'=>'<p class="comment-form-comment"><label for="comment">Kommentar <span class="required">*</span></label> <textarea id="comment" name="comment" cols="45" rows="8" maxlength="2000" required></textarea></p>',
        'must_log_in'=>'','logged_in_as'=>'','comment_notes_before'=>'<p class="comment-notes"><span id="email-notes">Deine E-Mail-Adresse wird nicht veröffentlicht.</span></p>','comment_notes_after'=>'','action'=>site_url('/wp-comments-post.php'),'id_form'=>'commentform','id_submit'=>'submit','class_container'=>'comment-respond','class_form'=>'comment-form','class_submit'=>'submit','name_submit'=>'submit',
        'title_reply'=>'Kommentar schreiben','title_reply_to'=>'Antworte auf %s','title_reply_before'=>'<h3 id="reply-title" class="comment-reply-title">','title_reply_after'=>'</h3>','cancel_reply_before'=>' <small>','cancel_reply_after'=>'</small>','cancel_reply_link'=>'Antwort abbrechen','label_submit'=>'Kommentar abschicken',
        'submit_button'=>'<input name="%1$s" type="submit" id="%2$s" class="%3$s" value="%4$s" />','submit_field'=>'<p class="form-submit">%1$s %2$s</p>','format'=>'html5'];
    $a=wp_parse_args($args,apply_filters('comment_form_defaults',$d));
    do_action('comment_form_before');echo '<div id="respond" class="'.esc_attr($a['class_container']).'">'.$a['title_reply_before'].esc_html($a['title_reply']).$a['title_reply_after'].cancel_comment_reply_link($a['cancel_reply_link']);
    echo '<form action="'.esc_url($a['action']).'" method="post" id="'.esc_attr($a['id_form']).'" class="'.esc_attr($a['class_form']).'">';do_action('comment_form_top');
    echo $a['comment_notes_before'];foreach((array)$a['fields'] as $f)echo $f;echo apply_filters('comment_form_field_comment',$a['comment_field']);echo $a['comment_notes_after'];
    $sub=sprintf($a['submit_button'],esc_attr($a['name_submit']),esc_attr($a['id_submit']),esc_attr($a['class_submit']),esc_attr($a['label_submit']));
    echo sprintf($a['submit_field'],apply_filters('comment_form_submit_button',$sub,$a),get_comment_id_fields_html($pid));
    echo '<p style="display:none"><input type="text" name="hp" value="" tabindex="-1" autocomplete="off" /></p>';do_action('comment_form',$pid);echo '</form></div><!-- #respond -->';do_action('comment_form_after');
}
function get_comment_id_fields_html($pid) { return '<input type="hidden" name="comment_post_ID" value="'.(int)$pid.'" id="comment_post_ID" /><input type="hidden" name="comment_parent" id="comment_parent" value="0" />'; }
function wp_list_comments($args=[], $comments=null) {
    global $wp_query;$a=wp_parse_args($args,['walker'=>null,'max_depth'=>'','style'=>'ul','callback'=>null,'end-callback'=>null,'type'=>'all','reply_text'=>'Antworten','avatar_size'=>32,'reverse_top_level'=>null,'echo'=>true,'short_ping'=>false,'per_page'=>'']);
    $list=$comments??($wp_query->comments??[]);if(!$list)return;
    $walker=$a['walker']?:new Walker_Comment();$tag=$a['style']==='ol'?'ol':($a['style']==='div'?'div':'ul');
    $out=$walker->walk($list,(int)($a['max_depth']?:get_option('thread_comments_depth',5)),$a);$out=apply_filters('wp_list_comments',$out,$a);
    if($a['echo'])echo $out;else return $out;
}
function rrw_wp_default_comment($c, $args, $depth) {
    $tag=($args['style']??'ul')==='div'?'div':'li';
    echo '<'.$tag.' id="comment-'.(int)$c->comment_ID.'" class="comment depth-'.(int)$depth.'"><article class="comment-body"><footer class="comment-meta"><div class="comment-author vcard">'.(($args['avatar_size']??32)?get_avatar($c,(int)$args['avatar_size']):'').'<b class="fn">'.esc_html($c->comment_author).'</b></div><div class="comment-metadata"><a href="'.esc_url(get_comment_link($c)).'"><time datetime="'.esc_attr(mysql2date('c',$c->comment_date_gmt)).'">'.esc_html(get_comment_date('',$c).' '.get_comment_time('',false,false,$c)).'</time></a></div></footer><div class="comment-content">'.wpautop(esc_html($c->comment_content)).'</div></article>';
}
function get_comment_ID() { return (int)($GLOBALS['comment']->comment_ID??0); }
function comment_ID() { echo get_comment_ID(); }
function get_comment_link($comment=null, $args=[]) { $c=get_comment($comment);return $c?get_permalink((int)$c->comment_post_ID).'#comment-'.(int)$c->comment_ID:''; }
function get_comment_author($c=0) { $x=get_comment($c?:null);return apply_filters('get_comment_author',$x?$x->comment_author:'',$x?$x->comment_ID:0,$x); }
function comment_author($c=0) { echo apply_filters('comment_author',get_comment_author($c)); }
function get_comment_author_url($c=0) { $x=get_comment($c?:null);return $x?$x->comment_author_url:''; }
function get_comment_author_link($c=0) { $u=get_comment_author_url($c);$a=get_comment_author($c);return $u?'<a href="'.esc_url($u).'" rel="external nofollow ugc" class="url">'.esc_html($a).'</a>':esc_html($a); }
function comment_author_link($c=0) { echo get_comment_author_link($c); }
function get_comment_author_email($c=0) { $x=get_comment($c?:null);return $x?$x->comment_author_email:''; }
function get_comment_text($c=0, $args=[]) { $x=get_comment($c?:null);return $x?apply_filters('get_comment_text',$x->comment_content,$x,$args):''; }
function comment_text($c=0, $args=[]) { echo apply_filters('comment_text',get_comment_text($c,$args),get_comment($c?:null),$args); }
function get_comment_date($format='', $c=0) { $x=is_object($c)?$c:get_comment($c?:null);return $x?mysql2date($format!==''?$format:get_option('date_format','d.m.Y'),$x->comment_date):''; }
function comment_date($format='', $c=0) { echo get_comment_date($format,$c); }
function get_comment_time($format='', $gmt=false, $translate=true, $c=null) { $x=is_object($c)?$c:get_comment($c);return $x?mysql2date($format!==''?$format:get_option('time_format','H:i'),$gmt?$x->comment_date_gmt:$x->comment_date):''; }
function comment_time($format='') { echo get_comment_time($format); }
function get_comment_class($class='', $comment_id=null, $post=null) { $c=['comment'];if($class)$c=array_merge($c,(array)$class);return apply_filters('comment_class',$c,$class,$comment_id,$post); }
function comment_class($class='', $comment_id=null, $post=null, $display=true) { $s='class="'.esc_attr(implode(' ',get_comment_class($class,$comment_id,$post))).'"';if($display)echo $s;else return $s; }
function get_comment_reply_link($args=[], $comment=null, $post=null) { return ''; }
function comment_reply_link($args=[], $comment=null, $post=null) { echo get_comment_reply_link($args,$comment,$post); }
function get_comment_type($c=0) { return 'comment'; }
function comment_type() { echo 'Kommentar'; }
function wp_count_comments($post_id=0) { $n=get_comments(['post_id'=>$post_id,'count'=>true]);return (object)['approved'=>$n,'moderated'=>0,'spam'=>0,'trash'=>0,'total_comments'=>$n,'all'=>$n,'post-trashed'=>0]; }
function get_option_comment_dummy() {}
function wp_new_comment($commentdata, $wp_error=false) { return rrw_wp_submit_comment($commentdata); }
/** Kommentar in den CMS-Speicher schreiben (gleiche Regeln wie die CMS-Schnittstelle). @return array|WP_Error */
function rrw_wp_submit_comment(array $in) {
    $dir=rrw_wp_cms_dir();$site=rrw_wp_read_json($dir.'/site.json',[]);$cfg=(array)($site['comments']??[]);
    if(empty($cfg['enabled']))return new WP_Error('comments_closed','Kommentare sind derzeit deaktiviert.');
    if(trim((string)($in['hp']??''))!=='')return ['ok'=>true,'pending'=>false];
    $pid=(int)($in['comment_post_ID']??0);$p=rrw_wp_cms_find_post($pid);if(!$p||$p->rrw_source!=='news'||$p->post_status!=='publish')return new WP_Error('no_post','Beitrag nicht gefunden.');
    $name=mb_substr(trim((string)($in['author']??'')),0,80);$text=mb_substr(trim(strip_tags((string)($in['comment']??''))),0,2000);
    if($name===''||$text==='')return new WP_Error('missing','Bitte Name und Kommentar ausfüllen.');
    if(!rrw_wp_rate_ok('comment|'.($_SERVER['REMOTE_ADDR']??''),5,300))return new WP_Error('rate','Bitte warte einen Moment, bevor du erneut kommentierst.');
    $file=$dir.'/comments.json';$h=@fopen($dir.'/.wp-comments.lock','c');if($h)@flock($h,LOCK_EX);
    try{
        $all=rrw_wp_read_json($file,[]);$id=1;foreach($all as $c)$id=max($id,(int)($c['id']??0)+1);
        $parent=(int)($in['comment_parent']??0);$byId=[];foreach($all as $c)$byId[(int)$c['id']]=$c;
        while($parent&&isset($byId[$parent])&&!empty($byId[$parent]['parent_id']))$parent=(int)$byId[$parent]['parent_id'];
        if($parent&&(!isset($byId[$parent])||(int)$byId[$parent]['article_id']!==$pid))$parent=0;
        $pending=!array_key_exists('require_approval',$cfg)||!empty($cfg['require_approval']);
        $all[]=['id'=>$id,'article_id'=>$pid,'parent_id'=>$parent,'name'=>$name,'text'=>$text,'is_staff'=>false,'status'=>$pending?'pending':'approved','created_at'=>date('Y-m-d H:i:s')];
        $tmp=$file.'.'.bin2hex(random_bytes(3)).'.tmp';file_put_contents($tmp,json_encode($all,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");rename($tmp,$file);rrw_wp_cms_reset();
    } finally { if($h){@flock($h,LOCK_UN);@fclose($h);} }
    return ['ok'=>true,'pending'=>$pending,'id'=>$id];
}
function rrw_wp_rate_ok(string $key, int $max, int $window): bool {
    $f=rtrim(RRW_WP_DATA,'/').'/ratelimit.json';$now=time();$d=is_file($f)?json_decode((string)file_get_contents($f),true):[];$d=is_array($d)?$d:[];$k=hash('sha256',$key);
    $mine=array_values(array_filter((array)($d[$k]??[]),fn($t)=>$t>$now-$window));if(count($mine)>=$max)return false;$mine[]=$now;$d[$k]=$mine;
    foreach($d as $kk=>$ts){ $d[$kk]=array_values(array_filter((array)$ts,fn($t)=>$t>$now-86400));if(!$d[$kk])unset($d[$kk]); }
    if(!is_dir(RRW_WP_DATA)){@mkdir(RRW_WP_DATA,0775,true);rrw_wp_protect_dir(RRW_WP_DATA);}@file_put_contents($f,json_encode($d),LOCK_EX);return true;
}

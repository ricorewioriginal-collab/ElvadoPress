<?php
// Seitenleisten (register_sidebar, dynamic_sidebar) und die Standard-Widgets von WordPress.
$GLOBALS['wp_registered_sidebars']=$GLOBALS['wp_registered_sidebars']??[];

function register_sidebar($args=[]) {
    $i=count($GLOBALS['wp_registered_sidebars'])+1;$id=$args['id']??'sidebar-'.$i;
    $d=['name'=>'Seitenleiste '.$i,'id'=>$id,'description'=>'','class'=>'','before_widget'=>'<li id="%1$s" class="widget %2$s">','after_widget'=>"</li>\n",'before_title'=>'<h2 class="widgettitle">','after_title'=>"</h2>\n",'before_sidebar'=>'','after_sidebar'=>'','show_in_rest'=>false];
    $GLOBALS['wp_registered_sidebars'][$id]=wp_parse_args($args,$d);do_action('register_sidebar',$GLOBALS['wp_registered_sidebars'][$id]);return $id;
}
function register_sidebars($number=1, $args=[]) { $first=null;for($i=1;$i<=$number;$i++){ $a=$args;if($number>1){ $a['name']=sprintf($args['name']??'Seitenleiste %d',$i);if(isset($args['id']))$a['id']=$args['id'].'-'.$i; } $id=register_sidebar($a);$first=$first??$id; }return $first; }
function unregister_sidebar($id) { unset($GLOBALS['wp_registered_sidebars'][$id]); }
function elvado_wp_default_sidebars_widgets(): array { return ['sidebar-1'=>['search-2','recent-posts-2','recent-comments-2','archives-2','categories-2','meta-2']]; }
function wp_get_sidebars_widgets($deprecated=true) {
    $s=get_option('sidebars_widgets',null);
    if(!is_array($s)||!$s){ $s=elvado_wp_default_sidebars_widgets(); }
    foreach($GLOBALS['wp_registered_sidebars'] as $id=>$_)if(!isset($s[$id]))$s[$id]=[];
    return $s;
}
function wp_set_sidebars_widgets($s) { update_option('sidebars_widgets',$s); }
function elvado_wp_sidebar_index($index) { if(is_int($index))$index="sidebar-$index";else{ $index=sanitize_title($index);foreach($GLOBALS['wp_registered_sidebars'] as $id=>$s)if(sanitize_title($s['name'])===$index){$index=$id;break;} }return $index; }
function is_active_sidebar($index) { $index=elvado_wp_sidebar_index($index);$s=wp_get_sidebars_widgets();$has=!empty($s[$index])&&isset($GLOBALS['wp_registered_sidebars'][$index]);return (bool)apply_filters('is_active_sidebar',$has,$index); }
function elvado_wp_widget_instance(string $widget_id) {
    if(!preg_match('/^(.+)-(\d+)$/',$widget_id,$m))return null;[$_,$base,$num]=$m;$num=(int)$num;
    foreach($GLOBALS['wp_widget_factory']->widgets as $cls=>$w)if($w->id_base===$base){
        $opts=get_option('widget_'.$base,null);$inst=is_array($opts)&&isset($opts[$num])?$opts[$num]:(elvado_wp_default_widget_instance($base));
        $w->_set($num);return [$w,$inst];
    }
    return null;
}
function elvado_wp_default_widget_instance(string $base): array { return match($base){'recent-posts'=>['title'=>'Neueste Beiträge','number'=>5],'recent-comments'=>['title'=>'Neueste Kommentare','number'=>5],'archives'=>['title'=>'Archiv'],'categories'=>['title'=>'Kategorien'],'meta'=>['title'=>'Meta'],'search'=>['title'=>''],default=>[]}; }
function dynamic_sidebar($index=1) {
    $index=elvado_wp_sidebar_index($index);$sb=$GLOBALS['wp_registered_sidebars'][$index]??null;if(!$sb)return false;
    $widgets=wp_get_sidebars_widgets()[$index]??[];if(!$widgets)return false;
    do_action('dynamic_sidebar_before',$index,true);$any=false;
    if($sb['before_sidebar'])echo sprintf($sb['before_sidebar'],'',''); 
    foreach($widgets as $wid){
        $r=elvado_wp_widget_instance($wid);if(!$r)continue;[$w,$inst]=$r;
        $cls=$w->widget_options['classname']??$w->id_base;
        $args=array_merge($sb,['widget_id'=>$wid,'widget_name'=>$w->name,'before_widget'=>sprintf($sb['before_widget'],$wid,$cls),'before_title'=>$sb['before_title'],'after_title'=>$sb['after_title']]);
        do_action('dynamic_sidebar',['id'=>$wid,'name'=>$w->name]);
        $w->display_callback($args,$inst);$any=true;
    }
    if($sb['after_sidebar'])echo $sb['after_sidebar'];
    do_action('dynamic_sidebar_after',$index,true);return $any;
}
function wp_get_sidebar($id) { return $GLOBALS['wp_registered_sidebars'][$id]??null; }
function is_registered_sidebar($id) { return isset($GLOBALS['wp_registered_sidebars'][$id]); }
function wp_register_sidebar_widget($id,$name,$cb,$options=[]) { return true; }
function wp_unregister_sidebar_widget($id) {}
function is_active_widget($callback=false, $widget_id=false, $id_base=false, $skip_inactive=true) { foreach(wp_get_sidebars_widgets() as $ws)foreach($ws as $w)if($id_base&&str_starts_with($w,$id_base.'-'))return true;return false; }
function get_dynamic_sidebar_dummy() {}

/* Standard-Widgets */
class WP_Widget_Search extends WP_Widget {
    public function __construct() { parent::__construct('search','Suche',['classname'=>'widget_search']); }
    public function widget($args,$i) { echo $args['before_widget'];$t=apply_filters('widget_title',$i['title']??'',$i,$this->id_base);if($t)echo $args['before_title'].$t.$args['after_title'];get_search_form();echo $args['after_widget']; }
    public function form($i) { echo '<p><label>Titel: <input class="widefat" name="'.esc_attr($this->get_field_name('title')).'" type="text" value="'.esc_attr($i['title']??'').'" /></label></p>'; }
    public function update($n,$o) { return ['title'=>sanitize_text_field($n['title']??'')]; }
}
class WP_Widget_Recent_Posts extends WP_Widget {
    public function __construct() { parent::__construct('recent-posts','Neueste Beiträge',['classname'=>'widget_recent_entries']); }
    public function widget($args,$i) {
        $n=max(1,(int)($i['number']??5));$q=new WP_Query(['posts_per_page'=>$n,'no_found_rows'=>true,'post_status'=>'publish','ignore_sticky_posts'=>true]);if(!$q->posts)return;
        echo $args['before_widget'];$t=apply_filters('widget_title',$i['title']??'Neueste Beiträge',$i,$this->id_base);if($t)echo $args['before_title'].$t.$args['after_title'];
        echo '<ul>';foreach($q->posts as $p){ echo '<li><a href="'.esc_url(get_permalink($p)).'">'.esc_html(get_the_title($p)).'</a>'.(!empty($i['show_date'])?' <span class="post-date">'.esc_html(get_the_date('',$p)).'</span>':'').'</li>'; }echo '</ul>'.$args['after_widget'];
    }
    public function form($i) { echo '<p><label>Titel: <input class="widefat" name="'.esc_attr($this->get_field_name('title')).'" type="text" value="'.esc_attr($i['title']??'').'" /></label></p><p><label>Anzahl: <input class="tiny-text" name="'.esc_attr($this->get_field_name('number')).'" type="number" min="1" max="20" value="'.(int)($i['number']??5).'" /></label></p><p><label><input type="checkbox" name="'.esc_attr($this->get_field_name('show_date')).'" value="1" '.(!empty($i['show_date'])?'checked':'').' /> Datum anzeigen</label></p>'; }
    public function update($n,$o) { return ['title'=>sanitize_text_field($n['title']??''),'number'=>max(1,min(20,(int)($n['number']??5))),'show_date'=>!empty($n['show_date'])]; }
}
class WP_Widget_Recent_Comments extends WP_Widget {
    public function __construct() { parent::__construct('recent-comments','Neueste Kommentare',['classname'=>'widget_recent_comments']); }
    public function widget($args,$i) {
        $c=get_comments(['number'=>max(1,(int)($i['number']??5)),'status'=>'approve']);
        echo $args['before_widget'];$t=apply_filters('widget_title',$i['title']??'Neueste Kommentare',$i,$this->id_base);if($t)echo $args['before_title'].$t.$args['after_title'];
        echo '<ul id="recentcomments">';foreach($c as $cm)echo '<li class="recentcomments"><span class="comment-author-link">'.esc_html($cm->comment_author).'</span> zu <a href="'.esc_url(get_comment_link($cm)).'">'.esc_html(get_the_title((int)$cm->comment_post_ID)).'</a></li>';
        echo '</ul>'.$args['after_widget'];
    }
    public function update($n,$o) { return ['title'=>sanitize_text_field($n['title']??''),'number'=>max(1,(int)($n['number']??5))]; }
}
class WP_Widget_Archives extends WP_Widget {
    public function __construct() { parent::__construct('archives','Archiv',['classname'=>'widget_archive']); }
    public function widget($args,$i) { echo $args['before_widget'];$t=apply_filters('widget_title',$i['title']??'Archiv',$i,$this->id_base);if($t)echo $args['before_title'].$t.$args['after_title'];echo '<ul>';wp_get_archives(['type'=>'monthly','show_post_count'=>!empty($i['count'])]);echo '</ul>'.$args['after_widget']; }
    public function update($n,$o) { return ['title'=>sanitize_text_field($n['title']??''),'count'=>!empty($n['count'])]; }
}
class WP_Widget_Categories extends WP_Widget {
    public function __construct() { parent::__construct('categories','Kategorien',['classname'=>'widget_categories']); }
    public function widget($args,$i) {
        $cats=get_categories(['hide_empty'=>true]);if(!$cats)return;echo $args['before_widget'];$t=apply_filters('widget_title',$i['title']??'Kategorien',$i,$this->id_base);if($t)echo $args['before_title'].$t.$args['after_title'];
        echo '<ul>';foreach($cats as $c)echo '<li class="cat-item cat-item-'.$c->term_id.'"><a href="'.esc_url(get_category_link($c)).'">'.esc_html($c->name).'</a>'.(!empty($i['count'])?' ('.(int)$c->count.')':'').'</li>';echo '</ul>'.$args['after_widget'];
    }
    public function update($n,$o) { return ['title'=>sanitize_text_field($n['title']??''),'count'=>!empty($n['count'])]; }
}
class WP_Widget_Tag_Cloud extends WP_Widget {
    public function __construct() { parent::__construct('tag_cloud','Schlagwort-Wolke',['classname'=>'widget_tag_cloud']); }
    public function widget($args,$i) { $tags=get_tags(['hide_empty'=>true]);if(!$tags)return;echo $args['before_widget'];$t=apply_filters('widget_title',$i['title']??'Schlagwörter',$i,$this->id_base);if($t)echo $args['before_title'].$t.$args['after_title'];echo '<div class="tagcloud">';foreach($tags as $tg)echo '<a href="'.esc_url(get_tag_link($tg)).'" class="tag-cloud-link">'.esc_html($tg->name).'</a> ';echo '</div>'.$args['after_widget']; }
    public function update($n,$o) { return ['title'=>sanitize_text_field($n['title']??'')]; }
}
class WP_Widget_Meta extends WP_Widget {
    public function __construct() { parent::__construct('meta','Meta',['classname'=>'widget_meta']); }
    public function widget($args,$i) { echo $args['before_widget'];$t=apply_filters('widget_title',$i['title']??'Meta',$i,$this->id_base);if($t)echo $args['before_title'].$t.$args['after_title'];echo '<ul><li><a href="'.esc_url(get_feed_link()).'">Beitrags-Feed (RSS)</a></li></ul>'.$args['after_widget']; }
    public function update($n,$o) { return ['title'=>sanitize_text_field($n['title']??'')]; }
}
class WP_Widget_Pages extends WP_Widget {
    public function __construct() { parent::__construct('pages','Seiten',['classname'=>'widget_pages']); }
    public function widget($args,$i) { $o=wp_list_pages(['title_li'=>'','echo'=>0]);if(!$o)return;echo $args['before_widget'];$t=apply_filters('widget_title',$i['title']??'Seiten',$i,$this->id_base);if($t)echo $args['before_title'].$t.$args['after_title'];echo '<ul>'.$o.'</ul>'.$args['after_widget']; }
    public function update($n,$o) { return ['title'=>sanitize_text_field($n['title']??'')]; }
}
class WP_Widget_Text extends WP_Widget {
    public function __construct() { parent::__construct('text','Text',['classname'=>'widget_text']); }
    public function widget($args,$i) { $t=apply_filters('widget_title',$i['title']??'',$i,$this->id_base);$txt=apply_filters('widget_text',$i['text']??'',$i,$this);echo $args['before_widget'];if($t)echo $args['before_title'].$t.$args['after_title'];echo '<div class="textwidget">'.(!empty($i['filter'])?wpautop($txt):$txt).'</div>'.$args['after_widget']; }
    public function form($i) { echo '<p><label>Titel: <input class="widefat" name="'.esc_attr($this->get_field_name('title')).'" type="text" value="'.esc_attr($i['title']??'').'" /></label></p><p><textarea class="widefat" rows="8" name="'.esc_attr($this->get_field_name('text')).'">'.esc_textarea($i['text']??'').'</textarea></p>'; }
    public function update($n,$o) { return ['title'=>sanitize_text_field($n['title']??''),'text'=>current_user_can('unfiltered_html')?(string)($n['text']??''):wp_kses_post((string)($n['text']??'')),'filter'=>!empty($n['filter'])]; }
}
class WP_Widget_Custom_HTML extends WP_Widget {
    public function __construct() { parent::__construct('custom_html','Eigenes HTML',['classname'=>'widget_custom_html']); }
    public function widget($args,$i) { $t=apply_filters('widget_title',$i['title']??'',$i,$this->id_base);echo $args['before_widget'];if($t)echo $args['before_title'].$t.$args['after_title'];echo '<div class="textwidget custom-html-widget">'.apply_filters('widget_text',$i['content']??'',$i,$this).'</div>'.$args['after_widget']; }
    public function update($n,$o) { return ['title'=>sanitize_text_field($n['title']??''),'content'=>current_user_can('unfiltered_html')?(string)($n['content']??''):wp_kses_post((string)($n['content']??''))]; }
}
class WP_Nav_Menu_Widget extends WP_Widget {
    public function __construct() { parent::__construct('nav_menu','Navigationsmenü',['classname'=>'widget_nav_menu']); }
    public function widget($args,$i) { $m=$i['nav_menu']??'top';echo $args['before_widget'];$t=apply_filters('widget_title',$i['title']??'',$i,$this->id_base);if($t)echo $args['before_title'].$t.$args['after_title'];wp_nav_menu(['menu'=>$m,'fallback_cb'=>'']);echo $args['after_widget']; }
    public function update($n,$o) { return ['title'=>sanitize_text_field($n['title']??''),'nav_menu'=>in_array($n['nav_menu']??'top',['top','bottom'],true)?$n['nav_menu']:'top']; }
}
function elvado_wp_register_core_widgets() {
    foreach(['WP_Widget_Search','WP_Widget_Recent_Posts','WP_Widget_Recent_Comments','WP_Widget_Archives','WP_Widget_Categories','WP_Widget_Tag_Cloud','WP_Widget_Meta','WP_Widget_Pages','WP_Widget_Text','WP_Widget_Custom_HTML','WP_Nav_Menu_Widget'] as $c)register_widget($c);
}

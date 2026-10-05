<?php
// Ergänzende WordPress-Funktionen (Kern 2, Teil 12): weitere Widget-Klassen (Medien, Links, Kalender, RSS, Block) und die Customizer-Widget-Verwaltung.
// Eigenständig umgesetzt; die Widgets sind nutzbar (register_widget / the_widget), werden aber nicht selbsttätig registriert.

/* ───────── Medien-Widgets ───────── */
if(!class_exists('WP_Widget_Media')){
abstract class WP_Widget_Media extends WP_Widget {
    public $l10n=[];
    public function __construct($id_base, $name, $widget_options=[], $control_options=[]) {
        $o=wp_parse_args($widget_options,['description'=>'Ein Medien-Element.','customize_selective_refresh'=>true,'show_instance_in_rest'=>true,'mime_type'=>'']);
        parent::__construct($id_base,$name,$o,wp_parse_args($control_options,[]));
    }
    public function get_instance_schema() {
        return apply_filters("widget_{$this->id_base}_instance_schema",['attachment_id'=>['type'=>'integer','default'=>0,'minimum'=>0,'description'=>'Anhangs-ID'],'url'=>['type'=>'string','default'=>'','format'=>'uri','description'=>'Adresse der Datei'],'title'=>['type'=>'string','default'=>'','sanitize_callback'=>'sanitize_text_field','description'=>'Titel']],$this);
    }
    public function display_media_state($states, $post=null) { return $states; }
    public function is_attachment_with_mime_type($attachment, $mime_type) {
        if(empty($attachment))return false;$a=get_post($attachment);
        if(!$a||'attachment'!==$a->post_type)return false;
        return wp_attachment_is($mime_type,$a);
    }
    public function has_content($instance) { return !empty($instance['attachment_id'])||!empty($instance['url']); }
    public function update($new_instance, $old_instance) {
        $schema=$this->get_instance_schema();$inst=(array)$old_instance;
        foreach($schema as $field=>$s){
            if(!array_key_exists($field,$new_instance))continue;$v=$new_instance[$field];
            if(isset($s['sanitize_callback'])&&is_callable($s['sanitize_callback'])){ $inst[$field]=call_user_func($s['sanitize_callback'],$v);continue; }
            switch($s['type']??'string'){
                case 'integer': $n=(int)$v;if(isset($s['minimum'])&&$n<$s['minimum'])continue 2;$inst[$field]=$n;break;
                case 'boolean': $inst[$field]=(bool)$v;break;
                case 'array': $inst[$field]=array_map('absint',(array)$v);break;
                default:
                    $v=is_scalar($v)?(string)$v:'';
                    if(isset($s['enum'])&&!in_array($v,$s['enum'],true))continue 2;
                    $inst[$field]=($s['format']??'')==='uri'?esc_url_raw($v):sanitize_text_field($v);
            }
        }
        return $inst;
    }
    public function widget($args, $instance) {
        $instance=wp_parse_args($instance,wp_list_pluck($this->get_instance_schema(),'default'));
        $instance=apply_filters("widget_{$this->id_base}_instance",$instance,$args,$this);
        if(!$this->has_content($instance))return;
        $title=apply_filters('widget_title',$instance['title']??'',$instance,$this->id_base);
        echo $args['before_widget'];if($title)echo $args['before_title'].$title.$args['after_title'];
        echo $this->render_media($instance);echo $args['after_widget'];
    }
    abstract public function render_media($instance);
    public function form($instance) {
        $i=wp_parse_args($instance,wp_list_pluck($this->get_instance_schema(),'default'));
        echo '<p><label for="'.esc_attr($this->get_field_id('title')).'">Titel:</label><input class="widefat" id="'.esc_attr($this->get_field_id('title')).'" name="'.esc_attr($this->get_field_name('title')).'" type="text" value="'.esc_attr($i['title']).'" /></p>';
        echo '<p><label for="'.esc_attr($this->get_field_id('url')).'">Adresse:</label><input class="widefat" id="'.esc_attr($this->get_field_id('url')).'" name="'.esc_attr($this->get_field_name('url')).'" type="url" value="'.esc_attr($i['url']).'" /></p>';
        return 'form';
    }
    public function enqueue_admin_scripts() {} public function render_control_template_scripts() {} public function enqueue_preview_scripts() {}
}
}
if(!class_exists('WP_Widget_Media_Image')){
class WP_Widget_Media_Image extends WP_Widget_Media {
    public function __construct() { parent::__construct('media_image','Bild',['description'=>'Zeigt ein Bild an.','mime_type'=>'image']); }
    public function get_instance_schema() {
        return array_merge(parent::get_instance_schema(),['size'=>['type'=>'string','default'=>'medium'],'width'=>['type'=>'integer','default'=>0,'minimum'=>0],'height'=>['type'=>'integer','default'=>0,'minimum'=>0],'caption'=>['type'=>'string','default'=>'','sanitize_callback'=>'wp_kses_post'],'alt'=>['type'=>'string','default'=>'','sanitize_callback'=>'sanitize_text_field'],
            'link_type'=>['type'=>'string','enum'=>['custom','file','post','none'],'default'=>'custom'],'link_url'=>['type'=>'string','default'=>'','format'=>'uri'],'image_classes'=>['type'=>'string','default'=>'','sanitize_callback'=>'sanitize_text_field'],'link_classes'=>['type'=>'string','default'=>'','sanitize_callback'=>'sanitize_text_field'],'link_rel'=>['type'=>'string','default'=>'','sanitize_callback'=>'sanitize_text_field'],'link_target_blank'=>['type'=>'boolean','default'=>false],'image_title'=>['type'=>'string','default'=>'','sanitize_callback'=>'sanitize_text_field']]);
    }
    public function render_media($instance) {
        $i=wp_parse_args($instance,wp_list_pluck($this->get_instance_schema(),'default'));$id=(int)$i['attachment_id'];
        $attr=['class'=>trim('image '.$i['image_classes'])];if($i['alt']!=='')$attr['alt']=$i['alt'];if($i['image_title']!=='')$attr['title']=$i['image_title'];
        if($id){ $img=wp_get_attachment_image($id,$i['size']==='custom'?[(int)$i['width'],(int)$i['height']]:$i['size'],false,$attr); }
        else{ $img='<img src="'.esc_url($i['url']).'" class="'.esc_attr($attr['class']).'" alt="'.esc_attr($i['alt']).'"'.($i['width']?' width="'.(int)$i['width'].'"':'').($i['height']?' height="'.(int)$i['height'].'"':'').' />'; }
        if(!$img)return '';
        $href='';switch($i['link_type']){ case 'file': $href=$id?(string)wp_get_attachment_url($id):$i['url'];break; case 'post': $href=$id?(string)get_attachment_link($id):'';break; case 'custom': $href=$i['link_url'];break; }
        if($href!==''){ $img='<a class="'.esc_attr($i['link_classes']).'" href="'.esc_url($href).'"'.($i['link_target_blank']?' target="_blank"':'').($i['link_rel']!==''?' rel="'.esc_attr($i['link_rel']).'"':'').'>'.$img.'</a>'; }
        if($i['caption']!=='')$img='<figure class="wp-caption">'.$img.'<figcaption class="wp-caption-text">'.wp_kses_post($i['caption']).'</figcaption></figure>';
        return $img;
    }
}
}
if(!class_exists('WP_Widget_Media_Video')){
class WP_Widget_Media_Video extends WP_Widget_Media {
    public function __construct() { parent::__construct('media_video','Video',['description'=>'Zeigt ein Video an.','mime_type'=>'video']); }
    public function get_instance_schema() { return array_merge(parent::get_instance_schema(),['preload'=>['type'=>'string','enum'=>['none','auto','metadata'],'default'=>'metadata'],'loop'=>['type'=>'boolean','default'=>false],'content'=>['type'=>'string','default'=>'','sanitize_callback'=>'wp_kses_post']]); }
    public function render_media($instance) {
        $i=wp_parse_args($instance,wp_list_pluck($this->get_instance_schema(),'default'));
        $url=!empty($i['attachment_id'])?(string)wp_get_attachment_url((int)$i['attachment_id']):(string)$i['url'];if($url==='')return '';
        if(preg_match('#(youtube\.com|youtu\.be|vimeo\.com)#i',$url)){ $h=wp_oembed_get($url);if($h)return $h; }
        return wp_video_shortcode(['src'=>$url,'preload'=>$i['preload'],'loop'=>$i['loop']?'true':'false'],$i['content']);
    }
}
}
if(!class_exists('WP_Widget_Media_Audio')){
class WP_Widget_Media_Audio extends WP_Widget_Media {
    public function __construct() { parent::__construct('media_audio','Audio',['description'=>'Spielt eine Audiodatei ab.','mime_type'=>'audio']); }
    public function get_instance_schema() { return array_merge(parent::get_instance_schema(),['preload'=>['type'=>'string','enum'=>['none','auto','metadata'],'default'=>'none'],'loop'=>['type'=>'boolean','default'=>false]]); }
    public function render_media($instance) {
        $i=wp_parse_args($instance,wp_list_pluck($this->get_instance_schema(),'default'));
        $url=!empty($i['attachment_id'])?(string)wp_get_attachment_url((int)$i['attachment_id']):(string)$i['url'];if($url==='')return '';
        return wp_audio_shortcode(['src'=>$url,'preload'=>$i['preload'],'loop'=>$i['loop']?'true':'false']);
    }
}
}
if(!class_exists('WP_Widget_Media_Gallery')){
class WP_Widget_Media_Gallery extends WP_Widget_Media {
    public function __construct() { parent::__construct('media_gallery','Galerie',['description'=>'Zeigt eine Bildergalerie an.','mime_type'=>'image']); }
    public function get_instance_schema() {
        return ['title'=>['type'=>'string','default'=>'','sanitize_callback'=>'sanitize_text_field'],'ids'=>['type'=>'array','items'=>['type'=>'integer'],'default'=>[]],'columns'=>['type'=>'integer','default'=>3,'minimum'=>1,'maximum'=>9],'size'=>['type'=>'string','default'=>'thumbnail'],'link_type'=>['type'=>'string','enum'=>['post','file','none'],'default'=>'post'],'orderby_random'=>['type'=>'boolean','default'=>false]];
    }
    public function has_content($instance) { return !empty($instance['ids']); }
    public function render_media($instance) {
        $i=wp_parse_args($instance,wp_list_pluck($this->get_instance_schema(),'default'));$ids=array_filter(array_map('absint',(array)$i['ids']));if(!$ids)return '';
        $a=['ids'=>implode(',',$ids),'columns'=>(int)$i['columns'],'size'=>$i['size'],'link'=>$i['link_type']==='post'?'':$i['link_type']];
        if($i['orderby_random'])$a['orderby']='rand';
        return gallery_shortcode($a);
    }
}
}

/* ───────── Links, Kalender, RSS, Block ───────── */
if(!class_exists('WP_Widget_Links')){
class WP_Widget_Links extends WP_Widget {
    public function __construct() { parent::__construct('links','Links',['description'=>'Ihre Blogroll.','classname'=>'widget_links']); }
    public function widget($args, $instance) {
        $show_desc=!empty($instance['description']);$show_name=!empty($instance['name']);$show_rating=!empty($instance['rating']);$show_images=!isset($instance['images'])||$instance['images'];
        $cat=$instance['category']??false;$order=($instance['orderby']??'name')==='rating'?'DESC':'ASC';
        $before=$args['before_widget'];
        if(!empty($cat)&&class_exists('WP_Term')){ $c=get_term((int)$cat,'link_category');$cls=$c?$c->slug:'';$before=str_replace('widget_links','widget_links linkcat-'.$cls,$before); }
        $title=apply_filters('widget_title',$instance['title']??'Links',$instance,$this->id_base);
        $out=wp_list_bookmarks(apply_filters('widget_links_args',['title_li'=>'','title_before'=>$args['before_title'],'title_after'=>$args['after_title'],'category_before'=>'','category_after'=>'','show_images'=>$show_images,'show_description'=>$show_desc,'show_name'=>$show_name,'show_rating'=>$show_rating,'category'=>$cat,'class'=>'linkcat widget','orderby'=>$instance['orderby']??'name','order'=>$order,'limit'=>$instance['limit']??-1,'echo'=>0],$instance));
        if(!$out)return;
        echo $before;if($title&&!empty($instance['title']))echo $args['before_title'].$title.$args['after_title'];
        echo '<ul class="xoxo blogroll">'.$out.'</ul>'.$args['after_widget'];
    }
    public function update($new_instance, $old_instance) {
        $i=(array)$old_instance;$i['title']=sanitize_text_field($new_instance['title']??'');
        foreach(['description','rating','images','name'] as $k)$i[$k]=!empty($new_instance[$k])?1:0;
        $i['category']=(int)($new_instance['category']??0);$i['orderby']=in_array($new_instance['orderby']??'name',['name','rating','id','random'],true)?$new_instance['orderby']:'name';
        $i['limit']=!empty($new_instance['limit'])?(int)$new_instance['limit']:-1;
        return $i;
    }
    public function form($instance) {
        $i=wp_parse_args((array)$instance,['title'=>'','category'=>false,'orderby'=>'name','limit'=>-1]);
        echo '<p><label for="'.esc_attr($this->get_field_id('title')).'">Titel:</label><input class="widefat" id="'.esc_attr($this->get_field_id('title')).'" name="'.esc_attr($this->get_field_name('title')).'" type="text" value="'.esc_attr($i['title']).'" /></p>';
    }
}
}
if(!class_exists('WP_Widget_Calendar')){
class WP_Widget_Calendar extends WP_Widget {
    private static $instance=0;
    public function __construct() { parent::__construct('calendar','Kalender',['classname'=>'widget_calendar','description'=>'Ein Kalender mit den Beiträgen Ihrer Website.','customize_selective_refresh'=>true,'show_instance_in_rest'=>true]); }
    public function widget($args, $instance) {
        $title=apply_filters('widget_title',$instance['title']??'',$instance,$this->id_base);
        echo $args['before_widget'];if($title)echo $args['before_title'].$title.$args['after_title'];
        if(0===self::$instance)echo '<div id="calendar_wrap" class="calendar_wrap">';else echo '<div class="calendar_wrap">';
        get_calendar(true,true);echo '</div>'.$args['after_widget'];self::$instance++;
    }
    public function update($new_instance, $old_instance) { $i=(array)$old_instance;$i['title']=sanitize_text_field($new_instance['title']??'');return $i; }
    public function form($instance) { $t=(string)(((array)$instance)['title']??'');echo '<p><label for="'.esc_attr($this->get_field_id('title')).'">Titel:</label><input class="widefat" id="'.esc_attr($this->get_field_id('title')).'" name="'.esc_attr($this->get_field_name('title')).'" type="text" value="'.esc_attr($t).'" /></p>'; }
}
}
if(!class_exists('WP_Widget_RSS')){
class WP_Widget_RSS extends WP_Widget {
    public function __construct() { parent::__construct('rss','RSS',['description'=>'Einträge eines beliebigen RSS- oder Atom-Feeds.','customize_selective_refresh'=>true,'show_instance_in_rest'=>true],['width'=>400,'height'=>200]); }
    public function widget($args, $instance) {
        if(isset($instance['error'])&&$instance['error'])return;
        $url=!empty($instance['url'])?$instance['url']:'';
        while(stristr($url,'http')!==$url)$url=substr($url,1);
        if(empty($url))return;
        $rss=fetch_feed($url);$title=$instance['title']??'';$desc='';$link='';
        if(!is_wp_error($rss)){ $desc=esc_attr(strip_tags(method_exists($rss,'get_description')?(string)$rss->get_description():''));$link=method_exists($rss,'get_permalink')?(string)$rss->get_permalink():'';if(empty($title))$title=strip_tags(method_exists($rss,'get_title')?(string)$rss->get_title():''); }
        $title=apply_filters('widget_title',$title?:'Unbekannter Feed',$instance,$this->id_base);
        echo $args['before_widget'];if($title)echo $args['before_title'].esc_html($title).$args['after_title'];
        wp_widget_rss_output($rss,$instance);echo $args['after_widget'];
        if(!is_wp_error($rss)&&method_exists($rss,'__destruct'))$rss->__destruct();
    }
    public function update($new_instance, $old_instance) {
        $i=(array)$old_instance;
        $i['title']=sanitize_text_field($new_instance['title']??'');$i['url']=esc_url_raw(trim((string)($new_instance['url']??'')));
        $i['items']=min(20,max(1,(int)($new_instance['items']??10)));
        foreach(['show_summary','show_author','show_date'] as $k)$i[$k]=!empty($new_instance[$k])?1:0;
        $i['error']=false;
        if($i['url']!==''){ $r=fetch_feed($i['url']);if(is_wp_error($r))$i['error']=$r->get_error_message(); }
        return $i;
    }
    public function form($instance) {
        $i=wp_parse_args((array)$instance,['title'=>'','url'=>'','items'=>10]);
        echo '<p><label for="'.esc_attr($this->get_field_id('url')).'">Adresse des Feeds:</label><input class="widefat" id="'.esc_attr($this->get_field_id('url')).'" name="'.esc_attr($this->get_field_name('url')).'" type="text" value="'.esc_attr($i['url']).'" /></p>';
        echo '<p><label for="'.esc_attr($this->get_field_id('title')).'">Titel:</label><input class="widefat" id="'.esc_attr($this->get_field_id('title')).'" name="'.esc_attr($this->get_field_name('title')).'" type="text" value="'.esc_attr($i['title']).'" /></p>';
    }
}
}
if(!class_exists('WP_Widget_Block')){
class WP_Widget_Block extends WP_Widget {
    protected $default_instance=['content'=>''];
    public function __construct() { parent::__construct('block','Block',['classname'=>'widget_block','description'=>'Ein Widget mit Blöcken.','show_instance_in_rest'=>true],['width'=>400,'height'=>350]); }
    public function widget($args, $instance) {
        $instance=wp_parse_args($instance,$this->default_instance);
        echo $args['before_widget'];
        echo do_blocks($instance['content']);echo $args['after_widget'];
    }
    public function update($new_instance, $old_instance) {
        $i=(array)$old_instance;
        $i['content']=current_user_can('unfiltered_html')?(string)($new_instance['content']??''):wp_kses_post((string)($new_instance['content']??''));
        return $i;
    }
    public function form($instance) {
        $i=wp_parse_args((array)$instance,$this->default_instance);
        echo '<textarea class="widefat" rows="8" id="'.esc_attr($this->get_field_id('content')).'" name="'.esc_attr($this->get_field_name('content')).'">'.esc_textarea($i['content']).'</textarea>';
    }
}
}

/* ───────── Customizer: Widgets ───────── */
if(!class_exists('WP_Customize_Widgets')){
final class WP_Customize_Widgets {
    public $manager;protected $core_widget_id_bases=['archives','calendar','categories','custom_html','nav_menu','media_audio','media_image','media_video','meta','pages','recent-comments','recent-posts','rss','search','tag_cloud','text'];
    protected $rendered_sidebars=[];protected $rendered_widgets=[];protected $old_sidebars_widgets=[];protected $selective_refreshable_widgets;
    public function __construct($manager) { $this->manager=$manager; }
    public static function get_setting_id($widget_id) { return 'widget_'.$widget_id; }
    public function get_widget_setting_id($widget_id) { return 'widget_'.preg_replace('/-(\d+)$/','[$1]',(string)$widget_id); }
    public function is_widget_selective_refreshable($id_base) {
        $a=$this->get_selective_refreshable_widgets();return !empty($a[$id_base]);
    }
    public function get_selective_refreshable_widgets() {
        if($this->selective_refreshable_widgets===null){ $this->selective_refreshable_widgets=[];
            foreach((array)($GLOBALS['wp_widget_factory']->widgets??[]) as $w)$this->selective_refreshable_widgets[$w->id_base]=!empty($w->widget_options['customize_selective_refresh']); }
        return $this->selective_refreshable_widgets;
    }
    public function is_panel_active() { return !empty($GLOBALS['wp_registered_sidebars']); }
    public function filter_nonces($nonces) { $nonces['update-widget']=wp_create_nonce('update-widget');return $nonces; }
    public function setup_widget_addition_previews() {} public function register_settings() {} public function customize_dynamic_setting_args($args, $id) { return $args; } public function enqueue_scripts() {} public function print_scripts() {} public function output_widget_control_templates() {}
    public function wp_ajax_update_widget() { wp_send_json_error('widget_update_unsupported'); }
}
}

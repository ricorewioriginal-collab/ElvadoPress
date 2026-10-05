<?php
// WP_Post und WP_Term – die Datenobjekte von WordPress.
if(!class_exists('WP_Post')){
#[AllowDynamicProperties]
final class WP_Post {
    public $ID=0;public $post_author='0';public $post_date='0000-00-00 00:00:00';public $post_date_gmt='0000-00-00 00:00:00';public $post_content='';public $post_title='';public $post_excerpt='';
    public $post_status='publish';public $comment_status='open';public $ping_status='open';public $post_password='';public $post_name='';public $to_ping='';public $pinged='';
    public $post_modified='0000-00-00 00:00:00';public $post_modified_gmt='0000-00-00 00:00:00';public $post_content_filtered='';public $post_parent=0;public $guid='';public $menu_order=0;
    public $post_type='post';public $post_mime_type='';public $comment_count=0;public $filter;
    /** Herkunft: db (Tabelle wp_posts) | news (CMS-Beitrag) | page (CMS-Seite) – nur für die Laufzeit, wird nicht gespeichert. */
    public $rrw_source='db';
    public static function get_instance($post_id) { return get_post((int)$post_id) ?: false; }
    public function __construct($post) { foreach(get_object_vars($post) as $k=>$v)$this->$k=$v; }
    public function __isset($key) { return in_array($key,['page_template','post_category','tags_input','ancestors'],true)?true:false; }
    public function __get($key) {
        switch($key){
            case 'page_template': return (string)get_post_meta($this->ID,'_wp_page_template',true);
            case 'post_category': return array_map(fn($t)=>$t->term_id,(array)wp_get_post_terms($this->ID,'category'));
            case 'tags_input': return wp_get_post_terms($this->ID,'post_tag',['fields'=>'names']);
            case 'ancestors': return get_post_ancestors($this);
        }
        return null;
    }
    public function filter($filter) { if($this->filter===$filter)return $this;return $filter==='raw'?self::get_instance($this->ID):sanitize_post($this,$filter); }
    public function to_array() { $p=get_object_vars($this);unset($p['rrw_source']);foreach(['ancestors','page_template','post_category','tags_input'] as $k)if(isset($p[$k]))unset($p[$k]);return $p; }
}
#[AllowDynamicProperties]
final class WP_Term {
    public $term_id=0;public $name='';public $slug='';public $term_group=0;public $term_taxonomy_id=0;public $taxonomy='';public $description='';public $parent=0;public $count=0;public $filter='raw';
    public function __construct($term) { foreach(get_object_vars($term) as $k=>$v)$this->$k=$v; }
    public static function get_instance($term_id, $taxonomy=null) { return get_term((int)$term_id,$taxonomy?:'')?: false; }
    public function to_array() { return get_object_vars($this); }
}
}
function sanitize_post($post, $context='display') { return $post; }
function sanitize_post_field($field, $value, $post_id, $context='display') { return $value; }

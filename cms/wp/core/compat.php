<?php
// Ergänzende WordPress-Funktionen: Rückgabe-Helfer, Custom Header, Verzeichnis-/Archiv-Helfer sowie Bedingungen von Shop-/Lern-Plugins
// (liefern ohne das jeweilige Plugin immer false, damit Themes mit WooCommerce-Weichen & Co. normal rendern).
if(!function_exists('__return_true')){
    function __return_true() { return true; }
    function __return_false() { return false; }
    function __return_zero() { return 0; }
    function __return_empty_array() { return []; }
    function __return_null() { return null; }
    function __return_empty_string() { return ''; }
}
function user_trailingslashit($string, $type_of_url='') { $string=(string)$string;return preg_match('#\.[a-z0-9]{2,5}$#i',parse_url($string,PHP_URL_PATH)?:'')?untrailingslashit($string):trailingslashit($string); }
function wp_is_writable($path) { return is_writable((string)$path); }
function wp_json_file_decode($filename, $options=[]) { $f=(string)$filename;if(!is_file($f))return null;$d=json_decode((string)file_get_contents($f),empty($options['associative'])?false:true);return json_last_error()===JSON_ERROR_NONE?$d:null; }
function use_block_editor_for_post_type($t) { return false; }
function wp_get_active_and_valid_plugins() { $o=[];foreach((array)get_option('active_plugins',[]) as $p){$f=WP_PLUGIN_DIR.'/'.$p;if(is_file($f))$o[]=$f;}return $o; }
function switch_to_locale($l) { return true; }
function restore_previous_locale() { return false; }
function wp_get_nav_menu_name($location) { $l=get_nav_menu_locations();$m=(string)($l[$location]??'');$o=$m!==''?wp_get_nav_menu_object($m):false;return $o?$o->name:''; }
function set_current_screen($s='') {}
function wp_add_privacy_policy_content($n,$c) {}
function wp_maybe_enqueue_oembed_host_js($h) { return $h; }
function wp_safe_remote_post($url,$args=[]) { return wp_remote_post($url,$args); }
function wp_oembed_get($url,$args='') { return false; }
function wp_customize_url($t=null) { return home_url('/cms/'); }
function is_user_member_of_blog($u=0,$b=0) { return (int)$u>0||is_user_logged_in(); }
function rest_authorization_required_code() { return is_user_logged_in()?403:401; }
function wp_generate_attachment_metadata($id,$file) { return []; }
function image_make_intermediate_size($file,$w,$h,$crop=false) { return false; }
function download_url($url,$timeout=300) { return new WP_Error('download_failed','Downloads sind hier nicht möglich.'); }
function get_comment_pages_count($comments=null,$per_page=null,$threaded=null) { $n=count((array)($comments??$GLOBALS['wp_query']->comments??[]));$pp=(int)($per_page??get_option('comments_per_page',50));return $pp>0?max(1,(int)ceil($n/$pp)):1; }
function get_archives_link($url,$text,$format='html',$before='',$after='') { $t=esc_html($text);$u=esc_url($url);return $format==='option'?"\t<option value='$u'>$before$t$after</option>\n":($format==='link'?"\t<link rel='archives' title='".esc_attr($text)."' href='$u' />\n":"\t<li>$before<a href='$u'>$t</a>$after</li>\n"); }
function et_pb_is_pagebuilder_used($id=0) { return false; }
// Custom Header (ohne Bild – Themes prüfen has_custom_header())
function has_custom_header() { return (bool)get_header_image(); }
function register_default_headers($h) { $GLOBALS['_wp_default_headers']=$h; }
function get_uploaded_header_images() { return []; }
function the_custom_header_markup() { $i=get_header_image();if($i)echo '<div id="wp-custom-header" class="wp-custom-header"><img src="'.esc_url($i).'" alt="" /></div>'; }
if(!class_exists('WP_REST_Response')){
    class WP_REST_Response { public $data;public int $status;public array $headers=[];
        public function __construct($data=null,$status=200,$headers=[]) { $this->data=$data;$this->status=(int)$status;$this->headers=(array)$headers; }
        public function get_data() { return $this->data; } public function set_data($d) { $this->data=$d; } public function get_status() { return $this->status; } public function set_status($s) { $this->status=(int)$s; }
        public function header($k,$v) { $this->headers[$k]=$v; } public function get_headers() { return $this->headers; } public function is_error() { return false; }
        public $links=[];public $matched_route='';public $matched_handler=null;
        public function add_link($rel,$href,$attrs=[]) { $this->links[$rel][]=['href'=>$href,'attributes'=>$attrs];} public function add_links($links) { foreach((array)$links as $rel=>$items){ if(isset($items['href']))$items=[$items];foreach($items as $i){ $h=$i['href']??'';unset($i['href']);$this->add_link($rel,$h,$i); } } }
        public function remove_link($rel,$href=null) { unset($this->links[$rel]); } public function get_links() { return $this->links; } public function get_matched_route() { return $this->matched_route; } public function set_matched_route($r) { $this->matched_route=$r; }
        public function get_matched_handler() { return $this->matched_handler; } public function set_matched_handler($h) { $this->matched_handler=$h; } public function as_error() { return null; } public function jsonSerialize(): mixed { return $this->get_data(); } public function link_header($rel,$link,$other=[]) { $h='<'.$link.'>; rel="'.$rel.'"';$this->header('Link',trim(($this->headers['Link']??'').', '.$h,', ')); } }
}
if(!class_exists('WP_REST_Request')){
    #[AllowDynamicProperties]
    class WP_REST_Request implements ArrayAccess {
        protected array $url=[];protected array $query=[];protected array $body=[];protected array $json=[];protected array $headers=[];protected array $attrs=[];protected string $method;protected string $route;protected string $raw='';
        public function __construct($method='',$route='',$attributes=[]) { $this->method=strtoupper((string)$method);$this->route=(string)$route;$this->attrs=(array)$attributes; }
        public function get_method() { return $this->method; } public function set_method($m) { $this->method=strtoupper((string)$m); } public function get_route() { return $this->route; } public function set_route($r) { $this->route=(string)$r; }
        public function get_attributes() { return $this->attrs; } public function set_attributes($a) { $this->attrs=(array)$a; }
        public function get_headers() { return $this->headers; } public function set_headers($h) { $this->headers=[];foreach((array)$h as $k=>$v)$this->headers[strtolower(str_replace('-','_',$k))]=is_array($v)?$v:[$v]; }
        public function get_header($k) { $v=$this->headers[strtolower(str_replace('-','_',(string)$k))]??null;return $v?implode(', ',$v):null; }
        public function set_header($k,$v) { $this->headers[strtolower(str_replace('-','_',(string)$k))]=[$v]; }
        public function get_content_type() { $c=$this->get_header('content_type');if(!$c)return null;$p=explode(';',$c);return ['value'=>trim($p[0]),'type'=>trim(explode('/',$p[0])[0]),'subtype'=>trim(explode('/',$p[0].'/')[1])]; }
        public function is_json_content_type() { $t=$this->get_content_type();return $t&&($t['subtype']==='json'||str_ends_with($t['subtype'],'+json')); }
        public function get_param($k) { foreach([$this->url,$this->json,$this->body,$this->query] as $set)if(array_key_exists($k,$set))return $set[$k];return $this->attrs['args'][$k]['default']??null; }
        public function set_param($k,$v) { $this->url[$k]=$v; }
        public function get_params() { return array_merge($this->query,$this->body,$this->json,$this->url); }
        public function has_param($k) { return array_key_exists($k,$this->get_params()); }
        public function get_url_params() { return $this->url; } public function set_url_params($p) { $this->url=(array)$p; }
        public function get_query_params() { return $this->query; } public function set_query_params($p) { $this->query=(array)$p; }
        public function get_body_params() { return $this->body; } public function set_body_params($p) { $this->body=(array)$p; }
        public function get_json_params() { return $this->json; } public function set_json_params($p) { $this->json=(array)$p; }
        public function get_default_params() { $d=[];foreach((array)($this->attrs['args']??[]) as $k=>$a)if(is_array($a)&&array_key_exists('default',$a))$d[$k]=$a['default'];return $d; }
        public function get_body() { return $this->raw; } public function set_body($b) { $this->raw=(string)$b; }
        public function get_file_params() { return []; }
        public function offsetExists($k): bool { return $this->has_param($k); } public function offsetGet($k): mixed { return $this->get_param($k); }
        public function offsetSet($k,$v): void { $this->set_param($k,$v); } public function offsetUnset($k): void { unset($this->url[$k],$this->json[$k],$this->body[$k],$this->query[$k]); }
    }
}
if(!class_exists('WP_REST_Server')){
    class WP_REST_Server { const READABLE='GET';const CREATABLE='POST';const EDITABLE='POST, PUT, PATCH';const DELETABLE='DELETE';const ALLMETHODS='GET, POST, PUT, PATCH, DELETE'; }
}
if(!class_exists('WP_REST_Controller')){
    #[AllowDynamicProperties]
    abstract class WP_REST_Controller { protected $namespace;protected $rest_base;protected $schema;
        public function register_routes() { return null; }
        public function get_items($request) { return new WP_Error('invalid-method','Methode nicht implementiert.',['status'=>405]); } public function get_item($request) { return $this->get_items($request); } public function create_item($request) { return $this->get_items($request); } public function update_item($request) { return $this->get_items($request); } public function delete_item($request) { return $this->get_items($request); }
        public function get_items_permissions_check_default($request) { return true; }
        protected function get_additional_fields($object_type=null) { $t=$object_type??$this->get_object_type();return (array)($GLOBALS['wp_rest_additional_fields'][$t]??[]); }
        protected function update_additional_fields_for_object($obj,$request) { return true; } protected function add_additional_fields_schema($schema) { return $schema; } 
        protected function get_context_param($args=[]) { return array_merge(['description'=>'Kontext der Anfrage','type'=>'string','enum'=>['view','embed','edit'],'default'=>'view'],$args); }
        public function get_endpoint_args_for_item_schema($method='POST') { return rest_get_endpoint_args_for_schema($this->get_item_schema(),$method); }
        public function filter_response_by_context($data,$context) { return $data; } public function add_additional_fields_to_schema($schema) { return $schema; } public function get_public_item_schema() { $s=$this->get_item_schema();return $s; }
        public function prepare_item_for_response($item,$request) { return new WP_REST_Response($item); } public function get_items_permissions_check($request) { return true; } public function get_item_permissions_check($request) { return true; }
        public function create_item_permissions_check($request) { return current_user_can('manage_options'); } public function update_item_permissions_check($request) { return current_user_can('manage_options'); } public function delete_item_permissions_check($request) { return current_user_can('manage_options'); }
        public function get_fields_for_response($request) { return array_keys((array)($this->get_item_schema()['properties']??[])); } public function get_object_type() { return $this->get_item_schema()['title']??''; } public function sanitize_slug($s) { return sanitize_title($s); }
        public function prepare_response_for_collection($r) { return $r instanceof WP_REST_Response?$r->get_data():$r; }
        public function get_collection_params() { return []; } public function get_item_schema() { return []; }
        public function add_additional_fields_to_object($o,$r) { return $o; }
    }
}
/** Bedingungen fremder Plugins (WooCommerce, bbPress …): ohne das Plugin immer false. Wird erst beim Laden des Themes aufgerufen – nie vor der Aktivierung eines Plugins, das diese Funktionen selbst definiert. */
function rrw_wp_define_missing_conditionals(): void {
    static $done=false;if($done)return;$done=true;
    foreach(['is_shop','is_product','is_product_taxonomy','is_product_category','is_product_tag','is_cart','is_checkout','is_account_page','is_wc_endpoint_url','is_store_notice_showing','is_lesson','is_courses','is_course','is_course_taxonomy','is_memberships','is_quiz','is_bbpress','is_amp_endpoint','dokan_is_seller_dashboard','dokan_is_store_page','dokan_is_store_listing','dokan_is_account_page','dokan_is_order_page','dokan_is_cart_page','dokan_is_checkout_page','bp_is_directory','bbp_is_topic_tag','bbp_is_topic_tag_edit','bbp_is_single_user','bbp_is_topic_edit','bbp_is_reply_edit','bbp_is_forum_edit','bbp_is_search','bbp_is_forum_archive','is_buddypress','bp_is_user','is_woocommerce'] as $_f)
        if(!function_exists($_f))eval('function '.$_f.'() { return false; }');
}

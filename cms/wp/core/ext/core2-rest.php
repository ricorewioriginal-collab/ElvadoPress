<?php
// Ergänzende WordPress-Funktionen (Kern 2, Teil 11): REST-Klassen (Such-Handler, Meta-Felder, Controller) und oEmbed (WP_oEmbed, WP_oEmbed_Controller).
// Eigenständig umgesetzt; die Klassen arbeiten auf den vorhandenen REST-Bausteinen der Schicht (WP_REST_Request/Response/Controller).

/* ───────── Such-Handler ───────── */
if(!class_exists('WP_REST_Search_Handler')){
abstract class WP_REST_Search_Handler {
    const RESULT_IDS='ids';const RESULT_TOTAL='total';
    protected $type='';protected $subtypes=[];
    public function get_type() { return $this->type; }
    public function get_subtypes() { return $this->subtypes; }
    abstract public function search_items(WP_REST_Request $request);
    abstract public function prepare_item($id, array $fields);
    abstract public function prepare_item_links($id);
}
}
if(!class_exists('WP_REST_Post_Search_Handler')){
class WP_REST_Post_Search_Handler extends WP_REST_Search_Handler {
    public function __construct() {
        $this->type='post';$types=[];
        $objs=get_post_types(['public'=>true,'show_in_rest'=>true],'objects')?:get_post_types(['public'=>true],'objects');   // ohne show_in_rest-Angabe alle öffentlichen Typen
        foreach($objs as $t)if('attachment'!==$t->name)$types[]=$t->name;
        $this->subtypes=$types;
    }
    public function search_items(WP_REST_Request $request) {
        $sub=(array)$request->get_param('subtype');if(!$sub||in_array('any',$sub,true))$sub=$this->subtypes;
        $page=max(1,(int)$request->get_param('page'));$per=max(1,(int)($request->get_param('per_page')?:10));
        $a=['post_type'=>array_values(array_intersect($sub,$this->subtypes))?:'none','post_status'=>'publish','paged'=>$page,'posts_per_page'=>$per,'ignore_sticky_posts'=>true,'fields'=>'ids'];
        if($request->get_param('search'))$a['s']=$request->get_param('search');
        if($request->get_param('exclude'))$a['post__not_in']=(array)$request->get_param('exclude');
        if($request->get_param('include'))$a['post__in']=(array)$request->get_param('include');
        $q=new WP_Query(apply_filters('rest_post_search_query',$a,$request));
        return [self::RESULT_IDS=>array_map('intval',$q->posts),self::RESULT_TOTAL=>(int)$q->found_posts];
    }
    public function prepare_item($id, array $fields) {
        $p=get_post($id);$d=[];
        if(in_array('id',$fields,true))$d['id']=(int)$p->ID;
        if(in_array('title',$fields,true))$d['title']=$p->post_title?:'(ohne Titel)';
        if(in_array('url',$fields,true))$d['url']=get_permalink($p->ID);
        if(in_array('type',$fields,true))$d['type']=$this->type;
        if(in_array('subtype',$fields,true))$d['subtype']=$p->post_type;
        return $d;
    }
    public function prepare_item_links($id) {
        $p=get_post($id);$l=['self'=>['href'=>rest_url('wp/v2/'.(get_post_type_object($p->post_type)->rest_base??$p->post_type).'/'.$id),'embeddable'=>true],'about'=>['href'=>rest_url('wp/v2/types/'.$p->post_type)]];
        return $l;
    }
}
}
if(!class_exists('WP_REST_Term_Search_Handler')){
class WP_REST_Term_Search_Handler extends WP_REST_Search_Handler {
    public function __construct() { $this->type='term';$this->subtypes=array_values(get_taxonomies(['public'=>true,'show_in_rest'=>true],'names')?:get_taxonomies(['public'=>true],'names')); }
    public function search_items(WP_REST_Request $request) {
        $sub=(array)$request->get_param('subtype');if(!$sub||in_array('any',$sub,true))$sub=$this->subtypes;
        $per=max(1,(int)($request->get_param('per_page')?:10));$page=max(1,(int)$request->get_param('page'));
        $a=['taxonomy'=>array_values(array_intersect($sub,$this->subtypes)),'hide_empty'=>false,'fields'=>'ids'];
        if($request->get_param('search'))$a['search']=$request->get_param('search');
        if($request->get_param('exclude'))$a['exclude']=(array)$request->get_param('exclude');
        if($request->get_param('include'))$a['include']=(array)$request->get_param('include');
        $all=$a['taxonomy']?get_terms($a):[];$all=is_wp_error($all)?[]:array_map('intval',(array)$all);
        return [self::RESULT_IDS=>array_slice($all,($page-1)*$per,$per),self::RESULT_TOTAL=>count($all)];
    }
    public function prepare_item($id, array $fields) {
        $t=get_term($id);$d=[];
        if(in_array('id',$fields,true))$d['id']=(int)$t->term_id;
        if(in_array('title',$fields,true))$d['title']=$t->name;
        if(in_array('url',$fields,true)){ $l=get_term_link($t);$d['url']=is_wp_error($l)?'':$l; }
        if(in_array('type',$fields,true))$d['type']=$this->type;
        if(in_array('subtype',$fields,true))$d['subtype']=$t->taxonomy;
        return $d;
    }
    public function prepare_item_links($id) {
        $t=get_term($id);$tx=get_taxonomy($t->taxonomy);
        return ['self'=>['href'=>rest_url('wp/v2/'.(($tx->rest_base??'')?:$t->taxonomy).'/'.$id),'embeddable'=>true],'about'=>['href'=>rest_url('wp/v2/taxonomies/'.$t->taxonomy)]];
    }
}
}
if(!class_exists('WP_REST_Post_Format_Search_Handler')){
class WP_REST_Post_Format_Search_Handler extends WP_REST_Search_Handler {
    public function __construct() { $this->type='post-format';$this->subtypes=[]; }
    public function search_items(WP_REST_Request $request) {
        $f=get_post_format_slugs();$s=(string)$request->get_param('search');$ids=[];
        foreach($f as $slug=>$name)if($s===''||stripos($slug.' '.$name,$s)!==false)$ids[]=$slug;
        $per=max(1,(int)($request->get_param('per_page')?:10));$page=max(1,(int)$request->get_param('page'));
        return [self::RESULT_IDS=>array_slice($ids,($page-1)*$per,$per),self::RESULT_TOTAL=>count($ids)];
    }
    public function prepare_item($id, array $fields) {
        $d=[];
        if(in_array('id',$fields,true))$d['id']=$id;
        if(in_array('title',$fields,true))$d['title']=get_post_format_string($id);
        if(in_array('url',$fields,true))$d['url']=get_post_format_link($id)?:'';
        if(in_array('type',$fields,true))$d['type']=$this->type;
        if(in_array('subtype',$fields,true))$d['subtype']=$id;
        return $d;
    }
    public function prepare_item_links($id) { return []; }
}
}

/* ───────── Meta-Felder ───────── */
if(!class_exists('WP_REST_Meta_Fields')){
abstract class WP_REST_Meta_Fields {
    abstract protected function get_meta_type();
    protected function get_meta_subtype() { return ''; }
    abstract protected function get_rest_field_type();
    public function register_field() {
        register_rest_field($this->get_rest_field_type(),'meta',['get_callback'=>[$this,'get_value'],'update_callback'=>[$this,'update_value'],'schema'=>$this->get_field_schema()]);
    }
    public function get_value($object_id, $request=null) {
        $out=[];
        foreach($this->get_registered_fields() as $key=>$args){
            $all=get_metadata($this->get_meta_type(),$object_id,$key,false);
            if(!empty($args['single'])){ $v=$all?$all[0]:($args['schema']['default']??null);$out[$args['name']]=$this->prepare_value_for_response($v,$request,$args); }
            else{ $out[$args['name']]=array_map(fn($x)=>$this->prepare_value_for_response($x,$request,$args),$all?:[]); }
        }
        return (object)$out;
    }
    protected function prepare_value_for_response($value, $request, $args) {
        if(!empty($args['prepare_callback']))$value=call_user_func($args['prepare_callback'],$value,$request,$args);
        return $value;
    }
    public function update_value($meta, $object_id) {
        $fields=$this->get_registered_fields();
        foreach((array)$meta as $name=>$value){
            $key=null;foreach($fields as $k=>$a)if($a['name']===$name){ $key=$k;$args=$a;break; }
            if($key===null)return new WP_Error('rest_invalid_stored_value','Ungültiges Meta-Feld.',['status'=>400]);
            $mt=$this->get_meta_type();
            if(!current_user_can('edit_'.$mt.'_meta',$object_id,$key)&&!(current_user_can('edit_'.$mt,$object_id)&&!is_protected_meta($key,$mt)))return new WP_Error('rest_cannot_update',sprintf('Sie dürfen das Meta-Feld „%s“ nicht ändern.',$key),['key'=>$key,'status'=>rest_authorization_required_code()]);
            if($value===null){ delete_metadata($this->get_meta_type(),$object_id,$key);continue; }
            if(!empty($args['single'])){ if(update_metadata($this->get_meta_type(),$object_id,$key,wp_slash($value))===false&&get_metadata($this->get_meta_type(),$object_id,$key,true)!==$value)return new WP_Error('rest_meta_database_error','Beim Aktualisieren ist ein Datenbankfehler aufgetreten.',['status'=>500]); }
            else{ delete_metadata($this->get_meta_type(),$object_id,$key);foreach((array)$value as $v)add_metadata($this->get_meta_type(),$object_id,$key,wp_slash($v)); }
        }
        return null;
    }
    protected function get_registered_fields() {
        $o=[];
        foreach(get_registered_meta_keys($this->get_meta_type(),$this->get_meta_subtype()) as $key=>$args){
            if(empty($args['show_in_rest']))continue;
            $r=is_array($args['show_in_rest'])?$args['show_in_rest']:[];$name=$r['name']??$key;
            $schema=array_merge(['type'=>$args['type']??'string','description'=>$args['description']??''],(array)($r['schema']??[]));
            if(!empty($args['default'])||array_key_exists('default',$args))$schema['default']=$args['default']??null;
            $o[$key]=['name'=>$name,'single'=>!empty($args['single']),'type'=>$args['type']??'string','schema'=>$schema,'prepare_callback'=>$r['prepare_callback']??null];
        }
        return $o;
    }
    public function get_field_schema() {
        $props=[];foreach($this->get_registered_fields() as $a)$props[$a['name']]=$a['single']?$a['schema']:['type'=>'array','items'=>$a['schema']]+array_intersect_key($a['schema'],['description'=>1]);
        return ['description'=>'Meta-Felder.','type'=>'object','context'=>['view','edit'],'properties'=>$props,'arg_options'=>['sanitize_callback'=>null,'validate_callback'=>null]];
    }
}
}
if(!class_exists('WP_REST_Post_Meta_Fields')){
class WP_REST_Post_Meta_Fields extends WP_REST_Meta_Fields {
    protected $post_type;
    public function __construct($post_type) { $this->post_type=(string)$post_type; }
    protected function get_meta_type() { return 'post'; }
    protected function get_meta_subtype() { return $this->post_type; }
    protected function get_rest_field_type() { return $this->post_type; }
}
}
if(!class_exists('WP_REST_Comment_Meta_Fields')){
class WP_REST_Comment_Meta_Fields extends WP_REST_Meta_Fields {
    protected function get_meta_type() { return 'comment'; }
    protected function get_rest_field_type() { return 'comment'; }
}
}
if(!class_exists('WP_REST_Term_Meta_Fields')){
class WP_REST_Term_Meta_Fields extends WP_REST_Meta_Fields {
    protected $taxonomy;
    public function __construct($taxonomy) { $this->taxonomy=(string)$taxonomy; }
    protected function get_meta_type() { return 'term'; }
    protected function get_meta_subtype() { return $this->taxonomy; }
    protected function get_rest_field_type() { return 'post_tag'===$this->taxonomy?'tag':$this->taxonomy; }
}
}
if(!class_exists('WP_REST_User_Meta_Fields')){
class WP_REST_User_Meta_Fields extends WP_REST_Meta_Fields {
    protected function get_meta_type() { return 'user'; }
    protected function get_rest_field_type() { return 'user'; }
}
}

/* ───────── Controller ohne Entsprechung im CMS (leere bzw. nicht unterstützte Antworten) ───────── */
if(!class_exists('WP_REST_Template_Autosaves_Controller')){
class WP_REST_Template_Autosaves_Controller extends WP_REST_Controller {
    protected $parent_post_type;protected $rest_base_parent;
    public function __construct($parent_post_type='wp_template') {
        $this->parent_post_type=$parent_post_type;$this->namespace='wp/v2';$this->rest_base='autosaves';$this->rest_base_parent=$parent_post_type==='wp_template_part'?'template-parts':'templates';
    }
    public function register_routes() {
        register_rest_route($this->namespace,'/'.$this->rest_base_parent.'/(?P<id>[\d]+|[^/]+/[^/]+)/'.$this->rest_base,[['methods'=>'GET','callback'=>[$this,'get_items'],'permission_callback'=>[$this,'get_items_permissions_check']],['methods'=>'POST','callback'=>[$this,'create_item'],'permission_callback'=>[$this,'create_item_permissions_check']]]);
    }
    public function get_items_permissions_check($request) { return current_user_can('edit_theme_options')?true:new WP_Error('rest_cannot_manage_templates','Sie dürfen Vorlagen nicht bearbeiten.',['status'=>rest_authorization_required_code()]); }
    public function create_item_permissions_check($request) { return $this->get_items_permissions_check($request); }
    public function get_items($request) { return rest_ensure_response([]); }   // Vorlagen haben im CMS keine Autosaves
    public function get_item($request) { return new WP_Error('rest_post_invalid_id','Ungültige Autosave-ID.',['status'=>404]); }
    public function create_item($request) { return new WP_Error('rest_not_supported','Autosaves für Vorlagen werden nicht unterstützt.',['status'=>501]); }
    public function get_item_schema() { return ['$schema'=>'http://json-schema.org/draft-04/schema#','title'=>'autosave','type'=>'object','properties'=>['id'=>['type'=>'integer'],'parent'=>['type'=>'integer']]]; }
}
}
if(!class_exists('WP_REST_Global_Styles_Revisions_Controller')){
class WP_REST_Global_Styles_Revisions_Controller extends WP_REST_Controller {
    public function __construct() { $this->namespace='wp/v2';$this->rest_base='global-styles/(?P<parent>[\d]+)/revisions'; }
    public function register_routes() {
        register_rest_route($this->namespace,'/'.$this->rest_base,[['methods'=>'GET','callback'=>[$this,'get_items'],'permission_callback'=>[$this,'get_items_permissions_check']]]);
    }
    public function get_items_permissions_check($request) { return current_user_can('edit_theme_options')?true:new WP_Error('rest_cannot_view','Sie dürfen Stil-Revisionen nicht ansehen.',['status'=>rest_authorization_required_code()]); }
    public function get_items($request) { return rest_ensure_response([]); }   // Revisionen der globalen Stile werden nicht geführt
    public function get_item($request) { return new WP_Error('rest_post_invalid_id','Ungültige Revisions-ID.',['status'=>404]); }
    public function get_item_schema() { return ['$schema'=>'http://json-schema.org/draft-04/schema#','title'=>'global-styles-revision','type'=>'object','properties'=>['id'=>['type'=>'integer'],'parent'=>['type'=>'integer'],'date'=>['type'=>'string'],'settings'=>['type'=>'object'],'styles'=>['type'=>'object']]]; }
}
}

/* ───────── oEmbed ───────── */
if(!class_exists('WP_oEmbed')){
#[AllowDynamicProperties]
class WP_oEmbed {
    public $providers=[];public static $early_providers=[];private $compat_methods=['_fetch_with_format','_parse_json','_parse_xml','_parse_xml_body'];
    public function __construct() {
        $this->providers=apply_filters('oembed_providers',[
            '#https?://((m|www)\.)?youtube\.com/watch.*#i'=>['https://www.youtube.com/oembed',true],'#https?://((m|www)\.)?youtube\.com/playlist.*#i'=>['https://www.youtube.com/oembed',true],'#https?://youtu\.be/.*#i'=>['https://www.youtube.com/oembed',true],
            '#https?://(.+\.)?vimeo\.com/.*#i'=>['https://vimeo.com/api/oembed.{format}',true],'#https?://(www\.)?dailymotion\.com/.*#i'=>['https://www.dailymotion.com/services/oembed',true],
            '#https?://(www\.)?flickr\.com/.*#i'=>['https://www.flickr.com/services/oembed/',true],'#https?://(.+\.)?soundcloud\.com/.*#i'=>['https://soundcloud.com/oembed',true],'#https?://open\.spotify\.com/.*#i'=>['https://embed.spotify.com/oembed/',true],
            '#https?://(www\.)?tiktok\.com/.*/video/.*#i'=>['https://www.tiktok.com/oembed',true],'#https?://(www\.)?(twitter|x)\.com/\w{1,15}/status(es)?/.*#i'=>['https://publish.twitter.com/oembed',true],'#https?://(www\.)?reddit\.com/r/[^/]+/comments/.*#i'=>['https://www.reddit.com/oembed',true],
        ]);
        self::$early_providers=$this->providers;
    }
    public function __call($name, $arguments) { return in_array($name,$this->compat_methods,true)?false:null; }
    public function get_provider($url, $args='') {
        $args=wp_parse_args($args);$provider=false;
        foreach($this->providers as $pattern=>$p){ [$u,$regex]=$p;if(!$regex){ $pattern='#'.str_replace('___wildcard___','(.+)',preg_quote(str_replace('*','___wildcard___',$pattern),'#')).'#i'; }
            if(preg_match($pattern,(string)$url)){ $provider=str_replace('{format}','json',$u);break; } }
        if(!$provider&&!empty($args['discover']))$provider=$this->discover($url);
        return $provider;
    }
    public function discover($url) {   // Netzwerkzugriff nur beim Aufruf
        $r=wp_safe_remote_get($url,['timeout'=>5]);if(is_wp_error($r))return false;
        $b=wp_remote_retrieve_body($r);
        return preg_match('#<link[^>]+type=["\']application/json\+oembed["\'][^>]+href=["\']([^"\']+)["\']#i',$b,$m)||preg_match('#<link[^>]+href=["\']([^"\']+)["\'][^>]+type=["\']application/json\+oembed["\']#i',$b,$m)?html_entity_decode($m[1]):false;
    }
    public function fetch($provider, $url, $args='') {
        $ed=(array)apply_filters('embed_defaults',['width'=>500]);
        $args=wp_parse_args($args,['maxwidth'=>(int)($ed['width']??500)]);
        $q=add_query_arg(array_filter(['maxwidth'=>$args['maxwidth']??null,'maxheight'=>$args['height']??null,'url'=>$url,'format'=>'json']),$provider);
        $r=wp_safe_remote_get(apply_filters('oembed_fetch_url',$q,$url,$args),['timeout'=>10]);
        if(is_wp_error($r)||(int)wp_remote_retrieve_response_code($r)!==200)return false;
        $d=json_decode(wp_remote_retrieve_body($r));
        return is_object($d)?$d:false;
    }
    public function get_html($url, $args='') {
        $pre=apply_filters('pre_oembed_result',null,$url,$args);if($pre!==null)return $pre;
        $provider=$this->get_provider($url,$args);if(!$provider)return false;
        $data=$this->fetch($provider,$url,$args);if(!$data)return false;
        $html=$this->data2html($data,$url);
        return apply_filters('oembed_result',$html,$url,$args);
    }
    public function data2html($data, $url) {
        $data=(object)$data;if(!isset($data->type))return false;$return=false;
        switch($data->type){
            case 'photo': if(!empty($data->url)&&!empty($data->width)&&!empty($data->height))$return='<a href="'.esc_url($url).'"><img src="'.esc_url($data->url).'" alt="'.esc_attr($data->title??'').'" width="'.esc_attr($data->width).'" height="'.esc_attr($data->height).'" /></a>';break;
            case 'video': case 'rich': if(!empty($data->html)&&is_string($data->html))$return=$data->html;break;
            case 'link': if(!empty($data->title))$return='<a href="'.esc_url($url).'">'.esc_html($data->title).'</a>';break;
        }
        return apply_filters('oembed_dataparse',$return,$data,$url);
    }
    public function _strip_newlines($html, $data, $url) { return str_contains((string)$html,'<!-- wp:')?$html:preg_replace('/[\r\n\t ]+/',' ',(string)$html); }
}
}
if(!class_exists('WP_oEmbed_Controller')){
final class WP_oEmbed_Controller {
    public function register_routes() {
        register_rest_route('oembed/1.0','/embed',[['methods'=>'GET','callback'=>[$this,'get_item'],'permission_callback'=>'__return_true','args'=>['url'=>['required'=>true,'type'=>'string','format'=>'uri'],'format'=>['default'=>'json','sanitize_callback'=>'wp_oembed_ensure_format'],'maxwidth'=>['default'=>600,'sanitize_callback'=>'absint']]]]);
        register_rest_route('oembed/1.0','/proxy',[['methods'=>'GET','callback'=>[$this,'get_proxy_item'],'permission_callback'=>[$this,'get_proxy_item_permissions_check'],'args'=>['url'=>['required'=>true,'type'=>'string','format'=>'uri']]]]);
    }
    public function get_item($request) {
        $post_id=url_to_postid((string)$request['url']);$post_id=apply_filters('oembed_request_post_id',$post_id,$request['url']);
        $data=$post_id?get_oembed_response_data($post_id,(int)$request['maxwidth']):false;
        if(!$data)return new WP_Error('oembed_invalid_url',get_status_header_desc(404),['status'=>404]);
        return $data;
    }
    public function get_proxy_item_permissions_check() { return current_user_can('edit_posts')?true:new WP_Error('rest_forbidden','Sie dürfen Einbettungen nicht abrufen.',['status'=>rest_authorization_required_code()]); }
    public function get_proxy_item($request) {
        $o=new WP_oEmbed();$data=$o->get_html((string)$request['url'],['discover'=>true]);
        return $data?['html'=>$data]:new WP_Error('oembed_invalid_url',get_status_header_desc(404),['status'=>404]);
    }
}
}

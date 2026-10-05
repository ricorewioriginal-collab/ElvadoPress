<?php
// Weitere WordPress-Funktionen und -Klassen, die größere Plugins (WooCommerce, Elementor, Yoast …) voraussetzen:
// Rollen, Benutzer-/Netzwerkoptionen, Dateien/Uploads, Kommentare schreiben, Anhänge, Abfrage-Klassen, Hilfsfunktionen.
// Alles hier ist auf eine Einzelseite (ohne Multisite) zugeschnitten.

/* ───────── Rollen ───────── */
class WP_Role {
    public $name;public $capabilities;
    public function __construct($role,$caps) { $this->name=$role;$this->capabilities=$caps; }
    public function has_cap($c) { return !empty($this->capabilities[$c]); }
    public function add_cap($c,$grant=true) { $this->capabilities[$c]=$grant;rrw_wp_roles_save($this->name,$this->capabilities); }
    public function remove_cap($c) { unset($this->capabilities[$c]);rrw_wp_roles_save($this->name,$this->capabilities); }
}
function rrw_wp_roles_custom(): array { $r=get_option('rrw_wp_custom_roles',[]);return is_array($r)?$r:[]; }
function rrw_wp_roles_save(string $role,array $caps,?string $name=null): void { $r=rrw_wp_roles_custom();$r[$role]=['name'=>$name??($r[$role]['name']??$role),'capabilities'=>$caps];update_option('rrw_wp_custom_roles',$r); }
class WP_Roles {
    public $roles=[];public $role_objects=[];public $role_names=[];public $role_key='wp_user_roles';public $use_db=true;
    public function __construct() { $this->reload(); }
    public function reload() {
        $base=['administrator'=>'Administrator','editor'=>'Redakteur','author'=>'Autor','contributor'=>'Mitarbeiter','subscriber'=>'Abonnent'];$this->roles=[];
        foreach($base as $k=>$n)$this->roles[$k]=['name'=>$n,'capabilities'=>rrw_wp_caps_for_role($k,false)];
        foreach(rrw_wp_roles_custom() as $k=>$r)$this->roles[$k]=['name'=>(string)$r['name'],'capabilities'=>(array)$r['capabilities']];
        $this->role_objects=[];$this->role_names=[];
        foreach($this->roles as $k=>$r){ $this->role_objects[$k]=new WP_Role($k,$r['capabilities']);$this->role_names[$k]=$r['name']; }
    }
    public function add_role($role,$name,$caps=[]) { if(isset($this->roles[$role]))return null;rrw_wp_roles_save((string)$role,(array)$caps,(string)$name);$this->reload();return $this->role_objects[$role]; }
    public function remove_role($role) { $r=rrw_wp_roles_custom();unset($r[$role]);update_option('rrw_wp_custom_roles',$r);$this->reload(); }
    public function add_cap($role,$cap,$grant=true) { if(isset($this->role_objects[$role]))$this->role_objects[$role]->add_cap($cap,$grant); }
    public function remove_cap($role,$cap) { if(isset($this->role_objects[$role]))$this->role_objects[$role]->remove_cap($cap); }
    public function get_role($role) { return $this->role_objects[$role]??null; }
    public function get_names() { return $this->role_names; }
    public function is_role($role) { return isset($this->role_names[$role]); }
}
function wp_roles() { static $r=null;return $r=$r??new WP_Roles(); }
function get_role($role) { return wp_roles()->get_role((string)$role); }
function add_role($role,$display_name,$capabilities=[]) { return wp_roles()->add_role($role,$display_name,$capabilities); }
function remove_role($role) { wp_roles()->remove_role($role); }
function get_editable_roles() { return apply_filters('editable_roles',wp_roles()->roles); }
function translate_user_role($name,$domain='default') { return translate_with_gettext_context($name,'User role',$domain); }

/* ───────── Benutzer-/Netzwerk-/Site-Optionen ───────── */
function get_user_option($option,$user=0) { $uid=$user?:get_current_user_id();if(!$uid)return false;global $wpdb;$v=get_user_meta($uid,$wpdb->prefix.$option,true);if($v==='')$v=get_user_meta($uid,$option,true);return apply_filters("get_user_option_{$option}",$v===''?false:$v,$option,$user); }
function update_user_option($user_id,$option,$newvalue,$global=false) { global $wpdb;return update_user_meta($user_id,$global?$option:$wpdb->prefix.$option,$newvalue); }
function delete_user_option($user_id,$option,$global=false) { global $wpdb;return delete_user_meta($user_id,$global?$option:$wpdb->prefix.$option); }
function get_network_option($network_id,$option,$default_value=false) { return get_option($option,$default_value); }
function update_network_option($network_id,$option,$value) { return update_option($option,$value); }
function add_network_option($network_id,$option,$value) { return add_option($option,$value); }
function delete_network_option($network_id,$option) { return delete_option($option); }
function add_blog_option($id,$option,$value) { return add_option($option,$value); }
function update_blog_option($id,$option,$value) { return update_option($option,$value); }
function delete_blog_option($id,$option) { return delete_option($option); }
function add_option_whitelist($new,$options='') { return add_allowed_options($new,$options); }
function add_allowed_options($new,$options='') { global $new_allowed_options;$new_allowed_options=array_merge_recursive((array)$new_allowed_options,(array)$new);return $new_allowed_options; }
function remove_allowed_options($del,$options='') { return []; }
function wp_prime_option_caches($options) {}
function ms_is_switched() { return false; } function get_current_network_id() { return 1; } function is_main_network($id=null) { return true; } function is_subdomain_install() { return false; }
function get_sites($args=[]) { return []; } function get_site($id=null) { return false; } function get_main_site_id($n=null) { return 1; } function is_multisite_dummy() { return false; }
function add_user_to_blog($blog_id,$user_id,$role) { return true; }
function is_blog_installed() { return true; }

/* ───────── Dateien, Uploads, MIME ───────── */
function wp_mkdir_p($target) { $target=rtrim(wp_normalize_path((string)$target),'/');if($target==='')return false;return is_dir($target)||@mkdir($target,0775,true); }
function wp_tempnam($filename='',$dir='') { $dir=$dir?:sys_get_temp_dir();$f=tempnam($dir,'rrw');return $f?:false; }
function wp_delete_file($file) { $file=apply_filters('wp_delete_file',$file);if(is_string($file)&&$file!==''&&is_file($file))@unlink($file); }
function wp_delete_file_from_directory($file,$dir) { if(str_starts_with(realpath((string)$file)?:'',realpath((string)$dir)?:"\0")){ @unlink($file);return true; }return false; }
function wp_is_stream($path) { return (bool)preg_match('#^[a-z0-9.+-]+://#i',(string)$path)&&!preg_match('#^file://#i',(string)$path); }
function wp_filesize($path) { return (int)(@filesize((string)$path)?:0); }
function wp_get_mime_types() { return apply_filters('mime_types',['jpg|jpeg|jpe'=>'image/jpeg','gif'=>'image/gif','png'=>'image/png','webp'=>'image/webp','avif'=>'image/avif','ico'=>'image/x-icon','svg'=>'image/svg+xml','pdf'=>'application/pdf','txt|asc|c|cc|h|srt'=>'text/plain','csv'=>'text/csv','json'=>'application/json','zip'=>'application/zip','mp3|m4a|m4b'=>'audio/mpeg','ogg|oga'=>'audio/ogg','wav'=>'audio/wav','mp4|m4v'=>'video/mp4','webm'=>'video/webm','doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','xls'=>'application/vnd.ms-excel','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']); }
function get_allowed_mime_types($user=null) { $t=wp_get_mime_types();unset($t['svg']);return apply_filters('upload_mimes',$t,$user); }
function wp_check_filetype_and_ext($file,$filename,$mimes=null) { $ft=wp_check_filetype($filename,$mimes);return ['ext'=>$ft['ext'],'type'=>$ft['type'],'proper_filename'=>false]; }
function wp_unique_filename($dir,$filename,$unique_filename_callback=null) {
    $filename=sanitize_file_name((string)$filename);$info=pathinfo($filename);$ext=isset($info['extension'])?'.'.$info['extension']:'';$name=$info['filename'];$n=1;$out=$filename;
    while(file_exists(rtrim((string)$dir,'/').'/'.$out)){ $out=$name.'-'.$n.$ext;$n++; }
    return apply_filters('wp_unique_filename',$out,$ext,$dir,$unique_filename_callback,[],[]);
}
function wp_upload_bits($name,$deprecated,$bits,$time=null) {
    if(empty($name))return ['error'=>'Leerer Dateiname'];$ft=wp_check_filetype($name);if(!$ft['ext']||!$ft['type'])return ['error'=>'Dieser Dateityp ist nicht erlaubt.'];
    $u=wp_upload_dir($time);if(!empty($u['error']))return ['error'=>$u['error']];
    $fn=wp_unique_filename($u['path'],$name);$new=$u['path'].'/'.$fn;if(!wp_mkdir_p($u['path']))return ['error'=>'Ordner konnte nicht angelegt werden'];
    if(@file_put_contents($new,$bits)===false)return ['error'=>'Datei konnte nicht gespeichert werden'];@chmod($new,0644);
    return ['file'=>$new,'url'=>$u['url'].'/'.$fn,'error'=>false];
}
function wp_getimagesize($f,&$info=null) { return @getimagesize((string)$f)?:false; }
function wp_get_image_editor($path,$args=[]) { return new WP_Error('image_no_editor','Die Bildbearbeitung ist hier nicht verfügbar.'); }
function wp_image_editor_supports($args=[]) { return false; }
function wp_get_additional_image_sizes() { return $GLOBALS['_wp_additional_image_sizes']??[]; }
function image_resize_dimensions($ow,$oh,$dw,$dh,$crop=false) { if($ow<=0||$oh<=0)return false;$r=min($dw?$dw/$ow:INF,$dh?$dh/$oh:INF);if($r>=1)return false;$w=max(1,(int)round($ow*$r));$h=max(1,(int)round($oh*$r));return [0,0,0,0,$w,$h,$ow,$oh]; }
function image_get_intermediate_size($post_id,$size='thumbnail') { return false; }
function wp_get_attachment_image_srcset($id,$size='medium',$meta=null) { return false; }
function wp_get_attachment_image_sizes($id,$size='medium',$meta=null) { return false; }
function wp_calculate_image_srcset($size_array,$image_src,$image_meta,$attachment_id=0) { return false; }
function wp_calculate_image_sizes($size,$image_src=null,$image_meta=null,$attachment_id=0) { return false; }
function wp_image_matches_ratio($w1,$h1,$w2,$h2) { if(!$h1||!$h2)return false;return abs($w1/$h1-$w2/$h2)<0.02; }
function wp_increase_content_media_count($n=1) { return 0; } function wp_omit_loading_attr_threshold($f=false) { return 1; }
function wp_high_priority_element_flag($v=null) { return false; }
function media_sideload_image($file,$post_id=0,$desc=null,$return_type='html') { return new WP_Error('sideload_unavailable','Bilder von fremden Adressen werden hier nicht übernommen.'); }
function media_handle_sideload($file_array,$post_id=0,$desc=null,$post_data=[]) { return new WP_Error('sideload_unavailable','Nicht verfügbar.'); }
function media_handle_upload($file_id,$post_id,$post_data=[],$overrides=[]) { if(rrw_wp_bridge('media'))return rrw_wp_cms_media_handle_upload((string)$file_id,(int)$post_id,(array)$post_data,(array)$overrides);return new WP_Error('upload_unavailable','Uploads laufen über die CMS-Medien.'); }
function wp_insert_attachment($args,$file=false,$parent=0,$wp_error=false,$fire_after_hooks=true) {
    $a=wp_parse_args($args,['post_type'=>'attachment','post_status'=>'inherit','post_parent'=>$parent,'post_title'=>$file?basename((string)$file):'','post_mime_type'=>'']);
    $id=wp_insert_post($a,$wp_error);if($id&&!is_wp_error($id)&&$file)update_post_meta($id,'_wp_attached_file',(string)$file);
    return $id;
}
function wp_delete_attachment($post_id,$force_delete=false) { $f=get_attached_file($post_id);$r=wp_delete_post($post_id,true);if($r&&$f&&is_file($f))@unlink($f);return $r; }

/* ───────── Kommentare schreiben (Tabelle wp_comments) ───────── */
function wp_filter_comment($commentdata) { return $commentdata; }
function wp_insert_comment($commentdata) {
    global $wpdb;if(!rrw_wp_db_ready())return false;$d=wp_unslash($commentdata);$now=current_time('mysql');
    $row=['comment_post_ID'=>(int)($d['comment_post_ID']??0),'comment_author'=>(string)($d['comment_author']??''),'comment_author_email'=>(string)($d['comment_author_email']??''),'comment_author_url'=>(string)($d['comment_author_url']??''),'comment_author_IP'=>(string)($d['comment_author_IP']??''),
        'comment_date'=>(string)($d['comment_date']??$now),'comment_date_gmt'=>(string)($d['comment_date_gmt']??get_gmt_from_date($d['comment_date']??$now)),'comment_content'=>(string)($d['comment_content']??''),'comment_karma'=>(int)($d['comment_karma']??0),
        'comment_approved'=>(string)($d['comment_approved']??'1'),'comment_agent'=>(string)($d['comment_agent']??''),'comment_type'=>(string)($d['comment_type']??'comment'),'comment_parent'=>(int)($d['comment_parent']??0),'user_id'=>(int)($d['user_id']??0)];
    if(!$wpdb->insert($wpdb->comments,$row))return false;$id=(int)$wpdb->insert_id;
    if(!empty($d['comment_meta']))foreach((array)$d['comment_meta'] as $k=>$v)add_comment_meta($id,$k,$v);
    do_action('wp_insert_comment',$id,get_comment($id));return $id;
}
function wp_update_comment($commentarr,$wp_error=false) {
    global $wpdb;$c=wp_unslash($commentarr);$id=(int)($c['comment_ID']??0);if(!$id||!rrw_wp_db_ready())return 0;$cols=['comment_content','comment_author','comment_author_email','comment_author_url','comment_approved','comment_type','comment_parent'];$set=[];
    foreach($cols as $k)if(array_key_exists($k,$c))$set[$k]=$c[$k];
    if($set)$wpdb->update($wpdb->comments,$set,['comment_ID'=>$id]);do_action('edit_comment',$id,$c);return 1;
}
function wp_set_comment_status($comment_id,$comment_status,$wp_error=false) {
    global $wpdb;$map=['hold'=>'0','approve'=>'1','spam'=>'spam','trash'=>'trash'];if(!isset($map[$comment_status]))return false;
    $r=$wpdb->update($wpdb->comments,['comment_approved'=>$map[$comment_status]],['comment_ID'=>(int)$comment_id]);if($r===false)return false;do_action('wp_set_comment_status',$comment_id,$comment_status);return true;
}
function wp_get_comment_status($comment_id) { $c=get_comment($comment_id);if(!$c)return false;return match((string)$c->comment_approved){'1'=>'approved','0'=>'unapproved','spam'=>'spam','trash'=>'trash',default=>false}; }
function wp_trash_comment($comment_id) { return wp_set_comment_status($comment_id,'trash'); }
function wp_untrash_comment($comment_id) { return wp_set_comment_status($comment_id,'approve'); }
function wp_spam_comment($comment_id) { return wp_set_comment_status($comment_id,'spam'); }
function wp_delete_comment($comment_id,$force_delete=false) { global $wpdb;if(!rrw_wp_db_ready())return false;$wpdb->delete($wpdb->comments,['comment_ID'=>(int)$comment_id]);$wpdb->delete($wpdb->commentmeta,['comment_id'=>(int)$comment_id]);do_action('deleted_comment',$comment_id);return true; }
function wp_defer_comment_counting($defer=null) { return false; } function wp_defer_term_counting($defer=null) { return false; }
function wp_update_comment_count($post_id,$do_deferred=false) { return true; }
function wp_suspend_cache_invalidation($suspend=true) { return false; } 

/* ───────── Abfrage-Klassen ───────── */
class WP_User_Query {
    public $query_vars=[];public $results=[];public $total_users=0;
    public function __construct($query=null) { if($query!==null)$this->prepare_query($query)&&$this->query(); }
    public function prepare_query($query=[]) { $this->query_vars=wp_parse_args($query,['fields'=>'all','number'=>'','offset'=>0,'count_total'=>true]);return true; }
    public function query() { $a=$this->query_vars;$n=$a['number'];$all=get_users(['number'=>'']+$a);$this->total_users=count($all);$this->results=$n!==''?array_slice($all,(int)$a['offset'],(int)$n):$all; }
    public function get_results() { return $this->results; } public function get_total() { return $this->total_users; }
}
class WP_Comment_Query {
    public $query_vars=[];public $comments=[];public $found_comments=0;public $max_num_pages=0;
    public function __construct($query=null) { if($query!==null)$this->query($query); }
    public function query($query) { $this->query_vars=(array)$query;$r=get_comments($this->query_vars);$this->comments=is_array($r)?$r:[];$this->found_comments=count($this->comments);return $this->comments; }
    public function get_comments() { return $this->comments; }
}
class WP_Term_Query {
    public $query_vars=[];public $terms=null;
    public function __construct($query=null) { if($query!==null)$this->query($query); }
    public function query($query) { $this->query_vars=(array)$query;$r=get_terms($this->query_vars);$this->terms=is_wp_error($r)?[]:$r;return $this->terms; }
    public function get_terms() { return $this->terms; }
}
class WP_Meta_Query {
    public $queries=[];public $relation='AND';public $meta_table;public $meta_id_column;public $primary_table;public $primary_id_column;
    public function __construct($meta_query=false) { if($meta_query)$this->parse_query_vars(['meta_query'=>$meta_query]); }
    public function parse_query_vars($q) { $mq=$q['meta_query']??[];$this->relation=strtoupper((string)($mq['relation']??'AND'))==='OR'?'OR':'AND';foreach($mq as $k=>$c)if(is_array($c))$this->queries[]=$c;
        foreach(['meta_key','meta_value','meta_compare'] as $_)if(!empty($q['meta_key'])){ $this->queries[]=['key'=>$q['meta_key'],'value'=>$q['meta_value']??'','compare'=>$q['meta_compare']??'=',];break; } }
    public function get_sql($type,$primary_table,$primary_id_column,$context=null) {
        global $wpdb;$tbl=['post'=>$wpdb->postmeta,'user'=>$wpdb->usermeta,'term'=>$wpdb->termmeta,'comment'=>$wpdb->commentmeta][$type]??$wpdb->postmeta;$idc=['post'=>'post_id','user'=>'user_id','term'=>'term_id','comment'=>'comment_id'][$type]??'post_id';
        $join=[];$where=[];$i=0;
        foreach($this->queries as $q){
            $i++;$a='mt'.$i;$w=[];$cmp=strtoupper((string)($q['compare']??(isset($q['value'])&&is_array($q['value'])?'IN':'=')));
            if(isset($q['key']))$w[]=$wpdb->prepare("$a.meta_key = %s",$q['key']);
            if(array_key_exists('value',$q)&&!in_array($cmp,['EXISTS','NOT EXISTS'],true)){
                $v=$q['value'];$col=!empty($q['type'])&&in_array(strtoupper($q['type']),['NUMERIC','SIGNED','UNSIGNED','DECIMAL'],true)?"CAST($a.meta_value AS SIGNED)":"$a.meta_value";
                if(in_array($cmp,['IN','NOT IN'],true)){ $v=(array)$v;$w[]=$col.' '.$cmp.' ('.implode(',',array_map(fn($x)=>$wpdb->prepare('%s',$x),$v)).')'; }
                elseif(in_array($cmp,['BETWEEN','NOT BETWEEN'],true)){ $w[]=$col.' '.$cmp.' '.$wpdb->prepare('%s',$v[0]??'').' AND '.$wpdb->prepare('%s',$v[1]??''); }
                elseif(in_array($cmp,['LIKE','NOT LIKE'],true)){ $w[]=$col.' '.$cmp.' '.$wpdb->prepare('%s','%'.$wpdb->esc_like((string)$v).'%'); }
                elseif(in_array($cmp,['=','!=','>','>=','<','<='],true)){ $w[]=$col.' '.$cmp.' '.$wpdb->prepare('%s',$v); }
            }
            $join[]="INNER JOIN $tbl AS $a ON ($primary_table.$primary_id_column = $a.$idc)";if($w)$where[]='('.implode(' AND ',$w).')';
        }
        return ['join'=>$join?' '.implode(' ',$join).' ':'','where'=>$where?' AND ('.implode(" {$this->relation} ",$where).') ':''];
    }
}
class WP_Tax_Query {
    public $queries=[];public $relation='AND';
    public function __construct($tax_query=[]) { $this->relation=strtoupper((string)($tax_query['relation']??'AND'))==='OR'?'OR':'AND';foreach((array)$tax_query as $k=>$c)if(is_array($c))$this->queries[]=$c; }
    public function get_sql($primary_table,$primary_id_column) {
        global $wpdb;$join='';$where=[];$i=0;
        foreach($this->queries as $q){
            $i++;$a='tt'.$i;$ids=[];$field=$q['field']??'term_id';
            foreach((array)($q['terms']??[]) as $t){ $term=get_term_by($field==='term_taxonomy_id'?'id':$field,$t,$q['taxonomy']??'category');if($term)$ids[]=(int)$term->term_taxonomy_id; }
            $op=strtoupper((string)($q['operator']??'IN'));$join.=" LEFT JOIN {$wpdb->term_relationships} AS $a ON ($primary_table.$primary_id_column = $a.object_id)";
            $list=$ids?implode(',',$ids):'0';$where[]=$op==='NOT IN'?"$a.term_taxonomy_id NOT IN ($list)":"$a.term_taxonomy_id IN ($list)";
        }
        return ['join'=>$join,'where'=>$where?' AND ('.implode(" {$this->relation} ",$where).') ':''];
    }
}
class WP_Date_Query { public $queries=[];public function __construct($q=[],$column='post_date') { $this->queries=(array)$q; } public function get_sql() { return ''; } }
class WP_Http_Cookie { public $name;public $value;public $expires;public $path;public $domain;public function __construct($d,$url='') { foreach((array)$d as $k=>$v)$this->$k=$v; } public function getHeaderValue() { return $this->name.'='.$this->value; } public function getFullHeader() { return 'Cookie: '.$this->getHeaderValue(); } }
class WP_Ajax_Response {
    public $responses=[];
    public function __construct($args='') { if($args)$this->add($args); }
    public function add($args='') { $a=wp_parse_args($args,['what'=>'object','action'=>false,'id'=>'0','old_id'=>false,'position'=>1,'data'=>'','supplemental'=>[]]);$this->responses[]=$a;return $a['id']; }
    public function send() { header('Content-Type: text/xml; charset=UTF-8');echo "<?xml version='1.0' standalone='yes'?><wp_ajax>";foreach($this->responses as $r)echo '<response action="'.esc_attr((string)$r['action']).'"><'.$r['what'].' id="'.esc_attr((string)$r['id']).'"><response_data><![CDATA['.(string)$r['data'].']]></response_data></'.$r['what'].'></response>';echo '</wp_ajax>';if(!empty($GLOBALS['rrw_wp_die_throws']))throw new RRW_WP_Die('xml',200);die(); }
}

/* ───────── REST-Hilfsfunktionen ───────── */
class WP_REST_Server_Shim extends WP_REST_Server {
    public function dispatch($request) { return rest_do_request($request); }
    public function get_routes($ns='') { require_once dirname(__DIR__).'/rest.php';$r=rrw_wp_rest_routes();$o=[];foreach($r as $k=>$eps)if($ns===''||str_starts_with(ltrim($k,'/'),trim($ns,'/').'/'))$o[$k]=$eps;return $o; }
    public function get_namespaces() { return array_values(array_unique(array_map(fn($k)=>preg_match('#^/([^/]+(?:/v\d+)?)/#',$k.'/',$m)?$m[1]:'',array_keys($this->get_routes())))); }
    public function send_header($k,$v) { if(!headers_sent())header($k.': '.$v); }
    public function response_to_data($r,$embed=false) { return $r instanceof WP_REST_Response?$r->get_data():$r; }
    public static function get_compact_response_links($response) { return []; } public static function get_response_links($response) { return []; } public function get_raw_data($response,$embed=false) { return $this->response_to_data($response,$embed); } public function embed_links($data,$embed=true) { return $data; } public function get_json_last_error() { return false; } public function get_headers($server) { return []; } public function get_max_batch_size() { return 25; } public function get_route_options($route) { return null; }
    public function get_index($req=null) { return ['name'=>get_bloginfo('name'),'url'=>home_url(),'namespaces'=>$this->get_namespaces()]; }
}
function rest_get_server() { static $s=null;return $s=$s??new WP_REST_Server_Shim(); }
function rest_get_url_prefix() { return apply_filters('rest_url_prefix','wp-json'); }
function rest_do_request($request) {
    require_once dirname(__DIR__).'/rest.php';
    if(is_string($request))$request=new WP_REST_Request('GET',$request);
    $params=$request->get_params();$m=$request->get_method();$raw=$request->get_body();
    if($raw===''&&$m!=='GET'&&$request->get_json_params())$raw=(string)wp_json_encode($request->get_json_params());
    $r=rrw_wp_rest_dispatch($m,$request->get_route(),$m==='GET'?array_merge($params,$request->get_query_params()):$request->get_query_params(),$raw,['Content-Type'=>$request->get_header('content_type')??'application/json']);
    $data=json_decode($r['body'],true);
    if(is_array($data)&&isset($data['code'],$data['message'])&&$r['status']>=400)return new WP_REST_Response($data,$r['status']);
    return new WP_REST_Response($data,$r['status']);
}
function wp_is_serving_rest_request() { return !empty($GLOBALS['rrw_wp_serving_rest']); }
function wp_is_rest_endpoint() { return wp_is_serving_rest_request(); }
function rest_is_integer($v) { return is_numeric($v)&&round((float)$v)==(float)$v; }
function rest_is_boolean($v) { if(is_bool($v))return true;if(is_string($v))return in_array(strtolower($v),['false','true','0','1'],true);if(is_int($v))return in_array($v,[0,1],true);return false; }
function rest_sanitize_boolean($v) { if(is_string($v)){ $v=strtolower($v);if(in_array($v,['false','0'],true))return false; }return (bool)$v; }
function wp_validate_boolean($v) { if(is_string($v)&&strtolower($v)==='false')return false;return (bool)$v; }
function rest_is_ip_address($ip) { return filter_var($ip,FILTER_VALIDATE_IP)?$ip:false; }
function rest_parse_date($date,$force_utc=false) { $t=strtotime((string)$date);return $t?:false; }
function rest_get_date_with_gmt($date,$is_utc=false) { $t=strtotime((string)$date);return $t?[gmdate('Y-m-d H:i:s',$t),gmdate('Y-m-d H:i:s',$t)]:null; }
function rest_get_avatar_sizes() { return [24,48,96]; } function rest_get_avatar_urls($c) { return []; }
function rest_is_field_included($field,$fields) { return empty($fields)||in_array($field,(array)$fields,true); }
function rest_convert_error_to_response($error) { $st=(int)((array)$error->get_error_data())['status']??500;return new WP_REST_Response(['code'=>$error->get_error_code(),'message'=>$error->get_error_message(),'data'=>$error->get_error_data()],$st?:500); }
function rest_get_combining_operation_error($value,$param,$errors) { return new WP_Error('rest_no_matching_schema',$param.' ist ungültig.'); }
function register_rest_field($object_type,$attribute,$args=[]) { $GLOBALS['wp_rest_additional_fields'][(string)(is_array($object_type)?implode(',',$object_type):$object_type)][$attribute]=$args; }
function rest_validate_value_from_schema($value,$args,$param='') {
    $types=(array)($args['type']??[]);if(!$types)return true;
    foreach($types as $t){ $ok=match($t){'integer'=>rest_is_integer($value),'number'=>is_numeric($value),'boolean'=>rest_is_boolean($value),'array'=>is_array($value),'object'=>is_array($value)||is_object($value),'string'=>is_scalar($value),'null'=>$value===null,default=>true};if($ok)break; }
    if(empty($ok))return new WP_Error('rest_invalid_type',sprintf('%1$s ist nicht vom Typ %2$s.',$param,implode(',',$types)),['param'=>$param]);
    if(isset($args['enum'])&&!in_array($value,$args['enum'],true))return new WP_Error('rest_not_in_enum',sprintf('%1$s ist kein erlaubter Wert.',$param),['param'=>$param]);
    if(isset($args['minimum'])&&is_numeric($value)&&$value<$args['minimum'])return new WP_Error('rest_out_of_bounds',sprintf('%1$s ist zu klein.',$param),['param'=>$param]);
    if(isset($args['maximum'])&&is_numeric($value)&&$value>$args['maximum'])return new WP_Error('rest_out_of_bounds',sprintf('%1$s ist zu groß.',$param),['param'=>$param]);
    return true;
}
function rest_sanitize_value_from_schema($value,$args,$param='') {
    $t=(array)($args['type']??['string']);$t=$t[0];
    return match($t){'integer'=>(int)$value,'number'=>0+$value,'boolean'=>rest_sanitize_boolean($value),'array'=>(array)$value,'string'=>is_scalar($value)?(string)$value:'',default=>$value};
}
function rest_validate_request_arg($value,$request,$param) { $a=$request->get_attributes()['args'][$param]??null;return $a?rest_validate_value_from_schema($value,$a,$param):true; }
function rest_sanitize_request_arg($value,$request,$param) { $a=$request->get_attributes()['args'][$param]??null;return $a?rest_sanitize_value_from_schema($value,$a,$param):$value; }
function rest_get_endpoint_args_for_schema($schema,$method='POST') { $o=[];foreach((array)($schema['properties']??[]) as $k=>$p){ if(!empty($p['readonly']))continue;$o[$k]=array_intersect_key($p,['type'=>1,'enum'=>1,'default'=>1,'description'=>1,'format'=>1])+(!empty($p['required'])&&$method==='POST'?['required'=>true]:[]); }return $o; }

/* ───────── Hilfsfunktionen ───────── */
function wp_admin_notice($message,$args=[]) { $a=wp_parse_args($args,['type'=>'','dismissible'=>false,'id'=>'','additional_classes'=>[],'paragraph_wrap'=>true]);$cls='notice'.($a['type']?' notice-'.$a['type']:'').($a['dismissible']?' is-dismissible':'').($a['additional_classes']?' '.implode(' ',(array)$a['additional_classes']):'');echo '<div'.($a['id']?' id="'.esc_attr($a['id']).'"':'').' class="'.esc_attr($cls).'">'.($a['paragraph_wrap']?'<p>'.$message.'</p>':$message).'</div>'; }
function wp_trigger_error($function_name,$message,$error_level=E_USER_NOTICE) { rrw_wp_log(($function_name?$function_name.': ':'').$message); }
function wp_fast_hash($message) { return '$generic$'.hash('sha256',(string)$message); } function wp_verify_fast_hash($message,$hash) { return hash_equals($hash,wp_fast_hash($message)); }
function _nx_noop($singular,$plural,$context,$domain=null) { return ['singular'=>$singular,'plural'=>$plural,'context'=>$context,'domain'=>$domain,0=>$singular,1=>$plural,2=>$context]; }
function wp_cache_get_multiple($keys,$group='',$force=false) { $o=[];foreach($keys as $k)$o[$k]=wp_cache_get($k,$group,$force);return $o; }
function wp_cache_set_multiple($data,$group='',$expire=0) { $o=[];foreach($data as $k=>$v)$o[$k]=wp_cache_set($k,$v,$group,$expire);return $o; }
function wp_cache_delete_multiple($keys,$group='') { $o=[];foreach($keys as $k)$o[$k]=wp_cache_delete($k,$group);return $o; }
function wp_cache_add_multiple($data,$group='',$expire=0) { $o=[];foreach($data as $k=>$v)$o[$k]=wp_cache_add($k,$v,$group,$expire);return $o; }
function wp_cache_supports($feature) { return in_array($feature,['add_multiple','set_multiple','get_multiple','delete_multiple','flush_runtime'],true); }
function wp_cache_flush_runtime() { return wp_cache_flush(); } function wp_cache_flush_group($g) { return wp_cache_flush(); }
function wp_cache_close() { return true; } function wp_cache_switch_to_blog($id) {} function wp_cache_reset() {}
function update_meta_cache($type,$ids) { return []; } function update_termmeta_cache($ids) { return []; } function update_post_thumbnail_cache($q=null) {}
function wp_clean_plugins_cache($clear=true) {} function wp_clean_themes_cache($clear=true) {} function wp_update_plugins() {} function wp_update_themes() {}
function get_plugin_updates() { return []; } function get_core_updates($o=[]) { return []; } function get_dropins() { return []; } function wp_get_mu_plugins() { return []; } function get_mu_plugins() { return []; }
function flush_rewrite_rules($hard=true) { return true; } function add_rewrite_endpoint($name,$places,$query_var=true) { $GLOBALS['rrw_wp_rewrite_endpoints'][$name]=[$places,$query_var]; } function add_rewrite_rule($regex,$query,$after='bottom') { $GLOBALS['rrw_wp_rewrite_rules'][$regex]=$query; } function add_rewrite_tag($tag,$regex,$query='') {} function add_permastruct($name,$struct,$args=[]) {}
function wp_get_nocache_headers() { return ['Expires'=>'Wed, 11 Jan 1984 05:00:00 GMT','Cache-Control'=>'no-cache, must-revalidate, max-age=0']; }
function wp_sanitize_redirect($location) { return preg_replace('/[^a-z0-9-~+_.?#=!&;,\/:%@$\|*\'()\[\]\\x80-\\xff]/i','',(string)$location); }
function wp_get_server_protocol() { $p=$_SERVER['SERVER_PROTOCOL']??'HTTP/1.1';return in_array($p,['HTTP/1.1','HTTP/2','HTTP/2.0','HTTP/3','HTTP/1.0'],true)?$p:'HTTP/1.0'; }
function get_status_header_desc($code) { return [200=>'OK',201=>'Created',204=>'No Content',301=>'Moved Permanently',302=>'Found',304=>'Not Modified',400=>'Bad Request',401=>'Unauthorized',403=>'Forbidden',404=>'Not Found',410=>'Gone',500=>'Internal Server Error',503=>'Service Unavailable'][(int)$code]??''; }
function get_the_generator($type='') { return '<meta name="generator" content="WordPress '.RRW_WP_VERSION.'" />'; }
function wp_slash_strings_only($v) { return map_deep($v,fn($x)=>is_string($x)?addslashes($x):$x); }
function wp_is_uuid($uuid,$version=null) { return is_string($uuid)&&(bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',$uuid); }
function is_protected_meta($meta_key,$meta_type='') { return apply_filters('is_protected_meta',str_starts_with((string)$meta_key,'_'),$meta_key,$meta_type); }
function delete_post_meta_by_key($post_meta_key) { return delete_metadata('post',null,$post_meta_key,'',true); }
function _get_meta_table($type) { global $wpdb;return $wpdb->{$type.'meta'}??false; }
function get_post_statuses() { return ['draft'=>'Entwurf','pending'=>'Ausstehender Review','private'=>'Privat','publish'=>'Veröffentlicht']; }
function get_post_datetime($post=null,$field='date',$source='local') { $p=get_post($post);if(!$p)return false;$f=$field==='modified'?'post_modified':'post_date';$g=$f.'_gmt';try{ return $source==='gmt'?new DateTimeImmutable($p->$g?:$p->$f,new DateTimeZone('UTC')):new DateTimeImmutable($p->$f,wp_timezone()); }catch(Throwable $e){ return false; } }
function get_post_timestamp($post=null,$field='date') { $d=get_post_datetime($post,$field);return $d?$d->getTimestamp():false; }
function current_action() { return current_filter(); }
function get_bloginfo_rss($show='') { return strip_tags(get_bloginfo($show)); }
function wp_check_post_lock($post_id) { return false; } 
function wp_revisions_enabled($post) { return false; } function wp_delete_post_revision($id) { return false; } function wp_get_post_autosave($id,$uid=0) { return false; } function wp_create_post_autosave($a,$f=false) { return 0; } 
function get_extended($post) { $p=preg_split('/<!--more(.*?)?-->/',(string)$post,2);return ['main'=>$p[0],'extended'=>$p[1]??'','more_text'=>''];  }
function get_preview_post_link($post=null,$args=[],$url=null) { return get_permalink($post); }
function get_delete_post_link($post=0,$deprecated='',$force=false) { return false; } function get_edit_user_link($id=null) { return admin_url('profile.php'); }
function get_dashboard_url($uid=0,$path='',$scheme='admin') { return admin_url($path); }
function get_privacy_policy_url() { return ''; } function get_page_templates($post=null,$post_type='page') { return []; }
function get_home_path() { return rtrim(dirname(__DIR__,3),'/').'/'; }
function wp_get_available_translations() { return []; }
function get_sample_permalink($post,$title=null,$name=null) { return [get_permalink($post),(string)get_post($post)->post_name]; }
function url_to_postid($url) { $path=trim((string)parse_url((string)$url,PHP_URL_PATH),'/');if($path==='')return 0;$p=get_page_by_path($path,OBJECT,['post','page']);if($p)return (int)$p->ID;foreach(get_posts(['name'=>basename($path),'post_type'=>'any','numberposts'=>1]) as $q)return (int)$q->ID;return 0; }
function single_month_title($prefix='',$display=true) { $m=get_query_var('monthnum');$y=get_query_var('year');if(!$m||!$y)return false;$t=$prefix.date_i18n('F Y',mktime(0,0,0,(int)$m,1,(int)$y));if($display)echo $t;return $t; }
function term_description($term=0,$taxonomy='post_tag') { $t=get_term($term,$taxonomy);return $t&&!is_wp_error($t)?(string)$t->description:''; }
function get_category_parents($category,$link=false,$separator='/',$nicename=false,$deprecated=[]) { $c=get_category($category);return $c&&!is_wp_error($c)?($link?'<a href="'.esc_url(get_category_link($c)).'">'.esc_html($c->name).'</a>':$c->name).$separator:''; }
function wp_get_split_term($old_term_id,$taxonomy) { return false; } function _get_term_hierarchy($taxonomy) { return []; }
function get_objects_in_term($term_ids,$taxonomies,$args=[]) { global $wpdb;if(!rrw_wp_db_ready())return [];$ids=implode(',',array_map('intval',(array)$term_ids));return $wpdb->get_col("SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ($ids)"); }
function wp_dropdown_categories($args='') { $a=wp_parse_args($args,['show_option_all'=>'','hide_empty'=>1,'name'=>'cat','id'=>'','class'=>'postform','selected'=>0,'echo'=>1,'taxonomy'=>'category','value_field'=>'term_id']);$terms=get_terms(['taxonomy'=>$a['taxonomy'],'hide_empty'=>(bool)$a['hide_empty']]);$o='<select name="'.esc_attr($a['name']).'" id="'.esc_attr($a['id']?:$a['name']).'" class="'.esc_attr($a['class']).'">';if($a['show_option_all'])$o.='<option value="0">'.esc_html($a['show_option_all']).'</option>';foreach(is_wp_error($terms)?[]:$terms as $t){ $v=$t->{$a['value_field']};$o.='<option value="'.esc_attr((string)$v).'"'.selected((string)$a['selected'],(string)$v,false).'>'.esc_html($t->name).'</option>'; }$o.='</select>';if($a['echo'])echo $o;return $o; }
function validate_username($u) { return (bool)preg_match('/^[a-z0-9_.@\-]{1,60}$/i',(string)$u)&&$u===sanitize_user($u,true); }
function get_password_reset_key($user) { return new WP_Error('no_reset','Passwort-Zurücksetzen läuft über das CMS.'); }
function wp_lostpassword_url($redirect='') { return home_url('/'); } function wp_logout() { do_action('wp_logout'); }
function wp_privacy_anonymize_data($type,$data='') { return match($type){'email'=>'deleted@site.invalid','url'=>'https://site.invalid','ip'=>'0.0.0.0','date'=>'0000-00-00 00:00:00','text'=>'[gelöscht]',default=>'[gelöscht]'}; }
function _cleanup_header_comment($str) { return trim(preg_replace('/\s*(?:\*\/|\?>).*/','',(string)$str)); }
function iso8601_timezone_to_offset($tz) { if($tz==='Z')return 0;$s=str_starts_with($tz,'-')?-1:1;[$h,$m]=array_pad(explode(':',ltrim($tz,'+-')),2,0);return $s*((int)$h*3600+(int)$m*60); }
function wp_get_word_count_type() { return 'words'; }
function kses_init_filters() {} function kses_remove_filters() {} function kses_init() {} function wp_kses_no_null($content,$options=null) { return str_replace("\0",'',(string)$content); }
function wp_get_list_item_separator() { return ', '; }
function copy_dir($from,$to,$skip=[]) { if(!is_dir($from))return new WP_Error('copy_failed','Quelle fehlt');@mkdir($to,0775,true);foreach(scandir($from) as $f){ if($f==='.'||$f==='..'||in_array($f,$skip,true))continue;if(is_dir("$from/$f")){ $r=copy_dir("$from/$f","$to/$f");if(is_wp_error($r))return $r; }else @copy("$from/$f","$to/$f"); }return true; }
function do_robots() { header('Content-Type: text/plain; charset=utf-8');echo "User-agent: *\nDisallow: /cms/\n"; }
function get_column_headers($screen) { static $h=[];$s=is_string($screen)?(object)['id'=>$screen]:$screen;$id=(string)($s->id??'');if(!isset($h[$id]))$h[$id]=apply_filters("manage_{$id}_columns",[]);return $h[$id]; } function get_hidden_columns($screen) { $s=is_string($screen)?(object)['id'=>$screen]:$screen;return (array)apply_filters('hidden_columns',[],$s,false); } // RRW_WP_Screen steht in admin.php; Listentabellen (WP_List_Table) und convert_to_screen() brauchen sie auch außerhalb der Verwaltung → bei Bedarf nachladen
spl_autoload_register(function($c){ if($c==='RRW_WP_Screen')require_once dirname(__DIR__).'/admin.php'; });
function convert_to_screen($hook_name) { if(is_object($hook_name))return $hook_name;$s=new RRW_WP_Screen();$s->id=$s->base=(string)$hook_name;return $s; } function _get_list_table($class_name,$args=[]) { return class_exists($class_name)?new $class_name($args):false; } function add_screen_option($o,$a=[]) {} function set_screen_options() {} function do_meta_boxes($s,$c,$o) {} function get_user_setting($n,$d=false) { return $d; } function set_user_setting($n,$v) { return false; } function wp_add_dashboard_widget($id,$name,$cb,$cc=null,$args=null) {} function _post_states($post,$display=true) { return ''; } function get_submit_button($text='',$type='primary large',$name='submit',$wrap=true,$other=''){ ob_start();submit_button($text?:null,$type,$name,$wrap);return ob_get_clean(); } function post_categories_meta_box($post,$box) {}
function register_importer($id,$name,$desc,$cb) {} function wp_import_handle_upload() { return ['error'=>'Import wird hier nicht unterstützt.']; } function wp_import_cleanup($id) {}
function wp_style_engine_get_styles($block_styles,$options=[]) { return ['css'=>'','declarations'=>[],'classnames'=>'']; }
function wp_enqueue_script_module($id,$src='',$deps=[],$version=false) { $GLOBALS['rrw_wp_script_modules'][$id]=true;wp_enqueue_script($id,$src,[],$version,true); }
function wp_register_script_module($id,$src,$deps=[],$version=false) { $GLOBALS['rrw_wp_script_modules'][$id]=true;wp_register_script($id,$src,[],$version,true); }
function wp_dequeue_script_module($id) { wp_dequeue_script($id); }
add_filter('script_loader_tag',function($tag,$handle){ return !empty($GLOBALS['rrw_wp_script_modules'][$handle])?str_replace('<script ','<script type="module" ',$tag):$tag; },10,2);
function wp_interactivity_state($ns=null,$state=[]) { return []; } function wp_interactivity_config($ns=null,$cfg=[]) { return []; } function wp_interactivity_data_wp_context($ctx,$ns='') { return 'data-wp-context=\''.esc_attr((string)wp_json_encode($ctx)).'\''; } function wp_interactivity_get_context($ns=null) { return []; } function wp_interactivity_process_directives($html) { return $html; }
function is_wp_version_compatible($required) { return empty($required)||version_compare(RRW_WP_VERSION,(string)$required,'>='); }
function is_php_version_compatible($required) { return empty($required)||version_compare(PHP_VERSION,(string)$required,'>='); }
if(!function_exists('getallheaders')){ function getallheaders() { $h=[];foreach($_SERVER as $k=>$v)if(str_starts_with($k,'HTTP_'))$h[str_replace(' ','-',ucwords(strtolower(str_replace('_',' ',substr($k,5)))))]=$v;return $h; } }
if(!function_exists('apache_setenv')){ function apache_setenv($n,$v,$s=true) { return false; } }
if(!function_exists('fastcgi_finish_request')){ function fastcgi_finish_request() { return false; } }

/* ───────── Rewrite-Konstanten und Einbettungen ───────── */
foreach(['EP_NONE'=>0,'EP_PERMALINK'=>1,'EP_ATTACHMENT'=>2,'EP_DATE'=>4,'EP_YEAR'=>8,'EP_MONTH'=>16,'EP_DAY'=>32,'EP_ROOT'=>64,'EP_COMMENTS'=>128,'EP_SEARCH'=>256,'EP_CATEGORIES'=>512,'EP_TAGS'=>1024,'EP_AUTHORS'=>2048,'EP_PAGES'=>4096,'EP_ALL_ARCHIVES'=>3132,'EP_ALL'=>8191] as $k=>$v)if(!defined($k))define($k,$v);
class WP_Embed {
    public $handlers=[];public $post_ID;public $usecache=true;public $linkifunknown=true;public $last_attr=[];public $last_url='';
    public function __construct() {}
    public function run_shortcode($content) { return do_shortcode($content); }
    public function autoembed($content) { return (string)$content; }
    public function maybe_make_link($url) { return $this->linkifunknown?'<a href="'.esc_url($url).'">'.esc_html($url).'</a>':false; }
    public function shortcode($attr,$url='') { return $this->maybe_make_link($url?:($attr[0]??'')); }
    public function register_handler($id,$regex,$cb,$priority=10) {} public function unregister_handler($id,$priority=10) {}
    public function delete_oembed_caches($id) {} public function cache_oembed($id) {}
}
$GLOBALS['wp_embed']=$GLOBALS['wp_embed']??new WP_Embed();
function wp_embed_register_handler($id,$regex,$cb,$priority=10) {} function wp_embed_unregister_handler($id,$priority=10) {} function wp_embed_defaults($url='') { return ['width'=>500,'height'=>750]; } function _wp_oembed_get_object() { return new class { public function get_html($url,$args=[]) { return false; } public function get_data($url,$args=[]) { return false; } public function get_provider($url,$args=[]) { return false; } }; }

/* ───────── Inhalts-Filter, die WordPress standardmäßig setzt ───────── */
function convert_smilies($text) { return (string)$text; } function prepend_attachment($content) { return (string)$content; }
function wp_filter_content_tags($content,$context=null) { return (string)$content; } function wp_replace_insecure_home_url($content) { return (string)$content; } function capital_P_dangit($text) { return (string)$text; }
function wp_make_content_images_responsive($content) { return (string)$content; } function wp_targeted_link_rel($text) { return (string)$text; } function wp_rel_nofollow($text) { return preg_replace('/<a /i','<a rel="nofollow" ',(string)$text); }
function wp_staticize_emoji($text) { return (string)$text; } function wp_encode_emoji($text) { return (string)$text; } function wp_filter_oembed_result($html,$data,$url) { return $html; }
function wp_get_attachment_link($id=0,$size='thumbnail',$permalink=false,$icon=false,$text=false,$attr='') { $u=wp_get_attachment_url($id);$t=$text?:basename((string)$u);return $u?'<a href="'.esc_url($u).'">'.esc_html($t).'</a>':''; }

/* ───────── $wp_scripts / $wp_styles als Objekte (Plugins lesen ->registered, ->queue) ───────── */
class RRW_WP_Dependency { public $handle;public $src;public $deps=[];public $ver=false;public $args='all';public $extra=[];public $textdomain;public $translations_path;
    public function __construct($h,$it,$kind) { $this->handle=$h;$this->src=$it['src'];$this->deps=$it['deps'];$this->ver=$it['ver'];$this->args=$kind==='scripts'?($it['extra']?:false):$it['extra'];if($kind==='scripts'&&$it['extra'])$this->extra['group']=1; } }
class RRW_WP_Dependencies {
    private $kind;
    /** Ohne Argument (new WP_Scripts() in Plugins): Registrierung zurücksetzen und die Kern-Skripte neu eintragen, wie WordPress es tut. */
    public function __construct($kind=null) {
        if($kind!==null){ $this->kind=$kind;return; }
        $this->kind=$this instanceof WP_Styles?'styles':'scripts';
        $GLOBALS['rrw_wp_'.$this->kind]=['reg'=>[],'queue'=>[],'done'=>[]];
        if($this->kind==='scripts'&&function_exists('rrw_wp_core_register')){ rrw_wp_core_register(true); }
    }
    public function __get($n) {
        $g=$GLOBALS['rrw_wp_'.$this->kind];
        if($n==='registered'){ $o=[];foreach($g['reg'] as $h=>$it)$o[$h]=new RRW_WP_Dependency($h,$it,$this->kind);return $o; }
        if($n==='queue')return array_keys($g['queue']);if($n==='done')return array_keys($g['done']);if($n==='to_do')return [];
        return null;
    }
    public function add($h,$src,$deps=[],$ver=false,$args=null) { return $this->kind==='scripts'?wp_register_script($h,$src,$deps,$ver,$args):wp_register_style($h,$src,$deps,$ver,$args??'all'); }
    public function enqueue($h) { $this->kind==='scripts'?wp_enqueue_script($h):wp_enqueue_style($h); } public function dequeue($h) { $this->kind==='scripts'?wp_dequeue_script($h):wp_dequeue_style($h); }
    public function remove($h) { $this->kind==='scripts'?wp_deregister_script($h):wp_deregister_style($h); } public function query($h,$list='registered') { return $this->registered[$h]??false; }
    public function add_inline_script($h,$data,$pos='after') { return wp_add_inline_script($h,$data,$pos); }
    public function add_data($h,$k,$v) { return true; } public function get_data($h,$k) { return false; } public function print_inline_script($h,$pos='after',$echo=true) { return false; }
}
 
$GLOBALS['wp_scripts']=new RRW_WP_Dependencies('scripts');$GLOBALS['wp_styles']=new RRW_WP_Dependencies('styles');
class WP_Scripts extends RRW_WP_Dependencies {} class WP_Styles extends RRW_WP_Dependencies {}
function wp_scripts() { return $GLOBALS['wp_scripts']=$GLOBALS['wp_scripts']??new RRW_WP_Dependencies('scripts'); } function wp_styles() { return $GLOBALS['wp_styles']=$GLOBALS['wp_styles']??new RRW_WP_Dependencies('styles'); }

/* ───────── Kern-REST-Controller als Basisklassen (Plugins erweitern sie; die Kernrouten liefert cms/wp/rest.php) ───────── */
if(!class_exists('WP_REST_Users_Controller')){
class WP_REST_Users_Controller extends WP_REST_Controller {
    public $post_type="";public $taxonomy="";
    public function __construct($t="") { $this->rest_base="users"; }
    public function get_items($request) { return new WP_REST_Response([],200); }
    public function get_item($request) { return $this->get_current_item($request); }
    public function get_current_item($request) {
        $id=function_exists('get_current_user_id')?(int)get_current_user_id():0;if(!$id)return new WP_Error('rest_not_logged_in','Du bist derzeit nicht angemeldet.',['status'=>401]);
        $u=get_userdata($id);$caps=new stdClass;foreach((array)($GLOBALS['rrw_wp_user']['caps']??[]) as $k=>$v){ if(is_int($k))$caps->$v=true;else $caps->$k=(bool)$v; }
        return new WP_REST_Response(['id'=>$id,'username'=>(string)($u->user_login??'admin'),'name'=>(string)($u->display_name??'admin'),'first_name'=>'','last_name'=>'','email'=>(string)($u->user_email??''),'url'=>'','description'=>'','link'=>home_url('/'),'locale'=>get_locale(),'nickname'=>'','slug'=>(string)($u->user_nicename??'admin'),'roles'=>['administrator'],'registered_date'=>gmdate('c'),'capabilities'=>$caps,'extra_capabilities'=>new stdClass,'avatar_urls'=>[],'meta'=>[]],200);
    }
}
}
foreach(['Posts','Terms','Users','Attachments','Themes','Settings','Comments','Revisions','Autosaves','Post_Types','Post_Statuses','Taxonomies','Menu_Items','Menus','Menu_Locations','Widgets','Widget_Types','Sidebars','Templates','Template_Revisions','Global_Styles','Block_Types','Blocks','Block_Directory','Pattern_Directory','Search','Plugins','Site_Health','Application_Passwords','Font_Families','Font_Faces','Font_Collections','Navigation_Fallback','URL_Details','Edit_Site_Export','Block_Renderer','Block_Patterns','Block_Pattern_Categories','Menu_Items_Controller_Dummy'] as $__c){
    $__n='WP_REST_'.$__c.'_Controller';if(!class_exists($__n))eval('class '.$__n.' extends WP_REST_Controller { public $post_type=""; public $taxonomy=""; public function __construct($t="") { $this->rest_base=""; } public function get_items($request) { return new WP_REST_Response([],200); } public function get_item($request) { return new WP_Error("rest_no_route","Nicht verfügbar.",["status"=>404]); } }');
}
unset($__c,$__n);
class WP_Http {
    const OK = 200; const CREATED = 201; const ACCEPTED = 202; const NO_CONTENT = 204; const MOVED_PERMANENTLY = 301; const FOUND = 302; const NOT_MODIFIED = 304; const BAD_REQUEST = 400; const UNAUTHORIZED = 401; const FORBIDDEN = 403; const NOT_FOUND = 404; const METHOD_NOT_ALLOWED = 405; const INTERNAL_SERVER_ERROR = 500; const SERVICE_UNAVAILABLE = 503;
    public function request($url,$args=[]) { return wp_remote_request($url,$args); } public function get($url,$args=[]) { return wp_remote_get($url,$args); } public function post($url,$args=[]) { return wp_remote_post($url,$args); } public function head($url,$args=[]) { return wp_remote_head($url,$args); }
    public static function processResponse($res) { $p=explode("\r\n\r\n",(string)$res,2);return ['headers'=>$p[0],'body'=>$p[1]??'']; } public static function is_ip_address($maybe_ip) { return (bool)filter_var($maybe_ip,FILTER_VALIDATE_IP); }
    public static function normalize_cookies($cookies) { return []; } public static function make_absolute_url($maybe_relative,$url) { return $maybe_relative; } public static function handle_redirects($url,$args,$response) { return false; }
    public static function validate_redirects($location) {} public static function browser_redirect_compatibility($url,$args,$response) {}
}
class WP_HTTP_Proxy { public function is_enabled() { return false; } }
class WP_Http_Curl {} class WP_Http_Streams {}
class WP_HTTP_Response { public $data;public $headers;public $status;public function __construct($data=null,$status=200,$headers=[]) { $this->data=$data;$this->status=$status;$this->headers=$headers; } public function get_data() { return $this->data; } public function get_status() { return $this->status; } public function get_headers() { return $this->headers; } }
function mbstring_binary_safe_encoding($reset=false) { return null; } function reset_mbstring_encoding() { return null; }

/* ───────── weitere Kleinigkeiten aus WooCommerce / Yoast / Elementor ───────── */
function wp_safe_remote_get($url,$args=[]) { return wp_remote_get($url,$args); } function wp_safe_remote_request($url,$args=[]) { return wp_remote_request($url,$args); }
function _wp_array_get($input_array,$path,$default_value=null) { if(!is_array($input_array)||empty($path))return $default_value;foreach($path as $k){ if(is_array($input_array)&&isset($input_array[$k]))$input_array=$input_array[$k];else return $default_value; }return $input_array; }
function _wp_array_set(&$input_array,$path,$value=null) { if(!is_array($input_array)||empty($path))return;$cur=&$input_array;foreach($path as $i=>$k){ if($i===count($path)-1){ $cur[$k]=$value; }else{ if(!isset($cur[$k])||!is_array($cur[$k]))$cur[$k]=[];$cur=&$cur[$k]; } } }
function get_query_template($type,$templates=[]) { return locate_template(array_merge((array)$templates,[$type.'.php'])); }
function wp_set_auth_cookie($user_id,$remember=false,$secure='',$token='') {} function wp_validate_auth_cookie($cookie='',$scheme='') { return false; } function wp_clear_auth_cookie() {} 
function wp_check_comment_data_max_lengths($comment_data) { return true; }
function get_taxonomy_labels($tax) { return (object)(array)($tax->labels??[]); }
function wp_filter_kses($data) { return addslashes(wp_kses(stripslashes((string)$data),'post')); }
function sanitize_sql_orderby($orderby) { return preg_match('/^\s*(?:[a-z0-9_.`]+(?:\s+(?:ASC|DESC))?\s*,?\s*)+$/i',(string)$orderby)?$orderby:false; }
function validate_file($file,$allowed_files=[]) { $file=(string)$file;if(str_contains($file,'..')||str_contains($file,"\0")||preg_match('#^[a-z]:#i',$file))return 1;if($allowed_files&&!in_array($file,$allowed_files,true))return 3;return 0; }
function get_weekstartend($mysqlstring,$start_of_week='') { $t=strtotime((string)$mysqlstring);$sow=(int)($start_of_week===''?get_option('start_of_week',1):$start_of_week);$d=(int)gmdate('w',$t);$diff=($d-$sow+7)%7;$s=$t-$diff*86400;$s=strtotime(gmdate('Y-m-d',$s).' UTC');return ['start'=>$s,'end'=>$s+604799]; }
function get_the_category_by_id($cat_id) { $c=get_term($cat_id,'category');return is_wp_error($c)||!$c?$c:$c->name; }
function get_http_origin() { return $_SERVER['HTTP_ORIGIN']??''; } function is_allowed_http_origin($origin=null) { $o=$origin??get_http_origin();return $o===''||untrailingslashit($o)===untrailingslashit(home_url()); }
function wp_update_term_count_now($terms,$taxonomy) { return true; } 
function rest_filter_response_fields($response,$server,$request) { return $response; } function _admin_search_query() { echo esc_attr(wp_unslash($_REQUEST['s']??'')); }
function wp_sprintf_l($pattern,$args) { return implode(', ',(array)$args); } function post_tags_meta_box($post,$box) {} function list_meta($meta) {}
function sanitize_title_for_query($title) { return sanitize_title($title,'','query'); }
function wp_is_application_passwords_available() { return false; } function wp_is_application_passwords_supported() { return false; } function wp_is_application_passwords_available_for_user($u) { return false; }
function wp_scripts_get_suffix($type='') { return '.min'; } function _draft_or_post_title($post=0) { $t=get_the_title($post);return $t===''?'(ohne Titel)':$t; }
function get_term_parents_list($term_id,$taxonomy,$args=[]) { $t=get_term($term_id,$taxonomy);return is_wp_error($t)||!$t?'':esc_html($t->name).'/'; }
function activate_plugins($plugins,$redirect='',$network_wide=false,$silent=false) { foreach((array)$plugins as $p)activate_plugin($p,'',false,true);return true; }
function is_post_status_viewable($post_status) { $s=get_post_status_object($post_status);return $s?!empty($s->publicly_queryable)||(empty($s->_builtin)&&!empty($s->public)):false; }

function wp_cache_clean_cache($a=null,$b=null) { return true; } function wp_cache_clear_cache($blog=0) { return true; } function wp_update_nav_menu_item($menu_id=0,$id=0,$args=[]) { return rrw_wp_bridge('menus')?rrw_wp_cms_menu_item_save((int)$menu_id,(int)$id,(array)$args):0; }
function _default_wp_die_handler($message,$title='',$args=[]) { wp_die($message,$title,$args); } function litespeed_finish_request() { return false; }
function format_items($items,$fields) { return ''; }
function wp_get_password_hint() { return 'Das Passwort sollte mindestens zwölf Zeichen lang sein.'; }

if(!class_exists('Requests_IDNAEncoder')){
    class Requests_IDNAEncoder { public static function encode($host){ $h=(string)$host;return function_exists('idn_to_ascii')?((idn_to_ascii($h,0,defined('INTL_IDNA_VARIANT_UTS46')?INTL_IDNA_VARIANT_UTS46:0))?:$h):$h; } }
}
if(!class_exists('WpOrg\Requests\IdnaEncoder')){ class_alias('Requests_IDNAEncoder','WpOrg\Requests\IdnaEncoder'); }
function wp_parse_auth_cookie($cookie='',$scheme='') { return false; }

/** Vereinfachtes $wp_rewrite: Das CMS liefert immer „schöne“ Adressen (/%postname%/). */
if(!class_exists('WP_Rewrite')){
class WP_Rewrite {
    public $permalink_structure='/%postname%/';public $use_trailing_slashes=true;public $root='';public $index='index.php';public $front='/';public $pagination_base='page';public $comments_base='comments';public $search_base='search';public $author_base='author';public $feed_base='feed';public $category_base='';public $tag_base='';public $comment_feed_structure='';public $feed_structure='';public $feeds=['feed','rdf','rss','rss2','atom'];public $extra_rules=[];public $extra_rules_top=[];public $non_wp_rules=[];public $endpoints=[];public $rules=[];public $matches='';
    public function using_permalinks() { return true; } public function using_index_permalinks() { return false; } public function using_mod_rewrite_permalinks() { return true; }
    public function preg_index($number) { return '$matches['.$number.']'; }
    public function init() {} public function set_permalink_structure($s) {} public function set_category_base($b) {} public function set_tag_base($b) {}
    public function wp_rewrite_rules() { return $this->rules; } public function rewrite_rules() { return $this->rules; } public function flush_rules($hard=true) {} public function mod_rewrite_rules() { return ''; } public function iis7_url_rewrite_rules($f=false,$p='') { return ''; }
    public function add_rule($regex,$query,$after='bottom') { add_rewrite_rule($regex,$query,$after); } public function add_endpoint($name,$places,$query_var=true) { add_rewrite_endpoint($name,$places,$query_var); }
    public function add_rewrite_tag($tag,$regex,$query) {} public function add_permastruct($name,$struct,$args=[]) {}
    public function get_page_permastruct() { return '%pagename%/'; } public function get_date_permastruct() { return '%year%/%monthnum%/%day%/'; } public function get_year_permastruct() { return '%year%/'; } public function get_month_permastruct() { return '%year%/%monthnum%/'; } public function get_day_permastruct() { return '%year%/%monthnum%/%day%/'; }
    public function get_category_permastruct() { return 'category/%category%'; } public function get_tag_permastruct() { return 'tag/%post_tag%'; } public function get_author_permastruct() { return 'author/%author%'; } public function get_search_permastruct() { return 'search/%search%'; } public function get_feed_permastruct() { return 'feed/%feed%'; } public function get_extra_permastruct($name) { return false; }
    public function generate_rewrite_rules($permalink_structure,$ep_mask=1,$paged=true,$feed=true,$forcomments=false,$walk_dirs=true,$endpoints=true) { return []; }
    public function get_comment_feed_permastruct() { return 'comments/feed/%feed%'; }
}
}
$GLOBALS['wp_rewrite']=$GLOBALS['wp_rewrite']??new WP_Rewrite();

/** $wp_locale: Wochentage/Monate in der Sprache der Website (über die Übersetzungs-Schicht). */
if(!class_exists('WP_Locale')){
class WP_Locale {
    public $weekday=[];public $weekday_initial=[];public $weekday_abbrev=[];public $month=[];public $month_genitive=[];public $month_abbrev=[];public $meridiem=[];public $text_direction='ltr';public $number_format=['decimal_point'=>'.','thousands_sep'=>','];
    public function __construct() { $this->init(false); }
    public function init($translate=true) {
        $w=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];$m=['January','February','March','April','May','June','July','August','September','October','November','December'];
        $__=$translate?'__':fn($x)=>$x;
        foreach($w as $i=>$n){ $t=$__($n);$this->weekday[$i]=$t;$this->weekday_initial[$t]=mb_substr($t,0,1);$this->weekday_abbrev[$t]=$__(substr($n,0,3)); }
        foreach($m as $i=>$n){ $k=sprintf('%02d',$i+1);$t=$__($n);$this->month[$k]=$t;$this->month_genitive[$k]=$t;$this->month_abbrev[$t]=$__(substr($n,0,3)); }
        $this->meridiem=['am'=>'am','pm'=>'pm','AM'=>'AM','PM'=>'PM'];
    }
    public function get_weekday($i) { return $this->weekday[$i%7]; } public function get_weekday_initial($n) { return $this->weekday_initial[$n]??mb_substr((string)$n,0,1); } public function get_weekday_abbrev($n) { return $this->weekday_abbrev[$n]??substr((string)$n,0,3); }
    public function get_month($i) { return $this->month[sprintf('%02d',$i)]??''; } public function get_month_abbrev($n) { return $this->month_abbrev[$n]??substr((string)$n,0,3); }
    public function get_meridiem($m) { return $this->meridiem[$m]??$m; } public function is_rtl() { return false; }
}
}
$GLOBALS['wp_locale']=$GLOBALS['wp_locale']??new WP_Locale();
add_action('init',function(){ $GLOBALS['wp_locale']->init(true); },0);
function wp_is_file_mod_allowed($context) { return !defined('DISALLOW_FILE_MODS')||!DISALLOW_FILE_MODS; }
$GLOBALS['wp_roles']=$GLOBALS['wp_roles']??wp_roles();

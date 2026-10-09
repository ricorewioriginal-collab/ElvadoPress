<?php
// Ergänzende WordPress-Funktionen (Bereich Multisite, Teil 1): Sites, Netzwerke, Site-Metadaten, Blog-Funktionen, Laden/Konstanten (ms-site, ms-blogs, ms-network, ms-load).
// Die Schicht ist ein Einzelseiten-Betrieb: is_multisite() bleibt false, es gibt genau eine Site (ID 1) in genau einem Netzwerk (ID 1).
// Die Werte kommen aus den Optionen; Status-Flags und Site-Metadaten liegen in den Optionen elvado_ms_site_flags / elvado_ms_sitemeta.
// Funktionen, die weitere Sites anlegen oder löschen würden, melden einen WP_Error („nicht unterstützt“).

/* ───────── Hilfen ───────── */
if(!function_exists('elvado_ms_site_row')){
    /** Die eine Site als Zeile (Werte als Zeichenketten wie in der Datenbank). */
    function elvado_ms_site_row(): array {
        $u=parse_url((string)home_url());$port=!empty($u['port'])?':'.$u['port']:'';$f=(array)get_option('elvado_ms_site_flags',[]);
        $reg=(string)($f['registered']??'0000-00-00 00:00:00');
        return ['blog_id'=>'1','domain'=>strtolower((string)($u['host']??'localhost')).$port,'path'=>trailingslashit((string)($u['path']??'')),'site_id'=>'1','registered'=>$reg,'last_updated'=>(string)($f['last_updated']??$reg),
            'public'=>(string)(int)get_option('blog_public',1),'archived'=>(string)(int)($f['archived']??0),'mature'=>(string)(int)($f['mature']??0),'spam'=>(string)(int)($f['spam']??0),'deleted'=>(string)(int)($f['deleted']??0),'lang_id'=>(string)(int)($f['lang_id']??0)];
    }
}
if(!function_exists('elvado_ms_network_row')){
    function elvado_ms_network_row(): array { $s=elvado_ms_site_row();return ['id'=>'1','domain'=>$s['domain'],'path'=>$s['path']]; }
}
if(!function_exists('elvado_ms_sitemeta_load')){
    /** Site-Metadaten: ['seq'=>int,'data'=>[Schlüssel=>[[mid,Wert],…]]]. */
    function elvado_ms_sitemeta_load(): array { $m=get_option('elvado_ms_sitemeta');return is_array($m)&&isset($m['data'])?$m:['seq'=>0,'data'=>[]]; }
}
if(!function_exists('elvado_ms_site_id')){
    /** Site-ID aus Zahl oder Objekt (0 bei Unbekanntem). */
    function elvado_ms_site_id($site): int { return is_object($site)?(int)($site->blog_id??$site->id??0):(int)$site; }
}

/* ───────── Klassen ───────── */
if(!class_exists('WP_Site')){
#[AllowDynamicProperties]
class WP_Site {
    public $blog_id='0';public $domain='';public $path='';public $site_id='0';public $registered='0000-00-00 00:00:00';public $last_updated='0000-00-00 00:00:00';
    public $public='1';public $archived='0';public $mature='0';public $spam='0';public $deleted='0';public $lang_id='0';
    public static function get_instance($site_id) { return (int)$site_id===1?new WP_Site((object)elvado_ms_site_row()):false; }
    public function __construct($site) { foreach(get_object_vars((object)$site) as $k=>$v)$this->$k=$v; }
    public function to_array() { return get_object_vars($this); }
    public function __get($key) {
        return match($key){'id'=>(int)$this->blog_id,'network_id'=>(int)$this->site_id,'blogname'=>get_option('blogname'),'siteurl'=>get_option('siteurl'),'home'=>get_option('home'),'post_count'=>get_option('post_count'),default=>null};
    }
    public function __isset($key) { return in_array($key,['id','network_id','blogname','siteurl','home','post_count'],true); }
}
}
if(!class_exists('WP_Network')){
#[AllowDynamicProperties]
class WP_Network {
    public $id;public $domain='';public $path='';private $blog_id=0;public $cookie_domain='';public $site_name='';
    public static function get_instance($network_id) { return (int)$network_id===1?new WP_Network((object)elvado_ms_network_row()):false; }
    public function __construct($network) { foreach(get_object_vars((object)$network) as $k=>$v)$this->$k=$v;$this->_set_site_name();$this->_set_cookie_domain(); }
    public function __get($key) { return match($key){'blog_id'=>(string)$this->get_main_site_id(),'site_id'=>$this->get_main_site_id(),default=>null}; }
    public function __isset($key) { return in_array($key,['blog_id','site_id'],true); }
    public function __set($key,$value) { if($key==='blog_id'||$key==='site_id')$this->blog_id=(int)$value;else $this->$key=$value; }
    public function get_main_site_id() { return (int)apply_filters('pre_get_main_site_id',1,$this); }
    private function _set_cookie_domain() { $this->cookie_domain=preg_replace('/^www\./','',(string)$this->domain); }
    private function _set_site_name() { if($this->site_name!=='')return;$n=get_option('site_name');$this->site_name=$n?(string)$n:ucfirst((string)$this->domain); }
}
}
if(!class_exists('WP_Network_Query')){
class WP_Network_Query {
    public $query_vars=[];public $query_var_defaults=[];public $networks=null;public $found_networks=0;public $max_num_pages=0;
    public function __construct($query='') {
        $this->query_var_defaults=['network__in'=>'','network__not_in'=>'','count'=>false,'fields'=>'','number'=>'','offset'=>'','no_found_rows'=>true,'orderby'=>'id','order'=>'ASC','domain'=>'','domain__in'=>'','domain__not_in'=>'','path'=>'','path__in'=>'','path__not_in'=>'','search'=>'','update_network_cache'=>true];
        if(!empty($query))$this->query($query);
    }
    public function parse_query($query='') { if(empty($query))$query=$this->query_vars;$this->query_vars=wp_parse_args($query,$this->query_var_defaults);do_action_ref_array('parse_network_query',[&$this]); }
    public function query($query) { $this->query_vars=wp_parse_args($query);return $this->get_networks(); }
    public function get_networks() {
        $this->parse_query();do_action_ref_array('pre_get_networks',[&$this]);$q=&$this->query_vars;
        $pre=apply_filters_ref_array('networks_pre_query',[null,&$this]);
        if($pre!==null)return $this->networks=$pre;
        $row=elvado_ms_network_row();$ok=true;   // genau ein Netzwerk; die Filter prüfen, ob es passt
        $ids=wp_parse_id_list($q['network__in']);$not=wp_parse_id_list($q['network__not_in']);
        if($ids&&!in_array(1,$ids,true))$ok=false;if($not&&in_array(1,$not,true))$ok=false;
        if($q['domain']!==''&&strcasecmp((string)$q['domain'],$row['domain'])!==0)$ok=false;if($q['path']!==''&&(string)$q['path']!==$row['path'])$ok=false;
        $l=fn($v)=>array_values(array_filter((array)$v,fn($x)=>$x!==''&&$x!==null));
        $dom=array_map('strtolower',$l($q['domain__in']));if($dom&&!in_array($row['domain'],$dom,true))$ok=false;
        $dn=array_map('strtolower',$l($q['domain__not_in']));if($dn&&in_array($row['domain'],$dn,true))$ok=false;
        $pi=$l($q['path__in']);if($pi&&!in_array($row['path'],$pi,true))$ok=false;
        $pn=$l($q['path__not_in']);if($pn&&in_array($row['path'],$pn,true))$ok=false;
        if($q['search']!==''&&stripos($row['domain'].' '.$row['path'],trim((string)$q['search'],'*'))===false)$ok=false;
        $found=$ok?1:0;
        if($q['count'])return $found;
        $list=$ok?[1]:[];
        if($q['number']!==''&&(int)$q['number']>0)$list=array_slice($list,max(0,(int)$q['offset']),(int)$q['number']);
        if($q['number']!==''&&!$q['no_found_rows']){ $this->found_networks=$found;$this->max_num_pages=(int)ceil($found/max(1,(int)$q['number'])); }
        if($q['fields']==='ids')return $this->networks=$list;
        $this->networks=array_map(fn($i)=>WP_Network::get_instance($i),$list);
        return $this->networks=(array)apply_filters_ref_array('the_networks',[$this->networks,&$this]);
    }
}
}

/* ───────── Netzwerke (ms-network, ms-load) ───────── */
if(!function_exists('get_networks')){ function get_networks($args=[]) { $q=new WP_Network_Query();return $q->query($args); } }
if(!function_exists('clean_network_cache')){ function clean_network_cache($ids) { foreach((array)$ids as $id){ wp_cache_delete($id,'networks');wp_cache_delete($id,'network_ids'); }do_action('clean_network_cache',$ids); } }
if(!function_exists('update_network_cache')){ function update_network_cache($networks) { foreach((array)$networks as $n)if(is_object($n))wp_cache_set($n->id,$n,'networks'); } }
if(!function_exists('_prime_network_caches')){ function _prime_network_caches($network_ids) {} }   // kein Cache nötig: ein Netzwerk
if(!function_exists('wp_get_network')){
    function wp_get_network($network) {
        if($network instanceof WP_Network)$network=$network->id;elseif(is_object($network))$network=$network->id??0;
        return WP_Network::get_instance((int)$network);
    }
}
if(!function_exists('get_network_by_path')){
    function get_network_by_path($domain,$path,$segments=null) {
        $pre=apply_filters('pre_get_network_by_path',null,$domain,$path,$segments);if($pre!==null)return $pre;
        $n=elvado_ms_network_row();$d=preg_replace('/^www\./','',strtolower((string)$domain));
        if($d!==preg_replace('/^www\./','',$n['domain']))return false;
        return WP_Network::get_instance(1);
    }
}
if(!function_exists('get_site_by_path')){
    function get_site_by_path($domain,$path,$segments=null) {
        $pre=apply_filters('pre_get_site_by_path',null,$domain,$path,$segments);if($pre!==null)return $pre;
        $s=elvado_ms_site_row();$d=preg_replace('/^www\./','',strtolower((string)$domain));
        if($d!==preg_replace('/^www\./','',$s['domain'])||!str_starts_with(trailingslashit((string)$path),$s['path']))return false;
        return WP_Site::get_instance(1);
    }
}
if(!function_exists('ms_load_current_site_and_network')){
    // Setzt die globalen Variablen für die eine Site/das eine Netzwerk; true, wenn Domain/Pfad passen.
    function ms_load_current_site_and_network($domain,$path,$subdomain=false) {
        $net=get_network_by_path($domain,$path);if(!$net)return false;
        $site=get_site_by_path($domain,$path);if(!$site)return false;
        $GLOBALS['current_site']=$net;$GLOBALS['current_blog']=$site;$GLOBALS['blog_id']=1;$GLOBALS['site_id']=1;$GLOBALS['domain']=$net->domain;$GLOBALS['path']=$net->path;
        return true;
    }
}
if(!function_exists('wpmu_current_site')){
    function wpmu_current_site() { _deprecated_function(__FUNCTION__,'3.9.0','ms_load_current_site_and_network()');$n=elvado_ms_network_row();ms_load_current_site_and_network($n['domain'],$n['path']); }
}
if(!function_exists('ms_not_installed')){
    function ms_not_installed($domain,$path) { wp_die(sprintf(__('Error establishing a database connection for %s.'),esc_html($domain.$path)),__('Error'),['response'=>500]); }
}
if(!function_exists('get_current_site_name')){
    function get_current_site_name($current_site) {
        if(!is_object($current_site))return $current_site;
        $n=get_option('site_name');$current_site->site_name=$n?(string)$n:ucfirst((string)($current_site->domain??''));
        return apply_filters('get_current_site_name',$current_site);
    }
}
if(!function_exists('wp_get_active_network_plugins')){
    function wp_get_active_network_plugins() {
        $out=[];foreach(array_keys((array)get_network_option(1,'active_sitewide_plugins',[])) as $p)if(validate_file($p)===0&&str_ends_with($p,'.php')&&is_file(WP_PLUGIN_DIR.'/'.$p))$out[]=WP_PLUGIN_DIR.'/'.$p;
        sort($out);return $out;
    }
}
if(!function_exists('ms_site_check')){
    // true, wenn die Site erreichbar ist; sonst Abbruch mit 410 (gelöscht, Spam, archiviert).
    function ms_site_check() {
        $pre=apply_filters('ms_site_check',null);if($pre!==null)return true;
        $s=elvado_ms_site_row();
        if($s['deleted']==='1')wp_die(__('This site is no longer available.'),'',['response'=>410]);
        if($s['archived']==='1'||$s['spam']==='1')wp_die(__('This site has been archived or suspended.'),'',['response'=>410]);
        return true;
    }
}

/* ───────── Konstanten (ms-default-constants) ───────── */
if(!function_exists('ms_upload_constants')){
    // UPLOADS wird bewusst nicht gesetzt: es würde den Upload-Pfad der Einzelseite verändern.
    function ms_upload_constants() { if(!defined('UPLOADBLOGSDIR'))define('UPLOADBLOGSDIR','wp-content/blogs.dir'); }
}
if(!function_exists('ms_cookie_constants')){
    function ms_cookie_constants() {
        $path=preg_replace('|https?://[^/]+|i','',trailingslashit((string)get_option('home')));$site=preg_replace('|https?://[^/]+|i','',trailingslashit((string)get_option('siteurl')));
        if(!defined('COOKIEPATH'))define('COOKIEPATH',$path);if(!defined('SITECOOKIEPATH'))define('SITECOOKIEPATH',$site);
        if(!defined('ADMIN_COOKIE_PATH'))define('ADMIN_COOKIE_PATH',SITECOOKIEPATH.'wp-admin');if(!defined('PLUGINS_COOKIE_PATH'))define('PLUGINS_COOKIE_PATH',(defined('WP_PLUGIN_URL')?preg_replace('|https?://[^/]+|i','',WP_PLUGIN_URL):SITECOOKIEPATH.'wp-content/plugins'));
        if(!defined('COOKIE_DOMAIN'))define('COOKIE_DOMAIN',false);
    }
}
if(!function_exists('ms_file_constants')){
    function ms_file_constants() { if(!defined('BLOGUPLOADDIR'))define('BLOGUPLOADDIR',WP_CONTENT_DIR.'/blogs.dir/1/files/'); }
}
if(!function_exists('ms_subdomain_constants')){
    function ms_subdomain_constants() {
        if(!defined('SUBDOMAIN_INSTALL'))define('SUBDOMAIN_INSTALL',defined('VHOST')&&VHOST==='yes');
        if(!defined('VHOST'))define('VHOST',SUBDOMAIN_INSTALL?'yes':'no');
    }
}

/* ───────── Sites (ms-site) ───────── */
if(!function_exists('wp_cache_set_sites_last_changed')){ function wp_cache_set_sites_last_changed() { wp_cache_set('last_changed',microtime(),'sites'); } }
if(!function_exists('wp_normalize_site_data')){
    function wp_normalize_site_data($data) {
        if(array_key_exists('domain',$data))$data['domain']=strtolower(preg_replace('/[^a-z0-9\-.:]+/i','',(string)$data['domain']));
        if(array_key_exists('path',$data))$data['path']=trailingslashit('/'.trim((string)$data['path'],'/'));
        foreach(['site_id','public','archived','mature','spam','deleted','lang_id'] as $k)if(array_key_exists($k,$data))$data[$k]=(int)$data[$k];
        foreach(['registered','last_updated'] as $k)if(array_key_exists($k,$data)&&$data[$k]!==''&&strtotime((string)$data[$k])!==false)$data[$k]=gmdate('Y-m-d H:i:s',strtotime((string)$data[$k]));
        return $data;
    }
}
if(!function_exists('wp_validate_site_data')){
    function wp_validate_site_data($errors,$data,$old_site=null) {
        if(array_key_exists('domain',$data)&&$data['domain']==='')$errors->add('site_empty_domain',__('Site domain must not be empty.'));
        if(array_key_exists('path',$data)&&$data['path']==='')$errors->add('site_empty_path',__('Site path must not be empty.'));
        if(array_key_exists('site_id',$data)&&(int)$data['site_id']!==1)$errors->add('site_invalid_network',__('Site does not exist in the network.'));
        $dom=$data['domain']??($old_site->domain??null);$pth=$data['path']??($old_site->path??null);
        if($dom!==null&&$pth!==null&&(!$old_site||$old_site->domain!==$dom||$old_site->path!==$pth)&&domain_exists($dom,$pth,1))$errors->add('site_taken',__('Sorry, that site already exists!'));
        foreach(['registered','last_updated'] as $k)if(!empty($data[$k])&&strtotime((string)$data[$k])===false)$errors->add('site_invalid_'.$k,__('Invalid date.'));
        do_action('wp_validate_site_data',$errors,$data,$old_site);
    }
}
if(!function_exists('wp_prepare_site_data')){
    function wp_prepare_site_data($data,$defaults,$old_site=null) {
        $data=wp_normalize_site_data(wp_parse_args($data,$defaults));$errors=new WP_Error();
        $data=apply_filters('wp_normalize_site_data',$data);
        wp_validate_site_data($errors,$data,$old_site);
        if($errors->has_errors())return $errors;
        return apply_filters('wp_prepare_site_data',$data,$defaults,$old_site);
    }
}
if(!function_exists('wp_insert_site')){ function wp_insert_site(array $data) { return new WP_Error('multisite_unsupported',__('Creating additional sites is not supported.')); } }
if(!function_exists('wp_update_site')){
    // Nur die eine Site: Status-Flags und Sprache werden gespeichert, „public“ steckt in der Option blog_public; Domain/Pfad folgen der Adresse des CMS.
    function wp_update_site($site_id,array $data) {
        $old=WP_Site::get_instance((int)$site_id);if(!$old)return new WP_Error('site_not_exist',__('Site does not exist.'));
        $data=wp_normalize_site_data($data);$errors=new WP_Error();wp_validate_site_data($errors,array_diff_key($data,['domain'=>1,'path'=>1]),$old);
        if($errors->has_errors())return $errors;
        $f=(array)get_option('elvado_ms_site_flags',[]);
        foreach(['archived','mature','spam','deleted','lang_id'] as $k)if(array_key_exists($k,$data))$f[$k]=(int)$data[$k];
        foreach(['registered','last_updated'] as $k)if(!empty($data[$k]))$f[$k]=$data[$k];
        if(!array_key_exists('last_updated',$data))$f['last_updated']=gmdate('Y-m-d H:i:s');
        update_option('elvado_ms_site_flags',$f);
        if(array_key_exists('public',$data))update_option('blog_public',(string)(int)$data['public']);
        clean_blog_cache($old);$new=WP_Site::get_instance(1);
        do_action('wp_update_site',$new,$old);
        return 1;
    }
}
if(!function_exists('wp_delete_site')){
    function wp_delete_site($site_id) {
        if((int)$site_id!==1)return new WP_Error('site_not_exist',__('Site does not exist.'));
        return new WP_Error('site_main',__('Sorry, you cannot delete the main site.'));
    }
}
if(!function_exists('wp_is_site_initialized')){
    function wp_is_site_initialized($site_id) {
        $pre=apply_filters('pre_wp_is_site_initialized',null,$site_id);if($pre!==null)return (bool)$pre;
        return elvado_ms_site_id($site_id)===1;
    }
}
if(!function_exists('wp_initialize_site')){
    function wp_initialize_site($site_id,array $args=[]) {
        if(empty($site_id))return new WP_Error('site_empty_id',__('Site ID must not be empty.'));
        if((int)$site_id!==1)return new WP_Error('site_invalid_id',__('Site with the ID does not exist.'));
        return new WP_Error('site_already_initialized',__('The site appears to be already initialized.'));
    }
}
if(!function_exists('wp_uninitialize_site')){
    function wp_uninitialize_site($site_id) {
        if(empty($site_id))return new WP_Error('site_empty_id',__('Site ID must not be empty.'));
        if((int)$site_id!==1)return new WP_Error('site_invalid_id',__('Site with the ID does not exist.'));
        return new WP_Error('site_main',__('The main site cannot be uninitialized.'));
    }
}
if(!function_exists('clean_blog_cache')){
    function clean_blog_cache($blog) {
        $id=elvado_ms_site_id($blog);if($id<=0)return;
        foreach(['sites','site-details','blog-details','blog-lookup','blog-id-cache'] as $g)wp_cache_delete($id,$g);
        wp_cache_set_sites_last_changed();do_action('clean_site_cache',$id,$blog);
    }
}
if(!function_exists('_prime_site_caches')){ function _prime_site_caches($ids,$update_meta_cache=true) {} }
if(!function_exists('wp_lazyload_site_meta')){ function wp_lazyload_site_meta(array $site_ids) {} }
if(!function_exists('update_site_cache')){ function update_site_cache($sites,$update_meta_cache=true) { foreach((array)$sites as $s)if(is_object($s))wp_cache_set($s->blog_id,$s,'sites'); } }
if(!function_exists('update_sitemeta_cache')){
    function update_sitemeta_cache($site_ids) { $m=elvado_ms_sitemeta_load();$o=[];foreach(wp_parse_id_list($site_ids) as $i)if($i===1)foreach($m['data'] as $k=>$vals)$o[1][$k]=array_column($vals,1);return $o; }
}
if(!function_exists('wp_check_site_meta_support_prefilter')){ function wp_check_site_meta_support_prefilter($check) { return $check; } }   // Site-Metadaten sind verfügbar

/* ───────── Site-Metadaten (in der Option elvado_ms_sitemeta) ───────── */
if(!function_exists('add_site_meta')){
    function add_site_meta($site_id,$meta_key,$meta_value,$unique=false) {
        if((int)$site_id!==1||!is_scalar($meta_key)||$meta_key==='')return false;
        $m=elvado_ms_sitemeta_load();$meta_value=wp_unslash($meta_value);
        if($unique&&!empty($m['data'][$meta_key]))return false;
        $m['seq']++;$m['data'][$meta_key][]=[$m['seq'],$meta_value];update_option('elvado_ms_sitemeta',$m,false);
        return $m['seq'];
    }
}
if(!function_exists('get_site_meta')){
    function get_site_meta($site_id,$key='',$single=false) {
        if((int)$site_id!==1)return $single?'':[];
        $m=elvado_ms_sitemeta_load();
        if($key==='')return array_map(fn($v)=>array_column($v,1),$m['data']);
        $vals=array_column($m['data'][$key]??[],1);
        return $single?($vals[0]??''):$vals;
    }
}
if(!function_exists('update_site_meta')){
    function update_site_meta($site_id,$meta_key,$meta_value,$prev_value='') {
        if((int)$site_id!==1||!is_scalar($meta_key)||$meta_key==='')return false;
        $m=elvado_ms_sitemeta_load();$meta_value=wp_unslash($meta_value);
        if(empty($m['data'][$meta_key]))return add_site_meta($site_id,$meta_key,$meta_value);
        $changed=false;
        foreach($m['data'][$meta_key] as &$e){ if($prev_value!==''&&$e[1]!=$prev_value)continue;if($e[1]!==$meta_value){$e[1]=$meta_value;$changed=true;} }
        unset($e);if($changed)update_option('elvado_ms_sitemeta',$m,false);
        return $changed;
    }
}
if(!function_exists('delete_site_meta')){
    function delete_site_meta($site_id,$meta_key,$meta_value='') {
        if((int)$site_id!==1||empty($m=elvado_ms_sitemeta_load())||!isset($m['data'][$meta_key]))return false;
        $keep=array_values(array_filter($m['data'][$meta_key],fn($e)=>$meta_value!==''&&$e[1]!=$meta_value));
        if(count($keep)===count($m['data'][$meta_key]))return false;
        if($keep)$m['data'][$meta_key]=$keep;else unset($m['data'][$meta_key]);
        update_option('elvado_ms_sitemeta',$m,false);return true;
    }
}
if(!function_exists('delete_site_meta_by_key')){ function delete_site_meta_by_key($meta_key) { return delete_site_meta(1,$meta_key); } }

/* ───────── Aktualisierungs-Hooks für Sites (Bewegungen zwischen alten und neuen Werten) ───────── */
if(!function_exists('wp_maybe_update_network_site_counts_on_update')){
    function wp_maybe_update_network_site_counts_on_update($new_site,$old_site=null) {
        if($old_site===null){ wp_maybe_update_network_site_counts((int)($new_site->network_id??1));return; }
        if((int)$new_site->network_id!==(int)$old_site->network_id){ wp_maybe_update_network_site_counts((int)$new_site->network_id);wp_maybe_update_network_site_counts((int)$old_site->network_id); }
    }
}
if(!function_exists('wp_maybe_transition_site_statuses_on_update')){
    function wp_maybe_transition_site_statuses_on_update($new_site,$old_site=null) {
        $id=(int)$new_site->id;$o=$old_site?:(object)['spam'=>0,'mature'=>0,'archived'=>0,'deleted'=>0];
        $map=['spam'=>['make_spam_blog','make_ham_blog'],'mature'=>['mature_blog','unmature_blog'],'archived'=>['archive_blog','unarchive_blog'],'deleted'=>['make_delete_blog','make_undelete_blog']];
        foreach($map as $k=>[$on,$off])if((int)$new_site->$k!==(int)$o->$k)do_action((int)$new_site->$k?$on:$off,$id);
    }
}
if(!function_exists('wp_maybe_clean_new_site_cache_on_update')){
    function wp_maybe_clean_new_site_cache_on_update($new_site,$old_site) { if($old_site->domain!==$new_site->domain||$old_site->path!==$new_site->path)clean_blog_cache($new_site); }
}
if(!function_exists('wp_update_blog_public_option_on_site_update')){
    function wp_update_blog_public_option_on_site_update($new_site,$old_site) { if((int)$old_site->public!==(int)$new_site->public)update_blog_option((int)$new_site->id,'blog_public',(string)(int)$new_site->public); }
}

/* ───────── Blogs (ms-blogs) ───────── */
if(!function_exists('update_blog_details')){
    function update_blog_details($blog_id,$details=[]) { $details=(array)$details;if(!$details)return false;return !is_wp_error(wp_update_site((int)$blog_id,$details)); }
}
if(!function_exists('refresh_blog_details')){ function refresh_blog_details($blog_id=0) { clean_blog_cache((int)$blog_id?:1); } }
if(!function_exists('clean_site_details_cache')){ function clean_site_details_cache($site_id=0) { $i=(int)$site_id?:1;wp_cache_delete($i.'short','blog-details');wp_cache_delete($i,'blog-details'); } }
if(!function_exists('update_blog_status')){
    function update_blog_status($blog_id,$pref,$value,$deprecated=null) {
        if(!in_array($pref,['site_id','domain','path','registered','last_updated','public','archived','mature','spam','deleted','lang_id'],true))return $value;
        if((int)$blog_id===1&&!in_array($pref,['site_id','domain','path'],true))wp_update_site(1,[$pref=>$value]);
        return $value;
    }
}
if(!function_exists('get_blog_status')){
    function get_blog_status($id,$pref) { $s=WP_Site::get_instance((int)$id);return $s&&property_exists($s,$pref)?$s->$pref:false; }
}
if(!function_exists('is_archived')){ function is_archived($id) { return get_blog_status($id,'archived'); } }
if(!function_exists('update_archived')){ function update_archived($id,$archived) { update_blog_status($id,'archived',$archived);return $archived; } }
if(!function_exists('wpmu_update_blogs_date')){
    function wpmu_update_blogs_date() { update_blog_details(1,['last_updated'=>gmdate('Y-m-d H:i:s')]);do_action('wpmu_blog_updated',1); }
}
if(!function_exists('get_blogaddress_by_id')){
    function get_blogaddress_by_id($blog_id) { return (int)$blog_id===1?esc_url(trailingslashit((string)home_url())):''; }
}
if(!function_exists('get_blogaddress_by_name')){
    function get_blogaddress_by_name($blogname) {
        $n=elvado_ms_network_row();$u=parse_url((string)home_url());$scheme=($u['scheme']??'http').'://';
        if($blogname==='main'||$blogname==='')return esc_url($scheme.$n['domain'].$n['path']);
        return esc_url($scheme.$n['domain'].$n['path'].$blogname.'/');
    }
}
if(!function_exists('get_id_from_blogname')){
    function get_id_from_blogname($slug) {
        $n=elvado_ms_network_row();$slug=trim((string)$slug,'/');
        $id=(int)get_blog_id_from_url($n['domain'],$n['path'].$slug.'/');
        return $slug===''||!$id?($slug===''?1:null):$id;
    }
}
if(!function_exists('wp_switch_roles_and_user')){ function wp_switch_roles_and_user($new_site_id,$old_site_id) {} }   // eine Site: Rollen und Benutzer bleiben
if(!function_exists('get_last_updated')){
    function get_last_updated($deprecated='',$start=0,$quantity=40) {
        if((int)$start>0||(int)$quantity<1)return [];
        $s=elvado_ms_site_row();$s['post_count']=(string)(int)get_option('post_count',0);
        return [$s];
    }
}
if(!function_exists('_update_blog_date_on_post_publish')){
    function _update_blog_date_on_post_publish($new_status,$old_status,$post) {
        $t=get_post_type_object($post->post_type??'');if(!$t||empty($t->public))return;
        if('publish'!==$new_status&&'publish'!==$old_status)return;
        wpmu_update_blogs_date();
    }
}
if(!function_exists('_update_blog_date_on_post_delete')){
    function _update_blog_date_on_post_delete($post_id) { $p=get_post($post_id);if(!$p)return;$t=get_post_type_object($p->post_type);if(!$t||empty($t->public)||'publish'!==$p->post_status)return;wpmu_update_blogs_date(); }
}
if(!function_exists('_update_posts_count_on_delete')){
    function _update_posts_count_on_delete($post_id) { $p=get_post($post_id);if(!$p||'publish'!==$p->post_status||'post'!==$p->post_type)return;update_posts_count(); }
}
if(!function_exists('_update_posts_count_on_transition_post_status')){
    function _update_posts_count_on_transition_post_status($new_status,$old_status,$post=null) {
        if($new_status===$old_status||'post'!==get_post_type($post)||('publish'!==$new_status&&'publish'!==$old_status))return;
        update_posts_count();
    }
}
if(!function_exists('wp_count_sites')){
    function wp_count_sites($network_id=null) {
        $s=elvado_ms_site_row();$c=['all'=>1,'public'=>0,'archived'=>0,'mature'=>0,'spam'=>0,'deleted'=>0];
        foreach(['public','archived','mature','spam','deleted'] as $k)$c[$k]=(int)$s[$k];
        return $c;
    }
}

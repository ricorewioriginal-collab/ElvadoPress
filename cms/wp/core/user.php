<?php
// Benutzer: WP_User, get_userdata/get_user_by/get_users … Quellen: CMS-Redakteure (lokale Anmeldung), Autorennamen der CMS-Beiträge und die Tabelle wp_users.

if(!class_exists('WP_User')){
#[AllowDynamicProperties]
class WP_User {
    public $data;public $ID=0;public $caps=[];public $cap_key='';public $roles=[];public $allcaps=[];public $filter=null;
    public $user_login='';public $display_name='';public $user_email='';public $user_nicename='';public $user_url='';public $user_registered='';
    public function __construct($id=0, $name='', $site_id=0) {
        if($id instanceof WP_User){ foreach(get_object_vars($id) as $k=>$v)$this->$k=$v;return; }
        if(is_object($id)){ $this->init($id);return; }
        $row=null;
        if((int)$id>0){ $row=rrw_wp_find_user('id',(int)$id); }
        elseif($name!=='')$row=rrw_wp_find_user('login',(string)$name);
        if($row)$this->init((object)$row);
        else{ $this->data=(object)['ID'=>0,'user_login'=>'','user_pass'=>'','user_nicename'=>'','user_email'=>'','user_url'=>'','user_registered'=>'','user_activation_key'=>'','user_status'=>0,'display_name'=>'']; }
    }
    public function init($data, $site_id=0) {
        $data=(object)$data;$this->data=$data;$this->ID=(int)($data->ID??$data->id??0);
        $map=['user_login'=>$data->user_login??($data->login??''),'display_name'=>$data->display_name??($data->name??''),'user_email'=>$data->user_email??($data->email??''),'user_nicename'=>$data->user_nicename??sanitize_title((string)($data->user_login??$data->login??'')),'user_url'=>$data->user_url??'','user_registered'=>$data->user_registered??''];
        foreach($map as $k=>$v){ $this->$k=$v;$this->data->$k=$v; }
        $this->data->ID=$this->ID;
        $role=(string)($data->role??'subscriber');$this->roles=[$role];$this->caps=[$role=>true];$this->allcaps=rrw_wp_caps_for_role($role);$this->cap_key='wp_capabilities';
    }
    public function exists() { return !empty($this->ID); }
    public function __get($key) { if($key==='id')return $this->ID;if(isset($this->data->$key))return $this->data->$key;return get_user_meta($this->ID,$key,true)?:null; }
    public function __isset($key) { return isset($this->data->$key); }
    public function get($key) { return $this->__get($key); }
    public function has_prop($key) { return isset($this->data->$key); }
    public function has_cap($cap, ...$args) { return !empty($this->allcaps[$cap])||(in_array($cap,$this->roles,true)); }
    public function to_array() { return get_object_vars($this->data); }
    public function add_role($r) { $this->roles[]=$r; }
    public function remove_role($r) { $this->roles=array_values(array_diff($this->roles,[$r])); }
    public function set_role($r) { $this->roles=[$r];$this->allcaps=rrw_wp_caps_for_role($r); }
    public function add_cap($c, $g=true) { $this->allcaps[$c]=$g; }
    public function remove_cap($c) { unset($this->allcaps[$c]); }
}
}

/** Alle bekannten Benutzer: CMS-Redakteure, Beitrags-Autoren, Tabelle wp_users. @return array<int,array> */
function rrw_wp_users_all(bool $reset=false): array {
    static $cache=null;if($reset)$cache=null;if($cache!==null)return $cache;
    $out=[];
    if(function_exists('rrw_local_users')){
        foreach((array)rrw_local_users() as $u){ $login=(string)($u['username']??'');if($login==='')continue;
            $role=($u['role']??'admin')==='admin'?'administrator':(($u['role']??'')==='autor'?'author':'editor');
            $id=rrw_wp_cms_author_id((string)($u['display_name']??$login));$out[$id]=['ID'=>$id,'user_login'=>$login,'display_name'=>(string)($u['display_name']??$login),'user_email'=>(string)($u['email']??''),'user_registered'=>(string)($u['created_at']??''),'role'=>$role]; }
    }
    foreach(rrw_wp_cms_data()['news'] as $a){ $n=trim((string)($a['author']??''));if($n==='')continue;$id=rrw_wp_cms_author_id($n);if(!isset($out[$id]))$out[$id]=['ID'=>$id,'user_login'=>sanitize_title($n),'display_name'=>$n,'user_email'=>'','user_registered'=>'','role'=>'author']; }
    global $wpdb;
    if($wpdb&&$wpdb->ready&&rrw_wp_db_ready()){ foreach((array)$wpdb->get_results("SELECT * FROM {$wpdb->users}",ARRAY_A) as $r){ $role=(string)get_user_meta((int)$r['ID'],$wpdb->prefix.'rrw_role',true)?:'subscriber';$r['role']=$role;$out[(int)$r['ID']]=$r; } }
    if(!$out)$out[1]=['ID'=>1,'user_login'=>'admin','display_name'=>'Administrator','user_email'=>'','user_registered'=>'','role'=>'administrator'];
    return $cache=$out;
}
function rrw_wp_find_user(string $field, $value): ?array {
    foreach(rrw_wp_users_all() as $u){
        $v=match($field){'id','ID'=>$u['ID'],'login'=>$u['user_login'],'slug'=>sanitize_title((string)$u['user_login']),'email'=>$u['user_email'],'display_name'=>$u['display_name'],default=>null};
        if($v!==null&&(string)$v!==''&&strtolower((string)$v)===strtolower((string)$value))return $u;
    }
    return null;
}
function get_userdata($user_id) { $u=new WP_User((int)$user_id);return $u->exists()?$u:false; }
function get_user_by($field, $value) { $r=rrw_wp_find_user((string)$field,$value);return $r?new WP_User((object)$r):false; }
function username_exists($username) { $r=rrw_wp_find_user('login',$username);return $r?(int)$r['ID']:false; }
function email_exists($email) { $r=rrw_wp_find_user('email',$email);return $r?(int)$r['ID']:false; }
function get_users($args=[]) {
    $a=wp_parse_args($args,['role'=>'','role__in'=>[],'orderby'=>'login','order'=>'ASC','number'=>'','offset'=>0,'search'=>'','include'=>[],'exclude'=>[],'fields'=>'all']);$out=[];
    $inc=wp_parse_id_list($a['include']);$exc=wp_parse_id_list($a['exclude']);
    foreach(rrw_wp_users_all() as $u){
        if($a['role']!==''&&!in_array($u['role'],(array)$a['role'],true))continue;if($a['role__in']&&!in_array($u['role'],(array)$a['role__in'],true))continue;
        if($inc&&!in_array((int)$u['ID'],$inc,true))continue;if($exc&&in_array((int)$u['ID'],$exc,true))continue;
        if($a['search']!==''&&stripos($u['user_login'].' '.$u['display_name'].' '.$u['user_email'],trim((string)$a['search'],'*'))===false)continue;
        $out[]=new WP_User((object)$u);
    }
    $key=match((string)$a['orderby']){'display_name','name'=>'display_name','ID','id'=>'ID','email'=>'user_email','registered'=>'user_registered',default=>'user_login'};
    usort($out,fn($x,$y)=>strtoupper($a['order'])==='DESC'?strcasecmp((string)$y->$key,(string)$x->$key):strcasecmp((string)$x->$key,(string)$y->$key));
    if($a['number']!=='')$out=array_slice($out,(int)$a['offset'],(int)$a['number']);
    if($a['fields']==='ID'||$a['fields']==='ids')return array_map(fn($u)=>(int)$u->ID,$out);
    if($a['fields']==='display_name')return array_map(fn($u)=>$u->display_name,$out);
    return $out;
}
function count_users($strategy='time', $site_id=null) { $r=[];foreach(rrw_wp_users_all() as $u)$r[$u['role']]=($r[$u['role']]??0)+1;return ['total_users'=>count(rrw_wp_users_all()),'avail_roles'=>$r]; }
function wp_insert_user($userdata) {
    global $wpdb;$u=is_object($userdata)?get_object_vars($userdata):(array)$userdata;
    if(!$wpdb||!$wpdb->ready)return new WP_Error('no_database','Keine Datenbank verfügbar.');rrw_wp_install_schema();
    $login=sanitize_user((string)($u['user_login']??''),true);if($login==='')return new WP_Error('empty_user_login','Der Benutzername darf nicht leer sein.');
    if(username_exists($login))return new WP_Error('existing_user_login','Dieser Benutzername ist bereits vergeben.');
    $email=(string)($u['user_email']??'');if($email!==''&&!is_email($email))return new WP_Error('invalid_email','Ungültige E-Mail-Adresse.');
    $pass=(string)($u['user_pass']??wp_generate_password(20));
    $wpdb->insert($wpdb->users,['user_login'=>$login,'user_pass'=>password_hash($pass,PASSWORD_DEFAULT),'user_nicename'=>sanitize_title($login),'user_email'=>$email,'user_url'=>(string)($u['user_url']??''),'user_registered'=>gmdate('Y-m-d H:i:s'),'display_name'=>(string)($u['display_name']??$login)]);
    $id=(int)$wpdb->insert_id;if(!$id)return new WP_Error('db_insert_error','Der Benutzer konnte nicht angelegt werden.');
    update_user_meta($id,$wpdb->prefix.'rrw_role',(string)($u['role']??'subscriber'));
    foreach(['first_name','last_name','nickname','description'] as $m)if(isset($u[$m]))update_user_meta($id,$m,$u[$m]);
    rrw_wp_users_all(true);do_action('user_register',$id,$u);return $id;
}
function wp_create_user($username, $password, $email='') { return wp_insert_user(['user_login'=>$username,'user_pass'=>$password,'user_email'=>$email]); }
function wp_update_user($userdata) { global $wpdb;$u=(array)$userdata;$id=(int)($u['ID']??0);if(!$id||!$wpdb)return new WP_Error('invalid_user_id','Ungültige Benutzer-ID.');$d=[];foreach(['user_email','display_name','user_url'] as $k)if(isset($u[$k]))$d[$k]=$u[$k];if(isset($u['user_pass']))$d['user_pass']=password_hash((string)$u['user_pass'],PASSWORD_DEFAULT);if($d)$wpdb->update($wpdb->users,$d,['ID'=>$id]);rrw_wp_users_all(true);do_action('profile_update',$id);return $id; }
function wp_delete_user($id, $reassign=null) { global $wpdb;if(!$wpdb||(int)$id<RRW_WP_ID_DB_MIN)return false;$wpdb->delete($wpdb->usermeta,['user_id'=>(int)$id]);$r=$wpdb->delete($wpdb->users,['ID'=>(int)$id]);rrw_wp_users_all(true);do_action('deleted_user',$id,$reassign);return (bool)$r; }
function get_avatar_url($id_or_email, $args=null) { $e=is_object($id_or_email)?($id_or_email->user_email??''):(is_numeric($id_or_email)?(get_userdata((int)$id_or_email)->user_email??''):(string)$id_or_email);$s=(int)($args['size']??96);return 'https://www.gravatar.com/avatar/'.md5(strtolower(trim((string)$e))).'?s='.$s.'&d=mm&r=g'; }
function get_avatar($id_or_email, $size=96, $default_value='', $alt='', $args=null) { $u=get_avatar_url($id_or_email,['size'=>$size]);return '<img alt="'.esc_attr($alt).'" src="'.esc_url($u).'" class="avatar avatar-'.(int)$size.' photo" height="'.(int)$size.'" width="'.(int)$size.'" loading="lazy" decoding="async" />'; }
function map_meta_cap($cap, $user_id, ...$args) { return [$cap]; }
function author_can($post, $capability, ...$args) { return false; }
function wp_get_current_user_dummy() { return null; }

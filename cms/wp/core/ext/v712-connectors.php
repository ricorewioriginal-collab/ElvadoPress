<?php
// Ergänzende WordPress-Funktionen (Version 7.1, Teil Connectors/AI-Client): schlanke Registrierung im Arbeitsspeicher (Register/Get/Has/Unregister),
// Zugangsdaten-Hilfen. AI-Client: es gibt keinen Anbieter und keine Netzwerkzugriffe (wp_supports_ai() = false, wp_ai_client_prompt() -> WP_Error).

if(!class_exists('WP_Connector_Registry')){ class WP_Connector_Registry {
    private $items=[];
    public function register($id, $args=[]) {   // Schlüssel: name, description, logo_url, type (z. B. ai_provider), authentication [method, credentials_url, setting_name], plugin
        $id=sanitize_key((string)$id);if($id===''||isset($this->items[$id])||!is_array($args)||empty($args['name']))return null;
        $auth=is_array($args['authentication']??null)?$args['authentication']:['method'=>'none'];$method=in_array($auth['method']??'none',['api_key','application_password','none'],true)?$auth['method']:'none';
        $auth['method']=$method;if($method==='api_key'&&empty($auth['setting_name']))$auth['setting_name']='connectors_'.($args['type']??'ai_provider').'_'.$id.'_api_key';
        return $this->items[$id]=['name'=>(string)$args['name'],'description'=>(string)($args['description']??''),'logo_url'=>(string)($args['logo_url']??''),'type'=>(string)($args['type']??'ai_provider'),'authentication'=>$auth]+(isset($args['plugin'])?['plugin'=>$args['plugin']]:[]);
    }
    public function unregister($id) { $r=$this->items[$id]??null;unset($this->items[$id]);return $r; }
    public function is_registered($id) { return isset($this->items[$id]); }
    public function get_registered($id) { return $this->items[$id]??null; }
    public function get_all_registered() { return $this->items; }
} }

if(!function_exists('_wp_connectors_init')){ function _wp_connectors_init() {   // einmalig: Registry anlegen, Standardanbieter eintragen, Action wp_connectors_init auslösen
    if(isset($GLOBALS['elvado_wp_connectors'])&&$GLOBALS['elvado_wp_connectors'] instanceof WP_Connector_Registry)return $GLOBALS['elvado_wp_connectors'];
    $reg=$GLOBALS['elvado_wp_connectors']=new WP_Connector_Registry();
    _wp_connectors_register_default_ai_providers($reg);
    do_action('wp_connectors_init',$reg);
    return $reg;
} }
if(!function_exists('_wp_connectors_register_default_ai_providers')){ function _wp_connectors_register_default_ai_providers($registry=null) {
    $registry=$registry??_wp_connectors_init();
    $defs=['anthropic'=>['Anthropic','Anthropic-Modelle für Textgenerierung.','https://console.anthropic.com/settings/keys'],'google'=>['Google','Google-Modelle für Text- und Bildgenerierung.','https://aistudio.google.com/api-keys'],'openai'=>['OpenAI','OpenAI-Modelle für Text-, Bild- und Audiogenerierung.','https://platform.openai.com/api-keys']];
    foreach($defs as $id=>$d)$registry->register($id,['name'=>$d[0],'description'=>$d[1],'type'=>'ai_provider','logo_url'=>_wp_connectors_resolve_ai_provider_logo_url($id),'authentication'=>['method'=>'api_key','credentials_url'=>$d[2],'setting_name'=>'connectors_ai_'.$id.'_api_key']]);
} }
if(!function_exists('wp_is_connector_registered')){ function wp_is_connector_registered($id) { return _wp_connectors_init()->is_registered((string)$id); } }
if(!function_exists('wp_get_connector')){ function wp_get_connector($id) { $r=_wp_connectors_init()->get_registered((string)$id);return $r===null?null:['id'=>(string)$id]+$r; } }
if(!function_exists('wp_get_connectors')){ function wp_get_connectors() { $o=[];foreach(_wp_connectors_init()->get_all_registered() as $id=>$c)$o[$id]=['id'=>$id]+$c;return $o; } }
if(!function_exists('_wp_connectors_resolve_ai_provider_logo_url')){ function _wp_connectors_resolve_ai_provider_logo_url($provider_id) {   // Logos liefert die Schicht nicht mit; Filter erlaubt eigene
    return (string)apply_filters('wp_connectors_ai_provider_logo_url','',(string)$provider_id);
} }
if(!function_exists('_wp_connectors_mask_api_key')){ function _wp_connectors_mask_api_key($key) {   // nur die letzten 4 Zeichen sichtbar
    $k=(string)$key;$n=strlen($k);if($n<=4)return $n?str_repeat("\u{2022}",$n):'';
    return str_repeat("\u{2022}",min(16,$n-4)).substr($k,-4);
} }
if(!function_exists('_wp_connectors_get_api_key_source')){ function _wp_connectors_get_api_key_source($setting_name, $env_var_name='', $constant_name='') {   // 'env' | 'constant' | 'database' | 'none'
    if($env_var_name!==''&&(string)getenv($env_var_name)!=='')return 'env';
    if($constant_name!==''&&defined($constant_name)&&(string)constant($constant_name)!=='')return 'constant';
    return (string)get_option((string)$setting_name,'')!==''?'database':'none';
} }
if(!function_exists('_wp_connectors_is_ai_api_key_valid')){ function _wp_connectors_is_ai_api_key_valid($key, $provider_id='') {   // ohne Netzwerkzugriff nur Formprüfung
    $k=(string)$key;return $k!==''&&strlen($k)>=8&&!preg_match('/\s/',$k);
} }
if(!function_exists('wp_connectors_parse_application_password_credentials')){ function wp_connectors_parse_application_password_credentials($credentials) {   // "Benutzer:Passwort" -> ['username','password'] oder null
    if(is_array($credentials))$credentials=($credentials['username']??'').':'.($credentials['password']??'');
    $p=strpos((string)$credentials,':');if($p===false||$p===0)return null;
    $pw=preg_replace('/\s+/','',substr((string)$credentials,$p+1));
    return $pw===''?null:['username'=>substr((string)$credentials,0,$p),'password'=>$pw];
} }
if(!function_exists('wp_connectors_get_application_password_credentials')){ function wp_connectors_get_application_password_credentials($connector_id) {
    $c=wp_get_connector($connector_id);if(!$c||($c['authentication']['method']??'')!=='application_password')return null;
    $name=(string)($c['authentication']['setting_name']??'connectors_'.$connector_id.'_credentials');
    return wp_connectors_parse_application_password_credentials(get_option($name,''));
} }
if(!function_exists('wp_connectors_sanitize_application_password_credentials')){ function wp_connectors_sanitize_application_password_credentials($value) {   // gültige Angabe normalisiert, sonst ''
    $p=wp_connectors_parse_application_password_credentials(is_string($value)?trim($value):$value);
    return $p?sanitize_user($p['username'],true).':'.$p['password']:'';
} }
if(!function_exists('_wp_register_default_connector_settings')){ function _wp_register_default_connector_settings() {   // je API-Key-Connector eine Einstellung (REST sichtbar)
    foreach(wp_get_connectors() as $id=>$c){
        if(($c['authentication']['method']??'')!=='api_key'||empty($c['authentication']['setting_name']))continue;
        register_setting('connectors',$c['authentication']['setting_name'],['type'=>'string','default'=>'','show_in_rest'=>true,'sanitize_callback'=>static fn($v)=>is_string($v)?trim(sanitize_text_field($v)):'']);
    }
} }
if(!function_exists('_wp_connectors_rest_settings_dispatch')){ function _wp_connectors_rest_settings_dispatch($response, $handler=null, $request=null) {   // Filter rest_post_dispatch: API-Schlüssel in Einstellungen maskieren
    if(!($response instanceof WP_REST_Response)||!is_array($d=$response->get_data()))return $response;
    foreach(wp_get_connectors() as $c){ $n=$c['authentication']['setting_name']??'';if($n!==''&&isset($d[$n])&&is_string($d[$n])&&$d[$n]!=='')$d[$n]=_wp_connectors_mask_api_key($d[$n]); }
    $response->set_data($d);return $response;
} }
if(!function_exists('_wp_connectors_pass_default_keys_to_ai_client')){ function _wp_connectors_pass_default_keys_to_ai_client() {   // kein AI-Client in dieser Schicht: nichts zu übergeben
    return false;
} }
if(!function_exists('_wp_connectors_get_connector_script_module_data')){ function _wp_connectors_get_connector_script_module_data($data=[]) {   // Daten für das Verbindungen-Skriptmodul der Admin-Seite
    $data=is_array($data)?$data:[];$out=[];
    foreach(wp_get_connectors() as $id=>$c){
        $a=$c['authentication'];$n=(string)($a['setting_name']??'');$src=$n!==''?_wp_connectors_get_api_key_source($n):'none';
        $out[$id]=['name'=>$c['name'],'description'=>$c['description'],'logoUrl'=>$c['logo_url'],'type'=>$c['type'],'authentication'=>['method'=>$a['method'],'credentialsUrl'=>$a['credentials_url']??'','settingName'=>$n,'keySource'=>$src,'isConnected'=>$src!=='none']];
    }
    $data['connectors']=$out;return $data;
} }

/* ───────── AI-Client (nicht verfügbar) ───────── */
if(!function_exists('wp_supports_ai')){ function wp_supports_ai() { return false; } }
if(!function_exists('wp_ai_client_prompt')){ function wp_ai_client_prompt($prompt=null) { return new WP_Error('ai_client_unavailable','Der KI-Client ist in dieser Installation nicht verfügbar.'); } }

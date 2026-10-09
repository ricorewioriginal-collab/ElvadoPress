<?php
// Ergänzende REST-Funktionen: Schema-Prüfung (Typen, anyOf/oneOf, enum, Muster), Antwort-Filter nach Kontext, Routen-Ermittlung,
// Cookie-/Anwendungspasswort-Prüfung, CORS- und Link-Header. Die Anfrageverarbeitung selbst liegt in cms/wp/rest.php.

/** Header setzen (nur, wenn noch möglich) und für Prüfungen mitschreiben. */
if(!function_exists('elvado_wp_rest_send_header')){
function elvado_wp_rest_send_header(string $line, bool $replace=true): void { $GLOBALS['elvado_wp_rest_sent_headers'][]=$line;if(!headers_sent())@header($line,$replace); }
}

/* ───────── Start und Standardfilter ───────── */
if(!function_exists('rest_api_register_rewrites')){
function rest_api_register_rewrites() {
    $p=function_exists('rest_get_url_prefix')?rest_get_url_prefix():'wp-json';
    if(function_exists('add_rewrite_rule')){ add_rewrite_rule('^'.$p.'/?$','index.php?rest_route=/','top');add_rewrite_rule('^'.$p.'/(.*)?','index.php?rest_route=/$matches[1]','top');add_rewrite_rule('^index.php/'.$p.'/?$','index.php?rest_route=/','top');add_rewrite_rule('^index.php/'.$p.'/(.*)?','index.php?rest_route=/$matches[1]','top'); }
}}
if(!function_exists('rest_api_init')){
function rest_api_init() { rest_api_register_rewrites();if(isset($GLOBALS['wp'])&&is_object($GLOBALS['wp'])&&method_exists($GLOBALS['wp'],'add_query_var'))$GLOBALS['wp']->add_query_var('rest_route'); }
}
if(!function_exists('rest_api_default_filters')){
function rest_api_default_filters() {
    add_action('deprecated_function_run','rest_handle_deprecated_function',10,3);add_action('deprecated_argument_run','rest_handle_deprecated_argument',10,3);add_action('doing_it_wrong_run','rest_handle_doing_it_wrong',10,3);
    add_filter('rest_pre_serve_request','rest_send_cors_headers');add_filter('rest_post_dispatch','rest_send_allow_header',10,3);add_filter('rest_pre_dispatch','rest_handle_options_request',10,3);
    add_filter('rest_index','rest_add_application_passwords_to_index');
    add_filter('rest_authentication_errors','rest_application_password_check_errors',90);add_filter('rest_authentication_errors','rest_cookie_check_errors',100);
    add_action('auth_cookie_malformed','rest_cookie_collect_status');add_action('auth_cookie_expired','rest_cookie_collect_status');add_action('auth_cookie_bad_username','rest_cookie_collect_status');add_action('auth_cookie_bad_hash','rest_cookie_collect_status');add_action('auth_cookie_valid','rest_cookie_collect_status');
    add_filter('wp_login_errors','rest_application_password_collect_status');
}}
if(!function_exists('create_initial_rest_routes')){
function create_initial_rest_routes() { /* Standardwert: Die Kernrouten registriert cms/wp/rest.php beim ersten Zugriff. */ }
}
if(!function_exists('rest_api_loaded')){
/** Bedient eine Anfrage mit rest_route (z. B. ?rest_route=/wp/v2/posts) und beendet danach. */
function rest_api_loaded() {
    $route=(string)($GLOBALS['wp']->query_vars['rest_route']??($_GET['rest_route']??''));if($route==='')return;
    require_once dirname(__DIR__,2).'/rest.php';$GLOBALS['elvado_wp_serving_rest']=true;
    $q=$_GET;unset($q['rest_route']);
    $r=elvado_wp_rest_dispatch((string)($_SERVER['REQUEST_METHOD']??'GET'),'/'.ltrim($route,'/'),$q,(string)file_get_contents('php://input'));
    if(!headers_sent()){ http_response_code($r['status']);foreach($r['headers'] as $k=>$v)@header($k.': '.$v); }
    echo $r['body'];
    if(!empty($GLOBALS['elvado_wp_die_throws']))throw new ELVADO_WP_Die('rest',$r['status']);
    die();
}}
if(!function_exists('rest_ensure_request')){
function rest_ensure_request($request) {
    if($request instanceof WP_REST_Request)return $request;
    if(is_string($request))return new WP_REST_Request('GET',$request);
    return new WP_REST_Request('GET','',(array)$request);
}}

/* ───────── Hinweise als Header (nur bei WP_DEBUG) ───────── */
if(!function_exists('rest_handle_deprecated_function')){
function rest_handle_deprecated_function($function_name,$replacement,$version) {
    if(!WP_DEBUG)return;
    $m=$replacement?sprintf('%1$s ist seit Version %2$s veraltet! Verwende stattdessen %3$s.',$function_name,$version,$replacement):sprintf('%1$s ist seit Version %2$s veraltet ohne Alternative.',$function_name,$version);
    elvado_wp_rest_send_header(sprintf('X-WP-DeprecatedFunction: %s',$m));
}}
if(!function_exists('rest_handle_deprecated_argument')){
function rest_handle_deprecated_argument($function_name,$message,$version) {
    if(!WP_DEBUG)return;
    $m=$message?sprintf('%1$s wurde mit einem Argument aufgerufen, das seit Version %2$s veraltet ist! %3$s',$function_name,$version,$message):sprintf('%1$s wurde mit einem Argument aufgerufen, das seit Version %2$s veraltet ist ohne Alternative.',$function_name,$version);
    elvado_wp_rest_send_header(sprintf('X-WP-DeprecatedParam: %s',$m));
}}
if(!function_exists('rest_handle_doing_it_wrong')){
function rest_handle_doing_it_wrong($function_name,$message,$version) {
    if(!WP_DEBUG)return;
    $m=$version?sprintf('%1$s wurde falsch aufgerufen. %2$s (Seit Version %3$s.)',$function_name,$message,$version):sprintf('%1$s wurde falsch aufgerufen. %2$s',$function_name,$message);
    elvado_wp_rest_send_header(sprintf('X-WP-DoingItWrong: %s',$m));
}}

/* ───────── CORS, OPTIONS, Allow ───────── */
if(!function_exists('rest_send_cors_headers')){
function rest_send_cors_headers($value) {
    $origin=get_http_origin();
    if($origin){
        $origin=esc_url_raw($origin);
        elvado_wp_rest_send_header('Access-Control-Allow-Origin: '.$origin);elvado_wp_rest_send_header('Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, PATCH, DELETE');
        elvado_wp_rest_send_header('Access-Control-Allow-Credentials: true');elvado_wp_rest_send_header('Access-Control-Allow-Headers: Authorization, X-WP-Nonce, Content-Disposition, Content-MD5, Content-Type');
        elvado_wp_rest_send_header('Vary: Origin',false);
    } elseif(($_SERVER['REQUEST_METHOD']??'')==='GET'&&!is_user_logged_in())elvado_wp_rest_send_header('Vary: Origin',false);
    return $value;
}}
/** Methoden eines Endpunkts als Liste (die Schicht speichert „GET,POST“ als Text, WordPress als Array). */
if(!function_exists('elvado_wp_rest_endpoint_methods')){
function elvado_wp_rest_endpoint_methods($ep): array {
    $m=$ep['methods']??'GET';
    if(is_array($m))$m=array_is_list($m)?implode(',',$m):implode(',',array_keys(array_filter($m)));
    return array_values(array_filter(array_map('trim',explode(',',strtoupper((string)$m)))));
}}
if(!function_exists('rest_handle_options_request')){
function rest_handle_options_request($response,$handler,$request) {
    if(!empty($response)||!$request instanceof WP_REST_Request||$request->get_method()!=='OPTIONS')return $response;
    $response=new WP_REST_Response();$data=[];$route=(string)$request->get_route();
    foreach(rest_get_server()->get_routes() as $key=>$eps){
        if(!@preg_match('#^'.str_replace('#','\#',$key).'/?$#i',$route))continue;
        foreach($eps as $ep){
            $ms=elvado_wp_rest_endpoint_methods($ep);$pc=$ep['permission_callback']??null;
            try{ $ok=$pc===null||(is_callable($pc)&&call_user_func($pc,new WP_REST_Request($ms[0]??'GET',$route,$ep))===true); }catch(Throwable $e){ $ok=false; }
            if(!$ok)continue;
            $data['methods']=array_values(array_unique(array_merge($data['methods']??[],$ms)));
            $data['endpoints'][]=['methods'=>$ms,'args'=>(object)(array)($ep['args']??[])];
        }
        $data['namespace']=explode('/',trim($key,'/'))[0]??'';break;
    }
    $response->set_data($data);
    if(!empty($data['methods']))$response->header('Allow',implode(', ',$data['methods']));
    return $response;
}}
if(!function_exists('rest_send_allow_header')){
function rest_send_allow_header($response,$server,$request) {
    if(!$response instanceof WP_REST_Response)return $response;
    $matched=(string)$response->get_matched_route();if($matched==='')return $response;
    $routes=$server->get_routes();$allowed=[];
    foreach((array)($routes[$matched]??[]) as $ep){
        $pc=$ep['permission_callback']??null;$ms=elvado_wp_rest_endpoint_methods($ep);
        try{ $ok=$pc===null||(is_callable($pc)&&call_user_func($pc,new WP_REST_Request($ms[0]??'GET',(string)$request->get_route(),$ep))===true); }catch(Throwable $e){ $ok=false; }
        if($ok)foreach($ms as $m)$allowed[$m]=true;
    }
    $response->header('Allow',implode(', ',array_keys($allowed)));
    return $response;
}}
if(!function_exists('_rest_array_intersect_key_recursive')){
function _rest_array_intersect_key_recursive($array1,$array2) {
    $array1=array_intersect_key($array1,$array2);
    foreach($array1 as $k=>$v)if(is_array($v)&&is_array($array2[$k]??null))$array1[$k]=_rest_array_intersect_key_recursive($v,$array2[$k]);
    return $array1;
}}

/* ───────── Hinweise im Head und Header ───────── */
if(!function_exists('rest_output_rsd')){
function rest_output_rsd() { $r=get_rest_url();if(empty($r))return;echo '<api name="WP-API" blogID="1" preferred="false" apiLink="'.esc_url($r).'" />'; }
}
if(!function_exists('rest_output_link_wp_head')){
function rest_output_link_wp_head() {
    $r=get_rest_url();if(empty($r))return;
    echo '<link rel="https://api.w.org/" href="'.esc_url($r).'" />';
    $res=rest_get_queried_resource_route();if($res)echo '<link rel="alternate" title="JSON" type="application/json" href="'.esc_url(rest_url($res)).'" />';
}}
if(!function_exists('rest_output_link_header')){
function rest_output_link_header() {
    if(headers_sent())return;
    $r=get_rest_url();if(empty($r))return;
    elvado_wp_rest_send_header('Link: <'.sanitize_url($r).'>; rel="https://api.w.org/"',false);
    $res=rest_get_queried_resource_route();if($res)elvado_wp_rest_send_header('Link: <'.sanitize_url(rest_url($res)).'>; rel="alternate"; title="JSON"; type="application/json"',false);
}}

/* ───────── Anmeldung: Cookie (mit Nonce) und Anwendungspasswörter ───────── */
if(!function_exists('rest_cookie_check_errors')){
function rest_cookie_check_errors($result) {
    if(!empty($result))return $result;
    $cookie=$GLOBALS['wp_rest_auth_cookie']??null;
    if(true!==$cookie&&is_user_logged_in())return $result;   // anders angemeldet (z. B. Anwendungspasswort)
    $nonce=$_REQUEST['_wpnonce']??($_SERVER['HTTP_X_WP_NONCE']??null);
    if(null===$nonce){ if(function_exists('wp_set_current_user'))wp_set_current_user(0);return true; }   // ohne Nonce: wie unangemeldet
    if(!wp_verify_nonce((string)$nonce,'wp_rest')){ add_filter('rest_send_nocache_headers','__return_true',20);return new WP_Error('rest_cookie_invalid_nonce','Cookie-Prüfung fehlgeschlagen.',['status'=>403]); }
    rest_get_server()->send_header('X-WP-Nonce',wp_create_nonce('wp_rest'));
    return true;
}}
if(!function_exists('rest_cookie_collect_status')){
function rest_cookie_collect_status() { $GLOBALS['wp_rest_auth_cookie']=current_action()==='auth_cookie_valid'; }
}
if(!function_exists('rest_application_password_collect_status')){
function rest_application_password_collect_status($user_or_error,$app_password=[]) {
    $GLOBALS['wp_rest_application_password_status']=$user_or_error;
    $GLOBALS['wp_rest_application_password_uuid']=empty($app_password['uuid'])?null:$app_password['uuid'];
    return $user_or_error;
}}
if(!function_exists('rest_get_authenticated_app_password')){
function rest_get_authenticated_app_password() { return $GLOBALS['wp_rest_application_password_uuid']??null; }
}
if(!function_exists('rest_application_password_check_errors')){
function rest_application_password_check_errors($result) {
    if(!empty($result))return $result;
    $st=$GLOBALS['wp_rest_application_password_status']??null;
    if(is_wp_error($st)){ $d=(array)$st->get_error_data();if(!isset($d['status']))$d['status']=401;$st->add_data($d);return $st; }
    return $result;
}}
if(!function_exists('rest_add_application_passwords_to_index')){
function rest_add_application_passwords_to_index($response) {
    // Anwendungspasswörter gibt es in der Schicht nicht: Antwort bleibt unverändert, außer die Funktion wird bereitgestellt.
    if(!function_exists('wp_is_application_passwords_available')||!wp_is_application_passwords_available()||!is_object($response))return $response;
    $response->data['authentication']['application-passwords']=['endpoints'=>['authorization'=>admin_url('authorize-application.php')]];
    return $response;
}}

/* ───────── Typ-Hilfen ───────── */
if(!function_exists('rest_parse_hex_color')){
function rest_parse_hex_color($color) { return preg_match('/^#(?:[A-Fa-f0-9]{3}){1,2}$/',(string)$color)?$color:false; }
}
if(!function_exists('rest_parse_request_arg')){
function rest_parse_request_arg($value,$request,$param) {
    $ok=rest_validate_request_arg($value,$request,$param);if(is_wp_error($ok))return $ok;
    return rest_sanitize_request_arg($value,$request,$param);
}}
if(!function_exists('rest_is_array')){
function rest_is_array($maybe_array) {
    if(is_scalar($maybe_array))$maybe_array=function_exists('wp_parse_list')?wp_parse_list($maybe_array):preg_split('/[\s,]+/',(string)$maybe_array,-1,PREG_SPLIT_NO_EMPTY);
    return is_array($maybe_array)&&!array_filter(array_keys($maybe_array),'is_string');
}}
if(!function_exists('rest_sanitize_array')){
function rest_sanitize_array($maybe_array) {
    if(is_scalar($maybe_array))$maybe_array=function_exists('wp_parse_list')?wp_parse_list($maybe_array):preg_split('/[\s,]+/',(string)$maybe_array,-1,PREG_SPLIT_NO_EMPTY);
    return is_array($maybe_array)?array_values($maybe_array):[];
}}
if(!function_exists('rest_is_object')){
function rest_is_object($maybe_object) {
    if($maybe_object==='')return true;
    if($maybe_object instanceof stdClass)return true;
    if($maybe_object instanceof JsonSerializable)$maybe_object=$maybe_object->jsonSerialize();
    return is_array($maybe_object);
}}
if(!function_exists('rest_sanitize_object')){
function rest_sanitize_object($maybe_object) {
    if($maybe_object==='')return [];
    if($maybe_object instanceof stdClass)return (array)$maybe_object;
    if($maybe_object instanceof JsonSerializable)$maybe_object=$maybe_object->jsonSerialize();
    return is_array($maybe_object)?$maybe_object:[];
}}
if(!function_exists('rest_get_best_type_for_value')){
function rest_get_best_type_for_value($value,$types) {
    static $checks=['array'=>'rest_is_array','object'=>'rest_is_object','integer'=>'rest_is_integer','number'=>'is_numeric','boolean'=>'rest_is_boolean','string'=>'is_string','null'=>'is_null'];
    $types=(array)$types;
    if($value===''&&in_array('string',$types,true))$types=array_diff($types,['array','object']);   // leerer Text ist kein Treffer für Liste/Objekt
    foreach($types as $t)if(isset($checks[$t])&&$checks[$t]($value))return $t;
    return '';
}}
if(!function_exists('rest_handle_multi_type_schema')){
function rest_handle_multi_type_schema($value,$args,$param='') {
    $allowed=['array','object','string','number','integer','boolean','null'];
    $types=(array)($args['type']??[]);$invalid=array_diff($types,$allowed);
    if($invalid)_doing_it_wrong(__FUNCTION__,sprintf('Der Parameter %1$s verwendet unbekannte Typen: %2$s.',$param,implode(', ',$invalid)),'5.5.0');
    $best=rest_get_best_type_for_value($value,$types);
    if(!$best){ if($invalid)return '';$best=reset($types)?:''; }
    return $best;
}}
if(!function_exists('rest_are_values_equal')){
function rest_are_values_equal($value1,$value2) {
    if(is_array($value1)&&is_array($value2)){
        if(count($value1)!==count($value2))return false;
        foreach($value1 as $i=>$v)if(!array_key_exists($i,$value2)||!rest_are_values_equal($v,$value2[$i]))return false;
        return true;
    }
    if((is_int($value1)&&is_float($value2))||(is_float($value1)&&is_int($value2)))return (float)$value1===(float)$value2;
    return $value1===$value2;
}}
if(!function_exists('rest_validate_array_contains_unique_items')){
function rest_validate_array_contains_unique_items($input_array) {
    $n=count($input_array);$a=array_values($input_array);
    for($i=0;$i<$n;$i++)for($j=$i+1;$j<$n;$j++)if(rest_are_values_equal($a[$i],$a[$j]))return false;
    return true;
}}
if(!function_exists('rest_stabilize_value')){
function rest_stabilize_value($value) {
    if(is_object($value))$value=(array)$value;
    if(!is_array($value))return $value;
    foreach($value as $k=>$v)$value[$k]=rest_stabilize_value($v);
    ksort($value);
    return $value;
}}
if(!function_exists('rest_validate_json_schema_pattern')){
function rest_validate_json_schema_pattern($pattern,$value) { return 1===@preg_match('#'.str_replace('#','\\#',(string)$pattern).'#u',(string)$value); }
}
if(!function_exists('rest_find_matching_pattern_property_schema')){
function rest_find_matching_pattern_property_schema($property,$args) {
    if(!isset($args['patternProperties']))return null;
    foreach((array)$args['patternProperties'] as $pattern=>$schema)if(rest_validate_json_schema_pattern($pattern,$property))return $schema;
    return null;
}}
if(!function_exists('rest_get_allowed_schema_keywords')){
function rest_get_allowed_schema_keywords() {
    return ['title','description','default','type','format','enum','items','properties','additionalProperties','patternProperties','minProperties','maxProperties','minimum','maximum','exclusiveMinimum','exclusiveMaximum','multipleOf','minLength','maxLength','pattern','minItems','maxItems','uniqueItems','anyOf','oneOf','required'];
}}

/* ───────── Schema-Prüfung (vollständig, rekursiv) ───────── */
if(!function_exists('elvado_wp_rest_type_error')){
function elvado_wp_rest_type_error($param,$type) { return new WP_Error('rest_invalid_type',sprintf('%1$s ist nicht vom Typ %2$s.',$param,is_array($type)?implode(',',$type):$type),['param'=>$param]); }
}
if(!function_exists('rest_format_combining_operation_error')){
function rest_format_combining_operation_error($param,$error) {
    $reason=$error['error_object']->get_error_message();
    if(isset($error['schema']['title']))return new WP_Error('rest_no_matching_schema',sprintf('%1$s ist kein gültiges %2$s. Grund: %3$s',$param,$error['schema']['title'],$reason),['position'=>$error['index']]);
    return new WP_Error('rest_no_matching_schema',sprintf('%1$s entspricht nicht dem erwarteten Format. Grund: %2$s',$param,$reason),['position'=>$error['index']]);
}}
if(!function_exists('rest_find_any_matching_schema')){
function rest_find_any_matching_schema($value,$args,$param) {
    $errors=[];
    foreach((array)$args['anyOf'] as $i=>$schema){
        if(!isset($schema['type'])&&isset($args['type']))$schema['type']=$args['type'];
        $ok=elvado_wp_rest_validate($value,$schema,$param);
        if(!is_wp_error($ok))return $schema;
        $errors[]=['error_object'=>$ok,'schema'=>$schema,'index'=>$i];
    }
    return elvado_wp_rest_combining_error($value,$param,$errors);
}}
/** Fehler, wenn keine Variante passt: Meldung der Variante mit dem passenden Typ, sonst allgemeine Meldung. */
if(!function_exists('elvado_wp_rest_combining_error')){
function elvado_wp_rest_combining_error($value,$param,$errors) {
    $types=[];foreach($errors as $e)foreach((array)($e['schema']['type']??[]) as $t)$types[$t]=true;
    $best=rest_get_best_type_for_value($value,array_keys($types));
    foreach($errors as $e)if($best!==''&&in_array($best,(array)($e['schema']['type']??[]),true))return rest_format_combining_operation_error($param,$e);
    return new WP_Error('rest_no_matching_schema',sprintf('%1$s ist keiner von %2$s.',$param,implode(', ',array_keys($types))));
}}
if(!function_exists('rest_find_one_matching_schema')){
function rest_find_one_matching_schema($value,$args,$param,$stop_after_first_match=false) {
    $matching=[];$errors=[];
    foreach((array)$args['oneOf'] as $i=>$schema){
        if(!isset($schema['type'])&&isset($args['type']))$schema['type']=$args['type'];
        $ok=elvado_wp_rest_validate($value,$schema,$param);
        if(!is_wp_error($ok)){ if($stop_after_first_match)return $schema;$matching[]=['schema_object'=>$schema,'index'=>$i]; }
        else $errors[]=['error_object'=>$ok,'schema'=>$schema,'index'=>$i];
    }
    if(!$matching)return elvado_wp_rest_combining_error($value,$param,$errors);
    if(count($matching)>1){
        $pos=array_column($matching,'index');
        return new WP_Error('rest_one_of_multiple_matches',sprintf('%1$s passt zu %2$s, soll aber nur zu einer Variante passen.',$param,implode(' und ',$pos)),['positions'=>$pos]);
    }
    return $matching[0]['schema_object'];
}}
if(!function_exists('rest_validate_enum')){
function rest_validate_enum($value,$args,$param) {
    foreach((array)($args['enum']??[]) as $e)if(rest_are_values_equal($value,$e))return true;
    $enc=array_map(fn($e)=>is_scalar($e)?(is_bool($e)?($e?'true':'false'):(string)$e):wp_json_encode($e),(array)($args['enum']??[]));
    if(count($enc)===1)return new WP_Error('rest_not_in_enum',sprintf('%1$s ist nicht %2$s.',$param,$enc[0]),['param'=>$param]);
    return new WP_Error('rest_not_in_enum',sprintf('%1$s ist keiner von %2$s.',$param,implode(', ',$enc)),['param'=>$param]);
}}
if(!function_exists('rest_validate_null_value_from_schema')){
function rest_validate_null_value_from_schema($value,$param='') { return $value!==null?elvado_wp_rest_type_error($param,'null'):true; }
}
if(!function_exists('rest_validate_boolean_value_from_schema')){
function rest_validate_boolean_value_from_schema($value,$param='') { return !rest_is_boolean($value)?elvado_wp_rest_type_error($param,'boolean'):true; }
}
if(!function_exists('rest_validate_object_value_from_schema')){
function rest_validate_object_value_from_schema($value,$args,$param='') {
    if(!rest_is_object($value))return elvado_wp_rest_type_error($param,'object');
    $value=rest_sanitize_object($value);
    foreach((array)($args['required']??[]) as $name)if(is_string($name)&&!array_key_exists($name,$value))return new WP_Error('rest_property_required',sprintf('%1$s ist eine Pflichteigenschaft von %2$s.',$name,$param));
    foreach($value as $prop=>$v){
        if(isset($args['properties'][$prop])){ $ok=elvado_wp_rest_validate($v,$args['properties'][$prop],$param.'['.$prop.']');if(is_wp_error($ok))return $ok;continue; }
        $ps=rest_find_matching_pattern_property_schema((string)$prop,$args);
        if($ps!==null){ $ok=elvado_wp_rest_validate($v,$ps,$param.'['.$prop.']');if(is_wp_error($ok))return $ok;continue; }
        if(isset($args['additionalProperties'])){
            if($args['additionalProperties']===false)return new WP_Error('rest_additional_properties_forbidden',sprintf('%1$s ist keine gültige Eigenschaft des Objekts.',$prop),['property'=>$prop]);
            if(is_array($args['additionalProperties'])){ $ok=elvado_wp_rest_validate($v,$args['additionalProperties'],$param.'['.$prop.']');if(is_wp_error($ok))return $ok; }
        }
    }
    if(isset($args['minProperties'])&&count($value)<$args['minProperties'])return new WP_Error('rest_too_few_properties',sprintf('%1$s enthält zu wenige Eigenschaften (mindestens %2$d).',$param,$args['minProperties']));
    if(isset($args['maxProperties'])&&count($value)>$args['maxProperties'])return new WP_Error('rest_too_many_properties',sprintf('%1$s enthält zu viele Eigenschaften (höchstens %2$d).',$param,$args['maxProperties']));
    return true;
}}
if(!function_exists('rest_validate_array_value_from_schema')){
function rest_validate_array_value_from_schema($value,$args,$param='') {
    if(!rest_is_array($value))return elvado_wp_rest_type_error($param,'array');
    $value=rest_sanitize_array($value);
    if(isset($args['items']))foreach($value as $i=>$v){ $ok=elvado_wp_rest_validate($v,$args['items'],$param.'['.$i.']');if(is_wp_error($ok))return $ok; }
    if(isset($args['minItems'])&&count($value)<$args['minItems'])return new WP_Error('rest_too_few_items',sprintf('%1$s enthält zu wenige Einträge (mindestens %2$d).',$param,$args['minItems']));
    if(isset($args['maxItems'])&&count($value)>$args['maxItems'])return new WP_Error('rest_too_many_items',sprintf('%1$s enthält zu viele Einträge (höchstens %2$d).',$param,$args['maxItems']));
    if(!empty($args['uniqueItems'])&&!rest_validate_array_contains_unique_items($value))return new WP_Error('rest_duplicate_items',sprintf('%1$s enthält doppelte Einträge.',$param));
    return true;
}}
if(!function_exists('rest_validate_number_value_from_schema')){
function rest_validate_number_value_from_schema($value,$args,$param='') {
    if(!is_numeric($value))return elvado_wp_rest_type_error($param,$args['type']??'number');
    $value=0+$value;
    if(isset($args['multipleOf'])&&$args['multipleOf']>0){ $q=$value/$args['multipleOf'];if(abs($q-round($q))>1e-9)return new WP_Error('rest_invalid_multiple',sprintf('%1$s muss ein Vielfaches von %2$s sein.',$param,$args['multipleOf'])); }
    if(isset($args['minimum'])&&(!empty($args['exclusiveMinimum'])?$value<=$args['minimum']:$value<$args['minimum']))return new WP_Error('rest_out_of_bounds',sprintf(!empty($args['exclusiveMinimum'])?'%1$s muss größer als %2$s sein.':'%1$s muss mindestens %2$s betragen.',$param,$args['minimum']));
    if(isset($args['maximum'])&&(!empty($args['exclusiveMaximum'])?$value>=$args['maximum']:$value>$args['maximum']))return new WP_Error('rest_out_of_bounds',sprintf(!empty($args['exclusiveMaximum'])?'%1$s muss kleiner als %2$s sein.':'%1$s darf höchstens %2$s betragen.',$param,$args['maximum']));
    return true;
}}
if(!function_exists('rest_validate_integer_value_from_schema')){
function rest_validate_integer_value_from_schema($value,$args,$param='') {
    $ok=rest_validate_number_value_from_schema($value,$args,$param);if(is_wp_error($ok))return $ok;
    if(round((float)$value)!==(float)$value)return elvado_wp_rest_type_error($param,'integer');
    return true;
}}
if(!function_exists('rest_validate_string_value_from_schema')){
function rest_validate_string_value_from_schema($value,$args,$param='') {
    if(!is_string($value))return elvado_wp_rest_type_error($param,'string');
    $len=function_exists('mb_strlen')?mb_strlen($value):strlen($value);
    if(isset($args['minLength'])&&$len<$args['minLength'])return new WP_Error('rest_too_short',sprintf('%1$s muss mindestens %2$d Zeichen lang sein.',$param,$args['minLength']));
    if(isset($args['maxLength'])&&$len>$args['maxLength'])return new WP_Error('rest_too_long',sprintf('%1$s darf höchstens %2$d Zeichen lang sein.',$param,$args['maxLength']));
    if(isset($args['pattern'])&&!rest_validate_json_schema_pattern($args['pattern'],$value))return new WP_Error('rest_invalid_pattern',sprintf('%1$s entspricht nicht dem Muster %2$s.',$param,$args['pattern']));
    switch($args['format']??''){
        case 'hex-color': if(!rest_parse_hex_color($value))return new WP_Error('rest_invalid_hex_color','Ungültiger Farbwert (Hex).');break;
        case 'email': if(!is_email($value))return new WP_Error('rest_invalid_email','Ungültige E-Mail-Adresse.');break;
        case 'ip': if(!rest_is_ip_address($value))return new WP_Error('rest_invalid_ip',sprintf('%s ist keine gültige IP-Adresse.',$param));break;
        case 'uri': if(esc_url_raw($value)==='')return new WP_Error('rest_invalid_uri',sprintf('%s ist keine gültige Adresse.',$param));break;
        case 'uuid': if(!wp_is_uuid($value))return new WP_Error('rest_invalid_uuid',sprintf('%s ist keine gültige UUID.',$param));break;
        case 'date-time': if(rest_parse_date($value)===false)return new WP_Error('rest_invalid_date','Ungültiges Datum.');break;
    }
    return true;
}}
/** Gesamte Prüfung eines Werts gegen ein Schema (anyOf/oneOf, Mehrfachtypen, enum, Typ-Prüfer). */
if(!function_exists('elvado_wp_rest_validate')){
function elvado_wp_rest_validate($value,$args,$param='') {
    $args=(array)$args;
    if(isset($args['anyOf'])){ $m=rest_find_any_matching_schema($value,$args,$param);if(is_wp_error($m))return $m;if(!isset($args['type'])&&isset($m['type']))$args['type']=$m['type']; }
    if(isset($args['oneOf'])){ $m=rest_find_one_matching_schema($value,$args,$param);if(is_wp_error($m))return $m;if(!isset($args['type'])&&isset($m['type']))$args['type']=$m['type']; }
    $type=$args['type']??null;
    if(is_array($type)){ $t=rest_handle_multi_type_schema($value,$args,$param);if($t==='')return elvado_wp_rest_type_error($param,$type);$type=$t; }
    if($type===null&&!isset($args['enum']))return true;
    if(isset($args['enum'])){ $ok=rest_validate_enum($value,$args,$param);if(is_wp_error($ok))return $ok; }
    return match($type){
        'null'=>rest_validate_null_value_from_schema($value,$param),'boolean'=>rest_validate_boolean_value_from_schema($value,$param),'object'=>rest_validate_object_value_from_schema($value,$args,$param),
        'array'=>rest_validate_array_value_from_schema($value,$args,$param),'number'=>rest_validate_number_value_from_schema($value,$args,$param),'integer'=>rest_validate_integer_value_from_schema($value,$args,$param),
        'string'=>rest_validate_string_value_from_schema($value,$args,$param),default=>true };
}}

/* ───────── Vorladen, Einbetten, Kontext ───────── */
if(!function_exists('rest_preload_api_request')){
function rest_preload_api_request($memo,$path) {
    if(empty($path))return $memo;
    $method='GET';if(is_array($path)&&count($path)===2){ $method=strtoupper((string)end($path));$path=reset($path);if(!in_array($method,['GET','OPTIONS'],true))$method='GET'; }
    $path=untrailingslashit((string)$path);if($path==='')return $memo;
    $parts=explode('?',$path,2);$query=[];if(isset($parts[1]))parse_str($parts[1],$query);
    if(!is_array($memo))$memo=[];
    $req=new WP_REST_Request($method,'/'.ltrim($parts[0],'/'));$req->set_query_params($query);
    $res=rest_do_request($req);
    if(!$res instanceof WP_REST_Response||$res->get_status()>=400&&$method==='GET')return $memo;   // Fehler nicht vorladen
    $entry=['body'=>$res->get_data(),'headers'=>(object)$res->get_headers()];
    if($method==='OPTIONS')$memo['OPTIONS'][$path]=$entry;else $memo[$path]=$entry;
    return $memo;
}}
if(!function_exists('rest_parse_embed_param')){
function rest_parse_embed_param($embed) {
    if(!$embed||$embed==='true'||$embed==='1')return true;
    $rels=function_exists('wp_parse_list')?wp_parse_list($embed):preg_split('/[\s,]+/',(string)$embed,-1,PREG_SPLIT_NO_EMPTY);
    return $rels?$rels:true;
}}
if(!function_exists('rest_filter_response_by_context')){
function rest_filter_response_by_context($data,$schema,$context) {
    if(isset($schema['anyOf'])){ $m=rest_find_any_matching_schema($data,$schema,'');if(!is_wp_error($m)){ if(!isset($schema['type'])&&isset($m['type']))$schema['type']=$m['type'];$data=rest_filter_response_by_context($data,$m,$context); } }
    if(isset($schema['oneOf'])){ $m=rest_find_one_matching_schema($data,$schema,'',true);if(!is_wp_error($m)){ if(!isset($schema['type'])&&isset($m['type']))$schema['type']=$m['type'];$data=rest_filter_response_by_context($data,$m,$context); } }
    if(!is_array($data)&&!is_object($data))return $data;
    $type=$schema['type']??(isset($schema['properties'])?'object':null);
    $isArr=$type==='array'||(is_array($type)&&in_array('array',$type,true));$isObj=$type==='object'||(is_array($type)&&in_array('object',$type,true));
    if($isArr&&$isObj){ if(rest_is_array($data))$isObj=false;else $isArr=false; }
    $addl=$isObj&&isset($schema['additionalProperties'])&&is_array($schema['additionalProperties']);
    foreach($data as $key=>$value){
        $check=[];
        if($isArr)$check=$schema['items']??[];
        elseif($isObj){
            if(isset($schema['properties'][$key]))$check=$schema['properties'][$key];
            else{ $ps=rest_find_matching_pattern_property_schema((string)$key,$schema);if($ps!==null)$check=$ps;elseif($addl)$check=$schema['additionalProperties']; }
        }
        if(!isset($check['context']))continue;
        if(!in_array($context,$check['context'],true)){ if(is_array($data))unset($data[$key]);else unset($data->$key);continue; }
        if(is_array($value)||is_object($value)){ $n=rest_filter_response_by_context($value,$check,$context);if(is_array($data))$data[$key]=$n;else $data->$key=$n; }
    }
    return $data;
}}
if(!function_exists('rest_default_additional_properties_to_false')){
function rest_default_additional_properties_to_false($schema) {
    $type=(array)($schema['type']??[]);
    if(in_array('object',$type,true)){
        foreach(['properties','patternProperties'] as $k)if(isset($schema[$k]))foreach($schema[$k] as $key=>$child)$schema[$k][$key]=rest_default_additional_properties_to_false($child);
        if(!isset($schema['additionalProperties']))$schema['additionalProperties']=false;
    }
    if(in_array('array',$type,true)&&isset($schema['items']))$schema['items']=rest_default_additional_properties_to_false($schema['items']);
    foreach(['anyOf','oneOf'] as $k)if(isset($schema[$k]))foreach($schema[$k] as $i=>$child)$schema[$k][$i]=rest_default_additional_properties_to_false($child);
    return $schema;
}}

/* ───────── Routen von Inhalten ───────── */
if(!function_exists('rest_get_route_for_post_type_items')){
function rest_get_route_for_post_type_items($post_type) {
    $pt=get_post_type_object($post_type);if(!$pt||empty($pt->show_in_rest))return '';
    $ns=!empty($pt->rest_namespace)?$pt->rest_namespace:'wp/v2';$base=!empty($pt->rest_base)?$pt->rest_base:$pt->name;
    return apply_filters('rest_route_for_post_type_items',sprintf('/%s/%s',$ns,$base),$pt);
}}
if(!function_exists('rest_get_route_for_post')){
function rest_get_route_for_post($post) {
    $post=get_post($post);if(!$post instanceof WP_Post)return '';
    $r=rest_get_route_for_post_type_items($post->post_type);if(!$r)return '';
    return apply_filters('rest_route_for_post',sprintf('%s/%d',$r,$post->ID),$post);
}}
if(!function_exists('rest_get_route_for_taxonomy_items')){
function rest_get_route_for_taxonomy_items($taxonomy) {
    $t=get_taxonomy($taxonomy);if(!$t||empty($t->show_in_rest))return '';
    $ns=!empty($t->rest_namespace)?$t->rest_namespace:'wp/v2';$base=!empty($t->rest_base)?$t->rest_base:$t->name;
    return apply_filters('rest_route_for_taxonomy_items',sprintf('/%s/%s',$ns,$base),$t);
}}
if(!function_exists('rest_get_route_for_term')){
function rest_get_route_for_term($term) {
    $term=get_term($term);if(!$term instanceof WP_Term)return '';
    $r=rest_get_route_for_taxonomy_items($term->taxonomy);if(!$r)return '';
    return apply_filters('rest_route_for_term',sprintf('%s/%d',$r,$term->term_id),$term);
}}
if(!function_exists('rest_get_queried_resource_route')){
function rest_get_queried_resource_route() {
    if(is_singular())$route=rest_get_route_for_post(get_queried_object());
    elseif(is_category()||is_tag()||is_tax())$route=rest_get_route_for_term(get_queried_object());
    elseif(is_author())$route='/wp/v2/users/'.get_queried_object_id();
    else $route='';
    return apply_filters('rest_queried_resource_route',$route);
}}

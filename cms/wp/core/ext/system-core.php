<?php
// Ergänzende WordPress-Funktionen (Bereich System, Teil 1): allgemeine Hilfen (Zeit, URLs, Dateien, Feeds, Die-Handler, JSON, Datenschutz, Update-URLs).
// Eigenständig umgesetzt; Mehrinstallations-/Server-Funktionen liefern sinnvolle Standardwerte (jeweils vermerkt).

/* ───────── Server, URLs, Anfrage ───────── */
if(!function_exists('is_lighttpd_before_150')){ function is_lighttpd_before_150() { return preg_match('#lighttpd/(\d+\.\d+(?:\.\d+)?)#i',(string)($_SERVER['SERVER_SOFTWARE']??''),$m)&&version_compare($m[1],'1.5.0','<'); } }
if(!function_exists('wp_guess_url')){ function wp_guess_url() { return defined('WP_SITEURL')&&WP_SITEURL!==''?untrailingslashit((string)WP_SITEURL):elvado_wp_home_url(); } }
if(!function_exists('wp_maybe_decline_date')){ function wp_maybe_decline_date($date, $format='') { return $date; } }   // Genitiv-Monatsnamen gibt es nur in wenigen Sprachen (ru/pl …): Standard unverändert
if(!function_exists('apache_mod_loaded')){ function apache_mod_loaded($mod, $default=false) {
    $apache=(bool)preg_match('/Apache|LiteSpeed|Litespeed/',(string)($_SERVER['SERVER_SOFTWARE']??''));
    if(!$apache)return false;
    $r=function_exists('apache_get_modules')?in_array($mod,apache_get_modules(),true):$default;
    return (bool)apply_filters('apache_mod_loaded',$r,$mod);
} }
if(!function_exists('iis7_supports_permalinks')){ function iis7_supports_permalinks() {
    $iis=str_contains((string)($_SERVER['SERVER_SOFTWARE']??''),'Microsoft-IIS');
    return (bool)apply_filters('iis7_supports_permalinks',$iis&&class_exists('DOMDocument',false)&&!empty($_SERVER['IIS_UrlRewriteModule']));
} }
if(!function_exists('force_ssl_admin')){ function force_ssl_admin($force=null) { static $forced=null;if($forced===null)$forced=defined('FORCE_SSL_ADMIN')&&FORCE_SSL_ADMIN;if($force!==null){$old=$forced;$forced=(bool)$force;return $old;}return $forced; } }
if(!function_exists('get_main_network_id')){ function get_main_network_id() { return 1; } }   // Einzelseite: immer Netzwerk 1
if(!function_exists('is_site_meta_supported')){ function is_site_meta_supported() { return false; } }   // gibt es nur bei Multisite
if(!function_exists('wp_removable_query_args')){ function wp_removable_query_args() { return apply_filters('removable_query_args',['activate','activated','admin_email_remind_later','approved','core-major-auto-updates-saved','deactivate','delete_count','deleted','disabled','doing_wp_cron','enabled','error','hotkeys_highlight_first','hotkeys_highlight_last','ids','locked','message','same','saved','settings-updated','skipped','spammed','trashed','unspammed','untrashed','update','updated','wp-post-new-reload']); } }
if(!function_exists('add_magic_quotes')){ function add_magic_quotes($array) { foreach((array)$array as $k=>$v)$array[$k]=is_array($v)?add_magic_quotes($v):addslashes((string)$v);return $array; } }
if(!function_exists('_http_build_query')){ function _http_build_query($data, $prefix=null, $sep=null, $key='', $urlencode=true) {
    $ret=[];$sep=$sep??'&';
    foreach((array)$data as $k=>$v){
        $k=$urlencode?urlencode((string)$k):(string)$k;
        if(!empty($key))$k=$key.'%5B'.$k.'%5D';
        if($v===null)continue;
        if($v===false)$v='0';
        if(is_array($v)||is_object($v))$ret[]=_http_build_query($v,'',$sep,$k,$urlencode);
        elseif($urlencode)$ret[]=$k.'='.urlencode((string)$v);
        else $ret[]=$k.'='.$v;
    }
    return implode($sep,$ret);   // $prefix (nummerische Schlüssel) wird nicht mehr ausgewertet, wie in WordPress ab 3.x
} }
if(!function_exists('wp_remote_fopen')){ function wp_remote_fopen($uri) {
    $p=parse_url((string)$uri);if(!$p||empty($p['scheme']))return false;
    $r=wp_remote_get($uri,['timeout'=>10]);if(is_wp_error($r)||(int)wp_remote_retrieve_response_code($r)!==200)return false;
    return wp_remote_retrieve_body($r);
} }
if(!function_exists('wp')){ function wp($query_vars='') {   // Umgebung aufbauen: vorhandenes $wp->main(), sonst nur der Hook
    global $wp;
    if(is_object($wp)&&method_exists($wp,'main')){ $wp->main($query_vars);return; }
    do_action_ref_array('wp',[&$wp]);
} }
if(!function_exists('cache_javascript_headers')){ function cache_javascript_headers() {
    if(headers_sent())return;$ex=10*DAY_IN_SECONDS;
    header('Content-Type: text/javascript; charset='.get_bloginfo('charset'));header('Vary: Accept-Encoding');
    header('Expires: '.gmdate('D, d M Y H:i:s',time()+$ex).' GMT');header('Cache-Control: public, max-age='.$ex);
} }
if(!function_exists('bool_from_yn')){ function bool_from_yn($yn) { return strtolower((string)$yn)==='y'; } }
if(!function_exists('is_new_day')){ function is_new_day() { global $currentday,$previousday;return ($currentday??null)!==($previousday??null)?1:0; } }
if(!function_exists('wp_original_referer_field')){ function wp_original_referer_field($display=true, $jump_back_to='current') {
    $ref=$jump_back_to==='previous'?(wp_get_referer()?:wp_unslash($_SERVER['REQUEST_URI']??'')):wp_unslash($_SERVER['REQUEST_URI']??'');
    $f='<input type="hidden" name="_wp_original_http_referer" value="'.esc_attr((string)$ref).'" />';
    if($display)echo $f;return $f;
} }
if(!function_exists('wp_get_original_referer')){ function wp_get_original_referer() { return !empty($_REQUEST['_wp_original_http_referer'])?wp_validate_redirect(wp_unslash($_REQUEST['_wp_original_http_referer']),false):false; } }
if(!function_exists('send_nosniff_header')){ function send_nosniff_header() { if(!headers_sent())header('X-Content-Type-Options: nosniff'); } }
if(!function_exists('send_frame_options_header')){ function send_frame_options_header() { if(!headers_sent())header('X-Frame-Options: SAMEORIGIN'); } }
if(!function_exists('wp_admin_headers')){ function wp_admin_headers() { if(headers_sent())return;send_frame_options_header();$p=apply_filters('admin_referrer_policy','strict-origin-when-cross-origin');if($p)header('Referrer-Policy: '.$p); } }
if(!function_exists('do_favicon')){ function do_favicon() {
    do_action('do_faviconico');
    if(function_exists('has_site_icon')&&has_site_icon()){ wp_redirect(get_site_icon_url(32)); return; }
    status_header(204);
} }
if(!function_exists('wp_post_preview_js')){ function wp_post_preview_js() {   // Vorschau-Fenster benennen, damit erneute Vorschau dasselbe Fenster nutzt
    global $post;if(!is_preview()||empty($post))return;
    echo '<script>(function(){try{if(window.name&&window.name.indexOf("wp-preview-")===0&&window.name!=="wp-preview-'.(int)$post->ID.'"){window.name="wp-preview-'.(int)$post->ID.'"}else if(!window.name){window.name="wp-preview-'.(int)$post->ID.'"}}catch(e){}})();</script>'."\n";
} }

/* ───────── Dauer, Zeit, Datum ───────── */
if(!function_exists('human_readable_duration')){ function human_readable_duration($duration='') {
    $duration=trim((string)$duration);if($duration==='')return false;
    $p=explode(':',$duration);if(count($p)>3||count($p)<2)return false;
    foreach($p as $x)if(!ctype_digit($x))return false;
    $p=array_map('intval',$p);$s=array_pop($p);$m=array_pop($p);$h=array_pop($p);
    $o=[];
    if($h!==null)$o[]=sprintf(_n('%s hour','%s hours',$h),$h);
    if($m!==null)$o[]=sprintf(_n('%s minute','%s minutes',$m),$m);
    $o[]=sprintf(_n('%s second','%s seconds',$s),$s);
    return implode(', ',$o);
} }
if(!function_exists('mysql_to_rfc3339')){ function mysql_to_rfc3339($date_string) { return mysql2date('c',(string)$date_string,false); } }
if(!function_exists('wp_checkdate')){ function wp_checkdate($month, $day, $year, $source_date) { return (bool)apply_filters('wp_checkdate',checkdate((int)$month,(int)$day,(int)$year),$source_date); } }
if(!function_exists('wp_timezone_override_offset')){ function wp_timezone_override_offset() {
    $tz=get_option('timezone_string');if(!$tz)return false;
    try{ return (new DateTimeZone((string)$tz))->getOffset(new DateTime('now',new DateTimeZone('UTC')))/3600; }catch(Throwable $e){ return false; }
} }
if(!function_exists('_wp_timezone_choice_usort_callback')){ function _wp_timezone_choice_usort_callback($a, $b) {
    if($a['t_continent']==='Europe'&&$b['t_continent']!=='Europe')return -1;   // Reihenfolge nach Kontinent, dann Stadt, dann Unterstadt
    $c=strcmp($a['t_continent'],$b['t_continent']);if($c!==0)return $c;
    $c=strcmp($a['t_city'],$b['t_city']);if($c!==0)return $c;
    return strcmp($a['t_subcity'],$b['t_subcity']);
} }
if(!function_exists('wp_timezone_choice')){ function wp_timezone_choice($selected_zone, $locale=null) {
    $cont=['Africa','America','Antarctica','Arctic','Asia','Atlantic','Australia','Europe','Indian','Pacific'];$z=[];
    foreach(timezone_identifiers_list() as $id){ $p=explode('/',$id,3);if(!in_array($p[0],$cont,true))continue;
        $z[]=['continent'=>$p[0],'city'=>$p[1]??'','subcity'=>$p[2]??'','t_continent'=>$p[0],'t_city'=>str_replace('_',' ',$p[1]??''),'t_subcity'=>str_replace('_',' ',$p[2]??'')]; }
    usort($z,'_wp_timezone_choice_usort_callback');
    $o=[];if(empty($selected_zone))$o[]='<option selected="selected" value="">'.esc_html__('Select a city').'</option>';
    $open=null;
    foreach($z as $k){
        $v=$k['continent'].($k['city']!==''?'/'.$k['city']:'').($k['subcity']!==''?'/'.$k['subcity']:'');
        if($open!==$k['continent']){ if($open!==null)$o[]='</optgroup>';$o[]='<optgroup label="'.esc_attr($k['t_continent']).'">';$open=$k['continent']; }
        $o[]='<option value="'.esc_attr($v).'"'.($v===$selected_zone?' selected="selected"':'').'>'.esc_html(trim($k['t_city'].($k['t_subcity']!==''?' - '.$k['t_subcity']:''))).'</option>';
    }
    if($open!==null)$o[]='</optgroup>';
    $o[]='<optgroup label="'.esc_attr__('UTC').'"><option value="UTC"'.($selected_zone==='UTC'?' selected="selected"':'').'>'.esc_html__('UTC').'</option></optgroup>';
    $o[]='<optgroup label="'.esc_attr__('Manual Offsets').'">';
    for($h=-12;$h<=14;$h+=0.25){   // Viertelstunden-Schritte wie WordPress (nur gebräuchliche)
        $f=fmod(abs($h),1);if(!in_array($f,[0.0,0.5,0.75,0.25],true))continue;
        $sign=$h<0?'-':'+';$a=abs($h);$val='UTC'.($h<0?'-':'+').rtrim(rtrim((string)$a,'0'),'.');if($h==0)$val='UTC+0';
        $label='UTC'.$sign.floor($a).($f>0?':'.str_pad((string)(int)($f*60),2,'0',STR_PAD_LEFT):'');
        $o[]='<option value="'.esc_attr($val).'"'.($val===$selected_zone?' selected="selected"':'').'>'.esc_html($label).'</option>';
    }
    $o[]='</optgroup>';
    return implode("\n",$o);
} }
if(!function_exists('_wp_mysql_week')){ function _wp_mysql_week($column) {
    $s=(int)get_option('start_of_week',1);
    return $s>=2&&$s<=6?"WEEK( DATE_SUB( $column, INTERVAL $s DAY ), 0 )":($s===1?"WEEK( $column, 1 )":"WEEK( $column, 0 )");
} }

/* ───────── XML-RPC-Hilfen, Texte, Zeichensatz ───────── */
if(!function_exists('xmlrpc_getposttitle')){ function xmlrpc_getposttitle($content) { global $post_default_title;return preg_match('/<title>(.+?)<\/title>/is',(string)$content,$m)?$m[1]:(string)($post_default_title??''); } }
if(!function_exists('xmlrpc_getpostcategory')){ function xmlrpc_getpostcategory($content) {
    global $post_default_category;
    if(preg_match('/<category>(.+?)<\/category>/is',(string)$content,$m))return array_map('trim',explode(',',$m[1]));
    return [$post_default_category??get_option('default_category')];
} }
if(!function_exists('xmlrpc_removepostdata')){ function xmlrpc_removepostdata($content) { return trim(preg_replace('/<(title|category)>.+?<\/\1>/is','',(string)$content)); } }
if(!function_exists('wp_extract_urls')){ function wp_extract_urls($content) {
    preg_match_all("#([\"']?)(https?://[^\s<>\"'()\[\]]+(?:\([\w\d]+\)[^\s<>\"'()\[\]]*)*)\\1#i",(string)$content,$m);
    $u=[];foreach($m[2] as $x){ $x=rtrim(html_entity_decode($x),'.,;:!?');if($x!=='')$u[]=sanitize_url($x); }
    return array_values(array_unique($u));
} }
if(!function_exists('do_enclose')){ function do_enclose($content=null, $post=null) {   // Medien-Anhänge (enclosure) aus Beitragsinhalt eintragen
    $post=get_post($post);if(!$post)return false;if($content===null)$content=$post->post_content;
    $have=(array)get_post_custom_values('enclosure',$post->ID);$seen=[];foreach($have as $e)$seen[]=trim((string)strtok((string)$e,"\n"));
    $types=wp_get_mime_types();$found=[];
    foreach(wp_extract_urls($content) as $url){
        if(in_array($url,$seen,true))continue;
        $ext=strtolower(pathinfo((string)parse_url($url,PHP_URL_PATH),PATHINFO_EXTENSION));if($ext==='')continue;
        $mime='';foreach($types as $re=>$t)if(preg_match('/^('.$re.')$/i',$ext)&&preg_match('#^(audio|video)/#',$t)){$mime=$t;break;}
        if($mime===''||!apply_filters('rss_enclosure_allowed',true,$url))continue;
        $len=0;$r=wp_safe_remote_head($url);if(!is_wp_error($r)){ $len=(int)wp_remote_retrieve_header($r,'content-length');$ct=(string)wp_remote_retrieve_header($r,'content-type');if($ct!=='')$mime=trim(explode(';',$ct)[0]); }
        add_post_meta($post->ID,'enclosure',$url."\n".$len."\n".$mime."\n");$found[]=$url;
    }
    return $found;
} }
if(!function_exists('get_tag_regex')){ function get_tag_regex($tag) { return empty($tag)?'':sprintf('<%1$s[^<]*(?:>[\s\S]*<\/%1$s>|\s*\/>)',tag_escape($tag)); } }
if(!function_exists('is_utf8_charset')){ function is_utf8_charset($blog_charset=null) { return in_array(strtolower((string)($blog_charset??get_option('blog_charset','UTF-8'))),['utf-8','utf8'],true); } }
if(!function_exists('_canonical_charset')){ function _canonical_charset($charset) {
    $l=strtolower((string)$charset);
    if($l==='utf-8'||$l==='utf8')return 'UTF-8';
    if($l==='iso-8859-1'||$l==='iso8859-1')return 'ISO-8859-1';
    return $charset;
} }
if(!function_exists('_mce_set_direction')){ function _mce_set_direction($mce_init) { if(is_rtl()){ $mce_init['directionality']='rtl';$mce_init['rtl_ui']=true; }return $mce_init; } }
if(!function_exists('smilies_init')){ function smilies_init() {
    global $wpsmiliestrans,$wp_smiliessearch;
    if(!get_option('use_smilies'))return;
    if(!isset($wpsmiliestrans))$wpsmiliestrans=[':mrgreen:'=>'mrgreen.png',':neutral:'=>"\xf0\x9f\x98\x90",':twisted:'=>"\xf0\x9f\x98\x88",':arrow:'=>"\xe2\x9e\xa1\xef\xb8\x8f",':shock:'=>"\xf0\x9f\x98\xaf",':smile:'=>"\xf0\x9f\x99\x82",':???:'=>"\xf0\x9f\x98\x95",':cool:'=>"\xf0\x9f\x98\x8e",':evil:'=>"\xf0\x9f\x91\xbf",':grin:'=>"\xf0\x9f\x98\x80",':idea:'=>"\xf0\x9f\x92\xa1",':oops:'=>"\xf0\x9f\x98\xb3",':razz:'=>"\xf0\x9f\x98\x9b",':roll:'=>"\xf0\x9f\x99\x84",':wink:'=>"\xf0\x9f\x98\x89",':cry:'=>"\xf0\x9f\x98\xa5",':eek:'=>"\xf0\x9f\x98\xae",':lol:'=>"\xf0\x9f\x98\x86",':mad:'=>"\xf0\x9f\x98\xa1",':sad:'=>"\xf0\x9f\x99\x81",'8-)'=>"\xf0\x9f\x98\x8e",'8-O'=>"\xf0\x9f\x98\xaf",':-('=>"\xf0\x9f\x99\x81",':-)'=>"\xf0\x9f\x99\x82",':-?'=>"\xf0\x9f\x98\x95",':-D'=>"\xf0\x9f\x98\x80",':-P'=>"\xf0\x9f\x98\x9b",':-o'=>"\xf0\x9f\x98\xae",':-x'=>"\xf0\x9f\x98\xa1",':-|'=>"\xf0\x9f\x98\x90",';-)'=>"\xf0\x9f\x98\x89",'8O'=>"\xf0\x9f\x98\xaf",':('=>"\xf0\x9f\x99\x81",':)'=>"\xf0\x9f\x99\x82",':?'=>"\xf0\x9f\x98\x95",':D'=>"\xf0\x9f\x98\x80",':P'=>"\xf0\x9f\x98\x9b",':o'=>"\xf0\x9f\x98\xae",':x'=>"\xf0\x9f\x98\xa1",':|'=>"\xf0\x9f\x98\x90",';)'=>"\xf0\x9f\x98\x89",':!:'=>"\xe2\x9d\x97",':?:'=>"\xe2\x9d\x93"];
    uksort($wpsmiliestrans,fn($a,$b)=>strlen($b)<=>strlen($a));
    $wp_smiliessearch='/(?:\s|^)('.implode('|',array_map(fn($s)=>preg_quote($s,'/'),array_keys($wpsmiliestrans))).')(?=\s|$)/m';
} }
if(!function_exists('wp_recursive_ksort')){ function wp_recursive_ksort(&$array) { foreach($array as &$v)if(is_array($v))wp_recursive_ksort($v);unset($v);ksort($array); } }

/* ───────── JSON ───────── */
if(!function_exists('_wp_json_convert_string')){ function _wp_json_convert_string($input_string) {
    $s=(string)$input_string;
    if(function_exists('mb_check_encoding')&&mb_check_encoding($s,'UTF-8'))return $s;
    if(function_exists('mb_detect_encoding')){ $enc=mb_detect_encoding($s,mb_detect_order(),true);if($enc)return mb_convert_encoding($s,'UTF-8',$enc);return mb_convert_encoding($s,'UTF-8','UTF-8'); }
    return (string)wp_check_invalid_utf8($s,true);
} }
if(!function_exists('_wp_json_sanity_check')){ function _wp_json_sanity_check($value, $depth) {
    if($depth<0)throw new Exception('Reached depth limit');
    if(is_array($value)){ $out=[];foreach($value as $k=>$v){ $k=is_string($k)?_wp_json_convert_string($k):$k;$out[$k]=_wp_json_sanity_check($v,$depth-1); }return $out; }
    if(is_object($value)){ $o=new stdClass();foreach(get_object_vars($value) as $k=>$v)$o->{_wp_json_convert_string((string)$k)}=_wp_json_sanity_check($v,$depth-1);return $o; }
    return is_string($value)?_wp_json_convert_string($value):$value;
} }
if(!function_exists('_wp_json_prepare_data')){ function _wp_json_prepare_data($value) { return $value; } }   // seit WordPress veraltet: unverändert
if(!function_exists('wp_check_jsonp_callback')){ function wp_check_jsonp_callback($callback) { if(!is_string($callback))return false;preg_replace('/[^\w\.]/','',$callback,-1,$bad);return $bad===0; } }

/* ───────── Beenden-Handler (wp_die je Anfrageart) ───────── */
if(!function_exists('elvado_ext_die_end')){ function elvado_ext_die_end($out, $code, $ctype, $exit=true) {   // gemeinsames Ende der Handler; im Testbetrieb (elvado_wp_die_throws) mit Ausnahme statt exit
    if(!headers_sent()){ if($code)http_response_code((int)$code);if($ctype)header('Content-Type: '.$ctype); }
    echo $out;
    if(!$exit)return;
    if(!empty($GLOBALS['elvado_wp_die_throws']))throw new ELVADO_WP_Die(is_string($out)?$out:'',(int)$code);
    exit;
} }
if(!function_exists('_wp_die_process_input')){ function _wp_die_process_input($message, $title='', $args=[]) {
    $a=wp_parse_args($args,['response'=>0,'code'=>'','exit'=>true,'back_link'=>false,'link_url'=>'','link_text'=>'','text_direction'=>'','charset'=>'utf-8','additional_errors'=>[]]);
    $a=apply_filters('wp_die_args',$a,$message);
    if(is_int($title)&&$title>0){ $a['response']=$title;$title=''; }
    if(is_wp_error($message)){
        $e=$message->get_error_messages();$data=$message->get_error_data();
        if(is_array($data)){ if(!empty($data['title'])&&$title==='')$title=$data['title'];if(!$a['response']&&!empty($data['status']))$a['response']=(int)$data['status']; }
        elseif(is_scalar($data)&&!$a['response']&&(int)$data>0&&$title===''){ /* Rohdaten ohne Statusbedeutung */ }
        if($a['code']==='')$a['code']=$message->get_error_code();
        $message=count($e)>1?"<ul>\n\t\t<li>".implode("</li>\n\t\t<li>",$e)."</li>\n\t</ul>":($e[0]??'');
    }
    if(!$a['response'])$a['response']=500;
    if($a['code']==='')$a['code']='wp_die';
    if(!is_string($title)||$title==='')$title=__('WordPress &rsaquo; Error');
    return [$message,$title,$a];
} }
if(!function_exists('_ajax_wp_die_handler')){ function _ajax_wp_die_handler($message, $title='', $args=[]) {
    [$message,,$a]=_wp_die_process_input($message,$title,$args);
    elvado_ext_die_end(is_scalar($message)?(string)$message:'0',$a['response'],'',(bool)$a['exit']);
} }
if(!function_exists('_json_wp_die_handler')){ function _json_wp_die_handler($message, $title='', $args=[]) {
    [$message,,$a]=_wp_die_process_input($message,$title,$args);
    $d=['code'=>$a['code'],'message'=>$message,'data'=>['status'=>$a['response']],'additional_errors'=>$a['additional_errors']];
    elvado_ext_die_end(wp_json_encode($d),$a['response'],'application/json; charset=utf-8',(bool)$a['exit']);
} }
if(!function_exists('_jsonp_wp_die_handler')){ function _jsonp_wp_die_handler($message, $title='', $args=[]) {
    [$message,,$a]=_wp_die_process_input($message,$title,$args);
    $d=['code'=>$a['code'],'message'=>$message,'data'=>['status'=>$a['response']],'additional_errors'=>$a['additional_errors']];
    $cb=isset($_GET['_jsonp'])?(string)$_GET['_jsonp']:'';
    $out=wp_check_jsonp_callback($cb)&&$cb!==''?'/**/'.$cb.'('.wp_json_encode($d).')':wp_json_encode($d);
    elvado_ext_die_end($out,$a['response'],'application/javascript; charset=utf-8',(bool)$a['exit']);
} }
if(!function_exists('_xmlrpc_wp_die_handler')){ function _xmlrpc_wp_die_handler($message, $title='', $args=[]) {
    [$message,,$a]=_wp_die_process_input($message,$title,$args);
    $x='<?xml version="1.0"?><methodResponse><fault><value><struct><member><name>faultCode</name><value><int>'.(int)$a['response'].'</int></value></member><member><name>faultString</name><value><string>'.htmlspecialchars(strip_tags((string)$message),ENT_XML1).'</string></value></member></struct></value></fault></methodResponse>';
    elvado_ext_die_end($x,200,'text/xml; charset=utf-8',(bool)$a['exit']);
} }
if(!function_exists('_xml_wp_die_handler')){ function _xml_wp_die_handler($message, $title='', $args=[]) {
    [$message,$title,$a]=_wp_die_process_input($message,$title,$args);
    $x="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<error><code>".htmlspecialchars((string)$a['code'],ENT_XML1).'</code><title><![CDATA['.str_replace(']]>',']]]]><![CDATA[>',(string)$title).']]></title><message><![CDATA['.str_replace(']]>',']]]]><![CDATA[>',(string)$message).']]></message><data><status>'.(int)$a['response'].'</status></data></error>';
    elvado_ext_die_end($x,$a['response'],'text/xml; charset=utf-8',(bool)$a['exit']);
} }
if(!function_exists('_scalar_wp_die_handler')){ function _scalar_wp_die_handler($message='', $title='', $args=[]) {
    [$message,,$a]=_wp_die_process_input($message,$title,$args);
    $out=is_scalar($message)?(string)$message:'0';
    if(!$a['exit'])return $out;
    elvado_ext_die_end($out,0,'',true);
} }

/* ───────── Dateien, Pfade, MIME ───────── */
if(!function_exists('path_is_absolute')){ function path_is_absolute($path) {
    $path=(string)$path;if($path===''||$path==='.')return false;
    if(@realpath($path)===$path)return true;
    return $path[0]==='/'||$path[0]==='\\'||(bool)preg_match('#^[a-zA-Z]:[\\\\/]#',$path);
} }
if(!function_exists('get_temp_dir')){ function get_temp_dir() { static $t='';if($t!=='')return trailingslashit(apply_filters('get_temp_dir',$t));
    if(defined('WP_TEMP_DIR'))return $t=trailingslashit((string)WP_TEMP_DIR);
    $d=function_exists('sys_get_temp_dir')?sys_get_temp_dir():'/tmp';return $t=trailingslashit($d); } }
if(!function_exists('win_is_writable')){ function win_is_writable($path) {   // schreibbar prüfen durch echtes Anlegen (unter Windows liefert is_writable() Unsinn)
    $path=(string)$path;
    if($path!==''&&($path[strlen($path)-1]==='/'||is_dir($path))){ $t=rtrim($path,'/').'/elvado'.uniqid(); $ok=@file_put_contents($t,'')!==false;if($ok)@unlink($t);return $ok; }
    $ex=file_exists($path);$f=@fopen($path,'ab');if($f===false)return false;fclose($f);if(!$ex)@unlink($path);return true;
} }
if(!function_exists('_wp_upload_dir')){ function _wp_upload_dir($time=null) {
    $b=WP_CONTENT_DIR.'/uploads';$u=content_url('uploads');$sub='';
    if(get_option('uploads_use_yearmonth_folders')){ $t=$time?:current_time('mysql');$y=substr((string)$t,0,4);$m=substr((string)$t,5,2);$sub='/'.$y.'/'.$m; }
    return apply_filters('upload_dir',['path'=>$b.$sub,'url'=>$u.$sub,'subdir'=>$sub,'basedir'=>$b,'baseurl'=>$u,'error'=>false]);
} }
if(!function_exists('_wp_check_alternate_file_names')){ function _wp_check_alternate_file_names($filenames, $dir, $files) {   // true, wenn eine der Alternativen schon im Ordner/der Dateiliste liegt
    foreach((array)$filenames as $f){ if(file_exists(rtrim((string)$dir,'/').'/'.$f))return true;if(is_array($files)&&in_array($f,$files,true))return true; }
    return false;
} }
if(!function_exists('_wp_check_existing_file_names')){ function _wp_check_existing_file_names($filename, $files) {   // Datei oder ihre Bildgrößen-Varianten (name-100x100.ext, name-scaled.ext) vorhanden?
    $i=pathinfo((string)$filename);$n=preg_quote($i['filename'],'/');$e=isset($i['extension'])?preg_quote($i['extension'],'/'):'';
    foreach((array)$files as $f)if(preg_match('/^'.$n.'(-(scaled|rotated)|-\d+x\d+)?\.'.$e.'$/i',(string)$f))return true;
    return false;
} }
if(!function_exists('wp_get_ext_types')){ function wp_get_ext_types() { return apply_filters('ext2type',[
    'image'=>['jpg','jpeg','jpe','gif','png','bmp','tif','tiff','ico','heic','webp','avif'],
    'audio'=>['aac','ac3','aif','aiff','flac','m3a','m4a','m4b','mka','mp1','mp2','mp3','ogg','oga','ram','wav','wma'],
    'video'=>['3g2','3gp','3gpp','asf','avi','divx','dv','flv','m4v','mkv','mov','mp4','mpeg','mpg','mpv','ogm','ogv','qt','rm','vob','wmv'],
    'document'=>['doc','docx','docm','dotm','odt','pages','pdf','xps','oxps','rtf','wp','wpd','psd','xcf'],
    'spreadsheet'=>['numbers','ods','xls','xlsx','xlsm','xlsb'],
    'interactive'=>['swf','key','ppt','pptx','pptm','pps','ppsx','ppsm','sldx','sldm','odp'],
    'text'=>['asc','csv','tsv','txt'],
    'archive'=>['bz2','cab','dmg','gz','rar','sea','sit','sqx','tar','tgz','zip','7z'],
    'code'=>['css','htm','html','php','js']]); } }
if(!function_exists('wp_ext2type')){ function wp_ext2type($ext) { $ext=strtolower((string)$ext);foreach(wp_get_ext_types() as $t=>$exts)if(in_array($ext,$exts,true))return $t;return null; } }
if(!function_exists('wp_get_default_extension_for_mime_type')){ function wp_get_default_extension_for_mime_type($mime_type) {
    $m=strtolower(trim((string)$mime_type));if($m==='')return false;
    foreach(wp_get_mime_types() as $re=>$t)if(strtolower($t)===$m)return explode('|',$re)[0];
    return false;
} }
if(!function_exists('wp_get_image_mime')){ function wp_get_image_mime($file) {
    $f=@fopen((string)$file,'rb');if(!$f)return false;$h=(string)fread($f,32);fclose($f);
    if(strncmp($h,"\xFF\xD8\xFF",3)===0)return 'image/jpeg';
    if(strncmp($h,"\x89PNG\r\n\x1a\n",8)===0)return 'image/png';
    if(strncmp($h,'GIF8',4)===0)return 'image/gif';
    if(strncmp($h,'RIFF',4)===0&&substr($h,8,4)==='WEBP')return 'image/webp';
    if(substr($h,4,4)==='ftyp'){ $b=substr($h,8,4);if(in_array($b,['avif','avis'],true))return 'image/avif';if(in_array($b,['heic','heix','hevc','hevx'],true))return 'image/heic';if(in_array($b,['heif','mif1','msf1'],true))return 'image/heif'; }
    if(strncmp($h,'BM',2)===0)return 'image/bmp';
    if(strncmp($h,"II*\0",4)===0||strncmp($h,"MM\0*",4)===0)return 'image/tiff';
    return false;
} }
if(!function_exists('wp_is_heic_image_mime_type')){ function wp_is_heic_image_mime_type($mime_type) { return in_array(strtolower((string)$mime_type),['image/heic','image/heif','image/heic-sequence','image/heif-sequence'],true); } }
if(!function_exists('get_dirsize')){ function get_dirsize($directory, $max_execution_time=null) {
    $c=get_transient('dirsize_cache');$c=is_array($c)?$c:[];
    $r=recurse_dirsize($directory,null,$max_execution_time,$c);if(is_int($r))set_transient('dirsize_cache',$c,HOUR_IN_SECONDS);
    return $r;
} }
if(!function_exists('recurse_dirsize')){ function recurse_dirsize($directory, $exclude=null, $max_execution_time=null, &$directory_cache=null) {
    $directory=untrailingslashit(wp_normalize_path((string)$directory));
    if(!is_dir($directory))return false;
    if(is_array($directory_cache)&&isset($directory_cache[$directory])&&is_int($directory_cache[$directory]))return $directory_cache[$directory];
    $start=microtime(true);$max=$max_execution_time??(int)ini_get('max_execution_time')?:30;$size=0;
    $h=@opendir($directory);if(!$h)return false;
    while(($f=readdir($h))!==false){
        if($f==='.'||$f==='..')continue;$p=$directory.'/'.$f;
        if($exclude!==null&&wp_normalize_path((string)$exclude)===$p)continue;
        if(is_file($p)){ $size+=(int)@filesize($p); }
        elseif(is_dir($p)&&!is_link($p)){
            $s=recurse_dirsize($p,$exclude,$max_execution_time,$directory_cache);
            if(!is_int($s)){ closedir($h);return $s; }
            $size+=$s;
        }
        if(microtime(true)-$start>$max){ closedir($h);return null; }   // Zeitgrenze: unbekannt
    }
    closedir($h);
    if(is_array($directory_cache))$directory_cache[$directory]=$size;
    return $size;
} }
if(!function_exists('clean_dirsize_cache')){ function clean_dirsize_cache($path) {
    $c=get_transient('dirsize_cache');if(!is_array($c)||!$c)return;
    $p=untrailingslashit(wp_normalize_path((string)$path));
    while($p!==''&&$p!=='.'&&$p!=='/'){ unset($c[$p]);$n=dirname($p);if($n===$p)break;$p=$n; }
    if($c)set_transient('dirsize_cache',$c,HOUR_IN_SECONDS);else delete_transient('dirsize_cache');
} }

/* ───────── Cache, Hierarchie, kleine Helfer ───────── */
if(!function_exists('wp_find_hierarchy_loop_tortoise_hare')){ function wp_find_hierarchy_loop_tortoise_hare($callback, $start, $override=[], $callback_args=[], $_return_loop=false) {
    $step=fn($x)=>isset($override[$x])?$override[$x]:call_user_func_array($callback,array_merge([$x],$callback_args));
    $t=$h=$start;
    while(true){   // Floyd: Schildkröte ein Schritt, Hase zwei; treffen sie sich, gibt es eine Schleife
        if(!($t=$step($t))||!($h=$step($h))||!($h=$step($h)))return false;
        if($t==$h)break;
    }
    if(!$_return_loop)return $t;
    $loop=[];$x=$t;do{ $loop[$x]=true;$x=$step($x); }while($x&&$x!=$t);   // Mitglieder der Schleife selbst
    return $loop;
} }
if(!function_exists('wp_find_hierarchy_loop')){ function wp_find_hierarchy_loop($callback, $start, $start_parent, $callback_args=[]) {
    $override=is_null($start_parent)?[]:[$start=>$start_parent];
    $m=wp_find_hierarchy_loop_tortoise_hare($callback,$start,$override,$callback_args);
    if(!$m)return [];
    return wp_find_hierarchy_loop_tortoise_hare($callback,$m,$override,$callback_args,true);
} }
if(!function_exists('_get_non_cached_ids')){ function _get_non_cached_ids($object_ids, $cache_group) {
    $ids=[];foreach(array_unique((array)$object_ids) as $id){ if(!_validate_cache_id($id))continue;wp_cache_get($id,$cache_group,false,$found);if(!$found)$ids[]=$id; }
    return $ids;
} }
if(!function_exists('_validate_cache_id')){ function _validate_cache_id($object_id) {
    if(is_int($object_id))return true;
    if(is_string($object_id)&&(string)(int)$object_id===$object_id)return true;
    return is_float($object_id)&&(int)$object_id==$object_id&&(string)(int)$object_id===(string)$object_id;
} }
if(!function_exists('wp_cache_get_last_changed')){ function wp_cache_get_last_changed($group) { $l=wp_cache_get('last_changed',$group);if(!$l){ $l=microtime();wp_cache_set('last_changed',$l,$group); }return $l; } }
if(!function_exists('wp_cache_set_last_changed')){ function wp_cache_set_last_changed($group) { $p=wp_cache_get('last_changed',$group);$n=microtime();wp_cache_set('last_changed',$n,$group);do_action('wp_cache_set_last_changed',$group,$n,$p);return $n; } }
if(!function_exists('wp_fuzzy_number_match')){ function wp_fuzzy_number_match($expected, $actual, $precision=1) { return abs((float)$expected-(float)$actual)<=$precision; } }
if(!function_exists('wp_unique_id_from_values')){ function wp_unique_id_from_values(array $data, $prefix='') { return $prefix.substr(md5((string)wp_json_encode($data)),0,8); } }
if(!function_exists('_device_can_upload')){ function _device_can_upload() {
    if(!wp_is_mobile())return true;
    $ua=(string)($_SERVER['HTTP_USER_AGENT']??'');
    if(str_contains($ua,'iPhone')||str_contains($ua,'iPad')||str_contains($ua,'iPod'))return preg_match('#OS ([\d_]+) like Mac OS X#',$ua,$m)&&version_compare(str_replace('_','.',$m[1]),'6','>=');
    return true;
} }
if(!function_exists('wp_get_admin_notice')){ function wp_get_admin_notice($message, $args=[]) {
    $a=wp_parse_args($args,['type'=>'','dismissible'=>false,'id'=>'','additional_classes'=>[],'attributes'=>[],'paragraph_wrap'=>true]);
    $a=apply_filters('wp_admin_notice_args',$a,$message);
    $cls=['notice'];if($a['type']!=='')$cls[]='notice-'.$a['type'];if($a['dismissible'])$cls[]='is-dismissible';
    $cls=array_merge($cls,(array)$a['additional_classes']);
    $attr=' class="'.esc_attr(implode(' ',array_map('sanitize_html_class',$cls))).'"';
    if($a['id']!=='')$attr.=' id="'.esc_attr($a['id']).'"';
    foreach((array)$a['attributes'] as $k=>$v)$attr.=is_int($k)?' '.esc_attr($v):' '.esc_attr($k).'="'.esc_attr($v).'"';
    $m=$a['paragraph_wrap']?'<p>'.$message.'</p>':(string)$message;
    return apply_filters('wp_admin_notice_markup','<div'.$attr.'>'.$m."</div>\n",$message,$a);
} }
if(!function_exists('_deprecated_constructor')){ function _deprecated_constructor($class_name, $version, $parent_class='') {
    do_action('deprecated_constructor_run',$class_name,$parent_class,$version);
    if(defined('WP_DEBUG')&&WP_DEBUG&&apply_filters('deprecated_constructor_trigger_error',true)&&function_exists('wp_trigger_error'))
        wp_trigger_error('',sprintf('The called constructor method for %1$s class%2$s is deprecated since version %3$s!',$class_name,$parent_class!==''?' in '.$parent_class:'',$version),E_USER_DEPRECATED);
} }
if(!function_exists('_deprecated_class')){ function _deprecated_class($class_name, $version, $replacement='') {
    do_action('deprecated_class_run',$class_name,$replacement,$version);
    if(defined('WP_DEBUG')&&WP_DEBUG&&apply_filters('deprecated_class_trigger_error',true)&&function_exists('wp_trigger_error'))
        wp_trigger_error('',sprintf('Class %1$s is deprecated since version %2$s!%3$s',$class_name,$version,$replacement!==''?' Use '.$replacement.' instead.':''),E_USER_DEPRECATED);
} }
if(!function_exists('dead_db')){ function dead_db() {
    $f=WP_CONTENT_DIR.'/db-error.php';
    if(is_file($f)&&empty($GLOBALS['elvado_wp_die_throws'])){ require_once $f;exit; }
    wp_die(__('Error establishing a database connection'),__('Database Error'),['response'=>500]);
} }
if(!function_exists('_config_wp_home')){ function _config_wp_home($url='') { return defined('WP_HOME')&&WP_HOME!==''?untrailingslashit((string)WP_HOME):$url; } }
if(!function_exists('_config_wp_siteurl')){ function _config_wp_siteurl($url='') { return defined('WP_SITEURL')&&WP_SITEURL!==''?untrailingslashit((string)WP_SITEURL):$url; } }
if(!function_exists('_delete_option_fresh_site')){ function _delete_option_fresh_site() { update_option('fresh_site',0); } }
if(!function_exists('wp_maybe_load_widgets')){ function wp_maybe_load_widgets() { /* Standard-Widgets stehen schon in der Kernschicht bereit: nichts nachzuladen */ } }
if(!function_exists('wp_widgets_add_menu')){ function wp_widgets_add_menu() { if(!current_theme_supports('widgets'))return;add_theme_page(__('Widgets'),__('Widgets'),'edit_theme_options','widgets.php'); } }

/* ───────── Anmelde-Prüfung (Heartbeat) ───────── */
if(!function_exists('wp_auth_check_load')){ function wp_auth_check_load() {
    if(!is_admin()&&!is_user_logged_in())return;
    if(defined('IFRAME_REQUEST'))return;
    if(!apply_filters('wp_auth_check_load',true))return;
    add_filter('heartbeat_received','wp_auth_check',10,2);add_filter('heartbeat_send','wp_auth_check');
    add_action('admin_print_footer_scripts','wp_auth_check_html',5);add_action('wp_print_footer_scripts','wp_auth_check_html',5);
} }
if(!function_exists('wp_auth_check')){ function wp_auth_check($response) { $response['wp-auth-check']=is_user_logged_in()&&empty($GLOBALS['login_grace_period']);return $response; } }

/* ───────── Datenschutz ───────── */
if(!function_exists('wp_privacy_anonymize_ip')){ function wp_privacy_anonymize_ip($ip_addr, $ipv6_fallback=false) {
    $ip_addr=(string)$ip_addr;if($ip_addr==='')return '0.0.0.0';
    $ip_addr=trim($ip_addr,'[]');
    $bin=@inet_pton($ip_addr);if($bin===false)return '0.0.0.0';
    if(strlen($bin)===16&&strncmp($bin,"\0\0\0\0\0\0\0\0\0\0\xff\xff",12)===0)$bin=substr($bin,12);   // IPv4 in IPv6-Schreibweise
    if(strlen($bin)===4){ $bin=$bin&"\xff\xff\xff\0";$ip=inet_ntop($bin); }
    else { $bin=$bin&("\xff\xff\xff\xff\xff\xff".str_repeat("\0",10));$ip=inet_ntop($bin); }
    return apply_filters('privacy_anonymize_ip',(string)$ip,$ip_addr);
} }
if(!function_exists('wp_privacy_exports_dir')){ function wp_privacy_exports_dir() { $u=wp_upload_dir();return apply_filters('wp_privacy_exports_dir',trailingslashit($u['basedir']).'wp-personal-data-exports/'); } }
if(!function_exists('wp_privacy_exports_url')){ function wp_privacy_exports_url() { $u=wp_upload_dir();return apply_filters('wp_privacy_exports_url',trailingslashit($u['baseurl']).'wp-personal-data-exports/'); } }
if(!function_exists('wp_schedule_delete_old_privacy_export_files')){ function wp_schedule_delete_old_privacy_export_files() { if(wp_installing())return;if(!wp_next_scheduled('wp_privacy_delete_old_export_files'))wp_schedule_event(time(),'hourly','wp_privacy_delete_old_export_files'); } }
if(!function_exists('wp_privacy_delete_old_export_files')){ function wp_privacy_delete_old_export_files() {
    $d=wp_privacy_exports_dir();if(!is_dir($d))return;
    $exp=(int)apply_filters('wp_privacy_export_expiration',3*DAY_IN_SECONDS);
    foreach((array)glob(rtrim($d,'/').'/*') as $f){ if(!is_file($f)||basename($f)==='index.php'||basename($f)==='.htaccess')continue;if(time()-(int)@filemtime($f)>=$exp)@unlink($f); }
} }
if(!function_exists('wp_site_admin_email_change_notification')){ function wp_site_admin_email_change_notification($old_value, $value, $option) {
    if(!apply_filters('send_site_admin_email_change_email',true,$old_value,$value))return;
    $site=wp_specialchars_decode((string)get_option('blogname'),ENT_QUOTES);
    $msg=sprintf(__('This notice confirms that the admin email address was changed on %s.'),$site)."\n\n".sprintf(__('The new admin email address is %s.'),$value)."\n\n".sprintf(__('This email has been sent to %s'),$old_value)."\n\n".sprintf(__('Regards,'))."\n".sprintf(__('All at %s'),$site)."\n".home_url();
    wp_mail($old_value,sprintf(__('[%s] Admin Email Changed'),$site),$msg);
} }

/* ───────── Update-Hinweise (PHP, HTTPS) ───────── */
if(!function_exists('wp_get_default_update_php_url')){ function wp_get_default_update_php_url() { return _x('https://wordpress.org/support/update-php/','localized PHP upgrade information page'); } }
if(!function_exists('wp_get_update_php_url')){ function wp_get_update_php_url() { $d=wp_get_default_update_php_url();$u=$d;$e=getenv('WP_UPDATE_PHP_URL');if($e)$u=$e;return (string)apply_filters('wp_update_php_url',$u?:$d); } }
if(!function_exists('wp_get_update_php_annotation')){ function wp_get_update_php_annotation() {
    $u=wp_get_update_php_url();if($u===wp_get_default_update_php_url())return '';
    return sprintf(__('<a href="%s" target="_blank" rel="noopener noreferrer">Learn more about updating PHP</a>.'),esc_url($u));
} }
if(!function_exists('wp_update_php_annotation')){ function wp_update_php_annotation($before='<p class="description">', $after='</p>') { $a=wp_get_update_php_annotation();if($a!=='')echo $before.$a.$after; } }
if(!function_exists('wp_get_direct_php_update_url')){ function wp_get_direct_php_update_url() { $u='';$e=getenv('WP_DIRECT_UPDATE_PHP_URL');if($e)$u=$e;return (string)apply_filters('wp_direct_php_update_url',$u); } }
if(!function_exists('wp_direct_php_update_button')){ function wp_direct_php_update_button() { $u=wp_get_direct_php_update_url();if($u==='')return;echo '<p class="button-container"><a class="button button-primary" href="'.esc_url($u).'" target="_blank" rel="noopener noreferrer">'.esc_html__('Update PHP').'</a></p>'; } }
if(!function_exists('wp_get_default_update_https_url')){ function wp_get_default_update_https_url() { return _x('https://wordpress.org/documentation/article/why-should-i-use-https/','localized website security information page'); } }
if(!function_exists('wp_get_update_https_url')){ function wp_get_update_https_url() { $d=wp_get_default_update_https_url();$u=$d;$e=getenv('WP_UPDATE_HTTPS_URL');if($e)$u=$e;return (string)apply_filters('wp_update_https_url',$u?:$d); } }
if(!function_exists('wp_get_direct_update_https_url')){ function wp_get_direct_update_https_url() { $u='';$e=getenv('WP_DIRECT_UPDATE_HTTPS_URL');if($e)$u=$e;return (string)apply_filters('wp_direct_update_https_url',$u); } }

/* ───────── Planmäßiges Löschen (Papierkorb) ───────── */
if(!function_exists('wp_scheduled_delete')){ function wp_scheduled_delete() {
    global $wpdb;if(!isset($wpdb))return;
    $limit=time()-(DAY_IN_SECONDS*(int)EMPTY_TRASH_DAYS);
    foreach((array)$wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_trash_meta_time'",ARRAY_A) as $r){
        if((int)$r['meta_value']>=$limit)continue;$id=(int)$r['post_id'];$p=get_post($id);
        if(!$p||$p->post_status!=='trash'){ delete_post_meta($id,'_wp_trash_meta_status');delete_post_meta($id,'_wp_trash_meta_time'); }
        else wp_delete_post($id);
    }
    foreach((array)$wpdb->get_results("SELECT comment_id, meta_value FROM {$wpdb->commentmeta} WHERE meta_key = '_wp_trash_meta_time'",ARRAY_A) as $r){
        if((int)$r['meta_value']>=$limit)continue;$id=(int)$r['comment_id'];$c=get_comment($id);
        if(!$c||!in_array((string)$c->comment_approved,['trash','spam'],true)){ delete_comment_meta($id,'_wp_trash_meta_time');delete_comment_meta($id,'_wp_trash_meta_status'); }
        else wp_delete_comment($id,true);
    }
} }

/* ───────── Feeds ───────── */
if(!function_exists('elvado_ext_feed')){ function elvado_ext_feed($fmt) {   // vereinfachte Ausgabe der Hauptschleife (rss2/atom; rdf/rss als RSS-2-Ausschnitt)
    $x=fn($s)=>htmlspecialchars((string)$s,ENT_XML1|ENT_QUOTES,'UTF-8');$name=$x(get_bloginfo('name'));$home=$x(home_url('/'));$desc=$x(get_bloginfo('description'));
    if(!headers_sent())header('Content-Type: '.($fmt==='atom'?'application/atom+xml':($fmt==='rdf'?'application/rdf+xml':'application/rss+xml')).'; charset=UTF-8',true);
    echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
    if($fmt==='atom')echo '<feed xmlns="http://www.w3.org/2005/Atom"><title>'.$name.'</title><subtitle>'.$desc.'</subtitle><link rel="alternate" href="'.$home.'"/><id>'.$home.'</id><updated>'.gmdate('c').'</updated>'."\n";
    elseif($fmt==='rdf')echo '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/"><channel rdf:about="'.$home.'"><title>'.$name.'</title><link>'.$home.'</link><description>'.$desc.'</description></channel>'."\n";
    else echo '<rss version="'.($fmt==='rss'?'0.92':'2.0').'" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/"><channel><title>'.$name.'</title><link>'.$home.'</link><description>'.$desc.'</description><lastBuildDate>'.gmdate('r').'</lastBuildDate>'."\n";
    while(have_posts()){ the_post();$t=$x(get_the_title());$l=$x(get_permalink());$d=$x(get_post_time('r',true));$ex=$x(get_the_excerpt());
        if($fmt==='atom')echo '<entry><title>'.$t.'</title><link rel="alternate" href="'.$l.'"/><id>'.$l.'</id><updated>'.$x(get_post_time('c',true)).'</updated><summary>'.$ex.'</summary></entry>'."\n";
        elseif($fmt==='rdf')echo '<item rdf:about="'.$l.'"><title>'.$t.'</title><link>'.$l.'</link><description>'.$ex.'</description></item>'."\n";
        else echo '<item><title>'.$t.'</title><link>'.$l.'</link><guid isPermaLink="false">'.$l.'</guid><pubDate>'.$d.'</pubDate><description>'.$ex.'</description></item>'."\n";
    }
    echo $fmt==='atom'?'</feed>':($fmt==='rdf'?'</rdf:RDF>':'</channel></rss>');
} }
if(!function_exists('do_feed_rdf')){ function do_feed_rdf() { elvado_ext_feed('rdf'); } }
if(!function_exists('do_feed_rss')){ function do_feed_rss() { elvado_ext_feed('rss'); } }
if(!function_exists('do_feed_rss2')){ function do_feed_rss2($for_comments=false) { elvado_ext_feed('rss2'); } }
if(!function_exists('do_feed_atom')){ function do_feed_atom($for_comments=false) { elvado_ext_feed('atom'); } }
if(!function_exists('do_feed')){ function do_feed() {
    global $wp_query;
    $feed=preg_replace('/^_+/','',(string)get_query_var('feed'));
    if($feed===''||$feed==='feed')$feed=(string)apply_filters('default_feed','rss2');
    if(in_array($feed,['rdf','rss','rss2','atom'],true)&&!has_action("do_feed_$feed"))add_action("do_feed_$feed","do_feed_$feed",10,1);
    if(!has_action("do_feed_$feed"))wp_die(sprintf(__('<strong>Error:</strong> %s is not a valid feed template.'),esc_html($feed)),'',['response'=>404,'back_link'=>true]);
    do_action("do_feed_{$feed}",!empty($wp_query->is_comment_feed),$feed);
} }

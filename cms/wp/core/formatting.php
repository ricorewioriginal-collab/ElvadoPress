<?php
// WordPress-kompatible Formatierungs-, Escaping- und Sanitizing-Funktionen (eigenständig implementiert).

/* ───────── Escaping ───────── */
function _wp_specialchars($text, $quote_style=ENT_NOQUOTES, $charset=false, $double_encode=false) {
    $text=(string)$text;if($text==='')return '';
    if(!preg_match('/[&<>"\']/',$text))return $text;
    return htmlspecialchars($text,$quote_style,'UTF-8',$double_encode);
}
function esc_html($text) { $safe=wp_check_invalid_utf8((string)$text); return apply_filters('esc_html',_wp_specialchars($safe,ENT_QUOTES,false,false),$text); }
function esc_attr($text) { $safe=wp_check_invalid_utf8((string)$text); return apply_filters('attribute_escape',_wp_specialchars($safe,ENT_QUOTES,false,false),$text); }
function esc_textarea($text) { return apply_filters('esc_textarea',htmlspecialchars((string)$text,ENT_QUOTES,'UTF-8'),$text); }
function esc_js($text) {
    $safe=wp_check_invalid_utf8((string)$text);$safe=_wp_specialchars($safe,ENT_COMPAT);
    $safe=str_ireplace(['&#039;','&#x27;','&#39;'],"'",stripslashes($safe));
    $safe=str_replace("\r",'',$safe);$safe=str_replace("\n",'\\n',addslashes($safe));
    return apply_filters('js_escape',$safe,$text);
}
function esc_xml($text) { return htmlspecialchars((string)$text,ENT_QUOTES|ENT_XML1,'UTF-8',false); }
function esc_sql($data) { if(is_array($data)){foreach($data as $k=>$v)$data[$k]=esc_sql($v);return $data;} return str_replace(["\\","\0","'","\"","\n","\r","\x1a"],["\\\\","\\0","\\'","\\\"","\\n","\\r","\\Z"],(string)$data); }
function wp_check_invalid_utf8($text, $strip=false) {
    $text=(string)$text;if($text==='')return '';
    if(preg_match('//u',$text))return $text;
    return $strip&&function_exists('iconv')?(string)@iconv('UTF-8','UTF-8//IGNORE',$text):'';
}
function wp_allowed_protocols() { static $p=['http','https','ftp','ftps','mailto','news','irc','irc6','ircs','gopher','nntp','feed','telnet','mms','rtsp','sms','svn','tel','fax','xmpp','webcal','urn']; return $p; }
function wp_kses_bad_protocol($content, $allowed) {
    $content=(string)$content;
    $content=preg_replace('/[\x00-\x20]+/','',html_entity_decode($content,ENT_QUOTES|ENT_HTML5,'UTF-8'));
    if(!preg_match('#^([a-z][a-z0-9+.\-]*):#i',$content,$m))return true;      // relativ: ok
    return in_array(strtolower($m[1]),array_map('strtolower',$allowed),true);
}
function esc_url($url, $protocols=null, $_context='display') {
    $original=$url;$url=(string)$url;if(trim($url)==='')return '';
    $url=str_replace(' ','%20',ltrim($url));
    $url=preg_replace('|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\x80-\xff]|i','',$url);
    if($url==='')return '';
    if(0!==stripos($url,'mailto:'))$url=str_ireplace(['%0d','%0a'],'',$url);
    $url=str_replace(';//','://',$url);
    $hasScheme=(bool)preg_match('#^([a-z][a-z0-9+.\-]*):#i',$url,$sm);
    if(!$hasScheme&&!in_array($url[0],['/','#','?'],true)&&!preg_match('/^[a-z0-9-]+?\.php/i',$url)&&preg_match('#^[^/?#]+\.[a-z]{2,}#i',$url))$url='http://'.$url;
    if($hasScheme&&!in_array(strtolower($sm[1]),array_map('strtolower',$protocols??wp_allowed_protocols()),true))return '';
    if($_context==='display')$url=preg_replace('/&([^#])(?![a-z1-4]{1,8};)/i','&#038;$1',$url);
    $url=str_replace("'",'&#039;',$url);
    return apply_filters('clean_url',$url,$original,$_context);
}
function esc_url_raw($url, $protocols=null) { return esc_url($url,$protocols,'db'); }
function sanitize_url($url, $protocols=null) { return esc_url($url,$protocols,'db'); }
function wp_http_validate_url($url) {
    $p=parse_url((string)$url);if(!$p||empty($p['host'])||!in_array($p['scheme']??'',['http','https'],true))return false;
    return $url;
}

/* ───────── Sanitizing ───────── */
function wp_strip_all_tags($text, $remove_breaks=false) {
    $text=preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si','',(string)$text);$text=strip_tags($text);
    if($remove_breaks)$text=preg_replace('/[\r\n\t ]+/',' ',$text);
    return trim($text);
}
function _sanitize_text_fields($str, $keep_newlines=false) {
    if(is_object($str)||is_array($str))return '';
    $str=(string)$str;$filtered=wp_check_invalid_utf8($str);
    if(str_contains($filtered,'<')){$filtered=wp_pre_kses_less_than($filtered);$filtered=wp_strip_all_tags($filtered,false);$filtered=str_replace("<\n","&lt;\n",$filtered);}
    if(!$keep_newlines)$filtered=preg_replace('/[\r\n\t ]+/',' ',$filtered);
    $filtered=trim($filtered);
    while(preg_match('/%[a-f0-9]{2}/i',$filtered,$m))$filtered=str_replace($m[0],'',$filtered);
    return $filtered;
}
function wp_pre_kses_less_than($content) { return preg_replace_callback('%<[^>]*?((?=<)|>|$)%',function($m){ return str_contains($m[0],'>')?$m[0]:esc_html($m[0]); },$content); }
function sanitize_text_field($str) { return apply_filters('sanitize_text_field',_sanitize_text_fields($str,false),$str); }
function sanitize_textarea_field($str) { return apply_filters('sanitize_textarea_field',_sanitize_text_fields($str,true),$str); }
function sanitize_key($key) { $k=strtolower((string)$key);$k=preg_replace('/[^a-z0-9_\-]/','',$k);return apply_filters('sanitize_key',$k,$key); }
function sanitize_html_class($classname, $fallback='') { $s=preg_replace('/%[a-fA-F0-9][a-fA-F0-9]/','',(string)$classname);$s=preg_replace('/[^A-Za-z0-9_-]/','',$s);return $s===''&&$fallback!==''?$fallback:$s; }
function sanitize_email($email) {
    $email=(string)$email;if(strlen($email)<6)return '';
    $email=preg_replace('/[^a-z0-9+_.@\-!#$%&\'*\/=?^`{|}~]/i','',$email);
    if(substr_count($email,'@')!==1)return '';
    [$local,$domain]=explode('@',$email);if($local===''||$domain==='')return '';
    if(!preg_match('/^[a-z0-9.-]+$/i',$domain)||!str_contains($domain,'.'))return '';
    return $email;
}
function is_email($email, $deprecated=false) { return filter_var((string)$email,FILTER_VALIDATE_EMAIL)?apply_filters('is_email',$email,$email,null):false; }
function sanitize_user($username, $strict=false) {
    $u=wp_strip_all_tags((string)$username);$u=preg_replace('/%[a-f0-9]{2}/i','',$u);$u=preg_replace('/&.+?;/','',$u);
    if($strict)$u=preg_replace('|[^a-z0-9 _.\-@]|i','',$u);
    return apply_filters('sanitize_user',trim(preg_replace('|\s+|',' ',$u)),$username,$strict);
}
function sanitize_hex_color($color) { if(''===$color)return ''; return preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|',(string)$color)?$color:null; }
function sanitize_hex_color_no_hash($color) { $c=ltrim((string)$color,'#');return $c===''?'':(sanitize_hex_color('#'.$c)?$c:null); }
function sanitize_mime_type($m) { return preg_replace('/[^-+*.a-zA-Z0-9\/]/','',(string)$m); }
function sanitize_file_name($filename) {
    $f=preg_replace('/[\r\n\t ]+/','-',(string)$filename);$f=preg_replace('/[\x00-\x1f\/\\\\?%*:|"<>]+/','',$f);
    $f=remove_accents($f);$f=preg_replace('/[^A-Za-z0-9._-]/','-',$f);$f=preg_replace('/-+/','-',$f);$f=trim($f,'.-_');
    $f=preg_replace('/\.(php\d?|phtml|phar|pl|py|cgi|asp|jsp|sh|exe)(\.|$)/i','.$1_$2',$f);
    return apply_filters('sanitize_file_name',$f===''?'file':$f,$filename);
}
function remove_accents($text) {
    $text=(string)$text;if(!preg_match('/[\x80-\xff]/',$text))return $text;
    $map=['ä'=>'ae','ö'=>'oe','ü'=>'ue','Ä'=>'Ae','Ö'=>'Oe','Ü'=>'Ue','ß'=>'ss','Æ'=>'AE','æ'=>'ae','Œ'=>'OE','œ'=>'oe','Ø'=>'O','ø'=>'o','Å'=>'A','å'=>'a','Đ'=>'D','đ'=>'d','Þ'=>'TH','þ'=>'th','Ł'=>'L','ł'=>'l','€'=>'E'];
    $text=strtr($text,$map);
    if(class_exists('Transliterator')){$t=@transliterator_transliterate('Any-Latin; Latin-ASCII',$text);if(is_string($t))return $t;}
    if(function_exists('iconv')){$t=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$text);if(is_string($t)&&$t!=='')return preg_replace('/[\'`^~"]/','',$t);}
    return $text;
}
function sanitize_title($title, $fallback_title='', $context='save') {
    $raw=$title;$title=strip_tags((string)$title);$title=apply_filters('sanitize_title',sanitize_title_with_dashes($title,$raw,$context),$raw,$context);
    return $title===''?$fallback_title:$title;
}
function sanitize_title_with_dashes($title, $raw_title='', $context='display') {
    $title=strip_tags((string)$title);$title=preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|','---$1---',$title);$title=str_replace('%','',$title);$title=preg_replace('|---([a-fA-F0-9][a-fA-F0-9])---|','%$1',$title);
    $title=remove_accents($title);$title=strtolower($title);
    $title=preg_replace('/&.+?;/','',$title);$title=str_replace('.','-',$title);
    $title=preg_replace('/[^%a-z0-9 _-]/','',$title);$title=preg_replace('/\s+/','-',$title);$title=preg_replace('|-+|','-',$title);
    return trim($title,'-');
}
function sanitize_term_slug($slug) { return sanitize_title($slug); }
function absint($v) { return abs((int)$v); }
function zeroise($number, $threshold) { return sprintf('%0'.(int)$threshold.'s',$number); }
function wp_trim_words($text, $num_words=55, $more=null) {
    if(null===$more)$more='&hellip;';$text=wp_strip_all_tags((string)$text);
    $words=preg_split('/[\n\r\t ]+/',trim($text),$num_words+1,PREG_SPLIT_NO_EMPTY);
    if(count($words)>$num_words){array_pop($words);return implode(' ',$words).$more;}
    return implode(' ',$words);
}
function wp_html_excerpt($str, $count, $more=null) { $s=wp_strip_all_tags((string)$str,true);$s=mb_substr($s,0,$count);return $s.($more??''); }
function wp_specialchars_decode($text, $quote_style=ENT_NOQUOTES) { return htmlspecialchars_decode((string)$text,$quote_style); }
function force_balance_tags($text) {
    if(trim((string)$text)==='')return (string)$text;
    $d=new DOMDocument();libxml_use_internal_errors(true);
    $d->loadHTML('<?xml encoding="utf-8"?><div id="rrwbal">'.$text.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);libxml_clear_errors();
    $n=$d->getElementById('rrwbal');if(!$n)return (string)$text;$o='';foreach($n->childNodes as $c)$o.=$d->saveHTML($c);return $o;
}
function balanceTags($text, $force=false) { return $force?force_balance_tags($text):(string)$text; }
function convert_chars($c) { return (string)$c; }
function wptexturize($text, $reset=false) { return (string)$text; }
function wp_replace_in_html_tags($haystack, $replace_pairs) { return strtr((string)$haystack,$replace_pairs); }
function make_clickable($text) {
    return (string)preg_replace_callback('#(?<![">=\'])\b(https?://[^\s<>"\']+)#i',function($m){ $u=rtrim($m[1],'.,;:!?)');$tail=substr($m[1],strlen($u)); return '<a href="'.esc_url($u).'" rel="nofollow">'.esc_html($u).'</a>'.$tail; },(string)$text);
}
function wp_make_link_relative($link) { return preg_replace('|^(https?:)?//[^/]+(/?.*)|i','$2',(string)$link); }
function wpautop($text, $br=true) {
    $text=(string)$text;if(trim($text)==='')return '';
    $text=str_replace(["\r\n","\r"],"\n",$text)."\n";
    $blocks='table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|form|map|area|blockquote|address|math|style|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary';
    $text=preg_replace('|<br\s*/?>\s*<br\s*/?>|',"\n\n",$text);
    $text=preg_replace('!(<(?:'.$blocks.')[\s/>])!',"\n\n$1",$text);
    $text=preg_replace('!(</(?:'.$blocks.')>)!',"$1\n\n",$text);
    $text=preg_replace("/\n\n+/","\n\n",$text);
    $parts=preg_split('/\n\s*\n/',$text,-1,PREG_SPLIT_NO_EMPTY);$out='';
    foreach($parts as $p){$out.='<p>'.trim($p,"\n")."</p>\n";}
    $out=preg_replace('|<p>\s*</p>|','',$out);
    $out=preg_replace('!<p>\s*(</?(?:'.$blocks.')(?:[\s/>][^>]*)?>)!','$1',$out);
    $out=preg_replace('!(</?(?:'.$blocks.')(?:[\s/>][^>]*)?>)\s*</p>!','$1',$out);
    if($br)$out=preg_replace_callback('/<(script|style|pre).*?<\/\\1>/s',fn($m)=>str_replace("\n",'<WPPreserveNewline />',$m[0]),$out);
    if($br){$out=preg_replace('|(?<!<br />)\s*\n|',"<br />\n",$out);$out=str_replace('<WPPreserveNewline />',"\n",$out);}
    $out=preg_replace('!(</?(?:'.$blocks.')[^>]*>)\s*<br />!','$1',$out);
    return rtrim($out);
}
function wpautop_remove($t) { return $t; }
function shortcode_unautop($text) { return (string)$text; }
function nl2br_wp($t) { return nl2br((string)$t); }

/* ───────── wp_kses ───────── */
function wp_kses_allowed_html($context='') {
    $g=['class'=>true,'id'=>true,'style'=>true,'title'=>true,'lang'=>true,'dir'=>true,'role'=>true,'aria-*'=>true,'data-*'=>true];
    $tags=['a'=>['href'=>true,'rel'=>true,'rev'=>true,'name'=>true,'target'=>true,'download'=>['valueless'=>'y']],'abbr'=>[],'acronym'=>[],'address'=>[],'article'=>[],'aside'=>[],'audio'=>['controls'=>true,'src'=>true,'loop'=>true,'preload'=>true],
        'b'=>[],'big'=>[],'blockquote'=>['cite'=>true],'br'=>[],'caption'=>[],'cite'=>[],'code'=>[],'col'=>['span'=>true,'width'=>true],'colgroup'=>['span'=>true,'width'=>true],'dd'=>[],'del'=>['datetime'=>true],'details'=>['open'=>true],'dfn'=>[],'div'=>[],'dl'=>[],'dt'=>[],'em'=>[],'figcaption'=>[],'figure'=>[],'footer'=>[],
        'h1'=>[],'h2'=>[],'h3'=>[],'h4'=>[],'h5'=>[],'h6'=>[],'header'=>[],'hr'=>[],'i'=>[],'img'=>['alt'=>true,'height'=>true,'src'=>true,'width'=>true,'srcset'=>true,'sizes'=>true,'loading'=>true],'ins'=>['datetime'=>true],'kbd'=>[],'li'=>['value'=>true],'main'=>[],'mark'=>[],'nav'=>[],
        'ol'=>['start'=>true,'type'=>true,'reversed'=>true],'p'=>[],'pre'=>[],'q'=>['cite'=>true],'s'=>[],'samp'=>[],'section'=>[],'small'=>[],'span'=>[],'strike'=>[],'strong'=>[],'sub'=>[],'summary'=>[],'sup'=>[],
        'table'=>['width'=>true,'border'=>true,'cellpadding'=>true,'cellspacing'=>true],'tbody'=>[],'td'=>['colspan'=>true,'rowspan'=>true,'headers'=>true,'scope'=>true,'width'=>true],'tfoot'=>[],'th'=>['colspan'=>true,'rowspan'=>true,'scope'=>true,'width'=>true],'thead'=>[],'time'=>['datetime'=>true],'tr'=>[],'tt'=>[],'u'=>[],'ul'=>['type'=>true],'var'=>[],'video'=>['controls'=>true,'height'=>true,'src'=>true,'width'=>true,'poster'=>true,'loop'=>true,'preload'=>true,'muted'=>true]];
    if($context==='strip'||$context==='')return [];
    if($context==='data'||$context==='user_description'){$tags=array_intersect_key($tags,array_flip(['a','abbr','acronym','b','blockquote','cite','code','del','em','i','q','strike','strong']));}
    foreach($tags as $t=>$attrs)$tags[$t]=$attrs+$g;
    return apply_filters('wp_kses_allowed_html',$tags,$context);
}
function wp_kses($content, $allowed_html, $allowed_protocols=[]) {
    $content=(string)$content;
    if(is_string($allowed_html))$allowed_html=wp_kses_allowed_html($allowed_html);
    $allowed=[];foreach((array)$allowed_html as $t=>$a)$allowed[strtolower($t)]=array_change_key_case((array)$a,CASE_LOWER);
    if(empty($allowed_protocols))$allowed_protocols=wp_allowed_protocols();
    $content=wp_check_invalid_utf8($content);
    return (string)preg_replace_callback('%(<!--.*?(-->|$))|(<[^>]*(>|$)|>)%s',function($m) use($allowed,$allowed_protocols){
        if($m[1]!=='')return '';                       // Kommentare entfernen
        $el=$m[3];
        if($el==='>')return '&gt;';
        if(!str_ends_with($el,'>'))return esc_html($el);
        if(!preg_match('%^<\s*(/\s*)?([a-zA-Z0-9-]+)((?:\s*[^>]*?)?)\s*(/?)\s*>$%s',$el,$t))return '';
        $closing=trim($t[1])!=='';$name=strtolower($t[2]);
        if(!isset($allowed[$name]))return '';
        if($closing)return '</'.$name.'>';
        $attrs='';$ok=$allowed[$name];
        if(preg_match_all('%([a-zA-Z_:][\w:.-]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+)))?%',$t[3],$am,PREG_SET_ORDER)){
            foreach($am as $a){
                $an=strtolower($a[1]);$val=$a[2]??'';if($val===''&&isset($a[3]))$val=$a[3];if($val===''&&isset($a[4]))$val=$a[4];
                $has=array_key_exists($an,$ok);
                if(!$has){ $pref=false; foreach(['data-','aria-'] as $px)if(str_starts_with($an,$px)&&isset($ok[$px.'*']))$pref=true; if(!$pref)continue; }
                if(str_starts_with($an,'on'))continue;
                if(in_array($an,['href','src','poster','cite','action','formaction','longdesc','usemap'],true)){ if(!wp_kses_bad_protocol($val,$allowed_protocols))continue; }
                if($an==='style'){ if(preg_match('/expression\s*\(|javascript:|url\s*\(\s*[\'"]?\s*(?:javascript|data):|behavior:|-moz-binding/i',$val))continue; $val=str_replace(['<','>'],'',$val); }
                $attrs.=' '.$an.'="'.htmlspecialchars(html_entity_decode($val,ENT_QUOTES|ENT_HTML5,'UTF-8'),ENT_QUOTES,'UTF-8').'"';
            }
        }
        return '<'.$name.$attrs.($t[4]==='/'?' /':'').'>';
    },$content);
}
function wp_kses_post($data) { return wp_kses($data,'post'); }
function wp_kses_data($data) { return wp_kses($data,'data'); }
function wp_kses_post_deep($d) { return map_deep($d,'wp_kses_post'); }
function wp_filter_post_kses($d) { return wp_kses($d,'post'); }
function wp_filter_nohtml_kses($d) { return wp_kses((string)$d,'strip'); }
function wp_kses_allowed_html_for($c) { return wp_kses_allowed_html($c); }
function map_deep($value, $callback) {
    if(is_array($value)){foreach($value as $k=>$v)$value[$k]=map_deep($v,$callback);return $value;}
    if(is_object($value)){foreach(get_object_vars($value) as $k=>$v)$value->$k=map_deep($v,$callback);return $value;}
    return call_user_func($callback,$value);
}

/* ───────── Arrays / Serialisierung / Slashes ───────── */
function wp_parse_args($args, $defaults=[]) {
    if(is_object($args))$r=get_object_vars($args);elseif(is_array($args))$r=&$args;else wp_parse_str($args,$r);
    return is_array($defaults)&&$defaults?array_merge($defaults,$r):$r;
}
function wp_parse_str($input_string, &$result) { parse_str((string)$input_string,$result); $result=apply_filters('wp_parse_str',$result,$input_string); }
function wp_parse_list($input_list) { if(!is_array($input_list))return preg_split('/[\s,]+/',(string)$input_list,-1,PREG_SPLIT_NO_EMPTY); return $input_list; }
function wp_parse_id_list($list) { return array_values(array_unique(array_map('absint',wp_parse_list($list)))); }
function wp_parse_slug_list($list) { return array_values(array_unique(array_map('sanitize_title',wp_parse_list($list)))); }
function wp_array_slice_assoc($array, $keys) { $r=[];foreach($keys as $k)if(isset($array[$k]))$r[$k]=$array[$k];return $r; }
function wp_list_pluck($input_list, $field, $index_key=null) {
    $out=[];foreach((array)$input_list as $k=>$o){ $v=is_object($o)?($o->$field??null):($o[$field]??null); if($index_key===null)$out[$k]=$v; else $out[is_object($o)?$o->$index_key:$o[$index_key]]=$v; }
    return $out;
}
function wp_list_filter($input_list, $args=[], $operator='AND') {
    if(!is_array($input_list))return [];if(empty($args))return $input_list;$out=[];
    foreach($input_list as $k=>$o){ $m=0;foreach($args as $f=>$v){ $ov=is_object($o)?($o->$f??null):($o[$f]??null); if($ov==$v)$m++; }
        if(($operator==='AND'&&$m===count($args))||($operator==='OR'&&$m>0)||($operator==='NOT'&&$m===0))$out[$k]=$o; }
    return $out;
}
function wp_list_sort($list, $orderby=[], $order='ASC', $preserve_keys=false) {
    if(!is_array($list))return [];$orderby=is_string($orderby)?[$orderby=>$order]:$orderby;
    $cmp=function($a,$b) use($orderby){ foreach($orderby as $f=>$o){ $x=is_object($a)?($a->$f??null):($a[$f]??null);$y=is_object($b)?($b->$f??null):($b[$f]??null); if($x==$y)continue; $r=$x<=>$y; return strtoupper((string)$o)==='DESC'?-$r:$r; } return 0; };
    if($preserve_keys)uasort($list,$cmp);else{usort($list,$cmp);}return $list;
}
function is_serialized($data, $strict=true) {
    if(!is_string($data))return false;$data=trim($data);if($data==='N;')return true;if(strlen($data)<4||$data[1]!==':')return false;
    return (bool)preg_match('/^[aOsidb]:/',$data)&&($data[-1]===';'||$data[-1]==='}');
}
function maybe_serialize($data) { return (is_array($data)||is_object($data)||is_serialized($data,false))?serialize($data):$data; }
function maybe_unserialize($data) { if(is_serialized($data)){ $r=@unserialize(trim($data)); return $r===false&&trim($data)!=='b:0;'?$data:$r; } return $data; }
function wp_slash($v) { return is_array($v)?array_map('wp_slash',$v):(is_string($v)?addslashes($v):$v); }
function wp_unslash($v) { return stripslashes_deep($v); }
function stripslashes_deep($v) { return map_deep($v,fn($x)=>is_string($x)?stripslashes($x):$x); }
function addslashes_gpc($g) { return wp_slash($g); }
function stripslashes_from_strings_only($v) { return is_string($v)?stripslashes($v):$v; }
function wp_json_encode($data, $options=0, $depth=512) { return json_encode($data,$options|JSON_PARTIAL_OUTPUT_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE,$depth); }

/* ───────── URLs ───────── */
function trailingslashit($v) { return untrailingslashit($v).'/'; }
function untrailingslashit($v) { return rtrim((string)$v,'/\\'); }
function wp_parse_url($url, $component=-1) {
    $p=parse_url((string)$url,$component);return $p;
}
function add_query_arg(...$args) {
    if(is_array($args[0])){ $uri=$args[1]??($_SERVER['REQUEST_URI']??'');$new=$args[0]; }
    else { $uri=$args[2]??($_SERVER['REQUEST_URI']??'');$new=[$args[0]=>$args[1]??null]; }
    $frag='';if(str_contains($uri,'#')){[$uri,$frag]=explode('#',$uri,2);$frag='#'.$frag;}
    $q='';if(str_contains($uri,'?')){[$uri,$q]=explode('?',$uri,2);}
    parse_str($q,$qs);
    foreach($new as $k=>$v){ if($v===false||$v===null)unset($qs[$k]);else $qs[$k]=$v; }
    $qs=array_map(fn($x)=>is_string($x)?rawurldecode($x):$x,$qs);
    $built=http_build_query($qs,'','&',PHP_QUERY_RFC3986);
    return $uri.($built!==''?'?'.$built:'').$frag;
}
function remove_query_arg($key, $query=false) { $keys=(array)$key; $args=array_fill_keys($keys,false); return $query===false?add_query_arg($args):add_query_arg($args,$query); }
function wp_nonce_url($actionurl, $action=-1, $name='_wpnonce') { return add_query_arg($name,wp_create_nonce($action),str_replace('&amp;','&',$actionurl)); }
function build_query($data) { return http_build_query($data,'','&',PHP_QUERY_RFC3986); }
function urlencode_deep($v) { return map_deep($v,'urlencode'); }
function rawurlencode_deep($v) { return map_deep($v,'rawurlencode'); }

/* ───────── Zeit / Zahlen ───────── */
function size_format($bytes, $decimals=0) {
    $q=['TB'=>1024**4,'GB'=>1024**3,'MB'=>1024**2,'KB'=>1024,'B'=>1];$bytes=(float)$bytes;if($bytes<=0)return false;
    foreach($q as $u=>$m)if($bytes>=$m)return number_format_i18n($bytes/$m,$decimals).' '.$u;
    return false;
}
function number_format_i18n($number, $decimals=0) { return number_format((float)$number,(int)$decimals,',','.'); }
function human_time_diff($from, $to=0) {
    $to=$to?:time();$d=abs($to-$from);
    if($d<3600){$n=max(1,(int)round($d/60));return $n===1?'1 Minute':$n.' Minuten';}
    if($d<86400){$n=max(1,(int)round($d/3600));return $n===1?'1 Stunde':$n.' Stunden';}
    if($d<2592000){$n=max(1,(int)round($d/86400));return $n===1?'1 Tag':$n.' Tage';}
    if($d<31536000){$n=max(1,(int)round($d/2592000));return $n===1?'1 Monat':$n.' Monate';}
    $n=max(1,(int)round($d/31536000));return $n===1?'1 Jahr':$n.' Jahre';
}
function wp_timezone_string() { $tz=get_option('timezone_string');return $tz?:'UTC'; }
function wp_timezone() { try{return new DateTimeZone(wp_timezone_string());}catch(Throwable $e){return new DateTimeZone('UTC');} }
function current_time($type, $gmt=0) {
    if($type==='timestamp'||$type==='U')return $gmt?time():(new DateTime('now',wp_timezone()))->getTimestamp()+(new DateTime('now',wp_timezone()))->getOffset();
    if($type==='mysql')$type='Y-m-d H:i:s';
    $dt=new DateTime('now',$gmt?new DateTimeZone('UTC'):wp_timezone());return $dt->format($type);
}
function wp_date($format, $timestamp=null, $timezone=null) {
    $timestamp=$timestamp??time();$dt=new DateTime('@'.$timestamp);$dt->setTimezone($timezone??wp_timezone());
    $out=$dt->format((string)$format);
    $en=['January','February','March','April','May','June','July','August','September','October','November','December','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    $de=['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag','Sonntag'];
    $out=str_replace($en,$de,$out);
    return apply_filters('wp_date',$out,$format,$timestamp,$timezone);
}
function date_i18n($format, $timestamp_with_offset=false, $gmt=false) { $ts=$timestamp_with_offset===false?time():(int)$timestamp_with_offset;return wp_date($format,$ts,$gmt?new DateTimeZone('UTC'):null); }
function mysql2date($format, $date, $translate=true) {
    if(empty($date))return false;$ts=strtotime((string)$date.' '.(str_contains((string)$date,':')?'':'00:00:00'));if($ts===false)return false;
    if($format==='G'||$format==='U')return $ts;return $translate?wp_date($format,$ts):gmdate($format,$ts);
}
function get_gmt_from_date($string, $format='Y-m-d H:i:s') { $dt=date_create((string)$string,wp_timezone());return $dt?$dt->setTimezone(new DateTimeZone('UTC'))->format($format):false; }
function get_date_from_gmt($string, $format='Y-m-d H:i:s') { $dt=date_create((string)$string,new DateTimeZone('UTC'));return $dt?$dt->setTimezone(wp_timezone())->format($format):false; }
function is_serialized_string($d) { return is_string($d)&&preg_match('/^s:\d+:/',$d)===1; }

<?php
// Ergänzende Formatierungs-Funktionen (Bereich Ausgabe): Aufteilen von HTML, Hilfsfunktionen für Links/Entitäten, Rel-Attribute, Smileys, Emoji-Platzhalter.

/* ───────── HTML-Aufteilung / Texturize-Helfer ───────── */
if(!function_exists('wp_spaces_regexp')){ function wp_spaces_regexp() { return apply_filters('wp_spaces_regexp','[\r\n\t ]|\xC2\xA0|&nbsp;'); } }
if(!function_exists('get_html_split_regex')){
    /** Regex, die Text an HTML-Kommentaren, CDATA und Tags trennt (Trenner bleiben erhalten). */
    function get_html_split_regex() { static $r=null;if($r===null)$r='/(<!--.*?(?:-->|$)|<!\[CDATA\[.*?(?:\]\]>|$)|<(?=[a-zA-Z\/!?])(?:[^"\'>]|"[^"]*"|\'[^\']*\')*>)/s';return $r; }
}
if(!function_exists('wp_html_split')){ function wp_html_split($input) { return preg_split(get_html_split_regex(),(string)$input,-1,PREG_SPLIT_DELIM_CAPTURE); } }
if(!function_exists('_get_wptexturize_split_regex')){
    function _get_wptexturize_split_regex($shortcode_regex='') {
        $inner=substr(get_html_split_regex(),2,-3);   // Muster ohne „/(“ und „)/s“
        return '/('.$inner.($shortcode_regex!==''?'|'.$shortcode_regex:'').')/s';
    }
}
if(!function_exists('_get_wptexturize_shortcode_regex')){
    function _get_wptexturize_shortcode_regex($tagnames) { return '\[\/?(?:'.implode('|',array_map(fn($t)=>preg_quote((string)$t,'/'),(array)$tagnames)).')(?![\w-])[^\]]*\]'; }
}
if(!function_exists('_wptexturize_pushpop_element')){
    /** Merkt offene gesperrte Elemente (HTML-Tags oder Shortcodes) in einem Stapel; schließende Tags räumen ihn ab. */
    function _wptexturize_pushpop_element($text, &$stack, $disabled_elements) {
        $text=(string)$text;if(strlen($text)<3)return;
        $closing=($text[1]==='/');
        if(!preg_match('/^.\/?\s*([a-zA-Z0-9_-]+)/',$text,$m))return;$name=strtolower($m[1]);
        if(!in_array($name,(array)$disabled_elements,true))return;
        if($closing){ $i=array_search($name,$stack,true);if($i!==false)$stack=array_slice($stack,0,(int)$i); }
        elseif(!str_ends_with($text,'/>')&&!str_ends_with($text,'/]'))$stack[]=$name;
    }
}
if(!function_exists('wptexturize_primes')){
    /** Ersetzt Zollzeichen/Anführungszeichen nach Ziffern durch Primes, übrige schließende durch $close_quote. */
    function wptexturize_primes($haystack, $needle, $prime, $open_quote, $close_quote) {
        $spaces=wp_spaces_regexp();$q=preg_quote($needle,'/');
        $quote_pattern="/$q(?=\\Z|[.,:;!?)}\\-\\]]|&gt;|".$spaces.")/";$prime_pattern="/(?<=\\d)$q/";
        $parts=explode($open_quote,(string)$haystack);
        foreach($parts as $k=>$s){
            if(!str_contains($s,$needle))continue;
            $n=substr_count($s,$needle);
            $s=preg_replace($prime_pattern,$prime,$s);
            if($n>=2||$k>0)$s=preg_replace($quote_pattern,$close_quote,$s);   // in einem geöffneten Zitat schließt das letzte Zeichen
            $parts[$k]=$s;
        }
        return implode($open_quote,$parts);
    }
}
if(!function_exists('_autop_newline_preservation_helper')){ function _autop_newline_preservation_helper($matches) { return str_replace("\n","<WPPreserveNewline />",$matches[0]); } }

/* ───────── Zeichen / Entitäten ───────── */
if(!function_exists('seems_utf8')){ function seems_utf8($str) { return (bool)preg_match('//u',(string)$str); } }
if(!function_exists('utf8_uri_encode')){
    /** UTF-8-Text als %XX-Folge (Mehrbyte-Zeichen immer, ASCII nur auf Wunsch); $length begrenzt die Ausgabelänge. */
    function utf8_uri_encode($utf8_string, $length=0, $encode_ascii_characters=false) {
        $chars=preg_split('//u',(string)$utf8_string,-1,PREG_SPLIT_NO_EMPTY);if($chars===false)return '';
        $out='';$len=0;
        foreach($chars as $c){
            if(strlen($c)===1){ $e=$encode_ascii_characters?rawurlencode($c):$c; }
            else $e=implode('',array_map(fn($b)=>sprintf('%%%02X',ord($b)),str_split($c)));
            if($length&&$len+strlen($e)>$length)break;
            $out.=$e;$len+=strlen($e);
        }
        return $out;
    }
}
if(!function_exists('sanitize_locale_name')){ function sanitize_locale_name($locale) { return is_string($locale)?preg_replace('/[^A-Za-z0-9_-]/','',$locale):''; } }
if(!function_exists('convert_invalid_entities')){
    /** Windows-1252-Zahlenentitäten (&#128; … &#159;) auf die richtigen Unicode-Entitäten umstellen. */
    function convert_invalid_entities($content) {
        static $map=[128=>8364,130=>8218,131=>402,132=>8222,133=>8230,134=>8224,135=>8225,136=>710,137=>8240,138=>352,139=>8249,140=>338,142=>381,145=>8216,146=>8217,147=>8220,148=>8221,149=>8226,150=>8211,151=>8212,152=>732,153=>8482,154=>353,155=>8250,156=>339,158=>382,159=>376];
        return preg_replace_callback('/&#(x[0-9a-f]{2}|1[2-5][0-9]);/i',function($m) use($map){ $n=strtolower($m[1][0])==='x'?hexdec(substr($m[1],1)):(int)$m[1];return isset($map[$n])?'&#'.$map[$n].';':$m[0]; },(string)$content);
    }
}
if(!function_exists('ent2ncr')){
    /** Benannte HTML-Entitäten (&nbsp;) in Zahlenentitäten (&#160;) umwandeln. */
    function ent2ncr($text) {
        static $map=null;
        if($map===null){ $map=['&apos;'=>'&#39;'];foreach(get_html_translation_table(HTML_ENTITIES,ENT_QUOTES|ENT_HTML401,'UTF-8') as $ch=>$ent)$map[$ent]='&#'.mb_ord($ch,'UTF-8').';'; }
        return strtr((string)$text,$map);
    }
}
if(!function_exists('htmlentities2')){
    /** Wie htmlentities, lässt aber vorhandene Entitäten unangetastet. */
    function htmlentities2($text) {
        $t=get_html_translation_table(HTML_ENTITIES,ENT_QUOTES,'UTF-8');$t[chr(38)]='&';
        return preg_replace('/&(?![A-Za-z]{0,4}\w{2,3};|#[0-9]{2,3};)/','&amp;',strtr((string)$text,$t));
    }
}
if(!function_exists('format_to_edit')){ function format_to_edit($content, $rich_text=false) { $content=apply_filters('format_to_edit',$content);return $rich_text?$content:esc_textarea((string)$content); } }
if(!function_exists('format_for_editor')){ function format_for_editor($text, $default_editor=null) { if($text)$text=htmlspecialchars((string)$text,ENT_NOQUOTES,'UTF-8');return apply_filters('format_for_editor',$text,$default_editor); } }
if(!function_exists('backslashit')){ function backslashit($string) { $string=(string)$string;if(isset($string[0])&&$string[0]>='0'&&$string[0]<='9')$string='\\\\'.$string;return addcslashes($string,'A..Za..z'); } }
if(!function_exists('urldecode_deep')){ function urldecode_deep($value) { return map_deep($value,'urldecode'); } }
if(!function_exists('_deep_replace')){ function _deep_replace($search, $subject) { $subject=(string)$subject;$count=1;while($count)$subject=str_replace($search,'',$subject,$count);return $subject; } }
if(!function_exists('antispambot')){
    /** E-Mail-Adresse zufällig als Entitäten/Zeichen mischen (gegen Adress-Sammler). */
    function antispambot($email_address, $hex_encoding=0) {
        $out='';$hex=(int)$hex_encoding?1:0;
        foreach(str_split((string)$email_address) as $c){ $j=random_int(0,1+$hex);$out.=$j===0?'&#'.ord($c).';':($j===1?$c:'%'.zeroise(dechex(ord($c)),2)); }
        return str_replace('@','&#64;',$out);
    }
}
if(!function_exists('normalize_whitespace')){ function normalize_whitespace($str) { $str=str_replace("\r","\n",trim((string)$str));return preg_replace(['/\n+/','/[ \t]+/'],["\n",' '],$str); } }
if(!function_exists('url_shorten')){ function url_shorten($url) { $s=untrailingslashit(str_replace(['https://','http://','www.'],'',(string)$url));return strlen($s)>35?substr($s,0,32).'&hellip;':$s; } }
if(!function_exists('maybe_hash_hex_color')){ function maybe_hash_hex_color($color) { $h=sanitize_hex_color_no_hash($color);return $h?'#'.$h:$color; } }
if(!function_exists('get_url_in_content')){ function get_url_in_content($content) { if(empty($content))return false;return preg_match('/<a\s[^>]*?href=([\'"])(.+?)\1/is',(string)$content,$m)?sanitize_url($m[2]):false; } }
if(!function_exists('sanitize_trackback_urls')){
    function sanitize_trackback_urls($to_ping) {
        $urls=array_filter(preg_split('/[\r\n\t ]/',trim((string)$to_ping),-1,PREG_SPLIT_NO_EMPTY),fn($u)=>(bool)preg_match('#^https?://.#i',$u));
        $urls=implode("\n",array_map('sanitize_url',$urls));
        return apply_filters('sanitize_trackback_urls',$urls,$to_ping);
    }
}
if(!function_exists('iso8601_to_datetime')){
    /** ISO-8601 (20240131T12:00:00+0200) in „Y-m-d H:i:s“; $timezone 'user' (Zeit unverändert) oder 'gmt'. */
    function iso8601_to_datetime($date_string, $timezone='user') {
        $timezone=strtolower((string)$timezone);$re='#([0-9]{4})-?([0-9]{2})-?([0-9]{2})T([0-9]{2}):?([0-9]{2}):?([0-9]{2})(Z|[+\-][0-9]{2,4})?#';
        if(!preg_match($re,(string)$date_string,$b))return false;
        if($timezone==='gmt'){
            $off=(int)get_option('gmt_offset')*3600;
            if(!empty($b[7])&&$b[7]!=='Z'){ $d=substr($b[7],1);$off=((int)substr($d,0,2)*3600+(int)substr($d,2,2)*60)*($b[7][0]==='-'?-1:1); } elseif(($b[7]??'')==='Z')$off=0;
            return gmdate('Y-m-d H:i:s',gmmktime((int)$b[4],(int)$b[5],(int)$b[6],(int)$b[2],(int)$b[3],(int)$b[1])-(int)$off);
        }
        return $timezone==='user'?"{$b[1]}-{$b[2]}-{$b[3]} {$b[4]}:{$b[5]}:{$b[6]}":false;
    }
}
if(!function_exists('_wp_iso_convert')){ function _wp_iso_convert($matches) { return chr((int)hexdec(strtolower($matches[1]))); } }
if(!function_exists('wp_iso_descrambler')){
    /** Q-codierten MIME-Betreff („=?iso-8859-1?q?…?=“) dekodieren. */
    function wp_iso_descrambler($string) {
        if(!preg_match('#\=\?(.+)\?Q\?(.+)\?\=#i',(string)$string,$m))return $string;
        return preg_replace_callback('#\=([0-9a-f]{2})#i','_wp_iso_convert',str_replace('_',' ',$m[2]));
    }
}
if(!function_exists('wp_sprintf')){
    /** sprintf mit Haken „wp_sprintf“ je Platzhalter; „%l“ fügt ein Array als Aufzählung ein. */
    function wp_sprintf($pattern, ...$args) {
        $pattern=(string)$pattern;$len=strlen($pattern);$start=0;$result='';$ai=0;
        while($len>$start){
            if($len-1===$start){ $result.=substr($pattern,-1);break; }
            if(substr($pattern,$start,2)==='%%'){ $start+=2;$result.='%';continue; }
            $end=strpos($pattern,'%',$start+1);if($end===false)$end=$len;
            $frag=substr($pattern,$start,$end-$start);
            if($pattern[$start]==='%'){
                if(preg_match('/^%(\d+)\$/',$frag,$m)){ $arg=$args[(int)$m[1]-1]??'';$frag=str_replace("%{$m[1]}$",'%',$frag); } else { $arg=$args[$ai]??'';$ai++; }
                $f2=apply_filters('wp_sprintf',$frag,$arg);
                if($f2!==$frag)$frag=$f2;
                elseif(str_starts_with($frag,'%l'))$frag=implode(', ',array_map('strval',(array)$arg)).substr($frag,2);
                else $frag=sprintf($frag,is_scalar($arg)||$arg===null?strval($arg):'');
            }
            $result.=$frag;$start=$end;
        }
        return $result;
    }
}
if(!function_exists('translate_smiley')){
    function translate_smiley($matches) {
        global $wpsmiliestrans;if(!$matches)return '';
        $smiley=trim(reset($matches));$img=$wpsmiliestrans[$smiley]??null;if($img===null)return $smiley;
        $ext=preg_match('/\.([^.]+)$/',$img,$m)?strtolower($m[1]):'';
        if(!in_array($ext,['jpg','jpeg','jpe','gif','png','webp','avif'],true))return $img;
        $src=apply_filters('smilies_src',includes_url("images/smilies/$img"),$img,site_url());
        return sprintf('<img src="%s" alt="%s" class="wp-smiley" style="height: 1em; max-height: 1em;" />',esc_url($src),esc_attr($smiley));
    }
}

/* ───────── Links im Text ───────── */
if(!function_exists('_make_clickable_rel_attr')){
    function _make_clickable_rel_attr($url) {
        $rel=[];$scheme=strtolower((string)wp_parse_url((string)$url,PHP_URL_SCHEME));
        if(in_array($scheme,array_intersect(wp_allowed_protocols(),['https','http']),true))$rel[]='nofollow';
        if(current_filter()==='comment_text')$rel[]='ugc';
        return $rel?' rel="'.esc_attr(implode(' ',$rel)).'"':'';
    }
}
if(!function_exists('_make_url_clickable_cb')){
    function _make_url_clickable_cb($matches) {
        $url=$matches[2];$suffix=$matches[3]??'';
        if($suffix===')'&&str_contains($url,'(')){ $url.=$suffix;$suffix=''; }
        while(substr_count($url,'(')<substr_count($url,')')){ $suffix=strrchr($url,')').$suffix;$url=substr($url,0,strrpos($url,')')); }
        $url=esc_url($url);if(empty($url))return $matches[0];
        return $matches[1].'<a href="'.$url.'"'._make_clickable_rel_attr($url).'>'.$url.'</a>'.$suffix;
    }
}
if(!function_exists('_make_web_ftp_clickable_cb')){
    function _make_web_ftp_clickable_cb($matches) {
        $ret='';$dest='http://'.$matches[2];$last=substr($dest,-1);
        if(in_array($last,['.',',',';',':',')'],true)){ $ret=$last;$dest=substr($dest,0,-1); }
        $dest=esc_url($dest);if(empty($dest))return $matches[0];
        return $matches[1].'<a href="'.$dest.'"'._make_clickable_rel_attr($dest).'>'.$dest.'</a>'.$ret;
    }
}
if(!function_exists('_make_email_clickable_cb')){ function _make_email_clickable_cb($matches) { $e=$matches[2].'@'.$matches[3];return $matches[1].'<a href="mailto:'.$e.'">'.$e.'</a>'; } }
if(!function_exists('_split_str_by_whitespace')){
    /** Text in Stücke von höchstens $goal Zeichen teilen, immer nach einem Leerraumzeichen. */
    function _split_str_by_whitespace($text, $goal) {
        $text=(string)$text;$goal=max(1,(int)$goal);$chunks=[];$ns=strtr($text,"\r\n\t\v\f ","\000\000\000\000\000\000");
        while(strlen($ns)>$goal){
            $pos=strrpos(substr($ns,0,$goal+1),"\000");
            if($pos===false){ $pos=strpos($ns,"\000",$goal+1);if($pos===false)break; }
            $chunks[]=substr($text,0,$pos+1);$text=substr($text,$pos+1);$ns=substr($ns,$pos+1);
        }
        if($text!=='')$chunks[]=$text;
        return $chunks;
    }
}
if(!function_exists('wp_rel_callback')){
    /** Fügt dem rel-Attribut eines <a>-Tags $rel hinzu (vorhandene Werte bleiben). $matches[1] = Attributtext. */
    function wp_rel_callback($matches, $rel) {
        $text=$matches[1];$atts=shortcode_parse_atts($text);
        if(!empty($atts['rel'])){
            $parts=array_map('trim',explode(' ',$atts['rel']));
            foreach(explode(' ',$rel) as $r)if(!in_array($r,$parts,true))$parts[]=$r;
            $rel=trim(implode(' ',$parts));unset($atts['rel']);$html='';
            foreach($atts as $name=>$value)$html.=is_int($name)?esc_attr($value).' ':$name.'="'.esc_attr($value).'" ';
            $text=trim($html);
        }
        return "<a $text rel=\"".esc_attr($rel).'">';
    }
}
if(!function_exists('wp_rel_nofollow_callback')){
    function wp_rel_nofollow_callback($matches) {
        $html=$matches[1];$atts=shortcode_parse_atts($html);
        if(!empty($atts['href'])&&in_array(strtolower((string)wp_parse_url($atts['href'],PHP_URL_SCHEME)),['http','https'],true)
            &&strtolower((string)wp_parse_url($atts['href'],PHP_URL_HOST))===strtolower((string)wp_parse_url(home_url(),PHP_URL_HOST)))return "<a $html>";   // eigene Adresse bleibt ohne nofollow
        return wp_rel_callback($matches,'nofollow');
    }
}
if(!function_exists('wp_rel_ugc')){ function wp_rel_ugc($text) { $text=stripslashes((string)$text);return wp_slash(preg_replace_callback('|<a (.+?)>|i',fn($m)=>wp_rel_callback($m,'nofollow ugc'),$text)); } }
if(!function_exists('wp_targeted_link_rel_callback')){
    /** Ergänzt bei target="_blank" das rel-Attribut um „noopener“ (Filter „wp_targeted_link_rel“). $matches[1] = Attributtext. */
    function wp_targeted_link_rel_callback($matches) {
        $orig=$matches[1];$html=$orig;
        if(!preg_match('/(^|[^\\\\])[\'"]/',$html))$html=preg_replace('/\\\\([\'"])/','$1',$html);   // maskierte Anführungszeichen
        $atts=shortcode_parse_atts($html);
        if(!is_array($atts)||($atts['target']??'')!=='_blank')return $matches[0];
        $new=array_filter(explode(' ',(string)apply_filters('wp_targeted_link_rel','noopener',$html)));
        $have=array_filter(explode(' ',(string)($atts['rel']??'')));$rel=trim(implode(' ',array_unique(array_merge($have,$new))));
        if(isset($atts['rel']))$out=preg_replace('/\brel\s*=\s*("[^"]*"|\'[^\']*\'|\S+)/i','rel="'.esc_attr($rel).'"',$html,1);
        else $out=rtrim($html).' rel="'.esc_attr($rel).'"';
        return '<a '.$out.'>';
    }
}
if(!function_exists('wp_init_targeted_link_rel_filters')){
    function wp_init_targeted_link_rel_filters() { foreach(['title_save_pre','content_save_pre','excerpt_save_pre','content_filtered_save_pre','pre_comment_content','pre_term_description','pre_link_description','pre_link_notes','pre_user_description'] as $f)add_filter($f,'wp_targeted_link_rel'); }
}
if(!function_exists('wp_remove_targeted_link_rel_filters')){
    function wp_remove_targeted_link_rel_filters() { foreach(['title_save_pre','content_save_pre','excerpt_save_pre','content_filtered_save_pre','pre_comment_content','pre_term_description','pre_link_description','pre_link_notes','pre_user_description'] as $f)remove_filter($f,'wp_targeted_link_rel'); }
}
if(!function_exists('elvado_ext_absolute_url')){
    /** Relative Adresse gegen eine Basisadresse auflösen (RFC-3986, vereinfacht). */
    function elvado_ext_absolute_url($rel, $base) {
        $rel=(string)$rel;if($rel===''||preg_match('#^[a-z][a-z0-9+.\-]*:#i',$rel))return $rel;
        $b=parse_url((string)$base);if(!$b||empty($b['host']))return $rel;
        $root=($b['scheme']??'http').'://'.$b['host'].(isset($b['port'])?':'.$b['port']:'');
        if(str_starts_with($rel,'//'))return ($b['scheme']??'http').':'.$rel;
        if($rel[0]==='/')return $root.$rel;
        if($rel[0]==='#'||$rel[0]==='?')return $root.($b['path']??'/').($rel[0]==='?'?'':(isset($b['query'])?'?'.$b['query']:'')).$rel;
        $dir=preg_replace('#/[^/]*$#','/',$b['path']??'/');$out=[];
        foreach(explode('/',$dir.$rel) as $seg){ if($seg==='..')array_pop($out);elseif($seg!=='.')$out[]=$seg; }
        $p=implode('/',$out);return $root.($p!==''&&$p[0]==='/'?'':'/').$p;
    }
}
if(!function_exists('_links_add_base')){
    function _links_add_base($m) {
        global $_links_add_base;
        return $m[1].'='.$m[2].((preg_match('#^(\w{1,20}):#',$m[3],$p)&&in_array($p[1],wp_allowed_protocols(),true))?$m[3]:elvado_ext_absolute_url($m[3],$_links_add_base)).$m[2];
    }
}
if(!function_exists('links_add_base_url')){
    function links_add_base_url($content, $base, $attrs=['src','href']) {
        global $_links_add_base;$_links_add_base=$base;$a=implode('|',array_map('preg_quote',(array)$attrs));
        return preg_replace_callback("!($a)=(['\"])(.+?)\\2!i",'_links_add_base',(string)$content);
    }
}
if(!function_exists('_links_add_target')){
    function _links_add_target($m) { global $_links_add_target;$link=preg_replace('|( target=([\'"])(.*?)\2)|i','',$m[1]);return '<a'.$link.' target="'.esc_attr($_links_add_target).'">'; }
}

/* ───────── Kses-Vorstufen ───────── */
if(!function_exists('wp_pre_kses_less_than_callback')){ function wp_pre_kses_less_than_callback($matches) { return str_contains($matches[0],'>')?$matches[0]:esc_html($matches[0]); } }
if(!function_exists('wp_pre_kses_block_attributes')){
    /** Entfernt HTML aus den JSON-Attributen von Block-Kommentaren (<!-- wp:x {"a":"<b>"} -->) mit wp_kses. */
    function wp_pre_kses_block_attributes($content, $allowed_html, $allowed_protocols) {
        return preg_replace_callback('/<!--\s+(wp:[a-z][a-z0-9_\-]*(?:\/[a-z][a-z0-9_\-]*)?)\s+(\{.*?\})\s*(\/?)-->/s',function($m) use($allowed_html,$allowed_protocols){
            $d=json_decode($m[2],true);if(!is_array($d))return $m[0];
            $clean=map_deep($d,fn($v)=>is_string($v)?wp_kses($v,$allowed_html,$allowed_protocols):$v);
            if($clean===$d)return $m[0];
            $j=str_replace(['--','<','>','&','\\"'],['\u002d\u002d','\u003c','\u003e','\u0026','\u0022'],wp_json_encode($clean,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
            return '<!-- '.$m[1].' '.$j.' '.$m[3].'-->';
        },(string)$content);
    }
}

/* ───────── Emoji (Browser stellen Emoji selbst dar – nur Platzhalter) ───────── */
if(!function_exists('wp_enqueue_emoji_styles')){
    function wp_enqueue_emoji_styles() {
        if(!wp_style_is('wp-emoji-styles','registered'))wp_register_style('wp-emoji-styles',false);
        wp_add_inline_style('wp-emoji-styles','img.wp-smiley, img.emoji { display: inline !important; border: none !important; box-shadow: none !important; height: 1em !important; width: 1em !important; margin: 0 0.07em !important; vertical-align: -0.1em !important; background: none !important; padding: 0 !important; }');
        wp_enqueue_style('wp-emoji-styles');
    }
}
if(!function_exists('print_emoji_detection_script')){ function print_emoji_detection_script() { /* Standardwert: keine Emoji-Erkennung nötig (kein Skript) */ } }
if(!function_exists('_print_emoji_detection_script')){ function _print_emoji_detection_script() { /* siehe print_emoji_detection_script */ } }
if(!function_exists('wp_staticize_emoji_for_email')){ function wp_staticize_emoji_for_email($mail) { return $mail; } }   // Mail-Programme zeigen Emoji selbst an
if(!function_exists('_wp_emoji_list')){
    /** Kleine Auswahl häufiger Emoji ('entities' = Zahlenentitäten, sonst Zeichen); die vollständige Liste liefert das Skript nicht. */
    function _wp_emoji_list($type='entities') {
        static $cp=[0x1F600,0x1F601,0x1F602,0x1F603,0x1F604,0x1F609,0x1F60A,0x1F60D,0x1F60E,0x1F62D,0x1F44D,0x1F44E,0x1F44B,0x1F64F,0x1F389,0x1F525,0x1F4A1,0x2764,0x2705,0x2728,0x2B50,0x1F680,0x1F31F,0x1F3B5];
        $r=array_map(fn($c)=>$type==='entities'?'&#x'.dechex($c).';':mb_chr($c,'UTF-8'),$cp);
        return apply_filters('wp_emoji_list',$r,$type);
    }
}

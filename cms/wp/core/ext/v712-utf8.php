<?php
// Ergänzende WordPress-Funktionen (Version 7.1, Teil UTF-8): Prüfen/Bereinigen von UTF-8, Nichtzeichen, Scan-Hilfen, _mb_chr/_mb_ord, Latin-1-Umwandlung.
// Eigenständig umgesetzt (preg/mb_); beim Laden entsteht nur die Definition, keine Ausgabe, keine DB.

if(!function_exists('_mb_chr')){ function _mb_chr($code) {   // Zeichen zu Codepunkt (UTF-8); ungültige Werte -> false
    $c=(int)$code;if($c<0||$c>0x10FFFF||($c>=0xD800&&$c<=0xDFFF))return false;
    if($c<0x80)return chr($c);if($c<0x800)return chr(0xC0|$c>>6).chr(0x80|$c&0x3F);
    if($c<0x10000)return chr(0xE0|$c>>12).chr(0x80|$c>>6&0x3F).chr(0x80|$c&0x3F);
    return chr(0xF0|$c>>18).chr(0x80|$c>>12&0x3F).chr(0x80|$c>>6&0x3F).chr(0x80|$c&0x3F);
} }
if(!function_exists('_mb_ord')){ function _mb_ord($string) {   // Codepunkt des ersten Zeichens; ungültig/leer -> false
    $s=(string)$string;if($s==='')return false;$b=ord($s[0]);
    if($b<0x80)return $b;
    if(!preg_match('/\A(?:[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})/',$s,$m))return false;
    $v=$b&($b>=0xF0?0x07:($b>=0xE0?0x0F:0x1F));
    for($i=1,$n=strlen($m[0]);$i<$n;$i++)$v=$v<<6|(ord($m[0][$i])&0x3F);
    return $v;
} }

/** Scan: zählt gültige Codepunkte ab $at, setzt $at ans Ende des gültigen Abschnitts und $invalid_length auf die Länge der folgenden ungültigen Folge (maximaler Teil). */
if(!function_exists('_wp_scan_utf8')){ function _wp_scan_utf8($bytes, &$at, &$invalid_length, $max_bytes=null, $max_code_points=null, &$has_noncharacters=null) {
    $bytes=(string)$bytes;$len=strlen($bytes);$at=max(0,(int)$at);$invalid_length=0;$has_noncharacters=(bool)$has_noncharacters;
    $end=$max_bytes===null?$len:min($len,$at+max(0,(int)$max_bytes));
    if($at>=$end)return 0;
    $chunk=($at===0&&$end===$len)?$bytes:substr($bytes,$at,$end-$at);
    preg_match('/\A(?:[\x00-\x7F]+|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})*+/',$chunk,$m);
    $span=$m[0];$count=strlen($span)-preg_match_all('/[\x80-\xBF]/',$span);
    if($max_code_points!==null&&$count>(int)$max_code_points){   // auf N Codepunkte kürzen
        $max=max(0,(int)$max_code_points);$cut=0;$seen=0;$n=strlen($span);
        while($seen<$max&&$cut<$n){ $cut++;while($cut<$n&&(ord($span[$cut])&0xC0)===0x80)$cut++;$seen++; }
        $span=substr($span,0,$cut);$count=$seen;
    }
    if(!$has_noncharacters)$has_noncharacters=wp_has_noncharacters($span);
    $at+=strlen($span);
    if($max_code_points!==null&&$count>=(int)$max_code_points)return $count;
    if($at<$end){   // ungültige Folge: Lead-Byte plus gültige Fortsetzungen (Unicode „maximal subpart“)
        $b=ord($bytes[$at]);$inv=1;
        if($b>=0xC2&&$b<=0xF4){
            $lo=0x80;$hi=0xBF;$need=$b>=0xF0?3:($b>=0xE0?2:1);
            if($b===0xE0)$lo=0xA0;elseif($b===0xED)$hi=0x9F;elseif($b===0xF0)$lo=0x90;elseif($b===0xF4)$hi=0x8F;
            for($k=1;$k<=$need;$k++){ $c=$at+$k<$len?ord($bytes[$at+$k]):-1;if($c<($k===1?$lo:0x80)||$c>($k===1?$hi:0xBF))break;$inv++; }
        }
        $invalid_length=$inv;
    }
    return $count;
} }
if(!function_exists('_wp_is_valid_utf8_fallback')){ function _wp_is_valid_utf8_fallback($bytes) {
    $bytes=(string)$bytes;$at=0;$inv=0;_wp_scan_utf8($bytes,$at,$inv);return $at>=strlen($bytes);
} }
if(!function_exists('_wp_scrub_utf8_fallback')){ function _wp_scrub_utf8_fallback($bytes) {   // ungültige Folgen -> U+FFFD
    $bytes=(string)$bytes;$len=strlen($bytes);$at=0;$out='';
    while($at<$len){ $start=$at;$inv=0;_wp_scan_utf8($bytes,$at,$inv);$out.=substr($bytes,$start,$at-$start);if($at>=$len)break;$out.="\u{FFFD}";$at+=max(1,$inv); }
    return $out;
} }
if(!function_exists('_wp_has_noncharacters_fallback')){ function _wp_has_noncharacters_fallback($text) {   // U+FDD0..FDEF und U+xFFFE/xFFFF
    return (bool)preg_match('/\xEF\xB7[\x90-\xAF]|\xEF\xBF[\xBE\xBF]|[\xF0-\xF4][\x8F\x9F\xAF\xBF]\xBF[\xBE\xBF]/',(string)$text);
} }
if(!function_exists('wp_is_valid_utf8')){ function wp_is_valid_utf8($bytes) {
    $bytes=(string)$bytes;
    if(function_exists('mb_check_encoding'))return mb_check_encoding($bytes,'UTF-8');
    return _wp_is_valid_utf8_fallback($bytes);
} }
if(!function_exists('wp_scrub_utf8')){ function wp_scrub_utf8($bytes) {
    $bytes=(string)$bytes;return wp_is_valid_utf8($bytes)?$bytes:_wp_scrub_utf8_fallback($bytes);
} }
if(!function_exists('wp_has_noncharacters')){ function wp_has_noncharacters($text) { return _wp_has_noncharacters_fallback($text); } }
if(!function_exists('_wp_utf8_codepoint_count')){ function _wp_utf8_codepoint_count($text, $starts_at=0, $length=null) {   // ungültige Folgen zählen als ein Zeichen
    $text=(string)$text;$at=max(0,(int)$starts_at);$end=$length===null?strlen($text):min(strlen($text),$at+(int)$length);$n=0;
    while($at<$end){ $inv=0;$n+=_wp_scan_utf8($text,$at,$inv,$end-$at);if($inv>0){ $n++;$at+=$inv; }else break; }
    return $n;
} }
if(!function_exists('_wp_utf8_codepoint_span')){ function _wp_utf8_codepoint_span($text, $byte_offset, $max_code_points, &$found_code_points=0) {   // Bytelänge des Abschnitts mit höchstens N gültigen Codepunkten
    $at=max(0,(int)$byte_offset);$start=$at;$inv=0;$found_code_points=_wp_scan_utf8((string)$text,$at,$inv,null,(int)$max_code_points);return $at-$start;
} }
if(!function_exists('_wp_utf8_encode_fallback')){ function _wp_utf8_encode_fallback($iso8859_1_text) {   // Latin-1 -> UTF-8
    return preg_replace_callback('/[\x80-\xFF]/',static fn($m)=>_mb_chr(ord($m[0])),(string)$iso8859_1_text);
} }
if(!function_exists('_wp_utf8_decode_fallback')){ function _wp_utf8_decode_fallback($utf8_text) {   // UTF-8 -> Latin-1, Nicht-Latin-1 und Ungültiges -> '?'
    $s=_wp_scrub_utf8_fallback((string)$utf8_text);
    return preg_replace_callback('/[\xC2-\xF4][\x80-\xBF]*/',static function($m){ $c=_mb_ord($m[0]);return $c!==false&&$c<=0xFF?chr($c):'?'; },$s);
} }

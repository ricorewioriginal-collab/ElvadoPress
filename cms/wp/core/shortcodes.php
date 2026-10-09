<?php
// WordPress-kompatible Shortcodes: [name attr="x"]Inhalt[/name], [name /], [[name]] (maskiert).
if(!isset($GLOBALS['shortcode_tags']))$GLOBALS['shortcode_tags']=[];

function add_shortcode($tag, $callback) {
    global $shortcode_tags;
    if(''===trim((string)$tag)||preg_match('@[<>&/\[\]\x00-\x20=]@',(string)$tag))return;
    $shortcode_tags[$tag]=$callback;
}
function remove_shortcode($tag) { global $shortcode_tags; unset($shortcode_tags[$tag]); }
function remove_all_shortcodes() { global $shortcode_tags; $shortcode_tags=[]; }
function shortcode_exists($tag) { global $shortcode_tags; return array_key_exists($tag,$shortcode_tags); }
function has_shortcode($content, $tag) {
    if(!str_contains((string)$content,'['))return false;
    if(shortcode_exists($tag)){
        preg_match_all('/'.get_shortcode_regex([$tag]).'/',(string)$content,$m,PREG_SET_ORDER);
        if(empty($m))return false;
        foreach($m as $s)if($tag===$s[2])return true;
        foreach($m as $s)if(!empty($s[5])&&has_shortcode($s[5],$tag))return true;
    }
    return false;
}
function get_shortcode_regex($tagnames=null) {
    global $shortcode_tags;
    if(empty($tagnames))$tagnames=array_keys($shortcode_tags);
    $tagregexp=implode('|',array_map('preg_quote',$tagnames));
    return '\\[(\\[?)('.$tagregexp.')(?![\\w-])([^\\]\\/]*(?:\\/(?!\\])[^\\]\\/]*)*?)(?:(\\/)\\]|\\](?:([^\\[]*+(?:\\[(?!\\/\\2\\])[^\\[]*+)*+)\\[\\/\\2\\])?)(\\]?)';
}
function do_shortcode($content, $ignore_html=false) {
    global $shortcode_tags;
    $content=(string)$content;
    if(!str_contains($content,'[')||empty($shortcode_tags))return $content;
    preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@',$content,$matches);
    $tagnames=array_intersect(array_keys($shortcode_tags),$matches[1]);
    if(empty($tagnames))return $content;
    $pattern=get_shortcode_regex($tagnames);
    $out=preg_replace_callback("/$pattern/",'do_shortcode_tag',$content);
    return $out===null?$content:$out;
}
function do_shortcode_tag($m) {
    global $shortcode_tags;
    if('['===$m[1]&&']'===$m[6])return substr($m[0],1,-1);   // [[tag]] → [tag]
    $tag=$m[2];$attr=shortcode_parse_atts($m[3]);
    if(!is_callable($shortcode_tags[$tag]??null)){return $m[0];}
    $content=$m[5]??null;
    $pre=apply_filters('pre_do_shortcode_tag',false,$tag,$attr,$m);
    if(false!==$pre)return $pre;
    try{ $output=$m[1].call_user_func($shortcode_tags[$tag],$attr,$content,$tag).$m[6]; }
    catch(Throwable $e){ elvado_wp_log('Shortcode ['.$tag.'] Fehler: '.$e->getMessage()); $output=''; }
    return apply_filters('do_shortcode_tag',$output,$tag,$attr,$m);
}
function shortcode_parse_atts($text) {
    $atts=[];
    $pattern='/([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|\'([^\']*)\'(?:\s|$)|(\S+)(?:\s|$)/';
    $text=preg_replace('/[\x{00a0}\x{200b}]+/u',' ',(string)$text);
    if(preg_match_all($pattern,$text,$match,PREG_SET_ORDER)){
        foreach($match as $m){
            if(!empty($m[1]))$atts[strtolower($m[1])]=stripcslashes($m[2]);
            elseif(!empty($m[3]))$atts[strtolower($m[3])]=stripcslashes($m[4]);
            elseif(!empty($m[5]))$atts[strtolower($m[5])]=stripcslashes($m[6]);
            elseif(isset($m[7])&&strlen($m[7]))$atts[]=stripcslashes($m[7]);
            elseif(isset($m[8])&&strlen($m[8]))$atts[]=stripcslashes($m[8]);
            elseif(isset($m[9]))$atts[]=stripcslashes($m[9]);
        }
        foreach($atts as &$value){
            if(str_contains((string)$value,'<')&&1!==preg_match('/^[^<]*+(?:<[^>]*+>[^<]*+)*+$/',(string)$value))$value='';
        }
        unset($value);
    } else $atts=ltrim($text);
    return $atts;
}
function shortcode_atts($pairs, $atts, $shortcode='') {
    $atts=(array)$atts;$out=[];
    foreach($pairs as $name=>$default)$out[$name]=array_key_exists($name,$atts)?$atts[$name]:$default;
    if($shortcode)$out=apply_filters("shortcode_atts_{$shortcode}",$out,$pairs,$atts,$shortcode);
    return $out;
}
function strip_shortcodes($content) {
    global $shortcode_tags;
    $content=(string)$content;
    if(!str_contains($content,'[')||empty($shortcode_tags))return $content;
    preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@',$content,$matches);
    $tagnames=array_intersect(array_keys($shortcode_tags),$matches[1]);
    if(empty($tagnames))return $content;
    $pattern=get_shortcode_regex($tagnames);
    return (string)preg_replace_callback("/$pattern/",function($m){ if('['===$m[1]&&']'===$m[6])return substr($m[0],1,-1); return $m[1].$m[6]; },$content);
}
function do_shortcodes_in_html_tags($content, $ignore_html, $tagnames) { return $content; }

<?php
// Ergänzende kses-Funktionen (Bereich Ausgabe): Bausteine zum Zerlegen und Prüfen von HTML-Attributen, Entitäten und Protokollen sowie CSS-Filter.
// wp_kses() des Kerns bleibt unverändert; diese Funktionen sind eigenständig nutzbar (Plugins rufen sie direkt auf).
// Hinweis: wp_kses_bad_protocol() des Kerns liefert bool; hier wird intern rrw_ext_kses_protocol() (String-Ergebnis) verwendet.

if(!function_exists('wp_kses_version')){ function wp_kses_version() { return '0.2.2'; } }
if(!function_exists('wp_kses_hook')){ function wp_kses_hook($content, $allowed_html, $allowed_protocols) { return apply_filters('pre_kses',$content,$allowed_html,$allowed_protocols); } }
if(!function_exists('wp_kses_uri_attributes')){
    function wp_kses_uri_attributes() {
        $u=['action','archive','background','cite','classid','codebase','data','formaction','href','icon','longdesc','manifest','poster','profile','src','usemap','xmlns'];
        return apply_filters('wp_kses_uri_attributes',$u);
    }
}
if(!function_exists('_wp_add_global_attributes')){
    function _wp_add_global_attributes($value) {
        $g=['aria-describedby'=>true,'aria-details'=>true,'aria-label'=>true,'aria-labelledby'=>true,'aria-hidden'=>true,'class'=>true,'id'=>true,'style'=>true,'title'=>true,'role'=>true,'data-*'=>true];
        if($value===true)$value=[];
        return is_array($value)?array_merge($value,$g):$value;
    }
}
if(!function_exists('wp_kses_stripslashes')){ function wp_kses_stripslashes($content) { return preg_replace('%\\\\"%','"',(string)$content); } }
if(!function_exists('wp_kses_array_lc')){
    function wp_kses_array_lc($inarray) {
        $out=[];foreach((array)$inarray as $k=>$v){ $ok=strtolower((string)$k);$out[$ok]=[];foreach((array)$v as $k2=>$v2)$out[$ok][strtolower((string)$k2)]=$v2; }
        return $out;
    }
}
if(!function_exists('wp_kses_html_error')){ function wp_kses_html_error($attr) { return preg_replace('/^("[^"]*("|$)|\'[^\']*(\'|$)|\S)*\s*/','',(string)$attr); } }

/* ───────── Entitäten ───────── */
if(!function_exists('valid_unicode')){
    function valid_unicode($i) { $i=(int)$i;return $i===9||$i===10||$i===13||($i>=32&&$i<=55295)||($i>=57344&&$i<=65533)||($i>=65536&&$i<=1114111); }
}
if(!function_exists('wp_kses_named_entities')){
    function wp_kses_named_entities($matches) {
        global $allowedentitynames;
        if(empty($matches[1]))return '';
        if(empty($allowedentitynames)){ $allowedentitynames=['apos'];foreach(get_html_translation_table(HTML_ENTITIES,ENT_QUOTES|ENT_HTML401,'UTF-8') as $e)if(preg_match('/^&([a-zA-Z0-9]+);$/',$e,$m))$allowedentitynames[]=$m[1]; }
        return in_array($matches[1],$allowedentitynames,true)?"&{$matches[1]};":"&amp;{$matches[1]};";
    }
}
if(!function_exists('wp_kses_xml_named_entities')){
    function wp_kses_xml_named_entities($matches) {
        if(empty($matches[1]))return '';
        return in_array($matches[1],['amp','lt','gt','apos','quot'],true)?"&{$matches[1]};":"&amp;{$matches[1]};";
    }
}
if(!function_exists('wp_kses_normalize_entities2')){
    function wp_kses_normalize_entities2($matches) {
        if(empty($matches[1]))return '';
        $i=$matches[1];
        return valid_unicode($i)?'&#'.str_pad(ltrim($i,'0'),3,'0',STR_PAD_LEFT).';':"&amp;#$i;";
    }
}
if(!function_exists('wp_kses_normalize_entities3')){
    function wp_kses_normalize_entities3($matches) {
        if(empty($matches[1]))return '';
        $h=$matches[1];return valid_unicode(hexdec($h))?'&#x'.ltrim($h,'0').';':"&amp;#x$h;";
    }
}
if(!function_exists('wp_kses_normalize_entities')){
    /** Alle „&“ maskieren, dann erlaubte benannte und gültige Zahlenentitäten wiederherstellen. */
    function wp_kses_normalize_entities($content, $context='html') {
        $content=str_replace('&','&amp;',(string)$content);
        $content=preg_replace_callback('/&amp;([A-Za-z]{2,8}[0-9]{0,2});/',$context==='xml'?'wp_kses_xml_named_entities':'wp_kses_named_entities',$content);
        $content=preg_replace_callback('/&amp;#(0*[0-9]{1,7});/','wp_kses_normalize_entities2',$content);
        return preg_replace_callback('/&amp;#[Xx](0*[0-9A-Fa-f]{1,6});/','wp_kses_normalize_entities3',$content);
    }
}
if(!function_exists('_wp_kses_decode_entities_chr')){ function _wp_kses_decode_entities_chr($match) { return chr((int)$match[1]); } }
if(!function_exists('_wp_kses_decode_entities_chr_hexdec')){ function _wp_kses_decode_entities_chr_hexdec($match) { return chr((int)hexdec($match[1])); } }
if(!function_exists('wp_kses_decode_entities')){
    function wp_kses_decode_entities($content) {
        $content=preg_replace_callback('/&#([0-9]+);/','_wp_kses_decode_entities_chr',(string)$content);
        return preg_replace_callback('/&#[Xx]([0-9A-Fa-f]+);/','_wp_kses_decode_entities_chr_hexdec',$content);
    }
}

/* ───────── Protokolle ───────── */
if(!function_exists('wp_kses_bad_protocol_once2')){
    /** Prüft ein Schema: „schema:“ wenn erlaubt, sonst leer. */
    function wp_kses_bad_protocol_once2($scheme, $allowed_protocols) {
        $s=strtolower(wp_kses_no_null(preg_replace('/\s/','',wp_kses_decode_entities((string)$scheme))));
        return in_array($s,array_map('strtolower',(array)$allowed_protocols),true)?"$s:":'';
    }
}
if(!function_exists('wp_kses_bad_protocol_once')){
    function wp_kses_bad_protocol_once($content, $allowed_protocols, $count=1) {
        $content=preg_replace('/\xad+/','',(string)$content);   // weiche Trennstriche
        $content=preg_replace('/(&#0*58(?![;0-9])|&#x0*3a(?![;a-f0-9]))/i','$1;',$content);
        $p=preg_split('/:|&#0*58;|&#x0*3a;|&colon;/i',$content,2);
        if(isset($p[1])&&!preg_match('%/\?%',$p[0])){
            $rest=trim($p[1]);$proto=wp_kses_bad_protocol_once2($p[0],$allowed_protocols);
            if($proto==='feed:'){ if($count>2)return '';$rest=wp_kses_bad_protocol_once($rest,$allowed_protocols,++$count);if(empty($rest))return $rest; }
            $content=$proto.$rest;
        }
        return $content;
    }
}
if(!function_exists('rrw_ext_kses_protocol')){
    /** Wie WordPress’ wp_kses_bad_protocol (liefert den bereinigten String); wiederholt, bis nichts mehr wegfällt. */
    function rrw_ext_kses_protocol($content, $allowed_protocols) {
        $content=wp_kses_no_null((string)$content);$n=0;
        do{ $orig=$content;$content=wp_kses_bad_protocol_once($content,$allowed_protocols); }while($orig!==$content&&++$n<6);
        return $orig!==$content?'':$content;
    }
}
if(!function_exists('_wp_kses_allow_pdf_objects')){
    /** Erlaubt <object>/<embed> auf PDF-Dateien im Upload-Verzeichnis (ohne Query/Fragment). */
    function _wp_kses_allow_pdf_objects($url) {
        $url=(string)$url;if(str_contains($url,'?')||str_contains($url,'#')||!str_ends_with($url,'.pdf'))return false;
        $u=wp_parse_url(wp_upload_dir(null,false)['url']??'');$host=($u['host']??'').(isset($u['port'])?':'.$u['port']:'');
        return $host!==''&&(str_starts_with($url,"http://$host/")||str_starts_with($url,"https://$host/"));
    }
}

/* ───────── Attribute ───────── */
if(!function_exists('wp_kses_check_attr_val')){
    function wp_kses_check_attr_val($value, $vless, $checkname, $checkvalue) {
        $ok=true;$value=(string)$value;
        switch(strtolower((string)$checkname)){
            case 'maxlen': if(strlen($value)>$checkvalue)$ok=false;break;
            case 'minlen': if(strlen($value)<$checkvalue)$ok=false;break;
            case 'maxval': if(!preg_match('/^\s{0,6}[0-9]{1,6}\s{0,6}$/',$value)||$value>$checkvalue)$ok=false;break;
            case 'minval': if(!preg_match('/^\s{0,6}[0-9]{1,6}\s{0,6}$/',$value)||$value<$checkvalue)$ok=false;break;
            case 'valueless': if(strtolower((string)$checkvalue)!==$vless)$ok=false;break;
            case 'values': if(!in_array(strtolower($value),array_map('strtolower',(array)$checkvalue),true))$ok=false;break;
            case 'value_callback': if(!call_user_func($checkvalue,$value))$ok=false;break;
        }
        return $ok;
    }
}
if(!function_exists('wp_kses_attr_check')){
    /** Prüft ein Attribut gegen die Erlaubnisliste des Elements; bei Ablehnung werden $name/$value/$whole geleert. */
    function wp_kses_attr_check(&$name, &$value, &$whole, &$vless, $element, $allowed_html) {
        $allowed=$allowed_html[strtolower((string)$element)]??[];if($allowed===true)$allowed=[];
        $low=strtolower((string)$name);
        if(!isset($allowed[$low])||$allowed[$low]===''){
            $ok=false;
            foreach($allowed as $k=>$v){ if(!is_string($k)||!str_ends_with($k,'*'))continue; if(str_starts_with($low,substr($k,0,-1))){ $allowed[$low]=$v;$ok=true;break; } }
            if(!$ok){ $name=$value=$whole='';return false; }
        }
        if($low==='style'){
            $new=safecss_filter_attr((string)$value);
            if($new===''){ $name=$value=$whole='';return false; }
            $whole=str_replace($value,$new,$whole);$value=$new;
        }
        if(is_array($allowed[$low])){
            foreach($allowed[$low] as $k=>$v)if(!wp_kses_check_attr_val($value,$vless,$k,$v)){ $name=$value=$whole='';return false; }
        }
        return true;
    }
}
if(!function_exists('wp_kses_hair_parse')){
    /** Zerlegt einen Attributtext in einzelne Attribute (jeweils mit führendem Leerraum); false bei unlesbarem Rest. */
    function wp_kses_hair_parse($attr) {
        $attr=(string)$attr;if(trim($attr)==='')return [];
        $re='~\G\s*[^\s"\'>/=]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'>]*))?~';$out=[];$pos=0;
        while(preg_match($re,$attr,$m,0,$pos)){ $out[]=$m[0];$pos+=strlen($m[0]); }
        return trim(substr($attr,$pos))===''?$out:false;
    }
}
if(!function_exists('wp_kses_attr_parse')){
    /** Zerlegt ein ganzes Element (<a href="x">) in [Anfang, Attribut, …, Ende]; Schluss-Tags bleiben unverändert (String). */
    function wp_kses_attr_parse($element) {
        if(!preg_match('%^(<\s*)(/\s*)?([a-zA-Z0-9]+\s*)([^>]*)(>?)$%',(string)$element,$m))return false;
        [, $begin,$slash,$name,$attr,$end]=$m;
        if($slash!=='')return $element;
        $x='';if(preg_match('%\s*/\s*$%',$attr,$s)){ $x=$s[0];$attr=substr($attr,0,-strlen($x)); }
        $arr=wp_kses_hair_parse($attr);if($arr===false)return false;
        array_unshift($arr,$begin.$slash.$name);$arr[]=$x.$end;
        return $arr;
    }
}
if(!function_exists('wp_kses_one_attr')){
    /** Bereinigt ein einzelnes Attribut („href=x“) für das Element $element nach den „post“-Regeln; leer, wenn nicht erlaubt. */
    function wp_kses_one_attr($attr, $element) {
        $uris=wp_kses_uri_attributes();$allowed_html=wp_kses_allowed_html('post');$protocols=wp_allowed_protocols();
        $attr=wp_kses_no_null((string)$attr);
        preg_match('/^\s*/',$attr,$m);$lead=$m[0];preg_match('/\s*$/',$attr,$m);$trail=$m[0];
        $attr=$trail===''?substr($attr,strlen($lead)):substr($attr,strlen($lead),-strlen($trail));
        $split=preg_split('/\s*=\s*/',$attr,2);$name=$split[0];
        if(count($split)===2){
            $value=$split[1];$quote=$value===''?'':$value[0];
            if($quote==='"'||$quote==="'"){ if(!str_ends_with($value,$quote)||strlen($value)<2)return '';$value=substr($value,1,-1); } else $quote='"';
            $value=esc_attr($value);
            if(in_array(strtolower($name),$uris,true))$value=rrw_ext_kses_protocol($value,$protocols);
            $attr="$name=$quote$value$quote";$vless='n';
        } else { $value='';$vless='y'; }
        wp_kses_attr_check($name,$value,$attr,$vless,$element,$allowed_html);
        return $lead.$attr.$trail;
    }
}
if(!function_exists('wp_kses_hair')){
    /** Liest Attribute eines Tags: Liste je Name mit name/value/whole/vless; URL-Attribute werden auf erlaubte Protokolle geprüft, doppelte Namen ignoriert. */
    function wp_kses_hair($attr, $allowed_protocols) {
        $uris=wp_kses_uri_attributes();$arr=[];
        foreach((array)wp_kses_hair_parse($attr) as $raw){
            $raw=ltrim($raw);
            if(!preg_match('~^([^\s"\'>/=]+)(?:\s*=\s*("[^"]*"|\'[^\']*\'|[^\s"\'>]*))?$~',$raw,$m))continue;
            $n=$m[1];if(isset($arr[$n]))continue;
            if(!isset($m[2])){ $arr[$n]=['name'=>$n,'value'=>'','whole'=>$n,'vless'=>'y'];continue; }
            $v=$m[2];$q='"';
            if(strlen($v)>=2&&($v[0]==='"'||$v[0]==="'")&&str_ends_with($v,$v[0])){ $q=$v[0];$v=substr($v,1,-1); }
            if(in_array(strtolower($n),$uris,true))$v=rrw_ext_kses_protocol($v,$allowed_protocols);
            $arr[$n]=['name'=>$n,'value'=>$v,'whole'=>"$n=$q$v$q",'vless'=>'n'];
        }
        return $arr;
    }
}
if(!function_exists('wp_kses_attr')){
    /** Bereinigt die Attribute eines Start-Tags und gibt das fertige Tag zurück (<a href="…">). */
    function wp_kses_attr($element, $attr, $allowed_html, $allowed_protocols) {
        if(!is_array($allowed_html))$allowed_html=wp_kses_allowed_html($allowed_html);
        $x=preg_match('%\s*/\s*$%',(string)$attr)?' /':'';$low=strtolower((string)$element);
        if(empty($allowed_html[$low])||$allowed_html[$low]===true)return "<$element$x>";
        $arr=wp_kses_hair($attr,$allowed_protocols);
        foreach($allowed_html[$low] as $k=>$v)if(is_array($v)&&($v['required']??false)===true&&!isset($arr[strtolower((string)$k)]))return '';   // Pflichtattribut fehlt
        $out='';
        foreach($arr as $a)if(wp_kses_attr_check($a['name'],$a['value'],$a['whole'],$a['vless'],$element,$allowed_html))$out.=' '.$a['whole'];
        return "<$element".preg_replace('/[<>]/','',$out)."$x>";
    }
}

/* ───────── Zerlegen ───────── */
if(!function_exists('wp_kses_split2')){
    function wp_kses_split2($content, $allowed_html, $allowed_protocols) {
        $content=wp_kses_stripslashes($content);
        if(!str_starts_with($content,'<'))return '&gt;';
        if(str_starts_with($content,'<!--')){
            $content=str_replace(['<!--','-->'],'',$content);
            while(($n=wp_kses($content,$allowed_html,$allowed_protocols))!=$content)$content=$n;
            if($content==='')return '';
            return '<!--'.preg_replace('/-$/','',preg_replace('/--+/','-',$content)).'-->';
        }
        if(!preg_match('%^<\s*(/\s*)?([a-zA-Z0-9-]+)([^>]*)>?$%',$content,$m))return '';
        [, $slash,$elem,$attrs]=$m;
        if(!is_array($allowed_html))$allowed_html=wp_kses_allowed_html($allowed_html);
        if(!isset($allowed_html[strtolower($elem)]))return '';
        if(trim($slash)!=='')return "</$elem>";
        return wp_kses_attr($elem,$attrs,$allowed_html,$allowed_protocols);
    }
}
if(!function_exists('_wp_kses_split_callback')){
    function _wp_kses_split_callback($matches) { global $pass_allowed_html,$pass_allowed_protocols;return wp_kses_split2($matches[0],$pass_allowed_html,$pass_allowed_protocols); }
}
if(!function_exists('wp_kses_split')){
    function wp_kses_split($content, $allowed_html, $allowed_protocols) {
        global $pass_allowed_html,$pass_allowed_protocols;$pass_allowed_html=$allowed_html;$pass_allowed_protocols=$allowed_protocols;
        return preg_replace_callback('%(<!--.*?(-->|$))|(<[^>]*(>|$)|>)%','_wp_kses_split_callback',(string)$content);
    }
}

/* ───────── CSS ───────── */
if(!function_exists('safecss_filter_attr')){
    /** Lässt aus einem style-Attribut nur erlaubte Eigenschaften mit unbedenklichen Werten durch (url() nur mit erlaubtem Protokoll). */
    function safecss_filter_attr($css, $deprecated='') {
        $css=str_replace(["\n","\r","\t"],'',wp_kses_no_null((string)$css));
        $protocols=wp_allowed_protocols();
        $allowed=apply_filters('safe_style_css',['background','background-color','background-image','background-position','background-repeat','background-size','background-attachment','background-blend-mode','border','border-radius','border-width','border-color','border-style','border-collapse','border-spacing',
            'border-right','border-right-color','border-right-style','border-right-width','border-bottom','border-bottom-color','border-bottom-left-radius','border-bottom-right-radius','border-bottom-style','border-bottom-width','border-left','border-left-color','border-left-style','border-left-width',
            'border-top','border-top-color','border-top-left-radius','border-top-right-radius','border-top-style','border-top-width','caption-side','columns','column-count','column-fill','column-gap','column-rule','column-span','column-width','color','filter','font','font-family','font-size','font-style','font-variant','font-weight',
            'letter-spacing','line-height','text-align','text-decoration','text-indent','text-transform','text-shadow','white-space','height','min-height','max-height','width','min-width','max-width','margin','margin-top','margin-right','margin-bottom','margin-left','margin-block-start','margin-block-end','margin-inline-start','margin-inline-end',
            'padding','padding-top','padding-right','padding-bottom','padding-left','padding-block-start','padding-block-end','padding-inline-start','padding-inline-end','flex','flex-basis','flex-direction','flex-flow','flex-grow','flex-shrink','flex-wrap','gap','row-gap','grid-template-columns','grid-auto-columns','grid-column-start','grid-column-end','grid-template-rows','grid-auto-rows','grid-row-start','grid-row-end',
            'justify-content','justify-items','justify-self','align-content','align-items','align-self','order','clear','cursor','direction','float','list-style-type','object-fit','object-position','overflow','vertical-align','writing-mode','position','top','right','bottom','left','z-index','box-shadow','aspect-ratio','--*']);
        if(empty($allowed))return '';
        $url_types=['background','background-image','cursor','list-style','list-style-image'];$grad_types=['background','background-image'];
        $items=[];$buf='';$depth=0;$q='';   // an „;“ außerhalb von Klammern/Anführungszeichen trennen
        foreach(str_split(trim($css)) as $ch){
            if($q!==''){ if($ch===$q)$q=''; } elseif($ch==='"'||$ch==="'")$q=$ch; elseif($ch==='(')$depth++; elseif($ch===')')$depth=max(0,$depth-1);
            if($ch===';'&&$depth===0&&$q===''){ $items[]=$buf;$buf=''; } else $buf.=$ch;
        }
        $items[]=$buf;$out='';
        foreach($items as $item){
            $item=trim($item);if($item==='')continue;
            $test=$item;$found=false;$url_attr=false;$grad_attr=false;$parts=[];
            if(!str_contains($item,':'))$found=true;
            else{
                $parts=explode(':',$item,2);$sel=trim($parts[0]);$custom=in_array('--*',$allowed,true)&&preg_match('/^--[a-zA-Z0-9_-]+$/',$sel);
                if($custom||in_array($sel,$allowed,true)){ $found=true;$url_attr=in_array($sel,$url_types,true);$grad_attr=in_array($sel,$grad_types,true); }
                if($custom){ $val=trim($parts[1]);$url_attr=str_starts_with($val,'url(');$grad_attr=str_contains($val,'-gradient('); }
            }
            if($found&&$url_attr){
                preg_match_all('/url\([^)]+\)/',$parts[1],$um);
                foreach($um[0] as $u){
                    preg_match('/url\(\s*([\'"]?)(.*?)\1\s*\)/',$u,$p);$url=trim($p[2]??'');
                    if($url===''||rrw_ext_kses_protocol($url,$protocols)!==$url){ $found=false;break; }
                    $test=str_replace($u,'',$test);
                }
            }
            if($found&&$grad_attr){ $val=trim($parts[1]);if(preg_match('/^(repeating-)?(linear|radial|conic)-gradient\(([^()]|rgb[a]?\([^()]*\))*\)$/',$val))$test=str_replace($val,'',$test); }
            if($found){
                $test=preg_replace('/\b(?:calc|min|max|clamp)\((?:[^()]|\([^()]*\))*\)/','',$test);   // calc() u. ä. (sind unbedenklich)
                $test=preg_replace('/\bvar\(\s*--[a-zA-Z0-9_-]+\s*(?:,\s*[^()]*)?\)/','',$test);
                $ok=!preg_match('%[\\\\(&=}]|/\*%',$test);
                $ok=(bool)apply_filters('safecss_filter_attr_allow_css',$ok,$test);
                if($ok)$out.=($out!==''?';':'').$item;
            }
        }
        return $out;
    }
}
if(!function_exists('wp_filter_global_styles_post')){
    /** Filtert ein Global-Styles-JSON (Datenbank-Fassung): unsichere Zeichenketten in „styles“ werden entfernt. */
    function wp_filter_global_styles_post($data) {
        $d=json_decode(wp_unslash((string)$data),true);
        if(json_last_error()!==JSON_ERROR_NONE||!is_array($d)||empty($d['isGlobalStylesUserThemeJSON']))return $data;
        unset($d['isGlobalStylesUserThemeJSON']);
        if(method_exists('WP_Theme_JSON','remove_insecure_properties'))$d=WP_Theme_JSON::remove_insecure_properties($d);
        elseif(isset($d['styles'])){
            $f=function($n) use(&$f){ $o=[];foreach($n as $k=>$v){ if(is_array($v)){ $o[$k]=$f($v);continue; } if(!is_string($v)||safecss_filter_attr('color:'.$v)!=='')$o[$k]=$v; }return $o; };
            $d['styles']=$f($d['styles']);
        }
        $d['isGlobalStylesUserThemeJSON']=true;
        return wp_slash(wp_json_encode($d));
    }
}

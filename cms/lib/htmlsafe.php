<?php
declare(strict_types=1);
// cms/lib/htmlsafe.php – HTML-Bereinigung für Beiträge, Seiten, Widgets und Rechtstexte (DOM-basiert, Positivliste).
//
// Erlaubt ist alles, was ein Block-Editor erzeugt: Überschriften h1–h6, Listen, Zitate, Tabellen, Bilder, Figuren, Code/pre, Details,
// Video/Audio, eingebettete Player bekannter Anbieter (iframe nur von festen Hosts, nur https) sowie Block-Kommentare des Editors
// (<!-- ep:paragraph {…} --> … <!-- /ep:paragraph -->; ebenso wp:…, damit WordPress-Inhalte ihre Struktur behalten).
// Alles Aktive fliegt raus: script, style, form-Elemente, object/embed, svg/math, Ereignis-Attribute (on…), javascript:-/data:-Adressen, srcdoc,
// gefährliche CSS-Angaben (url(), expression(), @import, behavior …). style="…" wird auf eine Liste harmloser Eigenschaften reduziert.
// Raw-HTML-Blöcke (<!-- ep:html --> … <!-- /ep:html -->) bleiben nur dann unverändert, wenn $unfiltered gesetzt ist – das gilt nur für
// Administratoren (wie „unfiltered_html“ in WordPress) und nie in der Demo; sonst wird auch ihr Inhalt bereinigt.

const RRW_HTML_MAX=400000;
/** Erlaubte Tags (alles andere wird entpackt – der Text bleibt – oder, bei gefährlichen Tags, samt Inhalt entfernt). */
const RRW_HTML_TAGS=['a','abbr','address','article','aside','audio','b','bdi','blockquote','br','caption','cite','code','col','colgroup','dd','del','details','dfn','div','dl','dt','em','figcaption','figure','footer','h1','h2','h3','h4','h5','h6','header','hr','i','iframe','img','ins','kbd','li','main','mark','nav','ol','p','picture','pre','q','s','samp','section','small','source','span','strong','sub','summary','sup','table','tbody','td','tfoot','th','thead','time','tr','track','u','ul','var','video','wbr'];
/** Tags, die samt Inhalt verschwinden. */
const RRW_HTML_DROP=['script','style','object','embed','applet','form','input','button','textarea','select','option','optgroup','link','meta','base','svg','math','template','frame','frameset','noscript','canvas','dialog','head','title','xml','param'];
/** Attribute je Tag (zusätzlich zu den globalen). */
const RRW_HTML_ATTRS=[
    'a'=>['href','target','rel','hreflang','download'],'img'=>['src','alt','width','height','loading','decoding'],'td'=>['colspan','rowspan','headers'],'th'=>['colspan','rowspan','scope','headers','abbr'],
    'col'=>['span'],'colgroup'=>['span'],'ol'=>['start','reversed','type'],'li'=>['value'],'time'=>['datetime'],'q'=>['cite'],'blockquote'=>['cite'],'del'=>['cite','datetime'],'ins'=>['cite','datetime'],
    'video'=>['src','poster','controls','loop','muted','preload','width','height','playsinline'],'audio'=>['src','controls','loop','muted','preload'],'source'=>['src','type','media'],
    'track'=>['src','kind','srclang','label','default'],'iframe'=>['src','width','height','allow','allowfullscreen','loading','title','frameborder','referrerpolicy'],'details'=>['open'],'abbr'=>[],
];
const RRW_HTML_GLOBAL_ATTRS=['class','id','title','lang','dir','style','role','tabindex'];
/** Hosts, deren Player als iframe eingebettet werden dürfen (https). */
const RRW_HTML_IFRAME_HOSTS=['www.youtube.com','youtube.com','www.youtube-nocookie.com','player.vimeo.com','open.spotify.com','w.soundcloud.com','embed.music.apple.com','embed.podcasts.apple.com','bandcamp.com','player.twitch.tv','www.mixcloud.com','www.tiktok.com','www.instagram.com','www.openstreetmap.org','maps.google.com','www.google.com','www.facebook.com','player.rss.com','widget.mixcloud.com'];
/** Erlaubte CSS-Eigenschaften in style="…". */
const RRW_HTML_CSS=['color','background-color','background','text-align','font-size','font-weight','font-style','font-family','text-decoration','text-transform','line-height','letter-spacing','margin','margin-top','margin-bottom','margin-left','margin-right','padding','padding-top','padding-bottom','padding-left','padding-right','border','border-top','border-bottom','border-left','border-right','border-color','border-width','border-style','border-radius','width','max-width','min-width','height','max-height','min-height','display','gap','row-gap','column-gap','flex','flex-direction','flex-wrap','flex-basis','flex-grow','flex-shrink','justify-content','align-items','align-self','grid-template-columns','float','clear','opacity','object-fit','aspect-ratio','list-style','list-style-type','vertical-align','white-space','overflow','box-shadow','text-shadow'];

/** Adresse prüfen: http(s), mailto, tel, #anker, relative Pfade; für Bilder zusätzlich kleine data:image/…;base64. Leer = nicht erlaubt. */
function rrw_html_url(string $v,bool $allowData=false): string {
    $v=trim($v);if($v===''||strlen($v)>4000)return '';
    $probe=preg_replace('/[\x00-\x20\x7f]+/','',$v);   // Tricks wie "java\nscript:"
    if(preg_match('~^data:image/(png|jpe?g|gif|webp);base64,[A-Za-z0-9+/=]+$~i',$probe))return ($allowData&&strlen($probe)<2_800_000)?$probe:'';
    if(preg_match('~^[a-z][a-z0-9+.\-]*:~i',$probe))return preg_match('~^(https?:|mailto:|tel:)~i',$probe)?$v:'';
    if(str_starts_with($probe,'//'))return '';
    return $v;   // relativ oder #anker
}
/** style="…" auf harmlose Angaben reduzieren. */
function rrw_html_style(string $css): string {
    $out=[];
    foreach(explode(';',$css) as $decl){
        if(!str_contains($decl,':'))continue;[$p,$val]=array_map('trim',explode(':',$decl,2));$p=strtolower($p);
        if(!in_array($p,RRW_HTML_CSS,true)||$val===''||strlen($val)>200)continue;
        if(preg_match('~url\s*\(|expression|@import|behavior|javascript|vbscript|[<>\\\\{}]|/\*|\*/~i',$val))continue;
        if(!preg_match('~^[#a-zA-Z0-9%.,()\s\-+/"\'!:]*$~',$val))continue;
        $out[]=$p.':'.$val;
    }
    return implode(';',$out);
}
function rrw_html_comment_ok(string $c): bool {
    return (bool)preg_match('~^\s*/?(?:ep|wp):[a-z0-9][a-z0-9_/\-]*(?:\s+\{[^<>]{0,4000}\})?\s*$~i',$c);
}
function rrw_html_clean_attrs(DOMElement $el): void {
    $tag=strtolower($el->tagName);$allowed=array_merge(RRW_HTML_GLOBAL_ATTRS,RRW_HTML_ATTRS[$tag]??[]);
    $remove=[];
    foreach($el->attributes as $a){
        $n=strtolower($a->nodeName);$v=(string)$a->nodeValue;
        $ok=in_array($n,$allowed,true)||(bool)preg_match('/^(aria-[a-z\-]+|data-[a-z0-9\-]+)$/',$n);
        if(!$ok||str_starts_with($n,'on')){ $remove[]=$a->nodeName;continue; }
        if(strlen($v)>4000){ $remove[]=$a->nodeName;continue; }
        if($n==='style'){ $s=rrw_html_style($v);if($s==='')$remove[]=$a->nodeName;else $el->setAttribute('style',$s);continue; }
        if(in_array($n,['href','src','poster','cite'],true)){
            $u=rrw_html_url($v,$tag==='img'&&$n==='src');
            if($u==='')$remove[]=$a->nodeName;else $el->setAttribute($a->nodeName,$u);
            continue;
        }
        if($n==='target'&&!in_array($v,['_blank','_self'],true))$remove[]=$a->nodeName;
        if($n==='id'||$n==='class'){ if(!preg_match('/^[A-Za-z0-9_\-: .]*$/',$v))$remove[]=$a->nodeName; }
        if($n==='allow'){ $el->setAttribute('allow',implode('; ',array_values(array_intersect(array_map('trim',explode(';',$v)),['accelerometer','autoplay','clipboard-write','encrypted-media','gyroscope','picture-in-picture','web-share','fullscreen'])))); }
    }
    foreach(array_unique($remove) as $r)$el->removeAttribute($r);
    if($tag==='a'&&strtolower((string)$el->getAttribute('target'))==='_blank'){ $el->setAttribute('rel','noopener noreferrer'); }
    if($tag==='img'&&!$el->hasAttribute('alt'))$el->setAttribute('alt','');
}
function rrw_html_iframe_ok(DOMElement $el): bool {
    $src=trim((string)$el->getAttribute('src'));if($src==='')return false;
    $p=@parse_url($src);if(!is_array($p)||strtolower((string)($p['scheme']??''))!=='https')return false;
    $host=strtolower((string)($p['host']??''));if(!in_array($host,RRW_HTML_IFRAME_HOSTS,true))return false;
    $path=(string)($p['path']??'');
    if($host==='www.google.com'&&!str_starts_with($path,'/maps/embed'))return false;
    if($host==='www.facebook.com'&&!str_starts_with($path,'/plugins/'))return false;
    if($host==='www.instagram.com'&&!str_contains($path,'/embed'))return false;
    if($host==='www.tiktok.com'&&!str_starts_with($path,'/embed'))return false;
    if(($host==='www.youtube.com'||$host==='youtube.com')&&!str_starts_with($path,'/embed/'))return false;
    return true;
}
function rrw_html_walk(DOMNode $parent): void {
    $kids=[];foreach($parent->childNodes as $c)$kids[]=$c;
    foreach($kids as $n){
        if($n instanceof DOMText||$n instanceof DOMCdataSection){ continue; }
        if($n instanceof DOMComment){
            $cv=(string)$n->nodeValue;
            if(!empty($GLOBALS['rrw_html_keep_raw'])&&preg_match('/^epraw-\d+$/',$cv))continue;   // Platzhalter eines Raw-Blocks (nur bei ungefiltert)
            if(!rrw_html_comment_ok($cv))$parent->removeChild($n);continue;
        }
        if(!($n instanceof DOMElement)){ $parent->removeChild($n);continue; }
        $tag=strtolower($n->tagName);
        if(in_array($tag,RRW_HTML_DROP,true)){ $parent->removeChild($n);continue; }
        if($tag==='iframe'&&!rrw_html_iframe_ok($n)){ $parent->removeChild($n);continue; }
        if(!in_array($tag,RRW_HTML_TAGS,true)){   // unbekannt: Inhalt behalten, Hülle entfernen
            rrw_html_walk($n);
            while($n->firstChild)$parent->insertBefore($n->firstChild,$n);
            $parent->removeChild($n);continue;
        }
        rrw_html_clean_attrs($n);
        if($tag==='img'&&!$n->hasAttribute('src')){ $parent->removeChild($n);continue; }   // Bild ohne erlaubte Quelle
        if($tag==='iframe'){ $n->setAttribute('loading','lazy');$n->setAttribute('referrerpolicy','strict-origin-when-cross-origin'); }
        rrw_html_walk($n);
    }
}
/**
 * Hauptfunktion. $unfiltered: Raw-HTML-Blöcke unverändert lassen (nur Administratoren, nie in der Demo); null = aus der Anfrage ($GLOBALS['rrw_html_unfiltered']).
 */
function rrw_html_sanitize(string $html,?bool $unfiltered=null,int $max=RRW_HTML_MAX): string {
    if($html==='')return '';
    if($unfiltered===null)$unfiltered=!empty($GLOBALS['rrw_html_unfiltered'])&&!defined('RRW_DEMO');
    if(strlen($html)>$max)$html=substr($html,0,$max);
    if(!class_exists('DOMDocument'))return rrw_html_sanitize_basic($html);
    $raw=[];
    if($unfiltered){   // Raw-HTML-Blöcke vor der Verarbeitung herausnehmen
        $html=preg_replace_callback('~<!--\s*(ep|wp):html(\s+\{[^<>]{0,4000}\})?\s*-->(.*?)<!--\s*/\1:html\s*-->~is',function(array $m) use(&$raw): string {
            $raw[]=$m[0];return '<!--epraw-'.(count($raw)-1).'-->';
        },$html);
    }
    $prev=libxml_use_internal_errors(true);
    $doc=new DOMDocument('1.0','UTF-8');
    $doc->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div id="rrw-root">'.$html.'</div></body></html>',LIBXML_NOERROR|LIBXML_NOWARNING|LIBXML_NONET);
    libxml_clear_errors();libxml_use_internal_errors($prev);
    $root=$doc->getElementById('rrw-root');
    if(!$root){ $xp=new DOMXPath($doc);$q=$xp->query('//div[@id="rrw-root"]');$root=$q&&$q->length?$q->item(0):null; }
    if(!$root)return rrw_html_sanitize_basic($html);
    $GLOBALS['rrw_html_keep_raw']=$raw!==[];
    rrw_html_walk($root);
    $GLOBALS['rrw_html_keep_raw']=false;
    $out='';foreach($root->childNodes as $c)$out.=$doc->saveHTML($c);
    if($raw)$out=preg_replace_callback('~<!--epraw-(\d+)-->~',fn($m)=>$raw[(int)$m[1]]??'',$out);
    return $out;
}
/** Rückfall ohne DOM-Erweiterung: strenge Positivliste ohne Kommentare, Tabellen und Embeds. */
function rrw_html_sanitize_basic(string $html): string {
    $html=preg_replace('#<(script|object|embed|form|input|button|textarea|select|style|iframe|svg|math)[^>]*>.*?</\1>#is','',$html);
    $html=strip_tags($html,'<p><br><strong><b><em><i><u><s><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><a><span><div><hr><small><code><pre><img><figure><figcaption>');
    $html=preg_replace_callback('/<[a-z][^>]*>/i',function(array $m): string {
        $t=$m[0];for($i=0;$i<3;$i++)$t=preg_replace('/[\s\/]on[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]*)/i','',$t);return $t;
    },$html);
    return preg_replace('/(javascript|vbscript)\s*:/i','',$html);
}

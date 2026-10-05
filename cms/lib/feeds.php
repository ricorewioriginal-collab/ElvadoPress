<?php
declare(strict_types=1);

// SSRF-sicheres Abrufen und Parsen externer RSS/Atom-Feeds für das
// News-Magazin (feed_sources) und den öffentlichen RSS-Export.

function rrw_remote_feed_allowed(string $url): bool {
    $p=@parse_url($url);
    if(!is_array($p)||!in_array(strtolower((string)($p['scheme']??'')),['http','https'],true))return false;
    $host=strtolower((string)($p['host']??''));if($host===''||$host==='localhost'||str_ends_with($host,'.local'))return false;
    if(filter_var($host,FILTER_VALIDATE_IP)){
        return (bool)filter_var($host,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE);
    }
    $ip=@gethostbyname($host);
    if($ip&&$ip!==$host&&!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))return false;
    return true;
}
// Kennung beim Abruf: mit RicoReWi-Paket wie bisher, sonst neutral mit dem Produktnamen.
function rrw_feed_user_agent(bool $long=true): string {
    if(!function_exists('rrw_pack_available'))require_once __DIR__.'/pack.php';
    if(rrw_pack_available())return $long?'RicoReWi-Radio-Magazin/1.0 (+https://www.ricorewi-radio.de/)':'RicoReWi-Radio-Magazin/1.0';
    if(!function_exists('rrw_product'))require_once __DIR__.'/product.php';
    $n=(string)(rrw_product()['name']??'');$n=preg_replace('/[^A-Za-z0-9.-]/','',$n)?:'CMS';
    return $n.'-Feeds/1.0';
}
function rrw_fetch_url(string $url,int $timeout=7): string {
    if(!rrw_remote_feed_allowed($url))return '';
    if(function_exists('curl_init')){
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>5,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>$timeout,CURLOPT_USERAGENT=>rrw_feed_user_agent(),CURLOPT_SSL_VERIFYPEER=>true]);
        $raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$final=(string)curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);curl_close($ch);
        if($raw===false||$code<200||$code>=400||!rrw_remote_feed_allowed($final))return '';
        return (string)$raw;
    }
    $ua=rrw_feed_user_agent(false);$ctx=stream_context_create(['http'=>['timeout'=>$timeout,'user_agent'=>$ua],'https'=>['timeout'=>$timeout,'user_agent'=>$ua]]);
    return (string)(@file_get_contents($url,false,$ctx)?:'');
}
function rrw_feed_clean_text(string $s,int $max=1200): string {
    $s=html_entity_decode(strip_tags($s),ENT_QUOTES|ENT_HTML5,'UTF-8');
    $s=preg_replace('/\s+/u',' ',trim($s));return mb_substr((string)$s,0,$max);
}
function rrw_external_feed_articles(array $site): array {
    if(!class_exists('SimpleXMLElement'))return [];
    $out=[];
    foreach((array)($site['feed_sources']??[]) as $source){
        if(empty($source['enabled']))continue;
        $url=trim((string)($source['url']??''));if($url===''||!rrw_remote_feed_allowed($url))continue;
        $raw=rrw_fetch_url($url,8);if($raw==='')continue;
        libxml_use_internal_errors(true);
        $xml=simplexml_load_string($raw,'SimpleXMLElement',LIBXML_NOCDATA|LIBXML_NONET);libxml_clear_errors();
        if(!$xml)continue;
        $items=[];$isAtom=false;
        if(isset($xml->channel->item))$items=$xml->channel->item;
        elseif(isset($xml->entry)){$items=$xml->entry;$isAtom=true;}
        $max=max(1,min(25,(int)($source['max_items']??5)));$count=0;
        foreach($items as $item){
            if($count++ >= $max)break;
            $title=trim((string)($item->title??''));if($title==='')continue;
            $link='';
            if($isAtom){
                foreach($item->link as $ln){$attrs=$ln->attributes();$rel=(string)($attrs['rel']??'alternate');if($rel===''||$rel==='alternate'){$link=(string)($attrs['href']??'');if($link!=='')break;}}
                if($link==='')$link=trim((string)($item->id??''));
            } else $link=trim((string)($item->link??''));
            if($link!==''&&!filter_var($link,FILTER_VALIDATE_URL))$link='';
            $desc=$isAtom?(string)($item->summary??$item->content??''):(string)($item->description??'');
            $published=$isAtom?(string)($item->updated??$item->published??''):(string)($item->pubDate??$item->{'dc:date'}??'');
            $ts=$published!==''?strtotime($published):false;$date=$ts?date('Y-m-d H:i:s',$ts):date('Y-m-d H:i:s');
            $guid=$isAtom?(string)($item->id??$link??$title):(string)($item->guid??$link??$title);
            $key=(string)($source['id']??$source['name']??$url).'|'.$guid;
            $id=800000000+(abs((int)crc32($key))%199999999);
            $image='';
            if(!$isAtom&&isset($item->enclosure)){$a=$item->enclosure->attributes();$type=(string)($a['type']??'');if(str_starts_with($type,'image/'))$image=(string)($a['url']??'');}
            if($image===''&&preg_match('#<img[^>]+src=["\']([^"\']+)#i',$desc,$m))$image=$m[1];
            $excerpt=rrw_feed_clean_text($desc,600);
            $sourceName=trim((string)($source['name']??'Externe Quelle'));
            $out[]=[
                'id'=>$id,'slug'=>'extern-'.rrw_slug($title).'-'.substr(md5($key),0,6),'title'=>mb_substr($title,0,255),
                'category'=>mb_substr(trim((string)($source['category']??'Extern')),0,80),'excerpt'=>$excerpt,'image_url'=>mb_substr($image,0,1200),
                'image_mode'=>$image!==''?'thumbnail':'none','external_url'=>$link,'video_url'=>'','tags'=>'','embed_html'=>'','status'=>'published','featured'=>0,
                'published_at'=>$date,'body_html'=>$excerpt!==''?'<p>'.htmlspecialchars($excerpt,ENT_QUOTES,'UTF-8').'</p>':'',
                'author'=>$sourceName,'external_source'=>$sourceName,'external_feed_url'=>$url,'is_external'=>true,'created_at'=>$date,'updated_at'=>$date
            ];
        }
    }
    return $out;
}

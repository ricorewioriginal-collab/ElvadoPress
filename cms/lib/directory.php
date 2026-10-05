<?php
declare(strict_types=1);
// Radioverzeichnis (für Marken/Apps mit Verzeichnis-Funktion): Suche in der öffentlichen laut.fm-API und in der Community-Datenbank radio-browser.info.
//
// Rechtlicher Rahmen (bewusst konservativ):
//  - laut.fm (AGB § 6 Abs. 9): Audiostreams dürfen nicht auf Webseiten Dritter eingebunden werden, Verlinkungen sind erlaubt,
//    wenn sie die Seite verlassen bzw. laut.fm in einem neuen Fenster öffnen. Fremde laut.fm-Sender werden daher nur mit
//    Beschreibung gelistet und zum Anhören auf laut.fm verlinkt (kein Player, keine Stream-URL im Ergebnis).
//  - radio-browser.info: Daten sind gemeinfrei (Namen, Tags, Links), die API darf frei und auch kommerziell genutzt werden
//    (sprechender User-Agent, Serverliste per DNS/JSON). Gelistet werden nur Name, Land, Sprache, Tags und die Webseite des
//    Betreibers als Link – keine Stream-URLs, keine Logos/Favicons (Rechte der Betreiber), keine Wiedergabe in unserem Player.
//  - Nur eigene Sender (Kernnetzwerk laut dem CMS) spielen im Portal.
require_once __DIR__.'/pack.php';
// Kennung gegenüber laut.fm/radio-browser.info: mit dem RicoReWi-Paket wie bisher, sonst neutral mit der eigenen Adresse
define('RRW_DIR_UA',rrw_pack_available()?'SenderWelt-Radioverzeichnis/1.0 (+https://senderwelt.de)':'Radioverzeichnis-CMS/1.0 (+'.(rrw_default_canonical_base()?:'https://localhost').')');

function rrw_dir_dir(string $dataDir): string { $d=$dataDir.'/.directory';if(!is_dir($d))@mkdir($d,0775,true);return $d; }

function rrw_dir_get(string $url,int $timeout=6): ?string {
    if(!function_exists('curl_init'))return null;
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>3,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>$timeout,CURLOPT_USERAGENT=>RRW_DIR_UA,CURLOPT_HTTPHEADER=>['Accept: application/json']]);
    $raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    return $raw!==false&&$code>=200&&$code<300&&trim((string)$raw)!==''?(string)$raw:null;
}
// Mehrere Abrufe parallel (Detail-Daten der Treffer)
function rrw_dir_multi(array $urls,int $timeout=6): array {
    $out=[];if(!$urls)return $out;
    // Hosting kann curl_multi_* sperren -> sequenziell abrufen
    if(!function_exists('curl_multi_init')||!function_exists('curl_multi_exec')||!function_exists('curl_multi_select')||!defined('CURLM_OK')){
        foreach($urls as $key=>$url)$out[$key]=rrw_dir_get($url,$timeout);
        return $out;
    }
    $mh=curl_multi_init();$handles=[];
    foreach($urls as $key=>$url){
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>3,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>$timeout,CURLOPT_USERAGENT=>RRW_DIR_UA,CURLOPT_HTTPHEADER=>['Accept: application/json']]);
        curl_multi_add_handle($mh,$ch);$handles[$key]=$ch;
    }
    do{$st=curl_multi_exec($mh,$running);if($running)curl_multi_select($mh,1.0);}while($running&&$st===CURLM_OK);
    foreach($handles as $key=>$ch){$raw=curl_multi_getcontent($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$out[$key]=$code>=200&&$code<300&&$raw!==''&&$raw!==null?(string)$raw:null;curl_multi_remove_handle($mh,$ch);curl_close($ch);}
    curl_multi_close($mh);return $out;
}
function rrw_dir_cached(string $dataDir,string $key,int $ttl,callable $make){
    $f=rrw_dir_dir($dataDir).'/c_'.preg_replace('/[^a-z0-9_-]/i','_',$key).'.json';
    if(is_file($f)&&filemtime($f)>=time()-$ttl){$d=json_decode((string)@file_get_contents($f),true);if(is_array($d)&&array_key_exists('v',$d))return $d['v'];}
    $v=$make();
    if($v!==null)@file_put_contents($f,json_encode(['v'=>$v],JSON_UNESCAPED_UNICODE));
    elseif(is_file($f)){$d=json_decode((string)@file_get_contents($f),true);if(is_array($d)&&array_key_exists('v',$d))return $d['v'];} // veralteter Stand besser als nichts
    if(random_int(1,200)===1)foreach((array)glob(rrw_dir_dir($dataDir).'/c_*.json') as $old)if(@filemtime($old)<time()-86400)@unlink($old);
    return $v;
}
// Nur frische Cache-Einträge (kein Rückgriff auf veraltete Daten)
function rrw_dir_fresh(string $dataDir,string $key,int $ttl){
    $f=rrw_dir_dir($dataDir).'/c_'.preg_replace('/[^a-z0-9_-]/i','_',$key).'.json';
    if(!is_file($f)||filemtime($f)<time()-$ttl)return null;$d=json_decode((string)@file_get_contents($f),true);return is_array($d)&&array_key_exists('v',$d)?$d['v']:null;
}
function rrw_dir_norm(string $s): string { return mb_strtolower(trim(preg_replace('/[\s_\-\.]+/u',' ',$s))); }

// ---------------------------------------------------------------- laut.fm
function rrw_dir_laut_names(string $dataDir): array {
    $n=rrw_dir_cached($dataDir,'laut_names',3600,function(){$b=rrw_dir_get('https://api.laut.fm/station_names',10);$d=$b?json_decode($b,true):null;return is_array($d)&&count($d)>100?array_values(array_filter($d,'is_string')):null;});
    return is_array($n)?$n:[];
}
function rrw_dir_laut_item(array $d,array $own): ?array {
    $id=(string)($d['name']??'');if($id===''||($d['active']??true)===false)return null;
    $img=(array)($d['images']??[]);
    return ['source'=>'laut','id'=>$id,'name'=>trim((string)($d['display_name']??''))?:$id,
        'description'=>mb_substr(trim(preg_replace('/\s+/u',' ',strip_tags((string)($d['description']??'')))),0,200),
        'genres'=>array_slice(array_values(array_filter((array)($d['genres']??[]),'is_string')),0,4),
        'cover'=>(string)($img['station_120x120']??$img['station']??''),
        'link'=>(string)($d['page_url']??('https://laut.fm/'.rawurlencode($id))),'country'=>trim((string)($d['location']??'')),'own'=>in_array(strtolower($id),$own,true)];
}
function rrw_dir_laut_details(array $ids,array $own,string $dataDir): array {
    $items=[];$need=[];
    foreach($ids as $id){
        $c=rrw_dir_fresh($dataDir,'lautd_'.$id,900);
        if(is_array($c))$items[$id]=$c;else $need['https://api.laut.fm/station/'.rawurlencode($id)]=$id;
    }
    if($need){
        foreach(rrw_dir_multi(array_flip($need),7) as $id=>$body){
            $d=$body?json_decode($body,true):null;$it=is_array($d)?rrw_dir_laut_item($d,$own):null;
            if($it){$items[$id]=$it;@file_put_contents(rrw_dir_dir($dataDir).'/c_lautd_'.preg_replace('/[^a-z0-9_-]/i','_',$id).'.json',json_encode(['v'=>$it],JSON_UNESCAPED_UNICODE));}
        }
    }
    $out=[];foreach($ids as $id)if(isset($items[$id]))$out[]=$items[$id];return $out;
}
function rrw_dir_laut_search(string $q,int $offset,int $limit,array $own,string $dataDir): array {
    $nq=rrw_dir_norm($q);$tokens=array_values(array_filter(explode(' ',$nq)));if(!$tokens)return ['items'=>[],'total'=>0];
    $hits=[];
    foreach(rrw_dir_laut_names($dataDir) as $name){
        $n=rrw_dir_norm($name);$ok=true;foreach($tokens as $t)if(!str_contains($n,$t)){$ok=false;break;}
        if(!$ok)continue;
        $score=$n===$nq?0:(str_starts_with($n,$nq)?1:(preg_match('/(^| )'.preg_quote($tokens[0],'/').'/u',$n)?2:3));
        if(in_array(strtolower($name),$own,true))$score-=0.5;
        $hits[]=[$score,mb_strlen($n),$name];
    }
    usort($hits,fn($a,$b)=>[$a[0],$a[1],$a[2]]<=>[$b[0],$b[1],$b[2]]);
    $total=count($hits);$page=array_slice($hits,$offset,$limit);
    return ['items'=>rrw_dir_laut_details(array_column($page,2),$own,$dataDir),'total'=>$total];
}
function rrw_dir_genres(string $dataDir): array {
    $g=rrw_dir_cached($dataDir,'laut_genres',28800,function(){
        $b=rrw_dir_get('https://api.laut.fm/genres',8);$d=$b?json_decode($b,true):null;if(!is_array($d))return null;
        usort($d,fn($a,$b)=>($b['score']??0)<=>($a['score']??0));$out=[];foreach(array_slice($d,0,28) as $r)if(is_array($r)&&!empty($r['name']))$out[]=(string)$r['name'];return $out;
    });
    return is_array($g)?$g:[];
}

// ---------------------------------------------------------------- radio-browser.info (nur Metadaten + Link zur Betreiber-Webseite)
function rrw_dir_rb_servers(string $dataDir): array {
    $s=rrw_dir_cached($dataDir,'rb_servers',86400,function(){
        $b=rrw_dir_get('https://all.api.radio-browser.info/json/servers',5);$d=$b?json_decode($b,true):null;if(!is_array($d))return null;
        $n=[];foreach($d as $r)if(!empty($r['name'])&&preg_match('/^[a-z0-9.-]+\.radio-browser\.info$/i',(string)$r['name']))$n[(string)$r['name']]=true;return array_keys($n)?:null;
    });
    $list=is_array($s)&&$s?$s:['de1.api.radio-browser.info','de2.api.radio-browser.info'];shuffle($list);return array_slice($list,0,3);
}
function rrw_dir_safe_url(string $u): string {
    $u=trim($u);if(!preg_match('~^https?://[^\s<>"\']+$~i',$u))return '';
    $host=(string)parse_url($u,PHP_URL_HOST);return $host!==''&&str_contains($host,'.')?$u:'';
}
function rrw_dir_world_search(string $q,int $offset,int $limit,string $dataDir): array {
    $key='rb_'.sha1(mb_strtolower($q).'|'.$offset.'|'.$limit);
    $list=rrw_dir_cached($dataDir,$key,900,function() use($q,$offset,$limit,$dataDir){
        $path='/json/stations/search?name='.rawurlencode($q).'&limit='.($limit*2).'&offset='.$offset.'&hidebroken=true&order=clickcount&reverse=true';
        foreach(rrw_dir_rb_servers($dataDir) as $srv){
            $b=rrw_dir_get('https://'.$srv.$path,7);$d=$b?json_decode($b,true):null;if(!is_array($d))continue;
            $out=[];$seen=[];
            foreach($d as $r){
                if(!is_array($r))continue;$name=trim((string)($r['name']??''));$home=rrw_dir_safe_url((string)($r['homepage']??''));
                if($name===''||$home==='')continue;
                if(stripos($home,'laut.fm')!==false||stripos((string)($r['url_resolved']??''),'laut.fm')!==false)continue; // laut.fm läuft über die laut.fm-Suche
                $k=mb_strtolower($name).'|'.parse_url($home,PHP_URL_HOST);if(isset($seen[$k]))continue;$seen[$k]=true;
                $tags=array_slice(array_values(array_filter(array_map('trim',explode(',',(string)($r['tags']??''))),fn($t)=>$t!==''&&mb_strlen($t)<=24)),0,4);
                $out[]=['source'=>'world','id'=>(string)($r['stationuuid']??''),'name'=>mb_substr($name,0,80),'description'=>'','genres'=>$tags,'cover'=>rrw_dir_https_img((string)($r['favicon']??'')),'bitrate'=>(int)($r['bitrate']??0),'codec'=>mb_substr(trim((string)($r['codec']??'')),0,10),'link'=>$home,'stream'=>rrw_dir_stream_url((string)($r['url_resolved']??($r['url']??''))),'stream_try'=>rrw_dir_stream_try((string)($r['url_resolved']??($r['url']??''))),
                    'country'=>trim((string)($r['country']??'')),'language'=>trim((string)($r['language']??'')),'own'=>false];
                if(count($out)>=$limit)break;
            }
            return $out;
        }
        return null;
    });
    return is_array($list)?$list:[];
}


// Nur https-Streams werden im Portal abgespielt (http würde als Mischinhalt blockiert); Wiedergabe direkt vom Betreiber, keine Weiterleitung über uns
function rrw_dir_stream_url(string $u): string {
    $u=rrw_dir_safe_url($u);return ($u!==''&&stripos($u,'https://')===0&&!preg_match('~\.(m3u8?|pls|asx)(\?|$)~i',$u))?$u:'';
}
// Bilder nur über https (kein Mischinhalt)
function rrw_dir_https_img(string $u): string {
    $u=rrw_dir_safe_url($u);return ($u!==''&&stripos($u,'https://')===0&&mb_strlen($u)<400)?$u:'';
}
// Nur http-Stream bekannt: https-Variante desselben Pfads als Versuch (der Browser prüft, ob sie antwortet)
function rrw_dir_stream_try(string $u): string {
    $u=rrw_dir_safe_url($u);if($u===''||stripos($u,'http://')!==0||preg_match('~\.(m3u8?|pls|asx)(\?|$)~i',$u))return '';
    return 'https://'.substr($u,7);
}
// Darf die Webseite eines Senders im Vollbild eingebettet werden? (X-Frame-Options / CSP frame-ancestors, 24 h Cache)
function rrw_dir_public_ip(string $host): ?string {
    if(filter_var($host,FILTER_VALIDATE_IP))$ips=[$host];else $ips=(array)@gethostbynamel($host);
    if(!$ips)return null;
    foreach($ips as $ip)if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))return null;
    return $ips[0];
}
function rrw_dir_frame_headers(string $url): ?array {
    for($i=0;$i<4;$i++){
        $u=rrw_dir_safe_url($url);if($u===''||stripos($u,'https://')!==0)return null;
        $host=(string)parse_url($u,PHP_URL_HOST);$port=(int)(parse_url($u,PHP_URL_PORT)?:443);
        $ip=rrw_dir_public_ip($host);if($ip===null)return null;
        $hdr=[];$ch=curl_init($u);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>6,CURLOPT_USERAGENT=>'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Mobile Safari/537.36',CURLOPT_HTTPHEADER=>['Accept: text/html,application/xhtml+xml','Accept-Language: de,en;q=0.8'],
            CURLOPT_RESOLVE=>[$host.':'.$port.':'.$ip],CURLOPT_HEADERFUNCTION=>function($c,$l)use(&$hdr){$p=explode(':',$l,2);if(count($p)===2)$hdr[strtolower(trim($p[0]))][]=trim($p[1]);return strlen($l);},
            CURLOPT_WRITEFUNCTION=>fn($c,$d)=>0]);
        curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if($code>=300&&$code<400&&!empty($hdr['location'][0])){
            $loc=$hdr['location'][0];
            if(!preg_match('~^https?://~i',$loc))$loc='https://'.$host.($loc[0]==='/'?'':'/').$loc;
            $url=$loc;continue;
        }
        if($code<200||$code>=400)return null;
        $hdr['_url']=[$u];return $hdr;
    }
    return null;
}
function rrw_dir_embeddable(string $url,string $dataDir): array {
    $u=rrw_dir_safe_url($url);if($u==='')return ['ok'=>false,'url'=>''];
    if(stripos($u,'http://')===0)$u='https://'.substr($u,7); // viele http-Adressen leiten ohnehin auf https
    $host=strtolower((string)parse_url($u,PHP_URL_HOST));
    $r=rrw_dir_cached($dataDir,'emb2_'.sha1($u),86400,function() use($u){
        $h=rrw_dir_frame_headers($u);
        if($h===null){ // manche Hosts antworten nur mit/ohne www
            $p=parse_url($u);$hh=(string)($p['host']??'');
            $alt=stripos($hh,'www.')===0?substr($hh,4):'www.'.$hh;
            $h=rrw_dir_frame_headers('https://'.$alt.(string)($p['path']??'/').(isset($p['query'])?'?'.$p['query']:''));
        }
        if($h===null)return ['ok'=>false,'url'=>''];
        $eff=(string)($h['_url'][0]??$u);
        foreach((array)($h['x-frame-options']??[]) as $v)if(preg_match('~deny|sameorigin|allow-from~i',$v))return ['ok'=>false,'url'=>''];
        foreach((array)($h['content-security-policy']??[]) as $v){
            if(preg_match('~frame-ancestors\s+([^;]*)~i',$v,$m)){$a=trim($m[1]);if(!preg_match('~(^|\s)\*(\s|$)~',$a))return ['ok'=>false,'url'=>''];}
        }
        return ['ok'=>true,'url'=>$eff];
    });
    return is_array($r)?$r:['ok'=>false,'url'=>''];
}


// Stream-Metadaten (ICY): Titel des laufenden Songs. Es wird nur ein kurzer Anfang des Streams gelesen (bis zum ersten Metadatenblock), nichts gespeichert.
function rrw_dir_icy(string $url): string {
    for($i=0;$i<3;$i++){
        $u=rrw_dir_safe_url($url);if($u===''||stripos($u,'https://')!==0)return '';
        $host=(string)parse_url($u,PHP_URL_HOST);$port=(int)(parse_url($u,PHP_URL_PORT)?:443);
        $ip=rrw_dir_public_ip($host);if($ip===null)return '';
        $hdr=[];$buf='';$metaint=0;
        $ch=curl_init($u);
        curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>8,CURLOPT_USERAGENT=>RRW_DIR_UA,CURLOPT_HTTPHEADER=>['Icy-MetaData: 1'],
            CURLOPT_RESOLVE=>[$host.':'.$port.':'.$ip],
            CURLOPT_HEADERFUNCTION=>function($c,$l)use(&$hdr){$p=explode(':',$l,2);if(count($p)===2)$hdr[strtolower(trim($p[0]))][]=trim($p[1]);return strlen($l);},
            CURLOPT_WRITEFUNCTION=>function($c,$d)use(&$buf,&$hdr,&$metaint){
                if($metaint===0){$metaint=(int)($hdr['icy-metaint'][0]??-1);if($metaint<=0||$metaint>32768)return 0;}
                $buf.=$d;
                if(strlen($buf)>$metaint){$need=$metaint+1+ord($buf[$metaint])*16;if(strlen($buf)>=$need)return 0;}
                return strlen($d);
            }]);
        curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if($code>=300&&$code<400&&!empty($hdr['location'][0])){$url=$hdr['location'][0];continue;}
        if($metaint<=0||strlen($buf)<=$metaint)return '';
        $len=ord($buf[$metaint])*16;if($len===0)return '';
        $meta=substr($buf,$metaint+1,$len);
        if(!preg_match("~StreamTitle='(.*?)';~s",$meta,$m))return '';
        $t=trim($m[1]);if(!mb_check_encoding($t,'UTF-8'))$t=mb_convert_encoding($t,'UTF-8','ISO-8859-1');
        $t=trim(strip_tags($t));
        $t=in_array(strtolower($t),['','undefined','unknown','-'],true)?'':mb_substr($t,0,120);
        $img='';if(preg_match("~StreamUrl='(.*?)';~s",$meta,$m2)&&preg_match('~\.(jpe?g|png|webp|gif)(\?|$)~i',$m2[1]))$img=rrw_dir_https_img(trim($m2[1]));
        return $t===''?'':json_encode(['t'=>$t,'i'=>$img],JSON_UNESCAPED_UNICODE);
    }
    return '';
}
function rrw_dir_stream_title(string $url,string $dataDir): array {
    $u=rrw_dir_safe_url($url);if($u===''||stripos($u,'https://')!==0)return ['t'=>'','i'=>''];
    $r=rrw_dir_cached($dataDir,'icy2_'.sha1($u),20,function() use($u){$j=rrw_dir_icy($u);$d=$j!==''?json_decode($j,true):null;return is_array($d)?$d:['t'=>'','i'=>''];});
    return is_array($r)?['t'=>(string)($r['t']??''),'i'=>(string)($r['i']??'')]:['t'=>'','i'=>''];
}
// Vorschau einer Sender-Webseite (Titel, Beschreibung, Bild aus den Meta-Angaben im Head), 24 h Cache
function rrw_dir_preview(string $url,string $dataDir): array {
    $u=rrw_dir_safe_url($url);if($u==='')return [];
    if(stripos($u,'http://')===0)$u='https://'.substr($u,7);
    $r=rrw_dir_cached($dataDir,'prev_'.sha1($u),86400,function() use($u){
        for($i=0;$i<4;$i++){
            $uu=rrw_dir_safe_url($u);if($uu===''||stripos($uu,'https://')!==0)return [];
            $host=(string)parse_url($uu,PHP_URL_HOST);$port=(int)(parse_url($uu,PHP_URL_PORT)?:443);
            $ip=rrw_dir_public_ip($host);if($ip===null)return [];
            $hdr=[];$buf='';
            $ch=curl_init($uu);
            curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>7,CURLOPT_USERAGENT=>'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Mobile Safari/537.36',
                CURLOPT_HTTPHEADER=>['Accept: text/html','Accept-Language: de,en;q=0.8'],CURLOPT_RESOLVE=>[$host.':'.$port.':'.$ip],
                CURLOPT_HEADERFUNCTION=>function($c,$l)use(&$hdr){$p=explode(':',$l,2);if(count($p)===2)$hdr[strtolower(trim($p[0]))][]=trim($p[1]);return strlen($l);},
                CURLOPT_WRITEFUNCTION=>function($c,$d)use(&$buf){$buf.=$d;return strlen($buf)>=120000?0:strlen($d);}]);
            curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
            if($code>=300&&$code<400&&!empty($hdr['location'][0])){$loc=$hdr['location'][0];$u=preg_match('~^https?://~i',$loc)?$loc:'https://'.$host.($loc[0]==='/'?'':'/').$loc;continue;}
            if($code<200||$code>=400||$buf==='')return [];
            $head=substr($buf,0,100000);
            $meta=function(array $keys) use($head){foreach($keys as $k){
                $q=preg_quote($k,'~');
                if(preg_match('~<meta[^>]+(?:property|name)=["\']'.$q.'["\'][^>]*content=(["\'])(.*?)\1~is',$head,$m)||preg_match('~<meta[^>]+content=(["\'])(.*?)\1[^>]+(?:property|name)=["\']'.$q.'["\']~is',$head,$m))return trim(html_entity_decode($m[2],ENT_QUOTES|ENT_HTML5,'UTF-8'));}return '';};
            $title=$meta(['og:title','twitter:title']);if($title===''&&preg_match('~<title[^>]*>(.*?)</title>~is',$head,$m))$title=trim(html_entity_decode(strip_tags($m[1]),ENT_QUOTES|ENT_HTML5,'UTF-8'));
            $desc=$meta(['og:description','description','twitter:description']);
            $img=$meta(['og:image','twitter:image']);
            if($img!==''&&!preg_match('~^https?://~i',$img))$img=($img[0]==='/'?'https://'.$host.$img:'');
            $cl=function($t,$n){$t=trim(preg_replace('/\s+/u',' ',strip_tags((string)$t)));return mb_substr($t,0,$n);};
            if(!mb_check_encoding($title.$desc,'UTF-8')){$title=mb_convert_encoding($title,'UTF-8','ISO-8859-1');$desc=mb_convert_encoding($desc,'UTF-8','ISO-8859-1');}
            return ['title'=>$cl($title,100),'desc'=>$cl($desc,280),'image'=>rrw_dir_https_img($img)];
        }
        return [];
    });
    return is_array($r)?$r:[];
}
// Weltradio nach Genre-Tag und/oder Land (für den KI-Assistenten)
function rrw_dir_world_by_tag(string $tag,string $cc,int $limit,string $dataDir): array {
    $key='rbt_'.sha1(mb_strtolower($tag).'|'.$cc.'|'.$limit);
    $list=rrw_dir_cached($dataDir,$key,900,function() use($tag,$cc,$limit,$dataDir){
        $path='/json/stations/search?hidebroken=true&order=clickcount&reverse=true&limit='.($limit*3).($tag!==''?'&tag='.rawurlencode($tag):'').($cc!==''?'&countrycode='.rawurlencode($cc):'');
        foreach(rrw_dir_rb_servers($dataDir) as $srv){
            $b=rrw_dir_get('https://'.$srv.$path,7);$d=$b?json_decode($b,true):null;if(!is_array($d))continue;
            $out=[];$seen=[];
            foreach($d as $r){
                if(!is_array($r))continue;$name=trim((string)($r['name']??''));$home=rrw_dir_safe_url((string)($r['homepage']??''));
                $raw=(string)($r['url_resolved']??($r['url']??''));
                if($name===''||$home===''||stripos($home,'laut.fm')!==false||stripos($raw,'laut.fm')!==false)continue;
                $k=mb_strtolower($name);if(isset($seen[$k]))continue;$seen[$k]=true;
                $tags=array_slice(array_values(array_filter(array_map('trim',explode(',',(string)($r['tags']??''))),fn($t)=>$t!==''&&mb_strlen($t)<=24)),0,4);
                $out[]=['source'=>'world','id'=>(string)($r['stationuuid']??''),'name'=>mb_substr($name,0,80),'description'=>'','genres'=>$tags,'cover'=>rrw_dir_https_img((string)($r['favicon']??'')),
                    'bitrate'=>(int)($r['bitrate']??0),'codec'=>mb_substr(trim((string)($r['codec']??'')),0,10),'link'=>$home,'stream'=>rrw_dir_stream_url($raw),'stream_try'=>rrw_dir_stream_try($raw),
                    'country'=>trim((string)($r['country']??'')),'language'=>trim((string)($r['language']??'')),'own'=>false];
                if(count($out)>=$limit)break;
            }
            return $out;
        }
        return null;
    });
    return is_array($list)?$list:[];
}
// Zufällige Sender für "Überrasch mich" und den Tab "World Radio" (laut.fm und/oder radio-browser.info)
function rrw_dir_random(string $kind,int $n,array $own,string $dataDir): array {
    $n=max(1,min(12,$n));$out=[];
    $wantLaut=$kind==='laut'?$n:($kind==='world'?0:(int)ceil($n/2));$wantWorld=$n-$wantLaut;
    if($wantLaut>0){
        $names=rrw_dir_laut_names($dataDir);
        if($names){
            $own_l=array_map('strtolower',$own);$pick=[];$tries=0;
            while(count($pick)<$wantLaut*2&&$tries++<80){$c=$names[random_int(0,count($names)-1)];if(!in_array(strtolower($c),$own_l,true)&&!in_array($c,$pick,true))$pick[]=$c;}
            $det=rrw_dir_laut_details($pick,$own,$dataDir);
            foreach($det as $it){if(!empty($it['own']))continue;$out[]=$it;if(count($out)>=$wantLaut)break;}
        }
    }
    if($wantWorld>0){
        $got=0;
        foreach(rrw_dir_rb_servers($dataDir) as $srv){
            $b=rrw_dir_get('https://'.$srv.'/json/stations/search?order=random&limit='.($wantWorld*6).'&hidebroken=true&has_extended_info=false',7);$d=$b?json_decode($b,true):null;if(!is_array($d))continue;
            foreach($d as $r){
                if(!is_array($r))continue;$name=trim((string)($r['name']??''));$home=rrw_dir_safe_url((string)($r['homepage']??''));
                $raw=(string)($r['url_resolved']??($r['url']??''));
                if($name===''||$home===''||stripos($home,'laut.fm')!==false||stripos($raw,'laut.fm')!==false)continue;
                $st=rrw_dir_stream_url($raw);$try=rrw_dir_stream_try($raw);if($st===''&&$try==='')continue;
                $tags=array_slice(array_values(array_filter(array_map('trim',explode(',',(string)($r['tags']??''))),fn($t)=>$t!==''&&mb_strlen($t)<=24)),0,4);
                $out[]=['source'=>'world','id'=>(string)($r['stationuuid']??''),'name'=>mb_substr($name,0,80),'description'=>'','genres'=>$tags,'cover'=>rrw_dir_https_img((string)($r['favicon']??'')),
                    'bitrate'=>(int)($r['bitrate']??0),'codec'=>mb_substr(trim((string)($r['codec']??'')),0,10),'link'=>$home,'stream'=>$st,'stream_try'=>$try,
                    'country'=>trim((string)($r['country']??'')),'language'=>trim((string)($r['language']??'')),'own'=>false];
                if(++$got>=$wantWorld)break;
            }
            break;
        }
    }
    $out=rrw_dir_apply_admin($out,rrw_dir_admin_load($dataDir));
    shuffle($out);return $out;
}


// ---------------------------------------------------------------- Verwaltung (CMS): Einstellungen, Ausschlüsse, Meldungen
const RRW_DIR_REASONS=['rights'=>'Rechtsverletzung / Urheberrecht','offline'=>'Nicht erreichbar / defekt','content'=>'Unpassender Inhalt','wrong'=>'Falsche Angaben','other'=>'Sonstiges'];
function rrw_dir_admin_defaults(): array {
    return ['settings'=>['laut'=>true,'world'=>true,'foreign_play'=>true,'preview'=>true,'report'=>true],'blocked'=>[],'reports'=>[]];
}
function rrw_dir_admin_file(string $dataDir): string { return rrw_dir_dir($dataDir).'/admin.json'; }
function rrw_dir_admin_load(string $dataDir): array {
    $d=rrw_dir_admin_defaults();$f=rrw_dir_admin_file($dataDir);
    if(is_file($f)){$j=json_decode((string)@file_get_contents($f),true);if(is_array($j)){
        foreach($d['settings'] as $k=>$v)if(isset($j['settings'][$k]))$d['settings'][$k]=(bool)$j['settings'][$k];
        $d['blocked']=array_values(array_filter((array)($j['blocked']??[]),fn($b)=>is_array($b)&&!empty($b['key'])));
        $d['reports']=array_values(array_filter((array)($j['reports']??[]),fn($r)=>is_array($r)&&!empty($r['id'])));
    }}
    return $d;
}
function rrw_dir_admin_save(string $dataDir,array $d): bool {
    $d['blocked']=array_slice($d['blocked'],0,2000);$d['reports']=array_slice($d['reports'],-500);
    $ok=@file_put_contents(rrw_dir_admin_file($dataDir),json_encode($d,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX)!==false;
    return $ok;
}
function rrw_dir_host(string $u): string { $h=strtolower((string)parse_url($u,PHP_URL_HOST));return preg_replace('/^www\./','',$h); }
// Schlüssel einer Sperre: laut:<id> | world:<uuid> | host:<domain>
function rrw_dir_block_keys(array $it): array {
    $k=[];if(!empty($it['id']))$k[]=($it['source']??'').':'.strtolower((string)$it['id']);
    foreach(['link','stream','stream_try'] as $f)if(!empty($it[$f])){$h=rrw_dir_host((string)$it[$f]);if($h!=='')$k[]='host:'.$h;}
    return $k;
}
function rrw_dir_is_blocked(array $it,array $adm): bool {
    if(!$adm['blocked'])return false;
    static $idx=null,$for=null;
    if($for!==$adm['blocked']){$idx=[];foreach($adm['blocked'] as $b)$idx[strtolower((string)$b['key'])]=1;$for=$adm['blocked'];}
    foreach(rrw_dir_block_keys($it) as $k)if(isset($idx[$k]))return true;
    return false;
}
// Ergebnisse nach den Einstellungen und Ausschlüssen bereinigen
function rrw_dir_apply_admin(array $items,array $adm): array {
    $out=[];
    foreach($items as $it){
        if(!is_array($it))continue;
        if(($it['source']??'')==='laut'&&!$adm['settings']['laut'])continue;
        if(($it['source']??'')==='world'&&!$adm['settings']['world'])continue;
        if(empty($it['own'])&&rrw_dir_is_blocked($it,$adm))continue;
        if(!$adm['settings']['foreign_play']){$it['stream']='';$it['stream_try']='';}
        $out[]=$it;
    }
    return $out;
}
function rrw_dir_url_blocked(string $url,array $adm): bool {
    $h=rrw_dir_host($url);if($h==='')return false;
    foreach($adm['blocked'] as $b)if(strtolower((string)$b['key'])==='host:'.$h)return true;
    return false;
}
function rrw_dir_report_add(string $dataDir,array $in): array {
    $adm=rrw_dir_admin_load($dataDir);
    if(!$adm['settings']['report'])return ['ok'=>false,'message'=>'Meldungen sind derzeit deaktiviert.'];
    if(trim((string)($in['website']??''))!=='')return ['ok'=>true]; // Honeypot
    $src=in_array(($in['source']??''),['laut','world'],true)?(string)$in['source']:'';$id=mb_substr(trim((string)($in['id']??'')),0,120);
    if($src===''||$id==='')return ['ok'=>false,'message'=>'Unvollständige Meldung.'];
    $reason=array_key_exists(($in['reason']??''),RRW_DIR_REASONS)?(string)$in['reason']:'other';
    $text=mb_substr(trim(preg_replace('/\s+/u',' ',strip_tags((string)($in['text']??'')))),0,400);
    $name=mb_substr(trim(strip_tags((string)($in['name']??''))),0,100);$link=rrw_dir_safe_url((string)($in['link']??''));
    foreach($adm['reports'] as &$r){
        if($r['status']==='open'&&$r['source']===$src&&strtolower($r['station_id'])===strtolower($id)){$r['count']=(int)($r['count']??1)+1;if($text!==''&&empty($r['text']))$r['text']=$text;$r['last']=time();unset($r);goto saved;}
    }
    unset($r);
    $adm['reports'][]=['id'=>bin2hex(random_bytes(6)),'source'=>$src,'station_id'=>$id,'name'=>$name,'link'=>$link,'reason'=>$reason,'text'=>$text,'ts'=>time(),'last'=>time(),'count'=>1,'status'=>'open'];
    saved:
    return ['ok'=>rrw_dir_admin_save($dataDir,$adm)];
}
// Ansicht für das CMS
function rrw_dir_admin_view(string $dataDir): array {
    $a=rrw_dir_admin_load($dataDir);
    $a['reasons']=RRW_DIR_REASONS;
    $a['reports']=array_reverse($a['reports']);
    return $a;
}
// Änderungen aus dem CMS (op: settings | block_add | block_remove | report_block | report_dismiss | report_delete)
function rrw_dir_admin_apply(string $dataDir,array $in,string $user): array {
    $a=rrw_dir_admin_load($dataDir);$op=(string)($in['op']??'');
    $addBlock=function(string $key,string $name,string $reason) use(&$a,$user){
        $key=strtolower(trim($key));if(!preg_match('~^(laut|world|host):[^\s]{1,160}$~',$key))return false;
        foreach($a['blocked'] as $b)if(strtolower($b['key'])===$key)return true;
        $a['blocked'][]=['key'=>$key,'name'=>mb_substr(trim(strip_tags($name)),0,100),'reason'=>mb_substr(trim(strip_tags($reason)),0,200),'added'=>time(),'by'=>$user];return true;
    };
    if($op==='settings'){foreach($a['settings'] as $k=>$v)if(isset($in['settings'][$k]))$a['settings'][$k]=(bool)$in['settings'][$k];}
    elseif($op==='block_add'){
        $type=(string)($in['type']??'');$val=trim((string)($in['value']??''));
        if($type==='host'){$val=rrw_dir_host(preg_match('~^https?://~i',$val)?$val:'https://'.$val);}
        if($val===''||!$addBlock($type.':'.$val,(string)($in['name']??$val),(string)($in['reason']??'')))return ['ok'=>false,'message'=>'Ungültiger Eintrag.'];
    }
    elseif($op==='block_remove'){$k=strtolower((string)($in['key']??''));$a['blocked']=array_values(array_filter($a['blocked'],fn($b)=>strtolower($b['key'])!==$k));}
    elseif($op==='report_block'||$op==='report_dismiss'||$op==='report_delete'){
        $id=(string)($in['id']??'');$found=false;
        foreach($a['reports'] as $i=>&$r){
            if($r['id']!==$id)continue;$found=true;
            if($op==='report_block'){
                $key=$r['source'].':'.strtolower($r['station_id']);
                if(!empty($in['host'])&&$r['link']!=='')$key='host:'.rrw_dir_host($r['link']);
                if(!$addBlock($key,$r['name']?:$r['station_id'],'Meldung: '.(RRW_DIR_REASONS[$r['reason']]??'')))return ['ok'=>false,'message'=>'Ausschluss nicht möglich.'];
                $r['status']='blocked';
            }elseif($op==='report_dismiss')$r['status']='dismissed';
            else $r['status']='deleted';
        }
        unset($r);if(!$found)return ['ok'=>false,'message'=>'Meldung nicht gefunden.'];
        $a['reports']=array_values(array_filter($a['reports'],fn($r)=>$r['status']!=='deleted'));
    }
    else return ['ok'=>false,'message'=>'Unbekannte Aktion.'];
    return ['ok'=>rrw_dir_admin_save($dataDir,$a)];
}

// ---------------------------------------------------------------- Einstieg
function rrw_directory_search(array $site,array $in,string $dataDir): array {
    $own=array_map('strtolower',array_map('strval',(array)($site['core_network']['stations']??[])));
    $q=trim(mb_substr((string)($in['q']??''),0,60));
    $scope=in_array(($in['scope']??'all'),['all','laut','world'],true)?(string)($in['scope']??'all'):'all';
    $offset=max(0,min(2000,(int)($in['offset']??0)));$limit=max(1,min(24,(int)($in['limit']??12)));
    $out=['status'=>'ok','query'=>$q,'scope'=>$scope,'results'=>[],'counts'=>['laut'=>0,'world'=>0],'has_more'=>false];
    if(!empty($in['genres']))$out['genres']=rrw_dir_genres($dataDir);
    $adm=rrw_dir_admin_load($dataDir);$out['report']=!empty($adm['settings']['report']);
    if(mb_strlen($q)>=2){
        if($scope!=='world'){$r=rrw_dir_laut_search($q,$offset,$limit,$own,$dataDir);$out['results']=array_merge($out['results'],$r['items']);$out['counts']['laut']=$r['total'];if($r['total']>$offset+$limit)$out['has_more']=true;}
        if($scope!=='laut'){$w=rrw_dir_world_search($q,$offset,$limit,$dataDir);$out['results']=array_merge($out['results'],$w);$out['counts']['world']=count($w);if(count($w)>=$limit)$out['has_more']=true;}
    }
    $out['results']=rrw_dir_apply_admin($out['results'],$adm);
    return $out;
}

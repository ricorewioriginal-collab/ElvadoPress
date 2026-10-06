<?php
declare(strict_types=1);
// Freie Bilder in der Mediathek: Suche und Übernahme aus Pixabay, Pexels, Unsplash (jeweils mit eigenem, kostenlosem API-Schlüssel)
// sowie Openverse und Wikimedia Commons (ohne Schlüssel). Übernommene Bilder werden in die Medienbibliothek heruntergeladen (kein
// Hotlinking, die Website bleibt unabhängig) und tragen ihren Bildnachweis (Urheber, Quelle, Lizenz) im meta.json.
// Schlüssel liegen nur serverseitig (cms/data/.tools/stockmedia.json, gesperrter Ordner) und werden nie an den Browser gesendet.
// Abrufe sind SSRF-geschützt (nur öffentliche Adressen), Downloads auf 15 MB begrenzt, das Bildformat prüft die Mediathek (finfo).
require_once __DIR__.'/tools.php';
require_once __DIR__.'/feeds.php';

const RRW_STOCK_PER_PAGE=24;
function rrw_stock_providers(): array {
    return [
        'pixabay'=>['name'=>'Pixabay','key'=>true,'key_url'=>'https://pixabay.com/api/docs/','license'=>'Pixabay-Inhaltslizenz (frei nutzbar, Namensnennung nicht nötig)','hosts'=>['pixabay.com','cdn.pixabay.com']],
        'pexels'=>['name'=>'Pexels','key'=>true,'key_url'=>'https://www.pexels.com/api/new/','license'=>'Pexels-Lizenz (frei nutzbar, Namensnennung erwünscht)','hosts'=>['images.pexels.com']],
        'unsplash'=>['name'=>'Unsplash','key'=>true,'key_url'=>'https://unsplash.com/oauth/applications','license'=>'Unsplash-Lizenz (frei nutzbar; Quellenangabe laut API-Richtlinien)','hosts'=>['images.unsplash.com']],
        'openverse'=>['name'=>'Openverse','key'=>false,'key_url'=>'','license'=>'Creative-Commons-Bilder (Namensnennung je nach Lizenz Pflicht)','hosts'=>[]],
        'wikimedia'=>['name'=>'Wikimedia Commons','key'=>false,'key_url'=>'','license'=>'Freie Lizenzen (meist Creative Commons; Namensnennung je nach Lizenz Pflicht)','hosts'=>['upload.wikimedia.org']],
    ];
}
function rrw_stock_file(string $dataDir): string { return rrw_tools_dir($dataDir).'/stockmedia.json'; }
/** Konfiguration: ['keys'=>[Anbieter=>Schlüssel],'enabled'=>[Anbieter=>bool]]; ohne Eintrag sind Anbieter ohne Schlüssel an, die mit Schlüssel an, sobald einer gesetzt ist. */
function rrw_stock_config(string $dataDir): array {
    $c=rrw_tools_read(rrw_stock_file($dataDir),[]);$p=rrw_stock_providers();$keys=[];$en=[];
    foreach($p as $id=>$def){
        $k=trim((string)($c['keys'][$id]??''));$keys[$id]=preg_match('/^[A-Za-z0-9_:.\-]{8,200}$/',$k)?$k:'';
        $en[$id]=array_key_exists($id,(array)($c['enabled']??[]))?!empty($c['enabled'][$id]):true;
    }
    return ['keys'=>$keys,'enabled'=>$en];
}
/** Speichern: $keys[id]='' lässt den Schlüssel unverändert, $keys[id]=null/'-' entfernt ihn. */
function rrw_stock_save(string $dataDir,array $in): array {
    $cur=rrw_stock_config($dataDir);$p=rrw_stock_providers();
    foreach($p as $id=>$def){
        if(array_key_exists($id,(array)($in['keys']??[]))){ $k=$in['keys'][$id];if($k===null||$k==='-')$cur['keys'][$id]='';elseif(is_string($k)&&trim($k)!==''){ $k=trim($k);if(!preg_match('/^[A-Za-z0-9_:.\-]{8,200}$/',$k))throw new InvalidArgumentException('Der Schlüssel für '.$def['name'].' enthält unzulässige Zeichen.');$cur['keys'][$id]=$k; } }
        if(array_key_exists($id,(array)($in['enabled']??[])))$cur['enabled'][$id]=filter_var($in['enabled'][$id],FILTER_VALIDATE_BOOLEAN);
    }
    rrw_tools_write(rrw_tools_dir($dataDir),'stockmedia.json',$cur);
    foreach(glob(rrw_tools_dir($dataDir).'/stock-cache/*.json')?:[] as $f)@unlink($f);
    return $cur;
}
/** Zustand für die Oberfläche (ohne Schlüssel): [{id,name,needs_key,has_key,enabled,usable,key_url,license}]. */
function rrw_stock_status(string $dataDir): array {
    $c=rrw_stock_config($dataDir);$o=[];
    foreach(rrw_stock_providers() as $id=>$d){ $has=$c['keys'][$id]!=='';$o[]=['id'=>$id,'name'=>$d['name'],'needs_key'=>$d['key'],'has_key'=>$has,'enabled'=>$c['enabled'][$id],'usable'=>$c['enabled'][$id]&&(!$d['key']||$has),'key_url'=>$d['key_url'],'license'=>$d['license']]; }
    return $o;
}

/* ───────── HTTP ───────── */
/** JSON/Text-Abruf mit Kopfzeilen; Tests setzen $GLOBALS['rrw_stock_http'] (callable url,headers → string). */
function rrw_stock_http(string $url,array $headers=[],int $timeout=10): string {
    if(isset($GLOBALS['rrw_stock_http']))return (string)($GLOBALS['rrw_stock_http'])($url,$headers);
    if(!rrw_remote_feed_allowed($url)||!function_exists('curl_init'))return '';
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>3,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>$timeout,CURLOPT_USERAGENT=>rrw_feed_user_agent(false),CURLOPT_HTTPHEADER=>$headers,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS]);
    $raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$final=(string)curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);curl_close($ch);
    return $raw!==false&&$code>=200&&$code<300&&rrw_remote_feed_allowed($final)?(string)$raw:'';
}
function rrw_stock_json(string $url,array $headers=[]): ?array { $r=rrw_stock_http($url,$headers);$j=$r!==''?json_decode($r,true):null;return is_array($j)?$j:null; }
/** Bild herunterladen (höchstens 15 MB, nur öffentliche Adressen, nur http/https). Tests: $GLOBALS['rrw_stock_download'] (callable url,ziel → bool). */
function rrw_stock_download(string $url,string $dest,int $max=15728640): bool {
    if(isset($GLOBALS['rrw_stock_download']))return (bool)($GLOBALS['rrw_stock_download'])($url,$dest);
    if(!rrw_remote_feed_allowed($url)||!function_exists('curl_init'))return false;
    $fh=@fopen($dest,'wb');if(!$fh)return false;$got=0;$ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>3,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>40,CURLOPT_USERAGENT=>rrw_feed_user_agent(false),CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,
        CURLOPT_WRITEFUNCTION=>function($c,$d) use($fh,&$got,$max){ $got+=strlen($d);if($got>$max)return 0;return fwrite($fh,$d); }]);
    $ok=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$final=(string)curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);curl_close($ch);fclose($fh);
    if($ok===false||$code<200||$code>=300||!rrw_remote_feed_allowed($final)||$got<=0){ @unlink($dest);return false; }
    return true;
}

/* ───────── Normalisierung ───────── */
function rrw_stock_clip($v,int $n=200): string { return mb_substr(trim(preg_replace('/\s+/u',' ',strip_tags(html_entity_decode((string)$v,ENT_QUOTES,'UTF-8')))),0,$n); }
function rrw_stock_url($v): string { $u=trim((string)$v);return preg_match('~^https://[^\s"\'<>]{4,1200}$~',$u)?$u:''; }
/** Einheitliches Ergebnis; „credit“ ist der fertige Bildnachweis-Text. */
function rrw_stock_item(string $provider,string $id,array $f): array {
    $author=rrw_stock_clip($f['author']??'',120);$src=(string)rrw_stock_providers()[$provider]['name'];
    $text=$f['credit']??($author!==''?'Foto: '.$author.' / '.$src:'Foto: '.$src);
    return ['provider'=>$provider,'id'=>$id,'title'=>rrw_stock_clip($f['title']??'',160),'thumb'=>rrw_stock_url($f['thumb']??''),'preview'=>rrw_stock_url($f['preview']??($f['thumb']??'')),
        'width'=>(int)($f['width']??0),'height'=>(int)($f['height']??0),'author'=>$author,'author_url'=>rrw_stock_url($f['author_url']??''),'source_url'=>rrw_stock_url($f['source_url']??''),
        'license'=>rrw_stock_clip($f['license']??'',80),'license_url'=>rrw_stock_url($f['license_url']??''),'attribution_required'=>!empty($f['attribution_required']),'credit'=>rrw_stock_clip($text,300),
        'download'=>rrw_stock_url($f['download']??'')];
}
function rrw_stock_orient(string $o,array $map): string { return $map[$o]??''; }
/** Pro Anbieter: Suchadresse + Kopfzeilen + Abbildung der Antwort auf Items. [url,headers,parser] */
function rrw_stock_search_request(string $p,string $key,string $q,int $page,string $orient): array {
    $per=RRW_STOCK_PER_PAGE;$qe=rawurlencode($q);
    switch($p){
    case 'pixabay':
        $o=rrw_stock_orient($orient,['landscape'=>'horizontal','portrait'=>'vertical']);
        return ['https://pixabay.com/api/?key='.rawurlencode($key).'&q='.$qe.'&image_type=photo&safesearch=true&lang=de&per_page='.$per.'&page='.$page.($o!==''?'&orientation='.$o:''),[],'rrw_stock_parse_pixabay'];
    case 'pexels':
        $o=rrw_stock_orient($orient,['landscape'=>'landscape','portrait'=>'portrait','square'=>'square']);
        return ['https://api.pexels.com/v1/search?query='.$qe.'&per_page='.$per.'&page='.$page.($o!==''?'&orientation='.$o:''),['Authorization: '.$key],'rrw_stock_parse_pexels'];
    case 'unsplash':
        $o=rrw_stock_orient($orient,['landscape'=>'landscape','portrait'=>'portrait','square'=>'squarish']);
        return ['https://api.unsplash.com/search/photos?query='.$qe.'&per_page='.$per.'&page='.$page.($o!==''?'&orientation='.$o:''),['Authorization: Client-ID '.$key,'Accept-Version: v1'],'rrw_stock_parse_unsplash'];
    case 'openverse':
        $o=rrw_stock_orient($orient,['landscape'=>'wide','portrait'=>'tall','square'=>'square']);
        return ['https://api.openverse.org/v1/images/?q='.$qe.'&page_size='.$per.'&page='.$page.'&license_type=commercial,modification&mature=false'.($o!==''?'&aspect_ratio='.$o:''),[],'rrw_stock_parse_openverse'];
    case 'wikimedia':
        return ['https://commons.wikimedia.org/w/api.php?action=query&format=json&generator=search&gsrnamespace=6&gsrsearch='.rawurlencode($q.' filetype:bitmap').'&gsrlimit='.$per.'&gsroffset='.(($page-1)*$per).'&prop=imageinfo&iiprop=url|size|mime|extmetadata&iiurlwidth=640',[],'rrw_stock_parse_wikimedia'];
    }
    return ['',[],''];
}
function rrw_stock_parse_pixabay(array $j): array {
    $out=[];foreach((array)($j['hits']??[]) as $h){ if(!is_array($h))continue;$id=(string)($h['id']??'');if($id==='')continue;
        $out[]=rrw_stock_item('pixabay',$id,['title'=>$h['tags']??'','thumb'=>$h['webformatURL']??($h['previewURL']??''),'preview'=>$h['webformatURL']??'','width'=>$h['imageWidth']??0,'height'=>$h['imageHeight']??0,'author'=>$h['user']??'',
            'author_url'=>!empty($h['user'])&&!empty($h['user_id'])?'https://pixabay.com/users/'.rawurlencode((string)$h['user']).'-'.(int)$h['user_id'].'/':'','source_url'=>$h['pageURL']??'','license'=>'Pixabay-Inhaltslizenz','license_url'=>'https://pixabay.com/service/license-summary/','download'=>$h['largeImageURL']??($h['webformatURL']??'')]); }
    return ['items'=>$out,'total'=>(int)($j['totalHits']??count($out))];
}
function rrw_stock_parse_pexels(array $j): array {
    $out=[];foreach((array)($j['photos']??[]) as $h){ if(!is_array($h))continue;$id=(string)($h['id']??'');if($id==='')continue;$s=(array)($h['src']??[]);
        $out[]=rrw_stock_item('pexels',$id,['title'=>$h['alt']??'','thumb'=>$s['medium']??'','preview'=>$s['large']??($s['medium']??''),'width'=>$h['width']??0,'height'=>$h['height']??0,'author'=>$h['photographer']??'','author_url'=>$h['photographer_url']??'','source_url'=>$h['url']??'',
            'license'=>'Pexels-Lizenz','license_url'=>'https://www.pexels.com/license/','download'=>$s['large2x']??($s['large']??'')]); }
    return ['items'=>$out,'total'=>(int)($j['total_results']??count($out))];
}
function rrw_stock_parse_unsplash(array $j): array {
    $out=[];foreach((array)($j['results']??($j['id']??null?[$j]:[])) as $h){ if(!is_array($h))continue;$id=(string)($h['id']??'');if($id==='')continue;$u=(array)($h['urls']??[]);
        $out[]=rrw_stock_item('unsplash',$id,['title'=>$h['description']??($h['alt_description']??''),'thumb'=>$u['small']??'','preview'=>$u['regular']??($u['small']??''),'width'=>$h['width']??0,'height'=>$h['height']??0,'author'=>$h['user']['name']??'','author_url'=>($h['user']['links']['html']??'').'?utm_source=elvadopress&utm_medium=referral',
            'source_url'=>($h['links']['html']??'').'?utm_source=elvadopress&utm_medium=referral','license'=>'Unsplash-Lizenz','license_url'=>'https://unsplash.com/license','attribution_required'=>true,'download'=>$u['regular']??'']);
        if(isset($h['links']['download_location']))$out[count($out)-1]['track']=(string)$h['links']['download_location']; }
    return ['items'=>$out,'total'=>(int)($j['total']??count($out))];
}
function rrw_stock_license_free(string $l): bool { return in_array(strtolower($l),['cc0','pdm','publicdomain'],true); }
function rrw_stock_parse_openverse(array $j): array {
    $out=[];foreach((array)($j['results']??(($j['id']??null)?[$j]:[])) as $h){ if(!is_array($h))continue;$id=(string)($h['id']??'');if($id==='')continue;
        $lic='CC '.trim(strtoupper((string)($h['license']??'')).' '.(string)($h['license_version']??''));$title=rrw_stock_clip($h['title']??'',160);$by=rrw_stock_clip($h['creator']??'',120);
        $credit=rrw_stock_clip($h['attribution']??'',300);if($credit==='')$credit=rrw_stock_clip(($title!==''?'„'.$title.'“ ':'').($by!==''?'von '.$by.' ':'').'('.$lic.')',300);
        $out[]=rrw_stock_item('openverse',$id,['title'=>$title,'thumb'=>$h['thumbnail']??'','preview'=>$h['thumbnail']??'','width'=>$h['width']??0,'height'=>$h['height']??0,'author'=>$by,'author_url'=>$h['creator_url']??'','source_url'=>$h['foreign_landing_url']??'',
            'license'=>$lic,'license_url'=>$h['license_url']??'','attribution_required'=>!rrw_stock_license_free((string)($h['license']??'')),'download'=>$h['url']??'','credit'=>$credit]); }
    return ['items'=>$out,'total'=>(int)($j['result_count']??count($out))];
}
function rrw_stock_parse_wikimedia(array $j): array {
    $out=[];foreach((array)($j['query']['pages']??[]) as $pid=>$h){ if(!is_array($h)||empty($h['imageinfo'][0]))continue;$i=$h['imageinfo'][0];$mime=(string)($i['mime']??'');if(!in_array($mime,['image/jpeg','image/png','image/webp','image/gif'],true))continue;
        $m=(array)($i['extmetadata']??[]);$val=fn($k)=>rrw_stock_clip($m[$k]['value']??'',200);$lic=$val('LicenseShortName');$free=stripos($lic,'CC0')!==false||stripos($lic,'public domain')!==false||stripos($lic,'PD')===0;
        $title=preg_replace('/^File:|\.[a-z]{3,4}$/i','',(string)($h['title']??''));$artist=$val('Artist');
        $out[]=rrw_stock_item('wikimedia',(string)($h['pageid']??$pid),['title'=>$title,'thumb'=>$i['thumburl']??'','preview'=>$i['thumburl']??'','width'=>$i['width']??0,'height'=>$i['height']??0,'author'=>$artist,'source_url'=>$i['descriptionurl']??'','license'=>$lic,'license_url'=>rrw_stock_clip($m['LicenseUrl']['value']??'',300),
            'attribution_required'=>!$free,'download'=>$i['url']??'','credit'=>rrw_stock_clip(($artist!==''?$artist.' / ':'').'Wikimedia Commons'.($lic!==''?', '.$lic:''),300)]); }
    return ['items'=>$out,'total'=>count($out)+(count($out)>=RRW_STOCK_PER_PAGE?RRW_STOCK_PER_PAGE:0)];
}

/* ───────── Suche und Übernahme ───────── */
function rrw_stock_usable(string $dataDir,string $p): ?string {
    $def=rrw_stock_providers()[$p]??null;if(!$def)return null;$c=rrw_stock_config($dataDir);
    if(!$c['enabled'][$p])return null;if($def['key']&&$c['keys'][$p]==='')return null;return $c['keys'][$p];
}
/** Suche (10 Minuten zwischengespeichert, schont die Ratenbegrenzung der Anbieter). Ergebnis: ['items'=>…,'total'=>…,'page'=>…,'per_page'=>…] oder wirft RuntimeException. */
function rrw_stock_search(string $dataDir,string $p,string $q,int $page=1,string $orient=''): array {
    $key=rrw_stock_usable($dataDir,$p);if($key===null)throw new RuntimeException('Diese Bildquelle ist nicht eingerichtet oder ausgeschaltet.');
    $q=trim(preg_replace('/\s+/u',' ',strip_tags($q)));if($q===''||mb_strlen($q)>100)throw new RuntimeException('Bitte einen Suchbegriff eingeben (höchstens 100 Zeichen).');
    $page=max(1,min(50,$page));if(!in_array($orient,['landscape','portrait','square'],true))$orient='';
    $cache=rrw_tools_dir($dataDir).'/stock-cache/'.md5($p.'|'.$q.'|'.$page.'|'.$orient).'.json';
    $old=is_file($cache)?json_decode((string)@file_get_contents($cache),true):null;
    if(is_array($old)&&isset($old['t'],$old['d'])&&time()-(int)$old['t']<600)return $old['d'];
    [$url,$hdr,$parser]=rrw_stock_search_request($p,(string)$key,$q,$page,$orient);
    $j=rrw_stock_json($url,$hdr);
    if($j===null)throw new RuntimeException('Die Bildquelle antwortet nicht. Schlüssel prüfen oder später erneut versuchen.');
    $r=$parser($j);$r+=['page'=>$page,'per_page'=>RRW_STOCK_PER_PAGE];
    $r['items']=array_map(function($i){ unset($i['download'],$i['track']);return $i; },$r['items']);   // Download-Adressen verlassen den Server nicht: die Übernahme holt sie selbst neu
    try{ rrw_tools_write(dirname($cache),basename($cache),['t'=>time(),'d'=>$r]); }catch(Throwable $e){}
    return $r;
}
/** Einzelnes Bild (Einzelabruf nach Kennung) – liefert das Item inkl. Download-Adresse (nur serverintern). */
function rrw_stock_detail(string $dataDir,string $p,string $id): array {
    $key=rrw_stock_usable($dataDir,$p);if($key===null)throw new RuntimeException('Diese Bildquelle ist nicht eingerichtet oder ausgeschaltet.');
    if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/',$id))throw new RuntimeException('Ungültige Bildkennung.');
    switch($p){
        case 'pixabay': $j=rrw_stock_json('https://pixabay.com/api/?key='.rawurlencode((string)$key).'&id='.rawurlencode($id));$r=$j?rrw_stock_parse_pixabay($j):null;break;
        case 'pexels': $j=rrw_stock_json('https://api.pexels.com/v1/photos/'.rawurlencode($id),['Authorization: '.$key]);$r=$j?rrw_stock_parse_pexels(['photos'=>[$j]]):null;break;
        case 'unsplash': $j=rrw_stock_json('https://api.unsplash.com/photos/'.rawurlencode($id),['Authorization: Client-ID '.$key,'Accept-Version: v1']);$r=$j?rrw_stock_parse_unsplash($j):null;break;
        case 'openverse': $j=rrw_stock_json('https://api.openverse.org/v1/images/'.rawurlencode($id).'/');$r=$j?rrw_stock_parse_openverse($j):null;break;
        case 'wikimedia': $j=rrw_stock_json('https://commons.wikimedia.org/w/api.php?action=query&format=json&prop=imageinfo&iiprop=url|size|mime|extmetadata&iiurlwidth=640&pageids='.rawurlencode($id));$r=$j?rrw_stock_parse_wikimedia($j):null;break;
        default: $r=null;
    }
    $it=$r['items'][0]??null;if(!$it||$it['download']==='')throw new RuntimeException('Das Bild wurde bei der Quelle nicht gefunden.');
    return $it;
}
/** Zulässiger Download-Host je Anbieter (Openverse: beliebige öffentliche https-Hosts, Schutz über rrw_remote_feed_allowed). */
function rrw_stock_host_ok(string $p,string $url): bool {
    $h=strtolower((string)parse_url($url,PHP_URL_HOST));if($h===''||!str_starts_with($url,'https://'))return false;$hosts=rrw_stock_providers()[$p]['hosts']??[];
    if(!$hosts)return true;foreach($hosts as $x)if($h===$x||str_ends_with($h,'.'.$x))return true;return false;
}
/** Bild in die Mediathek übernehmen. Ergebnis: ['item'=>meta,'warnings'=>[],'credit'=>Text,'attribution_required'=>bool]. */
function rrw_stock_import(string $dataDir,string $p,string $id): array {
    require_once __DIR__.'/media.php';
    $it=rrw_stock_detail($dataDir,$p,$id);$url=$it['download'];
    if(!rrw_stock_host_ok($p,$url))throw new RuntimeException('Die Download-Adresse der Quelle ist nicht zulässig.');
    $tmp=tempnam(sys_get_temp_dir(),'stock');if($tmp===false)throw new RuntimeException('Temporäre Datei nicht möglich.');
    try{
        if(!rrw_stock_download($url,$tmp))throw new RuntimeException('Das Bild konnte nicht heruntergeladen werden (zu groß oder nicht erreichbar).');
        if($p==='unsplash'&&!empty($it['track'])&&str_starts_with($it['track'],'https://api.unsplash.com/'))rrw_stock_http($it['track'],['Authorization: Client-ID '.(string)rrw_stock_usable($dataDir,$p),'Accept-Version: v1']);   // Unsplash verlangt die Meldung des Downloads
        $base=preg_replace('/[^a-z0-9]+/','-',mb_strtolower($it['title']!==''?$it['title']:$p));$base=trim(substr($base,0,50),'-');$name=($base!==''?$base:$p).'-'.$p.'-'.$id;
        $credit=['provider'=>$p,'provider_name'=>rrw_stock_providers()[$p]['name'],'title'=>$it['title'],'author'=>$it['author'],'author_url'=>$it['author_url'],'source_url'=>$it['source_url'],'license'=>$it['license'],'license_url'=>$it['license_url'],'attribution_required'=>$it['attribution_required'],'text'=>$it['credit']];
        $r=rrw_media_library_store(['tmp_name'=>$tmp,'size'=>(int)@filesize($tmp),'name'=>$name],rrw_media_sizes('64,128,192,256,512,1024,1600'),90,'rename',$credit);
    } finally { if(is_file($tmp))@unlink($tmp); }
    $r['credit']=$it['credit'];$r['attribution_required']=$it['attribution_required'];
    return $r;
}

/**
 * Passendes Bild zu einem Suchbegriff finden (für den Website-Generator): Anbieter in der Reihenfolge Pixabay, Pexels, Unsplash, Openverse, Wikimedia Commons;
 * bevorzugt Bilder ohne Namensnennungspflicht und mit ausreichender Auflösung. Ergebnis: Item (wie rrw_stock_search) oder null.
 */
function rrw_stock_pick(string $dataDir,string $q,string $orient='landscape'): ?array {
    $best=null;$bestScore=-1;
    foreach(['pixabay','pexels','unsplash','openverse','wikimedia'] as $p){
        if(rrw_stock_usable($dataDir,$p)===null)continue;
        try{ $r=rrw_stock_search($dataDir,$p,$q,1,$orient); }catch(Throwable $e){ continue; }
        foreach(array_slice((array)($r['items']??[]),0,8) as $it){
            $w=(int)($it['width']??0);$h=(int)($it['height']??0);
            $score=($it['attribution_required']?0:100)+($w>=1600?30:($w>=1000?20:($w>=700?5:0)))+($orient==='landscape'&&$w>$h?10:0)+($p==='wikimedia'?-10:0);
            if($w>0&&$w<600)$score-=50;
            if($score>$bestScore){$bestScore=$score;$best=$it;}
        }
        if($best!==null&&$bestScore>=120)break;   // gutes, frei nutzbares Bild gefunden
    }
    return $best;
}

/**
 * Bilder für einen Website-Entwurf laden. $queries: [{key,q,orient?,width?}] (höchstens $max). Je Anfrage: passendes Bild suchen, in die Mediathek übernehmen, Alt-Text bestimmen und speichern.
 * $altFn(array $meta,string $file,string $q,string $title): string liefert einen KI-Vorschlag (Fehler werden abgefangen; Rückfall: Titel der Bildquelle bzw. Suchbegriff).
 * @return list<array<string,mixed>> je Anfrage: key, ok, url, alt, credit, attribution_required, provider, error
 */
function rrw_stock_fetch_for_plan(string $dataDir,array $queries,callable $altFn,int $max=8): array {
    require_once __DIR__.'/media.php';
    $out=[];
    foreach(array_slice($queries,0,$max) as $qq){
        $key=(string)($qq['key']??'');$q=trim(preg_replace('/\s+/u',' ',strip_tags((string)($qq['q']??''))));$row=['key'=>$key,'ok'=>false,'url'=>'','alt'=>'','credit'=>'','attribution_required'=>false,'provider'=>'','error'=>''];
        try{
            if($q==='')throw new RuntimeException('kein Suchbegriff');
            $pick=rrw_stock_pick($dataDir,$q,in_array(($qq['orient']??''),['landscape','portrait','square'],true)?(string)$qq['orient']:'landscape');
            if($pick===null)throw new RuntimeException('kein passendes Bild gefunden');
            $r=rrw_stock_import($dataDir,(string)$pick['provider'],(string)$pick['id']);
            $meta=(array)$r['item'];$url=rrw_media_pick_variant($meta,max(320,min(2400,(int)($qq['width']??1600))));
            $alt='';
            try{ $src=rrw_media_alt_source((string)$meta['id']);$alt=trim((string)$altFn($src['meta'],$src['file'],$q,(string)($pick['title']??''))); }catch(Throwable $e){}
            if($alt==='')$alt=trim((string)($pick['title']??''))!==''?mb_substr(trim((string)$pick['title']),0,125):mb_strtoupper(mb_substr($q,0,1)).mb_substr($q,1);
            try{ rrw_media_alt_save((string)$meta['id'],$alt); }catch(Throwable $e){}
            $row=['key'=>$key,'ok'=>true,'url'=>$url,'alt'=>$alt,'credit'=>(string)($r['credit']??''),'attribution_required'=>!empty($r['attribution_required']),'provider'=>(string)$pick['provider'],'error'=>'','id'=>(string)$meta['id']];
        }catch(Throwable $e){ $row['error']=mb_substr($e->getMessage(),0,160); }
        $out[]=$row;
    }
    return $out;
}

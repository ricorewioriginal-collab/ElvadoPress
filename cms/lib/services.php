<?php
declare(strict_types=1);
// Verbundene Dienste im eigenständigen CMS: Der Betreiber trägt seine eigenen Dienste ein (Website, Cloud, Podcast, Video, Statistik, Shop …),
// bekommt eine Übersicht mit Links und kann die Erreichbarkeit prüfen.
// Die Prüfung läuft auf dem Server, nur gegen öffentliche Adressen (SSRF-Schutz wie bei den Feeds) und folgt keinen Weiterleitungen.

const ELVADO_SVC_KINDS=[
    'website'=>'Website','api'=>'Schnittstelle (API)','media'=>'Medien / Cloud','podcast'=>'Podcast','video'=>'Video / Livestream',
    'mail'=>'E-Mail / Webmail','analytics'=>'Statistik','shop'=>'Shop','docs'=>'Dokumentation / Wiki','other'=>'Sonstiges',
];
const ELVADO_SVC_MAX=30;

/** Adresse: https://… oder http://… (ohne Zugangsdaten) oder ein Pfad auf dieser Website (/…). */
function elvado_services_url_clean($u): string {
    $u=trim((string)$u);
    if($u===''||strlen($u)>1000||preg_match('/[\s"\'<>]/',$u))return '';
    if(preg_match('~^/(?!/)[^\s]*$~',$u))return $u;
    if(!preg_match('~^https?://~i',$u))return '';
    $p=@parse_url($u);
    if(!is_array($p)||empty($p['host'])||isset($p['user'])||isset($p['pass']))return '';
    return $u;
}
/** Eigene Dienste bereinigen: Name und Adresse sind Pflicht, Kennungen werden eindeutig vergeben. */
function elvado_services_clean($v): array {
    $v=is_array($v)?$v:[];$items=[];$seen=[];
    foreach(array_slice((array)($v['items']??[]),0,ELVADO_SVC_MAX*2) as $s){
        if(!is_array($s))continue;
        $name=mb_substr(trim(strip_tags((string)($s['name']??''))),0,60);$url=elvado_services_url_clean($s['url']??'');
        if($name===''||$url==='')continue;
        $id=trim((string)preg_replace('/[^a-z0-9]+/','-',strtolower(strtr($name,['ä'=>'ae','ö'=>'oe','ü'=>'ue','Ä'=>'ae','Ö'=>'oe','Ü'=>'ue','ß'=>'ss']))),'-');
        $id=substr($id!==''?$id:'dienst',0,40);$base=$id;for($i=2;isset($seen[$id]);$i++)$id=substr($base,0,36).'-'.$i;
        $seen[$id]=1;$kind=(string)($s['kind']??'other');
        $items[]=['id'=>$id,'name'=>$name,'url'=>$url,'kind'=>isset(ELVADO_SVC_KINDS[$kind])?$kind:'other',
                  'note'=>mb_substr(trim(strip_tags((string)($s['note']??''))),0,200),'check'=>!array_key_exists('check',$s)||!empty($s['check'])];
        if(count($items)>=ELVADO_SVC_MAX)break;
    }
    return ['items'=>$items];
}
/** Vollständige Adresse eines Dienstes (Pfade werden auf die eigene Website bezogen). */
function elvado_services_target(string $url,string $origin): string { return $url!==''&&$url[0]==='/'?rtrim($origin,'/').$url:$url; }
/** Ergebnis einer Prüfung aus HTTP-Code und Curl-Fehler: online (auch geschützt oder weitergeleitet) oder offline. */
function elvado_services_state(int $http,int $errno): array {
    if($http>=200&&$http<400)return ['online',$http>=300?'erreichbar (Weiterleitung)':'erreichbar'];
    if($http===401||$http===403)return ['online','erreichbar (Zugriff geschützt)'];
    if($http>0)return ['offline','Fehler HTTP '.$http];
    return ['offline',match(true){$errno===6=>'Adresse nicht auflösbar',$errno===28=>'Zeitüberschreitung',$errno===60||$errno===35||$errno===51=>'Zertifikatsfehler',default=>'nicht erreichbar'}];
}
/**
 * Erreichbarkeit aller Dienste prüfen (parallel). Rückgabe je Dienst: id, name, url, kind, note, configured, check, state (online|offline|blocked|skipped), message, http, ms.
 * $fetch kann für Tests die Netzabfrage ersetzen: fn(string $url): [http, errno, ms].
 */
function elvado_services_probe(array $items,string $origin,int $timeout=5,?callable $fetch=null): array {
    $out=[];$jobs=[];
    foreach($items as $i=>$s){
        $row=['id'=>$s['id'],'name'=>$s['name'],'url'=>$s['url'],'kind'=>$s['kind'],'note'=>$s['note'],'configured'=>true,'check'=>!empty($s['check']),'state'=>'skipped','message'=>'wird nicht geprüft','http'=>0,'ms'=>0];
        if($row['check']){
            $t=elvado_services_target($s['url'],$origin);
            if(!function_exists('elvado_remote_feed_allowed'))require_once __DIR__.'/feeds.php';
            if(!elvado_remote_feed_allowed($t)){$row['state']='blocked';$row['message']='nicht prüfbar (nur öffentliche Adressen)';}
            else{$row['state']='pending';$jobs[$i]=$t;}
        }
        $out[$i]=$row;
    }
    $res=[];
    if($fetch!==null){ foreach($jobs as $i=>$t)$res[$i]=$fetch($t); }
    elseif($jobs&&function_exists('curl_multi_init')){
        $mh=curl_multi_init();$hs=[];$t0=[];
        foreach($jobs as $i=>$t){
            $ch=curl_init($t);
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>min(4,$timeout),CURLOPT_TIMEOUT=>$timeout,
                CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_USERAGENT=>elvado_product_name_safe().'-Dienstpruefung/1.0',CURLOPT_HTTPHEADER=>['Range: bytes=0-0'],
                CURLOPT_WRITEFUNCTION=>fn($c,$d)=>0]);   // nur Kopfzeilen: Antworttext nicht laden (Abbruch nach dem Antwortkopf ist gewollt)
            curl_multi_add_handle($mh,$ch);$hs[$i]=$ch;$t0[$i]=microtime(true);
        }
        do{ $st=curl_multi_exec($mh,$running);if($running)curl_multi_select($mh,0.2); }while($running&&$st===CURLM_OK);
        foreach($hs as $i=>$ch){
            $res[$i]=[(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE),curl_errno($ch),(int)round(((float)curl_getinfo($ch,CURLINFO_TOTAL_TIME))*1000)];
            curl_multi_remove_handle($mh,$ch);curl_close($ch);
        }
        curl_multi_close($mh);
    }
    foreach($jobs as $i=>$t){
        [$http,$errno,$ms]=$res[$i]??[0,0,0];
        if($http>0)$errno=0;   // Abbruch nach dem Antwortkopf ist kein Fehler
        [$state,$msg]=elvado_services_state((int)$http,(int)$errno);
        $out[$i]['state']=$state;$out[$i]['message']=$msg;$out[$i]['http']=(int)$http;$out[$i]['ms']=(int)$ms;
    }
    return array_values($out);
}
function elvado_product_name_safe(): string {
    if(!function_exists('elvado_product'))require_once __DIR__.'/product.php';
    return preg_replace('/[^A-Za-z0-9.-]/','',(string)(elvado_product()['name']??''))?:'CMS';
}

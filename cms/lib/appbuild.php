<?php
// Build-Assistent für eigene Android-Apps (CMS → Apps → „Eigene App bauen“).
// Gradle/Android-SDK laufen nicht auf normalem Webhosting. Der Assistent legt deshalb die Marken-Konfiguration (android/brands.json, Icon) in einem
// GitHub-Repository mit den App-Quellen ab, startet den Workflow „android-custom-brand.yml“ und zeigt Stand und Downloads (GitHub-Releases) im CMS.
// Die Apps selbst brauchen danach nur die Website (app_config) – kein Control Center, keine zentrale Infrastruktur.
// Das GitHub-Token liegt ausschließlich serverseitig (cms/data/.apps/build.json) und wird nie an den Browser geliefert.

if(!function_exists('rrw_pack_available'))require_once __DIR__.'/pack.php';
const RRW_AB_WORKFLOW='android-custom-brand.yml';   // Android (Standard-Plattform)
const RRW_AB_WORKFLOW_WIN='windows-custom-brand.yml';
const RRW_AB_WIN_PROJECT='windows-native/ElvadoPress.App.Windows.csproj';      // Projektdatei der Windows-App in der App-Vorlage (app-template/)
const RRW_AB_WIN_PROJECT_OLD='windows-native/RicoReWi.Radio.Windows.csproj';  // frühere Vorlage
// Plattformen: Workflow-Datei, Anfang des Lauf-Titels (run-name), Muster des Release-Tags und Dateiendungen der Pakete
const RRW_AB_PLATFORMS=['android'=>['label'=>'Android','workflow'=>RRW_AB_WORKFLOW,'title'=>'App ','tag'=>'app-%s-','tagre'=>'/^app-%s-\d+$/','ext'=>'apk|aab'],
                        'windows'=>['label'=>'Windows','workflow'=>RRW_AB_WORKFLOW_WIN,'title'=>'Windows ','tag'=>'app-%s-win-','tagre'=>'/^app-%s-win-\d+$/','ext'=>'exe']];
const RRW_AB_TYPES=['radio'=>'Radio-App','web'=>'Website-App','content'=>'Baukasten-App'];
const RRW_AB_RESERVED=['ricorewi','senderwelt','debug','release','developer','main','test','android','app'];
const RRW_AB_MAX_BRANDS=8;
const RRW_AB_MAX_SCREENSHOTS=8;

function rrw_ab_file(string $dataDir): string { return rrw_apps_dir($dataDir).'/build.json'; }
function rrw_ab_load(string $dataDir): array {
    $d=json_decode((string)@file_get_contents(rrw_ab_file($dataDir)),true);$d=is_array($d)?$d:[];
    return ['repo'=>(string)($d['repo']??''),'branch'=>(string)($d['branch']??'app-builder'),'token'=>(string)($d['token']??''),'brands'=>array_values(array_filter((array)($d['brands']??[]),'is_array'))];
}
function rrw_ab_save(string $dataDir, array $d): bool {
    $f=rrw_ab_file($dataDir);$t=$f.'.'.bin2hex(random_bytes(3)).'.tmp';
    if(@file_put_contents($t,json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX)===false)return false;
    @chmod($t,0600);return @rename($t,$f);
}
/** Zustand für die Oberfläche – ohne Token (nur „vorhanden“ und die letzten 4 Zeichen). */
function rrw_ab_state(string $dataDir): array {
    $d=rrw_ab_load($dataDir);
    return ['repo'=>$d['repo'],'branch'=>$d['branch'],'has_token'=>$d['token']!=='','token_hint'=>$d['token']!==''?'…'.substr($d['token'],-4):'','brands'=>$d['brands'],'workflow'=>RRW_AB_WORKFLOW,'max_brands'=>RRW_AB_MAX_BRANDS];
}

/* ───────── Eingaben prüfen ───────── */
function rrw_ab_clean_repo(string $r): string { $r=trim($r);return preg_match('~^[A-Za-z0-9_.-]{1,100}/[A-Za-z0-9_.-]{1,100}$~',$r)?$r:''; }
function rrw_ab_clean_branch(string $b): string { $b=trim($b);return preg_match('~^[A-Za-z0-9][A-Za-z0-9._/-]{0,60}$~',$b)&&!str_contains($b,'..')&&!str_ends_with($b,'/')&&!str_ends_with($b,'.lock')?$b:''; }
/** Eine Marke prüfen und bereinigen. Rückgabe [Marke|null, Fehlertext]. */
/** Bild aus der Medienbibliothek (Pfad unter /cms/media/, keine Umwege). */
function rrw_ab_media_url_ok(string $u): bool { return preg_match('~^/cms/media/[A-Za-z0-9_./-]+$~',$u)===1&&!str_contains($u,'..'); }
function rrw_ab_clean_brand(array $in, string $siteOrigin=''): array {
    $id=strtolower(trim((string)($in['id']??'')));
    if(!preg_match('/^[a-z][a-z0-9]{2,19}$/',$id))return [null,'Die Marken-ID besteht aus 3–20 Kleinbuchstaben/Ziffern und beginnt mit einem Buchstaben.'];
    if(in_array($id,RRW_AB_RESERVED,true))return [null,'Die Marken-ID „'.$id.'“ ist reserviert.'];
    // Gradle verbietet Product-Flavor-Namen, die mit „test“ oder „androidTest“ beginnen (sonst bricht der Build mit „ProductFlavor names cannot start with 'test'“ ab)
    if(str_starts_with($id,'test')||str_starts_with($id,'androidtest'))return [null,'Die Marken-ID darf nicht mit „test“ oder „androidtest“ beginnen (Android-Build-Regel). Bitte eine andere ID wählen.'];
    $pkg=strtolower(trim((string)($in['applicationId']??'')));
    if(!preg_match('/^[a-z][a-z0-9_]{0,30}(\.[a-z][a-z0-9_]{0,30}){1,4}$/',$pkg))return [null,'Der Paketname sieht aus wie de.meinradio.app (Kleinbuchstaben, mindestens zwei Teile mit Punkt).'];
    foreach(explode('.',$pkg) as $seg)if(in_array($seg,['abstract','assert','boolean','break','byte','case','catch','char','class','const','continue','default','do','double','else','enum','extends','final','finally','float','for','goto','if','implements','import','instanceof','int','interface','long','native','new','package','private','protected','public','return','short','static','strictfp','super','switch','synchronized','this','throw','throws','transient','try','void','volatile','while','true','false','null'],true))return [null,'Im Paketnamen ist „'.$seg.'“ ein reserviertes Wort.'];
    if(in_array($pkg,['de.ricorewi.radio','de.senderwelt.app'],true))return [null,'Dieser Paketname gehört einer anderen App.'];
    $name=trim(preg_replace('/\s+/u',' ',(string)($in['appName']??'')));
    if($name===''||mb_strlen($name)>30||!preg_match('/^[\p{L}\p{N} ._-]+$/u',$name))return [null,'Der App-Name hat 1–30 Zeichen (Buchstaben, Ziffern, Leerzeichen, Punkt, Bindestrich).'];
    $site=rtrim(trim((string)($in['site']??$siteOrigin)),'/');
    if(!preg_match('~^https://[a-z0-9]([a-z0-9.-]{0,120}[a-z0-9])?(:\d{2,5})?$~i',$site))return [null,'Die Website-Adresse muss mit https:// beginnen und darf keinen Pfad enthalten (z. B. https://www.meinradio.de).'];
    $prefix=trim((string)($in['filePrefix']??''));if($prefix==='')$prefix=preg_replace('/[^A-Za-z0-9-]+/','-',$name);
    $prefix=trim((string)preg_replace('/-+/','-',$prefix),'-');
    if(!preg_match('/^[A-Za-z0-9-]{2,30}$/',$prefix))return [null,'Der Dateiname-Anfang besteht aus 2–30 Buchstaben, Ziffern oder Bindestrichen.'];
    $type=(string)($in['type']??'radio');if(!isset(RRW_AB_TYPES[$type]))return [null,'Unbekannter App-Typ.'];
    $pl=array_values(array_unique(array_filter(array_map('strval',(array)($in['platforms']??['android'])),fn($x)=>isset(RRW_AB_PLATFORMS[$x]))));
    if(!$pl)return [null,'Bitte mindestens eine Plattform wählen (Android oder Windows).'];
    $theme=trim((string)($in['themeColor']??''));if($theme!==''&&!preg_match('/^#[0-9a-fA-F]{6}$/',$theme))return [null,'Die Farbe hat die Form #112233.'];
    $icon=trim((string)($in['icon']??''));
    if($icon!==''&&!rrw_ab_media_url_ok($icon))return [null,'Das Icon muss ein Bild aus der Medienbibliothek sein.'];
    $out=['id'=>$id,'applicationId'=>$pkg,'appName'=>$name,'site'=>$site,'launchUrl'=>$site.'/','filePrefix'=>$prefix,'directory'=>$type==='radio'&&!empty($in['directory'])&&rrw_pack_available(),'icon'=>$icon,'type'=>$type,'platforms'=>$pl,'themeColor'=>$theme];
    // Branding für alle App-Typen (nur gesetzte Werte werden gespeichert, bestehende Apps bleiben unverändert)
    $splash=trim((string)($in['splash']??''));
    if($splash!==''){ if(!rrw_ab_media_url_ok($splash))return [null,'Das Startbild muss ein Bild aus der Medienbibliothek sein.'];$out['splash']=$splash; }
    $hl=trim((string)($in['headerLogo']??''));
    if($hl!==''){ if(!rrw_ab_media_url_ok($hl))return [null,'Das Kopfzeilen-Logo muss ein Bild aus der Medienbibliothek sein.'];$out['headerLogo']=$hl; }
    $bg=trim((string)($in['iconBg']??''));
    if($bg!==''){ if(!preg_match('/^#[0-9a-fA-F]{6}$/',$bg))return [null,'Die Icon-Hintergrundfarbe hat die Form #112233.'];$out['iconBg']=strtolower($bg); }
    $shots=[];foreach(array_slice((array)($in['screenshots']??[]),0,RRW_AB_MAX_SCREENSHOTS*2) as $u){
        $u=trim((string)$u);if($u==='')continue;
        if(!rrw_ab_media_url_ok($u))return [null,'Screenshots müssen Bilder aus der Medienbibliothek sein.'];
        if(!in_array($u,$shots,true))$shots[]=$u;
    }
    if(count($shots)>RRW_AB_MAX_SCREENSHOTS)return [null,'Es sind höchstens '.RRW_AB_MAX_SCREENSHOTS.' Screenshots möglich.'];
    if($shots)$out['screenshots']=$shots;
    $short=mb_substr(trim(strip_tags((string)($in['shortDescription']??''))),0,80);if($short!=='')$out['shortDescription']=$short;
    $full=mb_substr(trim(strip_tags((string)($in['fullDescription']??''))),0,4000);if($full!=='')$out['fullDescription']=$full;
    return [$out,''];
}

/* ───────── GitHub ───────── */
/** HTTP-Anfrage an die GitHub-API (feste Domain). Für Tests kann $GLOBALS['rrw_ab_http'] die Anfrage ersetzen. */
function rrw_ab_http(string $method, string $path, string $token, ?array $json=null, array $extra=[], int $timeout=25): array {
    if(isset($GLOBALS['rrw_ab_http'])&&is_callable($GLOBALS['rrw_ab_http']))return ($GLOBALS['rrw_ab_http'])($method,$path,$token,$json,$extra);
    $url='https://api.github.com'.$path;$body=$json!==null?json_encode($json,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null;
    $h=array_merge(['Accept: application/vnd.github+json','X-GitHub-Api-Version: 2022-11-28','User-Agent: ElvadoPress-App-Builder','Authorization: Bearer '.$token],$extra);
    if($body!==null)$h[]='Content-Type: application/json';
    if(function_exists('curl_init')){
        $ch=curl_init($url);$hdr=[];
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>6,CURLOPT_TIMEOUT=>$timeout,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$h,
            CURLOPT_HEADERFUNCTION=>function($c,$l) use(&$hdr){ if(str_contains($l,':')){ [$k,$v]=explode(':',$l,2);$hdr[strtolower(trim($k))]=trim($v); }return strlen($l); }]);
        if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
        $raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
        return ['code'=>$code,'body'=>(string)$raw,'headers'=>$hdr,'error'=>$err];
    }
    $ctx=stream_context_create(['http'=>['method'=>$method,'timeout'=>$timeout,'ignore_errors'=>true,'follow_location'=>0,'header'=>implode("\r\n",$h),'content'=>$body??'']]);
    $raw=@file_get_contents($url,false,$ctx);$code=0;$hdr=[];
    foreach((array)($http_response_header??[]) as $l){ if(preg_match('#^HTTP/\S+\s+(\d{3})#',$l,$m))$code=(int)$m[1];elseif(str_contains($l,':')){ [$k,$v]=explode(':',$l,2);$hdr[strtolower(trim($k))]=trim($v); } }
    return ['code'=>$code,'body'=>(string)$raw,'headers'=>$hdr,'error'=>$raw===false?'request failed':''];
}
function rrw_ab_msg(array $r): string {
    $j=json_decode($r['body'],true);$m=is_array($j)?(string)($j['message']??''):'';
    return match(true){ $r['code']===0=>'GitHub ist gerade nicht erreichbar.',$r['code']===401=>'GitHub lehnt das Token ab (ungültig oder abgelaufen).',$r['code']===403=>'GitHub verweigert den Zugriff (Token-Rechte oder Limit). '.$m,$r['code']===404=>'Nicht gefunden – Repository, Branch oder Token-Rechte prüfen.',$r['code']===422=>'GitHub hat die Anfrage abgelehnt: '.$m,default=>'GitHub-Fehler '.$r['code'].($m!==''?': '.$m:'') };
}
function rrw_ab_enc(string $path): string { return implode('/',array_map('rawurlencode',explode('/',$path))); }

/** Repository prüfen: Zugriff, Schreibrecht, App-Quellen und Workflow vorhanden? Rückgabe ['ok'=>bool,'checks'=>[[ok,Text]…]]. */
function rrw_ab_check(string $dataDir): array {
    $d=rrw_ab_load($dataDir);$c=[];
    if($d['repo']===''||$d['token']==='')return ['ok'=>false,'checks'=>[[false,'Repository und Token eintragen und speichern.']]];
    $r=rrw_ab_http('GET','/repos/'.$d['repo'],$d['token']);
    if($r['code']!==200)return ['ok'=>false,'checks'=>[[false,rrw_ab_msg($r)]]];
    $j=json_decode($r['body'],true);$c[]=[true,'Repository gefunden: '.(string)($j['full_name']??$d['repo']).(!empty($j['private'])?' (privat)':' (öffentlich)')];
    $push=!empty($j['permissions']['push']);$c[]=[$push,$push?'Schreibrecht vorhanden':'Das Token darf nicht schreiben (Contents: Read and write nötig).'];
    $def=(string)($j['default_branch']??'main');
    $ok=$push;
    $needWin=false;foreach($d['brands'] as $b)if(in_array('windows',(array)($b['platforms']??[]),true))$needWin=true;
    $files=['android/brands.json'=>'App-Quellen (android/)','.github/workflows/'.RRW_AB_WORKFLOW=>'Workflow „'.RRW_AB_WORKFLOW.'“ (Android)'];
    if($needWin)$files+=[RRW_AB_WIN_PROJECT=>'Windows-Quellen (windows-native/)','.github/workflows/'.RRW_AB_WORKFLOW_WIN=>'Workflow „'.RRW_AB_WORKFLOW_WIN.'“ (Windows)'];
    foreach($files as $p=>$label){
        $x=rrw_ab_http('GET','/repos/'.$d['repo'].'/contents/'.rrw_ab_enc($p).'?ref='.rawurlencode($def),$d['token']);
        if($x['code']!==200&&$p===RRW_AB_WIN_PROJECT)$x=rrw_ab_http('GET','/repos/'.$d['repo'].'/contents/'.rrw_ab_enc(RRW_AB_WIN_PROJECT_OLD).'?ref='.rawurlencode($def),$d['token']);   // Repositories aus der früheren Vorlage
        $has=$x['code']===200;$c[]=[$has,$label.($has?' vorhanden':' fehlt – das Repository muss auf der App-Vorlage von ElvadoPress (Ordner app-template/) beruhen.')];$ok=$ok&&$has;
    }
    return ['ok'=>$ok,'checks'=>$c,'default_branch'=>$def];
}
/** Branch sicherstellen (aus dem Standard-Branch anlegen). Rückgabe Fehlertext oder null. */
function rrw_ab_ensure_branch(array $d): ?string {
    $repo='/repos/'.$d['repo'];
    $r=rrw_ab_http('GET',$repo.'/git/ref/heads/'.rrw_ab_enc($d['branch']),$d['token']);
    if($r['code']===200)return null;
    if($r['code']!==404)return rrw_ab_msg($r);
    $info=json_decode(rrw_ab_http('GET',$repo,$d['token'])['body'],true);$def=(string)($info['default_branch']??'main');
    $base=rrw_ab_http('GET',$repo.'/git/ref/heads/'.rrw_ab_enc($def),$d['token']);
    $sha=(string)(json_decode($base['body'],true)['object']['sha']??'');if($base['code']!==200||$sha==='')return rrw_ab_msg($base);
    $c=rrw_ab_http('POST',$repo.'/git/refs',$d['token'],['ref'=>'refs/heads/'.$d['branch'],'sha'=>$sha]);
    return $c['code']===201?null:rrw_ab_msg($c);
}
/** Datei im Branch anlegen/ersetzen. Rückgabe Fehlertext oder null. */
function rrw_ab_put_file(array $d, string $path, string $content, string $message): ?string {
    $p='/repos/'.$d['repo'].'/contents/'.rrw_ab_enc($path);
    $cur=rrw_ab_http('GET',$p.'?ref='.rawurlencode($d['branch']),$d['token']);$sha=$cur['code']===200?(string)(json_decode($cur['body'],true)['sha']??''):'';
    $body=['message'=>$message,'content'=>base64_encode($content),'branch'=>$d['branch']];if($sha!=='')$body['sha']=$sha;
    $r=rrw_ab_http('PUT',$p,$d['token'],$body);
    return in_array($r['code'],[200,201],true)?null:rrw_ab_msg($r);
}
/** brands.json im Branch lesen und die Marke ersetzen/ergänzen (andere Marken bleiben unberührt). Rückgabe [neuer Inhalt|null, Fehler]. */
function rrw_ab_merge_brands(array $d, array $brand): array {
    $cur=rrw_ab_http('GET','/repos/'.$d['repo'].'/contents/android/brands.json?ref='.rawurlencode($d['branch']),$d['token']);
    if($cur['code']!==200)return [null,rrw_ab_msg($cur)];
    $j=json_decode($cur['body'],true);$list=json_decode((string)base64_decode((string)($j['content']??''),true),true);
    if(!is_array($list))return [null,'android/brands.json im Repository ist unlesbar.'];
    $entry=['id'=>$brand['id'],'applicationId'=>$brand['applicationId'],'appName'=>$brand['appName'],'launchUrl'=>$brand['launchUrl'],'site'=>$brand['site'],'filePrefix'=>$brand['filePrefix']];
    if(!empty($brand['directory']))$entry['directory']=true;
    if(in_array(($brand['type']??'radio'),['web','content'],true))$entry['type']=$brand['type'];
    if(($brand['themeColor']??'')!=='')$entry['themeColor']=$brand['themeColor'];
    $out=[];$done=false;
    // Zusätzliche Angaben, die von Hand in brands.json stehen (z. B. "radio": {"podcast": true, "shops": […]}), bleiben beim Speichern erhalten
    $known=['id','applicationId','appName','launchUrl','site','filePrefix','directory','type','themeColor'];
    foreach($list as $b){ if(is_array($b)&&($b['id']??'')===$brand['id']){ foreach($b as $k=>$v)if(!in_array($k,$known,true)&&!array_key_exists($k,$entry))$entry[$k]=$v;$out[]=$entry;$done=true; }else $out[]=$b; }
    if(!$done)$out[]=$entry;
    return [json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n",''];
}
/** Bild aus der Medienbibliothek als PNG. $square: auf ein Quadrat einpassen (transparent aufgefüllt); sonst nur verkleinern. Rückgabe [PNG-Bytes|null, Fehler]. */
function rrw_ab_image_png(string $root, string $url, int $max=512, bool $square=true, int $min=96, string $label='Das Bild', string $bg=''): array {
    if($url==='')return ['',''];
    $base=realpath($root.'/cms/media');$file=realpath($root.$url);
    if(!$base||!$file||!str_starts_with($file,$base.DIRECTORY_SEPARATOR)||!is_file($file))return [null,$label.' wurde in der Medienbibliothek nicht gefunden.'];
    if(filesize($file)>8*1024*1024)return [null,$label.' ist zu groß (max. 8 MB).'];
    $raw=(string)file_get_contents($file);
    if(!function_exists('imagecreatefromstring')){ return str_starts_with($raw,"\x89PNG")&&strlen($raw)<1500000?[$raw,'']:[null,'Für die Umwandlung fehlt die PHP-Erweiterung GD – bitte ein PNG-Bild wählen.']; }
    $im=@imagecreatefromstring($raw);if(!$im)return [null,$label.' ist kein gültiges Bild (PNG, JPG oder WebP).'];
    $w=imagesx($im);$h=imagesy($im);if(min($w,$h)<$min){ imagedestroy($im);return [null,$label.' ist zu klein (mindestens '.$min.' Pixel an der kurzen Seite).']; }
    if($square){
        $s=min($max,max($w,$h));$out=imagecreatetruecolor($s,$s);imagealphablending($out,false);imagesavealpha($out,true);
        // Mit Hintergrundfarbe (#rrggbb): deckende Fläche, das Icon wird daraufgesetzt; sonst transparent
        if(preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i',$bg,$c)){ imagefill($out,0,0,imagecolorallocate($out,hexdec($c[1]),hexdec($c[2]),hexdec($c[3])));imagealphablending($out,true); }
        else imagefill($out,0,0,imagecolorallocatealpha($out,0,0,0,127));
        $k=min($s/$w,$s/$h);$nw=(int)round($w*$k);$nh=(int)round($h*$k);imagecopyresampled($out,$im,(int)(($s-$nw)/2),(int)(($s-$nh)/2),0,0,$nw,$nh,$w,$h);
    }else{
        $k=min(1,$max/max($w,$h));$nw=max(1,(int)round($w*$k));$nh=max(1,(int)round($h*$k));
        $out=imagecreatetruecolor($nw,$nh);imagealphablending($out,false);imagesavealpha($out,true);imagecopyresampled($out,$im,0,0,0,0,$nw,$nh,$w,$h);
    }
    ob_start();imagepng($out);$png=(string)ob_get_clean();imagedestroy($im);imagedestroy($out);
    return [$png,''];
}
/** Icon aus der Medienbibliothek als PNG (max. 512 px, quadratisch). Rückgabe [PNG-Bytes|null, Fehler]. */
function rrw_ab_icon_png(string $root, string $url, string $bg=''): array { return rrw_ab_image_png($root,$url,512,true,96,'Das gewählte Icon',$bg); }
/** Store-Texte (Markdown) für Play Store / Microsoft Store aus den Angaben der App. */
function rrw_ab_listing_md(array $brand): string {
    $md='# '.$brand['appName']."\n\n";
    if(!empty($brand['shortDescription']))$md.="## Kurzbeschreibung\n\n".$brand['shortDescription']."\n\n";
    if(!empty($brand['fullDescription']))$md.="## Beschreibung\n\n".$brand['fullDescription']."\n\n";
    return rtrim($md)."\n";
}
/** Marke ins Repository schreiben und den Build starten. $platform: android | windows | all (= alle für die App gewählten Plattformen). Rückgabe ['ok'=>bool,'message'=>…]. */
function rrw_ab_start(string $dataDir, string $root, string $id, string $platform='android'): array {
    $d=rrw_ab_load($dataDir);$brand=null;foreach($d['brands'] as $b)if(($b['id']??'')===$id)$brand=$b;
    if(!$brand)return ['ok'=>false,'message'=>'Diese App gibt es nicht.'];
    $have=array_values(array_filter((array)($brand['platforms']??['android']),fn($x)=>isset(RRW_AB_PLATFORMS[$x])))?:['android'];
    $targets=$platform==='all'?$have:[$platform];
    foreach($targets as $t)if(!isset(RRW_AB_PLATFORMS[$t])||!in_array($t,$have,true))return ['ok'=>false,'message'=>'Diese Plattform ist für die App nicht ausgewählt.'];
    if($d['repo']===''||$d['token']==='')return ['ok'=>false,'message'=>'Bitte zuerst Repository und Token eintragen.'];
    [$png,$err]=rrw_ab_icon_png($root,(string)($brand['icon']??''),(string)($brand['iconBg']??''));if($png===null)return ['ok'=>false,'message'=>$err];
    $e=rrw_ab_ensure_branch($d);if($e!==null)return ['ok'=>false,'message'=>$e];
    [$json,$err]=rrw_ab_merge_brands($d,$brand);if($json===null)return ['ok'=>false,'message'=>$err];
    $msg='App-Builder: '.$brand['appName'];
    if(($e=rrw_ab_put_file($d,'android/brands.json',$json,$msg))!==null)return ['ok'=>false,'message'=>$e];
    if($png!==''&&($e=rrw_ab_put_file($d,'brands/'.$brand['id'].'/app_logo.png',$png,$msg.' (Icon)'))!==null)return ['ok'=>false,'message'=>$e];
    // Branding (alle App-Typen): Startbild, Store-Screenshots und -Texte; nur wenn angegeben
    if(!empty($brand['splash'])){
        [$sp,$err]=rrw_ab_image_png($root,(string)$brand['splash'],1080,false,200,'Das Startbild');if($sp===null)return ['ok'=>false,'message'=>$err];
        if(($e=rrw_ab_put_file($d,'brands/'.$brand['id'].'/startscreen.png',$sp,$msg.' (Startbild)'))!==null)return ['ok'=>false,'message'=>$e];
    }
    if(!empty($brand['headerLogo'])){   // Logo in der Kopfzeile der App (logo-lockup.png, Android und Windows)
        [$hl,$err]=rrw_ab_image_png($root,(string)$brand['headerLogo'],1000,false,64,'Das Kopfzeilen-Logo');if($hl===null)return ['ok'=>false,'message'=>$err];
        if(($e=rrw_ab_put_file($d,'brands/'.$brand['id'].'/logo-lockup.png',$hl,$msg.' (Kopfzeilen-Logo)'))!==null)return ['ok'=>false,'message'=>$e];
    }
    foreach(array_values((array)($brand['screenshots']??[])) as $i=>$u){
        [$sh,$err]=rrw_ab_image_png($root,(string)$u,1600,false,200,'Der Screenshot '.($i+1));if($sh===null)return ['ok'=>false,'message'=>$err];
        if(($e=rrw_ab_put_file($d,'brands/'.$brand['id'].'/store/screenshot-'.($i+1).'.png',$sh,$msg.' (Screenshot '.($i+1).')'))!==null)return ['ok'=>false,'message'=>$e];
    }
    if(!empty($brand['shortDescription'])||!empty($brand['fullDescription'])){
        if(($e=rrw_ab_put_file($d,'brands/'.$brand['id'].'/store/listing-de.md',rrw_ab_listing_md($brand),$msg.' (Store-Texte)'))!==null)return ['ok'=>false,'message'=>$e];
    }
    $started=[];
    foreach($targets as $t){
        $wf=RRW_AB_PLATFORMS[$t]['workflow'];
        $r=rrw_ab_http('POST','/repos/'.$d['repo'].'/actions/workflows/'.$wf.'/dispatches',$d['token'],['ref'=>$d['branch'],'inputs'=>['brand'=>$brand['id']]]);
        if($r['code']!==204)return ['ok'=>false,'message'=>($r['code']===404?'Der Workflow „'.$wf.'“ fehlt im Branch „'.$d['branch'].'“. Branch löschen und neu bauen lassen, damit er aus dem Standard-Branch neu entsteht.':rrw_ab_msg($r)).($started?' (Bereits gestartet: '.implode(', ',$started).')':'')];
        $started[]=RRW_AB_PLATFORMS[$t]['label'];
    }
    return ['ok'=>true,'message'=>'Build gestartet für '.implode(' und ',$started).' (dauert etwa 5–10 Minuten).'];
}
/** Läufe und fertige Pakete (GitHub-Releases „app-<marke>-<nr>“ bzw. „app-<marke>-win-<nr>“) einer Marke, je Plattform. */
function rrw_ab_status(string $dataDir, string $id): array {
    $d=rrw_ab_load($dataDir);if($d['repo']===''||$d['token']===''||!preg_match('/^[a-z][a-z0-9]{2,19}$/',$id))return ['ok'=>false,'message'=>'Nicht eingerichtet.'];
    $brand=null;foreach($d['brands'] as $b)if(($b['id']??'')===$id)$brand=$b;
    $plats=$brand?array_values(array_filter((array)($brand['platforms']??['android']),fn($x)=>isset(RRW_AB_PLATFORMS[$x]))):['android'];if(!$plats)$plats=['android'];
    $runs=[];$files=[];
    foreach($plats as $pk){
        $P=RRW_AB_PLATFORMS[$pk];
        $r=rrw_ab_http('GET','/repos/'.$d['repo'].'/actions/workflows/'.$P['workflow'].'/runs?per_page=20&branch='.rawurlencode($d['branch']),$d['token']);
        if($r['code']===404)continue;   // Workflow dieser Plattform existiert (noch) nicht im Branch
        if($r['code']!==200)return ['ok'=>false,'message'=>rrw_ab_msg($r)];
        $n=0;foreach((array)(json_decode($r['body'],true)['workflow_runs']??[]) as $w){
            if(($w['display_title']??'')!==$P['title'].$id)continue;
            $runs[]=['platform'=>$pk,'id'=>(int)$w['id'],'status'=>(string)$w['status'],'conclusion'=>(string)($w['conclusion']??''),'created'=>(string)($w['created_at']??''),'url'=>(string)($w['html_url']??'')];
            if(++$n>=3)break;
        }
    }
    $rel=rrw_ab_http('GET','/repos/'.$d['repo'].'/releases?per_page=40',$d['token']);
    if($rel['code']===200)foreach((array)json_decode($rel['body'],true) as $x){
        $tag=(string)($x['tag_name']??'');
        foreach($plats as $pk){
            $P=RRW_AB_PLATFORMS[$pk];if(!preg_match(sprintf($P['tagre'],preg_quote($id,'/')),$tag))continue;
            foreach((array)($x['assets']??[]) as $a)if(preg_match('/\.('.$P['ext'].')$/i',(string)$a['name']))$files[]=['platform'=>$pk,'asset'=>(int)$a['id'],'name'=>(string)$a['name'],'size'=>(int)$a['size'],'created'=>(string)($a['created_at']??''),'tag'=>$tag];
        }
        if(count($files)>=10)break;
    }
    return ['ok'=>true,'runs'=>$runs,'files'=>$files];
}
/** Paket über das CMS ausliefern (auch aus privaten Repositories). Der Token wird nie an den Speicher-Host weitergegeben. */
function rrw_ab_download(string $dataDir, string $id, int $assetId): void {
    $d=rrw_ab_load($dataDir);$st=rrw_ab_status($dataDir,$id);
    $asset=null;if($st['ok'])foreach($st['files'] as $f)if($f['asset']===$assetId)$asset=$f;
    if(!$asset||$d['token']===''){ http_response_code(404);header('Content-Type: text/plain; charset=utf-8');echo 'Paket nicht gefunden.';return; }
    $r=rrw_ab_http('GET','/repos/'.$d['repo'].'/releases/assets/'.$assetId,$d['token'],null,['Accept: application/octet-stream'],20);
    $loc=$r['headers']['location']??'';
    if(!in_array($r['code'],[301,302,303,307],true)||!preg_match('~^https://[a-z0-9.-]+\.(githubusercontent\.com|github\.com|blob\.core\.windows\.net)/~i',$loc)){ http_response_code(502);header('Content-Type: text/plain; charset=utf-8');echo 'Download bei GitHub nicht möglich.';return; }
    $name=preg_replace('/[^A-Za-z0-9._-]/','_',$asset['name']);
    header('Content-Type: '.(str_ends_with(strtolower($asset['name']),'.exe')?'application/vnd.microsoft.portable-executable':'application/vnd.android.package-archive'));header('Content-Disposition: attachment; filename="'.$name.'"');header('X-Content-Type-Options: nosniff');header('Cache-Control: no-store');
    if(function_exists('curl_init')){
        $ch=curl_init($loc);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>false,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>6,CURLOPT_TIMEOUT=>300,CURLOPT_HTTPHEADER=>['User-Agent: ElvadoPress-App-Builder'],
            CURLOPT_WRITEFUNCTION=>function($c,$data){ echo $data;return strlen($data); }]);
        curl_exec($ch);curl_close($ch);
    }else{ $fh=@fopen($loc,'rb');if($fh){ while(!feof($fh))echo fread($fh,65536);fclose($fh); } }
}

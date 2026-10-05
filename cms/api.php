<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$root = dirname(__DIR__);
if(is_file(__DIR__.'/lib/demo.json')){ require_once __DIR__.'/lib/demo.php';rrw_demo_boot(); }   // Demo-Betrieb (nur mit cms/lib/demo.json)
$dataDir = __DIR__ . '/data';
$genDir = __DIR__ . '/generated';
$mediaDir = __DIR__ . '/media';
$siteFile = $dataDir . '/site.json';
$newsFile = $dataDir . '/news.json';
$commentsFile = $dataDir . '/comments.json';
$revisionsFile = $dataDir . '/news-revisions.json';
$notificationsFile = $dataDir . '/notifications.json';
$newsViewsFile = $dataDir . '/news-views.json';
$activityLogFile = $dataDir . '/activity-log.json';

function rrw_json($data, int $code=200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function rrw_body(): array {
    static $b=null; if($b!==null)return $b;
    $raw=(string)file_get_contents('php://input');
    $j=json_decode($raw,true); return $b=is_array($j)?$j:[];
}
function rrw_token(): string {
    $h=$_SERVER['HTTP_X_ANMACHA_TOKEN'] ?? '';
    if($h!=='')return trim($h);
    $b=rrw_body(); return trim((string)($b['_tok']??$_GET['_tok']??''));
}
function rrw_auth(bool $super=false): array {
    $tok=rrw_token();
    if($tok==='')rrw_json(['status'=>'error','message'=>'Nicht eingeloggt'],401);
    if(str_starts_with($tok,'local_')){
        $sess=rrw_local_session_validate($tok);
        if($sess===null)rrw_json(['status'=>'error','message'=>'Sitzung abgelaufen, bitte erneut anmelden'],401);
        $isAdmin=($sess['role']??'admin')==='admin';
        if($super&&!$isAdmin)rrw_json(['status'=>'error','message'=>'Nur Administratoren dürfen diese Aktion ausführen'],403);
        return ['allowed'=>true,'superadmin'=>$isAdmin,'role'=>$sess['role']??'admin','user'=>$sess['username'],'display_name'=>$sess['display_name']??$sess['username'],'source'=>'local'];
    }
    // Eigenständiger Betrieb: nur lokale Anmeldung, keinerlei Anfrage ans Control Center.
    if(rrw_standalone())rrw_json(['status'=>'error','message'=>'Sitzung abgelaufen, bitte lokal anmelden (Control-Center-Anmeldung ist im eigenständigen Betrieb ausgeschaltet)'],401);
    // Control-Center-Token: zuerst lokal verifizieren (siehe rrw_control_center_verify_token_local()
    // und rrw_control_center_local_identity() weiter unten) - identische kryptographische Prüfung wie
    // im Control Center selbst (gleiches Session-Secret, gleiche HMAC-Signatur, gleiche Tabellen für
    // Rolle/Rechte). Das spart den HTTP-Roundtrip zum Control Center, wo immer er lokal entbehrlich
    // ist. Ist die Secret-Datei vorhanden, aber das Token ungültig/abgelaufen, ist das ein endgültiges,
    // lokal sicher festgestelltes Ergebnis: sofort ablehnen, nicht erst noch per HTTP nachfragen.
    // Kann die Identität lokal nicht aufgelöst werden (Secret fehlt oder - wie auf dem Live-Hosting -
    // keine lesbare Rechte-DB), entscheidet das Control Center per HTTP (siehe unten).
    $secretAvailable=rrw_control_center_secret_available();
    $name=$secretAvailable?rrw_control_center_verify_token_local($tok):null;
    if($name!==null){
        $identity=rrw_control_center_local_identity($name);
        if($identity!==null){
            if(empty($identity['allowed']))rrw_json(['status'=>'error','message'=>'Keine CMS-Berechtigung'],403);
            if($super&&empty($identity['superadmin']))rrw_json(['status'=>'error','message'=>'Nur Superadmins dürfen diese Aktion ausführen'],403);
            $identity['role']=!empty($identity['superadmin'])?'admin':'autor';
            $identity['source']='control-center';
            return $identity;
        }
    } elseif($secretAvailable){
        rrw_json(['status'=>'error','message'=>'Sitzung abgelaufen, bitte erneut anmelden'],401);
    }
    // HTTP-Berechtigungsprüfung beim Control Center. Die Live-Messung im Website-Zustand hat
    // gezeigt: Der Selbstaufruf ist auf diesem Hosting NICHT blockiert - das Control Center
    // antwortet auf ein ungültiges/abgelaufenes Token schlicht mit HTTP 401. Dieser Code wurde
    // hier bisher wie ein Verbindungsfehler behandelt ("nicht erreichbar", 503), weshalb eine
    // abgelaufene Sitzung nie zur Neuanmeldung führte, sondern wie ein toter Server aussah.
    // Das Ergebnis wird kurz zwischengespeichert (rrw_control_center_auth_cache_*): Das Dashboard
    // feuert bis zu zehn API-Anfragen gleichzeitig, und jede davon würde sonst einen eigenen
    // Selbstaufruf starten - jede blockiert dabei einen PHP-Worker UND belegt einen zweiten für
    // die Antwort. Auf Shared Hosting mit wenigen Workern laufen diese Anfragen gegenseitig in
    // den Timeout ("Berechtigungsprüfung nicht erreichbar", "Statistik nicht verfügbar"), obwohl
    // ein einzelner Aufruf in unter einer Sekunde antwortet. Jetzt holt nur die erste Anfrage
    // die Entscheidung, alle parallelen warten an einer Sperre auf dasselbe Ergebnis.
    $d=rrw_control_center_auth_cache_get($tok);
    if($d===null){
        $lock=rrw_control_center_auth_lock($tok);
        $d=rrw_control_center_auth_cache_get($tok);
        if($d===null){
            $url='https://www.ricorewi-radio.de/control/cron.php?action=radio_cms_access&_tok='.rawurlencode($tok).'&_='.time();
            $raw=false;$httpCode=0;
            if(function_exists('curl_init')){
                $r=rrw_curl_fetch($url,8,3);
                if(!$r['ok'])$httpCode=(int)$r['code']; else $raw=$r['body'];
            } else $raw=@file_get_contents($url);
            if($lock)rrw_control_center_auth_unlock($lock);
            if($httpCode===401)rrw_json(['status'=>'error','message'=>'Sitzung abgelaufen, bitte erneut anmelden'],401);
            if($httpCode===403)rrw_json(['status'=>'error','message'=>'Keine CMS-Berechtigung'],403);
            if($httpCode!==0||$raw===false)rrw_json(['status'=>'error','message'=>'Berechtigungsprüfung nicht erreichbar'.($httpCode>0?' (HTTP '.$httpCode.')':'')],503);
            $d=json_decode((string)$raw,true);
            if(!is_array($d)||empty($d['allowed']))rrw_json(['status'=>'error','message'=>'Keine CMS-Berechtigung'],403);
            rrw_control_center_auth_cache_put($tok,$d);
        } elseif($lock) rrw_control_center_auth_unlock($lock);
    }
    if($super&&empty($d['superadmin']))rrw_json(['status'=>'error','message'=>'Nur Superadmins dürfen diese Aktion ausführen'],403);
    // Das Control Center kennt nur superadmin/nicht-superadmin, keine feingranularen CMS-Rollen:
    // Superadmins gelten hier als 'admin', alle anderen als 'autor'. Seit der zugehörigen Änderung
    // im Control-Center-Repo (anmacha_control_center, radio_cms_access) liefert es zusätzlich 'user'
    // (stabiler Login-Name) und 'display_name' mit, damit Besitzrechte (rrw_news_can_edit) auch
    // Control-Center-Autoren korrekt auseinanderhalten können statt sie alle auf eine generische
    // Sammelidentität abzubilden. Der Fallback bleibt als Schutz, falls eine ältere Control-Center-
    // Version (vor diesem Feld) im Einsatz ist.
    $d['role']=!empty($d['superadmin'])?'admin':'autor';
    $d['user']=(string)($d['user']??$d['username']??(rrw_pack_available()?'AnMaCha Redaktion':rrw_product_name()));
    $d['display_name']=(string)($d['display_name']??$d['user']);
    $d['source']='control-center';
    return $d;
}
// Gemeinsamer curl-Helfer für alle Selbstaufrufe dieses Servers auf seine eigene Domain
// (Control-Center-Anbindung über https://www.ricorewi-radio.de/control/cron.php). Erster Versuch
// direkt über 127.0.0.1 (kein Umweg über das öffentliche Netz; Host-Header/SNI bleiben korrekt,
// sodass vHost-Auswahl und TLS-Zertifikatsprüfung dieselbe echte Domain betreffen), bei einem
// Verbindungsfehler ganz normal über DNS. Laut Live-Messung im Website-Zustand funktionieren auf
// diesem Hosting beide Wege.
// Diagnose für den Website-Zustand: Warum scheitert der Selbstaufruf des Control Centers auf
// diesem Hosting? Führt beide Strategien von rrw_curl_fetch() (Loopback 127.0.0.1, normales DNS)
// mit kurzen Timeouts einmal aus und meldet je Versuch HTTP-Code, Ziel-IP und den konkreten
// curl-Fehler. Ergebnis wird 10 Minuten zwischengespeichert, damit ein Dashboard-Aufruf auf einem
// blockierten Hosting nicht jedes Mal in die Timeouts läuft; "Prüfen" erzwingt eine neue Messung.
function rrw_control_center_selfcall_probe(bool $force=false): array {
    $cache=__DIR__.'/data/.cc-selfcall.json';
    if(!$force&&is_file($cache)){$c=json_decode((string)@file_get_contents($cache),true);if(is_array($c)&&(time()-(int)($c['at']??0))<600)return $c;}
    $url='https://www.ricorewi-radio.de/control/cron.php?action=radio_cms_access&_tok=probe&_='.time();
    $attempts=[];
    foreach([['via'=>'127.0.0.1','resolve'=>['www.ricorewi-radio.de:443:127.0.0.1']],['via'=>'DNS','resolve'=>[]]] as $a){
        if(!function_exists('curl_init')){$attempts[]=['via'=>$a['via'],'ok'=>false,'http'=>0,'ip'=>'','error'=>'curl-Erweiterung fehlt','body'=>''];continue;}
        $ch=curl_init($url);
        $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>4,CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_HTTPHEADER=>['Accept: application/json']];
        if($a['resolve'])$opts[CURLOPT_RESOLVE]=$a['resolve'];
        curl_setopt_array($ch,$opts);
        $raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$errno=curl_errno($ch);$err=curl_error($ch);$ip=(string)curl_getinfo($ch,CURLINFO_PRIMARY_IP);curl_close($ch);
        // Erreichbar heißt: eine HTTP-Antwort des Control Centers. Auf das Probe-Token antwortet es
        // korrekt mit 401 - das ist ein funktionierendes Sicherheitsnetz, kein Verbindungsfehler.
        $attempts[]=['via'=>$a['via'],'ok'=>$raw!==false&&(($code>=200&&$code<300)||$code===401||$code===403),'http'=>$code,'ip'=>$ip,'error'=>$err!==''?'curl #'.$errno.': '.$err:'','body'=>is_string($raw)?trim(substr(strip_tags($raw),0,100)):''];
    }
    $out=['at'=>time(),'ok'=>count(array_filter($attempts,fn($x)=>$x['ok']))>0,'attempts'=>$attempts];
    @file_put_contents($cache,json_encode($out));
    return $out;
}
// Kurzzeit-Cache für die per HTTP beim Control Center getroffene Zugriffsentscheidung (siehe
// rrw_auth()). Schlüssel ist ein Hash des Tokens, nie das Token selbst; gespeichert wird nur die
// Antwort des Control Centers (allowed/superadmin/user/display_name). Gültigkeit 5 Minuten - ein
// im Control Center entzogenes Recht wirkt damit spätestens nach 5 Minuten, ein abgelaufenes Token
// wird ohnehin vorher lokal an seiner Ablaufzeit erkannt. Verneinende Antworten werden nicht
// gespeichert. Alte Einträge räumt der nächste Schreibzugriff weg.
function rrw_control_center_auth_cache_dir(): string { $d=__DIR__.'/data/.cc-auth'; if(!is_dir($d))@mkdir($d,0750,true); return $d; }
function rrw_control_center_auth_cache_file(string $tok): string { return rrw_control_center_auth_cache_dir().'/'.hash('sha256',$tok).'.json'; }
function rrw_control_center_auth_cache_get(string $tok): ?array {
    $f=rrw_control_center_auth_cache_file($tok);
    if(!is_file($f))return null;
    $c=json_decode((string)@file_get_contents($f),true);
    if(!is_array($c)||(time()-(int)($c['at']??0))>300||empty($c['data']['allowed']))return null;
    return $c['data'];
}
function rrw_control_center_auth_cache_put(string $tok,array $d): void {
    $dir=rrw_control_center_auth_cache_dir();
    $keep=['allowed','superadmin','user','username','display_name'];
    $slim=array_intersect_key($d,array_flip($keep));
    @file_put_contents(rrw_control_center_auth_cache_file($tok),json_encode(['at'=>time(),'data'=>$slim]),LOCK_EX);
    foreach((array)@glob($dir.'/*.json') as $old)if(@filemtime($old)<time()-3600)@unlink($old);
    foreach((array)@glob($dir.'/*.lock') as $old)if(@filemtime($old)<time()-600)@unlink($old);
}
function rrw_control_center_auth_lock(string $tok) {
    $h=@fopen(rrw_control_center_auth_cache_dir().'/'.hash('sha256',$tok).'.lock','c');
    if($h===false)return null;
    if(!@flock($h,LOCK_EX)){fclose($h);return null;}
    return $h;
}
function rrw_control_center_auth_unlock($h): void { if($h){@flock($h,LOCK_UN);fclose($h);} }
function rrw_curl_fetch(string $url,int $timeout=8,int $connectTimeout=4): array {
    $host=parse_url($url,PHP_URL_HOST)?:'';
    $port=parse_url($url,PHP_URL_PORT)?:(parse_url($url,PHP_URL_SCHEME)==='https'?443:80);
    $attempts=$host!==''?[["$host:$port:127.0.0.1"],[]]:[[]];
    foreach($attempts as $resolve){
        $ch=curl_init($url);
        $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>$timeout,CURLOPT_CONNECTTIMEOUT=>$connectTimeout,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_HTTPHEADER=>['Accept: application/json']];
        if($resolve)$opts[CURLOPT_RESOLVE]=$resolve;
        curl_setopt_array($ch,$opts);
        $raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if($raw!==false&&$code>=200&&$code<300)return ['ok'=>true,'code'=>$code,'body'=>$raw];
        // Eine echte HTTP-Antwort (auch 401/403) heißt: Verbindung steht, die Gegenseite hat
        // entschieden. Dann nicht noch die zweite Strategie probieren, sondern den Code melden -
        // der Aufrufer unterscheidet damit "Token ungültig" von "nicht erreichbar".
        if($raw!==false&&$code>0)return ['ok'=>false,'code'=>$code,'body'=>$raw];
    }
    return ['ok'=>false,'code'=>0,'body'=>null];
}
// Für unauthentifizierte, öffentliche Abrufe (z.B. den News-Legacy-Fallback): liefert null statt
// eine Exception zu werfen, damit ein nicht erreichbares Control Center die öffentliche
// News-Auflistung nicht mit einem Serverfehler abschießt, sondern einfach leer bleibt.
function rrw_fetch_json_url(string $url,int $timeout=8): ?array {
    if(function_exists('curl_init')){
        $r=rrw_curl_fetch($url,$timeout);
        if(!$r['ok'])return null;
        $raw=$r['body'];
    } else {
        $raw=@file_get_contents($url);
        if($raw===false)return null;
    }
    $d=json_decode((string)$raw,true);
    return is_array($d)?$d:null;
}
// Liest veröffentlichte News direkt aus der SQLite-Datei des Control Centers (liegt als
// Geschwisterverzeichnis "control/" im selben Deploy-Pfad wie "cms/"). Vermeidet den
// HTTP-Umweg über die eigene Domain, der auf diesem Shared-Hosting-Setup offenbar blockiert
// wird oder ins Leere läuft (Loopback des Servers auf sich selbst) — direkter Dateizugriff
// ist zuverlässiger und ressourcensparender als ein Netzwerk-Roundtrip auf sich selbst.
// Lesender SQLite-Zugriff auf die Control-Center-Datenbank über den Treiber, den das Hosting
// tatsächlich bereitstellt: pdo_sqlite ODER die SQLite3-Klasse. Auf dem Live-Server fehlt
// pdo_sqlite (sichtbar im Website-Zustand) - eine reine PDO-Anbindung ließ dort jede lokale
// Rechteermittlung für Control-Center-Logins stillschweigend scheitern. Rückgabe null, wenn
// Datei oder Treiber fehlen oder die Abfrage fehlschlägt; niemals eine geratene Antwort.
function rrw_control_center_db_driver(): string {
    if(extension_loaded('pdo_sqlite')&&class_exists('PDO'))return 'pdo_sqlite';
    if(extension_loaded('sqlite3')&&class_exists('SQLite3'))return 'sqlite3';
    return '';
}
function rrw_control_center_db_rows(string $sql,array $params=[]): ?array {
    if(rrw_standalone())return null;
    $dbFile=__DIR__.'/../control/radio_stats_crazy.sqlite';
    if(!is_file($dbFile)||!is_readable($dbFile))return null;
    try{
        $driver=rrw_control_center_db_driver();
        if($driver==='pdo_sqlite'){
            $pdo=new PDO('sqlite:'.$dbFile,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            $q=$pdo->prepare($sql);$q->execute(array_values($params));
            $rows=$q->fetchAll(PDO::FETCH_ASSOC);return is_array($rows)?$rows:[];
        }
        if($driver==='sqlite3'){
            $db=new SQLite3($dbFile,SQLITE3_OPEN_READONLY);$db->busyTimeout(2000);
            $st=$db->prepare($sql);if($st===false){$db->close();return null;}
            foreach(array_values($params) as $i=>$v)$st->bindValue($i+1,$v);
            $res=$st->execute();if($res===false){$db->close();return null;}
            $rows=[];while(($row=$res->fetchArray(SQLITE3_ASSOC))!==false)$rows[]=$row;
            $db->close();return $rows;
        }
    }catch(Throwable $e){}
    return null;
}
function rrw_fetch_legacy_news_local(): ?array {
    return rrw_control_center_db_rows("SELECT id,slug,title,excerpt,body_html,category,image_url,external_url,status,featured,author,published_at,created_at,updated_at,tags,video_url,embed_html,image_mode FROM anmacha_news_articles WHERE status='published' ORDER BY COALESCE(published_at,created_at) DESC,id DESC");
}
// Verifiziert ein AnMaCha-Session-Token lokal, ohne HTTP-Aufruf ans Control Center: identische
// Prüfung wie anmachaVerifySessionToken() im Control-Center-Repo (anmacha_control_center/cron.php) -
// payload.signatur, HMAC-SHA256(payload, Session-Secret), Ablaufzeit. Das Session-Secret liegt als
// Datei control/.session_secret im selben Deploy-Pfad (0600, vom Control Center selbst erzeugt) -
// derselbe geheime Schlüssel, dieselbe kryptographische Prüfung, nur ohne den auf diesem Hosting
// blockierten Selbstaufruf über die öffentliche Domain. Fail closed: jeder Fehler (Datei fehlt,
// Signatur ungültig, Token abgelaufen/kaputt) liefert null, niemals eine geratene/leere Identität.
function rrw_control_center_secret_available(): bool {
    return is_file(__DIR__.'/../control/.session_secret');
}
function rrw_control_center_verify_token_local(string $token): ?string {
    if($token===''||!str_contains($token,'.'))return null;
    $secretFile=__DIR__.'/../control/.session_secret';
    if(!is_file($secretFile))return null;
    $secret=trim((string)@file_get_contents($secretFile));
    if($secret==='')return null;
    [$payload,$sig]=explode('.',$token,2);
    $expected=hash_hmac('sha256',$payload,$secret);
    if(!hash_equals($expected,$sig))return null;
    $data=json_decode((string)base64_decode($payload,true),true);
    if(!is_array($data)||empty($data['n'])||empty($data['e']))return null;
    if((int)$data['e']<time())return null;
    return strtolower((string)$data['n']);
}
// Liest Rolle/Rechte/Anzeigename zum bereits verifizierten Nutzernamen direkt aus der
// Control-Center-SQLite-Datenbank (control/radio_stats_crazy.sqlite, siehe
// rrw_fetch_legacy_news_local()) - repliziert isSuperadmin()/anmachaRadioCmsCanAccess() aus dem
// Control-Center-Repo 1:1 (gleiche Tabellen, gleiche Bedingungen, rein lesend).
function rrw_control_center_local_identity(string $name): ?array {
    $users=rrw_control_center_db_rows('SELECT role,stations,nickname,display_name FROM users WHERE lautfm_name=? LIMIT 1',[$name]);
    // Der Inhaber-Account ist im Control Center selbst allein per Namen Superadmin (isSuperadmin()
    // entscheidet das vor jedem Datenbankzugriff). Das Token wurde bereits kryptographisch gegen
    // das Session-Secret verifiziert, der Name ist also belegt - deshalb bleibt dieser Login auch
    // dann lokal auflösbar, wenn die Rechte-Datenbank (noch) nicht lesbar ist.
    if($users===null)return $name==='ricorewi'?['allowed'=>true,'superadmin'=>true,'user'=>$name,'display_name'=>$name]:null;
    $u=$users[0]??null;
    $superadmin=($name==='ricorewi')||($u&&($u['role']??'')==='superadmin');
    if(!$superadmin&&$u){
        $stations=json_decode((string)($u['stations']??'[]'),true);
        if(is_array($stations))foreach($stations as $st){
            $sn=strtolower(is_array($st)?(string)($st['name']??''):(string)$st);
            if($sn==='ricorewi'){$superadmin=true;break;}
        }
    }
    $allowed=$superadmin;
    if(!$allowed){
        $f=rrw_control_center_db_rows("SELECT enabled FROM anmacha_feature_access WHERE LOWER(user_name)=? AND feature='radio_cms' LIMIT 1",[$name]);
        if($f===null)return null;
        $allowed=((int)($f[0]['enabled']??0))===1;
    }
    $displayName=$u?(string)($u['nickname']?:($u['display_name']?:$name)):$name;
    return ['allowed'=>$allowed,'superadmin'=>$superadmin,'user'=>$name,'display_name'=>$displayName];
}
function rrw_control_center_json(string $action,string $token): array {
    if(rrw_standalone())throw new RuntimeException(rrw_standalone_notice('Der Zugriff auf das '.rrw_product_control_center()));
    $url='https://www.ricorewi-radio.de/control/cron.php?action='.rawurlencode($action).'&_tok='.rawurlencode($token).'&_='.time();
    if(function_exists('curl_init')){
        $r=rrw_curl_fetch($url,12,4);
        $raw=$r['ok']?$r['body']:false;
    } else {$raw=@file_get_contents($url);}
    $d=json_decode((string)$raw,true);
    if(!is_array($d)||($d['status']??'error')!=='ok')throw new RuntimeException('Legacy-Export konnte nicht gelesen werden');
    return $d;
}
function rrw_upload(string $bucket,int $max=12582912): string {
    if(empty($_FILES['file'])||!is_uploaded_file($_FILES['file']['tmp_name']))rrw_json(['status'=>'error','message'=>'Keine Datei'],400);
    $f=$_FILES['file'];if(($f['size']??0)<=0||$f['size']>$max)rrw_json(['status'=>'error','message'=>'Datei zu groß'],400);
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$map=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif','image/svg+xml'=>'svg','image/x-icon'=>'ico','image/vnd.microsoft.icon'=>'ico','application/octet-stream'=>'ico'];
    if(!isset($map[$mime]))rrw_json(['status'=>'error','message'=>'Nicht unterstütztes Bildformat'],400);
    if($mime==='image/svg+xml'&&preg_match('/<(script|foreignObject)\b|on[a-z]+\s*=|javascript:/i',(string)file_get_contents($f['tmp_name'])))rrw_json(['status'=>'error','message'=>'Unsicheres SVG'],400);
    $dir=__DIR__.'/media/'.$bucket;if(!is_dir($dir))@mkdir($dir,0755,true);$name=date('Ymd_His').'_'.bin2hex(random_bytes(5)).'.'.$map[$mime];if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$name))rrw_json(['status'=>'error','message'=>'Upload fehlgeschlagen'],500);@chmod($dir.'/'.$name,0644);return '/cms/media/'.$bucket.'/'.$name;
}
require_once __DIR__.'/lib/media.php';
// Bibliothek-Upload (HTTP): Prüfungen und Ablage liegen in cms/lib/media.php (rrw_media_library_store), hier nur die Antwort-Hülle.
function rrw_media_library_upload_variants(array $sizes,int $quality=86): array {
    if(empty($_FILES['file'])||!is_uploaded_file($_FILES['file']['tmp_name']))rrw_json(['status'=>'error','message'=>'Keine Datei'],400);
    try{return rrw_media_library_store($_FILES['file'],$sizes,$quality);}
    catch(RuntimeException $e){rrw_json(['status'=>'error','message'=>$e->getMessage()],$e->getCode()>=400?$e->getCode():400);}
}
function rrw_sync_branding_asset(string $kind,string $url,string $root): array {
    $rel=str_starts_with($url,'/cms/')?substr($url,4):'';$src=$rel!==''?__DIR__.$rel:'';
    if($src===''||!is_file($src))return ['url'=>$url,'files'=>[],'warnings'=>['Quelldatei konnte lokal nicht aufgelöst werden']];
    $ext=strtolower((string)pathinfo($src,PATHINFO_EXTENSION));$mime=function_exists('mime_content_type')?(string)@mime_content_type($src):'';$targets=[];$public=$url;$warnings=[];$written=[];
    $pngTargets=[];
    if($kind==='portal_logo')$pngTargets[]=['path'=>$root.'/logo-lockup.png','width'=>1400];
    if($kind==='portal_icon'){$pngTargets[]=['path'=>$root.'/icon-512.png','width'=>512];$pngTargets[]=['path'=>$root.'/icon-192.png','width'=>192];}
    if($kind==='favicon'){$pngTargets[]=['path'=>$root.'/favicon.png','width'=>192];}
    if($kind==='android_inapp_logo')$pngTargets[]=['path'=>$root.'/android-app/assets/config/logo-lockup.png','width'=>1400];
    if($kind==='android_startscreen')$pngTargets[]=['path'=>$root.'/android-app/assets/config/startscreen.png','width'=>1600];
    if($kind==='android_app_icon')$pngTargets[]=['path'=>$root.'/android-app/assets/config/app_icon.png','width'=>512];
    if($kind==='windows_logo')$pngTargets[]=['path'=>$root.'/android-app/assets/config/logo-lockup.png','width'=>1400];
    foreach($pngTargets as $t){
        $dir=dirname($t['path']);if(!is_dir($dir)&&!@mkdir($dir,0755,true)){$warnings[]='Ordner nicht beschreibbar: '.$dir;continue;}
        $ok=false;
        if($ext==='png'&&$t['width']>=4096)$ok=@copy($src,$t['path']);
        else $ok=rrw_resize_image_file($src,$mime,(int)$t['width'],$t['path'],92,'png');
        if(!$ok&&$ext==='png')$ok=@copy($src,$t['path']);
        if($ok){@chmod($t['path'],0644);$written[]=str_replace($root,'',$t['path']);}else $warnings[]='Konnte Ziel nicht erzeugen: '.str_replace($root,'',$t['path']);
    }
    if($kind==='portal_icon'&&in_array('/icon-512.png',$written,true))$public='/icon-512.png';
    if($kind==='favicon'&&in_array('/favicon.png',$written,true))$public='/favicon.png';
    if($kind==='portal_logo'&&in_array('/logo-lockup.png',$written,true))$public='/logo-lockup.png';
    if($kind==='android_inapp_logo'&&in_array('/android-app/assets/config/logo-lockup.png',$written,true))$public='/android-app/assets/config/logo-lockup.png';
    if($kind==='android_startscreen'&&in_array('/android-app/assets/config/startscreen.png',$written,true))$public='/android-app/assets/config/startscreen.png';
    if($kind==='android_app_icon'&&in_array('/android-app/assets/config/app_icon.png',$written,true))$public='/android-app/assets/config/app_icon.png';
    if($kind==='windows_logo'&&in_array('/android-app/assets/config/logo-lockup.png',$written,true))$public='/android-app/assets/config/logo-lockup.png';
    return ['url'=>$public,'files'=>$written,'warnings'=>$warnings];
}
function rrw_plugin_id(string $s): string {
    $s=strtolower(trim($s));$s=preg_replace('/[^a-z0-9_-]+/','-',$s);$s=trim((string)$s,'-');return substr($s!==''?$s:'plugin',0,64);
}
function rrw_plugin_catalog(array $site): array {
    $base=__DIR__.'/plugins';$active=array_fill_keys(array_map('strval',(array)($site['plugins']??[])),true);$out=[];
    if(!is_dir($base))return [];
    foreach(glob($base.'/*',GLOB_ONLYDIR)?:[] as $dir){
        $file=$dir.'/plugin.json';if(!is_file($file))continue;$m=json_decode((string)file_get_contents($file),true);if(!is_array($m))continue;
        $id=rrw_plugin_id((string)($m['id']??basename($dir)));if($id==='')continue;
        $out[]=[
            'id'=>$id,'name'=>(string)($m['name']??$id),'version'=>(string)($m['version']??'1.0.0'),
            'author'=>(string)($m['author']??''),'description'=>(string)($m['description']??''),
            'license'=>(string)($m['license']??'Unknown'),'active'=>isset($active[$id]),
            'frontend_js'=>is_file($dir.'/frontend.js')?'/cms/plugins/'.$id.'/frontend.js':'',
            'frontend_css'=>is_file($dir.'/frontend.css')?'/cms/plugins/'.$id.'/frontend.css':'',
            'admin_js'=>is_file($dir.'/admin.js')?'/cms/plugins/'.$id.'/admin.js':'',
            'hooks'=>array_values(array_filter(array_map('strval',(array)($m['hooks']??[])))),
            'widgets'=>is_array($m['widgets']??null)?$m['widgets']:[]
        ];
    }
    usort($out,fn($a,$b)=>strcasecmp($a['name'],$b['name']));return $out;
}
function rrw_import_plugin_zip(string $zipPath): array {
    if(!class_exists('ZipArchive'))throw new RuntimeException('ZIP-Unterstützung ist auf dem Server nicht verfügbar');
    $zip=new ZipArchive();if($zip->open($zipPath)!==true)throw new RuntimeException('Plugin-ZIP konnte nicht geöffnet werden');
    $manifest=null;$prefix='';
    for($i=0;$i<$zip->numFiles;$i++){
        $name=str_replace('\\','/',$zip->getNameIndex($i));if(!rrw_zip_entry_safe($name))continue;
        if(strtolower(basename($name))==='plugin.json'){$manifest=$zip->getFromIndex($i);$prefix=dirname($name)==='.'?'':dirname($name).'/';break;}
    }
    if($manifest===null)throw new RuntimeException('plugin.json fehlt');
    $m=json_decode((string)$manifest,true);if(!is_array($m))throw new RuntimeException('plugin.json ist ungültig');
    $id=rrw_plugin_id((string)($m['id']??$m['name']??'plugin'));if($id==='')throw new RuntimeException('Plugin-ID ungültig');
    $dir=__DIR__.'/plugins/'.$id;if(is_dir($dir)){foreach(glob($dir.'/*')?:[] as $x)if(is_file($x))@unlink($x);}else if(!@mkdir($dir,0755,true))throw new RuntimeException('Plugin-Ordner konnte nicht angelegt werden');
    $safe=['plugin.json','frontend.js','frontend.css','admin.js','README.md'];
    foreach($safe as $file){
        $idx=$zip->locateName($prefix.$file);if($idx===false)continue;$data=$zip->getFromIndex($idx);if($data===false)continue;
        if($file==='frontend.js'||$file==='admin.js'){
            if(strlen($data)>250000)throw new RuntimeException($file.' ist zu groß');
            if(preg_match('/\b(eval|Function)\s*\(|document\.write\s*\(/i',$data))throw new RuntimeException($file.' enthält nicht erlaubte dynamische Code-Ausführung');
        }
        rrw_write_atomic($dir.'/'.$file,(string)$data);
    }
    if(!is_file($dir.'/plugin.json'))rrw_write_atomic($dir.'/plugin.json',json_encode($m,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    $zip->close();return ['id'=>$id,'manifest'=>$m];
}
function rrw_theme_catalog(array $site): array {
    $base=__DIR__.'/themes';$active=(string)($site['theme']['active']??'ricorewi-neon');$out=[];
    foreach(glob($base.'/*',GLOB_ONLYDIR)?:[] as $dir){
        $file=$dir.'/theme.json';if(!is_file($file))continue;$m=json_decode((string)file_get_contents($file),true);if(!is_array($m))continue;
        $id=rrw_theme_id((string)($m['id']??basename($dir)));if($id==='')continue;
        $shot='';foreach(['screenshot.webp','screenshot.png','screenshot.jpg','screenshot.jpeg'] as $sn)if(is_file($dir.'/'.$sn)){$shot='/cms/themes/'.$id.'/'.$sn;break;}
        $css=is_file($dir.'/theme.css')?'/cms/themes/'.$id.'/theme.css':'';
        $out[]=[
            'id'=>$id,'name'=>(string)($m['name']??$id),'version'=>(string)($m['version']??'1.0'),
            'author'=>(string)($m['author']??''),'description'=>(string)($m['description']??''),
            'license'=>(string)($m['license']??'RicoReWi Free Theme'),
            'builtin'=>!empty($m['builtin']),'wordpress'=>!empty($m['wordpress']),'bootstrap'=>!empty($m['bootstrap']),
            'compatibility'=>(string)($m['compatibility']??(!empty($m['wordpress'])?'wordpress-css':(!empty($m['bootstrap'])?'bootstrap-css':'native'))),
            'controls'=>is_array($m['controls']??null)?$m['controls']:[],
            'variants'=>is_array($m['variants']??null)?$m['variants']:[],
            'defaults'=>is_array($m['defaults']??null)?$m['defaults']:[],
            'layout'=>rrw_theme_layout_clean($m['layout']??[]),
            'widget_areas'=>is_array($m['widget_areas']??null)?rrw_clean_section('widget_areas',$m['widget_areas']):[],
            'stylesheet'=>$css,'screenshot'=>$shot,'active'=>$id===$active
        ];
    }
    usort($out,fn($a,$b)=>($b['active']<=>$a['active'])?:strcasecmp($a['name'],$b['name']));return $out;
}
function rrw_zip_entry_safe(string $name): bool {
    $name=str_replace('\\','/',$name);
    return $name!==''&&!str_contains($name,'../')&&!str_starts_with($name,'/')&&!preg_match('/^[A-Za-z]:/',$name);
}
function rrw_import_theme_zip(string $zipPath): array {
    if(!class_exists('ZipArchive'))throw new RuntimeException('ZIP-Unterstützung ist auf dem Server nicht verfügbar');
    $zip=new ZipArchive();if($zip->open($zipPath)!==true)throw new RuntimeException('ZIP konnte nicht geöffnet werden');
    $themeJson=null;$styleCss=null;$screenshot=null;$wp=false;$rootPrefix='';
    for($i=0;$i<$zip->numFiles;$i++){
        $name=str_replace('\\','/',$zip->getNameIndex($i));if(!rrw_zip_entry_safe($name))continue;
        $base=strtolower(basename($name));
        if($base==='theme.json'&&$themeJson===null){$themeJson=$zip->getFromIndex($i);$rootPrefix=dirname($name)==='.'?'':dirname($name).'/';}
    }
    // WordPress-Block-Themes bringen eine eigene theme.json (anderes Format) mit: ohne theme.css
    // ist es kein RicoReWi-Theme, sondern wird über style.css als WordPress-Theme importiert.
    if($themeJson!==null){$hasThemeCss=false;for($i=0;$i<$zip->numFiles;$i++)if(strtolower(basename(str_replace('\\','/',$zip->getNameIndex($i))))==='theme.css'){$hasThemeCss=true;break;}if(!$hasThemeCss)$themeJson=null;}
    if($themeJson!==null){
        $m=json_decode((string)$themeJson,true);if(!is_array($m))throw new RuntimeException('theme.json ist ungültig');
        $m['compatibility']=$m['compatibility']??'native';
        $id=rrw_theme_id((string)($m['id']??$m['name']??'theme'));
        for($i=0;$i<$zip->numFiles;$i++){
            $name=str_replace('\\','/',$zip->getNameIndex($i));if(!rrw_zip_entry_safe($name)||!str_starts_with($name,$rootPrefix))continue;
            $rel=substr($name,strlen($rootPrefix));$base=strtolower(basename($rel));
            if($base==='theme.css')$styleCss=(string)$zip->getFromIndex($i);
            if(in_array($base,['screenshot.png','screenshot.jpg','screenshot.jpeg','screenshot.webp'],true))$screenshot=['name'=>$base,'data'=>$zip->getFromIndex($i)];
        }
    } else {
        $wpStyleIndex=-1;
        for($i=0;$i<$zip->numFiles;$i++){
            $name=str_replace('\\','/',$zip->getNameIndex($i));if(!rrw_zip_entry_safe($name))continue;
            if(strtolower(basename($name))==='style.css'){$wpStyleIndex=$i;$rootPrefix=dirname($name)==='.'?'':dirname($name).'/';break;}
        }
        if($wpStyleIndex<0)throw new RuntimeException('Kein RicoReWi theme.json und kein WordPress style.css gefunden');
        $wp=true;$styleCss=(string)$zip->getFromIndex($wpStyleIndex);
        preg_match('/Theme Name:\s*(.+)/i',$styleCss,$mm);$name=trim((string)($mm[1]??basename(rtrim($rootPrefix,'/'))?:'WordPress Theme'));
        preg_match('/Version:\s*(.+)/i',$styleCss,$mv);preg_match('/Author:\s*(.+)/i',$styleCss,$ma);
        $id=rrw_theme_id($name);
        $isBootstrap=stripos($styleCss,'bootstrap')!==false;
        $m=['id'=>$id,'name'=>$name,'version'=>trim((string)($mv[1]??'1.0')),'author'=>trim((string)($ma[1]??'')),'description'=>'WordPress-Theme über die RicoReWi-Kompatibilitätsschicht importiert. CSS, Bilder und Webfonts werden übernommen; WordPress-PHP wird nicht ausgeführt.','wordpress'=>true,'bootstrap'=>$isBootstrap,'compatibility'=>$isBootstrap?'wordpress+bootstrap':'wordpress-css','builtin'=>false];
        for($i=0;$i<$zip->numFiles;$i++){
            $nameIn=str_replace('\\','/',$zip->getNameIndex($i));if(!rrw_zip_entry_safe($nameIn)||!str_starts_with($nameIn,$rootPrefix))continue;
            $base=strtolower(basename($nameIn));if(in_array($base,['screenshot.png','screenshot.jpg','screenshot.jpeg','screenshot.webp'],true)){$screenshot=['name'=>$base,'data'=>$zip->getFromIndex($i)];break;}
        }
    }
    if($id==='ricorewi-neon'){$zip->close();throw new RuntimeException('Das Standardtheme kann nicht überschrieben werden');}
    $dir=__DIR__.'/themes/'.$id;if(is_dir($dir)){foreach(glob($dir.'/*')?:[] as $x)if(is_file($x))@unlink($x);}else @mkdir($dir,0755,true);
    // Safe static assets only. No PHP/JS/templates/plugins are extracted.
    $allowedAssets=['png','jpg','jpeg','webp','gif','svg','ico'];
    for($i=0;$i<$zip->numFiles;$i++){
        $name=str_replace('\\','/',$zip->getNameIndex($i));if(!rrw_zip_entry_safe($name)||!str_starts_with($name,$rootPrefix))continue;
        $rel=substr($name,strlen($rootPrefix));if($rel===''||str_ends_with($rel,'/'))continue;
        $ext=strtolower((string)pathinfo($rel,PATHINFO_EXTENSION));if(!in_array($ext,$allowedAssets,true))continue;
        $data=$zip->getFromIndex($i);if(!is_string($data)||strlen($data)>8388608)continue;
        if($ext==='svg'&&preg_match('/<(script|foreignObject)\b|on[a-z]+\s*=|javascript:/i',$data))continue;
        $dest=$dir.'/'.ltrim($rel,'/');$destDir=dirname($dest);if(!is_dir($destDir))@mkdir($destDir,0755,true);
        rrw_write_atomic($dest,$data);
    }
    $zip->close();
    if($styleCss===null||trim($styleCss)==='')throw new RuntimeException('Theme enthält keine CSS-Datei');
    $styleCss=preg_replace('#url\((["\']?)\s*(?:javascript:|data:text/html)[^)]*\)#i','none',$styleCss);
    $m['id']=$id;$m['builtin']=false;$m['wordpress']=$wp||!empty($m['wordpress']);
    $m['bootstrap']=!empty($m['bootstrap'])||stripos((string)$styleCss,'bootstrap')!==false||preg_match('/\.container(?:-fluid)?\s*\{|\.row\s*\{|\.btn-primary\s*\{/i',(string)$styleCss);
    if(empty($m['compatibility']))$m['compatibility']=!empty($m['wordpress'])?(!empty($m['bootstrap'])?'wordpress+bootstrap':'wordpress-css'):(!empty($m['bootstrap'])?'bootstrap-css':'native');
    rrw_write_atomic($dir.'/theme.json',json_encode($m,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    rrw_write_atomic($dir.'/theme.css',(string)$styleCss);
    if($screenshot&&is_string($screenshot['data']))rrw_write_atomic($dir.'/'.$screenshot['name'],$screenshot['data']);
    return ['id'=>$id,'wordpress'=>!empty($m['wordpress'])];
}
require_once __DIR__.'/lib/publish.php';
require_once __DIR__.'/lib/feeds.php';
require_once __DIR__.'/lib/backup.php';
require_once __DIR__.'/lib/database.php';
require_once __DIR__.'/lib/seo.php';
require_once __DIR__.'/lib/content.php';
require_once __DIR__.'/lib/auth.php';
require_once __DIR__.'/lib/mail.php';
require_once __DIR__.'/lib/assistant.php';
require_once __DIR__.'/lib/directory.php';
require_once __DIR__.'/lib/apps.php';
require_once __DIR__.'/lib/geo.php';
require_once __DIR__.'/lib/alexa.php';
require_once __DIR__.'/lib/tools.php';
require_once __DIR__.'/lib/forms.php';
require_once __DIR__.'/lib/polls.php';
require_once __DIR__.'/lib/community.php';
require_once __DIR__.'/lib/forum.php';
require_once __DIR__.'/lib/social.php';
require_once __DIR__.'/lib/system.php';
require_once __DIR__.'/lib/product.php';
require_once __DIR__.'/lib/install.php';
require_once __DIR__.'/lib/pack.php';
rrw_system_apply_timezone();

// Gleichzeitiges Bearbeiten (CMS, Control Center, mehrere Personen): Beim Speichern wird die Datei gesperrt und neu gelesen,
// und jeder Bereich hat eine Versionskennung. Wer auf einem älteren Stand speichern will, bekommt statt stillem Überschreiben
// einen Konflikt (HTTP 409) und lädt neu.
function rrw_section_rev(array $site,string $section): string { return substr(sha1(json_encode($site[$section]??null,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)),0,12); }
function rrw_site_revs(array $site): array { $r=[];foreach($site as $k=>$v){if(is_string($k)&&$k!==''&&$k[0]!=='_')$r[$k]=rrw_section_rev($site,$k);}return $r; }
function rrw_site_lock(string $dataDir): void {
    static $h=null; if($h)return;
    $h=@fopen($dataDir.'/.site.lock','c'); if(!$h)return;
    @flock($h,LOCK_EX); // wird beim Ende der Anfrage freigegeben
}
rrw_ensure_dirs();
$action=(string)($_GET['action']??'public');
if(function_exists('rrw_demo_guard'))rrw_demo_guard($action,rrw_body());
$site=rrw_ensure_site_defaults(rrw_read_json($siteFile,[]));$GLOBALS['RRW_SITE']=$site;
if(!isset($site['theme'])||!is_array($site['theme']))$site['theme']=['active'=>rrw_default_theme_id()];

// One-time migration of the previously stored Control-Center CMS settings.
// After this marker exists, /cms/data/site.json is the only content source.
if(in_array($action,['access','get','save','media_upload','branding_upload','core_validate','core_add','core_remove','architecture','services_status','news_list','news_get','news_save','news_delete','news_thumbnail_upload'],true)
   && empty($site['_meta']['control_center_imported_at']) && !rrw_standalone()) {
    $tok=rrw_token();
    if($tok!=='') {
        try {
            $legacy=rrw_control_center_json('radio_cms_legacy_settings_export',$tok);
            $settings=is_array($legacy['settings']??null)?$legacy['settings']:[];
            foreach(['portal','branding','social','apps','core_network','pages','menus','widgets','widget_areas','widget_inactive','feed_sources','rss','legal','services','theme','brands','assistant','alexa'] as $section){
                if(array_key_exists($section,$settings)){
                    $clean=rrw_clean_section($section,$settings[$section]);
                    if($clean!==null)$site[$section]=$clean;
                }
            }
            $site=rrw_ensure_site_defaults($site);
            $site['_meta']=is_array($site['_meta']??null)?$site['_meta']:[];
            $site['_meta']['control_center_imported_at']=date(DATE_ATOM);
            rrw_publish($site,$siteFile,$genDir,$root);
        } catch(Throwable $e) {
            // Keep the file-CMS usable even when legacy migration is unavailable.
        }
    }
}
// Multi-Brand: öffentliche Markeninfo für den aufgerufenen Hostname (oder ?rrw_brand=<id> zur Vorschau)
$rrwBrand=rrw_brand_resolve($site,(string)($_SERVER['HTTP_HOST']??''),rrw_brand_forced_from_request());
header('Vary: Host');
if($action==='brand')rrw_json(['status'=>'ok']+rrw_brand_public_payload($rrwBrand));
// KI-Assistent (öffentlich): Chat, Nachricht ans Studio, Sprachnachricht – Weiterleitung an Studiomail im Control Center
if($action==='assistant_chat'){ $r=rrw_assistant_chat($site,$rrwBrand,rrw_body(),$dataDir,$newsFile);$code=(int)($r['code']??200);unset($r['code']);rrw_json($r,$code); }
if($action==='assistant_send'){ $r=rrw_assistant_send_studiomail($site,rrw_body(),$dataDir);$code=(int)($r['code']??200);unset($r['code']);rrw_json($r,$code); }
if($action==='assistant_voice'){ $r=rrw_assistant_send_voice($site,$dataDir);$code=(int)($r['code']??200);unset($r['code']);rrw_json($r,$code); }
// Radioverzeichnis: gehört zum RicoReWi-Paket und fehlt im eigenständigen CMS
if(strncmp($action,'directory_',10)===0&&!rrw_pack_available())rrw_json(['status'=>'error','message'=>'Das Radioverzeichnis ist in diesem CMS nicht enthalten.'],404);
// Radioverzeichnis (Marken mit Verzeichnis-Funktion, z.B. SenderWelt): öffentlich, mit Stundenlimit je IP
if($action==='directory_search'){
    if(!rrw_assistant_rate_ok($dataDir,'directory',400))rrw_json(['status'=>'error','message'=>'Zu viele Suchanfragen – bitte in ein paar Minuten noch einmal versuchen.'],429);
    try{rrw_json(rrw_directory_search($site,$_GET,$dataDir));}
    catch(Throwable $e){error_log('directory_search: '.get_class($e).': '.$e->getMessage().' @'.basename($e->getFile()).':'.$e->getLine());rrw_json(['status'=>'error','message'=>'Die Verzeichnis-Suche ist gerade nicht erreichbar. Bitte später noch einmal versuchen.','where'=>get_class($e).' @'.basename($e->getFile()).':'.$e->getLine()],503);}
}
if($action==='directory_frame'){
    if(!rrw_assistant_rate_ok($dataDir,'directory',400))rrw_json(['status'=>'error','message'=>'Zu viele Anfragen.'],429);
    try{$adm=rrw_dir_admin_load($dataDir);if(!$adm['settings']['preview']||rrw_dir_url_blocked((string)($_GET['url']??''),$adm))rrw_json(['status'=>'ok','embeddable'=>false,'url'=>'']);$e=rrw_dir_embeddable((string)($_GET['url']??''),$dataDir);rrw_json(['status'=>'ok','embeddable'=>!empty($e['ok']),'url'=>(string)($e['url']??'')]);}
    catch(Throwable $e){error_log('directory_frame: '.get_class($e).': '.$e->getMessage());rrw_json(['status'=>'ok','embeddable'=>false]);}
}
if($action==='directory_meta'){
    if(!rrw_assistant_rate_ok($dataDir,'dirmeta',500))rrw_json(['status'=>'ok','title'=>'','image'=>'']);
    try{if(rrw_dir_url_blocked((string)($_GET['url']??''),rrw_dir_admin_load($dataDir)))rrw_json(['status'=>'ok','title'=>'','image'=>'']);$m=rrw_dir_stream_title((string)($_GET['url']??''),$dataDir);rrw_json(['status'=>'ok','title'=>$m['t'],'image'=>$m['i']]);}
    catch(Throwable $e){error_log('directory_meta: '.get_class($e).': '.$e->getMessage());rrw_json(['status'=>'ok','title'=>'','image'=>'']);}
}
if($action==='directory_preview'){
    if(!rrw_assistant_rate_ok($dataDir,'directory',400))rrw_json(['status'=>'ok','preview'=>new stdClass()]);
    try{$adm=rrw_dir_admin_load($dataDir);if(!$adm['settings']['preview']||rrw_dir_url_blocked((string)($_GET['url']??''),$adm))rrw_json(['status'=>'ok','preview'=>new stdClass()]);$pv=rrw_dir_preview((string)($_GET['url']??''),$dataDir);rrw_json(['status'=>'ok','preview'=>$pv?:new stdClass()]);}
    catch(Throwable $e){error_log('directory_preview: '.get_class($e).': '.$e->getMessage());rrw_json(['status'=>'ok','preview'=>new stdClass()]);}
}
if($action==='directory_random'){
    if(!rrw_assistant_rate_ok($dataDir,'directory',400))rrw_json(['status'=>'error','message'=>'Zu viele Anfragen.'],429);
    try{
        $own=array_map('strtolower',array_map('strval',(array)($site['core_network']['stations']??[])));
        $kind=in_array(($_GET['kind']??'mix'),['laut','world','mix'],true)?(string)$_GET['kind']:'mix';
        rrw_json(['status'=>'ok','report'=>!empty(rrw_dir_admin_load($dataDir)['settings']['report']),'results'=>rrw_dir_random($kind,(int)($_GET['n']??6),$own,$dataDir)]);
    }catch(Throwable $e){error_log('directory_random: '.get_class($e).': '.$e->getMessage().' @'.basename($e->getFile()).':'.$e->getLine());rrw_json(['status'=>'error','message'=>'Gerade nicht erreichbar.'],503);}
}
if($action==='directory_report'){
    if(!rrw_assistant_rate_ok($dataDir,'dirreport',6))rrw_json(['status'=>'error','message'=>'Zu viele Meldungen – bitte später erneut versuchen.'],429);
    try{$r=rrw_dir_report_add($dataDir,rrw_body());rrw_json(!empty($r['ok'])?['status'=>'ok']:['status'=>'error','message'=>(string)($r['message']??'Meldung nicht möglich.')],!empty($r['ok'])?200:400);}
    catch(Throwable $e){error_log('directory_report: '.get_class($e).': '.$e->getMessage());rrw_json(['status'=>'error','message'=>'Meldung momentan nicht möglich.'],503);}
}
// Apps: öffentliche Startabfrage der nativen Apps (Funktionen, Hinweis, Update) und Übersicht für das CMS (Admin)
if($action==='app_config'){
    $did=rrw_apps_did_clean($_GET['did']??'');$pl=(string)($_GET['platform']??'android');$ver=(string)($_GET['version']??'');
    $out=rrw_apps_public($site,$root,$rrwBrand,$pl,$ver,$did,rrw_apps_salt($dataDir),max(0,min(99999999,(int)($_GET['code']??0))));
    if(!empty($out['telemetry']['usage'])&&$did!==''&&rrw_apps_rate_ok($dataDir,'ping',120)){
        $geo='';if(($site['apps']['telemetry']['geo']??true)){$ip=trim(explode(',',(string)($_SERVER['HTTP_X_FORWARDED_FOR']??$_SERVER['REMOTE_ADDR']??''))[0]);$geo=rrw_geo_lookup($dataDir,$ip);}
        rrw_apps_record_ping($dataDir,$out['brand'],$out['platform'],$ver,$did,$geo);
    }
    rrw_json($out);
}
// Download-Zähler: zählt (je Datei höchstens 3x pro Stunde und Besucher, nicht für Suchmaschinen/Vorschau/HEAD) und leitet auf die Datei in downloads/ weiter
if($action==='app_download'){
    $f=basename((string)($_GET['file']??''));
    if(!rrw_apps_download_file_ok($root,$f)){http_response_code(404);header('Content-Type:text/plain; charset=utf-8');exit('Datei nicht gefunden');}
    $bot=preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|curl|wget/i',(string)($_SERVER['HTTP_USER_AGENT']??''));
    if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'&&!$bot&&rrw_apps_rate_ok($dataDir,'dl_'.substr(sha1($f),0,8),3))rrw_apps_download_add($dataDir,$f);
    header('Cache-Control: no-store');header('Location: /downloads/'.rawurlencode($f),true,302);exit;
}
if($action==='app_error'){
    $b=rrw_body();$pl=isset(RRW_APPS_PLATFORMS[(string)($b['platform']??'')])?(string)$b['platform']:'';
    $on=!empty($site['apps']['telemetry']['errors']);
    if(!$on||$pl==='')rrw_json(['status'=>'ok','stored'=>false]);
    if(!rrw_apps_rate_ok($dataDir,'err',30))rrw_json(['status'=>'error','message'=>'Zu viele Berichte.'],429);
    $reg=rrw_brands_registry($site);$bid=(string)($rrwBrand['brand']??$rrwBrand['id']??$reg['default']);
    rrw_json(['status'=>'ok','stored'=>rrw_apps_error_add($dataDir,$bid,$pl,$b)]);
}
if($action==='app_listen'){
    $b=rrw_body();$pl=isset(RRW_APPS_PLATFORMS[(string)($b['platform']??'')])?(string)$b['platform']:'';
    $on=!empty($site['apps']['telemetry']['usage'])&&!empty($site['apps']['telemetry']['listen']);
    if(!$on||$pl==='')rrw_json(['status'=>'ok','stored'=>false]);
    if(!rrw_apps_rate_ok($dataDir,'listen',120))rrw_json(['status'=>'error','message'=>'Zu viele Berichte.'],429);
    $reg=rrw_brands_registry($site);$bid=(string)($rrwBrand['brand']??$rrwBrand['id']??$reg['default']);
    rrw_json(['status'=>'ok','stored'=>rrw_apps_record_listen($dataDir,$bid,$pl,(string)($b['did']??''),(string)($b['station']??''),(int)($b['seconds']??0))]);
}
if($action==='alexa_config'){
    $origin=rrw_site_origin($site);
    rrw_alexa_note_fetch($dataDir);header('Cache-Control: public, max-age=60');
    rrw_json(rrw_alexa_public($site,$dataDir,$origin));
}
if($action==='alexa_stat'){
    $b=rrw_body();$tok=(string)($b['token']??'');
    if(!hash_equals(rrw_alexa_token($dataDir),$tok))rrw_json(['status'=>'error','message'=>'Nicht erlaubt'],403);
    if(empty($site['alexa']['stats']))rrw_json(['status'=>'ok','stored'=>false]);
    rrw_json(['status'=>'ok','stored'=>rrw_alexa_stat_add($dataDir,(string)($b['event']??''),(string)($b['station']??''),(string)($b['intent']??''))]);
}
if($action==='alexa_get'){
    rrw_auth(true);$origin=rrw_site_origin($site);
    $defs=rrw_alexa_station_defs($site);$st=[];
    foreach($defs as $id=>$s){$v=rrw_alexa_station_value($s,rrw_alexa_catalog()['suffixes']);$st[]=['id'=>$id,'title'=>$s['title'],'enabled'=>$s['enabled'],'extra'=>$s['extra'],'speakable'=>array_merge([$v['name']['value']],array_slice($v['name']['synonyms'],0,6)),'custom_title'=>(string)(((array)(($site['alexa']['stations']??[])))[$id]['title']??''),'stream'=>(string)($s['stream']??'')];}
    rrw_alexa_model($site,$warn);
    rrw_json(['status'=>'ok','config'=>rrw_alexa_clean($site['alexa']??[]),'stations'=>$st,'stats'=>rrw_alexa_stats($dataDir),'last_fetch'=>rrw_alexa_last_fetch($dataDir),'warnings'=>$warn,'origin'=>$origin,'invocation'=>rrw_alexa_catalog()['brand']['invocationName'],'neutral'=>rrw_alexa_neutral(),'app_name'=>rrw_alexa_catalog()['brand']['name'],'token_set'=>strlen(rrw_alexa_token($dataDir))>=32,'model_rev'=>rrw_alexa_model_rev($site),'exported_rev'=>rrw_alexa_exported_rev($dataDir)]);
}
if($action==='alexa_token_reset'){ rrw_auth(true);rrw_alexa_token($dataDir,true);rrw_json(['status'=>'ok']); }
if($action==='alexa_stats_clear'){ rrw_auth(true);rrw_alexa_stats_clear($dataDir);rrw_json(['status'=>'ok']); }
if($action==='alexa_download'){
    rrw_auth(true);$origin=rrw_site_origin($site);
    $what=(string)($_GET['file']??'package');$files=rrw_alexa_package_files($site,$root,$origin,$dataDir,$warn);
    if(in_array($what,['package','model'],true))rrw_alexa_mark_exported($dataDir,rrw_alexa_model_rev($site));
    header_remove('Content-Type');
    if($what==='package'){try{$zip=rrw_alexa_zip($files);}catch(Throwable $e){http_response_code(500);exit($e->getMessage());}header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="ricorewi-radio-alexa-skill.zip"');header('Content-Length: '.filesize($zip));readfile($zip);@unlink($zip);exit;}
    $map=['model'=>['skill-package/interactionModels/custom/de-DE.json','de-DE.json'],'manifest'=>['skill-package/skill.json','skill.json'],'lambda'=>['lambda/index.js','index.js'],'fallback'=>['lambda/fallback.json','fallback.json'],'cms'=>['lambda/cms.json','cms.json']];
    if(!isset($map[$what])){http_response_code(404);exit('Unbekannte Datei');}
    header('Content-Type: '.(str_ends_with($map[$what][1],'.js')?'application/javascript':'application/json').'; charset=utf-8');header('Content-Disposition: attachment; filename="'.$map[$what][1].'"');echo $files[$map[$what][0]];exit;
}
if($action==='apps_overview'){ rrw_auth(true);rrw_json(rrw_apps_overview($site,$root)); }
// Build-Assistent für eigene Android-Apps (GitHub-Repository mit den App-Quellen, Workflow android-custom-brand.yml) – nur Superadmins
if(str_starts_with($action,'app_build')){
    $abUser=rrw_auth(true);require_once __DIR__.'/lib/appbuild.php';$ab=rrw_body();
    $abFail=fn(string $m,int $c=422)=>rrw_json(['status'=>'error','message'=>$m],$c);
    $abState=fn(array $x=[])=>rrw_json(['status'=>'ok']+rrw_ab_state($dataDir)+$x);
    if($action==='app_build_state')$abState();
    if($action==='app_build_download'){ rrw_ab_download($dataDir,(string)($_GET['brand']??''),(int)($_GET['asset']??0));exit; }
    if($action==='app_build_save'){   // Repository, Branch, Token
        $d=rrw_ab_load($dataDir);$repo=rrw_ab_clean_repo((string)($ab['repo']??''));$br=rrw_ab_clean_branch((string)($ab['branch']??'app-builder'));
        if($repo==='')$abFail('Das Repository hat die Form besitzer/name.');
        if($br==='')$abFail('Ungültiger Branch-Name.');
        $tok=trim((string)($ab['token']??''));if($tok!==''&&!preg_match('/^[A-Za-z0-9_\-]{20,255}$/',$tok))$abFail('Das Token hat ein ungültiges Format.');
        if($tok!=='')$d['token']=$tok;if(!empty($ab['clear_token']))$d['token']='';
        $d['repo']=$repo;$d['branch']=$br;if(!rrw_ab_save($dataDir,$d))$abFail('Konnte nicht gespeichert werden (Schreibrechte für cms/data prüfen).',500);
        rrw_log_activity($activityLogFile,$abUser,'app_build','App-Builder: Repository '.$repo.' eingerichtet');$abState();
    }
    if($action==='app_build_check')rrw_json(['status'=>'ok']+rrw_ab_check($dataDir));
    if($action==='app_build_brand_save'){
        $d=rrw_ab_load($dataDir);$origin=(isset($_SERVER['HTTP_HOST'])?'https://'.preg_replace('/[^A-Za-z0-9.:-]/','',(string)$_SERVER['HTTP_HOST']):'');
        [$b,$err]=rrw_ab_clean_brand($ab,$origin);if($b===null)$abFail($err);
        $i=null;foreach($d['brands'] as $k=>$x)if(($x['id']??'')===$b['id'])$i=$k;
        foreach($d['brands'] as $k=>$x)if($k!==$i&&($x['applicationId']??'')===$b['applicationId'])$abFail('Dieser Paketname wird schon von einer anderen App verwendet.');
        if($i===null&&count($d['brands'])>=RRW_AB_MAX_BRANDS)$abFail('Es sind höchstens '.RRW_AB_MAX_BRANDS.' eigene Apps möglich.');
        if($i===null)$d['brands'][]=$b;else $d['brands'][$i]=$b;
        if(!rrw_ab_save($dataDir,$d))$abFail('Konnte nicht gespeichert werden.',500);
        rrw_log_activity($activityLogFile,$abUser,'app_build','App-Builder: App „'.$b['appName'].'“ gespeichert');$abState();
    }
    if($action==='app_build_brand_delete'){
        $d=rrw_ab_load($dataDir);$id=(string)($ab['id']??'');$d['brands']=array_values(array_filter($d['brands'],fn($x)=>($x['id']??'')!==$id));
        if(!rrw_ab_save($dataDir,$d))$abFail('Konnte nicht gespeichert werden.',500);
        rrw_log_activity($activityLogFile,$abUser,'app_build','App-Builder: App '.$id.' entfernt');$abState();
    }
    if($action==='app_build_start'){
        if(!rrw_apps_rate_ok($dataDir,'abstart',6))$abFail('Bitte kurz warten, bevor du erneut baust.',429);
        $r=rrw_ab_start($dataDir,$root,(string)($ab['id']??''),(string)($ab['platform']??'android'));if(!$r['ok'])$abFail($r['message']);
        rrw_log_activity($activityLogFile,$abUser,'app_build','App-Builder: Build gestartet ('.(string)$ab['id'].')');rrw_json(['status'=>'ok','message'=>$r['message']]);
    }
    if($action==='app_build_status'){ $r=rrw_ab_status($dataDir,(string)($_GET['brand']??''));if(!$r['ok'])$abFail($r['message']);rrw_json(['status'=>'ok']+$r); }
    $abFail('Unbekannte Aktion',404);
}
if($action==='apps_stats'){ rrw_auth(true);rrw_json(['status'=>'ok','usage'=>rrw_apps_usage($dataDir),'new_daily'=>rrw_apps_new_daily($dataDir),'retention'=>rrw_apps_retention($dataDir),'downloads'=>rrw_apps_download_stats($dataDir,$site,$root),'errors'=>rrw_apps_errors($dataDir),'telemetry'=>(array)($site['apps']['telemetry']??[]),'listen'=>rrw_apps_listen_stats($dataDir),'geo'=>rrw_apps_geo_stats($dataDir),'geo_db'=>rrw_geo_status($dataDir)]); }
if($action==='apps_geo_update'){ rrw_auth(true);$r=rrw_geo_download($dataDir);rrw_json($r['ok']?['status'=>'ok','geo_db'=>rrw_geo_status($dataDir)]:['status'=>'error','message'=>$r['message']],$r['ok']?200:502); }
if($action==='apps_stats_clear'){ rrw_auth(true);$b=rrw_body();rrw_apps_stats_clear($dataDir,(string)($b['what']??'all'));rrw_json(['status'=>'ok']); }
if($action==='directory_admin_get'){ rrw_auth(true);rrw_json(['status'=>'ok']+rrw_dir_admin_view($dataDir)); }
if($action==='directory_admin_save'){
    $auth=rrw_auth(true);$r=rrw_dir_admin_apply($dataDir,rrw_body(),(string)($auth['user']??'admin'));
    rrw_json(!empty($r['ok'])?['status'=>'ok']+rrw_dir_admin_view($dataDir):['status'=>'error','message'=>(string)($r['message']??'Speichern nicht möglich.')],!empty($r['ok'])?200:400);
}
if($action==='assistant_status'){ rrw_auth(false);rrw_json(['status'=>'ok']+rrw_assistant_status($site,$dataDir)); }
if($action==='assistant_test'){ rrw_auth(false);$b=rrw_body();rrw_json(['status'=>'ok']+rrw_assistant_test($site,(string)($b['provider']??''),$dataDir,(string)($b['model']??''))); }
if($action==='assistant_models'){ rrw_auth(true);rrw_json(['status'=>'ok']+rrw_assistant_models_list($site,rrw_body(),$dataDir)); }
if($action==='brands_public'){$reg=rrw_brands_registry($site);rrw_json(['status'=>'ok','default'=>$reg['default'],'brands'=>array_map(fn($b)=>['id'=>$b['id'],'name'=>$b['name'],'short_name'=>$b['short_name'],'primary_domain'=>$b['primary_domain'],'domains'=>$b['domains'],'enabled'=>!empty($b['enabled'])],$reg['items'])]);}
if($action==='schedule'){
    // Öffentlich, nur lesend: Sendeplan der Kernsender (aufgeräumt, zwischengespeichert, "jetzt/als Nächstes" in Sender-Zeitzone)
    require_once __DIR__.'/lib/schedule.php';
    $core=rrw_assistant_stations($site);
    $want=strtolower(trim((string)($_GET['station']??'')));
    if($want!==''&&!in_array($want,$core,true))rrw_json(['status'=>'error','message'=>'Unbekannter Sender.'],404);
    header('Cache-Control: public, max-age=60');
    rrw_json(rrw_schedule_payload($want!==''?[$want]:$core,$dataDir));
}
if($action==='public')rrw_json(['status'=>'ok','config'=>rrw_site_public($site),'brand'=>rrw_brand_public_payload($rrwBrand),'storage'=>'/cms/data/site.json']);
if($action==='import_legacy'){
    rrw_auth(true);$tok=rrw_token();
    if(rrw_standalone())rrw_json(['status'=>'error','message'=>rrw_standalone_notice('Die Altdaten-Übernahme')],409);
    try{
        $legacySettings=rrw_control_center_json('radio_cms_legacy_settings_export',$tok);
        $legacyNews=rrw_control_center_json('radio_cms_legacy_news_export',$tok);
    }catch(Throwable $e){rrw_json(['status'=>'error','message'=>$e->getMessage()],502);}
    $settings=is_array($legacySettings['settings']??null)?$legacySettings['settings']:[];
    foreach(['portal','social','apps','branding','core_network','pages','menus','widgets','widget_areas','widget_inactive','feed_sources','rss','legal','services','theme','brands','assistant','alexa'] as $section){
        if(array_key_exists($section,$settings)){
            $clean=rrw_clean_section($section,$settings[$section]);
            if($clean!==null)$site[$section]=$clean;
        }
    }
    $newsRows=is_array($legacyNews['articles']??null)?array_values($legacyNews['articles']):[];
    try{
        $site=rrw_ensure_site_defaults($site);
            $site['_meta']=is_array($site['_meta']??null)?$site['_meta']:[];
        $site['_meta']['control_center_imported_at']=date(DATE_ATOM);
        $site['_meta']['legacy_imported_at']=date(DATE_ATOM);
        rrw_publish($site,$siteFile,$genDir,$root);
        if($newsRows)rrw_write_atomic($newsFile,json_encode($newsRows,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    }catch(Throwable $e){rrw_json(['status'=>'error','message'=>'Import konnte nicht gespeichert werden: '.$e->getMessage()],500);}
    rrw_json(['status'=>'ok','config'=>$site,'news_count'=>count($newsRows),'message'=>'Altdaten wurden in /cms übernommen']);
}
if($action==='access'){ $a=rrw_auth(false);rrw_json(['status'=>'ok','allowed'=>true,'superadmin'=>!empty($a['superadmin']),'role'=>$a['role']??'admin','user'=>$a['user']??'','display_name'=>$a['display_name']??'','source'=>$a['source']??'','standalone'=>rrw_standalone(),'product'=>rrw_product_public(),'version'=>rrw_cms_version()]); }
if($action==='product'){ rrw_json(['status'=>'ok','product'=>rrw_product_public(),'standalone'=>rrw_standalone()]); }
if($action==='local_auth_status'){ rrw_json(['status'=>'ok','configured'=>rrw_local_auth_configured(),'standalone'=>rrw_standalone(),'install_needed'=>rrw_install_needed(),'product'=>rrw_product_public()]); }
if($action==='local_auth_setup'){
    // Frische Installation: Konto nur über den Einrichtungsassistenten (Passwortregeln, CSRF, Sperre), nicht über diese offene Route.
    if(rrw_install_needed())rrw_json(['status'=>'error','message'=>'Bitte die Einrichtung über install.php abschließen','install_needed'=>true],409);
    $configured=rrw_local_auth_configured();
    if($configured)rrw_auth(true);
    $b=rrw_body();
    try{ rrw_local_auth_set((string)($b['username']??''),(string)($b['password']??''),'admin'); }
    catch(Throwable $e){ rrw_json(['status'=>'error','message'=>$e->getMessage()],400); }
    if(!$configured)rrw_json(['status'=>'ok','token'=>rrw_local_session_create(trim((string)($b['username']??''))),'superadmin'=>true]);
    rrw_json(['status'=>'ok']);
}
if($action==='login'){
    if(!rrw_local_auth_configured())rrw_json(['status'=>'error','message'=>'Lokaler Zugang ist nicht eingerichtet'],400);
    $b=rrw_body();$username=trim((string)($b['username']??''));$password=(string)($b['password']??'');
    $user=rrw_local_auth_verify($username,$password);
    if($user===null)rrw_json(['status'=>'error','message'=>'Benutzername oder Passwort falsch'],401);
    rrw_json(['status'=>'ok','token'=>rrw_local_session_create((string)$user['username']),'superadmin'=>($user['role']??'admin')==='admin']);
}
if(in_array($action,['users_list','user_add','user_update','user_delete','activity_log_list'],true))$authUser=rrw_auth(true);
if($action==='activity_log_list'){
    $all=rrw_read_json($activityLogFile,[]);
    usort($all,fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));
    rrw_json(['status'=>'ok','entries'=>array_slice($all,0,100)]);
}
if($action==='users_list'){
    $users=array_map(fn($u)=>['username'=>(string)($u['username']??''),'role'=>$u['role']??'autor','display_name'=>(string)($u['display_name']??$u['username']??''),'email'=>(string)($u['email']??''),'created_at'=>(string)($u['created_at']??'')],rrw_local_users());
    rrw_json(['status'=>'ok','users'=>$users]);
}
if($action==='user_add'){
    $b=rrw_body();$newUsername=(string)($b['username']??'');
    try{ rrw_local_user_add($newUsername,(string)($b['password']??''),(string)($b['role']??'autor'),(string)($b['display_name']??''),(string)($b['email']??'')); }
    catch(Throwable $e){ rrw_json(['status'=>'error','message'=>$e->getMessage()],400); }
    rrw_log_activity($activityLogFile,$authUser,'user_add','Redakteur „'.$newUsername.'“ angelegt');
    rrw_json(['status'=>'ok']);
}
if($action==='user_update'){
    $b=rrw_body();$targetUsername=(string)($b['username']??'');
    $password=array_key_exists('password',$b)&&trim((string)$b['password'])!==''?(string)$b['password']:null;
    $role=array_key_exists('role',$b)?(string)$b['role']:null;
    $displayName=array_key_exists('display_name',$b)?(string)$b['display_name']:null;
    $email=array_key_exists('email',$b)?(string)$b['email']:null;
    try{ rrw_local_user_update($targetUsername,$password,$role,$displayName,$email); }
    catch(Throwable $e){ rrw_json(['status'=>'error','message'=>$e->getMessage()],400); }
    $changed=array_filter(['Rolle'=>$role!==null,'Passwort'=>$password!==null,'E-Mail'=>$email!==null,'Anzeigename'=>$displayName!==null]);
    rrw_log_activity($activityLogFile,$authUser,'user_update','Redakteur „'.$targetUsername.'“ aktualisiert ('.implode(', ',array_keys($changed)).')');
    rrw_json(['status'=>'ok']);
}
if($action==='user_delete'){
    $b=rrw_body();$username=(string)($b['username']??'');
    if(strcasecmp($authUser['user']??'',$username)===0)rrw_json(['status'=>'error','message'=>'Du kannst dich nicht selbst löschen'],400);
    try{ rrw_local_user_delete($username); }
    catch(Throwable $e){ rrw_json(['status'=>'error','message'=>$e->getMessage()],400); }
    rrw_log_activity($activityLogFile,$authUser,'user_delete','Redakteur „'.$username.'“ entfernt');
    rrw_json(['status'=>'ok']);
}
// Eigenes Profil bearbeiten (Anzeigename, E-Mail, Passwort) - im Gegensatz zu user_update
// braucht dies keine Administratorrechte, jeder eingeloggte lokale Nutzer darf nur sich selbst
// ändern (Rolle bleibt dabei bewusst unveränderbar). Wie bei WordPress' "Eigenes Profil
// bearbeiten" ist das unabhängig von der Redakteur-Verwaltung, die Admins vorbehalten ist.
if($action==='profile_get_self'){
    $selfAuth=rrw_auth(false);
    if(($selfAuth['source']??'')!=='local')rrw_json(['status'=>'ok','source'=>$selfAuth['source']??'','user'=>$selfAuth['user']??'','display_name'=>$selfAuth['display_name']??'','role'=>$selfAuth['role']??'admin','email'=>'']);
    $me=null;foreach(rrw_local_users() as $u)if(strcasecmp((string)($u['username']??''),(string)$selfAuth['user'])===0){$me=$u;break;}
    rrw_json(['status'=>'ok','source'=>'local','user'=>$selfAuth['user'],'display_name'=>(string)($me['display_name']??$selfAuth['user']),'role'=>$selfAuth['role']??'autor','email'=>(string)($me['email']??'')]);
}
if($action==='profile_update_self'){
    $selfAuth=rrw_auth(false);
    if(($selfAuth['source']??'')!=='local')rrw_json(['status'=>'error','message'=>'Dieses Profil wird über das '.rrw_product_control_center().' verwaltet'],400);
    $b=rrw_body();
    $password=array_key_exists('password',$b)&&trim((string)$b['password'])!==''?(string)$b['password']:null;
    $displayName=array_key_exists('display_name',$b)?(string)$b['display_name']:null;
    $email=array_key_exists('email',$b)?(string)$b['email']:null;
    try{ rrw_local_user_update((string)$selfAuth['user'],$password,null,$displayName,$email); }
    catch(Throwable $e){ rrw_json(['status'=>'error','message'=>$e->getMessage()],400); }
    rrw_log_activity($activityLogFile,$selfAuth,'profile_update_self','Eigenes Profil aktualisiert');
    rrw_json(['status'=>'ok']);
}
if($action==='logout'){
    $tok=rrw_token(); if($tok!=='')rrw_local_session_destroy($tok);
    rrw_json(['status'=>'ok']);
}
// Betrieb und Produkt (nur Administratoren): Betriebsmodus, Produktname, Version, optionale Prüfsumme.
if($action==='system_get'){
    rrw_auth(true);
    $adm=0;foreach(rrw_local_users() as $u)if(($u['role']??'')==='admin')$adm++;
    $out=['status'=>'ok','system'=>rrw_system_config(),'product'=>rrw_product(),'product_overrides'=>rrw_product_overrides(),'product_defaults'=>rrw_product_defaults(),'version'=>rrw_cms_version(),'local_admins'=>$adm,'standalone'=>rrw_standalone()];
    if(!empty($_GET['checksum']))$out['checksum']=rrw_cms_checksum();
    rrw_json($out);
}
if($action==='system_save'){
    $su=rrw_auth(true);$b=rrw_body();$changed=[];
    try{
        if(array_key_exists('control_center',$b)){
            $want=!empty($b['control_center']);
            if(!$want){
                $adm=0;foreach(rrw_local_users() as $u)if(($u['role']??'')==='admin')$adm++;
                if($adm<1)rrw_json(['status'=>'error','message'=>'Vor dem Ausschalten der Control-Center-Anbindung muss ein lokaler Administrator existieren (Redakteure).'],400);
            }
            if($want===rrw_standalone())$changed[]='Betriebsmodus';
            rrw_system_save(['control_center'=>$want]);
            try{rrw_update_index_snapshot(rrw_ensure_site_defaults(rrw_read_json($siteFile,[])),$root);}catch(Throwable $e){}
        }
        foreach(['language','timezone'] as $k)if(array_key_exists($k,$b)){rrw_system_save([$k=>(string)$b[$k]]);$changed[]=$k==='language'?'Sprache':'Zeitzone';}
        if(is_array($b['product']??null)){rrw_product_save($b['product']);$changed[]='Produktname';}
    }catch(InvalidArgumentException $e){rrw_json(['status'=>'error','message'=>$e->getMessage()],400);}
    catch(Throwable $e){rrw_json(['status'=>'error','message'=>'Speichern fehlgeschlagen'],500);}
    if($changed)rrw_log_activity($activityLogFile,$su,'system_save','Betrieb/Produkt geändert ('.implode(', ',array_unique($changed)).')');
    rrw_json(['status'=>'ok','system'=>rrw_system_config(),'product'=>rrw_product(),'standalone'=>rrw_standalone()]);
}
if($action==='health'){
    rrw_auth(false);
    rrw_ensure_dirs();
    $checks=[
        'cms_dir'=>['path'=>__DIR__,'writable'=>is_writable(__DIR__)],
        'data_dir'=>['path'=>$dataDir,'writable'=>is_writable($dataDir)],
        'generated_dir'=>['path'=>$genDir,'writable'=>is_writable($genDir)],
        'media_dir'=>['path'=>$mediaDir,'writable'=>is_writable($mediaDir)],
        'site_file'=>['path'=>$siteFile,'exists'=>is_file($siteFile),'writable'=>!is_file($siteFile)||is_writable($siteFile)],
        'news_file'=>['path'=>$newsFile,'exists'=>is_file($newsFile),'writable'=>!is_file($newsFile)||is_writable($newsFile)],
        'site_root'=>['path'=>$root,'writable'=>is_writable($root)],
        'index_file'=>['path'=>$root.'/index.html','exists'=>is_file($root.'/index.html'),'writable'=>is_file($root.'/index.html')&&is_writable($root.'/index.html')],
        'rss_file'=>['path'=>$root.'/web/rss.php','exists'=>is_file($root.'/web/rss.php'),'writable'=>is_file($root.'/web/rss.php')&&is_writable($root.'/web/rss.php')],
    ];
    // Eigenständiges CMS ohne Portal: Startseite und Feed liefert die WordPress-Schicht aus, es gibt keine Dateien dazu
    if(!rrw_pack_available())unset($checks['index_file'],$checks['rss_file']);
    $ok=true;foreach($checks as $x){if(empty($x['writable'])){$ok=false;break;}}
    rrw_json(['status'=>'ok','healthy'=>$ok,'checks'=>$checks,'storage'=>'/cms/data/site.json','news'=>'/cms/data/news.json']);
}
// Website-Zustand (wie WordPress' "Site Health"): rein lesende Selbstdiagnose. Prüft vor allem die
// Punkte, die im Betrieb sonst nur als diffuse Folgefehler sichtbar werden - insbesondere die
// Anbindung des Control-Center-Logins (Session-Secret + Rechte-DB), fehlende PHP-Erweiterungen,
// beschädigte Datendateien und zu kleine Upload-Limits. Status je Prüfung: good | warn | critical.
if($action==='site_health'){
    rrw_auth(false);
    $items=[];
    $add=function(string $group,string $label,string $status,string $detail)use(&$items){$items[]=['group'=>$group,'label'=>$label,'status'=>$status,'detail'=>$detail];};
    $bytes=function(string $v):int{$v=trim($v);if($v==='')return 0;$n=(float)$v;switch(strtolower(substr($v,-1))){case 'g':$n*=1024;case 'm':$n*=1024;case 'k':$n*=1024;}return (int)$n;};
    $fmt=function(int $n):string{if($n<1048576)return round($n/1024).' KB';if($n<1073741824)return round($n/1048576,1).' MB';return round($n/1073741824,2).' GB';};
    // Funktioniert der HTTP-Weg zum Control Center, sind lokaler SQLite-Treiber und Rechte-DB nur
    // eine Abkürzung, keine Voraussetzung - dann dürfen diese Zeilen nicht als Problem erscheinen.
    $probe=rrw_standalone()?['ok'=>false,'at'=>time(),'attempts'=>[]]:rrw_control_center_selfcall_probe(!empty(rrw_body()['force']));

    $add('Server','PHP-Version',version_compare(PHP_VERSION,'8.1.0','>=')?'good':'critical',PHP_VERSION.(version_compare(PHP_VERSION,'8.1.0','>=')?'':' - mindestens 8.1 erforderlich'));
    $https=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||(($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https');
    $add('Server','HTTPS',$https?'good':'warn',$https?'Verwaltung läuft verschlüsselt':'Verwaltung wird ohne HTTPS aufgerufen');
    $sqliteDriver=rrw_control_center_db_driver();
    $add('PHP-Erweiterungen','SQLite-Treiber',($sqliteDriver!==''||$probe['ok'])?'good':'warn',$sqliteDriver==='pdo_sqlite'?'pdo_sqlite verfügbar':($sqliteDriver==='sqlite3'?'SQLite3-Klasse verfügbar (pdo_sqlite fehlt - Datenbank-Spiegel nicht nutzbar, Control-Center-Rechte werden über SQLite3 gelesen)':'weder pdo_sqlite noch SQLite3 verfügbar - '.($probe['ok']?'nicht erforderlich, Control-Center-Rechte werden per HTTP beim Control Center geprüft (Datenbank-Spiegel nicht nutzbar)':'Control-Center-Rechte können lokal nicht gelesen werden')));
    foreach([
        ['curl',function_exists('curl_init'),'externe Feeds und Sicherheitsnetz der Berechtigungsprüfung'],
        ['gd',function_exists('imagecreatetruecolor'),'Bildgrößen-Varianten in der Medienbibliothek'],
        ['fileinfo',class_exists('finfo'),'Typprüfung bei Uploads'],
        ['zip',class_exists('ZipArchive'),'Backups und Plugin-/Theme-Installation'],
        ['mbstring',function_exists('mb_substr'),'Umlaute und Textlängen in Beiträgen'],
    ] as [$ext,$ok,$use])$add('PHP-Erweiterungen',$ext,$ok?'good':'warn',$ok?'verfügbar - '.$use:'fehlt - betrifft: '.$use);
    $uploadMax=min($bytes((string)ini_get('upload_max_filesize')),$bytes((string)ini_get('post_max_size')));
    $add('Server','Upload-Limit',$uploadMax>=12582912?'good':'warn',$fmt($uploadMax).' (upload_max_filesize / post_max_size)'.($uploadMax>=12582912?'':' - Medien bis 12 MB können sonst nicht hochgeladen werden'));
    $mem=$bytes((string)ini_get('memory_limit'));
    $add('Server','PHP-Speicherlimit',($mem<=0||$mem>=134217728)?'good':'warn',$mem<=0?'unbegrenzt':$fmt($mem).($mem>=134217728?'':' - für Bildvarianten sind 128 MB empfohlen'));

    $add('Anmeldung','Lokaler CMS-Zugang',rrw_local_auth_configured()?'good':'warn',rrw_local_auth_configured()?'eingerichtet - Login direkt über /cms/ möglich':'nicht eingerichtet - Login nur über das Control Center möglich');
    $add('Anmeldung','Betriebsmodus','good',rrw_standalone()?'eigenständig - Anmeldung nur lokal, keine Anfragen an das '.rrw_product_control_center():'mit '.rrw_product_control_center().'-Anbindung (Standard)');
    if(!rrw_standalone()){
    $secret=rrw_control_center_secret_available();
    $ccDb=__DIR__.'/../control/radio_stats_crazy.sqlite';
    $ccDbFile=is_file($ccDb)&&is_readable($ccDb);
    $ccDbOk=$ccDbFile&&rrw_control_center_db_rows('SELECT 1 FROM users LIMIT 1')!==null;
    $add('Anmeldung','Control-Center-Sitzungsprüfung',$secret?'good':'warn',$secret?'control/.session_secret vorhanden - Control-Center-Tokens werden lokal verifiziert':'control/.session_secret fehlt - Control-Center-Tokens werden ausschließlich per HTTP beim Control Center geprüft');
    $found=[];foreach(array_merge(glob(__DIR__.'/../control/*.sqlite')?:[],glob(__DIR__.'/../control/*/*.sqlite')?:[],glob(__DIR__.'/../control/*.db')?:[],glob(__DIR__.'/../control/*/*.db')?:[]) as $f)$found[]=substr($f,strlen(__DIR__.'/../control/'));
    $add('Anmeldung','Control-Center-Rechtedatenbank',($ccDbOk||$probe['ok'])?'good':'warn',$ccDbOk?'control/radio_stats_crazy.sqlite lesbar über '.$sqliteDriver:(!$ccDbFile?'control/radio_stats_crazy.sqlite nicht vorhanden'.($found?' - gefundene Datenbankdateien unter control/: '.implode(', ',array_slice($found,0,8)):''):'Datei vorhanden ('.$fmt((int)@filesize($ccDb)).'), aber nicht abfragbar - '.($sqliteDriver===''?'kein SQLite-Treiber':'Tabelle users fehlt oder Datei beschädigt')).($probe['ok']?' - nicht erforderlich, Rollen/Rechte kommen per HTTP vom Control Center':' - Rollen/Rechte für Control-Center-Logins (außer Inhaber-Account) können nicht ermittelt werden'));
    $probeDetail=implode(' | ',array_map(fn($x)=>$x['via'].': '.($x['ok']?'erreichbar, HTTP '.$x['http'].($x['http']===401?' auf Probe-Token (korrekt)':''):($x['error']!==''?$x['error']:'HTTP '.$x['http'])).($x['ip']!==''?' ('.$x['ip'].')':''),$probe['attempts']));
    $add('Anmeldung','Control-Center-Selbstaufruf',$probe['ok']?'good':'warn',($probe['ok']?'HTTP-Berechtigungsprüfung beim Control Center funktioniert - Control-Center-Logins werden darüber aufgelöst - ':'Control Center vom Server aus nicht erreichbar - Control-Center-Logins ohne lokale Rechte-DB scheitern mit "Berechtigungsprüfung nicht erreichbar" - ').$probeDetail.' (gemessen '.date('H:i',(int)$probe['at']).')');
    }

    foreach([['site.json',$siteFile,true],['news.json',$newsFile,true],['comments.json',$commentsFile,false],['local-sessions.local.json',$dataDir.'/local-sessions.local.json',false]] as [$name,$file,$required]){
        if(!is_file($file)){$add('Daten',$name,$required?'critical':'good',$required?'fehlt':'noch nicht angelegt (wird bei Bedarf erzeugt)');continue;}
        $raw=(string)@file_get_contents($file);$valid=trim($raw)===''?!$required:json_decode($raw,true)!==null;
        $add('Daten',$name,$valid?'good':'critical',$valid?$fmt((int)strlen($raw)).', gültiges JSON':'beschädigt - enthält kein gültiges JSON');
    }
    $fsOk=is_writable($dataDir)&&is_writable($genDir)&&is_writable($mediaDir)&&is_writable($root);
    $add('Dateisystem','Schreibrechte',$fsOk?'good':'critical',$fsOk?'Daten, Generiert, Medien und Website-Root beschreibbar':'mindestens ein Ordner ist nicht beschreibbar - Details unter "Dateisystem & Veröffentlichung"');
    $size=0;$count=0;
    foreach([$dataDir,$mediaDir] as $dir){if(!is_dir($dir))continue;try{foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS)) as $f){if(++$count>5000)break 2;$size+=(int)$f->getSize();}}catch(Throwable $e){}}
    $add('Dateisystem','Belegter Speicher (Daten + Medien)',$size>1073741824?'warn':'good',$fmt($size).($count>5000?' (mehr als 5000 Dateien, Schätzung)':' in '.$count.' Dateien'));

    $summary=['good'=>0,'warn'=>0,'critical'=>0];foreach($items as $i)$summary[$i['status']]++;
    $overall=$summary['critical']>0?'critical':($summary['warn']>0?'warn':'good');
    rrw_json(['status'=>'ok','overall'=>$overall,'summary'=>$summary,'items'=>$items,'checked_at'=>date('Y-m-d H:i:s')]);
}
if($action==='revs'){rrw_auth(false);rrw_json(['status'=>'ok','revs'=>rrw_site_revs($site)]);}
if($action==='get'){rrw_auth(false);$cfgOut=$site;if(function_exists('rrw_assistant_admin_view'))$cfgOut['assistant']=rrw_assistant_admin_view((array)($site['assistant']??[]));rrw_json(['status'=>'ok','config'=>$cfgOut,'revs'=>rrw_site_revs($site),'storage'=>'cms/data/site.json','packs'=>rrw_pack_status($site)]);}
if($action==='pack_status'){rrw_auth(false);rrw_json(['status'=>'ok','packs'=>rrw_pack_status($site)]);}
const RRW_ADMIN_ONLY_SECTIONS=['apps','alexa','assistant','services','brands','storage','backup','plugins'];
if($action==='save'){
    $authUser=rrw_auth(false);$b=rrw_body();$section=(string)($b['section']??'');
    // Sicherheitsrelevante Bereiche (Apps, Alexa, KI-Assistent mit API-Schlüsseln, Dienste, Domains, Speicher, Backup, Plugins) wie ihre eigenen Lese-/Schreibaktionen
    // nur für Administratoren, nicht für Redakteure: sonst ließe sich die Sperre dieser Aktionen über das allgemeine Speichern umgehen.
    if(in_array($section,RRW_ADMIN_ONLY_SECTIONS,true)&&empty($authUser['superadmin']))rrw_json(['status'=>'error','message'=>'Nur Administratoren dürfen diesen Bereich ändern'],403);
    rrw_site_lock($dataDir);
    $site=rrw_ensure_site_defaults(rrw_read_json($siteFile,[]));if(!isset($site['theme'])||!is_array($site['theme']))$site['theme']=['active'=>rrw_default_theme_id()];$GLOBALS['RRW_SITE']=$site;
    // Bereiche, die eine frische Installation noch nicht angelegt hat (die Oberfläche speichert sie beim ersten Mal): leer anlegen statt „Unbekannter CMS-Bereich“
    if(!array_key_exists($section,$site)&&in_array($section,['pages','social','legal','apps','core_network'],true))$site[$section]=in_array($section,['pages'],true)?[]:(in_array($section,['core_network'],true)?['stations'=>[]]:[]);
    if(!array_key_exists($section,$site))rrw_json(['status'=>'error','message'=>'Unbekannter CMS-Bereich'],400);
    $baseRev=(string)($b['base_rev']??'');
    if($baseRev!==''&&$baseRev!==rrw_section_rev($site,$section))rrw_json(['status'=>'conflict','message'=>'Dieser Bereich wurde inzwischen an anderer Stelle geändert (z. B. im Control Center oder von einer anderen Person). Bitte neu laden, damit nichts überschrieben wird.','rev'=>rrw_section_rev($site,$section)],409);
    $value=$b['value']??null;
    // Theme-Layout und die pro Theme gespeicherten Anpassungen gehören dem Theme-System, nicht
    // dem Formular: beim generischen Speichern des Theme-Bereichs bleiben sie erhalten.
    if($section==='theme'&&is_array($value)){$value+=['layout'=>$site['theme']['layout']??null,'mods'=>$site['theme']['mods']??[]];}
    if($section==='assistant'&&function_exists('rrw_assistant_merge_keys'))$value=rrw_assistant_merge_keys((array)($site['assistant']??[]),$value);
    // Optionale Zusatzfelder, die nicht im Formular stehen (von der WordPress-Schicht geschrieben), bleiben beim Speichern erhalten.
    if(is_array($value)&&in_array($section,['portal','legal'],true)){$kx=$section==='portal'?'tagline':'email';if(!array_key_exists($kx,$value)&&isset($site[$section][$kx]))$value[$kx]=$site[$section][$kx];}
    $clean=rrw_clean_section($section,$value);
    if($section==='assistant'&&function_exists('rrw_assistant_admin_view')){$site[$section]=$clean;try{rrw_publish($site,$siteFile,$genDir,$root);}catch(Throwable $e){rrw_json(['status'=>'error','message'=>'Dateispeicherung fehlgeschlagen: '.$e->getMessage()],500);}rrw_json(['status'=>'ok','value'=>rrw_assistant_admin_view($clean),'rev'=>rrw_section_rev(rrw_ensure_site_defaults(rrw_read_json($siteFile,[])),$section),'file'=>'cms/data/site.json','published'=>true]);}if($clean===null)rrw_json(['status'=>'error','message'=>'Ungültige Daten'],400);$prevSection=$site[$section]??null;$site[$section]=$clean;
    if($section==='pages'){$u0=rrw_auth(false);rrw_page_revisions_record($dataDir,$prevSection,$clean,(string)($u0['display_name']??$u0['user']??''));}
    if($section==='widget_areas'||$section==='theme')$site=rrw_theme_remember_mods($site);
    try{rrw_publish($site,$siteFile,$genDir,$root);}catch(Throwable $e){rrw_json(['status'=>'error','message'=>'Dateispeicherung fehlgeschlagen: '.$e->getMessage()],500);}
    if(in_array($section,['apps','alexa'],true)){$chg=rrw_apps_change_summary($section,$prevSection,$clean);if($chg!=='')rrw_log_activity($activityLogFile,rrw_auth(false),$section.'_save',$chg);}
    rrw_json(['status'=>'ok','value'=>$clean,'rev'=>rrw_section_rev(rrw_ensure_site_defaults(rrw_read_json($siteFile,[])),$section),'file'=>'cms/data/site.json','published'=>true]);
}
if($action==='media_upload'){rrw_auth(false);rrw_json(['status'=>'ok','url'=>rrw_upload('content')]);}
if($action==='branding_upload'){
    rrw_auth(false);$kind=preg_replace('/[^a-z0-9_-]/','',strtolower((string)($_GET['kind']??'')));
    $allowed=['portal_logo','portal_icon','favicon','android_inapp_logo','android_startscreen','android_app_icon','windows_logo'];
    if(!in_array($kind,$allowed,true))rrw_json(['status'=>'error','message'=>'Unbekannter Asset-Typ'],400);
    $result=rrw_media_library_upload_variants(rrw_media_sizes($_POST['sizes']??'64,128,192,256,512,1024,1600'),max(45,min(100,(int)($_POST['quality']??90))));
    $item=$result['item'];$path='library/'.(string)($item['id']??'');$source=rrw_media_pick_variant($item,'auto',$kind);$sync=rrw_sync_branding_asset($kind,$source,$root);
    $site['branding'][$kind]=$sync['url'];$site['branding_media'][$kind]=['path'=>$path,'size'=>'auto','source_url'=>$source,'assigned_at'=>date(DATE_ATOM)];
    rrw_publish($site,$siteFile,$genDir,$root);
    rrw_json(['status'=>'ok','url'=>$sync['url'],'branding'=>$site['branding'],'branding_media'=>$site['branding_media'],'updated_files'=>$sync['files'],'warnings'=>array_values(array_merge($result['warnings']??[],$sync['warnings']??[]))]);
}
if($action==='brand_asset_assign'){
    rrw_auth(false);$b=rrw_body();$brandId=rrw_brand_id_clean((string)($b['brand_id']??''));$kind=preg_replace('/[^a-z_]/','',(string)($b['kind']??''));
    if(!in_array($kind,['logo','logo_dark','logo_light','favicon','touch_icon','og_image','social_image'],true))rrw_json(['status'=>'error','message'=>'Unbekannter Asset-Typ'],400);
    $reg=rrw_brands_registry($site);$idx=null;foreach($reg['items'] as $i=>$x)if($x['id']===$brandId)$idx=$i;
    if($idx===null)rrw_json(['status'=>'error','message'=>'Marke nicht gefunden'],404);
    $url='';
    if(($b['path']??'')!==''){
        $item=rrw_media_item_from_path((string)$b['path']);if(!$item)rrw_json(['status'=>'error','message'=>'Medium nicht gefunden'],404);
        $size=$b['size']??'auto';$url=rrw_media_pick_variant($item,$size,$kind==='logo'?'portal_logo':($kind==='favicon'?'favicon':'portal_icon'));
    } elseif(($b['url']??'')!=='') $url=rrw_brand_asset_clean((string)$b['url']);
    $reg['items'][$idx][$kind]=$url;$site['brands']=$reg;
    rrw_publish($site,$siteFile,$genDir,$root);
    rrw_log_activity($activityLogFile,rrw_auth(false),'brand_asset','Marke „'.$reg['items'][$idx]['name'].'“: '.$kind.($url!==''?' gesetzt':' entfernt'));
    rrw_json(['status'=>'ok','url'=>$url,'brands'=>$site['brands']]);
}
if($action==='branding_assign'){
    rrw_auth(false);$b=rrw_body();$kind=preg_replace('/[^a-z0-9_-]/','',strtolower((string)($b['kind']??'')));
    $allowed=['portal_logo','portal_icon','favicon','android_inapp_logo','android_startscreen','android_app_icon','windows_logo'];
    if(!in_array($kind,$allowed,true))rrw_json(['status'=>'error','message'=>'Unbekannter Branding-Typ'],400);
    $item=rrw_media_item_from_path((string)($b['path']??''));if(!$item)rrw_json(['status'=>'error','message'=>'Medium nicht gefunden'],404);
    $size=$b['size']??'auto';$url=rrw_media_pick_variant($item,$size,$kind);$sync=rrw_sync_branding_asset($kind,$url,$root);$site['branding'][$kind]=$sync['url'];$site['branding_media'][$kind]=['path'=>(string)($b['path']??''),'size'=>$size,'source_url'=>$url,'assigned_at'=>date(DATE_ATOM)];rrw_publish($site,$siteFile,$genDir,$root);
    rrw_json(['status'=>'ok','url'=>$sync['url'],'source_url'=>$url,'branding'=>$site['branding'],'branding_media'=>$site['branding_media'],'updated_files'=>$sync['files'],'warnings'=>$sync['warnings']]);
}
if($action==='core_validate'){rrw_auth(false);$st=strtolower(trim((string)($_GET['station']??'')));if(!preg_match('/^[a-z0-9][a-z0-9_-]{1,62}$/',$st))rrw_json(['status'=>'error','message'=>'Ungültiger Sendername'],400);$raw=@file_get_contents('https://api.laut.fm/station/'.rawurlencode($st));$d=json_decode((string)$raw,true);if(!is_array($d)||empty($d['name']))rrw_json(['status'=>'error','message'=>'Sender bei laut.fm nicht gefunden'],404);rrw_json(['status'=>'ok','station'=>$d]);}
if($action==='core_add'){rrw_auth(false);$b=rrw_body();$st=strtolower(trim((string)($b['station']??'')));if(!preg_match('/^[a-z0-9][a-z0-9_-]{1,62}$/',$st))rrw_json(['status'=>'error','message'=>'Ungültiger Sendername'],400);$list=(array)($site['core_network']['stations']??[]);if(!in_array($st,$list,true))$list[]=$st;$site['core_network']=rrw_clean_section('core_network',['stations'=>$list]);rrw_publish($site,$siteFile,$genDir,$root);rrw_json(['status'=>'ok','stations'=>$site['core_network']['stations']]);}
if($action==='core_remove'){rrw_auth(true);$b=rrw_body();$st=strtolower(trim((string)($b['station']??'')));if($st==='ricorewi')rrw_json(['status'=>'error','message'=>'ricorewi ist geschützt'],400);if(strtolower(trim((string)($b['typed']??'')))!==$st||trim((string)($b['confirm']??''))!=='ENTFERNEN '.$st)rrw_json(['status'=>'error','message'=>'Sicherheitsbestätigung stimmt nicht'],400);$list=array_values(array_filter((array)($site['core_network']['stations']??[]),fn($x)=>$x!==$st));$site['core_network']=rrw_clean_section('core_network',['stations'=>$list]);rrw_publish($site,$siteFile,$genDir,$root);rrw_json(['status'=>'ok','stations'=>$site['core_network']['stations']]);}
if($action==='media_library_list'){rrw_auth(false);rrw_json(['status'=>'ok','items'=>rrw_media_library_items($site)]);}
if($action==='media_library_upload'){rrw_auth(false);$sizes=rrw_media_sizes($_POST['sizes']??'64,128,192,256,512,1024,1600');$quality=max(45,min(100,(int)($_POST['quality']??86)));$result=rrw_media_library_upload_variants($sizes,$quality);rrw_json(['status'=>'ok']+$result);}
if($action==='media_library_delete'){
    rrw_auth(false);$b=rrw_body();$rel=rrw_safe_media_rel((string)($b['path']??''));
    if($rel===''||!str_starts_with($rel,'library/'))rrw_json(['status'=>'error','message'=>'Nur Medien aus der Bibliothek können hier gelöscht werden'],400);
    foreach((array)($site['branding_media']??[]) as $kind=>$ref){
        if((string)($ref['path']??'')===$rel)rrw_json(['status'=>'error','message'=>'Medium wird noch als '.$kind.' verwendet. Erst im Branding ein anderes Medium zuweisen.'],409);
    }
    $target=__DIR__.'/media/'.$rel;
    if(is_dir($target)){foreach(glob($target.'/*')?:[] as $x)if(is_file($x))@unlink($x);if(!@rmdir($target))rrw_json(['status'=>'error','message'=>'Mediengruppe konnte nicht gelöscht werden'],500);}
    elseif(is_file($target)){if(!@unlink($target))rrw_json(['status'=>'error','message'=>'Datei konnte nicht gelöscht werden'],500);}
    else rrw_json(['status'=>'error','message'=>'Medium nicht gefunden'],404);
    rrw_json(['status'=>'ok']);
}
// Radio-Erweiterung (Menü „Radio“, erscheint bei aktivem Radio-Theme): Sender, Datenquelle, Sendeplan. Konfiguration in cms/data/.tools/radio.json
if(str_starts_with($action,'radio_')){
    require_once __DIR__.'/lib/radio.php';
    if($action==='radio_state'){ rrw_auth(false);rrw_json(['status'=>'ok','active'=>rrw_radio_theme_active($dataDir),'theme'=>RRW_RADIO_THEME]); }
    $rdUser=rrw_auth(true);
    if($action==='radio_get')rrw_json(['status'=>'ok','config'=>rrw_radio_load($dataDir),'sources'=>RRW_RADIO_SOURCES,'active'=>rrw_radio_theme_active($dataDir)]);
    if($action==='radio_save'){
        $rb=rrw_body();if(!is_array($rb['config']??null))rrw_json(['status'=>'error','message'=>'Konfiguration fehlt'],400);
        try{ $c=rrw_radio_save($dataDir,$rb['config']); }catch(Throwable $e){ rrw_json(['status'=>'error','message'=>$e->getMessage()],500); }
        rrw_log_activity($activityLogFile,$rdUser,'radio_save','Radio-Einstellungen gespeichert ('.count($c['stations']).' Sender)');
        rrw_json(['status'=>'ok','config'=>$c]);
    }
    if($action==='radio_test'){
        $rb=rrw_body();$c=rrw_radio_clean(['stations'=>[is_array($rb['station']??null)?$rb['station']:[]]]);if(!$c['stations'])rrw_json(['status'=>'error','message'=>'Kein Sender angegeben'],400);
        rrw_json(['status'=>'ok']+rrw_radio_test($c['stations'][0]));
    }
    rrw_json(['status'=>'error','message'=>'Unbekannte Aktion'],400);
}
// WordPress-Plugins (PHP): Laufzeit unter cms/wp, Plugins unter cms/wp-content/plugins. Nur Superadmins.
if(str_starts_with($action,'wp_')){
    $wpUser=rrw_auth(true);
    $GLOBALS['RRW_SITE']=$site;
    // Sandbox: abgetrennter Spielraum zwischen Live und Deployment (eigene Optionen und Themes, siehe wp/sandbox.php)
    require_once __DIR__.'/wp/sandbox.php';
    if(str_starts_with($action,'wp_sandbox')){
        $sb=rrw_body();$sbOut=function(array $extra=[]) { $m=rrw_sbx_meta();return ['status'=>'ok','exists'=>rrw_sbx_exists(),'created'=>(string)($m['created']??''),'last_publish'=>(string)($m['last_publish']??''),'url'=>rrw_sbx_exists()?rrw_sbx_url():'','diff'=>rrw_sbx_exists()?rrw_sbx_diff():null,'backups'=>rrw_sbx_backups()]+$extra; };
        if($action==='wp_sandbox')rrw_json($sbOut());
        $fail=fn(string $m,int $c=422)=>rrw_json(['status'=>'error','message'=>$m],$c);
        if($action==='wp_sandbox_create'){ $e=rrw_sbx_create();if($e!==null)$fail($e);rrw_log_activity($activityLogFile,$wpUser,'wp_sandbox','Sandbox angelegt');rrw_json($sbOut()); }
        if($action==='wp_sandbox_reset'){ $e=rrw_sbx_reset();if($e!==null)$fail($e);rrw_log_activity($activityLogFile,$wpUser,'wp_sandbox','Sandbox auf den Live-Stand zurückgesetzt');rrw_json($sbOut()); }
        if($action==='wp_sandbox_rotate'){ $e=rrw_sbx_rotate();if($e!==null)$fail($e);rrw_log_activity($activityLogFile,$wpUser,'wp_sandbox','Sandbox-Link erneuert');rrw_json($sbOut()); }
        if($action==='wp_sandbox_delete'){ rrw_sbx_delete();rrw_log_activity($activityLogFile,$wpUser,'wp_sandbox','Sandbox gelöscht');rrw_json($sbOut()); }
        if($action==='wp_sandbox_publish'){ $r=rrw_sbx_publish();if(!$r['ok'])$fail($r['message']);rrw_log_activity($activityLogFile,$wpUser,'wp_sandbox','Sandbox live gestellt ('.$r['options'].' Einstellungen'.($r['themes']?', Themes: '.implode(', ',$r['themes']):'').')');rrw_json($sbOut(['published'=>$r])); }
        if($action==='wp_sandbox_rollback'){ $e=rrw_sbx_rollback((string)($sb['backup']??''));if($e!==null)$fail($e);rrw_log_activity($activityLogFile,$wpUser,'wp_sandbox','Live-Stand aus Sicherung '.(string)$sb['backup'].' wiederhergestellt');rrw_json($sbOut()); }
        $fail('Unbekannte Aktion',404);
    }
    if(!empty($_GET['sandbox'])){   // Theme-Verwaltung in der Sandbox statt auf der Live-Seite
        if(!in_array($action,['wp_themes','wp_theme_search','wp_theme_install','wp_theme_upload','wp_theme_activate','wp_theme_deactivate','wp_theme_delete','wp_theme_preview','wp_theme_customize','wp_theme_customize_draft','wp_theme_customize_save','wp_theme_customize_changeset'],true))rrw_json(['status'=>'error','message'=>'Diese Aktion gibt es in der Sandbox nicht.'],400);
        if(!rrw_sbx_exists())rrw_json(['status'=>'error','message'=>'Es gibt noch keine Sandbox.'],404);
        rrw_sbx_enter();
    }
    require_once __DIR__.'/wp/load.php';require_once __DIR__.'/wp/installer.php';require_once __DIR__.'/wp/coreassets.php';
    // Plugins registrieren ihre Hooks schon beim Laden abhängig von $_GET['page']: die gewünschte Seite vor dem Start bekanntmachen.
    if($action==='wp_admin_page'){
        $pre=rrw_body();$qs=[];parse_str((string)($pre['query']??''),$qs);if(strtoupper((string)($pre['method']??'GET'))==='POST'){ $pb=[];parse_str((string)($pre['body']??''),$pb);$qs=$pb+$qs; }
        $pg=(string)($pre['page']??'');if(str_contains($pg,'&')){ [$pg,$rest]=explode('&',$pg,2);$ex=[];parse_str($rest,$ex);$qs=$ex+$qs; }$qs['page']=$pg;$_GET=$qs;$GLOBALS['plugin_page']=$pg;$GLOBALS['pagenow']='admin.php';$_REQUEST=array_merge($_REQUEST,$qs);
    }
    // Customizer für WordPress-Themes: das gewählte Theme vor dem Start einschalten (Filter), damit seine functions.php/customize_register laufen
    $wpCz=in_array($action,['wp_theme_customize','wp_theme_customize_draft','wp_theme_customize_save','wp_theme_customize_changeset'],true);
    if($wpCz){
        require_once __DIR__.'/wp/customizer-api.php';$czSlug=(string)(rrw_body()['slug']??'');
        $czKnown=array_column(rrw_wpi_list_themes(),null,'slug');
        if(!isset($czKnown[$czSlug]))rrw_json(['status'=>'error','message'=>'Theme nicht gefunden'],404);
        if(!empty($czKnown[$czSlug]['error']))rrw_json(['status'=>'error','message'=>$czKnown[$czSlug]['error']],422);
        rrw_wpc_use_theme($czSlug);ob_start();
    }
    // Homepage-Baukasten (visueller Abschnitts-Editor): Layout des Themes „elvado-baukasten“ lesen/speichern (Option elvado_bk_layout)
    $wpBk=in_array($action,['wp_bk_get','wp_bk_save'],true);
    if($wpBk){ require_once __DIR__.'/wp/customizer-api.php';if(!rrw_wpc_use_theme('elvado-baukasten'))rrw_json(['status'=>'error','message'=>'Das Theme „ElvadoPress Baukasten“ ist nicht installiert.'],404); }
    rrw_wp_boot(['user'=>['id'=>1,'login'=>(string)($wpUser['user']??'admin'),'name'=>(string)($wpUser['display_name']??''),'role'=>'administrator'],'admin'=>true,'theme'=>$wpCz||$wpBk||str_starts_with($action,'wp_admin_')]);
    if(str_starts_with($action,'wp_admin_'))rrw_wp_core_register();
    $b=rrw_body();
    $wpList=function() use($dataDir){
        $act=get_option_active_plugins();$errs=(array)get_option('rrw_wp_plugin_errors',[]);$out=[];
        foreach(get_plugins() as $file=>$d)$out[]=['file'=>$file,'name'=>$d['Name'],'version'=>$d['Version'],'author'=>strip_tags($d['Author']),'description'=>mb_substr(strip_tags($d['Description']),0,300),'uri'=>$d['PluginURI'],'requires_php'=>$d['RequiresPHP'],'active'=>in_array($file,$act,true),'error'=>(string)($errs[$file]??'')];
        return $out;
    };
    if($action==='wp_plugins')rrw_json(['status'=>'ok','plugins'=>$wpList(),'wp_version'=>RRW_WP_VERSION,'zip'=>class_exists('ZipArchive')]);
    if($action==='wp_plugin_search'){
        $r=rrw_wpi_search_plugins($dataDir,(string)($_GET['q']??''),(int)($_GET['page']??1));if(!$r['ok'])rrw_json(['status'=>'error','message'=>$r['message']],502);
        $have=array_map(fn($f)=>explode('/',$f)[0],array_keys(get_plugins()));foreach($r['items'] as &$it)$it['installed']=in_array($it['slug'],$have,true);unset($it);
        rrw_json(['status'=>'ok']+$r);
    }
    if($action==='wp_plugin_install'||$action==='wp_plugin_upload'){
        $zip='';
        try{
            if($action==='wp_plugin_install'){ $slug=(string)($b['slug']??'');$zip=rrw_wpi_download_plugin($slug);$res=rrw_wpi_install_plugin_zip($zip,$slug); }
            else{ if(empty($_FILES['file'])||!is_uploaded_file($_FILES['file']['tmp_name']))rrw_json(['status'=>'error','message'=>'Keine ZIP-Datei'],400);
                if((int)$_FILES['file']['size']>41943040)rrw_json(['status'=>'error','message'=>'Das ZIP ist größer als 40 MB'],400);
                $res=rrw_wpi_install_plugin_zip($_FILES['file']['tmp_name']); }
        }catch(Throwable $e){ if($zip!==''&&is_file($zip))@unlink($zip); rrw_json(['status'=>'error','message'=>$e->getMessage()],422); }
        if($zip!==''&&is_file($zip))@unlink($zip);
        try{ $pl=get_plugins();foreach($pl as $pf=>$pd)if(explode('/',$pf)[0]===$res['slug']){ rrw_wpi_fetch_translation('plugin',$res['slug'],(string)$pd['Version']);break; } }catch(Throwable $e){}
        rrw_log_activity($activityLogFile,$wpUser,'wp_plugin_install','WordPress-Plugin „'.$res['slug'].'“ installiert');
        rrw_json(['status'=>'ok','slug'=>$res['slug'],'plugins'=>$wpList()]);
    }
    /* ── Sprachpakete (Deutsch) für Core, Plugins und Themes ── */
    if($action==='wp_translations'){ $r=rrw_wpi_fetch_all_translations('de_DE');rrw_log_activity($activityLogFile,$wpUser,'wp_translations','WordPress-Sprachpakete geladen ('.$r['ok'].' ok, '.$r['fail'].' ohne Paket)');rrw_json(['status'=>'ok']+$r); }
    /* ── Updates aus dem WordPress-Verzeichnis ── */
    if($action==='wp_updates')rrw_json(['status'=>'ok','updates'=>rrw_wpi_updates($dataDir)]);
    /* ── Automatische Updates (Plugins, Themes, Sprachpakete, Kernressourcen) ── */
    if($action==='wp_version_status'){ require_once __DIR__.'/wp/wpversion.php';rrw_json(['status'=>'ok']+rrw_wpv_status($dataDir)); }
    if($action==='wp_version_update'){
        require_once __DIR__.'/wp/wpversion.php';$r=rrw_wpv_update($dataDir,'all',(string)($b['version']??''));
        if(!empty($r['ok']))rrw_log_activity($activityLogFile,$wpUser,'wp_version','WordPress-Version '.$r['version'].' übernommen');
        if(!$r['ok'])rrw_json(['status'=>'error','message'=>$r['message']],!empty($r['none'])?409:502);
        rrw_json(['status'=>'ok','message'=>$r['message'],'report'=>$r['report']??null]+rrw_wpv_status($dataDir));
    }
    if($action==='wp_version_rollback'){
        require_once __DIR__.'/wp/wpversion.php';$r=rrw_wpv_rollback();
        if(!$r['ok'])rrw_json(['status'=>'error','message'=>$r['message']],422);
        rrw_log_activity($activityLogFile,$wpUser,'wp_version','WordPress-Version auf '.$r['version'].' zurückgesetzt');
        rrw_json(['status'=>'ok','message'=>$r['message']]+rrw_wpv_status($dataDir));
    }
    if($action==='wp_autoupdate_get'){ require_once __DIR__.'/wp/autoupdate.php';$s=rrw_wpau_get();rrw_json(['status'=>'ok','settings'=>$s,'due'=>rrw_wpau_due($s),'cron'=>is_file(dirname(__DIR__).'/cron/wp-cron.php')]); }
    if($action==='wp_autoupdate_set'){
        require_once __DIR__.'/wp/autoupdate.php';$s=rrw_wpau_set((array)($b['settings']??[]));
        rrw_log_activity($activityLogFile,$wpUser,'wp_autoupdate','Automatische WordPress-Updates: Plugins '.$s['plugins'].', Themes '.$s['themes'].', Sprachpakete '.($s['translations']?'an':'aus'));
        rrw_json(['status'=>'ok','settings'=>$s,'due'=>rrw_wpau_due($s)]);
    }
    if($action==='wp_autoupdate_run'||$action==='wp_autoupdate_tick'){
        require_once __DIR__.'/wp/autoupdate.php';$force=$action==='wp_autoupdate_run';
        $r=rrw_wpau_run($dataDir,$force);
        if($r['ran']&&($r['updated']||$r['failed']))rrw_log_activity($activityLogFile,$wpUser,'wp_autoupdate_run','Automatische Updates: '.$r['updated'].' aktualisiert, '.$r['failed'].' fehlgeschlagen');
        $s=rrw_wpau_get();rrw_json(['status'=>'ok','result'=>$r,'settings'=>$s,'due'=>rrw_wpau_due($s)]);
    }
    if($action==='wp_autoupdate_rollback'){
        require_once __DIR__.'/wp/autoupdate.php';$r=rrw_wpau_rollback((string)($b['type']??''),(string)($b['slug']??''));
        if(!$r['ok'])rrw_json(['status'=>'error','message'=>$r['msg']],422);
        rrw_log_activity($activityLogFile,$wpUser,'wp_autoupdate_rollback','WordPress-'.($b['type']==='plugin'?'Plugin':'Theme').' „'.$b['slug'].'“ auf die vorherige Fassung zurückgesetzt');
        rrw_json(['status'=>'ok']);
    }
    if($action==='wp_update_apply'){
        $type=(string)($b['type']??'');$slug=(string)($b['slug']??'');$zip='';
        try{
            if($type==='plugin'){ $zip=rrw_wpi_download_plugin($slug);rrw_wpi_install_plugin_zip($zip,$slug); }
            elseif($type==='theme'){ $zip=rrw_wpi_download_theme($slug);rrw_wpi_install_theme_zip($zip,$slug); }
            else rrw_json(['status'=>'error','message'=>'Ungültiger Typ'],400);
        }catch(Throwable $e){ if($zip!==''&&is_file($zip))@unlink($zip); rrw_json(['status'=>'error','message'=>$e->getMessage()],422); }
        if($zip!==''&&is_file($zip))@unlink($zip);
        try{ if($type==='plugin'){ foreach(get_plugins() as $pf=>$pd)if(explode('/',$pf)[0]===$slug){ rrw_wpi_fetch_translation('plugin',$slug,(string)$pd['Version']);break; } } else foreach(rrw_wpi_list_themes() as $tt)if($tt['slug']===$slug){ rrw_wpi_fetch_translation('theme',$slug,(string)$tt['version']);break; } }catch(Throwable $e){}
        rrw_log_activity($activityLogFile,$wpUser,'wp_update','WordPress-'.($type==='plugin'?'Plugin':'Theme').' „'.$slug.'“ aktualisiert');
        rrw_json(['status'=>'ok','updates'=>rrw_wpi_updates($dataDir)]);
    }
    /* ── Plugin-Verwaltungsseiten (admin_menu, options.php, admin-post, admin-ajax) ── */
    /* ── Editoren im eigenen Tab (Elementor): Sitzungs-Token, WordPress-Seiten anlegen/listen ── */
    if($action==='wp_session_open'){
        require_once __DIR__.'/wp/session.php';
        $to=rrw_wp_sess_target((string)($b['to']??''));if(!$to)rrw_json(['status'=>'error','message'=>'Ungültiges Ziel'],400);
        if(!is_file(RRW_WP_DATA.'/front-on'))rrw_json(['status'=>'error','message'=>'Aktiviere zuerst ein WordPress-Theme: Der Editor zeigt die Website live an.'],409);
        rrw_log_activity($activityLogFile,$wpUser,'wp_session','WordPress-Editor geöffnet: '.$to);
        rrw_json(['status'=>'ok','url'=>'/wp-admin/'.$to.(str_contains($to,'?')?'&':'?').'rrw_wp_login='.rrw_wp_sess_token_issue()]);
    }
    // Einheitliche Sicht „Alle Inhalte“: CMS-Beiträge, CMS-Seiten und WordPress-Datenbank in einer Liste (scope=all); Schreibbrücke ein-/ausschalten
    if($action==='wp_content_list'&&($_GET['scope']??'')==='all'){
        rrw_json(['status'=>'ok','items'=>rrw_wp_cms_content_overview(),'elementor'=>in_array('elementor/elementor.php',(array)get_option_active_plugins(),true),'bridge'=>rrw_wp_bridge_parts(),'bridge_parts'=>RRW_WP_BRIDGE_PARTS]);
    }
    if($action==='wp_bridge_set'){
        $parts=array_values(array_intersect(RRW_WP_BRIDGE_PARTS,array_map('strval',(array)($b['parts']??[]))));
        rrw_wp_bridge_set($parts);
        rrw_log_activity($activityLogFile,$wpUser,'wp_bridge','Schreibbrücke WordPress ↔ CMS: '.($parts?implode(', ',$parts):'aus'));
        rrw_json(['status'=>'ok','bridge'=>rrw_wp_bridge_parts()]);
    }
    if($action==='wp_content_list'){
        global $wpdb;
        $rows=$wpdb?$wpdb->get_results("SELECT ID,post_title,post_status,post_type,post_modified FROM {$wpdb->posts} WHERE post_type IN ('page','post') AND post_status IN ('publish','draft','private') ORDER BY ID DESC LIMIT 100"):[];
        $out=[];foreach((array)$rows as $r)$out[]=['id'=>(int)$r->ID,'title'=>(string)$r->post_title,'status'=>$r->post_status,'type'=>$r->post_type,'modified'=>$r->post_modified,'elementor'=>get_post_meta((int)$r->ID,'_elementor_edit_mode',true)==='builder','url'=>get_permalink((int)$r->ID)];
        rrw_json(['status'=>'ok','items'=>$out,'elementor'=>in_array('elementor/elementor.php',(array)get_option_active_plugins(),true)]);
    }
    if($action==='wp_page_create'){
        $title=trim((string)($b['title']??''));if($title===''||mb_strlen($title)>200)$title='Neue Seite';
        $id=wp_insert_post(['post_type'=>'page','post_title'=>$title,'post_status'=>'draft','post_content'=>'']);
        if(is_wp_error($id)||!$id)rrw_json(['status'=>'error','message'=>'Seite konnte nicht angelegt werden'],500);
        rrw_log_activity($activityLogFile,$wpUser,'wp_page','WordPress-Seite „'.$title.'“ angelegt');
        rrw_json(['status'=>'ok','id'=>(int)$id]);
    }
    if($action==='wp_core_status')rrw_json(['status'=>'ok']+rrw_wp_core_status());
    if($action==='wp_core_install'){
        $r=rrw_wp_core_install();if(!$r['ok'])rrw_json(['status'=>'error','message'=>$r['message']],502);
        rrw_log_activity($activityLogFile,$wpUser,'wp_core','WordPress-Kernressourcen installiert');
        rrw_json(['status'=>'ok']+rrw_wp_core_status()+['message'=>$r['message']]);
    }
    if($action==='wp_admin_rest'){
        require_once __DIR__.'/wp/admin.php';require_once __DIR__.'/wp/rest.php';
        $GLOBALS['rrw_wp_session_token']=hash('sha256',(string)($_SERVER['HTTP_X_ANMACHA_TOKEN']??''));
        $GLOBALS['rrw_wp_die_throws']=true;$GLOBALS['rrw_wp_serving_rest']=true;
        $hdr=[];foreach((array)($b['headers']??[]) as $k=>$v)if(is_string($k)&&is_scalar($v))$hdr[$k]=(string)$v;
        $path=(string)($b['path']??'');$path=preg_replace('#^.*?/wp-json#','',$path);$q=[];
        if(str_contains($path,'?')){ [$path,$qs]=explode('?',$path,2);parse_str($qs,$q); }
        $method=strtoupper((string)($b['method']??'GET'));
        foreach($hdr as $k=>$v)if(strcasecmp($k,'X-HTTP-Method-Override')===0&&preg_match('/^(GET|POST|PUT|PATCH|DELETE)$/i',$v))$method=strtoupper($v);
        $raw=(string)($b['body']??'');if(strlen($raw)>2097152)rrw_json(['status'=>'error','message'=>'Anfrage zu groß'],413);
        $lv=ob_get_level();ob_start();
        try{ $res=rrw_wp_rest_dispatch($method,$path,$q,$raw,$hdr); } finally { while(ob_get_level()>$lv)ob_end_clean(); }
        rrw_json(['status'=>'ok','result'=>['status'=>$res['status'],'type'=>$res['headers']['Content-Type']??'application/json','text'=>$res['body'],'headers'=>array_intersect_key($res['headers'],array_flip(['X-WP-Total','X-WP-TotalPages','Allow']))]]);
    }
    if(in_array($action,['wp_admin_menu','wp_admin_page','wp_admin_ajax'],true)){
        require_once __DIR__.'/wp/admin.php';
        $GLOBALS['rrw_wp_session_token']=hash('sha256',(string)($_SERVER['HTTP_X_ANMACHA_TOKEN']??''));
        if($action==='wp_admin_menu')rrw_json(['status'=>'ok','groups'=>rrw_wp_admin_menu_tree()]);
        $lv=ob_get_level();$sent=false;
        // Ein Plugin darf mit exit/die enden: das Ergebnis wird dann aus dem Puffer gebaut.
        register_shutdown_function(function() use($lv,&$sent,$action,$b){
            if($sent)return;$out='';while(ob_get_level()>$lv)$out=ob_get_clean().$out;
            if(!headers_sent())header('Content-Type: application/json; charset=utf-8');
            if($action==='wp_admin_ajax'){echo json_encode(['status'=>'ok','result'=>['status'=>200,'type'=>'text/html','text'=>$out===''?'0':$out]]);return;}
            $red=(string)($GLOBALS['rrw_wp_admin_redirect']??'');
            echo json_encode($red!==''?['status'=>'ok','redirect'=>$red]:['status'=>'ok','frame'=>rrw_wp_admin_frame_store(rrw_wp_admin_doc((string)($b['page']??''),$out,true)),'title'=>(string)($GLOBALS['title']??'')]);
        });
        ob_start();
        if($action==='wp_admin_ajax'){
            $u=(string)($b['url']??'');$q=(string)parse_url($u,PHP_URL_QUERY);
            $res=rrw_wp_ajax((string)($b['method']??'POST'),$q,(string)($b['body']??''),true);
            $sent=true;while(ob_get_level()>$lv)ob_end_clean();rrw_json(['status'=>'ok','result'=>$res]);
        }
        $res=rrw_wp_admin_page(['page'=>(string)($b['page']??''),'method'=>(string)($b['method']??'GET'),'body'=>(string)($b['body']??''),'query'=>(string)($b['query']??'')]);
        $sent=true;while(ob_get_level()>$lv)ob_end_clean();
        if(empty($res['ok']))rrw_json(['status'=>'error','message'=>(string)($res['message']??'Fehler')],404);
        if(!empty($res['notice']))rrw_log_activity($activityLogFile,$wpUser,'wp_admin_page','WordPress-Plugin-Seite „'.(string)$b['page'].'“: '.(string)$res['notice']);
        if(isset($res['html'])&&is_string($res['html'])){ $res['frame']=rrw_wp_admin_frame_store($res['html']).(!empty($res['frame_query'])?'&'.$res['frame_query']:'');unset($res['html'],$res['frame_query']); }
        rrw_json(['status'=>'ok']+array_diff_key($res,['ok'=>1]));
    }
    /* ── WordPress-Themes (PHP) ── */
    $wpThemes=function(){ $front=is_file(RRW_WP_DATA.'/front-on');$act=$front?(string)get_option('stylesheet',''):'';$l=rrw_wpi_list_themes();foreach($l as &$t)$t['active']=$t['slug']===$act;unset($t);return ['themes'=>$l,'front'=>$front,'active'=>$act]; };
    if($action==='wp_reading'||$action==='wp_reading_save'){
        require_once __DIR__.'/wp/links-api.php';
        if($action==='wp_reading')rrw_json(['status'=>'ok']+rrw_wpl_reading_get());
        $r=rrw_wpl_reading_save(rrw_body());if(!$r['ok'])rrw_json(['status'=>'error','message'=>$r['message']],422);
        rrw_log_activity($activityLogFile,$wpUser,'wp_reading','Startseite geändert ('.($r['show_on_front']==='page'?'statische Seite':'neueste Beiträge').')');
        unset($r['ok']);rrw_json(['status'=>'ok']+$r);
    }
    if($action==='wp_settings'||$action==='wp_settings_save'){
        require_once __DIR__.'/wp/settings-api.php';
        $grp=(string)($b['group']??$_GET['group']??'');
        if($action==='wp_settings'){ $r=rrw_wps_get($grp);if($r===null)rrw_json(['status'=>'error','message'=>'Unbekannter Einstellungsbereich'],404);rrw_json(['status'=>'ok']+$r); }
        $r=rrw_wps_save($grp,(array)($b['values']??[]));if(!$r['ok'])rrw_json(['status'=>'error','message'=>$r['message']],422);
        rrw_log_activity($activityLogFile,$wpUser,'wp_settings','Einstellungen „'.$r['title'].'“ gespeichert');
        unset($r['ok']);rrw_json(['status'=>'ok']+$r);
    }
    if($action==='wp_permalinks'||$action==='wp_permalinks_save'){
        require_once __DIR__.'/wp/links-api.php';
        if($action==='wp_permalinks')rrw_json(['status'=>'ok']+rrw_wpl_get());
        $r=rrw_wpl_save(rrw_body());if(!$r['ok'])rrw_json(['status'=>'error','message'=>$r['message']],422);
        rrw_log_activity($activityLogFile,$wpUser,'wp_permalinks','Link-Struktur geändert ('.($r['structure']===''?'einfach':$r['structure']).($r['hash']?', Hash-Form':'').')');
        unset($r['ok']);rrw_json(['status'=>'ok']+$r);
    }
    if($action==='wp_themes')rrw_json(['status'=>'ok']+$wpThemes()+['wp_version'=>RRW_WP_VERSION,'zip'=>class_exists('ZipArchive')]);
    if($action==='wp_theme_search'){
        $r=rrw_wpi_search_themes($dataDir,(string)($_GET['q']??''),(int)($_GET['page']??1));if(!$r['ok'])rrw_json(['status'=>'error','message'=>$r['message']],502);
        $have=array_column(rrw_wpi_list_themes(),'slug');foreach($r['items'] as &$it)$it['installed']=in_array($it['slug'],$have,true);unset($it);
        rrw_json(['status'=>'ok']+$r);
    }
    if($action==='wp_theme_install'||$action==='wp_theme_upload'){
        $zip='';
        try{
            if($action==='wp_theme_install'){ $slug=(string)($b['slug']??'');$zip=rrw_wpi_download_theme($slug);$res=rrw_wpi_install_theme_zip($zip,$slug); }
            else{ if(empty($_FILES['file'])||!is_uploaded_file($_FILES['file']['tmp_name']))rrw_json(['status'=>'error','message'=>'Keine ZIP-Datei'],400);
                if((int)$_FILES['file']['size']>41943040)rrw_json(['status'=>'error','message'=>'Das ZIP ist größer als 40 MB'],400);
                $res=rrw_wpi_install_theme_zip($_FILES['file']['tmp_name']); }
        }catch(Throwable $e){ if($zip!==''&&is_file($zip))@unlink($zip); rrw_json(['status'=>'error','message'=>$e->getMessage()],422); }
        if($zip!==''&&is_file($zip))@unlink($zip);
        try{ foreach(rrw_wpi_list_themes() as $tt)if($tt['slug']===$res['slug']){ rrw_wpi_fetch_translation('theme',$res['slug'],(string)$tt['version']);break; } }catch(Throwable $e){}
        rrw_log_activity($activityLogFile,$wpUser,'wp_theme_install','WordPress-Theme „'.$res['slug'].'“ installiert');
        rrw_json(['status'=>'ok','slug'=>$res['slug']]+$wpThemes());
    }
    if(in_array($action,['wp_theme_activate','wp_theme_delete','wp_theme_preview'],true)){
        $slug=(string)($b['slug']??'');if(!preg_match('/^[a-z0-9_-]{1,80}$/',$slug))rrw_json(['status'=>'error','message'=>'Ungültiges Theme'],400);
        $known=array_column(rrw_wpi_list_themes(),null,'slug');if(!isset($known[$slug]))rrw_json(['status'=>'error','message'=>'Theme nicht gefunden'],404);
        if($action==='wp_theme_activate'){
            $err=rrw_wpi_activate_theme($slug);if($err!==null)rrw_json(['status'=>'error','message'=>$err],422);
            rrw_log_activity($activityLogFile,$wpUser,'wp_theme_activate','WordPress-Theme „'.$slug.'“ aktiviert');rrw_json(['status'=>'ok']+$wpThemes());
        }
        if($action==='wp_theme_preview'){
            if(!empty($known[$slug]['error']))rrw_json(['status'=>'error','message'=>$known[$slug]['error']],422);
            rrw_json(['status'=>'ok','url'=>'/?'.(defined('RRW_WP_SANDBOX')?'rrw_sbx='.rawurlencode((string)rrw_sbx_meta()['token']).'&':'').'rrw_wp_preview='.rawurlencode(rrw_wpi_preview_token($slug)),'expires_in'=>900]);
        }
        if(!empty($known[$slug]['bundled']))rrw_json(['status'=>'error','message'=>'Das mitgelieferte Standard-Theme kann nicht gelöscht werden.'],422);
        if(defined('RRW_WP_SANDBOX')&&($known[$slug]['origin']??'')!=='sandbox')rrw_json(['status'=>'error','message'=>'Dieses Theme gehört zur Live-Seite und lässt sich in der Sandbox nicht löschen.'],422);
        $cur=$wpThemes();if($cur['active']===$slug||(string)get_option('template','')===$slug&&$cur['front'])rrw_json(['status'=>'error','message'=>'Das aktive Theme (oder dessen Eltern-Theme) kann nicht gelöscht werden. Erst ein anderes aktivieren.'],409);
        foreach($known as $o)if($o['parent']===$slug&&$o['slug']!==$slug)rrw_json(['status'=>'error','message'=>'Das Theme „'.$o['name'].'“ benötigt dieses Eltern-Theme.'],409);
        rrw_wp_rmdir(get_theme_root().'/'.$slug);
        rrw_log_activity($activityLogFile,$wpUser,'wp_theme_delete','WordPress-Theme „'.$slug.'“ gelöscht');rrw_json(['status'=>'ok']+$wpThemes());
    }
    if($wpCz){
        // Live-Customizer: Einstellungen lesen, Entwurf für die Vorschau ablegen, Werte speichern (nur Administratoren, wie die übrigen wp_theme_*-Aktionen)
        $vals=is_array($b['values']??null)?$b['values']:[];
        $finish=function(array $out,int $code=200){ while(ob_get_level()>0)ob_end_clean();rrw_json($out,$code); };
        $sbxQ=defined('RRW_WP_SANDBOX')?'rrw_sbx='.rawurlencode((string)rrw_sbx_meta()['token']).'&':'';
        $prevUrl=function(string $id) use($czSlug,$sbxQ){ return '/?'.$sbxQ.'rrw_wp_preview='.rawurlencode(rrw_wpi_preview_token($czSlug)).'&rrw_wp_draft='.$id; };
        $shareUrl=function(string $id) use($czSlug,$sbxQ){ return '/?'.$sbxQ.'rrw_wp_preview='.rawurlencode(rrw_wpi_preview_token($czSlug,null,7*86400)).'&rrw_wp_draft='.$id; };
        if($action==='wp_theme_customize'){
            $cs=rrw_wpc_cs_latest($czSlug);$id=$cs?(string)$cs['id']:rrw_wpc_new_id();
            $finish(['status'=>'ok']+rrw_wpc_describe($czSlug)+['draft'=>$id,'url'=>$prevUrl($id),'active'=>$wpThemes()['active']===$czSlug,'front'=>is_file(RRW_WP_DATA.'/front-on'),
                'changeset'=>$cs?['id'=>$id,'status'=>$cs['status'],'date'=>(int)$cs['date'],'values'=>$cs['values']??new stdClass,'share'=>$shareUrl($id)]:null]);
        }
        if($action==='wp_theme_customize_changeset'){
            // Entwurf speichern / Veröffentlichung planen / Entwurf verwerfen
            $id=(string)($b['draft']??'');if(!rrw_wpc_cs_ok($id))rrw_json(['status'=>'error','message'=>'Ungültiger Entwurf'],400);
            $mode=(string)($b['mode']??'');
            if($mode==='discard'){ rrw_wpc_cs_discard($id);$finish(['status'=>'ok']); }
            if(!in_array($mode,['draft','future'],true))rrw_json(['status'=>'error','message'=>'Ungültige Aktion'],400);
            $r=rrw_wpc_validate($vals);
            if($r['errors'])$finish(['status'=>'error','message'=>'Einige Werte sind nicht zulässig: '.implode(' · ',array_slice(array_values($r['errors']),0,3)),'errors'=>$r['errors']],422);
            $err=rrw_wpc_cs_save($id,$czSlug,$r['ok'],$mode,(int)($b['date']??0),!empty($b['activate']));
            if($err!==null)$finish(['status'=>'error','message'=>$err],422);
            rrw_wpc_draft_save($id,$czSlug,$r['ok']);
            rrw_log_activity($activityLogFile,$wpUser,'wp_theme_customize',$mode==='future'?'WordPress-Theme „'.$czSlug.'“: Änderungen geplant für '.date('d.m.Y H:i',(int)$b['date']):'WordPress-Theme „'.$czSlug.'“: Entwurf gespeichert');
            $finish(['status'=>'ok','changeset'=>['id'=>$id,'status'=>$mode,'date'=>$mode==='future'?(int)$b['date']:0,'share'=>$shareUrl($id)]]);
        }
        if($action==='wp_theme_customize_draft'){
            $id=(string)($b['draft']??'');if(!preg_match('/^[a-f0-9]{32}$/',$id))$id=rrw_wpc_new_id();
            $r=rrw_wpc_validate($vals);rrw_wpc_draft_save($id,$czSlug,$r['ok']);
            $finish(['status'=>'ok','draft'=>$id,'url'=>$prevUrl($id),'errors'=>$r['errors']]);
        }
        $r=rrw_wpc_save($vals);
        $csId=(string)($b['draft']??'');if($r['errors']===[]&&rrw_wpc_cs_ok($csId))rrw_wpc_cs_discard($csId);   // veröffentlicht → gespeicherter Entwurf entfällt
        if($r['errors'])$finish(['status'=>'error','message'=>'Einige Werte sind nicht zulässig: '.implode(' · ',array_slice(array_values($r['errors']),0,3)),'errors'=>$r['errors']],422);
        if($r['saved'])rrw_log_activity($activityLogFile,$wpUser,'wp_theme_customize','WordPress-Theme „'.$czSlug.'“ angepasst ('.count($r['saved']).' Einstellungen)');
        $finish(['status'=>'ok','saved'=>$r['saved'],'unchanged'=>$r['unchanged']]);
    }
    if($wpBk){
        $bkOut=function(array $layout) use($wpThemes){ $t=$wpThemes();return ['status'=>'ok','layout'=>$layout,'custom'=>elvado_bk_saved_layout()!==null,'schema'=>elvado_bk_schema(),'active'=>$t['front']&&(string)get_option('template','')==='elvado-baukasten'||$t['active']==='elvado-baukasten'];};
        if($action==='wp_bk_get')rrw_json($bkOut(elvado_bk_active_layout()));
        if(array_key_exists('reset',$b)&&$b['reset']){ delete_option('elvado_bk_layout');rrw_log_activity($activityLogFile,$wpUser,'wp_bk','Homepage-Baukasten auf die Customizer-Positionen zurückgesetzt');rrw_json($bkOut(elvado_bk_active_layout())); }
        if(!is_array($b['layout']??null))rrw_json(['status'=>'error','message'=>'Layout fehlt'],400);
        $l=elvado_bk_save_layout($b['layout']);rrw_log_activity($activityLogFile,$wpUser,'wp_bk','Homepage-Baukasten gespeichert ('.count($l).' Abschnitte)');
        rrw_json($bkOut($l));
    }
    if($action==='wp_theme_deactivate'){rrw_wpi_deactivate_theme();rrw_log_activity($activityLogFile,$wpUser,'wp_theme_deactivate','WordPress-Theme-Auslieferung beendet (CMS-Portal aktiv)');rrw_json(['status'=>'ok']+$wpThemes());}
    $file=(string)($b['file']??'');
    if($action==='wp_plugin_activate'){
        $r=activate_plugin($file);if(is_wp_error($r))rrw_json(['status'=>'error','message'=>$r->get_error_message()],422);
        rrw_log_activity($activityLogFile,$wpUser,'wp_plugin_activate','WordPress-Plugin „'.$file.'“ aktiviert');
        rrw_json(['status'=>'ok','plugins'=>$wpList()]);
    }
    if($action==='wp_plugin_deactivate'){deactivate_plugins($file);rrw_log_activity($activityLogFile,$wpUser,'wp_plugin_deactivate','WordPress-Plugin „'.$file.'“ deaktiviert');rrw_json(['status'=>'ok','plugins'=>$wpList()]);}
    if($action==='wp_plugin_delete'){
        $r=delete_plugins([$file]);if(is_wp_error($r))rrw_json(['status'=>'error','message'=>$r->get_error_message()],422);
        rrw_log_activity($activityLogFile,$wpUser,'wp_plugin_delete','WordPress-Plugin „'.$file.'“ gelöscht');rrw_json(['status'=>'ok','plugins'=>$wpList()]);
    }
    rrw_json(['status'=>'error','message'=>'Unbekannte Aktion'],404);
}
if($action==='plugins_list'){rrw_auth(false);rrw_json(['status'=>'ok','plugins'=>rrw_plugin_catalog($site),'active'=>$site['plugins']??[]]);}
if($action==='plugin_upload'){
    rrw_auth(true);if(empty($_FILES['file'])||!is_uploaded_file($_FILES['file']['tmp_name']))rrw_json(['status'=>'error','message'=>'Keine Plugin-ZIP'],400);
    if((int)($_FILES['file']['size']??0)>10485760)rrw_json(['status'=>'error','message'=>'Plugin-ZIP ist größer als 10 MB'],400);
    try{$x=rrw_import_plugin_zip($_FILES['file']['tmp_name']);}catch(Throwable $e){rrw_json(['status'=>'error','message'=>$e->getMessage()],422);}
    rrw_json(['status'=>'ok','id'=>$x['id'],'plugins'=>rrw_plugin_catalog($site)]);
}
if($action==='plugin_toggle'){
    rrw_auth(true);$b=rrw_body();$id=rrw_plugin_id((string)($b['id']??''));$enable=!empty($b['enabled']);
    $found=false;foreach(rrw_plugin_catalog($site) as $pl)if($pl['id']===$id){$found=true;break;}if(!$found)rrw_json(['status'=>'error','message'=>'Plugin nicht gefunden'],404);
    $active=array_values(array_unique(array_map('strval',(array)($site['plugins']??[]))));
    $active=array_values(array_filter($active,fn($x)=>$x!==$id));if($enable)$active[]=$id;$site['plugins']=$active;rrw_publish($site,$siteFile,$genDir,$root);
    rrw_json(['status'=>'ok','active'=>$active]);
}
if($action==='plugin_delete'){
    rrw_auth(true);$b=rrw_body();$id=rrw_plugin_id((string)($b['id']??''));$active=(array)($site['plugins']??[]);
    if(in_array($id,$active,true))rrw_json(['status'=>'error','message'=>'Aktives Plugin zuerst deaktivieren'],409);
    $dir=__DIR__.'/plugins/'.$id;if(!is_dir($dir))rrw_json(['status'=>'error','message'=>'Plugin nicht gefunden'],404);
    foreach(glob($dir.'/*')?:[] as $x)if(is_file($x))@unlink($x);if(!@rmdir($dir))rrw_json(['status'=>'error','message'=>'Plugin-Ordner konnte nicht gelöscht werden'],500);
    rrw_json(['status'=>'ok']);
}
if($action==='backup_list'){rrw_auth(false);rrw_json(['status'=>'ok','backups'=>rrw_backup_list()]);}
if($action==='backup_create'){
    rrw_auth(true);$b=rrw_body();$include=array_key_exists('include_media',$b)?!empty($b['include_media']):!empty($site['backup']['include_media']);
    try{$x=rrw_backup_create($root,$include);$keep=max(1,min(50,(int)($site['backup']['keep']??10)));$all=rrw_backup_list();foreach(array_slice($all,$keep) as $old)@unlink(rrw_backup_dir().'/'.basename($old['name']));rrw_json(['status'=>'ok','backup'=>$x,'backups'=>rrw_backup_list()]);}catch(Throwable $e){rrw_json(['status'=>'error','message'=>$e->getMessage()],500);}
}
if($action==='backup_restore'){
    rrw_auth(true);$b=rrw_body();try{rrw_backup_restore((string)($b['name']??''),$root);$site=rrw_ensure_site_defaults(rrw_read_json($siteFile,[]));rrw_publish($site,$siteFile,$genDir,$root);rrw_json(['status'=>'ok','message'=>'Backup wiederhergestellt','config'=>$site]);}catch(Throwable $e){rrw_json(['status'=>'error','message'=>$e->getMessage()],500);}
}
if($action==='backup_download'){
    rrw_auth(true);$name=basename((string)($_GET['name']??''));$file=rrw_backup_dir().'/'.$name;if(!is_file($file)){http_response_code(404);exit('Backup nicht gefunden');}
    header_remove('Content-Type');header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="'.str_replace('"','',$name).'"');header('Content-Length: '.filesize($file));readfile($file);exit;
}
if($action==='database_status'){rrw_auth(true);rrw_json(['status'=>'ok','database'=>rrw_db_status(),'storage'=>$site['storage']??['mode'=>'files','database_mirror'=>false]]);}
if($action==='database_test'){
    rrw_auth(true);$b=rrw_body();[$c,$err]=rrw_db_clean_input((array)($b['database']??[]));
    if($err!==null)rrw_json(['status'=>'error','message'=>$err],400);
    rrw_json(['status'=>'ok']+rrw_db_test($c,!empty($b['create'])));
}
if($action==='database_drivers'){rrw_auth(true);$o=[];foreach(rrw_db_drivers() as $k=>$d)$o[]=['id'=>$k,'label'=>$d['label'],'available'=>$d['ext']===''||extension_loaded($d['ext']),'port'=>$d['port']];rrw_json(['status'=>'ok','drivers'=>$o]);}
if($action==='database_config_save'){
    rrw_auth(true);$b=rrw_body();try{rrw_db_write_config((array)($b['database']??[]));$site['storage']=rrw_clean_section('storage',(array)($b['storage']??[]));rrw_publish($site,$siteFile,$genDir,$root);rrw_json(['status'=>'ok','database'=>rrw_db_status(),'storage'=>$site['storage']]);}catch(Throwable $e){rrw_json(['status'=>'error','message'=>$e->getMessage()],500);}
}
if($action==='database_push'){
    rrw_auth(true);try{$x=rrw_db_push($site,rrw_read_json($newsFile,[]));rrw_json(['status'=>'ok','synced'=>$x,'database'=>rrw_db_status()]);}catch(Throwable $e){rrw_json(['status'=>'error','message'=>$e->getMessage()],500);}
}
if($action==='database_pull'){
    rrw_auth(true);$b=rrw_body();if(($b['confirm']??'')!=='DATENBANK IMPORTIEREN')rrw_json(['status'=>'error','message'=>'Bestätigung fehlt'],400);
    try{$x=rrw_db_pull();if(!empty($x['site'])){$site=rrw_ensure_site_defaults($x['site']);rrw_publish($site,$siteFile,$genDir,$root);}if(isset($x['news']))rrw_write_atomic($newsFile,json_encode($x['news'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");rrw_json(['status'=>'ok','config'=>$site,'news_count'=>count($x['news']??[])]);}catch(Throwable $e){rrw_json(['status'=>'error','message'=>$e->getMessage()],500);}
}
if($action==='content_scan'){rrw_auth(false);rrw_json(['status'=>'ok','pages'=>rrw_content_scan()]);}
if($action==='content_sync'){rrw_auth(false);rrw_json(['status'=>'ok','files'=>rrw_content_sync_from_site($site)]);}
if($action==='content_import'){
    rrw_auth(false);$b=rrw_body();$slug=trim((string)($b['slug']??''));
    try{$x=rrw_content_import_to_site($site,$slug!==''?$slug:null);$site=rrw_ensure_site_defaults($x['site']);rrw_publish($site,$siteFile,$genDir,$root);rrw_json(['status'=>'ok','imported'=>$x['imported'],'config'=>$site]);}
    catch(Throwable $e){rrw_json(['status'=>'error','message'=>$e->getMessage()],500);}
}
if($action==='seo_rebuild'){rrw_auth(false);rrw_seo_generate($site,rrw_read_json($newsFile,[]),$root);rrw_json(['status'=>'ok','sitemap'=>'/sitemap.xml','robots'=>'/robots.txt']);}

if($action==='api_docs'){
    rrw_auth(false);rrw_json(['status'=>'ok','version'=>'1.0','docs'=>'/cms/docs/API.md','docs_html'=>'/cms/docs/','plugin_docs'=>'/cms/docs/PLUGINS.md','public_endpoint'=>'/cms/api.php?action=public','rss'=>'/cms/rss.php','rss_alias'=>'/feed/','rss_mirror'=>'/rss.xml',
      'write_sections'=>['portal','pages','menus','widgets','widget_areas','widget_inactive','branding','social','apps','legal','feed_sources','rss','theme','plugins','seo','storage','backup','brands','assistant','alexa'],
      'plugin_hooks'=>['portal:ready','page:rendered','cms:config-applied','plugin:loaded']]);
}
if($action==='themes_list'){rrw_auth(false);$state=$site['theme']??['active'=>'ricorewi-neon','variant'=>'default','settings'=>[]];$modsSaved=array_keys(is_array($state['mods']??null)?$state['mods']:[]);unset($state['mods']);rrw_json(['status'=>'ok','themes'=>rrw_theme_catalog($site),'active'=>$site['theme']['active']??'ricorewi-neon','theme_state'=>$state,'mods_saved'=>$modsSaved]);}
if($action==='theme_upload'){
    rrw_auth(false);if(empty($_FILES['file'])||!is_uploaded_file($_FILES['file']['tmp_name']))rrw_json(['status'=>'error','message'=>'Keine ZIP-Datei'],400);
    if((int)($_FILES['file']['size']??0)>20971520)rrw_json(['status'=>'error','message'=>'Theme-ZIP ist größer als 20 MB'],400);
    try{$x=rrw_import_theme_zip($_FILES['file']['tmp_name']);}catch(Throwable $e){rrw_json(['status'=>'error','message'=>$e->getMessage()],422);}
    $catalog=rrw_theme_catalog($site);$imported=null;foreach($catalog as $t)if(($t['id']??'')===$x['id']){$imported=$t;break;}
    rrw_json(['status'=>'ok','id'=>$x['id'],'wordpress'=>$x['wordpress'],'bootstrap'=>!empty($imported['bootstrap']),'compatibility'=>$imported['compatibility']??'native','themes'=>$catalog]);
}
if($action==='theme_directory'||$action==='theme_install'){
    require_once __DIR__.'/lib/theme-directory.php';
    if($action==='theme_directory'){
        rrw_auth(false);
        $r=rrw_td_search($dataDir,(string)($_GET['source']??'bootswatch'),(string)($_GET['q']??''),(int)($_GET['page']??1));
        if(!$r['ok'])rrw_json(['status'=>'error','message'=>$r['message']],502);
        $have=array_column(rrw_theme_catalog($site),'id');
        foreach($r['items'] as &$it)$it['installed']=in_array($it['source']==='bootswatch'?'bootswatch-'.$it['slug']:rrw_theme_id($it['slug']),$have,true);unset($it);
        rrw_json(['status'=>'ok']+$r);
    }
    $u=rrw_auth(true);$b=rrw_body();$src=(string)($b['source']??'');$slug=(string)($b['slug']??'');$zipTmp='';
    try{$zipTmp=rrw_td_build_zip($dataDir,$src,$slug);$x=rrw_import_theme_zip($zipTmp);}
    catch(Throwable $e){if($zipTmp!==''&&is_file($zipTmp))@unlink($zipTmp);rrw_json(['status'=>'error','message'=>$e->getMessage()],422);}
    @unlink($zipTmp);
    rrw_log_activity($activityLogFile,$u,'theme_install','Theme „'.$x['id'].'“ aus dem Verzeichnis installiert');
    rrw_json(['status'=>'ok','id'=>$x['id'],'wordpress'=>$x['wordpress']]);
}
if($action==='theme_activate'){
    rrw_auth(false);$b=rrw_body();$id=rrw_theme_id((string)($b['id']??''));$found=null;
    foreach(rrw_theme_catalog($site) as $t)if($t['id']===$id){$found=$t;break;}if(!$found)rrw_json(['status'=>'error','message'=>'Theme nicht gefunden'],404);
    $defaults=is_array($found['defaults']??null)?$found['defaults']:[];
    $variant=(string)($found['variants'][0]['id']??'default');
    // Wie bei WordPress: Anpassungen und Widget-Anordnung des bisherigen Themes sichern, dann
    // die gespeicherten des neuen Themes wiederherstellen - oder, beim ersten Aktivieren, seine
    // mitgelieferte Anordnung laden. Bringt ein Theme keine eigenen Bereiche mit (z.B. ein
    // importiertes reines CSS-Theme), bleibt die aktuelle Anordnung erhalten.
    $site=rrw_theme_remember_mods($site);
    $mods=is_array($site['theme']['mods']??null)?$site['theme']['mods']:[];
    $prev=is_array($mods[$id]??null)?$mods[$id]:null;
    if($prev!==null){$variant=(string)($prev['variant']??$variant);$settings=is_array($prev['settings']??null)?$prev['settings']:$defaults;$areas=is_array($prev['widget_areas']??null)?$prev['widget_areas']:null;}
    else{$settings=$defaults;$settings['sidebar_width']=(string)$found['layout']['sidebar_width'];$areas=$found['widget_areas']?:null;}
    $site['theme']=['active'=>$id,'variant'=>$variant,'settings'=>$settings,'layout'=>$found['layout'],'mods'=>$mods];
    if($areas!==null)$site['widget_areas']=rrw_clean_section('widget_areas',$areas);
    $site=rrw_theme_remember_mods($site);
    rrw_publish($site,$siteFile,$genDir,$root);
    rrw_log_activity($activityLogFile,null,'theme_activate','Theme „'.$found['name'].'“ aktiviert'.($prev!==null?' (gespeicherte Anpassungen wiederhergestellt)':' (Standard-Anordnung des Themes geladen)'));
    rrw_json(['status'=>'ok','theme'=>$site['theme'],'widget_areas'=>$site['widget_areas']??[]]);
}
if($action==='theme_customize_save'){
    rrw_auth(false);$b=rrw_body();$id=rrw_theme_id((string)($b['id']??$site['theme']['active']??'ricorewi-neon'));
    $found=null;foreach(rrw_theme_catalog($site) as $t)if($t['id']===$id){$found=$t;break;}if(!$found)rrw_json(['status'=>'error','message'=>'Theme nicht gefunden'],404);
    $switching=($site['theme']['active']??'ricorewi-neon')!==$id;
    if($switching){$site=rrw_theme_remember_mods($site);$prev=$site['theme']['mods'][$id]??null;if(is_array($prev['widget_areas']??null))$site['widget_areas']=rrw_clean_section('widget_areas',$prev['widget_areas']);elseif($found['widget_areas'])$site['widget_areas']=$found['widget_areas'];}
    $clean=rrw_clean_section('theme',['active'=>$id,'variant'=>$b['variant']??'default','settings'=>$b['settings']??[],'layout'=>$found['layout'],'mods'=>$site['theme']['mods']??[]]);
    $site['theme']=$clean;$site=rrw_theme_remember_mods($site);
    rrw_publish($site,$siteFile,$genDir,$root);rrw_json(['status'=>'ok','theme'=>$site['theme'],'widget_areas'=>$site['widget_areas']??[]]);
}
if($action==='theme_reset_areas'){
    rrw_auth(false);$id=rrw_theme_id((string)($site['theme']['active']??'ricorewi-neon'));
    $found=null;foreach(rrw_theme_catalog($site) as $t)if($t['id']===$id){$found=$t;break;}if(!$found)rrw_json(['status'=>'error','message'=>'Aktives Theme nicht gefunden'],404);
    if(!$found['widget_areas'])rrw_json(['status'=>'error','message'=>'Dieses Theme bringt keine eigenen Widget-Bereiche mit'],400);
    $site['widget_areas']=$found['widget_areas'];$site=rrw_theme_remember_mods($site);
    rrw_publish($site,$siteFile,$genDir,$root);
    rrw_log_activity($activityLogFile,null,'theme_reset_areas','Widget-Anordnung auf den Standard des Themes „'.$found['name'].'“ zurückgesetzt');
    rrw_json(['status'=>'ok','widget_areas'=>$site['widget_areas']]);
}
if($action==='theme_delete'){
    rrw_auth(false);$b=rrw_body();$id=rrw_theme_id((string)($b['id']??''));if($id==='ricorewi-neon')rrw_json(['status'=>'error','message'=>'Standardtheme ist geschützt'],400);
    if(($site['theme']['active']??'ricorewi-neon')===$id)rrw_json(['status'=>'error','message'=>'Aktives Theme kann nicht gelöscht werden'],400);
    $dir=__DIR__.'/themes/'.$id;if(!is_dir($dir))rrw_json(['status'=>'error','message'=>'Theme nicht gefunden'],404);
    foreach(glob($dir.'/*')?:[] as $x)if(is_file($x))@unlink($x);@rmdir($dir);rrw_json(['status'=>'ok']);
}
if($action==='architecture'){rrw_auth(false);rrw_json(['status'=>'ok','components'=>[['id'=>'portal','name'=>rrw_pack_available()?'RicoReWi Radioportal':'Website','type'=>'Frontend','path'=>'/'],['id'=>'cms','name'=>rrw_product_title(),'type'=>'Datei-CMS','path'=>'/cms/'],['id'=>'storage','name'=>'CMS-Dateispeicher','type'=>'JSON','path'=>'/cms/data/site.json'],['id'=>'generated','name'=>'Generierte Seiten & SEO','type'=>'HTML/CSS','path'=>'/cms/generated/'],['id'=>'control-center','name'=>rrw_product_control_center().(rrw_standalone()?' (ausgeschaltet)':' (optional)'),'type'=>'Zugriff & Rechte','path'=>'/control/'],['id'=>'local-auth','name'=>'Lokaler CMS-Zugang','type'=>'Zugriff & Rechte','path'=>'/cms/data/local-auth.local.php']],'core_stations'=>rrw_pack_available()?($site['core_network']['stations']??[]):[],'updated_at'=>date(DATE_ATOM)]);}
// Community (Mitglieder; optional, standardmäßig aus): öffentliche Konto-Funktionen und Verwaltung im CMS
if(str_starts_with($action,'member_')||str_starts_with($action,'community_')||str_starts_with($action,'forum_')||str_starts_with($action,'social_')){
    $cmCfg=rrw_cm_config($dataDir);$cmIp=trim(explode(',',(string)($_SERVER['HTTP_X_FORWARDED_FOR']??$_SERVER['REMOTE_ADDR']??''))[0]);
    $cmTok=trim((string)($_SERVER['HTTP_X_MEMBER_TOKEN']??''));
    $cmOut=function(array $r,array $extra=[]){ if(!$r['ok'])rrw_json(['status'=>'error','message'=>$r['message']],$r['code']); unset($r['ok'],$r['code']); rrw_json(['status'=>'ok']+$r+$extra); };
    $cmNeedOn=function() use($cmCfg){ if(!$cmCfg['enabled'])rrw_json(['status'=>'error','message'=>'Die Community ist nicht aktiv.'],404); };
    $cmMe=function() use($dataDir,$cmTok,$cmNeedOn){ $cmNeedOn(); $m=rrw_cm_auth($dataDir,$cmTok); if(!$m)rrw_json(['status'=>'error','message'=>'Bitte melde dich an.'],401); return $m; };
    if($action==='community_config')rrw_json(['status'=>'ok','config'=>rrw_cm_public_config($cmCfg)]);
    if($action==='member_register'){$cmOut(rrw_cm_register($dataDir,$cmCfg,rrw_body(),$cmIp));}
    if($action==='member_login'){$cmOut(rrw_cm_login($dataDir,$cmCfg,rrw_body(),$cmIp));}
    if($action==='member_logout'){rrw_cm_logout($dataDir,$cmTok);rrw_json(['status'=>'ok']);}
    if($action==='member_me'){$m=$cmMe();rrw_json(['status'=>'ok','member'=>rrw_cm_public($m)+['email'=>$m['email']]]);}
    if($action==='member_update'){$m=$cmMe();$cmOut(rrw_cm_update_profile($dataDir,$cmCfg,(string)$m['id'],rrw_body()));}
    if($action==='member_delete'){$m=$cmMe();$b=rrw_body();$cmOut(rrw_cm_delete_account($dataDir,(string)$m['id'],(string)($b['password']??''),function($id) use($dataDir){ if(function_exists('rrw_cm_anonymize_member'))rrw_cm_anonymize_member($dataDir,$id); }));}
    if($action==='member_reset_request'){
        $cmNeedOn();$b=rrw_body();$origin=rtrim((string)(rrw_seo_defaults($site)['canonical_base']??''),'/');
        $cmOut(rrw_cm_reset_request($dataDir,$cmCfg,(string)($b['email']??''),$cmIp,$origin,function($to,$link) use($site){
            rrw_send_mail($to,'Passwort zurücksetzen',"Hallo,\n\nüber diesen Link kannst du ein neues Passwort festlegen (eine Stunde gültig):\n\n$link\n\nWenn du das nicht angefordert hast, ignoriere diese Nachricht einfach.\n",rrw_mail_from($site));
        }));
    }
    if($action==='member_reset'){$cmNeedOn();$b=rrw_body();$cmOut(rrw_cm_reset_apply($dataDir,$cmCfg,(string)($b['token']??''),(string)($b['password']??'')));}
    if($action==='member_profile'){
        $cmNeedOn();$ms=rrw_cm_members($dataDir);$i=rrw_cm_find($ms,'id',(string)($_GET['id']??''));if($i===null)$i=rrw_cm_find($ms,'username',(string)($_GET['username']??''));
        if($i===null||($ms[$i]['status']??'')!=='active')rrw_json(['status'=>'error','message'=>'Mitglied nicht gefunden'],404);
        rrw_json(['status'=>'ok','member'=>rrw_cm_public($ms[$i])]);
    }
    // Forum (Lesen öffentlich, Schreiben nur Mitglieder; Moderation: Moderatoren-Mitglieder oder CMS-Administratoren)
    if(str_starts_with($action,'forum_')){
        $cmNeedOn();if(!$cmCfg['forum'])rrw_json(['status'=>'error','message'=>'Das Forum ist nicht aktiv.'],404);
        $b=rrw_body();$q=fn(string $k)=>(string)($_GET[$k]??'');
        // Moderation: CMS-Token (Administrator) oder Mitglied mit Rolle moderator
        $foMod=function() use($cmMe,$dataDir,&$b){ if(trim((string)($_SERVER['HTTP_X_ANMACHA_TOKEN']??''))!==''){rrw_auth(true);return null;} $m=$cmMe(); if(($m['role']??'')!=='moderator')rrw_json(['status'=>'error','message'=>'Dazu fehlt dir die Berechtigung.'],403); return $m; };
        if($action==='forum_overview')rrw_json(['status'=>'ok','categories'=>rrw_fo_overview($dataDir)]);
        if($action==='forum_topics'){$r=rrw_fo_topics($dataDir,$q('cat'),(int)$q('page'));rrw_json(['status'=>'ok']+$r);}
        if($action==='forum_topic'){$r=rrw_fo_topic($dataDir,$q('id'),(int)$q('page'));if(!$r)rrw_json(['status'=>'error','message'=>'Thema nicht gefunden'],404);rrw_json(['status'=>'ok']+$r);}
        if($action==='forum_topic_create'){$m=$cmMe();$cmOut(rrw_fo_topic_create($dataDir,$m,$b));}
        if($action==='forum_reply'){$m=$cmMe();$cmOut(rrw_fo_reply($dataDir,$m,(string)($b['topic']??''),(string)($b['body']??''),($m['role']??'')==='moderator'));}
        if($action==='forum_edit'){$m=$cmMe();$cmOut(rrw_fo_edit($dataDir,$m,(string)($b['topic']??''),(string)($b['post']??''),(string)($b['body']??'')));}
        if($action==='forum_delete'){$m=$cmMe();$cmOut(rrw_fo_delete_post($dataDir,$m,($m['role']??'')==='moderator',(string)($b['topic']??''),(string)($b['post']??'')));}
        if($action==='forum_report'){$m=$cmMe();$cmOut(rrw_fo_report($dataDir,$m,(string)($b['topic']??''),(string)($b['post']??''),(string)($b['reason']??'')));}
        if($action==='forum_mod'){$foMod();$cmOut(rrw_fo_mod($dataDir,(string)($b['op']??''),(string)($b['topic']??''),(string)($b['arg']??'')));}
        if($action==='forum_mod_delete_post'){$foMod();$cmOut(rrw_fo_delete_post($dataDir,null,true,(string)($b['topic']??''),(string)($b['post']??'')));}
        if($action==='forum_reports'){$foMod();rrw_json(['status'=>'ok','reports'=>rrw_fo_reports($dataDir)]);}
        if($action==='forum_report_dismiss'){$foMod();$cmOut(rrw_fo_report_dismiss($dataDir,(string)($b['id']??'')));}
        if($action==='forum_categories_save'){rrw_auth(true);rrw_json(['status'=>'ok','categories'=>rrw_fo_categories_save($dataDir,$b['categories']??[])]);}
        rrw_json(['status'=>'error','message'=>'Unbekannte Aktion'],404);
    }
    // Soziales Netzwerk (Lesen öffentlich, Posten/Folgen/Liken nur Mitglieder)
    if(str_starts_with($action,'social_')){
        $cmNeedOn();if(!$cmCfg['social'])rrw_json(['status'=>'error','message'=>'Das soziale Netzwerk ist nicht aktiv.'],404);
        $b=rrw_body();$viewer=$cmTok!==''?rrw_cm_auth($dataDir,$cmTok):null;
        if($action==='social_feed'){
            $mode=in_array((string)($_GET['mode']??''),['following','user'],true)?(string)$_GET['mode']:'all';
            if($mode==='following'&&!$viewer)rrw_json(['status'=>'error','message'=>'Bitte melde dich an.'],401);
            $uid='';if($mode==='user'){$ms=rrw_cm_members($dataDir);$i=rrw_cm_find($ms,'username',(string)($_GET['username']??''));if($i===null||($ms[$i]['status']??'')!=='active')rrw_json(['status'=>'error','message'=>'Mitglied nicht gefunden'],404);$uid=(string)$ms[$i]['id'];}
            rrw_json(['status'=>'ok']+rrw_so_feed($dataDir,$mode,$viewer,$uid,(string)($_GET['before']??'')));
        }
        if($action==='social_profile'){$r=rrw_so_profile($dataDir,(string)($_GET['username']??''),$viewer);if(!$r)rrw_json(['status'=>'error','message'=>'Mitglied nicht gefunden'],404);rrw_json(['status'=>'ok']+$r);}
        $m=$cmMe();
        if($action==='social_post')$cmOut(rrw_so_post($dataDir,$m,(string)($b['body']??'')));
        if($action==='social_delete')$cmOut(rrw_so_delete($dataDir,$m,($m['role']??'')==='moderator',(string)($b['id']??'')));
        if($action==='social_like')$cmOut(rrw_so_like($dataDir,$m,(string)($b['id']??'')));
        if($action==='social_follow')$cmOut(rrw_so_follow($dataDir,$m,(string)($b['id']??'')));
        rrw_json(['status'=>'error','message'=>'Unbekannte Aktion'],404);
    }
    // Verwaltung (nur Administratoren)
    if($action==='community_admin_get'){rrw_auth(true);rrw_json(['status'=>'ok','config'=>$cmCfg,'members'=>rrw_cm_admin_list($dataDir)]);}
    if($action==='community_config_save'){
        $u=rrw_auth(true);$b=rrw_body();$new=rrw_cm_config_clean($b['config']??[]);
        try{rrw_cm_write($dataDir,'config.json',$new);rrw_protect_dir(rrw_cm_dir($dataDir));}catch(Throwable $e){rrw_json(['status'=>'error','message'=>'Speichern fehlgeschlagen'],500);}
        if($new['enabled']!==$cmCfg['enabled'])rrw_log_activity($activityLogFile,$u,'community_'.($new['enabled']?'on':'off'),'Community '.($new['enabled']?'eingeschaltet':'ausgeschaltet'));
        rrw_json(['status'=>'ok','config'=>$new]);
    }
    if($action==='community_member_op'){
        $u=rrw_auth(true);$b=rrw_body();$op=(string)($b['op']??'');$id=(string)($b['id']??'');
        $r=rrw_cm_admin_set($dataDir,$id,$op,function($mid) use($dataDir){ if(function_exists('rrw_cm_anonymize_member'))rrw_cm_anonymize_member($dataDir,$mid); });
        if($r['ok'])rrw_log_activity($activityLogFile,$u,'community_member_'.$op,'Mitglied '.$id.': '.$op);
        $cmOut($r);
    }
    rrw_json(['status'=>'error','message'=>'Unbekannte Aktion'],404);
}
// Umfragen des CMS: öffentlich anzeigen/abstimmen, im CMS verwalten
if($action==='poll_get'){
    $id=(string)($_GET['id']??'');$client=(string)($_GET['client']??'');$polls=rrw_polls_load($dataDir);$p=null;
    if($id!==''){foreach($polls as $x)if(($x['id']??'')===$id){$p=$x;break;}}
    else{foreach(array_reverse($polls) as $x)if(rrw_poll_state($x)==='open'){$p=$x;break;}}      // ohne ID: die neueste offene Umfrage
    if(!$p)rrw_json(['status'=>'ok','poll'=>null]);
    rrw_json(['status'=>'ok','poll'=>rrw_poll_public($p,rrw_poll_has_voted($dataDir,(string)$p['id'],$client))]);
}
if($action==='poll_vote'){
    $b=rrw_body();$ip=trim(explode(',',(string)($_SERVER['HTTP_X_FORWARDED_FOR']??$_SERVER['REMOTE_ADDR']??''))[0]);
    $r=rrw_poll_vote($dataDir,(string)($b['poll_id']??''),array_map('strval',(array)($b['options']??[])),(string)($b['client']??''),$ip);
    if(!$r['ok'])rrw_json(['status'=>'error','message'=>$r['message']],$r['code']);
    rrw_json(['status'=>'ok','message'=>$r['message'],'poll'=>rrw_poll_public($r['poll'],true)]);
}
if($action==='polls_list'){
    rrw_auth(false);
    rrw_json(['status'=>'ok','polls'=>array_values(array_map(fn($p)=>$p+['state'=>rrw_poll_state($p),'total'=>rrw_poll_total($p)],rrw_polls_load($dataDir)))]);
}
if($action==='poll_save'){
    $u=rrw_auth(false);$b=rrw_body();$polls=rrw_polls_load($dataDir);$idx=null;
    foreach($polls as $i=>$p)if(($p['id']??'')===(string)($b['id']??'')){$idx=$i;break;}
    $clean=rrw_poll_clean($b,$idx!==null?$polls[$idx]:null);
    if($clean===null)rrw_json(['status'=>'error','message'=>'Bitte eine Frage und mindestens zwei verschiedene Antworten angeben.'],400);
    if($idx===null)$polls[]=$clean;else $polls[$idx]=$clean;
    try{rrw_polls_save($dataDir,$polls);}catch(Throwable $e){rrw_json(['status'=>'error','message'=>'Speichern fehlgeschlagen'],500);}
    rrw_log_activity($activityLogFile,$u,$idx===null?'poll_create':'poll_update','Umfrage „'.$clean['question'].'“ '.($idx===null?'angelegt':'gespeichert'));
    rrw_json(['status'=>'ok','poll'=>$clean+['state'=>rrw_poll_state($clean),'total'=>rrw_poll_total($clean)]]);
}
if($action==='poll_delete'||$action==='poll_reset'){
    $u=rrw_auth(false);$b=rrw_body();$id=(string)($b['id']??'');$polls=rrw_polls_load($dataDir);$found=false;
    foreach($polls as $i=>&$p)if(($p['id']??'')===$id){
        $found=true;
        if($action==='poll_delete'){rrw_log_activity($activityLogFile,$u,'poll_delete','Umfrage „'.$p['question'].'“ gelöscht');unset($polls[$i]);}
        else{foreach($p['options'] as &$o)$o['votes']=0;unset($o);rrw_log_activity($activityLogFile,$u,'poll_reset','Stimmen der Umfrage „'.$p['question'].'“ zurückgesetzt');}
        break;
    }unset($p);
    if(!$found)rrw_json(['status'=>'error','message'=>'Umfrage nicht gefunden'],404);
    try{rrw_polls_save($dataDir,$polls);rrw_poll_voters_drop($dataDir,$id);}catch(Throwable $e){rrw_json(['status'=>'error','message'=>'Speichern fehlgeschlagen'],500);}
    rrw_json(['status'=>'ok']);
}
if($action==='polls_export'){
    rrw_auth(false);$id=(string)($_GET['id']??'');
    foreach(rrw_polls_load($dataDir) as $p)if(($p['id']??'')===$id){
        header_remove('Content-Type');header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="umfrage-'.$id.'.csv"');echo rrw_polls_csv($p);exit;
    }
    rrw_json(['status'=>'error','message'=>'Umfrage nicht gefunden'],404);
}
// Formulare (Kontaktformular-/Newsletter-Widget): öffentliche Einsendung und Verwaltung der Einsendungen
if($action==='form_submit'){
    $b=rrw_body();$ip=trim(explode(',',(string)($_SERVER['HTTP_X_FORWARDED_FOR']??$_SERVER['REMOTE_ADDR']??''))[0]);
    $r=rrw_forms_submit($dataDir,$site,$b,$ip);
    rrw_json(['status'=>$r['ok']?'ok':'error','message'=>$r['message']],$r['code']);
}
if($action==='forms_list'){
    rrw_auth(false);$items=array_reverse(rrw_forms_load($dataDir));
    $unread=0;foreach($items as $i)if(empty($i['read']))$unread++;
    rrw_json(['status'=>'ok','items'=>$items,'unread'=>$unread]);
}
if($action==='forms_update'){
    $u=rrw_auth(false);$b=rrw_body();$ids=array_flip(array_map('strval',(array)($b['ids']??[])));$op=(string)($b['op']??'');
    if(!$ids||!in_array($op,['read','unread','delete'],true))rrw_json(['status'=>'error','message'=>'Ungültige Anfrage'],400);
    $items=rrw_forms_load($dataDir);$n=0;$keep=[];
    foreach($items as $i){
        if(!isset($ids[(string)($i['id']??'')])){$keep[]=$i;continue;}
        $n++;if($op==='delete')continue;$i['read']=$op==='read';$keep[]=$i;
    }
    try{rrw_forms_save($dataDir,$keep);}catch(Throwable $e){rrw_json(['status'=>'error','message'=>'Speichern fehlgeschlagen'],500);}
    if($op==='delete')rrw_log_activity($activityLogFile,$u,'forms_delete',$n.' Einsendung(en) gelöscht');
    rrw_json(['status'=>'ok','changed'=>$n]);
}
if($action==='forms_export'){
    rrw_auth(true);$kind=($_GET['kind']??'')==='newsletter'?'newsletter':'contact';
    header_remove('Content-Type');header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="'.($kind==='newsletter'?'newsletter-anmeldungen':'kontaktanfragen').'-'.date('Y-m-d').'.csv"');
    echo rrw_forms_csv(rrw_forms_load($dataDir),$kind);exit;
}
// Werkzeuge: Wartungsmodus und Weiterleitungen/404-Protokoll (nur Administratoren, siehe lib/tools.php)
if($action==='maintenance_get'){
    rrw_auth(true);$cfg=rrw_maint_load($dataDir);
    if($cfg['key']===''){$cfg=rrw_maint_clean($cfg);try{rrw_tools_write(rrw_tools_dir($dataDir),'maintenance.json',$cfg);}catch(Throwable $e){}} // Vorschau-Schlüssel gleich beim ersten Öffnen festlegen
    rrw_json(['status'=>'ok','config'=>$cfg]);
}
if($action==='maintenance_save'){
    $u=rrw_auth(true);$b=rrw_body();$old=rrw_maint_load($dataDir);
    $new=rrw_maint_clean($b['config']??[],(string)$old['key']);
    if(!empty($b['new_key']))$new['key']=bin2hex(random_bytes(8));
    try{rrw_tools_write(rrw_tools_dir($dataDir),'maintenance.json',$new);}catch(Throwable $e){rrw_json(['status'=>'error','message'=>'Speichern fehlgeschlagen: '.$e->getMessage()],500);}
    if($new['enabled']!==$old['enabled'])rrw_log_activity($activityLogFile,$u,'maintenance_'.($new['enabled']?'on':'off'),'Wartungsmodus '.($new['enabled']?'eingeschaltet':'ausgeschaltet'));
    rrw_json(['status'=>'ok','config'=>$new]);
}
if($action==='redirects_get'){
    rrw_auth(true);$d=rrw_tools_dir($dataDir);
    rrw_json(['status'=>'ok','rules'=>rrw_redirects_clean(rrw_tools_read($d.'/redirects.json',['rules'=>[]])['rules']??[]),'log'=>array_values((array)(rrw_tools_read($d.'/404.json',['items'=>[]])['items']??[]))]);
}
if($action==='redirects_save'){
    $u=rrw_auth(true);$b=rrw_body();$rules=rrw_redirects_clean($b['rules']??[]);
    try{rrw_tools_write(rrw_tools_dir($dataDir),'redirects.json',['rules'=>$rules]);}catch(Throwable $e){rrw_json(['status'=>'error','message'=>'Speichern fehlgeschlagen: '.$e->getMessage()],500);}
    rrw_log_activity($activityLogFile,$u,'redirects_save',count($rules).' Weiterleitung(en) gespeichert');
    rrw_json(['status'=>'ok','rules'=>$rules]);
}
if($action==='redirects_log_clear'){
    $u=rrw_auth(true);try{rrw_tools_write(rrw_tools_dir($dataDir),'404.json',['items'=>[]]);}catch(Throwable $e){rrw_json(['status'=>'error','message'=>'Speichern fehlgeschlagen'],500);}
    rrw_log_activity($activityLogFile,$u,'redirects_log_clear','404-Protokoll geleert');rrw_json(['status'=>'ok']);
}
// Datenschutz-Werkzeuge (nur Administratoren): Daten zu einer Person finden, exportieren und Kommentare löschen
if($action==='privacy_overview'){
    rrw_auth(true);$cm=rrw_read_json($commentsFile,[]);$pend=0;foreach($cm as $c)if(($c['status']??'')==='pending')$pend++;
    rrw_json(['status'=>'ok','overview'=>[
        'comments'=>count($cm),'comments_pending'=>$pend,
        'users'=>count(rrw_local_users()),
        'activity'=>count(rrw_read_json($activityLogFile,[])),'notifications'=>count(rrw_read_json($notificationsFile,[])),
        'not_found_log'=>count((array)(rrw_tools_read(rrw_tools_dir($dataDir).'/404.json',['items'=>[]])['items']??[])),
    ]]);
}
if($action==='privacy_search'){
    rrw_auth(true);$b=rrw_body();$q=mb_strtolower(trim((string)($b['q']??'')));
    if(mb_strlen($q)<2)rrw_json(['status'=>'error','message'=>'Bitte mindestens 2 Zeichen eingeben'],400);
    $has=fn($v)=>str_contains(mb_strtolower((string)$v),$q);
    $newsList=rrw_read_json($newsFile,[]);
    $titles=[];foreach($newsList as $a)$titles[(int)($a['id']??0)]=(string)($a['title']??'');
    $comments=[];foreach(rrw_read_json($commentsFile,[]) as $c)if($has($c['name']??'')||$has($c['text']??''))$comments[]=['id'=>(int)($c['id']??0),'article_id'=>(int)($c['article_id']??0),'article_title'=>$titles[(int)($c['article_id']??0)]??'','name'=>(string)($c['name']??''),'text'=>(string)($c['text']??''),'status'=>(string)($c['status']??''),'created_at'=>(string)($c['created_at']??''),'is_staff'=>!empty($c['is_staff'])];
    $users=[];foreach(rrw_local_users() as $u)if($has($u['username']??'')||$has($u['display_name']??'')||$has($u['email']??''))$users[]=['username'=>(string)($u['username']??''),'display_name'=>(string)($u['display_name']??''),'email'=>(string)($u['email']??''),'role'=>(string)($u['role']??''),'created_at'=>(string)($u['created_at']??'')];
    $activity=0;foreach(rrw_read_json($activityLogFile,[]) as $e)if($has($e['user']??''))$activity++;
    $articles=[];foreach($newsList as $a)if($has($a['author']??''))$articles[]=['id'=>(int)($a['id']??0),'title'=>(string)($a['title']??''),'author'=>(string)($a['author']??'')];
    $mem=[];foreach(rrw_cm_members($dataDir) as $m)if($has($m['username']??'')||$has($m['display_name']??'')||$has($m['email']??''))$mem[]=['id'=>(string)$m['id'],'username'=>(string)$m['username'],'name'=>(string)($m['display_name']??''),'email'=>(string)$m['email'],'status'=>(string)($m['status']??''),'created_at'=>(string)($m['created_at']??'')];
    $forms=[];foreach(rrw_forms_load($dataDir) as $f){$d=(array)($f['data']??[]);if($has($d['name']??'')||$has($d['email']??'')||$has($d['message']??''))$forms[]=['id'=>(string)($f['id']??''),'kind'=>(string)($f['kind']??''),'name'=>(string)($d['name']??''),'email'=>(string)($d['email']??''),'text'=>mb_substr((string)($d['message']??''),0,200),'created_at'=>(string)($f['created_at']??'')];}
    rrw_json(['status'=>'ok','q'=>$q,'comments'=>$comments,'forms'=>$forms,'members'=>$mem,'users'=>$users,'activity_entries'=>$activity,'articles'=>array_slice($articles,0,100)]);
}
if($action==='privacy_comments_delete'){
    $u=rrw_auth(true);$b=rrw_body();$ids=array_values(array_unique(array_filter(array_map('intval',(array)($b['ids']??[])),fn($i)=>$i>0)));
    if(!$ids)rrw_json(['status'=>'error','message'=>'Keine Kommentare ausgewählt'],400);
    $all=rrw_read_json($commentsFile,[]);$set=array_flip($ids);
    // Antworten auf gelöschte Kommentare gehen mit, damit keine verwaisten Unterkommentare bleiben
    $del=$set;do{$grew=false;foreach($all as $c){if(isset($del[(int)($c['parent_id']??0)])&&!isset($del[(int)($c['id']??0)])){$del[(int)$c['id']]=1;$grew=true;}}}while($grew);
    $keep=array_values(array_filter($all,fn($c)=>!isset($del[(int)($c['id']??0)])));
    $removed=count($all)-count($keep);
    rrw_write_atomic($commentsFile,json_encode($keep,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    rrw_log_activity($activityLogFile,$u,'privacy_comments_delete',$removed.' Kommentar(e) aus Datenschutzgründen gelöscht');
    rrw_json(['status'=>'ok','removed'=>$removed]);
}
// Seiten: Versionen (die letzten 10 Fassungen je Seite)
if($action==='pages_revisions'){
    rrw_auth(false);$id=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($_GET['id']??''));
    $list=rrw_tools_read(rrw_page_revisions_file($dataDir),['pages'=>[]])['pages'][$id]??[];
    rrw_json(['status'=>'ok','revisions'=>array_values(array_map(fn($r,$i)=>['index'=>$i,'saved_at'=>(string)($r['saved_at']??''),'user'=>(string)($r['user']??''),'title'=>(string)($r['title']??'')],(array)$list,array_keys((array)$list)))]);
}
if($action==='pages_revision'){
    rrw_auth(false);$id=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($_GET['id']??''));$i=(int)($_GET['index']??-1);
    $list=rrw_tools_read(rrw_page_revisions_file($dataDir),['pages'=>[]])['pages'][$id]??[];
    if(!isset($list[$i]['page']))rrw_json(['status'=>'error','message'=>'Version nicht gefunden'],404);
    rrw_json(['status'=>'ok','page'=>$list[$i]['page']]);
}
if($action==='services_status'&&!rrw_pack_available()){
    // Eigene Dienste des Betreibers: Erreichbarkeit serverseitig prüfen (nur Administratoren, mit Stundenlimit)
    rrw_auth(true);require_once __DIR__.'/lib/services.php';
    if(!rrw_apps_rate_ok($dataDir,'services',120))rrw_json(['status'=>'error','message'=>'Zu viele Prüfungen – bitte später erneut versuchen.'],429);
    rrw_json(['status'=>'ok','services'=>rrw_services_probe((array)(rrw_services_clean((array)($site['services']??[]))['items']),rrw_site_origin($site))]);
}
if($action==='services_status'){rrw_auth(false);$out=[];foreach((array)($site['services']??[]) as $id=>$url)$out[]=['id'=>$id,'name'=>$id,'url'=>$url,'configured'=>$url!=='','online'=>null,'http'=>0,'ms'=>0];rrw_json(['status'=>'ok','services'=>$out]);}
if($action==='feed_test'){
    rrw_auth(false);$b=rrw_body();$source=is_array($b['source']??null)?$b['source']:[];
    $url=trim((string)($source['url']??''));if($url==='')rrw_json(['status'=>'error','message'=>'Bitte zuerst eine Feed-URL eintragen'],400);
    $tmp=['feed_sources'=>[[
        'id'=>'test','name'=>(string)($source['name']??'Test-Feed'),'url'=>$url,'category'=>(string)($source['category']??'Extern'),
        'enabled'=>true,'max_items'=>max(1,min(10,(int)($source['max_items']??5)))
    ]]];
    $rows=rrw_external_feed_articles($tmp);
    if(!$rows)rrw_json(['status'=>'error','message'=>'Feed konnte nicht gelesen werden oder enthält keine unterstützten RSS-/Atom-Einträge'],422);
    rrw_json(['status'=>'ok','count'=>count($rows),'sample'=>array_slice(array_map(fn($x)=>['title'=>$x['title']??'','published_at'=>$x['published_at']??'','external_url'=>$x['external_url']??''], $rows),0,3)]);
}

// File-based News
$news=rrw_read_json($newsFile,[]);
// Papierkorb wie bei WordPress nach 30 Tagen automatisch endgültig leeren - lazy bei jedem
// Request geprüft (kein echter Cron auf diesem Hosting nötig), betrifft nur alte Einträge.
$newsTrashCutoff=date('Y-m-d H:i:s',strtotime('-30 days'));
$newsPurged=array_values(array_filter($news,fn($a)=>empty($a['deleted_at'])||(string)$a['deleted_at']>$newsTrashCutoff));
if(count($newsPurged)!==count($news)){
    $news=$newsPurged;
    rrw_write_atomic($newsFile,json_encode($news,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    rrw_log_activity($activityLogFile,null,'news_trash_auto_purge','Papierkorb automatisch geleert (Beiträge älter als 30 Tage)');
}
$newsViews=rrw_read_json($newsViewsFile,[]);
if(empty($news) && in_array($action,['news_list','news_get','news_public'],true)) {
    $tok=rrw_token();
    if($tok!=='') {
        try {
            $legacy=rrw_control_center_json('radio_cms_legacy_news_export',$tok);
            $legacyRows=is_array($legacy['articles']??null)?$legacy['articles']:[];
            if($legacyRows) {
                $news=array_values($legacyRows);
                rrw_write_atomic($newsFile,json_encode($news,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
            }
        } catch(Throwable $e) {}
    }
}
if($action==='news_public'){
    // Läuft nicht nur bei komplett leerer news.json, sondern auch dann, wenn lokal (noch) kein
    // einziger Artikel als "live" gilt: Ein Artikel kann lokal als Entwurf importiert worden sein,
    // bevor er im Control Center final veröffentlicht wurde (Zeitpunkt des allerersten Imports lag
    // vor der Veröffentlichung dort). Ohne diesen erweiterten Trigger bliebe so ein Eintrag dauerhaft
    // hängen, weil die reine "$news ist leer"-Prüfung danach nie wieder zutrifft.
    if(!rrw_standalone()&&!array_filter($news,'rrw_news_is_live')){
        $legacy=rrw_fetch_legacy_news_local();
        if(!is_array($legacy)){
            $legacyRemote=rrw_fetch_json_url('https://www.ricorewi-radio.de/control/cron.php?action=news_public_legacy_fallback&_='.time());
            $legacy=is_array($legacyRemote['articles']??null)?$legacyRemote['articles']:null;
        }
        if(is_array($legacy)&&$legacy){
            // Merge statt Überschreiben: nur tatsächlich im Control Center veröffentlichte Legacy-
            // Artikel werden per Slug eingepflegt (neu angelegt oder ein vorhandener, noch nicht
            // veröffentlichter lokaler Eintrag auf "published" aktualisiert). So gehen weder andere,
            // bereits lokal im CMS gepflegte Entwürfe verloren, noch überschreibt ein leerer/fehlerhafter
            // Fallback versehentlich vorhandene Inhalte.
            $bySlug=[]; foreach($news as $i=>$a)$bySlug[(string)($a['slug']??'')]=$i;
            $changed=false;
            foreach($legacy as $row){
                if(!is_array($row)||($row['status']??'')!=='published')continue;
                $slug=(string)($row['slug']??''); if($slug==='')continue;
                if(isset($bySlug[$slug])){
                    $idx=$bySlug[$slug];
                    if(($news[$idx]['status']??'')!=='published'){$news[$idx]=$row+$news[$idx];$news[$idx]['status']='published';$changed=true;}
                } else {
                    $news[]=$row;$bySlug[$slug]=array_key_last($news);$changed=true;
                }
            }
            if($changed){try{ rrw_write_atomic($newsFile,json_encode($news,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n"); }catch(Throwable $e){}}
        }
    }
    $published=array_values(array_filter($news,'rrw_news_is_live'));
    $published=array_map(fn($a)=>$a+['views'=>rrw_news_view_count($newsViews,(int)($a['id']??0))],$published);
    $published=array_merge($published,rrw_external_feed_articles($site));
    usort($published,fn($a,$b)=>strcmp((string)($b['published_at']??$b['created_at']??''),(string)($a['published_at']??$a['created_at']??'')));
    foreach($published as &$pa)if(isset($pa['body_html'])&&is_string($pa['body_html'])&&str_contains($pa['body_html'],'['))$pa['body_html']=rrw_expand_shortcodes($pa['body_html']);unset($pa);
    $slug=trim((string)($_GET['slug']??''));
    if($slug!==''){
        foreach($published as $a)if((string)($a['slug']??'')===$slug)rrw_json(['status'=>'ok','article'=>$a]);
        rrw_json(['status'=>'error','message'=>'Artikel nicht gefunden'],404);
    }
    $limit=max(1,min(100,(int)($_GET['limit']??50)));
    rrw_json(['status'=>'ok','articles'=>array_slice($published,0,$limit),'external_sources'=>count(array_filter((array)($site['feed_sources']??[]),fn($s)=>!empty($s['enabled'])))]);
}
$newsAuth=null;
if(in_array($action,['news_list','news_get','news_save','news_quick_edit','news_delete','news_thumbnail_upload','news_trash_list','news_restore','news_delete_permanent','news_bulk_action','news_category_rename','news_revisions_list','news_revision_restore','news_duplicate','news_export','news_import','news_tags','news_tag_change'],true))$newsAuth=rrw_auth(false);
if($action==='news_list')rrw_json(['status'=>'ok','articles'=>array_values(array_map(fn($a)=>$a+['can_edit'=>rrw_news_can_edit($a,$newsAuth),'views'=>rrw_news_view_count($newsViews,(int)($a['id']??0))],array_filter($news,fn($a)=>empty($a['deleted_at'])))),'can_edit'=>true]);
if($action==='news_trash_list')rrw_json(['status'=>'ok','articles'=>array_values(array_map(fn($a)=>$a+['can_edit'=>rrw_news_can_edit($a,$newsAuth)],array_filter($news,fn($a)=>!empty($a['deleted_at'])))),'can_edit'=>true]);
if($action==='news_tags'){
    // Schlagwörter stehen als Komma-Liste an jedem Beitrag; hier werden sie wie in WordPress als Übersicht mit Zählern zusammengefasst (Groß-/Kleinschreibung egal)
    $by=[];
    foreach($news as $a){
        if(!empty($a['deleted_at']))continue;$seen=[];
        foreach(rrw_news_tag_list((string)($a['tags']??'')) as $t){$k=mb_strtolower($t);if(isset($seen[$k]))continue;$seen[$k]=1;
            $by[$k]=$by[$k]??['tag'=>$t,'count'=>0,'live'=>0,'spell'=>[]];$by[$k]['count']++;if(($a['status']??'')==='published')$by[$k]['live']++;$by[$k]['spell'][$t]=($by[$k]['spell'][$t]??0)+1;}
    }
    foreach($by as &$r){arsort($r['spell']);$r['tag']=(string)array_key_first($r['spell']);unset($r['spell']);}unset($r);
    $out=array_values($by);usort($out,fn($x,$y)=>$y['count']<=>$x['count']?:strcasecmp($x['tag'],$y['tag']));
    rrw_json(['status'=>'ok','tags'=>$out]);
}
if($action==='news_tag_change'){
    // Umbenennen, Zusammenführen (Ziel existiert schon) oder Löschen (Ziel leer) – nur bei Beiträgen, die der Benutzer bearbeiten darf
    $b=rrw_body();$from=trim((string)($b['from']??''));$to=mb_substr(trim(str_replace(',',' ',(string)($b['to']??''))),0,60);
    if($from==='')rrw_json(['status'=>'error','message'=>'Schlagwort fehlt'],400);
    $fk=mb_strtolower($from);$tk=mb_strtolower($to);$changed=0;$denied=0;
    foreach($news as &$a){
        $list=rrw_news_tag_list((string)($a['tags']??''));
        $has=false;foreach($list as $t)if(mb_strtolower($t)===$fk){$has=true;break;}
        if(!$has)continue;
        if(!rrw_news_can_edit($a,$newsAuth)){$denied++;continue;}
        $new=[];$seen=[];
        foreach($list as $t){
            $x=mb_strtolower($t)===$fk?$to:$t;if($x==='')continue;
            $k=mb_strtolower($x);if(isset($seen[$k]))continue;$seen[$k]=1;$new[]=$x;
        }
        $a['tags']=mb_substr(implode(',',$new),0,800);$a['updated_at']=date('Y-m-d H:i:s');$changed++;
    }unset($a);
    if($changed>0){
        rrw_write_atomic($newsFile,json_encode($news,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
        rrw_log_activity($activityLogFile,$newsAuth,'news_tag_change','Schlagwort „'.$from.'“ '.($to===''?'gelöscht':($tk===$fk?'umbenannt in „'.$to.'“':'zu „'.$to.'“ geändert/zusammengeführt')).' ('.$changed.' Beitrag/Beiträge)');
    }
    rrw_json(['status'=>'ok','changed'=>$changed,'denied'=>$denied]);
}
if($action==='news_export'){
    $rows=array_values(array_filter($news,fn($a)=>empty($a['deleted_at'])));
    $filename='ricorewi-radio-beitraege-'.date('Y-m-d').'.json';
    header_remove('Content-Type');header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="'.$filename.'"');
    echo json_encode(['exported_at'=>date('c'),'site'=>preg_replace('#^https?://#','',rrw_site_origin($site)),'count'=>count($rows),'articles'=>$rows],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
if($action==='news_import'){
    // Gegenstück zu news_export: übernimmt Beiträge aus einer Exportdatei (neue IDs, Texte werden wie beim Speichern bereinigt)
    require_once __DIR__.'/wp/settings-api.php';$defCat=rrw_wps_default('rrw_default_category','News');$b=rrw_body();$in=$b['articles']??null;
    if(!is_array($in)||!$in)rrw_json(['status'=>'error','message'=>'Keine Beiträge in der Datei gefunden'],400);
    if(count($in)>200)rrw_json(['status'=>'error','message'=>'Höchstens 200 Beiträge pro Import'],400);
    $mode=($b['on_duplicate']??'skip')==='copy'?'copy':'skip';$asDraft=array_key_exists('as_draft',$b)?!empty($b['as_draft']):rrw_wps_default('rrw_default_status','draft')!=='published';
    $now=date('Y-m-d H:i:s');$nextId=1;$slugs=[];foreach($news as $a){$nextId=max($nextId,(int)($a['id']??0)+1);$slugs[(string)($a['slug']??'')]=1;}
    $made=0;$skipped=0;$bad=0;
    foreach($in as $r){
        if(!is_array($r)){$bad++;continue;}
        $title=mb_substr(trim(strip_tags((string)($r['title']??''))),0,255);if($title===''){$bad++;continue;}
        $slug=rrw_slug((string)($r['slug']??'')!==''?(string)$r['slug']:$title);
        if(isset($slugs[$slug])){
            if($mode==='skip'){$skipped++;continue;}
            $base=$slug;$n=2;while(isset($slugs[$slug]))$slug=$base.'-'.($n++);
        }
        $slugs[$slug]=1;
        $pub=trim((string)($r['published_at']??''));if(!preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/',$pub))$pub=$now;else $pub=str_replace('T',' ',substr($pub,0,19));
        $news[]=['id'=>$nextId++,'slug'=>$slug,'title'=>$title,'category'=>mb_substr(trim((string)($r['category']??$defCat)),0,80)?:$defCat,'excerpt'=>mb_substr((string)($r['excerpt']??''),0,600),'image_url'=>mb_substr((string)($r['image_url']??''),0,1200),'image_mode'=>in_array(($r['image_mode']??'thumbnail'),['thumbnail','article','both','none'],true)?$r['image_mode']:'thumbnail','external_url'=>mb_substr((string)($r['external_url']??''),0,1200),'video_url'=>mb_substr((string)($r['video_url']??''),0,1200),'tags'=>mb_substr((string)($r['tags']??''),0,800),'embed_html'=>rrw_safe_html((string)($r['embed_html']??'')),'status'=>(!$asDraft&&($r['status']??'')==='published')?'published':'draft','featured'=>!empty($r['featured'])?1:0,'published_at'=>$pub,'body_html'=>rrw_safe_html((string)($r['body_html']??'')),'author'=>$newsAuth['display_name']??rrw_product_title(),'author_user'=>$newsAuth['user']??'','seo_title'=>mb_substr(trim((string)($r['seo_title']??'')),0,70),'seo_description'=>mb_substr(trim((string)($r['seo_description']??'')),0,200),'updated_at'=>$now,'created_at'=>$now];
        $made++;
    }
    if($made>0){
        rrw_write_atomic($newsFile,json_encode($news,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
        if(!isset($site['rss']['enabled'])||!empty($site['rss']['enabled']))rrw_write_atomic($root.'/rss.xml',rrw_rss_xml($site));
        rrw_log_activity($activityLogFile,$newsAuth,'news_import',$made.' Beitrag/Beiträge importiert'.($skipped?', '.$skipped.' übersprungen (Adresse schon vorhanden)':''));
    }
    rrw_json(['status'=>'ok','imported'=>$made,'skipped'=>$skipped,'invalid'=>$bad]);
}
if($action==='news_get'){ $id=(int)($_GET['id']??0);foreach($news as $a)if((int)($a['id']??0)===$id)rrw_json(['status'=>'ok','article'=>$a+['views'=>rrw_news_view_count($newsViews,$id)]]);rrw_json(['status'=>'error','message'=>'Beitrag nicht gefunden'],404); }
if($action==='news_track_view'){
    $b=rrw_body();$id=(int)($b['article_id']??0);
    $article=null;foreach($news as $a)if((int)($a['id']??0)===$id){$article=$a;break;}
    if($article===null||!rrw_news_is_live($article))rrw_json(['status'=>'ok']); // still: keine Fehlermeldung an Besucher für einen simplen Zähler
    rrw_news_track_view($newsViewsFile,$id);
    rrw_json(['status'=>'ok']);
}
if($action==='news_thumbnail_upload')rrw_json(['status'=>'ok','url'=>rrw_upload('news',8388608)]);
if($action==='news_delete'){ $b=rrw_body();$id=(int)($b['id']??0);$now=date('Y-m-d H:i:s');$found=false;$title='';foreach($news as &$a)if((int)($a['id']??0)===$id){if(!rrw_news_can_edit($a,$newsAuth))rrw_json(['status'=>'error','message'=>'Keine Berechtigung für diesen Beitrag'],403);$a['deleted_at']=$now;$found=true;$title=(string)($a['title']??'');break;}unset($a);if(!$found)rrw_json(['status'=>'error','message'=>'Beitrag nicht gefunden'],404);rrw_write_atomic($newsFile,json_encode($news,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");if(!isset($site['rss']['enabled'])||!empty($site['rss']['enabled']))rrw_write_atomic($root.'/rss.xml',rrw_rss_xml($site));rrw_log_activity($activityLogFile,$newsAuth,'news_trash','„'.$title.'“ in den Papierkorb verschoben');rrw_json(['status'=>'ok']);}
if($action==='news_restore'){ $b=rrw_body();$id=(int)($b['id']??0);$found=false;$title='';foreach($news as &$a)if((int)($a['id']??0)===$id){if(!rrw_news_can_edit($a,$newsAuth))rrw_json(['status'=>'error','message'=>'Keine Berechtigung für diesen Beitrag'],403);unset($a['deleted_at']);$found=true;$title=(string)($a['title']??'');break;}unset($a);if(!$found)rrw_json(['status'=>'error','message'=>'Beitrag nicht gefunden'],404);rrw_write_atomic($newsFile,json_encode($news,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");if(!isset($site['rss']['enabled'])||!empty($site['rss']['enabled']))rrw_write_atomic($root.'/rss.xml',rrw_rss_xml($site));rrw_log_activity($activityLogFile,$newsAuth,'news_restore','„'.$title.'“ aus dem Papierkorb wiederhergestellt');rrw_json(['status'=>'ok']);}
if($action==='news_delete_permanent'){ $delAuth=rrw_auth(true);$b=rrw_body();$id=(int)($b['id']??0);$before=count($news);$title='';foreach($news as $a)if((int)($a['id']??0)===$id){$title=(string)($a['title']??'');break;}$news=array_values(array_filter($news,fn($a)=>(int)($a['id']??0)!==$id||empty($a['deleted_at'])));if(count($news)===$before)rrw_json(['status'=>'error','message'=>'Beitrag nicht im Papierkorb'],404);rrw_write_atomic($newsFile,json_encode($news,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");rrw_log_activity($activityLogFile,$delAuth,'news_delete_permanent','„'.$title.'“ endgültig gelöscht');rrw_json(['status'=>'ok']);}
if($action==='news_bulk_action'){
    $b=rrw_body();$op=(string)($b['op']??'');$ids=array_map('intval',(array)($b['ids']??[]));$ids=array_values(array_filter($ids,fn($x)=>$x>0));
    if(!$ids)rrw_json(['status'=>'error','message'=>'Keine Beiträge ausgewählt'],400);
    $value=(string)($b['value']??'');
    if(!in_array($op,['trash','restore','delete_permanent','publish','draft','feature','unfeature','set_category'],true))rrw_json(['status'=>'error','message'=>'Unbekannte Aktion'],400);
    if($op==='delete_permanent')rrw_auth(true);
    if($op==='set_category'&&trim($value)==='')rrw_json(['status'=>'error','message'=>'Bitte eine Kategorie auswählen'],400);
    $now=date('Y-m-d H:i:s');$affected=0;
    if($op==='delete_permanent'){
        $before=count($news);
        $news=array_values(array_filter($news,fn($a)=>!(in_array((int)($a['id']??0),$ids,true)&&!empty($a['deleted_at']))));
        $affected=$before-count($news);
    } else {
        foreach($news as &$a){
            if(!in_array((int)($a['id']??0),$ids,true))continue;
            if(!rrw_news_can_edit($a,$newsAuth))continue;
            if($op==='trash'){$a['deleted_at']=$now;$affected++;}
            elseif($op==='restore'){unset($a['deleted_at']);$affected++;}
            elseif($op==='publish'){$a['status']='published';$a['updated_at']=$now;$affected++;}
            elseif($op==='draft'){$a['status']='draft';$a['updated_at']=$now;$affected++;}
            elseif($op==='feature'){$a['featured']=1;$a['updated_at']=$now;$affected++;}
            elseif($op==='unfeature'){$a['featured']=0;$a['updated_at']=$now;$affected++;}
            elseif($op==='set_category'){$a['category']=mb_substr($value,0,80);$a['updated_at']=$now;$affected++;}
        }
        unset($a);
    }
    rrw_write_atomic($newsFile,json_encode($news,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    if(in_array($op,['trash','restore','publish','draft'],true)&&(!isset($site['rss']['enabled'])||!empty($site['rss']['enabled'])))rrw_write_atomic($root.'/rss.xml',rrw_rss_xml($site));
    if($affected>0)rrw_log_activity($activityLogFile,$newsAuth,'news_bulk_'.$op,$affected.' Beitrag/Beiträge per Massenaktion „'.$op.'“ bearbeitet');
    rrw_json(['status'=>'ok','affected'=>$affected]);
}
if($action==='news_category_rename'){
    // Kategorie umbenennen: aktualisiert sowohl die Kategorie-Liste (site.json) als auch alle
    // bereits vorhandenen Beiträge, die dieser Kategorie zugewiesen sind - sonst würden sie
    // stillschweigend auf einen nicht mehr existierenden Namen zeigen (anders als bei WordPress'
    // ID-basierten Taxonomien ist "Kategorie" hier ein reiner Freitext-Wert pro Beitrag).
    $b=rrw_body();$old=trim((string)($b['old']??''));$new=mb_substr(trim((string)($b['new']??'')),0,40);
    if($old===''||$new==='')rrw_json(['status'=>'error','message'=>'Alter und neuer Name dürfen nicht leer sein'],400);
    $cats=(array)($site['news_categories']??[]);
    if(!in_array($old,$cats,true))rrw_json(['status'=>'error','message'=>'Kategorie nicht gefunden'],404);
    if($old!==$new&&in_array($new,$cats,true))rrw_json(['status'=>'error','message'=>'Eine Kategorie mit diesem Namen existiert bereits'],400);
    $cats=array_values(array_map(fn($c)=>$c===$old?$new:$c,$cats));
    $site['news_categories']=$cats;
    $affected=0;
    foreach($news as &$a){if(($a['category']??'')===$old){$a['category']=$new;$affected++;}}unset($a);
    rrw_write_atomic($newsFile,json_encode($news,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    rrw_publish($site,$siteFile,$genDir,$root);
    rrw_log_activity($activityLogFile,$newsAuth,'news_category_rename','Kategorie „'.$old.'“ in „'.$new.'“ umbenannt ('.$affected.' Beitrag/Beiträge aktualisiert)');
    rrw_json(['status'=>'ok','categories'=>$cats,'affected'=>$affected]);
}
if($action==='news_duplicate'){
    $b=rrw_body();$id=(int)($b['id']??0);$src=null;foreach($news as $a)if((int)($a['id']??0)===$id){$src=$a;break;}
    if($src===null)rrw_json(['status'=>'error','message'=>'Beitrag nicht gefunden'],404);
    if(!rrw_news_can_edit($src,$newsAuth))rrw_json(['status'=>'error','message'=>'Keine Berechtigung für diesen Beitrag'],403);
    $newId=1;foreach($news as $a)$newId=max($newId,(int)($a['id']??0)+1);
    $now=date('Y-m-d H:i:s');
    $copy=$src;$copy['id']=$newId;$copy['title']=trim((string)($src['title']??'')).' (Kopie)';
    $copy['slug']=rrw_slug($copy['title'].'-'.$newId);
    $copy['status']='draft';$copy['featured']=0;$copy['published_at']=$now;$copy['created_at']=$now;$copy['updated_at']=$now;
    $copy['author']=$newsAuth['display_name']??($src['author']??'RicoReWi Radio CMS');$copy['author_user']=$newsAuth['user']??'';
    unset($copy['deleted_at']);
    $news[]=$copy;
    rrw_write_atomic($newsFile,json_encode($news,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    rrw_log_activity($activityLogFile,$newsAuth,'news_duplicate','Beitrag „'.($src['title']??'').'“ als Entwurf dupliziert');
    rrw_json(['status'=>'ok','id'=>$newId,'article'=>$copy]);
}
if($action==='news_save'){
    $b=rrw_body();$id=(int)($b['id']??0);if($id<=0){$id=1;foreach($news as $a)$id=max($id,(int)($a['id']??0)+1);}
    $now=date('Y-m-d H:i:s');$existing=null;foreach($news as $a)if((int)($a['id']??0)===$id){$existing=$a;break;}
    if($existing!==null&&!rrw_news_can_edit($existing,$newsAuth))rrw_json(['status'=>'error','message'=>'Keine Berechtigung für diesen Beitrag'],403);
    $slug=rrw_slug((string)($b['slug']??($existing['slug']??$b['title']??'news')));
    $article=['id'=>$id,'slug'=>$slug,'title'=>mb_substr(trim((string)($b['title']??'')),0,255),'category'=>mb_substr((string)($b['category']??'News'),0,80),'excerpt'=>mb_substr((string)($b['excerpt']??''),0,600),'image_url'=>mb_substr((string)($b['image_url']??''),0,1200),'image_mode'=>in_array(($b['image_mode']??'thumbnail'),['thumbnail','article','both','none'],true)?$b['image_mode']:'thumbnail','external_url'=>mb_substr((string)($b['external_url']??''),0,1200),'video_url'=>mb_substr((string)($b['video_url']??''),0,1200),'tags'=>mb_substr((string)($b['tags']??''),0,800),'embed_html'=>rrw_safe_html((string)($b['embed_html']??'')),'status'=>($b['status']??'draft')==='published'?'published':'draft','featured'=>!empty($b['featured'])?1:0,'published_at'=>trim((string)($b['published_at']??''))?:$now,'body_html'=>rrw_safe_html((string)($b['body_html']??'')),'author'=>$existing['author']??($newsAuth['display_name']??rrw_product_title()),'author_user'=>$existing['author_user']??($newsAuth['user']??''),'seo_title'=>mb_substr(trim((string)($b['seo_title']??'')),0,70),'seo_description'=>mb_substr(trim((string)($b['seo_description']??'')),0,200),'updated_at'=>$now,'created_at'=>$now];
    if($existing!==null)rrw_news_save_revision($revisionsFile,$id,$existing);
    $found=false;foreach($news as &$a)if((int)($a['id']??0)===$id){$article['created_at']=$a['created_at']??$now;$a=$article;$found=true;break;}unset($a);if(!$found)$news[]=$article;
    rrw_write_atomic($newsFile,json_encode($news,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");if(!isset($site['rss']['enabled'])||!empty($site['rss']['enabled']))rrw_write_atomic($root.'/rss.xml',rrw_rss_xml($site));rrw_log_activity($activityLogFile,$newsAuth,$existing!==null?'news_update':'news_create',($existing!==null?'Beitrag „':'Neuer Beitrag „').$article['title'].'“ '.($existing!==null?'bearbeitet':'angelegt'));rrw_json(['status'=>'ok','id'=>$id]);
}
if($action==='news_quick_edit'){
    // Im Gegensatz zu news_save (baut den kompletten Artikel aus dem Request neu auf) ändert
    // Quick-Edit gezielt nur die übergebenen Felder am bestehenden Artikel - wie bei WordPress'
    // Schnellbearbeitung. So bleiben Artikeltext, SEO, Tags, Bild etc. unangetastet.
    $b=rrw_body();$id=(int)($b['id']??0);
    $idx=null;foreach($news as $i=>$a)if((int)($a['id']??0)===$id){$idx=$i;break;}
    if($idx===null)rrw_json(['status'=>'error','message'=>'Beitrag nicht gefunden'],404);
    if(!rrw_news_can_edit($news[$idx],$newsAuth))rrw_json(['status'=>'error','message'=>'Keine Berechtigung für diesen Beitrag'],403);
    rrw_news_save_revision($revisionsFile,$id,$news[$idx]);
    if(array_key_exists('title',$b))$news[$idx]['title']=mb_substr(trim((string)$b['title']),0,255);
    if(array_key_exists('category',$b))$news[$idx]['category']=mb_substr((string)$b['category'],0,80);
    if(array_key_exists('status',$b))$news[$idx]['status']=($b['status']==='published')?'published':'draft';
    if(array_key_exists('featured',$b))$news[$idx]['featured']=!empty($b['featured'])?1:0;
    if(array_key_exists('published_at',$b)&&trim((string)$b['published_at'])!=='')$news[$idx]['published_at']=trim((string)$b['published_at']);
    if($news[$idx]['title']==='')rrw_json(['status'=>'error','message'=>'Titel darf nicht leer sein'],400);
    $news[$idx]['updated_at']=date('Y-m-d H:i:s');
    rrw_write_atomic($newsFile,json_encode($news,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    if(!isset($site['rss']['enabled'])||!empty($site['rss']['enabled']))rrw_write_atomic($root.'/rss.xml',rrw_rss_xml($site));
    rrw_log_activity($activityLogFile,$newsAuth,'news_quick_edit','Beitrag „'.$news[$idx]['title'].'“ per Schnellbearbeitung geändert');
    rrw_json(['status'=>'ok','article'=>$news[$idx]]);
}
if($action==='news_revisions_list'){
    $id=(int)($_GET['id']??0);$all=rrw_read_json($revisionsFile,[]);
    $revs=array_values(array_filter($all,fn($r)=>(int)($r['article_id']??0)===$id));
    usort($revs,fn($a,$b)=>strcmp((string)($b['saved_at']??''),(string)($a['saved_at']??'')));
    rrw_json(['status'=>'ok','revisions'=>array_map(fn($r)=>['revision_id'=>$r['revision_id'],'saved_at'=>$r['saved_at'],'title'=>$r['article']['title']??'','status'=>$r['article']['status']??''],$revs)]);
}
if($action==='news_revision_restore'){
    $b=rrw_body();$id=(int)($b['id']??0);$revisionId=(string)($b['revision_id']??'');
    $all=rrw_read_json($revisionsFile,[]);$target=null;
    foreach($all as $r)if((int)($r['article_id']??0)===$id&&(string)($r['revision_id']??'')===$revisionId){$target=$r['article'];break;}
    if($target===null)rrw_json(['status'=>'error','message'=>'Revision nicht gefunden'],404);
    $current=null;foreach($news as $a)if((int)($a['id']??0)===$id){$current=$a;break;}
    if($current===null)rrw_json(['status'=>'error','message'=>'Beitrag nicht gefunden'],404);
    if(!rrw_news_can_edit($current,$newsAuth))rrw_json(['status'=>'error','message'=>'Keine Berechtigung für diesen Beitrag'],403);
    rrw_news_save_revision($revisionsFile,$id,$current);
    $now=date('Y-m-d H:i:s');$restored=$target;$restored['id']=$id;$restored['created_at']=$current['created_at']??$now;$restored['updated_at']=$now;
    foreach($news as &$a)if((int)($a['id']??0)===$id){$a=$restored;break;}unset($a);
    rrw_write_atomic($newsFile,json_encode($news,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    if(!isset($site['rss']['enabled'])||!empty($site['rss']['enabled']))rrw_write_atomic($root.'/rss.xml',rrw_rss_xml($site));
    rrw_json(['status'=>'ok']);
}
// Kostenlose Kommentarfunktion für News-Beiträge (ein-/ausschaltbar, optional moderiert)
$commentsCfg=(array)($site['comments']??['enabled'=>false,'require_approval'=>true]);
if($action==='comments_settings')rrw_json(['status'=>'ok','enabled'=>!empty($commentsCfg['enabled']),'require_approval'=>!array_key_exists('require_approval',$commentsCfg)||!empty($commentsCfg['require_approval'])]);
if($action==='comments_recent'){
    $limit=max(1,min(10,(int)($_GET['limit']??5)));
    if(empty($commentsCfg['enabled']))rrw_json(['status'=>'ok','comments'=>[],'enabled'=>false]);
    $comments=array_values(array_filter(rrw_read_json($commentsFile,[]),fn($c)=>($c['status']??'')==='approved'));
    usort($comments,fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));
    $out=[];
    foreach(array_slice($comments,0,$limit) as $c){
        $art=null;foreach($news as $n)if((int)($n['id']??0)===(int)($c['article_id']??0)){$art=$n;break;}
        if($art===null||!rrw_news_is_live($art))continue;
        $out[]=['id'=>(int)($c['id']??0),'name'=>(string)($c['name']??''),'excerpt'=>mb_substr(trim((string)($c['text']??'')),0,120),'created_at'=>(string)($c['created_at']??''),'article_title'=>(string)($art['title']??''),'article_slug'=>(string)($art['slug']??'')];
    }
    rrw_json(['status'=>'ok','comments'=>$out,'enabled'=>true]);
}
if($action==='comments_list'){
    if(empty($commentsCfg['enabled']))rrw_json(['status'=>'ok','comments'=>[],'enabled'=>false]);
    $articleId=(int)($_GET['article_id']??0);
    $comments=rrw_read_json($commentsFile,[]);
    $out=array_values(array_filter($comments,fn($c)=>(int)($c['article_id']??0)===$articleId&&($c['status']??'')==='approved'));
    usort($out,fn($a,$b)=>strcmp((string)($a['created_at']??''),(string)($b['created_at']??'')));
    foreach($out as &$c){unset($c['status']);$c['parent_id']=(int)($c['parent_id']??0);$c['is_staff']=!empty($c['is_staff']);}unset($c);
    rrw_json(['status'=>'ok','comments'=>$out,'enabled'=>true]);
}
if($action==='comment_submit'){
    if(empty($commentsCfg['enabled']))rrw_json(['status'=>'error','message'=>'Kommentare sind derzeit deaktiviert'],403);
    $b=rrw_body();
    if(trim((string)($b['hp']??''))!=='')rrw_json(['status'=>'ok']); // Honeypot: Bots bekommen scheinbar Erfolg, es wird nichts gespeichert
    $articleId=(int)($b['article_id']??0);
    $article=null;foreach($news as $a)if((int)($a['id']??0)===$articleId){$article=$a;break;}
    if($article===null||!rrw_news_is_live($article))rrw_json(['status'=>'error','message'=>'Beitrag nicht gefunden'],404);
    $name=mb_substr(trim((string)($b['name']??'')),0,80);
    $text=mb_substr(trim(strip_tags((string)($b['text']??''))),0,2000);
    if($name===''||$text==='')rrw_json(['status'=>'error','message'=>'Bitte Name und Kommentar ausfüllen'],400);
    $comments=rrw_read_json($commentsFile,[]);
    $parentId=rrw_comment_top_parent($comments,(int)($b['parent_id']??0),$articleId);
    if($parentId===null)rrw_json(['status'=>'error','message'=>'Ursprünglicher Kommentar nicht gefunden'],404);
    $id=1;foreach($comments as $c)$id=max($id,(int)($c['id']??0)+1);
    $requireApproval=!array_key_exists('require_approval',$commentsCfg)||!empty($commentsCfg['require_approval']);
    $comment=['id'=>$id,'article_id'=>$articleId,'parent_id'=>$parentId,'name'=>$name,'text'=>$text,'is_staff'=>false,'status'=>$requireApproval?'pending':'approved','created_at'=>date('Y-m-d H:i:s')];
    $comments[]=$comment;
    rrw_write_atomic($commentsFile,json_encode($comments,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    $targetAuthor=(string)($article['author']??'');
    rrw_add_notification($notificationsFile,[
        'type'=>'comment','article_id'=>$articleId,'article_title'=>(string)($article['title']??''),
        'target_author'=>$targetAuthor,'comment_name'=>$name,
        'comment_excerpt'=>mb_substr($text,0,140),
    ]);
    $authorEmail=rrw_local_user_email_for($targetAuthor);
    if($authorEmail!=='')rrw_send_comment_notification_email($site,$authorEmail,(string)($article['title']??''),$name,mb_substr($text,0,140),rrw_cms_admin_url($site));
    rrw_json(['status'=>'ok','pending'=>$requireApproval]);
}
$commentAuth=null;
if(in_array($action,['comments_admin_list','comment_approve','comment_delete','comment_reply','notifications_list','notifications_mark_read'],true))$commentAuth=rrw_auth(false);
if($action==='comment_reply'){
    $b=rrw_body();$articleId=(int)($b['article_id']??0);$parentId=(int)($b['parent_id']??0);
    $article=null;foreach($news as $a)if((int)($a['id']??0)===$articleId){$article=$a;break;}
    if($article===null)rrw_json(['status'=>'error','message'=>'Beitrag nicht gefunden'],404);
    $comments=rrw_read_json($commentsFile,[]);
    $parentId=rrw_comment_top_parent($comments,$parentId,$articleId);
    if($parentId===null)rrw_json(['status'=>'error','message'=>'Ursprünglicher Kommentar nicht gefunden'],404);
    $text=mb_substr(trim(strip_tags((string)($b['text']??''))),0,2000);
    if($text==='')rrw_json(['status'=>'error','message'=>'Bitte einen Text eingeben'],400);
    $id=1;foreach($comments as $c)$id=max($id,(int)($c['id']??0)+1);
    $comment=['id'=>$id,'article_id'=>$articleId,'parent_id'=>$parentId,'name'=>(string)($commentAuth['display_name']??'Redaktion'),'text'=>$text,'is_staff'=>true,'status'=>'approved','created_at'=>date('Y-m-d H:i:s')];
    $comments[]=$comment;
    rrw_write_atomic($commentsFile,json_encode($comments,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    rrw_log_activity($activityLogFile,$commentAuth,'comment_reply','Antwort zu „'.(string)($article['title']??'').'“ verfasst');
    rrw_json(['status'=>'ok','id'=>$id]);
}
if($action==='notifications_list'){
    $all=rrw_read_json($notificationsFile,[]);
    usort($all,fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));
    $all=array_slice($all,0,50);
    $unread=count(array_filter($all,fn($n)=>empty($n['read'])));
    rrw_json(['status'=>'ok','notifications'=>$all,'unread'=>$unread]);
}
if($action==='notifications_mark_read'){
    $b=rrw_body();$id=(string)($b['id']??'');$all=(bool)($b['all']??false);
    $list=rrw_read_json($notificationsFile,[]);
    foreach($list as &$n){if($all||(string)($n['id']??'')===$id)$n['read']=true;}
    unset($n);
    rrw_write_atomic($notificationsFile,json_encode($list,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    rrw_json(['status'=>'ok']);
}
if($action==='comments_admin_list'){
    $comments=rrw_read_json($commentsFile,[]);
    $titleFor=function(int $id) use ($news): string { foreach($news as $a)if((int)($a['id']??0)===$id)return (string)($a['title']??''); return ''; };
    foreach($comments as &$c)$c['article_title']=$titleFor((int)($c['article_id']??0));unset($c);
    usort($comments,fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));
    rrw_json(['status'=>'ok','comments'=>$comments]);
}
if($action==='comment_approve'){
    $b=rrw_body();$id=(int)($b['id']??0);$comments=rrw_read_json($commentsFile,[]);$found=false;$name='';
    foreach($comments as &$c)if((int)($c['id']??0)===$id){$c['status']='approved';$found=true;$name=(string)($c['name']??'');break;}unset($c);
    if(!$found)rrw_json(['status'=>'error','message'=>'Kommentar nicht gefunden'],404);
    rrw_write_atomic($commentsFile,json_encode($comments,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    rrw_log_activity($activityLogFile,$commentAuth,'comment_approve','Kommentar von „'.$name.'“ freigegeben');
    rrw_json(['status'=>'ok']);
}
if($action==='comment_delete'){
    $b=rrw_body();$id=(int)($b['id']??0);$comments=rrw_read_json($commentsFile,[]);$before=count($comments);$name='';
    foreach($comments as $c)if((int)($c['id']??0)===$id){$name=(string)($c['name']??'');break;}
    $comments=array_values(array_filter($comments,fn($c)=>(int)($c['id']??0)!==$id));
    if(count($comments)===$before)rrw_json(['status'=>'error','message'=>'Kommentar nicht gefunden'],404);
    rrw_write_atomic($commentsFile,json_encode($comments,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    rrw_log_activity($activityLogFile,$commentAuth,'comment_delete','Kommentar von „'.$name.'“ gelöscht');
    rrw_json(['status'=>'ok']);
}
if($action==='dashboard_stats'){
    rrw_auth(false);
    $comments=rrw_read_json($commentsFile,[]);
    $newsStats=['published'=>0,'scheduled'=>0,'draft'=>0,'trash'=>0];
    foreach($news as $a){
        if(!empty($a['deleted_at'])){$newsStats['trash']++;continue;}
        if(($a['status']??'draft')!=='published'){$newsStats['draft']++;continue;}
        if(rrw_news_is_live($a))$newsStats['published']++; else $newsStats['scheduled']++;
    }
    $commentStats=['approved'=>0,'pending'=>0];
    foreach($comments as $c){
        if(($c['status']??'')==='pending')$commentStats['pending']++; else $commentStats['approved']++;
    }
    $topViewed=array_filter($news,fn($a)=>empty($a['deleted_at'])&&rrw_news_view_count($newsViews,(int)($a['id']??0))>0);
    $topViewed=array_map(fn($a)=>['id'=>$a['id'],'title'=>$a['title']??'','slug'=>$a['slug']??'','views'=>rrw_news_view_count($newsViews,(int)($a['id']??0))],$topViewed);
    usort($topViewed,fn($a,$b)=>$b['views']<=>$a['views']);
    rrw_json(['status'=>'ok','news'=>$newsStats,'comments'=>$commentStats,'top_viewed'=>array_slice($topViewed,0,5)]);
}
rrw_json(['status'=>'error','message'=>'Unbekannte Aktion'],404);

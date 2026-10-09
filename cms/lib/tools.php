<?php
declare(strict_types=1);
// Website-Werkzeuge wie in WordPress: Wartungsmodus, Weiterleitungen (301/302/307/410) und das 404-Protokoll.
// Die Daten liegen in cms/data/.tools/ (Punkt-Ordner: per Webserver gesperrt, nicht im Repo).
// Diese Datei ist bewusst eigenständig (keine Abhängigkeit von publish.php), damit index.php, brandpage.php und
// web/notfound.php sie schnell und ohne den ganzen CMS-Kern laden können.

/** Sperrt einen Laufzeit-Ordner für den Webserver (.htaccess „Require all denied“, bei Apache 2.2 „Deny from all“). Schadet nie, wird bei jedem Anlegen/Schreiben geprüft. */
function elvado_protect_dir(string $dir): void {
    $f=rtrim($dir,'/').'/.htaccess';
    if(is_file($f)||!is_dir($dir))return;
    @file_put_contents($f,"# Laufzeitdaten des CMS: nie öffentlich abrufbar\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
}
function elvado_tools_dir(string $dataDir): string { return rtrim($dataDir,'/').'/.tools'; }
function elvado_tools_read(string $file, array $fallback): array {
    if(!is_file($file))return $fallback;
    $j=json_decode((string)@file_get_contents($file),true);
    return is_array($j)?$j:$fallback;
}
function elvado_tools_write(string $dir, string $name, array $data): void {
    if(!is_dir($dir))@mkdir($dir,0775,true);
    elvado_protect_dir($dir);
    $file=$dir.'/'.$name;$tmp=$file.'.'.bin2hex(random_bytes(4)).'.tmp';
    if(@file_put_contents($tmp,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n",LOCK_EX)===false)throw new RuntimeException('Datei nicht beschreibbar');
    if(!@rename($tmp,$file)){@unlink($tmp);throw new RuntimeException('Datei nicht beschreibbar');}
}

/* ───────── Wartungsmodus ───────── */
function elvado_maint_defaults(): array {
    return ['enabled'=>false,'title'=>'Wir sind gleich zurück','text'=>'Die Website wird gerade gewartet. Das Radio-Programm läuft in unseren Apps weiter. Bitte schau in Kürze wieder vorbei.','eta'=>'','key'=>''];
}
function elvado_maint_clean($v, ?string $oldKey=null): array {
    $v=is_array($v)?$v:[];$d=elvado_maint_defaults();
    $key=(string)($v['key']??$oldKey??'');
    if(!preg_match('/^[a-f0-9]{16}$/',$key))$key=bin2hex(random_bytes(8));
    return [
        'enabled'=>!empty($v['enabled']),
        'title'=>mb_substr(trim((string)($v['title']??$d['title'])),0,80)?:$d['title'],
        'text'=>mb_substr(trim((string)($v['text']??$d['text'])),0,600),
        'eta'=>mb_substr(trim((string)($v['eta']??'')),0,60),
        'key'=>$key,
    ];
}
function elvado_maint_load(string $dataDir): array {
    $f=elvado_tools_read(elvado_tools_dir($dataDir).'/maintenance.json',[]);
    return $f?elvado_maint_clean($f,(string)($f['key']??'')):elvado_maint_defaults();
}
/** Entscheidung für einen Seitenaufruf: 'allow' (normal ausliefern), 'bypass' (Vorschau-Schlüssel stimmt, Cookie setzen) oder 'block'. */
function elvado_maint_decide(array $cfg, array $cookies, array $query): string {
    if(empty($cfg['enabled']))return 'allow';
    $key=(string)($cfg['key']??'');if($key==='')return 'block';
    if(isset($query['vorschau'])&&is_string($query['vorschau'])&&hash_equals($key,$query['vorschau']))return 'bypass';
    if(isset($cookies['elvado_maint'])&&is_string($cookies['elvado_maint'])&&hash_equals($key,$cookies['elvado_maint']))return 'allow';
    return 'block';
}
function elvado_maint_page(array $cfg, string $siteName): string {
    $e=fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $eta=$cfg['eta']!==''?'<p class="eta">Voraussichtlich wieder erreichbar: <b>'.$e($cfg['eta']).'</b></p>':'';
    return '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>'.$e($cfg['title']).' – '.$e($siteName).'</title>'
        .'<style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;color:#eaf1fb;background:#04070e radial-gradient(900px 500px at 10% -10%,rgba(47,184,255,.16),transparent 60%),radial-gradient(700px 420px at 100% 0,rgba(155,123,255,.12),transparent 60%)}'
        .'main{max-width:520px;text-align:center;padding:34px 28px;border:1px solid rgba(47,184,255,.3);border-radius:22px;background:rgba(10,18,32,.9)}h1{margin:0 0 10px;font-size:1.6rem}p{color:#9fb0c8;line-height:1.6;margin:8px 0}.eta b{color:#eaf1fb}small{display:block;margin-top:18px;color:#62748f}</style></head><body><main><h1>'.$e($cfg['title']).'</h1><p>'.nl2br($e($cfg['text'])).'</p>'.$eta.'<small>'.$e($siteName).'</small></main></body></html>';
}
/** Für index.php / brandpage.php: liefert bei aktivem Wartungsmodus die 503-Seite aus und beendet. Fehler dürfen die Website nie lahmlegen. */
function elvado_maint_gate(string $root): void {
    try{
        $dataDir=$root.'/cms/data';
        $file=elvado_tools_dir($dataDir).'/maintenance.json';
        if(!is_file($file))return;
        $cfg=elvado_maint_load($dataDir);
        $d=elvado_maint_decide($cfg,$_COOKIE,$_GET);
        if($d==='allow')return;
        if($d==='bypass'){
            setcookie('elvado_maint',(string)$cfg['key'],['expires'=>time()+43200,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']),'httponly'=>true,'samesite'=>'Lax']);
            return;
        }
        $site=elvado_tools_read($dataDir.'/site.json',[]);
        $name=(string)($site['portal']['site_name']??'Radio');
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        header('Retry-After: 3600');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        echo elvado_maint_page($cfg,$name);
        exit;
    }catch(Throwable $e){ /* im Zweifel normal ausliefern */ }
}

/* ───────── Weiterleitungen ───────── */
function elvado_redirect_norm_path(string $p): string {
    $p=(string)parse_url($p,PHP_URL_PATH);
    $p=rawurldecode($p);
    $p='/'.ltrim($p,'/');
    if(strlen($p)>1)$p=rtrim($p,'/');
    return mb_strtolower($p);
}
function elvado_redirects_clean($rules): array {
    $out=[];$seen=[];
    foreach(is_array($rules)?$rules:[] as $r){
        if(!is_array($r))continue;
        $from=trim((string)($r['from']??''));
        if($from===''||$from[0]!=='/'||str_starts_with($from,'//')||preg_match('/[\x00-\x1f\s]/',$from))continue;
        $from=mb_substr($from,0,300);
        $code=(int)($r['code']??301);if(!in_array($code,[301,302,307,410],true))$code=301;
        $to=trim((string)($r['to']??''));
        if($code!==410){
            $okPath=$to!==''&&$to[0]==='/'&&!str_starts_with($to,'//');
            $okUrl=(bool)preg_match('#^https?://[^\s/$.?\#].[^\s]*$#i',$to);
            if(!$okPath&&!$okUrl)continue;
            if($okPath&&elvado_redirect_norm_path($to)===elvado_redirect_norm_path($from)&&!str_contains($from,'*'))continue; // Endlosschleife
            $to=mb_substr($to,0,600);
        }else $to='';
        $k=elvado_redirect_norm_path($from).(str_ends_with($from,'*')?'*':'');
        if(isset($seen[$k]))continue;$seen[$k]=1;
        $out[]=['id'=>preg_match('/^[a-f0-9]{8}$/',(string)($r['id']??''))?(string)$r['id']:bin2hex(random_bytes(4)),'from'=>$from,'to'=>$to,'code'=>$code,'note'=>mb_substr(trim((string)($r['note']??'')),0,120)];
        if(count($out)>=500)break;
    }
    if(function_exists('elvado_np_filter'))$out=elvado_np_filter('redirects_clean',$out);   // Plugins (Elvado Redirects): Schleifen erkennen
    return $out;
}
/** Erste passende Regel zum Pfad (exakt, Groß-/Kleinschreibung und Schrägstrich am Ende egal; „*“ am Ende = Präfix) oder null. */
function elvado_redirect_match(array $rules, string $path): ?array {
    $p=elvado_redirect_norm_path($path);
    foreach($rules as $r){
        $from=(string)($r['from']??'');
        if(str_ends_with($from,'*')){
            $base=elvado_redirect_norm_path(substr($from,0,-1));
            if($base==='/'||$p===$base||str_starts_with($p,rtrim($base,'/').'/'))return $r;
        }elseif(elvado_redirect_norm_path($from)===$p)return $r;
    }
    return null;
}
function elvado_404_ignored(string $path): bool {
    return (bool)preg_match('/\.(?:js|css|map|png|jpe?g|gif|webp|avif|svg|ico|woff2?|ttf|eot|mp3|aac|ogg|mp4|webm)$/i',$path)||$path==='/favicon.ico';
}
function elvado_404_log(string $dataDir, string $path, string $referer=''): void {
    $path=mb_substr($path,0,200);if($path===''||elvado_404_ignored($path))return;
    $dir=elvado_tools_dir($dataDir);if(!is_dir($dir)&&!@mkdir($dir,0775,true))return;
    elvado_protect_dir($dir);
    $h=@fopen($dir.'/.404.lock','c');if(!$h)return;
    try{
        if(!@flock($h,LOCK_EX))return;
        $log=elvado_tools_read($dir.'/404.json',['items'=>[]]);$items=is_array($log['items']??null)?$log['items']:[];
        $now=date('Y-m-d H:i:s');$found=false;
        foreach($items as &$it){if(($it['path']??'')===$path){$it['count']=(int)($it['count']??0)+1;$it['last']=$now;if($referer!=='')$it['ref']=mb_substr($referer,0,200);$found=true;break;}}
        unset($it);
        if(!$found)$items[]=['path'=>$path,'count'=>1,'first'=>$now,'last'=>$now,'ref'=>mb_substr($referer,0,200)];
        usort($items,fn($a,$b)=>strcmp((string)($b['last']??''),(string)($a['last']??'')));
        $items=array_slice($items,0,300);
        elvado_tools_write($dir,'404.json',['items'=>$items]);
    }catch(Throwable $e){}
    finally{@flock($h,LOCK_UN);@fclose($h);}
}
function elvado_notfound_page(string $siteName): string {
    $e=fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    return '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Seite nicht gefunden – '.$e($siteName).'</title>'
        .'<style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;color:#eaf1fb;background:#04070e radial-gradient(900px 500px at 10% -10%,rgba(47,184,255,.16),transparent 60%)}'
        .'main{max-width:480px;text-align:center;padding:34px 28px;border:1px solid rgba(47,184,255,.3);border-radius:22px;background:rgba(10,18,32,.9)}h1{margin:0 0 6px;font-size:3rem;color:#2fb8ff}p{color:#9fb0c8;line-height:1.6}a{display:inline-block;margin-top:12px;padding:11px 20px;border-radius:12px;background:linear-gradient(135deg,#38c6ff,#1a8fd6);color:#04121f;font-weight:800;text-decoration:none}</style></head>'
        .'<body><main><h1>404</h1><p>Diese Seite gibt es nicht (mehr). Vielleicht hilft dir die Startseite von '.$e($siteName).' weiter.</p><a href="/">Zur Startseite</a></main></body></html>';
}
/** Für web/notfound.php (ErrorDocument 404): Weiterleitung, 410 oder die Fehlerseite samt Protokoll. */
function elvado_notfound_handle(string $root): void {
    $dataDir=$root.'/cms/data';
    elvado_protect_dir($dataDir);elvado_protect_dir(elvado_tools_dir($dataDir));elvado_protect_dir($root.'/cms/backups');
    $uri=(string)($_SERVER['REQUEST_URI']??'/');
    $path=(string)parse_url($uri,PHP_URL_PATH);
    $rules=elvado_tools_read(elvado_tools_dir($dataDir).'/redirects.json',['rules'=>[]])['rules']??[];
    $hit=is_array($rules)?elvado_redirect_match($rules,$path):null;
    if($hit){
        $code=(int)($hit['code']??301);
        if($code===410){http_response_code(410);header('Content-Type: text/plain; charset=utf-8');echo 'Diese Seite wurde dauerhaft entfernt.';return;}
        header('Location: '.str_replace(["\r","\n"],'',(string)$hit['to']),true,$code);
        return;
    }
    elvado_404_log($dataDir,$path,(string)($_SERVER['HTTP_REFERER']??''));
    $site=elvado_tools_read($dataDir.'/site.json',[]);
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo elvado_notfound_page((string)($site['portal']['site_name']??'Radio'));
}

/* ───────── Seiten: Planung (Veröffentlichung zu einem Zeitpunkt) und Versionen ───────── */
/** „2026-12-24T18:00“ / „2026-12-24 18:00“ → „2026-12-24 18:00:00“ (Serverzeit) oder ''. */
function elvado_page_publish_at($v): string {
    if(!is_scalar($v))return '';
    $v=trim((string)$v);if($v==='')return '';
    if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/',$v,$m)||!checkdate((int)$m[2],(int)$m[3],(int)$m[1])||(int)$m[4]>23||(int)$m[5]>59)return '';
    return sprintf('%04d-%02d-%02d %02d:%02d:00',$m[1],$m[2],$m[3],$m[4],$m[5]);
}
/** Eine Seite ist sichtbar, wenn sie aktiv ist und ihr Veröffentlichungszeitpunkt erreicht ist. */
function elvado_page_is_live(array $p, ?int $now=null): bool {
    if(empty($p['enabled']))return false;
    $at=elvado_page_publish_at($p['publish_at']??'');
    return $at===''||strtotime($at)<=($now??time());
}
/** Merkt sich, welche Seiten erst später erscheinen (slug → Zeitpunkt); brandpage.php blendet sie bis dahin aus. */
function elvado_page_schedule_write(string $dataDir, array $pages): void {
    $map=[];
    foreach($pages as $p){
        if(!is_array($p)||empty($p['enabled']))continue;
        $at=elvado_page_publish_at($p['publish_at']??'');
        if($at!==''&&($p['type']??'')==='custom'&&!empty($p['slug']))$map[(string)$p['slug']]=$at;
    }
    try{elvado_tools_write(elvado_tools_dir($dataDir),'page-schedule.json',['pages'=>$map]);}catch(Throwable $e){}
}
function elvado_page_hidden_until_due(string $dataDir, string $slug): bool {
    $map=elvado_tools_read(elvado_tools_dir($dataDir).'/page-schedule.json',['pages'=>[]])['pages']??[];
    if(!is_array($map)||!isset($map[$slug]))return false;
    $ts=strtotime((string)$map[$slug]);
    return $ts!==false&&$ts>time();
}
/** Vorschau-Schlüssel des Wartungsmodus gilt auch, um geplante Seiten schon vor der Zeit zu sehen. */
function elvado_preview_key_ok(string $dataDir): bool {
    $cfg=elvado_maint_load($dataDir);$key=(string)($cfg['key']??'');if($key==='')return false;
    foreach([$_GET['vorschau']??null,$_COOKIE['elvado_maint']??null] as $v)if(is_string($v)&&hash_equals($key,$v))return true;
    return false;
}
function elvado_page_revisions_file(string $dataDir): string { return elvado_tools_dir($dataDir).'/page-revisions.json'; }
/** Legt von jeder geänderten Seite die vorherige Fassung ab (je Seite die letzten 10). */
function elvado_page_revisions_record(string $dataDir, $oldPages, $newPages, string $user): int {
    $old=[];foreach(is_array($oldPages)?$oldPages:[] as $p)if(is_array($p)&&isset($p['id']))$old[(string)$p['id']]=$p;
    $store=elvado_tools_read(elvado_page_revisions_file($dataDir),['pages'=>[]]);$all=is_array($store['pages']??null)?$store['pages']:[];
    $n=0;$now=date('Y-m-d H:i:s');
    foreach(is_array($newPages)?$newPages:[] as $p){
        if(!is_array($p)||!isset($p['id'])||!isset($old[(string)$p['id']]))continue;
        $id=(string)$p['id'];
        if(json_encode($old[$id])===json_encode($p))continue;
        $list=is_array($all[$id]??null)?$all[$id]:[];
        array_unshift($list,['saved_at'=>$now,'user'=>mb_substr($user,0,60),'title'=>(string)($old[$id]['title']??''),'page'=>$old[$id]]);
        $all[$id]=array_slice($list,0,10);$n++;
    }
    if($n===0)return 0;
    if(count($all)>60)$all=array_slice($all,-60,null,true);
    try{elvado_tools_write(elvado_tools_dir($dataDir),'page-revisions.json',['pages'=>$all]);}catch(Throwable $e){return 0;}
    return $n;
}

<?php
// Front-Controller der WordPress-Theme-Laufzeit: liefert die Website mit einem WordPress-Theme (PHP) aus.
// Aktiv, wenn die Datei cms/data/.wp/front-on existiert (Aktivieren eines WordPress-Themes im CMS) oder ein gültiger Vorschau-Schlüssel mitgegeben wird.
// Aufruf über index.php (Startseite) und die Umschreibregel in .htaccess (alle weiteren nicht vorhandenen Pfade).
declare(strict_types=1);
$cmsDir=__DIR__;$root=dirname(__DIR__);
if(is_file($cmsDir.'/lib/demo.json')){ require_once $cmsDir.'/lib/demo.php';rrw_demo_boot();rrw_demo_guard_front((string)parse_url((string)($_SERVER['REQUEST_URI']??'/'),PHP_URL_PATH));ob_start('rrw_demo_inject'); }   // Demo-Betrieb
$flag=$cmsDir.'/data/.wp/front-on';
/* Sandbox: geheimer Link (?rrw_sbx=<Schlüssel>) → Cookie; eigene Optionen/Themes, nur lesend, nicht indexierbar (siehe wp/sandbox.php) */
$sbx=false;
$sbxTok=(string)($_GET['rrw_sbx']??$_COOKIE['rrw_sbx']??'');
if($sbxTok!==''){
    require_once $cmsDir.'/wp/sandbox.php';
    if($sbxTok==='off'){ foreach(['rrw_sbx','rrw_wp_preview','rrw_wp_draft'] as $c)setcookie($c,'',['expires'=>1,'path'=>'/']);header('Location: /');http_response_code(302);exit; }
    if(rrw_sbx_token_ok($sbxTok)){
        rrw_sbx_enter();$sbx=true;header('X-Robots-Tag: noindex, nofollow',true);header('Cache-Control: no-store');
        if(isset($_GET['rrw_sbx']))setcookie('rrw_sbx',$sbxTok,['expires'=>time()+14*86400,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);
        if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){ http_response_code(405);header('Content-Type: text/plain; charset=utf-8');echo 'Die Sandbox ist schreibgeschützt.';exit; }
    }elseif(isset($_COOKIE['rrw_sbx'])&&!isset($_GET['rrw_sbx'])){ setcookie('rrw_sbx','',['expires'=>1,'path'=>'/']);header('Location: '.str_replace(["\r","\n"],'',(string)($_SERVER['REQUEST_URI']??'/')));http_response_code(302);exit; }   // Sandbox gelöscht/Link erneuert → altes Cookie ablegen
    else{ http_response_code(404);header('Content-Type: text/plain; charset=utf-8');echo 'Seite nicht gefunden';exit; }
}
require_once $cmsDir.'/wp/load.php';

/* Vorschau: signierter, 15 Minuten gültiger Schlüssel (?rrw_wp_preview=<theme>.<ablauf>.<signatur>) → Cookie */
$preview='';
if(($_GET['rrw_wp_preview']??'')==='off'){ setcookie('rrw_wp_preview','',['expires'=>1,'path'=>'/']);setcookie('rrw_wp_draft','',['expires'=>1,'path'=>'/']);header('Location: /');http_response_code(302);exit; }
$tok=(string)($_GET['rrw_wp_preview']??$_COOKIE['rrw_wp_preview']??'');
if($tok!==''&&preg_match('/^([a-z0-9_-]{1,80})\.(\d{9,11})\.([a-f0-9]{64})$/',$tok,$m)&&(int)$m[2]>time()&&hash_equals(hash_hmac('sha256',$m[1].'|'.$m[2],wp_salt('preview')),$m[3])){
    $preview=$m[1];
    if(isset($_GET['rrw_wp_preview']))setcookie('rrw_wp_preview',$tok,['expires'=>(int)$m[2],'path'=>'/','httponly'=>true,'samesite'=>'Lax']);
    $GLOBALS['rrw_wp_preview_theme']=$preview;
    $parent=$preview;foreach(array_map(fn($rt)=>$rt['dir'].'/'.$preview,rrw_wp_theme_roots()) as $d)if(is_file($d.'/style.css')){ $h=get_file_data($d.'/style.css',['Template'=>'Template']);if($h['Template']!==''&&preg_match('/^[a-z0-9_-]{1,80}$/',$h['Template']))$parent=$h['Template'];break; }
    add_filter('pre_option_stylesheet',fn()=>$preview);add_filter('pre_option_template',fn()=>$parent);
}
// Customizer-Vorschau: ungespeicherte Entwurfswerte (nur mit gültigem Vorschau-Schlüssel, 15 Minuten, nichts wird gespeichert)
$liveCz=false;
if($preview!==''){
    $dr=(string)($_GET['rrw_wp_draft']??$_COOKIE['rrw_wp_draft']??'');
    if(preg_match('/^[a-f0-9]{32}$/',$dr)){
        require_once $cmsDir.'/wp/customizer-api.php';$liveCz=true;
        $dd=rrw_wpc_draft_load($dr,$preview);if($dd!==null)rrw_wpc_draft_apply($dd);
        if(isset($_GET['rrw_wp_draft']))setcookie('rrw_wp_draft',$dr,['expires'=>time()+900,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);
    }
}
if($sbx&&$preview===''&&!is_file(RRW_WP_DATA.'/front-on')){ header('Content-Type: text/html; charset=utf-8');echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sandbox</title><body style="font:16px system-ui;max-width:34rem;margin:15vh auto;padding:0 1rem"><h1>Sandbox</h1><p>In der Sandbox ist kein WordPress-Theme aktiv – so käme es auch live: das Portal-Design.</p><p>Im CMS unter <b>Design → Sandbox</b> kannst du ein Theme aktivieren.</p><p><a href="/?rrw_sbx=off">Sandbox verlassen</a></p>';exit; }
if(!is_file($flag)&&$preview===''&&!$sbx){
    http_response_code(404);
    try{require_once $root.'/cms/lib/tools.php';rrw_notfound_handle($root);}catch(Throwable $e){header('Content-Type: text/plain; charset=utf-8');echo 'Seite nicht gefunden';}
    exit;
}
try{require_once $cmsDir.'/lib/tools.php';if($preview===''&&!$sbx)rrw_maint_gate($root);}catch(Throwable $e){}
$GLOBALS['RRW_SITE']=json_decode((string)@file_get_contents($cmsDir.'/data/site.json'),true)?:[];
try{ require_once $cmsDir.'/lib/seo.php';require_once $cmsDir.'/lib/brand.php'; }catch(Throwable $e){}
require_once $cmsDir.'/wp/router.php';
require_once $cmsDir.'/wp/session.php';

$uri=(string)($_SERVER['REQUEST_URI']??'/');
$reqPath=(string)parse_url($uri,PHP_URL_PATH);
// Native Plugins (Essentials): laden, geplante Aufgaben, frühe Anfragen (z. B. Seiten-Cache, Weiterleitungen) – siehe cms/lib/nplugins.php
require_once $cmsDir.'/lib/nplugins.php';
if($preview===''&&!$sbx){ rrw_np_boot();rrw_np_tick();rrw_np_do('front_request',$uri,$reqPath,(string)($_SERVER['REQUEST_METHOD']??'GET')); }
// Wechsel vom CMS: Einmal-Token gegen Sitzungs-Cookie tauschen und auf die saubere Adresse weiterleiten
if(isset($_GET['rrw_wp_login'])){
    $ok=rrw_wp_sess_token_consume((string)$_GET['rrw_wp_login']);
    $clean=preg_replace('/([?&])rrw_wp_login=[^&]*&?/','$1',$uri);$clean=rtrim((string)$clean,'?&');
    if($ok)header('Set-Cookie: '.rrw_wp_sess_cookie_issue(),false);
    header('Cache-Control: no-store');header('Location: '.($ok?$clean:'/cms/'));http_response_code(302);exit;
}
$sess=rrw_wp_sess_check();$GLOBALS['rrw_wp_sess_user']=$sess!==null;
rrw_wp_boot(['theme'=>true,'user'=>$sess?:null,'admin'=>$sess!==null&&str_starts_with($reqPath,'/wp-admin/')]);
if($preview===''&&is_file(RRW_WP_DATA.'/customize-changesets/future.idx')){ try{ require_once $cmsDir.'/wp/customizer-api.php';rrw_wpc_cs_run_due(); }catch(Throwable $e){} }   // geplante Customizer-Änderungen
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){ $GLOBALS['rrw_wp_raw_body']=(string)file_get_contents('php://input',false,null,0,1048576);}
$GLOBALS['rrw_wp_req_headers']=['Content-Type'=>(string)($_SERVER['CONTENT_TYPE']??''),'Accept'=>(string)($_SERVER['HTTP_ACCEPT']??''),'X-WP-Nonce'=>(string)($_SERVER['HTTP_X_WP_NONCE']??''),'X-HTTP-Method-Override'=>(string)($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']??'')];
$r=rrw_wp_dispatch($uri,(string)($_SERVER['REQUEST_METHOD']??'GET'),$_GET,$_POST);
if($r['status']===404&&$preview===''){
    // Weiterleitungen aus dem CMS (Werkzeuge) haben Vorrang vor der 404-Seite des Themes; unbekannte Pfade werden protokolliert
    try{
        $dataDir=$cmsDir.'/data';$path=(string)parse_url($uri,PHP_URL_PATH);
        $rules=rrw_tools_read(rrw_tools_dir($dataDir).'/redirects.json',['rules'=>[]])['rules']??[];$hit=is_array($rules)?rrw_redirect_match($rules,$path):null;
        if($hit){ $code=(int)($hit['code']??301);if($code===410){ http_response_code(410);header('Content-Type: text/plain; charset=utf-8');echo 'Diese Seite wurde dauerhaft entfernt.';exit; } header('Location: '.str_replace(["\r","\n"],'',(string)$hit['to']),true,$code);exit; }
        rrw_404_log($dataDir,$path,(string)($_SERVER['HTTP_REFERER']??''));
    }catch(Throwable $e){}
}
if($preview===''&&$r['status']===200&&($_SERVER['REQUEST_METHOD']??'GET')==='GET'&&($loc=rrw_wp_canonical_redirect($uri))!==null){ header('Location: '.str_replace(["\r","\n"],'',$loc),true,301);exit; }   // alte Beitrags-Adresse → eingestellte Link-Form
http_response_code($r['status']);
foreach($r['headers'] as $k=>$v)header($k.': '.str_replace(["\r","\n"],'',(string)$v));
if($preview!=='')header('Cache-Control: no-store');elseif($r['status']===200&&($r['headers']['Content-Type']??'')!==''&&($_SERVER['REQUEST_METHOD']??'GET')==='GET')header('Cache-Control: no-cache, must-revalidate');
$body=$r['body'];
// App-Modus (Baukasten-App): Kopf und Fuß der Website in der App ausblenden (siehe lib/appmode.php)
if($preview===''&&$r['status']===200&&is_string($body)&&stripos((string)($r['headers']['Content-Type']??'text/html'),'html')!==false){
    try{
        require_once $cmsDir.'/lib/appmode.php';$am=rrw_appmode_detect();
        if($am!==null){
            header('Vary: User-Agent, Cookie',false);
            require_once $cmsDir.'/lib/apps.php';
            if(rrw_appmode_hide($am,rrw_apps_own($cmsDir.'/data'),(array)$GLOBALS['RRW_SITE']))$body=rrw_appmode_inject($body,true);
        }
    }catch(Throwable $e){}
}
if($liveCz&&$r['status']===200&&is_string($body)&&($p=strripos($body,'</body>'))!==false)$body=substr($body,0,$p).rrw_wpc_live_script().substr($body,$p);
if($sbx&&$r['status']===200&&is_string($body)&&stripos((string)($r['headers']['Content-Type']??'text/html'),'html')!==false&&($p=strripos($body,'</body>'))!==false)$body=substr($body,0,$p).'<div style="position:fixed;left:0;right:0;bottom:0;z-index:2147483647;background:#7c3aed;color:#fff;font:600 13px/1.3 system-ui,sans-serif;padding:7px 12px;display:flex;gap:12px;justify-content:center;align-items:center;flex-wrap:wrap">Sandbox – nicht öffentlich <a href="/?rrw_sbx=off" style="color:#fff;text-decoration:underline">Sandbox verlassen</a></div>'.substr($body,$p);
if($preview===''&&!$sbx&&is_string($body)&&($_SERVER['REQUEST_METHOD']??'GET')==='GET'&&stripos((string)($r['headers']['Content-Type']??'text/html'),'html')!==false)$body=rrw_np_filter('front_output',$body,(int)$r['status']);   // Plugins: SEO, Leistung, Statistik, Sicherheits-Header
rrw_np_do('front_response',(int)$r['status'],$reqPath,(string)($r['headers']['Content-Type']??'text/html'));
echo $body;

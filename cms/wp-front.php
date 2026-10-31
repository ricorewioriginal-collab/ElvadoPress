<?php
// Front-Controller der Website: echtes WordPress liefert sie aus (cms/wp-engine-front.php, eingerichtet unter System → WordPress-Engine).
// Davor: Demo-Betrieb, Sandbox (geheimer Link, eigene Datenbank) und weitere Websites (Multisite). Ist WordPress (noch) nicht eingerichtet, gibt es Feed,
// robots.txt, Weiterleitungen/404-Seite der Werkzeuge und sonst eine Hinweisseite. Aufruf über index.php (Startseite) und die Umschreibregel in .htaccess.
declare(strict_types=1);
$cmsDir=__DIR__;$root=dirname(__DIR__);
if(is_file($cmsDir.'/lib/demo.json')){ require_once $cmsDir.'/lib/demo.php';elvado_demo_boot();elvado_demo_guard_front((string)parse_url((string)($_SERVER['REQUEST_URI']??'/'),PHP_URL_PATH));ob_start('elvado_demo_inject'); }   // Demo-Betrieb
/* Sandbox: geheimer Link (?elvado_sbx=<Schlüssel>) → Cookie; eigene WordPress-Datenbank, nur lesend, nicht indexierbar (lib/wp-real-sandbox.php) */
$sbx=false;
$sbxTok=(string)($_GET['elvado_sbx']??$_COOKIE['elvado_sbx']??'');
$sbxReal=false;
if($sbxTok!==''){   // echtes WordPress liefert aus: Sandbox = Kopie der WordPress-Datenbank (lib/wp-real-sandbox.php)
    require_once $cmsDir.'/lib/wp-real-admin.php';
    if(elvado_wpra_front_on($cmsDir)){
        require_once $cmsDir.'/lib/wp-real-sandbox.php';$sbxReal=true;
        if($sbxTok==='off'){ foreach(['elvado_sbx','elvado_wp_preview','elvado_wp_draft'] as $c)setcookie($c,'',['expires'=>1,'path'=>'/']);header('Location: /');http_response_code(302);exit; }
        if(elvado_wprs_token_ok($cmsDir.'/data',$sbxTok)){
            $sbx=true;header('X-Robots-Tag: noindex, nofollow',true);header('Cache-Control: no-store');
            if(isset($_GET['elvado_sbx']))setcookie('elvado_sbx',$sbxTok,['expires'=>time()+14*86400,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);
            if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){ http_response_code(405);header('Content-Type: text/plain; charset=utf-8');echo 'Die Sandbox ist schreibgeschützt.';exit; }
            $GLOBALS['elvado_wpe_db_override']=elvado_wprs_override((new \Elvado\Wp\DbConfig(elvado_wpe()))->get()??[],$cmsDir.'/data');
        }elseif(isset($_COOKIE['elvado_sbx'])&&!isset($_GET['elvado_sbx'])){ setcookie('elvado_sbx','',['expires'=>1,'path'=>'/']);header('Location: '.str_replace(["\r","\n"],'',(string)($_SERVER['REQUEST_URI']??'/')));http_response_code(302);exit; }
        else{ http_response_code(404);header('Content-Type: text/plain; charset=utf-8');echo 'Seite nicht gefunden';exit; }
    }
}
if($sbxTok!==''&&!$sbxReal){   // ohne echtes WordPress gibt es keine Sandbox: altes Cookie ablegen
    if(isset($_COOKIE['elvado_sbx']))setcookie('elvado_sbx','',['expires'=>1,'path'=>'/']);
    if(isset($_GET['elvado_sbx'])){ http_response_code(404);header('Content-Type: text/plain; charset=utf-8');echo 'Seite nicht gefunden';exit; }
}
/* Weitere Website (Multisite, lib/sites.php): Domain → eigene Daten und eigene WordPress-Ablage (Themes/Optionen), Medien-Adressen siehe unten. Ohne Registry bleibt alles wie bisher. */
$siteId='';$siteData=$cmsDir.'/data';
if(!$sbx&&is_file($cmsDir.'/data/sites.json')){
    try{ require_once $cmsDir.'/lib/sites.php';$siteId=elvado_site_current();if($siteId!==''){ $siteData=elvado_site_dir('data',$siteId);if(!defined('ELVADO_WP_DATA'))define('ELVADO_WP_DATA',$siteData.'/.wp');if(!defined('ELVADO_MEDIA_DIR'))define('ELVADO_MEDIA_DIR',elvado_site_dir('media',$siteId)); } }catch(Throwable $e){ $siteId='';$siteData=$cmsDir.'/data'; }
}
require $cmsDir.'/wp-engine-front.php';   // echtes WordPress liefert die Website aus (kehrt nur zurück, wenn es nicht eingerichtet bzw. ausgeschaltet ist)

// WordPress liefert (noch) nicht aus: Feed und robots.txt aus den ElvadoPress-Daten, Weiterleitungen/404 der Werkzeuge, sonst Hinweisseite
$path=(string)parse_url((string)($_SERVER['REQUEST_URI']??'/'),PHP_URL_PATH);
try{ require_once $cmsDir.'/lib/tools.php';elvado_maint_gate($root); }catch(Throwable $e){}
$site=json_decode((string)@file_get_contents($siteData.'/site.json'),true)?:[];
if($path==='/rss.xml'){
    header('Content-Type: application/rss+xml; charset=utf-8');
    try{ require_once $cmsDir.'/lib/publish.php';require_once $cmsDir.'/lib/feeds.php';
        if(isset($site['rss']['enabled'])&&empty($site['rss']['enabled'])){ http_response_code(404);header('Content-Type: text/plain; charset=utf-8');echo 'Der RSS-Feed ist ausgeschaltet.';exit; }
        echo elvado_rss_xml($site);
    }catch(Throwable $e){ http_response_code(503);header('Content-Type: text/plain; charset=utf-8');echo 'Der Feed ist gerade nicht verfügbar.'; }
    exit;
}
if(preg_match('#^/(feed|rss)(/|\.xml|\.php)?$#',$path)){ header('Location: /rss.xml',true,302);exit; }
if($path==='/robots.txt'){
    $pub=!str_starts_with(strtolower(trim((string)($site['seo']['robots']??'index,follow'))),'noindex');
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\n".($pub?"Disallow: /cms/data/\nDisallow: /cms/backups/\nDisallow: /cms/plugins/\nAllow: /\n":"Disallow: /\n");
    exit;
}
if($path!=='/'&&$path!==''){
    http_response_code(404);
    try{ elvado_notfound_handle($root); }catch(Throwable $e){ header('Content-Type: text/plain; charset=utf-8');echo 'Seite nicht gefunden'; }
    exit;
}
http_response_code(503);header('Retry-After: 300');header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');
$name=htmlspecialchars((string)($site['portal']['site_name']??'Website'),ENT_QUOTES,'UTF-8');
echo '<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>'.$name.'</title>'
    .'<body style="font:16px/1.5 system-ui,sans-serif;max-width:36rem;margin:15vh auto;padding:0 1rem"><h1>'.$name.'</h1><p>Die Website wird gerade eingerichtet.</p>'
    .'<p style="color:#666">Betreiber: Melde dich in der <a href="/cms/">Verwaltung</a> an – WordPress wird dabei automatisch eingerichtet (System → WordPress-Engine).</p></body></html>';

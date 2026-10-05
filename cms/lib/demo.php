<?php
declare(strict_types=1);

// Demo-Betrieb (öffentliche Testinstanz). Aktiv nur, wenn die Datei cms/lib/demo.json existiert (legt der Build mit --demo an);
// ohne die Datei ändert sich nichts. Die Daten werden alle N Minuten (Standard 10) auf den Ausgangszustand zurückgesetzt:
// träge bei der ersten Anfrage nach Ablauf (kein Cron nötig), unter Sperre, mit derselben Einrichtung wie der Assistent.
// Gefährliche Aktionen (Code-/Dateiuploads, Plugin- und Theme-Installation, Benutzer, System, Mailversand, Zugangsdaten) sind gesperrt.
//
// demo.json: user, password, minutes (1–1440), site_name, display_name

function rrw_demo_config_file(): string { return __DIR__.'/demo.json'; }
/** Demo-Einstellungen oder null (kein Demo-Betrieb). */
function rrw_demo_config(): ?array {
    static $c=false;if($c!==false)return $c;
    $f=rrw_demo_config_file();$j=is_file($f)?json_decode((string)@file_get_contents($f),true):null;
    if(!is_array($j))return $c=null;
    $user=(string)($j['user']??'demo');if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,39}$/',$user))$user='demo';
    $pw=(string)($j['password']??'');if(strlen($pw)<8)$pw='ElvadoPress-Demo1';
    return $c=['user'=>$user,'password'=>$pw,'minutes'=>max(1,min(1440,(int)($j['minutes']??10))),
        'site_name'=>mb_substr(trim((string)($j['site_name']??'ElvadoPress Demo')),0,80)?:'ElvadoPress Demo','display_name'=>mb_substr(trim((string)($j['display_name']??'Demo-Benutzer')),0,80)?:'Demo-Benutzer'];
}
function rrw_demo_enabled(): bool { return rrw_demo_config()!==null; }
function rrw_demo_state_dir(): string { return dirname(__DIR__).'/demo-state'; }
function rrw_demo_state(): array {
    $f=rrw_demo_state_dir().'/state.json';$j=is_file($f)?json_decode((string)@file_get_contents($f),true):null;
    return is_array($j)?$j:[];
}
function rrw_demo_state_write(array $s): void {
    $f=rrw_demo_state_dir().'/state.json';$t=$f.'.'.bin2hex(random_bytes(4)).'.tmp';
    if(@file_put_contents($t,json_encode($s))!==false)@rename($t,$f);
}
/** Ablauf des aktuellen Zeitfensters (Unix-Zeit) oder 0. */
function rrw_demo_reset_at(): int {
    $c=rrw_demo_config();$s=rrw_demo_state();
    return ($c&&!empty($s['started']))?(int)$s['started']+$c['minutes']*60:0;
}
/** Öffentliche Angaben für Banner, Anmeldung und Startseite der Demo (enthält bewusst die Demo-Zugangsdaten). */
function rrw_demo_public(): array {
    $c=rrw_demo_config();if(!$c)return [];
    return ['user'=>$c['user'],'password'=>$c['password'],'minutes'=>$c['minutes'],'reset_at'=>rrw_demo_reset_at(),'now'=>time(),'site_name'=>$c['site_name']];
}

/** Inhalt eines Ordners entfernen (der Ordner selbst und .htaccess/.gitkeep bleiben); Links werden nie verfolgt. */
function rrw_demo_clear_dir(string $dir): void {
    if(!is_dir($dir)||is_link($dir))return;
    foreach(scandir($dir)?:[] as $f){
        if($f==='.'||$f==='..'||$f==='.htaccess'||$f==='.gitkeep')continue;
        $p=$dir.'/'.$f;
        if(is_dir($p)&&!is_link($p)){ rrw_demo_clear_dir($p);@rmdir($p); } else @unlink($p);
    }
}
/** Ausgangszustand herstellen: Daten, Veröffentlichtes und Uploads leeren, dann neu einrichten und Beispielinhalte anlegen. */
function rrw_demo_reset(): bool {
    $c=rrw_demo_config();if(!$c)return false;
    $cms=dirname(__DIR__);$data=rrw_data_dir();
    // Sicherheitsnetz: nur der CMS-Datenordner darf geleert werden
    if(basename($data)!=='data'||realpath($data)===false||realpath($data)!==realpath($cms.'/data'))return false;
    foreach([$data,$cms.'/generated',$cms.'/media',$cms.'/wp-content/uploads'] as $d)rrw_demo_clear_dir($d);
    rrw_system_config(true);
    foreach(['feeds','backup','database','seo','content','auth','mail','assistant','directory','apps','geo','alexa','tools','forms','polls','community','forum','social'] as $lib)require_once $cms.'/lib/'.$lib.'.php';
    require_once $cms.'/lib/publish.php';require_once $cms.'/lib/install.php';
    $ctx=['siteFile'=>$data.'/site.json','newsFile'=>$data.'/news.json','genDir'=>$cms.'/generated','root'=>dirname($cms),'activityLog'=>$data.'/activity-log.json'];
    $r=rrw_install_run(['site_name'=>$c['site_name'],'language'=>'de','timezone'=>'Europe/Berlin','username'=>$c['user'],'display_name'=>$c['display_name'],'email'=>'','password'=>$c['password'],
        'db'=>['driver'=>'none'],'db_create'=>false,'sample'=>true],$ctx);
    if(empty($r['ok']))return false;
    rrw_demo_seed($ctx['newsFile'],$c);
    rrw_system_config(true);
    return true;
}
/** Beispielbeiträge, damit Website und Verwaltung gefüllt wirken. */
function rrw_demo_seed(string $newsFile,array $c): void {
    $posts=[
        ['Neu: Die Demo-Verwaltung','News','Probiere die Verwaltung aus – alles wird alle paar Minuten zurückgesetzt.','<p>Du siehst hier eine <strong>Testinstanz</strong> von ElvadoPress. Lege Beiträge und Seiten an, ändere das Design oder stöbere durch die Einstellungen. Plugin- und Theme-Uploads, Benutzer- und Systemänderungen sind in der Demo gesperrt.</p>'],
        ['WordPress-kompatibel: Themes und Plugins','Technik','ElvadoPress führt klassische WordPress-Themes und -Plugins aus.','<p>Die WordPress-Schicht bringt Hooks, Shortcodes, <code>WP_Query</code>, Block-Themes und viele Plugins mit. In der Demo sind einige Themes und Plugins vorinstalliert.</p>'],
        ['Für Radios gemacht: App-Baukasten und Alexa-Skill','Radio','Eigene Radio-Apps, Alexa-Skill, Radioverzeichnis und KI-Assistent.','<p>Neben dem allgemeinen CMS enthält ElvadoPress optionale Radio-Erweiterungen: Android- und Windows-Apps bauen, einen Alexa-Skill erzeugen und ein Radioverzeichnis einbinden.</p>'],
    ];
    $cur=is_file($newsFile)?json_decode((string)file_get_contents($newsFile),true):[];if(!is_array($cur))$cur=[];
    $id=0;foreach($cur as $a)$id=max($id,(int)($a['id']??0));
    $t=time();
    foreach($posts as $i=>[$title,$cat,$ex,$html]){
        $d=date('Y-m-d H:i:s',$t-($i+1)*3600);$id++;
        $cur[]=['id'=>$id,'slug'=>rrw_product_slugify($title),'title'=>$title,'category'=>$cat,'excerpt'=>$ex,'body_html'=>$html,'status'=>'published','featured'=>$i===0?1:0,
            'author'=>$c['display_name'],'author_user'=>$c['user'],'tags'=>[],'image_url'=>'','image_mode'=>'thumbnail','published_at'=>$d,'created_at'=>$d,'updated_at'=>$d];
    }
    rrw_write_atomic($newsFile,json_encode($cur,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
}

/** Beim Start jeder Anfrage: Konstanten setzen und bei Ablauf des Zeitfensters (oder fehlender Einrichtung) zurücksetzen. */
function rrw_demo_boot(): void {
    static $done=false;if($done)return;$done=true;
    $c=rrw_demo_config();if(!$c)return;
    if(!defined('RRW_DEMO'))define('RRW_DEMO',true);
    foreach(['DISALLOW_FILE_MODS','DISALLOW_FILE_EDIT'] as $k)if(!defined($k))define($k,true);
    require_once __DIR__.'/system.php';require_once __DIR__.'/product.php';
    $dir=rrw_demo_state_dir();if(!is_dir($dir))@mkdir($dir,0775,true);
    $fresh=function()use($c){ $at=rrw_demo_reset_at();return $at>time()&&is_file(rrw_install_lock_file()); };
    if($fresh())return;
    $h=@fopen($dir.'/reset.lock','c');if(!$h)return;
    @flock($h,LOCK_EX);
    clearstatcache();
    if(!$fresh()){
        $s=rrw_demo_state();
        if(rrw_demo_reset())rrw_demo_state_write(['started'=>time(),'resets'=>(int)($s['resets']??0)+1]);
        else rrw_demo_state_write(['started'=>time()-$c['minutes']*60+30,'resets'=>(int)($s['resets']??0),'error'=>1]);   // später erneut versuchen (nach 30 s)
    }
    @flock($h,LOCK_UN);fclose($h);
}

const RRW_DEMO_MSG='In der Demo nicht möglich – diese Aktion ist gesperrt.';
/** Aktionen der Verwaltungs-API, die in der Demo gesperrt sind. */
const RRW_DEMO_BLOCKED=[
    // Dateien und Code
    'media_upload','media_library_upload','branding_upload','news_thumbnail_upload','plugin_upload','plugin_delete','theme_upload','theme_install','theme_delete',
    'wp_theme_upload','wp_theme_install','wp_theme_delete','wp_plugin_upload','wp_plugin_install','wp_plugin_delete','wp_translations','content_import','import_legacy','news_import',
    // Benutzer, Zugänge, System
    'user_add','user_update','user_delete','local_auth_setup','profile_update_self','system_save','database_config_save','database_test','database_push','database_pull',
    'backup_create','backup_restore','backup_download','redirects_save',
    // Updates und Kern
    'wp_core_install','wp_update_apply','wp_autoupdate_set','wp_autoupdate_run','wp_autoupdate_rollback','wp_autoupdate_tick','wp_version_update','wp_version_rollback',
    'wp_sandbox_create','wp_sandbox_delete','wp_sandbox_publish','wp_sandbox_reset','wp_sandbox_rollback','wp_sandbox_rotate',
    // Zugangsdaten, Dienste, externe Anfragen, Mailversand
    'app_build_save','app_build_start','app_build_check','app_build_download','app_build_brand_save','app_build_brand_delete','alexa_token_reset',
    'assistant_chat','assistant_send','assistant_voice','assistant_test','feed_test','directory_admin_save','apps_geo_update',
    'member_register','member_reset_request','member_reset',
];
/** WordPress-Verwaltungsseiten, die in der Demo gesperrt sind. */
const RRW_DEMO_WP_PAGES='#^(plugin-install|plugin-editor|theme-install|theme-editor|update|update-core|user-new|user-edit|users|import|export|options|erase-personal-data|export-personal-data|site-health|network/.*)\.php$#';
/** Ajax-Aktionen der WordPress-Verwaltung, die in der Demo gesperrt sind. */
const RRW_DEMO_WP_AJAX='#^(upload-attachment|save-attachment|save-attachment-compat|install-plugin|install-theme|update-plugin|update-theme|delete-plugin|delete-theme|edit-theme-plugin-file|upload-plugin|upload-theme|wp-remove-post-lock|send-password-reset|import-.*|crop-image|image-editor|set-post-thumbnail|upload-.*)$#';

function rrw_demo_deny(string $why=''): never {
    if(!headers_sent())header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode(['status'=>'error','message'=>RRW_DEMO_MSG,'demo'=>true],JSON_UNESCAPED_UNICODE);exit;
}
/** Prüfung vor der Verarbeitung einer API-Anfrage (cms/api.php). */
function rrw_demo_guard(string $action,array $body): void {
    if(!rrw_demo_enabled())return;
    if($action==='demo_status'){ header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode(['status'=>'ok']+rrw_demo_public());exit; }
    if(in_array($action,RRW_DEMO_BLOCKED,true))rrw_demo_deny($action);
    if($action==='wp_admin_page'){
        $page=(string)($body['page']??'');$p=(string)parse_url($page,PHP_URL_PATH);
        if(preg_match(RRW_DEMO_WP_PAGES,ltrim($p,'/')))rrw_demo_deny($action);
        if(preg_match('#^(plugins|themes)\.php$#',ltrim($p,'/'))&&preg_match('/delete|install|upload/',(string)parse_url($page,PHP_URL_QUERY).'&'.(string)($body['body']??'').'&'.(string)($body['query']??'')))rrw_demo_deny($action);
    }
    if($action==='wp_admin_ajax'){
        $q=(string)parse_url((string)($body['url']??''),PHP_URL_QUERY);parse_str($q.'&'.(string)($body['body']??''),$p);
        if(preg_match(RRW_DEMO_WP_AJAX,(string)($p['action']??'')))rrw_demo_deny($action);
    }
    if($action==='wp_admin_rest'){
        $m=strtoupper((string)($body['method']??'GET'));$path=(string)preg_replace('#^.*?/wp-json#','',(string)($body['path']??''));
        if($m!=='GET'&&preg_match('#^/(wp/v2/(media|plugins|themes|users)|wc/|wc-admin)#',$path))rrw_demo_deny($action);
    }
}
/** Prüfung direkter Aufrufe der Website (cms/wp-front.php): gesperrte WordPress-Verwaltungsseiten. */
function rrw_demo_guard_front(string $path): void {
    if(!rrw_demo_enabled())return;
    $p=ltrim($path,'/');
    if(str_starts_with($p,'xmlrpc.php')||(str_starts_with($p,'wp-admin/')&&preg_match(RRW_DEMO_WP_PAGES,substr($p,9)))||str_starts_with($p,'wp-admin/async-upload.php')){
        http_response_code(403);header('Content-Type: text/plain; charset=utf-8');echo RRW_DEMO_MSG;exit;
    }
}
/** Banner-Skript vor </body> einfügen (nur HTML-Antworten der Website). */
function rrw_demo_inject(string $html): string {
    if(!rrw_demo_enabled()||stripos($html,'</body>')===false)return $html;
    foreach(headers_list() as $h)if(stripos($h,'content-type:')===0&&stripos($h,'text/html')===false)return $html;
    $j=json_encode(rrw_demo_public(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
    $tag='<script>window.RRW_DEMO='.$j.';</script><script src="/cms/assets/demo.js?v=1" defer></script>';
    $pos=strripos($html,'</body>');
    return substr($html,0,$pos).$tag.substr($html,$pos);
}

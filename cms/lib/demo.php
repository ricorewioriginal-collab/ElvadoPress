<?php
declare(strict_types=1);

// Demo-Betrieb (öffentliche Testinstanz). Aktiv nur, wenn die Datei cms/lib/demo.json existiert (legt der Build mit --demo an);
// ohne die Datei ändert sich nichts. Die Daten werden alle N Minuten (Standard 10) auf den Ausgangszustand zurückgesetzt:
// träge bei der ersten Anfrage nach Ablauf (kein Cron nötig), unter Sperre, mit derselben Einrichtung wie der Assistent.
// Die Demo-Website ist die ElvadoPress-Produkt-Homepage (lib/demo-content.php) und alle Funktionen sind freigeschaltet – gesperrt ist nur, was den gemeinsam genutzten Server
// gefährden würde: Installation fremden Programmcodes (Plugin-/Theme-Upload), externe Datenbank-Verbindungen, Mailversand, Updates und Dritt-Dienste mit Zugangsdaten.
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
        'site_name'=>mb_substr(trim((string)($j['site_name']??'ElvadoPress')),0,80)?:'ElvadoPress','display_name'=>mb_substr(trim((string)($j['display_name']??'Demo-Benutzer')),0,80)?:'Demo-Benutzer'];
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
    foreach([$data,$cms.'/generated',$cms.'/media',$cms.'/wp-content/uploads',$cms.'/backups',$cms.'/frontend'] as $d)rrw_demo_clear_dir($d);
    rrw_system_config(true);
    foreach(['feeds','backup','database','seo','content','auth','mail','assistant','directory','apps','geo','alexa','tools','forms','polls','community','forum','social'] as $lib)require_once $cms.'/lib/'.$lib.'.php';
    require_once $cms.'/lib/publish.php';require_once $cms.'/lib/install.php';
    $ctx=['siteFile'=>$data.'/site.json','newsFile'=>$data.'/news.json','genDir'=>$cms.'/generated','root'=>dirname($cms),'activityLog'=>$data.'/activity-log.json'];
    $r=rrw_install_run(['site_name'=>$c['site_name'],'language'=>'de','timezone'=>'Europe/Berlin','username'=>$c['user'],'display_name'=>$c['display_name'],'email'=>'','password'=>$c['password'],
        'db'=>['driver'=>'none'],'db_create'=>false,'sample'=>true],$ctx);
    if(empty($r['ok']))return false;
    rrw_demo_seed($ctx['newsFile'],$c);
    rrw_demo_seed_site($ctx,$c);
    rrw_system_config(true);
    return true;
}
/** Beispielbeiträge, damit Website und Verwaltung gefüllt wirken (Texte: lib/demo-content.php). */
function rrw_demo_seed(string $newsFile,array $c): void {
    require_once __DIR__.'/demo-content.php';
    $posts=rrw_demo_posts();
    $cur=is_file($newsFile)?json_decode((string)file_get_contents($newsFile),true):[];if(!is_array($cur))$cur=[];
    $id=0;foreach($cur as $a)$id=max($id,(int)($a['id']??0));
    $t=time();
    foreach($posts as $i=>[$title,$cat,$ex,$html,$tags]){
        $d=date('Y-m-d H:i:s',$t-($i+1)*3600);$id++;
        $cur[]=['id'=>$id,'slug'=>rrw_product_slugify($title),'title'=>$title,'category'=>$cat,'excerpt'=>$ex,'body_html'=>$html,'status'=>'published','featured'=>$i===0?1:0,
            'author'=>$c['display_name'],'author_user'=>$c['user'],'tags'=>$tags,'image_url'=>'','image_mode'=>'thumbnail','published_at'=>$d,'created_at'=>$d,'updated_at'=>$d];
    }
    foreach($cur as &$a)if((int)($a['id']??0)===1){ $o=date('Y-m-d H:i:s',$t-86400);$a['published_at']=$a['created_at']=$a['updated_at']=$o; }   // Beispielbeitrag der Einrichtung hinter die Demo-Beiträge
    unset($a);
    rrw_write_atomic($newsFile,json_encode($cur,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
}

/**
 * Die Demo-Website als ElvadoPress-Homepage anlegen: Theme „Baukasten“ aktivieren, Startseiten-Layout, Farben, Seiten und Menü.
 * Fehler hier lassen die Demo mit dem Standard-Theme weiterlaufen (die Einrichtung selbst ist dann schon abgeschlossen).
 */
function rrw_demo_seed_site(array $ctx,array $c): void {
    try{
        require_once __DIR__.'/demo-content.php';
        $cms=dirname(__DIR__);
        // Seiten und Menü in site.json (über dieselbe Bereinigung wie beim Speichern in der Verwaltung)
        require_once $cms.'/lib/publish.php';
        $site=rrw_read_json($ctx['siteFile'],[]);
        $pages=rrw_clean_section('pages',rrw_demo_pages($c));if($pages!==null)$site['pages']=$pages;
        $menus=rrw_clean_section('menus',rrw_demo_menu());if($menus!==null)$site['menus']=$menus;
        // Logo: Kopf der Website (Baukasten-Theme liest branding.portal_logo) und Symbol
        $site['branding']=is_array($site['branding']??null)?$site['branding']:[];
        $site['branding']['portal_logo']='/cms/assets/brand/elvadopress-logo.png';$site['branding']['portal_icon']='/cms/assets/brand/icon-192.png';
        $site['portal']=is_array($site['portal']??null)?$site['portal']:[];
        $site['portal']['tagline']='Das erweiterbare CMS für Websites aller Art';
        $site['portal']['footer_text']='ElvadoPress – freie Software (GPL-2.0-or-later) · Live-Demo, wird alle '.(int)$c['minutes'].' Minuten zurückgesetzt';
        rrw_write_atomic($ctx['siteFile'],json_encode($site,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
        // Theme und Layout über die WordPress-Schicht
        require_once $cms.'/wp/load.php';require_once $cms.'/wp/installer.php';
        if(!function_exists('update_option'))rrw_wp_boot(['theme'=>false]);
        if(rrw_wpi_activate_theme('elvado-baukasten')!==null)return;
        require_once $cms.'/themes/elvado-baukasten/inc/layout.php';
        elvado_bk_save_layout(rrw_demo_layout($c));
        foreach(['color_accent'=>'#2f7bff','content_width'=>1180,'home_sidebar'=>true,'sidebar_pos'=>'right','sidebar_width'=>300] as $k=>$v)set_theme_mod($k,$v);
        // Seitenleiste mit Demo-Widgets (Logo, Zugang, Suche, Funktionen, Neueste Beiträge, Kategorien, Schlagwörter, Seiten, Themes, Archiv, GitHub)
        $w=rrw_demo_widgets($c);
        foreach($w['options'] as $opt=>$val)update_option($opt,$val);
        update_option('sidebars_widgets',['sidebar-1'=>$w['sidebars'],'wp_inactive_widgets'=>[],'array_version'=>3]);
    }catch(Throwable $e){}
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
        $wpBooted=defined('RRW_WP_LOADED');
    }else $wpBooted=false;
    @flock($h,LOCK_UN);fclose($h);
    // Das Zurücksetzen hat die WordPress-Schicht in diesem Prozess ohne das neue Theme gestartet – die Anfrage läuft deshalb in einem frischen Prozess neu an
    if($wpBooted&&PHP_SAPI!=='cli')rrw_demo_restart_request();
}
/** Aktuelle Anfrage nach dem Zurücksetzen neu starten: GET per Weiterleitung auf dieselbe Adresse, sonst Hinweis mit Wiederholung. */
function rrw_demo_restart_request(): never {
    if(headers_sent()){ exit; }
    $uri=(string)($_SERVER['REQUEST_URI']??'/');
    if(!preg_match('~^/[^\r\n]*$~',$uri)||str_starts_with($uri,'//'))$uri='/';
    if(in_array(strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET')),['GET','HEAD'],true)){
        header('Cache-Control: no-store');header('Location: '.$uri,true,307);exit;
    }
    http_response_code(503);header('Retry-After: 2');header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status'=>'error','message'=>'Die Demo wurde gerade zurückgesetzt – bitte noch einmal versuchen.','demo'=>true],JSON_UNESCAPED_UNICODE);exit;
}

const RRW_DEMO_MSG='In der Demo nicht möglich – diese Aktion ist gesperrt.';
/** Aktionen der Verwaltungs-API, die in der Demo gesperrt sind. */
const RRW_DEMO_BLOCKED=[
    // Fremder Programmcode auf dem gemeinsamen Server: nie (Plugin-/Theme-Upload und -Installation, Verzeichnis-Importe, Updates der WordPress-Schicht)
    'plugin_upload','plugin_delete','theme_upload','theme_install','theme_delete','wp_theme_upload','wp_theme_install','wp_theme_delete','wp_plugin_upload','wp_plugin_install','wp_plugin_delete','wp_translations',
    'content_import','import_legacy','news_import','wp_core_install','wp_update_apply','wp_autoupdate_set','wp_autoupdate_run','wp_autoupdate_rollback','wp_autoupdate_tick','wp_version_update','wp_version_rollback',
    'wp_sandbox_create','wp_sandbox_delete','wp_sandbox_publish','wp_sandbox_reset','wp_sandbox_rollback','wp_sandbox_rotate',
    // CMS-Update: würde den Code der Demo verändern
    'update_apply','update_rollback','update_confirm','update_config_save','update_secret_rotate',
    // Externe Datenbank-Verbindungen (Verbindungsversuche zu beliebigen Servern)
    'database_config_save','database_test','database_push','database_pull',
    // Mailversand und Dritt-Dienste mit Zugangsdaten, Weiterleitungen auf beliebige Adressen, fremde Dateien von GitHub
    'app_build_save','app_build_start','app_build_check','app_build_download','app_build_brand_save','app_build_brand_delete',
    'assistant_chat','assistant_send','assistant_voice','assistant_test','assistant_models','feed_test','services_status','directory_admin_save','apps_geo_update',
    'member_register','member_reset_request','member_reset','redirects_save','lovable_sync','local_auth_setup',
    // KI-Zentrale und KI-Funktionen: Schlüssel, Verbindungstests (auch zu lokalen Adressen) und Anfragen an fremde Dienste gibt es in der Demo nicht
    'ai_config_save','ai_test','ai_models','ai_migrate_legacy','ai_generate','ai_site_plan','ai_dev_plan','ai_dev_install','ai_dev_check',
];
/** WordPress-Verwaltungsseiten, die in der Demo gesperrt sind. */
const RRW_DEMO_WP_PAGES='#^(plugin-install|plugin-editor|theme-install|theme-editor|update|update-core|import|erase-personal-data|export-personal-data|site-health|network/.*)\.php$#';
/** Ajax-Aktionen der WordPress-Verwaltung, die in der Demo gesperrt sind. */
const RRW_DEMO_WP_AJAX='#^(install-plugin|install-theme|update-plugin|update-theme|delete-plugin|delete-theme|edit-theme-plugin-file|upload-plugin|upload-theme|send-password-reset|import-.*)$#';

function rrw_demo_deny(string $why=''): never {
    if(!headers_sent())header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    // Plugins/Themes sind fremder PHP-Code: auf der gemeinsamen Demo-Instanz nie, in einer eigenen Installation mit einem Klick
    $msg=preg_match('/(plugin|theme)_(install|upload|delete)$/',$why)?'In der Demo gesperrt: Plugins und Themes aus dem WordPress-Verzeichnis oder per ZIP lassen sich nur in einer eigenen ElvadoPress-Installation installieren (die Demo teilt sich den Server mit anderen Besuchern).':RRW_DEMO_MSG;
    echo json_encode(['status'=>'error','message'=>$msg,'demo'=>true],JSON_UNESCAPED_UNICODE);exit;
}
/** Prüfung vor der Verarbeitung einer API-Anfrage (cms/api.php). */
function rrw_demo_guard(string $action,array $body): void {
    if(!rrw_demo_enabled())return;
    if($action==='demo_status'){ header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode(['status'=>'ok']+rrw_demo_public());exit; }
    if(in_array($action,RRW_DEMO_BLOCKED,true))rrw_demo_deny($action);
    // Betriebsmodus darf nicht umgeschaltet werden (sonst wäre die Anmeldung der Demo weg); alles andere (Name, Sprache, Zeitzone) ist frei
    if($action==='system_save'&&array_key_exists('control_center',$body)&&function_exists('rrw_standalone')&&(bool)$body['control_center']===rrw_standalone())rrw_demo_deny($action);
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
        if($m!=='GET'&&preg_match('#^/(wp/v2/(plugins|themes)|wc/|wc-admin)#',$path))rrw_demo_deny($action);
    }
}
/** Prüfung direkter Aufrufe der Website (cms/wp-front.php): gesperrte WordPress-Verwaltungsseiten. */
function rrw_demo_guard_front(string $path): void {
    if(!rrw_demo_enabled())return;
    $p=ltrim($path,'/');
    if(str_starts_with($p,'xmlrpc.php')||(str_starts_with($p,'wp-admin/')&&preg_match(RRW_DEMO_WP_PAGES,substr($p,9)))){
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

/**
 * Ein fertiges Paket (Ordner mit cms/) zur Demo machen: Demo-Einstellungen, Startseite /demo/, Zustandsordner und Schutzdateien.
 * Wird von scripts/make-demo.php (ElvadoPress) und scripts/build-standalone.php --demo (Entwicklungsprojekt) genutzt.
 */
function rrw_demo_make(string $dir, int $minutes=10): void {
    $dir=rtrim($dir,'/');$minutes=max(1,min(1440,$minutes));
    $w=function(string $rel,string $content,bool $append=false)use($dir){ $f=$dir.'/'.$rel;if(!is_dir(dirname($f)))mkdir(dirname($f),0775,true);file_put_contents($f,$content,$append?FILE_APPEND:0); };
    $w('cms/lib/demo.json',json_encode(['user'=>'demo','password'=>'ElvadoPress-Demo1','minutes'=>$minutes,'site_name'=>'ElvadoPress','display_name'=>'Demo-Benutzer'],JSON_PRETTY_PRINT)."\n");
    $landing=$dir.'/cms/assets/demo-landing.html';
    if(!is_file($landing))throw new RuntimeException('cms/assets/demo-landing.html fehlt');
    $w('demo/index.html',(string)file_get_contents($landing));
    $w('demo/.htaccess',"DirectoryIndex index.html\n");
    $w('cms/demo-state/.htaccess',"Require all denied\n");
    // Die Demo-Konfiguration gehört nicht ins Netz (PHP-Dateien in cms/lib sind ohnehin gesperrt)
    $w('cms/lib/.htaccess',"<Files \"demo.json\">\nRequire all denied\n</Files>\n",true);
}

// ---------- Vorinstallierte WordPress-Themes und -Plugins (Kompatibilität ausprobieren) ----------
/** Bekannte Themes und Plugins aus dem WordPress.org-Verzeichnis, die die Demo mitliefert (Kennung => Anzeigename). Aktiviert wird nichts: Besucher probieren sie selbst aus. */
const RRW_DEMO_EXTRAS=[
    'themes'=>['twentytwentyfour'=>'Twenty Twenty-Four','astra'=>'Astra','generatepress'=>'GeneratePress'],
    'plugins'=>['contact-form-7'=>'Contact Form 7','wordpress-seo'=>'Yoast SEO','classic-editor'=>'Classic Editor'],
];
/** ZIP von wordpress.org laden (null bei Fehler). */
function rrw_demo_extras_fetch(string $url): ?string {
    for($try=0;$try<3;$try++){
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>3,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>180,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_USERAGENT=>'ElvadoPress-Demo/1.0']);
        $raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        if($raw!==false&&$code===200&&is_string($raw)&&strlen($raw)>1000&&strlen($raw)<120*1048576)return $raw;
        sleep(2);
    }
    return null;
}
function rrw_demo_extras_rmdir(string $d): void {
    if(!is_dir($d)||is_link($d))return;
    foreach(scandir($d)?:[] as $f){ if($f==='.'||$f==='..')continue;$p=$d.'/'.$f;is_dir($p)&&!is_link($p)?rrw_demo_extras_rmdir($p):@unlink($p); }
    @rmdir($d);
}
/** ZIP sicher nach $targetDir/<slug>/ entpacken: nur Einträge unter "<slug>/", keine Umwege (..), keine absoluten Pfade, keine Symlinks. Rückgabe Fehlertext oder null. */
function rrw_demo_extras_unzip(string $zipBytes,string $slug,string $targetDir): ?string {
    if(!class_exists('ZipArchive'))return 'PHP-Erweiterung zip fehlt';
    $tmp=tempnam(sys_get_temp_dir(),'dx');file_put_contents($tmp,$zipBytes);
    $z=new ZipArchive();if($z->open($tmp)!==true){@unlink($tmp);return 'ZIP nicht lesbar'; }
    $dest=$targetDir.'/'.$slug;$stage=$targetDir.'/.stage-'.$slug.'-'.bin2hex(random_bytes(3));$total=0;$err=null;
    for($i=0;$i<$z->numFiles&&$err===null;$i++){
        $st=$z->statIndex($i);$name=str_replace('\\','/',(string)$st['name']);
        if($name===''||str_starts_with($name,'/')||str_contains($name,'../')||str_contains($name,"\0")||preg_match('~^[A-Za-z]:~',$name)){ $err='unsicherer Pfad im ZIP: '.$name;break; }
        if(!str_starts_with($name,$slug.'/'))continue;
        $z->getExternalAttributesIndex($i,$opsys,$attr);if($opsys===ZipArchive::OPSYS_UNIX&&((($attr>>16)&0170000)===0120000))continue;   // Symlink
        $rel=substr($name,strlen($slug)+1);$path=$stage.'/'.$rel;
        if(str_ends_with($name,'/')){ if(!is_dir($path)&&!@mkdir($path,0775,true))$err='Ordner nicht anlegbar';continue; }
        $total+=(int)$st['size'];if($total>300*1048576){ $err='ZIP zu groß';break; }
        if(!is_dir(dirname($path))&&!@mkdir(dirname($path),0775,true)){ $err='Ordner nicht anlegbar';break; }
        $data=$z->getFromIndex($i);if($data===false||file_put_contents($path,$data)===false){ $err='Datei nicht schreibbar: '.$rel;break; }
    }
    $z->close();@unlink($tmp);
    if($err===null&&!is_dir($stage))$err='ZIP enthält kein Verzeichnis "'.$slug.'"';
    if($err!==null){ rrw_demo_extras_rmdir($stage);return $err; }
    rrw_demo_extras_rmdir($dest);
    if(!@rename($stage,$dest)){ rrw_demo_extras_rmdir($stage);return 'Zielordner nicht anlegbar'; }
    return null;
}
/**
 * Themes und Plugins aus RRW_DEMO_EXTRAS ins Demo-Paket legen (cms/wp-content/themes|plugins) und die Startseite der Demo ergänzen.
 * $fetch ersetzt den Download (Tests): fn(string $url): ?string. Rückgabe: ['ok'=>[…Namen], 'failed'=>[Name=>Grund]]. Einzelne Fehler stoppen nichts.
 */
function rrw_demo_extras(string $dir,?callable $fetch=null,?array $extras=null): array {
    $dir=rtrim($dir,'/');$extras=$extras??RRW_DEMO_EXTRAS;$fetch=$fetch??'rrw_demo_extras_fetch';$ok=['themes'=>[],'plugins'=>[]];$failed=[];
    foreach(['themes'=>'theme','plugins'=>'plugin'] as $group=>$kind){
        $target=$dir.'/cms/wp-content/'.$group;if(!is_dir($target)&&!@mkdir($target,0775,true)){ $failed[$group]='Ordner fehlt';continue; }
        foreach((array)($extras[$group]??[]) as $slug=>$name){
            $slug=(string)$slug;
            if(!preg_match('/^[a-z0-9][a-z0-9-]{1,60}$/',$slug)){ $failed[$name]='ungültige Kennung';continue; }
            $zip=$fetch('https://downloads.wordpress.org/'.$kind.'/'.$slug.'.zip');
            if(!is_string($zip)||$zip===''){ $failed[$name]='Download fehlgeschlagen';continue; }
            $err=rrw_demo_extras_unzip($zip,$slug,$target);
            if($err===null){   // Pflichtdatei prüfen: Theme-Stylesheet bzw. Plugin-Hauptdatei mit Kopfzeile
                $good=false;
                if($kind==='theme')$good=is_file($target.'/'.$slug.'/style.css')&&str_contains((string)file_get_contents($target.'/'.$slug.'/style.css',false,null,0,8192),'Theme Name:');
                else foreach(glob($target.'/'.$slug.'/*.php')?:[] as $f)if(str_contains((string)file_get_contents($f,false,null,0,8192),'Plugin Name:')){ $good=true;break; }
                if(!$good){ $err='Hauptdatei fehlt';rrw_demo_extras_rmdir($target.'/'.$slug); }
            }
            if($err!==null){ $failed[$name]=$err;continue; }
            $ok[$group][]=$name;
        }
    }
    $landing=$dir.'/demo/index.html';
    if(is_file($landing)&&($ok['themes']||$ok['plugins'])){
        $parts=[];if($ok['themes'])$parts[]='Themes '.implode(', ',array_map('htmlspecialchars',$ok['themes']));if($ok['plugins'])$parts[]='Plugins '.implode(', ',array_map('htmlspecialchars',$ok['plugins']));
        $li='<li>Vorinstallierte WordPress-'.implode(' und ',$parts).' – zum Testen der Kompatibilität in der Verwaltung aktivieren</li>';
        file_put_contents($landing,str_replace('<!--demo-extras-->',$li,(string)file_get_contents($landing)));
    }
    return ['ok'=>array_merge($ok['themes'],$ok['plugins']),'failed'=>$failed];
}

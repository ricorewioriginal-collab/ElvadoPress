<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$root = dirname(__DIR__);
if(is_file(__DIR__.'/lib/demo.json')){ require_once __DIR__.'/lib/demo.php';elvado_demo_boot(); }   // Demo-Betrieb (nur mit cms/lib/demo.json)
require_once __DIR__.'/lib/sites.php';elvado_sites_request_context();   // mehrere Websites: Kontext per Domain bzw. Verwaltungs-Kopf (ohne weitere Websites: nichts)
$dataDir = elvado_site_dir('data');
$genDir = elvado_site_dir('generated');
$mediaDir = elvado_site_dir('media');
$siteFile = $dataDir . '/site.json';
$newsFile = $dataDir . '/news.json';
$commentsFile = $dataDir . '/comments.json';
$revisionsFile = $dataDir . '/news-revisions.json';
$notificationsFile = $dataDir . '/notifications.json';
$newsViewsFile = $dataDir . '/news-views.json';
$activityLogFile = $dataDir . '/activity-log.json';

function elvado_json($data, int $code=200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function elvado_body(): array {
    static $b=null; if($b!==null)return $b;
    $raw=(string)file_get_contents('php://input');
    $j=json_decode($raw,true); return $b=is_array($j)?$j:[];
}
function elvado_token(): string {
    $h=$_SERVER['HTTP_X_ELVADOPRESS_TOKEN'] ?? '';
    if($h!=='')return trim($h);
    $b=elvado_body(); return trim((string)($b['_tok']??$_GET['_tok']??''));
}
function elvado_auth(bool $super=false): array {
    $tok=elvado_token();
    if($tok==='')elvado_json(['status'=>'error','message'=>'Nicht eingeloggt'],401);
    $sess=str_starts_with($tok,'local_')?elvado_local_session_validate($tok):null;
    if($sess===null)elvado_json(['status'=>'error','message'=>'Sitzung abgelaufen, bitte erneut anmelden'],401);
    $isAdmin=($sess['role']??'admin')==='admin';
    if($super&&!$isAdmin)elvado_json(['status'=>'error','message'=>'Nur Administratoren dürfen diese Aktion ausführen'],403);
    return ['allowed'=>true,'superadmin'=>$isAdmin,'role'=>$sess['role']??'admin','user'=>$sess['username'],'display_name'=>$sess['display_name']??$sess['username'],'source'=>'local'];
}
function elvado_upload(string $bucket,int $max=12582912): string {
    if(empty($_FILES['file'])||!is_uploaded_file($_FILES['file']['tmp_name']))elvado_json(['status'=>'error','message'=>'Keine Datei'],400);
    $f=$_FILES['file'];if(($f['size']??0)<=0||$f['size']>$max)elvado_json(['status'=>'error','message'=>'Datei zu groß'],400);
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$map=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif','image/svg+xml'=>'svg','image/x-icon'=>'ico','image/vnd.microsoft.icon'=>'ico','application/octet-stream'=>'ico'];
    if(!isset($map[$mime]))elvado_json(['status'=>'error','message'=>'Nicht unterstütztes Bildformat'],400);
    if($mime==='image/svg+xml'&&preg_match('/<(script|foreignObject)\b|on[a-z]+\s*=|javascript:/i',(string)file_get_contents($f['tmp_name'])))elvado_json(['status'=>'error','message'=>'Unsicheres SVG'],400);
    $dir=elvado_site_dir('media').'/'.$bucket;if(!is_dir($dir))@mkdir($dir,0755,true);$name=date('Ymd_His').'_'.bin2hex(random_bytes(5)).'.'.$map[$mime];if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$name))elvado_json(['status'=>'error','message'=>'Upload fehlgeschlagen'],500);@chmod($dir.'/'.$name,0644);return '/cms/media/'.$bucket.'/'.$name;
}
require_once __DIR__.'/lib/media.php';
// Bibliothek-Upload (HTTP): Prüfungen und Ablage liegen in cms/lib/media.php (elvado_media_library_store), hier nur die Antwort-Hülle.
function elvado_media_library_upload_variants(array $sizes,int $quality=86): array {
    if(empty($_FILES['file'])||!is_uploaded_file($_FILES['file']['tmp_name']))elvado_json(['status'=>'error','message'=>'Keine Datei'],400);
    try{return elvado_media_library_store($_FILES['file'],$sizes,$quality);}
    catch(RuntimeException $e){elvado_json(['status'=>'error','message'=>$e->getMessage()],$e->getCode()>=400?$e->getCode():400);}
}
function elvado_sync_branding_asset(string $kind,string $url,string $root): array {
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
        else $ok=elvado_resize_image_file($src,$mime,(int)$t['width'],$t['path'],92,'png');
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
function elvado_plugin_id(string $s): string {
    $s=strtolower(trim($s));$s=preg_replace('/[^a-z0-9_-]+/','-',$s);$s=trim((string)$s,'-');return substr($s!==''?$s:'plugin',0,64);
}
function elvado_plugin_catalog(array $site): array {
    $base=__DIR__.'/plugins';$active=array_fill_keys(array_map('strval',(array)($site['plugins']??[])),true);$out=[];
    if(!is_dir($base))return [];
    foreach(glob($base.'/*',GLOB_ONLYDIR)?:[] as $dir){
        $file=$dir.'/plugin.json';if(!is_file($file))continue;$m=json_decode((string)file_get_contents($file),true);if(!is_array($m))continue;
        if(($m['type']??'')==='native'||isset($m['entry'])||elvado_np()->reservedId(elvado_plugin_id((string)($m['id']??basename($dir)))))continue;   // native Plugins verwaltet das Plugin-System (np_*), nicht diese Liste
        $id=elvado_plugin_id((string)($m['id']??basename($dir)));if($id==='')continue;
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
function elvado_import_plugin_zip(string $zipPath): array {
    if(!class_exists('ZipArchive'))throw new RuntimeException('ZIP-Unterstützung ist auf dem Server nicht verfügbar');
    $zip=new ZipArchive();if($zip->open($zipPath)!==true)throw new RuntimeException('Plugin-ZIP konnte nicht geöffnet werden');
    $manifest=null;$prefix='';
    for($i=0;$i<$zip->numFiles;$i++){
        $name=str_replace('\\','/',$zip->getNameIndex($i));if(!elvado_zip_entry_safe($name))continue;
        if(strtolower(basename($name))==='plugin.json'){$manifest=$zip->getFromIndex($i);$prefix=dirname($name)==='.'?'':dirname($name).'/';break;}
    }
    if($manifest===null)throw new RuntimeException('plugin.json fehlt');
    $m=json_decode((string)$manifest,true);if(!is_array($m))throw new RuntimeException('plugin.json ist ungültig');
    $id=elvado_plugin_id((string)($m['id']??$m['name']??'plugin'));if($id==='')throw new RuntimeException('Plugin-ID ungültig');
    if(($m['type']??'')==='native'||elvado_np()->reservedId($id))throw new RuntimeException('Diese Plugin-ID ist für offizielle ElvadoPress-Plugins reserviert. Hochgeladene Plugins dürfen sie nicht verwenden und keinen Server-PHP-Code mitbringen.');
    $dir=__DIR__.'/plugins/'.$id;if(is_dir($dir)){foreach(glob($dir.'/*')?:[] as $x)if(is_file($x))@unlink($x);}else if(!@mkdir($dir,0755,true))throw new RuntimeException('Plugin-Ordner konnte nicht angelegt werden');
    $safe=['plugin.json','frontend.js','frontend.css','admin.js','README.md'];
    foreach($safe as $file){
        $idx=$zip->locateName($prefix.$file);if($idx===false)continue;$data=$zip->getFromIndex($idx);if($data===false)continue;
        if($file==='frontend.js'||$file==='admin.js'){
            if(strlen($data)>250000)throw new RuntimeException($file.' ist zu groß');
            if(preg_match('/\b(eval|Function)\s*\(|document\.write\s*\(/i',$data))throw new RuntimeException($file.' enthält nicht erlaubte dynamische Code-Ausführung');
        }
        elvado_write_atomic($dir.'/'.$file,(string)$data);
    }
    if(!is_file($dir.'/plugin.json'))elvado_write_atomic($dir.'/plugin.json',json_encode($m,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    $zip->close();return ['id'=>$id,'manifest'=>$m];
}
function elvado_theme_catalog(array $site,bool $withHidden=false): array {
    $base=__DIR__.'/themes';$active=(string)($site['theme']['active']??elvado_default_theme_id());$out=[];
    foreach(glob($base.'/*',GLOB_ONLYDIR)?:[] as $dir){
        $file=$dir.'/theme.json';if(!is_file($file))continue;$m=json_decode((string)file_get_contents($file),true);if(!is_array($m))continue;
        $id=elvado_theme_id((string)($m['id']??basename($dir)));if($id==='')continue;
        if($id!==$active&&in_array($id,(array)($site['theme_hidden']??[]),true)&&!$withHidden)continue;   // ausgeblendet (mitgelieferte Themes lassen sich nicht aus dem Code löschen)
        $shot='';foreach(['screenshot.webp','screenshot.png','screenshot.jpg','screenshot.jpeg'] as $sn)if(is_file($dir.'/'.$sn)){$shot='/cms/themes/'.$id.'/'.$sn;break;}
        $css=is_file($dir.'/theme.css')?'/cms/themes/'.$id.'/theme.css':'';
        $out[]=[
            'id'=>$id,'name'=>(string)($m['name']??$id),'version'=>(string)($m['version']??'1.0'),
            'author'=>(string)($m['author']??''),'description'=>(string)($m['description']??''),
            'license'=>(string)($m['license']??'Free Theme'),
            'builtin'=>!empty($m['builtin']),'wordpress'=>!empty($m['wordpress']),'bootstrap'=>!empty($m['bootstrap']),
            'compatibility'=>(string)($m['compatibility']??(!empty($m['wordpress'])?'wordpress-css':(!empty($m['bootstrap'])?'bootstrap-css':'native'))),
            'controls'=>is_array($m['controls']??null)?$m['controls']:[],
            'variants'=>is_array($m['variants']??null)?$m['variants']:[],
            'defaults'=>is_array($m['defaults']??null)?$m['defaults']:[],
            'layout'=>elvado_theme_layout_clean($m['layout']??[]),
            'widget_areas'=>is_array($m['widget_areas']??null)?elvado_clean_section('widget_areas',$m['widget_areas']):[],
            'stylesheet'=>$css,'screenshot'=>$shot,'active'=>$id===$active,'default'=>$id===elvado_default_theme_id(),'hidden'=>in_array($id,(array)($site['theme_hidden']??[]),true)
        ];
    }
    usort($out,fn($a,$b)=>($b['active']<=>$a['active'])?:strcasecmp($a['name'],$b['name']));return $out;
}
function elvado_zip_entry_safe(string $name): bool {
    $name=str_replace('\\','/',$name);
    return $name!==''&&!str_contains($name,'../')&&!str_starts_with($name,'/')&&!preg_match('/^[A-Za-z]:/',$name);
}
function elvado_import_theme_zip(string $zipPath): array {
    if(!class_exists('ZipArchive'))throw new RuntimeException('ZIP-Unterstützung ist auf dem Server nicht verfügbar');
    $zip=new ZipArchive();if($zip->open($zipPath)!==true)throw new RuntimeException('ZIP konnte nicht geöffnet werden');
    $themeJson=null;$styleCss=null;$screenshot=null;$wp=false;$rootPrefix='';
    for($i=0;$i<$zip->numFiles;$i++){
        $name=str_replace('\\','/',$zip->getNameIndex($i));if(!elvado_zip_entry_safe($name))continue;
        $base=strtolower(basename($name));
        if($base==='theme.json'&&$themeJson===null){$themeJson=$zip->getFromIndex($i);$rootPrefix=dirname($name)==='.'?'':dirname($name).'/';}
    }
    // WordPress-Block-Themes bringen eine eigene theme.json (anderes Format) mit: ohne theme.css
    // ist es kein Theme mit theme.json, sondern wird über style.css als WordPress-Theme importiert.
    if($themeJson!==null){$hasThemeCss=false;for($i=0;$i<$zip->numFiles;$i++)if(strtolower(basename(str_replace('\\','/',$zip->getNameIndex($i))))==='theme.css'){$hasThemeCss=true;break;}if(!$hasThemeCss)$themeJson=null;}
    if($themeJson!==null){
        $m=json_decode((string)$themeJson,true);if(!is_array($m))throw new RuntimeException('theme.json ist ungültig');
        $m['compatibility']=$m['compatibility']??'native';
        $id=elvado_theme_id((string)($m['id']??$m['name']??'theme'));
        for($i=0;$i<$zip->numFiles;$i++){
            $name=str_replace('\\','/',$zip->getNameIndex($i));if(!elvado_zip_entry_safe($name)||!str_starts_with($name,$rootPrefix))continue;
            $rel=substr($name,strlen($rootPrefix));$base=strtolower(basename($rel));
            if($base==='theme.css')$styleCss=(string)$zip->getFromIndex($i);
            if(in_array($base,['screenshot.png','screenshot.jpg','screenshot.jpeg','screenshot.webp'],true))$screenshot=['name'=>$base,'data'=>$zip->getFromIndex($i)];
        }
    } else {
        $wpStyleIndex=-1;
        for($i=0;$i<$zip->numFiles;$i++){
            $name=str_replace('\\','/',$zip->getNameIndex($i));if(!elvado_zip_entry_safe($name))continue;
            if(strtolower(basename($name))==='style.css'){$wpStyleIndex=$i;$rootPrefix=dirname($name)==='.'?'':dirname($name).'/';break;}
        }
        if($wpStyleIndex<0)throw new RuntimeException('Weder theme.json noch WordPress style.css gefunden');
        $wp=true;$styleCss=(string)$zip->getFromIndex($wpStyleIndex);
        preg_match('/Theme Name:\s*(.+)/i',$styleCss,$mm);$name=trim((string)($mm[1]??basename(rtrim($rootPrefix,'/'))?:'WordPress Theme'));
        preg_match('/Version:\s*(.+)/i',$styleCss,$mv);preg_match('/Author:\s*(.+)/i',$styleCss,$ma);
        $id=elvado_theme_id($name);
        $isBootstrap=stripos($styleCss,'bootstrap')!==false;
        $m=['id'=>$id,'name'=>$name,'version'=>trim((string)($mv[1]??'1.0')),'author'=>trim((string)($ma[1]??'')),'description'=>'WordPress-Theme über die WordPress-Kompatibilitätsschicht importiert. CSS, Bilder und Webfonts werden übernommen; WordPress-PHP wird nicht ausgeführt.','wordpress'=>true,'bootstrap'=>$isBootstrap,'compatibility'=>$isBootstrap?'wordpress+bootstrap':'wordpress-css','builtin'=>false];
        for($i=0;$i<$zip->numFiles;$i++){
            $nameIn=str_replace('\\','/',$zip->getNameIndex($i));if(!elvado_zip_entry_safe($nameIn)||!str_starts_with($nameIn,$rootPrefix))continue;
            $base=strtolower(basename($nameIn));if(in_array($base,['screenshot.png','screenshot.jpg','screenshot.jpeg','screenshot.webp'],true)){$screenshot=['name'=>$base,'data'=>$zip->getFromIndex($i)];break;}
        }
    }
    if($id===elvado_default_theme_id()){$zip->close();throw new RuntimeException('Das Standardtheme kann nicht überschrieben werden');}
    $dir=__DIR__.'/themes/'.$id;if(is_dir($dir)){foreach(glob($dir.'/*')?:[] as $x)if(is_file($x))@unlink($x);}else @mkdir($dir,0755,true);
    // Safe static assets only. No PHP/JS/templates/plugins are extracted.
    $allowedAssets=['png','jpg','jpeg','webp','gif','svg','ico'];
    for($i=0;$i<$zip->numFiles;$i++){
        $name=str_replace('\\','/',$zip->getNameIndex($i));if(!elvado_zip_entry_safe($name)||!str_starts_with($name,$rootPrefix))continue;
        $rel=substr($name,strlen($rootPrefix));if($rel===''||str_ends_with($rel,'/'))continue;
        $ext=strtolower((string)pathinfo($rel,PATHINFO_EXTENSION));if(!in_array($ext,$allowedAssets,true))continue;
        $data=$zip->getFromIndex($i);if(!is_string($data)||strlen($data)>8388608)continue;
        if($ext==='svg'&&preg_match('/<(script|foreignObject)\b|on[a-z]+\s*=|javascript:/i',$data))continue;
        $dest=$dir.'/'.ltrim($rel,'/');$destDir=dirname($dest);if(!is_dir($destDir))@mkdir($destDir,0755,true);
        elvado_write_atomic($dest,$data);
    }
    $zip->close();
    if($styleCss===null||trim($styleCss)==='')throw new RuntimeException('Theme enthält keine CSS-Datei');
    $styleCss=preg_replace('#url\((["\']?)\s*(?:javascript:|data:text/html)[^)]*\)#i','none',$styleCss);
    $m['id']=$id;$m['builtin']=false;$m['wordpress']=$wp||!empty($m['wordpress']);
    $m['bootstrap']=!empty($m['bootstrap'])||stripos((string)$styleCss,'bootstrap')!==false||preg_match('/\.container(?:-fluid)?\s*\{|\.row\s*\{|\.btn-primary\s*\{/i',(string)$styleCss);
    if(empty($m['compatibility']))$m['compatibility']=!empty($m['wordpress'])?(!empty($m['bootstrap'])?'wordpress+bootstrap':'wordpress-css'):(!empty($m['bootstrap'])?'bootstrap-css':'native');
    elvado_write_atomic($dir.'/theme.json',json_encode($m,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    elvado_write_atomic($dir.'/theme.css',(string)$styleCss);
    @file_put_contents($dir.'/.uploaded',gmdate('c'));   // Marke: über das CMS hochgeladen (lässt sich löschen; Themes aus dem Code werden nur ausgeblendet)
    if($screenshot&&is_string($screenshot['data']))elvado_write_atomic($dir.'/'.$screenshot['name'],$screenshot['data']);
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
require_once __DIR__.'/lib/nplugins.php';
elvado_system_apply_timezone();

// Gleichzeitiges Bearbeiten (mehrere Personen): Beim Speichern wird die Datei gesperrt und neu gelesen,
// und jeder Bereich hat eine Versionskennung. Wer auf einem älteren Stand speichern will, bekommt statt stillem Überschreiben
// einen Konflikt (HTTP 409) und lädt neu.
function elvado_section_rev(array $site,string $section): string { return substr(sha1(json_encode($site[$section]??null,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)),0,12); }
function elvado_site_revs(array $site): array { $r=[];foreach($site as $k=>$v){if(is_string($k)&&$k!==''&&$k[0]!=='_')$r[$k]=elvado_section_rev($site,$k);}return $r; }
function elvado_site_lock(string $dataDir): void {
    static $h=null; if($h)return;
    $h=@fopen($dataDir.'/.site.lock','c'); if(!$h)return;
    @flock($h,LOCK_EX); // wird beim Ende der Anfrage freigegeben
}
elvado_ensure_dirs();
if(defined('ELVADO_API_LIB_ONLY'))return;   // nur Hilfsfunktionen und Anmeldeprüfung laden, ohne Aktionen auszuführen (eigener Einstieg cms/engine-api.php für die WordPress-Engine)
$action=(string)($_GET['action']??'public');
// Native Plugins: bei Verwaltungsaktionen für Plugins werden sie nicht vorab geladen (Installieren/Aktualisieren prüft sie selbst); sonst laden die aktiven Plugins hier ihre Erweiterungspunkte
if(!in_array($action,['np_list','np_plan','np_install','np_activate','np_deactivate','np_update','np_uninstall','np_settings_save','np_settings_get','np_enable_recommended'],true)){ elvado_np_boot(); }
// Update-Überwachung: öffentlicher Lebenszeichen-Ping (prüft, dass das CMS startet) und – nur solange ein frisches Update überwacht wird – die Gesundheitsprüfung nach der Antwort
if($action==='update_ping')elvado_json(['status'=>'ok','version'=>elvado_cms_version()]);
if(is_file($dataDir.'/.update/state.json')&&str_contains((string)@file_get_contents($dataDir.'/.update/state.json'),'"pending"')){
    register_shutdown_function(static function() use($dataDir){
        if(function_exists('fastcgi_finish_request'))@fastcgi_finish_request();
        try{ require_once __DIR__.'/src/autoload.php';\Elvado\Update\UpdateService::forCms(__DIR__,$dataDir)->watchdog(); }catch(Throwable $e){}
    });
}
if(function_exists('elvado_demo_guard'))elvado_demo_guard($action,elvado_body());
$site=elvado_ensure_site_defaults(elvado_read_json($siteFile,[]));$GLOBALS['ELVADO_SITE']=$site;
if(!isset($site['theme'])||!is_array($site['theme']))$site['theme']=['active'=>elvado_default_theme_id()];

// Multi-Brand: öffentliche Markeninfo für den aufgerufenen Hostname (oder ?elvado_brand=<id> zur Vorschau)
$elvadoBrand=elvado_brand_resolve($site,(string)($_SERVER['HTTP_HOST']??''),elvado_brand_forced_from_request());
header('Vary: Host');
if($action==='brand')elvado_json(['status'=>'ok']+elvado_brand_public_payload($elvadoBrand));
// KI-Assistent (öffentlich): Chat
if($action==='assistant_chat'){ $r=elvado_assistant_chat($site,$elvadoBrand,elvado_body(),$dataDir,$newsFile);$code=(int)($r['code']??200);unset($r['code']);elvado_json($r,$code); }
// Apps: öffentliche Startabfrage der nativen Apps (Funktionen, Hinweis, Update) und Übersicht für das CMS (Admin)
if($action==='app_config'){
    $did=elvado_apps_did_clean($_GET['did']??'');$pl=(string)($_GET['platform']??'android');$ver=(string)($_GET['version']??'');
    // Eigene Apps des Build-Assistenten melden ihre Kennung (brand=<id>); alle anderen Apps wie bisher über den Hostnamen
    $acBrand=elvado_apps_own_brand(elvado_apps_own($dataDir),(string)($_GET['brand']??''))??$elvadoBrand;
    $out=elvado_apps_public($site,$root,$acBrand,$pl,$ver,$did,elvado_apps_salt($dataDir),max(0,min(99999999,(int)($_GET['code']??0))));
    if(!empty($out['telemetry']['usage'])&&$did!==''&&elvado_apps_rate_ok($dataDir,'ping',120)){
        $geo='';if(($site['apps']['telemetry']['geo']??true)){$ip=trim(explode(',',(string)($_SERVER['HTTP_X_FORWARDED_FOR']??$_SERVER['REMOTE_ADDR']??''))[0]);$geo=elvado_geo_lookup($dataDir,$ip);}
        elvado_apps_record_ping($dataDir,$out['brand'],$out['platform'],$ver,$did,$geo);
    }
    elvado_json($out);
}
// Download-Zähler: zählt (je Datei höchstens 3x pro Stunde und Besucher, nicht für Suchmaschinen/Vorschau/HEAD) und leitet auf die Datei in downloads/ weiter
if($action==='app_download'){
    $f=basename((string)($_GET['file']??''));
    if(!elvado_apps_download_file_ok($root,$f)){http_response_code(404);header('Content-Type:text/plain; charset=utf-8');exit('Datei nicht gefunden');}
    $bot=preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|curl|wget/i',(string)($_SERVER['HTTP_USER_AGENT']??''));
    if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'&&!$bot&&elvado_apps_rate_ok($dataDir,'dl_'.substr(sha1($f),0,8),3))elvado_apps_download_add($dataDir,$f);
    header('Cache-Control: no-store');header('Location: /downloads/'.rawurlencode($f),true,302);exit;
}
if($action==='app_error'){
    $b=elvado_body();$pl=isset(ELVADO_APPS_PLATFORMS[(string)($b['platform']??'')])?(string)$b['platform']:'';
    $on=!empty($site['apps']['telemetry']['errors']);
    if(!$on||$pl==='')elvado_json(['status'=>'ok','stored'=>false]);
    if(!elvado_apps_rate_ok($dataDir,'err',30))elvado_json(['status'=>'error','message'=>'Zu viele Berichte.'],429);
    $reg=elvado_brands_registry($site);$ob=elvado_apps_own_brand(elvado_apps_own($dataDir),(string)($b['brand']??''));$bid=(string)($ob['brand']??$elvadoBrand['brand']??$elvadoBrand['id']??$reg['default']);   // eigene App des Build-Assistenten: ihre Kennung
    elvado_json(['status'=>'ok','stored'=>elvado_apps_error_add($dataDir,$bid,$pl,$b)]);
}
if($action==='alexa_config'){
    $origin=elvado_site_origin($site);
    elvado_alexa_note_fetch($dataDir);header('Cache-Control: public, max-age=60');
    elvado_json(elvado_alexa_public($site,$dataDir,$origin));
}
if($action==='alexa_stat'){
    $b=elvado_body();$tok=(string)($b['token']??'');
    if(!hash_equals(elvado_alexa_token($dataDir),$tok))elvado_json(['status'=>'error','message'=>'Nicht erlaubt'],403);
    if(empty($site['alexa']['stats']))elvado_json(['status'=>'ok','stored'=>false]);
    elvado_json(['status'=>'ok','stored'=>elvado_alexa_stat_add($dataDir,(string)($b['event']??''),(string)($b['topic']??''),(string)($b['intent']??''))]);
}
if($action==='alexa_get'){
    elvado_auth(true);$origin=elvado_site_origin($site);$cfg=elvado_alexa_clean($site['alexa']??[]);$over=(array)$cfg['topics'];$tp=[];
    foreach(elvado_alexa_topic_defs($site) as $id=>$s){$v=elvado_alexa_topic_value($s);$tp[]=['id'=>$id,'title'=>$s['title'],'text'=>(string)($over[$id]['text']??''),'enabled'=>(bool)($over[$id]['enabled']??true),'extra'=>$s['extra'],'custom_title'=>(string)($over[$id]['title']??''),'speakable'=>array_merge([$v['name']['value']],array_slice($v['name']['synonyms'],0,6))];}
    elvado_alexa_model($site,$warn);$brand=elvado_alexa_brand($site);
    elvado_json(['status'=>'ok','config'=>$cfg,'topics'=>$tp,'news'=>elvado_alexa_news($dataDir,$cfg['news']['count']),'stats'=>elvado_alexa_stats($dataDir),'last_fetch'=>elvado_alexa_last_fetch($dataDir),'warnings'=>$warn,'origin'=>$origin,'invocation'=>$brand['invocationName'],'app_name'=>$brand['name'],'token_set'=>strlen(elvado_alexa_token($dataDir))>=32,'model_rev'=>elvado_alexa_model_rev($site),'exported_rev'=>elvado_alexa_exported_rev($dataDir)]);
}
if($action==='alexa_icon_upload'){
    elvado_auth(true);
    if(empty($_FILES['file'])||!is_uploaded_file($_FILES['file']['tmp_name']))elvado_json(['status'=>'error','message'=>'Keine Bilddatei empfangen'],400);
    $f=$_FILES['file'];if(($f['size']??0)<=0||$f['size']>12582912)elvado_json(['status'=>'error','message'=>'Bilddatei ist leer oder zu groß'],400);
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if(!in_array($mime,['image/png','image/jpeg','image/webp'],true))elvado_json(['status'=>'error','message'=>'Bitte PNG, JPG oder WebP hochladen'],400);
    $info=@getimagesize($f['tmp_name']);if(!$info||($info[0]??0)<108||($info[1]??0)<108)elvado_json(['status'=>'error','message'=>'Das Skill-Icon muss mindestens 108 × 108 Pixel groß sein'],400);
    if(abs(($info[0]/$info[1])-1)>0.02)elvado_json(['status'=>'error','message'=>'Das Skill-Icon muss quadratisch sein'],400);

    // Dauerhaftes Original im CMS ablegen. Die öffentliche Website bekommt nur die daraus erzeugten Größen.
    $store=elvado_site_dir('media').'/alexa';if(!is_dir($store)&&!@mkdir($store,0755,true))elvado_json(['status'=>'error','message'=>'CMS-Medienordner für Alexa ist nicht beschreibbar'],500);
    $original=$store.'/skill-icon-original.'.($mime==='image/jpeg'?'jpg':($mime==='image/webp'?'webp':'png'));
    foreach(glob($store.'/skill-icon-original.*')?:[] as $oldFile)@unlink($oldFile);
    if(!@copy($f['tmp_name'],$original)||!is_file($original)||filesize($original)<1)elvado_json(['status'=>'error','message'=>'Das Original konnte nicht dauerhaft gespeichert werden'],500);
    @chmod($original,0644);

    $dir=$root.'/assets/img/alexa';if(!is_dir($dir)&&!@mkdir($dir,0755,true))elvado_json(['status'=>'error','message'=>'Öffentlicher Alexa-Icon-Ordner ist nicht beschreibbar'],500);
    $written=[];$checks=[];
    foreach([108,512] as $size){
        $dest=$dir.'/icon-'.$size.'.png';$tmp=$dir.'/.icon-'.$size.'.'.bin2hex(random_bytes(4)).'.tmp.png';
        if(!elvado_resize_image_file($original,$mime,$size,$tmp,94,'png')||!is_file($tmp))elvado_json(['status'=>'error','message'=>'Icon konnte nicht in '.$size.' × '.$size.' erzeugt werden'],500);
        $dim=@getimagesize($tmp);
        if(!$dim||($dim[0]??0)!==$size||($dim[1]??0)!==$size){@unlink($tmp);elvado_json(['status'=>'error','message'=>'Erzeugtes '.$size.'-px-Icon hat eine falsche Größe'],500);}
        // Erst nach erfolgreicher Erzeugung atomar ersetzen.
        if(!@rename($tmp,$dest)){if(!@copy($tmp,$dest)){@unlink($tmp);elvado_json(['status'=>'error','message'=>'Icon '.$size.' px konnte nicht veröffentlicht werden. Schreibrechte prüfen.'],500);}@unlink($tmp);}
        clearstatcache(true,$dest);@chmod($dest,0644);
        if(!is_file($dest)||filesize($dest)<1)elvado_json(['status'=>'error','message'=>'Icon '.$size.' px wurde nach dem Schreiben nicht gefunden'],500);
        $hash=hash_file('sha256',$dest);$written[]='/assets/img/alexa/icon-'.$size.'.png';$checks[(string)$size]=['bytes'=>filesize($dest),'sha256'=>$hash];
    }
    clearstatcache(true,$original);
    elvado_json(['status'=>'ok','files'=>$written,'checks'=>$checks,'original'=>['bytes'=>filesize($original),'sha256'=>hash_file('sha256',$original)],'version'=>time()]);
}
if($action==='alexa_token_reset'){ elvado_auth(true);elvado_alexa_token($dataDir,true);elvado_json(['status'=>'ok']); }
if($action==='alexa_stats_clear'){ elvado_auth(true);elvado_alexa_stats_clear($dataDir);elvado_json(['status'=>'ok']); }
if($action==='alexa_download'){
    elvado_auth(true);$origin=elvado_site_origin($site);
    $what=(string)($_GET['file']??'package');$files=elvado_alexa_package_files($site,$root,$origin,$dataDir,$warn);
    if(in_array($what,['package','model'],true))elvado_alexa_mark_exported($dataDir,elvado_alexa_model_rev($site));
    header_remove('Content-Type');
    if($what==='package'){try{$zip=elvado_alexa_zip($files);}catch(Throwable $e){http_response_code(500);exit($e->getMessage());}header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="alexa-skill.zip"');header('Content-Length: '.filesize($zip));readfile($zip);@unlink($zip);exit;}
    $map=['model'=>['skill-package/interactionModels/custom/de-DE.json','de-DE.json'],'manifest'=>['skill-package/skill.json','skill.json'],'lambda'=>['lambda/index.js','index.js'],'fallback'=>['lambda/fallback.json','fallback.json'],'cms'=>['lambda/cms.json','cms.json']];
    if(!isset($map[$what])){http_response_code(404);exit('Unbekannte Datei');}
    header('Content-Type: '.(str_ends_with($map[$what][1],'.js')?'application/javascript':'application/json').'; charset=utf-8');header('Content-Disposition: attachment; filename="'.$map[$what][1].'"');echo $files[$map[$what][0]];exit;
}
if($action==='apps_overview'){ elvado_auth(true);elvado_json(elvado_apps_overview($site,$root,elvado_apps_own($dataDir))); }
// Build-Assistent für eigene Android-Apps (GitHub-Repository mit den App-Quellen, Workflow android-custom-brand.yml) – nur Superadmins
if(str_starts_with($action,'app_build')){
    $abUser=elvado_auth(true);require_once __DIR__.'/lib/appbuild.php';$ab=elvado_body();
    $abFail=fn(string $m,int $c=422)=>elvado_json(['status'=>'error','message'=>$m],$c);
    $abState=fn(array $x=[])=>elvado_json(['status'=>'ok']+elvado_ab_state($dataDir)+$x);
    if($action==='app_build_state')$abState();
    if($action==='app_build_download'){ elvado_ab_download($dataDir,(string)($_GET['brand']??''),(int)($_GET['asset']??0));exit; }
    if($action==='app_build_save'){   // Repository, Branch, Token
        $d=elvado_ab_load($dataDir);$repo=elvado_ab_clean_repo((string)($ab['repo']??''));$br=elvado_ab_clean_branch((string)($ab['branch']??'app-builder'));
        if($repo==='')$abFail('Das Repository hat die Form besitzer/name.');
        if($br==='')$abFail('Ungültiger Branch-Name.');
        $tok=trim((string)($ab['token']??''));if($tok!==''&&!preg_match('/^[A-Za-z0-9_\-]{20,255}$/',$tok))$abFail('Das Token hat ein ungültiges Format.');
        if($tok!=='')$d['token']=$tok;if(!empty($ab['clear_token']))$d['token']='';
        $d['repo']=$repo;$d['branch']=$br;if(!elvado_ab_save($dataDir,$d))$abFail('Konnte nicht gespeichert werden (Schreibrechte für cms/data prüfen).',500);
        elvado_log_activity($activityLogFile,$abUser,'app_build','App-Builder: Repository '.$repo.' eingerichtet');$abState();
    }
    if($action==='app_build_check')elvado_json(['status'=>'ok']+elvado_ab_check($dataDir));
    if($action==='app_build_brand_save'){
        $d=elvado_ab_load($dataDir);$origin=(isset($_SERVER['HTTP_HOST'])?'https://'.preg_replace('/[^A-Za-z0-9.:-]/','',(string)$_SERVER['HTTP_HOST']):'');
        [$b,$err]=elvado_ab_clean_brand($ab,$origin);if($b===null)$abFail($err);
        $i=null;foreach($d['brands'] as $k=>$x)if(($x['id']??'')===$b['id'])$i=$k;
        foreach($d['brands'] as $k=>$x)if($k!==$i&&($x['applicationId']??'')===$b['applicationId'])$abFail('Dieser Paketname wird schon von einer anderen App verwendet.');
        if($i===null&&count($d['brands'])>=ELVADO_AB_MAX_BRANDS)$abFail('Es sind höchstens '.ELVADO_AB_MAX_BRANDS.' eigene Apps möglich.');
        if($i===null)$d['brands'][]=$b;else $d['brands'][$i]=$b;
        if(!elvado_ab_save($dataDir,$d))$abFail('Konnte nicht gespeichert werden.',500);
        elvado_log_activity($activityLogFile,$abUser,'app_build','App-Builder: App „'.$b['appName'].'“ gespeichert');$abState();
    }
    if($action==='app_build_brand_delete'){
        $d=elvado_ab_load($dataDir);$id=(string)($ab['id']??'');$d['brands']=array_values(array_filter($d['brands'],fn($x)=>($x['id']??'')!==$id));
        if(!elvado_ab_save($dataDir,$d))$abFail('Konnte nicht gespeichert werden.',500);
        elvado_log_activity($activityLogFile,$abUser,'app_build','App-Builder: App '.$id.' entfernt');$abState();
    }
    if($action==='app_build_start'){
        if(!elvado_apps_rate_ok($dataDir,'abstart',6))$abFail('Bitte kurz warten, bevor du erneut baust.',429);
        $r=elvado_ab_start($dataDir,$root,(string)($ab['id']??''),(string)($ab['platform']??'android'));if(!$r['ok'])$abFail($r['message']);
        elvado_log_activity($activityLogFile,$abUser,'app_build','App-Builder: Build gestartet ('.(string)$ab['id'].')');elvado_json(['status'=>'ok','message'=>$r['message']]);
    }
    if($action==='app_build_status'){ $r=elvado_ab_status($dataDir,(string)($_GET['brand']??''));if(!$r['ok'])$abFail($r['message']);elvado_json(['status'=>'ok']+$r); }
    $abFail('Unbekannte Aktion',404);
}
if($action==='apps_stats'){ elvado_auth(true);elvado_json(['status'=>'ok','usage'=>elvado_apps_usage($dataDir),'new_daily'=>elvado_apps_new_daily($dataDir),'retention'=>elvado_apps_retention($dataDir),'downloads'=>elvado_apps_download_stats($dataDir,$site,$root),'errors'=>elvado_apps_errors($dataDir),'telemetry'=>(array)($site['apps']['telemetry']??[]),'geo'=>elvado_apps_geo_stats($dataDir),'geo_db'=>elvado_geo_status($dataDir)]); }
if($action==='apps_geo_update'){ elvado_auth(true);$r=elvado_geo_download($dataDir);elvado_json($r['ok']?['status'=>'ok','geo_db'=>elvado_geo_status($dataDir)]:['status'=>'error','message'=>$r['message']],$r['ok']?200:502); }
if($action==='apps_stats_clear'){ elvado_auth(true);$b=elvado_body();elvado_apps_stats_clear($dataDir,(string)($b['what']??'all'));elvado_json(['status'=>'ok']); }
if($action==='assistant_status'){ elvado_auth(false);elvado_json(['status'=>'ok']+elvado_assistant_status($site,$dataDir)); }
if($action==='assistant_test'){ elvado_auth(false);$b=elvado_body();elvado_json(['status'=>'ok']+elvado_assistant_test($site,(string)($b['provider']??''),$dataDir,(string)($b['model']??''))); }
if($action==='assistant_models'){ elvado_auth(true);elvado_json(['status'=>'ok']+elvado_assistant_models_list($site,elvado_body(),$dataDir)); }
if($action==='brands_public'){$reg=elvado_brands_registry($site);elvado_json(['status'=>'ok','default'=>$reg['default'],'brands'=>array_map(fn($b)=>['id'=>$b['id'],'name'=>$b['name'],'short_name'=>$b['short_name'],'primary_domain'=>$b['primary_domain'],'domains'=>$b['domains'],'enabled'=>!empty($b['enabled'])],$reg['items'])]);}
if($action==='public')elvado_json(['status'=>'ok','config'=>elvado_site_public($site),'brand'=>elvado_brand_public_payload($elvadoBrand),'storage'=>'/cms/data/site.json']);
if($action==='access'){ $a=elvado_auth(false);elvado_json(['status'=>'ok','allowed'=>true,'superadmin'=>!empty($a['superadmin']),'role'=>$a['role']??'admin','user'=>$a['user']??'','display_name'=>$a['display_name']??'','source'=>$a['source']??'','product'=>elvado_product_public(),'version'=>elvado_cms_version()]); }
if($action==='product'){ elvado_json(['status'=>'ok','product'=>elvado_product_public()]); }
if($action==='local_auth_status'){ elvado_json(['status'=>'ok','configured'=>elvado_local_auth_configured(),'install_needed'=>elvado_install_needed(),'product'=>elvado_product_public()]); }
if($action==='local_auth_setup'){
    // Frische Installation: Konto nur über den Einrichtungsassistenten (Passwortregeln, CSRF, Sperre), nicht über diese offene Route.
    if(elvado_install_needed())elvado_json(['status'=>'error','message'=>'Bitte die Einrichtung über install.php abschließen','install_needed'=>true],409);
    $configured=elvado_local_auth_configured();
    if($configured)elvado_auth(true);
    $b=elvado_body();
    try{ elvado_local_auth_set((string)($b['username']??''),(string)($b['password']??''),'admin'); }
    catch(Throwable $e){ elvado_json(['status'=>'error','message'=>$e->getMessage()],400); }
    if(!$configured)elvado_json(['status'=>'ok','token'=>elvado_local_session_create(trim((string)($b['username']??''))),'superadmin'=>true]);
    elvado_json(['status'=>'ok']);
}
if($action==='login'){
    if(!elvado_local_auth_configured())elvado_json(['status'=>'error','message'=>'Lokaler Zugang ist nicht eingerichtet'],400);
    $b=elvado_body();$username=trim((string)($b['username']??''));$password=(string)($b['password']??'');
    $loginBlock=elvado_np_filter('login_check',null,$username,(string)($_SERVER['REMOTE_ADDR']??''));   // Plugins (z. B. Security) dürfen den Versuch vor der Passwortprüfung abweisen
    if(is_string($loginBlock)&&$loginBlock!=='')elvado_json(['status'=>'error','message'=>$loginBlock],429);
    $user=elvado_local_auth_verify($username,$password);
    elvado_np_do('login_result',$username,$user!==null,(string)($_SERVER['REMOTE_ADDR']??''));
    if($user===null)elvado_json(['status'=>'error','message'=>'Benutzername oder Passwort falsch'],401);
    elvado_json(['status'=>'ok','token'=>elvado_local_session_create((string)$user['username']),'superadmin'=>($user['role']??'admin')==='admin']);
}
if(in_array($action,['users_list','user_add','user_update','user_delete','activity_log_list'],true))$authUser=elvado_auth(true);
if($action==='activity_log_list'){
    $all=elvado_read_json($activityLogFile,[]);
    usort($all,fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));
    elvado_json(['status'=>'ok','entries'=>array_slice($all,0,100)]);
}
if($action==='users_list'){
    $users=array_map(fn($u)=>['username'=>(string)($u['username']??''),'role'=>$u['role']??'autor','display_name'=>(string)($u['display_name']??$u['username']??''),'email'=>(string)($u['email']??''),'created_at'=>(string)($u['created_at']??'')],elvado_local_users());
    elvado_json(['status'=>'ok','users'=>$users]);
}
if($action==='user_add'){
    $b=elvado_body();$newUsername=(string)($b['username']??'');
    try{ elvado_local_user_add($newUsername,(string)($b['password']??''),(string)($b['role']??'autor'),(string)($b['display_name']??''),(string)($b['email']??'')); }
    catch(Throwable $e){ elvado_json(['status'=>'error','message'=>$e->getMessage()],400); }
    elvado_log_activity($activityLogFile,$authUser,'user_add','Redakteur „'.$newUsername.'“ angelegt');
    elvado_json(['status'=>'ok']);
}
if($action==='user_update'){
    $b=elvado_body();$targetUsername=(string)($b['username']??'');
    $password=array_key_exists('password',$b)&&trim((string)$b['password'])!==''?(string)$b['password']:null;
    $role=array_key_exists('role',$b)?(string)$b['role']:null;
    $displayName=array_key_exists('display_name',$b)?(string)$b['display_name']:null;
    $email=array_key_exists('email',$b)?(string)$b['email']:null;
    try{ elvado_local_user_update($targetUsername,$password,$role,$displayName,$email); }
    catch(Throwable $e){ elvado_json(['status'=>'error','message'=>$e->getMessage()],400); }
    $changed=array_filter(['Rolle'=>$role!==null,'Passwort'=>$password!==null,'E-Mail'=>$email!==null,'Anzeigename'=>$displayName!==null]);
    elvado_log_activity($activityLogFile,$authUser,'user_update','Redakteur „'.$targetUsername.'“ aktualisiert ('.implode(', ',array_keys($changed)).')');
    elvado_json(['status'=>'ok']);
}
if($action==='user_delete'){
    $b=elvado_body();$username=(string)($b['username']??'');
    if(strcasecmp($authUser['user']??'',$username)===0)elvado_json(['status'=>'error','message'=>'Du kannst dich nicht selbst löschen'],400);
    try{ elvado_local_user_delete($username); }
    catch(Throwable $e){ elvado_json(['status'=>'error','message'=>$e->getMessage()],400); }
    elvado_log_activity($activityLogFile,$authUser,'user_delete','Redakteur „'.$username.'“ entfernt');
    elvado_json(['status'=>'ok']);
}
// Eigenes Profil bearbeiten (Anzeigename, E-Mail, Passwort) - im Gegensatz zu user_update
// braucht dies keine Administratorrechte, jeder eingeloggte lokale Nutzer darf nur sich selbst
// ändern (Rolle bleibt dabei bewusst unveränderbar). Wie bei WordPress' "Eigenes Profil
// bearbeiten" ist das unabhängig von der Redakteur-Verwaltung, die Admins vorbehalten ist.
if($action==='profile_get_self'){
    $selfAuth=elvado_auth(false);
    $me=null;foreach(elvado_local_users() as $u)if(strcasecmp((string)($u['username']??''),(string)$selfAuth['user'])===0){$me=$u;break;}
    elvado_json(['status'=>'ok','source'=>'local','user'=>$selfAuth['user'],'display_name'=>(string)($me['display_name']??$selfAuth['user']),'role'=>$selfAuth['role']??'autor','email'=>(string)($me['email']??'')]);
}
if($action==='profile_update_self'){
    $selfAuth=elvado_auth(false);
    $b=elvado_body();
    $password=array_key_exists('password',$b)&&trim((string)$b['password'])!==''?(string)$b['password']:null;
    $displayName=array_key_exists('display_name',$b)?(string)$b['display_name']:null;
    $email=array_key_exists('email',$b)?(string)$b['email']:null;
    try{ elvado_local_user_update((string)$selfAuth['user'],$password,null,$displayName,$email); }
    catch(Throwable $e){ elvado_json(['status'=>'error','message'=>$e->getMessage()],400); }
    elvado_log_activity($activityLogFile,$selfAuth,'profile_update_self','Eigenes Profil aktualisiert');
    elvado_json(['status'=>'ok']);
}
if($action==='logout'){
    $tok=elvado_token(); if($tok!=='')elvado_local_session_destroy($tok);
    elvado_json(['status'=>'ok']);
}
// Betrieb und Produkt (nur Administratoren): Betriebsmodus, Produktname, Version, optionale Prüfsumme.
if($action==='system_get'){
    elvado_auth(true);
    $adm=0;foreach(elvado_local_users() as $u)if(($u['role']??'')==='admin')$adm++;
    $out=['status'=>'ok','system'=>elvado_system_config(),'product'=>elvado_product(),'product_overrides'=>elvado_product_overrides(),'product_defaults'=>elvado_product_defaults(),'version'=>elvado_cms_version(),'local_admins'=>$adm];
    if(!empty($_GET['checksum']))$out['checksum']=elvado_cms_checksum();
    elvado_json($out);
}
if($action==='system_save'){
    $su=elvado_auth(true);$b=elvado_body();$changed=[];
    try{
        foreach(['language','timezone'] as $k)if(array_key_exists($k,$b)){elvado_system_save([$k=>(string)$b[$k]]);$changed[]=$k==='language'?'Sprache':'Zeitzone';}
        if(is_array($b['product']??null)){elvado_product_save($b['product']);$changed[]='Produktname';}
    }catch(InvalidArgumentException $e){elvado_json(['status'=>'error','message'=>$e->getMessage()],400);}
    catch(Throwable $e){elvado_json(['status'=>'error','message'=>'Speichern fehlgeschlagen'],500);}
    if($changed)elvado_log_activity($activityLogFile,$su,'system_save','Betrieb/Produkt geändert ('.implode(', ',array_unique($changed)).')');
    elvado_json(['status'=>'ok','system'=>elvado_system_config(),'product'=>elvado_product()]);
}
if($action==='health'){
    elvado_auth(false);
    elvado_ensure_dirs();
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
    unset($checks['index_file'],$checks['rss_file']);
    $ok=true;foreach($checks as $x){if(empty($x['writable'])){$ok=false;break;}}
    elvado_json(['status'=>'ok','healthy'=>$ok,'checks'=>$checks,'storage'=>'/cms/data/site.json','news'=>'/cms/data/news.json']);
}
// Website-Zustand (wie WordPress' "Site Health"): rein lesende Selbstdiagnose. Prüft vor allem die
// Punkte, die im Betrieb sonst nur als diffuse Folgefehler sichtbar werden - insbesondere die
// fehlende PHP-Erweiterungen,
// beschädigte Datendateien und zu kleine Upload-Limits. Status je Prüfung: good | warn | critical.
if($action==='site_health'){
    elvado_auth(false);
    $items=[];
    $add=function(string $group,string $label,string $status,string $detail)use(&$items){$items[]=['group'=>$group,'label'=>$label,'status'=>$status,'detail'=>$detail];};
    $bytes=function(string $v):int{$v=trim($v);if($v==='')return 0;$n=(float)$v;switch(strtolower(substr($v,-1))){case 'g':$n*=1024;case 'm':$n*=1024;case 'k':$n*=1024;}return (int)$n;};
    $fmt=function(int $n):string{if($n<1048576)return round($n/1024).' KB';if($n<1073741824)return round($n/1048576,1).' MB';return round($n/1073741824,2).' GB';};

    $add('Server','PHP-Version',version_compare(PHP_VERSION,'8.1.0','>=')?'good':'critical',PHP_VERSION.(version_compare(PHP_VERSION,'8.1.0','>=')?'':' - mindestens 8.1 erforderlich'));
    $https=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||(($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https');
    $add('Server','HTTPS',$https?'good':'warn',$https?'Verwaltung läuft verschlüsselt':'Verwaltung wird ohne HTTPS aufgerufen');
    $add('PHP-Erweiterungen','SQLite-Treiber',(class_exists('PDO')&&in_array('sqlite',PDO::getAvailableDrivers(),true))?'good':'warn',(class_exists('PDO')&&in_array('sqlite',PDO::getAvailableDrivers(),true))?'pdo_sqlite verfügbar':'pdo_sqlite fehlt - Datenbank-Spiegel nicht nutzbar');
    foreach([
        ['curl',function_exists('curl_init'),'externe Feeds'],
        ['gd',function_exists('imagecreatetruecolor'),'Bildgrößen-Varianten in der Medienbibliothek'],
        ['fileinfo',class_exists('finfo'),'Typprüfung bei Uploads'],
        ['zip',class_exists('ZipArchive'),'Backups und Plugin-/Theme-Installation'],
        ['mbstring',function_exists('mb_substr'),'Umlaute und Textlängen in Beiträgen'],
    ] as [$ext,$ok,$use])$add('PHP-Erweiterungen',$ext,$ok?'good':'warn',$ok?'verfügbar - '.$use:'fehlt - betrifft: '.$use);
    $uploadMax=min($bytes((string)ini_get('upload_max_filesize')),$bytes((string)ini_get('post_max_size')));
    $add('Server','Upload-Limit',$uploadMax>=12582912?'good':'warn',$fmt($uploadMax).' (upload_max_filesize / post_max_size)'.($uploadMax>=12582912?'':' - Medien bis 12 MB können sonst nicht hochgeladen werden'));
    $mem=$bytes((string)ini_get('memory_limit'));
    $add('Server','PHP-Speicherlimit',($mem<=0||$mem>=134217728)?'good':'warn',$mem<=0?'unbegrenzt':$fmt($mem).($mem>=134217728?'':' - für Bildvarianten sind 128 MB empfohlen'));

    $add('Anmeldung','Lokaler CMS-Zugang',elvado_local_auth_configured()?'good':'warn',elvado_local_auth_configured()?'eingerichtet - Login direkt über /cms/ möglich':'nicht eingerichtet - bitte die Einrichtung abschließen');

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
    elvado_json(['status'=>'ok','overall'=>$overall,'summary'=>$summary,'items'=>$items,'checked_at'=>date('Y-m-d H:i:s')]);
}
if($action==='revs'){elvado_auth(false);elvado_json(['status'=>'ok','revs'=>elvado_site_revs($site)]);}
if($action==='get'){elvado_auth(false);$cfgOut=$site;if(function_exists('elvado_assistant_admin_view'))$cfgOut['assistant']=elvado_assistant_admin_view((array)($site['assistant']??[]));elvado_json(['status'=>'ok','config'=>$cfgOut,'revs'=>elvado_site_revs($site),'storage'=>'cms/data/site.json']);}
const ELVADO_ADMIN_ONLY_SECTIONS=['apps','alexa','assistant','services','brands','storage','backup','plugins'];
if($action==='save'){
    $authUser=elvado_auth(false);$b=elvado_body();$section=(string)($b['section']??'');
    $GLOBALS['elvado_html_unfiltered']=!empty($authUser['superadmin']);   // Raw-HTML-Blöcke bleiben nur bei Administratoren unverändert (nie in der Demo)
    // Sicherheitsrelevante Bereiche (Apps, Alexa, KI-Assistent mit API-Schlüsseln, Dienste, Domains, Speicher, Backup, Plugins) wie ihre eigenen Lese-/Schreibaktionen
    // nur für Administratoren, nicht für Redakteure: sonst ließe sich die Sperre dieser Aktionen über das allgemeine Speichern umgehen.
    if(in_array($section,ELVADO_ADMIN_ONLY_SECTIONS,true)&&empty($authUser['superadmin']))elvado_json(['status'=>'error','message'=>'Nur Administratoren dürfen diesen Bereich ändern'],403);
    elvado_site_lock($dataDir);
    $site=elvado_ensure_site_defaults(elvado_read_json($siteFile,[]));if(!isset($site['theme'])||!is_array($site['theme']))$site['theme']=['active'=>elvado_default_theme_id()];$GLOBALS['ELVADO_SITE']=$site;
    // Bereiche, die eine frische Installation noch nicht angelegt hat (die Oberfläche speichert sie beim ersten Mal): leer anlegen statt „Unbekannter CMS-Bereich“
    if(!array_key_exists($section,$site)&&in_array($section,['pages','legal','apps','core_network','header_builder'],true))$site[$section]=in_array($section,['pages'],true)?[]:(in_array($section,['core_network'],true)?['stations'=>[]]:(($section==='header_builder')?['enabled'=>false,'items'=>[]]:[]));
    if(!array_key_exists($section,$site))elvado_json(['status'=>'error','message'=>'Unbekannter CMS-Bereich'],400);
    $baseRev=(string)($b['base_rev']??'');
    if($baseRev!==''&&$baseRev!==elvado_section_rev($site,$section))elvado_json(['status'=>'conflict','message'=>'Dieser Bereich wurde inzwischen an anderer Stelle geändert (z. B. von einer anderen Person). Bitte neu laden, damit nichts überschrieben wird.','rev'=>elvado_section_rev($site,$section)],409);
    $value=$b['value']??null;
    // Theme-Layout und die pro Theme gespeicherten Anpassungen gehören dem Theme-System, nicht
    // dem Formular: beim generischen Speichern des Theme-Bereichs bleiben sie erhalten.
    if($section==='theme'&&is_array($value)){$value+=['layout'=>$site['theme']['layout']??null,'mods'=>$site['theme']['mods']??[]];}
    if($section==='assistant'&&function_exists('elvado_assistant_merge_keys'))$value=elvado_assistant_merge_keys((array)($site['assistant']??[]),$value);
    // Optionale Zusatzfelder, die nicht im Formular stehen (von der WordPress-Schicht geschrieben), bleiben beim Speichern erhalten.
    if(is_array($value)&&in_array($section,['portal','legal'],true)){$kx=$section==='portal'?'tagline':'email';if(!array_key_exists($kx,$value)&&isset($site[$section][$kx]))$value[$kx]=$site[$section][$kx];}
    $clean=elvado_clean_section($section,$value);
    if($section==='brands'){$cf=elvado_brand_domain_conflicts((array)($clean['items']??[]));if($cf)elvado_json(['status'=>'error','message'=>elvado_brand_conflict_message($cf,(array)($clean['items']??[]))],400);}
    if($section==='assistant'&&function_exists('elvado_assistant_admin_view')){$site[$section]=$clean;try{elvado_publish($site,$siteFile,$genDir,$root);}catch(Throwable $e){elvado_json(['status'=>'error','message'=>'Dateispeicherung fehlgeschlagen: '.$e->getMessage()],500);}elvado_json(['status'=>'ok','value'=>elvado_assistant_admin_view($clean),'rev'=>elvado_section_rev(elvado_ensure_site_defaults(elvado_read_json($siteFile,[])),$section),'file'=>'cms/data/site.json','published'=>true]);}if($clean===null)elvado_json(['status'=>'error','message'=>'Ungültige Daten'],400);$prevSection=$site[$section]??null;$site[$section]=$clean;
    if($section==='pages'){$u0=elvado_auth(false);elvado_page_revisions_record($dataDir,$prevSection,$clean,(string)($u0['display_name']??$u0['user']??''));}
    if($section==='widget_areas'||$section==='theme')$site=elvado_theme_remember_mods($site);
    try{elvado_publish($site,$siteFile,$genDir,$root);}catch(Throwable $e){elvado_json(['status'=>'error','message'=>'Dateispeicherung fehlgeschlagen: '.$e->getMessage()],500);}
    if(in_array($section,['apps','alexa'],true)){$chg=elvado_apps_change_summary($section,$prevSection,$clean);if($chg!=='')elvado_log_activity($activityLogFile,elvado_auth(false),$section.'_save',$chg);}
    elvado_json(['status'=>'ok','value'=>$clean,'rev'=>elvado_section_rev(elvado_ensure_site_defaults(elvado_read_json($siteFile,[])),$section),'file'=>'cms/data/site.json','published'=>true]);
}
if($action==='media_upload'){elvado_auth(false);elvado_json(['status'=>'ok','url'=>elvado_upload('content')]);}
if($action==='branding_upload'){
    elvado_auth(false);$kind=preg_replace('/[^a-z0-9_-]/','',strtolower((string)($_GET['kind']??'')));
    $allowed=['portal_logo','portal_icon','favicon','android_inapp_logo','android_startscreen','android_app_icon','windows_logo'];
    if(!in_array($kind,$allowed,true))elvado_json(['status'=>'error','message'=>'Unbekannter Asset-Typ'],400);
    $result=elvado_media_library_upload_variants(elvado_media_sizes($_POST['sizes']??'64,128,192,256,512,1024,1600'),max(45,min(100,(int)($_POST['quality']??90))));
    $item=$result['item'];$path='library/'.(string)($item['id']??'');$source=elvado_media_pick_variant($item,'auto',$kind);$sync=elvado_sync_branding_asset($kind,$source,$root);
    $site['branding'][$kind]=$sync['url'];$site['branding_media'][$kind]=['path'=>$path,'size'=>'auto','source_url'=>$source,'assigned_at'=>date(DATE_ATOM)];
    elvado_publish($site,$siteFile,$genDir,$root);
    elvado_json(['status'=>'ok','url'=>$sync['url'],'branding'=>$site['branding'],'branding_media'=>$site['branding_media'],'updated_files'=>$sync['files'],'warnings'=>array_values(array_merge($result['warnings']??[],$sync['warnings']??[]))]);
}
// ───────── Websites (mehrere eigenständige Homepages): nur Administratoren; die Liste liegt immer in der Hauptwebsite ─────────
if($action==='sites_list'){
    $au=elvado_auth(false);if(empty($au['superadmin']))elvado_json(['status'=>'error','message'=>'Nur Administratoren'],403);
    $main=elvado_read_json(elvado_sites_base().'/data/site.json',[]);
    elvado_json(['status'=>'ok','current'=>elvado_site_current(),'main'=>['name'=>(string)($main['portal']['site_name']??'Hauptwebsite')],'sites'=>elvado_sites_registry(true)]);
}
if($action==='sites_create'){
    $au=elvado_auth(false);if(empty($au['superadmin']))elvado_json(['status'=>'error','message'=>'Nur Administratoren'],403);
    $b=elvado_body();
    try{$r=elvado_site_create($b);}catch(InvalidArgumentException $e){elvado_json(['status'=>'error','message'=>$e->getMessage()],400);}catch(RuntimeException $e){elvado_json(['status'=>'error','message'=>$e->getMessage()],500);}
    elvado_log_activity($activityLogFile,$au,'site_create','Website „'.$r['site']['id'].'“ angelegt'.(!empty($b['copy_from'])?' (Kopie von '.elvado_site_id_clean((string)$b['copy_from']).')':''));
    elvado_json(['status'=>'ok','site'=>$r['site'],'copied'=>$r['copied'],'sites'=>elvado_sites_registry(true)]);
}
if($action==='sites_update'){
    $au=elvado_auth(false);if(empty($au['superadmin']))elvado_json(['status'=>'error','message'=>'Nur Administratoren'],403);
    $b=elvado_body();
    try{$s=elvado_site_update((string)($b['id']??''),$b);}catch(InvalidArgumentException $e){elvado_json(['status'=>'error','message'=>$e->getMessage()],400);}catch(RuntimeException $e){elvado_json(['status'=>'error','message'=>$e->getMessage()],500);}
    elvado_log_activity($activityLogFile,$au,'site_update','Website „'.$s['id'].'“ geändert');
    elvado_json(['status'=>'ok','site'=>$s,'sites'=>elvado_sites_registry(true)]);
}
if($action==='brand_create'){   // Neue Marke (optional als Kopie): nur Administratoren
    $au=elvado_auth(false);if(empty($au['superadmin']))elvado_json(['status'=>'error','message'=>'Nur Administratoren dürfen Marken anlegen'],403);
    $b=elvado_body();elvado_site_lock($dataDir);$site=elvado_ensure_site_defaults(elvado_read_json($siteFile,[]));
    try{$items=elvado_brand_create(elvado_brands_registry($site),$b);}catch(InvalidArgumentException $e){elvado_json(['status'=>'error','message'=>$e->getMessage()],400);}
    $reg=elvado_brands_registry($site);$reg['items']=$items;$site['brands']=elvado_brands_clean($reg);
    try{elvado_publish($site,$siteFile,$genDir,$root);}catch(Throwable $e){elvado_json(['status'=>'error','message'=>'Dateispeicherung fehlgeschlagen: '.$e->getMessage()],500);}
    $newId=(string)end($site['brands']['items'])['id'];
    elvado_log_activity($activityLogFile,$au,'brand_create','Marke „'.$newId.'“ angelegt'.(!empty($b['copy_from'])?' (Kopie von '.elvado_brand_id_clean((string)$b['copy_from']).')':''));
    elvado_json(['status'=>'ok','id'=>$newId,'brands'=>$site['brands']]);
}
if($action==='brand_domain_check'){   // DNS-Prüfung (nur Namensauflösung): Administratoren
    $au=elvado_auth(false);if(empty($au['superadmin']))elvado_json(['status'=>'error','message'=>'Nur Administratoren'],403);
    $b=elvado_body();elvado_json(['status'=>'ok']+elvado_brand_domain_dns((string)($b['domain']??($_GET['domain']??''))));
}
if($action==='brand_asset_assign'){
    elvado_auth(false);$b=elvado_body();$brandId=elvado_brand_id_clean((string)($b['brand_id']??''));$kind=preg_replace('/[^a-z_]/','',(string)($b['kind']??''));
    if(!in_array($kind,['logo','logo_dark','logo_light','favicon','touch_icon','og_image','social_image'],true))elvado_json(['status'=>'error','message'=>'Unbekannter Asset-Typ'],400);
    $reg=elvado_brands_registry($site);$idx=null;foreach($reg['items'] as $i=>$x)if($x['id']===$brandId)$idx=$i;
    if($idx===null)elvado_json(['status'=>'error','message'=>'Marke nicht gefunden'],404);
    $url='';
    if(($b['path']??'')!==''){
        $item=elvado_media_item_from_path((string)$b['path']);if(!$item)elvado_json(['status'=>'error','message'=>'Medium nicht gefunden'],404);
        $size=$b['size']??'auto';$url=elvado_media_pick_variant($item,$size,$kind==='logo'?'portal_logo':($kind==='favicon'?'favicon':'portal_icon'));
    } elseif(($b['url']??'')!=='') $url=elvado_brand_asset_clean((string)$b['url']);
    $reg['items'][$idx][$kind]=$url;$site['brands']=$reg;
    elvado_publish($site,$siteFile,$genDir,$root);
    elvado_log_activity($activityLogFile,elvado_auth(false),'brand_asset','Marke „'.$reg['items'][$idx]['name'].'“: '.$kind.($url!==''?' gesetzt':' entfernt'));
    elvado_json(['status'=>'ok','url'=>$url,'brands'=>$site['brands']]);
}
if($action==='branding_assign'){
    elvado_auth(false);$b=elvado_body();$kind=preg_replace('/[^a-z0-9_-]/','',strtolower((string)($b['kind']??'')));
    $allowed=['portal_logo','portal_icon','favicon','android_inapp_logo','android_startscreen','android_app_icon','windows_logo'];
    if(!in_array($kind,$allowed,true))elvado_json(['status'=>'error','message'=>'Unbekannter Branding-Typ'],400);
    $item=elvado_media_item_from_path((string)($b['path']??''));if(!$item)elvado_json(['status'=>'error','message'=>'Medium nicht gefunden'],404);
    $size=$b['size']??'auto';$url=elvado_media_pick_variant($item,$size,$kind);$sync=elvado_sync_branding_asset($kind,$url,$root);$site['branding'][$kind]=$sync['url'];$site['branding_media'][$kind]=['path'=>(string)($b['path']??''),'size'=>$size,'source_url'=>$url,'assigned_at'=>date(DATE_ATOM)];elvado_publish($site,$siteFile,$genDir,$root);
    elvado_json(['status'=>'ok','url'=>$sync['url'],'source_url'=>$url,'branding'=>$site['branding'],'branding_media'=>$site['branding_media'],'updated_files'=>$sync['files'],'warnings'=>$sync['warnings']]);
}
if($action==='admin_prefs_get'||$action==='admin_prefs_save'){   // persönliche Einstellungen der Verwaltung (Design), pro Benutzer, nicht pro Browser
    $u=elvado_auth(false);$pf=$dataDir.'/.prefs/admin.json';$all=is_file($pf)?json_decode((string)@file_get_contents($pf),true):[];$all=is_array($all)?$all:[];$name=(string)($u['user']??'');
    if($name==='')elvado_json(['status'=>'error','message'=>'Kein Benutzer'],400);
    if($action==='admin_prefs_save'){
        $b=elvado_body();$t=(string)($b['theme']??'');if(!in_array($t,['neon','light','dark','auto'],true))elvado_json(['status'=>'error','message'=>'Unbekanntes Design'],400);
        $all[$name]=['theme'=>$t];$dir=dirname($pf);if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))elvado_json(['status'=>'error','message'=>'Ordner nicht beschreibbar'],500);
        if(!is_file($dir.'/.htaccess'))@file_put_contents($dir.'/.htaccess',"Require all denied\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
        elvado_write_atomic($pf,json_encode($all,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    }
    elvado_json(['status'=>'ok','theme'=>(string)($all[$name]['theme']??'')]);
}
if($action==='media_alt_save'){elvado_auth(false);$b=elvado_body();try{elvado_json(['status'=>'ok']+elvado_media_alt_save((string)($b['id']??''),(string)($b['alt']??'')));}catch(RuntimeException $e){elvado_json(['status'=>'error','message'=>$e->getMessage()],$e->getCode()>=400?$e->getCode():400);}}
if($action==='media_library_list'){elvado_auth(false);elvado_json(['status'=>'ok','items'=>elvado_media_library_items($site)]);}
if($action==='media_library_upload'){elvado_auth(false);$sizes=elvado_media_sizes($_POST['sizes']??'64,128,192,256,512,1024,1600');$quality=max(45,min(100,(int)($_POST['quality']??86)));$result=elvado_media_library_upload_variants($sizes,$quality);elvado_json(['status'=>'ok']+$result);}
if($action==='media_library_delete'){
    elvado_auth(false);$b=elvado_body();$rel=elvado_safe_media_rel((string)($b['path']??''));
    if($rel===''||!str_starts_with($rel,'library/'))elvado_json(['status'=>'error','message'=>'Nur Medien aus der Bibliothek können hier gelöscht werden'],400);
    foreach((array)($site['branding_media']??[]) as $kind=>$ref){
        if((string)($ref['path']??'')===$rel)elvado_json(['status'=>'error','message'=>'Medium wird noch als '.$kind.' verwendet. Erst im Branding ein anderes Medium zuweisen.'],409);
    }
    $target=elvado_site_dir('media').'/'.$rel;
    if(is_dir($target)){foreach(glob($target.'/*')?:[] as $x)if(is_file($x))@unlink($x);if(!@rmdir($target))elvado_json(['status'=>'error','message'=>'Mediengruppe konnte nicht gelöscht werden'],500);}
    elseif(is_file($target)){if(!@unlink($target))elvado_json(['status'=>'error','message'=>'Datei konnte nicht gelöscht werden'],500);}
    else elvado_json(['status'=>'error','message'=>'Medium nicht gefunden'],404);
    elvado_json(['status'=>'ok']);
}
// KI-Gateway (EvoLink, OpenAI, Anthropic, Google, OpenRouter, DeepSeek) und Lovable/GitHub-Anbindung (Menü „KI & Lovable“): Dienste in cms/src/ (Namensraum Elvado\)
if(str_starts_with($action,'ai_')||str_starts_with($action,'lovable_')||$action==='core_posts_mirror'){
    require_once __DIR__.'/src/autoload.php';
    $kUser=in_array($action,['ai_status','ai_generate','ai_alt_suggest'],true)?elvado_auth(false):elvado_auth(true);$kB=elvado_body();
    $kDb=function() use($dataDir){ $db=\Elvado\Database\DatabaseConnection::fromCmsSettings($dataDir);$db->migrateCore();return $db; };
    try{
        if(str_starts_with($action,'ai_')){
            $aiCfg=\Elvado\Ai\AiGatewayConfig::load($dataDir,$site);
            if($action==='ai_status')elvado_json(['status'=>'ok','providers'=>(new \Elvado\Ai\AiGatewayService($aiCfg))->usableProviders(),'tasks'=>\Elvado\Ai\AiGatewayService::TASKS,'default'=>$aiCfg->defaultProvider()]);
            if($action==='ai_config_get')elvado_json(['status'=>'ok','config'=>$aiCfg->adminView(),'tasks'=>\Elvado\Ai\AiGatewayService::TASKS]);
            if($action==='ai_config_save'){ $aiCfg->save(is_array($kB['config']??null)?$kB['config']:[]);elvado_log_activity($activityLogFile,$kUser,'ai_config','KI-Gateway: Einstellungen gespeichert');$npMsg='';if(!elvado_demo_enabled()){$npMsg=elvado_np_ai_autoactivate((bool)(new \Elvado\Ai\AiGatewayService($aiCfg))->usableProviders());}elvado_json(['status'=>'ok','config'=>$aiCfg->adminView(),'plugin_message'=>$npMsg]); }
            if($action==='ai_migrate_legacy'){   // Schlüssel, die früher im KI-Assistenten eingetragen wurden, in die KI-Zentrale übernehmen und dort entfernen
                $moved=$aiCfg->migrateAssistantKeys();$cleared=0;$sa=(array)($site['assistant']??[]);
                foreach((array)($sa['providers']??[]) as $i=>$p){if(is_array($p)&&trim((string)($p['api_key']??''))!==''&&$aiCfg->ownKey(\Elvado\Ai\AiGatewayConfig::centralId(strtolower((string)($p['id']??''))))!==''){$sa['providers'][$i]['api_key']='';$cleared++;}}
                if($cleared){$site['assistant']=function_exists('elvado_assistant_clean')?elvado_assistant_clean($sa):$sa;try{elvado_publish($site,$siteFile,$genDir,$root);}catch(Throwable $e){elvado_json(['status'=>'error','message'=>'Dateispeicherung fehlgeschlagen: '.$e->getMessage()],500);}}
                elvado_log_activity($activityLogFile,$kUser,'ai_config','KI-Zentrale: '.count($moved).' Schlüssel aus dem KI-Assistenten übernommen');
                elvado_json(['status'=>'ok','moved'=>$moved,'cleared'=>$cleared,'config'=>\Elvado\Ai\AiGatewayConfig::load($dataDir,(array)$site)->adminView()]);
            }
            if($action==='ai_test'){   // Verbindungstest: eine winzige Anfrage an den gewählten Anbieter
                $pid=(string)($kB['provider']??'');$t0=microtime(true);
                try{ $r=(new \Elvado\Ai\AiGatewayService($aiCfg))->generate(['provider'=>$pid,'task'=>'text','prompt'=>'Antworte nur mit dem Wort: OK','max_tokens'=>64,'user'=>'']);elvado_json(['status'=>'ok','ok'=>true,'ms'=>(int)round((microtime(true)-$t0)*1000),'model'=>$r->model,'text'=>mb_substr($r->text,0,80)]); }
                catch(\Elvado\Ai\AiGatewayException $e){ elvado_json(['status'=>'ok','ok'=>false,'ms'=>(int)round((microtime(true)-$t0)*1000),'error'=>$e->getMessage()]); }
            }
            if($action==='ai_models'){   // Modellliste live beim Anbieter (OpenAI-kompatibel, Gemini, Claude); sonst die Vorschläge des Katalogs
                $pid=(string)($kB['provider']??'');$cat=$aiCfg->catalog();if(!isset($cat[$pid]))elvado_json(['status'=>'error','message'=>'Unbekannter Anbieter'],400);
                $def=$cat[$pid];$fallback=array_map(fn($m)=>['id'=>$m,'free'=>null,'ctx'=>0],(array)$def['models']);
                $k=$aiCfg->apiKey($pid);if(($k===''&&$def['needs_key'])||!in_array($def['kind'],['openai','gemini','anthropic'],true))elvado_json(['status'=>'ok','ok'=>true,'models'=>$fallback,'source'=>'catalog']);
                $base=rtrim($aiCfg->baseUrl($pid),'/');$opt=['timeout'=>12,'max_bytes'=>2000000,'allow_local'=>!empty($def['custom'])];$list=[];
                if($def['kind']==='gemini'){
                    $resp=\Elvado\Support\Http::request('GET',$base.'/models?pageSize=200',['x-goog-api-key: '.$k],null,$opt);
                    foreach($resp->ok()?(array)($resp->json()['models']??[]):[] as $m){if(!is_array($m)||!in_array('generateContent',(array)($m['supportedGenerationMethods']??[]),true))continue;$id=preg_replace('~^models/~','',(string)($m['name']??''));if($id===''||preg_match('/embed|aqa|imagen|veo|tts|image|live|audio/i',$id))continue;$list[]=['id'=>$id,'free'=>null,'ctx'=>(int)($m['inputTokenLimit']??0)];}
                    usort($list,fn($x,$y)=>[!str_contains($x['id'],'latest'),$x['id']]<=>[!str_contains($y['id'],'latest'),$y['id']]);
                }elseif($def['kind']==='anthropic'){
                    $resp=\Elvado\Support\Http::request('GET',$base.'/models?limit=100',['x-api-key: '.$k,'anthropic-version: 2023-06-01'],null,$opt);
                    foreach($resp->ok()?(array)($resp->json()['data']??[]):[] as $m)if(is_array($m)&&preg_match('~^[\w.:/@+-]{1,120}$~',(string)($m['id']??'')))$list[]=['id'=>(string)$m['id'],'free'=>null,'ctx'=>0];
                }else{
                    $resp=\Elvado\Support\Http::request('GET',$base.'/models',$k!==''?['Authorization: Bearer '.$k]:[],null,$opt);
                    $list=$resp->ok()&&function_exists('elvado_assistant_parse_models')?elvado_assistant_parse_models($resp->json()):[];
                }
                elvado_json(['status'=>'ok','ok'=>true,'models'=>$list?:$fallback,'source'=>$list?'provider':'catalog']);
            }
            if($action==='ai_media_providers')elvado_json(['status'=>'ok','providers'=>(new \Elvado\Ai\MediaGenerator($aiCfg))->providers(),'ratios'=>\Elvado\Ai\MediaGenerator::RATIOS]);
            if($action==='ai_media_start'||$action==='ai_media_status'||$action==='ai_media_save'){   // KI-Bilder und -Videos (EvoLink, fal.ai, OpenAI): starten, abfragen, in die Mediathek übernehmen
                @set_time_limit(180);$mg=new \Elvado\Ai\MediaGenerator($aiCfg);
                if($action==='ai_media_start'){
                    $rl=new \Elvado\Support\RateLimiter($dataDir.'/.ai/ratelimit');if(!$rl->hit('aimedia:'.(string)($kUser['user']??'anon'),30,3600))elvado_json(['status'=>'error','message'=>'Zu viele Medien-Aufträge in der letzten Stunde.'],429);
                    $r=$mg->start((string)($kB['provider']??''),(string)($kB['kind']??''),(string)($kB['model']??''),(string)($kB['prompt']??''),(string)($kB['ratio']??'16:9'),(string)($kB['image_url']??''));
                    elvado_log_activity($activityLogFile,$kUser,'ai_media','KI-Medien: '.(string)($kB['kind']??'').' gestartet ('.(string)($kB['provider']??'').')');elvado_json(['status'=>'ok']+$r);
                }
                if($action==='ai_media_status')elvado_json(['status'=>'ok']+$mg->status((string)($kB['job']??'')));
                require_once __DIR__.'/lib/media.php';require_once __DIR__.'/lib/stockmedia.php';
                $kind=(string)($kB['kind']??'');$url=(string)($kB['url']??'');$b64=(string)($kB['b64']??'');$prompt=trim((string)($kB['prompt']??''));
                $tmp=tempnam(sys_get_temp_dir(),'aimg');if($tmp===false)elvado_json(['status'=>'error','message'=>'Temporäre Datei nicht möglich.'],500);
                try{
                    if($b64!==''){$raw=base64_decode($b64,true);if($raw===false||strlen($raw)>15728640||file_put_contents($tmp,$raw)===false)elvado_json(['status'=>'error','message'=>'Bilddaten ungültig.'],400);}
                    elseif(!preg_match('~^https://[^\s"\'<>]{4,1500}$~',$url)||!elvado_stock_download($url,$tmp,$kind==='video'?83886080:15728640))elvado_json(['status'=>'error','message'=>'Das Ergebnis konnte nicht heruntergeladen werden (zu groß, abgelaufen oder nicht erreichbar).'],502);
                    $base=trim(substr(preg_replace('/[^a-z0-9]+/','-',mb_strtolower($prompt!==''?$prompt:'ki-medien')),0,50),'-');
                    if($kind==='video'){
                        $head=(string)file_get_contents($tmp,false,null,0,16);$ext=str_contains(substr($head,4,4),'ftyp')?'mp4':(str_starts_with($head,"\x1A\x45\xDF\xA3")?'webm':'');
                        if($ext==='')elvado_json(['status'=>'error','message'=>'Das Ergebnis ist keine unterstützte Videodatei (MP4 oder WebM).'],400);
                        $dir=elvado_media_dir().'/videos';if(!is_dir($dir)&&!@mkdir($dir,0755,true))elvado_json(['status'=>'error','message'=>'Medienordner nicht beschreibbar.'],500);
                        $fn=date('Ymd_His').'_'.bin2hex(random_bytes(4)).'-'.($base!==''?$base:'video').'.'.$ext;if(!@rename($tmp,$dir.'/'.$fn)){if(!@copy($tmp,$dir.'/'.$fn))elvado_json(['status'=>'error','message'=>'Video konnte nicht gespeichert werden.'],500);}@chmod($dir.'/'.$fn,0644);
                        elvado_log_activity($activityLogFile,$kUser,'ai_media','KI-Medien: Video gespeichert');elvado_json(['status'=>'ok','kind'=>'video','url'=>'/cms/media/videos/'.$fn]);
                    }
                    $credit=['provider'=>'ai','provider_name'=>'KI-generiert','title'=>mb_substr($prompt,0,160),'author'=>'','author_url'=>'','source_url'=>'','license'=>'','license_url'=>'','attribution_required'=>false,'text'=>'KI-generiertes Bild','ai'=>true];
                    $r=elvado_media_library_store(['tmp_name'=>$tmp,'size'=>(int)@filesize($tmp),'name'=>($base!==''?$base:'ki-bild').'-ki'],elvado_media_sizes('64,128,192,256,512,1024,1600'),90,'rename',$credit);
                    elvado_log_activity($activityLogFile,$kUser,'ai_media','KI-Medien: Bild in die Mediathek übernommen');elvado_json(['status'=>'ok','kind'=>'image']+$r);
                }catch(RuntimeException $e){elvado_json(['status'=>'error','message'=>$e->getMessage()],$e->getCode()>=400?(int)$e->getCode():400);}
                finally{if(is_file($tmp))@unlink($tmp);}
            }
            if($action==='ai_site_plan'){   // KI-Website-Generator: Entwurf aus einer Beschreibung (wird nicht gespeichert)
                @set_time_limit(200);
                $lg=null;try{ $lg=new \Elvado\Repository\AiLogRepository($kDb()); }catch(Throwable $e){}
                $svc=new \Elvado\Ai\AiGatewayService($aiCfg,$lg,new \Elvado\Support\RateLimiter($dataDir.'/.ai/ratelimit'));
                $slugs=[];foreach((array)($site['pages']??[]) as $pg)if(is_array($pg))$slugs[]=(string)($pg['slug']??'');
                $plan=(new \Elvado\Ai\SiteBuilder($svc))->plan(['description'=>(string)($kB['description']??''),'name'=>(string)($kB['name']??''),'tone'=>(string)($kB['tone']??''),'language'=>(string)($kB['language']??''),
                    'parts'=>['home'=>!isset($kB['parts']['home'])||!empty($kB['parts']['home']),'pages'=>!isset($kB['parts']['pages'])||!empty($kB['parts']['pages']),'posts'=>!isset($kB['parts']['posts'])||!empty($kB['parts']['posts'])],
                    'existing_slugs'=>$slugs,'user'=>(string)($kUser['user']??''),'provider'=>(string)($kB['provider']??'')]);
                elvado_log_activity($activityLogFile,$kUser,'ai_site_plan','KI-Website-Generator: Entwurf erstellt ('.count($plan['pages']).' Seiten, '.count($plan['posts']).' Beiträge)');
                elvado_json(['status'=>'ok','plan'=>$plan]);
            }
            if($action==='ai_dev_plan'||$action==='ai_dev_install'||$action==='ai_dev_check'){   // KI-Entwickler: Plugins, Widgets, Themes (nur Superadmin, nie in der Demo; Installation immer inaktiv)
                if(empty($kUser['superadmin']))elvado_json(['status'=>'error','message'=>'Der KI-Entwickler ist nur für den Superadmin verfügbar.'],403);
                $plDir=__DIR__.'/wp-content/plugins';$thDir=__DIR__.'/wp-content/themes';
                if($action==='ai_dev_check'){$c=\Elvado\Ai\CodeBuilder::check(is_array($kB['plan']??null)?$kB['plan']:[]);elvado_json(['status'=>'ok','errors'=>$c['errors'],'warnings'=>$c['warnings'],'ok'=>$c['ok']]);}
                if($action==='ai_dev_install'){
                    $plan=is_array($kB['plan']??null)?$kB['plan']:[];
                    $res=\Elvado\Ai\CodeBuilder::install($plan,$plDir,$thDir,(string)($kUser['user']??''),!empty($kB['confirm_warnings']));
                    elvado_log_activity($activityLogFile,$kUser,'ai_dev_install','KI-Entwickler: '.\Elvado\Ai\CodeBuilder::KINDS[$res['kind']].' „'.$res['slug'].'“ installiert (inaktiv, '.$res['files'].' Dateien)');
                    elvado_json(['status'=>'ok']+$res+['activate'=>$res['kind']==='theme'?'wp_theme_activate':'wp_plugin_activate','plugin_file'=>$res['kind']==='theme'?'':$res['slug'].'/'.$res['slug'].'.php']);
                }
                @set_time_limit(240);
                $ex=[];foreach([$plDir,$thDir,__DIR__.'/themes'] as $d)foreach(glob($d.'/*',GLOB_ONLYDIR)?:[] as $x)$ex[]=basename($x);
                $lg=null;try{ $lg=new \Elvado\Repository\AiLogRepository($kDb()); }catch(Throwable $e){}
                $svc=new \Elvado\Ai\AiGatewayService($aiCfg,$lg,new \Elvado\Support\RateLimiter($dataDir.'/.ai/ratelimit'));
                $plan=(new \Elvado\Ai\CodeBuilder($svc))->plan(['kind'=>(string)($kB['kind']??''),'prompt'=>(string)($kB['prompt']??''),'base'=>(string)($kB['base']??'child'),'previous'=>is_array($kB['previous']??null)?$kB['previous']:[],
                    'instruction'=>(string)($kB['instruction']??''),'slug'=>(string)($kB['slug']??''),'existing'=>$ex,'user'=>(string)($kUser['user']??''),'provider'=>(string)($kB['provider']??''),'model'=>(string)($kB['model']??'')]);
                elvado_log_activity($activityLogFile,$kUser,'ai_dev_plan','KI-Entwickler: Entwurf für '.\Elvado\Ai\CodeBuilder::KINDS[$plan['kind']].' „'.$plan['slug'].'“ erstellt');
                elvado_json(['status'=>'ok','plan'=>$plan]);
            }
            if($action==='ai_alt_suggest'){   // Alt-Text-Vorschlag für ein Bild der Mediathek (wird nicht gespeichert)
                require_once __DIR__.'/lib/media.php';
                try{ $src=elvado_media_alt_source((string)($kB['id']??'')); }catch(RuntimeException $e){ elvado_json(['status'=>'error','message'=>$e->getMessage()],$e->getCode()>=400?$e->getCode():400); }
                $lg=null;try{ $lg=new \Elvado\Repository\AiLogRepository($kDb()); }catch(Throwable $e){}
                $svc=new \Elvado\Ai\AiGatewayService($aiCfg,$lg,new \Elvado\Support\RateLimiter($dataDir.'/.ai/ratelimit'));
                $r=(new \Elvado\Ai\AltTexter($svc))->suggest($src['meta'],$src['file'],(string)($kUser['user']??''),(string)($kB['context']??''));
                elvado_json(['status'=>'ok']+$r);
            }
            if($action==='ai_site_images'){   // Website-Generator: passende freie Bilder laden (Suche, Übernahme in die Mediathek, Alt-Text)
                @set_time_limit(240);require_once __DIR__.'/lib/media.php';require_once __DIR__.'/lib/stockmedia.php';
                $qs=[];foreach(array_slice(is_array($kB['queries']??null)?$kB['queries']:[],0,8) as $x)if(is_array($x))$qs[]=['key'=>(string)($x['key']??''),'q'=>(string)($x['q']??''),'orient'=>(string)($x['orient']??''),'width'=>(int)($x['width']??1600)];
                $usable=false;foreach(['pixabay','pexels','unsplash','openverse','wikimedia'] as $sp)if(elvado_stock_usable($dataDir,$sp)!==null){$usable=true;break;}
                if(!$usable)elvado_json(['status'=>'ok','usable'=>false,'images'=>[]]);
                $lg=null;try{ $lg=new \Elvado\Repository\AiLogRepository($kDb()); }catch(Throwable $e){}
                $svc=new \Elvado\Ai\AiGatewayService($aiCfg,$lg,new \Elvado\Support\RateLimiter($dataDir.'/.ai/ratelimit'));$alt=new \Elvado\Ai\AltTexter($svc);$uname=(string)($kUser['user']??'');
                $imgs=elvado_stock_fetch_for_plan($dataDir,$qs,function(array $meta,string $file,string $q,string $title) use($alt,$uname){ $meta['credit']=(array)($meta['credit']??[])+['title'=>$title];return $alt->suggest($meta,$file,$uname,$q)['alt']; });
                elvado_log_activity($activityLogFile,$kUser,'ai_site_images','KI-Website-Generator: '.count(array_filter($imgs,fn($i)=>$i['ok'])).' von '.count($imgs).' Bildern geladen');
                elvado_json(['status'=>'ok','usable'=>true,'images'=>$imgs]);
            }
            if($action==='ai_logs'){ $lg=null;try{ $lg=new \Elvado\Repository\AiLogRepository($kDb()); }catch(Throwable $e){} elvado_json(['status'=>'ok','available'=>$lg!==null,'summary'=>$lg?$lg->summary(30):[],'recent'=>$lg?$lg->recent(30):[]]); }
            if($action==='ai_generate'){
                $lg=null;try{ $lg=new \Elvado\Repository\AiLogRepository($kDb()); }catch(Throwable $e){}   // Protokoll ist optional (ohne Datenbank/SQLite-Erweiterung entfällt es)
                $svc=new \Elvado\Ai\AiGatewayService($aiCfg,$lg,new \Elvado\Support\RateLimiter($dataDir.'/.ai/ratelimit'));
                $res=$svc->generate(['provider'=>(string)($kB['provider']??''),'purpose'=>(string)($kB['purpose']??''),'task'=>(string)($kB['task']??'text'),'prompt'=>(string)($kB['prompt']??''),'text'=>(string)($kB['text']??''),'language'=>(string)($kB['language']??''),'model'=>(string)($kB['model']??''),
                    'temperature'=>isset($kB['temperature'])?(float)$kB['temperature']:null,'max_tokens'=>isset($kB['max_tokens'])?(int)$kB['max_tokens']:1200,'user'=>(string)($kUser['user']??'')]+[]);
                elvado_json(['status'=>'ok']+$res->toArray());
            }
        }
        if($action==='core_posts_mirror'){ $n=(new \Elvado\Repository\PostRepository($kDb()))->mirror(elvado_read_json($newsFile,[]));elvado_log_activity($activityLogFile,$kUser,'core_posts','Beiträge in die Kern-Datenbank gespiegelt ('.$n.')');elvado_json(['status'=>'ok','posts'=>$n]); }
        if(str_starts_with($action,'lovable_')){
            $lvSet=\Elvado\Lovable\LovableSettings::load($dataDir);$origin=elvado_site_origin($site);
            $lvSync=new \Elvado\GitHub\GitHubSyncService($lvSet,__DIR__.'/frontend/lovable',$dataDir);
            $lvWidgets=function() use($kDb){ try{ return (new \Elvado\Repository\LovableWidgetRepository($kDb()))->all(); }catch(Throwable $e){ return null; } };
            $lvView=fn()=>['settings'=>$lvSet->adminView(),'widgets'=>$lvWidgets(),'sync'=>$lvSync->status(),'webhook_url'=>$origin.'/cms/github-webhook.php','provider_url'=>$origin.'/cms/api-lovable-provider.php','origin'=>$origin];
            if($action==='lovable_get')elvado_json(['status'=>'ok']+$lvView());
            if($action==='lovable_save'){ $lvSet->save(is_array($kB['settings']??null)?$kB['settings']:[]);elvado_log_activity($activityLogFile,$kUser,'lovable_save','Lovable/GitHub: Einstellungen gespeichert');$lvSet=\Elvado\Lovable\LovableSettings::load($dataDir);elvado_json(['status'=>'ok']+$lvView()); }
            if($action==='lovable_secret_rotate'){ $sec=$lvSet->rotateSecret();elvado_log_activity($activityLogFile,$kUser,'lovable_secret','GitHub-Webhook-Geheimnis erneuert');elvado_json(['status'=>'ok','secret'=>$sec]+$lvView()); }   // Klartext nur in dieser Antwort
            if($action==='lovable_widget_save'){ $w=(new \Elvado\Repository\LovableWidgetRepository($kDb()))->save(is_array($kB['widget']??null)?$kB['widget']:[]);elvado_log_activity($activityLogFile,$kUser,'lovable_widget','Lovable-Widget „'.$w['component_name'].'“ gespeichert');elvado_json(['status'=>'ok','widget'=>$w,'widgets'=>$lvWidgets()]); }
            if($action==='lovable_widget_delete'){ (new \Elvado\Repository\LovableWidgetRepository($kDb()))->delete((int)($kB['id']??0));elvado_json(['status'=>'ok','widgets'=>$lvWidgets()]); }
            if($action==='lovable_sync'){ @set_time_limit(180);$r=$lvSync->sync(!empty($kB['force']));elvado_log_activity($activityLogFile,$kUser,'lovable_sync','GitHub-Synchronisation: '.$r['status'].' ('.substr($r['sha'],0,7).')');elvado_json(['status'=>'ok','result'=>$r]+$lvView()); }
        }
    }catch(\Elvado\Ai\AiGatewayException $e){ elvado_json(['status'=>'error','message'=>$e->getMessage()],$e->httpStatus());
    }catch(\Elvado\GitHub\GitHubSyncException|\Elvado\Database\DatabaseException|\RuntimeException $e){ elvado_json(['status'=>'error','message'=>$e->getMessage()],422);
    }catch(Throwable $e){ elvado_json(['status'=>'error','message'=>'Unerwarteter Fehler ('.get_class($e).')'],500); }
    elvado_json(['status'=>'error','message'=>'Unbekannte Aktion'],400);
}
// CMS-Aktualisierung über GitHub (Menü System → Version & Update): Suche, Einspielen, Downgrade/Rückschritt, Einstellungen. Dienst: cms/src/Update/
if(str_starts_with($action,'update_')){
    require_once __DIR__.'/src/autoload.php';
    $uUser=$action==='update_badge'?elvado_auth(false):elvado_auth(true);$uB=elvado_body();
    try{
        $uSvc=\Elvado\Update\UpdateService::forCms(__DIR__,$dataDir);
        $uSvc->settings()->rememberBaseUrl(((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https'?'https':'http').'://'.(string)($_SERVER['HTTP_HOST']??'').rtrim(str_replace('\\','/',dirname((string)($_SERVER['SCRIPT_NAME']??''))),'/'));
        if($action==='update_badge'){ elvado_json(['status'=>'ok']+$uSvc->badge()); }
        $uFull=static fn(array $s)=>$s+['rescue_token'=>(function_exists('elvado_demo_enabled')&&elvado_demo_enabled())?'':$uSvc->rescueToken(),'webhook'=>'cms/update-webhook.php'];
        if($action==='update_status'){ $uSvc->tick();elvado_json(['status'=>'ok']+$uFull($uSvc->status())); }
        if($action==='update_check'){ elvado_json(['status'=>'ok']+$uFull($uSvc->check())); }
        if($action==='update_versions'){ elvado_json(['status'=>'ok','versions'=>$uSvc->versions()]); }
        if($action==='update_config_save'){ $uSvc->settings()->save(is_array($uB['settings']??null)?$uB['settings']:[]);elvado_log_activity($activityLogFile,$uUser,'update_config','CMS-Update: Einstellungen gespeichert');elvado_json(['status'=>'ok']+$uFull($uSvc->status())); }
        if($action==='update_secret_rotate'){ $sec=$uSvc->settings()->rotateSecret();elvado_log_activity($activityLogFile,$uUser,'update_secret','CMS-Update: Webhook-Geheimnis erneuert');elvado_json(['status'=>'ok','secret'=>$sec]+$uFull($uSvc->status())); }
        if($action==='update_confirm'){ $uSvc->confirm();elvado_json(['status'=>'ok']+$uFull($uSvc->status())); }
        if($action==='update_apply'){
            $uRes=$uSvc->apply((string)($uB['ref']??''),!empty($uB['downgrade']));
            elvado_log_activity($activityLogFile,$uUser,'update_apply','CMS-Update: '.$uRes['from'].' → '.$uRes['to']);
            elvado_json(['status'=>'ok','result'=>$uRes]+$uFull($uSvc->status()));
        }
        if($action==='update_rollback'){
            $uRes=$uSvc->rollback((string)($uB['snapshot']??''));
            elvado_log_activity($activityLogFile,$uUser,'update_rollback','CMS-Update: zurückgesetzt '.$uRes['from'].' → '.$uRes['to']);
            elvado_json(['status'=>'ok','result'=>$uRes]+$uFull($uSvc->status()));
        }
    }catch(\RuntimeException $e){ elvado_json(['status'=>'error','message'=>$e->getMessage()],422);
    }catch(Throwable $e){ elvado_json(['status'=>'error','message'=>'Unerwarteter Fehler ('.get_class($e).')'],500); }
    elvado_json(['status'=>'error','message'=>'Unbekannte Aktion'],400);
}
// Freie Bildquellen (Pixabay, Pexels, Unsplash, Openverse, Wikimedia Commons) für die Mediathek: Status, Einstellungen (Schlüssel nur schreibend), Suche, Übernahme
if(str_starts_with($action,'stock_')){
    require_once __DIR__.'/lib/stockmedia.php';
    $skUser=in_array($action,['stock_config_save'],true)?elvado_auth(true):elvado_auth(false);$skB=elvado_body();
    if($action==='stock_status')elvado_json(['status'=>'ok','providers'=>elvado_stock_status($dataDir),'admin'=>!empty($skUser['superadmin'])]);
    if($action==='stock_config_save'){
        try{ elvado_stock_save($dataDir,['keys'=>is_array($skB['keys']??null)?$skB['keys']:[],'enabled'=>is_array($skB['enabled']??null)?$skB['enabled']:[]]); }
        catch(InvalidArgumentException $e){ elvado_json(['status'=>'error','message'=>$e->getMessage()],400); }
        catch(Throwable $e){ elvado_json(['status'=>'error','message'=>'Einstellungen konnten nicht gespeichert werden'],500); }
        elvado_log_activity($activityLogFile,$skUser,'stock_config','Freie Bildquellen: Einstellungen gespeichert');
        elvado_json(['status'=>'ok','providers'=>elvado_stock_status($dataDir)]);
    }
    if($action==='stock_search'){
        try{ $r=elvado_stock_search($dataDir,(string)($skB['provider']??''),(string)($skB['q']??''),(int)($skB['page']??1),(string)($skB['orientation']??'')); }
        catch(Throwable $e){ elvado_json(['status'=>'error','message'=>$e->getMessage()],422); }
        elvado_json(['status'=>'ok']+$r);
    }
    if($action==='stock_import'){
        try{ $r=elvado_stock_import($dataDir,(string)($skB['provider']??''),(string)($skB['id']??'')); }
        catch(Throwable $e){ elvado_json(['status'=>'error','message'=>$e->getMessage()],$e->getCode()>=400&&$e->getCode()<600?$e->getCode():422); }
        elvado_log_activity($activityLogFile,$skUser,'stock_import','Freies Bild übernommen ('.(string)($skB['provider']??'').' '.(string)($skB['id']??'').')');
        elvado_json(['status'=>'ok']+$r);
    }
    elvado_json(['status'=>'error','message'=>'Unbekannte Aktion'],400);
}
// Theme-Konfiguration (Menüs wie „Band“, nur bei aktivem Theme; Schema je Theme in cms/lib/themeconf.php): state | get | save
if(str_starts_with($action,'themeconf_')){
    require_once __DIR__.'/lib/themeconf.php';
    if($action==='themeconf_state'){ elvado_auth(false);elvado_json(['status'=>'ok','configs'=>elvado_tc_state($dataDir)]); }
    $tcUser=elvado_auth(true);$tcB=elvado_body();$tcId=preg_replace('/[^a-z0-9_-]/','',(string)($_GET['id']??$tcB['id']??''));
    if(!elvado_tc_entry($tcId))elvado_json(['status'=>'error','message'=>'Unbekannte Konfiguration'],404);
    if($action==='themeconf_get')elvado_json(['status'=>'ok','schema'=>elvado_tc_public_schema($tcId),'config'=>elvado_tc_load($dataDir,$tcId),'active'=>elvado_tc_theme_active($dataDir,elvado_tc_entry($tcId)['theme'])]);
    if($action==='themeconf_save'){
        if(!is_array($tcB['config']??null))elvado_json(['status'=>'error','message'=>'Konfiguration fehlt'],400);
        try{ $c=elvado_tc_save($dataDir,$tcId,$tcB['config']); }catch(Throwable $e){ elvado_json(['status'=>'error','message'=>$e->getMessage()],500); }
        elvado_log_activity($activityLogFile,$tcUser,'themeconf_save','Theme-Einstellungen „'.$tcId.'“ gespeichert');
        elvado_json(['status'=>'ok','config'=>$c]);
    }
    elvado_json(['status'=>'error','message'=>'Unbekannte Aktion'],400);
}
// WordPress-Plugins (PHP): Laufzeit unter cms/wp, Plugins unter cms/wp-content/plugins. Nur Superadmins.
if(str_starts_with($action,'wp_')){
    $wpUser=elvado_auth(true);
    $GLOBALS['ELVADO_SITE']=$site;
    // Sandbox: abgetrennter Spielraum zwischen Live und Deployment (eigene Optionen und Themes, siehe wp/sandbox.php)
    require_once __DIR__.'/wp/sandbox.php';
    if(str_starts_with($action,'wp_sandbox')){
        $sb=elvado_body();$sbOut=function(array $extra=[]) { $m=elvado_sbx_meta();return ['status'=>'ok','exists'=>elvado_sbx_exists(),'created'=>(string)($m['created']??''),'last_publish'=>(string)($m['last_publish']??''),'url'=>elvado_sbx_exists()?elvado_sbx_url():'','diff'=>elvado_sbx_exists()?elvado_sbx_diff():null,'backups'=>elvado_sbx_backups()]+$extra; };
        if($action==='wp_sandbox')elvado_json($sbOut());
        $fail=fn(string $m,int $c=422)=>elvado_json(['status'=>'error','message'=>$m],$c);
        if($action==='wp_sandbox_create'){ $e=elvado_sbx_create();if($e!==null)$fail($e);elvado_log_activity($activityLogFile,$wpUser,'wp_sandbox','Sandbox angelegt');elvado_json($sbOut()); }
        if($action==='wp_sandbox_reset'){ $e=elvado_sbx_reset();if($e!==null)$fail($e);elvado_log_activity($activityLogFile,$wpUser,'wp_sandbox','Sandbox auf den Live-Stand zurückgesetzt');elvado_json($sbOut()); }
        if($action==='wp_sandbox_rotate'){ $e=elvado_sbx_rotate();if($e!==null)$fail($e);elvado_log_activity($activityLogFile,$wpUser,'wp_sandbox','Sandbox-Link erneuert');elvado_json($sbOut()); }
        if($action==='wp_sandbox_delete'){ elvado_sbx_delete();elvado_log_activity($activityLogFile,$wpUser,'wp_sandbox','Sandbox gelöscht');elvado_json($sbOut()); }
        if($action==='wp_sandbox_publish'){ $r=elvado_sbx_publish();if(!$r['ok'])$fail($r['message']);elvado_log_activity($activityLogFile,$wpUser,'wp_sandbox','Sandbox live gestellt ('.$r['options'].' Einstellungen'.($r['themes']?', Themes: '.implode(', ',$r['themes']):'').')');elvado_json($sbOut(['published'=>$r])); }
        if($action==='wp_sandbox_rollback'){ $e=elvado_sbx_rollback((string)($sb['backup']??''));if($e!==null)$fail($e);elvado_log_activity($activityLogFile,$wpUser,'wp_sandbox','Live-Stand aus Sicherung '.(string)$sb['backup'].' wiederhergestellt');elvado_json($sbOut()); }
        $fail('Unbekannte Aktion',404);
    }
    if(!empty($_GET['sandbox'])){   // Theme-Verwaltung in der Sandbox statt auf der Live-Seite
        if(!in_array($action,['wp_themes','wp_theme_search','wp_theme_install','wp_theme_upload','wp_theme_activate','wp_theme_deactivate','wp_theme_delete','wp_theme_unhide','wp_theme_preview','wp_theme_customize','wp_theme_customize_draft','wp_theme_customize_save','wp_theme_customize_changeset'],true))elvado_json(['status'=>'error','message'=>'Diese Aktion gibt es in der Sandbox nicht.'],400);
        if(!elvado_sbx_exists())elvado_json(['status'=>'error','message'=>'Es gibt noch keine Sandbox.'],404);
        elvado_sbx_enter();
    }
    require_once __DIR__.'/wp/load.php';require_once __DIR__.'/wp/installer.php';require_once __DIR__.'/wp/coreassets.php';
    // Plugins registrieren ihre Hooks schon beim Laden abhängig von $_GET['page']: die gewünschte Seite vor dem Start bekanntmachen.
    if($action==='wp_admin_page'){
        $pre=elvado_body();$qs=[];parse_str((string)($pre['query']??''),$qs);if(strtoupper((string)($pre['method']??'GET'))==='POST'){ $pb=[];parse_str((string)($pre['body']??''),$pb);$qs=$pb+$qs; }
        $pg=(string)($pre['page']??'');if(str_contains($pg,'&')){ [$pg,$rest]=explode('&',$pg,2);$ex=[];parse_str($rest,$ex);$qs=$ex+$qs; }$qs['page']=$pg;$_GET=$qs;$GLOBALS['plugin_page']=$pg;$GLOBALS['pagenow']='admin.php';$_REQUEST=array_merge($_REQUEST,$qs);
    }
    // Customizer für WordPress-Themes: das gewählte Theme vor dem Start einschalten (Filter), damit seine functions.php/customize_register laufen
    $wpCz=in_array($action,['wp_theme_customize','wp_theme_customize_draft','wp_theme_customize_save','wp_theme_customize_changeset'],true);
    if($wpCz){
        require_once __DIR__.'/wp/customizer-api.php';$czSlug=(string)(elvado_body()['slug']??'');
        $czKnown=array_column(elvado_wpi_list_themes(),null,'slug');
        if(!isset($czKnown[$czSlug]))elvado_json(['status'=>'error','message'=>'Theme nicht gefunden'],404);
        if(!empty($czKnown[$czSlug]['error']))elvado_json(['status'=>'error','message'=>$czKnown[$czSlug]['error']],422);
        elvado_wpc_use_theme($czSlug);ob_start();
    }
    // Homepage-Baukasten (visueller Abschnitts-Editor): Layout des Themes „elvado-baukasten“ lesen/speichern (Option elvado_bk_layout)
    $wpBk=in_array($action,['wp_bk_get','wp_bk_save','wp_bk_draft','wp_bk_publish','wp_bk_discard','wp_bk_rollback','wp_bk_revisions'],true);
    if($wpBk){ require_once __DIR__.'/wp/customizer-api.php';if(!elvado_wpc_use_theme('elvado-baukasten'))elvado_json(['status'=>'error','message'=>'Das Theme „ElvadoPress Baukasten“ ist nicht installiert.'],404); }
    elvado_wp_boot(['user'=>['id'=>1,'login'=>(string)($wpUser['user']??'admin'),'name'=>(string)($wpUser['display_name']??''),'role'=>'administrator'],'admin'=>true,'theme'=>$wpCz||$wpBk||str_starts_with($action,'wp_admin_')]);
    if(str_starts_with($action,'wp_admin_'))elvado_wp_core_register();
    $b=elvado_body();
    $wpList=function() use($dataDir){
        $act=get_option_active_plugins();$errs=(array)get_option('elvado_wp_plugin_errors',[]);$out=[];
        foreach(get_plugins() as $file=>$d)$out[]=['file'=>$file,'name'=>$d['Name'],'version'=>$d['Version'],'author'=>strip_tags($d['Author']),'description'=>mb_substr(strip_tags($d['Description']),0,300),'uri'=>$d['PluginURI'],'requires_php'=>$d['RequiresPHP'],'active'=>in_array($file,$act,true),'error'=>(string)($errs[$file]??'')];
        return $out;
    };
    if($action==='wp_plugins')elvado_json(['status'=>'ok','plugins'=>$wpList(),'wp_version'=>ELVADO_WP_VERSION,'zip'=>class_exists('ZipArchive')]);
    if($action==='wp_plugin_search'){
        $r=elvado_wpi_search_plugins($dataDir,(string)($_GET['q']??''),(int)($_GET['page']??1));if(!$r['ok'])elvado_json(['status'=>'error','message'=>$r['message']],502);
        $have=array_map(fn($f)=>explode('/',$f)[0],array_keys(get_plugins()));foreach($r['items'] as &$it)$it['installed']=in_array($it['slug'],$have,true);unset($it);
        elvado_json(['status'=>'ok']+$r);
    }
    if($action==='wp_plugin_install'||$action==='wp_plugin_upload'){
        $zip='';
        try{
            if($action==='wp_plugin_install'){ $slug=(string)($b['slug']??'');$zip=elvado_wpi_download_plugin($slug);$res=elvado_wpi_install_plugin_zip($zip,$slug); }
            else{ if(empty($_FILES['file'])||!is_uploaded_file($_FILES['file']['tmp_name']))elvado_json(['status'=>'error','message'=>'Keine ZIP-Datei'],400);
                if((int)$_FILES['file']['size']>41943040)elvado_json(['status'=>'error','message'=>'Das ZIP ist größer als 40 MB'],400);
                $res=elvado_wpi_install_plugin_zip($_FILES['file']['tmp_name']); }
        }catch(Throwable $e){ if($zip!==''&&is_file($zip))@unlink($zip); elvado_json(['status'=>'error','message'=>$e->getMessage()],422); }
        if($zip!==''&&is_file($zip))@unlink($zip);
        try{ $pl=get_plugins();foreach($pl as $pf=>$pd)if(explode('/',$pf)[0]===$res['slug']){ elvado_wpi_fetch_translation('plugin',$res['slug'],(string)$pd['Version']);break; } }catch(Throwable $e){}
        elvado_log_activity($activityLogFile,$wpUser,'wp_plugin_install','WordPress-Plugin „'.$res['slug'].'“ installiert');
        elvado_json(['status'=>'ok','slug'=>$res['slug'],'plugins'=>$wpList()]);
    }
    /* ── Sprachpakete (Deutsch) für Core, Plugins und Themes ── */
    if($action==='wp_translations'){ $r=elvado_wpi_fetch_all_translations('de_DE');elvado_log_activity($activityLogFile,$wpUser,'wp_translations','WordPress-Sprachpakete geladen ('.$r['ok'].' ok, '.$r['fail'].' ohne Paket)');elvado_json(['status'=>'ok']+$r); }
    /* ── Updates aus dem WordPress-Verzeichnis ── */
    if($action==='wp_updates')elvado_json(['status'=>'ok','updates'=>elvado_wpi_updates($dataDir)]);
    /* ── Automatische Updates (Plugins, Themes, Sprachpakete, Kernressourcen) ── */
    if($action==='wp_version_status'){ require_once __DIR__.'/wp/wpversion.php';elvado_json(['status'=>'ok']+elvado_wpv_status($dataDir)); }
    if($action==='wp_version_update'){
        require_once __DIR__.'/wp/wpversion.php';$r=elvado_wpv_update($dataDir,'all',(string)($b['version']??''));
        if(!empty($r['ok']))elvado_log_activity($activityLogFile,$wpUser,'wp_version','WordPress-Version '.$r['version'].' übernommen');
        if(!$r['ok'])elvado_json(['status'=>'error','message'=>$r['message']],!empty($r['none'])?409:502);
        elvado_json(['status'=>'ok','message'=>$r['message'],'report'=>$r['report']??null]+elvado_wpv_status($dataDir));
    }
    if($action==='wp_version_rollback'){
        require_once __DIR__.'/wp/wpversion.php';$r=elvado_wpv_rollback();
        if(!$r['ok'])elvado_json(['status'=>'error','message'=>$r['message']],422);
        elvado_log_activity($activityLogFile,$wpUser,'wp_version','WordPress-Version auf '.$r['version'].' zurückgesetzt');
        elvado_json(['status'=>'ok','message'=>$r['message']]+elvado_wpv_status($dataDir));
    }
    if($action==='wp_autoupdate_get'){ require_once __DIR__.'/wp/autoupdate.php';$s=elvado_wpau_get();elvado_json(['status'=>'ok','settings'=>$s,'due'=>elvado_wpau_due($s),'cron'=>is_file(dirname(__DIR__).'/cron/wp-cron.php')]); }
    if($action==='wp_autoupdate_set'){
        require_once __DIR__.'/wp/autoupdate.php';$s=elvado_wpau_set((array)($b['settings']??[]));
        elvado_log_activity($activityLogFile,$wpUser,'wp_autoupdate','Automatische WordPress-Updates: Plugins '.$s['plugins'].', Themes '.$s['themes'].', Sprachpakete '.($s['translations']?'an':'aus'));
        elvado_json(['status'=>'ok','settings'=>$s,'due'=>elvado_wpau_due($s)]);
    }
    if($action==='wp_autoupdate_run'||$action==='wp_autoupdate_tick'){
        require_once __DIR__.'/wp/autoupdate.php';$force=$action==='wp_autoupdate_run';
        $r=elvado_wpau_run($dataDir,$force);
        if($r['ran']&&($r['updated']||$r['failed']))elvado_log_activity($activityLogFile,$wpUser,'wp_autoupdate_run','Automatische Updates: '.$r['updated'].' aktualisiert, '.$r['failed'].' fehlgeschlagen');
        $s=elvado_wpau_get();elvado_json(['status'=>'ok','result'=>$r,'settings'=>$s,'due'=>elvado_wpau_due($s)]);
    }
    if($action==='wp_autoupdate_rollback'){
        require_once __DIR__.'/wp/autoupdate.php';$r=elvado_wpau_rollback((string)($b['type']??''),(string)($b['slug']??''));
        if(!$r['ok'])elvado_json(['status'=>'error','message'=>$r['msg']],422);
        elvado_log_activity($activityLogFile,$wpUser,'wp_autoupdate_rollback','WordPress-'.($b['type']==='plugin'?'Plugin':'Theme').' „'.$b['slug'].'“ auf die vorherige Fassung zurückgesetzt');
        elvado_json(['status'=>'ok']);
    }
    if($action==='wp_update_apply'){
        $type=(string)($b['type']??'');$slug=(string)($b['slug']??'');$zip='';
        try{
            if($type==='plugin'){ $zip=elvado_wpi_download_plugin($slug);elvado_wpi_install_plugin_zip($zip,$slug); }
            elseif($type==='theme'){ $zip=elvado_wpi_download_theme($slug);elvado_wpi_install_theme_zip($zip,$slug); }
            else elvado_json(['status'=>'error','message'=>'Ungültiger Typ'],400);
        }catch(Throwable $e){ if($zip!==''&&is_file($zip))@unlink($zip); elvado_json(['status'=>'error','message'=>$e->getMessage()],422); }
        if($zip!==''&&is_file($zip))@unlink($zip);
        try{ if($type==='plugin'){ foreach(get_plugins() as $pf=>$pd)if(explode('/',$pf)[0]===$slug){ elvado_wpi_fetch_translation('plugin',$slug,(string)$pd['Version']);break; } } else foreach(elvado_wpi_list_themes() as $tt)if($tt['slug']===$slug){ elvado_wpi_fetch_translation('theme',$slug,(string)$tt['version']);break; } }catch(Throwable $e){}
        elvado_log_activity($activityLogFile,$wpUser,'wp_update','WordPress-'.($type==='plugin'?'Plugin':'Theme').' „'.$slug.'“ aktualisiert');
        elvado_json(['status'=>'ok','updates'=>elvado_wpi_updates($dataDir)]);
    }
    /* ── Plugin-Verwaltungsseiten (admin_menu, options.php, admin-post, admin-ajax) ── */
    /* ── Editoren im eigenen Tab (Elementor): Sitzungs-Token, WordPress-Seiten anlegen/listen ── */
    if($action==='wp_session_open'){
        require_once __DIR__.'/wp/session.php';
        $to=elvado_wp_sess_target((string)($b['to']??''));if(!$to)elvado_json(['status'=>'error','message'=>'Ungültiges Ziel'],400);
        if(!is_file(ELVADO_WP_DATA.'/front-on'))elvado_json(['status'=>'error','message'=>'Aktiviere zuerst ein WordPress-Theme: Der Editor zeigt die Website live an.'],409);
        elvado_log_activity($activityLogFile,$wpUser,'wp_session','WordPress-Editor geöffnet: '.$to);
        elvado_json(['status'=>'ok','url'=>'/wp-admin/'.$to.(str_contains($to,'?')?'&':'?').'elvado_wp_login='.elvado_wp_sess_token_issue()]);
    }
    // Einheitliche Sicht „Alle Inhalte“: CMS-Beiträge, CMS-Seiten und WordPress-Datenbank in einer Liste (scope=all); Schreibbrücke ein-/ausschalten
    if($action==='wp_content_list'&&($_GET['scope']??'')==='all'){
        elvado_json(['status'=>'ok','items'=>elvado_wp_cms_content_overview(),'elementor'=>in_array('elementor/elementor.php',(array)get_option_active_plugins(),true),'bridge'=>elvado_wp_bridge_parts(),'bridge_parts'=>ELVADO_WP_BRIDGE_PARTS]);
    }
    if($action==='wp_bridge_set'){
        $parts=array_values(array_intersect(ELVADO_WP_BRIDGE_PARTS,array_map('strval',(array)($b['parts']??[]))));
        elvado_wp_bridge_set($parts);
        elvado_log_activity($activityLogFile,$wpUser,'wp_bridge','Schreibbrücke WordPress ↔ CMS: '.($parts?implode(', ',$parts):'aus'));
        elvado_json(['status'=>'ok','bridge'=>elvado_wp_bridge_parts()]);
    }
    if($action==='wp_content_list'){
        global $wpdb;
        $rows=$wpdb?$wpdb->get_results("SELECT ID,post_title,post_status,post_type,post_modified FROM {$wpdb->posts} WHERE post_type IN ('page','post') AND post_status IN ('publish','draft','private') ORDER BY ID DESC LIMIT 100"):[];
        $out=[];foreach((array)$rows as $r)$out[]=['id'=>(int)$r->ID,'title'=>(string)$r->post_title,'status'=>$r->post_status,'type'=>$r->post_type,'modified'=>$r->post_modified,'elementor'=>get_post_meta((int)$r->ID,'_elementor_edit_mode',true)==='builder','url'=>get_permalink((int)$r->ID)];
        elvado_json(['status'=>'ok','items'=>$out,'elementor'=>in_array('elementor/elementor.php',(array)get_option_active_plugins(),true)]);
    }
    // Auswahlliste für Links (z. B. Tabs der Baukasten-App): veröffentlichte Seiten, Beiträge und Kategorien als Pfad auf dieser Website
    if($action==='wp_link_targets'){
        global $wpdb;$out=[['group'=>'Allgemein','title'=>'Startseite','path'=>'/']];
        $path=function($u){ $u=(string)$u;$x=parse_url($u);if(!is_array($x)||!isset($x['path']))return '';return $x['path'].(isset($x['query'])?'?'.$x['query']:''); };
        $rows=$wpdb?$wpdb->get_results("SELECT ID,post_title,post_type FROM {$wpdb->posts} WHERE post_type IN ('page','post') AND post_status='publish' ORDER BY post_type='page' DESC, post_title ASC LIMIT 300"):[];
        foreach((array)$rows as $r){ $pa=$path(get_permalink((int)$r->ID));if($pa!==''&&trim((string)$r->post_title)!=='')$out[]=['group'=>$r->post_type==='page'?'Seiten':'Beiträge','title'=>(string)$r->post_title,'path'=>$pa]; }
        $terms=get_terms(['taxonomy'=>'category','hide_empty'=>false]);
        foreach(is_array($terms)?$terms:[] as $t){ $l=get_term_link($t);if(is_string($l)&&($pa=$path($l))!=='')$out[]=['group'=>'Kategorien','title'=>(string)$t->name,'path'=>$pa]; }
        elvado_json(['status'=>'ok','items'=>$out]);
    }
    if($action==='wp_page_create'){
        $title=trim((string)($b['title']??''));if($title===''||mb_strlen($title)>200)$title='Neue Seite';
        $id=wp_insert_post(['post_type'=>'page','post_title'=>$title,'post_status'=>'draft','post_content'=>'']);
        if(is_wp_error($id)||!$id)elvado_json(['status'=>'error','message'=>'Seite konnte nicht angelegt werden'],500);
        elvado_log_activity($activityLogFile,$wpUser,'wp_page','WordPress-Seite „'.$title.'“ angelegt');
        elvado_json(['status'=>'ok','id'=>(int)$id]);
    }
    if($action==='wp_core_status')elvado_json(['status'=>'ok']+elvado_wp_core_status());
    if($action==='wp_core_install'){
        $r=elvado_wp_core_install();if(!$r['ok'])elvado_json(['status'=>'error','message'=>$r['message']],502);
        elvado_log_activity($activityLogFile,$wpUser,'wp_core','WordPress-Kernressourcen installiert');
        elvado_json(['status'=>'ok']+elvado_wp_core_status()+['message'=>$r['message']]);
    }
    if($action==='wp_admin_rest'){
        require_once __DIR__.'/wp/admin.php';require_once __DIR__.'/wp/rest.php';
        $GLOBALS['elvado_wp_session_token']=hash('sha256',(string)($_SERVER['HTTP_X_ELVADOPRESS_TOKEN']??''));
        $GLOBALS['elvado_wp_die_throws']=true;$GLOBALS['elvado_wp_serving_rest']=true;
        $hdr=[];foreach((array)($b['headers']??[]) as $k=>$v)if(is_string($k)&&is_scalar($v))$hdr[$k]=(string)$v;
        $path=(string)($b['path']??'');$path=preg_replace('#^.*?/wp-json#','',$path);$q=[];
        if(str_contains($path,'?')){ [$path,$qs]=explode('?',$path,2);parse_str($qs,$q); }
        $method=strtoupper((string)($b['method']??'GET'));
        foreach($hdr as $k=>$v)if(strcasecmp($k,'X-HTTP-Method-Override')===0&&preg_match('/^(GET|POST|PUT|PATCH|DELETE)$/i',$v))$method=strtoupper($v);
        $raw=(string)($b['body']??'');if(strlen($raw)>2097152)elvado_json(['status'=>'error','message'=>'Anfrage zu groß'],413);
        $lv=ob_get_level();ob_start();
        try{ $res=elvado_wp_rest_dispatch($method,$path,$q,$raw,$hdr); } finally { while(ob_get_level()>$lv)ob_end_clean(); }
        elvado_json(['status'=>'ok','result'=>['status'=>$res['status'],'type'=>$res['headers']['Content-Type']??'application/json','text'=>$res['body'],'headers'=>array_intersect_key($res['headers'],array_flip(['X-WP-Total','X-WP-TotalPages','Allow']))]]);
    }
    if(in_array($action,['wp_admin_menu','wp_admin_page','wp_admin_ajax','wp_admin_notices'],true)){
        require_once __DIR__.'/wp/admin.php';
        $GLOBALS['elvado_wp_session_token']=hash('sha256',(string)($_SERVER['HTTP_X_ELVADOPRESS_TOKEN']??''));
        if($action==='wp_admin_menu')elvado_json(['status'=>'ok','groups'=>elvado_wp_admin_menu_tree()]);
        if($action==='wp_admin_notices'){ $nd=elvado_wp_admin_notices_doc();elvado_json(['status'=>'ok','frame'=>$nd===''?'':elvado_wp_admin_frame_store($nd)]); }   // Meldungen der WordPress-Plugins (z. B. Hello Dolly)
        $lv=ob_get_level();$sent=false;
        // Ein Plugin darf mit exit/die enden: das Ergebnis wird dann aus dem Puffer gebaut.
        register_shutdown_function(function() use($lv,&$sent,$action,$b){
            if($sent)return;$out='';while(ob_get_level()>$lv)$out=ob_get_clean().$out;
            if(!headers_sent())header('Content-Type: application/json; charset=utf-8');
            if($action==='wp_admin_ajax'){echo json_encode(['status'=>'ok','result'=>['status'=>200,'type'=>'text/html','text'=>$out===''?'0':$out]]);return;}
            $red=(string)($GLOBALS['elvado_wp_admin_redirect']??'');
            echo json_encode($red!==''?['status'=>'ok','redirect'=>$red]:['status'=>'ok','frame'=>elvado_wp_admin_frame_store(elvado_wp_admin_doc((string)($b['page']??''),$out,true)),'title'=>(string)($GLOBALS['title']??'')]);
        });
        ob_start();
        if($action==='wp_admin_ajax'){
            $u=(string)($b['url']??'');$q=(string)parse_url($u,PHP_URL_QUERY);
            $res=elvado_wp_ajax((string)($b['method']??'POST'),$q,(string)($b['body']??''),true);
            $sent=true;while(ob_get_level()>$lv)ob_end_clean();elvado_json(['status'=>'ok','result'=>$res]);
        }
        $res=elvado_wp_admin_page(['page'=>(string)($b['page']??''),'method'=>(string)($b['method']??'GET'),'body'=>(string)($b['body']??''),'query'=>(string)($b['query']??'')]);
        $sent=true;while(ob_get_level()>$lv)ob_end_clean();
        if(empty($res['ok']))elvado_json(['status'=>'error','message'=>(string)($res['message']??'Fehler')],404);
        if(!empty($res['notice']))elvado_log_activity($activityLogFile,$wpUser,'wp_admin_page','WordPress-Plugin-Seite „'.(string)$b['page'].'“: '.(string)$res['notice']);
        if(isset($res['html'])&&is_string($res['html'])){ $res['frame']=elvado_wp_admin_frame_store($res['html']).(!empty($res['frame_query'])?'&'.$res['frame_query']:'');unset($res['html'],$res['frame_query']); }
        elvado_json(['status'=>'ok']+array_diff_key($res,['ok'=>1]));
    }
    /* ── WordPress-Themes (PHP) ── */
    $wpThemes=function(){ $front=is_file(ELVADO_WP_DATA.'/front-on');$act=$front?(string)get_option('stylesheet',''):'';$l=elvado_wpi_list_themes();$hid=array_values(array_filter((array)($GLOBALS['site']['wp_themes_hidden']??[]),'is_string'));$hl=[];foreach($l as &$t){$t['active']=$t['slug']===$act;$t['hidden']=in_array($t['slug'],$hid,true)&&!$t['active'];}unset($t);foreach($l as $t)if($t['hidden'])$hl[]=['slug'=>$t['slug'],'name'=>$t['name']];$l=array_values(array_filter($l,fn($t)=>!$t['hidden']));return ['themes'=>$l,'front'=>$front,'active'=>$act,'hidden_themes'=>$hl]; };
    if($action==='wp_reading'||$action==='wp_reading_save'){
        require_once __DIR__.'/wp/links-api.php';
        if($action==='wp_reading')elvado_json(['status'=>'ok']+elvado_wpl_reading_get());
        $r=elvado_wpl_reading_save(elvado_body());if(!$r['ok'])elvado_json(['status'=>'error','message'=>$r['message']],422);
        elvado_log_activity($activityLogFile,$wpUser,'wp_reading','Startseite geändert ('.($r['show_on_front']==='page'?'statische Seite':'neueste Beiträge').')');
        unset($r['ok']);elvado_json(['status'=>'ok']+$r);
    }
    if($action==='wp_settings'||$action==='wp_settings_save'){
        require_once __DIR__.'/wp/settings-api.php';
        $grp=(string)($b['group']??$_GET['group']??'');
        if($action==='wp_settings'){ $r=elvado_wps_get($grp);if($r===null)elvado_json(['status'=>'error','message'=>'Unbekannter Einstellungsbereich'],404);elvado_json(['status'=>'ok']+$r); }
        $r=elvado_wps_save($grp,(array)($b['values']??[]));if(!$r['ok'])elvado_json(['status'=>'error','message'=>$r['message']],422);
        elvado_log_activity($activityLogFile,$wpUser,'wp_settings','Einstellungen „'.$r['title'].'“ gespeichert');
        unset($r['ok']);elvado_json(['status'=>'ok']+$r);
    }
    if($action==='wp_permalinks'||$action==='wp_permalinks_save'){
        require_once __DIR__.'/wp/links-api.php';
        if($action==='wp_permalinks')elvado_json(['status'=>'ok']+elvado_wpl_get());
        $r=elvado_wpl_save(elvado_body());if(!$r['ok'])elvado_json(['status'=>'error','message'=>$r['message']],422);
        elvado_log_activity($activityLogFile,$wpUser,'wp_permalinks','Link-Struktur geändert ('.($r['structure']===''?'einfach':$r['structure']).($r['hash']?', Hash-Form':'').')');
        unset($r['ok']);elvado_json(['status'=>'ok']+$r);
    }
    if($action==='wp_themes')elvado_json(['status'=>'ok']+$wpThemes()+['wp_version'=>ELVADO_WP_VERSION,'zip'=>class_exists('ZipArchive')]);
    if($action==='wp_theme_search'){
        $r=elvado_wpi_search_themes($dataDir,(string)($_GET['q']??''),(int)($_GET['page']??1));if(!$r['ok'])elvado_json(['status'=>'error','message'=>$r['message']],502);
        $have=array_column(elvado_wpi_list_themes(),'slug');foreach($r['items'] as &$it)$it['installed']=in_array($it['slug'],$have,true);unset($it);
        elvado_json(['status'=>'ok']+$r);
    }
    if($action==='wp_theme_install'||$action==='wp_theme_upload'){
        $zip='';
        try{
            if($action==='wp_theme_install'){ $slug=(string)($b['slug']??'');$zip=elvado_wpi_download_theme($slug);$res=elvado_wpi_install_theme_zip($zip,$slug); }
            else{ if(empty($_FILES['file'])||!is_uploaded_file($_FILES['file']['tmp_name']))elvado_json(['status'=>'error','message'=>'Keine ZIP-Datei'],400);
                if((int)$_FILES['file']['size']>41943040)elvado_json(['status'=>'error','message'=>'Das ZIP ist größer als 40 MB'],400);
                $res=elvado_wpi_install_theme_zip($_FILES['file']['tmp_name']); }
        }catch(Throwable $e){ if($zip!==''&&is_file($zip))@unlink($zip); elvado_json(['status'=>'error','message'=>$e->getMessage()],422); }
        if($zip!==''&&is_file($zip))@unlink($zip);
        try{ foreach(elvado_wpi_list_themes() as $tt)if($tt['slug']===$res['slug']){ elvado_wpi_fetch_translation('theme',$res['slug'],(string)$tt['version']);break; } }catch(Throwable $e){}
        elvado_log_activity($activityLogFile,$wpUser,'wp_theme_install','WordPress-Theme „'.$res['slug'].'“ installiert');
        elvado_json(['status'=>'ok','slug'=>$res['slug']]+$wpThemes());
    }
    if($action==='wp_theme_unhide'){
        $b0=elvado_body();$sl=preg_replace('/[^a-z0-9_-]/','',(string)($b0['slug']??''));$GLOBALS['site']['wp_themes_hidden']=array_values(array_diff((array)($GLOBALS['site']['wp_themes_hidden']??[]),[$sl]));$site=$GLOBALS['site'];elvado_publish($site,$siteFile,$genDir,$root);
        elvado_json(['status'=>'ok']+$wpThemes());
    }
    if(in_array($action,['wp_theme_activate','wp_theme_delete','wp_theme_preview'],true)){
        $slug=(string)($b['slug']??'');if(!preg_match('/^[a-z0-9_-]{1,80}$/',$slug))elvado_json(['status'=>'error','message'=>'Ungültiges Theme'],400);
        $known=array_column(elvado_wpi_list_themes(),null,'slug');if(!isset($known[$slug]))elvado_json(['status'=>'error','message'=>'Theme nicht gefunden'],404);
        if($action==='wp_theme_activate'){
            $err=elvado_wpi_activate_theme($slug);if($err!==null)elvado_json(['status'=>'error','message'=>$err],422);
            elvado_log_activity($activityLogFile,$wpUser,'wp_theme_activate','WordPress-Theme „'.$slug.'“ aktiviert');elvado_json(['status'=>'ok']+$wpThemes());
        }
        if($action==='wp_theme_preview'){
            if(!empty($known[$slug]['error']))elvado_json(['status'=>'error','message'=>$known[$slug]['error']],422);
            elvado_json(['status'=>'ok','url'=>'/?'.(defined('ELVADO_WP_SANDBOX')?'elvado_sbx='.rawurlencode((string)elvado_sbx_meta()['token']).'&':'').'elvado_wp_preview='.rawurlencode(elvado_wpi_preview_token($slug)),'expires_in'=>900]);
        }
        if(!empty($known[$slug]['bundled'])){   // Themes aus dem Code: nicht löschbar (kämen beim Deploy zurück) – ausblenden; das Standard-Theme bleibt als Rückfallebene
            if($slug==='elvado-classic')elvado_json(['status'=>'error','message'=>'Das Standard-Theme ist die Rückfallebene und kann nicht entfernt werden.'],422);
            $cur0=$wpThemes();if($cur0['active']===$slug)elvado_json(['status'=>'error','message'=>'Das aktive Theme kann nicht entfernt werden. Erst ein anderes aktivieren.'],409);
            foreach($known as $o)if($o['parent']===$slug&&$o['slug']!==$slug&&$cur0['active']===$o['slug'])elvado_json(['status'=>'error','message'=>'Das aktive Theme „'.$o['name'].'“ benötigt dieses Eltern-Theme.'],409);
            $GLOBALS['site']['wp_themes_hidden']=array_values(array_unique(array_merge((array)($GLOBALS['site']['wp_themes_hidden']??[]),[$slug])));$site=$GLOBALS['site'];elvado_publish($site,$siteFile,$genDir,$root);
            elvado_log_activity($activityLogFile,$wpUser,'wp_theme_delete','WordPress-Theme „'.$slug.'“ ausgeblendet');elvado_json(['status'=>'ok','hidden'=>true]+$wpThemes());
        }
        if(defined('ELVADO_WP_SANDBOX')&&($known[$slug]['origin']??'')!=='sandbox')elvado_json(['status'=>'error','message'=>'Dieses Theme gehört zur Live-Seite und lässt sich in der Sandbox nicht löschen.'],422);
        $cur=$wpThemes();if($cur['active']===$slug||(string)get_option('template','')===$slug&&$cur['front'])elvado_json(['status'=>'error','message'=>'Das aktive Theme (oder dessen Eltern-Theme) kann nicht gelöscht werden. Erst ein anderes aktivieren.'],409);
        foreach($known as $o)if($o['parent']===$slug&&$o['slug']!==$slug)elvado_json(['status'=>'error','message'=>'Das Theme „'.$o['name'].'“ benötigt dieses Eltern-Theme.'],409);
        elvado_wp_rmdir(get_theme_root().'/'.$slug);
        elvado_log_activity($activityLogFile,$wpUser,'wp_theme_delete','WordPress-Theme „'.$slug.'“ gelöscht');elvado_json(['status'=>'ok']+$wpThemes());
    }
    if($wpCz){
        // Live-Customizer: Einstellungen lesen, Entwurf für die Vorschau ablegen, Werte speichern (nur Administratoren, wie die übrigen wp_theme_*-Aktionen)
        $vals=is_array($b['values']??null)?$b['values']:[];
        $finish=function(array $out,int $code=200){ while(ob_get_level()>0)ob_end_clean();elvado_json($out,$code); };
        $sbxQ=defined('ELVADO_WP_SANDBOX')?'elvado_sbx='.rawurlencode((string)elvado_sbx_meta()['token']).'&':'';
        $prevUrl=function(string $id) use($czSlug,$sbxQ){ return '/?'.$sbxQ.'elvado_wp_preview='.rawurlencode(elvado_wpi_preview_token($czSlug)).'&elvado_wp_draft='.$id; };
        $shareUrl=function(string $id) use($czSlug,$sbxQ){ return '/?'.$sbxQ.'elvado_wp_preview='.rawurlencode(elvado_wpi_preview_token($czSlug,null,7*86400)).'&elvado_wp_draft='.$id; };
        if($action==='wp_theme_customize'){
            $cs=elvado_wpc_cs_latest($czSlug);$id=$cs?(string)$cs['id']:elvado_wpc_new_id();
            $finish(['status'=>'ok']+elvado_wpc_describe($czSlug)+['draft'=>$id,'url'=>$prevUrl($id),'active'=>$wpThemes()['active']===$czSlug,'front'=>is_file(ELVADO_WP_DATA.'/front-on'),
                'changeset'=>$cs?['id'=>$id,'status'=>$cs['status'],'date'=>(int)$cs['date'],'values'=>$cs['values']??new stdClass,'share'=>$shareUrl($id)]:null]);
        }
        if($action==='wp_theme_customize_changeset'){
            // Entwurf speichern / Veröffentlichung planen / Entwurf verwerfen
            $id=(string)($b['draft']??'');if(!elvado_wpc_cs_ok($id))elvado_json(['status'=>'error','message'=>'Ungültiger Entwurf'],400);
            $mode=(string)($b['mode']??'');
            if($mode==='discard'){ elvado_wpc_cs_discard($id);$finish(['status'=>'ok']); }
            if(!in_array($mode,['draft','future'],true))elvado_json(['status'=>'error','message'=>'Ungültige Aktion'],400);
            $r=elvado_wpc_validate($vals);
            if($r['errors'])$finish(['status'=>'error','message'=>'Einige Werte sind nicht zulässig: '.implode(' · ',array_slice(array_values($r['errors']),0,3)),'errors'=>$r['errors']],422);
            $err=elvado_wpc_cs_save($id,$czSlug,$r['ok'],$mode,(int)($b['date']??0),!empty($b['activate']));
            if($err!==null)$finish(['status'=>'error','message'=>$err],422);
            elvado_wpc_draft_save($id,$czSlug,$r['ok']);
            elvado_log_activity($activityLogFile,$wpUser,'wp_theme_customize',$mode==='future'?'WordPress-Theme „'.$czSlug.'“: Änderungen geplant für '.date('d.m.Y H:i',(int)$b['date']):'WordPress-Theme „'.$czSlug.'“: Entwurf gespeichert');
            $finish(['status'=>'ok','changeset'=>['id'=>$id,'status'=>$mode,'date'=>$mode==='future'?(int)$b['date']:0,'share'=>$shareUrl($id)]]);
        }
        if($action==='wp_theme_customize_draft'){
            $id=(string)($b['draft']??'');if(!preg_match('/^[a-f0-9]{32}$/',$id))$id=elvado_wpc_new_id();
            $r=elvado_wpc_validate($vals);elvado_wpc_draft_save($id,$czSlug,$r['ok']);
            $finish(['status'=>'ok','draft'=>$id,'url'=>$prevUrl($id),'errors'=>$r['errors']]);
        }
        $r=elvado_wpc_save($vals);
        $csId=(string)($b['draft']??'');if($r['errors']===[]&&elvado_wpc_cs_ok($csId))elvado_wpc_cs_discard($csId);   // veröffentlicht → gespeicherter Entwurf entfällt
        if($r['errors'])$finish(['status'=>'error','message'=>'Einige Werte sind nicht zulässig: '.implode(' · ',array_slice(array_values($r['errors']),0,3)),'errors'=>$r['errors']],422);
        if($r['saved'])elvado_log_activity($activityLogFile,$wpUser,'wp_theme_customize','WordPress-Theme „'.$czSlug.'“ angepasst ('.count($r['saved']).' Einstellungen)');
        $finish(['status'=>'ok','saved'=>$r['saved'],'unchanged'=>$r['unchanged']]);
    }
    if($wpBk){
        $GLOBALS['elvado_bk_actor']=new \Elvado\Wp\Actor(mb_substr((string)($wpUser['user']??'admin'),0,100),'admin');   // Layouts liegen im ElvadoPress-Speicher (Entwurf, Fassungen, Termin)
        $bkSt=elvado_bk_store();
        $bkOut=function(array $layout) use($wpThemes,$bkSt){ $t=$wpThemes();$st=$bkSt->get('home');
            return ['status'=>'ok','layout'=>$layout,'custom'=>elvado_bk_saved_layout()!==null,'draft'=>$st['draft']!==null,'publish_at'=>(string)($st['draft']['publish_at']??''),'revisions'=>$bkSt->revisions('home'),'schema'=>elvado_bk_schema(),
                'active'=>$t['front']&&(string)get_option('template','')==='elvado-baukasten'||$t['active']==='elvado-baukasten'];};
        if($action==='wp_bk_get'){ $d=elvado_bk_draft_layout();elvado_json($bkOut($d!==null?$d:elvado_bk_active_layout())+['published'=>elvado_bk_active_layout()]); }
        if($action==='wp_bk_revisions'){ elvado_json(['status'=>'ok','revisions'=>$bkSt->revisions('home')]); }
        if($action==='wp_bk_draft'){
            if(!is_array($b['layout']??null))elvado_json(['status'=>'error','message'=>'Layout fehlt'],400);
            try{ $l=elvado_bk_save_draft($b['layout'],(string)($b['publish_at']??'')); }catch(\InvalidArgumentException $e){ elvado_json(['status'=>'error','message'=>$e->getMessage()],422); }
            elvado_json($bkOut($l));
        }
        if($action==='wp_bk_discard'){ elvado_bk_discard_draft();elvado_log_activity($activityLogFile,$wpUser,'wp_bk','Entwurf der Startseite verworfen');elvado_json($bkOut(elvado_bk_active_layout())); }
        if($action==='wp_bk_publish'){
            if(is_array($b['layout']??null))elvado_bk_save_draft($b['layout']);
            if(elvado_bk_draft_layout()===null)elvado_json(['status'=>'error','message'=>'Es gibt keinen Entwurf zum Veröffentlichen.'],400);
            $p=$bkSt->publish('home',elvado_bk_actor(),(string)($b['label']??''),(array)($b['changes']??[]));
            elvado_log_activity($activityLogFile,$wpUser,'wp_bk','Startseite veröffentlicht (Fassung '.$p['n'].', '.count($p['layout']).' Abschnitte)'.(($p['changes']??[])?' – '.implode('; ',array_slice($p['changes'],0,6)):''));elvado_json($bkOut((array)$p['layout']));
        }
        if($action==='wp_bk_rollback'){
            try{ $p=$bkSt->rollback('home',(int)($b['n']??0),elvado_bk_actor()); }catch(\RuntimeException $e){ elvado_json(['status'=>'error','message'=>$e->getMessage()],422); }
            elvado_log_activity($activityLogFile,$wpUser,'wp_bk','Startseite auf Fassung '.(int)($b['n']??0).' zurückgesetzt');elvado_json($bkOut((array)$p['layout']));
        }
        if(array_key_exists('reset',$b)&&$b['reset']){ delete_option('elvado_bk_layout');$bkSt->unpublish('home',elvado_bk_actor());elvado_log_activity($activityLogFile,$wpUser,'wp_bk','Homepage-Baukasten auf die Customizer-Positionen zurückgesetzt');elvado_json($bkOut(elvado_bk_active_layout())); }
        if(!is_array($b['layout']??null))elvado_json(['status'=>'error','message'=>'Layout fehlt'],400);
        $l=elvado_bk_save_layout($b['layout']);elvado_log_activity($activityLogFile,$wpUser,'wp_bk','Homepage-Baukasten gespeichert ('.count($l).' Abschnitte)');
        elvado_json($bkOut($l));
    }
    if($action==='wp_theme_deactivate'){elvado_wpi_deactivate_theme();elvado_log_activity($activityLogFile,$wpUser,'wp_theme_deactivate','WordPress-Theme-Auslieferung beendet (CMS-Portal aktiv)');elvado_json(['status'=>'ok']+$wpThemes());}
    $file=(string)($b['file']??'');
    if($action==='wp_plugin_activate'){
        $r=activate_plugin($file);if(is_wp_error($r))elvado_json(['status'=>'error','message'=>$r->get_error_message()],422);
        elvado_log_activity($activityLogFile,$wpUser,'wp_plugin_activate','WordPress-Plugin „'.$file.'“ aktiviert');
        elvado_json(['status'=>'ok','plugins'=>$wpList()]);
    }
    if($action==='wp_plugin_deactivate'){deactivate_plugins($file);elvado_log_activity($activityLogFile,$wpUser,'wp_plugin_deactivate','WordPress-Plugin „'.$file.'“ deaktiviert');elvado_json(['status'=>'ok','plugins'=>$wpList()]);}
    if($action==='wp_plugin_delete'){
        $r=delete_plugins([$file]);if(is_wp_error($r))elvado_json(['status'=>'error','message'=>$r->get_error_message()],422);
        elvado_log_activity($activityLogFile,$wpUser,'wp_plugin_delete','WordPress-Plugin „'.$file.'“ gelöscht');elvado_json(['status'=>'ok','plugins'=>$wpList()]);
    }
    elvado_json(['status'=>'error','message'=>'Unbekannte Aktion'],404);
}
// ---- Native ElvadoPress-Plugins (offizielle Essentials und weitere): Verwaltung, Einstellungen, Plugin-API (cms/lib/nplugins.php, cms/src/Plugin/)
if(str_starts_with($action,'np_')){
    $npMgr=elvado_np();
    $npRole=function(array $u):string{ return !empty($u['superadmin'])?'admin':'editor'; };
    if($action==='np_public'){   // ohne Anmeldung: nur Plugin-Aktionen, die ausdrücklich öffentlich sind (z. B. Formular absenden)
        $b=elvado_body();if(!$b&&isset($_POST['call'])){$b=['id'=>$_POST['id']??'','call'=>$_POST['call'],'args'=>json_decode((string)($_POST['args']??'{}'),true)?:[]];}   // multipart (Datei-Uploads)
        $r=$npMgr->callApi((string)preg_replace('/[^a-z0-9-]/','',(string)($b['id']??'')),(string)($b['call']??''),(array)($b['args']??[]),'public');
        $code=(int)($r['code']??200);unset($r['code']);elvado_json($r,$code);
    }
    if($action==='np_call'){
        $u=elvado_auth(false);$GLOBALS['elvado_np_user']=(string)($u['user']??'');$b=elvado_body();$r=$npMgr->callApi((string)preg_replace('/[^a-z0-9-]/','',(string)($b['id']??'')),(string)($b['call']??''),(array)($b['args']??[]),$npRole($u));
        $code=(int)($r['code']??200);unset($r['code']);elvado_json($r,$code);
    }
    if($action==='np_download'){   // Datei aus einem Plugin-Datenordner (Admin): das Plugin liefert über die API-Aktion ['file'=>…,'name'=>…,'mime'=>…]; erlaubt sind nur Pfade in Plugin-Daten und cms/backups
        elvado_auth(true);$r=$npMgr->callApi((string)preg_replace('/[^a-z0-9-]/','',(string)($_GET['id']??'')),(string)($_GET['call']??''),(array)($_GET['args']??[]),'admin');
        $f=(string)($r['file']??'');$real=$f!==''?realpath($f):false;$okBase=false;
        foreach([realpath($npMgr->stateDir().'/data'),realpath(__DIR__.'/backups')] as $base)if($base&&$real&&str_starts_with($real,$base.DIRECTORY_SEPARATOR))$okBase=true;
        if(!$okBase||!is_file($real)){http_response_code(404);exit('Datei nicht gefunden');}
        $name=preg_replace('/[^A-Za-z0-9._-]/','_',(string)($r['name']??basename($real)));header_remove('Content-Type');header('Content-Type: '.(preg_match('~^[a-z]+/[a-z0-9.+-]+$~i',(string)($r['mime']??''))?$r['mime']:'application/octet-stream'));header('X-Content-Type-Options: nosniff');header('Content-Disposition: attachment; filename="'.$name.'"');header('Content-Length: '.filesize($real));readfile($real);exit;
    }
    $u=elvado_auth(true);$b=in_array($action,['np_list'],true)?[]:elvado_body();$pid=(string)preg_replace('/[^a-z0-9-]/','',(string)($b['id']??''));
    $res=function(array $r,string $what)use($activityLogFile,$u,$pid,$npMgr){ if(!empty($r['ok']))elvado_log_activity($activityLogFile,$u,'plugin',$what.($pid!==''?': '.$pid:'')); elvado_json($r+['plugins'=>$npMgr->rows()],!empty($r['ok'])?200:422); };
    elvado_np_migrate();
    if($action==='np_enable_recommended'){$r=$npMgr->installSelection($npMgr->recommendedIds(),true);if($r['activated']||!$r['failed'])$npMgr->setMode('recommended');elvado_log_activity($activityLogFile,$u,'plugin','Empfohlene Plugins aktiviert: '.implode(', ',$r['activated']));elvado_json(['status'=>$r['failed']?'error':'ok','ok'=>!$r['failed'],'message'=>($r['activated']?'Aktiviert: '.implode(', ',array_map(fn($x)=>$npMgr->catalog()[$x]['name']??$x,$r['activated'])).'. ':'').($r['failed']?'Nicht möglich: '.implode(' ',array_map(fn($k,$v)=>(($npMgr->catalog()[$k]['name']??$k).': '.$v),array_keys($r['failed']),$r['failed'])):''),'plugins'=>$npMgr->rows()],$r['failed']?422:200);}
    if($action==='np_list')elvado_json(['status'=>'ok','plugins'=>$npMgr->rows(),'cms_version'=>elvado_cms_version(),'php'=>PHP_VERSION,'mode'=>$npMgr->state()['mode']]);
    if($action==='np_plan')elvado_json(['status'=>'ok']+$npMgr->plan(array_map('strval',(array)($b['ids']??[]))));
    if($action==='np_install'){$r=$npMgr->install($pid,!empty($b['with_deps']));$res(['status'=>$r['ok']?'ok':'error']+$r,'Plugin installiert');}
    if($action==='np_activate'){$r=$npMgr->activate($pid,!empty($b['with_deps']));$res(['status'=>$r['ok']?'ok':'error']+$r,'Plugin aktiviert');}
    if($action==='np_deactivate'){$r=$npMgr->deactivate($pid,!empty($b['cascade']));$res(['status'=>$r['ok']?'ok':'error']+$r,'Plugin deaktiviert');}
    if($action==='np_update'){$r=$npMgr->update($pid);$res(['status'=>$r['ok']?'ok':'error']+$r,'Plugin aktualisiert');}
    if($action==='np_uninstall'){$r=$npMgr->uninstall($pid,!empty($b['purge']));$res(['status'=>$r['ok']?'ok':'error']+$r,'Plugin deinstalliert');}
    if($action==='np_settings_get'){if(!$npMgr->isInstalled($pid))elvado_json(['status'=>'error','message'=>'Plugin nicht installiert'],404);elvado_json(['status'=>'ok','schema'=>$npMgr->settingsSchema($pid),'settings'=>$npMgr->settings($pid)]);}
    if($action==='np_settings_save'){$r=$npMgr->saveSettings($pid,(array)($b['values']??[]));if($r['ok'])elvado_log_activity($activityLogFile,$u,'plugin','Plugin-Einstellungen gespeichert: '.$pid);elvado_json(['status'=>$r['ok']?'ok':'error']+$r,$r['ok']?200:422);}
    elvado_json(['status'=>'error','message'=>'Unbekannte Aktion'],404);
}
if($action==='plugins_list'){elvado_auth(false);elvado_json(['status'=>'ok','plugins'=>elvado_plugin_catalog($site),'active'=>$site['plugins']??[]]);}
if($action==='plugin_upload'){
    elvado_auth(true);if(empty($_FILES['file'])||!is_uploaded_file($_FILES['file']['tmp_name']))elvado_json(['status'=>'error','message'=>'Keine Plugin-ZIP'],400);
    if((int)($_FILES['file']['size']??0)>10485760)elvado_json(['status'=>'error','message'=>'Plugin-ZIP ist größer als 10 MB'],400);
    try{$x=elvado_import_plugin_zip($_FILES['file']['tmp_name']);}catch(Throwable $e){elvado_json(['status'=>'error','message'=>$e->getMessage()],422);}
    elvado_json(['status'=>'ok','id'=>$x['id'],'plugins'=>elvado_plugin_catalog($site)]);
}
if($action==='plugin_toggle'){
    elvado_auth(true);$b=elvado_body();$id=elvado_plugin_id((string)($b['id']??''));$enable=!empty($b['enabled']);
    $found=false;foreach(elvado_plugin_catalog($site) as $pl)if($pl['id']===$id){$found=true;break;}if(!$found)elvado_json(['status'=>'error','message'=>'Plugin nicht gefunden'],404);
    $active=array_values(array_unique(array_map('strval',(array)($site['plugins']??[]))));
    $active=array_values(array_filter($active,fn($x)=>$x!==$id));if($enable)$active[]=$id;$site['plugins']=$active;elvado_publish($site,$siteFile,$genDir,$root);
    elvado_json(['status'=>'ok','active'=>$active]);
}
if($action==='plugin_delete'){
    elvado_auth(true);$b=elvado_body();$id=elvado_plugin_id((string)($b['id']??''));$active=(array)($site['plugins']??[]);
    if(in_array($id,$active,true))elvado_json(['status'=>'error','message'=>'Aktives Plugin zuerst deaktivieren'],409);
    $dir=__DIR__.'/plugins/'.$id;if(!is_dir($dir))elvado_json(['status'=>'error','message'=>'Plugin nicht gefunden'],404);
    foreach(glob($dir.'/*')?:[] as $x)if(is_file($x))@unlink($x);if(!@rmdir($dir))elvado_json(['status'=>'error','message'=>'Plugin-Ordner konnte nicht gelöscht werden'],500);
    elvado_json(['status'=>'ok']);
}
if($action==='backup_list'){elvado_auth(false);elvado_json(['status'=>'ok','backups'=>elvado_backup_list()]);}
if($action==='backup_create'){
    elvado_auth(true);$b=elvado_body();$include=array_key_exists('include_media',$b)?!empty($b['include_media']):!empty($site['backup']['include_media']);
    try{$x=elvado_backup_create($root,$include);$keep=max(1,min(50,(int)($site['backup']['keep']??10)));$all=elvado_backup_list();foreach(array_slice($all,$keep) as $old)@unlink(elvado_backup_dir().'/'.basename($old['name']));elvado_json(['status'=>'ok','backup'=>$x,'backups'=>elvado_backup_list()]);}catch(Throwable $e){elvado_json(['status'=>'error','message'=>$e->getMessage()],500);}
}
if($action==='backup_restore'){
    elvado_auth(true);$b=elvado_body();try{elvado_backup_restore((string)($b['name']??''),$root);$site=elvado_ensure_site_defaults(elvado_read_json($siteFile,[]));elvado_publish($site,$siteFile,$genDir,$root);elvado_json(['status'=>'ok','message'=>'Backup wiederhergestellt','config'=>$site]);}catch(Throwable $e){elvado_json(['status'=>'error','message'=>$e->getMessage()],500);}
}
if($action==='backup_download'){
    elvado_auth(true);$name=basename((string)($_GET['name']??''));$file=elvado_backup_dir().'/'.$name;if(!is_file($file)){http_response_code(404);exit('Backup nicht gefunden');}
    header_remove('Content-Type');header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="'.str_replace('"','',$name).'"');header('Content-Length: '.filesize($file));readfile($file);exit;
}
if($action==='database_status'){elvado_auth(true);elvado_json(['status'=>'ok','database'=>elvado_db_status(),'storage'=>$site['storage']??['mode'=>'files','database_mirror'=>false]]);}
if($action==='database_test'){
    elvado_auth(true);$b=elvado_body();[$c,$err]=elvado_db_clean_input((array)($b['database']??[]));
    if($err!==null)elvado_json(['status'=>'error','message'=>$err],400);
    elvado_json(['status'=>'ok']+elvado_db_test($c,!empty($b['create'])));
}
if($action==='database_drivers'){elvado_auth(true);$o=[];foreach(elvado_db_drivers() as $k=>$d)$o[]=['id'=>$k,'label'=>$d['label'],'available'=>$d['ext']===''||extension_loaded($d['ext']),'port'=>$d['port']];elvado_json(['status'=>'ok','drivers'=>$o]);}
if($action==='database_config_save'){
    elvado_auth(true);$b=elvado_body();
    // Eine Datenbank für CMS und WordPress-Kern: das Präfix der WordPress-Schicht darf nicht das Präfix der Engine sein
    try{ $dbIn=(array)($b['database']??[]);$ep=@json_decode((string)@file_get_contents($dataDir.'/.wp-engine/db.json'),true);
        if(in_array((string)($dbIn['driver']??''),['mysql','mariadb'],true)&&is_array($ep)&&strcasecmp((string)($ep['prefix']??'-'),trim((string)($dbIn['prefix']??'wp_')))===0)
            elvado_json(['status'=>'error','message'=>'Das Tabellenpräfix „'.trim((string)($dbIn['prefix']??'')).'“ nutzt schon der WordPress-Kern in dieser Datenbank. Bitte ein anderes wählen (z. B. wpl_).'],400);
    }catch(Throwable $e){}
    try{elvado_db_write_config((array)($b['database']??[]));$site['storage']=elvado_clean_section('storage',(array)($b['storage']??[]));elvado_publish($site,$siteFile,$genDir,$root);elvado_json(['status'=>'ok','database'=>elvado_db_status(),'storage'=>$site['storage']]);}catch(Throwable $e){elvado_json(['status'=>'error','message'=>$e->getMessage()],500);}
}
if($action==='database_push'){
    elvado_auth(true);try{$x=elvado_db_push($site,elvado_read_json($newsFile,[]));try{ require_once __DIR__.'/src/autoload.php';$cdb=\Elvado\Database\DatabaseConnection::fromCmsSettings($dataDir);$cdb->migrateCore();$x['core_posts']=(new \Elvado\Repository\PostRepository($cdb))->mirror(elvado_read_json($newsFile,[])); }catch(Throwable $e){}elvado_json(['status'=>'ok','synced'=>$x,'database'=>elvado_db_status()]);}catch(Throwable $e){elvado_json(['status'=>'error','message'=>$e->getMessage()],500);}
}
if($action==='database_pull'){
    elvado_auth(true);$b=elvado_body();if(($b['confirm']??'')!=='DATENBANK IMPORTIEREN')elvado_json(['status'=>'error','message'=>'Bestätigung fehlt'],400);
    try{$x=elvado_db_pull();if(!empty($x['site'])){$site=elvado_ensure_site_defaults($x['site']);elvado_publish($site,$siteFile,$genDir,$root);}if(isset($x['news']))elvado_write_atomic($newsFile,json_encode($x['news'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");elvado_json(['status'=>'ok','config'=>$site,'news_count'=>count($x['news']??[])]);}catch(Throwable $e){elvado_json(['status'=>'error','message'=>$e->getMessage()],500);}
}
if($action==='content_scan'){elvado_auth(false);elvado_json(['status'=>'ok','pages'=>elvado_content_scan()]);}
if($action==='content_pull'){elvado_auth(false);$x=elvado_content_pull($site);if($x['imported']){$site=elvado_ensure_site_defaults($x['site']);elvado_publish($site,$siteFile,$genDir,$root);}elvado_json(['status'=>'ok','imported'=>$x['imported'],'baseline'=>$x['baseline'],'errors'=>$x['errors']??[]]);}
if($action==='content_sync'){elvado_auth(false);elvado_json(['status'=>'ok','files'=>elvado_content_sync_from_site($site)]);}
if($action==='content_import'){
    elvado_auth(false);$b=elvado_body();$slug=trim((string)($b['slug']??''));
    try{$x=elvado_content_import_to_site($site,$slug!==''?$slug:null);$site=elvado_ensure_site_defaults($x['site']);elvado_publish($site,$siteFile,$genDir,$root);elvado_json(['status'=>'ok','imported'=>$x['imported'],'config'=>$site]);}
    catch(Throwable $e){elvado_json(['status'=>'error','message'=>$e->getMessage()],500);}
}
if($action==='seo_rebuild'){elvado_auth(false);elvado_seo_generate($site,elvado_read_json($newsFile,[]),$root);elvado_json(['status'=>'ok','sitemap'=>'/sitemap.xml','robots'=>'/robots.txt']);}

if($action==='api_docs'){
    elvado_auth(false);elvado_json(['status'=>'ok','version'=>'1.0','docs'=>'/cms/docs/API.md','docs_html'=>'/cms/docs/','plugin_docs'=>'/cms/docs/PLUGINS.md','public_endpoint'=>'/cms/api.php?action=public','rss'=>'/cms/rss.php','rss_alias'=>'/feed/','rss_mirror'=>'/rss.xml',
      'write_sections'=>['portal','pages','menus','widgets','widget_areas','widget_inactive','branding','apps','legal','feed_sources','rss','theme','plugins','seo','storage','backup','brands','assistant','alexa'],
      'plugin_hooks'=>['portal:ready','page:rendered','cms:config-applied','plugin:loaded']]);
}
if($action==='themes_list'){elvado_auth(false);$state=$site['theme']??['active'=>elvado_default_theme_id(),'variant'=>'default','settings'=>[]];$modsSaved=array_keys(is_array($state['mods']??null)?$state['mods']:[]);unset($state['mods']);elvado_json(['status'=>'ok','themes'=>elvado_theme_catalog($site),'hidden_themes'=>array_values(array_map(fn($t)=>['id'=>$t['id'],'name'=>$t['name']],array_filter(elvado_theme_catalog($site,true),fn($t)=>$t['hidden']))),'active'=>$site['theme']['active']??elvado_default_theme_id(),'theme_state'=>$state,'mods_saved'=>$modsSaved]);}
if($action==='theme_upload'){
    elvado_auth(false);if(empty($_FILES['file'])||!is_uploaded_file($_FILES['file']['tmp_name']))elvado_json(['status'=>'error','message'=>'Keine ZIP-Datei'],400);
    if((int)($_FILES['file']['size']??0)>20971520)elvado_json(['status'=>'error','message'=>'Theme-ZIP ist größer als 20 MB'],400);
    try{$x=elvado_import_theme_zip($_FILES['file']['tmp_name']);}catch(Throwable $e){elvado_json(['status'=>'error','message'=>$e->getMessage()],422);}
    $catalog=elvado_theme_catalog($site);$imported=null;foreach($catalog as $t)if(($t['id']??'')===$x['id']){$imported=$t;break;}
    elvado_json(['status'=>'ok','id'=>$x['id'],'wordpress'=>$x['wordpress'],'bootstrap'=>!empty($imported['bootstrap']),'compatibility'=>$imported['compatibility']??'native','themes'=>$catalog]);
}
if($action==='theme_directory'||$action==='theme_install'){
    require_once __DIR__.'/lib/theme-directory.php';
    if($action==='theme_directory'){
        elvado_auth(false);
        $r=elvado_td_search($dataDir,(string)($_GET['source']??'bootswatch'),(string)($_GET['q']??''),(int)($_GET['page']??1));
        if(!$r['ok'])elvado_json(['status'=>'error','message'=>$r['message']],502);
        $have=array_column(elvado_theme_catalog($site),'id');
        foreach($r['items'] as &$it)$it['installed']=in_array($it['source']==='bootswatch'?'bootswatch-'.$it['slug']:elvado_theme_id($it['slug']),$have,true);unset($it);
        elvado_json(['status'=>'ok']+$r);
    }
    $u=elvado_auth(true);$b=elvado_body();$src=(string)($b['source']??'');$slug=(string)($b['slug']??'');$zipTmp='';
    try{$zipTmp=elvado_td_build_zip($dataDir,$src,$slug);$x=elvado_import_theme_zip($zipTmp);}
    catch(Throwable $e){if($zipTmp!==''&&is_file($zipTmp))@unlink($zipTmp);elvado_json(['status'=>'error','message'=>$e->getMessage()],422);}
    @unlink($zipTmp);
    elvado_log_activity($activityLogFile,$u,'theme_install','Theme „'.$x['id'].'“ aus dem Verzeichnis installiert');
    elvado_json(['status'=>'ok','id'=>$x['id'],'wordpress'=>$x['wordpress']]);
}
if($action==='theme_activate'){
    elvado_auth(false);$b=elvado_body();$id=elvado_theme_id((string)($b['id']??''));$found=null;
    foreach(elvado_theme_catalog($site) as $t)if($t['id']===$id){$found=$t;break;}if(!$found)elvado_json(['status'=>'error','message'=>'Theme nicht gefunden'],404);
    $defaults=is_array($found['defaults']??null)?$found['defaults']:[];
    $variant=(string)($found['variants'][0]['id']??'default');
    // Wie bei WordPress: Anpassungen und Widget-Anordnung des bisherigen Themes sichern, dann
    // die gespeicherten des neuen Themes wiederherstellen - oder, beim ersten Aktivieren, seine
    // mitgelieferte Anordnung laden. Bringt ein Theme keine eigenen Bereiche mit (z.B. ein
    // importiertes reines CSS-Theme), bleibt die aktuelle Anordnung erhalten.
    $site=elvado_theme_remember_mods($site);
    $mods=is_array($site['theme']['mods']??null)?$site['theme']['mods']:[];
    $prev=is_array($mods[$id]??null)?$mods[$id]:null;
    if($prev!==null){$variant=(string)($prev['variant']??$variant);$settings=is_array($prev['settings']??null)?$prev['settings']:$defaults;$areas=is_array($prev['widget_areas']??null)?$prev['widget_areas']:null;}
    else{$settings=$defaults;$settings['sidebar_width']=(string)$found['layout']['sidebar_width'];$areas=$found['widget_areas']?:null;}
    $site['theme']=['active'=>$id,'variant'=>$variant,'settings'=>$settings,'layout'=>$found['layout'],'mods'=>$mods];
    if($areas!==null)$site['widget_areas']=elvado_clean_section('widget_areas',$areas);
    $site=elvado_theme_remember_mods($site);
    elvado_publish($site,$siteFile,$genDir,$root);
    elvado_log_activity($activityLogFile,null,'theme_activate','Theme „'.$found['name'].'“ aktiviert'.($prev!==null?' (gespeicherte Anpassungen wiederhergestellt)':' (Standard-Anordnung des Themes geladen)'));
    elvado_json(['status'=>'ok','theme'=>$site['theme'],'widget_areas'=>$site['widget_areas']??[]]);
}
if($action==='theme_customize_save'){
    elvado_auth(false);$b=elvado_body();$id=elvado_theme_id((string)($b['id']??$site['theme']['active']??elvado_default_theme_id()));
    $found=null;foreach(elvado_theme_catalog($site) as $t)if($t['id']===$id){$found=$t;break;}if(!$found)elvado_json(['status'=>'error','message'=>'Theme nicht gefunden'],404);
    $switching=($site['theme']['active']??elvado_default_theme_id())!==$id;
    if($switching){$site=elvado_theme_remember_mods($site);$prev=$site['theme']['mods'][$id]??null;if(is_array($prev['widget_areas']??null))$site['widget_areas']=elvado_clean_section('widget_areas',$prev['widget_areas']);elseif($found['widget_areas'])$site['widget_areas']=$found['widget_areas'];}
    $clean=elvado_clean_section('theme',['active'=>$id,'variant'=>$b['variant']??'default','settings'=>$b['settings']??[],'layout'=>$found['layout'],'mods'=>$site['theme']['mods']??[]]);
    $czOld=is_array($site['theme']['settings']??null)?$site['theme']['settings']:[];$czNew=is_array($clean['settings']??null)?$clean['settings']:[];$czCh=[];
    foreach(array_unique(array_merge(array_keys($czOld),array_keys($czNew))) as $czK){$o=$czOld[$czK]??null;$n2=$czNew[$czK]??null;if($o!==$n2&&count($czCh)<8)$czCh[]=mb_substr((string)$czK,0,40).': '.mb_substr(is_scalar($o)?(string)$o:'…',0,24).' → '.mb_substr(is_scalar($n2)?(string)$n2:'…',0,24);}
    if(($site['theme']['variant']??'default')!==($clean['variant']??'default'))array_unshift($czCh,'Variante: '.($site['theme']['variant']??'default').' → '.($clean['variant']??'default'));
    $site['theme']=$clean;$site=elvado_theme_remember_mods($site);
    elvado_publish($site,$siteFile,$genDir,$root);elvado_log_activity($activityLogFile,elvado_auth(false),'theme_customize','Customizer „'.$id.'“ gespeichert'.($czCh?' – '.implode('; ',array_slice($czCh,0,6)):' (keine Wertänderung)'));elvado_json(['status'=>'ok','theme'=>$site['theme'],'widget_areas'=>$site['widget_areas']??[]]);
}
if($action==='theme_reset_areas'){
    elvado_auth(false);$id=elvado_theme_id((string)($site['theme']['active']??elvado_default_theme_id()));
    $found=null;foreach(elvado_theme_catalog($site) as $t)if($t['id']===$id){$found=$t;break;}if(!$found)elvado_json(['status'=>'error','message'=>'Aktives Theme nicht gefunden'],404);
    if(!$found['widget_areas'])elvado_json(['status'=>'error','message'=>'Dieses Theme bringt keine eigenen Widget-Bereiche mit'],400);
    $site['widget_areas']=$found['widget_areas'];$site=elvado_theme_remember_mods($site);
    elvado_publish($site,$siteFile,$genDir,$root);
    elvado_log_activity($activityLogFile,null,'theme_reset_areas','Widget-Anordnung auf den Standard des Themes „'.$found['name'].'“ zurückgesetzt');
    elvado_json(['status'=>'ok','widget_areas'=>$site['widget_areas']]);
}
if($action==='theme_delete'||$action==='theme_unhide'){
    elvado_auth(false);$b=elvado_body();$id=elvado_theme_id((string)($b['id']??''));$hid=array_values(array_filter((array)($site['theme_hidden']??[]),'is_string'));
    if($action==='theme_unhide'){$site['theme_hidden']=array_values(array_diff($hid,[$id]));elvado_publish($site,$siteFile,$genDir,$root);elvado_json(['status'=>'ok']);}
    if($id===elvado_default_theme_id())elvado_json(['status'=>'error','message'=>'Das Standard-Theme ist die Rückfallebene und kann nicht entfernt werden.'],400);
    if(($site['theme']['active']??elvado_default_theme_id())===$id)elvado_json(['status'=>'error','message'=>'Das aktive Theme kann nicht entfernt werden. Erst ein anderes aktivieren.'],400);
    $dir=__DIR__.'/themes/'.$id;if(!is_dir($dir)||!is_file($dir.'/theme.json'))elvado_json(['status'=>'error','message'=>'Theme nicht gefunden'],404);
    if(!is_file($dir.'/.uploaded')){   // Themes aus dem Code (Git/Deploy): ausblenden statt löschen – sonst kämen sie beim nächsten Deploy zurück
        $site['theme_hidden']=array_values(array_unique(array_merge($hid,[$id])));elvado_publish($site,$siteFile,$genDir,$root);
        elvado_json(['status'=>'ok','hidden'=>true,'message'=>'Theme ausgeblendet (die Dateien liegen im Code und bleiben erhalten; unter „Ausgeblendete Themes“ lässt es sich zurückholen).']);
    }
    $rm=function(string $d)use(&$rm):void{foreach(scandir($d)?:[] as $x){if($x==='.'||$x==='..')continue;$f=$d.'/'.$x;if(is_link($f)||is_file($f))@unlink($f);elseif(is_dir($f))$rm($f);}@rmdir($d);};$rm($dir);
    elvado_json(['status'=>'ok','hidden'=>false]);
}
if($action==='architecture'){elvado_auth(false);elvado_json(['status'=>'ok','components'=>[['id'=>'portal','name'=>'Website','type'=>'Frontend','path'=>'/'],['id'=>'cms','name'=>elvado_product_title(),'type'=>'Datei-CMS','path'=>'/cms/'],['id'=>'storage','name'=>'CMS-Dateispeicher','type'=>'JSON','path'=>'/cms/data/site.json'],['id'=>'generated','name'=>'Generierte Seiten & SEO','type'=>'HTML/CSS','path'=>'/cms/generated/'],['id'=>'local-auth','name'=>'Lokaler CMS-Zugang','type'=>'Zugriff & Rechte','path'=>'/cms/data/local-auth.local.php']],'core_stations'=>[],'updated_at'=>date(DATE_ATOM)]);}
// Community (Mitglieder; optional, standardmäßig aus): öffentliche Konto-Funktionen und Verwaltung im CMS
if(str_starts_with($action,'member_')||str_starts_with($action,'community_')||str_starts_with($action,'forum_')||str_starts_with($action,'social_')){
    $cmCfg=elvado_cm_config($dataDir);$cmIp=trim(explode(',',(string)($_SERVER['HTTP_X_FORWARDED_FOR']??$_SERVER['REMOTE_ADDR']??''))[0]);
    $cmTok=trim((string)($_SERVER['HTTP_X_MEMBER_TOKEN']??''));
    $cmOut=function(array $r,array $extra=[]){ if(!$r['ok'])elvado_json(['status'=>'error','message'=>$r['message']],$r['code']); unset($r['ok'],$r['code']); elvado_json(['status'=>'ok']+$r+$extra); };
    $cmNeedOn=function() use($cmCfg){ if(!$cmCfg['enabled'])elvado_json(['status'=>'error','message'=>'Die Community ist nicht aktiv.'],404); };
    $cmMe=function() use($dataDir,$cmTok,$cmNeedOn){ $cmNeedOn(); $m=elvado_cm_auth($dataDir,$cmTok); if(!$m)elvado_json(['status'=>'error','message'=>'Bitte melde dich an.'],401); return $m; };
    if($action==='community_config')elvado_json(['status'=>'ok','config'=>elvado_cm_public_config($cmCfg)]);
    if($action==='member_register'){$cmOut(elvado_cm_register($dataDir,$cmCfg,elvado_body(),$cmIp));}
    if($action==='member_login'){$cmOut(elvado_cm_login($dataDir,$cmCfg,elvado_body(),$cmIp));}
    if($action==='member_logout'){elvado_cm_logout($dataDir,$cmTok);elvado_json(['status'=>'ok']);}
    if($action==='member_me'){$m=$cmMe();elvado_json(['status'=>'ok','member'=>elvado_cm_public($m)+['email'=>$m['email']]]);}
    if($action==='member_update'){$m=$cmMe();$cmOut(elvado_cm_update_profile($dataDir,$cmCfg,(string)$m['id'],elvado_body()));}
    if($action==='member_delete'){$m=$cmMe();$b=elvado_body();$cmOut(elvado_cm_delete_account($dataDir,(string)$m['id'],(string)($b['password']??''),function($id) use($dataDir){ if(function_exists('elvado_cm_anonymize_member'))elvado_cm_anonymize_member($dataDir,$id); }));}
    if($action==='member_reset_request'){
        $cmNeedOn();$b=elvado_body();$origin=rtrim((string)(elvado_seo_defaults($site)['canonical_base']??''),'/');
        $cmOut(elvado_cm_reset_request($dataDir,$cmCfg,(string)($b['email']??''),$cmIp,$origin,function($to,$link) use($site){
            elvado_send_mail($to,'Passwort zurücksetzen',"Hallo,\n\nüber diesen Link kannst du ein neues Passwort festlegen (eine Stunde gültig):\n\n$link\n\nWenn du das nicht angefordert hast, ignoriere diese Nachricht einfach.\n",elvado_mail_from($site));
        }));
    }
    if($action==='member_reset'){$cmNeedOn();$b=elvado_body();$cmOut(elvado_cm_reset_apply($dataDir,$cmCfg,(string)($b['token']??''),(string)($b['password']??'')));}
    if($action==='member_profile'){
        $cmNeedOn();$ms=elvado_cm_members($dataDir);$i=elvado_cm_find($ms,'id',(string)($_GET['id']??''));if($i===null)$i=elvado_cm_find($ms,'username',(string)($_GET['username']??''));
        if($i===null||($ms[$i]['status']??'')!=='active')elvado_json(['status'=>'error','message'=>'Mitglied nicht gefunden'],404);
        elvado_json(['status'=>'ok','member'=>elvado_cm_public($ms[$i])]);
    }
    // Forum (Lesen öffentlich, Schreiben nur Mitglieder; Moderation: Moderatoren-Mitglieder oder CMS-Administratoren)
    if(str_starts_with($action,'forum_')){
        $cmNeedOn();if(!$cmCfg['forum'])elvado_json(['status'=>'error','message'=>'Das Forum ist nicht aktiv.'],404);
        $b=elvado_body();$q=fn(string $k)=>(string)($_GET[$k]??'');
        // Moderation: CMS-Token (Administrator) oder Mitglied mit Rolle moderator
        $foMod=function() use($cmMe,$dataDir,&$b){ if(trim((string)($_SERVER['HTTP_X_ELVADOPRESS_TOKEN']??''))!==''){elvado_auth(true);return null;} $m=$cmMe(); if(($m['role']??'')!=='moderator')elvado_json(['status'=>'error','message'=>'Dazu fehlt dir die Berechtigung.'],403); return $m; };
        if($action==='forum_overview')elvado_json(['status'=>'ok','categories'=>elvado_fo_overview($dataDir)]);
        if($action==='forum_topics'){$r=elvado_fo_topics($dataDir,$q('cat'),(int)$q('page'));elvado_json(['status'=>'ok']+$r);}
        if($action==='forum_topic'){$r=elvado_fo_topic($dataDir,$q('id'),(int)$q('page'));if(!$r)elvado_json(['status'=>'error','message'=>'Thema nicht gefunden'],404);elvado_json(['status'=>'ok']+$r);}
        if($action==='forum_topic_create'){$m=$cmMe();$cmOut(elvado_fo_topic_create($dataDir,$m,$b));}
        if($action==='forum_reply'){$m=$cmMe();$cmOut(elvado_fo_reply($dataDir,$m,(string)($b['topic']??''),(string)($b['body']??''),($m['role']??'')==='moderator'));}
        if($action==='forum_edit'){$m=$cmMe();$cmOut(elvado_fo_edit($dataDir,$m,(string)($b['topic']??''),(string)($b['post']??''),(string)($b['body']??'')));}
        if($action==='forum_delete'){$m=$cmMe();$cmOut(elvado_fo_delete_post($dataDir,$m,($m['role']??'')==='moderator',(string)($b['topic']??''),(string)($b['post']??'')));}
        if($action==='forum_report'){$m=$cmMe();$cmOut(elvado_fo_report($dataDir,$m,(string)($b['topic']??''),(string)($b['post']??''),(string)($b['reason']??'')));}
        if($action==='forum_mod'){$foMod();$cmOut(elvado_fo_mod($dataDir,(string)($b['op']??''),(string)($b['topic']??''),(string)($b['arg']??'')));}
        if($action==='forum_mod_delete_post'){$foMod();$cmOut(elvado_fo_delete_post($dataDir,null,true,(string)($b['topic']??''),(string)($b['post']??'')));}
        if($action==='forum_reports'){$foMod();elvado_json(['status'=>'ok','reports'=>elvado_fo_reports($dataDir)]);}
        if($action==='forum_report_dismiss'){$foMod();$cmOut(elvado_fo_report_dismiss($dataDir,(string)($b['id']??'')));}
        if($action==='forum_categories_save'){elvado_auth(true);elvado_json(['status'=>'ok','categories'=>elvado_fo_categories_save($dataDir,$b['categories']??[])]);}
        elvado_json(['status'=>'error','message'=>'Unbekannte Aktion'],404);
    }
    // Soziales Netzwerk (Lesen öffentlich, Posten/Folgen/Liken nur Mitglieder)
    if(str_starts_with($action,'social_')){
        $cmNeedOn();if(!$cmCfg['social'])elvado_json(['status'=>'error','message'=>'Das soziale Netzwerk ist nicht aktiv.'],404);
        $b=elvado_body();$viewer=$cmTok!==''?elvado_cm_auth($dataDir,$cmTok):null;
        if($action==='social_feed'){
            $mode=in_array((string)($_GET['mode']??''),['following','user'],true)?(string)$_GET['mode']:'all';
            if($mode==='following'&&!$viewer)elvado_json(['status'=>'error','message'=>'Bitte melde dich an.'],401);
            $uid='';if($mode==='user'){$ms=elvado_cm_members($dataDir);$i=elvado_cm_find($ms,'username',(string)($_GET['username']??''));if($i===null||($ms[$i]['status']??'')!=='active')elvado_json(['status'=>'error','message'=>'Mitglied nicht gefunden'],404);$uid=(string)$ms[$i]['id'];}
            elvado_json(['status'=>'ok']+elvado_so_feed($dataDir,$mode,$viewer,$uid,(string)($_GET['before']??'')));
        }
        if($action==='social_profile'){$r=elvado_so_profile($dataDir,(string)($_GET['username']??''),$viewer);if(!$r)elvado_json(['status'=>'error','message'=>'Mitglied nicht gefunden'],404);elvado_json(['status'=>'ok']+$r);}
        $m=$cmMe();
        if($action==='social_post')$cmOut(elvado_so_post($dataDir,$m,(string)($b['body']??'')));
        if($action==='social_delete')$cmOut(elvado_so_delete($dataDir,$m,($m['role']??'')==='moderator',(string)($b['id']??'')));
        if($action==='social_like')$cmOut(elvado_so_like($dataDir,$m,(string)($b['id']??'')));
        if($action==='social_follow')$cmOut(elvado_so_follow($dataDir,$m,(string)($b['id']??'')));
        elvado_json(['status'=>'error','message'=>'Unbekannte Aktion'],404);
    }
    // Verwaltung (nur Administratoren)
    if($action==='community_admin_get'){elvado_auth(true);elvado_json(['status'=>'ok','config'=>$cmCfg,'members'=>elvado_cm_admin_list($dataDir)]);}
    if($action==='community_config_save'){
        $u=elvado_auth(true);$b=elvado_body();$new=elvado_cm_config_clean($b['config']??[]);
        try{elvado_cm_write($dataDir,'config.json',$new);elvado_protect_dir(elvado_cm_dir($dataDir));}catch(Throwable $e){elvado_json(['status'=>'error','message'=>'Speichern fehlgeschlagen'],500);}
        if($new['enabled']!==$cmCfg['enabled'])elvado_log_activity($activityLogFile,$u,'community_'.($new['enabled']?'on':'off'),'Community '.($new['enabled']?'eingeschaltet':'ausgeschaltet'));
        elvado_json(['status'=>'ok','config'=>$new]);
    }
    if($action==='community_member_op'){
        $u=elvado_auth(true);$b=elvado_body();$op=(string)($b['op']??'');$id=(string)($b['id']??'');
        $r=elvado_cm_admin_set($dataDir,$id,$op,function($mid) use($dataDir){ if(function_exists('elvado_cm_anonymize_member'))elvado_cm_anonymize_member($dataDir,$mid); });
        if($r['ok'])elvado_log_activity($activityLogFile,$u,'community_member_'.$op,'Mitglied '.$id.': '.$op);
        $cmOut($r);
    }
    elvado_json(['status'=>'error','message'=>'Unbekannte Aktion'],404);
}
// Umfragen des CMS: öffentlich anzeigen/abstimmen, im CMS verwalten
if($action==='poll_get'){
    $id=(string)($_GET['id']??'');$client=(string)($_GET['client']??'');$polls=elvado_polls_load($dataDir);$p=null;
    if($id!==''){foreach($polls as $x)if(($x['id']??'')===$id){$p=$x;break;}}
    else{foreach(array_reverse($polls) as $x)if(elvado_poll_state($x)==='open'){$p=$x;break;}}      // ohne ID: die neueste offene Umfrage
    if(!$p)elvado_json(['status'=>'ok','poll'=>null]);
    elvado_json(['status'=>'ok','poll'=>elvado_poll_public($p,elvado_poll_has_voted($dataDir,(string)$p['id'],$client))]);
}
if($action==='poll_vote'){
    $b=elvado_body();$ip=trim(explode(',',(string)($_SERVER['HTTP_X_FORWARDED_FOR']??$_SERVER['REMOTE_ADDR']??''))[0]);
    $r=elvado_poll_vote($dataDir,(string)($b['poll_id']??''),array_map('strval',(array)($b['options']??[])),(string)($b['client']??''),$ip);
    if(!$r['ok'])elvado_json(['status'=>'error','message'=>$r['message']],$r['code']);
    elvado_json(['status'=>'ok','message'=>$r['message'],'poll'=>elvado_poll_public($r['poll'],true)]);
}
if($action==='polls_list'){
    elvado_auth(false);
    elvado_json(['status'=>'ok','polls'=>array_values(array_map(fn($p)=>$p+['state'=>elvado_poll_state($p),'total'=>elvado_poll_total($p)],elvado_polls_load($dataDir)))]);
}
if($action==='poll_save'){
    $u=elvado_auth(false);$b=elvado_body();$polls=elvado_polls_load($dataDir);$idx=null;
    foreach($polls as $i=>$p)if(($p['id']??'')===(string)($b['id']??'')){$idx=$i;break;}
    $clean=elvado_poll_clean($b,$idx!==null?$polls[$idx]:null);
    if($clean===null)elvado_json(['status'=>'error','message'=>'Bitte eine Frage und mindestens zwei verschiedene Antworten angeben.'],400);
    if($idx===null)$polls[]=$clean;else $polls[$idx]=$clean;
    try{elvado_polls_save($dataDir,$polls);}catch(Throwable $e){elvado_json(['status'=>'error','message'=>'Speichern fehlgeschlagen'],500);}
    elvado_log_activity($activityLogFile,$u,$idx===null?'poll_create':'poll_update','Umfrage „'.$clean['question'].'“ '.($idx===null?'angelegt':'gespeichert'));
    elvado_json(['status'=>'ok','poll'=>$clean+['state'=>elvado_poll_state($clean),'total'=>elvado_poll_total($clean)]]);
}
if($action==='poll_delete'||$action==='poll_reset'){
    $u=elvado_auth(false);$b=elvado_body();$id=(string)($b['id']??'');$polls=elvado_polls_load($dataDir);$found=false;
    foreach($polls as $i=>&$p)if(($p['id']??'')===$id){
        $found=true;
        if($action==='poll_delete'){elvado_log_activity($activityLogFile,$u,'poll_delete','Umfrage „'.$p['question'].'“ gelöscht');unset($polls[$i]);}
        else{foreach($p['options'] as &$o)$o['votes']=0;unset($o);elvado_log_activity($activityLogFile,$u,'poll_reset','Stimmen der Umfrage „'.$p['question'].'“ zurückgesetzt');}
        break;
    }unset($p);
    if(!$found)elvado_json(['status'=>'error','message'=>'Umfrage nicht gefunden'],404);
    try{elvado_polls_save($dataDir,$polls);elvado_poll_voters_drop($dataDir,$id);}catch(Throwable $e){elvado_json(['status'=>'error','message'=>'Speichern fehlgeschlagen'],500);}
    elvado_json(['status'=>'ok']);
}
if($action==='polls_export'){
    elvado_auth(false);$id=(string)($_GET['id']??'');
    foreach(elvado_polls_load($dataDir) as $p)if(($p['id']??'')===$id){
        header_remove('Content-Type');header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="umfrage-'.$id.'.csv"');echo elvado_polls_csv($p);exit;
    }
    elvado_json(['status'=>'error','message'=>'Umfrage nicht gefunden'],404);
}
// Formulare (Kontaktformular-/Newsletter-Widget): öffentliche Einsendung und Verwaltung der Einsendungen
if($action==='form_submit'){
    $b=elvado_body();$ip=trim(explode(',',(string)($_SERVER['HTTP_X_FORWARDED_FOR']??$_SERVER['REMOTE_ADDR']??''))[0]);
    $r=elvado_forms_submit($dataDir,$site,$b,$ip);
    elvado_json(['status'=>$r['ok']?'ok':'error','message'=>$r['message']],$r['code']);
}
if($action==='forms_list'){
    elvado_auth(false);$items=array_reverse(elvado_forms_load($dataDir));
    $unread=0;foreach($items as $i)if(empty($i['read']))$unread++;
    elvado_json(['status'=>'ok','items'=>$items,'unread'=>$unread]);
}
if($action==='forms_update'){
    $u=elvado_auth(false);$b=elvado_body();$ids=array_flip(array_map('strval',(array)($b['ids']??[])));$op=(string)($b['op']??'');
    if(!$ids||!in_array($op,['read','unread','delete'],true))elvado_json(['status'=>'error','message'=>'Ungültige Anfrage'],400);
    $items=elvado_forms_load($dataDir);$n=0;$keep=[];
    foreach($items as $i){
        if(!isset($ids[(string)($i['id']??'')])){$keep[]=$i;continue;}
        $n++;if($op==='delete')continue;$i['read']=$op==='read';$keep[]=$i;
    }
    try{elvado_forms_save($dataDir,$keep);}catch(Throwable $e){elvado_json(['status'=>'error','message'=>'Speichern fehlgeschlagen'],500);}
    if($op==='delete')elvado_log_activity($activityLogFile,$u,'forms_delete',$n.' Einsendung(en) gelöscht');
    elvado_json(['status'=>'ok','changed'=>$n]);
}
if($action==='forms_export'){
    elvado_auth(true);$kind=($_GET['kind']??'')==='newsletter'?'newsletter':'contact';
    header_remove('Content-Type');header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="'.($kind==='newsletter'?'newsletter-anmeldungen':'kontaktanfragen').'-'.date('Y-m-d').'.csv"');
    echo elvado_forms_csv(elvado_forms_load($dataDir),$kind);exit;
}
// Werkzeuge: Wartungsmodus und Weiterleitungen/404-Protokoll (nur Administratoren, siehe lib/tools.php)
if($action==='maintenance_get'){
    elvado_auth(true);$cfg=elvado_maint_load($dataDir);
    if($cfg['key']===''){$cfg=elvado_maint_clean($cfg);try{elvado_tools_write(elvado_tools_dir($dataDir),'maintenance.json',$cfg);}catch(Throwable $e){}} // Vorschau-Schlüssel gleich beim ersten Öffnen festlegen
    elvado_json(['status'=>'ok','config'=>$cfg]);
}
if($action==='maintenance_save'){
    $u=elvado_auth(true);$b=elvado_body();$old=elvado_maint_load($dataDir);
    $new=elvado_maint_clean($b['config']??[],(string)$old['key']);
    if(!empty($b['new_key']))$new['key']=bin2hex(random_bytes(8));
    try{elvado_tools_write(elvado_tools_dir($dataDir),'maintenance.json',$new);}catch(Throwable $e){elvado_json(['status'=>'error','message'=>'Speichern fehlgeschlagen: '.$e->getMessage()],500);}
    if($new['enabled']!==$old['enabled'])elvado_log_activity($activityLogFile,$u,'maintenance_'.($new['enabled']?'on':'off'),'Wartungsmodus '.($new['enabled']?'eingeschaltet':'ausgeschaltet'));
    elvado_json(['status'=>'ok','config'=>$new]);
}
if($action==='redirects_get'){
    elvado_auth(true);$d=elvado_tools_dir($dataDir);
    elvado_json(['status'=>'ok','rules'=>elvado_redirects_clean(elvado_tools_read($d.'/redirects.json',['rules'=>[]])['rules']??[]),'log'=>array_values((array)(elvado_tools_read($d.'/404.json',['items'=>[]])['items']??[]))]);
}
if($action==='redirects_save'){
    $u=elvado_auth(true);$b=elvado_body();$rules=elvado_redirects_clean($b['rules']??[]);
    try{elvado_tools_write(elvado_tools_dir($dataDir),'redirects.json',['rules'=>$rules]);}catch(Throwable $e){elvado_json(['status'=>'error','message'=>'Speichern fehlgeschlagen: '.$e->getMessage()],500);}
    elvado_log_activity($activityLogFile,$u,'redirects_save',count($rules).' Weiterleitung(en) gespeichert');
    elvado_json(['status'=>'ok','rules'=>$rules]);
}
if($action==='redirects_log_clear'){
    $u=elvado_auth(true);try{elvado_tools_write(elvado_tools_dir($dataDir),'404.json',['items'=>[]]);}catch(Throwable $e){elvado_json(['status'=>'error','message'=>'Speichern fehlgeschlagen'],500);}
    elvado_log_activity($activityLogFile,$u,'redirects_log_clear','404-Protokoll geleert');elvado_json(['status'=>'ok']);
}
// Datenschutz-Werkzeuge (nur Administratoren): Daten zu einer Person finden, exportieren und Kommentare löschen
if($action==='privacy_overview'){
    elvado_auth(true);$cm=elvado_read_json($commentsFile,[]);$pend=0;foreach($cm as $c)if(($c['status']??'')==='pending')$pend++;
    elvado_json(['status'=>'ok','overview'=>[
        'comments'=>count($cm),'comments_pending'=>$pend,
        'users'=>count(elvado_local_users()),
        'activity'=>count(elvado_read_json($activityLogFile,[])),'notifications'=>count(elvado_read_json($notificationsFile,[])),
        'not_found_log'=>count((array)(elvado_tools_read(elvado_tools_dir($dataDir).'/404.json',['items'=>[]])['items']??[])),
    ]]);
}
if($action==='privacy_search'){
    elvado_auth(true);$b=elvado_body();$q=mb_strtolower(trim((string)($b['q']??'')));
    if(mb_strlen($q)<2)elvado_json(['status'=>'error','message'=>'Bitte mindestens 2 Zeichen eingeben'],400);
    $has=fn($v)=>str_contains(mb_strtolower((string)$v),$q);
    $newsList=elvado_read_json($newsFile,[]);
    $titles=[];foreach($newsList as $a)$titles[(int)($a['id']??0)]=(string)($a['title']??'');
    $comments=[];foreach(elvado_read_json($commentsFile,[]) as $c)if($has($c['name']??'')||$has($c['text']??''))$comments[]=['id'=>(int)($c['id']??0),'article_id'=>(int)($c['article_id']??0),'article_title'=>$titles[(int)($c['article_id']??0)]??'','name'=>(string)($c['name']??''),'text'=>(string)($c['text']??''),'status'=>(string)($c['status']??''),'created_at'=>(string)($c['created_at']??''),'is_staff'=>!empty($c['is_staff'])];
    $users=[];foreach(elvado_local_users() as $u)if($has($u['username']??'')||$has($u['display_name']??'')||$has($u['email']??''))$users[]=['username'=>(string)($u['username']??''),'display_name'=>(string)($u['display_name']??''),'email'=>(string)($u['email']??''),'role'=>(string)($u['role']??''),'created_at'=>(string)($u['created_at']??'')];
    $activity=0;foreach(elvado_read_json($activityLogFile,[]) as $e)if($has($e['user']??''))$activity++;
    $articles=[];foreach($newsList as $a)if($has($a['author']??''))$articles[]=['id'=>(int)($a['id']??0),'title'=>(string)($a['title']??''),'author'=>(string)($a['author']??'')];
    $mem=[];foreach(elvado_cm_members($dataDir) as $m)if($has($m['username']??'')||$has($m['display_name']??'')||$has($m['email']??''))$mem[]=['id'=>(string)$m['id'],'username'=>(string)$m['username'],'name'=>(string)($m['display_name']??''),'email'=>(string)$m['email'],'status'=>(string)($m['status']??''),'created_at'=>(string)($m['created_at']??'')];
    $forms=[];foreach(elvado_forms_load($dataDir) as $f){$d=(array)($f['data']??[]);if($has($d['name']??'')||$has($d['email']??'')||$has($d['message']??''))$forms[]=['id'=>(string)($f['id']??''),'kind'=>(string)($f['kind']??''),'name'=>(string)($d['name']??''),'email'=>(string)($d['email']??''),'text'=>mb_substr((string)($d['message']??''),0,200),'created_at'=>(string)($f['created_at']??'')];}
    elvado_json(['status'=>'ok','q'=>$q,'comments'=>$comments,'forms'=>$forms,'members'=>$mem,'users'=>$users,'activity_entries'=>$activity,'articles'=>array_slice($articles,0,100)]);
}
if($action==='privacy_comments_delete'){
    $u=elvado_auth(true);$b=elvado_body();$ids=array_values(array_unique(array_filter(array_map('intval',(array)($b['ids']??[])),fn($i)=>$i>0)));
    if(!$ids)elvado_json(['status'=>'error','message'=>'Keine Kommentare ausgewählt'],400);
    $all=elvado_read_json($commentsFile,[]);$set=array_flip($ids);
    // Antworten auf gelöschte Kommentare gehen mit, damit keine verwaisten Unterkommentare bleiben
    $del=$set;do{$grew=false;foreach($all as $c){if(isset($del[(int)($c['parent_id']??0)])&&!isset($del[(int)($c['id']??0)])){$del[(int)$c['id']]=1;$grew=true;}}}while($grew);
    $keep=array_values(array_filter($all,fn($c)=>!isset($del[(int)($c['id']??0)])));
    $removed=count($all)-count($keep);
    elvado_write_atomic($commentsFile,json_encode($keep,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    elvado_log_activity($activityLogFile,$u,'privacy_comments_delete',$removed.' Kommentar(e) aus Datenschutzgründen gelöscht');
    elvado_json(['status'=>'ok','removed'=>$removed]);
}
// Seiten: Versionen (die letzten 10 Fassungen je Seite)
if($action==='visibility_report'){
    elvado_auth(false);require_once __DIR__.'/lib/visibility.php';$vn=elvado_read_json($newsFile,[]);
    elvado_json(['status'=>'ok']+elvado_visibility_report(is_array($site)?$site:[],is_array($vn)?$vn:[]));
}
if($action==='pages_revisions'){
    elvado_auth(false);$id=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($_GET['id']??''));
    $list=elvado_tools_read(elvado_page_revisions_file($dataDir),['pages'=>[]])['pages'][$id]??[];
    elvado_json(['status'=>'ok','revisions'=>array_values(array_map(fn($r,$i)=>['index'=>$i,'saved_at'=>(string)($r['saved_at']??''),'user'=>(string)($r['user']??''),'title'=>(string)($r['title']??'')],(array)$list,array_keys((array)$list)))]);
}
if($action==='pages_revision'){
    elvado_auth(false);$id=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($_GET['id']??''));$i=(int)($_GET['index']??-1);
    $list=elvado_tools_read(elvado_page_revisions_file($dataDir),['pages'=>[]])['pages'][$id]??[];
    if(!isset($list[$i]['page']))elvado_json(['status'=>'error','message'=>'Version nicht gefunden'],404);
    elvado_json(['status'=>'ok','page'=>$list[$i]['page']]);
}
if($action==='services_status'){
    // Eigene Dienste des Betreibers: Erreichbarkeit serverseitig prüfen (nur Administratoren, mit Stundenlimit)
    elvado_auth(true);require_once __DIR__.'/lib/services.php';
    if(!elvado_apps_rate_ok($dataDir,'services',120))elvado_json(['status'=>'error','message'=>'Zu viele Prüfungen – bitte später erneut versuchen.'],429);
    elvado_json(['status'=>'ok','services'=>elvado_services_probe((array)(elvado_services_clean((array)($site['services']??[]))['items']),elvado_site_origin($site))]);
}
if($action==='feed_test'){
    elvado_auth(false);$b=elvado_body();$source=is_array($b['source']??null)?$b['source']:[];
    $url=trim((string)($source['url']??''));if($url==='')elvado_json(['status'=>'error','message'=>'Bitte zuerst eine Feed-URL eintragen'],400);
    $tmp=['feed_sources'=>[[
        'id'=>'test','name'=>(string)($source['name']??'Test-Feed'),'url'=>$url,'category'=>(string)($source['category']??'Extern'),
        'enabled'=>true,'max_items'=>max(1,min(10,(int)($source['max_items']??5)))
    ]]];
    $rows=elvado_external_feed_articles($tmp);
    if(!$rows)elvado_json(['status'=>'error','message'=>'Feed konnte nicht gelesen werden oder enthält keine unterstützten RSS-/Atom-Einträge'],422);
    elvado_json(['status'=>'ok','count'=>count($rows),'sample'=>array_slice(array_map(fn($x)=>['title'=>$x['title']??'','published_at'=>$x['published_at']??'','external_url'=>$x['external_url']??''], $rows),0,3)]);
}

// File-based News
$news=elvado_read_json($newsFile,[]);
// Papierkorb wie bei WordPress nach 30 Tagen automatisch endgültig leeren - lazy bei jedem
// Request geprüft (kein echter Cron auf diesem Hosting nötig), betrifft nur alte Einträge.
$newsTrashCutoff=date('Y-m-d H:i:s',strtotime('-30 days'));
$newsPurged=array_values(array_filter($news,fn($a)=>empty($a['deleted_at'])||(string)$a['deleted_at']>$newsTrashCutoff));
if(count($newsPurged)!==count($news)){
    $news=$newsPurged;
    elvado_write_atomic($newsFile,json_encode($news,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    elvado_log_activity($activityLogFile,null,'news_trash_auto_purge','Papierkorb automatisch geleert (Beiträge älter als 30 Tage)');
}
$newsViews=elvado_read_json($newsViewsFile,[]);
if($action==='news_public'){
    $published=array_values(array_filter($news,'elvado_news_is_live'));
    $published=array_map(fn($a)=>$a+['views'=>elvado_news_view_count($newsViews,(int)($a['id']??0))],$published);
    $published=array_merge($published,elvado_external_feed_articles($site));
    usort($published,'elvado_news_cmp_desc');
    foreach($published as &$pa)if(isset($pa['body_html'])&&is_string($pa['body_html'])&&str_contains($pa['body_html'],'['))$pa['body_html']=elvado_expand_shortcodes($pa['body_html']);unset($pa);
    $slug=trim((string)($_GET['slug']??''));
    if($slug!==''){
        foreach($published as $a)if((string)($a['slug']??'')===$slug)elvado_json(['status'=>'ok','article'=>$a]);
        elvado_json(['status'=>'error','message'=>'Artikel nicht gefunden'],404);
    }
    $limit=max(1,min(100,(int)($_GET['limit']??50)));
    elvado_json(['status'=>'ok','articles'=>array_slice($published,0,$limit),'external_sources'=>count(array_filter((array)($site['feed_sources']??[]),fn($s)=>!empty($s['enabled'])))]);
}
$newsAuth=null;
if(in_array($action,['news_list','news_get','news_save','news_quick_edit','news_delete','news_thumbnail_upload','news_trash_list','news_restore','news_delete_permanent','news_bulk_action','news_category_rename','news_revisions_list','news_revision_restore','news_duplicate','news_export','news_import','news_tags','news_tag_change'],true))$newsAuth=elvado_auth(false);
if($action==='news_list')elvado_json(['status'=>'ok','articles'=>array_values(array_map(fn($a)=>$a+['can_edit'=>elvado_news_can_edit($a,$newsAuth),'views'=>elvado_news_view_count($newsViews,(int)($a['id']??0))],array_filter($news,fn($a)=>empty($a['deleted_at'])))),'can_edit'=>true]);
if($action==='news_trash_list')elvado_json(['status'=>'ok','articles'=>array_values(array_map(fn($a)=>$a+['can_edit'=>elvado_news_can_edit($a,$newsAuth)],array_filter($news,fn($a)=>!empty($a['deleted_at'])))),'can_edit'=>true]);
if($action==='news_tags'){
    // Schlagwörter stehen als Komma-Liste an jedem Beitrag; hier werden sie wie in WordPress als Übersicht mit Zählern zusammengefasst (Groß-/Kleinschreibung egal)
    $by=[];
    foreach($news as $a){
        if(!empty($a['deleted_at']))continue;$seen=[];
        foreach(elvado_news_tag_list((string)($a['tags']??'')) as $t){$k=mb_strtolower($t);if(isset($seen[$k]))continue;$seen[$k]=1;
            $by[$k]=$by[$k]??['tag'=>$t,'count'=>0,'live'=>0,'spell'=>[]];$by[$k]['count']++;if(($a['status']??'')==='published')$by[$k]['live']++;$by[$k]['spell'][$t]=($by[$k]['spell'][$t]??0)+1;}
    }
    foreach($by as &$r){arsort($r['spell']);$r['tag']=(string)array_key_first($r['spell']);unset($r['spell']);}unset($r);
    $out=array_values($by);usort($out,fn($x,$y)=>$y['count']<=>$x['count']?:strcasecmp($x['tag'],$y['tag']));
    elvado_json(['status'=>'ok','tags'=>$out]);
}
if($action==='news_tag_change'){
    // Umbenennen, Zusammenführen (Ziel existiert schon) oder Löschen (Ziel leer) – nur bei Beiträgen, die der Benutzer bearbeiten darf
    $b=elvado_body();$from=trim((string)($b['from']??''));$to=mb_substr(trim(str_replace(',',' ',(string)($b['to']??''))),0,60);
    if($from==='')elvado_json(['status'=>'error','message'=>'Schlagwort fehlt'],400);
    $fk=mb_strtolower($from);$tk=mb_strtolower($to);$changed=0;$denied=0;
    foreach($news as &$a){
        $list=elvado_news_tag_list((string)($a['tags']??''));
        $has=false;foreach($list as $t)if(mb_strtolower($t)===$fk){$has=true;break;}
        if(!$has)continue;
        if(!elvado_news_can_edit($a,$newsAuth)){$denied++;continue;}
        $new=[];$seen=[];
        foreach($list as $t){
            $x=mb_strtolower($t)===$fk?$to:$t;if($x==='')continue;
            $k=mb_strtolower($x);if(isset($seen[$k]))continue;$seen[$k]=1;$new[]=$x;
        }
        $a['tags']=mb_substr(implode(',',$new),0,800);$a['updated_at']=date('Y-m-d H:i:s');$changed++;
    }unset($a);
    if($changed>0){
        elvado_write_atomic($newsFile,json_encode($news,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
        elvado_log_activity($activityLogFile,$newsAuth,'news_tag_change','Schlagwort „'.$from.'“ '.($to===''?'gelöscht':($tk===$fk?'umbenannt in „'.$to.'“':'zu „'.$to.'“ geändert/zusammengeführt')).' ('.$changed.' Beitrag/Beiträge)');
    }
    elvado_json(['status'=>'ok','changed'=>$changed,'denied'=>$denied]);
}
if($action==='news_export'){
    $rows=array_values(array_filter($news,fn($a)=>empty($a['deleted_at'])));
    $filename='beitraege-'.date('Y-m-d').'.json';
    header_remove('Content-Type');header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="'.$filename.'"');
    echo json_encode(['exported_at'=>date('c'),'site'=>preg_replace('#^https?://#','',elvado_site_origin($site)),'count'=>count($rows),'articles'=>$rows],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
if($action==='news_import'){
    // Gegenstück zu news_export: übernimmt Beiträge aus einer Exportdatei (neue IDs, Texte werden wie beim Speichern bereinigt)
    require_once __DIR__.'/wp/settings-api.php';$defCat=elvado_wps_default('elvado_default_category','News');$b=elvado_body();$in=$b['articles']??null;
    if(!is_array($in)||!$in)elvado_json(['status'=>'error','message'=>'Keine Beiträge in der Datei gefunden'],400);
    if(count($in)>200)elvado_json(['status'=>'error','message'=>'Höchstens 200 Beiträge pro Import'],400);
    $mode=($b['on_duplicate']??'skip')==='copy'?'copy':'skip';$asDraft=array_key_exists('as_draft',$b)?!empty($b['as_draft']):elvado_wps_default('elvado_default_status','draft')!=='published';
    $now=date('Y-m-d H:i:s');$nextId=1;$slugs=[];foreach($news as $a){$nextId=max($nextId,(int)($a['id']??0)+1);$slugs[(string)($a['slug']??'')]=1;}
    $made=0;$skipped=0;$bad=0;
    foreach($in as $r){
        if(!is_array($r)){$bad++;continue;}
        $title=mb_substr(trim(strip_tags((string)($r['title']??''))),0,255);if($title===''){$bad++;continue;}
        $slug=elvado_slug((string)($r['slug']??'')!==''?(string)$r['slug']:$title);
        if(isset($slugs[$slug])){
            if($mode==='skip'){$skipped++;continue;}
            $base=$slug;$n=2;while(isset($slugs[$slug]))$slug=$base.'-'.($n++);
        }
        $slugs[$slug]=1;
        $pub=trim((string)($r['published_at']??''));if(!preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/',$pub))$pub=$now;else $pub=str_replace('T',' ',substr($pub,0,19));
        $news[]=['id'=>$nextId++,'slug'=>$slug,'title'=>$title,'category'=>mb_substr(trim((string)($r['category']??$defCat)),0,80)?:$defCat,'excerpt'=>mb_substr((string)($r['excerpt']??''),0,600),'image_url'=>mb_substr((string)($r['image_url']??''),0,1200),'image_mode'=>in_array(($r['image_mode']??'thumbnail'),['thumbnail','article','both','none'],true)?($r['image_mode']??'thumbnail'):'thumbnail','external_url'=>mb_substr((string)($r['external_url']??''),0,1200),'video_url'=>mb_substr((string)($r['video_url']??''),0,1200),'tags'=>mb_substr((string)($r['tags']??''),0,800),'embed_html'=>elvado_safe_html((string)($r['embed_html']??'')),'status'=>(!$asDraft&&($r['status']??'')==='published')?'published':'draft','featured'=>!empty($r['featured'])?1:0,'published_at'=>$pub,'body_html'=>elvado_safe_html((string)($r['body_html']??'')),'author'=>$newsAuth['display_name']??elvado_product_title(),'author_user'=>$newsAuth['user']??'','seo_title'=>mb_substr(trim((string)($r['seo_title']??'')),0,70),'seo_description'=>mb_substr(trim((string)($r['seo_description']??'')),0,200),'updated_at'=>$now,'created_at'=>$now];
        $made++;
    }
    if($made>0){
        elvado_write_atomic($newsFile,json_encode($news,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
        if(!isset($site['rss']['enabled'])||!empty($site['rss']['enabled']))elvado_write_atomic($root.'/rss.xml',elvado_rss_xml($site));
        elvado_log_activity($activityLogFile,$newsAuth,'news_import',$made.' Beitrag/Beiträge importiert'.($skipped?', '.$skipped.' übersprungen (Adresse schon vorhanden)':''));
    }
    elvado_json(['status'=>'ok','imported'=>$made,'skipped'=>$skipped,'invalid'=>$bad]);
}
if($action==='news_get'){ $id=(int)($_GET['id']??0);foreach($news as $a)if((int)($a['id']??0)===$id)elvado_json(['status'=>'ok','article'=>$a+['views'=>elvado_news_view_count($newsViews,$id)]]);elvado_json(['status'=>'error','message'=>'Beitrag nicht gefunden'],404); }
if($action==='news_track_view'){
    $b=elvado_body();$id=(int)($b['article_id']??0);
    $article=null;foreach($news as $a)if((int)($a['id']??0)===$id){$article=$a;break;}
    if($article===null||!elvado_news_is_live($article))elvado_json(['status'=>'ok']); // still: keine Fehlermeldung an Besucher für einen simplen Zähler
    elvado_news_track_view($newsViewsFile,$id);
    elvado_json(['status'=>'ok']);
}
if($action==='news_thumbnail_upload')elvado_json(['status'=>'ok','url'=>elvado_upload('news',8388608)]);
if($action==='news_delete'){ elvado_np_do('content_saved','news');$b=elvado_body();$id=(int)($b['id']??0);$now=date('Y-m-d H:i:s');$found=false;$title='';foreach($news as &$a)if((int)($a['id']??0)===$id){if(!elvado_news_can_edit($a,$newsAuth))elvado_json(['status'=>'error','message'=>'Keine Berechtigung für diesen Beitrag'],403);$a['deleted_at']=$now;$found=true;$title=(string)($a['title']??'');break;}unset($a);if(!$found)elvado_json(['status'=>'error','message'=>'Beitrag nicht gefunden'],404);elvado_write_atomic($newsFile,json_encode($news,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");if(!isset($site['rss']['enabled'])||!empty($site['rss']['enabled']))elvado_write_atomic($root.'/rss.xml',elvado_rss_xml($site));elvado_log_activity($activityLogFile,$newsAuth,'news_trash','„'.$title.'“ in den Papierkorb verschoben');elvado_json(['status'=>'ok']);}
if($action==='news_restore'){ $b=elvado_body();$id=(int)($b['id']??0);$found=false;$title='';foreach($news as &$a)if((int)($a['id']??0)===$id){if(!elvado_news_can_edit($a,$newsAuth))elvado_json(['status'=>'error','message'=>'Keine Berechtigung für diesen Beitrag'],403);unset($a['deleted_at']);$found=true;$title=(string)($a['title']??'');break;}unset($a);if(!$found)elvado_json(['status'=>'error','message'=>'Beitrag nicht gefunden'],404);elvado_write_atomic($newsFile,json_encode($news,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");if(!isset($site['rss']['enabled'])||!empty($site['rss']['enabled']))elvado_write_atomic($root.'/rss.xml',elvado_rss_xml($site));elvado_log_activity($activityLogFile,$newsAuth,'news_restore','„'.$title.'“ aus dem Papierkorb wiederhergestellt');elvado_json(['status'=>'ok']);}
if($action==='news_delete_permanent'){ $delAuth=elvado_auth(true);$b=elvado_body();$id=(int)($b['id']??0);$before=count($news);$title='';foreach($news as $a)if((int)($a['id']??0)===$id){$title=(string)($a['title']??'');break;}$news=array_values(array_filter($news,fn($a)=>(int)($a['id']??0)!==$id||empty($a['deleted_at'])));if(count($news)===$before)elvado_json(['status'=>'error','message'=>'Beitrag nicht im Papierkorb'],404);elvado_write_atomic($newsFile,json_encode($news,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");elvado_log_activity($activityLogFile,$delAuth,'news_delete_permanent','„'.$title.'“ endgültig gelöscht');elvado_json(['status'=>'ok']);}
if($action==='news_bulk_action'){
    $b=elvado_body();$op=(string)($b['op']??'');$ids=array_map('intval',(array)($b['ids']??[]));$ids=array_values(array_filter($ids,fn($x)=>$x>0));
    if(!$ids)elvado_json(['status'=>'error','message'=>'Keine Beiträge ausgewählt'],400);
    $value=(string)($b['value']??'');
    if(!in_array($op,['trash','restore','delete_permanent','publish','draft','feature','unfeature','set_category'],true))elvado_json(['status'=>'error','message'=>'Unbekannte Aktion'],400);
    if($op==='delete_permanent')elvado_auth(true);
    if($op==='set_category'&&trim($value)==='')elvado_json(['status'=>'error','message'=>'Bitte eine Kategorie auswählen'],400);
    $now=date('Y-m-d H:i:s');$affected=0;
    if($op==='delete_permanent'){
        $before=count($news);
        $news=array_values(array_filter($news,fn($a)=>!(in_array((int)($a['id']??0),$ids,true)&&!empty($a['deleted_at']))));
        $affected=$before-count($news);
    } else {
        foreach($news as &$a){
            if(!in_array((int)($a['id']??0),$ids,true))continue;
            if(!elvado_news_can_edit($a,$newsAuth))continue;
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
    elvado_write_atomic($newsFile,json_encode($news,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    if(in_array($op,['trash','restore','publish','draft'],true)&&(!isset($site['rss']['enabled'])||!empty($site['rss']['enabled'])))elvado_write_atomic($root.'/rss.xml',elvado_rss_xml($site));
    if($affected>0)elvado_log_activity($activityLogFile,$newsAuth,'news_bulk_'.$op,$affected.' Beitrag/Beiträge per Massenaktion „'.$op.'“ bearbeitet');
    elvado_json(['status'=>'ok','affected'=>$affected]);
}
if($action==='news_category_rename'){
    // Kategorie umbenennen: aktualisiert sowohl die Kategorie-Liste (site.json) als auch alle
    // bereits vorhandenen Beiträge, die dieser Kategorie zugewiesen sind - sonst würden sie
    // stillschweigend auf einen nicht mehr existierenden Namen zeigen (anders als bei WordPress'
    // ID-basierten Taxonomien ist "Kategorie" hier ein reiner Freitext-Wert pro Beitrag).
    $b=elvado_body();$old=trim((string)($b['old']??''));$new=mb_substr(trim((string)($b['new']??'')),0,40);
    if($old===''||$new==='')elvado_json(['status'=>'error','message'=>'Alter und neuer Name dürfen nicht leer sein'],400);
    $cats=(array)($site['news_categories']??[]);
    if(!in_array($old,$cats,true))elvado_json(['status'=>'error','message'=>'Kategorie nicht gefunden'],404);
    if($old!==$new&&in_array($new,$cats,true))elvado_json(['status'=>'error','message'=>'Eine Kategorie mit diesem Namen existiert bereits'],400);
    $cats=array_values(array_map(fn($c)=>$c===$old?$new:$c,$cats));
    $site['news_categories']=$cats;
    $affected=0;
    foreach($news as &$a){if(($a['category']??'')===$old){$a['category']=$new;$affected++;}}unset($a);
    elvado_write_atomic($newsFile,json_encode($news,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    elvado_publish($site,$siteFile,$genDir,$root);
    elvado_log_activity($activityLogFile,$newsAuth,'news_category_rename','Kategorie „'.$old.'“ in „'.$new.'“ umbenannt ('.$affected.' Beitrag/Beiträge aktualisiert)');
    elvado_json(['status'=>'ok','categories'=>$cats,'affected'=>$affected]);
}
if($action==='news_duplicate'){
    $b=elvado_body();$id=(int)($b['id']??0);$src=null;foreach($news as $a)if((int)($a['id']??0)===$id){$src=$a;break;}
    if($src===null)elvado_json(['status'=>'error','message'=>'Beitrag nicht gefunden'],404);
    if(!elvado_news_can_edit($src,$newsAuth))elvado_json(['status'=>'error','message'=>'Keine Berechtigung für diesen Beitrag'],403);
    $newId=1;foreach($news as $a)$newId=max($newId,(int)($a['id']??0)+1);
    $now=date('Y-m-d H:i:s');
    $copy=$src;$copy['id']=$newId;$copy['title']=trim((string)($src['title']??'')).' (Kopie)';
    $copy['slug']=elvado_slug($copy['title'].'-'.$newId);
    $copy['status']='draft';$copy['featured']=0;$copy['published_at']=$now;$copy['created_at']=$now;$copy['updated_at']=$now;
    $copy['author']=$newsAuth['display_name']??($src['author']??elvado_product_title());$copy['author_user']=$newsAuth['user']??'';
    unset($copy['deleted_at']);
    $news[]=$copy;
    elvado_write_atomic($newsFile,json_encode($news,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    elvado_log_activity($activityLogFile,$newsAuth,'news_duplicate','Beitrag „'.($src['title']??'').'“ als Entwurf dupliziert');
    elvado_json(['status'=>'ok','id'=>$newId,'article'=>$copy]);
}
if($action==='news_save'){
    $GLOBALS['elvado_html_unfiltered']=!empty($newsAuth['superadmin']);   // Raw-HTML-Blöcke bleiben nur bei Administratoren unverändert (nie in der Demo)
    $b=elvado_body();$id=(int)($b['id']??0);if($id<=0){$id=1;foreach($news as $a)$id=max($id,(int)($a['id']??0)+1);}
    $now=date('Y-m-d H:i:s');$existing=null;foreach($news as $a)if((int)($a['id']??0)===$id){$existing=$a;break;}
    if($existing!==null&&!elvado_news_can_edit($existing,$newsAuth))elvado_json(['status'=>'error','message'=>'Keine Berechtigung für diesen Beitrag'],403);
    $slug=elvado_slug((string)(($b['slug']??'')!==''?$b['slug']:($existing['slug']??$b['title']??'news')));
    $slug=elvado_news_unique_slug($news,$id,$slug);   // Adresse eindeutig halten
    $article=['id'=>$id,'slug'=>$slug,'title'=>mb_substr(trim((string)($b['title']??'')),0,255),'category'=>mb_substr((string)($b['category']??'News'),0,80),'excerpt'=>mb_substr((string)($b['excerpt']??''),0,600),'image_url'=>mb_substr((string)($b['image_url']??''),0,1200),'image_mode'=>in_array(($b['image_mode']??'thumbnail'),['thumbnail','article','both','none'],true)?($b['image_mode']??'thumbnail'):'thumbnail','external_url'=>mb_substr((string)($b['external_url']??''),0,1200),'video_url'=>mb_substr((string)($b['video_url']??''),0,1200),'tags'=>mb_substr((string)($b['tags']??''),0,800),'embed_html'=>elvado_safe_html((string)($b['embed_html']??'')),'status'=>($b['status']??'draft')==='published'?'published':'draft','featured'=>!empty($b['featured'])?1:0,'published_at'=>elvado_news_date($b['published_at']??'',$now),'body_html'=>elvado_safe_html((string)($b['body_html']??'')),'author'=>$existing['author']??($newsAuth['display_name']??elvado_product_title()),'author_user'=>$existing['author_user']??($newsAuth['user']??''),'updated_at'=>$now,'created_at'=>$now]+elvado_news_seo_fields($b);
    if($existing!==null)elvado_news_save_revision($revisionsFile,$id,$existing);
    if($existing!==null&&(string)($existing['slug']??'')!==''&&(string)$existing['slug']!==$slug)elvado_np_do('slug_changed','news',(string)$existing['slug'],$slug);   // Plugins (Weiterleitungen) reagieren auf geänderte Adressen
    elvado_np_do('content_saved','news');
    $found=false;foreach($news as &$a)if((int)($a['id']??0)===$id){$article['created_at']=$a['created_at']??$now;$a=$article;$found=true;break;}unset($a);if(!$found)$news[]=$article;
    elvado_write_atomic($newsFile,json_encode($news,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");if(!isset($site['rss']['enabled'])||!empty($site['rss']['enabled']))elvado_write_atomic($root.'/rss.xml',elvado_rss_xml($site));elvado_log_activity($activityLogFile,$newsAuth,$existing!==null?'news_update':'news_create',($existing!==null?'Beitrag „':'Neuer Beitrag „').$article['title'].'“ '.($existing!==null?'bearbeitet':'angelegt'));elvado_json(['status'=>'ok','id'=>$id]);
}
if($action==='news_quick_edit'){
    // Im Gegensatz zu news_save (baut den kompletten Artikel aus dem Request neu auf) ändert
    // Quick-Edit gezielt nur die übergebenen Felder am bestehenden Artikel - wie bei WordPress'
    // Schnellbearbeitung. So bleiben Artikeltext, SEO, Tags, Bild etc. unangetastet.
    $b=elvado_body();$id=(int)($b['id']??0);
    $idx=null;foreach($news as $i=>$a)if((int)($a['id']??0)===$id){$idx=$i;break;}
    if($idx===null)elvado_json(['status'=>'error','message'=>'Beitrag nicht gefunden'],404);
    if(!elvado_news_can_edit($news[$idx],$newsAuth))elvado_json(['status'=>'error','message'=>'Keine Berechtigung für diesen Beitrag'],403);
    elvado_news_save_revision($revisionsFile,$id,$news[$idx]);
    if(array_key_exists('title',$b))$news[$idx]['title']=mb_substr(trim((string)$b['title']),0,255);
    if(array_key_exists('category',$b))$news[$idx]['category']=mb_substr((string)$b['category'],0,80);
    if(array_key_exists('status',$b))$news[$idx]['status']=($b['status']==='published')?'published':'draft';
    if(array_key_exists('featured',$b))$news[$idx]['featured']=!empty($b['featured'])?1:0;
    if(array_key_exists('published_at',$b)&&($pubNorm=elvado_news_date($b['published_at']??''))!=='')$news[$idx]['published_at']=$pubNorm;
    if($news[$idx]['title']==='')elvado_json(['status'=>'error','message'=>'Titel darf nicht leer sein'],400);
    $news[$idx]['updated_at']=date('Y-m-d H:i:s');
    elvado_write_atomic($newsFile,json_encode($news,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    if(!isset($site['rss']['enabled'])||!empty($site['rss']['enabled']))elvado_write_atomic($root.'/rss.xml',elvado_rss_xml($site));
    elvado_log_activity($activityLogFile,$newsAuth,'news_quick_edit','Beitrag „'.$news[$idx]['title'].'“ per Schnellbearbeitung geändert');
    elvado_json(['status'=>'ok','article'=>$news[$idx]]);
}
if($action==='news_revisions_list'){
    $id=(int)($_GET['id']??0);$all=elvado_read_json($revisionsFile,[]);
    $revs=array_values(array_filter($all,fn($r)=>(int)($r['article_id']??0)===$id));
    usort($revs,fn($a,$b)=>strcmp((string)($b['saved_at']??''),(string)($a['saved_at']??'')));
    elvado_json(['status'=>'ok','revisions'=>array_map(fn($r)=>['revision_id'=>$r['revision_id'],'saved_at'=>$r['saved_at'],'title'=>$r['article']['title']??'','status'=>$r['article']['status']??''],$revs)]);
}
if($action==='news_revision_restore'){
    $b=elvado_body();$id=(int)($b['id']??0);$revisionId=(string)($b['revision_id']??'');
    $all=elvado_read_json($revisionsFile,[]);$target=null;
    foreach($all as $r)if((int)($r['article_id']??0)===$id&&(string)($r['revision_id']??'')===$revisionId){$target=$r['article'];break;}
    if($target===null)elvado_json(['status'=>'error','message'=>'Revision nicht gefunden'],404);
    $current=null;foreach($news as $a)if((int)($a['id']??0)===$id){$current=$a;break;}
    if($current===null)elvado_json(['status'=>'error','message'=>'Beitrag nicht gefunden'],404);
    if(!elvado_news_can_edit($current,$newsAuth))elvado_json(['status'=>'error','message'=>'Keine Berechtigung für diesen Beitrag'],403);
    elvado_news_save_revision($revisionsFile,$id,$current);
    $now=date('Y-m-d H:i:s');$restored=$target;$restored['id']=$id;$restored['created_at']=$current['created_at']??$now;$restored['updated_at']=$now;
    foreach($news as &$a)if((int)($a['id']??0)===$id){$a=$restored;break;}unset($a);
    elvado_write_atomic($newsFile,json_encode($news,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    if(!isset($site['rss']['enabled'])||!empty($site['rss']['enabled']))elvado_write_atomic($root.'/rss.xml',elvado_rss_xml($site));
    elvado_json(['status'=>'ok']);
}
// Kostenlose Kommentarfunktion für News-Beiträge (ein-/ausschaltbar, optional moderiert)
$commentsCfg=(array)($site['comments']??['enabled'=>false,'require_approval'=>true]);
if($action==='comments_settings')elvado_json(['status'=>'ok','enabled'=>!empty($commentsCfg['enabled']),'require_approval'=>!array_key_exists('require_approval',$commentsCfg)||!empty($commentsCfg['require_approval'])]);
if($action==='comments_recent'){
    $limit=max(1,min(10,(int)($_GET['limit']??5)));
    if(empty($commentsCfg['enabled']))elvado_json(['status'=>'ok','comments'=>[],'enabled'=>false]);
    $comments=array_values(array_filter(elvado_read_json($commentsFile,[]),fn($c)=>($c['status']??'')==='approved'));
    usort($comments,fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));
    $out=[];
    foreach(array_slice($comments,0,$limit) as $c){
        $art=null;foreach($news as $n)if((int)($n['id']??0)===(int)($c['article_id']??0)){$art=$n;break;}
        if($art===null||!elvado_news_is_live($art))continue;
        $out[]=['id'=>(int)($c['id']??0),'name'=>(string)($c['name']??''),'excerpt'=>mb_substr(trim((string)($c['text']??'')),0,120),'created_at'=>(string)($c['created_at']??''),'article_title'=>(string)($art['title']??''),'article_slug'=>(string)($art['slug']??'')];
    }
    elvado_json(['status'=>'ok','comments'=>$out,'enabled'=>true]);
}
if($action==='comments_list'){
    if(empty($commentsCfg['enabled']))elvado_json(['status'=>'ok','comments'=>[],'enabled'=>false]);
    $articleId=(int)($_GET['article_id']??0);
    $comments=elvado_read_json($commentsFile,[]);
    $out=array_values(array_filter($comments,fn($c)=>(int)($c['article_id']??0)===$articleId&&($c['status']??'')==='approved'));
    usort($out,fn($a,$b)=>strcmp((string)($a['created_at']??''),(string)($b['created_at']??'')));
    foreach($out as &$c){unset($c['status']);$c['parent_id']=(int)($c['parent_id']??0);$c['is_staff']=!empty($c['is_staff']);}unset($c);
    elvado_json(['status'=>'ok','comments'=>$out,'enabled'=>true]);
}
if($action==='comment_submit'){
    $b=elvado_body();
    $cArt=null;foreach($news as $a)if((int)($a['id']??0)===(int)($b['article_id']??0)){$cArt=$a;break;}
    $cMode=(string)($cArt['comments']??'default');   // je Beitrag: default = Einstellung der Website, open = immer, closed = nie
    if($cMode==='closed')elvado_json(['status'=>'error','message'=>'Kommentare sind für diesen Beitrag geschlossen'],403);
    if($cMode!=='open'&&empty($commentsCfg['enabled']))elvado_json(['status'=>'error','message'=>'Kommentare sind derzeit deaktiviert'],403);
    if(trim((string)($b['hp']??''))!=='')elvado_json(['status'=>'ok']); // Honeypot: Bots bekommen scheinbar Erfolg, es wird nichts gespeichert
    $articleId=(int)($b['article_id']??0);
    $article=null;foreach($news as $a)if((int)($a['id']??0)===$articleId){$article=$a;break;}
    if($article===null||!elvado_news_is_live($article))elvado_json(['status'=>'error','message'=>'Beitrag nicht gefunden'],404);
    $name=mb_substr(trim((string)($b['name']??'')),0,80);
    $text=mb_substr(trim(strip_tags((string)($b['text']??''))),0,2000);
    if($name===''||$text==='')elvado_json(['status'=>'error','message'=>'Bitte Name und Kommentar ausfüllen'],400);
    $comments=elvado_read_json($commentsFile,[]);
    $parentId=elvado_comment_top_parent($comments,(int)($b['parent_id']??0),$articleId);
    if($parentId===null)elvado_json(['status'=>'error','message'=>'Ursprünglicher Kommentar nicht gefunden'],404);
    $id=1;foreach($comments as $c)$id=max($id,(int)($c['id']??0)+1);
    $requireApproval=!array_key_exists('require_approval',$commentsCfg)||!empty($commentsCfg['require_approval']);
    $comment=['id'=>$id,'article_id'=>$articleId,'parent_id'=>$parentId,'name'=>$name,'text'=>$text,'is_staff'=>false,'status'=>$requireApproval?'pending':'approved','created_at'=>date('Y-m-d H:i:s')];
    $comments[]=$comment;
    elvado_write_atomic($commentsFile,json_encode($comments,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    $targetAuthor=(string)($article['author']??'');
    elvado_add_notification($notificationsFile,[
        'type'=>'comment','article_id'=>$articleId,'article_title'=>(string)($article['title']??''),
        'target_author'=>$targetAuthor,'comment_name'=>$name,
        'comment_excerpt'=>mb_substr($text,0,140),
    ]);
    $authorEmail=elvado_local_user_email_for($targetAuthor);
    if($authorEmail!=='')elvado_send_comment_notification_email($site,$authorEmail,(string)($article['title']??''),$name,mb_substr($text,0,140),elvado_cms_admin_url($site));
    elvado_json(['status'=>'ok','pending'=>$requireApproval]);
}
$commentAuth=null;
if(in_array($action,['comments_admin_list','comment_approve','comment_delete','comment_reply','notifications_list','notifications_mark_read'],true))$commentAuth=elvado_auth(false);
if($action==='comment_reply'){
    $b=elvado_body();$articleId=(int)($b['article_id']??0);$parentId=(int)($b['parent_id']??0);
    $article=null;foreach($news as $a)if((int)($a['id']??0)===$articleId){$article=$a;break;}
    if($article===null)elvado_json(['status'=>'error','message'=>'Beitrag nicht gefunden'],404);
    $comments=elvado_read_json($commentsFile,[]);
    $parentId=elvado_comment_top_parent($comments,$parentId,$articleId);
    if($parentId===null)elvado_json(['status'=>'error','message'=>'Ursprünglicher Kommentar nicht gefunden'],404);
    $text=mb_substr(trim(strip_tags((string)($b['text']??''))),0,2000);
    if($text==='')elvado_json(['status'=>'error','message'=>'Bitte einen Text eingeben'],400);
    $id=1;foreach($comments as $c)$id=max($id,(int)($c['id']??0)+1);
    $comment=['id'=>$id,'article_id'=>$articleId,'parent_id'=>$parentId,'name'=>(string)($commentAuth['display_name']??'Redaktion'),'text'=>$text,'is_staff'=>true,'status'=>'approved','created_at'=>date('Y-m-d H:i:s')];
    $comments[]=$comment;
    elvado_write_atomic($commentsFile,json_encode($comments,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    elvado_log_activity($activityLogFile,$commentAuth,'comment_reply','Antwort zu „'.(string)($article['title']??'').'“ verfasst');
    elvado_json(['status'=>'ok','id'=>$id]);
}
if($action==='notifications_list'){
    $all=elvado_read_json($notificationsFile,[]);
    usort($all,fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));
    $all=array_slice($all,0,50);
    $unread=count(array_filter($all,fn($n)=>empty($n['read'])));
    elvado_json(['status'=>'ok','notifications'=>$all,'unread'=>$unread]);
}
if($action==='notifications_mark_read'){
    $b=elvado_body();$id=(string)($b['id']??'');$all=(bool)($b['all']??false);
    $list=elvado_read_json($notificationsFile,[]);
    foreach($list as &$n){if($all||(string)($n['id']??'')===$id)$n['read']=true;}
    unset($n);
    elvado_write_atomic($notificationsFile,json_encode($list,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    elvado_json(['status'=>'ok']);
}
if($action==='comments_admin_list'){
    $comments=elvado_read_json($commentsFile,[]);
    $titleFor=function(int $id) use ($news): string { foreach($news as $a)if((int)($a['id']??0)===$id)return (string)($a['title']??''); return ''; };
    foreach($comments as &$c)$c['article_title']=$titleFor((int)($c['article_id']??0));unset($c);
    usort($comments,fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));
    elvado_json(['status'=>'ok','comments'=>$comments]);
}
if($action==='comment_approve'){
    $b=elvado_body();$id=(int)($b['id']??0);$comments=elvado_read_json($commentsFile,[]);$found=false;$name='';
    foreach($comments as &$c)if((int)($c['id']??0)===$id){$c['status']='approved';$found=true;$name=(string)($c['name']??'');break;}unset($c);
    if(!$found)elvado_json(['status'=>'error','message'=>'Kommentar nicht gefunden'],404);
    elvado_write_atomic($commentsFile,json_encode($comments,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    elvado_log_activity($activityLogFile,$commentAuth,'comment_approve','Kommentar von „'.$name.'“ freigegeben');
    elvado_json(['status'=>'ok']);
}
if($action==='comment_delete'){
    $b=elvado_body();$id=(int)($b['id']??0);$comments=elvado_read_json($commentsFile,[]);$before=count($comments);$name='';
    foreach($comments as $c)if((int)($c['id']??0)===$id){$name=(string)($c['name']??'');break;}
    $comments=array_values(array_filter($comments,fn($c)=>(int)($c['id']??0)!==$id));
    if(count($comments)===$before)elvado_json(['status'=>'error','message'=>'Kommentar nicht gefunden'],404);
    elvado_write_atomic($commentsFile,json_encode($comments,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    elvado_log_activity($activityLogFile,$commentAuth,'comment_delete','Kommentar von „'.$name.'“ gelöscht');
    elvado_json(['status'=>'ok']);
}
if($action==='dashboard_stats'){
    elvado_auth(false);
    $comments=elvado_read_json($commentsFile,[]);
    $newsStats=['published'=>0,'scheduled'=>0,'draft'=>0,'trash'=>0];
    foreach($news as $a){
        if(!empty($a['deleted_at'])){$newsStats['trash']++;continue;}
        if(($a['status']??'draft')!=='published'){$newsStats['draft']++;continue;}
        if(elvado_news_is_live($a))$newsStats['published']++; else $newsStats['scheduled']++;
    }
    $commentStats=['approved'=>0,'pending'=>0];
    foreach($comments as $c){
        if(($c['status']??'')==='pending')$commentStats['pending']++; else $commentStats['approved']++;
    }
    $topViewed=array_filter($news,fn($a)=>empty($a['deleted_at'])&&elvado_news_view_count($newsViews,(int)($a['id']??0))>0);
    $topViewed=array_map(fn($a)=>['id'=>$a['id'],'title'=>$a['title']??'','slug'=>$a['slug']??'','views'=>elvado_news_view_count($newsViews,(int)($a['id']??0))],$topViewed);
    usort($topViewed,fn($a,$b)=>$b['views']<=>$a['views']);
    elvado_json(['status'=>'ok','news'=>$newsStats,'comments'=>$commentStats,'top_viewed'=>array_slice($topViewed,0,5)]);
}
elvado_json(['status'=>'error','message'=>'Unbekannte Aktion'],404);

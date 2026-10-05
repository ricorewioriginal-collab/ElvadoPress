<?php
// WordPress-Kernressourcen (JavaScript-Pakete wie wp-element, wp-components, wp-api-fetch sowie jQuery und Dashicons) für Plugin-Seiten,
// die mit React gebaut sind (WooCommerce Admin, Yoast SEO …). Sie werden einmalig von wordpress.org geladen und nach cms/wp-core/ entpackt.
require_once __DIR__.'/installer.php';

function rrw_wp_core_dir(): string { return defined('RRW_WP_CORE_DIR')?RRW_WP_CORE_DIR:dirname(__DIR__).'/wp-core'; }
function rrw_wp_core_url(): string { return defined('RRW_WP_CORE_URL')?RRW_WP_CORE_URL:home_url('/cms/wp-core'); }
function rrw_wp_core_ready(): bool { return is_file(rrw_wp_core_dir().'/assets/script-loader-packages.php')&&is_file(rrw_wp_core_dir().'/js/dist/element.min.js'); }
function rrw_wp_core_status(): array {
    $d=rrw_wp_core_dir();$v=is_file($d.'/version.txt')?trim((string)file_get_contents($d.'/version.txt')):'';
    $rev=is_file($d.'/rev.txt')?(int)file_get_contents($d.'/rev.txt'):1;
    return ['installed'=>rrw_wp_core_ready()&&$rev>=RRW_WP_CORE_REV,'version'=>$v,'zip'=>class_exists('ZipArchive'),'writable'=>is_dir($d)?is_writable($d):is_writable(dirname($d))];
}
/** Welche Dateien aus dem WordPress-Paket gebraucht werden (Pfad relativ zu wp-includes/). */
/** Stand der mitgelieferten Dateiauswahl: erhöhen, wenn rrw_wp_core_wanted() wächst (bestehende Installationen bieten dann die Aktualisierung an). */
const RRW_WP_CORE_REV=2;
function rrw_wp_core_wanted(string $rel): bool {
    if(preg_match('#\.(map|php)$#',$rel))return $rel==='assets/script-loader-packages.php'||$rel==='php/class-wp-list-table.php';
    if(preg_match('#^js/dist/(vendor/)?[a-z0-9._-]+\.min\.js$#i',$rel))return true;
    if(preg_match('#^css/dist/[a-z0-9-]+/(style|common|content|default-editor-styles|editor-elements|classic|theme|editor)\.min\.css$#i',$rel))return true;
    if(preg_match('#^css/(dashicons|buttons)\.min\.css$#',$rel))return true;
    if(preg_match('#^fonts/dashicons\.(woff2|woff|ttf|eot)$#',$rel))return true;
    if(preg_match('#^js/(underscore|backbone|wp-util|hoverIntent|clipboard|json2|wp-a11y|utils|imagesloaded|masonry)\.min\.js$#',$rel))return true;
    if(preg_match('#^js/jquery/(jquery|jquery-migrate|jquery\.form|jquery\.color|jquery\.hotkeys|jquery\.query)\.min\.js$#',$rel))return true;
    if(preg_match('#^js/jquery/ui/[a-z0-9-]+\.min\.js$#',$rel))return true;
    if($rel==='js/jquery/jquery.ui.touch-punch.js')return true;
    if(preg_match('#^admin/(iris|color-picker|postbox|common)\.min\.js$#',$rel))return true;
    return false;
}
/** Lädt WordPress und entpackt die benötigten Ressourcen. @return array{ok:bool,message:string,files?:int,version?:string} */
function rrw_wp_core_install(?string $zipPath=null, ?string $version=null, bool $keepPrev=false): array {
    if(!class_exists('ZipArchive'))return ['ok'=>false,'message'=>'ZIP-Unterstützung fehlt auf dem Server'];
    $own=false;
    if($zipPath===null){
        // Passend zur gemeldeten WordPress-Version (Plugins sind dagegen getestet); ersatzweise die neueste
        $data=rrw_td_get('https://wordpress.org/wordpress-'.($version??RRW_WP_VERSION).'.zip',104857600,240);
        if(($data===null||strlen($data)<1000000)&&$version===null)$data=rrw_td_get('https://wordpress.org/latest.zip',104857600,240);
        if($data===null||strlen($data)<1000000)return ['ok'=>false,'message'=>'WordPress konnte nicht von wordpress.org geladen werden.'];
        $zipPath=tempnam(sys_get_temp_dir(),'rrwcore');file_put_contents($zipPath,$data);unset($data);$own=true;
    }
    $dest=rrw_wp_core_dir();$tmp=$dest.'.tmp-'.bin2hex(random_bytes(3));
    try{
        $z=new ZipArchive();if($z->open($zipPath)!==true)throw new RuntimeException('ZIP konnte nicht geöffnet werden');
        $count=0;$total=0;$prefix='wordpress/wp-includes/';
        if(!@mkdir($tmp,0755,true)&&!is_dir($tmp))throw new RuntimeException('Zielordner kann nicht angelegt werden');
        for($i=0;$i<$z->numFiles;$i++){
            $name=(string)$z->getNameIndex($i);if(str_ends_with($name,'/'))continue;
            if($name==='wordpress/wp-admin/includes/class-wp-list-table.php')$rel='php/class-wp-list-table.php';
            elseif(str_starts_with($name,'wordpress/wp-admin/js/'))$rel='admin/'.substr($name,strlen('wordpress/wp-admin/js/'));
            elseif(str_starts_with($name,$prefix))$rel=substr($name,strlen($prefix));
            else continue;if(rrw_wpi_clean_name($rel)===null||!rrw_wp_core_wanted($rel))continue;
            $st=$z->statIndex($i);$size=(int)($st['size']??0);$total+=$size;if($size>8388608||$total>80*1048576||++$count>3000)throw new RuntimeException('Das Paket ist größer als erwartet');
            $target=$tmp.'/'.$rel;if(!is_dir(dirname($target)))@mkdir(dirname($target),0755,true);
            $in=$z->getStream($name);if(!$in)continue;$out=fopen($target,'wb');stream_copy_to_stream($in,$out);fclose($out);fclose($in);
        }
        $ver='';$vf=$z->getFromName('wordpress/wp-includes/version.php');if($vf&&preg_match('/\$wp_version\s*=\s*\'([\d.]+)/',$vf,$m))$ver=$m[1];
        $z->close();
        if(!is_file($tmp.'/assets/script-loader-packages.php')||!is_file($tmp.'/js/dist/element.min.js'))throw new RuntimeException('Die benötigten Dateien fehlen im Paket');
        file_put_contents($tmp.'/version.txt',$ver."\n");file_put_contents($tmp.'/rev.txt',(string)RRW_WP_CORE_REV);
        file_put_contents($tmp.'/index.html','');
        file_put_contents($tmp.'/.htaccess',"# Skripte, Stile und Schriften des Rahmens (abgeschottet, daher ohne Herkunft) – PHP-Dateien sind nie direkt aufrufbar\n<IfModule mod_headers.c>\nHeader set Access-Control-Allow-Origin \"*\"\n</IfModule>\n<FilesMatch \"\\.php$\">\nRequire all denied\n</FilesMatch>\n");
        if(is_dir($dest)){
            $old=$dest.'.tmp-old'.bin2hex(random_bytes(2));if(!@rename($dest,$old))throw new RuntimeException('Alter Ordner kann nicht ersetzt werden');
            if($keepPrev){   // vorherige Fassung für „Zurücksetzen“ aufheben
                $prev=$dest.'.prev';
                if(is_dir($prev)){ $gone=$dest.'.tmp-gone'.bin2hex(random_bytes(2));if(@rename($prev,$gone))rrw_wpi_rm_tmp($gone); }
                if(!@rename($old,$prev)&&is_dir($old))rrw_wpi_rm_tmp($old);
            }
            else rrw_wpi_rm_tmp($old);
        }
        if(!@rename($tmp,$dest))throw new RuntimeException('Zielordner kann nicht ersetzt werden');
        return ['ok'=>true,'message'=>'WordPress-Kernressourcen '.($ver?:'').' installiert.','files'=>$count,'version'=>$ver];
    }catch(Throwable $e){
        if(is_dir($tmp))rrw_wpi_rm_tmp($tmp);
        return ['ok'=>false,'message'=>$e->getMessage()];
    } finally { if($own&&is_file($zipPath))@unlink($zipPath); }
}

/** Skripte und Stile registrieren (nur wenn die Ressourcen vorhanden sind). */
function rrw_wp_core_register(bool $force=false): void {
    static $done=false;if(($done&&!$force)||!rrw_wp_core_ready())return;$done=true;
    $d=rrw_wp_core_dir();$u=rrw_wp_core_url();$ver=trim((string)@file_get_contents($d.'/version.txt'))?:RRW_WP_VERSION;
    $reg=function(string $h,string $rel,array $deps=[],$v=null) use($d,$u,$ver){ if(is_file($d.'/'.$rel))wp_register_script($h,$u.'/'.$rel,$deps,$v??$ver,['in_footer'=>true]); };
    $reg('wp-polyfill','js/dist/vendor/wp-polyfill.min.js',['regenerator-runtime']);
    $reg('regenerator-runtime','js/dist/vendor/regenerator-runtime.min.js');
    foreach(['react','react-dom','react-jsx-runtime','lodash','moment'] as $v){
        $deps=match($v){'react'=>['wp-polyfill'],'react-dom'=>['react'],'react-jsx-runtime'=>['react'],default=>[]};
        $reg($v,'js/dist/vendor/'.$v.'.min.js',$deps);
    }
    $pk=include $d.'/assets/script-loader-packages.php';
    if(is_array($pk))foreach($pk as $file=>$info){
        $name=preg_replace('/\.js$/','',$file);if(!is_file($d.'/js/dist/'.$name.'.min.js'))continue;
        $deps=array_values(array_unique(array_merge((array)($info['dependencies']??[]),in_array($name,['i18n','hooks','url'],true)?[]:[])));
        $reg('wp-'.$name,'js/dist/'.$name.'.min.js',$deps,(string)($info['version']??$ver));
    }
    wp_add_inline_script('lodash','window.lodash = _.noConflict();','after');
    wp_add_inline_script('moment','moment.updateLocale('.wp_json_encode(substr(get_locale(),0,2)).', {});','after');
    // Wie in wp-admin ist der aktuelle Benutzer sofort bekannt (sonst rechnen Seiten mit einem leeren Benutzerobjekt)
    if(class_exists('WP_REST_Users_Controller')&&function_exists('get_current_user_id')&&get_current_user_id()){
        $me=(new WP_REST_Users_Controller())->get_current_item(new WP_REST_Request('GET','/wp/v2/users/me'));
        if(!is_wp_error($me))wp_add_inline_script('wp-core-data','window.wp&&wp.data&&wp.data.dispatch("core").receiveCurrentUser('.wp_json_encode($me->get_data()).');','after');
    }
    // Eigener Tab (z. B. Elementor): wpApiSettings wie von wp-api, gültig mit dem Nonce der Sitzung
    if(!empty($GLOBALS['rrw_wp_native_admin'])&&is_file($d.'/js/jquery/jquery.min.js')){
        $reg('jquery-core','js/jquery/jquery.min.js');
        wp_add_inline_script('jquery-core','var wpApiSettings='.wp_json_encode(['root'=>esc_url_raw(rest_url()),'nonce'=>wp_create_nonce('wp_rest'),'versionString'=>'wp/v2/']).';','before');
    }
    // jQuery & Co.
    $reg('jquery-core','js/jquery/jquery.min.js');$reg('jquery-migrate','js/jquery/jquery-migrate.min.js');
    if(is_file($d.'/js/jquery/jquery.min.js'))wp_register_script('jquery',false,['jquery-core','jquery-migrate'],$ver);
    $reg('underscore','js/underscore.min.js');$reg('backbone','js/backbone.min.js',['underscore','jquery']);$reg('wp-util','js/wp-util.min.js',['underscore','jquery']);
    $reg('imagesloaded','js/imagesloaded.min.js');$reg('masonry','js/masonry.min.js',['imagesloaded']);
    $reg('hoverIntent','js/hoverIntent.min.js',['jquery']);$reg('clipboard','js/clipboard.min.js');$reg('jquery-form','js/jquery/jquery.form.min.js',['jquery']);$reg('jquery-color','js/jquery/jquery.color.min.js',['jquery']);
    foreach(glob($d.'/js/jquery/ui/*.min.js')?:[] as $f){
        $n=basename($f,'.min.js');
        if($n==='core'){ wp_register_script('jquery-ui-widget',false,['jquery-ui-core'],$ver); }
        $h=$n==='core'?'jquery-ui-core':(str_starts_with($n,'effect')?'jquery-effects-'.preg_replace('/^effect-?/','',$n?:'core'):'jquery-ui-'.$n);
        if($n==='effect')$h='jquery-effects-core';
        $deps=$n==='core'?['jquery']:($n==='widget'?['jquery']:(in_array($n,['mouse'],true)?['jquery-ui-widget']:(str_starts_with($n,'effect')?($n==='effect'?['jquery']:['jquery-effects-core']):['jquery-ui-core'])));
        if(in_array($n,['draggable','droppable','resizable','sortable','slider','selectable'],true))$deps=['jquery-ui-mouse'];
        wp_register_script($h,$u.'/js/jquery/ui/'.$n.'.min.js',$deps,$ver,['in_footer'=>true]);
    }
    $reg('jquery-touch-punch','js/jquery/jquery.ui.touch-punch.js',['jquery-ui-core','jquery-ui-mouse']);
    $reg('iris','admin/iris.min.js',['jquery-ui-widget','jquery-ui-draggable','jquery-ui-slider','jquery-touch-punch']);
    $reg('wp-color-picker','admin/color-picker.min.js',['iris','wp-i18n']);
    $reg('postbox','admin/postbox.min.js',['jquery-ui-sortable','wp-a11y']);
    $reg('common','admin/common.min.js',['jquery','hoverIntent']);
    // Stile
    foreach(glob($d.'/css/dist/*/style.min.css')?:[] as $f){ $n=basename(dirname($f));
        $deps=match($n){'block-editor'=>['wp-components'],'editor'=>['wp-components','wp-block-editor','wp-reusable-blocks','wp-patterns','wp-preferences'],'edit-post'=>['wp-components','wp-block-editor','wp-editor'],'format-library'=>['wp-block-editor'],'commands'=>['wp-components'],'block-library'=>[],default=>[]};
        wp_register_style('wp-'.$n,$u.'/css/dist/'.$n.'/style.min.css',$deps,$ver);
    }
    if(is_file($d.'/css/dashicons.min.css'))wp_register_style('dashicons',$u.'/css/dashicons.min.css',[],$ver);
    if(is_file($d.'/css/buttons.min.css'))wp_register_style('buttons',$u.'/css/buttons.min.css',[],$ver);
    // apiFetch: Aufrufe an /wp-json laufen über die Brücke zum CMS (angemeldet als Administration)
    if(!empty($GLOBALS['rrw_wp_native_admin']))wp_add_inline_script('wp-api-fetch','wp.apiFetch.use(wp.apiFetch.createRootURLMiddleware('.wp_json_encode(esc_url_raw(rest_url())).'));wp.apiFetch.nonceMiddleware=wp.apiFetch.createNonceMiddleware('.wp_json_encode(wp_create_nonce('wp_rest')).');wp.apiFetch.use(wp.apiFetch.nonceMiddleware);','after');
    wp_add_inline_script('wp-api-fetch','window.wp&&wp.apiFetch&&window.rrwRestFetch&&wp.apiFetch.setFetchHandler(window.rrwRestFetch);','after');
    wp_add_inline_script('wp-date','window.wp&&wp.date&&wp.date.setSettings&&wp.date.setSettings('.wp_json_encode(['l10n'=>['locale'=>get_locale(),'months'=>array_map(fn($m)=>wp_date('F',mktime(0,0,0,$m,1,2024)),range(1,12)),'monthsShort'=>array_map(fn($m)=>wp_date('M',mktime(0,0,0,$m,1,2024)),range(1,12)),'weekdays'=>array_map(fn($d)=>wp_date('l',strtotime("Sunday +$d days")),range(0,6)),'weekdaysShort'=>array_map(fn($d)=>wp_date('D',strtotime("Sunday +$d days")),range(0,6)),'meridiem'=>['am'=>'am','pm'=>'pm','AM'=>'AM','PM'=>'PM'],'relative'=>['future'=>'%s from now','past'=>'%s ago'],'startOfWeek'=>1],'formats'=>['time'=>'H:i','date'=>'j. F Y','datetime'=>'j. F Y H:i','datetimeAbbreviated'=>'j. M Y H:i'],'timezone'=>['offset'=>(float)get_option('gmt_offset',0),'offsetFormatted'=>'0','string'=>(string)(get_option('timezone_string')?:'UTC'),'abbr'=>'']]).');','after');
}

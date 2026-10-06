<?php
declare(strict_types=1);
require_once __DIR__.'/pack.php';

// Logik der Ersteinrichtung (Webseite: cms/install.php). Erwartet geladen: publish.php, database.php,
// auth.php, system.php, product.php. Alle Schreibzugriffe laufen über die vorhandenen Wege des CMS
// (rrw_write_atomic, rrw_local_auth_set, rrw_db_write_config, rrw_publish, rrw_log_activity); eine
// Sperrdatei (install.lock) verhindert jede Wiederholung.

const RRW_INSTALL_PW_MIN = 10;
const RRW_INSTALL_MODES = [
    'recommended' => ['label' => 'Empfohlen', 'text' => 'ElvadoPress mit den empfohlenen Essentials: SEO, Security, Backup, Performance, Forms, Analytics, Redirects und KI-Werkzeuge – installiert und aktiviert.'],
    'minimal' => ['label' => 'Minimal', 'text' => 'Nur ElvadoPress Core. Die grundlegende Sicherheit (Login, Rechte, geschützte Datenordner) gehört zum Core; es wird kein Plugin erzwungen.'],
    'custom' => ['label' => 'Benutzerdefiniert', 'text' => 'Alle verfügbaren offiziellen Plugins ansehen und selbst wählen. Benötigte Plugins werden automatisch mit ausgewählt.'],
];

/** Verfügbare offizielle Plugins für die Auswahl im Installer. @return list<array{id:string,name:string,description:string,recommended:bool,status:string,needs:list<string>}> */
function rrw_install_plugin_choices(): array {
    require_once __DIR__.'/nplugins.php';
    $m=rrw_np();$out=[];
    foreach($m->catalog() as $id=>$c){
        $needs=[];if($c['status']==='available'){[$man]=$m->libManifest($id);foreach(array_keys($man['requires']['plugins']??[]) as $d)$needs[]=$m->catalog()[$d]['name']??$d;}
        $out[]=['id'=>$id,'name'=>(string)$c['name'],'description'=>(string)$c['description'],'recommended'=>!empty($c['recommended']),'status'=>(string)$c['status'],'needs'=>$needs];
    }
    return $out;
}
/**
 * Plugins gemäß Installationsart einrichten. Ein Fehler hier macht die Einrichtung nie ungültig: Das CMS ist dann installiert, die betroffenen Plugins lassen sich später
 * in der Verwaltung installieren. @return array{mode:string,installed:list<string>,activated:list<string>,failed:array<string,string>}
 */
function rrw_install_plugins_run(array $c): array {
    $mode=in_array((string)($c['mode']??''),array_keys(RRW_INSTALL_MODES),true)?(string)$c['mode']:'recommended';
    $res=['mode'=>$mode,'installed'=>[],'activated'=>[],'failed'=>[]];
    try{
        require_once __DIR__.'/nplugins.php';$m=rrw_np();
        $ids=$mode==='recommended'?$m->recommendedIds():($mode==='custom'?array_values(array_filter(array_map('strval',(array)($c['plugins']??[])),fn($x)=>isset($m->catalog()[$x]))):[]);
        if($ids){$r=$m->installSelection($ids,true);$res=['mode'=>$mode]+$r;}
        $m->setMode($mode);
    }catch(Throwable $e){ $res['failed']['_']=mb_substr($e->getMessage(),0,200); }
    return $res;
}

/** Passwortregeln der Einrichtung (strenger als die Mindestlänge des lokalen Logins). @return ?string Fehlertext */
function rrw_install_password_error(string $pw,string $user,string $site): ?string {
    if(mb_strlen($pw)<RRW_INSTALL_PW_MIN)return 'Das Passwort braucht mindestens '.RRW_INSTALL_PW_MIN.' Zeichen.';
    if(mb_strlen($pw)>200)return 'Das Passwort ist zu lang (höchstens 200 Zeichen).';
    if(!preg_match('/\p{L}/u',$pw)||!preg_match('/[\p{N}\p{P}\p{S}]/u',$pw))return 'Das Passwort braucht mindestens einen Buchstaben und eine Ziffer oder ein Sonderzeichen.';
    $l=mb_strtolower($pw);
    if($user!==''&&str_contains($l,mb_strtolower($user)))return 'Das Passwort darf den Benutzernamen nicht enthalten.';
    if($site!==''&&mb_strtolower($site)===$l)return 'Das Passwort darf nicht dem Website-Namen entsprechen.';
    if(in_array($l,['passwort123','password123','1234567890','qwertz12345','administrator1','changeme123','willkommen123'],true))return 'Dieses Passwort ist zu bekannt.';
    return null;
}
/** Schutz gegen Formular-Fremdaufrufe: Cookie und Formularwert müssen übereinstimmen. */
function rrw_install_csrf_ok(string $cookie,string $field): bool {
    return preg_match('/^[a-f0-9]{32}$/',$cookie)===1&&hash_equals($cookie,$field);
}
/** Versuche je Gegenstelle und Zeitfenster zählen (Datei im Datenordner). false = zu viele Versuche. */
function rrw_install_rate_ok(string $file,string $client,int $max=10,int $window=600): bool {
    $h=@fopen($file,'c+');if(!$h)return true;   // ohne Schreibrecht ist ohnehin keine Einrichtung möglich
    @flock($h,LOCK_EX);$now=time();
    $d=json_decode((string)stream_get_contents($h),true);if(!is_array($d))$d=[];
    foreach($d as $k=>$ts){$d[$k]=array_values(array_filter((array)$ts,fn($t)=>(int)$t>$now-$window));if(!$d[$k])unset($d[$k]);}
    $key=hash('sha256',$client);$mine=$d[$key]??[];$all=0;foreach($d as $ts)$all+=count($ts);
    $ok=count($mine)<$max&&$all<$max*6;
    if($ok){$mine[]=$now;$d[$key]=$mine;}
    ftruncate($h,0);rewind($h);fwrite($h,json_encode($d));@flock($h,LOCK_UN);fclose($h);@chmod($file,0600);
    return $ok;
}
/**
 * Eingaben der Einrichtung prüfen und bereinigen.
 * @return array{0:array,1:array<string,string>} [bereinigte Werte, Fehlertexte je Feld]
 */
function rrw_install_clean(array $in): array {
    $e=[];$s=fn(string $k,int $max)=>mb_substr(trim((string)preg_replace('/[\x00-\x1f]/u','',(string)($in[$k]??''))),0,$max);
    $site=$s('site_name',80);if($site==='')$e['site_name']='Bitte einen Website-Namen angeben.';
    $lang=$s('language',5);if(!in_array($lang,['de','en'],true))$lang='de';
    $tz=$s('timezone',64);if(!in_array($tz,DateTimeZone::listIdentifiers(),true)){$tz='Europe/Berlin';}
    $user=$s('username',40);if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,39}$/',$user))$e['username']='Benutzername: 3 bis 40 Zeichen (Buchstaben, Ziffern, . _ -).';
    $display=$s('display_name',80);if($display==='')$display=$user;
    $email=$s('email',120);if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))$e['email']='Die E-Mail-Adresse ist ungültig.';
    $pw=(string)($in['password']??'');$pw2=(string)($in['password2']??'');
    if($pw!==$pw2)$e['password2']='Die beiden Passwörter stimmen nicht überein.';
    elseif(($pe=rrw_install_password_error($pw,$user,$site))!==null)$e['password']=$pe;
    $drvIn=['driver'=>$s('db_driver',12)?:'none','host'=>$s('db_host',253),'port'=>(int)($in['db_port']??0),'socket'=>$s('db_socket',200),'database'=>$s('db_name',64),
        'user'=>$s('db_user',64),'password'=>(string)($in['db_password']??''),'prefix'=>$s('db_prefix',31)?:'wp_','sqlite_path'=>$s('db_sqlite_path',100)?:'cms.sqlite'];
    [$db,$dbErr]=rrw_db_clean_input($drvIn,rrw_db_fill(['driver'=>'none']));
    if($dbErr!==null){$e['db']=$dbErr;$db=['driver'=>'none'];}
    $drivers=rrw_db_drivers();
    if(isset($drivers[$db['driver']])&&$drivers[$db['driver']]['ext']!==''&&!extension_loaded($drivers[$db['driver']]['ext']))$e['db']='Die PHP-Erweiterung '.$drivers[$db['driver']]['ext'].' ist auf diesem Server nicht aktiv.';
    $mode=(string)($in['install_mode']??'recommended');if(!isset(RRW_INSTALL_MODES[$mode]))$mode='recommended';
    $pluginSel=[];foreach((array)($in['plugins']??[]) as $pid)if(is_string($pid)&&preg_match('/^[a-z][a-z0-9-]{2,40}$/',$pid))$pluginSel[]=$pid;
    return [['site_name'=>$site,'language'=>$lang,'timezone'=>$tz,'username'=>$user,'display_name'=>$display,'email'=>$email,'password'=>$pw,'db'=>$db,'db_create'=>!empty($in['db_create']),'sample'=>!empty($in['sample']),'mode'=>$mode,'plugins'=>$pluginSel],$e];
}
/**
 * Einrichtung ausführen. $ctx: siteFile, newsFile, genDir, root, activityLog.
 * Bei Fehlern werden Konfiguration, Zugang, site.json und news.json auf den Stand vorher zurückgesetzt und die Sperre wieder entfernt.
 * @return array{ok:bool,message:string}
 */
/** Neutrales Standard-Theme einschalten (Theme „rrw-classic“); schlägt still fehl, die Einrichtung bleibt gültig – das Theme lässt sich später im CMS wählen. */
function rrw_install_default_theme(): bool {
    try{
        $cms=dirname(__DIR__);
        require_once $cms.'/wp/load.php';require_once $cms.'/wp/installer.php';
        rrw_wp_boot(['theme'=>false]);
        return rrw_wpi_activate_theme('rrw-classic')===null;
    }catch(Throwable $e){ return false; }
}
function rrw_install_run(array $c,array $ctx): array {
    if(!rrw_install_needed())return ['ok'=>false,'message'=>'Die Einrichtung ist nicht (mehr) möglich.'];
    if($c['db']['driver']!=='none'){
        $t=rrw_db_test($c['db'],!empty($c['db_create']));
        if(!$t['ok'])return ['ok'=>false,'message'=>'Datenbank: '.$t['message']];
    }
    $lock=rrw_install_lock_file();$dir=dirname($lock);
    if(!is_dir($dir)&&!@mkdir($dir,0755,true))return ['ok'=>false,'message'=>'Der Datenordner ist nicht beschreibbar.'];
    $gate=@fopen($lock,'x');   // atomar: nur ein Aufruf kommt hier durch
    if(!$gate)return ['ok'=>false,'message'=>'Die Einrichtung läuft bereits oder ist abgeschlossen.'];
    @flock($gate,LOCK_EX);
    $orig=[];
    $remember=function(string $f)use(&$orig){if(!array_key_exists($f,$orig))$orig[$f]=is_file($f)?(string)file_get_contents($f):null;};
    try{
        $now=date(DATE_ATOM);
        if($c['db']['driver']!=='none'){
            $remember(rrw_db_config_file());rrw_db_write_config($c['db']);
        }
        $remember(rrw_local_auth_file());
        rrw_local_auth_set($c['username'],$c['password'],'admin');
        rrw_local_user_update($c['username'],null,null,$c['display_name'],$c['email']!==''?$c['email']:null);
        $remember(rrw_system_file());
        rrw_system_save(['control_center'=>false,'language'=>$c['language'],'timezone'=>$c['timezone'],'installed_at'=>$now]);
        // Beispielbeitrag nur, wenn noch keine Beiträge existieren
        $remember($ctx['newsFile']);$remember($ctx['siteFile']);$remember($ctx['activityLog']);
        if(!empty($c['sample'])&&rrw_system_file_empty($ctx['newsFile'])){
            $d=date('Y-m-d H:i:s');
            $article=['id'=>1,'slug'=>'willkommen','title'=>'Willkommen bei '.$c['site_name'],'category'=>'News','excerpt'=>'Das ist ein Beispielbeitrag. Du kannst ihn bearbeiten oder löschen.',
                'body_html'=>'<p>Schön, dass du da bist! Dieser Beitrag wurde bei der Einrichtung angelegt. Unter „News“ in der Verwaltung kannst du ihn ändern oder eigene Beiträge schreiben.</p>',
                'status'=>'published','featured'=>0,'author'=>$c['display_name'],'author_user'=>$c['username'],'tags'=>[],'image_url'=>'','image_mode'=>'thumbnail','published_at'=>$d,'created_at'=>$d,'updated_at'=>$d];
            rrw_write_atomic($ctx['newsFile'],json_encode([$article],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
        }
        $site=rrw_read_json($ctx['siteFile'],[]);
        $site['portal']=is_array($site['portal']??null)?$site['portal']:[];$site['portal']['site_name']=$c['site_name'];
        $site=rrw_ensure_site_defaults($site);
        $site['seo']=is_array($site['seo']??null)?$site['seo']:[];$site['seo']['site_title']=$c['site_name'];
        $site['rss']=is_array($site['rss']??null)?$site['rss']:[];$site['rss']['title']=$c['site_name'].' – News & Magazin';
        $site['_meta']=is_array($site['_meta']??null)?$site['_meta']:[];$site['_meta']['installed_at']=$now;
        try{rrw_log_activity($ctx['activityLog'],['display_name'=>$c['display_name'],'user'=>$c['username'],'role'=>'admin'],'install','Ersteinrichtung abgeschlossen (eigenständiger Betrieb)');}catch(Throwable $e){}
        // Veröffentlichen wie sonst im CMS (index.html-Snapshot, Seiten, RSS, SEO); ohne index.html im Webroot nur speichern
        if(is_file($ctx['root'].'/index.html'))rrw_publish($site,$ctx['siteFile'],$ctx['genDir'],$ctx['root']);
        else rrw_write_atomic($ctx['siteFile'],json_encode($site,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
        // Ohne das RicoReWi-Portal (eigenständiges CMS) liefert ein neutrales WordPress-Theme die Website aus
        if(!rrw_pack_available())rrw_install_default_theme();
        $plugRes=rrw_pack_available()?null:rrw_install_plugins_run($c);   // Essentials je Installationsart (Fehler sind hier nie fatal)
        ftruncate($gate,0);fwrite($gate,json_encode(['installed_at'=>$now],JSON_UNESCAPED_SLASHES)."\n");
        @flock($gate,LOCK_UN);fclose($gate);@chmod($lock,0600);
        return ['ok'=>true,'message'=>'Die Einrichtung ist abgeschlossen.','plugins'=>$plugRes];
    }catch(Throwable $e){
        foreach($orig as $f=>$content){if($content===null)@unlink($f);else @file_put_contents($f,$content);}
        @flock($gate,LOCK_UN);fclose($gate);@unlink($lock);
        rrw_system_config(true);
        return ['ok'=>false,'message'=>'Die Einrichtung ist fehlgeschlagen: '.mb_substr(str_replace((string)$c['password'],'***',$e->getMessage()),0,200)];
    }
}

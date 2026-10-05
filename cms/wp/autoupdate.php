<?php
// Automatische Updates für WordPress-Plugins, -Themes, Sprachpakete und Kernressourcen.
// Einstellungen und Protokoll: cms/data/.wp/autoupdate.json. Ausgelöst durch cron/wp-cron.php (empfohlen) oder per Schaltfläche im CMS.
// Sicherheit: Vor jedem Update wird die alte Fassung verschoben (rollback/), nach dem Update werden alle PHP-Dateien auf Syntaxfehler geprüft;
// bei einem Fehler wird die alte Fassung wiederhergestellt. Je Lauf höchstens 5 Updates und 90 Sekunden.
require_once __DIR__.'/installer.php';
if(!function_exists('rrw_td_get')&&is_file(dirname(__DIR__).'/lib/theme-directory.php'))require_once dirname(__DIR__).'/lib/theme-directory.php';

function rrw_wpau_file(): string { return rtrim(RRW_WP_DATA,'/').'/autoupdate.json'; }
function rrw_wpau_defaults(): array {
    return ['plugins'=>'off','themes'=>'off','translations'=>false,'core_assets'=>false,'wp_core'=>'off','interval_h'=>12,'selected'=>['plugin'=>[],'theme'=>[]],'last_run'=>0,'log'=>[]];
}
/** Einstellungen lesen (immer vollständig und bereinigt). */
function rrw_wpau_get(): array {
    $d=rrw_wpau_defaults();$j=is_file(rrw_wpau_file())?json_decode((string)file_get_contents(rrw_wpau_file()),true):null;
    return is_array($j)?rrw_wpau_clean($j,true):$d;
}
function rrw_wpau_slugs($v): array {
    $o=[];foreach((array)$v as $s)if(is_string($s)&&preg_match('/^[a-z0-9_-]{2,80}$/',$s))$o[$s]=$s;return array_values($o);
}
/** Eingaben bereinigen; $keepState übernimmt auch last_run und log (nur beim Lesen der Datei). */
function rrw_wpau_clean(array $in, bool $keepState=false): array {
    $d=rrw_wpau_defaults();$mode=fn($v)=>in_array($v,['off','all','selected'],true)?$v:'off';
    $o=['plugins'=>$mode($in['plugins']??'off'),'themes'=>$mode($in['themes']??'off'),'translations'=>!empty($in['translations']),'core_assets'=>!empty($in['core_assets']),'wp_core'=>in_array($wc=(string)($in['wp_core']??'off'),['off','minor','all'],true)?$wc:'off',
        'interval_h'=>max(1,min(168,(int)($in['interval_h']??$d['interval_h']))),
        'selected'=>['plugin'=>rrw_wpau_slugs($in['selected']['plugin']??[]),'theme'=>rrw_wpau_slugs($in['selected']['theme']??[])],'last_run'=>0,'log'=>[]];
    if($keepState){
        $o['last_run']=(int)($in['last_run']??0);
        foreach(array_slice((array)($in['log']??[]),-50) as $e)if(is_array($e))$o['log'][]=['t'=>(int)($e['t']??0),'type'=>(string)($e['type']??''),'slug'=>(string)($e['slug']??''),'from'=>(string)($e['from']??''),'to'=>(string)($e['to']??''),'ok'=>!empty($e['ok']),'msg'=>mb_substr((string)($e['msg']??''),0,200)];
    }
    return $o;
}
function rrw_wpau_save(array $s): bool {
    $dir=dirname(rrw_wpau_file());if(!is_dir($dir))@mkdir($dir,0775,true);
    $s=rrw_wpau_clean($s,true);$tmp=rrw_wpau_file().'.tmp'.bin2hex(random_bytes(3));
    return @file_put_contents($tmp,json_encode($s,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX)!==false&&@rename($tmp,rrw_wpau_file());
}
/** Nur die Einstellungen ändern (Protokoll und letzter Lauf bleiben). */
function rrw_wpau_set(array $in): array {
    $cur=rrw_wpau_get();$new=rrw_wpau_clean($in);$new['last_run']=$cur['last_run'];$new['log']=$cur['log'];
    rrw_wpau_save($new);return $new;
}
/** Soll dieses Plugin/Theme automatisch aktualisiert werden? */
function rrw_wpau_wants(array $s, string $type, string $slug): bool {
    $m=$s[$type==='plugin'?'plugins':'themes']??'off';
    return $m==='all'||($m==='selected'&&in_array($slug,$s['selected'][$type]??[],true));
}
function rrw_wpau_enabled(array $s): bool { return $s['plugins']!=='off'||$s['themes']!=='off'||$s['translations']||$s['core_assets']||($s['wp_core']??'off')!=='off'; }
/** Ist ein Lauf fällig? */
function rrw_wpau_due(?array $s=null): bool {
    $s=$s??rrw_wpau_get();return rrw_wpau_enabled($s)&&time()-$s['last_run']>=$s['interval_h']*3600;
}
function rrw_wpau_rollback_dir(string $type, string $slug=''): string { return rtrim(RRW_WP_DATA,'/').'/rollback/'.$type.($slug!==''?'/'.$slug:''); }
function rrw_wpau_rm(string $dir): void {
    $root=realpath(rtrim(RRW_WP_DATA,'/').'/rollback');$real=realpath($dir);
    if(!$root||!$real||is_link($dir)||!str_starts_with($real,$root.DIRECTORY_SEPARATOR))return;
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $f){ $f->isDir()&&!$f->isLink()?@rmdir($f->getPathname()):@unlink($f->getPathname()); }
    @rmdir($real);
}
function rrw_wpau_live_dir(string $type, string $slug): string { return ($type==='plugin'?WP_PLUGIN_DIR:WP_CONTENT_DIR.'/themes').'/'.$slug; }
/** Alle PHP-Dateien auf Syntaxfehler prüfen (Zeitbudget in Sekunden). @return string Fehlermeldung oder '' */
function rrw_wpau_syntax_check(string $dir, int $budget=30): string {
    $end=microtime(true)+$budget;
    if(!is_dir($dir))return 'Ordner fehlt';
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS)) as $f){
        if(microtime(true)>$end)return '';
        if(!$f->isFile()||strtolower($f->getExtension())!=='php')continue;
        $src=@file_get_contents($f->getPathname());if($src===false||$src==='')continue;
        try{ token_get_all($src,TOKEN_PARSE); }catch(ParseError $e){ return basename($f->getPathname()).': '.$e->getMessage(); }
    }
    return '';
}
/** Ein Plugin/Theme aktualisieren: alte Fassung sichern, installieren, prüfen, bei Fehler zurückrollen. @return array{ok:bool,msg:string} */
function rrw_wpau_update_one(string $type, string $slug): array {
    $live=rrw_wpau_live_dir($type,$slug);$rb=rrw_wpau_rollback_dir($type,$slug);$zip='';
    if(!is_dir($live))return ['ok'=>false,'msg'=>'nicht installiert'];
    if(!is_dir(dirname($rb)))@mkdir(dirname($rb),0775,true);
    if(is_dir($rb))rrw_wpau_rm($rb);
    try{
        $zip=$type==='plugin'?rrw_wpi_download_plugin($slug):rrw_wpi_download_theme($slug);
    }catch(Throwable $e){ return ['ok'=>false,'msg'=>mb_substr($e->getMessage(),0,160)]; }
    if(!@rename($live,$rb)){ @unlink($zip);return ['ok'=>false,'msg'=>'Sicherung nicht möglich']; }
    $err='';
    try{
        if($type==='plugin')rrw_wpi_install_plugin_zip($zip,$slug);else rrw_wpi_install_theme_zip($zip,$slug);
        $err=rrw_wpau_syntax_check($live);
    }catch(Throwable $e){ $err=$e->getMessage(); }
    if(is_file($zip))@unlink($zip);
    if($err!==''){
        if(is_dir($live)){ $bad=$live.'.fail-'.bin2hex(random_bytes(3));@rename($live,$bad);if(is_dir($bad))rrw_wp_rmdir($bad); }
        @rename($rb,$live);
        return ['ok'=>false,'msg'=>'zurückgesetzt: '.mb_substr($err,0,150)];
    }
    return ['ok'=>true,'msg'=>''];
}
/** Letztes automatisches Update zurücknehmen (alte Fassung aus rollback/). */
function rrw_wpau_rollback(string $type, string $slug): array {
    if(!in_array($type,['plugin','theme'],true)||!preg_match('/^[a-z0-9_-]{2,80}$/',$slug))return ['ok'=>false,'msg'=>'Ungültige Angabe'];
    $rb=rrw_wpau_rollback_dir($type,$slug);$live=rrw_wpau_live_dir($type,$slug);
    if(!is_dir($rb))return ['ok'=>false,'msg'=>'Keine gesicherte Fassung vorhanden'];
    $tmp=null;
    if(is_dir($live)){ $tmp=$live.'.cur-'.bin2hex(random_bytes(3));if(!@rename($live,$tmp))return ['ok'=>false,'msg'=>'Aktuelle Fassung nicht verschiebbar']; }
    if(!@rename($rb,$live)){ if($tmp)@rename($tmp,$live);return ['ok'=>false,'msg'=>'Wiederherstellung fehlgeschlagen']; }
    if($tmp&&is_dir($tmp))rrw_wp_rmdir($tmp);
    return ['ok'=>true,'msg'=>''];
}
function rrw_wpau_version(string $type, string $slug): string {
    if($type==='plugin'){ foreach(get_plugins() as $f=>$d)if(explode('/',$f)[0]===$slug)return (string)$d['Version']; }
    else foreach(rrw_wpi_list_themes() as $t)if($t['slug']===$slug)return (string)$t['version'];
    return '';
}
/** Fällige Updates ausführen. @return array{ran:bool,updated:int,failed:int,items:array} */
function rrw_wpau_run(string $dataDir, bool $force=false, int $max=5, int $budget=90): array {
    $res=['ran'=>false,'updated'=>0,'failed'=>0,'items'=>[]];
    $s=rrw_wpau_get();if(!$force&&!rrw_wpau_due($s))return $res;
    if(!rrw_wpau_enabled($s)&&!$force)return $res;
    if(!is_dir(RRW_WP_DATA))@mkdir(RRW_WP_DATA,0775,true);
    $lockF=rtrim(RRW_WP_DATA,'/').'/autoupdate.lock';$fh=@fopen($lockF,'c');
    if(!$fh||!flock($fh,LOCK_EX|LOCK_NB)){ if($fh)fclose($fh);return $res; }
    $res['ran']=true;$start=microtime(true);$log=[];
    try{
        $changed=false;
        foreach(rrw_wpi_updates($dataDir) as $u){
            if($res['updated']+$res['failed']>=$max||microtime(true)-$start>$budget)break;
            if(!rrw_wpau_wants($s,$u['type'],$u['slug']))continue;
            $r=rrw_wpau_update_one($u['type'],$u['slug']);
            $log[]=['t'=>time(),'type'=>$u['type'],'slug'=>$u['slug'],'from'=>$u['current'],'to'=>$u['latest'],'ok'=>$r['ok'],'msg'=>$r['msg']];
            $r['ok']?$res['updated']++:$res['failed']++;if($r['ok'])$changed=true;
        }
        if($s['translations']&&($changed||$force)&&microtime(true)-$start<$budget){
            $t=rrw_wpi_fetch_all_translations('de_DE');$log[]=['t'=>time(),'type'=>'translations','slug'=>'','from'=>'','to'=>'','ok'=>true,'msg'=>$t['ok'].' Sprachpaket(e) geladen'];
        }
        if(($s['wp_core']??'off')!=='off'&&microtime(true)-$start<$budget){
            require_once __DIR__.'/wpversion.php';
            $r=rrw_wpv_update($dataDir,$s['wp_core']);
            if(empty($r['none'])){ $log[]=['t'=>time(),'type'=>'wordpress','slug'=>'','from'=>'','to'=>(string)($r['version']??''),'ok'=>!empty($r['ok']),'msg'=>!empty($r['ok'])?(($r['report']['missing']??0).' von '.($r['report']['total']??0).' Funktionen unbekannt'):mb_substr((string)$r['message'],0,150)]; if(!empty($r['ok']))$changed=true; }
        }
        if($s['core_assets']&&microtime(true)-$start<$budget){
            require_once __DIR__.'/coreassets.php';
            if(!rrw_wp_core_status()['installed']){ $r=rrw_wp_core_install();$log[]=['t'=>time(),'type'=>'core','slug'=>'','from'=>'','to'=>'','ok'=>!empty($r['ok']),'msg'=>mb_substr((string)($r['message']??''),0,150)]; }
        }
    }catch(Throwable $e){ $log[]=['t'=>time(),'type'=>'error','slug'=>'','from'=>'','to'=>'','ok'=>false,'msg'=>mb_substr($e->getMessage(),0,150)]; }
    $cur=rrw_wpau_get();$cur['last_run']=time();$cur['log']=array_slice(array_merge($cur['log'],$log),-50);rrw_wpau_save($cur);
    $res['items']=$log;flock($fh,LOCK_UN);fclose($fh);
    return $res;
}

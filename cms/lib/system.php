<?php
declare(strict_types=1);

// Betriebseinstellungen, Installationsstatus und CMS-Version.
// Die Datei cms/data/system.local.json enthält nur Betriebseinstellungen (keine Zugangsdaten):
//   language (z. B. "de"), timezone (z. B. "Europe/Berlin"), installed_at

function elvado_data_dir(): string { return defined('ELVADO_DATA_DIR')?rtrim((string)ELVADO_DATA_DIR,'/'):__DIR__.'/../data'; }
function elvado_system_file(): string { return defined('ELVADO_SYSTEM_FILE')?(string)ELVADO_SYSTEM_FILE:elvado_data_dir().'/system.local.json'; }
function elvado_install_lock_file(): string { return defined('ELVADO_INSTALL_LOCK')?(string)ELVADO_INSTALL_LOCK:elvado_data_dir().'/install.lock'; }

/** Betriebseinstellungen, auf feste Felder gebracht . */
function elvado_system_config(bool $reload=false): array {
    static $c=null;if($c!==null&&!$reload)return $c;
    $f=elvado_system_file();$j=is_file($f)?json_decode((string)@file_get_contents($f),true):null;if(!is_array($j))$j=[];
    $tz=(string)($j['timezone']??'');if($tz!==''&&!in_array($tz,DateTimeZone::listIdentifiers(),true))$tz='';
    $lang=(string)($j['language']??'');if(!preg_match('/^[a-z]{2}(_[A-Z]{2})?$/',$lang))$lang='';
    return $c=['language'=>$lang,'timezone'=>$tz,'installed_at'=>(string)($j['installed_at']??'')];
}
/** Zeitzone aus der Einrichtung anwenden (ohne Einstellung bleibt die Server-Zeitzone). */
function elvado_system_apply_timezone(): void {
    $tz=elvado_system_config()['timezone'];if($tz!=='')@date_default_timezone_set($tz);
}
/** Speichern (atomar); unbekannte vorhandene Felder bleiben erhalten. */
function elvado_system_save(array $in): array {
    $f=elvado_system_file();$old=is_file($f)?json_decode((string)@file_get_contents($f),true):null;if(!is_array($old))$old=[];
    $new=$old;
    if(array_key_exists('language',$in)){$l=(string)$in['language'];if($l!==''&&!preg_match('/^[a-z]{2}(_[A-Z]{2})?$/',$l))throw new InvalidArgumentException('Ungültige Sprache');$new['language']=$l;}
    if(array_key_exists('timezone',$in)){$t=(string)$in['timezone'];if($t!==''&&!in_array($t,DateTimeZone::listIdentifiers(),true))throw new InvalidArgumentException('Ungültige Zeitzone');$new['timezone']=$t;}
    if(array_key_exists('installed_at',$in))$new['installed_at']=(string)$in['installed_at'];
    elvado_write_atomic($f,json_encode($new,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    @chmod($f,0600);
    return elvado_system_config(true);
}

// ---------------------------------------------------------------- Ersteinrichtung (Status)

/** Datei ist leer ("[]", "{}", nur Leerraum) oder fehlt. */
function elvado_system_file_empty(string $file): bool {
    if(!is_file($file))return true;
    $raw=trim((string)@file_get_contents($file));if($raw==='')return true;
    $j=json_decode($raw,true);return is_array($j)&&!$j;
}
/**
 * Muss die Ersteinrichtung laufen? Nur bei einer wirklich frischen Installation:
 * keine Sperrdatei, keine Betriebseinstellung, kein lokaler Zugang und
 * Inhalte nur im ausgelieferten Ausgangszustand (site.json ohne "_meta", keine Beiträge, kein Protokoll).
 * Im Zweifel (unlesbare oder ungültige Dateien) lautet die Antwort false.
 */
function elvado_install_needed(): bool {
    if(is_file(elvado_install_lock_file())||is_file(elvado_system_file()))return false;
    if(function_exists('elvado_local_auth_configured')?elvado_local_auth_configured():is_file(elvado_data_dir().'/local-auth.local.php'))return false;
    $d=elvado_data_dir();
    $site=$d.'/site.json';
    if(is_file($site)){
        $raw=trim((string)@file_get_contents($site));
        if($raw!==''){$j=json_decode($raw,true);if(!is_array($j)||array_key_exists('_meta',$j))return false;}
    }
    foreach(['news.json','activity-log.json','comments.json'] as $f)if(!elvado_system_file_empty($d.'/'.$f))return false;
    return true;
}

// ---------------------------------------------------------------- Version und Prüfsumme

function elvado_version_file(): string { return defined('ELVADO_VERSION_FILE')?(string)ELVADO_VERSION_FILE:__DIR__.'/../VERSION'; }
/** CMS-Version aus cms/VERSION ("unbekannt", wenn die Datei fehlt). */
function elvado_cms_version(): string {
    $f=elvado_version_file();$v=is_file($f)?trim((string)@file_get_contents($f)):'';
    return preg_match('/^[0-9A-Za-z][0-9A-Za-z.+_-]{0,31}$/',$v)?$v:'unbekannt';
}
/**
 * Prüfsumme der CMS-Programmdateien (nur Code und Oberfläche, keine Daten, Medien, Themes, Plugins).
 * Rechenintensiv, deshalb nur auf ausdrücklichen Abruf. Dient dem Abgleich mit einer Referenz, nie dem Überschreiben.
 * @return array{sha256:string,files:int,truncated:bool}
 */
function elvado_cms_checksum(?string $base=null,int $max=6000): array {
    $base=rtrim($base??dirname(__DIR__),'/');
    $topSkip=['data','media','generated','backups','content','themes','plugins','wp-content','wp-core'];
    $files=[];$trunc=false;
    try{
        $it=new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS),
            function(SplFileInfo $f)use($topSkip,$base){
                $n=$f->getFilename();
                if($f->isDir())return !in_array($n,['.git','node_modules'],true)&&!($f->getPath()===$base&&in_array($n,$topSkip,true));
                return $n==='VERSION'||preg_match('/\.(php|js|css|html|md|json|xsl|svg)$/i',$n)===1;
            }
        ));
        foreach($it as $f){
            if(!$f->isFile())continue;
            if(count($files)>=$max){$trunc=true;break;}
            $rel=substr($f->getPathname(),strlen($base)+1);
            if(preg_match('#(^|/)[^/]*\.local\.(php|json)$#',$rel))continue;
            $files[$rel]=(string)hash_file('sha256',$f->getPathname());
        }
    }catch(Throwable $e){}
    ksort($files);
    $ctx=hash_init('sha256');foreach($files as $rel=>$h)hash_update($ctx,$rel."\0".$h."\n");
    return ['sha256'=>hash_final($ctx),'files'=>count($files),'truncated'=>$trunc];
}

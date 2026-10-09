<?php
// WordPress-Version nachführen: Die Schicht meldet Plugins und Themes eine WordPress-Version (get_bloginfo('version'), $wp_version).
// Erscheint bei wordpress.org eine neue Version, kann sie hier übernommen werden: Kernressourcen (JavaScript/CSS) werden neu geladen,
// die gemeldete Version wechselt (data/.wp/wp-version.json) und ein Kompatibilitätsbericht zeigt, welche Funktionen der neuen Version
// die Schicht noch nicht kennt. Die vorherige Fassung bleibt zum Zurücksetzen erhalten (cms/wp-core.prev/).
require_once __DIR__.'/coreassets.php';

function elvado_wpv_file(): string { return rtrim(ELVADO_WP_DATA,'/').'/wp-version.json'; }
function elvado_wpv_report_file(): string { return rtrim(ELVADO_WP_DATA,'/').'/wp-compat.json'; }
/** Aktuell gemeldete Version laut Versionsdatei (der Start-Wert ELVADO_WP_VERSION gilt nur bis zum nächsten Seitenaufruf). @return array{version:string,db:int} */
function elvado_wpv_current(): array {
    $j=is_file(elvado_wpv_file())?json_decode((string)file_get_contents(elvado_wpv_file()),true):null;
    if(is_array($j)&&elvado_wpv_valid((string)($j['version']??'')))return ['version'=>(string)$j['version'],'db'=>max(1,(int)($j['db']??57155))];
    return ['version'=>ELVADO_WP_BASE_VERSION,'db'=>57155];
}
function elvado_wpv_valid(string $v): bool { return (bool)preg_match('/^\d{1,2}\.\d{1,2}(\.\d{1,2})?$/',$v); }
/** Hauptversion („6.8“) einer Versionsnummer. */
function elvado_wpv_branch(string $v): string { $p=explode('.',$v);return ($p[0]??'0').'.'.($p[1]??'0'); }

/**
 * Passendes Angebot aus der Antwort von api.wordpress.org/core/version-check wählen.
 * @param array $offers Liste von ['version'=>…, 'download'=>…]
 * @param string $policy 'minor' (nur Fehlerkorrekturen derselben Hauptversion) oder 'all'
 * @return array{version:string,download:string}|null
 */
function elvado_wpv_pick_offer(array $offers, string $current, string $policy): ?array {
    $best=null;
    foreach($offers as $o){
        if(!is_array($o))continue;$v=(string)($o['version']??'');$dl=(string)($o['download']??'');
        if(!elvado_wpv_valid($v)||!str_starts_with($dl,'https://downloads.wordpress.org/release/')||!version_compare($v,$current,'>'))continue;
        if(preg_match('/(alpha|beta|rc)/i',$v))continue;
        if($policy==='minor'&&elvado_wpv_branch($v)!==elvado_wpv_branch($current))continue;
        if($policy!=='minor'&&$policy!=='all')continue;
        if($best===null||version_compare($v,$best['version'],'>'))$best=['version'=>$v,'download'=>$dl];
    }
    return $best;
}
/** Verfügbare Versionen abfragen (Zwischenspeicher 6 Stunden). @return array{latest:?string,offers:array} */
function elvado_wpv_offers(string $dataDir): array {
    $r=elvado_td_cache($dataDir,'wpcore'.md5(ELVADO_WP_VERSION),21600,function(){
        $raw=elvado_td_get('https://api.wordpress.org/core/version-check/1.7/?version='.rawurlencode(ELVADO_WP_VERSION).'&locale=en_US',1048576);
        $j=$raw?json_decode($raw,true):null;$o=[];
        foreach((array)($j['offers']??[]) as $x)if(is_array($x)&&isset($x['version'],$x['download']))$o[]=['version'=>(string)$x['version'],'download'=>(string)$x['download']];
        return ['offers'=>$o];
    });
    $offers=(array)($r['offers']??[]);$latest=null;
    foreach($offers as $o)if(elvado_wpv_valid($o['version'])&&($latest===null||version_compare($o['version'],$latest,'>')))$latest=$o['version'];
    return ['latest'=>$latest,'offers'=>$offers];
}
/** Funktionsnamen (oberste Ebene) aus PHP-Quelltext. */
function elvado_wpv_function_names(string $src): array {
    $out=[];try{ $tok=token_get_all($src,TOKEN_PARSE); }catch(Throwable $e){ return []; }
    $depth=0;$n=count($tok);
    for($i=0;$i<$n;$i++){ $t=$tok[$i];
        if($t==='{'||(is_array($t)&&in_array($t[0],[T_CURLY_OPEN,T_DOLLAR_OPEN_CURLY_BRACES],true)))$depth++;
        elseif($t==='}')$depth--;
        elseif(is_array($t)&&$t[0]===T_FUNCTION&&$depth===0){
            for($j=$i+1;$j<$n;$j++){ if(is_array($tok[$j])&&$tok[$j][0]===T_STRING){ $out[]=$tok[$j][1];break; } if($tok[$j]==='(')break; }
        }
    }
    return $out;
}
/** Kompatibilitätsbericht: Wie viele Funktionen der Version im ZIP kennt die Schicht noch nicht? Es werden nur Namen gelesen. */
function elvado_wpv_report(string $zipPath, string $version): array {
    $z=new ZipArchive();if($z->open($zipPath)!==true)return ['version'=>$version,'total'=>0,'missing'=>0,'sample'=>[]];
    $total=0;$missing=[];$seen=[];
    for($i=0;$i<$z->numFiles;$i++){
        $name=(string)$z->getNameIndex($i);
        if(!preg_match('#^wordpress/(wp-includes|wp-admin/includes)/[^/]+\.php$#',$name))continue;
        $st=$z->statIndex($i);if(($st['size']??0)>3145728)continue;
        $src=$z->getFromIndex($i);if($src===false)continue;
        foreach(elvado_wpv_function_names($src) as $f){ if(isset($seen[$f]))continue;$seen[$f]=1;$total++;if(!function_exists($f))$missing[]=$f; }
    }
    $z->close();
    return ['version'=>$version,'total'=>$total,'missing'=>count($missing),'sample'=>array_slice($missing,0,40),'time'=>time()];
}
function elvado_wpv_status(string $dataDir): array {
    $o=elvado_wpv_offers($dataDir);$rep=is_file(elvado_wpv_report_file())?json_decode((string)file_get_contents(elvado_wpv_report_file()),true):null;
    $cur=elvado_wpv_current()['version'];$j=is_file(elvado_wpv_file())?json_decode((string)file_get_contents(elvado_wpv_file()),true):[];
    return ['current'=>$cur,'base'=>ELVADO_WP_BASE_VERSION,'latest'=>$o['latest'],'update'=>$o['latest']&&version_compare($o['latest'],$cur,'>'),
        'minor'=>(bool)elvado_wpv_pick_offer($o['offers'],$cur,'minor'),'report'=>is_array($rep)?$rep:null,
        'can_rollback'=>is_dir(elvado_wp_core_dir().'.prev')&&!empty($j['previous']),'previous'=>(string)($j['previous']??''),'updated'=>(int)($j['updated']??0)];
}
/**
 * Version aus einem bereits geladenen WordPress-ZIP übernehmen (Prüfung, Kernressourcen, Versionsdatei).
 * @return array{ok:bool,message:string,version?:string,report?:array}
 */
function elvado_wpv_apply_zip(string $zipPath, string $expectVersion=''): array {
    if(!class_exists('ZipArchive'))return ['ok'=>false,'message'=>'ZIP-Unterstützung fehlt auf dem Server'];
    $z=new ZipArchive();if($z->open($zipPath)!==true)return ['ok'=>false,'message'=>'ZIP konnte nicht geöffnet werden'];
    $vf=$z->getFromName('wordpress/wp-includes/version.php');$z->close();
    if(!$vf||!preg_match('/\$wp_version\s*=\s*\'([\d.]+)\'/',$vf,$m)||!elvado_wpv_valid($m[1]))return ['ok'=>false,'message'=>'Die Versionsangabe im Paket fehlt oder ist ungültig'];
    $ver=$m[1];$db=preg_match('/\$wp_db_version\s*=\s*(\d+)/',$vf,$d)?(int)$d[1]:0;
    if($expectVersion!==''&&$ver!==$expectVersion)return ['ok'=>false,'message'=>'Das Paket hat die Version '.$ver.' statt '.$expectVersion];
    if($db<1)return ['ok'=>false,'message'=>'Die Datenbankversion im Paket fehlt'];
    $report=elvado_wpv_report($zipPath,$ver);
    $cur=elvado_wpv_current();$prevVer=$cur['version'];$prevDb=$cur['db'];
    $r=elvado_wp_core_install($zipPath,$ver,true);
    if(empty($r['ok']))return ['ok'=>false,'message'=>(string)$r['message']];
    if(!elvado_wp_core_ready()){ return ['ok'=>false,'message'=>'Die Kernressourcen sind nach dem Wechsel nicht vollständig']; }
    $dir=dirname(elvado_wpv_file());if(!is_dir($dir))@mkdir($dir,0775,true);
    $tmp=elvado_wpv_file().'.tmp'.bin2hex(random_bytes(3));
    $data=['version'=>$ver,'db'=>$db,'updated'=>time(),'previous'=>$prevVer,'previous_db'=>$prevDb];
    if(@file_put_contents($tmp,json_encode($data),LOCK_EX)===false||!@rename($tmp,elvado_wpv_file()))return ['ok'=>false,'message'=>'Die Versionsdatei konnte nicht geschrieben werden'];
    @file_put_contents(elvado_wpv_report_file(),json_encode($report,JSON_UNESCAPED_UNICODE));
    return ['ok'=>true,'message'=>'WordPress-Version '.$ver.' übernommen.','version'=>$ver,'report'=>$report];
}
/** Neue Version laden und übernehmen. $target leer = beste Version nach Richtlinie ('minor'|'all'). */
function elvado_wpv_update(string $dataDir, string $policy='all', string $target=''): array {
    $o=elvado_wpv_offers($dataDir);
    $pick=$target!==''?(function() use($o,$target){ foreach($o['offers'] as $x)if($x['version']===$target&&str_starts_with($x['download'],'https://downloads.wordpress.org/release/'))return $x;return null; })()
        :elvado_wpv_pick_offer($o['offers'],ELVADO_WP_VERSION,$policy);
    if(!$pick)return ['ok'=>false,'message'=>'Keine neuere WordPress-Version verfügbar','none'=>true];
    $data=elvado_td_get($pick['download'],104857600,240);
    if($data===null||strlen($data)<1000000)return ['ok'=>false,'message'=>'Das Paket konnte nicht von wordpress.org geladen werden.'];
    // Prüfsumme (SHA-1) von wordpress.org
    $sum=elvado_td_get($pick['download'].'.sha1',256);
    if($sum!==null&&preg_match('/^[a-f0-9]{40}/i',trim($sum),$m)&&!hash_equals(strtolower($m[0]),sha1($data)))return ['ok'=>false,'message'=>'Die Prüfsumme des Pakets stimmt nicht'];
    $tmp=tempnam(sys_get_temp_dir(),'elvadowp');file_put_contents($tmp,$data);unset($data);
    try{ return elvado_wpv_apply_zip($tmp,$pick['version']); } finally { if(is_file($tmp))@unlink($tmp); }
}
/** Vorherige Version wiederherstellen. */
function elvado_wpv_rollback(): array {
    $dest=elvado_wp_core_dir();$prev=$dest.'.prev';$j=is_file(elvado_wpv_file())?json_decode((string)file_get_contents(elvado_wpv_file()),true):null;
    if(!is_dir($prev)||!is_array($j)||!elvado_wpv_valid((string)($j['previous']??'')))return ['ok'=>false,'message'=>'Keine vorherige Version gesichert'];
    $cur=$dest.'.tmp-cur'.bin2hex(random_bytes(2));
    if(is_dir($dest)&&!@rename($dest,$cur))return ['ok'=>false,'message'=>'Aktuelle Kernressourcen nicht verschiebbar'];
    if(!@rename($prev,$dest)){ if(is_dir($cur))@rename($cur,$dest);return ['ok'=>false,'message'=>'Wiederherstellung fehlgeschlagen']; }
    if(is_dir($cur))elvado_wpi_rm_tmp($cur);
    $data=['version'=>(string)$j['previous'],'db'=>max(1,(int)($j['previous_db']??57155)),'updated'=>time(),'previous'=>'','previous_db'=>0];
    if(@file_put_contents(elvado_wpv_file(),json_encode($data),LOCK_EX)===false)return ['ok'=>false,'message'=>'Die Versionsdatei konnte nicht geschrieben werden'];
    @unlink(elvado_wpv_report_file());
    return ['ok'=>true,'message'=>'WordPress-Version '.$data['version'].' wiederhergestellt.','version'=>$data['version']];
}

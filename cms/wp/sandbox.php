<?php
// Sandbox: ein abgetrennter Spielraum zwischen „Live“ und Deployment. Eigene WordPress-Optionen (Theme, Anpassungen, Widgets, Link-Struktur …) in
// cms/data/.wp-sandbox und eigene Themes in cms/wp-sandbox/themes; Beiträge, Seiten und Menüs kommen unverändert aus dem CMS (nur lesend).
// Erreichbar über einen geheimen Link (Cookie), nicht indexierbar, ohne Schreibzugriffe (kein Kommentieren/Senden). „Live stellen“ übernimmt
// ausschließlich Design-Optionen und neue Themes in die Live-Seite – mit Sicherung zum Zurückrollen. Reines PHP ohne WordPress-Abhängigkeit
// (wird vor dem Start der Laufzeit geladen: wp-front.php, api.php).

const RRW_SBX_KEY_EXACT=['stylesheet','template','current_theme','sidebars_widgets','permalink_structure','category_base','tag_base','rrw_link_hash','show_on_front','page_on_front','page_for_posts'];
function rrw_sbx_key_ok(string $k): bool { return in_array($k,RRW_SBX_KEY_EXACT,true)||(bool)preg_match('/^(theme_mods_[A-Za-z0-9_.-]+|widget_[A-Za-z0-9_-]+)$/',$k); }
function rrw_sbx_cms(): string { return dirname(__DIR__); }
function rrw_sbx_live_data(): string { return rrw_sbx_cms().'/data/.wp'; }
function rrw_sbx_data(): string { return rrw_sbx_cms().'/data/.wp-sandbox'; }
function rrw_sbx_themes(): string { return rrw_sbx_cms().'/wp-sandbox/themes'; }
function rrw_sbx_meta_file(): string { return rrw_sbx_data().'/sandbox.json'; }
function rrw_sbx_exists(): bool { return is_file(rrw_sbx_meta_file()); }
function rrw_sbx_meta(): array { $d=json_decode((string)@file_get_contents(rrw_sbx_meta_file()),true);return is_array($d)?$d:[]; }
function rrw_sbx_write_meta(array $m): bool { $f=rrw_sbx_meta_file();$t=$f.'.'.bin2hex(random_bytes(3)).'.tmp';if(@file_put_contents($t,json_encode($m,JSON_UNESCAPED_UNICODE))===false)return false;return @rename($t,$f); }
/** Zugriffsschlüssel prüfen (zeitgleich-sicher). */
function rrw_sbx_token_ok(string $t): bool { if(!preg_match('/^[a-f0-9]{32}$/',$t)||!rrw_sbx_exists())return false;$m=rrw_sbx_meta();return is_string($m['token']??null)&&hash_equals($m['token'],$t); }
/** Sandbox-Modus für diese Anfrage einschalten – vor dem Laden von cms/wp/load.php aufrufen. */
function rrw_sbx_enter(): void {
    if(defined('RRW_WP_SANDBOX'))return;
    define('RRW_WP_SANDBOX',true);define('RRW_WP_DATA',rrw_sbx_data());define('RRW_WP_SANDBOX_THEMES',rrw_sbx_themes());define('DISABLE_WP_CRON',true);
}
function rrw_sbx_protect(string $dir): void { if(!is_dir($dir))@mkdir($dir,0775,true);if(!is_file($dir.'/.htaccess'))@file_put_contents($dir.'/.htaccess',"Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");if(!is_file($dir.'/index.html'))@file_put_contents($dir.'/index.html',''); }
function rrw_sbx_rm(string $p): void {
    if(is_link($p)||is_file($p)){ @unlink($p);return; }
    if(!is_dir($p))return;
    foreach(scandir($p)?:[] as $e)if($e!=='.'&&$e!=='..')rrw_sbx_rm($p.'/'.$e);
    @rmdir($p);
}
function rrw_sbx_copy(string $src, string $dst): bool {
    if(is_link($src))return true;   // keine Verknüpfungen übernehmen
    if(is_file($src)){ $d=dirname($dst);if(!is_dir($d)&&!@mkdir($d,0775,true))return false;return @copy($src,$dst); }
    if(!is_dir($src))return false;
    if(!is_dir($dst)&&!@mkdir($dst,0775,true))return false;$ok=true;
    foreach(scandir($src)?:[] as $e)if($e!=='.'&&$e!=='..')$ok=rrw_sbx_copy($src.'/'.$e,$dst.'/'.$e)&&$ok;
    return $ok;
}
function rrw_sbx_read_opts(string $dir): array { $d=json_decode((string)@file_get_contents($dir.'/options.json'),true);return is_array($d)?$d:[]; }
/** Optionen unter Sperre schreiben (gleiches Muster wie rrw_wp_opts_mutate). */
function rrw_sbx_write_opts(string $dir, callable $fn): bool {
    if(!is_dir($dir)&&!@mkdir($dir,0775,true))return false;
    $h=@fopen($dir.'/.options.lock','c');if($h)@flock($h,LOCK_EX);
    try{
        $all=rrw_sbx_read_opts($dir);$fn($all);$f=$dir.'/options.json';$t=$f.'.'.bin2hex(random_bytes(3)).'.tmp';
        if(@file_put_contents($t,json_encode($all,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))===false)return false;
        return @rename($t,$f);
    } finally { if($h){ @flock($h,LOCK_UN);@fclose($h); } }
}
/** Themes (Ordnernamen), die ein Verzeichnis enthält. */
function rrw_sbx_theme_dirs(string $root): array { $o=[];foreach(glob($root.'/*/style.css')?:[] as $c)$o[]=basename(dirname($c));sort($o);return $o; }

/** Sandbox anlegen: Optionen der Live-Seite übernehmen, eigenes Theme-Verzeichnis. Rückgabe Fehlertext oder null. */
function rrw_sbx_create(): ?string {
    if(rrw_sbx_exists())return 'Die Sandbox gibt es schon.';
    $d=rrw_sbx_data();rrw_sbx_protect($d);
    if(!is_dir($d))return 'Sandbox-Ordner konnte nicht angelegt werden (Schreibrechte für cms/data prüfen).';
    $live=rrw_sbx_live_data();
    foreach(['options.json','salt.json','wp-version.json'] as $f)if(is_file($live.'/'.$f)&&!@copy($live.'/'.$f,$d.'/'.$f))return 'Live-Einstellungen konnten nicht kopiert werden.';
    if(is_file($live.'/front-on'))@copy($live.'/front-on',$d.'/front-on');
    $t=rrw_sbx_themes();if(!is_dir($t)&&!@mkdir($t,0775,true))return 'Sandbox-Theme-Ordner konnte nicht angelegt werden (Schreibrechte für cms/ prüfen).';
    @file_put_contents(dirname($t).'/.htaccess',"Options -Indexes\n");
    $m=['created'=>gmdate('c'),'token'=>bin2hex(random_bytes(16)),'version'=>1];
    return rrw_sbx_write_meta($m)?null:'Sandbox konnte nicht gespeichert werden.';
}
function rrw_sbx_delete(): void { rrw_sbx_rm(rrw_sbx_data());rrw_sbx_rm(dirname(rrw_sbx_themes())); }
/** Zurücksetzen: Sandbox wird wieder ein Abbild der Live-Seite (Themes der Sandbox werden entfernt, der Zugangslink bleibt). */
function rrw_sbx_reset(): ?string {
    if(!rrw_sbx_exists())return 'Es gibt noch keine Sandbox.';
    $m=rrw_sbx_meta();rrw_sbx_delete();$e=rrw_sbx_create();if($e!==null)return $e;
    $n=rrw_sbx_meta();$n['token']=(string)($m['token']??$n['token']);return rrw_sbx_write_meta($n)?null:'Sandbox konnte nicht gespeichert werden.';
}
function rrw_sbx_rotate(): ?string { if(!rrw_sbx_exists())return 'Es gibt noch keine Sandbox.';$m=rrw_sbx_meta();$m['token']=bin2hex(random_bytes(16));return rrw_sbx_write_meta($m)?null:'Link konnte nicht erneuert werden.'; }

/** Unterschiede Sandbox ↔ Live: geänderte Design-Optionen, nur in der Sandbox vorhandene Themes, Auslieferung (WordPress-Theme/Portal-Design). */
function rrw_sbx_diff(): array {
    $s=rrw_sbx_read_opts(rrw_sbx_data());$l=rrw_sbx_read_opts(rrw_sbx_live_data());$keys=[];
    foreach(array_unique(array_merge(array_keys($s),array_keys($l))) as $k){ if(!rrw_sbx_key_ok((string)$k))continue;if(($s[$k]['v']??null)!==($l[$k]['v']??null))$keys[]=(string)$k; }
    sort($keys);
    $new=array_values(array_diff(rrw_sbx_theme_dirs(rrw_sbx_themes()),rrw_sbx_theme_dirs(rrw_sbx_cms().'/wp-content/themes')));
    $val=function(array $o,string $k){ $v=@unserialize((string)($o[$k]['v']??''));return is_string($v)?$v:''; };
    return ['options'=>$keys,'new_themes'=>$new,'sandbox_theme'=>$val($s,'stylesheet'),'live_theme'=>$val($l,'stylesheet'),
        'sandbox_front'=>is_file(rrw_sbx_data().'/front-on'),'live_front'=>is_file(rrw_sbx_live_data().'/front-on'),'changed'=>$keys||$new||is_file(rrw_sbx_data().'/front-on')!==is_file(rrw_sbx_live_data().'/front-on')];
}
function rrw_sbx_backup_dir(): string { return rrw_sbx_live_data().'/sandbox-backups'; }
/** Live stellen: Design-Optionen und neue Themes übernehmen, vorher Sicherung. Rückgabe ['ok'=>bool,'message'=>…,'backup'=>id,'themes'=>[…]]. */
function rrw_sbx_publish(): array {
    if(!rrw_sbx_exists())return ['ok'=>false,'message'=>'Es gibt noch keine Sandbox.'];
    $diff=rrw_sbx_diff();if(!$diff['changed'])return ['ok'=>false,'message'=>'Die Sandbox unterscheidet sich nicht von der Live-Seite.'];
    $liveD=rrw_sbx_live_data();$liveT=rrw_sbx_cms().'/wp-content/themes';$sbxT=rrw_sbx_themes();
    if(!is_dir($liveD)&&!@mkdir($liveD,0775,true))return ['ok'=>false,'message'=>'Live-Ordner nicht beschreibbar.'];
    if(!is_dir($liveT)&&!@mkdir($liveT,0775,true))return ['ok'=>false,'message'=>'Theme-Ordner der Live-Seite nicht beschreibbar.'];
    // Themes zuerst (ein Fehler bricht ab, bevor Optionen geändert werden)
    $copied=[];
    foreach($diff['new_themes'] as $slug){
        if(!preg_match('/^[a-z0-9_-]{1,80}$/',$slug)){ continue; }
        $tmp=$liveT.'/.sbx-'.bin2hex(random_bytes(3));
        if(!rrw_sbx_copy($sbxT.'/'.$slug,$tmp)||!@rename($tmp,$liveT.'/'.$slug)){ rrw_sbx_rm($tmp);foreach($copied as $c)rrw_sbx_rm($liveT.'/'.$c);return ['ok'=>false,'message'=>'Theme „'.$slug.'“ konnte nicht übernommen werden (Schreibrechte prüfen). Es wurde nichts geändert.']; }
        $copied[]=$slug;
    }
    // Sicherung der Live-Werte
    $sbxO=rrw_sbx_read_opts(rrw_sbx_data());$bk=['created'=>gmdate('c'),'options'=>[],'front_on'=>is_file($liveD.'/front-on'),'themes'=>$copied];
    $liveO=rrw_sbx_read_opts($liveD);foreach($diff['options'] as $k)$bk['options'][$k]=$liveO[$k]??null;
    $bd=rrw_sbx_backup_dir();rrw_sbx_protect($bd);$id=gmdate('Ymd-His');
    if(@file_put_contents($bd.'/'.$id.'.json',json_encode($bk,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))===false){ foreach($copied as $c)rrw_sbx_rm($liveT.'/'.$c);return ['ok'=>false,'message'=>'Sicherung konnte nicht geschrieben werden. Es wurde nichts geändert.']; }
    foreach(array_slice(array_reverse(glob($bd.'/*.json')?:[]),10) as $old)@unlink($old);   // nur die letzten 10 Sicherungen
    $ok=rrw_sbx_write_opts($liveD,function(array &$all) use($diff,$sbxO){ foreach($diff['options'] as $k){ if(isset($sbxO[$k]))$all[$k]=$sbxO[$k];else unset($all[$k]); } });
    if(!$ok){ foreach($copied as $c)rrw_sbx_rm($liveT.'/'.$c);@unlink($bd.'/'.$id.'.json');return ['ok'=>false,'message'=>'Die Live-Einstellungen konnten nicht geschrieben werden.']; }
    $sf=is_file(rrw_sbx_data().'/front-on');$lf=$liveD.'/front-on';
    if($sf&&!is_file($lf))@file_put_contents($lf,gmdate('c'));elseif(!$sf&&is_file($lf))@unlink($lf);
    $m=rrw_sbx_meta();$m['last_publish']=gmdate('c');rrw_sbx_write_meta($m);
    return ['ok'=>true,'message'=>'Die Sandbox ist jetzt live.','backup'=>$id,'themes'=>$copied,'options'=>count($diff['options'])];
}
function rrw_sbx_backups(): array { $o=[];foreach(array_reverse(glob(rrw_sbx_backup_dir().'/*.json')?:[]) as $f){ $d=json_decode((string)@file_get_contents($f),true);if(is_array($d))$o[]=['id'=>basename($f,'.json'),'created'=>(string)($d['created']??''),'options'=>count((array)($d['options']??[]))]; }return $o; }
/** Stand vor einer Veröffentlichung wiederherstellen (neu hinzugekommene Themes bleiben liegen). */
function rrw_sbx_rollback(string $id): ?string {
    if(!preg_match('/^\d{8}-\d{6}$/',$id))return 'Ungültige Sicherung.';
    $f=rrw_sbx_backup_dir().'/'.$id.'.json';$d=is_file($f)?json_decode((string)@file_get_contents($f),true):null;if(!is_array($d))return 'Sicherung nicht gefunden.';
    $liveD=rrw_sbx_live_data();
    $ok=rrw_sbx_write_opts($liveD,function(array &$all) use($d){ foreach((array)$d['options'] as $k=>$v){ if(!rrw_sbx_key_ok((string)$k))continue;if($v===null)unset($all[$k]);else $all[$k]=$v; } });
    if(!$ok)return 'Die Live-Einstellungen konnten nicht zurückgesetzt werden.';
    $lf=$liveD.'/front-on';if(!empty($d['front_on'])&&!is_file($lf))@file_put_contents($lf,gmdate('c'));elseif(empty($d['front_on'])&&is_file($lf))@unlink($lf);
    return null;
}
/** Zugangslink (Pfad) für die Sandbox. */
function rrw_sbx_url(): string { $m=rrw_sbx_meta();return '/?rrw_sbx='.rawurlencode((string)($m['token']??'')); }

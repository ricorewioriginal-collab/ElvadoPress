<?php
declare(strict_types=1);
// Theme-Konfiguration (neutral, für alle Themes mit eigenem Verwaltungsmenü): Ein Theme meldet in elvado_tc_registry() ein Schema
// (Abschnitte als Formular oder Liste mit typisierten Feldern). Das CMS erzeugt daraus automatisch das Menü, den Editor
// (cms/assets/theme-config.js) und die serverseitige Bereinigung. Das Menü erscheint nur, solange das Theme die Website ausliefert.
// Daten: cms/data/.tools/themeconf-<id>.json (gesperrter Ordner). Ohne Abhängigkeit vom CMS-Kern und ohne WordPress-Laufzeit.
require_once __DIR__.'/tools.php';

/** Registrierte Konfigurationen: id => ['theme'=>Theme-Ordner, 'title'=>…, 'hint'=>…, 'icon'=>…, 'menu'=>Menütext, 'schema'=>callable]. */
function elvado_tc_registry(): array {
    static $r=null;
    if($r===null){
        $r=[];
        foreach(['band','creator'] as $id)if(is_file(__DIR__.'/'.$id.'.php')){ require_once __DIR__.'/'.$id.'.php';$r[$id]=('elvado_'.$id.'_registration')(); }
    }
    return $r;
}
function elvado_tc_entry(string $id): ?array { $r=elvado_tc_registry();return $r[$id]??null; }
function elvado_tc_file(string $dataDir,string $id): string { return elvado_tools_dir($dataDir).'/themeconf-'.preg_replace('/[^a-z0-9_-]/','',$id).'.json'; }
/** Liefert das Theme dieses Namens gerade die Website aus? (WordPress-Theme-Laufzeit: Flag front-on + Option stylesheet) */
function elvado_tc_theme_active(string $dataDir,string $theme): bool {
    $wp=rtrim($dataDir,'/').'/.wp';if(!is_file($wp.'/front-on'))return false;
    $o=json_decode((string)@file_get_contents($wp.'/options.json'),true);
    $v=is_array($o)&&isset($o['stylesheet']['v'])?@unserialize((string)$o['stylesheet']['v']):false;
    return $v===$theme;
}
/** Zustand aller Konfigurationen für das Menü: [id => ['active'=>bool,'title'=>…,'menu'=>…,'icon'=>…]]. */
function elvado_tc_state(string $dataDir): array {
    $o=[];foreach(elvado_tc_registry() as $id=>$e)$o[$id]=['active'=>elvado_tc_theme_active($dataDir,$e['theme']),'title'=>$e['title'],'menu'=>$e['menu'],'icon'=>$e['icon'],'theme'=>$e['theme']];
    return $o;
}
/** Schema ohne Funktionen (für den Editor). */
function elvado_tc_public_schema(string $id): ?array {
    $e=elvado_tc_entry($id);if(!$e)return null;
    return ['id'=>$id,'title'=>$e['title'],'hint'=>$e['hint'],'icon'=>$e['icon'],'theme'=>$e['theme'],'sections'=>($e['schema'])()];
}

/* ───────── Bereinigung ───────── */
function elvado_tc_text($v,int $max=200): string { return mb_substr(trim(preg_replace('/\s+/u',' ',strip_tags((string)$v))),0,$max); }
function elvado_tc_longtext($v,int $max=4000): string { $s=str_replace(["\r\n","\r"],"\n",strip_tags((string)$v));return mb_substr(trim(preg_replace("/\n{3,}/","\n\n",$s)),0,$max); }
/** URL: http(s) oder – für Bilder und eigene Seiten – ein Pfad auf dieser Website. */
function elvado_tc_url($v,bool $relOk=true): string {
    $u=trim((string)$v);if($u===''||strlen($u)>1200||preg_match('/[\s"\'<>]/',$u))return '';
    if($relOk&&preg_match('~^/(?!/)[^\s]*$~',$u))return $u;
    $p=@parse_url($u);return is_array($p)&&in_array(strtolower((string)($p['scheme']??'')),['http','https'],true)&&!empty($p['host'])?$u:'';
}
function elvado_tc_field(array $f,$v) {
    $t=(string)$f['type'];
    switch($t){
        case 'text': return elvado_tc_text($v,(int)($f['max']??200));
        case 'textarea': return elvado_tc_longtext($v,(int)($f['max']??4000));
        case 'url': return elvado_tc_url($v);
        case 'image': return elvado_tc_url($v);
        case 'email': $e=trim((string)$v);return filter_var($e,FILTER_VALIDATE_EMAIL)&&strlen($e)<=200?$e:'';
        case 'date': $d=trim((string)$v);return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$d,$m)&&checkdate((int)$m[2],(int)$m[3],(int)$m[1])?$d:'';
        case 'time': return preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/',trim((string)$v),$m)?sprintf('%02d:%02d',$m[1],$m[2]):'';
        case 'select': $v=(string)$v;return isset($f['options'][$v])?$v:(string)($f['default']??array_key_first($f['options']));
        case 'checkbox': return filter_var($v,FILTER_VALIDATE_BOOLEAN);
        case 'number': return max((int)($f['min']??0),min((int)($f['max']??100000),(int)$v));
    }
    return '';
}
function elvado_tc_default_field(array $f) { $t=(string)$f['type'];return $f['default']??($t==='checkbox'?false:($t==='number'?(int)($f['min']??0):($t==='select'?array_key_first($f['options']):''))); }
/** Eingabe → bereinigte Konfiguration nach Schema (unbekannte Schlüssel entfallen, Listen begrenzt). */
function elvado_tc_clean(array $sections,$in): array {
    $in=is_array($in)?$in:[];$out=[];
    foreach($sections as $s){
        $id=(string)$s['id'];$raw=$in[$id]??[];$fields=(array)$s['fields'];
        if(($s['kind']??'form')==='list'){
            $items=[];
            foreach(is_array($raw)?$raw:[] as $row){
                if(!is_array($row)||count($items)>=(int)($s['max']??50))continue;$it=[];
                foreach($fields as $f)$it[$f['k']]=array_key_exists($f['k'],$row)?elvado_tc_field($f,$row[$f['k']]):elvado_tc_default_field($f);
                $key=(string)($s['required']??'');if($key!==''&&($it[$key]??'')==='')continue;   // Pflichtfeld leer → Eintrag verwerfen
                $items[]=$it;
            }
            $out[$id]=$items;
        }else{
            $raw=is_array($raw)?$raw:[];$o=[];
            foreach($fields as $f)$o[$f['k']]=array_key_exists($f['k'],$raw)?elvado_tc_field($f,$raw[$f['k']]):elvado_tc_default_field($f);
            $out[$id]=$o;
        }
    }
    return $out;
}
function elvado_tc_load(string $dataDir,string $id): array {
    $e=elvado_tc_entry($id);if(!$e)return [];
    return elvado_tc_clean(($e['schema'])(),elvado_tools_read(elvado_tc_file($dataDir,$id),[]));
}
function elvado_tc_save(string $dataDir,string $id,$in): array {
    $e=elvado_tc_entry($id);if(!$e)throw new RuntimeException('Unbekannte Konfiguration');
    $c=elvado_tc_clean(($e['schema'])(),$in);elvado_tools_write(elvado_tools_dir($dataDir),'themeconf-'.$id.'.json',$c);return $c;
}

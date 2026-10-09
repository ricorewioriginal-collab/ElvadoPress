<?php
declare(strict_types=1);
// ---------------------------------------------------------------------------------------------
// Design-Pakete: Ein Theme kann ein Paket mitbringen ("pack" in theme.json); Pakete können Komponenten (cms/packs/<paket>/components.php)
// und Verwaltungs-Skripte (cms/packs/<paket>/admin.js) beisteuern. ElvadoPress liefert selbst kein Paket aus – ohne Paket-Theme ist
// rrw_pack_available() immer false.
// ---------------------------------------------------------------------------------------------
/** Paket, das ein Theme mitbringt ('' = keines). Liest cms/themes/<id>/theme.json. */
function rrw_pack_of_theme(string $themeId, ?string $themesDir=null): string {
    $themesDir=$themesDir??dirname(__DIR__).'/themes';
    if(!preg_match('/^[a-z0-9_-]{1,80}$/i',$themeId))return '';
    $f=$themesDir.'/'.$themeId.'/theme.json';if(!is_file($f))return '';
    $m=json_decode((string)@file_get_contents($f),true);
    $p=is_array($m)?(string)($m['pack']??''):'';
    return preg_match('/^[a-z0-9-]{1,40}$/',$p)?$p:'';
}
/** Gibt es das Paket in dieser Installation (ein Theme bringt es mit)? Im eigenständigen CMS ohne die RicoReWi-Themes: nein. */
function rrw_pack_available(string $pack, ?string $themesDir=null): bool {
    static $cache=[];
    $dir=$themesDir??dirname(__DIR__).'/themes';$key=$pack.'|'.$dir;
    if(!array_key_exists($key,$cache)){
        $cache[$key]=false;
        foreach(glob($dir.'/*/theme.json')?:[] as $f)if(rrw_pack_of_theme(basename(dirname($f)),$dir)===$pack){ $cache[$key]=true;break; }
    }
    return $cache[$key];
}

/** Adresse der Hauptseite, wenn nichts eingestellt ist: der aufgerufene Host. */
function rrw_default_canonical_base(): string {
    $h=preg_replace('/[^a-z0-9.:-]/i','',(string)($_SERVER['HTTP_HOST']??''));
    return $h!==''?'https://'.$h:'';
}

/** Portal-Design, wenn nichts gewählt ist: keines (ElvadoPress liefert über ein WordPress-Theme aus). */
function rrw_default_theme_id(): string { return ''; }

/** Öffentliche Adresse der Website (ohne Schrägstrich am Ende): Hauptdomain der Hauptmarke, sonst die eingestellte oder aufgerufene Adresse. */
function rrw_site_origin(array $site): string {
    $reg=function_exists('rrw_brands_registry')?rrw_brands_registry($site):['default'=>'','items'=>[]];
    foreach($reg['items'] as $b)if($b['id']===$reg['default']&&trim((string)($b['primary_domain']??''))!=='')return 'https://'.trim((string)$b['primary_domain']);
    return rtrim((string)($site['seo']['canonical_base']??rrw_default_canonical_base()),'/');
}

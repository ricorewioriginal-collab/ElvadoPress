<?php
declare(strict_types=1);
// ---------------------------------------------------------------------------------------------
// Design-Pakete: Ein Theme kann ein Paket mitbringen ("pack" in theme.json). Das Paket "ricorewi-radio" umfasst alles,
// was zum RicoReWi-Radioportal gehört: die Radio-Widgets (Sender, Jetzt läuft, Sendeplan, Voting …), Alexa-Skill,
// Radioverzeichnis, Sender-Netzwerk und die mitgelieferten Apps. Das Paket ist nur aktiv, solange das RicoReWi-Portal
// (ein Theme mit diesem Paket) die Website ausliefert – bei einem WordPress-Theme oder ohne die Themes verschwinden die Teile.
// Das eigenständige CMS wird ohne die RicoReWi-Themes ausgeliefert; dann ist das Paket nie aktiv.
// ---------------------------------------------------------------------------------------------
const RRW_PACK_RADIO='ricorewi-radio';
/** Widget-Typen des Radio-Pakets (ids wie in rrw_widget_types()). */
const RRW_PACK_RADIO_WIDGETS=['stations','now-playing','schedule','random-station','favorites','podcast','voting','song-voting','studiomail','voicemail','wunsch','poll','social-wall','social-single'];

/** Paket, das ein Theme mitbringt ('' = keines). Liest cms/themes/<id>/theme.json. */
function rrw_pack_of_theme(string $themeId, ?string $themesDir=null): string {
    $themesDir=$themesDir??dirname(__DIR__).'/themes';
    if(!preg_match('/^[a-z0-9_-]{1,80}$/i',$themeId))return '';
    $f=$themesDir.'/'.$themeId.'/theme.json';if(!is_file($f))return '';
    $m=json_decode((string)@file_get_contents($f),true);
    $p=is_array($m)?(string)($m['pack']??''):'';
    return preg_match('/^[a-z0-9-]{1,40}$/',$p)?$p:'';
}
/** Ist das Paket aktiv? $wpFrontOn = true, wenn ein WordPress-Theme die Website ausliefert. */
function rrw_pack_active(array $site, string $pack=RRW_PACK_RADIO, ?bool $wpFrontOn=null, ?string $themesDir=null): bool {
    if($wpFrontOn===null)$wpFrontOn=defined('RRW_WP_DATA')?is_file(RRW_WP_DATA.'/front-on'):is_file(__DIR__.'/../data/.wp/front-on');
    if($wpFrontOn)return false;
    $active=(string)($site['theme']['active']??'ricorewi-neon');
    return rrw_pack_of_theme($active,$themesDir)===$pack;
}
/** Zustand aller Pakete für das CMS. */
function rrw_pack_status(array $site): array { return [RRW_PACK_RADIO=>rrw_pack_active($site,RRW_PACK_RADIO)]; }

/** Gibt es das Paket in dieser Installation (ein Theme bringt es mit)? Im eigenständigen CMS ohne die RicoReWi-Themes: nein. */
function rrw_pack_available(string $pack=RRW_PACK_RADIO, ?string $themesDir=null): bool {
    static $cache=[];
    $dir=$themesDir??dirname(__DIR__).'/themes';$key=$pack.'|'.$dir;
    if(!array_key_exists($key,$cache)){
        $cache[$key]=false;
        foreach(glob($dir.'/*/theme.json')?:[] as $f)if(rrw_pack_of_theme(basename(dirname($f)),$dir)===$pack){ $cache[$key]=true;break; }
    }
    return $cache[$key];
}

/** Adresse der Hauptseite, wenn nichts eingestellt ist: mit Paket die bisherige RicoReWi-Adresse, sonst der aufgerufene Host. */
function rrw_default_canonical_base(): string {
    if(rrw_pack_available())return 'https://www.ricorewi-radio.de';
    $h=preg_replace('/[^a-z0-9.:-]/i','',(string)($_SERVER['HTTP_HOST']??''));
    return $h!==''?'https://'.$h:'';
}

/** Portal-Design, wenn nichts gewählt ist: mit Paket das RicoReWi-Standarddesign, sonst keines (das eigenständige CMS liefert über ein WordPress-Theme aus). */
function rrw_default_theme_id(): string { return rrw_pack_available()?'ricorewi-neon':''; }

/** Öffentliche Adresse der Website (ohne Schrägstrich am Ende): Hauptdomain der Hauptmarke, sonst die eingestellte oder aufgerufene Adresse. */
function rrw_site_origin(array $site): string {
    $reg=function_exists('rrw_brands_registry')?rrw_brands_registry($site):['default'=>'','items'=>[]];
    foreach($reg['items'] as $b)if($b['id']===$reg['default']&&trim((string)($b['primary_domain']??''))!=='')return 'https://'.trim((string)$b['primary_domain']);
    return rtrim((string)($site['seo']['canonical_base']??rrw_default_canonical_base()),'/');
}

<?php
// Übersetzungen: .mo-Dateien (gettext) lesen und __(), _x(), _n(), _nx() damit beantworten.
// Sprachpakete von WordPress.org (de_DE) legt das CMS unter cms/wp-content/languages/ ab (Core: de_DE.mo, Themes: themes/<slug>-de_DE.mo, Plugins: plugins/<slug>-de_DE.mo).
class ELVADO_MO {
    public array $t=[];public bool $pluralN1=true;   // pluralN1: Mehrzahl bei n != 1 (de/en/…); sonst bei n > 1 (fr/pt_BR …)
    public function load(string $file): bool {
        if(!is_file($file)||filesize($file)>8388608)return false;
        $d=(string)@file_get_contents($file);if(strlen($d)<28)return false;
        $m=unpack('V',substr($d,0,4))[1];$f=$m===0x950412de?'V':($m===0xde120495?'N':'');if($f==='')return false;
        $h=unpack("{$f}1magic/{$f}1rev/{$f}1n/{$f}1o/{$f}1t",substr($d,0,20));$n=(int)$h['n'];$o=(int)$h['o'];$tt=(int)$h['t'];
        if($n<0||$n>200000||$o+8*$n>strlen($d)||$tt+8*$n>strlen($d))return false;
        for($i=0;$i<$n;$i++){
            $a=unpack("{$f}1l/{$f}1p",substr($d,$o+8*$i,8));$b=unpack("{$f}1l/{$f}1p",substr($d,$tt+8*$i,8));
            if($a['p']+$a['l']>strlen($d)||$b['p']+$b['l']>strlen($d))continue;
            $orig=substr($d,$a['p'],$a['l']);$tr=substr($d,$b['p'],$b['l']);
            if($orig===''){ if(preg_match('/plural=\s*\(?\s*n\s*>\s*1/i',$tr))$this->pluralN1=false;continue; }
            if($tr==='')continue;
            $this->t[$orig]=explode("\0",$tr);
        }
        return true;
    }
    public function get(string $key): ?array { return $this->t[$key]??null; }
}
$GLOBALS['elvado_wp_mo']=$GLOBALS['elvado_wp_mo']??[];$GLOBALS['elvado_wp_mo_tried']=$GLOBALS['elvado_wp_mo_tried']??[];

function elvado_wp_mo_merge(string $domain, string $file): bool {
    $mo=new ELVADO_MO();if(!$mo->load($file))return false;
    if(isset($GLOBALS['elvado_wp_mo'][$domain])){ $GLOBALS['elvado_wp_mo'][$domain]->t=$mo->t+$GLOBALS['elvado_wp_mo'][$domain]->t; }   // zuerst geladene Datei hat Vorrang
    else $GLOBALS['elvado_wp_mo'][$domain]=$mo;
    return true;
}
function elvado_wp_locale_active(): bool { return strncmp(get_locale(),'en',2)!==0; }
/** Verzeichnis für Sprachdateien (cms/wp-content/languages). */
function elvado_wp_lang_dir(): string { return rtrim(WP_CONTENT_DIR,'/').'/languages'; }
/** Lädt eine Textdomäne bei Bedarf selbst aus dem Sprachverzeichnis (einmal je Domäne). */
function elvado_wp_mo_domain(string $domain): ?ELVADO_MO {
    if(isset($GLOBALS['elvado_wp_mo'][$domain]))return $GLOBALS['elvado_wp_mo'][$domain];
    if(isset($GLOBALS['elvado_wp_mo_tried'][$domain]))return null;
    $GLOBALS['elvado_wp_mo_tried'][$domain]=true;$loc=get_locale();$dir=elvado_wp_lang_dir();
    $cands=$domain==='default'?[$dir.'/'.$loc.'.mo']:[$dir.'/themes/'.$domain.'-'.$loc.'.mo',$dir.'/plugins/'.$domain.'-'.$loc.'.mo',$dir.'/'.$domain.'-'.$loc.'.mo'];
    foreach($cands as $f)if(elvado_wp_mo_merge($domain,$f))break;
    return $GLOBALS['elvado_wp_mo'][$domain]??null;
}
function elvado_wp_tr(string $text, string $domain, ?string $ctx=null): string {
    if(!elvado_wp_locale_active())return $text;
    $mo=elvado_wp_mo_domain($domain===''?'default':$domain);if(!$mo)return $text;
    $r=$mo->get($ctx!==null?$ctx."\x04".$text:$text);
    return $r&&$r[0]!==''?$r[0]:$text;
}
function elvado_wp_trn(string $single, string $plural, int $n, string $domain, ?string $ctx=null): string {
    $mo=elvado_wp_locale_active()?elvado_wp_mo_domain($domain===''?'default':$domain):null;
    $isPlural=$mo&&!$mo->pluralN1?$n>1:$n!==1;
    if($mo){
        $r=$mo->get(($ctx!==null?$ctx."\x04":'').$single."\0".$plural);
        if($r){ $i=$isPlural?1:0;if(isset($r[$i])&&$r[$i]!=='')return $r[$i]; }
    }
    return $n===1?$single:$plural;
}

function __($text, $domain='default') { $t=elvado_wp_tr((string)$text,(string)$domain);return apply_filters('gettext',$t,(string)$text,$domain); }
function _e($text, $domain='default') { echo __($text,$domain); }
function _x($text, $context, $domain='default') { $t=elvado_wp_tr((string)$text,(string)$domain,(string)$context);return apply_filters('gettext_with_context',$t,(string)$text,$context,$domain); }
function _ex($text, $context, $domain='default') { echo _x($text,$context,$domain); }
function _n($single, $plural, $number, $domain='default') { $t=elvado_wp_trn((string)$single,(string)$plural,(int)$number,(string)$domain);return apply_filters('ngettext',$t,$single,$plural,$number,$domain); }
function _nx($single, $plural, $number, $context, $domain='default') { $t=elvado_wp_trn((string)$single,(string)$plural,(int)$number,(string)$domain,(string)$context);return apply_filters('ngettext_with_context',$t,$single,$plural,$number,$context,$domain); }
function translate($text, $domain='default') { return __($text,$domain); }
function translate_with_gettext_context($t, $c, $d='default') { return _x($t,$c,$d); }
function __ngettext($s,$p,$n,$d='default') { return _n($s,$p,$n,$d); }
function load_textdomain($domain, $mofile, $locale=null) { $ok=elvado_wp_mo_merge((string)$domain,(string)$mofile);if($ok)unset($GLOBALS['elvado_wp_mo_tried'][$domain]);return $ok; }
function load_plugin_textdomain($domain, $deprecated=false, $plugin_rel_path=false) {
    $loc=get_locale();$ok=false;
    if($plugin_rel_path)foreach([WP_PLUGIN_DIR.'/'.trim((string)$plugin_rel_path,'/').'/'.$domain.'-'.$loc.'.mo'] as $f)$ok=$ok||elvado_wp_mo_merge((string)$domain,$f);
    return $ok||(bool)elvado_wp_mo_domain((string)$domain);
}
function load_muplugin_textdomain($domain, $p=false) { return false; }
function load_theme_textdomain($domain, $path=false) {
    $loc=get_locale();$ok=false;$path=$path?rtrim((string)$path,'/'):get_template_directory().'/languages';
    foreach([$path.'/'.$loc.'.mo',$path.'/'.$domain.'-'.$loc.'.mo'] as $f)if(elvado_wp_mo_merge((string)$domain,$f)){ $ok=true;break; }
    return $ok||(bool)elvado_wp_mo_domain((string)$domain);
}
function load_child_theme_textdomain($domain, $path=false) { return load_theme_textdomain($domain,$path?:get_stylesheet_directory().'/languages'); }
function unload_textdomain($domain, $reloadable=false) { unset($GLOBALS['elvado_wp_mo'][$domain]);return true; }
function is_textdomain_loaded($domain) { return isset($GLOBALS['elvado_wp_mo'][$domain]); }

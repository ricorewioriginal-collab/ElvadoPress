<?php
declare(strict_types=1);

// Zentrale Produktbezeichnung der Verwaltung. Alle sichtbaren Namen (Titel, Überschrift, Anmeldung,
// Generator-Angaben) kommen von hier. Optional überschreibt cms/data/product.json
// die Werte; ohne Datei gilt exakt die bisherige Anzeige. Wird kein "name" gesetzt, bleiben auch die
// abgeleiteten Texte bei ihren bisherigen Formulierungen.
//
// product.json (alles optional): name, slug, logo, title, heading, access_name, generator

function rrw_product_file(): string { return defined('RRW_PRODUCT_FILE')?(string)RRW_PRODUCT_FILE:__DIR__.'/../data/product.json'; }

/** Bisherige Werte (Standard). */
function rrw_product_defaults(): array {
    $d=[
        'name'=>'ElvadoPress','slug'=>'elvadopress','logo'=>'',
        'title'=>'ElvadoPress','heading'=>'ElvadoPress Verwaltung','access_name'=>'ElvadoPress','generator'=>'ElvadoPress',
    ];
    // Eigenständiges Paket (z. B. ElvadoPress): cms/lib/product.default.json legt den Standard fest, den das Bauen des Pakets mitbringt
    $f=__DIR__.'/product.default.json';
    if(is_file($f)){
        $j=json_decode((string)@file_get_contents($f),true);
        if(is_array($j)){
            $o=rrw_product_clean($j);
            if(isset($o['name'])){ $d['name']=$o['name'];$d['title']=$o['name'];$d['heading']=$o['name'].' Verwaltung';$d['access_name']=$o['name'];$d['generator']=$o['name'];$d['slug']=rrw_product_slugify($o['name'])?:$d['slug']; }
            foreach($o as $k=>$v)$d[$k]=$v;
        }
    }
    return $d;
}
/** Namen in einen URL-tauglichen Kurznamen wandeln. */
function rrw_product_slugify(string $name): string {
    $s=strtr(mb_strtolower($name),['ä'=>'ae','ö'=>'oe','ü'=>'ue','ß'=>'ss']);
    $s=trim((string)preg_replace('/[^a-z0-9]+/','-',$s),'-');
    return mb_substr($s,0,40);
}
/** Eingaben bereinigen: unbekannte Felder fallen weg, leere Felder bedeuten "Standard". */
function rrw_product_clean(array $in): array {
    $o=[];
    foreach(['name'=>60,'title'=>80,'heading'=>80,'access_name'=>60,'generator'=>60] as $k=>$max){
        $v=trim((string)preg_replace('/[\x00-\x1f<>"]/u','',(string)($in[$k]??'')));
        if($v!=='')$o[$k]=mb_substr($v,0,$max);
    }
    $slug=strtolower(trim((string)($in['slug']??'')));
    if($slug!==''&&preg_match('/^[a-z0-9][a-z0-9-]{1,39}$/',$slug))$o['slug']=$slug;
    $logo=trim((string)($in['logo']??''));
    if($logo!==''&&strlen($logo)<=300&&preg_match('#^(https://|/)[A-Za-z0-9._~%/?=&:+-]+$#',$logo)&&!str_contains($logo,'..'))$o['logo']=$logo;
    return $o;
}
/** Gespeicherte (bereinigte) Überschreibungen, ohne Standardwerte. */
function rrw_product_overrides(): array {
    $f=rrw_product_file();if(!is_file($f))return [];
    $j=json_decode((string)@file_get_contents($f),true);
    return is_array($j)?rrw_product_clean($j):[];
}
/** Wirksame Produktdaten (Standard + Überschreibungen). Pro Anfrage einmal gelesen; $reload=true liest neu. */
function rrw_product(bool $reload=false): array {
    static $c=null;if($c!==null&&!$reload)return $c;
    $d=rrw_product_defaults();$o=rrw_product_overrides();$p=$d;
    if(isset($o['name'])){
        // Eigener Name: abgeleitete Texte folgen ihm, solange sie nicht selbst gesetzt sind.
        $p['name']=$o['name'];$p['title']=$o['name'];$p['heading']=$o['name'].' Verwaltung';$p['access_name']=$o['name'];$p['generator']=$o['name'];
        $p['slug']=rrw_product_slugify($o['name'])?:$d['slug'];
    }
    foreach($o as $k=>$v)$p[$k]=$v;
    return $c=$p;
}
function rrw_product_name(): string { return rrw_product()['name']; }
function rrw_product_slug(): string { return rrw_product()['slug']; }
function rrw_product_logo(): string { return rrw_product()['logo']; }
function rrw_product_title(): string { return rrw_product()['title']; }
function rrw_product_heading(): string { return rrw_product()['heading']; }
function rrw_product_generator(): string { return rrw_product()['generator']; }
/** HTML-Escape für Ausgaben. */
function rrw_product_h(string $s): string { return htmlspecialchars($s,ENT_QUOTES,'UTF-8'); }
/** Öffentlich unkritische Angaben für die Oberfläche (keine Pfade, keine Zugangsdaten). */
function rrw_product_public(): array {
    $p=rrw_product();
    return ['name'=>$p['name'],'slug'=>$p['slug'],'logo'=>$p['logo'],'title'=>$p['title'],'heading'=>$p['heading'],'access_name'=>$p['access_name']];
}
/** Speichern (atomar). Leere Eingabe entfernt die Datei und stellt damit die Standardwerte wieder her. */
function rrw_product_save(array $in): array {
    $o=rrw_product_clean($in);$f=rrw_product_file();
    if(!$o){if(is_file($f)&&!@unlink($f))throw new RuntimeException('Produktdatei konnte nicht entfernt werden');}
    else rrw_write_atomic($f,json_encode($o,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    return rrw_product(true);
}

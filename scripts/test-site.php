<?php
// Projektseite (docs/): Bilder und Anker vorhanden, keine fremden Skripte oder Ressourcen, nur erlaubte externe Links, nicht im Installations-ZIP.
declare(strict_types=1);
$root=dirname(__DIR__);$n=0;$fail=0;
function t(string $name,bool $ok,string $info=''): void { global $n,$fail;$n++;if(!$ok){$fail++;echo "FAIL  $name".($info!==''?": $info":'')."\n";}else echo "  ok  $name\n"; }
$f=$root.'/docs/index.html';$h=is_file($f)?(string)file_get_contents($f):'';
t('docs/index.html vorhanden, Sprache und Titel gesetzt',$h!==''&&str_contains($h,'<html lang="de">')&&preg_match('~<title>[^<]{5,}</title>~',$h)===1);
t('Jekyll ist ausgeschaltet (.nojekyll)',is_file($root.'/docs/.nojekyll'));
preg_match_all('~(?:src|href)="(assets/[^"#?]+)"~',$h,$m);$missing=array_filter(array_unique($m[1]),fn($p)=>!is_file($root.'/docs/'.$p));
t('Alle lokalen Bilder und Icons existieren',$missing===[]&&count($m[1])>=4,implode(',',$missing));
preg_match_all('~id="([^"]+)"~',$h,$ids);preg_match_all('~href="#([^"]+)"~',$h,$an);$bad=array_diff(array_unique($an[1]),$ids[1]);
t('Alle Anker zeigen auf vorhandene Ziele',$bad===[],implode(',',$bad));
t('Kein Skript, kein iframe, keine fremden Stylesheets oder Schriften',!preg_match('~<script|<iframe|<link[^>]+rel="stylesheet"|@import|fonts\.googleapis|cdn\.~i',$h));
preg_match_all('~(?:src|href)="(https?://[^"]+)"~',$h,$ext);$hosts=array_unique(array_map(fn($u)=>(string)parse_url($u,PHP_URL_HOST),$ext[1]));sort($hosts);
t('Externe Adressen nur GitHub und die Demo',$hosts===['elvadopress.ricorewi-radio.de','github.com'],implode(',',$hosts));
$bytes=array_sum(array_map('filesize',glob($root.'/docs/assets/*')?:[]))+strlen($h);
t('Seite bleibt klein (unter 1 MB)',$bytes<1_000_000,(string)$bytes);
t('Kein Fremdmarken-Bezug und keine Radio-Behauptungen als Standard',!preg_match('/ricorewi(?!-radio\.de|original-collab)|anmacha|senderwelt|laut\.fm/i',$h));
$ga=(string)@file_get_contents($root.'/.gitattributes');
t('Projektseite ist vom Installations-ZIP ausgeschlossen (export-ignore)',str_contains($ga,'/docs export-ignore'));
echo "\n".($n-$fail)." von $n Prüfungen bestanden\n";exit($fail?1:0);

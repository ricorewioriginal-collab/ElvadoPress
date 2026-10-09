<?php
// Prüft die Übersetzungs-Engine (.mo lesen, __/_x/_n/_nx, Textdomänen). Aufruf: php scripts/test-wp-i18n.php [--live] (--live lädt zusätzlich echte Sprachpakete von WordPress.org)
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/elvado-wpi18n-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/wp-content/languages');mkdir($tmp.'/wp-content/languages/themes');mkdir($tmp.'/cms');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('ELVADO_WP_DATA',$tmp.'/cms/.wp');define('ELVADO_WP_CMS_DATA',$tmp.'/cms');
require __DIR__.'/../cms/wp/load.php';
$fail=0;$n=0;function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
/** Minimaler .mo-Schreiber (little endian). */
function mo(array $pairs, string $header="Plural-Forms: nplurals=2; plural=(n != 1);\n", bool $be=false): string {
    $pairs=[''=>"Content-Type: text/plain; charset=UTF-8\n".$header]+$pairs;ksort($pairs);$f=$be?'N':'V';
    $n=count($pairs);$o=28;$t=28+8*$n;$ids='';$trs='';$oi=[];$ti=[];$base=28+16*$n;
    foreach($pairs as $k=>$v){ $oi[]=[strlen($k),$base+strlen($ids)];$ids.=$k."\0"; }
    $base2=$base+strlen($ids);foreach($pairs as $k=>$v){ $ti[]=[strlen($v),$base2+strlen($trs)];$trs.=$v."\0"; }
    $out=pack("{$f}7",0x950412de,0,$n,$o,$t,0,0);if($be)$out=pack('N',0x950412de).substr($out,4);
    foreach($oi as $e)$out.=pack("{$f}2",$e[0],$e[1]);foreach($ti as $e)$out.=pack("{$f}2",$e[0],$e[1]);
    return $out.$ids.$trs;
}
file_put_contents(WP_CONTENT_DIR.'/languages/de_DE.mo',mo(['Search'=>'Suchen',"Read more"=>'Weiterlesen',"menu\x04Post"=>'Beitrag (Menü)',"%s comment\0%s comments"=>"%s Kommentar\0%s Kommentare","ctx\x04%d item\0%d items"=>"%d Element\0%d Elemente"]));
file_put_contents(WP_CONTENT_DIR.'/languages/themes/mytheme-de_DE.mo',mo(['Hello'=>'Hallo']));
t('Standardsprache de_DE',get_locale()==='de_DE');
t('__ übersetzt',__('Search')==='Suchen');
t('__ unbekannter Text bleibt',__('Unknown')==='Unknown');
t('esc_html__/_e',esc_html__('Read more')==='Weiterlesen');
t('_x mit Kontext',_x('Post','menu')==='Beitrag (Menü)'&&_x('Post','andere')==='Post');
t('_n Einzahl/Mehrzahl',sprintf(_n('%s comment','%s comments',1),1)==='1 Kommentar'&&sprintf(_n('%s comment','%s comments',3),3)==='3 Kommentare');
t('_nx',sprintf(_nx('%d item','%d items',2,'ctx'),2)==='2 Elemente');
t('_n ohne Übersetzung',_n('apple','apples',2)==='apples'&&_n('apple','apples',1)==='apple');
t('Textdomäne eines Themes aus languages/themes',__('Hello','mytheme')==='Hallo'&&__('Hello','andere')==='Hello');
file_put_contents($tmp.'/x-de_DE.mo',mo(['Bye'=>'Tschüss']));
t('load_textdomain',load_textdomain('x',$tmp.'/x-de_DE.mo')&&__('Bye','x')==='Tschüss'&&is_textdomain_loaded('x'));
file_put_contents($tmp.'/be.mo',mo(['Yes'=>'Ja'],"Plural-Forms: nplurals=2; plural=(n > 1);\n",true));
t('Big-Endian-.mo',load_textdomain('be',$tmp.'/be.mo')&&__('Yes','be')==='Ja');
file_put_contents($tmp.'/kaputt.mo','kein mo');
t('defekte .mo wird abgelehnt',!load_textdomain('k',$tmp.'/kaputt.mo')&&__('Search','k')==='Search');
update_option('WPLANG','en_US');t('Englisch: keine Übersetzung',__('Search')==='Search');update_option('WPLANG','de_DE');
if(in_array('--live',$argv,true)){
    require __DIR__.'/../cms/lib/theme-directory.php';require __DIR__.'/../cms/wp/installer.php';
    $GLOBALS['elvado_wp_mo']=[];$GLOBALS['elvado_wp_mo_tried']=[];unlink(WP_CONTENT_DIR.'/languages/de_DE.mo');
    t('Live: Core-Sprachpaket',elvado_wpi_fetch_translation('core','',ELVADO_WP_VERSION)&&is_file(WP_CONTENT_DIR.'/languages/de_DE.mo'));
    t('Live: Core-Text übersetzt',__('Search')==='Suche'||__('Search')==='Suchen',__('Search'));
    t('Live: Theme-Sprachpaket',elvado_wpi_fetch_translation('theme','twentytwentyone','2.4')&&is_file(WP_CONTENT_DIR.'/languages/themes/twentytwentyone-de_DE.mo'));
    t('Live: unbekanntes Plugin → false',!elvado_wpi_fetch_translation('plugin','gibts-garantiert-nicht-xyz','1.0'));
}
system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

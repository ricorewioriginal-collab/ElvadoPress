<?php
// Prüft das Nachführen der WordPress-Version: Angebotsauswahl, Namenslesen, Übernahme eines Pakets (mit Kompatibilitätsbericht),
// Zurücksetzen und das Lesen der gemeldeten Version beim Start. Aufruf: php scripts/test-wp-version.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/rrw-ver-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/cms');mkdir($tmp.'/core');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('RRW_WP_DATA',$tmp.'/cms/.wp');define('RRW_WP_CMS_DATA',$tmp.'/cms');define('RRW_WP_CORE_DIR',$tmp.'/core');define('RRW_WP_CORE_URL','http://example.test/core');
$_SERVER['HTTP_HOST']='example.test';file_put_contents($tmp.'/cms/site.json','{}');$GLOBALS['RRW_SITE']=[];
require __DIR__.'/_testdb.php';
require __DIR__.'/../cms/wp/load.php';require_once __DIR__.'/../cms/wp/wpversion.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
rrw_wp_boot(['theme'=>false]);
t('Grundstand ohne Versionsdatei',RRW_WP_VERSION===RRW_WP_BASE_VERSION&&RRW_WP_VERSION==='6.8.3');

// Angebotsauswahl
$dl=fn($v)=>'https://downloads.wordpress.org/release/wordpress-'.$v.'.zip';
$offers=[['version'=>'6.8.5','download'=>$dl('6.8.5')],['version'=>'6.9.1','download'=>$dl('6.9.1')],['version'=>'6.8.3','download'=>$dl('6.8.3')],['version'=>'7.0-RC1','download'=>$dl('7.0-RC1')],['version'=>'6.9.2','download'=>'https://evil.example/x.zip']];
t('minor: nur Fehlerkorrektur derselben Hauptversion',rrw_wpv_pick_offer($offers,'6.8.3','minor')['version']==='6.8.5');
t('all: neueste gültige Version',rrw_wpv_pick_offer($offers,'6.8.3','all')['version']==='6.9.1');
t('Unbekannte Richtlinie: nichts',rrw_wpv_pick_offer($offers,'6.8.3','egal')===null);
t('Keine neuere Version: nichts',rrw_wpv_pick_offer($offers,'6.9.1','all')===null);
t('Fremder Download-Host wird ignoriert',rrw_wpv_pick_offer([['version'=>'6.9.2','download'=>'https://evil.example/x.zip']],'6.8.3','all')===null);
t('Vorabversionen werden ignoriert',rrw_wpv_pick_offer([['version'=>'7.0-RC1','download'=>$dl('7.0-RC1')]],'6.8.3','all')===null);
t('Hauptversion',rrw_wpv_branch('6.8.3')==='6.8'&&rrw_wpv_valid('6.9')&&!rrw_wpv_valid('6.x')&&!rrw_wpv_valid('../1'));

// Funktionsnamen lesen
$names=rrw_wpv_function_names("<?php\nfunction a_one(){ function inner(){} }\nclass K{ function methode(){} }\nif(true){ function b_two(){} }\nfunction c_three(\$x){}\n");
t('Namen der obersten Ebene',in_array('a_one',$names,true)&&in_array('c_three',$names,true)&&!in_array('methode',$names,true)&&!in_array('inner',$names,true),json_encode($names));
t('Syntaxfehler: leere Liste',rrw_wpv_function_names('<?php function (( {')===[]);

// Fake-Paket der Version 9.9.9
function fake_zip(string $path, string $ver, int $db): void {
    $z=new ZipArchive();$z->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE);
    $z->addFromString('wordpress/wp-includes/version.php',"<?php\n\$wp_version = '$ver';\n\$wp_db_version = $db;\n");
    $z->addFromString('wordpress/wp-includes/assets/script-loader-packages.php',"<?php return array('element.js'=>array('dependencies'=>array(),'version'=>'a'));");
    $z->addFromString('wordpress/wp-includes/js/dist/element.min.js','/*element*/');
    $z->addFromString('wordpress/wp-includes/neu.php',"<?php\nfunction wp_gibt_es_nicht_im_cms_xyz(){}\nfunction get_option(){}\n");
    $z->addFromString('wordpress/wp-admin/includes/admin.php',"<?php\nfunction noch_so_eine_xyz(){}\n");
    $z->close();
}
$zip=$tmp.'/wp.zip';fake_zip($zip,'9.9.9',99999);
mkdir($tmp.'/core/js/dist',0775,true);file_put_contents($tmp.'/core/marker-alt.txt','alt');file_put_contents($tmp.'/core/version.txt',"6.8.3\n");

$bad=rrw_wpv_apply_zip($zip,'1.2.3');
t('Falsche erwartete Version wird abgelehnt',!$bad['ok']&&!is_file(rrw_wpv_file()),json_encode($bad));
$r=rrw_wpv_apply_zip($zip,'9.9.9');
t('Paket wird übernommen',$r['ok']&&$r['version']==='9.9.9',json_encode($r));
$j=json_decode((string)file_get_contents(rrw_wpv_file()),true);
t('Versionsdatei: Version, Datenbankstand, Vorgänger',$j['version']==='9.9.9'&&$j['db']===99999&&$j['previous']==='6.8.3',json_encode($j));
t('Kernressourcen sind die neuen',is_file($tmp.'/core/js/dist/element.min.js')&&trim((string)file_get_contents($tmp.'/core/version.txt'))==='9.9.9'&&!is_file($tmp.'/core/marker-alt.txt'));
t('Vorherige Fassung aufgehoben',is_file($tmp.'/core.prev/marker-alt.txt'));
t('Bericht: 3 Funktionen, 2 unbekannt',$r['report']['total']===3&&$r['report']['missing']===2&&in_array('wp_gibt_es_nicht_im_cms_xyz',$r['report']['sample'],true),json_encode($r['report']));
t('Bericht gespeichert',is_file(rrw_wpv_report_file()));

// Start liest die Versionsdatei
$out=shell_exec('php -r '.escapeshellarg('define("RRW_WP_DATA",'.var_export(RRW_WP_DATA,true).');$_SERVER["HTTP_HOST"]="x";require '.var_export(__DIR__.'/../cms/wp/load.php',true).';echo RRW_WP_VERSION," ",$GLOBALS["wp_db_version"];').' 2>&1');
t('Neuer Start meldet 9.9.9 / 99999',trim((string)$out)==='9.9.9 99999',(string)$out);

// Zweiter Wechsel behält nur eine Vorgängerfassung
$zip2=$tmp.'/wp2.zip';fake_zip($zip2,'9.9.10',100000);
$r2=rrw_wpv_apply_zip($zip2);
t('Zweiter Wechsel',$r2['ok']&&$r2['version']==='9.9.10');
t('Vorgänger ist jetzt 9.9.9',json_decode((string)file_get_contents(rrw_wpv_file()),true)['previous']==='9.9.9'&&!is_file($tmp.'/core.prev/marker-alt.txt'));

// Zurücksetzen
$rb=rrw_wpv_rollback();
t('Zurücksetzen gelingt',$rb['ok']&&$rb['version']==='9.9.9',json_encode($rb));
t('Versionsdatei zeigt 9.9.9 / 99999',json_decode((string)file_get_contents(rrw_wpv_file()),true)['version']==='9.9.9'&&json_decode((string)file_get_contents(rrw_wpv_file()),true)['db']===99999);
t('Zurücksetzen ohne Sicherung schlägt fehl',!rrw_wpv_rollback()['ok']);

// Ungültige Pakete
$z=new ZipArchive();$z->open($tmp.'/leer.zip',ZipArchive::CREATE);$z->addFromString('x.txt','y');$z->close();
t('Paket ohne Versionsdatei',!rrw_wpv_apply_zip($tmp.'/leer.zip')['ok']);
$z=new ZipArchive();$z->open($tmp.'/boese.zip',ZipArchive::CREATE);$z->addFromString('wordpress/wp-includes/version.php',"<?php \$wp_version = '../../x';\$wp_db_version=1;");$z->close();
t('Ungültige Versionsangabe',!rrw_wpv_apply_zip($tmp.'/boese.zip')['ok']);
t('Datei ohne Datenbankstand',!rrw_wpv_apply_zip((function() use($tmp){ $z=new ZipArchive();$z->open($tmp.'/nodb.zip',ZipArchive::CREATE);$z->addFromString('wordpress/wp-includes/version.php',"<?php \$wp_version = '9.1.1';");$z->close();return $tmp.'/nodb.zip'; })())['ok']);

// Richtlinie im Auto-Update speichern
require_once __DIR__.'/../cms/wp/autoupdate.php';
$s=rrw_wpau_set(['wp_core'=>'minor']);t('Richtlinie „minor“ wird gespeichert',$s['wp_core']==='minor'&&rrw_wpau_enabled($s));
$s=rrw_wpau_set(['wp_core'=>'quatsch']);t('Ungültige Richtlinie wird „off“',$s['wp_core']==='off'&&!rrw_wpau_enabled($s));

system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

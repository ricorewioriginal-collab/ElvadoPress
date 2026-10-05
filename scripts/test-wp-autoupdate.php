<?php
// Prüft die automatischen WordPress-Updates: Einstellungen, Fälligkeit, Wiederherstellung, Syntaxprüfung, Lauf ohne Updates. Aufruf: php scripts/test-wp-autoupdate.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/rrw-au-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/wp-content/plugins');mkdir($tmp.'/cms');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('RRW_WP_DATA',$tmp.'/cms/.wp');define('RRW_WP_CMS_DATA',$tmp.'/cms');
$_SERVER['HTTP_HOST']='example.test';file_put_contents($tmp.'/cms/site.json','{}');$GLOBALS['RRW_SITE']=[];
require __DIR__.'/_testdb.php';
require __DIR__.'/../cms/wp/load.php';require_once __DIR__.'/../cms/wp/autoupdate.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
rrw_wp_boot(['theme'=>false]);
$d=$tmp.'/cms/data';

// Einstellungen
$s=rrw_wpau_get();
t('Standard: alles aus',$s['plugins']==='off'&&$s['themes']==='off'&&!$s['translations']&&!rrw_wpau_enabled($s)&&!rrw_wpau_due($s));
$s=rrw_wpau_set(['plugins'=>'selected','themes'=>'bogus','interval_h'=>9999,'translations'=>1,'selected'=>['plugin'=>['gut-1','../böse','x'],'theme'=>['twentytwentyfive']]]);
t('Ungültiger Modus wird „off“',$s['themes']==='off');
t('Intervall begrenzt',$s['interval_h']===168);
t('Nur gültige Slugs',$s['selected']['plugin']===['gut-1']&&$s['selected']['theme']===['twentytwentyfive'],json_encode($s['selected']));
t('Einstellungen bleiben erhalten',rrw_wpau_get()['plugins']==='selected');
t('Auswahl: ausgewähltes Plugin',rrw_wpau_wants($s,'plugin','gut-1')&&!rrw_wpau_wants($s,'plugin','anderes'));
t('Modus „Aus“ wählt nichts',!rrw_wpau_wants($s,'theme','twentytwentyfive'));
$s=rrw_wpau_set(['plugins'=>'all']);
t('Modus „Alle“ wählt jedes',rrw_wpau_wants($s,'plugin','irgendeins'));
t('Fällig bei aktiviertem Modus ohne Lauf',rrw_wpau_due($s));
t('set() lässt das Protokoll unberührt',rrw_wpau_get()['log']===[]);

// Lauf ohne installierte Plugins/Themes (keine Netzwerkzugriffe nötig)
$r=rrw_wpau_run($d);
t('Lauf ohne Updates',$r['ran']&&$r['updated']===0&&$r['failed']===0);
t('Letzter Lauf gesetzt, danach nicht mehr fällig',rrw_wpau_get()['last_run']>0&&!rrw_wpau_due());
$r2=rrw_wpau_run($d);
t('Nicht fällig: kein Lauf',!$r2['ran']);
$lock=fopen(rtrim(RRW_WP_DATA,'/').'/autoupdate.lock','c');flock($lock,LOCK_EX);
$r3=rrw_wpau_run($d,true);
t('Parallel laufender Lauf blockiert',!$r3['ran']);
flock($lock,LOCK_UN);fclose($lock);

// Syntaxprüfung
$pd=WP_PLUGIN_DIR.'/demo';mkdir($pd);file_put_contents($pd.'/demo.php',"<?php\n/* Plugin Name: Demo */\nfunction demo_ok(){ return 1; }\n");
t('Syntaxprüfung: gültig',rrw_wpau_syntax_check($pd)==='');
file_put_contents($pd.'/kaputt.php',"<?php\nfunction (( {");
t('Syntaxprüfung: Fehler erkannt',str_contains(rrw_wpau_syntax_check($pd),'kaputt.php'));
t('Syntaxprüfung: fehlender Ordner',rrw_wpau_syntax_check($pd.'/gibt-es-nicht')!=='');

// Zurücksetzen auf die gesicherte Fassung
$rb=rrw_wpau_rollback_dir('plugin','demo');mkdir($rb,0775,true);file_put_contents($rb.'/demo.php',"<?php\n/* Plugin Name: Demo\nVersion: 1.0 */\n");
$r=rrw_wpau_rollback('plugin','demo');
t('Zurücksetzen gelingt',$r['ok'],json_encode($r));
t('Alte Fassung ist wieder live',is_file($pd.'/demo.php')&&str_contains((string)file_get_contents($pd.'/demo.php'),'Version: 1.0')&&!is_file($pd.'/kaputt.php'));
t('Sicherung verbraucht',!is_dir($rb));
t('Zurücksetzen ohne Sicherung',!rrw_wpau_rollback('plugin','demo')['ok']);
t('Zurücksetzen: ungültiger Slug',!rrw_wpau_rollback('plugin','../x')['ok']&&!rrw_wpau_rollback('foo','demo')['ok']);

// Aktualisieren eines nicht installierten Plugins
$r=rrw_wpau_update_one('plugin','nicht-da');
t('Nicht installiert: Fehler ohne Netzwerkzugriff',!$r['ok']&&$r['msg']==='nicht installiert');

// Datei bleibt gültiges JSON mit begrenztem Protokoll
$s=rrw_wpau_get();for($i=0;$i<70;$i++)$s['log'][]=['t'=>$i,'type'=>'plugin','slug'=>'a','ok'=>true];rrw_wpau_save($s);
t('Protokoll auf 50 Einträge begrenzt',count(rrw_wpau_get()['log'])===50);

system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

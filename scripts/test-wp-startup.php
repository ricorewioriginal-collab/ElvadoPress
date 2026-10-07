<?php
// Prüft den Start der WordPress-Schicht gegen den echten Ablauf von WordPress 7.1.3 (mit demselben Test-Plugin dort gemessen):
//   mu_plugin_loaded (voller Pfad) → muplugins_loaded → plugin_loaded je Plugin (voller Pfad) → plugins_loaded → setup_theme → after_setup_theme → init (darin widgets_init, Priorität 1) → wp_loaded
// Dazu: Must-Use-Plugins (wp-content/mu-plugins) – Reihenfolge, Fehler in einer Datei, Absturz (fatal) wird bis zur Änderung der Datei übersprungen. Aufruf: php scripts/test-wp-startup.php
declare(strict_types=1);
$sc=(string)($argv[1]??'');$tmp=(string)($argv[2]??'');
function rmrf(string $d): void { if(!is_dir($d))return; foreach(scandir($d) as $f){if($f==='.'||$f==='..')continue;$p=$d.'/'.$f;is_dir($p)&&!is_link($p)?rmrf($p):@unlink($p);} @rmdir($d); }
$fail=0;$n=0;function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }

function boot_env(string $tmp): void {
    define('WP_CONTENT_DIR',$tmp.'/wp-content');define('RRW_WP_DATA',$tmp.'/cms/.wp');define('RRW_WP_CMS_DATA',$tmp.'/cms');$_SERVER['HTTP_HOST']='example.test';$_SERVER['REMOTE_ADDR']='203.0.113.5';
    $GLOBALS['RRW_SITE']=json_decode((string)file_get_contents($tmp.'/cms/site.json'),true);
    require __DIR__.'/_testdb.php';
    require __DIR__.'/../cms/wp/load.php';
}
function setup_env(string $tmp,array $mu,array $plugins): void {
    foreach(['wp-content/plugins','wp-content/mu-plugins','cms'] as $d)@mkdir($tmp.'/'.$d,0755,true);
    file_put_contents($tmp.'/cms/news.json','[]');file_put_contents($tmp.'/cms/site.json',json_encode(['portal'=>['site_name'=>'Test']]));
    foreach($mu as $f=>$code)file_put_contents($tmp.'/wp-content/mu-plugins/'.$f,$code);
    foreach($plugins as $f=>$code){ @mkdir(dirname($tmp.'/wp-content/plugins/'.$f),0755,true);file_put_contents($tmp.'/wp-content/plugins/'.$f,$code); }
}
$rec=<<<'PHP'
foreach(['mu_plugin_loaded','muplugins_loaded','plugin_loaded','plugins_loaded','setup_theme','after_setup_theme','init','widgets_init','wp_loaded'] as $t)
  add_action($t,function() use($t){ $a=func_get_args();$GLOBALS['rrw_order'][]=$t.(isset($a[0])&&is_string($a[0])?' '.basename(dirname($a[0])).'/'.basename($a[0]):''); },5);
PHP;

if($sc==='fatal1'||$sc==='fatal2'){   // Absturz eines Must-Use-Plugins: erster Lauf stürzt ab, zweiter überspringt die Datei
    boot_env($tmp);update_option('active_plugins',[]);
    register_shutdown_function(function(){ echo "\nENDE\n"; });
    $errs=rrw_wp_boot(['theme'=>false]);
    echo $sc==='fatal2'?('GELADEN:'.(function_exists('mu_fatal_geladen')?'ja':'nein').' FEHLER:'.json_encode(array_keys($errs))."\n"):"OHNE ABSTURZ\n";exit(0);
}
if($sc==='lauf'){
    boot_env($tmp);update_option('active_plugins',['b-plugin/b-plugin.php','a-plugin/a-plugin.php']);
    $GLOBALS['rrw_order']=[];
    $errs=rrw_wp_boot(['theme'=>true]);
    echo json_encode(['order'=>$GLOBALS['rrw_order'],'errs'=>$errs,'early'=>$GLOBALS['rrw_early']??[]]),"\n";exit(0);
}
if($sc!=='')exit(0);

$tmp=sys_get_temp_dir().'/rrw-wps-'.bin2hex(random_bytes(4));mkdir($tmp);
/* 1) Reihenfolge wie im echten WordPress; mu-Dateien alphabetisch, eine wirft einen Fehler, die nächste lädt trotzdem */
setup_env($tmp,[
    'a-erste.php'=>"<?php\n$rec\n\$GLOBALS['rrw_early'][]='mu-a';\n",
    'b-zweite.php'=>"<?php\n\$GLOBALS['rrw_early'][]='mu-b';\n",
    'c-kaputt.php'=>"<?php\nthrow new RuntimeException('mu kaputt');\n",
    'd-letzte.php'=>"<?php\n\$GLOBALS['rrw_early'][]='mu-d';\n",
    'e-kein-php.txt'=>"nichts",
],[
    'a-plugin/a-plugin.php'=>"<?php\n/* Plugin Name: A */\n",
    'b-plugin/b-plugin.php'=>"<?php\n/* Plugin Name: B */\n",
]);
$out=[];exec('php '.escapeshellarg(__FILE__).' lauf '.escapeshellarg($tmp).' 2>&1',$out);
$r=json_decode((string)end($out),true);
t('Start läuft durch',is_array($r),implode(' | ',array_slice($out,-2)));
$ord=is_array($r)?$r['order']:[];
// So hat WordPress 7.1.3 mit demselben Test-Plugin gemessen (Rekorder mit Priorität 5; widgets_init läuft in init mit Priorität 1, daher vor dem Rekorder von init)
// (Hooks ohne Argumente zeigen im Rekorder „ /“ – in WordPress genauso)
$expect=['mu_plugin_loaded mu-plugins/a-erste.php','mu_plugin_loaded mu-plugins/b-zweite.php','mu_plugin_loaded mu-plugins/c-kaputt.php','mu_plugin_loaded mu-plugins/d-letzte.php','muplugins_loaded /',
    'plugin_loaded b-plugin/b-plugin.php','plugin_loaded a-plugin/a-plugin.php','plugins_loaded /','setup_theme /','after_setup_theme /','widgets_init /','init /','wp_loaded /'];
t('Reihenfolge der Start-Hooks und Pfade wie im echten WordPress',$ord===$expect,json_encode($ord));
t('Must-Use-Plugins laden alphabetisch, vor den Plugins',is_array($r)&&$r['early']===['mu-a','mu-b','mu-d'],json_encode($r['early']??null));
t('Fehler in einer Must-Use-Datei wird gemeldet, die nächste lädt trotzdem',is_array($r)&&isset($r['errs']['mu-plugins/c-kaputt.php'])&&in_array('mu-d',$r['early'],true));
t('Nicht-PHP-Dateien im mu-plugins-Ordner werden ignoriert',is_array($r)&&!isset($r['errs']['mu-plugins/e-kein-php.txt']));
rmrf($tmp);

/* 2) Absturz (fatal) eines Must-Use-Plugins: einmal abgefangen, danach bis zur Änderung der Datei übersprungen */
$tmp=sys_get_temp_dir().'/rrw-wps-'.bin2hex(random_bytes(4));mkdir($tmp);
setup_env($tmp,['x-fatal.php'=>"<?php\nini_set('memory_limit','40M');\$x=str_repeat('x',300000000);\n",'y-ok.php'=>"<?php\nfunction mu_fatal_geladen(){}\n"],[]);
$o1=[];exec('php '.escapeshellarg(__FILE__).' fatal1 '.escapeshellarg($tmp).' 2>&1',$o1);
$o2=[];exec('php '.escapeshellarg(__FILE__).' fatal2 '.escapeshellarg($tmp).' 2>&1',$o2);
$s1=implode("\n",$o1);$s2=implode("\n",$o2);
t('Erster Lauf: die Datei bricht PHP ab (echter Fatal, der Absturzschutz greift)',!str_contains($s1,'OHNE ABSTURZ')&&str_contains($s1,'ENDE'),$s1);
t('Zweiter Lauf: die abgestürzte Datei wird übersprungen, die Website startet und die übrigen Dateien laden',str_contains($s2,'GELADEN:ja')&&str_contains($s2,'x-fatal.php'),$s2);
sleep(1);touch($tmp.'/wp-content/mu-plugins/x-fatal.php',time()+5);   // Datei geändert → neuer Versuch (bricht wieder ab)
$o3=[];exec('php '.escapeshellarg(__FILE__).' fatal2 '.escapeshellarg($tmp).' 2>&1',$o3);
t('Nach einer Änderung der Datei wird sie erneut versucht',!str_contains(implode("\n",$o3),'GELADEN:'),implode("\n",$o3));
rmrf($tmp);
echo $fail?"$fail von $n Prüfungen fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

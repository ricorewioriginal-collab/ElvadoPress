<?php
// Macht aus einem Paket-Ordner (Kopie dieses Repositories) eine öffentliche Testinstanz: Demo-Benutzer, Rücksetzen alle N Minuten, gesperrte Aktionen.
// Aufruf: php scripts/make-demo.php <Paketordner> [--minutes=10] [--extras]   (--extras: bekannte WordPress-Themes/-Plugins aus wordpress.org vorinstallieren, braucht Netz)
declare(strict_types=1);
$dir=(string)($argv[1]??'');$min=10;$extras=in_array('--extras',$argv,true);
foreach(array_slice($argv,2) as $a)if(str_starts_with($a,'--minutes='))$min=(int)substr($a,10);
if($dir===''||!is_file($dir.'/cms/lib/demo.php')){ fwrite(STDERR,"Aufruf: php scripts/make-demo.php <Paketordner> [--minutes=10]\n");exit(2); }
require_once $dir.'/cms/lib/demo.php';
rrw_demo_make($dir,$min);
if($extras){ $r=rrw_demo_extras($dir);echo "Vorinstalliert: ".($r['ok']?implode(', ',$r['ok']):'nichts')."\n";foreach($r['failed'] as $n=>$why)fwrite(STDERR,"Warnung: $n nicht installiert ($why)\n"); }
echo "Demo vorbereitet: $dir (Zeitfenster $min Minuten)\n";

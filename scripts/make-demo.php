<?php
// Macht aus einem Paket-Ordner (Kopie dieses Repositories) eine öffentliche Testinstanz: Demo-Benutzer, Rücksetzen alle N Minuten, gesperrte Aktionen.
// Aufruf: php scripts/make-demo.php <Paketordner> [--minutes=10]
declare(strict_types=1);
$dir=(string)($argv[1]??'');$min=10;
foreach(array_slice($argv,2) as $a)if(str_starts_with($a,'--minutes='))$min=(int)substr($a,10);
if($dir===''||!is_file($dir.'/cms/lib/demo.php')){ fwrite(STDERR,"Aufruf: php scripts/make-demo.php <Paketordner> [--minutes=10]\n");exit(2); }
require_once $dir.'/cms/lib/demo.php';
rrw_demo_make($dir,$min);
echo "Demo vorbereitet: $dir (Zeitfenster $min Minuten)\n";

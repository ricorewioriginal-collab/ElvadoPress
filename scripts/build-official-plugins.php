<?php
// Erzeugt cms/official-plugins/catalog.json: Prüfsummen der offiziellen Plugins (Vertrauensanker des Plugin-Systems) und die Liste geplanter Plugins.
// Aufruf: php scripts/build-official-plugins.php [--check]   (--check: nur prüfen, ob catalog.json aktuell ist; Exit 1 sonst)
// Nach jeder Änderung an einem Ordner in cms/official-plugins/ neu ausführen (der Test test-nplugins.php schlägt sonst fehl).
declare(strict_types=1);
require_once __DIR__.'/../cms/src/autoload.php';
use Elvado\Plugin\{Fs,Manifest};

$lib=__DIR__.'/../cms/official-plugins';$check=in_array('--check',$argv,true);
$planned=json_decode((string)@file_get_contents($lib.'/planned.json'),true);$planned=is_array($planned)?$planned:[];
$entries=[];$errors=[];
foreach(glob($lib.'/*',GLOB_ONLYDIR)?:[] as $dir){
    $id=basename($dir);if(!is_file($dir.'/plugin.json'))continue;
    [$m,$errs]=Manifest::normalize(json_decode((string)file_get_contents($dir.'/plugin.json'),true),$id);
    if($m===null){$errors[]="$id: ".implode(' ',$errs);continue;}
    $raw=json_decode((string)file_get_contents($dir.'/plugin.json'),true);
    $lint=Fs::lint($dir);if($lint)$errors[]="$id: ".$lint[0];
    $entries[]=['id'=>$id,'name'=>$m['name'],'description'=>$m['description'],'category'=>$m['category'],'icon'=>$m['icon'],'status'=>'available','recommended'=>!empty($raw['recommended']),'version'=>$m['version'],'hash'=>Fs::treeHash($dir)];
}
foreach($planned as $p){
    if(!is_array($p)||!preg_match(Manifest::ID_RE,(string)($p['id']??'')))continue;
    foreach($entries as $e)if($e['id']===$p['id']){$errors[]="{$p['id']} steht in planned.json, existiert aber schon.";continue 2;}
    $entries[]=['id'=>$p['id'],'name'=>(string)$p['name'],'description'=>(string)($p['description']??''),'category'=>(string)($p['category']??''),'icon'=>preg_match('/^fa-[a-z0-9-]+$/',(string)($p['icon']??''))?$p['icon']:'fa-plug','status'=>'planned','recommended'=>false,'version'=>'','hash'=>''];
}
if($errors){fwrite(STDERR,implode("\n",$errors)."\n");exit(2);}
usort($entries,fn($a,$b)=>[$a['status']!=='available',$a['name']]<=>[$b['status']!=='available',$b['name']]);
$json=json_encode(['schema'=>1,'note'=>'Erzeugt von scripts/build-official-plugins.php – nicht von Hand ändern.','plugins'=>$entries],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
$file=$lib.'/catalog.json';
if($check){ if(!is_file($file)||file_get_contents($file)!==$json){fwrite(STDERR,"cms/official-plugins/catalog.json ist nicht aktuell – php scripts/build-official-plugins.php ausführen.\n");exit(1);}echo "catalog.json ist aktuell (".count($entries)." Einträge)\n";exit(0); }
file_put_contents($file,$json);echo "catalog.json geschrieben (".count($entries)." Einträge)\n";

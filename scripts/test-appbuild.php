<?php
// Prüft die Marken-Angaben des Build-Assistenten (cms/lib/appbuild.php), vor allem das Branding für alle App-Typen: php scripts/test-appbuild.php
declare(strict_types=1);
require_once __DIR__.'/../cms/lib/publish.php';
require_once __DIR__.'/../cms/lib/apps.php';
require_once __DIR__.'/../cms/lib/appbuild.php';
$fail=0;$n=0;
function t(string $name,callable $fn){global $fail,$n;$n++;try{$fn();echo "  ok  $name\n";}catch(Throwable $e){$fail++;echo "FAIL  $name: ".$e->getMessage()."\n";}}
function eq($a,$b,string $m=''){if($a!==$b)throw new RuntimeException(($m?$m.': ':'').'erwartet '.json_encode($b).', war '.json_encode($a));}
$GLOBALS['__base']=$base=['id'=>'meinapp','applicationId'=>'de.beispiel.app','appName'=>'Meine App','site'=>'https://www.beispiel.de','type'=>'web','platforms'=>['android']];
function clean(array $in){ [$b,$e]=rrw_ab_clean_brand($in);return [$b,$e]; }

t('Ohne Branding-Angaben bleibt der Eintrag unverändert (keine neuen Schlüssel)',function() use($base){
    [$b]=clean($base);foreach(['splash','iconBg','screenshots','shortDescription','fullDescription'] as $k)if(array_key_exists($k,$b))throw new RuntimeException("Schlüssel $k ohne Angabe");
    eq(array_keys($b),['id','applicationId','appName','site','launchUrl','filePrefix','directory','icon','type','platforms','themeColor']);
});
t('Branding gilt für jeden App-Typ (Radio und Website)',function() use($base){
    foreach(['radio','web'] as $ty){
        [$b,$e]=clean(['type'=>$ty,'splash'=>'/cms/media/start.png','iconBg'=>'#AABBCC','screenshots'=>['/cms/media/a.png','/cms/media/b.png','/cms/media/a.png'],'shortDescription'=>'<b>Kurz</b>','fullDescription'=>'Lang']+$base);
        eq($e,'',$ty);eq($b['splash'],'/cms/media/start.png');eq($b['iconBg'],'#aabbcc');eq($b['screenshots'],['/cms/media/a.png','/cms/media/b.png'],'doppelte entfallen');
        eq($b['shortDescription'],'Kurz','HTML entfernt');eq($b['fullDescription'],'Lang');
    }
});
t('Ungültige Branding-Angaben werden abgelehnt',function() use($base){
    foreach([['splash'=>'/cms/media/../x.png'],['splash'=>'https://fremd.example/x.png'],['iconBg'=>'rot'],['screenshots'=>['/etc/passwd']],['screenshots'=>array_map(fn($i)=>"/cms/media/s$i.png",range(1,9))]] as $bad){
        [$b,$e]=clean($bad+$base);if($b!==null||$e==='')throw new RuntimeException('nicht abgelehnt: '.json_encode($bad));
    }
});
t('Texte werden begrenzt',function() use($base){
    [$b]=clean(['shortDescription'=>str_repeat('k',200),'fullDescription'=>str_repeat('l',9000)]+$base);eq(mb_strlen($b['shortDescription']),80);eq(mb_strlen($b['fullDescription']),4000);
});
t('Radioverzeichnis in der App nur mit RicoReWi-Paket',function() use($base){
    [$b]=clean(['type'=>'radio','directory'=>true]+$base);eq($b['directory'],rrw_pack_available());
    [$b]=clean(['type'=>'web','directory'=>true]+$base);eq($b['directory'],false);
});
t('Store-Texte als Markdown',function(){
    $md=rrw_ab_listing_md(['appName'=>'Meine App','shortDescription'=>'Kurz','fullDescription'=>'Lang']);
    foreach(['# Meine App','## Kurzbeschreibung','Kurz','## Beschreibung','Lang'] as $x)if(!str_contains($md,$x))throw new RuntimeException("fehlt: $x");
});
t('Bilder: nur aus der Medienbibliothek, verkleinert, Seitenverhältnis bleibt (nicht quadratisch)',function(){
    if(!function_exists('imagecreatetruecolor'))return;
    $root=sys_get_temp_dir().'/abimg-'.bin2hex(random_bytes(4));mkdir($root.'/cms/media',0777,true);
    $im=imagecreatetruecolor(2000,1000);ob_start();imagepng($im);file_put_contents($root.'/cms/media/wide.png',ob_get_clean());
    [$png,$e]=rrw_ab_image_png($root,'/cms/media/wide.png',1000,false,200,'Das Bild');eq($e,'');
    $g=getimagesizefromstring((string)$png);eq([$g[0],$g[1]],[1000,500]);
    [$sq]=rrw_ab_image_png($root,'/cms/media/wide.png',512,true,96);$g=getimagesizefromstring((string)$sq);eq([$g[0],$g[1]],[512,512],'Icon quadratisch');
    [$x,$e]=rrw_ab_image_png($root,'/cms/media/../../etc/passwd',512,false,10);eq($x,null);
    [$x,$e]=rrw_ab_image_png($root,'/cms/media/fehlt.png');eq($x,null);
    [$x,$e]=rrw_ab_image_png($root,'/cms/media/wide.png',512,false,5000,'Das Bild');eq($x,null);if(!str_contains($e,'zu klein'))throw new RuntimeException($e);
    exec('rm -rf '.escapeshellarg($root));
});
t('Eigene Sender: laut.fm-Kennung oder https-Stream, selbst eingetragen',function(){
    $c=rrw_apps_custom_stations([['title'=>'Mein Sender','stream'=>'meinsender'],['title'=>'Zweiter','stream'=>'https://laut.fm/zweiter'],['title'=>'Dritter','stream'=>'https://stream.example.org/live'],['title'=>'Vierter','stream'=>'http://stream.example.org/live']]);
    eq(array_column($c,'id'),['mein-sender','zweiter','dritter']);
    eq($c[0]['stream'],'https://meinsender.stream.laut.fm/meinsender');eq($c[0]['laut'],'meinsender');eq($c[1]['stream'],'https://zweiter.stream.laut.fm/zweiter');eq($c[2]['stream'],'https://stream.example.org/live');eq(isset($c[2]['laut']),false);
});
t('Build: Branding-Dateien landen im Repository (Startbild, Screenshots, Store-Texte, Icon-Hintergrund) – nur wenn angegeben',function(){
    if(!function_exists('imagecreatetruecolor'))return;
    $root=sys_get_temp_dir().'/abst-'.bin2hex(random_bytes(4));mkdir($root.'/cms/media',0777,true);mkdir($root.'/data');
    $im=imagecreatetruecolor(800,600);ob_start();imagepng($im);$png=ob_get_clean();foreach(['icon','splash','s1','s2'] as $f)file_put_contents($root."/cms/media/$f.png",$png);
    $run=function(array $brand) use($root){
        $put=[];$brands=[];
        $GLOBALS['rrw_ab_http']=function($m,$path,$tok,$json) use(&$put,&$brands){
            $ok=fn($b=[])=>['code'=>200,'body'=>json_encode($b),'headers'=>[]];
            if($m==='GET'&&str_contains($path,'/git/ref/heads/'))return $ok();
            if($m==='GET'&&str_contains($path,'/contents/android/brands.json'))return $ok(['content'=>base64_encode('[]'),'sha'=>'x']);
            if($m==='GET')return ['code'=>404,'body'=>'{}','headers'=>[]];
            if($m==='PUT'){ $put[]=preg_replace('~^/repos/[^/]+/[^/]+/contents/~','',$path);if(str_ends_with($path,'android/brands.json'))$brands=json_decode(base64_decode($json['content']),true);return ['code'=>201,'body'=>'{}','headers'=>[]]; }
            if($m==='POST')return ['code'=>204,'body'=>'','headers'=>[]];
            return ['code'=>500,'body'=>'{}','headers'=>[]];
        };
        file_put_contents($root.'/data/.x','');rrw_ab_save($root.'/data',['repo'=>'me/app-template','branch'=>'app-builder','token'=>'tok','brands'=>[$brand]]);
        $r=rrw_ab_start($root.'/data',$root,$brand['id'],'android');unset($GLOBALS['rrw_ab_http']);
        return [$r,$put,$brands];
    };
    [$b]=rrw_ab_clean_brand(['icon'=>'/cms/media/icon.png','splash'=>'/cms/media/splash.png','iconBg'=>'#112233','screenshots'=>['/cms/media/s1.png','/cms/media/s2.png'],'shortDescription'=>'Kurz','fullDescription'=>'Lang']+$GLOBALS['__base']);
    [$r,$put,$entry]=$run($b);eq($r['ok'],true,$r['message']);
    foreach(['android/brands.json','brands/meinapp/app_logo.png','brands/meinapp/app_splash.png','brands/meinapp/store/screenshot-1.png','brands/meinapp/store/screenshot-2.png','brands/meinapp/store/listing-de.md'] as $f)if(!in_array($f,$put,true))throw new RuntimeException("nicht geschrieben: $f (".implode(', ',$put).')');
    eq($entry[0]['iconBg'],'#112233');eq($entry[0]['splash'],true);
    [$b2]=rrw_ab_clean_brand(['icon'=>'/cms/media/icon.png']+$GLOBALS['__base']);
    [$r,$put,$entry]=$run($b2);eq($r['ok'],true,$r['message']);
    eq($put,['android/brands.json','brands/meinapp/app_logo.png'],'ohne Branding nur wie bisher');
    foreach(['iconBg','splash'] as $k)if(array_key_exists($k,$entry[0]))throw new RuntimeException("$k ohne Angabe im Eintrag");
    exec('rm -rf '.escapeshellarg($root));
});
echo "\n".($n-$fail)." von $n Prüfungen bestanden\n";
exit($fail?1:0);

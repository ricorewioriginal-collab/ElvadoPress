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
    [$b]=clean($base);foreach(['splash','headerLogo','iconBg','screenshots','shortDescription','fullDescription'] as $k)if(array_key_exists($k,$b))throw new RuntimeException("Schlüssel $k ohne Angabe");
    eq(array_keys($b),['id','applicationId','appName','site','launchUrl','filePrefix','icon','type','platforms','themeColor']);
});
t('Branding gilt für jeden App-Typ (Website und Baukasten)',function() use($base){
    foreach(['content','web'] as $ty){
        [$b,$e]=clean(['type'=>$ty,'splash'=>'/cms/media/start.png','headerLogo'=>'/cms/media/lockup.png','iconBg'=>'#AABBCC','screenshots'=>['/cms/media/a.png','/cms/media/b.png','/cms/media/a.png'],'shortDescription'=>'<b>Kurz</b>','fullDescription'=>'Lang']+$base);
        eq($e,'',$ty);eq($b['splash'],'/cms/media/start.png');eq($b['headerLogo'],'/cms/media/lockup.png');eq($b['iconBg'],'#aabbcc');eq($b['screenshots'],['/cms/media/a.png','/cms/media/b.png'],'doppelte entfallen');
        eq($b['shortDescription'],'Kurz','HTML entfernt');eq($b['fullDescription'],'Lang');
    }
});
t('Ungültige Branding-Angaben werden abgelehnt',function() use($base){
    foreach([['splash'=>'/cms/media/../x.png'],['splash'=>'https://fremd.example/x.png'],['headerLogo'=>'https://fremd.example/l.png'],['iconBg'=>'rot'],['screenshots'=>['/etc/passwd']],['screenshots'=>array_map(fn($i)=>"/cms/media/s$i.png",range(1,9))]] as $bad){
        [$b,$e]=clean($bad+$base);if($b!==null||$e==='')throw new RuntimeException('nicht abgelehnt: '.json_encode($bad));
    }
});
t('Marken-IDs, die Gradle als Flavor ablehnt (test…, androidtest…, Build-Typen), werden abgelehnt',function() use($base){
    foreach(['testradio','tester','androidtestx','debug','release','developer','main'] as $id){
        [$b,$e]=clean(['id'=>$id]+$base);if($b!==null||$e==='')throw new RuntimeException('nicht abgelehnt: '.$id);
    }
    [$b,$e]=clean(['id'=>'meinradio']+$base);eq($e,'');eq($b['id'],'meinradio');
});
t('Texte werden begrenzt',function() use($base){
    [$b]=clean(['shortDescription'=>str_repeat('k',200),'fullDescription'=>str_repeat('l',9000)]+$base);eq(mb_strlen($b['shortDescription']),80);eq(mb_strlen($b['fullDescription']),4000);
});
t('Nur Website- und Baukasten-App: der frühere Typ „radio“ ist unbekannt',function() use($base){
    [$b,$e]=clean(['type'=>'radio']+$base);eq($b,null);eq($e,'Unbekannter App-Typ.');
    [$b]=clean(['type'=>'content']+$base);eq($b['type'],'content');
    eq(array_keys(RRW_AB_TYPES),['web','content']);
});
t('Icon mit Hintergrundfarbe: deckende Fläche statt Transparenz, Icon bleibt sichtbar',function(){
    if(!function_exists('imagecreatetruecolor'))return;
    $root=sys_get_temp_dir().'/abbg-'.bin2hex(random_bytes(4));mkdir($root.'/cms/media',0777,true);
    $im=imagecreatetruecolor(200,200);imagealphablending($im,false);imagesavealpha($im,true);imagefill($im,0,0,imagecolorallocatealpha($im,0,0,0,127));
    imagefilledrectangle($im,80,80,120,120,imagecolorallocate($im,255,0,0));ob_start();imagepng($im);file_put_contents($root.'/cms/media/i.png',ob_get_clean());
    [$flat]=rrw_ab_icon_png($root,'/cms/media/i.png','#102030');[$clear]=rrw_ab_icon_png($root,'/cms/media/i.png');
    $f=imagecreatefromstring((string)$flat);$c=imagecreatefromstring((string)$clear);
    $px=fn($g,$x,$y)=>imagecolorat($g,$x,$y);$rgba=fn($v)=>[($v>>16)&255,($v>>8)&255,$v&255,($v>>24)&127];
    eq($rgba($px($f,2,2)),[16,32,48,0],'Ecke mit Hintergrund');eq($rgba($px($f,100,100)),[255,0,0,0],'Icon bleibt');
    eq($rgba($px($c,2,2))[3],127,'ohne Hintergrund transparent');
    eq(rrw_ab_icon_png($root,'/cms/media/i.png','kein-farbwert')[1],'');exec('rm -rf '.escapeshellarg($root));
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
t('Build: Branding-Dateien landen im Repository (Startbild, Screenshots, Store-Texte, Icon-Hintergrund) – nur wenn angegeben',function(){
    if(!function_exists('imagecreatetruecolor'))return;
    $root=sys_get_temp_dir().'/abst-'.bin2hex(random_bytes(4));mkdir($root.'/cms/media',0777,true);mkdir($root.'/data');
    $im=imagecreatetruecolor(800,600);ob_start();imagepng($im);$png=ob_get_clean();foreach(['icon','splash','lockup','s1','s2'] as $f)file_put_contents($root."/cms/media/$f.png",$png);
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
    [$b]=rrw_ab_clean_brand(['icon'=>'/cms/media/icon.png','splash'=>'/cms/media/splash.png','headerLogo'=>'/cms/media/lockup.png','iconBg'=>'#112233','screenshots'=>['/cms/media/s1.png','/cms/media/s2.png'],'shortDescription'=>'Kurz','fullDescription'=>'Lang']+$GLOBALS['__base']);
    [$r,$put,$entry]=$run($b);eq($r['ok'],true,$r['message']);
    foreach(['android/brands.json','brands/meinapp/app_logo.png','brands/meinapp/startscreen.png','brands/meinapp/logo-lockup.png','brands/meinapp/store/screenshot-1.png','brands/meinapp/store/screenshot-2.png','brands/meinapp/store/listing-de.md'] as $f)if(!in_array($f,$put,true))throw new RuntimeException("nicht geschrieben: $f (".implode(', ',$put).')');
    foreach(['iconBg','splash','headerLogo'] as $k)if(array_key_exists($k,$entry[0]))throw new RuntimeException("$k gehört nicht in brands.json (die Dateien tragen es): ".json_encode($entry[0]));
    [$b2]=rrw_ab_clean_brand(['icon'=>'/cms/media/icon.png']+$GLOBALS['__base']);
    [$r,$put,$entry]=$run($b2);eq($r['ok'],true,$r['message']);
    eq($put,['android/brands.json','brands/meinapp/app_logo.png'],'ohne Branding nur wie bisher');
    exec('rm -rf '.escapeshellarg($root));
});
t('brands.json: von Hand ergänzte Angaben bleiben erhalten, bekannte Felder folgen dem CMS',function(){
    $cur=[['id'=>'meinapp','applicationId'=>'de.alt.app','appName'=>'Alt','launchUrl'=>'https://alt.example/','site'=>'https://alt.example','filePrefix'=>'Alt','type'=>'content','themeColor'=>'#111111','extra'=>['podcast'=>true,'shops'=>[['title'=>'Shop','url'=>'https://s.example/']]]],
          ['id'=>'andere','applicationId'=>'de.x.y','appName'=>'X','launchUrl'=>'https://x/','site'=>'https://x','filePrefix'=>'X']];
    $GLOBALS['rrw_ab_http']=fn($m,$path,$tok,$json)=>['code'=>200,'body'=>json_encode(['content'=>base64_encode(json_encode($cur)),'sha'=>'x']),'headers'=>[]];
    [$b]=rrw_ab_clean_brand(['type'=>'web','applicationId'=>'de.neu.app']+$GLOBALS['__base']);   // Website-App, ohne Farbe
    [$json,$err]=rrw_ab_merge_brands(['repo'=>'me/x','branch'=>'app-builder','token'=>'t'],$b);unset($GLOBALS['rrw_ab_http']);
    eq($err,'');$list=json_decode((string)$json,true);eq(count($list),2);eq($list[1]['id'],'andere','andere Marke unberührt');
    $e=$list[0];eq($e['applicationId'],'de.neu.app');eq($e['type'],'web','Typ folgt dem CMS');eq(array_key_exists('themeColor',$e),false,'Farbe entfernt');
    eq($e['extra']['podcast'],true,'Handangaben bleiben');eq($e['extra']['shops'][0]['url'],'https://s.example/');
});
echo "\n".($n-$fail)." von $n Prüfungen bestanden\n";
exit($fail?1:0);

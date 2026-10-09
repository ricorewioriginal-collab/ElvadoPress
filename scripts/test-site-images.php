<?php
// Prüft die Bilder des Website-Generators (cms/lib/stockmedia.php: elvado_stock_pick, elvado_stock_fetch_for_plan) mit Fake-Abruf (kein Netz): Auswahl des Bildes
// (ohne Namensnennungspflicht, hohe Auflösung), Übernahme in die Mediathek, Alt-Text (KI oder Rückfall), Fehlerfälle. Aufruf: php scripts/test-site-images.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/elvado-si-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/media');define('ELVADO_MEDIA_DIR',$tmp.'/media');
require __DIR__.'/../cms/lib/publish.php';require __DIR__.'/../cms/lib/media.php';require __DIR__.'/../cms/lib/stockmedia.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
$data=$tmp.'/data';mkdir($data);
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$GLOBALS['elvado_stock_download']=function(string $u,string $dest) use($png){ file_put_contents($dest,$png);return true; };
$mode='pix';$calls=[];
$GLOBALS['elvado_stock_http']=function(string $u,array $h) use(&$mode,&$calls){
    $calls[]=$u;
    if(str_contains($u,'pixabay.com/api/')){
        if($mode==='none')return json_encode(['totalHits'=>0,'hits'=>[]]);
        $hit=fn($id,$tags,$w,$hh)=>['id'=>$id,'tags'=>$tags,'webformatURL'=>"https://cdn.pixabay.com/photo/w$id.jpg",'largeImageURL'=>"https://cdn.pixabay.com/photo/l$id.jpg",'imageWidth'=>$w,'imageHeight'=>$hh,'user'=>'Maler','user_id'=>5,'pageURL'=>"https://pixabay.com/photos/$id/"];
        if(str_contains($u,'&id=')){$id=(int)preg_replace('/.*&id=(\d+).*/','$1',$u);return json_encode(['hits'=>[$hit($id,$id===2?'werkstatt, holz':'klein',$id===2?4000:500,$id===2?2600:300)]]);}
        return json_encode(['totalHits'=>3,'hits'=>[$hit(1,'klein',500,300),$hit(2,'werkstatt, holz',4000,2600),$hit(3,'hochformat',1200,1800)]]);
    }
    if(str_contains($u,'api.openverse.org/v1/images/?'))return json_encode(['result_count'=>1,'results'=>[['id'=>'ov9','title'=>'Hobel','creator'=>'Carla','license'=>'by','license_version'=>'2.0','license_url'=>'https://creativecommons.org/licenses/by/2.0/','thumbnail'=>'https://api.openverse.org/t.jpg','url'=>'https://farm.example/hobel.jpg','width'=>2400,'height'=>1600]]]);
    if(str_contains($u,'api.openverse.org/v1/images/ov9/'))return json_encode(['id'=>'ov9','title'=>'Hobel','creator'=>'Carla','license'=>'by','license_version'=>'2.0','license_url'=>'https://creativecommons.org/licenses/by/2.0/','url'=>'https://farm.example/hobel.jpg','thumbnail'=>'https://api.openverse.org/t.jpg','width'=>2400,'height'=>1600]);
    return '';
};
// Nur Openverse (ohne Schlüssel): Pixabay & Co. nicht eingerichtet
elvado_stock_save($data,['enabled'=>['wikimedia'=>false]]);
$p=elvado_stock_pick($data,'wood tools','landscape');
t('Ohne Schlüssel: Openverse liefert (Namensnennung nötig, aber vorhanden)',$p!==null&&$p['provider']==='openverse'&&$p['attribution_required']===true);
// Mit Pixabay-Schlüssel: bestes freies Bild (groß, Querformat) gewinnt gegen Openverse mit Namensnennung
elvado_stock_save($data,['keys'=>['pixabay'=>'PIXKEY12345678']]);
$p=elvado_stock_pick($data,'workshop carpenter','landscape');
t('Auswahl: Pixabay, ohne Namensnennungspflicht, größtes Querformat-Bild (Kennung 2)',$p!==null&&$p['provider']==='pixabay'&&$p['id']==='2'&&$p['attribution_required']===false,json_encode($p));
$calls=[];elvado_stock_pick($data,'workshop carpenter again','landscape');
t('Gutes Bild von Pixabay: Openverse wird nicht abgefragt',!array_filter($calls,fn($u)=>str_contains($u,'openverse')));
$mode='none';$p=elvado_stock_pick($data,'nichts zu finden','landscape');
t('Pixabay ohne Treffer: Rückfall auf Openverse',$p!==null&&$p['provider']==='openverse');
elvado_stock_save($data,['enabled'=>['pixabay'=>false,'openverse'=>false,'wikimedia'=>false,'pexels'=>false,'unsplash'=>false]]);
t('Kein Anbieter nutzbar → null',elvado_stock_pick($data,'x y z','landscape')===null);
// Übernahme für den Entwurf
elvado_stock_save($data,['enabled'=>['pixabay'=>true]]);$mode='pix';
$altCalls=[];$altFn=function(array $meta,string $file,string $q,string $title) use(&$altCalls){$altCalls[]=[$q,$title,is_file($file)];return 'Werkbank in einer Holzwerkstatt';};
$rows=elvado_stock_fetch_for_plan($data,[['key'=>'home:0','q'=>'workshop carpenter','orient'=>'landscape','width'=>1600],['key'=>'post:0','q'=>'  ','width'=>1024]],$altFn);
t('Ergebnis je Anfrage: Bild übernommen, URL der Mediathek, Alt-Text von der KI, Quelle',$rows[0]['ok']===true&&str_starts_with($rows[0]['url'],'/cms/media/library/')&&$rows[0]['alt']==='Werkbank in einer Holzwerkstatt'&&$rows[0]['provider']==='pixabay'&&$rows[0]['key']==='home:0',json_encode($rows[0]));
t('KI bekam Suchbegriff, Titel der Quelle und eine Bilddatei',$altCalls[0][0]==='workshop carpenter'&&$altCalls[0][1]==='werkstatt, holz'&&$altCalls[0][2]===true);
$lib=elvado_media_library_items([]);
t('Alt-Text steht in der Mediathek, Bildnachweis-Titel im Nachweis',count($lib)===1&&$lib[0]['alt']==='Werkbank in einer Holzwerkstatt'&&($lib[0]['credit']['title']??'')==='werkstatt, holz');
t('Leerer Suchbegriff: Fehler in der Zeile, kein Abbruch',$rows[1]['ok']===false&&$rows[1]['error']==='kein Suchbegriff');
$rows=elvado_stock_fetch_for_plan($data,[['key'=>'a','q'=>'holz tisch']],function(){ throw new RuntimeException('KI nicht erreichbar'); });
t('KI-Fehler: Rückfall auf den Titel der Bildquelle als Alt-Text',$rows[0]['ok']===true&&$rows[0]['alt']==='werkstatt, holz');
$rows=elvado_stock_fetch_for_plan($data,[['key'=>'b','q'=>'holz tisch']],fn()=>'');
t('Leere KI-Antwort: ebenfalls Rückfall',$rows[0]['alt']==='werkstatt, holz');
$mode='none';elvado_stock_save($data,['enabled'=>['pixabay'=>true]]);
$rows=elvado_stock_fetch_for_plan($data,[['key'=>'c','q'=>'gibt es nicht']],$altFn);
t('Kein Treffer: verständlicher Fehler',$rows[0]['ok']===false&&str_contains($rows[0]['error'],'kein passendes Bild'));
$many=array_map(fn($i)=>['key'=>"k$i",'q'=>'x'.$i.' abc'],range(1,12));
t('Höchstens 8 Anfragen je Aufruf',count(elvado_stock_fetch_for_plan($data,$many,$altFn))===8);
system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

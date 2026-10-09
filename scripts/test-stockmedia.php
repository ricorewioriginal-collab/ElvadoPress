<?php
// Prüft die freien Bildquellen (cms/lib/stockmedia.php): Konfiguration (Schlüssel nur serverseitig), Suche und Normalisierung für Pixabay, Pexels,
// Unsplash, Openverse, Wikimedia Commons mit Fake-Abruf, Zwischenspeicher, Übernahme in die Mediathek mit Bildnachweis, Sicherheitsgrenzen. Aufruf: php scripts/test-stockmedia.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/elvado-stk-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/media');
define('ELVADO_MEDIA_DIR',$tmp.'/media');
require __DIR__.'/../cms/lib/publish.php';require __DIR__.'/../cms/lib/media.php';require __DIR__.'/../cms/lib/stockmedia.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
$data=$tmp.'/data';mkdir($data);
$calls=[];
$fake=function(string $u,array $h) use(&$calls){
    $calls[]=[$u,$h];
    if(str_contains($u,'pixabay.com/api/')&&str_contains($u,'&id='))return json_encode(['hits'=>[['id'=>77,'tags'=>'berg, see','webformatURL'=>'https://cdn.pixabay.com/photo/w77.jpg','largeImageURL'=>'https://cdn.pixabay.com/photo/l77.jpg','imageWidth'=>3000,'imageHeight'=>2000,'user'=>'Maler','user_id'=>5,'pageURL'=>'https://pixabay.com/photos/77/']]]);
    if(str_contains($u,'pixabay.com/api/'))return json_encode(['totalHits'=>2,'hits'=>[['id'=>77,'tags'=>'berg, see','webformatURL'=>'https://cdn.pixabay.com/photo/w77.jpg','largeImageURL'=>'https://cdn.pixabay.com/photo/l77.jpg','imageWidth'=>3000,'imageHeight'=>2000,'user'=>'Maler','user_id'=>5,'pageURL'=>'https://pixabay.com/photos/77/'],['id'=>78,'tags'=>'x','webformatURL'=>'http://insecure.example/a.jpg','largeImageURL'=>'http://insecure.example/b.jpg','user'=>'Y','user_id'=>1]]]);
    if(str_contains($u,'api.pexels.com/v1/photos/'))return json_encode(['id'=>5,'width'=>4000,'height'=>3000,'url'=>'https://www.pexels.com/photo/5/','photographer'=>'Pia','photographer_url'=>'https://www.pexels.com/@pia','alt'=>'Strand','src'=>['medium'=>'https://images.pexels.com/m5.jpg','large'=>'https://images.pexels.com/l5.jpg','large2x'=>'https://images.pexels.com/l5x2.jpg']]);
    if(str_contains($u,'api.pexels.com/v1/search'))return json_encode(['total_results'=>1,'photos'=>[['id'=>5,'width'=>4000,'height'=>3000,'url'=>'https://www.pexels.com/photo/5/','photographer'=>'Pia','photographer_url'=>'https://www.pexels.com/@pia','alt'=>'Strand','src'=>['medium'=>'https://images.pexels.com/m5.jpg','large'=>'https://images.pexels.com/l5.jpg','large2x'=>'https://images.pexels.com/l5x2.jpg']]]]);
    if(str_contains($u,'api.unsplash.com/photos/abc/download'))return '{}';
    if(str_contains($u,'api.unsplash.com/photos/'))return json_encode(['id'=>'abc','description'=>'Wald','width'=>5000,'height'=>3000,'urls'=>['small'=>'https://images.unsplash.com/s','regular'=>'https://images.unsplash.com/r'],'links'=>['html'=>'https://unsplash.com/photos/abc','download_location'=>'https://api.unsplash.com/photos/abc/download'],'user'=>['name'=>'Uwe','links'=>['html'=>'https://unsplash.com/@uwe']]]);
    if(str_contains($u,'api.unsplash.com/search'))return json_encode(['total'=>1,'results'=>[['id'=>'abc','description'=>'Wald','width'=>5000,'height'=>3000,'urls'=>['small'=>'https://images.unsplash.com/s','regular'=>'https://images.unsplash.com/r'],'links'=>['html'=>'https://unsplash.com/photos/abc','download_location'=>'https://api.unsplash.com/photos/abc/download'],'user'=>['name'=>'Uwe','links'=>['html'=>'https://unsplash.com/@uwe']]]]]);
    if(str_contains($u,'api.openverse.org/v1/images/?'))return json_encode(['result_count'=>2,'results'=>[
        ['id'=>'ov1','title'=>'Katze','creator'=>'Carla','creator_url'=>'https://flickr.example/carla','license'=>'by','license_version'=>'2.0','license_url'=>'https://creativecommons.org/licenses/by/2.0/','thumbnail'=>'https://api.openverse.org/v1/images/ov1/thumb/','url'=>'https://farm.example/cat.jpg','foreign_landing_url'=>'https://flickr.example/cat','width'=>800,'height'=>600,'attribution'=>'"Katze" by Carla is licensed under CC BY 2.0.'],
        ['id'=>'ov2','title'=>'Hund','creator'=>'','license'=>'cc0','license_version'=>'1.0','thumbnail'=>'https://api.openverse.org/v1/images/ov2/thumb/','url'=>'https://farm.example/dog.jpg']]]);
    if(str_contains($u,'api.openverse.org/v1/images/ov1/'))return json_encode(['id'=>'ov1','title'=>'Katze','creator'=>'Carla','license'=>'by','license_version'=>'2.0','url'=>'https://farm.example/cat.jpg','thumbnail'=>'https://api.openverse.org/v1/images/ov1/thumb/','attribution'=>'"Katze" by Carla is licensed under CC BY 2.0.']);
    if(str_contains($u,'commons.wikimedia.org'))return json_encode(['query'=>['pages'=>['11'=>['pageid'=>11,'title'=>'File:Berlin Dom.jpg','imageinfo'=>[['thumburl'=>'https://upload.wikimedia.org/t.jpg','url'=>'https://upload.wikimedia.org/o.jpg','width'=>4000,'height'=>3000,'mime'=>'image/jpeg','descriptionurl'=>'https://commons.wikimedia.org/wiki/File:Berlin_Dom.jpg','extmetadata'=>['Artist'=>['value'=>'<a href="x">Max Foto</a>'],'LicenseShortName'=>['value'=>'CC BY-SA 4.0'],'LicenseUrl'=>['value'=>'https://creativecommons.org/licenses/by-sa/4.0']]]]],'12'=>['pageid'=>12,'title'=>'File:Plan.svg','imageinfo'=>[['mime'=>'image/svg+xml','url'=>'https://upload.wikimedia.org/p.svg','thumburl'=>'https://upload.wikimedia.org/p.png']]]]]]);
    return '';
};
$GLOBALS['elvado_stock_http']=$fake;
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$dls=[];$GLOBALS['elvado_stock_download']=function(string $u,string $dest) use(&$dls,$png){ $dls[]=$u;file_put_contents($dest,$png);return true; };
// Konfiguration
$st=elvado_stock_status($data);$byId=array_column($st,null,'id');
t('Anbieter: drei mit Schlüssel, zwei ohne',count($st)===5&&$byId['pixabay']['needs_key']&&!$byId['openverse']['needs_key']);
t('Ohne Schlüssel nur Anbieter ohne Schlüssel nutzbar',!$byId['pixabay']['usable']&&$byId['openverse']['usable']&&$byId['wikimedia']['usable']);
try{ elvado_stock_search($data,'pixabay','berg');$e='';}catch(Throwable $x){ $e=$x->getMessage(); }t('Suche ohne Schlüssel wird verweigert, kein Netzabruf',str_contains($e,'nicht eingerichtet')&&$calls===[]);
try{ elvado_stock_save($data,['keys'=>['pixabay'=>'böse key!']]);$e='';}catch(Throwable $x){ $e=$x->getMessage(); }t('Ungültiger Schlüssel abgelehnt',str_contains($e,'unzulässige'));
elvado_stock_save($data,['keys'=>['pixabay'=>'PIXKEY12345678','pexels'=>'pexelskey12345678','unsplash'=>'unsplashkey1234567']]);
$st=array_column(elvado_stock_status($data),null,'id');
t('Schlüssel gesetzt → nutzbar; Status enthält keinen Schlüssel',$st['pixabay']['usable']&&!str_contains(json_encode(elvado_stock_status($data)),'PIXKEY'));
t('Schlüsseldatei im gesperrten Ordner',is_file($data.'/.tools/stockmedia.json')&&is_file($data.'/.tools/.htaccess'));
elvado_stock_save($data,['keys'=>['pixabay'=>'']]);t('Leerer Wert lässt Schlüssel unverändert',elvado_stock_config($data)['keys']['pixabay']==='PIXKEY12345678');
elvado_stock_save($data,['enabled'=>['pexels'=>false]]);t('Anbieter abschaltbar',!array_column(elvado_stock_status($data),null,'id')['pexels']['usable']);
elvado_stock_save($data,['enabled'=>['pexels'=>true]]);
// Suche
$r=elvado_stock_search($data,'pixabay','Berg See',1,'landscape');
t('Pixabay: Ergebnisse normalisiert',count($r['items'])===2&&$r['items'][0]['id']==='77'&&$r['items'][0]['author']==='Maler'&&$r['items'][0]['credit']==='Foto: Maler / Pixabay'&&!$r['items'][0]['attribution_required']);
t('Pixabay: Anfrage mit Schlüssel, Suchbegriff und Ausrichtung',str_contains($calls[0][0],'key=PIXKEY12345678')&&str_contains($calls[0][0],'q=Berg%20See')&&str_contains($calls[0][0],'orientation=horizontal'));
t('Download-Adressen und Schlüssel verlassen den Server nicht',!str_contains(json_encode($r),'largeImageURL')&&!isset($r['items'][0]['download'])&&!str_contains(json_encode($r),'PIXKEY'));
t('Unsichere (http) Vorschaubilder werden entfernt',$r['items'][1]['thumb']==='');
$before=count($calls);elvado_stock_search($data,'pixabay','Berg See',1,'landscape');t('Zwischenspeicher: kein erneuter Abruf',count($calls)===$before);
$r=elvado_stock_search($data,'pexels','Strand');t('Pexels: Kopfzeile Authorization, Item',$r['items'][0]['author']==='Pia'&&in_array('Authorization: pexelskey12345678',end($calls)[1],true)&&$r['items'][0]['credit']==='Foto: Pia / Pexels');
$r=elvado_stock_search($data,'unsplash','Wald');t('Unsplash: Client-ID, Quellenangabe Pflicht',$r['items'][0]['attribution_required']===true&&in_array('Authorization: Client-ID unsplashkey1234567',end($calls)[1],true)&&str_contains($r['items'][0]['source_url'],'utm_source='));
$r=elvado_stock_search($data,'openverse','katze');t('Openverse: ohne Schlüssel, Lizenz, CC0 ohne Pflicht',count($r['items'])===2&&$r['items'][0]['attribution_required']&&!$r['items'][1]['attribution_required']&&str_contains($r['items'][0]['credit'],'Carla')&&$r['items'][0]['license']==='CC BY 2.0');
$r=elvado_stock_search($data,'wikimedia','dom');t('Wikimedia: nur Rasterbilder, Urheber ohne HTML, Lizenz',count($r['items'])===1&&$r['items'][0]['author']==='Max Foto'&&$r['items'][0]['license']==='CC BY-SA 4.0'&&str_contains($r['items'][0]['credit'],'Wikimedia Commons'));
foreach([['',1],[str_repeat('x',101),1]] as [$q,$pg]){ try{ elvado_stock_search($data,'openverse',$q,$pg);$e='';}catch(Throwable $x){ $e=$x->getMessage(); }t('Leerer/zu langer Suchbegriff abgelehnt',$e!==''); }
try{ elvado_stock_search($data,'gibtsnicht','x');$e='';}catch(Throwable $x){ $e=$x->getMessage(); }t('Unbekannter Anbieter abgelehnt',$e!=='');
$GLOBALS['elvado_stock_http']=fn($u,$h)=>'';try{ elvado_stock_search($data,'openverse','neuer begriff');$e='';}catch(Throwable $x){ $e=$x->getMessage(); }t('Quelle nicht erreichbar: verständliche Meldung',str_contains($e,'nicht erreichbar'));
$status=function(int $code,string $body) use($data){ $GLOBALS['elvado_stock_http']=function($u,$h) use($code,$body){ $GLOBALS['elvado_stock_last']=['code'=>$code,'body'=>$body];return ''; };try{ elvado_stock_search($data,'openverse','status-'.$code);return ''; }catch(Throwable $x){ return $x->getMessage(); } };
t('Fehlermeldungen nach HTTP-Status: 401 Schlüssel, 429 Limit, 400 mit Hinweis des Anbieters, 500 mit Status',str_contains($status(401,'{"detail":"x"}'),'Schlüssel')&&str_contains($status(429,''),'Limit')&&str_contains($status(400,'{"detail":"page_size may not exceed 20"}'),'page_size may not exceed 20')&&str_contains($status(503,''),'HTTP 503'));
$GLOBALS['elvado_stock_http']=$fake;$calls=[];elvado_stock_search($data,'openverse','seitengroesse');
t('Openverse: höchstens 20 Treffer je Seite (anonym erlaubt) – sonst lehnt Openverse mit 401 ab',preg_match('/page_size=(\d+)/',(string)end($calls)[0],$m)===1&&(int)$m[1]<=20);
// Übernahme
$GLOBALS['elvado_stock_http']=$fake;
$r=elvado_stock_import($data,'pixabay','77');$m=$r['item'];
t('Übernahme: Bild in der Mediathek (Original + Varianten)',is_file(ELVADO_MEDIA_DIR.'/'.$m['original']['path'])&&$m['width']===1&&str_starts_with($m['original']['url'],'/cms/media/library/'));
t('Download von der großen Variante (nicht Vorschau)',$dls===['https://cdn.pixabay.com/photo/l77.jpg']);
t('Bildnachweis im meta.json und in der Bibliotheksliste',($m['credit']['author']??'')==='Maler'&&$m['credit']['provider']==='pixabay'&&str_contains($m['credit']['license_url'],'pixabay.com')&&elvado_media_library_items()[0]['credit']['text']==='Foto: Maler / Pixabay');
t('Antwort: Nachweis-Text und Pflicht-Kennzeichen',$r['credit']==='Foto: Maler / Pixabay'&&$r['attribution_required']===false);
$r2=elvado_stock_import($data,'unsplash','abc');t('Unsplash: Download wird gemeldet, Pflicht-Nachweis',$r2['attribution_required']&&array_filter($calls,fn($c)=>str_contains($c[0],'photos/abc/download'))!==[]);
$r3=elvado_stock_import($data,'openverse','ov1');t('Openverse: beliebiger https-Host erlaubt, Nachweis mit Lizenz',$r3['item']['credit']['license']==='CC BY 2.0'&&str_contains($r3['credit'],'Carla'));
$r4=elvado_stock_import($data,'wikimedia','11');t('Wikimedia übernommen',$r4['item']['credit']['provider']==='wikimedia'&&$r4['attribution_required']);
// Sicherheit
foreach([['pixabay','../etc'],['pixabay','1 2'],['pexels',''],['openverse','x/y']] as [$p,$i]){ try{ elvado_stock_import($data,$p,$i);$e='';}catch(Throwable $x){ $e=$x->getMessage(); }t("Ungültige Kennung abgelehnt ($p/$i)",$e!==''); }
$GLOBALS['elvado_stock_http']=fn($u,$h)=>str_contains($u,'&id=')?json_encode(['hits'=>[['id'=>9,'tags'=>'x','webformatURL'=>'https://cdn.pixabay.com/a','largeImageURL'=>'https://evil.example/steal.jpg','user'=>'u','user_id'=>1]]]):'';
$dl0=count($dls);try{ elvado_stock_import($data,'pixabay','9');$e='';}catch(Throwable $x){ $e=$x->getMessage(); }t('Fremder Download-Host (nicht von Pixabay) wird nicht geladen',str_contains($e,'nicht zulässig')&&count($dls)===$dl0);
$GLOBALS['elvado_stock_http']=fn($u,$h)=>str_contains($u,'&id=')?json_encode(['hits'=>[['id'=>9,'tags'=>'x','webformatURL'=>'https://cdn.pixabay.com/a','largeImageURL'=>'http://cdn.pixabay.com/a.jpg','user'=>'u','user_id'=>1]]]):'';
try{ elvado_stock_import($data,'pixabay','9');$e='';}catch(Throwable $x){ $e=$x->getMessage(); }t('http-Download wird abgelehnt',$e!=='');
$GLOBALS['elvado_stock_http']=$fake;
$GLOBALS['elvado_stock_download']=function(string $u,string $dest){ file_put_contents($dest,'<?php echo 1; ?> kein Bild');return true; };
$cnt=count(glob(ELVADO_MEDIA_DIR.'/library/*'));try{ elvado_stock_import($data,'pixabay','77');$e='';}catch(Throwable $x){ $e=$x->getMessage(); }
t('Kein Bild (PHP-Code) wird nicht gespeichert',$e!==''&&count(glob(ELVADO_MEDIA_DIR.'/library/*'))===$cnt);
$GLOBALS['elvado_stock_download']=fn($u,$d)=>false;try{ elvado_stock_import($data,'pixabay','77');$e='';}catch(Throwable $x){ $e=$x->getMessage(); }t('Download scheitert: verständliche Meldung',str_contains($e,'heruntergeladen'));
unset($GLOBALS['elvado_stock_http'],$GLOBALS['elvado_stock_download']);
t('SSRF: private/lokale Adresse wird nie abgerufen',elvado_stock_http('http://127.0.0.1/x')===''&&elvado_stock_http('http://localhost/x')===''&&!elvado_stock_download('http://127.0.0.1/x.jpg',$tmp.'/x'));
t('Host-Prüfung',elvado_stock_host_ok('pixabay','https://cdn.pixabay.com/a.jpg')&&!elvado_stock_host_ok('pixabay','https://pixabay.com.evil.example/a.jpg')&&!elvado_stock_host_ok('pexels','http://images.pexels.com/a')&&elvado_stock_host_ok('openverse','https://x.example/a.jpg'));
system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

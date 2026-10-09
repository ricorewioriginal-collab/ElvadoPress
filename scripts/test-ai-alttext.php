<?php
// Prüft die Alt-Texte (cms/src/Ai/AltTexter.php, cms/lib/media.php): Speichern in der Mediathek (meta.json), Auswahl der Bild-Quelldatei, Bildeingabe je Anbieter
// (OpenAI, Claude, Gemini) mit Fake-Transport, Rückfall auf Text, Bereinigung der KI-Antwort. Aufruf: php scripts/test-ai-alttext.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/elvado-alt-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/media');define('ELVADO_MEDIA_DIR',$tmp.'/media');
require __DIR__.'/../cms/src/autoload.php';require __DIR__.'/../cms/lib/publish.php';require __DIR__.'/../cms/lib/media.php';
use Elvado\Ai\{AiGatewayConfig,AiGatewayService,AiGatewayException,AltTexter};use Elvado\Support\{Http,HttpResponse};
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
function thr(callable $f,string $needle=''): ?AiGatewayException { try{ $f();return null; }catch(AiGatewayException $e){ return ($needle===''||str_contains($e->getMessage(),$needle))?$e:null; } }
// Bereinigung
t('Bereinigung: Anführungszeichen, erste Zeile, Alt-Text:, Punkt, Großschreibung',AltTexter::clean("\"roter Tisch aus Holz.\"\nZweite Zeile")==='Roter Tisch aus Holz'&&AltTexter::clean('Alt-Text: ein See bei Sonnenaufgang')==='Ein See bei Sonnenaufgang');
$long=str_repeat('Wort ',60);t('Bereinigung: Länge höchstens 125, am Wortende',mb_strlen(AltTexter::clean($long))<=125&&!str_ends_with(AltTexter::clean($long),' '));
t('Bereinigung: HTML entfernt, leer bleibt leer',AltTexter::clean('<b>Fett</b> Text')==='Fett Text'&&AltTexter::clean('  ')==='');
t('Lesbarer Name aus Dateinamen',AltTexter::readableName('holz-tisch-pixabay-123456.jpg')==='holz tisch'&&AltTexter::readableName('IMG_20240101_123456.jpg')===''&&AltTexter::readableName('upload_a1b2c3d4.png')==='');
// Mediathek: Alt speichern, Quelldatei
$id='20260101_000000_abcdef1234';$dir=$tmp.'/media/library/'.$id;mkdir($dir,0755,true);
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
file_put_contents($dir.'/original.png',$png);file_put_contents($dir.'/w512.webp','RIFFxxxxWEBPfake');
file_put_contents($dir.'/meta.json',json_encode(['id'=>$id,'name'=>'holz-tisch-pixabay-123456','mime'=>'image/png','original'=>['url'=>'/cms/media/library/'.$id.'/original.png','path'=>'library/'.$id.'/original.png','size'=>strlen($png)],'variants'=>[['width'=>512,'url'=>'/cms/media/library/'.$id.'/w512.webp','path'=>'library/'.$id.'/w512.webp']],'credit'=>['title'=>'Roter Holztisch','text'=>'Foto: X']]));
$r=elvado_media_alt_save($id,"  Ein <b>roter</b>   Tisch \n aus Holz ");
t('Alt speichern: bereinigt, in meta.json, in der Medienliste',$r['alt']==='Ein roter Tisch aus Holz'&&json_decode((string)file_get_contents($dir.'/meta.json'),true)['alt']==='Ein roter Tisch aus Holz'&&elvado_media_library_items([])[0]['alt']==='Ein roter Tisch aus Holz');
elvado_media_alt_save($id,'');t('Alt leeren entfernt das Feld',!array_key_exists('alt',json_decode((string)file_get_contents($dir.'/meta.json'),true)));
t('Alt speichern: ungültige Kennung und unbekanntes Bild',(function() use($id){foreach(['../x','a b','zzzzzz_nicht_da'] as $bad){try{elvado_media_alt_save($bad,'x');return false;}catch(RuntimeException $e){}}return true;})());
$src=elvado_media_alt_source($id);
t('Quelldatei: Original (PNG ≤ 1,2 MB), da Variante nur 512 aber nicht lesbar? → Variante wird bevorzugt',$src['file']!==''&&str_ends_with($src['file'],'w512.webp'));
unlink($dir.'/w512.webp');$m=json_decode((string)file_get_contents($dir.'/meta.json'),true);unset($m['variants']);file_put_contents($dir.'/meta.json',json_encode($m));
t('Quelldatei: ohne Variante das kleine Original',str_ends_with(elvado_media_alt_source($id)['file'],'original.png'));
// Gateway
$calls=[];$answer='"roter Tisch aus Holz."';$kind='openai';
Http::useTransport(function(string $m,string $u,array $h,?string $b,array $o) use(&$calls,&$answer): HttpResponse {
    $calls[]=['u'=>$u,'h'=>$h,'b'=>$b?json_decode($b,true):null];
    if(str_contains($u,'anthropic.com'))return new HttpResponse(200,json_encode(['content'=>[['type'=>'text','text'=>$answer]],'usage'=>['input_tokens'=>1,'output_tokens'=>1]]));
    if(str_contains($u,'googleapis.com'))return new HttpResponse(200,json_encode(['candidates'=>[['content'=>['parts'=>[['text'=>$answer]]]]]]));
    return new HttpResponse(200,json_encode(['choices'=>[['message'=>['content'=>$answer]]],'usage'=>['prompt_tokens'=>1,'completion_tokens'=>1]]));
});
$cfgDir=$tmp.'/cfg';$cfg=AiGatewayConfig::load($cfgDir);
t('Ohne Anbieter: kein Bildanbieter, Alt-Text nur mit Metadaten möglich (sonst verständliche Meldung)',(new AiGatewayService($cfg))->visionProvider()===''&&thr(fn()=>(new AltTexter(new AiGatewayService($cfg)))->suggest(['name'=>'IMG_1.jpg']),'Bildverständnis')!==null);
$cfg->save(['providers'=>['groq'=>['api_key'=>'gsk-test-key-1234'],'openai'=>['api_key'=>'sk-test-openai-1234'],'anthropic'=>['api_key'=>'sk-ant-test-1234'],'google'=>['api_key'=>'goog-test-key-1234']]]);$cfg=AiGatewayConfig::load($cfgDir);
$svc=new AiGatewayService($cfg);$at=new AltTexter($svc);$meta=json_decode((string)file_get_contents($dir.'/meta.json'),true);
t('Bildanbieter: erster nutzbarer mit Bildverständnis (OpenAI), „Zweck media“ hat Vorrang',$svc->visionProvider()==='openai'&&(function() use($cfg,$cfgDir){$cfg->save(['purposes'=>['media'=>'google']]);return (new AiGatewayService(AiGatewayConfig::load($cfgDir)))->visionProvider()==='google';})());
$cfg->save(['purposes'=>['media'=>'groq']]);$cfg=AiGatewayConfig::load($cfgDir);$svc=new AiGatewayService($cfg);$at=new AltTexter($svc);
t('Zweck media auf Anbieter ohne Bildverständnis → Rückfall auf den ersten mit Bildverständnis',$svc->visionProvider()==='openai');
$file=$dir.'/original.png';
$r=$at->suggest($meta,$file,'u1','Hero der Startseite');
$p=$calls[0]['b']['messages'][1]['content'];
t('OpenAI: Bild als data-URL (image_url), Text zuerst, Modus „vision“, bereinigter Alt-Text',$r['mode']==='vision'&&$r['provider']==='openai'&&$p[0]['type']==='text'&&$p[1]['type']==='image_url'&&str_starts_with($p[1]['image_url']['url'],'data:image/png;base64,')&&$r['alt']==='Roter Tisch aus Holz',json_encode($r));
t('Prompt enthält Titel der Bildquelle, Dateiname und Verwendung',str_contains($p[0]['text'],'Roter Holztisch')&&str_contains($p[0]['text'],'holz tisch')&&str_contains($p[0]['text'],'Hero der Startseite'));
$cfg->save(['purposes'=>['media'=>'anthropic']]);$at=new AltTexter(new AiGatewayService(AiGatewayConfig::load($cfgDir)));$calls=[];$at->suggest($meta,$file);
$c=$calls[0]['b']['messages'][0]['content'];t('Claude: image-Block (base64, media_type) vor dem Text',$c[0]['type']==='image'&&$c[0]['source']['type']==='base64'&&$c[0]['source']['media_type']==='image/png'&&$c[1]['type']==='text');
$cfg->save(['purposes'=>['media'=>'google']]);$at=new AltTexter(new AiGatewayService(AiGatewayConfig::load($cfgDir)));$calls=[];$at->suggest($meta,$file);
$g=$calls[0]['b']['contents'][0]['parts'];t('Gemini: inlineData (mimeType, data) vor dem Text',isset($g[0]['inlineData']['mimeType'])&&$g[0]['inlineData']['mimeType']==='image/png'&&isset($g[1]['text']));
// Rückfall auf Text (kein Bildanbieter, aber Metadaten)
$only=$tmp.'/cfg2';$c2=AiGatewayConfig::load($only);$c2->save(['providers'=>['groq'=>['api_key'=>'gsk-test-key-1234']],'default_provider'=>'groq']);
$calls=[];$tx=new AltTexter(new AiGatewayService(AiGatewayConfig::load($only)));$r2=$tx->suggest($meta,$file);
t('Ohne Bildanbieter: Text-Modus aus den Metadaten (kein Bild gesendet)',$r2['mode']==='text'&&is_string($calls[0]['b']['messages'][1]['content'])&&str_contains($calls[0]['b']['messages'][1]['content'],'Roter Holztisch'));
// Gateway-Schutz
t('Bilder an Anbieter ohne Bildverständnis werden abgelehnt',thr(fn()=>$svc->generate(['provider'=>'groq','task'=>'alt','internal'=>true,'system'=>'x','prompt'=>'y','images'=>[['mime'=>'image/png','data'=>base64_encode($png)]]]),'keine Bilder lesen')!==null);
t('Ungültige Bilder (Typ, Zeichen) werden abgelehnt',thr(fn()=>$svc->generate(['provider'=>'openai','task'=>'alt','internal'=>true,'system'=>'x','prompt'=>'y','images'=>[['mime'=>'text/html','data'=>'AAAA']]]),'nicht verwendbar')!==null&&thr(fn()=>$svc->generate(['provider'=>'openai','task'=>'alt','internal'=>true,'system'=>'x','prompt'=>'y','images'=>[['mime'=>'image/png','data'=>'<script>']]]),'nicht verwendbar')!==null);
t('Bild-Eingabe nur für interne Aufrufer (nicht aus der Oberfläche)',thr(fn()=>$svc->generate(['provider'=>'openai','task'=>'text','prompt'=>'y','images'=>[['mime'=>'image/png','data'=>base64_encode($png)]]]))===null&&!is_array($calls[count($calls)-1]['b']['messages'][1]['content']));
t('Admin-Sicht zeigt Bildverständnis je Anbieter',(function() use($cfg){$v=array_column($cfg->adminView()['providers'],'vision','id');return $v['openai']===true&&$v['groq']===false;})());
Http::useTransport(null);system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

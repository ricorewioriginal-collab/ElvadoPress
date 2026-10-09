<?php
// Prüft KI-Bilder/-Videos (cms/src/Ai/MediaGenerator.php) und den fal.ai-Textzugang mit Fake-Transport (kein Netz). Aufruf: php scripts/test-ai-media.php
declare(strict_types=1);
require __DIR__.'/../cms/src/autoload.php';
use Elvado\Ai\{AiGatewayConfig,AiGatewayService,AiGatewayException,MediaGenerator};use Elvado\Support\{Http,HttpResponse};
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
function thr(callable $f,string $needle=''): bool { try{ $f();return false; }catch(AiGatewayException $e){ return $needle===''||str_contains($e->getMessage(),$needle); } }
$tmp=sys_get_temp_dir().'/elvado-aim-'.bin2hex(random_bytes(4));mkdir($tmp);
$calls=[];$reply=null;
Http::useTransport(function(string $m,string $u,array $h,?string $b,array $o) use(&$calls,&$reply): HttpResponse { $calls[]=['m'=>$m,'u'=>$u,'h'=>$h,'b'=>$b?json_decode($b,true):null];return $reply($m,$u); });
$cfg=AiGatewayConfig::load($tmp);$mg=new MediaGenerator($cfg);
t('Ohne Schlüssel kein Medien-Anbieter',$mg->providers()===[]);
$cfg->save(['providers'=>['evolink'=>['api_key'=>'evo-test-key-1234'],'fal'=>['api_key'=>'fal-test-key-1234'],'openai'=>['api_key'=>'sk-test-openai-1234']]]);
$cfg=AiGatewayConfig::load($tmp);$mg=new MediaGenerator($cfg);
t('EvoLink, fal.ai und OpenAI sind Medien-Anbieter',array_column($mg->providers(),'id')===['evolink','fal','openai']);
t('EvoLink hat mehrere Bild- und Videomodelle',count(MediaGenerator::MODELS['evolink']['image'])>=3&&count(MediaGenerator::MODELS['evolink']['video'])>=2);
t('Katalog: EvoLink hat viele Textmodelle (nicht nur evolink-auto), fal.ai ist Anbieter',count($cfg->catalog()['evolink']['models'])>=10&&isset($cfg->catalog()['fal']));
t('Gemini-Standard ist ein dauerhafter Alias',$cfg->model('google')==='gemini-flash-latest');

// EvoLink Bild: Auftrag + Abfrage
$reply=fn($m,$u)=>new HttpResponse(200,json_encode(['id'=>'task-unified-123','status'=>'pending','progress'=>0]));
$r=$mg->start('evolink','image','gpt-image-2.5-flare','Ein Studio','16:9');$c=end($calls);
t('EvoLink: POST /v1/images/generations mit Bearer, Modell, Format',$c['m']==='POST'&&str_ends_with($c['u'],'/v1/images/generations')&&in_array('Authorization: Bearer evo-test-key-1234',$c['h'],true)&&$c['b']['model']==='gpt-image-2.5-flare'&&$c['b']['size']==='16:9'&&$r['job']==='evolink:task-unified-123');
$mg->start('evolink','video','','Ein Studio','9:16');$c=end($calls);
t('EvoLink: Video über /v1/videos/generations mit Standardmodell',str_ends_with($c['u'],'/v1/videos/generations')&&$c['b']['model']==='seedance-2.0-text-to-video'&&$c['b']['aspect_ratio']==='9:16');
$reply=fn($m,$u)=>new HttpResponse(200,json_encode(['status'=>'processing','progress'=>40]));
$s=$mg->status('evolink:task-unified-123');$c=end($calls);
t('EvoLink: Abfrage GET /v1/tasks/{id}, Fortschritt',$c['m']==='GET'&&str_ends_with($c['u'],'/v1/tasks/task-unified-123')&&$s['status']==='pending'&&$s['progress']===40);
$reply=fn($m,$u)=>new HttpResponse(200,json_encode(['status'=>'completed','results'=>['https://cdn.example/a.png']]));
t('EvoLink: fertig liefert die Adresse',$mg->status('evolink:task-unified-123')===['status'=>'completed','url'=>'https://cdn.example/a.png']);
$reply=fn($m,$u)=>new HttpResponse(200,json_encode(['status'=>'failed','error'=>['message'=>'Inhalt abgelehnt']]));
t('EvoLink: Fehler wird gemeldet',$mg->status('evolink:task-unified-123')['status']==='failed');

// fal.ai: Queue
$reply=fn($m,$u)=>new HttpResponse(200,json_encode(['request_id'=>'req-abcdef-123456','status'=>'IN_QUEUE']));
$r=$mg->start('fal','image','fal-ai/flux/schnell','Ein Hund','1:1');$c=end($calls);
t('fal.ai: POST queue.fal.run/{modell} mit „Key“-Kopf und Bildgröße',$c['u']==='https://queue.fal.run/fal-ai/flux/schnell'&&in_array('Authorization: Key fal-test-key-1234',$c['h'],true)&&$c['b']['image_size']==='square_hd'&&$r['job']==='fal:req-abcdef-123456@fal-ai/flux/schnell');
$seq=[];$reply=function($m,$u) use(&$seq){$seq[]=$u;return str_ends_with($u,'/status')?new HttpResponse(200,json_encode(['status'=>'COMPLETED'])):new HttpResponse(200,json_encode(['images'=>[['url'=>'https://v3.fal.media/x.png']]]));};
$s=$mg->status($r['job']);
t('fal.ai: Status, dann Ergebnis',$s==['status'=>'completed','url'=>'https://v3.fal.media/x.png']&&$seq[0]==='https://queue.fal.run/fal-ai/flux/schnell/requests/req-abcdef-123456/status'&&$seq[1]==='https://queue.fal.run/fal-ai/flux/schnell/requests/req-abcdef-123456');
$reply=fn($m,$u)=>new HttpResponse(200,json_encode(['status'=>'IN_PROGRESS']));
t('fal.ai: läuft noch',$mg->status($r['job'])['status']==='pending');
$reply=fn($m,$u)=>new HttpResponse(200,json_encode(['video'=>['url'=>'https://v3.fal.media/v.mp4']]));
// OpenAI: sofort
$reply=fn($m,$u)=>new HttpResponse(200,json_encode(['data'=>[['b64_json'=>base64_encode('PNGDATA')]]]));
$r=$mg->start('openai','image','gpt-image-1','Ein Haus','16:9');$c=end($calls);
t('OpenAI: /images/generations, sofort fertig mit Bilddaten',str_ends_with($c['u'],'/images/generations')&&$c['b']['size']==='1536x1024'&&$r['status']==='completed'&&$r['b64']===base64_encode('PNGDATA'));

// Fehler und Schutz
$reply=fn($m,$u)=>new HttpResponse(404,'{}');
t('404 → Hinweis Modellname',thr(fn()=>$mg->start('fal','image','fal-ai/gibts-nicht','x'),'Modell nicht gefunden'));
$reply=fn($m,$u)=>new HttpResponse(401,'{}');
t('401 → Schlüssel prüfen',thr(fn()=>$mg->start('evolink','image','m','x'),'Schlüssel'));
t('Ungültiger Modellname, leere Beschreibung, Video bei OpenAI',thr(fn()=>$mg->start('fal','image','../x y','x'))&&thr(fn()=>$mg->start('fal','image','fal-ai/flux/dev','  '))&&thr(fn()=>$mg->start('openai','video','','x'),'nur Bilder'));
t('Unbekannter/manipulierter Auftrag abgelehnt',thr(fn()=>$mg->status('evolink:../../x'))&&thr(fn()=>$mg->status('fal:abc@../../etc'))&&thr(fn()=>$mg->status('openai:done')));

// fal.ai Text (any-llm)
$reply=fn($m,$u)=>new HttpResponse(200,json_encode(['output'=>'Hallo Welt']));
$svc=new AiGatewayService($cfg);$res=$svc->generate(['provider'=>'fal','prompt'=>'Hi']);$c=end($calls);
t('fal.ai Text: fal.run/fal-ai/any-llm, Antwort „output“',$c['u']==='https://fal.run/fal-ai/any-llm'&&$res->text==='Hallo Welt'&&$c['b']['model']==='google/gemini-2.5-flash'&&isset($c['b']['system_prompt']));

Http::useTransport(null);system('rm -rf '.escapeshellarg($tmp));
echo ($fail?"$fail von $n Prüfungen fehlgeschlagen":"OK – $n Prüfungen bestanden")."\n";exit($fail?1:0);

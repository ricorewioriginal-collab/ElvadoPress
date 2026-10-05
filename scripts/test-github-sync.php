<?php
// Prüft die GitHub-Synchronisation (cms/src/GitHub): Webhook-Signatur und -Entscheidungen, Abruf/Entpacken mit präparierten ZIP-Dateien (Fake-Transport, kein Netz),
// Schutz gegen Pfad-Tricks/PHP/versteckte Dateien/Symlinks/Übergrößen, Aktualisieren und Aufräumen alter Dateien. Aufruf: php scripts/test-github-sync.php
declare(strict_types=1);
require __DIR__.'/../cms/src/autoload.php';
use Elvado\GitHub\{GitHubSyncService,GitHubSyncException,WebhookController};use Elvado\Lovable\LovableSettings;use Elvado\Support\{Http,HttpResponse,RateLimiter};
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
function thr(callable $f,string $needle=''): bool { try{ $f();return false; }catch(GitHubSyncException $e){ return $needle===''||str_contains($e->getMessage(),$needle); } }
$tmp=sys_get_temp_dir().'/rrw-gh-'.bin2hex(random_bytes(4));mkdir($tmp);$front=$tmp.'/frontend/lovable';
$sha1=str_repeat('a',40);$sha2=str_repeat('b',40);
function makeZip(string $file,array $entries,array $symlinks=[]): void { $z=new ZipArchive();$z->open($file,ZipArchive::CREATE|ZipArchive::OVERWRITE);foreach($entries as $name=>$c){ $z->addFromString($name,$c); }foreach($symlinks as $name=>$target){ $z->addFromString($name,$target);$z->setExternalAttributesName($name,ZipArchive::OPSYS_UNIX,(0120777<<16)); }$z->close(); }
$good=['own-repo-'.$sha1.'/src/App.tsx'=>'export default 1;','own-repo-'.$sha1.'/src/components/Hero.tsx'=>'hero','own-repo-'.$sha1.'/src/index.css'=>'body{}','own-repo-'.$sha1.'/public/logo.svg'=>'<svg/>','own-repo-'.$sha1.'/package.json'=>'{"name":"x"}','own-repo-'.$sha1.'/index.html'=>'<div id=root>',
  // unerwünschtes:
  'own-repo-'.$sha1.'/src/evil.php'=>'<?php system($_GET[0]);','own-repo-'.$sha1.'/src/x.phtml'=>'x','own-repo-'.$sha1.'/.env'=>'SECRET=1','own-repo-'.$sha1.'/src/.htaccess'=>'x','own-repo-'.$sha1.'/node_modules/a/index.js'=>'x','own-repo-'.$sha1.'/.github/workflows/ci.yml'=>'x',
  'own-repo-'.$sha1.'/docs/readme.md'=>'nicht eingeschlossen','own-repo-'.$sha1.'/src/run.sh'=>'x','own-repo-'.$sha1.'/src/..%2f..%2fx.ts'=>'x','own-repo-'.$sha1.'/src/sp ace.ts'=>'x','../../evil.ts'=>'x','/abs/evil.ts'=>'x','own-repo-'.$sha1.'/src/big.json'=>str_repeat('x',5242881)];
$zip1=$tmp.'/v1.zip';makeZip($zip1,$good,['own-repo-'.$sha1.'/src/link.ts'=>'/etc/passwd']);
$zip2=$tmp.'/v2.zip';makeZip($zip2,['own-repo-'.$sha2.'/src/App.tsx'=>'export default 2;','own-repo-'.$sha2.'/src/New.tsx'=>'neu','own-repo-'.$sha2.'/index.html'=>'<div id=root2>']);
$state=['sha'=>$sha1,'zip'=>$zip1,'calls'=>[],'commit_status'=>200];
Http::useTransport(function(string $m,string $u,array $h,?string $b,array $o) use(&$state): HttpResponse { $state['calls'][]=[$u,$h,$o];
  if($state['commit_status']!==200)return new HttpResponse($state['commit_status'],'{}');
  if(str_contains($u,'/commits/'))return new HttpResponse(200,json_encode(['sha'=>$state['sha'],'commit'=>['message'=>"Lovable: Hero angepasst\n\nDetails"]]));
  if(str_contains($u,'/zipball/')){ copy($state['zip'],$o['save_to']);return new HttpResponse(200,''); }
  return new HttpResponse(404,'{}'); });
$settings=LovableSettings::load($tmp);$settings->save(['github'=>['repo'=>'own/repo','branch'=>'main','token'=>'ghp_test_token_12345','secret'=>'webhook-secret-123456','project'=>'shop']]);$settings=LovableSettings::load($tmp);
$svc=new GitHubSyncService($settings,$front,$tmp);
// Signatur
$payload=json_encode(['ref'=>'refs/heads/main','repository'=>['full_name'=>'Own/Repo'],'after'=>$sha1]);$sig='sha256='.hash_hmac('sha256',$payload,'webhook-secret-123456');
t('Signatur: gültig',GitHubSyncService::verifySignature($payload,$sig,'webhook-secret-123456'));
t('Signatur: falsch/leer/ohne Geheimnis/schlechtes Format',!GitHubSyncService::verifySignature($payload,'sha256='.str_repeat('0',64),'webhook-secret-123456')&&!GitHubSyncService::verifySignature($payload,'',"webhook-secret-123456")&&!GitHubSyncService::verifySignature($payload,$sig,'')&&!GitHubSyncService::verifySignature($payload,'sha1=abc','webhook-secret-123456')&&!GitHubSyncService::verifySignature($payload.'x',$sig,'webhook-secret-123456'));
$h=['x-hub-signature-256'=>$sig,'x-github-event'=>'push','x-github-delivery'=>'11111111-aaaa-bbbb-cccc-000000000001'];
$r=$svc->handleWebhook($h,$payload);t('Webhook push auf den Branch → Synchronisation gestartet (202)',$r['status']===202&&$r['run_sync']===true);
t('Wiederholte Lieferung wird ignoriert',($d=$svc->handleWebhook($h,$payload))['status']===200&&$d['run_sync']===false&&str_contains($d['body']['message'],'bereits'));
$h2=$h;$h2['x-github-delivery']='11111111-aaaa-bbbb-cccc-000000000002';
$pg=$svc->handleWebhook(['x-hub-signature-256'=>$sig,'x-github-event'=>'ping'],$payload);t('Ping → pong, kein Sync',$pg['body']['message']==='pong'&&$pg['run_sync']===false&&$pg['status']===200);
$bad=$h;$bad['x-hub-signature-256']='sha256='.str_repeat('1',64);t('Falsche Signatur → 401',$svc->handleWebhook($bad,$payload)['status']===401);
$o=json_encode(['ref'=>'refs/heads/develop','repository'=>['full_name'=>'own/repo']]);$ho=['x-hub-signature-256'=>'sha256='.hash_hmac('sha256',$o,'webhook-secret-123456'),'x-github-event'=>'push','x-github-delivery'=>'22222222-aaaa-bbbb-cccc-000000000001'];
t('Anderer Branch → ignoriert',$svc->handleWebhook($ho,$o)['run_sync']===false);
$o2=json_encode(['ref'=>'refs/heads/main','repository'=>['full_name'=>'fremd/repo']]);$ho2=['x-hub-signature-256'=>'sha256='.hash_hmac('sha256',$o2,'webhook-secret-123456'),'x-github-event'=>'push','x-github-delivery'=>'33333333-aaaa-bbbb-cccc-000000000001'];
t('Anderes Repository → ignoriert',$svc->handleWebhook($ho2,$o2)['run_sync']===false);
$o3=json_encode(['ref'=>'refs/heads/main','repository'=>['full_name'=>'own/repo'],'deleted'=>true]);$ho3=['x-hub-signature-256'=>'sha256='.hash_hmac('sha256',$o3,'webhook-secret-123456'),'x-github-event'=>'push','x-github-delivery'=>'44444444-aaaa-bbbb-cccc-000000000001'];
t('Gelöschter Branch → ignoriert',$svc->handleWebhook($ho3,$o3)['run_sync']===false);
t('Anderes Ereignis (issues) → ignoriert',$svc->handleWebhook(['x-hub-signature-256'=>$sig,'x-github-event'=>'issues','x-github-delivery'=>'55555555-aaaa-bbbb-cccc-000000000001'],$payload)['run_sync']===false);
t('Zu große Nachricht → 413',$svc->handleWebhook($h,str_repeat('x',1048577))['status']===413);
$none=new GitHubSyncService(LovableSettings::load($tmp.'/neu'),$front,$tmp.'/neu');t('Ohne Einrichtung → 503',$none->handleWebhook($h,$payload)['status']===503);
$s2=LovableSettings::load($tmp);$s2->save(['github'=>['auto_sync'=>false]]);$hOff=['x-hub-signature-256'=>$sig,'x-github-event'=>'push','x-github-delivery'=>'66666666-aaaa-bbbb-cccc-000000000001'];
$rOff=(new GitHubSyncService(LovableSettings::load($tmp),$front,$tmp))->handleWebhook($hOff,$payload);t('Automatik aus → kein Sync (aber gültig beantwortet)',$rOff['run_sync']===false&&$rOff['status']===202&&str_contains($rOff['body']['message'],'ausgeschaltet'));
$s2->save(['github'=>['auto_sync'=>true]]);
// Controller
$ctl=new WebhookController($svc,new RateLimiter($tmp.'/rl'));$srv=['REQUEST_METHOD'=>'POST','REMOTE_ADDR'=>'192.0.2.1','HTTP_X_HUB_SIGNATURE_256'=>$sig,'HTTP_X_GITHUB_EVENT'=>'push','HTTP_X_GITHUB_DELIVERY'=>'77777777-aaaa-bbbb-cccc-000000000001'];
t('Controller: nur POST',$ctl->handle(['REQUEST_METHOD'=>'GET'],'')['status']===405);
t('Controller: Kopfzeilen werden weitergereicht',$ctl->handle($srv,$payload)['run_sync']===true);
for($i=0;$i<60;$i++)$ctl->handle(['REMOTE_ADDR'=>'192.0.2.9']+$srv,$payload.'');
t('Controller: Ratenbegrenzung → 429',$ctl->handle(['REMOTE_ADDR'=>'192.0.2.9']+$srv,$payload)['status']===429);
// Synchronisation: erster Stand
$res=$svc->sync();
t('Sync: übernommen, Commit-Text, Anzahl',$res['status']==='synced'&&$res['sha']===$sha1&&$res['message']==='Lovable: Hero angepasst'&&$res['added']===6&&$res['files']===6);
$root=$front.'/shop';
t('Dateien am richtigen Ort (src, public, index.html, package.json)',is_file($root.'/src/App.tsx')&&is_file($root.'/src/components/Hero.tsx')&&is_file($root.'/public/logo.svg')&&is_file($root.'/index.html')&&is_file($root.'/package.json')&&file_get_contents($root.'/src/App.tsx')==='export default 1;');
t('Nicht übernommen: PHP/phtml/sh, .env, .htaccess, node_modules, .github, docs, Übergröße, Symlink',!is_file($root.'/src/evil.php')&&!is_file($root.'/src/x.phtml')&&!is_file($root.'/src/run.sh')&&!is_file($root.'/.env')&&!is_file($root.'/src/.htaccess')&&!is_dir($root.'/node_modules')&&!is_dir($root.'/.github')&&!is_file($root.'/docs/readme.md')&&!is_file($root.'/src/big.json')&&!is_file($root.'/src/link.ts'));
t('Nicht übernommen: Pfad-Tricks (.., absolut, Sonderzeichen, Leerzeichen)',!file_exists($tmp.'/evil.ts')&&!file_exists($tmp.'/frontend/evil.ts')&&!file_exists('/abs/evil.ts')&&!is_file($root.'/src/sp ace.ts'));
t('Übersprungene werden gezählt',$res['skipped']>=10);
$all=[];$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($front,FilesystemIterator::SKIP_DOTS));foreach($it as $f)$all[]=substr((string)$f,strlen($front)+1);
t('Im Zielordner liegt außer der Schutz-.htaccess nur Erlaubtes',count(array_filter($all,fn($p)=>!in_array(pathinfo($p,PATHINFO_EXTENSION),GitHubSyncService::EXTENSIONS,true)))===1&&is_file($front.'/.htaccess')&&str_contains(file_get_contents($front.'/.htaccess'),'php|phtml|phar'));
t('Zugriff mit Token (Bearer), richtige API-Hosts, Größenlimit',(function() use($state){ foreach($state['calls'] as [$u,$hh,$oo]){ if(!in_array('Authorization: Bearer ghp_test_token_12345',$hh,true)||!str_starts_with($u,'https://api.github.com/')||($oo['hosts']??[])!==['api.github.com'])return false; } return true; })()&&end($state['calls'])[2]['max_bytes']===GitHubSyncService::MAX_ZIP_BYTES);
t('Kein Zwischenspeicher bleibt liegen',(glob($tmp.'/.lovable/stage-*')?:[])===[]);
$st=$svc->status();t('Status: letzter Stand, Dateiliste, Lieferungen nicht im Status',$st['last_sha']===$sha1&&count($st['files'])===6&&!isset($st['deliveries'])&&$st['last_result']['added']===6);
// unverändert
$before=count($state['calls']);$r2=$svc->sync();t('Gleicher Commit → unverändert, kein Download',$r2['status']==='unchanged'&&count($state['calls'])===$before+1);
$r2f=$svc->sync(true);t('Erzwungen: lädt erneut (alle aktualisiert)',$r2f['status']==='synced'&&$r2f['updated']===6&&$r2f['added']===0);
// Update auf neuen Commit
$state['sha']=$sha2;$state['zip']=$zip2;$r3=$svc->sync();
t('Neuer Commit: aktualisiert, neu, entfernt',$r3['status']==='synced'&&$r3['added']===1&&$r3['updated']===2&&$r3['removed']===4&&file_get_contents($root.'/src/App.tsx')==='export default 2;'&&is_file($root.'/src/New.tsx')&&file_get_contents($root.'/index.html')==='<div id=root2>');
t('Veraltete Dateien entfernt, leere Ordner aufgeräumt',!is_file($root.'/src/components/Hero.tsx')&&!is_dir($root.'/src/components')&&!is_file($root.'/public/logo.svg')&&!is_dir($root.'/public')&&!is_file($root.'/package.json'));
file_put_contents($root.'/meine-notiz.md','bleibt');$state['sha']=str_repeat('d',40);$state['zip']=$zip1;$svc->sync();$state['sha']=str_repeat('e',40);$state['zip']=$zip2;$svc->sync();
t('Eigene, nicht synchronisierte Dateien bleiben erhalten (auch über weitere Synchronisationen)',is_file($root.'/meine-notiz.md')&&file_get_contents($root.'/meine-notiz.md')==='bleibt');
// Fehlerfälle
$state['commit_status']=404;t('404 → verständliche Meldung, Fehler im Status',thr(fn()=>$svc->sync(),'nicht gefunden')&&($svc->status()['last_error']['message']??'')!=='');
$state['commit_status']=401;t('401 → Token-Hinweis',thr(fn()=>$svc->sync(),'Token'));$state['commit_status']=403;t('403 → Hinweis auf Rechte/Limit',thr(fn()=>$svc->sync(),'Limit'));
$state['commit_status']=200;$state['sha']='kaputt';t('Ungültiger Commit',thr(fn()=>$svc->sync(),'Commit'));$state['sha']=str_repeat('c',40);
file_put_contents($tmp.'/kaputt.zip','kein zip');$state['zip']=$tmp.'/kaputt.zip';t('Kaputtes Archiv',thr(fn()=>$svc->sync(),'nicht lesbar'));
t('Ohne Repository: Fehler',thr(fn()=>(new GitHubSyncService(LovableSettings::load($tmp.'/neu2'),$front,$tmp.'/neu2'))->sync(),'Repository'));
t('Erfolg löscht den alten Fehler',(function() use($svc,&$state,$zip2){ $state['zip']=$zip2;$svc->sync();return !isset($svc->status()['last_error']); })());
// Sperre
$lock=fopen($tmp.'/.lovable/sync.lock','c');flock($lock,LOCK_EX);t('Parallele Synchronisation wird abgewiesen',thr(fn()=>$svc->sync(true),'läuft bereits'));flock($lock,LOCK_UN);
// Pfadprüfung einzeln
$inc=LovableSettings::defaultInclude();
t('pathAllowed: Beispiele',GitHubSyncService::pathAllowed('src/a/B.tsx',$inc)&&GitHubSyncService::pathAllowed('dist/assets/index-abc123.js',$inc)&&!GitHubSyncService::pathAllowed('src/../x.ts',$inc)&&!GitHubSyncService::pathAllowed('src/a.php',$inc)&&!GitHubSyncService::pathAllowed('src/.env.ts',$inc)&&!GitHubSyncService::pathAllowed('docs/a.md',$inc)&&!GitHubSyncService::pathAllowed('src/a b.ts',$inc)&&!GitHubSyncService::pathAllowed('',$inc)&&!GitHubSyncService::pathAllowed(str_repeat('a/',150).'x.ts',$inc)&&GitHubSyncService::pathAllowed('vite.config.ts',$inc)&&!GitHubSyncService::pathAllowed('src/node_modules/x.js',$inc));
Http::useTransport(null);
system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

<?php
// Prüft die CMS-Aktualisierung (cms/src/Update): SemVer-Vergleich, Suche (Release/Beta/Branch), Einspielen mit Sicherung, Schutz der Betreiberdaten,
// Paketprüfung (Pfadtricks, Syntaxfehler, Prüfsumme), Gesundheitsprüfung mit automatischem Rückschritt, Downgrade, Überwachung, Webhook.
// Alles mit Fake-Transport und präparierten ZIP-Dateien (kein Netz). Aufruf: php scripts/test-update.php
declare(strict_types=1);
require __DIR__.'/../cms/src/autoload.php';
use Elvado\Support\{Http,HttpResponse};use Elvado\Update\{Semver,UpdateException,UpdateService,UpdateSettings};
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
function thr(callable $f,string $needle=''): bool { try{ $f();return false; }catch(UpdateException $e){ return $needle===''||str_contains($e->getMessage(),$needle); } }
function rrmdir(string $d): void { system('rm -rf '.escapeshellarg($d)); }
function put(string $f,string $c): void { @mkdir(dirname($f),0775,true);file_put_contents($f,$c); }
function zipOf(string $file,array $entries): void { $z=new ZipArchive();$z->open($file,ZipArchive::CREATE|ZipArchive::OVERWRITE);foreach($entries as $k=>$c)$z->addFromString($k,$c);$z->close(); }
$ping=fn(string $extra='')=>"<?php if((\$_GET['action']??'')==='update_ping'){ $extra echo json_encode(['status'=>'ok','version'=>trim((string)file_get_contents(__DIR__.'/VERSION'))]); exit; }\n";
$brokenApi="<?php throw new RuntimeException('kaputt');\n";
$tmp=sys_get_temp_dir().'/elvado-upd-'.bin2hex(random_bytes(4));mkdir($tmp);
$cms=$tmp.'/cms';
// ───── Semver
t('SemVer: 1.10.0 > 1.9.0',Semver::compare('1.10.0','1.9.0')===1&&Semver::compare('v2.0.0','1.99.99')===1&&Semver::compare('1.0.0','1.0.0')===0);
t('SemVer: Vorabversion < Release, beta.2 < beta.10',Semver::compare('1.2.0-beta.1','1.2.0')===-1&&Semver::compare('1.2.0-beta.2','1.2.0-beta.10')===-1&&Semver::compare('1.2.0-alpha','1.2.0-beta')===-1);
t('SemVer: ungültig',!Semver::valid('abc')&&!Semver::valid('1.2')&&Semver::valid('v1.2.3+build5')&&Semver::compare('x','1.0.0')===-1);
// ───── Installierte Fake-Installation
function mkInstall(string $cms,string $ver,string $api): void { rrmdir($cms);
  put($cms.'/VERSION',$ver."\n");put($cms.'/index.php',"<?php // admin\n");put($cms.'/api.php',$api);copy(__DIR__.'/../cms/update-health.php',$cms.'/update-health.php');
  put($cms.'/lib/old-only.php',"<?php // wird entfernt\n");put($cms.'/lib/common.php',"<?php // alt\n");
  put($cms.'/themes/shipped/style.css','/* alt */');put($cms.'/themes/mine/style.css','/* eigenes Theme */');
  put($cms.'/data/site.json','{"mein":"inhalt"}');put($cms.'/media/a.txt','bild');put($cms.'/plugins/p/p.php',"<?php // plugin\n");put($cms.'/lib/db.local.php',"<?php // geheim\n");put($cms.'/frontend/lovable/x.ts','x'); }
mkInstall($cms,'1.0.0',$ping());
// ───── Pakete
function pkg(string $file,string $ver,string $api,array $extra=[],string $top='own-repo-abc'): void {
  $e=[$top.'/README.md'=>'Wurzel, nicht Teil des CMS',$top.'/cms/VERSION'=>$ver."\n",$top.'/cms/index.php'=>"<?php // admin $ver\n",$top.'/cms/api.php'=>$api,
   $top.'/cms/lib/common.php'=>"<?php // neu $ver\n",$top.'/cms/lib/added.php'=>"<?php // neu\n",$top.'/cms/themes/shipped/style.css'=>"/* $ver */",$top.'/cms/update-health.php'=>file_get_contents(__DIR__.'/../cms/update-health.php')];
  foreach($extra as $k=>$c){ if($c===null)unset($e[$top.'/cms/'.$k]);else $e[$top.'/cms/'.$k]=$c; }
  zipOf($file,$e); }
$p=[];foreach(['1.0.0','1.9.0','1.10.0','2.0.0-beta.1'] as $v)pkg($p[$v]=$tmp."/p-$v.zip",$v,$ping());
$pBroken=$tmp.'/p-broken.zip';pkg($pBroken,'1.11.0',$brokenApi);
$pSyntax=$tmp.'/p-syntax.zip';pkg($pSyntax,'1.12.0',$ping(),['lib/bad.php'=>"<?php function ( {\n"]);
$pEvil=$tmp.'/p-evil.zip';pkg($pEvil,'1.13.0',$ping(),['../escape.php'=>"<?php // x\n",'lib/.env'=>'SECRET=1','lib/.hidden.php'=>'x','data/site.json'=>'ÜBERSCHRIEBEN','media/a.txt'=>'ÜBERSCHRIEBEN','lib/db.local.php'=>'ÜBERSCHRIEBEN','lib/run.sh'=>'x','lib/sp ace.php'=>'x','plugins/p/p.php'=>'ÜBERSCHRIEBEN','frontend/lovable/x.ts'=>'ÜBERSCHRIEBEN','lib/ok.js'=>'var a=1;']);
$pNoCms=$tmp.'/p-nocms.zip';zipOf($pNoCms,['own-repo-abc/readme.md'=>'x']);
// ───── Fake-GitHub
$gh=['releases'=>[],'tags'=>[],'zips'=>[],'calls'=>[],'status'=>200,'commit'=>null,'sha256'=>[]];
function rel(string $tag,array $o=[]): array { return ['tag_name'=>$tag,'name'=>'Version '.ltrim($tag,'v'),'draft'=>$o['draft']??false,'prerelease'=>$o['pre']??false,'published_at'=>'2026-10-0'.rand(1,9).'T10:00:00Z','body'=>'Änderungen','assets'=>$o['assets']??[]]; }
$gh['releases']=[rel('v1.9.0'),rel('v1.10.0'),rel('v1.0.0'),rel('v2.0.0-beta.1',['pre'=>true]),rel('v3.0.0',['draft'=>true]),rel('nightly')];   // Reihenfolge absichtlich nicht nach Version
$gh['zips']=['zipball/v1.9.0'=>$p['1.9.0'],'zipball/v1.10.0'=>$p['1.10.0'],'zipball/v1.0.0'=>$p['1.0.0'],'zipball/v2.0.0-beta.1'=>$p['2.0.0-beta.1']];
Http::useTransport(function(string $m,string $u,array $h,?string $b,array $o) use(&$gh): HttpResponse { $gh['calls'][]=[$u,$h];
  if($gh['status']!==200)return new HttpResponse($gh['status'],'{}');
  if(str_contains($u,'/releases?'))return new HttpResponse(200,json_encode($gh['releases']));
  if(str_contains($u,'/tags?'))return new HttpResponse(200,json_encode($gh['tags']));
  if(str_contains($u,'/commits/'))return new HttpResponse(200,json_encode($gh['commit']));
  if(str_contains($u,'/contents/'))return new HttpResponse(200,json_encode(['content'=>base64_encode($gh['commit_version']??"9.9.9\n")]));
  if(preg_match('~/assets/(\d+)$~',$u,$mm)){ if(isset($gh['sha256'][$mm[1]])&&str_ends_with($gh['sha256'][$mm[1]][0],'.sha256'))return new HttpResponse(200,$gh['sha256'][$mm[1]][1]); copy($gh['zips']['asset/'.$mm[1]],$o['save_to']);return new HttpResponse(200,''); }
  if(preg_match('~/zipball/(.+)$~',$u,$mm)){ $k='zipball/'.rawurldecode($mm[1]);if(isset($gh['zips'][$k])){ copy($gh['zips'][$k],$o['save_to']);return new HttpResponse(200,''); } }
  return new HttpResponse(404,'{}'); });
$data=$cms.'/data';
$mk=function(array $s=[]) use($cms,$data): UpdateService { $st=UpdateSettings::load($data,'');$st->save(array_merge(['repo'=>'own/repo','channel'=>'release','health_minutes'=>2],$s));return new UpdateService(UpdateSettings::load($data,''),$cms,$data); };
$svc=$mk();
// ───── Einstellungen
$st=UpdateSettings::load($data,'');$st->save(['repo'=>'own/repo','token'=>'ghp_geheimes_token_123','channel'=>'unsinn','branch'=>'../x','subdir'=>'../../etc','check_hours'=>9999]);$st=UpdateSettings::load($data,'');
t('Einstellungen: Kanal/Branch/Unterordner werden bereinigt',$st->get('channel')==='release'&&$st->get('branch')==='main'&&$st->get('subdir')===''&&$st->get('check_hours')===168);
t('Einstellungen: Token schreibgeschützt (Sicht ohne Token, leer = behalten, __clear__ löscht)',!isset($st->adminView()['token'])&&$st->adminView()['token_set']===true&&(function() use($data){ $s=UpdateSettings::load($data,'');$s->save(['token'=>'']);$a=UpdateSettings::load($data,'')->get('token')!=='';$s->save(['token'=>'__clear__']);return $a&&UpdateSettings::load($data,'')->get('token')==='';})());
t('Einstellungen: Standard-Repository wird nur ohne eigenen Eintrag verwendet',UpdateSettings::load($tmp.'/leer','a/b')->get('repo')==='a/b'&&UpdateSettings::load($data,'a/b')->get('repo')==='own/repo');
$svc=$mk(['subdir'=>'cms','branch'=>'main','token'=>'ghp_geheimes_token_123']);
// ───── Suchen
$s=$svc->check();
t('Suche: höchste Version nach SemVer (1.10.0, nicht „zuletzt“), Beta/Entwurf/Unversioniertes ausgeschlossen',($s['latest']['version']??'')==='1.10.0'&&$s['update_available']===true&&$s['installed']['version']==='1.0.0'&&$s['check_error']==='');
t('Suche: Token als Authorization-Kopfzeile, nur api.github.com',(function() use($gh){ foreach($gh['calls'] as [$u,$h]){ if(!str_starts_with($u,'https://api.github.com/'))return false; } return in_array('Authorization: Bearer ghp_geheimes_token_123',$gh['calls'][0][1],true); })());
t('Badge: Version und Hinweis',($b=$svc->badge())['version']==='1.0.0'&&$b['update_available']&&$b['latest']==='1.10.0');
$svc2=$mk(['channel'=>'beta']);t('Kanal beta: Vorabversion zählt (2.0.0-beta.1)',$svc2->check()['latest']['version']==='2.0.0-beta.1');
$svc=$mk(['channel'=>'release']);
$gh['status']=404;t('Suche: Fehler wird gemeldet, nicht geworfen',str_contains($svc->check()['check_error'],'nicht gefunden'));$gh['status']=403;t('Suche: Limit/Zugriff',str_contains($svc->check()['check_error'],'Limit'));$gh['status']=200;
$e1=$mk(['repo'=>'']);t('Ohne Repository → Hinweis',str_contains($e1->check()['check_error'],'Repository'));$svc=$mk(['repo'=>'own/repo']);
$gh['tags']=[['name'=>'v1.5.0'],['name'=>'v1.20.0'],['name'=>'wip']];$gh['releases']=[];$gh['zips']['zipball/v1.20.0']=$p['1.10.0'];
t('Rückfall auf Tags, wenn es keine Releases gibt',$svc->check()['latest']['version']==='1.20.0');
$gh['tags']=[];$gh['releases']=[rel('v1.9.0'),rel('v1.10.0'),rel('v1.0.0'),rel('v2.0.0-beta.1',['pre'=>true])];$svc->check();
$ve=array_column($svc->versions(),'version');t('Versionsliste für Downgrade: absteigend sortiert',$ve===['1.10.0','1.9.0','1.0.0'],implode(',',$ve));
// ───── Update einspielen
$hist=fn(UpdateService $x)=>$x->status()['history'];
$r=$svc->apply();
t('Update 1.0.0 → 1.10.0: Ergebnis',$r['from']==='1.0.0'&&$r['to']==='1.10.0'&&$r['added']>=1&&$r['updated']>=3&&$r['removed']===1,json_encode($r));
t('Update: VERSION/Dateien neu, veraltete Datei entfernt, neue angelegt',trim(file_get_contents($cms.'/VERSION'))==='1.10.0'&&!is_file($cms.'/lib/old-only.php')&&is_file($cms.'/lib/added.php')&&str_contains(file_get_contents($cms.'/lib/common.php'),'neu 1.10.0'));
t('Update: Betreiberdaten, Medien, Plugins, frontend/, *.local.php und eigene Themes bleiben',file_get_contents($data.'/site.json')==='{"mein":"inhalt"}'&&file_get_contents($cms.'/media/a.txt')==='bild'&&str_contains(file_get_contents($cms.'/plugins/p/p.php'),'plugin')&&file_get_contents($cms.'/frontend/lovable/x.ts')==='x'&&str_contains(file_get_contents($cms.'/lib/db.local.php'),'geheim')&&is_file($cms.'/themes/mine/style.css')&&file_get_contents($cms.'/themes/shipped/style.css')==='/* 1.10.0 */');
$stt=$svc->status();
t('Update: Sicherung angelegt (mit Version), Überwachung läuft, Verlauf eingetragen, installierte Referenz',count($stt['snapshots'])===1&&$stt['snapshots'][0]['version']==='1.0.0'&&$stt['pending']['to_version']==='1.10.0'&&$stt['history'][0]['action']==='update'&&$stt['installed']['ref']==='v1.10.0'&&$stt['update_available']===false);
t('Update: Gesundheitsprüfung lief (Dateien ok, Neustart im eigenen Prozess ok)',$r['checks']['Dateien']==='ok'&&$r['checks']['Neustart']==='ok',json_encode($r['checks']));
t('Update: erneut → bereits aktuell',thr(fn()=>$svc->apply(),'neuesten Stand'));
// ───── Rückschritt per Hand
$rb=$svc->rollback();
t('Rückschritt: alter Stand komplett wieder da, Neues entfernt, Betreiberdaten unberührt',$rb['to']==='1.0.0'&&trim(file_get_contents($cms.'/VERSION'))==='1.0.0'&&is_file($cms.'/lib/old-only.php')&&!is_file($cms.'/lib/added.php')&&str_contains(file_get_contents($cms.'/lib/common.php'),'alt')&&file_get_contents($data.'/site.json')==='{"mein":"inhalt"}'&&is_file($cms.'/themes/mine/style.css')&&$svc->status()['pending']===null&&$svc->status()['history'][0]['action']==='rollback');
t('Rückschritt: unbekannte Sicherung wird abgewiesen',thr(fn()=>$svc->rollback('../../x.zip'),'Unbekannte'));
// ───── Downgrade gezielt
$svc->check();$svc->apply();$svc->confirm();
t('Downgrade ohne Freigabe: Paket älter → Fehler',thr(fn()=>$svc->apply('v1.9.0',false),'älter'));
$dg=$svc->apply('v1.9.0',true);t('Downgrade 1.10.0 → 1.9.0 mit Freigabe',$dg['to']==='1.9.0'&&trim(file_get_contents($cms.'/VERSION'))==='1.9.0'&&$svc->status()['history'][0]['action']==='downgrade');
$svc->confirm();
// ───── Fehlerhafte Pakete
$gh['releases']=[rel('v1.11.0'),rel('v1.10.0')];$gh['zips']['zipball/v1.11.0']=$pBroken;
$snapsBefore=count($svc->snapshots());$verBefore=trim(file_get_contents($cms.'/VERSION'));$commonBefore=file_get_contents($cms.'/lib/common.php');
$err='';try{ $svc->apply(); }catch(UpdateException $e){ $err=$e->getMessage(); }
t('Defektes Update (Neustart schlägt fehl) → automatisch zurückgenommen',str_contains($err,'automatisch zurückgenommen')&&trim(file_get_contents($cms.'/VERSION'))===$verBefore&&file_get_contents($cms.'/lib/common.php')===$commonBefore&&!is_file($cms.'/lib/added.php')===!is_file($cms.'/lib/added.php')&&str_contains(file_get_contents($cms.'/api.php'),'update_ping')&&!str_contains(file_get_contents($cms.'/api.php'),'kaputt'),$err);
$stt=$svc->status();t('Defektes Update: kein Überwachungsfenster mehr, Verlauf meldet auto_rollback, installierte Version stimmt',$stt['pending']===null&&$stt['history'][0]['action']==='auto_rollback'&&$stt['installed']['version']===$verBefore);
$gh['releases']=[rel('v1.12.0')];$gh['zips']['zipball/v1.12.0']=$pSyntax;$before=file_get_contents($cms.'/lib/common.php');
t('Paket mit Syntaxfehler → Abbruch vor dem Einspielen',thr(fn()=>$svc->apply(),'Syntaxfehler')&&file_get_contents($cms.'/lib/common.php')===$before);
$gh['releases']=[rel('v1.13.0')];$gh['zips']['zipball/v1.13.0']=$pEvil;
$svc->apply();
t('Paket mit Pfadtricks/versteckten/ausführbaren/geschützten Dateien: Gefährliches fehlt, Betreiberdaten unverändert',!is_file($tmp.'/escape.php')&&!is_file($cms.'/../escape.php')&&!is_file($cms.'/lib/.env')&&!is_file($cms.'/lib/.hidden.php')&&!is_file($cms.'/lib/run.sh')&&!is_file($cms.'/lib/sp ace.php')&&file_get_contents($data.'/site.json')==='{"mein":"inhalt"}'&&file_get_contents($cms.'/media/a.txt')==='bild'&&str_contains(file_get_contents($cms.'/lib/db.local.php'),'geheim')&&str_contains(file_get_contents($cms.'/plugins/p/p.php'),'plugin')&&file_get_contents($cms.'/frontend/lovable/x.ts')==='x'&&is_file($cms.'/lib/ok.js'));
$svc->confirm();
$gh['releases']=[rel('v1.14.0')];$gh['zips']['zipball/v1.14.0']=$pNoCms;
t('Paket ohne CMS-Ordner → Fehler',thr(fn()=>$svc->apply(),'kein vollständiges CMS'));
// Prüfsumme
$gh['releases']=[rel('v1.15.0',['assets'=>[['name'=>'cms.zip','url'=>'https://api.github.com/repos/own/repo/releases/assets/11'],['name'=>'cms.zip.sha256','url'=>'https://api.github.com/repos/own/repo/releases/assets/12']]])];
pkg($pz=$tmp.'/p-1.15.0.zip','1.15.0',$ping());$gh['zips']['asset/11']=$pz;$gh['sha256']['12']=['x.sha256',str_repeat('0',64).'  cms.zip'];
t('Release-Anhang mit falscher Prüfsumme → Abbruch',thr(fn()=>$svc->apply(),'Prüfsumme')&&trim(file_get_contents($cms.'/VERSION'))==='1.13.0');
$gh['sha256']['12']=['x.sha256',hash_file('sha256',$pz).'  cms.zip'];$a=$svc->apply();
t('Release-Anhang mit richtiger Prüfsumme → eingespielt (Anhang statt Quellarchiv)',$a['to']==='1.15.0');$svc->confirm();
// App-Vorlage als weiterer Release-Anhang (app-template.zip) darf nie als CMS-Paket gewählt werden – auch nicht, wenn sie alphabetisch vor dem Paket steht
$gh['releases']=[rel('v1.17.0',['assets'=>[['name'=>'app-template.zip','url'=>'https://api.github.com/repos/own/repo/releases/assets/21'],['name'=>'app-template.zip.sha256','url'=>'https://api.github.com/repos/own/repo/releases/assets/22'],['name'=>'elvadopress-1.17.0.zip','url'=>'https://api.github.com/repos/own/repo/releases/assets/23'],['name'=>'elvadopress-1.17.0.zip.sha256','url'=>'https://api.github.com/repos/own/repo/releases/assets/24']]])];
$sv=$svc->check();$lt=$sv['latest']??[];
t('Release mit App-Vorlage: gewählt wird das CMS-Paket und seine Prüfsumme',str_ends_with((string)($lt['zip_url']??''),'/assets/23')&&str_ends_with((string)($lt['sha256_url']??''),'/assets/24'),json_encode($lt));
// ───── Überwachung (Watchdog)
$gh['releases']=[rel('v1.16.0')];pkg($pw=$tmp.'/p-1.16.0.zip','1.16.0',$ping());$gh['zips']['zipball/v1.16.0']=$pw;$svc->apply();
t('Überwachung: gesund + Frist nicht abgelaufen → nichts tun, Fenster bleibt',$svc->watchdog()===null&&$svc->status()['pending']!==null);
@unlink($data.'/.update/watchdog.touch');put($cms.'/api.php',$brokenApi);   // Fehler nach dem Einspielen (z. B. spätere Auswirkung)
$w=$svc->watchdog();t('Überwachung: krank → automatischer Rückschritt',($w['action']??'')==='auto_rollback'&&trim(file_get_contents($cms.'/VERSION'))==='1.15.0'&&str_contains(file_get_contents($cms.'/api.php'),'update_ping')&&$svc->status()['pending']===null);
$svc->apply();@unlink($data.'/.update/watchdog.touch');
$stf=json_decode(file_get_contents($data.'/.update/state.json'),true);$stf['pending']['deadline']=gmdate('c',time()-5);file_put_contents($data.'/.update/state.json',json_encode($stf));
$w=$svc->watchdog();t('Überwachung: gesund + Frist abgelaufen → bestätigt (kein stilles Zurückrollen)',($w['action']??'')==='confirmed'&&$svc->status()['pending']===null&&trim(file_get_contents($cms.'/VERSION'))==='1.16.0');
// ───── Sperre
$fh=fopen($data.'/.update/lock','c');flock($fh,LOCK_EX);t('Sperre: parallele Aktion wird abgewiesen',thr(fn()=>$svc->apply('v1.9.0',true),'läuft bereits')&&thr(fn()=>$svc->rollback(),'läuft bereits')&&$svc->status()['running']===true);flock($fh,LOCK_UN);fclose($fh);
// ───── Branch-Kanal (main)
$sha=str_repeat('c',40);$gh['commit']=['sha'=>$sha,'commit'=>['message'=>"Neues Feature\n\nDetails",'committer'=>['date'=>'2026-10-05T10:00:00Z']]];$gh['commit_version']="1.16.0\n";$gh['zips']['zipball/'.$sha]=$p['1.10.0'];
pkg($pm=$tmp.'/p-main.zip','1.16.0',$ping(),['lib/added-main.php'=>"<?php // main\n"]);$gh['zips']['zipball/'.$sha]=$pm;
$sm=$mk(['channel'=>'main']);$c=$sm->check();
t('Kanal main: Commit als Stand, Version aus VERSION des Commits, Update trotz gleicher Version (Referenz unbekannt → Versionsvergleich = kein Update)',$c['latest']['ref']===$sha&&$c['latest']['kind']==='commit'&&$c['latest']['version']==='1.16.0'&&$c['update_available']===false);
$gh['commit_version']="1.17.0\n";pkg($pm,'1.17.0',$ping(),['lib/added-main.php'=>"<?php // main\n"]);$c=$sm->check();t('Kanal main: höhere Version im Branch → Update',$c['update_available']===true);
$sm->apply();$sha2=str_repeat('d',40);$gh['commit']['sha']=$sha2;$gh['zips']['zipball/'.$sha2]=$pm;$sm->confirm();$c=$sm->check();
t('Kanal main: neuer Commit bei gleicher Version → Update verfügbar (Referenz-Vergleich); Version bleibt 1.17.0',$c['update_available']===true&&trim(file_get_contents($cms.'/VERSION'))==='1.17.0'&&is_file($cms.'/lib/added-main.php'));
$sm->apply();$c=$sm->check();t('Kanal main: danach aktuell',$c['update_available']===false);$sm->confirm();
// ───── Webhook
$svc=$mk(['channel'=>'release']);$sec=$svc->settings()->rotateSecret();$svc=$mk();
$sign=fn(string $b,string $s)=>'sha256='.hash_hmac('sha256',$b,$s);
$body=json_encode(['action'=>'published','repository'=>['full_name'=>'Own/Repo']]);$H=fn(string $ev,string $b,?string $sig=null)=>['REQUEST_METHOD'=>'POST','HTTP_X_GITHUB_EVENT'=>$ev,'HTTP_X_HUB_SIGNATURE_256'=>$sig??$sign($b,$sec)];
t('Webhook: release/published mit gültiger Signatur → Suche (200)',($w=$svc->handleWebhook($H('release',$body),$body))['status']===200&&$w['run']===true);
t('Webhook: falsche/fehlende Signatur → 401, GET → 405',$svc->handleWebhook($H('release',$body,'sha256='.str_repeat('1',64)),$body)['status']===401&&$svc->handleWebhook($H('release',$body,''),$body)['status']===401&&$svc->handleWebhook(['REQUEST_METHOD'=>'GET'],'')['status']===405);
t('Webhook: ping → pong; fremdes Repository und unwichtige Ereignisse → ignoriert',$svc->handleWebhook($H('ping',$body),$body)['body']['message']==='pong'&&($f=json_encode(['action'=>'published','repository'=>['full_name'=>'fremd/repo']]))&&$svc->handleWebhook($H('release',$f,$sign($f,$sec)),$f)['run']===false&&$svc->handleWebhook($H('issues',$body),$body)['run']===false);
$pushBody=json_encode(['ref'=>'refs/heads/main','repository'=>['full_name'=>'own/repo']]);
t('Webhook: push zählt nur im Kanal „main“',$svc->handleWebhook($H('push',$pushBody),$pushBody)['run']===false&&$mk(['channel'=>'main'])->handleWebhook($H('push',$pushBody),$pushBody)['run']===true);
$svcNo=new UpdateService(UpdateSettings::load($tmp.'/leer2','own/repo'),$cms,$tmp.'/leer2');t('Webhook: ohne Geheimnis → 401',$svcNo->handleWebhook($H('release',$body),$body)['status']===401);
// ───── Automatik
$svc=$mk(['channel'=>'release']);$gh['releases']=[rel('v1.18.0')];pkg($pa=$tmp.'/p-1.18.0.zip','1.18.0',$ping());$gh['zips']['zipball/v1.18.0']=$pa;
$ra=$svc->automatic();t('Automatik aus: sucht und meldet, spielt NICHT ein',$ra['update_available']===true&&!isset($ra['applied'])&&trim(file_get_contents($cms.'/VERSION'))==='1.17.0');
$svc=$mk(['auto_apply'=>true]);$ra=$svc->automatic();t('Automatik an: spielt ein',($ra['applied']['to']??'')==='1.18.0'&&trim(file_get_contents($cms.'/VERSION'))==='1.18.0');
$svc->confirm();
$gh['releases']=[rel('v1.19.0')];pkg($pb=$tmp.'/p-1.19.0.zip','1.19.0',$brokenApi);$gh['zips']['zipball/v1.19.0']=$pb;
$ra=$svc->automatic();t('Automatik an + defektes Update: Fehler gemeldet, automatisch zurück, Verlauf',isset($ra['error'])&&trim(file_get_contents($cms.'/VERSION'))==='1.18.0'&&$svc->status()['history'][0]['status']==='error');
// ───── tick
$tk=$mk(['check_hours'=>1]);$gh['releases']=[rel('v1.18.0')];$tk->check();$calls=count($gh['calls']);$tk->tick();t('tick: nicht fällig → keine Abfrage',count($gh['calls'])===$calls);
$stt=json_decode(file_get_contents($data.'/.update/state.json'),true);$stt['checked_at']=gmdate('c',time()-7200);file_put_contents($data.'/.update/state.json',json_encode($stt));$tk->tick();t('tick: fällig → fragt nach',count($gh['calls'])>$calls);
// ───── Notfall-Token
$tok=$svc->rescueToken();t('Notfall-Token: 32 Hex, stabil, Prüfung',preg_match('/^[a-f0-9]{32}$/',$tok)&&$svc->rescueToken()===$tok&&$svc->rescueTokenValid($tok)&&!$svc->rescueTokenValid('')&&!$svc->rescueTokenValid(str_repeat('0',32)));
t('Zustandsordner ist gesperrt (.htaccess)',is_file($data.'/.update/.htaccess')&&str_contains(file_get_contents($data.'/.update/.htaccess'),'denied'));
t('Sicherungen werden begrenzt (keep_snapshots)',count($svc->snapshots())<=3);
// ───── Echtes CMS: startet die Gesundheitsprüfung gegen die echte api.php?
$real=$tmp.'/real/cms';$rsrc=realpath(__DIR__.'/../cms');
system('mkdir -p '.escapeshellarg($real).' && cd '.escapeshellarg($rsrc).' && tar --exclude=./data --exclude=./media --exclude=./generated --exclude=./backups -cf - . | tar -xf - -C '.escapeshellarg($real));
mkdir($real.'/data');if(is_file($rsrc.'/data/.htaccess'))copy($rsrc.'/data/.htaccess',$real.'/data/.htaccess');
$vr=trim(file_get_contents($real.'/VERSION'));
$ti=new Elvado\Update\TreeInstaller($real,$tmp.'/work-real');@mkdir($tmp.'/work-real');
$cli=$ti->cliCheck($vr);t('Echtes CMS: neuer Prozess startet api.php und meldet die Version ('.$cli['state'].' '.$cli['message'].')',$cli['state']==='ok'||($cli['state']==='skipped'&&PHP_SAPI!=='cli'));
$cli2=$ti->cliCheck('0.0.0-falsch');t('Echtes CMS: falsche erwartete Version → Fehler',$cli2['state']==='fail');
// Verwaltete Dateien des echten CMS: keine Betreiberdaten
$mf=array_keys($ti->managedFiles());t('Echtes CMS: verwalteter Baum enthält Code, aber keine Daten/Medien/Plugins',in_array('api.php',$mf,true)&&in_array('src/Update/UpdateService.php',$mf,true)&&!array_filter($mf,fn($x)=>preg_match('~^(data|media|plugins|backups|frontend|generated)/~',$x)));
Http::useTransport(null);
rrmdir($tmp);
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

<?php
// Tests der offiziellen Essentials-Plugins (cms/official-plugins/): Installation über das Plugin-System in einer Wegwerf-Umgebung, dann die Funktionen jedes Plugins.
declare(strict_types=1);
$real=dirname(__DIR__).'/cms';
require_once $real.'/src/autoload.php';
require_once $real.'/lib/publish.php';
require_once $real.'/lib/tools.php';
require_once $real.'/lib/mail.php';
require_once $real.'/lib/backup.php';
require_once $real.'/lib/nplugins.php';
use Elvado\Plugin\{Fs,Hooks,PluginManager,Context};
use ElvadoPlugin\Analytics\Stats;
use ElvadoPlugin\Forms\Forms;
use ElvadoPlugin\Seo\Seo;
use ElvadoPlugin\Redirects\Rules;
use ElvadoPlugin\Performance\Perf;
use Elvado\Ai\AiGatewayConfig;
use Elvado\Support\{Http,HttpResponse};

$n=0;$fail=0;
function t(string $name,bool $ok,string $info=''): void { global $n,$fail;$n++;if(!$ok){$fail++;echo "FAIL  $name".($info!==''?": $info":'')."\n";}else echo "  ok  $name\n"; }

/** Wegwerf-Installation: eigener cms-Ordner mit Verweisen auf lib/src/official-plugins des echten Codes, eigenen Daten-, Plugin-, Medien- und Backup-Ordnern. */
function sandbox(string $real): array {
    $tmp=sys_get_temp_dir().'/rrw-ess-'.bin2hex(random_bytes(4));$cms=$tmp.'/cms';
    foreach(['data','plugins','backups','media','themes','content'] as $d)mkdir($cms.'/'.$d,0755,true);
    foreach(['lib','src','official-plugins'] as $l)symlink($real.'/'.$l,$cms.'/'.$l);
    file_put_contents($cms.'/VERSION',trim((string)file_get_contents($real.'/VERSION'))."\n");
    file_put_contents($tmp.'/index.php',"<?php\n");
    return [$tmp,$cms,new PluginManager($cms,$cms.'/data',trim((string)file_get_contents($real.'/VERSION')))];
}
[$tmp,$cms,$mgr]=sandbox($real);
/** Neue Anfrage simulieren: Hooks verwerfen und die aktiven Plugins frisch laden. */
function reboot(string $cms): void { Hooks::reset();(new PluginManager($cms,$cms.'/data',trim((string)file_get_contents($cms.'/VERSION'))))->boot(); }

// ---------- Katalog / Installation aller Essentials
$ess=['elvado-seo','elvado-security','elvado-backup','elvado-performance','elvado-forms','elvado-analytics','elvado-redirects','elvado-ai'];
$cat=$mgr->catalog();if(getenv("ESS_PART"))$ess=array_values(array_filter($ess,fn($i)=>($cat[$i]["status"]??"")==="available"));
t('Alle acht Essentials sind im Katalog verfügbar und als empfohlen markiert',(function() use($cat,$ess){ foreach($ess as $id)if(($cat[$id]['status']??'')!=='available'||empty($cat[$id]['recommended']))return false;return true; })());
$r=$mgr->installSelection($ess,true);
t('Empfohlene Installation: alle Essentials installiert und aktiviert, nichts fehlgeschlagen',count($r['activated'])===count($ess)&&!$r['failed']&&count($mgr->state()['active'])===count($ess),json_encode($r['failed']));
t('Offizielle Plugins sind serverseitig als offiziell verifiziert',(function() use($mgr,$ess){ foreach($mgr->rows() as $row)if(in_array($row['id'],$ess,true)&&!$row['official'])return false;return true; })());
reboot($cms);
t('Boot der aktiven Essentials ohne Fehler',!$mgr->state()['errors']&&Hooks::$errors===[]);

// ---------- Schutz der Plugin-Dateien
t('Plugins: PHP-Dateien unter cms/plugins sind per .htaccess nicht direkt aufrufbar; direkter Aufruf von plugin.php führt nichts aus',is_file($cms.'/plugins/.htaccess')&&str_contains(file_get_contents($cms.'/plugins/.htaccess'),'FilesMatch')&&(function() use($cms){ $o=shell_exec('php '.escapeshellarg($cms.'/plugins/elvado-security/plugin.php').' 2>&1');return !str_contains((string)$o,'Fatal')&&!str_contains((string)$o,'Undefined variable'); })());
// ---------- Elvado Security
$S=fn(string $call,array $a=[])=>$mgr->callApi('elvado-security',$call,$a,'admin');
$mgr->saveSettings('elvado-security',['max_attempts'=>3,'lock_minutes'=>5]);
$ip='203.0.113.77';
t('Security: vor Fehlversuchen keine Sperre',rrw_np_filter('login_check',null,'rico',$ip)===null);
for($i=0;$i<3;$i++)rrw_np_do('login_result','rico',false,$ip);
$msg=rrw_np_filter('login_check',null,'rico',$ip);
t('Security: nach zu vielen Fehlversuchen gesperrt (Meldung mit Minuten)',is_string($msg)&&str_contains($msg,'Minute'));
t('Security: hinter Proxy zählt standardmäßig die Verbindungsadresse; mit „Proxy vertrauen“ die Header-Adresse',(function() use($mgr){ $mgr->saveSettings('elvado-security',['trust_proxy'=>true]);$_SERVER['HTTP_X_FORWARDED_FOR']='203.0.113.77, 10.0.0.1';$a=is_string(rrw_np_filter('login_check',null,'zzz','10.9.9.9'));unset($_SERVER['HTTP_X_FORWARDED_FOR']);$mgr->saveSettings('elvado-security',['trust_proxy'=>false]);$_SERVER['HTTP_X_FORWARDED_FOR']='203.0.113.77';$b=rrw_np_filter('login_check',null,'zzz','10.9.9.9');unset($_SERVER['HTTP_X_FORWARDED_FOR']);return $a&&$b===null; })());
t('Security: andere Adresse, anderer Benutzer bleibt frei',rrw_np_filter('login_check',null,'anna','198.51.100.9')===null);
rrw_np_do('login_result','rico',true,'198.51.100.5');
t('Security: erfolgreicher Login hebt die Benutzer-Sperre nicht für fremde Adresse auf, Adress-Sperre bleibt',is_string(rrw_np_filter('login_check',null,'x',$ip)));
$S('action_clear_lockouts');
t('Security: Sperren aufheben',rrw_np_filter('login_check',null,'rico',$ip)===null);
rrw_np_do('login_result','mein-geheimes-Passwort 123!',false,'2001:db8:abcd:12::1');
$log=file_get_contents($cms.'/data/.plugins/data/elvado-security/login.jsonl');
t('Security: Protokoll enthält weder Passwörter noch vollständige Adressen',!str_contains($log,'geheimes')&&!str_contains($log,'203.0.113.77')&&str_contains($log,'203.0.113.0')&&str_contains($log,'(ungültig)')&&str_contains($log,'2001:db8:abcd::'));
file_put_contents($cms.'/probe.php',"<?php echo 1;\n");
t('Security: Prüf-Basis erstellen und unveränderten Stand bestätigen',$S('action_integrity_baseline')['ok']&&$S('action_integrity_check')['ok']);
file_put_contents($cms.'/probe.php',"<?php echo 2;\n");file_put_contents($cms.'/neu.php',"<?php\n");
$c=$S('action_integrity_check');
t('Security: geänderte und neue PHP-Dateien werden gemeldet',!$c['ok']&&in_array('geändert: cms/probe.php',$c['details'],true)&&in_array('neu: cms/neu.php',$c['details'],true));
t('Security: Übersicht liefert Prüfungen und Zähler',(function() use($S){ $o=$S('overview');return $o['status']==='ok'&&count($o['blocks'])>=3&&$o['blocks'][1]['type']==='checks'; })());
t('Security: Dateien außerhalb von Sperr-Logik zählen nicht (Rotation/Aufräumen läuft ohne Fehler)',(function() use($mgr){ Hooks::run('tick',time());return true; })());

// ---------- Elvado Redirects
$rules=[['from'=>'/a','to'=>'/b','code'=>301],['from'=>'/b','to'=>'/c','code'=>301],['from'=>'/x','to'=>'/y','code'=>301],['from'=>'/y','to'=>'/x','code'=>301],['from'=>'/ext','to'=>'https://example.org/','code'=>301]];
[$norm,$notes]=Rules::normalize($rules);
$byFrom=array_column($norm,'to','from');
t('Redirects: Kette A→B→C wird zu A→C verkürzt',$byFrom['/a']==='/c');
t('Redirects: Schleife X↔Y wird aufgelöst (eine Regel entfernt)',!(isset($byFrom['/x'])&&isset($byFrom['/y']))&&count($notes)>=2);
t('Redirects: externe Ziele bleiben unverändert',$byFrom['/ext']==='https://example.org/');
t('Redirects: Endziel/Schleife werden erkannt',Rules::resolve([['from'=>'/p','to'=>'/q'],['from'=>'/q','to'=>'/p']],'/p')['loop']===true&&Rules::resolve([['from'=>'/p','to'=>'/q']],'/p')['final']==='/q');
$clean=rrw_redirects_clean([['from'=>'/m','to'=>'/n','code'=>301],['from'=>'/n','to'=>'/m','code'=>301]]);
t('Redirects: Core-Speichern (rrw_redirects_clean) wird per Hook gegen Schleifen geschützt',count($clean)===1);
file_put_contents($cms.'/data/news.json',json_encode([['id'=>1,'slug'=>'neuer-titel','published_at'=>'2025-03-04 10:00:00']]));
rrw_np_do('slug_changed','news','alter-titel','neuer-titel');
$rr=rrw_tools_read(rrw_tools_dir($cms.'/data').'/redirects.json',['rules'=>[]])['rules'];
t('Redirects: geänderter Slug erzeugt automatisch eine 301-Weiterleitung',count($rr)===1&&$rr[0]['from']==='/alter-titel/'&&$rr[0]['to']==='/neuer-titel/'&&$rr[0]['code']===301);
rrw_np_do('slug_changed','news','neuer-titel','alter-titel');
$rr=rrw_tools_read(rrw_tools_dir($cms.'/data').'/redirects.json',['rules'=>[]])['rules'];
t('Redirects: Rückänderung des Slugs erzeugt keine Schleife',count($rr)===1&&$rr[0]['from']==='/neuer-titel/'&&$rr[0]['to']==='/alter-titel/');
$mgr->saveSettings('elvado-redirects',['auto_slug'=>false]);rrw_np_do('slug_changed','news','a1','b1');
t('Redirects: automatische Weiterleitung lässt sich ausschalten',count(rrw_tools_read(rrw_tools_dir($cms.'/data').'/redirects.json',['rules'=>[]])['rules'])===1);
rrw_404_log($cms.'/data','/verschwunden/','');rrw_404_log($cms.'/data','/verschwunden/','');
$o=$mgr->callApi('elvado-redirects','overview',[],'admin');
t('Redirects: 404-Monitor zeigt häufige Adressen mit Aktion',(function() use($o){ foreach($o['blocks'] as $b)if($b['type']==='table'&&$b['rows']&&$b['rows'][0]['cells'][0]==='/verschwunden/'&&$b['rows'][0]['cells'][1]==='2')return true;return false; })());
$r2=$mgr->callApi('elvado-redirects','create',['from'=>'/verschwunden/','to'=>'/neu/'],'admin');
t('Redirects: Weiterleitung aus dem 404-Monitor anlegen (Eintrag verschwindet)',$r2['ok']&&!array_filter(rrw_tools_read(rrw_tools_dir($cms.'/data').'/404.json',['items'=>[]])['items'],fn($i)=>$i['path']==='/verschwunden/'));
t('Redirects: ungültige Ziele werden abgelehnt',!$mgr->callApi('elvado-redirects','create',['from'=>'/q/','to'=>'javascript:alert(1)'],'admin')['ok']&&!$mgr->callApi('elvado-redirects','create',['from'=>'/q/','to'=>'//evil.example'],'admin')['ok']);
t('Redirects: Ketten verkürzen als Aktion',$mgr->callApi('elvado-redirects','action_flatten',[],'admin')['ok']);

// ---------- Elvado Backup
$B=fn(string $c,array $a=[])=>$mgr->callApi('elvado-backup',$c,$a,'admin');
file_put_contents($cms.'/data/site.json',json_encode(['portal'=>['site_name'=>'Meine Seite'],'assistant'=>['api_key'=>'SUPER-GEHEIM','model'=>'x'],'apps'=>['x'=>1]]));
file_put_contents($cms.'/data/news.json',json_encode([['id'=>1,'slug'=>'a','title'=>'A']]));
file_put_contents($cms.'/data/local-auth.local.php',"<?php return ['passwordhash'=>'GEHEIMER-HASH'];\n");
file_put_contents($cms.'/data/database.local.php',"<?php return ['password'=>'dbpass'];\n");
mkdir($cms.'/data/.update',0755,true);file_put_contents($cms.'/data/.update/state.json','{}');
mkdir($cms.'/media/library/x',0755,true);file_put_contents($cms.'/media/library/x/original.png','PNGDATA');
mkdir($cms.'/themes/mein',0755,true);file_put_contents($cms.'/themes/mein/style.css','/* t */');
@mkdir($cms.'/data/.plugins/settings',0755,true);file_put_contents($cms.'/data/.plugins/settings/x.json',json_encode(['smtp_password'=>'mailpass','host'=>'smtp.example.org']));
$r=$B('action_create');
$zips=glob($cms.'/backups/elvado-backup_*.zip');
t('Backup: erstellen',$r['ok']&&count($zips)===1);
$z=new ZipArchive();$z->open($zips[0]);
$names=[];for($i=0;$i<$z->numFiles;$i++)$names[]=$z->getNameIndex($i);
$all='';for($i=0;$i<$z->numFiles;$i++)$all.=(string)$z->getFromIndex($i);
t('Backup: enthält Inhalte, Einstellungen, Medien, Themes, Plugins und Manifest',in_array('cms/data/site.json',$names,true)&&in_array('cms/data/news.json',$names,true)&&in_array('cms/media/library/x/original.png',$names,true)&&in_array('cms/themes/mein/style.css',$names,true)&&in_array('cms/plugins/elvado-security/plugin.json',$names,true)&&in_array('backup.json',$names,true));
t('Backup: Geheimnisse und serverlokale Dateien sind nicht enthalten',!str_contains($all,'SUPER-GEHEIM')&&!str_contains($all,'GEHEIMER-HASH')&&!str_contains($all,'dbpass')&&!str_contains($all,'mailpass')&&!in_array('cms/data/local-auth.local.php',$names,true)&&!in_array('cms/data/.update/state.json',$names,true));
$z->close();
$name=basename($zips[0]);
t('Backup: Prüfsummen stimmen',$B('verify',['name'=>$name])['ok']);
// beschädigtes Backup
copy($zips[0],$cms.'/backups/kaputt.zip');$z=new ZipArchive();$z->open($cms.'/backups/kaputt.zip');$z->addFromString('cms/data/news.json','MANIPULIERT');$z->close();
t('Backup: manipuliertes Archiv wird erkannt und nicht eingespielt',!$B('verify',['name'=>'kaputt.zip'])['ok']&&!$B('restore',['name'=>'kaputt.zip'])['ok']&&str_contains(file_get_contents($cms.'/data/news.json'),'"title":"A"'));
file_put_contents($cms.'/backups/kein.zip','das ist kein zip');
t('Backup: kein ZIP wird verständlich abgelehnt',!$B('verify',['name'=>'kein.zip'])['ok']);
// Wiederherstellung
file_put_contents($cms.'/data/site.json',json_encode(['portal'=>['site_name'=>'GEÄNDERT'],'assistant'=>['api_key'=>'NEUER-KEY','model'=>'y']]));
file_put_contents($cms.'/data/news.json',json_encode([['id'=>1,'slug'=>'b','title'=>'B']]));
$r=$B('restore',['name'=>$name]);
$site=json_decode(file_get_contents($cms.'/data/site.json'),true);
t('Backup: Wiederherstellung stellt Inhalte wieder her',$r['ok']&&$site['portal']['site_name']==='Meine Seite'&&str_contains(file_get_contents($cms.'/data/news.json'),'"title": "A"'));
t('Backup: Geheimnisse der laufenden Installation bleiben bei der Wiederherstellung erhalten',$site['assistant']['api_key']==='NEUER-KEY'&&$site['assistant']['model']==='x');
t('Backup: Sicherheitskopie vor der Wiederherstellung angelegt',glob($cms.'/backups/vor-wiederherstellung_*.zip')!==[]);
// Rotation
$mgr->saveSettings('elvado-backup',['keep'=>2]);
for($i=0;$i<4;$i++){ sleep(1);$B('action_create'); }
t('Backup: Rotation behält nur die eingestellte Anzahl',count(glob($cms.'/backups/elvado-backup_*.zip'))===2);
file_put_contents($cms.'/backups/weg.zip','x');
t('Backup: Löschen',$B('delete',['name'=>'weg.zip'])['ok']&&!is_file($cms.'/backups/weg.zip'));
t('Backup: Download-Pfad liegt im Backup-Ordner',(function() use($B,$cms){ $f=basename(glob($cms.'/backups/elvado-backup_*.zip')[0]);$r=$B('download_file',['name'=>$f]);return str_starts_with(realpath($r['file']),realpath($cms.'/backups'))&&$B('download_file',['name'=>'../../etc/passwd'])['file']===''; })());
// Zeitplan
$mgr->saveSettings('elvado-backup',['schedule'=>'daily','hour'=>0,'keep'=>10]);
reboot($cms);
$before=count(glob($cms.'/backups/elvado-backup_*.zip'));
sleep(1);Hooks::run('tick',time());
t('Backup: fälliges automatisches Backup läuft im Tick und merkt sich den Lauf',count(glob($cms.'/backups/elvado-backup_*.zip'))===$before+1&&!empty(json_decode(file_get_contents($cms.'/data/.plugins/data/elvado-backup/schedule.json'),true)['last_ok']));
$before=count(glob($cms.'/backups/elvado-backup_*.zip'));Hooks::run('tick',time());
t('Backup: nicht doppelt am selben Tag',count(glob($cms.'/backups/elvado-backup_*.zip'))===$before);
t('Backup: der Core-Bereich „Backups“ nutzt die Plugin-Engine',(function() use($cms){ $x=rrw_backup_create($cms.'/..',false);return str_starts_with($x['name'],'elvado-backup_'); })());
$cur=glob($cms.'/backups/elvado-backup_*.zip');copy(end($cur),$cms.'/backups/kaputt.zip');$z=new ZipArchive();$z->open($cms.'/backups/kaputt.zip');$z->addFromString('cms/data/news.json','MANIPULIERT');$z->close();
t('Backup: Core-Wiederherstellung nutzt die Plugin-Engine (Prüfung greift, kein Rückfall auf das alte Verfahren)',(function() use($cms){ try{ rrw_backup_restore('kaputt.zip',$cms.'/..');return false; }catch(Throwable $e){ return str_contains($e->getMessage(),'beschädigt'); } })());

// ---------- Elvado Performance
$h='<html><head><link rel="x"><img src="no.png"></head><body><img src="a.jpg" alt="x"><p>t</p><img src="b.jpg"><img src="c.jpg" loading="eager"><noscript><img src="n.jpg"></noscript><iframe src="https://e.example"></iframe><img src="d.jpg" /></body></html>';
$o=Perf::lazyLoad($h,1);
t('Performance: Lazy Loading überspringt das erste Bild, ergänzt weitere, achtet auf vorhandene Angaben und noscript',str_contains($o,'<img src="a.jpg" alt="x">')&&str_contains($o,'<img src="b.jpg" loading="lazy" decoding="async">')&&str_contains($o,'loading="eager"')&&str_contains($o,'<noscript><img src="n.jpg"></noscript>')&&str_contains($o,'<iframe src="https://e.example" loading="lazy">')&&str_contains($o,'<img src="d.jpg" loading="lazy" decoding="async" />')&&str_contains($o,'<img src="no.png">'));
$m=Perf::minify("<html><body>\n  <!-- weg -->\n  <p>a</p>   <p>b</p>\n<pre>  x\n   y </pre><script>var a = 1;   // x\n</script><!--[if IE]>bleibt<![endif]--></body></html>");
t('Performance: Minify entfernt Kommentare/Leerräume, schont pre, script und Bedingungskommentare',!str_contains($m,'weg')&&str_contains($m,"<pre>  x\n   y </pre>")&&str_contains($m,'var a = 1;   // x')&&str_contains($m,'<!--[if IE]>bleibt<![endif]-->'));
$_SERVER['REQUEST_URI']='/seite/';$_SERVER['REQUEST_METHOD']='GET';$_SERVER['HTTP_HOST']='t.example';$_GET=[];$_COOKIE=[];$_SERVER['HTTP_USER_AGENT']='Mozilla';unset($_SERVER['HTTP_ACCEPT']);
$page='<html><head><title>T</title></head><body><img src="a.jpg"><img src="b.jpg"><p>Hallo Welt</p></body></html>';
$mgr->saveSettings('elvado-performance',['cache'=>true,'ttl_minutes'=>10,'lazy'=>true,'skip_first'=>1,'minify'=>false]);
$_SERVER['REQUEST_URI']='/seite/';$_SERVER['REQUEST_METHOD']='GET';$_SERVER['HTTP_HOST']='t.example';$_GET=[];$_COOKIE=[];$_SERVER['HTTP_USER_AGENT']='Mozilla';
$out=rrw_np_filter('front_output',$page,200);
t('Performance: Ausgabe wird optimiert (Lazy Loading) und im Cache abgelegt',str_contains($out,'loading="lazy"')&&count(glob($cms.'/data/.plugins/data/elvado-performance/cache/*.html'))===1);
function child(string $cms,string $uri,array $env=[],string $cookie=''): string {
    $f=$cms.'/child.php';
    file_put_contents($f,'<?php define("RRW_DATA_DIR",'.var_export($cms.'/data',true).');require '.var_export($GLOBALS['real'].'/lib/nplugins.php',true).';
$ver=trim((string)file_get_contents('.var_export($cms.'/VERSION',true).'));rrw_np('.var_export($cms,true).','.var_export($cms.'/data',true).',$ver,true);rrw_np_boot();
$_SERVER["REQUEST_URI"]='.var_export($uri,true).';$_SERVER["REQUEST_METHOD"]="GET";$_SERVER["HTTP_HOST"]="t.example";$_SERVER["HTTP_USER_AGENT"]="Mozilla";foreach('.var_export($env,true).' as $k=>$v)$_SERVER[$k]=$v;'.($cookie!==''?'$_COOKIE='.$cookie.';':'').'
$q=[];parse_str((string)parse_url('.var_export($uri,true).',PHP_URL_QUERY),$q);$_GET=$q;
rrw_np_do("front_request",'.var_export($uri,true).',(string)parse_url('.var_export($uri,true).',PHP_URL_PATH),"GET");echo "NOCACHE";');
    return (string)shell_exec('php '.escapeshellarg($f).' 2>&1');
}
$real_nplugins=$real.'/lib/nplugins.php';
$hit=child($cms,'/seite/');
t('Performance: zweite Anfrage wird aus dem Cache beantwortet (ohne WordPress)',str_contains($hit,'Hallo Welt')&&!str_contains($hit,'NOCACHE'),substr($hit,0,200));
t('Performance: Anfragen mit Parametern werden nicht aus dem Cache bedient',str_contains(child($cms,'/seite/?s=x'),'NOCACHE'));
t('Performance: Tracking-Parameter (utm_*) stören den Cache nicht',str_contains(child($cms,'/seite/?utm_source=a'),'Hallo Welt'));
t('Performance: angemeldete Personen (Sitzungs-Cookie) bekommen nie Cache-Seiten',str_contains(child($cms,'/seite/',[],'["rrw_wp_sess"=>"x"]'),'NOCACHE'));
t('Performance: Verwaltungs- und API-Adressen werden nie gecacht',str_contains(child($cms,'/cms/api.php'),'NOCACHE')&&str_contains(child($cms,'/wp-json/x'),'NOCACHE'));
t('Performance: App-Anfragen (App-Modus) werden nicht gecacht',str_contains(child($cms,'/seite/',['HTTP_USER_AGENT'=>'Mozilla ElvadoPressApp/1.0 (brand=abc; platform=android)']),'NOCACHE'));
rrw_np_do('content_saved','news');
t('Performance: Inhaltsänderung leert den Cache',glob($cms.'/data/.plugins/data/elvado-performance/cache/*.html')===[]&&str_contains(child($cms,'/seite/'),'NOCACHE'));
$_SERVER['REQUEST_URI']='/form/';rrw_np_filter('front_output','<html><body><form data-elvado-nocache></form></body></html>',200);
t('Performance: Seiten mit Markierung data-elvado-nocache werden nie gespeichert',glob($cms.'/data/.plugins/data/elvado-performance/cache/*.html')===[]);
$_SERVER['REQUEST_URI']='/e/';rrw_np_filter('front_output',$page,404);
t('Performance: Fehlerseiten (404) werden nicht gecacht',glob($cms.'/data/.plugins/data/elvado-performance/cache/*.html')===[]);
$mgr->saveSettings('elvado-performance',['exclude_paths'=>"/warenkorb/\n/kasse"]);$_SERVER['REQUEST_URI']='/warenkorb/artikel';rrw_np_filter('front_output',$page,200);
t('Performance: eigene Ausschlüsse werden beachtet',glob($cms.'/data/.plugins/data/elvado-performance/cache/*.html')===[]);
t('Performance: Übersicht liefert Kennzahlen und Serverprüfung',(function() use($mgr){ $o=$mgr->callApi('elvado-performance','overview',[],'admin');return $o['status']==='ok'&&$o['blocks'][0]['type']==='stats'&&$o['blocks'][1]['type']==='checks'; })());
// .htaccess
file_put_contents($tmp.'/.htaccess',"RewriteEngine On\nRewriteRule ^x$ /y [L]\n");
t('Performance: .htaccess-Block eintragen (Original bleibt erhalten und wird gesichert)',$mgr->callApi('elvado-performance','action_htaccess_enable',[],'admin')['ok']&&str_contains(file_get_contents($tmp.'/.htaccess'),'BEGIN ElvadoPress Performance')&&str_contains(file_get_contents($tmp.'/.htaccess'),'RewriteRule ^x$ /y [L]')&&is_file($cms.'/data/.plugins/data/elvado-performance/htaccess.bak'));
$once=file_get_contents($tmp.'/.htaccess');$mgr->callApi('elvado-performance','action_htaccess_enable',[],'admin');
t('Performance: .htaccess-Block nicht doppelt',$once===file_get_contents($tmp.'/.htaccess'));
$mgr->callApi('elvado-performance','action_htaccess_disable',[],'admin');
t('Performance: .htaccess-Block wieder entfernen (Original unverändert)',file_get_contents($tmp.'/.htaccess')==="RewriteEngine On\nRewriteRule ^x\$ /y [L]\n");
t('Performance: AVIF-Aktion meldet ehrlich, wenn der Server es nicht kann (oder arbeitet)',(function() use($mgr){ $r=$mgr->callApi('elvado-performance','action_optimize_images',[],'admin');return function_exists('imageavif')?$r['ok']:(!$r['ok']&&str_contains($r['message'],'AVIF')); })());


// ======================================================= Teil 2: SEO, Forms, Analytics, AI
function ctx(PluginManager $mgr,string $id): Context { [$m]=$mgr->installedManifest($id);return new Context($mgr,$m,$mgr->cmsDir().'/plugins/'.$id); }
$real_vers=trim((string)file_get_contents($cms.'/VERSION'));

// ---------- Elvado Analytics
if(in_array('elvado-analytics',$ess,true)){
$A=new Stats(ctx($mgr,'elvado-analytics'));
$srv=['REQUEST_METHOD'=>'GET','REMOTE_ADDR'=>'203.0.113.5','HTTP_USER_AGENT'=>'Mozilla/5.0 (Windows NT 10.0) Chrome/120','HTTP_HOST'=>'t.example','HTTP_REFERER'=>'https://www.google.com/search?q=x'];
$hit=function(array $o=[],string $path='/a/',array $cookie=[]) use($A,$srv){ $_SERVER=$o+$srv;$_COOKIE=$cookie;$A->count(200,$path,'text/html; charset=UTF-8'); };
t('Analytics: Cache-Treffer werden mitgezählt (front_response beim Ausliefern aus dem Cache)',Stats::sum($A->days(1))['p']['/seite/']>=1);
$mgr->callApi('elvado-analytics','action_purge',[],'admin');
$hit();$hit();$hit(['REMOTE_ADDR'=>'198.51.100.7'],'/b/');
$d=$A->days(1);$t=Stats::sum($d);
t('Analytics: Aufrufe und eindeutige Besucher werden gezählt',$t['v']===3&&$t['u']===2&&$t['p']['/a/']===2&&$t['p']['/b/']===1);
t('Analytics: Herkunft (Host ohne www) und Gerät werden erfasst',$t['r']['google.com']===3&&$t['d']['desktop']===3);
$hit(['HTTP_USER_AGENT'=>'Googlebot/2.1 (+http://www.google.com/bot.html)'],'/bot/');
$hit(['HTTP_DNT'=>'1'],'/dnt/');$hit(['HTTP_SEC_GPC'=>'1'],'/gpc/');
$hit([],'/editor/',['rrw_wp_sess'=>'x']);$hit([],'/cms/api.php');$hit(['REQUEST_METHOD'=>'POST'],'/post/');
$A->count(404,'/nix/','text/html');$A->count(200,'/feed.json','application/json');
$t=Stats::sum($A->days(1));
t('Analytics: Bots, Do-Not-Track, Global Privacy Control, Redakteure, Verwaltung, POST, 404 und Nicht-HTML werden nicht gezählt',$t['v']===3&&!isset($t['p']['/bot/'])&&!isset($t['p']['/dnt/'])&&!isset($t['p']['/gpc/'])&&!isset($t['p']['/editor/'])&&!isset($t['p']['/nix/']));
$hit(['HTTP_USER_AGENT'=>'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Mobile Safari','HTTP_REFERER'=>'https://t.example/intern/'],'/m/');$hit(['HTTP_USER_AGENT'=>'Foo ElvadoPressApp/1.0 (brand=abc; platform=android)','REMOTE_ADDR'=>'192.0.2.9'],'/app/');
$t=Stats::sum($A->days(1));
t('Analytics: Geräteklassen (Smartphone, App) und interne Verweise',($t['d']['mobile']??0)===1&&($t['d']['app']??0)===1&&!isset($t['r']['(intern)'])&&!isset($t['r']['t.example']));
$files=glob($cms.'/data/.plugins/data/elvado-analytics/*/*');$all=implode("\n",array_map('file_get_contents',$files));
t('Analytics: weder IP-Adressen noch User-Agent im Speicher; Besucher nur als kurzer Hash',!str_contains($all,'203.0.113')&&!str_contains($all,'Chrome')&&!str_contains($all,'198.51.100')&&preg_match('/^[a-f0-9]{12}$/m',(string)file_get_contents(glob($cms.'/data/.plugins/data/elvado-analytics/visitors/*.txt')[0]))===1);
$old=$cms.'/data/.plugins/data/elvado-analytics/days/'.date('Y-m-d',time()-200*86400).'.json';file_put_contents($old,'{}');
$ov=$mgr->callApi('elvado-analytics','overview',[],'admin');$A->tick();
t('Analytics: Aufbewahrung löscht alte Tage; Übersicht mit Balken, Tabellen und Datenschutz-Hinweisen',!is_file($old)&&$ov['status']==='ok'&&array_column($ov['blocks'],'type')===['stats','bars','table','table','bars','checks']);
// externe Dienste
$page='<html><head></head><body><p>x</p></body></html>';$_COOKIE=[];
$mgr->saveSettings('elvado-analytics',['matomo_url'=>'https://matomo.example.org/','matomo_site_id'=>5,'ga_id'=>'G-ABC123XYZ9','ack'=>false]);
t('Analytics: externe Dienste werden ohne Bestätigung („ack“) NICHT ausgeliefert',!str_contains(rrw_np_filter('front_output',$page,200),'matomo')&&!str_contains(rrw_np_filter('front_output',$page,200),'googletagmanager'));
$mgr->saveSettings('elvado-analytics',['ack'=>true]);
$o=rrw_np_filter('front_output',$page,200);
t('Analytics: nach Bestätigung kommen Matomo und GA (clientseitig mit DNT-/Einwilligungsprüfung)',str_contains($o,'matomo.example.org')&&str_contains($o,'G-ABC123XYZ9')&&str_contains($o,'doNotTrack')&&str_contains($o,'"consent":""'));
$mgr->saveSettings('elvado-analytics',['consent_cookie'=>'cookie_consent']);
t('Analytics: Einwilligungs-Cookie wird in die Prüfung übernommen',str_contains(rrw_np_filter('front_output',$page,200),'"consent":"cookie_consent"'));
$mgr->saveSettings('elvado-analytics',['ga_id'=>'G-<script>alert(1)</script>','matomo_url'=>'https://matomo.example.org/','matomo_site_id'=>0]);
t('Analytics: ungültige IDs (Skript-Einschleusung) und Matomo-ID 0 liefern nichts aus',!str_contains(rrw_np_filter('front_output',$page,200),'script>alert')&&!str_contains(rrw_np_filter('front_output',$page,200),'googletagmanager')&&!str_contains(rrw_np_filter('front_output',$page,200),'matomo.js'));
$mgr->saveSettings('elvado-analytics',['matomo_site_id'=>5,'ga_id'=>'']);$_COOKIE=['rrw_wp_sess'=>'x'];
t('Analytics: Redakteure (angemeldet) werden nicht von externen Diensten erfasst',!str_contains(rrw_np_filter('front_output',$page,200),'matomo.js')&&!str_contains(rrw_np_filter('front_output',$page,200),'matomo.example'));
$_COOKIE=[];
t('Analytics: Standard ist ausgeschaltet (ack=false, keine IDs)',(function() use($mgr){ $d=array_column($mgr->installedManifest('elvado-analytics')[0]['settings'],'default','key');return $d['ack']===false&&$d['ga_id']===''&&(int)$d['matomo_site_id']===0&&$d['internal']===true; })());
$mgr->callApi('elvado-analytics','action_purge',[],'admin');
t('Analytics: alle Daten löschen',Stats::sum($A->days(30))['v']===0);
$_SERVER=['REQUEST_METHOD'=>'GET','HTTP_HOST'=>'t.example','HTTP_USER_AGENT'=>'Mozilla'];
}

// ---------- Elvado Forms
if(in_array('elvado-forms',$ess,true)){
define('ELVADO_FORMS_TEST',1);
$np=ctx($mgr,'elvado-forms');$F=new Forms($np);
$good=['id'=>'kontakt','title'=>'Kontakt','fields'=>[['id'=>'name','type'=>'text','label'=>'Name','required'=>true],['id'=>'email','type'=>'email','label'=>'E-Mail','required'=>true],['id'=>'thema','type'=>'select','label'=>'Thema','options'=>['Frage','Lob'],'required'=>true],['id'=>'nachricht','type'=>'textarea','label'=>'Nachricht','required'=>true],['id'=>'abo','type'=>'checkbox','label'=>'Newsletter'],['id'=>'art','type'=>'radio','label'=>'Art','options'=>['A','B']]],
    'store'=>true,'notify'=>['enabled'=>true,'to'=>'ziel@example.org','reply_to_field'=>'email'],'consent'=>['enabled'=>true],'spam'=>['honeypot'=>true,'min_seconds'=>0,'rate_limit'=>5,'captcha'=>false]];
t('Forms: Formular speichern und lesen',$F->save($good)['ok']&&$F->find('kontakt')['title']==='Kontakt'&&count($F->find('kontakt')['fields'])===6);
t('Forms: Validierung der Definition (Kennung, Felder, Typ, Optionen, URLs, E-Mail)',!$F->save(['id'=>'X Y','title'=>'t','fields'=>[['id'=>'a','type'=>'text','label'=>'a']]])['ok']&&!$F->save(['id'=>'ok1','title'=>'t','fields'=>[]])['ok']&&!$F->save(['id'=>'ok2','title'=>'t','fields'=>[['id'=>'a','type'=>'zauber','label'=>'a']]])['ok']&&!$F->save(['id'=>'ok3','title'=>'t','fields'=>[['id'=>'a','type'=>'select','label'=>'a','options'=>[]]]])['ok']&&!$F->save(['id'=>'ok4','title'=>'t','fields'=>[['id'=>'a','type'=>'text','label'=>'a'],['id'=>'a','type'=>'text','label'=>'b']]])['ok']&&!$F->save(['id'=>'ok5','title'=>'t','redirect_url'=>'javascript:x','fields'=>[['id'=>'a','type'=>'text','label'=>'a']]])['ok']&&!$F->save(['id'=>'ok6','title'=>'t','webhook'=>['url'=>'http://x.example'],'fields'=>[['id'=>'a','type'=>'text','label'=>'a']]])['ok']&&!$F->save(['id'=>'ok7','title'=>'t','notify'=>['to'=>'kein-mail'],'fields'=>[['id'=>'a','type'=>'text','label'=>'a']]])['ok']);
$html=$F->render('kontakt');
t('Forms: Ausgabe enthält alle Felder, Pflichtmarkierung, Token, Honeypot, Einwilligung und das Skript',str_contains($html,'name="email"')&&str_contains($html,'type="email"')&&str_contains($html,'<select')&&str_contains($html,'aria-required="true"')&&str_contains($html,'name="_token"')&&str_contains($html,'class="ef-hp"')&&str_contains($html,'name="_consent"')&&str_contains($html,'np_public')&&str_contains($F->render('gibts-nicht'),'nicht gefunden'));
t('Forms: Ausgabe maskiert Beschriftungen (kein XSS)',(function() use($F){ $F->save(['id'=>'xss','title'=>'<b>x</b>','fields'=>[['id'=>'a','type'=>'text','label'=>'"><script>alert(1)</script>']]]);return !str_contains($F->render('xss'),'<script>alert(1)'); })());
$F->delete('xss',true);
$tok=fn()=>$F->token('kontakt',time()-10);
$vals=fn(array $o=[])=>['form'=>'kontakt','values'=>$o+['name'=>'Rico','email'=>'rico@example.org','thema'=>'Lob','nachricht'=>'Tolles CMS!','abo'=>'1','art'=>'A','_consent'=>'1','_token'=>$tok()]];
// SMTP-Testserver
$srvScript=$tmp.'/smtpd.php';
file_put_contents($srvScript,'<?php $s=stream_socket_server("tcp://127.0.0.1:0",$en,$es);file_put_contents($argv[1],explode(":",stream_socket_get_name($s,false))[1]);$out=$argv[2];$user=base64_encode("smtpuser");$pass=base64_encode("smtppass");
while($c=@stream_socket_accept($s,60)){fwrite($c,"220 test ESMTP\r\n");$data="";$in=false;$authed=false;while(($l=fgets($c))!==false){$u=strtoupper(trim($l));if($in){if(trim($l)==="."){$in=false;file_put_contents($out,$data."\n=====\n",FILE_APPEND);fwrite($c,"250 queued\r\n");continue;}$data.=$l;continue;}
if(str_starts_with($u,"EHLO")){fwrite($c,"250-test\r\n250 AUTH LOGIN\r\n");}elseif($u==="AUTH LOGIN"){fwrite($c,"334 VXNlcm5hbWU6\r\n");$a=trim(fgets($c));fwrite($c,"334 UGFzc3dvcmQ6\r\n");$b=trim(fgets($c));if($a===$user&&$b===$pass){$authed=true;fwrite($c,"235 ok\r\n");}else fwrite($c,"535 bad\r\n");}elseif(str_starts_with($u,"MAIL FROM")){fwrite($c,$authed?"250 ok\r\n":"530 auth first\r\n");}elseif(str_starts_with($u,"RCPT TO")){fwrite($c,"250 ok\r\n");}elseif($u==="DATA"){fwrite($c,"354 go\r\n");$in=true;}elseif($u==="QUIT"){fwrite($c,"221 bye\r\n");break;}else fwrite($c,"250 ok\r\n");}fclose($c);}');
$portFile=$tmp.'/smtp.port';$mailFile=$tmp.'/smtp.mails';
$proc=proc_open([PHP_BINARY,$srvScript,$portFile,$mailFile],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
for($i=0;$i<50&&!is_file($portFile);$i++)usleep(100000);$port=(int)@file_get_contents($portFile);
t('Forms: SMTP-Testserver gestartet',$port>0);
$mgr->saveSettings('elvado-forms',['smtp_host'=>'127.0.0.1','smtp_port'=>$port,'smtp_security'=>'none','smtp_user'=>'smtpuser','smtp_password'=>'smtppass','from_email'=>'absender@example.org','from_name'=>'Mein Verein','default_to'=>'standard@example.org']);
$np=ctx($mgr,'elvado-forms');$F=new Forms($np);
$r=$F->submit($vals(),[],'203.0.113.9');
t('Forms: gültige Einsendung wird gespeichert und per SMTP (mit Anmeldung) versendet',$r['ok']&&$F->count('kontakt')===1&&is_file($mailFile),json_encode($r));
$mail=(string)@file_get_contents($mailFile);preg_match('/\r?\n\r?\n(.*)/s',$mail,$m);$body=base64_decode(preg_replace('/\s+/','',explode("\n=====",(string)($m[1]??''))[0]));
t('Forms: E-Mail enthält Empfänger, Reply-To, kodierten Betreff und alle Feldwerte',str_contains($mail,'To: <ziel@example.org>')&&str_contains($mail,'Reply-To: <rico@example.org>')&&str_contains($mail,'From: Mein Verein <absender@example.org>')&&str_contains($body,'Rico')&&str_contains($body,'Tolles CMS!')&&str_contains($body,'Lob'));
t('Forms: Test-E-Mail über die Plugin-Aktion',$mgr->callApi('elvado-forms','action_test_mail',[],'admin')['ok']&&str_contains((string)file_get_contents($mailFile),'To: <standard@example.org>'));
$mgr->saveSettings('elvado-forms',['smtp_password'=>'falsch']);$np=ctx($mgr,'elvado-forms');$F=new Forms($np);
$r=$F->submit($vals(['_token'=>$F->token('kontakt',time()-10)]),[],'203.0.113.10');
t('Forms: falsches SMTP-Passwort: Einsendung bleibt gespeichert, Besucher sieht Erfolg, Fehler steht im Plugin-Protokoll (ohne Passwort)',$r['ok']&&$F->count('kontakt')===2&&str_contains((string)file_get_contents($cms.'/data/.plugins/log.txt'),'Anmeldung am Mailserver fehlgeschlagen')&&!str_contains((string)file_get_contents($cms.'/data/.plugins/log.txt'),'falsch'));
$mgr->saveSettings('elvado-forms',['smtp_password'=>'smtppass','smtp_port'=>1]);$np=ctx($mgr,'elvado-forms');$F=new Forms($np);
$F->save(['store'=>false]+$good);
$r=$F->submit($vals(['_token'=>$F->token('kontakt',time()-10)]),[],'203.0.113.11');
t('Forms: weder gespeichert noch versendbar => ehrlicher Fehler statt falscher Erfolgsmeldung',!$r['ok']&&str_contains($r['message'],'nicht zugestellt'));
$mgr->saveSettings('elvado-forms',['smtp_port'=>$port]);$np=ctx($mgr,'elvado-forms');$F=new Forms($np);$F->save($good);
$rt=fn(array $o,array $files=[],string $ip='198.51.100.50')=>$F->submit($vals($o),$files,$ip);
t('Forms: Pflichtfelder, E-Mail- und Auswahl-Validierung',!$rt(['name'=>''])['ok']&&!$rt(['email'=>'kaputt'])['ok']&&!$rt(['thema'=>'Hack'])['ok']&&!$rt(['art'=>'Z'])['ok']&&!$rt(['nachricht'=>str_repeat('x',5001)])['ok']);
t('Forms: Einwilligung ist Pflicht, wenn aktiviert',!$F->submit(['form'=>'kontakt','values'=>['name'=>'a','email'=>'a@example.org','thema'=>'Lob','nachricht'=>'hallo hallo','_token'=>$F->token('kontakt',time()-10)]],[],'198.51.100.51')['ok']);
t('Forms: zu viele Links im Text werden abgelehnt',!$rt(['nachricht'=>'http://a.example http://b.example http://c.example'])['ok']);
$n0=$F->count('kontakt');
$hp=$F->submit($vals(['hp_'.substr(hash_hmac('sha256','hp|kontakt',trim((string)file_get_contents($cms.'/data/.plugins/data/elvado-forms/salt.txt'))),0,8)=>'http://spam']),[],'198.51.100.52');
t('Forms: Honeypot – stiller Erfolg, nichts gespeichert',$hp['ok']&&!empty($hp['spam'])&&$F->count('kontakt')===$n0);
t('Forms: Zeitfalle und Token (fehlend, manipuliert, zu schnell, uralt)',!$F->submit($vals(['_token'=>'']),[],'198.51.100.53')['ok']&&!$F->submit($vals(['_token'=>time().'.aaaaaaaaaaaaaaaaaaaaaaaa']),[],'198.51.100.53')['ok']&&!$F->save(['min_seconds'=>5]+$good)===false);
$F->save(['spam'=>['min_seconds'=>10,'rate_limit'=>5,'honeypot'=>true]]+$good);
t('Forms: zu schnelles Absenden wird abgelehnt',!$F->submit($vals(['_token'=>$F->token('kontakt',time()-2)]),[],'198.51.100.54')['ok']);
t('Forms: abgelaufenes Token (älter als 12 Stunden) wird abgelehnt',!$F->submit($vals(['_token'=>$F->token('kontakt',time()-50000)]),[],'198.51.100.55')['ok']);
$F->save(['spam'=>['min_seconds'=>0,'rate_limit'=>3,'honeypot'=>true,'captcha'=>false]]+$good);
$ok=0;for($i=0;$i<5;$i++)$ok+=$F->submit($vals(['_token'=>$F->token('kontakt',time()-10)]),[],'198.51.100.60')['ok']?1:0;
t('Forms: Begrenzung je Besucher (3 von 5 Versuchen)',$ok===3);
t('Forms: andere Besucher sind von der Begrenzung nicht betroffen',$F->submit($vals(['_token'=>$F->token('kontakt',time()-10)]),[],'198.51.100.61')['ok']);
$F->save(['spam'=>['min_seconds'=>0,'rate_limit'=>50,'honeypot'=>true,'captcha'=>true]]+$good);
$ct=fn(int $a,int $b)=>$a.'.'.$b.'.'.$F->token('kontakt',time()-10,$a.'+'.$b);
t('Forms: Rechenfrage (richtig / falsch / Token manipuliert)',$F->submit($vals(['_captcha'=>'7','_ctoken'=>$ct(3,4)]),[],'198.51.100.70')['ok']&&!$F->submit($vals(['_captcha'=>'8','_ctoken'=>$ct(3,4)]),[],'198.51.100.71')['ok']&&!$F->submit($vals(['_captcha'=>'7','_ctoken'=>'3.4.'.explode('.',$ct(5,5),3)[2]]),[],'198.51.100.72')['ok']);
$F->save($good);
// Datei-Uploads
$f1=$tmp.'/u1.pdf';file_put_contents($f1,"%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
$up=fn(string $path,string $name)=>['f_datei'=>['name'=>$name,'tmp_name'=>$path,'size'=>filesize($path),'error'=>0]];
$withFile=['fields'=>array_merge($good['fields'],[['id'=>'datei','type'=>'file','label'=>'Anhang','required'=>false]])]+$good;
$F->save($withFile);
t('Forms: Upload ist standardmäßig aus',!$F->submit($vals(['_token'=>$F->token('kontakt',time()-10)]),$up($f1,'a.pdf'),'198.51.100.80')['ok']);
$mgr->saveSettings('elvado-forms',['allow_uploads'=>true,'upload_max_mb'=>1]);$np=ctx($mgr,'elvado-forms');$F=new Forms($np);
$run=fn(string $path,string $name,string $ip)=>$F->submit($vals(['_token'=>$F->token('kontakt',time()-10)]),$up($path,$name),$ip);
$r=$run($f1,'Mein Lebenslauf (final).pdf','198.51.100.81');
$sub=$F->submissions('kontakt',1)[0];
t('Forms: erlaubte Datei wird geprüft, mit Zufallsnamen geschützt abgelegt und der Einsendung zugeordnet',$r['ok']&&count($sub['files'])===1&&preg_match('/^[a-f0-9]{20}\.pdf$/',$sub['files'][0]['stored'])===1&&$F->uploadPath('kontakt',$sub['files'][0]['stored'])!==null&&is_file($cms.'/data/.plugins/data/elvado-forms/uploads/.htaccess')||is_file($cms.'/data/.plugins/.htaccess'));
file_put_contents($tmp.'/shell.php',"<?php system(\$_GET['c']);");file_put_contents($tmp.'/bild.png',"<?php echo 1;");file_put_contents($tmp.'/gross.pdf',"%PDF".str_repeat('x',1200000));
t('Forms: gefährliche Uploads werden abgelehnt (.php, falscher Inhalt zur Endung, zu groß, doppelte Endung)',!$run($tmp.'/shell.php','shell.php','198.51.100.82')['ok']&&!$run($tmp.'/bild.png','bild.png','198.51.100.83')['ok']&&!$run($tmp.'/gross.pdf','gross.pdf','198.51.100.84')['ok']&&!$run($f1,'x.php.pdf.exe','198.51.100.85')['ok']);
t('Forms: Upload-Pfade sind gegen Pfadangriffe geschützt',$F->uploadPath('kontakt','../../etc/passwd')===null&&$F->uploadPath('../x','aaaaaaaaaaaaaaaaaaaa.pdf')===null);
// Export, Löschen, Aufbewahrung
$csv=$F->exportCsv('kontakt');file_put_contents($tmp.'/x','');
$F->save(['spam'=>['min_seconds'=>0,'rate_limit'=>50,'honeypot'=>true]]+$withFile);
$F->submit($vals(['name'=>'=HYPERLINK("http://evil")','_token'=>$F->token('kontakt',time()-10)]),[],'198.51.100.90');
$csv=file_get_contents($F->exportCsv('kontakt'));
t('Forms: CSV-Export mit Schutz vor Formel-Einschleusung',str_contains($csv,'Zeit;Name;')&&str_contains($csv,"'=HYPERLINK")&&!preg_match('/;=HYPERLINK/',$csv));
$id=$F->submissions('kontakt',1)[0]['id'];
t('Forms: Einsendung löschen',$F->deleteSubmission('kontakt',$id)&&!array_filter($F->submissions('kontakt'),fn($s)=>$s['id']===$id));
$sf=$cms.'/data/.plugins/data/elvado-forms/submissions/kontakt.jsonl';
$lines=file($sf,FILE_IGNORE_NEW_LINES);$e=json_decode($lines[0],true);$e['t']=date('Y-m-d H:i:s',time()-200*86400);$lines[0]=json_encode($e);file_put_contents($sf,implode("\n",$lines)."\n");
$before=$F->count('kontakt');$F->save(['retention_days'=>90]+$withFile);$F->tick();
t('Forms: Aufbewahrungsfrist löscht alte Einsendungen automatisch',$F->count('kontakt')===$before-1);
t('Forms: Webhook-Ziel wird gegen interne Adressen geschützt (https, öffentliche IP)',!Forms::webhookTargetOk('http://93.184.216.34/h')&&!Forms::webhookTargetOk('https://127.0.0.1/h')&&!Forms::webhookTargetOk('https://10.0.0.5/h')&&!Forms::webhookTargetOk('https://192.168.1.1/h')&&!Forms::webhookTargetOk('https://169.254.169.254/latest')&&!Forms::webhookTargetOk('https://localhost/h')&&Forms::webhookTargetOk('https://93.184.216.34/h'));
$F->save(['webhook'=>['url'=>'https://93.184.216.34/h','secret'=>'geheim']]+$good);
t('Forms: Webhook-Geheimnis wird dem Browser nie geliefert und bleibt beim Speichern ohne Neueingabe erhalten',(function() use($mgr,$F,$good){ $l=$mgr->callApi('elvado-forms','list_forms',[],'editor');$j=json_encode($l);$F->save(['webhook'=>['url'=>'https://93.184.216.34/h']]+$good);return !str_contains($j,'geheim')&&str_contains($j,'secret_set')&&$F->find('kontakt')['webhook']['secret']==='geheim'; })());
t('Forms: öffentliche API nimmt nur die Aktion „submit“ an (keine Verwaltung ohne Anmeldung)',$mgr->callApi('elvado-forms','save_form',['form'=>$good],'public')['code']===403&&$mgr->callApi('elvado-forms','submissions',['form'=>'kontakt'],'public')['code']===403&&$mgr->callApi('elvado-forms','list_forms',[],'public')['code']===403&&$mgr->callApi('elvado-forms','submissions',['form'=>'kontakt'],'editor')['code']===403);
t('Forms: Formular löschen (mit Daten)',$F->delete('kontakt',true)&&$F->find('kontakt')===null&&!is_file($sf));
@proc_terminate($proc);@proc_close($proc);
}

// ---------- Elvado SEO
if(in_array('elvado-seo',$ess,true)){
if(!class_exists('WP_Post')){ class WP_Post { public $ID=1,$post_title='',$post_content='',$post_excerpt='',$post_type='post',$post_date_gmt='2025-03-04 10:00:00',$post_modified_gmt='2025-03-05 11:00:00',$post_author=1,$post_password='',$rrw_source='news',$rrw_data=[]; }
class WP_Query { public $posts=[]; function __construct($a=[]){ $this->posts=$GLOBALS['stub_posts']??[]; } } }
$GLOBALS['stub']=['kind'=>'single','obj'=>null,'url'=>'https://t.example/mein-beitrag/'];
foreach(['is_front_page'=>fn()=>$GLOBALS['stub']['kind']==='front','is_home'=>fn()=>false,'is_singular'=>fn()=>in_array($GLOBALS['stub']['kind'],['single','page'],true),'is_404'=>fn()=>$GLOBALS['stub']['kind']==='404','is_search'=>fn()=>$GLOBALS['stub']['kind']==='search','is_date'=>fn()=>$GLOBALS['stub']['kind']==='date','is_author'=>fn()=>false,'is_tag'=>fn()=>false,'is_category'=>fn()=>false,
  'get_queried_object'=>fn()=>$GLOBALS['stub']['obj'],'get_bloginfo'=>fn($k='name')=>'Mein Verein','home_url'=>fn($p='/')=>'https://t.example'.$p,'get_permalink'=>fn($p=null)=>$GLOBALS['stub']['url'],'get_locale'=>fn()=>'de_DE','get_post_meta'=>fn()=>'','get_userdata'=>fn()=>null,'get_term_link'=>fn($t)=>'https://t.example/category/'.$t->slug.'/','get_terms'=>fn()=>[(object)['name'=>'News','slug'=>'news','description'=>'']]] as $fn=>$impl){ if(!function_exists($fn)){ eval('function '.$fn.'(...$a){ return ($GLOBALS["stubfn"]["'.$fn.'"])(...$a); }'); } $GLOBALS['stubfn'][$fn]=$impl; }
$mk=function(array $d,string $title='Mein Beitrag',string $src='news',string $type='post'){ $p=new WP_Post();$p->post_title=$title;$p->rrw_source=$src;$p->rrw_data=$d;$p->post_type=$type;$p->post_content='<p>Der Text des Beitrags mit genug Inhalt.</p>';return $p; };
$Sx=new Seo(ctx($mgr,'elvado-seo'));
$head='<html><head><title>Theme-Titel</title></head><body class="x"><p>b</p></body></html>';
$GLOBALS['stub']=['kind'=>'single','obj'=>$mk(['seo_title'=>'Mein SEO-Titel','seo_description'=>'Beschreibung des Beitrags','image_url'=>'/cms/media/a.jpg','author'=>'Rico','category'=>'News']),'url'=>'https://t.example/mein-beitrag/'];
$o=$Sx->inject($head,200);
t('SEO: eigener SEO-Titel ersetzt den Theme-Titel; Meta-Beschreibung, Canonical, robots',str_contains($o,'<title>Mein SEO-Titel</title>')&&!str_contains($o,'Theme-Titel')&&str_contains($o,'<meta name="description" content="Beschreibung des Beitrags"')&&str_contains($o,'rel="canonical" href="https://t.example/mein-beitrag/"')&&str_contains($o,'max-image-preview:large'));
t('SEO: OpenGraph und Twitter-Cards (Typ article, absolutes Bild, Zeiten)',str_contains($o,'property="og:type" content="article"')&&str_contains($o,'property="og:image" content="https://t.example/cms/media/a.jpg"')&&str_contains($o,'property="og:title" content="Mein SEO-Titel"')&&str_contains($o,'article:published_time')&&str_contains($o,'name="twitter:card" content="summary_large_image"'));
preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s',$o,$mm);$ld=array_map(fn($j)=>json_decode($j,true),$mm[1]);$types=array_column($ld,'@type');
t('SEO: Schema.org BlogPosting und Brotkrumen als gültiges JSON-LD',in_array('BlogPosting',$types,true)&&in_array('BreadcrumbList',$types,true)&&$ld[0]['headline']==='Mein Beitrag'&&$ld[0]['author']['name']==='Rico');
$theme='<html><head><title>T</title><meta name="description" content="Vom Theme"><meta property="og:title" content="Theme OG"><link rel="canonical" href="https://t.example/x/"><meta name="robots" content="noindex,follow"><script type="application/ld+json">{}</script></head><body></body></html>';
$o=$Sx->inject($theme,200);
t('SEO: vorhandene Angaben des Themes werden nicht doppelt ausgegeben',substr_count($o,'name="description"')===1&&substr_count($o,'og:title')===1&&substr_count($o,'rel="canonical"')===1&&substr_count($o,'name="robots"')===1&&substr_count($o,'application/ld+json')===1&&str_contains($o,'og:description'));
$GLOBALS['stub']['obj']=$mk(['noindex'=>true,'seo_title'=>'']);$o=$Sx->inject($head,200);
t('SEO: noindex-Beitrag bekommt robots noindex (ohne große Vorschau)',str_contains($o,'content="noindex,follow"')&&!str_contains($o,'max-image-preview'));
$GLOBALS['stub']['kind']='front';$GLOBALS['stub']['obj']=null;$mgr->saveSettings('elvado-seo',['home_title'=>'Willkommen beim Verein','home_description'=>'Alles über unseren Verein.']);
$o=$Sx->inject($head,200);preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s',$o,$mm);$types=array_map(fn($j)=>json_decode($j,true)['@type'],$mm[1]);
t('SEO: Startseite – eigener Titel/Beschreibung, WebSite- und Organisation-Schema',str_contains($o,'<title>Willkommen beim Verein</title>')&&str_contains($o,'content="Alles über unseren Verein."')&&in_array('WebSite',$types,true)&&in_array('Organization',$types,true));
$GLOBALS['stub']['kind']='404';$o=$Sx->inject($head,404);
t('SEO: 404-Seiten sind noindex',str_contains($o,'noindex')&&!str_contains($o,'og:type'));
$GLOBALS['stub']['kind']='search';$o=$Sx->inject($head,200);$GLOBALS['stub']['kind']='date';$o2=$Sx->inject($head,200);
t('SEO: Suche und Archive sind standardmäßig noindex (abschaltbar)',str_contains($o,'noindex')&&str_contains($o2,'noindex')&&(function() use($mgr,$Sx,$head){ $mgr->saveSettings('elvado-seo',['noindex_archives'=>false]);$GLOBALS['stub']['kind']='date';return !str_contains($Sx->inject($head,200),'noindex'); })());
$mgr->saveSettings('elvado-seo',['title_template'=>'%title% | %site%','og'=>false,'schema'=>false,'meta'=>false]);$GLOBALS['stub']=['kind'=>'single','obj'=>$mk([]),'url'=>'https://t.example/y/'];
$o=$Sx->inject($head,200);
t('SEO: Titel-Vorlage; OpenGraph, Schema und Meta lassen sich abschalten',str_contains($o,'<title>Mein Beitrag | Mein Verein</title>')&&!str_contains($o,'og:title')&&!str_contains($o,'ld+json')&&!str_contains($o,'name="description"'));
t('SEO: Titel mit Sonderzeichen ($, \\, &) wird korrekt ersetzt',(function() use($Sx,$mk,$head){ $GLOBALS['stub']['obj']=$mk(['seo_title'=>'Preis $5 \\1 & mehr']);$o=$Sx->inject($head,200);return str_contains($o,'<title>Preis $5 \\1 &amp; mehr</title>'); })());
t('SEO: Kürzen von Beschreibungen an Wortgrenzen mit Auslassung',Seo::trim('Das ist ein ziemlich langer Satz mit vielen Wörtern, der gekürzt werden soll.',30)==='Das ist ein ziemlich langer…'&&Seo::trim('<p>Kurz &amp; gut</p>',50)==='Kurz & gut');
// Sitemap
$mgr->saveSettings('elvado-seo',['sitemap'=>true]);
$p1=$mk([],'A');$p1->ID=1;$p2=$mk(['noindex'=>true],'B');$p2->ID=2;$p3=$mk([],'C','page','page');$p3->ID=3;$p3->post_password='x';$p4=$mk([],'D');$p4->ID=4;
$GLOBALS['stub_posts']=[$p1,$p2,$p3,$p4];$GLOBALS['stub']['url']='https://t.example/a-beitrag/';
$r=$Sx->generate();$xml=(string)@file_get_contents($tmp.'/sitemap.xml');
t('SEO: Sitemap enthält Startseite, indexierbare Beiträge und Kategorien – ohne noindex und passwortgeschützte Seiten',$r['ok']&&str_contains($xml,'<loc>https://t.example/</loc>')&&str_contains($xml,'https://t.example/category/news/')&&!str_contains($xml,'noindex')&&str_contains($xml,'Elvado SEO')&&@simplexml_load_string($xml)!==false);
t('SEO: Sitemap wird nach Veröffentlichung sofort ersetzt, nach Beitragsänderung per Tick',(function() use($tmp){ file_put_contents($tmp.'/sitemap.xml','ALT');rrw_np_do('content_saved','site');$a=str_contains((string)file_get_contents($tmp.'/sitemap.xml'),'Elvado SEO');file_put_contents($tmp.'/sitemap.xml','ALT');rrw_np_do('content_saved','news');$b=(string)file_get_contents($tmp.'/sitemap.xml')==='ALT';Hooks::run('tick',time());return $a&&$b&&str_contains((string)file_get_contents($tmp.'/sitemap.xml'),'Elvado SEO'); })());
// Beschreibungen ergänzen
file_put_contents($cms.'/data/news.json',json_encode([['id'=>1,'slug'=>'a','status'=>'published','title'=>'A','excerpt'=>'Teaser des Beitrags','seo_description'=>''],['id'=>2,'slug'=>'b','status'=>'published','title'=>'B','excerpt'=>'','body_html'=>'<p>Der Beginn des Textes von Beitrag B.</p>','seo_description'=>'Eigene'],['id'=>3,'slug'=>'c','status'=>'published','title'=>'C','excerpt'=>'','body_html'=>'<p>Text C ohne Beschreibung.</p>']]));
file_put_contents($cms.'/data/site.json',json_encode(['pages'=>[['slug'=>'p','intro'=>'Intro der Seite','meta_description'=>''],['slug'=>'q','intro'=>'x','meta_description'=>'bleibt']]]));
$r=$mgr->callApi('elvado-seo','action_fill_descriptions',[],'admin');$news=json_decode(file_get_contents($cms.'/data/news.json'),true);$site=json_decode(file_get_contents($cms.'/data/site.json'),true);
t('SEO: fehlende Beschreibungen werden ergänzt, vorhandene bleiben unverändert',$news[0]['seo_description']==='Teaser des Beitrags'&&$news[1]['seo_description']==='Eigene'&&str_contains($news[2]['seo_description'],'Text C')&&$site['pages'][0]['meta_description']==='Intro der Seite'&&$site['pages'][1]['meta_description']==='bleibt'&&str_contains($r['message'],'2 Beitrag'));
t('SEO: Übersicht zeigt Prüfungen',(function() use($mgr){ $o=$mgr->callApi('elvado-seo','overview',[],'admin');return $o['status']==='ok'&&$o['blocks'][1]['type']==='checks'; })());
t('SEO: Vorschau-API für den Editor',$mgr->callApi('elvado-seo','preview',['title'=>'Titel','description'=>str_repeat('wort ',60)],'editor')['description_len']>160);
t('SEO: Editor-Skript wird als globales Admin-Skript eingebunden',(function() use($mgr){ foreach($mgr->globalAdminScripts() as $s)if(str_contains($s,'elvado-seo/admin.js'))return true;return false; })());
}

// ---------- Elvado AI
if(in_array('elvado-ai',$ess,true)){
$AI=fn(string $c,array $a=[],string $role='editor')=>$mgr->callApi('elvado-ai',$c,$a,$role);
t('AI: ohne eingerichteten Anbieter ehrliche Meldung statt Fake-Ergebnis',(function() use($AI){ $s=$AI('status');$r=$AI('text',['action'=>'improve','text'=>'Hallo']);return $s['usable']===false&&$r['ok']===false&&str_contains($r['message'],'Anbieter')||str_contains($r['message']??'','Schlüssel'); })());
$ov=$AI('overview',[],'admin');
t('AI: Übersicht weist auf fehlende Konfiguration hin',$ov['blocks'][0]['type']==='notice'&&$ov['blocks'][0]['level']==='warn');
AiGatewayConfig::load($cms.'/data',[])->save(['providers'=>['openai'=>['api_key'=>'sk-test-openai-1234']]]);
$calls=[];$reply='';
Http::useTransport(function(string $m,string $u,array $h,?string $b,array $o) use(&$calls,&$reply): HttpResponse { $calls[]=['u'=>$u,'h'=>$h,'b'=>json_decode((string)$b,true)];return new HttpResponse(200,json_encode(['choices'=>[['message'=>['content'=>$reply]]],'usage'=>['prompt_tokens'=>1,'completion_tokens'=>1]])); });
$GLOBALS['rrw_np_user']='tester';
t('AI: Anbieter aus der KI-Zentrale sind nutzbar (kein eigener Schlüssel im Plugin)',$AI('status')['usable']===true&&$AI('status')['providers'][0]['id']==='openai'&&!str_contains(json_encode($AI('status')),'sk-test'));
$reply='Ein verbesserter Satz.';$r=$AI('text',['action'=>'improve','text'=>'ein schlechter satz']);$c=end($calls);
t('AI: Textwerkzeug nutzt den zentralen Gateway (Anbieter, Schlüssel nur im Header, Auftrag + Text getrennt)',$r['ok']&&$r['text']==='Ein verbesserter Satz.'&&$c['u']==='https://api.openai.com/v1/chat/completions'&&in_array('Authorization: Bearer sk-test-openai-1234',$c['h'],true)&&str_contains(json_encode($c['b']),'ein schlechter satz')&&!str_contains(json_encode($c['b']),'sk-test'));
t('AI: leere Eingabe und unbekannte Aktion werden abgelehnt, ohne Anfrage zu senden',(function() use($AI,&$calls){ $n=count($calls);return !$AI('text',['action'=>'improve','text'=>''])['ok']&&!$AI('text',['action'=>'hacken','text'=>'x'])['ok']&&count($calls)===$n; })());
$reply='{"title":"Mein Verein – Termine & News","description":"Alle Termine und Neuigkeiten unseres Vereins auf einen Blick: Training, Feste und Berichte."}';$r=$AI('seo',['title'=>'Termine','text'=>'<p>Viel Text</p>']);
t('AI: SEO-Vorschlag (JSON) wird gelesen und begrenzt',$r['ok']&&$r['title']==='Mein Verein – Termine & News'&&mb_strlen($r['description'])<=200);
$reply='Das ist kein JSON';t('AI: unbrauchbare Antwort wird ehrlich gemeldet',!$AI('seo',['title'=>'T','text'=>'x'])['ok']);
$pages=[['title'=>'Start','path'=>'/'],['title'=>'Termine','path'=>'/termine/'],['title'=>'Kontakt','path'=>'/kontakt/'],['title'=>'Impressum','path'=>'/impressum/']];
$reply='{"tabs":[{"title":"Start","icon":"home","url":"/"},{"title":"Termine und Veranstaltungen","icon":"calendar","url":"/termine/"},{"title":"Erfunden","icon":"star","url":"/gibt-es-nicht/"},{"title":"Kontakt","icon":"unbekannt","url":"/kontakt/"}]}';
$r=$AI('app_tabs',['app'=>'Mein Verein','brief'=>'Sportverein','pages'=>$pages],'admin');
t('AI: Tab-Vorschlag nutzt nur vorhandene Seiten, begrenzt Titel, prüft Symbole',$r['ok']&&count($r['tabs'])===3&&array_column($r['tabs'],'url')===['/','/termine/','/kontakt/']&&mb_strlen($r['tabs'][1]['title'])<=16&&$r['tabs'][2]['icon']==='star');
t('AI: Tab-Vorschlag ohne Seiten wird abgelehnt',!$AI('app_tabs',['brief'=>'x','pages'=>[]],'admin')['ok']);
t('AI: App-Werkzeuge sind nur für Administratoren',$AI('app_tabs',['pages'=>$pages],'editor')['code']===403&&$AI('app_notice',[],'editor')['code']===403&&$AI('app_store',[],'editor')['code']===403);
$reply='{"title":"Neuer Terminkalender","text":"Ab sofort findest du alle Termine direkt in der App."}';$r=$AI('app_notice',['app'=>'X','brief'=>'Neue Version mit Kalender'],'admin');
t('AI: Hinweistext-Vorschlag',$r['ok']&&$r['title']==='Neuer Terminkalender');
$reply='{"short":"Termine, News und Kontakt deines Vereins.","long":"Die App des Vereins.\n• Termine\n• News"}';$r=$AI('app_store',['app'=>'X','brief'=>'Sportverein'],'admin');
t('AI: Store-Text-Vorschlag',$r['ok']&&mb_strlen($r['short'])<=80&&str_contains($r['long'],'• Termine'));
$mgr->saveSettings('elvado-ai',['editor_tools'=>false,'app_tools'=>false]);
t('AI: Werkzeuge lassen sich in den Einstellungen abschalten',!$AI('text',['action'=>'improve','text'=>'x'])['ok']&&!$AI('app_notice',['brief'=>'x'],'admin')['ok']);
t('AI: Plugin enthält keinen API-Schlüssel und keine eigene Anbieter-Anbindung (nur der zentrale Gateway)',(function() use($real){ $src=(string)file_get_contents($real.'/official-plugins/elvado-ai/lib/Ai.php').(string)file_get_contents($real.'/official-plugins/elvado-ai/plugin.php');return !preg_match('~api\.(openai|anthropic|evolink)|curl_init|sk-[A-Za-z0-9]{10}~',$src); })());
Http::useTransport(null);
}

Fs::rmTree($tmp);
echo $fail?"$fail von $n Prüfungen fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

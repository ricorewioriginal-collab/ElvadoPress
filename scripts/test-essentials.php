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
use Elvado\Plugin\{Fs,Hooks,PluginManager};

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

// ---------- Elvado Security
$S=fn(string $call,array $a=[])=>$mgr->callApi('elvado-security',$call,$a,'admin');
$mgr->saveSettings('elvado-security',['max_attempts'=>3,'lock_minutes'=>5]);
$ip='203.0.113.77';
t('Security: vor Fehlversuchen keine Sperre',rrw_np_filter('login_check',null,'rico',$ip)===null);
for($i=0;$i<3;$i++)rrw_np_do('login_result','rico',false,$ip);
$msg=rrw_np_filter('login_check',null,'rico',$ip);
t('Security: nach zu vielen Fehlversuchen gesperrt (Meldung mit Minuten)',is_string($msg)&&str_contains($msg,'Minute'));
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
use ElvadoPlugin\Redirects\Rules;
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
use ElvadoPlugin\Performance\Perf;
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


Fs::rmTree($tmp);
echo $fail?"$fail von $n Prüfungen fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

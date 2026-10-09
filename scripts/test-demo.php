<?php
// Baut das eigenständige CMS als öffentliche Testinstanz (--demo) und prüft Demo-Betrieb: automatische Einrichtung, Anmeldung mit dem Demo-Zugang,
// gesperrte Aktionen, Banner, Rücksetzen nach Ablauf des Zeitfensters (10 Minuten, hier durch Zurückdatieren) und gesperrte Mail. Aufruf: php scripts/test-demo.php
declare(strict_types=1);
$root=dirname(__DIR__);
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
function rmrf(string $d): void { if(!is_dir($d))return; foreach(scandir($d) as $f){if($f==='.'||$f==='..')continue;$p=$d.'/'.$f;is_dir($p)&&!is_link($p)?rmrf($p):@unlink($p);} @rmdir($d); }
$tmp=sys_get_temp_dir().'/elvado-demo-'.bin2hex(random_bytes(4));mkdir($tmp);$pkg=$tmp.'/pkg';$proc=null;
register_shutdown_function(function() use($tmp,&$proc){ if(is_resource($proc))proc_terminate($proc);rmrf($tmp); });
// Entwicklungsprojekt: Paket bauen (--demo). ElvadoPress selbst: Kopie des Repositories + scripts/make-demo.php.
if(is_file($root.'/scripts/build-standalone.php')){
    exec('php '.escapeshellarg($root.'/scripts/build-standalone.php').' '.escapeshellarg($pkg).' --product=ElvadoPress --demo 2>&1',$o,$rc);
}else{
    exec('cp -a '.escapeshellarg($root).' '.escapeshellarg($pkg).' 2>&1',$o,$rc);
    if($rc===0){ rmrf($pkg.'/.git'); foreach(glob($pkg.'/cms/data/*')?:[] as $f)if(basename($f)!=='.htaccess'&&!str_ends_with($f,'.example'))exec('rm -rf '.escapeshellarg($f));
        exec('php '.escapeshellarg($root.'/scripts/make-demo.php').' '.escapeshellarg($pkg).' 2>&1',$o,$rc); }
}
t('Demo-Paket entsteht',$rc===0,implode("\n",$o));
foreach(['cms/lib/demo.json','cms/lib/demo.php','cms/assets/demo.js','demo/index.html','cms/demo-state/.htaccess'] as $x)t("Im Demo-Paket: $x",is_file("$pkg/$x"));
$cfg=json_decode((string)@file_get_contents("$pkg/cms/lib/demo.json"),true)?:[];
t('Zeitfenster 10 Minuten',($cfg['minutes']??0)===10);

$router=$tmp.'/router.php';
file_put_contents($router,'<?php $p=(string)parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH);$f=$_SERVER["DOCUMENT_ROOT"].$p;if($p==="/"){require $_SERVER["DOCUMENT_ROOT"]."/index.php";return true;}if(is_dir($f)&&is_file($f."/index.html")){header("Content-Type: text/html");readfile($f."/index.html");return true;}if(str_starts_with($p,"/cms/")||is_file($f))return false;require $_SERVER["DOCUMENT_ROOT"]."/cms/wp-front.php";return true;');
$port=random_int(20000,40000);
$proc=proc_open(['php','-S',"127.0.0.1:$port",'-t',$pkg,$router],[1=>['file','/dev/null','w'],2=>['file',$tmp.'/server.log','w']],$pipes);
for($i=0;$i<50;$i++){ $s=@fsockopen('127.0.0.1',$port,$e1,$e2,0.2);if($s){fclose($s);break;}usleep(100000); }
function http(string $method,string $url,$body=null,array $hdr=[]): array {
    $c=curl_init($url);
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_TIMEOUT=>120,CURLOPT_HTTPHEADER=>$hdr,CURLOPT_FOLLOWLOCATION=>$method==='GET',CURLOPT_MAXREDIRS=>3]);
    if($method==='POST'){ curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,is_array($body)?json_encode($body):(string)$body); }
    $r=(string)curl_exec($c);$code=(int)curl_getinfo($c,CURLINFO_RESPONSE_CODE);$hs=(int)curl_getinfo($c,CURLINFO_HEADER_SIZE);curl_close($c);
    return ['code'=>$code,'head'=>substr($r,0,$hs),'body'=>substr($r,$hs),'json'=>json_decode(substr($r,$hs),true)];
}
$B="http://127.0.0.1:$port";

/* Erste Anfrage richtet die Demo selbst ein */
$r=http('GET',"$B/");
t('Startseite sofort eingerichtet (kein Assistent)',$r['code']===200&&str_contains($r['body'],'bk-hero')&&str_contains($r['body'],'Live-Demo'),'HTTP '.$r['code'].' '.substr(strip_tags($r['body']),0,120));
t('Demo-Leiste im HTML der Website',str_contains($r['body'],'window.ELVADO_DEMO')&&str_contains($r['body'],'/cms/assets/demo.js'));
t('Sperrdatei und Zustand vorhanden',is_file("$pkg/cms/data/install.lock")&&is_file("$pkg/cms/demo-state/state.json"));
$st=json_decode((string)file_get_contents("$pkg/cms/demo-state/state.json"),true)?:[];
t('Zeitfenster läuft (≈10 Minuten)',abs(((int)($st['started']??0)+600)-(time()+600))<30&&($st['resets']??0)===1,json_encode($st));
$r=http('GET',"$B/willkommen/");
t('Beispielbeitrag der Einrichtung erreichbar',$r['code']===200,'HTTP '.$r['code']);
$r=http('GET',"$B/die-demo-ist-die-homepage/");
t('Demo-Beispielbeitrag erreichbar',$r['code']===200&&str_contains($r['body'],'Homepage-Baukasten'),'HTTP '.$r['code']);
/* Die Demo-Website ist die ElvadoPress-Homepage (Baukasten-Theme, Layout, Seiten, Menü) */
$h=http('GET',"$B/");
t('Homepage: Baukasten-Abschnitte (Hero, Karten, Text, Beiträge, Aufruf)',str_contains($h['body'],'bk-hero')&&substr_count($h['body'],'bk-features')>=1&&str_contains($h['body'],'bk-posts')&&str_contains($h['body'],'bk-cta')&&str_contains($h['body'],'<h1>ElvadoPress</h1>'),substr(strip_tags($h['body']),0,200));
t('Homepage: Produkttexte und Demo-Zugang',str_contains($h['body'],'Homepage-Baukasten')&&str_contains($h['body'],'Updates mit Rückschritt')&&str_contains($h['body'],'Fünf Themes')&&str_contains($h['body'],'ElvadoPress-Demo1')&&str_contains($h['body'],'/cms/?demo=1'));
t('Homepage: Seitenleiste mit Demo-Widgets (Logo, Zugang, Suche, Funktionen, Beiträge, Kategorien, Schlagwörter, Seiten, Themes, Archiv, GitHub)',str_contains($h['body'],'bk-home-grid')&&str_contains($h['body'],'id="sidebar"')&&substr_count($h['body'],'class="widget ')>=10&&str_contains($h['body'],'widget_search')&&str_contains($h['body'],'widget_tag_cloud')&&str_contains($h['body'],'widget_categories')&&str_contains($h['body'],'Das kann ElvadoPress')&&str_contains($h['body'],'Live-Demo')&&str_contains($h['body'],'Fünf Themes'),(string)substr_count($h['body'],'class="widget '));
t('Homepage: ElvadoPress-Logo in Kopf und Seitenleiste',str_contains($h['body'],'custom-logo')&&substr_count($h['body'],'/cms/assets/brand/elvadopress-logo.png')>=2&&is_file("$pkg/cms/assets/brand/elvadopress-logo.png")&&is_file("$pkg/cms/assets/demo/homepage-builder.svg"));
t('Homepage: Hero über die ganze Breite vor der Seitenleiste',strpos($h['body'],'bk-hero')!==false&&strpos($h['body'],'bk-hero')<strpos($h['body'],'bk-home-grid'));
$sp=http('GET',"$B/funktionen/");
t('Unterseite mit Seitenleiste und Widgets',$sp['code']===200&&str_contains($sp['body'],'id="sidebar"')&&str_contains($sp['body'],'widget_search'));
t('Homepage: Menü mit Seiten',str_contains($h['body'],'Funktionen')&&str_contains($h['body'],'Themes')&&str_contains($h['body'],'Selbst betreiben'));
foreach(['funktionen','themes','demo','selbst-betreiben'] as $sl){ $pg=http('GET',"$B/$sl/");t("Seite /$sl/ erreichbar",$pg['code']===200&&strlen(strip_tags($pg['body']))>300,'HTTP '.$pg['code']); }
t('Keine RicoReWi-Inhalte auf der Demo-Homepage',!stripos(str_replace('github.com/ricorewioriginal-collab/ElvadoPress','',$h['body']),'ricorewi')&&!stripos($h['body'],'anmacha'));
$r=http('GET',"$B/demo/");
t('Info-Seite erreichbar',$r['code']===200&&str_contains($r['body'],'Live-Demo'));
$r=http('GET',"$B/cms/index.php");
t('Verwaltung mit Demo-Skript',$r['code']===200&&str_contains($r['body'],'window.ELVADO_DEMO')&&str_contains($r['body'],'assets/demo.js'),'HTTP '.$r['code'].' '.substr($r['body'],0,200));
$r=http('GET',"$B/cms/api.php?action=demo_status");
t('demo_status öffentlich mit Zugang und Rücksetzzeit',($r['json']['status']??'')==='ok'&&($r['json']['user']??'')==='demo'&&($r['json']['reset_at']??0)>time()&&($r['json']['minutes']??0)===10,$r['body']);
$r=http('GET',"$B/cms/lib/demo.json");
t('Konfiguration nicht über die Website lesbar (Apache-Regel vorhanden)',is_file("$pkg/cms/lib/.htaccess")&&str_contains((string)file_get_contents("$pkg/cms/lib/.htaccess"),'demo.json'));

/* Anmeldung und gesperrte Aktionen */
$r=http('POST',"$B/cms/api.php?action=login",['username'=>'demo','password'=>'falsch']);
t('Falsches Passwort wird abgelehnt',$r['code']===401);
$r=http('POST',"$B/cms/api.php?action=login",['username'=>$cfg['user'],'password'=>$cfg['password']]);
$tok=(string)($r['json']['token']??'');
t('Anmeldung mit Demo-Zugang',($r['json']['status']??'')==='ok'&&$tok!=='',$r['body']);
$H=['X-ElvadoPress-Token: '.$tok,'Content-Type: application/json'];
$r=http('GET',"$B/cms/api.php?action=news_list",null,$H);
t('Verwaltung liefert Beiträge',($r['json']['status']??'')==='ok');
$ok=0;$blocked=['plugin_upload','theme_upload','wp_plugin_install','wp_theme_upload','database_config_save','assistant_chat','feed_test','redirects_save','member_register','wp_core_install','update_apply','update_rollback','update_config_save','lovable_sync'];
foreach($blocked as $a){ $r=http('POST',"$B/cms/api.php?action=$a",['x'=>1],$H);if($r['code']===403&&($r['json']['demo']??false))$ok++;else echo "nicht gesperrt: $a HTTP {$r['code']} {$r['body']}\n"; }
t('Gefährliche Aktionen sind gesperrt',$ok===count($blocked),"$ok/".count($blocked));
/* Alles andere ist frei (Benutzer, System, Backups, Medien, KI, Update-Anzeige …) */
$free=0;$freeList=['user_add','user_update','profile_update_self','backup_create','backup_restore','media_upload','branding_upload','news_thumbnail_upload','ai_status','update_status','update_check','lovable_get','stock_status'];
foreach($freeList as $a){ $r=http('POST',"$B/cms/api.php?action=$a",['x'=>1],$H);if(!($r['code']===403&&($r['json']['demo']??false)))$free++;else echo "zu Unrecht gesperrt: $a\n"; }
t('Benutzer, Backups, Medien, KI, Update-Anzeige sind in der Demo frei',$free===count($freeList),"$free/".count($freeList));
$r=http('POST',"$B/cms/api.php?action=system_save",['timezone'=>'Europe/Vienna'],$H);
t('Zeitzone/Name änderbar',$r['code']!==403,$r['body']);
$r=http('GET',"$B/cms/api.php?action=update_status",null,$H);
t('Update-Anzeige ohne Notfall-Token',($r['json']['status']??'')==='ok'&&($r['json']['rescue_token']??'x')==='',substr($r['body'],0,200));
$r=http('POST',"$B/cms/api.php?action=wp_admin_page",['page'=>'plugin-install.php','method'=>'GET'],$H);
t('WordPress-Plugin-Installation gesperrt',$r['code']===403&&($r['json']['demo']??false),$r['body']);
$r=http('POST',"$B/cms/api.php?action=wp_admin_ajax",['url'=>'/wp-admin/admin-ajax.php?action=install-plugin','method'=>'POST','body'=>''],$H);
t('WordPress-Plugin-Installation per Ajax gesperrt',$r['code']===403&&($r['json']['demo']??false),$r['body']);
$r=http('POST',"$B/cms/api.php?action=wp_admin_rest",['method'=>'POST','path'=>'/wp-json/wp/v2/plugins','body'=>'{}'],$H);
t('WordPress-REST: Plugins gesperrt',$r['code']===403&&($r['json']['demo']??false),$r['body']);
$r=http('GET',"$B/wp-admin/plugin-install.php");
t('Direkter Aufruf der Plugin-Installation gesperrt',$r['code']===403,'HTTP '.$r['code']);
$r=http('GET',"$B/xmlrpc.php");
t('xmlrpc.php gesperrt',$r['code']===403);
$r=http('POST',"$B/cms/api.php?action=news_save",['title'=>'Mein Demo-Beitrag','body_html'=>'<p>Hallo</p>','status'=>'published','category'=>'News','image_mode'=>'thumbnail','tags'=>''],$H);
t('Erlaubte Aktion (Beitrag speichern) funktioniert',($r['json']['status']??'')==='ok',$r['body']);
/* Veröffentlichungszeit im Format des Editor-Datumsfelds ("YYYY-MM-DDTHH:MM", vor wenigen Minuten): Beitrag ist sofort sichtbar und wird einheitlich gespeichert */
$r=http('POST',"$B/cms/api.php?action=news_save",['title'=>'Zeitformat-Test','body_html'=>'<p>Zeit</p>','status'=>'published','category'=>'News','image_mode'=>'thumbnail','tags'=>'','published_at'=>date('Y-m-d\TH:i',time()-600)],$H);
t('Beitrag mit Editor-Zeitformat gespeichert',($r['json']['status']??'')==='ok',$r['body']);
$nl=http('GET',"$B/cms/api.php?action=news_list",null,$H);$saved=null;foreach(($nl['json']['articles']??$nl['json']['news']??$nl['json']['items']??[]) as $x)if(($x['title']??'')==='Zeitformat-Test')$saved=$x;
t('Veröffentlichungszeit wird als "Y-m-d H:i:s" gespeichert',is_array($saved)&&preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',(string)($saved['published_at']??'')),json_encode($saved['published_at']??null));
$pub=http('GET',"$B/zeitformat-test/");
t('Beitrag mit Editor-Zeitformat ist öffentlich sichtbar',$pub['code']===200,'HTTP '.$pub['code']);
t('Mailversand ist in der Demo aus',(function() use($pkg){ $c='<?php define("ELVADO_DEMO",true);require "'.$pkg.'/cms/lib/mail.php";var_dump(elvado_send_mail("a@example.test","s","b","x@example.test"));'; exec('php -r '.escapeshellarg(substr($c,6)).' 2>&1',$o);return trim(implode('',$o))==='bool(false)'; })());

/* Rücksetzen nach Ablauf */
$sf="$pkg/cms/demo-state/state.json";
$s=json_decode((string)file_get_contents($sf),true);$s['started']=time()-601;file_put_contents($sf,json_encode($s));
$r=http('GET',"$B/cms/api.php?action=demo_status");
t('Nach Ablauf: neues Zeitfenster',($r['json']['reset_at']??0)>time()+500,$r['body']);
$s2=json_decode((string)file_get_contents($sf),true);
t('Rücksetzzähler erhöht',($s2['resets']??0)===2,json_encode($s2));
$r=http('GET',"$B/cms/api.php?action=news_list",null,$H);
t('Alte Sitzung nach Rücksetzen ungültig',$r['code']===401,'HTTP '.$r['code']);
$r=http('GET',"$B/mein-demo-beitrag/");
t('Eigener Beitrag nach Rücksetzen weg',$r['code']===404,'HTTP '.$r['code']);
$r=http('GET',"$B/die-demo-ist-die-homepage/");
t('Beispielinhalt nach Rücksetzen wieder da',$r['code']===200);
$r=http('GET',"$B/");
t('Homepage nach Rücksetzen wieder im Ausgangszustand',str_contains($r['body'],'bk-hero')&&str_contains($r['body'],'<h1>ElvadoPress</h1>'));
$r=http('POST',"$B/cms/api.php?action=login",['username'=>$cfg['user'],'password'=>$cfg['password']]);
t('Anmeldung nach Rücksetzen möglich',($r['json']['status']??'')==='ok');
$errs=array_values(array_filter(explode("\n",(string)@file_get_contents($tmp.'/server.log')),fn($l)=>preg_match('/PHP (Fatal|Parse|Warning|Notice|Deprecated)/',$l)&&!str_contains($l,'JIT is incompatible')));
t('Keine PHP-Fehler im Serverprotokoll',!$errs,implode("\n",array_slice($errs,0,5)));

echo $fail?"\n$fail von $n Prüfungen fehlgeschlagen\n":"Alle $n Prüfungen bestanden\n";
exit($fail?1:0);

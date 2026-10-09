<?php
// Zusammenspiel der Funktionen auf einer echten Installation (Einrichtungsassistent, HTTP, Wegwerf-Kopie): Plugins ↔ Inhalte ↔ Website ↔ Alexa ↔ Assistent ↔ Apps ↔ Weiterleitungen ↔ Backup.
// Aufruf: php scripts/test-integration.php
declare(strict_types=1);
$real=dirname(__DIR__);$n=0;$fail=0;
function t(string $name,bool $ok,string $info=''): void { global $n,$fail;$n++;if(!$ok){$fail++;echo "FAIL  $name".($info!==''?": $info":'')."\n";}else echo "  ok  $name\n"; }
function rm(string $d): void { if(!is_dir($d)||is_link($d)){@unlink($d);return;}foreach(scandir($d)?:[] as $f)if($f!=='.'&&$f!=='..')rm($d.'/'.$f);@rmdir($d); }
function http(string $method,string $url,array $headers=[],string $body=''): array {
    $ctx=stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'content'=>$body,'ignore_errors'=>true,'timeout'=>60,'follow_location'=>0]]);
    $b=@file_get_contents($url,false,$ctx);$st=0;$ck=[];$loc='';foreach($http_response_header??[] as $h){if(preg_match('~^HTTP/\S+\s+(\d{3})~',$h,$m))$st=(int)$m[1];if(stripos($h,'Set-Cookie:')===0)$ck[]=trim(explode(';',substr($h,11))[0]);if(stripos($h,'Location:')===0)$loc=trim(substr($h,9));}
    return [$st,$ck,(string)$b,$loc];
}
function site(string $real): array {
    $tmp=sys_get_temp_dir().'/elvado-int-'.bin2hex(random_bytes(4));mkdir($tmp,0755,true);
    exec('cp -a '.escapeshellarg($real.'/cms').' '.escapeshellarg($tmp.'/cms').' && cp '.escapeshellarg($real.'/index.php').' '.escapeshellarg($tmp.'/index.php'));
    foreach(glob($tmp.'/cms/data/*')?:[] as $f)if(!str_ends_with($f,'.example'))rm($f);
    foreach(glob($tmp.'/cms/data/.[a-z]*')?:[] as $f)rm($f);
    foreach(['backups','media','plugins'] as $d)rm($tmp.'/cms/'.$d);
    @mkdir($tmp.'/cms/data',0755,true);@mkdir($tmp.'/cms/plugins',0755,true);
    $port=random_int(20000,40000);
    $proc=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,'-t',$tmp],[['pipe','r'],['file',$tmp.'/srv.log','a'],['file',$tmp.'/srv.log','a']],$pipes);
    for($i=0;$i<50;$i++){ if(@fsockopen('127.0.0.1',$port,$e,$s,0.2))break;usleep(100000); }
    return [$tmp,'http://127.0.0.1:'.$port,$proc];
}
function install(string $base): array {
    [$st,$ck,$html]=http('GET',$base.'/cms/install.php');
    preg_match('/name="csrf" value="([a-f0-9]{32})"/',$html,$m);$csrf=$m[1]??'';
    $post=http_build_query(['csrf'=>$csrf,'do'=>'install','site_name'=>'Testseite','language'=>'de','timezone'=>'Europe/Berlin','username'=>'admin1','email'=>'a@example.org','password'=>'Sehr-gutes-Passwort-42','password2'=>'Sehr-gutes-Passwort-42','db_driver'=>'none','sample'=>'1','install_mode'=>'recommended']);
    return http('POST',$base.'/cms/install.php',['Content-Type: application/x-www-form-urlencoded','Cookie: elvado_inst='.$csrf],$post);
}
function api(string $base,string $action,array $body=[],string $tok=''): array { [,,$b]=http('POST',$base.'/cms/api.php?action='.$action,['Content-Type: application/json','X-ElvadoPress-Token: '.$tok],json_encode($body));return json_decode($b,true)?:['_raw'=>substr($b,0,200)]; }
function get(string $base,string $action,string $tok='',string $q=''): array { [,,$b]=http('GET',$base.'/cms/api.php?action='.$action.$q,['X-ElvadoPress-Token: '.$tok]);return json_decode($b,true)?:['_raw'=>substr($b,0,200)]; }

[$tmp,$base,$proc]=site($real);
register_shutdown_function(function() use($tmp,&$proc){ if(is_resource($proc))proc_terminate($proc);rm($tmp); });
[, , $html]=install($base);
t('Einrichtung (Empfohlen) läuft durch',str_contains($html,'Die Einrichtung ist abgeschlossen'),substr(strip_tags($html),0,200));
$tok=(string)(api($base,'login',['username'=>'admin1','password'=>'Sehr-gutes-Passwort-42'])['token']??'');
t('Anmeldung liefert lokalen Token',str_starts_with($tok,'local_'));
t('Zugriff und Produktangaben stimmen (kein Fremdbezug in der Antwort)',(function() use($base,$tok){ $a=get($base,'access',$tok);return ($a['status']??'')==='ok'&&!preg_match('/ricorewi|anmacha|senderwelt|control.?center/i',json_encode($a)); })());

// ---------- Radio-Plugin ↔ Plugin-Verwaltung ↔ Website
$r=api($base,'np_install',['id'=>'elvado-radio'],$tok);$a=api($base,'np_activate',['id'=>'elvado-radio'],$tok);
t('Radio-Plugin lässt sich installieren und aktivieren',($r['status']??'')==='ok'&&($a['status']??'')==='ok',json_encode([$r,$a]));
$s=api($base,'np_settings_save',['id'=>'elvado-radio','values'=>['stations'=>"mein-radio | Mein Radio | https://stream.example.org/live.mp3",'schedule'=>"täglich | 00:00-24:00 | Rund um die Uhr | mein-radio"]],$tok);
t('Radio-Einstellungen werden gespeichert',($s['status']??'')==='ok',json_encode($s));
$art=api($base,'news_save',['title'=>'Radio und Formular','slug'=>'radio-test','status'=>'published','body_html'=>'<p>[elvado_radio station="mein-radio"]</p><p>[elvado_radio_now station="mein-radio"]</p><p>[elvado_radio_schedule]</p><p>[elvado_form id="kontakt"]</p>','excerpt'=>'Test'],$tok);
t('Beitrag mit Shortcodes wird gespeichert',($art['status']??'')==='ok',json_encode($art));
[$st,,$page]=http('GET',$base.'/radio-test/');
t('Website liefert den Beitrag aus',$st===200,"HTTP $st");
t('Shortcode Player: Audio-Element mit https-Stream, Stream erst nach Klick',str_contains($page,'<audio controls preload="none" src="https://stream.example.org/live.mp3"'));
t('Shortcode „Jetzt läuft“ und Sendeplan werden ersetzt (nicht als Rohtext)',str_contains($page,'Rund um die Uhr')&&!str_contains($page,'[elvado_radio_now')&&!str_contains($page,'[elvado_radio_schedule'));
t('Kein Rohtext anderer Shortcodes auf der Seite',!str_contains($page,'[elvado_radio '));

// ---------- Weiterleitungen (Plugin) ↔ Beitragsadresse
$id=(int)($art['article']['id']??($art['id']??0));
if($id<=0){ foreach((array)(get($base,'news_list',$tok)['articles']??get($base,'news_list',$tok)['items']??[]) as $x)if(($x['slug']??'')==='radio-test')$id=(int)$x['id']; }
$mv=api($base,'news_save',['id'=>$id,'title'=>'Radio und Formular','slug'=>'radio-neu','status'=>'published','body_html'=>'<p>[elvado_radio station="mein-radio"]</p>','excerpt'=>'Test'],$tok);
[$st,,,$loc]=http('GET',$base.'/radio-test/');
t('Geänderte Beitragsadresse: alte Adresse leitet automatisch weiter (Plugin Redirects)',in_array($st,[301,302],true)&&str_contains($loc,'radio-neu'),"HTTP $st → $loc");
[$st]=http('GET',$base.'/radio-neu/');
t('Neue Adresse ist erreichbar',$st===200,"HTTP $st");

// ---------- Alexa ↔ Assistent ↔ Website
$sv=api($base,'save',['section'=>'alexa','value'=>['topics'=>['oeffnung'=>['title'=>'Öffnungszeiten','text'=>'Wir haben Montag bis Freitag von neun bis achtzehn Uhr geöffnet.']]]],$tok);
t('Alexa-Themen werden gespeichert',($sv['status']??'')==='ok',json_encode($sv));
$pub=get($base,'alexa_config');
t('Alexa: öffentliche Konfiguration enthält das Thema und die Beiträge',($pub['status']??'')==='ok'&&in_array('Öffnungszeiten',array_column((array)($pub['topics']??[]),'title'),true)&&!empty($pub['news']));
$ag=get($base,'alexa_get',$tok);
t('Alexa: Verwaltung liefert Sprachmodell-Hinweise ohne Fehler',($ag['status']??'')==='ok'&&isset($ag['topics']));
[$st403]=http('POST',$base.'/cms/api.php?action=assistant_chat',['Content-Type: application/json'],json_encode(['messages'=>[['role'=>'user','content'=>'Hallo']]]));
t('Assistent ist nach der Einrichtung aus: keine Anfrage an externe KI-Dienste ohne Zutun des Betreibers',$st403===403);
[,,$hp]=http('GET',$base.'/');
t('Ohne eingeschalteten Assistenten bindet die Website kein Chat-Skript ein',!str_contains($hp,'assistant-widget'));
$as=api($base,'save',['section'=>'assistant','value'=>['enabled'=>true,'features'=>['research'=>false],'providers'=>array_map(fn($p)=>['id'=>$p['id'],'enabled'=>false],(array)(get($base,'assistant_status',$tok)['providers']??[]))]],$tok);
t('Assistent ist ab Werk aus und lässt sich einschalten (ohne Anbieter: Antwort nur aus den Inhalten der Website)',($as['status']??'')==='ok',json_encode($as));
$chat=api($base,'assistant_chat',['messages'=>[['role'=>'user','content'=>'Wann habt ihr geöffnet? Öffnungszeiten?']]]);
t('Assistent antwortet aus dem Alexa-Thema (ohne KI-Anbieter, aus Wissen der Website)',($chat['status']??'')==='ok'&&str_contains(json_encode($chat,JSON_UNESCAPED_UNICODE),'neun bis achtzehn'),substr(json_encode($chat,JSON_UNESCAPED_UNICODE),0,300));

// ---------- Apps ↔ Build-Assistent ↔ Startabfrage
$br=api($base,'app_build_brand_save',['id'=>'meinshop','applicationId'=>'de.example.meinshop','appName'=>'Mein Shop','type'=>'content','platforms'=>['android','windows'],'site'=>'https://www.example.org'],$tok);
t('App-Baukasten: eigene App wird angelegt',($br['status']??'')==='ok',json_encode($br));
$ov=get($base,'apps_overview',$tok);
t('Apps verwalten listet die eigene App',($ov['status']??'')==='ok'&&str_contains(json_encode($ov),'meinshop'));
$tabs=api($base,'np_call',['id'=>'elvado-ai','call'=>'status','args'=>[]],$tok);
t('KI-Plugin ist erreichbar (np_call) und enthält keine Schlüssel',(isset($tabs['status'])||isset($tabs['ok'])||isset($tabs['configured']))&&!preg_match('/api[_-]?key|secret|token|password|passwort/i',json_encode($tabs)),json_encode($tabs));
$ac=get($base,'app_config','','&brand=meinshop&platform=android&version=3.0.0');
t('App-Startabfrage: öffentlich, gültig, Marke erkannt, kein Aussperren',($ac['status']??'')==='ok'&&($ac['brand']??'')==='meinshop'&&array_key_exists('update',$ac)&&array_key_exists('maintenance',$ac)&&array_key_exists('tabs',$ac));
$bad=get($base,'app_config','','&brand=unbekannt&platform=windows&version=1');
t('App-Startabfrage mit unbekannter Marke bleibt gültig',($bad['status']??'')==='ok');

// ---------- Robustheit: aktives Theme fehlt (gelöscht/umbenannt, z. B. nach einem Update) → Standard-Theme statt leerer Seite
$of=$tmp.'/cms/data/.wp/options.json';
if(is_file($of)){
    $orig=(string)file_get_contents($of);
    file_put_contents($of,str_replace('elvado-classic','gibt-es-nicht',$orig));
    [$st,,$hb]=http('GET',$base.'/radio-neu/');
    t('Fehlendes aktives Theme: Website fällt auf das Standard-Theme zurück (keine leere Seite)',$st===200&&str_contains($hb,'<html')&&str_contains($hb,'<audio'),"HTTP $st, ".strlen($hb).' Bytes');
    file_put_contents($of,$orig);
}else t('Optionsdatei der WordPress-Schicht vorhanden',false,$of);

// ---------- Sicherheit der Schnittstellen
t('Verwaltungsaktionen ohne Anmeldung werden abgewiesen',(function() use($base){ foreach(['apps_overview','alexa_get','np_list','system_get','app_build_state'] as $a){ [$st]=http('GET',$base.'/cms/api.php?action='.$a);if($st!==401)return false; } return true; })());
t('Altes Token-Format wird abgelehnt (kein Fremd-Login)',(function() use($base){ [$st]=http('GET',$base.'/cms/api.php?action=np_list',['X-ElvadoPress-Token: fremd123']);return $st===401; })());
t('Veralteter Header wird nicht mehr beachtet',(function() use($base,$tok){ [$st]=http('GET',$base.'/cms/api.php?action=np_list',['X-AnMaCha-Token: '.$tok]);return $st===401; })());

// ---------- Backup ↔ Plugins
$bk=api($base,'np_call',['id'=>'elvado-backup','call'=>'overview','args'=>[]],$tok);
t('Backup-Plugin liefert Übersicht',isset($bk['blocks']),json_encode($bk));

// ---------- Konsistenz der Navigation
[$stNo]=http('GET',$base.'/cms/api.php?action=admin_links_get');
t('Eigene Menülinks: ohne Anmeldung nicht lesbar',$stNo===401,"HTTP $stNo");
$lk=api($base,'admin_links_save',['links'=>[['label'=>'Statistik','url'=>'https://stats.example.org/','icon'=>'chart-line','mode'=>'frame'],['label'=>'Hilfe','url'=>'/hilfe/','mode'=>'new']]],$tok);
t('Eigene Menülinks: Administrator speichert zwei Links',($lk['status']??'')==='ok'&&count($lk['links']??[])===2,json_encode($lk));
[$stBad,,$bBad]=http('POST',$base.'/cms/api.php?action=admin_links_save',['Content-Type: application/json','X-ElvadoPress-Token: '.$tok],json_encode(['links'=>[['label'=>'X','url'=>'javascript:alert(1)']]]));
t('Eigene Menülinks: unsichere Adresse wird mit 400 abgelehnt',$stBad===400&&str_contains($bBad,'Adresse'),"HTTP $stBad");
$lg=get($base,'admin_links_get',$tok);
t('Eigene Menülinks: gespeicherte Links kommen zurück, die abgelehnte Eingabe hat nichts überschrieben',($lg['status']??'')==='ok'&&array_column((array)($lg['links']??[]),'label')===['Statistik','Hilfe']&&($lg['links'][0]['mode']??'')==='frame');
[,,$adm]=http('GET',$base.'/cms/');
t('Verwaltung wird ausgeliefert ohne Platzhalter/Fehlermeldungen',str_contains($adm,'cmsApp')&&!str_contains($adm,'Fatal error')&&!str_contains($adm,'Warning:')&&!str_contains($adm,'Notice:'));
preg_match_all('~<script src="(assets/[^"?]+)~',$adm,$sc);$bad=[];foreach(array_unique($sc[1]) as $s)if(!is_file($tmp.'/cms/'.$s))$bad[]=$s;
t('Alle in der Verwaltung eingebundenen Skripte existieren',$bad===[],implode(',',$bad));
preg_match_all('~<link[^>]+href="(assets/[^"?]+)~',$adm,$cs);$bad=[];foreach(array_unique($cs[1]) as $s)if(!is_file($tmp.'/cms/'.$s))$bad[]=$s;
t('Alle eingebundenen Stylesheets existieren',$bad===[],implode(',',$bad));
$log=is_file($tmp.'/srv.log')?(string)file_get_contents($tmp.'/srv.log'):'';
$log=implode("\n",preg_grep('/JIT is incompatible/',explode("\n",$log),PREG_GREP_INVERT));   // bekannte Meldung mancher CI-Läufer, kein Fehler des CMS
t('PHP-Server-Protokoll ohne Fatal/Warning/Deprecated',!preg_match('/Fatal error|Warning:|Deprecated:|Uncaught/',$log),substr(implode("\n",array_slice(preg_grep('/Fatal error|Warning:|Deprecated:|Uncaught/',explode("\n",$log)),0,4)),0,500));

echo "\n".($n-$fail)." von $n Prüfungen bestanden\n";
exit($fail?1:0);

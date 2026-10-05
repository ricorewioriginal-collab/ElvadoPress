<?php
// Baut das eigenständige CMS (scripts/build-standalone.php), prüft, dass keine RicoReWi-Inhalte enthalten sind, richtet es über den
// Assistenten ein (PHP-Entwicklungsserver) und prüft die frische Installation. Aufruf: php scripts/test-standalone-build.php
declare(strict_types=1);
$root=dirname(__DIR__);
// Paket-Modus: Liegt das Skript in einem fertigen Paket (ohne Bauskript), wird dieses Paket selbst geprüft statt gebaut.
$pkgMode=!is_file($root.'/scripts/build-standalone.php');
require_once $root.'/cms/lib/pack.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
function rmrf(string $d): void { if(!is_dir($d))return; foreach(scandir($d) as $f){if($f==='.'||$f==='..')continue;$p=$d.'/'.$f;is_dir($p)&&!is_link($p)?rmrf($p):@unlink($p);} @rmdir($d); }
$tmp=sys_get_temp_dir().'/rrw-sab-'.bin2hex(random_bytes(4));mkdir($tmp);$pkg=$tmp.'/pkg';$proc=null;
register_shutdown_function(function() use($tmp,&$proc){ if(is_resource($proc))proc_terminate($proc);rmrf($tmp); });

/* Bauen (im Paket-Modus: das Paket in einen Arbeitsordner kopieren, damit die Installation das Original nicht verändert) */
if($pkgMode){
    exec('cp -a '.escapeshellarg($root).' '.escapeshellarg($pkg).' 2>&1',$o,$rc);
    t('Paket kopiert',$rc===0,implode("\n",$o));
    foreach(glob($pkg.'/cms/data/*')?:[] as $f)if(basename($f)!=='.htaccess'&&!str_ends_with($f,'.example'))exec('rm -rf '.escapeshellarg($f));
    foreach(['.git'] as $x)if(is_dir("$pkg/$x"))exec('rm -rf '.escapeshellarg("$pkg/$x"));
}else{
exec('php '.escapeshellarg($root.'/scripts/build-standalone.php').' '.escapeshellarg($pkg).' --zip='.escapeshellarg($tmp.'/cms.zip').' 2>&1',$o,$rc);
t('Bauen gelingt',$rc===0,implode("\n",$o));
t('Zielordner ist nicht leer → Abbruch',(function() use($root,$pkg){ exec('php '.escapeshellarg($root.'/scripts/build-standalone.php').' '.escapeshellarg($pkg).' 2>&1',$o,$rc);return $rc!==0; })());
t('ZIP erzeugt',is_file($tmp.'/cms.zip')&&filesize($tmp.'/cms.zip')>100000);

}

/* Inhalt */
t('Kein Portal-Design im Paket',array_values(array_diff(scandir($pkg.'/cms/themes'),['.','..']))===['rrw-classic']);
foreach(['index.html','news.html','sender.html','assets','android','windows-native','alexa','brands','downloads','app-screenshots','cms/lib/alexa-skill/lambda/node_modules','cms/standalone','cms/content/pages/partner','cms/docs/BRANDS.md','cms/docs/PARTNER.md'] as $x)t("Fehlt im Paket: $x",!file_exists("$pkg/$x"));
foreach(['index.php','.htaccess','INSTALL.md','cms/api.php','cms/install.php','cms/wp-front.php','cms/lib/pack.php','cms/themes/rrw-classic/style.css','cms/data/.htaccess'] as $x)t("Im Paket: $x",file_exists("$pkg/$x"));
t('Datenordner leer (nur Schutzdateien)',(function() use($pkg){ foreach(scandir($pkg.'/cms/data') as $f)if($f!=='.'&&$f!=='..'&&$f!=='.htaccess'&&!str_ends_with($f,'.example'))return false;return true; })());
$bad=0;$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pkg,FilesystemIterator::SKIP_DOTS));
foreach($it as $f)if($f->getExtension()==='php'){ exec('php -l '.escapeshellarg($f->getPathname()).' 2>&1',$o2,$r2);if($r2!==0){$bad++;echo "Syntaxfehler: ".$f->getPathname()."\n";} }
t('Alle PHP-Dateien im Paket sind syntaktisch in Ordnung',$bad===0);

/* Server */
$router=$tmp.'/router.php';
file_put_contents($router,'<?php $p=(string)parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH);$f=$_SERVER["DOCUMENT_ROOT"].$p;if($p==="/"){require $_SERVER["DOCUMENT_ROOT"]."/index.php";return true;}if(str_starts_with($p,"/cms/")||is_file($f))return false;require $_SERVER["DOCUMENT_ROOT"]."/cms/wp-front.php";return true;');
$port=random_int(20000,40000);
$proc=proc_open(['php','-S',"127.0.0.1:$port",'-t',$pkg,$router],[1=>['file','/dev/null','w'],2=>['file',$tmp.'/server.log','w']],$pipes);
for($i=0;$i<50;$i++){ $s=@fsockopen('127.0.0.1',$port,$e1,$e2,0.2);if($s){fclose($s);break;}usleep(100000); }
$jar=$tmp.'/jar.txt';
function http(string $method,string $url,array $post=[],array $hdr=[],bool $follow=false): array {
    global $jar;$c=curl_init($url);
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>$follow,CURLOPT_COOKIEJAR=>$jar,CURLOPT_COOKIEFILE=>$jar,CURLOPT_TIMEOUT=>60,CURLOPT_HTTPHEADER=>$hdr]);
    if($method==='POST'){ curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,isset($post['__json'])?$post['__json']:http_build_query($post)); }
    $r=(string)curl_exec($c);$code=(int)curl_getinfo($c,CURLINFO_RESPONSE_CODE);$hs=(int)curl_getinfo($c,CURLINFO_HEADER_SIZE);curl_close($c);
    return ['code'=>$code,'head'=>substr($r,0,$hs),'body'=>substr($r,$hs)];
}
$B="http://127.0.0.1:$port";
$r=http('GET',"$B/");
t('Vor der Einrichtung: Startseite leitet zur Verwaltung',$r['code']===302&&str_contains($r['head'],'/cms/'),$r['head']);
$r=http('GET',"$B/cms/install.php");
t('Einrichtungsassistent erreichbar',$r['code']===200&&str_contains($r['body'],'name="csrf"'));
preg_match('/name="csrf" value="([a-f0-9]{32})"/',$r['body'],$m);
$pw='Teststark-Passwort-42';
$r=http('POST',"$B/cms/install.php",['csrf'=>$m[1]??'','do'=>'install','site_name'=>'Mein Test-Radio','language'=>'de','timezone'=>'Europe/Berlin','username'=>'chef','display_name'=>'Chefredaktion','email'=>'chef@example.test','password'=>$pw,'password2'=>$pw,'db_driver'=>'none','sample'=>'1']);
t('Einrichtung abgeschlossen',$r['code']===200&&(stripos($r['body'],'abgeschlossen')!==false),substr(strip_tags($r['body']),0,300));
t('Sperrdatei vorhanden',is_file($pkg.'/cms/data/install.lock'));
t('Neutrales Theme eingeschaltet',is_file($pkg.'/cms/data/.wp/front-on'));
$opts=json_decode((string)@file_get_contents($pkg.'/cms/data/.wp/options.json'),true)?:[];
t('Theme „rrw-classic“ gewählt',isset($opts['stylesheet']['v'])&&@unserialize($opts['stylesheet']['v'])==='rrw-classic');

/* Website */
$r=http('GET',"$B/");
t('Startseite wird vom Theme ausgeliefert',$r['code']===200&&str_contains($r['body'],'Mein Test-Radio'),'HTTP '.$r['code']);
t('Startseite ohne RicoReWi-Inhalte',!preg_match('/ricorewi|anmacha|senderwelt/i',$r['body']));
$r=http('GET',"$B/cms/rss.php");
t('RSS-Feed: eigener Titel und eigene Beiträge',$r['code']===200&&str_contains($r['body'],'<title>Mein Test-Radio – News &amp; Magazin</title>')&&str_contains($r['body'],'Willkommen bei Mein Test-Radio'),substr($r['body'],0,600));
t('RSS-Feed ohne RicoReWi-Inhalte (Generator-Angabe folgt dem Produktnamen des Pakets)',!preg_match('/ricorewi|anmacha|senderwelt/i',(string)preg_replace('~<generator>.*?</generator>~s','',$r['body'])));
$r=http('GET',"$B/willkommen/");
t('Beispielbeitrag erreichbar',$r['code']===200&&str_contains($r['body'],'Willkommen bei Mein Test-Radio'),'HTTP '.$r['code']);
$r=http('GET',"$B/gibt-es-nicht/");
t('Unbekannte Adresse → 404',$r['code']===404);

/* Verwaltung */
$r=http('POST',"$B/cms/api.php?action=login",['__json'=>json_encode(['username'=>'chef','password'=>$pw])],['Content-Type: application/json']);
$login=json_decode($r['body'],true)?:[];$tok=(string)($login['token']??'');
t('Anmeldung',$tok!=='',$r['body']);
$H=['X-AnMaCha-Token: '.$tok];
$g=json_decode(http('GET',"$B/cms/api.php?action=get",[],$H)['body'],true)?:[];
$cfg=$g['config']??[];
t('Paket ist nicht aktiv',($g['packs']['ricorewi-radio']??null)===false,json_encode($g['packs']??null));
$ids=array_column($cfg['widgets']??[],'id');
t('Nur das Beitrags-Widget vorhanden (keine Radio-Widgets)',$ids===['w-news'],json_encode($ids));
$menus=json_encode($cfg['menus']??[]);
t('Kein Favoriten-Menü',!str_contains($menus,'favoriten'));
t('Keine Partnerseite',!in_array('partner',array_column($cfg['pages']??[],'slug'),true));
t('Keine vorgefertigten Rechtstexte',trim(strip_tags((string)($cfg['legal']['imprint_content']??'')))===''&&trim(strip_tags((string)($cfg['legal']['privacy_content']??'')))==='');
$brands=$cfg['brands']['items']??[];
t('Nur die eigene Marke, benannt nach der Website',count($brands)===1&&($brands[0]['name']??'')==='Mein Test-Radio',json_encode(array_column($brands,'name')));
preg_match_all('/.{30}(ricorewi|anmacha|senderwelt).{30}/i',preg_replace('/"(id|default)":"ricorewi-radio"/','',json_encode($cfg)),$mm);t('Keine RicoReWi-Texte in der Konfiguration',empty($mm[0]),implode(' | ',$mm[0]));
$themes=json_decode(http('GET',"$B/cms/api.php?action=theme_catalog",[],$H)['body'],true)?:[];
t('Keine RicoReWi-Portal-Themes im Katalog',empty($themes['themes']??[]));
$r=http('GET',"$B/cms/");
t('Verwaltung lädt',$r['code']===200&&str_contains($r['body'],'panel-settings'));
$hl=json_decode(http('GET',"$B/cms/api.php?action=health",[],$H)['body'],true)?:[];
t('Dateisystem-Prüfung meldet keine Portal-Dateien (Startseite/Feed)',($hl['healthy']??false)===true&&!isset($hl['checks']['index_file'])&&!isset($hl['checks']['rss_file']),json_encode($hl));
$pv=$r['body'];
t('Verwaltung: Paket als nicht vorhanden gemeldet',str_contains($pv,'window.CMS_PACKS_AVAILABLE={"ricorewi-radio":false}'));
t('Verwaltung: Soziale Profile neutral beschriftet',!str_contains($pv,'AnMaCha · TikTok')&&!str_contains($pv,'RicoReWi · TikTok'));
foreach(['news-editor.js','alexa-manager.js','apps-manager.js','theme-manager.js'] as $jsf){ $js=(string)@file_get_contents($pkg.'/cms/assets/'.$jsf);
    t("$jsf: RicoReWi-/AnMaCha-Texte nur hinter der Portal-Prüfung",$js!==''&&preg_match_all('/(?:RicoReWi|AnMaCha)[^\n]{0,60}/',$js,$mm)>=0&&!preg_match('/>AnMaCha Redaktion<|\bname\s*=\s*[\'"]RicoReWi Radio[\'"]/',$js)); }

/* KI-Assistent als neutrale App-Erweiterung */
$as=$cfg['assistant']??[];
t('Assistent: neutrale Vorgaben im Baukasten-Modus',($as['neutral']??null)===true&&!preg_match('/ricorewi|anmacha|senderwelt|studiomail/i',json_encode(array_diff_key($as,['providers'=>1,'features'=>1]))),json_encode(array_diff_key($as,['providers'=>1,'features'=>1])));
t('Assistent: Podcast, Studiomail und Voicemail des Herstellers sind aus',($as['features']['podcast']??1)===false&&($as['features']['studiomail']??1)===false&&($as['features']['voicemail']??1)===false);
$prov=[];foreach($as['providers']??[] as $pr)$prov[]=['id'=>$pr['id'],'enabled'=>false];   // keine echten KI-Aufrufe im Test
$sv=http('POST',"$B/cms/api.php?action=save",['__json'=>json_encode(['section'=>'assistant','value'=>['enabled'=>true,'name'=>'Test-Assistent','knowledge'=>'Wir sind Mein Test-Radio.','stations'=>"meinradio\nlaut.fm/zweites-24",'providers'=>$prov]])],array_merge($H,['Content-Type: application/json']));
t('Assistent: Einstellungen speichern',(json_decode($sv['body'],true)['status']??'')==='ok',$sv['body']);
$g2=json_decode(http('GET',"$B/cms/api.php?action=get",[],$H)['body'],true)?:[];
t('Assistent: eigene Sender gespeichert (Zeilen, laut.fm-Adresse bereinigt)',($g2['config']['assistant']['stations']??null)===['meinradio','zweites-24'],json_encode($g2['config']['assistant']['stations']??null));
$chat=json_decode(http('POST',"$B/cms/api.php?action=assistant_chat",['__json'=>json_encode(['messages'=>[['role'=>'user','content'=>'Welche Sender gibt es bei euch?']]])],['Content-Type: application/json'])['body'],true)?:[];
t('Assistent: antwortet ohne KI-Anbieter aus den eigenen Sendern',($chat['status']??'')==='ok'&&($chat['provider']??'')==='offline'&&str_contains((string)($chat['reply']??''),'meinradio'),json_encode($chat));
t('Assistent: Antwort ohne RicoReWi-Inhalte',!preg_match('/ricorewi|anmacha|senderwelt/i',(string)($chat['reply']??'')));
/* KI-Assistent: Website-Modus, Chat-Fenster auf der Website, Modelle */
$fr=http('GET',"$B/");
t('Assistent: Chat-Fenster auf der Website, wenn eingeschaltet',str_contains($fr['body'],'assistant-widget.js')&&str_contains($fr['body'],'data-name="Test-Assistent"'),substr($fr['body'],-300));
t('Assistent: Chat-Skript wird ausgeliefert',http('GET',"$B/cms/assets/assistant-widget.js")['code']===200);
$sv=http('POST',"$B/cms/api.php?action=save",['__json'=>json_encode(['section'=>'assistant','value'=>['enabled'=>true,'name'=>'Berater','mode'=>'website','order_mode'=>'manual','temperature'=>0.5,'knowledge'=>'Wir sind Mein Test-Radio.','providers'=>$prov]])],array_merge($H,['Content-Type: application/json']));
t('Assistent: Website-Modus speichern',(json_decode($sv['body'],true)['status']??'')==='ok',$sv['body']);
$ac=json_decode(http('GET',"$B/cms/api.php?action=get",[],$H)['body'],true)['config']['assistant']??[];
t('Assistent: Modus, Reihenfolge und Temperatur gespeichert',($ac['mode']??'')==='website'&&($ac['order_mode']??'')==='manual'&&($ac['temperature']??0)===0.5&&($ac['name']??'')==='Berater',json_encode(array_diff_key($ac,['providers'=>1])));
$chat=json_decode(http('POST',"$B/cms/api.php?action=assistant_chat",['__json'=>json_encode(['messages'=>[['role'=>'user','content'=>'Willkommen bei euch?']]])],['Content-Type: application/json'])['body'],true)?:[];
t('Assistent: Website-Modus antwortet aus den Beiträgen, ohne Radio-Bezug',($chat['status']??'')==='ok'&&($chat['provider']??'')==='offline'&&str_contains((string)($chat['reply']??''),'Willkommen bei Mein Test-Radio')&&!preg_match('/sendeplan|laut\.fm|ricorewi|senderwelt/i',(string)($chat['reply']??'')),json_encode($chat));
$ml=json_decode(http('POST',"$B/cms/api.php?action=assistant_models",['__json'=>json_encode(['base_url'=>'http://evil.example.org/v1'])],array_merge($H,['Content-Type: application/json']))['body'],true)?:[];
t('Assistent: Modellliste prüft die Adresse',($ml['status']??'')==='ok'&&($ml['ok']??null)===false,json_encode($ml));
t('Assistent: Modellliste nur für Administratoren',in_array(http('POST',"$B/cms/api.php?action=assistant_models",['__json'=>'{}'],['Content-Type: application/json'])['code'],[401,403],true));
$sv=http('POST',"$B/cms/api.php?action=save",['__json'=>json_encode(['section'=>'assistant','value'=>['enabled'=>false,'providers'=>$prov]])],array_merge($H,['Content-Type: application/json']));
t('Assistent: ausgeschaltet → kein Chat-Fenster',!str_contains(http('GET',"$B/")['body'],'assistant-widget.js'));
$sv=http('POST',"$B/cms/api.php?action=save",['__json'=>json_encode(['section'=>'assistant','value'=>['enabled'=>true,'name'=>'Test-Assistent','stations'=>"meinradio\nlaut.fm/zweites-24",'providers'=>$prov]])],array_merge($H,['Content-Type: application/json']));
$sm=http('POST',"$B/cms/api.php?action=assistant_send",['__json'=>json_encode(['name'=>'x','message'=>'Hallo Studio'])],['Content-Type: application/json']);
t('Assistent: Studiomail des Herstellers nicht verfügbar',in_array($sm['code'],[403,503],true),(string)$sm['code']);

/* Radioverzeichnis gehört zum RicoReWi-Paket und fehlt im eigenständigen CMS */
foreach(['directory_admin_get'=>true,'directory_search'=>false,'directory_random'=>false] as $act=>$auth){
    $dr=http('GET',"$B/cms/api.php?action=$act",[],$auth?$H:[]);
    t("Radioverzeichnis: Aktion $act ist nicht verfügbar",$dr['code']===404&&(json_decode($dr['body'],true)['status']??'')==='error','HTTP '.$dr['code']);
}
t('Radioverzeichnis: Reiter nur mit Paket sichtbar',str_contains($pv,'data-pack="ricorewi-radio" data-tab="directory"')&&!str_contains($pv,'data-pack-app="ricorewi-radio" data-tab="directory"'));
t('Radioverzeichnis: keine Marke hat es aktiviert',(function() use($cfg){ foreach((array)($cfg['brands']['items']??[]) as $b)if(!empty($b['directory']))return false;return true; })());

/* Verbundene Dienste: eigene Dienste statt der festen Liste des Herstellers */
$sv=http('POST',"$B/cms/api.php?action=save",['__json'=>json_encode(['section'=>'services','value'=>['radio_portal'=>'https://x.example.org','items'=>[['name'=>'Meine Cloud','url'=>'https://cloud.example.org','kind'=>'media'],['name'=>'Intern','url'=>'http://127.0.0.1/status','kind'=>'api'],['name'=>'Aus','url'=>'/hilfe/','check'=>false]]]])],array_merge($H,['Content-Type: application/json']));
t('Dienste: eigene Dienste speichern',(json_decode($sv['body'],true)['status']??'')==='ok',$sv['body']);
$g2=json_decode(http('GET',"$B/cms/api.php?action=get",[],$H)['body'],true)['config']['services']??[];
t('Dienste: nur eigene Einträge gespeichert (keine Felder des Herstellers)',array_keys($g2)===['items']&&array_column($g2['items'],'id')===['meine-cloud','intern','aus'],json_encode($g2));
$ss=http('GET',"$B/cms/api.php?action=services_status",[],$H);$sj=json_decode($ss['body'],true)?:[];$by=[];foreach($sj['services']??[] as $x)$by[$x['id']]=$x;
t('Dienste: Prüfung liefert Zustand je Dienst (lokal gesperrt, abgeschaltet übersprungen)',$ss['code']===200&&($by['intern']['state']??'')==='blocked'&&($by['aus']['state']??'')==='skipped'&&isset($by['meine-cloud']['state']),$ss['body']);
t('Dienste: Prüfung nur für angemeldete Administratoren',http('GET',"$B/cms/api.php?action=services_status")['code']===401||http('GET',"$B/cms/api.php?action=services_status")['code']===403);
$ar=json_decode(http('GET',"$B/cms/api.php?action=architecture",[],$H)['body'],true)?:[];
t('Systemübersicht: neutrale Website-Bezeichnung, keine Core-Sender (Produktnamen folgen dem Paket)',($ar['components'][0]['name']??'')==='Website'&&($ar['core_stations']??null)===[],json_encode($ar));
/* Speichern von Bereichen, die eine frische Installation noch nicht angelegt hat (Seiten, Soziale Profile, Rechtliches) */
foreach(['pages'=>[],'social'=>['ricorewi_tiktok'=>''],'legal'=>['imprint_mode'=>'shared']] as $sec=>$val){
    $r=http('POST',"$B/cms/api.php?action=save",['__json'=>json_encode(['section'=>$sec,'value'=>$val])],array_merge($H,['Content-Type: application/json']));
    t("Bereich ‚".$sec."‘ lässt sich in einer frischen Installation speichern",(json_decode($r['body'],true)['status']??'')==='ok',$r['body']);
}
$r=http('POST',"$B/cms/api.php?action=save",['__json'=>json_encode(['section'=>'rss','value'=>['enabled'=>true]])],array_merge($H,['Content-Type: application/json']));
t('Feed-Titel ohne Angabe folgt dem Website-Namen (nicht dem des Herstellers)',str_contains((string)(json_decode($r['body'],true)['value']['title']??''),'Mein Test-Radio'),$r['body']);
/* Alexa-Skill als Baukasten (ohne RicoReWi-Katalog) */
$ax=json_decode(http('GET',"$B/cms/api.php?action=alexa_get",[],$H)['body'],true)?:[];
t('Alexa: Baukasten-Modus ohne Sender',($ax['neutral']??null)===true&&($ax['stations']??null)===[],json_encode($ax));
t('Alexa: Aufrufname aus dem Website-Namen',($ax['invocation']??'')==='mein test radio',(string)($ax['invocation']??''));
$sv=http('POST',"$B/cms/api.php?action=save",['__json'=>json_encode(['section'=>'alexa','value'=>['default_station'=>'meinradio','stations'=>['meinradio'=>['enabled'=>true,'title'=>'Mein Radio','extra'=>['meins']],'zweites-24'=>['enabled'=>true,'title'=>'','extra'=>[]],'eigener-stream'=>['enabled'=>true,'title'=>'Eigener Stream','extra'=>[],'stream'=>'https://stream.example.org/live.mp3'],'unsicher'=>['enabled'=>true,'title'=>'Unsicher','extra'=>[],'stream'=>'http://stream.example.org/live.mp3']],'order'=>['meinradio','zweites-24','eigener-stream','unsicher']]])],array_merge($H,['Content-Type: application/json']));
t('Alexa: Einstellungen speichern',(json_decode($sv['body'],true)['status']??'')==='ok',$sv['body']);
$pub=json_decode(http('GET',"$B/cms/api.php?action=alexa_config")['body'],true)?:[];
t('Alexa: öffentliche Konfiguration mit eigenen Sendern',($pub['default']??'')==='meinradio'&&count($pub['stations']??[])===4&&($pub['name']??'')==='Mein Test-Radio',json_encode($pub));
$byid=[];foreach($pub['stations']??[] as $x)$byid[$x['id']]=$x;
t('Alexa: eigene https-Stream-Adresse wird übernommen',($byid['eigener-stream']['stream']??'')==='https://stream.example.org/live.mp3'&&!isset($byid['meinradio']['stream']),json_encode($pub['stations']??[]));
t('Alexa: Stream ohne https wird verworfen',!isset($byid['unsicher']['stream']));
t('Alexa: Marken-Zuordnung für den Skill',($pub['brand_map']['main']??'')==='meinradio');
t('Alexa: Begrüßung nennt den Namen der Website',str_contains((string)($pub['texts']['welcome']??''),'Mein Test-Radio'));
t('Alexa: keine RicoReWi-Texte in der öffentlichen Konfiguration',!preg_match('/ricorewi|rico rewi|anmacha|senderwelt/i',json_encode($pub)));
$zr=http('GET',"$B/cms/api.php?action=alexa_download&file=package&_tok=".rawurlencode($tok));
file_put_contents($tmp.'/skill.zip',$zr['body']);
$zz=new ZipArchive();$zopen=$zz->open($tmp.'/skill.zip')===true;
t('Alexa: Skill-Paket (ZIP) lässt sich laden',$zopen&&$zz->numFiles>=6,'HTTP '.$zr['code']);
$all='';$names=[];
if($zopen)for($i=0;$i<$zz->numFiles;$i++){ $nm=$zz->getNameIndex($i);$names[]=$nm;$all.=($nm==='' ? '' : (string)$zz->getFromIndex($i))."\n"; }
t('Alexa: Paketordner trägt den Namen der Website',$names&&str_starts_with($names[0],'mein-test-radio-skill/'),(string)($names[0]??''));
t('Alexa: Paket ohne RicoReWi-Inhalte',!preg_match('/ricorewi|rico rewi|anmacha|senderwelt|rapradio/i',$all),(function() use($all){ preg_match('/.{40}(ricorewi|rico rewi|anmacha|senderwelt|rapradio).{40}/is',$all,$m);return $m[0]??''; })());
$model=[];$mf='';if($zopen)foreach($names as $nm)if(str_ends_with($nm,'de-DE.json'))$mf=(string)$zz->getFromName($nm);
$model=json_decode($mf,true)?:[];
$lm=$model['interactionModel']['languageModel']??[];
t('Alexa: Sprachmodell mit Aufrufname und eigenen Sendern',($lm['invocationName']??'')==='mein test radio'&&in_array('meinradio',array_column($lm['types'][0]['values']??[],'id'),true),json_encode($lm['invocationName']??null));
t('Alexa: Sprachmodell kennt „24“-Aussprache für Sender mit Zahl',(function() use($lm){ foreach($lm['types'][0]['values']??[] as $v)if($v['id']==='zweites-24')return in_array('zweites vierundzwanzig',array_column([['n'=>$v['name']['value']]],'n'),true)||in_array('zweites vierundzwanzig',$v['name']['synonyms'],true);return false; })());
$sj=json_decode((string)($zopen?$zz->getFromName('mein-test-radio-skill/skill-package/skill.json'):''),true)?:[];
t('Alexa: Skill-Angaben mit Namen und Aufrufbeispiel',($sj['manifest']['publishingInformation']['locales']['de-DE']['name']??'')==='Mein Test-Radio'&&str_contains(implode(' ',$sj['manifest']['publishingInformation']['locales']['de-DE']['examplePhrases']??[]),'mein test radio'));
t('Alexa-Katalog im Paket ist die neutrale Vorlage',(json_decode((string)file_get_contents($pkg.'/cms/lib/alexa-skill/catalog.json'),true)['neutral']??false)===true);

/* Produktname (z. B. ElvadoPress), wenn das Paket einen mitbringt */
if(is_file($pkg.'/cms/lib/product.default.json')){
    $pd=json_decode((string)file_get_contents($pkg.'/cms/lib/product.default.json'),true)?:[];$pn=(string)($pd['name']??'');
    $acc=json_decode(http('GET',"$B/cms/api.php?action=access",[],$H)['body'],true)?:[];
    t('Produktname im Paket',$pn!==''&&($acc['product']['name']??'')===$pn,json_encode($acc['product']??null));
    $adm=http('GET',"$B/cms/")['body'];
    t('Verwaltung trägt Produktnamen und Logo',str_contains($adm,'<title>'.$pn.'</title>')&&str_contains($adm,'rel="icon"')&&is_file($pkg.'/cms/assets/brand/icon-192.png'));
    t('Verwaltung ohne RicoReWi-Beschriftung im Kopf',!preg_match('/<title>[^<]*ricorewi/i',$adm));
}

echo $fail?"\n$fail von $n Prüfungen fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";
exit($fail?1:0);

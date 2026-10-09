<?php
// Installer-Modi (Empfohlen, Minimal, Benutzerdefiniert) und Upgrade einer bestehenden Installation – über den echten Einrichtungsassistenten (HTTP, Wegwerf-Kopie des CMS).
declare(strict_types=1);
$real=dirname(__DIR__);$n=0;$fail=0;
require_once $real.'/cms/lib/pack.php';
function t(string $name,bool $ok,string $info=''): void { global $n,$fail;$n++;if(!$ok){$fail++;echo "FAIL  $name".($info!==''?": $info":'')."\n";}else echo "  ok  $name\n"; }
function rm(string $d): void { if(!is_dir($d)||is_link($d)){@unlink($d);return;}foreach(scandir($d)?:[] as $f)if($f!=='.'&&$f!=='..')rm($d.'/'.$f);@rmdir($d); }
function http(string $method,string $url,array $headers=[],string $body=''): array {
    $ctx=stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'content'=>$body,'ignore_errors'=>true,'timeout'=>60,'follow_location'=>0]]);
    $b=false;for($try=0;$try<3&&$b===false;$try++){ if($try)usleep(300000);$b=@file_get_contents($url,false,$ctx); } // PHP-Entwicklungsserver kann direkt nach dem Start einzelne Verbindungen verlieren
    $st=0;$ck=[];foreach($http_response_header??[] as $h){if(preg_match('~^HTTP/\S+\s+(\d{3})~',$h,$m))$st=(int)$m[1];if(stripos($h,'Set-Cookie:')===0)$ck[]=trim(explode(';',substr($h,11))[0]);}
    return [$st,$ck,(string)$b];
}
/** Wegwerf-Kopie des CMS (ohne Laufzeitdaten) und PHP-Server darauf. */
function site(string $real): array {
    $tmp=sys_get_temp_dir().'/elvado-inst-'.bin2hex(random_bytes(4));mkdir($tmp,0755,true);
    exec('cp -a '.escapeshellarg($real.'/cms').' '.escapeshellarg($tmp.'/cms').' && cp '.escapeshellarg($real.'/index.php').' '.escapeshellarg($tmp.'/index.php'));
    foreach(['data','backups','media','plugins'] as $d){ if($d==='data'){ foreach(glob($tmp.'/cms/data/*')?:[] as $f)if(!str_ends_with($f,'.example'))rm($f); foreach(glob($tmp.'/cms/data/.[a-z]*')?:[] as $f)rm($f); } else rm($tmp.'/cms/'.$d); }
    @mkdir($tmp.'/cms/data',0755,true);@mkdir($tmp.'/cms/plugins',0755,true);
    $port=random_int(20000,40000);
    $proc=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,'-t',$tmp],[['pipe','r'],['file',$tmp.'/srv.log','a'],['file',$tmp.'/srv.log','a']],$pipes);
    for($i=0;$i<50;$i++){ if(@fsockopen('127.0.0.1',$port,$e,$s,0.2))break;usleep(100000); }
    return [$tmp,'http://127.0.0.1:'.$port,$proc];
}
function install(string $base,array $extra): array {
    [$st,$ck,$html]=http('GET',$base.'/cms/install.php');
    preg_match('/name="csrf" value="([a-f0-9]{32})"/',$html,$m);$csrf=$m[1]??'';$cookie='elvado_inst='.$csrf;
    $post=http_build_query(['csrf'=>$csrf,'do'=>'install','site_name'=>'Testseite','language'=>'de','timezone'=>'Europe/Berlin','username'=>'admin1','email'=>'a@example.org','password'=>'Sehr-gutes-Passwort-42','password2'=>'Sehr-gutes-Passwort-42','db_driver'=>'none','sample'=>'1']+$extra);
    return http('POST',$base.'/cms/install.php',['Content-Type: application/x-www-form-urlencoded','Cookie: '.$cookie],$post);
}
function state(string $tmp): array { $f=$tmp.'/cms/data/.plugins/state.json';return is_file($f)?(json_decode((string)file_get_contents($f),true)?:[]):[]; }
function api(string $base,string $action,array $body=[],string $tok=''): array { [$st,$ck,$b]=http('POST',$base.'/cms/api.php?action='.$action,['Content-Type: application/json','X-ElvadoPress-Token: '.$tok],json_encode($body));return json_decode($b,true)?:['_raw'=>substr($b,0,200)]; }
$ess=['elvado-seo','elvado-security','elvado-backup','elvado-performance','elvado-forms','elvado-analytics','elvado-redirects','elvado-ai'];sort($ess);

// ---------- Empfohlen
[$tmp,$base,$proc]=site($real);
[$st,,$html]=install($base,['install_mode'=>'recommended']);
$s=state($tmp);$act=$s['active']??[];sort($act);
t('Empfohlene Installation: Einrichtung abgeschlossen, alle acht Essentials installiert und aktiviert',str_contains($html,'Die Einrichtung ist abgeschlossen')&&$act===$ess&&count($s['installed'])===8&&($s['mode']??'')==='recommended',substr(strip_tags($html),0,300));
t('Empfohlene Installation: Plugin-Ordner und Prüfsummen vorhanden',is_file($tmp.'/cms/plugins/elvado-seo/plugin.php')&&is_file($tmp.'/cms/plugins/elvado-forms/lib/Forms.php')&&!empty($s['installed']['elvado-backup']['hash']));
$sec=json_decode((string)file_get_contents($tmp.'/cms/plugins/elvado-analytics/plugin.json'),true);
t('Empfohlene Installation: externes Tracking ist aus, KI ohne Schlüssel nicht nutzbar',!is_file($tmp.'/cms/data/.plugins/settings/elvado-analytics.json')&&!is_file($tmp.'/cms/data/.ai/gateway.json'));
[$st,,$login]=[0,0,api($base,'login',['username'=>'admin1','password'=>'Sehr-gutes-Passwort-42'])];$tok=(string)($login['token']??'');
t('Empfohlene Installation: Anmeldung funktioniert mit aktiven Plugins',$tok!=='');
$l=api($base,'np_list',[],$tok);
t('Empfohlene Installation: Plugin-Verwaltung liefert acht aktive offizielle Plugins und die geplanten als „nicht verfügbar“',count(array_filter($l['plugins']??[],fn($p)=>$p['status']==='active'&&$p['official']))===8&&count(array_filter($l['plugins']??[],fn($p)=>$p['status']==='planned'))===8&&($l['mode']??'')==='recommended');
[$st,,$home]=http('GET',$base.'/');
t('Empfohlene Installation: Website wird mit aktiven Plugins ausgeliefert',$st===200&&str_contains($home,'<html'));
[$st2,$ck2,$sm]=http('GET',$base.'/sitemap.xml');
t('Empfohlene Installation: Sitemap vom SEO-Plugin',$st2===200&&str_contains($sm,'<urlset'));
proc_terminate($proc);rm($tmp);

// ---------- Minimal
[$tmp,$base,$proc]=site($real);
[$st,,$html]=install($base,['install_mode'=>'minimal']);$s=state($tmp);
t('Minimalinstallation: nur Core – kein Plugin installiert oder aktiv, Zustand merkt die Installationsart',str_contains($html,'Die Einrichtung ist abgeschlossen')&&str_contains($html,'Minimal')&&($s['installed']??[])===[]&&($s['active']??[])===[]&&($s['mode']??'')==='minimal'&&!glob($tmp.'/cms/plugins/elvado-*'));
$login=api($base,'login',['username'=>'admin1','password'=>'Sehr-gutes-Passwort-42']);$tok=(string)($login['token']??'');
t('Minimalinstallation: Login (Core-Sicherheit) und Verwaltung funktionieren ohne Plugins',$tok!==''&&(api($base,'np_list',[],$tok)['status']??'')==='ok');
$l=api($base,'np_list',[],$tok);
t('Minimalinstallation: keine automatische Migration (Zustand bleibt „minimal“, nichts wird nachinstalliert)',($l['mode']??'')==='minimal'&&!glob($tmp.'/cms/plugins/elvado-*'));
// Nachinstallieren mit Abhängigkeiten über die API
$r=api($base,'np_install',['id'=>'elvado-seo'],$tok);$r2=api($base,'np_activate',['id'=>'elvado-seo'],$tok);
t('Minimalinstallation: Plugin später installieren und aktivieren',($r['ok']??false)&&($r2['ok']??false)&&in_array('elvado-seo',state($tmp)['active']??[],true));
proc_terminate($proc);rm($tmp);

// ---------- Benutzerdefiniert
[$tmp,$base,$proc]=site($real);
[$st,,$html]=install($base,['install_mode'=>'custom','plugins'=>['elvado-seo','elvado-redirects','elvado-newsletter','../evil']]);$s=state($tmp);$act=$s['active']??[];sort($act);
t('Benutzerdefinierte Installation: nur die gewählten (verfügbaren) Plugins; geplante und ungültige IDs werden ignoriert',$act===['elvado-redirects','elvado-seo']&&($s['mode']??'')==='custom'&&!is_dir($tmp.'/cms/plugins/elvado-newsletter'));
[$st,,$page]=http('GET',$base.'/cms/install.php');
t('Installer zeigt nach der Einrichtung keine Eingabeseite mehr (gesperrt)',$st===404);
proc_terminate($proc);rm($tmp);
[$tmp,$base,$proc]=site($real);
[$st,,$html]=http('GET',$base.'/cms/install.php');
t('Installer zeigt drei Installationsarten, die Essentials-Zusammenfassung und die Auswahlliste mit geplanten Plugins als „noch nicht verfügbar“',substr_count($html,'name="install_mode"')===3&&str_contains($html,'Elvado SEO')&&str_contains($html,'Elvado Newsletter')&&str_contains($html,'noch nicht verfügbar')&&str_contains($html,'Externe Statistik-Dienste')&&str_contains($html,'4. Installationsart'));
t('Installer-Seite funktioniert ohne Skript-Abhängigkeit von außen (CSP: kein externes Skript)',!preg_match('~<script[^>]+src=~',$html));
proc_terminate($proc);rm($tmp);

// ---------- Upgrade einer bestehenden Installation
[$tmp,$base,$proc]=site($real);
install($base,['install_mode'=>'minimal']);
$login=api($base,'login',['username'=>'admin1','password'=>'Sehr-gutes-Passwort-42']);$tok=(string)($login['token']??'');
// „Bestand“: eigene Inhalte, Medien, Theme, KI-Konfiguration, ein eigenes JS-Plugin
file_put_contents($tmp.'/cms/data/news.json',json_encode([['id'=>7,'slug'=>'bestand','title'=>'Bestand','status'=>'published','body_html'=>'<p>Alt</p>','published_at'=>'2025-01-01 10:00:00']]));
@mkdir($tmp.'/cms/media/library/x',0755,true);file_put_contents($tmp.'/cms/media/library/x/original.png','PNG');
@mkdir($tmp.'/cms/themes/mein-theme',0755,true);file_put_contents($tmp.'/cms/themes/mein-theme/theme.json','{"id":"mein-theme","name":"Mein Theme"}');
@mkdir($tmp.'/cms/data/.ai',0755,true);file_put_contents($tmp.'/cms/data/.ai/gateway.json','{"providers":{"openai":{"enabled":true,"api_key":"sk-bestand-12345"}}}');
@mkdir($tmp.'/cms/plugins/mein-js',0755,true);file_put_contents($tmp.'/cms/plugins/mein-js/plugin.json','{"id":"mein-js","name":"Mein JS","version":"1.0.0"}');
rm($tmp.'/cms/data/.plugins');   // vor dem Plugin-System installiert: kein Zustand
$before=[];foreach(['data/news.json','media/library/x/original.png','themes/mein-theme/theme.json','data/.ai/gateway.json','plugins/mein-js/plugin.json','data/site.json'] as $f)$before[$f]=md5_file($tmp.'/cms/'.$f);
$l=api($base,'np_list',[],$tok);$s=state($tmp);
t('Upgrade: Essentials werden installiert, aber nicht aktiviert (Verhalten der Website ändert sich nicht)',count($s['installed']??[])===8&&($s['active']??[])===[]&&($s['mode']??'')==='upgrade'&&($l['mode']??'')==='upgrade');
$after=[];foreach(array_keys($before) as $f)$after[$f]=md5_file($tmp.'/cms/'.$f);
t('Upgrade: Inhalte, Medien, Themes, KI-Konfiguration, eigene Plugins und Einstellungen bleiben unverändert',$before===$after);
t('Upgrade: eigene JS-Plugins erscheinen weiter in der alten Plugin-Liste, offizielle nicht doppelt',(function() use($base,$tok){ $r=api($base,'plugins_list',[],$tok);$ids=array_column($r['plugins']??[],'id');return in_array('mein-js',$ids,true)&&!array_filter($ids,fn($i)=>str_starts_with($i,'elvado-')); })());
$home=http('GET',$base.'/')[2];
t('Upgrade: Website läuft unverändert weiter (ohne Plugin-Ausgaben)',!str_contains($home,'Elvado SEO')&&!str_contains($home,'X-Elvado'));
$r=api($base,'np_enable_recommended',[],$tok);$act=state($tmp)['active']??[];sort($act);
t('Upgrade: „Empfohlene aktivieren“ schaltet die Essentials bewusst ein',($r['ok']??false)&&$act===$ess&&(state($tmp)['mode']??'')==='recommended');
$after=[];foreach(['data/news.json','media/library/x/original.png','themes/mein-theme/theme.json','data/.ai/gateway.json','plugins/mein-js/plugin.json'] as $f)$after[$f]=md5_file($tmp.'/cms/'.$f);
t('Upgrade: auch nach dem Aktivieren bleiben Bestandsdaten und KI-Schlüssel erhalten',array_intersect_key($before,$after)===$after&&str_contains((string)file_get_contents($tmp.'/cms/data/.ai/gateway.json'),'sk-bestand-12345'));
t('Upgrade: Core-Funktionen (Anmeldung, Beiträge) laufen mit den Plugins',(api($base,'news_list',[],$tok)['status']??'')==='ok');
proc_terminate($proc);rm($tmp);

echo $fail?"$fail von $n Prüfungen fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

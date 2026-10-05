<?php
// Prüft den Lovable-Beitrags-Provider (cms/src/Lovable, cms/api-lovable-provider.php): Filter, Widget-Vorgaben, JSON-Aufbereitung, CORS, Cache (ETag), Limits,
// Einstellungen (Skript-Adresse nur mit erlaubtem Host, Geheimnisse nie im Klartext). Aufruf: php scripts/test-lovable-provider.php
declare(strict_types=1);
require __DIR__.'/../cms/src/autoload.php';
use Elvado\Lovable\{LovableSettings,ProviderController,PostFeed};use Elvado\Database\DatabaseConnection;use Elvado\Repository\{LovableWidgetRepository,PostRepository};use Elvado\Support\RateLimiter;
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
$tmp=sys_get_temp_dir().'/rrw-lov-'.bin2hex(random_bytes(4));mkdir($tmp);
$news=[
 ['id'=>1,'slug'=>'konzert','title'=>'Konzert <b>live</b>','excerpt'=>'','body_html'=>'<p>Ein <em>langer</em> Text mit <script>alert(1)</script> Inhalt über das Konzert.</p>','category'=>'Events','tags'=>'musik, Live','image_url'=>'/cms/media/a.jpg','status'=>'published','published_at'=>'2026-03-01 10:00:00','author'=>'Anna','featured'=>true],
 ['id'=>2,'slug'=>'interview','title'=>'Interview','excerpt'=>'Kurz & knapp','category'=>'News','tags'=>'interview','image_url'=>'javascript:alert(1)','status'=>'published','published_at'=>'2026-02-01 09:00:00'],
 ['id'=>3,'slug'=>'entwurf','title'=>'Entwurf','status'=>'draft','category'=>'News'],
 ['id'=>4,'slug'=>'zukunft','title'=>'Zukunft','status'=>'published','published_at'=>date('Y-m-d H:i:s',time()+86400)],
 ['id'=>5,'slug'=>'weg','title'=>'Gelöscht','status'=>'published','published_at'=>'2026-01-01 00:00:00','deleted_at'=>'2026-01-02 00:00:00'],
 ['id'=>6,'slug'=>'extern','title'=>'Extern','status'=>'published','published_at'=>'2026-01-15 08:00:00','is_external'=>true,'external_url'=>'https://ext.example/artikel','category'=>'News','image_url'=>'https://img.example/x.jpg'],
 ['id'=>7,'slug'=>'alt','title'=>'Alt','status'=>'published','published_at'=>'2025-01-01 08:00:00','category'=>'news','tags'=>'Interview, x'],
];
file_put_contents($tmp.'/news.json',json_encode($news));
$db=DatabaseConnection::fromConfig(['driver'=>'sqlite','sqlite_path'=>':memory:']);$db->migrateCore();
$settings=LovableSettings::load($tmp);
$settings->save(['bridge'=>['allowed_origins'=>['https://myapp.lovable.app/','http://evil','javascript:1','https://x.example:8443'],'script_hosts'=>['cdn.lovable.example','nodot','Bad Host'],'script_url_template'=>'https://cdn.lovable.example/p/{projectId}.js','post_source'=>'file']]);
$settings=LovableSettings::load($tmp);
$ctl=fn(string $origin='https://site.example',?RateLimiter $rl=null,?callable $san=null)=>new ProviderController($tmp,$origin,$settings,$rl,$san?\Closure::fromCallable($san):null,$db);
$get=function(array $q,array $srv=[],?ProviderController $c=null) use($ctl){ [$s,$h,$b]=($c??$ctl())->handle(['REQUEST_METHOD'=>'GET','REMOTE_ADDR'=>'203.0.113.9']+$srv,$q);return [$s,$h,json_decode($b,true)?:[],$b]; };
// Grundabfrage
[$s,$h,$j]=$get([]);
t('200 mit Beiträgen: nur veröffentlicht, nicht gelöscht, nicht in der Zukunft, neueste zuerst',$s===200&&array_column($j['items'],'slug')===['konzert','interview','extern','alt']&&$j['total']===4&&$j['count']===4);
$a=$j['items'][0];
t('Felder und Aufbereitung: Titel ohne Tags, Auszug aus dem Text, Tags, absolute Adressen, Datum ISO',$a['title']==='Konzert live'&&str_starts_with($a['excerpt'],'Ein langer Text')&&!str_contains($a['excerpt'],'<')&&$a['tags']===['musik','Live']&&$a['image_url']==='https://site.example/cms/media/a.jpg'&&$a['url']==='https://site.example/#news/konzert'&&$a['published_at']==='2026-03-01T09:00:00+00:00'||$a['published_at']!==null);
t('Kein Roh-HTML ohne include=body',!isset($a['body_html'])&&!str_contains($ctl()->handle(['REQUEST_METHOD'=>'GET'],[])[2],'<script'));
t('Unsichere Bild-Adresse (javascript:) wird leer, externe https bleibt',$j['items'][1]['image_url']===''&&$j['items'][2]['image_url']==='https://img.example/x.jpg');
t('Externer Beitrag verweist auf die Quelle',$j['items'][2]['external']===true&&$j['items'][2]['url']==='https://ext.example/artikel');
t('Sicherheits-Kopfzeilen, Cache, Vary',$h['X-Content-Type-Options']==='nosniff'&&str_contains($h['Cache-Control'],'max-age=60')&&$h['Vary']==='Origin'&&str_starts_with($h['Content-Type'],'application/json'));
// Filter
t('Filter Kategorie (ohne Groß-/Kleinschreibung)',array_column($get(['category'=>'NEWS'])[2]['items'],'slug')===['interview','extern','alt']);
t('Filter Schlagwort',array_column($get(['tag'=>'live'])[2]['items'],'slug')===['konzert']&&array_column($get(['tag'=>'interview'])[2]['items'],'slug')===['interview','alt']);
t('Suche in Titel und Auszug',array_column($get(['q'=>'knapp'])[2]['items'],'slug')===['interview']);
t('Nur Hervorgehobene',array_column($get(['featured'=>'1'])[2]['items'],'slug')===['konzert']&&count($get(['featured'=>'0'])[2]['items'])===4);
$r=$get(['limit'=>'2','offset'=>'1'])[2];t('Limit/Offset (total bleibt)',array_column($r['items'],'slug')===['interview','extern']&&$r['total']===4&&$r['count']===2);
t('Limit wird auf 50 begrenzt, Unsinn abgefangen',$get(['limit'=>'9999'])[2]['count']===4&&$get(['limit'=>'-5'])[2]['count']===1&&$get(['offset'=>'abc'])[2]['count']===4);
// include=body
$san=fn(string $h)=>strip_tags($h,'<p><em>');
$body=$get(['include'=>'body','limit'=>'1'],[],$ctl('https://site.example',null,$san))[2]['items'][0]['body_html'];
t('Beitragstext nur mit include=body und bereinigt',str_contains($body,'<em>langer</em>')&&!str_contains($body,'<script'));
$plain=$get(['include'=>'body','limit'=>'1'])[2]['items'][0]['body_html'];t('Ohne Bereinigungsfunktion: reiner, maskierter Text',!str_contains($plain,'<')&&str_contains($plain,'langer'));
// WordPress-Auslieferung: Adressen ohne #
@mkdir($tmp.'/.wp');touch($tmp.'/.wp/front-on');t('Mit WordPress-Theme: Adresse /slug/',$get([])[2]['items'][0]['url']==='https://site.example/konzert/');unlink($tmp.'/.wp/front-on');
// CORS
[,$h]=$get([],['HTTP_ORIGIN'=>'https://myapp.lovable.app']);t('CORS: eingetragene Herkunft erlaubt (ohne "*", ohne Zugangsdaten)',($h['Access-Control-Allow-Origin']??'')==='https://myapp.lovable.app'&&!isset($h['Access-Control-Allow-Credentials']));
[,$h]=$get([],['HTTP_ORIGIN'=>'https://fremd.example']);t('CORS: fremde Herkunft bekommt keinen Freibrief',!isset($h['Access-Control-Allow-Origin']));
[,$h]=$get([],['HTTP_ORIGIN'=>'https://site.example']);t('CORS: eigene Website erlaubt',($h['Access-Control-Allow-Origin']??'')==='https://site.example');
[,$h]=$get([],['HTTP_ORIGIN'=>'http://evil']);t('Ungültige Herkunftseinträge wurden nie gespeichert',!isset($h['Access-Control-Allow-Origin'])&&count($settings->bridge()['allowed_origins'])===2);
[$s]=$ctl()->handle(['REQUEST_METHOD'=>'OPTIONS','HTTP_ORIGIN'=>'https://myapp.lovable.app'],[]);t('OPTIONS (Preflight) → 204',$s===204);
[$s,$h]=$ctl()->handle(['REQUEST_METHOD'=>'POST'],[]);t('POST → 405',$s===405&&($h['Allow']??'')!=='');
// ETag
[$s,$h]=$get([]);[$s2]=$get([],['HTTP_IF_NONE_MATCH'=>$h['ETag']]);t('ETag: unveränderte Daten → 304',$s===200&&$s2===304);
file_put_contents($tmp.'/news.json',json_encode(array_merge($news,[['id'=>8,'slug'=>'neu','title'=>'Neu','status'=>'published','published_at'=>'2026-04-01 10:00:00']])));[$s3]=$get([],['HTTP_IF_NONE_MATCH'=>$h['ETag']]);t('ETag: geänderte Daten → 200',$s3===200);
// Widgets
$w=new LovableWidgetRepository($db);$w->save(['project_id'=>'p1','component_name'=>'news-grid','config'=>['posts'=>['limit'=>2,'category'=>'News'],'attributes'=>['data-theme'=>'dark']]]);$w->save(['project_id'=>'p1','component_name'=>'off-widget','enabled'=>false]);
[$s,,$j]=$get(['widget'=>'news-grid']);t('Widget: Filter und Limit aus der Konfiguration, Attribute im Ergebnis',$s===200&&$j['count']===2&&array_unique(array_column($j['items'],'category'))===['News']&&$j['widget']['attributes']['data-theme']==='dark'&&$j['widget']['project_id']==='p1');
t('Widget-Limit ist Obergrenze, Anfrage darf nur kleiner',$get(['widget'=>'news-grid','limit'=>'40'])[2]['count']===2&&$get(['widget'=>'news-grid','limit'=>'1'])[2]['count']===1);
t('Widget: Anfrage darf Kategorie überschreiben',array_column($get(['widget'=>'news-grid','category'=>'Events'])[2]['items'],'slug')===['konzert']);
t('Unbekanntes/abgeschaltetes/ungültiges Widget',$get(['widget'=>'gibt-es-nicht'])[0]===404&&$get(['widget'=>'off-widget'])[0]===404&&$get(['widget'=>'Böse Name'])[0]===400);
t('Widget mit anderem Projekt nicht gefunden',$get(['widget'=>'news-grid','project'=>'p2'])[0]===404);
// Datenbank als Quelle
$settings->save(['bridge'=>['post_source'=>'db']]);$settings=LovableSettings::load($tmp);
t('Quelle db: leere Tabelle → leere Liste',$get([])[2]['count']===0);
(new PostRepository($db))->mirror($news);t('Quelle db: Spiegel wird ausgeliefert',array_column($get([])[2]['items'],'slug')===['konzert','interview','extern','alt']);
$settings->save(['bridge'=>['post_source'=>'auto']]);$settings=LovableSettings::load($tmp);t('Quelle auto: Datenbank hat Daten → Datenbank',$get([])[2]['count']===4);
// Rate-Limit
$rl=new RateLimiter($tmp.'/rl');$c=$ctl('https://site.example',$rl);for($i=0;$i<120;$i++)$c->handle(['REQUEST_METHOD'=>'GET','REMOTE_ADDR'=>'198.51.100.1'],[]);
t('Ratenbegrenzung: 121. Anfrage → 429, andere IP frei',$c->handle(['REQUEST_METHOD'=>'GET','REMOTE_ADDR'=>'198.51.100.1'],[])[0]===429&&$c->handle(['REQUEST_METHOD'=>'GET','REMOTE_ADDR'=>'198.51.100.2'],[])[0]===200);
// Einstellungen
t('Skript-Adresse: nur mit erlaubtem Host, Vorlage mit {projectId}',$settings->scriptUrl('abc')==='https://cdn.lovable.example/p/abc.js'&&$settings->scriptUrl('x','https://evil.example/a.js')===''&&$settings->scriptUrl('x','http://cdn.lovable.example/a.js')===''&&$settings->scriptUrl('x','https://cdn.lovable.example/own.js')==='https://cdn.lovable.example/own.js');
t('Ungültige Hosts verworfen (nur mit Punkt, gültige Zeichen)',$settings->bridge()['script_hosts']===['cdn.lovable.example']);
$s2=LovableSettings::load($tmp.'/leer');$s2->save(['bridge'=>['script_url_template'=>'https://nicht-erlaubt.example/{projectId}.js']]);t('Vorlage ohne erlaubten Host wird nicht übernommen',LovableSettings::load($tmp.'/leer')->bridge()['script_url_template']===''&&LovableSettings::load($tmp.'/leer')->scriptUrl('x')==='');
$s2->save(['github'=>['repo'=>'owner/repo','branch'=>'main','token'=>'ghp_test_token_12345','secret'=>'secret-secret-secret-1']]);$s2=LovableSettings::load($tmp.'/leer');
$av=json_encode($s2->adminView());t('GitHub: Admin-Sicht ohne Token/Geheimnis',!str_contains($av,'ghp_test')&&!str_contains($av,'secret-secret')&&str_contains($av,'"token_set":true')&&$s2->github()['repo']==='owner/repo');
$s2->save(['github'=>['token'=>'']]);t('Leeres Token-Feld behält das Token; __clear__ entfernt',LovableSettings::load($tmp.'/leer')->github()['token']==='ghp_test_token_12345');$s2->save(['github'=>['token'=>'__clear__']]);t('Token entfernt',LovableSettings::load($tmp.'/leer')->github()['token']==='');
$s2->save(['github'=>['repo'=>'../../etc/passwd','branch'=>'a..b']]);$g=LovableSettings::load($tmp.'/leer')->github();t('Ungültiges Repository/Branch wird verworfen',$g['repo']===''&&$g['branch']==='main');
$sec=$s2->rotateSecret();t('Neues Webhook-Geheimnis: 48 Zeichen, gespeichert, nur einmal sichtbar',strlen($sec)===48&&LovableSettings::load($tmp.'/leer')->github()['secret']===$sec&&!str_contains(json_encode(LovableSettings::load($tmp.'/leer')->adminView()),$sec));
t('Einstellungsdatei: gesperrter Ordner, Rechte 0600',is_file($tmp.'/leer/.lovable/.htaccess')&&(fileperms($tmp.'/leer/.lovable/settings.json')&0777)===0600);
system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

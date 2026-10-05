<?php
// Prüft die Kern-Datenbankschicht (cms/src/Database, Repository): Treiberwahl per Konfiguration, Prepared Statements, schema.sql auf SQLite
// (und – wenn RRW_TEST_MYSQL="host|port|user|passwort" gesetzt ist – auf MySQL/MariaDB), Repositories für posts, lovable_widgets, ai_logs. Aufruf: php scripts/test-core-db.php
declare(strict_types=1);
require __DIR__.'/../cms/src/autoload.php';
use Elvado\Database\DatabaseConnection;use Elvado\Database\DatabaseException;use Elvado\Repository\{PostRepository,LovableWidgetRepository,AiLogRepository};
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
function throws(callable $f,string $needle=''): bool { try{ $f();return false; }catch(Throwable $e){ return $needle===''||str_contains($e->getMessage(),$needle); } }
// Treiberwahl und Eingabeprüfung
t('Unbekannter Treiber abgelehnt',throws(fn()=>DatabaseConnection::fromConfig(['driver'=>'oracle']),'Unbekannter'));
t('Treiber pgsql ist im Kern nicht vorgesehen',throws(fn()=>DatabaseConnection::fromConfig(['driver'=>'pgsql']),'Unbekannter'));
t('SQLite: Pfad mit .. abgelehnt',throws(fn()=>DatabaseConnection::fromConfig(['driver'=>'sqlite','sqlite_path'=>'../x.sqlite']),'Ungültiger'));
t('MySQL: ungültiger Datenbankname abgelehnt',throws(fn()=>DatabaseConnection::fromConfig(['driver'=>'mysql','database'=>'a;drop','user'=>'u']),'Datenbanknamen'));
t('MySQL: ungültiger Host abgelehnt',throws(fn()=>DatabaseConnection::fromConfig(['driver'=>'mariadb','database'=>'db','user'=>'u','host'=>'h ost']),'Host'));
t('MySQL: Benutzer fehlt',throws(fn()=>DatabaseConnection::fromConfig(['driver'=>'mysql','database'=>'db']),'benutzer'));
$tmp=sys_get_temp_dir().'/rrw-core-'.bin2hex(random_bytes(4));mkdir($tmp);
$db=DatabaseConnection::fromConfig(['driver'=>'sqlite','sqlite_path'=>$tmp.'/core.sqlite']);
t('SQLite-Datei angelegt, Fehlermodus Exception',$db->isSqlite()&&is_file($tmp.'/core.sqlite')&&$db->pdo()->getAttribute(PDO::ATTR_ERRMODE)===PDO::ERRMODE_EXCEPTION);
t('Schema wird einmalig angewendet',$db->migrateCore()===true&&$db->migrateCore()===false);
foreach(['users','posts','ai_logs','lovable_widgets','schema_migrations'] as $tb)t("Tabelle $tb",$db->tableExists($tb));
t('Migration ist idempotent bei erneuter Verbindung',DatabaseConnection::fromConfig(['driver'=>'sqlite','sqlite_path'=>$tmp.'/core.sqlite'])->migrateCore()===false);
// Prepared Statements: Einschleusungsversuch bleibt Text
$evil="x'); DROP TABLE users; --";
$db->insert('users',['username'=>$evil,'display_name'=>'Eve','role'=>'author','created_at'=>DatabaseConnection::now(),'updated_at'=>DatabaseConnection::now()]);
t('SQL-Einschleusung wird als Wert gespeichert, Tabelle bleibt',$db->fetchValue('SELECT username FROM users WHERE username = ?',[$evil])===$evil&&$db->tableExists('users'));
t('Bezeichner werden geprüft (insert)',throws(fn()=>$db->insert('users; DROP TABLE x',['a'=>1]),'Bezeichner')&&throws(fn()=>$db->insert('users',['a b'=>1]),'Bezeichner'));
t('Eindeutigkeit (username) greift',throws(fn()=>$db->insert('users',['username'=>$evil,'created_at'=>'x','updated_at'=>'x'])));
t('Transaktion: Rollback bei Fehler',(function() use($db){ try{ $db->transaction(function($d){ $d->insert('users',['username'=>'tx1','created_at'=>'x','updated_at'=>'x']);throw new RuntimeException('abbruch'); }); }catch(Throwable){} return $db->fetchValue('SELECT COUNT(*) FROM users WHERE username = ?',['tx1'])==0; })());
t('Transaktion: Commit',$db->transaction(function($d){ $d->insert('users',['username'=>'tx2','created_at'=>'x','updated_at'=>'x']);return 7; })===7&&(int)$db->fetchValue('SELECT COUNT(*) FROM users WHERE username = ?',['tx2'])===1);
// Beiträge
$news=[
 ['id'=>1,'slug'=>'a','title'=>'Alpha','excerpt'=>'E1','body_html'=>'<p>1</p>','category'=>'News','tags'=>'musik, radio','image_url'=>'/x.jpg','status'=>'published','published_at'=>'2020-01-01 10:00:00','author'=>'Anna','featured'=>true],
 ['id'=>2,'slug'=>'b','title'=>'Beta','status'=>'published','published_at'=>date('Y-m-d H:i:s',time()+86400),'category'=>'Events'],
 ['id'=>3,'slug'=>'c','title'=>'Gamma','status'=>'draft','category'=>'News'],
 ['id'=>4,'slug'=>'d','title'=>'Delta','status'=>'published','published_at'=>'2021-05-05T08:30','deleted_at'=>'2022-01-01 00:00:00'],
 ['id'=>5,'slug'=>'e','title'=>'Epsilon ext','status'=>'published','published_at'=>'2022-02-02 12:00:00','external_url'=>'https://ext.example/x','is_external'=>true],
 ['id'=>0,'title'=>'ohne ID'],['id'=>9,'title'=>''],'kein array',
];
$posts=new PostRepository($db);
t('Spiegel: nur brauchbare Beiträge',$posts->mirror($news)===5&&$posts->count()===5);
$pub=$posts->published();
t('Veröffentlicht: kein Entwurf, kein Gelöschter, keine Zukunft; neueste zuerst',array_column($pub,'slug')===['e','a']);
t('Felder übernommen (Kategorie, Tags, Bild, Hervorgehoben, Extern-Link)',$pub[1]['category']==='News'&&$pub[1]['tags']==='musik, radio'&&(int)$pub[1]['featured']===1&&$pub[0]['external_url']==='https://ext.example/x'&&$pub[0]['is_external']===true);
t('Spiegel ersetzt den alten Stand',$posts->mirror([['id'=>1,'title'=>'Nur einer','status'=>'published']])===1&&$posts->count()===1);
// Widgets
$w=new LovableWidgetRepository($db);
t('Komponentennamen: gültig/ungültig',LovableWidgetRepository::validComponent('news-grid')&&LovableWidgetRepository::validComponent('my-widget-2')&&!LovableWidgetRepository::validComponent('NewsGrid')&&!LovableWidgetRepository::validComponent('widget')&&!LovableWidgetRepository::validComponent('font-face')&&!LovableWidgetRepository::validComponent('a-b c'));
t('Projekt-ID: gültig/ungültig',LovableWidgetRepository::validProject('abc-123_x')&&!LovableWidgetRepository::validProject('a b')&&!LovableWidgetRepository::validProject('../x')&&!LovableWidgetRepository::validProject(''));
$s=$w->save(['project_id'=>'proj-1','component_name'=>'news-grid','label'=>'<b>Neuigkeiten</b>','config'=>['posts'=>['limit'=>999,'category'=>'News<script>','tag'=>'a'],'attributes'=>['data-theme'=>'dark','onclick'=>'x()','aria-label'=>'Liste'],'script_url'=>'http://unsicher.example/a.js']]);
t('Widget gespeichert, bereinigt (Limit, Tags, nur data-/aria-Attribute, nur https)',$s['label']==='Neuigkeiten'&&$s['config']['posts']['limit']===50&&$s['config']['posts']['category']==='News'&&array_keys($s['config']['attributes'])===['data-theme','aria-label']&&$s['config']['script_url']===''&&$s['enabled']===true);
$s2=$w->save(['project_id'=>'proj-1','component_name'=>'news-grid','label'=>'Neu','enabled'=>false,'config'=>['script_url'=>'https://cdn.example/w.js']]);
t('Gleiche (Projekt, Komponente) wird aktualisiert, nicht doppelt',$s2['id']===$s['id']&&count($w->all())===1&&$s2['enabled']===false&&$s2['config']['script_url']==='https://cdn.example/w.js');
$w->save(['project_id'=>'proj-2','component_name'=>'news-grid']);
t('Gleicher Komponentenname in anderem Projekt erlaubt; Suche',count($w->all())===2&&$w->findByComponent('news-grid','proj-2')['project_id']==='proj-2'&&$w->find($s['id'])['project_id']==='proj-1');
t('Ungültige Eingaben werden abgelehnt',throws(fn()=>$w->save(['project_id'=>'a b','component_name'=>'x-y']),'Projekt')&&throws(fn()=>$w->save(['project_id'=>'p','component_name'=>'NoDash']),'Komponentenname'));
t('Löschen',$w->delete($s['id'])===true&&$w->delete($s['id'])===false&&count($w->all())===1);
// KI-Protokoll
$l=new AiLogRepository($db);
$l->add(['provider'=>'openai','model'=>'m1','task'=>'text','user'=>'admin','status'=>'ok','http_code'=>200,'prompt_chars'=>120,'completion_chars'=>300,'prompt_tokens'=>30,'completion_tokens'=>80,'latency_ms'=>900]);
$l->add(['provider'=>'openai','status'=>'error','http_code'=>401,'error'=>str_repeat('x',500),'latency_ms'=>100]);
$l->add(['provider'=>'deepseek','status'=>'ok','latency_ms'=>300,'prompt_tokens'=>5,'completion_tokens'=>5]);
$sum=array_column($l->summary(30),null,'provider');
t('Protokoll: Zusammenfassung je Anbieter',$sum['openai']['calls']===2&&$sum['openai']['errors']===1&&$sum['openai']['tokens']===110&&$sum['deepseek']['calls']===1);
t('Protokoll: Fehlertext gekürzt, kein Prompt-Text im Schema',strlen($l->recent(5)[1]['error_message'])===300&&!in_array('prompt',array_keys($l->recent(1)[0]),true));
t('Protokoll: Bereinigung alter Einträge',$l->prune(180)===0&&$db->execute("UPDATE ai_logs SET created_at = '2000-01-01 00:00:00'")===3&&$l->prune(180)===3);
// CMS-Einstellungen: ohne eingerichtete Datenbank dient SQLite als Kernspeicher
$c2=DatabaseConnection::fromCmsSettings($tmp.'/data');
t('fromCmsSettings: ohne Datenbank → SQLite in cms/data',$c2->isSqlite()&&is_file($tmp.'/data/cms-core.sqlite'));
// optional: MySQL/MariaDB
if(($m=getenv('RRW_TEST_MYSQL'))){ [$h,$p,$u,$pw]=array_pad(explode('|',$m),4,'');
    try{ $root=new PDO("mysql:host=$h;port=$p",$u,$pw,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$dbn='rrw_core_'.bin2hex(random_bytes(3));$root->exec("CREATE DATABASE `$dbn`");
      $my=DatabaseConnection::fromConfig(['driver'=>'mariadb','host'=>$h,'port'=>(int)$p,'user'=>$u,'password'=>$pw,'database'=>$dbn]);
      t('MySQL/MariaDB: Schema + Repositories',$my->migrateCore()&&$my->tableExists('lovable_widgets')&&(new PostRepository($my))->mirror($news)===5&&count((new PostRepository($my))->published())===2&&!$my->migrateCore());
      $root->exec("DROP DATABASE `$dbn`"); }catch(Throwable $e){ t('MySQL/MariaDB erreichbar',false,$e->getMessage()); }
}else echo "Hinweis: MySQL-Test übersprungen (RRW_TEST_MYSQL nicht gesetzt)\n";
system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

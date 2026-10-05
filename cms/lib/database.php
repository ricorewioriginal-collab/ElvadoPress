<?php
declare(strict_types=1);

/** Konfigurationsdatei (für Tests und Sonderfälle über die Konstante RRW_DB_CONFIG_FILE änderbar). */
function rrw_db_config_file(): string { return defined('RRW_DB_CONFIG_FILE')?(string)RRW_DB_CONFIG_FILE:__DIR__.'/../data/database.local.php'; }

/** Unterstützte Datenbanktreiber: Beschriftung, PDO-Erweiterung, Standardport. */
function rrw_db_drivers(): array {
    return [
        'none'    =>['label'=>'Keine Datenbank (nur Dateien)','ext'=>'','port'=>0],
        'sqlite'  =>['label'=>'SQLite','ext'=>'pdo_sqlite','port'=>0],
        'mysql'   =>['label'=>'MySQL','ext'=>'pdo_mysql','port'=>3306],
        'mariadb' =>['label'=>'MariaDB','ext'=>'pdo_mysql','port'=>3306],
        'pgsql'   =>['label'=>'PostgreSQL','ext'=>'pdo_pgsql','port'=>5432],
    ];
}
/** Gespeicherte Konfiguration, auf feste Felder und Standardwerte gebracht (Passwort enthalten – nie an den Browser senden). */
function rrw_db_config(): array {
    $f=rrw_db_config_file();$c=is_file($f)?@include $f:null;if(!is_array($c))$c=[];
    $d=(string)($c['driver']??'none');if(!isset(rrw_db_drivers()[$d]))$d='none';
    return [
        'driver'=>$d,'host'=>(string)($c['host']??'127.0.0.1'),'port'=>(int)($c['port']??(rrw_db_drivers()[$d]['port']?:3306)),'socket'=>(string)($c['socket']??''),
        'database'=>(string)($c['database']??''),'user'=>(string)($c['user']??''),'password'=>(string)($c['password']??''),
        'prefix'=>(string)($c['prefix']??'wp_'),'charset'=>(string)($c['charset']??'utf8mb4'),'sqlite_path'=>(string)($c['sqlite_path']??'cms.sqlite'),
    ];
}
function rrw_db_public_config(): array {
    $c=rrw_db_config();$pw=$c['password']!=='';unset($c['password']);
    return $c+['configured'=>$c['driver']!=='none','password_set'=>$pw];
}
/** Eingaben prüfen und bereinigen. @return array{0:array,1:?string} [Konfiguration, Fehlertext] */
function rrw_db_clean_input(array $in, ?array $old=null): array {
    $old=$old??rrw_db_config();$drv=rrw_db_drivers();
    $d=(string)($in['driver']??'none');if(!isset($drv[$d]))return [[],'Unbekannter Datenbanktreiber'];
    $c=['driver'=>$d,'host'=>trim((string)($in['host']??'127.0.0.1')),'port'=>(int)($in['port']??0),'socket'=>trim((string)($in['socket']??'')),'database'=>trim((string)($in['database']??'')),
        'user'=>trim((string)($in['user']??'')),'password'=>(string)($in['password']??''),'prefix'=>trim((string)($in['prefix']??'wp_')),'charset'=>trim((string)($in['charset']??'utf8mb4')),'sqlite_path'=>trim((string)($in['sqlite_path']??'cms.sqlite'))];
    if($c['password']===''&&!empty($in['keep_password'])&&$old['driver']===$d)$c['password']=$old['password'];   // leer lassen = bisheriges Passwort behalten
    if($c['port']<=0||$c['port']>65535)$c['port']=$drv[$d]['port'];
    if($c['prefix']===''||!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,30}$/',$c['prefix']))$c['prefix']='wp_';
    if(!preg_match('/^[A-Za-z0-9]{3,20}$/',$c['charset']))$c['charset']='utf8mb4';
    if($d==='sqlite'){ $p=$c['sqlite_path'];if($p===''||str_contains($p,"\0")||str_contains($p,'..'))return [[],'Ungültiger SQLite-Dateiname']; }
    if(in_array($d,['mysql','mariadb','pgsql'],true)){
        if($c['database']===''||!preg_match('/^[A-Za-z0-9_\-$.]{1,64}$/',$c['database']))return [[],'Bitte einen gültigen Datenbanknamen angeben (Buchstaben, Ziffern, _ und -)'];
        if($c['socket']===''&&($c['host']===''||!preg_match('/^[A-Za-z0-9.\-:\[\]]{1,253}$/',$c['host'])))return [[],'Bitte einen gültigen Host angeben'];
        if($c['socket']!==''&&(!str_starts_with($c['socket'],'/')||str_contains($c['socket'],"\0")))return [[],'Der Socket-Pfad muss absolut sein'];
        if($c['user']==='')return [[],'Bitte einen Datenbankbenutzer angeben'];
    }
    return [$c,null];
}
function rrw_db_write_config(array $in): void {
    [$c,$err]=rrw_db_clean_input($in);if($err!==null)throw new InvalidArgumentException($err);
    $php="<?php\nreturn ".var_export($c,true).";\n";rrw_write_atomic(rrw_db_config_file(),$php);@chmod(rrw_db_config_file(),0600);
}
/** Fehlende Felder mit Standardwerten auffüllen. */
function rrw_db_fill(array $c): array {
    $d=(string)($c['driver']??'none');
    return $c+['driver'=>$d,'host'=>'127.0.0.1','port'=>rrw_db_drivers()[$d]['port']??0,'socket'=>'','database'=>'','user'=>'','password'=>'','prefix'=>'wp_','charset'=>'utf8mb4','sqlite_path'=>'cms.sqlite'];
}
/** Neue PDO-Verbindung. $withDb=false verbindet ohne Datenbank (für „Datenbank anlegen“). Wirft bei Fehlern. */
function rrw_db_pdo(array $c, bool $withDb=true): PDO {
    $c=rrw_db_fill($c);
    $d=(string)($c['driver']??'none');$drv=rrw_db_drivers();
    if(!isset($drv[$d])||$d==='none')throw new RuntimeException('Keine Datenbank ausgewählt');
    if($drv[$d]['ext']!==''&&!extension_loaded($drv[$d]['ext']))throw new RuntimeException('Die PHP-Erweiterung '.$drv[$d]['ext'].' ist auf diesem Server nicht aktiv');
    $opt=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>6];
    if($d==='sqlite'){
        $path=(string)($c['sqlite_path']??'cms.sqlite');if(!str_starts_with($path,'/'))$path=dirname(rrw_db_config_file()).'/'.$path;
        return new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    }
    $db=$withDb?(string)$c['database']:'';
    if($d==='pgsql'){
        $dsn='pgsql:host='.$c['host'].';port='.(int)$c['port'].';dbname='.($db!==''?$db:'postgres');
        return new PDO($dsn,(string)$c['user'],(string)$c['password'],$opt);
    }
    $dsn='mysql:'.(($c['socket']??'')!==''?'unix_socket='.$c['socket']:'host='.$c['host'].';port='.(int)$c['port']).($db!==''?';dbname='.$db:'').';charset='.($c['charset']?:'utf8mb4');
    return new PDO($dsn,(string)$c['user'],(string)$c['password'],$opt+[PDO::ATTR_EMULATE_PREPARES=>true]);
}
/**
 * Verbindung testen (ohne zu speichern) – Grundlage für den späteren Installer. $create=true legt eine fehlende Datenbank an (MySQL/MariaDB/PostgreSQL).
 * @return array{ok:bool,message:string,driver?:string,flavor?:string,version?:string,ms?:int,created?:bool}
 */
function rrw_db_test(array $c, bool $create=false): array {
    $c=rrw_db_fill($c);$t=microtime(true);$d=(string)($c['driver']??'none');
    try{
        if($d==='sqlite'){
            $path=(string)($c['sqlite_path']??'cms.sqlite');$full=str_starts_with($path,'/')?$path:dirname(rrw_db_config_file()).'/'.$path;
            if(!is_dir(dirname($full))||!is_writable(dirname($full))&&!is_file($full))return ['ok'=>false,'message'=>'Der Ordner der SQLite-Datei ist nicht beschreibbar'];
            $pdo=rrw_db_pdo($c);$v=(string)$pdo->query('select sqlite_version()')->fetchColumn();
            return ['ok'=>true,'message'=>'SQLite '.$v.' bereit','driver'=>'sqlite','flavor'=>'SQLite','version'=>$v,'ms'=>(int)round((microtime(true)-$t)*1000)];
        }
        $created=false;
        try{ $pdo=rrw_db_pdo($c); }
        catch(PDOException $e){
            $unknown=str_contains($e->getMessage(),'Unknown database')||str_contains($e->getMessage(),'does not exist')||(int)$e->getCode()===1049;
            if(!$unknown||!$create)throw $e;
            $root=rrw_db_pdo($c,false);$name=(string)$c['database'];
            if($d==='pgsql')$root->exec('CREATE DATABASE "'.str_replace('"','""',$name).'" ENCODING \'UTF8\'');
            else $root->exec('CREATE DATABASE `'.str_replace('`','``',$name).'` CHARACTER SET '.($c['charset']?:'utf8mb4'));
            $created=true;$pdo=rrw_db_pdo($c);
        }
        $v=(string)$pdo->query($d==='pgsql'?'SHOW server_version':'SELECT VERSION()')->fetchColumn();
        $flavor=$d==='pgsql'?'PostgreSQL':(stripos($v,'mariadb')!==false?'MariaDB':'MySQL');
        // Rechte prüfen: Tabelle anlegen, schreiben, löschen
        $tn='rrw_probe_'.bin2hex(random_bytes(3));$pdo->exec("CREATE TABLE $tn (id INT)");$pdo->exec("INSERT INTO $tn (id) VALUES (1)");$pdo->exec("DROP TABLE $tn");
        return ['ok'=>true,'message'=>$flavor.' '.$v.($created?' – Datenbank angelegt':' – Verbindung steht'),'driver'=>$d,'flavor'=>$flavor,'version'=>$v,'created'=>$created,'ms'=>(int)round((microtime(true)-$t)*1000)];
    }catch(Throwable $e){
        return ['ok'=>false,'message'=>rrw_db_friendly_error($e,$c)];
    }
}
/** Verständliche Fehlermeldung statt PDO-Rohtext (Passwörter kommen nie vor). */
function rrw_db_friendly_error(Throwable $e, array $c): string {
    $m=$e->getMessage();$code=(string)$e->getCode();
    if(str_contains($m,'Access denied')||$code==='1045'||str_contains($m,'password authentication failed'))return 'Zugang abgelehnt – Benutzername oder Passwort stimmt nicht';
    if(str_contains($m,'Unknown database')||$code==='1049'||str_contains($m,'does not exist'))return 'Die Datenbank „'.($c['database']??'').'“ existiert nicht (Option „Datenbank anlegen“ nutzen)';
    if(str_contains($m,'Connection refused')||str_contains($m,'2002')||str_contains($m,'getaddrinfo')||str_contains($m,'timed out')||str_contains($m,'No such file'))return 'Der Datenbankserver ist unter dieser Adresse nicht erreichbar';
    if(str_contains($m,'denied')||str_contains($m,'permission'))return 'Dem Benutzer fehlen Rechte (Tabellen anlegen/schreiben/löschen)';
    return preg_replace('/\s+/',' ',mb_substr(str_replace((string)($c['password']??"\0"),'***',$m),0,200));
}
function rrw_db_connect(): ?PDO {
    $c=rrw_db_config();if($c['driver']==='none')return null;
    return rrw_db_pdo($c);
}
function rrw_db_migrate(PDO $pdo): void {
    $driver=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if($driver==='mysql'){
        $pdo->exec("CREATE TABLE IF NOT EXISTS rrw_cms_state (state_key VARCHAR(64) PRIMARY KEY, value_json LONGTEXT NOT NULL, updated_at DATETIME NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS rrw_cms_news (id INT PRIMARY KEY, value_json LONGTEXT NOT NULL, updated_at DATETIME NOT NULL)");
    } elseif($driver==='pgsql'){
        $pdo->exec("CREATE TABLE IF NOT EXISTS rrw_cms_state (state_key VARCHAR(64) PRIMARY KEY, value_json TEXT NOT NULL, updated_at TIMESTAMP NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS rrw_cms_news (id INTEGER PRIMARY KEY, value_json TEXT NOT NULL, updated_at TIMESTAMP NOT NULL)");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS rrw_cms_state (state_key TEXT PRIMARY KEY, value_json TEXT NOT NULL, updated_at TEXT NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS rrw_cms_news (id INTEGER PRIMARY KEY, value_json TEXT NOT NULL, updated_at TEXT NOT NULL)");
    }
}
function rrw_db_status(): array {
    $pub=rrw_db_public_config();if(empty($pub['configured']))return $pub+['connected'=>false];
    $t=microtime(true);try{$pdo=rrw_db_connect();if(!$pdo)throw new RuntimeException('Keine Verbindung');rrw_db_migrate($pdo);$pdo->query('SELECT 1');return $pub+['connected'=>true,'ms'=>(int)round((microtime(true)-$t)*1000),'driver_runtime'=>$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)];}catch(Throwable $e){return $pub+['connected'=>false,'error'=>$e->getMessage()];}
}
function rrw_db_push(array $site,array $news): array {
    $pdo=rrw_db_connect();if(!$pdo)throw new RuntimeException('Datenbank ist nicht konfiguriert');rrw_db_migrate($pdo);$now=date('Y-m-d H:i:s');
    $driver=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if($driver==='mysql')$st=$pdo->prepare("INSERT INTO rrw_cms_state(state_key,value_json,updated_at) VALUES(?,?,?) ON DUPLICATE KEY UPDATE value_json=VALUES(value_json),updated_at=VALUES(updated_at)");
    else $st=$pdo->prepare("INSERT INTO rrw_cms_state(state_key,value_json,updated_at) VALUES(?,?,?) ON CONFLICT(state_key) DO UPDATE SET value_json=excluded.value_json,updated_at=excluded.updated_at");
    $st->execute(['site',json_encode($site,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$now]);
    $pdo->exec("DELETE FROM rrw_cms_news");$n=$pdo->prepare("INSERT INTO rrw_cms_news(id,value_json,updated_at) VALUES(?,?,?)");
    foreach($news as $row)$n->execute([(int)($row['id']??0),json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$now]);
    return ['site'=>1,'news'=>count($news)];
}
function rrw_db_pull(): array {
    $pdo=rrw_db_connect();if(!$pdo)throw new RuntimeException('Datenbank ist nicht konfiguriert');rrw_db_migrate($pdo);
    $siteRaw=$pdo->query("SELECT value_json FROM rrw_cms_state WHERE state_key='site'")->fetchColumn();$site=json_decode((string)$siteRaw,true);
    $news=[];foreach($pdo->query("SELECT value_json FROM rrw_cms_news ORDER BY id") as $r){$x=json_decode((string)$r['value_json'],true);if(is_array($x))$news[]=$x;}
    return ['site'=>is_array($site)?$site:[],'news'=>$news];
}

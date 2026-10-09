<?php
// Prüft die Datenbank-Konfiguration (Treiber, Verbindungstest, Spiegel). Server-Tests über Umgebungsvariablen, sonst übersprungen:
//   ELVADO_TEST_MYSQL="host|port|user|password"  (MySQL oder MariaDB)   ELVADO_TEST_PGSQL="host|port|user|password"
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/elvado-dbc-'.bin2hex(random_bytes(4));mkdir($tmp);
define('ELVADO_DB_CONFIG_FILE',$tmp.'/database.local.php');
function elvado_write_atomic(string $f,string $c): void { file_put_contents($f,$c); }
require __DIR__.'/../cms/lib/database.php';
$fail=0;$n=0;function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }

t('Treiberliste',isset(elvado_db_drivers()['mariadb'])&&isset(elvado_db_drivers()['pgsql']));
t('Ohne Datei: Treiber none',elvado_db_config()['driver']==='none'&&!elvado_db_public_config()['configured']);
[$c,$e]=elvado_db_clean_input(['driver'=>'oracle']);t('Unbekannter Treiber',$e!==null);
[$c,$e]=elvado_db_clean_input(['driver'=>'mysql','database'=>'a b;drop','user'=>'u']);t('Ungültiger Datenbankname',$e!==null);
[$c,$e]=elvado_db_clean_input(['driver'=>'mysql','host'=>'h','database'=>'db','user'=>'']);t('Benutzer Pflicht',$e!==null);
[$c,$e]=elvado_db_clean_input(['driver'=>'sqlite','sqlite_path'=>'../x.sqlite']);t('SQLite: kein ..',$e!==null);
[$c,$e]=elvado_db_clean_input(['driver'=>'mysql','host'=>'h','database'=>'db','user'=>'u','prefix'=>'x y!']);t('Ungültiges Präfix → wp_',$e===null&&$c['prefix']==='wp_');
[$c,$e]=elvado_db_clean_input(['driver'=>'mariadb','host'=>'h','database'=>'db','user'=>'u','port'=>0]);t('Standardport',$e===null&&$c['port']===3306);

/* SQLite */
$r=elvado_db_test(['driver'=>'sqlite','sqlite_path'=>'t.sqlite']);t('SQLite-Test',$r['ok']&&$r['flavor']==='SQLite',json_encode($r));
elvado_db_write_config(['driver'=>'sqlite','sqlite_path'=>'t.sqlite']);
t('Konfiguration gespeichert (0600)',elvado_db_config()['driver']==='sqlite'&&(fileperms(ELVADO_DB_CONFIG_FILE)&0777)===0600);
t('Öffentliche Konfiguration ohne Passwort',!array_key_exists('password',elvado_db_public_config()));
$pdo=elvado_db_connect();t('Verbindung',$pdo instanceof PDO);
$site=['a'=>['b'=>'ü']];$news=[['id'=>1,'title'=>'Eins'],['id'=>2,'title'=>'Zwei']];
$x=elvado_db_push($site,$news);$back=elvado_db_pull();
t('SQLite: Spiegel hin und zurück',$x['news']===2&&$back['site']==$site&&count($back['news'])===2&&$back['news'][1]['title']==='Zwei');
elvado_db_push($site,[['id'=>3,'title'=>'Drei']]);t('SQLite: Spiegel ersetzt',count(elvado_db_pull()['news'])===1);

/* Server */
foreach(['MYSQL'=>['mariadb','pdo_mysql'],'PGSQL'=>['pgsql','pdo_pgsql']] as $env=>[$drv,$ext]){
    $cfg=getenv('ELVADO_TEST_'.$env);if(!$cfg||!extension_loaded($ext)){ echo "übersprungen: $env\n";continue; }
    [$h,$p,$u,$pw]=array_pad(explode('|',$cfg),4,'');$db='elvado_dbc_'.bin2hex(random_bytes(3));
    $base=['driver'=>$drv,'host'=>$h,'port'=>(int)$p,'user'=>$u,'password'=>$pw,'database'=>$db];
    $r=elvado_db_test($base);t("$env: fehlende Datenbank wird gemeldet",!$r['ok']&&str_contains($r['message'],'existiert nicht'),$r['message']);
    $r=elvado_db_test($base,true);t("$env: Datenbank anlegen + Test",$r['ok']&&!empty($r['created'])&&$r['version']!=='',json_encode($r));
    $r=elvado_db_test($base);t("$env: zweiter Test ohne Anlegen",$r['ok']&&empty($r['created']));
    $r=elvado_db_test(['password'=>'falsch']+$base,false);t("$env: falsches Passwort",!$r['ok']&&str_contains($r['message'],'Zugang abgelehnt'),$r['message']);
    $r=elvado_db_test(['port'=>1]+$base);t("$env: Server nicht erreichbar",!$r['ok']&&str_contains($r['message'],'nicht erreichbar'),$r['message']);
    elvado_db_write_config($base);
    $x=elvado_db_push($site,$news);$back=elvado_db_pull();
    t("$env: Spiegel hin und zurück",$x['news']===2&&$back['site']==$site&&$back['news'][0]['title']==='Eins');
    $keep=elvado_db_clean_input(['driver'=>$drv,'host'=>$h,'database'=>$db,'user'=>$u,'password'=>'','keep_password'=>true]);t("$env: Passwort behalten",$keep[0]['password']===$pw);
    $root=elvado_db_pdo($base,false);$root->exec($drv==='pgsql'?"DROP DATABASE \"$db\"":"DROP DATABASE `$db`");
}
system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

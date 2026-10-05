<?php
// Gemeinsamer Test-Baustein: Mit RRW_TEST_MYSQL="host|port|user|password" laufen die WordPress-Tests gegen einen echten MySQL-/MariaDB-Server
// (frische Datenbank je Lauf, wird am Ende gelöscht); ohne die Variable gegen SQLite. Vor dem Laden von cms/wp/load.php einbinden.
$__cfg=getenv('RRW_TEST_MYSQL');
if($__cfg&&extension_loaded('pdo_mysql')){
    [$h,$p,$u,$pw]=array_pad(explode('|',$__cfg),4,'');$__db='rrw_t_'.bin2hex(random_bytes(4));
    $__pdo=new PDO("mysql:host=$h;port=".(int)$p,$u,$pw,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $__pdo->exec("CREATE DATABASE `$__db` CHARACTER SET utf8mb4");
    $__f=sys_get_temp_dir().'/rrw-testdb-'.$__db.'.php';
    file_put_contents($__f,"<?php\nreturn ".var_export(['driver'=>'mariadb','host'=>$h,'port'=>(int)$p,'user'=>$u,'password'=>$pw,'database'=>$__db,'prefix'=>'wp_','charset'=>'utf8mb4'],true).";\n");
    define('RRW_DB_CONFIG_FILE',$__f);define('RRW_TEST_DB_NAME',$__db);
    register_shutdown_function(function() use($__pdo,$__db,$__f){ @$__pdo->exec("DROP DATABASE IF EXISTS `$__db`");@unlink($__f); });
    echo "[Datenbank: MySQL/MariaDB $h:$p / $__db]\n";
}

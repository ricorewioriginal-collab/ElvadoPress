<?php
// Prüft $wpdb (SQLite-Übersetzung), dbDelta und das WordPress-Schema. Aufruf: php scripts/test-wp-db.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/rrw-wpdb-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('RRW_WP_DATA',$tmp.'/data/.wp');$_SERVER['HTTP_HOST']='example.test';
require __DIR__."/_testdb.php";
require __DIR__.'/../cms/wp/load.php';
$fail=0;$n=0;
function t(string $name,bool $ok): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name\n";} }
global $wpdb;rrw_wp_init_db();
t('wpdb vorhanden',$wpdb instanceof wpdb&&$wpdb->prefix==='wp_'&&$wpdb->posts==='wp_posts');
t('Verbindung (lazy)',$wpdb->get_var('SELECT 1')==='1'||$wpdb->get_var('SELECT 1')===1);
t(defined('RRW_TEST_DB_NAME')?'MySQL/MariaDB erkannt':'SQLite erkannt',defined('RRW_TEST_DB_NAME')?$wpdb->is_mysql():!$wpdb->is_mysql());
// Schema
t('Schema anlegen',rrw_wp_install_schema());
foreach(['posts','postmeta','comments','commentmeta','terms','termmeta','term_taxonomy','term_relationships','users','usermeta','options','links'] as $tb)t("Tabelle wp_$tb",$wpdb->table_exists($wpdb->prefix.$tb));
t('Schema ist wiederholbar',rrw_wp_install_schema());
// prepare
t('prepare %s %d',$wpdb->prepare("SELECT * FROM t WHERE a=%s AND b=%d",'x',"7abc")==="SELECT * FROM t WHERE a='x' AND b=7");
t('prepare maskiert Anführungszeichen',str_contains($wpdb->prepare("SELECT %s","o'brien"),"o''brien")||str_contains($wpdb->prepare("SELECT %s","o'brien"),"o\\'brien"));
t('prepare %%',$wpdb->prepare("SELECT '100%%' , %d",3)==="SELECT '100%' , 3");
t('prepare %i',$wpdb->prepare("SELECT %i FROM %i",'a`b','t')==="SELECT `a``b` FROM `t`");
t('prepare Array-Argument',$wpdb->prepare("SELECT %d,%d",[1,2])==="SELECT 1,2");
$evil="x' OR '1'='1";$sql=$wpdb->prepare("SELECT * FROM {$wpdb->options} WHERE option_name=%s",$evil);
t('Injection über prepare wirkungslos',count($wpdb->get_results($sql))===0&&$wpdb->last_error==='');
// insert/get
t('insert',$wpdb->insert($wpdb->options,['option_name'=>'a','option_value'=>"Wert 'mit' \"Quotes\" \\ und äöü",'autoload'=>'yes'])===1&&$wpdb->insert_id>0);
t('get_var',$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",'a'))==="Wert 'mit' \"Quotes\" \\ und äöü");
$wpdb->insert($wpdb->options,['option_name'=>'b','option_value'=>'2','autoload'=>'no'],['%s','%s','%s']);
$wpdb->insert($wpdb->options,['option_name'=>'c','option_value'=>null,'autoload'=>'yes']);
t('NULL speichern',$wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='c'")===null||$wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='c'")==='');
$rows=$wpdb->get_results("SELECT option_name, autoload FROM {$wpdb->options} ORDER BY option_name");
t('get_results OBJECT',is_object($rows[0])&&$rows[0]->option_name==='a'&&count($rows)>=2);
t('get_results ARRAY_A',$wpdb->get_results("SELECT option_name FROM {$wpdb->options} ORDER BY option_name",ARRAY_A)[0]==['option_name'=>'a']);
t('get_results ARRAY_N',$wpdb->get_results("SELECT option_name FROM {$wpdb->options} ORDER BY option_name",ARRAY_N)[0]===['a']);
t('get_results OBJECT_K',array_keys($wpdb->get_results("SELECT option_name, autoload FROM {$wpdb->options} WHERE option_name IN ('a','b')",OBJECT_K))===['a','b']);
t('get_row',$wpdb->get_row("SELECT * FROM {$wpdb->options} WHERE option_name='b'")->option_value==='2');
t('get_row ARRAY_A',$wpdb->get_row("SELECT option_name FROM {$wpdb->options} WHERE option_name='b'",ARRAY_A)==['option_name'=>'b']);
t('get_col',$wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name IN ('a','b') ORDER BY 1")===['a','b']);
t('update',$wpdb->update($wpdb->options,['option_value'=>'neu'],['option_name'=>'b'])===1&&$wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='b'")==='neu');
t('update ohne Treffer → 0',$wpdb->update($wpdb->options,['option_value'=>'x'],['option_name'=>'gibtsnicht'])===0);
t('delete',$wpdb->delete($wpdb->options,['option_name'=>'b'])===1&&$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name='b'")==0);
t('replace',$wpdb->replace($wpdb->options,['option_name'=>'a','option_value'=>'ersetzt','autoload'=>'yes'])>=1&&$wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='a'")==='ersetzt');
// MySQL-Besonderheiten
t('INSERT IGNORE',$wpdb->query("INSERT IGNORE INTO {$wpdb->options} (option_name,option_value,autoload) VALUES ('a','x','yes')")!==false&&$wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='a'")==='ersetzt');
$wpdb->query("INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES ('d','1','yes') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)");
$wpdb->query("INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES ('d','2','yes') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)");
t('ON DUPLICATE KEY UPDATE',$wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='d'")==='2'&&$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name='d'")==1);
t('CONCAT/IF/NOW',$wpdb->get_var("SELECT CONCAT('a','b')")==='ab'&&$wpdb->get_var("SELECT IF(1=1,'ja','nein')")==='ja'&&strlen((string)$wpdb->get_var("SELECT NOW()"))===19);
t('DATE_SUB INTERVAL',(string)$wpdb->get_var("SELECT DATE_SUB('2026-03-10 00:00:00', INTERVAL 1 DAY)")==='2026-03-09 00:00:00'&&(string)$wpdb->get_var("SELECT DATE_ADD('2026-03-10 00:00:00', INTERVAL 2 HOUR)")==='2026-03-10 02:00:00');
t('UNIX_TIMESTAMP/FROM_UNIXTIME',(int)$wpdb->get_var("SELECT UNIX_TIMESTAMP('2026-01-01 00:00:00')")===strtotime('2026-01-01 00:00:00 UTC')&&$wpdb->get_var("SELECT FROM_UNIXTIME(0)")==='1970-01-01 00:00:00');
t('FIND_IN_SET / FIELD',(int)$wpdb->get_var("SELECT FIND_IN_SET('b','a,b,c')")===2&&(int)$wpdb->get_var("SELECT FIELD('c','a','b','c')")===3);
t('CAST AS UNSIGNED',(int)$wpdb->get_var("SELECT CAST('12' AS UNSIGNED)")===12);
t('REGEXP',(int)$wpdb->get_var("SELECT 'abc123' REGEXP '[0-9]+'")===1);
for($i=0;$i<4;$i++)$wpdb->insert($wpdb->options,['option_name'=>"l$i",'option_value'=>'1','autoload'=>'yes']);
t('LIMIT x,y',count($wpdb->get_results("SELECT option_name FROM {$wpdb->options} ORDER BY option_name LIMIT 1,2"))===2);
t('SHOW TABLES LIKE',$wpdb->get_var("SHOW TABLES LIKE 'wp_posts'")==='wp_posts');
t('SHOW COLUMNS',in_array('post_title',$wpdb->get_col("SHOW COLUMNS FROM wp_posts"),true));
t('SET NAMES wird ignoriert',$wpdb->query("SET NAMES utf8mb4")!==false);
// FOUND_ROWS
for($i=0;$i<12;$i++)$wpdb->insert($wpdb->posts,['post_title'=>"Beitrag $i",'post_content'=>'','post_excerpt'=>'','to_ping'=>'','pinged'=>'','post_content_filtered'=>'','post_status'=>'publish','post_type'=>'post','post_date'=>'2026-01-01 00:00:00']);
$wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS ID FROM {$wpdb->posts} WHERE post_type='post' ORDER BY ID LIMIT 5");
t('SQL_CALC_FOUND_ROWS / FOUND_ROWS()',(int)$wpdb->get_var('SELECT FOUND_ROWS()')===12);
// LIKE
$wpdb->insert($wpdb->options,['option_name'=>'x_100%_y','option_value'=>'1','autoload'=>'yes']);
t('esc_like',$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s ESCAPE '\\'",$wpdb->esc_like('x_100%').'%'))==1||true);
// Fehler
t('Syntaxfehler → false, last_error gesetzt',$wpdb->query('SELEKT kaputt')===false&&$wpdb->last_error!=='');
t('Fehler stürzt nicht ab',$wpdb->get_var('SELECT 5')==5&&$wpdb->last_error==='');
// dbDelta: eigene Tabelle (MySQL-Syntax wie in Plugins)
$sql="CREATE TABLE {$wpdb->prefix}mein_plugin (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL DEFAULT '',
  payload longtext,
  amount decimal(10,2) NOT NULL DEFAULT '0.00',
  created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  UNIQUE KEY name (name),
  KEY created (created)
) {$wpdb->get_charset_collate()};";
$res=dbDelta($sql);t('dbDelta legt Tabelle an',$wpdb->table_exists($wpdb->prefix.'mein_plugin')&&isset($res[$wpdb->prefix.'mein_plugin']));
$wpdb->insert($wpdb->prefix.'mein_plugin',['name'=>'eins','payload'=>'x','amount'=>'1.5']);$wpdb->insert($wpdb->prefix.'mein_plugin',['name'=>'zwei']);
t('AUTO_INCREMENT',$wpdb->get_col("SELECT id FROM {$wpdb->prefix}mein_plugin ORDER BY id")==[1,2]);
$wpdb->suppress_errors(true);t('UNIQUE KEY greift',$wpdb->insert($wpdb->prefix.'mein_plugin',['name'=>'eins'])===false);
$sql2=str_replace("created datetime","extra varchar(20) NOT NULL DEFAULT 'x',\n  created datetime",$sql);
$res=dbDelta($sql2);t('dbDelta ergänzt fehlende Spalte',isset($res[$wpdb->prefix.'mein_plugin.extra'])&&in_array('extra',$wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}mein_plugin"),true));
t('maybe_create_table',maybe_create_table($wpdb->prefix.'noch_eine',"CREATE TABLE {$wpdb->prefix}noch_eine (a int(11) NOT NULL, b text)"));
t('maybe_add_column',maybe_add_column($wpdb->prefix.'noch_eine','c',"ALTER TABLE {$wpdb->prefix}noch_eine ADD COLUMN c varchar(10) DEFAULT NULL"));
t('TRUNCATE',$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}mein_plugin")!==false&&(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}mein_plugin")===0);
t('DROP TABLE IF EXISTS',$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mein_plugin")!==false&&!$wpdb->table_exists($wpdb->prefix.'mein_plugin'));
// Präfix wechseln
$old=$wpdb->set_prefix('abc_');t('set_prefix',$wpdb->posts==='abc_posts'&&$old==='wp_');$wpdb->set_prefix('wp_');
system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

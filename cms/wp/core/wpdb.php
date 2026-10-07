<?php
// WordPress-kompatibles $wpdb. Datenbank: MySQL (wenn im CMS unter „Datenbank“ konfiguriert) oder SQLite (cms/data/.wp/wp.sqlite).
// Plugins schreiben MySQL-SQL; für SQLite übersetzt RRW_SQL_Translator die gängigen MySQL-Besonderheiten.

if(!class_exists('wpdb')){
class wpdb {
    public $prefix='wp_';public $base_prefix='wp_';public $blogid=1;public $siteid=1;
    public $charset='utf8mb4';public $collate='utf8mb4_unicode_520_ci';
    public $last_error='';public $last_query='';public $last_result=[];public $rows_affected=0;public $insert_id=0;public $num_rows=0;public $num_queries=0;public $queries=[];
    public $show_errors=false;private $schema_tried=false;public $suppress_errors=true;public $ready=false;public $dbname='';public $is_mysql=false;public $driver='none';
    public $posts,$postmeta,$comments,$commentmeta,$terms,$termmeta,$term_taxonomy,$term_relationships,$users,$usermeta,$options,$links,$blogs='';
    private $pdo=null;private $connector=null;private $connected=false;private $found_rows=0;private $found_sql='';private $tr=null;
    public $tables=['posts','comments','links','options','postmeta','terms','term_taxonomy','term_relationships','termmeta','commentmeta'];
    public $global_tables=['users','usermeta'];

    /** $pdo: PDO, oder eine Funktion, die erst beim ersten Zugriff verbindet (spart Zeit bei Anfragen ohne Datenbankbedarf). */
    public function __construct($pdo=null, string $prefix='wp_') {
        $this->set_prefix($prefix);
        if(is_callable($pdo)&&!($pdo instanceof PDO)){ $this->connector=$pdo;$this->ready=true;$this->is_mysql=false; }
        else $this->attach($pdo);
    }
    private function attach($pdo): void {
        $this->connected=true;$this->pdo=$pdo;$this->ready=(bool)$pdo;
        if($pdo){ $this->driver=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);$this->is_mysql=$this->driver==='mysql'; if($this->driver==='sqlite')$this->tr=new RRW_SQL_Translator($pdo); }
    }
    private function conn() {
        if(!$this->connected){ $c=$this->connector;$this->attach($c?$c():null); }
        return $this->pdo;
    }
    public function set_prefix($prefix, $set_table_names=true) {
        $old=$this->prefix;$this->prefix=$this->base_prefix=(string)$prefix;
        foreach(array_merge($this->tables,$this->global_tables) as $t)$this->$t=$this->prefix.$t;
        return $old;
    }
    public function get_blog_prefix($blog_id=null) { return $this->prefix; }
    public function db_version() { $this->conn();return $this->is_mysql?(string)$this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION):'5.7.0'; }
    public function db_server_info() { return $this->db_version(); }
    public function has_cap($c) { return in_array(strtolower((string)$c),['collation','group_concat','subqueries','set_charset','utf8mb4','utf8mb4_520'],true); }
    public function get_charset_collate() { return "DEFAULT CHARACTER SET {$this->charset} COLLATE {$this->collate}"; }
    public function show_errors($show=true) { $o=$this->show_errors;$this->show_errors=$show;return $o; }
    public function hide_errors() { $o=$this->show_errors;$this->show_errors=false;return $o; }
    public function suppress_errors($suppress=true) { $o=$this->suppress_errors;$this->suppress_errors=(bool)$suppress;return $o; }
    public function flush() { $this->last_result=[];$this->last_error='';$this->rows_affected=0;$this->num_rows=0; }
    public function print_error($str='') { rrw_wp_log('SQL-Fehler: '.($str?:$this->last_error).' – '.mb_substr($this->last_query,0,300)); if($this->show_errors)echo '<div class="wpdb-error">'.esc_html($str?:$this->last_error).'</div>'; return false; }
    public function get_col_info($info_type='name', $col_offset=-1) { return []; }
    public function check_connection($allow_bail=true) { return $this->ready; }
    public function esc_like($text) { return addcslashes((string)$text,'_%\\'); }
    public function _real_escape($data) {
        if(is_array($data)){foreach($data as $k=>$v)$data[$k]=$this->_real_escape($v);return $data;}
        $data=(string)$data;
        if($this->conn()){ $q=$this->pdo->quote($data);return substr($q,1,-1); }
        return addslashes($data);
    }
    public function escape($data) { return $this->_real_escape($data); }
    public function _escape($data) { return is_array($data)?array_map([$this,'_escape'],$data):$this->_real_escape((string)$data); }
    public function add_placeholder_escape($query) { return $query; }
    public function remove_placeholder_escape($query) { return $query; }
    public function get_caller() { return ''; }
    public function strip_invalid_text_for_column($table,$column,$value) { return $value; }
    public function get_col_charset($table,$column) { return 'utf8mb4'; }
    public function get_table_charset($table) { return 'utf8mb4'; }
    public function process_fields($table,$data,$format) { return $data; }
    public function close() { return true; }
    public function db_connect($allow_bail=true) { return true; }
    public function select($db,$dbh=null) { return true; }
    public function bail($message,$error_code='500') { return false; }
    public function timer_start() { return true; } public function timer_stop() { return 0; }
    public function escape_by_ref(&$data) { $data=$this->_real_escape($data); }
    public function quote_identifier($s) { return '`'.str_replace('`','``',(string)$s).'`'; }

    /** %s %d %f %i (+ nummerierte %1$s). Werte werden gequotet; Platzhalter in Werten bleiben unverändert. */
    public function prepare($query, ...$args) {
        if($query===null||$query==='')return;
        if(count($args)===1&&is_array($args[0]))$args=$args[0];
        $args=array_values($args);   // wie vsprintf(): nach Reihenfolge, nicht nach Schlüssel
        $query=(string)$query;$idx=0;
        $out=preg_replace_callback('/%(?:(\d+)\$)?([sdfi%])/',function($m) use(&$idx,$args){
            if($m[2]==='%')return '%';
            $i=$m[1]!==''?((int)$m[1])-1:$idx++;
            if(!array_key_exists($i,$args))return $m[0];
            $v=$args[$i];
            switch($m[2]){
                case 'd': return (string)(int)$v;
                case 'f': return rtrim(rtrim(sprintf('%F',(float)$v),'0'),'.')?:'0';
                case 'i': return $this->quote_identifier($v);
                default:  return $v===null?'NULL':"'".$this->_real_escape((string)$v)."'";
            }
        },$query);
        return $out;
    }
    private function format_for($v, $f) {
        if($v===null)return 'NULL';
        if($f==='%d')return (string)(int)$v;
        if($f==='%f')return (string)(float)$v;
        return "'".$this->_real_escape(is_bool($v)?(int)$v:(string)(is_array($v)||is_object($v)?maybe_serialize($v):$v))."'";
    }
    private function cols_formats(array $data, $format): array {
        $cols=[];$vals=[];$i=0;$formats=is_array($format)?array_values($format):($format?array_fill(0,count($data),$format):[]);
        foreach($data as $k=>$v){$f=$formats[$i]??($formats?$formats[0]:'%s');$cols[]=$this->quote_identifier($k);$vals[]=$this->format_for($v,$f);$i++;}
        return [$cols,$vals];
    }
    public function insert($table, $data, $format=null) { return $this->_insert_replace($table,$data,$format,'INSERT'); }
    public function replace($table, $data, $format=null) { return $this->_insert_replace($table,$data,$format,'REPLACE'); }
    private function _insert_replace($table, $data, $format, $type) {
        if(!is_array($data)||!$data)return false;[$c,$v]=$this->cols_formats($data,$format);
        return $this->query("$type INTO ".$this->quote_identifier($table)." (".implode(', ',$c).") VALUES (".implode(', ',$v).")");
    }
    private function where_sql(array $where, $wf): string {
        $parts=[];$i=0;$wfs=is_array($wf)?array_values($wf):($wf?array_fill(0,count($where),$wf):[]);
        foreach($where as $k=>$v){$f=$wfs[$i]??($wfs?$wfs[0]:'%s');$parts[]=$v===null?$this->quote_identifier($k).' IS NULL':$this->quote_identifier($k).' = '.$this->format_for($v,$f);$i++;}
        return implode(' AND ',$parts);
    }
    public function update($table, $data, $where, $format=null, $where_format=null) {
        if(!is_array($data)||!is_array($where)||!$data||!$where)return false;
        $sets=[];$i=0;$formats=is_array($format)?array_values($format):($format?array_fill(0,count($data),$format):[]);
        foreach($data as $k=>$v){$f=$formats[$i]??($formats?$formats[0]:'%s');$sets[]=$this->quote_identifier($k).' = '.$this->format_for($v,$f);$i++;}
        return $this->query("UPDATE ".$this->quote_identifier($table)." SET ".implode(', ',$sets)." WHERE ".$this->where_sql($where,$where_format));
    }
    public function delete($table, $where, $where_format=null) {
        if(!is_array($where)||!$where)return false;
        return $this->query("DELETE FROM ".$this->quote_identifier($table)." WHERE ".$this->where_sql($where,$where_format));
    }

    public function query($query) {
        $this->flush();
        if(!$this->conn()){ $this->ready=false;$this->last_error='Keine Datenbank verfügbar (unter „Datenbank“ MySQL oder SQLite aktivieren).';return false; }
        $query=(string)$query;$query=apply_filters('query',$query);
        $this->last_query=$query;$this->num_queries++;
        if(WP_DEBUG)$this->queries[]=$query;
        $t=$this->tr?$this->tr->translate($query):[$query];     // Liste von Anweisungen
        try{
            $isRead=(bool)preg_match('/^\s*\(?\s*(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN|PRAGMA|WITH)\b/i',$query);
            if($this->tr&&preg_match('/^\s*SELECT\s+SQL_CALC_FOUND_ROWS/i',$query)){ $this->found_sql=$this->tr->count_sql($t[0]); }
            if($this->tr&&preg_match('/^\s*SELECT\s+FOUND_ROWS\(\)/i',$query)){
                $this->last_result=[(object)['FOUND_ROWS()'=>$this->found_rows_calc()]];$this->num_rows=1;return 1;
            }
            if($isRead){
                $st=$this->pdo->query($t[0]);$rows=$st?$st->fetchAll(PDO::FETCH_ASSOC):[];
                $this->last_result=array_map(fn($r)=>(object)$r,$rows);$this->num_rows=count($rows);
                if($this->tr&&preg_match('/^\s*SELECT\s+SQL_CALC_FOUND_ROWS/i',$query)){ $this->found_rows=-1; }
                return $this->num_rows;
            }
            $n=0;foreach($t as $stmt){ if(trim($stmt)==='')continue; $n+=(int)$this->pdo->exec($stmt); }
            $this->rows_affected=$n;
            if(preg_match('/^\s*(INSERT|REPLACE)\b/i',$query))$this->insert_id=(int)$this->pdo->lastInsertId();
            return $n;
        }catch(Throwable $e){
            // Kern-Tabellen fehlen noch (Plugin greift direkt auf wp_options o. Ä. zu): Schema einmal anlegen und die Abfrage wiederholen
            if(!$this->schema_tried&&preg_match('/no such table|doesn\'t exist|does not exist|Base table or view not found/i',$e->getMessage())&&preg_match('/\b'.preg_quote($this->prefix,'/').'(options|posts|postmeta|users|usermeta|terms|term_taxonomy|term_relationships|termmeta|comments|commentmeta|links)\b/',$query)&&function_exists('rrw_wp_install_schema')){
                $this->schema_tried=true;$this->ready=true;
                try{ rrw_wp_install_schema(); }catch(Throwable $e2){}
                return $this->query($query);
            }
            $this->last_error=$e->getMessage();$this->print_error();return false;
        }
    }
    private function found_rows_calc(): int {
        if($this->found_sql==='')return 0;
        try{$st=$this->pdo->query($this->found_sql);return (int)$st->fetchColumn();}catch(Throwable $e){return 0;}
    }
    public function get_var($query=null, $x=0, $y=0) {
        if($query)$this->query($query);
        $r=$this->last_result[$y]??null;if(!$r)return null;$a=array_values(get_object_vars($r));return $a[$x]??null;
    }
    public function get_row($query=null, $output=OBJECT, $y=0) {
        if($query)$this->query($query);
        $r=$this->last_result[$y]??null;if(!$r)return null;
        if($output===ARRAY_A)return get_object_vars($r);if($output===ARRAY_N)return array_values(get_object_vars($r));return $r;
    }
    public function get_col($query=null, $x=0) {
        if($query)$this->query($query);$o=[];
        foreach($this->last_result as $r){$a=array_values(get_object_vars($r));$o[]=$a[$x]??null;}return $o;
    }
    public function get_results($query=null, $output=OBJECT) {
        if($query)$this->query($query);else return null;
        $res=$this->last_result;
        if($output===OBJECT)return $res;
        if($output===OBJECT_K){$o=[];foreach($res as $r){$a=get_object_vars($r);$o[reset($a)]=$r;}return $o;}
        if($output===ARRAY_A)return array_map('get_object_vars',$res);
        if($output===ARRAY_N)return array_map(fn($r)=>array_values(get_object_vars($r)),$res);
        return $res;
    }
    public function table_exists(string $name): bool {
        $this->conn();
        $r=$this->is_mysql?$this->get_var($this->prepare('SHOW TABLES LIKE %s',$name)):$this->get_var($this->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=%s",$name));
        return $r!==null&&$r!==false;
    }
    public function pdo() { return $this->conn(); }
    public function is_mysql() { $this->conn();return $this->is_mysql; }
    public function __get($n) { return null; }
}
}

/** Übersetzt gängiges MySQL-SQL nach SQLite. Funktionen (CONCAT, NOW, …) werden als SQLite-Funktionen bereitgestellt. */
class RRW_SQL_Translator {
    private $pdo;
    public function __construct(PDO $pdo) {
        $this->pdo=$pdo;
        $f=function(string $n,callable $fn,int $args=-1) use($pdo){ $pdo->sqliteCreateFunction($n,$fn,$args); };
        $f('CONCAT',function(...$a){ foreach($a as $x)if($x===null)return null; return implode('',$a); });
        $f('CONVERT_TZ',function($dt,$from,$to){ if($dt===null)return null;try{ $tz=function($z){ $z=(string)$z;return new DateTimeZone(preg_match('/^[+-]\d{1,2}:\d{2}$/',$z)?$z:($z===''?'UTC':$z)); };$d=new DateTime((string)$dt,$tz($from));$d->setTimezone($tz($to));return $d->format('Y-m-d H:i:s'); }catch(Throwable $e){ return null; } },3);
        $f('CONCAT_WS',fn($sep,...$a)=>implode((string)$sep,array_filter($a,fn($x)=>$x!==null)));
        $f('NOW',fn()=>gmdate('Y-m-d H:i:s'),0);$f('UTC_TIMESTAMP',fn()=>gmdate('Y-m-d H:i:s'),0);$f('CURDATE',fn()=>gmdate('Y-m-d'),0);
        $f('UNIX_TIMESTAMP',fn($d=null)=>$d===null?time():(int)strtotime((string)$d.' UTC'));
        $f('FROM_UNIXTIME',fn($t,$fmt=null)=>$fmt?gmdate(str_replace(['%Y','%m','%d','%H','%i','%s'],['Y','m','d','H','i','s'],(string)$fmt),(int)$t):gmdate('Y-m-d H:i:s',(int)$t));
        $f('DATE_FORMAT',function($d,$fmt){ $ts=strtotime((string)$d.' UTC');if($ts===false)return null;return gmdate(strtr((string)$fmt,['%Y'=>'Y','%y'=>'y','%m'=>'m','%c'=>'n','%d'=>'d','%e'=>'j','%H'=>'H','%k'=>'G','%i'=>'i','%s'=>'s','%S'=>'s','%M'=>'F','%b'=>'M','%W'=>'l','%a'=>'D','%j'=>'z','%%'=>'%']),$ts); },2);
        $f('rrw_date_add',function($d,$n,$unit){ $u=['SECOND'=>'seconds','MINUTE'=>'minutes','HOUR'=>'hours','DAY'=>'days','WEEK'=>'weeks','MONTH'=>'months','YEAR'=>'years'][strtoupper((string)$unit)]??'days';$ts=strtotime((string)$d.' UTC '.((int)$n>=0?'+':'').(int)$n.' '.$u);return $ts===false?null:gmdate('Y-m-d H:i:s',$ts); },3);
        $f('FIND_IN_SET',function($n,$list){ $i=array_search((string)$n,explode(',',(string)$list),true);return $i===false?0:$i+1; },2);
        $f('FIELD',function($v,...$list){ $i=array_search((string)$v,array_map('strval',$list),true);return $i===false?0:$i+1; });
        $f('RAND',fn()=>mt_rand()/mt_getrandmax(),-1);$f('LEFT',fn($s,$n)=>mb_substr((string)$s,0,(int)$n),2);$f('RIGHT',fn($s,$n)=>mb_substr((string)$s,-(int)$n),2);
        $f('CHAR_LENGTH',fn($s)=>mb_strlen((string)$s),1);$f('LOCATE',fn($n,$h,$p=1)=>($i=mb_strpos((string)$h,(string)$n,max(0,(int)$p-1)))===false?0:$i+1);
        $f('REGEXP',function($pat,$s){ return @preg_match('~'.str_replace('~','\~',(string)$pat).'~iu',(string)$s)?1:0; },2);
        $f('REVERSE',fn($s)=>implode('',array_reverse(preg_split('//u',(string)$s,-1,PREG_SPLIT_NO_EMPTY))),1);$f('MD5',fn($s)=>md5((string)$s),1);$f('SHA1',fn($s)=>sha1((string)$s),1);
        $f('UNHEX',fn($s)=>hex2bin((string)$s),1);$f('HEX',fn($s)=>strtoupper(bin2hex((string)$s)),1);
        $f('YEAR',fn($d)=>(int)substr((string)$d,0,4),1);$f('MONTH',fn($d)=>(int)substr((string)$d,5,2),1);$f('DAY',fn($d)=>(int)substr((string)$d,8,2),1);
        $f('DATE',fn($d)=>substr((string)$d,0,10),1);$f('TO_DAYS',fn($d)=>(int)(strtotime(substr((string)$d,0,10).' UTC')/86400)+719528,1);
        $f('GREATEST',fn(...$a)=>max($a));$f('LEAST',fn(...$a)=>min($a));$f('IFNULL',fn($a,$b)=>$a??$b,2);$f('POW',fn($a,$b)=>$a**$b,2);
        $f('SUBSTRING_INDEX',function($s,$d,$c){ $p=explode((string)$d,(string)$s);return (int)$c>=0?implode((string)$d,array_slice($p,0,(int)$c)):implode((string)$d,array_slice($p,(int)$c)); },3);
        $pdo->exec('PRAGMA journal_mode=WAL');$pdo->exec('PRAGMA busy_timeout=5000');$pdo->exec('PRAGMA synchronous=NORMAL');
    }
    /** SQL zum Zählen ohne LIMIT (für FOUND_ROWS). */
    public function count_sql(string $sql): string {
        $s=preg_replace('/\s+LIMIT\s+\d+(\s*,\s*\d+|\s+OFFSET\s+\d+)?\s*;?\s*$/i','',$sql);
        $s=preg_replace('/\s+ORDER\s+BY\s+[^()]*$/i','',$s);
        return 'SELECT COUNT(*) FROM ('.$s.')';
    }
    /** @return string[] SQLite-Anweisungen für eine MySQL-Anweisung */
    public function translate(string $sql): array {
        $q=trim($sql);$q=rtrim($q,';');
        if(preg_match('/^\s*SHOW\s+TABLES(?:\s+LIKE\s+(\'[^\']*\'))?\s*$/i',$q,$m))return ["SELECT name AS Tables_in_db FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'".(isset($m[1])?" AND name LIKE $m[1] ESCAPE '\\'":'')];
        if(preg_match('/^\s*SHOW\s+(?:FULL\s+)?COLUMNS\s+FROM\s+`?(\w+)`?/i',$q,$m)||preg_match('/^\s*(?:DESCRIBE|DESC)\s+`?(\w+)`?/i',$q,$m))return ["SELECT name AS Field, type AS Type, CASE WHEN \"notnull\"=1 THEN 'NO' ELSE 'YES' END AS \"Null\", CASE WHEN pk>0 THEN 'PRI' ELSE '' END AS \"Key\", dflt_value AS \"Default\", '' AS Extra FROM pragma_table_info('".str_replace("'","''",$m[1])."')"];
        if(preg_match('/^\s*SHOW\s+(?:INDEX|INDEXES|KEYS)\s+FROM\s+`?(\w+)`?/i',$q,$m))return ["SELECT '".$m[1]."' AS \"Table\", il.\"unique\"=0 AS Non_unique, il.name AS Key_name, ii.seqno+1 AS Seq_in_index, ii.name AS Column_name FROM pragma_index_list('".$m[1]."') il JOIN pragma_index_info(il.name) ii"];
        if(preg_match('/^\s*(?:SET\s+(?:NAMES|SESSION|@@|time_zone|sql_mode)|LOCK\s+TABLES|UNLOCK\s+TABLES|START\s+TRANSACTION|SET\s+autocommit)/i',$q))return ['SELECT 1'];
        if(preg_match('/^\s*TRUNCATE\s+(?:TABLE\s+)?`?(\w+)`?/i',$q,$m))return ['DELETE FROM `'.$m[1].'`',"DELETE FROM sqlite_sequence WHERE name='".$m[1]."'"];
        if(preg_match('/^\s*OPTIMIZE\s+TABLE|^\s*ANALYZE\s+TABLE/i',$q))return ['SELECT 1'];
        if(preg_match('/^\s*(?:COMMIT|ROLLBACK|BEGIN)\b/i',$q))return ['SELECT 1'];   // Transaktionen laufen in SQLite pro Anweisung
        if(preg_match('/^\s*ALTER\s+TABLE\s+`?\w+`?\s+(?:CONVERT\s+TO|DEFAULT\s+CHARACTER|CHARACTER\s+SET|CHANGE\b|MODIFY\b|ENGINE\b|AUTO_INCREMENT\b|COLLATE\b)/i',$q))return ['SELECT 1'];   // Typ-/Zeichensatzänderungen: SQLite ist typlos
        if(preg_match('/^\s*ALTER\s+TABLE\b/i',$q))$q=preg_replace(['/\s+ON\s+UPDATE\s+CURRENT_TIMESTAMP\b/i','/\s+UNSIGNED\b/i'],'',$q);
        // ALTER TABLE t ADD [UNIQUE] INDEX|KEY name (spalten) → CREATE [UNIQUE] INDEX IF NOT EXISTS
        if(preg_match('/^\s*ALTER\s+TABLE\s+`?(\w+)`?\s+ADD\s+(UNIQUE\s+)?(?:INDEX|KEY)\s+`?(\w+)`?\s*(\([^)]*\))\s*$/i',$q,$m))return ['CREATE '.(trim($m[2])!==''?'UNIQUE ':'').'INDEX IF NOT EXISTS `'.$m[3].'` ON `'.$m[1].'` '.$m[4]];
        if(preg_match('/^\s*(?:ALTER\s+TABLE\s+`?\w+`?\s+)?DROP\s+(?:INDEX|KEY)\s+`?(\w+)`?(?:\s+ON\s+`?\w+`?)?\s*$/i',$q,$m))return ['DROP INDEX IF EXISTS `'.$m[1].'`'];
        if(preg_match('/^\s*DELETE\s+\w+\s+FROM\b/i',$q))return ['SELECT 1'];   // DELETE alias FROM … JOIN (Bereinigung doppelter Zeilen)
        if(preg_match('/^\s*DELETE\s+\w+\s*,\s*\w+\s+FROM\b/i',$q))return ['SELECT 1'];   // Mehrtabellen-DELETE (abgelaufene Transients): in SQLite nicht nötig
        if(preg_match('/^\s*CREATE\s+TABLE\b/i',$q))return $this->create_table($q);
        if(preg_match('/^\s*DROP\s+TABLE\s+IF\s+EXISTS\s+(.+)$/i',$q,$m))return array_map(fn($t)=>'DROP TABLE IF EXISTS '.trim($t),explode(',',$m[1]));
        $q=$this->rewrite_dml($q);
        return [$q];
    }
    private function rewrite_dml(string $q): string {
        $q=preg_replace('/^\s*SELECT\s+SQL_CALC_FOUND_ROWS\b/i','SELECT',$q);
        $q=preg_replace('/\s+FROM\s+DUAL\b/i','',$q);   // SELECT … FROM DUAL (MySQL) → SELECT …
        $q=preg_replace('/\bSQL_NO_CACHE\b|\bSTRAIGHT_JOIN\b|\bLOW_PRIORITY\b|\bHIGH_PRIORITY\b/i','',$q);
        $q=preg_replace('/^\s*INSERT\s+IGNORE\s+INTO/i','INSERT OR IGNORE INTO',$q);
        $q=preg_replace('/\bCAST\s*\((.+?)\s+AS\s+(?:UNSIGNED|SIGNED)(?:\s+INTEGER)?\)/i','CAST($1 AS INTEGER)',$q);
        $q=preg_replace('/\bCAST\s*\((.+?)\s+AS\s+(?:DECIMAL\s*\([^)]*\)|DOUBLE|FLOAT)\)/i','CAST($1 AS REAL)',$q);
        $q=preg_replace('/\bCAST\s*\((.+?)\s+AS\s+(?:CHAR|BINARY|DATETIME|DATE)(?:\s*\(\d+\))?\)/i','CAST($1 AS TEXT)',$q);
        $q=preg_replace('/\bCOLLATE\s+\w+/i','',$q);$q=preg_replace('/\bBINARY\s+(?=[\'`\w(])/i','',$q);
        $q=preg_replace_callback('/\bDATE_(ADD|SUB)\s*\(\s*(.+?)\s*,\s*INTERVAL\s+(-?\d+|\'-?\d+\')\s+(\w+)\s*\)/i',fn($m)=>'rrw_date_add('.$m[2].', '.(strtoupper($m[1])==='SUB'?'-':'').'('.trim($m[3],"'").'), \''.$m[4].'\')',$q);
        $q=preg_replace('/\bIF\s*\(/i','iif(',$q);
        // ON DUPLICATE KEY UPDATE → ON CONFLICT(…) DO UPDATE
        if(preg_match('/^\s*INSERT\s+INTO\s+`?(\w+)`?.*\bON\s+DUPLICATE\s+KEY\s+UPDATE\b(.*)$/is',$q,$m)){
            $table=$m[1];preg_match('/^\s*INSERT\s+INTO\s+`?\w+`?\s*\(([^)]*)\)/i',$q,$cm);
            $insCols=isset($cm[1])?array_map(fn($c)=>trim($c,' `'),explode(',',$cm[1])):[];
            $cols=$this->conflict_columns($table,$insCols);
            $upd=preg_replace('/\bVALUES\s*\(\s*`?(\w+)`?\s*\)/i','excluded.`$1`',$m[2]);
            $head=preg_replace('/\s*\bON\s+DUPLICATE\s+KEY\s+UPDATE\b.*$/is','',$q);
            $q=$head.' ON CONFLICT('.implode(', ',array_map(fn($c)=>'`'.$c.'`',$cols)).') DO UPDATE SET '.trim($upd);
        }
        return $q;
    }
    /** Konfliktziel für ON DUPLICATE KEY UPDATE: ein eindeutiger Index, dessen Spalten alle im INSERT stehen; sonst der Primärschlüssel. */
    private function conflict_columns(string $table, array $insCols=[]): array {
        $ti=$this->pdo->query("PRAGMA table_info(`$table`)")->fetchAll(PDO::FETCH_ASSOC);
        foreach($this->pdo->query("PRAGMA index_list(`$table`)")->fetchAll(PDO::FETCH_ASSOC) as $ix){
            if((int)$ix['unique']!==1)continue;
            $cols=array_column($this->pdo->query("PRAGMA index_info(`".$ix['name']."`)")->fetchAll(PDO::FETCH_ASSOC),'name');
            if($cols&&!array_diff($cols,$insCols))return $cols;
        }
        $pk=[];foreach($ti as $c)if((int)$c['pk']>0)$pk[(int)$c['pk']]=$c['name'];ksort($pk);
        if($pk)return array_values($pk);
        return [$ti[0]['name']??'id'];
    }
    /** Eine MySQL-Spaltendefinition nach SQLite. @return array{0:string,1:bool} [Definition, ist AUTO_INCREMENT-Primärschlüssel] */
    public function convert_column(string $name, string $def): array {
        $type='TEXT';
        if(preg_match('/^(tinyint|smallint|mediumint|int|integer|bigint|bit|bool|boolean|year)\b/i',$def))$type='INTEGER';
        elseif(preg_match('/^(decimal|numeric|float|double|real)\b/i',$def))$type='REAL';
        elseif(preg_match('/^(blob|longblob|mediumblob|tinyblob|varbinary|binary)\b/i',$def))$type='BLOB';
        $auto=(bool)preg_match('/\bauto_increment\b/i',$def);
        $rest=preg_replace('/^\w+(\s*\([^)]*\))?(\s+unsigned)?(\s+zerofill)?/i','',$def);
        $rest=preg_replace('/\bauto_increment\b|\bunsigned\b|\bCHARACTER\s+SET\s+\w+|\bCOLLATE\s+\w+|\bON\s+UPDATE\s+CURRENT_TIMESTAMP(\(\))?|\bCOMMENT\s+\'[^\']*\'/i','',$rest);
        $rest=trim(preg_replace('/\s+/',' ',$rest));
        if($auto)return ["`$name` INTEGER PRIMARY KEY AUTOINCREMENT",true];
        return ["`$name` $type".($rest!==''?' '.$rest:''),false];
    }
    /** CREATE TABLE (MySQL, wie von dbDelta/Plugins) → SQLite-CREATE TABLE + CREATE INDEX */
    private function create_table(string $sql): array {
        if(!preg_match('/^\s*CREATE\s+TABLE\s+(IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?\s*\((.*)\)\s*([^()]*)$/is',$sql,$m))return [$sql];
        $ine=$m[1]!==''?'IF NOT EXISTS ':'';$table=$m[2];$body=$m[3];
        // Zeilen auf oberster Klammerebene trennen
        $parts=[];$depth=0;$cur='';$inStr=false;$qch='';
        for($i=0,$n=strlen($body);$i<$n;$i++){
            $ch=$body[$i];
            if($inStr){ $cur.=$ch; if($ch==='\\'){$cur.=$body[++$i]??'';continue;} if($ch===$qch)$inStr=false; continue; }
            if($ch==="'"||$ch==='"'){ $inStr=true;$qch=$ch;$cur.=$ch;continue; }
            if($ch==='(')$depth++;if($ch===')')$depth--;
            if($ch===','&&$depth===0){ $parts[]=trim($cur);$cur='';continue; }
            $cur.=$ch;
        }
        if(trim($cur)!=='')$parts[]=trim($cur);
        $cols=[];$indexes=[];$pkCols=[];$autoCol=null;
        foreach($parts as $p){
            if(preg_match('/^PRIMARY\s+KEY\s*\((.+)\)$/is',$p,$k)){ $pkCols=array_map(fn($c)=>trim(preg_replace('/\(\d+\)/','',trim($c)),' `'),explode(',',$k[1]));continue; }
            if(preg_match('/^(UNIQUE\s+)?(?:KEY|INDEX)\s+`?(\w+)?`?\s*\((.+)\)$/is',$p,$k)){ $indexes[]=['unique'=>trim($k[1])!=='','name'=>$k[2]?:'','cols'=>array_map(fn($c)=>trim(preg_replace('/\(\d+\)/','',trim($c)),' `'),explode(',',$k[3]))];continue; }
            if(preg_match('/^(FULLTEXT|SPATIAL|CONSTRAINT|FOREIGN\s+KEY)\b/i',$p))continue;
            if(preg_match('/^`?(\w+)`?\s+(.*)$/s',$p,$c)){
                [$d,$auto]=$this->convert_column($c[1],$c[2]);
                if($auto)$autoCol=$c[1];
                $cols[]=$d;
            }
        }
        if($pkCols&&!($autoCol&&count($pkCols)===1&&$pkCols[0]===$autoCol))$cols[]='PRIMARY KEY ('.implode(', ',array_map(fn($c)=>"`$c`",$pkCols)).')';
        $out=['CREATE TABLE '.$ine."`$table` (\n  ".implode(",\n  ",$cols)."\n)"];
        foreach($indexes as $ix){ $nm=$table.'_'.($ix['name']?:implode('_',$ix['cols']));$out[]='CREATE '.($ix['unique']?'UNIQUE ':'').'INDEX IF NOT EXISTS `'.$nm.'` ON `'.$table.'` ('.implode(', ',array_map(fn($c)=>"`$c`",$ix['cols'])).')'; }
        return $out;
    }
}

/** Gespeicherte Datenbank-Konfiguration des CMS (cms/data/database.local.php bzw. RRW_DB_CONFIG_FILE) oder null. */
function rrw_wp_db_config(): ?array {
    static $c=false;if($c!==false)return $c;$c=null;
    if(function_exists('rrw_site_current')&&rrw_site_current()!=='')return $c;   // weitere Website: eigene SQLite-Ablage (RRW_WP_DATA) – keine gemeinsamen Tabellen mit der Hauptwebsite
    $f=defined('RRW_DB_CONFIG_FILE')?(string)RRW_DB_CONFIG_FILE:dirname(__DIR__,2).'/data/database.local.php';
    if(is_file($f)){ $x=@include $f;if(is_array($x)&&in_array($x['driver']??'',['mysql','mariadb'],true))$c=$x; }
    return $c;
}
/** Datenbankverbindung für $wpdb: MySQL/MariaDB aus der CMS-Konfiguration, sonst SQLite-Datei (SQLite wird über den SQL-Übersetzer bedient; PostgreSQL gilt nur für den CMS-Spiegel). */
function rrw_wp_db_connect(): ?PDO {
    static $pdo=false;if($pdo!==false)return $pdo;
    $pdo=null;
    try{
        $cfg=rrw_wp_db_config();
        if($cfg&&extension_loaded('pdo_mysql')){
            $dsn='mysql:'.(($cfg['socket']??'')!==''?'unix_socket='.$cfg['socket']:'host='.($cfg['host']??'127.0.0.1').';port='.(int)($cfg['port']??3306)).';dbname='.($cfg['database']??'').';charset='.($cfg['charset']??'utf8mb4');
            $pdo=new PDO($dsn,(string)($cfg['user']??''),(string)($cfg['password']??''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>true,PDO::ATTR_TIMEOUT=>6]);
            return $pdo;
        }
        if(extension_loaded('pdo_sqlite')){
            if(!is_dir(RRW_WP_DATA)){@mkdir(RRW_WP_DATA,0775,true);rrw_wp_protect_dir(RRW_WP_DATA);}
            $path=defined('RRW_WP_SQLITE')?RRW_WP_SQLITE:RRW_WP_DATA.'/wp.sqlite';
            $pdo=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            return $pdo;
        }
    }catch(Throwable $e){ rrw_wp_log('Datenbankverbindung: '.preg_replace('/password=\S+/i','password=***',$e->getMessage()));$pdo=null; }
    return $pdo;
}
function rrw_wp_init_db(): wpdb {
    if(!isset($GLOBALS['wpdb'])||!($GLOBALS['wpdb'] instanceof wpdb)){
        $GLOBALS['wpdb']=new wpdb('rrw_wp_db_connect',(string)(defined('RRW_WP_TABLE_PREFIX')?RRW_WP_TABLE_PREFIX:((rrw_wp_db_config()['prefix']??'')?:'wp_')));
    }
    return $GLOBALS['wpdb'];
}

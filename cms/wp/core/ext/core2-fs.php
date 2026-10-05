<?php
// Ergänzende WordPress-Funktionen (Kern 2, Teil 5): Dateisystem-Hüllen (FTP/SSH), FTP-Basisklassen, PclZip (auf Basis von ZipArchive), Upgrader-Skins, File_Upload_Upgrader.
// Eigenständig umgesetzt. FTP/SSH sind bewusst nicht unterstützt: sie melden Fehler bzw. false; das CMS schreibt Dateien direkt.

/* ───────── Dateisystem-Hüllen ───────── */
if(!class_exists('RRW_WP_Filesystem_Unsupported')){
abstract class RRW_WP_Filesystem_Unsupported extends WP_Filesystem_Base {
    public $method='unsupported';
    public function __construct($opt='') { $this->options=is_array($opt)?$opt:[];$this->errors=new WP_Error(); }
    public function connect() { $this->errors->add('connect','Diese Verbindungsart wird nicht unterstützt; das CMS schreibt Dateien direkt.');return false; }
    public function get_contents($file) { return false; } public function get_contents_array($file) { return false; } public function put_contents($file, $contents, $mode=false) { return false; }
    public function cwd() { return false; } public function chdir($dir) { return false; } public function chgrp($file, $group, $recursive=false) { return false; } public function chmod($file, $mode=false, $recursive=false) { return false; } public function chown($file, $owner, $recursive=false) { return false; }
    public function owner($file) { return false; } public function getchmod($file) { return false; } public function group($file) { return false; }
    public function copy($source, $destination, $overwrite=false, $mode=false) { return false; } public function move($source, $destination, $overwrite=false) { return false; } public function delete($file, $recursive=false, $type=false) { return false; }
    public function exists($path) { return false; } public function is_file($file) { return false; } public function is_dir($path) { return false; } public function is_readable($file) { return false; } public function is_writable($path) { return false; }
    public function atime($file) { return false; } public function mtime($file) { return false; } public function size($file) { return false; } public function touch($file, $time=0, $atime=0) { return false; }
    public function mkdir($path, $chmod=false, $chown=false, $chgrp=false) { return false; } public function rmdir($path, $recursive=false) { return false; } public function dirlist($path, $include_hidden=true, $recursive=false) { return false; }
}
}
if(!class_exists('WP_Filesystem_SSH2')){ class WP_Filesystem_SSH2 extends RRW_WP_Filesystem_Unsupported { public $method='ssh2'; } }
if(!class_exists('WP_Filesystem_ftpsockets')){ class WP_Filesystem_ftpsockets extends RRW_WP_Filesystem_Unsupported { public $method='ftpsockets'; } }
if(!class_exists('WP_Filesystem_FTPext')){ class WP_Filesystem_FTPext extends RRW_WP_Filesystem_Unsupported { public $method='ftpext'; } }

/* ───────── FTP-Basisklassen (nur Schnittstelle, ohne Verbindung) ───────── */
if(!class_exists('ftp_base')){
#[AllowDynamicProperties]
class ftp_base {
    public $LocalEcho=false;public $Verbose=false;public $OS_local;public $OS_remote;
    protected $_lastaction=null;protected $_errors=[];protected $_type=2;protected $_umask=0022;protected $_timeout=30;protected $_passive=true;protected $_host='';protected $_fullhost='';protected $_port=21;
    protected $_datahost=null;protected $_dataport=null;protected $_ftp_control_sock=null;protected $_ftp_data_sock=null;protected $_ftp_temp_sock=null;protected $_ftp_buff_size=4096;protected $_login='anonymous';protected $_password='anon@ftp.com';
    protected $_connected=false;protected $_ready=false;protected $_code=0;protected $_message='';protected $_can_restore=false;protected $_port_available=true;protected $_curtype=null;protected $_features=[];
    protected $_error_array=[];protected $AuthorizedTransferMode=[2,1];protected $OS_FullName=[];protected $_eol_code=[];protected $AutoAsciiExt=['ASP','BAT','C','CPP','CSS','CSV','JS','H','HTM','HTML','SHTML','INI','LOG','PHP3','PHTML','PL','PERL','SH','SQL','TXT'];
    public function __construct($port_mode=false, $verb=false, $le=false) { $this->LocalEcho=$le;$this->Verbose=$verb;$this->_port_available=($port_mode==true);$this->OS_local=PHP_OS_FAMILY==='Windows'?'W':'U'; }
    public function parselisting($line) { return false; }
    public function SetType($mode=2) { if(!in_array($mode,$this->AuthorizedTransferMode,true))return false;$this->_type=$mode;return true; }
    public function Passive($pasv=null) { if($pasv===null)return $this->_passive;$this->_passive=(bool)$pasv;return $this->_passive; }
    public function SetServer($host, $port=21, $reconnect=true) { $this->_host=$host;$this->_port=(int)$port;$this->_fullhost=$host;return true; }
    public function SetUmask($umask=0022) { $this->_umask=$umask;return true; }
    public function setTimeout($timeout=30) { $this->_timeout=(int)$timeout;return true; }
    public function connect($server=null) { $this->PushError('connect','Die FTP-Verbindung wird in dieser Umgebung nicht unterstützt.');return false; }
    public function login($user=null, $pass=null) { $this->PushError('login','Die FTP-Anmeldung wird in dieser Umgebung nicht unterstützt.');return false; }
    public function quit($force=false) { $this->_connected=false;$this->_ready=false;return true; }
    public function PushError($fctname, $msg, $desc=false) { $this->_error_array[]=['code'=>$fctname,'msg'=>$msg,'desc'=>$desc]; }
    public function PopError() { return array_pop($this->_error_array)?:false; }
    public function ready() { return $this->_ready; }
    public function __call($name, $args) { return false; }   // alle übrigen FTP-Befehle (put, get, mkdir, rawlist …): nicht unterstützt
}
}
if(!class_exists('ftp_pure')){ class ftp_pure extends ftp_base { public function __construct($verb=false, $le=false) { parent::__construct(false,$verb,$le); } } }
if(!class_exists('ftp_sockets')){ class ftp_sockets extends ftp_base { public function __construct($verb=false, $le=false) { parent::__construct(true,$verb,$le); } } }

/* ───────── PclZip auf Basis von ZipArchive ───────── */
foreach(['PCLZIP_OPT_PATH'=>77001,'PCLZIP_OPT_ADD_PATH'=>77002,'PCLZIP_OPT_REMOVE_PATH'=>77003,'PCLZIP_OPT_REMOVE_ALL_PATH'=>77004,'PCLZIP_OPT_SET_CHMOD'=>77005,'PCLZIP_OPT_EXTRACT_AS_STRING'=>77006,'PCLZIP_OPT_NO_COMPRESSION'=>77007,'PCLZIP_OPT_BY_NAME'=>77008,'PCLZIP_OPT_BY_INDEX'=>77009,'PCLZIP_OPT_BY_EREG'=>77010,'PCLZIP_OPT_BY_PREG'=>77011,'PCLZIP_OPT_COMMENT'=>77012,'PCLZIP_OPT_ADD_COMMENT'=>77013,'PCLZIP_OPT_PREPEND_COMMENT'=>77014,'PCLZIP_OPT_EXTRACT_IN_OUTPUT'=>77015,'PCLZIP_OPT_REPLACE_NEWER'=>77016,'PCLZIP_OPT_STOP_ON_ERROR'=>77017,'PCLZIP_OPT_EXTRACT_DIR_RESTRICTION'=>77019,'PCLZIP_OPT_TEMP_FILE_THRESHOLD'=>77020,'PCLZIP_OPT_TEMP_FILE_ON'=>77021,'PCLZIP_OPT_TEMP_FILE_OFF'=>77022,
    'PCLZIP_ATT_FILE_NAME'=>79001,'PCLZIP_ATT_FILE_NEW_SHORT_NAME'=>79002,'PCLZIP_ATT_FILE_NEW_FULL_NAME'=>79003,'PCLZIP_ATT_FILE_MTIME'=>79004,'PCLZIP_ATT_FILE_CONTENT'=>79005,'PCLZIP_ATT_FILE_COMMENT'=>79006,
    'PCLZIP_CB_PRE_EXTRACT'=>78001,'PCLZIP_CB_POST_EXTRACT'=>78002,'PCLZIP_CB_PRE_ADD'=>78003,'PCLZIP_CB_POST_ADD'=>78004,
    'PCLZIP_ERR_USER_ABORTED'=>2,'PCLZIP_ERR_NO_ERROR'=>0,'PCLZIP_ERR_WRITE_OPEN_FAIL'=>-1,'PCLZIP_ERR_READ_OPEN_FAIL'=>-2,'PCLZIP_ERR_INVALID_PARAMETER'=>-3,'PCLZIP_ERR_MISSING_FILE'=>-4,'PCLZIP_ERR_FILENAME_TOO_LONG'=>-5,'PCLZIP_ERR_INVALID_ZIP'=>-6,'PCLZIP_ERR_BAD_EXTRACTED_FILE'=>-7,'PCLZIP_ERR_DIR_CREATE_FAIL'=>-8,'PCLZIP_ERR_BAD_EXTENSION'=>-9,'PCLZIP_ERR_BAD_FORMAT'=>-10,'PCLZIP_ERR_DELETE_FILE_FAIL'=>-11,'PCLZIP_ERR_RENAME_FILE_FAIL'=>-12,'PCLZIP_ERR_BAD_CHECKSUM'=>-13,'PCLZIP_ERR_INVALID_ARCHIVE_ZIP'=>-14,'PCLZIP_ERR_MISSING_OPTION_VALUE'=>-15,'PCLZIP_ERR_INVALID_OPTION_VALUE'=>-16,'PCLZIP_ERR_ALREADY_A_DIRECTORY'=>-17,'PCLZIP_ERR_UNSUPPORTED_COMPRESSION'=>-18,'PCLZIP_ERR_UNSUPPORTED_ENCRYPTION'=>-19,'PCLZIP_ERR_INVALID_ATTRIBUTE_VALUE'=>-20,'PCLZIP_ERR_DIRECTORY_RESTRICTION'=>-21,
    'PCLZIP_TEMPORARY_DIR'=>'','PCLZIP_SEPARATOR'=>','] as $__k=>$__v)if(!defined($__k))define($__k,$__v);
unset($__k,$__v);
if(!class_exists('PclZip')){
class PclZip {
    public $zipname='';public $zip_fd=0;public $error_code=1;public $error_string='';public $magic_quotes_status=-1;
    public function __construct($p_zipname) { $this->zipname=(string)$p_zipname;$this->error_code=PCLZIP_ERR_NO_ERROR; }
    private function err(int $code, string $msg) { $this->error_code=$code;$this->error_string=$msg;return 0; }
    private function opts(array $args): array {   // alte Schreibweise (Pfad, ...) oder Optionspaare
        if(count($args)===1&&is_array($args[0]))$args=$args[0];
        $o=[];
        if($args&&is_int($args[0])&&$args[0]>=77000){ for($i=0;$i<count($args);$i++){ $k=$args[$i];
            if(in_array($k,[PCLZIP_OPT_REMOVE_ALL_PATH,PCLZIP_OPT_EXTRACT_AS_STRING,PCLZIP_OPT_NO_COMPRESSION,PCLZIP_OPT_EXTRACT_IN_OUTPUT,PCLZIP_OPT_REPLACE_NEWER,PCLZIP_OPT_STOP_ON_ERROR,PCLZIP_OPT_TEMP_FILE_ON,PCLZIP_OPT_TEMP_FILE_OFF],true))$o[$k]=true;
            else{ $o[$k]=$args[$i+1]??null;$i++; } } }
        else{ if(isset($args[0])&&is_string($args[0]))$o[PCLZIP_OPT_PATH]=$args[0]; if(isset($args[1])&&is_string($args[1]))$o[PCLZIP_OPT_REMOVE_PATH]=$args[1]; }
        return $o;
    }
    private function open(int $flags=0) {
        if(!class_exists('ZipArchive')){ $this->err(PCLZIP_ERR_READ_OPEN_FAIL,'ZipArchive ist nicht verfügbar.');return null; }
        $z=new ZipArchive();$r=$z->open($this->zipname,$flags);
        if($r!==true){ $this->err($flags&ZipArchive::CREATE?PCLZIP_ERR_WRITE_OPEN_FAIL:PCLZIP_ERR_READ_OPEN_FAIL,'Das Archiv konnte nicht geöffnet werden: '.$this->zipname);return null; }
        return $z;
    }
    private function entry(ZipArchive $z, int $i): array {
        $s=$z->statIndex($i);$n=(string)$s['name'];$dir=str_ends_with($n,'/');
        return ['filename'=>$n,'stored_filename'=>$n,'size'=>(int)$s['size'],'compressed_size'=>(int)$s['comp_size'],'mtime'=>(int)$s['mtime'],'comment'=>(string)$z->getCommentIndex($i),'folder'=>$dir,'index'=>$i,'status'=>'ok','crc'=>(int)$s['crc']];
    }
    private function add_files(array $args, bool $reset) {
        $o=$this->opts(array_slice($args,1));if(!$o&&isset($args[1]))$o=[PCLZIP_OPT_ADD_PATH=>(string)$args[1]]+(isset($args[2])?[PCLZIP_OPT_REMOVE_PATH=>(string)$args[2]]:[]);
        $list=$args[0]??[];if(is_string($list))$list=explode(PCLZIP_SEPARATOR,$list);
        $z=$this->open(ZipArchive::CREATE|($reset?ZipArchive::OVERWRITE:0));if(!$z)return 0;
        $add=trim((string)($o[PCLZIP_OPT_ADD_PATH]??''),'/');$rm=(string)($o[PCLZIP_OPT_REMOVE_PATH]??'');$all=!empty($o[PCLZIP_OPT_REMOVE_ALL_PATH]);$names=[];
        $one=function($path,$content=null,$full=null) use($z,$add,$rm,$all,&$names){
            $name=$full??ltrim($all?basename($path):(($rm!==''&&str_starts_with($path,$rm))?substr($path,strlen($rm)):$path),'/\\');
            if($full===null&&$add!=='')$name=$add.'/'.$name;
            $name=str_replace('\\','/',$name);
            if($content!==null)$z->addFromString($name,$content);elseif(is_dir($path))$z->addEmptyDir($name);else $z->addFile($path,$name);
            $names[]=$name;
        };
        foreach($list as $item){
            if(is_array($item)){ $p=$item[PCLZIP_ATT_FILE_NAME]??null;$cont=$item[PCLZIP_ATT_FILE_CONTENT]??null;$full=$item[PCLZIP_ATT_FILE_NEW_FULL_NAME]??($item[PCLZIP_ATT_FILE_NEW_SHORT_NAME]??null);
                if($cont!==null&&($full!==null||$p!==null))$one((string)($full??$p),(string)$cont,(string)($full??$p));elseif($p!==null&&file_exists($p))$one((string)$p,null,$full!==null?(string)$full:null); continue; }
            $item=trim((string)$item);if($item===''||!file_exists($item))continue;
            if(is_dir($item)){ $one($item);$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($item,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);foreach($it as $f)$one($f->getPathname()); }
            else $one($item);
        }
        if(isset($o[PCLZIP_OPT_COMMENT]))$z->setArchiveComment((string)$o[PCLZIP_OPT_COMMENT]);
        $z->close();
        $r=$this->listContent();if(!is_array($r))return 0;
        return array_values(array_filter($r,fn($e)=>in_array(rtrim($e['filename'],'/'),$names,true)));
    }
    public function create($p_filelist, ...$rest) { $this->privErrorReset();return $this->add_files(array_merge([$p_filelist],$rest),true); }
    public function add($p_filelist, ...$rest) { $this->privErrorReset();return $this->add_files(array_merge([$p_filelist],$rest),false); }
    public function listContent() {
        $this->privErrorReset();
        if(!is_file($this->zipname))return $this->err(PCLZIP_ERR_MISSING_FILE,'Archiv fehlt: '.$this->zipname);
        $z=$this->open();if(!$z)return 0;$o=[];for($i=0;$i<$z->numFiles;$i++)$o[]=$this->entry($z,$i);$z->close();return $o;
    }
    public function extract(...$args) {
        $this->privErrorReset();$o=$this->opts($args);
        if(!is_file($this->zipname))return $this->err(PCLZIP_ERR_MISSING_FILE,'Archiv fehlt: '.$this->zipname);
        $z=$this->open();if(!$z)return 0;
        $path=rtrim((string)($o[PCLZIP_OPT_PATH]??'./'),'/\\');$path=$path===''?'.':$path;$add=trim((string)($o[PCLZIP_OPT_ADD_PATH]??''),'/');$rm=(string)($o[PCLZIP_OPT_REMOVE_PATH]??'');$all=!empty($o[PCLZIP_OPT_REMOVE_ALL_PATH]);$str=!empty($o[PCLZIP_OPT_EXTRACT_AS_STRING]);
        $byName=isset($o[PCLZIP_OPT_BY_NAME])?(array)$o[PCLZIP_OPT_BY_NAME]:null;if(is_string($o[PCLZIP_OPT_BY_NAME]??null))$byName=explode(PCLZIP_SEPARATOR,$o[PCLZIP_OPT_BY_NAME]);
        $preg=$o[PCLZIP_OPT_BY_PREG]??null;$res=[];
        if(!$str&&!is_dir($path)&&!@mkdir($path,0775,true)){ $z->close();return $this->err(PCLZIP_ERR_DIR_CREATE_FAIL,'Zielordner nicht anlegbar: '.$path); }
        for($i=0;$i<$z->numFiles;$i++){
            $e=$this->entry($z,$i);$n=$e['filename'];
            if($byName!==null&&!array_filter($byName,fn($b)=>$n===$b||str_starts_with($n,rtrim($b,'/').'/')))continue;
            if($preg!==null&&!preg_match($preg,$n))continue;
            if(isset($o[PCLZIP_OPT_BY_INDEX])&&!in_array($i,(array)$o[PCLZIP_OPT_BY_INDEX],true))continue;
            $t=$all?basename($n):(($rm!==''&&str_starts_with($n,$rm))?substr($n,strlen($rm)):$n);
            $t=ltrim($t,'/');if($add!=='')$t=$add.'/'.$t;
            if($t==='')continue;
            if(preg_match('~(^|[/\\\\])\.\.([/\\\\]|$)~',$t)||str_starts_with($t,'/')){ $e['status']='filtered';$res[]=$e;continue; }   // Pfade außerhalb des Ziels nie schreiben
            $e['stored_filename']=$n;$e['filename']=$path.'/'.$t;
            if($str){ if(!$e['folder'])$e['content']=(string)$z->getFromIndex($i);$res[]=$e;continue; }
            $target=$path.'/'.$t;
            if($e['folder']){ if(!is_dir($target)&&!@mkdir($target,0775,true))$e['status']='path_creation_fail';$res[]=$e;continue; }
            if(!is_dir(dirname($target))&&!@mkdir(dirname($target),0775,true)){ $e['status']='path_creation_fail';$res[]=$e;if(!empty($o[PCLZIP_OPT_STOP_ON_ERROR]))break;continue; }
            if(is_file($target)&&empty($o[PCLZIP_OPT_REPLACE_NEWER])&&filemtime($target)>$e['mtime']){ $e['status']='newer_exist';$res[]=$e;continue; }
            $c=$z->getFromIndex($i);
            if($c===false||@file_put_contents($target,$c)===false){ $e['status']='write_error';$res[]=$e;if(!empty($o[PCLZIP_OPT_STOP_ON_ERROR]))break;continue; }
            if(isset($o[PCLZIP_OPT_SET_CHMOD]))@chmod($target,(int)$o[PCLZIP_OPT_SET_CHMOD]);
            @touch($target,$e['mtime']);$res[]=$e;
        }
        $z->close();return $res;
    }
    public function properties() {
        $this->privErrorReset();if(!is_file($this->zipname))return $this->err(PCLZIP_ERR_MISSING_FILE,'Archiv fehlt: '.$this->zipname);
        $z=$this->open();if(!$z)return 0;$r=['comment'=>(string)$z->getArchiveComment(),'nb'=>$z->numFiles,'status'=>'ok'];$z->close();return $r;
    }
    public function delete(...$args) {
        $this->privErrorReset();$o=$this->opts($args);$z=$this->open();if(!$z)return 0;
        $byName=isset($o[PCLZIP_OPT_BY_NAME])?(array)$o[PCLZIP_OPT_BY_NAME]:[];$preg=$o[PCLZIP_OPT_BY_PREG]??null;$idx=isset($o[PCLZIP_OPT_BY_INDEX])?(array)$o[PCLZIP_OPT_BY_INDEX]:[];$del=[];
        for($i=0;$i<$z->numFiles;$i++){ $n=(string)$z->getNameIndex($i);
            if(in_array($n,$byName,true)||in_array($i,$idx,true)||($preg!==null&&preg_match($preg,$n)))$del[]=$i; }
        foreach($del as $i)$z->deleteIndex($i);
        $z->close();
        return $this->listContent();
    }
    public function duplicate($p_archive) { return $this->err(PCLZIP_ERR_INVALID_PARAMETER,'duplicate() wird nicht unterstützt.'); }
    public function merge($p_archive_to_add) { return $this->err(PCLZIP_ERR_INVALID_PARAMETER,'merge() wird nicht unterstützt.'); }
    public function errorCode() { return $this->error_code; }
    public function errorName($p_with_code=false) {
        $n=array_search($this->error_code,array_filter(get_defined_constants(true)['user']??[],fn($v,$k)=>str_starts_with($k,'PCLZIP_ERR_'),ARRAY_FILTER_USE_BOTH),true);
        $n=$n!==false?$n:'PCLZIP_ERR_NO_ERROR';return $p_with_code?$n.' ('.$this->error_code.')':$n;
    }
    public function errorInfo($p_full=false) { return $p_full?$this->errorName(true).' : '.$this->error_string:$this->error_string.' [code '.$this->error_code.']'; }
    public function privErrorReset() { $this->error_code=PCLZIP_ERR_NO_ERROR;$this->error_string=''; }
    public function privErrorLog($p_error_code=0, $p_error_string='') { $this->error_code=$p_error_code;$this->error_string=$p_error_string; }
    public function privCheckFormat($p_level=0) { $r=$this->properties();return is_array($r); }
}
}

/* ───────── Upgrader-Skins ───────── */
if(!class_exists('RRW_Skin_Base')){
#[AllowDynamicProperties]
class RRW_Skin_Base extends WP_Upgrader_Skin {
    public $options=[];public $messages=[];public $errors=null;public $done_header=false;public $done_footer=false;public $upgrader=null;public $result=false;
    public function __construct($args=[]) { $this->options=wp_parse_args($args,['url'=>'','nonce'=>'','title'=>'','context'=>false]);$this->errors=new WP_Error(); }
    public function set_upgrader(&$upgrader) { if(is_object($upgrader))$this->upgrader=&$upgrader; }
    public function add_strings() {}
    public function set_result($result) { $this->result=$result; }
    public function request_filesystem_credentials($error=false, $context='', $allow_relaxed_file_ownership=false) { return true; }
    public function header() { $this->done_header=true; }
    public function footer() { $this->done_footer=true; }
    public function error($errors) {
        if(is_string($errors)){ $this->messages[]=$errors;return; }
        if(is_wp_error($errors)&&$errors->has_errors())foreach($errors->get_error_messages() as $m)$this->messages[]=$m;
    }
    public function feedback($feedback, ...$args) {
        if(is_wp_error($feedback)){ $this->error($feedback);return; }
        if(isset($this->upgrader->strings[$feedback]))$feedback=$this->upgrader->strings[$feedback];
        if($args)$feedback=@vsprintf((string)$feedback,$args)?:$feedback;
        if((string)$feedback!=='')$this->messages[]=(string)$feedback;
    }
    public function before() {} public function after() {}
    public function get_upgrade_messages() { return $this->messages; }
    public function bulk_header() {} public function bulk_footer() {}
}
}
if(!class_exists('Plugin_Upgrader_Skin')){
class Plugin_Upgrader_Skin extends RRW_Skin_Base {
    public $plugin='';public $plugin_active=false;public $plugin_network_active=false;
    public function __construct($args=[]) {
        $args=wp_parse_args($args,['url'=>'','plugin'=>'','nonce'=>'','title'=>'Plugin aktualisieren']);
        parent::__construct($args);$this->plugin=(string)$args['plugin'];
        $this->plugin_active=$this->plugin!==''&&function_exists('is_plugin_active')&&is_plugin_active($this->plugin);
    }
    public function after() { if($this->upgrader&&method_exists($this->upgrader,'plugin_info'))$this->plugin=$this->upgrader->plugin_info()?:$this->plugin; }
}
}
if(!class_exists('Theme_Upgrader_Skin')){
class Theme_Upgrader_Skin extends RRW_Skin_Base {
    public $theme='';
    public function __construct($args=[]) {
        $args=wp_parse_args($args,['url'=>'','theme'=>'','nonce'=>'','title'=>'Theme aktualisieren']);
        parent::__construct($args);$this->theme=(string)$args['theme'];
    }
    public function after() {}
}
}
if(!class_exists('Bulk_Plugin_Upgrader_Skin')){
class Bulk_Plugin_Upgrader_Skin extends RRW_Skin_Base {
    public $plugin_info=[];public $in_loop=false;public $error=false;
    public function add_strings() { $this->upgrader->strings['skin_upgrade_start']='Das Update beginnt. Das kann einen Moment dauern.'; }
    public function before($title='') { $this->in_loop=true; }
    public function after($title='') { $this->in_loop=false; }
    public function bulk_header() {} public function bulk_footer() {}
}
}
if(!class_exists('Bulk_Theme_Upgrader_Skin')){
class Bulk_Theme_Upgrader_Skin extends RRW_Skin_Base {
    public $theme_info=[];public $in_loop=false;public $error=false;
    public function add_strings() { $this->upgrader->strings['skin_upgrade_start']='Das Update beginnt. Das kann einen Moment dauern.'; }
    public function before($title='') { $this->in_loop=true; }
    public function after($title='') { $this->in_loop=false; }
}
}
if(!class_exists('Language_Pack_Upgrader_Skin')){
class Language_Pack_Upgrader_Skin extends RRW_Skin_Base {
    public $language_update=null;public $done_header=false;public $done_footer=false;public $display_footer_actions=true;
    public function __construct($args=[]) { parent::__construct(wp_parse_args($args,['url'=>'','nonce'=>'','title'=>'Übersetzungen aktualisieren','skip_header_footer'=>false]));
        $this->language_update=$this->options['language_update']??null; }
    public function before() {} public function after() {}
    public function bulk_footer() { $this->done_footer=true; }
}
}

/* ───────── Hochgeladene Pakete für Upgrader ───────── */
if(!class_exists('File_Upload_Upgrader')){
class File_Upload_Upgrader {
    public $package='';public $filename='';public $id=0;
    public function __construct($form, $urlholder) {
        if(empty($_FILES[$form]['name'])&&empty($_GET[$urlholder]))wp_die('Es wurde keine Datei übermittelt.');
        $this->filename=!empty($_FILES[$form]['name'])?sanitize_file_name((string)$_FILES[$form]['name']):'';
        if(!empty($_FILES[$form]['name'])){
            if(($_FILES[$form]['error']??0)!==UPLOAD_ERR_OK)wp_die('Der Upload ist fehlgeschlagen (Fehlercode '.(int)$_FILES[$form]['error'].').');
            $r=wp_handle_upload($_FILES[$form],['test_form'=>false,'test_type'=>false]);
            if(!empty($r['error']))wp_die(esc_html($r['error']));
            $this->package=$r['file'];
        }else{
            $this->filename=sanitize_file_name(wp_unslash((string)$_GET[$urlholder]));
            $this->package=trailingslashit(wp_upload_dir(null,false)['basedir']).'upgrade/'.$this->filename;
            if(!is_file($this->package))wp_die('Die Paketdatei wurde nicht gefunden.');
        }
        $this->id=0;
    }
    public function cleanup() { if($this->package!==''&&is_file($this->package))wp_delete_file($this->package);return true; }
}
}

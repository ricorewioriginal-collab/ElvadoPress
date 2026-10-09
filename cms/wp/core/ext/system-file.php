<?php
// Ergänzende WordPress-Funktionen (Bereich System, Teil 5): Dateien – Auflisten, Hochladen, Prüfsummen, ZIP, Verschieben, Datei-Editor.

if(!function_exists('elvado_ext_rmtree')){ function elvado_ext_rmtree($dir) {   // Ordner samt Inhalt löschen (Symlinks werden nur entfernt, nicht verfolgt)
    $dir=rtrim((string)$dir,'/');if($dir===''||$dir==='/')return false;
    if(is_link($dir)||is_file($dir))return @unlink($dir);
    if(!is_dir($dir))return true;
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $f)$f->isDir()&&!$f->isLink()?@rmdir($f->getPathname()):@unlink($f->getPathname());
    return @rmdir($dir);
} }
if(!function_exists('get_file_description')){ function get_file_description($file) {
    $names=['functions.php'=>__('Theme Functions'),'header.php'=>__('Theme Header'),'footer.php'=>__('Theme Footer'),'sidebar.php'=>__('Sidebar'),'comments.php'=>__('Comments'),'searchform.php'=>__('Search Form'),'404.php'=>__('404 Template'),'link.php'=>__('Links Template'),'theme.json'=>__('Theme Styles & Block Settings'),'style.css'=>__('Stylesheet'),'editor-style.css'=>__('Visual Editor Stylesheet'),'editor-style-rtl.css'=>__('Visual Editor RTL Stylesheet'),'rtl.css'=>__('RTL Stylesheet'),'my-hacks.php'=>__('my-hacks.php (legacy hacks support)'),'.htaccess'=>__('.htaccess (for rewrite rules )'),
        'index.php'=>__('Main Index Template'),'archive.php'=>__('Archives'),'author.php'=>__('Author Template'),'category.php'=>__('Category Template'),'tag.php'=>__('Tag Template'),'taxonomy.php'=>__('Taxonomy Template'),'date.php'=>__('Date Template'),'home.php'=>__('Posts Page'),'front-page.php'=>__('Front Page Template'),'single.php'=>__('Single Post'),'page.php'=>__('Single Page'),'attachment.php'=>__('Attachment Template'),'image.php'=>__('Image Attachment Template'),'search.php'=>__('Search Results'),'privacy-policy.php'=>__('Privacy Policy Page')];
    $p=wp_normalize_path((string)$file);$base=basename($p);
    $root=wp_normalize_path(WP_PLUGIN_DIR);
    if(str_starts_with($p,$root.'/')&&str_ends_with($base,'.php')&&is_file($file)){ $n=get_plugin_data($file,false,false)['Name']??'';if($n!=='')return $n; }
    return $names[$base]??$base;
} }
if(!function_exists('list_files')){ function list_files($folder='', $levels=100, $exclude=[]) {
    if(empty($folder)||$levels<1||!is_dir($folder))return false;
    $out=[];$h=@opendir($folder);if(!$h)return false;
    while(($f=readdir($h))!==false){
        if($f==='.'||$f==='..'||$f[0]==='.'||in_array($f,(array)$exclude,true))continue;
        $p=rtrim($folder,'/').'/'.$f;
        if(is_dir($p)){ $sub=list_files($p,$levels-1,$exclude);if($sub)$out=array_merge($out,$sub);else $out[]=$p.'/'; }   // leere Ordner mit Schrägstrich am Ende
        else $out[]=$p;
    }
    closedir($h);
    return $out;
} }
if(!function_exists('wp_get_plugin_file_editable_extensions')){ function wp_get_plugin_file_editable_extensions($plugin) {
    $e=['bash','conf','css','diff','htm','html','http','inc','include','js','json','jsx','less','md','patch','php','php3','php4','php5','php7','phps','phtml','sass','scss','sh','sql','svg','text','txt','tsx','ts','xml','yaml','yml'];
    return apply_filters('editable_extensions',$e,$plugin);
} }
if(!function_exists('wp_get_theme_file_editable_extensions')){ function wp_get_theme_file_editable_extensions($theme) {
    $e=['bash','conf','css','diff','htm','html','http','inc','include','js','mjs','json','jsx','less','md','patch','php','php3','php4','php5','php7','phps','phtml','sass','scss','sh','sql','svg','text','txt','tsx','ts','xml','yaml','yml'];
    return apply_filters('wp_theme_editor_filetypes',$e,$theme);
} }
if(!function_exists('wp_print_file_editor_templates')){ function wp_print_file_editor_templates() {
    echo '<script type="text/html" id="tmpl-wp-file-editor-notice"><div class="notice inline notice-{{ data.type || \'info\' }} {{ data.alt ? \'notice-alt\' : \'\' }}"><p>{{ data.message }}</p></div></script>'."\n";
} }
if(!function_exists('validate_file_to_edit')){ function validate_file_to_edit($file, $allowed_files=[]) {
    $code=validate_file($file,$allowed_files);if(!$code)return $file;
    wp_die($code===3?__('Sorry, that file cannot be edited.'):__('Sorry, you are not allowed to edit this file.'));
} }
if(!function_exists('wp_edit_theme_plugin_file')){ function wp_edit_theme_plugin_file($args) {
    if(empty($args['file']))return new WP_Error('missing_file',__('Invalid file.'));
    if(validate_file($args['file'])!==0)return new WP_Error('bad_file',__('Invalid file.'));
    if(!isset($args['newcontent']))return new WP_Error('missing_content',__('Content for new file is missing.'));
    if(defined('DISALLOW_FILE_EDIT')&&DISALLOW_FILE_EDIT||defined('DISALLOW_FILE_MODS')&&DISALLOW_FILE_MODS)return new WP_Error('disallow_file_edit',__('Sorry, you are not allowed to edit files.'));
    $file=(string)$args['file'];
    if(!empty($args['plugin'])){
        if(!current_user_can('edit_plugins'))return new WP_Error('unauthorized',__('Sorry, you are not allowed to edit plugins for this site.'));
        if(empty($args['nonce'])||!wp_verify_nonce($args['nonce'],'edit-plugin_'.$file))return new WP_Error('nonce_failure',__('The request has failed due to an expired nonce.'));
        $root=WP_PLUGIN_DIR;$path=$root.'/'.$file;$exts=wp_get_plugin_file_editable_extensions((string)$args['plugin']);
    } elseif(!empty($args['theme'])){
        if(!current_user_can('edit_themes'))return new WP_Error('unauthorized',__('Sorry, you are not allowed to edit templates for this site.'));
        $t=wp_get_theme((string)$args['theme']);if(!$t->exists())return new WP_Error('invalid_theme',__('Invalid theme.'));
        if(empty($args['nonce'])||!wp_verify_nonce($args['nonce'],'edit-theme_'.$t->get_stylesheet().'_'.$file))return new WP_Error('nonce_failure',__('The request has failed due to an expired nonce.'));
        $root=$t->get_stylesheet_directory();$path=$root.'/'.$file;$exts=wp_get_theme_file_editable_extensions($t);
    } else return new WP_Error('missing_type',__('Invalid file.'));
    $real=realpath($path);$rr=realpath($root);
    if(!$real||!$rr||!str_starts_with($real,$rr.DIRECTORY_SEPARATOR)||!is_file($real))return new WP_Error('file_does_not_exist',__('File does not exist! Please double check the name and try again.'));
    if(!in_array(strtolower(pathinfo($real,PATHINFO_EXTENSION)),(array)$exts,true))return new WP_Error('bad_file_type',__('Files of this type cannot be edited.'));
    if(!is_writable($real))return new WP_Error('file_not_writable',__('You need to make this file writable before you can save your changes.'));
    if(@file_put_contents($real,wp_unslash((string)$args['newcontent']))===false)return new WP_Error('file_not_writable',__('Unable to write to the file.'));
    wp_opcache_invalidate($real,true);
    return true;
} }

/* ───────── Hochladen ───────── */
if(!function_exists('_wp_handle_upload')){ function _wp_handle_upload(&$file, $overrides, $time, $action) {
    $file=apply_filters("{$action}_prefilter",$file);
    $o=is_array($overrides)?$overrides:[];$o+=['test_form'=>true,'test_size'=>true,'test_upload'=>true,'test_type'=>true];
    $err=function($m) use($o,&$file){ return isset($o['upload_error_handler'])&&is_callable($o['upload_error_handler'])?call_user_func_array($o['upload_error_handler'],[&$file,$m]):['error'=>$m]; };
    if($o['test_form']&&(!isset($_POST['action'])||$_POST['action']!==($o['action']??$action)))return $err(__('Invalid form submission.'));
    $msgs=[UPLOAD_ERR_INI_SIZE=>__('The uploaded file exceeds the upload_max_filesize directive in php.ini.'),UPLOAD_ERR_FORM_SIZE=>__('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.'),UPLOAD_ERR_PARTIAL=>__('The uploaded file was only partially uploaded.'),UPLOAD_ERR_NO_FILE=>__('No file was uploaded.'),UPLOAD_ERR_NO_TMP_DIR=>__('Missing a temporary folder.'),UPLOAD_ERR_CANT_WRITE=>__('Failed to write file to disk.'),UPLOAD_ERR_EXTENSION=>__('File upload stopped by extension.')];
    if(!empty($file['error']))return $err($msgs[$file['error']]??__('Unknown upload error.'));
    if($o['test_size']&&empty($file['size']))return $err(__('File is empty. Please upload something more substantial.'));
    if(empty($file['name'])||empty($file['tmp_name']))return $err(__('Invalid file.'));
    if($o['test_upload']&&$action==='wp_handle_upload'&&!@is_uploaded_file($file['tmp_name']))return $err(__('Specified file failed upload test.'));
    $type='';$ext='';
    if($o['test_type']){
        $ft=wp_check_filetype_and_ext($file['tmp_name'],$file['name'],$o['mimes']??null);$type=(string)$ft['type'];$ext=(string)$ft['ext'];
        if(!empty($o['mimes'])){ $ext=strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION));$type='';foreach($o['mimes'] as $re=>$m)if(preg_match('!^('.$re.')$!i',$ext)){ $type=$m;break; } }
        if((!$type||!$ext)&&!current_user_can('unfiltered_upload'))return $err(__('Sorry, you are not allowed to upload this file type.'));
    }
    $u=wp_upload_dir($time);if(!empty($u['error']))return $err($u['error']);
    $name=wp_unique_filename($u['path'],(string)$file['name'],$o['unique_filename_callback']??null);
    if(!wp_mkdir_p($u['path']))return $err(sprintf(__('Unable to create directory %s.'),$u['path']));
    $new=rtrim($u['path'],'/').'/'.$name;
    $ok=$action==='wp_handle_upload'&&$o['test_upload']?@move_uploaded_file($file['tmp_name'],$new):@rename($file['tmp_name'],$new)||(@copy($file['tmp_name'],$new)&&@unlink($file['tmp_name']));
    if($ok===false||!file_exists($new))return $err(sprintf(__('The uploaded file could not be moved to %s.'),$u['path']));
    @chmod($new,0644);
    return apply_filters('wp_handle_upload',['file'=>$new,'url'=>rtrim($u['url'],'/').'/'.rawurlencode($name),'type'=>$type],$action==='wp_handle_upload'?'upload':'sideload');
} }
if(!function_exists('wp_handle_upload')){ function wp_handle_upload(&$file, $overrides=false, $time=null) { return _wp_handle_upload($file,$overrides,$time,'wp_handle_upload'); } }
if(!function_exists('wp_handle_sideload')){ function wp_handle_sideload(&$file, $overrides=false, $time=null) { return _wp_handle_upload($file,$overrides,$time,'wp_handle_sideload'); } }

/* ───────── Prüfsummen, Signaturen ───────── */
if(!function_exists('verify_file_md5')){ function verify_file_md5($filename, $expected_md5) {
    if(!is_file($filename))return new WP_Error('md5_missing',__('The file is missing.'));
    $m=md5_file($filename);
    return hash_equals(strtolower((string)$expected_md5),$m)?true:new WP_Error('md5_mismatch',sprintf(__('The checksum of the file (%1$s) does not match the expected checksum value (%2$s).'),$m,$expected_md5));
} }
if(!function_exists('wp_trusted_keys')){ function wp_trusted_keys() { return (array)apply_filters('wp_trusted_keys',[]); } }   // keine eingebauten Schlüssel: nur per Filter
if(!function_exists('verify_file_signature')){ function verify_file_signature($filename, $signatures, $filename_for_errors=false) {
    $shown=$filename_for_errors?:wp_basename($filename);
    if(!function_exists('sodium_crypto_sign_verify_detached'))return new WP_Error('signature_verification_unsupported',sprintf(__('The authenticity of %s could not be verified as signature verification is unavailable on this system.'),'<span class="code">'.esc_html($shown).'</span>'),!function_exists('sodium_crypto_sign_verify_detached')?'sodium_crypto_sign_verify_detached':'');
    if(!$signatures)return new WP_Error('signature_verification_no_signature',sprintf(__('The authenticity of %s could not be verified as no signature was found.'),'<span class="code">'.esc_html($shown).'</span>'));
    $keys=wp_trusted_keys();if(!$keys)return new WP_Error('signature_verification_no_trusted_keys',sprintf(__('The authenticity of %s could not be verified as no trusted signature keys are available.'),'<span class="code">'.esc_html($shown).'</span>'));
    $hash=hash_file('sha384',$filename,true);if($hash===false)return new WP_Error('signature_verification_failed',__('The file could not be read.'));
    foreach((array)$signatures as $s){ $sig=base64_decode((string)$s,true);if($sig===false||strlen($sig)!==SODIUM_CRYPTO_SIGN_BYTES)continue;
        foreach($keys as $k){ $kb=base64_decode((string)$k,true);if($kb!==false&&strlen($kb)===SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES&&sodium_crypto_sign_verify_detached($sig,$hash,$kb))return true; } }
    return new WP_Error('signature_verification_failed',sprintf(__('The authenticity of %s could not be verified.'),'<span class="code">'.esc_html($shown).'</span>'));
} }

/* ───────── ZIP, Verschieben ───────── */
if(!function_exists('wp_zip_file_is_valid')){ function wp_zip_file_is_valid($file) {
    if(!class_exists('ZipArchive')||!is_file((string)$file))return false;
    $z=new ZipArchive();$r=$z->open($file,ZipArchive::CHECKCONS);if($r===true){ $z->close();return true; }return false;
} }
if(!function_exists('_unzip_file_ziparchive')){ function _unzip_file_ziparchive($file, $to, $needed_dirs=[]) {
    $z=new ZipArchive();if($z->open($file,ZipArchive::CHECKCONS)!==true)return new WP_Error('incompatible_archive',__('Incompatible Archive.'));
    $to=trailingslashit($to);$total=0;$max=(int)apply_filters('elvado_unzip_max_bytes',1073741824);$names=[];
    for($i=0;$i<$z->numFiles;$i++){
        $s=$z->statIndex($i);if(!$s){ $z->close();return new WP_Error('stat_failed_ziparchive',__('Could not retrieve file from archive.')); }
        $n=str_replace('\\','/',(string)$s['name']);if(str_starts_with($n,'__MACOSX/'))continue;
        if($n===''||$n[0]==='/'||preg_match('#(^|/)\.\.(/|$)#',$n)||str_contains($n,"\0")||preg_match('#^[A-Za-z]:#',$n)){ $z->close();return new WP_Error('invalid_path',sprintf(__('Unsafe path in archive: %s'),$n)); }   // Zip-Slip verhindern
        $total+=(int)$s['size'];if($total>$max){ $z->close();return new WP_Error('disk_full_unzip_file',__('Could not copy file. The archive is too large.')); }
        $names[$i]=$n;
    }
    foreach($names as $i=>$n){
        if(str_ends_with($n,'/')){ wp_mkdir_p($to.rtrim($n,'/'));continue; }
        wp_mkdir_p(dirname($to.$n));$c=$z->getFromIndex($i);
        if($c===false){ $z->close();return new WP_Error('copy_failed_ziparchive',__('Could not copy file.'),$n); }
        if(@file_put_contents($to.$n,$c)===false){ $z->close();return new WP_Error('copy_failed_ziparchive',__('Could not copy file.'),$n); }
    }
    $z->close();return true;
} }
if(!function_exists('_unzip_file_pclzip')){ function _unzip_file_pclzip($file, $to, $needed_dirs=[]) { return new WP_Error('incompatible_archive',__('PclZip is not available; ZipArchive is required.')); } }
if(!function_exists('unzip_file')){ function unzip_file($file, $to) {
    if(!is_file((string)$file))return new WP_Error('incompatible_archive',__('Incompatible Archive.'));
    $to=trailingslashit($to);if(!is_dir($to)&&!wp_mkdir_p($to))return new WP_Error('mkdir_failed',__('Could not create directory.'),$to);
    if(!class_exists('ZipArchive'))return _unzip_file_pclzip($file,$to);
    return _unzip_file_ziparchive($file,$to);
} }
if(!function_exists('move_dir')){ function move_dir($from, $to, $overwrite=false) {
    $from=untrailingslashit((string)$from);$to=untrailingslashit((string)$to);
    if(strtolower($from)===strtolower($to))return new WP_Error('source_destination_same_move_dir',__('The source and destination are the same.'));
    if(!is_dir($from))return new WP_Error('source_missing_move_dir',__('The source directory does not exist.'));
    if(file_exists($to)){ if(!$overwrite)return new WP_Error('destination_already_exists_move_dir',__('The destination folder already exists.'),$to);elvado_ext_rmtree($to); }
    wp_mkdir_p(dirname($to));
    if(@rename($from,$to))return true;
    if(!wp_mkdir_p($to))return new WP_Error('mkdir_failed_move_dir',__('Could not create directory.'),$to);
    $r=copy_dir($from,$to);if(is_wp_error($r))return $r;
    elvado_ext_rmtree($from);return true;
} }
if(!function_exists('wp_print_request_filesystem_credentials_modal')){ function wp_print_request_filesystem_credentials_modal() { /* direkter Dateizugriff: keine Zugangsdaten nötig, also kein Dialog */ } }
if(!function_exists('wp_opcache_invalidate')){ function wp_opcache_invalidate($filepath, $force=false) {
    static $on=null;if($on===null)$on=function_exists('opcache_invalidate')&&filter_var(ini_get('opcache.enable'),FILTER_VALIDATE_BOOLEAN)&&(PHP_SAPI!=='cli'||filter_var(ini_get('opcache.enable_cli'),FILTER_VALIDATE_BOOLEAN));
    if(!$on||strtolower(pathinfo((string)$filepath,PATHINFO_EXTENSION))!=='php')return false;
    return @opcache_invalidate((string)$filepath,(bool)$force);
} }
if(!function_exists('wp_opcache_invalidate_directory')){ function wp_opcache_invalidate_directory($dir) {
    if(!is_string($dir)||$dir===''||!is_dir($dir))return;
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS)) as $f)if($f->isFile())wp_opcache_invalidate($f->getPathname(),true);
} }

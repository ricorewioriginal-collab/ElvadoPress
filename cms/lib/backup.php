<?php
declare(strict_types=1);

function elvado_backup_dir(): string { $d=__DIR__.'/../backups'; if(!is_dir($d))@mkdir($d,0755,true); elvado_protect_dir($d); return realpath($d)?:$d; }
function elvado_backup_list(): array {
    $out=[]; foreach(glob(elvado_backup_dir().'/*.zip')?:[] as $f){
        $out[]=['name'=>basename($f),'size'=>filesize($f)?:0,'created_at'=>date(DATE_ATOM,filemtime($f)?:time())];
    }
    usort($out,fn($a,$b)=>strcmp($b['created_at'],$a['created_at'])); return $out;
}
function elvado_backup_add_file(ZipArchive $zip,string $path,string $name): void {
    if(is_file($path))$zip->addFile($path,$name);
}
function elvado_backup_add_dir(ZipArchive $zip,string $dir,string $prefix): void {
    if(!is_dir($dir))return;
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
    foreach($it as $f){$path=$f->getPathname();$rel=$prefix.'/'.str_replace('\\','/',substr($path,strlen($dir)+1));if(str_contains($rel,'/backups/'))continue;if($f->isDir())$zip->addEmptyDir($rel);else $zip->addFile($path,$rel);}
}
function elvado_backup_create(string $root,bool $includeMedia=true): array {
    if(function_exists('elvado_np_filter')){ $o=elvado_np_filter('backup_create',null,$root,$includeMedia);if(is_array($o))return $o;if(is_string($o)&&$o!=='')throw new RuntimeException($o); }   // Plugin „Elvado Backup“ ersetzt die Erstellung (vollständig, ohne Geheimnisse, mit Prüfsummen)
    if(!class_exists('ZipArchive'))throw new RuntimeException('ZIP-Unterstützung ist nicht verfügbar');
    $name='elvado-cms-backup_'.date('Y-m-d_H-i-s').'.zip';$file=elvado_backup_dir().'/'.$name;
    $zip=new ZipArchive();if($zip->open($file,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Backup-ZIP konnte nicht erstellt werden');
    elvado_backup_add_file($zip,__DIR__.'/../data/site.json','cms/data/site.json');
    elvado_backup_add_file($zip,__DIR__.'/../data/news.json','cms/data/news.json');
    elvado_backup_add_file($zip,__DIR__.'/../data/comments.json','cms/data/comments.json');
    elvado_backup_add_file($zip,__DIR__.'/../data/news-revisions.json','cms/data/news-revisions.json');
    elvado_backup_add_dir($zip,__DIR__.'/../themes','cms/themes');
    elvado_backup_add_dir($zip,__DIR__.'/../plugins','cms/plugins');
    elvado_backup_add_dir($zip,__DIR__.'/../content','cms/content');
    if($includeMedia)elvado_backup_add_dir($zip,__DIR__.'/../media','cms/media');
    foreach(glob($root.'/*.html')?:[] as $p){$head=(string)@file_get_contents($p,false,null,0,128);if(str_contains($head,'ELVADO-CMS-GENERATED'))$zip->addFile($p,basename($p));}
    elvado_backup_add_file($zip,$root.'/rss.xml','rss.xml');
    elvado_backup_add_file($zip,$root.'/sitemap.xml','sitemap.xml');
    elvado_backup_add_file($zip,$root.'/robots.txt','robots.txt');
    $manifest=['version'=>1,'created_at'=>date(DATE_ATOM),'include_media'=>$includeMedia,'source'=>function_exists('elvado_product_title')?elvado_product_title():'ElvadoPress'];
    $zip->addFromString('backup.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $zip->close();return ['name'=>$name,'size'=>filesize($file)?:0,'created_at'=>$manifest['created_at']];
}
function elvado_backup_safe_entry(string $name): bool {
    $name=str_replace('\\','/',$name);return $name!==''&&!str_contains($name,'../')&&!str_starts_with($name,'/')&&!preg_match('/^[A-Za-z]:/',$name);
}
function elvado_backup_restore(string $name,string $root): void {
    if(function_exists('elvado_np_filter')){ $o=elvado_np_filter('backup_restore',false,$name,$root);if($o===true)return;if(is_string($o)&&$o!=='')throw new RuntimeException($o); }   // Plugin „Elvado Backup“: prüft, sichert vorher, stellt wieder her
    $name=basename($name);$file=elvado_backup_dir().'/'.$name;if(!is_file($file))throw new RuntimeException('Backup nicht gefunden');
    if(!class_exists('ZipArchive'))throw new RuntimeException('ZIP-Unterstützung ist nicht verfügbar');
    $zip=new ZipArchive();if($zip->open($file)!==true)throw new RuntimeException('Backup kann nicht gelesen werden');
    $allowPrefixes=['cms/data/','cms/media/','cms/themes/','cms/plugins/','cms/content/'];
    for($i=0;$i<$zip->numFiles;$i++){
        $entry=str_replace('\\','/',$zip->getNameIndex($i));if(!elvado_backup_safe_entry($entry)||str_ends_with($entry,'/'))continue;
        $allowed=in_array($entry,['rss.xml','sitemap.xml','robots.txt'],true);
        foreach($allowPrefixes as $p)if(str_starts_with($entry,$p)){$allowed=true;break;}
        if(!$allowed&&preg_match('/^[a-z0-9][a-z0-9-]*\.html$/i',$entry))$allowed=true;
        if(!$allowed)continue;$data=$zip->getFromIndex($i);if($data===false)continue;
        $dest=$root.'/'.$entry;$dir=dirname($dest);if(!is_dir($dir))@mkdir($dir,0755,true);elvado_write_atomic($dest,(string)$data);
    }
    $zip->close();
}

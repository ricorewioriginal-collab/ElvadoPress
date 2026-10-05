<?php
declare(strict_types=1);
// Medienbibliothek (cms/media): Auflisten, Auflösen, Bildvarianten und Ablage von Uploads.
// Gemeinsam genutzt von cms/api.php (CMS-Verwaltung) und der WordPress-Schicht (Anhänge, media_handle_upload, REST /wp/v2/media),
// damit es nur einen Speicherweg gibt.

function rrw_media_dir(): string { return defined('RRW_MEDIA_DIR')?rtrim((string)RRW_MEDIA_DIR,'/'):dirname(__DIR__).'/media'; }
function rrw_image_resource(string $file,string $mime) {
    if(!function_exists('imagecreatetruecolor'))return null;
    return match($mime){
        'image/jpeg'=>function_exists('imagecreatefromjpeg')?@imagecreatefromjpeg($file):null,
        'image/png'=>function_exists('imagecreatefrompng')?@imagecreatefrompng($file):null,
        'image/webp'=>function_exists('imagecreatefromwebp')?@imagecreatefromwebp($file):null,
        'image/gif'=>function_exists('imagecreatefromgif')?@imagecreatefromgif($file):null,
        default=>null
    };
}
function rrw_resize_image_file(string $src,string $mime,int $width,string $dest,int $quality=86,string $format='webp'): bool {
    $im=rrw_image_resource($src,$mime);if(!$im)return false;
    $sw=imagesx($im);$sh=imagesy($im);if($sw<1||$sh<1){imagedestroy($im);return false;}
    $width=max(16,min(4096,$width));$tw=min($width,$sw);$th=max(1,(int)round($sh*($tw/$sw)));
    $out=imagecreatetruecolor($tw,$th);
    if(in_array($mime,['image/png','image/webp','image/gif'],true)){imagealphablending($out,false);imagesavealpha($out,true);$transparent=imagecolorallocatealpha($out,0,0,0,127);imagefill($out,0,0,$transparent);}
    imagecopyresampled($out,$im,0,0,0,0,$tw,$th,$sw,$sh);
    $ok=false;
    if($format==='png'&&function_exists('imagepng'))$ok=@imagepng($out,$dest,6);
    elseif($format==='webp'&&function_exists('imagewebp'))$ok=@imagewebp($out,$dest,max(45,min(100,$quality)));
    elseif(function_exists('imagejpeg'))$ok=@imagejpeg($out,$dest,max(45,min(100,$quality)));
    imagedestroy($out);imagedestroy($im);if($ok)@chmod($dest,0644);return $ok;
}
function rrw_media_sizes($raw): array {
    if(is_array($raw))$parts=$raw;else $parts=preg_split('/[^0-9]+/',(string)$raw,-1,PREG_SPLIT_NO_EMPTY);
    $sizes=[];foreach($parts as $v){$n=(int)$v;if($n>=32&&$n<=4096)$sizes[]=$n;}
    $sizes=array_values(array_unique($sizes));sort($sizes,SORT_NUMERIC);return array_slice($sizes,0,14);
}
/**
 * Legt ein hochgeladenes Bild in der Bibliothek ab (Ordner library/<id>/ mit Original, Varianten und meta.json).
 * $f = Eintrag aus $_FILES; $mover = Funktion zum Verschieben (Standard move_uploaded_file; Tests/CLI: rename). Fehler als RuntimeException (Code = HTTP-Status).
 */
function rrw_media_library_store(array $f,array $sizes,int $quality=86,string $mover='move_uploaded_file'): array {
    if(($f['size']??0)<=0||$f['size']>20*1024*1024)throw new RuntimeException('Datei darf maximal 20 MB groß sein',400);
    if(!is_file((string)($f['tmp_name']??'')))throw new RuntimeException('Keine Datei',400);
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $map=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif','image/svg+xml'=>'svg','image/x-icon'=>'ico','image/vnd.microsoft.icon'=>'ico','application/octet-stream'=>'ico'];
    if(!isset($map[$mime]))throw new RuntimeException('Nicht unterstütztes Bildformat',400);
    if($mime==='image/svg+xml'&&preg_match('/<(script|foreignObject)\b|on[a-z]+\s*=|javascript:/i',(string)file_get_contents($f['tmp_name'])))throw new RuntimeException('Unsicheres SVG',400);
    $id=date('Ymd_His').'_'.bin2hex(random_bytes(5));$dir=rrw_media_dir().'/library/'.$id;if(!is_dir($dir)&&!@mkdir($dir,0755,true))throw new RuntimeException('Medienordner konnte nicht angelegt werden',500);
    $ext=$map[$mime];$original=$dir.'/original.'.$ext;
    if(!$mover($f['tmp_name'],$original))throw new RuntimeException('Upload fehlgeschlagen',500);@chmod($original,0644);
    $variants=[];$warnings=[];$canResize=rrw_image_resource($original,$mime)!==null;
    if($canResize){
        $probe=rrw_image_resource($original,$mime);$sw=$probe?imagesx($probe):0;$sh=$probe?imagesy($probe):0;if($probe)imagedestroy($probe);
        foreach($sizes as $w){
            if($sw>0&&$w>$sw)continue;
            $dest=$dir.'/w'.$w.'.webp';
            if(rrw_resize_image_file($original,$mime,$w,$dest,$quality,'webp'))$variants[]=['width'=>$w,'url'=>'/cms/media/library/'.$id.'/w'.$w.'.webp','path'=>'library/'.$id.'/w'.$w.'.webp','format'=>'webp','size'=>@filesize($dest)?:0];
        }
        if(!$variants&&$sizes)$warnings[]='Server kann für dieses Bild keine WebP-Varianten erzeugen; Original bleibt verfügbar.';
    } elseif($sizes&&in_array($ext,['jpg','jpeg','png','webp','gif'],true))$warnings[]='GD-Bildbibliothek ist auf dem Server nicht verfügbar; Original wurde gespeichert.';
    $meta=['id'=>$id,'name'=>mb_substr((string)($f['name']??('Bild '.$id)),0,200),'mime'=>$mime,'original'=>['url'=>'/cms/media/library/'.$id.'/original.'.$ext,'path'=>'library/'.$id.'/original.'.$ext,'size'=>(int)($f['size']??0)],'width'=>$sw??0,'height'=>$sh??0,'variants'=>$variants,'created_at'=>date(DATE_ATOM)];
    rrw_write_atomic($dir.'/meta.json',json_encode($meta,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    return ['item'=>$meta,'warnings'=>$warnings];
}
function rrw_media_pick_variant(array $item,$requested='auto',string $kind=''): string {
    $target=['favicon'=>192,'portal_icon'=>512,'android_app_icon'=>512,'android_inapp_logo'=>1024,'windows_logo'=>1024,'portal_logo'=>1200,'android_startscreen'=>1600][$kind]??1024;
    if(is_numeric($requested))$target=max(32,min(4096,(int)$requested));
    $vars=(array)($item['variants']??[]);if(!$vars)return (string)($item['original']['url']??'');
    usort($vars,fn($a,$b)=>abs((int)($a['width']??0)-$target)<=>abs((int)($b['width']??0)-$target));
    return (string)($vars[0]['url']??($item['original']['url']??''));
}
function rrw_media_item_from_path(string $path): ?array {
    $raw=trim($path);
    if(preg_match('#^https?://#i',$raw)){
        $u=@parse_url($raw);$raw=is_array($u)?(string)($u['path']??''):$raw;
    }
    $raw=str_replace('\\','/',$raw);
    foreach(['/cms/media/','cms/media/','/media/'] as $prefix){
        if(str_starts_with($raw,$prefix)){$raw=substr($raw,strlen($prefix));break;}
    }
    $raw=ltrim($raw,'/');
    if(preg_match('#^library/([^/]+)(?:/.*)?$#',$raw,$m))$raw='library/'.$m[1];
    $rel=rrw_safe_media_rel($raw);if($rel==='')return null;$parts=explode('/',$rel);
    if(($parts[0]??'')==='library'&&!empty($parts[1])){
        $dir=rrw_media_dir().'/library/'.$parts[1];$meta=$dir.'/meta.json';
        if(is_file($meta)){ $j=json_decode((string)file_get_contents($meta),true); if(is_array($j))return $j; }
        $file=rrw_media_dir().'/'.$rel;
        if(is_file($file)){
            $mime=function_exists('mime_content_type')?(string)@mime_content_type($file):'';
            return ['id'=>md5($rel),'name'=>basename($file),'mime'=>$mime,'original'=>['url'=>'/cms/media/'.$rel,'path'=>$rel,'size'=>(int)@filesize($file)],'variants'=>[],'width'=>0,'height'=>0];
        }
    }
    if(in_array(($parts[0]??''),['branding','content','news'],true)){
        $file=rrw_media_dir().'/'.$rel;
        if(is_file($file)){
            $mime=function_exists('mime_content_type')?(string)@mime_content_type($file):'';
            return ['id'=>md5($rel),'name'=>basename($file),'mime'=>$mime,'original'=>['url'=>'/cms/media/'.$rel,'path'=>$rel,'size'=>(int)@filesize($file)],'variants'=>[],'width'=>0,'height'=>0];
        }
    }
    return null;
}
function rrw_media_library_items(array $site=[]): array {
    $base=rrw_media_dir();$out=[];
    if(!is_dir($base))return [];
    $library=$base.'/library';
    if(is_dir($library)){
        foreach(glob($library.'/*',GLOB_ONLYDIR)?:[] as $dir){
            $meta=$dir.'/meta.json';if(!is_file($meta))continue;$m=json_decode((string)file_get_contents($meta),true);if(!is_array($m))continue;
            $orig=$m['original']??[];$url=(string)($orig['url']??'');$path='library/'.basename($dir);
            $out[]=['id'=>(string)($m['id']??basename($dir)),'path'=>$path,'url'=>$url,'name'=>(string)($m['name']??basename($dir)),'bucket'=>'library','mime'=>(string)($m['mime']??''),'size'=>(int)($orig['size']??0),'mtime'=>(int)@filemtime($meta),'width'=>(int)($m['width']??0),'height'=>(int)($m['height']??0),'variants'=>(array)($m['variants']??[]),'original'=>$orig];
        }
        foreach(glob($library.'/*')?:[] as $file){
            if(!is_file($file))continue;$ext=strtolower(pathinfo($file,PATHINFO_EXTENSION));if(!in_array($ext,['png','jpg','jpeg','webp','gif','svg','ico'],true))continue;
            $rel='library/'.basename($file);$out[]=['id'=>md5($rel),'path'=>$rel,'url'=>'/cms/media/'.$rel,'name'=>basename($file),'bucket'=>'library','mime'=>function_exists('mime_content_type')?(string)@mime_content_type($file):'','size'=>(int)@filesize($file),'mtime'=>(int)@filemtime($file),'variants'=>[],'original'=>['url'=>'/cms/media/'.$rel,'path'=>$rel,'size'=>(int)@filesize($file)]];
        }
    }
    foreach(['branding','content','news'] as $bucket){
        $dir=$base.'/'.$bucket;if(!is_dir($dir))continue;
        foreach(glob($dir.'/*')?:[] as $file){if(!is_file($file))continue;$ext=strtolower(pathinfo($file,PATHINFO_EXTENSION));if(!in_array($ext,['png','jpg','jpeg','webp','gif','svg','ico'],true))continue;$rel=$bucket.'/'.basename($file);$out[]=['id'=>md5($rel),'path'=>$rel,'url'=>'/cms/media/'.$rel,'name'=>basename($file),'bucket'=>$bucket,'mime'=>function_exists('mime_content_type')?(string)@mime_content_type($file):'','size'=>(int)@filesize($file),'mtime'=>(int)@filemtime($file),'variants'=>[],'original'=>['url'=>'/cms/media/'.$rel,'path'=>$rel,'size'=>(int)@filesize($file)]];}
    }
    $usage=[];
    foreach((array)($site['branding_media']??[]) as $kind=>$ref){
        $path=(string)($ref['path']??'');if($path==='')continue;
        $usage[$path][]=$kind;
    }
    foreach($out as &$item){$item['used_as']=$usage[(string)($item['path']??'')]??[];}unset($item);
    usort($out,fn($a,$b)=>($b['mtime']??0)<=>($a['mtime']??0));return $out;
}
function rrw_safe_media_rel(string $rel): string {
    $rel=str_replace('\\','/',trim($rel));$rel=ltrim($rel,'/');
    if($rel===''||str_contains($rel,'..')||!preg_match('#^[a-zA-Z0-9_./-]+$#',$rel))return '';
    return $rel;
}

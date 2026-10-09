<?php
// Ergänzende Medien-Funktionen für Bilder in der Verwaltung (wp-admin/includes/image.php): Zuschneiden, Zwischengrößen erzeugen,
// EXIF/IPTC lesen, Bild laden. Die Bildverarbeitung nutzt GD (ohne GD liefern die Funktionen WP_Error bzw. false).
// Hilfsfunktionen beginnen mit _elvado_m_.

if(!function_exists('_elvado_m_dims')){ /** Zielmaße und Ausschnitt wie image_resize_dimensions (mit echtem Zuschnitt); false, wenn nichts zu tun ist. */
function _elvado_m_dims($ow,$oh,$dw,$dh,$crop=false) {
    $ow=(int)$ow;$oh=(int)$oh;$dw=(int)$dw;$dh=(int)$dh;if($ow<=0||$oh<=0||($dw<=0&&$dh<=0))return false;
    if($crop){
        $ar=$ow/$oh;$nw=min($dw?:$ow,$ow);$nh=min($dh?:$oh,$oh);
        if(!$dw)$nw=(int)round($nh*$ar);if(!$dh)$nh=(int)round($nw/$ar);
        $ratio=max($nw/$ow,$nh/$oh);$cw=(int)round($nw/$ratio);$ch=(int)round($nh/$ratio);
        if(!is_array($crop)||count($crop)!==2)$crop=['center','center'];[$x,$y]=$crop;
        $sx=$x==='left'?0:($x==='right'?$ow-$cw:(int)floor(($ow-$cw)/2));$sy=$y==='top'?0:($y==='bottom'?$oh-$ch:(int)floor(($oh-$ch)/2));
    } else { $cw=$ow;$ch=$oh;$sx=$sy=0;[$nw,$nh]=wp_constrain_dimensions($ow,$oh,$dw,$dh); }
    if($nw>=$ow&&$nh>=$oh&&$dw!==$ow&&$dh!==$oh)return false;   // nur verkleinern
    return [0,0,(int)$sx,(int)$sy,(int)$nw,(int)$nh,(int)$cw,(int)$ch];
} }
if(!function_exists('_elvado_m_gd_load')){ function _elvado_m_gd_load($path,$mime=null) {
    if(!function_exists('imagecreatetruecolor')||!is_file((string)$path))return false;
    $mime=$mime??(wp_getimagesize($path)['mime']??'');$f=['image/jpeg'=>'imagecreatefromjpeg','image/png'=>'imagecreatefrompng','image/gif'=>'imagecreatefromgif','image/webp'=>'imagecreatefromwebp','image/avif'=>'imagecreatefromavif','image/bmp'=>'imagecreatefrombmp'][$mime]??null;
    if(!$f||!function_exists($f))return false;$i=@$f($path);
    if(is_gd_image($i)&&in_array($mime,['image/png','image/webp'],true)){ imagealphablending($i,false);imagesavealpha($i,true); }
    return $i;
} }
if(!function_exists('_elvado_m_gd_save')){ function _elvado_m_gd_save($img,$path,$mime) {
    return match($mime){ 'image/jpeg'=>@imagejpeg($img,$path,(int)apply_filters('jpeg_quality',82)), 'image/png'=>@imagepng($img,$path), 'image/gif'=>@imagegif($img,$path),
        'image/webp'=>function_exists('imagewebp')&&@imagewebp($img,$path,82), 'image/avif'=>function_exists('imageavif')&&@imageavif($img,$path), default=>false };
} }
if(!function_exists('_elvado_m_resize')){ /** Skaliert/schneidet eine Datei nach „Name-BxH.ext“; liefert die Metadaten der Zwischengröße oder WP_Error. */
function _elvado_m_resize($file,$w,$h,$crop=false,$suffix=null,$dir=null) {
    $info=wp_getimagesize($file);if(!$info)return new WP_Error('invalid_image','Die Datei ist kein Bild.',$file);
    $d=_elvado_m_dims($info[0],$info[1],$w,$h,$crop);if(!$d)return new WP_Error('error_getting_dimensions','Die Bildmaße konnten nicht berechnet werden.');
    $src=_elvado_m_gd_load($file,$info['mime']);if(!is_gd_image($src))return new WP_Error('image_no_editor','Die Bildbearbeitung (GD) ist nicht verfügbar.');
    $dst=wp_imagecreatetruecolor($d[4],$d[5]);imagecopyresampled($dst,$src,$d[0],$d[1],$d[2],$d[3],$d[4],$d[5],$d[6],$d[7]);
    $pi=pathinfo($file);$dir=$dir??$pi['dirname'];$suffix=$suffix??$d[4].'x'.$d[5];$name=$pi['filename'].'-'.$suffix.'.'.($pi['extension']??'jpg');$path=rtrim($dir,'/').'/'.$name;
    if(!_elvado_m_gd_save($dst,$path,$info['mime']))return new WP_Error('image_save_error','Das Bild konnte nicht gespeichert werden.',$path);
    return ['path'=>$path,'file'=>$name,'width'=>$d[4],'height'=>$d[5],'mime-type'=>$info['mime'],'filesize'=>(int)@filesize($path)];
} }
if(!function_exists('_elvado_m_subsizes')){ function _elvado_m_subsizes() {
    $s=wp_get_registered_image_subsizes();foreach(wp_get_additional_image_sizes() as $n=>$a)$s[$n]=['width'=>(int)$a['width'],'height'=>(int)$a['height'],'crop'=>$a['crop']];return $s;
} }
if(!function_exists('_elvado_m_upload_rel')){ function _elvado_m_upload_rel($file) {
    if(function_exists('_wp_relative_upload_path'))return _wp_relative_upload_path($file);
    $b=wp_get_upload_dir()['basedir'];$f=wp_normalize_path((string)$file);$b=wp_normalize_path($b);return str_starts_with($f,$b)?ltrim(substr($f,strlen($b)),'/'):$f;
} }

if(!function_exists('wp_exif_frac2dec')){ function wp_exif_frac2dec($str) {
    if(!is_scalar($str)||is_bool($str))return 0;if(!is_string($str))return $str;
    if(!str_contains($str,'/'))return (float)$str;
    [$a,$b]=explode('/',$str,2);return (float)$b==0.0?0.0:(float)$a/(float)$b;
} }
if(!function_exists('wp_exif_date2ts')){ function wp_exif_date2ts($str) {
    $p=explode(' ',trim((string)$str),2);if(count($p)<2)return false;$d=explode(':',$p[0]);if(count($d)!==3)return false;
    try{ return (new DateTime(sprintf('%s-%s-%s %s',$d[0],$d[1],$d[2],$p[1]),new DateTimeZone('UTC')))->getTimestamp(); }catch(Throwable $e){ return false; }
} }
if(!function_exists('wp_read_image_metadata')){ function wp_read_image_metadata($file) {
    if(!file_exists($file))return false;
    $info=[];$size=wp_getimagesize($file,$info);$type=$size[2]??0;
    $m=['aperture'=>'0','credit'=>'','camera'=>'','caption'=>'','created_timestamp'=>'0','copyright'=>'','focal_length'=>'0','iso'=>'0','shutter_speed'=>'0','title'=>'','orientation'=>'0','keywords'=>[]];
    $iptc=[];
    if(is_callable('iptcparse')&&!empty($info['APP13'])){
        $iptc=@iptcparse($info['APP13'])?:[];
        if(!empty($iptc['2#105'][0]))$m['title']=trim($iptc['2#105'][0]);elseif(!empty($iptc['2#005'][0]))$m['title']=trim($iptc['2#005'][0]);
        if(!empty($iptc['2#120'][0])){ $c=trim($iptc['2#120'][0]);if(empty($m['title'])&&strlen($c)<80)$m['title']=$c;$m['caption']=$c; }
        if(!empty($iptc['2#110'][0]))$m['credit']=trim($iptc['2#110'][0]);elseif(!empty($iptc['2#080'][0]))$m['credit']=trim($iptc['2#080'][0]);
        if(!empty($iptc['2#055'][0])&&!empty($iptc['2#060'][0]))$m['created_timestamp']=(string)strtotime($iptc['2#055'][0].' '.$iptc['2#060'][0]);
        if(!empty($iptc['2#116'][0]))$m['copyright']=trim($iptc['2#116'][0]);
        if(!empty($iptc['2#025'][0]))$m['keywords']=array_values($iptc['2#025']);
    }
    $exif=[];
    if(is_callable('exif_read_data')&&in_array($type,apply_filters('wp_read_image_metadata_types',[IMAGETYPE_JPEG,IMAGETYPE_TIFF_II,IMAGETYPE_TIFF_MM]),true)){
        $exif=@exif_read_data($file)?:[];
        if(!empty($exif['ImageDescription'])){ $d=trim((string)$exif['ImageDescription']);if(empty($m['title'])&&strlen($d)<80)$m['title']=$d;if(empty($m['caption'])&&$d!==$m['title'])$m['caption']=$d; }
        if(!empty($exif['Artist']))$m['credit']=trim((string)$exif['Artist']);elseif(!empty($exif['Author']))$m['credit']=trim((string)$exif['Author']);
        if(!empty($exif['Copyright']))$m['copyright']=trim((string)$exif['Copyright']);
        if(!empty($exif['FNumber'])&&is_scalar($exif['FNumber']))$m['aperture']=(string)round(wp_exif_frac2dec($exif['FNumber']),2);
        if(!empty($exif['Model']))$m['camera']=trim((string)$exif['Model']);
        if(empty($m['created_timestamp'])||$m['created_timestamp']==='0'){ if(!empty($exif['DateTimeDigitized']))$m['created_timestamp']=(string)wp_exif_date2ts($exif['DateTimeDigitized']); }
        if(!empty($exif['FocalLength']))$m['focal_length']=is_scalar($exif['FocalLength'])?(string)wp_exif_frac2dec($exif['FocalLength']):'0';
        if(!empty($exif['ISOSpeedRatings'])){ $iso=$exif['ISOSpeedRatings'];$m['iso']=(string)(is_array($iso)?reset($iso):$iso); }
        if(!empty($exif['ExposureTime']))$m['shutter_speed']=(string)wp_exif_frac2dec($exif['ExposureTime']);
        if(!empty($exif['Orientation']))$m['orientation']=(string)$exif['Orientation'];
    }
    foreach(['title','caption','credit','copyright','camera','iso'] as $k)if($m[$k]!==''&&!mb_check_encoding($m[$k],'UTF-8'))$m[$k]=mb_convert_encoding($m[$k],'UTF-8','ISO-8859-1');
    foreach($m['keywords'] as $i=>$kw)if(!mb_check_encoding($kw,'UTF-8'))$m['keywords'][$i]=mb_convert_encoding($kw,'UTF-8','ISO-8859-1');
    return apply_filters('wp_read_image_metadata',$m,$file,$type,$iptc,$exif);
} }
if(!function_exists('file_is_valid_image')){ function file_is_valid_image($path) { $s=wp_getimagesize($path);return !empty($s); } }
if(!function_exists('file_is_displayable_image')){ function file_is_displayable_image($path) {
    $ok=[IMAGETYPE_GIF,IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_BMP,IMAGETYPE_ICO,IMAGETYPE_WEBP];if(defined('IMAGETYPE_AVIF'))$ok[]=IMAGETYPE_AVIF;
    $i=wp_getimagesize($path);$r=!empty($i)&&in_array($i[2],$ok,true);return apply_filters('file_is_displayable_image',$r,$path);
} }
if(!function_exists('_load_image_to_edit_path')){ function _load_image_to_edit_path($attachment_id,$size='full') {
    $p=get_attached_file($attachment_id);
    if($p&&file_exists($p)){
        if($size!=='full'){ $d=_elvado_m_intermediate($attachment_id,$size);if($d){ $p=apply_filters('load_image_to_edit_filesystempath',path_join(dirname($p),$d['file']),$attachment_id,$size); } }
    } elseif(function_exists('fopen')&&ini_get('allow_url_fopen'))$p=apply_filters('load_image_to_edit_attachmenturl',wp_get_attachment_url($attachment_id),$attachment_id,$size);
    return apply_filters('load_image_to_edit_path',$p,$attachment_id,$size);
} }
if(!function_exists('load_image_to_edit')){ function load_image_to_edit($attachment_id,$mime_type,$size='full') {
    $p=_load_image_to_edit_path($attachment_id,$size);if(empty($p))return false;
    $i=_elvado_m_gd_load($p,$mime_type);
    if(is_gd_image($i)){ $i=apply_filters('load_image_to_edit',$i,$attachment_id,$size);if(function_exists('imagealphablending')&&function_exists('imagesavealpha')){ imagealphablending($i,false);imagesavealpha($i,true); } }
    return $i;
} }
if(!function_exists('_copy_image_file')){ function _copy_image_file($attachment_id) {
    $dst=$src=get_attached_file($attachment_id);if(!$src||!file_exists($src))$src=_load_image_to_edit_path($attachment_id);
    if(!$src)return false;
    $dst=dirname($dst).'/'.wp_unique_filename(dirname($dst),'copy-'.wp_basename($dst));wp_mkdir_p(dirname($dst));
    return @copy($src,$dst)?$dst:false;
} }
if(!function_exists('wp_copy_parent_attachment_properties')){ function wp_copy_parent_attachment_properties($cropped,$parent_attachment_id,$context='') {
    $parent_url=(string)wp_get_attachment_url($parent_attachment_id);$url=str_replace(wp_basename($parent_url),wp_basename($cropped),$parent_url);
    $s=wp_getimagesize($cropped);
    return ['post_title'=>wp_basename($cropped),'post_content'=>$url,'post_mime_type'=>$s?$s['mime']:'image/jpeg','guid'=>$url,'context'=>$context];
} }
if(!function_exists('wp_crop_image')){ function wp_crop_image($src,$src_x,$src_y,$src_w,$src_h,$dst_w,$dst_h,$src_abs=false,$dst_file=false) {
    $file=$src;
    if(is_numeric($src)){ $file=get_attached_file($src);if(!$file||!file_exists($file))$file=_load_image_to_edit_path($src); }
    $info=$file?wp_getimagesize($file):false;$img=$info?_elvado_m_gd_load($file,$info['mime']):false;
    if(!is_gd_image($img))return new WP_Error('invalid_image','Die Datei ist kein Bild.',$file);
    if($src_abs){ $dst_w=$src_w;$dst_h=$src_h; }
    $dst=wp_imagecreatetruecolor((int)$dst_w,(int)$dst_h);imagecopyresampled($dst,$img,0,0,(int)$src_x,(int)$src_y,(int)$dst_w,(int)$dst_h,(int)$src_w,(int)$src_h);
    if(!$dst_file)$dst_file=dirname($file).'/cropped-'.wp_basename($file);
    wp_mkdir_p(dirname($dst_file));$dst_file=dirname($dst_file).'/'.wp_unique_filename(dirname($dst_file),wp_basename($dst_file));
    return _elvado_m_gd_save($dst,$dst_file,$info['mime'])?$dst_file:new WP_Error('copy_failed','Das zugeschnittene Bild konnte nicht gespeichert werden.',$dst_file);
} }

/* ───────── Zwischengrößen ───────── */
if(!function_exists('wp_get_missing_image_subsizes')){ function wp_get_missing_image_subsizes($attachment_id) {
    if(!wp_attachment_is_image($attachment_id))return [];
    $reg=_elvado_m_subsizes();$meta=wp_get_attachment_metadata($attachment_id);
    if(empty($meta)||!is_array($meta))return $reg;
    $missing=[];$fw=(int)($meta['width']??0);$fh=(int)($meta['height']??0);
    foreach($reg as $n=>$d){ if(!empty($meta['sizes'][$n]))continue;
        if(_elvado_m_dims($fw,$fh,$d['width'],$d['height'],$d['crop']))$missing[$n]=$d; }
    return apply_filters('wp_get_missing_image_subsizes',$missing,$meta,$attachment_id);
} }
if(!function_exists('_wp_image_meta_replace_original')){ function _wp_image_meta_replace_original($saved,$original_file,$image_meta,$attachment_id) {
    $new=$saved['path'];
    if(function_exists('update_attached_file'))update_attached_file($attachment_id,$new);else update_post_meta($attachment_id,'_wp_attached_file',_elvado_m_upload_rel($new));
    $image_meta['width']=$saved['width'];$image_meta['height']=$saved['height'];$image_meta['file']=_elvado_m_upload_rel($new);
    if(!empty($saved['filesize']))$image_meta['filesize']=$saved['filesize'];else unset($image_meta['filesize']);
    $image_meta['original_image']=wp_basename($original_file);return $image_meta;
} }
if(!function_exists('_wp_make_subsizes')){ function _wp_make_subsizes($new_sizes,$file,$image_meta,$attachment_id) {
    if(empty($image_meta)||!is_array($image_meta))return new WP_Error('invalid_image','Die Bilddaten fehlen. Bitte laden Sie das Bild erneut hoch.');
    $info=wp_getimagesize($file);if(!$info)return new WP_Error('invalid_image','Die Datei ist kein Bild.',$file);
    if(empty($image_meta['sizes']))$image_meta['sizes']=[];
    $new_sizes=apply_filters('intermediate_image_sizes_advanced',$new_sizes,$image_meta,$attachment_id);
    foreach((array)$new_sizes as $name=>$d){
        $r=_elvado_m_resize($file,(int)($d['width']??0),(int)($d['height']??0),$d['crop']??false);
        if(is_array($r)){ unset($r['path']);$image_meta['sizes'][$name]=$r; }
    }
    if($attachment_id)wp_update_attachment_metadata($attachment_id,$image_meta);
    return $image_meta;
} }
if(!function_exists('wp_create_image_subsizes')){ function wp_create_image_subsizes($file,$attachment_id) {
    $info=wp_getimagesize($file);if(empty($info))return [];
    $meta=['width'=>$info[0],'height'=>$info[1],'file'=>_elvado_m_upload_rel($file),'filesize'=>wp_filesize($file),'sizes'=>[]];
    $exif=wp_read_image_metadata($file);if($exif)$meta['image_meta']=$exif;
    if($info['mime']!=='image/png'){   // große PNGs nicht verkleinern
        $th=(int)apply_filters('big_image_size_threshold',2560,$info,$file,$attachment_id);
        if($th&&($meta['width']>$th||$meta['height']>$th)){
            $r=_elvado_m_resize($file,$th,$th,false,'scaled');
            if(is_array($r)){ $meta=_wp_image_meta_replace_original($r,$file,$meta,$attachment_id);$file=$r['path']; }
        }
    }
    $new=apply_filters('intermediate_image_sizes_advanced',_elvado_m_subsizes(),$meta,$attachment_id);
    return _wp_make_subsizes($new,$file,$meta,$attachment_id);
} }
if(!function_exists('wp_update_image_subsizes')){ function wp_update_image_subsizes($attachment_id) {
    $meta=wp_get_attachment_metadata($attachment_id);$file=get_attached_file($attachment_id);
    if(empty($meta)||!is_array($meta)){ return $file?wp_create_image_subsizes($file,$attachment_id):$meta; }
    $missing=wp_get_missing_image_subsizes($attachment_id);if(empty($missing)||!$file)return $meta;
    return _wp_make_subsizes($missing,$file,$meta,$attachment_id);
} }

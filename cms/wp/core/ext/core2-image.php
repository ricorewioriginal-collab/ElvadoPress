<?php
// Ergänzende WordPress-Funktionen (Kern 2, Teil 15): Bild-Editoren (WP_Image_Editor, GD- und Imagick-Fassung).
// Eigenständig umgesetzt; GD ist vollständig, Imagick nutzt die Imagick-Klasse nur, wenn die Erweiterung vorhanden ist. Bilder werden erst bei load()/save() verarbeitet.

if(!class_exists('WP_Image_Editor')){
abstract class WP_Image_Editor {
    protected $file=null;protected $size=null;protected $mime_type=null;protected $output_mime_type=null;protected $default_mime_type='image/jpeg';protected $quality=false;protected $default_quality=82;
    protected function __construct($file) { $this->file=$file; }
    public static function test($args=[]) { return false; }
    public static function supports_mime_type($mime_type) { return false; }
    abstract public function load();
    abstract public function save($destfilename=null, $mime_type=null);
    abstract public function resize($max_w, $max_h, $crop=false);
    abstract public function multi_resize($sizes);
    abstract public function crop($src_x, $src_y, $src_w, $src_h, $dst_w=null, $dst_h=null, $src_abs=false);
    abstract public function rotate($angle);
    abstract public function flip($horz, $vert);
    abstract public function stream($mime_type=null);
    /** Passenden Editor für die Datei wählen und laden; WP_Error, wenn keiner geeignet ist. Die Schicht wählt Editoren sonst nicht selbsttätig (wp_get_image_editor meldet „kein Editor“): hier ist die Wahl ausdrücklich gewünscht. */
    protected static function enabled($args) { return !empty($args['elvado_direct'])||(bool)apply_filters('elvado_wp_image_editor_enabled',defined('ELVADO_WP_IMAGE_EDITOR')&&ELVADO_WP_IMAGE_EDITOR); }
    public static function get_instance($path, $args=[]) {
        $c=_wp_image_editor_choose(array_merge((array)$args,['path'=>$path,'elvado_direct'=>true]));
        if(!$c)return new WP_Error('image_no_editor','Kein Bildeditor ist verfügbar.');
        $e=new $c($path);$l=$e->load();
        return is_wp_error($l)?$l:$e;
    }
    /** Zielmaße wie in WordPress: [dst_x,dst_y,src_x,src_y,dst_w,dst_h,src_w,src_h] oder false (nie vergrößern). */
    protected static function dimensions($ow, $oh, $dw, $dh, $crop=false) {
        $ow=(int)$ow;$oh=(int)$oh;$dw=(int)$dw;$dh=(int)$dh;
        if($ow<=0||$oh<=0||($dw<=0&&$dh<=0))return false;
        if($crop){
            $ar=$ow/$oh;$nw=min($dw?:PHP_INT_MAX,$ow);$nh=min($dh?:PHP_INT_MAX,$oh);
            if(!$dw)$nw=(int)($nh*$ar);if(!$dh)$nh=(int)($nw/$ar);
            $ratio=max($nw/$ow,$nh/$oh);$cw=(int)round($nw/$ratio);$ch=(int)round($nh/$ratio);
            $sx=(int)floor(($ow-$cw)/2);$sy=(int)floor(($oh-$ch)/2);
        }else{
            $cw=$ow;$ch=$oh;$sx=$sy=0;
            $r=min($dw?$dw/$ow:INF,$dh?$dh/$oh:INF);$nw=max(1,(int)round($ow*$r));$nh=max(1,(int)round($oh*$r));
        }
        if($nw>=$ow&&$nh>=$oh)return false;
        return [0,0,$sx,$sy,$nw,$nh,$cw,$ch];
    }
    public function get_size() { return $this->size; }
    protected function update_size($width=null, $height=null) { $this->size=['width'=>(int)$width,'height'=>(int)$height]; }
    public function get_suffix() { return $this->get_size()?$this->size['width'].'x'.$this->size['height']:false; }
    public function set_quality($quality=null) {
        if($quality===null){
            $quality=(int)apply_filters('wp_editor_set_quality',$this->default_quality,$this->mime_type);
            if('image/jpeg'===$this->mime_type)$quality=(int)apply_filters('jpeg_quality',$quality,'image_resize');
            if($quality<0||$quality>100)$quality=$this->default_quality;
            if($quality===0)$quality=1;
        }
        if($quality<1||$quality>100)return new WP_Error('invalid_image_quality','Die Bildqualität muss zwischen 1 und 100 liegen.');
        $this->quality=(int)$quality;return true;
    }
    public function get_quality() { if(!$this->quality)$this->set_quality();return $this->quality; }
    public function generate_filename($suffix=null, $dest_path=null, $extension=null) {
        if(!$suffix)$suffix=$this->get_suffix();
        $dir=pathinfo((string)$this->file,PATHINFO_DIRNAME);$ext=pathinfo((string)$this->file,PATHINFO_EXTENSION);$name=wp_basename((string)$this->file,".$ext");$new_ext=strtolower($extension?:$ext);
        if($dest_path!==null){ $p=realpath($dest_path);if($p)$dir=$p; }
        return trailingslashit($dir)."{$name}-{$suffix}.{$new_ext}";
    }
    protected function get_output_format($filename=null, $mime_type=null) {
        $new=$filename;$ext='';
        if($mime_type)$ext=$this->get_extension($mime_type);
        elseif($filename){ $ext=strtolower(pathinfo($filename,PATHINFO_EXTENSION));$mime_type=$this->get_mime_type($ext)?:null; }
        if(!$mime_type||!$ext){ $mime_type=$this->output_mime_type?:($this->mime_type?:$this->default_mime_type);$ext=$this->get_extension($mime_type); }
        if(!static::supports_mime_type($mime_type)){ $mime_type=$this->default_mime_type;$ext=$this->get_extension($mime_type);$new=null; }
        if($filename&&!$new)$new=null;
        return [$new,$ext,$mime_type];
    }
    protected static function get_mime_type($extension=null) {
        if(!$extension)return false;
        foreach(wp_get_mime_types() as $exts=>$mime)if(preg_match('!^('.$exts.')$!i',$extension))return $mime;
        return false;
    }
    protected static function get_extension($mime_type=null) {
        if(empty($mime_type))return false;
        $e=explode('|',(string)array_search($mime_type,wp_get_mime_types(),true));
        return $e[0]!==''?$e[0]:false;
    }
    protected function make_image($filename, $callback, $arguments) {
        if(!is_callable($callback))return false;
        if(wp_is_stream($filename))$arguments[1]=null;
        $ok=call_user_func_array($callback,$arguments);
        return $ok;
    }
}
}
if(!class_exists('WP_Image_Editor_GD')){
class WP_Image_Editor_GD extends WP_Image_Editor {
    protected $image=null;
    public function __destruct() { if($this->image&&is_gd_image($this->image))imagedestroy($this->image); }
    public static function test($args=[]) {
        if(!extension_loaded('gd')||!function_exists('gd_info')||!static::enabled($args))return false;
        if(isset($args['methods'])&&in_array('rotate',(array)$args['methods'],true)&&!function_exists('imagerotate'))return false;
        return true;
    }
    public static function supports_mime_type($mime_type) {
        $t=imagetypes();
        switch($mime_type){ case 'image/jpeg': return ($t&IMG_JPG)!=0; case 'image/png': return ($t&IMG_PNG)!=0; case 'image/gif': return ($t&IMG_GIF)!=0;
            case 'image/webp': return defined('IMG_WEBP')&&($t&IMG_WEBP)!=0; case 'image/avif': return function_exists('imageavif'); }
        return false;
    }
    public function load() {
        if($this->image)return true;
        if(!is_file((string)$this->file)&&!preg_match('~^https?://~',(string)$this->file))return new WP_Error('error_loading_image','Die Datei ist keine Bilddatei oder nicht lesbar.',$this->file);
        $info=@getimagesize((string)$this->file);if(!$info)return new WP_Error('invalid_image','Das Bild konnte nicht gelesen werden.',$this->file);
        $data=@file_get_contents((string)$this->file);$this->image=$data===false?false:@imagecreatefromstring($data);
        if(!is_gd_image($this->image))return new WP_Error('invalid_image','Das Bild konnte nicht gelesen werden.',$this->file);
        if(function_exists('imagealphablending')&&function_exists('imagesavealpha')){ imagealphablending($this->image,false);imagesavealpha($this->image,true); }
        $this->update_size($info[0],$info[1]);$this->mime_type=$info['mime'];
        return $this->set_quality();
    }
    protected function make_truecolor($w, $h) { $i=imagecreatetruecolor(max(1,(int)$w),max(1,(int)$h));if(is_gd_image($i)){ imagealphablending($i,false);imagesavealpha($i,true); }return $i; }
    protected function _resize($max_w, $max_h, $crop=false) {
        $d=static::dimensions($this->size['width'],$this->size['height'],$max_w,$max_h,$crop);
        if(!$d)return new WP_Error('error_getting_dimensions','Die Bildgröße konnte nicht berechnet werden.',$this->file);
        [$dst_x,$dst_y,$src_x,$src_y,$dst_w,$dst_h,$src_w,$src_h]=$d;
        $r=$this->make_truecolor($dst_w,$dst_h);
        imagecopyresampled($r,$this->image,$dst_x,$dst_y,$src_x,$src_y,$dst_w,$dst_h,$src_w,$src_h);
        if(!is_gd_image($r))return new WP_Error('resize_failed','Die Größe konnte nicht geändert werden.',$this->file);
        $this->update_size($dst_w,$dst_h);return $r;
    }
    public function resize($max_w, $max_h, $crop=false) {
        if($this->size['width']==$max_w&&$this->size['height']==$max_h)return true;
        $r=$this->_resize($max_w,$max_h,$crop);
        if(is_gd_image($r)){ imagedestroy($this->image);$this->image=$r;return true; }
        return is_wp_error($r)?$r:new WP_Error('image_resize_error','Die Bildgröße konnte nicht geändert werden.',$this->file);
    }
    public function multi_resize($sizes) {
        $meta=[];$orig_size=$this->size;
        foreach($sizes as $name=>$d){
            $d+=['width'=>0,'height'=>0,'crop'=>false];if(!$d['width']&&!$d['height'])continue;
            $orig=$this->image;$r=$this->make_subsize($d);
            if(!is_wp_error($r)&&$r){ unset($r['path']);$meta[$name]=$r; }
            $this->image=$orig;$this->size=$orig_size;
        }
        return $meta;
    }
    public function make_subsize($size_data) {
        if(!isset($size_data['width'],$size_data['height']))return new WP_Error('image_subsize_create_error','Die Größenangaben fehlen.');
        $w=(int)$size_data['width'];$h=(int)$size_data['height'];$crop=!empty($size_data['crop']);
        $r=$this->_resize($w,$h,$crop);if(is_wp_error($r))return $r;
        $saved=$this->_save($r);imagedestroy($r);
        return $saved;
    }
    public function crop($src_x, $src_y, $src_w, $src_h, $dst_w=null, $dst_h=null, $src_abs=false) {
        $dst_w=$dst_w?:$src_w;$dst_h=$dst_h?:$src_h;
        if($src_abs){ $src_w-=$src_x;$src_h-=$src_y; }
        $d=$this->make_truecolor($dst_w,$dst_h);
        imagecopyresampled($d,$this->image,0,0,(int)$src_x,(int)$src_y,(int)$dst_w,(int)$dst_h,(int)$src_w,(int)$src_h);
        if(!is_gd_image($d))return new WP_Error('image_crop_error','Das Bild konnte nicht zugeschnitten werden.',$this->file);
        imagedestroy($this->image);$this->image=$d;$this->update_size(imagesx($d),imagesy($d));return true;
    }
    public function rotate($angle) {
        if(!function_exists('imagerotate'))return new WP_Error('image_rotate_error','Das Bild konnte nicht gedreht werden.',$this->file);
        $r=imagerotate($this->image,-$angle,imagecolorallocatealpha($this->image,0,0,0,127));
        if(!is_gd_image($r))return new WP_Error('image_rotate_error','Das Bild konnte nicht gedreht werden.',$this->file);
        imagealphablending($r,false);imagesavealpha($r,true);imagedestroy($this->image);$this->image=$r;$this->update_size(imagesx($r),imagesy($r));return true;
    }
    public function flip($horz, $vert) {
        if(function_exists('imageflip')){ imageflip($this->image,$horz&&$vert?IMG_FLIP_BOTH:($horz?IMG_FLIP_HORIZONTAL:IMG_FLIP_VERTICAL));return true; }
        return new WP_Error('image_flip_error','Das Bild konnte nicht gespiegelt werden.',$this->file);
    }
    public function save($destfilename=null, $mime_type=null) {
        $s=$this->_save($this->image,$destfilename,$mime_type);if(is_wp_error($s))return $s;
        $this->file=$s['path'];$this->mime_type=$s['mime-type'];return $s;
    }
    protected function _save($image, $filename=null, $mime_type=null) {
        [$filename,$extension,$mime_type]=$this->get_output_format($filename,$mime_type);
        if(!$filename)$filename=$this->generate_filename(null,null,$extension);
        if(!wp_mkdir_p(dirname($filename)))return new WP_Error('image_save_error','Der Zielordner konnte nicht angelegt werden.',$filename);
        $q=$this->get_quality();$ok=false;
        switch($mime_type){
            case 'image/gif': $ok=imagegif($image,$filename);break;
            case 'image/png': imagealphablending($image,false);imagesavealpha($image,true);$ok=imagepng($image,$filename,min(9,max(0,(int)round(9*(100-$q)/100))));break;
            case 'image/webp': $ok=function_exists('imagewebp')&&imagewebp($image,$filename,$q);break;
            case 'image/avif': $ok=function_exists('imageavif')&&imageavif($image,$filename,$q);break;
            default: $mime_type='image/jpeg';imageinterlace($image,true);$ok=imagejpeg($image,$filename,$q);
        }
        if(!$ok)return new WP_Error('image_save_error','Das Bild konnte nicht gespeichert werden.',$filename);
        @chmod($filename,0644);
        return ['path'=>$filename,'file'=>wp_basename(apply_filters('image_make_intermediate_size',$filename)),'width'=>$this->size['width'],'height'=>$this->size['height'],'mime-type'=>$mime_type,'filesize'=>(int)@filesize($filename)];
    }
    public function stream($mime_type=null) {
        [,,$mime_type]=$this->get_output_format(null,$mime_type);
        switch($mime_type){
            case 'image/png': header('Content-Type: image/png');return imagepng($this->image);
            case 'image/gif': header('Content-Type: image/gif');return imagegif($this->image);
            case 'image/webp': if(function_exists('imagewebp')){ header('Content-Type: image/webp');return imagewebp($this->image,null,$this->get_quality()); }
            default: header('Content-Type: image/jpeg');imageinterlace($this->image,true);return imagejpeg($this->image,null,$this->get_quality());
        }
    }
}
}
if(!class_exists('WP_Image_Editor_Imagick')){
class WP_Image_Editor_Imagick extends WP_Image_Editor {
    protected $image=null;
    public function __destruct() { if($this->image instanceof Imagick)$this->image->clear(); }
    public static function test($args=[]) { return extension_loaded('imagick')&&class_exists('Imagick',false)&&class_exists('ImagickPixel',false)&&static::enabled($args); }
    public static function supports_mime_type($mime_type) {
        if(!class_exists('Imagick',false))return false;
        $f=strtoupper(str_replace('image/','',(string)static::get_extension($mime_type)));
        try{ return (bool)@Imagick::queryFormats($f); }catch(Throwable $e){ return false; }
    }
    public function load() {
        if($this->image instanceof Imagick)return true;
        if(!is_file((string)$this->file))return new WP_Error('error_loading_image','Die Datei ist keine Bilddatei oder nicht lesbar.',$this->file);
        try{ $this->image=new Imagick((string)$this->file);$this->image->setIteratorIndex(0);$this->mime_type=$this->get_mime_type($this->image->getImageFormat())?:'image/jpeg';
            $this->update_size($this->image->getImageWidth(),$this->image->getImageHeight()); }
        catch(Throwable $e){ return new WP_Error('invalid_image',$e->getMessage(),$this->file); }
        return $this->set_quality();
    }
    public function resize($max_w, $max_h, $crop=false) {
        $d=static::dimensions($this->size['width'],$this->size['height'],$max_w,$max_h,$crop);
        if(!$d)return new WP_Error('error_getting_dimensions','Die Bildgröße konnte nicht berechnet werden.',$this->file);
        [,,$sx,$sy,$dw,$dh,$sw,$sh]=$d;
        try{ if($crop)$this->image->cropImage($sw,$sh,$sx,$sy);$this->image->scaleImage($dw,$dh);$this->update_size($dw,$dh); }catch(Throwable $e){ return new WP_Error('image_resize_error',$e->getMessage(),$this->file); }
        return true;
    }
    public function multi_resize($sizes) {
        $meta=[];$orig=clone $this->image;$os=$this->size;
        foreach($sizes as $name=>$d){
            $d+=['width'=>0,'height'=>0,'crop'=>false];if(!$d['width']&&!$d['height'])continue;
            $this->image=clone $orig;$this->size=$os;
            if(true!==$this->resize($d['width'],$d['height'],$d['crop']))continue;
            $s=$this->save();if(!is_wp_error($s)){ unset($s['path']);$meta[$name]=$s; }
            $this->file=$this->file;
        }
        $this->image=$orig;$this->size=$os;return $meta;
    }
    public function crop($src_x, $src_y, $src_w, $src_h, $dst_w=null, $dst_h=null, $src_abs=false) {
        if($src_abs){ $src_w-=$src_x;$src_h-=$src_y; }
        try{ $this->image->cropImage((int)$src_w,(int)$src_h,(int)$src_x,(int)$src_y);$this->image->setImagePage($src_w,$src_h,0,0);
            if($dst_w||$dst_h)$this->image->scaleImage((int)($dst_w?:$src_w),(int)($dst_h?:$src_h));$this->update_size($this->image->getImageWidth(),$this->image->getImageHeight()); }
        catch(Throwable $e){ return new WP_Error('image_crop_error',$e->getMessage(),$this->file); }
        return true;
    }
    public function rotate($angle) { try{ $this->image->rotateImage(new ImagickPixel('none'),$angle);$this->update_size($this->image->getImageWidth(),$this->image->getImageHeight()); }catch(Throwable $e){ return new WP_Error('image_rotate_error',$e->getMessage(),$this->file); }return true; }
    public function flip($horz, $vert) { try{ if($horz)$this->image->flopImage();if($vert)$this->image->flipImage(); }catch(Throwable $e){ return new WP_Error('image_flip_error',$e->getMessage(),$this->file); }return true; }
    public function save($destfilename=null, $mime_type=null) {
        [$filename,$extension,$mime_type]=$this->get_output_format($destfilename,$mime_type);
        if(!$filename)$filename=$this->generate_filename(null,null,$extension);
        if(!wp_mkdir_p(dirname($filename)))return new WP_Error('image_save_error','Der Zielordner konnte nicht angelegt werden.',$filename);
        try{ $this->image->setImageFormat(strtoupper((string)$extension));$this->image->setImageCompressionQuality($this->get_quality());$this->image->writeImage($filename); }
        catch(Throwable $e){ return new WP_Error('image_save_error',$e->getMessage(),$filename); }
        @chmod($filename,0644);$this->file=$filename;$this->mime_type=$mime_type;
        return ['path'=>$filename,'file'=>wp_basename($filename),'width'=>$this->size['width'],'height'=>$this->size['height'],'mime-type'=>$mime_type,'filesize'=>(int)@filesize($filename)];
    }
    public function stream($mime_type=null) {
        [,$ext,$mime_type]=$this->get_output_format(null,$mime_type);
        try{ $this->image->setImageFormat(strtoupper((string)$ext));header('Content-Type: '.$mime_type);echo $this->image->getImageBlob();return true; }
        catch(Throwable $e){ return new WP_Error('image_stream_error',$e->getMessage()); }
    }
}
}

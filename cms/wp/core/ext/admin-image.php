<?php
// Ergänzende WordPress-Funktionen (Bereich Admin, Teil 8): Bildeditor (image-edit.php) mit GD – Drehen, Spiegeln, Zuschneiden, Skalieren, Sichern/Wiederherstellen.
// Ohne die PHP-Erweiterung GD melden die Funktionen einen Fehler (Objekt mit ->error) bzw. false. Änderungen kommen als JSON-Verlauf ($_REQUEST['history']) wie beim WordPress-Editor.

if(!function_exists('elvado_adm_img_load')){ function elvado_adm_img_load($post_id) {   // GD-Bild des Anhangs (oder false)
    $f=get_attached_file($post_id);if(!$f||!is_file($f))$f=function_exists('_load_image_to_edit_path')?_load_image_to_edit_path($post_id):false;
    if(!$f||!is_file($f))return false;
    $i=_elvado_m_gd_load($f);return is_gd_image($i)?$i:false;
} }
if(!function_exists('_image_get_preview_ratio')){ function _image_get_preview_ratio($width, $height) { $m=max($width,$height);return $m>600?(600/$m):1; } }
if(!function_exists('_rotate_image_resource')){ function _rotate_image_resource($img, $angle) {
    if(function_exists('imagerotate')){ $r=imagerotate($img,$angle,0);if(is_gd_image($r)){ return $r; } }
    return $img;
} }
if(!function_exists('_flip_image_resource')){ function _flip_image_resource($img, $horz, $vert) {   // $horz spiegelt oben/unten, $vert links/rechts (wie bei WordPress)
    $w=imagesx($img);$h=imagesy($img);$dst=wp_imagecreatetruecolor($w,$h);
    if(is_gd_image($dst)){
        $sx=$vert?($w-1):0;$sy=$horz?($h-1):0;$sw=$vert?-$w:$w;$sh=$horz?-$h:$h;
        if(imagecopyresampled($dst,$img,0,0,$sx,$sy,$w,$h,$sw,$sh))return $dst;
    }
    return $img;
} }
if(!function_exists('_crop_image_resource')){ function _crop_image_resource($img, $x, $y, $w, $h) {
    $dst=wp_imagecreatetruecolor((int)$w,(int)$h);
    if(is_gd_image($dst)&&imagecopy($dst,$img,0,0,(int)$x,(int)$y,(int)$w,(int)$h))return $dst;
    return $img;
} }
if(!function_exists('image_edit_apply_changes')){ function image_edit_apply_changes($img, $changes) {   // Verlauf (rotate/flip/crop) auf ein GD-Bild anwenden
    if(!is_array($changes))return $img;
    foreach($changes as $k=>$o){
        if(!is_object($o))continue;
        if(isset($o->r)){ $o->type='rotate';$o->angle=$o->r;unset($o->r); }
        elseif(isset($o->f)){ $o->type='flip';$o->axis=$o->f;unset($o->f); }
        elseif(isset($o->c)){ $o->type='crop';$o->sel=$o->c;unset($o->c); }
        $changes[$k]=$o;
    }
    $img=apply_filters('image_edit_before_change',$img,$changes);
    foreach($changes as $op){
        if(!is_object($op)||!isset($op->type))continue;
        switch($op->type){
            case 'rotate': if(!empty($op->angle))$img=_rotate_image_resource($img,(int)$op->angle);break;
            case 'flip': if(!empty($op->axis))$img=_flip_image_resource($img,(bool)((int)$op->axis&1),(bool)((int)$op->axis&2));break;
            case 'crop': $s=$op->sel;$img=_crop_image_resource($img,$s->x,$s->y,$s->w,$s->h);break;
        }
    }
    return $img;
} }
if(!function_exists('wp_stream_image')){ function wp_stream_image($image, $mime_type, $attachment_id) {   // Bild direkt ausgeben
    if(!is_gd_image($image))return false;
    $image=apply_filters('image_save_pre',$image,$attachment_id);
    switch($mime_type){
        case 'image/jpeg': header('Content-Type: image/jpeg');return imagejpeg($image,null,90);
        case 'image/png': header('Content-Type: image/png');return imagepng($image);
        case 'image/gif': header('Content-Type: image/gif');return imagegif($image);
        case 'image/webp': if(function_exists('imagewebp')){ header('Content-Type: image/webp');return imagewebp($image,null,90); }return false;
        default: return false;
    }
} }
if(!function_exists('wp_save_image_file')){ function wp_save_image_file($filename, $image, $mime_type, $post_id) {
    $image=apply_filters('image_save_pre',$image,$post_id);
    $saved=apply_filters('wp_save_image_file',null,$filename,$image,$mime_type,$post_id);
    if(null!==$saved)return $saved;
    if(!is_gd_image($image))return false;
    return in_array($mime_type,['image/jpeg','image/png','image/gif','image/webp'],true)?(bool)_elvado_m_gd_save($image,$filename,$mime_type):false;
} }
if(!function_exists('stream_preview_image')){ function stream_preview_image($post_id) {   // Vorschau mit dem aktuellen Verlauf (auf 600 px begrenzt)
    $post=get_post($post_id);$img=elvado_adm_img_load($post_id);if(!$post||!$img)return false;
    $changes=!empty($_REQUEST['history'])?json_decode(wp_unslash($_REQUEST['history'])):null;
    if($changes)$img=image_edit_apply_changes($img,$changes);
    $w=imagesx($img);$h=imagesy($img);$r=_image_get_preview_ratio($w,$h);
    if($r<1){ $s=imagescale($img,max(1,(int)round($w*$r)),max(1,(int)round($h*$r)));if(is_gd_image($s))$img=$s; }
    return wp_stream_image($img,$post->post_mime_type,$post_id);
} }
if(!function_exists('wp_restore_image')){ function wp_restore_image($post_id) {   // Original aus den Sicherungs-Größen wiederherstellen
    $msg=new stdClass();$meta=wp_get_attachment_metadata($post_id);$file=get_attached_file($post_id);$bk=get_post_meta($post_id,'_wp_attachment_backup_sizes',true);
    if(!is_array($bk)||!is_array($meta)){ $msg->error='Die Bild-Metadaten konnten nicht geladen werden.';return $msg; }
    $parts=pathinfo((string)$file);$suffix=time().rand(100,999);$restored=false;
    if(isset($bk['full-orig'])&&is_array($bk['full-orig'])){
        $d=$bk['full-orig'];
        if($parts['basename']!==$d['file']&&isset($meta['width'],$meta['height']))$bk["full-$suffix"]=['width'=>$meta['width'],'height'=>$meta['height'],'file'=>$parts['basename']];
        $rf=path_join($parts['dirname'],$d['file']);$restored=(bool)update_attached_file($post_id,$rf);
        $meta['file']=_wp_relative_upload_path($rf);$meta['width']=$d['width'];$meta['height']=$d['height'];
    }
    foreach(get_intermediate_image_sizes() as $s){
        if(!isset($bk["$s-orig"]))continue;
        $d=$bk["$s-orig"];
        if(isset($meta['sizes'][$s])&&$meta['sizes'][$s]['file']!==$d['file']){ if(!empty($meta['sizes'][$s]['file']))$bk["$s-$suffix"]=$meta['sizes'][$s]; }
        $meta['sizes'][$s]=$d;
    }
    update_post_meta($post_id,'_wp_attachment_backup_sizes',$bk);
    if(!wp_update_attachment_metadata($post_id,$meta)){ $msg->error='Die Bild-Metadaten konnten nicht gespeichert werden.';return $msg; }
    if(!$restored)$msg->error='Die Bild-Metadaten sind inkonsistent.';else $msg->msg='Das Bild wurde wiederhergestellt.';
    return $msg;
} }
if(!function_exists('wp_save_image')){ function wp_save_image($post_id) {   // Änderungen als neue Datei speichern (Original bleibt als „full-orig“ gesichert)
    $r=new stdClass();$post=get_post($post_id);$file=get_attached_file($post_id);
    $img=elvado_adm_img_load($post_id);if(!$post||!$img){ $r->error='Das Bild konnte nicht geladen werden.';return $r; }
    $fw=!empty($_REQUEST['fwidth'])?(int)$_REQUEST['fwidth']:0;$fh=!empty($_REQUEST['fheight'])?(int)$_REQUEST['fheight']:0;
    $target=!empty($_REQUEST['target'])?preg_replace('/[^a-z0-9_-]+/i','',(string)$_REQUEST['target']):'';$scale=!empty($_REQUEST['do'])&&'scale'===$_REQUEST['do'];$scaled=false;
    if($scale&&$fw>0&&$fh>0){
        if($fw>imagesx($img)||$fh>imagesy($img)){ $r->error='Das Bild kann nicht vergrößert werden.';return $r; }
        $s=imagescale($img,$fw,$fh);if(!is_gd_image($s)){ $r->error='Das Bild konnte nicht skaliert werden.';return $r; }$img=$s;$scaled=true;
    } elseif(!empty($_REQUEST['history'])){
        $ch=json_decode(wp_unslash($_REQUEST['history']));if($ch)$img=image_edit_apply_changes($img,$ch);
    } else { $r->error='Es gibt nichts zu speichern: Das Bild wurde nicht verändert.';return $r; }
    $meta=wp_get_attachment_metadata($post_id);$meta=is_array($meta)?$meta:[];$bk=get_post_meta($post_id,'_wp_attachment_backup_sizes',true);$bk=is_array($bk)?$bk:[];
    $pi=pathinfo($file);$fn=preg_replace('/-e([0-9]+)$/','',$pi['filename']);$suffix=time().rand(100,999);
    $w=imagesx($img);$h=imagesy($img);$mime=$post->post_mime_type;
    if(in_array($target,['','all','full','nothumb'],true)||$scaled){
        $new=path_join($pi['dirname'],"{$fn}-e{$suffix}.".$pi['extension']);
        if(!wp_save_image_file($new,$img,$mime,$post_id)){ $r->error='Das Bild konnte nicht gespeichert werden.';return $r; }
        if(!isset($bk['full-orig'])&&isset($meta['width'],$meta['height']))$bk['full-orig']=['width'=>$meta['width'],'height'=>$meta['height'],'file'=>$pi['basename']];
        elseif(isset($meta['file'])&&$pi['basename']!==($bk['full-orig']['file']??''))$bk["full-$suffix"]=['width'=>$meta['width']??0,'height'=>$meta['height']??0,'file'=>$pi['basename']];
        update_attached_file($post_id,$new);$meta['file']=_wp_relative_upload_path($new);$meta['width']=$w;$meta['height']=$h;
        $r->fw=$w;$r->fh=$h;
        if('nothumb'!==$target){ $meta['sizes']=[];update_post_meta($post_id,'_wp_attachment_backup_sizes',$bk);wp_update_attachment_metadata($post_id,$meta);wp_update_image_subsizes($post_id);$meta=wp_get_attachment_metadata($post_id)?:$meta; }
    }
    if('thumbnail'===$target){   // nur die Vorschaugröße aus dem bearbeiteten Bild
        $tw=(int)get_option('thumbnail_size_w',150);$th=(int)get_option('thumbnail_size_h',150);
        $t=wp_imagecreatetruecolor($tw,$th);$sw=min($w,$h);imagecopyresampled($t,$img,0,0,(int)(($w-$sw)/2),(int)(($h-$sw)/2),$tw,$th,$sw,$sw);
        $tf="{$fn}-e{$suffix}-{$tw}x{$th}.".$pi['extension'];
        if(!wp_save_image_file(path_join($pi['dirname'],$tf),$t,$mime,$post_id)){ $r->error='Das Vorschaubild konnte nicht gespeichert werden.';return $r; }
        if(isset($meta['sizes']['thumbnail'])&&!isset($bk['thumbnail-orig']))$bk['thumbnail-orig']=$meta['sizes']['thumbnail'];
        $meta['sizes']['thumbnail']=['file'=>$tf,'width'=>$tw,'height'=>$th,'mime-type'=>$mime];$r->thumbnail=wp_basename($tf);
    }
    update_post_meta($post_id,'_wp_attachment_backup_sizes',$bk);wp_update_attachment_metadata($post_id,$meta);
    $r->msg='Das Bild wurde gespeichert.';return $r;
} }
if(!function_exists('wp_image_editor')){ function wp_image_editor($post_id, $msg=false) {   // Bearbeitungsfläche (HTML) mit Werkzeugleiste
    $nonce=wp_create_nonce("image_editor-$post_id");$meta=wp_get_attachment_metadata($post_id);$meta=is_array($meta)?$meta:[];
    $w=(int)($meta['width']??0);$h=(int)($meta['height']??0);$ratio=_image_get_preview_ratio($w,$h);
    echo '<div class="imgedit-wrap wp-clearfix" data-id="'.(int)$post_id.'">';
    if(is_object($msg)){ if(!empty($msg->error))echo '<div class="notice notice-error"><p>'.esc_html($msg->error).'</p></div>'; elseif(!empty($msg->msg))echo '<div class="notice notice-success"><p>'.esc_html($msg->msg).'</p></div>'; }
    echo '<div class="imgedit-panel-content"><div class="imgedit-menu"><button type="button" class="imgedit-rleft button" title="Nach links drehen">&#8634;</button> <button type="button" class="imgedit-rright button" title="Nach rechts drehen">&#8635;</button> ';
    echo '<button type="button" class="imgedit-flipv button" title="Vertikal spiegeln">&#8645;</button> <button type="button" class="imgedit-fliph button" title="Horizontal spiegeln">&#8646;</button> <button type="button" class="imgedit-crop button">Zuschneiden</button></div>';
    echo '<p class="imgedit-dims">Original: '.$w.' &times; '.$h.' &mdash; Vorschau: '.(int)round($w*$ratio).' &times; '.(int)round($h*$ratio).'</p>';
    echo '<div class="imgedit-crop-wrap"><img id="image-preview-'.(int)$post_id.'" alt="" src="'.esc_url(admin_url('admin-ajax.php?action=imgedit-preview&_ajax_nonce='.$nonce.'&postid='.(int)$post_id)).'" /></div>';
    echo '<input type="hidden" id="imgedit-nonce-'.(int)$post_id.'" value="'.esc_attr($nonce).'" /><input type="hidden" id="imgedit-history-'.(int)$post_id.'" value="" />';
    echo '<p class="imgedit-submit"><button type="button" class="imgedit-submit-btn button button-primary" disabled>Speichern</button> <button type="button" class="imgedit-restore button">Original wiederherstellen</button></p></div></div>';
} }

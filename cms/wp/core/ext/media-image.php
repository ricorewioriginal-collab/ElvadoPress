<?php
// Ergänzende Medien-Funktionen (Bilder): Größen, Abmessungen, <img>-Markup, Lazy-Loading/Ladeoptimierung, srcset/sizes, Bildformate.
// Eigenständig umgesetzt nach dem dokumentierten Verhalten; Hilfsfunktionen beginnen mit _elvado_m_.

/* ───────── Größen und Abmessungen ───────── */
if(!function_exists('wp_constrain_dimensions')){ /** Verkleinert proportional in den Rahmen (vergrößert nie). */
function wp_constrain_dimensions($current_width,$current_height,$max_width=0,$max_height=0) {
    $max_width=(int)$max_width;$max_height=(int)$max_height;$cw=(int)$current_width;$ch=(int)$current_height;
    if(!$max_width&&!$max_height)return [$cw,$ch];
    $wr=$hr=1.0;$dw=$dh=false;
    if($max_width>0&&$cw>0&&$cw>$max_width){ $wr=$max_width/$cw;$dw=true; }
    if($max_height>0&&$ch>0&&$ch>$max_height){ $hr=$max_height/$ch;$dh=true; }
    $small=min($wr,$hr);$large=max($wr,$hr);
    $ratio=((int)round($cw*$large)>$max_width||(int)round($ch*$large)>$max_height)?$small:$large;   // der größere Faktor darf nicht überlaufen
    $w=max(1,(int)round($cw*$ratio));$h=max(1,(int)round($ch*$ratio));
    if($dw&&$w===$max_width-1)$w=$max_width;   // Rundung: ein Pixel zu klein
    if($dh&&$h===$max_height-1)$h=$max_height;
    return apply_filters('wp_constrain_dimensions',[$w,$h],$current_width,$current_height,$max_width,$max_height);
} }
if(!function_exists('wp_expand_dimensions')){ /** Gegenstück zu wp_constrain_dimensions: skaliert (auch vergrößernd) so, dass das Ziel-Rechteck ausgefüllt, aber nicht überschritten wird. */
function wp_expand_dimensions($width,$height,$target_width=0,$target_height=0) {
    $width=(int)$width;$height=(int)$height;if($width<1||$height<1)return [$width,$height];
    $tw=(int)$target_width;$th=(int)$target_height;if(!$tw&&!$th)return [$width,$height];
    $r=min($tw>0?$tw/$width:INF,$th>0?$th/$height:INF);
    return [max(1,(int)round($width*$r)),max(1,(int)round($height*$r))];
} }
if(!function_exists('image_hwstring')){ function image_hwstring($width,$height) { $o='';if($width)$o.='width="'.(int)$width.'" ';if($height)$o.='height="'.(int)$height.'" ';return $o; } }
if(!function_exists('image_constrain_size_for_editor')){ function image_constrain_size_for_editor($width,$height,$size='medium',$context=null) {
    global $content_width;
    if(!$context)$context=is_admin()?'edit':'display';
    if(is_array($size)){ $mw=(int)$size[0];$mh=(int)$size[1]; }
    elseif($size==='thumbnail'){ $mw=(int)get_option('thumbnail_size_w',150);$mh=(int)get_option('thumbnail_size_h',150); }
    elseif($size==='medium'){ $mw=(int)get_option('medium_size_w',300);$mh=(int)get_option('medium_size_h',300); }
    elseif($size==='medium_large'){ $mw=(int)get_option('medium_large_size_w',768);$mh=(int)get_option('medium_large_size_h',0);if(!$mh)$mh=9999; }
    elseif($size==='large'){ $mw=(int)get_option('large_size_w',1024);$mh=(int)get_option('large_size_h',1024); }
    elseif(($add=wp_get_additional_image_sizes())&&isset($add[$size])){ $mw=(int)$add[$size]['width'];$mh=(int)$add[$size]['height'];if($content_width>0&&$context==='edit')$mw=min((int)$content_width,$mw); }
    else { $mw=(int)$width;$mh=(int)$height; }   // „full“ hat keine Begrenzung
    [$mw,$mh]=apply_filters('editor_max_image_size',[$mw,$mh],$size,$context);
    return wp_constrain_dimensions($width,$height,$mw,$mh);
} }
if(!function_exists('has_image_size')){ function has_image_size($name) { return isset(wp_get_additional_image_sizes()[$name]); } }
if(!function_exists('remove_image_size')){ function remove_image_size($name) { if(!isset($GLOBALS['_wp_additional_image_sizes'][$name]))return false;unset($GLOBALS['_wp_additional_image_sizes'][$name]);return true; } }
if(!function_exists('_wp_add_additional_image_sizes')){ function _wp_add_additional_image_sizes() { add_image_size('1536x1536',1536,1536);add_image_size('2048x2048',2048,2048); } }
if(!function_exists('_wp_get_attachment_relative_path')){ function _wp_get_attachment_relative_path($file) {
    $dir=dirname((string)$file);if($dir==='.')return '';
    if(str_contains($dir,'wp-content/uploads'))$dir=substr($dir,strpos($dir,'wp-content/uploads')+18);
    return ltrim($dir,'/');
} }
if(!function_exists('_wp_get_image_size_from_meta')){ function _wp_get_image_size_from_meta($size_name,$image_meta) {
    if($size_name==='full')return [absint($image_meta['width']??0),absint($image_meta['height']??0)];
    if(!empty($image_meta['sizes'][$size_name]))return [absint($image_meta['sizes'][$size_name]['width']),absint($image_meta['sizes'][$size_name]['height'])];
    return false;
} }
if(!function_exists('wp_image_file_matches_image_meta')){ function wp_image_file_matches_image_meta($image_location,$image_meta,$attachment_id=0) {
    $match=false;$loc=(string)$image_location;
    if(!empty($image_meta['file'])&&str_contains($loc,(string)$image_meta['file']))$match=true;
    elseif(!empty($image_meta['sizes'])){ $dir=dirname((string)($image_meta['file']??''));
        foreach($image_meta['sizes'] as $s)if(!empty($s['file'])&&str_contains($loc,path_join($dir==='.'?'':$dir,$s['file']))){ $match=true;break; } }
    if(!$match&&!empty($image_meta['original_image'])&&str_contains($loc,(string)$image_meta['original_image']))$match=true;
    return (bool)apply_filters('wp_image_file_matches_image_meta',$match,$image_location,$image_meta,$attachment_id);
} }
if(!function_exists('wp_image_src_get_dimensions')){ function wp_image_src_get_dimensions($image_src,$image_meta,$attachment_id=0) {
    $dim=false;
    if(is_array($image_meta)&&!empty($image_meta['file'])&&wp_image_file_matches_image_meta($image_src,$image_meta,$attachment_id)){
        $name=wp_basename(strtok((string)$image_src,'?'));
        if(wp_basename($image_meta['file'])===$name)$dim=[(int)$image_meta['width'],(int)$image_meta['height']];
        else foreach((array)($image_meta['sizes']??[]) as $s)if(isset($s['file'])&&$s['file']===$name){ $dim=[(int)$s['width'],(int)$s['height']];break; }
    }
    return apply_filters('wp_image_src_get_dimensions',$dim,$image_src,$image_meta,$attachment_id);
} }

/** Zwischengröße aus den Metadaten (die vorhandene image_get_intermediate_size liefert hier nichts). */
if(!function_exists('_elvado_m_intermediate')){ function _elvado_m_intermediate($id,$size,$meta=null) {
    $meta=$meta??wp_get_attachment_metadata($id);if(!is_array($meta)||empty($meta['sizes']))return false;
    $data=false;
    if(is_array($size)){ $best=null;
        foreach($meta['sizes'] as $s){ if(empty($s['width'])||empty($s['height']))continue;
            if($s['width']>=(int)$size[0]&&$s['height']>=(int)$size[1]&&($best===null||$s['width']*$s['height']<$best['width']*$best['height']))$best=$s; }
        $data=$best?:false;
    } elseif(isset($meta['sizes'][$size]))$data=$meta['sizes'][$size];
    if(!$data||empty($data['file']))return false;
    $url=wp_get_attachment_url($id);if($url)$data['url']=path_join(dirname($url),$data['file']);
    return $data;
} }
if(!function_exists('image_downsize')){ function image_downsize($id,$size='medium') {
    $is_image=wp_attachment_is_image($id);
    $out=apply_filters('image_downsize',false,$id,$size);if($out)return $out;
    $url=wp_get_attachment_url($id);$meta=wp_get_attachment_metadata($id);$w=$h=0;$inter=false;
    if(!$is_image){
        if(!empty($meta['sizes']['full'])&&$url){ $url=path_join(dirname($url),$meta['sizes']['full']['file']);$w=(int)$meta['sizes']['full']['width'];$h=(int)$meta['sizes']['full']['height'];$inter=true; }
        else return false;
    } else {
        $d=$size==='full'?false:_elvado_m_intermediate($id,$size,is_array($meta)?$meta:null);
        if($d&&!empty($d['url'])){ $url=$d['url'];$w=(int)$d['width'];$h=(int)$d['height'];$inter=true; }
    }
    if(!$w&&!$h&&is_array($meta)&&isset($meta['width'],$meta['height'])){ $w=(int)$meta['width'];$h=(int)$meta['height']; }
    if($url){ [$w,$h]=image_constrain_size_for_editor($w,$h,$size);return [$url,$w,$h,$inter]; }
    return false;
} }
if(!function_exists('get_image_tag')){ function get_image_tag($id,$alt,$title,$align,$size='medium') {
    $d=image_downsize($id,$size);if(!$d)return '';[$src,$w,$h]=$d;
    $title=$title?'title="'.esc_attr($title).'" ':'';$sc=is_array($size)?implode('x',$size):$size;
    $class=apply_filters('get_image_tag_class','align'.esc_attr($align).' size-'.esc_attr($sc).' wp-image-'.$id,$id,$align,$size);
    $html='<img src="'.esc_url($src).'" alt="'.esc_attr($alt).'" '.$title.image_hwstring($w,$h).'class="'.$class.'" />';
    return apply_filters('get_image_tag',$html,$id,$alt,$title,$align,$size);
} }

/* ───────── srcset / sizes ───────── */
if(!function_exists('_elvado_m_srcset')){ /** Eigene srcset-Berechnung (die vorhandene wp_calculate_image_srcset liefert hier false). */
function _elvado_m_srcset($size_array,$image_src,$image_meta,$attachment_id=0) {
    $w=(int)($size_array[0]??0);$h=(int)($size_array[1]??0);if(!$w||empty($image_meta['sizes'])||empty($image_meta['file']))return false;
    $dir=trailingslashit(dirname((string)strtok((string)$image_src,'?')));$max=(int)apply_filters('max_srcset_image_width',2048,$size_array);
    $all=$image_meta['sizes'];$all[]=['width'=>$image_meta['width']??0,'height'=>$image_meta['height']??0,'file'=>wp_basename($image_meta['file'])];
    $src=[];foreach($all as $s){ if(empty($s['width'])||empty($s['file'])||(int)$s['width']>$max||!wp_image_matches_ratio($w,$h,(int)$s['width'],(int)($s['height']??0)))continue;
        $src[(int)$s['width']]=['url'=>$dir.$s['file'],'descriptor'=>'w','value'=>(int)$s['width']]; }
    $src=apply_filters('wp_calculate_image_srcset',$src,$size_array,$image_src,$image_meta,$attachment_id);
    if(!is_array($src)||count($src)<2)return false;ksort($src);$o=[];
    foreach($src as $s)$o[]=str_replace(' ','%20',$s['url']).' '.$s['value'].$s['descriptor'];
    return implode(', ',$o);
} }
if(!function_exists('wp_image_add_srcset_and_sizes')){ function wp_image_add_srcset_and_sizes($image,$image_meta,$attachment_id) {
    if(empty($image_meta['sizes']))return $image;
    $src=preg_match('/src="([^"]+)"/',$image,$m)?$m[1]:'';[$src]=explode('?',$src);if($src==='')return $image;
    // Nachträglich bearbeitetes Bild (…-e<13 Ziffern>) nicht verändern
    if(!empty($image_meta['file'])&&preg_match('/-e[0-9]{13}/',$image_meta['file'],$e)&&!str_contains(wp_basename($src),$e[0]))return $image;
    $w=preg_match('/ width="([0-9]+)"/',$image,$m)?(int)$m[1]:0;$h=preg_match('/ height="([0-9]+)"/',$image,$m)?(int)$m[1]:0;
    $size=$w&&$h?[$w,$h]:wp_image_src_get_dimensions($src,$image_meta,$attachment_id);if(!$size)return $image;
    $srcset=wp_calculate_image_srcset($size,$src,$image_meta,$attachment_id)?:_elvado_m_srcset($size,$src,$image_meta,$attachment_id);
    $sizes=false;
    if($srcset){ $sizes=strpos($image,' sizes=')!==false?true:(wp_calculate_image_sizes($size,$src,$image_meta,$attachment_id)?:apply_filters('wp_calculate_image_sizes',sprintf('(max-width: %1$dpx) 100vw, %1$dpx',(int)$size[0]),$size,$src,$image_meta,$attachment_id)); }
    if($srcset&&$sizes){ $attr=sprintf(' srcset="%s"',esc_attr($srcset));if(is_string($sizes))$attr.=sprintf(' sizes="%s"',esc_attr($sizes));
        return preg_replace('/<img ([^>]+?)[\/ ]*>/','<img $1'.str_replace('$','\$',$attr).' />',$image,1); }
    return $image;
} }
if(!function_exists('wp_img_tag_add_srcset_and_sizes_attr')){ function wp_img_tag_add_srcset_and_sizes_attr($image,$context,$attachment_id) {
    if(str_contains($image,' srcset=')||!apply_filters('wp_img_tag_add_srcset_and_sizes_attr',true,$image,$context,$attachment_id))return $image;
    $meta=wp_get_attachment_metadata($attachment_id);return $meta?wp_image_add_srcset_and_sizes($image,$meta,$attachment_id):$image;
} }
if(!function_exists('wp_img_tag_add_width_and_height_attr')){ function wp_img_tag_add_width_and_height_attr($image,$context,$attachment_id) {
    if(!str_contains($image,' src="')||(preg_match('/\swidth=/i',$image)&&preg_match('/\sheight=/i',$image)))return $image;
    if(!apply_filters('wp_img_tag_add_width_and_height_attr',true,$image,$context,$attachment_id))return $image;
    $src=preg_match('/ src="([^"]+)"/',$image,$m)?$m[1]:'';$dim=$src!==''?wp_image_src_get_dimensions($src,wp_get_attachment_metadata($attachment_id),$attachment_id):false;
    return $dim?preg_replace('/<img/','<img '.trim(image_hwstring($dim[0],$dim[1])),$image,1):$image;
} }
if(!function_exists('wp_sizes_attribute_includes_valid_auto')){ function wp_sizes_attribute_includes_valid_auto($sizes_attr) {
    $first=explode(',',(string)$sizes_attr)[0];return strtolower(trim($first," \t\f\r\n"))==='auto';
} }
if(!function_exists('wp_img_tag_add_auto_sizes')){ function wp_img_tag_add_auto_sizes($content) {
    $p=new WP_HTML_Tag_Processor($content);if(!$p->next_tag(['tag_name'=>'img']))return $content;
    $loading=$p->get_attribute('loading');if(!is_string($loading)||strtolower(trim($loading," \t\f\r\n"))!=='lazy')return $content;
    $sizes=$p->get_attribute('sizes');if(is_string($sizes)&&wp_sizes_attribute_includes_valid_auto($sizes))return $content;
    $p->set_attribute('sizes',is_string($sizes)&&$sizes!==''?'auto, '.$sizes:'auto');
    return $p->get_updated_html();
} }
if(!function_exists('wp_print_auto_sizes_contain_css_fix')){ function wp_print_auto_sizes_contain_css_fix() {
    if(!apply_filters('wp_img_tag_add_auto_sizes',true))return;
    echo '<style>img:is([sizes="auto" i], [sizes^="auto," i]) { contain-intrinsic-size: 3000px 1500px }</style>'."\n";
} }

/* ───────── Ladeoptimierung (loading / fetchpriority / decoding) ───────── */
if(!function_exists('wp_lazy_loading_enabled')){ function wp_lazy_loading_enabled($tag_name,$context) { return (bool)apply_filters('wp_lazy_loading_enabled',$tag_name==='img'||$tag_name==='iframe',$tag_name,$context); } }
if(!function_exists('_elvado_m_media_count')){ /** Zähler der bisher ausgegebenen Medien (Seitenaufruf); $inc=0 liest nur. */
function _elvado_m_media_count($inc=0) { $c=(int)($GLOBALS['_elvado_m_media_count']??0)+$inc;$GLOBALS['_elvado_m_media_count']=$c;return $c; } }
if(!function_exists('wp_maybe_add_fetchpriority_high_attr')){ function wp_maybe_add_fetchpriority_high_attr($loading_attrs,$tag_name,$attr) {
    if($tag_name!=='img')return $loading_attrs;
    if(isset($attr['fetchpriority'])){ if($attr['fetchpriority']==='high')$GLOBALS['_elvado_m_high_flag']=true;return $loading_attrs; }
    if(!empty($GLOBALS['_elvado_m_high_flag']))return $loading_attrs;
    $min=(int)apply_filters('wp_min_priority_img_pixels',50000,$attr);
    if((int)($attr['width']??0)*(int)($attr['height']??0)>$min){ $GLOBALS['_elvado_m_high_flag']=true;$loading_attrs['fetchpriority']='high';unset($loading_attrs['loading']); }   // lazy und high schließen sich aus
    return $loading_attrs;
} }
if(!function_exists('wp_get_loading_optimization_attributes')){ function wp_get_loading_optimization_attributes($tag_name,$attr,$context) {
    $pre=apply_filters('pre_wp_get_loading_optimization_attributes',false,$tag_name,$attr,$context);if(is_array($pre))return $pre;
    $a=[];if($tag_name==='img'&&empty($attr['decoding']))$a['decoding']='async';
    $done=fn($x)=>apply_filters('wp_get_loading_optimization_attributes',$x,$tag_name,$attr,$context);
    if(is_admin()||is_feed())return $done($a);
    $explicit=$attr['loading']??null;
    if(is_string($explicit)&&$explicit!==''){ if($explicit!=='lazy')_elvado_m_media_count(1);return $done($a); }   // ausdrücklich gesetzt: nicht überschreiben
    $a=wp_maybe_add_fetchpriority_high_attr($a,$tag_name,$attr);
    if(isset($a['fetchpriority'])){ _elvado_m_media_count(1);return $done($a); }
    if(!wp_lazy_loading_enabled($tag_name,$context))return $done($a);
    // Die ersten Medien (Standard 3) liegen vermutlich im sichtbaren Bereich und werden nicht verzögert
    if(_elvado_m_media_count(1)>(int)apply_filters('wp_omit_loading_attr_threshold',3))$a['loading']='lazy';
    return $done($a);
} }
if(!function_exists('wp_img_tag_add_loading_optimization_attrs')){ function wp_img_tag_add_loading_optimization_attrs($image,$context) {
    if(!preg_match('/\ssrc=(["\'])(.*?)\1/is',$image,$m)||trim($m[2])==='')return $image;
    $attr=[];foreach(['width','height','loading','fetchpriority','decoding'] as $n)if(preg_match('/\s'.$n.'=(["\'])(.*?)\1/is',$image,$mm))$attr[$n]=in_array($n,['width','height'],true)?(int)$mm[2]:$mm[2];
    $o=wp_get_loading_optimization_attributes('img',$attr,$context);
    foreach(['decoding','loading','fetchpriority'] as $n)if(empty($attr[$n])&&isset($o[$n]))$image=preg_replace('/<img/','<img '.$n.'="'.esc_attr($o[$n]).'"',$image,1);
    return $image;
} }
if(!function_exists('wp_iframe_tag_add_loading_attr')){ function wp_iframe_tag_add_loading_attr($iframe,$context) {
    if(!preg_match('/\ssrc=(["\'])(.*?)\1/is',$iframe,$m)||trim($m[2])===''||preg_match('/\sloading=/i',$iframe))return $iframe;
    $o=wp_get_loading_optimization_attributes('iframe',['width'=>preg_match('/\swidth=["\']?(\d+)/i',$iframe,$w)?(int)$w[1]:0,'height'=>preg_match('/\sheight=["\']?(\d+)/i',$iframe,$h)?(int)$h[1]:0],$context);
    return !empty($o['loading'])?preg_replace('/<iframe/','<iframe loading="'.esc_attr($o['loading']).'"',$iframe,1):$iframe;
} }

/** <img> eines Anhangs mit Größe, srcset/sizes und Ladeoptimierung (die vorhandene wp_get_attachment_image liefert nur die Originaladresse). */
if(!function_exists('_elvado_m_attachment_image')){ function _elvado_m_attachment_image($id,$size='thumbnail',$icon=false,$attr='') {
    $post=get_post($id);$img=$post?image_downsize($id,$size):false;if(!$img)return '';
    [$src,$w,$h]=$img;$sc=is_array($size)?implode('x',$size):$size;$meta=wp_get_attachment_metadata($id);
    $def=['src'=>$src,'class'=>"attachment-$sc size-$sc",'alt'=>trim(strip_tags((string)get_post_meta($id,'_wp_attachment_image_alt',true)))];
    $attr=wp_parse_args($attr,$def);
    if(empty($attr['srcset'])&&is_array($meta)&&$w){ $ss=wp_calculate_image_srcset([$w,$h],$src,$meta,$id)?:_elvado_m_srcset([$w,$h],$src,$meta,$id);
        if($ss){ $attr['srcset']=$ss;if(empty($attr['sizes']))$attr['sizes']=wp_calculate_image_sizes([$w,$h],$src,$meta,$id)?:sprintf('(max-width: %1$dpx) 100vw, %1$dpx',$w); } }
    $attr=apply_filters('wp_get_attachment_image_attributes',$attr,$post,$size);
    $opt=wp_get_loading_optimization_attributes('img',$attr+['width'=>$w,'height'=>$h],apply_filters('wp_get_attachment_image_context','wp_get_attachment_image'));
    foreach($opt as $k=>$v)if(!isset($attr[$k]))$attr[$k]=$v;
    $html=rtrim('<img '.image_hwstring($w,$h));foreach(array_map('esc_attr',$attr) as $k=>$v)$html.=" $k=\"$v\"";$html.=' />';
    return apply_filters('wp_get_attachment_image',$html,$id,$size,$icon,$attr);
} }
/** Link auf einen Anhang (Bild bzw. Titel als Linktext). */
if(!function_exists('_elvado_m_attachment_link')){ function _elvado_m_attachment_link($id=0,$size='thumbnail',$permalink=false,$icon=false,$text=false,$attr='') {
    $p=get_post($id);if(!$p||$p->post_type!=='attachment'||!($url=wp_get_attachment_url($p->ID)))return 'Anhang fehlt';
    if($permalink)$url=get_attachment_link($p->ID);
    if($text)$t=$text;elseif($size&&$size!=='none')$t=_elvado_m_attachment_image($p->ID,$size,$icon,$attr);else $t='';
    if(trim((string)$t)==='')$t=$p->post_title;
    if(trim((string)$t)==='')$t=esc_html(pathinfo((string)get_attached_file($p->ID),PATHINFO_FILENAME));
    return apply_filters('wp_get_attachment_link',sprintf('<a href="%s">%s</a>',esc_url($url),$t),$id,$size,$permalink,$icon,$text,$attr);
} }

/* ───────── Beitragsbild-Haken ───────── */
if(!function_exists('_wp_post_thumbnail_class_filter')){ function _wp_post_thumbnail_class_filter($attr) { $attr['class']=trim((string)($attr['class']??'').' wp-post-image');return $attr; } }
if(!function_exists('_wp_post_thumbnail_class_filter_add')){ function _wp_post_thumbnail_class_filter_add($attr) { add_filter('wp_get_attachment_image_attributes','_wp_post_thumbnail_class_filter'); } }
if(!function_exists('_wp_post_thumbnail_class_filter_remove')){ function _wp_post_thumbnail_class_filter_remove($attr) { remove_filter('wp_get_attachment_image_attributes','_wp_post_thumbnail_class_filter'); } }
if(!function_exists('_wp_post_thumbnail_context_filter')){ function _wp_post_thumbnail_context_filter($context) { return 'the_post_thumbnail'; } }
if(!function_exists('_wp_post_thumbnail_context_filter_add')){ function _wp_post_thumbnail_context_filter_add() { add_filter('wp_get_attachment_image_context','_wp_post_thumbnail_context_filter'); } }
if(!function_exists('_wp_post_thumbnail_context_filter_remove')){ function _wp_post_thumbnail_context_filter_remove() { remove_filter('wp_get_attachment_image_context','_wp_post_thumbnail_context_filter'); } }

/* ───────── Bildformate und GD ───────── */
if(!function_exists('is_gd_image')){ function is_gd_image($image) { return $image instanceof GdImage||(is_resource($image)&&get_resource_type($image)==='gd'); } }
if(!function_exists('wp_imagecreatetruecolor')){ function wp_imagecreatetruecolor($width,$height) {
    if(!function_exists('imagecreatetruecolor'))return false;
    $img=@imagecreatetruecolor((int)$width,(int)$height);
    if(is_gd_image($img)&&function_exists('imagealphablending')&&function_exists('imagesavealpha')){ imagealphablending($img,false);imagesavealpha($img,true); }
    return $img;
} }
if(!function_exists('_wp_image_editor_choose')){ function _wp_image_editor_choose($args=[]) {
    foreach(apply_filters('wp_image_editors',['WP_Image_Editor_Imagick','WP_Image_Editor_GD']) as $c)
        if(class_exists($c)&&method_exists($c,'test')&&call_user_func([$c,'test'],$args)&&(empty($args['mime_type'])||!method_exists($c,'supports_mime_type')||call_user_func([$c,'supports_mime_type'],$args['mime_type'])))return $c;
    return false;
} }
if(!function_exists('wp_get_image_editor_output_format')){ function wp_get_image_editor_output_format($filename,$mime_type) {
    $def=['image/heic'=>'image/jpeg','image/heif'=>'image/jpeg','image/heic-sequence'=>'image/jpeg','image/heif-sequence'=>'image/jpeg'];
    return apply_filters('image_editor_output_format',$def,$filename,$mime_type);
} }
if(!function_exists('wp_show_heic_upload_error')){ function wp_show_heic_upload_error($plupload_settings) { $plupload_settings['heic_upload_error']=true;return $plupload_settings; } }
if(!function_exists('wp_get_avif_info')){ /** Liest Breite/Höhe/Bittiefe/Kanäle aus einer AVIF-Datei (ISOBMFF-Kästen ispe/pixi); false, wenn keine AVIF-Datei. */
function wp_get_avif_info($filename) {
    $r=['width'=>false,'height'=>false,'bit_depth'=>false,'num_channels'=>false];
    $b=is_file((string)$filename)?@file_get_contents($filename,false,null,0,65536):false;
    if(!is_string($b)||strlen($b)<32||substr($b,4,4)!=='ftyp'||!preg_match('/avif|avis/',substr($b,8,16)))return false;
    if(($i=strpos($b,'ispe'))!==false&&strlen($b)>=$i+16){ $u=unpack('Nw/Nh',substr($b,$i+8,8));$r['width']=$u['w'];$r['height']=$u['h']; }
    if(($i=strpos($b,'pixi'))!==false&&strlen($b)>=$i+9){ $n=ord($b[$i+8]);$r['num_channels']=$n;if($n>0&&strlen($b)>$i+9)$r['bit_depth']=ord($b[$i+9]); }
    return $r;
} }
if(!function_exists('wp_get_webp_info')){ /** Liest Breite/Höhe/Art (lossy, lossless, animated, alpha …) aus einer WebP-Datei. */
function wp_get_webp_info($filename) {
    $r=['width'=>false,'height'=>false,'type'=>false];
    $b=is_file((string)$filename)?@file_get_contents($filename,false,null,0,64):false;
    if(!is_string($b)||strlen($b)<30||substr($b,0,4)!=='RIFF'||substr($b,8,4)!=='WEBP')return $r;
    $c=substr($b,12,4);
    if($c==='VP8 '){ $u=unpack('vw/vh',substr($b,26,4));$r=['width'=>$u['w']&0x3fff,'height'=>$u['h']&0x3fff,'type'=>'lossy']; }
    elseif($c==='VP8L'){ $bits=unpack('V',substr($b,21,4))[1];$r=['width'=>1+($bits&0x3fff),'height'=>1+(($bits>>14)&0x3fff),'type'=>'lossless']; }
    elseif($c==='VP8X'){ $f=ord($b[20]);$w=unpack('V',substr($b,24,3)."\0")[1];$h=unpack('V',substr($b,27,3)."\0")[1];
        $t=($f&2)?(($f&16)?'animated-alpha':'animated'):(($f&16)?'alpha':'lossy');$r=['width'=>$w+1,'height'=>$h+1,'type'=>$t]; }
    return $r;
} }

/* ───────── Anhänge, Galerien, Datenschutz ───────── */
/** Beitragsliste nach ID indizieren (get_children liefert hier eine fortlaufende Liste, WordPress eine ID-Karte). */
if(!function_exists('_elvado_m_by_id')){ function _elvado_m_by_id($posts) { $o=[];foreach((array)$posts as $p)if(is_object($p))$o[$p->ID]=$p;return $o; } }

if(!function_exists('get_attached_media')){ function get_attached_media($type,$post=0) {
    $post=get_post($post);if(!$post)return [];
    $args=apply_filters('get_attached_media_args',['post_parent'=>$post->ID,'post_type'=>'attachment','post_mime_type'=>$type,'posts_per_page'=>-1,'orderby'=>'menu_order ID','order'=>'ASC'],$type,$post);
    return (array)apply_filters('get_attached_media',_elvado_m_by_id(get_children($args)),$type,$post);
} }
if(!function_exists('get_attachment_taxonomies')){ function get_attachment_taxonomies($attachment,$output='names') {
    if(is_int($attachment))$attachment=get_post($attachment);elseif(is_array($attachment))$attachment=(object)$attachment;
    if(!is_object($attachment))return [];
    $name=wp_basename((string)get_attached_file($attachment->ID));$objs=['attachment'];if(str_contains($name,'.'))$objs[]='attachment:'.substr($name,strrpos($name,'.')+1);
    if(!empty($attachment->post_mime_type)){ $objs[]='attachment:'.$attachment->post_mime_type;
        if(str_contains($attachment->post_mime_type,'/'))foreach(explode('/',$attachment->post_mime_type) as $t)if($t!=='')$objs[]="attachment:$t"; }
    $tax=[];foreach($objs as $o){ $x=get_object_taxonomies($o,$output);if($x)$tax=array_merge($tax,$x); }
    return $output==='names'?array_values(array_unique($tax)):$tax;
} }
if(!function_exists('get_taxonomies_for_attachments')){ function get_taxonomies_for_attachments($output='names') {
    $t=[];foreach(get_taxonomies([],'objects') as $x)foreach((array)$x->object_type as $ot)if($ot==='attachment'||str_starts_with($ot,'attachment:')){ if($output==='names')$t[]=$x->name;else $t[$x->name]=$x;break; }
    return $t;
} }
if(!function_exists('get_post_galleries')){ function get_post_galleries($post,$html=true) {
    $post=get_post($post);if(!$post)return [];$c=(string)$post->post_content;$g=[];
    if(has_shortcode($c,'gallery')&&preg_match_all('/'.get_shortcode_regex(['gallery']).'/s',$c,$ms,PREG_SET_ORDER))
        foreach($ms as $s){ if($s[2]!=='gallery')continue;$atts=shortcode_parse_atts($s[3]);if(!is_array($atts))$atts=[];if(!isset($atts['id']))$atts['id']=$post->ID;
            $out=do_shortcode_tag($s);
            if($html)$g[]=$out;else{ preg_match_all('#src=([\'"])(.+?)\1#is',$out,$src,PREG_SET_ORDER);$srcs=[];foreach($src as $x)$srcs[]=$x[2];$g[]=array_merge($atts,['src'=>array_values(array_unique($srcs))]); } }
    if(str_contains($c,'<!-- wp:gallery'))foreach(parse_blocks($c) as $b){ if(($b['blockName']??'')!=='core/gallery')continue;
        if($html)$g[]=render_block($b);else{ preg_match_all('#<img[^>]+src=([\'"])(.+?)\1#is',(string)$b['innerHTML'].implode('',array_column((array)$b['innerBlocks'],'innerHTML')),$src,PREG_SET_ORDER);
            $g[]=['ids'=>implode(',',array_filter(array_column(array_column((array)$b['innerBlocks'],'attrs'),'id'))),'src'=>array_values(array_unique(array_column($src,2)))]; } }
    return apply_filters('get_post_galleries',$g,$post);
} }
if(!function_exists('get_post_galleries_images')){ function get_post_galleries_images($post=0) { return wp_list_pluck(get_post_galleries($post,false),'src'); } }
if(!function_exists('get_post_gallery_images')){ function get_post_gallery_images($post=0) {
    $g=get_post_galleries($post,false);$first=$g[0]??[];return apply_filters('get_post_gallery_images',empty($first['src'])?[]:$first['src'],$post);
} }
if(!function_exists('get_adjacent_image_link')){ function get_adjacent_image_link($prev=true,$size='thumbnail',$text=false) {
    $post=get_post();if(!$post)return '';
    $att=array_values(get_children(['post_parent'=>$post->post_parent,'post_status'=>'inherit','post_type'=>'attachment','post_mime_type'=>'image','order'=>'ASC','orderby'=>'menu_order ID']));
    $k=0;foreach($att as $k=>$a)if((int)$a->ID===(int)$post->ID)break;
    $out='';$id=0;
    if($att){ $k=$prev?$k-1:$k+1;if(isset($att[$k])){ $id=$att[$k]->ID;$out=_elvado_m_attachment_link($id,$size,true,false,$text,['alt'=>get_the_title($id)]); } }
    return apply_filters(($prev?'previous':'next').'_image_link',$out,$id,$size,$text);
} }
if(!function_exists('adjacent_image_link')){ function adjacent_image_link($prev=true,$size='thumbnail',$text=false) { echo get_adjacent_image_link($prev,$size,$text); } }
if(!function_exists('get_previous_image_link')){ function get_previous_image_link($size='thumbnail',$text=false) { return get_adjacent_image_link(true,$size,$text); } }
if(!function_exists('previous_image_link')){ function previous_image_link($size='thumbnail',$text=false) { adjacent_image_link(true,$size,$text); } }
if(!function_exists('get_next_image_link')){ function get_next_image_link($size='thumbnail',$text=false) { return get_adjacent_image_link(false,$size,$text); } }
if(!function_exists('next_image_link')){ function next_image_link($size='thumbnail',$text=false) { adjacent_image_link(false,$size,$text); } }
if(!function_exists('wp_maybe_generate_attachment_metadata')){ function wp_maybe_generate_attachment_metadata($attachment) {
    if(empty($attachment)||empty($attachment->ID))return;$id=(int)$attachment->ID;$file=get_attached_file($id);
    if(!$file||!file_exists($file)||wp_get_attachment_metadata($id))return;
    $lock='wp_generating_att_'.$id;
    if(!metadata_exists('post',$id,'_wp_attachment_metadata')&&!get_transient($lock)){ set_transient($lock,$file);wp_update_attachment_metadata($id,wp_generate_attachment_metadata($id,$file));delete_transient($lock); }
} }
if(!function_exists('wpview_media_sandbox_styles')){ function wpview_media_sandbox_styles() {
    $v='ver='.$GLOBALS['wp_version'];
    return apply_filters('wpview_media_sandbox_styles',[includes_url("js/mediaelement/mediaelementplayer-legacy.min.css?$v"),includes_url("js/mediaelement/wp-mediaelement.css?$v")]);
} }
if(!function_exists('wp_register_media_personal_data_exporter')){ function wp_register_media_personal_data_exporter($exporters) {
    $exporters['wordpress-media']=['exporter_friendly_name'=>'WordPress-Medien','callback'=>'wp_media_personal_data_exporter'];return $exporters;
} }
if(!function_exists('wp_media_personal_data_exporter')){ function wp_media_personal_data_exporter($email_address,$page=1) {
    $user=get_user_by('email',trim((string)$email_address));if(!$user)return ['data'=>[],'done'=>true];
    $q=new WP_Query(['author'=>$user->ID,'posts_per_page'=>30,'paged'=>max(1,(int)$page),'post_type'=>'attachment','post_status'=>'any','orderby'=>'ID','order'=>'ASC']);
    $data=[];foreach($q->posts as $p){ $u=wp_get_attachment_url($p->ID);
        $data[]=['group_id'=>'media','group_label'=>'Medien','group_description'=>'Mediendaten der Person.','item_id'=>"post-{$p->ID}",'data'=>$u?[['name'=>'URL','value'=>$u]]:[]]; }
    return ['data'=>$data,'done'=>$q->max_num_pages<=max(1,(int)$page)];
} }
if(!function_exists('wp_plupload_default_settings')){ /** Setzt die Upload-Einstellungen für das Skript wp-plupload (nur einmal je Aufruf der Seite). */
function wp_plupload_default_settings() {
    static $done=false;if($done)return;$done=true;
    $max=wp_max_upload_size();$d=['runtimes'=>'html5,html4','file_data_name'=>'async-upload','url'=>admin_url('async-upload.php'),'filters'=>['max_file_size'=>$max.'b','mime_types'=>[['extensions'=>'*']]],'multipart_params'=>['action'=>'upload-attachment','_wpnonce'=>wp_create_nonce('media-form')]];
    $s=['defaults'=>apply_filters('plupload_default_settings',$d),'browser'=>['mobile'=>wp_is_mobile(),'supported'=>true],'limitExceeded'=>false];
    wp_localize_script('wp-plupload','_wpPluploadSettings',$s);
} }
if(!function_exists('wp_prepare_attachment_for_js')){ function wp_prepare_attachment_for_js($attachment) {
    $attachment=get_post($attachment);if(!$attachment||$attachment->post_type!=='attachment')return null;
    $meta=wp_get_attachment_metadata($attachment->ID);$mime=(string)$attachment->post_mime_type;
    [$type,$subtype]=array_pad(explode('/',$mime,2),2,'');$url=(string)wp_get_attachment_url($attachment->ID);
    $r=['id'=>$attachment->ID,'title'=>$attachment->post_title,'filename'=>wp_basename((string)get_attached_file($attachment->ID)),'url'=>$url,'link'=>get_attachment_link($attachment->ID),
        'alt'=>(string)get_post_meta($attachment->ID,'_wp_attachment_image_alt',true),'author'=>$attachment->post_author,'description'=>$attachment->post_content,'caption'=>$attachment->post_excerpt,'name'=>$attachment->post_name,
        'status'=>$attachment->post_status,'uploadedTo'=>$attachment->post_parent,'date'=>(int)strtotime((string)$attachment->post_date_gmt)*1000,'modified'=>(int)strtotime((string)$attachment->post_modified_gmt)*1000,
        'menuOrder'=>$attachment->menu_order,'mime'=>$mime,'type'=>$type,'subtype'=>$subtype,'icon'=>'','dateFormatted'=>mysql2date((string)get_option('date_format','d.m.Y'),$attachment->post_date),
        'nonces'=>['update'=>false,'delete'=>false,'edit'=>false],'editLink'=>false,'meta'=>false];
    if(current_user_can('edit_post',$attachment->ID)){ $r['nonces']['update']=wp_create_nonce('update-post_'.$attachment->ID);$r['nonces']['edit']=wp_create_nonce('image_editor-'.$attachment->ID); }
    if(current_user_can('delete_post',$attachment->ID))$r['nonces']['delete']=wp_create_nonce('delete-post_'.$attachment->ID);
    if($u=get_userdata((int)$attachment->post_author)){ $r['authorName']=html_entity_decode($u->display_name,ENT_QUOTES,get_bloginfo('charset')); }
    $f=get_attached_file($attachment->ID);if($f&&is_file($f))$r['filesizeInBytes']=filesize($f);$r['filesizeHumanReadable']=isset($r['filesizeInBytes'])?size_format($r['filesizeInBytes']):'';
    if($type==='image'&&is_array($meta)&&isset($meta['width'])){
        $sizes=[];$base=$url!==''?dirname($url).'/':'';
        foreach((array)($meta['sizes']??[]) as $k=>$s){ if(empty($s['file']))continue;$sizes[$k]=['height'=>$s['height'],'width'=>$s['width'],'url'=>$base.$s['file'],'orientation'=>$s['height']>$s['width']?'portrait':'landscape']; }
        $sizes['full']=['url'=>$url,'height'=>$meta['height'],'width'=>$meta['width'],'orientation'=>$meta['height']>$meta['width']?'portrait':'landscape'];
        $r=array_merge($r,['sizes'=>$sizes,'width'=>$meta['width'],'height'=>$meta['height'],'orientation'=>$meta['height']>$meta['width']?'portrait':'landscape']);
    }
    if(in_array($type,['audio','video'],true)&&is_array($meta)){ $r['meta']=array_intersect_key($meta,array_flip(['artist','album','length','length_formatted','genre','year']));
        if($type==='video'){ $r['width']=$meta['width']??0;$r['height']=$meta['height']??0; } }
    $r['compat']=['item'=>'','meta'=>''];
    return apply_filters('wp_prepare_attachment_for_js',$r,$attachment,$meta);
} }

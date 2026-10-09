<?php
// Ergänzende Medien-Funktionen (Shortcodes): [caption], [gallery], [playlist], [audio], [video] sowie die Underscore-Vorlagen für Wiedergabelisten.
// Die vorhandenen vereinfachten Shortcodes [caption]/[audio]/[video] bleiben aktiv; [gallery] und [playlist] werden beim Start (init) eingetragen.

if(!function_exists('wp_get_audio_extensions')){ function wp_get_audio_extensions() { return apply_filters('wp_audio_extensions',['mp3','ogg','flac','m4a','wav']); } }
if(!function_exists('wp_get_video_extensions')){ function wp_get_video_extensions() { return apply_filters('wp_video_extensions',['mp4','m4v','webm','ogv','flv']); } }
if(!function_exists('wp_mediaelement_fallback')){ function wp_mediaelement_fallback($url) { return apply_filters('wp_mediaelement_fallback','<a href="'.esc_url($url).'">'.esc_html($url).'</a>',$url); } }
if(!function_exists('wp_get_attachment_id3_keys')){ function wp_get_attachment_id3_keys($attachment,$context='display') {
    $f=['artist'=>'Interpret','album'=>'Album'];
    if($context==='display'){ $f['genre']='Genre';$f['year']='Jahr';$f['length_formatted']='Länge'; }
    elseif($context==='js'){ $f['bitrate']='Bitrate';$f['bitrate_mode']='Bitratenmodus'; }
    return apply_filters('wp_get_attachment_id3_keys',$f,$attachment,$context);
} }

if(!function_exists('img_caption_shortcode')){ function img_caption_shortcode($attr,$content='') {
    $atts=shortcode_atts(['id'=>'','caption_id'=>'','align'=>'alignnone','width'=>'','caption'=>'','class'=>''],$attr,'caption');
    $atts['width']=(int)$atts['width'];
    $out=apply_filters('img_caption_shortcode','',$attr,$content);if(!empty($out))return $out;
    if($atts['width']<1||empty($atts['caption']))return (string)$content;
    $id=$cid=$desc='';
    if($atts['id']){ $atts['id']=sanitize_html_class($atts['id']);$id='id="'.esc_attr($atts['id']).'" '; }
    $class=trim('wp-caption '.$atts['align'].' '.$atts['class']);$html5=current_theme_supports('html5','caption');
    if($html5&&$atts['id']){ $cid='id="figcaption_'.esc_attr($atts['id']).'" ';$desc='aria-describedby="figcaption_'.esc_attr($atts['id']).'" '; }
    $width=$html5?$atts['width']:10+$atts['width'];   // HTML5 fügt die 10 px nicht hinzu
    $cw=apply_filters('img_caption_shortcode_width',$width,$atts,$content);$style=$cw?'style="width: '.(int)$cw.'px" ':'';
    if($html5)return sprintf('<figure %s%s%sclass="%s">%s%s</figure>',$id,$desc,$style,esc_attr($class),do_shortcode($content),sprintf('<figcaption %sclass="wp-caption-text">%s</figcaption>',$cid,$atts['caption']));
    return sprintf('<div %s%sclass="%s">%s%s</div>',$id,$style,esc_attr($class),do_shortcode($content),sprintf('<p %sclass="wp-caption-text">%s</p>',$cid,$atts['caption']));
} }

if(!function_exists('gallery_shortcode')){ function gallery_shortcode($attr) {
    $post=get_post();static $instance=0;$instance++;
    if(!empty($attr['ids'])){ if(empty($attr['orderby']))$attr['orderby']='post__in';$attr['include']=$attr['ids']; }
    $out=apply_filters('post_gallery','',$attr,$instance);if(!empty($out))return $out;
    $html5=current_theme_supports('html5','gallery');
    $atts=shortcode_atts(['order'=>'ASC','orderby'=>'menu_order ID','id'=>$post?$post->ID:0,'itemtag'=>$html5?'figure':'dl','icontag'=>$html5?'div':'dt','captiontag'=>$html5?'figcaption':'dd','columns'=>3,'size'=>'thumbnail','include'=>'','exclude'=>'','link'=>''],$attr,'gallery');
    $pid=(int)$atts['id'];$base=['post_status'=>'inherit','post_type'=>'attachment','post_mime_type'=>'image','order'=>$atts['order'],'orderby'=>$atts['orderby']];
    if(!empty($atts['include'])){ $att=[];foreach(get_posts($base+['include'=>$atts['include']]) as $p)$att[$p->ID]=$p; }
    elseif(!empty($atts['exclude']))$att=_elvado_m_by_id(get_children($base+['post_parent'=>$pid,'exclude'=>$atts['exclude']]));
    else $att=_elvado_m_by_id(get_children($base+['post_parent'=>$pid]));
    if(empty($att))return '';
    if(is_feed()){ $o="\n";foreach($att as $aid=>$a)$o.=_elvado_m_attachment_link($aid,$atts['size'],true)."\n";return $o; }
    $itemtag=tag_escape($atts['itemtag']);$icontag=tag_escape($atts['icontag']);$captiontag=tag_escape($atts['captiontag']);
    // ungültige Tags auf Standardwerte zurücksetzen
    if(!in_array($itemtag,['dl','figure','div','section','article','p','ul','li'],true))$itemtag=$html5?'figure':'dl';
    if(!in_array($icontag,['dt','div','span','p'],true))$icontag=$html5?'div':'dt';
    if($captiontag!==''&&!in_array($captiontag,['dd','figcaption','div','span','p'],true))$captiontag=$html5?'figcaption':'dd';
    $columns=(int)$atts['columns'];$iw=$columns>0?floor(100/$columns):100;$float=is_rtl()?'right':'left';$sel="gallery-{$instance}";
    $style='';
    if(apply_filters('use_default_gallery_style',!$html5)){
        $style="<style>\n\t\t\t#{$sel} {\n\t\t\t\tmargin: auto;\n\t\t\t}\n\t\t\t#{$sel} .gallery-item {\n\t\t\t\tfloat: {$float};\n\t\t\t\tmargin-top: 10px;\n\t\t\t\ttext-align: center;\n\t\t\t\twidth: {$iw}%;\n\t\t\t}\n\t\t\t#{$sel} img {\n\t\t\t\tborder: 2px solid #cfcfcf;\n\t\t\t}\n\t\t\t#{$sel} .gallery-caption {\n\t\t\t\tmargin-left: 0;\n\t\t\t}\n\t\t</style>\n\t\t";
    }
    $sc=sanitize_html_class(is_array($atts['size'])?implode('x',$atts['size']):$atts['size']);
    $o=apply_filters('gallery_style',$style."<div id='$sel' class='gallery galleryid-{$pid} gallery-columns-{$columns} gallery-size-{$sc}'>");
    $i=0;
    foreach($att as $aid=>$a){
        $at=trim((string)$a->post_excerpt)?['aria-describedby'=>"$sel-$aid"]:'';
        if(!empty($atts['link'])&&$atts['link']==='file')$img=_elvado_m_attachment_link($aid,$atts['size'],false,false,false,$at);
        elseif(!empty($atts['link'])&&$atts['link']==='none')$img=_elvado_m_attachment_image($aid,$atts['size'],false,$at);
        else $img=_elvado_m_attachment_link($aid,$atts['size'],true,false,false,$at);
        $m=wp_get_attachment_metadata($aid);$ori='';if(isset($m['height'],$m['width']))$ori=$m['height']>$m['width']?'portrait':'landscape';
        $o.="<{$itemtag} class='gallery-item'><{$icontag} class='gallery-icon {$ori}'>$img</{$icontag}>";
        if($captiontag&&trim((string)$a->post_excerpt))$o.="<{$captiontag} class='wp-caption-text gallery-caption' id='$sel-$aid'>".wptexturize($a->post_excerpt)."</{$captiontag}>";
        $o.="</{$itemtag}>";
        if(!$html5&&$columns>0&&++$i%$columns===0)$o.='<br style="clear: both" />';
    }
    if(!$html5&&$columns>0&&$i%$columns!==0)$o.="<br style='clear: both' />";
    return $o."</div>\n";
} }

if(!function_exists('wp_audio_shortcode')){ function wp_audio_shortcode($attr,$content='') {
    $post_id=get_post()?get_the_ID():0;static $instance=0;$instance++;
    $override=apply_filters('wp_audio_shortcode_override','',$attr,$content,$instance);if(!empty($override))return $override;
    $audio=null;$types=wp_get_audio_extensions();$d=['src'=>'','loop'=>'','autoplay'=>'','preload'=>'none','class'=>'wp-audio-shortcode','style'=>'width: 100%;'];
    foreach($types as $t)$d[$t]='';
    $atts=shortcode_atts($d,$attr,'audio');$primary=false;
    if(!empty($atts['src'])){
        $ft=wp_check_filetype($atts['src'],wp_get_mime_types());
        if(!in_array(strtolower((string)$ft['ext']),$types,true))return sprintf('<a class="wp-embedded-audio" href="%s">%s</a>',esc_url($atts['src']),esc_html($atts['src']));
        $primary=true;array_unshift($types,'src');
    } else foreach($types as $ext)if(!empty($atts[$ext])){ $ft=wp_check_filetype($atts[$ext],wp_get_mime_types());if(strtolower((string)$ft['ext'])===$ext)$primary=true; }
    if(!$primary){ $a=get_attached_media('audio',$post_id);if(empty($a))return '';$audio=reset($a);$atts['src']=wp_get_attachment_url($audio->ID);if(empty($atts['src']))return '';array_unshift($types,'src'); }
    $library=apply_filters('wp_audio_shortcode_library','mediaelement');
    if($library==='mediaelement'&&did_action('init')){ wp_enqueue_style('wp-mediaelement');wp_enqueue_script('wp-mediaelement'); }
    $ha=['class'=>apply_filters('wp_audio_shortcode_class',$atts['class'],$atts),'id'=>sprintf('audio-%d-%d',$post_id,$instance),'loop'=>wp_validate_boolean($atts['loop']),'autoplay'=>wp_validate_boolean($atts['autoplay']),'preload'=>$atts['preload'],'style'=>$atts['style']];
    foreach(['loop','autoplay','preload'] as $k)if(empty($ha[$k]))unset($ha[$k]);
    $as=[];foreach($ha as $k=>$v)$as[]=$k.'="'.esc_attr($v).'"';
    $html=sprintf('<audio %s controls="controls">',implode(' ',$as));$file='';
    foreach($types as $fb)if(!empty($atts[$fb])){ if($file==='')$file=$atts[$fb];$ft=wp_check_filetype($atts[$fb],wp_get_mime_types());$html.=sprintf('<source type="%s" src="%s" />',$ft['type'],esc_url(add_query_arg('_',$instance,$atts[$fb]))); }
    if($library==='mediaelement')$html.=wp_mediaelement_fallback($file);
    $html.='</audio>';
    return apply_filters('wp_audio_shortcode',$html,$atts,$audio,$post_id,$library);
} }

if(!function_exists('wp_video_shortcode')){ function wp_video_shortcode($attr,$content='') {
    global $content_width;
    $post_id=get_post()?get_the_ID():0;static $instance=0;$instance++;
    $override=apply_filters('wp_video_shortcode_override','',$attr,$content,$instance);if(!empty($override))return $override;
    $video=null;$types=wp_get_video_extensions();
    $d=['src'=>'','poster'=>'','loop'=>'','autoplay'=>'','muted'=>'false','preload'=>'metadata','width'=>640,'height'=>360,'class'=>'wp-video-shortcode'];
    foreach($types as $t)$d[$t]='';
    $atts=shortcode_atts($d,$attr,'video');
    if(is_admin()){ if($atts['width']>$d['width']){ $atts['height']=round(($atts['height']*$d['width'])/$atts['width']);$atts['width']=$d['width']; } }
    elseif(!empty($content_width)&&$atts['width']>$content_width){ $atts['height']=round(($atts['height']*$content_width)/$atts['width']);$atts['width']=$content_width; }
    $vimeo=$yt=false;$primary=false;
    if(!empty($atts['src'])){
        $vimeo=(bool)preg_match('#^https?://(.+\.)?vimeo\.com/.*#',$atts['src']);$yt=(bool)preg_match('#^https?://(?:www\.)?(?:youtube\.com/watch|youtu\.be/)#',$atts['src']);
        if(!$yt&&!$vimeo){ $ft=wp_check_filetype($atts['src'],wp_get_mime_types());if(!in_array(strtolower((string)$ft['ext']),$types,true))return sprintf('<a class="wp-embedded-video" href="%s">%s</a>',esc_url($atts['src']),esc_html($atts['src'])); }
        $primary=true;array_unshift($types,'src');
    } else foreach($types as $ext)if(!empty($atts[$ext])){ $ft=wp_check_filetype($atts[$ext],wp_get_mime_types());if(strtolower((string)$ft['ext'])===$ext)$primary=true; }
    if(!$primary){ $v=get_attached_media('video',$post_id);if(empty($v))return '';$video=reset($v);$atts['src']=wp_get_attachment_url($video->ID);if(empty($atts['src']))return '';array_unshift($types,'src'); }
    $library=apply_filters('wp_video_shortcode_library','mediaelement');
    if($library==='mediaelement'&&did_action('init')){ wp_enqueue_style('wp-mediaelement');wp_enqueue_script('wp-mediaelement'); }
    if($library==='mediaelement'){
        if($yt)$atts['src']=set_url_scheme(remove_query_arg('feature',$atts['src']),'https');
        elseif($vimeo){ $pu=wp_parse_url($atts['src']);$atts['src']=add_query_arg('loop',$atts['loop']?'1':'0','https://'.$pu['host'].($pu['path']??'')); }
    }
    $ha=['class'=>apply_filters('wp_video_shortcode_class',$atts['class'],$atts),'id'=>sprintf('video-%d-%d',$post_id,$instance),'width'=>absint($atts['width']),'height'=>absint($atts['height']),'poster'=>esc_url($atts['poster']),
        'loop'=>wp_validate_boolean($atts['loop']),'autoplay'=>wp_validate_boolean($atts['autoplay']),'muted'=>wp_validate_boolean($atts['muted']),'preload'=>$atts['preload']];
    foreach(['poster','loop','autoplay','preload','muted'] as $k)if(empty($ha[$k]))unset($ha[$k]);
    $as=[];foreach($ha as $k=>$v)$as[]=$k.'="'.esc_attr($v).'"';
    $html=sprintf('<video %s controls="controls">',implode(' ',$as));$file='';
    foreach($types as $fb)if(!empty($atts[$fb])){
        if($file==='')$file=$atts[$fb];
        $ft=$fb==='src'&&$yt?['type'=>'video/youtube']:($fb==='src'&&$vimeo?['type'=>'video/vimeo']:wp_check_filetype($atts[$fb],wp_get_mime_types()));
        $html.=sprintf('<source type="%s" src="%s" />',$ft['type'],esc_url(add_query_arg('_',$instance,$atts[$fb])));
    }
    if(!empty($content)){ if(str_contains($content,"\n"))$content=str_replace(["\r\n","\n","\t"],'',$content);$html.=trim($content); }
    if($library==='mediaelement')$html.=wp_mediaelement_fallback($file);
    $html.='</video>';
    $out=sprintf('<div style="%s" class="wp-video">%s</div>',!empty($atts['width'])?sprintf('width: %dpx;',$atts['width']):'',$html);
    return apply_filters('wp_video_shortcode',$out,$atts,$video,$post_id,$library);
} }

if(!function_exists('wp_playlist_scripts')){ function wp_playlist_scripts($type) {
    wp_enqueue_style('wp-mediaelement');wp_enqueue_script('wp-playlist');
    add_action('wp_footer','wp_underscore_playlist_templates',0);add_action('admin_footer','wp_underscore_playlist_templates',0);
} }
if(!function_exists('wp_playlist_shortcode')){ function wp_playlist_shortcode($attr) {
    global $content_width;$post=get_post();static $instance=0;$instance++;
    if(!empty($attr['ids'])){ if(empty($attr['orderby']))$attr['orderby']='post__in';$attr['include']=$attr['ids']; }
    $out=apply_filters('post_playlist','',$attr,$instance);if(!empty($out))return $out;
    $atts=shortcode_atts(['type'=>'audio','order'=>'ASC','orderby'=>'menu_order ID','id'=>$post?$post->ID:0,'include'=>'','exclude'=>'','style'=>'light','tracklist'=>true,'tracknumbers'=>true,'images'=>true,'artists'=>true,'tracknames'=>true],$attr,'playlist');
    $id=(int)$atts['id'];if($atts['type']!=='audio')$atts['type']='video';
    $args=['post_status'=>'inherit','post_type'=>'attachment','post_mime_type'=>$atts['type'],'order'=>$atts['order'],'orderby'=>$atts['orderby']];
    if(!empty($atts['include'])){ $att=[];foreach(get_posts($args+['include'=>$atts['include']]) as $p)$att[$p->ID]=$p; }
    elseif(!empty($atts['exclude']))$att=_elvado_m_by_id(get_children($args+['post_parent'=>$id,'exclude'=>$atts['exclude']]));
    else $att=_elvado_m_by_id(get_children($args+['post_parent'=>$id]));
    if(empty($att))return '';
    if(is_feed()){ $o="\n";foreach($att as $aid=>$a)$o.=_elvado_m_attachment_link($aid)."\n";return $o; }
    $dw=640;$dh=360;$tw=empty($content_width)?$dw:$content_width-22;$th=empty($content_width)?$dh:round(($dh*$tw)/$dw);
    $data=['type'=>$atts['type'],'tracklist'=>wp_validate_boolean($atts['tracklist']),'tracknumbers'=>wp_validate_boolean($atts['tracknumbers']),'images'=>wp_validate_boolean($atts['images']),'artists'=>wp_validate_boolean($atts['artists']),'tracks'=>[]];
    foreach($att as $a){
        $url=wp_get_attachment_url($a->ID);$ft=wp_check_filetype((string)$url,wp_get_mime_types());
        $t=['src'=>$url,'type'=>$ft['type'],'title'=>$a->post_title,'caption'=>$a->post_excerpt,'description'=>$a->post_content,'meta'=>[]];
        $m=wp_get_attachment_metadata($a->ID);
        if(!empty($m)){ foreach(wp_get_attachment_id3_keys($a) as $k=>$_)if(!empty($m[$k]))$t['meta'][$k]=$m[$k];
            if($atts['type']==='video'){ if(!empty($m['width'])&&!empty($m['height'])){ $w=$m['width'];$h=$m['height'];$th=round(($h*$tw)/$w); }else{ $w=$dw;$h=$dh; }
                $t['dimensions']=['original'=>['width'=>$w,'height'=>$h],'resized'=>['width'=>$tw,'height'=>$th]]; } }
        if($data['images']){ $tid=get_post_thumbnail_id($a->ID);$img=$tid?wp_get_attachment_image_src($tid,'full'):false;
            if($img){ $t['image']=['src'=>$img[0],'width'=>$img[1],'height'=>$img[2]];$th2=wp_get_attachment_image_src($tid,'thumbnail')?:$img;$t['thumb']=['src'=>$th2[0],'width'=>$th2[1],'height'=>$th2[2]]; }
            else { $src=function_exists('wp_mime_type_icon')?(string)wp_mime_type_icon($a->ID,'.svg'):'';$t['image']=$t['thumb']=['src'=>$src,'width'=>48,'height'=>64]; } }
        $data['tracks'][]=$t;
    }
    $ty=esc_attr($atts['type']);$st=esc_attr($atts['style']);ob_start();
    if($instance===1)do_action('wp_playlist_scripts',$atts['type'],$atts['style']);
    echo '<div class="wp-playlist wp-'.$ty.'-playlist wp-playlist-'.$st.'">';
    if($atts['type']==='audio')echo '<div class="wp-playlist-current-item"></div>';
    echo '<'.$ty.' controls="controls" preload="none" width="'.(int)$tw.'"'.($ty==='video'?' height="'.(int)$th.'"':'').'></'.$ty.'>';
    echo '<div class="wp-playlist-next"></div><div class="wp-playlist-prev"></div><noscript><ol>';
    foreach($att as $aid=>$a)printf('<li>%s</li>',_elvado_m_attachment_link($aid));
    echo '</ol></noscript><script type="application/json" class="wp-playlist-script">'.wp_json_encode($data).'</script></div>';
    return ob_get_clean();
} }

/* ───────── Underscore-Vorlagen (Medien-Frontend) ───────── */
if(!function_exists('wp_underscore_playlist_templates')){ function wp_underscore_playlist_templates() { ?>
<script type="text/html" id="tmpl-wp-playlist-current-item">
	<# if ( data.image ) { #>
	<img src="{{ data.thumb.src }}" alt="" />
	<# } #>
	<div class="wp-playlist-caption">
		<span class="wp-playlist-item-meta wp-playlist-item-title">
			<# if ( data.meta.album || data.meta.artist ) { #>
				&#8220;{{ data.title }}&#8221;
			<# } else { #>
				{{ data.title }}
			<# } #>
		</span>
		<# if ( data.meta.album ) { #><span class="wp-playlist-item-meta wp-playlist-item-album">{{ data.meta.album }}</span><# } #>
		<# if ( data.meta.artist ) { #><span class="wp-playlist-item-meta wp-playlist-item-artist">{{ data.meta.artist }}</span><# } #>
	</div>
</script>
<script type="text/html" id="tmpl-wp-playlist-item">
	<div class="wp-playlist-item">
		<a class="wp-playlist-caption" href="{{ data.src }}">
			{{ data.index ? ( data.index + '. ' ) : '' }}
			<# if ( data.caption ) { #>
				{{ data.caption }}
			<# } else { #>
				<# if ( data.artists && data.meta.artist ) { #>
				<span class="wp-playlist-item-title">&#8220;{{{ data.title }}}&#8221;</span>
				<span class="wp-playlist-item-artist"> &mdash; {{ data.meta.artist }}</span>
				<# } else { #>
				<span class="wp-playlist-item-title">{{{ data.title }}}</span>
				<# } #>
			<# } #>
		</a>
		<# if ( data.meta.length_formatted ) { #>
		<div class="wp-playlist-item-length">{{ data.meta.length_formatted }}</div>
		<# } #>
	</div>
</script>
<?php } }
if(!function_exists('wp_underscore_audio_template')){ function wp_underscore_audio_template() { $types=wp_get_audio_extensions(); ?>
<audio style="visibility: hidden" controls class="wp-audio-shortcode" width="{{ _.isUndefined( data.model.width ) ? 400 : data.model.width }}" preload="{{ _.isUndefined( data.model.preload ) ? 'none' : data.model.preload }}"
	<# if ( ! _.isUndefined( data.model.autoplay ) && data.model.autoplay ) { #> autoplay<# } #>
	<# if ( ! _.isUndefined( data.model.loop ) && data.model.loop ) { #> loop<# } #>
>
	<# if ( ! _.isEmpty( data.model.src ) ) { #><source src="{{ data.model.src }}" type="{{ wp.media.view.settings.embedMimes[ data.model.src.split('.').pop() ] }}" /><# } #>
<?php foreach($types as $t){ ?>
	<# if ( ! _.isEmpty( data.model.<?php echo esc_js($t); ?> ) ) { #><source src="{{ data.model.<?php echo esc_js($t); ?> }}" type="{{ wp.media.view.settings.embedMimes[ '<?php echo esc_js($t); ?>' ] }}" /><# } #>
<?php } ?>
</audio>
<?php } }
if(!function_exists('wp_underscore_video_template')){ function wp_underscore_video_template() { $types=wp_get_video_extensions(); ?>
<# var w = _.isUndefined( data.model.width ) ? 640 : data.model.width, h = _.isUndefined( data.model.height ) ? 360 : data.model.height; #>
<div style="max-width: 100%; width: {{ w }}px">
<video controls class="wp-video-shortcode" width="{{ w }}" height="{{ h }}" preload="{{ _.isUndefined( data.model.preload ) ? 'metadata' : data.model.preload }}"
	<# if ( ! _.isUndefined( data.model.poster ) && data.model.poster ) { #> poster="{{ data.model.poster }}"<# } #>
	<# if ( ! _.isUndefined( data.model.autoplay ) && data.model.autoplay ) { #> autoplay<# } #>
	<# if ( ! _.isUndefined( data.model.loop ) && data.model.loop ) { #> loop<# } #>
>
	<# if ( ! _.isEmpty( data.model.src ) ) { #><source src="{{ data.model.src }}" type="{{ wp.media.view.settings.embedMimes[ data.model.src.split('.').pop() ] }}" /><# } #>
<?php foreach($types as $t){ ?>
	<# if ( ! _.isEmpty( data.model.<?php echo esc_js($t); ?> ) ) { #><source src="{{ data.model.<?php echo esc_js($t); ?> }}" type="{{ wp.media.view.settings.embedMimes[ '<?php echo esc_js($t); ?>' ] }}" /><# } #>
<?php } ?>
	{{{ data.model.content }}}
</video>
</div>
<?php } }

/** Standard-Haken wie in WordPress: [gallery]/[playlist] eintragen (nur wenn nicht belegt), Playlist-Skripte, Bildunterschrift, Datenschutz-Export. */
if(!function_exists('_elvado_m_media_boot')){ function _elvado_m_media_boot() {
    foreach(['gallery'=>'gallery_shortcode','playlist'=>'wp_playlist_shortcode'] as $t=>$cb)if(!shortcode_exists($t))add_shortcode($t,$cb);
    add_action('wp_playlist_scripts','wp_playlist_scripts');
    add_filter('image_send_to_editor','image_add_caption',20,8);
    add_filter('wp_privacy_personal_data_exporters','wp_register_media_personal_data_exporter',10);
}
if(function_exists('add_action'))add_action('init','_elvado_m_media_boot',1); }

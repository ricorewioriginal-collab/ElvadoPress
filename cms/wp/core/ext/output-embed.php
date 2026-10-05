<?php
// Ergänzende Einbettungs-Funktionen (Bereich Ausgabe): oEmbed-Anbieterliste, eigene Beiträge als oEmbed-Antwort, Einbettungs-Vorlage (Embed-Ausgaben).
// Es gibt keinen Netzzugriff beim Einbetten fremder Dienste; YouTube-Adressen und Audio/Video-Dateien werden lokal in HTML umgesetzt.

if(!function_exists('rrw_ext_oembed_providers')){
    /** Anbieterliste (Format ⇒ [Endpunkt, regex?]); wird erst beim ersten Zugriff aufgebaut. */
    function &rrw_ext_oembed_providers() {
        if(!isset($GLOBALS['rrw_wp_oembed_providers'])){
            $GLOBALS['rrw_wp_oembed_providers']=[
                '#https?://((m|www)\.)?youtube\.com/watch.*#i'=>['https://www.youtube.com/oembed',true],'#https?://((m|www)\.)?youtube\.com/playlist.*#i'=>['https://www.youtube.com/oembed',true],'#https?://youtu\.be/.*#i'=>['https://www.youtube.com/oembed',true],
                '#https?://(.+\.)?vimeo\.com/.*#i'=>['https://vimeo.com/api/oembed.{format}',true],'#https?://(www\.)?dailymotion\.com/.*#i'=>['https://www.dailymotion.com/services/oembed',true],
                '#https?://(www\.)?flickr\.com/.*#i'=>['https://www.flickr.com/services/oembed/',true],'#https?://(.+\.)?soundcloud\.com/.*#i'=>['https://soundcloud.com/oembed',true],
                '#https?://(open|play)\.spotify\.com/.*#i'=>['https://embed.spotify.com/oembed/',true],'#https?://(www\.)?mixcloud\.com/.*#i'=>['https://app.mixcloud.com/oembed/',true],
                '#https?://(www\.)?(twitter|x)\.com/\w{1,15}/status(es)?/.*#i'=>['https://publish.twitter.com/oembed',true],'#https?://(www\.)?tiktok\.com/.*/video/.*#i'=>['https://www.tiktok.com/oembed',true],
                '#https?://(www\.)?reddit\.com/r/[^/]+/comments/.*#i'=>['https://www.reddit.com/oembed',true],'#https?://(www\.)?instagram\.com/(p|tv|reel)/.*#i'=>['https://graph.facebook.com/v5.0/instagram_oembed',true],
                '#https?://(www\.)?ted\.com/talks/.*#i'=>['https://www.ted.com/services/v1/oembed.{format}',true],'#https?://(www\.)?slideshare\.net/.*#i'=>['https://www.slideshare.net/api/oembed/2',true],
                '#https?://(www\.)?speakerdeck\.com/.*#i'=>['https://speakerdeck.com/oembed.json',true],'#https?://(.+\.)?wordpress\.tv/.*#i'=>['https://wordpress.tv/oembed/',true],
                '#https?://(www\.)?scribd\.com/doc/.*#i'=>['https://www.scribd.com/services/oembed',true],'#https?://(www\.)?issuu\.com/.+/docs/.*#i'=>['https://issuu.com/oembed_wp',true],
            ];
        }
        return $GLOBALS['rrw_wp_oembed_providers'];
    }
}
if(!function_exists('wp_oembed_add_provider')){
    /** Anbieter hinzufügen: $format mit * als Platzhalter (oder Regex, wenn $regex true), $provider = Endpunkt. */
    function wp_oembed_add_provider($format, $provider, $regex=false) { $p=&rrw_ext_oembed_providers();$p[$format]=[$provider,(bool)$regex]; }
}
if(!function_exists('rrw_ext_oembed_provider_for')){
    /** Endpunkt des ersten passenden Anbieters für eine Adresse (oder false). */
    function rrw_ext_oembed_provider_for($url) {
        foreach(rrw_ext_oembed_providers() as $mask=>[$endpoint,$regex]){
            $re=$regex?$mask:'#'.str_replace('___w___','(.+)',preg_quote(str_replace('*','___w___',$mask),'#')).'#i';
            if(@preg_match($re,(string)$url))return $endpoint;
        }
        return false;
    }
}
if(!function_exists('wp_oembed_remove_provider')){
    function wp_oembed_remove_provider($format) { $p=&rrw_ext_oembed_providers();if(isset($p[$format])){ unset($p[$format]);return true; }return false; }
}
if(!function_exists('wp_maybe_load_embeds')){
    function wp_maybe_load_embeds() {
        if(!apply_filters('load_default_embeds',true))return;
        wp_embed_register_handler('youtube_embed_url','#https?://(www.)?youtube\.com/(?:v|embed)/([^/]+)#i','wp_embed_handler_youtube');
        wp_embed_register_handler('audio','#^https?://.+?\.(mp3|m4a|ogg|wav|wma)$#i',apply_filters('wp_audio_embed_handler','wp_embed_handler_audio'),9999);
        wp_embed_register_handler('video','#^https?://.+?\.(mp4|m4v|webm|ogv|flv)$#i',apply_filters('wp_video_embed_handler','wp_embed_handler_video'),9999);
    }
}
if(!function_exists('wp_embed_handler_youtube')){
    function wp_embed_handler_youtube($matches, $attr, $url, $rawattr) {
        $d=wp_embed_defaults($url);$w=(int)($attr['width']??$d['width']);$h=(int)($attr['height']??$d['height']);
        $html=sprintf('<iframe title="YouTube-Video" width="%d" height="%d" src="%s" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>',$w,$h,esc_url('https://www.youtube.com/embed/'.rawurlencode($matches[2])));
        return apply_filters('wp_embed_handler_youtube',$html,$attr,$url,$rawattr);
    }
}
if(!function_exists('wp_embed_handler_audio')){ function wp_embed_handler_audio($matches, $attr, $url, $rawattr) { return apply_filters('wp_embed_handler_audio',sprintf('[audio src="%s" /]',esc_url($url)),$attr,$url,$rawattr); } }
if(!function_exists('wp_embed_handler_video')){
    function wp_embed_handler_video($matches, $attr, $url, $rawattr) {
        $dim='';if(!empty($rawattr['width'])&&!empty($rawattr['height']))$dim=sprintf('width="%d" height="%d" ',(int)$rawattr['width'],(int)$rawattr['height']);
        return apply_filters('wp_embed_handler_video',sprintf('[video %ssrc="%s" /]',$dim,esc_url($url)),$attr,$url,$rawattr);
    }
}
if(!function_exists('wp_oembed_ensure_format')){ function wp_oembed_ensure_format($format) { return in_array($format,['json','xml'],true)?$format:'json'; } }
if(!function_exists('wp_oembed_add_host_js')){ function wp_oembed_add_host_js() { add_filter('embed_oembed_html','wp_maybe_enqueue_oembed_host_js'); } }
if(!function_exists('wp_maybe_enqueue_oembed_host_js')){ function wp_maybe_enqueue_oembed_host_js($html) { return $html; } }   // das Host-Skript (wp-embed.js) liefert die Schicht nicht

/* ───────── Eigene Beiträge als oEmbed ───────── */
if(!function_exists('get_post_embed_url')){
    /** Adresse der Einbettungs-Ansicht eines Beitrags (…/?embed=true). */
    function get_post_embed_url($post=null) {
        $post=get_post($post);if(!$post)return false;
        return apply_filters('post_embed_url',add_query_arg(['embed'=>'true'],get_permalink($post)),$post);
    }
}
if(!function_exists('get_oembed_endpoint_url')){
    function get_oembed_endpoint_url($permalink='', $format='json') {
        $url=rest_url('oembed/1.0/embed');
        if($permalink!=='')$url=add_query_arg(['url'=>urlencode($permalink),'format'=>$format!=='json'?$format:false],$url);
        return apply_filters('oembed_endpoint_url',$url,$permalink,$format);
    }
}
if(!function_exists('get_post_embed_html')){
    /** Einbettungscode (Zitat + iframe) für einen Beitrag; ohne das Größen-Skript von WordPress (Höhe bleibt fest). */
    function get_post_embed_html($width, $height, $post=null) {
        $post=get_post($post);if(!$post)return false;
        $secret=wp_generate_password(10,false);$title=get_the_title($post);
        $out='<blockquote class="wp-embedded-content" data-secret="'.esc_attr($secret).'"><a href="'.esc_url(get_permalink($post)).'">'.$title.'</a></blockquote>';
        $out.=sprintf('<iframe sandbox="allow-scripts" security="restricted" src="%1$s" width="%2$d" height="%3$d" title="%4$s" data-secret="%5$s" frameborder="0" marginwidth="0" marginheight="0" scrolling="no" class="wp-embedded-content"></iframe>',
            esc_url(get_post_embed_url($post).'#?secret='.$secret),absint($width),absint($height),esc_attr('„'.$title.'“ – '.get_bloginfo('name')),esc_attr($secret));
        return apply_filters('embed_html',$out,$post,$width,$height);
    }
}
if(!function_exists('get_oembed_response_data')){
    function get_oembed_response_data($post, $width) {
        $post=get_post($post);$width=absint($width);if(!$post||!is_post_status_viewable($post->post_status))return false;
        $mm=apply_filters('oembed_min_max_width',['min'=>200,'max'=>600]);$width=min(max($mm['min'],$width),$mm['max']);$height=max((int)ceil($width/16*9),200);
        $data=['version'=>'1.0','provider_name'=>get_bloginfo('name'),'provider_url'=>get_home_url(),'author_name'=>get_bloginfo('name'),'author_url'=>get_home_url(),'title'=>get_the_title($post),'type'=>'link'];
        $author=get_userdata((int)$post->post_author);
        if($author){ $data['author_name']=$author->display_name;$data['author_url']=get_author_posts_url($author->ID); }
        return apply_filters('oembed_response_data',$data,$post,$width,$height);
    }
}
if(!function_exists('get_oembed_response_data_for_url')){
    function get_oembed_response_data_for_url($url, $args) {
        $id=apply_filters('oembed_request_post_id',url_to_postid((string)$url),$url);
        return $id?get_oembed_response_data($id,(int)(((array)$args)['width']??600)):false;
    }
}
if(!function_exists('get_oembed_response_data_rich')){
    function get_oembed_response_data_rich($data, $post, $width, $height) {
        $data['width']=absint($width);$data['height']=absint($height);$data['type']='rich';$data['html']=get_post_embed_html($width,$height,$post);
        $tid=has_post_thumbnail($post->ID)?get_post_thumbnail_id($post->ID):false;
        if($post->post_type==='attachment'&&wp_attachment_is_image($post))$tid=$post->ID;
        if($tid&&($img=wp_get_attachment_image_src($tid,[$width,99999]))){ [$data['thumbnail_url'],$data['thumbnail_width'],$data['thumbnail_height']]=$img; }
        return $data;
    }
}
if(!function_exists('_oembed_create_xml')){
    function _oembed_create_xml($data, $node=null) {
        if(!is_array($data)||empty($data)||!class_exists('SimpleXMLElement'))return false;
        if($node===null)$node=new SimpleXMLElement('<oembed></oembed>');
        foreach($data as $k=>$v){
            if(is_numeric($k))$k='oembed';
            if(is_array($v))_oembed_create_xml($v,$node->addChild($k)); else $node->addChild($k,esc_html((string)$v));
        }
        return $node->asXML();
    }
}
if(!function_exists('_oembed_rest_pre_serve_request')){
    function _oembed_rest_pre_serve_request($served, $result, $request, $server) {
        if($request->get_route()!=='/oembed/1.0/embed'||$request->get_method()!=='GET'||($_GET['format']??'')!=='xml')return $served;
        $data=method_exists($server,'response_to_data')?$server->response_to_data($result,false):(method_exists($result,'get_data')?$result->get_data():(array)$result);
        $xml=_oembed_create_xml($data);
        if(!$xml){ status_header(501);return get_status_header_desc(501); }
        if(!headers_sent())header('Content-Type: text/xml; charset=UTF-8');
        echo $xml;return true;
    }
}
if(!function_exists('wp_oembed_register_route')){
    /** Registriert die REST-Route oembed/1.0/embed (Beitrag zu einer Adresse); nicht automatisch – Aufruf zu „rest_api_init“. */
    function wp_oembed_register_route() {
        register_rest_route('oembed/1.0','/embed',['methods'=>'GET','permission_callback'=>'__return_true','args'=>['url'=>['required'=>true,'type'=>'string'],'format'=>['default'=>'json'],'maxwidth'=>['default'=>600]],
            'callback'=>function($req) {
                $url=(string)$req->get_param('url');$w=(int)$req->get_param('maxwidth');
                $data=get_oembed_response_data_for_url($url,['width'=>$w?:600]);
                if(!$data)return new WP_Error('oembed_invalid_url','Nicht gefunden.',['status'=>404]);
                return $data;
            }]);
    }
}
if(!function_exists('wp_filter_oembed_iframe_title_attribute')){
    /** Setzt im iframe eines oEmbed-Ergebnisses das title-Attribut aus dem Titel der Antwort (falls es fehlt oder abweicht). */
    function wp_filter_oembed_iframe_title_attribute($result, $data, $url) {
        if($result===false||!is_string($result))return $result;
        $title=!empty($data->title)?(string)$data->title:'';
        $title=(string)apply_filters('oembed_iframe_title_attribute',$title,$result,$data,$url);
        if($title==='')return $result;
        if(preg_match('/<iframe[^>]*?\stitle=(["\'])(.*?)\1/i',$result,$m)){
            return $m[2]===$title||$m[2]===esc_attr($title)?$result:preg_replace('/(<iframe[^>]*?\stitle=)(["\']).*?\2/i','$1"'.str_replace(['\\','$'],['\\\\','\\$'],esc_attr($title)).'"',$result,1);
        }
        return str_ireplace('<iframe ','<iframe title="'.esc_attr($title).'" ',$result);
    }
}
if(!function_exists('wp_filter_pre_oembed_result')){
    /** Adressen der eigenen Website direkt aus dem Beitrag beantworten (kein HTTP-Aufruf). */
    function wp_filter_pre_oembed_result($result, $url, $args) {
        if(strtolower((string)wp_parse_url((string)$url,PHP_URL_HOST))!==strtolower((string)wp_parse_url(home_url(),PHP_URL_HOST)))return $result;
        $data=get_oembed_response_data_for_url($url,$args);if(!$data)return $result;
        $t=$data['type']??'link';
        if(($t==='rich'||$t==='video')&&!empty($data['html']))return $data['html'];
        if($t==='photo'&&!empty($data['url']))return '<img src="'.esc_url($data['url']).'" alt="'.esc_attr($data['title']??'').'" />';
        return '<a href="'.esc_url($url).'">'.esc_html($data['title']??$url).'</a>';
    }
}

/* ───────── Einbettungs-Ansicht ───────── */
if(!function_exists('wp_embed_excerpt_more')){
    function wp_embed_excerpt_more($more_string) {
        if(!is_embed())return $more_string;
        return ' &hellip; <a href="'.esc_url(get_permalink()).'" class="wp-embed-more" target="_top">Weiterlesen<span class="screen-reader-text"> '.get_the_title().'</span></a>';
    }
}
if(!function_exists('the_excerpt_embed')){ function the_excerpt_embed() { echo apply_filters('the_excerpt_embed',get_the_excerpt()); } }
if(!function_exists('wp_embed_excerpt_attachment')){ function wp_embed_excerpt_attachment($content) { return is_attachment()?prepend_attachment(''):$content; } }
if(!function_exists('_oembed_filter_feed_content')){
    function _oembed_filter_feed_content($content) {
        return str_replace('<iframe class="wp-embedded-content" sandbox="allow-scripts" security="restricted" style="position: absolute; clip: rect(1px, 1px, 1px, 1px);"','<iframe class="wp-embedded-content" sandbox="allow-scripts" security="restricted"',(string)$content);
    }
}
if(!function_exists('enqueue_embed_scripts')){ function enqueue_embed_scripts() { do_action('enqueue_embed_scripts'); } }
if(!function_exists('wp_enqueue_embed_styles')){
    function wp_enqueue_embed_styles() {
        if(!wp_style_is('wp-embed-template','registered'))wp_register_style('wp-embed-template',false);
        wp_add_inline_style('wp-embed-template','body{margin:0;font:14px/1.5 sans-serif}.wp-embed{padding:25px;border:1px solid rgba(128,128,128,.4);border-radius:4px}.wp-embed-heading{margin:0 0 .5em;font-size:1.4em}.wp-embed-footer{display:flex;justify-content:space-between;margin-top:1em}.screen-reader-text{position:absolute;clip:rect(1px,1px,1px,1px);width:1px;height:1px;overflow:hidden}');
        wp_enqueue_style('wp-embed-template');
    }
}
if(!function_exists('print_embed_scripts')){ function print_embed_scripts() { /* Standardwert: kein Größen-/Dialog-Skript (wp-embed-template.js wird nicht mitgeliefert) */ } }
if(!function_exists('print_embed_comments_button')){
    function print_embed_comments_button() {
        if(post_password_required())return;
        if(get_comments_number()||comments_open())echo '<div class="wp-embed-comments"><a href="'.esc_url(get_comments_link()).'" target="_top"><span class="dashicons dashicons-admin-comments"></span> '.number_format_i18n(get_comments_number()).'<span class="screen-reader-text"> Kommentare</span></a></div>';
    }
}
if(!function_exists('print_embed_sharing_button')){
    function print_embed_sharing_button() {
        if(is_404())return;
        echo '<div class="wp-embed-share"><button type="button" class="wp-embed-share-dialog-open" aria-label="Dialog zum Teilen öffnen"><span class="dashicons dashicons-share"></span></button></div>';
    }
}
if(!function_exists('print_embed_sharing_dialog')){
    function print_embed_sharing_dialog() {
        if(is_404())return;
        $url=get_permalink();
        echo '<div class="wp-embed-share-dialog hidden" role="dialog" aria-label="Teilen"><div class="wp-embed-share-dialog-content"><div class="wp-embed-share-tab" id="wp-embed-share-tab-wordpress"><input type="text" value="'.esc_url($url).'" class="wp-embed-share-input" readonly="readonly" aria-label="Adresse" /></div>'
            .'<div class="wp-embed-share-tab" id="wp-embed-share-tab-html"><textarea class="wp-embed-share-input" rows="4" readonly="readonly" aria-label="HTML-Code">'.esc_textarea((string)get_post_embed_html(600,400)).'</textarea></div></div>'
            .'<button type="button" class="wp-embed-share-dialog-close" aria-label="Dialog zum Teilen schließen"><span class="dashicons dashicons-no"></span></button></div>';
    }
}
if(!function_exists('the_embed_site_title')){
    function the_embed_site_title() {
        $icon=esc_url(get_site_icon_url(32,includes_url('images/w-logo-blue.png')));
        $t='<div class="wp-embed-site-title"><a href="'.esc_url(home_url()).'" target="_top"><img src="'.$icon.'" width="32" height="32" alt="" class="wp-embed-site-icon" /><span>'.esc_html(get_bloginfo('name')).'</span></a></div>';
        echo apply_filters('embed_site_title_html',$t);
    }
}

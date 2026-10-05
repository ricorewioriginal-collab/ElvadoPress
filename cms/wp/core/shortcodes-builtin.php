<?php
// Eingebaute Shortcodes: Brücke zu den CMS-Widgets (Forum, Netzwerk, Umfrage, FAQ …) und WordPress-Standard ([caption], [audio], [video]).
// Widgets werden als Platzhalter ausgegeben, den das Portal im Browser durch das echte Widget ersetzt (nur serverseitig geprüfte Einstellungen).

function rrw_sc_widget(string $type, array $settings=[], string $title=''): string {
    if(function_exists('rrw_widget_types')){
        if(!isset(rrw_widget_types()[$type]))return '';
        $settings=rrw_widget_settings_clean($type,$settings);
    }
    $w=['type'=>$type,'title'=>mb_substr($title,0,120),'settings'=>$settings];
    return '<div class="rrw-sc" data-rrw-widget="'.esc_attr(wp_json_encode($w,JSON_UNESCAPED_UNICODE)).'"></div>';
}
function rrw_sc_register_builtin(): void {
    $alias=['forum'=>'community-forum','community'=>'community-account','mitglieder'=>'community-account','netzwerk'=>'community-social','social'=>'community-social','video'=>null];
    foreach(['forum','community','mitglieder','netzwerk','social'] as $tag){
        add_shortcode($tag,function($atts) use($alias,$tag){ $a=shortcode_atts(['title'=>''],$atts,$tag); return rrw_sc_widget($alias[$tag],[],$a['title']); });
    }
    add_shortcode('umfrage',function($atts){ $a=shortcode_atts(['id'=>'','title'=>''],$atts,'umfrage'); return rrw_sc_widget('cms-poll',['poll_id'=>$a['id']],$a['title']); });
    add_shortcode('faq',function($atts,$content=null){ $a=shortcode_atts(['title'=>'','open_first'=>'0'],$atts,'faq'); $items=trim(wp_strip_all_tags(str_replace(['<br />','<br>','</p>'],"\n",(string)$content))); return rrw_sc_widget('faq',['items'=>$items,'open_first'=>$a['open_first']],$a['title']); });
    add_shortcode('countdown',function($atts){ $a=shortcode_atts(['target'=>'','label'=>'','done'=>'Es ist so weit!','title'=>''],$atts,'countdown'); return rrw_sc_widget('countdown',['target'=>$a['target'],'label'=>$a['label'],'done'=>$a['done']],$a['title']); });
    add_shortcode('karte',function($atts){ $a=shortcode_atts(['lat'=>'','lon'=>'','zoom'=>'14','height'=>'320','label'=>'','title'=>''],$atts,'karte'); return rrw_sc_widget('map',['lat'=>$a['lat'],'lon'=>$a['lon'],'zoom'=>$a['zoom'],'height'=>$a['height'],'label'=>$a['label']],$a['title']); });
    add_shortcode('kontakt',function($atts){ $a=shortcode_atts(['title'=>''],$atts,'kontakt'); return rrw_sc_widget('contact-form',[],$a['title']); });
    add_shortcode('newsletter',function($atts){ $a=shortcode_atts(['title'=>''],$atts,'newsletter'); return rrw_sc_widget('newsletter',[],$a['title']); });
    // Allgemein: [widget type="faq" items="…"] – jedes CMS-Widget
    add_shortcode('widget',function($atts){ $atts=(array)$atts;$type=sanitize_key($atts['type']??'');$title=(string)($atts['title']??'');unset($atts['type'],$atts['title']);return $type===''?'':rrw_sc_widget($type,$atts,$title); });
    // WordPress-Standard
    add_shortcode('caption',function($atts,$content=null){ $a=shortcode_atts(['id'=>'','align'=>'alignnone','width'=>'','caption'=>''],$atts,'caption'); $img=do_shortcode((string)$content); return '<figure class="wp-caption '.esc_attr($a['align']).'">'.$img.'<figcaption class="wp-caption-text">'.esc_html($a['caption']).'</figcaption></figure>'; });
    add_shortcode('audio',function($atts){ $a=shortcode_atts(['src'=>'','mp3'=>'','loop'=>'','autoplay'=>'','preload'=>'none'],$atts,'audio'); $s=$a['src']?:$a['mp3']; return $s===''?'':'<audio class="wp-audio-shortcode" controls preload="'.esc_attr($a['preload']).'" src="'.esc_url($s).'"></audio>'; });
    add_shortcode('video',function($atts){ $a=shortcode_atts(['src'=>'','mp4'=>'','poster'=>'','width'=>'','height'=>'','preload'=>'metadata'],$atts,'video'); $s=$a['src']?:$a['mp4']; if($s==='')return ''; if(function_exists('rrw_sc_widget')&&preg_match('~(youtu\.?be|vimeo)~i',$s))return rrw_sc_widget('video',['url'=>$s]); return '<video class="wp-video-shortcode" controls preload="'.esc_attr($a['preload']).'" src="'.esc_url($s).'"'.($a['poster']?' poster="'.esc_url($a['poster']).'"':'').' style="max-width:100%"></video>'; });
}

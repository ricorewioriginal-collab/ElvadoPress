<?php
// Eingebaute Shortcodes: Brücke zu den CMS-Widgets (Forum, Netzwerk, Umfrage, FAQ …) und WordPress-Standard ([caption], [audio], [video]).
// Widgets werden als Platzhalter ausgegeben, den das Portal im Browser durch das echte Widget ersetzt (nur serverseitig geprüfte Einstellungen).

function elvado_sc_widget(string $type, array $settings=[], string $title=''): string {
    if(function_exists('elvado_widget_types')){
        if(!isset(elvado_widget_types()[$type]))return '';
        $settings=elvado_widget_settings_clean($type,$settings);
    }
    $w=['type'=>$type,'title'=>mb_substr($title,0,120),'settings'=>$settings];
    return '<div class="elvado-sc" data-elvado-widget="'.esc_attr(wp_json_encode($w,JSON_UNESCAPED_UNICODE)).'"></div>';
}
/** [lovable widget="news-grid" project="…"]: hängt ein in „KI & Lovable“ eingerichtetes Lovable-Widget ein (React-Bridge cms/assets/react/elvado-react.js, Daten von cms/api-lovable-provider.php). */
function elvado_sc_lovable(string $name, string $project=''): string {
    $cms=dirname(__DIR__,2);
    if(!is_file($cms.'/src/autoload.php')||!class_exists('PDO'))return '';
    require_once $cms.'/src/autoload.php';
    if(!\Elvado\Repository\LovableWidgetRepository::validComponent($name))return '';
    $data=dirname(ELVADO_WP_DATA);
    try{
        $db=\Elvado\Database\DatabaseConnection::fromCmsSettings($data);$db->migrateCore();
        $w=(new \Elvado\Repository\LovableWidgetRepository($db))->findByComponent($name,\Elvado\Repository\LovableWidgetRepository::validProject($project)?$project:null);
    }catch(Throwable $e){ return ''; }
    if(!$w||!$w['enabled'])return '';
    $set=\Elvado\Lovable\LovableSettings::load($data);
    $cfg=['projectId'=>$w['project_id'],'componentName'=>$w['component_name'],'dataSourceUrl'=>'/cms/api-lovable-provider.php?widget='.rawurlencode($w['component_name']).'&project='.rawurlencode($w['project_id']),
        'scriptUrl'=>$set->scriptUrl($w['project_id'],(string)$w['config']['script_url']),'allowedHosts'=>$set->bridge()['script_hosts'],'attributes'=>(object)$w['config']['attributes']];
    wp_enqueue_script('elvado-react',home_url('/cms/assets/react/elvado-react.js'),[],'1',true);
    return '<div class="ep-lovable" data-elvado-lovable="'.esc_attr(wp_json_encode($cfg,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)).'"></div>';
}
function elvado_sc_register_builtin(): void {
    $alias=['forum'=>'community-forum','community'=>'community-account','mitglieder'=>'community-account','netzwerk'=>'community-social','social'=>'community-social','video'=>null];
    foreach(['forum','community','mitglieder','netzwerk','social'] as $tag){
        add_shortcode($tag,function($atts) use($alias,$tag){ $a=shortcode_atts(['title'=>''],$atts,$tag); return elvado_sc_widget($alias[$tag],[],$a['title']); });
    }
    add_shortcode('umfrage',function($atts){ $a=shortcode_atts(['id'=>'','title'=>''],$atts,'umfrage'); return elvado_sc_widget('cms-poll',['poll_id'=>$a['id']],$a['title']); });
    add_shortcode('faq',function($atts,$content=null){ $a=shortcode_atts(['title'=>'','open_first'=>'0'],$atts,'faq'); $items=trim(wp_strip_all_tags(str_replace(['<br />','<br>','</p>'],"\n",(string)$content))); return elvado_sc_widget('faq',['items'=>$items,'open_first'=>$a['open_first']],$a['title']); });
    add_shortcode('countdown',function($atts){ $a=shortcode_atts(['target'=>'','label'=>'','done'=>'Es ist so weit!','title'=>''],$atts,'countdown'); return elvado_sc_widget('countdown',['target'=>$a['target'],'label'=>$a['label'],'done'=>$a['done']],$a['title']); });
    add_shortcode('karte',function($atts){ $a=shortcode_atts(['lat'=>'','lon'=>'','zoom'=>'14','height'=>'320','label'=>'','title'=>''],$atts,'karte'); return elvado_sc_widget('map',['lat'=>$a['lat'],'lon'=>$a['lon'],'zoom'=>$a['zoom'],'height'=>$a['height'],'label'=>$a['label']],$a['title']); });
    add_shortcode('kontakt',function($atts){ $a=shortcode_atts(['title'=>''],$atts,'kontakt'); return elvado_sc_widget('contact-form',[],$a['title']); });
    add_shortcode('newsletter',function($atts){ $a=shortcode_atts(['title'=>''],$atts,'newsletter'); return elvado_sc_widget('newsletter',[],$a['title']); });
    add_shortcode('lovable',function($atts){ $a=shortcode_atts(['widget'=>'','project'=>''],$atts,'lovable'); return elvado_sc_lovable((string)$a['widget'],(string)$a['project']); });
    // Allgemein: [widget type="faq" items="…"] – jedes CMS-Widget
    add_shortcode('widget',function($atts){ $atts=(array)$atts;$type=sanitize_key($atts['type']??'');$title=(string)($atts['title']??'');unset($atts['type'],$atts['title']);return $type===''?'':elvado_sc_widget($type,$atts,$title); });
    // WordPress-Standard
    add_shortcode('caption',function($atts,$content=null){ $a=shortcode_atts(['id'=>'','align'=>'alignnone','width'=>'','caption'=>''],$atts,'caption'); $img=do_shortcode((string)$content); return '<figure class="wp-caption '.esc_attr($a['align']).'">'.$img.'<figcaption class="wp-caption-text">'.esc_html($a['caption']).'</figcaption></figure>'; });
    add_shortcode('audio',function($atts){ $a=shortcode_atts(['src'=>'','mp3'=>'','loop'=>'','autoplay'=>'','preload'=>'none'],$atts,'audio'); $s=$a['src']?:$a['mp3']; return $s===''?'':'<audio class="wp-audio-shortcode" controls preload="'.esc_attr($a['preload']).'" src="'.esc_url($s).'"></audio>'; });
    add_shortcode('video',function($atts){ $a=shortcode_atts(['src'=>'','mp4'=>'','poster'=>'','width'=>'','height'=>'','preload'=>'metadata'],$atts,'video'); $s=$a['src']?:$a['mp4']; if($s==='')return ''; if(function_exists('elvado_sc_widget')&&preg_match('~(youtu\.?be|vimeo)~i',$s))return elvado_sc_widget('video',['url'=>$s]); return '<video class="wp-video-shortcode" controls preload="'.esc_attr($a['preload']).'" src="'.esc_url($s).'"'.($a['poster']?' poster="'.esc_url($a['poster']).'"':'').' style="max-width:100%"></video>'; });
}

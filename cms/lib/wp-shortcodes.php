<?php
// ElvadoPress-Shortcodes und Assistent auf der Website mit echtem WordPress (geladen von der Engine-Schicht cms/src/Wp/mu/elvado-engine.php):
// Brücke zu den CMS-Widgets (Forum, Netzwerk, Umfrage, FAQ …, als Platzhalter mit serverseitig geprüften Einstellungen), [lovable] und das Chat-Fenster
// des KI-Assistenten im Seitenfuß. [caption], [audio] und [video] bringt WordPress selbst mit.

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
    $cms=dirname(__DIR__);
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
    $alias=['forum'=>'community-forum','community'=>'community-account','mitglieder'=>'community-account','netzwerk'=>'community-social','social'=>'community-social',];
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
}

/** Chat-Fenster des KI-Assistenten im Seitenfuß, wenn er in der Verwaltung eingeschaltet ist (nicht in der Demo, nicht in Vorschauen). */
function elvado_wp_assistant_widget(): void
{
    if (defined('ELVADO_DEMO') || is_admin() || is_customize_preview() || !defined('ELVADO_WP_DATA')) {
        return;
    }
    require_once __DIR__ . '/assistant.php';
    $site = json_decode((string)@file_get_contents(dirname(ELVADO_WP_DATA) . '/site.json'), true);
    $a = elvado_assistant_clean((array)((is_array($site) ? $site : [])['assistant'] ?? []));
    if (empty($a['enabled'])) {
        return;
    }
    $e = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    echo '<script src="/cms/assets/assistant-widget.js?v=1" defer data-endpoint="/cms/api.php?action=assistant_chat" data-name="' . $e($a['name']) . '" data-greeting="' . $e($a['greeting']) . '" data-privacy="' . $e($a['privacy_note']) . '"></script>' . "\n";
}

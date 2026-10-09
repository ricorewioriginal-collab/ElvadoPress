<?php
// Ergänzende Widget-Funktionen (wp-includes/widgets.php, wp-admin/includes/widgets.php): Steuerelemente, Seitenleisten-Zuordnung,
// RSS-Ausgabe, Konvertierung alter Widgets. Die Registrierung der Widgets läuft hier über die Widget-Fabrik ($wp_widget_factory);
// $wp_registered_widgets füllt die Schicht nicht, die Funktionen berücksichtigen deshalb beides (_elvado_m_widget_known).

if(!function_exists('_elvado_m_widget_known')){ /** Ist die Widget-ID bekannt (registriertes Widget oder Instanz einer Widget-Klasse)? */
function _elvado_m_widget_known($id) {
    if(isset($GLOBALS['wp_registered_widgets'][$id]))return true;
    $base=_get_widget_id_base((string)$id);foreach($GLOBALS['wp_widget_factory']->widgets??[] as $w)if($w->id_base===$base)return true;
    return false;
} }
if(!function_exists('_get_widget_id_base')){ function _get_widget_id_base($id) { return preg_replace('/-[0-9]+$/','',(string)$id); } }
if(!function_exists('wp_parse_widget_id')){ function wp_parse_widget_id($id) {
    if(preg_match('/^(.+)-(\d+)$/',(string)$id,$m))return ['id_base'=>$m[1],'number'=>(int)$m[2]];
    return ['id_base'=>$id];   // vermutlich ein altes Einzel-Widget
} }
if(!function_exists('wp_widget_description')){ function wp_widget_description($id) {
    if(!is_scalar($id))return null;
    if(isset($GLOBALS['wp_registered_widgets'][$id]['description']))return esc_html($GLOBALS['wp_registered_widgets'][$id]['description']);
    $base=_get_widget_id_base((string)$id);foreach($GLOBALS['wp_widget_factory']->widgets??[] as $w)if($w->id_base===$base&&isset($w->widget_options['description']))return esc_html($w->widget_options['description']);
    return null;
} }
if(!function_exists('wp_sidebar_description')){ function wp_sidebar_description($id) {
    if(!is_scalar($id)||!isset($GLOBALS['wp_registered_sidebars'][$id]['description']))return null;
    $ok=['a'=>['href'=>true,'title'=>true,'target'=>true,'rel'=>true],'abbr'=>['title'=>true],'acronym'=>['title'=>true],'code'=>[],'pre'=>[],'em'=>[],'strong'=>[],'b'=>[],'i'=>[],'span'=>[],'div'=>[],'ul'=>[],'ol'=>[],'li'=>[],'p'=>[],'br'=>[]];
    return wp_kses($GLOBALS['wp_registered_sidebars'][$id]['description'],$ok);
} }
if(!function_exists('wp_register_widget_control')){ function wp_register_widget_control($id,$name,$control_callback,$options=[],...$params) {
    global $wp_registered_widget_controls,$wp_registered_widget_updates;$id=strtolower((string)$id);
    if(empty($control_callback)){ unset($wp_registered_widget_controls[$id],$wp_registered_widget_updates[$id]);return; }
    if(isset($wp_registered_widget_controls[$id])&&!did_action('widgets_init'))return;
    $options=wp_parse_args($options,['width'=>250,'height'=>200]);$options['width']=(int)$options['width'];$options['height']=(int)$options['height'];
    $w=array_merge(['name'=>$name,'id'=>$id,'callback'=>$control_callback,'params'=>$params],$options);$wp_registered_widget_controls[$id]=$w;
    if(isset($wp_registered_widget_updates[$id]))return;
    if(isset($w['params'][0]['number']))$w['params'][0]['number']=-1;
    unset($w['width'],$w['height'],$w['name'],$w['id']);$wp_registered_widget_updates[$id]=$w;
} }
if(!function_exists('wp_unregister_widget_control')){ function wp_unregister_widget_control($id) { wp_register_widget_control($id,'',''); } }
if(!function_exists('_register_widget_update_callback')){ function _register_widget_update_callback($id_base,$update_callback,$options=[],...$params) {
    global $wp_registered_widget_updates;
    if(isset($wp_registered_widget_updates[$id_base])){ if(empty($update_callback))unset($wp_registered_widget_updates[$id_base]);return; }
    $wp_registered_widget_updates[$id_base]=array_merge(['callback'=>$update_callback,'params'=>$params],(array)$options);
} }
if(!function_exists('_register_widget_form_callback')){ function _register_widget_form_callback($id,$name,$form_callback,$options=[],...$params) {
    global $wp_registered_widget_controls;$id=strtolower((string)$id);
    if(empty($form_callback)){ unset($wp_registered_widget_controls[$id]);return; }
    if(isset($wp_registered_widget_controls[$id])&&!did_action('widgets_init'))return;
    $o=wp_parse_args($options,['width'=>250,'height'=>200]);$o['width']=(int)$o['width'];$o['height']=(int)$o['height'];
    $wp_registered_widget_controls[$id]=array_merge(['name'=>$name,'id'=>$id,'callback'=>$form_callback,'params'=>$params],$o);
} }
if(!function_exists('is_dynamic_sidebar')){ function is_dynamic_sidebar() {
    $sw=get_option('sidebars_widgets');
    foreach((array)$GLOBALS['wp_registered_sidebars'] as $index=>$_)if(!empty($sw[$index]))foreach((array)$sw[$index] as $w)if(_elvado_m_widget_known($w))return true;
    return false;
} }
if(!function_exists('wp_get_widget_defaults')){ function wp_get_widget_defaults() { $d=[];foreach((array)$GLOBALS['wp_registered_sidebars'] as $i=>$_)$d[$i]=[];return $d; } }
if(!function_exists('wp_convert_widget_settings')){ function wp_convert_widget_settings($base_name,$option_name,$settings) {
    $single=false;$changed=false;
    if(empty($settings))$single=true;else foreach(array_keys($settings) as $n){ if($n==='number')continue;if(!is_numeric($n)){ $single=true;break; } }
    if($single){
        $settings=[2=>$settings];
        if(is_admin())$sw=get_option('sidebars_widgets');else{ if(empty($GLOBALS['_wp_sidebars_widgets']))$GLOBALS['_wp_sidebars_widgets']=get_option('sidebars_widgets',[]);$sw=&$GLOBALS['_wp_sidebars_widgets']; }
        if(is_array($sw))foreach($sw as $index=>$sidebar)if(is_array($sidebar))foreach($sidebar as $i=>$name)if($base_name===$name){ $sw[$index][$i]="$name-2";$changed=true;break 2; }
        if(is_admin()&&$changed)update_option('sidebars_widgets',$sw);
    }
    $settings['_multiwidget']=1;if(is_admin())update_option($option_name,$settings);
    return $settings;
} }
if(!function_exists('_wp_remove_unregistered_widgets')){ function _wp_remove_unregistered_widgets($sidebars_widgets,$allowed_widget_ids=[]) {
    foreach($sidebars_widgets as $sb=>$widgets)if(is_array($widgets))
        $sidebars_widgets[$sb]=array_values(array_filter($widgets,fn($w)=>$allowed_widget_ids?in_array($w,$allowed_widget_ids,true):_elvado_m_widget_known($w)));
    return $sidebars_widgets;
} }
if(!function_exists('wp_map_sidebars_widgets')){ function wp_map_sidebars_widgets($existing_sidebars_widgets) {
    $reg=(array)$GLOBALS['wp_registered_sidebars'];$new=['wp_inactive_widgets'=>[]];
    if(!is_array($existing_sidebars_widgets)||!$existing_sidebars_widgets)return $new;
    foreach($existing_sidebars_widgets as $sb=>$w)if($sb==='wp_inactive_widgets'||str_starts_with((string)$sb,'orphaned_widgets')){ $new['wp_inactive_widgets']=array_merge($new['wp_inactive_widgets'],(array)$w);unset($existing_sidebars_widgets[$sb]); }
    if(count($existing_sidebars_widgets)===1&&count($reg)===1){ $new[key($reg)]=array_pop($existing_sidebars_widgets);return $new; }
    $keys=array_keys($existing_sidebars_widgets);   // gleiche Kennungen übernehmen
    foreach($reg as $sb=>$_){ if(in_array($sb,$keys,true)){ $new[$sb]=$existing_sidebars_widgets[$sb];unset($existing_sidebars_widgets[$sb]); }elseif(!array_key_exists($sb,$new))$new[$sb]=[]; }
    if($existing_sidebars_widgets){   // ähnliche Namen zusammenführen
        foreach([['sidebar','primary','main','right'],['second','left'],['sidebar-2','footer','bottom'],['header','top']] as $group)foreach($group as $slug)foreach($existing_sidebars_widgets as $sb=>$widgets)
            if(str_contains((string)$sb,$slug))foreach($reg as $nsb=>$_)if(empty($new[$nsb])&&str_contains((string)$nsb,$slug)){ $new[$nsb]=$widgets;unset($existing_sidebars_widgets[$sb]);break; }
    }
    foreach($existing_sidebars_widgets as $widgets)if(is_array($widgets)&&$widgets)$new['wp_inactive_widgets']=array_merge($new['wp_inactive_widgets'],$widgets);
    return $new;
} }
if(!function_exists('retrieve_widgets')){ function retrieve_widgets($theme_changed=false) {
    global $sidebars_widgets;$reg=array_keys((array)$GLOBALS['wp_registered_sidebars']);
    $sw=get_option('sidebars_widgets');if(!is_array($sw))$sw=wp_get_sidebars_widgets();unset($sw['array_version']);
    $old=get_theme_mod('sidebars_widgets');
    if($theme_changed){ $sw=wp_map_sidebars_widgets(is_array($old)&&isset($old['data'])?$old['data']:$sw); }
    else { $keep=[];$orphans=0;
        foreach($sw as $sb=>$widgets){ if($sb==='wp_inactive_widgets'||str_starts_with((string)$sb,'orphaned_widgets')||in_array($sb,$reg,true))$keep[$sb]=$widgets;elseif(is_array($widgets)&&$widgets){ $orphans++;$keep['orphaned_widgets_'.$orphans]=$widgets; } }
        $sw=$keep; }
    foreach($reg as $sb)if(!isset($sw[$sb]))$sw[$sb]=[];
    if(!isset($sw['wp_inactive_widgets']))$sw['wp_inactive_widgets']=[];
    $sw=_wp_remove_unregistered_widgets($sw);$sw['array_version']=3;
    update_option('sidebars_widgets',$sw);$sidebars_widgets=$sw;return wp_get_sidebars_widgets();
} }
if(!function_exists('_wp_sidebars_changed')){ function _wp_sidebars_changed() { global $sidebars_widgets;if(!is_array($sidebars_widgets))$sidebars_widgets=wp_get_sidebars_widgets();retrieve_widgets(true); } }
if(!function_exists('wp_find_widgets_sidebar')){ function wp_find_widgets_sidebar($widget_id) {
    foreach(wp_get_sidebars_widgets() as $sb=>$ids)if(is_array($ids))foreach($ids as $w)if($w===$widget_id)return (string)$sb;
    return null;
} }
if(!function_exists('wp_assign_widget_to_sidebar')){ function wp_assign_widget_to_sidebar($widget_id,$sidebar_id) {
    $sw=wp_get_sidebars_widgets();
    foreach($sw as $sb=>$widgets)if(is_array($widgets))foreach($widgets as $i=>$w)if($w===$widget_id&&$sidebar_id!==$sb){ unset($sw[$sb][$i]);$sw[$sb]=array_values($sw[$sb]);continue 2; }
    if($sidebar_id)$sw[$sidebar_id][]=$widget_id;
    wp_set_sidebars_widgets($sw);
} }
if(!function_exists('wp_render_widget')){ /** Gibt das Widget so aus, wie es in der Seitenleiste erscheint (Rückgabe: ob das Widget bekannt war). */
function wp_render_widget($widget_id,$sidebar_id) {
    $r=elvado_wp_widget_instance((string)$widget_id);if(!$r)return false;[$w,$inst]=$r;
    $sb=$GLOBALS['wp_registered_sidebars'][$sidebar_id]??['before_widget'=>'<section id="%1$s" class="widget %2$s">','after_widget'=>'</section>','before_title'=>'<h2 class="widget-title">','after_title'=>'</h2>'];
    $cls=$w->widget_options['classname']??$w->id_base;
    $args=array_merge($sb,['widget_id'=>$widget_id,'widget_name'=>$w->name,'before_widget'=>sprintf($sb['before_widget'],$widget_id,$cls)]);
    $w->display_callback($args,$inst);return true;
} }
if(!function_exists('wp_render_widget_control')){ function wp_render_widget_control($id) {
    $r=elvado_wp_widget_instance((string)$id);if(!$r)return null;[$w,$inst]=$r;
    ob_start();$w->form($inst);$form=ob_get_clean();
    return '<div class="widget-inside"><div class="widget-content">'.$form.'</div></div>';
} }
if(!function_exists('wp_check_widget_editor_deps')){ function wp_check_widget_editor_deps() {
    foreach(['script'=>'wp_script_is','style'=>'wp_style_is'] as $k=>$fn)
        if($fn('wp-widgets','enqueued')&&($fn('wp-edit-widgets','enqueued')||$fn('wp-customize-widgets','enqueued')))
            _doing_it_wrong("wp_enqueue_{$k}()",'„wp-widgets“ sollte nicht zusammen mit dem neuen Widget-Editor („wp-edit-widgets“ oder „wp-customize-widgets“) eingebunden werden.','5.8.0');
} }
if(!function_exists('wp_setup_widgets_block_editor')){ function wp_setup_widgets_block_editor() { add_theme_support('widgets-block-editor'); } }
if(!function_exists('wp_use_widgets_block_editor')){ function wp_use_widgets_block_editor() { return apply_filters('use_widgets_block_editor',get_theme_support('widgets-block-editor')); } }
if(!function_exists('wp_widgets_init')){ function wp_widgets_init() { if(!is_blog_installed())return;elvado_wp_register_core_widgets();do_action('widgets_init'); } }
if(!function_exists('_wp_block_theme_register_classic_sidebars')){ function _wp_block_theme_register_classic_sidebars() {
    if(!wp_is_block_theme())return;$c=get_theme_mod('wp_classic_sidebars');if(empty($c))return;
    foreach((array)$c as $sb)if(!empty($sb['id']))$GLOBALS['wp_registered_sidebars'][$sb['id']]=$sb;
} }

/* ───────── RSS-Widget ───────── */
if(!function_exists('_elvado_m_fetch_feed')){ /** Lädt einen RSS-/Atom-Feed und liefert Einträge als Arrays (title, link, date, description, author) oder WP_Error. 12 Stunden zwischengespeichert. */
function _elvado_m_fetch_feed($url) {
    $url=trim((string)$url);if($url==='')return new WP_Error('empty_url','Die Feed-Adresse fehlt.');
    $key='elvado_feed_'.md5($url);$c=get_transient($key);if(is_array($c))return $c;
    $r=wp_remote_get($url,['timeout'=>10]);if(is_wp_error($r))return $r;
    if((int)wp_remote_retrieve_response_code($r)!==200)return new WP_Error('feed_http','Der Feed konnte nicht geladen werden.');
    $prev=libxml_use_internal_errors(true);$x=simplexml_load_string((string)wp_remote_retrieve_body($r),'SimpleXMLElement',LIBXML_NONET|LIBXML_NOCDATA);libxml_use_internal_errors($prev);
    if(!$x)return new WP_Error('simplepie-error','Der Inhalt ist kein gültiger Feed.');
    $items=[];
    if(isset($x->channel->item))foreach($x->channel->item as $i){ $dc=$i->children('http://purl.org/dc/elements/1.1/');
        $items[]=['title'=>(string)$i->title,'link'=>(string)$i->link,'date'=>strtotime((string)$i->pubDate)?:0,'description'=>(string)$i->description,'author'=>(string)($dc->creator?:$i->author)]; }
    elseif(isset($x->entry))foreach($x->entry as $e){ $l='';foreach($e->link as $lk)if(!isset($lk['rel'])||(string)$lk['rel']==='alternate'){ $l=(string)$lk['href'];break; }
        $items[]=['title'=>(string)$e->title,'link'=>$l,'date'=>strtotime((string)($e->updated?:$e->published))?:0,'description'=>(string)($e->summary?:$e->content),'author'=>(string)$e->author->name]; }
    $out=['items'=>$items,'link'=>isset($x->channel->link)?(string)$x->channel->link:''];set_transient($key,$out,12*HOUR_IN_SECONDS);return $out;
} }
if(!function_exists('wp_widget_rss_output')){ function wp_widget_rss_output($rss,$args=[]) {
    if(is_string($rss))$rss=_elvado_m_fetch_feed($rss);
    elseif(is_array($rss)&&isset($rss['url'])){ $args=$rss;$rss=_elvado_m_fetch_feed($rss['url']); }
    elseif(is_object($rss)&&method_exists($rss,'get_items')){   // SimplePie-ähnliches Objekt
        $it=[];foreach($rss->get_items() as $i){ $a=method_exists($i,'get_author')?$i->get_author():null;
            $it[]=['title'=>$i->get_title(),'link'=>$i->get_link(),'date'=>(int)$i->get_date('U'),'description'=>$i->get_description(),'author'=>is_object($a)?$a->get_name():''];}
        $rss=['items'=>$it]; }
    elseif(!is_array($rss)&&!is_wp_error($rss))return;
    if(is_wp_error($rss)){ if(is_admin()||current_user_can('manage_options'))echo '<p><strong>RSS-Fehler:</strong> '.esc_html($rss->get_error_message()).'</p>';return; }
    $args=wp_parse_args($args,['show_author'=>0,'show_date'=>0,'show_summary'=>0,'items'=>0]);$n=(int)$args['items'];if($n<1||$n>20)$n=10;
    if(empty($rss['items'])){ echo '<ul><li>Ein Fehler ist aufgetreten, vermutlich ist der Feed nicht erreichbar. Versuchen Sie es später erneut.</li></ul>';return; }
    echo '<ul>';
    foreach(array_slice($rss['items'],0,$n) as $it){
        $link=(string)$it['link'];while($link!==''&&stristr($link,'http')!==$link)$link=substr($link,1);$link=esc_url(strip_tags($link));
        $title=esc_html(trim(strip_tags((string)$it['title'])));if($title==='')$title='Ohne Titel';
        $sum='';if((int)$args['show_summary']){ $d=html_entity_decode((string)$it['description'],ENT_QUOTES,(string)get_option('blog_charset','UTF-8'));$d=wp_trim_words(strip_tags($d),55);
            if(str_ends_with($d,'[...]'))$d=substr($d,0,-5).'[&hellip;]';$sum='<div class="rssSummary">'.$d.'</div>'; }
        $date='';if((int)$args['show_date']&&!empty($it['date']))$date=' <span class="rss-date">'.date_i18n((string)get_option('date_format','d.m.Y'),$it['date']).'</span>';
        $au='';if((int)$args['show_author']&&$it['author']!=='')$au=' <cite>'.esc_html(strip_tags((string)$it['author'])).'</cite>';
        echo $link===''?"<li>$title{$date}{$sum}{$au}</li>":"<li><a class='rsswidget' href='$link'>$title</a>{$date}{$sum}{$au}</li>";
    }
    echo '</ul>';
} }
if(!function_exists('wp_widget_rss_form')){ function wp_widget_rss_form($args,$inputs=null) {
    $d=['url'=>'','title'=>'','items'=>10,'error'=>false,'show_summary'=>0,'show_author'=>0,'show_date'=>0];$inputs=wp_parse_args($inputs,['url'=>true,'title'=>true,'items'=>true,'show_summary'=>true,'show_author'=>true,'show_date'=>true]);
    $args=wp_parse_args($args,$d);$n=esc_attr((string)($args['number']??''));$items=(int)$args['items'];if($items<1||$items>20)$items=10;
    if($args['error'])echo '<p class="widget-error"><strong>RSS-Fehler:</strong> '.esc_html($args['error']).'</p>';
    $t=[];
    if($inputs['url'])echo '<p><label for="rss-url-'.$n.'">Feed-Adresse:</label><input class="widefat" id="rss-url-'.$n.'" name="widget-rss['.$n.'][url]" type="text" value="'.esc_url($args['url']).'" /></p>';
    if($inputs['title'])echo '<p><label for="rss-title-'.$n.'">Titel (optional):</label><input class="widefat" id="rss-title-'.$n.'" name="widget-rss['.$n.'][title]" type="text" value="'.esc_attr($args['title']).'" /></p>';
    if($inputs['items']){ echo '<p><label for="rss-items-'.$n.'">Anzahl der Einträge:</label><select id="rss-items-'.$n.'" name="widget-rss['.$n.'][items]">';for($i=1;$i<=20;$i++)echo "<option value='$i'".($items==$i?" selected='selected'":'').">$i</option>";echo '</select></p>'; }
    foreach(['show_summary'=>'Inhaltsangabe anzeigen','show_author'=>'Autor anzeigen','show_date'=>'Datum anzeigen'] as $k=>$l)if($inputs[$k])echo '<p><input id="rss-'.$k.'-'.$n.'" name="widget-rss['.$n.']['.$k.']" type="checkbox" value="1"'.(!empty($args[$k])?' checked="checked"':'').' /> <label for="rss-'.$k.'-'.$n.'">'.$l.'</label></p>';
    foreach(array_keys($d) as $k)if(isset($inputs[$k])&&!$inputs[$k])echo '<input type="hidden" name="widget-rss['.$n.']['.$k.']" value="'.esc_attr((string)$args[$k]).'" />';
} }
if(!function_exists('wp_widget_rss_process')){ function wp_widget_rss_process($widget_rss,$check_feed=true) {
    $items=(int)($widget_rss['items']??10);if($items<1||$items>20)$items=10;
    $url=sanitize_url(strip_tags((string)($widget_rss['url']??'')));$title=isset($widget_rss['title'])?trim(strip_tags((string)$widget_rss['title'])):'';
    $show_summary=(int)($widget_rss['show_summary']??0);$show_author=(int)($widget_rss['show_author']??0);$show_date=(int)($widget_rss['show_date']??0);$error=false;$link='';
    if($check_feed){ $f=_elvado_m_fetch_feed($url);
        if(is_wp_error($f))$error=$f->get_error_message();else{ $link=esc_url(strip_tags((string)($f['link']??'')));while($link!==''&&stristr($link,'http')!==$link)$link=substr($link,1); } }
    return compact('title','url','link','items','error','show_summary','show_author','show_date');
} }

/* ───────── Verwaltung der Widget-Bereiche ───────── */
if(!function_exists('_sort_name_callback')){ function _sort_name_callback($a,$b) { return strnatcasecmp($a['name'],$b['name']); } }
if(!function_exists('next_widget_id_number')){ function next_widget_id_number($id_base) {
    $n=1;$q='/'.preg_quote($id_base,'/').'-([0-9]+)$/';
    foreach(array_keys((array)$GLOBALS['wp_registered_widgets']) as $w)if(preg_match($q,(string)$w,$m))$n=max($n,(int)$m[1]);
    foreach((array)get_option('widget_'.$id_base,[]) as $k=>$_)if(is_numeric($k))$n=max($n,(int)$k);
    foreach((array)get_option('sidebars_widgets',[]) as $ws)if(is_array($ws))foreach($ws as $w)if(preg_match($q,(string)$w,$m))$n=max($n,(int)$m[1]);
    return $n+1;
} }
if(!function_exists('wp_widgets_access_body_class')){ function wp_widgets_access_body_class($classes) {
    $on=(isset($_GET['widgets-access'])&&$_GET['widgets-access']==='on')||(function_exists('get_user_setting')&&get_user_setting('widgets_access')==='on');
    return $on?$classes.' widgets_access ':$classes;
} }
if(!function_exists('wp_list_widgets')){ function wp_list_widgets() {
    $w=[];foreach($GLOBALS['wp_widget_factory']->widgets??[] as $o)$w[]=['name'=>$o->name,'id_base'=>$o->id_base,'desc'=>(string)($o->widget_options['description']??'')];
    usort($w,'_sort_name_callback');$i=0;
    foreach($w as $x){ $i++;$id='widget-'.$i.'_'.esc_attr($x['id_base']).'-__i__';
        echo '<div id="'.$id.'" class="widget"><div class="widget-top"><div class="widget-title ui-draggable-handle"><h3>'.esc_html($x['name']).'<span class="in-widget-title"></span></h3></div></div><div class="widget-description">'.esc_html($x['desc']).'</div></div>'."\n"; }
} }
if(!function_exists('wp_widget_control')){ function wp_widget_control($sidebar_args) {
    $wid=(string)($sidebar_args['widget_id']??($sidebar_args[0]['widget_id']??''));$r=$wid!==''?elvado_wp_widget_instance($wid):null;if(!$r)return null;[$w,$inst]=$r;
    $sb=(string)($sidebar_args['id']??'');
    echo '<div class="widget-top"><div class="widget-title"><h3>'.esc_html($w->name).'<span class="in-widget-title"></span></h3></div></div><div class="widget-inside"><form method="post"><div class="widget-content">';
    $ret=$w->form($inst);
    echo '</div><input type="hidden" name="widget-id" class="widget-id" value="'.esc_attr($wid).'" /><input type="hidden" name="id_base" class="id_base" value="'.esc_attr($w->id_base).'" /><input type="hidden" name="sidebar" class="sidebar" value="'.esc_attr($sb).'" />';
    echo '<div class="widget-control-actions"><div class="alignleft"><button type="button" class="button-link button-link-delete widget-control-remove">Löschen</button> | <button type="button" class="button-link widget-control-close">Schließen</button></div><div class="alignright">';
    if($ret!=='noform')submit_button('Speichern','primary widget-control-save right','savewidget',false);
    echo '</div><br class="clear" /></div></form></div>';return $ret;
} }
if(!function_exists('wp_list_widget_controls_dynamic_sidebar')){ function wp_list_widget_controls_dynamic_sidebar($params) {
    static $i=0;$i++;$wid=$params[0]['widget_id'];$id=$params[0]['_temp_id']??$wid;$hidden=isset($params[0]['_hide'])?' style="display:none;"':'';
    $params[0]['before_widget']="<div id='widget-{$i}_{$id}' class='widget'$hidden>";$params[0]['after_widget']='</div>';
    $params[0]['before_title']='%BEG_OF_TITLE%';$params[0]['after_title']='%END_OF_TITLE%';return $params;
} }
if(!function_exists('wp_list_widget_controls')){ function wp_list_widget_controls($sidebar,$sidebar_name='') {
    add_filter('dynamic_sidebar_params','wp_list_widget_controls_dynamic_sidebar');
    $sb=$GLOBALS['wp_registered_sidebars'][$sidebar]??[];$name=$sidebar_name!==''?$sidebar_name:(string)($sb['name']??$sidebar);
    echo '<div id="'.esc_attr($sidebar).'" class="widgets-sortables"><div class="sidebar-name"><h2>'.esc_html($name).'</h2></div><div class="sidebar-description">';
    echo (string)wp_sidebar_description($sidebar);echo '</div>';
    foreach((wp_get_sidebars_widgets()[$sidebar]??[]) as $wid){ echo '<div class="widget" id="widget-'.esc_attr($wid).'">';wp_widget_control(['widget_id'=>$wid,'id'=>$sidebar]);echo '</div>'; }
    echo '</div>';
    remove_filter('dynamic_sidebar_params','wp_list_widget_controls_dynamic_sidebar');
} }

<?php
// Ergänzende WordPress-Funktionen (Bereich Admin, Teil 2): Ajax-Handler wp_ajax_* für Widgets, Medien, Themes/Plugins, Datenschutz und Website-Zustand.
// Installieren/Aktualisieren läuft über den CMS-Installer (installer.php, autoupdate.php); ohne diesen melden die Handler einen Fehler im JSON-Format von WordPress.

if(!function_exists('elvado_adm_widget_obj')){ function elvado_adm_widget_obj($base) {   // Widget-Objekt zu einer id_base
    foreach(($GLOBALS['wp_widget_factory']->widgets??[]) as $w)if($w->id_base===$base)return $w;
    return null;
} }
if(!function_exists('elvado_adm_widget_save')){ function elvado_adm_widget_save($base, $n, $post, $add=false) {   // Widget-Instanz aus Formulardaten speichern und in eine Seitenleiste einhängen; liefert [Widget, Instanz]
    $w=elvado_adm_widget_obj($base);if(!$w)return null;
    $opts=get_option('widget_'.$base,[]);$opts=is_array($opts)?$opts:[];
    $new=(array)($post['widget-'.$base][$n]??[]);$w->_set($n);
    $inst=$w->update($new,$opts[$n]??[]);if($inst===false)$inst=$opts[$n]??[];
    $opts[$n]=$inst;$opts['_multiwidget']=1;update_option('widget_'.$base,$opts);
    return [$w,$inst];
} }
if(!function_exists('elvado_adm_widget_remove')){ function elvado_adm_widget_remove($wid) {   // Widget aus allen Seitenleisten und aus den Optionen entfernen
    $sw=wp_get_sidebars_widgets();foreach($sw as $k=>$l)if(is_array($l))$sw[$k]=array_values(array_diff($l,[$wid]));wp_set_sidebars_widgets($sw);
    if(preg_match('/^(.+)-(\d+)$/',(string)$wid,$m)){ $o=get_option('widget_'.$m[1],[]);if(is_array($o)&&isset($o[(int)$m[2]])){ unset($o[(int)$m[2]]);update_option('widget_'.$m[1],$o); } }
} }

/* ───────── Widgets ───────── */
if(!function_exists('wp_ajax_widgets_order')){ function wp_ajax_widgets_order() {
    check_ajax_referer('save-sidebar-widgets','savewidgets');elvado_adm_need('edit_theme_options');
    if(!isset($_POST['sidebars'])||!is_array($_POST['sidebars']))wp_die(-1);
    $sw=wp_get_sidebars_widgets();
    foreach($_POST['sidebars'] as $key=>$val){
        $key=sanitize_key($key);if($key===''||$val==='')continue;
        $ids=[];foreach(explode(',',(string)$val) as $v){ $v=preg_replace('/^widget-\d+_/','',trim($v));if($v!=='')$ids[]=$v; }
        $sw[$key]=$ids;
    }
    wp_set_sidebars_widgets($sw);wp_die(1);
} }
if(!function_exists('wp_ajax_save_widget')){ function wp_ajax_save_widget() {   // Widget speichern, löschen oder neu anlegen; Antwort ist das Formular bzw. „deleted:ID“
    check_ajax_referer('save-sidebar-widgets','savewidgets');elvado_adm_need('edit_theme_options');
    $base=(string)elvado_adm_req('id_base');$wid=(string)elvado_adm_req('widget-id');$sb=(string)elvado_adm_req('sidebar');$multi=(int)elvado_adm_req('multi_number',0);
    if($base===''||$wid==='')wp_die('<p>Fehler: Das Widget wurde nicht gefunden.</p>');
    $p=wp_unslash($_POST);$n=!empty($p['add_new'])&&$multi?$multi:(preg_match('/-(\d+)$/',$wid,$m)?(int)$m[1]:0);$id=$base.'-'.$n;
    if(!empty($p['delete_widget'])){ elvado_adm_widget_remove($id);echo 'deleted:'.$id;wp_die(); }
    $r=$n>0?elvado_adm_widget_save($base,$n,$p):null;
    if(!$r)wp_die('<p>Fehler: Das Widget wurde nicht gefunden.</p>');
    if($sb!==''){ $sw=wp_get_sidebars_widgets();$in=false;foreach($sw as $l)if(is_array($l)&&in_array($id,$l,true))$in=true;if(!$in){ $sw[$sb][]=$id;wp_set_sidebars_widgets($sw); } }
    if(!empty($p['add_new']))wp_die();
    ob_start();$r[0]->form($r[1]);wp_die(ob_get_clean());
} }
if(!function_exists('wp_ajax_update_widget')){ function wp_ajax_update_widget() {   // Live-Vorschau-Aktualisierung: Instanz und Formular als JSON
    check_ajax_referer('update-widget','nonce');
    if(!current_user_can('edit_theme_options'))wp_send_json_error(['message'=>'Du darfst keine Widgets bearbeiten.'],403);
    $base=(string)elvado_adm_req('id_base');$wid=(string)elvado_adm_req('widget-id');
    $n=preg_match('/-(\d+)$/',$wid,$m)?(int)$m[1]:0;if($base===''||$n<1)wp_send_json_error(['message'=>'Ungültiges Widget.'],400);
    $r=elvado_adm_widget_save($base,$n,wp_unslash($_POST));if(!$r)wp_send_json_error(['message'=>'Unbekanntes Widget.'],404);
    ob_start();$r[0]->form($r[1]);wp_send_json_success(['form'=>ob_get_clean(),'instance'=>$r[1]]);
} }
if(!function_exists('wp_ajax_delete_inactive_widgets')){ function wp_ajax_delete_inactive_widgets() {
    check_ajax_referer('remove-inactive-widgets','removeinactivewidgets');elvado_adm_need('edit_theme_options');
    $sw=wp_get_sidebars_widgets();foreach((array)($sw['wp_inactive_widgets']??[]) as $wid)elvado_adm_widget_remove($wid);
    $sw=wp_get_sidebars_widgets();$sw['wp_inactive_widgets']=[];wp_set_sidebars_widgets($sw);wp_die('deleted');
} }

/* ───────── Medien ───────── */
if(!function_exists('wp_ajax_media_create_image_subsizes')){ function wp_ajax_media_create_image_subsizes() {
    check_ajax_referer('media-form');
    if(!current_user_can('upload_files'))wp_send_json_error(['message'=>'Du darfst keine Dateien hochladen.']);
    $id=(int)($_POST['attachment_id']??0);if(!$id)wp_send_json_error(['message'=>'Der Upload ist fehlgeschlagen. Bitte lade die Seite neu und versuche es erneut.']);
    if(!empty($_POST['_wp_upload_failed_cleanup'])){
        $a=get_post($id);if($a&&'attachment'===$a->post_type&&current_user_can('delete_post',$id)&&!get_post_meta($id,'_wp_attachment_metadata',true))wp_delete_attachment($id,true);
        wp_send_json_error(['message'=>'Die Bildgrößen konnten nicht erzeugt werden.']);
    }
    $m=wp_update_image_subsizes($id);if(is_wp_error($m))wp_send_json_error(['message'=>$m->get_error_message()]);
    wp_send_json_success(wp_prepare_attachment_for_js($id));
} }
if(!function_exists('wp_ajax_upload_attachment')){ function wp_ajax_upload_attachment() {
    check_ajax_referer('media-form');
    if(!current_user_can('upload_files'))wp_send_json_error(['message'=>'Du darfst keine Dateien hochladen.','filename'=>esc_html($_FILES['async-upload']['name']??'')]);
    $pd=isset($_REQUEST['post_data'])?(array)$_REQUEST['post_data']:[];$pid=isset($_REQUEST['post_id'])?(int)$_REQUEST['post_id']:0;
    if($pid&&!current_user_can('edit_post',$pid))wp_send_json_error(['message'=>'Du darfst diesen Beitrag nicht bearbeiten.','filename'=>esc_html($_FILES['async-upload']['name']??'')]);
    $id=media_handle_upload('async-upload',$pid,$pd);
    if(is_wp_error($id))wp_send_json_error(['message'=>$id->get_error_message(),'filename'=>esc_html($_FILES['async-upload']['name']??'')]);
    $a=wp_prepare_attachment_for_js($id);if(!$a)wp_send_json_error();
    wp_send_json_success($a);
} }
if(!function_exists('wp_ajax_get_attachment')){ function wp_ajax_get_attachment() {
    $id=isset($_REQUEST['id'])?absint($_REQUEST['id']):0;if(!$id)wp_send_json_error();
    $p=get_post($id);if(!$p||'attachment'!==$p->post_type||!current_user_can('edit_post',$id))wp_send_json_error();
    $a=wp_prepare_attachment_for_js($id);if(!$a)wp_send_json_error();wp_send_json_success($a);
} }
if(!function_exists('wp_ajax_query_attachments')){ function wp_ajax_query_attachments() {
    if(!current_user_can('upload_files'))wp_send_json_error();
    $q=isset($_REQUEST['query'])?(array)wp_unslash($_REQUEST['query']):[];
    $keys=['s','order','orderby','posts_per_page','paged','post_mime_type','post_parent','author','post__in','post__not_in','year','monthnum'];
    $q=array_intersect_key($q,array_flip($keys));$q['post_type']='attachment';
    $q['post_status']='inherit';if(current_user_can(get_post_type_object('attachment')->cap->read_private_posts??'read_private_posts'))$q['post_status'].=',private';
    $q['posts_per_page']=min(max(1,(int)($q['posts_per_page']??40)),100);
    $out=[];foreach(get_posts(apply_filters('ajax_query_attachments_args',$q)) as $p){ $a=wp_prepare_attachment_for_js($p);if($a)$out[]=$a; }
    wp_send_json_success($out);
} }
if(!function_exists('wp_ajax_save_attachment')){ function wp_ajax_save_attachment() {
    if(!isset($_REQUEST['id'],$_REQUEST['changes']))wp_send_json_error();
    $id=absint($_REQUEST['id']);if(!$id||'attachment'!==get_post_type($id))wp_send_json_error();
    check_ajax_referer("update-post_$id",'nonce');
    if(!current_user_can('edit_post',$id))wp_send_json_error();
    $ch=(array)$_REQUEST['changes'];$post=get_post($id,ARRAY_A);if(!$post||'attachment'!==$post['post_type'])wp_send_json_error();
    foreach(['title'=>'post_title','caption'=>'post_excerpt','description'=>'post_content'] as $k=>$f)if(isset($ch[$k]))$post[$f]=$ch[$k];
    if(isset($ch['alt'])){ $alt=wp_strip_all_tags(wp_unslash($ch['alt']),true);if($alt!=get_post_meta($id,'_wp_attachment_image_alt',true))update_post_meta($id,'_wp_attachment_image_alt',$alt); }
    $post=wp_slash($post);wp_update_post($post);wp_send_json_success();
} }
if(!function_exists('wp_ajax_save_attachment_compat')){ function wp_ajax_save_attachment_compat() {   // Zusatzfelder aus Plugins (attachment_fields_to_save)
    if(!isset($_REQUEST['id']))wp_send_json_error();
    $id=absint($_REQUEST['id']);if(!$id||!isset($_REQUEST['attachments'][$id]))wp_send_json_error();
    $a=$_REQUEST['attachments'][$id];check_ajax_referer("update-post_$id",'nonce');
    if(!current_user_can('edit_post',$id))wp_send_json_error();
    $post=get_post($id,ARRAY_A);if(!$post||'attachment'!==$post['post_type'])wp_send_json_error();
    $post=apply_filters('attachment_fields_to_save',$post,$a);
    if(isset($post['errors']))unset($post['errors']);
    wp_update_post(wp_slash($post));
    $out=wp_prepare_attachment_for_js($id);if(!$out)wp_send_json_error();wp_send_json_success($out);
} }
if(!function_exists('wp_ajax_save_attachment_order')){ function wp_ajax_save_attachment_order() {
    if(!isset($_REQUEST['post_id']))wp_send_json_error();
    $pid=absint($_REQUEST['post_id']);if(!$pid||empty($_REQUEST['attachments']))wp_send_json_error();
    check_ajax_referer('update-post_'.$pid,'nonce');
    $atts=(array)$_REQUEST['attachments'];if(!current_user_can('edit_post',$pid))wp_send_json_error();
    foreach($atts as $aid=>$order){
        $aid=absint($aid);if(!current_user_can('edit_post',$aid))continue;
        $p=get_post($aid);if(!$p||'attachment'!==$p->post_type)continue;
        if((int)$p->menu_order!==(int)$order)wp_update_post(['ID'=>$aid,'menu_order'=>(int)$order]);
    }
    wp_send_json_success();
} }
if(!function_exists('wp_ajax_send_attachment_to_editor')){ function wp_ajax_send_attachment_to_editor() {   // HTML-Schnipsel für den Editor (Bild oder Link)
    check_ajax_referer('media-send-to-editor','nonce');
    $att=wp_unslash($_POST['attachment']??[]);$id=(int)($att['id']??0);$p=get_post($id);
    if(!$p||'attachment'!==$p->post_type)wp_send_json_error();
    if(current_user_can('edit_post',$id)){
        $d=['ID'=>$id];foreach(['post_title'=>'post_title','post_excerpt'=>'post_excerpt','post_content'=>'post_content'] as $k=>$f)if(isset($att[$k]))$d[$f]=$att[$k];
        if(count($d)>1)wp_update_post(wp_slash($d));
        if(isset($att['image_alt']))update_post_meta($id,'_wp_attachment_image_alt',wp_strip_all_tags($att['image_alt'],true));
    }
    $url=(string)($att['url']??'');$rel=(false!==strpos($url,'attachment_id')||get_attachment_link($id)==$url);
    if(str_starts_with((string)$p->post_mime_type,'image')){
        $html=get_image_send_to_editor($id,(string)($att['post_excerpt']??''),(string)($att['post_title']??''),(string)($att['align']??'none'),$url,$rel,(string)($att['image-size']??'medium'),(string)($att['image_alt']??''));
    } else {
        $html=(string)($att['post_title']??'');$rel=$rel?' rel="attachment wp-att-'.$id.'"':'';
        if($url!=='')$html='<a href="'.esc_url($url).'"'.$rel.'>'.$html.'</a>';
    }
    $html=apply_filters('media_send_to_editor',$html,$id,$att);wp_send_json_success($html);
} }
if(!function_exists('wp_ajax_send_link_to_editor')){ function wp_ajax_send_link_to_editor() {
    check_ajax_referer('media-send-to-editor','nonce');
    $src=(string)wp_unslash($_POST['src']??'');if($src==='')wp_send_json_error();
    if(!strpos($src,'://'))$src='http://'.$src;
    $link=esc_url_raw($src);if(!$link)wp_send_json_error();
    $title=trim((string)wp_unslash($_POST['link_text']??''));if($title==='')$title=wp_basename($link);
    $html='<a href="'.esc_url($link).'">'.esc_html($title).'</a>';
    wp_send_json_success(apply_filters('media_send_to_editor',$html,0,['url'=>$link]));
} }
if(!function_exists('wp_ajax_set_post_thumbnail')){ function wp_ajax_set_post_thumbnail() {
    $json=!empty($_REQUEST['json']);$pid=(int)($_POST['post_id']??0);elvado_adm_need('edit_post',$pid);
    $tid=(int)($_POST['thumbnail_id']??0);
    if($json)check_ajax_referer("update-post_$pid");else check_ajax_referer("set_post_thumbnail-$pid");
    if($tid==-1){ if(delete_post_thumbnail($pid)){ $r=_wp_post_thumbnail_html(null,$pid);$json?wp_send_json_success($r):wp_die($r); } wp_die(0); }
    if(set_post_thumbnail($pid,$tid)){ $r=_wp_post_thumbnail_html($tid,$pid);$json?wp_send_json_success($r):wp_die($r); }
    wp_die(0);
} }
if(!function_exists('wp_ajax_get_post_thumbnail_html')){ function wp_ajax_get_post_thumbnail_html() {
    $pid=(int)($_POST['post_id']??0);elvado_adm_need('edit_post',$pid);
    check_ajax_referer("update-post_$pid");
    $tid=(int)($_POST['thumbnail_id']??0);if($tid==-1)$tid=null;
    wp_send_json_success(_wp_post_thumbnail_html($tid,$pid));
} }
if(!function_exists('wp_ajax_set_attachment_thumbnail')){ function wp_ajax_set_attachment_thumbnail() {   // Vorschaubild für Audio/Video-Anhänge nach URL
    if(empty($_POST['urls'])||!is_array($_POST['urls']))wp_send_json_error();
    $tid=(int)($_POST['thumbnail_id']??0);if(!$tid)wp_send_json_error();
    if(false===check_ajax_referer('set-attachment-thumbnail','_ajax_nonce',false))wp_send_json_error();
    $ids=[];foreach($_POST['urls'] as $u){ $pid=attachment_url_to_postid(esc_url_raw((string)wp_unslash($u)));if($pid)$ids[]=$pid; }
    if(!$ids)wp_send_json_error();
    $ok=0;foreach($ids as $pid){ if(current_user_can('edit_post',$pid)&&set_post_thumbnail($pid,$tid))$ok++; }
    $ok?wp_send_json_success():wp_send_json_error();
} }
if(!function_exists('wp_ajax_imgedit_preview')){ function wp_ajax_imgedit_preview() {
    $pid=(int)($_GET['postid']??0);if(empty($pid)||!current_user_can('edit_post',$pid))wp_die(-1);
    check_ajax_referer("image_editor-$pid");
    if(!stream_preview_image($pid))wp_die(-1);
    wp_die();
} }
if(!function_exists('wp_ajax_image_editor')){ function wp_ajax_image_editor() {
    $id=(int)($_POST['postid']??0);if(empty($id)||!current_user_can('edit_post',$id))wp_die(-1);
    check_ajax_referer("image_editor-$id");
    switch((string)($_POST['do']??'')){
        case 'save': case 'scale': wp_die(wp_json_encode(wp_save_image($id)));
        case 'restore': $msg=wp_restore_image($id);ob_start();wp_image_editor($id,$msg);wp_die(ob_get_clean());
    }
    wp_die(-1);
} }
if(!function_exists('wp_ajax_crop_image')){ function wp_ajax_crop_image() {   // zugeschnittene Kopie als neuer Anhang
    $aid=absint($_POST['id']??0);check_ajax_referer('image_editor-'.$aid,'nonce');
    if(empty($aid)||!current_user_can('edit_post',$aid))wp_send_json_error();
    $ctx=str_replace('_','-',sanitize_key($_POST['context']??''));$d=array_map('absint',(array)($_POST['cropDetails']??[]));
    $file=wp_crop_image($aid,$d['x1']??0,$d['y1']??0,$d['width']??0,$d['height']??0,$d['dst_width']??0,$d['dst_height']??0);
    if(!$file||is_wp_error($file))wp_send_json_error(['message'=>'Das Bild konnte nicht verarbeitet werden. Bitte lade eine andere Datei hoch.']);
    $parent=get_post($aid);$pu=$parent?$parent->guid:'';$url=str_replace(basename($pu),basename($file),$pu);
    $sz=wp_getimagesize($file);
    $new=wp_insert_attachment(['post_title'=>wp_basename($file),'post_content'=>$url,'post_mime_type'=>$sz?$sz['mime']:'image/jpeg','guid'=>$url,'context'=>$ctx],$file);
    if(!$new||is_wp_error($new))wp_send_json_error(['message'=>'Das Bild konnte nicht gespeichert werden.']);
    update_attached_file($new,$file);
    wp_update_attachment_metadata($new,wp_generate_attachment_metadata($new,$file));
    wp_send_json_success(wp_prepare_attachment_for_js($new));
} }
if(!function_exists('wp_ajax_oembed_cache')){ function wp_ajax_oembed_cache() { $GLOBALS['wp_embed']->cache_oembed((int)($_GET['post']??0));wp_die(0); } }
if(!function_exists('wp_ajax_parse_embed')){ function wp_ajax_parse_embed() {
    global $wp_embed;$pid=(int)($_POST['post_ID']??0);
    if(empty($pid)||!current_user_can('edit_post',$pid))wp_send_json_error();
    $sc=(string)wp_unslash($_POST['shortcode']??'');if($sc==='')wp_send_json_error();
    $url=trim(str_replace(['[embed]','[/embed]'],'',$sc));
    if(!filter_var($url,FILTER_VALIDATE_URL))wp_send_json_error(['type'=>'not-embeddable','message'=>sprintf('%s kann nicht eingebettet werden.',esc_url($url))]);
    $wp_embed->post_ID=$pid;$out=$wp_embed->run_shortcode('[embed]'.$url.'[/embed]');
    if(!$out||$out===$url)wp_send_json_error(['type'=>'not-embeddable','message'=>sprintf('%s kann nicht eingebettet werden.',esc_url($url))]);
    wp_send_json_success(['body'=>$out,'attr'=>$wp_embed->last_attr]);
} }
if(!function_exists('wp_ajax_parse_media_shortcode')){ function wp_ajax_parse_media_shortcode() {
    $sc=(string)wp_unslash($_POST['shortcode']??'');if($sc==='')wp_send_json_error();
    $pid=(int)($_POST['post_ID']??0);if($pid&&!current_user_can('edit_post',$pid))wp_send_json_error();
    $out=do_shortcode($sc);if($out===''||$out===$sc)wp_send_json_error();
    wp_send_json_success(['body'=>$out]);
} }

/* ───────── Themes und Plugins (über den CMS-Installer) ───────── */
if(!function_exists('elvado_adm_installer')){ function elvado_adm_installer() {   // Installer-Funktionen verfügbar machen
    if(!function_exists('elvado_wpi_download_plugin')&&function_exists('elvado_td_get')&&is_file(dirname(__DIR__,2).'/installer.php'))require_once dirname(__DIR__,2).'/installer.php';
    return function_exists('elvado_wpi_download_plugin');
} }
if(!function_exists('elvado_adm_update_err')){ function elvado_adm_update_err($msg, $extra=[]) { wp_send_json_error($extra+['errorCode'=>'unable_to_connect_to_filesystem','errorMessage'=>$msg]); } }
if(!function_exists('wp_ajax_install_plugin')){ function wp_ajax_install_plugin() {
    check_ajax_referer('updates');
    $slug=sanitize_key(wp_unslash($_POST['slug']??''));$st=['install'=>'plugin','slug'=>$slug];
    if(empty($slug))wp_send_json_error($st+['errorCode'=>'no_plugin_specified','errorMessage'=>'Es wurde kein Plugin angegeben.']);
    if(!current_user_can('install_plugins'))wp_send_json_error($st+['errorMessage'=>'Du darfst keine Plugins installieren.']);
    if(!wp_is_file_mod_allowed('install_plugins')||!elvado_adm_installer())wp_send_json_error($st+['errorMessage'=>'Plugins können hier nicht installiert werden.']);
    try{ $zip=elvado_wpi_download_plugin($slug);$r=elvado_wpi_install_plugin_zip($zip,$slug);@unlink($zip); }
    catch(Throwable $e){ wp_send_json_error($st+['errorMessage'=>$e->getMessage()]); }
    wp_send_json_success($st+['pluginName'=>$slug,'activateUrl'=>wp_nonce_url(admin_url('plugins.php?action=activate&plugin='.rawurlencode($r['slug'].'/'.($r['files'][0]??''))),'activate-plugin_'.$r['slug'].'/'.($r['files'][0]??''))]);
} }
if(!function_exists('wp_ajax_activate_plugin')){ function wp_ajax_activate_plugin() {
    check_ajax_referer('updates');
    $pl=(string)wp_unslash($_POST['plugin']??'');$st=['activate'=>'plugin','slug'=>(string)wp_unslash($_POST['slug']??''),'plugin'=>$pl];
    if($pl==='')wp_send_json_error($st+['errorMessage'=>'Es wurde kein Plugin angegeben.']);
    if(!elvado_adm_can('activate_plugins',''))wp_send_json_error($st+['errorMessage'=>'Du darfst dieses Plugin nicht aktivieren.']);
    $r=activate_plugin($pl);if(is_wp_error($r))wp_send_json_error($st+['errorCode'=>$r->get_error_code(),'errorMessage'=>$r->get_error_message()]);
    wp_send_json_success($st);
} }
if(!function_exists('wp_ajax_update_plugin')){ function wp_ajax_update_plugin() {
    check_ajax_referer('updates');
    $pl=(string)wp_unslash($_POST['plugin']??'');$slug=sanitize_key(wp_unslash($_POST['slug']??''));$st=['update'=>'plugin','slug'=>$slug,'oldVersion'=>'','newVersion'=>''];
    if($pl===''||$slug==='')wp_send_json_error($st+['errorMessage'=>'Es wurde kein Plugin angegeben.']);
    if(!current_user_can('update_plugins')||0!==validate_file($pl))wp_send_json_error($st+['errorMessage'=>'Du darfst keine Plugins aktualisieren.']);
    if(!wp_is_file_mod_allowed('update_plugins')||!elvado_adm_installer()||!function_exists('elvado_wpau_update_one'))wp_send_json_error($st+['errorMessage'=>'Plugins können hier nicht aktualisiert werden.']);
    $r=elvado_wpau_update_one('plugin',$slug);if(empty($r['ok']))wp_send_json_error($st+['errorMessage'=>(string)($r['msg']??'Die Aktualisierung ist fehlgeschlagen.')]);
    wp_send_json_success($st);
} }
if(!function_exists('wp_ajax_delete_plugin')){ function wp_ajax_delete_plugin() {
    check_ajax_referer('updates');
    $pl=(string)wp_unslash($_POST['plugin']??'');$st=['delete'=>'plugin','slug'=>sanitize_key(wp_unslash($_POST['slug']??''))];
    if($pl===''||$st['slug']==='')wp_send_json_error($st+['errorMessage'=>'Es wurde kein Plugin angegeben.']);
    if(!current_user_can('delete_plugins')||0!==validate_file($pl))wp_send_json_error($st+['errorMessage'=>'Du darfst keine Plugins löschen.']);
    if(is_plugin_active($pl))wp_send_json_error($st+['errorMessage'=>'Aktive Plugins können nicht gelöscht werden.']);
    $r=delete_plugins([$pl]);if(is_wp_error($r))wp_send_json_error($st+['errorMessage'=>$r->get_error_message()]);
    if(!$r)wp_send_json_error($st+['errorMessage'=>'Das Plugin konnte nicht gelöscht werden.']);
    wp_send_json_success($st);
} }
if(!function_exists('wp_ajax_search_plugins')){ function wp_ajax_search_plugins() {   // Suche im Plugin-Verzeichnis über den Installer
    check_ajax_referer('updates');
    if(!current_user_can('install_plugins'))wp_send_json_error(['message'=>'Du darfst keine Plugins installieren.']);
    if(!elvado_adm_installer()||!function_exists('elvado_wpi_search_plugins'))wp_send_json_error(['message'=>'Die Plugin-Suche ist nicht verfügbar.']);
    $r=elvado_wpi_search_plugins(dirname(ELVADO_WP_DATA),(string)wp_unslash($_REQUEST['s']??($_REQUEST['q']??'')),max(1,(int)($_REQUEST['paged']??1)));
    if(empty($r['ok']))wp_send_json_error(['message'=>(string)($r['message']??'Die Suche ist fehlgeschlagen.')]);
    wp_send_json_success(['items'=>$r['items'],'pages'=>$r['pages'],'page'=>$r['page']]);
} }
if(!function_exists('wp_ajax_search_install_plugins')){ function wp_ajax_search_install_plugins() { wp_ajax_search_plugins(); } }
if(!function_exists('wp_ajax_query_themes')){ function wp_ajax_query_themes() {
    if(!current_user_can('install_themes'))wp_send_json_error(['message'=>'Du darfst keine Themes installieren.']);
    if(!elvado_adm_installer()||!function_exists('elvado_wpi_search_themes'))wp_send_json_error(['message'=>'Die Theme-Suche ist nicht verfügbar.']);
    $r=elvado_wpi_search_themes(dirname(ELVADO_WP_DATA),(string)wp_unslash($_REQUEST['request']['search']??($_REQUEST['s']??'')),max(1,(int)($_REQUEST['request']['page']??1)));
    if(empty($r['ok']))wp_send_json_error(['message'=>(string)($r['message']??'Die Suche ist fehlgeschlagen.')]);
    wp_send_json_success(['info'=>['page'=>$r['page'],'pages'=>$r['pages']],'themes'=>$r['items']]);
} }
if(!function_exists('wp_ajax_install_theme')){ function wp_ajax_install_theme() {
    check_ajax_referer('updates');
    $slug=sanitize_key(wp_unslash($_POST['slug']??''));$st=['install'=>'theme','slug'=>$slug];
    if($slug==='')wp_send_json_error($st+['errorMessage'=>'Es wurde kein Theme angegeben.']);
    if(!current_user_can('install_themes'))wp_send_json_error($st+['errorMessage'=>'Du darfst keine Themes installieren.']);
    if(!wp_is_file_mod_allowed('install_themes')||!elvado_adm_installer())wp_send_json_error($st+['errorMessage'=>'Themes können hier nicht installiert werden.']);
    try{ $zip=elvado_wpi_download_theme($slug);elvado_wpi_install_theme_zip($zip,$slug);@unlink($zip); }
    catch(Throwable $e){ wp_send_json_error($st+['errorMessage'=>$e->getMessage()]); }
    wp_send_json_success($st+['themeName'=>$slug]);
} }
if(!function_exists('wp_ajax_update_theme')){ function wp_ajax_update_theme() {
    check_ajax_referer('updates');
    $slug=sanitize_key(wp_unslash($_POST['slug']??''));$st=['update'=>'theme','slug'=>$slug,'oldVersion'=>'','newVersion'=>''];
    if($slug==='')wp_send_json_error($st+['errorMessage'=>'Es wurde kein Theme angegeben.']);
    if(!current_user_can('update_themes'))wp_send_json_error($st+['errorMessage'=>'Du darfst keine Themes aktualisieren.']);
    if(!wp_is_file_mod_allowed('update_themes')||!elvado_adm_installer()||!function_exists('elvado_wpau_update_one'))wp_send_json_error($st+['errorMessage'=>'Themes können hier nicht aktualisiert werden.']);
    $r=elvado_wpau_update_one('theme',$slug);if(empty($r['ok']))wp_send_json_error($st+['errorMessage'=>(string)($r['msg']??'Die Aktualisierung ist fehlgeschlagen.')]);
    wp_send_json_success($st);
} }
if(!function_exists('wp_ajax_delete_theme')){ function wp_ajax_delete_theme() {
    check_ajax_referer('updates');
    $slug=sanitize_key(wp_unslash($_POST['slug']??''));$st=['delete'=>'theme','slug'=>$slug];
    if($slug==='')wp_send_json_error($st+['errorMessage'=>'Es wurde kein Theme angegeben.']);
    if(!current_user_can('delete_themes'))wp_send_json_error($st+['errorMessage'=>'Du darfst keine Themes löschen.']);
    if($slug===get_stylesheet()||$slug===get_template())wp_send_json_error($st+['errorMessage'=>'Das aktive Theme kann nicht gelöscht werden.']);
    $r=delete_theme($slug);if(is_wp_error($r))wp_send_json_error($st+['errorMessage'=>$r->get_error_message()]);
    if(!$r)wp_send_json_error($st+['errorMessage'=>'Das Theme konnte nicht gelöscht werden.']);
    wp_send_json_success($st);
} }
if(!function_exists('wp_ajax_toggle_auto_updates')){ function wp_ajax_toggle_auto_updates() {
    check_ajax_referer('updates');
    $type=(string)($_POST['type']??'');$asset=sanitize_text_field(wp_unslash($_POST['asset']??''));$state=(string)($_POST['state']??'');
    if(!in_array($type,['plugin','theme'],true)||$asset===''||!in_array($state,['enable','disable'],true))wp_send_json_error(['error'=>'Ungültige Angabe.']);
    if(0!==validate_file($asset))wp_send_json_error(['error'=>'Ungültiger Name.']);
    if(!current_user_can('plugin'===$type?'update_plugins':'update_themes'))wp_send_json_error(['error'=>'Du darfst hier keine automatischen Updates ändern.']);
    $o='auto_update_'.$type.'s';$l=array_values(array_unique((array)get_option($o,[])));
    $l='enable'===$state?array_values(array_unique(array_merge($l,[$asset]))):array_values(array_diff($l,[$asset]));
    update_option($o,$l);wp_send_json_success();
} }
if(!function_exists('wp_ajax_edit_theme_plugin_file')){ function wp_ajax_edit_theme_plugin_file() {   // Datei im Editor speichern; mit DISALLOW_FILE_EDIT (Standard) nicht erlaubt
    if(empty($_POST['file']))wp_send_json_error(['message'=>'Es wurde keine Datei angegeben.']);
    $rel=(string)wp_unslash($_POST['file']);if(0!==validate_file($rel))wp_send_json_error(['message'=>'Ungültiger Dateiname.']);
    $isTheme=!empty($_POST['theme']);
    if($isTheme){ $slug=sanitize_text_field(wp_unslash($_POST['theme']));check_ajax_referer('edit-theme_'.$slug.'_'.$rel,'nonce'); if(!current_user_can('edit_themes')||defined('DISALLOW_FILE_EDIT')&&DISALLOW_FILE_EDIT)wp_send_json_error(['message'=>'Die Dateibearbeitung ist deaktiviert.']);$base=get_theme_root().'/'.$slug; }
    elseif(!empty($_POST['plugin'])){ $pl=(string)wp_unslash($_POST['plugin']);check_ajax_referer('edit-plugin_'.$rel,'nonce'); if(!current_user_can('edit_plugins')||defined('DISALLOW_FILE_EDIT')&&DISALLOW_FILE_EDIT)wp_send_json_error(['message'=>'Die Dateibearbeitung ist deaktiviert.']);$base=WP_PLUGIN_DIR.'/'.dirname($pl); }
    else wp_send_json_error(['message'=>'Es wurde weder Theme noch Plugin angegeben.']);
    $path=realpath($base.'/'.$rel);$rb=realpath($base);
    if(!$path||!$rb||!str_starts_with($path,$rb.DIRECTORY_SEPARATOR)||!is_writable($path))wp_send_json_error(['message'=>'Die Datei ist nicht beschreibbar.']);
    if(!in_array(strtolower(pathinfo($path,PATHINFO_EXTENSION)),['php','css','js','html','txt','json','md'],true))wp_send_json_error(['message'=>'Dieser Dateityp ist nicht bearbeitbar.']);
    if(file_put_contents($path,(string)wp_unslash($_POST['newcontent']??''))===false)wp_send_json_error(['message'=>'Die Datei konnte nicht gespeichert werden.']);
    wp_send_json_success(['message'=>'Die Datei wurde bearbeitet.']);
} }

/* ───────── Datenschutz ───────── */
if(!function_exists('wp_ajax_wp_privacy_export_personal_data')){ function wp_ajax_wp_privacy_export_personal_data() {
    if(empty($_POST['id']))wp_send_json_error('Ungültige Anfrage-ID.');
    $rid=(int)$_POST['id'];check_ajax_referer('wp-privacy-export-personal-data-'.$rid,'security');
    if(!elvado_adm_can('export_others_personal_data'))wp_send_json_error('Du darfst keine personenbezogenen Daten exportieren.');
    $req=wp_get_user_request($rid);if(!$req||'export_personal_data'!==$req->action_name)wp_send_json_error('Ungültige Anfrage-ID.');
    $email=$req->email;if(!is_email($email))wp_send_json_error('Ungültige E-Mail-Adresse in der Anfrage.');
    if(!isset($_POST['exporter']))wp_send_json_error('Ungültiger Exporter-Index.');
    $idx=(int)$_POST['exporter'];if(!isset($_POST['page']))wp_send_json_error('Ungültige Seitenzahl.');
    $page=(int)$_POST['page'];$mail=!empty($_POST['sendAsEmail'])&&'true'===$_POST['sendAsEmail'];
    $exporters=apply_filters('wp_privacy_personal_data_exporters',[]);if(!is_array($exporters))wp_send_json_error('Eine Exporter-Funktion hat ein ungültiges Ergebnis geliefert.');
    $keys=array_keys($exporters);if($idx<1||$idx>count($keys)&&count($keys)>0)wp_send_json_error('Ungültiger Exporter-Index.');
    $r=wp_privacy_process_personal_data_export_page(['data'=>[]],$idx,$email,$page,$rid,$mail,$keys[$idx-1]??'');
    if(is_wp_error($r))wp_send_json_error($r);
    wp_send_json_success($r);
} }
if(!function_exists('wp_ajax_wp_privacy_erase_personal_data')){ function wp_ajax_wp_privacy_erase_personal_data() {
    if(empty($_POST['id']))wp_send_json_error('Ungültige Anfrage-ID.');
    $rid=(int)$_POST['id'];check_ajax_referer('wp-privacy-erase-personal-data-'.$rid,'security');
    if(!elvado_adm_can('erase_others_personal_data')||!current_user_can('delete_users'))wp_send_json_error('Du darfst keine personenbezogenen Daten löschen.');
    $req=wp_get_user_request($rid);if(!$req||'remove_personal_data'!==$req->action_name)wp_send_json_error('Ungültige Anfrage-ID.');
    $email=$req->email;if(!is_email($email))wp_send_json_error('Ungültige E-Mail-Adresse in der Anfrage.');
    if(!isset($_POST['eraser']))wp_send_json_error('Ungültiger Löschfunktions-Index.');
    $idx=(int)$_POST['eraser'];if(!isset($_POST['page']))wp_send_json_error('Ungültige Seitenzahl.');
    $page=(int)$_POST['page'];$erasers=apply_filters('wp_privacy_personal_data_erasers',[]);
    if(!is_array($erasers))wp_send_json_error('Eine Löschfunktion hat ein ungültiges Ergebnis geliefert.');
    $keys=array_keys($erasers);if($idx<1||$idx>count($keys)&&count($keys)>0)wp_send_json_error('Ungültiger Löschfunktions-Index.');
    $r=wp_privacy_process_personal_data_erasure_page(['items_removed'=>false,'items_retained'=>false,'messages'=>[],'done'=>true],$idx,$email,$page,$rid,$keys[$idx-1]??'');
    if(is_wp_error($r))wp_send_json_error($r);
    wp_send_json_success($r);
} }

/* ───────── Website-Zustand (Health Check) ───────── */
if(!function_exists('elvado_adm_health_check')){ function elvado_adm_health_check() { check_ajax_referer('health-check-site-status');if(!elvado_adm_can('view_site_health_checks'))wp_die(-1); } }
if(!function_exists('elvado_adm_health_result')){ function elvado_adm_health_result($label, $status, $desc, $test) {
    return ['label'=>$label,'status'=>$status,'badge'=>['label'=>'Sicherheit','color'=>'blue'],'description'=>'<p>'.esc_html($desc).'</p>','actions'=>'','test'=>$test];
} }
if(!function_exists('wp_ajax_health_check_dotorg_communication')){ function wp_ajax_health_check_dotorg_communication() {   // keine Prüfung gegen wordpress.org (kein Netzabruf)
    elvado_adm_health_check();
    wp_send_json_success(elvado_adm_health_result('Die Verbindung zu wordpress.org wird nicht geprüft','good','Das CMS ruft wordpress.org nur bei Installation und Aktualisierung auf.','dotorg_communication'));
} }
if(!function_exists('wp_ajax_health_check_background_updates')){ function wp_ajax_health_check_background_updates() {
    elvado_adm_health_check();
    $on=function_exists('elvado_wpau_get')&&function_exists('elvado_wpau_enabled')&&elvado_wpau_enabled(elvado_wpau_get());
    wp_send_json_success(elvado_adm_health_result($on?'Automatische Updates sind aktiv':'Automatische Updates sind ausgeschaltet',$on?'good':'recommended','Der Zeitplan des CMS aktualisiert Plugins, Themes und Übersetzungen nach den eingestellten Regeln.','background_updates'));
} }
if(!function_exists('wp_ajax_health_check_loopback_requests')){ function wp_ajax_health_check_loopback_requests() {   // Zeitplan-Aufrufe laufen über das CMS, nicht über Loopback
    elvado_adm_health_check();
    wp_send_json_success(elvado_adm_health_result('Loopback-Anfragen werden nicht benötigt','good','Geplante Aufgaben werden vom CMS selbst ausgelöst.','loopback_requests'));
} }
if(!function_exists('wp_ajax_health_check_site_status_result')){ function wp_ajax_health_check_site_status_result() {
    elvado_adm_health_check();
    set_transient('health-check-site-status-result',wp_json_encode(array_map('intval',(array)($_POST['counts']??[]))));
    wp_send_json_success();
} }
if(!function_exists('wp_ajax_health_check_get_sizes')){ function wp_ajax_health_check_get_sizes() {   // Verzeichnisgrößen (nur auf Anforderung berechnet)
    elvado_adm_health_check();
    $up=wp_upload_dir();$dirs=['wordpress_size'=>ABSPATH,'themes_size'=>get_theme_root(),'plugins_size'=>WP_PLUGIN_DIR,'uploads_size'=>$up['basedir']??''];$out=[];
    foreach($dirs as $k=>$d){ $s=$d!==''&&is_dir($d)?(int)get_dirsize($d):0;$out[$k]=['size'=>size_format($s),'debug'=>size_format($s),'raw'=>$s]; }
    $out['total_size']=['size'=>size_format(array_sum(array_column($out,'raw'))),'debug'=>'','raw'=>array_sum(array_column($out,'raw'))];
    wp_send_json_success($out);
} }

/* ───────── Registrierung der Aktionen ───────── */
elvado_adm_hook(['widgets-order'=>'wp_ajax_widgets_order','save-widget'=>'wp_ajax_save_widget','update-widget'=>'wp_ajax_update_widget','delete-inactive-widgets'=>'wp_ajax_delete_inactive_widgets',
    'media-create-image-subsizes'=>'wp_ajax_media_create_image_subsizes','upload-attachment'=>'wp_ajax_upload_attachment','get-attachment'=>'wp_ajax_get_attachment','query-attachments'=>'wp_ajax_query_attachments',
    'save-attachment'=>'wp_ajax_save_attachment','save-attachment-compat'=>'wp_ajax_save_attachment_compat','save-attachment-order'=>'wp_ajax_save_attachment_order','send-attachment-to-editor'=>'wp_ajax_send_attachment_to_editor',
    'send-link-to-editor'=>'wp_ajax_send_link_to_editor','set-post-thumbnail'=>'wp_ajax_set_post_thumbnail','get-post-thumbnail-html'=>'wp_ajax_get_post_thumbnail_html','set-attachment-thumbnail'=>'wp_ajax_set_attachment_thumbnail',
    'imgedit-preview'=>'wp_ajax_imgedit_preview','image-editor'=>'wp_ajax_image_editor','crop-image'=>'wp_ajax_crop_image','oembed-cache'=>'wp_ajax_oembed_cache','parse-embed'=>'wp_ajax_parse_embed',
    'parse-media-shortcode'=>'wp_ajax_parse_media_shortcode','install-plugin'=>'wp_ajax_install_plugin','activate-plugin'=>'wp_ajax_activate_plugin','update-plugin'=>'wp_ajax_update_plugin','delete-plugin'=>'wp_ajax_delete_plugin',
    'search-plugins'=>'wp_ajax_search_plugins','search-install-plugins'=>'wp_ajax_search_install_plugins','query-themes'=>'wp_ajax_query_themes','install-theme'=>'wp_ajax_install_theme','update-theme'=>'wp_ajax_update_theme',
    'delete-theme'=>'wp_ajax_delete_theme','toggle-auto-updates'=>'wp_ajax_toggle_auto_updates','edit-theme-plugin-file'=>'wp_ajax_edit_theme_plugin_file','wp-privacy-export-personal-data'=>'wp_ajax_wp_privacy_export_personal_data',
    'wp-privacy-erase-personal-data'=>'wp_ajax_wp_privacy_erase_personal_data','health-check-dotorg-communication'=>'wp_ajax_health_check_dotorg_communication','health-check-background-updates'=>'wp_ajax_health_check_background_updates',
    'health-check-loopback-requests'=>'wp_ajax_health_check_loopback_requests','health-check-site-status-result'=>'wp_ajax_health_check_site_status_result','health-check-get-sizes'=>'wp_ajax_health_check_get_sizes']);

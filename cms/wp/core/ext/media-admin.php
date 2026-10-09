<?php
// Ergänzende Medien-Funktionen für die Verwaltung (wp-admin/includes/media.php): Upload-Reiter, Einfügen in den Editor, Anhangsfelder,
// Mediathek-Formulare, Metadaten aus Audio-/Videodateien. Die Oberflächen sind schlanke Fassungen (Texte deutsch); das Hochladen selbst
// läuft über die CMS-Medien (media_handle_upload meldet das). Hilfsfunktionen beginnen mit _elvado_m_.

/* ───────── Reiter und Adressen ───────── */
if(!function_exists('media_upload_tabs')){ function media_upload_tabs() { return apply_filters('media_upload_tabs',['type'=>'Vom Computer','type_url'=>'Von URL','gallery'=>'Galerie','library'=>'Mediathek']); } }
if(!function_exists('update_gallery_tab')){ function update_gallery_tab($tabs) {
    global $wpdb;
    if(!isset($_REQUEST['post_id'])){ unset($tabs['gallery']);return $tabs; }
    $pid=(int)$_REQUEST['post_id'];$n=0;
    if($pid)$n=(int)$wpdb->get_var($wpdb->prepare("SELECT count(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status != 'trash' AND post_parent = %d",$pid));
    if(!$n){ unset($tabs['gallery']);return $tabs; }
    $tabs['gallery']=sprintf('Galerie (%s)',"<span id='attachments-count'>$n</span>");return $tabs;
} }
if(!function_exists('the_media_upload_tabs')){ function the_media_upload_tabs() {
    global $redir_tab;$tabs=media_upload_tabs();if(empty($tabs))return;
    echo "<ul id='sidemenu'>\n";
    if(isset($redir_tab)&&array_key_exists($redir_tab,$tabs))$cur=$redir_tab;
    elseif(isset($_GET['tab'])&&array_key_exists($_GET['tab'],$tabs))$cur=$_GET['tab'];
    else $cur=apply_filters('media_upload_default_tab','type');
    foreach($tabs as $cb=>$text){
        $class=$cur===$cb?" class='current'":'';$href=add_query_arg(['tab'=>$cb,'s'=>false,'paged'=>false,'post_mime_type'=>false,'post_type'=>false]);
        echo "\t<li id='".esc_attr("tab-$cb")."'><a href='".esc_url($href)."'$class>$text</a></li>\n";
    }
    echo "</ul>\n";
} }
if(!function_exists('get_upload_iframe_src')){ function get_upload_iframe_src($type=null,$post_id=null,$tab=null) {
    global $post_ID;if(empty($post_id))$post_id=$post_ID;
    $src=add_query_arg('post_id',(int)$post_id,admin_url('media-upload.php'));
    if($type&&$type!=='media')$src=add_query_arg('type',$type,$src);
    if(!empty($tab))$src=add_query_arg('tab',$tab,$src);
    $src=apply_filters($type?"{$type}_upload_iframe_src":'upload_iframe_src',$src);
    return add_query_arg('TB_iframe',true,$src);
} }
if(!function_exists('media_buttons')){ function media_buttons($editor_id='content') {
    if(!current_user_can('upload_files'))return;
    printf('<button type="button" id="insert-media-button" class="button insert-media add_media" data-editor="%s"><span class="wp-media-buttons-icon"></span> %s</button>',esc_attr($editor_id),'Dateien hinzufügen');
} }

/* ───────── In den Editor einfügen ───────── */
if(!function_exists('get_image_send_to_editor')){ function get_image_send_to_editor($id,$caption,$title,$align,$url='',$rel=false,$size='medium',$alt='',$caption_id='') {
    $html=get_image_tag($id,$alt,'',$align,$size);
    if($rel){ $rel=is_string($rel)?' rel="'.esc_attr($rel).'"':' rel="attachment wp-att-'.(int)$id.'"'; }else $rel='';
    if($url)$html='<a href="'.esc_url($url).'"'.$rel.'>'.$html.'</a>';
    return apply_filters('image_send_to_editor',$html,$id,$caption,$title,$align,$url,$size,$alt,$rel);
} }
if(!function_exists('_cleanup_image_add_caption')){ function _cleanup_image_add_caption($matches) { return preg_replace('/[\r\n\t]+/',' ',$matches[0]); } }
if(!function_exists('image_add_caption')){ function image_add_caption($html,$id,$caption,$title,$align,$url,$size,$alt='',$rel='') {
    $caption=apply_filters('image_add_caption_text',$caption,$id);
    if(empty($caption)||apply_filters('disable_captions',''))return $html;
    $id=$id>0?'attachment_'.$id:'';
    if(!preg_match('/width=["\']([0-9]+)/',$html,$m))return $html;
    $width=$m[1];$caption=str_replace(["\r\n","\r"],"\n",(string)$caption);
    $caption=preg_replace_callback('/<[a-zA-Z0-9]+(?: [^<>]+>)*/','_cleanup_image_add_caption',$caption);
    $caption=preg_replace('/[ \n\t]*\n[ \t]*/','<br />',$caption);
    $html=preg_replace('/(class=["\'][^\'"]*)align(none|left|right|center)\s?/','$1',$html);
    if(empty($align))$align='none';
    return apply_filters('image_add_caption_shortcode','[caption id="'.$id.'" align="align'.$align.'" width="'.$width.'"]'.$html.' '.$caption.'[/caption]',$html);
} }
if(!function_exists('media_send_to_editor')){ /** Gibt das Skript aus, das den HTML-Code an den Editor übergibt (WordPress beendet hier die Anfrage; diese Fassung nicht). */
function media_send_to_editor($html) {
    echo '<script>var win = window.dialogArguments || opener || parent || top; win.send_to_editor('.wp_json_encode($html).');</script>';
} }
if(!function_exists('image_media_send_to_editor')){ function image_media_send_to_editor($html,$attachment_id,$attachment) {
    $post=get_post($attachment_id);
    if($post&&str_starts_with((string)$post->post_mime_type,'image')){
        $url=$attachment['url']??'';$align=!empty($attachment['align'])?$attachment['align']:'none';$size=!empty($attachment['image-size'])?$attachment['image-size']:'medium';$alt=!empty($attachment['image_alt'])?$attachment['image_alt']:'';
        $rel=($url==get_attachment_link($attachment_id));
        return get_image_send_to_editor($attachment_id,$attachment['post_excerpt']??'',$attachment['post_title']??'',$align,$url,$rel,$size,$alt);
    }
    return $html;
} }

/* ───────── Eingabefelder für Anhänge ───────── */
if(!function_exists('image_align_input_fields')){ function image_align_input_fields($post,$checked='') {
    $al=['none'=>'Keine','left'=>'Links','center'=>'Mitte','right'=>'Rechts'];
    if(empty($checked))$checked=function_exists('get_user_setting')?(get_user_setting('align')?:'none'):'none';
    if(!array_key_exists((string)$checked,$al))$checked='none';
    $o=[];foreach($al as $name=>$label){ $name=esc_attr($name);
        $o[]="<input type='radio' name='attachments[{$post->ID}][align]' id='image-align-{$name}-{$post->ID}' value='$name'".($checked==$name?" checked='checked'":'')." /><label for='image-align-{$name}-{$post->ID}' class='align image-align-{$name}-label'>$label</label>"; }
    return implode("\n",$o);
} }
if(!function_exists('image_size_input_fields')){ function image_size_input_fields($post,$check='') {
    $names=apply_filters('image_size_names_choose',['thumbnail'=>'Miniaturansicht','medium'=>'Mittel','large'=>'Groß','full'=>'Originalgröße']);
    if(empty($check))$check=function_exists('get_user_setting')?get_user_setting('imgsize','medium'):'medium';
    $out=[];
    foreach($names as $size=>$label){
        $d=image_downsize($post->ID,$size);$checked='';$enabled=($d&&$d[3])||$size==='full';$css="image-size-{$size}-{$post->ID}";
        if($size==$check){ if($enabled)$checked=" checked='checked'";else $check=''; }
        elseif(!$check&&$enabled&&$size!=='thumbnail'){ $check=$size;$checked=" checked='checked'"; }
        $h="<div class='image-size-item'><input type='radio' ".($enabled?'':"disabled='disabled' ")."name='attachments[{$post->ID}][image-size]' id='{$css}' value='{$size}'$checked />";
        $h.="<label for='{$css}'>$label</label>";
        if($enabled&&$d)$h.=" <label for='{$css}' class='help'>".sprintf('(%d&nbsp;&times;&nbsp;%d)',$d[1],$d[2]).'</label>';
        $out[]=$h.'</div>';
    }
    return ['label'=>'Größe','input'=>'html','html'=>implode("\n",$out)];
} }
if(!function_exists('image_link_input_fields')){ function image_link_input_fields($post,$url_type='') {
    $file=wp_get_attachment_url($post->ID);$link=get_attachment_link($post->ID);
    if(empty($url_type))$url_type=function_exists('get_user_setting')?get_user_setting('urlbutton','post'):'post';
    $url='';if($url_type==='file')$url=$file;elseif($url_type==='post')$url=$link;
    return "<input type='text' class='text urlfield' name='attachments[{$post->ID}][url]' value='".esc_attr($url)."' /><br />
<button type='button' class='button urlnone' data-link-url=''>Keiner</button>
<button type='button' class='button urlfile' data-link-url='".esc_attr($file)."'>Datei-URL</button>
<button type='button' class='button urlpost' data-link-url='".esc_attr($link)."'>Anhangsseite</button>";
} }
if(!function_exists('wp_caption_input_textarea')){ function wp_caption_input_textarea($edit_post) {
    $name="attachments[{$edit_post->ID}][post_excerpt]";return '<textarea name="'.$name.'" id="'.$name.'">'.$edit_post->post_excerpt.'</textarea>';   // die Beitragsdaten sind bereits maskiert
} }
if(!function_exists('image_attachment_fields_to_edit')){ function image_attachment_fields_to_edit($form_fields,$post) { return $form_fields; } }
if(!function_exists('media_single_attachment_fields_to_edit')){ function media_single_attachment_fields_to_edit($form_fields,$post) { unset($form_fields['url'],$form_fields['align'],$form_fields['image-size']);return $form_fields; } }
if(!function_exists('media_post_single_attachment_fields_to_edit')){ function media_post_single_attachment_fields_to_edit($form_fields,$post) { unset($form_fields['image_url']);return $form_fields; } }
if(!function_exists('get_attachment_fields_to_edit')){ function get_attachment_fields_to_edit($post,$errors=null) {
    if(is_int($post))$post=get_post($post);elseif(is_array($post))$post=(object)$post;
    if(!$post)return [];
    $image_url=wp_get_attachment_url($post->ID);$edit=sanitize_post($post,'edit');
    $f=['post_title'=>['label'=>'Titel','value'=>$edit->post_title],'image_alt'=>[],
        'post_excerpt'=>['label'=>'Beschriftung','input'=>'html','html'=>wp_caption_input_textarea($edit)],
        'post_content'=>['label'=>'Beschreibung','value'=>$edit->post_content,'input'=>'textarea'],
        'url'=>['label'=>'Link-URL','input'=>'html','html'=>image_link_input_fields($post,get_option('image_default_link_type'))],
        'menu_order'=>['label'=>'Reihenfolge','value'=>$edit->menu_order],
        'image_url'=>['label'=>'Datei-URL','input'=>'html','html'=>"<input type='text' class='text urlfield' readonly='readonly' name='attachments[{$post->ID}][url]' value='".esc_attr($image_url)."' /><br />",'value'=>$image_url]];
    if(wp_attachment_is_image($post->ID)){
        $alt=get_post_meta($post->ID,'_wp_attachment_image_alt',true);
        $f['post_title']['required']=true;$f['image_alt']=['value'=>$alt?:'','label'=>'Alternativtext'];
        $f['align']=['label'=>'Ausrichtung','input'=>'html','html'=>image_align_input_fields($post,get_option('image_default_align'))];
        $f['image-size']=image_size_input_fields($post,get_option('image_default_size','medium'));
    } else unset($f['image_alt']);
    return apply_filters('attachment_fields_to_edit',$f,$post);
} }

/** Tabellenzeilen für Anhangsfelder (Hilfsfunktion der Medien-Formulare). */
if(!function_exists('_elvado_m_field_rows')){ function _elvado_m_field_rows($post,array $fields,array $skip=[]) {
    $o='';
    foreach($fields as $id=>$f){ if(in_array($id,$skip,true)||!is_array($f)||(!isset($f['label'])&&!isset($f['html'])))continue;
        $f+=['input'=>'text','required'=>false,'value'=>'','label'=>$id];$name="attachments[{$post->ID}][$id]";
        $req=$f['required']?' <span class="alignright"><abbr title="erforderlich" class="required">*</abbr></span>':'';
        if($f['input']==='html')$in=$f['html']??'';
        elseif($f['input']==='textarea')$in='<textarea id="'.esc_attr($name).'" name="'.esc_attr($name).'">'.esc_textarea((string)$f['value']).'</textarea>';
        else $in='<input type="text" class="text" id="'.esc_attr($name).'" name="'.esc_attr($name).'" value="'.esc_attr((string)$f['value']).'"'.($f['required']?' required':'').' />';
        $o.="\t<tr class='".esc_attr($id)."'>\n\t\t<th scope='row' class='label'><label for='".esc_attr($name)."'><span class='alignleft'>".esc_html($f['label'])."</span>$req<br class='clear' /></label></th>\n\t\t<td class='field'>$in".(!empty($f['error'])?'<p class="error">'.esc_html($f['error']).'</p>':'')."</td>\n\t</tr>\n";
    }
    return $o;
} }
if(!function_exists('get_media_item')){ function get_media_item($attachment_id,$args=null) {
    $args=wp_parse_args($args,['errors'=>null,'send'=>(bool)get_post($attachment_id)&&get_post($attachment_id)->post_parent,'delete'=>true,'toggle'=>true,'show_title'=>true]);
    $args=apply_filters('get_media_item_args',$args);
    $post=get_post($attachment_id);if(!$post)return false;
    $id=(int)$post->ID;$filename=esc_html(wp_basename((string)get_attached_file($id)));$title=esc_attr($post->post_title);
    $thumb='';if(wp_attachment_is_image($id)&&($t=wp_get_attachment_image_src($id,'thumbnail')))$thumb="<img class='pinkynail' src='".esc_url($t[0])."' alt='' />";
    $o=$thumb.($args['show_title']?"<div class='filename new'><span class='title'>".($title!==''?$title:$filename)."</span></div>":'');
    $fields=get_attachment_fields_to_edit($post,$args['errors']);
    $o.="<table class='slidetoggle describe startclosed'><tbody>\n".(wp_attachment_is_image($id)?'':'').'<tr><th scope="row" class="label"><span class="alignleft">Dateiname</span></th><td class="field">'.$filename."</td></tr>\n"._elvado_m_field_rows($post,$fields)."\t<tr class='submit'><td></td><td class='savesend'>";
    if($args['send'])$o.='<input type="submit" class="button" name="send['.$id.']" value="In Beitrag einfügen" />';
    if($args['delete']&&current_user_can('delete_post',$id))$o.=' <a href="'.esc_url(wp_nonce_url(admin_url('post.php?action=delete&post='.$id),'delete-post_'.$id)).'" class="delete">Endgültig löschen</a>';
    return $o."</td></tr></tbody></table>\n";
} }
if(!function_exists('get_media_items')){ function get_media_items($post_id,$errors) {
    $att=[];
    if($post_id){ $p=get_post($post_id);$att=$p&&$p->post_type==='attachment'?[$p->ID=>$p]:_elvado_m_by_id(get_children(['post_parent'=>$post_id,'post_type'=>'attachment','orderby'=>'menu_order ID','order'=>'DESC'])); }
    elseif(!empty($GLOBALS['wp_the_query']->posts)&&is_array($GLOBALS['wp_the_query']->posts))foreach($GLOBALS['wp_the_query']->posts as $a)$att[$a->ID]=$a;
    $o='';
    foreach($att as $id=>$a){ if($a->post_status==='trash')continue;
        $item=get_media_item($id,['errors'=>$errors[$id]??null]);
        if(!empty($item))$o.="\n<div id='media-item-$id' class='media-item child-of-$a->post_parent preloaded'><div class='progress hidden'><div class='bar'></div></div><div id='media-upload-error-$id'></div><div class='filename hidden'></div>$item\n</div>"; }
    return $o;
} }
if(!function_exists('get_compat_media_markup')){ function get_compat_media_markup($attachment_id,$args=null) {
    $post=get_post($attachment_id);if(!$post)return ['item'=>'','meta'=>''];
    $args=apply_filters('get_media_item_args',wp_parse_args($args,['errors'=>null,'in_modal'=>false]));$f=[];
    if($args['in_modal'])foreach(get_attachment_taxonomies($post) as $tax){ $t=(array)get_taxonomy($tax);if(empty($t['public'])||empty($t['show_ui']))continue;
        $vals=[];foreach((array)wp_get_object_terms($post->ID,$tax) as $term)$vals[]=$term->slug;
        $f[$tax]=['label'=>$t['label']??$tax,'value'=>implode(', ',$vals),'taxonomy'=>true]; }
    $f=array_merge($f,(array)$args['errors']);$f=apply_filters('attachment_fields_to_edit',$f,$post);
    $core=['post_title','post_excerpt','post_content','url','menu_order','image_alt','image-size','align','image_url'];
    $item="<table class='compat-attachment-fields'>"._elvado_m_field_rows($post,$f,$core).'</table>';
    return ['item'=>$item,'meta'=>apply_filters('media_meta','',$post)];
} }

/* ───────── Formulare und Fenster ───────── */
if(!function_exists('_wp_admin_html_begin')){ function _wp_admin_html_begin() {
    $cls=is_admin_bar_showing()?'wp-toolbar':'';
    echo '<!DOCTYPE html><html class="'.esc_attr($cls).'" ';do_action('admin_xml_ns');echo ' ';language_attributes();
    echo '><head><meta http-equiv="Content-Type" content="text/html; charset='.esc_attr((string)get_option('blog_charset','UTF-8')).'" />';
} }
if(!function_exists('wp_iframe')){ function wp_iframe($content_func,...$args) {
    $hook='media-upload-popup';_wp_admin_html_begin();
    echo '<title>'.esc_html(get_bloginfo('name')).' &rsaquo; Medien hochladen</title>';
    wp_enqueue_style('colors');wp_enqueue_script('utils');
    do_action('admin_enqueue_scripts',$hook);do_action("admin_print_styles-$hook");do_action('admin_print_styles');do_action("admin_print_scripts-$hook");do_action('admin_print_scripts');do_action("admin_head-$hook");do_action('admin_head');
    $body=sanitize_html_class(is_string($content_func)?str_replace('_form','',str_replace('media_upload_','',$content_func)):'media');
    echo '</head><body id="media-upload" class="wp-core-ui no-js media-upload-popup '.esc_attr($body).'"><script>document.body.className = document.body.className.replace("no-js", "js");</script>';
    if(is_callable($content_func))call_user_func_array($content_func,$args);
    do_action('admin_print_footer_scripts');
    echo '<script>if(typeof wpOnload==="function")wpOnload();</script></body></html>';
} }
if(!function_exists('media_upload_header')){ function media_upload_header() {
    echo '<script>post_id = '.(isset($_REQUEST['post_id'])?(int)$_REQUEST['post_id']:0).';</script>';
    if(empty($_GET['chromeless'])){ echo '<div id="media-upload-header">';the_media_upload_tabs();echo '</div>'; }
} }
if(!function_exists('media_upload_text_after')){ function media_upload_text_after() { echo '<span class="after-file-upload">Nach dem Hochladen können Sie Titel und Beschreibungen ergänzen.</span>'; } }
if(!function_exists('media_upload_flash_bypass')){ function media_upload_flash_bypass() {
    echo '<p class="upload-flash-bypass">';printf('Sie nutzen den Mehrfach-Upload. Probleme? Versuchen Sie stattdessen den <a href="%1$s" target="%2$s">Browser-Upload</a>.',esc_url(admin_url('media-new.php?browser-uploader')),'_blank');echo '</p>';
} }
if(!function_exists('media_upload_html_bypass')){ function media_upload_html_bypass() {
    echo '<p class="upload-html-bypass hide-if-no-js">Sie nutzen den einfachen Datei-Upload des Browsers. <a href="#">Zum Mehrfach-Upload wechseln</a>.</p>';
} }
if(!function_exists('media_upload_max_image_resize')){ function media_upload_max_image_resize() {
    $checked=function_exists('get_user_setting')&&get_user_setting('upload_resize')?' checked="true"':'';$a=$end='';
    if(current_user_can('manage_options')){ $a='<a href="'.esc_url(admin_url('options-media.php')).'" target="_blank">';$end='</a>'; }
    echo '<p class="hide-if-no-js"><label><input name="image_resize" type="checkbox" id="image_resize" value="true"'.$checked.' /> ';
    printf('Bilder auf die in den %1$sBildoptionen%2$s gewählte große Größe verkleinern (%3$d &times; %4$d).',$a,$end,(int)get_option('large_size_w',1024),(int)get_option('large_size_h',1024));
    echo '</label></p>';
} }
if(!function_exists('multisite_over_quota_message')){ /** Einzelseite: kein Speicherkontingent – nur eine allgemeine Meldung. */
function multisite_over_quota_message() { echo '<p>Das Speicherkontingent ist ausgeschöpft.</p>'; } }
if(!function_exists('media_upload_form')){ function media_upload_form($errors=null) {
    echo '<div id="media-upload-notice"></div><div id="media-upload-error"></div><div id="html-upload-ui" class="hide-if-js"><p id="async-upload-wrap"><label class="screen-reader-text" for="async-upload">Hochladen</label>';
    echo '<input type="file" name="async-upload" id="async-upload" /> <input type="submit" name="html-upload" id="html-upload" class="button" value="Hochladen" /></p><div class="clear"></div></div>';
    echo '<p class="max-upload-size">Maximale Dateigröße: '.esc_html(size_format(wp_max_upload_size())).'</p>';
    media_upload_text_after();
} }
if(!function_exists('media_upload_type_form')){ function media_upload_type_form($type='file',$errors=null,$id=null) {
    media_upload_header();$pid=isset($_REQUEST['post_id'])?(int)$_REQUEST['post_id']:0;
    $url=apply_filters('media_upload_form_url',admin_url("media-upload.php?type=$type&tab=type&post_id=$pid"),$type);
    echo '<form enctype="multipart/form-data" method="post" action="'.esc_url($url).'" class="media-upload-form type-form validate" id="'.esc_attr($type).'-form"><input type="hidden" name="post_id" id="post_id" value="'.$pid.'" />';
    wp_nonce_field('media-form');echo '<h3 class="media-title">Mediendateien vom Computer hinzufügen</h3>';media_upload_form($errors);
    echo '<div id="media-items">';
    if($id){ if(!is_wp_error($id)){ add_filter('attachment_fields_to_edit','media_post_single_attachment_fields_to_edit',10,2);echo get_media_items($id,$errors); }
        else echo '<div id="media-upload-error">'.esc_html($id->get_error_message()).'</div>'; }
    echo '</div><p class="savebutton ml-submit">';submit_button('Änderungen speichern','primary','save',false);echo '</p></form>';
} }
if(!function_exists('wp_media_insert_url_form')){ function wp_media_insert_url_form($default_view='image') {
    $o='<p class="media-types media-types-required-info">Pflichtfelder sind mit * markiert.</p><p class="media-types"><label><input type="radio" name="media_type" value="image" id="image-only"'.($default_view==='image'?' checked="checked"':'').' /> Bild</label> ';
    $o.='<label><input type="radio" name="media_type" value="audio" id="audio-only"'.($default_view==='audio'?' checked="checked"':'').' /> Audio</label> <label><input type="radio" name="media_type" value="video" id="video-only"'.($default_view==='video'?' checked="checked"':'').' /> Video</label></p>';
    $o.='<table class="describe"><tbody><tr><th scope="row" class="label"><label for="src">URL</label></th><td class="field"><input id="src" name="src" value="" type="text" required /></td></tr>';
    $o.='<tr><th scope="row" class="label"><label for="title">Titel</label></th><td class="field"><input id="title" name="title" value="" type="text" /></td></tr>';
    $o.='<tr class="not-image"><td></td><td><p class="help">Link-Text, z. B. „Schlechte Neuigkeiten aus Berlin“</p></td></tr>';
    $o.='<tr class="image-only"><th scope="row" class="label"><label for="alt">Alternativtext</label></th><td class="field"><input id="alt" name="alt" value="" type="text" /></td></tr>';
    $o.='<tr class="image-only"><th scope="row" class="label"><span class="alignleft">Ausrichtung</span></th><td class="field">';
    foreach(['none'=>'Keine','left'=>'Links','center'=>'Mitte','right'=>'Rechts'] as $v=>$l)$o.='<input type="radio" name="align" id="align-'.$v.'" value="'.$v.'"'.($v==='none'?' checked="checked"':'').' /><label for="align-'.$v.'" class="align image-align-'.$v.'-label">'.$l.'</label>';
    return $o.'</td></tr><tr><td></td><td><input type="submit" class="button" name="insertonlybutton" value="In Beitrag einfügen" /></td></tr></tbody></table>';
} }
if(!function_exists('media_upload_type_url_form')){ function media_upload_type_url_form($type=null,$errors=null,$id=null) {
    if($type===null)$type='image';media_upload_header();$pid=isset($_REQUEST['post_id'])?(int)$_REQUEST['post_id']:0;
    $url=apply_filters('media_upload_form_url',admin_url("media-upload.php?type=$type&tab=type&post_id=$pid"),$type);
    echo '<form enctype="multipart/form-data" method="post" action="'.esc_url($url).'" class="media-upload-form type-form validate" id="'.esc_attr($type).'-form"><input type="hidden" name="post_id" id="post_id" value="'.$pid.'" />';
    wp_nonce_field('media-form');echo '<h3 class="media-title">Medien von einer Adresse einfügen</h3><div id="media-items"><div class="media-item media-blank">'.apply_filters('type_url_form_media',wp_media_insert_url_form($type)).'</div></div></form>';
} }
if(!function_exists('media_upload_gallery_form')){ function media_upload_gallery_form($errors) {
    media_upload_header();$pid=isset($_REQUEST['post_id'])?(int)$_REQUEST['post_id']:0;
    echo '<form enctype="multipart/form-data" method="post" action="'.esc_url(admin_url("media-upload.php?type=image&tab=gallery&post_id=$pid")).'" class="media-upload-form validate" id="gallery-form"><input type="hidden" name="post_id" id="post_id" value="'.$pid.'" />';
    wp_nonce_field('media-form');echo '<div id="sort-buttons" class="hide-if-no-js"><span>Sortieren nach: <a href="#" id="asc">aufsteigend</a> | <a href="#" id="desc">absteigend</a></span></div>';
    echo '<div id="media-items">'.get_media_items($pid,$errors).'</div><p class="ml-submit">';submit_button('Änderungen speichern','primary','save',false);
    echo '<input type="submit" class="button" name="insert-gallery" value="Galerie einfügen" /> <input type="submit" class="button" name="update-gallery" value="Galerie aktualisieren" /></p></form>';
} }
if(!function_exists('media_upload_library_form')){ function media_upload_library_form($errors) {
    media_upload_header();$pid=isset($_REQUEST['post_id'])?(int)$_REQUEST['post_id']:0;
    $items=get_posts(['post_type'=>'attachment','post_status'=>'any','posts_per_page'=>20,'orderby'=>'date','order'=>'DESC']);
    echo '<form enctype="multipart/form-data" method="post" action="'.esc_url(admin_url("media-upload.php?type=image&tab=library&post_id=$pid")).'" class="media-upload-form validate" id="library-form"><input type="hidden" name="post_id" id="post_id" value="'.$pid.'" />';
    wp_nonce_field('media-form');echo '<div id="media-items">';
    foreach($items as $a){ $i=get_media_item($a->ID,['errors'=>$errors[$a->ID]??null,'send'=>$pid>0]);if($i)echo "<div id='media-item-{$a->ID}' class='media-item'>$i</div>"; }
    echo '</div></form>';
} }
if(!function_exists('media_upload_form_handler')){ function media_upload_form_handler() {
    check_admin_referer('media-form');$errors=null;
    if(isset($_POST['send'])){ $k=array_keys((array)$_POST['send']);$send_id=(int)reset($k); }
    if(!empty($_POST['attachments']))foreach((array)$_POST['attachments'] as $aid=>$at){
        $aid=(int)$aid;$p=get_post($aid);if(!$p||!current_user_can('edit_post',$aid))continue;
        $post=['ID'=>$aid,'post_title'=>$p->post_title,'post_excerpt'=>$p->post_excerpt,'post_content'=>$p->post_content,'menu_order'=>$p->menu_order,'post_parent'=>$p->post_parent];$orig=$post;
        foreach(['post_content','post_title','post_excerpt','menu_order'] as $k)if(isset($at[$k]))$post[$k]=$at[$k];
        if(isset($send_id)&&$aid==$send_id&&isset($at['post_parent']))$post['post_parent']=$at['post_parent'];
        $post=apply_filters('attachment_fields_to_save',$post,$at);
        if(isset($at['image_alt'])){ $alt=wp_unslash($at['image_alt']);if(get_post_meta($aid,'_wp_attachment_image_alt',true)!==$alt)update_post_meta($aid,'_wp_attachment_image_alt',wp_strip_all_tags($alt,true)); }
        if(isset($post['errors'])){ $errors[$aid]=$post['errors'];unset($post['errors']); }
        if($post!=$orig)wp_update_post($post);
        foreach(get_attachment_taxonomies($p) as $t)if(isset($at[$t]))wp_set_object_terms($aid,array_map('trim',preg_split('/,+/',$at[$t])),$t,false);
    }
    if(isset($_POST['insert-gallery'])||isset($_POST['update-gallery'])){ echo '<script>var win = window.dialogArguments || opener || parent || top; win.tb_remove();</script>';return ''; }
    if(isset($send_id)){
        $at=wp_unslash($_POST['attachments'][$send_id]);$html=$at['post_title']??'';
        if(!empty($at['url'])){ $rel='';if(str_contains($at['url'],'attachment_id')||get_attachment_link($send_id)===$at['url'])$rel=" rel='attachment wp-att-".esc_attr($send_id)."'";$html="<a href='{$at['url']}'$rel>$html</a>"; }
        $html=apply_filters('media_send_to_editor',$html,$send_id,$at);media_send_to_editor($html);return '';
    }
    return $errors;
} }
if(!function_exists('wp_media_upload_handler')){ function wp_media_upload_handler() {
    $errors=[];$id=0;
    if(isset($_POST['html-upload'])&&!empty($_FILES)){ check_admin_referer('media-form');$id=media_handle_upload('async-upload',$_REQUEST['post_id']??0);unset($_FILES);if(is_wp_error($id)){ $errors['upload_error']=$id;$id=false; } }
    if(!empty($_POST['insertonlybutton'])){
        $src=(string)($_POST['src']??'');if($src!==''&&!strpos($src,'://'))$src="http://$src";$html='';
        if(isset($_POST['media_type'])&&$_POST['media_type']!=='image'){
            $title=esc_html(wp_unslash($_POST['title']??''));if(empty($title))$title=esc_html(wp_basename($src));
            if($title&&$src)$html="<a href='".esc_url($src)."'>$title</a>";
            $type=in_array($_POST['media_type'],['audio','video'],true)?$_POST['media_type']:'file';
            $html=apply_filters("{$type}_send_to_editor_url",$html,$src,$title);
        } else {
            $alt=esc_attr(wp_unslash($_POST['alt']??''));$align='';$class='';
            if(isset($_POST['align'])){ $align=esc_attr(wp_unslash($_POST['align']));$class=" class='align$align'"; }
            if($src!=='')$html="<img src='".esc_url($src)."' alt='$alt'$class />";
            $html=apply_filters('image_send_to_editor_url',$html,esc_url_raw($src),$alt,$align);
        }
        media_send_to_editor($html);return '';
    }
    if(isset($_POST['save'])){ $errors['upload_notice']='Gespeichert.';return wp_iframe('media_upload_gallery_form',$errors); }
    elseif(!empty($_POST)){ $r=media_upload_form_handler();if(is_string($r))return $r;if(is_array($r))$errors=$r; }
    if(isset($_GET['tab'])&&$_GET['tab']==='type_url'){ $type='image';if(isset($_GET['type'])&&in_array($_GET['type'],['video','audio','file'],true))$type=$_GET['type'];return wp_iframe('media_upload_type_url_form',$type,$errors,$id); }
    return wp_iframe('media_upload_type_form','image',$errors,$id);
} }
if(!function_exists('media_upload_gallery')){ function media_upload_gallery() {
    $errors=[];if(!empty($_POST)){ $r=media_upload_form_handler();if(is_string($r))return $r;if(is_array($r))$errors=$r; }
    wp_enqueue_script('admin-gallery');return wp_iframe('media_upload_gallery_form',$errors);
} }
if(!function_exists('media_upload_library')){ function media_upload_library() {
    $errors=[];if(!empty($_POST)){ $r=media_upload_form_handler();if(is_string($r))return $r;if(is_array($r))$errors=$r; }
    return wp_iframe('media_upload_library_form',$errors);
} }

/* ───────── Anhang bearbeiten ───────── */
if(!function_exists('edit_form_image_editor')){ function edit_form_image_editor($post) {
    $id=(int)$post->ID;$alt=get_post_meta($id,'_wp_attachment_image_alt',true);$url=wp_get_attachment_url($id);
    echo '<p class="attachment-alt-text"><label for="attachment_alt"><strong>Alternativtext</strong></label><br /><input type="text" class="widefat" name="_wp_attachment_image_alt" id="attachment_alt" value="'.esc_attr($alt).'" /></p>';
    echo '<div class="wp_attachment_holder wp-clearfix">';
    if(wp_attachment_is_image($id)){ $t=wp_get_attachment_image_src($id,[900,450],true);echo '<p class="attachment"><a href="'.esc_url($url).'"><img class="thumbnail" src="'.esc_url($t?$t[0]:$url).'" alt="'.esc_attr($alt).'" /></a></p>'; }
    elseif(preg_match('#^audio/#',(string)$post->post_mime_type))echo wp_audio_shortcode(['src'=>$url]);
    elseif(preg_match('#^video/#',(string)$post->post_mime_type))echo wp_video_shortcode(['src'=>$url]);
    else do_action('wp_edit_form_attachment_display',$post);
    echo '</div><div class="wp_attachment_details edit-form-section"><p><label for="attachment_caption"><strong>Beschriftung</strong></label><br />';
    echo '<textarea class="widefat" name="excerpt" id="attachment_caption">'.esc_textarea((string)$post->post_excerpt).'</textarea></p>';
    echo '<p><label for="attachment_content"><strong>Beschreibung</strong></label><br /><textarea class="widefat" name="content" id="attachment_content">'.esc_textarea((string)$post->post_content).'</textarea></p></div>';
} }
if(!function_exists('attachment_submitbox_metadata')){ function attachment_submitbox_metadata() {
    $post=get_post();if(!$post)return;$id=$post->ID;$file=get_attached_file($id);$meta=wp_get_attachment_metadata($id);$dims='';
    if(isset($meta['width'],$meta['height']))$dims.="<span id='media-dims-$id'>{$meta['width']}&nbsp;&times;&nbsp;{$meta['height']}</span> ";
    $dims=apply_filters('media_meta',$dims,$post);
    echo '<div class="misc-pub-section misc-pub-attachment"><label for="attachment_url">Datei-URL:</label><input type="text" class="widefat urlfield" readonly="readonly" name="attachment_url" id="attachment_url" value="'.esc_attr((string)wp_get_attachment_url($id)).'" /></div>';
    echo '<div class="misc-pub-section misc-pub-filename">Dateiname: <strong>'.esc_html(wp_basename((string)$file)).'</strong></div>';
    echo '<div class="misc-pub-section misc-pub-filetype">Dateityp: <strong>'.($file?esc_html(strtoupper((string)wp_check_filetype($file)['ext'])):esc_html(strtoupper(str_replace('image/','',(string)$post->post_mime_type)))).'</strong></div>';
    $size=$meta['filesize']??($file&&file_exists($file)?filesize($file):false);
    if(!empty($size))echo '<div class="misc-pub-section misc-pub-filesize">Dateigröße: <strong>'.esc_html(size_format($size)).'</strong></div>';
    if(preg_match('#^(audio|video)/#',(string)$post->post_mime_type)&&!empty($meta['length_formatted']))echo '<div class="misc-pub-section misc-pub-length">Länge: <strong>'.esc_html($meta['length_formatted']).'</strong></div>';
    if($dims!=='')echo '<div class="misc-pub-section misc-pub-dimensions">Abmessungen: <strong>'.$dims.'</strong></div>';
} }
if(!function_exists('wp_media_attach_action')){ /** Hängt Medien an einen Beitrag an bzw. löst sie; gibt die Zahl der geänderten Zeilen zurück (WordPress leitet danach weiter). */
function wp_media_attach_action($parent_id,$action='attach') {
    global $wpdb;$parent_id=(int)$parent_id;if(!$parent_id)return false;
    if(!current_user_can('edit_post',$parent_id))wp_die('Sie dürfen diesen Beitrag nicht bearbeiten.');
    $ids=[];foreach((array)($_REQUEST['media']??[]) as $a){ $a=(int)$a;if($a&&current_user_can('edit_post',$a))$ids[]=$a; }
    if(!$ids)return 0;$in=implode(',',$ids);
    $r=$action==='attach'?$wpdb->query($wpdb->prepare("UPDATE {$wpdb->posts} SET post_parent = %d WHERE post_type = 'attachment' AND ID IN ( $in )",$parent_id)):$wpdb->query("UPDATE {$wpdb->posts} SET post_parent = 0 WHERE post_type = 'attachment' AND ID IN ( $in )");
    foreach($ids as $a){ do_action('wp_media_attach_action',$action,$a,$parent_id);clean_post_cache($a); }
    return $r;
} }

/* ───────── Metadaten aus Audio-/Videodateien (ohne getID3) ───────── */
if(!function_exists('wp_add_id3_tag_data')){ function wp_add_id3_tag_data(&$metadata,$data) {
    foreach(['id3v2','id3v1'] as $v){
        if(!empty($data[$v]['comments'])){ foreach($data[$v]['comments'] as $key=>$list){ if($key!=='length'&&!empty($list))$metadata[$key]=wp_kses_post(reset($list)); } break; }
    }
    if(!empty($data['id3v2']['APIC'])){ $p=reset($data['id3v2']['APIC']);if(!empty($p['data']))$metadata['image']=['data'=>$p['data'],'mime'=>$p['image_mime']??'','width'=>$p['image_width']??0,'height'=>$p['image_height']??0]; }
    elseif(!empty($data['comments']['picture'])){ $p=reset($data['comments']['picture']);if(!empty($p['data']))$metadata['image']=['data'=>$p['data'],'mime'=>$p['image_mime']??'']; }
} }
if(!function_exists('_elvado_m_duration')){ function _elvado_m_duration($sec) { $s=(int)round($sec);return $s>=3600?sprintf('%d:%02d:%02d',intdiv($s,3600),intdiv($s%3600,60),$s%60):sprintf('%d:%02d',intdiv($s,60),$s%60); } }
if(!function_exists('_elvado_m_mp4_info')){ /** Liest Dauer, Abmessungen und Erstellzeit aus dem moov-Kasten einer MP4/MOV-Datei. */
function _elvado_m_mp4_info($file) {
    $fh=@fopen($file,'rb');if(!$fh)return [];$size=filesize($file);$pos=0;$moov=null;
    while($pos+8<=$size){ fseek($fh,$pos);$h=fread($fh,8);if(strlen($h)<8)break;$len=unpack('N',substr($h,0,4))[1];$type=substr($h,4,4);$hl=8;
        if($len===1){ $e=fread($fh,8);$len=unpack('J',$e)[1];$hl=16; } elseif($len===0)$len=$size-$pos;
        if($len<$hl)break;
        if($type==='moov'){ $n=min($len-$hl,8388608);$moov=fread($fh,$n);break; }
        $pos+=$len; }
    fclose($fh);if($moov===null)return [];
    $out=[];$boxes=function(string $d,int $o=0) use(&$boxes){ $r=[];$n=strlen($d);while($o+8<=$n){ $l=unpack('N',substr($d,$o,4))[1];$t=substr($d,$o+4,4);if($l<8||$o+$l>$n)break;$r[]=[$t,substr($d,$o+8,$l-8)];$o+=$l; }return $r; };
    foreach($boxes($moov) as [$t,$d]){
        if($t==='mvhd'){ $v=ord($d[0]);
            if($v===1){ $c=unpack('J',substr($d,4,8))[1];$ts=unpack('N',substr($d,20,4))[1];$du=unpack('J',substr($d,24,8))[1]; } else { $c=unpack('N',substr($d,4,4))[1];$ts=unpack('N',substr($d,12,4))[1];$du=unpack('N',substr($d,16,4))[1]; }
            if($ts>0)$out['length']=(int)round($du/$ts);if($c>2082844800)$out['created_timestamp']=$c-2082844800; }
        elseif($t==='trak')foreach($boxes($d) as [$t2,$d2])if($t2==='tkhd'&&empty($out['width'])){ $off=ord($d2[0])===1?88:76;
            if(strlen($d2)>=$off+8){ $w=unpack('N',substr($d2,$off,4))[1]>>16;$h=unpack('N',substr($d2,$off+4,4))[1]>>16;if($w>0&&$h>0){ $out['width']=$w;$out['height']=$h; } } }
    }
    return $out;
} }
if(!function_exists('wp_read_video_metadata')){ function wp_read_video_metadata($file) {
    if(!file_exists($file)||!is_file($file))return false;
    $ft=wp_check_filetype($file);$ext=strtolower((string)$ft['ext']);
    $m=['filesize'=>filesize($file),'mime_type'=>(string)$ft['type'],'fileformat'=>$ext,'dataformat'=>in_array($ext,['mp4','m4v','mov'],true)?'quicktime':$ext];
    if(in_array($ext,['mp4','m4v','mov'],true)){ $i=_elvado_m_mp4_info($file);
        foreach(['width','height','length','created_timestamp'] as $k)if(isset($i[$k]))$m[$k]=$i[$k];
        if(isset($m['length']))$m['length_formatted']=_elvado_m_duration($m['length']); }
    return apply_filters('wp_read_video_metadata',$m,$file,$ext,[]);
} }
if(!function_exists('_elvado_m_id3_text')){ function _elvado_m_id3_text($d) {
    if($d==='')return '';$enc=ord($d[0]);$t=substr($d,1);
    $t=match($enc){ 1=>(str_starts_with($t,"\xFF\xFE")||str_starts_with($t,"\xFE\xFF")?mb_convert_encoding($t,'UTF-8','UTF-16'):mb_convert_encoding($t,'UTF-8','UTF-16LE')), 2=>mb_convert_encoding($t,'UTF-8','UTF-16BE'), 3=>$t, default=>mb_convert_encoding($t,'UTF-8','ISO-8859-1') };
    return trim(str_replace("\0",'',$t));
} }
if(!function_exists('_elvado_m_audio_tags')){ /** ID3v2/ID3v1 in die getID3-ähnliche Struktur ['id3v2'=>['comments'=>[Schlüssel=>[Wert]]]] umsetzen. */
function _elvado_m_audio_tags($file) {
    $fh=@fopen($file,'rb');if(!$fh)return [];$data=[];$head=fread($fh,10);
    if(strlen($head)===10&&str_starts_with($head,'ID3')){
        $ver=ord($head[3]);$sz=0;for($i=6;$i<10;$i++)$sz=($sz<<7)|(ord($head[$i])&0x7f);$body=$sz>0&&$sz<8388608?fread($fh,$sz):'';$o=0;$n=strlen($body);
        $map=['TIT2'=>'title','TPE1'=>'artist','TALB'=>'album','TYER'=>'year','TDRC'=>'year','TCON'=>'genre','TRCK'=>'track_number','TPE2'=>'band','TCOM'=>'composer'];
        while($ver>=3&&$o+10<=$n){ $id=substr($body,$o,4);if($id[0]==="\0")break;
            $l=$ver===4?((ord($body[$o+4])<<21)|(ord($body[$o+5])<<14)|(ord($body[$o+6])<<7)|ord($body[$o+7])):unpack('N',substr($body,$o+4,4))[1];
            if($l<=0||$o+10+$l>$n)break;
            if(isset($map[$id])){ $v=_elvado_m_id3_text(substr($body,$o+10,$l));if($v!=='')$data['id3v2']['comments'][$map[$id]][]=$v; }
            elseif($id==='APIC'&&!isset($data['id3v2']['APIC'])){ $f=substr($body,$o+10,$l);$p=strpos($f,"\0",1);if($p!==false){ $mime=substr($f,1,$p-1);$q=strpos($f,"\0",$p+2);if($q!==false)$data['id3v2']['APIC'][]=['data'=>substr($f,$q+1),'image_mime'=>$mime]; } }
            $o+=10+$l; }
    }
    if(!isset($data['id3v2'])&&filesize($file)>128){ fseek($fh,-128,SEEK_END);$t=fread($fh,128);
        if(str_starts_with($t,'TAG')){ foreach(['title'=>[3,30],'artist'=>[33,30],'album'=>[63,30],'year'=>[93,4]] as $k=>[$a,$b]){ $v=trim(str_replace("\0",'',substr($t,$a,$b)));if($v!=='')$data['id3v1']['comments'][$k][]=mb_convert_encoding($v,'UTF-8','ISO-8859-1'); } } }
    fclose($fh);return $data;
} }
if(!function_exists('wp_read_audio_metadata')){ function wp_read_audio_metadata($file) {
    if(!file_exists($file)||!is_file($file))return false;
    $ft=wp_check_filetype($file);$ext=strtolower((string)$ft['ext']);
    $m=['filesize'=>filesize($file),'mime_type'=>(string)$ft['type'],'fileformat'=>$ext,'dataformat'=>$ext];
    if($ext==='wav'){ $h=(string)@file_get_contents($file,false,null,0,44);if(strlen($h)===44&&substr($h,0,4)==='RIFF'){ $br=unpack('V',substr($h,28,4))[1];if($br>0)$m['length']=(int)round(($m['filesize']-44)/$br); } }
    elseif($ext==='mp3'){
        $fh=@fopen($file,'rb');$start=0;if($fh){ $hd=fread($fh,10);if(str_starts_with($hd,'ID3')){ $s=0;for($i=6;$i<10;$i++)$s=($s<<7)|(ord($hd[$i])&0x7f);$start=10+$s; }
            fseek($fh,$start);$buf=fread($fh,8192);fclose($fh);
            if(preg_match('/\xFF[\xE2-\xFF][\x10-\xEF]/s',$buf,$mm,PREG_OFFSET_CAPTURE)){ $b=substr($buf,$mm[0][1],4);$vb=(ord($b[1])>>3)&3;$br=ord($b[2])>>4;
                $t=$vb===3?[0,32,40,48,56,64,80,96,112,128,160,192,224,256,320]:[0,8,16,24,32,40,48,56,64,80,96,112,128,144,160];
                if($vb!==1&&isset($t[$br])&&$t[$br]>0){ $m['bitrate']=$t[$br]*1000;$m['length']=(int)round((filesize($file)-$start)*8/$m['bitrate']); } } } }
    if(isset($m['length']))$m['length_formatted']=_elvado_m_duration($m['length']);
    $tags=_elvado_m_audio_tags($file);if($tags)wp_add_id3_tag_data($m,$tags);
    return apply_filters('wp_read_audio_metadata',$m,$file,$ext,$tags);
} }
if(!function_exists('wp_get_media_creation_timestamp')){ function wp_get_media_creation_timestamp($metadata) {
    $ts=false;
    if(!empty($metadata['created_timestamp']))$ts=(int)$metadata['created_timestamp'];
    elseif(!empty($metadata['matroska']['comments']['creation_time'][0]))$ts=strtotime((string)$metadata['matroska']['comments']['creation_time'][0]);
    elseif(!empty($metadata['quicktime']['moov']['subatoms'])){ foreach($metadata['quicktime']['moov']['subatoms'] as $a)if(!empty($a['creation_time_unix'])){ $ts=(int)$a['creation_time_unix'];break; } }
    elseif(!empty($metadata['tags']['id3v2']['recording_time'][0]))$ts=strtotime((string)$metadata['tags']['id3v2']['recording_time'][0]);
    return $ts?:false;
} }

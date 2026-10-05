<?php
// Ergänzende Verwaltungs-Vorlagenfunktionen (wp-admin/includes/template.php): Begriffs-Checklisten, Schnellbearbeitung, Zeitstempel-Felder,
// Beitrags-/Medien-Status, Akkordeon, kleine Hinweise. Die Oberflächen sind schlanke Fassungen (Texte deutsch). Reine Browser-Prüfungen
// (compression_test) sind bewusst No-ops. Hilfsfunktionen beginnen mit _rrw_m_.

/* ───────── Begriffe ───────── */
if(!function_exists('wp_terms_checklist')){ function wp_terms_checklist($post_id=0,$args=[]) {
    $args=apply_filters('wp_terms_checklist_args',$args,$post_id);
    $p=wp_parse_args($args,['descendants_and_self'=>0,'selected_cats'=>false,'popular_cats'=>false,'walker'=>null,'taxonomy'=>'category','checked_ontop'=>true]);
    $tax=$p['taxonomy'];$taxo=get_taxonomy($tax);if(!$taxo)return;
    $name=$tax==='category'?'post_category':'tax_input['.$tax.']';
    if(is_array($p['selected_cats']))$sel=array_map('intval',$p['selected_cats']);
    elseif($post_id)$sel=array_map('intval',(array)wp_get_object_terms($post_id,$tax,['fields'=>'ids']));
    else $sel=$tax==='category'?[(int)get_option('default_category',1)]:[];
    $pop=is_array($p['popular_cats'])?array_map('intval',$p['popular_cats']):array_map('intval',(array)get_terms(['taxonomy'=>$tax,'fields'=>'ids','orderby'=>'count','order'=>'DESC','number'=>10,'hierarchical'=>false]));
    $terms=(array)get_terms(['taxonomy'=>$tax,'get'=>'all','hide_empty'=>false]);
    if($p['descendants_and_self']){ $root=(int)$p['descendants_and_self'];$kids=array_map('intval',(array)get_term_children($root,$tax));
        $terms=array_values(array_filter($terms,fn($t)=>$t->term_id===$root||in_array((int)$t->term_id,$kids,true))); }
    $by=[];foreach($terms as $t)$by[$p['descendants_and_self']&&(int)$t->term_id===(int)$p['descendants_and_self']?0:(int)($t->parent??0)][]=$t;
    $draw=function($t,$level) use(&$draw,$by,$tax,$name,$sel,$pop,$taxo){
        $id=(int)$t->term_id;$cls=in_array($id,$pop,true)?" class='popular-category'":'';
        $o="\n<li id='{$tax}-{$id}'$cls>".'<label class="selectit"><input value="'.$id.'" type="checkbox" name="'.esc_attr($name).'[]" id="in-'.$tax.'-'.$id.'"'.(in_array($id,$sel,true)?" checked='checked'":'').(current_user_can($taxo->cap->assign_terms??'edit_posts')?'':" disabled='disabled'").' /> '.esc_html(apply_filters('the_category',$t->name,'','')).'</label>';
        if(!empty($by[$id])&&!empty($taxo->hierarchical)){ $o.="\n<ul class='children'>";foreach($by[$id] as $c)$o.=$draw($c,$level+1);$o.="\n</ul>\n"; }
        return $o."</li>\n";
    };
    $top=!empty($taxo->hierarchical)?($by[0]??[]):$terms;
    if($p['checked_ontop']){ $on=array_filter($top,fn($t)=>in_array((int)$t->term_id,$sel,true));$top=array_merge($on,array_udiff($top,$on,fn($a,$b)=>$a->term_id<=>$b->term_id)); }
    foreach($top as $t)echo $draw($t,0);
} }
if(!function_exists('wp_category_checklist')){ function wp_category_checklist($post_id=0,$descendants_and_self=0,$selected_cats=false,$popular_cats=false,$walker=null,$checked_ontop=true) {
    wp_terms_checklist($post_id,['taxonomy'=>'category','descendants_and_self'=>$descendants_and_self,'selected_cats'=>$selected_cats,'popular_cats'=>$popular_cats,'walker'=>$walker,'checked_ontop'=>$checked_ontop]);
} }
if(!function_exists('wp_popular_terms_checklist')){ function wp_popular_terms_checklist($taxonomy,$default_term=0,$number=10,$display=true) {
    $post=get_post();$checked=$post&&$post->ID?array_map('intval',(array)wp_get_object_terms($post->ID,$taxonomy,['fields'=>'ids'])):[];
    $terms=get_terms(['taxonomy'=>$taxonomy,'orderby'=>'count','order'=>'DESC','number'=>$number,'hierarchical'=>false]);$tax=get_taxonomy($taxonomy);$ids=[];
    foreach((array)$terms as $t){ $ids[]=$t->term_id;if(!$display)continue;$id="popular-$taxonomy-$t->term_id";
        echo '<li id="'.$id.'" class="popular-category"><label class="selectit"><input id="in-'.$id.'" type="checkbox"'.(in_array((int)$t->term_id,$checked,true)?' checked="checked"':'').' value="'.(int)$t->term_id.'"'.(current_user_can($tax->cap->assign_terms??'edit_posts')?'':' disabled="disabled"').' /> '.esc_html(apply_filters('the_category',$t->name,'','')).'</label></li>'; }
    return $ids;
} }
if(!function_exists('wp_link_category_checklist')){ function wp_link_category_checklist($link_id=0) {
    if(!taxonomy_exists('link_category'))return;   // ohne Link-Verwaltung gibt es keine Link-Kategorien
    $checked=$link_id?array_map('intval',(array)wp_get_object_terms($link_id,'link_category',['fields'=>'ids'])):[1];
    foreach((array)get_terms(['taxonomy'=>'link_category','orderby'=>'name','hide_empty'=>0]) as $c){ $id=(int)$c->term_id;
        echo '<li id="link-category-'.$id.'"><label for="in-link-category-'.$id.'" class="selectit"><input value="'.$id.'" type="checkbox" name="link_category[]" id="in-link-category-'.$id.'"'.(in_array($id,$checked,true)?' checked="checked"':'').'/> '.esc_html(apply_filters('the_category',$c->name,'','')).'</label></li>'; }
} }

/* ───────── Schnellbearbeitung, Kommentare, Metafelder ───────── */
if(!function_exists('get_inline_data')){ function get_inline_data($post) {
    $o=get_post_type_object($post->post_type);if(!current_user_can('edit_post',$post->ID))return;
    echo '<div class="hidden" id="inline_'.$post->ID.'"><div class="post_title">'.esc_textarea(trim($post->post_title)).'</div><div class="post_name">'.apply_filters('editable_slug',$post->post_name,$post).'</div>';
    echo '<div class="post_author">'.$post->post_author.'</div><div class="comment_status">'.esc_html($post->comment_status).'</div><div class="ping_status">'.esc_html($post->ping_status).'</div><div class="_status">'.esc_html($post->post_status).'</div>';
    foreach(['jj'=>'d','mm'=>'m','aa'=>'Y','hh'=>'H','mn'=>'i','ss'=>'s'] as $c=>$f)echo '<div class="'.$c.'">'.mysql2date($f,$post->post_date,false).'</div>';
    echo '<div class="post_password">'.esc_html($post->post_password).'</div>';
    if($o&&$o->hierarchical)echo '<div class="post_parent">'.$post->post_parent.'</div>';
    echo '<div class="page_template">'.esc_html(get_page_template_slug($post)?:'default').'</div>';
    if(post_type_supports($post->post_type,'page-attributes'))echo '<div class="menu_order">'.$post->menu_order.'</div>';
    foreach(get_object_taxonomies($post->post_type) as $tn){ $t=get_taxonomy($tn);if(!$t||!($t->show_in_quick_edit??true))continue;
        $terms=(array)wp_get_object_terms($post->ID,$tn);
        if(!empty($t->hierarchical))echo '<div class="post_category" id="'.$tn.'_'.$post->ID.'">'.implode(',',wp_list_pluck($terms,'term_id')).'</div>';
        else echo '<div class="tags_input" id="'.$tn.'_'.$post->ID.'">'.esc_html(implode(', ',wp_list_pluck($terms,'name'))).'</div>'; }
    if($o&&!$o->hierarchical)echo '<div class="sticky">'.(is_sticky($post->ID)?'sticky':'').'</div>';
    if(post_type_supports($post->post_type,'post-formats'))echo '<div class="post_format">'.esc_html((string)get_post_format($post->ID)).'</div>';
    echo '</div>';
} }
if(!function_exists('wp_comment_reply')){ function wp_comment_reply($position=1,$checkbox=false,$mode='single',$table_row=true) {
    $c=apply_filters('wp_comment_reply','',['position'=>$position,'checkbox'=>$checkbox,'mode'=>$mode]);if($c!=='')return print $c;
    echo '<form method="get">'.($table_row?'<table style="display:none;"><tbody id="com-reply"><tr id="replyrow" class="inline-edit-row" style="display:none;"><td colspan="5" class="colspanchange">':'<div id="com-reply" style="display:none;"><div id="replyrow" style="display:none;">');
    echo '<fieldset class="comment-reply"><legend><span class="hidden" id="editlegend">Kommentar bearbeiten</span><span class="hidden" id="replyhead">Auf Kommentar antworten</span></legend>';
    echo '<div id="replycontainer"><label for="replycontent" class="screen-reader-text">Kommentar</label><textarea rows="8" cols="40" name="replycontent" id="replycontent" class="widefat"></textarea></div>';
    echo '<div id="edithead" style="display:none;"><div class="inside"><label for="author-name">Name</label><input type="text" name="newcomment_author" size="50" value="" id="author-name" /></div><div class="inside"><label for="author-email">E-Mail</label><input type="text" name="newcomment_author_email" size="50" value="" id="author-email" /></div><div class="inside"><label for="author-url">URL</label><input type="text" id="author-url" name="newcomment_author_url" class="code" size="103" value="" /></div></div>';
    echo '<p id="replysubmit" class="submit"><button type="button" class="save button button-primary">Antworten</button> <button type="button" class="cancel button">Abbrechen</button><span class="waiting spinner"></span></p>';
    foreach(['action'=>'','comment_ID'=>'','comment_post_ID'=>'','status'=>'','position'=>(string)$position,'checkbox'=>$checkbox?'1':'0','mode'=>$mode] as $k=>$v)echo '<input type="hidden" name="'.$k.'" id="'.($k==='comment_ID'?'comment_ID':($k==='comment_post_ID'?'comment_post_ID':$k)).'" value="'.esc_attr($v).'" />';
    wp_nonce_field('replyto-comment','_ajax_nonce-replyto-comment',false);wp_nonce_field('unfiltered-html-comment','_wp_unfiltered_html_comment_disabled',false);
    echo '</fieldset>'.($table_row?'</td></tr></tbody></table>':'</div></div>').'</form>';
} }
if(!function_exists('wp_comment_trashnotice')){ function wp_comment_trashnotice() {
    echo '<div class="hidden" id="trash-undo-holder"><div class="trash-undo-inside">Kommentar von <strong></strong> in den Papierkorb verschoben. <span class="undo untrash"><a href="#">Rückgängig machen</a></span></div></div>';
    echo '<div class="hidden" id="spam-undo-holder"><div class="spam-undo-inside">Kommentar von <strong></strong> als Spam markiert. <span class="undo unspam"><a href="#">Rückgängig machen</a></span></div></div>';
} }
if(!function_exists('_list_meta_row')){ function _list_meta_row($entry,&$count) {
    static $nonce='';if(is_protected_meta($entry['meta_key'],'post'))return '';
    if(!$nonce)$nonce=wp_create_nonce('add-meta');++$count;
    if(is_serialized($entry['meta_value'])){ if(is_serialized_string($entry['meta_value']))$entry['meta_value']=maybe_unserialize($entry['meta_value']);else{ --$count;return ''; } }
    $k=esc_attr($entry['meta_key']);$v=esc_textarea((string)$entry['meta_value']);$id=(int)$entry['meta_id'];$dn=wp_create_nonce('delete-meta_'.$id);
    return "\n\t<tr id='meta-$id'>\n\t\t<td class='left'><label class='screen-reader-text' for='meta-$id-key'>Schlüssel</label><input name='meta[$id][key]' id='meta-$id-key' type='text' size='20' value='$k' />\n\t\t<div class='submit'>"
        ."<input type='submit' name='deletemeta[$id]' id='deletemeta[$id]' class='button deletemeta button-small' value='Löschen' data-wp-lists='delete:the-list:meta-$id::_ajax_nonce=$dn' />\n\t\t"
        ."<input type='submit' name='meta-$id-submit' id='meta-$id-submit' class='button updatemeta button-small' value='Aktualisieren' data-wp-lists='add:the-list:meta-$id::_ajax_nonce-add-meta=$nonce' /></div>".wp_nonce_field('change-meta','_ajax_nonce',false,false)
        ."</td>\n\t\t<td><label class='screen-reader-text' for='meta-$id-value'>Wert</label><textarea name='meta[$id][value]' id='meta-$id-value' rows='2' cols='30'>$v</textarea></td>\n\t</tr>";
} }
if(!function_exists('meta_form')){ function meta_form($post=null) {
    global $wpdb;$post=get_post($post);$keys=apply_filters('postmeta_form_keys',null,$post);
    if($keys===null){ $limit=(int)apply_filters('postmeta_form_limit',30);$keys=[];
        foreach((array)$wpdb->get_col("SELECT DISTINCT meta_key FROM {$wpdb->postmeta} ORDER BY meta_key") as $k)if(!str_starts_with((string)$k,'_')&&count($keys)<$limit)$keys[]=$k; }
    if($keys)natcasesort($keys);
    echo '<p><strong>Neues benutzerdefiniertes Feld hinzufügen:</strong></p><table id="newmeta"><thead><tr><th class="left"><label for="metakeyselect">Name</label></th><th><label for="metavalue">Wert</label></th></tr></thead><tbody><tr><td id="newmetaleft" class="left">';
    if($keys){ echo '<select id="metakeyselect" name="metakeyselect"><option value="#NONE#">&mdash; Auswählen &mdash;</option>';
        foreach($keys as $k){ if(is_protected_meta($k,'post')||!(current_user_can('add_post_meta',$post->ID??0,$k)||current_user_can('edit_post',$post->ID??0)))continue;echo "\n<option value='".esc_attr($k)."'>".esc_html($k).'</option>'; }
        echo '</select><input class="hide-if-js" type="text" id="metakeyinput" name="metakeyinput" value="" /> <button type="button" id="newmeta-button" class="button button-small hide-if-no-js">Neu eingeben</button>'; }
    else echo '<input type="text" id="metakeyinput" name="metakeyinput" value="" />';
    echo '</td><td><textarea id="metavalue" name="metavalue" rows="2" cols="25"></textarea>';wp_nonce_field('add-meta','_ajax_nonce-add-meta',false);
    echo '</td></tr></tbody></table><div class="submit"><input type="submit" name="addmeta" id="newmeta-submit" class="button" value="Benutzerdefiniertes Feld hinzufügen" data-wp-lists="add:the-list:newmeta" /></div>';
} }
if(!function_exists('touch_time')){ function touch_time($edit=1,$for_post=1,$tab_index=0,$multi=0) {
    global $wp_locale;$post=get_post();
    if($for_post)$edit=!(in_array($post->post_status,['draft','pending'],true)&&(!$post->post_date_gmt||$post->post_date_gmt==='0000-00-00 00:00:00'));
    $ti=(int)$tab_index>0?" tabindex=\"$tab_index\"":'';$date=$for_post?$post->post_date:(get_comment()->comment_date??current_time('mysql'));
    $v=[];foreach(['jj'=>'d','mm'=>'m','aa'=>'Y','hh'=>'H','mn'=>'i','ss'=>'s'] as $k=>$f){ $v[$k]=$edit?mysql2date($f,$date,false):current_time($f);$cur[$k]=current_time($f); }
    $month='<label><span class="screen-reader-text">Monat</span><select class="form-required" '.($multi?'':'id="mm" ').'name="mm"'.$ti.">\n";
    for($i=1;$i<13;$i++){ $n=zeroise($i,2);$text=$wp_locale->month[$n]??$n;$ab=$wp_locale->month_abbrev[$text]??substr($text,0,3);
        $month.="\t\t\t".'<option value="'.$n.'" data-text="'.esc_attr($text).'" '.selected($n,$v['mm'],false).'>'.sprintf('%1$s-%2$s',$n,$ab)."</option>\n"; }
    $month.='</select></label>';
    $in=fn($k,$size,$lbl)=>'<label><span class="screen-reader-text">'.$lbl.'</span><input type="text" '.($multi?'':'id="'.$k.'" ').'name="'.$k.'" value="'.$v[$k].'" size="'.$size.'" maxlength="'.$size.'"'.$ti.' autocomplete="off"'.($k==='jj'||$k==='aa'?' class="form-required"':'').' /></label>';
    echo '<div class="timestamp-wrap">';printf('%1$s %2$s, %3$s um %4$s:%5$s',$month,$in('jj',2,'Tag'),$in('aa',4,'Jahr'),$in('hh',2,'Stunde'),$in('mn',2,'Minute'));echo '</div><input type="hidden" id="ss" name="ss" value="'.$v['ss'].'" />';
    if($multi)return;echo "\n\n";
    foreach(['mm','jj','aa','hh','mn'] as $k)echo '<input type="hidden" id="hidden_'.$k.'" name="hidden_'.$k.'" value="'.$v[$k].'" />'."\n".'<input type="hidden" id="cur_'.$k.'" name="cur_'.$k.'" value="'.$cur[$k].'" />'."\n";
    echo '<p><a href="#edit_timestamp" class="save-timestamp hide-if-no-js button">OK</a> <a href="#edit_timestamp" class="cancel-timestamp hide-if-no-js button-cancel">Abbrechen</a></p>';
} }
if(!function_exists('page_template_dropdown')){ function page_template_dropdown($default_template='',$post_type='page') {
    $t=get_page_templates(null,$post_type);ksort($t);
    foreach(array_keys($t) as $n)echo "\n\t<option value='".esc_attr($t[$n])."' ".selected($default_template,$t[$n],false).'>'.esc_html($n).'</option>';
} }
if(!function_exists('parent_dropdown')){ function parent_dropdown($default_page=0,$parent_page=0,$level=0,$post=null) {
    $post=get_post($post);
    $items=get_posts(['post_type'=>'page','post_parent'=>(int)$parent_page,'post_status'=>['publish','draft','pending','private','future'],'numberposts'=>-1,'orderby'=>'menu_order','order'=>'ASC','suppress_filters'=>true]);
    if(!$items)return false;
    foreach($items as $it){ if($post&&$post->ID&&(int)$it->ID===(int)$post->ID)continue;   // eine Seite kann nicht ihre eigene Elternseite sein
        echo "\n\t<option class='level-$level' value='$it->ID' ".selected($default_page,$it->ID,false).'>'.str_repeat('&nbsp;',$level*3).' '.esc_html($it->post_title).'</option>';
        parent_dropdown($default_page,$it->ID,$level+1); }
} }
if(!function_exists('wp_dropdown_roles')){ function wp_dropdown_roles($selected='') {
    $r='';foreach(array_reverse(get_editable_roles()) as $role=>$d){ $name=translate_user_role($d['name']);
        $r.="\n\t<option ".($selected===$role?"selected='selected' ":'')."value='".esc_attr($role)."'>$name</option>"; }
    echo $r;
} }
if(!function_exists('wp_import_upload_form')){ function wp_import_upload_form($action) {
    $bytes=apply_filters('import_upload_size_limit',wp_max_upload_size());$size=size_format($bytes);$ud=wp_upload_dir();
    if(!empty($ud['error'])){ echo '<div class="error"><p>Bevor Sie importieren können, muss das Hochladen funktionieren. Der Upload-Ordner hat ein Problem:</p><p><strong>'.esc_html($ud['error']).'</strong></p></div>';return; }
    echo '<form enctype="multipart/form-data" id="import-upload-form" method="post" class="wp-upload-form" action="'.esc_url(wp_nonce_url($action,'import-upload')).'"><p><label for="upload">Wählen Sie eine Datei von Ihrem Computer:</label> (Maximale Größe: '.esc_html($size).')';
    echo '<input type="file" id="upload" name="import" size="25" /><input type="hidden" name="action" value="save" /><input type="hidden" name="max_file_size" value="'.(int)$bytes.'" /></p>';
    submit_button('Datei hochladen und importieren','primary');echo '</form>';
} }

/* ───────── Meta-Boxen und Akkordeon ───────── */
if(!function_exists('_get_plugin_from_callback')){ function _get_plugin_from_callback($callback) {
    try{ if(is_array($callback))$r=new ReflectionMethod($callback[0],$callback[1]);elseif(is_string($callback)&&str_contains($callback,'::'))$r=new ReflectionMethod($callback);else $r=new ReflectionFunction($callback); }
    catch(ReflectionException|TypeError $e){ return null; }
    $file=wp_normalize_path((string)$r->getFileName());$dir=wp_normalize_path(WP_PLUGIN_DIR);if(!str_starts_with($file,$dir.'/'))return null;
    $folder=explode('/',ltrim(substr($file,strlen($dir)),'/'))[0];
    foreach(get_plugins() as $pf=>$data)if(str_starts_with($pf,$folder.'/')||$pf===$folder)return $data;
    return null;
} }
if(!function_exists('do_block_editor_incompatible_meta_box')){ function do_block_editor_incompatible_meta_box($data_object,$box) {
    $plugin=_get_plugin_from_callback($box['callback']??'');$plugins=get_plugins();echo '<p>';
    echo $plugin?sprintf('Diese Meta-Box des Plugins %s ist nicht mit dem Block-Editor kompatibel.',"<strong>{$plugin['Name']}</strong>"):'Diese Meta-Box ist nicht mit dem Block-Editor kompatibel.';echo '</p>';
    if(empty($plugins['classic-editor/classic-editor.php'])){ if(current_user_can('install_plugins'))printf('<p>Bitte installieren Sie das <a href="%s">Classic-Editor-Plugin</a>, um diese Meta-Box zu nutzen.</p>',esc_url(wp_nonce_url(self_admin_url('plugin-install.php?tab=favorites&user=wordpressdotorg&save=0'),'save_wporg_username_'.get_current_user_id()))); }
    elseif(is_plugin_inactive('classic-editor/classic-editor.php')){ if(current_user_can('activate_plugins'))printf('<p>Bitte aktivieren Sie das <a href="%s">Classic-Editor-Plugin</a>, um diese Meta-Box zu nutzen.</p>',esc_url(wp_nonce_url(self_admin_url('plugins.php?action=activate&plugin=classic-editor/classic-editor.php'),'activate-plugin_classic-editor/classic-editor.php'))); }
    elseif($data_object instanceof WP_Post)printf('<p>Bitte öffnen Sie den <a href="%s">klassischen Editor</a>, um diese Meta-Box zu nutzen.</p>',esc_url(add_query_arg(['classic-editor'=>'','classic-editor__forget'=>''],(string)get_edit_post_link($data_object))));
} }
if(!function_exists('do_accordion_sections')){ function do_accordion_sections($screen,$context,$data_object) {
    global $wp_meta_boxes;wp_enqueue_script('accordion');
    if(empty($screen))$screen=get_current_screen();elseif(is_string($screen))$screen=(object)['id'=>$screen];
    $page=is_object($screen)?$screen->id:(string)$screen;$hidden=function_exists('get_hidden_meta_boxes')?(array)get_hidden_meta_boxes($screen):[];
    echo '<div id="side-sortables" class="accordion-container"><ul class="outer-border">';$i=0;$open=false;
    if(isset($wp_meta_boxes[$page][$context]))foreach(['high','core','default','low'] as $prio)foreach($wp_meta_boxes[$page][$context][$prio]??[] as $box){
        if($box===false||empty($box['title']))continue;++$i;$hc=in_array($box['id'],$hidden,true)?'hide-if-js':'';$oc='';
        if(!$open&&empty($_GET['open-style'])){ $open=true;$oc='open'; }
        echo '<li class="control-section accordion-section '.$hc.' '.$oc.' '.esc_attr($box['id']).'" id="'.esc_attr($box['id']).'"><h3 class="accordion-section-title hndle" tabindex="0">'.esc_html($box['title']).'</h3><div class="accordion-section-content postbox"><div class="inside">';
        call_user_func($box['callback'],$data_object,$box);echo '</div></div></li>';
    }
    echo '</ul></div>';return $i;
} }
if(!function_exists('find_posts_div')){ function find_posts_div($found_action='') {
    echo '<div id="find-posts" class="find-box" style="display: none;"><div id="find-posts-head" class="find-box-head">Anhängen an vorhandenen Inhalt<button type="button" id="find-posts-close"><span class="screen-reader-text">Schließen</span></button></div>';
    echo '<div class="find-box-inside"><div class="find-box-search">';if($found_action)echo '<input type="hidden" name="found_action" value="'.esc_attr($found_action).'" />';
    echo '<input type="hidden" name="affected" id="affected" value="" />';wp_nonce_field('find-posts','_ajax_nonce',false);
    echo '<label class="screen-reader-text" for="find-posts-input">Suche</label><input type="text" id="find-posts-input" name="ps" value="" /><span class="spinner"></span><input type="button" id="find-posts-search" value="Suchen" class="button" /><div class="clear"></div></div><div id="find-posts-response"></div></div>';
    echo '<div class="find-box-buttons"><input id="find-posts-close-button" type="button" class="button alignleft" value="Schließen" /><input id="find-posts-submit" type="submit" class="button button-primary alignright" value="Auswählen" /></div></div>';
} }
if(!function_exists('the_post_password')){ function the_post_password() { $p=get_post();if(isset($p->post_password))echo esc_attr($p->post_password); } }

/* ───────── Rahmen für Popup-Seiten ───────── */
if(!function_exists('iframe_header')){ function iframe_header($title='',$deprecated=false) {
    global $hook_suffix,$admin_body_class;show_admin_bar(false);$hook_suffix=(string)$hook_suffix;
    $admin_body_class=preg_replace('/[^a-z0-9_-]+/i','-',$hook_suffix);_wp_admin_html_begin();
    echo '<title>'.esc_html(get_bloginfo('name')).' &rsaquo; '.$title.' &#8212; WordPress</title>';wp_enqueue_style('colors');
    echo '<script>var ajaxurl = '.wp_json_encode(admin_url('admin-ajax.php','relative')).', pagenow = '.wp_json_encode($hook_suffix).';</script>';
    do_action('admin_enqueue_scripts',$hook_suffix);do_action("admin_print_styles-{$hook_suffix}");do_action('admin_print_styles');do_action("admin_print_scripts-{$hook_suffix}");do_action('admin_print_scripts');do_action("admin_head-{$hook_suffix}");do_action('admin_head');
    $admin_body_class.=' locale-'.sanitize_html_class(strtolower(str_replace('_','-',get_user_locale())));if(is_rtl())$admin_body_class.=' rtl';
    $cls=ltrim(apply_filters('admin_body_class','').' '.$admin_body_class);
    echo '</head><body class="wp-admin wp-core-ui no-js iframe '.esc_attr($cls).'"><script>document.body.className = document.body.className.replace(/no-js/, "js");</script>';
} }
if(!function_exists('iframe_footer')){ function iframe_footer() {
    global $hook_suffix;echo '<div class="hidden">';do_action('admin_footer',(string)$hook_suffix);do_action("admin_print_footer_scripts-{$hook_suffix}");do_action('admin_print_footer_scripts');
    echo '</div><script>if(typeof wpOnload==="function")wpOnload();</script></body></html>';
} }

/* ───────── Status und Hinweise ───────── */
if(!function_exists('get_post_states')){ function get_post_states($post) {
    $s=[];$ps=$_REQUEST['post_status']??'';
    if(!empty($post->post_password))$s['protected']='Passwortgeschützt';
    if($post->post_status==='private'&&$ps!=='private')$s['private']='Privat';
    if($post->post_status==='draft'){ if(get_post_meta($post->ID,'_customize_changeset_uuid',true))$s[]='Customizer-Entwurf';elseif($ps!=='draft')$s['draft']='Entwurf'; }
    elseif($post->post_status==='trash'){ if(get_post_meta($post->ID,'_customize_changeset_uuid',true))$s[]='Customizer-Entwurf'; }
    if($post->post_status==='pending'&&$ps!=='pending')$s['pending']='Ausstehend';
    if(is_sticky($post->ID))$s['sticky']='Angeheftet';
    if($post->post_status==='future')$s['scheduled']='Geplant';
    if(get_option('show_on_front')==='page'){ if((int)get_option('page_on_front')===(int)$post->ID)$s['page_on_front']='Startseite';if((int)get_option('page_for_posts')===(int)$post->ID)$s['page_for_posts']='Beitragsseite'; }
    if((int)get_option('wp_page_for_privacy_policy')===(int)$post->ID)$s['page_for_privacy_policy']='Datenschutzseite';
    return apply_filters('display_post_states',$s,$post);
} }
if(!function_exists('get_media_states')){ function get_media_states($post) {
    $m=[];$ss=get_option('stylesheet');
    if(current_theme_supports('custom-header')){
        $mh=get_post_meta($post->ID,'_wp_attachment_is_custom_header',true);$url=wp_get_attachment_url($post->ID);
        if(is_random_header_image()){ $ids=wp_list_pluck(get_uploaded_header_images(),'attachment_id');if($mh===$ss&&in_array($post->ID,$ids,true))$m[]='Kopfbild'; }
        else{ $hi=get_header_image();if(!empty($mh)&&$mh===$ss&&$url!==$hi)$m[]='Kopfbild';if($hi&&$url===$hi)$m[]='Aktuelles Kopfbild'; }
        if(get_theme_support('custom-header')&&_rrw_m_th('custom-header','video')&&has_header_video()){ $mods=get_theme_mods();if(isset($mods['header_video'])&&(int)$post->ID===(int)$mods['header_video'])$m[]='Aktuelles Kopfvideo'; }
    }
    if(current_theme_supports('custom-background')&&get_post_meta($post->ID,'_wp_attachment_is_custom_background',true)===$ss){
        $m[]='Hintergrundbild';$bi=get_background_image();if($bi&&wp_get_attachment_url($post->ID)===$bi)$m[]='Aktuelles Hintergrundbild'; }
    if((int)get_option('site_icon')===(int)$post->ID)$m[]='Website-Icon';
    if((int)get_theme_mod('custom_logo')===(int)$post->ID)$m[]='Logo';
    return apply_filters('display_media_states',$m,$post);
} }
if(!function_exists('_media_states')){ function _media_states($post) {
    $m=get_media_states($post);if(!$m)return;$n=count($m);$i=0;echo ' &mdash; ';
    foreach($m as $s){ ++$i;echo "<span class='post-state'>{$s}".($i<$n?', ':'').'</span>'; }
} }
if(!function_exists('compression_test')){ /** Bewusst ohne Ausgabe: Der Browsertest für die Skript-Komprimierung entfällt (kein Verketten/Komprimieren). */
function compression_test() {} }
if(!function_exists('_local_storage_notice')){ function _local_storage_notice() {
    echo '<div id="local-storage-notice" class="hidden notice is-dismissible"><p class="local-restore">Dieser Beitrag wurde aus einer lokal gespeicherten Sicherung wiederhergestellt. <button type="button" class="button restore-backup">Sicherung wiederherstellen</button></p>';
    echo '<p class="help">Das passiert, wenn der Browser abstürzt oder die Seite versehentlich verlassen wird.</p></div>';
} }
if(!function_exists('wp_star_rating')){ function wp_star_rating($args=[]) {
    $a=wp_parse_args($args,['rating'=>0,'type'=>'rating','number'=>0,'echo'=>true]);$r=(float)str_replace(',','.',(string)$a['rating']);
    if($a['type']==='percent')$r=round($r/10,0)/2;
    $full=(int)floor($r);$half=(int)ceil($r-$full);$empty=max(0,5-$full-$half);
    $title=$a['number']?sprintf('%1$s Bewertung(en) basierend auf %2$s Stimmen',number_format_i18n($r,1),number_format_i18n($a['number'])):sprintf('%s Bewertung',number_format_i18n($r,1));
    $o='<div class="star-rating"><span class="screen-reader-text">'.$title.'</span>'.str_repeat('<div class="star star-full" aria-hidden="true"></div>',$full).str_repeat('<div class="star star-half" aria-hidden="true"></div>',$half).str_repeat('<div class="star star-empty" aria-hidden="true"></div>',$empty).'</div>';
    if($a['echo'])echo $o;return $o;
} }
if(!function_exists('_wp_posts_page_notice')){ function _wp_posts_page_notice() { printf('<div class="notice notice-warning inline"><p>%s</p></div>','Sie bearbeiten gerade die Seite, die Ihre neuesten Beiträge anzeigt.'); } }
if(!function_exists('_wp_block_editor_posts_page_notice')){ function _wp_block_editor_posts_page_notice() {
    wp_add_inline_script('wp-notices',sprintf('wp.data.dispatch( "core/notices" ).createWarningNotice( %s, { isDismissible: false } )',wp_json_encode('Sie bearbeiten gerade die Seite, die Ihre neuesten Beiträge anzeigt.')),'after');
} }

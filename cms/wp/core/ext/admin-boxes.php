<?php
// Ergänzende WordPress-Funktionen (Bereich Admin, Teil 6): Meta-Boxen (meta-boxes.php) und Navigationsmenü-Verwaltung (nav-menu.php).
// Die Boxen geben schlankes HTML aus (Felder wie bei WordPress benannt: post_excerpt, post_name, post_author_override …); die Speicherung übernehmen edit_post()/wp_update_post().
// Die Menüs des CMS (top/bottom) werden in der CMS-Verwaltung gepflegt; wp_update_nav_menu_item() ist ein Platzhalter, daher legen die Speicher-Funktionen hier keine Menüeinträge an.

/* ───────── Beitrags-Boxen ───────── */
if(!function_exists('post_submit_meta_box')){ function post_submit_meta_box($post, $args=[]) {   // Box „Veröffentlichen“
    $pt=get_post_type_object($post->post_type);$can=current_user_can($pt->cap->publish_posts);$st=$post->post_status;
    echo '<div class="submitbox" id="submitpost"><div id="minor-publishing"><div id="minor-publishing-actions">';
    if(!in_array($st,['publish','future','private'],true)||0==$post->ID)echo '<input type="submit" name="save" id="save-post" value="'.esc_attr('Entwurf speichern').'" class="button" />';
    echo '<a class="preview button" href="'.esc_url(get_preview_post_link($post)).'" target="wp-preview-'.(int)$post->ID.'" id="post-preview">Vorschau</a>';
    echo '<input type="hidden" name="wp-preview" id="wp-preview" value="" /></div><div id="misc-publishing-actions">';
    $labels=['publish'=>'Veröffentlicht','private'=>'Privat','future'=>'Geplant','pending'=>'Wartet auf Überprüfung','draft'=>'Entwurf','auto-draft'=>'Entwurf'];
    echo '<div class="misc-pub-section misc-pub-post-status">Status: <span id="post-status-display">'.esc_html($labels[$st]??$st).'</span></div>';
    echo '<div class="misc-pub-section misc-pub-visibility" id="visibility">Sichtbarkeit: <span id="post-visibility-display">'.esc_html(!empty($post->post_password)?'Passwortgeschützt':('private'===$st?'Privat':'Öffentlich')).'</span></div>';
    $d=0!==(int)$post->ID&&'0000-00-00 00:00:00'!==$post->post_date_gmt?mysql2date('j. M Y H:i',$post->post_date):'';
    echo '<div class="misc-pub-section curtime misc-pub-curtime"><span id="timestamp">'.($d?esc_html(('future'===$st?'Geplant für: ':('publish'===$st||'private'===$st?'Veröffentlicht am: ':'Veröffentlichen: ')).$d):'Sofort veröffentlichen').'</span></div>';
    do_action('post_submitbox_misc_actions',$post);
    echo '</div><div class="clear"></div></div><div id="major-publishing-actions">';
    do_action('post_submitbox_start',$post);
    echo '<div id="delete-action">';
    if(current_user_can('delete_post',$post->ID))echo '<a class="submitdelete deletion" href="'.esc_url(get_delete_post_link($post->ID)?:admin_url('post.php?post='.(int)$post->ID.'&action=trash')).'">'.(!EMPTY_TRASH_DAYS?'Endgültig löschen':'In den Papierkorb legen').'</a>';
    echo '</div><div id="publishing-action"><span class="spinner"></span>';
    if(!in_array($st,['publish','future','private'],true)||0==$post->ID){
        if($can)echo '<input type="submit" name="publish" id="publish" class="button button-primary button-large" value="'.esc_attr(!empty($post->post_date_gmt)&&time()<strtotime($post->post_date_gmt.' +0000')?'Planen':'Veröffentlichen').'" />';
        else echo '<input type="submit" name="publish" id="publish" class="button button-primary button-large" value="Zur Überprüfung einreichen" />';
    } else echo '<input type="submit" name="save" id="publish" class="button button-primary button-large" value="Aktualisieren" />';
    echo '</div><div class="clear"></div></div></div>';
} }
if(!function_exists('attachment_submit_meta_box')){ function attachment_submit_meta_box($post) {
    echo '<div class="submitbox" id="submitpost"><div id="misc-publishing-actions"><div class="misc-pub-section curtime"><span id="timestamp">Hochgeladen am: '.esc_html(mysql2date('j. M Y H:i',$post->post_date)).'</span></div>';
    do_action('attachment_submitbox_misc_actions',$post);
    echo '</div><div id="major-publishing-actions"><div id="delete-action">';
    if(current_user_can('delete_post',$post->ID))echo '<a class="submitdelete deletion" href="'.esc_url(wp_nonce_url(admin_url('post.php?action=delete&post='.(int)$post->ID),'delete-post_'.(int)$post->ID)).'">Endgültig löschen</a>';
    echo '</div><div id="publishing-action"><input type="submit" name="save" id="publish" class="button button-primary button-large" value="Aktualisieren" /></div><div class="clear"></div></div></div>';
} }
if(!function_exists('post_format_meta_box')){ function post_format_meta_box($post, $box) {
    if(!current_theme_supports('post-formats')||!post_type_supports($post->post_type,'post-formats'))return;
    $formats=get_theme_support('post-formats');if(!is_array($formats)||empty($formats[0]))return;
    $formats=array_merge(['standard'],array_intersect($formats[0],array_keys(get_post_format_strings())));
    $cur=get_post_format($post->ID);if(!$cur)$cur='standard';
    $strings=get_post_format_strings();
    echo '<div id="post-formats-select"><fieldset><legend class="screen-reader-text">Beitragsformate</legend><input type="radio" name="post_format" class="post-format" id="post-format-0" value="0" '.checked($cur,'standard',false).' />';
    foreach($formats as $f){ if('standard'===$f)continue;
        echo ' <input type="radio" name="post_format" class="post-format" id="post-format-'.esc_attr($f).'" value="'.esc_attr($f).'" '.checked($cur,$f,false).' /> <label for="post-format-'.esc_attr($f).'" class="post-format-icon post-format-'.esc_attr($f).'">'.esc_html($strings[$f]??$f).'</label><br />'; }
    echo '</fieldset></div>';
} }
if(!function_exists('post_excerpt_meta_box')){ function post_excerpt_meta_box($post) {
    echo '<label class="screen-reader-text" for="excerpt">Textauszug</label><textarea rows="1" cols="40" name="excerpt" id="excerpt">'.esc_textarea($post->post_excerpt).'</textarea>';
    echo '<p>Textauszüge sind handgeschriebene Zusammenfassungen deines Inhalts, die in Themes angezeigt werden können.</p>';
} }
if(!function_exists('post_trackback_meta_box')){ function post_trackback_meta_box($post) {
    $f=(bool)get_option('use_trackback')||true;
    echo '<p><label for="trackback_url">Trackbacks senden an:</label> <input type="text" name="trackback_url" id="trackback_url" class="code" value="'.esc_attr(str_replace("\n",' ',(string)$post->to_ping)).'" /></p>';
    echo '<p>Trenne mehrere URLs durch Leerzeichen.</p>';
    if(!empty($post->pinged)){ echo '<p>Bereits benachrichtigt:</p><ul>';foreach(array_filter(explode("\n",trim((string)$post->pinged))) as $u)echo '<li>'.esc_html($u).'</li>';echo '</ul>'; }
} }
if(!function_exists('post_custom_meta_box')){ function post_custom_meta_box($post) {   // Eigene Felder: Liste und Formular zum Hinzufügen
    $rows=has_meta($post->ID);$c=0;
    echo '<div id="postcustomstuff"><table id="list-table"><thead><tr><th>Name</th><th>Wert</th></tr></thead><tbody id="the-list">';
    foreach($rows as $r){ if(is_protected_meta($r['meta_key'],'post'))continue;echo _list_meta_row($r,$c); }
    echo '</tbody></table>';
    echo '<p><strong>Neues Feld hinzufügen:</strong></p><table id="newmeta"><tr><td><input type="text" id="metakeyinput" name="metakeyinput" value="" /></td><td><textarea id="metavalue" name="metavalue" rows="2" cols="25"></textarea>';
    wp_nonce_field('add-meta','_ajax_nonce-add-meta',false);
    echo '</td></tr></table></div><p>Eigene Felder können für zusätzliche Angaben zu deinem Inhalt genutzt werden.</p>';
} }
if(!function_exists('post_comment_status_meta_box')){ function post_comment_status_meta_box($post) {
    echo '<input name="advanced_view" type="hidden" value="1" /><p class="meta-options"><label for="comment_status" class="selectit"><input name="comment_status" type="checkbox" id="comment_status" value="open" '.checked($post->comment_status,'open',false).' /> Kommentare erlauben</label><br />';
    echo '<label for="ping_status" class="selectit"><input name="ping_status" type="checkbox" id="ping_status" value="open" '.checked($post->ping_status,'open',false).' /> Pingbacks und Trackbacks erlauben</label></p>';
    do_action('post_comment_status_meta_box-options',$post);
} }
if(!function_exists('post_comment_meta_box_thead')){ function post_comment_meta_box_thead($result) { unset($result['cb'],$result['response']);return $result; } }
if(!function_exists('post_comment_meta_box')){ function post_comment_meta_box($post) {
    wp_nonce_field('get-comments','add_comment_nonce',false);
    echo '<p class="hide-if-no-js" id="add-new-comment"><a role="button" href="#commentstatusdiv">Kommentar hinzufügen</a></p>';
    $total=get_comments(['post_id'=>$post->ID,'count'=>true,'orderby'=>'none']);
    if($total){ echo '<table class="widefat comments-box"><tbody id="the-comment-list">';foreach(get_comments(['post_id'=>$post->ID,'number'=>10,'status'=>'all'])?:[] as $c)echo elvado_adm_comment_row($c);echo '</tbody></table>'; }
    else echo '<p class="hide-if-no-js">Noch keine Kommentare.</p>';
} }
if(!function_exists('post_slug_meta_box')){ function post_slug_meta_box($post) {
    echo '<label class="screen-reader-text" for="post_name">Titelform</label><input name="post_name" type="text" class="large-text" id="post_name" value="'.esc_attr(apply_filters('editable_slug',$post->post_name,$post)).'" autocomplete="off" />';
} }
if(!function_exists('post_author_meta_box')){ function post_author_meta_box($post) {
    $uid=empty($post->ID)?get_current_user_id():$post->post_author;
    echo '<label class="screen-reader-text" for="post_author_override">Autor</label>';
    wp_dropdown_users(['capability'=>['edit_posts'],'name'=>'post_author_override','selected'=>$uid,'include_selected'=>true,'show'=>'display_name_with_login']);
} }
if(!function_exists('post_revisions_meta_box')){ function post_revisions_meta_box($post) { wp_list_post_revisions($post); } }
if(!function_exists('page_attributes_meta_box')){ function page_attributes_meta_box($post) {
    $pt=get_post_type_object($post->post_type);
    if($pt->hierarchical){
        $dd=wp_dropdown_pages(['post_type'=>$post->post_type,'exclude_tree'=>$post->ID,'selected'=>$post->post_parent,'name'=>'parent_id','show_option_none'=>'(kein übergeordnetes Element)','sort_column'=>'menu_order, post_title','echo'=>0]);
        if($dd)echo '<p class="post-attributes-label-wrapper parent-id-label-wrapper"><label class="post-attributes-label" for="parent_id">Übergeordnet</label></p>'.$dd;
    }
    echo '<p class="post-attributes-label-wrapper menu-order-label-wrapper"><label class="post-attributes-label" for="menu_order">Reihenfolge</label></p><input name="menu_order" type="text" size="4" id="menu_order" value="'.esc_attr($post->menu_order).'" />';
    do_action('page_attributes_misc_attributes',$post);
} }
if(!function_exists('post_thumbnail_meta_box')){ function post_thumbnail_meta_box($post) { echo _wp_post_thumbnail_html(get_post_thumbnail_id($post->ID),$post->ID); } }
if(!function_exists('attachment_id3_data_meta_box')){ function attachment_id3_data_meta_box($post) {   // Audio-/Video-Metadaten (ID3)
    $m=wp_get_attachment_metadata($post->ID);if(!is_array($m))$m=[];
    echo '<dl class="attachment-id3-data">';
    foreach(wp_get_attachment_id3_keys($post,'edit') as $k=>$label){ if(!empty($m[$k]))echo '<dt>'.esc_html($label).'</dt><dd>'.esc_html(is_scalar($m[$k])?(string)$m[$k]:wp_json_encode($m[$k])).'</dd>'; }
    echo '</dl>';
} }
if(!function_exists('register_and_do_post_meta_boxes')){ function register_and_do_post_meta_boxes($post) {   // Standard-Boxen eintragen, Plugin-Hooks auslösen und ausgeben
    $pt=$post->post_type;$obj=get_post_type_object($pt);if(!$obj)return;
    $thumb=current_theme_supports('post-thumbnails',$pt)&&post_type_supports($pt,'thumbnail');
    if('attachment'===$pt)elvado_adm_add_box('submitdiv','Veröffentlichen','attachment_submit_meta_box',$pt,'side','core');
    else elvado_adm_add_box('submitdiv','Veröffentlichen','post_submit_meta_box',$pt,'side','core');
    if(current_theme_supports('post-formats')&&post_type_supports($pt,'post-formats'))elvado_adm_add_box('formatdiv','Format','post_format_meta_box',$pt,'side','core');
    if($thumb)elvado_adm_add_box('postimagediv','Beitragsbild','post_thumbnail_meta_box',$pt,'side','low');
    if(post_type_supports($pt,'excerpt'))elvado_adm_add_box('postexcerpt','Textauszug','post_excerpt_meta_box',$pt,'normal','core');
    if(post_type_supports($pt,'trackbacks'))elvado_adm_add_box('trackbacksdiv','Trackbacks senden','post_trackback_meta_box',$pt);
    if(post_type_supports($pt,'custom-fields'))elvado_adm_add_box('postcustom','Eigene Felder','post_custom_meta_box',$pt);
    if(post_type_supports($pt,'comments'))elvado_adm_add_box('commentstatusdiv','Diskussion','post_comment_status_meta_box',$pt);
    if(('publish'===$post->post_status||'private'===$post->post_status)&&post_type_supports($pt,'comments'))elvado_adm_add_box('commentsdiv','Kommentare','post_comment_meta_box',$pt);
    if(!('pending'===$post->post_status&&!current_user_can($obj->cap->publish_posts)))elvado_adm_add_box('slugdiv','Titelform','post_slug_meta_box',$pt);
    if(post_type_supports($pt,'author')&&current_user_can($obj->cap->edit_others_posts))elvado_adm_add_box('authordiv','Autor','post_author_meta_box',$pt);
    if(post_type_supports($pt,'revisions')&&0<$post->ID&&wp_get_post_revisions($post->ID,['posts_per_page'=>1]))elvado_adm_add_box('revisionsdiv','Revisionen','post_revisions_meta_box',$pt);
    if(post_type_supports($pt,'page-attributes'))elvado_adm_add_box('pageparentdiv','Seiten-Attribute','page_attributes_meta_box',$pt,'side','core');
    do_action('add_meta_boxes',$pt,$post);do_action("add_meta_boxes_{$pt}",$post);do_action('do_meta_boxes',$pt,'normal',$post);do_action('do_meta_boxes',$pt,'advanced',$post);do_action('do_meta_boxes',$pt,'side',$post);
    foreach(['side','normal','advanced'] as $ctx){ echo '<div id="'.$ctx.'-sortables" class="meta-box-sortables">';elvado_adm_do_boxes($pt,$ctx,$post);echo '</div>'; }
} }

/* ───────── Navigationsmenüs ───────── */
if(!function_exists('wp_nav_menu_setup')){ function wp_nav_menu_setup() {
    wp_nav_menu_post_type_meta_boxes();
    elvado_adm_add_box('add-custom-links','Individuelle Links','wp_nav_menu_item_link_meta_box','nav-menus','side','default');
    wp_nav_menu_taxonomy_meta_boxes();
    add_filter('manage_nav-menus_columns','wp_nav_menu_manage_columns');
    if(false===get_user_option('managenav-menuscolumnshidden'))update_user_option(get_current_user_id(),'managenav-menuscolumnshidden',['link-target','css-classes','xfn','description','title-attribute'],true);
} }
if(!function_exists('wp_initial_nav_menu_meta_boxes')){ function wp_initial_nav_menu_meta_boxes() {   // beim ersten Aufruf alle bis auf wenige Boxen ausblenden
    global $wp_meta_boxes;
    if(get_user_option('metaboxhidden_nav-menus')!==false||!is_array($wp_meta_boxes))return;
    $initial=['add-post-type-page','add-post-type-post','add-custom-links','add-category'];$hidden=[];
    foreach($wp_meta_boxes['nav-menus']??[] as $ctx=>$prios)foreach($prios as $boxes)foreach($boxes as $id=>$b)if(!in_array($id,$initial,true))$hidden[]=$id;
    update_user_option(get_current_user_id(),'metaboxhidden_nav-menus',$hidden,true);
} }
if(!function_exists('wp_nav_menu_post_type_meta_boxes')){ function wp_nav_menu_post_type_meta_boxes() {
    foreach(get_post_types(['show_in_nav_menus'=>true],'object') as $pt){
        $pt=apply_filters('nav_menu_meta_box_object',$pt);if(!$pt)continue;
        elvado_adm_add_box('add-post-type-'.$pt->name,$pt->labels->name??$pt->name,'wp_nav_menu_item_post_type_meta_box','nav-menus','side','default',$pt);
    }
} }
if(!function_exists('wp_nav_menu_taxonomy_meta_boxes')){ function wp_nav_menu_taxonomy_meta_boxes() {
    foreach(get_taxonomies(['show_in_nav_menus'=>true],'object') as $tx){
        $tx=apply_filters('nav_menu_meta_box_object',$tx);if(!$tx)continue;
        elvado_adm_add_box('add-'.$tx->name,$tx->labels->name??$tx->name,'wp_nav_menu_item_taxonomy_meta_box','nav-menus','side','default',$tx);
    }
} }
if(!function_exists('wp_nav_menu_disabled_check')){ function wp_nav_menu_disabled_check($nav_menu_selected_id, $display=true) {   // „disabled“, solange kein Menü gewählt ist
    global $one_theme_location_no_menus;
    if($one_theme_location_no_menus)return false;
    return disabled($nav_menu_selected_id,0,$display);
} }
if(!function_exists('_wp_nav_menu_meta_box_object')){ function _wp_nav_menu_meta_box_object($object=null) {
    if(isset($object->name)){
        if('page'===$object->name)$object->_default_query=['orderby'=>'menu_order title','post_status'=>'publish'];
        elseif('post'===$object->name)$object->_default_query=['post_status'=>'publish'];
        elseif(!isset($object->_default_query))$object->_default_query=[];
        return apply_filters('nav_menu_meta_box_object',$object);
    }
    return $object;
} }
if(!function_exists('wp_nav_menu_item_link_meta_box')){ function wp_nav_menu_item_link_meta_box() {
    echo '<div class="customlinkdiv" id="customlinkdiv"><input type="hidden" value="custom" name="menu-item[-1][menu-item-type]" /><p id="menu-item-url-wrap"><label for="custom-menu-item-url">URL</label> <input id="custom-menu-item-url" name="menu-item[-1][menu-item-url]" type="text" class="code menu-item-textbox" value="https://" /></p>';
    echo '<p id="menu-item-name-wrap"><label for="custom-menu-item-name">Linktext</label> <input id="custom-menu-item-name" name="menu-item[-1][menu-item-title]" type="text" class="regular-text menu-item-textbox" /></p>';
    echo '<p class="button-controls"><input type="submit" name="add-custom-menu-item" id="submit-customlinkdiv" class="button submit-add-to-menu right" value="Zum Menü hinzufügen" /></p></div>';
} }
if(!function_exists('wp_nav_menu_item_post_type_meta_box')){ function wp_nav_menu_item_post_type_meta_box($data_object, $box) {
    $pt=_wp_nav_menu_meta_box_object($box['args']??null);if(!$pt||empty($pt->name))return;
    $posts=get_posts(array_merge(['post_type'=>$pt->name,'posts_per_page'=>50,'orderby'=>'title','order'=>'ASC','post_status'=>'publish'],(array)($pt->_default_query??[])));
    echo '<div id="posttype-'.esc_attr($pt->name).'" class="posttypediv"><ul id="'.esc_attr($pt->name).'checklist" class="categorychecklist form-no-clear">';
    $i=-1;foreach($posts as $p){ $n=esc_attr($p->ID);
        echo '<li><label class="menu-item-title"><input type="checkbox" class="menu-item-checkbox" name="menu-item['.$i.'][menu-item-object-id]" value="'.$n.'" /> '.esc_html(_draft_or_post_title($p)).'</label>'
            .'<input type="hidden" name="menu-item['.$i.'][menu-item-type]" value="post_type" /><input type="hidden" name="menu-item['.$i.'][menu-item-object]" value="'.esc_attr($pt->name).'" /><input type="hidden" name="menu-item['.$i.'][menu-item-title]" value="'.esc_attr($p->post_title).'" /><input type="hidden" name="menu-item['.$i.'][menu-item-url]" value="'.esc_url(get_permalink($p->ID)).'" /></li>';
        $i--; }
    echo '</ul>';
    if(!$posts)echo '<p>Keine Einträge vorhanden.</p>';
    echo '<p class="button-controls"><input type="submit" class="button submit-add-to-menu right" value="Zum Menü hinzufügen" /></p></div>';
} }
if(!function_exists('wp_nav_menu_item_taxonomy_meta_box')){ function wp_nav_menu_item_taxonomy_meta_box($data_object, $box) {
    $tx=_wp_nav_menu_meta_box_object($box['args']??null);if(!$tx||empty($tx->name))return;
    $terms=get_terms(['taxonomy'=>$tx->name,'number'=>50,'hide_empty'=>false,'orderby'=>'name']);
    echo '<div id="taxonomy-'.esc_attr($tx->name).'" class="taxonomydiv"><ul id="'.esc_attr($tx->name).'checklist" class="categorychecklist form-no-clear">';
    $i=-1;if(is_array($terms))foreach($terms as $t){ $n=esc_attr($t->term_id);
        echo '<li><label class="menu-item-title"><input type="checkbox" class="menu-item-checkbox" name="menu-item['.$i.'][menu-item-object-id]" value="'.$n.'" /> '.esc_html($t->name).'</label>'
            .'<input type="hidden" name="menu-item['.$i.'][menu-item-type]" value="taxonomy" /><input type="hidden" name="menu-item['.$i.'][menu-item-object]" value="'.esc_attr($tx->name).'" /><input type="hidden" name="menu-item['.$i.'][menu-item-title]" value="'.esc_attr($t->name).'" /></li>';
        $i--; }
    echo '</ul><p class="button-controls"><input type="submit" class="button submit-add-to-menu right" value="Zum Menü hinzufügen" /></p></div>';
} }
if(!function_exists('wp_save_nav_menu_items')){ function wp_save_nav_menu_items($menu_id=0, $menu_data=[]) {   // gespeicherte Menüeintrags-IDs; ohne bearbeitbare Menüs (Platzhalter) bleibt die Liste leer
    $menu_id=(int)$menu_id;$saved=[];
    if(0===$menu_id||is_nav_menu($menu_id)){
        foreach((array)$menu_data as $k=>$item){
            if(!is_array($item))continue;
            $item['menu-item-object-id']=(int)($item['menu-item-object-id']??$k);
            $id=wp_update_nav_menu_item($menu_id,0,$item);
            if(!is_wp_error($id)&&$id)$saved[]=$id;
        }
    }
    return $saved;
} }
if(!function_exists('wp_get_nav_menu_to_edit')){ function wp_get_nav_menu_to_edit($menu_id=0) {   // Bearbeitungsliste eines Menüs als HTML (false, wenn es das Menü nicht gibt)
    $menu=wp_get_nav_menu_object($menu_id);if(!$menu)return false;
    $items=wp_get_nav_menu_items($menu->slug);
    $r='<ul class="menu" id="menu-to-edit">';
    foreach((array)$items as $it)$r.='<li id="menu-item-'.(int)$it->ID.'" class="menu-item menu-item-depth-'.($it->menu_item_parent?'1':'0').'"><span class="item-title">'.esc_html($it->title).'</span> <span class="item-type">'.esc_html($it->type).'</span></li>';
    return $r.'</ul>';
} }
if(!function_exists('wp_nav_menu_manage_columns')){ function wp_nav_menu_manage_columns() {
    return ['_title'=>'Erweiterte Menü-Eigenschaften anzeigen','cb'=>'<input type="checkbox" />','link-target'=>'Link-Ziel','title-attribute'=>'Titelattribut','css-classes'=>'CSS-Klassen','xfn'=>'Link-Beziehung (XFN)','description'=>'Beschreibung'];
} }
if(!function_exists('_wp_delete_orphaned_draft_menu_items')){ function _wp_delete_orphaned_draft_menu_items() {   // Entwurfs-Menüeinträge ohne Menü, älter als ein Tag
    global $wpdb;
    $ids=$wpdb->get_col($wpdb->prepare("SELECT p.ID FROM {$wpdb->posts} p WHERE p.post_type = 'nav_menu_item' AND p.post_status = 'draft' AND p.post_modified < %s AND p.ID NOT IN (SELECT object_id FROM {$wpdb->term_relationships})",gmdate('Y-m-d H:i:s',time()-DAY_IN_SECONDS)));
    foreach((array)$ids as $id)wp_delete_post((int)$id,true);
} }
if(!function_exists('wp_nav_menu_update_menu_items')){ function wp_nav_menu_update_menu_items($nav_menu_selected_id, $nav_menu_selected_title) {   // Menüeinträge aus $_POST übernehmen; liefert Meldungen
    $messages=[];
    if(!is_nav_menu($nav_menu_selected_id))return $messages;
    if(!empty($_POST['menu-item-db-id']))foreach((array)$_POST['menu-item-db-id'] as $k=>$db){
        $a=[];foreach(['object-id','object','parent-id','position','type','title','url','description','attr-title','target','classes','xfn'] as $f)$a['menu-item-'.$f]=wp_unslash($_POST['menu-item-'.$f][$k]??'');
        $r=wp_update_nav_menu_item($nav_menu_selected_id,(int)$db,$a);if(is_wp_error($r))$messages[]='<div id="message" class="error notice is-dismissible"><p>'.esc_html($r->get_error_message()).'</p></div>';
    }
    return $messages;
} }
if(!function_exists('_wp_expand_nav_menu_post_data')){ function _wp_expand_nav_menu_post_data() {   // JSON „nav-menu-data“ in $_POST-Felder entpacken (umgeht max_input_vars)
    if(!isset($_POST['nav-menu-data']))return;
    $data=json_decode(stripslashes((string)$_POST['nav-menu-data']));
    if(!is_array($data)||!$data)return;
    foreach($data as $in){
        if(!is_object($in)||!isset($in->name))continue;
        preg_match('#([^\[]*)(\[(.+)\])?#',(string)$in->name,$m);
        $bits=[$m[1]];if(isset($m[3]))$bits=array_merge($bits,explode('][',$m[3]));
        $new=[];
        for($i=count($bits)-1;$i>=0;$i--)$new=$i===count($bits)-1?[$bits[$i]=>wp_slash($in->value??'')]:[$bits[$i]=>$new];
        $_POST=array_replace_recursive($_POST,$new);
    }
} }
if(!function_exists('_wp_ajax_menu_quick_search')){ function _wp_ajax_menu_quick_search($request=[]) {   // Schnellsuche in Menü-Boxen (JSON je Zeile oder Listenelemente)
    $type=(string)($request['type']??'');$obj=(string)($request['object_type']??'');$q=isset($request['q'])?(string)$request['q']:'';$fmt=(isset($request['response-format'])&&'markup'===$request['response-format'])?'markup':'json';
    $out=function($id,$title,$ptype) use($fmt){ echo $fmt==='markup'?'<li><label class="menu-item-title"><input type="checkbox" class="menu-item-checkbox" value="'.(int)$id.'" /> '.esc_html($title).'</label></li>':wp_json_encode(['ID'=>$id,'post_title'=>$title,'post_type'=>$ptype]);echo "\n"; };
    if('get-post-item'===$type){
        $id=(int)($request['ID']??0);
        if(post_type_exists($obj)&&$id)$out($id,get_the_title($id),get_post_type($id));
        elseif(taxonomy_exists($obj)&&$id){ $t=get_term($id,$obj);if($t&&!is_wp_error($t))$out($id,$t->name,$obj); }
    } elseif(preg_match('/quick-search-(posttype|taxonomy)-([a-zA-Z_-]*\b)/',$type,$m)){
        if('posttype'===$m[1]&&get_post_type_object($m[2])){
            $po=_wp_nav_menu_meta_box_object(get_post_type_object($m[2]));
            foreach(get_posts(array_merge(['post_type'=>$m[2],'s'=>$q,'posts_per_page'=>10,'no_found_rows'=>true],(array)($po->_default_query??[]))) as $p)$out($p->ID,get_the_title($p->ID),$m[2]);
        } elseif('taxonomy'===$m[1]){
            $terms=get_terms(['taxonomy'=>$m[2],'name__like'=>$q,'number'=>10,'hide_empty'=>false]);
            if(is_array($terms))foreach($terms as $t)$out($t->term_id,$t->name,$m[2]);
        }
    }
} }

/* ───────── Link-Boxen ───────── */
if(!function_exists('link_submit_meta_box')){ function link_submit_meta_box($link) {
    echo '<div class="submitbox" id="submitlink"><div id="minor-publishing"><div id="misc-publishing-actions"><div class="misc-pub-section"><label for="link_private" class="selectit"><input id="link_private" name="link_visible" type="checkbox" value="N" '.checked($link->link_visible??'Y','N',false).' /> Privat halten</label></div></div></div>';
    echo '<div id="major-publishing-actions">';do_action('post_submitbox_start',null);
    echo '<div id="delete-action">';
    if(!empty($link->link_id)&&current_user_can('manage_links'))echo '<a class="submitdelete deletion" href="'.esc_url(wp_nonce_url(admin_url('link.php?action=delete&link_id='.(int)$link->link_id),'delete-bookmark_'.(int)$link->link_id)).'">Löschen</a>';
    echo '</div><div id="publishing-action">'.(!empty($link->link_id)?'<input name="save" type="submit" class="button button-primary button-large" id="publish" value="Link aktualisieren" />':'<input name="save" type="submit" class="button button-primary button-large" id="publish" value="Link hinzufügen" />').'</div><div class="clear"></div></div></div>';
} }
if(!function_exists('link_categories_meta_box')){ function link_categories_meta_box($link) {
    echo '<div id="taxonomy-linkcategory" class="categorydiv"><div id="link_category-all" class="tabs-panel"><ul id="link_categorychecklist" class="categorychecklist form-no-clear">';
    wp_link_category_checklist($link->link_id??0);
    echo '</ul></div></div>';
} }
if(!function_exists('link_target_meta_box')){ function link_target_meta_box($link) {
    $t=$link->link_target??'';
    echo '<fieldset><legend class="screen-reader-text">Ziel</legend><p><label for="link_target_blank" class="selectit"><input id="link_target_blank" type="radio" name="link_target" value="_blank" '.(('_blank'===$t)?'checked="checked"':'').' /> <code>_blank</code> – neues Fenster oder neuer Tab</label></p>';
    echo '<p><label for="link_target_top" class="selectit"><input id="link_target_top" type="radio" name="link_target" value="_top" '.(('_top'===$t)?'checked="checked"':'').' /> <code>_top</code> – aktuelles Fenster</label></p>';
    echo '<p><label for="link_target_none" class="selectit"><input id="link_target_none" type="radio" name="link_target" value="" '.((''===$t)?'checked="checked"':'').' /> <code>_none</code> – gleiches Fenster oder Tab</label></p></fieldset>';
} }
if(!function_exists('xfn_check')){ function xfn_check($class, $value='', $deprecated='') {   // „checked“ für die XFN-Optionsfelder
    global $link;$rel=isset($link->link_rel)?(string)$link->link_rel:'';$rels=preg_split('/\s+/',$rel);
    if(''!==$value&&in_array($value,$rels,true))echo ' checked="checked"';
    if(''===$value){
        if('family'===$class&&!preg_match('/child|parent|sibling|spouse|kin/',$rel))echo ' checked="checked"';
        if('friendship'===$class&&!preg_match('/friend|acquaintance|contact/',$rel))echo ' checked="checked"';
        if('geographical'===$class&&!preg_match('/co-resident|neighbor/',$rel))echo ' checked="checked"';
        if('identity'===$class&&in_array('me',$rels,true))echo ' checked="checked"';
    }
} }
if(!function_exists('link_xfn_meta_box')){ function link_xfn_meta_box($link) {
    $groups=['identity'=>['me'=>'Ich selbst'],'friendship'=>['contact'=>'Kontakt','acquaintance'=>'Bekannter','friend'=>'Freund'],'physical'=>['met'=>'Persönlich getroffen'],'professional'=>['co-worker'=>'Kollege','colleague'=>'Kollege (extern)'],
        'geographical'=>['co-resident'=>'Mitbewohner','neighbor'=>'Nachbar'],'family'=>['child'=>'Kind','kin'=>'Verwandter','parent'=>'Elternteil','sibling'=>'Geschwister','spouse'=>'Ehepartner'],'romantic'=>['muse'=>'Muse','crush'=>'Schwarm','date'=>'Date','sweetheart'=>'Liebling']];
    $GLOBALS['link']=$link;
    echo '<table class="links-table"><tr><th scope="row"><label for="link_rel">rel:</label></th><td><input type="text" name="link_rel" id="link_rel" value="'.esc_attr($link->link_rel??'').'" /></td></tr>';
    foreach($groups as $cls=>$opts){
        echo '<tr><th scope="row">'.esc_html($cls).'</th><td><fieldset>';
        foreach($opts as $v=>$label){ echo '<label for="'.esc_attr($cls.'-'.$v).'"><input type="radio" name="'.esc_attr($cls).'" value="'.esc_attr($v).'" id="'.esc_attr($cls.'-'.$v).'"';xfn_check($cls,$v);echo ' /> '.esc_html($label).'</label> '; }
        echo '</fieldset></td></tr>';
    }
    echo '</table><p>Wenn der Link auf jemanden verweist, kannst du hier deine Beziehung angeben.</p>';
} }
if(!function_exists('link_advanced_meta_box')){ function link_advanced_meta_box($link) {
    echo '<table class="links-table"><tr><th scope="row"><label for="link_image">Bildadresse</label></th><td><input type="text" name="link_image" class="code" id="link_image" maxlength="255" value="'.esc_attr($link->link_image??'').'" /></td></tr>';
    echo '<tr><th scope="row"><label for="rss_uri">RSS-Adresse</label></th><td><input name="link_rss" class="code" type="text" id="rss_uri" maxlength="255" value="'.esc_attr($link->link_rss??'').'" /></td></tr>';
    echo '<tr><th scope="row"><label for="link_notes">Notizen</label></th><td><textarea name="link_notes" id="link_notes" rows="10">'.esc_textarea($link->link_notes??'').'</textarea></td></tr>';
    echo '<tr><th scope="row"><label for="link_rating">Bewertung</label></th><td><select name="link_rating" id="link_rating" size="1">';
    for($r=0;$r<=10;$r++)echo '<option value="'.$r.'"'.((int)($link->link_rating??0)===$r?' selected="selected"':'').'>'.$r.'</option>';
    echo '</select> (Bewertung zwischen 0 und 10)</td></tr></table>';
} }

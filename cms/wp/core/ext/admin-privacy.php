<?php
// Ergänzende WordPress-Funktionen (Bereich Admin, Teil 9): Datenschutz-Werkzeuge (privacy-tools.php) sowie Oberflächen für die Plugin- und Theme-Installation (plugin-install.php, theme-install.php).
// Datenschutz-Anfragen liegen als Beitragstyp user_request (siehe users-privacy.php). Die Verzeichnis-Oberflächen liefern Formulare und kompakte Listen; Suchen und Installationen laufen über den CMS-Installer.

/* ───────── Datenschutz-Werkzeuge ───────── */
if(!function_exists('_wp_privacy_resend_request')){ function _wp_privacy_resend_request($request_id) {   // Bestätigungs-E-Mail erneut senden
    $id=absint($request_id);$r=wp_get_user_request($id);
    if(!$r)return new WP_Error('privacy_request_error','Ungültige Anfrage.');
    $res=wp_send_user_request($id);
    if(is_wp_error($res))return $res;
    return $id;
} }
if(!function_exists('_wp_privacy_completed_request')){ function _wp_privacy_completed_request($request_id) {   // Anfrage als erledigt markieren
    $id=absint($request_id);$r=wp_get_user_request($id);
    if(!$r)return new WP_Error('privacy_request_error','Ungültige Anfrage.');
    update_post_meta($id,'_wp_user_request_completed_timestamp',time());
    elvado_wpx_request_status($id,'request-completed');
    return $id;
} }
if(!function_exists('_wp_personal_data_handle_actions')){ function _wp_personal_data_handle_actions() {   // Formular-Aktionen der Seiten „Daten exportieren/löschen“
    if(!isset($_POST['action']))return;
    $a=sanitize_key(wp_unslash($_POST['action']));
    if(in_array($a,['add_export_personal_data_request','add_remove_personal_data_request'],true)){
        check_admin_referer('personal-data-request');if(!elvado_adm_can('export_others_personal_data'))wp_die('Du darfst keine Datenschutzanfragen anlegen.');
        $type=$a==='add_export_personal_data_request'?'export_personal_data':'remove_personal_data';
        $in=isset($_POST['username_or_email_for_privacy_request'])?trim(wp_unslash((string)$_POST['username_or_email_for_privacy_request'])):'';
        $email=is_email($in)?$in:'';if($email===''&&$in!==''){ $u=get_user_by('login',$in);if($u)$email=$u->user_email; }
        if($email===''){ if(function_exists('add_settings_error'))add_settings_error('username_or_email_for_privacy_request','username_or_email_for_privacy_request','Bitte gib eine gültige E-Mail-Adresse oder einen Benutzernamen an.','error');return; }
        $id=wp_create_user_request($email,$type,[],!empty($_POST['send_confirmation_email'])?'pending':'confirmed');
        if(is_wp_error($id)){ if(function_exists('add_settings_error'))add_settings_error('username_or_email_for_privacy_request','username_or_email_for_privacy_request',$id->get_error_message(),'error');return; }
        if(!empty($_POST['send_confirmation_email']))wp_send_user_request($id);
        return;
    }
    $ids=array_map('absint',(array)($_REQUEST['request_id']??[]));
    foreach($ids as $id){
        if($a==='resend'||$a==='complete'||$a==='delete')check_admin_referer('bulk-privacy_requests');
        if(!elvado_adm_can('export_others_personal_data'))continue;
        if($a==='resend')_wp_privacy_resend_request($id);elseif($a==='complete')_wp_privacy_completed_request($id);elseif($a==='delete')wp_delete_post($id,true);
    }
} }
if(!function_exists('_wp_personal_data_cleanup_requests')){ function _wp_personal_data_cleanup_requests() {   // abgeschlossene/fehlgeschlagene Anfragen nach 30 Tagen löschen
    global $wpdb;$exp=(int)apply_filters('user_request_expiration',30*DAY_IN_SECONDS);
    $ids=$wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'user_request' AND post_status IN ('request-failed','request-completed') AND post_modified_gmt < %s",gmdate('Y-m-d H:i:s',time()-$exp)));
    foreach((array)$ids as $id)wp_delete_post((int)$id,true);
} }
if(!function_exists('wp_privacy_generate_personal_data_export_group_html')){ function wp_privacy_generate_personal_data_export_group_html($group_data, $group_id='', $groups_count=1) {
    $attr=sanitize_title_with_dashes(($group_data['group_label']??'').'-'.$group_id);
    $h='<h2 id="'.esc_attr($attr).'">'.esc_html($group_data['group_label']??'');$n=count((array)($group_data['items']??[]));
    if($n>1)$h.=sprintf(' <span class="count">(%d)</span>',$n);
    $h.='</h2>';
    if(!empty($group_data['group_description']))$h.='<p>'.esc_html($group_data['group_description']).'</p>';
    foreach((array)($group_data['items']??[]) as $item){
        $h.='<table><tbody>';
        foreach((array)$item as $d){
            $v=(string)($d['value']??'');
            if(false===strpos($v,' ')&&(0===strpos($v,'http://')||0===strpos($v,'https://')))$v='<a href="'.esc_url($v).'">'.esc_html($v).'</a>';
            $h.='<tr><th>'.esc_html((string)($d['name']??'')).'</th><td>'.wp_kses_post($v).'</td></tr>';
        }
        $h.='</tbody></table>';
    }
    if($groups_count>1)$h.='<div class="return-to-top"><a href="#top"><span aria-hidden="true">&uarr; </span> Nach oben</a></div>';
    return $h;
} }
if(!function_exists('elvado_adm_export_groups')){ function elvado_adm_export_groups(array $raw) {   // Rohdaten der Exporter nach group_id zusammenfassen
    $g=[];
    foreach($raw as $items)foreach((array)$items as $it){
        $id=(string)($it['group_id']??'');if($id==='')continue;
        if(!isset($g[$id]))$g[$id]=['group_label'=>(string)($it['group_label']??$id),'group_description'=>(string)($it['group_description']??''),'items'=>[]];
        $g[$id]['items'][(string)($it['item_id']??count($g[$id]['items']))]=(array)($it['data']??[]);
    }
    return $g;
} }
if(!function_exists('wp_privacy_generate_personal_data_export_file')){ function wp_privacy_generate_personal_data_export_file($request_id) {   // ZIP mit index.html und export.json im Export-Ordner; liefert den Pfad
    $r=wp_get_user_request($request_id);if(!$r||'export_personal_data'!==$r->action_name)return new WP_Error('invalid_request','Ungültige Anfrage.');
    $dir=wp_privacy_exports_dir();if(!wp_mkdir_p($dir))return new WP_Error('export_dir','Der Export-Ordner konnte nicht angelegt werden.');
    if(!is_file($dir.'index.php'))@file_put_contents($dir.'index.php',"<?php\n// Platzhalter\n");
    $groups=elvado_adm_export_groups((array)get_post_meta($request_id,'_export_data_raw',true));update_post_meta($request_id,'_export_data_grouped',$groups);
    $html='<!doctype html><html><head><meta charset="utf-8"><title>Export personenbezogener Daten</title></head><body><h1 id="top">Export für '.esc_html($r->email).'</h1>';
    foreach($groups as $id=>$g)$html.=wp_privacy_generate_personal_data_export_group_html($g,$id,count($groups));
    $html.='</body></html>';
    $obs=wp_generate_password(32,false,false);$name='wp-personal-data-file-'.$obs;$zip=$dir.$name.'.zip';
    if(!class_exists('ZipArchive'))return new WP_Error('no_zip','Die PHP-Erweiterung zip fehlt.');
    $za=new ZipArchive();if(true!==$za->open($zip,ZipArchive::CREATE|ZipArchive::OVERWRITE))return new WP_Error('zip_error','Die ZIP-Datei konnte nicht angelegt werden.');
    $za->addFromString('index.html',$html);$za->addFromString('export.json',wp_json_encode($groups,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));$za->close();
    update_post_meta($request_id,'_export_file_name',$name.'.zip');
    do_action('wp_privacy_personal_data_export_file_created',$zip,wp_privacy_exports_url().$name.'.zip',$html,$request_id);
    return $zip;
} }
if(!function_exists('wp_privacy_send_personal_data_export_email')){ function wp_privacy_send_personal_data_export_email($request_id) {   // @return true|string Fehlertext
    $r=wp_get_user_request($request_id);if(!$r)return 'Ungültige Anfrage-ID.';
    $f=(string)get_post_meta($request_id,'_export_file_name',true);if($f==='')return 'Es wurde keine Exportdatei erzeugt.';
    $url=wp_privacy_exports_url().$f;$site=wp_specialchars_decode(get_option('blogname'),ENT_QUOTES);
    $m=apply_filters('wp_privacy_personal_data_email_content',"Hallo,\n\ndein angeforderter Export personenbezogener Daten von $site ist bereit. Du kannst ihn bis zum Ablauf der Frist herunterladen:\n\n$url\n",$request_id,['request'=>$r,'export_file_url'=>$url,'sitename'=>$site]);
    $ok=wp_mail($r->email,'['.$site.'] Export deiner personenbezogenen Daten',(string)$m);
    if(!$ok)return 'Beim Senden der E-Mail ist ein Fehler aufgetreten.';
    update_post_meta($request_id,'_wp_user_notified',true);
    return true;
} }
if(!function_exists('wp_privacy_process_personal_data_export_page')){ function wp_privacy_process_personal_data_export_page($response, $exporter_index, $email_address, $page, $request_id, $send_as_email, $exporter_key) {
    $ex=apply_filters('wp_privacy_personal_data_exporters',[]);
    if(!is_array($ex))return new WP_Error('exporters_error','Eine Exporter-Funktion hat ein ungültiges Ergebnis geliefert.');
    $keys=array_keys($ex);$cnt=count($keys);$i=(int)$exporter_index;
    if($cnt===0||$i<1||$i>$cnt)return new WP_Error('invalid_exporter_index','Ungültiger Exporter-Index.');
    $key=$keys[$i-1];$e=$ex[$key];
    if(!is_array($e)||empty($e['callback'])||!is_callable($e['callback']))return new WP_Error('exporter_callback','Die Exporter-Funktion ist ungültig.');
    $r=call_user_func($e['callback'],$email_address,(int)$page);
    if(is_wp_error($r))return $r;
    if(!is_array($r)||!array_key_exists('data',$r)||!is_array($r['data'])||!array_key_exists('done',$r))return new WP_Error('exporter_result','Die Exporter-Funktion hat ein ungültiges Ergebnis geliefert.');
    $raw=(array)get_post_meta($request_id,'_export_data_raw',true);if($i===1&&(int)$page===1)$raw=[];
    $raw[$key.':'.(int)$page]=$r['data'];update_post_meta($request_id,'_export_data_raw',$raw);
    $response['done']=(bool)$r['done'];
    if($response['done']&&$i===$cnt){
        $zip=wp_privacy_generate_personal_data_export_file($request_id);
        if(is_wp_error($zip))return $zip;
        if($send_as_email){ $mail=wp_privacy_send_personal_data_export_email($request_id);if(is_string($mail))return new WP_Error('send_export_email_error',$mail); }
        $response['url']=wp_privacy_exports_url().(string)get_post_meta($request_id,'_export_file_name',true);
    }
    return apply_filters('wp_privacy_personal_data_export_page',$response,$i,$email_address,$page,$request_id,$send_as_email,$exporter_key);
} }
if(!function_exists('wp_privacy_process_personal_data_erasure_page')){ function wp_privacy_process_personal_data_erasure_page($response, $eraser_index, $email_address, $page, $request_id, $eraser_key='') {
    $er=apply_filters('wp_privacy_personal_data_erasers',[]);
    if(!is_array($er))return new WP_Error('erasers_error','Eine Löschfunktion hat ein ungültiges Ergebnis geliefert.');
    $keys=array_keys($er);$cnt=count($keys);$i=(int)$eraser_index;
    if($cnt===0||$i<1||$i>$cnt)return new WP_Error('invalid_eraser_index','Ungültiger Löschfunktions-Index.');
    $e=$er[$keys[$i-1]];
    if(!is_array($e)||empty($e['callback'])||!is_callable($e['callback']))return new WP_Error('eraser_callback','Die Löschfunktion ist ungültig.');
    $r=call_user_func($e['callback'],$email_address,(int)$page);
    if(is_wp_error($r))return $r;
    foreach(['items_removed','items_retained','messages','done'] as $k)if(!array_key_exists($k,$r))return new WP_Error('eraser_result','Die Löschfunktion hat ein ungültiges Ergebnis geliefert.');
    $response['items_removed']=!empty($response['items_removed'])||!empty($r['items_removed']);
    $response['items_retained']=!empty($response['items_retained'])||!empty($r['items_retained']);
    $response['messages']=array_merge((array)($response['messages']??[]),(array)$r['messages']);
    $response['done']=(bool)$r['done'];
    if($response['done']&&$i===$cnt){
        _wp_privacy_completed_request($request_id);
        do_action('wp_privacy_personal_data_erased',$request_id);
        if(function_exists('_wp_privacy_send_erasure_fulfillment_notification'))_wp_privacy_send_erasure_fulfillment_notification($request_id);
    }
    return apply_filters('wp_privacy_personal_data_erasure_page',$response,$i,$email_address,$page,$request_id,$eraser_key);
} }

/* ───────── Plugin-Installation (Oberfläche) ───────── */
if(!function_exists('install_popular_tags')){ function install_popular_tags($args=[]) {   // kein Abruf bei wordpress.org: nur Zwischenspeicher
    $r=get_site_transient('poptags_'.md5(serialize($args)));
    return is_array($r)?$r:[];
} }
if(!function_exists('install_search_form')){ function install_search_form($deprecated=true) {
    $t=isset($_REQUEST['type'])?wp_unslash((string)$_REQUEST['type']):'term';$term=isset($_REQUEST['s'])?wp_unslash((string)$_REQUEST['s']):'';
    echo '<form class="search-form search-plugins" method="get"><input type="hidden" name="tab" value="search" /><label class="screen-reader-text" for="typeselector">Suchbegriff</label><select name="type" id="typeselector">';
    foreach(['term'=>'Stichwort','author'=>'Autor','tag'=>'Schlagwort'] as $v=>$l)echo '<option value="'.$v.'"'.selected($t,$v,false).'>'.esc_html($l).'</option>';
    echo '</select><label class="screen-reader-text" for="search-plugins">Plugins suchen</label><input type="search" name="s" id="search-plugins" value="'.esc_attr($term).'" class="wp-filter-search" /><input type="submit" id="search-submit" class="button hide-if-js" value="Plugins suchen" /></form>';
} }
if(!function_exists('install_dashboard')){ function install_dashboard() {
    echo '<p>Plugins erweitern WordPress. Du kannst Plugins aus dem Verzeichnis installieren, indem du danach suchst, oder ein Plugin als ZIP-Datei hochladen.</p>';
    install_search_form(false);
    $tags=install_popular_tags();
    echo '<p class="popular-tags">';
    if($tags)foreach($tags as $t){ $t=(object)$t;echo '<a href="'.esc_url(self_admin_url('plugin-install.php?tab=search&type=tag&s='.rawurlencode($t->name??''))).'">'.esc_html($t->name??'').'</a> '; }
    else echo 'Beliebte Schlagwörter sind derzeit nicht verfügbar.';
    echo '</p>';
} }
if(!function_exists('install_plugins_upload')){ function install_plugins_upload() {
    echo '<div class="upload-plugin"><p class="install-help">Wenn du ein Plugin als ZIP-Datei hast, kannst du es hier installieren.</p><form method="post" enctype="multipart/form-data" class="wp-upload-form" action="'.esc_url(self_admin_url('update.php?action=upload-plugin')).'">';
    wp_nonce_field('plugin-upload');
    echo '<label class="screen-reader-text" for="pluginzip">Plugin-ZIP-Datei</label><input type="file" id="pluginzip" name="pluginzip" accept=".zip" /><input type="submit" name="install-plugin-submit" id="install-plugin-submit" class="button" value="Jetzt installieren" /></form></div>';
} }
if(!function_exists('install_plugins_favorites_form')){ function install_plugins_favorites_form() {
    $u=get_user_option('wporg_favorites');
    echo '<p class="install-help">Wenn du Plugins auf WordPress.org als Favoriten markiert hast, findest du sie hier.</p><form method="get"><input type="hidden" name="tab" value="favorites" /><p><label for="user">Dein WordPress.org-Benutzername:</label> <input type="search" id="user" name="user" value="'.esc_attr((string)$u).'" /> <input type="submit" class="button" value="Favoriten abrufen" /></p></form>';
} }
if(!function_exists('display_plugins_table')){ function display_plugins_table() {   // Tabelle der Plugin-Liste, falls eine Listenklasse gesetzt ist
    global $wp_list_table;
    if(is_object($wp_list_table)&&method_exists($wp_list_table,'display')){ $wp_list_table->display();return; }
    echo '<p>Keine Plugins gefunden.</p>';
} }
if(!function_exists('install_plugin_information')){ function install_plugin_information() {
    $slug=isset($_REQUEST['plugin'])?sanitize_key(wp_unslash((string)$_REQUEST['plugin'])):'';
    if($slug===''){ echo '<div class="error"><p>Es wurde kein Plugin angegeben.</p></div>';return; }
    $api=plugins_api('plugin_information',['slug'=>$slug]);
    if(is_wp_error($api)){ echo '<div class="error"><p>'.esc_html($api->get_error_message()).'</p></div>';return; }
    echo '<div id="plugin-information"><h2>'.esc_html($api->name??$slug).'</h2><p>'.esc_html(wp_strip_all_tags((string)($api->short_description??''))).'</p></div>';
} }
if(!function_exists('wp_get_plugin_action_button')){ function wp_get_plugin_action_button($name, $data, $compatible_php, $compatible_wp) {   // Schaltfläche je nach Installationsstand
    $slug=is_object($data)?(string)($data->slug??''):(string)($data['slug']??'');$file='';
    foreach(get_plugins() as $f=>$p)if(dirname($f)===$slug||basename($f,'.php')===$slug){ $file=$f;break; }
    $ok=$compatible_php&&$compatible_wp;$n=esc_attr($name);
    if($file===''){
        if(!current_user_can('install_plugins'))return '';
        return $ok?sprintf('<a class="install-now button" data-slug="%s" href="%s" aria-label="%s" data-name="%s">Jetzt installieren</a>',esc_attr($slug),esc_url(wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin='.rawurlencode($slug)),'install-plugin_'.$slug)),esc_attr(sprintf('%s jetzt installieren',$name)),$n)
            :sprintf('<button type="button" class="button button-disabled" disabled="disabled">Nicht kompatibel</button>');
    }
    if(is_plugin_active($file))return '<button type="button" class="button button-disabled" disabled="disabled">Aktiv</button>';
    if(elvado_adm_can('activate_plugins',''))return $ok?sprintf('<a href="%s" class="button activate-now" aria-label="%s">Aktivieren</a>',esc_url(wp_nonce_url(self_admin_url('plugins.php?action=activate&plugin='.rawurlencode($file)),'activate-plugin_'.$file)),esc_attr(sprintf('%s aktivieren',$name))):'<button type="button" class="button button-disabled" disabled="disabled">Nicht kompatibel</button>';
    return '<button type="button" class="button button-disabled" disabled="disabled">Installiert</button>';
} }

/* ───────── Theme-Installation (Oberfläche) ───────── */
if(!function_exists('install_themes_feature_list')){ function install_themes_feature_list() { return get_theme_feature_list(false); } }
if(!function_exists('install_theme_search_form')){ function install_theme_search_form($type_selector=true) {
    $t=isset($_REQUEST['type'])?wp_unslash((string)$_REQUEST['type']):'term';$term=isset($_REQUEST['s'])?wp_unslash((string)$_REQUEST['s']):'';
    echo '<p class="install-help">Suche nach Themes nach Stichwort, Autor oder Schlagwort.</p><form id="search-themes" method="get"><input type="hidden" name="tab" value="search" />';
    if($type_selector){ echo '<select name="type" id="typeselector">';foreach(['term'=>'Stichwort','author'=>'Autor','tag'=>'Schlagwort'] as $v=>$l)echo '<option value="'.$v.'"'.selected($t,$v,false).'>'.esc_html($l).'</option>';echo '</select>'; }
    echo '<input type="search" name="s" value="'.esc_attr($term).'" aria-label="Themes suchen" /> <input type="submit" name="search" class="button" value="Themes suchen" /></form>';
} }
if(!function_exists('install_themes_dashboard')){ function install_themes_dashboard() {
    install_theme_search_form(false);
    echo '<h4>Themes nach Eigenschaften filtern</h4><form method="get"><input type="hidden" name="tab" value="search" /><input type="hidden" name="type" value="tag" />';
    foreach(install_themes_feature_list() as $group=>$feats){
        echo '<div class="feature-group"><h3 class="feature-name">'.esc_html($group).'</h3><ol>';
        foreach((array)$feats as $slug=>$label)echo '<li><input type="checkbox" name="features[]" id="feature-id-'.esc_attr($slug).'" value="'.esc_attr($slug).'" /> <label for="feature-id-'.esc_attr($slug).'">'.esc_html($label).'</label></li>';
        echo '</ol></div>';
    }
    echo '<input type="submit" class="button" value="Filter anwenden" /></form>';
} }
if(!function_exists('install_themes_upload')){ function install_themes_upload() {
    echo '<p class="install-help">Wenn du ein Theme als ZIP-Datei hast, kannst du es hier installieren.</p><form method="post" enctype="multipart/form-data" class="wp-upload-form" action="'.esc_url(self_admin_url('update.php?action=upload-theme')).'">';
    wp_nonce_field('theme-upload');
    echo '<label class="screen-reader-text" for="themezip">Theme-ZIP-Datei</label><input type="file" id="themezip" name="themezip" accept=".zip" /><input type="submit" name="install-theme-submit" id="install-theme-submit" class="button" value="Jetzt installieren" /></form>';
} }
if(!function_exists('display_theme')){ function display_theme($theme) {   // Karte eines Themes aus der Verzeichnis-Liste
    $t=(object)$theme;$slug=(string)($t->slug??'');
    echo '<div class="available-theme"><h3>'.esc_html($t->name??$slug).'</h3>';
    if(!empty($t->author))echo '<p class="theme-author">von '.esc_html(is_object($t->author)?($t->author->display_name??''):(is_array($t->author)?($t->author['display_name']??''):(string)$t->author)).'</p>';
    if($slug!==''&&current_user_can('install_themes'))echo '<a class="button install-theme-preview" href="'.esc_url(wp_nonce_url(self_admin_url('update.php?action=install-theme&theme='.rawurlencode($slug)),'install-theme_'.$slug)).'">Installieren</a>';
    echo '</div>';
} }
if(!function_exists('display_themes')){ function display_themes() {
    global $wp_list_table;
    if(is_object($wp_list_table)&&method_exists($wp_list_table,'display')){ $wp_list_table->display();return; }
    echo '<p>Keine Themes gefunden.</p>';
} }
if(!function_exists('install_theme_information')){ function install_theme_information() {
    $slug=isset($_REQUEST['theme'])?sanitize_key(wp_unslash((string)$_REQUEST['theme'])):'';
    if($slug===''){ echo '<div class="error"><p>Es wurde kein Theme angegeben.</p></div>';return; }
    $api=themes_api('theme_information',['slug'=>$slug]);
    if(is_wp_error($api)){ echo '<div class="error"><p>'.esc_html($api->get_error_message()).'</p></div>';return; }
    echo '<div id="theme-information"><h2>'.esc_html($api->name??$slug).'</h2><p>'.esc_html(wp_strip_all_tags((string)($api->description??''))).'</p></div>';
} }

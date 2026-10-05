<?php
// Ergänzende WordPress-Funktionen (Bereich Admin, Teil 4): Installation (wp_install, Standard-Inhalte, Standard-Optionen und -Rollen), Kern-Update, PclZip-Hilfen, Danksagungen,
// Sprachpakete, Importer, Bildschirm-/Menü-/Spaltenhilfen. Multisite-Funktionen (Netzwerk anlegen) liefern Standardwerte: das CMS kennt nur eine Website.

/* ───────── Standard-Optionen und -Rollen (schema.php) ───────── */
if(!function_exists('populate_options')){ function populate_options($options=[]) {   // fehlende Standardoptionen anlegen; vorhandene bleiben unverändert
    global $wp_db_version;
    $url=function_exists('wp_guess_url')?wp_guess_url():'';
    $d=['siteurl'=>$url,'home'=>$url,'blogname'=>'Meine Website','blogdescription'=>'','users_can_register'=>0,'admin_email'=>'you@example.com','start_of_week'=>1,'use_balanceTags'=>0,'use_smilies'=>1,'require_name_email'=>1,
        'comments_notify'=>1,'posts_per_rss'=>10,'rss_use_excerpt'=>0,'mailserver_url'=>'mail.example.com','mailserver_login'=>'login@example.com','mailserver_pass'=>'password','mailserver_port'=>110,'default_category'=>1,
        'default_comment_status'=>'open','default_ping_status'=>'open','default_pingback_flag'=>1,'posts_per_page'=>10,'date_format'=>'j. F Y','time_format'=>'H:i','links_updated_date_format'=>'j. F Y H:i','comment_moderation'=>0,
        'moderation_notify'=>1,'permalink_structure'=>'','rewrite_rules'=>'','hack_file'=>0,'blog_charset'=>'UTF-8','moderation_keys'=>'','active_plugins'=>[],'category_base'=>'','ping_sites'=>'https://rpc.pingomatic.com/',
        'comment_max_links'=>2,'gmt_offset'=>0,'default_email_category'=>1,'recently_edited'=>'','template'=>'','stylesheet'=>'','comment_registration'=>0,'html_type'=>'text/html','use_trackback'=>0,'default_role'=>'subscriber',
        'db_version'=>$wp_db_version,'uploads_use_yearmonth_folders'=>1,'upload_path'=>'','blog_public'=>1,'default_link_category'=>2,'show_on_front'=>'posts','tag_base'=>'','show_avatars'=>1,'avatar_rating'=>'G',
        'upload_url_path'=>'','thumbnail_size_w'=>150,'thumbnail_size_h'=>150,'thumbnail_crop'=>1,'medium_size_w'=>300,'medium_size_h'=>300,'avatar_default'=>'mystery','large_size_w'=>1024,'large_size_h'=>1024,
        'image_default_link_type'=>'none','image_default_size'=>'','image_default_align'=>'','close_comments_for_old_posts'=>0,'close_comments_days_old'=>14,'thread_comments'=>1,'thread_comments_depth'=>5,'page_comments'=>0,
        'comments_per_page'=>50,'default_comments_page'=>'newest','comment_order'=>'asc','sticky_posts'=>[],'widget_categories'=>[],'widget_text'=>[],'widget_rss'=>[],'uninstall_plugins'=>[],'timezone_string'=>'',
        'page_for_posts'=>0,'page_on_front'=>0,'default_post_format'=>0,'link_manager_enabled'=>0,'finished_splitting_shared_terms'=>1,'site_icon'=>0,'medium_large_size_w'=>768,'medium_large_size_h'=>0,
        'wp_page_for_privacy_policy'=>0,'show_comments_cookies_opt_in'=>1,'admin_email_lifespan'=>time()+6*MONTH_IN_SECONDS,'disallowed_keys'=>'','comment_previously_approved'=>1,'auto_plugin_theme_update_emails'=>[],
        'auto_update_core_dev'=>'enabled','auto_update_core_minor'=>'enabled','auto_update_core_major'=>'unset','wp_force_deactivated_plugins'=>[],'initial_db_version'=>$wp_db_version,'can_compress_scripts'=>0];
    $d=array_merge($d,(array)$options);
    foreach($d as $k=>$v){ if(($k==='siteurl'||$k==='home')&&$v==='')continue;add_option($k,$v); }
} }
if(!function_exists('rrw_adm_role_caps')){ function rrw_adm_role_caps($role, array $caps) {   // fehlende Rechte einer Rolle ergänzen
    $r=get_role($role);if(!$r)return;
    foreach($caps as $c)if(!$r->has_cap($c))$r->add_cap($c);
} }
if(!function_exists('populate_roles_160')){ function populate_roles_160() {   // Grundrollen sicherstellen (fehlende werden angelegt)
    foreach(['administrator'=>'Administrator','editor'=>'Redakteur','author'=>'Autor','contributor'=>'Mitarbeiter','subscriber'=>'Abonnent'] as $r=>$n)if(!get_role($r))add_role($r,$n,rrw_wp_caps_for_role($r,false));
    rrw_adm_role_caps('administrator',['switch_themes','edit_themes','activate_plugins','edit_plugins','edit_users','edit_files','manage_options','moderate_comments','manage_categories','manage_links','upload_files','import','unfiltered_html','edit_posts','edit_others_posts','edit_published_posts','publish_posts','edit_pages','read']);
} }
if(!function_exists('populate_roles_210')){ function populate_roles_210() {
    $c=['edit_others_pages','edit_published_pages','publish_pages','delete_pages','delete_others_pages','delete_published_pages','delete_posts','delete_others_posts','delete_published_posts','delete_private_posts','edit_private_posts','read_private_posts','delete_private_pages','edit_private_pages','read_private_pages'];
    rrw_adm_role_caps('administrator',array_merge($c,['delete_users','create_users']));rrw_adm_role_caps('editor',$c);
} }
if(!function_exists('populate_roles_230')){ function populate_roles_230() { rrw_adm_role_caps('administrator',['unfiltered_upload']); } }
if(!function_exists('populate_roles_250')){ function populate_roles_250() { rrw_adm_role_caps('administrator',['edit_dashboard']); } }
if(!function_exists('populate_roles_260')){ function populate_roles_260() { rrw_adm_role_caps('administrator',['update_plugins','delete_plugins']); } }
if(!function_exists('populate_roles_270')){ function populate_roles_270() { rrw_adm_role_caps('administrator',['install_plugins','install_themes']); } }
if(!function_exists('populate_roles_280')){ function populate_roles_280() { rrw_adm_role_caps('administrator',['update_themes','update_core']); } }
if(!function_exists('populate_roles_300')){ function populate_roles_300() { rrw_adm_role_caps('administrator',['list_users','remove_users','promote_users','edit_theme_options','delete_themes','export']); } }
if(!function_exists('populate_roles')){ function populate_roles() { populate_roles_160();populate_roles_210();populate_roles_230();populate_roles_250();populate_roles_260();populate_roles_270();populate_roles_280();populate_roles_300(); } }
if(!function_exists('install_network')){ function install_network() {} }   // Netzwerktabellen entfallen (Einzelseite)
if(!function_exists('populate_network')){ function populate_network($network_id=1, $domain='', $email='', $site_name='', $path='/', $subdomain_install=false) {   // kein Netzwerk anlegbar
    return new WP_Error('multisite_unsupported','Das CMS betreibt eine einzelne Website; ein Netzwerk kann nicht angelegt werden.');
} }
if(!function_exists('populate_network_meta')){ function populate_network_meta($network_id, array $meta=[]) { if((int)$network_id===1)foreach($meta as $k=>$v)update_site_option($k,$v); } }
if(!function_exists('populate_site_meta')){ function populate_site_meta($site_id, array $meta=[]) {   // Websitemeta der einen Website als Option
    if((int)$site_id!==1||!$meta)return;
    update_option('rrw_site_meta',array_merge((array)get_option('rrw_site_meta',[]),$meta));
} }

/* ───────── Installation ───────── */
if(!function_exists('wp_install')){ function wp_install($blog_title, $user_name, $user_email, $is_public, $deprecated='', $user_password='', $language='') {
    wp_check_mysql_version();make_db_current_silent();populate_options();populate_roles();
    update_option('blogname',$blog_title);update_option('admin_email',$user_email);update_option('blog_public',$is_public?1:0);
    if($language)update_option('WPLANG',$language);
    $url=function_exists('wp_guess_url')?wp_guess_url():'';if($url!==''&&!get_option('siteurl')){ update_option('siteurl',$url);update_option('home',$url); }
    $url=(string)get_option('siteurl',$url);
    if(!$is_public)update_option('default_pingback_flag',0);
    $uid=username_exists($user_name);$pw=trim((string)$user_password);$mailpw=false;$created=false;
    if(!$uid&&$pw===''){ $pw=wp_generate_password(12,false);$msg='<strong><em>Merke dir dieses Passwort</em></strong> gut: Es wurde zufällig erzeugt und wird nicht erneut angezeigt.';$uid=wp_create_user($user_name,$pw,$user_email);if(!is_wp_error($uid))update_user_meta($uid,'default_password_nag',true);$mailpw=true;$created=true; }
    elseif(!$uid){ $msg='<em>Dein gewähltes Passwort.</em>';$uid=wp_create_user($user_name,$pw,$user_email);$created=true; }
    else { $pw='';$msg='Der Benutzer existiert bereits. Das Passwort bleibt unverändert.'; }
    if(is_wp_error($uid))return ['url'=>$url,'user_id'=>0,'password'=>'','password_message'=>$uid->get_error_message()];
    $user=new WP_User($uid);$user->set_role('administrator');
    if($created&&$url!==''){ $user->user_url=$url;wp_update_user($user); }
    wp_install_defaults($uid);wp_install_maybe_enable_pretty_permalinks();flush_rewrite_rules();
    wp_new_blog_notification($blog_title,$url,$uid,$mailpw?$pw:'Das bei der Installation gewählte Passwort.');
    do_action('wp_install',$user);
    return ['url'=>$url,'user_id'=>$uid,'password'=>$pw,'password_message'=>$msg];
} }
if(!function_exists('wp_install_defaults')){ function wp_install_defaults($user_id) {   // Standardkategorie, erster Beitrag, Beispielseite, erster Kommentar (nur in leerer Datenbank)
    global $wpdb;
    $cat=term_exists('uncategorized','category');
    if(!$cat){ $cat=wp_insert_term(__('Uncategorized'),'category',['slug'=>'uncategorized']); }
    $cid=is_wp_error($cat)?1:(int)(is_array($cat)?$cat['term_id']:$cat);update_option('default_category',$cid);
    if((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('post','page') AND post_status <> 'auto-draft'")>0)return;
    $now=current_time('mysql');$gmt=current_time('mysql',1);
    $pid=wp_insert_post(['post_author'=>$user_id,'post_date'=>$now,'post_date_gmt'=>$gmt,'post_content'=>'<!-- wp:paragraph --><p>Willkommen bei WordPress. Das ist dein erster Beitrag. Bearbeite oder lösche ihn und fang an zu schreiben.</p><!-- /wp:paragraph -->',
        'post_title'=>'Hallo Welt!','post_name'=>'hallo-welt','post_status'=>'publish','post_type'=>'post','comment_status'=>'open','post_category'=>[$cid]]);
    if($pid&&!is_wp_error($pid)){
        wp_insert_comment(['comment_post_ID'=>$pid,'comment_author'=>'Ein WordPress-Kommentator','comment_author_email'=>'wapuu@wordpress.example','comment_author_url'=>'https://wordpress.org/','comment_date'=>$now,'comment_date_gmt'=>$gmt,
            'comment_content'=>'Hallo, das ist ein Kommentar. Zum Moderieren, Bearbeiten und Löschen von Kommentaren besuche einfach die Kommentare-Ansicht im Dashboard.','comment_approved'=>1]);
    }
    wp_insert_post(['post_author'=>$user_id,'post_date'=>$now,'post_date_gmt'=>$gmt,'post_content'=>'<!-- wp:paragraph --><p>Dies ist eine Beispielseite. Sie unterscheidet sich von einem Beitrag, da sie an einer Stelle bleibt und in der Navigation erscheint.</p><!-- /wp:paragraph -->',
        'post_title'=>'Beispiel-Seite','post_name'=>'beispiel-seite','post_status'=>'publish','post_type'=>'page','comment_status'=>'closed']);
} }
if(!function_exists('wp_install_maybe_enable_pretty_permalinks')){ function wp_install_maybe_enable_pretty_permalinks() {   // schöne Links, wenn mod_rewrite verfügbar ist
    global $wp_rewrite;
    if(get_option('permalink_structure'))return true;
    if(!got_mod_rewrite()&&!iis7_supports_permalinks())return false;
    if(is_object($wp_rewrite)&&method_exists($wp_rewrite,'set_permalink_structure'))$wp_rewrite->set_permalink_structure('/%postname%/');else update_option('permalink_structure','/%postname%/');
    flush_rewrite_rules();return true;
} }
if(!function_exists('wp_new_blog_notification')){ function wp_new_blog_notification($blog_title, $blog_url, $user_id, $password) {   // E-Mail an den neuen Administrator
    $u=get_userdata($user_id);if(!$u)return;
    $msg=sprintf("Deine neue WordPress-Website wurde eingerichtet unter:\n\n%1\$s\n\nBenutzername: %2\$s\nPasswort: %3\$s\n\nAnmelden: %4\$s\n",$blog_url,$u->user_login,$password,wp_login_url());
    wp_mail($u->user_email,sprintf('[%s] Neue Website',wp_specialchars_decode($blog_title)),$msg);
} }

/* ───────── Kern-Update (update-core.php) ───────── */
if(!function_exists('update_core')){ function update_core($from, $to) {   // Kern-Dateien werden vom CMS-Installer verwaltet, nicht von dieser Funktion
    return new WP_Error('update_core_unsupported','Der WordPress-Kern wird im CMS nicht über update_core() aktualisiert.');
} }
if(!function_exists('_preload_old_requests_classes_and_interfaces')){ function _preload_old_requests_classes_and_interfaces($to) {} }   // Requests-Bibliothek wird nicht ausgetauscht
if(!function_exists('_redirect_to_about_wordpress')){ function _redirect_to_about_wordpress($new_version) {
    wp_redirect(self_admin_url('about.php?updated'));
    if(!empty($GLOBALS['rrw_wp_die_throws']))throw new RRW_WP_Die('redirect',302);
    exit;
} }
if(!function_exists('_upgrade_422_find_genericons_files_in_folder')){ function _upgrade_422_find_genericons_files_in_folder($directory) {   // example.html in „genericons“-Ordnern (bekannte XSS-Quelle)
    $d=trailingslashit($directory);$out=[];
    if(!is_dir($d))return $out;
    if(is_file($d.'example.html')&&false!==stripos((string)file_get_contents($d.'example.html'),'genericons'))$out[]=$d.'example.html';
    foreach(glob($d.'*',GLOB_ONLYDIR)?:[] as $s)$out=array_merge($out,_upgrade_422_find_genericons_files_in_folder($s));
    return $out;
} }
if(!function_exists('_upgrade_422_remove_genericons')){ function _upgrade_422_remove_genericons() {
    $dirs=[WP_PLUGIN_DIR];foreach((array)search_theme_directories()?:[] as $t)if(!empty($t['theme_root']))$dirs[]=$t['theme_root'];
    foreach(array_unique($dirs) as $dir)foreach(_upgrade_422_find_genericons_files_in_folder($dir) as $f)@unlink($f);
} }
if(!function_exists('_upgrade_core_deactivate_incompatible_plugins')){ function _upgrade_core_deactivate_incompatible_plugins() {   // aktive Plugins mit zu hoher „Requires at least“-Angabe abschalten
    global $wp_version;$off=[];
    foreach(get_option('active_plugins',[]) as $pl){
        $f=WP_PLUGIN_DIR.'/'.$pl;if(!is_file($f))continue;
        $req=get_file_data($f,['r'=>'Requires at least'])['r'];
        if($req!==''&&version_compare($wp_version,$req,'<'))$off[]=$pl;
    }
    if($off){ deactivate_plugins($off,true);update_option('wp_force_deactivated_plugins',array_merge((array)get_option('wp_force_deactivated_plugins',[]),array_fill_keys($off,time()))); }
    return $off;
} }
if(!function_exists('_upgrade_440_force_deactivate_incompatible_plugins')){ function _upgrade_440_force_deactivate_incompatible_plugins() { return _upgrade_core_deactivate_incompatible_plugins(); } }

/* ───────── PclZip-Hilfen (class-pclzip.php) ───────── */
if(!function_exists('PclZipUtilPathReduction')){ function PclZipUtilPathReduction($p_dir) {   // „a/b/../c“ → „a/c“; führende „..“ bleiben
    if($p_dir==='')return '';
    $parts=explode('/',$p_dir);$out=[];$skip=0;$n=count($parts);
    for($i=$n-1;$i>=0;$i--){
        $p=$parts[$i];
        if($p==='.')continue;
        if($p==='..'){ $skip++;continue; }
        if($p===''){ if($i===$n-1||$i===0)array_unshift($out,''); continue; }
        if($skip>0){ $skip--;continue; }
        array_unshift($out,$p);
    }
    $r=implode('/',$out);
    return str_repeat('../',$skip).$r;
} }
if(!function_exists('PclZipUtilPathInclusion')){ function PclZipUtilPathInclusion($p_dir, $p_path) {   // 0 = nicht enthalten, 1 = innerhalb, 2 = gleich
    if($p_dir==='.'||str_starts_with($p_dir,'./'))$p_dir=getcwd().'/'.substr($p_dir,1);
    if($p_path==='.'||str_starts_with($p_path,'./'))$p_path=getcwd().'/'.substr($p_path,1);
    $d=explode('/',PclZipUtilPathReduction($p_dir));$p=explode('/',PclZipUtilPathReduction($p_path));
    $d=array_values(array_filter($d,'strlen'));$p=array_values(array_filter($p,'strlen'));
    if(count($d)>count($p))return 0;
    foreach($d as $i=>$seg)if($seg!==$p[$i])return 0;
    return count($d)===count($p)?2:1;
} }
if(!function_exists('PclZipUtilCopyBlock')){ function PclZipUtilCopyBlock($p_src, $p_dest, $p_size, $p_mode=0) {   // Modi: 0 roh→roh, 1 gz→gz, 2 roh→gz, 3 gz→roh
    $rd=in_array($p_mode,[1,3],true)?'gzread':'fread';$wr=in_array($p_mode,[1,2],true)?'gzwrite':'fwrite';
    while($p_size>0){ $n=min(2048,$p_size);$b=@$rd($p_src,$n);if($b===false||$b==='')break;@$wr($p_dest,$b,strlen($b));$p_size-=strlen($b); }
    return 1;
} }
if(!function_exists('PclZipUtilRename')){ function PclZipUtilRename($p_src, $p_dest) {
    if(@rename($p_src,$p_dest))return 1;
    if(@copy($p_src,$p_dest)){ @unlink($p_src);return 1; }
    return 0;
} }
if(!function_exists('PclZipUtilOptionText')){ function PclZipUtilOptionText($p_option) {   // Name der Konstante PCLZIP_OPT_*/CB_*/ATT_*
    foreach((get_defined_constants(true)['user']??[]) as $k=>$v)if($v===$p_option&&preg_match('/^PCLZIP_(OPT|CB|ATT)_/',$k))return $k;
    return 'Unknown';
} }
if(!function_exists('PclZipUtilTranslateWinPath')){ function PclZipUtilTranslateWinPath($p_path, $p_remove_disk_letter=true) {   // nur unter Windows: Laufwerksbuchstabe weg, „\“ → „/“
    if(!stristr(php_uname(),'windows'))return $p_path;
    if($p_remove_disk_letter&&($p=strpos($p_path,':'))!==false)$p_path=substr($p_path,$p+1);
    return str_replace('\\','/',$p_path);
} }

/* ───────── Danksagungen (credits.php) ───────── */
if(!function_exists('wp_credits')){ function wp_credits($locale='') {   // nur aus dem Zwischenspeicher; kein Abruf bei wordpress.org
    global $wp_version;$locale=$locale?:get_user_locale();
    $r=get_site_transient('wordpress_credits_'.md5($wp_version.'-'.$locale));
    return is_array($r)?$r:false;
} }
if(!function_exists('_wp_credits_add_profile_link')){ function _wp_credits_add_profile_link(&$display_name, $username, $profiles) { $display_name='<a href="'.esc_url(sprintf($profiles,$username)).'">'.esc_html($display_name).'</a>'; } }
if(!function_exists('_wp_credits_build_object_link')){ function _wp_credits_build_object_link(&$data) { $data='<a href="'.esc_url($data[1]).'">'.esc_html($data[0]).'</a>'; } }
if(!function_exists('wp_credits_section_title')){ function wp_credits_section_title($title='') { if($title==='')return;echo '<h3 class="wp-people-group">'.esc_html($title).'</h3>'."\n"; } }
if(!function_exists('wp_credits_section_list')){ function wp_credits_section_list($credits=[], $slug='') {   // Liste von [Name, Gravatar, Benutzername]-Einträgen
    $g=$credits['groups'][$slug]??null;$items=$g['data']??($credits['data']??[]);if(!$items)return;
    $profiles=$credits['data']['profiles']??'https://profiles.wordpress.org/%s';
    echo '<ul class="wp-people-group'.($slug?'" id="wp-people-group-'.esc_attr($slug):'').'">'."\n";
    foreach((array)$items as $k=>$p){ $p=(array)$p;$name=(string)($p[0]??$k);$user=(string)($p[2]??$k);_wp_credits_add_profile_link($name,$user,$profiles);echo '<li class="wp-person" id="wp-person-'.esc_attr(sanitize_key($user)).'">'.$name.'</li>'."\n"; }
    echo '</ul>';
} }

/* ───────── Sprachpakete (translation-install.php) ───────── */
if(!function_exists('wp_can_install_language_pack')){ function wp_can_install_language_pack() {
    if(!wp_is_file_mod_allowed('can_install_language_pack'))return false;
    $d=WP_LANG_DIR;return is_dir($d)?is_writable($d):(is_dir(dirname($d))&&is_writable(dirname($d)));
} }
if(!function_exists('wp_download_language_pack')){ function wp_download_language_pack($download) {   // lädt das Sprachpaket über den CMS-Installer; liefert den Sprachcode oder false
    if($download===''||!preg_match('/^[a-z]{2,3}(_[A-Za-z0-9]+)*$/',$download))return false;
    if(in_array($download,get_available_languages(),true))return $download;
    if(!wp_can_install_language_pack()||!function_exists('rrw_wpi_fetch_translation'))return false;
    return rrw_wpi_fetch_translation('core','default',RRW_WP_VERSION,$download)?$download:false;
} }
if(!function_exists('wp_install_language_form')){ function wp_install_language_form($languages) {   // Auswahlliste (aus wp_get_available_translations())
    echo '<fieldset class="language-chooser"><legend class="screen-reader-text">Sprache</legend><ul>';
    echo '<li><label><input type="radio" name="language" value="" checked> English (United States)</label></li>';
    foreach((array)$languages as $l){ $l=(array)$l;if(empty($l['language']))continue;
        echo '<li><label><input type="radio" name="language" value="'.esc_attr($l['language']).'"> '.esc_html($l['native_name']??$l['english_name']??$l['language']).'</label></li>'; }
    echo '</ul></fieldset>';
} }

/* ───────── Importer (import.php) ───────── */
if(!function_exists('_usort_by_first_member')){ function _usort_by_first_member($a, $b) { return strnatcasecmp((string)$a[0],(string)$b[0]); } }
if(!function_exists('get_importers')){ function get_importers() {   // registrierte Importer, nach Name sortiert
    global $wp_importers;if(!is_array($wp_importers))return [];
    uasort($wp_importers,'_usort_by_first_member');return $wp_importers;
} }
if(!function_exists('wp_get_popular_importers')){ function wp_get_popular_importers() {   // fest eingebaute Liste der bekannten Importer-Plugins
    $p=['blogger'=>['Blogger','Importiert Beiträge, Kommentare und Benutzer aus einem Blogger-Blog.','blogger-importer'],'wpcat2tag'=>['Kategorien- und Schlagwort-Konverter','Wandelt vorhandene Kategorien in Schlagwörter um oder umgekehrt.','wpcat2tag-importer'],
        'livejournal'=>['LiveJournal','Importiert Beiträge aus LiveJournal.','livejournal-importer'],'movabletype'=>['Movable Type und TypePad','Importiert Beiträge und Kommentare aus Movable Type oder TypePad.','movabletype-importer'],
        'opml'=>['Blogroll','Importiert Links im OPML-Format.','opml-importer'],'rss'=>['RSS','Importiert Beiträge aus einer RSS-Datei.','rss-importer'],'tumblr'=>['Tumblr','Importiert Beiträge und Medien aus Tumblr.','tumblr-importer'],
        'wordpress'=>['WordPress','Importiert Beiträge, Seiten, Kommentare, eigene Felder, Kategorien und Schlagwörter aus einer WXR-Datei.','wordpress-importer']];
    $o=[];foreach($p as $id=>$d)$o[$id]=['name'=>$d[0],'description'=>$d[1],'plugin-slug'=>$d[2],'importer-id'=>$id];
    return $o;
} }

/* ───────── Bildschirm-, Menü- und Spaltenhilfen ───────── */
if(!function_exists('get_hidden_meta_boxes')){ function get_hidden_meta_boxes($screen) {   // ausgeblendete Boxen des Benutzers oder Standard (Filter default_hidden_meta_boxes)
    if(is_string($screen))$screen=convert_to_screen($screen);
    $id=is_object($screen)?(string)$screen->id:'';
    $h=get_user_option("metaboxhidden_$id");
    if(!is_array($h)){ $h=$id==='dashboard'?[]:(in_array($id,['post','page'],true)?['slugdiv','trackbacksdiv','postcustom','postexcerpt','commentstatusdiv','commentsdiv','authordiv','revisionsdiv']:[]);$h=apply_filters('default_hidden_meta_boxes',$h,$screen); }
    return apply_filters('hidden_meta_boxes',$h,$screen,false);
} }
if(!function_exists('meta_box_prefs')){ function meta_box_prefs($screen) {   // Kontrollkästchen „Bildschirmoptionen“ für die Boxen eines Bildschirms
    global $wp_meta_boxes;
    if(is_string($screen))$screen=convert_to_screen($screen);
    if(empty($wp_meta_boxes[$screen->id]))return;
    $hidden=get_hidden_meta_boxes($screen);
    foreach(array_keys($wp_meta_boxes[$screen->id]) as $ctx)foreach(['high','core','default','low'] as $prio){
        foreach($wp_meta_boxes[$screen->id][$ctx][$prio]??[] as $b){
            if(false===$b||!$b['title'])continue;
            if('submitdiv'===$b['id']||'linksubmitdiv'===$b['id'])continue;
            $title=preg_replace('/<[^>]*>.*?<\/[^>]*>|<[^>]*>/s','',(string)$b['title']);
            printf('<label for="%1$s-hide"><input class="hide-postbox-tog" name="%1$s-hide" type="checkbox" id="%1$s-hide" value="%1$s" %2$s />%3$s</label>',esc_attr($b['id']),checked(!in_array($b['id'],$hidden,true),true,false),esc_html($title));
        }
    }
} }
if(!function_exists('add_cssclass')){ function add_cssclass($add, $class) { return empty($class)?$add:$class.' '.$add; } }
if(!function_exists('add_menu_classes')){ function add_menu_classes($menu) {   // „menu-top-first/-last“ an Gruppen der Verwaltungsmenüs
    $first=false;$last=false;$i=0;$mc=count($menu);
    foreach($menu as $order=>$top){
        $i++;
        if(0==$order){ $menu[$order][4]=add_cssclass('menu-top-first',$top[4]);$last=$order;continue; }
        if(str_contains((string)$top[4],'wp-menu-separator')){ $first=true;if($last!==false)$menu[$last][4]=add_cssclass('menu-top-last',$menu[$last][4]);continue; }
        if($first){ $menu[$order][4]=add_cssclass('menu-top-first',$top[4]);$first=false; }
        if($mc==$i)$menu[$order][4]=add_cssclass('menu-top-last',$top[4]);
        $last=$order;
    }
    return apply_filters('add_menu_classes',$menu);
} }
if(!function_exists('register_column_headers')){ function register_column_headers($screen, $columns) {
    $screen=convert_to_screen($screen);$cols=$columns;
    add_filter("manage_{$screen->id}_columns",function() use($cols){ return $cols; },0);
} }
if(!function_exists('print_column_headers')){ function print_column_headers($screen, $with_id=true) {   // Tabellenkopf aus den registrierten Spalten (ohne ausgeblendete)
    $screen=convert_to_screen($screen);$cols=get_column_headers($screen);$hidden=array_merge(get_hidden_columns($screen),(array)get_user_option('manage'.$screen->id.'columnshidden'));
    foreach($cols as $k=>$label){
        $cls=['manage-column','column-'.$k];if(in_array($k,$hidden,true))$cls[]='hidden';
        if('cb'===$k){ $cls[]='check-column';$label='<input id="cb-select-all-'.($with_id?'1':'2').'" type="checkbox"/>'; }
        echo '<th scope="col"'.($with_id?' id="'.esc_attr($k).'"':'').' class="'.esc_attr(implode(' ',$cls)).'">'.$label.'</th>';
    }
} }

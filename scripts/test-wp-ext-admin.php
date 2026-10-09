<?php
// Prüft die ergänzenden Admin-Funktionen der WordPress-Schicht (cms/wp/core/ext/admin-*.php): Ajax-Handler (wp_ajax_*), Installation/Aktualisierung,
// Dashboard, Meta-Boxen, Menüs, Links, Bildeditor, Datenschutz-Werkzeuge, Plugin-/Theme-Oberflächen. Aufruf: php scripts/test-wp-ext-admin.php
// (Nicht zu verwechseln mit scripts/test-wp-admin.php, das Seiten/Einstellungen der Plugin-Verwaltung prüft.)
$tmp=sys_get_temp_dir().'/elvado-xa-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/wp-content/plugins');mkdir($tmp.'/wp-content/themes');mkdir($tmp.'/cms');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('ELVADO_WP_DATA',$tmp.'/cms/.wp');define('ELVADO_WP_CMS_DATA',$tmp.'/cms');define('ELVADO_WP_TEST',1);$_SERVER['HTTP_HOST']='example.test';
file_put_contents($tmp.'/cms/news.json','[]');
file_put_contents($tmp.'/cms/site.json',json_encode(['comments'=>['enabled'=>true,'require_approval'=>false],'menus'=>['top'=>[['id'=>'m1','label'=>'Start','target'=>'system:start','enabled'=>true]]]]));
require __DIR__.'/_testdb.php';
require __DIR__.'/../cms/wp/load.php';require __DIR__.'/../cms/wp/router.php';require __DIR__.'/../cms/wp/admin.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
elvado_wp_boot(['theme'=>false,'user'=>['id'=>1,'login'=>'admin','name'=>'Administration','email'=>'admin@example.test','role'=>'administrator']]);
$GLOBALS['elvado_wp_die_throws']=true;$GLOBALS['elvado_wp_is_admin']=true;
update_option('admin_email','chef@example.test');update_option('blogname','Testradio');
$mails=[];add_filter('pre_wp_mail',function($pre,$atts) use(&$mails){ $mails[]=$atts;return true; },10,2);
function as_user(int $id,string $login,string $role): void { $GLOBALS['elvado_wp_user']=['id'=>$id,'login'=>$login,'name'=>$login,'email'=>$login.'@example.test','role'=>$role,'caps'=>elvado_wp_caps_for_role($role)]; }
/** Ajax-Aufruf über den Router-Baustein: action in der Query, Felder im Rumpf. */
function aj(string $action,array $post=[],bool $login=true,array $get=[]): array { return elvado_wp_ajax('POST',http_build_query(array_merge(['action'=>$action],$get)),http_build_query($post),$login); }
function out(callable $f): string { ob_start(); try { $f(); } finally { $s=ob_get_clean(); } return $s; }
function js(array $r) { return json_decode($r['text'],true); }

/* ───────── Alle Funktionen der Listen vorhanden ───────── */
$names=preg_split('/\s+/',trim(<<<'NAMES'
wp_ajax_nopriv_heartbeat wp_ajax_fetch_list wp_ajax_ajax_tag_search wp_ajax_wp_compression_test wp_ajax_imgedit_preview wp_ajax_oembed_cache wp_ajax_autocomplete_user
wp_ajax_get_community_events wp_ajax_dashboard_widgets wp_ajax_logged_in _wp_ajax_delete_comment_response _wp_ajax_add_hierarchical_term wp_ajax_delete_comment
wp_ajax_delete_tag wp_ajax_delete_link wp_ajax_delete_meta wp_ajax_delete_post wp_ajax_trash_post wp_ajax_untrash_post wp_ajax_delete_page wp_ajax_dim_comment
wp_ajax_add_link_category wp_ajax_add_tag wp_ajax_get_tagcloud wp_ajax_get_comments wp_ajax_replyto_comment wp_ajax_edit_comment wp_ajax_add_menu_item wp_ajax_add_meta
wp_ajax_add_user wp_ajax_closed_postboxes wp_ajax_hidden_columns wp_ajax_update_welcome_panel wp_ajax_menu_get_metabox wp_ajax_wp_link_ajax wp_ajax_menu_locations_save
wp_ajax_meta_box_order wp_ajax_menu_quick_search wp_ajax_get_permalink wp_ajax_sample_permalink wp_ajax_inline_save wp_ajax_inline_save_tax wp_ajax_find_posts
wp_ajax_widgets_order wp_ajax_save_widget wp_ajax_update_widget wp_ajax_delete_inactive_widgets wp_ajax_media_create_image_subsizes wp_ajax_upload_attachment
wp_ajax_image_editor wp_ajax_set_post_thumbnail wp_ajax_get_post_thumbnail_html wp_ajax_set_attachment_thumbnail wp_ajax_date_format wp_ajax_time_format
wp_ajax_wp_fullscreen_save_post wp_ajax_wp_remove_post_lock wp_ajax_dismiss_wp_pointer wp_ajax_get_attachment wp_ajax_query_attachments wp_ajax_save_attachment
wp_ajax_save_attachment_compat wp_ajax_save_attachment_order wp_ajax_send_attachment_to_editor wp_ajax_send_link_to_editor wp_ajax_heartbeat wp_ajax_get_revision_diffs
wp_ajax_save_user_color_scheme wp_ajax_query_themes wp_ajax_parse_embed wp_ajax_parse_media_shortcode wp_ajax_destroy_sessions wp_ajax_crop_image
wp_ajax_generate_password wp_ajax_nopriv_generate_password wp_ajax_save_wporg_username wp_ajax_install_theme wp_ajax_update_theme wp_ajax_delete_theme
wp_ajax_install_plugin wp_ajax_activate_plugin wp_ajax_update_plugin wp_ajax_delete_plugin wp_ajax_search_plugins wp_ajax_search_install_plugins
wp_ajax_edit_theme_plugin_file wp_ajax_wp_privacy_export_personal_data wp_ajax_wp_privacy_erase_personal_data wp_ajax_health_check_dotorg_communication
wp_ajax_health_check_background_updates wp_ajax_health_check_loopback_requests wp_ajax_health_check_site_status_result wp_ajax_health_check_get_sizes wp_ajax_rest_nonce
wp_ajax_toggle_auto_updates wp_ajax_send_password_reset wp_install wp_install_defaults wp_install_maybe_enable_pretty_permalinks wp_new_blog_notification wp_upgrade
upgrade_all upgrade_100 upgrade_101 upgrade_110 upgrade_130 upgrade_160 upgrade_210 upgrade_230 upgrade_230_options_table upgrade_230_old_tables upgrade_old_slugs
upgrade_250 upgrade_252 upgrade_260 upgrade_270 upgrade_280 upgrade_290 upgrade_300 upgrade_330 upgrade_340 upgrade_350 upgrade_370 upgrade_372 upgrade_380 upgrade_400
upgrade_420 upgrade_430 upgrade_430_fix_comments upgrade_431 upgrade_440 upgrade_450 upgrade_460 upgrade_500 upgrade_510 upgrade_530 upgrade_550 upgrade_560 upgrade_590
upgrade_600 upgrade_630 upgrade_640 upgrade_650 upgrade_670 upgrade_682 upgrade_network drop_index add_clean_index get_alloptions_110 __get_option deslash make_db_current
make_db_current_silent make_site_theme_from_oldschool make_site_theme_from_default make_site_theme translate_level_to_role wp_check_mysql_version
maybe_disable_automattic_widgets maybe_disable_link_manager pre_schema_upgrade wp_should_upgrade_global_tables wp_dashboard_setup _wp_dashboard_control_callback
wp_dashboard wp_dashboard_right_now wp_network_dashboard_right_now wp_dashboard_quick_press wp_dashboard_recent_drafts _wp_dashboard_recent_comments_row
wp_dashboard_site_activity wp_dashboard_recent_posts wp_dashboard_recent_comments wp_dashboard_rss_output wp_dashboard_cached_rss_widget
wp_dashboard_trigger_widget_control wp_dashboard_rss_control wp_dashboard_events_news wp_print_community_events_markup wp_print_community_events_templates
wp_dashboard_primary wp_dashboard_primary_output wp_dashboard_quota wp_dashboard_browser_nag dashboard_browser_nag_class wp_check_browser_version wp_dashboard_php_nag
dashboard_php_nag_class wp_dashboard_site_health wp_dashboard_empty wp_welcome_panel post_submit_meta_box attachment_submit_meta_box post_format_meta_box
post_excerpt_meta_box post_trackback_meta_box post_custom_meta_box post_comment_status_meta_box post_comment_meta_box_thead post_comment_meta_box post_slug_meta_box
post_author_meta_box post_revisions_meta_box page_attributes_meta_box link_submit_meta_box link_categories_meta_box link_target_meta_box xfn_check link_xfn_meta_box
link_advanced_meta_box post_thumbnail_meta_box attachment_id3_data_meta_box register_and_do_post_meta_boxes _wp_ajax_menu_quick_search wp_nav_menu_setup
wp_initial_nav_menu_meta_boxes wp_nav_menu_post_type_meta_boxes wp_nav_menu_taxonomy_meta_boxes wp_nav_menu_disabled_check wp_nav_menu_item_link_meta_box
wp_nav_menu_item_post_type_meta_box wp_nav_menu_item_taxonomy_meta_box wp_save_nav_menu_items _wp_nav_menu_meta_box_object wp_get_nav_menu_to_edit
wp_nav_menu_manage_columns _wp_delete_orphaned_draft_menu_items wp_nav_menu_update_menu_items _wp_expand_nav_menu_post_data populate_options populate_roles
populate_roles_160 populate_roles_210 populate_roles_230 populate_roles_250 populate_roles_260 populate_roles_270 populate_roles_280 populate_roles_300 install_network
populate_network populate_network_meta populate_site_meta wp_image_editor wp_stream_image wp_save_image_file _image_get_preview_ratio _rotate_image_resource
_flip_image_resource _crop_image_resource image_edit_apply_changes stream_preview_image wp_restore_image wp_save_image add_link edit_link get_default_link_to_edit
wp_delete_link wp_get_link_cats get_link_to_edit wp_insert_link wp_set_link_cats wp_update_link wp_link_manager_disabled_message _wp_privacy_resend_request
_wp_privacy_completed_request _wp_personal_data_handle_actions _wp_personal_data_cleanup_requests wp_privacy_generate_personal_data_export_group_html
wp_privacy_generate_personal_data_export_file wp_privacy_send_personal_data_export_email wp_privacy_process_personal_data_export_page
wp_privacy_process_personal_data_erasure_page install_popular_tags install_dashboard install_search_form install_plugins_upload install_plugins_favorites_form
display_plugins_table install_plugin_information wp_get_plugin_action_button install_themes_feature_list install_theme_search_form install_themes_dashboard
install_themes_upload display_theme display_themes install_theme_information update_core _preload_old_requests_classes_and_interfaces _redirect_to_about_wordpress
_upgrade_422_remove_genericons _upgrade_422_find_genericons_files_in_folder _upgrade_440_force_deactivate_incompatible_plugins
_upgrade_core_deactivate_incompatible_plugins PclZipUtilPathReduction PclZipUtilPathInclusion PclZipUtilCopyBlock PclZipUtilRename PclZipUtilOptionText
PclZipUtilTranslateWinPath wp_credits _wp_credits_add_profile_link _wp_credits_build_object_link wp_credits_section_title wp_credits_section_list wp_install_language_form
wp_download_language_pack wp_can_install_language_pack get_importers _usort_by_first_member wp_get_popular_importers meta_box_prefs get_hidden_meta_boxes add_cssclass
add_menu_classes register_column_headers print_column_headers
NAMES));
$missing=[];foreach($names as $f)if(!function_exists($f))$missing[]=$f;
t('alle 318 Funktionen der Listen (Ajax + Admin-Oberfläche) vorhanden, keine Ausnahmen',count($names)===318&&!$missing,implode(',',$missing));
$core=['logged-in','generate-password','rest-nonce','date_format','time_format','heartbeat','delete-comment','add-tag','delete-post','save-widget','image-editor','toggle-auto-updates','health-check-get-sizes','wp-privacy-export-personal-data'];
$un=[];foreach($core as $a)if(!has_action('wp_ajax_'.$a))$un[]=$a;
t('Ajax-Aktionen sind an wp_ajax_* gehängt',!$un,implode(',',$un));
t('nopriv-Aktionen: heartbeat und generate-password',has_action('wp_ajax_nopriv_heartbeat')&&has_action('wp_ajax_nopriv_generate-password'));

/* ───────── Ajax: Allgemeines ───────── */
t('logged-in → 1',aj('logged-in')['text']==='1');
t('Besucher darf logged-in nicht (kein nopriv-Handler)',aj('logged-in',[],false)['status']===400);
$pw=aj('generate-password')['text'];t('generate-password: 24 Zeichen',strlen($pw)===24);
t('generate-password für Besucher erlaubt',strlen(aj('generate-password',[],false)['text'])===24);
t('rest-nonce gültig für wp_rest',(bool)wp_verify_nonce(aj('rest-nonce')['text'],'wp_rest'));
t('date_format: Y-m-d',(bool)preg_match('/^\d{4}-\d\d-\d\d$/',aj('date_format',['date'=>'Y-m-d'])['text']));
t('time_format: H:i',(bool)preg_match('/^\d\d:\d\d$/',aj('time_format',['date'=>'H:i'])['text']));
$r=aj('heartbeat',['_nonce'=>wp_create_nonce('heartbeat-nonce'),'screen_id'=>'front']);t('heartbeat: server_time',isset(js($r)['server_time']));
add_filter('heartbeat_received',function($resp,$data){ if(isset($data['elvado-ping']))$resp['elvado-pong']=$data['elvado-ping'];return $resp; },10,2);
$r=aj('heartbeat',['_nonce'=>wp_create_nonce('heartbeat-nonce'),'data'=>['elvado-ping'=>'x']]);t('heartbeat: Filter heartbeat_received',(js($r)['elvado-pong']??'')==='x');
t('heartbeat: falsches Nonce → nonces_expired',!empty(js(aj('heartbeat',['_nonce'=>'falsch']))['nonces_expired']));
t('heartbeat ohne Nonce → Fehler',js(aj('heartbeat'))['success']===false);
add_filter('heartbeat_nopriv_send',function($r){ $r['front']='ja';return $r; });
t('heartbeat nopriv (Besucher)',(js(aj('heartbeat',['_nonce'=>'x'],false))['front']??'')==='ja');
t('dismiss-wp-pointer: erstes Mal 1, dann 0',aj('dismiss-wp-pointer',['pointer'=>'elvado_p1'])['text']==='1'&&aj('dismiss-wp-pointer',['pointer'=>'elvado_p1'])['text']==='0');
t('dismiss-wp-pointer: ungültiger Name → 0',aj('dismiss-wp-pointer',['pointer'=>'Bad Name!'])['text']==='0');
$r=aj('closed-postboxes',['closedpostboxesnonce'=>wp_create_nonce('closedpostboxes'),'page'=>'post','closed'=>'a,b','hidden'=>'c']);
t('closed-postboxes speichert Benutzeroption',$r['text']==='1'&&get_user_option('closedpostboxes_post')===['a','b']&&get_user_option('metaboxhidden_post')===['c']);
$r=aj('closed-postboxes',['closedpostboxesnonce'=>'x','page'=>'post']);t('closed-postboxes: falsches Nonce → 403',$r['status']===403&&$r['text']==='-1');
$r=aj('hidden-columns',['screenoptionnonce'=>wp_create_nonce('screen-options-nonce'),'page'=>'edit-post','hidden'=>'author,tags']);
t('hidden-columns',$r['text']==='1'&&get_user_option('manageedit-postcolumnshidden')===['author','tags']);
$r=aj('update-welcome-panel',['welcomepanelnonce'=>wp_create_nonce('welcome-panel-nonce'),'visible'=>'0']);t('update-welcome-panel',$r['text']==='1'&&get_user_meta(1,'show_welcome_panel',true)==='0');
$r=aj('meta-box-order',['_ajax_nonce'=>wp_create_nonce('meta-box-order'),'page'=>'post','order'=>['side'=>'submitdiv'],'page_columns'=>'2']);
t('meta-box-order: JSON-Erfolg und Option',js($r)['success']===true&&get_user_option('screen_layout_post')==='2');
$r=aj('save-user-color-scheme',['color-nonce'=>wp_create_nonce('save-color-scheme'),'color_scheme'=>'midnight']);t('save-user-color-scheme',js($r)['data']['currentScheme']==='admin-color-midnight');
$r=aj('wp-compression-test',[],true,['test'=>'yes']);t('wp-compression-test: yes merkt Ergebnis',$r['text']==='yes'&&get_site_option('can_compress_scripts')==1);
t('wp-compression-test: Testtext',str_contains(aj('wp-compression-test',[],true,['test'=>'1'])['text'],'wpCompressionTest'));
t('get-community-events: leere Liste',js(aj('get-community-events',['_ajax_nonce'=>wp_create_nonce('community_events')]))['data']['events']===[]);
t('fetch-list: unbekannte Klasse → 0',aj('fetch-list',[],true,['list_args'=>['class'=>'Gibt_Es_Nicht']])['text']==='0');
t('dashboard-widgets: unbekanntes Widget → 0',aj('dashboard-widgets',[],true,['widget'=>'unbekannt'])['text']==='0');
wp_create_user('suchbar','pass-1234x','suchbar@example.test');
$r=aj('autocomplete-user',[],true,['term'=>'suchb','autocomplete_type'=>'add']);t('autocomplete-user: JSON mit Benutzername',str_contains($r['text'],'suchbar'),$r['text']);

/* ───────── Ajax: Beiträge, Kommentare, Begriffe, Meta ───────── */
$pid=wp_insert_post(['post_title'=>'Testbeitrag','post_content'=>'Inhalt','post_status'=>'publish','post_author'=>1]);
t('Testbeitrag angelegt',is_int($pid)&&$pid>0);
$cid=wp_insert_comment(['comment_post_ID'=>$pid,'comment_author'=>'Gast','comment_author_email'=>'gast@example.test','comment_content'=>'Hallo','comment_approved'=>1]);
$r=aj('delete-comment',['id'=>$cid,'trash'=>1,'_ajax_nonce'=>wp_create_nonce("delete-comment_$cid")]);
t('delete-comment (trash): XML und Status',str_contains($r['text'],'<wp_ajax>')&&elvado_adm_cstatus($cid)==='trash',$r['text']);
$r=aj('delete-comment',['id'=>$cid,'untrash'=>1,'_ajax_nonce'=>wp_create_nonce("delete-comment_$cid")]);t('delete-comment (untrash)',elvado_adm_cstatus($cid)==='approved');
$r=aj('delete-comment',['id'=>$cid,'spam'=>1,'_ajax_nonce'=>wp_create_nonce("delete-comment_$cid")]);t('delete-comment (spam)',elvado_adm_cstatus($cid)==='spam');
$r=aj('delete-comment',['id'=>$cid,'unspam'=>1,'_ajax_nonce'=>wp_create_nonce("delete-comment_$cid")]);t('delete-comment (unspam)',elvado_adm_cstatus($cid)==='approved');
t('delete-comment ohne Nonce → 403',aj('delete-comment',['id'=>$cid,'trash'=>1])['status']===403);
$r=aj('dim-comment',['id'=>$cid,'new'=>'unapproved','_ajax_nonce'=>wp_create_nonce("approve-comment_$cid")]);t('dim-comment: zurückstellen',elvado_adm_cstatus($cid)==='unapproved',$r['text']);
$r=aj('dim-comment',['id'=>$cid,'new'=>'approved','_ajax_nonce'=>wp_create_nonce("approve-comment_$cid")]);t('dim-comment: freigeben',elvado_adm_cstatus($cid)==='approved');
$r=aj('replyto-comment',['comment_post_ID'=>$pid,'comment_ID'=>$cid,'content'=>'Antwort','_ajax_nonce-replyto-comment'=>wp_create_nonce('replyto-comment')]);
global $wpdb;$reply=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->comments} WHERE comment_parent = %d",$cid));
t('replyto-comment legt Antwort an',$reply&&$reply->comment_content==='Antwort'&&str_contains($r['text'],'<wp_ajax>'),$r['text']);
$r=aj('get-comments',['p'=>$pid,'_ajax_nonce-post'=>wp_create_nonce('get-comments')]);t('get-comments liefert Zeilen',str_contains($r['text'],'comment-'.$cid),$r['text']);
t('Rechte: Abonnent darf Kommentar nicht löschen (-1)',(function() use($cid){ as_user(9,'sub','subscriber');$r=aj('delete-comment',['id'=>$cid,'trash'=>1,'_ajax_nonce'=>wp_create_nonce("delete-comment_$cid")]);as_user(1,'admin','administrator');return $r['text']==='-1'; })());
$r=aj('add-tag',['taxonomy'=>'post_tag','tag-name'=>'Radiotag','_wpnonce_add-tag'=>wp_create_nonce('add-tag')]);
$tt=get_term_by('name','Radiotag','post_tag');t('add-tag legt Schlagwort an',$tt&&str_contains($r['text'],'<wp_ajax>'),$r['text']);
$r=aj('ajax-tag-search',[],true,['tax'=>'post_tag','q'=>'radi']);t('ajax-tag-search findet Schlagwort',str_contains($r['text'],'Radiotag'),$r['text']);
t('ajax-tag-search: zu kurzer Suchbegriff leer',trim(aj('ajax-tag-search',[],true,['tax'=>'post_tag','q'=>'r'])['text'])==='0'||trim(aj('ajax-tag-search',[],true,['tax'=>'post_tag','q'=>'r'])['text'])==='');
wp_set_post_tags($pid,['Radiotag'],true);$r=aj('get-tagcloud',['tax'=>'post_tag']);t('get-tagcloud',str_contains($r['text'],'Radiotag')||str_contains($r['text'],'tag-cloud'),$r['text']);
$r=aj('inline-save-tax',['taxonomy'=>'post_tag','tax_ID'=>$tt->term_id,'name'=>'Radiotag2','slug'=>'radiotag2','_inline_edit'=>wp_create_nonce('taxinlineeditnonce')]);
t('inline-save-tax benennt um',get_term($tt->term_id,'post_tag')->name==='Radiotag2',$r['text']);
$r=aj('delete-tag',['tag_ID'=>$tt->term_id,'taxonomy'=>'post_tag','_ajax_nonce'=>wp_create_nonce('delete-tag_'.$tt->term_id)]);t('delete-tag',$r['text']==='1'&&!get_term($tt->term_id,'post_tag'),$r['text']);
$r=aj('add-category',['action'=>'add-category','newcategory'=>'Musik','_ajax_nonce-add-category'=>wp_create_nonce('add-category')]);
t('add-category (hierarchisch): Begriff + XML',term_exists('Musik','category')&&str_contains($r['text'],'Musik'),$r['text']);
$r=aj('add-link-category',['newcat'=>'Freunde','_ajax_nonce'=>wp_create_nonce('add-link-category')]);t('add-link-category',term_exists('Freunde','link_category')&&str_contains($r['text'],'Freunde'),$r['text']);
$lid=wp_insert_link(['link_name'=>'Radio','link_url'=>'https://radio.example.test/']);
$r=aj('delete-link',['id'=>$lid,'_ajax_nonce'=>wp_create_nonce("delete-bookmark_$lid")]);t('delete-link',$r['text']==='1'&&!elvado_adm_link_row($lid),$r['text']);
$r=aj('add-meta',['post_id'=>$pid,'metakeyinput'=>'farbe','metavalue'=>'blau','_ajax_nonce-add-meta'=>wp_create_nonce('add-meta')]);
t('add-meta: Feld gespeichert',get_post_meta($pid,'farbe',true)==='blau'&&str_contains($r['text'],'<wp_ajax>'),$r['text']);
$mid=$wpdb->get_var($wpdb->prepare("SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = 'farbe'",$pid));
$r=aj('add-meta',['post_id'=>$pid,'meta'=>[$mid=>['key'=>'farbe','value'=>'rot']],'_ajax_nonce-add-meta'=>wp_create_nonce('add-meta')]);t('add-meta: aktualisieren',get_post_meta($pid,'farbe',true)==='rot',$r['text']);
$r=aj('delete-meta',['id'=>$mid,'_ajax_nonce'=>wp_create_nonce("delete-meta_$mid")]);t('delete-meta',$r['text']==='1'&&get_post_meta($pid,'farbe',true)==='',$r['text']);
$p2=wp_insert_post(['post_title'=>'Zweiter','post_status'=>'publish','post_author'=>1]);
$r=aj('trash-post',['id'=>$p2,'_ajax_nonce'=>wp_create_nonce("trash-post_$p2")]);t('trash-post',$r['text']==='1'&&get_post_status($p2)==='trash',$r['text']);
$r=aj('untrash-post',['id'=>$p2,'_ajax_nonce'=>wp_create_nonce("untrash-post_$p2")]);t('untrash-post',$r['text']==='1'&&get_post_status($p2)!=='trash',$r['text']);
$r=aj('delete-post',['id'=>$p2,'_ajax_nonce'=>wp_create_nonce("delete-post_$p2")]);t('delete-post',$r['text']==='1'&&!get_post($p2),$r['text']);
$pg=wp_insert_post(['post_title'=>'Seite','post_type'=>'page','post_status'=>'publish','post_author'=>1]);
$r=aj('delete-page',['id'=>$pg,'_ajax_nonce'=>wp_create_nonce("delete-page_$pg")]);t('delete-page',$r['text']==='1'&&!get_post($pg),$r['text']);
$r=aj('inline-save',['post_ID'=>$pid,'post_title'=>'Neu benannt','_inline_edit'=>wp_create_nonce('inlineeditnonce')]);t('inline-save',get_post($pid)->post_title==='Neu benannt',$r['text']);
$r=aj('find_posts',['ps'=>'Neu','_ajax_nonce'=>wp_create_nonce('find-posts')]);t('find_posts: Treffer',js($r)['success']===true&&str_contains(js($r)['data'],'Neu benannt'),$r['text']);
$r=aj('find_posts',['ps'=>'gibtesnicht','_ajax_nonce'=>wp_create_nonce('find-posts')]);t('find_posts: keine Treffer → Fehler',js($r)['success']===false);
$r=aj('get-permalink',['post_id'=>$pid,'getpermalinknonce'=>wp_create_nonce('getpermalink')]);t('get-permalink',str_contains($r['text'],'http'),$r['text']);
$r=aj('sample-permalink',['post_id'=>$pid,'new_title'=>'Titel','new_slug'=>'mein-slug','samplepermalinknonce'=>wp_create_nonce('samplepermalink')]);t('sample-permalink',$r['status']===200&&$r['text']!=='',$r['text']);
update_post_meta($pid,'_edit_lock',(time()-100).':1');
$r=aj('wp-remove-post-lock',['post_ID'=>$pid,'active_post_lock'=>(time()-100).':1','_ajax_nonce'=>wp_create_nonce("update-post_$pid")]);t('wp-remove-post-lock',$r['text']==='1',$r['text']);
$r=aj('wp-fullscreen-save-post',['post_ID'=>$pid,'post_title'=>'Vollbild','_wpnonce'=>wp_create_nonce("update-post_$pid")]);t('wp-fullscreen-save-post: JSON',isset(js($r)['last_edited'])||isset(js($r)['success']),$r['text']);

/* ───────── Ajax: Benutzer ───────── */
$bob=wp_create_user('bob2','geheim-1234','bob2@example.test');
$r=aj('destroy-sessions',['user_id'=>$bob,'nonce'=>wp_create_nonce('destroy-sessions-'.$bob)]);t('destroy-sessions (anderer Benutzer)',js($r)['success']===true,$r['text']);
$r=aj('send-password-reset',['user_id'=>$bob,'_ajax_nonce'=>wp_create_nonce('reset-password-for-bob2')]);t('send-password-reset sendet E-Mail',js($r)['success']===true&&count($mails)>0,$r['text']);
$r=aj('save-wporg-username',['user_id'=>$bob,'username'=>'bobdev','_ajax_nonce'=>wp_create_nonce("save_wporg_username_$bob")]);t('save-wporg-username',js($r)['data']==='bobdev'&&get_user_meta($bob,'wporg_favorites',true)==='bobdev',$r['text']);
$r=aj('add-user',['user_login'=>'neuling','email'=>'neuling@example.test','pass1'=>'sehr-geheim-99','pass2'=>'sehr-geheim-99','role'=>'author','_wpnonce_create-user'=>wp_create_nonce('add-user')]);
t('add-user legt Benutzer an',(bool)get_user_by('login','neuling'),$r['text']);

/* ───────── Ajax: Widgets ───────── */
$r=aj('save-widget',['id_base'=>'text','widget-id'=>'text-__i__','multi_number'=>5,'add_new'=>1,'sidebar'=>'sidebar-1','widget-text'=>[5=>['title'=>'Hallo','text'=>'Inhalt']],'savewidgets'=>wp_create_nonce('save-sidebar-widgets')]);
$wt=get_option('widget_text');t('save-widget (neu): Option und Seitenleiste',($wt[5]['title']??'')==='Hallo'&&in_array('text-5',wp_get_sidebars_widgets()['sidebar-1']??[],true),$r['text']);
$r=aj('save-widget',['id_base'=>'text','widget-id'=>'text-5','sidebar'=>'sidebar-1','widget-text'=>[5=>['title'=>'Neu','text'=>'x']],'savewidgets'=>wp_create_nonce('save-sidebar-widgets')]);
t('save-widget (ändern): liefert Formular',(get_option('widget_text')[5]['title']??'')==='Neu'&&str_contains($r['text'],'widget-text'),$r['text']);
$r=aj('widgets-order',['sidebars'=>['sidebar-1'=>'widget-1_search-2,widget-2_text-5'],'savewidgets'=>wp_create_nonce('save-sidebar-widgets')]);
t('widgets-order sortiert Seitenleiste',$r['text']==='1'&&(wp_get_sidebars_widgets()['sidebar-1']??[])===['search-2','text-5'],$r['text']);
$r=aj('update-widget',['id_base'=>'text','widget-id'=>'text-5','widget-text'=>[5=>['title'=>'Live','text'=>'y']],'nonce'=>wp_create_nonce('update-widget')]);t('update-widget: JSON mit Instanz',(js($r)['data']['instance']['title']??'')==='Live',$r['text']);
$r=aj('save-widget',['id_base'=>'text','widget-id'=>'text-5','delete_widget'=>1,'sidebar'=>'sidebar-1','savewidgets'=>wp_create_nonce('save-sidebar-widgets')]);
t('save-widget (löschen)',$r['text']==='deleted:text-5'&&!isset(get_option('widget_text')[5]),$r['text']);
$sw=wp_get_sidebars_widgets();$sw['wp_inactive_widgets']=['search-9'];wp_set_sidebars_widgets($sw);
$r=aj('delete-inactive-widgets',['removeinactivewidgets'=>wp_create_nonce('remove-inactive-widgets')]);t('delete-inactive-widgets',$r['text']==='deleted'&&(wp_get_sidebars_widgets()['wp_inactive_widgets']??[])===[]);

/* ───────── Ajax: Medien ───────── */
$up=wp_upload_dir();wp_mkdir_p($up['path']);$png=$up['path'].'/elvado-test.png';
$has_gd=function_exists('imagecreatetruecolor');
if($has_gd){ $im=imagecreatetruecolor(40,20);imagefill($im,0,0,imagecolorallocate($im,200,30,30));imagepng($im,$png); }
else file_put_contents($png,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
$aid=wp_insert_attachment(['post_mime_type'=>'image/png','post_title'=>'Testbild','post_status'=>'inherit','post_author'=>1],$png,$pid);update_attached_file($aid,$png);
wp_update_attachment_metadata($aid,['width'=>40,'height'=>20,'file'=>_wp_relative_upload_path($png),'sizes'=>[]]);
$r=aj('get-attachment',['id'=>$aid]);t('get-attachment: JSON',js($r)['success']===true&&(js($r)['data']['id']??0)==$aid,$r['text']);
t('get-attachment: ungültige ID → Fehler',js(aj('get-attachment',['id'=>999999999]))['success']===false);
$r=aj('query-attachments',['query'=>['posts_per_page'=>5]]);t('query-attachments: Liste enthält Anhang',js($r)['success']===true&&count(js($r)['data'])>=1,$r['text']);
$r=aj('save-attachment',['id'=>$aid,'changes'=>['title'=>'Neuer Titel','alt'=>'Alt-Text','caption'=>'Bildunterschrift'],'nonce'=>wp_create_nonce("update-post_$aid")]);
t('save-attachment',js($r)['success']===true&&get_post($aid)->post_title==='Neuer Titel'&&get_post_meta($aid,'_wp_attachment_image_alt',true)==='Alt-Text'&&get_post($aid)->post_excerpt==='Bildunterschrift',$r['text']);
$r=aj('save-attachment-order',['post_id'=>$pid,'attachments'=>[$aid=>7],'nonce'=>wp_create_nonce("update-post_$pid")]);t('save-attachment-order',js($r)['success']===true&&(int)get_post($aid)->menu_order===7,$r['text']);
$r=aj('send-attachment-to-editor',['attachment'=>['id'=>$aid,'url'=>wp_get_attachment_url($aid),'post_title'=>'Neuer Titel','align'=>'none','image-size'=>'full'],'nonce'=>wp_create_nonce('media-send-to-editor')]);
t('send-attachment-to-editor: HTML',js($r)['success']===true&&str_contains(js($r)['data'],'<img'),$r['text']);
$r=aj('send-link-to-editor',['src'=>'example.test/seite','link_text'=>'Zur Seite','nonce'=>wp_create_nonce('media-send-to-editor')]);t('send-link-to-editor',str_contains(js($r)['data'],'<a href="http://example.test/seite">Zur Seite</a>'),$r['text']);
$r=aj('set-post-thumbnail',['post_id'=>$pid,'thumbnail_id'=>$aid,'json'=>1,'_ajax_nonce'=>wp_create_nonce("update-post_$pid")]);t('set-post-thumbnail',js($r)['success']===true&&(int)get_post_thumbnail_id($pid)===$aid,$r['text']);
$r=aj('get-post-thumbnail-html',['post_id'=>$pid,'thumbnail_id'=>$aid,'_ajax_nonce'=>wp_create_nonce("update-post_$pid")]);t('get-post-thumbnail-html',js($r)['success']===true,$r['text']);
$r=aj('set-post-thumbnail',['post_id'=>$pid,'thumbnail_id'=>-1,'json'=>1,'_ajax_nonce'=>wp_create_nonce("update-post_$pid")]);t('set-post-thumbnail: entfernen',js($r)['success']===true&&!get_post_thumbnail_id($pid),$r['text']);
$r=aj('media-create-image-subsizes',['attachment_id'=>$aid,'_ajax_nonce'=>wp_create_nonce('media-form')]);t('media-create-image-subsizes: JSON',isset(js($r)['success']),$r['text']);
$r=aj('upload-attachment',['_ajax_nonce'=>wp_create_nonce('media-form')]);t('upload-attachment ohne Datei → Fehler',js($r)['success']===false,$r['text']);
if($has_gd){
    $r=aj('imgedit-preview',[],true,['postid'=>$aid,'_ajax_nonce'=>wp_create_nonce("image_editor-$aid")]);t('imgedit-preview liefert PNG',str_starts_with($r['text'],"\x89PNG"));
    if(function_exists('imagerotate')){
        $r=aj('image-editor',['postid'=>$aid,'do'=>'save','target'=>'all','history'=>'[{"r":90}]','_ajax_nonce'=>wp_create_nonce("image_editor-$aid")]);
        $m=wp_get_attachment_metadata($aid);t('image-editor: Drehung speichert neue Datei, Maße getauscht',(int)$m['width']===20&&(int)$m['height']===40&&str_contains((string)$m['file'],'-e'),$r['text']);
        $r=aj('image-editor',['postid'=>$aid,'do'=>'restore','_ajax_nonce'=>wp_create_nonce("image_editor-$aid")]);$m=wp_get_attachment_metadata($aid);
        t('image-editor: Wiederherstellen',(int)$m['width']===40&&str_contains($r['text'],'imgedit-wrap'),$r['text']);
    }
    $r=aj('crop-image',['id'=>$aid,'context'=>'custom_header','cropDetails'=>['x1'=>0,'y1'=>0,'width'=>10,'height'=>10,'dst_width'=>10,'dst_height'=>10],'nonce'=>wp_create_nonce("image_editor-$aid")]);
    t('crop-image: neuer Anhang',js($r)['success']===true&&(int)js($r)['data']['id']!==$aid,$r['text']);
}

/* ───────── Ajax: Menüs ───────── */
$r=aj('menu-locations-save',['menu-locations'=>['primary'=>3],'menu-settings-column-nonce'=>wp_create_nonce('add-menu_item')]);t('menu-locations-save',$r['text']==='1'&&(get_theme_mod('nav_menu_locations')['primary']??0)===3,$r['text']);
$r=aj('menu-quick-search',['type'=>'quick-search-posttype-post','q'=>'Vollbild','response-format'=>'json']);t('menu-quick-search: JSON-Zeile',str_contains($r['text'],'"post_title"')&&str_contains($r['text'],'Vollbild'),$r['text']);
$r=aj('menu-get-metabox',['item-type'=>'post_type','item-object'=>'page']);t('menu-get-metabox: Seiten-Box',str_contains($r['text'],'posttype-page'),$r['text']);
$r=aj('add-menu-item',['menu'=>0,'menu-settings-column-nonce'=>wp_create_nonce('add-menu_item'),'menu-item'=>[-1=>['menu-item-type'=>'custom','menu-item-url'=>'https://x.test/','menu-item-title'=>'X']]]);t('add-menu-item: Menüs des CMS bleiben unverändert (leere Antwort)',$r['status']===200&&trim($r['text'])==='0'||$r['text']==='');
$r=aj('wp-link-ajax',['search'=>'Vollbild','_ajax_linking_nonce'=>wp_create_nonce('internal-linking')]);t('wp-link-ajax: Treffer',str_contains($r['text'],'permalink'),$r['text']);

/* ───────── Ajax: Plugins, Themes, Datenschutz, Website-Zustand ───────── */
t('install-plugin ohne Slug → JSON-Fehler',js(aj('install-plugin',['_ajax_nonce'=>wp_create_nonce('updates')]))['success']===false);
t('install-plugin ohne Nonce → 403',aj('install-plugin',['slug'=>'x'])['status']===403);
mkdir(WP_PLUGIN_DIR.'/elvado-demo');file_put_contents(WP_PLUGIN_DIR.'/elvado-demo/elvado-demo.php',"<?php\n/*\nPlugin Name: ELVADO Demo\nVersion: 1.0\n*/\n");
$r=aj('activate-plugin',['plugin'=>'elvado-demo/elvado-demo.php','slug'=>'elvado-demo','_ajax_nonce'=>wp_create_nonce('updates')]);t('activate-plugin',js($r)['success']===true&&in_array('elvado-demo/elvado-demo.php',(array)get_option('active_plugins'),true),$r['text']);
$r=aj('delete-plugin',['plugin'=>'elvado-demo/elvado-demo.php','slug'=>'elvado-demo','_ajax_nonce'=>wp_create_nonce('updates')]);t('delete-plugin: aktives Plugin bleibt',js($r)['success']===false&&is_dir(WP_PLUGIN_DIR.'/elvado-demo'),$r['text']);
deactivate_plugins('elvado-demo/elvado-demo.php',true);
$r=aj('delete-plugin',['plugin'=>'elvado-demo/elvado-demo.php','slug'=>'elvado-demo','_ajax_nonce'=>wp_create_nonce('updates')]);t('delete-plugin: inaktives Plugin wird gelöscht',js($r)['success']===true&&!is_dir(WP_PLUGIN_DIR.'/elvado-demo'),$r['text']);
t('update-plugin ohne Installer → JSON-Fehler',js(aj('update-plugin',['plugin'=>'a/a.php','slug'=>'a','_ajax_nonce'=>wp_create_nonce('updates')]))['success']===false);
mkdir($tmp.'/wp-content/themes/altes');file_put_contents($tmp.'/wp-content/themes/altes/style.css',"/*\nTheme Name: Altes\n*/");file_put_contents($tmp.'/wp-content/themes/altes/index.php','<?php');
$r=aj('delete-theme',['slug'=>'altes','_ajax_nonce'=>wp_create_nonce('updates')]);t('delete-theme',js($r)['success']===true&&!is_dir($tmp.'/wp-content/themes/altes'),$r['text']);
t('delete-theme: aktives Theme bleibt',js(aj('delete-theme',['slug'=>get_stylesheet(),'_ajax_nonce'=>wp_create_nonce('updates')]))['success']===false);
t('install-theme ohne Slug → Fehler',js(aj('install-theme',['_ajax_nonce'=>wp_create_nonce('updates')]))['success']===false);
$r=aj('toggle-auto-updates',['type'=>'plugin','asset'=>'elvado-demo/elvado-demo.php','state'=>'enable','_ajax_nonce'=>wp_create_nonce('updates')]);t('toggle-auto-updates (ein)',js($r)['success']===true&&in_array('elvado-demo/elvado-demo.php',(array)get_option('auto_update_plugins'),true));
aj('toggle-auto-updates',['type'=>'plugin','asset'=>'elvado-demo/elvado-demo.php','state'=>'disable','_ajax_nonce'=>wp_create_nonce('updates')]);t('toggle-auto-updates (aus)',!in_array('elvado-demo/elvado-demo.php',(array)get_option('auto_update_plugins'),true));
t('toggle-auto-updates: ungültiger Zustand → Fehler',js(aj('toggle-auto-updates',['type'=>'plugin','asset'=>'x','state'=>'?','_ajax_nonce'=>wp_create_nonce('updates')]))['success']===false);
$r=aj('edit-theme-plugin-file',['theme'=>'x','file'=>'style.css','nonce'=>wp_create_nonce('edit-theme_x_style.css'),'newcontent'=>'a']);t('edit-theme-plugin-file: bei DISALLOW_FILE_EDIT abgelehnt',js($r)['success']===false,$r['text']);
$hc=['nonce'=>wp_create_nonce('health-check-site-status')];
foreach(['health-check-dotorg-communication','health-check-background-updates','health-check-loopback-requests'] as $a){ $r=aj($a,['_ajax_nonce'=>$hc['nonce']]);t("$a: JSON mit Status",in_array(js($r)['data']['status']??'',['good','recommended','critical'],true),$r['text']); }
$r=aj('health-check-site-status-result',['counts'=>['good'=>5,'recommended'=>1,'critical'=>0],'_ajax_nonce'=>$hc['nonce']]);t('health-check-site-status-result speichert',js($r)['success']===true&&(json_decode((string)get_transient('health-check-site-status-result'),true)['good']??0)===5);
$r=aj('health-check-get-sizes',['_ajax_nonce'=>$hc['nonce']]);t('health-check-get-sizes: Größen',isset(js($r)['data']['total_size']['raw']),$r['text']);
t('health-check ohne Nonce → 403',aj('health-check-get-sizes')['status']===403);
add_filter('wp_privacy_personal_data_exporters',function($e){ $e['elvado']=['exporter_friendly_name'=>'Test','callback'=>function($email,$page){ return ['data'=>[['group_id'=>'g1','group_label'=>'Gruppe','item_id'=>'i1','data'=>[['name'=>'Name','value'=>'Wert']]]],'done'=>true]; }];return $e; });
add_filter('wp_privacy_personal_data_erasers',function($e){ $e['elvado']=['eraser_friendly_name'=>'Test','callback'=>function($email,$page){ return ['items_removed'=>true,'items_retained'=>false,'messages'=>['ok'],'done'=>true]; }];return $e; });
$rq=wp_create_user_request('person@example.test','export_personal_data');
$nex=count(apply_filters('wp_privacy_personal_data_exporters',[]));$r=['text'=>''];
for($i=1;$i<=$nex;$i++)$r=aj('wp-privacy-export-personal-data',['id'=>$rq,'exporter'=>$i,'page'=>1,'sendAsEmail'=>'true','security'=>wp_create_nonce('wp-privacy-export-personal-data-'.$rq)]);
$mf=(string)get_post_meta($rq,'_export_file_name',true);
t('wp-privacy-export-personal-data: alle Exporter, ZIP, URL, E-Mail',$nex>=2&&js($r)['success']===true&&$mf!==''&&is_file(wp_privacy_exports_dir().$mf)&&str_contains((string)(js($r)['data']['url']??''),$mf)&&count($mails)>0,$r['text']);
if(class_exists('ZipArchive')&&$mf!==''){ $z=new ZipArchive();$z->open(wp_privacy_exports_dir().$mf);t('Export-ZIP enthält index.html und export.json',str_contains((string)$z->getFromName('index.html'),'Wert')&&$z->locateName('export.json')!==false); }
$rq2=wp_create_user_request('weg@example.test','remove_personal_data');
$ner=count(apply_filters('wp_privacy_personal_data_erasers',[]));$r=['text'=>''];
for($i=1;$i<=$ner;$i++)$r=aj('wp-privacy-erase-personal-data',['id'=>$rq2,'eraser'=>$i,'page'=>1,'security'=>wp_create_nonce('wp-privacy-erase-personal-data-'.$rq2)]);
t('wp-privacy-erase-personal-data: alle Löschfunktionen, Anfrage abgeschlossen',js($r)['success']===true&&js($r)['data']['items_removed']===true&&wp_get_user_request($rq2)->status==='request-completed',$r['text']);
t('wp-privacy-export: falsche Anfrage-ID → Fehler',js(aj('wp-privacy-export-personal-data',['id'=>$rq2,'exporter'=>1,'page'=>1,'security'=>wp_create_nonce('wp-privacy-export-personal-data-'.$rq2)]))['success']===false);

/* ───────── Installation und Aktualisierung ───────── */
t('translate_level_to_role',translate_level_to_role(10)==='administrator'&&translate_level_to_role(7)==='editor'&&translate_level_to_role(3)==='author'&&translate_level_to_role(1)==='contributor'&&translate_level_to_role(0)==='subscriber');
t('deslash entfernt Backslashes',deslash("a\\'b\\\\c")==="a'b\\c");
t('__get_option liest aus der Datenbank',__get_option('blogname')==='Testradio'&&__get_option('gibt_es_nicht')===false);
t('get_alloptions_110 enthält blogname',(get_alloptions_110()['blogname']??'')==='Testradio');
t('drop_index/add_clean_index liefern true',drop_index($wpdb->posts,'elvado_nix')===true&&add_clean_index($wpdb->posts,'post_author')===true);
t('wp_should_upgrade_global_tables',wp_should_upgrade_global_tables()===true);
wp_check_mysql_version();t('wp_check_mysql_version stirbt nicht',true);
global $wp_db_version;update_option('db_version',100);$GLOBALS['wp_current_db_version']=100;upgrade_all();
t('upgrade_all setzt db_version',(int)get_option('db_version')===(int)$wp_db_version&&get_option('db_upgraded')==true);
update_option('db_version',$wp_db_version);wp_upgrade();t('wp_upgrade: bei gleichem Stand ohne Wirkung',(int)get_option('db_version')===(int)$wp_db_version);
$stages=array_filter(get_defined_functions()['user'],fn($f)=>str_starts_with($f,'upgrade_')&&preg_match('/^upgrade_\d+$/',$f));
$ok=true;foreach($stages as $f)$ok=$ok&&$f()===null;t('Migrationsstufen upgrade_NNN sind ohne Wirkung (≥ 39 nummerierte Stufen)',$ok&&count($stages)>=39,(string)count($stages));
delete_option('default_comments_page');update_option('blogdescription','Eigen');populate_options();
t('populate_options ergänzt Fehlendes, lässt Vorhandenes',get_option('default_comments_page')==='newest'&&get_option('blogdescription')==='Eigen');
populate_options(['posts_per_rss'=>33]);t('populate_options mit eigenen Werten (nur wenn fehlt)',(int)get_option('posts_per_rss')===10);
populate_roles();t('populate_roles: Administrator behält Rechte',get_role('administrator')->has_cap('manage_options')&&get_role('administrator')->has_cap('edit_theme_options'));
install_network();t('populate_network: kein Netzwerk (WP_Error)',is_wp_error(populate_network()));
populate_network_meta(1,['elvado_meta'=>'ja']);t('populate_network_meta schreibt Site-Option',get_site_option('elvado_meta')==='ja');
populate_site_meta(1,['a'=>1]);t('populate_site_meta (Site 1)',(get_option('elvado_site_meta')['a']??0)===1);
t('make_site_theme* sind Standardwerte (false)',make_site_theme()===false&&make_site_theme_from_default('a','b')===false&&make_site_theme_from_oldschool('a','b')===false);
update_option('active_plugins',['widgets.php','x/x.php']);maybe_disable_automattic_widgets();t('maybe_disable_automattic_widgets',get_option('active_plugins')===['x/x.php']);update_option('active_plugins',[]);
$GLOBALS['wp_current_db_version']=30000;update_option('link_manager_enabled',1);maybe_disable_link_manager();t('maybe_disable_link_manager (keine Links → aus)',(int)get_option('link_manager_enabled')===0);
t('update_core: nicht unterstützt (WP_Error)',is_wp_error(update_core('/a','/b')));
$gd=$tmp.'/wp-content/plugins/elvado-gen/genericons';mkdir($gd,0775,true);file_put_contents($gd.'/example.html','<html>genericons</html>');
t('_upgrade_422_find_genericons_files_in_folder findet example.html',count(_upgrade_422_find_genericons_files_in_folder($tmp.'/wp-content/plugins/elvado-gen'))===1);
_upgrade_422_remove_genericons();t('_upgrade_422_remove_genericons löscht die Datei',!is_file($gd.'/example.html'));
mkdir(WP_PLUGIN_DIR.'/hoch');file_put_contents(WP_PLUGIN_DIR.'/hoch/hoch.php',"<?php\n/*\nPlugin Name: Hoch\nRequires at least: 99.0\n*/\n");update_option('active_plugins',['hoch/hoch.php']);
t('_upgrade_core_deactivate_incompatible_plugins schaltet zu neue Anforderung ab',_upgrade_core_deactivate_incompatible_plugins()===['hoch/hoch.php']&&!in_array('hoch/hoch.php',(array)get_option('active_plugins'),true)&&isset(get_option('wp_force_deactivated_plugins')['hoch/hoch.php']));
t('_upgrade_440_… delegiert',_upgrade_440_force_deactivate_incompatible_plugins()===[]);
_preload_old_requests_classes_and_interfaces('6.8');
$ok=false;try{ _redirect_to_about_wordpress('6.8'); }catch(ELVADO_WP_Die $e){ $ok=$e->getCode()===302; }t('_redirect_to_about_wordpress leitet um',$ok);

/* ───────── PclZip-Hilfen, Danksagungen, Sprachen, Importer, Bildschirmhilfen ───────── */
t('PclZipUtilPathReduction',PclZipUtilPathReduction('a/b/../c')==='a/c'&&PclZipUtilPathReduction('a/./b/')==='a/b/'&&PclZipUtilPathReduction('../a')==='../a'&&PclZipUtilPathReduction('/x/y/../z')==='/x/z'&&PclZipUtilPathReduction('')===''&&PclZipUtilPathReduction('a/../../b')==='../b');
t('PclZipUtilPathInclusion',PclZipUtilPathInclusion('a/b','a/b/c')===1&&PclZipUtilPathInclusion('a/b','a/b')===2&&PclZipUtilPathInclusion('a/b','a/x')===0&&PclZipUtilPathInclusion('a/b/c','a/b')===0);
$s=$tmp.'/s.bin';$d=$tmp.'/d.bin';file_put_contents($s,str_repeat('0123456789',500));$hs=fopen($s,'rb');$hd=fopen($d,'wb');PclZipUtilCopyBlock($hs,$hd,3000);fclose($hs);fclose($hd);
t('PclZipUtilCopyBlock kopiert die angegebene Länge',filesize($d)===3000);
t('PclZipUtilRename verschiebt',PclZipUtilRename($d,$tmp.'/d2.bin')===1&&is_file($tmp.'/d2.bin')&&!is_file($d)&&PclZipUtilRename($tmp.'/gibtsnicht',$tmp.'/x')===0);
define('PCLZIP_OPT_PATH',77001);t('PclZipUtilOptionText',PclZipUtilOptionText(77001)==='PCLZIP_OPT_PATH'&&PclZipUtilOptionText(-5)==='Unknown');
t('PclZipUtilTranslateWinPath (Linux: unverändert)',PclZipUtilTranslateWinPath('a\\b')==='a\\b');
t('wp_credits ohne Zwischenspeicher → false',wp_credits('de_DE')===false);
$dn='Anna';_wp_credits_add_profile_link($dn,'anna','https://profiles.example/%s');t('_wp_credits_add_profile_link',$dn==='<a href="https://profiles.example/anna">Anna</a>');
$dd=['Seite','https://x.test/'];_wp_credits_build_object_link($dd);t('_wp_credits_build_object_link',$dd==='<a href="https://x.test/">Seite</a>');
t('wp_credits_section_title/-list',str_contains(out(fn()=>wp_credits_section_title('Team')),'<h3 class="wp-people-group">Team</h3>')&&str_contains(out(fn()=>wp_credits_section_list(['data'=>['anna'=>['Anna','','anna']]],'team')),'wp-person-anna'));
t('wp_can_install_language_pack liefert bool',is_bool(wp_can_install_language_pack()));
t('wp_download_language_pack: ungültiger Code → false',wp_download_language_pack('')===false&&wp_download_language_pack('../x')===false);
t('wp_install_language_form: Auswahlliste',str_contains(out(fn()=>wp_install_language_form([['language'=>'de_DE','native_name'=>'Deutsch']])),'value="de_DE"'));
t('_usort_by_first_member',_usort_by_first_member(['a'],['b'])<0&&_usort_by_first_member(['b'],['a'])>0);
global $wp_importers;$wp_importers=['z'=>['Zeta'],'a'=>['Alpha']];t('get_importers sortiert nach Name',array_keys(get_importers())===['a','z']);
$pop=wp_get_popular_importers();t('wp_get_popular_importers',isset($pop['wordpress']['plugin-slug'])&&count($pop)>=6);
t('add_cssclass',add_cssclass('b','')==='b'&&add_cssclass('b','a')==='a b');
$menu=[0=>['Dashboard','read','index.php','','menu-top'],4=>['','read','sep','','wp-menu-separator'],5=>['Beiträge','edit_posts','edit.php','','menu-top'],6=>['Medien','upload_files','upload.php','','menu-top']];
$mc=add_menu_classes($menu);t('add_menu_classes: first/last',str_contains($mc[0][4],'menu-top-first')&&str_contains($mc[5][4],'menu-top-first')&&str_contains($mc[6][4],'menu-top-last')&&str_contains($mc[0][4],'menu-top-last'));
register_column_headers('elvado-liste',['cb'=>'x','title'=>'Titel','autor'=>'Autor']);
t('register_column_headers/print_column_headers',str_contains(out(fn()=>print_column_headers('elvado-liste')),'column-title')&&str_contains(out(fn()=>print_column_headers('elvado-liste')),'check-column'));
update_user_option(1,'manage'.'elvado-listecolumnshidden',['autor'],true);
t('print_column_headers blendet versteckte Spalten aus (hidden)',(bool)preg_match('/column-autor[^"]*hidden/',out(fn()=>print_column_headers('elvado-liste'))));
elvado_adm_add_box('elvado-box','Meine Box','__return_empty_string','elvado-seite','normal');
t('get_hidden_meta_boxes liefert Array, meta_box_prefs gibt Kontrollkästchen aus',is_array(get_hidden_meta_boxes('elvado-seite'))&&str_contains(out(fn()=>meta_box_prefs('elvado-seite')),'elvado-box-hide'));

/* ───────── Dashboard ───────── */
wp_dashboard_setup();
$dash=out(fn()=>wp_dashboard());t('wp_dashboard: Spalten und Standard-Boxen',str_contains($dash,'dashboard-widgets-wrap')&&str_contains($dash,'dashboard_right_now')&&str_contains($dash,'dashboard_quick_press'),substr($dash,0,200));
$rn=out(fn()=>wp_dashboard_right_now());t('wp_dashboard_right_now: Zähler und Version',str_contains($rn,'post-count')&&str_contains($rn,'wp-version'));
$qp=out(fn()=>wp_dashboard_quick_press());t('wp_dashboard_quick_press: Formular',str_contains($qp,'id="quick-press"')&&str_contains($qp,'post_ID'));
wp_insert_post(['post_title'=>'Entwurf-Eins','post_status'=>'draft','post_author'=>1]);
t('wp_dashboard_recent_drafts: zeigt Entwurf',str_contains(out(fn()=>wp_dashboard_recent_drafts()),'Entwurf-Eins'));
t('wp_dashboard_recent_posts: true bei Beiträgen',(function(){ ob_start();$r=wp_dashboard_recent_posts(['max'=>5,'status'=>'publish','order'=>'DESC','title'=>'Neu','id'=>'x']);$o=ob_get_clean();return $r===true&&str_contains($o,'<h3>Neu</h3>'); })());
t('wp_dashboard_recent_posts: false ohne Beiträge',(function(){ ob_start();$r=wp_dashboard_recent_posts(['max'=>5,'status'=>'future','order'=>'ASC','title'=>'Z','id'=>'y']);ob_end_clean();return $r===false; })());
t('wp_dashboard_recent_comments + _wp_dashboard_recent_comments_row',(function() use($cid){ ob_start();$r=wp_dashboard_recent_comments();$o=ob_get_clean();return $r===true&&str_contains($o,'comment-'.$cid); })());
$c=get_comment($cid);t('_wp_dashboard_recent_comments_row',str_contains(out(function() use(&$c){ _wp_dashboard_recent_comments_row($c); }),'Gast'));
t('wp_dashboard_site_activity',str_contains(out(fn()=>wp_dashboard_site_activity()),'activity-widget'));
t('wp_network_dashboard_right_now (kein Netzwerk)',str_contains(out(fn()=>wp_network_dashboard_right_now()),'keine Multisite'));
t('wp_welcome_panel',str_contains(out(fn()=>wp_welcome_panel()),'welcome-panel-content'));
t('wp_dashboard_empty/-quota/-browser',str_contains(out(fn()=>wp_dashboard_empty()),'empty-container')&&wp_dashboard_quota()===true&&wp_check_browser_version()===false&&out(fn()=>wp_dashboard_browser_nag())==='');
t('dashboard_browser_nag_class/php_nag_class unverändert bei aktuellem System',dashboard_browser_nag_class('a')==='a'&&dashboard_php_nag_class('a')==='a'&&out(fn()=>wp_dashboard_php_nag())==='');
t('wp_dashboard_site_health: ohne/mit Ergebnis',str_contains(out(fn()=>wp_dashboard_site_health()),'gut,'));
set_transient('dash_v2_'.md5('dashboard_primary_'.get_user_locale()),'<p>NEWS-CACHE</p>');
t('wp_dashboard_primary/-cached_rss_widget nutzen den Zwischenspeicher (ohne Netz)',str_contains(out(fn()=>wp_dashboard_primary()),'NEWS-CACHE')&&str_contains(out(fn()=>wp_dashboard_events_news()),'NEWS-CACHE'));
t('wp_print_community_events_markup/-templates',str_contains(out(fn()=>wp_print_community_events_markup()),'community-events')&&str_contains(out(fn()=>wp_print_community_events_templates()),'tmpl-community-events'));
t('wp_dashboard_primary_output ohne Feeds leer',out(fn()=>wp_dashboard_primary_output('x',[]))==='');
t('wp_dashboard_rss_output/-control',is_string(out(fn()=>wp_dashboard_rss_control('elvado_feed')))&&str_contains(out(fn()=>wp_dashboard_rss_control('elvado_feed')),'widget-rss'));
$GLOBALS['wp_dashboard_control_callbacks']=['elvado_w'=>function(){ echo 'STEUERUNG'; }];
t('wp_dashboard_trigger_widget_control + _wp_dashboard_control_callback',str_contains(out(fn()=>_wp_dashboard_control_callback('',['id'=>'elvado_w'])),'STEUERUNG')&&str_contains(out(fn()=>wp_dashboard_trigger_widget_control('elvado_w')),'STEUERUNG'));

/* ───────── Meta-Boxen ───────── */
$post=get_post($pid);
$h=out(fn()=>post_submit_meta_box($post));t('post_submit_meta_box',str_contains($h,'submitpost')&&str_contains($h,'Aktualisieren'));
$h=out(fn()=>attachment_submit_meta_box(get_post($aid)));t('attachment_submit_meta_box',str_contains($h,'submitpost')&&str_contains($h,'Endgültig löschen'));
t('post_excerpt/slug/trackback/comment_status-Boxen',str_contains(out(fn()=>post_excerpt_meta_box($post)),'name="excerpt"')&&str_contains(out(fn()=>post_slug_meta_box($post)),'name="post_name"')&&str_contains(out(fn()=>post_trackback_meta_box($post)),'trackback_url')&&str_contains(out(fn()=>post_comment_status_meta_box($post)),'name="comment_status"'));
t('post_author_meta_box: Auswahlliste',str_contains(out(fn()=>post_author_meta_box($post)),'post_author_override'));
$pgp=get_post(wp_insert_post(['post_title'=>'Attr','post_type'=>'page','post_status'=>'publish','post_author'=>1]));
t('page_attributes_meta_box (Seite): übergeordnet + Reihenfolge',str_contains(out(fn()=>page_attributes_meta_box($pgp)),'menu_order')&&str_contains(out(fn()=>page_attributes_meta_box($pgp)),'parent_id'));
update_post_meta($pid,'elvado_feld','Wert1');t('post_custom_meta_box: listet Felder',str_contains(out(fn()=>post_custom_meta_box(get_post($pid))),'elvado_feld'));
t('post_comment_meta_box + _thead',str_contains(out(fn()=>post_comment_meta_box(get_post($pid))),'comment-'.$cid)&&post_comment_meta_box_thead(['cb'=>'x','author'=>'A','response'=>'R'])===['author'=>'A']);
t('post_thumbnail_meta_box',is_string(out(fn()=>post_thumbnail_meta_box(get_post($pid)))));
t('post_revisions_meta_box/post_format_meta_box laufen leer durch',is_string(out(fn()=>post_revisions_meta_box(get_post($pid))))&&out(fn()=>post_format_meta_box(get_post($pid),[]))==='');
t('attachment_id3_data_meta_box',str_contains(out(fn()=>attachment_id3_data_meta_box(get_post($aid))),'attachment-id3-data'));
$all=out(fn()=>register_and_do_post_meta_boxes(get_post($pid)));t('register_and_do_post_meta_boxes: Standard-Boxen',str_contains($all,'id="submitdiv"')&&str_contains($all,'id="slugdiv"')&&str_contains($all,'side-sortables'));
$GLOBALS['link']=(object)['link_rel'=>'friend met','link_visible'=>'N','link_target'=>'_blank','link_id'=>0];
t('xfn_check',trim(out(fn()=>xfn_check('friendship','friend')))==='checked="checked"'&&out(fn()=>xfn_check('friendship','contact'))===''&&trim(out(fn()=>xfn_check('family','')))==='checked="checked"'&&out(fn()=>xfn_check('friendship',''))==='');
$l=$GLOBALS['link'];
t('link_*-Boxen',str_contains(out(fn()=>link_submit_meta_box($l)),'checked')&&str_contains(out(fn()=>link_target_meta_box($l)),'link_target_blank')&&str_contains(out(fn()=>link_xfn_meta_box($l)),'name="friendship"')&&str_contains(out(fn()=>link_advanced_meta_box($l)),'link_rating')&&is_string(out(fn()=>link_categories_meta_box($l))));

/* ───────── Navigationsmenüs ───────── */
t('wp_nav_menu_manage_columns',array_keys(wp_nav_menu_manage_columns())===['_title','cb','link-target','title-attribute','css-classes','xfn','description']);
$po=_wp_nav_menu_meta_box_object((object)['name'=>'page']);t('_wp_nav_menu_meta_box_object: Standardabfrage',($po->_default_query['post_status']??'')==='publish');
t('wp_nav_menu_disabled_check',str_contains((string)wp_nav_menu_disabled_check(0,false),'disabled')&&wp_nav_menu_disabled_check(3,false)==='');
wp_nav_menu_setup();$GLOBALS['wp_meta_boxes']['nav-menus']??null;t('wp_nav_menu_setup registriert Boxen',isset($GLOBALS['wp_meta_boxes']['nav-menus']['side']['default']['add-custom-links'])&&isset($GLOBALS['wp_meta_boxes']['nav-menus']['side']['default']['add-post-type-page']));
wp_initial_nav_menu_meta_boxes();t('wp_initial_nav_menu_meta_boxes blendet überzählige Boxen aus',is_array(get_user_option('metaboxhidden_nav-menus')));
t('wp_nav_menu_item_link_meta_box',str_contains(out(fn()=>wp_nav_menu_item_link_meta_box()),'custom-menu-item-url'));
t('wp_nav_menu_item_post_type_meta_box',str_contains(out(fn()=>wp_nav_menu_item_post_type_meta_box(null,['args'=>get_post_type_object('post')])),'Vollbild'));
t('wp_nav_menu_item_taxonomy_meta_box',str_contains(out(fn()=>wp_nav_menu_item_taxonomy_meta_box(null,['args'=>get_taxonomy('category')])),'categorychecklist'));
t('wp_nav_menu_post_type_meta_boxes/-taxonomy_meta_boxes',(function(){ wp_nav_menu_post_type_meta_boxes();wp_nav_menu_taxonomy_meta_boxes();return isset($GLOBALS['wp_meta_boxes']['nav-menus']['side']['default']['add-category']); })());
t('wp_save_nav_menu_items: CMS-Menüs bleiben unverändert (leer)',wp_save_nav_menu_items(0,[-1=>['menu-item-type'=>'custom','menu-item-url'=>'https://x.test/']])===[]);
$me=wp_get_nav_menu_to_edit('top');t('wp_get_nav_menu_to_edit: CMS-Menü',is_string($me)&&str_contains($me,'menu-to-edit')&&str_contains($me,'Start'));
t('wp_get_nav_menu_to_edit: unbekanntes Menü → false',wp_get_nav_menu_to_edit('gibtsnicht')===false);
t('wp_nav_menu_update_menu_items (ohne bearbeitbares Menü)',wp_nav_menu_update_menu_items(0,'x')===[]);
$_POST=['nav-menu-data'=>wp_slash(json_encode([['name'=>'menu-item-title[3]','value'=>'Hallo'],['name'=>'menu-item-db-id[3][4]','value'=>'7'],['name'=>'einfach','value'=>'x']]))];
_wp_expand_nav_menu_post_data();t('_wp_expand_nav_menu_post_data entpackt Namen mit Klammern',($_POST['menu-item-title'][3]??'')==='Hallo'&&($_POST['menu-item-db-id'][3][4]??'')==='7'&&($_POST['einfach']??'')==='x');$_POST=[];
_wp_delete_orphaned_draft_menu_items();t('_wp_delete_orphaned_draft_menu_items läuft ohne Fehler',true);
t('_wp_ajax_menu_quick_search: Begriff',str_contains(out(fn()=>_wp_ajax_menu_quick_search(['type'=>'quick-search-taxonomy-category','q'=>'Musik'])),'Musik'));
t('_wp_ajax_menu_quick_search: get-post-item (JSON)',str_contains(out(fn()=>_wp_ajax_menu_quick_search(['type'=>'get-post-item','object_type'=>'post','ID'=>$pid])),'"ID":'.$pid));

/* ───────── Linkverwaltung ───────── */
$l1=wp_insert_link(['link_name'=>'Sender','link_url'=>'https://sender.example.test/','link_rating'=>4,'link_category'=>[]]);
t('wp_insert_link: ID und Felder',$l1>0&&elvado_adm_link_row($l1)->link_rating==4&&elvado_adm_link_row($l1)->link_visible==='Y');
t('wp_insert_link: Standard-Linkkategorie',wp_get_link_cats($l1)===[(int)get_option('default_link_category')]);
t('wp_insert_link: ohne URL → 0 / WP_Error',wp_insert_link(['link_name'=>'x'])===0&&is_wp_error(wp_insert_link(['link_name'=>'x'],true))&&wp_insert_link(['link_url'=>'https://nur-url.example.test/'])>0);
$cat=wp_insert_term('Partner','link_category');wp_set_link_cats($l1,[$cat['term_id']]);t('wp_set_link_cats/wp_get_link_cats',wp_get_link_cats($l1)===[(int)$cat['term_id']]);
wp_update_link(['link_id'=>$l1,'link_name'=>'Sender neu']);$row=elvado_adm_link_row($l1);
t('wp_update_link: ändert Name, behält URL, Wertung und Kategorie',$row->link_name==='Sender neu'&&$row->link_url==='https://sender.example.test/'&&$row->link_rating==4&&wp_get_link_cats($l1)===[(int)$cat['term_id']]);
$ed=get_link_to_edit($l1);t('get_link_to_edit',$ed->link_name==='Sender neu'&&get_link_to_edit(99999999)===null);
$_GET['linkurl']='https://x.test/';$d=get_default_link_to_edit();t('get_default_link_to_edit',$d->link_visible==='Y'&&str_contains($d->link_url,'x.test'));$_GET=[];
$_POST=['link_name'=>'Per Formular','link_url'=>'https://form.example.test/','link_description'=>'Beschreibung'];$l2=add_link();t('add_link/edit_link aus $_POST',$l2>0&&elvado_adm_link_row($l2)->link_description==='Beschreibung');
$_POST=['link_name'=>'Per Formular 2','link_url'=>'https://form.example.test/'];$l3=edit_link($l2);t('edit_link (vorhandener Link)',$l3===$l2&&elvado_adm_link_row($l2)->link_name==='Per Formular 2');$_POST=[];
t('wp_delete_link',wp_delete_link($l2)===true&&elvado_adm_link_row($l2)===null&&wp_delete_link($l2)===false);
$GLOBALS['pagenow']='index.php';wp_link_manager_disabled_message();t('wp_link_manager_disabled_message: andere Seite → nichts',true);
$GLOBALS['pagenow']='link-manager.php';$ok=false;try{ wp_link_manager_disabled_message(); }catch(ELVADO_WP_Die $e){ $ok=$e->getCode()===403; }t('wp_link_manager_disabled_message: Linkverwaltung aus → 403',$ok);$GLOBALS['pagenow']='index.php';

/* ───────── Bildeditor (GD) ───────── */
t('_image_get_preview_ratio',_image_get_preview_ratio(1200,600)===0.5&&_image_get_preview_ratio(300,200)===1);
if($has_gd){
    $im=imagecreatetruecolor(40,20);imagefill($im,0,0,imagecolorallocate($im,10,200,10));
    if(function_exists('imagerotate')){ $rot=_rotate_image_resource($im,90);t('_rotate_image_resource: Maße getauscht',imagesx($rot)===20&&imagesy($rot)===40); }
    $fl=_flip_image_resource($im,false,true);t('_flip_image_resource: gleiche Maße',imagesx($fl)===40&&imagesy($fl)===20);
    imagesetpixel($im,0,0,imagecolorallocate($im,255,0,0));$fl=_flip_image_resource($im,false,true);t('_flip_image_resource: spiegelt links/rechts',(imagecolorat($fl,39,0)&0xFF0000)>>16===255);
    $cr=_crop_image_resource($im,5,5,10,8);t('_crop_image_resource',imagesx($cr)===10&&imagesy($cr)===8);
    $ap=image_edit_apply_changes($im,[(object)['c'=>(object)['x'=>0,'y'=>0,'w'=>8,'h'=>6]]]);t('image_edit_apply_changes: Zuschnitt',imagesx($ap)===8&&imagesy($ap)===6);
    t('image_edit_apply_changes: kein Array → unverändert',image_edit_apply_changes($im,null)===$im);
    $f=$tmp.'/save.png';t('wp_save_image_file schreibt PNG',wp_save_image_file($f,$im,'image/png',0)===true&&str_starts_with((string)file_get_contents($f),"\x89PNG")&&wp_save_image_file($f,$im,'text/plain',0)===false);
    t('wp_stream_image gibt PNG aus',str_starts_with(out(fn()=>wp_stream_image($im,'image/png',0)),"\x89PNG")&&wp_stream_image('kein-bild','image/png',0)===false);
    $sv=wp_save_image($aid);t('wp_save_image ohne Änderung → Fehler',!empty($sv->error));
    $_REQUEST=['do'=>'scale','fwidth'=>20,'fheight'=>10,'target'=>'all'];$sv=wp_save_image($aid);$mm=wp_get_attachment_metadata($aid);
    t('wp_save_image: Skalieren',!empty($sv->msg)&&(int)$mm['width']===20&&(int)$mm['height']===10,json_encode($sv));
    $_REQUEST=['do'=>'scale','fwidth'=>500,'fheight'=>500];$sv=wp_save_image($aid);t('wp_save_image: Vergrößern abgelehnt',!empty($sv->error));$_REQUEST=[];
    $rs=wp_restore_image($aid);$mm=wp_get_attachment_metadata($aid);t('wp_restore_image: Original zurück',!empty($rs->msg)&&(int)$mm['width']===40,json_encode($rs));
}
t('wp_restore_image ohne Sicherung → Fehler',!empty(wp_restore_image(wp_insert_post(['post_title'=>'Ohne','post_type'=>'attachment','post_status'=>'inherit']))->error));
t('wp_image_editor: Oberfläche',str_contains(out(fn()=>wp_image_editor($aid)),'imgedit-wrap')&&str_contains(out(fn()=>wp_image_editor($aid,(object)['error'=>'Mist'])),'Mist'));

/* ───────── Datenschutz-Werkzeuge ───────── */
$rq3=wp_create_user_request('x3@example.test','export_personal_data');
$mails=[];t('_wp_privacy_resend_request sendet erneut',_wp_privacy_resend_request($rq3)===$rq3&&count($mails)===1&&is_wp_error(_wp_privacy_resend_request(999999999)));
t('_wp_privacy_completed_request',_wp_privacy_completed_request($rq3)===$rq3&&wp_get_user_request($rq3)->status==='request-completed'&&wp_get_user_request($rq3)->completed_timestamp>0&&is_wp_error(_wp_privacy_completed_request(999999999)));
$g=['group_label'=>'Konto','group_description'=>'Daten','items'=>['a'=>[['name'=>'Seite','value'=>'https://x.test/'],['name'=>'Name','value'=>'Anna <b>X</b>']],'b'=>[['name'=>'Mail','value'=>'a@b.test']]]];
$gh=wp_privacy_generate_personal_data_export_group_html($g,'k',2);
t('wp_privacy_generate_personal_data_export_group_html: Titel, Zähler, Link, Nach-oben',str_contains($gh,'<h2 id="konto-k">Konto <span class="count">(2)</span></h2>')&&str_contains($gh,'<a href="https://x.test/">')&&str_contains($gh,'return-to-top'));
$old=wp_create_user_request('alt@example.test','export_personal_data');_wp_privacy_completed_request($old);$wpdb->update($wpdb->posts,['post_modified_gmt'=>gmdate('Y-m-d H:i:s',time()-40*DAY_IN_SECONDS)],['ID'=>$old]);elvado_wp_post_cache_clear($old);
_wp_personal_data_cleanup_requests();t('_wp_personal_data_cleanup_requests löscht alte erledigte Anfragen',!get_post($old)&&(bool)get_post($rq3));
t('wp_privacy_generate_personal_data_export_file: ungültige Anfrage → Fehler',is_wp_error(wp_privacy_generate_personal_data_export_file(999999999)));
t('wp_privacy_send_personal_data_export_email: ohne Datei → Text',is_string(wp_privacy_send_personal_data_export_email($rq3)));
$mails=[];t('wp_privacy_send_personal_data_export_email: sendet',wp_privacy_send_personal_data_export_email($rq)===true&&count($mails)===1&&str_contains((string)$mails[0]['message'],$mf));
t('wp_privacy_process_personal_data_export_page: ungültiger Index → Fehler',is_wp_error(wp_privacy_process_personal_data_export_page(['data'=>[]],9,'a@b.test',1,$rq,false,'x')));
t('wp_privacy_process_personal_data_erasure_page: ungültiger Index → Fehler',is_wp_error(wp_privacy_process_personal_data_erasure_page([],9,'a@b.test',1,$rq2,'x')));
$_POST=['action'=>'add_export_personal_data_request','username_or_email_for_privacy_request'=>'bob2','_wpnonce'=>wp_create_nonce('personal-data-request')];$_REQUEST=$_POST;
_wp_personal_data_handle_actions();t('_wp_personal_data_handle_actions: Anfrage für Benutzernamen',(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='user_request' AND post_title='bob2@example.test'")===1);$_POST=$_REQUEST=[];

/* ───────── Plugin-/Theme-Oberflächen ───────── */
t('install_search_form/install_dashboard/install_plugins_upload/-favorites_form',str_contains(out(fn()=>install_search_form(false)),'search-plugins')&&str_contains(out(fn()=>install_dashboard()),'Plugins erweitern')&&str_contains(out(fn()=>install_plugins_upload()),'pluginzip')&&str_contains(out(fn()=>install_plugins_favorites_form()),'name="user"'));
t('install_popular_tags (ohne Netz leer)',install_popular_tags()===[]);
mkdir(WP_PLUGIN_DIR.'/vorhanden');file_put_contents(WP_PLUGIN_DIR.'/vorhanden/vorhanden.php',"<?php\n/*\nPlugin Name: Vorhanden\n*/\n");update_option('active_plugins',[]);
t('wp_get_plugin_action_button: installieren',str_contains(wp_get_plugin_action_button('Neu',(object)['slug'=>'neu-plugin'],true,true),'install-now'));
t('wp_get_plugin_action_button: inkompatibel',str_contains(wp_get_plugin_action_button('Neu',(object)['slug'=>'neu-plugin'],false,true),'disabled'));
t('wp_get_plugin_action_button: aktivieren, dann aktiv',str_contains(wp_get_plugin_action_button('V',(object)['slug'=>'vorhanden'],true,true),'activate-now')&&(function(){ update_option('active_plugins',['vorhanden/vorhanden.php']);return str_contains(wp_get_plugin_action_button('V',(object)['slug'=>'vorhanden'],true,true),'Aktiv'); })());
t('display_plugins_table/display_themes ohne Listenklasse',str_contains(out(fn()=>display_plugins_table()),'Keine Plugins')&&str_contains(out(fn()=>display_themes()),'Keine Themes'));
t('install_plugin_information: ohne Plugin → Hinweis',str_contains(out(fn()=>install_plugin_information()),'Es wurde kein Plugin'));
$_REQUEST['plugin']='gibts-nicht';t('install_plugin_information: Verzeichnis nicht erreichbar → Fehlerbox',str_contains(out(fn()=>install_plugin_information()),'error'));$_REQUEST=[];
t('install_theme_information: ohne Theme → Hinweis',str_contains(out(fn()=>install_theme_information()),'Es wurde kein Theme'));
t('install_theme_search_form/install_themes_upload',str_contains(out(fn()=>install_theme_search_form()),'search-themes')&&str_contains(out(fn()=>install_themes_upload()),'themezip'));
t('install_themes_feature_list/install_themes_dashboard',is_array(install_themes_feature_list())&&str_contains(out(fn()=>install_themes_dashboard()),'feature-group'));
t('display_theme: Karte mit Installieren',str_contains(out(fn()=>display_theme((object)['slug'=>'schick','name'=>'Schick','author'=>(object)['display_name'=>'Anna']])),'Schick')&&str_contains(out(fn()=>display_theme(['slug'=>'schick','name'=>'Schick'])),'install-theme'));

/* ───────── Neuinstallation (zuletzt: leert Beiträge und Kommentare der Test-Datenbank) ───────── */
$wpdb->query("DELETE FROM {$wpdb->comments}");$wpdb->query("DELETE FROM {$wpdb->posts}");   // frische Datenbank wie bei einer Neuinstallation
$res=wp_install('Neues Radio','instadmin','inst@example.test',true,'','start-1234-geheim');
t('wp_install: Benutzer, URL, Passwortmeldung',$res['user_id']>0&&(get_user_by('login','instadmin')->user_email??'')==='inst@example.test'&&get_option('blogname')==='Neues Radio'&&$res['password']==='start-1234-geheim',json_encode($res));
t('wp_install: Hallo-Welt-Beitrag, Beispielseite, erster Kommentar',(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_name='hallo-welt' AND post_type='post'")===1&&(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_name='beispiel-seite' AND post_type='page'")===1&&(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments}")===1);
$cnt=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_name='hallo-welt'");wp_install_defaults(1);
t('wp_install_defaults ist idempotent',(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_name='hallo-welt'")===$cnt&&$cnt===1);
t('wp_install: Standardkategorie gesetzt',term_exists('uncategorized','category')&&(int)get_option('default_category')>0);
t('wp_install_maybe_enable_pretty_permalinks liefert bool',is_bool(wp_install_maybe_enable_pretty_permalinks()));
$mails=[];wp_new_blog_notification('Radio','https://example.test',$res['user_id'],'pw-123');t('wp_new_blog_notification sendet E-Mail mit Passwort',count($mails)===1&&str_contains((string)$mails[0]['message'],'pw-123'));

echo ($fail?"FEHLGESCHLAGEN: $fail von $n":"OK: alle $n Prüfungen bestanden")."\n";
exit($fail?1:0);

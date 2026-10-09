<?php
// Prüft die ergänzenden Inhalts-Funktionen (cms/wp/core/ext/content-*.php): Beiträge, Begriffe, Meta, Optionen, Revisionen, Cron, Kategorien, Formate.
// Aufruf: php scripts/test-wp-ext-content.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/elvado-wpxc-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/cms');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('ELVADO_WP_DATA',$tmp.'/cms/.wp');define('ELVADO_WP_CMS_DATA',$tmp.'/cms');$_SERVER['HTTP_HOST']='example.test';
file_put_contents($tmp.'/cms/news.json',json_encode([
 ['id'=>1,'slug'=>'erster','title'=>'Erster Beitrag','category'=>'News','tags'=>'radio','excerpt'=>'Kurz','body_html'=>'<p>Hallo</p>','status'=>'published','published_at'=>'2026-01-10 10:00:00','author'=>'Anna Autor'],
 ['id'=>2,'slug'=>'zweiter','title'=>'Zweiter','category'=>'Events','tags'=>'','excerpt'=>'','body_html'=>'<p>Konzert</p>','status'=>'published','published_at'=>'2026-02-10 10:00:00','author'=>'Ben Bauer'],
]));
file_put_contents($tmp.'/cms/site.json',json_encode(['pages'=>[]]));
require __DIR__.'/_testdb.php';
require __DIR__.'/../cms/wp/load.php';
$fail=0;$n=0;
function t(string $name,bool $ok): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name\n";} }
elvado_wp_boot(['user'=>['id'=>1,'login'=>'admin','name'=>'Admin','email'=>'a@example.test','role'=>'administrator']]);

/* Alle Funktionen der Liste müssen existieren (bewusste Ausnahmen: keine) */
$names=preg_split('/\s+/',trim(<<<'NAMES'
create_initial_post_types update_attached_file _wp_relative_upload_path get_page_statuses _wp_privacy_statuses get_post_type_capabilities
_post_type_meta_capabilities _get_custom_object_labels _add_post_type_submenus set_post_type is_post_publicly_viewable is_post_embeddable
unregister_post_meta _count_posts_cache_key wp_count_attachments get_post_mime_types wp_match_mime_types wp_post_mime_type_where
_reset_front_page_settings_for_post wp_trash_post_comments wp_untrash_post_comments wp_get_recent_posts check_and_publish_future_post
wp_resolve_post_date _truncate_post_slug wp_add_post_tags wp_after_insert_post add_ping get_enclosed get_pung get_to_ping trackback_url_list
get_all_page_ids get_page_children get_page_hierarchy _page_traverse_name is_local_attachment wp_delete_attachment_files wp_mime_type_icon
wp_check_for_changed_slugs wp_check_for_changed_dates get_private_posts_cap_sql get_posts_by_author_sql get_lastpostdate get_lastpostmodified
_get_last_post_time update_post_cache update_post_author_caches update_post_parent_caches clean_attachment_cache _transition_post_status
_future_post_hook _publish_post_hook wp_check_post_hierarchy_for_loops wp_delete_auto_drafts wp_queue_posts_for_term_meta_lazyload
_update_term_count_on_transition_post_status _prime_post_parent_id_caches wp_add_trashed_suffix_to_post_name_for_trashed_posts
wp_add_trashed_suffix_to_post_name_for_post wp_cache_set_posts_last_changed get_available_post_mime_types wp_get_original_image_path
wp_get_original_image_url wp_untrash_post_set_previous_status wp_create_initial_post_meta create_initial_taxonomies get_tax_sql get_term_to_edit
wp_lazyload_term_meta has_term_meta unregister_term_meta term_is_ancestor_of sanitize_term sanitize_term_field wp_delete_object_term_relationships
wp_delete_category wp_add_object_terms wp_unique_term_slug clean_taxonomy_cache get_object_term_cache update_object_term_cache update_term_cache
_get_term_children _pad_term_counts _prime_term_caches _update_post_term_count _update_generic_term_count _split_shared_term _wp_batch_split_terms
_wp_check_for_scheduled_split_terms _wp_check_split_default_terms _wp_check_split_terms_in_menus _wp_check_split_nav_menu_terms wp_get_split_terms
wp_term_is_shared the_taxonomies get_post_taxonomies is_object_in_term get_ancestors wp_get_term_taxonomy_parent_id wp_check_term_hierarchy_for_loops
is_term_publicly_viewable wp_cache_set_terms_last_changed wp_check_term_meta_support_prefilter _wp_translate_postdata _wp_get_allowed_postdata
edit_post bulk_edit_posts get_default_post_to_edit wp_write_post write_post add_meta delete_meta get_meta_keys get_post_meta_by_id has_meta
update_meta _fix_attachment_links get_available_post_statuses wp_edit_posts_query wp_edit_attachments_query_vars wp_edit_attachments_query
postbox_classes get_sample_permalink_html _wp_post_thumbnail_html _admin_notice_post_locked wp_autosave_post_revisioned_meta_fields post_preview
wp_autosave redirect_post taxonomy_meta_box_sanitize_cb_checkboxes taxonomy_meta_box_sanitize_cb_input get_block_editor_server_block_settings
the_block_editor_meta_boxes the_block_editor_meta_box_post_form_hidden_fields _disable_block_editor_for_navigation_post_type
_disable_content_editor_for_navigation_post_type _enable_content_editor_for_navigation_post_type wp_prime_option_caches_by_group get_options
wp_set_option_autoload_values wp_set_options_autoload wp_set_option_autoload wp_protect_special_option form_option wp_prime_site_option_caches
wp_prime_network_option_caches wp_load_core_site_options wp_determine_option_autoload_value wp_filter_default_autoload_value_via_option_size
delete_expired_transients wp_user_settings delete_user_setting get_all_user_settings wp_set_all_user_settings delete_all_user_settings
register_initial_settings filter_default_option wp_autoload_values_to_autoload _wp_post_revision_fields _wp_post_revision_data
wp_save_post_revision_on_insert _wp_put_post_revision wp_save_revisioned_meta_fields wp_get_post_revision wp_restore_post_revision
wp_restore_post_revision_meta _wp_copy_post_meta wp_post_revision_meta_keys wp_check_revisioned_meta_fields_have_changed
wp_get_latest_revision_id_and_total_count wp_get_post_revisions_url wp_revisions_to_keep _set_preview _show_post_preview _wp_preview_terms_filter
_wp_preview_post_thumbnail_filter _wp_get_post_revision_version _wp_upgrade_revisions_of_post _wp_preview_meta_filter the_guid get_the_guid
_wp_link_page post_custom the_meta wp_dropdown_pages walk_page_tree walk_page_dropdown_tree the_attachment_link wp_post_revision_title
wp_post_revision_title_expanded wp_list_post_revisions get_category_to_edit wp_create_category wp_create_categories wp_insert_category
wp_update_category tag_exists wp_create_tag get_tags_to_edit get_terms_to_edit wp_create_term get_metadata_default wp_metadata_lazyloader get_meta_sql
sanitize_meta filter_default_metadata registered_meta_key_exists get_registered_meta_keys get_registered_metadata _wp_register_meta_args_allowed_list
get_object_subtype has_post_format get_post_format_strings get_post_format_slugs get_post_format_string get_post_format_link _post_format_request
_post_format_link _post_format_get_term _post_format_get_terms _post_format_wp_get_object_terms in_category category_description
default_topic_count_scale wp_generate_tag_cloud _wp_object_name_sort_cb _wp_object_count_sort_cb walk_category_tree walk_category_dropdown_tree
tag_description current_user_can_for_site user_can_for_site grant_super_admin revoke_super_admin wp_maybe_grant_install_languages_cap
wp_maybe_grant_resume_extensions_caps wp_maybe_grant_site_health_caps get_category_by_path cat_is_ancestor_of sanitize_category
sanitize_category_field clean_category_cache _make_cat_compat is_comment_feed is_favicon the_comment _find_post_by_old_slug _find_post_by_old_date
generate_postdata redirect_canonical _remove_qs_args_if_not_in_url strip_fragment_from_url redirect_guess_404_permalink wp_redirect_admin_locations
wp_reschedule_event _wp_cron _get_cron_array _set_cron_array _upgrade_cron_array remove_rewrite_tag remove_permastruct add_feed
_wp_filter_taxonomy_base wp_resolve_numeric_slug_conflicts wp_get_revision_ui_diff wp_prepare_revisions_for_js wp_print_revision_templates
the_post_thumbnail_caption
NAMES));
$missing=array_values(array_filter($names,fn($f)=>!function_exists($f)));
t('alle '.count($names).' Funktionen vorhanden (fehlend: '.implode(',',$missing).')',count($names)===270&&!$missing);

/* ───────── Beiträge, Typen, Anhänge ───────── */
$A=wp_insert_post(['post_title'=>'Alpha','post_content'=>'A','post_status'=>'publish','post_name'=>'alpha']);
$B=wp_insert_post(['post_title'=>'Seite Eltern','post_content'=>'E','post_status'=>'publish','post_type'=>'page']);
$C=wp_insert_post(['post_title'=>'Seite Kind','post_content'=>'K','post_status'=>'publish','post_type'=>'page','post_parent'=>$B]);
$D=wp_insert_post(['post_title'=>'Seite Enkel','post_content'=>'G','post_status'=>'publish','post_type'=>'page','post_parent'=>$C]);
t('Testdaten angelegt',$A>0&&$B>0&&$C>0&&$D>0);
t('get_page_statuses/_wp_privacy_statuses',array_keys(get_page_statuses())===['draft','private','publish']&&isset(_wp_privacy_statuses()['request-pending']));
$caps=get_post_type_capabilities((object)['capability_type'=>'book','map_meta_cap'=>true]);
t('get_post_type_capabilities',$caps->edit_posts==='edit_books'&&$caps->create_posts==='edit_books'&&$caps->delete_private_posts==='delete_private_books'&&$caps->read==='read');
t('get_post_type_capabilities eigene Rechte',get_post_type_capabilities((object)['capability_type'=>['story','stories'],'capabilities'=>['publish_posts'=>'x']])->edit_others_posts==='edit_others_stories'&&get_post_type_capabilities((object)['capability_type'=>'post','capabilities'=>['publish_posts'=>'x']])->publish_posts==='x');
_post_type_meta_capabilities(['edit_post'=>'edit_book','foo'=>'bar']);
t('_post_type_meta_capabilities',($GLOBALS['post_type_meta_caps']['edit_book']??'')==='edit_post'&&!isset($GLOBALS['post_type_meta_caps']['bar']));
$ob=(object)['labels'=>['name'=>'Bücher'],'hierarchical'=>false];$lb=_get_custom_object_labels($ob,['add_new_item'=>['Neues Buch','Neue Seite'],'name_admin_bar'=>'x']);
t('_get_custom_object_labels',$lb->name==='Bücher'&&$lb->singular_name==='Bücher'&&$lb->add_new_item==='Neues Buch'&&$ob->label==='Bücher');
t('is_post_publicly_viewable',is_post_publicly_viewable($A)&&!is_post_publicly_viewable(wp_insert_post(['post_title'=>'Entwurf','post_status'=>'draft'])));
t('is_post_embeddable',is_post_embeddable($A)===true&&is_post_embeddable(0)===false);
t('set_post_type',set_post_type($A,'Book')===1&&get_post_type($A)==='book'&&set_post_type($A,'post')===1&&get_post_type($A)==='post'&&set_post_type(1,'x')===0);
t('_count_posts_cache_key',_count_posts_cache_key('post')==='posts-post'&&str_starts_with(_count_posts_cache_key('page','readable'),'posts-page'));
t('create_initial_post_types/taxonomies',(function(){ create_initial_post_types();create_initial_taxonomies();return post_type_exists('post')&&taxonomy_exists('category'); })());

t('wp_match_mime_types',wp_match_mime_types('image/*,video','image/png,image/jpeg,video/mp4,audio/mp3')===['image/*'=>['image/png','image/jpeg'],'video'=>['video/mp4']]&&wp_match_mime_types('image/png','image/jpeg')===[]);
t('wp_post_mime_type_where',str_contains(wp_post_mime_type_where('image,application/pdf'),"post_mime_type LIKE 'image/%'")&&str_contains(wp_post_mime_type_where('application/pdf','p'),"p.post_mime_type LIKE 'application/pdf'")&&wp_post_mime_type_where('*')==='');
t('get_post_mime_types',array_keys(get_post_mime_types())===['image','audio','video','document','spreadsheet','archive']);
$img=wp_insert_post(['post_title'=>'Bild','post_type'=>'attachment','post_status'=>'inherit','post_mime_type'=>'image/jpeg','post_parent'=>$A]);
$pdf=wp_insert_post(['post_title'=>'Papier','post_type'=>'attachment','post_status'=>'inherit','post_mime_type'=>'application/pdf']);
$ca=wp_count_attachments();t('wp_count_attachments',$ca->{'image/jpeg'}===1&&$ca->{'application/pdf'}===1&&$ca->trash===0&&!isset(wp_count_attachments('image')->{'application/pdf'}));
t('get_available_post_mime_types',get_available_post_mime_types()===['image/jpeg','application/pdf']||array_diff(get_available_post_mime_types(),['image/jpeg','application/pdf'])===[]);
$up=wp_get_upload_dir();
t('_wp_relative_upload_path',_wp_relative_upload_path($up['basedir'].'/2026/04/a.jpg')==='2026/04/a.jpg'&&_wp_relative_upload_path('/fremd/a.jpg')==='/fremd/a.jpg');
t('update_attached_file',update_attached_file($img,$up['basedir'].'/2026/04/a.jpg')&&get_post_meta($img,'_wp_attached_file',true)==='2026/04/a.jpg'&&update_attached_file(999999999,'x')===false);
wp_update_attachment_metadata($img,['width'=>10,'height'=>10,'original_image'=>'orig.jpg','sizes'=>['thumbnail'=>['file'=>'a-150.jpg']]]);
t('wp_get_original_image_path/url',str_ends_with((string)wp_get_original_image_path($img),'2026/04/orig.jpg')&&str_ends_with((string)wp_get_original_image_url($img),'/2026/04/orig.jpg')&&wp_get_original_image_path($pdf)===false);
mkdir($up['basedir'].'/2026/04',0775,true);foreach(['a.jpg','orig.jpg','a-150.jpg'] as $f)file_put_contents($up['basedir'].'/2026/04/'.$f,'x');
t('wp_delete_attachment_files',wp_delete_attachment_files($img,wp_get_attachment_metadata($img),[],'2026/04/a.jpg')&&!is_file($up['basedir'].'/2026/04/a.jpg')&&!is_file($up['basedir'].'/2026/04/orig.jpg')&&!is_file($up['basedir'].'/2026/04/a-150.jpg'));
clean_attachment_cache($img);t('clean_attachment_cache läuft',true);
t('wp_mime_type_icon Standard false',wp_mime_type_icon('image/png')===false);

/* Startseite, Kommentare */
update_option('show_on_front','page');update_option('page_on_front',$B);stick_post($B);
_reset_front_page_settings_for_post($B);
t('_reset_front_page_settings_for_post',get_option('show_on_front')==='posts'&&(int)get_option('page_on_front')===0&&!is_sticky($B));
$cm=wp_insert_comment(['comment_post_ID'=>$A,'comment_content'=>'Hallo','comment_author'=>'X','comment_approved'=>1]);
$cm2=wp_insert_comment(['comment_post_ID'=>$A,'comment_content'=>'Wartet','comment_author'=>'Y','comment_approved'=>0]);
global $wpdb;$ap=fn($i)=>$wpdb->get_var("SELECT comment_approved FROM {$wpdb->comments} WHERE comment_ID = ".(int)$i);
t('wp_trash_post_comments',wp_trash_post_comments($A)===true&&$ap($cm)==='post-trashed'&&$ap($cm2)==='post-trashed');
wp_untrash_post_comments($A);
t('wp_untrash_post_comments stellt Status wieder her',$ap($cm)==='1'&&$ap($cm2)==='0'&&get_post_meta($A,'_wp_trash_meta_comments_status',true)==='');

/* Abfragen, Status, Slugs */
$rp=wp_get_recent_posts(['numberposts'=>2]);t('wp_get_recent_posts',count($rp)===2&&isset($rp[0]['post_title'])&&is_object(wp_get_recent_posts(['numberposts'=>1],OBJECT)[0]));
$F=wp_insert_post(['post_title'=>'Später','post_status'=>'future','post_date'=>'2020-01-01 10:00:00','post_date_gmt'=>'2020-01-01 09:00:00']);
check_and_publish_future_post($F);t('check_and_publish_future_post (fällig)',get_post_status($F)==='publish');
$G=wp_insert_post(['post_title'=>'Weit später','post_status'=>'future','post_date'=>'2090-01-01 10:00:00','post_date_gmt'=>'2090-01-01 09:00:00']);
check_and_publish_future_post($G);t('check_and_publish_future_post (noch nicht fällig)',get_post_status($G)==='future'&&wp_next_scheduled('publish_future_post',[$G])===strtotime('2090-01-01 09:00:00 GMT'));
t('wp_resolve_post_date',wp_resolve_post_date('2026-02-30 10:00:00')===false&&wp_resolve_post_date('2026-02-28 10:00:00')==='2026-02-28 10:00:00'&&preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/',(string)wp_resolve_post_date())===1&&wp_resolve_post_date('',  '2026-03-01 08:00:00')!==false);
t('_truncate_post_slug',_truncate_post_slug(str_repeat('a',300),20)===str_repeat('a',20)&&_truncate_post_slug('kurz',20)==='kurz'&&strlen(_truncate_post_slug(rawurlencode(str_repeat('ä',60)),20))<=20&&_truncate_post_slug('abc-def',4)==='abc');
wp_set_post_tags($A,'eins');wp_add_post_tags($A,'zwei');
t('wp_add_post_tags',wp_get_post_terms($A,'post_tag',['fields'=>'names'])===['eins','zwei']);
t('add_ping/get_pung',add_ping($A,'http://a.test/p')===1&&add_ping($A,['http://b.test/p'])===1&&get_pung($A)===['http://a.test/p','http://b.test/p']&&get_pung(0)===false);
$wpdb->update($wpdb->posts,['to_ping'=>"http://t.test/x\n  javascript:evil \nhttp://u.test/y"],['ID'=>$A]);clean_post_cache($A);
t('get_to_ping (nur gültige Adressen)',get_to_ping($A)===['http://t.test/x','http://u.test/y']);
add_post_meta($A,'enclosure',"http://m.test/a.mp3\n123\naudio/mpeg\n");
t('get_enclosed',get_enclosed($A)===['http://m.test/a.mp3']);
t('trackback_url_list ohne trackback() ohne Wirkung',trackback_url_list('http://a.test',$A)===null);
t('wp_check_post_hierarchy_for_loops',wp_check_post_hierarchy_for_loops($C,$B)===0&&wp_check_post_hierarchy_for_loops($B,$D)===$B&&wp_check_post_hierarchy_for_loops(0,$B)===0&&wp_check_post_hierarchy_for_loops($B,$B)===0);
$ids=get_all_page_ids();t('get_all_page_ids',in_array($B,$ids,true)&&in_array($D,$ids,true)&&!in_array($A,$ids,true));
$pages=get_pages(['sort_column'=>'ID']);$kids=get_page_children($B,$pages);
t('get_page_children',array_map(fn($p)=>(int)$p->ID,$kids)===[$C,$D]&&get_page_children($D,$pages)===[]);
$pp=$pages;t('get_page_hierarchy/_page_traverse_name',get_page_hierarchy($pp,0)===[$B=>'seite-eltern',$C=>'seite-kind',$D=>'seite-enkel']&&get_page_hierarchy($pp,$C)===[$D=>'seite-enkel']);
t('is_local_attachment',is_local_attachment(home_url('/?attachment_id='.$img))&&!is_local_attachment('http://fremd.test/x.jpg'));

$before=clone get_post($A);wp_update_post(['ID'=>$A,'post_name'=>'alpha-neu']);$after=get_post($A);
wp_check_for_changed_slugs($A,$after,$before);t('wp_check_for_changed_slugs',get_post_meta($A,'_wp_old_slug')===['alpha']);
$b2=clone $after;$a2=clone $after;$b2->post_date='2026-01-01 10:00:00';wp_check_for_changed_dates($A,$a2,$b2);
t('wp_check_for_changed_dates',get_post_meta($A,'_wp_old_date')===['2026-01-01']);
t('_find_post_by_old_slug',(function() use($A){ set_query_var('name','alpha');return _find_post_by_old_slug('post')===$A&&_find_post_by_old_slug('page')===0; })());
$sql=get_posts_by_author_sql('post');t('get_posts_by_author_sql',str_starts_with($sql,'WHERE ')&&str_contains($sql,"post_status = 'publish'")&&str_contains($sql,"'private'")&&get_posts_by_author_sql('gibtsnicht')==='WHERE 1 = 0'&&str_contains(get_posts_by_author_sql('post',false,7),'post_author = 7'));
t('get_private_posts_cap_sql',!str_starts_with(get_private_posts_cap_sql('post'),'WHERE'));
$lp=get_lastpostdate('gmt');t('get_lastpostdate/get_lastpostmodified',preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/',(string)$lp)===1&&get_lastpostmodified('gmt')>=$lp&&preg_match('/^\d{4}/',(string)get_lastpostdate('server','page'))===1&&get_lastpostdate('blog','gibtsnicht')===false);
t('_get_last_post_time ungültiges Feld',_get_last_post_time('gmt','falsch')===false);
$arr=[get_post($A)];update_post_cache($arr);update_post_author_caches($arr);update_post_parent_caches($arr);_prime_post_parent_id_caches([$A]);wp_queue_posts_for_term_meta_lazyload($arr);t('Cache-Funktionen laufen',true);
wp_cache_set_posts_last_changed();t('wp_cache_set_posts_last_changed',wp_cache_get('last_changed','posts')!==false);
_transition_post_status('publish','draft',get_post($A));t('_transition_post_status',wp_cache_get('lastpostdate:gmt','timeinfo')===false);
_future_post_hook(0,get_post($G));t('_future_post_hook',wp_next_scheduled('publish_future_post',[$G])!==false);
_publish_post_hook($A);t('_publish_post_hook',get_post_meta($A,'_encloseme',true)==='1'&&wp_next_scheduled('do_pings')!==false);
$old=wp_insert_post(['post_title'=>'Alt','post_status'=>'auto-draft','post_date'=>'2020-01-01 00:00:00']);$new=wp_insert_post(['post_title'=>'Neu','post_status'=>'auto-draft']);
wp_delete_auto_drafts();t('wp_delete_auto_drafts',get_post($old)===null&&get_post($new)!==null);
$T=wp_insert_post(['post_title'=>'Weg','post_status'=>'publish','post_name'=>'weg']);wp_trash_post($T);
t('wp_add_trashed_suffix_to_post_name_for_trashed_posts',get_post($T)->post_name==='weg'&&wp_add_trashed_suffix_to_post_name_for_trashed_posts('weg',$T)==='weg__trashed'&&get_post($T)->post_name==='weg__trashed'&&get_post_meta($T,'_wp_desired_post_slug',true)==='weg'&&wp_add_trashed_suffix_to_post_name_for_post($T)==='weg__trashed'&&wp_add_trashed_suffix_to_post_name_for_trashed_posts('x',$A)==='x');
t('wp_untrash_post_set_previous_status',wp_untrash_post_set_previous_status('draft',1,'pending')==='pending');
wp_create_initial_post_meta();t('wp_create_initial_post_meta',registered_meta_key_exists('post','footnotes','post'));
t('wp_after_insert_post feuert',(function() use($A){ $got=0;add_action('wp_after_insert_post',function() use(&$got){ $got++; });wp_after_insert_post($A,true,null);return $got===1; })());
t('_add_post_type_submenus ohne Fehler',(function(){ _add_post_type_submenus();return true; })());

/* ───────── Begriffe, Kategorien, Schlagwörter ───────── */
$M=wp_create_category('Musik');$R=wp_insert_category(['cat_name'=>'Rock','category_parent'=>$M,'category_nicename'=>'rock']);
t('wp_create_category/wp_insert_category',$M>0&&$R>0&&wp_create_category('Musik')===$M&&(int)get_term($R,'category')->parent===$M&&wp_insert_category(['cat_name'=>' '])===0&&is_wp_error(wp_insert_category(['cat_name'=>''],true)));
t('wp_create_categories',(function() use($M,$A){ $ids=wp_create_categories(['Musik','Jazz'],$A);return count($ids)===2&&$ids[0]===$M&&array_diff(wp_get_post_categories($A),$ids)===[]&&count(wp_get_post_categories($A))===2; })());
t('get_ancestors/term_is_ancestor_of/cat_is_ancestor_of',get_ancestors($R,'category')===[$M]&&get_ancestors($M,'category')===[]&&term_is_ancestor_of($M,$R,'category')&&!term_is_ancestor_of($R,$M,'category')&&cat_is_ancestor_of($M,$R)&&get_ancestors($D,'page')===[$C,$B]);
t('wp_get_term_taxonomy_parent_id',wp_get_term_taxonomy_parent_id($R,'category')===$M&&wp_get_term_taxonomy_parent_id(99999,'category')===false);
t('wp_check_term_hierarchy_for_loops',wp_check_term_hierarchy_for_loops($R,$M,'category')===0&&wp_check_term_hierarchy_for_loops($M,$R,'category')===$M&&wp_check_term_hierarchy_for_loops(0,$R,'category')===0);
t('_get_term_children',_get_term_children($M,get_terms(['taxonomy'=>'category','hide_empty'=>false]),'category')[0]->term_id==$R&&_get_term_children($M,[$R],'category')===[$R]&&_get_term_children($R,[],'category')===[]);
wp_set_object_terms($A,[$R],'category');
t('get_category_by_path',get_category_by_path('musik/rock')->term_id==$R&&get_category_by_path('musik/rock')->cat_ID==$R&&get_category_by_path('rock',false)->term_id==$R&&get_category_by_path('rock')===null&&get_category_by_path('nix')===null);
$cats=get_terms(['taxonomy'=>'category','hide_empty'=>false,'include'=>[$M,$R]]);foreach($cats as $c)$c->count=($c->term_id==$R?2:1);_pad_term_counts($cats,'category');
t('_pad_term_counts',array_column(array_map(fn($c)=>['i'=>$c->term_id,'n'=>$c->count],$cats),'n','i')==[$M=>3,$R=>2]);
t('_make_cat_compat',(function() use($R){ $c=get_term($R,'category');_make_cat_compat($c);$a=['term_id'=>5,'name'=>'x'];_make_cat_compat($a);return $c->cat_name==='Rock'&&$c->category_parent!==0&&$a['cat_ID']===5&&$a['cat_name']==='x'; })());
t('wp_update_category',wp_update_category(['cat_ID'=>$R,'cat_name'=>'Hardrock'])===$R&&get_term($R,'category')->name==='Hardrock'&&(int)get_term($R,'category')->parent===$M&&wp_update_category(['cat_ID'=>$R,'category_parent'=>$R])===false);
t('get_category_to_edit',get_category_to_edit($R)->filter==='edit'&&get_category_to_edit($R)->cat_name==='Hardrock'&&get_term_to_edit(0,'category')==='');
t('sanitize_term_field',sanitize_term_field('parent','-5',1,'category','raw')===0&&sanitize_term_field('count','7',1,'category','display')===7&&sanitize_term_field('name','a"b',1,'category','edit')==='a&quot;b'&&sanitize_term_field('description','<b>',1,'category','edit')==='&lt;b&gt;'&&sanitize_term_field('name','x',1,'category','attribute')==='x');
t('sanitize_term/sanitize_category',(function(){ $o=sanitize_term((object)['term_id'=>'3','name'=>'n','parent'=>'-1'],'category','raw');$a=sanitize_term(['term_id'=>'4','count'=>'2'],'category','display');return $o->term_id===3&&$o->parent===0&&$o->filter==='raw'&&$a['count']===2&&sanitize_category(['term_id'=>'9'],'raw')['term_id']===9&&sanitize_category_field('term_id','8',1,'raw')===8; })());
t('wp_unique_term_slug',(function() use($M,$R){ $x=(object)['taxonomy'=>'category','term_id'=>0,'parent'=>0];$free=wp_unique_term_slug('neu',$x);$dup=wp_unique_term_slug('musik',$x);$self=wp_unique_term_slug('musik',(object)['taxonomy'=>'category','term_id'=>$M,'parent'=>0]);
    $child=wp_unique_term_slug('musik',(object)['taxonomy'=>'category','term_id'=>0,'parent'=>$R]);return $free==='neu'&&$dup==='musik-2'&&$self==='musik'&&$child==='musik-rock'; })());
t('wp_create_tag/tag_exists/wp_create_term',(function(){ $r=wp_create_tag('Jazz');$again=wp_create_tag('Jazz');return is_array($r)&&is_array(tag_exists('Jazz'))&&$again['term_id']===$r['term_id']&&!tag_exists('Gibtsnicht')&&is_array(wp_create_term('Bebop','post_tag')); })());
$tagJ=tag_exists('Jazz')['term_id'];
t('wp_add_object_terms/is_object_in_term',(function() use($A,$tagJ){ wp_add_object_terms($A,'Jazz','post_tag');return is_object_in_term($A,'post_tag','Jazz')===true&&is_object_in_term($A,'post_tag',$tagJ)===true&&is_object_in_term($A,'post_tag',['x','zwei'])===true&&is_object_in_term($A,'post_tag','nix')===false&&is_object_in_term($A,'post_tag')===true&&is_wp_error(is_object_in_term(0,'post_tag')); })());
t('get_terms_to_edit/get_tags_to_edit',get_terms_to_edit($A)===get_tags_to_edit($A)&&str_contains((string)get_terms_to_edit($A),'Jazz')&&get_terms_to_edit(0)===false&&get_terms_to_edit($T)===false);
t('get_post_taxonomies/the_taxonomies',(function() use($A){ ob_start();the_taxonomies(['post'=>$A,'before'=>'[','after'=>']']);$o=ob_get_clean();return in_array('category',get_post_taxonomies($A),true)&&str_starts_with($o,'[')&&str_contains($o,'Jazz'); })());
t('get_object_term_cache/update_object_term_cache',(function() use($A){ $none=get_object_term_cache($A,'post_tag');update_object_term_cache([$A],'post');$c=get_object_term_cache($A,'post_tag');return $none===false&&is_array($c)&&count($c)===3; })());
$ttJ=get_term($tagJ,'post_tag')->term_taxonomy_id;$wpdb->update($wpdb->term_taxonomy,['count'=>9],['term_taxonomy_id'=>$ttJ]);
_update_post_term_count([$ttJ],get_taxonomy('post_tag'));t('_update_post_term_count zählt veröffentlichte Beiträge',(int)get_term($tagJ,'post_tag')->count===1);
$wpdb->update($wpdb->term_taxonomy,['count'=>9],['term_taxonomy_id'=>$ttJ]);_update_generic_term_count([$ttJ],get_taxonomy('post_tag'));t('_update_generic_term_count',(int)get_term($tagJ,'post_tag')->count===1);
$wpdb->update($wpdb->posts,['post_status'=>'draft'],['ID'=>$A]);clean_post_cache($A);_update_term_count_on_transition_post_status('draft','publish',get_post($A));
t('_update_term_count_on_transition_post_status',(int)get_term($tagJ,'post_tag')->count===0);
$wpdb->update($wpdb->posts,['post_status'=>'publish'],['ID'=>$A]);clean_post_cache($A);
wp_delete_object_term_relationships($A,['post_tag']);
t('wp_delete_object_term_relationships',wp_get_object_terms($A,'post_tag')===[]&&is_object_in_term($A,'post_tag','Jazz')===false&&(int)get_term($tagJ,'post_tag')->count===0&&count(wp_get_object_terms($A,'category'))>0);
t('wp_delete_category (Unterkategorie rückt nach oben)',(function() use($M,$R,$A){ $r=wp_delete_category($M);return $r===true&&get_term($M,'category')===null&&(int)get_term($R,'category')->parent===0; })());
t('wp_delete_category mit Standard-Kategorie',(function() use($R,$A){ update_option('default_category',$R);$x=wp_create_category('Weg');wp_set_object_terms($A,[$x],'category');$del=wp_delete_category($x);$none=wp_delete_category($R);return $del===true&&$none===0&&wp_get_post_categories($A)===[$R]; })());
t('get_tax_sql',(function() use($R){ $q=get_tax_sql([['taxonomy'=>'category','field'=>'term_id','terms'=>[$R]]],'p','ID');return str_contains($q['join'],'term_relationships')&&str_contains($q['where'],'IN'); })());
t('term_is_publicly_viewable/has_term_meta',is_term_publicly_viewable($R)&&!is_term_publicly_viewable(999999)&&has_term_meta($R)===[]&&(add_term_meta($R,'farbe','rot')&&count(has_term_meta($R))===1)&&wp_check_term_meta_support_prefilter(null)===null);
t('clean_taxonomy_cache/update_term_cache/_prime_term_caches',(function() use($R){ update_term_cache([get_term($R,'category')]);_prime_term_caches([$R]);wp_cache_set_terms_last_changed();clean_taxonomy_cache('category');return wp_cache_get('last_changed','terms')!==false&&wp_cache_get($R,'terms')!==false; })());
t('Begriffe teilen: schlanke Funktionen',wp_get_split_terms(5)===[]&&wp_term_is_shared($R)===false&&_split_shared_term(5,6)===5&&(function() use($R){ update_option('default_category',77);_wp_check_split_default_terms(77,78,1,'category');return (int)get_option('default_category')===78; })());
_wp_batch_split_terms();_wp_check_for_scheduled_split_terms();_wp_check_split_terms_in_menus(1,2,3,'category');_wp_check_split_nav_menu_terms(1,2,3,'category');t('Split-Hooks laufen ohne Fehler',true);
t('wp_lazyload_term_meta',wp_lazyload_term_meta('x',1)==='x');

/* Kategorie-Vorlagen */
t('in_category/category_description',(function() use($A,$R){ wp_update_term($R,'category',['description'=>'Laut']);return in_category($R,$A)&&!in_category(0,$A)&&category_description($R)==='Laut'&&tag_description(0)===''; })());
t('default_topic_count_scale/Sortierung',default_topic_count_scale(9)===100&&default_topic_count_scale(0)===0&&_wp_object_name_sort_cb((object)['name'=>'a2'],(object)['name'=>'a10'])<0&&_wp_object_count_sort_cb((object)['count'=>5],(object)['count'=>2])>0);
$tags=[(object)['term_id'=>1,'name'=>'Klein','slug'=>'klein','count'=>1,'link'=>'/t/klein/'],(object)['term_id'=>2,'name'=>'Gross','slug'=>'gross','count'=>9,'link'=>'/t/gross/']];
$cloud=wp_generate_tag_cloud($tags);
t('wp_generate_tag_cloud',str_contains($cloud,'tag-cloud-link tag-link-1')&&str_contains($cloud,'font-size: 8pt')&&str_contains($cloud,'font-size: 22pt')&&str_contains($cloud,'aria-label="Gross (9 Einträge)"')&&strpos($cloud,'Gross')<strpos($cloud,'Klein'));
t('wp_generate_tag_cloud Formate',count(wp_generate_tag_cloud($tags,['format'=>'array']))===2&&str_contains(wp_generate_tag_cloud($tags,['format'=>'list']),'<ul')&&wp_generate_tag_cloud([])===''&&wp_generate_tag_cloud([],['format'=>'array'])===[]&&str_contains(wp_generate_tag_cloud($tags,['number'=>1,'orderby'=>'count','order'=>'DESC']),'Gross')&&!str_contains(wp_generate_tag_cloud($tags,['number'=>1,'orderby'=>'count','order'=>'DESC']),'Klein')&&str_contains(wp_generate_tag_cloud($tags,['show_count'=>1]),'tag-link-count'));
$tree=[(object)['term_id'=>1,'name'=>'Eltern','parent'=>0,'count'=>2,'description'=>'','taxonomy'=>'category','slug'=>'e'],(object)['term_id'=>2,'name'=>'Kind','parent'=>1,'count'=>1,'description'=>'Info','taxonomy'=>'category','slug'=>'k']];
$wt=walk_category_tree($tree,0,['show_count'=>1,'current_category'=>2,'use_desc_for_title'=>1]);
t('walk_category_tree',str_contains($wt,'cat-item cat-item-1')&&str_contains($wt,"<ul class='children'>")&&str_contains($wt,'current-cat')&&str_contains($wt,'title="Info"')&&str_contains($wt,'(2)')&&!str_contains(walk_category_tree($tree,1,[]),'children'));
$wd=walk_category_dropdown_tree($tree,0,['selected'=>2,'show_count'=>1]);
t('walk_category_dropdown_tree',str_contains($wd,'<option class="level-0" value="1">')&&str_contains($wd,'level-1" value="2" selected="selected">&nbsp;&nbsp;&nbsp;Kind'));

/* Beitragsformate */
t('Beitragsformate',count(get_post_format_strings())===10&&get_post_format_slugs()['quote']==='quote'&&get_post_format_string('')==='Standard'&&get_post_format_string('video')==='Video'&&get_post_format_string('x')===''&&has_post_format('video',$A)===false&&get_post_format_link('video')===false);
t('_post_format_request/_link/_get_term',_post_format_request(['post_format'=>'quote'])['post_format']==='post-format-quote'&&_post_format_request(['post_format'=>''])==['post_format'=>'']&&str_contains(_post_format_link('/x?post_format=a','quote'===1?null:(object)['slug'=>'post-format-quote'],'post_format'),'post_format=quote')&&_post_format_link('l',(object)['slug'=>'s'],'category')==='l'&&_post_format_get_term((object)['slug'=>'post-format-quote'])->name==='Zitat');
t('_post_format_get_terms/_post_format_wp_get_object_terms',(function(){ $tm=[(object)['slug'=>'post-format-link','taxonomy'=>'post_format','name'=>'post-format-link']];$a=_post_format_get_terms($tm,['post_format'],[]);$b=_post_format_get_terms(['post-format-image'],['post_format'],['fields'=>'names']);$c=_post_format_wp_get_object_terms($tm);return $a[0]->name==='Link'&&$b===['Bild']&&$c[0]->name==='Link'; })());

/* ───────── Meta-Registrierung ───────── */
register_post_meta('post','farbe',['type'=>'string','single'=>true,'default'=>'blau','sanitize_callback'=>'strtoupper','revisions_enabled'=>true]);
t('register_post_meta/registered_meta_key_exists',registered_meta_key_exists('post','farbe','post')&&!registered_meta_key_exists('post','farbe')&&get_registered_meta_keys('post','post')['farbe']['single']===true);
t('sanitize_meta',sanitize_meta('farbe','rot','post','post')==='ROT'&&sanitize_meta('unbekannt','rot','post')==='rot');
t('filter_default_metadata',filter_default_metadata('',$A,'farbe',true,'post')==='blau'&&filter_default_metadata([],$A,'farbe',false,'post')===['blau']&&filter_default_metadata('x',$A,'andere',true,'post')==='x');
t('get_object_subtype',get_object_subtype('post',$A)==='post'&&get_object_subtype('term',$R)==='category'&&get_object_subtype('post',999999999)==='');
update_post_meta($A,'farbe','gruen');update_post_meta($A,'ohne','x');
t('get_registered_metadata',get_registered_metadata('post',$A,'farbe')==='gruen'&&get_registered_metadata('post',$A,'ohne')===false&&array_keys(get_registered_metadata('post',$A))===['farbe']);
t('get_metadata_default/_wp_register_meta_args_allowed_list',get_metadata_default('post','x',true)===''&&get_metadata_default('post','x',false)===[]&&_wp_register_meta_args_allowed_list(['a'=>1,'b'=>2],['a'=>0])===['a'=>1]);
t('unregister_post_meta',unregister_post_meta('post','farbe')&&!registered_meta_key_exists('post','farbe','post')&&unregister_post_meta('post','farbe')===false);
register_term_meta('category','symbol',['single'=>true]);t('unregister_term_meta',registered_meta_key_exists('term','symbol','category')&&unregister_term_meta('category','symbol')&&!registered_meta_key_exists('term','symbol','category'));
t('get_meta_sql',(function(){ $q=get_meta_sql([['key'=>'farbe','value'=>'x']],'post','p','ID');return is_array($q)&&str_contains((string)($q['join']??''),'postmeta'); })());
t('wp_metadata_lazyloader',wp_metadata_lazyloader() instanceof WP_Metadata_Lazyloader&&wp_metadata_lazyloader()===wp_metadata_lazyloader());

/* ───────── Optionen ───────── */
add_option('x_opt','wert','','yes');add_option('y_opt',['a'=>1]);
t('get_options',get_options(['x_opt','y_opt','nix_opt'])===['x_opt'=>'wert','y_opt'=>['a'=>1],'nix_opt'=>false]);
t('wp_set_option_autoload',wp_set_option_autoload('x_opt',false)===true&&wp_set_option_autoload('x_opt',false)===false&&!array_key_exists('x_opt',wp_load_alloptions())&&wp_set_option_autoload('x_opt','on')===true&&array_key_exists('x_opt',wp_load_alloptions()));
$res=wp_set_option_autoload_values(['x_opt'=>'off','y_opt'=>'no','nicht_da'=>true]);
t('wp_set_option_autoload_values',$res===['x_opt'=>true,'y_opt'=>true,'nicht_da'=>false]&&get_option('x_opt')==='wert'&&wp_set_option_autoload_values([])===[]);
t('wp_set_options_autoload',wp_set_options_autoload(['x_opt','y_opt'],true)===['x_opt'=>true,'y_opt'=>true]);
t('wp_autoload_values_to_autoload',in_array('on',wp_autoload_values_to_autoload(),true)&&!in_array('no',wp_autoload_values_to_autoload(),true));
t('wp_determine_option_autoload_value',wp_determine_option_autoload_value('a','v','s',true)==='on'&&wp_determine_option_autoload_value('a','v','s',false)==='off'&&wp_determine_option_autoload_value('a','v','s',null)==='auto'&&wp_determine_option_autoload_value('a','v','s','yes')==='yes');
t('wp_filter_default_autoload_value_via_option_size',wp_filter_default_autoload_value_via_option_size(null,'a','v',str_repeat('x',200000))===false&&wp_filter_default_autoload_value_via_option_size(true,'a','v','kurz')===true);
t('wp_protect_special_option',(function(){ $GLOBALS['elvado_wp_die_throws']=true;try{ wp_protect_special_option('alloptions');return false; }catch(ELVADO_WP_Die $e){ return true; }finally{ unset($GLOBALS['elvado_wp_die_throws']); } })()&&(function(){ wp_protect_special_option('harmlos');return true; })());
t('form_option',(function(){ update_option('fo','a"b');ob_start();form_option('fo');return ob_get_clean()==='a&quot;b'; })());
t('prime-Funktionen ohne Wirkung',(function(){ wp_prime_option_caches_by_group('x');wp_prime_site_option_caches(['a']);wp_prime_network_option_caches(1,['a']);wp_load_core_site_options();return true; })());
set_transient('abgelaufen','x',60);set_transient('frisch','y',600);update_option('_transient_timeout_abgelaufen',time()-10);
t('delete_expired_transients',delete_expired_transients()===1&&get_transient('abgelaufen')===false&&get_transient('frisch')==='y');
t('Benutzer-Einstellungen',(function(){ wp_set_all_user_settings(['editor'=>'html','x y'=>'a b']);$ok=get_all_user_settings()['editor']==='html';delete_user_setting('editor');$gone=!isset(get_all_user_settings()['editor']);delete_all_user_settings();return $ok&&$gone&&get_all_user_settings()===[]&&get_user_option('user-settings')===false; })());
wp_user_settings();t('wp_user_settings ohne Fehler',true);
register_initial_settings();
t('register_initial_settings/filter_default_option',isset(get_registered_settings()['posts_per_page'])&&filter_default_option('x','posts_per_page',false)===10&&filter_default_option('x','posts_per_page',true)==='x'&&filter_default_option('x','unbekannt',false)==='x');

/* ───────── Revisionen ───────── */
$P=wp_insert_post(['post_title'=>'Rev','post_content'=>'Zeile 1','post_status'=>'publish','post_name'=>'rev']);
t('_wp_post_revision_fields',array_keys(_wp_post_revision_fields($P))===['post_title','post_content','post_excerpt']&&!isset(_wp_post_revision_fields($P)['post_name']));
$rd=_wp_post_revision_data(get_post($P,ARRAY_A));t('_wp_post_revision_data',$rd['post_type']==='revision'&&$rd['post_parent']==$P&&$rd['post_status']==='inherit'&&$rd['post_name']==="$P-revision-v1"&&_wp_post_revision_data(get_post($P,ARRAY_A),true)['post_name']==="$P-autosave-v1");
$r1=_wp_put_post_revision($P);wp_update_post(['ID'=>$P,'post_content'=>"Zeile 1\nZeile 2",'post_title'=>'Rev neu']);$r2=_wp_put_post_revision($P);
t('_wp_put_post_revision',is_int($r1)&&$r1>0&&$r2>$r1&&get_post($r1)->post_type==='revision'&&(int)get_post($r1)->post_parent===$P&&is_wp_error(_wp_put_post_revision($r1))&&is_wp_error(_wp_put_post_revision(0)));
t('wp_get_post_revision',wp_get_post_revision($r1)->post_content==='Zeile 1'&&wp_get_post_revision($P)===null&&wp_get_post_revision($r1,ARRAY_A)['post_title']==='Rev'&&wp_get_post_revision(999999999)===null);
t('_wp_get_post_revision_version',_wp_get_post_revision_version(get_post($r1))===1&&_wp_get_post_revision_version(['post_name'=>'x'])===0&&_wp_get_post_revision_version((object)['post_name'=>'7-autosave-v3'])===3);
t('wp_get_latest_revision_id_and_total_count',wp_get_latest_revision_id_and_total_count($P)==['latest_id'=>$r2,'count'=>2]&&wp_get_latest_revision_id_and_total_count($A)==['latest_id'=>0,'count'=>0]&&is_wp_error(wp_get_latest_revision_id_and_total_count(0)));
$ru=wp_get_post_revisions_url($P);t('wp_get_post_revisions_url',$ru===null||is_string($ru));
t('wp_revisions_to_keep',wp_revisions_to_keep(get_post($P))===-1&&wp_revisions_to_keep(get_post($B))===-1);
t('wp_restore_post_revision',wp_restore_post_revision($r1)===$P&&get_post($P)->post_content==='Zeile 1'&&get_post($P)->post_title==='Rev'&&(int)get_post_meta($P,'_edit_last',true)===1&&wp_restore_post_revision($P)===null&&wp_restore_post_revision($r1,['post_title'])===$P);
t('wp_save_post_revision_on_insert',(function() use($P){ wp_save_post_revision_on_insert($P,get_post($P),false);return true; })());
register_post_meta('post','rev_m',['single'=>true,'revisions_enabled'=>true]);update_post_meta($P,'rev_m','v1');
t('wp_post_revision_meta_keys',wp_post_revision_meta_keys('post')===['footnotes','rev_m']&&wp_post_revision_meta_keys('page')===['footnotes']);
$r3=_wp_put_post_revision($P);wp_save_revisioned_meta_fields($r3,$P);
t('wp_save_revisioned_meta_fields/_wp_copy_post_meta',get_post_meta($r3,'rev_m',true)==='v1');
update_post_meta($P,'rev_m','v2');
t('wp_check_revisioned_meta_fields_have_changed',wp_check_revisioned_meta_fields_have_changed(false,get_post($r3),get_post($P))===true&&wp_check_revisioned_meta_fields_have_changed(false,get_post($r1),get_post($P))===true);
wp_restore_post_revision_meta(get_post($P),$r3);t('wp_restore_post_revision_meta',get_post_meta($P,'rev_m',true)==='v1');
$ui=wp_get_revision_ui_diff($P,$r1,$r2);$byId=array_column($ui,'diff','id');
t('wp_get_revision_ui_diff',isset($byId['post_content'])&&str_contains($byId['post_content'],'diff-addedline')&&str_contains($byId['post_content'],'Zeile 2')&&isset($byId['post_title'])&&!isset($byId['post_excerpt'])&&wp_get_revision_ui_diff($P,0,999999999)===false);
t('elvado_wp_x_text_diff',elvado_wp_x_text_diff("a\nb\nc","a\nc")!==''&&str_contains(elvado_wp_x_text_diff("a\nb\nc","a\nc"),'<del>b</del>')&&elvado_wp_x_text_diff('x','x')===''&&str_contains(elvado_wp_x_text_diff('x','x',true),'<td>x</td>'));
$js=wp_prepare_revisions_for_js($P,$r2);t('wp_prepare_revisions_for_js',isset($js[$P])&&$js[$P]['current']===true&&$js[$P]['restoreUrl']===false&&isset($js[$r1])&&is_string($js[$r1]['restoreUrl'])&&str_contains($js[$r1]['restoreUrl'],'revision='.$r1)&&str_starts_with($js[$r1]['timeAgo'],'vor '));
t('wp_post_revision_title',str_contains((string)wp_post_revision_title($r1,false),'vor ')&&str_contains((string)wp_post_revision_title($P),'[Aktuelle Revision]')&&wp_post_revision_title(999999999)===null&&wp_post_revision_title($img)===false);
t('wp_post_revision_title_expanded',str_contains((string)wp_post_revision_title_expanded($r1,false),'avatar')&&str_contains((string)wp_post_revision_title_expanded($P),'[Aktuelle Revision]'));
t('wp_list_post_revisions',(function() use($P,$A){ ob_start();wp_list_post_revisions($P);$o=ob_get_clean();ob_start();wp_list_post_revisions($A);$e=ob_get_clean();return str_contains($o,"<ul class='post-revisions'>")&&substr_count($o,'<li>')===3&&$e===''; })());
t('_wp_upgrade_revisions_of_post',(function() use($P,$wpdb,$r1){ $wpdb->update($wpdb->posts,['post_name'=>'alt'],['ID'=>$r1]);clean_post_cache($r1);$ok=_wp_upgrade_revisions_of_post(get_post($P),[get_post($r1)]);return $ok===true&&_wp_get_post_revision_version(get_post($r1))===1&&get_option("revision-upgrade-$P")===false; })());

/* Vorschau */
t('_wp_preview_*-Filter ohne Anfrage-Daten',_wp_preview_terms_filter(['t'],$A,'post_format')===['t']&&_wp_preview_post_thumbnail_filter('v',$A,'_thumbnail_id')==='v'&&_wp_preview_meta_filter('v',$A,'k',true)==='v');
t('_set_preview',(function() use($P){ $p=get_post($P);return _set_preview($p)===$p&&_set_preview('kein Objekt')==='kein Objekt'; })());
t('_show_post_preview',(function() use($P){ $_GET=['preview_id'=>$P,'preview_nonce'=>wp_create_nonce('post_preview_'.$P)];_show_post_preview();$ok=has_filter('the_preview','_set_preview')!==false;$_GET=['preview_id'=>$P,'preview_nonce'=>'falsch'];$GLOBALS['elvado_wp_die_throws']=true;try{ _show_post_preview();$bad=false; }catch(ELVADO_WP_Die $e){ $bad=true; }unset($GLOBALS['elvado_wp_die_throws']);$_GET=[];return $ok&&$bad; })());

/* ───────── Beitrags-Vorlagen ───────── */
t('get_the_guid/the_guid',(function() use($A){ ob_start();the_guid($A);$o=ob_get_clean();return str_contains(get_the_guid($A),'p='.$A)&&$o===esc_url(get_the_guid($A)); })());
t('post_custom',(function() use($P){ add_post_meta($P,'multi','a');add_post_meta($P,'multi','b');$GLOBALS['post']=get_post($P);$r=post_custom('multi')===['a','b']&&post_custom('rev_m')==='v1'&&post_custom('nix')===false;return $r; })());
t('the_meta',(function() use($P){ ob_start();the_meta();$o=ob_get_clean();return str_contains($o,"<ul class='post-meta'>")&&str_contains($o,'multi:')&&str_contains($o,'a, b')&&!str_contains($o,'_edit_last'); })());
t('_wp_link_page',(function() use($P){ $GLOBALS['post']=get_post($P);$a=_wp_link_page(1);$b=_wp_link_page(2);return str_starts_with($a,'<a href="')&&str_contains($a,'post-page-numbers')&&$a!==$b&&str_contains($b,'/2/'); })());
$pg=get_pages(['sort_column'=>'ID']);
t('walk_page_tree',(function() use($pg,$B,$C){ $h=walk_page_tree($pg,0,$C,[]);return str_contains($h,'page-item-'.$B)&&str_contains($h,'page_item_has_children')&&str_contains($h,"<ul class='children'>")&&str_contains($h,'current_page_item')&&substr_count($h,'<li')===3&&substr_count(walk_page_tree($pg,1,0,[]),'<li')===1; })());
t('walk_page_dropdown_tree',(function() use($pg,$C){ $h=walk_page_dropdown_tree($pg,0,['selected'=>$C]);return str_contains($h,'level-2')&&str_contains($h,'selected="selected"')&&str_contains($h,'&nbsp;&nbsp;&nbsp;Seite Enkel'); })());
t('wp_dropdown_pages',(function() use($B){ $h=wp_dropdown_pages(['echo'=>0,'name'=>'seite','selected'=>$B,'show_option_none'=>'(keine)','option_none_value'=>'0']);return str_starts_with($h,"<select name='seite'")&&str_contains($h,'(keine)')&&str_contains($h,'value="'.$B.'" selected="selected"')&&str_ends_with(trim($h),'</select>'); })());
t('the_attachment_link/the_post_thumbnail_caption',(function() use($img){ ob_start();the_attachment_link($img);the_post_thumbnail_caption($img);$o=ob_get_clean();return is_string($o); })());

/* ───────── Cron, Rewrite, Abfrage, Weiterleitung ───────── */
wp_schedule_event(time()-7200,'hourly','x_cron');$ts=wp_next_scheduled('x_cron');wp_unschedule_event($ts,'x_cron');
t('wp_reschedule_event',wp_reschedule_event($ts,'hourly','x_cron')===true&&wp_next_scheduled('x_cron')>time()&&wp_next_scheduled('x_cron')<=time()+3600&&wp_reschedule_event($ts,'gibtsnicht','x_cron')===false&&is_wp_error(wp_reschedule_event($ts,'gibtsnicht','x_cron',[],true)));
t('_get_cron_array/_set_cron_array',(function(){ $c=_get_cron_array();$c[9999999999]['y_cron']['k']=['schedule'=>false,'args'=>[]];_set_cron_array($c);return isset(_get_cron_array()[9999999999]['y_cron'])&&!isset(_get_cron_array()['version']); })());
t('_upgrade_cron_array',(function(){ $u=_upgrade_cron_array([100=>['h'=>['args'=>['a']]]]);return $u['version']===2&&isset($u[100]['h'][md5(serialize(['a']))])&&_upgrade_cron_array(['version'=>2,'x'=>1])['x']===1; })());
wp_clear_scheduled_hook('do_pings');t('_wp_cron (nichts fällig)',_wp_cron()===0);
wp_schedule_single_event(time()-5,'z_cron');$ran=0;add_action('z_cron',function() use(&$ran){ $ran++; });
t('_wp_cron führt Fälliges aus',_wp_cron()===1&&$ran===1);
t('add_feed',add_feed('podcast','strlen')==='do_feed_podcast'&&in_array('podcast',$GLOBALS['wp_rewrite']->feeds,true)&&has_action('do_feed_podcast','strlen')!==false);
remove_rewrite_tag('%x%');remove_permastruct('x');t('Rewrite-Stubs',true);
t('_wp_filter_taxonomy_base',_wp_filter_taxonomy_base('/index.php/themen/')==='themen'&&_wp_filter_taxonomy_base('')==='');
t('wp_resolve_numeric_slug_conflicts',wp_resolve_numeric_slug_conflicts(['name'=>'x'])===['name'=>'x']&&(function() use($wpdb){ $id=wp_insert_post(['post_title'=>'2077','post_name'=>'2077','post_status'=>'publish']);update_option('permalink_structure','/%postname%/');$r=wp_resolve_numeric_slug_conflicts(['year'=>'2077']);return $r==['name'=>'2077']&&wp_resolve_numeric_slug_conflicts(['year'=>'1999'])==['year'=>'1999']; })());
t('is_comment_feed/is_favicon',is_comment_feed()===false&&is_favicon()===false);
t('the_comment',(function(){ global $wp_query,$comment;$wp_query=new WP_Query();$wp_query->comments=[(object)['comment_ID'=>5],(object)['comment_ID'=>6]];$wp_query->current_comment=-1;the_comment();$a=$comment->comment_ID;the_comment();return $a===5&&$comment->comment_ID===6; })());
t('strip_fragment_from_url',strip_fragment_from_url('http://a.test/x?y=1#frag')==='http://a.test/x?y=1'&&strip_fragment_from_url('/x')==='/x');
t('_remove_qs_args_if_not_in_url',_remove_qs_args_if_not_in_url('?a=1&b=2',['b'],'http://x.test/?a=1')==='?a=1'&&_remove_qs_args_if_not_in_url('?a=1&b=2',['b'],'http://x.test/?b=3')==='?a=1&b=2');
t('redirect_guess_404_permalink',(function(){ set_query_var('name','erst');$u=redirect_guess_404_permalink();set_query_var('name','gibtsnicht');$n=redirect_guess_404_permalink();set_query_var('name','');return str_contains((string)$u,'/erster/')&&$n===false&&redirect_guess_404_permalink()===false; })());
$_SERVER['REQUEST_METHOD']='GET';$GLOBALS['wp_query']=$GLOBALS['wp_the_query']=new WP_Query(['p'=>1]);
t('redirect_canonical (?p=ID → schöne Adresse)',redirect_canonical('http://example.test/?p=1',false)==='http://example.test/erster/'&&redirect_canonical('http://example.test/erster/',false)===false&&redirect_canonical('http://example.test/erster',false)==='http://example.test/erster/'&&redirect_canonical('http://example.test/?p=1&utm=a',false)==='http://example.test/erster/?utm=a');
$_SERVER['REQUEST_METHOD']='POST';t('redirect_canonical nur GET/HEAD',redirect_canonical('http://example.test/?p=1',false)===null);$_SERVER['REQUEST_METHOD']='GET';
t('redirect_canonical Filter',(function(){ add_filter('redirect_canonical',fn($u)=>false);$r=redirect_canonical('http://example.test/?p=1',false);remove_all_filters('redirect_canonical');return $r===false; })());
t('redirect_canonical 404 → Vorschlag',(function(){ $GLOBALS['wp_query']=new WP_Query(['p'=>999999999]);$GLOBALS['wp_query']->set_404();set_query_var('name','zweit');$r=redirect_canonical('http://example.test/zweit',false);set_query_var('name','');return str_contains((string)$r,'/zweiter/'); })());
t('wp_redirect_admin_locations (kein 404)',(function(){ $GLOBALS['wp_query']=new WP_Query(['p'=>1]);wp_redirect_admin_locations();return true; })());
t('generate_postdata',(function() use($P){ wp_update_post(['ID'=>$P,'post_content'=>"Seite eins\n<!--nextpage-->\nSeite zwei"]);$g=generate_postdata($P);return $g['numpages']===2&&$g['multipage']===1&&$g['pages']===['Seite eins','Seite zwei']&&$g['id']===$P&&$g['page']===1&&generate_postdata(999999999)===false; })());

/* ───────── Verwaltung: Formulare, Meta, Listen ───────── */
t('_wp_get_allowed_postdata',_wp_get_allowed_postdata(['post_title'=>'x','guid'=>'g','meta_input'=>['a'=>1],'file'=>'f'])==['post_title'=>'x']);
$_POST=[];
$tr=_wp_translate_postdata(false,['post_type'=>'post','post_title'=>'Form','content'=>'Inhalt','excerpt'=>'Auszug','publish'=>'1','parent_id'=>'4','trackback_url'=>'http://t.test']);
t('_wp_translate_postdata',$tr['post_content']==='Inhalt'&&$tr['post_excerpt']==='Auszug'&&$tr['post_status']==='publish'&&$tr['post_parent']===4&&$tr['to_ping']==='http://t.test'&&$tr['post_author']===1);
$td=_wp_translate_postdata(false,['post_type'=>'post','aa'=>'2030','mm'=>'05','jj'=>'06','hh'=>'07','mn'=>'08','ss'=>'09','edit_date'=>'1']);
t('_wp_translate_postdata Datum',$td['post_date']==='2030-05-06 07:08:09'&&is_wp_error(_wp_translate_postdata(false,['post_type'=>'post','aa'=>'2030','mm'=>'02','jj'=>'31','edit_date'=>'1']))&&is_wp_error(_wp_translate_postdata(false,['post_type'=>'gibtsnicht'])));
t('_wp_translate_postdata Rechte',(function(){ $GLOBALS['elvado_wp_user']['caps']=elvado_wp_caps_for_role('subscriber');$a=_wp_translate_postdata(false,['post_type'=>'post']);$GLOBALS['elvado_wp_user']['caps']=elvado_wp_caps_for_role('author');$b=_wp_translate_postdata(false,['post_type'=>'post','publish'=>'1']);$c=_wp_translate_postdata(false,['post_type'=>'post','post_author'=>'5']);$GLOBALS['elvado_wp_user']['caps']=elvado_wp_caps_for_role('administrator');return is_wp_error($a)&&$b['post_status']==='publish'&&is_wp_error($c); })());
$_POST=['post_type'=>'post','post_title'=>'Per Formular','content'=>'Text','publish'=>'1','metakeyinput'=>'farbe2','metavalue'=>'rot','tax_input'=>['post_tag'=>'formular']];
$W=wp_write_post();
t('wp_write_post',is_int($W)&&$W>0&&get_post($W)->post_title==='Per Formular'&&get_post($W)->post_status==='publish'&&get_post_meta($W,'farbe2',true)==='rot'&&wp_get_post_terms($W,'post_tag',['fields'=>'names'])===['formular']);
$_POST=['post_type'=>'post','post_title'=>''];$we=wp_write_post();t('wp_write_post Fehlerfall',$we===0||is_wp_error($we));
$_POST=['post_type'=>'gibtsnicht'];t('wp_write_post ungültiger Typ',is_wp_error(wp_write_post()));
$_POST=[];
t('write_post',(function(){ $_POST=['post_type'=>'post','post_title'=>'Write','content'=>'x'];$id=write_post();$_POST=[];return $id>0; })());
$mid=add_post_meta($W,'zusatz','eins');
t('Meta-Verwaltung',(function() use($W,$mid){ $m=get_post_meta_by_id($mid);$ok=$m&&$m->meta_key==='zusatz'&&$m->meta_value==='eins'&&in_array('zusatz',get_meta_keys(),true);
    $h=has_meta($W);$keys=array_column($h,'meta_key');update_meta($mid,'zusatz2','zwei');$u=get_post_meta($W,'zusatz2',true)==='zwei'&&get_post_meta($W,'zusatz',true)==='';
    delete_meta($mid);return $ok&&in_array('zusatz',$keys,true)&&$u&&get_post_meta_by_id($mid)===false&&!in_array('zusatz2',get_meta_keys(),true); })());
t('add_meta Randfälle',(function() use($W){ $_POST=['metakeyselect'=>'#NONE#','metavalue'=>'x'];$a=add_meta($W);$_POST=['metakeyinput'=>'_geschuetzt','metavalue'=>'x'];$b=add_meta($W);$_POST=['metakeyinput'=>'null','metavalue'=>'0'];$c=add_meta($W);$_POST=['metakeyselect'=>'wahl','metakeyinput'=>'eingabe','metavalue'=>'v'];add_meta($W);$d=get_post_meta($W,'eingabe',true);$_POST=[];return $a===false&&$b===false&&is_int($c)&&$d==='v'&&get_post_meta($W,'null',true)==='0'&&get_post_meta($W,'wahl',true)===''; })());
t('edit_post',(function() use($W){ $r=edit_post(['post_ID'=>$W,'post_title'=>'Geändert','content'=>'Neuer Text','post_status'=>'draft','tax_input'=>['post_tag'=>['formular','zweit']]]);$p=get_post($W);return $r===$W&&$p->post_title==='Geändert'&&$p->post_content==='Neuer Text'&&$p->post_status==='draft'&&$p->post_type==='post'; })());
t('edit_post: Meta, Sticky, fremde/ungültige',(function() use($W,$wpdb){ $m=add_post_meta($W,'bearb','alt');$r=edit_post(['post_ID'=>$W,'meta'=>[$m=>['key'=>'bearb','value'=>'neu']],'sticky'=>'sticky']);$ok=get_post_meta($W,'bearb',true)==='neu'&&is_sticky($W);
    edit_post(['post_ID'=>$W,'deletemeta'=>[$m=>'x']]);$del=get_post_meta($W,'bearb',true)==='';$bad=edit_post(['post_ID'=>999999999]);$cms=edit_post(['post_ID'=>1,'post_title'=>'x']);
    $GLOBALS['elvado_wp_user']['caps']=elvado_wp_caps_for_role('subscriber');$deny=edit_post(['post_ID'=>$W,'post_title'=>'Verboten']);$GLOBALS['elvado_wp_user']['caps']=elvado_wp_caps_for_role('administrator');
    return $r===$W&&$ok&&$del&&$bad===0&&$cms===0&&$deny===0&&get_post($W)->post_title==='Geändert'; })());
t('edit_post: Sichtbarkeit privat',(function() use($W){ edit_post(['post_ID'=>$W,'visibility'=>'private']);return get_post($W)->post_status==='private'; })());
t('bulk_edit_posts',(function() use($A,$W,$P){ $r=bulk_edit_posts(['post_ID'=>[$W,$P,1,999999999],'post_type'=>'post','post_status'=>'draft','comment_status'=>-1,'post_author'=>'','sticky'=>'sticky','tax_input'=>['post_tag'=>'sammel']]);
    return $r['updated']===[$W,$P]&&$r['skipped']===[1,999999999]&&$r['locked']===[]&&get_post($P)->post_status==='draft'&&is_sticky($P)&&in_array('sammel',wp_get_post_terms($P,'post_tag',['fields'=>'names']),true)&&bulk_edit_posts(['post_ID'=>[]])['updated']===[]; })());
t('get_default_post_to_edit',(function(){ $_REQUEST['post_title']='Vorgabe';$p=get_default_post_to_edit('page');unset($_REQUEST['post_title']);$d=get_default_post_to_edit('post',true);return $p->ID===0&&$p->post_type==='page'&&$p->post_status==='draft'&&$p->post_title==='Vorgabe'&&$d->ID>0&&$d->post_status==='auto-draft'&&$d->post_type==='post'; })());
t('get_available_post_statuses/wp_edit_posts_query',(function(){ $s=get_available_post_statuses('post');[$all,$av]=wp_edit_posts_query(['post_status'=>'draft','orderby'=>'title','order'=>'asc']);global $wp_query;return in_array('publish',$s,true)&&isset($all['draft'])&&$av===$s&&$wp_query->query_vars['post_status']==='draft'&&array_unique(array_map(fn($p)=>$p->post_status,$wp_query->posts))===['draft']; })());
t('wp_edit_attachments_query(_vars)',(function(){ $q=wp_edit_attachments_query_vars(['attachment-filter'=>'post_mime_type:image','m'=>'2']);[$types,$av]=wp_edit_attachments_query(['attachment-filter'=>'post_mime_type:image']);global $wp_query;
    return $q['post_type']==='attachment'&&$q['post_mime_type']==='image'&&$q['m']===2&&str_contains($q['post_status'],'inherit')&&wp_edit_attachments_query_vars(['attachment-filter'=>'trash'])['post_status']==='trash'&&isset($types['image'])&&in_array('image/jpeg',$av,true)&&$wp_query->post_count===1; })());
t('postbox_classes',postbox_classes('submitdiv','post')===''&&(function(){ update_user_option(1,'closedpostboxes_post',['submitdiv']);$r=postbox_classes('submitdiv','post')==='closed'&&postbox_classes('x','post')==='';update_user_option(1,'closedpostboxes_post',[]);return $r; })());
t('get_sample_permalink_html',(function() use($P){ $h=get_sample_permalink_html($P);return str_contains($h,'id="sample-permalink"')&&str_contains($h,'href=')&&get_sample_permalink_html(999999999)===''; })());
t('_wp_post_thumbnail_html',(function() use($P,$img){ $a=_wp_post_thumbnail_html(null,$P);$wpdb=$GLOBALS['wpdb'];update_attached_file($img,wp_get_upload_dir()['basedir'].'/2026/04/a.jpg');$b=_wp_post_thumbnail_html($img,$P);return str_contains($a,'set-post-thumbnail')&&str_contains($a,'value="-1"')&&str_contains($b,'remove-post-thumbnail')&&str_contains($b,'value="'.$img.'"'); })());
t('wp_autosave: Nonce und Entwurf',(function() use($W,$P){ $bad=wp_autosave(['post_id'=>$P,'_wpnonce'=>'x']);$dr=wp_insert_post(['post_title'=>'Auto','post_status'=>'draft','post_author'=>1]);
    $ok=wp_autosave(['post_id'=>$dr,'post_ID'=>$dr,'_wpnonce'=>wp_create_nonce('update-post_'.$dr),'post_type'=>'post','post_title'=>'Auto neu','content'=>'c']);return is_wp_error($bad)&&$ok===$dr&&get_post($dr)->post_title==='Auto neu'; })());
t('wp_autosave_post_revisioned_meta_fields',(function() use($P){ $_POST['rev_m']='aus Formular';wp_autosave_post_revisioned_meta_fields(['ID'=>$P,'post_parent'=>$P]);$_POST=[];return get_post_meta($P,'rev_m',true)==='aus Formular'; })());
t('post_preview (eigener Entwurf)',(function() use($W){ $dr=wp_insert_post(['post_title'=>'Vorschau','post_status'=>'draft','post_author'=>1]);$_POST=['post_ID'=>$dr,'post_title'=>'Vorschau neu','post_type'=>'post','post_status'=>'draft','content'=>'v'];$u=post_preview();$_POST=[];return is_string($u)&&get_post($dr)->post_title==='Vorschau neu'; })());
t('redirect_post',(function() use($P){ $GLOBALS['elvado_wp_is_admin']=true;$loc=function($post) use($P){ $_POST=$post;try{ redirect_post($P); }catch(ELVADO_WP_Die $e){}$_POST=[];return (string)($GLOBALS['elvado_wp_admin_redirect']??''); };
    $a=$loc(['save'=>'1']);$b=$loc([]);$c=$loc(['addmeta'=>'1']);$d=$loc(['publish'=>'1']);unset($GLOBALS['elvado_wp_is_admin'],$GLOBALS['elvado_wp_admin_redirect']);
    return str_contains($a,'message=10')&&str_contains($b,'message=4')&&str_contains($c,'message=2')&&str_contains($c,'#postcustom')&&str_contains($d,'message=10'); })());
t('taxonomy_meta_box_sanitize_cb_*',taxonomy_meta_box_sanitize_cb_checkboxes('category',['3','x'])===[3,0]&&taxonomy_meta_box_sanitize_cb_input('post_tag','Jazz, neu ,,')===[$tagJ,'neu']&&taxonomy_meta_box_sanitize_cb_input('post_tag',['Jazz',''])===[$tagJ]);
t('_fix_attachment_links',(function() use($P,$img){ update_option('permalink_structure','/%postname%/');$c='<a href="'.home_url('/?attachment_id='.$img).'" rel="attachment wp-att-'.$img.'">Bild</a>';wp_update_post(['ID'=>$P,'post_status'=>'publish','post_content'=>$c]);$r=_fix_attachment_links($P);return $r===$P&&!str_contains(get_post($P)->post_content,'?attachment_id=')&&_fix_attachment_links(999999999)===null; })());
t('get_block_editor_server_block_settings',(function(){ register_block_type('test/block',['title'=>'Test','attributes'=>['a'=>['type'=>'string']],'supports'=>['html'=>false]]);$s=get_block_editor_server_block_settings();return $s['test/block']['title']==='Test'&&isset($s['test/block']['attributes']['a'])&&$s['test/block']['supports']==['html'=>false]; })());
t('Block-Editor-Hilfen',(function() use($P){ ob_start();the_block_editor_meta_box_post_form_hidden_fields($P);the_block_editor_meta_boxes();$o=ob_get_clean();return str_contains($o,'name="post_ID" value="'.$P.'"')&&str_contains($o,'name="_wpnonce"')&&_disable_block_editor_for_navigation_post_type(true,'wp_navigation')===false&&_disable_block_editor_for_navigation_post_type(true,'post')===true; })());
t('Navigations-Editor ein-/ausschalten',(function(){ register_post_type('wp_navigation',['supports'=>['title','editor']]);$p=wp_insert_post(['post_title'=>'Nav','post_type'=>'wp_navigation','post_status'=>'publish']);_disable_content_editor_for_navigation_post_type($p);$off=!post_type_supports('wp_navigation','editor');_enable_content_editor_for_navigation_post_type($p);return $off&&post_type_supports('wp_navigation','editor'); })());
t('_admin_notice_post_locked',(function(){ _admin_notice_post_locked();return true; })());

/* Rechte */
t('Rechte-Hilfen',wp_maybe_grant_install_languages_cap(['update_core'=>true,'install_plugins'=>true,'install_themes'=>true])['install_languages']===true&&!isset(wp_maybe_grant_install_languages_cap(['update_core'=>true])['install_languages'])&&wp_maybe_grant_resume_extensions_caps(['edit_themes'=>true])['resume_themes']===true&&wp_maybe_grant_resume_extensions_caps(['edit_plugins'=>true])['resume_plugins']===true&&wp_maybe_grant_site_health_caps(['install_plugins'=>true])['view_site_health_checks']===true);
t('Multisite-Funktionen: Standardwerte',current_user_can_for_site(1,'manage_options')===true&&current_user_can_for_site(1,'gibtsnicht')===false&&user_can_for_site(wp_get_current_user(),1,'manage_options')===true&&grant_super_admin(1)===false&&revoke_super_admin(1)===false);

system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

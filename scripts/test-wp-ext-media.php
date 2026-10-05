<?php
// Prüft die ergänzenden Medien-/Theme-/Skript-/Widget-/Vorlagen-Funktionen der WordPress-Schicht (cms/wp/core/ext/media-*.php).
// Alle Funktionen der Liste müssen existieren; dazu Rückgabewerte und Randfälle der wichtigsten. Aufruf: php scripts/test-wp-ext-media.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/rrw-wpm-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/wp-content/themes');mkdir($tmp.'/wp-content/uploads');mkdir($tmp.'/cms');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('RRW_WP_DATA',$tmp.'/cms/.wp');define('RRW_WP_CMS_DATA',$tmp.'/cms');$_SERVER['HTTP_HOST']='example.test';
file_put_contents($tmp.'/cms/news.json','[]');file_put_contents($tmp.'/cms/site.json','{}');
require __DIR__.'/_testdb.php';
require __DIR__.'/../cms/wp/load.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
function out(callable $f): string { ob_start();try{ $f(); }finally{ $o=ob_get_clean(); }return $o; }
rrw_wp_boot(['user'=>['id'=>1,'login'=>'admin','name'=>'Administration','email'=>'a@example.test','role'=>'administrator']]);
// aktives Theme liegt im temporären Ordner (die Tests schreiben Vorlagendateien hinein)
mkdir(WP_CONTENT_DIR.'/themes/tpl-theme');file_put_contents(WP_CONTENT_DIR.'/themes/tpl-theme/style.css',"/*\nTheme Name: Tpl\n*/");
update_option('stylesheet','tpl-theme');update_option('template','tpl-theme');

/* ───── Vollständigkeit: alle Funktionen der Liste (keine Ausnahmen; No-ops/Standardwerte sind im Code kommentiert) ───── */
$names=preg_split('/\s+/',trim(<<<'LIST'
image_constrain_size_for_editor image_hwstring image_downsize has_image_size remove_image_size get_image_tag wp_constrain_dimensions _wp_get_attachment_relative_path _wp_get_image_size_from_meta wp_image_file_matches_image_meta
wp_image_src_get_dimensions wp_image_add_srcset_and_sizes wp_lazy_loading_enabled wp_img_tag_add_auto_sizes wp_sizes_attribute_includes_valid_auto wp_print_auto_sizes_contain_css_fix wp_img_tag_add_loading_optimization_attrs
wp_img_tag_add_width_and_height_attr wp_img_tag_add_srcset_and_sizes_attr wp_iframe_tag_add_loading_attr _wp_post_thumbnail_class_filter _wp_post_thumbnail_class_filter_add _wp_post_thumbnail_class_filter_remove
_wp_post_thumbnail_context_filter _wp_post_thumbnail_context_filter_add _wp_post_thumbnail_context_filter_remove img_caption_shortcode gallery_shortcode wp_underscore_playlist_templates wp_playlist_scripts wp_playlist_shortcode
wp_mediaelement_fallback wp_get_audio_extensions wp_get_attachment_id3_keys wp_audio_shortcode wp_get_video_extensions wp_video_shortcode get_previous_image_link previous_image_link get_next_image_link next_image_link
get_adjacent_image_link adjacent_image_link get_attachment_taxonomies get_taxonomies_for_attachments is_gd_image wp_imagecreatetruecolor wp_expand_dimensions _wp_image_editor_choose wp_plupload_default_settings
wp_prepare_attachment_for_js get_attached_media get_post_galleries get_post_galleries_images get_post_gallery_images wp_maybe_generate_attachment_metadata wpview_media_sandbox_styles wp_register_media_personal_data_exporter
wp_media_personal_data_exporter _wp_add_additional_image_sizes wp_show_heic_upload_error wp_get_avif_info wp_get_webp_info wp_get_loading_optimization_attributes wp_maybe_add_fetchpriority_high_attr
wp_get_image_editor_output_format media_upload_tabs update_gallery_tab the_media_upload_tabs get_image_send_to_editor image_add_caption _cleanup_image_add_caption media_send_to_editor wp_iframe media_buttons get_upload_iframe_src
media_upload_form_handler wp_media_upload_handler media_upload_gallery media_upload_library image_align_input_fields image_size_input_fields image_link_input_fields wp_caption_input_textarea image_attachment_fields_to_edit
media_single_attachment_fields_to_edit media_post_single_attachment_fields_to_edit image_media_send_to_editor get_attachment_fields_to_edit get_media_items get_media_item get_compat_media_markup media_upload_header
media_upload_form media_upload_type_form media_upload_type_url_form media_upload_gallery_form media_upload_library_form wp_media_insert_url_form media_upload_flash_bypass media_upload_html_bypass media_upload_text_after
media_upload_max_image_resize multisite_over_quota_message edit_form_image_editor attachment_submitbox_metadata wp_add_id3_tag_data wp_read_video_metadata wp_read_audio_metadata wp_get_media_creation_timestamp
wp_media_attach_action get_theme_roots register_theme_directory search_theme_directories locale_stylesheet validate_theme_requirements get_header_image_tag _get_random_header_data get_random_header_image is_random_header_image
header_image unregister_default_headers has_header_video get_header_video_url the_header_video_url get_header_video_settings is_header_video_active get_custom_header_markup background_image background_color wp_custom_css_cb
wp_get_custom_css_post wp_update_custom_css_post remove_editor_styles get_editor_stylesheets get_theme_starter_content _custom_header_background_just_in_time _custom_logo_header_styles _remove_theme_support
require_if_theme_supports register_theme_feature get_registered_theme_features get_registered_theme_feature _delete_attachment_theme_mod check_theme_switched _wp_customize_include _wp_customize_publish_changeset
_wp_customize_changeset_filter_insert_post_data _wp_customize_loader_settings wp_customize_support_script _wp_keep_alive_customize_changeset_dependent_auto_drafts create_initial_theme_features wp_theme_get_element_class_name
_add_default_theme_supports wp_register_tinymce_scripts wp_default_packages_vendor wp_get_script_polyfill wp_register_development_scripts wp_default_packages_scripts wp_default_packages_inline_scripts wp_tinymce_inline_scripts
wp_default_packages wp_default_scripts wp_default_styles wp_prototype_before_jquery wp_just_in_time_script_localization wp_localize_community_events wp_style_loader_src print_head_scripts print_footer_scripts _print_scripts
_wp_footer_scripts print_admin_styles print_late_styles _print_styles script_concat_settings wp_common_block_scripts_and_styles wp_filter_out_block_nodes wp_enqueue_global_styles wp_should_load_block_editor_scripts_and_styles
wp_should_load_block_assets_on_demand wp_enqueue_registered_block_scripts_and_styles enqueue_block_styles_assets enqueue_editor_block_styles_assets wp_enqueue_editor_block_directory_assets wp_enqueue_editor_format_library_assets
wp_sanitize_script_attributes wp_get_script_tag wp_print_script_tag wp_maybe_inline_styles _wp_normalize_relative_css_links wp_enqueue_global_styles_css_custom_properties wp_enqueue_block_support_styles wp_enqueue_stored_styles
wp_enqueue_classic_theme_styles wp_remove_surrounding_empty_script_tags wp_category_checklist wp_terms_checklist wp_popular_terms_checklist wp_link_category_checklist get_inline_data wp_comment_reply wp_comment_trashnotice
_list_meta_row meta_form touch_time page_template_dropdown parent_dropdown wp_dropdown_roles wp_import_upload_form do_block_editor_incompatible_meta_box _get_plugin_from_callback do_accordion_sections find_posts_div
the_post_password iframe_header iframe_footer get_post_states _media_states get_media_states compression_test _wp_admin_html_begin _local_storage_notice wp_star_rating _wp_posts_page_notice _wp_block_editor_posts_page_notice
wp_widget_description wp_sidebar_description wp_register_widget_control _register_widget_update_callback _register_widget_form_callback wp_unregister_widget_control is_dynamic_sidebar wp_get_widget_defaults
wp_convert_widget_settings _get_widget_id_base _wp_sidebars_changed retrieve_widgets wp_map_sidebars_widgets _wp_remove_unregistered_widgets wp_widget_rss_output wp_widget_rss_form wp_widget_rss_process wp_widgets_init
wp_setup_widgets_block_editor wp_use_widgets_block_editor wp_parse_widget_id wp_find_widgets_sidebar wp_assign_widget_to_sidebar wp_render_widget wp_render_widget_control wp_check_widget_editor_deps
_wp_block_theme_register_classic_sidebars get_index_template get_404_template get_archive_template get_post_type_archive_template get_author_template get_category_template get_tag_template get_taxonomy_template get_date_template
get_home_template get_front_page_template get_privacy_policy_template get_page_template get_search_template get_single_template get_embed_template get_singular_template get_attachment_template wp_set_template_globals wp_crop_image
wp_get_missing_image_subsizes wp_update_image_subsizes _wp_image_meta_replace_original wp_create_image_subsizes _wp_make_subsizes wp_copy_parent_attachment_properties wp_exif_frac2dec wp_exif_date2ts wp_read_image_metadata
file_is_valid_image file_is_displayable_image load_image_to_edit _load_image_to_edit_path _copy_image_file delete_theme _get_template_edit_filename theme_update_available get_theme_update_available get_theme_feature_list
wp_prepare_themes_for_js customize_themes_print_templates is_theme_paused wp_get_theme_error resume_theme paused_themes_notice wp_list_widgets _sort_name_callback wp_list_widget_controls wp_list_widget_controls_dynamic_sidebar
next_widget_id_number wp_widget_control wp_widgets_access_body_class wp_set_unique_slug_on_create_template_part wp_filter_wp_template_unique_post_slug wp_enqueue_block_template_skip_link wp_enable_block_templates
wp_get_theme_preview_path wp_attach_theme_preview_middleware wp_block_theme_activate_nonce wp_initialize_theme_preview_hooks wp_script_modules wp_deregister_script_module wp_default_script_modules wp_underscore_audio_template
wp_underscore_video_template
LIST));
$missing=array_values(array_filter($names,fn($f)=>!function_exists($f)));
t('alle '.count($names).' Funktionen der Liste existieren',$missing===[],implode(' ',$missing));

/* ───── Größen und Abmessungen ───── */
t('wp_constrain_dimensions verkleinert proportional',wp_constrain_dimensions(1000,500,300,300)===[300,150]);
t('wp_constrain_dimensions: nur Breite',wp_constrain_dimensions(400,200,100,0)===[100,50]);
t('wp_constrain_dimensions: nur Höhe',wp_constrain_dimensions(400,200,0,100)===[200,100]);
t('wp_constrain_dimensions vergrößert nie',wp_constrain_dimensions(100,50,300,300)===[100,50]);
t('wp_constrain_dimensions ohne Grenzen',wp_constrain_dimensions(100,50)===[100,50]);
t('wp_constrain_dimensions: Rundung auf Rahmen',wp_constrain_dimensions(465,700,177,177)===[118,177]);
t('wp_expand_dimensions',wp_expand_dimensions(100,50,400,400)===[400,200]&&wp_expand_dimensions(100,50)===[100,50]);
t('image_hwstring',image_hwstring(10,20)==='width="10" height="20" '&&image_hwstring(0,5)==='height="5" '&&image_hwstring(0,0)==='');
t('image_constrain_size_for_editor: thumbnail',image_constrain_size_for_editor(1200,800,'thumbnail')===[150,100]);
t('image_constrain_size_for_editor: full unbegrenzt',image_constrain_size_for_editor(1200,800,'full')===[1200,800]);
t('image_constrain_size_for_editor: Feldgröße',image_constrain_size_for_editor(1200,800,[600,600])===[600,400]);
add_image_size('eigen',200,200,true);
t('has_image_size/remove_image_size',has_image_size('eigen')&&!has_image_size('gibtsnicht')&&remove_image_size('eigen')&&!has_image_size('eigen')&&!remove_image_size('eigen'));
_wp_add_additional_image_sizes();
t('_wp_add_additional_image_sizes (1536/2048)',has_image_size('1536x1536')&&has_image_size('2048x2048'));
remove_image_size('1536x1536');remove_image_size('2048x2048');
t('_wp_get_attachment_relative_path',_wp_get_attachment_relative_path('2026/01/a.jpg')==='2026/01'&&_wp_get_attachment_relative_path('a.jpg')===''&&_wp_get_attachment_relative_path('/srv/wp-content/uploads/2026/02/b.jpg')==='2026/02');
$meta=['width'=>1200,'height'=>800,'file'=>'2026/01/foto.jpg','sizes'=>['thumbnail'=>['file'=>'foto-150x100.jpg','width'=>150,'height'=>100,'mime-type'=>'image/jpeg'],'medium'=>['file'=>'foto-300x200.jpg','width'=>300,'height'=>200,'mime-type'=>'image/jpeg'],'large'=>['file'=>'foto-1024x683.jpg','width'=>1024,'height'=>683,'mime-type'=>'image/jpeg']]];
t('_wp_get_image_size_from_meta',_wp_get_image_size_from_meta('full',$meta)===[1200,800]&&_wp_get_image_size_from_meta('medium',$meta)===[300,200]&&_wp_get_image_size_from_meta('xyz',$meta)===false);
t('wp_image_file_matches_image_meta',wp_image_file_matches_image_meta('http://x/uploads/2026/01/foto-300x200.jpg',$meta)&&!wp_image_file_matches_image_meta('http://x/uploads/2026/01/anders.jpg',$meta));
t('wp_image_src_get_dimensions',wp_image_src_get_dimensions('http://x/uploads/2026/01/foto.jpg',$meta)===[1200,800]&&wp_image_src_get_dimensions('http://x/uploads/2026/01/foto-150x100.jpg',$meta)===[150,100]&&wp_image_src_get_dimensions('http://x/a/anders.jpg',$meta)===false);

/* ───── Anhang mit echtem Bild: Zwischengrößen, image_downsize, Galerie ───── */
$up=WP_CONTENT_DIR.'/uploads/2026/01';mkdir($up,0775,true);
$im=imagecreatetruecolor(1200,800);imagefill($im,0,0,imagecolorallocate($im,200,50,50));imagejpeg($im,$up.'/foto.jpg',90);
$aid=wp_insert_attachment(['post_mime_type'=>'image/jpeg','post_title'=>'Foto','post_status'=>'inherit','post_excerpt'=>'Bildunterschrift'],'2026/01/foto.jpg',0);
t('Anhang angelegt',is_int($aid)&&$aid>0&&get_post($aid)->post_type==='attachment');
$m=wp_create_image_subsizes($up.'/foto.jpg',$aid);
t('wp_create_image_subsizes: Metadaten',is_array($m)&&$m['width']===1200&&$m['height']===800&&$m['file']==='2026/01/foto.jpg');
t('wp_create_image_subsizes: Größen erzeugt',isset($m['sizes']['thumbnail'],$m['sizes']['medium'])&&is_file($up.'/'.$m['sizes']['thumbnail']['file']));
t('Zuschnitt thumbnail 150x150',$m['sizes']['thumbnail']['width']===150&&$m['sizes']['thumbnail']['height']===150);
t('Größe medium proportional',$m['sizes']['medium']['width']===300&&$m['sizes']['medium']['height']===200);
t('Metadaten gespeichert',wp_get_attachment_metadata($aid)['sizes']['medium']['file']===$m['sizes']['medium']['file']);
t('wp_get_missing_image_subsizes (vollständig)',wp_get_missing_image_subsizes($aid)===[]);
$mm=wp_get_attachment_metadata($aid);unset($mm['sizes']['medium']);wp_update_attachment_metadata($aid,$mm);
t('wp_get_missing_image_subsizes (fehlt medium)',array_keys(wp_get_missing_image_subsizes($aid))===['medium']);
$m2=wp_update_image_subsizes($aid);
t('wp_update_image_subsizes ergänzt',isset($m2['sizes']['medium'])&&is_file($up.'/'.$m2['sizes']['medium']['file']));
$d=image_downsize($aid,'medium');
t('image_downsize: Zwischengröße',is_array($d)&&str_ends_with($d[0],'-300x200.jpg')&&$d[1]===300&&$d[2]===200&&$d[3]===true);
$d=image_downsize($aid,'full');
t('image_downsize: full',str_ends_with($d[0],'/foto.jpg')&&$d[1]===1200&&$d[3]===false);
t('image_downsize: unbekannte Anhangs-ID',image_downsize(999999,'full')===false);
$tag=get_image_tag($aid,'Alt','Titel','left','medium');
t('get_image_tag',str_contains($tag,'width="300" height="200"')&&str_contains($tag,'class="alignleft size-medium wp-image-'.$aid.'"')&&str_contains($tag,'alt="Alt"')&&str_contains($tag,'title="Titel"'));
t('_rrw_m_intermediate mit Feldgröße',str_ends_with(_rrw_m_intermediate($aid,[250,150])['url'],'-300x200.jpg'));
$sizes=wp_get_attachment_metadata($aid);
$img='<img src="'.$d[0].'" width="300" height="200" alt="">';
$img=str_replace('foto.jpg','foto-300x200.jpg',$img);
$r=wp_image_add_srcset_and_sizes($img,$sizes,$aid);
t('wp_image_add_srcset_and_sizes: srcset',str_contains($r,'srcset="')&&str_contains($r,'foto-300x200.jpg 300w')&&str_contains($r,'foto.jpg 1200w'));
t('wp_image_add_srcset_and_sizes: sizes',str_contains($r,'sizes="(max-width: 300px) 100vw, 300px"'));
t('wp_image_add_srcset_and_sizes: ohne Metadaten unverändert',wp_image_add_srcset_and_sizes($img,[],0)===$img);
t('wp_img_tag_add_srcset_and_sizes_attr (vorhandenes srcset bleibt)',wp_img_tag_add_srcset_and_sizes_attr('<img src="a.jpg" srcset="x 1w">','the_content',$aid)==='<img src="a.jpg" srcset="x 1w">');
$noW='<img src="'.str_replace('foto.jpg','foto-300x200.jpg',$d[0]).'" alt="">';
t('wp_img_tag_add_width_and_height_attr',str_contains(wp_img_tag_add_width_and_height_attr($noW,'the_content',$aid),'width="300" height="200"'));
t('wp_img_tag_add_width_and_height_attr: vorhanden → unverändert',wp_img_tag_add_width_and_height_attr('<img src="a.jpg" width="1" height="2">','the_content',$aid)==='<img src="a.jpg" width="1" height="2">');

/* ───── Lazy-Loading, auto-sizes ───── */
$GLOBALS['_rrw_m_media_count']=0;$GLOBALS['_rrw_m_high_flag']=false;
t('wp_lazy_loading_enabled',wp_lazy_loading_enabled('img','the_content')&&wp_lazy_loading_enabled('iframe','x')&&!wp_lazy_loading_enabled('video','x'));
$o1=wp_img_tag_add_loading_optimization_attrs('<img src="a.jpg" width="100" height="100">','the_content');
t('erstes kleines Bild: nicht lazy, decoding async',!str_contains($o1,'loading=')&&str_contains($o1,'decoding="async"'));
wp_img_tag_add_loading_optimization_attrs('<img src="b.jpg" width="10" height="10">','the_content');
wp_img_tag_add_loading_optimization_attrs('<img src="c.jpg" width="10" height="10">','the_content');
$o4=wp_img_tag_add_loading_optimization_attrs('<img src="d.jpg" width="10" height="10">','the_content');
t('viertes Bild: loading=lazy',str_contains($o4,'loading="lazy"'));
$GLOBALS['_rrw_m_media_count']=0;
t('ausdrücklich loading=eager bleibt',!str_contains(wp_img_tag_add_loading_optimization_attrs('<img src="a.jpg" loading="eager">','x'),'loading="lazy"'));
t('Bild ohne src unverändert',wp_img_tag_add_loading_optimization_attrs('<img alt="x">','x')==='<img alt="x">');
$GLOBALS['_rrw_m_media_count']=0;$GLOBALS['_rrw_m_high_flag']=false;
$big=wp_get_loading_optimization_attributes('img',['width'=>800,'height'=>600],'the_content');
t('großes erstes Bild: fetchpriority=high ohne lazy',($big['fetchpriority']??'')==='high'&&!isset($big['loading']));
$big2=wp_get_loading_optimization_attributes('img',['width'=>800,'height'=>600],'the_content');
t('nur ein Bild mit fetchpriority=high',!isset($big2['fetchpriority']));
t('wp_maybe_add_fetchpriority_high_attr: Nicht-Bild',wp_maybe_add_fetchpriority_high_attr(['loading'=>'lazy'],'iframe',['width'=>9999,'height'=>9999])===['loading'=>'lazy']);
$GLOBALS['_rrw_m_media_count']=9;
t('iframe: lazy nach Schwelle',str_contains(wp_iframe_tag_add_loading_attr('<iframe src="https://e.test/v" width="10" height="10"></iframe>','the_content'),'loading="lazy"'));
t('iframe: vorhandenes loading bleibt',wp_iframe_tag_add_loading_attr('<iframe src="x" loading="eager"></iframe>','c')==='<iframe src="x" loading="eager"></iframe>');
$GLOBALS['_rrw_m_media_count']=0;
t('wp_img_tag_add_auto_sizes: lazy ohne sizes',str_contains(wp_img_tag_add_auto_sizes('<img src="a.jpg" loading="lazy">'),'sizes="auto"'));
t('wp_img_tag_add_auto_sizes: bestehendes sizes',str_contains(wp_img_tag_add_auto_sizes('<img src="a.jpg" loading="lazy" sizes="100vw">'),'sizes="auto, 100vw"'));
t('wp_img_tag_add_auto_sizes: nicht lazy unverändert',wp_img_tag_add_auto_sizes('<img src="a.jpg">')==='<img src="a.jpg">');
t('wp_sizes_attribute_includes_valid_auto',wp_sizes_attribute_includes_valid_auto('auto, 100vw')&&wp_sizes_attribute_includes_valid_auto(' AUTO')&&!wp_sizes_attribute_includes_valid_auto('100vw, auto'));
t('wp_print_auto_sizes_contain_css_fix',str_contains(out('wp_print_auto_sizes_contain_css_fix'),'contain-intrinsic-size: 3000px 1500px'));

/* ───── Beitragsbild-Haken, GD, Bildformate ───── */
t('_wp_post_thumbnail_class_filter',_wp_post_thumbnail_class_filter(['class'=>'a'])['class']==='a wp-post-image'&&_wp_post_thumbnail_class_filter([])['class']==='wp-post-image');
_wp_post_thumbnail_class_filter_add([]);
t('class_filter add/remove',has_filter('wp_get_attachment_image_attributes','_wp_post_thumbnail_class_filter')!==false);
_wp_post_thumbnail_class_filter_remove([]);
t('class_filter entfernt',has_filter('wp_get_attachment_image_attributes','_wp_post_thumbnail_class_filter')===false);
_wp_post_thumbnail_context_filter_add();
t('context_filter',has_filter('wp_get_attachment_image_context','_wp_post_thumbnail_context_filter')!==false&&_wp_post_thumbnail_context_filter('x')==='the_post_thumbnail');
_wp_post_thumbnail_context_filter_remove();
$gd=wp_imagecreatetruecolor(10,10);
t('is_gd_image / wp_imagecreatetruecolor',is_gd_image($gd)&&!is_gd_image('x')&&!is_gd_image(null));
t('_wp_image_editor_choose ohne Editor-Klassen',_wp_image_editor_choose()===false);
t('wp_get_image_editor_output_format (HEIC → JPEG)',wp_get_image_editor_output_format('a.heic','image/heic')['image/heic']==='image/jpeg');
t('wp_show_heic_upload_error',wp_show_heic_upload_error(['a'=>1])===['a'=>1,'heic_upload_error'=>true]);
$webp=$tmp.'/t.webp';
if(function_exists('imagewebp')){ imagewebp($gd,$webp);$wi=wp_get_webp_info($webp);t('wp_get_webp_info',$wi['width']===10&&$wi['height']===10&&in_array($wi['type'],['lossy','lossless'],true)); }
else t('wp_get_webp_info (ohne GD-WebP übersprungen)',true);
t('wp_get_webp_info: keine WebP-Datei',wp_get_webp_info(__FILE__)===['width'=>false,'height'=>false,'type'=>false]);
t('wp_get_avif_info: keine AVIF-Datei',wp_get_avif_info(__FILE__)===false&&wp_get_avif_info('/gibt/es/nicht')===false);
$avif=$tmp.'/t.avif';file_put_contents($avif,pack('N',24).'ftypavif'.str_repeat("\0",8).pack('N',20).'ispe'.pack('N',0).pack('N',640).pack('N',480).'pixi'.pack('N',0)."\x03\x08\x08\x08");
$ai=wp_get_avif_info($avif);t('wp_get_avif_info: Maße',$ai&&$ai['width']===640&&$ai['height']===480&&$ai['num_channels']===3&&$ai['bit_depth']===8);

/* ───── Bild-Hilfen der Verwaltung ───── */
t('file_is_valid_image / displayable',file_is_valid_image($up.'/foto.jpg')&&file_is_displayable_image($up.'/foto.jpg')&&!file_is_valid_image(__FILE__)&&!file_is_displayable_image(__FILE__));
t('wp_exif_frac2dec',wp_exif_frac2dec('1/4')===0.25&&wp_exif_frac2dec('3')===3.0&&wp_exif_frac2dec('1/0')===0.0&&wp_exif_frac2dec(true)===0);
t('wp_exif_date2ts',wp_exif_date2ts('2026:01:02 03:04:05')===gmmktime(3,4,5,1,2,2026)&&wp_exif_date2ts('Unsinn')===false);
$rim=wp_read_image_metadata($up.'/foto.jpg');
t('wp_read_image_metadata: Grundstruktur',is_array($rim)&&$rim['keywords']===[]&&$rim['aperture']==='0'&&array_key_exists('created_timestamp',$rim)&&wp_read_image_metadata('/gibt/es/nicht')===false);
t('_load_image_to_edit_path',_load_image_to_edit_path($aid)===$up.'/foto.jpg'&&str_ends_with((string)_load_image_to_edit_path($aid,'medium'),'-300x200.jpg'));
$li=load_image_to_edit($aid,'image/jpeg');t('load_image_to_edit',is_gd_image($li)&&imagesx($li)===1200);
$cp=wp_crop_image($aid,100,100,400,300,200,150);
t('wp_crop_image',is_string($cp)&&is_file($cp)&&getimagesize($cp)[0]===200&&getimagesize($cp)[1]===150);
t('wp_crop_image: kein Bild',is_wp_error(wp_crop_image(__FILE__,0,0,1,1,1,1)));
$cf=_copy_image_file($aid);t('_copy_image_file',is_string($cf)&&is_file($cf)&&str_contains(basename($cf),'copy-foto'));
$prop=wp_copy_parent_attachment_properties($cp,$aid,'custom-header');
t('wp_copy_parent_attachment_properties',$prop['post_mime_type']==='image/jpeg'&&$prop['context']==='custom-header'&&$prop['post_title']===basename($cp)&&str_ends_with($prop['guid'],basename($cp)));
$sc=_wp_image_meta_replace_original(['path'=>$up.'/foto-scaled.jpg','width'=>800,'height'=>533,'filesize'=>1234],$up.'/foto.jpg',['sizes'=>[],'file'=>'x'],$aid);
t('_wp_image_meta_replace_original',$sc['width']===800&&$sc['original_image']==='foto.jpg'&&$sc['file']==='2026/01/foto-scaled.jpg'&&$sc['filesize']===1234);
$mk=_wp_make_subsizes(['kl'=>['width'=>50,'height'=>50,'crop'=>false]],$up.'/foto.jpg',['width'=>1200,'height'=>800,'file'=>'2026/01/foto.jpg','sizes'=>[]],0);
t('_wp_make_subsizes',$mk['sizes']['kl']['width']===50&&$mk['sizes']['kl']['height']===33&&is_wp_error(_wp_make_subsizes([],'x',[],0)));
$big=imagecreatetruecolor(3000,1000);imagejpeg($big,$up.'/gross.jpg');
$aid2=wp_insert_attachment(['post_mime_type'=>'image/jpeg','post_title'=>'Gross','post_status'=>'inherit'],'2026/01/gross.jpg',0);
$m3=wp_create_image_subsizes($up.'/gross.jpg',$aid2);
t('große Bilder werden auf 2560 verkleinert (-scaled)',$m3['width']===2560&&$m3['original_image']==='gross.jpg'&&str_contains($m3['file'],'gross-scaled.jpg')&&is_file($up.'/gross-scaled.jpg'));

/* ───── Anhänge, Galerie, Schnittstelle ───── */
wp_update_post(['ID'=>$aid,'post_parent'=>0]);
$pid=wp_insert_post(['post_title'=>'Mit Bildern','post_status'=>'publish','post_content'=>'[gallery ids="'.$aid.','.$aid2.'"]']);
wp_update_post(['ID'=>$aid,'post_parent'=>$pid]);wp_update_post(['ID'=>$aid2,'post_parent'=>$pid]);
$audio=wp_insert_attachment(['post_mime_type'=>'audio/mpeg','post_title'=>'Lied','post_status'=>'inherit','post_parent'=>$pid],'2026/01/lied.mp3',$pid);
t('get_attached_media nach Typ (ID-Karte, Reihenfolge)',array_keys(get_attached_media('image',$pid))===[$aid,$aid2]);
t('get_attached_media: Audio',count(get_attached_media('audio',$pid))===1&&count(get_attached_media('video',$pid))===0);
$g=gallery_shortcode(['ids'=>"$aid,$aid2",'id'=>$pid]);
t('gallery_shortcode: Struktur',str_contains($g,"class='gallery galleryid-$pid gallery-columns-3 gallery-size-thumbnail'")&&substr_count($g,"class='gallery-item'")===2&&str_contains($g,'gallery-icon landscape'));
t('gallery_shortcode: Beschriftung',str_contains($g,'Bildunterschrift')&&(bool)preg_match("/gallery-caption' id='gallery-\d+-$aid'/",$g));
t('gallery_shortcode: leere Galerie',gallery_shortcode(['id'=>999999])==='');
$gs=gallery_shortcode(['ids'=>(string)$aid,'columns'=>'2','link'=>'none']);
t('gallery_shortcode: Spalten + link=none',str_contains($gs,'gallery-columns-2')&&!str_contains($gs,'<a '));
$GLOBALS['post']=get_post($pid);setup_postdata($GLOBALS['post']);
$gal=get_post_galleries($pid,false);
t('get_post_galleries (Quelle)',count($gal)===1&&count($gal[0]['src'])===2&&isset($gal[0]['ids']));
t('get_post_galleries (HTML)',count(get_post_galleries($pid))===1&&str_contains(get_post_galleries($pid)[0],'gallery-item'));
t('get_post_galleries_images',count(get_post_galleries_images($pid))===1&&count(get_post_galleries_images($pid)[0])===2);
t('get_post_gallery_images',count(get_post_gallery_images($pid))===2&&get_post_gallery_images(wp_insert_post(['post_title'=>'leer','post_status'=>'publish']))===[]);
t('get_post_galleries: Beitrag ohne Galerie',get_post_galleries(wp_insert_post(['post_title'=>'x2','post_status'=>'publish','post_content'=>'Text']))===[]);
$cap=img_caption_shortcode(['id'=>'attachment_5','align'=>'aligncenter','width'=>'300','caption'=>'Hallo'],'<img src="a.jpg" />');
t('img_caption_shortcode (HTML4)',str_contains($cap,'<div id="attachment_5" style="width: 310px" class="wp-caption aligncenter">')&&str_contains($cap,'<p class="wp-caption-text">Hallo</p>'));
add_theme_support('html5',['caption']);
$cap5=img_caption_shortcode(['id'=>'attachment_5','width'=>'300','caption'=>'Hallo'],'<img src="a.jpg" />');
t('img_caption_shortcode (HTML5)',str_contains($cap5,'<figure id="attachment_5" aria-describedby="figcaption_attachment_5" style="width: 300px"')&&str_contains($cap5,'<figcaption id="figcaption_attachment_5" class="wp-caption-text">Hallo</figcaption>'));
remove_theme_support('html5');
t('img_caption_shortcode: ohne Beschriftung/Breite → Inhalt',img_caption_shortcode(['width'=>'300'],'X')==='X'&&img_caption_shortcode(['caption'=>'c'],'Y')==='Y');
$js=wp_prepare_attachment_for_js($aid);
t('wp_prepare_attachment_for_js',$js['id']===$aid&&$js['type']==='image'&&$js['subtype']==='jpeg'&&$js['width']===1200&&isset($js['sizes']['medium'],$js['sizes']['full'])&&$js['sizes']['medium']['orientation']==='landscape'&&is_string($js['nonces']['update']));
t('wp_prepare_attachment_for_js: kein Anhang',wp_prepare_attachment_for_js($pid)===null);
t('get_attachment_taxonomies / for_attachments',is_array(get_attachment_taxonomies($aid))&&is_array(get_taxonomies_for_attachments()));
register_taxonomy('medienart','attachment',['public'=>true]);
t('get_taxonomies_for_attachments mit Taxonomie',in_array('medienart',get_taxonomies_for_attachments(),true)&&in_array('medienart',get_attachment_taxonomies($aid),true));
$GLOBALS['post']=get_post($aid);
t('get_adjacent_image_link: Nachbar',str_contains(get_next_image_link('thumbnail'),'<a ')&&get_previous_image_link()==='');
t('previous/next_image_link geben aus',out(fn()=>next_image_link())!==''&&out(fn()=>previous_image_link())==='');
$exp=wp_media_personal_data_exporter('gibt@es.nicht');
t('wp_media_personal_data_exporter: unbekannte Person',$exp==['data'=>[],'done'=>true]);
t('wp_register_media_personal_data_exporter',wp_register_media_personal_data_exporter([])['wordpress-media']['callback']==='wp_media_personal_data_exporter');
t('wpview_media_sandbox_styles',count(wpview_media_sandbox_styles())===2&&str_contains(wpview_media_sandbox_styles()[0],'mediaelementplayer-legacy.min.css'));
t('wp_maybe_generate_attachment_metadata (ohne Fehler)',(function() use($aid){ wp_maybe_generate_attachment_metadata(get_post($aid));return true; })());
t('wp_plupload_default_settings',(function(){ wp_plupload_default_settings();return true; })());

/* ───── Shortcodes: Audio, Video, Wiedergabeliste ───── */
t('wp_get_audio_extensions / video',in_array('mp3',wp_get_audio_extensions(),true)&&in_array('mp4',wp_get_video_extensions(),true));
t('wp_mediaelement_fallback',wp_mediaelement_fallback('http://x/a.mp3')==='<a href="http://x/a.mp3">http://x/a.mp3</a>');
t('wp_get_attachment_id3_keys',array_keys(wp_get_attachment_id3_keys(null))===['artist','album','genre','year','length_formatted']&&isset(wp_get_attachment_id3_keys(null,'js')['bitrate']));
$a=wp_audio_shortcode(['src'=>'http://x/a.mp3','loop'=>'on']);
t('wp_audio_shortcode',str_contains($a,'<audio class="wp-audio-shortcode"')&&str_contains($a,'loop="1"')&&str_contains($a,'<source type="audio/mpeg" src="http://x/a.mp3?_=')&&str_contains($a,'</audio>'));
t('wp_audio_shortcode: fremder Typ → Link',str_contains(wp_audio_shortcode(['src'=>'http://x/a.pdf']),'class="wp-embedded-audio"'));
$v=wp_video_shortcode(['src'=>'http://x/v.mp4','width'=>'400','height'=>'200','poster'=>'http://x/p.jpg']);
t('wp_video_shortcode',str_contains($v,'<div style="width: 400px;" class="wp-video">')&&str_contains($v,'width="400"')&&str_contains($v,'poster="http://x/p.jpg"')&&str_contains($v,'type="video/mp4"'));
t('wp_video_shortcode: YouTube',str_contains(wp_video_shortcode(['src'=>'http://www.youtube.com/watch?v=abc&feature=x']),'type="video/youtube"')&&!str_contains(wp_video_shortcode(['src'=>'http://www.youtube.com/watch?v=abc&feature=x']),'feature='));
t('wp_video_shortcode: fremder Typ → Link',str_contains(wp_video_shortcode(['src'=>'http://x/v.txt']),'wp-embedded-video'));
$GLOBALS['post']=get_post($pid);
$pl=wp_playlist_shortcode(['type'=>'audio','id'=>$pid]);
t('wp_playlist_shortcode',str_contains($pl,'wp-playlist wp-audio-playlist wp-playlist-light')&&str_contains($pl,'<script type="application/json" class="wp-playlist-script">')&&str_contains($pl,'"type":"audio"')&&str_contains($pl,'Lied'));
t('wp_playlist_shortcode: ohne Medien leer',wp_playlist_shortcode(['type'=>'video','id'=>$pid])==='');
wp_register_script('wp-playlist','p.js');wp_register_style('wp-mediaelement','m.css');
wp_playlist_scripts('audio');
t('wp_playlist_scripts bindet Skript und Vorlagen ein',wp_script_is('wp-playlist','enqueued')&&has_action('wp_footer','wp_underscore_playlist_templates')!==false);
t('wp_underscore_playlist_templates',str_contains(out('wp_underscore_playlist_templates'),'id="tmpl-wp-playlist-item"'));
t('wp_underscore_audio/video_template',str_contains(out('wp_underscore_audio_template'),'<audio')&&str_contains(out('wp_underscore_video_template'),'<video')&&str_contains(out('wp_underscore_audio_template'),'data.model.mp3'));
t('Gallery/Playlist-Shortcodes sind (nach init) eingetragen',shortcode_exists('gallery')&&shortcode_exists('playlist'));
t('[gallery] über do_shortcode',str_contains(do_shortcode('[gallery ids="'.$aid.'"]'),'gallery-item'));

/* ───── Verwaltung: Einfügen, Felder, Tabs ───── */
$_REQUEST['post_id']=(string)$pid;
t('media_upload_tabs',array_keys(media_upload_tabs())===['type','type_url','gallery','library']);
t('update_gallery_tab: mit Anhängen',isset(update_gallery_tab(media_upload_tabs())['gallery'])&&str_contains(update_gallery_tab(media_upload_tabs())['gallery'],'attachments-count'));
unset($_REQUEST['post_id']);
t('update_gallery_tab: ohne post_id',!isset(update_gallery_tab(media_upload_tabs())['gallery']));
t('the_media_upload_tabs',str_contains(out('the_media_upload_tabs'),"<li id='tab-type'>"));
t('get_upload_iframe_src',str_contains(get_upload_iframe_src('image',7),'post_id=7')&&str_contains(get_upload_iframe_src('image',7),'type=image')&&str_contains(get_upload_iframe_src('image',7),'TB_iframe=1'));
t('media_buttons',str_contains(out(fn()=>media_buttons('content')),'data-editor="content"'));
t('get_image_send_to_editor mit Link und rel',(function() use($aid){ $h=get_image_send_to_editor($aid,'','T','center','http://x/seite',true,'medium','Alt');return str_starts_with($h,'<a href="http://x/seite" rel="attachment wp-att-'.$aid.'"><img ')&&str_contains($h,'aligncenter'); })());
t('image_add_caption',image_add_caption('<img width="300" class="alignleft wp-image-1" src="a.jpg" />',5,"Zeile 1\nZeile 2",'T','left','','medium')==='[caption id="attachment_5" align="alignleft" width="300"]<img width="300" class="wp-image-1" src="a.jpg" /> Zeile 1<br />Zeile 2[/caption]');
t('image_add_caption: leere Beschriftung / ohne Breite',image_add_caption('<img width="3" />',1,'','','','','')==='<img width="3" />'&&image_add_caption('<img />',1,'c','','','','')==='<img />');
t('_cleanup_image_add_caption',_cleanup_image_add_caption(["<a\nhref='x'>"])==="<a href='x'>");
t('get_image_send_to_editor ruft image_add_caption (Haken)',str_contains(get_image_send_to_editor($aid,'Unterschrift','','none','',false,'medium','a'),'[caption id="attachment_'.$aid.'"'));
t('media_send_to_editor',str_contains(out(fn()=>media_send_to_editor('<b>x</b>')),'win.send_to_editor("<b>x<\/b>");'));
$post=get_post($aid);
t('image_align_input_fields',str_contains(image_align_input_fields($post,'center'),"value='center' checked='checked'")&&str_contains(image_align_input_fields($post,'unsinn'),"value='none' checked='checked'"));
$sz=image_size_input_fields($post,'medium');
t('image_size_input_fields',$sz['input']==='html'&&str_contains($sz['html'],"value='medium' checked='checked'")&&str_contains($sz['html'],'(300&nbsp;&times;&nbsp;200)'));
t('image_link_input_fields',str_contains(image_link_input_fields($post,'file'),"value='".wp_get_attachment_url($aid)."'")&&str_contains(image_link_input_fields($post,'none'),"value=''"));
t('wp_caption_input_textarea',wp_caption_input_textarea($post)==='<textarea name="attachments['.$aid.'][post_excerpt]" id="attachments['.$aid.'][post_excerpt]">Bildunterschrift</textarea>');
$ff=get_attachment_fields_to_edit($aid);
t('get_attachment_fields_to_edit',isset($ff['post_title'],$ff['image_alt'],$ff['align'],$ff['image-size'],$ff['url'])&&$ff['post_title']['value']==='Foto'&&!isset(get_attachment_fields_to_edit($audio)['image_alt']));
t('image_attachment_fields_to_edit / single / post_single',image_attachment_fields_to_edit($ff,$post)===$ff&&!isset(media_single_attachment_fields_to_edit($ff,$post)['url'])&&!isset(media_post_single_attachment_fields_to_edit($ff,$post)['image_url']));
t('image_media_send_to_editor: Bild und Nicht-Bild',str_contains(image_media_send_to_editor('X',$aid,['url'=>'','post_excerpt'=>'','post_title'=>'T']),'<img ')&&image_media_send_to_editor('X',$audio,[])==='X');
$mi=get_media_item($aid);
t('get_media_item',is_string($mi)&&str_contains($mi,'Foto')&&str_contains($mi,'name="attachments['.$aid.'][post_title]"')&&str_contains($mi,'name="send['.$aid.']"'));
t('get_media_items',str_contains(get_media_items($pid,null),"id='media-item-$aid'")&&str_contains(get_media_items($pid,null),"child-of-$pid"));
$cm=get_compat_media_markup($aid);
t('get_compat_media_markup',array_keys($cm)===['item','meta']&&str_contains($cm['item'],'compat-attachment-fields'));
t('media_upload_header / form',str_contains(out(fn()=>media_upload_header()),'media-upload-header')&&str_contains(out(fn()=>media_upload_form()),'async-upload'));
t('media_upload_type_form / url_form',str_contains(out(fn()=>media_upload_type_form('image')),'id="image-form"')&&str_contains(out(fn()=>media_upload_type_url_form('image')),'insertonlybutton'));
t('wp_media_insert_url_form',str_contains(wp_media_insert_url_form('audio'),'id="audio-only" checked="checked"')&&str_contains(wp_media_insert_url_form(),'name="src"'));
t('media_upload_gallery_form / library_form',str_contains(out(fn()=>media_upload_gallery_form(null)),'gallery-form')&&str_contains(out(fn()=>media_upload_library_form(null)),'library-form'));
t('Upload-Hinweise',str_contains(out('media_upload_text_after'),'after-file-upload')&&str_contains(out('media_upload_flash_bypass'),'upload-flash-bypass')&&str_contains(out('media_upload_html_bypass'),'upload-html-bypass')&&str_contains(out('media_upload_max_image_resize'),'image_resize')&&str_contains(out('multisite_over_quota_message'),'<p>'));
t('wp_iframe ruft die Funktion auf',str_contains(out(fn()=>wp_iframe(fn($x)=>print("INHALT-$x"),'ok')),'INHALT-ok')&&str_contains(out(fn()=>wp_iframe(fn()=>print('')),),'<!DOCTYPE html>'));
t('media_upload_form_handler / wp_media_upload_handler / gallery / library vorhanden',is_callable('media_upload_form_handler')&&is_callable('wp_media_upload_handler')&&is_callable('media_upload_gallery')&&is_callable('media_upload_library'));
$_POST=['html'=>1,'insertonlybutton'=>'1','src'=>'example.test/b.png','alt'=>'A','align'=>'left','media_type'=>'image'];
t('wp_media_upload_handler: URL einfügen',str_contains(out('wp_media_upload_handler'),'send_to_editor'));
$_POST=[];
t('edit_form_image_editor / attachment_submitbox_metadata',str_contains(out(fn()=>edit_form_image_editor($post)),'name="_wp_attachment_image_alt"')&&(function() use($aid){ $GLOBALS['post']=get_post($aid);return str_contains(out('attachment_submitbox_metadata'),'misc-pub-filename'); })());
t('wp_media_attach_action',(function() use($aid,$pid){ $_REQUEST['media']=[$aid];$r=wp_media_attach_action($pid,'detach');$p=get_post($aid)->post_parent;wp_media_attach_action($pid,'attach');unset($_REQUEST['media']);return $r!==false&&$p===0&&(int)get_post($aid)->post_parent===$pid; })());

/* ───── Audio-/Video-Metadaten ───── */
$mp4=$tmp.'/t.mp4';
$mvhd=pack('N',108).'mvhd'.pack('N',0).pack('N',3600000000).pack('N',3600000000).pack('N',1000).pack('N',65000).str_repeat("\0",80);
$tkhd=pack('N',92).'tkhd'.pack('N',0).str_repeat("\0",72).pack('N',640<<16).pack('N',360<<16);
$trak=pack('N',8+strlen($tkhd)).'trak'.$tkhd;$moov=pack('N',8+strlen($mvhd)+strlen($trak)).'moov'.$mvhd.$trak;
file_put_contents($mp4,pack('N',16).'ftypmp42'.pack('N',0).$moov);
$vm=wp_read_video_metadata($mp4);
t('wp_read_video_metadata (MP4)',$vm['width']===640&&$vm['height']===360&&$vm['length']===65&&$vm['length_formatted']==='1:05'&&$vm['fileformat']==='mp4'&&$vm['mime_type']==='video/mp4');
t('wp_read_video_metadata: fehlende Datei',wp_read_video_metadata('/gibt/es/nicht.mp4')===false);
t('wp_get_media_creation_timestamp',wp_get_media_creation_timestamp($vm)===3600000000-2082844800&&wp_get_media_creation_timestamp(['matroska'=>['comments'=>['creation_time'=>['2026-01-02 03:04:05 UTC']]]])===gmmktime(3,4,5,1,2,2026)&&wp_get_media_creation_timestamp([])===false);
$mp3=$tmp.'/t.mp3';
$fr=pack('N',0xFFFB9064).str_repeat("\0",413);   // MPEG1 Layer3, 128 kbit/s
$txt=fn($id,$s)=>$id.pack('N',strlen($s)+1)."\0\0\3".$s;   // UTF-8
$body=$txt('TIT2','Mein Lied').$txt('TPE1','Die Band').$txt('TALB','Album X');
file_put_contents($mp3,'ID3'."\3\0\0".pack('N',(((strlen($body)>>21)&0x7f)<<24)|(((strlen($body)>>14)&0x7f)<<16)|(((strlen($body)>>7)&0x7f)<<8)|(strlen($body)&0x7f)).$body.str_repeat($fr,100));
$am=wp_read_audio_metadata($mp3);
t('wp_read_audio_metadata: ID3v2-Felder',$am['title']==='Mein Lied'&&$am['artist']==='Die Band'&&$am['album']==='Album X'&&$am['fileformat']==='mp3');
t('wp_read_audio_metadata: Dauer',($am['bitrate']??0)===128000&&isset($am['length'])&&$am['length']>=3&&$am['length']<=4);
t('wp_read_audio_metadata: fehlende Datei',wp_read_audio_metadata('/nein.mp3')===false);
$md=[];wp_add_id3_tag_data($md,['id3v1'=>['comments'=>['title'=>['<b>T</b><script>x</script>']]]]);
t('wp_add_id3_tag_data (gefiltert)',$md['title']==='<b>T</b>x');
$md=[];wp_add_id3_tag_data($md,['id3v2'=>['comments'=>['artist'=>['A']]],'id3v1'=>['comments'=>['artist'=>['B']]]]);
t('wp_add_id3_tag_data: id3v2 hat Vorrang',$md['artist']==='A');

/* ───── Theme: Verzeichnisse, Anforderungen ───── */
$th=WP_CONTENT_DIR.'/themes';mkdir($th.'/mein-theme');file_put_contents($th.'/mein-theme/style.css',"/*\nTheme Name: Mein Theme\nVersion: 1.0\nRequires at least: 5.0\nRequires PHP: 7.0\n*/");
mkdir($th.'/neu-theme');file_put_contents($th.'/neu-theme/style.css',"/*\nTheme Name: Zu neu\nVersion: 1.0\nRequires at least: 99.0\nRequires PHP: 99.0\n*/");
mkdir($th.'/nur-wp');file_put_contents($th.'/nur-wp/style.css',"/*\nTheme Name: Nur WP\nRequires at least: 99.0\n*/");
mkdir($th.'/nur-php');file_put_contents($th.'/nur-php/style.css',"/*\nTheme Name: Nur PHP\nRequires PHP: 99.0\n*/");
mkdir($th.'/gruppe/kind',0775,true);file_put_contents($th.'/gruppe/kind/style.css',"/*\nTheme Name: Kind\n*/");
$st=search_theme_directories();
t('search_theme_directories',isset($st['mein-theme'])&&$st['mein-theme']['theme_file']==='mein-theme/style.css'&&$st['mein-theme']['theme_root']===$th&&isset($st['gruppe/kind']));
t('search_theme_directories findet CMS-Themes',isset($st['rrw-classic'])||isset($st['clean-air']));
t('get_theme_roots (ein Verzeichnis)',get_theme_roots()==='/themes');
mkdir($tmp.'/extra-themes');
t('register_theme_directory',register_theme_directory($tmp.'/extra-themes')===true&&register_theme_directory('/gibt/es/nicht')===false&&in_array($tmp.'/extra-themes',$GLOBALS['wp_theme_directories'],true));
register_theme_directory($tmp.'/extra-themes');
t('register_theme_directory: keine Doppelten',count(array_keys($GLOBALS['wp_theme_directories'],$tmp.'/extra-themes',true))===1);
t('get_theme_roots (mehrere Verzeichnisse) liefert Karte',is_array(get_theme_roots())&&get_theme_roots()['mein-theme']==='/themes');
t('validate_theme_requirements',validate_theme_requirements('mein-theme')===true&&validate_theme_requirements('neu-theme')->get_error_code()==='theme_wp_php_incompatible'&&validate_theme_requirements('nur-wp')->get_error_code()==='theme_wp_incompatible'&&validate_theme_requirements('nur-php')->get_error_code()==='theme_php_incompatible');
t('locale_stylesheet ohne Sprachstil leer',out('locale_stylesheet')===''&&get_locale_stylesheet_uri()==='');

/* ───── Kopfbild, Kopfvideo, Hintergrund ───── */
add_theme_support('custom-header',['width'=>1000,'height'=>250,'video'=>true,'random-default'=>true]);
t('get_header_image_tag ohne Bild leer',get_header_image_tag()==='');
set_theme_mod('header_image','http://x/kopf.jpg');
$ht=get_header_image_tag(['class'=>'k']);
t('get_header_image_tag',str_starts_with($ht,'<img')&&str_contains($ht,'src="http://x/kopf.jpg"')&&str_contains($ht,'width="1000"')&&str_contains($ht,'height="250"')&&str_contains($ht,'alt=""')&&str_contains($ht,'class="k"'));
t('header_image',out('header_image')==='http://x/kopf.jpg');
register_default_headers(['a'=>['url'=>'%s/kopf-a.jpg','thumbnail_url'=>'%s/t-a.jpg','description'=>'A']]);
set_theme_mod('header_image','random-default-image');
t('_get_random_header_data / get_random_header_image',str_contains(get_random_header_image(),'kopf-a.jpg')&&_get_random_header_data()->description==='A');
t('is_random_header_image',is_random_header_image()&&is_random_header_image('default')&&is_random_header_image('uploaded')===false);
set_theme_mod('header_image','http://x/kopf.jpg');
t('is_random_header_image: festes Bild',is_random_header_image()===false);
t('unregister_default_headers',unregister_default_headers('a')===true&&unregister_default_headers('a')===false&&unregister_default_headers(['b'])===true);
t('Kopfvideo: keins',!has_header_video()&&!get_header_video_url());
set_theme_mod('external_header_video','https://www.youtube.com/watch?v=abc');
t('Kopfvideo: extern',has_header_video()&&get_header_video_url()==='https://www.youtube.com/watch?v=abc'&&out('the_header_video_url')==='https://www.youtube.com/watch?v=abc');
$hs=get_header_video_settings();
t('get_header_video_settings',$hs['mimeType']==='video/x-youtube'&&$hs['videoUrl']==='https://www.youtube.com/watch?v=abc'&&$hs['minWidth']===900&&$hs['width']===1000);
t('is_header_video_active',is_header_video_active()===true);
add_theme_support('custom-header',['video'=>true,'video-active-callback'=>fn()=>false]);
t('is_header_video_active mit Rückruf',is_header_video_active()===false);
remove_theme_support('custom-header');
t('is_header_video_active ohne Unterstützung',is_header_video_active()===false);
add_theme_support('custom-header',['width'=>1000,'height'=>250,'video'=>true]);
t('get_custom_header_markup',str_contains(get_custom_header_markup(),'<div id="wp-custom-header" class="wp-custom-header"><img '));
remove_theme_mod('header_image');remove_theme_mod('external_header_video');
t('get_custom_header_markup ohne Inhalte leer',get_custom_header_markup()==='');
set_theme_mod('background_color','abcdef');
t('background_color / background_image',out('background_color')==='abcdef'&&out('background_image')==='');

/* ───── Eigenes CSS, Editor-Stile ───── */
$cssPost=wp_update_custom_css_post('body{color:red}');
t('wp_update_custom_css_post (neu)',$cssPost instanceof WP_Post&&$cssPost->post_type==='custom_css'&&$cssPost->post_content==='body{color:red}');
t('wp_get_custom_css_post',wp_get_custom_css_post()->ID===$cssPost->ID);
$cssPost2=wp_update_custom_css_post('p{margin:0}');
t('wp_update_custom_css_post (ändern)',$cssPost2->ID===$cssPost->ID&&wp_get_custom_css_post()->post_content==='p{margin:0}');
t('wp_get_custom_css_post: anderes Theme leer',wp_get_custom_css_post('anderes-theme')===null);
t('wp_custom_css_cb ohne Inhalt leer',out('wp_custom_css_cb')===''||str_contains(out('wp_custom_css_cb'),'<style id="wp-custom-css">'));
$GLOBALS['editor_styles']=['editor.css','https://fonts.example/f.css','editor.css'];
$es=get_editor_stylesheets();
t('get_editor_stylesheets: externe Adresse',$es===['https://fonts.example/f.css']);
file_put_contents(get_stylesheet_directory().'/editor.css','');
$es=get_editor_stylesheets();
t('get_editor_stylesheets: vorhandene Datei',count($es)===2&&str_ends_with($es[1],'/editor.css'));
add_theme_support('editor-style');
t('remove_editor_styles',remove_editor_styles()===true&&!current_theme_supports('editor-style')&&remove_editor_styles()===false);
$GLOBALS['editor_styles']=[];
add_theme_support('starter-content',['posts'=>['home'=>['post_title'=>'Hallo']],'unbekannt'=>1]);
$sc=get_theme_starter_content();
t('get_theme_starter_content',$sc['posts']['home']['post_title']==='Hallo'&&$sc['widgets']===[]&&!isset($sc['unbekannt']));
remove_theme_support('starter-content');
t('get_theme_starter_content ohne Unterstützung',get_theme_starter_content()['posts']===[]);
t('_custom_header_background_just_in_time',(function(){ add_theme_support('custom-header',['wp-head-callback'=>'strlen']);_custom_header_background_just_in_time();return has_action('wp_head','strlen')!==false; })());
remove_action('wp_head','strlen');
t('_custom_logo_header_styles',(function(){ add_theme_support('custom-logo',['header-text'=>['site-title','site-desc']]);remove_theme_support('custom-header');set_theme_mod('header_text',false);$o=out('_custom_logo_header_styles');remove_theme_support('custom-logo');return str_contains($o,'.site-title, .site-desc')&&str_contains($o,'clip-path'); })());

/* ───── Theme-Features ───── */
t('create_initial_theme_features / get_registered_theme_features',(function(){ create_initial_theme_features();$f=get_registered_theme_features();return isset($f['title-tag'],$f['html5'],$f['post-thumbnails'])&&$f['html5']['variadic']===true&&$f['html5']['type']==='array'; })());
t('get_registered_theme_feature',get_registered_theme_feature('title-tag')['type']==='boolean'&&get_registered_theme_feature('gibtsnicht')===null);
t('register_theme_feature: ungültiger Typ / variadic',register_theme_feature('x','unsinn'===1?[]:['type'=>'kaputt'])instanceof WP_Error&&register_theme_feature('y',['variadic'=>true])instanceof WP_Error&&register_theme_feature('z',['type'=>'object','show_in_rest'=>true])===true&&get_registered_theme_feature('z')['show_in_rest']['name']==='z');
t('_remove_theme_support',(function(){ add_theme_support('custom-background');$r=_remove_theme_support('custom-background');return $r===true&&!current_theme_supports('custom-background'); })());
t('require_if_theme_supports',(function() use($tmp){ file_put_contents($tmp.'/inc.php','<?php $GLOBALS["inc_ok"]=1;');add_theme_support('mein-feature');$a=require_if_theme_supports('mein-feature',$tmp.'/inc.php');$b=require_if_theme_supports('anderes',$tmp.'/inc.php');return $a===true&&$b===false&&$GLOBALS['inc_ok']===1; })());
t('wp_theme_get_element_class_name',wp_theme_get_element_class_name('button')==='wp-element-button'&&wp_theme_get_element_class_name('caption')==='wp-element-caption'&&wp_theme_get_element_class_name('x')==='');
t('_add_default_theme_supports: Klassisches Theme ändert nichts',(function(){ _add_default_theme_supports();return !current_theme_supports('responsive-embeds'); })());
t('_delete_attachment_theme_mod',(function() use($aid){ set_theme_mod('custom_logo',$aid);set_theme_mod('header_image',wp_get_attachment_url($aid));_delete_attachment_theme_mod($aid);return get_theme_mod('custom_logo',false)===false&&get_theme_mod('header_image',false)===false; })());
t('check_theme_switched',(function(){ $got=[];add_action('after_switch_theme',function($n,$t) use(&$got){ $got=[$n,$t]; },10,2);update_option('theme_switched','mein-theme');check_theme_switched();return $got[0]==='Mein Theme'&&$got[1] instanceof WP_Theme&&get_option('theme_switched')===false; })());
t('check_theme_switched ohne Wechsel',(function(){ $c=0;add_action('after_switch_theme',function() use(&$c){ $c++; });check_theme_switched();return $c===0; })());

/* ───── Customizer-Hilfen (bewusst vereinfacht) ───── */
t('Customizer-No-ops',(function(){ _wp_customize_include();_wp_customize_publish_changeset('a','b',null);_wp_keep_alive_customize_changeset_dependent_auto_drafts('a','b',null);return _wp_customize_changeset_filter_insert_post_data(['x'=>1],[])===['x'=>1]; })());
t('wp_customize_support_script',str_contains(out('wp_customize_support_script'),'customize-support')&&str_contains(out('wp_customize_support_script'),'request = true'));
wp_register_script('customize-loader','c.js');_wp_customize_loader_settings();
t('_wp_customize_loader_settings',str_contains(wp_scripts()->registered['customize-loader']->src??'','c.js')&&!empty($GLOBALS['rrw_wp_scripts']['reg']['customize-loader']['inline_before'][0])&&str_contains($GLOBALS['rrw_wp_scripts']['reg']['customize-loader']['inline_before'][0],'_wpCustomizeLoaderSettings'));

/* ───── Skript-Lader ───── */
t('wp_sanitize_script_attributes',wp_sanitize_script_attributes(['src'=>'a.js','async'=>true,'defer'=>false,'data-x'=>'"<'])===' src="a.js" async="async" data-x="&quot;&lt;"');
add_theme_support('html5',['script']);
t('wp_sanitize_script_attributes (HTML5: nur Name)',wp_sanitize_script_attributes(['async'=>true,'src'=>'a.js'])===' async src="a.js"');
t('wp_get_script_tag / wp_print_script_tag',wp_get_script_tag(['src'=>'a.js'])==="<script src=\"a.js\"></script>\n"&&out(fn()=>wp_print_script_tag(['src'=>'b.js']))==="<script src=\"b.js\"></script>\n");
remove_theme_support('html5');
t('wp_get_script_tag: Filter wp_script_attributes',(function(){ add_filter('wp_script_attributes',fn($a)=>$a+['nonce'=>'n1']);$r=wp_get_script_tag(['src'=>'c.js']);remove_all_filters('wp_script_attributes');return str_contains($r,'nonce="n1"'); })());
t('wp_remove_surrounding_empty_script_tags',wp_remove_surrounding_empty_script_tags("  <script>var a=1;</script>\n")==='var a=1;');
t('wp_remove_surrounding_empty_script_tags: ungültig',(function(){ add_filter('doing_it_wrong_trigger_error','__return_false');return wp_remove_surrounding_empty_script_tags('<script defer>x</script>')==='<script defer>x</script>'; })());
t('wp_prototype_before_jquery',wp_prototype_before_jquery(['jquery','a','prototype'])===['prototype','jquery','a']&&wp_prototype_before_jquery(['prototype','jquery'])===['prototype','jquery']&&wp_prototype_before_jquery(['a'])===['a']);
t('_wp_normalize_relative_css_links',_wp_normalize_relative_css_links('a{background:url(img/a.png)} b{background:url("http://x/b.png")} c{background:url(#id)} d{background:url(data:x)}','http://s.test/css/style.css')==='a{background:url(http://s.test/css/img/a.png)} b{background:url("http://x/b.png")} c{background:url(#id)} d{background:url(data:x)}');
t('wp_filter_out_block_nodes',array_keys(wp_filter_out_block_nodes([['path'=>['styles','blocks','core/x']],['path'=>['styles','elements']]]))===[1]);
$ws=wp_scripts();
wp_default_scripts($ws);wp_default_styles(wp_styles());
t('wp_default_scripts registriert Kernskripte',wp_script_is('jquery','registered')&&wp_script_is('wp-playlist','registered')&&wp_script_is('jquery-ui-sortable','registered')&&in_array('jquery-core',wp_scripts()->registered['jquery']->deps,true));
t('wp_default_scripts: Pakete und Vendor',wp_script_is('wp-element','registered')&&wp_script_is('react','registered')&&wp_script_is('wp-polyfill','registered')&&wp_script_is('wp-tinymce-root','registered'));
t('wp_default_styles',wp_style_is('dashicons','registered')&&wp_style_is('wp-block-library','registered')&&in_array('wp-mediaelement',wp_styles()->registered['media-views']->deps,true));
t('wp_default_scripts: Haken wp_default_scripts',(function() use($ws){ $x=0;add_action('wp_default_scripts',function() use(&$x){ $x++; });wp_default_scripts($ws);return $x===1; })());
t('wp_default_packages_vendor: Versionen',(string)wp_scripts()->registered['react']->ver==='18.3.1');
t('wp_get_script_polyfill',str_contains(wp_get_script_polyfill(wp_scripts(),['window.fetch'=>'wp-polyfill-fetch','x'=>'gibt-es-nicht']),'( window.fetch )||document.write( \'<script src="')&&!str_contains(wp_get_script_polyfill(wp_scripts(),['x'=>'gibt-es-nicht']),'x'));
wp_default_packages_inline_scripts(wp_scripts());
t('wp_default_packages_inline_scripts (api-fetch, i18n, date)',str_contains(implode('',$GLOBALS['rrw_wp_scripts']['reg']['wp-api-fetch']['inline_after']),'createRootURLMiddleware')&&str_contains(implode('',$GLOBALS['rrw_wp_scripts']['reg']['wp-api-fetch']['inline_after']),'createNonceMiddleware')&&str_contains(implode('',$GLOBALS['rrw_wp_scripts']['reg']['wp-i18n']['inline_after']),'text direction')&&str_contains(implode('',$GLOBALS['rrw_wp_scripts']['reg']['wp-date']['inline_after']),'setSettings'));
t('wp_tinymce_inline_scripts',str_contains(implode('',$GLOBALS['rrw_wp_scripts']['reg']['wp-tinymce-root']['inline_before']),'wpEditorL10n'));
t('wp_register_development_scripts ohne SCRIPT_DEBUG',!wp_script_is('wp-react-refresh-runtime','registered'));
wp_register_script('mce-view','m.js');wp_register_script('word-count','w.js');wp_just_in_time_script_localization();
t('wp_just_in_time_script_localization',count($GLOBALS['rrw_wp_scripts']['reg']['mce-view']['l10n'])===1&&in_array('gallery',$GLOBALS['rrw_wp_scripts']['reg']['mce-view']['l10n'][0][1]['shortcodes'],true)&&$GLOBALS['rrw_wp_scripts']['reg']['word-count']['l10n'][0][0]==='wordCountL10n');
t('wp_localize_community_events',(function(){ wp_localize_community_events();return !empty($GLOBALS['rrw_wp_scripts']['reg']['community-events']['l10n'][0][1]['nonce']); })());
t('wp_style_loader_src',wp_style_loader_src('a.css','x')==='a.css'&&wp_style_loader_src('a.css','colors')===false);
global $concatenate_scripts;$concatenate_scripts=null;unset($GLOBALS['concatenate_scripts'],$GLOBALS['compress_scripts'],$GLOBALS['compress_css']);
script_concat_settings();
t('script_concat_settings',$GLOBALS['concatenate_scripts']===false&&isset($GLOBALS['compress_scripts'],$GLOBALS['compress_css']));
$GLOBALS['rrw_wp_scripts']['queue']=[];$GLOBALS['rrw_wp_scripts']['done']=[];$GLOBALS['rrw_wp_styles']['queue']=[];$GLOBALS['rrw_wp_styles']['done']=[];
wp_enqueue_script('t-kopf','http://x/kopf.js');wp_enqueue_script('t-fuss','http://x/fuss.js',[],false,true);wp_enqueue_style('t-stil','http://x/stil.css');
$k=out(fn()=>print_head_scripts());
t('print_head_scripts: nur Kopfskripte',str_contains($k,'t-kopf-js')&&!str_contains($k,'t-fuss-js'));
$f=out(fn()=>print_footer_scripts());
t('print_footer_scripts',str_contains($f,'t-fuss-js'));
t('print_admin_styles',str_contains(out(fn()=>print_admin_styles()),'t-stil-css'));
$GLOBALS['rrw_wp_styles']['queue']=[];wp_enqueue_style('t-spaet','http://x/spaet.css');
t('print_late_styles / _wp_footer_scripts',str_contains(out('_wp_footer_scripts'),'t-spaet-css'));
t('print_head_scripts: Filter print_head_scripts',(function(){ wp_enqueue_script('t-kopf2','http://x/k2.js');add_filter('print_head_scripts','__return_false');$o=out(fn()=>print_head_scripts());remove_all_filters('print_head_scripts');return $o===''; })());
t('_print_scripts/_print_styles/wp_maybe_inline_styles/compression_test geben nichts aus',out(function(){ _print_scripts();_print_styles();wp_maybe_inline_styles();compression_test(); })==='');
t('wp_common_block_scripts_and_styles',(function(){ $x=0;add_action('enqueue_block_assets',function() use(&$x){ $x++; });add_theme_support('wp-block-styles');wp_common_block_scripts_and_styles();remove_theme_support('wp-block-styles');return $x===1&&wp_style_is('wp-block-library','enqueued')&&wp_style_is('wp-block-library-theme','enqueued'); })());
t('wp_should_load_block_editor_scripts_and_styles / on_demand',wp_should_load_block_editor_scripts_and_styles()===false&&wp_should_load_block_assets_on_demand()===false);
t('wp_enqueue_registered_block_scripts_and_styles',(function(){ register_block_type('t/blk',['style'=>'t-blk-stil','script'=>'t-blk-js']);wp_enqueue_registered_block_scripts_and_styles();return wp_style_is('t-blk-stil','enqueued')&&wp_script_is('t-blk-js','enqueued'); })());
register_block_style('core/button',['name'=>'rund','label'=>'Rund','inline_style'=>'.is-style-rund{border-radius:9px}']);
enqueue_block_styles_assets();
t('enqueue_block_styles_assets (Inline-Stil)',str_contains(implode('',$GLOBALS['rrw_wp_styles']['reg']['rrw-block-style-inline']['inline_after']??$GLOBALS['rrw_wp_styles']['reg']['wp-block-library']['inline_after']??[]),'is-style-rund'));
enqueue_editor_block_styles_assets();
t('enqueue_editor_block_styles_assets',str_contains(implode('',$GLOBALS['rrw_wp_scripts']['reg']['wp-block-styles']['inline_after']),"wp.blocks.registerBlockStyle( 'core/button', {\"name\":\"rund\",\"label\":\"Rund\"} );")&&wp_script_is('wp-block-styles','enqueued'));
wp_enqueue_editor_block_directory_assets();wp_enqueue_editor_format_library_assets();
t('wp_enqueue_editor_*_assets',wp_script_is('wp-block-directory','enqueued')&&wp_style_is('wp-format-library','enqueued')&&wp_script_is('wp-format-library','enqueued'));
t('wp_enqueue_global_styles / custom_properties',(function(){ wp_enqueue_global_styles();wp_enqueue_global_styles_css_custom_properties();return wp_style_is('global-styles-css-custom-properties','enqueued'); })());
t('wp_enqueue_block_support_styles (klassisches Theme: Fuß)',(function(){ wp_enqueue_block_support_styles('.a{b:c}',5);return str_contains(out(fn()=>do_action('wp_footer')),'<style>.a{b:c}</style>')&&!str_contains(out(fn()=>do_action('wp_head')),'<style>.a{b:c}</style>'); })());
$GLOBALS['rrw_wp_block_support_css']=['x'=>'.wp-block-support{a:b}'];wp_enqueue_stored_styles();
t('wp_enqueue_stored_styles',wp_style_is('core-block-supports','enqueued')&&str_contains(implode('',$GLOBALS['rrw_wp_styles']['reg']['core-block-supports']['inline_after']),'.wp-block-support{a:b}'));
t('wp_enqueue_classic_theme_styles',(function(){ wp_enqueue_classic_theme_styles();return wp_style_is('classic-theme-styles','enqueued'); })());
t('Skript-Module',(function(){ $m=wp_script_modules();$m->register('@t/mod','http://x/mod.js',[],'1.0');$m->enqueue('@t/mod');$o=out(fn()=>$m->print_enqueued_script_modules());$im=out(fn()=>$m->print_import_map());return $m instanceof WP_Script_Modules&&$m===wp_script_modules()&&str_contains($o,'type="module" src="http://x/mod.js?ver=1.0"')&&str_contains($im,'"@t/mod":"http://x/mod.js?ver=1.0"'); })());
t('wp_deregister_script_module',(function(){ wp_deregister_script_module('@t/mod');return !wp_script_modules()->is_registered('@t/mod')&&out(fn()=>wp_script_modules()->print_enqueued_script_modules())===''; })());
wp_default_script_modules();
t('wp_default_script_modules',wp_script_modules()->is_registered('@wordpress/interactivity')&&in_array('@wordpress/interactivity',wp_script_modules()->get_registered()['@wordpress/interactivity-router']['deps'],true));

/* ───── Widgets ───── */
t('_get_widget_id_base / wp_parse_widget_id',_get_widget_id_base('text-12')==='text'&&_get_widget_id_base('calendar')==='calendar'&&wp_parse_widget_id('text-12')===['id_base'=>'text','number'=>12]&&wp_parse_widget_id('alt')===['id_base'=>'alt']);
register_sidebar(['id'=>'sb-a','name'=>'A','description'=>'Hallo <b>fett</b><script>x</script>']);register_sidebar(['id'=>'sb-b','name'=>'B']);
t('wp_sidebar_description (gefiltert)',wp_sidebar_description('sb-a')==='Hallo <b>fett</b>x'&&wp_sidebar_description('gibt-es-nicht')===null);
class T_Widget extends WP_Widget{ function __construct(){ parent::__construct('tw','TW',['description'=>'Beschr & <b>']); } function widget($a,$i){} }
register_widget('T_Widget');
t('wp_widget_description',wp_widget_description('tw-2')==='Beschr &amp; &lt;b&gt;'&&wp_widget_description('gibt-es-nicht-1')===null&&wp_widget_description([])===null);
wp_register_widget_control('mein-w','Mein W','strlen',['width'=>400]);
t('wp_register_widget_control',$GLOBALS['wp_registered_widget_controls']['mein-w']['width']===400&&$GLOBALS['wp_registered_widget_controls']['mein-w']['callback']==='strlen'&&isset($GLOBALS['wp_registered_widget_updates']['mein-w']));
wp_unregister_widget_control('mein-w');
t('wp_unregister_widget_control',!isset($GLOBALS['wp_registered_widget_controls']['mein-w'])&&!isset($GLOBALS['wp_registered_widget_updates']['mein-w']));
_register_widget_update_callback('wx','strlen',['height'=>1]);_register_widget_form_callback('WX-1','WX','strlen',['width'=>10]);
t('_register_widget_update_callback / _form_callback',$GLOBALS['wp_registered_widget_updates']['wx']['callback']==='strlen'&&$GLOBALS['wp_registered_widget_controls']['wx-1']['width']===10&&$GLOBALS['wp_registered_widget_controls']['wx-1']['height']===200);
_register_widget_form_callback('wx-1','','');
t('_register_widget_form_callback entfernt',!isset($GLOBALS['wp_registered_widget_controls']['wx-1']));
update_option('sidebars_widgets',['sb-a'=>['text-2','search-2'],'sb-b'=>[],'wp_inactive_widgets'=>[]]);
t('wp_find_widgets_sidebar',wp_find_widgets_sidebar('search-2')==='sb-a'&&wp_find_widgets_sidebar('nirgends-9')===null);
wp_assign_widget_to_sidebar('search-2','sb-b');
t('wp_assign_widget_to_sidebar (verschieben)',wp_find_widgets_sidebar('search-2')==='sb-b'&&wp_find_widgets_sidebar('text-2')==='sb-a'&&count(wp_get_sidebars_widgets()['sb-a'])===1);
wp_assign_widget_to_sidebar('search-2','');
t('wp_assign_widget_to_sidebar (entfernen)',wp_find_widgets_sidebar('search-2')===null);
t('is_dynamic_sidebar',is_dynamic_sidebar()===true);
t('wp_get_widget_defaults',isset(wp_get_widget_defaults()['sb-a'])&&wp_get_widget_defaults()['sb-a']===[]);
t('next_widget_id_number',next_widget_id_number('text')===3&&next_widget_id_number('neu')===2);
$cv=wp_convert_widget_settings('calendar','widget_calendar',['title'=>'T']);
t('wp_convert_widget_settings: Einzel-Widget',$cv===[2=>['title'=>'T'],'_multiwidget'=>1]);
$cv=wp_convert_widget_settings('text','widget_text',[3=>['title'=>'x']]);
t('wp_convert_widget_settings: schon Mehrfach-Widget',$cv[3]['title']==='x'&&$cv['_multiwidget']===1&&!isset($cv[2]));
t('_wp_remove_unregistered_widgets',_wp_remove_unregistered_widgets(['sb'=>['text-2','gibtsnicht-1']])===['sb'=>['text-2']]&&_wp_remove_unregistered_widgets(['sb'=>['a','b']],['b'])===['sb'=>['b']]);
$map=wp_map_sidebars_widgets(['sidebar-1'=>['text-2'],'wp_inactive_widgets'=>['x-1'],'orphaned_widgets_1'=>['y-1']]);
t('wp_map_sidebars_widgets: gleiche Kennung/Inaktive',isset($map['wp_inactive_widgets'])&&in_array('x-1',$map['wp_inactive_widgets'],true)&&in_array('y-1',$map['wp_inactive_widgets'],true)&&isset($map['sb-a'])&&isset($map['sb-b']));
unregister_sidebar('sb-b');
$map=wp_map_sidebars_widgets(['alt'=>['text-2']]);
t('wp_map_sidebars_widgets: ein Bereich → ein Bereich (plus Standard)',!empty($map)&&isset($map['wp_inactive_widgets']));
register_sidebar(['id'=>'sb-b','name'=>'B']);
$sw=retrieve_widgets();
t('retrieve_widgets: Struktur',isset($sw['sb-a'],$sw['sb-b'],$sw['wp_inactive_widgets'])&&get_option('sidebars_widgets')['array_version']===3);
update_option('sidebars_widgets',['entfernt'=>['text-2'],'sb-a'=>[],'sb-b'=>[],'wp_inactive_widgets'=>[]]);
$sw=retrieve_widgets();
t('retrieve_widgets: nicht mehr registrierte Bereiche werden zu orphaned',isset($sw['orphaned_widgets_1'])&&!isset($sw['entfernt']));
t('_wp_sidebars_changed',(function(){ update_option('sidebars_widgets',['sb-a'=>['text-2']]);_wp_sidebars_changed();return isset(get_option('sidebars_widgets')['sb-b']); })());
t('wp_render_widget',(function(){ $o=out(fn()=>wp_render_widget('text-2','sb-a'));return is_string($o)&&wp_render_widget('gibtsnicht-1','sb-a')===false; })());
t('wp_render_widget_control',str_contains((string)wp_render_widget_control('text-2'),'widget-content')&&wp_render_widget_control('gibtsnicht-1')===null);
t('wp_use_widgets_block_editor / wp_setup_widgets_block_editor',wp_use_widgets_block_editor()===false&&(function(){ wp_setup_widgets_block_editor();return wp_use_widgets_block_editor()!==false; })());
t('wp_check_widget_editor_deps',(function(){ wp_check_widget_editor_deps();return true; })());
t('wp_widgets_init löst widgets_init aus',(function(){ $x=0;add_action('widgets_init',function() use(&$x){ $x++; });wp_widgets_init();return $x===1; })());
t('_wp_block_theme_register_classic_sidebars (klassisches Theme)',(function(){ $b=count($GLOBALS['wp_registered_sidebars']);_wp_block_theme_register_classic_sidebars();return count($GLOBALS['wp_registered_sidebars'])===$b; })());
$feed='<?xml version="1.0"?><rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/"><channel><title>T</title><link>http://feed.test/</link><item><title>Eins &amp; zwei</title><link>http://feed.test/1</link><description>&lt;p&gt;Kurztext&lt;/p&gt;</description><pubDate>Mon, 05 Jan 2026 10:00:00 +0000</pubDate><dc:creator>Anna</dc:creator></item><item><title></title><link>http://feed.test/2</link><description>Zwei</description></item></channel></rss>';
$cache='rrw_feed_'.md5('http://feed.test/rss');set_transient($cache,['items'=>[['title'=>'Eins & zwei','link'=>'http://feed.test/1','date'=>1767607200,'description'=>'<p>Kurztext</p>','author'=>'Anna'],['title'=>'','link'=>'http://feed.test/2','date'=>0,'description'=>'Zwei','author'=>'']],'link'=>'http://feed.test/'],3600);
$ro=out(fn()=>wp_widget_rss_output('http://feed.test/rss',['items'=>5,'show_summary'=>1,'show_author'=>1,'show_date'=>1]));
t('wp_widget_rss_output: Einträge',str_contains($ro,"<a class='rsswidget' href='http://feed.test/1'>Eins &amp; zwei</a>")&&str_contains($ro,'<cite>Anna</cite>')&&str_contains($ro,'<div class="rssSummary">Kurztext</div>')&&str_contains($ro,'rss-date')&&str_contains($ro,'Ohne Titel'));
t('wp_widget_rss_output: Begrenzung / Array-Aufruf',substr_count(out(fn()=>wp_widget_rss_output(['url'=>'http://feed.test/rss','items'=>1])),'<li>')===1);
t('wp_widget_rss_output: Fehler',str_contains(out(fn()=>wp_widget_rss_output(new WP_Error('x','Kaputt'))),'RSS-Fehler')&&out(fn()=>wp_widget_rss_output(5))==='');
$rp=wp_widget_rss_process(['url'=>'http://feed.test/rss','title'=>' <b>T</b> ','items'=>99,'show_author'=>'1']);
t('wp_widget_rss_process',$rp['items']===10&&$rp['title']==='T'&&$rp['url']==='http://feed.test/rss'&&$rp['link']==='http://feed.test/'&&$rp['error']===false&&$rp['show_author']===1&&$rp['show_date']===0);
t('wp_widget_rss_process: ohne Prüfung',wp_widget_rss_process(['url'=>'http://nirgends.test/x','items'=>3],false)['error']===false);
$rf=out(fn()=>wp_widget_rss_form(['number'=>4,'url'=>'http://f.test','items'=>7,'show_date'=>1]));
t('wp_widget_rss_form',str_contains($rf,'name="widget-rss[4][url]"')&&str_contains($rf,"<option value='7' selected='selected'>7</option>")&&str_contains($rf,'name="widget-rss[4][show_date]" type="checkbox" value="1" checked="checked"'));
t('_sort_name_callback',_sort_name_callback(['name'=>'a2'],['name'=>'a10'])<0&&_sort_name_callback(['name'=>'B'],['name'=>'a'])>0);
t('wp_list_widgets',str_contains(out('wp_list_widgets'),'class="widget"')&&str_contains(out('wp_list_widgets'),'widget-description'));
t('wp_widget_control',str_contains(out(fn()=>wp_widget_control(['widget_id'=>'text-2','id'=>'sb-a'])),'name="widget-id" class="widget-id" value="text-2"'));
t('wp_list_widget_controls',str_contains(out(fn()=>wp_list_widget_controls('sb-a')),'id="widget-text-2"'));
$pa=wp_list_widget_controls_dynamic_sidebar([['widget_id'=>'text-2']]);
t('wp_list_widget_controls_dynamic_sidebar',str_contains($pa[0]['before_widget'],"_text-2' class='widget'")&&$pa[0]['before_title']==='%BEG_OF_TITLE%');
t('wp_widgets_access_body_class',wp_widgets_access_body_class('a')==='a'&&(function(){ $_GET['widgets-access']='on';$r=wp_widgets_access_body_class('a');unset($_GET['widgets-access']);return $r==='a widgets_access '; })());

/* ───── Vorlagen-Hierarchie ───── */
$tdir=get_stylesheet_directory();
foreach(['index.php','404.php','archive.php','archive-folge.php','author.php','author-anna.php','category.php','category-news.php','tag.php','taxonomy.php','taxonomy-genre-rock.php','date.php','home.php','front-page.php','page.php','page-impressum.php','search.php','single.php','single-folge.php','single-folge-pilot.php','singular.php','attachment.php','image-jpeg.php','embed.php','privacy-policy.php'] as $f)file_put_contents($tdir.'/'.$f,'');
$rel=fn($p)=>$p===''?'':basename($p);
t('get_index/404/date/search/singular/privacy/front/home',$rel(get_index_template())==='index.php'&&$rel(get_404_template())==='404.php'&&$rel(get_date_template())==='date.php'&&$rel(get_search_template())==='search.php'&&$rel(get_singular_template())==='singular.php'&&$rel(get_privacy_policy_template())==='privacy-policy.php'&&$rel(get_front_page_template())==='front-page.php'&&$rel(get_home_template())==='home.php');
unlink($tdir.'/home.php');
t('get_home_template: Rückfall auf index.php',$rel(get_home_template())==='index.php');
$GLOBALS['wp_query']->queried_object=(object)['slug'=>'news','term_id'=>5,'taxonomy'=>'category'];$GLOBALS['wp_query']->queried_object_id=5;
t('get_category_template (Slug vor ID vor Standard)',$rel(get_category_template())==='category-news.php');
unlink($tdir.'/category-news.php');
t('get_category_template: Rückfall',$rel(get_category_template())==='category.php');
t('get_tag_template',$rel(get_tag_template())==='tag.php');
$GLOBALS['wp_query']->queried_object=(object)['slug'=>'rock','term_id'=>7,'taxonomy'=>'genre'];
t('get_taxonomy_template',$rel(get_taxonomy_template())==='taxonomy-genre-rock.php');
$GLOBALS['wp_query']->queried_object=new WP_User((object)['ID'=>3,'user_login'=>'anna','user_nicename'=>'anna']);
t('get_author_template',$rel(get_author_template())==='author-anna.php');
$GLOBALS['wp_query']->queried_object=(object)['post_type'=>'folge','post_name'=>'pilot','ID'=>9];
t('get_single_template (Typ+Name zuerst)',$rel(get_single_template())==='single-folge-pilot.php');
unlink($tdir.'/single-folge-pilot.php');
t('get_single_template (Typ)',$rel(get_single_template())==='single-folge.php');
$GLOBALS['wp_query']->queried_object=(object)['post_type'=>'post','post_name'=>'x','ID'=>10];
t('get_single_template (Standard)',$rel(get_single_template())==='single.php');
$ip=wp_insert_post(['post_title'=>'Impressum','post_type'=>'page','post_name'=>'impressum','post_status'=>'publish']);
$GLOBALS['wp_query']->query_vars['pagename']='impressum';$GLOBALS['wp_query']->queried_object=get_post($ip);$GLOBALS['wp_query']->queried_object_id=$ip;$GLOBALS['post']=get_post($ip);
t('get_page_template (Seitenname)',$rel(get_page_template())==='page-impressum.php');
update_post_meta($ip,'_wp_page_template','breit.php');
t('get_page_template: Vorlage fehlt → Seitenname',$rel(get_page_template())==='page-impressum.php');
file_put_contents($tdir.'/breit.php','');
t('get_page_template: gewählte Vorlage zuerst',$rel(get_page_template())==='breit.php');
$GLOBALS['wp_query']->query_vars['post_type']='folge';
t('get_archive_template (Typ)',$rel(get_archive_template())==='archive-folge.php');
register_post_type('folge',['public'=>true,'has_archive'=>true]);
t('get_post_type_archive_template',$rel(get_post_type_archive_template())==='archive-folge.php');
register_post_type('ohne',['public'=>true,'has_archive'=>false]);$GLOBALS['wp_query']->query_vars['post_type']='ohne';
t('get_post_type_archive_template: ohne Archiv leer',get_post_type_archive_template()==='');
$GLOBALS['wp_query']->queried_object=(object)['post_type'=>'attachment','post_mime_type'=>'image/jpeg','ID'=>12];
t('get_attachment_template (MIME vor Untertyp)',$rel(get_attachment_template())==='image-jpeg.php');
$GLOBALS['wp_query']->queried_object=(object)['post_type'=>'post','ID'=>13,'post_name'=>'p'];
t('get_embed_template',$rel(get_embed_template())==='embed.php');
t('Vorlagen-Filter {$type}_template',(function() use($tdir){ add_filter('frontpage_template',fn($t)=>'/ueberschrieben.php');$r=get_front_page_template();remove_all_filters('frontpage_template');return $r==='/ueberschrieben.php'; })());
t('Vorlagen-Filter {$type}_template_hierarchy',(function(){ add_filter('search_template_hierarchy',fn($t)=>['index.php']);$r=basename(get_search_template());remove_all_filters('search_template_hierarchy');return $r==='index.php'; })());
t('wp_set_template_globals',(function(){ wp_set_template_globals('/pfad/index.php');$a=$GLOBALS['template']==='/pfad/index.php';wp_set_template_globals((object)['id'=>'theme//home','content'=>'<!-- wp:x /-->']);return $a&&$GLOBALS['_wp_current_template_id']==='theme//home'&&$GLOBALS['_wp_current_template_content']==='<!-- wp:x /-->'; })());

/* ───── Verwaltungs-Vorlagenfunktionen ───── */
$t1=wp_insert_term('Rock','category');$t2=wp_insert_term('Pop','category',['parent'=>$t1['term_id']]);
$cid=wp_insert_post(['post_title'=>'Beitrag mit Kategorie','post_status'=>'publish','post_category'=>[$t2['term_id']]]);
$cl=out(fn()=>wp_terms_checklist($cid,['taxonomy'=>'category']));
t('wp_terms_checklist: Einträge, Auswahl, Hierarchie',str_contains($cl,"id='category-{$t1['term_id']}'")&&str_contains($cl,"name=\"post_category[]\" id=\"in-category-{$t2['term_id']}\" checked='checked'")&&str_contains($cl,"<ul class='children'>"));
t('wp_terms_checklist: Auswahl oben',strpos($cl,"id='category-{$t1['term_id']}'")!==false);
t('wp_category_checklist',str_contains(out(fn()=>wp_category_checklist($cid)),'post_category[]')&&str_contains(out(fn()=>wp_category_checklist(0,0,[$t1['term_id']])),"id=\"in-category-{$t1['term_id']}\" checked='checked'"));
t('wp_terms_checklist: nur Nachkommen',(function() use($t1,$t2){ $o=out(fn()=>wp_terms_checklist(0,['taxonomy'=>'category','descendants_and_self'=>$t1['term_id']]));return str_contains($o,"category-{$t1['term_id']}'")&&str_contains($o,"category-{$t2['term_id']}'"); })());
t('wp_terms_checklist: unbekannte Taxonomie leer',out(fn()=>wp_terms_checklist(0,['taxonomy'=>'gibtsnicht']))==='');
register_taxonomy('thema','post',['hierarchical'=>false,'public'=>true]);wp_insert_term('Alpha','thema');
t('wp_terms_checklist: eigene Taxonomie',str_contains(out(fn()=>wp_terms_checklist(0,['taxonomy'=>'thema'])),'name="tax_input[thema][]"'));
$GLOBALS['post']=get_post($cid);
$pop=[];$po=out(function() use(&$pop){ $pop=wp_popular_terms_checklist('category'); });
t('wp_popular_terms_checklist',is_array($pop)&&count($pop)>=1&&str_contains($po,'popular-category')&&wp_popular_terms_checklist('category',0,10,false)===$pop);
t('wp_link_category_checklist ohne Link-Taxonomie leer',out(fn()=>wp_link_category_checklist())==='');
t('get_inline_data',(function() use($cid){ $o=out(fn()=>get_inline_data(get_post($cid)));return str_contains($o,'id="inline_'.$cid.'"')&&str_contains($o,'<div class="post_title">Beitrag mit Kategorie</div>')&&str_contains($o,'class="post_category"')&&str_contains($o,'<div class="_status">publish</div>')&&str_contains($o,'<div class="sticky"></div>'); })());
t('wp_comment_reply / wp_comment_trashnotice',str_contains(out(fn()=>wp_comment_reply()),'id="replycontent"')&&str_contains(out('wp_comment_trashnotice'),'trash-undo-holder'));
global $wpdb;$wpdb->insert($wpdb->postmeta,['post_id'=>$cid,'meta_key'=>'farbe','meta_value'=>'rot']);$mid=(int)$wpdb->insert_id;$cnt=0;
$row=_list_meta_row(['meta_id'=>$mid,'meta_key'=>'farbe','meta_value'=>'<rot>'],$cnt);
t('_list_meta_row',$cnt===1&&str_contains($row,"id='meta-$mid'")&&str_contains($row,'&lt;rot&gt;')&&str_contains($row,"name='meta[$mid][key]'"));
t('_list_meta_row: geschützt / serialisiert',_list_meta_row(['meta_id'=>1,'meta_key'=>'_intern','meta_value'=>'x'],$cnt)===''&&_list_meta_row(['meta_id'=>2,'meta_key'=>'k','meta_value'=>serialize(['a'])],$cnt)===''&&$cnt===1);
t('meta_form',str_contains(out(fn()=>meta_form(get_post($cid))),'<option value=\'farbe\'>farbe</option>')&&str_contains(out(fn()=>meta_form(get_post($cid))),'name="metavalue"'));
$GLOBALS['wp_locale']=$GLOBALS['wp_locale']??new WP_Locale();
$tt=out(fn()=>touch_time(1,1));
t('touch_time',str_contains($tt,'name="mm"')&&str_contains($tt,'name="jj"')&&(bool)preg_match('/name="aa" value="\d{4}"/',$tt)&&str_contains($tt,'timestamp-wrap'));
t('touch_time: Mehrfachmodus ohne ids',!str_contains(out(fn()=>touch_time(1,1,0,1)),'id="mm"')&&str_contains($tt,'id="hidden_mm"'));
t('page_template_dropdown',out(fn()=>page_template_dropdown())==='');
$pg1=wp_insert_post(['post_title'=>'Eltern','post_type'=>'page','post_status'=>'publish']);$pg2=wp_insert_post(['post_title'=>'Kind','post_type'=>'page','post_status'=>'publish','post_parent'=>$pg1]);
$pd=out(fn()=>parent_dropdown($pg1));
t('parent_dropdown',(bool)preg_match("/class='level-0' value='$pg1'\s+selected='selected'>/",$pd)&&str_contains($pd,"class='level-1' value='$pg2'"));
t('parent_dropdown: keine eigene Elternseite / leer',!str_contains(out(function() use($pg2){ $GLOBALS['post']=get_post($pg2);parent_dropdown(0); }),"value='$pg2'")&&parent_dropdown(0,999999)===false);
t('wp_dropdown_roles',str_contains(out(fn()=>wp_dropdown_roles('editor')),"selected='selected' value='editor'")&&str_contains(out(fn()=>wp_dropdown_roles()),"value='administrator'"));
t('wp_import_upload_form',str_contains(out(fn()=>wp_import_upload_form('http://x/import')),'name="import"')&&str_contains(out(fn()=>wp_import_upload_form('http://x/import')),'import-upload-form'));
function rrw_t_box_cb(){}
t('_get_plugin_from_callback: Kern-Funktion → null',_get_plugin_from_callback('rrw_t_box_cb')===null&&_get_plugin_from_callback('gibt_es_nicht')===null&&_get_plugin_from_callback('strlen')===null);
t('do_block_editor_incompatible_meta_box',str_contains(out(fn()=>do_block_editor_incompatible_meta_box(get_post($cid),['callback'=>'rrw_t_box_cb'])),'nicht mit dem Block-Editor kompatibel'));
$GLOBALS['wp_meta_boxes']['seite']['side']['default']['k1']=['id'=>'k1','title'=>'Kasten','callback'=>function(){ echo 'KASTEN-INHALT'; }];
$acc='';$cnt2=0;$acc=out(function() use(&$cnt2){ $cnt2=do_accordion_sections('seite','side',null); });
t('do_accordion_sections',$cnt2===1&&str_contains($acc,'accordion-section-title')&&str_contains($acc,'KASTEN-INHALT')&&str_contains($acc,'open k1'));
t('find_posts_div',str_contains(out(fn()=>find_posts_div('x')),'id="find-posts"')&&str_contains(out(fn()=>find_posts_div('x')),'name="found_action" value="x"'));
$GLOBALS['post']=(object)['post_password'=>'g"eheim'];
t('the_post_password',out('the_post_password')==='g&quot;eheim');
$GLOBALS['hook_suffix']='media-upload-popup';
$ih=out(fn()=>iframe_header('Titel'));
t('iframe_header / iframe_footer',str_contains($ih,'<!DOCTYPE html>')&&str_contains($ih,'<body class="wp-admin wp-core-ui no-js iframe')&&str_contains(out('iframe_footer'),'</body></html>'));
t('_wp_admin_html_begin',str_contains(out('_wp_admin_html_begin'),'<!DOCTYPE html>')&&str_contains(out('_wp_admin_html_begin'),'<head>'));
$ps=get_post($cid);
t('get_post_states: veröffentlicht',get_post_states($ps)===[]);
$dr=wp_insert_post(['post_title'=>'Entwurf','post_status'=>'draft','post_password'=>'x']);
t('get_post_states: Entwurf, geschützt',get_post_states(get_post($dr))===['protected'=>'Passwortgeschützt','draft'=>'Entwurf']);
update_option('show_on_front','page');update_option('page_on_front',$pg1);update_option('page_for_posts',$pg2);
t('get_post_states: Startseite / Beitragsseite',get_post_states(get_post($pg1))['page_on_front']==='Startseite'&&get_post_states(get_post($pg2))['page_for_posts']==='Beitragsseite');
t('get_post_states: Filter display_post_states',(function() use($cid){ add_filter('display_post_states',fn($s)=>$s+['x'=>'X']);$r=get_post_states(get_post($cid));remove_all_filters('display_post_states');return $r==['x'=>'X']; })());
stick_post($cid);
t('get_post_states: angeheftet',get_post_states(get_post($cid))['sticky']==='Angeheftet');
set_theme_mod('custom_logo',$aid);
t('get_media_states / _media_states',get_media_states(get_post($aid))===['Logo']&&str_contains(out(fn()=>_media_states(get_post($aid))),"<span class='post-state'>Logo</span>")&&out(fn()=>_media_states(get_post($aid2)))==='');
remove_theme_mod('custom_logo');
t('_local_storage_notice',str_contains(out('_local_storage_notice'),'local-storage-notice'));
t('wp_star_rating',out(fn()=>wp_star_rating(['rating'=>3.5]))===wp_star_rating(['rating'=>3.5,'echo'=>false])&&substr_count(wp_star_rating(['rating'=>3.5,'echo'=>false]),'star-full')===3&&substr_count(wp_star_rating(['rating'=>3.5,'echo'=>false]),'star-half')===1&&substr_count(wp_star_rating(['rating'=>3.5,'echo'=>false]),'star-empty')===1);
t('wp_star_rating: Prozent',substr_count(wp_star_rating(['rating'=>80,'type'=>'percent','echo'=>false]),'star-full')===4);
t('wp_star_rating: Anzahl der Stimmen',str_contains(wp_star_rating(['rating'=>4,'number'=>12,'echo'=>false]),'basierend auf 12 Stimmen'));
t('_wp_posts_page_notice / block-editor-Hinweis',str_contains(out('_wp_posts_page_notice'),'notice-warning')&&(function(){ wp_register_script('wp-notices','n.js');_wp_block_editor_posts_page_notice();return str_contains(implode('',$GLOBALS['rrw_wp_scripts']['reg']['wp-notices']['inline_after']),'createWarningNotice'); })());

/* ───── Theme-Verwaltung ───── */
t('_get_template_edit_filename',_get_template_edit_filename('/srv/wp-content/themes/mein/style.css','/srv/wp-content/themes/mein/')==='/themes/mein/style.css');
t('get_theme_feature_list',isset(get_theme_feature_list()['Layout']['one-column'])&&isset(get_theme_feature_list(false)['Features'],get_theme_feature_list()['Subject']));
t('get_theme_update_available / theme_update_available (ohne Update)',get_theme_update_available(wp_get_theme('mein-theme'))===false&&out(fn()=>theme_update_available(wp_get_theme('mein-theme')))===''&&get_theme_update_available('kein-theme')===false);
set_site_transient('update_themes',(object)['response'=>['mein-theme'=>['new_version'=>'2.0']]]);
t('get_theme_update_available (Update)',str_contains((string)get_theme_update_available(wp_get_theme('mein-theme')),'Version 2.0')&&str_contains(out(fn()=>theme_update_available(wp_get_theme('mein-theme'))),'2.0'));
delete_site_transient('update_themes');
$pj=wp_prepare_themes_for_js(['mein-theme'=>wp_get_theme('mein-theme'),'neu-theme'=>wp_get_theme('neu-theme')]);
t('wp_prepare_themes_for_js',count($pj)===2&&$pj[0]['id']==='mein-theme'&&$pj[0]['name']==='Mein Theme'&&$pj[0]['version']==='1.0'&&$pj[0]['compatibleWP']===true&&$pj[1]['compatibleWP']===false&&isset($pj[0]['actions']['activate']));
t('customize_themes_print_templates ohne Ausgabe',out('customize_themes_print_templates')==='');
t('is_theme_paused / wp_get_theme_error',is_theme_paused('mein-theme')===false&&wp_get_theme_error('mein-theme')===false&&(function(){ update_option('rrw_paused_themes',['mein-theme'=>['type'=>1,'message'=>'Fehler']]);return is_theme_paused('mein-theme')&&wp_get_theme_error('mein-theme')['message']==='Fehler'; })());
$GLOBALS['pagenow']='index.php';
t('paused_themes_notice',str_contains(out('paused_themes_notice'),'notice-error'));
$GLOBALS['pagenow']='themes.php';
t('paused_themes_notice: nicht auf themes.php',out('paused_themes_notice')==='');
t('resume_theme',resume_theme('mein-theme')===true&&!is_theme_paused('mein-theme')&&resume_theme('mein-theme')===true);
t('resume_theme: nicht erfüllte Anforderungen',is_wp_error(resume_theme('neu-theme')));
t('delete_theme: Eingaben prüfen',delete_theme('')===false&&is_wp_error(delete_theme('../x'))&&is_wp_error(delete_theme('gibt-es-nicht'))&&true);
mkdir($th.'/weg');file_put_contents($th.'/weg/style.css',"/*\nTheme Name: Weg\n*/");file_put_contents($th.'/weg/a.php','');
$log=[];add_action('deleted_theme',function($s,$ok) use(&$log){ $log=[$s,$ok]; },10,2);
t('delete_theme: löscht Ordner',delete_theme('weg')===true&&!is_dir($th.'/weg')&&$log===['weg',true]);
update_option('stylesheet','mein-theme');update_option('template','mein-theme');
t('delete_theme: aktives Theme bleibt',delete_theme('mein-theme')->get_error_code()==='cannot_delete_active_theme'&&is_dir($th.'/mein-theme'));

/* ───── Block-Vorlagen und Theme-Vorschau ───── */
t('wp_enable_block_templates (klassisch: nichts)',(function(){ wp_enable_block_templates();return !current_theme_supports('block-templates'); })());
t('wp_filter_wp_template_unique_post_slug: fremder Typ',wp_filter_wp_template_unique_post_slug('','abc',1,'publish','post')==='');
$tp=wp_insert_post(['post_title'=>'Start','post_name'=>'start','post_type'=>'wp_template','post_status'=>'publish']);
t('wp_filter_wp_template_unique_post_slug: Zähler',wp_filter_wp_template_unique_post_slug('','start',0,'publish','wp_template')==='start-2'&&wp_filter_wp_template_unique_post_slug('','neu',0,'publish','wp_template')==='neu'&&wp_filter_wp_template_unique_post_slug('','start',$tp,'publish','wp_template')==='start');
t('wp_set_unique_slug_on_create_template_part',(function(){ $id=wp_insert_post(['post_title'=>'T','post_name'=>'kopf','post_type'=>'wp_template_part','post_status'=>'auto-draft']);wp_set_unique_slug_on_create_template_part($id);return get_post($id)->post_name!==''; })());
t('wp_enqueue_block_template_skip_link (klassisch: nichts)',!str_contains(out(function(){ wp_enqueue_block_template_skip_link();do_action('wp_footer'); }),'skip-link')&&!wp_style_is('wp-block-template-skip-link','enqueued'));
$_GET['wp_theme_preview']='neu-theme';
t('wp_get_theme_preview_path',wp_get_theme_preview_path('aktuell')==='neu-theme'&&(function(){ $_GET['wp_theme_preview']='gibt-es-nicht';$r=wp_get_theme_preview_path('aktuell');$_GET['wp_theme_preview']='neu-theme';return $r==='aktuell'; })());
wp_initialize_theme_preview_hooks();
t('wp_initialize_theme_preview_hooks: Stylesheet-Filter',get_stylesheet()==='neu-theme'&&get_template()==='neu-theme');
remove_filter('stylesheet','wp_get_theme_preview_path');remove_filter('template','wp_get_theme_preview_path');
t('wp_block_theme_activate_nonce',str_contains(out('wp_block_theme_activate_nonce'),'window.WP_BLOCK_THEME_ACTIVATE_NONCE = "'));
wp_register_script('wp-api-fetch','a.js');wp_attach_theme_preview_middleware();
t('wp_attach_theme_preview_middleware',str_contains(implode('',$GLOBALS['rrw_wp_scripts']['reg']['wp-api-fetch']['inline_after']),'createThemePreviewMiddleware( "neu-theme" )'));
unset($_GET['wp_theme_preview']);
t('wp_get_theme_preview_path ohne Parameter',wp_get_theme_preview_path('aktuell')==='aktuell');

system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

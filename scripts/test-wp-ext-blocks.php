<?php
// Prüft die ergänzenden Block-/REST-Funktionen (cms/wp/core/ext/blocks-*.php): Schema-Prüfung, Routen, Hooks, Serialisierung, Vorlagen,
// Block-Unterstützungen, Style-Engine, Muster, Bindungen. Aufruf: php scripts/test-wp-ext-blocks.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/rrw-extb-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/cms');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('RRW_WP_DATA',$tmp.'/cms/.wp');define('RRW_WP_CMS_DATA',$tmp.'/cms');$_SERVER['HTTP_HOST']='example.test';
file_put_contents($tmp.'/cms/site.json','{}');$GLOBALS['RRW_SITE']=[];
require __DIR__.'/_testdb.php';
require __DIR__.'/../cms/wp/load.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
rrw_wp_boot(['theme'=>false,'user'=>['id'=>1,'login'=>'admin','name'=>'Administration','email'=>'a@example.test','role'=>'administrator']]);

// Vollständigkeit: alle Funktionen der Liste existieren (bewusst ausgelassen: keine)
$names=preg_split('/\s+/',trim('
rest_api_init rest_api_register_rewrites rest_api_default_filters create_initial_rest_routes rest_api_loaded rest_ensure_request
rest_handle_deprecated_function rest_handle_deprecated_argument rest_handle_doing_it_wrong rest_send_cors_headers rest_handle_options_request
rest_send_allow_header _rest_array_intersect_key_recursive rest_output_rsd rest_output_link_wp_head rest_output_link_header rest_cookie_check_errors
rest_cookie_collect_status rest_application_password_collect_status rest_get_authenticated_app_password rest_application_password_check_errors
rest_add_application_passwords_to_index rest_parse_hex_color rest_parse_request_arg rest_is_array rest_sanitize_array rest_is_object
rest_sanitize_object rest_get_best_type_for_value rest_handle_multi_type_schema rest_validate_array_contains_unique_items rest_stabilize_value
rest_validate_json_schema_pattern rest_find_matching_pattern_property_schema rest_format_combining_operation_error rest_find_any_matching_schema
rest_find_one_matching_schema rest_are_values_equal rest_validate_enum rest_get_allowed_schema_keywords rest_validate_null_value_from_schema
rest_validate_boolean_value_from_schema rest_validate_object_value_from_schema rest_validate_array_value_from_schema
rest_validate_number_value_from_schema rest_validate_string_value_from_schema rest_validate_integer_value_from_schema rest_preload_api_request
rest_parse_embed_param rest_filter_response_by_context rest_default_additional_properties_to_false rest_get_route_for_post
rest_get_route_for_post_type_items rest_get_route_for_term rest_get_route_for_taxonomy_items rest_get_queried_resource_route
generate_block_asset_handle get_block_asset_url register_block_script_module_id register_block_script_handle register_block_style_handle
get_block_metadata_i18n_schema wp_register_block_types_from_metadata_collection wp_register_block_metadata_collection insert_hooked_blocks
set_ignored_hooked_blocks_metadata apply_block_hooks_to_content apply_block_hooks_to_content_from_post_object remove_serialized_parent_block
extract_serialized_parent_block update_ignored_hooked_blocks_postmeta insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata
insert_hooked_blocks_into_rest_response serialize_block_attributes get_comment_delimited_block_content resolve_pattern_blocks
_filter_block_content_callback filter_block_kses filter_block_kses_value filter_block_core_template_part_attributes excerpt_remove_footnotes
_excerpt_render_inner_blocks _restore_wpautop_hook block_version block_has_support wp_migrate_old_typography_shape get_query_pagination_arrow
get_comments_pagination_arrow _wp_filter_post_meta_footnotes _wp_footnotes_kses_init_filters _wp_footnotes_remove_filters _wp_footnotes_kses_init
_wp_footnotes_force_filtered_html_on_import_filter get_block_theme_folders get_allowed_block_template_part_areas get_default_block_template_types
_filter_block_template_part_area _get_block_templates_paths _get_block_template_file _get_block_templates_files _add_block_template_info
_add_block_template_part_area_info _flatten_blocks _inject_theme_attribute_in_template_part_block _remove_theme_attribute_from_template_part_block
_build_block_template_result_from_file _wp_build_title_and_description_for_single_post_type_block_template
_wp_build_title_and_description_for_taxonomy_block_template _build_block_template_object_from_post_object _build_block_template_result_from_post
block_template_part block_header_area block_footer_area wp_is_theme_directory_ignored wp_generate_block_templates_export_file get_template_hierarchy
inject_ignored_hooked_blocks_metadata_attributes get_default_block_categories get_block_categories get_allowed_block_types
get_default_block_editor_settings get_legacy_widget_block_editor_settings _wp_get_iframed_editor_assets wp_get_first_block
wp_get_post_content_block_attributes get_block_editor_settings block_editor_rest_api_preload get_block_editor_theme_styles
get_classic_theme_supports_block_editor_settings wp_initialize_site_preview_hooks _add_template_loader_filters wp_render_empty_block_template_warning
locate_block_template resolve_block_template _block_template_render_title_tag _block_template_viewport_meta_tag _strip_template_file_suffix
_block_template_render_without_post_block_context _resolve_template_for_new_post unregister_block_template wp_register_typography_support
wp_apply_typography_support wp_typography_get_preset_inline_style_value wp_render_typography_support wp_get_typography_value_and_unit
wp_get_computed_fluid_typography_value wp_get_typography_font_size_value wp_get_layout_definitions wp_register_layout_support wp_get_layout_style
wp_render_layout_support_flag wp_add_parent_layout_to_parsed_block wp_restore_group_inner_container wp_restore_image_outer_container
wp_add_global_styles_for_blocks wp_get_block_name_from_theme_json_path wp_clean_theme_json_cache wp_get_theme_data_custom_templates
wp_get_theme_data_template_parts wp_get_block_css_selector _register_core_block_patterns_and_categories wp_normalize_remote_block_pattern
_load_remote_block_patterns _load_remote_featured_patterns _register_remote_theme_patterns _register_theme_block_patterns
wp_get_block_style_variation_name_from_class wp_resolve_block_style_variation_ref_values wp_render_block_style_variation_support_styles
wp_render_block_style_variation_class_name wp_enqueue_block_style_variation_styles wp_register_block_style_variations_from_theme_json_partials
wp_get_elements_class_name wp_should_add_elements_class_name wp_render_elements_support_styles wp_render_elements_class_name
_wp_get_presets_class_name _wp_add_block_level_presets_class _wp_add_block_level_preset_styles wp_register_dimensions_support
wp_apply_dimensions_support wp_render_dimensions_support wp_register_border_support wp_apply_border_support wp_has_border_feature_support
wp_get_block_default_classname wp_apply_generated_classname_support wp_register_custom_classname_support wp_apply_custom_classname_support
wp_register_shadow_support wp_apply_shadow_support wp_register_position_support wp_render_position_support wp_register_spacing_support
wp_apply_spacing_support wp_register_aria_label_support wp_apply_aria_label_support wp_register_background_support wp_render_background_support
wp_register_colors_support wp_apply_colors_support wp_register_alignment_support wp_apply_alignment_support _block_bindings_post_meta_get_value
_register_block_bindings_post_meta_source _block_bindings_pattern_overrides_get_value _register_block_bindings_pattern_overrides_source
wp_style_engine_get_stylesheet_from_css_rules wp_style_engine_get_stylesheet_from_context wp_should_skip_block_supports_serialization
'));
$missing=array_values(array_filter($names,fn($f)=>!function_exists($f)));
t('Alle '.count($names).' Funktionen vorhanden',$missing===[],implode(',',$missing));

/* ───── REST: Schema-Prüfung ───── */
t('rest_is_array: Liste',rest_is_array([1,2])&&rest_is_array('a,b')&&!rest_is_array(['a'=>1]));
t('rest_sanitize_array: Text → Liste',rest_sanitize_array('1, 2,3')===['1','2','3']);
t('rest_is_object / sanitize',rest_is_object(['a'=>1])&&rest_is_object('')&&rest_sanitize_object('')===[]&&rest_sanitize_object((object)['a'=>1])===['a'=>1]);
t('rest_parse_hex_color',rest_parse_hex_color('#fff')==='#fff'&&rest_parse_hex_color('#123456')==='#123456'&&rest_parse_hex_color('123')===false&&rest_parse_hex_color('#12')===false);
t('rest_get_best_type_for_value',rest_get_best_type_for_value(5,['string','integer'])==='integer'&&rest_get_best_type_for_value('x',['integer','string'])==='string'&&rest_get_best_type_for_value('',['array','string'])==='string'&&rest_get_best_type_for_value([1],['string'])==='');
t('rest_are_values_equal',rest_are_values_equal(1,1.0)&&rest_are_values_equal([1,[2]],[1,[2]])&&!rest_are_values_equal([1],[1,2])&&!rest_are_values_equal('1',1));
t('rest_validate_array_contains_unique_items',rest_validate_array_contains_unique_items([1,2,3])&&!rest_validate_array_contains_unique_items([1,[2],[2]]));
t('rest_stabilize_value sortiert Schlüssel',array_keys(rest_stabilize_value(['b'=>1,'a'=>['d'=>1,'c'=>2]]))===['a','b']&&array_keys(rest_stabilize_value(['b'=>1,'a'=>['d'=>1,'c'=>2]])['a'])===['c','d']);
t('rest_validate_json_schema_pattern',rest_validate_json_schema_pattern('^a.c$','abc')&&!rest_validate_json_schema_pattern('^a.c$','abd')&&rest_validate_json_schema_pattern('#x','a#x'));
t('rest_find_matching_pattern_property_schema',rest_find_matching_pattern_property_schema('x1',['patternProperties'=>['^x\d$'=>['type'=>'string']]])===['type'=>'string']&&rest_find_matching_pattern_property_schema('y',['patternProperties'=>['^x'=>[]]])===null);
t('rest_validate_enum',rest_validate_enum('a',['enum'=>['a','b']],'p')===true&&rest_validate_enum('c',['enum'=>['a','b']],'p') instanceof WP_Error&&rest_validate_enum(1.0,['enum'=>[1]],'p')===true);
t('Zahl: Grenzen und Vielfaches',rest_validate_number_value_from_schema(5,['minimum'=>1,'maximum'=>9],'n')===true&&is_wp_error(rest_validate_number_value_from_schema(0,['minimum'=>1],'n'))&&is_wp_error(rest_validate_number_value_from_schema(1,['minimum'=>1,'exclusiveMinimum'=>true],'n'))&&is_wp_error(rest_validate_number_value_from_schema(7,['multipleOf'=>5],'n'))&&rest_validate_number_value_from_schema(10,['multipleOf'=>5],'n')===true);
t('Ganzzahl',rest_validate_integer_value_from_schema(3,['type'=>'integer'],'i')===true&&is_wp_error(rest_validate_integer_value_from_schema(3.5,['type'=>'integer'],'i'))&&is_wp_error(rest_validate_integer_value_from_schema('x',['type'=>'integer'],'i')));
t('Text: Länge, Muster, Format',rest_validate_string_value_from_schema('abc',['minLength'=>2,'maxLength'=>5],'s')===true&&is_wp_error(rest_validate_string_value_from_schema('a',['minLength'=>2],'s'))&&is_wp_error(rest_validate_string_value_from_schema('abcdef',['maxLength'=>5],'s'))&&is_wp_error(rest_validate_string_value_from_schema('x',['pattern'=>'^a'],'s'))&&is_wp_error(rest_validate_string_value_from_schema('kein-mail',['format'=>'email'],'s'))&&rest_validate_string_value_from_schema('a@b.de',['format'=>'email'],'s')===true&&is_wp_error(rest_validate_string_value_from_schema('#xyz',['format'=>'hex-color'],'s')));
t('null und boolean',rest_validate_null_value_from_schema(null,'p')===true&&is_wp_error(rest_validate_null_value_from_schema(0,'p'))&&rest_validate_boolean_value_from_schema('true','p')===true&&is_wp_error(rest_validate_boolean_value_from_schema('ja','p')));
$sch=['type'=>'object','required'=>['name'],'properties'=>['name'=>['type'=>'string'],'tags'=>['type'=>'array','items'=>['type'=>'integer'],'maxItems'=>2,'uniqueItems'=>true]],'additionalProperties'=>false];
t('Objekt gültig',rest_validate_object_value_from_schema(['name'=>'x','tags'=>[1,2]],$sch,'o')===true);
t('Objekt: Pflichtfeld fehlt',rest_validate_object_value_from_schema([],$sch,'o')->get_error_code()==='rest_property_required');
t('Objekt: zusätzliche Eigenschaft',rest_validate_object_value_from_schema(['name'=>'x','z'=>1],$sch,'o')->get_error_code()==='rest_additional_properties_forbidden');
t('Objekt: verschachtelter Fehler',rest_validate_object_value_from_schema(['name'=>'x','tags'=>['a']],$sch,'o')->get_error_code()==='rest_invalid_type');
t('Liste: zu viele / doppelt',rest_validate_array_value_from_schema([1,2,3],$sch['properties']['tags'],'a')->get_error_code()==='rest_too_many_items'&&rest_validate_array_value_from_schema([1,1],$sch['properties']['tags'],'a')->get_error_code()==='rest_duplicate_items');
$any=['anyOf'=>[['type'=>'integer'],['type'=>'string','minLength'=>3]]];
t('anyOf: passende Variante',rest_find_any_matching_schema(5,$any,'p')===['type'=>'integer']&&rest_find_any_matching_schema('abcd',$any,'p')['type']==='string');
t('anyOf: keine Variante',is_wp_error(rest_find_any_matching_schema('a',$any,'p')));
$one=['oneOf'=>[['type'=>'number','minimum'=>0],['type'=>'number','maximum'=>10]]];
t('oneOf: mehrere Treffer',rest_find_one_matching_schema(5,$one,'p')->get_error_code()==='rest_one_of_multiple_matches'&&rest_find_one_matching_schema(50,$one,'p')===['type'=>'number','minimum'=>0]);
t('rest_format_combining_operation_error',rest_format_combining_operation_error('p',['index'=>1,'schema'=>['title'=>'Zahl'],'error_object'=>new WP_Error('x','kaputt')])->get_error_data()==['position'=>1]);
t('rest_handle_multi_type_schema',rest_handle_multi_type_schema('5',['type'=>['integer','string']],'p')==='integer'&&rest_handle_multi_type_schema([],['type'=>['string','array']],'p')==='array');
t('rest_get_allowed_schema_keywords',in_array('patternProperties',rest_get_allowed_schema_keywords(),true));
$sc=['type'=>'object','properties'=>['a'=>['type'=>'string','context'=>['view']],'b'=>['type'=>'string','context'=>['edit']],'c'=>['type'=>'string']]];
t('rest_filter_response_by_context',rest_filter_response_by_context(['a'=>1,'b'=>2,'c'=>3],$sc,'view')===['a'=>1,'c'=>3]);
$def=rest_default_additional_properties_to_false(['type'=>'object','properties'=>['x'=>['type'=>'object','properties'=>[]]]]);
t('rest_default_additional_properties_to_false',$def['additionalProperties']===false&&$def['properties']['x']['additionalProperties']===false);
t('rest_parse_embed_param',rest_parse_embed_param('')===true&&rest_parse_embed_param('true')===true&&rest_parse_embed_param('author,replies')===['author','replies']);
t('_rest_array_intersect_key_recursive',_rest_array_intersect_key_recursive(['a'=>1,'b'=>['c'=>1,'d'=>2]],['b'=>['c'=>0]])===['b'=>['c'=>1]]);

/* ───── REST: Routen, Header, Allow ───── */
$p=wp_insert_post(['post_title'=>'Beispiel','post_content'=>'<!-- wp:paragraph --><p>Hallo</p><!-- /wp:paragraph -->','post_status'=>'publish','post_type'=>'post']);
$GLOBALS['wp_post_types']['post']->show_in_rest=true;
t('rest_get_route_for_post_type_items',rest_get_route_for_post_type_items('post')==='/wp/v2/post');
$GLOBALS['wp_post_types']['post']->rest_base='posts';
t('rest_get_route_for_post',rest_get_route_for_post($p)==='/wp/v2/posts/'.$p&&rest_get_route_for_post(999999)==='');
t('Route unbekannter Typ leer',rest_get_route_for_post_type_items('gibtsnicht')==='');
t('rest_get_route_for_taxonomy_items ohne show_in_rest leer',rest_get_route_for_taxonomy_items('post_tag')===''||str_starts_with(rest_get_route_for_taxonomy_items('post_tag'),'/wp/v2/'));
add_filter('rest_route_for_post',fn($r)=>$r.'?x=1');
t('Filter rest_route_for_post',str_ends_with(rest_get_route_for_post($p),'?x=1'));
remove_all_filters('rest_route_for_post');
$m=rrw_wp_rest_endpoint_methods(['methods'=>'GET, POST']);t('Methoden-Liste',$m===['GET','POST']);
$req=new WP_REST_Request('OPTIONS','/wp/v2/posts');$o=rest_handle_options_request(null,null,$req);
t('rest_handle_options_request liefert Allow',$o instanceof WP_REST_Response&&str_contains((string)($o->get_headers()['Allow']??''),'GET'));
t('rest_handle_options_request: GET unberührt',rest_handle_options_request(null,null,new WP_REST_Request('GET','/wp/v2/posts'))===null);
$resp=new WP_REST_Response(['a'=>1]);$resp->set_matched_route('/wp/v2/posts');$r2=rest_send_allow_header($resp,rest_get_server(),new WP_REST_Request('GET','/wp/v2/posts'));
t('rest_send_allow_header',str_contains((string)($r2->get_headers()['Allow']??''),'GET'));
$_SERVER['HTTP_ORIGIN']='http://example.test';$GLOBALS['rrw_wp_rest_sent_headers']=[];rest_send_cors_headers(true);
t('rest_send_cors_headers',(bool)array_filter($GLOBALS['rrw_wp_rest_sent_headers'],fn($h)=>str_starts_with($h,'Access-Control-Allow-Origin: http://example.test')));
unset($_SERVER['HTTP_ORIGIN']);
t('rest_cookie_check_errors ohne Nonce: unangemeldet',(function(){ unset($_REQUEST['_wpnonce'],$_SERVER['HTTP_X_WP_NONCE']);$GLOBALS['wp_rest_auth_cookie']=true;return rest_cookie_check_errors(null)===true; })());
t('rest_cookie_check_errors falscher Nonce → 403',(function(){ $_SERVER['HTTP_X_WP_NONCE']='falsch';$r=rest_cookie_check_errors(null);unset($_SERVER['HTTP_X_WP_NONCE']);return is_wp_error($r)&&$r->get_error_code()==='rest_cookie_invalid_nonce'; })());
t('rest_cookie_check_errors reicht Vorergebnis durch',rest_cookie_check_errors('x')==='x');
rest_application_password_collect_status(new WP_Error('bad','Falsch'),['uuid'=>'u-1']);
t('Anwendungspasswort: Fehler mit Status 401',rest_application_password_check_errors(null)->get_error_data()['status']===401&&rest_get_authenticated_app_password()==='u-1');
rest_application_password_collect_status(true,[]);t('Anwendungspasswort: ok',rest_application_password_check_errors(null)===null&&rest_get_authenticated_app_password()===null);
t('rest_output_link_wp_head',(function(){ ob_start();rest_output_link_wp_head();return str_contains(ob_get_clean(),'api.w.org'); })());
t('rest_output_rsd',(function(){ ob_start();rest_output_rsd();return str_contains(ob_get_clean(),'apiLink'); })());
$pre=rest_preload_api_request([],'/wp/v2/posts?per_page=1');
t('rest_preload_api_request',isset($pre['/wp/v2/posts?per_page=1']['body'])&&count($pre['/wp/v2/posts?per_page=1']['body'])===1);
t('rest_ensure_request',rest_ensure_request('/wp/v2/posts') instanceof WP_REST_Request&&rest_ensure_request('/x')->get_route()==='/x');
$GLOBALS['rrw_wp_rest_sent_headers']=[];rest_handle_doing_it_wrong('f','Meldung','6.0');t('Hinweise nur bei WP_DEBUG',$GLOBALS['rrw_wp_rest_sent_headers']===[]);

/* ───── Blöcke: Handles, Serialisierung, Hooks ───── */
t('generate_block_asset_handle',generate_block_asset_handle('core/paragraph','editorScript')==='wp-block-paragraph-editor'&&generate_block_asset_handle('core/image','viewScript',1)==='wp-block-image-view-2'&&generate_block_asset_handle('my/block','editorStyle')==='my-block-editor-style'&&generate_block_asset_handle('a/b','script',2)==='a-b-script-3');
t('get_block_asset_url',str_ends_with((string)get_block_asset_url(WP_CONTENT_DIR.'/plugins/p/build/x.js'),'/plugins/p/build/x.js')&&get_block_asset_url('')===false);
$bd=$tmp.'/wp-content/plugins/demo/blk';mkdir($bd.'/build',0777,true);file_put_contents($bd.'/build/index.js','//x');file_put_contents($bd.'/build/index.asset.php',"<?php return ['dependencies'=>['wp-blocks','wp-i18n'],'version'=>'abc'];");file_put_contents($bd.'/build/style.css','.a{}');
$meta=['name'=>'demo/blk','file'=>$bd.'/block.json','editorScript'=>'file:./build/index.js','style'=>'file:./build/style.css','viewScript'=>'bereits-handle','textdomain'=>'demo'];
t('register_block_script_handle',register_block_script_handle($meta,'editorScript')==='demo-blk-editor-script'&&wp_script_is('demo-blk-editor-script','registered')&&$GLOBALS['rrw_wp_scripts']['reg']['demo-blk-editor-script']['deps']===['wp-blocks','wp-i18n']&&$GLOBALS['rrw_wp_scripts']['reg']['demo-blk-editor-script']['ver']==='abc');
t('register_block_script_handle: vorhandener Handle',register_block_script_handle($meta,'viewScript')==='bereits-handle'&&register_block_script_handle($meta,'fehlt')===false);
t('register_block_style_handle',register_block_style_handle($meta,'style')==='demo-blk-style'&&wp_style_is('demo-blk-style','registered'));
t('register_block_script_module_id',register_block_script_module_id(['name'=>'demo/blk','file'=>$bd.'/block.json','viewScriptModule'=>'file:./build/index.js'],'viewScriptModule')==='demo-blk-view-script-module'&&register_block_script_module_id(['viewScriptModule'=>'mod/id'],'viewScriptModule')==='mod/id');
$i18n=get_block_metadata_i18n_schema();t('get_block_metadata_i18n_schema',$i18n->title==='block title'&&$i18n->keywords===['block keyword']);
t('serialize_block_attributes maskiert',serialize_block_attributes(['a'=>'<b>--"x"&'])===str_replace('@','\u','{"a":"@003cb@003e@002d@002d@0022x@0022@0026"}'));
t('get_comment_delimited_block_content',get_comment_delimited_block_content('core/paragraph',['align'=>'left'],'<p>x</p>')==='<!-- wp:paragraph {"align":"left"} --><p>x</p><!-- /wp:paragraph -->'&&get_comment_delimited_block_content('core/spacer',[],'')==='<!-- wp:spacer /-->'&&get_comment_delimited_block_content(null,[],'frei')==='frei');
$ser='<!-- wp:group {"a":1} --><div><!-- wp:paragraph --><p>x</p><!-- /wp:paragraph --></div><!-- /wp:group -->';
t('remove_/extract_serialized_parent_block',remove_serialized_parent_block($ser)==='<div><!-- wp:paragraph --><p>x</p><!-- /wp:paragraph --></div>'&&extract_serialized_parent_block($ser)==='<!-- wp:group {"a":1} --><!-- /wp:group -->');
$hook=function($types,$pos,$anchor,$ctx){ if($anchor==='core/post-content'&&$pos==='first_child')$types[]='core/heading';if($anchor==='core/paragraph'&&$pos==='after')$types[]='core/spacer';if($anchor==='core/group'&&$pos==='first_child')$types[]='core/heading';if($anchor==='core/group'&&$pos==='last_child')$types[]='core/separator';return $types; };
add_filter('hooked_block_types',$hook,10,4);
$h=apply_block_hooks_to_content($ser);
t('Hooks: after',str_contains($h,'<!-- /wp:paragraph --><!-- wp:spacer /-->'));
t('Hooks: first_child und last_child',str_contains($h,'<div><!-- wp:heading /--><!-- wp:paragraph -->')&&str_contains($h,'<!-- wp:separator /--></div>'));
$h2=apply_block_hooks_to_content($ser,null,'insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata');
t('Hooks: ignoredHookedBlocks wird gesetzt',str_contains($h2,'"ignoredHookedBlocks":["core/spacer"]')&&str_contains($h2,'"ignoredHookedBlocks":["core/heading","core/separator"]'));
$h3=apply_block_hooks_to_content($h2);
t('Hooks: ignorierte Blöcke werden nicht erneut eingefügt',substr_count($h3,'wp:spacer')===substr_count($h2,'wp:spacer')&&substr_count($h3,'wp:heading')===substr_count($h2,'wp:heading'));
$a=['blockName'=>'core/paragraph','attrs'=>[],'innerBlocks'=>[],'innerContent'=>[]];
t('set_ignored_hooked_blocks_metadata gibt leeren Text zurück',set_ignored_hooked_blocks_metadata($a,'after',[],null)===''&&$a['attrs']['metadata']['ignoredHookedBlocks']===['core/spacer']);
add_filter('hooked_block_core/spacer',fn()=>null);
$a=['blockName'=>'core/paragraph','attrs'=>[],'innerBlocks'=>[],'innerContent'=>[]];
t('Filter hooked_block_{typ} kann Einfügen verhindern',insert_hooked_blocks($a,'after',[],null)==='');
remove_all_filters('hooked_block_core/spacer');
$post=new WP_Post((object)['ID'=>$p,'post_content'=>'<!-- wp:paragraph --><p>y</p><!-- /wp:paragraph -->','post_type'=>'post','post_status'=>'publish']);
update_ignored_hooked_blocks_postmeta($post);
t('update_ignored_hooked_blocks_postmeta: Meta des Wurzelblocks und Anker-Attribute',json_decode((string)get_post_meta($p,'_wp_ignored_hooked_blocks',true),true)===['core/heading']&&str_contains($post->post_content,'"ignoredHookedBlocks":["core/spacer"]')&&!str_contains($post->post_content,'post-content'));
$resp=new WP_REST_Response(['content'=>['raw'=>'<!-- wp:paragraph --><p>z</p><!-- /wp:paragraph -->','rendered'=>'<p>z</p>']]);
$resp=insert_hooked_blocks_into_rest_response($resp,get_post($p));
t('insert_hooked_blocks_into_rest_response',str_contains($resp->data['content']['raw'],'wp:spacer'));
remove_filter('hooked_block_types',$hook,10);
$cc=(object)['post_content'=>'<!-- wp:paragraph --><p>q</p><!-- /wp:paragraph -->'];
t('inject_ignored_hooked_blocks_metadata_attributes ohne Hooks unverändert',inject_ignored_hooked_blocks_metadata_attributes($cc)->post_content===$cc->post_content);
// Besucher mit eigenem Filter sehen den Anker-Typ
t('apply_block_hooks_to_content ohne Hooks: gleicher Inhalt',apply_block_hooks_to_content('<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->')==='<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->');

/* ───── Blöcke: Muster, KSES, Auszug, Hilfen ───── */
register_block_pattern('t/inner',['title'=>'Innen','content'=>'<!-- wp:paragraph --><p>Muster</p><!-- /wp:paragraph -->']);
register_block_pattern('t/rekursiv',['title'=>'R','content'=>'<!-- wp:pattern {"slug":"t/rekursiv"} /-->']);
$res=resolve_pattern_blocks(parse_blocks('<!-- wp:group --><div class="wp-block-group"><!-- wp:pattern {"slug":"t/inner"} /--></div><!-- /wp:group --><!-- wp:pattern {"slug":"t/unbekannt"} /-->'));
t('resolve_pattern_blocks: verschachtelt',$res[0]['innerBlocks'][0]['blockName']==='core/paragraph'&&count($res[0]['innerContent'])===3&&$res[0]['innerContent'][1]===null);
t('resolve_pattern_blocks: unbekannt bleibt',$res[1]['blockName']==='core/pattern');
t('resolve_pattern_blocks: Rekursion wird abgebrochen',resolve_pattern_blocks(parse_blocks('<!-- wp:pattern {"slug":"t/rekursiv"} /-->'))===[]);
t('_filter_block_content_callback',_filter_block_content_callback([1=>' wp:x --'])===  '<!-- wp:x -->');
$blk=['blockName'=>'core/paragraph','attrs'=>['content'=>'<script>x</script><b>ok</b>'],'innerBlocks'=>[],'innerHTML'=>'','innerContent'=>['<p onclick="x">a<script>1</script></p>']];
$k=filter_block_kses($blk,'post');
t('filter_block_kses: Attribute und Inhalt',!str_contains($k['attrs']['content'],'<script')&&str_contains($k['attrs']['content'],'<b>ok</b>')&&!str_contains($k['innerContent'][0],'onclick'));
t('filter_block_kses_value rekursiv',filter_block_kses_value(['a'=>['b'=>'<script>x</script>t']],'post')===['a'=>['b'=>'xt']]||!str_contains(json_encode(filter_block_kses_value(['a'=>['b'=>'<script>x</script>t']],'post')),'script'));
t('filter_block_core_template_part_attributes',filter_block_core_template_part_attributes(['slug'=>'ko/pf','tagName'=>'script','theme'=>'t"x'],'core/template-part')===['slug'=>'ko/pf','theme'=>'tx']&&filter_block_core_template_part_attributes(['tagName'=>'script'],'core/paragraph')===['tagName'=>'script']);
t('excerpt_remove_footnotes',excerpt_remove_footnotes('Text<sup data-fn="abc" class="fn"><a href="#abc" id="abc-link">1</a></sup> Ende')==='Text Ende');
t('_excerpt_render_inner_blocks',_excerpt_render_inner_blocks(parse_blocks('<!-- wp:group --><div><!-- wp:paragraph --><p>erlaubt</p><!-- /wp:paragraph --><!-- wp:heading --><h2>nein</h2><!-- /wp:heading --></div><!-- /wp:group -->')[0],['core/paragraph'])==='<p>erlaubt</p>');
t('block_version',block_version('<!-- wp:a /-->')===1&&block_version('<p>x</p>')===0);
$bt=new WP_Block_Type('t/s',['supports'=>['color'=>['text'=>true],'spacing'=>['margin'=>true],'typography'=>['fontSize'=>true]]]);
t('block_has_support',block_has_support($bt,'color')&&block_has_support($bt,['spacing','margin'])&&!block_has_support($bt,['spacing','padding'])&&!block_has_support($bt,'gibtsnicht')&&block_has_support($bt,'x',false)===false&&block_has_support(null,'color',false)===false);
$mm=wp_migrate_old_typography_shape(['name'=>'a/b','supports'=>['fontSize'=>true,'lineHeight'=>true,'color'=>true]]);
t('wp_migrate_old_typography_shape',$mm['supports']['typography']['fontSize']===true&&$mm['supports']['typography']['lineHeight']===true&&!isset($mm['supports']['fontSize']));
$fake=new class{ public $context=['paginationArrow'=>'arrow','comments/paginationArrow'=>'chevron']; };
t('Pagination-Pfeile',str_contains((string)get_query_pagination_arrow($fake,true),'→')&&str_contains((string)get_query_pagination_arrow($fake,false),'←')&&str_contains((string)get_comments_pagination_arrow($fake,'next'),'»')&&get_query_pagination_arrow(new class{ public $context=[]; },true)===null);
t('_wp_filter_post_meta_footnotes',_wp_filter_post_meta_footnotes(json_encode([['id'=>'a','content'=>'<script>x</script>t'],['id'=>'','content'=>'ohne id']]))===json_encode([['id'=>'a','content'=>'xt']])||!str_contains(_wp_filter_post_meta_footnotes(json_encode([['id'=>'a','content'=>'<script>x</script>t']])),'script'));
t('_wp_footnotes: Filter an/aus',(function(){ _wp_footnotes_kses_init_filters();$a=has_filter('sanitize_post_meta_footnotes','_wp_filter_post_meta_footnotes')!==false;_wp_footnotes_remove_filters();return $a&&has_filter('sanitize_post_meta_footnotes','_wp_filter_post_meta_footnotes')===false; })());
t('WP_Block_Metadata_Registry + Sammlung',(function() use($tmp){
    $d=$tmp.'/wp-content/plugins/coll';mkdir($d.'/a',0777,true);file_put_contents($d.'/manifest.php',"<?php return ['a'=>['name'=>'coll/a','title'=>'Eins'],'b'=>['name'=>'coll/b','title'=>'Zwei']];");
    wp_register_block_types_from_metadata_collection($d,$d.'/manifest.php');
    $r=WP_Block_Type_Registry::get_instance();return $r->is_registered('coll/b')&&$r->get_registered('coll/b')->title==='Zwei';
})());

/* ───── Vorlagen ───── */
$td=$tmp.'/wp-content/themes/bt';mkdir($td.'/templates',0777,true);mkdir($td.'/parts',0777,true);
file_put_contents($td.'/style.css',"/*\nTheme Name: BT\nVersion: 1\n*/");file_put_contents($td.'/theme.json','{"version":3,"customTemplates":[{"name":"landing","title":"Landung","postTypes":["page"]}],"templateParts":[{"name":"header","title":"Kopf","area":"header"}],"settings":{"layout":{"contentSize":"600px","wideSize":"900px"}}}');
file_put_contents($td.'/templates/index.html','<!-- wp:template-part {"slug":"header"} /--><!-- wp:post-content /-->');file_put_contents($td.'/templates/landing.html','<!-- wp:post-content /-->');file_put_contents($td.'/templates/single.html','<!-- wp:post-title /-->');
file_put_contents($td.'/parts/header.html','<!-- wp:site-title /-->');file_put_contents($td.'/parts/footer.html','<!-- wp:paragraph --><p>Fuß</p><!-- /wp:paragraph -->');
update_option('template','bt');update_option('stylesheet','bt');
t('get_block_theme_folders',get_block_theme_folders('bt')===['wp_template'=>'templates','wp_template_part'=>'parts']&&get_block_theme_folders('gibtsnicht')['wp_template']==='templates');
t('get_allowed_block_template_part_areas',array_column(get_allowed_block_template_part_areas(),'area')===['uncategorized','header','footer']);
t('get_default_block_template_types',isset(get_default_block_template_types()['404'])&&count(get_default_block_template_types())===16);
t('_filter_block_template_part_area',_filter_block_template_part_area('header')==='header'&&_filter_block_template_part_area('unsinn')==='uncategorized');
t('_get_block_templates_paths',count(_get_block_templates_paths($td.'/templates'))===3);
t('wp_get_theme_data_custom_templates / template_parts',(wp_get_theme_data_custom_templates()['landing']['title']??'')==='Landung'&&(wp_get_theme_data_template_parts()['header']['area']??'')==='header');
$f=_get_block_template_file('wp_template','landing');
t('_get_block_template_file mit Titel aus theme.json',$f['theme']==='bt'&&$f['title']==='Landung'&&_get_block_template_file('wp_template','fehlt')===null&&_get_block_template_file('post','x')===null);
$ff=_get_block_templates_files('wp_template_part');
t('_get_block_templates_files: Teile mit Bereich',count($ff)===2&&array_column($ff,'area','slug')===['footer'=>'uncategorized','header'=>'header']);
t('_get_block_templates_files: Filter',count(_get_block_templates_files('wp_template',['slug__in'=>['index']]))===1&&count(_get_block_templates_files('wp_template_part',['area'=>'header']))===1);
$tpl=_build_block_template_result_from_file($ff[1]['slug']==='header'?$ff[1]:$ff[0],'wp_template_part');
t('_build_block_template_result_from_file',$tpl instanceof WP_Block_Template&&$tpl->id==='bt//header'&&$tpl->area==='header'&&$tpl->source==='theme'&&$tpl->has_theme_file===true);
$tidx=_build_block_template_result_from_file(['slug'=>'index','path'=>$td.'/templates/index.html','theme'=>'bt','type'=>'wp_template'],'wp_template');
t('Template-Part-Block erhält Theme-Attribut',str_contains($tidx->content,'"theme":"bt"')&&$tidx->is_custom===false&&$tidx->title==='Index');
$bl=parse_blocks('<!-- wp:template-part {"slug":"x"} /--><!-- wp:group --><div><!-- wp:template-part {"slug":"y"} /--></div><!-- /wp:group -->');
foreach(_flatten_blocks($bl) as &$fb)_inject_theme_attribute_in_template_part_block($fb);unset($fb);
t('_flatten_blocks + _inject_theme_attribute…',$bl[0]['attrs']['theme']==='bt'&&$bl[1]['innerBlocks'][0]['attrs']['theme']==='bt');
foreach(_flatten_blocks($bl) as &$fb)_remove_theme_attribute_from_template_part_block($fb);unset($fb);
t('_remove_theme_attribute_from_template_part_block',!isset($bl[0]['attrs']['theme'])&&!isset($bl[1]['innerBlocks'][0]['attrs']['theme']));
ob_start();block_header_area();block_footer_area();$o=ob_get_clean();
t('block_header_area / block_footer_area',str_contains($o,'Fuß'));
t('wp_is_theme_directory_ignored',wp_is_theme_directory_ignored('node_modules/x')&&wp_is_theme_directory_ignored('.git')&&!wp_is_theme_directory_ignored('templates'));
t('get_template_hierarchy',get_template_hierarchy('index')===['index']&&get_template_hierarchy('front-page')===['front-page','home','index']&&get_template_hierarchy('page')===['page','singular','index']&&get_template_hierarchy('category-news',false,'category')===['category-news','category','archive','index']&&get_template_hierarchy('x',true)===['page','singular','index']&&get_template_hierarchy('single-post','','single')===['single-post','single','singular','index']);
t('_strip_template_file_suffix',_strip_template_file_suffix('single.php')==='single'&&_strip_template_file_suffix('a.html')==='a'&&_strip_template_file_suffix('b')==='b');
t('Titel-Tag und Viewport',(function(){ ob_start();_block_template_viewport_meta_tag();$v=ob_get_clean();ob_start();_block_template_render_title_tag();$tt=ob_get_clean();return str_contains($v,'viewport')&&str_starts_with($tt,'<title>'); })());
t('_block_template_render_without_post_block_context',_block_template_render_without_post_block_context(['postType'=>'wp_template','postId'=>1])===[]&&_block_template_render_without_post_block_context(['postType'=>'post'])===['postType'=>'post']);
$rt=resolve_block_template('wp_template',['single-post.php','single.php','index.php'],'');
t('resolve_block_template: spezifischste Vorlage',$rt instanceof WP_Block_Template&&$rt->slug==='single');
t('resolve_block_template: PHP-Vorlage gewinnt bei Gleichstand',resolve_block_template('wp_template',['single.php','index.php'],'/x/single.php')===null);
t('resolve_block_template: leer/ohne Typ',resolve_block_template('',[],'')===null);
$GLOBALS['wp_query']=new WP_Query();
t('locate_block_template setzt Vorlage',locate_block_template('','single',['single-post.php','single.php'])!==''&&($GLOBALS['_wp_current_template_id']??'')==='bt//single');
t('wp_get_post_content_block_attributes',(function(){ $GLOBALS['_wp_current_template_content']='<!-- wp:group --><div><!-- wp:post-content {"layout":{"type":"constrained"}} /--></div><!-- /wp:group -->';return wp_get_post_content_block_attributes()===['layout'=>['type'=>'constrained']]; })());
t('wp_get_first_block',wp_get_first_block(parse_blocks('<!-- wp:group --><div><!-- wp:heading --><h2>x</h2><!-- /wp:heading --></div><!-- /wp:group -->'),'core/heading')['blockName']==='core/heading'&&wp_get_first_block([],'core/x')===[]);
register_block_template('plug//foo',['title'=>'Foo','content'=>'<!-- wp:paragraph /-->']);
$ur=unregister_block_template('plug//foo');
t('unregister_block_template',$ur instanceof WP_Block_Template&&$ur->title==='Foo'&&is_wp_error(unregister_block_template('plug//foo')));
t('wp_render_empty_block_template_warning',str_contains(wp_render_empty_block_template_warning(''),'leer')&&wp_render_empty_block_template_warning('<p>x</p>')==='<p>x</p>');
t('_add_template_loader_filters ohne Unterstützung wirkungslos',(function(){ _add_template_loader_filters();return true; })());
$zip=wp_generate_block_templates_export_file();
t('wp_generate_block_templates_export_file',is_wp_error($zip)||(is_string($zip)&&is_file($zip)&&(function() use($zip){ $z=new ZipArchive();$z->open($zip);$ok=$z->locateName('templates/index.html')!==false&&$z->locateName('style.css')!==false;$z->close();@unlink($zip);return $ok; })()));
$po=get_post($p);$po->post_type='wp_template';$po->post_name='single';$po->post_title='Eigene';
$res=_build_block_template_result_from_post($po);
t('_build_block_template_result_from_post',$res instanceof WP_Block_Template&&$res->source==='custom'&&$res->id==='bt//single'&&$res->has_theme_file===true&&$res->is_custom===false);
t('_build_block_template_object_from_post_object ohne Theme',is_wp_error(_build_block_template_object_from_post_object($po,[])));
$tt=new WP_Block_Template();t('_wp_build_title…single_post_type',_wp_build_title_and_description_for_single_post_type_block_template('post','gibtsnicht',$tt)===false&&str_contains($tt->title,'gibtsnicht'));
$tt=new WP_Block_Template();t('_wp_build_title…taxonomy',_wp_build_title_and_description_for_taxonomy_block_template('category','gibtsnicht',$tt)===false&&str_contains($tt->title,'gibtsnicht'));

/* ───── Block-Editor ───── */
t('get_default_block_categories',array_column(get_default_block_categories(),'slug')===['text','media','design','widgets','theme','embed','reusable']);
add_filter('block_categories_all',function($c,$ctx){ $c[]=['slug'=>'extra','title'=>'Extra'];return $c; },10,2);
$bc=get_block_categories(new WP_Block_Editor_Context());t('get_block_categories mit Filter',end($bc)['slug']==='extra');
add_filter('allowed_block_types_all',fn($a,$ctx)=>['core/paragraph'],10,2);
t('get_allowed_block_types',get_allowed_block_types(new WP_Block_Editor_Context())===['core/paragraph']);
$ds=get_default_block_editor_settings();
t('get_default_block_editor_settings',$ds['allowedBlockTypes']===true&&isset($ds['imageSizes'][0]['slug'])&&is_int($ds['maxUploadFileSize']));
$es=get_block_editor_settings(['x'=>1],new WP_Block_Editor_Context());
t('get_block_editor_settings',$es['x']===1&&is_array($es['styles'])&&isset($es['__experimentalFeatures'])&&$es['localAutosaveInterval']===15);
t('get_legacy_widget_block_editor_settings',in_array('search',get_legacy_widget_block_editor_settings()['widgetTypesToHideFromLegacyWidgetBlock'],true));
t('get_classic_theme_supports_block_editor_settings',(function(){ add_theme_support('custom-units');add_theme_support('align-wide');$s=get_classic_theme_supports_block_editor_settings();return !empty($s['enableCustomUnits'])&&!empty($s['alignWide'])&&!isset($s['disableCustomColors']); })());
t('get_block_editor_theme_styles ohne Editor-Stile leer',get_block_editor_theme_styles()===[]);
t('_wp_get_iframed_editor_assets',_wp_get_iframed_editor_assets()===['styles'=>'','scripts'=>'']);
block_editor_rest_api_preload(['wp/v2/posts?per_page=1'],new WP_Block_Editor_Context());
t('block_editor_rest_api_preload ohne Skript-Handle ohne Fehler',true);

/* ───── Block-Unterstützungen ───── */
$bt=new WP_Block_Type('t/sup',['supports'=>['color'=>['text'=>true,'background'=>true,'gradients'=>true],'typography'=>['fontSize'=>true,'lineHeight'=>true,'__experimentalFontFamily'=>true],'spacing'=>['padding'=>true,'margin'=>true],'__experimentalBorder'=>['radius'=>true,'color'=>true,'width'=>true,'style'=>true],'align'=>true,'shadow'=>true,'ariaLabel'=>true,'dimensions'=>['minHeight'=>true,'aspectRatio'=>true],'className'=>true,'layout'=>true]]);
wp_register_colors_support($bt);wp_register_typography_support($bt);wp_register_border_support($bt);wp_register_alignment_support($bt);wp_register_shadow_support($bt);wp_register_aria_label_support($bt);wp_register_custom_classname_support($bt);wp_register_layout_support($bt);wp_register_spacing_support($bt);wp_register_dimensions_support($bt);
t('Registrierung ergänzt Attribute',isset($bt->attributes['style'],$bt->attributes['textColor'],$bt->attributes['backgroundColor'],$bt->attributes['gradient'],$bt->attributes['fontSize'],$bt->attributes['fontFamily'],$bt->attributes['borderColor'],$bt->attributes['align'],$bt->attributes['shadow'],$bt->attributes['ariaLabel'],$bt->attributes['className'],$bt->attributes['layout'])&&in_array('wide',$bt->attributes['align']['enum'],true));
$c=wp_apply_colors_support($bt,['textColor'=>'primary','style'=>['color'=>['background'=>'#fff']]]);
t('wp_apply_colors_support',$c['class']==='has-text-color has-primary-color has-background'&&$c['style']==='color:var(--wp--preset--color--primary);background-color:#fff;');
t('wp_apply_colors_support: Verlauf, nichts gesetzt',str_contains(wp_apply_colors_support($bt,['gradient'=>'sunset'])['class'],'has-sunset-gradient-background')&&wp_apply_colors_support($bt,[])===[]);
t('wp_apply_colors_support: ohne Farbunterstützung',wp_apply_colors_support(new WP_Block_Type('t/n',[]),['textColor'=>'x'])===[]);
$ty=wp_apply_typography_support($bt,['fontSize'=>'large','fontFamily'=>'serif','style'=>['typography'=>['lineHeight'=>'1.6']]]);
t('wp_apply_typography_support',$ty['class']==='has-large-font-size has-serif-font-family'&&$ty['style']==='line-height:1.6;');
t('wp_typography_get_preset_inline_style_value',wp_typography_get_preset_inline_style_value('var:preset|font-size|x-large','font-size')==='var(--wp--preset--font-size--x-large);'&&wp_typography_get_preset_inline_style_value('20px','font-size')==='20px');
t('wp_get_typography_value_and_unit',wp_get_typography_value_and_unit('1.5rem')===['value'=>1.5,'unit'=>'rem']&&wp_get_typography_value_and_unit('24px',['coerce_to'=>'rem'])===['value'=>1.5,'unit'=>'rem']&&wp_get_typography_value_and_unit('2rem',['coerce_to'=>'px'])===['value'=>32.0,'unit'=>'px']&&wp_get_typography_value_and_unit('10%')===null&&wp_get_typography_value_and_unit('')===null);
t('wp_get_computed_fluid_typography_value',wp_get_computed_fluid_typography_value(['minimum_font_size'=>'1rem','maximum_font_size'=>'2rem'])==='clamp(1rem, 1rem + ((1vw - 0.2rem) * 0.769), 2rem)'||str_starts_with((string)wp_get_computed_fluid_typography_value(['minimum_font_size'=>'1rem','maximum_font_size'=>'2rem']),'clamp(1rem, 1rem + ((1vw - '));
t('wp_get_computed_fluid_typography_value: ungültig',wp_get_computed_fluid_typography_value(['minimum_font_size'=>'1%','maximum_font_size'=>'2rem'])===null);
t('wp_get_typography_font_size_value',wp_get_typography_font_size_value(['size'=>'28px'],false)==='28px'&&str_starts_with(wp_get_typography_font_size_value(['size'=>'28px'],true),'clamp(')&&wp_get_typography_font_size_value(['size'=>'12px'],true)==='12px'&&wp_get_typography_font_size_value(['size'=>'28px','fluid'=>false],true)==='28px'&&wp_get_typography_font_size_value([],true)==='');
t('wp_get_typography_font_size_value: min/max',str_starts_with(wp_get_typography_font_size_value(['size'=>'30px','fluid'=>['min'=>'20px','max'=>'40px']],true),'clamp(20px, '));
t('wp_render_typography_support (nur mit fluid)',wp_render_typography_support('<p style="font-size:30px">x</p>',['attrs'=>['style'=>['typography'=>['fontSize'=>'30px']]]])==='<p style="font-size:30px">x</p>'||str_contains(wp_render_typography_support('<p style="font-size:30px">x</p>',['attrs'=>['style'=>['typography'=>['fontSize'=>'30px']]]]),'clamp('));
$sp=wp_apply_spacing_support($bt,['style'=>['spacing'=>['padding'=>['top'=>'10px','left'=>'5px'],'margin'=>'2px']]]);
t('wp_apply_spacing_support',$sp['style']==='padding-top:10px;padding-left:5px;margin:2px;');
$bo=wp_apply_border_support($bt,['borderColor'=>'accent','style'=>['border'=>['radius'=>4,'width'=>2,'style'=>'solid']]]);
t('wp_apply_border_support',$bo['class']==='has-border-color has-accent-border-color'&&str_contains($bo['style'],'border-radius:4px;')&&str_contains($bo['style'],'border-width:2px;')&&str_contains($bo['style'],'border-color:var(--wp--preset--color--accent);'));
t('wp_has_border_feature_support',wp_has_border_feature_support($bt,'radius')&&!wp_has_border_feature_support(new WP_Block_Type('t/b',['supports'=>['__experimentalBorder'=>['radius'=>true]]]),'color')&&wp_has_border_feature_support(new WP_Block_Type('t/c',['supports'=>['__experimentalBorder'=>true]]),'color')&&wp_has_border_feature_support(new WP_Block_Type('t/d',['supports'=>['border'=>['width'=>true]]]),'width'));
t('wp_apply_alignment_support',wp_apply_alignment_support($bt,['align'=>'wide'])===['class'=>'alignwide']&&wp_apply_alignment_support($bt,[])===[]);
t('wp_apply_shadow_support',wp_apply_shadow_support($bt,['shadow'=>'natural'])['style']==='box-shadow:var(--wp--preset--shadow--natural);'&&wp_apply_shadow_support($bt,['style'=>['shadow'=>'1px 1px 2px #000']])['style']==='box-shadow:1px 1px 2px #000;'&&wp_apply_shadow_support(new WP_Block_Type('t/x',[]),['shadow'=>'a'])===[]);
t('wp_apply_aria_label_support',wp_apply_aria_label_support($bt,['ariaLabel'=>'Hallo'])===['aria-label'=>'Hallo']&&wp_apply_aria_label_support($bt,[])===[]);
t('wp_apply_custom_classname_support',wp_apply_custom_classname_support($bt,['className'=>'a b'])===['class'=>'a b']&&wp_apply_custom_classname_support($bt,[])===[]);
t('wp_get_block_default_classname',wp_get_block_default_classname('core/paragraph')==='wp-block-paragraph'&&wp_get_block_default_classname('core-embed/youtube')==='wp-block-embed-youtube'&&wp_get_block_default_classname('my/block')==='wp-block-my-block');
$tg=new WP_Block_Type('core/group',[]);t('wp_apply_generated_classname_support',wp_apply_generated_classname_support($tg)===['class'=>'wp-block-group']&&wp_apply_generated_classname_support(new WP_Block_Type('a/b',['supports'=>['className'=>false]]))===[]);
$dm=wp_apply_dimensions_support($bt,['style'=>['dimensions'=>['minHeight'=>'50px','aspectRatio'=>'16/9']]]);
t('wp_apply_dimensions_support: Seitenverhältnis hebt Mindesthöhe auf',str_contains($dm['style'],'aspect-ratio:16/9;')&&str_contains($dm['style'],'min-height:unset;'));
t('wp_should_skip_block_supports_serialization',(function(){ $b=new WP_Block_Type('t/k',['supports'=>['color'=>['__experimentalSkipSerialization'=>true],'spacing'=>['__experimentalSkipSerialization'=>['padding']]]]);return wp_should_skip_block_supports_serialization($b,'color')&&wp_should_skip_block_supports_serialization($b,'spacing','padding')&&!wp_should_skip_block_supports_serialization($b,'spacing','margin')&&!wp_should_skip_block_supports_serialization($b,'typography'); })());
t('Überspringen: kein Stil bei Skip',wp_apply_colors_support(new WP_Block_Type('t/k',['supports'=>['color'=>['text'=>true,'__experimentalSkipSerialization'=>true]]]),['textColor'=>'a'])===[]);
WP_Block_Type_Registry::get_instance()->register($bt);
$bg=wp_render_background_support('<div class="a">x</div>',['blockName'=>'t/sup','attrs'=>['style'=>['background'=>['backgroundImage'=>['url'=>'https://example.test/a.jpg']]]]]);
t('wp_render_background_support: Block ohne Unterstützung unverändert',$bg==='<div class="a">x</div>');
$bt2=new WP_Block_Type('t/bg',['supports'=>['background'=>['backgroundImage'=>true]]]);WP_Block_Type_Registry::get_instance()->register($bt2);wp_register_background_support($bt2);
$bg=wp_render_background_support('<div class="a">x</div>',['blockName'=>'t/bg','attrs'=>['style'=>['background'=>['backgroundImage'=>['url'=>'https://example.test/a.jpg']]]]]);
t('wp_render_background_support',str_contains($bg,'background-image:url(')&&str_contains($bg,'background-size:cover;')&&str_contains($bg,'has-background')&&isset($bt2->attributes['style']));
$bt3=new WP_Block_Type('t/pos',['supports'=>['position'=>['sticky'=>true]]]);WP_Block_Type_Registry::get_instance()->register($bt3);
t('wp_render_position_support: Theme ohne sticky: unverändert',wp_render_position_support('<div>x</div>',['blockName'=>'t/pos','attrs'=>['style'=>['position'=>['type'=>'sticky','top'=>'0px']]]])==='<div>x</div>');
update_option('rrw_wp_global_styles',['settings'=>['position'=>['sticky'=>true]]]);rrw_wp_theme_json(true);
$po2=wp_render_position_support('<div>x</div>',['blockName'=>'t/pos','attrs'=>['style'=>['position'=>['type'=>'sticky','top'=>'0px']]]]);
t('wp_render_position_support: sticky',str_contains($po2,'is-position-sticky')&&preg_match('/wp-container-\d+/',$po2)===1&&str_contains(implode('',$GLOBALS['rrw_wp_block_support_css']),'position:sticky;'));
delete_option('rrw_wp_global_styles');rrw_wp_theme_json(true);
t('wp_register_position_support / dimensions',(function() use($bt3){ wp_register_position_support($bt3);return isset($bt3->attributes['style']); })());

/* ───── Layout ───── */
t('wp_get_layout_definitions',array_keys(wp_get_layout_definitions())===['default','constrained','flex','grid']&&wp_get_layout_definitions()['flex']['className']==='is-layout-flex');
$css=wp_get_layout_style('.c',['type'=>'flex','justifyContent'=>'space-between','flexWrap'=>'nowrap'],true,'1rem');
t('wp_get_layout_style: flex',str_contains($css,'.c{flex-wrap:nowrap;gap:1rem;justify-content:space-between;}'),$css);
t('wp_get_layout_style: vertikal',str_contains(wp_get_layout_style('.v',['type'=>'flex','orientation'=>'vertical']),'flex-direction:column;'));
t('wp_get_layout_style: constrained',str_contains(wp_get_layout_style('.k',['type'=>'constrained','contentSize'=>'600px','wideSize'=>'900px']),'.k > .alignwide{max-width:900px;}'));
t('wp_get_layout_style: flow mit Abstand',str_contains(wp_get_layout_style('.f',['type'=>'default'],true,'var:preset|spacing|50'),'.f > * + *{margin-block-start:var(--wp--preset--spacing--50);margin-block-end:0;}'));
t('wp_get_layout_style: grid',str_contains(wp_get_layout_style('.g',['type'=>'grid','columnCount'=>3]),'grid-template-columns:repeat(3, minmax(0, 1fr));')&&wp_get_layout_style('.n',['type'=>'default'])==='');
t('wp_get_layout_style: unsicherer Wert wird verworfen',strpos(wp_get_layout_style('.u',['type'=>'constrained','contentSize'=>'600px;x{}']),'600px;x')===false);
$lb=new WP_Block_Type('core/group',['supports'=>['layout'=>true]]);WP_Block_Type_Registry::get_instance()->register($lb);
$rl=wp_render_layout_support_flag('<div class="wp-block-group">x</div>',['blockName'=>'core/group','attrs'=>['layout'=>['type'=>'flex','justifyContent'=>'center']]]);
t('wp_render_layout_support_flag',str_contains($rl,'is-layout-flex')&&str_contains($rl,'wp-block-group-is-layout-flex')&&str_contains($rl,'wp-block-group wp-container-core-group-is-layout-')===false||str_contains($rl,'wp-container-core-group-is-layout-'),$rl);
t('wp_render_layout_support_flag: Block ohne layout unverändert',wp_render_layout_support_flag('<p>x</p>',['blockName'=>'core/gibtsnicht','attrs'=>[]])==='<p>x</p>');
$pl=wp_add_parent_layout_to_parsed_block(['blockName'=>'a'],[],(object)['attributes'=>['layout'=>['type'=>'flex']]]);
t('wp_add_parent_layout_to_parsed_block',$pl['parentLayout']==['type'=>'flex']&&wp_add_parent_layout_to_parsed_block(['blockName'=>'a'],[],null)===['blockName'=>'a']);
$oldSheet=get_option('stylesheet');update_option('stylesheet','gibtsnicht');update_option('template','gibtsnicht');
t('wp_restore_group_inner_container',str_contains(wp_restore_group_inner_container('<div class="wp-block-group">x</div>',['blockName'=>'core/group','attrs'=>[]]),'wp-block-group__inner-container')&&wp_restore_group_inner_container('<div class="wp-block-group">x</div>',['blockName'=>'core/paragraph','attrs'=>[]])==='<div class="wp-block-group">x</div>');
$im=wp_restore_image_outer_container('<figure class="wp-block-image alignleft size-full"><img src="a.jpg"/></figure>',['blockName'=>'core/image','attrs'=>[]]);
t('wp_restore_image_outer_container',str_starts_with($im,'<div class="wp-block-image"><figure class="alignleft size-full">')&&str_ends_with($im,'</figure></div>'),$im);

update_option('stylesheet',$oldSheet);update_option('template',$oldSheet);
/* ───── Style-Engine ───── */
$ss=wp_style_engine_get_stylesheet_from_css_rules([['selector'=>'.a','declarations'=>['color'=>'red','margin'=>'0']],['selector'=>'.a','declarations'=>['padding'=>'1px']],['selector'=>'','declarations'=>['x'=>'y']],['selector'=>'.b','declarations'=>['color'=>'red;evil:1']]],['prettify'=>false]);
t('wp_style_engine_get_stylesheet_from_css_rules',$ss==='.a{color:red;margin:0;padding:1px;}',$ss);
t('Style-Engine: schön formatiert',str_contains(wp_style_engine_get_stylesheet_from_css_rules([['selector'=>'.p','declarations'=>['color'=>'red']]],['prettify'=>true]),".p {\n\tcolor: red;\n}"));
wp_style_engine_get_stylesheet_from_css_rules([['selector'=>'.ctx','declarations'=>['top'=>'0']]],['context'=>'t-ctx']);wp_style_engine_get_stylesheet_from_css_rules([['selector'=>'.ctx','declarations'=>['left'=>'1px']]],['context'=>'t-ctx']);
t('wp_style_engine_get_stylesheet_from_context',wp_style_engine_get_stylesheet_from_context('t-ctx',['prettify'=>false])==='.ctx{top:0;left:1px;}'&&wp_style_engine_get_stylesheet_from_context('nix')==='');
t('Kontext block-supports erscheint im Footer',(function(){ wp_style_engine_get_stylesheet_from_css_rules([['selector'=>'.fz','declarations'=>['gap'=>'2px']]],['context'=>'block-supports']);ob_start();rrw_wp_fse_footer();return str_contains(ob_get_clean(),'.fz{gap:2px;}'); })());

/* ───── Global Styles, Selektoren ───── */
t('wp_get_block_name_from_theme_json_path',wp_get_block_name_from_theme_json_path(['styles','blocks','core/button','color'])==='core/button'&&wp_get_block_name_from_theme_json_path(['styles','color'])==='');
$b1=new WP_Block_Type('core/button',[]);
t('wp_get_block_css_selector: Standard',wp_get_block_css_selector($b1)==='.wp-block-button'&&wp_get_block_css_selector(new WP_Block_Type('a/b',[]))==='.wp-block-a-b');
$b2=new WP_Block_Type('t/sel',['supports'=>['__experimentalSelector'=>'.root','color'=>['__experimentalSelector'=>'.farbe']]]);
t('wp_get_block_css_selector: experimentell',wp_get_block_css_selector($b2)==='.root'&&wp_get_block_css_selector($b2,'color')==='.root .farbe'&&wp_get_block_css_selector($b2,'typography')===null&&wp_get_block_css_selector($b2,'typography',true)==='.root'&&wp_get_block_css_selector($b2,'')===null);
$b3=new class('t/sel2',[]) extends WP_Block_Type { public $selectors=['root'=>'.r2','color'=>['root'=>'.c2','text'=>'.t2']]; };
t('wp_get_block_css_selector: selectors-API',wp_get_block_css_selector($b3)==='.r2'&&wp_get_block_css_selector($b3,'color')==='.c2'&&wp_get_block_css_selector($b3,'color.text')==='.t2'&&wp_get_block_css_selector($b3,'color.background',true)==='.c2');
t('wp_clean_theme_json_cache läuft',(function(){ wp_clean_theme_json_cache();return true; })());
wp_add_global_styles_for_blocks();t('wp_add_global_styles_for_blocks ohne Fehler',true);

/* ───── Muster ───── */
_register_core_block_patterns_and_categories();
t('_register_core_block_patterns_and_categories',isset($GLOBALS['rrw_wp_pattern_categories']['featured'])&&isset($GLOBALS['rrw_wp_pattern_categories']['footer']));
$norm=wp_normalize_remote_block_pattern(['title'=>['rendered'=>'Titel'],'pattern_content'=>'<!-- wp:paragraph /-->','category_slugs'=>['text'],'meta'=>['wpop_viewport_width'=>'800','wpop_description'=>'Beschr','wpop_keywords'=>'a, b']]);
t('wp_normalize_remote_block_pattern',$norm['title']==='Titel'&&$norm['categories']===['text']&&$norm['viewportWidth']===800&&$norm['keywords']===['a','b']&&$norm['description']==='Beschr');
_load_remote_block_patterns([['title'=>['rendered'=>'Fern Muster'],'pattern_content'=>'<!-- wp:paragraph /-->','category_slugs'=>['text']]]);
t('_load_remote_block_patterns registriert übergebene',WP_Block_Patterns_Registry::get_instance()->is_registered('core/fern-muster'));
_load_remote_featured_patterns();_register_remote_theme_patterns();
mkdir($td.'/patterns');file_put_contents($td.'/patterns/hero.php',"<?php\n/**\n * Title: Hero\n * Slug: bt/hero\n * Categories: banner, featured\n * Keywords: gross, kopf\n * Viewport Width: 1200\n */\n?>\n<!-- wp:paragraph --><p>Hero</p><!-- /wp:paragraph -->");
_register_theme_block_patterns();
$pr=WP_Block_Patterns_Registry::get_instance()->get_registered('bt/hero');
t('_register_theme_block_patterns',$pr&&str_contains($pr['content'],'Hero')&&$pr['categories']===['banner','featured']&&$pr['viewportWidth']===1200&&$pr['keywords']===['gross','kopf']);

/* ───── Stil-Variationen ───── */
t('wp_get_block_style_variation_name_from_class',wp_get_block_style_variation_name_from_class('a is-style-fancy b')==='fancy'&&wp_get_block_style_variation_name_from_class('is-style-default')===null&&wp_get_block_style_variation_name_from_class(null)===null);
$rv=wp_resolve_block_style_variation_ref_values(['color'=>['text'=>['ref'=>'styles.color.text'],'background'=>['ref'=>'styles.fehlt']],'x'=>1],['styles'=>['color'=>['text'=>'#111']]]);
t('wp_resolve_block_style_variation_ref_values',$rv===['color'=>['text'=>'#111'],'x'=>1]);
update_option('rrw_wp_global_styles',['styles'=>['blocks'=>['core/button'=>['variations'=>['fancy'=>['color'=>['text'=>'#ff0000']]]]]]]);rrw_wp_theme_json(true);
$pb=wp_render_block_style_variation_support_styles(['blockName'=>'core/button','attrs'=>['className'=>'is-style-fancy']]);
t('wp_render_block_style_variation_support_styles: Klasse mit Instanz',preg_match('/^is-style-fancy--\d+$/',$pb['attrs']['className'])===1);
$stored=implode('',$GLOBALS['rrw_wp_style_engine_raw']['block-style-variation-styles']??[]);
t('Variations-CSS gespeichert',str_contains($stored,'.wp-block-button.is-style-fancy--')&&str_contains($stored,'color:#ff0000'),$stored);
t('wp_render_block_style_variation_support_styles: ohne Variation unverändert',wp_render_block_style_variation_support_styles(['blockName'=>'core/button','attrs'=>['className'=>'x']])===['blockName'=>'core/button','attrs'=>['className'=>'x']]);
$vc=wp_render_block_style_variation_class_name('<div class="wp-block-button">x</div>',['attrs'=>$pb['attrs']]);
t('wp_render_block_style_variation_class_name',str_contains($vc,'is-style-fancy--'));
wp_enqueue_block_style_variation_styles();t('wp_enqueue_block_style_variation_styles',wp_style_is('block-style-variation-styles','enqueued'));
delete_option('rrw_wp_global_styles');rrw_wp_theme_json(true);
wp_register_block_style_variations_from_theme_json_partials([['title'=>'Dunkel Variante','slug'=>'dunkel','blockTypes'=>['core/group','core/button']]]);
wp_register_block_style_variations_from_theme_json_partials(['hell'=>['title'=>'Hell','blockTypes'=>['core/group']]]);
$reg=WP_Block_Styles_Registry::get_instance();
t('wp_register_block_style_variations_from_theme_json_partials',$reg->is_registered('core/group','dunkel')&&$reg->is_registered('core/button','dunkel')&&$reg->is_registered('core/group','hell'));

/* ───── Elemente, Voreinstellungen ───── */
$eb=['blockName'=>'t/sup','attrs'=>['style'=>['elements'=>['link'=>['color'=>['text'=>'#00f'],':hover'=>['color'=>['text'=>'#0f0']]]]]]];
t('wp_get_elements_class_name',preg_match('/^wp-elements-[0-9a-f]{32}$/',wp_get_elements_class_name($eb))===1&&wp_get_elements_class_name($eb)===wp_get_elements_class_name($eb));
t('wp_should_add_elements_class_name',wp_should_add_elements_class_name($eb,[])===true&&wp_should_add_elements_class_name(['blockName'=>'t/sup','attrs'=>[]],[])===false&&wp_should_add_elements_class_name(['blockName'=>'t/gibtsnicht','attrs'=>$eb['attrs']],[])===false&&wp_should_add_elements_class_name($eb,['skip'=>['link']])===false);
$er=wp_render_elements_support_styles($eb);
t('wp_render_elements_support_styles: Klasse und CSS',preg_match('/wp-elements-[0-9a-f]{32}/',$er['attrs']['className'],$mm)===1&&str_contains(implode('',$GLOBALS['rrw_wp_block_support_css']),'a:where(:not(.wp-element-button)){color:#00f;}')&&str_contains(implode('',$GLOBALS['rrw_wp_block_support_css']),':hover{color:#0f0;}'));
t('wp_render_elements_class_name',str_contains(wp_render_elements_class_name('<p>x</p>',$er),$mm[0])&&wp_render_elements_class_name('<p>x</p>',['attrs'=>[]])==='<p>x</p>');
$sb=['blockName'=>'core/group','attrs'=>['settings'=>['color'=>['palette'=>[['slug'=>'rot','color'=>'#f00','name'=>'Rot']]]]],'innerBlocks'=>[['blockName'=>'core/paragraph']]];
t('_wp_get_presets_class_name',str_starts_with(_wp_get_presets_class_name($sb),'wp-settings-'));
t('_wp_add_block_level_presets_class',str_contains(_wp_add_block_level_presets_class('<div>x</div>',$sb),_wp_get_presets_class_name($sb))&&_wp_add_block_level_presets_class('<div>x</div>',['innerBlocks'=>[],'attrs'=>[]])==='<div>x</div>'&&_wp_add_block_level_presets_class('<div>x</div>',['innerBlocks'=>[1],'attrs'=>[]])==='<div>x</div>');
t('_wp_add_block_level_preset_styles',_wp_add_block_level_preset_styles(null,$sb)===null&&str_contains(implode('',$GLOBALS['rrw_wp_block_support_css']),'--wp--preset--color--rot:#f00;'));
t('_wp_add_block_level_preset_styles: Vorergebnis durchreichen',_wp_add_block_level_preset_styles('fertig',$sb)===null);

/* ───── Bindungen ───── */
_register_block_bindings_post_meta_source();_register_block_bindings_pattern_overrides_source();
t('Bindungsquellen registriert',get_block_bindings_source('core/post-meta')['get_value_callback']==='_block_bindings_post_meta_get_value'&&in_array('pattern/overrides',get_block_bindings_source('core/pattern-overrides')['uses_context'],true));
update_post_meta($p,'sichtbar','Wert A');update_post_meta($p,'_versteckt','Wert B');
$bi=new class($p){ public $context;public $attributes=['metadata'=>['name'=>'feld']];public function __construct($p){ $this->context=['postId'=>$p,'postType'=>'post','pattern/overrides'=>['feld'=>['content'=>'Überschrieben']]]; } };
t('post-meta-Bindung',_block_bindings_post_meta_get_value(['key'=>'sichtbar'],$bi,'content')==='Wert A'&&_block_bindings_post_meta_get_value(['key'=>'_versteckt'],$bi,'content')===null&&_block_bindings_post_meta_get_value([],$bi,'content')===null&&_block_bindings_post_meta_get_value(['key'=>'sichtbar'],(object)['context'=>[]],'content')===null);
t('pattern-overrides-Bindung',_block_bindings_pattern_overrides_get_value([],$bi,'content')==='Überschrieben'&&_block_bindings_pattern_overrides_get_value([],$bi,'url')===null&&_block_bindings_pattern_overrides_get_value([],(object)['attributes'=>[],'context'=>[]],'content')===null);

/* ───── Rest: Standardwerte / No-ops laufen ohne Fehler ───── */
create_initial_rest_routes();rest_api_default_filters();
t('rest_api_default_filters hängt Filter an',has_filter('rest_post_dispatch','rest_send_allow_header')!==false&&has_filter('rest_authentication_errors','rest_cookie_check_errors')===100);
wp_initialize_site_preview_hooks();_add_template_loader_filters();
t('No-ops liefern keine Ausgabe',true);

system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

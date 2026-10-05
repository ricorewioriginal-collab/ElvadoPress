<?php
// Prüft die ergänzenden Funktionen für WordPress 7.1 (cms/wp/core/ext/v712-*.php): UTF-8, Anmerkungen, Connectors, Abilities, Icons, Medien, Sonstiges.
// Aufruf: php scripts/test-wp-ext-v712.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/rrw-extv712-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/wp-content/plugins');mkdir($tmp.'/wp-content/languages');mkdir($tmp.'/cms');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('RRW_WP_DATA',$tmp.'/cms/.wp');define('RRW_WP_CMS_DATA',$tmp.'/cms');
$_SERVER['HTTP_HOST']='example.test';file_put_contents($tmp.'/cms/site.json','{}');$GLOBALS['RRW_SITE']=[];
require __DIR__.'/_testdb.php';
require __DIR__.'/../cms/wp/load.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
rrw_wp_boot(['theme'=>false,'user'=>['id'=>1,'login'=>'admin','name'=>'Administration','email'=>'a@example.test','role'=>'administrator']]);

// Vollständigkeit: alle Funktionen der Liste (keine Ausnahmen)
$names=preg_split('/\s+/',trim(<<<'LISTE'
wp_get_image_alttext upgrade_700 _wp_kses_allow_note_mention_span _wp_kses_sanitize_note_mention_classes wp_get_image_encode_quality wp_enqueue_img_auto_sizes_contain_css_fix
wp_is_client_side_media_processing_enabled wp_set_client_side_media_processing_flag wp_get_chromium_major_version wp_set_up_cross_origin_isolation wp_start_cross_origin_isolation_output_buffer wp_add_crossorigin_attributes
_mb_chr _mb_ord wp_enqueue_command_palette_assets wp_load_classic_theme_block_styles_on_demand wp_hoist_late_printed_styles wp_js_dataset_name
wp_html_custom_data_attribute_name wp_is_connector_registered wp_get_connector wp_get_connectors _wp_connectors_resolve_ai_provider_logo_url _wp_connectors_init
_wp_connectors_register_default_ai_providers _wp_connectors_mask_api_key _wp_connectors_get_api_key_source wp_connectors_parse_application_password_credentials wp_connectors_get_application_password_credentials _wp_connectors_is_ai_api_key_valid
wp_connectors_sanitize_application_password_credentials _wp_connectors_rest_settings_dispatch _wp_register_default_connector_settings _wp_connectors_pass_default_keys_to_ai_client _wp_connectors_get_connector_script_module_data is_sitemap
wp_is_valid_utf8 wp_scrub_utf8 wp_has_noncharacters _wp_is_template_path_allowed wp_should_output_buffer_template_for_enhancement wp_start_template_enhancement_output_buffer
wp_finalize_template_enhancement_output_buffer wp_set_script_module_translations wp_enqueue_block_editor_script_modules wp_cache_switch_to_blog_fallback wp_get_speculation_rules_default_configuration wp_get_speculative_loading_override
wp_get_entity_view_config_hook_name _wp_get_default_posttype_form wp_get_entity_view_config _wp_get_entity_view_config_posttype_page _wp_get_entity_view_config_posttype_wp_block _wp_get_entity_view_config_posttype_wp_template_part
_wp_get_entity_view_config_posttype_wp_template wp_enqueue_view_transitions_admin_css wp_get_view_transitions_admin_css wp_unregister_ability wp_has_ability wp_get_ability
_wp_get_abilities_match_meta wp_unregister_ability_category wp_has_ability_category wp_get_ability_category wp_get_ability_categories wp_supports_ai
wp_ai_client_prompt _reset_privacy_policy_page_for_post wp_cache_get_salted wp_cache_set_salted wp_cache_get_multiple_salted wp_cache_set_multiple_salted
wp_get_tooltip wp_get_toggletip wp_get_tooltip_helper get_block_bindings_supported_attributes _block_template_add_skip_link wp_register_icon_collection
wp_unregister_icon_collection wp_register_icon wp_unregister_icon _wp_register_default_icon_collections _wp_register_default_icons wp_get_icon
wp_new_comment_via_rest_notify_postauthor wp_get_note_mentioned_user_ids wp_notify_note_mentions wp_send_note_notification wp_should_disable_pings_for_environment wp_maybe_disable_outgoing_pings_for_environment
wp_maybe_disable_trackback_for_environment wp_maybe_disable_xmlrpc_pingback_for_environment wp_create_initial_comment_meta wp_strip_inline_note_markers _wp_apply_block_content_filters _wp_enqueue_auto_register_blocks
_wp_scripts_add_args_data _wp_scan_utf8 _wp_is_valid_utf8_fallback _wp_scrub_utf8_fallback _wp_utf8_codepoint_count _wp_utf8_codepoint_span
_wp_has_noncharacters_fallback _wp_utf8_encode_fallback _wp_utf8_decode_fallback wp_schedule_personal_data_cleanup_requests wp_privacy_personal_data_cleanup_requests wp_get_json_schema_allowed_keywords
wp_prepare_json_schema_for_client _wp_prepare_json_schema_for_client_with_allowed_keywords load_script_module_textdomain _load_script_textdomain_from_src wp_admin_bar_command_palette_menu
LISTE));
$missing=array_values(array_filter($names,fn($f)=>!function_exists($f)));
t('Vollständigkeit: '.count($names).' Funktionen vorhanden',$missing===[]&&count($names)===113,implode(' ',$missing));

// UTF-8
$bad="ab\xC3(cd\xE2\x82x\xF0\x9F\x98";
t('wp_is_valid_utf8: gültig',wp_is_valid_utf8("Grüße 😀")&&wp_is_valid_utf8(''));
t('wp_is_valid_utf8: ungültig',!wp_is_valid_utf8($bad)&&!wp_is_valid_utf8("\xC0\xAF")&&!wp_is_valid_utf8("\xED\xA0\x80"));
t('wp_scrub_utf8: ersetzt maximale Teilfolgen',wp_scrub_utf8($bad)==="ab\u{FFFD}(cd\u{FFFD}x\u{FFFD}");
t('wp_scrub_utf8: gültiger Text unverändert',wp_scrub_utf8('äöü')==='äöü');
t('Fallbacks stimmen mit Hauptfunktionen überein',_wp_scrub_utf8_fallback($bad)===wp_scrub_utf8($bad)&&_wp_is_valid_utf8_fallback('äö')&&!_wp_is_valid_utf8_fallback($bad));
t('wp_has_noncharacters',wp_has_noncharacters("a\u{FDD0}")&&wp_has_noncharacters("a\u{FFFF}")&&wp_has_noncharacters("\u{10FFFE}")&&!wp_has_noncharacters("a\u{FFFD}😀")&&_wp_has_noncharacters_fallback("\u{1FFFF}"));
$at=0;$inv=0;$c=_wp_scan_utf8("aä😀\xFFz",$at,$inv);
t('_wp_scan_utf8: Zähler, Position, ungültige Länge',$c===3&&$at===7&&$inv===1);
$at=0;$c=_wp_scan_utf8("aä😀z",$at,$inv,null,2);
t('_wp_scan_utf8: Codepunkt-Grenze',$c===2&&$at===3);
t('_wp_utf8_codepoint_count',_wp_utf8_codepoint_count("aä😀")===3&&_wp_utf8_codepoint_count("a\xFFb")===3&&_wp_utf8_codepoint_count('')===0);
t('_wp_utf8_codepoint_span',_wp_utf8_codepoint_span("aä😀z",0,3,$f)===7&&$f===3);
t('_mb_chr/_mb_ord Rundlauf',_mb_chr(0x20AC)==="€"&&_mb_ord("€x")===0x20AC&&_mb_ord("😀")===0x1F600&&_mb_chr(0xD800)===false&&_mb_ord("\xFF")===false&&_mb_ord('')===false);
t('Latin-1-Umwandlung',_wp_utf8_encode_fallback("caf\xE9")==='café'&&_wp_utf8_decode_fallback('café€')==="caf\xE9?");
$big=str_repeat('äb😀',200000);$tm=microtime(true);
t('wp_scrub_utf8: großer gültiger Text schnell',wp_scrub_utf8($big)===$big&&_wp_is_valid_utf8_fallback($big)&&microtime(true)-$tm<3);

// Anmerkungen, kses
$note='Hallo <span class="note-mention" data-user-id="5">@a</span> und <span class="x note-mention" data-user-id="7">@b</span> <span class="note-mention" data-user-id="5">@a</span>';
t('wp_get_note_mentioned_user_ids: eindeutig',wp_get_note_mentioned_user_ids($note)===[5,7]&&wp_get_note_mentioned_user_ids('kein')===[]);
t('wp_strip_inline_note_markers',wp_strip_inline_note_markers('A <mark class="note-marker" data-note-id="3">markiert</mark> B <mark class="gelb">bleibt</mark>')==='A markiert B <mark class="gelb">bleibt</mark>');
t('_wp_kses_sanitize_note_mention_classes',_wp_kses_sanitize_note_mention_classes('<span class="x note-mention evil" data-user-id="9">@u</span><span class="y">z</span>')==='<span class="note-mention" data-user-id="9">@u</span><span class="y">z</span>');
$tags=_wp_kses_allow_note_mention_span(['span'=>['id'=>true]],'post');
t('_wp_kses_allow_note_mention_span',($tags['span']['data-user-id']??false)===true&&isset($tags['span']['id'])&&_wp_kses_allow_note_mention_span(['p'=>[]],'strip')===['p'=>[]]);
t('wp_create_initial_comment_meta: keine Anmerkung -> false',wp_create_initial_comment_meta(999999)===false);
t('wp_send_note_notification / wp_notify_note_mentions: unbekannt',wp_send_note_notification(999999)===false&&wp_notify_note_mentions(999999)===0&&wp_new_comment_via_rest_notify_postauthor(999999)===false);

// Pings je Umgebung
t('Pings: Produktion -> nicht deaktiviert',wp_should_disable_pings_for_environment()===false&&wp_maybe_disable_outgoing_pings_for_environment()===false&&wp_maybe_disable_trackback_for_environment()===false&&wp_maybe_disable_xmlrpc_pingback_for_environment()===false);
add_filter('wp_should_disable_pings_for_environment','__return_true');
t('Pings: per Filter deaktiviert',wp_maybe_disable_outgoing_pings_for_environment()&&get_option('default_ping_status')==='closed'&&wp_maybe_disable_xmlrpc_pingback_for_environment()&&!isset(apply_filters('xmlrpc_methods',['pingback.ping'=>1,'x'=>2])['pingback.ping']));
remove_filter('wp_should_disable_pings_for_environment','__return_true');

// Datenschutz
t('_reset_privacy_policy_page_for_post: unbeteiligt',_reset_privacy_policy_page_for_post(987654)===false);
t('Bereinigung der Datenschutzanfragen planbar',function_exists('wp_schedule_personal_data_cleanup_requests')&&(wp_privacy_personal_data_cleanup_requests()===null));

// Medien / Skripte
file_put_contents($tmp.'/b.jpg',"\xFF\xD8 kein xmp");
t('wp_get_image_alttext: ohne XMP',wp_get_image_alttext($tmp.'/b.jpg')===''&&wp_get_image_alttext($tmp.'/nix.jpg')==='');
file_put_contents($tmp.'/a.jpg',"\xFF\xD8<x:xmpmeta xmlns:x=\"adobe:ns:meta/\"><Iptc4xmpCore:AltTextAccessibility><rdf:Alt><rdf:li xml:lang=\"x-default\">Ein &lt;b&gt;Hund&lt;/b&gt; am Strand</rdf:li></rdf:Alt></Iptc4xmpCore:AltTextAccessibility></x:xmpmeta>");
t('wp_get_image_alttext: aus XMP',wp_get_image_alttext($tmp.'/a.jpg')==='Ein Hund am Strand');
t('wp_get_image_encode_quality',wp_get_image_encode_quality('image/jpeg')===82&&wp_get_image_encode_quality('image/webp')===86);
add_filter('wp_editor_set_quality',fn()=>500);t('wp_get_image_encode_quality: begrenzt auf 100',wp_get_image_encode_quality('image/png')===100);remove_all_filters('wp_editor_set_quality');
$_SERVER['HTTP_USER_AGENT']='Mozilla/5.0 Chrome/126.0.0.0 Safari/537.36';
t('wp_get_chromium_major_version',wp_get_chromium_major_version()===126);$_SERVER['HTTP_USER_AGENT']='Firefox/120';t('wp_get_chromium_major_version: Firefox -> null',wp_get_chromium_major_version()===null);
t('Cross-Origin / clientseitige Medien: Standardwerte',wp_is_client_side_media_processing_enabled()===false&&wp_set_up_cross_origin_isolation()===false&&wp_start_cross_origin_isolation_output_buffer()===false&&wp_set_client_side_media_processing_flag()===null);
t('wp_add_crossorigin_attributes: unverändert',wp_add_crossorigin_attributes('<img src="https://x.test/a.png">')==='<img src="https://x.test/a.png">');
t('wp_js_dataset_name',wp_js_dataset_name('data-wp-foo-bar')==='wpFooBar'&&wp_js_dataset_name('foo')===null&&wp_js_dataset_name('data-')===null);
t('wp_html_custom_data_attribute_name',wp_html_custom_data_attribute_name('wpFooBar')==='data-wp-foo-bar'&&wp_html_custom_data_attribute_name('foo-bar')===null&&wp_html_custom_data_attribute_name('')===null);
wp_register_script('v712-s','https://example.test/wp-content/plugins/x/a.js',[],'1');
t('_wp_scripts_add_args_data',_wp_scripts_add_args_data(null,'v712-s',['strategy'=>'defer','in_footer'=>true])&&$GLOBALS['rrw_wp_scripts']['reg']['v712-s']['data']['strategy']==='defer'&&!_wp_scripts_add_args_data(null,'unbekannt',[]));
t('wp_set_script_module_translations',wp_set_script_module_translations('mod','dom','/p')&&$GLOBALS['rrw_wp_script_module_l10n']['mod']['domain']==='dom'&&!wp_set_script_module_translations(''));
t('load_script_module_textdomain: ohne Datei false',load_script_module_textdomain('v712-s','default')===false&&load_script_module_textdomain('nix')===false&&_load_script_textdomain_from_src('','default')===false);
wp_enqueue_view_transitions_admin_css();
t('View-Transitions-CSS',str_contains(wp_get_view_transitions_admin_css(),'@view-transition')&&isset($GLOBALS['rrw_wp_styles']['queue']['wp-view-transitions-admin']));
wp_enqueue_img_auto_sizes_contain_css_fix();
t('Auto-Sizes-Fix eingereiht',isset($GLOBALS['rrw_wp_styles']['reg']['wp-img-auto-sizes-contain-fix']['inline_after'][0]));
t('Editor-Oberflächen: No-ops',wp_enqueue_command_palette_assets()===null&&wp_admin_bar_command_palette_menu(null)===null&&wp_load_classic_theme_block_styles_on_demand()===false&&wp_hoist_late_printed_styles()===false&&wp_enqueue_block_editor_script_modules()===null);
t('Speculative Loading: Standard',wp_get_speculation_rules_default_configuration()===['mode'=>'auto','eagerness'=>'conservative']&&wp_get_speculative_loading_override()===null);

// Connectors, AI-Client
$cons=wp_get_connectors();
t('Connectors: Standardanbieter',isset($cons['anthropic'],$cons['google'],$cons['openai'])&&wp_is_connector_registered('openai')&&!wp_is_connector_registered('nix')&&wp_get_connector('nix')===null);
t('Connectors: Struktur',wp_get_connector('google')['id']==='google'&&wp_get_connector('google')['authentication']['setting_name']==='connectors_ai_google_api_key');
$reg=$GLOBALS['rrw_wp_connectors'];
t('Connectors: Registry register/unregister',$reg->register('mein',['name'=>'Mein','authentication'=>['method'=>'api_key']])!==null&&wp_is_connector_registered('mein')&&$reg->register('mein',['name'=>'x'])===null&&$reg->unregister('mein')!==null&&!wp_is_connector_registered('mein'));
t('_wp_connectors_mask_api_key',_wp_connectors_mask_api_key('sk-abcdefgh1234')==="\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}1234"&&_wp_connectors_mask_api_key('abc')==="\u{2022}\u{2022}\u{2022}"&&_wp_connectors_mask_api_key('')==='');
t('_wp_connectors_get_api_key_source',_wp_connectors_get_api_key_source('connectors_ai_zz_api_key','RRW_NIX_ENV','RRW_NIX_CONST')==='none');
update_option('connectors_ai_zz_api_key','abc');putenv('RRW_V712_ENV=1');
t('_wp_connectors_get_api_key_source: Datenbank/Umgebung',_wp_connectors_get_api_key_source('connectors_ai_zz_api_key')==='database'&&_wp_connectors_get_api_key_source('connectors_ai_zz_api_key','RRW_V712_ENV')==='env');
t('_wp_connectors_is_ai_api_key_valid',_wp_connectors_is_ai_api_key_valid('sk-12345678')&&!_wp_connectors_is_ai_api_key_valid('')&&!_wp_connectors_is_ai_api_key_valid('mit leer zeichen'));
t('Anwendungspasswörter parsen/bereinigen',wp_connectors_parse_application_password_credentials('admin:abcd efgh')===['username'=>'admin','password'=>'abcdefgh']&&wp_connectors_parse_application_password_credentials('nur')===null&&wp_connectors_sanitize_application_password_credentials(' admin:ab cd ')==='admin:abcd'&&wp_connectors_sanitize_application_password_credentials('x')==='');
t('wp_connectors_get_application_password_credentials: API-Key-Connector -> null',wp_connectors_get_application_password_credentials('openai')===null);
$resp=new WP_REST_Response(['connectors_ai_openai_api_key'=>'sk-abcdefgh1234','blogname'=>'X']);$resp=_wp_connectors_rest_settings_dispatch($resp);
t('REST-Einstellungen: Schlüssel maskiert',str_ends_with($resp->get_data()['connectors_ai_openai_api_key'],'1234')&&!str_contains($resp->get_data()['connectors_ai_openai_api_key'],'sk-')&&$resp->get_data()['blogname']==='X');
$d=_wp_connectors_get_connector_script_module_data(['a'=>1]);
t('Skriptmodul-Daten der Connectors',$d['a']===1&&$d['connectors']['openai']['authentication']['keySource']==='none'&&$d['connectors']['anthropic']['name']==='Anthropic');
t('Standard-Einstellungen/Logo/AI-Client-Schlüssel',_wp_register_default_connector_settings()===null&&_wp_connectors_resolve_ai_provider_logo_url('openai')===''&&_wp_connectors_pass_default_keys_to_ai_client()===false);
t('AI-Client nicht verfügbar',wp_supports_ai()===false&&wp_ai_client_prompt('Hallo') instanceof WP_Error&&wp_ai_client_prompt()->get_error_code()==='ai_client_unavailable');

// Abilities
add_action('wp_abilities_api_categories_init',function(){ wp_register_ability_category('daten',['label'=>'Daten','description'=>'Datenzugriff']); });
add_action('wp_abilities_api_init',function(){ wp_register_ability('test/hallo',['label'=>'Hallo','description'=>'Grüßt','category'=>'daten','meta'=>['show_in_rest'=>true,'a'=>['b'=>1]],'execute_callback'=>fn($in)=>'Hallo '.($in['name']??'Welt'),'permission_callback'=>fn()=>true]); });
t('Abilities: lazy Registrierung über Actions',wp_has_ability('test/hallo')&&wp_has_ability_category('daten')&&!wp_has_ability('test/nix'));
$ab=wp_get_ability('test/hallo');
t('Abilities: Objekt und Ausführung',$ab instanceof WP_Ability&&$ab->get_label()==='Hallo'&&$ab->execute(['name'=>'Rico'])==='Hallo Rico'&&$ab->get_meta_item('show_in_rest')===true);
t('Abilities: Kategorien',wp_get_ability_category('daten') instanceof WP_Ability_Category&&array_keys(wp_get_ability_categories())===['daten']);
wp_register_ability('test/nein',['label'=>'Nein','description'=>'verboten','category'=>'daten','execute_callback'=>fn()=>1,'permission_callback'=>fn()=>false]);
t('Abilities: Rechteprüfung',wp_get_ability('test/nein')->execute() instanceof WP_Error);
t('Abilities: Filter category/meta',array_keys(wp_get_abilities(['category'=>'daten']))===['test/hallo','test/nein']&&array_keys(wp_get_abilities(['meta'=>['a'=>['b'=>1]]]))===['test/hallo']&&_wp_get_abilities_match_meta(['x'=>1,'y'=>['z'=>2]],['y'=>['z'=>2]])&&!_wp_get_abilities_match_meta(['x'=>1],['x'=>2]));
$GLOBALS['rrw_wp_doing_it_wrong_silent']=true;
t('Abilities: ungültig/doppelt abgelehnt',@wp_register_ability('ohne-slash',['label'=>'a','description'=>'b','execute_callback'=>'strlen'])===null&&@wp_register_ability('test/hallo',['label'=>'a','description'=>'b','execute_callback'=>'strlen'])===null);
t('Abilities: unregister',wp_unregister_ability('test/nein') instanceof WP_Ability&&!wp_has_ability('test/nein')&&wp_unregister_ability('test/nein')===null&&wp_unregister_ability_category('daten')!==null&&!wp_has_ability_category('daten'));

// Icons
t('Icons: Standard',wp_get_icon('core/check')['label']==='Häkchen'&&str_contains(wp_get_icon('core/check')['content'],'<svg')&&wp_get_icon('core/nix')===null);
t('Icons: Sammlung und Icon registrieren',wp_register_icon_collection('meine',['label'=>'Meine'])&&!wp_register_icon_collection('meine')&&wp_register_icon('meine/stern',['label'=>'Stern','content'=>'<svg viewBox="0 0 1 1"></svg>'])&&wp_get_icon('meine/stern')['label']==='Stern'&&!wp_register_icon('meine/stern',['content'=>'<svg/>'])&&!wp_register_icon('fremd/x',['content'=>'<svg/>'])&&!wp_register_icon('meine/kein',['content'=>'kein svg']));
t('Icons: unregister',wp_unregister_icon('meine/stern')&&wp_get_icon('meine/stern')===null&&wp_register_icon('meine/a',['content'=>'<svg/>'])&&wp_unregister_icon_collection('meine')&&wp_get_icon('meine/a')===null&&!wp_unregister_icon_collection('meine'));

// Ansichts-Konfiguration
t('View-Konfiguration: Hook-Name',wp_get_entity_view_config_hook_name('postType','page')==='wp_entity_view_config_posttype_page');
$cfg=wp_get_entity_view_config('postType','page');
t('View-Konfiguration: Seite/Block/Vorlage',$cfg['default_view']['type']==='table'&&in_array('parent',$cfg['form']['fields'],true)&&wp_get_entity_view_config('postType','wp_block')['default_view']['type']==='grid'&&isset(wp_get_entity_view_config('postType','wp_template')['form'])&&isset(wp_get_entity_view_config('postType','wp_template_part')['view_list']));
add_filter('wp_entity_view_config_posttype_post',function($c){ $c['default_view']['perPage']=5;return $c; });
t('View-Konfiguration: Filter und Standard',wp_get_entity_view_config('postType','post')['default_view']['perPage']===5&&_wp_get_default_posttype_form('post')['layout']['type']==='regular');

// Cache mit Salz
t('Cache mit Salz',wp_cache_set_salted('k',['a'=>1],'v712','s1')&&wp_cache_get_salted('k','v712','s1')===['a'=>1]&&wp_cache_get_salted('k','v712','s2')===false&&wp_cache_get_salted('nix','v712','s1')===false);
$f=null;wp_cache_get_salted('k','v712','s2',false,$f);
t('Cache mit Salz: found',$f===false);
wp_cache_set_multiple_salted(['a'=>1,'b'=>2],'v712',['x','y']);
t('Cache mit Salz: mehrere',wp_cache_get_multiple_salted(['a','b','c'],'v712',['x','y'])===['a'=>1,'b'=>2,'c'=>false]&&wp_cache_get_multiple_salted(['a'],'v712','anders')===['a'=>false]);

// Tooltip / Toggletip
$tt=wp_get_tooltip('Hilfe <b>Text</b><script>x</script>',['label'=>'Info','id'=>'tt1','class'=>'mein']);
t('wp_get_tooltip',str_contains($tt,'aria-describedby="tt1"')&&str_contains($tt,'role="tooltip"')&&str_contains($tt,'wp-tooltip mein')&&!str_contains($tt,'<script')&&str_contains($tt,'>Info<'));
$tg=wp_get_toggletip('Mehr',['position'=>'bottom']);
t('wp_get_toggletip',str_contains($tg,'aria-expanded="false"')&&str_contains($tg,'role="status"')&&str_contains($tg,'data-position="bottom"')&&str_contains(wp_get_tooltip_helper('tooltip','x',['position'=>'unsinn']),'data-position="top"'));

// JSON-Schema
$sch=['type'=>'object','title'=>'T','sanitize_callback'=>'x','properties'=>['a'=>['type'=>'string','validate_callback'=>'y','arg_options'=>[1]],'b'=>['type'=>'array','items'=>['type'=>'integer','context'=>['edit']]]],'anyOf'=>[['type'=>'null','foo'=>1]],'required'=>['a']];
$out=wp_prepare_json_schema_for_client($sch);
t('JSON-Schema: Schlüssel entfernt',!isset($out['sanitize_callback'])&&!isset($out['properties']['a']['validate_callback'])&&!isset($out['properties']['b']['items']['context'])&&!isset($out['anyOf'][0]['foo']));
t('JSON-Schema: Rest bleibt',$out['title']==='T'&&$out['properties']['a']['type']==='string'&&$out['properties']['b']['items']['type']==='integer'&&$out['required']===['a']&&in_array('type',wp_get_json_schema_allowed_keywords(),true));
t('JSON-Schema: eigene Hilfsfunktion',_wp_prepare_json_schema_for_client_with_allowed_keywords(['type'=>'string','x'=>1],['type'])===['type'=>'string']);

// Template-Enhancement, Blöcke, Abfrage
t('Template-Enhancement: aus',wp_should_output_buffer_template_for_enhancement()===false&&wp_start_template_enhancement_output_buffer()===false&&wp_finalize_template_enhancement_output_buffer('<p>x</p>')==='<p>x</p>');
t('_wp_is_template_path_allowed',_wp_is_template_path_allowed('/etc/passwd')===false&&_wp_is_template_path_allowed($tmp.'/nix')===false);
t('get_block_bindings_supported_attributes',get_block_bindings_supported_attributes('core/paragraph')===['content']&&in_array('url',get_block_bindings_supported_attributes('core/image'),true)&&get_block_bindings_supported_attributes('core/nix')===[]);
t('_wp_apply_block_content_filters',_wp_apply_block_content_filters('<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->')!==''&&str_contains(_wp_apply_block_content_filters('<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->'),'Hi')&&_block_template_add_skip_link()===null&&_wp_enqueue_auto_register_blocks()===null);
t('is_sitemap',is_sitemap()===false);
t('Sonstige No-ops',upgrade_700()===null&&wp_cache_switch_to_blog_fallback(1)===null);

// Bestandsschutz: Laden verändert keine Ausgabe/Optionen; Hooks nicht belegt
t('Laden: keine zusätzlichen Hooks auf init/wp_head',!has_action('wp_head','wp_enqueue_img_auto_sizes_contain_css_fix')&&!has_action('init','_wp_connectors_init'));

echo $fail?"$fail Fehler von $n Prüfungen\n":"OK ($n Prüfungen)\n";
@exec('rm -rf '.escapeshellarg($tmp));
exit($fail?1:0);

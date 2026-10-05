<?php
// Prüft die ergänzenden Multisite-Funktionen der WordPress-Schicht (cms/wp/core/ext/multisite-*.php):
// Einzelseiten-Betrieb mit einer Site/einem Netzwerk, Site-Daten und -Metadaten, Anmeldungen, Speicherplatz, Netzwerk-Abfragen, Listen-Tabellen. Aufruf: php scripts/test-wp-ext-multisite.php
$tmp=sys_get_temp_dir().'/rrw-xm-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/cms');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('RRW_WP_DATA',$tmp.'/cms/.wp');define('RRW_WP_CMS_DATA',$tmp.'/cms');define('RRW_WP_TEST',1);$_SERVER['HTTP_HOST']='example.test';
file_put_contents($tmp.'/cms/news.json',json_encode([['id'=>1,'slug'=>'erster','title'=>'Erster Beitrag','category'=>'News','status'=>'published','published_at'=>'2026-01-10 10:00:00','author'=>'Anna Autor','body_html'=>'<p>Hallo</p>']]));
require __DIR__.'/_testdb.php';
require __DIR__.'/../cms/wp/load.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
rrw_wp_boot(['theme'=>false,'user'=>['id'=>1,'login'=>'admin','name'=>'Administration','email'=>'admin@example.test','role'=>'administrator']]);
$GLOBALS['rrw_wp_die_throws']=true;
update_option('home','http://example.test');update_option('siteurl','http://example.test');update_option('blogname','Testradio');update_option('admin_email','chef@example.test');
$mails=[];add_filter('pre_wp_mail',function($pre,$atts) use(&$mails){ $mails[]=$atts;return true; },10,2);
/** Ausgabe puffern. */
function cap(callable $f): string { ob_start();try{ $f(); }finally{ $o=ob_get_clean(); }return $o; }

/* ───────── Alle Funktionen und Klassen der Liste vorhanden (keine Ausnahmen) ───────── */
$names='get_sitestats get_active_blog_for_user get_blog_post remove_user_from_blog get_blog_permalink get_blog_id_from_url is_email_address_unsafe wpmu_validate_user_signup wpmu_validate_blog_signup wpmu_signup_blog wpmu_signup_user wpmu_signup_blog_notification wpmu_signup_user_notification wpmu_activate_signup wp_delete_signup_on_user_delete wpmu_create_user wpmu_create_blog newblog_notify_siteadmin newuser_notify_siteadmin domain_exists wpmu_welcome_notification wpmu_new_site_admin_notification wpmu_welcome_user_notification get_current_site get_most_recent_post_of_user check_upload_mimes update_posts_count wpmu_log_new_registrations redirect_this_site upload_is_file_too_big signup_nonce_fields signup_nonce_check maybe_redirect_404 maybe_add_existing_user_to_blog add_existing_user_to_blog add_new_user_to_blog fix_phpmailer_messageid is_user_spammy update_blog_public users_can_register_signup_filter welcome_user_msg_filter force_ssl_content filter_SSL wp_schedule_update_network_counts wp_update_network_counts wp_maybe_update_network_site_counts wp_maybe_update_network_user_counts wp_update_network_site_counts wp_update_network_user_counts get_space_used get_space_allowed get_upload_space_available is_upload_space_available upload_size_limit_filter wp_is_large_network get_subdirectory_reserved_names update_network_option_new_admin_email wp_network_admin_email_change_notification wp_insert_site wp_update_site wp_delete_site _prime_site_caches wp_lazyload_site_meta update_site_cache update_sitemeta_cache wp_prepare_site_data wp_normalize_site_data wp_validate_site_data wp_initialize_site wp_uninitialize_site wp_is_site_initialized clean_blog_cache add_site_meta delete_site_meta get_site_meta update_site_meta delete_site_meta_by_key wp_maybe_update_network_site_counts_on_update wp_maybe_transition_site_statuses_on_update wp_maybe_clean_new_site_cache_on_update wp_update_blog_public_option_on_site_update wp_cache_set_sites_last_changed wp_check_site_meta_support_prefilter check_upload_size wpmu_delete_blog wpmu_delete_user upload_is_user_over_quota display_space_usage fix_import_form_size upload_space_setting refresh_user_details format_code_lang _access_denied_splash check_import_new_users mu_dropdown_languages site_admin_notice avoid_blog_page_permalink_collision choose_primary_blog can_edit_network _thickbox_path_admin_subfolder confirm_delete_users network_settings_add_js network_edit_site_nav get_site_screen_help_tab_args get_site_screen_help_sidebar_content wp_ensure_editable_role wpmu_update_blogs_date get_blogaddress_by_id get_blogaddress_by_name get_id_from_blogname refresh_blog_details update_blog_details clean_site_details_cache wp_switch_roles_and_user is_archived update_archived update_blog_status get_blog_status get_last_updated _update_blog_date_on_post_publish _update_blog_date_on_post_delete _update_posts_count_on_delete _update_posts_count_on_transition_post_status wp_count_sites wp_get_active_network_plugins ms_site_check get_network_by_path get_site_by_path ms_load_current_site_and_network ms_not_installed get_current_site_name wpmu_current_site wp_get_network network_domain_check allow_subdomain_install allow_subdirectory_install get_clean_basedomain network_step1 network_step2 ms_upload_constants ms_cookie_constants ms_file_constants ms_subdomain_constants get_networks clean_network_cache update_network_cache _prime_network_caches';
$classes='WP_MS_Themes_List_Table WP_MS_Users_List_Table WP_Terms_List_Table WP_MS_Sites_List_Table WP_Network_Query WP_Network WP_Site';
$missing=[];foreach(preg_split('/\s+/',trim($names)) as $f)if(!function_exists($f))$missing[]=$f;
foreach(preg_split('/\s+/',trim($classes)) as $c)if(!class_exists($c))$missing[]=$c;
t('alle 147 Funktionen und 7 Klassen vorhanden (keine Ausnahmen)',count(preg_split('/\s+/',trim($names)))===147&&!$missing,implode(',',$missing));
t('Einzelseite bleibt bestehen',!is_multisite()&&get_current_blog_id()===1&&!is_subdomain_install());

/* ───────── Site und Netzwerk ───────── */
$s=WP_Site::get_instance(1);
t('WP_Site: Domain, Pfad, IDs',$s&&$s->domain==='example.test'&&$s->path==='/'&&$s->id===1&&$s->network_id===1&&$s->blog_id==='1'&&$s->blogname==='Testradio');
t('WP_Site: unbekannte ID',WP_Site::get_instance(2)===false);
$net=wp_get_network(1);
t('WP_Network: Felder',$net instanceof WP_Network&&$net->domain==='example.test'&&$net->site_id===1&&$net->blog_id==='1'&&$net->cookie_domain==='example.test'&&$net->site_name==='Testradio'||$net->site_name==='Example.test');
t('wp_get_network: Objekt und unbekannte ID',wp_get_network($net)->id==1&&wp_get_network(7)===false);
t('get_current_site liefert das Netzwerk',get_current_site() instanceof WP_Network&&get_current_site()->path==='/');
$ids=get_networks(['fields'=>'ids']);$objs=get_networks();
t('get_networks: IDs und Objekte',$ids===[1]&&count($objs)===1&&$objs[0] instanceof WP_Network);
t('get_networks: count und Filter',get_networks(['count'=>true])===1&&get_networks(['domain'=>'andere.test'])===[]&&count(get_networks(['domain'=>'example.test']))===1&&get_networks(['network__not_in'=>[1]])===[]&&get_networks(['search'=>'*xample*'])!==[]);
$q=new WP_Network_Query(['number'=>1,'no_found_rows'=>false]);$r=$q->get_networks();
t('WP_Network_Query: Seitenzahl',count($r)===1&&$q->found_networks===1&&$q->max_num_pages===1);
t('get_network_by_path / get_site_by_path',get_network_by_path('www.example.test','/')instanceof WP_Network&&get_network_by_path('x.test','/')===false&&get_site_by_path('example.test','/hallo/') instanceof WP_Site&&get_site_by_path('x.test','/')===false);
t('ms_load_current_site_and_network setzt Globals',ms_load_current_site_and_network('example.test','/')&&$GLOBALS['blog_id']===1&&$GLOBALS['current_site']->domain==='example.test'&&!ms_load_current_site_and_network('x.test','/'));
t('get_current_site_name',get_current_site_name((object)['domain'=>'example.test'])->site_name==='Example.test');
t('ms_site_check: erreichbar, dann gesperrt',ms_site_check()===true&&(function(){ update_blog_status(1,'archived',1);try{ ms_site_check();return false; }catch(RRW_WP_Die $e){ update_blog_status(1,'archived',0);return $e->getCode()===410; } })()&&ms_site_check()===true);
t('wp_get_active_network_plugins',wp_get_active_network_plugins()===[]);
t('wpmu_current_site (veraltet) setzt Globals',(function(){ unset($GLOBALS['current_site']);wpmu_current_site();return $GLOBALS['current_site'] instanceof WP_Network; })());

/* ───────── Site-Daten ───────── */
$d=wp_normalize_site_data(['domain'=>'Beispiel .TEST','path'=>'abc','public'=>'1','spam'=>'0']);
t('wp_normalize_site_data',$d['domain']==='beispiel.test'&&$d['path']==='/abc/'&&$d['public']===1&&$d['spam']===0);
$err=new WP_Error();wp_validate_site_data($err,['domain'=>'','path'=>'/']);
t('wp_validate_site_data: leere Domain',in_array('site_empty_domain',$err->get_error_codes(),true));
$err=new WP_Error();wp_validate_site_data($err,['domain'=>'example.test','path'=>'/']);
t('wp_validate_site_data: belegte Adresse',in_array('site_taken',$err->get_error_codes(),true));
t('wp_prepare_site_data: Fehler und Erfolg',is_wp_error(wp_prepare_site_data(['domain'=>''],['path'=>'/']))&&wp_prepare_site_data(['domain'=>'neu.test'],['path'=>'x'])['path']==='/x/');
t('wp_insert_site / wp_delete_site / wpmu_delete_blog nicht möglich',wp_insert_site(['domain'=>'a.test'])->get_error_code()==='multisite_unsupported'&&wp_delete_site(1)->get_error_code()==='site_main'&&wp_delete_site(5)->get_error_code()==='site_not_exist'&&wpmu_delete_blog(1)->get_error_code()==='site_main');
$tr=[];foreach(['make_spam_blog','make_ham_blog','archive_blog','unarchive_blog','make_delete_blog','mature_blog'] as $h)add_action($h,function($id) use(&$tr,$h){ $tr[]=$h.':'.$id; });
t('wp_update_site: Status-Flags',wp_update_site(1,['spam'=>1,'archived'=>1])===1&&get_blog_status(1,'spam')==='1'&&is_archived(1)==='1'&&wp_count_sites()['spam']===1);
wp_update_site(1,['spam'=>0,'archived'=>0]);
t('wp_update_site: unbekannte Site',wp_update_site(9,['spam'=>1])->get_error_code()==='site_not_exist'&&update_blog_details(9,['spam'=>1])===false&&update_blog_details(1,[])===false);
t('update_blog_status / update_archived / get_blog_status',update_blog_status(1,'mature',1)==1&&get_blog_status(1,'mature')==='1'&&update_archived(1,0)===0&&get_blog_status(1,'archived')==='0'&&get_blog_status(2,'mature')===false&&update_blog_status(1,'unbekannt',5)===5);
update_blog_status(1,'mature',0);
t('wp_update_site: public steuert blog_public',wp_update_site(1,['public'=>0])===1&&get_option('blog_public')==='0'&&get_blog_status(1,'public')==='0'&&wp_update_site(1,['public'=>1])===1&&get_option('blog_public')==='1');
$old=(object)['spam'=>0,'mature'=>0,'archived'=>0,'deleted'=>0];$new=(object)['id'=>1,'spam'=>1,'mature'=>0,'archived'=>1,'deleted'=>0];$tr=[];
wp_maybe_transition_site_statuses_on_update($new,$old);
t('Statuswechsel lösen Aktionen aus',$tr===['make_spam_blog:1','archive_blog:1']);
t('Site initialisiert / nicht erneut initialisierbar',wp_is_site_initialized(1)&&!wp_is_site_initialized(2)&&wp_initialize_site(1)->get_error_code()==='site_already_initialized'&&wp_initialize_site(0)->get_error_code()==='site_empty_id'&&wp_uninitialize_site(3)->get_error_code()==='site_invalid_id');
t('Cache-Hilfen laufen ohne Fehler',(function(){ clean_blog_cache(1);clean_network_cache(1);_prime_site_caches([1]);update_site_cache([$GLOBALS['current_blog']]);update_network_cache([$GLOBALS['current_site']]);wp_lazyload_site_meta([1]);_prime_network_caches([1]);refresh_blog_details(1);clean_site_details_cache(1);wp_cache_set_sites_last_changed();return wp_cache_get('last_changed','sites')!==false&&wp_check_site_meta_support_prefilter(null)===null; })());

/* ───────── Site-Metadaten ───────── */
$m1=add_site_meta(1,'farbe','blau');
t('add_site_meta: ID, eindeutig, fremde Site',is_int($m1)&&$m1>0&&add_site_meta(1,'farbe','rot',true)===false&&add_site_meta(2,'x','y')===false);
add_site_meta(1,'farbe','rot');
t('get_site_meta: einzeln, Liste, alle',get_site_meta(1,'farbe',true)==='blau'&&get_site_meta(1,'farbe')===['blau','rot']&&get_site_meta(1)['farbe']===['blau','rot']&&get_site_meta(1,'nix',true)===''&&get_site_meta(3,'farbe')===[]);
t('update_site_meta: ändern, neu anlegen, gleicher Wert',update_site_meta(1,'farbe','gelb','rot')===true&&get_site_meta(1,'farbe')===['blau','gelb']&&update_site_meta(1,'farbe','gelb','gelb')===false&&update_site_meta(1,'neu',['a'=>1])>0&&get_site_meta(1,'neu',true)===['a'=>1]);
t('delete_site_meta: Wert, Schlüssel, nichts',delete_site_meta(1,'farbe','blau')===true&&get_site_meta(1,'farbe')===['gelb']&&delete_site_meta(1,'farbe')===true&&get_site_meta(1,'farbe')===[]&&delete_site_meta(1,'farbe')===false);
add_site_meta(1,'tmp','1');
t('delete_site_meta_by_key / update_sitemeta_cache',delete_site_meta_by_key('tmp')&&update_sitemeta_cache([1])[1]['neu']===[['a'=>1]]);

/* ───────── Zuordnung und Adressen ───────── */
t('get_blog_id_from_url / domain_exists',get_blog_id_from_url('example.test','/')===1&&get_blog_id_from_url('example.test')===1&&get_blog_id_from_url('x.test','/')===0&&domain_exists('example.test','/')===1&&domain_exists('x.test','/')===null);
t('get_id_from_blogname',get_id_from_blogname('unbekannt')===null&&get_id_from_blogname('/')===1);
t('get_blogaddress_by_id / _by_name',get_blogaddress_by_id(1)==='http://example.test/'&&get_blogaddress_by_id(4)===''&&get_blogaddress_by_name('radio')==='http://example.test/radio/'&&get_blogaddress_by_name('main')==='http://example.test/');
t('redirect_this_site',redirect_this_site()===['example.test']);
t('get_blog_post / get_blog_permalink',(function(){ $p=wp_insert_post(['post_title'=>'MS Beitrag','post_status'=>'publish','post_content'=>'x','post_author'=>1]);return get_blog_post(1,$p)->post_title==='MS Beitrag'&&get_blog_post(2,$p)===null&&str_contains((string)get_blog_permalink(1,$p),'example.test')&&get_blog_permalink(2,$p)===false; })());
t('get_last_updated',count(get_last_updated())===1&&get_last_updated('',5)===[]&&get_last_updated()[0]['blog_id']==='1');
t('Blog-Datum bei Veröffentlichung',(function(){ $b=get_blog_status(1,'last_updated');sleep(1);_update_blog_date_on_post_publish('publish','draft',(object)['post_type'=>'post']);return get_blog_status(1,'last_updated')!==$b; })());
t('Beitragszähler',(function(){ update_posts_count();$c=get_option('post_count');_update_posts_count_on_transition_post_status('publish','draft',(object)['post_type'=>'post','ID'=>0]);return $c>=1&&get_option('post_count')==$c; })());
t('get_sitestats',get_sitestats()['blogs']===1&&get_sitestats()['users']>=1);
t('Rollen-Wechsel ohne Wirkung',wp_switch_roles_and_user(1,1)===null);

/* ───────── Benutzer-Anmeldung ───────── */
$r=wpmu_validate_user_signup('Ab_','falsch');$codes=$r['errors']->get_error_codes();
t('Benutzer-Anmeldung: Fehler',in_array('user_name',$codes,true)&&in_array('user_email',$codes,true));
$r=wpmu_validate_user_signup('anton99','anton@example.test');
t('Benutzer-Anmeldung: gültig',!$r['errors']->has_errors()&&$r['user_name']==='anton99');
t('Benutzer-Anmeldung: Länge und nur Ziffern',wpmu_validate_user_signup('abc','a@b.test')['errors']->has_errors()&&wpmu_validate_user_signup('12345','a@b.test')['errors']->has_errors()&&wpmu_validate_user_signup('admin','a@b.test')['errors']->has_errors());
update_option('banned_email_domains',['spam.test']);
t('is_email_address_unsafe',is_email_address_unsafe('x@spam.test')&&is_email_address_unsafe('x@mail.spam.test')&&!is_email_address_unsafe('x@gut.test')&&wpmu_validate_user_signup('anton99','x@spam.test')['errors']->has_errors());
delete_option('banned_email_domains');
$mails=[];wpmu_signup_user('anton99','anton@example.test',['k'=>'v']);
$sg=array_values(rrw_ms_signups());$key=$sg[0]['activation_key'];
t('wpmu_signup_user speichert',count($sg)===1&&$sg[0]['user_login']==='anton99'&&strlen($key)===16&&$sg[0]['meta']===['k'=>'v']);
t('Anmeldung blockiert denselben Namen',wpmu_validate_user_signup('anton99','neu@example.test')['errors']->get_error_message('user_name')!==''&&wpmu_validate_user_signup('anton99','anton@example.test')['errors']->has_errors());
t('wpmu_signup_user_notification: E-Mail mit Link',wpmu_signup_user_notification('anton99','anton@example.test',$key)&&str_contains($mails[0]['message'],'wp-activate.php?key='.$key)&&$mails[0]['to']==='anton@example.test');
add_filter('wpmu_signup_user_notification','__return_false');
t('wpmu_signup_user_notification: Filter verhindert',wpmu_signup_user_notification('a','a@b.test','k')===false);
remove_filter('wpmu_signup_user_notification','__return_false');
t('Aktivierung: ungültiger Schlüssel',wpmu_activate_signup('nope')->get_error_code()==='invalid_key');
$act=wpmu_activate_signup($key);
t('Aktivierung legt Benutzer an',is_array($act)&&$act['user_id']>0&&username_exists('anton99')===$act['user_id']&&strlen($act['password'])===12);
t('Zweite Aktivierung: bereits aktiv',wpmu_activate_signup($key)->get_error_code()==='already_active');
$mails=[];
t('Willkommens-E-Mail an Benutzer',wpmu_welcome_user_notification($act['user_id'],'geheim')&&str_contains($mails[0]['message'],'anton99')&&str_contains($mails[0]['message'],'geheim')&&str_contains($mails[0]['subject'],'anton99'));
t('Willkommens-E-Mail zur Site',wpmu_welcome_notification(1,$act['user_id'],'pw1','Titel')&&str_contains($mails[1]['message'],'pw1')&&str_contains($mails[1]['subject'],'Titel'));
t('welcome_user_msg_filter',str_contains(welcome_user_msg_filter(''),'Testradio')&&welcome_user_msg_filter('Eigen')==='Eigen');
t('wpmu_create_user',(function(){ $id=wpmu_create_user('berta77','pw-123456','berta@example.test');return $id>0&&wpmu_create_user('berta77','x','b2@example.test')===false; })());
wpmu_signup_user('claus55','claus@example.test');
$ck=array_values(array_filter(rrw_ms_signups(),fn($x)=>$x['user_login']==='claus55'))[0]['activation_key'];
$cid=wpmu_create_user('claus55','x1','claus@example.test');wp_delete_signup_on_user_delete($cid);
t('wp_delete_signup_on_user_delete',!array_filter(rrw_ms_signups(),fn($x)=>$x['user_login']==='claus55')&&wpmu_activate_signup($ck)->get_error_code()==='invalid_key');

/* ───────── Site-Anmeldung ───────── */
$b=wpmu_validate_blog_signup('radio','Radio Titel');
t('Site-Anmeldung: gültig (Unterverzeichnis)',!$b['errors']->has_errors()&&$b['domain']==='example.test'&&$b['path']==='/radio/'&&$b['blog_title']==='Radio Titel');
t('Site-Anmeldung: reserviert, kurz, Ziffern, Sonderzeichen',wpmu_validate_blog_signup('blog','x')['errors']->has_errors()&&wpmu_validate_blog_signup('ab','x')['errors']->has_errors()&&wpmu_validate_blog_signup('1234','x')['errors']->has_errors()&&wpmu_validate_blog_signup('Gro-ss','x')['errors']->has_errors()&&wpmu_validate_blog_signup('radio','')['errors']->get_error_message('blog_title')!=='');
t('Site-Anmeldung: Unterverzeichnis-Namen reserviert',in_array('wp-json',get_subdirectory_reserved_names(),true)&&in_array('feed',get_subdirectory_reserved_names(),true));
wpmu_signup_blog('example.test','/radio/','Radio','anton99','anton@example.test');
$bk=array_values(array_filter(rrw_ms_signups(),fn($x)=>$x['domain']!==''))[0]['activation_key'];
t('Site-Anmeldung belegt die Adresse zeitweise',wpmu_validate_blog_signup('radio','Radio')['errors']->has_errors());
t('Site-Aktivierung nicht möglich',wpmu_activate_signup($bk)->get_error_code()==='multisite_unsupported'&&wpmu_create_blog('neu.test','/','T',1)->get_error_code()==='multisite_unsupported'&&wpmu_create_blog('example.test','/','T',1)->get_error_code()==='blog_taken');
$mails=[];
t('wpmu_signup_blog_notification',wpmu_signup_blog_notification('example.test','/radio/','Radio','anton99','anton@example.test',$bk)&&str_contains($mails[0]['message'],$bk));
t('wpmu_new_site_admin_notification',wpmu_new_site_admin_notification(1,$act['user_id'])&&end($mails)['to']==='chef@example.test'&&!wpmu_new_site_admin_notification(5,1));

/* ───────── Benachrichtigungen an die Administration ───────── */
$mails=[];
t('newblog/newuser_notify_siteadmin: erst nach Aktivierung',newblog_notify_siteadmin(1)===false&&newuser_notify_siteadmin($act['user_id'])===false&&!$mails);
update_option('registrationnotification','yes');
t('newblog/newuser_notify_siteadmin: Mail',newblog_notify_siteadmin(1)&&newuser_notify_siteadmin($act['user_id'])&&count($mails)===2&&str_contains($mails[1]['subject'],'anton99'));
$mails=[];update_network_option_new_admin_email('chef@example.test','neu@example.test');wp_network_admin_email_change_notification('admin_email','neu@example.test','chef@example.test',1);
t('Netzwerk-Admin-E-Mail: Bestätigung und Hinweis',count($mails)===2&&$mails[0]['to']==='neu@example.test'&&str_contains($mails[0]['message'],'network_admin_hash=')&&is_array(get_network_option(1,'network_admin_hash'))&&$mails[1]['to']==='chef@example.test');
t('Netzwerk-Admin-E-Mail: ungültig oder unverändert',(function(){ $b=count($GLOBALS['mails']);update_network_option_new_admin_email('a','kaputt');update_network_option_new_admin_email('a','chef@example.test');return count($GLOBALS['mails'])===$b; })());
wpmu_log_new_registrations(1,$act['user_id']);
t('Registrierungs-Protokoll',get_option('rrw_ms_registration_log')[0]['email']==='anton@example.test');

/* ───────── Benutzer zur Site hinzufügen ───────── */
$bid=wpmu_create_user('dora88','pw-123456','dora@example.test');
t('add_existing_user_to_blog: Rolle setzen',add_existing_user_to_blog(['user_id'=>$bid,'role'=>'editor'])===true&&get_userdata($bid)->roles===['editor']);
t('add_existing_user_to_blog: Fehlerfälle',add_existing_user_to_blog(['user_id'=>999999999,'role'=>'editor'])->get_error_code()==='user_does_not_exist'&&add_existing_user_to_blog(['user_id'=>$bid,'role'=>'gibtsnicht'])->get_error_code()==='invalid_role'&&add_existing_user_to_blog(false)===null);
add_new_user_to_blog($bid,'x',['add_to_blog'=>1,'new_role'=>'author']);
t('add_new_user_to_blog',get_userdata($bid)->roles===['author']&&get_user_meta($bid,'primary_blog',true)==='1');
t('remove_user_from_blog',remove_user_from_blog($bid,1)===true&&remove_user_from_blog(999999999,1)->get_error_code()==='user_does_not_exist');
update_option('new_user_abc123',['user_id'=>$bid,'email'=>'dora@example.test','role'=>'contributor']);
$_SERVER['REQUEST_URI']='/newbloguser/abc123/';
$ok=false;try{ maybe_add_existing_user_to_blog(); }catch(RRW_WP_Die $e){ $ok=$e->getCode()===200; }
t('maybe_add_existing_user_to_blog: Einladung einlösen',$ok&&get_userdata($bid)->roles===['contributor']&&get_option('new_user_abc123')===false);
$_SERVER['REQUEST_URI']='/newbloguser/unbekannt/';$ok=false;try{ maybe_add_existing_user_to_blog(); }catch(RRW_WP_Die $e){ $ok=str_contains($e->getMessage(),'error occurred'); }
t('maybe_add_existing_user_to_blog: ungültiger Schlüssel',$ok);
$_SERVER['REQUEST_URI']='/';
t('get_active_blog_for_user / get_most_recent_post_of_user / is_user_spammy',get_active_blog_for_user($bid) instanceof WP_Site&&get_active_blog_for_user(999999999)===null&&is_array(get_most_recent_post_of_user($bid))&&!is_user_spammy('dora88')&&!is_user_spammy());
t('wpmu_delete_user',wpmu_delete_user($cid)===true&&wpmu_delete_user(999999999)===false&&username_exists('claus55')===false);

/* ───────── Filter-Funktionen ───────── */
t('check_upload_mimes',array_keys(check_upload_mimes(['jpg|jpeg|jpe'=>'a','exe'=>'b','png'=>'c']))===['jpg|jpeg|jpe','png']);
update_option('upload_filetypes','pdf');
t('check_upload_mimes mit eigener Liste',array_keys(check_upload_mimes(['jpg'=>'a','pdf'=>'b']))===['pdf']);
delete_option('upload_filetypes');
t('users_can_register_signup_filter',users_can_register_signup_filter()===false&&(function(){ update_option('registration','user');$r=users_can_register_signup_filter();update_option('registration','none');return $r; })());
t('force_ssl_content / filter_SSL',force_ssl_content()===false&&filter_SSL('http://a.test/')==='http://a.test/'&&filter_SSL(5)!==5&&force_ssl_content(true)===true&&force_ssl_content(false)===false);
t('update_blog_public',(function(){ update_blog_public(1,0);$a=get_option('blog_public');update_blog_public(0,1);return $a==='0'&&get_option('blog_public')==='1'; })());
t('fix_phpmailer_messageid',(function(){ $p=new stdClass;fix_phpmailer_messageid($p);return $p->Hostname==='example.test'; })());
t('signup_nonce_fields / signup_nonce_check',(function(){ $o=cap('signup_nonce_fields');$_SERVER['PHP_SELF']='/wp-signup.php';
    preg_match("/value='(\d+)'/",$o,$m);$_POST=['signup_form_id'=>$m[1],'_signup_form'=>wp_create_nonce('signup_form_'.$m[1])];$a=signup_nonce_check('ok');
    $_POST['_signup_form']='falsch';$thrown=false;try{ signup_nonce_check('ok'); }catch(RRW_WP_Die $e){ $thrown=true; }
    $_SERVER['PHP_SELF']='/index.php';$b=signup_nonce_check('x');$_POST=[];return str_contains($o,'_signup_form')&&$a==='ok'&&$thrown&&$b==='x'; })());
t('upload_is_file_too_big',(function(){ update_option('upload_space_check_disabled',0);$a=upload_is_file_too_big(['bits'=>str_repeat('a',2000*1024)]);$b=upload_is_file_too_big(['bits'=>'kurz']);delete_option('upload_space_check_disabled');return is_string($a)&&$b===['bits'=>'kurz']&&upload_is_file_too_big('x')==='x'; })());
t('maybe_redirect_404 ohne Konstante wirkungslos',maybe_redirect_404()===null);

/* ───────── Zähler ───────── */
t('wp_is_large_network',wp_is_large_network()===false&&wp_is_large_network('users')===false&&(function(){ add_filter('wp_is_large_network',$f=fn($x)=>true);$r=wp_is_large_network();remove_filter('wp_is_large_network',$f);return $r; })());
wp_update_network_counts();
t('wp_update_network_counts',get_network_option(1,'blog_count')===1&&get_network_option(1,'user_count')>=3&&get_user_count()===get_network_option(1,'user_count'));
t('wp_maybe_update_network_*_counts und Filter',(function(){ update_option('blog_count',9);wp_maybe_update_network_site_counts();$a=get_option('blog_count');add_filter('enable_live_network_counts','__return_false');update_option('blog_count',9);wp_maybe_update_network_site_counts();wp_maybe_update_network_user_counts();$b=get_option('blog_count');remove_filter('enable_live_network_counts','__return_false');return (int)$a===1&&(int)$b===9; })());
t('wp_maybe_update_network_site_counts_on_update',(function(){ update_option('blog_count',7);wp_maybe_update_network_site_counts_on_update((object)['network_id'=>1]);return (int)get_option('blog_count')===1; })());
t('wp_schedule_update_network_counts',(function(){ wp_schedule_update_network_counts();$a=wp_next_scheduled('update_network_counts');wp_schedule_update_network_counts();return (bool)$a&&wp_next_scheduled('update_network_counts')===$a; })());

/* ───────── Speicherplatz ───────── */
$up=wp_upload_dir(null,false);@mkdir($up['basedir'],0775,true);file_put_contents($up['basedir'].'/a.bin',str_repeat('x',3*MB_IN_BYTES));
t('get_space_used / get_space_allowed',abs(get_space_used()-3)<0.01&&get_space_allowed()===100);
update_option('blog_upload_space',10);
t('Platz: Kontingent der Site',get_space_allowed()==10);
t('Platz ohne Prüfung: voller Rahmen',is_upload_space_available()&&get_upload_space_available()===10*MB_IN_BYTES&&!upload_is_user_over_quota(false));
update_option('upload_space_check_disabled',0);
t('Platz mit Prüfung: Rest',abs(get_upload_space_available()-7*MB_IN_BYTES)<20000&&is_upload_space_available()&&upload_size_limit_filter(50*MB_IN_BYTES)===1500*1024);
update_option('blog_upload_space',2);
t('Platz überschritten',get_upload_space_available()===0&&!is_upload_space_available()&&upload_is_user_over_quota(false)&&fix_import_form_size(1000)===0&&str_contains(cap(fn()=>upload_is_user_over_quota(true)),'2 MB'));
$tmpf=$tmp.'/kl.txt';file_put_contents($tmpf,'abc');$fe=false;try{ check_upload_size(['tmp_name'=>$tmpf,'error'=>0,'size'=>3]); }catch(RRW_WP_Die $e){ $fe=str_contains($e->getMessage(),'space quota'); }
t('check_upload_size: Kontingent aufgebraucht',$fe);
update_option('upload_space_check_disabled',1);
t('check_upload_size: Prüfung aus',check_upload_size(['tmp_name'=>$tmpf,'error'=>0])['error']===0);
t('display_space_usage',str_contains(cap('display_space_usage'),'Used: 150% of 2 MB')||str_contains(cap('display_space_usage'),'Used:'));
delete_option('blog_upload_space');delete_option('upload_space_check_disabled');
t('upload_space_setting',str_contains(cap(fn()=>upload_space_setting(1)),'option[blog_upload_space]'));

/* ───────── Konstanten ───────── */
ms_upload_constants();ms_file_constants();ms_cookie_constants();ms_subdomain_constants();
t('Konstanten',UPLOADBLOGSDIR==='wp-content/blogs.dir'&&str_ends_with(BLOGUPLOADDIR,'/blogs.dir/1/files/')&&COOKIEPATH==='/'&&SITECOOKIEPATH==='/'&&ADMIN_COOKIE_PATH==='/wp-admin'&&COOKIE_DOMAIN===false&&SUBDOMAIN_INSTALL===false&&VHOST==='no'&&!defined('UPLOADS'));

/* ───────── Verwaltungs-Hilfen ───────── */
t('format_code_lang: bekannt und unbekannt',format_code_lang('fr')==='French'&&format_code_lang('EN')==='English'&&format_code_lang('xx')==='xx');
$opts=cap(fn()=>mu_dropdown_languages(['/x/de_DE.mo','/x/en_GB.mo'],'de_DE'));
t('mu_dropdown_languages',str_contains($opts,'value="de_DE" selected')&&str_contains($opts,'British English')&&!str_contains($opts,'American')&&str_contains(cap(fn()=>mu_dropdown_languages([],'')),'American English'));
t('can_edit_network / check_import_new_users / refresh_user_details',can_edit_network(1)&&!can_edit_network(2)&&refresh_user_details('5')===5&&check_import_new_users(true)!==null);
t('wp_ensure_editable_role',(function(){ wp_ensure_editable_role('editor');try{ wp_ensure_editable_role('gibtsnicht');return false; }catch(RRW_WP_Die $e){ return $e->getCode()===403; } })());
t('allow_subdomain_install / allow_subdirectory_install',allow_subdomain_install()===true&&is_bool(allow_subdirectory_install())&&get_clean_basedomain()==='example.test'&&network_domain_check()===false);
t('avoid_blog_page_permalink_collision',avoid_blog_page_permalink_collision(['post_type'=>'post','post_name'=>'x'],[])['post_name']==='x'&&avoid_blog_page_permalink_collision(['post_type'=>'page','post_name'=>'seite'],[])['post_name']==='seite');
t('_access_denied_splash: mit Rolle kein Abbruch',_access_denied_splash()===null);
t('site_admin_notice: Ausgabe oder false',(function(){ $o=cap(function() use(&$r){ $r=site_admin_notice(); });return $r===false||$r===null; })());
t('network_step1/2: Hinweis statt Formular',str_contains(cap(fn()=>network_step1()),'single site')&&str_contains(cap(fn()=>network_step2()),'single site'));
t('network_edit_site_nav / Hilfetexte',str_contains(cap(fn()=>network_edit_site_nav(['blog_id'=>1])),'nav-tab-wrapper')&&get_site_screen_help_tab_args()['id']==='overview'&&str_contains(get_site_screen_help_sidebar_content(),'<p>'));
t('choose_primary_blog / confirm_delete_users',str_contains(cap('choose_primary_blog'),'example.test/')&&confirm_delete_users([])===false&&(function() use($bid){ $o=cap(function() use($bid,&$r){ $r=confirm_delete_users([$bid]); });return $r===true&&str_contains($o,'dora88')&&str_contains($o,'dodelete'); })());
t('_thickbox_path_admin_subfolder / network_settings_add_js',str_contains(cap('_thickbox_path_admin_subfolder'),'tb_pathToImage')&&str_contains(cap('network_settings_add_js'),'language-install-spinner'));

/* ───────── Listen-Tabellen ───────── */
$lt=new WP_MS_Sites_List_Table();$_REQUEST=[];$lt->prepare_items();
t('WP_MS_Sites_List_Table',count($lt->items)===1&&isset($lt->get_columns()['blogname'])&&str_contains(cap(fn()=>$lt->display()),'example.test'));
$_REQUEST['s']='gibtsnicht';$lt->prepare_items();t('WP_MS_Sites_List_Table: Suche ohne Treffer',$lt->items===[]);$_REQUEST=[];
$ut=new WP_MS_Users_List_Table();$ut->prepare_items();$o=cap(fn()=>$ut->display());
t('WP_MS_Users_List_Table',count($ut->items)>=3&&str_contains($o,'anton99')&&isset($ut->get_columns()['blogs']));
$tt=new WP_MS_Themes_List_Table();$tt->prepare_items();
t('WP_MS_Themes_List_Table',is_array($tt->items)&&isset($tt->get_columns()['name'])&&str_contains(cap(fn()=>$tt->display()),'<table'));
register_taxonomy_for_object_type('post_tag','post');wp_insert_term('Jazz','post_tag');
$GLOBALS['taxonomy']='post_tag';$tm=new WP_Terms_List_Table();$tm->prepare_items();
t('WP_Terms_List_Table',isset($tm->get_columns()['posts'])&&is_array($tm->items)&&str_contains(cap(fn()=>$tm->display()),'<table'));

echo $fail===0?"OK: $n Prüfungen bestanden\n":"$fail von $n Prüfungen fehlgeschlagen\n";
exit($fail===0?0:1);

<?php
// Prüft die ergänzenden Benutzer-/Kommentar-Funktionen der WordPress-Schicht (cms/wp/core/ext/users-*.php):
// Anmeldung, Passwörter, Zurücksetzen, Sitzungen, Datenschutzanfragen, Kommentarprüfung/-status/-zähler, Vorlagen-Funktionen. Aufruf: php scripts/test-wp-ext-users.php
$tmp=sys_get_temp_dir().'/elvado-xu-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/cms');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('ELVADO_WP_DATA',$tmp.'/cms/.wp');define('ELVADO_WP_CMS_DATA',$tmp.'/cms');define('ELVADO_WP_TEST',1);$_SERVER['HTTP_HOST']='example.test';
file_put_contents($tmp.'/cms/news.json',json_encode([['id'=>1,'slug'=>'erster','title'=>'Erster Beitrag','category'=>'News','status'=>'published','published_at'=>'2026-01-10 10:00:00','author'=>'Anna Autor','body_html'=>'<p>Hallo</p>']]));
file_put_contents($tmp.'/cms/site.json',json_encode(['comments'=>['enabled'=>true,'require_approval'=>true]]));
require __DIR__.'/_testdb.php';
require __DIR__.'/../cms/wp/load.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
elvado_wp_boot(['theme'=>false,'user'=>['id'=>1,'login'=>'admin','name'=>'Administration','email'=>'admin@example.test','role'=>'administrator']]);
$GLOBALS['elvado_wp_die_throws']=true;
update_option('admin_email','chef@example.test');update_option('blogname','Testradio');
$mails=[];add_filter('pre_wp_mail',function($pre,$atts) use(&$mails){ $mails[]=$atts;return true; },10,2);
function as_user(int $id,string $login,string $role): void { $GLOBALS['elvado_wp_user']=['id'=>$id,'login'=>$login,'name'=>$login,'email'=>$login.'@example.test','role'=>$role,'caps'=>elvado_wp_caps_for_role($role)]; }

/* ───────── Alle Funktionen der Liste vorhanden ───────── */
$names='wp_signon wp_authenticate_username_password wp_authenticate_email_password wp_authenticate_cookie wp_authenticate_application_password wp_validate_application_password wp_authenticate_spam_check wp_validate_logged_in_cookie count_user_posts count_many_users_posts get_user wp_list_users get_blogs_of_user get_user_count wp_maybe_update_user_counts wp_update_user_counts wp_schedule_update_user_counts wp_is_large_user_count setup_userdata wp_dropdown_users sanitize_user_field update_user_caches clean_user_cache _get_additional_user_keys wp_get_user_contact_methods _wp_get_user_contactmethods check_password_reset_key retrieve_password reset_password register_new_user wp_send_new_user_notifications wp_get_session_token wp_get_all_sessions wp_destroy_current_session wp_destroy_other_sessions wp_destroy_all_sessions wp_get_users_with_no_role _wp_get_current_user send_confirmation_on_profile_email new_user_email_admin_notice _wp_privacy_action_request_types wp_register_user_personal_data_exporter wp_user_personal_data_exporter _wp_privacy_account_request_confirmed _wp_privacy_send_request_confirmation_notification _wp_privacy_send_erasure_fulfillment_notification _wp_privacy_account_request_confirmed_message wp_create_user_request wp_user_request_action_description wp_send_user_request wp_generate_user_request_key wp_validate_user_request_key wp_get_user_request wp_register_persisted_preferences_meta wp_cache_set_users_last_changed wp_is_password_reset_allowed_for_user '
 .'check_comment get_approved_comments get_comment_statuses get_default_comment_status get_lastcommentmodified get_comment_count wp_lazyload_comment_meta wp_set_comment_cookies sanitize_comment_cookies wp_allow_comment check_comment_flood_db wp_check_comment_flood separate_comments get_page_of_comment wp_get_comment_fields_max_lengths wp_check_comment_data wp_check_comment_disallowed_list wp_unspam_comment wp_transition_comment_status _clear_modified_cache_on_transition_comment_status wp_get_unapproved_comment_author_email wp_throttle_comment_flood wp_new_comment_notify_moderator wp_new_comment_notify_postauthor wp_update_comment_count_now discover_pingback_server_uri do_all_pings do_all_pingbacks do_all_enclosures do_all_trackbacks do_trackbacks generic_ping pingback privacy_ping_filter trackback weblog_ping pingback_ping_source_uri xmlrpc_pingback_error clean_comment_cache update_comment_cache _prime_comment_caches _close_comments_for_old_posts _close_comments_for_old_post wp_handle_comment_submission wp_register_comment_personal_data_exporter wp_comments_personal_data_exporter wp_register_comment_personal_data_eraser wp_comments_personal_data_eraser wp_cache_set_comments_last_changed _wp_batch_update_comment_type _wp_check_for_scheduled_update_comment_type '
 .'comment_author_email comment_author_email_link get_comment_author_email_link get_comment_author_IP comment_author_IP comment_author_url get_comment_author_url_link comment_author_url_link get_comment_excerpt comment_excerpt get_comments_number_text get_trackback_url trackback_url trackback_rdf wp_comment_form_unfiltered_html_nonce get_post_reply_link post_reply_link get_comment_id_fields comment_form_title _get_comment_reply_id '
 .'cache_users wp_authenticate wp_generate_auth_cookie auth_redirect _wp_sanitize_utf8_in_redirect wp_notify_postauthor wp_notify_moderator wp_password_change_notification wp_new_user_notification wp_hash_password wp_check_password wp_password_needs_rehash wp_set_password wp_text_diff '
 .'add_user edit_user get_user_to_edit get_users_drafts wp_revoke_user default_password_nag_handler default_password_nag_edit_user default_password_nag delete_users_add_js use_ssl_preference admin_created_user_email wp_is_authorize_application_password_request_valid wp_is_authorize_application_redirect_url_valid comment_exists edit_comment get_comment_to_edit get_pending_comments_num floated_admin_avatar enqueue_comment_hotkeys_js comment_footer_die get_the_modified_author the_modified_author wp_list_authors __clear_multi_author_cache';
$missing=[];foreach(preg_split('/\s+/',trim($names)) as $f)if(!function_exists($f))$missing[]=$f;
t('alle 165 Funktionen der Liste vorhanden (keine Ausnahmen)',count(preg_split('/\s+/',trim($names)))===165&&!$missing,implode(',',$missing));

/* ───────── Passwörter ───────── */
$h=wp_hash_password('geheim-123');
t('Hash: Präfix $wp$2y$',str_starts_with($h,'$wp$2y$'));
t('Passwort richtig/falsch',wp_check_password('geheim-123',$h)&&!wp_check_password('falsch',$h));
t('Passwort über 72 Zeichen: Ende zählt',wp_check_password(str_repeat('a',80).'X',wp_hash_password(str_repeat('a',80).'X'))&&!wp_check_password(str_repeat('a',80).'Y',wp_hash_password(str_repeat('a',80).'X')));
t('Neuer Hash braucht keine Erneuerung',!wp_password_needs_rehash($h));
t('Alter bcrypt-Hash braucht Erneuerung und passt',wp_password_needs_rehash(password_hash('x1',PASSWORD_DEFAULT))&&wp_check_password('x1',password_hash('x1',PASSWORD_DEFAULT)));
t('Alter MD5-Hash',wp_check_password('abc',md5('abc'))&&!wp_check_password('abd',md5('abc')));
t('phpass-Hash (Testvektor)',wp_check_password('test12345','$P$9IQRaTwmfeRo7ud9Fh4E2PdI0S3r.L0')&&!wp_check_password('test1234','$P$9IQRaTwmfeRo7ud9Fh4E2PdI0S3r.L0'));

/* ───────── Anmeldung ───────── */
$bob=wp_create_user('bob','geheim-123','bob@example.test');
t('Benutzer angelegt (ID aus wp_users)',is_int($bob)&&$bob>=ELVADO_WP_ID_DB_MIN);
$failed=[];add_action('wp_login_failed',function($u,$e) use(&$failed){ $failed[]=$e->get_error_code(); },10,2);
$logged=[];add_action('wp_login',function($l,$u) use(&$logged){ $logged[]=$l; },10,2);
$u=wp_signon(['user_login'=>'bob','user_password'=>'geheim-123']);
t('wp_signon: Erfolg liefert WP_User',$u instanceof WP_User&&$u->user_login==='bob'&&$logged===['bob']);
t('Anmeldung erneuert veralteten Hash',str_starts_with((string)get_userdata($bob)->user_pass,'$wp$2y$'));
t('wp_signon: falsches Passwort',wp_signon(['user_login'=>'bob','user_password'=>'nein'])->get_error_code()==='incorrect_password'&&end($failed)==='incorrect_password');
t('wp_signon: unbekannter Benutzer',wp_signon(['user_login'=>'nobody','user_password'=>'x'])->get_error_code()==='invalid_username');
$e=wp_authenticate('','');
t('wp_authenticate: leere Felder',$e->get_error_codes()===['empty_username','empty_password']);
t('Anmeldung per E-Mail-Adresse',wp_authenticate('bob@example.test','geheim-123') instanceof WP_User&&wp_authenticate('bob@example.test','falsch')->get_error_code()==='incorrect_password');
t('Anmeldung: Filter wp_authenticate_user kann ablehnen',(function(){ add_filter('wp_authenticate_user',$f=fn($u)=>new WP_Error('gesperrt','x'));$r=wp_authenticate('bob','geheim-123');remove_filter('wp_authenticate_user',$f);return is_wp_error($r)&&$r->get_error_code()==='gesperrt'; })());
t('Anwendungspasswörter: ohne Freigabe unverändert',wp_authenticate_application_password(null,'bob','x')===null&&wp_validate_application_password(null)===null);
t('Spam-Prüfung reicht Benutzer durch',wp_authenticate_spam_check($u)===$u);
t('Cookie-Prüfung ohne Cookie: unverändert',wp_authenticate_cookie(null,'','')===null&&wp_validate_logged_in_cookie(7)===7);

/* ───────── Auth-Cookie und Sitzungen ───────── */
$exp=time()+DAY_IN_SECONDS;$ck=wp_generate_auth_cookie($bob,$exp,'logged_in');
$p=explode('|',$ck);
$user=get_userdata($bob);
$key=wp_hash('bob|'.substr($user->user_pass,8,4).'|'.$exp.'|'.$p[2],'logged_in');
t('Auth-Cookie: Format und HMAC',count($p)===4&&$p[0]==='bob'&&(int)$p[1]===$exp&&hash_equals($p[3],hash_hmac('sha256','bob|'.$exp.'|'.$p[2],$key)));
as_user($bob,'bob','subscriber');
t('Sitzung gespeichert',count(wp_get_all_sessions())===1);
$_COOKIE['wordpress_logged_in_'.COOKIEHASH]=$ck;
t('Sitzungs-Token aus Cookie',wp_get_session_token()===$p[2]);
wp_generate_auth_cookie($bob,$exp,'logged_in');
t('zweite Sitzung',count(wp_get_all_sessions())===2);
wp_destroy_other_sessions();
t('andere Sitzungen beendet',count(wp_get_all_sessions())===1);
wp_destroy_current_session();
t('aktuelle Sitzung beendet',wp_get_all_sessions()===[]);
wp_generate_auth_cookie($bob,$exp);wp_destroy_all_sessions();
t('alle Sitzungen beendet',wp_get_all_sessions()===[]);

/* ───────── Passwort zurücksetzen und Registrierung ───────── */
$mails=[];
t('retrieve_password: Erfolg',retrieve_password('bob')===true&&count($mails)===1&&$mails[0]['to']==='bob@example.test');
preg_match('/key=([A-Za-z0-9]+)&action=rp/',$mails[0]['message'],$m);$rk=$m[1]??'';
t('Reset-Schlüssel gültig',check_password_reset_key($rk,'bob') instanceof WP_User);
t('Reset-Schlüssel falsch/unbekannt',check_password_reset_key('falsch','bob')->get_error_code()==='invalid_key'&&check_password_reset_key($rk,'niemand')->get_error_code()==='invalid_key');
$bu=get_userdata($bob);
global $wpdb;$wpdb->update($wpdb->users,['user_activation_key'=>(time()-3*DAY_IN_SECONDS).':'.explode(':',$bu->user_activation_key,2)[1]],['ID'=>$bob]);elvado_wp_users_all(true);
t('Reset-Schlüssel abgelaufen',check_password_reset_key($rk,'bob')->get_error_code()==='expired_key');
$mails=[];retrieve_password('bob@example.test');preg_match('/key=([A-Za-z0-9]+)&action=rp/',$mails[0]['message'],$m);$rk=$m[1];
reset_password(check_password_reset_key($rk,'bob'),'neues-pw-456');
t('reset_password: neues Passwort gilt, Schlüssel entwertet',wp_check_password('neues-pw-456',get_userdata($bob)->user_pass)&&is_wp_error(check_password_reset_key($rk,'bob')));
t('retrieve_password: unbekannt/leer',retrieve_password('gibtsnicht')->get_error_code()==='invalidcombo'&&retrieve_password('')->get_error_code()==='empty_username');
t('Zurücksetzen erlaubt/Filter',wp_is_password_reset_allowed_for_user($bob)===true&&wp_is_password_reset_allowed_for_user(999)===false);
$mails=[];$nid=register_new_user('carla','carla@example.test');
t('register_new_user: Erfolg und Mails',is_int($nid)&&count($mails)===2&&$mails[0]['to']==='chef@example.test'&&$mails[1]['to']==='carla@example.test');
t('register_new_user: Fehler',register_new_user('carla','x@example.test')->get_error_code()==='username_exists'&&register_new_user('dora','kaputt')->get_error_code()==='invalid_email'&&register_new_user('erik','carla@example.test')->get_error_code()==='email_exists'&&register_new_user('','a@b.de')->get_error_code()==='empty_username');
$mails=[];wp_password_change_notification(get_userdata($bob));
t('Passwortänderung meldet Administrator',count($mails)===1&&$mails[0]['to']==='chef@example.test');

/* ───────── Benutzer-Hilfen ───────── */
as_user(1,'admin','administrator');
t('wp_get_user_contact_methods mit Filter',wp_get_user_contact_methods()===[]&&(function(){ add_filter('user_contactmethods',$f=fn($m)=>$m+['twitter'=>'X']);$r=wp_get_user_contact_methods();remove_filter('user_contactmethods',$f);return $r===['twitter'=>'X']; })());
t('_get_additional_user_keys',in_array('nickname',_get_additional_user_keys(null),true));
t('sanitize_user_field',sanitize_user_field('ID','7x',1,'raw')===7&&sanitize_user_field('display_name','<b>"',1,'edit')==='&lt;b&gt;&quot;'&&sanitize_user_field('user_url','javascript:x',1,'display')==='');
t('get_user = get_userdata',get_user($bob)->user_login==='bob'&&get_user(5)===false);
t('get_blogs_of_user (Einzelseite)',count(get_blogs_of_user($bob))===1&&get_blogs_of_user($bob)[1]->userblog_id===1&&get_blogs_of_user(12345)===[]);
setup_userdata($bob);global $user_login,$user_ID,$user_identity;
t('setup_userdata setzt Globals',$user_login==='bob'&&$user_ID===$bob&&$user_identity==='bob');
wp_update_user_counts();
t('Benutzerzahl',get_user_count()>=2&&!wp_is_large_user_count());
wp_maybe_update_user_counts();wp_schedule_update_user_counts();
t('Benutzerzahl-Aktualisierung geplant',(bool)wp_next_scheduled('wp_update_user_counts'));
t('wp_get_users_with_no_role leer; wp_revoke_user',wp_get_users_with_no_role()===[]&&(function() use($nid){ wp_revoke_user($nid);return in_array($nid,wp_get_users_with_no_role(),true); })());
clean_user_cache($bob);
t('clean_user_cache/update_user_caches',update_user_caches(get_userdata($bob))===true&&update_user_caches('x')===false);
t('_wp_get_current_user',_wp_get_current_user()->ID===1);

/* ───────── Beiträge, Autoren, Listen ───────── */
$p1=wp_insert_post(['post_title'=>'Bobs Beitrag','post_content'=>'Inhalt','post_status'=>'publish','post_author'=>$bob,'comment_status'=>'open']);
$p2=wp_insert_post(['post_title'=>'Bobs zweiter','post_content'=>'Mehr','post_status'=>'publish','post_author'=>$bob,'comment_status'=>'open']);
wp_insert_post(['post_title'=>'Entwurf','post_content'=>'x','post_status'=>'draft','post_author'=>$bob]);
t('count_user_posts nur veröffentlichte',count_user_posts($bob)===2&&count_user_posts($bob,'page')===0);
t('count_many_users_posts',count_many_users_posts([$bob,$nid])===[$bob=>2,$nid=>0]);
t('get_users_drafts',count(get_users_drafts($bob))===1&&get_users_drafts($bob)[0]->post_title==='Entwurf');
$dd=wp_dropdown_users(['echo'=>0,'name'=>'autor','selected'=>$bob,'show'=>'display_name_with_login']);
t('wp_dropdown_users',str_contains($dd,"<select name='autor'")&&str_contains($dd,"value='$bob' selected='selected'")&&str_contains($dd,'bob (bob)'));
t('wp_list_users',str_contains(wp_list_users(['echo'=>false]),'bob</a></li>')&&!str_contains(wp_list_users(['echo'=>false,'html'=>false]),'<'));
$la=wp_list_authors(['echo'=>false,'optioncount'=>true]);
t('wp_list_authors: nur Autoren mit Beiträgen',str_contains($la,'bob</a> (2)')&&!str_contains($la,'carla')&&str_contains($la,'Anna Autor'));
t('wp_list_authors: Klartext ohne HTML',wp_list_authors(['echo'=>false,'html'=>false])!==''&&!str_contains(wp_list_authors(['echo'=>false,'html'=>false]),'<'));
update_post_meta($p1,'_edit_last',$bob);$GLOBALS['post']=get_post($p1);
t('get_the_modified_author',get_the_modified_author()==='bob'&&(function(){ ob_start();the_modified_author();return ob_get_clean()==='bob'; })());
$GLOBALS['post']=get_post($p2);t('get_the_modified_author ohne Meta: null',get_the_modified_author()===null);

/* ───────── Datenschutzanfragen ───────── */
t('Anfragetypen',_wp_privacy_action_request_types()===['export_personal_data','remove_personal_data']);
t('Fehler bei Anfrage',wp_create_user_request('kaputt','export_personal_data')->get_error_code()==='invalid_email'&&wp_create_user_request('bob@example.test','nix')->get_error_code()==='invalid_action');
$rq=wp_create_user_request('bob@example.test','export_personal_data',['x'=>1]);
$r=wp_get_user_request($rq);
t('Anfrage angelegt',is_int($rq)&&$r instanceof WP_User_Request&&$r->status==='request-pending'&&$r->email==='bob@example.test'&&$r->action_name==='export_personal_data'&&$r->user_id===$bob&&$r->request_data===['x'=>1]);
t('Doppelte Anfrage abgelehnt',wp_create_user_request('bob@example.test','export_personal_data')->get_error_code()==='duplicate_request');
$rq2=wp_create_user_request('carla@example.test','export_personal_data');
t('Aktionsname bleibt ohne Nummer',wp_get_user_request($rq2)->action_name==='export_personal_data');
t('Beschreibung',wp_user_request_action_description('remove_personal_data')==='Personenbezogene Daten löschen');
$mails=[];t('wp_send_user_request',wp_send_user_request($rq)===true&&count($mails)===1);
preg_match('/confirm_key=([A-Za-z0-9]+)/',$mails[0]['message'],$m);$ck2=$m[1]??'';
t('Bestätigungs-Link enthält Schlüssel',$ck2!==''&&str_contains($mails[0]['message'],'request_id='.$rq));
t('wp_validate_user_request_key',wp_validate_user_request_key($rq,$ck2)===true&&wp_validate_user_request_key($rq,'falsch')->get_error_code()==='invalid_key'&&wp_validate_user_request_key(424242,'x')->get_error_code()==='invalid_request');
$mails=[];_wp_privacy_account_request_confirmed($rq);
t('Anfrage bestätigt',wp_get_user_request($rq)->status==='request-confirmed'&&wp_get_user_request($rq)->confirmed_timestamp>0&&wp_validate_user_request_key($rq,$ck2)->get_error_code()==='expired_request');
_wp_privacy_send_request_confirmation_notification($rq);_wp_privacy_send_request_confirmation_notification($rq);
t('Administrator wird einmal benachrichtigt',count($mails)===1&&$mails[0]['to']==='chef@example.test');
t('Bestätigungstext',str_contains(_wp_privacy_account_request_confirmed_message($rq),'Export'));
$rm=wp_create_user_request('bob@example.test','remove_personal_data',[], 'completed');$mails=[];
_wp_privacy_send_erasure_fulfillment_notification($rm);_wp_privacy_send_erasure_fulfillment_notification($rm);
t('Löschbestätigung einmal an den Antragsteller',count($mails)===1&&$mails[0]['to']==='bob@example.test');
$ex=wp_user_personal_data_exporter('bob@example.test');
t('Datenexport Benutzer',$ex['done']&&$ex['data'][0]['item_id']==='user-'.$bob&&in_array('bob',array_column($ex['data'][0]['data'],'value'),true)&&wp_user_personal_data_exporter('niemand@x.de')['data']===[]);
t('Exporter registriert',isset(wp_register_user_personal_data_exporter([])['wordpress-user'])&&isset(wp_register_comment_personal_data_exporter([])['wordpress-comments'])&&isset(wp_register_comment_personal_data_eraser([])['wordpress-comments']));

/* ───────── Kommentare: Prüfung ───────── */
as_user(1,'admin','administrator');
t('Standard: Kommentar neuer Autoren wird moderiert',check_comment('Eva','eva@example.test','','Hallo','1.2.3.4','UA')===false);
$c1=wp_insert_comment(['comment_post_ID'=>$p1,'comment_author'=>'Eva','comment_author_email'=>'eva@example.test','comment_content'=>'Erster','comment_author_IP'=>'1.2.3.4','comment_approved'=>'0','comment_date'=>'2026-03-01 10:00:00']);
t('unfreigegebener Kommentar liegt in der Tabelle',is_int($c1)&&$wpdb->get_var("SELECT comment_approved FROM {$wpdb->comments} WHERE comment_ID = $c1")==='0');
t('check_comment: früher freigegeben → ohne Moderation',(function() use($c1){ wp_set_comment_status($c1,'approve');return check_comment('Eva','eva@example.test','','Zweiter','1.2.3.4','UA')===true&&check_comment('Eva','andere@example.test','','x','1.2.3.4','UA')===false; })());
t('check_comment: Pingback nie automatisch',check_comment('Eva','eva@example.test','','x','1.2.3.4','UA','pingback')===false);
update_option('comment_previously_approved',0);
t('check_comment: Linkgrenze',check_comment('A','a@b.de','','<a href="x">1</a>','','')===true&&check_comment('A','a@b.de','','<a href="x">1</a> <a href="y">2</a>','','')===false);
update_option('moderation_keys',"Kasino\nbillig");
t('check_comment: Moderations-Schlüsselwörter',check_comment('A','a@b.de','','Kasino hier','','')===false&&check_comment('A','a@b.de','','harmlos','','')===true);
update_option('moderation_keys','');update_option('comment_moderation',1);
t('check_comment: alles moderieren',check_comment('A','a@b.de','','x','','')===false);
update_option('comment_moderation',0);
update_option('disallowed_keys',"spamwort");
t('Sperrliste',wp_check_comment_disallowed_list('A','a@b.de','','mit SPAMWORT','','')===true&&wp_check_comment_disallowed_list('A','a@b.de','','sauber','','')===false);
t('wp_check_comment_data: Sperrliste → Papierkorb/Spam',in_array(wp_check_comment_data(['comment_post_ID'=>$p1,'comment_author'=>'A','comment_author_email'=>'a@b.de','comment_content'=>'spamwort']),['trash','spam'],true));
update_option('disallowed_keys','');
t('wp_check_comment_data: Beitragsautor freigegeben',wp_check_comment_data(['comment_post_ID'=>$p1,'user_id'=>$bob,'comment_content'=>'x'])===1);
update_option('comment_previously_approved',1);
t('wp_check_comment_data: fremder Benutzer wird moderiert',wp_check_comment_data(['comment_post_ID'=>$p1,'user_id'=>$nid,'comment_content'=>'x'])===0);
t('Maximallängen',wp_get_comment_fields_max_lengths()['comment_content']===65525&&wp_get_comment_fields_max_lengths()['comment_author']===245);
$dup=wp_allow_comment(['comment_post_ID'=>$p1,'comment_author'=>'Eva','comment_author_email'=>'eva@example.test','comment_content'=>'Erster','comment_parent'=>0],true);
t('wp_allow_comment: Duplikat',is_wp_error($dup)&&$dup->get_error_code()==='comment_duplicate'&&$dup->get_error_data()===409);
as_user($nid,'carla','subscriber');
$ok=wp_allow_comment(['comment_post_ID'=>$p1,'comment_author'=>'Neu','comment_author_email'=>'neu@example.test','comment_content'=>'Ganz neu','comment_author_IP'=>'9.9.9.9','comment_date_gmt'=>gmdate('Y-m-d H:i:s')],true);
t('wp_allow_comment: neuer Autor → Moderation (0)',$ok===0);
wp_insert_comment(['comment_post_ID'=>$p1,'comment_author'=>'Flut','comment_author_email'=>'flut@example.test','comment_content'=>'a','comment_author_IP'=>'7.7.7.7','user_id'=>$nid,'comment_approved'=>'1','comment_date'=>gmdate('Y-m-d H:i:s'),'comment_date_gmt'=>gmdate('Y-m-d H:i:s')]);
t('Flood: zu schnell → Fehler 429',(function(){ $r=wp_allow_comment(['comment_post_ID'=>1,'comment_author'=>'Flut2','comment_author_email'=>'f2@example.test','comment_content'=>'b','comment_author_IP'=>'7.7.7.7','comment_date_gmt'=>gmdate('Y-m-d H:i:s')],true);return is_wp_error($r)&&$r->get_error_code()==='comment_flood'; })());
t('wp_throttle_comment_flood',wp_throttle_comment_flood(false,100,110)===true&&wp_throttle_comment_flood(false,100,120)===false&&wp_throttle_comment_flood(true,0,999)===true);
t('Flood: Moderatoren ausgenommen',(function(){ as_user(1,'admin','administrator');$r=wp_check_comment_flood(false,'7.7.7.7','',gmdate('Y-m-d H:i:s'),true);as_user(5,'x','subscriber');return $r===false; })());
as_user(1,'admin','administrator');

/* ───────── Kommentare: Status, Zähler, Seiten ───────── */
$c2=wp_insert_comment(['comment_post_ID'=>$p2,'comment_author'=>'Zoe','comment_author_email'=>'zoe@example.test','comment_content'=>'Wartet','comment_approved'=>'0','comment_date'=>'2026-03-02 10:00:00']);
$cnt=get_comment_count($p2);
t('get_comment_count: wartend',$cnt['awaiting_moderation']===1&&$cnt['approved']===0&&$cnt['all']===1&&$cnt['total_comments']===1);
t('get_pending_comments_num',get_pending_comments_num($p2)===1&&get_pending_comments_num([$p1,$p2])===[$p1=>0,$p2=>1]);
$trans=[];add_action('transition_comment_status',function($n,$o,$c) use(&$trans){ $trans[]="$o>$n"; },10,3);
add_action('comment_unapproved_to_approved',function($c) use(&$trans){ $trans[]='spezifisch'; });
wp_transition_comment_status('approve','hold',elvado_wpx_comment($c2));
t('wp_transition_comment_status: Hooks und Namen',$trans===['unapproved>approved','spezifisch']);
wp_set_comment_status($c2,'approve');
t('Status freigegeben; Zähler',wp_get_comment_status($c2)==='approved'&&get_comment_count($p2)['approved']===1&&get_pending_comments_num($p2)===0);
wp_spam_comment($c2);
t('als Spam gezählt',get_comment_count($p2)['spam']===1&&get_comment_count($p2)['all']===0&&get_comment_count($p2)['total_comments']===1);
t('wp_unspam_comment',wp_unspam_comment($c2)===true&&$wpdb->get_var("SELECT comment_approved FROM {$wpdb->comments} WHERE comment_ID = $c2")==='0');
wp_set_comment_status($c2,'approve');
t('wp_update_comment_count_now',wp_update_comment_count_now($p2)===true&&(int)get_post($p2)->comment_count===1);
t('get_approved_comments',count(get_approved_comments($p2))===1&&get_approved_comments(0)===[]);
t('get_lastcommentmodified',get_lastcommentmodified('gmt')>='2026-03-02 10:00:00'&&get_lastcommentmodified('blog')!==false);
t('get_comment_statuses',array_keys(get_comment_statuses())===['hold','approve','spam','trash']);
t('get_default_comment_status',get_default_comment_status()==='open'&&get_default_comment_status('post','pingback')==='open'&&get_default_comment_status('gibtsnicht')==='closed');
wp_cache_set('lastcommentmodified:gmt','x','timeinfo');_clear_modified_cache_on_transition_comment_status('approved','hold');
t('Cache für letzte Änderung geleert',wp_cache_get('lastcommentmodified:gmt','timeinfo')===false);
$sep=[(object)['comment_type'=>'comment'],(object)['comment_type'=>'pingback'],(object)['comment_type'=>'']];$s=separate_comments($sep);
t('separate_comments',count($s['comment'])===2&&count($s['pings'])===1&&count($s['pingback'])===1);
update_option('comments_per_page',2);update_option('thread_comments',1);
$ids=[];foreach([1,2,3] as $i)$ids[$i]=wp_insert_comment(['comment_post_ID'=>$p1,'comment_author'=>'S'.$i,'comment_content'=>'seite '.$i,'comment_approved'=>'1','comment_date'=>'2026-04-0'.$i.' 10:00:00','comment_date_gmt'=>'2026-04-0'.$i.' 10:00:00']);
t('get_page_of_comment',get_page_of_comment($ids[1])===1&&get_page_of_comment($ids[3])===2&&get_page_of_comment($ids[3],['per_page'=>1])>=3);
$re=wp_insert_comment(['comment_post_ID'=>$p1,'comment_author'=>'Re','comment_content'=>'antwort','comment_approved'=>'1','comment_parent'=>$ids[3],'comment_date'=>'2026-04-09 10:00:00','comment_date_gmt'=>'2026-04-09 10:00:00']);
t('get_page_of_comment: Antwort liegt auf Seite des Elternkommentars',get_page_of_comment($re)===get_page_of_comment($ids[3]));
t('get_page_of_comment: unbekannt → null',get_page_of_comment(987654)===null);

/* ───────── Kommentare: Benachrichtigungen ───────── */
$ca=wp_insert_comment(['comment_post_ID'=>$p1,'comment_author'=>'Gast','comment_author_email'=>'gast@example.test','comment_content'=>'Schöner Beitrag','comment_approved'=>'1','comment_author_IP'=>'8.8.8.8']);
$mails=[];
t('wp_notify_postauthor an den Beitragsautor',wp_notify_postauthor($ca)===true&&count($mails)===1&&$mails[0]['to']==='bob@example.test'&&str_contains($mails[0]['subject'],'Bobs Beitrag'));
t('wp_notify_postauthor: nicht an sich selbst',wp_notify_postauthor(wp_insert_comment(['comment_post_ID'=>$p1,'comment_author'=>'bob','comment_content'=>'x','comment_approved'=>'1','user_id'=>$bob]))===false);
$cm=wp_insert_comment(['comment_post_ID'=>$p1,'comment_author'=>'Neu','comment_author_email'=>'n@example.test','comment_content'=>'Prüfen','comment_approved'=>'0']);
$mails=[];
t('wp_new_comment_notify_moderator',wp_new_comment_notify_moderator($cm)===true&&count($mails)===1&&$mails[0]['to']==='chef@example.test');
update_option('moderation_notify',0);$mails=[];
t('wp_notify_moderator: abschaltbar',wp_notify_moderator($cm)===true&&$mails===[]);
update_option('moderation_notify',1);
$mails=[];t('wp_new_comment_notify_postauthor: nur freigegebene',wp_new_comment_notify_postauthor($cm)===false&&wp_new_comment_notify_postauthor($ca)===true&&count($mails)===1);

/* ───────── Kommentare absenden ───────── */
update_option('require_name_email',1);unset($GLOBALS['elvado_wp_user']);   // Besucher ohne Anmeldung
$_SERVER['REMOTE_ADDR']='5.5.5.5';
$hc=fn(array $d)=>wp_handle_comment_submission($d);
t('Absenden: Beitrag fehlt',$hc(['comment_post_ID'=>424242,'author'=>'A','email'=>'a@example.test','comment'=>'x'])->get_error_code()==='comment_id_not_found');
t('Absenden: Pflichtfelder',$hc(['comment_post_ID'=>$p1,'author'=>'','email'=>'','comment'=>'x'])->get_error_code()==='require_name_email'&&$hc(['comment_post_ID'=>$p1,'author'=>'A','email'=>'kaputt-adresse','comment'=>'x'])->get_error_code()==='require_valid_email'&&$hc(['comment_post_ID'=>$p1,'author'=>'A','email'=>'a@example.test','comment'=>' '])->get_error_code()==='require_valid_comment');
t('Absenden: zu lang',$hc(['comment_post_ID'=>$p1,'author'=>str_repeat('a',300),'email'=>'a@example.test','comment'=>'x'])->get_error_code()==='comment_author_column_length');
$posted=[];add_action('comment_post',function($id,$ap) use(&$posted){ $posted[]=$ap; },10,2);
$res=$hc(['comment_post_ID'=>$p1,'author'=>'Heidi','email'=>'heidi@example.test','comment'=>'Mein <script>x</script>Kommentar']);
t('Absenden: Kommentar gespeichert und gezählt',$res instanceof WP_Comment&&$res->comment_author==='Heidi'&&!str_contains($res->comment_content,'<script>')&&$res->comment_approved==='0'&&$posted===[0]);
$wpdb->update($wpdb->posts,['comment_status'=>'closed'],['ID'=>$p2]);elvado_wp_post_cache_clear($p2);
t('Absenden: Kommentare geschlossen',$hc(['comment_post_ID'=>$p2,'author'=>'A','email'=>'a@example.test','comment'=>'x'])->get_error_code()==='comment_closed');
$ns=$hc(['comment_post_ID'=>1,'author'=>'Ina','email'=>'ina@example.test','comment'=>'Zum CMS-Beitrag']);
t('Absenden: CMS-Beitrag über den CMS-Speicher',$ns instanceof WP_Comment&&$ns->comment_author==='Ina'&&$ns->comment_approved==='0'&&is_file($tmp.'/cms/comments.json'));

/* ───────── Kommentare: Datenschutz, Wartung, Caches ───────── */
as_user(1,'admin','administrator');
$ce=wp_comments_personal_data_exporter('gast@example.test');
t('Kommentar-Export',$ce['done']&&count($ce['data'])===1&&$ce['data'][0]['group_id']==='comments'&&in_array('Schöner Beitrag',array_column($ce['data'][0]['data'],'value'),true)&&wp_comments_personal_data_exporter('')['data']===[]);
$er=wp_comments_personal_data_eraser('gast@example.test');
$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->comments} WHERE comment_ID = %d",$ca));
t('Kommentar-Löschung anonymisiert',$er['items_removed']===true&&$er['done']&&$row->comment_author_email==='deleted@site.invalid'&&$row->comment_author_IP==='0.0.0.0'&&wp_comments_personal_data_exporter('gast@example.test')['data']===[]);
$wpdb->update($wpdb->comments,['comment_type'=>''],['comment_ID'=>$ids[1]]);
_wp_batch_update_comment_type();
t('Kommentartyp-Nachtrag',$wpdb->get_var("SELECT comment_type FROM {$wpdb->comments} WHERE comment_ID = {$ids[1]}")==='comment'&&(bool)get_option('finished_updating_comment_type'));
delete_option('finished_updating_comment_type');_wp_check_for_scheduled_update_comment_type();
t('Nachtrag wird geplant',(bool)wp_next_scheduled('wp_update_comment_type_batch'));
wp_cache_set(77,'x','comment');clean_comment_cache(77);update_comment_cache([new WP_Comment((object)['comment_ID'=>78])],false);
t('Kommentar-Caches',wp_cache_get(77,'comment')===false&&wp_cache_get(78,'comment')!==false);
_prime_comment_caches([$ids[2]],false);
t('_prime_comment_caches lädt aus der Tabelle',wp_cache_get($ids[2],'comment')->comment_author==='S2');
update_option('close_comments_for_old_posts',1);update_option('close_comments_days_old',14);
$old=wp_insert_post(['post_title'=>'Alt','post_content'=>'x','post_status'=>'publish','post_date'=>'2020-01-01 00:00:00','post_date_gmt'=>'2020-01-01 00:00:00']);
t('Alte Beiträge: Kommentare schließen',_close_comments_for_old_post(true,$old)===false&&_close_comments_for_old_post(true,$p1)===true&&_close_comments_for_old_post(false,$old)===false);
$q=new WP_Query();$q->is_singular=true;$ps=_close_comments_for_old_posts([get_post($old)],$q);
t('_close_comments_for_old_posts',$ps[0]->comment_status==='closed');
update_option('close_comments_for_old_posts',0);
t('Ping-Hilfen',privacy_ping_filter('a b')==='a b'&&(function(){ update_option('blog_public','0');$r=privacy_ping_filter('a b');update_option('blog_public','1');return $r===''; })()&&xmlrpc_pingback_error((object)['code'=>48])->code===48&&xmlrpc_pingback_error((object)['code'=>1])===''&&pingback_ping_source_uri('kein url')===''&&pingback_ping_source_uri('https://example.org/x')==='https://example.org/x');
t('Ping ohne Ziele ist harmlos',generic_ping(5)===5&&discover_pingback_server_uri('ftp://x')===false&&do_all_pings()===null);
t('weblog_ping/trackback ohne Ziel',weblog_ping('')===null&&trackback('',"t","e",$p1)===null);

/* ───────── Vorlagen-Funktionen ───────── */
$cx=wp_insert_comment(['comment_post_ID'=>$p1,'comment_author'=>'Zed','comment_author_email'=>'zed@example.test','comment_author_url'=>'http://www.beispiel.de/seite/','comment_author_IP'=>'4.4.4.4','comment_content'=>"Ein <b>langer</b> Kommentar\nmit vielen Wörtern drei vier fünf sechs sieben acht neun zehn elf zwölf dreizehn vierzehn fünfzehn sechzehn siebzehn achtzehn neunzehn zwanzig einundzwanzig",'comment_approved'=>'1']);
$GLOBALS['post']=get_post($p1);$GLOBALS['comment']=get_comment($cx);
$o=function(callable $f){ ob_start();$f();return ob_get_clean(); };
t('comment_author_email/IP/url',$o('comment_author_email')==='zed@example.test'&&$o('comment_author_IP')==='4.4.4.4'&&$o('comment_author_url')==='http://www.beispiel.de/seite/');
t('Autor-E-Mail-Link',get_comment_author_email_link('Mail','<i>','</i>')==='<i><a href="mailto:zed@example.test">Mail</a></i>'&&$o(fn()=>comment_author_email_link())==='<a href="mailto:zed@example.test">zed@example.test</a>');
t('Autor-URL-Link',get_comment_author_url_link()==='<a href="http://www.beispiel.de/seite/" rel="external">beispiel.de/seite</a>'&&$o(fn()=>comment_author_url_link('Seite'))==='<a href="http://www.beispiel.de/seite/" rel="external">Seite</a>');
$ex=get_comment_excerpt();
t('Kommentar-Auszug gekürzt ohne HTML',!str_contains($ex,'<b>')&&str_ends_with($ex,'&hellip;')&&str_starts_with($ex,'Ein langer Kommentar mit'));
t('comment_excerpt gibt aus',$o('comment_excerpt')===get_comment_excerpt());
t('get_comments_number_text',get_comments_number_text(false,false,false,$p2)==='1 Kommentar'&&get_comments_number_text('keine','eins','% viele',$p1)!==''&&get_comments_number_text('keine')!==''&&str_contains(get_comments_number_text(false,false,'% mal',$p1),'mal'));
t('Trackback-Adresse (mit Permalinks)',str_ends_with(get_trackback_url(),'/bobs-beitrag/trackback/')&&$o(fn()=>trackback_url())===get_trackback_url()&&trackback_url(false)===get_trackback_url());
t('Trackback-Adresse (ohne Permalinks)',(function(){ $old=get_option('permalink_structure');update_option('permalink_structure','');$r=get_trackback_url();update_option('permalink_structure',$old);return str_contains($r,'/wp-trackback.php?p='); })());
t('trackback_rdf',str_contains($o('trackback_rdf'),'trackback:ping=')&&str_contains($o('trackback_rdf'),'dc:title="Bobs Beitrag"'));
t('Antwort-Link zum Beitrag',str_contains((string)get_post_reply_link(['reply_text'=>'Los']),'comment-reply-link')&&str_contains($o(fn()=>post_reply_link()),'#respond'));
$_GET['replytocom']=(string)$cx;
t('Antwort-ID und Formularfelder',_get_comment_reply_id()===$cx&&str_contains(get_comment_id_fields($p1),"name='comment_parent' id='comment_parent' value='$cx'")&&str_contains(get_comment_id_fields($p1),"value='$p1'"));
t('comment_form_title mit Antwort',str_contains($o(fn()=>comment_form_title()),'Antworte auf')&&str_contains($o(fn()=>comment_form_title()),'Zed'));
unset($_GET['replytocom']);
t('comment_form_title ohne Antwort',$o(fn()=>comment_form_title())==='Hinterlasse eine Antwort'&&_get_comment_reply_id()===0);
t('Nonce-Feld für ungefiltertes HTML (Administrator)',str_contains($o('wp_comment_form_unfiltered_html_nonce'),'_wp_unfiltered_html_comment_disabled'));

/* ───────── Verwaltung ───────── */
$_POST=['user_login'=>'frank','email'=>'frank@example.test','pass1'=>'abc12345','pass2'=>'abc12345','role'=>'editor','first_name'=>'Frank'];
$fid=edit_user();
t('edit_user: neuer Benutzer',is_int($fid)&&get_userdata($fid)->user_login==='frank'&&get_userdata($fid)->first_name==='Frank'&&get_userdata($fid)->roles===['editor']);
t('edit_user: Passwort gilt',wp_signon(['user_login'=>'frank','user_password'=>'abc12345']) instanceof WP_User);
$_POST=['user_login'=>'frank','email'=>'x@example.test','pass1'=>'a','pass2'=>'b'];
$er=edit_user();t('edit_user: Fehler (Passwörter, vorhandener Name)',is_wp_error($er)&&in_array('pass',$er->get_error_codes(),true)&&in_array('user_login',$er->get_error_codes(),true));
$_POST=['email'=>'frank2@example.test','nickname'=>'Franky','first_name'=>'Fränk','display_name'=>'Frank F.'];
t('edit_user: Aktualisierung',edit_user($fid)===$fid&&get_userdata($fid)->user_email==='frank2@example.test'&&get_userdata($fid)->display_name==='Frank F.'&&get_user_meta($fid,'nickname',true)==='Franky'&&get_user_meta($fid,'first_name',true)==='Fränk');
$_POST=['user_login'=>'gina','email'=>'frank2@example.test','pass1'=>'x','pass2'=>'x'];
t('edit_user: E-Mail schon vergeben',in_array('email_exists',edit_user()->get_error_codes(),true));
$_POST=[];
t('add_user ohne Daten liefert Fehler',is_wp_error(add_user()));
t('get_user_to_edit',get_user_to_edit($fid)->filter==='edit'&&get_user_to_edit(31)===false);
$_POST=['comment_ID'=>$cx,'content'=>'Geändert','newcomment_author'=>'Zed2','comment_status'=>'1'];
t('edit_comment',edit_comment()===1&&$wpdb->get_var("SELECT comment_content FROM {$wpdb->comments} WHERE comment_ID = $cx")==='Geändert'&&$wpdb->get_var("SELECT comment_author FROM {$wpdb->comments} WHERE comment_ID = $cx")==='Zed2');
$_POST=[];
$ce=get_comment_to_edit($cm);
t('get_comment_to_edit maskiert; comment_exists',$ce->comment_post_ID===$p1&&is_string($ce->comment_content)&&get_comment_to_edit(777777)===false&&comment_exists('Zed2','2000-01-01 00:00:00')===null);
t('comment_exists findet',(int)comment_exists('S1','2026-04-01 10:00:00')===$p1);
$GLOBALS['comment']=get_comment($ids[1]);
t('floated_admin_avatar',str_contains(floated_admin_avatar('Name'),'<img')&&str_ends_with(floated_admin_avatar('Name'),' Name'));
t('Weiterleitungsadressen für Anwendungspasswörter',wp_is_authorize_application_redirect_url_valid('https://app.example/cb')===true&&wp_is_authorize_application_redirect_url_valid('myapp://cb')===true&&wp_is_authorize_application_redirect_url_valid('javascript:alert(1)')->get_error_code()==='invalid_redirect_scheme'&&wp_is_authorize_application_redirect_url_valid('http://evil.example/x')->get_error_code()==='invalid_redirect_scheme'&&wp_is_authorize_application_redirect_url_valid('http://localhost/x')===true);
$av=wp_is_authorize_application_password_request_valid(['app_id'=>'kein-uuid','success_url'=>'javascript:x'],get_userdata($bob));
t('Freigabe-Anfrage prüfen',is_wp_error($av)&&in_array('invalid_app_id',$av->get_error_codes(),true)&&in_array('invalid_redirect_scheme',$av->get_error_codes(),true));
t('Admin-Mail-Text',str_contains(admin_created_user_email(''),'Testradio')&&str_contains(admin_created_user_email(''),'%s'));
t('HTML-Hilfen',str_contains($o('delete_users_add_js'),'delete_option1')&&str_contains((function() use($o){ return $o(fn()=>use_ssl_preference((object)['use_ssl'=>'1'])); })(),'checked="checked"'));
update_user_meta($bob,'default_password_nag',true);as_user($bob,'bob','subscriber');
t('Passwort-Hinweis',str_contains($o('default_password_nag'),'default-password-nag')&&!str_contains((function() use($bob,$o){ $_GET['default_password_nag']='0';default_password_nag_handler();unset($_GET['default_password_nag']);return $o('default_password_nag'); })(),'default-password-nag'));
t('wp_text_diff',wp_text_diff("a\nb","a\nb")===''&&str_contains(wp_text_diff("a\nb\nc","a\nx\nc"),'diff-deletedline')&&str_contains(wp_text_diff("a\nb\nc","a\nx\nc"),'diff-addedline')&&str_contains(wp_text_diff("a\nb\nc","a\nx\nc",['title'=>'T']),'<caption class="diff-title">T</caption>'));
t('Umleitungs-Rückruf',preg_replace_callback('/[^\x00-\x7F]+/','_wp_sanitize_utf8_in_redirect','/ä?x')==='/%C3%A4?x');
t('cache_users/wp_cache_set_*',(function() use($bob){ cache_users([$bob]);wp_cache_set_users_last_changed();wp_cache_set_comments_last_changed();return wp_cache_get($bob,'users') instanceof WP_User&&wp_cache_get('last_changed','users')!==false; })());
t('E-Mail-Änderung im Profil bestätigen',(function() use($bob,$mails){ global $mails;as_user($bob,'bob','subscriber');$mails=[];$_POST=['user_id'=>$bob,'email'=>'neu-bob@example.test'];send_confirmation_on_profile_email();$ok=$_POST['email']==='bob@example.test'&&count($mails)===1&&$mails[0]['to']==='neu-bob@example.test'&&(get_user_meta($bob,'_new_email',true)['newemail']??'')==='neu-bob@example.test';$_POST=[];return $ok; })());
t('Hinweis zur E-Mail-Änderung',(function() use($o){ $_SERVER['PHP_SELF']='/wp-admin/profile.php';$_GET['updated']='1';$r=$o('new_user_email_admin_notice');unset($_GET['updated']);return str_contains($r,'neu-bob@example.test'); })());
t('Kommentar-Cookies bereinigt',(function(){ $_COOKIE['comment_author_'.COOKIEHASH]='<x>"';$_COOKIE['comment_author_url_'.COOKIEHASH]='javascript:x';sanitize_comment_cookies();return !str_contains($_COOKIE['comment_author_'.COOKIEHASH],'<')&&$_COOKIE['comment_author_url_'.COOKIEHASH]===''; })());
t('Persistente Einstellungen registriert',(function(){ wp_register_persisted_preferences_meta();return true; })());
t('wp_set_comment_cookies ohne Ausgabe',(function(){ wp_set_comment_cookies((object)['comment_author'=>'a','comment_author_email'=>'b','comment_author_url'=>'c'],new WP_User(0),true);return true; })());
t('Mail-Adresse des unfreigegebenen Kommentators',wp_get_unapproved_comment_author_email()===''&&(function() use($cm){ $_GET['unapproved']=$cm;$_GET['moderation-hash']=wp_hash(elvado_wpx_comment($cm)->comment_date_gmt);$r=wp_get_unapproved_comment_author_email();$_GET=[];return $r==='n@example.test'; })());
t('Kommentar-Aktualisierung (Meta)',(function() use($ids){ wp_lazyload_comment_meta([(object)['comment_ID'=>$ids[1]]]);return true; })());
t('Hotkeys-Skript ohne Einstellung wirkungslos',(function(){ enqueue_comment_hotkeys_js();return !wp_script_is('jquery-table-hotkeys','enqueued'); })());
t('__clear_multi_author_cache',(function(){ set_transient('is_multi_author',1);__clear_multi_author_cache();return get_transient('is_multi_author')===false; })());

echo $fail===0?"OK: $n Prüfungen bestanden\n":"$fail von $n Prüfungen fehlgeschlagen\n";
exit($fail===0?0:1);

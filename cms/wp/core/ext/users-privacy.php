<?php
// Ergänzung (Benutzer): Datenschutz-Anfragen (Beitragstyp user_request), Export der Benutzerdaten und zugehörige Benachrichtigungen.
// Anfragen liegen in wp_posts (DB-Inhalte); der Beitragstyp wird erst bei Bedarf benutzt, nicht beim Laden registriert.

if(!class_exists('WP_User_Request')){
    #[AllowDynamicProperties]
    final class WP_User_Request {
        public $ID=0;public $user_id=0;public $email='';public $action_name='';public $status='';
        public $created_timestamp=0;public $modified_timestamp=0;public $confirmed_timestamp=0;public $completed_timestamp=0;public $request_data=[];public $confirm_key='';
        public function __construct($post) {
            $this->ID=(int)$post->ID;$this->user_id=(int)$post->post_author;$this->email=(string)$post->post_title;$this->action_name=(string)$post->post_name;$this->status=(string)$post->post_status;
            $this->created_timestamp=(int)strtotime($post->post_date_gmt.' UTC');$this->modified_timestamp=(int)strtotime($post->post_modified_gmt.' UTC');
            $this->confirmed_timestamp=(int)get_post_meta($this->ID,'_wp_user_request_confirmed_timestamp',true);$this->completed_timestamp=(int)get_post_meta($this->ID,'_wp_user_request_completed_timestamp',true);
            $d=json_decode((string)$post->post_content,true);$this->request_data=is_array($d)?$d:[];$this->confirm_key=(string)$post->post_password;
        }
    }
}
/** Status einer Anfrage direkt setzen (ohne Beitrags-Hooks und ohne Slug-Änderung). */
function rrw_wpx_request_status(int $id, string $status, array $extra=[]): void {
    global $wpdb;$now=current_time('mysql');$gmt=current_time('mysql',1);
    $wpdb->update($wpdb->posts,array_merge(['post_status'=>$status,'post_modified'=>$now,'post_modified_gmt'=>$gmt],$extra),['ID'=>$id]);rrw_wp_post_cache_clear($id);
}

if(!function_exists('_wp_privacy_action_request_types')){
    function _wp_privacy_action_request_types() { return ['export_personal_data','remove_personal_data']; }
}
if(!function_exists('wp_user_request_action_description')){
    function wp_user_request_action_description($action_name) {
        $d=match($action_name){'export_personal_data'=>'Personenbezogene Daten exportieren','remove_personal_data'=>'Personenbezogene Daten löschen',default=>$action_name};
        return apply_filters('user_request_action_description',$d,$action_name);
    }
}
if(!function_exists('wp_get_user_request')){
    function wp_get_user_request($request_id) {
        $id=absint($request_id);$post=$id?get_post($id):null;
        return $post&&$post->post_type==='user_request'?new WP_User_Request($post):false;
    }
}
if(!function_exists('wp_create_user_request')){
    function wp_create_user_request($email_address='', $action_name='', $request_data=[], $status='pending') {
        global $wpdb;
        $email=sanitize_email((string)$email_address);$action=sanitize_key((string)$action_name);
        if(!is_email($email))return new WP_Error('invalid_email','Ungültige E-Mail-Adresse.');
        if(!in_array($action,_wp_privacy_action_request_types(),true))return new WP_Error('invalid_action','Ungültige Aktion der Datenschutzanfrage.');
        if(!rrw_wp_db_ready())return new WP_Error('no_database','Keine Datenbank verfügbar.');
        $user=get_user_by('email',$email);$uid=$user?(int)$user->ID:0;
        $dup=$wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'user_request' AND post_name = %s AND post_title = %s AND post_status IN ('request-pending','request-confirmed') LIMIT 1",$action,$email));
        if($dup)return new WP_Error('duplicate_request','Für diese E-Mail-Adresse gibt es bereits eine ausstehende Anfrage.');
        $id=wp_insert_post(['post_author'=>$uid,'post_name'=>$action,'post_title'=>$email,'post_content'=>wp_json_encode($request_data),'post_status'=>'request-'.sanitize_key((string)$status),'post_type'=>'user_request','post_date'=>current_time('mysql',false),'post_date_gmt'=>current_time('mysql',true)],true);
        if(is_wp_error($id)||!$id)return is_wp_error($id)?$id:new WP_Error('db_insert_error','Die Anfrage konnte nicht gespeichert werden.');
        $wpdb->update($wpdb->posts,['post_name'=>$action],['ID'=>(int)$id]);rrw_wp_post_cache_clear((int)$id);   // Slug nicht „-2“ nummerieren
        return (int)$id;
    }
}
if(!function_exists('wp_generate_user_request_key')){
    function wp_generate_user_request_key($request_id) {
        $key=wp_generate_password(20,false);
        if(rrw_wp_db_ready())rrw_wpx_request_status((int)$request_id,'request-pending',['post_password'=>rrw_wpx_fast_hash($key)]);
        return $key;
    }
}
if(!function_exists('wp_validate_user_request_key')){
    function wp_validate_user_request_key($request_id, $key) {
        $r=wp_get_user_request($request_id);
        if(!$r)return new WP_Error('invalid_request','Ungültige Anfrage.');
        if(!in_array($r->status,['request-pending','request-failed'],true))return new WP_Error('expired_request','<strong>Fehler:</strong> Dieser Link ist abgelaufen.');
        if(empty($key)||$r->confirm_key===''||!$r->modified_timestamp||!rrw_wpx_fast_verify((string)$key,$r->confirm_key))return new WP_Error('invalid_key','<strong>Fehler:</strong> Ungültiger Schlüssel.');
        if(time()>$r->modified_timestamp+(int)apply_filters('user_request_key_expiration',DAY_IN_SECONDS))return new WP_Error('expired_key','<strong>Fehler:</strong> Dieser Link ist abgelaufen.');
        return true;
    }
}
if(!function_exists('wp_send_user_request')){
    /** Bestätigungs-E-Mail an den Antragsteller (Link mit action=confirmaction, request_id und confirm_key). */
    function wp_send_user_request($request_id) {
        $r=wp_get_user_request($request_id);if(!$r)return new WP_Error('invalid_request','Ungültige Anfrage.');
        $key=wp_generate_user_request_key($r->ID);$site=rrw_wpx_site_name();
        $url=add_query_arg(['action'=>'confirmaction','request_id'=>$r->ID,'confirm_key'=>$key],wp_login_url());
        $desc=wp_user_request_action_description($r->action_name);
        $m=apply_filters('user_request_action_email_content',"Hallo,\n\nFür deine E-Mail-Adresse wurde auf $site eine Anfrage gestellt: $desc.\n\nZum Bestätigen öffne diese Adresse:\n$url\n\nWenn du die Anfrage nicht gestellt hast, ignoriere diese E-Mail.\n",['request'=>$r,'email'=>$r->email,'description'=>$desc,'confirm_url'=>$url,'sitename'=>$site]);
        $ok=wp_mail($r->email,'['.$site.'] Bestätigung: '.$desc,(string)$m);
        if(!$ok)return new WP_Error('privacy_email_error','Beim Senden der E-Mail ist ein Fehler aufgetreten.');
        if(rrw_wp_db_ready())update_post_meta($r->ID,'_wp_user_request_last_notified',time());
        return true;
    }
}
if(!function_exists('_wp_privacy_account_request_confirmed')){
    function _wp_privacy_account_request_confirmed($request_id) {
        $r=wp_get_user_request($request_id);
        if(!$r||!in_array($r->status,['request-pending','request-failed'],true))return;
        rrw_wpx_request_status($r->ID,'request-confirmed');
        update_post_meta($r->ID,'_wp_user_request_confirmed_timestamp',time());delete_post_meta($r->ID,'_wp_admin_notified');
    }
}
if(!function_exists('_wp_privacy_account_request_confirmed_message')){
    function _wp_privacy_account_request_confirmed_message($request_id) {
        $r=wp_get_user_request($request_id);
        $msg='<p class="success">Die Aktion wurde bestätigt.</p><p>Der Websitebetreiber wurde benachrichtigt und bearbeitet deine Anfrage so bald wie möglich.</p>';
        if($r&&$r->action_name==='export_personal_data')$msg='<p class="success">Die Anfrage zum Export deiner Daten wurde bestätigt.</p><p>Der Websitebetreiber wurde benachrichtigt und sendet dir den Export per E-Mail.</p>';
        elseif($r&&$r->action_name==='remove_personal_data')$msg='<p class="success">Die Anfrage zum Löschen deiner Daten wurde bestätigt.</p><p>Der Websitebetreiber wurde benachrichtigt und löscht deine Daten.</p>';
        return apply_filters('user_request_action_confirmed_message',$msg,$request_id);
    }
}
if(!function_exists('_wp_privacy_send_request_confirmation_notification')){
    /** Meldet dem Administrator eine bestätigte Anfrage (einmalig je Anfrage). */
    function _wp_privacy_send_request_confirmation_notification($request_id) {
        $r=wp_get_user_request($request_id);
        if(!$r||$r->status!=='request-confirmed'||get_post_meta($r->ID,'_wp_admin_notified',true))return;
        $site=rrw_wpx_site_name();$desc=wp_user_request_action_description($r->action_name);
        $m=apply_filters('user_confirmed_action_email_content',"Hallo,\n\n{$r->email} hat die Anfrage „{$desc}“ auf $site bestätigt.\n\nBearbeitung: ".admin_url('tools.php?page='.($r->action_name==='export_personal_data'?'export_personal_data':'remove_personal_data'))."\n",['request'=>$r,'email'=>$r->email,'description'=>$desc,'sitename'=>$site]);
        $to=apply_filters('user_request_confirmed_email_to',get_option('admin_email'),$r);
        $ok=wp_mail($to,'['.$site.'] Anfrage bestätigt: '.$desc,(string)$m);
        if($ok)update_post_meta($r->ID,'_wp_admin_notified',true);
    }
}
if(!function_exists('_wp_privacy_send_erasure_fulfillment_notification')){
    function _wp_privacy_send_erasure_fulfillment_notification($request_id) {
        $r=wp_get_user_request($request_id);
        if(!$r||$r->action_name!=='remove_personal_data'||$r->status!=='request-completed'||get_post_meta($r->ID,'_wp_user_notified',true))return;
        $site=rrw_wpx_site_name();
        $m=apply_filters('user_erasure_fulfillment_email_content',"Hallo,\n\ndeine Anfrage zum Löschen personenbezogener Daten auf $site wurde bearbeitet. Deine Daten wurden entfernt, soweit keine gesetzliche Pflicht zur Aufbewahrung besteht.\n",['request'=>$r,'email'=>$r->email,'sitename'=>$site]);
        $ok=wp_mail($r->email,'['.$site.'] Deine Daten wurden gelöscht',(string)$m);
        if($ok)update_post_meta($r->ID,'_wp_user_notified',true);
    }
}
if(!function_exists('wp_register_user_personal_data_exporter')){
    function wp_register_user_personal_data_exporter($exporters) { $exporters['wordpress-user']=['exporter_friendly_name'=>'WordPress-Benutzer','callback'=>'wp_user_personal_data_exporter'];return $exporters; }
}
if(!function_exists('wp_user_personal_data_exporter')){
    function wp_user_personal_data_exporter($email_address) {
        $user=get_user_by('email',trim((string)$email_address));
        if(!$user)return ['data'=>[],'done'=>true];
        $props=['ID'=>'Benutzer-ID','user_login'=>'Benutzername','user_nicename'=>'Name in der Adresszeile','user_email'=>'E-Mail-Adresse','user_url'=>'Website','user_registered'=>'Registriert am','display_name'=>'Angezeigter Name'];
        $out=[];foreach($props as $k=>$label){ $v=$user->$k;if(!empty($v))$out[]=['name'=>$label,'value'=>(string)$v]; }
        foreach(['nickname'=>'Spitzname','first_name'=>'Vorname','last_name'=>'Nachname','description'=>'Biografie'] as $k=>$label){ $v=get_user_meta($user->ID,$k,true);if(!empty($v))$out[]=['name'=>$label,'value'=>(string)$v]; }
        $out=apply_filters('wp_privacy_additional_user_profile_data',$out,$user,[]);
        return ['data'=>[['group_id'=>'user','group_label'=>'Benutzer','group_description'=>'Daten des Benutzerkontos.','item_id'=>'user-'.$user->ID,'data'=>$out]],'done'=>true];
    }
}

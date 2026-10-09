<?php
// Ergänzende WordPress-Funktionen (Bereich Admin, Grundlage): kleine Helfer für die Ajax-Handler und Verwaltungsfunktionen (admin-*.php). Wird als erste admin-Datei geladen.

if(!function_exists('elvado_adm_req')){ function elvado_adm_req($k, $d='') { return isset($_REQUEST[$k])?wp_unslash($_REQUEST[$k]):$d; } }   // Anfragewert ohne Slashes
if(!function_exists('elvado_adm_need')){ function elvado_adm_need($cap, ...$args) { if(!current_user_can($cap,...$args))wp_die(-1); } }   // Recht prüfen, sonst „-1“
if(!function_exists('elvado_adm_hook')){ function elvado_adm_hook(array $map) { foreach($map as $a=>$fn)if(function_exists($fn)&&!has_action('wp_ajax_'.$a))add_action('wp_ajax_'.$a,$fn,1); } }
if(!function_exists('elvado_adm_can')){ function elvado_adm_can($cap, $fallback='manage_options') { return current_user_can($cap)||($fallback&&current_user_can($fallback)); } }   // Recht oder Rückfall-Recht (die Rechteprüfung des Kerns kennt keine Meta-Rechte wie edit_user)
if(!function_exists('elvado_adm_can_user')){ function elvado_adm_can_user($uid) { return (int)$uid===get_current_user_id()||current_user_can('edit_users'); } }
if(!function_exists('elvado_adm_comment')){ function elvado_adm_comment($id) { return function_exists('elvado_wpx_comment')?elvado_wpx_comment($id):get_comment($id); } }   // Kommentar in jedem Status (get_comment() kennt nur freigegebene)
if(!function_exists('elvado_adm_cstatus')){ function elvado_adm_cstatus($c) {   // Status eines Kommentars (ID oder Objekt) aus der Datenbank
    $c=elvado_adm_comment(is_object($c)?(int)$c->comment_ID:(int)$c);if(!$c)return false;
    return match((string)$c->comment_approved){'1'=>'approved','0'=>'unapproved','spam'=>'spam','trash'=>'trash',default=>false};
} }

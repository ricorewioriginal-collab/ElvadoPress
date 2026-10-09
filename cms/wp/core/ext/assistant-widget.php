<?php
// Chat-Fenster des KI-Assistenten auf der öffentlichen Website .
// Wird im Seitenfuß der WordPress-Themes eingebunden, wenn der Assistent in der Verwaltung eingeschaltet ist.
if(!function_exists('rrw_wp_assistant_widget')){
    function rrw_wp_assistant_widget(): void {
        if(defined('RRW_DEMO')||(function_exists('is_admin')&&is_admin())||(function_exists('is_customize_preview')&&is_customize_preview()))return;
        require_once dirname(__DIR__,3).'/lib/assistant.php';
        $a=rrw_assistant_clean((array)(($GLOBALS['RRW_SITE']??[])['assistant']??[]));
        if(empty($a['enabled']))return;
        $e=fn(string $s)=>htmlspecialchars($s,ENT_QUOTES,'UTF-8');
        echo '<script src="/cms/assets/assistant-widget.js?v=1" defer data-endpoint="/cms/api.php?action=assistant_chat" data-name="'.$e($a['name']).'" data-greeting="'.$e($a['greeting']).'" data-privacy="'.$e($a['privacy_note']).'"></script>'."\n";
    }
    add_action('wp_footer','rrw_wp_assistant_widget',90);
}

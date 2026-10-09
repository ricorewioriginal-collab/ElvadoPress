<?php
// WordPress-kompatibles Einbinden von Skripten und Stilen (wp_enqueue_script/style …) inkl. Abhängigkeiten, Versionen,
// wp_localize_script und Inline-Code. Ausgabe über wp_print_styles()/wp_print_head_scripts()/wp_print_footer_scripts().
$GLOBALS['elvado_wp_scripts']=$GLOBALS['elvado_wp_scripts']??['reg'=>[],'queue'=>[],'done'=>[]];
$GLOBALS['elvado_wp_styles']=$GLOBALS['elvado_wp_styles']??['reg'=>[],'queue'=>[],'done'=>[]];

function _elvado_wp_reg(string $kind, string $handle, $src, $deps, $ver, $extra): bool {
    $g=&$GLOBALS['elvado_wp_'.$kind];
    if(isset($g['reg'][$handle]))return false;
    $g['reg'][$handle]=['src'=>$src,'deps'=>array_values((array)$deps),'ver'=>$ver,'extra'=>$extra,'inline_before'=>[],'inline_after'=>[],'l10n'=>[]];
    return true;
}
function wp_register_script($handle, $src, $deps=[], $ver=false, $args=false) { return _elvado_wp_reg('scripts',(string)$handle,$src,$deps,$ver,is_array($args)?!empty($args['in_footer']):(bool)$args); }
function wp_register_style($handle, $src, $deps=[], $ver=false, $media='all') { return _elvado_wp_reg('styles',(string)$handle,$src,$deps,$ver,$media); }
function wp_enqueue_script($handle, $src='', $deps=[], $ver=false, $args=false) {
    if($src!=='')wp_register_script($handle,$src,$deps,$ver,$args);
    $GLOBALS['elvado_wp_scripts']['queue'][$handle]=true;
}
function wp_enqueue_style($handle, $src='', $deps=[], $ver=false, $media='all') {
    if($src!=='')wp_register_style($handle,$src,$deps,$ver,$media);
    $GLOBALS['elvado_wp_styles']['queue'][$handle]=true;
}
function wp_deregister_script($h) { unset($GLOBALS['elvado_wp_scripts']['reg'][$h],$GLOBALS['elvado_wp_scripts']['queue'][$h]); }
function wp_deregister_style($h) { unset($GLOBALS['elvado_wp_styles']['reg'][$h],$GLOBALS['elvado_wp_styles']['queue'][$h]); }
function wp_dequeue_script($h) { unset($GLOBALS['elvado_wp_scripts']['queue'][$h]); }
function wp_dequeue_style($h) { unset($GLOBALS['elvado_wp_styles']['queue'][$h]); }
/** Wie WP_Dependencies::query('enqueued'): auch Abhängigkeiten eingereihter Handles gelten als eingereiht. */
function _elvado_wp_is_queued(string $kind, string $handle): bool {
    $g=$GLOBALS['elvado_wp_'.$kind];if(isset($g['queue'][$handle]))return true;
    $seen=[];$walk=function(string $h) use(&$walk,&$seen,$g,$handle):bool{ if(isset($seen[$h])||!isset($g['reg'][$h]))return false;$seen[$h]=true;foreach($g['reg'][$h]['deps'] as $d){ if($d===$handle||$walk((string)$d))return true; } return false; };
    foreach(array_keys($g['queue']) as $q)if($walk((string)$q))return true;
    return false;
}
function wp_script_is($handle, $status='enqueued') { $s=$GLOBALS['elvado_wp_scripts'];return match($status){'registered','$handle'=>isset($s['reg'][$handle]),'enqueued','queue'=>_elvado_wp_is_queued('scripts',(string)$handle),'done'=>isset($s['done'][$handle]),default=>false}; }
function wp_style_is($handle, $status='enqueued') { $s=$GLOBALS['elvado_wp_styles'];return match($status){'registered'=>isset($s['reg'][$handle]),'enqueued','queue'=>_elvado_wp_is_queued('styles',(string)$handle),'done'=>isset($s['done'][$handle]),default=>false}; }
function wp_localize_script($handle, $object_name, $l10n) {
    if(!isset($GLOBALS['elvado_wp_scripts']['reg'][$handle]))return false;
    $GLOBALS['elvado_wp_scripts']['reg'][$handle]['l10n'][]=[(string)$object_name,$l10n];return true;
}
function wp_add_inline_script($handle, $data, $position='after') {
    if(!isset($GLOBALS['elvado_wp_scripts']['reg'][$handle]))return false;
    $GLOBALS['elvado_wp_scripts']['reg'][$handle]['inline_'.($position==='before'?'before':'after')][]=(string)$data;return true;
}
function wp_add_inline_style($handle, $data) {
    if(!isset($GLOBALS['elvado_wp_styles']['reg'][$handle]))return false;
    $GLOBALS['elvado_wp_styles']['reg'][$handle]['inline_after'][]=(string)$data;return true;
}
/** Wie WordPress: ein Skript mit Übersetzungen braucht wp-i18n (Übersetzungsdateien selbst liefert die Schicht nicht). */
function wp_set_script_translations($h,$d='default',$p=null) {
    $g=&$GLOBALS['elvado_wp_scripts'];
    if($h!=='wp-i18n'&&isset($g['reg'][$h],$g['reg']['wp-i18n'])&&!in_array('wp-i18n',$g['reg'][$h]['deps'],true))$g['reg'][$h]['deps'][]='wp-i18n';
    return true;
}

function _elvado_wp_resolve(string $kind, array $handles, array &$order, array &$visiting=[]): void {
    $g=$GLOBALS['elvado_wp_'.$kind];
    foreach($handles as $h){
        if(in_array($h,$order,true)||isset($visiting[$h])||!isset($g['reg'][$h]))continue;
        $visiting[$h]=true;_elvado_wp_resolve($kind,$g['reg'][$h]['deps'],$order,$visiting);unset($visiting[$h]);$order[]=$h;
    }
}
function _elvado_wp_src(array $item): string {
    $src=(string)$item['src'];if($src==='')return '';
    if(str_starts_with($src,'//'))$src='https:'.$src;
    elseif(!preg_match('#^https?://#i',$src))$src=site_url($src);
    $ver=$item['ver']===false?get_bloginfo('version'):$item['ver'];
    if($ver!==null&&$ver!==''&&!str_contains($src,'ver='))$src=add_query_arg('ver',(string)$ver,$src);
    return $src;
}
function wp_print_styles($handles=false) {
    if($handles==='')$handles=false;
    $g=&$GLOBALS['elvado_wp_styles'];$order=[];$q=$handles===false?array_keys($g['queue']):(array)$handles;_elvado_wp_resolve('styles',$q,$order);
    foreach($order as $h){
        if(isset($g['done'][$h]))continue;$g['done'][$h]=true;$it=$g['reg'][$h];$src=apply_filters('style_loader_src',_elvado_wp_src($it),$h);
        if($src!=='')echo apply_filters('style_loader_tag','<link rel="stylesheet" id="'.esc_attr($h).'-css" href="'.esc_url($src).'" media="'.esc_attr($it['extra']?:'all').'" />'."\n",$h,$src,$it['extra']);
        foreach($it['inline_after'] as $css)echo '<style id="'.esc_attr($h).'-inline-css">'.str_replace('</style','<\/style',$css)."</style>\n";
    }
    return array_keys($g['done']);
}
function wp_print_head_scripts() { return _elvado_wp_print_scripts(false); }
function wp_print_footer_scripts() { return _elvado_wp_print_scripts(true); }
function wp_print_scripts($handles=false) { if($handles==='')$handles=false;return _elvado_wp_print_scripts(null,$handles); }
function _elvado_wp_print_scripts(?bool $footer, $handles=false) {
    $g=&$GLOBALS['elvado_wp_scripts'];$order=[];$q=$handles===false?array_keys($g['queue']):(array)$handles;_elvado_wp_resolve('scripts',$q,$order);
    // Wie WordPress: Abhängigkeiten eines Skripts im Head müssen ebenfalls im Head stehen.
    $forceHead=[];$mark=function(string $h) use(&$mark,&$forceHead,&$g){ if(isset($forceHead[$h])||!isset($g['reg'][$h]))return;$forceHead[$h]=true;foreach($g['reg'][$h]['deps'] as $d)$mark($d); };
    foreach($order as $h)if(isset($g['reg'][$h])&&!(bool)$g['reg'][$h]['extra'])$mark($h);
    foreach($order as $h){
        if(isset($g['done'][$h]))continue;$it=$g['reg'][$h];
        // Abhängigkeiten eines Footer-Skripts landen ebenfalls im Footer; Head-Skripte nur, wenn nicht als Footer markiert.
        $inFooter=(bool)$it['extra']&&!isset($forceHead[$h]);if($footer!==null&&$inFooter!==$footer)continue;
        $g['done'][$h]=true;
        foreach($it['l10n'] as [$name,$data]){
            if(is_array($data))foreach($data as $k=>$v)if(is_scalar($v))$data[$k]=html_entity_decode((string)$v,ENT_QUOTES,'UTF-8');
            echo '<script id="'.esc_attr($h).'-js-extra">var '.preg_replace('/[^A-Za-z0-9_$]/','',$name).' = '.wp_json_encode($data,JSON_HEX_TAG|JSON_HEX_AMP).";</script>\n";
        }
        foreach($it['inline_before'] as $js)echo '<script id="'.esc_attr($h).'-js-before">'.str_replace('</script','<\/script',$js)."</script>\n";
        $src=apply_filters('script_loader_src',_elvado_wp_src($it),$h);
        if($src!=='')echo apply_filters('script_loader_tag','<script src="'.esc_url($src).'" id="'.esc_attr($h).'-js"></script>'."\n",$h,$src);
        foreach($it['inline_after'] as $js)echo '<script id="'.esc_attr($h).'-js-after">'.str_replace('</script','<\/script',$js)."</script>\n";
    }
    return array_keys($g['done']);
}

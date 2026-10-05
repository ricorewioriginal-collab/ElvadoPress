<?php
// WordPress-kompatible Hooks (Actions und Filter). Gleiche Namen, Argumente und Prioritäten-Logik wie WordPress,
// damit Plugins und Themes unverändert laufen. Eigenständig implementiert (keine WordPress-Quelltexte).
if(!isset($GLOBALS['wp_filter']))$GLOBALS['wp_filter']=[];
if(!isset($GLOBALS['wp_actions']))$GLOBALS['wp_actions']=[];
if(!isset($GLOBALS['wp_current_filter']))$GLOBALS['wp_current_filter']=[];
if(!isset($GLOBALS['wp_filters_run']))$GLOBALS['wp_filters_run']=[];

function _wp_filter_build_unique_id($tag, $callback, $priority) {
    if(is_string($callback))return $callback;
    if(is_object($callback))return spl_object_hash($callback);
    if(is_array($callback)){
        if(is_object($callback[0]))return spl_object_hash($callback[0]).$callback[1];
        if(is_string($callback[0]))return $callback[0].'::'.$callback[1];
    }
    return md5(serialize($callback));
}
function add_filter($hook_name, $callback, $priority=10, $accepted_args=1) {
    global $wp_filter;
    $id=_wp_filter_build_unique_id($hook_name,$callback,$priority);
    $wp_filter[$hook_name][(int)$priority][$id]=['function'=>$callback,'accepted_args'=>(int)$accepted_args];
    return true;
}
function has_filter($hook_name, $callback=false) {
    global $wp_filter;
    if(empty($wp_filter[$hook_name]))return false;
    if($callback===false)return true;
    $id=_wp_filter_build_unique_id($hook_name,$callback,false);
    foreach($wp_filter[$hook_name] as $prio=>$cbs)if(isset($cbs[$id]))return (int)$prio;
    return false;
}
function remove_filter($hook_name, $callback, $priority=10) {
    global $wp_filter;
    $id=_wp_filter_build_unique_id($hook_name,$callback,$priority);
    if(!isset($wp_filter[$hook_name][(int)$priority][$id]))return false;
    unset($wp_filter[$hook_name][(int)$priority][$id]);
    if(empty($wp_filter[$hook_name][(int)$priority]))unset($wp_filter[$hook_name][(int)$priority]);
    if(empty($wp_filter[$hook_name]))unset($wp_filter[$hook_name]);
    return true;
}
function remove_all_filters($hook_name, $priority=false) {
    global $wp_filter;
    if(!isset($wp_filter[$hook_name]))return true;
    if($priority===false)unset($wp_filter[$hook_name]);else unset($wp_filter[$hook_name][(int)$priority]);
    return true;
}
function _rrw_wp_run_hooks(string $hook_name, array $args, bool $isFilter) {
    global $wp_filter,$wp_current_filter,$wp_filters_run;
    $wp_filters_run[$hook_name]=($wp_filters_run[$hook_name]??0)+1;
    if(empty($wp_filter[$hook_name]))return $isFilter?($args[0]??null):null;
    $wp_current_filter[]=$hook_name;
    $value=$args[0]??null;$last=null;
    try{
        // Wie WordPress: Während des Durchlaufs hinzugefügte Callbacks (auch spätere Prioritäten) werden noch ausgeführt,
        // entfernte nicht mehr.
        while(true){
            $prios=array_keys($wp_filter[$hook_name]??[]);sort($prios,SORT_NUMERIC);
            $prio=null;foreach($prios as $pr)if($last===null||$pr>$last){$prio=$pr;break;}
            if($prio===null)break;
            $last=$prio;$seen=[];
            while(true){
                $id=null;foreach(($wp_filter[$hook_name][$prio]??[]) as $k=>$_)if(!isset($seen[$k])){$id=$k;break;}
                if($id===null)break;
                $seen[$id]=1;$cb=$wp_filter[$hook_name][$prio][$id];$n=$cb['accepted_args'];
                $callArgs=$isFilter?array_merge([$value],array_slice($args,1)):$args;
                if($n<count($callArgs))$callArgs=array_slice($callArgs,0,$n);
                try{ $r=call_user_func_array($cb['function'],$callArgs); if($isFilter)$value=$r; }
                catch(RRW_WP_Die $d){ throw $d; }
                catch(Throwable $e){   // ein fehlerhaftes Plugin darf die Seite nicht beenden: protokollieren und weitermachen
                    rrw_wp_log('Hook '.$hook_name.': '.get_class($e).': '.$e->getMessage().' ('.basename($e->getFile()).':'.$e->getLine().')');
                }
            }
        }
    } finally { array_pop($wp_current_filter); }
    return $isFilter?$value:null;
}
function apply_filters($hook_name, $value, ...$args) { return _rrw_wp_run_hooks((string)$hook_name,array_merge([$value],$args),true); }
function apply_filters_ref_array($hook_name, $args) { return _rrw_wp_run_hooks((string)$hook_name,array_values((array)$args),true); }
function current_filter() { global $wp_current_filter; return end($wp_current_filter)?:false; }
function doing_filter($hook_name=null) { global $wp_current_filter; return $hook_name===null?!empty($wp_current_filter):in_array($hook_name,$wp_current_filter,true); }

function add_action($hook_name, $callback, $priority=10, $accepted_args=1) { return add_filter($hook_name,$callback,$priority,$accepted_args); }
function has_action($hook_name, $callback=false) { return has_filter($hook_name,$callback); }
function remove_action($hook_name, $callback, $priority=10) { return remove_filter($hook_name,$callback,$priority); }
function remove_all_actions($hook_name, $priority=false) { return remove_all_filters($hook_name,$priority); }
function do_action($hook_name, ...$arg) {
    global $wp_actions;
    $wp_actions[$hook_name]=($wp_actions[$hook_name]??0)+1;
    if(!$arg)$arg=[''];   // wie WordPress: ohne Argumente erhalten Callbacks einen leeren String
    _rrw_wp_run_hooks((string)$hook_name,$arg,false);
}
function do_action_ref_array($hook_name, $args) {   // Verweise (&$x) in $args bleiben erhalten
    global $wp_actions;$wp_actions[$hook_name]=($wp_actions[$hook_name]??0)+1;if(!$args)$args=[''];_rrw_wp_run_hooks((string)$hook_name,(array)$args,false);
}
function did_action($hook_name) { global $wp_actions; return $wp_actions[$hook_name]??0; }
function doing_action($hook_name=null) { return doing_filter($hook_name); }
function did_filter($hook_name) { global $wp_filters_run; return $wp_filters_run[$hook_name]??0; }

function apply_filters_deprecated($hook_name, $args, $version='', $replacement='', $message='') { return apply_filters_ref_array($hook_name,$args); }
function do_action_deprecated($hook_name, $args, $version='', $replacement='', $message='') { do_action_ref_array($hook_name,$args); }
function _doing_it_wrong($function_name, $message, $version) { rrw_wp_log('doing_it_wrong: '.$function_name.' – '.$message); }
function _deprecated_function($function_name, $version, $replacement='') {}
function _deprecated_argument($function_name, $version, $message='') {}
function _deprecated_hook($hook, $version, $replacement='', $message='') {}
function _deprecated_file($file, $version, $replacement='', $message='') {}

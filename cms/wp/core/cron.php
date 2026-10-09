<?php
// WordPress-Cron (wp_schedule_event …) und wp_mail. Termine liegen in der Option „cron“; abgearbeitet werden sie durch elvado_wp_run_cron()
// (Cron-Aufruf cron/wp-cron.php oder nebenbei bei CMS-Anfragen, höchstens einmal pro Minute).

function wp_get_schedules() {
    return apply_filters('cron_schedules',['hourly'=>['interval'=>3600,'display'=>'Stündlich'],'twicedaily'=>['interval'=>43200,'display'=>'Zweimal täglich'],'daily'=>['interval'=>86400,'display'=>'Täglich'],'weekly'=>['interval'=>604800,'display'=>'Wöchentlich']]);
}
function _elvado_wp_cron_get(): array { $c=get_option('cron',[]);return is_array($c)?$c:[]; }
function _elvado_wp_cron_set(array $c): void { ksort($c);update_option('cron',$c,'yes'); }
function _elvado_wp_cron_key(array $args): string { return md5(serialize($args)); }
function wp_schedule_single_event($timestamp, $hook, $args=[], $wp_error=false) {
    $c=_elvado_wp_cron_get();$k=_elvado_wp_cron_key($args);
    foreach($c as $ts=>$hooks)if(is_int($ts)&&isset($hooks[$hook][$k])&&abs($ts-$timestamp)<600)return true;     // Doppelte vermeiden
    $c[(int)$timestamp][$hook][$k]=['schedule'=>false,'args'=>$args];_elvado_wp_cron_set($c);return true;
}
function wp_schedule_event($timestamp, $recurrence, $hook, $args=[], $wp_error=false) {
    $s=wp_get_schedules();if(!isset($s[$recurrence]))return $wp_error?new WP_Error('invalid_schedule','Ungültiger Zeitplan'):false;
    $c=_elvado_wp_cron_get();$k=_elvado_wp_cron_key($args);
    if(wp_next_scheduled($hook,$args)!==false)return true;
    $c[(int)$timestamp][$hook][$k]=['schedule'=>$recurrence,'args'=>$args,'interval'=>(int)$s[$recurrence]['interval']];_elvado_wp_cron_set($c);return true;
}
function wp_next_scheduled($hook, $args=[]) {
    $k=_elvado_wp_cron_key($args);$best=false;
    foreach(_elvado_wp_cron_get() as $ts=>$hooks)if(isset($hooks[$hook][$k])&&($best===false||$ts<$best))$best=(int)$ts;
    return $best;
}
function wp_get_scheduled_event($hook, $args=[], $timestamp=null) {
    $ts=$timestamp??wp_next_scheduled($hook,$args);if($ts===false)return false;
    $e=_elvado_wp_cron_get()[$ts][$hook][_elvado_wp_cron_key($args)]??null;if(!$e)return false;
    return (object)['hook'=>$hook,'timestamp'=>$ts,'schedule'=>$e['schedule'],'args'=>$e['args'],'interval'=>$e['interval']??null];
}
function wp_get_schedule($hook, $args=[]) { $e=wp_get_scheduled_event($hook,$args);return $e?$e->schedule:false; }
function wp_unschedule_event($timestamp, $hook, $args=[], $wp_error=false) {
    $c=_elvado_wp_cron_get();$k=_elvado_wp_cron_key($args);if(!isset($c[$timestamp][$hook][$k]))return false;
    unset($c[$timestamp][$hook][$k]);if(!$c[$timestamp][$hook])unset($c[$timestamp][$hook]);if(!$c[$timestamp])unset($c[$timestamp]);_elvado_wp_cron_set($c);return true;
}
function wp_clear_scheduled_hook($hook, $args=[], $wp_error=false) {
    $c=_elvado_wp_cron_get();$n=0;$k=_elvado_wp_cron_key($args);
    foreach($c as $ts=>$hooks)if(isset($hooks[$hook][$k])){unset($c[$ts][$hook][$k]);if(!$c[$ts][$hook])unset($c[$ts][$hook]);if(!$c[$ts])unset($c[$ts]);$n++;}
    if($n)_elvado_wp_cron_set($c);return $n;
}
function wp_unschedule_hook($hook, $wp_error=false) {
    $c=_elvado_wp_cron_get();$n=0;foreach($c as $ts=>$hooks)if(isset($hooks[$hook])){$n+=count($hooks[$hook]);unset($c[$ts][$hook]);if(!$c[$ts])unset($c[$ts]);}
    if($n)_elvado_wp_cron_set($c);return $n;
}
function wp_get_ready_cron_jobs() { $o=[];foreach(_elvado_wp_cron_get() as $ts=>$h)if($ts<=time())$o[$ts]=$h;return $o; }
function spawn_cron($gmt_time=0) { return false; }
function wp_cron() { elvado_wp_run_cron(); }
/** Fällige Aufgaben ausführen (höchstens $max, höchstens $budget Sekunden, höchstens einmal pro Minute, außer $force). */
function elvado_wp_run_cron(bool $force=false, int $max=15, int $budget=20): int {
    if(defined('ELVADO_WP_SANDBOX'))return 0;   // Sandbox führt keine geplanten Aufgaben aus
    $lock=rtrim(ELVADO_WP_DATA,'/').'/cron.lock';
    if(!$force&&is_file($lock)&&filemtime($lock)>time()-60)return 0;
    if(!is_dir(ELVADO_WP_DATA))@mkdir(ELVADO_WP_DATA,0775,true);@touch($lock);
    $start=microtime(true);$ran=0;$c=_elvado_wp_cron_get();
    foreach($c as $ts=>$hooks){
        if($ts>time()||$ran>=$max||microtime(true)-$start>$budget)break;
        foreach($hooks as $hook=>$events)foreach($events as $k=>$e){
            if($ran>=$max)break 3;
            // Zuerst neu einplanen/entfernen, dann ausführen – ein Absturz blockiert so nicht dauerhaft
            $fresh=_elvado_wp_cron_get();unset($fresh[$ts][$hook][$k]);if(empty($fresh[$ts][$hook]))unset($fresh[$ts][$hook]);if(empty($fresh[$ts]))unset($fresh[$ts]);
            if(!empty($e['schedule'])&&!empty($e['interval'])){ $next=$ts+$e['interval'];while($next<=time())$next+=$e['interval'];$fresh[$next][$hook][$k]=$e; }
            _elvado_wp_cron_set($fresh);
            try{ do_action_ref_array($hook,(array)($e['args']??[])); }catch(Throwable $ex){ elvado_wp_log('Cron '.$hook.': '.$ex->getMessage()); }
            $ran++;
        }
    }
    return $ran;
}

/* ───────── wp_mail ───────── */
// PHPMailer-Klassen (Namespace PHPMailer\PHPMailer) werden erst beim ersten Gebrauch geladen
spl_autoload_register(function ($c) { if(strncmp($c,'PHPMailer\\PHPMailer\\',20)===0){ $f=__DIR__.'/PHPMailer/'.substr($c,20).'.php'; if(is_file($f))require_once $f; } });
function wp_mail($to, $subject, $message, $headers='', $attachments=[]) {
    if(defined('ELVADO_WP_SANDBOX')||defined('ELVADO_DEMO'))return false;   // Sandbox/Demo: nichts versenden
    $atts=apply_filters('wp_mail',compact('to','subject','message','headers','attachments'));
    if(isset($atts['to']))$to=$atts['to'];if(isset($atts['subject']))$subject=$atts['subject'];if(isset($atts['message']))$message=$atts['message'];
    if(isset($atts['headers']))$headers=$atts['headers'];if(isset($atts['attachments']))$attachments=$atts['attachments'];
    $pre=apply_filters('pre_wp_mail',null,$atts);if(null!==$pre)return $pre;
    require_once __DIR__.'/mail.php';
    return _elvado_wp_mail_run($to,$subject,$message,$headers,$attachments);
}

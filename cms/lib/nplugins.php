<?php
declare(strict_types=1);
// Brücke zwischen CMS (prozedural) und dem Plugin-System (cms/src/Plugin/): gemeinsamer Plugin-Manager, Laden der aktiven Plugins, Erweiterungspunkte.
// Doku: cms/docs/PLUGIN-ENTWICKLUNG.md (Core vs. Plugin, offizielle Plugins entwickeln).

require_once __DIR__.'/../src/autoload.php';

use Elvado\Plugin\Hooks;
use Elvado\Plugin\PluginManager;

/** Gemeinsamer Plugin-Manager dieser Anfrage. $cmsDir/$dataDir/$version nur in Tests setzen. */
function rrw_np(?string $cmsDir=null,?string $dataDir=null,?string $version=null,bool $fresh=false): PluginManager {
    static $mgr=null;
    if($mgr===null||$fresh){
        $cms=$cmsDir??dirname(__DIR__);
        $mgr=new PluginManager($cms,$dataDir??(defined('RRW_DATA_DIR')?(string)RRW_DATA_DIR:$cms.'/data'),$version??(function_exists('rrw_cms_version')?rrw_cms_version():(trim((string)@file_get_contents($cms.'/VERSION'))?:'0.0.0')));
    }
    return $mgr;
}
/** Aktive offizielle Plugins laden (einmal je Anfrage). Ohne Plugin-Zustand kostet das nur eine Dateiprüfung. */
function rrw_np_boot(): void {
    static $done=false;if($done)return;$done=true;
    $dir=(defined('RRW_DATA_DIR')?(string)RRW_DATA_DIR:dirname(__DIR__).'/data').'/.plugins/state.json';
    if(!is_file($dir))return;
    try{ rrw_np()->boot();Hooks::run('boot'); }catch(Throwable $e){ error_log('[ElvadoPress] Plugin-Start: '.$e->getMessage()); }
}
/** Aktion für Plugins auslösen (kostet ohne Plugins nichts). */
function rrw_np_do(string $hook,mixed ...$args): void { if(Hooks::has($hook))Hooks::run($hook,...$args); }
/** Filter für Plugins (ohne Plugins unverändert). */
function rrw_np_filter(string $hook,mixed $value,mixed ...$args): mixed { return Hooks::has($hook)?Hooks::filter($hook,$value,...$args):$value; }
/** Wird höchstens einmal pro Minute ausgeführt (bei Verwaltungs- und Website-Anfragen): geplante Aufgaben der Plugins (automatische Backups, Aufräumen). */
function rrw_np_tick(): void {
    if(!Hooks::has('tick'))return;
    $f=(defined('RRW_DATA_DIR')?(string)RRW_DATA_DIR:dirname(__DIR__).'/data').'/.plugins/tick';
    $now=time();$last=is_file($f)?(int)@filemtime($f):0;if($now-$last<60)return;
    if(!@touch($f))return;
    Hooks::run('tick',$now);
}

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

/**
 * Bestehende Installation auf das Plugin-System heben: Die empfohlenen Essentials werden installiert, aber NICHT aktiviert – nichts ändert sich am Verhalten der Website,
 * bis der Administrator sie bewusst einschaltet (Plugins › „Empfohlene aktivieren“). Frische Installationen (Installer) und das RicoReWi-Paket sind nicht betroffen.
 * Läuft höchstens einmal (Zustandsdatei) und nur bei Aufrufen der Verwaltung.
 */
function rrw_np_migrate(): void {
    static $done=false;if($done)return;$done=true;
    $cms=dirname(__DIR__);$data=defined('RRW_DATA_DIR')?(string)RRW_DATA_DIR:$cms.'/data';
    if(is_file($data.'/.plugins/state.json'))return;
    if(!is_file($data.'/install.lock')&&!is_file($data.'/system.local.json')&&!is_file($data.'/site.json'))return;   // noch nicht eingerichtet
    try{
        $m=rrw_np();$m->installSelection($m->recommendedIds(),false);$m->setMode('upgrade');
    }catch(Throwable $e){ error_log('[ElvadoPress] Plugin-Migration: '.$e->getMessage()); }
}

/**
 * Kopplung KI-Zentrale ↔ Plugin „Elvado AI“: Sobald in der KI-Zentrale ein nutzbarer Anbieter eingerichtet ist, wird das Plugin (falls nicht aktiv) installiert und aktiviert.
 * Nur einmal (Markierung im Plugin-Zustandsordner): schaltet der Administrator das Plugin später bewusst ab, bleibt es aus. Nicht im RicoReWi-Paket und nicht in der Demo.
 * @return string Meldung für die Verwaltung ('' = nichts getan)
 */
function rrw_np_ai_autoactivate(bool $usable,?PluginManager $mgr=null): string {
    if(!$usable)return '';
    $mgr??=rrw_np();$id='elvado-ai';
    $flag=$mgr->stateDir().'/ai-autoactivated';
    if($mgr->isActive($id)||is_file($flag))return '';
    if(!isset($mgr->catalog()[$id]))return '';
    try{
        $r=$mgr->activate($id,true);
        if(empty($r['ok']))return '';
        @file_put_contents($flag,date('c'));
        return 'Das Plugin „Elvado AI“ wurde aktiviert (KI-Werkzeuge im Editor und im App-Bereich). Du kannst es unter Plugins jederzeit abschalten.';
    }catch(Throwable $e){ error_log('[ElvadoPress] KI-Plugin automatisch aktivieren: '.$e->getMessage());return ''; }
}

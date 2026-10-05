<?php
/* Radio-Theme: Daten aus der Radio-Erweiterung des CMS (cms/lib/radio.php), Bausteine für die Startseite und Shortcodes. */
if(!defined('ABSPATH'))exit;

function elvado_rd_color(string $k,string $d): string { $v=(string)get_theme_mod('rd_'.$k,$d);return preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i',$v)?$v:$d; }
function elvado_rd_data_dir(): string { return dirname(RRW_WP_DATA); }
function elvado_rd_lib(): bool {
    if(function_exists('rrw_radio_load'))return true;
    $f=(defined('RRW_WP_NATIVE_THEMES')?dirname(RRW_WP_NATIVE_THEMES):dirname(__DIR__,3)).'/lib/radio.php';
    if(!is_file($f))return false;require_once $f;return true;
}
function elvado_rd_cfg(bool $reset=false): array { static $c=null;if($reset)$c=null;if($c===null)$c=elvado_rd_lib()?rrw_radio_load(elvado_rd_data_dir()):['stations'=>[],'default'=>'','schedule'=>[],'history_count'=>8,'poll_seconds'=>15,'links'=>[],'show'=>[],'cover_lookup'=>false];return $c; }
function elvado_rd_station(string $id=''): ?array { return elvado_rd_lib()?rrw_radio_station(elvado_rd_cfg(),$id):null; }
/** Jetzt läuft aus dem Zwischenspeicher (kein Warten auf externe Server beim Seitenaufbau; der Browser holt danach den aktuellen Stand). */
function elvado_rd_now(array $s): array {
    $GLOBALS['rrw_radio_cache_only']=true;
    try{ return rrw_radio_now(elvado_rd_data_dir(),elvado_rd_cfg(),$s); } finally { unset($GLOBALS['rrw_radio_cache_only']); }
}
/** Hinweis auf den Alexa-Skill der Website – nur, wenn der Skill im CMS aktiv ist und seine Einstellungen schon abgerufen hat (also wirklich bei Amazon läuft). */
function elvado_rd_alexa_line(): string {
    $f=(defined('RRW_WP_NATIVE_THEMES')?dirname(RRW_WP_NATIVE_THEMES):dirname(__DIR__,3)).'/lib/alexa.php';
    if(!is_file($f))return '';
    require_once $f;
    $site=is_array($GLOBALS['RRW_SITE']??null)?$GLOBALS['RRW_SITE']:[];
    if(!rrw_alexa_neutral()||empty(rrw_alexa_clean($site['alexa']??[])['enabled'])||rrw_alexa_last_fetch(elvado_rd_data_dir())<=0)return '';
    $inv=(string)rrw_alexa_catalog()['brand']['invocationName'];
    return $inv!==''?'<p class="rd-alexa">Auch per Sprachbefehl: „Alexa, öffne '.esc_html($inv).'“</p>':'';
}
function elvado_rd_endpoint(): string { return home_url('/cms/radio.php'); }
function elvado_rd_icon(string $n): string {
    return $n==='play'?'<svg class="i-play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg><svg class="i-pause" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 5h4v14H6zm8 0h4v14h-4z"/></svg>':'';
}
function elvado_rd_station_attrs(array $s): string {
    return ' data-station="'.esc_attr($s['id']).'" data-stream="'.esc_url($s['stream_url']).'" data-name="'.esc_attr($s['name']).'" data-logo="'.esc_url($s['logo']).'"';
}
function elvado_rd_np_card(array $s, array $np): string {
    $cover=$np['cover']!==''?$np['cover']:$s['logo'];$line=trim($np['artist'].($np['artist']!==''&&$np['title']!==''?' – ':'').$np['title']);
    $h='<div class="np-card" data-radio-root'.elvado_rd_station_attrs($s).'>';
    $h.='<img class="np-cover" data-np="cover" src="'.($cover!==''?esc_url($cover):'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==').'" alt="">';
    $h.='<div class="np-body"><div class="np-label"><i class="live-dot"></i> Live · '.esc_html($s['name']).'</div>';
    $h.='<div class="np-artist" data-np="artist">'.esc_html($np['artist']!==''?$np['artist']:($np['title']!==''?'':$s['name'])).'</div><div class="np-title" data-np="title">'.esc_html($np['title']!==''?$np['title']:($np['artist']===''?'Live-Stream':'')).'</div>';
    $h.='<div class="np-meta"><span data-np="show">'.esc_html($np['show']).'</span><span data-np="listeners">'.($np['listeners']!==null?esc_html((string)(int)$np['listeners']).' Hörer':'').'</span></div></div>';
    if($s['stream_url']!=='')$h.='<button type="button" class="play-btn" data-radio-play aria-label="Abspielen / Pausieren">'.elvado_rd_icon('play').'</button>';
    return $h.'</div>';
}
function elvado_rd_history_html(array $np,array $s): string {
    $h='';foreach($np['history'] as $t){ $img=$t['cover']!==''?$t['cover']:$s['logo'];
        $h.='<div class="hist-item">'.($img!==''?'<img loading="lazy" src="'.esc_url($img).'" alt="">':'<img alt="" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==">').'<div><b>'.esc_html($t['artist']).'</b><br>'.esc_html($t['title']).'</div></div>'; }
    return $h!==''?$h:'<p class="hint" style="color:var(--muted)">Noch kein Titelverlauf verfügbar.</p>';
}
function elvado_rd_history(string $id=''): string {
    $s=elvado_rd_station($id);if(!$s)return '';$np=elvado_rd_now($s);
    return '<div class="card" data-radio-history data-station="'.esc_attr($s['id']).'">'.elvado_rd_history_html($np,$s).'</div>';
}
function elvado_rd_schedule(string $id='',bool $todayOnly=false): string {
    $s=elvado_rd_station($id);if(!$s||!elvado_rd_lib())return '';
    $GLOBALS['rrw_radio_cache_only']=true;try{ $sc=rrw_radio_schedule(elvado_rd_data_dir(),elvado_rd_cfg(),$s); } finally { unset($GLOBALS['rrw_radio_cache_only']); }
    if(!$sc)return '<div class="card"><p style="color:var(--muted);margin:0">Noch kein Sendeplan hinterlegt.</p></div>';
    $names=[1=>'Montag',2=>'Dienstag',3=>'Mittwoch',4=>'Donnerstag',5=>'Freitag',6=>'Samstag',7=>'Sonntag'];$now=new DateTimeImmutable('now',rrw_radio_tz());$today=(int)$now->format('N');$cur=rrw_radio_current_show($sc,$now);
    $h='<div class="card">';
    foreach($names as $d=>$n){
        if($todayOnly&&$d!==$today)continue;$rows='';
        foreach($sc as $e)if((int)$e['day']===$d)$rows.='<div class="sched-row'.($d===$today&&$e['title']===$cur?' now':'').'"><time>'.esc_html($e['from'].' – '.$e['to']).'</time><span>'.esc_html($e['title']).($e['host']!==''?' <small>mit '.esc_html($e['host']).'</small>':'').'</span></div>';
        if($rows!=='')$h.='<div class="sched-day'.($d===$today?' today':'').'"><h3>'.esc_html($n).'</h3>'.$rows.'</div>';
    }
    return $h.'</div>';
}
function elvado_rd_stations(): string {
    $c=elvado_rd_cfg();if(count($c['stations'])<1)return '';$h='<div class="st-grid">';
    foreach($c['stations'] as $s){
        $h.='<div class="st-card'.($s['id']===$c['default']?' is-current':'').'">'.($s['logo']!==''?'<img loading="lazy" src="'.esc_url($s['logo']).'" alt="">':'').'<h3>'.esc_html($s['name']).'</h3>'.($s['genre']!==''||$s['tagline']!==''?'<p>'.esc_html($s['tagline']!==''?$s['tagline']:$s['genre']).'</p>':'')
            .'<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:auto">'.($s['stream_url']!==''?'<button type="button" class="btn" data-radio-select'.elvado_rd_station_attrs($s).'>Hören</button>':'').($s['website']!==''?'<a class="btn btn-ghost" href="'.esc_url($s['website']).'" rel="noopener">Website</a>':'').'</div></div>';
    }
    return $h.'</div>';
}
function elvado_rd_player_bar(): void {
    $s=elvado_rd_station();if(!$s||$s['stream_url']==='')return;$c=elvado_rd_cfg();
    echo '<div class="radio-bar" data-radio-bar data-endpoint="'.esc_url(elvado_rd_endpoint()).'" data-poll="'.(int)$c['poll_seconds'].'"'.elvado_rd_station_attrs($s).' role="region" aria-label="Radio-Player">'
        .'<img data-np="cover" src="'.($s['logo']!==''?esc_url($s['logo']):'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==').'" alt="">'
        .'<div class="rb-text"><b data-np="station">'.esc_html($s['name']).'</b><span data-np="line">Live-Stream</span></div>'
        .'<label class="rb-vol"><span class="screen-reader-text">Lautstärke</span><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 9v6h4l5 5V4L7 9zm13.5 3A4.5 4.5 0 0 0 14 8v8a4.5 4.5 0 0 0 2.5-4z"/></svg><input type="range" min="0" max="100" value="80" data-radio-volume></label>'
        .'<button type="button" class="play-btn" data-radio-play aria-label="Abspielen / Pausieren">'.elvado_rd_icon('play').'</button></div>';
}
/* Shortcodes (wie in WordPress in Beiträgen, Seiten und HTML-Blöcken nutzbar) */
add_shortcode('radio_player',function($a){ $a=shortcode_atts(['station'=>''],$a);$s=elvado_rd_station((string)$a['station']);return $s?elvado_rd_np_card($s,elvado_rd_now($s)):'<p>Noch kein Sender eingerichtet.</p>'; });
add_shortcode('radio_nowplaying',function($a){ $a=shortcode_atts(['station'=>''],$a);$s=elvado_rd_station((string)$a['station']);if(!$s)return '';$np=elvado_rd_now($s);return '<span data-radio-root'.elvado_rd_station_attrs($s).'><b data-np="artist">'.esc_html($np['artist']).'</b> <span data-np="title">'.esc_html($np['title']).'</span></span>'; });
add_shortcode('radio_history',function($a){ $a=shortcode_atts(['station'=>''],$a);return elvado_rd_history((string)$a['station']); });
add_shortcode('radio_schedule',function($a){ $a=shortcode_atts(['station'=>'','today'=>''],$a);return elvado_rd_schedule((string)$a['station'],$a['today']==='1'||$a['today']==='ja'); });
add_shortcode('radio_stations',function(){ return elvado_rd_stations(); });

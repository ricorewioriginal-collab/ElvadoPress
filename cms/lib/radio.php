<?php
declare(strict_types=1);
// Radio-Erweiterung (neutral): Sender, Datenquellen (laut.fm-API oder eigener Icecast-/Shoutcast-Server), „Jetzt läuft“, Titelverlauf und Sendeplan.
// Konfiguration: cms/data/.tools/radio.json (gesperrter Ordner), kurzer Abruf-Cache: cms/data/.tools/radio-cache/.
// Genutzt vom Theme „ElvadoPress Radio“, vom öffentlichen Endpunkt cms/radio.php und von der Verwaltung (Aktionen radio_* in cms/api.php).
// Bewusst ohne Abhängigkeit vom CMS-Kern (nur tools.php + feeds.php), damit der Endpunkt schnell bleibt.
require_once __DIR__.'/tools.php';
require_once __DIR__.'/feeds.php';

const RRW_RADIO_THEME='elvado-radio';
const RRW_RADIO_SOURCES=['lautfm'=>'laut.fm','icecast'=>'Icecast','shoutcast'=>'Shoutcast','static'=>'Nur Stream (ohne Titelanzeige)'];

function rrw_radio_defaults(): array {
    return ['stations'=>[],'default'=>'','schedule'=>[],'history_count'=>8,'poll_seconds'=>15,'cover_lookup'=>true,
        'links'=>[],'show'=>['history'=>true,'schedule'=>true,'stations'=>true,'news'=>true]];
}
function rrw_radio_file(string $dataDir): string { return rrw_tools_dir($dataDir).'/radio.json'; }
function rrw_radio_load(string $dataDir): array {
    $c=rrw_tools_read(rrw_radio_file($dataDir),[]);
    return rrw_radio_clean($c);
}
function rrw_radio_save(string $dataDir, array $in): array {
    $c=rrw_radio_clean($in);rrw_tools_write(rrw_tools_dir($dataDir),'radio.json',$c);
    foreach(glob(rrw_tools_dir($dataDir).'/radio-cache/*.json')?:[] as $f)@unlink($f);   // neue Quelle → alter Cache ungültig
    return $c;
}
function rrw_radio_url($v,bool $httpOnly=true): string {
    $u=trim((string)$v);if($u===''||strlen($u)>1000)return '';
    $p=@parse_url($u);if(!is_array($p)||empty($p['host']))return '';
    $s=strtolower((string)($p['scheme']??''));if(!in_array($s,$httpOnly?['http','https']:['http','https','mailto'],true))return '';
    return $u;
}
function rrw_radio_text($v,int $max=200): string { return mb_substr(trim(strip_tags((string)$v)),0,$max); }
function rrw_radio_hm($v,string $fallback): string { return preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/',trim((string)$v),$m)?sprintf('%02d:%02d',$m[1],$m[2]):$fallback; }

/** Bereinigt die Konfiguration (Admin-Eingabe und gespeicherte Datei). */
function rrw_radio_clean($in): array {
    $in=is_array($in)?$in:[];$d=rrw_radio_defaults();$out=$d;$ids=[];
    foreach((array)($in['stations']??[]) as $s){
        if(!is_array($s)||count($out['stations'])>=12)continue;
        $src=(string)($s['source']??'static');if(!isset(RRW_RADIO_SOURCES[$src]))$src='static';
        $id=preg_replace('/[^a-z0-9_-]/','',strtolower((string)($s['id']??'')));if($id===''||strlen($id)>40||isset($ids[$id]))$id='s'.substr(md5(uniqid('',true).count($out['stations'])),0,7);$ids[$id]=1;
        $laut=strtolower(preg_replace('/[^A-Za-z0-9_-]/','',(string)($s['lautfm_id']??'')));
        $st=['id'=>$id,'name'=>rrw_radio_text($s['name']??'',80),'tagline'=>rrw_radio_text($s['tagline']??'',200),'genre'=>rrw_radio_text($s['genre']??'',80),'logo'=>rrw_radio_url($s['logo']??''),
            'source'=>$src,'lautfm_id'=>substr($laut,0,60),'stream_url'=>rrw_radio_url($s['stream_url']??''),'status_url'=>rrw_radio_url($s['status_url']??''),
            'mount'=>rrw_radio_text($s['mount']??'',120),'sid'=>max(1,min(99,(int)($s['sid']??1))),'website'=>rrw_radio_url($s['website']??'')];
        if($src==='lautfm'&&$st['stream_url']===''&&$laut!=='')$st['stream_url']='https://stream.laut.fm/'.$laut;
        if($st['name']==='')$st['name']=$laut!==''?$laut:'Sender '.(count($out['stations'])+1);
        $out['stations'][]=$st;
    }
    $dflt=preg_replace('/[^a-z0-9_-]/','',strtolower((string)($in['default']??'')));
    $out['default']=isset($ids[$dflt])?$dflt:(string)($out['stations'][0]['id']??'');
    foreach((array)($in['schedule']??[]) as $e){
        if(!is_array($e)||count($out['schedule'])>=300)continue;$t=rrw_radio_text($e['title']??'',120);if($t==='')continue;
        $out['schedule'][]=['day'=>max(1,min(7,(int)($e['day']??1))),'from'=>rrw_radio_hm($e['from']??'','00:00'),'to'=>rrw_radio_hm($e['to']??'','00:00'),'title'=>$t,'host'=>rrw_radio_text($e['host']??'',80)];
    }
    usort($out['schedule'],fn($a,$b)=>[$a['day'],$a['from']]<=>[$b['day'],$b['from']]);
    $out['history_count']=max(1,min(20,(int)($in['history_count']??$d['history_count'])));
    $out['poll_seconds']=max(10,min(120,(int)($in['poll_seconds']??$d['poll_seconds'])));
    $out['cover_lookup']=array_key_exists('cover_lookup',$in)?filter_var($in['cover_lookup'],FILTER_VALIDATE_BOOLEAN):true;
    foreach((array)($in['links']??[]) as $l){
        if(!is_array($l)||count($out['links'])>=12)continue;$u=rrw_radio_url($l['url']??'');$lb=rrw_radio_text($l['label']??'',40);
        if($u!==''&&$lb!=='')$out['links'][]=['label'=>$lb,'url'=>$u];
    }
    foreach($d['show'] as $k=>$v)$out['show'][$k]=array_key_exists($k,(array)($in['show']??[]))?filter_var($in['show'][$k],FILTER_VALIDATE_BOOLEAN):$v;
    return $out;
}
function rrw_radio_station(array $cfg, string $id=''): ?array {
    foreach($cfg['stations'] as $s)if($id!==''&&$s['id']===$id)return $s;
    if($id!=='')return null;
    foreach($cfg['stations'] as $s)if($s['id']===$cfg['default'])return $s;
    return $cfg['stations'][0]??null;
}
/** Öffentliche Sicht auf einen Sender (nur, was Besucher brauchen). */
function rrw_radio_public_station(array $s): array {
    return ['id'=>$s['id'],'name'=>$s['name'],'tagline'=>$s['tagline'],'genre'=>$s['genre'],'logo'=>$s['logo'],'stream_url'=>$s['stream_url'],'website'=>$s['website'],'source'=>$s['source']];
}
/** Ist das Radio-Theme die ausgelieferte Website? (Steuert das Verwaltungsmenü „Radio“.) */
function rrw_radio_theme_active(string $dataDir): bool {
    $wp=rtrim($dataDir,'/').'/.wp';if(!is_file($wp.'/front-on'))return false;
    $o=json_decode((string)@file_get_contents($wp.'/options.json'),true);
    $v=is_array($o)&&isset($o['stylesheet']['v'])?@unserialize((string)$o['stylesheet']['v']):false;
    return $v===RRW_RADIO_THEME;
}

/* ───────── Abruf ───────── */
/** HTTP-Abruf; Tests können $GLOBALS['rrw_radio_http'] (callable url→string) setzen. Nur öffentliche Adressen (SSRF-Schutz in rrw_fetch_url). */
function rrw_radio_http(string $url): string {
    if(isset($GLOBALS['rrw_radio_http']))return (string)($GLOBALS['rrw_radio_http'])($url);
    return rrw_fetch_url($url,6);
}
function rrw_radio_json(string $url): ?array { $r=rrw_radio_http($url);$j=$r!==''?json_decode($r,true):null;return is_array($j)?$j:null; }
function rrw_radio_cache(string $dataDir, string $key, int $ttl, callable $make): ?array {
    $dir=rrw_tools_dir($dataDir).'/radio-cache';$f=$dir.'/'.md5($key).'.json';
    $old=is_file($f)?json_decode((string)@file_get_contents($f),true):null;
    if(is_array($old)&&isset($old['t'],$old['d'])&&time()-(int)$old['t']<$ttl)return $old['d'];
    if(!empty($GLOBALS['rrw_radio_cache_only']))return is_array($old)?($old['d']??null):null;   // Seitenaufbau im Theme: nie auf externe Server warten (der Browser aktualisiert danach)
    $d=$make();
    if(is_array($d)){ try{ rrw_tools_write($dir,md5($key).'.json',['t'=>time(),'d'=>$d]); }catch(Throwable $e){} return $d; }
    return is_array($old)?($old['d']??null):null;   // Quelle gerade down → letzter bekannter Stand
}
/** „Artist - Titel“ trennen; ohne Trennzeichen bleibt alles im Titel. */
function rrw_radio_split_song(string $s): array {
    $s=trim(html_entity_decode($s,ENT_QUOTES,'UTF-8'));
    if(preg_match('/^(.{1,200}?)\s+[-–—]\s+(.{1,300})$/u',$s,$m))return [trim($m[1]),trim($m[2])];
    return ['',$s];
}
function rrw_radio_icecast_find(array $j, string $mount, string $listen): ?array {
    $src=$j['icestats']['source']??null;if($src===null)return null;
    if(isset($src['listenurl']))$src=[$src];
    foreach((array)$src as $s){
        if(!is_array($s))continue;$lu=(string)($s['listenurl']??'');
        if($mount!==''&&$mount!=='/'&&str_ends_with($lu,'/'.ltrim($mount,'/')))return $s;
    }
    if($mount===''&&$listen!==''){ foreach((array)$src as $s)if(is_array($s)&&rtrim((string)($s['listenurl']??''),'/')===rtrim($listen,'/'))return $s; }
    return $mount===''?((array)$src)[0]??null:null;
}
/** Rohdaten der Quelle: ['artist','title','listeners','history'=>[[artist,title]],'name','logo','description','genres'] oder null. */
function rrw_radio_fetch_raw(array $s): ?array {
    switch($s['source']){
    case 'lautfm':
        $id=$s['lautfm_id'];if($id==='')return null;$b='https://api.laut.fm/station/'.rawurlencode($id);
        $cur=rrw_radio_json($b.'/current_song');$hist=rrw_radio_json($b.'/last_songs');$info=rrw_radio_json($b);
        if($cur===null&&$info===null)return null;
        $h=[];foreach((array)($hist??[]) as $t)if(is_array($t))$h[]=[(string)($t['artist']['name']??''),(string)($t['title']??'')];
        return ['artist'=>(string)($cur['artist']['name']??''),'title'=>(string)($cur['title']??''),'listeners'=>null,'history'=>$h,
            'name'=>(string)($info['display_name']??''),'logo'=>(string)($info['images']['station_120x120']??''),'description'=>(string)($info['description']??''),'genres'=>array_values(array_filter(array_map('strval',(array)($info['genres']??[]))))];
    case 'icecast':
        if($s['status_url']==='')return null;$j=rrw_radio_json($s['status_url']);if($j===null)return null;
        $src=rrw_radio_icecast_find($j,$s['mount'],$s['stream_url']);if($src===null)return null;
        [$a,$t]=rrw_radio_split_song((string)($src['title']??''));if(isset($src['artist'])&&$src['artist']!==''){ $a=(string)$src['artist'];$t=(string)($src['title']??''); }
        return ['artist'=>$a,'title'=>$t,'listeners'=>isset($src['listeners'])?(int)$src['listeners']:null,'history'=>[],'name'=>(string)($src['server_name']??''),'logo'=>'','description'=>(string)($src['server_description']??''),'genres'=>[]];
    case 'shoutcast':
        if($s['status_url']==='')return null;$base=rtrim($s['status_url'],'/');
        $j=rrw_radio_json($base.'/stats?sid='.(int)$s['sid'].'&json=1');
        if($j!==null){
            [$a,$t]=rrw_radio_split_song((string)($j['songtitle']??''));$h=[];
            foreach((array)($j['songhistory']??[]) as $x)if(is_array($x)){ [$ha,$ht]=rrw_radio_split_song((string)($x['title']??''));$h[]=[$ha,$ht]; }
            return ['artist'=>$a,'title'=>$t,'listeners'=>isset($j['currentlisteners'])?(int)$j['currentlisteners']:null,'history'=>$h,'name'=>(string)($j['servertitle']??''),'logo'=>'','description'=>'','genres'=>[]];
        }
        $r=rrw_radio_http($base.'/7.html');   // Shoutcast v1: „<body>1,1,2,100,1,128,Artist - Titel</body>“
        if($r!==''&&preg_match('/<body>(.*?)<\/body>/is',$r,$m)){ $p=explode(',',$m[1],7);[$a,$t]=rrw_radio_split_song((string)($p[6]??''));return ['artist'=>$a,'title'=>$t,'listeners'=>isset($p[0])?(int)$p[0]:null,'history'=>[],'name'=>'','logo'=>'','description'=>'','genres'=>[]]; }
        return null;
    }
    return null;
}
/** Cover über die iTunes-Suche (serverseitig, zwischengespeichert; nur wenn aktiviert). */
function rrw_radio_cover(string $dataDir, string $artist, string $title): string {
    if($artist===''||$title==='')return '';
    $d=rrw_radio_cache($dataDir,'cover|'.mb_strtolower($artist.'|'.$title),86400*7,function() use($artist,$title){
        $j=rrw_radio_json('https://itunes.apple.com/search?term='.rawurlencode($artist.' '.$title).'&entity=song&limit=1');
        $u=(string)($j['results'][0]['artworkUrl100']??'');return ['u'=>$u!==''?str_replace('100x100bb','300x300bb',$u):''];
    });
    return (string)($d['u']??'');
}
/** „Jetzt läuft“ + Verlauf eines Senders (zwischengespeichert, bei Ausfall letzter Stand). */
function rrw_radio_now(string $dataDir, array $cfg, array $s): array {
    $ttl=max(5,(int)$cfg['poll_seconds']-3);
    $raw=rrw_radio_cache($dataDir,'now|'.json_encode([$s['source'],$s['lautfm_id'],$s['status_url'],$s['mount'],$s['sid'],$s['stream_url']]),$ttl,fn()=>rrw_radio_fetch_raw($s));
    $out=['station'=>rrw_radio_public_station($s),'ok'=>$raw!==null,'artist'=>'','title'=>'','cover'=>'','listeners'=>null,'show'=>'','history'=>[],'updated'=>time()];
    if($raw===null)return $out;
    $out['artist']=rrw_radio_text($raw['artist'],200);$out['title']=rrw_radio_text($raw['title'],300);$out['listeners']=$raw['listeners'];
    $fb=$s['logo']!==''?$s['logo']:(rrw_radio_url($raw['logo']??''));
    $cl=$cfg['cover_lookup'];
    $out['cover']=($cl?rrw_radio_cover($dataDir,$out['artist'],$out['title']):'')?:$fb;
    $n=(int)$cfg['history_count'];$cn=0;
    foreach((array)$raw['history'] as $h){
        if($cn>=$n)break;if(rrw_radio_text($h[1]??'')==='')continue;
        if($cn===0&&mb_strtolower($h[0])===mb_strtolower($out['artist'])&&mb_strtolower($h[1])===mb_strtolower($out['title']))continue;   // laufender Titel steht oft auch im Verlauf
        $out['history'][]=['artist'=>rrw_radio_text($h[0],200),'title'=>rrw_radio_text($h[1],300),'cover'=>$cl&&$cn<4?(rrw_radio_cover($dataDir,(string)$h[0],(string)$h[1])?:$fb):$fb];$cn++;
    }
    $out['show']=rrw_radio_current_show(rrw_radio_schedule($dataDir,$cfg,$s));
    return $out;
}
/* ───────── Sendeplan ───────── */
const RRW_RADIO_DAYS=['monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,'friday'=>5,'saturday'=>6,'sunday'=>7];
/** Sendeplan: manuell gepflegt (hat Vorrang) oder – bei laut.fm – aus den Playlists des Senders. Einträge: day 1–7, from/to „HH:MM“, title, host. */
function rrw_radio_schedule(string $dataDir, array $cfg, array $s): array {
    if($cfg['schedule'])return $cfg['schedule'];
    if($s['source']!=='lautfm'||$s['lautfm_id']==='')return [];
    $d=rrw_radio_cache($dataDir,'sched|'.$s['lautfm_id'],900,function() use($s){
        $j=rrw_radio_json('https://api.laut.fm/station/'.rawurlencode($s['lautfm_id']).'/playlists');if($j===null)return null;$out=[];
        foreach($j as $p){ if(!is_array($p))continue;
            foreach((array)($p['airtimes']??[]) as $a){ if(!is_array($a))continue;$day=RRW_RADIO_DAYS[strtolower((string)($a['day']??''))]??0;if(!$day)continue;
                $out[]=['day'=>$day,'from'=>sprintf('%02d:%02d',(int)($a['hour']??0),(int)($a['minute']??0)),'to'=>sprintf('%02d:%02d',(int)($a['end_time']??0),(int)($a['end_minute']??0)),
                    'title'=>rrw_radio_text($p['name']??'',120),'host'=>''];
            } }
        usort($out,fn($a,$b)=>[$a['day'],$a['from']]<=>[$b['day'],$b['from']]);return ['items'=>$out];
    });
    return array_values(array_filter((array)($d['items']??[]),fn($e)=>$e['title']!==''));
}
function rrw_radio_tz(): DateTimeZone { try{ return new DateTimeZone(date_default_timezone_get()?:'Europe/Berlin'); }catch(Throwable $e){ return new DateTimeZone('Europe/Berlin'); } }
/** Name der laufenden Sendung ('' = keine bekannt). Sendungen über Mitternacht (from ≥ to) werden berücksichtigt. */
function rrw_radio_current_show(array $sched, ?DateTimeInterface $now=null): string {
    $now=$now??new DateTimeImmutable('now',rrw_radio_tz());$day=(int)$now->format('N');$m=(int)$now->format('H')*60+(int)$now->format('i');
    $mins=fn($hm)=>(int)substr($hm,0,2)*60+(int)substr($hm,3,2);
    foreach($sched as $e){
        $f=$mins($e['from']);$t=$mins($e['to']);
        if($f<$t){ if((int)$e['day']===$day&&$m>=$f&&$m<$t)return (string)$e['title']; }
        else{ if(((int)$e['day']===$day&&$m>=$f)||((int)$e['day']%7+1===$day&&$m<$t))return (string)$e['title']; }
    }
    return '';
}
/** Test der Verbindung für die Verwaltung: Quelle abrufen und kurz melden. */
function rrw_radio_test(array $s): array {
    if($s['source']==='static')return ['ok'=>$s['stream_url']!=='','message'=>$s['stream_url']!==''?'Nur Stream: Es werden keine Titel angezeigt.':'Stream-Adresse fehlt.'];
    $r=rrw_radio_fetch_raw($s);
    if($r===null)return ['ok'=>false,'message'=>'Die Quelle antwortet nicht oder liefert keine passenden Daten. Adresse, Mount bzw. Sender-Kennung prüfen (nur öffentlich erreichbare Server).'];
    $np=trim($r['artist'].($r['artist']!==''&&$r['title']!==''?' – ':'').$r['title']);
    return ['ok'=>true,'message'=>'Verbunden'.($np!==''?'. Jetzt läuft: '.$np:'').($r['listeners']!==null?' ('.(int)$r['listeners'].' Hörer)':'').'.','now'=>$np];
}

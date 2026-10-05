<?php
require_once __DIR__.'/pack.php';
// Alexa-Skill (mit dem RicoReWi-Paket „RicoReWi Radio“, sonst als Baukasten für den eigenen Skill): Verwaltung im CMS.
// Der Skill (Node.js bei Amazon) holt seine Einstellungen per öffentlicher Aktion alexa_config (nur lesend, ohne Geheimnisse),
// meldet optional anonyme Zähler per alexa_stat (mit Token aus dem Skill-Paket) und wird als Paket aus dem CMS heruntergeladen.
// Das Sprachmodell (Sender-Aussprachen, Sätze) liegt bei Amazon und wird hier erzeugt; Änderungen daran müssen neu eingespielt werden.

function rrw_alexa_dir(string $dataDir): string {
    $d=$dataDir.'/.alexa';
    if(!is_dir($d)){@mkdir($d,0775,true);@file_put_contents($d.'/.htaccess',"Require all denied\n");}
    return $d;
}
function rrw_alexa_skill_dir(): string { return defined('RRW_ALEXA_SKILL_DIR')?rtrim((string)RRW_ALEXA_SKILL_DIR,'/'):__DIR__.'/alexa-skill'; }
/** Baukasten-Modus (eigenständiges CMS): Der Skill gehört dem Betreiber der Website – Name, Aufrufname und Sender stammen aus seinen Einstellungen, nicht aus dem RicoReWi-Katalog. */
function rrw_alexa_neutral(): bool { return !rrw_pack_available(); }
function rrw_alexa_file_catalog(): array {
    static $c=null;if($c===null)$c=json_decode((string)@file_get_contents(rrw_alexa_skill_dir().'/catalog.json'),true)?:['brand'=>[],'suffixes'=>[],'stations'=>[],'brands'=>[]];
    return $c;
}
/** Aufrufname: nur Kleinbuchstaben, Leerzeichen, Apostroph, Punkt (2–50 Zeichen, keine Ziffern) – so schreibt man es, wie man es spricht. */
function rrw_alexa_invocation_clean($v): string {
    $v=mb_strtolower(trim(preg_replace('/\s+/u',' ',(string)$v)));
    $v=preg_replace("/[^\p{Ll} .']+/u",' ',$v);   // Alexa erlaubt keine Ziffern: Zahlen ausschreiben
    $v=trim(preg_replace('/\s+/u',' ',(string)$v));
    return mb_strlen($v)>=2?mb_substr($v,0,50):'';
}
function rrw_alexa_site(): array { return is_array($GLOBALS['RRW_SITE']??null)?$GLOBALS['RRW_SITE']:[]; }
function rrw_alexa_app_name(array $site): string {
    $n=rrw_alexa_text($site['alexa']['app_name']??'',40);if($n!=='')return $n;
    $n=rrw_alexa_text($site['portal']['site_name']??'',40);return $n!==''?$n:'Mein Radio';
}
function rrw_alexa_catalog(): array {
    $f=rrw_alexa_file_catalog();
    if(!rrw_alexa_neutral())return $f;
    $site=rrw_alexa_site();$name=rrw_alexa_app_name($site);
    $inv=rrw_alexa_invocation_clean($site['alexa']['invocation']??'')?:rrw_alexa_invocation_clean($name)?:'mein radio';
    $ids=array_keys((array)($site['alexa']['stations']??[]));
    $default=rrw_alexa_id($site['alexa']['default_station']??'')?:(string)($ids[0]??'');
    $nm=mb_strtolower($name);
    return ['brand'=>['name'=>$name,'invocationName'=>$inv,'defaultStation'=>$default,'streamUrl'=>'https://{id}.stream.laut.fm/{id}','apiBase'=>'https://api.laut.fm/station/'],
        'suffixes'=>$f['suffixes']?:['vierundzwanzig','twenty four','24','vier und zwanzig','twentyfour'],'stations'=>[],
        'brands'=>[['id'=>'main','station'=>$default,'names'=>array_values(array_unique([$nm,$inv])),'syn'=>[]]]];
}
function rrw_alexa_text($v,int $max): string { return mb_substr(trim(strip_tags((string)$v)),0,$max); }
/** Eigene Stream-Adresse eines Senders (nur Baukasten-Modus): Alexa spielt Streams ausschließlich über https. */
function rrw_alexa_stream_clean($v): string { $v=trim((string)$v);return preg_match('~^https://[^\s"\'<>]{4,400}$~i',$v)&&filter_var($v,FILTER_VALIDATE_URL)?$v:''; }
function rrw_alexa_id($v): string { $v=strtolower(trim((string)$v));return preg_match('/^[a-z0-9][a-z0-9_-]{1,62}$/',$v)?$v:''; }

function rrw_alexa_defaults(): array {
    if(rrw_alexa_neutral()){
        $n=rrw_alexa_app_name(rrw_alexa_site());
        return [
            'enabled'=>true,
            'maintenance'=>['enabled'=>false,'text'=>'Der Skill ist gerade nicht verfügbar. Bitte versuche es später noch einmal.'],
            'default_station'=>'',
            'daily'=>false,
            'texts'=>[
                'welcome'=>'Willkommen bei '.$n.'.',
                'play'=>'Hier kommt {sender}.',
                'help'=>'Mit '.$n.' hörst du unsere Radiosender. Sag zum Beispiel: Spiele {beispiele}. Du kannst auch fragen: Was läuft gerade? Welche Sendung läuft? Was kommt als Nächstes? Mit Pause, Weiter und Stopp steuerst du die Wiedergabe.',
                'goodbye'=>'Bis bald!',
                'unavailable'=>'{sender} ist gerade nicht verfügbar.'
            ],
            'stations'=>new stdClass(),'order'=>[],'schedule'=>true,'stats'=>false,
            'app_name'=>'','invocation'=>''
        ];
    }
    return [
        'enabled'=>true,
        'maintenance'=>['enabled'=>false,'text'=>'Der Skill ist gerade nicht verfügbar. Bitte versuche es später noch einmal.'],
        'default_station'=>'ricorewi',
        'daily'=>false,
        'texts'=>[
            'welcome'=>'Willkommen bei RicoReWi Radio.',
            'play'=>'Hier kommt {sender}.',
            'help'=>'Mit RicoReWi Radio hörst du unsere Radiosender. Sag zum Beispiel: Spiele {beispiele}. Du kannst auch fragen: Was läuft gerade? Welche Sendung läuft? Was kommt als Nächstes? Oder: Wie ist der Sendeplan? Mit Pause, Weiter und Stopp steuerst du die Wiedergabe. Was möchtest du hören?',
            'goodbye'=>'Bis bald!',
            'unavailable'=>'{sender} ist gerade nicht verfügbar.'
        ],
        'stations'=>new stdClass(),
        'order'=>[],
        'schedule'=>true,
        'stats'=>false
    ];
}
function rrw_alexa_clean($v): array {
    $v=is_array($v)?$v:[];$d=rrw_alexa_defaults();$t=(array)($v['texts']??[]);$m=(array)($v['maintenance']??[]);
    $out=[
        'enabled'=>!array_key_exists('enabled',$v)||!empty($v['enabled']),
        'maintenance'=>['enabled'=>!empty($m['enabled']),'text'=>rrw_alexa_text($m['text']??'',300)?:$d['maintenance']['text']],
        'default_station'=>rrw_alexa_id($v['default_station']??'')?:$d['default_station'],
        'daily'=>!empty($v['daily']),
        'texts'=>[],
        'stations'=>[],
        'order'=>[],
        'schedule'=>!array_key_exists('schedule',$v)||!empty($v['schedule']),
        'stats'=>!empty($v['stats'])
    ];
    if(rrw_alexa_neutral()){$out['app_name']=rrw_alexa_text($v['app_name']??'',40);$out['invocation']=rrw_alexa_invocation_clean($v['invocation']??'');if(array_key_exists('radio_sync',$v))$out['radio_sync']=!empty($v['radio_sync']);}
    foreach($d['texts'] as $k=>$def){$x=rrw_alexa_text($t[$k]??'',400);$out['texts'][$k]=$x!==''?$x:$def;}
    foreach(array_slice((array)($v['stations']??[]),0,80,true) as $id=>$s){
        $id=rrw_alexa_id($id);if($id===''||!is_array($s))continue;
        $extra=[];foreach(array_slice((array)($s['extra']??[]),0,30) as $x){$x=mb_strtolower(preg_replace('/[^\p{L}\p{N} ]+/u',' ',rrw_alexa_text($x,60)));$x=trim(preg_replace('/\s+/',' ',$x));if($x!==''&&!in_array($x,$extra,true))$extra[]=$x;}
        $out['stations'][$id]=['enabled'=>!array_key_exists('enabled',$s)||!empty($s['enabled']),'title'=>rrw_alexa_text($s['title']??'',60),'extra'=>$extra];
        if(rrw_alexa_neutral()&&($u=rrw_alexa_stream_clean($s['stream']??''))!=='')$out['stations'][$id]['stream']=$u;
    }
    if(!$out['stations'])$out['stations']=new stdClass();
    foreach(array_slice((array)($v['order']??[]),0,80) as $id){$id=rrw_alexa_id($id);if($id!==''&&!in_array($id,$out['order'],true))$out['order'][]=$id;}
    return $out;
}

// "rapradio24" -> "Rapradio 24"
function rrw_alexa_pretty(string $id): string {
    $s=preg_replace('/([a-z])(\d)/','$1 $2',str_replace(['-','_'],' ',$id));return mb_convert_case($s,MB_CASE_TITLE);
}


/* ───────── Verdrahtung mit der Radio-Erweiterung (Radio-Theme) ─────────
   Mit aktivem Radio-Theme (oder gesetztem Schalter radio_sync) werden die Sender aus dem CMS-Menü „Radio“ automatisch zu Skill-Sendern:
   laut.fm-Sender über ihre laut.fm-Kennung (Stream, Titel und Sendeplan wie bisher), alle anderen als eigener Stream; Titel und Sendeplan
   liefert dann der öffentliche Endpunkt cms/radio.php. Alexa spielt nur https-Streams – andere Sender werden übersprungen und gemeldet. */
function rrw_alexa_data_dir(): string { return function_exists('rrw_data_dir')?rrw_data_dir():dirname(__DIR__).'/data'; }
function rrw_alexa_radio_load(): array {
    if(!is_file(__DIR__.'/radio.php'))return ['stations'=>[],'default'=>''];
    require_once __DIR__.'/radio.php';return rrw_radio_load(rrw_alexa_data_dir());
}
/** Ist die Verdrahtung wirksam? Nur im Baukasten-Modus und nur, wenn im Radio-Menü Sender eingerichtet sind. */
function rrw_alexa_radio_sync(array $site): bool {
    if(!rrw_alexa_neutral()||!rrw_alexa_radio_load()['stations'])return false;
    $c=(array)($site['alexa']??[]);
    if(array_key_exists('radio_sync',$c))return !empty($c['radio_sync']);
    return rrw_radio_theme_active(rrw_alexa_data_dir());
}
/** Skill-Sender aus den Radio-Sendern: [id => Definition]; $skipped = Sender, die Alexa nicht spielen kann (mit Grund). */
function rrw_alexa_radio_defs(array $site,?array &$skipped=null): array {
    $skipped=[];$defs=[];if(!rrw_alexa_radio_sync($site))return [];
    $cfg=rrw_alexa_radio_load();$list=$cfg['stations'];
    usort($list,fn($a,$b)=>($b['id']===$cfg['default'])<=>($a['id']===$cfg['default']));   // Standardsender zuerst
    foreach($list as $st){
        $name=$st['name'];
        if($st['source']==='lautfm'&&$st['lautfm_id']!==''){
            $id=rrw_alexa_id($st['lautfm_id']);if($id===''||isset($defs[$id])){ $skipped[]=['name'=>$name,'reason'=>'Kennung unzulässig oder doppelt'];continue; }
            $defs[$id]=['id'=>$id,'title'=>$name,'names'=>[mb_strtolower($name)],'from_radio'=>true];continue;
        }
        $stream=rrw_alexa_stream_clean($st['stream_url']);
        if($stream===''){ $skipped[]=['name'=>$name,'reason'=>'Alexa spielt nur Streams mit https-Adresse'];continue; }
        $id=rrw_alexa_id($st['id']);if($id===''||isset($defs[$id])){ $skipped[]=['name'=>$name,'reason'=>'Kennung unzulässig oder doppelt'];continue; }
        $defs[$id]=['id'=>$id,'title'=>$name,'names'=>[mb_strtolower($name)],'stream'=>$stream,'radio'=>$st['id'],'from_radio'=>true];
    }
    return $defs;
}
/** Effektive Senderliste: Core-Netzwerk (Katalog als Rückfall), Katalogwerte, CMS-Überschreibungen, Reihenfolge. */
function rrw_alexa_station_defs(array $site): array {
    $cat=rrw_alexa_catalog();$cfg=rrw_alexa_clean($site['alexa']??[]);$bycat=[];
    foreach($cat['stations'] as $s)$bycat[$s['id']]=$s;
    $ids=[];
    $radio=rrw_alexa_radio_defs($site);
    if(rrw_alexa_neutral()){ foreach(array_merge(array_keys($radio),(array)$cfg['order'],array_keys((array)$cfg['stations'])) as $x){$x=rrw_alexa_id($x);if($x!==''&&!in_array($x,$ids,true)&&(isset($radio[$x])||isset(((array)$cfg['stations'])[$x])))$ids[]=$x;} }   // nur Sender, die der Betreiber selbst hinzugefügt hat
    else{
        foreach((array)($site['core_network']['stations']??[]) as $x){$x=rrw_alexa_id($x);if($x!==''&&!in_array($x,$ids,true))$ids[]=$x;}
        if(!$ids)$ids=array_map(fn($s)=>$s['id'],$cat['stations']);
    }
    $over=(array)$cfg['stations'];$defs=[];
    foreach($ids as $id){
        $s=$bycat[$id]??($radio[$id]??null);
        if(!$s){
            $s=['id'=>$id,'title'=>rrw_alexa_pretty($id)];
            if(preg_match('/^([a-z][a-z0-9_-]*?)-?24$/',$id,$m))$s['bases']=rrw_alexa_uniq([str_replace(['-','_'],'',$m[1]),str_replace(['-','_'],' ',$m[1])]);
            else $s['names']=[str_replace(['-','_'],' ',$id)];
        }
        $o=(array)($over[$id]??[]);
        $s['enabled']=!array_key_exists('enabled',$o)||!empty($o['enabled']);
        if(!empty($o['title']))$s['title']=$o['title'];
        $s['extra']=(array)($o['extra']??[]);
        if(!empty($o['stream'])&&empty($s['from_radio']))$s['stream']=$o['stream'];
        $defs[$id]=$s;
    }
    $ordered=[];foreach($cfg['order'] as $id)if(isset($defs[$id])){$ordered[$id]=$defs[$id];unset($defs[$id]);}
    return $ordered+$defs;
}

function rrw_alexa_uniq(array $arr): array {
    $seen=[];$out=[];foreach($arr as $x){$x=trim((string)$x);$k=mb_strtolower($x);if($k===''||isset($seen[$k]))continue;$seen[$k]=1;$out[]=$x;}return $out;
}
/** Slot-Wert eines Senders: Hauptname + Aussprachen (Zahlen als vierundzwanzig, twenty four, 24 ...). */
function rrw_alexa_station_value(array $s,array $suffixes): array {
    $names=[];
    if(!empty($s['bases'])){
        foreach($s['bases'] as $b)foreach($suffixes as $suf)$names[]="$b $suf";
        if(($s['plainBases']??true)!==false)array_push($names,...$s['bases']);
    }
    array_push($names,...(array)($s['names']??[]),...(array)($s['syn']??[]),...(array)($s['extra']??[]));
    $names=rrw_alexa_uniq($names);
    $main=mb_strtolower(!empty($s['bases'])?$s['bases'][0].' '.$suffixes[0]:(($s['names'][0]??null)?:$s['title']));
    $syn=array_values(array_filter($names,fn($n)=>mb_strtolower($n)!==$main));
    return ['id'=>$s['id'],'name'=>['value'=>$main,'synonyms'=>$syn]];
}

const RRW_ALEXA_DAYS=['today'=>['heute'],'tomorrow'=>['morgen'],'dayafter'=>['übermorgen'],'mon'=>['montag'],'tue'=>['dienstag'],'wed'=>['mittwoch'],'thu'=>['donnerstag'],'fri'=>['freitag'],'sat'=>['samstag'],'sun'=>['sonntag']];

/** Sprachmodell de-DE aus der Senderliste; Doppelte Schreibweisen werden entfernt (der erste Sender behält sie). */
function rrw_alexa_model(array $site,?array &$warnings=null): array {
    $cat=rrw_alexa_catalog();$warnings=[];
    $values=[];$seen=[];
    foreach(rrw_alexa_station_defs($site) as $s){
        $v=rrw_alexa_station_value($s,$cat['suffixes']);
        $keep=[];foreach($v['name']['synonyms'] as $n){$k=mb_strtolower($n);if(isset($seen[$k])){$warnings[]="„$n“ gehört schon zu {$seen[$k]} und wurde bei {$s['id']} weggelassen.";continue;}$seen[$k]=$s['id'];$keep[]=$n;}
        if(!isset($seen[mb_strtolower($v['name']['value'])]))$seen[mb_strtolower($v['name']['value'])]=$s['id'];
        $v['name']['synonyms']=$keep;$values[]=$v;
    }
    $brands=[];foreach($cat['brands'] as $b){$n=rrw_alexa_uniq(array_merge($b['names']??[],$b['syn']??[]));$brands[]=['id'=>$b['id'],'name'=>['value'=>$n[0],'synonyms'=>array_slice($n,1)]];}
    $days=[];foreach(RRW_ALEXA_DAYS as $id=>$n)$days[]=['id'=>$id,'name'=>['value'=>$n[0],'synonyms'=>[]]];
    $verbs=['spiele','spiel','starte','höre','hör'];
    $play=[];foreach($verbs as $v)$play[]="$v {sender}";
    array_push($play,'spiele {sender} ab','spiel {sender} ab','spiele bitte {sender}','spiele den sender {sender}','mach {sender} an','schalte {sender} ein','schalte auf {sender}','wechsle zu {sender}','wechsle auf {sender}','ich möchte {sender} hören','ich will {sender} hören','ich möchte gerne {sender} hören','{sender}','{sender} bitte','{sender} hören');
    foreach(['von','bei','auf','mit'] as $p)array_push($play,"spiele {sender} $p {marke}","spiel {sender} $p {marke}","starte {sender} $p {marke}");
    $play[]='ich möchte {sender} von {marke} hören';
    $brand=[];foreach($verbs as $v)$brand[]="$v {marke}";
    array_push($brand,'mach {marke} an','schalte {marke} ein','ich möchte {marke} hören','{marke} hören','ich will {marke} hören');
    $sender=[['name'=>'sender','type'=>'SENDER_TYP']];$both=[['name'=>'sender','type'=>'SENDER_TYP'],['name'=>'marke','type'=>'MARKE_TYP']];
    $built=['Cancel','Help','Stop','Pause','Resume','Next','Previous','StartOver','Repeat','LoopOff','LoopOn','ShuffleOff','ShuffleOn','NavigateHome','Fallback'];
    $intents=[];foreach($built as $b)$intents[]=['name'=>"AMAZON.{$b}Intent",'samples'=>[]];
    array_push($intents,
        ['name'=>'PlayStationIntent','slots'=>$both,'samples'=>$play],
        ['name'=>'PlayBrandIntent','slots'=>[['name'=>'marke','type'=>'MARKE_TYP']],'samples'=>$brand],
        ['name'=>'NowPlayingIntent','slots'=>$sender,'samples'=>['was läuft gerade','was läuft','was spielt gerade','was spielt ihr gerade','welcher song läuft gerade','welches lied läuft gerade','welcher titel läuft gerade','wer singt das','wer singt das lied','wie heißt das lied','wie heißt der song','was ist das für ein lied','was läuft gerade auf {sender}','was läuft auf {sender}']],
        ['name'=>'CurrentShowIntent','slots'=>$sender,'samples'=>['welche sendung läuft gerade','welche sendung läuft','welche sendung ist gerade dran','welche show läuft gerade','was ist das für eine sendung','wer moderiert gerade','wer moderiert jetzt','welche sendung läuft gerade auf {sender}','welche sendung läuft auf {sender}']],
        ['name'=>'NextShowIntent','slots'=>$sender,'samples'=>['was kommt als nächstes','was kommt danach','welche sendung kommt als nächstes','welche sendung kommt danach','was läuft als nächstes','wie geht es weiter','was kommt als nächstes auf {sender}','welche sendung kommt als nächstes auf {sender}']],
        ['name'=>'ScheduleIntent','slots'=>[['name'=>'sender','type'=>'SENDER_TYP'],['name'=>'tag','type'=>'TAG_TYP']],'samples'=>['wie ist der sendeplan','was ist der sendeplan','sendeplan','sendeplan {tag}','sendeplan am {tag}','wie ist der sendeplan {tag}','wie ist der sendeplan am {tag}','wie ist der sendeplan von {sender}','sendeplan von {sender}','sendeplan von {sender} {tag}','was läuft {tag}','was läuft am {tag}','was läuft {tag} auf {sender}','welche sendungen gibt es {tag}','welche sendungen gibt es am {tag}','welche sendungen laufen {tag}','welche sendungen gibt es']],
        ['name'=>'ListStationsIntent','samples'=>['welche sender gibt es','welche sender hast du','welche sender kann ich hören','welche sender habt ihr','nenne mir die sender','liste die sender auf','sender auflisten','welche radiosender gibt es']]
    );
    return ['interactionModel'=>['languageModel'=>['invocationName'=>$cat['brand']['invocationName'],'intents'=>$intents,'types'=>[
        ['name'=>'SENDER_TYP','values'=>$values],['name'=>'MARKE_TYP','values'=>$brands],['name'=>'TAG_TYP','values'=>$days]
    ]]]];
}

function rrw_alexa_token(string $dataDir,bool $reset=false): string {
    $f=rrw_alexa_dir($dataDir).'/token.txt';$t=is_file($f)?trim((string)@file_get_contents($f)):'';
    if($reset||strlen($t)<32){$t=bin2hex(random_bytes(24));@file_put_contents($f,$t);@chmod($f,0640);}
    return $t;
}

/** Öffentliche Konfiguration für den Skill (keine Geheimnisse). */
function rrw_alexa_public(array $site,string $dataDir,string $origin): array {
    $cat=rrw_alexa_catalog();$cfg=rrw_alexa_clean($site['alexa']??[]);$defs=rrw_alexa_station_defs($site);
    $stations=[];$enabled=[];
    $hasRadio=false;
    foreach($defs as $id=>$s){$stations[]=['id'=>$id,'title'=>$s['title'],'enabled'=>$s['enabled']]+(!empty($s['stream'])?['stream'=>$s['stream']]:[])+(!empty($s['radio'])?['radio'=>$s['radio']]:[]);if(!empty($s['radio']))$hasRadio=true;if($s['enabled'])$enabled[]=$id;}
    $default=in_array($cfg['default_station'],$enabled,true)?$cfg['default_station']:($enabled[0]??$cfg['default_station']);
    if($cfg['daily']&&$enabled)$default=$enabled[(int)date('z')%count($enabled)];
    $out=['status'=>'ok','enabled'=>$cfg['enabled'],'maintenance'=>$cfg['maintenance']['enabled']?$cfg['maintenance']['text']:null,
        'default'=>$default,'stations'=>$stations,'texts'=>$cfg['texts'],'schedule'=>$cfg['schedule'],'stats'=>$cfg['stats'],
        'stream_url'=>$cat['brand']['streamUrl'],'api_base'=>$cat['brand']['apiBase'],'art'=>rtrim($origin,'/').'/icon-512.png','name'=>$cat['brand']['name']];
    // Marke → Sender (für „Spiele <Marke>“): Marken mit dem Standardsender spielen den aktuellen Standard, andere ihren eigenen Sender
    $bm=[];foreach($cat['brands'] as $b)$bm[$b['id']]=($b['station']===($cat['brand']['defaultStation']??''))?$default:$b['station'];$out['brand_map']=$bm;
    if(rrw_alexa_neutral()){ $bm=[];foreach($cat['brands'] as $b)$bm[$b['id']]=$default;$out['brand_map']=$bm;$ic=(string)($site['branding']['portal_icon']??'');$out['art']=preg_match('~^/~',$ic)?rtrim($origin,'/').$ic:(preg_match('~^https://~',$ic)?$ic:''); }
    if($hasRadio&&str_starts_with($origin,'https://'))$out['radio_api']=rtrim($origin,'/').'/cms/radio.php';   // Der Skill läuft bei Amazon und ruft nur https auf
    $out['rev']=substr(md5(json_encode($out)),0,10);
    return $out;
}
function rrw_alexa_note_fetch(string $dataDir): void {
    $f=rrw_alexa_dir($dataDir).'/last_fetch.json';$d=is_file($f)?(json_decode((string)@file_get_contents($f),true)?:[]):[];
    // höchstens alle 30 s schreiben
    if(($d['t']??0)>time()-30)return;
    @file_put_contents($f,json_encode(['t'=>time(),'n'=>(int)($d['n']??0)+1]));
}
function rrw_alexa_last_fetch(string $dataDir): int { $f=rrw_alexa_dir($dataDir).'/last_fetch.json';$d=is_file($f)?(json_decode((string)@file_get_contents($f),true)?:[]):[];return (int)($d['t']??0); }

// ---------- Zähler (anonym: nur Sender und Befehlsnamen, keine Geräte-/Nutzer-IDs) ----------
function rrw_alexa_stat_add(string $dataDir,string $event,string $station,string $intent): bool {
    $station=rrw_alexa_id($station);$intent=preg_match('/^[A-Za-z.]{3,40}$/',$intent)?$intent:'';
    if(!in_array($event,['play','intent'],true))return false;
    $today=gmdate('Y-m-d');$cut=gmdate('Y-m-d',time()-90*86400);
    return rrw_apps_rmw(rrw_alexa_dir($dataDir).'/stats.json',function(array $d) use($event,$station,$intent,$today,$cut){
        $day=&$d['days'][$today];$day=$day??['plays'=>[],'intents'=>[]];
        if($event==='play'&&$station!=='')$day['plays'][$station]=($day['plays'][$station]??0)+1;
        if($event==='intent'&&$intent!=='')$day['intents'][$intent]=($day['intents'][$intent]??0)+1;
        unset($day);foreach(array_keys($d['days']) as $k)if($k<$cut)unset($d['days'][$k]);
        return $d;
    });
}
function rrw_alexa_stats(string $dataDir): array {
    $f=rrw_alexa_dir($dataDir).'/stats.json';$d=is_file($f)?(json_decode((string)@file_get_contents($f),true)?:[]):[];
    $t30=gmdate('Y-m-d',time()-29*86400);$plays=[];$intents=[];$daily=[];
    foreach((array)($d['days']??[]) as $date=>$day){
        if($date<$t30)continue;$n=0;
        foreach((array)($day['plays']??[]) as $k=>$c){$plays[$k]=($plays[$k]??0)+$c;$n+=$c;}
        foreach((array)($day['intents']??[]) as $k=>$c)$intents[$k]=($intents[$k]??0)+$c;
        $daily[]=['date'=>$date,'plays'=>$n];
    }
    arsort($plays);arsort($intents);usort($daily,fn($a,$b)=>strcmp($a['date'],$b['date']));
    return ['plays'=>$plays,'intents'=>$intents,'daily'=>$daily,'total'=>array_sum($plays)];
}
function rrw_alexa_stats_clear(string $dataDir): void { @unlink(rrw_alexa_dir($dataDir).'/stats.json'); }

// ---------- Skill-Paket (ZIP) ----------
/** Platzhalter in Vorlagen ersetzen ({{SENDER}} Sendernamen, im Baukasten-Modus zusätzlich Name, Aufrufname, Adresse, Beispielsender). */
function rrw_alexa_fill(string $tpl,array $site,string $origin=''): string {
    $titles=[];$first='';foreach(rrw_alexa_station_defs($site) as $s)if($s['enabled']){$titles[]=$s['title'];if($first==='')$first=$s['title'];}
    $cat=rrw_alexa_catalog();
    return strtr($tpl,['{{SENDER}}'=>implode(', ',$titles),'{{NAME}}'=>(string)($cat['brand']['name']??''),'{{INVOCATION}}'=>(string)($cat['brand']['invocationName']??''),'{{ORIGIN}}'=>rtrim($origin,'/'),'{{EXAMPLE}}'=>$first!==''?$first:'einen Sender']);
}
function rrw_alexa_manifest(array $site,string $origin=''): array {
    $raw=(string)file_get_contents(rrw_alexa_skill_dir().'/skill.json');
    // Werte JSON-sicher einsetzen: erst entschlüsseln, dann in den Zeichenketten ersetzen
    $m=json_decode($raw,true);
    $fill=function($v) use(&$fill,$site,$origin){ return is_array($v)?array_map($fill,$v):(is_string($v)?rrw_alexa_fill($v,$site,$origin):$v); };
    return $fill($m);
}
function rrw_alexa_package_files(array $site,string $root,string $origin,string $dataDir,?array &$warnings=null): array {
    $json=fn($x)=>json_encode($x,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
    $dir=rrw_alexa_skill_dir();$files=[];
    $files['skill-package/skill.json']=$json(rrw_alexa_manifest($site,$origin));
    $files['skill-package/interactionModels/custom/de-DE.json']=$json(rrw_alexa_model($site,$warnings));
    $files['lambda/index.js']=(string)file_get_contents($dir.'/lambda/index.js');
    $files['lambda/package.json']=(string)file_get_contents($dir.'/lambda/package.json');
    $pub=rrw_alexa_public($site,$dataDir,$origin);
    $files['lambda/fallback.json']=$json($pub);
    $files['lambda/cms.json']=$json(['base'=>rtrim($origin,'/'),'token'=>rrw_alexa_token($dataDir)]);
    $files['README.md']=rrw_alexa_fill((string)file_get_contents($dir.'/README.md'),$site,$origin);
    $files['listing-de.md']=rrw_alexa_fill((string)file_get_contents($dir.'/listing-de.md'),$site,$origin);
    foreach(['icon-108.png','icon-512.png'] as $ic){$p=$root.'/assets/img/alexa/'.$ic;if(is_file($p))$files['icons/'.$ic]=(string)file_get_contents($p);}
    return $files;
}
function rrw_alexa_zip(array $files): string {
    if(!class_exists('ZipArchive'))throw new RuntimeException('ZIP-Unterstützung ist auf dem Server nicht verfügbar');
    $tmp=tempnam(sys_get_temp_dir(),'alexa');$zip=new ZipArchive();
    if($zip->open($tmp,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('ZIP konnte nicht erstellt werden');
    $dirName=rrw_alexa_neutral()?(trim((string)preg_replace('/[^a-z0-9]+/','-',strtolower(rrw_alexa_app_name(rrw_alexa_site()))),'-')?:'radio').'-skill':'ricorewi-radio-skill';
    foreach($files as $name=>$content)$zip->addFromString($dirName.'/'.$name,$content);
    $zip->close();return $tmp;
}

// Hat sich das Sprachmodell seit dem letzten Download geändert? (dann muss es bei Amazon neu eingespielt werden)
function rrw_alexa_model_rev(array $site): string { return substr(md5(json_encode(rrw_alexa_model($site))),0,12); }
function rrw_alexa_exported_rev(string $dataDir): string { $f=rrw_alexa_dir($dataDir).'/exported.txt';return is_file($f)?trim((string)@file_get_contents($f)):''; }
function rrw_alexa_mark_exported(string $dataDir,string $rev): void { @file_put_contents(rrw_alexa_dir($dataDir).'/exported.txt',$rev); }

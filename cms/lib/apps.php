<?php
// Apps verwalten (CMS): Übersicht der gebauten Apps, Funktionsschalter je Marke/Plattform, Hinweise und Mindestversion.
// Die nativen Apps fragen beim Start die öffentliche Schnittstelle app_config ab (Marke ergibt sich aus der Domain).

const RRW_APPS_PLATFORMS=['android'=>'Android','windows'=>'Windows'];
const RRW_APPS_FEATURES=['directory'=>'Radioverzeichnis (Suche, Fremd-Streams)','assistant'=>'KI-Assistent','report'=>'Sender melden','cast'=>'Cast (auf Lautsprecher/TV übertragen, nur Android)'];

// Bausteine des App-Builders (feste Auswahl: die Apps rendern diese nativ, es wird nie Code oder HTML aus dem CMS ausgeführt)
const RRW_APPS_TILES=['favorites'=>'Favoriten','schedule'=>'Sendeplan','podcast'=>'Podcast','community'=>'Mitmachen','news'=>'News & Magazin','assistant'=>'KI-Assistent','directory'=>'Radioverzeichnis','shops'=>'Shops','help'=>'Hilfe','link'=>'Eigener Link'];
const RRW_APPS_BLOCKS=['hero'=>'Willkommens-Karte','tiles'=>'Kacheln','stations'=>'Senderliste','text'=>'Text-Karte','link'=>'Link-Karte'];
// Einträge im „Mehr“-Menü der Android-App, die ausgeblendet werden dürfen (Datenschutz, Impressum, App-Info, Beenden bleiben immer)
const RRW_APPS_MENU=['podcast'=>'Podcast','news'=>'News & Magazin','help'=>'Hilfe & Bedienung','shops'=>'Shops','assistant'=>'KI-Assistent','anmacha'=>'anmacha.de','portal'=>'Radioportal'];

function rrw_apps_key(string $brand,string $platform): string { return $brand.':'.$platform; }

function rrw_apps_entry_defaults(): array {
    return ['features'=>['directory'=>true,'assistant'=>true,'report'=>true,'cast'=>true],
            'notice'=>['enabled'=>false,'level'=>'info','title'=>'','text'=>'','url'=>'','url_label'=>''],
            'min_version'=>'',
            'maintenance'=>['enabled'=>false,'title'=>'','text'=>''],
            'rollout'=>100,
            'blocked'=>[],      // gesperrte Versionen: Apps in genau diesen Versionen müssen aktualisieren
            'notes'=>'',        // "Was ist neu": Text im Update-Dialog
            'cert_pin'=>'',     // SHA-256 des erwarteten Signaturzertifikats (nur Android); weicht das veröffentlichte Paket ab, wird kein Update angeboten
            'builder'=>rrw_apps_builder_defaults()];
}
function rrw_apps_builder_defaults(): array {
    return ['enabled'=>false,'theme'=>['accent'=>'','hero_from'=>'','hero_to'=>''],'home'=>[],'stations'=>['order'=>[],'hidden'=>[]],'more_menu'=>['hide'=>[],'custom'=>[]]];
}
function rrw_apps_color_clean($c): string { $c=strtolower(trim((string)$c));return preg_match('/^#[0-9a-f]{6}$/',$c)?$c:''; }
function rrw_apps_text($v,int $max): string { return mb_substr(trim(strip_tags((string)$v)),0,$max); }
/** Eigene Sender der App: Kennung, Name und https-Stream (Pflicht), optional Logo (https). Doppelte Kennungen entfallen. */
function rrw_apps_custom_stations($list,int $max=20): array {
    $out=[];$seen=[];
    foreach(array_slice((array)$list,0,$max*2) as $c){
        if(!is_array($c))continue;
        $title=rrw_apps_text($c['title']??'',40);$stream=rrw_apps_url_clean((string)($c['stream']??''));
        $id=strtolower(trim((string)($c['id']??'')));if($id==='')$id=trim((string)preg_replace('/[^a-z0-9]+/','-',strtolower($title)),'-');
        $id=substr($id,0,40);
        if($title===''||$stream===''||!preg_match('/^[a-z0-9][a-z0-9_-]{1,62}$/',$id)||isset($seen[$id]))continue;
        $seen[$id]=1;$row=['id'=>$id,'title'=>$title,'stream'=>$stream];
        $logo=rrw_apps_url_clean((string)($c['logo']??''));if($logo!=='')$row['logo']=$logo;
        $out[]=$row;if(count($out)>=$max)break;
    }
    return $out;
}
function rrw_apps_station_ids($list,int $max=60): array {
    $out=[];foreach(array_slice((array)$list,0,$max) as $x){$x=strtolower(trim((string)$x));if(preg_match('/^[a-z0-9][a-z0-9_-]{1,62}$/',$x)&&!in_array($x,$out,true))$out[]=$x;}
    return $out;
}
// App-Builder bereinigen: feste Bausteine, begrenzte Längen, Links nur https
function rrw_apps_builder_clean($b): array {
    $b=is_array($b)?$b:[];$d=rrw_apps_builder_defaults();$th=(array)($b['theme']??[]);
    $out=['enabled'=>!empty($b['enabled']),'theme'=>['accent'=>rrw_apps_color_clean($th['accent']??''),'hero_from'=>rrw_apps_color_clean($th['hero_from']??''),'hero_to'=>rrw_apps_color_clean($th['hero_to']??'')],'home'=>[],'stations'=>$d['stations'],'more_menu'=>$d['more_menu']];
    foreach(array_slice((array)($b['home']??[]),0,14) as $blk){
        if(!is_array($blk))continue;$t=(string)($blk['type']??'');if(!isset(RRW_APPS_BLOCKS[$t]))continue;
        if($t==='hero')$out['home'][]=['type'=>'hero','eyebrow'=>rrw_apps_text($blk['eyebrow']??'',80),'title'=>rrw_apps_text($blk['title']??'',160),'text'=>rrw_apps_text($blk['text']??'',600)];
        elseif($t==='tiles'){
            $tiles=[];foreach(array_slice((array)($blk['tiles']??[]),0,8) as $tl){
                if(!is_array($tl)||!isset(RRW_APPS_TILES[(string)($tl['id']??'')]))continue;$id=(string)$tl['id'];$url=rrw_apps_url_clean((string)($tl['url']??''));
                if($id==='link'&&$url==='')continue;
                $tiles[]=['id'=>$id,'title'=>rrw_apps_text($tl['title']??'',40),'sub'=>rrw_apps_text($tl['sub']??'',60),'url'=>$id==='link'?$url:''];
            }
            $out['home'][]=['type'=>'tiles','title'=>rrw_apps_text($blk['title']??'',60),'tiles'=>$tiles];
        }
        elseif($t==='stations')$out['home'][]=['type'=>'stations','title'=>rrw_apps_text($blk['title']??'',60),'limit'=>max(0,min(40,(int)($blk['limit']??0)))];
        elseif($t==='text')$out['home'][]=['type'=>'text','title'=>rrw_apps_text($blk['title']??'',80),'text'=>rrw_apps_text($blk['text']??'',600)];
        elseif($t==='link')$out['home'][]=['type'=>'link','title'=>rrw_apps_text($blk['title']??'',80),'text'=>rrw_apps_text($blk['text']??'',400),'url'=>rrw_apps_url_clean((string)($blk['url']??'')),'label'=>rrw_apps_text($blk['label']??'',40)];
    }
    $st=(array)($b['stations']??[]);$out['stations']=['order'=>rrw_apps_station_ids($st['order']??[]),'hidden'=>rrw_apps_station_ids($st['hidden']??[])];
    // Eigene Sender (beliebige https-Streams neben dem Core-Netzwerk); der Schlüssel fehlt, solange keine angelegt sind
    $custom=rrw_apps_custom_stations($st['custom']??[]);if($custom)$out['stations']['custom']=$custom;
    $mm=(array)($b['more_menu']??[]);$hide=[];foreach((array)($mm['hide']??[]) as $k)if(isset(RRW_APPS_MENU[(string)$k])&&!in_array((string)$k,$hide,true))$hide[]=(string)$k;
    $custom=[];foreach(array_slice((array)($mm['custom']??[]),0,6) as $c){if(!is_array($c))continue;$u=rrw_apps_url_clean((string)($c['url']??''));$t=rrw_apps_text($c['title']??'',40);if($u===''||$t==='')continue;$custom[]=['title'=>$t,'sub'=>rrw_apps_text($c['sub']??'',60),'url'=>$u];}
    $out['more_menu']=['hide'=>$hide,'custom'=>$custom];
    return $out;
}
function rrw_apps_version_clean(string $v): string { return preg_match('/^\d{1,4}(\.\d{1,4}){0,3}/',trim($v),$m)?$m[0]:''; }
function rrw_apps_url_clean(string $u): string { $u=trim($u);return preg_match('~^https://[^\s"\'<>]{3,300}$~i',$u)?$u:''; }

function rrw_apps_hex_clean($v,int $len=64): string { $v=strtolower(preg_replace('/[^a-f0-9]/i','',(string)$v));return strlen($v)===$len?$v:''; }
function rrw_apps_versions_clean($v,int $max=20): array {
    if(is_string($v))$v=preg_split('/[\s,;]+/',$v,-1,PREG_SPLIT_NO_EMPTY);
    $out=[];foreach(array_slice((array)$v,0,100) as $x){$c=rrw_apps_version_clean((string)$x);if($c!==''&&!in_array($c,$out,true))$out[]=$c;if(count($out)>=$max)break;}
    return $out;
}

// Speichern: nur bekannte Schlüssel, Texte begrenzt (Ausgabe in den Apps erfolgt als reiner Text)
function rrw_apps_clean($value): array {
    $value=is_array($value)?$value:[];
    $tm=(array)($value['telemetry']??[]);
    $out=['android_enabled'=>!empty($value['android_enabled']),'windows_enabled'=>!empty($value['windows_enabled']),
          'telemetry'=>['usage'=>!empty($tm['usage']),'errors'=>!empty($tm['errors']),'listen'=>!empty($tm['listen']),'geo'=>!array_key_exists('geo',$tm)||!empty($tm['geo'])],'managed'=>[]];
    foreach(array_slice((array)($value['managed']??[]),0,40,true) as $k=>$e){
        if(!is_string($k)||!preg_match('/^([a-z0-9_-]{1,40}):(android|windows)$/',$k)||!is_array($e))continue;
        $d=rrw_apps_entry_defaults();$f=(array)($e['features']??[]);$n=(array)($e['notice']??[]);
        $mt=(array)($e['maintenance']??[]);
        $row=['features'=>[],'notice'=>[],'min_version'=>rrw_apps_version_clean((string)($e['min_version']??'')),
              'maintenance'=>['enabled'=>!empty($mt['enabled']),'title'=>rrw_apps_text($mt['title']??'',80),'text'=>rrw_apps_text($mt['text']??'',600)],
              'rollout'=>max(0,min(100,(int)($e['rollout']??100))),
              'blocked'=>rrw_apps_versions_clean($e['blocked']??[]),'notes'=>rrw_apps_text($e['notes']??'',600),'cert_pin'=>rrw_apps_hex_clean($e['cert_pin']??''),
              'builder'=>rrw_apps_builder_clean($e['builder']??[])];
        foreach($d['features'] as $fk=>$def)$row['features'][$fk]=array_key_exists($fk,$f)?!empty($f[$fk]):$def;
        $row['notice']=[
            'enabled'=>!empty($n['enabled']),
            'level'=>in_array(($n['level']??''),['info','warn'],true)?(string)$n['level']:'info',
            'title'=>mb_substr(trim(strip_tags((string)($n['title']??''))),0,80),
            'text'=>mb_substr(trim(strip_tags((string)($n['text']??''))),0,600),
            'url'=>rrw_apps_url_clean((string)($n['url']??'')),
            'url_label'=>mb_substr(trim(strip_tags((string)($n['url_label']??''))),0,40),
        ];
        $out['managed'][$k]=$row;
    }
    return $out;
}
function rrw_apps_entry(array $site,string $brand,string $platform): array {
    $d=rrw_apps_entry_defaults();$e=(array)($site['apps']['managed'][rrw_apps_key($brand,$platform)]??[]);
    return ['features'=>array_merge($d['features'],array_map('boolval',(array)($e['features']??[]))),
            'notice'=>array_merge($d['notice'],(array)($e['notice']??[])),
            'min_version'=>rrw_apps_version_clean((string)($e['min_version']??'')),
            'maintenance'=>array_merge($d['maintenance'],(array)($e['maintenance']??[])),
            'rollout'=>array_key_exists('rollout',$e)?max(0,min(100,(int)$e['rollout'])):100,
            'blocked'=>rrw_apps_versions_clean($e['blocked']??[]),'notes'=>rrw_apps_text($e['notes']??'',600),'cert_pin'=>rrw_apps_hex_clean($e['cert_pin']??''),
            'builder'=>array_key_exists('builder',$e)?rrw_apps_builder_clean($e['builder']):$d['builder']];
}

// "1.9.0-dev" -> [1,9,0]; Vergleich nur über die Zahlen
function rrw_apps_vparts(string $v): array { $v=rrw_apps_version_clean($v);return $v===''?[]:array_map('intval',explode('.',$v)); }
function rrw_apps_vcmp(string $a,string $b): int {
    $x=rrw_apps_vparts($a);$y=rrw_apps_vparts($b);$n=max(count($x),count($y),3);
    for($i=0;$i<$n;$i++){$p=$x[$i]??0;$q=$y[$i]??0;if($p!==$q)return $p<=>$q;}
    return 0;
}

// Prüfsumme der Datei auf dem Server wirklich nachrechnen (bisher stand nur die Angabe aus dem Build in der Datenbeschreibung).
// Zwischengespeichert je Name+Größe+Änderungszeit, damit nicht bei jedem Aufruf große Dateien gelesen werden.
function rrw_apps_sha_check(string $path,string $expected): ?bool {
    $expected=strtolower(trim($expected));
    if(!preg_match('/^[a-f0-9]{64}$/',$expected)||!is_file($path))return null;       // unbekannt: keine Prüfsumme angegeben
    $cf=rrw_apps_dir(dirname(__DIR__).'/data').'/hashcache.json';$cache=is_file($cf)?(json_decode((string)@file_get_contents($cf),true)?:[]):[];
    $key=basename($path);$sig=filesize($path).':'.filemtime($path);
    if(!isset($cache[$key])||($cache[$key]['sig']??'')!==$sig){
        $h=@hash_file('sha256',$path);if($h===false)return false;
        $cache[$key]=['sig'=>$sig,'sha'=>$h];@file_put_contents($cf,json_encode($cache),LOCK_EX);
    }
    return hash_equals((string)$cache[$key]['sha'],$expected);
}

// Metadaten der zuletzt gebauten App aus downloads/*.json (vom Build erzeugt) plus Prüfung, ob die Dateien auf dem Server liegen
function rrw_apps_meta(string $root,string $brandId,string $default,string $platform): array {
    $suffix=$brandId===$default?'':'-'.$brandId;
    $jf=$root.'/downloads/'.$platform.'-latest'.$suffix.'.json';
    $d=is_file($jf)?json_decode((string)@file_get_contents($jf),true):null;
    $out=['available'=>false,'version'=>'','built_at'=>'','files'=>[]];
    if(!is_array($d))return $out;
    $out['available']=true;$out['version']=(string)($d['version']??'');$out['built_at']=(string)($d['built_at']??'');
    if($platform==='android'){
        $out['package']=(string)($d['package']??'');$out['version_code']=(int)($d['version_code']??0);$out['signing']=in_array(($d['signing']??''),['stable','debug'],true)?(string)$d['signing']:'';
        $out['cert_sha256']=rrw_apps_hex_clean($d['cert_sha256']??'');
        $files=[[ 'label'=>'APK','name'=>(string)($d['filename']??''),'size'=>(int)($d['size_bytes']??0),'sha256'=>(string)($d['sha256']??'')]];
    }else{
        $files=[
            ['label'=>'Installer','name'=>(string)($d['setup_filename']??''),'size'=>(int)($d['setup_size_bytes']??0),'sha256'=>(string)($d['setup_sha256']??'')],
            ['label'=>'Portable','name'=>(string)($d['portable_filename']??''),'size'=>(int)($d['portable_size_bytes']??0),'sha256'=>(string)($d['portable_sha256']??'')],
        ];
    }
    foreach($files as $f){
        $name=basename($f['name']);if($name===''||$name==='.'||$name==='..')continue;
        $p=$root.'/downloads/'.$name;$exists=is_file($p);
        $sizeOk=$exists&&($f['size']<=0||filesize($p)===$f['size']);
        $out['files'][]=['label'=>$f['label'],'name'=>$name,'size'=>$f['size'],'sha256'=>$f['sha256'],'exists'=>$exists,'size_ok'=>$sizeOk,
            'sha_ok'=>$sizeOk?rrw_apps_sha_check($p,(string)$f['sha256']):false,'url'=>'/downloads/'.rawurlencode($name)];
    }
    return $out;
}

// Darf die Datei als Update angeboten werden? Nur wenn sie da ist, in Größe und Prüfsumme zum Build passt und (Android) mit dem
// stabilen, erwarteten Schlüssel signiert ist. Sonst scheitert die Installation oder sie wäre manipuliert.
function rrw_apps_offer_ok(array $m,array $e,string $platform): bool {
    $f=$m['files'][0]??null;
    if(!$f||empty($f['exists'])||empty($f['size_ok'])||$f['sha_ok']===false||strlen((string)$f['sha256'])!==64)return false;
    if($platform==='android'){
        if(($m['signing']??'')!=='stable')return false;
        $pin=(string)($e['cert_pin']??'');if($pin!==''&&($m['cert_sha256']??'')!==$pin)return false;
    }
    return true;
}
// Probleme, die der Verwalter sehen muss (Lücken schließen, bevor Nutzer sie spüren)
function rrw_apps_health(array $m,array $e,string $platform): array {
    $h=[];$add=function(string $lv,string $code,string $t) use(&$h){$h[]=['level'=>$lv,'code'=>$code,'text'=>$t];};
    if(empty($m['available'])){$add('info','no_build','Noch keine App gebaut: keine Downloads und keine Updates.');return $h;}
    foreach(($m['files']??[]) as $f){
        if(empty($f['exists']))$add('error','file_missing',$f['label'].' fehlt auf dem Server ('.$f['name'].').');
        elseif(empty($f['size_ok']))$add('error','size',$f['label'].': Größe weicht vom Build ab, Datei unvollständig oder ersetzt.');
        elseif($f['sha_ok']===false)$add('error','sha',$f['label'].': Prüfsumme stimmt nicht mit dem Build überein. Datei beschädigt oder verändert – wird nicht als Update angeboten.');
        elseif($f['sha_ok']===null)$add('warn','sha_unknown',$f['label'].': Keine Prüfsumme im Build, Echtheit nicht prüfbar.');
    }
    if($platform==='android'){
        if(($m['signing']??'')==='debug')$add('error','debug_sign','Mit Debug-Schlüssel signiert: Updates über bestehende Installationen schlagen fehl, kein Selbst-Update.');
        elseif(($m['signing']??'')!=='stable')$add('warn','sign_unknown','Signaturart unbekannt (älterer Build).');
        $pin=(string)($e['cert_pin']??'');$cert=(string)($m['cert_sha256']??'');
        if($pin!==''&&$cert!==''&&$pin!==$cert)$add('error','cert_changed','Das Signaturzertifikat des Builds weicht vom festgelegten ab. Installierte Apps könnten sich nicht aktualisieren (oder es wurde ein falscher Schlüssel benutzt). Update wird nicht angeboten.');
        elseif($pin==='' && $cert!=='')$add('warn','cert_unpinned','Zertifikat noch nicht festgelegt: ein Schlüsselwechsel würde nicht auffallen.');
        elseif($cert==='' )$add('warn','cert_unknown','Der Build meldet kein Zertifikat (älterer Build).');
    }
    if($e['min_version']!==''&&rrw_apps_version_clean((string)$m['version'])!==''&&rrw_apps_vcmp((string)$m['version'],$e['min_version'])<0)
        $add('error','min_above_latest','Die Mindestversion ('.$e['min_version'].') ist höher als die neueste Version ('.$m['version'].'). Sie wird ignoriert, damit niemand ausgesperrt wird.');
    return $h;
}

// Kurze Zusammenfassung einer Änderung für das Aktivitätsprotokoll (wer hat wann was an Apps/Alexa geändert)
function rrw_apps_change_summary(string $section,$old,$new): string {
    $old=is_array($old)?$old:[];$new=is_array($new)?$new:[];$parts=[];
    $yn=fn($v)=>$v?'an':'aus';
    if($section==='apps'){
        foreach(['android_enabled'=>'Android sichtbar','windows_enabled'=>'Windows sichtbar'] as $k=>$l)if(!empty($old[$k])!==!empty($new[$k]))$parts[]=$l.': '.$yn(!empty($new[$k]));
        if(json_encode($old['telemetry']??[])!==json_encode($new['telemetry']??[]))$parts[]='Datenschutz-Schalter geändert';
        $om=(array)($old['managed']??[]);$nm=(array)($new['managed']??[]);
        foreach(array_unique(array_merge(array_keys($om),array_keys($nm))) as $k){
            $a=array_replace_recursive(rrw_apps_entry_defaults(),(array)($om[$k]??[]));$b=array_replace_recursive(rrw_apps_entry_defaults(),(array)($nm[$k]??[]));$a['builder']=rrw_apps_builder_clean($a['builder']??[]);$b['builder']=rrw_apps_builder_clean($b['builder']??[]);$d=[];
            if(($a['rollout']??100)!==($b['rollout']??100))$d[]='Rollout '.($a['rollout']??100).' % → '.($b['rollout']??100).' %';
            if(($a['min_version']??'')!==($b['min_version']??''))$d[]='Mindestversion „'.($a['min_version']??'').'“ → „'.($b['min_version']??'').'“';
            if(!empty($a['maintenance']['enabled'])!==!empty($b['maintenance']['enabled']))$d[]='Wartungsmodus '.$yn(!empty($b['maintenance']['enabled']));
            if(!empty($a['notice']['enabled'])!==!empty($b['notice']['enabled'])||($a['notice']['text']??'')!==($b['notice']['text']??''))$d[]='Hinweis geändert';
            if(json_encode($a['features']??[])!==json_encode($b['features']??[]))$d[]='Funktionen geändert';
            if(json_encode($a['blocked']??[])!==json_encode($b['blocked']??[]))$d[]='gesperrte Versionen: '.(implode(', ',(array)($b['blocked']??[]))?:'keine');
            if(($a['cert_pin']??'')!==($b['cert_pin']??''))$d[]='Zertifikat-Pin '.(($b['cert_pin']??'')!==''?'gesetzt':'entfernt');
            if(($a['notes']??'')!==($b['notes']??''))$d[]='Update-Text geändert';
            if(json_encode($a['builder']??[])!==json_encode($b['builder']??[]))$d[]='App-Builder geändert';
            if($d)$parts[]=$k.': '.implode(', ',$d);
        }
    }else{
        foreach(array_unique(array_merge(array_keys($old),array_keys($new))) as $k)if(json_encode($old[$k]??null)!==json_encode($new[$k]??null))$parts[]=(string)$k;
        if($parts)$parts=['Felder geändert: '.implode(', ',$parts)];
    }
    return $parts?mb_substr(($section==='apps'?'Apps: ':'Alexa-Skill: ').implode('; ',$parts),0,500):'';
}

// Übersicht für das CMS: jede aktive Marke x Plattform
function rrw_apps_overview(array $site,string $root): array {
    $reg=rrw_brands_registry($site);$rows=[];
    foreach($reg['items'] as $b){
        if(empty($b['enabled']))continue;
        $dom=trim((string)($b['primary_domain']??''));
        foreach(RRW_APPS_PLATFORMS as $pk=>$pl){
            $m=rrw_apps_meta($root,$b['id'],$reg['default'],$pk);
            $e=rrw_apps_entry($site,$b['id'],$pk);
            $rows[]=['brand'=>$b['id'],'brand_name'=>(string)($b['name']??$b['id']),'directory'=>!empty($b['directory']),'platform'=>$pk,'platform_name'=>$pl,
                'origin'=>$dom!==''?'https://'.$dom:'','meta'=>$m,'config'=>$e,'health'=>rrw_apps_health($m,$e,$pk),
                'shown'=>!empty($site['apps'][$pk.'_enabled'])||!array_key_exists($pk.'_enabled',(array)($site['apps']??[]))];
        }
    }
    return ['status'=>'ok','default'=>$reg['default'],'items'=>$rows,'features'=>RRW_APPS_FEATURES];
}

// Rollout: stabile Gruppe 0-99 je Installation (ohne ID: Gruppe 0 nur bei 100 %)
function rrw_apps_bucket(string $did,string $salt): int { return $did===''?100:hexdec(substr(hash('sha256',$salt.'|rollout|'.$did),0,6))%100; }

// Öffentlich: was die App der aufgerufenen Marke beim Start wissen muss
function rrw_apps_public(array $site,string $root,array $brand,string $platform,string $version,string $did='',string $salt='',int $code=0): array {
    $platform=isset(RRW_APPS_PLATFORMS[$platform])?$platform:'android';
    $reg=rrw_brands_registry($site);$id=(string)($brand['brand']??$brand['id']??$reg['default']);
    $e=rrw_apps_entry($site,$id,$platform);$m=rrw_apps_meta($root,$id,$reg['default'],$platform);
    $features=$e['features'];if(empty($brand['directory']))$features['directory']=false;
    $notice=null;$n=$e['notice'];
    if(!empty($n['enabled'])&&($n['text']!==''||$n['title']!=='')){
        $notice=['id'=>substr(md5(json_encode([$n['level'],$n['title'],$n['text'],$n['url']])),0,10),'level'=>$n['level'],'title'=>$n['title'],'text'=>$n['text'],'url'=>$n['url'],'url_label'=>$n['url_label']];
    }
    $latest=rrw_apps_version_clean((string)$m['version']);$v=rrw_apps_version_clean($version);
    $primary=$m['files'][0]??null;$origin=trim((string)($brand['origin']??''));
    // Mindestversion nur erzwingen, wenn es eine Version gibt, auf die man aktualisieren kann (sonst Aussperren ohne Ausweg)
    $reachable=$latest!==''&&($e['min_version']===''||rrw_apps_vcmp($latest,$e['min_version'])>=0);
    $required=$v!==''&&$reachable&&(($e['min_version']!==''&&rrw_apps_vcmp($v,$e['min_version'])<0)||in_array($v,$e['blocked'],true)&&rrw_apps_vcmp($v,$latest)<0);
    $latestCode=(int)($m['version_code']??0);
    // Gleicher Versionsname, aber neuerer Build (Developer-Builds): die Versionsnummer (Code) entscheidet, wenn die App sie mitschickt
    $newer=$v!==''&&$latest!==''&&(rrw_apps_vcmp($v,$latest)<0||(rrw_apps_vcmp($v,$latest)===0&&$code>0&&$latestCode>$code));
    $offer=rrw_apps_offer_ok($m,$e,$platform);
    // Stufenweise Freigabe: Pflicht-Updates gehen immer an alle
    $inWave=$e['rollout']>=100||$required||($did!==''&&rrw_apps_bucket($did,$salt)<$e['rollout']);
    $update=['latest'=>$latest,'min'=>$e['min_version'],
        'available'=>$newer&&$inWave&&$offer,'required'=>$required,
        'url'=>($primary&&$offer&&$origin!=='')?$origin.$primary['url']:'',
        'package'=>$platform==='android'?(string)($m['package']??''):'','latest_code'=>$latestCode,'notes'=>(string)$e['notes'],
        'sha256'=>$primary?strtolower((string)$primary['sha256']):'','size'=>$primary?(int)$primary['size']:0,
        'page'=>$origin!==''?$origin.'/#apps':''];
    $mt=$e['maintenance'];$maintenance=!empty($mt['enabled'])?['title'=>$mt['title']!==''?$mt['title']:'Wartungsarbeiten','text'=>$mt['text']!==''?$mt['text']:'Die App ist vorübergehend nicht verfügbar. Bitte versuche es später erneut.']:null;
    $tm=(array)($site['apps']['telemetry']??[]);
    $out=['status'=>'ok','brand'=>$id,'platform'=>$platform,'features'=>$features,'notice'=>$notice,'update'=>$update,'maintenance'=>$maintenance,
          'telemetry'=>['usage'=>!empty($tm['usage']),'errors'=>!empty($tm['errors']),'listen'=>!empty($tm['usage'])&&!empty($tm['listen'])],'layout'=>null];
    $b=$e['builder'];
    if(!empty($b['enabled'])){
        $layout=['theme'=>$b['theme'],'home'=>$b['home'],'stations'=>$b['stations'],'more_menu'=>$b['more_menu']];
        $layout['rev']=substr(md5(json_encode($layout)),0,10);
        $out['layout']=$layout;
    }
    return $out;
}

// ---------- Nutzungszahlen & Fehlerberichte (nur wenn im CMS eingeschaltet) ----------
function rrw_apps_dir(string $dataDir): string {
    $d=$dataDir.'/.apps';
    if(!is_dir($d)){@mkdir($d,0775,true);@file_put_contents($d.'/.htaccess',"Require all denied\n");}
    return $d;
}
// Zufälliges Salz je Server: macht aus der anonymen Install-ID einen nicht rückverfolgbaren Hash
function rrw_apps_salt(string $dataDir): string {
    $f=rrw_apps_dir($dataDir).'/salt.txt';$s=is_file($f)?trim((string)@file_get_contents($f)):'';
    if(strlen($s)<32){$s=bin2hex(random_bytes(24));@file_put_contents($f,$s);}
    return $s;
}
function rrw_apps_did_clean($d): string { $d=strtolower(trim((string)$d));return preg_match('/^[a-f0-9-]{16,64}$/',$d)?$d:''; }
// Gesperrtes Lesen-Ändern-Schreiben einer JSON-Datei
function rrw_apps_rmw(string $file,callable $fn): bool {
    $h=@fopen($file,'c+');if(!$h)return false;
    if(!flock($h,LOCK_EX)){fclose($h);return false;}
    $raw=stream_get_contents($h);$data=json_decode((string)$raw,true);if(!is_array($data))$data=[];
    $data=$fn($data);
    ftruncate($h,0);rewind($h);fwrite($h,json_encode($data,JSON_UNESCAPED_UNICODE));fflush($h);flock($h,LOCK_UN);fclose($h);
    return true;
}
// Zählt eine Installation einmal pro Tag (Datum + Version); gespeichert wird nur der Hash
function rrw_apps_record_ping(string $dataDir,string $brand,string $platform,string $version,string $did,string $geo=''): void {
    $did=rrw_apps_did_clean($did);if($did==='')return;
    $v=rrw_apps_version_clean($version);if($v==='')$v='?';
    $h=substr(hash('sha256',rrw_apps_salt($dataDir).'|stat|'.$did),0,16);$key=rrw_apps_key($brand,$platform);$today=gmdate('Y-m-d');
    rrw_apps_rmw(rrw_apps_dir($dataDir).'/usage.json',function(array $d) use($key,$v,$h,$today,$geo){
        $old=$d['installs'][$key][$h]??[];$d['installs'][$key][$h]=['v'=>$v,'d'=>$today,'f'=>$old['f']??$today,'g'=>$geo!==''?$geo:($old['g']??'')];
        if(count($d['installs'][$key])>20000){uasort($d['installs'][$key],fn($a,$b)=>strcmp($b['d'],$a['d']));$d['installs'][$key]=array_slice($d['installs'][$key],0,20000,true);}
        return $d;
    });
}
function rrw_apps_usage(string $dataDir): array {
    $f=rrw_apps_dir($dataDir).'/usage.json';$d=is_file($f)?(json_decode((string)@file_get_contents($f),true)?:[]):[];
    $t7=gmdate('Y-m-d',time()-6*86400);$t30=gmdate('Y-m-d',time()-29*86400);$out=[];
    foreach((array)($d['installs']??[]) as $key=>$rows){
        $o=['total'=>count($rows),'active7'=>0,'active30'=>0,'new7'=>0,'versions'=>[]];
        $t7n=gmdate('Y-m-d',time()-6*86400);
        foreach($rows as $r){
            if($r['d']>=$t7)$o['active7']++;
            if(($r['f']??'')>=$t7n)$o['new7']++;
            if($r['d']>=$t30){$o['active30']++;$o['versions'][$r['v']]=($o['versions'][$r['v']]??0)+1;}
        }
        uksort($o['versions'],fn($a,$b)=>rrw_apps_vcmp($b,$a));
        $out[$key]=$o;
    }
    return $out;
}
// Bindung: Anteil der Installationen, die mindestens 7 bzw. 30 Tage nach der ersten Meldung (f) noch gemeldet haben (zuletzt gesehen d >= f+N).
// Nur Installationen, die alt genug sind, zählen zur Kohorte.
function rrw_apps_retention(string $dataDir): array {
    $f=rrw_apps_dir($dataDir).'/usage.json';$d=is_file($f)?(json_decode((string)@file_get_contents($f),true)?:[]):[];
    $now=time();$out=[];
    foreach((array)($d['installs']??[]) as $key=>$rows){
        $o=['r7'=>['cohort'=>0,'kept'=>0],'r30'=>['cohort'=>0,'kept'=>0]];
        foreach((array)$rows as $r){
            $first=(string)($r['f']??'');$last=(string)($r['d']??'');$ts=$first!==''?strtotime($first.' 00:00:00 UTC'):false;if($ts===false)continue;
            foreach(['r7'=>7,'r30'=>30] as $k=>$n){
                if($ts+$n*86400>$now)continue;
                $o[$k]['cohort']++;if($last!==''&&$last>=gmdate('Y-m-d',$ts+$n*86400))$o[$k]['kept']++;
            }
        }
        $out[$key]=$o;
    }
    return $out;
}
// Fehlerbericht aufnehmen: gleiche Fehler werden gezählt statt doppelt gespeichert (max. 200 Gruppen)
function rrw_apps_error_add(string $dataDir,string $brand,string $platform,array $in): bool {
    $kind=in_array(($in['kind']??''),['player','crash','network','other'],true)?(string)$in['kind']:'other';
    $msg=rrw_apps_text($in['message']??'',300);if($msg==='')return false;
    $where=rrw_apps_text($in['where']??'',80);$ver=rrw_apps_version_clean((string)($in['version']??''));$os=rrw_apps_text($in['os']??'',60);
    $stack=mb_substr(trim(strip_tags((string)($in['stack']??''))),0,1500);
    $gid=substr(sha1($brand.'|'.$platform.'|'.$kind.'|'.$where.'|'.preg_replace('/\d+/','#',$msg)),0,12);$now=time();
    return rrw_apps_rmw(rrw_apps_dir($dataDir).'/errors.json',function(array $d) use($gid,$brand,$platform,$kind,$msg,$where,$ver,$os,$stack,$now){
        $g=$d['groups'][$gid]??['id'=>$gid,'brand'=>$brand,'platform'=>$platform,'kind'=>$kind,'message'=>$msg,'where'=>$where,'count'=>0,'first'=>$now,'versions'=>[],'os'=>[],'stack'=>''];
        $g['count']++;$g['last']=$now;if($ver!=='')$g['versions'][$ver]=($g['versions'][$ver]??0)+1;if($os!=='')$g['os'][$os]=($g['os'][$os]??0)+1;
        if($stack!==''&&$g['stack']==='')$g['stack']=$stack;
        $g['versions']=array_slice($g['versions'],-8,null,true);$g['os']=array_slice($g['os'],-8,null,true);
        $d['groups'][$gid]=$g;
        if(count($d['groups'])>200){uasort($d['groups'],fn($a,$b)=>$b['last']<=>$a['last']);$d['groups']=array_slice($d['groups'],0,200,true);}
        return $d;
    });
}
function rrw_apps_errors(string $dataDir): array {
    $f=rrw_apps_dir($dataDir).'/errors.json';$d=is_file($f)?(json_decode((string)@file_get_contents($f),true)?:[]):[];
    $g=array_values((array)($d['groups']??[]));usort($g,fn($a,$b)=>($b['last']??0)<=>($a['last']??0));
    return $g;
}
function rrw_apps_stats_clear(string $dataDir,string $what): void {
    $dir=rrw_apps_dir($dataDir);
    if($what==='errors'||$what==='all')@unlink($dir.'/errors.json');
    if($what==='usage'||$what==='all'){@unlink($dir.'/usage.json');@unlink($dir.'/listen.json');}
    if($what==='downloads'||$what==='all')@unlink($dir.'/downloads.json');
}
// Kurzer Schreibschutz gegen Flut (je IP-Hash und Stunde)
function rrw_apps_rate_ok(string $dataDir,string $bucket,int $limit): bool {
    $ip=(string)($_SERVER['HTTP_CF_CONNECTING_IP']??$_SERVER['HTTP_X_FORWARDED_FOR']??$_SERVER['REMOTE_ADDR']??'');$ip=trim(explode(',',$ip)[0]);
    $f=rrw_apps_dir($dataDir).'/rl_'.$bucket.'_'.substr(sha1($ip),0,16).'.json';$now=time();$ok=true;
    rrw_apps_rmw($f,function(array $st) use($now,$limit,&$ok){
        if(($st['start']??0)<$now-3600)$st=['start'=>$now,'count'=>0];
        if($st['count']>=$limit){$ok=false;return $st;}
        $st['count']++;return $st;
    });
    if(random_int(1,50)===1)foreach((array)glob(rrw_apps_dir($dataDir).'/rl_*.json') as $old)if(@filemtime($old)<$now-7200)@unlink($old);
    return $ok;
}

// ---------- Neue Installationen pro Tag + Download-Zähler ----------
// Neue Installationen: erste Meldung eines Geräts (Feld "f" in usage.json), je Marke:Plattform und Tag der letzten $days Tage
function rrw_apps_new_daily(string $dataDir,int $days=60): array {
    $f=rrw_apps_dir($dataDir).'/usage.json';$d=is_file($f)?(json_decode((string)@file_get_contents($f),true)?:[]):[];
    $cut=gmdate('Y-m-d',time()-($days-1)*86400);$out=[];
    foreach((array)($d['installs']??[]) as $key=>$rows)foreach($rows as $r){$first=(string)($r['f']??'');if($first!==''&&$first>=$cut)$out[$key][$first]=($out[$key][$first]??0)+1;}
    foreach($out as $k=>$v)ksort($out[$k]);
    return $out;
}
const RRW_APPS_DL_EXT=['apk','exe','aab','msi','zip'];
// Nur vorhandene Installationsdateien aus downloads/ (kein Pfad, nur bekannte Endungen)
function rrw_apps_download_file_ok(string $root,string $f): bool {
    return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,120}$/',$f)===1&&in_array(strtolower(pathinfo($f,PATHINFO_EXTENSION)),RRW_APPS_DL_EXT,true)&&is_file($root.'/downloads/'.$f);
}
// Zählt einen Download (gesamt + je Tag, 90 Tage); gespeichert werden nur Dateiname und Zähler
function rrw_apps_download_add(string $dataDir,string $file): bool {
    $today=gmdate('Y-m-d');$cut=gmdate('Y-m-d',time()-90*86400);$now=time();
    return rrw_apps_rmw(rrw_apps_dir($dataDir).'/downloads.json',function(array $d) use($file,$today,$cut,$now){
        if(!isset($d['since']))$d['since']=$now;
        $x=$d['files'][$file]??['total'=>0,'daily'=>[]];$x['total']++;$x['daily'][$today]=($x['daily'][$today]??0)+1;
        foreach(array_keys($x['daily']) as $k)if($k<$cut)unset($x['daily'][$k]);
        $d['files'][$file]=$x;
        if(count($d['files'])>200){uasort($d['files'],fn($a,$b)=>$b['total']<=>$a['total']);$d['files']=array_slice($d['files'],0,200,true);}
        return $d;
    });
}
// Auswertung: je Datei gesamt / 7 / 30 Tage und Tagesverlauf; Marke und Plattform aus der App-Übersicht (sonst nach Dateiendung)
function rrw_apps_download_stats(string $dataDir,array $site,string $root): array {
    $f=rrw_apps_dir($dataDir).'/downloads.json';$d=is_file($f)?(json_decode((string)@file_get_contents($f),true)?:[]):[];
    $map=[];foreach(rrw_apps_overview($site,$root)['items'] as $it)foreach((array)($it['meta']['files']??[]) as $fl)if(($fl['name']??'')!=='')$map[(string)$fl['name']]=['brand'=>$it['brand'],'platform'=>$it['platform']];
    $t7=gmdate('Y-m-d',time()-6*86400);$t30=gmdate('Y-m-d',time()-29*86400);$files=[];
    foreach((array)($d['files']??[]) as $name=>$x){
        $ext=strtolower(pathinfo((string)$name,PATHINFO_EXTENSION));$m=$map[$name]??['brand'=>'','platform'=>($ext==='apk'||$ext==='aab')?'android':(($ext==='exe'||$ext==='msi')?'windows':'')];
        $l7=0;$l30=0;foreach((array)($x['daily']??[]) as $day=>$n){if($day>=$t7)$l7+=$n;if($day>=$t30)$l30+=$n;}
        $files[$name]=['brand'=>$m['brand'],'platform'=>$m['platform'],'total'=>(int)($x['total']??0),'last7'=>$l7,'last30'=>$l30,'daily'=>(array)($x['daily']??[])];
    }
    return ['since'=>(int)($d['since']??0),'files'=>$files];
}

// Herkunft der aktiven Geräte (30 Tage) je App. Kleine Gruppen (< $min) werden zu "weitere" zusammengefasst.
function rrw_apps_geo_stats(string $dataDir,int $min=3): array {
    $f=rrw_apps_dir($dataDir).'/usage.json';$d=is_file($f)?(json_decode((string)@file_get_contents($f),true)?:[]):[];
    $t30=gmdate('Y-m-d',time()-29*86400);$out=[];
    foreach((array)($d['installs']??[]) as $key=>$rows){
        $c=[];$r=[];$ci=[];$unknown=0;
        foreach($rows as $x){
            if($x['d']<$t30)continue;$g=explode('|',(string)($x['g']??''));
            if(count($g)<3||$g[0]===''){$unknown++;continue;}
            $c[$g[0]]=($c[$g[0]]??0)+1;
            if($g[1]!==''){$k=$g[0].'|'.$g[1];$r[$k]=($r[$k]??0)+1;}
            if($g[2]!==''){$k=$g[0].'|'.$g[1].'|'.$g[2];$ci[$k]=($ci[$k]??0)+1;}
        }
        $fmt=function(array $m) use($min){arsort($m);$list=[];$rest=0;foreach($m as $k=>$n){if($n<$min){$rest+=$n;continue;}$list[]=['name'=>str_replace('|',' · ',$k),'count'=>$n];}return ['items'=>array_slice($list,0,30),'other'=>$rest];};
        $out[$key]=['countries'=>$fmt($c),'regions'=>$fmt($r),'cities'=>$fmt($ci),'unknown'=>$unknown];
    }
    return $out;
}

// ---------- Hörstatistik (nur wenn "Hörstatistik" im CMS an ist): Sender + Sekunden je Sitzung ----------
function rrw_apps_station_clean($s): string { $s=strtolower(trim((string)$s));return preg_match('/^[a-z0-9][a-z0-9_.:-]{0,63}$/',$s)?$s:''; }
function rrw_apps_record_listen(string $dataDir,string $brand,string $platform,string $did,string $station,int $seconds): bool {
    $did=rrw_apps_did_clean($did);$station=rrw_apps_station_clean($station);
    if($did===''||$station===''||$seconds<10)return false;$seconds=min($seconds,6*3600);
    $h=substr(hash('sha256',rrw_apps_salt($dataDir).'|stat|'.$did),0,16);$key=rrw_apps_key($brand,$platform);$today=gmdate('Y-m-d');$cut=gmdate('Y-m-d',time()-90*86400);
    return rrw_apps_rmw(rrw_apps_dir($dataDir).'/listen.json',function(array $d) use($key,$h,$station,$seconds,$today,$cut){
        $day=&$d['days'][$today][$key];$day=$day??['sec'=>0,'n'=>0,'dev'=>[],'st'=>[]];
        $day['sec']+=$seconds;$day['n']++;
        $day['dev'][$h]=($day['dev'][$h]??0)+$seconds;
        $s=$day['st'][$station]??['sec'=>0,'n'=>0,'dev'=>[]];$s['sec']+=$seconds;$s['n']++;$s['dev'][$h]=1;$day['st'][$station]=$s;
        if(count($day['st'])>150){uasort($day['st'],fn($a,$b)=>$b['sec']<=>$a['sec']);$day['st']=array_slice($day['st'],0,150,true);}
        unset($day);
        foreach(array_keys($d['days']) as $dk)if($dk<$cut)unset($d['days'][$dk]);
        return $d;
    });
}
function rrw_apps_listen_stats(string $dataDir): array {
    $f=rrw_apps_dir($dataDir).'/listen.json';$d=is_file($f)?(json_decode((string)@file_get_contents($f),true)?:[]):[];
    $t30=gmdate('Y-m-d',time()-29*86400);$out=[];
    foreach((array)($d['days']??[]) as $date=>$keys)foreach($keys as $key=>$day){
        if($date<$t30)continue;$o=&$out[$key];
        $o=$o??['sec'=>0,'sessions'=>0,'dev'=>[],'stations'=>[],'daily'=>[]];
        $o['sec']+=$day['sec'];$o['sessions']+=$day['n'];$o['daily'][$date]=['sec'=>$day['sec'],'listeners'=>count($day['dev'])];
        foreach($day['dev'] as $h=>$sec)$o['dev'][$h]=($o['dev'][$h]??0)+$sec;
        foreach($day['st'] as $id=>$st){$x=$o['stations'][$id]??['sec'=>0,'n'=>0,'dev'=>[]];$x['sec']+=$st['sec'];$x['n']+=$st['n'];foreach($st['dev'] as $h=>$_)$x['dev'][$h]=1;$o['stations'][$id]=$x;}
        unset($o);
    }
    foreach($out as $key=>$o){
        $listeners=count($o['dev']);$days=max(1,count($o['daily']));
        uasort($o['stations'],fn($a,$b)=>$b['sec']<=>$a['sec']);ksort($o['daily']);
        $out[$key]=['seconds'=>$o['sec'],'sessions'=>$o['sessions'],'listeners'=>$listeners,
            'avg_per_listener'=>$listeners?(int)round($o['sec']/$listeners):0,
            'avg_per_listener_day'=>$listeners?(int)round($o['sec']/max(1,array_sum(array_map(fn($x)=>$x['listeners'],$o['daily'])))):0,
            'avg_session'=>$o['sessions']?(int)round($o['sec']/$o['sessions']):0,
            'stations'=>array_map(fn($id,$x)=>['id'=>$id,'seconds'=>$x['sec'],'sessions'=>$x['n'],'listeners'=>count($x['dev'])],array_slice(array_keys($o['stations']),0,15),array_slice(array_values($o['stations']),0,15)),
            'daily'=>array_map(fn($date,$x)=>['date'=>$date,'seconds'=>$x['sec'],'listeners'=>$x['listeners']],array_keys($o['daily']),array_values($o['daily']))];
    }
    return $out;
}

<?php
// Alexa-Skill-Baukasten (Website-Skill): Der Skill beantwortet Fragen zur Website. Der Betreiber pflegt „Themen“ (eigene Antworten, z. B.
// Öffnungszeiten, Kontakt, Über uns) und der Skill liest auf Wunsch die neuesten Beiträge der Website vor.
// Der Skill (Node.js bei Amazon) holt seine Einstellungen per öffentlicher Aktion alexa_config (nur lesend, ohne Geheimnisse),
// meldet optional anonyme Zähler per alexa_stat (mit Token aus dem Skill-Paket) und wird als Paket aus dem CMS heruntergeladen.
// Das Sprachmodell (Themen-Aussprachen, Sätze) liegt bei Amazon und wird hier erzeugt; Änderungen daran müssen neu eingespielt werden.

function rrw_alexa_dir(string $dataDir): string {
    $d=$dataDir.'/.alexa';
    if(!is_dir($d)){@mkdir($d,0775,true);@file_put_contents($d.'/.htaccess',"Require all denied\n");}
    return $d;
}
function rrw_alexa_skill_dir(): string { return defined('RRW_ALEXA_SKILL_DIR')?rtrim((string)RRW_ALEXA_SKILL_DIR,'/'):__DIR__.'/alexa-skill'; }
function rrw_alexa_data_dir(): string { return function_exists('rrw_data_dir')?rrw_data_dir():dirname(__DIR__).'/data'; }
function rrw_alexa_text($v,int $max): string { return mb_substr(trim(strip_tags((string)$v)),0,$max); }
function rrw_alexa_id($v): string { $v=strtolower(trim((string)$v));return preg_match('/^[a-z0-9][a-z0-9_-]{1,62}$/',$v)?$v:''; }
function rrw_alexa_uniq(array $arr): array {
    $seen=[];$out=[];foreach($arr as $x){$x=trim((string)$x);$k=mb_strtolower($x);if($k===''||isset($seen[$k]))continue;$seen[$k]=1;$out[]=$x;}return $out;
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
    $n=rrw_alexa_text($site['portal']['site_name']??'',40);return $n!==''?$n:'Meine Website';
}
/** Name und Aufrufname des Skills (Aufrufname aus den Einstellungen, sonst aus dem Namen). */
function rrw_alexa_brand(array $site): array {
    $name=rrw_alexa_app_name($site);
    $inv=rrw_alexa_invocation_clean($site['alexa']['invocation']??'')?:rrw_alexa_invocation_clean($name)?:'meine website';
    return ['name'=>$name,'invocationName'=>$inv];
}

const RRW_ALEXA_MAX_TOPICS=40;

function rrw_alexa_defaults(): array {
    $n=rrw_alexa_app_name(rrw_alexa_site());
    return [
        'enabled'=>true,
        'maintenance'=>['enabled'=>false,'text'=>'Der Skill ist gerade nicht verfügbar. Bitte versuche es später noch einmal.'],
        'texts'=>[
            'welcome'=>'Willkommen bei '.$n.'. Frag mich nach den Neuigkeiten oder einem Thema.',
            'help'=>'Mit '.$n.' erfährst du mehr über uns. Sag zum Beispiel: Was gibt es Neues? Oder: Erzähl mir etwas über {beispiele}. Frag: Welche Themen gibt es? Mit Stopp beendest du den Skill.',
            'goodbye'=>'Bis bald!',
            'unknown'=>'Dazu habe ich leider keine Antwort. Frag zum Beispiel: Welche Themen gibt es?',
            'nonews'=>'Aktuell gibt es keine Neuigkeiten.'
        ],
        'topics'=>new stdClass(),'order'=>[],
        'news'=>['enabled'=>true,'count'=>3],
        'stats'=>false,
        'app_name'=>'','invocation'=>''
    ];
}
function rrw_alexa_clean($v): array {
    $v=is_array($v)?$v:[];$d=rrw_alexa_defaults();$t=(array)($v['texts']??[]);$m=(array)($v['maintenance']??[]);$nw=(array)($v['news']??[]);
    $out=[
        'enabled'=>!array_key_exists('enabled',$v)||!empty($v['enabled']),
        'maintenance'=>['enabled'=>!empty($m['enabled']),'text'=>rrw_alexa_text($m['text']??'',300)?:$d['maintenance']['text']],
        'texts'=>[],
        'topics'=>[],
        'order'=>[],
        'news'=>['enabled'=>!array_key_exists('enabled',$nw)||!empty($nw['enabled']),'count'=>max(1,min(10,(int)($nw['count']??$d['news']['count'])))],
        'stats'=>!empty($v['stats']),
        'app_name'=>rrw_alexa_text($v['app_name']??'',40),
        'invocation'=>rrw_alexa_invocation_clean($v['invocation']??'')
    ];
    foreach($d['texts'] as $k=>$def){$x=rrw_alexa_text($t[$k]??'',400);$out['texts'][$k]=$x!==''?$x:$def;}
    foreach(array_slice((array)($v['topics']??[]),0,RRW_ALEXA_MAX_TOPICS*2,true) as $id=>$s){
        if(count($out['topics'])>=RRW_ALEXA_MAX_TOPICS)break;
        $id=rrw_alexa_id($id);if($id===''||!is_array($s))continue;
        $extra=[];foreach(array_slice((array)($s['extra']??[]),0,30) as $x){$x=mb_strtolower(preg_replace('/[^\p{L}\p{N} ]+/u',' ',rrw_alexa_text($x,60)));$x=trim(preg_replace('/\s+/',' ',$x));if($x!==''&&!in_array($x,$extra,true))$extra[]=$x;}
        $out['topics'][$id]=['enabled'=>!array_key_exists('enabled',$s)||!empty($s['enabled']),'title'=>rrw_alexa_text($s['title']??'',60),'text'=>rrw_alexa_text($s['text']??'',600),'extra'=>$extra];
    }
    if(!$out['topics'])$out['topics']=new stdClass();
    foreach(array_slice((array)($v['order']??[]),0,RRW_ALEXA_MAX_TOPICS*2) as $id){$id=rrw_alexa_id($id);if($id!==''&&!in_array($id,$out['order'],true))$out['order'][]=$id;}
    return $out;
}

/** Themen in Reihenfolge: [id => ['id','title','text','enabled','extra']]. Themen ohne Titel oder Text werden nicht ausgespielt. */
function rrw_alexa_topic_defs(array $site): array {
    $cfg=rrw_alexa_clean($site['alexa']??[]);$all=(array)$cfg['topics'];$defs=[];
    foreach($all as $id=>$s){
        $title=$s['title']!==''?$s['title']:ucfirst(str_replace(['-','_'],' ',$id));
        $defs[$id]=['id'=>$id,'title'=>$title,'text'=>$s['text'],'enabled'=>$s['enabled']&&$s['text']!=='','extra'=>$s['extra']];
    }
    $ordered=[];foreach($cfg['order'] as $id)if(isset($defs[$id])){$ordered[$id]=$defs[$id];unset($defs[$id]);}
    return $ordered+$defs;
}
/** Slot-Wert eines Themas: Hauptname + weitere Aussprachen. */
function rrw_alexa_topic_value(array $s): array {
    $main=mb_strtolower(trim(preg_replace('/\s+/u',' ',preg_replace('/[^\p{L}\p{N} ]+/u',' ',$s['title']))));
    if($main==='')$main=mb_strtolower(str_replace(['-','_'],' ',$s['id']));
    $syn=array_values(array_filter(rrw_alexa_uniq((array)$s['extra']),fn($n)=>mb_strtolower($n)!==$main));
    return ['id'=>$s['id'],'name'=>['value'=>$main,'synonyms'=>$syn]];
}

/** Die neuesten veröffentlichten Beiträge der Website (aus data/news.json): [['title','text']]. */
function rrw_alexa_news(string $dataDir,int $count): array {
    $f=$dataDir.'/news.json';$rows=is_file($f)?(json_decode((string)@file_get_contents($f),true)?:[]):[];$now=date('Y-m-d H:i:s');$out=[];
    foreach($rows as $a){
        if(!is_array($a)||($a['status']??'')!=='published'||!empty($a['deleted_at']))continue;
        $pub=(string)(($a['published_at']??'')!==''?$a['published_at']:($a['created_at']??''));
        if($pub!==''&&strlen($pub)>=16&&str_replace('T',' ',$pub)>$now)continue;   // Terminbeitrag in der Zukunft
        $out[]=$a;
    }
    $ts=static fn($x)=>strtotime((string)(($x['published_at']??'')!==''?$x['published_at']:($x['created_at']??'')))?:0;
    usort($out,fn($a,$b)=>$ts($b)<=>$ts($a));
    return array_map(function($a){
        $body=(string)($a['excerpt']??$a['intro']??'');if(trim(strip_tags($body))==='')$body=(string)($a['body_html']??$a['content']??'');
        $text=trim(preg_replace('/\s+/u',' ',html_entity_decode(strip_tags($body),ENT_QUOTES|ENT_HTML5,'UTF-8')));
        return ['title'=>rrw_alexa_text($a['title']??'',120),'text'=>mb_substr($text,0,320)];
    },array_slice($out,0,max(1,min(10,$count))));
}

/** Sprachmodell de-DE: Neuigkeiten, Beitrag lesen, Themen (nur wenn es welche gibt). */
function rrw_alexa_model(array $site,?array &$warnings=null): array {
    $warnings=[];$brand=rrw_alexa_brand($site);$cfg=rrw_alexa_clean($site['alexa']??[]);
    $values=[];$seen=[];
    foreach(rrw_alexa_topic_defs($site) as $s){
        if(!$s['enabled'])continue;
        $v=rrw_alexa_topic_value($s);$keep=[];
        foreach($v['name']['synonyms'] as $n){$k=mb_strtolower($n);if(isset($seen[$k])){$warnings[]="„$n“ gehört schon zu {$seen[$k]} und wurde bei {$s['id']} weggelassen.";continue;}$seen[$k]=$s['id'];$keep[]=$n;}
        $mk=mb_strtolower($v['name']['value']);
        if(isset($seen[$mk])){$warnings[]="Der Name „{$v['name']['value']}“ von {$s['id']} gehört schon zu {$seen[$mk]} – bitte einen eindeutigen Titel wählen.";continue;}
        $seen[$mk]=$s['id'];$v['name']['synonyms']=$keep;$values[]=$v;
    }
    $built=['Cancel','Help','Stop','Fallback','NavigateHome','Repeat'];
    $intents=[];foreach($built as $b)$intents[]=['name'=>"AMAZON.{$b}Intent",'samples'=>[]];
    if($cfg['news']['enabled']){
        $intents[]=['name'=>'NewsIntent','samples'=>['was gibt es neues','was gibt es neuigkeiten','was ist neu','was ist neues passiert','neuigkeiten','die neuigkeiten','aktuelle meldungen','was gibt es aktuell','lies mir die neuigkeiten vor','lies die neuesten beiträge vor','lies mir die neuesten beiträge vor','was sind die neuesten beiträge','was steht in den news','erzähl mir die neuigkeiten','gibt es etwas neues']];
        $intents[]=['name'=>'ReadNewsIntent','slots'=>[['name'=>'nummer','type'=>'AMAZON.NUMBER']],'samples'=>['lies beitrag {nummer}','lies mir beitrag {nummer} vor','beitrag {nummer}','mehr zu beitrag {nummer}','mehr zu nummer {nummer}','nummer {nummer}','lies nummer {nummer}','lies die nummer {nummer} vor','den {nummer} beitrag','erzähl mir mehr zu beitrag {nummer}']];
    }
    $types=[];
    if($values){
        $intents[]=['name'=>'TopicIntent','slots'=>[['name'=>'thema','type'=>'THEMA_TYP']],'samples'=>['erzähl mir etwas über {thema}','erzähl mir was über {thema}','was weißt du über {thema}','was gibt es zu {thema}','informationen zu {thema}','infos zu {thema}','sag mir etwas zu {thema}','sag mir was zu {thema}','ich möchte etwas über {thema} wissen','ich will etwas über {thema} wissen','sprich über {thema}','{thema}','zum thema {thema}','wie ist {thema}','was ist {thema}']];
        $intents[]=['name'=>'ListTopicsIntent','samples'=>['welche themen gibt es','welche themen hast du','welche themen habt ihr','nenne mir die themen','liste die themen auf','themen auflisten','worüber kannst du auskunft geben','was kannst du','was kannst du alles','was weißt du']];
        $types[]=['name'=>'THEMA_TYP','values'=>$values];
    }
    $lm=['invocationName'=>$brand['invocationName'],'intents'=>$intents];
    if($types)$lm['types']=$types;
    return ['interactionModel'=>['languageModel'=>$lm]];
}

function rrw_alexa_token(string $dataDir,bool $reset=false): string {
    $f=rrw_alexa_dir($dataDir).'/token.txt';$t=is_file($f)?trim((string)@file_get_contents($f)):'';
    if($reset||strlen($t)<32){$t=bin2hex(random_bytes(24));@file_put_contents($f,$t);@chmod($f,0640);}
    return $t;
}

/** Öffentliche Konfiguration für den Skill (keine Geheimnisse). */
function rrw_alexa_public(array $site,string $dataDir,string $origin): array {
    $brand=rrw_alexa_brand($site);$cfg=rrw_alexa_clean($site['alexa']??[]);
    $topics=[];foreach(rrw_alexa_topic_defs($site) as $s)$topics[]=['id'=>$s['id'],'title'=>$s['title'],'text'=>$s['text'],'enabled'=>$s['enabled']];
    $out=['status'=>'ok','enabled'=>$cfg['enabled'],'maintenance'=>$cfg['maintenance']['enabled']?$cfg['maintenance']['text']:null,
        'name'=>$brand['name'],'texts'=>$cfg['texts'],'topics'=>$topics,'news'=>$cfg['news']['enabled']?rrw_alexa_news($dataDir,$cfg['news']['count']):[],
        'stats'=>$cfg['stats']];
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

// ---------- Zähler (anonym: nur Thema und Befehlsname, keine Geräte-/Nutzer-IDs) ----------
function rrw_alexa_stat_add(string $dataDir,string $event,string $topic,string $intent): bool {
    $topic=rrw_alexa_id($topic);$intent=preg_match('/^[A-Za-z.]{3,40}$/',$intent)?$intent:'';
    if(!in_array($event,['topic','intent'],true))return false;
    $today=gmdate('Y-m-d');$cut=gmdate('Y-m-d',time()-90*86400);
    return rrw_apps_rmw(rrw_alexa_dir($dataDir).'/stats.json',function(array $d) use($event,$topic,$intent,$today,$cut){
        $day=&$d['days'][$today];$day=$day??['topics'=>[],'intents'=>[]];
        if($event==='topic'&&$topic!=='')$day['topics'][$topic]=($day['topics'][$topic]??0)+1;
        if($event==='intent'&&$intent!=='')$day['intents'][$intent]=($day['intents'][$intent]??0)+1;
        unset($day);foreach(array_keys($d['days']) as $k)if($k<$cut)unset($d['days'][$k]);
        return $d;
    });
}
function rrw_alexa_stats(string $dataDir): array {
    $f=rrw_alexa_dir($dataDir).'/stats.json';$d=is_file($f)?(json_decode((string)@file_get_contents($f),true)?:[]):[];
    $t30=gmdate('Y-m-d',time()-29*86400);$topics=[];$intents=[];$daily=[];
    foreach((array)($d['days']??[]) as $date=>$day){
        if($date<$t30)continue;$n=0;
        foreach((array)($day['topics']??[]) as $k=>$c){$topics[$k]=($topics[$k]??0)+$c;}
        foreach((array)($day['intents']??[]) as $k=>$c){$intents[$k]=($intents[$k]??0)+$c;$n+=$c;}
        $daily[]=['date'=>$date,'requests'=>$n];
    }
    arsort($topics);arsort($intents);usort($daily,fn($a,$b)=>strcmp($a['date'],$b['date']));
    return ['topics'=>$topics,'intents'=>$intents,'daily'=>$daily,'total'=>array_sum($intents)];
}
function rrw_alexa_stats_clear(string $dataDir): void { @unlink(rrw_alexa_dir($dataDir).'/stats.json'); }

// ---------- Skill-Paket (ZIP) ----------
/** Platzhalter in Vorlagen ersetzen ({{NAME}}, {{INVOCATION}}, {{ORIGIN}}, {{EXAMPLE}} = erstes Thema, {{THEMEN}} = alle Themen). */
function rrw_alexa_fill(string $tpl,array $site,string $origin=''): string {
    $titles=[];foreach(rrw_alexa_topic_defs($site) as $s)if($s['enabled'])$titles[]=$s['title'];
    $brand=rrw_alexa_brand($site);
    return strtr($tpl,['{{THEMEN}}'=>$titles?implode(', ',$titles):'(noch keine Themen)','{{NAME}}'=>$brand['name'],'{{INVOCATION}}'=>$brand['invocationName'],'{{ORIGIN}}'=>rtrim($origin,'/'),'{{EXAMPLE}}'=>$titles[0]??'die Neuigkeiten']);
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
    $dirName=(trim((string)preg_replace('/[^a-z0-9]+/','-',strtolower(rrw_alexa_app_name(rrw_alexa_site()))),'-')?:'website').'-skill';
    foreach($files as $name=>$content)$zip->addFromString($dirName.'/'.$name,$content);
    $zip->close();return $tmp;
}

// Hat sich das Sprachmodell seit dem letzten Download geändert? (dann muss es bei Amazon neu eingespielt werden)
function rrw_alexa_model_rev(array $site): string { return substr(md5(json_encode(rrw_alexa_model($site))),0,12); }
function rrw_alexa_exported_rev(string $dataDir): string { $f=rrw_alexa_dir($dataDir).'/exported.txt';return is_file($f)?trim((string)@file_get_contents($f)):''; }
function rrw_alexa_mark_exported(string $dataDir,string $rev): void { @file_put_contents(rrw_alexa_dir($dataDir).'/exported.txt',$rev); }

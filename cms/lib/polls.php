<?php
declare(strict_types=1);
// Umfragen des CMS (unabhängig von den Radio-Umfragen): anlegen, abstimmen, Ergebnisse. Daten: cms/data/.tools/polls.json
// (gesperrter Ordner). Doppelte Stimmen werden über eine zufällige Browser-Kennung und – gröber – über einen gesalzenen Hash der
// IP verhindert; roh wird nie eine IP gespeichert. Die Hashes verschwinden, wenn die Umfrage gelöscht oder zurückgesetzt wird.
require_once __DIR__.'/tools.php';

function elvado_polls_load(string $dataDir): array {
    $d=elvado_tools_read(elvado_tools_dir($dataDir).'/polls.json',['polls'=>[]]);
    return is_array($d['polls']??null)?array_values($d['polls']):[];
}
function elvado_polls_save(string $dataDir, array $polls): void {
    elvado_tools_write(elvado_tools_dir($dataDir),'polls.json',['polls'=>array_values(array_slice($polls,-200))]);
}
function elvado_poll_dt($v): string {
    $v=trim((string)$v);if($v==='')return '';
    if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/',$v,$m)||!checkdate((int)$m[2],(int)$m[3],(int)$m[1]))return '';
    return sprintf('%04d-%02d-%02d %02d:%02d:00',$m[1],$m[2],$m[3],$m[4],$m[5]);
}
/** Bereinigt eine Umfrage aus dem Formular; $old bewahrt Stimmen, wenn die Option-IDs gleich bleiben. */
function elvado_poll_clean(array $in, ?array $old=null): ?array {
    $q=mb_substr(trim(strip_tags((string)($in['question']??''))),0,200);if($q==='')return null;
    $texts=$in['options']??[];if(is_string($texts))$texts=preg_split('/\r?\n/',$texts);
    $oldBy=[];foreach((array)($old['options']??[]) as $o)$oldBy[(string)($o['id']??'')]=$o;
    $opts=[];$seen=[];
    foreach((array)$texts as $o){
        $id=null;if(is_array($o)){$id=preg_match('/^[a-f0-9]{6}$/',(string)($o['id']??''))?(string)$o['id']:null;$t=(string)($o['text']??'');}else $t=(string)$o;
        $t=mb_substr(trim(strip_tags($t)),0,120);if($t==='')continue;
        $k=mb_strtolower($t);if(isset($seen[$k]))continue;$seen[$k]=1;
        if($id===null){                                              // Text unverändert wiedererkennen, damit Stimmen erhalten bleiben
            foreach($oldBy as $oid=>$oo)if(mb_strtolower((string)($oo['text']??''))===$k){$id=(string)$oid;break;}
        }
        $id=$id??(chr(97+random_int(0,5)).bin2hex(random_bytes(2)).dechex(random_int(0,15)));      // erstes Zeichen a–f: nie rein numerisch (PHP macht daraus sonst Zahlen-Schlüssel)
        $opts[]=['id'=>$id,'text'=>$t,'votes'=>(int)($oldBy[$id]['votes']??0)];
        if(count($opts)>=12)break;
    }
    if(count($opts)<2)return null;
    $show=(string)($in['show_results']??'after_vote');if(!in_array($show,['always','after_vote','after_close'],true))$show='after_vote';
    return [
        'id'=>preg_match('/^[a-f0-9]{8}$/',(string)($old['id']??''))?(string)$old['id']:bin2hex(random_bytes(4)),
        'question'=>$q,'options'=>$opts,'multiple'=>!empty($in['multiple']),'show_results'=>$show,
        'closed'=>!empty($in['closed']),'starts_at'=>elvado_poll_dt($in['starts_at']??''),'ends_at'=>elvado_poll_dt($in['ends_at']??''),
        'created_at'=>(string)($old['created_at']??date('Y-m-d H:i:s')),'updated_at'=>date('Y-m-d H:i:s'),
    ];
}
function elvado_poll_state(array $p, ?int $now=null): string {   // upcoming | open | closed
    $now=$now??time();
    if(!empty($p['closed']))return 'closed';
    if(($p['starts_at']??'')!==''&&strtotime($p['starts_at'])>$now)return 'upcoming';
    if(($p['ends_at']??'')!==''&&strtotime($p['ends_at'])<=$now)return 'closed';
    return 'open';
}
function elvado_poll_total(array $p): int { $n=0;foreach((array)($p['options']??[]) as $o)$n+=(int)($o['votes']??0);return $n; }
/** Öffentliche Sicht: Ergebnisse nur, wenn die Einstellung es erlaubt. */
function elvado_poll_public(array $p, bool $voted, ?int $now=null): array {
    $state=elvado_poll_state($p,$now);
    $show=($p['show_results']??'after_vote')==='always'||(($p['show_results']??'')==='after_vote'&&($voted||$state==='closed'))||(($p['show_results']??'')==='after_close'&&$state==='closed');
    $total=elvado_poll_total($p);
    $opts=array_map(fn($o)=>['id'=>$o['id'],'text'=>$o['text']]+($show?['votes'=>(int)$o['votes'],'percent'=>$total>0?round(100*(int)$o['votes']/$total,1):0]:[]),(array)$p['options']);
    return ['id'=>$p['id'],'question'=>$p['question'],'multiple'=>!empty($p['multiple']),'state'=>$state,'ends_at'=>(string)($p['ends_at']??''),'options'=>$opts,'results'=>$show,'total'=>$show?$total:null,'voted'=>$voted];
}
function elvado_poll_salt(string $dataDir): string {
    $f=elvado_tools_dir($dataDir).'/polls-salt.json';$s=elvado_tools_read($f,[]);
    if(empty($s['salt'])){$s=['salt'=>bin2hex(random_bytes(16))];try{elvado_tools_write(elvado_tools_dir($dataDir),'polls-salt.json',$s);}catch(Throwable $e){}}
    return (string)$s['salt'];
}
function elvado_poll_voters_file(string $dataDir): string { return elvado_tools_dir($dataDir).'/polls-voters.json'; }
function elvado_poll_has_voted(string $dataDir, string $pollId, string $client): bool {
    if(!preg_match('/^[A-Za-z0-9_-]{16,64}$/',$client))return false;
    $v=elvado_tools_read(elvado_poll_voters_file($dataDir),[]);
    $key=hash_hmac('sha256',$pollId.'|c|'.$client,elvado_poll_salt($dataDir));
    return isset($v[$pollId]['c'][$key]);
}
/** Stimme abgeben. ['ok','message','code','poll'] */
function elvado_poll_vote(string $dataDir, string $pollId, array $optionIds, string $client, string $ip, ?int $now=null): array {
    $fail=fn(string $m,int $c=400)=>['ok'=>false,'message'=>$m,'code'=>$c];
    if(!preg_match('/^[a-f0-9]{8}$/',$pollId))return $fail('Umfrage nicht gefunden',404);
    if(!preg_match('/^[A-Za-z0-9_-]{16,64}$/',$client))return $fail('Ungültige Anfrage');
    $dir=elvado_tools_dir($dataDir);if(!is_dir($dir)&&!@mkdir($dir,0775,true))return $fail('Speichern nicht möglich',500);
    elvado_protect_dir($dir);
    $h=@fopen($dir.'/.polls.lock','c');if(!$h)return $fail('Speichern nicht möglich',500);
    try{
        if(!@flock($h,LOCK_EX))return $fail('Bitte erneut versuchen',503);
        $polls=elvado_polls_load($dataDir);$idx=null;foreach($polls as $i=>$p)if(($p['id']??'')===$pollId){$idx=$i;break;}
        if($idx===null)return $fail('Umfrage nicht gefunden',404);
        $p=$polls[$idx];$st=elvado_poll_state($p,$now);
        if($st==='upcoming')return $fail('Diese Umfrage hat noch nicht begonnen.',403);
        if($st==='closed')return $fail('Diese Umfrage ist beendet.',403);
        $valid=[];foreach($p['options'] as $o)$valid[(string)$o['id']]=1;
        $pick=[];foreach($optionIds as $id){$id=(string)$id;if(isset($valid[$id])&&!in_array($id,$pick,true))$pick[]=$id;}
        if(!$pick)return $fail('Bitte wähle eine Antwort aus.');
        if(empty($p['multiple'])&&count($pick)>1)return $fail('Bei dieser Umfrage ist nur eine Antwort möglich.');
        $salt=elvado_poll_salt($dataDir);
        $ck=hash_hmac('sha256',$pollId.'|c|'.$client,$salt);$ik=hash_hmac('sha256',$pollId.'|i|'.$ip,$salt);
        $v=elvado_tools_read(elvado_poll_voters_file($dataDir),[]);
        if(isset($v[$pollId]['c'][$ck]))return $fail('Du hast bei dieser Umfrage schon abgestimmt.',409);
        if(((int)($v[$pollId]['i'][$ik]??0))>=5)return $fail('Von deinem Anschluss wurde schon mehrfach abgestimmt.',429);
        foreach($polls[$idx]['options'] as &$o)if(in_array((string)$o['id'],$pick,true))$o['votes']=(int)$o['votes']+1;unset($o);
        $v[$pollId]['c'][$ck]=1;$v[$pollId]['i'][$ik]=((int)($v[$pollId]['i'][$ik]??0))+1;
        elvado_tools_write($dir,'polls-voters.json',$v);elvado_polls_save($dataDir,$polls);
        return ['ok'=>true,'message'=>'Danke für deine Stimme!','code'=>200,'poll'=>$polls[$idx]];
    }catch(Throwable $e){return $fail('Speichern fehlgeschlagen',500);}
    finally{@flock($h,LOCK_UN);@fclose($h);}
}
function elvado_poll_voters_drop(string $dataDir, string $pollId): void {
    $v=elvado_tools_read(elvado_poll_voters_file($dataDir),[]);if(isset($v[$pollId])){unset($v[$pollId]);try{elvado_tools_write(elvado_tools_dir($dataDir),'polls-voters.json',$v);}catch(Throwable $e){}}
}
function elvado_polls_csv(array $p): string {
    $q=fn($v)=>'"'.str_replace('"','""',preg_replace('/^([=+\-@])/','\'$1',(string)$v)).'"';
    $total=elvado_poll_total($p);$out="\xEF\xBB\xBF".$q($p['question'])."\r\nAntwort;Stimmen;Anteil\r\n";
    foreach($p['options'] as $o)$out.=implode(';',[$q($o['text']),(int)$o['votes'],$total>0?round(100*(int)$o['votes']/$total,1).' %':'0 %'])."\r\n";
    return $out.'Gesamt;'.$total."\r\n";
}

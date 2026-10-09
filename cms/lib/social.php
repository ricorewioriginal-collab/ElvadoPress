<?php
declare(strict_types=1);
// Soziales Netzwerk des Community-Moduls (optional): Statusbeiträge, Folgen, Aktivitäts-Feed, Likes, öffentliche Profile.
// Beiträge sind reiner Text (max. 500 Zeichen). Daten: cms/data/.community/social.json (gesperrt, nicht im Repo).
//  posts   [{id,author,body,created,likes:[Mitglieds-IDs]}]   follows  {Mitglieds-ID:[gefolgte IDs]}
require_once __DIR__.'/forum.php';

const ELVADO_SO_PER_PAGE=15;
const ELVADO_SO_MAX_POSTS=5000;
const ELVADO_SO_MAX_FOLLOWS=500;

function elvado_so_load(string $dataDir): array {
    $d=elvado_cm_read($dataDir,'social.json',[]);
    $f=[];foreach((array)($d['follows']??[]) as $k=>$v)if(is_array($v))$f[(string)$k]=array_values(array_map('strval',$v));
    return ['posts'=>array_values((array)($d['posts']??[])),'follows'=>$f];
}
function elvado_so_save(string $dataDir, array $s): void { elvado_cm_write($dataDir,'social.json',['posts'=>array_values($s['posts']),'follows'=>(object)$s['follows']]); }

/* ───────── Beiträge ───────── */
function elvado_so_post(string $dataDir, array $member, string $bodyIn, ?int $now=null): array {
    $fail=fn(string $m,int $c=400)=>['ok'=>false,'message'=>$m,'code'=>$c];$now=$now??time();
    $body=elvado_fo_text($bodyIn,500);if(mb_strlen($body)<2)return $fail('Bitte schreibe etwas (mindestens 2 Zeichen).');
    if(!elvado_cm_rate($dataDir,'so-post|'.$member['id'],10,600,$now))return $fail('Du postest zu schnell – bitte warte kurz.',429);
    return elvado_cm_locked($dataDir,function() use($dataDir,$member,$body,$now,$fail){
        $s=elvado_so_load($dataDir);
        for($i=count($s['posts'])-1;$i>=0&&$i>=count($s['posts'])-30;$i--)if($s['posts'][$i]['author']===(string)$member['id']){if($s['posts'][$i]['body']===$body)return $fail('Diesen Beitrag hast du bereits gepostet.',409);break;}
        $id=elvado_fo_newid('r');$s['posts'][]=['id'=>$id,'author'=>(string)$member['id'],'body'=>$body,'created'=>gmdate('Y-m-d H:i:s',$now),'likes'=>[]];
        if(count($s['posts'])>ELVADO_SO_MAX_POSTS)$s['posts']=array_slice($s['posts'],-ELVADO_SO_MAX_POSTS);
        elvado_so_save($dataDir,$s);return ['ok'=>true,'message'=>'Gepostet','code'=>200,'id'=>$id];
    });
}
function elvado_so_delete(string $dataDir, ?array $member, bool $isMod, string $pid): array {
    return elvado_cm_locked($dataDir,function() use($dataDir,$member,$isMod,$pid){
        $s=elvado_so_load($dataDir);$i=elvado_fo_find($s['posts'],$pid);if($i===null)return ['ok'=>false,'message'=>'Beitrag nicht gefunden.','code'=>404];
        if(!$isMod&&!($member&&$s['posts'][$i]['author']===(string)$member['id']))return ['ok'=>false,'message'=>'Dazu fehlt dir die Berechtigung.','code'=>403];
        array_splice($s['posts'],$i,1);elvado_so_save($dataDir,$s);return ['ok'=>true,'message'=>'Beitrag gelöscht','code'=>200];
    });
}
/** Like an/aus (Wechsel). Eigene Beiträge dürfen geliked werden, jedes Mitglied höchstens einmal. */
function elvado_so_like(string $dataDir, array $member, string $pid): array {
    if(!elvado_cm_rate($dataDir,'so-like|'.$member['id'],60,600))return ['ok'=>false,'message'=>'Zu viele Likes – bitte kurz warten.','code'=>429];
    return elvado_cm_locked($dataDir,function() use($dataDir,$member,$pid){
        $s=elvado_so_load($dataDir);$i=elvado_fo_find($s['posts'],$pid);if($i===null)return ['ok'=>false,'message'=>'Beitrag nicht gefunden.','code'=>404];
        $mid=(string)$member['id'];$likes=array_map('strval',(array)($s['posts'][$i]['likes']??[]));
        $on=!in_array($mid,$likes,true);
        $likes=$on?array_merge($likes,[$mid]):array_values(array_diff($likes,[$mid]));
        $s['posts'][$i]['likes']=array_values($likes);elvado_so_save($dataDir,$s);
        return ['ok'=>true,'message'=>'Gespeichert','code'=>200,'liked'=>$on,'likes'=>count($likes)];
    });
}

/* ───────── Folgen ───────── */
function elvado_so_follow(string $dataDir, array $member, string $targetId): array {
    $mid=(string)$member['id'];
    if($targetId===$mid)return ['ok'=>false,'message'=>'Du kannst dir nicht selbst folgen.','code'=>400];
    if(!elvado_cm_rate($dataDir,'so-follow|'.$mid,40,600))return ['ok'=>false,'message'=>'Zu viele Aktionen – bitte kurz warten.','code'=>429];
    return elvado_cm_locked($dataDir,function() use($dataDir,$mid,$targetId){
        $ms=elvado_cm_members($dataDir);$ti=elvado_cm_find($ms,'id',$targetId);
        if($ti===null||($ms[$ti]['status']??'')!=='active')return ['ok'=>false,'message'=>'Mitglied nicht gefunden.','code'=>404];
        $s=elvado_so_load($dataDir);$list=$s['follows'][$mid]??[];$on=!in_array($targetId,$list,true);
        if($on){if(count($list)>=ELVADO_SO_MAX_FOLLOWS)return ['ok'=>false,'message'=>'Du folgst bereits sehr vielen Mitgliedern.','code'=>400];$list[]=$targetId;}
        else $list=array_values(array_diff($list,[$targetId]));
        if($list)$s['follows'][$mid]=array_values($list);else unset($s['follows'][$mid]);
        elvado_so_save($dataDir,$s);return ['ok'=>true,'message'=>'Gespeichert','code'=>200,'following'=>$on];
    });
}

/* ───────── Lesen ───────── */
function elvado_so_shape(array $p, array $people, ?string $viewer): array {
    $a=elvado_fo_author($people,(string)$p['author']);$likes=array_map('strval',(array)($p['likes']??[]));
    return ['id'=>$p['id'],'body'=>$p['body'],'created'=>$p['created'],'author'=>$a['name'],'username'=>$a['username'],'author_id'=>$a['id'],'role'=>$a['role'],'likes'=>count($likes),'liked'=>$viewer!==null&&in_array($viewer,$likes,true)];
}
/** Feed. $mode: all (alle Beiträge) | following (eigene + gefolgte) | user (nur ein Mitglied). Seitenweise per Cursor `before` (Beitrags-ID, ältere davon). */
function elvado_so_feed(string $dataDir, string $mode, ?array $viewer, string $userId='', string $before=''): array {
    $s=elvado_so_load($dataDir);$people=elvado_fo_people($dataDir);$vid=$viewer?(string)$viewer['id']:null;
    $posts=array_reverse($s['posts']);
    if($mode==='following'){$set=array_flip(array_merge([$vid??''],$vid?($s['follows'][$vid]??[]):[]));$posts=array_values(array_filter($posts,fn($p)=>isset($set[$p['author']])));}
    elseif($mode==='user')$posts=array_values(array_filter($posts,fn($p)=>$p['author']===$userId));
    if($before!==''){$i=elvado_fo_find($posts,$before);$posts=$i===null?[]:array_slice($posts,$i+1);}
    $page=array_slice($posts,0,ELVADO_SO_PER_PAGE);
    return ['items'=>array_map(fn($p)=>elvado_so_shape($p,$people,$vid),$page),'more'=>count($posts)>ELVADO_SO_PER_PAGE];
}
function elvado_so_profile(string $dataDir, string $username, ?array $viewer): ?array {
    $ms=elvado_cm_members($dataDir);$i=elvado_cm_find($ms,'username',$username);
    if($i===null||($ms[$i]['status']??'')!=='active')return null;
    $m=$ms[$i];$id=(string)$m['id'];$s=elvado_so_load($dataDir);
    $followers=0;foreach($s['follows'] as $list)if(in_array($id,$list,true))$followers++;
    $count=0;foreach($s['posts'] as $p)if($p['author']===$id)$count++;
    $vid=$viewer?(string)$viewer['id']:null;
    return ['member'=>elvado_cm_public($m),'followers'=>$followers,'following'=>count($s['follows'][$id]??[]),'posts'=>$count,'is_following'=>$vid!==null&&in_array($id,$s['follows'][$vid]??[],true),'is_me'=>$vid===$id];
}

/* ───────── Datenschutz: Konto gelöscht → alles Soziale entfernen ───────── */
function elvado_so_forget(string $dataDir, string $memberId): void {
    elvado_cm_locked($dataDir,function() use($dataDir,$memberId){
        $s=elvado_so_load($dataDir);
        $s['posts']=array_values(array_filter($s['posts'],fn($p)=>$p['author']!==$memberId));
        foreach($s['posts'] as &$p)$p['likes']=array_values(array_diff(array_map('strval',(array)($p['likes']??[])),[$memberId]));unset($p);
        unset($s['follows'][$memberId]);
        foreach($s['follows'] as $k=>$list){$l=array_values(array_diff($list,[$memberId]));if($l)$s['follows'][$k]=$l;else unset($s['follows'][$k]);}
        elvado_so_save($dataDir,$s);
    });
}

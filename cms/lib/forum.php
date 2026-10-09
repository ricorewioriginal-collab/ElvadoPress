<?php
declare(strict_types=1);
// Forum des Community-Moduls (optional). Lesen ist öffentlich, Schreiben nur für angemeldete Mitglieder.
// Moderation: Mitglieder mit Rolle „moderator“ und CMS-Administratoren. Beiträge sind reiner Text (kein HTML).
//  forum.json            Kategorien, Themenübersicht, Meldungen
//  forum-<themaId>.json  Beiträge eines Themas
require_once __DIR__.'/community.php';

const ELVADO_FO_PER_PAGE=20;

function elvado_fo_load(string $dataDir): array {
    $d=elvado_cm_read($dataDir,'forum.json',[]);
    return ['categories'=>array_values((array)($d['categories']??[])),'topics'=>array_values((array)($d['topics']??[])),'reports'=>array_values((array)($d['reports']??[]))];
}
function elvado_fo_save(string $dataDir, array $f): void { elvado_cm_write($dataDir,'forum.json',$f); }
function elvado_fo_id_ok(string $id): bool { return (bool)preg_match('/^[tcr][a-f0-9]{10}$/',$id); }
function elvado_fo_newid(string $p): string { return $p.bin2hex(random_bytes(5)); }
function elvado_fo_posts(string $dataDir, string $tid): array {
    if(!elvado_fo_id_ok($tid))return [];
    $d=elvado_cm_read($dataDir,'forum-'.$tid.'.json',[]);return array_values((array)($d['posts']??[]));
}
function elvado_fo_posts_save(string $dataDir, string $tid, array $posts): void { elvado_cm_write($dataDir,'forum-'.$tid.'.json',['posts'=>array_values($posts)]); }
function elvado_fo_text($s, int $max): string {
    $s=str_replace(["\r\n","\r"],"\n",(string)$s);
    $s=(string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u','',$s);
    $s=trim((string)preg_replace("/\n{4,}/","\n\n\n",$s));
    return mb_substr($s,0,$max);
}
function elvado_fo_find(array $list, string $id): ?int { foreach($list as $i=>$x)if(($x['id']??'')===$id)return $i;return null; }

/* ───────── Kategorien (Verwaltung) ───────── */
function elvado_fo_categories_clean($in, array $old=[]): array {
    $out=[];$seen=[];$oldIds=array_column($old,'id');
    foreach((is_array($in)?$in:[]) as $c){
        if(!is_array($c))continue;$name=trim(strip_tags((string)($c['name']??'')));if(mb_strlen($name)<2)continue;
        $id=(string)($c['id']??'');if(!elvado_fo_id_ok($id)||$id[0]!=='c'||(!in_array($id,$oldIds,true)&&false)||isset($seen[$id]))$id=elvado_fo_newid('c');
        $seen[$id]=1;$out[]=['id'=>$id,'name'=>mb_substr($name,0,60),'desc'=>mb_substr(trim(strip_tags((string)($c['desc']??''))),0,200)];
        if(count($out)>=20)break;
    }
    return $out;
}
/** Speichert Kategorien; Themen aus entfernten Kategorien wandern in die erste verbleibende (oder werden ausgeblendet, wenn keine bleibt). */
function elvado_fo_categories_save(string $dataDir, $in): array {
    return elvado_cm_locked($dataDir,function() use($dataDir,$in){
        $f=elvado_fo_load($dataDir);$f['categories']=elvado_fo_categories_clean($in,$f['categories']);
        $ids=array_column($f['categories'],'id');$first=$ids[0]??'';
        foreach($f['topics'] as &$t)if(!in_array($t['cat'],$ids,true))$t['cat']=$first;unset($t);
        elvado_fo_save($dataDir,$f);return $f['categories'];
    });
}

/* ───────── Namen/Rollen ───────── */
function elvado_fo_people(string $dataDir): array {
    $o=[];foreach(elvado_cm_members($dataDir) as $m)$o[(string)$m['id']]=['id'=>(string)$m['id'],'name'=>(string)($m['display_name']??$m['username']),'username'=>(string)$m['username'],'role'=>(string)($m['role']??'member')];
    return $o;
}
function elvado_fo_author(array $people, string $id): array {
    return $people[$id]??['id'=>'','name'=>'Gelöschtes Mitglied','username'=>'','role'=>'member'];
}

/* ───────── Lesen ───────── */
function elvado_fo_overview(string $dataDir): array {
    $f=elvado_fo_load($dataDir);$cats=[];
    foreach($f['categories'] as $c){
        $n=0;$posts=0;$last=null;
        foreach($f['topics'] as $t)if($t['cat']===$c['id']){$n++;$posts+=(int)$t['replies'];if($last===null||$t['last']>$last['last'])$last=$t;}
        $cats[]=$c+['topics'=>$n,'replies'=>$posts,'last'=>$last?['title'=>$last['title'],'id'=>$last['id'],'at'=>$last['last']]:null];
    }
    return $cats;
}
function elvado_fo_topics(string $dataDir, string $cat, int $page): array {
    $f=elvado_fo_load($dataDir);$people=elvado_fo_people($dataDir);
    $list=array_values(array_filter($f['topics'],fn($t)=>$t['cat']===$cat));
    usort($list,fn($a,$b)=>[(int)!empty($b['pinned']),$b['last']]<=>[(int)!empty($a['pinned']),$a['last']]);
    $pages=max(1,(int)ceil(count($list)/ELVADO_FO_PER_PAGE));$page=min(max(1,$page),$pages);
    $items=array_map(fn($t)=>['id'=>$t['id'],'title'=>$t['title'],'author'=>elvado_fo_author($people,(string)$t['author'])['name'],'created'=>$t['created'],'last'=>$t['last'],'replies'=>(int)$t['replies'],'pinned'=>!empty($t['pinned']),'locked'=>!empty($t['locked'])],array_slice($list,($page-1)*ELVADO_FO_PER_PAGE,ELVADO_FO_PER_PAGE));
    return ['items'=>$items,'page'=>$page,'pages'=>$pages];
}
function elvado_fo_topic(string $dataDir, string $id, int $page): ?array {
    $f=elvado_fo_load($dataDir);$i=elvado_fo_find($f['topics'],$id);if($i===null)return null;$t=$f['topics'][$i];
    $people=elvado_fo_people($dataDir);$posts=elvado_fo_posts($dataDir,$id);
    $pages=max(1,(int)ceil(count($posts)/ELVADO_FO_PER_PAGE));$page=min(max(1,$page),$pages);
    $items=array_map(function($p) use($people){ $a=elvado_fo_author($people,(string)$p['author']);return ['id'=>$p['id'],'body'=>$p['body'],'created'=>$p['created'],'edited'=>$p['edited']??null,'author'=>$a['name'],'author_id'=>$a['id'],'role'=>$a['role']];},array_slice($posts,($page-1)*ELVADO_FO_PER_PAGE,ELVADO_FO_PER_PAGE));
    $cat='';foreach($f['categories'] as $c)if($c['id']===$t['cat'])$cat=$c['name'];
    return ['topic'=>['id'=>$t['id'],'title'=>$t['title'],'cat'=>$t['cat'],'cat_name'=>$cat,'pinned'=>!empty($t['pinned']),'locked'=>!empty($t['locked']),'replies'=>(int)$t['replies']],'posts'=>$items,'page'=>$page,'pages'=>$pages];
}

/* ───────── Schreiben ───────── */
function elvado_fo_topic_create(string $dataDir, array $member, array $in, ?int $now=null): array {
    $fail=fn(string $m,int $c=400)=>['ok'=>false,'message'=>$m,'code'=>$c];$now=$now??time();
    $title=elvado_fo_text(preg_replace('/\s+/u',' ',(string)($in['title']??'')),120);$body=elvado_fo_text($in['body']??'',5000);$cat=(string)($in['cat']??'');
    if(mb_strlen($title)<3)return $fail('Der Titel ist zu kurz (mindestens 3 Zeichen).');
    if(mb_strlen($body)<2)return $fail('Bitte schreibe einen Beitrag.');
    if(!elvado_cm_rate($dataDir,'fo-topic|'.$member['id'],3,600,$now))return $fail('Du erstellst Themen zu schnell – bitte warte einige Minuten.',429);
    return elvado_cm_locked($dataDir,function() use($dataDir,$member,$title,$body,$cat,$now,$fail){
        $f=elvado_fo_load($dataDir);if(!in_array($cat,array_column($f['categories'],'id'),true))return $fail('Kategorie nicht gefunden.',404);
        $id=elvado_fo_newid('t');$ts=gmdate('Y-m-d H:i:s',$now);
        $f['topics'][]=['id'=>$id,'cat'=>$cat,'title'=>$title,'author'=>(string)$member['id'],'created'=>$ts,'last'=>$ts,'replies'=>0,'pinned'=>false,'locked'=>false];
        if(count($f['topics'])>5000)return $fail('Das Forum ist voll.',507);
        elvado_fo_posts_save($dataDir,$id,[['id'=>elvado_fo_newid('r'),'author'=>(string)$member['id'],'body'=>$body,'created'=>$ts]]);
        elvado_fo_save($dataDir,$f);return ['ok'=>true,'message'=>'Thema erstellt','code'=>200,'id'=>$id];
    });
}
function elvado_fo_reply(string $dataDir, array $member, string $tid, string $bodyIn, bool $isMod=false, ?int $now=null): array {
    $fail=fn(string $m,int $c=400)=>['ok'=>false,'message'=>$m,'code'=>$c];$now=$now??time();$body=elvado_fo_text($bodyIn,5000);
    if(mb_strlen($body)<2)return $fail('Bitte schreibe einen Beitrag.');
    if(!elvado_cm_rate($dataDir,'fo-post|'.$member['id'],10,600,$now))return $fail('Du schreibst zu schnell – bitte warte kurz.',429);
    return elvado_cm_locked($dataDir,function() use($dataDir,$member,$tid,$body,$isMod,$now,$fail){
        $f=elvado_fo_load($dataDir);$i=elvado_fo_find($f['topics'],$tid);if($i===null)return $fail('Thema nicht gefunden.',404);
        if(!empty($f['topics'][$i]['locked'])&&!$isMod)return $fail('Dieses Thema ist geschlossen.',403);
        $posts=elvado_fo_posts($dataDir,$tid);if(count($posts)>=2000)return $fail('Dieses Thema ist voll.',507);
        $last=end($posts);if($last&&($last['author']??'')===(string)$member['id']&&($last['body']??'')===$body)return $fail('Diesen Beitrag hast du bereits gesendet.',409);
        $ts=gmdate('Y-m-d H:i:s',$now);$pid=elvado_fo_newid('r');
        $posts[]=['id'=>$pid,'author'=>(string)$member['id'],'body'=>$body,'created'=>$ts];elvado_fo_posts_save($dataDir,$tid,$posts);
        $f['topics'][$i]['replies']=count($posts)-1;$f['topics'][$i]['last']=$ts;elvado_fo_save($dataDir,$f);
        return ['ok'=>true,'message'=>'Antwort gespeichert','code'=>200,'id'=>$pid,'pages'=>max(1,(int)ceil(count($posts)/ELVADO_FO_PER_PAGE))];
    });
}
function elvado_fo_edit(string $dataDir, array $member, string $tid, string $pid, string $bodyIn, ?int $now=null): array {
    $fail=fn(string $m,int $c=400)=>['ok'=>false,'message'=>$m,'code'=>$c];$now=$now??time();$body=elvado_fo_text($bodyIn,5000);
    if(mb_strlen($body)<2)return $fail('Bitte schreibe einen Beitrag.');
    return elvado_cm_locked($dataDir,function() use($dataDir,$member,$tid,$pid,$body,$now,$fail){
        $posts=elvado_fo_posts($dataDir,$tid);$i=elvado_fo_find($posts,$pid);if($i===null)return $fail('Beitrag nicht gefunden.',404);
        if(($posts[$i]['author']??'')!==(string)$member['id'])return $fail('Du kannst nur eigene Beiträge bearbeiten.',403);
        if(strtotime($posts[$i]['created'].' UTC')<$now-1800)return $fail('Beiträge können nur 30 Minuten lang bearbeitet werden.',403);
        $posts[$i]['body']=$body;$posts[$i]['edited']=gmdate('Y-m-d H:i:s',$now);elvado_fo_posts_save($dataDir,$tid,$posts);
        return ['ok'=>true,'message'=>'Gespeichert','code'=>200];
    });
}
/** Entfernt einen Beitrag (eigener oder als Moderator). Der erste Beitrag entfernt das ganze Thema – für Autoren nur, solange niemand geantwortet hat. */
function elvado_fo_delete_post(string $dataDir, ?array $member, bool $isMod, string $tid, string $pid): array {
    $fail=fn(string $m,int $c=400)=>['ok'=>false,'message'=>$m,'code'=>$c];
    return elvado_cm_locked($dataDir,function() use($dataDir,$member,$isMod,$tid,$pid,$fail){
        $f=elvado_fo_load($dataDir);$ti=elvado_fo_find($f['topics'],$tid);if($ti===null)return $fail('Thema nicht gefunden.',404);
        $posts=elvado_fo_posts($dataDir,$tid);$i=elvado_fo_find($posts,$pid);if($i===null)return $fail('Beitrag nicht gefunden.',404);
        $own=$member&&($posts[$i]['author']??'')===(string)$member['id'];
        if(!$isMod&&!$own)return $fail('Dazu fehlt dir die Berechtigung.',403);
        if($i===0){
            if(!$isMod&&count($posts)>1)return $fail('Das Thema hat Antworten – bitte wende dich an einen Moderator.',403);
            return elvado_fo_drop_topic($dataDir,$f,$tid);
        }
        array_splice($posts,$i,1);elvado_fo_posts_save($dataDir,$tid,$posts);
        $f['topics'][$ti]['replies']=count($posts)-1;$f['reports']=array_values(array_filter($f['reports'],fn($r)=>$r['post']!==$pid));elvado_fo_save($dataDir,$f);
        return ['ok'=>true,'message'=>'Beitrag gelöscht','code'=>200];
    });
}
function elvado_fo_drop_topic(string $dataDir, array $f, string $tid): array {
    $f['topics']=array_values(array_filter($f['topics'],fn($t)=>$t['id']!==$tid));
    $f['reports']=array_values(array_filter($f['reports'],fn($r)=>$r['topic']!==$tid));
    elvado_fo_save($dataDir,$f);@unlink(elvado_cm_dir($dataDir).'/forum-'.$tid.'.json');
    return ['ok'=>true,'message'=>'Thema gelöscht','code'=>200,'topic_deleted'=>true];
}

/* ───────── Moderation ───────── */
function elvado_fo_mod(string $dataDir, string $op, string $tid, string $arg=''): array {
    $fail=fn(string $m,int $c=400)=>['ok'=>false,'message'=>$m,'code'=>$c];
    if(!in_array($op,['pin','unpin','lock','unlock','move','delete'],true))return $fail('Ungültige Aktion');
    return elvado_cm_locked($dataDir,function() use($dataDir,$op,$tid,$arg,$fail){
        $f=elvado_fo_load($dataDir);$i=elvado_fo_find($f['topics'],$tid);if($i===null)return $fail('Thema nicht gefunden.',404);
        if($op==='delete')return elvado_fo_drop_topic($dataDir,$f,$tid);
        if($op==='pin'||$op==='unpin')$f['topics'][$i]['pinned']=$op==='pin';
        if($op==='lock'||$op==='unlock')$f['topics'][$i]['locked']=$op==='lock';
        if($op==='move'){if(!in_array($arg,array_column($f['categories'],'id'),true))return $fail('Kategorie nicht gefunden.',404);$f['topics'][$i]['cat']=$arg;}
        elvado_fo_save($dataDir,$f);return ['ok'=>true,'message'=>'Gespeichert','code'=>200];
    });
}
function elvado_fo_report(string $dataDir, array $member, string $tid, string $pid, string $reason, ?int $now=null): array {
    $fail=fn(string $m,int $c=400)=>['ok'=>false,'message'=>$m,'code'=>$c];$now=$now??time();
    if(!elvado_cm_rate($dataDir,'fo-report|'.$member['id'],5,600,$now))return $fail('Zu viele Meldungen – bitte warte kurz.',429);
    return elvado_cm_locked($dataDir,function() use($dataDir,$member,$tid,$pid,$reason,$now,$fail){
        $f=elvado_fo_load($dataDir);if(elvado_fo_find($f['topics'],$tid)===null||elvado_fo_find(elvado_fo_posts($dataDir,$tid),$pid)===null)return $fail('Beitrag nicht gefunden.',404);
        foreach($f['reports'] as $r)if($r['post']===$pid&&$r['by']===(string)$member['id'])return ['ok'=>true,'message'=>'Danke, du hast diesen Beitrag bereits gemeldet.','code'=>200];
        $f['reports'][]=['id'=>elvado_fo_newid('r'),'topic'=>$tid,'post'=>$pid,'by'=>(string)$member['id'],'reason'=>mb_substr(trim(strip_tags($reason)),0,200),'created'=>gmdate('Y-m-d H:i:s',$now)];
        if(count($f['reports'])>500)$f['reports']=array_slice($f['reports'],-500);
        elvado_fo_save($dataDir,$f);return ['ok'=>true,'message'=>'Danke, die Meldung wurde an die Moderation gesendet.','code'=>200];
    });
}
function elvado_fo_reports(string $dataDir): array {
    $f=elvado_fo_load($dataDir);$people=elvado_fo_people($dataDir);$out=[];
    foreach($f['reports'] as $r){
        $ti=elvado_fo_find($f['topics'],$r['topic']);if($ti===null)continue;$posts=elvado_fo_posts($dataDir,$r['topic']);$pi=elvado_fo_find($posts,$r['post']);if($pi===null)continue;
        $out[]=['id'=>$r['id'],'topic'=>$r['topic'],'topic_title'=>$f['topics'][$ti]['title'],'post'=>$r['post'],'excerpt'=>mb_substr($posts[$pi]['body'],0,300),'post_author'=>elvado_fo_author($people,(string)$posts[$pi]['author'])['name'],'by'=>elvado_fo_author($people,(string)$r['by'])['name'],'reason'=>$r['reason'],'created'=>$r['created']];
    }
    return array_reverse($out);
}
/** Meldung als erledigt entfernen (ohne den Beitrag zu löschen). */
function elvado_fo_report_dismiss(string $dataDir, string $rid): array {
    return elvado_cm_locked($dataDir,function() use($dataDir,$rid){
        $f=elvado_fo_load($dataDir);$f['reports']=array_values(array_filter($f['reports'],fn($r)=>$r['id']!==$rid));elvado_fo_save($dataDir,$f);
        return ['ok'=>true,'message'=>'Meldung erledigt','code'=>200];
    });
}

/* ───────── Datenschutz: Konto gelöscht → Beiträge anonymisieren ───────── */
function elvado_cm_anonymize_member(string $dataDir, string $memberId): void {
    elvado_cm_locked($dataDir,function() use($dataDir,$memberId){
        $f=elvado_fo_load($dataDir);
        foreach($f['topics'] as &$t){
            if($t['author']===$memberId)$t['author']='';
            $posts=elvado_fo_posts($dataDir,$t['id']);$ch=false;
            foreach($posts as &$p)if(($p['author']??'')===$memberId){$p['author']='';$ch=true;}unset($p);
            if($ch)elvado_fo_posts_save($dataDir,$t['id'],$posts);
        }unset($t);
        $f['reports']=array_values(array_filter($f['reports'],fn($r)=>$r['by']!==$memberId));
        elvado_fo_save($dataDir,$f);
    });
    if(function_exists('elvado_so_forget'))elvado_so_forget($dataDir,$memberId);
}

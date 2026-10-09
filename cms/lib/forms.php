<?php
declare(strict_types=1);
// Formulare für das CMS (Kontaktformular- und Newsletter-Widget): Einsendungen landen im CMS-Postfach (Einsendungen),
// optional zusätzlich per E-Mail. Missbrauchsschutz: nur für vorhandene Widgets, Honeypot, Zeitprüfung, Rate-Limit je
// Besucher (nur ein täglich wechselnder Hash der IP wird gespeichert, nie die IP selbst).
// Daten: cms/data/.tools/forms.json (Ordner ist per .htaccess gesperrt).
require_once __DIR__.'/tools.php';

function elvado_forms_file(string $dataDir): string { return elvado_tools_dir($dataDir).'/forms.json'; }
function elvado_forms_load(string $dataDir): array {
    $d=elvado_tools_read(elvado_forms_file($dataDir),['items'=>[]]);
    return is_array($d['items']??null)?array_values($d['items']):[];
}
function elvado_forms_save(string $dataDir, array $items): void {
    if(count($items)>5000)$items=array_slice($items,-5000);
    elvado_tools_write(elvado_tools_dir($dataDir),'forms.json',['items'=>array_values($items)]);
}
/** Findet die Widget-Instanz (Typ contact-form/newsletter) mit dieser ID in den Widget-Bereichen. */
function elvado_forms_find_widget(array $site, string $id): ?array {
    if(!preg_match('/^[a-zA-Z0-9_-]{1,80}$/',$id))return null;
    $lists=[(array)($site['widget_inactive']??[])];
    foreach((array)($site['widget_areas']??[]) as $a)if(is_array($a))$lists[]=(array)($a['widgets']??[]);
    foreach($lists as $l)foreach($l as $w)if(is_array($w)&&($w['id']??'')===$id&&in_array($w['type']??'',['contact-form','newsletter'],true))return $w;
    return null;
}
function elvado_forms_clean(string $kind, array $in): array {
    $t=fn($k,$n)=>mb_substr(trim(strip_tags((string)($in[$k]??''))),0,$n);
    $email=mb_substr(trim((string)($in['email']??'')),0,120);
    if(!filter_var($email,FILTER_VALIDATE_EMAIL))return ['ok'=>false,'error'=>'Bitte eine gültige E-Mail-Adresse eingeben.'];
    if(empty($in['consent']))return ['ok'=>false,'error'=>'Bitte stimme der Datenverarbeitung zu.'];
    if($kind==='newsletter')return ['ok'=>true,'data'=>['email'=>$email]];
    $name=$t('name',80);$msg=$t('message',3000);
    if($name==='')return ['ok'=>false,'error'=>'Bitte gib deinen Namen an.'];
    if(mb_strlen($msg)<5)return ['ok'=>false,'error'=>'Bitte schreibe eine Nachricht.'];
    return ['ok'=>true,'data'=>['name'=>$name,'email'=>$email,'subject'=>$t('subject',120),'message'=>$msg]];
}
function elvado_forms_ip_hash(string $dataDir, string $ip): string {
    $f=elvado_tools_dir($dataDir).'/forms-salt.json';$s=elvado_tools_read($f,[]);
    if(empty($s['salt'])){$s=['salt'=>bin2hex(random_bytes(16))];try{elvado_tools_write(elvado_tools_dir($dataDir),'forms-salt.json',$s);}catch(Throwable $e){}}
    return hash_hmac('sha256',$ip.'|'.date('Y-m-d'),(string)$s['salt']);
}
/** Höchstens 5 Einsendungen je Besucher und Stunde (und 20 pro Tag). */
function elvado_forms_rate_ok(string $dataDir, string $hash, ?int $now=null): bool {
    $now=$now??time();$dir=elvado_tools_dir($dataDir);
    $h=@fopen($dir.'/.forms.lock','c');if(!$h)return true;
    try{
        if(!@flock($h,LOCK_EX))return true;
        $r=elvado_tools_read($dir.'/forms-rate.json',[]);
        foreach($r as $k=>$ts){$r[$k]=array_values(array_filter((array)$ts,fn($x)=>$x>$now-86400));if(!$r[$k])unset($r[$k]);}
        $mine=(array)($r[$hash]??[]);
        $lastHour=count(array_filter($mine,fn($x)=>$x>$now-3600));
        if($lastHour>=5||count($mine)>=20){elvado_tools_write($dir,'forms-rate.json',$r);return false;}
        $mine[]=$now;$r[$hash]=$mine;
        if(count($r)>3000)$r=array_slice($r,-3000,null,true);
        elvado_tools_write($dir,'forms-rate.json',$r);
        return true;
    }catch(Throwable $e){return true;}
    finally{@flock($h,LOCK_UN);@fclose($h);}
}
/** Nimmt eine Einsendung entgegen. Rückgabe: ['ok'=>bool,'message'=>string,'code'=>int]. */
function elvado_forms_submit(string $dataDir, array $site, array $body, string $ip): array {
    $fail=fn(string $m,int $c=400)=>['ok'=>false,'message'=>$m,'code'=>$c];
    if(trim((string)($body['hp']??''))!=='')return ['ok'=>true,'message'=>'Danke!','code'=>200];                // Honeypot: Bots bekommen scheinbar Erfolg
    $w=elvado_forms_find_widget($site,(string)($body['widget_id']??''));if(!$w)return $fail('Formular nicht gefunden',404);
    $kind=$w['type']==='newsletter'?'newsletter':'contact';$s=(array)($w['settings']??[]);
    $opened=(int)($body['opened']??0);if($opened>0&&time()-intdiv($opened,1000)<2)return $fail('Bitte einen Moment warten und erneut senden.',429);   // zu schnell = Bot
    $c=elvado_forms_clean($kind,$body);if(!$c['ok'])return $fail($c['error']);
    if(!elvado_forms_rate_ok($dataDir,elvado_forms_ip_hash($dataDir,$ip)))return $fail('Zu viele Einsendungen – bitte später erneut versuchen.',429);
    $items=elvado_forms_load($dataDir);
    if($kind==='newsletter')foreach($items as $it)if(($it['kind']??'')==='newsletter'&&strcasecmp((string)($it['data']['email']??''),$c['data']['email'])===0)
        return ['ok'=>true,'message'=>(string)($s['success']??'Danke für deine Anmeldung!'),'code'=>200];                 // schon eingetragen, nichts verraten
    $items[]=['id'=>bin2hex(random_bytes(6)),'created_at'=>date('Y-m-d H:i:s'),'kind'=>$kind,'widget_id'=>(string)$w['id'],'widget_title'=>mb_substr((string)($w['title']??''),0,80),'read'=>false,'data'=>$c['data']];
    try{elvado_forms_save($dataDir,$items);}catch(Throwable $e){return $fail('Speichern fehlgeschlagen – bitte später erneut versuchen.',500);}
    $to=trim((string)($s['notify']??''));
    if($to!==''&&function_exists('elvado_send_mail')){
        $d=$c['data'];$subject=($kind==='newsletter'?'Neue Newsletter-Anmeldung':'Neue Nachricht über das Kontaktformular');
        $text=$kind==='newsletter'?"Neue Anmeldung: {$d['email']}\n":"Von: {$d['name']} <{$d['email']}>\n".($d['subject']!==''?"Betreff: {$d['subject']}\n":'')."\n{$d['message']}\n";
        elvado_send_mail($to,$subject,$text."\n-- \nIm CMS unter Inhalte → Einsendungen.",elvado_mail_from($site));
    }
    return ['ok'=>true,'message'=>(string)($s['success']??($kind==='newsletter'?'Danke für deine Anmeldung!':'Danke, deine Nachricht ist angekommen.')),'code'=>200];
}
function elvado_forms_csv(array $items, string $kind): string {
    $q=fn($v)=>'"'.str_replace('"','""',preg_replace('/^([=+\-@])/','\'$1',(string)$v)).'"';   // Excel-Formel-Injektion vermeiden
    $out="\xEF\xBB\xBF";
    if($kind==='newsletter'){$out.="Datum;E-Mail;Formular\r\n";foreach($items as $i)if(($i['kind']??'')==='newsletter')$out.=implode(';',[$q($i['created_at']??''),$q($i['data']['email']??''),$q($i['widget_title']??'')])."\r\n";}
    else{$out.="Datum;Name;E-Mail;Betreff;Nachricht;Formular\r\n";foreach($items as $i)if(($i['kind']??'')==='contact')$out.=implode(';',[$q($i['created_at']??''),$q($i['data']['name']??''),$q($i['data']['email']??''),$q($i['data']['subject']??''),$q($i['data']['message']??''),$q($i['widget_title']??'')])."\r\n";}
    return $out;
}

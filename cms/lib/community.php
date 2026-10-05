<?php
declare(strict_types=1);
// Community-Modul des CMS (optional, standardmäßig AUS): Mitglieder mit Registrierung/Anmeldung, später Forum und soziales Netzwerk.
// Eigenständig gedacht – nutzt nichts vom Radio. Daten: cms/data/.community/ (per .htaccess gesperrt, nicht im Repo).
//  config.json    Einstellungen         members.json   Mitglieder (Passwörter nur als Hash)
//  sessions.json  angemeldete Geräte (nur SHA-256 der Tokens)   resets.json  Passwort-Zurücksetzen (nur Hash, 1 Stunde)
//  ratelimit.json Zähler gegen Missbrauch (nur gesalzene Hashes, nie IP im Klartext)
require_once __DIR__.'/tools.php';

function rrw_cm_dir(string $dataDir): string { return rtrim($dataDir,'/').'/.community'; }
function rrw_cm_read(string $dataDir, string $name, array $fallback): array { return rrw_tools_read(rrw_cm_dir($dataDir).'/'.$name,$fallback); }
function rrw_cm_write(string $dataDir, string $name, array $data): void { rrw_tools_write(rrw_cm_dir($dataDir),$name,$data); }

/** Exklusive Sperre für Lese-ändern-Schreiben-Abläufe (Registrierung, Anmeldung, Beiträge). */
function rrw_cm_locked(string $dataDir, callable $fn) {
    $dir=rrw_cm_dir($dataDir);if(!is_dir($dir))@mkdir($dir,0775,true);rrw_protect_dir($dir);
    $h=@fopen($dir.'/.lock','c');if(!$h)return $fn();
    try{@flock($h,LOCK_EX);return $fn();}finally{@flock($h,LOCK_UN);@fclose($h);}
}

/* ───────── Einstellungen ───────── */
function rrw_cm_config_defaults(): array {
    return ['enabled'=>false,'registration'=>'open','forum'=>true,'social'=>true,'min_password'=>8,'rules'=>'','session_days'=>30];
}
function rrw_cm_config_clean($in): array {
    $in=is_array($in)?$in:[];$d=rrw_cm_config_defaults();
    return [
        'enabled'=>!empty($in['enabled']),
        'registration'=>in_array((string)($in['registration']??''),['open','approval','closed'],true)?(string)$in['registration']:'open',
        'forum'=>!array_key_exists('forum',$in)||!empty($in['forum']),
        'social'=>!array_key_exists('social',$in)||!empty($in['social']),
        'min_password'=>max(8,min(64,(int)($in['min_password']??$d['min_password']))),
        'rules'=>mb_substr(trim(strip_tags((string)($in['rules']??''))),0,2000),
        'session_days'=>max(1,min(365,(int)($in['session_days']??$d['session_days']))),
    ];
}
function rrw_cm_config(string $dataDir): array { return rrw_cm_config_clean(rrw_cm_read($dataDir,'config.json',[])+rrw_cm_config_defaults()); }
function rrw_cm_public_config(array $cfg): array {
    return ['enabled'=>$cfg['enabled'],'registration'=>$cfg['registration'],'forum'=>$cfg['enabled']&&$cfg['forum'],'social'=>$cfg['enabled']&&$cfg['social'],'min_password'=>$cfg['min_password'],'rules'=>$cfg['rules']];
}

/* ───────── Missbrauchsschutz ───────── */
function rrw_cm_salt(string $dataDir): string {
    $s=rrw_cm_read($dataDir,'salt.json',[]);
    if(empty($s['salt'])){$s=['salt'=>bin2hex(random_bytes(16))];try{rrw_cm_write($dataDir,'salt.json',$s);}catch(Throwable $e){}}
    return (string)$s['salt'];
}
function rrw_cm_ip_key(string $dataDir, string $ip): string { return hash_hmac('sha256','ip|'.$ip,rrw_cm_salt($dataDir)); }
/** Zählt einen Versuch; false, wenn in $window Sekunden schon $max Versuche liegen. */
function rrw_cm_rate(string $dataDir, string $key, int $max, int $window, ?int $now=null): bool {
    $now=$now??time();
    return (bool)rrw_cm_locked($dataDir,function() use($dataDir,$key,$max,$window,$now){
        $r=rrw_cm_read($dataDir,'ratelimit.json',[]);
        foreach($r as $k=>$ts){$r[$k]=array_values(array_filter((array)$ts,fn($x)=>$x>$now-86400));if(!$r[$k])unset($r[$k]);}
        $mine=array_values(array_filter((array)($r[$key]??[]),fn($x)=>$x>$now-$window));
        if(count($mine)>=$max){$r[$key]=$mine;try{rrw_cm_write($dataDir,'ratelimit.json',$r);}catch(Throwable $e){}return false;}
        $mine[]=$now;$r[$key]=$mine;
        if(count($r)>4000)$r=array_slice($r,-4000,null,true);
        try{rrw_cm_write($dataDir,'ratelimit.json',$r);}catch(Throwable $e){}
        return true;
    });
}

/* ───────── Mitglieder ───────── */
function rrw_cm_members(string $dataDir): array { $d=rrw_cm_read($dataDir,'members.json',['members'=>[]]);return is_array($d['members']??null)?array_values($d['members']):[]; }
function rrw_cm_members_save(string $dataDir, array $m): void { rrw_cm_write($dataDir,'members.json',['members'=>array_values($m)]); }
function rrw_cm_username_ok(string $u): bool {
    if(!preg_match('/^[A-Za-z0-9_]{3,24}$/',$u))return false;
    return !in_array(strtolower($u),['admin','administrator','root','system','support','moderator','mod','team','staff','cms','null','undefined','anonym','gast'],true);
}
function rrw_cm_find(array $members, string $by, string $val): ?int {
    foreach($members as $i=>$m)if(strcasecmp((string)($m[$by]??''),$val)===0)return $i;
    return null;
}
function rrw_cm_pw_ok(string $pw, int $min): ?string {
    if(mb_strlen($pw)<$min)return "Das Passwort muss mindestens $min Zeichen lang sein.";
    if(mb_strlen($pw)>200)return 'Das Passwort ist zu lang.';
    if(in_array(strtolower($pw),['password','passwort','12345678','123456789','qwertz123','qwerty123','11111111'],true))return 'Dieses Passwort ist zu einfach.';
    return null;
}
/** Was andere Mitglieder/Besucher sehen dürfen (nie E-Mail oder Hash). */
function rrw_cm_public(array $m): array {
    return ['id'=>(string)$m['id'],'username'=>(string)$m['username'],'name'=>(string)($m['display_name']??$m['username']),'bio'=>(string)($m['bio']??''),'joined'=>substr((string)($m['created_at']??''),0,10),'role'=>(string)($m['role']??'member')];
}
function rrw_cm_register(string $dataDir, array $cfg, array $in, string $ip, ?int $now=null): array {
    $fail=fn(string $m,int $c=400)=>['ok'=>false,'message'=>$m,'code'=>$c];
    if(!$cfg['enabled'])return $fail('Die Community ist nicht aktiv.',404);
    if($cfg['registration']==='closed')return $fail('Neue Registrierungen sind derzeit nicht möglich.',403);
    if(trim((string)($in['hp']??''))!=='')return ['ok'=>true,'message'=>'Willkommen!','code'=>200,'pending'=>false];
    $opened=(int)($in['opened']??0);if($opened>0&&($now??time())-intdiv($opened,1000)<3)return $fail('Bitte einen Moment warten und erneut senden.',429);
    $user=trim((string)($in['username']??''));$email=mb_strtolower(trim((string)($in['email']??'')));$pw=(string)($in['password']??'');
    if(!rrw_cm_username_ok($user))return $fail('Der Benutzername muss 3–24 Zeichen lang sein (Buchstaben, Ziffern, Unterstrich) und darf nicht reserviert sein.');
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($email)>120)return $fail('Bitte eine gültige E-Mail-Adresse eingeben.');
    if(empty($in['consent']))return $fail('Bitte stimme den Regeln und der Datenverarbeitung zu.');
    if($e=rrw_cm_pw_ok($pw,$cfg['min_password']))return $fail($e);
    if(!rrw_cm_rate($dataDir,'reg|'.rrw_cm_ip_key($dataDir,$ip),5,3600,$now))return $fail('Zu viele Registrierungen von deinem Anschluss – bitte später erneut versuchen.',429);
    return rrw_cm_locked($dataDir,function() use($dataDir,$cfg,$user,$email,$pw,$in,$now,$fail){
        $members=rrw_cm_members($dataDir);
        if(rrw_cm_find($members,'username',$user)!==null)return $fail('Dieser Benutzername ist schon vergeben.',409);
        if(rrw_cm_find($members,'email',$email)!==null)return $fail('Mit dieser E-Mail-Adresse gibt es schon ein Konto.',409);
        if(count($members)>=20000)return $fail('Die Mitgliederzahl ist begrenzt.',503);
        $name=mb_substr(trim(strip_tags((string)($in['display_name']??''))),0,40);
        $pending=$cfg['registration']==='approval';
        $members[]=['id'=>bin2hex(random_bytes(5)),'username'=>$user,'display_name'=>$name!==''?$name:$user,'email'=>$email,'pw'=>password_hash($pw,PASSWORD_DEFAULT),'bio'=>'','role'=>'member','status'=>$pending?'pending':'active','created_at'=>date('Y-m-d H:i:s',$now??time()),'last_login'=>''];
        rrw_cm_members_save($dataDir,$members);
        return ['ok'=>true,'message'=>$pending?'Danke! Dein Konto wird von uns geprüft und freigeschaltet.':'Willkommen! Du kannst dich jetzt anmelden.','code'=>200,'pending'=>$pending];
    });
}

/* ───────── Anmeldung und Sitzungen ───────── */
function rrw_cm_token_hash(string $t): string { return hash('sha256',$t); }
function rrw_cm_login(string $dataDir, array $cfg, array $in, string $ip, ?int $now=null): array {
    $fail=fn(string $m,int $c=400)=>['ok'=>false,'message'=>$m,'code'=>$c];
    if(!$cfg['enabled'])return $fail('Die Community ist nicht aktiv.',404);
    $id=mb_strtolower(trim((string)($in['login']??'')));$pw=(string)($in['password']??'');
    if($id===''||$pw==='')return $fail('Bitte Benutzername und Passwort eingeben.');
    $ipk=rrw_cm_ip_key($dataDir,$ip);$uk=hash_hmac('sha256','user|'.$id,rrw_cm_salt($dataDir));
    // Fehlversuche begrenzen: je Konto 8 und je Anschluss 20 pro 15 Minuten (Erfolge zählen nicht – siehe unten)
    $failKeyU='lf|'.$uk;$failKeyI='lf|'.$ipk;
    $r=rrw_cm_read($dataDir,'ratelimit.json',[]);$t=$now??time();
    $recent=fn($k)=>count(array_filter((array)($r[$k]??[]),fn($x)=>$x>$t-900));
    if($recent($failKeyU)>=8||$recent($failKeyI)>=20)return $fail('Zu viele Fehlversuche – bitte in 15 Minuten erneut versuchen.',429);
    $members=rrw_cm_members($dataDir);
    $i=rrw_cm_find($members,'username',$id);if($i===null)$i=rrw_cm_find($members,'email',$id);
    static $dummy=null;if($dummy===null)$dummy=password_hash(bin2hex(random_bytes(8)),PASSWORD_DEFAULT);
    $hash=$i!==null?(string)$members[$i]['pw']:$dummy;                                  // gleiche Rechenzeit, auch wenn es das Konto nicht gibt
    $ok=password_verify($pw,$hash)&&$i!==null;
    if(!$ok){rrw_cm_rate($dataDir,$failKeyU,1000,900,$now);rrw_cm_rate($dataDir,$failKeyI,1000,900,$now);return $fail('Benutzername oder Passwort stimmt nicht.',401);}
    $m=$members[$i];
    if(($m['status']??'')==='pending')return $fail('Dein Konto wartet noch auf Freischaltung.',403);
    if(($m['status']??'')==='banned')return $fail('Dieses Konto ist gesperrt.',403);
    $token=bin2hex(random_bytes(32));
    rrw_cm_locked($dataDir,function() use($dataDir,$cfg,$m,$token,$t){
        $s=rrw_cm_read($dataDir,'sessions.json',[]);
        foreach($s as $k=>$x)if(($x['exp']??0)<$t)unset($s[$k]);
        $s[rrw_cm_token_hash($token)]=['member'=>$m['id'],'exp'=>$t+86400*$cfg['session_days'],'created'=>$t];
        if(count($s)>8000)$s=array_slice($s,-8000,null,true);
        rrw_cm_write($dataDir,'sessions.json',$s);
        $ms=rrw_cm_members($dataDir);if(($j=rrw_cm_find($ms,'id',(string)$m['id']))!==null){$ms[$j]['last_login']=date('Y-m-d H:i:s',$t);rrw_cm_members_save($dataDir,$ms);}
    });
    return ['ok'=>true,'message'=>'Angemeldet','code'=>200,'token'=>$token,'member'=>rrw_cm_public($m)+['email'=>$m['email']]];
}
/** Mitglied zum Token (nur aktive Konten) oder null. */
function rrw_cm_auth(string $dataDir, string $token, ?int $now=null): ?array {
    if(!preg_match('/^[a-f0-9]{64}$/',$token))return null;
    $s=rrw_cm_read($dataDir,'sessions.json',[]);$x=$s[rrw_cm_token_hash($token)]??null;
    if(!$x||($x['exp']??0)<($now??time()))return null;
    $members=rrw_cm_members($dataDir);$i=rrw_cm_find($members,'id',(string)$x['member']);
    if($i===null||($members[$i]['status']??'')!=='active')return null;
    return $members[$i];
}
function rrw_cm_logout(string $dataDir, string $token): void {
    if(!preg_match('/^[a-f0-9]{64}$/',$token))return;
    rrw_cm_locked($dataDir,function() use($dataDir,$token){$s=rrw_cm_read($dataDir,'sessions.json',[]);unset($s[rrw_cm_token_hash($token)]);rrw_cm_write($dataDir,'sessions.json',$s);});
}
function rrw_cm_drop_sessions(string $dataDir, string $memberId): void {
    rrw_cm_locked($dataDir,function() use($dataDir,$memberId){$s=rrw_cm_read($dataDir,'sessions.json',[]);foreach($s as $k=>$x)if(($x['member']??'')===$memberId)unset($s[$k]);rrw_cm_write($dataDir,'sessions.json',$s);});
}

/* ───────── Profil und Konto ───────── */
function rrw_cm_update_profile(string $dataDir, array $cfg, string $memberId, array $in): array {
    $fail=fn(string $m,int $c=400)=>['ok'=>false,'message'=>$m,'code'=>$c];
    return rrw_cm_locked($dataDir,function() use($dataDir,$cfg,$memberId,$in,$fail){
        $ms=rrw_cm_members($dataDir);$i=rrw_cm_find($ms,'id',$memberId);if($i===null)return $fail('Konto nicht gefunden',404);
        if(array_key_exists('display_name',$in)){$n=mb_substr(trim(strip_tags((string)$in['display_name'])),0,40);if(mb_strlen($n)<2)return $fail('Der Anzeigename braucht mindestens 2 Zeichen.');$ms[$i]['display_name']=$n;}
        if(array_key_exists('bio',$in))$ms[$i]['bio']=mb_substr(trim(strip_tags((string)$in['bio'])),0,300);
        if(!empty($in['new_password'])){
            if(!password_verify((string)($in['current_password']??''),(string)$ms[$i]['pw']))return $fail('Das aktuelle Passwort stimmt nicht.',403);
            if($e=rrw_cm_pw_ok((string)$in['new_password'],$cfg['min_password']))return $fail($e);
            $ms[$i]['pw']=password_hash((string)$in['new_password'],PASSWORD_DEFAULT);
        }
        rrw_cm_members_save($dataDir,$ms);
        return ['ok'=>true,'message'=>'Gespeichert','code'=>200,'member'=>rrw_cm_public($ms[$i])+['email'=>$ms[$i]['email']]];
    });
}
/** Konto löschen (Passwort nötig). Beiträge anderer Module werden über den Rückruf anonymisiert. */
function rrw_cm_delete_account(string $dataDir, string $memberId, string $password, ?callable $onDeleted=null): array {
    $r=rrw_cm_locked($dataDir,function() use($dataDir,$memberId,$password){
        $ms=rrw_cm_members($dataDir);$i=rrw_cm_find($ms,'id',$memberId);if($i===null)return ['ok'=>false,'message'=>'Konto nicht gefunden','code'=>404];
        if($password!==''&&!password_verify($password,(string)$ms[$i]['pw']))return ['ok'=>false,'message'=>'Das Passwort stimmt nicht.','code'=>403];
        array_splice($ms,$i,1);rrw_cm_members_save($dataDir,$ms);
        return ['ok'=>true,'message'=>'Dein Konto wurde gelöscht.','code'=>200];
    });
    if($r['ok']){rrw_cm_drop_sessions($dataDir,$memberId);if($onDeleted)$onDeleted($memberId);}
    return $r;
}

/* ───────── Passwort vergessen ───────── */
function rrw_cm_reset_request(string $dataDir, array $cfg, string $email, string $ip, string $origin, ?callable $mailer=null): array {
    $ok=['ok'=>true,'message'=>'Wenn es ein Konto mit dieser Adresse gibt, haben wir dir einen Link geschickt.','code'=>200];
    if(!$cfg['enabled'])return ['ok'=>false,'message'=>'Die Community ist nicht aktiv.','code'=>404];
    $email=mb_strtolower(trim($email));if(!filter_var($email,FILTER_VALIDATE_EMAIL))return $ok;
    if(!rrw_cm_rate($dataDir,'rs|'.rrw_cm_ip_key($dataDir,$ip),5,3600))return ['ok'=>false,'message'=>'Zu viele Anfragen – bitte später erneut versuchen.','code'=>429];
    $ms=rrw_cm_members($dataDir);$i=rrw_cm_find($ms,'email',$email);if($i===null||($ms[$i]['status']??'')!=='active')return $ok;
    $token=bin2hex(random_bytes(24));
    rrw_cm_locked($dataDir,function() use($dataDir,$token,$ms,$i){
        $r=rrw_cm_read($dataDir,'resets.json',[]);$t=time();foreach($r as $k=>$x)if(($x['exp']??0)<$t)unset($r[$k]);
        $r[rrw_cm_token_hash($token)]=['member'=>$ms[$i]['id'],'exp'=>$t+3600];rrw_cm_write($dataDir,'resets.json',$r);
    });
    if($mailer)$mailer($ms[$i]['email'],rtrim($origin,'/').'/?member_reset='.$token);
    return $ok;
}
function rrw_cm_reset_apply(string $dataDir, array $cfg, string $token, string $newPassword): array {
    if(!preg_match('/^[a-f0-9]{48}$/',$token))return ['ok'=>false,'message'=>'Der Link ist ungültig oder abgelaufen.','code'=>400];
    if($e=rrw_cm_pw_ok($newPassword,$cfg['min_password']))return ['ok'=>false,'message'=>$e,'code'=>400];
    $res=rrw_cm_locked($dataDir,function() use($dataDir,$token,$newPassword){
        $r=rrw_cm_read($dataDir,'resets.json',[]);$h=rrw_cm_token_hash($token);$x=$r[$h]??null;
        if(!$x||($x['exp']??0)<time())return null;
        $ms=rrw_cm_members($dataDir);$i=rrw_cm_find($ms,'id',(string)$x['member']);if($i===null)return null;
        $ms[$i]['pw']=password_hash($newPassword,PASSWORD_DEFAULT);rrw_cm_members_save($dataDir,$ms);
        unset($r[$h]);rrw_cm_write($dataDir,'resets.json',$r);return (string)$x['member'];
    });
    if($res===null)return ['ok'=>false,'message'=>'Der Link ist ungültig oder abgelaufen.','code'=>400];
    rrw_cm_drop_sessions($dataDir,$res);
    return ['ok'=>true,'message'=>'Passwort geändert – du kannst dich jetzt anmelden.','code'=>200];
}

/* ───────── Verwaltung im CMS ───────── */
function rrw_cm_admin_list(string $dataDir): array {
    return array_map(fn($m)=>['id'=>$m['id'],'username'=>$m['username'],'name'=>$m['display_name']??$m['username'],'email'=>$m['email'],'role'=>$m['role']??'member','status'=>$m['status']??'active','created_at'=>$m['created_at']??'','last_login'=>$m['last_login']??''],array_reverse(rrw_cm_members($dataDir)));
}
function rrw_cm_admin_set(string $dataDir, string $id, string $op, ?callable $onDeleted=null): array {
    if(!in_array($op,['approve','ban','unban','moderator','member','delete'],true))return ['ok'=>false,'message'=>'Ungültige Aktion','code'=>400];
    if($op==='delete')return rrw_cm_delete_account($dataDir,$id,'',$onDeleted);
    $res=rrw_cm_locked($dataDir,function() use($dataDir,$id,$op){
        $ms=rrw_cm_members($dataDir);$i=rrw_cm_find($ms,'id',$id);if($i===null)return false;
        if($op==='approve'||$op==='unban')$ms[$i]['status']='active';
        if($op==='ban')$ms[$i]['status']='banned';
        if($op==='moderator'||$op==='member')$ms[$i]['role']=$op;
        rrw_cm_members_save($dataDir,$ms);return true;
    });
    if(!$res)return ['ok'=>false,'message'=>'Mitglied nicht gefunden','code'=>404];
    if($op==='ban')rrw_cm_drop_sessions($dataDir,$id);
    return ['ok'=>true,'message'=>'Gespeichert','code'=>200];
}

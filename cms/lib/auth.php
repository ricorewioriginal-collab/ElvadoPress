<?php
declare(strict_types=1);

// Lokaler CMS-Login (im eigenständigen Betrieb der einzige Weg, sonst Alternative zum AnMaCha Control Center).
// Sessions leben lokal in local-sessions.local.json; Tokens erkennbar am Präfix "local_".
// Mehrere Redakteure mit Rollen: local-auth.local.php speichert eine Liste von Benutzern
// (['users'=>[['username','password_hash','role','display_name','created_at'],...]]).
// Rolle 'admin' darf alles inkl. Benutzerverwaltung; 'autor' darf nur eigene Beiträge verwalten
// (siehe rrw_news_can_edit() in publish.php).

function rrw_local_auth_file(): string { return defined('RRW_LOCAL_AUTH_FILE')?(string)RRW_LOCAL_AUTH_FILE:__DIR__.'/../data/local-auth.local.php'; }
function rrw_local_sessions_file(): string { return defined('RRW_LOCAL_SESSIONS_FILE')?(string)RRW_LOCAL_SESSIONS_FILE:__DIR__.'/../data/local-sessions.local.json'; }

function rrw_local_users(): array {
    $f=rrw_local_auth_file(); if(!is_file($f))return [];
    $c=include $f;
    if(!is_array($c))return [];
    if(isset($c['users'])&&is_array($c['users']))return array_values($c['users']);
    // Altformat (ein einzelner Benutzer direkt im Root): als Admin übernehmen.
    if(!empty($c['username'])&&!empty($c['password_hash'])){
        return [['username'=>$c['username'],'password_hash'=>$c['password_hash'],'role'=>'admin','display_name'=>$c['username'],'created_at'=>$c['updated_at']??date(DATE_ATOM)]];
    }
    return [];
}
function rrw_local_users_write(array $users): void {
    $out=['users'=>array_values($users)];
    $php="<?php\n// Wird vom CMS serverseitig erzeugt, nicht mit echten Zugangsdaten committen.\nreturn ".var_export($out,true).";\n";
    $file=rrw_local_auth_file();
    rrw_write_atomic($file,$php); @chmod($file,0600);
    // Diese Datei wird per include() gelesen: mit aktivem Opcache (validate_timestamps mit
    // revalidate_freq > 0) könnte sonst kurzzeitig noch die alte Fassung ausgeliefert werden,
    // z.B. wenn sich ein gerade angelegter Redakteur sofort danach anmeldet.
    if(function_exists('opcache_invalidate'))@opcache_invalidate($file,true);
}
function rrw_local_auth_configured(): bool { return count(rrw_local_users())>0; }

// Legt den ersten Zugang an (Ersteinrichtung) oder aktualisiert einen bestehenden Benutzer
// (gleicher Benutzername = Upsert, z.B. zum Zurücksetzen des eigenen Passworts).
function rrw_local_auth_set(string $username,string $password,string $role='admin'): void {
    $username=trim($username);
    if($username===''||mb_strlen($password)<8)throw new RuntimeException('Benutzername fehlt oder Passwort hat weniger als 8 Zeichen');
    if(!in_array($role,['admin','autor'],true))$role='autor';
    $users=rrw_local_users(); $found=false;
    foreach($users as &$u){
        if(strcasecmp((string)($u['username']??''),$username)===0){
            $u['password_hash']=password_hash($password,PASSWORD_DEFAULT); $u['role']=$role; $found=true; break;
        }
    }
    unset($u);
    if(!$found)$users[]=['username'=>$username,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>$role,'display_name'=>$username,'created_at'=>date(DATE_ATOM)];
    rrw_local_users_write($users);
}
function rrw_local_user_add(string $username,string $password,string $role,string $displayName,string $email=''): void {
    $username=trim($username);
    if($username===''||mb_strlen($password)<8)throw new RuntimeException('Benutzername fehlt oder Passwort hat weniger als 8 Zeichen');
    if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Ungültige E-Mail-Adresse');
    if(!in_array($role,['admin','autor'],true))$role='autor';
    $users=rrw_local_users();
    foreach($users as $u)if(strcasecmp((string)($u['username']??''),$username)===0)throw new RuntimeException('Benutzername existiert bereits');
    $users[]=['username'=>$username,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>$role,'display_name'=>$displayName!==''?$displayName:$username,'email'=>$email,'created_at'=>date(DATE_ATOM)];
    rrw_local_users_write($users);
}
function rrw_local_user_update(string $username,?string $password,?string $role,?string $displayName,?string $email=null): void {
    $users=rrw_local_users(); $found=false;
    if($email!==null&&$email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Ungültige E-Mail-Adresse');
    foreach($users as &$u){
        if(strcasecmp((string)($u['username']??''),$username)===0){
            $found=true;
            if($password!==null){ if(mb_strlen($password)<8)throw new RuntimeException('Passwort muss mindestens 8 Zeichen haben'); $u['password_hash']=password_hash($password,PASSWORD_DEFAULT); }
            if($role!==null){ if(!in_array($role,['admin','autor'],true))throw new RuntimeException('Unbekannte Rolle'); $u['role']=$role; }
            if($displayName!==null&&$displayName!=='')$u['display_name']=$displayName;
            if($email!==null)$u['email']=$email;
            break;
        }
    }
    unset($u);
    if(!$found)throw new RuntimeException('Benutzer nicht gefunden');
    if(!count(array_filter($users,fn($u)=>($u['role']??'')==='admin')))throw new RuntimeException('Es muss mindestens ein Administrator bestehen bleiben');
    rrw_local_users_write($users);
}
// Liefert die hinterlegte E-Mail-Adresse eines lokalen Redakteurs (per Anzeigename ODER
// Benutzername gesucht, da news_save.author den Anzeigenamen speichert), oder '' wenn
// keine hinterlegt ist oder der Autor kein lokales Konto (mehr) hat.
function rrw_local_user_email_for(string $authorNameOrUsername): string {
    foreach(rrw_local_users() as $u){
        if(strcasecmp((string)($u['username']??''),$authorNameOrUsername)===0||strcasecmp((string)($u['display_name']??''),$authorNameOrUsername)===0){
            $email=(string)($u['email']??'');
            return $email!==''&&filter_var($email,FILTER_VALIDATE_EMAIL)?$email:'';
        }
    }
    return '';
}
function rrw_local_user_delete(string $username): void {
    $users=rrw_local_users();
    $remaining=array_values(array_filter($users,fn($u)=>strcasecmp((string)($u['username']??''),$username)!==0));
    if(count($remaining)===count($users))throw new RuntimeException('Benutzer nicht gefunden');
    if(!count(array_filter($remaining,fn($u)=>($u['role']??'')==='admin')))throw new RuntimeException('Es muss mindestens ein Administrator bestehen bleiben');
    rrw_local_users_write($remaining);
    $sessions=rrw_local_sessions_read(); $changed=false;
    foreach($sessions as $t=>$s){ if(strcasecmp((string)($s['username']??''),$username)===0){unset($sessions[$t]);$changed=true;} }
    if($changed)rrw_local_sessions_write($sessions);
}
function rrw_local_auth_verify(string $username,string $password): ?array {
    $username=trim($username); if($username===''||$password==='')return null;
    foreach(rrw_local_users() as $u){
        if(strcasecmp((string)($u['username']??''),$username)===0){
            return password_verify($password,(string)($u['password_hash']??''))?$u:null;
        }
    }
    return null;
}
function rrw_local_sessions_read(): array {
    $f=rrw_local_sessions_file(); if(!is_file($f))return [];
    $d=json_decode((string)@file_get_contents($f),true); return is_array($d)?$d:[];
}
function rrw_local_sessions_write(array $sessions): void {
    rrw_write_atomic(rrw_local_sessions_file(),json_encode($sessions,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    @chmod(rrw_local_sessions_file(),0600);
}
function rrw_local_session_create(string $username): string {
    $now=time(); $sessions=rrw_local_sessions_read();
    foreach($sessions as $t=>$s){ if((int)($s['expires']??0)<$now) unset($sessions[$t]); }
    $token='local_'.bin2hex(random_bytes(24));
    $sessions[$token]=['username'=>$username,'expires'=>$now+43200];
    rrw_local_sessions_write($sessions);
    return $token;
}
// Liefert bei gültigem Token die aktuellen Nutzerdaten (Rolle wird live nachgeschlagen,
// damit eine Rollenänderung sofort wirkt, ohne dass sich der Benutzer neu anmelden muss).
function rrw_local_session_validate(string $token): ?array {
    if(!str_starts_with($token,'local_'))return null;
    $s=rrw_local_sessions_read()[$token]??null;
    if(!$s||(int)($s['expires']??0)<time())return null;
    $username=(string)($s['username']??'');
    foreach(rrw_local_users() as $u){
        if(strcasecmp((string)($u['username']??''),$username)===0){
            return ['username'=>(string)$u['username'],'role'=>in_array($u['role']??'',['admin','autor'],true)?$u['role']:'admin','display_name'=>(string)($u['display_name']??$u['username'])];
        }
    }
    return null;
}
function rrw_local_session_destroy(string $token): void {
    $sessions=rrw_local_sessions_read();
    if(isset($sessions[$token])){ unset($sessions[$token]); rrw_local_sessions_write($sessions); }
}

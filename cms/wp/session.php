<?php
// Anmelde-Sitzung der WordPress-Schicht für Editoren, die im eigenen Browser-Tab laufen müssen (z. B. Elementor): Das CMS stellt einem angemeldeten
// Superadmin ein Einmal-Token aus (90 s gültig); die WordPress-Schicht tauscht es gegen ein signiertes, nur für HTTP lesbares Cookie (8 h).
// Ohne gültiges Cookie sind alle Besucher unangemeldet; REST-Aufrufe zählen nur mit gültigem Nonce als angemeldet.
const ELVADO_WP_SESS_COOKIE='elvado_wp_sess';

function elvado_wp_sess_sign(string $payload): string { return hash_hmac('sha256',$payload,wp_salt('auth').'|elvado-sess'); }
/** Einmal-Token für den Wechsel vom CMS in die WordPress-Schicht. */
function elvado_wp_sess_token_issue(): string { $p='t.'.(time()+90).'.'.bin2hex(random_bytes(8));return $p.'.'.elvado_wp_sess_sign($p); }
function elvado_wp_sess_token_consume(string $t): bool {
    if(!preg_match('/^(t\.(\d{9,11})\.([a-f0-9]{16}))\.([a-f0-9]{64})$/',$t,$m))return false;
    if((int)$m[2]<time()||!hash_equals(elvado_wp_sess_sign($m[1]),$m[4]))return false;
    $dir=ELVADO_WP_DATA.'/sess';if(!is_dir($dir))@mkdir($dir,0775,true);
    foreach(glob($dir.'/*')?:[] as $f)if(filemtime($f)<time()-600)@unlink($f);
    $used=$dir.'/'.$m[3];if(is_file($used))return false;
    return file_put_contents($used,'1')!==false;
}
/** Setzt das Sitzungs-Cookie (HttpOnly, SameSite=Lax, Secure bei HTTPS). */
function elvado_wp_sess_cookie_issue(): string {
    $exp=time()+8*3600;$p='s.'.$exp.'.'.bin2hex(random_bytes(12));$v=$p.'.'.elvado_wp_sess_sign($p);
    $secure=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||(($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https');
    return ELVADO_WP_SESS_COOKIE.'='.$v.'; Expires='.gmdate('D, d M Y H:i:s',$exp).' GMT; Path=/; HttpOnly; SameSite=Lax'.($secure?'; Secure':'');
}
function elvado_wp_sess_cookie_clear(): string { return ELVADO_WP_SESS_COOKIE.'=; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Path=/; HttpOnly; SameSite=Lax'; }
/** Gültige Sitzung? Liefert den Benutzer (Administration) und setzt den Nonce-Schlüssel. */
function elvado_wp_sess_check(): ?array {
    $v=(string)($_COOKIE[ELVADO_WP_SESS_COOKIE]??'');
    if(!preg_match('/^(s\.(\d{9,11})\.([a-f0-9]{24}))\.([a-f0-9]{64})$/',$v,$m)||(int)$m[2]<time()||!hash_equals(elvado_wp_sess_sign($m[1]),$m[4]))return null;
    $GLOBALS['elvado_wp_session_token']=hash('sha256',$m[1]);
    return ['id'=>1,'login'=>'admin','name'=>'Administration','email'=>(string)get_option('admin_email',''),'role'=>'administrator'];
}
/** Nonce aus Kopf oder Parametern (REST). */
function elvado_wp_sess_rest_nonce_ok(array $headers, array $query): bool {
    $n='';foreach($headers as $k=>$v)if(strcasecmp((string)$k,'X-WP-Nonce')===0)$n=(string)$v;
    if($n==='')$n=(string)($query['_wpnonce']??'');
    return $n!==''&&(bool)wp_verify_nonce($n,'wp_rest');
}
/** Gültigen Zielpfad innerhalb von /wp-admin/ prüfen (kein offener Redirect). */
function elvado_wp_sess_target(string $to): ?string {
    return preg_match('#^(post|post-new|admin|index|edit)\.php(\?[A-Za-z0-9_=&%.\-:/+\[\]]*)?$#',$to)?$to:null;
}

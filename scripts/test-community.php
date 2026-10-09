<?php
// Prüft das Community-Modul (Mitglieder): cms/lib/community.php. Aufruf: php scripts/test-community.php
declare(strict_types=1);
require __DIR__.'/../cms/lib/community.php';
$fail=0;$n=0;
function t(string $name,bool $ok): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name\n";} }
$tmp=sys_get_temp_dir().'/elvado-cm-'.bin2hex(random_bytes(4));mkdir($tmp);
$cfg=elvado_cm_config_clean(['enabled'=>true]);
$reg=fn(array $x,string $ip='203.0.113.1')=>elvado_cm_register($tmp,$cfg,$x+['username'=>'erika_m','email'=>'Erika@Example.org','password'=>'geheim-123','consent'=>1,'opened'=>(time()-30)*1000],$ip);

// Einstellungen
t('Standard: aus',elvado_cm_config_clean([])['enabled']===false);
t('Registrierung ungültig -> open',elvado_cm_config_clean(['registration'=>'x'])['registration']==='open');
t('Mindestlänge mindestens 8',elvado_cm_config_clean(['min_password'=>2])['min_password']===8);
t('öffentliche Config ohne Interna',array_keys(elvado_cm_public_config($cfg))===['enabled','registration','forum','social','min_password','rules']);
t('deaktiviert: Registrierung abgelehnt',elvado_cm_register($tmp,elvado_cm_config_clean([]),['username'=>'x'],'1.1.1.1')['code']===404);

// Registrierung
t('Benutzername: zu kurz',$reg(['username'=>'ab'])['ok']===false);
t('Benutzername: reserviert',$reg(['username'=>'Admin'])['ok']===false);
t('Benutzername: Sonderzeichen',$reg(['username'=>'a b!'])['ok']===false);
t('E-Mail ungültig',$reg(['email'=>'nope'])['ok']===false);
t('Einwilligung nötig',$reg(['consent'=>0])['ok']===false);
t('Passwort zu kurz',$reg(['password'=>'kurz'])['ok']===false);
t('Passwort zu einfach',$reg(['password'=>'password'])['ok']===false);
t('zu schnell abgeschickt',elvado_cm_register($tmp,$cfg,['username'=>'schnell','email'=>'s@example.org','password'=>'geheim-123','consent'=>1,'opened'=>time()*1000],'203.0.113.9')['code']===429);
t('Honeypot: scheinbar ok, nichts angelegt',elvado_cm_register($tmp,$cfg,['hp'=>'bot'],'203.0.113.2')['ok']&&count(elvado_cm_members($tmp))===0);
$r=$reg([]);t('Registrierung ok',$r['ok']&&!$r['pending']&&count(elvado_cm_members($tmp))===1);
$m=elvado_cm_members($tmp)[0];
t('Passwort nur als Hash',strpos(json_encode($m),'geheim-123')===false&&password_verify('geheim-123',$m['pw']));
t('E-Mail kleingeschrieben gespeichert',$m['email']==='erika@example.org');
t('Benutzername doppelt (Groß/Klein)',$reg(['username'=>'ERIKA_M','email'=>'x@example.org'],'203.0.113.3')['code']===409);
t('E-Mail doppelt',$reg(['username'=>'andere','email'=>'erika@example.org'],'203.0.113.3')['code']===409);
t('öffentliche Sicht ohne E-Mail/Hash',!isset(elvado_cm_public($m)['email'])&&!isset(elvado_cm_public($m)['pw']));
$ipN=0;$ok=0;for($i=0;$i<8;$i++){if($reg(['username'=>'user_'.$i,'email'=>"u$i@example.org"],'198.51.100.5')['ok'])$ok++;}
t('höchstens 5 Registrierungen je Anschluss und Stunde',$ok===5);

// Anmeldung
$login=fn($l,$p,$ip='203.0.113.50')=>elvado_cm_login($tmp,$cfg,['login'=>$l,'password'=>$p],$ip);
t('falsches Passwort',$login('erika_m','falsch')['code']===401);
t('unbekanntes Konto: gleiche Meldung',$login('gibtsnicht','x')['message']===$login('erika_m','falsch')['message']);
$li=$login('erika_m','geheim-123');t('Anmeldung ok mit Token',$li['ok']&&preg_match('/^[a-f0-9]{64}$/',$li['token']));
t('Anmeldung per E-Mail',$login('ERIKA@example.org','geheim-123')['ok']);
t('Token führt zum Mitglied',(elvado_cm_auth($tmp,$li['token'])['username']??'')==='erika_m');
t('Sitzung nur als Hash gespeichert',strpos(file_get_contents(elvado_cm_dir($tmp).'/sessions.json'),$li['token'])===false);
t('falsches/kurzes Token',elvado_cm_auth($tmp,'abc')===null&&elvado_cm_auth($tmp,str_repeat('0',64))===null);
t('abgelaufene Sitzung',elvado_cm_auth($tmp,$li['token'],time()+86400*40)===null);
elvado_cm_logout($tmp,$li['token']);t('Abmelden entwertet das Token',elvado_cm_auth($tmp,$li['token'])===null);
// Sperre nach Fehlversuchen (eigenes Konto, damit die übrigen Tests nicht betroffen sind)
$reg(['username'=>'sperr_test','email'=>'sperr@example.org'],'203.0.113.99');
for($i=0;$i<8;$i++)$login('sperr_test','falsch','192.0.2.77');
t('Sperre nach 8 Fehlversuchen (auch mit richtigem Passwort)',$login('sperr_test','geheim-123','192.0.2.78')['code']===429);
t('andere Konten bleiben nutzbar',$login('erika_m','geheim-123','192.0.2.79')['ok']);

// Freischaltung / Sperre
$cfg2=elvado_cm_config_clean(['enabled'=>true,'registration'=>'approval']);
$p=elvado_cm_register($tmp,$cfg2,['username'=>'neu_ling','email'=>'neu@example.org','password'=>'geheim-123','consent'=>1,'opened'=>(time()-30)*1000],'203.0.113.60');
t('Freischaltung nötig',$p['ok']&&$p['pending']);
t('Anmeldung vor Freischaltung verwehrt',elvado_cm_login($tmp,$cfg2,['login'=>'neu_ling','password'=>'geheim-123'],'203.0.113.61')['code']===403);
$nid=elvado_cm_members($tmp)[array_search('neu_ling',array_column(elvado_cm_members($tmp),'username'))]['id'];
t('freischalten',elvado_cm_admin_set($tmp,$nid,'approve')['ok']&&elvado_cm_login($tmp,$cfg2,['login'=>'neu_ling','password'=>'geheim-123'],'203.0.113.62')['ok']);
$tk=elvado_cm_login($tmp,$cfg2,['login'=>'neu_ling','password'=>'geheim-123'],'203.0.113.63')['token'];
t('sperren beendet Sitzungen',elvado_cm_admin_set($tmp,$nid,'ban')['ok']&&elvado_cm_auth($tmp,$tk)===null);
t('gesperrt: keine Anmeldung',elvado_cm_login($tmp,$cfg2,['login'=>'neu_ling','password'=>'geheim-123'],'203.0.113.64')['code']===403);
t('Registrierung geschlossen',elvado_cm_register($tmp,elvado_cm_config_clean(['enabled'=>true,'registration'=>'closed']),['username'=>'x'],'1.2.3.4')['code']===403);
t('ungültige Admin-Aktion',elvado_cm_admin_set($tmp,$nid,'zerstoeren')['code']===400);

// Profil
$eid=elvado_cm_members($tmp)[0]['id'];
t('Profil: Name zu kurz',elvado_cm_update_profile($tmp,$cfg,$eid,['display_name'=>'x'])['ok']===false);
$up=elvado_cm_update_profile($tmp,$cfg,$eid,['display_name'=>'<b>Erika</b> M.','bio'=>'<script>x</script>Hallo']);
t('Profil gespeichert, HTML entfernt',$up['ok']&&$up['member']['name']==='Erika M.'&&strpos($up['member']['bio'],'<')===false);
t('Passwortwechsel braucht das alte',elvado_cm_update_profile($tmp,$cfg,$eid,['new_password'=>'ganz-neu-123','current_password'=>'falsch'])['code']===403);
t('Passwortwechsel ok',elvado_cm_update_profile($tmp,$cfg,$eid,['new_password'=>'ganz-neu-123','current_password'=>'geheim-123'])['ok']&&elvado_cm_login($tmp,$cfg,['login'=>'erika_m','password'=>'ganz-neu-123'],'203.0.113.70')['ok']);

// Passwort vergessen
$sent=[];$mail=function($to,$link) use(&$sent){$sent[]=[$to,$link];};
t('Reset: unbekannte Adresse – gleiche Antwort, keine Mail',elvado_cm_reset_request($tmp,$cfg,'niemand@example.org','203.0.113.80','https://x.test',$mail)['ok']&&!$sent);
$rr=elvado_cm_reset_request($tmp,$cfg,'erika@example.org','203.0.113.80','https://x.test',$mail);
t('Reset: Mail mit Link',$rr['ok']&&count($sent)===1&&preg_match('#^https://x\.test/\?member_reset=[a-f0-9]{48}$#',$sent[0][1]));
$tok=substr($sent[0][1],-48);
t('Reset: Token nur als Hash',strpos(file_get_contents(elvado_cm_dir($tmp).'/resets.json'),$tok)===false);
t('Reset: schwaches Passwort',elvado_cm_reset_apply($tmp,$cfg,$tok,'abc')['ok']===false);
t('Reset: falsches Token',elvado_cm_reset_apply($tmp,$cfg,str_repeat('a',48),'neues-passwort-1')['ok']===false);
t('Reset: klappt',elvado_cm_reset_apply($tmp,$cfg,$tok,'neues-passwort-1')['ok']&&elvado_cm_login($tmp,$cfg,['login'=>'erika_m','password'=>'neues-passwort-1'],'203.0.113.81')['ok']);
t('Reset: Token nur einmal',elvado_cm_reset_apply($tmp,$cfg,$tok,'noch-eins-123')['ok']===false);

// Konto löschen
$called=[];
t('Löschen: falsches Passwort',elvado_cm_delete_account($tmp,$eid,'falsch')['code']===403);
$d=elvado_cm_delete_account($tmp,$eid,'neues-passwort-1',function($id) use(&$called){$called[]=$id;});
t('Löschen: Konto weg, Rückruf, Sitzungen weg',$d['ok']&&$called===[$eid]&&elvado_cm_find(elvado_cm_members($tmp),'id',$eid)===null&&strpos(file_get_contents(elvado_cm_dir($tmp).'/sessions.json'),$eid)===false);

$rm=function(string $d) use (&$rm){foreach(array_diff(scandir($d),['.','..']) as $f){$p=$d.'/'.$f;is_dir($p)?$rm($p):unlink($p);}rmdir($d);};$rm($tmp);
echo $fail===0?"$n von $n Prüfungen bestanden\n":"$fail von $n Prüfungen FEHLGESCHLAGEN\n";
exit($fail===0?0:1);

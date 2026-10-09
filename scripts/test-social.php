<?php
// Prüft das soziale Netzwerk (cms/lib/social.php). Aufruf: php scripts/test-social.php
declare(strict_types=1);
require __DIR__.'/../cms/lib/social.php';
$fail=0;$n=0;
function t(string $name,bool $ok): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name\n";} }
$tmp=sys_get_temp_dir().'/elvado-social-'.bin2hex(random_bytes(4));mkdir($tmp);
$cfg=elvado_cm_config_clean(['enabled'=>true]);
foreach(['anna','ben','cara'] as $i=>$u)elvado_cm_register($tmp,$cfg,['username'=>$u,'email'=>"$u@example.com",'password'=>'geheim1234','consent'=>true,'opened'=>0],'203.0.113.'.($i+1));
[$A,$B,$C]=elvado_cm_members($tmp);
t('zu kurz',elvado_so_post($tmp,$A,'x',1000)['code']===400);
$p1=elvado_so_post($tmp,$A,"Hallo <b>Welt</b>\r\nzweite Zeile",1000);t('posten',$p1['ok']);
t('Doppelpost',elvado_so_post($tmp,$A,"Hallo <b>Welt</b>\nzweite Zeile",1001)['code']===409);
t('500 Zeichen Limit',mb_strlen(elvado_so_feed($tmp,'all',null)['items'][0]['body'])<=500&&elvado_so_post($tmp,$A,str_repeat('ä',900),1002)['ok']&&mb_strlen(elvado_so_feed($tmp,'all',null)['items'][0]['body'])===500);
$pb=elvado_so_post($tmp,$B,'Beitrag von Ben',1003)['id'];
// Likes
$r=elvado_so_like($tmp,$A,$pb);t('Like an',$r['ok']&&$r['liked']&&$r['likes']===1);
t('Like aus',!elvado_so_like($tmp,$A,$pb)['liked']);
elvado_so_like($tmp,$A,$pb);elvado_so_like($tmp,$C,$pb);
$f=elvado_so_feed($tmp,'all',$A);$mine=array_values(array_filter($f['items'],fn($x)=>$x['id']===$pb))[0];
t('Like-Zähler und liked-Flag',$mine['likes']===2&&$mine['liked']);
t('Anonym sieht kein liked',elvado_so_feed($tmp,'all',null)['items'][0]['liked']===false);
t('Like unbekannter Beitrag',elvado_so_like($tmp,$A,'rffffffffff')['code']===404);
// Folgen
t('nicht sich selbst folgen',elvado_so_follow($tmp,$A,(string)$A['id'])['code']===400);
t('unbekanntes Mitglied',elvado_so_follow($tmp,$A,'ffffffffff')['code']===404);
t('folgen',elvado_so_follow($tmp,$A,(string)$B['id'])['following']===true);
$fe=elvado_so_feed($tmp,'following',$A);$authors=array_unique(array_column($fe['items'],'username'));sort($authors);
t('Feed: eigene + gefolgte',$authors===['anna','ben']);
t('Feed ohne Folgen: nur eigene',array_unique(array_column(elvado_so_feed($tmp,'following',$C)['items'],'username'))===[]);
t('entfolgen',elvado_so_follow($tmp,$A,(string)$B['id'])['following']===false);
elvado_so_follow($tmp,$A,(string)$B['id']);
// Profil
$pr=elvado_so_profile($tmp,'ben',$A);t('Profil: Zähler',$pr['followers']===1&&$pr['posts']===1&&$pr['is_following']&&!$pr['is_me']&&!isset($pr['member']['email']));
t('Profil: unbekannt',elvado_so_profile($tmp,'nobody',null)===null);
// Cursor
for($i=0;$i<20;$i++)elvado_so_post($tmp,$C,"Post $i",2000+$i*700);
$p=elvado_so_feed($tmp,'user',null,(string)$C['id']);t('Seite 1 voll + mehr',count($p['items'])===ELVADO_SO_PER_PAGE&&$p['more']);
$p2=elvado_so_feed($tmp,'user',null,(string)$C['id'],end($p['items'])['id']);t('Seite 2',count($p2['items'])===5&&!$p2['more']&&$p2['items'][0]['id']!==$p['items'][0]['id']);
// Löschen
t('fremden nicht löschen',elvado_so_delete($tmp,$A,false,$pb)['code']===403);
t('Moderator löscht',elvado_so_delete($tmp,null,true,$pb)['ok']);
t('Eigenen löschen',elvado_so_delete($tmp,$A,false,$p1['id'])['ok']);
// Konto löschen -> alles weg
elvado_so_post($tmp,$B,'Noch ein Ben-Beitrag',9000);elvado_so_follow($tmp,$C,(string)$B['id']);
elvado_cm_anonymize_member($tmp,(string)$B['id']);
$s=elvado_so_load($tmp);
t('Konto-Löschung: Beiträge weg',!in_array((string)$B['id'],array_column($s['posts'],'author'),true));
t('Konto-Löschung: Folgen weg',!in_array((string)$B['id'],array_merge(...array_values($s['follows']?:[[]])),true));
system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

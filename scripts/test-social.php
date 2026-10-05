<?php
// Prüft das soziale Netzwerk (cms/lib/social.php). Aufruf: php scripts/test-social.php
declare(strict_types=1);
require __DIR__.'/../cms/lib/social.php';
$fail=0;$n=0;
function t(string $name,bool $ok): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name\n";} }
$tmp=sys_get_temp_dir().'/rrw-social-'.bin2hex(random_bytes(4));mkdir($tmp);
$cfg=rrw_cm_config_clean(['enabled'=>true]);
foreach(['anna','ben','cara'] as $i=>$u)rrw_cm_register($tmp,$cfg,['username'=>$u,'email'=>"$u@example.com",'password'=>'geheim1234','consent'=>true,'opened'=>0],'203.0.113.'.($i+1));
[$A,$B,$C]=rrw_cm_members($tmp);
t('zu kurz',rrw_so_post($tmp,$A,'x',1000)['code']===400);
$p1=rrw_so_post($tmp,$A,"Hallo <b>Welt</b>\r\nzweite Zeile",1000);t('posten',$p1['ok']);
t('Doppelpost',rrw_so_post($tmp,$A,"Hallo <b>Welt</b>\nzweite Zeile",1001)['code']===409);
t('500 Zeichen Limit',mb_strlen(rrw_so_feed($tmp,'all',null)['items'][0]['body'])<=500&&rrw_so_post($tmp,$A,str_repeat('ä',900),1002)['ok']&&mb_strlen(rrw_so_feed($tmp,'all',null)['items'][0]['body'])===500);
$pb=rrw_so_post($tmp,$B,'Beitrag von Ben',1003)['id'];
// Likes
$r=rrw_so_like($tmp,$A,$pb);t('Like an',$r['ok']&&$r['liked']&&$r['likes']===1);
t('Like aus',!rrw_so_like($tmp,$A,$pb)['liked']);
rrw_so_like($tmp,$A,$pb);rrw_so_like($tmp,$C,$pb);
$f=rrw_so_feed($tmp,'all',$A);$mine=array_values(array_filter($f['items'],fn($x)=>$x['id']===$pb))[0];
t('Like-Zähler und liked-Flag',$mine['likes']===2&&$mine['liked']);
t('Anonym sieht kein liked',rrw_so_feed($tmp,'all',null)['items'][0]['liked']===false);
t('Like unbekannter Beitrag',rrw_so_like($tmp,$A,'rffffffffff')['code']===404);
// Folgen
t('nicht sich selbst folgen',rrw_so_follow($tmp,$A,(string)$A['id'])['code']===400);
t('unbekanntes Mitglied',rrw_so_follow($tmp,$A,'ffffffffff')['code']===404);
t('folgen',rrw_so_follow($tmp,$A,(string)$B['id'])['following']===true);
$fe=rrw_so_feed($tmp,'following',$A);$authors=array_unique(array_column($fe['items'],'username'));sort($authors);
t('Feed: eigene + gefolgte',$authors===['anna','ben']);
t('Feed ohne Folgen: nur eigene',array_unique(array_column(rrw_so_feed($tmp,'following',$C)['items'],'username'))===[]);
t('entfolgen',rrw_so_follow($tmp,$A,(string)$B['id'])['following']===false);
rrw_so_follow($tmp,$A,(string)$B['id']);
// Profil
$pr=rrw_so_profile($tmp,'ben',$A);t('Profil: Zähler',$pr['followers']===1&&$pr['posts']===1&&$pr['is_following']&&!$pr['is_me']&&!isset($pr['member']['email']));
t('Profil: unbekannt',rrw_so_profile($tmp,'nobody',null)===null);
// Cursor
for($i=0;$i<20;$i++)rrw_so_post($tmp,$C,"Post $i",2000+$i*700);
$p=rrw_so_feed($tmp,'user',null,(string)$C['id']);t('Seite 1 voll + mehr',count($p['items'])===RRW_SO_PER_PAGE&&$p['more']);
$p2=rrw_so_feed($tmp,'user',null,(string)$C['id'],end($p['items'])['id']);t('Seite 2',count($p2['items'])===5&&!$p2['more']&&$p2['items'][0]['id']!==$p['items'][0]['id']);
// Löschen
t('fremden nicht löschen',rrw_so_delete($tmp,$A,false,$pb)['code']===403);
t('Moderator löscht',rrw_so_delete($tmp,null,true,$pb)['ok']);
t('Eigenen löschen',rrw_so_delete($tmp,$A,false,$p1['id'])['ok']);
// Konto löschen -> alles weg
rrw_so_post($tmp,$B,'Noch ein Ben-Beitrag',9000);rrw_so_follow($tmp,$C,(string)$B['id']);
rrw_cm_anonymize_member($tmp,(string)$B['id']);
$s=rrw_so_load($tmp);
t('Konto-Löschung: Beiträge weg',!in_array((string)$B['id'],array_column($s['posts'],'author'),true));
t('Konto-Löschung: Folgen weg',!in_array((string)$B['id'],array_merge(...array_values($s['follows']?:[[]])),true));
system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

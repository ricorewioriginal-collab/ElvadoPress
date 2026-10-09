<?php
// Prüft das Forum (cms/lib/forum.php). Aufruf: php scripts/test-forum.php
declare(strict_types=1);
require __DIR__.'/../cms/lib/forum.php';
$fail=0;$n=0;
function t(string $name,bool $ok): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name\n";} }
$tmp=sys_get_temp_dir().'/elvado-forum-'.bin2hex(random_bytes(4));mkdir($tmp);
$cfg=elvado_cm_config_clean(['enabled'=>true]);
$a=elvado_cm_register($tmp,$cfg,['username'=>'anna','email'=>'a@example.com','password'=>'geheim1234','consent'=>true,'opened'=>0],'203.0.113.1');
$b=elvado_cm_register($tmp,$cfg,['username'=>'ben','email'=>'b@example.com','password'=>'geheim1234','consent'=>true,'opened'=>0],'203.0.113.2');
$ms=elvado_cm_members($tmp);$A=$ms[0];$B=$ms[1];
// Kategorien
$cats=elvado_fo_categories_save($tmp,[['name'=>'Allgemein','desc'=>'<b>Hallo</b>'],['name'=>'x'],['name'=>'Technik']]);
t('Kategorien bereinigt (zu kurzer Name fällt raus)',count($cats)===2&&$cats[0]['desc']==='Hallo');
$c1=$cats[0]['id'];$c2=$cats[1]['id'];
// Themen
t('Titel zu kurz',elvado_fo_topic_create($tmp,$A,['cat'=>$c1,'title'=>'ab','body'=>'Text hier'],2000)['code']===400);
t('unbekannte Kategorie',elvado_fo_topic_create($tmp,$A,['cat'=>'cffffffffff','title'=>'Hallo Welt','body'=>'Text hier'],2000)['code']===404);
$r=elvado_fo_topic_create($tmp,$A,['cat'=>$c1,'title'=>"  Hallo   Welt ",'body'=>"Erster<script>Beitrag\r\nZeile2"],2000);
t('Thema erstellt',$r['ok']&&elvado_fo_id_ok($r['id']));$tid=$r['id'];
$v=elvado_fo_topic($tmp,$tid,1);
t('Titel normalisiert',$v['topic']['title']==='Hallo Welt');
t('Text bleibt Text (kein Entfernen, nur Anzeige als Text)',str_contains($v['posts'][0]['body'],'<script>')&&!str_contains($v['posts'][0]['body'],"\r"));
t('Autor-Name statt ID',$v['posts'][0]['author']==='anna');
// Antworten
$r2=elvado_fo_reply($tmp,$B,$tid,'Antwort von Ben',false,2100);
t('Antwort',$r2['ok']);$pid=$r2['id'];
t('Doppelpost abgewiesen',elvado_fo_reply($tmp,$B,$tid,'Antwort von Ben',false,2101)['code']===409);
t('zu kurz',elvado_fo_reply($tmp,$B,$tid,' ',false,2102)['code']===400);
t('Zähler',elvado_fo_topic($tmp,$tid,1)['topic']['replies']===1);
// Rate-Limit: 3 Themen / 10 Min
for($i=0;$i<3;$i++)$last=elvado_fo_topic_create($tmp,$B,['cat'=>$c2,'title'=>"Thema $i",'body'=>'Inhalt'],3000+$i);
t('Rate-Limit Themen greift beim 3.+1',$last['code']===429||elvado_fo_topic_create($tmp,$B,['cat'=>$c2,'title'=>'Noch eins','body'=>'Inhalt'],3010)['code']===429);
// Bearbeiten
t('fremden Beitrag nicht bearbeiten',elvado_fo_edit($tmp,$A,$tid,$pid,'Hack',2200)['code']===403);
t('eigenen bearbeiten',elvado_fo_edit($tmp,$B,$tid,$pid,'Geändert',2200)['ok']);
t('nach 30 Min gesperrt',elvado_fo_edit($tmp,$B,$tid,$pid,'Zu spät',2100+1801)['code']===403);
// Sperren / Anheften
t('schließen',elvado_fo_mod($tmp,'lock',$tid)['ok']);
t('geschlossen: Mitglied kann nicht antworten',elvado_fo_reply($tmp,$A,$tid,'Noch was',false,2300)['code']===403);
t('geschlossen: Moderator kann',elvado_fo_reply($tmp,$A,$tid,'Moderation',true,2301)['ok']);
elvado_fo_mod($tmp,'unlock',$tid);elvado_fo_mod($tmp,'pin',$tid);
$list=elvado_fo_topics($tmp,$c1,1);
t('angeheftet zuerst',$list['items'][0]['id']===$tid&&$list['items'][0]['pinned']);
t('Verschieben',elvado_fo_mod($tmp,'move',$tid,$c2)['ok']&&elvado_fo_topics($tmp,$c2,1)['items'][0]['id']===$tid);
t('Verschieben in unbekannte Kategorie',elvado_fo_mod($tmp,'move',$tid,'cffffffffff')['code']===404);
t('ungültige Aktion',elvado_fo_mod($tmp,'zerstoeren',$tid)['code']===400);
// Melden
$rep=elvado_fo_report($tmp,$A,$tid,$pid,'<b>Spam</b>',4000);
t('melden',$rep['ok']&&count(elvado_fo_reports($tmp))===1&&elvado_fo_reports($tmp)[0]['reason']==='Spam');
elvado_fo_report($tmp,$A,$tid,$pid,'nochmal',4001);
t('doppelte Meldung zählt einmal',count(elvado_fo_reports($tmp))===1);
t('Meldung zu unbekanntem Beitrag',elvado_fo_report($tmp,$A,$tid,'rffffffffff','x',4002)['code']===404);
// Löschen
t('fremden Beitrag nicht löschen',elvado_fo_delete_post($tmp,$A,false,$tid,$pid)['code']===403);
t('Moderator löscht Beitrag',elvado_fo_delete_post($tmp,null,true,$tid,$pid)['ok']&&count(elvado_fo_reports($tmp))===0);
$first=elvado_fo_posts($tmp,$tid)[0]['id'];
t('Autor kann Thema mit Antworten nicht löschen',elvado_fo_delete_post($tmp,$A,false,$tid,$first)['code']===403);
// Anonymisieren bei Konto-Löschung
elvado_cm_anonymize_member($tmp,(string)$A['id']);
t('anonymisiert: "Gelöschtes Mitglied"',elvado_fo_topic($tmp,$tid,1)['posts'][0]['author']==='Gelöschtes Mitglied');
// Thema löschen
t('Moderator löscht Thema',elvado_fo_mod($tmp,'delete',$tid)['ok']&&elvado_fo_topic($tmp,$tid,1)===null&&!is_file(elvado_cm_dir($tmp).'/forum-'.$tid.'.json'));
t('Pfad-Trick in Thema-ID',elvado_fo_posts($tmp,'../../x')===[]);
// Paging
$cnt=0;for($i=0;$i<25;$i++){$rr=elvado_fo_topic_create($tmp,$B,['cat'=>$c1,'title'=>"Seite $i",'body'=>'Inhalt'],10000+$i*1000);if($rr['ok'])$cnt++;}
$pg=elvado_fo_topics($tmp,$c1,1);t('Seiten-Aufteilung',$pg['pages']>=1&&count($pg['items'])<=ELVADO_FO_PER_PAGE);
system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

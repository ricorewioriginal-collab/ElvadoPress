<?php
// Prüft die CMS-Umfragen (cms/lib/polls.php). Aufruf: php scripts/test-polls.php
declare(strict_types=1);
require __DIR__.'/../cms/lib/polls.php';
$fail=0;$n=0;
function t(string $name,bool $ok): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name\n";} }
$tmp=sys_get_temp_dir().'/rrw-polls-'.bin2hex(random_bytes(4));mkdir($tmp);

// Bereinigung
t('zu wenige Antworten',rrw_poll_clean(['question'=>'F?','options'=>"Ja"])===null);
t('ohne Frage',rrw_poll_clean(['question'=>' ','options'=>"Ja\nNein"])===null);
t('doppelte Antworten zählen einmal',rrw_poll_clean(['question'=>'F?','options'=>"Ja\nja\nNein"])['options'][1]['text']==='Nein');
$p=rrw_poll_clean(['question'=>'<b>Lieblingsfarbe?</b>','options'=>"Rot\n<i>Blau</i>\nGrün",'show_results'=>'quatsch','ends_at'=>'2031-01-02T03:04']);
t('HTML entfernt',$p['question']==='Lieblingsfarbe?'&&$p['options'][1]['text']==='Blau');
t('ungültige Anzeige-Einstellung -> after_vote',$p['show_results']==='after_vote');
t('Ende normalisiert',$p['ends_at']==='2031-01-02 03:04:00');
t('höchstens 12 Antworten',count(rrw_poll_clean(['question'=>'F','options'=>implode("\n",range(1,20))])['options'])===12);
// Stimmen bleiben beim Bearbeiten erhalten
$p['options'][0]['votes']=7;
$p2=rrw_poll_clean(['question'=>'Neu formuliert','options'=>"Rot\nBlau\nGelb"],$p);
t('Stimmen bleiben (gleicher Text)',$p2['options'][0]['votes']===7&&$p2['id']===$p['id']);
t('neue Antwort startet bei 0',$p2['options'][2]['votes']===0);

// Zustand
$now=strtotime('2030-06-01 12:00:00');
t('offen',rrw_poll_state(['closed'=>false],$now)==='open');
t('beendet manuell',rrw_poll_state(['closed'=>true],$now)==='closed');
t('noch nicht gestartet',rrw_poll_state(['starts_at'=>'2030-07-01 00:00:00'],$now)==='upcoming');
t('abgelaufen',rrw_poll_state(['ends_at'=>'2030-05-01 00:00:00'],$now)==='closed');

// Abstimmen
$poll=rrw_poll_clean(['question'=>'Farbe?','options'=>"Rot\nBlau\nGrün"]);rrw_polls_save($tmp,[$poll]);
$ids=array_column($poll['options'],'id');$c1=str_repeat('a',24);$c2=str_repeat('b',24);
$r=rrw_poll_vote($tmp,$poll['id'],[$ids[0]],$c1,'203.0.113.1');
t('Stimme ok',$r['ok']&&rrw_poll_total(rrw_polls_load($tmp)[0])===1);
t('gleiche Browser-Kennung: abgewiesen',rrw_poll_vote($tmp,$poll['id'],[$ids[1]],$c1,'203.0.113.2')['code']===409);
t('hat abgestimmt erkannt',rrw_poll_has_voted($tmp,$poll['id'],$c1)&&!rrw_poll_has_voted($tmp,$poll['id'],$c2));
t('ungültige Antwort',rrw_poll_vote($tmp,$poll['id'],['ffffff'],$c2,'203.0.113.3')['code']===400);
t('Einfachauswahl: nur eine',rrw_poll_vote($tmp,$poll['id'],[$ids[0],$ids[1]],$c2,'203.0.113.3')['code']===400);
t('unbekannte Umfrage',rrw_poll_vote($tmp,'00000000',[$ids[0]],$c2,'203.0.113.3')['code']===404);
t('ungültige Kennung',rrw_poll_vote($tmp,$poll['id'],[$ids[0]],'kurz','203.0.113.3')['code']===400);
// gleiche IP: nach 5 Stimmen Schluss
$ok=0;for($i=0;$i<8;$i++){if(rrw_poll_vote($tmp,$poll['id'],[$ids[2]],str_repeat((string)($i+1),24),'198.51.100.7')['ok'])$ok++;}
t('höchstens 5 Stimmen pro Anschluss',$ok===5);
$v=file_get_contents(rrw_poll_voters_file($tmp));
t('keine IP und keine Kennung im Klartext',strpos($v,'198.51')===false&&strpos($v,$c1)===false);
// Mehrfachauswahl
$m=rrw_poll_clean(['question'=>'Mehrere?','options'=>"A\nB\nC",'multiple'=>true]);rrw_polls_save($tmp,[$poll,$m]);
$mid=array_column($m['options'],'id');
t('Mehrfachauswahl erlaubt',rrw_poll_vote($tmp,$m['id'],[$mid[0],$mid[2]],str_repeat('m',24),'192.0.2.1')['ok']);
// beendete Umfrage
$closed=rrw_poll_clean(['question'=>'Ende?','options'=>"X\nY",'closed'=>true]);rrw_polls_save($tmp,[$closed]);
t('beendet: keine Stimme',rrw_poll_vote($tmp,$closed['id'],[$closed['options'][0]['id']],str_repeat('z',24),'192.0.2.2')['code']===403);

// Rein numerische Antwort-IDs (Altdaten/Handarbeit) dürfen keine Stimme verschlucken
$numPoll=['id'=>'11111111','question'=>'Zahlen?','multiple'=>true,'show_results'=>'always','closed'=>false,'starts_at'=>'','ends_at'=>'','created_at'=>'2030-01-01 00:00:00','options'=>[['id'=>'123456','text'=>'A','votes'=>0],['id'=>'654321','text'=>'B','votes'=>0]]];
rrw_polls_save($tmp,[$numPoll]);
t('numerische ID: Stimme wird gezählt',rrw_poll_vote($tmp,'11111111',['123456','654321','123456'],str_repeat('n',24),'192.0.2.9')['ok']&&rrw_poll_total(rrw_polls_load($tmp)[0])===2);
$re=rrw_poll_clean(['question'=>'Zahlen?','options'=>"A\nB"],$numPoll);
t('numerische ID bleibt beim Bearbeiten ein String',$re['options'][0]['id']==='123456');
$ok=true;for($i=0;$i<300;$i++){$o=rrw_poll_clean(['question'=>'F','options'=>"x\ny\nz"])['options'];foreach($o as $x)if(ctype_digit($x['id']))$ok=false;}
t('neue IDs nie rein numerisch (300 Versuche)',$ok);
// Öffentliche Sicht
$pp=['id'=>'aaaaaaaa','question'=>'Q','options'=>[['id'=>'a1','text'=>'A','votes'=>3],['id'=>'b1','text'=>'B','votes'=>1]],'show_results'=>'after_vote'];
t('vor der Abstimmung keine Zahlen',!isset(rrw_poll_public($pp,false)['options'][0]['votes'])&&rrw_poll_public($pp,false)['total']===null);
$pv=rrw_poll_public($pp,true);t('nach der Abstimmung Prozente',$pv['options'][0]['percent']==75.0&&$pv['total']===4);
t('after_close: erst bei Ende',!rrw_poll_public(['show_results'=>'after_close']+$pp,true)['results']&&rrw_poll_public(['show_results'=>'after_close','closed'=>true]+$pp,false)['results']);
t('always: sofort',rrw_poll_public(['show_results'=>'always']+$pp,false)['results']);
// Aufräumen
rrw_poll_voters_drop($tmp,$poll['id']);t('Wähler-Hashes beim Löschen entfernt',!isset(rrw_tools_read(rrw_poll_voters_file($tmp),[])[$poll['id']]));
// CSV
$csv=rrw_polls_csv(['question'=>'=Frage','options'=>[['text'=>'A','votes'=>1],['text'=>'B','votes'=>3]]]);
t('CSV: Formel entschärft',strpos($csv,'"\'=Frage"')!==false&&strpos($csv,'25 %')!==false&&strpos($csv,'Gesamt;4')!==false);

$rm=function(string $d) use (&$rm){foreach(array_diff(scandir($d),['.','..']) as $f){$p=$d.'/'.$f;is_dir($p)?$rm($p):unlink($p);}rmdir($d);};$rm($tmp);
echo $fail===0?"$n von $n Prüfungen bestanden\n":"$fail von $n Prüfungen FEHLGESCHLAGEN\n";
exit($fail===0?0:1);

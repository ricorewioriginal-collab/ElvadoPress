<?php
// Prüft Kontaktformular/Newsletter (cms/lib/forms.php). Aufruf: php scripts/test-forms.php
declare(strict_types=1);
require __DIR__.'/../cms/lib/publish.php';
require __DIR__.'/../cms/lib/mail.php';
require __DIR__.'/../cms/lib/forms.php';
$fail=0;$n=0;
function t(string $name,bool $ok): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name\n";} }
$tmp=sys_get_temp_dir().'/rrw-forms-'.bin2hex(random_bytes(4));mkdir($tmp);
$site=['widget_areas'=>[['id'=>'a','widgets'=>[['id'=>'w-contact','type'=>'contact-form','title'=>'Kontakt','settings'=>['success'=>'Danke!']],['id'=>'w-news','type'=>'newsletter','title'=>'NL','settings'=>[]],['id'=>'w-text','type'=>'text','settings'=>[]]]]],'widget_inactive'=>[]];

// Bereinigung
t('ungültige E-Mail',rrw_forms_clean('contact',['name'=>'A','email'=>'kein-email','message'=>'Hallo Welt','consent'=>1])['ok']===false);
t('Einwilligung nötig',rrw_forms_clean('contact',['name'=>'A','email'=>'a@b.de','message'=>'Hallo Welt'])['ok']===false);
t('Name nötig',rrw_forms_clean('contact',['name'=>' ','email'=>'a@b.de','message'=>'Hallo Welt','consent'=>1])['ok']===false);
t('Nachricht zu kurz',rrw_forms_clean('contact',['name'=>'A','email'=>'a@b.de','message'=>'hi','consent'=>1])['ok']===false);
$c=rrw_forms_clean('contact',['name'=>'<b>Eva</b>','email'=>'eva@example.org','subject'=>'<i>Hi</i>','message'=>'<script>x</script>Hallo Welt','consent'=>1]);
t('HTML entfernt',$c['ok']&&$c['data']['name']==='Eva'&&strpos($c['data']['message'],'<')===false&&strpos($c['data']['subject'],'<')===false);
t('Newsletter nur E-Mail',rrw_forms_clean('newsletter',['email'=>'x@y.de','consent'=>1])['data']===['email'=>'x@y.de']);

// Widget-Suche
t('Formular-Widget gefunden',rrw_forms_find_widget($site,'w-contact')!==null);
t('Text-Widget ist kein Formular',rrw_forms_find_widget($site,'w-text')===null);
t('unbekannt/ungültig',rrw_forms_find_widget($site,'nix')===null&&rrw_forms_find_widget($site,'../x')===null);

// Einsenden
$ok=['widget_id'=>'w-contact','name'=>'Eva','email'=>'eva@example.org','message'=>'Hallo zusammen','consent'=>1,'opened'=>(time()-30)*1000];
$r=rrw_forms_submit($tmp,$site,$ok,'203.0.113.5');
t('Einsendung ok',$r['ok']&&$r['message']==='Danke!'&&count(rrw_forms_load($tmp))===1);
t('Datensatz ohne IP',strpos(json_encode(rrw_forms_load($tmp)),'203.0.113')===false);
t('Honeypot: scheinbar ok, nichts gespeichert',rrw_forms_submit($tmp,$site,$ok+['hp'=>'spam'],'203.0.113.6')['ok']&&count(rrw_forms_load($tmp))===1);
t('zu schnell',rrw_forms_submit($tmp,$site,['opened'=>time()*1000]+$ok,'203.0.113.7')['code']===429);
t('unbekanntes Widget',rrw_forms_submit($tmp,$site,['widget_id'=>'w-text']+$ok,'203.0.113.8')['code']===404);
$nl=['widget_id'=>'w-news','email'=>'Leser@Example.org','consent'=>1,'opened'=>(time()-30)*1000];
t('Newsletter ok',rrw_forms_submit($tmp,$site,$nl,'203.0.113.9')['ok']);
t('Newsletter doppelt: keine zweite Zeile',rrw_forms_submit($tmp,$site,['email'=>'leser@example.org']+$nl,'203.0.113.10')['ok']&&count(array_filter(rrw_forms_load($tmp),fn($i)=>$i['kind']==='newsletter'))===1);

// Rate-Limit: 5 pro Stunde je Besucher
$h=rrw_forms_ip_hash($tmp,'198.51.100.1');$now=time();
$allowed=0;for($i=0;$i<8;$i++)if(rrw_forms_rate_ok($tmp,$h,$now+$i))$allowed++;
t('Rate-Limit 5 pro Stunde',$allowed===5);
t('andere Besucher unberührt',rrw_forms_rate_ok($tmp,rrw_forms_ip_hash($tmp,'198.51.100.2'),$now));
t('nach einer Stunde wieder frei',rrw_forms_rate_ok($tmp,$h,$now+3700));
t('Hash enthält keine IP',strpos($h,'198.51')===false&&strlen($h)===64);

// CSV (Formel-Injektion entschärft)
$csv=rrw_forms_csv([['kind'=>'contact','created_at'=>'2030-01-01 10:00:00','widget_title'=>'K','data'=>['name'=>'=HYPERLINK("x")','email'=>'a@b.de','subject'=>'','message'=>'Zeile "1"']]],'contact');
t('CSV: BOM und Kopfzeile',str_starts_with($csv,"\xEF\xBB\xBFDatum;Name"));
t('CSV: Formel entschärft',strpos($csv,'"\'=HYPERLINK')!==false&&strpos($csv,'"=HYPERLINK')===false);
t('CSV: Anführungszeichen verdoppelt',strpos($csv,'Zeile ""1""')!==false);

// Widget-Einstellungen & Öffentlichkeit
$inst=rrw_widget_settings_clean('contact-form',['notify'=>'chef@example.org','button'=>'Los','subject'=>'0']);
t('Widget-Einstellungen bereinigt',$inst['notify']==='chef@example.org'&&$inst['button']==='Los'&&$inst['subject']===false);
$pub=rrw_site_public(['widget_areas'=>[['id'=>'a','widgets'=>[['id'=>'w','type'=>'contact-form','settings'=>['notify'=>'geheim@example.org','button'=>'Los']]]]],'widget_inactive'=>[['id'=>'x','type'=>'newsletter','settings'=>['notify'=>'geheim2@example.org']]]]);
t('Empfänger-Adresse nicht öffentlich',strpos(json_encode($pub),'geheim')===false&&strpos(json_encode($pub),'Los')!==false);

$rm=function(string $d) use (&$rm){foreach(array_diff(scandir($d),['.','..']) as $f){$p=$d.'/'.$f;is_dir($p)?$rm($p):unlink($p);}rmdir($d);};$rm($tmp);
echo $fail===0?"$n von $n Prüfungen bestanden\n":"$fail von $n Prüfungen FEHLGESCHLAGEN\n";
exit($fail===0?0:1);

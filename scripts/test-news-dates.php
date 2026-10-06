<?php
// Prüft den Umgang mit Veröffentlichungszeiten von Beiträgen (cms/lib/publish.php): Der Editor liefert "YYYY-MM-DDTHH:MM", gespeichert wird
// "YYYY-MM-DD HH:MM:SS"; Vergleiche und Sortierung laufen über Zeitstempel (Zeichenkettenvergleich ließ Beiträge bis zum nächsten Tag verschwinden).
// Aufruf: php scripts/test-news-dates.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/rrw-nd-'.bin2hex(random_bytes(4));mkdir($tmp);define('RRW_DATA_DIR',$tmp);
require __DIR__.'/../cms/lib/publish.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
date_default_timezone_set('Europe/Berlin');
$past=time()-1800;$future=time()+7200;
$art=fn($pub,$st='published')=>['status'=>$st,'published_at'=>$pub,'created_at'=>'2020-01-01 00:00:00'];
// Gleicher Tag, kurz in der Vergangenheit – in allen vorkommenden Formaten sichtbar
t('Format "Y-m-d H:i:s" (Vergangenheit) ist live',rrw_news_is_live($art(date('Y-m-d H:i:s',$past))));
t('Format "Y-m-dTH:i" aus dem Editor (Vergangenheit, gleicher Tag) ist live',rrw_news_is_live($art(date('Y-m-d\TH:i',$past))),date('Y-m-d\TH:i',$past));
t('Format "Y-m-dTH:i:s" ist live',rrw_news_is_live($art(date('Y-m-d\TH:i:s',$past))));
t('ISO 8601 mit Zeitzone (Vergangenheit) ist live',rrw_news_is_live($art(date('c',$past))));
t('UTC-ISO mit Z (Vergangenheit) ist live',rrw_news_is_live($art(gmdate('Y-m-d\TH:i:s\Z',$past))));
t('leeres Datum ist live',rrw_news_is_live($art('')));
// Zukunft: geplant, in allen Formaten
foreach(['Y-m-d H:i:s','Y-m-d\TH:i','Y-m-d\TH:i:s','c'] as $f)t("Zukunft im Format $f ist (noch) nicht live",!rrw_news_is_live($art(date($f,$future))),date($f,$future));
t('Unlesbares Datum: nicht live (kein Absturz)',!rrw_news_is_live($art('gestern')));
t('Entwurf/gelöscht nie live',!rrw_news_is_live($art(date('Y-m-d H:i:s',$past),'draft'))&&!rrw_news_is_live($art(date('Y-m-d H:i:s',$past))+['deleted_at'=>'2026-01-01 00:00:00']));
// Normalisierung beim Speichern
t('Normalisierung: Editor-Format → Speicherformat',rrw_news_date('2026-10-06T10:05')==='2026-10-06 10:05:00'&&rrw_news_date('2026-10-06 10:05:07')==='2026-10-06 10:05:07');
t('Normalisierung: ISO mit Zeitzone → Serverzeit',rrw_news_date('2026-10-06T08:05:00Z')==='2026-10-06 10:05:00'&&rrw_news_date('2026-10-06T10:05:00+02:00')==='2026-10-06 10:05:00');
t('Normalisierung: leer/unlesbar → Rückfall',rrw_news_date('','X')==='X'&&rrw_news_date('Quatsch','Y')==='Y'&&rrw_news_date('')==='');
// Sortierung neueste zuerst, gemischte Formate
$rows=[['id'=>1,'published_at'=>'2026-10-06 09:00:00'],['id'=>2,'published_at'=>'2026-10-06T10:30'],['id'=>3,'published_at'=>'2026-10-05 23:59:59'],['id'=>4,'published_at'=>'','created_at'=>'2026-10-06 09:30:00'],['id'=>5,'published_at'=>'2026-10-06T08:00:00+02:00']];
usort($rows,'rrw_news_cmp_desc');
t('Sortierung neueste zuerst über Zeitstempel (gemischte Formate)',array_column($rows,'id')===[2,4,1,5,3],implode(',',array_column($rows,'id')));
// Dateien, die Beiträge ausliefern, rufen die Helfer
foreach(['cms/api.php','cms/rss.php','cms/lib/assistant.php','cms/lib/publish.php'] as $f)t("$f sortiert nicht mehr per strcmp auf published_at",!preg_match("/strcmp\\(\\(string\\)\\(\\\$b\\['published_at'\\]/",(string)file_get_contents(__DIR__.'/../'.$f)));
// PostFeed (Lovable)
require __DIR__.'/../cms/src/autoload.php';
file_put_contents($tmp.'/news.json',json_encode([['id'=>1,'slug'=>'a','title'=>'A','status'=>'published','published_at'=>date('Y-m-d\TH:i',$past),'created_at'=>'2020-01-01 00:00:00','body_html'=>'<p>x</p>'],
 ['id'=>2,'slug'=>'b','title'=>'B','status'=>'published','published_at'=>date('c',$future),'created_at'=>'2020-01-01 00:00:00','body_html'=>'<p>y</p>'],
 ['id'=>3,'slug'=>'c','title'=>'C','status'=>'published','published_at'=>date('Y-m-d H:i:s',$past-600),'created_at'=>'2020-01-01 00:00:00','body_html'=>'<p>z</p>']]));
$feed=\Elvado\Lovable\PostFeed::rowsFromNewsFile($tmp.'/news.json');
t('Lovable-Feed: Beitrag im Editor-Format sichtbar, Zukunft ausgeblendet, richtige Reihenfolge',array_column($feed,'slug')===['a','c'],json_encode(array_column($feed,'slug')));
system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

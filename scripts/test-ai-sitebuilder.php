<?php
// Prüft den KI-Website-Generator (cms/src/Ai/SiteBuilder.php) mit Fake-Transport (kein Netz): Anfrage an das Gateway, strenge Prüfung und Abbildung der KI-Antwort
// auf CMS-Formate (Farben mit Kontrast, Adressen, eindeutige Seitenadressen, Blöcke, keine Skripte/Bilder-URLs). Aufruf: php scripts/test-ai-sitebuilder.php
declare(strict_types=1);
require __DIR__.'/../cms/src/autoload.php';
use Elvado\Ai\{AiGatewayConfig,AiGatewayService,AiGatewayException,SiteBuilder};use Elvado\Support\{Http,HttpResponse};
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
function thr(callable $f,string $needle=''): ?AiGatewayException { try{ $f();return null; }catch(AiGatewayException $e){ return ($needle===''||str_contains($e->getMessage(),$needle))?$e:null; } }
$tmp=sys_get_temp_dir().'/elvado-sb-'.bin2hex(random_bytes(4));mkdir($tmp);
$calls=[];$answer='';
Http::useTransport(function(string $m,string $u,array $h,?string $b,array $o) use(&$calls,&$answer): HttpResponse { $calls[]=['u'=>$u,'b'=>$b?json_decode($b,true):null,'o'=>$o];return new HttpResponse(200,json_encode(['choices'=>[['message'=>['content'=>$answer]]],'usage'=>['prompt_tokens'=>1,'completion_tokens'=>2]])); });
$cfg=AiGatewayConfig::load($tmp);$cfg->save(['providers'=>['groq'=>['api_key'=>'gsk-test-key-1234']],'purposes'=>['builder'=>'groq']]);$cfg=AiGatewayConfig::load($tmp);
$sb=new SiteBuilder(new AiGatewayService($cfg));
$good=['site'=>['title'=>'Schreinerei Weller','tagline'=>'Handwerk aus Holz'],
 'palette'=>['accent'=>'#8b5a2b','bg'=>'#faf6f0','card'=>'#ffffff','text'=>'#2b1d12','hero_bg'=>'#3b2a1a','hero_text'=>'#fff8ee'],
 'home'=>[['type'=>'hero','props'=>['title'=>'Möbel nach Maß','text'=>'Individuell & langlebig','btn_label'=>'Anfragen','btn_url'=>'/kontakt.html','image'=>'https://evil.example/x.jpg']],
   ['type'=>'features','props'=>['title'=>'Warum wir','items'=>[['title'=>'Qualität','text'=>'Sorgfältig gefertigt'],['title'=>'Beratung','text'=>'Persönlich'],['title'=>'','text'=>'leer']],'columns'=>3]],
   ['type'=>'html','props'=>['code'=>'<script>alert(1)</script>']],['type'=>'unbekannt','props'=>[]],
   ['type'=>'text','props'=>['title'=>'Über','body'=>"Erster <b>Absatz</b>\nZweiter <script>x</script>",'bg'=>'rot']],
   ['type'=>'posts','props'=>['title'=>'News','count'=>99]],['type'=>'cta','props'=>['title'=>'Los','btn_label'=>'Kontakt','btn_url'=>'javascript:alert(1)']]],
 'pages'=>[['title'=>'Über uns','blocks'=>[['type'=>'heading','text'=>'Wer wir sind','level'=>9],['type'=>'text','text'=>"Absatz eins\n\nAbsatz <i>zwei</i>"],['type'=>'list','items'=>['Eins','<b>Zwei</b>','']],['type'=>'button','label'=>'Kontakt','url'=>'/kontakt.html'],['type'=>'bild','url'=>'x']]],
   ['title'=>'Kontakt','blocks'=>[['type'=>'text','text'=>'Schreib uns.']]],['title'=>'Über uns','blocks'=>[['type'=>'text','text'=>'Doppelt']]],['title'=>'Leer','blocks'=>[]]],
 'posts'=>[['title'=>'Willkommen','excerpt'=>'Hallo','paragraphs'=>['Eins <script>x</script>','Zwei']],['title'=>'','paragraphs'=>['ohne Titel']]]];
$answer=json_encode($good);
$plan=$sb->plan(['description'=>'Schreinerei Weller: Handwerk, Qualität und individuelle Lösungen aus Holz','name'=>'','tone'=>'klassisch','existing_slugs'=>['kontakt']]);
t('Anfrage geht an den Anbieter des Einsatzzwecks „Website-Generator“ (Groq) mit JSON-Aufforderung und großem Rahmen',str_contains($calls[0]['u'],'api.groq.com')&&$calls[0]['b']['max_tokens']===6000&&str_contains($calls[0]['b']['messages'][0]['content'],'JSON-Objekt')&&str_contains($calls[0]['b']['messages'][0]['content'],'klassisch und seriös'));
t('Zeitrahmen für große Aufgaben (150 s)',$calls[0]['o']['timeout']===150);
t('Titel, Slogan, Meta',$plan['site']['title']==='Schreinerei Weller'&&$plan['site']['tagline']==='Handwerk aus Holz'&&$plan['meta']['provider']==='groq');
t('Palette: sechs gültige Farben',count($plan['palette'])===6&&!array_filter($plan['palette'],fn($c)=>!preg_match('/^#[0-9a-f]{6}$/',$c)));
$types=array_column($plan['home'],'type');
t('Startseite: unbekannte Typen und rohes HTML fliegen raus, Reihenfolge bleibt',$types===['hero','features','text','posts','cta'],implode(',',$types));
$hero=$plan['home'][0]['props'];
t('Hero: fremde Bild-Adresse entfernt, Link auf vorhandene Seite erlaubt',$hero['image']===''&&$hero['btn_url']==='/kontakt.html');
t('Vorteile: leere Einträge entfernt',count($plan['home'][1]['props']['items'])===2);
t('Text: HTML in Absätze mit maskiertem Inhalt umgewandelt, ungültiger Hintergrund → default',$plan['home'][2]['props']['body']==='<p>Erster Absatz</p><p>Zweiter x</p>'&&$plan['home'][2]['props']['bg']==='default',$plan['home'][2]['props']['body']);
t('Beitragsliste: Anzahl begrenzt (12), Aufruf-Link ohne javascript:',$plan['home'][3]['props']['count']===12&&$plan['home'][4]['props']['btn_url']==='#');
$pages=$plan['pages'];
t('Seiten: leere und doppelte werden gleich behandelt – Adressen eindeutig, „Leer“ entfällt',array_column($pages,'slug')===['ueber-uns','kontakt-2','ueber-uns-2'],implode(',',array_column($pages,'slug')));
$b=$pages[0]['blocks'];
t('Blöcke: Überschrift mit gültiger Ebene, Absätze, Liste als HTML (maskiert), Button, unbekannter Typ entfällt',array_column($b,'type')===['heading','text','html','button']&&$b[0]['level']===2&&$b[1]['text']==="Absatz eins\n\nAbsatz zwei"&&$b[2]['html']==='<ul><li>Eins</li><li>Zwei</li></ul>'&&$b[3]['url']==='/kontakt.html');
t('Blöcke haben Kennungen im CMS-Format',!array_filter($b,fn($x)=>!preg_match('/^blk_[0-9a-f]{8}$/',$x['id'])&&true));
t('Beiträge: nur mit Titel, Absätze als <p> maskiert, Auszug',count($plan['posts'])===1&&str_contains($plan['posts'][0]['body_html'],'<p>Eins x</p>')&&!str_contains($plan['posts'][0]['body_html'],'<script'));
t('Menü aus den Seiten',array_column($plan['menu'],'slug')===['ueber-uns','kontakt-2','ueber-uns-2']);
// Farben mit schlechtem Kontrast werden korrigiert
$bad=SiteBuilder::normalize(['site'=>['title'=>'X'],'palette'=>['bg'=>'#ffffff','text'=>'#fafafa','card'=>'#000000','hero_bg'=>'#111111','hero_text'=>'#222222','accent'=>'#fefefe'],'home'=>[['type'=>'hero','props'=>['title'=>'a']]]]);
$p=$bad['palette'];
t('Kontrast: Text auf Hintergrund und Hero ≥ 4,5, Karte lesbar, Akzent hebt sich ab',SiteBuilder::contrast($p['bg'],$p['text'])>=4.5&&SiteBuilder::contrast($p['hero_bg'],$p['hero_text'])>=4.5&&SiteBuilder::contrast($p['card'],$p['text'])>=4.5&&SiteBuilder::contrast($p['bg'],$p['accent'])>=2.0,json_encode($p));
t('Kurzform #rgb und ungültige Farben',SiteBuilder::normalize(['palette'=>['accent'=>'#f00','bg'=>'rot'],'home'=>[['type'=>'hero','props'=>['title'=>'a']]]])['palette']['accent']==='#ff0000');
t('Startseite beginnt immer mit einem Hero',SiteBuilder::normalize(['site'=>['title'=>'Meine Firma','tagline'=>'Slogan'],'home'=>[['type'=>'cta','props'=>['title'=>'x']]]])['home'][0]['type']==='hero');
t('Teile abwählbar',(function(){$q=SiteBuilder::normalize($GLOBALS['good'],'',[],['home'=>false,'pages'=>false,'posts'=>true]);return $q['home']===[]&&$q['pages']===[]&&count($q['posts'])===1;})());
t('Seitenadresse: Umlaute und Sonderzeichen',SiteBuilder::slug('Über uns & Größe!')==='ueber-uns-groesse'&&SiteBuilder::slug('???')==='seite');
t('Reservierte Adressen werden nicht vergeben',SiteBuilder::normalize(['pages'=>[['title'=>'Impressum','blocks'=>[['type'=>'text','text'=>'x']]]]])['pages'][0]['slug']==='impressum-2');
// Bild-Suchbegriffe (für die freien Bilder)
t('Bild-Suchbegriffe: bereinigt (Sonderzeichen, Länge) und nur für hero, image_text und Beiträge',(function(){
    $q=SiteBuilder::normalize(['site'=>['title'=>'X'],'home'=>[['type'=>'hero','props'=>['title'=>'a','image_query'=>'wood <b>workshop</b>, carpenter!!']],['type'=>'image_text','props'=>['title'=>'b','text'=>'c','image_query'=>str_repeat('abcd ',30)]],['type'=>'cta','props'=>['title'=>'c','image_query'=>'ignored']]],
        'posts'=>[['title'=>'P','paragraphs'=>['x'],'image_query'=>'  ']]]);
    return $q['home'][0]['props']['image_query']==='wood workshop carpenter'&&mb_strlen($q['home'][1]['props']['image_query'])<=60&&!isset($q['home'][2]['props']['image_query'])&&$q['posts'][0]['image_query']==='';
})());
t('Bild-Suchbegriff: zu kurz oder kein Text → leer',SiteBuilder::query('ab')===''&&SiteBuilder::query(['x'])===''&&SiteBuilder::query('Holz Werkstatt')==='Holz Werkstatt');
t('Anweisung an die KI nennt image_query',str_contains($calls[0]['b']['messages'][0]['content'],'image_query'));
// Fehlerfälle
t('Zu kurze Beschreibung wird abgelehnt (ohne Anfrage)',thr(fn()=>$sb->plan(['description'=>'Hallo']),'genauer')!==null&&count($calls)===1);
$answer='Das ist leider kein JSON.';
t('Kein JSON von der KI: verständliche Meldung',thr(fn()=>$sb->plan(['description'=>'Eine Bäckerei mit Café in Hamburg, Brot, Kuchen, Frühstück']))!==null);
$answer=json_encode(['foo'=>'bar']);
t('Unbrauchbares JSON: Meldung „kein brauchbarer Entwurf“',thr(fn()=>$sb->plan(['description'=>'Eine Bäckerei mit Café in Hamburg, Brot, Kuchen, Frühstück']),'keinen brauchbaren')!==null);
t('Nur interne Aufrufer dürfen die großen Aufgaben nutzen',thr(fn()=>(new AiGatewayService($cfg))->generate(['provider'=>'groq','task'=>'site','prompt'=>'x']),'Unbekannte Aufgabe')!==null);
Http::useTransport(null);
system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

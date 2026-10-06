<?php
// Prüft die Felder eines Beitrags: eindeutige Adresse (Slug), Kommentare (Standard/offen/geschlossen), noindex, eigene kanonische Adresse –
// Bereinigung beim Speichern (rrw_news_unique_slug, rrw_news_seo_fields in cms/lib/publish.php), Ausgabe im WordPress-Theme (Kommentarstatus, <link rel="canonical">,
// <meta robots>) und Sitemap (cms/lib/seo.php). Aufruf: php scripts/test-news-fields.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/rrw-nf-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/cms');mkdir($tmp.'/wp-content/themes');mkdir($tmp.'/themes-native');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('RRW_WP_DATA',$tmp.'/cms/.wp');define('RRW_WP_CMS_DATA',$tmp.'/cms');define('RRW_DATA_DIR',$tmp.'/cms');
$_SERVER['HTTP_HOST']='example.test';$_SERVER['REMOTE_ADDR']='203.0.113.5';define('RRW_WP_TEST_NATIVE',dirname(__DIR__).'/cms/themes');
$base=['status'=>'published','published_at'=>'2026-01-10 10:00:00','created_at'=>'2026-01-10 10:00:00','author'=>'Anna','category'=>'News','excerpt'=>'x'];
$news=[
 $base+['id'=>1,'slug'=>'normal','title'=>'Normal','body_html'=>'<p>Text eins</p>'],
 $base+['id'=>2,'slug'=>'versteckt','title'=>'Versteckt','body_html'=>'<p>Text zwei</p>','noindex'=>true],
 $base+['id'=>3,'slug'=>'zweitveroeffentlichung','title'=>'Zweit','body_html'=>'<p>Text drei</p>','canonical_url'=>'https://original.example/artikel?a=1&b=2'],
 $base+['id'=>4,'slug'=>'zu','title'=>'Zu','body_html'=>'<p>Text vier</p>','comments'=>'closed'],
 $base+['id'=>5,'slug'=>'auf','title'=>'Auf','body_html'=>'<p>Text fünf</p>','comments'=>'open'],
 array_merge($base,['id'=>6,'slug'=>'entwurf','title'=>'Entwurf','body_html'=>'<p>x</p>','status'=>'draft']),
];
file_put_contents($tmp.'/cms/news.json',json_encode($news));
$site=['portal'=>['site_name'=>'Test'],'comments'=>['enabled'=>true,'require_approval'=>true],'seo'=>['enabled'=>true,'canonical_base'=>'https://example.test','index_news'=>true],'pages'=>[],'menus'=>['top'=>[],'bottom'=>[]]];
file_put_contents($tmp.'/cms/site.json',json_encode($site));
require __DIR__.'/_testdb.php';
require __DIR__.'/../cms/wp/load.php';require __DIR__.'/../cms/wp/router.php';
require_once __DIR__.'/../cms/lib/publish.php';require_once __DIR__.'/../cms/lib/seo.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }

// ── Adresse (Slug) beim Speichern
t('Slug: freie Adresse bleibt',rrw_news_unique_slug($news,99,'neu')==='neu');
t('Slug: belegte Adresse bekommt -2, dann -3',rrw_news_unique_slug($news,99,'normal')==='normal-2'&&rrw_news_unique_slug(array_merge($news,[['id'=>7,'slug'=>'normal-2']]),99,'normal')==='normal-3');
t('Slug: der Beitrag selbst zählt beim Bearbeiten nicht als Doppelung',rrw_news_unique_slug($news,1,'normal')==='normal');
t('Slug: aus Titel mit Umlauten und Sonderzeichen',rrw_slug('Größe & Maß: Straße 5!')===rrw_slug('Größe & Maß: Straße 5!')&&preg_match('/^[a-z0-9-]+$/',rrw_slug('Größe & Maß: Straße 5!'))===1&&!str_contains(rrw_slug('Größe & Maß: Straße 5!'),'--'));

// ── Felder bereinigen
$f=rrw_news_seo_fields(['comments'=>'closed','noindex'=>'1','canonical_url'=>' https://a.example/x ','seo_title'=>str_repeat('t',100),'seo_description'=>str_repeat('d',300)]);
t('Felder: Kommentare, noindex, Adresse, Längen',$f['comments']==='closed'&&$f['noindex']===true&&$f['canonical_url']==='https://a.example/x'&&mb_strlen($f['seo_title'])===70&&mb_strlen($f['seo_description'])===200);
t('Kommentare: unbekannter Wert → default; offen bleibt offen',rrw_news_seo_fields(['comments'=>'egal'])['comments']==='default'&&rrw_news_seo_fields([])['comments']==='default'&&rrw_news_seo_fields(['comments'=>'open'])['comments']==='open');
t('noindex: ohne Angabe falsch',rrw_news_seo_fields([])['noindex']===false&&rrw_news_seo_fields(['noindex'=>''])['noindex']===false);
t('Canonical: nur gültige http(s)-Adressen',rrw_news_seo_fields(['canonical_url'=>'javascript:alert(1)'])['canonical_url']===''&&rrw_news_seo_fields(['canonical_url'=>'kein url'])['canonical_url']===''&&rrw_news_seo_fields(['canonical_url'=>'ftp://x.example/a'])['canonical_url']===''&&rrw_news_seo_fields(['canonical_url'=>'http://ok.example/a'])['canonical_url']==='http://ok.example/a');

// ── Kommentarstatus im WordPress-Beitrag
$post=fn(array $a)=>rrw_wp_cms_news_post($a);
t('WP: Kommentare Standard = offen, „closed“ = geschlossen, „open“ = offen',$post($news[0])->comment_status==='open'&&$post($news[3])->comment_status==='closed'&&$post($news[4])->comment_status==='open');
t('WP: Beitragsfelder landen am Beitrag (rrw_data)',($post($news[1])->rrw_data['noindex']??false)===true&&$post($news[2])->rrw_data['canonical_url']==='https://original.example/artikel?a=1&b=2');

// ── Ausgabe im Kopfbereich des Beitrags
update_option('permalink_structure','/%postname%/');rrw_wp_boot(['theme'=>true]);
@mkdir(RRW_WP_DATA,0775,true);touch(RRW_WP_DATA.'/front-on');
function head_of(string $slug): string { $r=rrw_wp_dispatch('/'.$slug.'/','GET',[],[]);return (string)($r['body']??''); }
$h1=head_of('normal');$h2=head_of('versteckt');$h3=head_of('zweitveroeffentlichung');
t('Seite lädt (Beitrag gefunden)',str_contains($h1,'Text eins')&&str_contains($h2,'Text zwei')&&str_contains($h3,'Text drei'));
t('Canonical: Standard ist die eigene Adresse',(bool)preg_match('#<link rel="canonical" href="[^"]*/normal/?"#',$h1));
t('Canonical: eigene kanonische Adresse (Zweitveröffentlichung) wird ausgegeben, & escaped',str_contains($h3,'<link rel="canonical" href="https://original.example/artikel?a=1&#038;b=2"')||str_contains($h3,'<link rel="canonical" href="https://original.example/artikel?a=1&amp;b=2"'));
t('noindex: nur beim markierten Beitrag, mit follow',str_contains($h2,'<meta name="robots" content="noindex,follow"')&&!str_contains($h1,'noindex')&&!str_contains($h3,'noindex'));

// ── Sitemap
rrw_seo_generate($site,$news,$tmp);
$sm=(string)@file_get_contents($tmp.'/sitemap.xml');
t('Sitemap: normale Beiträge enthalten, noindex und Entwurf nicht',str_contains($sm,'#normal<')&&str_contains($sm,'#zweitveroeffentlichung<')&&!str_contains($sm,'#versteckt')&&!str_contains($sm,'#entwurf'));
$site2=$site;$site2['seo']['index_news']=false;rrw_seo_generate($site2,$news,$tmp);
t('Sitemap: ohne „index_news“ keine Beiträge',!str_contains((string)file_get_contents($tmp.'/sitemap.xml'),'#normal'));

system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n Prüfungen fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

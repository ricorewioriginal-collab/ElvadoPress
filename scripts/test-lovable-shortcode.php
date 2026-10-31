<?php
// Prüft den Shortcode [lovable] (cms/lib/wp-shortcodes.php, auf echtem WordPress): Widget aus der Tabelle lovable_widgets wird als Mount-Punkt mit geprüfter Konfiguration ausgegeben, Skript nur mit erlaubtem Host. Aufruf: php scripts/test-lovable-shortcode.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/elvado-ls-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/cms');mkdir($tmp.'/wp-content/themes');
$_SERVER['HTTP_HOST']='example.test';$_SERVER['REMOTE_ADDR']='203.0.113.5';
$themesSrc=__DIR__.'/../cms/wp-content/themes';
$news=[];
for($i=1;$i<=5;$i++)$news[]=['id'=>$i,'slug'=>"beitrag-$i",'title'=>"Beitrag $i".($i===2?' <script>alert(1)</script>':''),'category'=>$i%2?'News':'Events','tags'=>$i<3?'musik, radio':'','excerpt'=>"Auszug $i",'body_html'=>"<p>Inhalt von Beitrag $i [forum]</p>",'status'=>'published','published_at'=>"2026-0$i-10 10:00:00",'author'=>'Anna Autor','image_url'=>$i===1?'/img/a.jpg':''];
file_put_contents($tmp.'/cms/news.json',json_encode($news));
file_put_contents($tmp.'/cms/site.json',json_encode(['portal'=>['site_name'=>'Mein Radio','tagline'=>'Hör rein'],'comments'=>['enabled'=>true,'require_approval'=>true],'branding'=>['portal_logo'=>'/logo.png'],
 'menus'=>['top'=>[['id'=>'m1','label'=>'Start','target'=>'system:start','enabled'=>true,'parent_id'=>''],['id'=>'m2','label'=>'Über uns','target'=>'page:ueber','enabled'=>true,'parent_id'=>''],['id'=>'m3','label'=>'Sender','target'=>'system:sender','enabled'=>true,'parent_id'=>'']],'bottom'=>[['id'=>'b1','label'=>'Impressum','target'=>'url:https://example.com/impressum','enabled'=>true,'parent_id'=>'']]],
 'pages'=>[['id'=>'ueber','slug'=>'ueber-uns','title'=>'Über uns','type'=>'custom','enabled'=>true,'blocks_before'=>[['type'=>'html','html'=>'<p>Wir sind ein Radio.</p>']],'blocks_after'=>[]],['id'=>'sender','type'=>'system','system_target'=>'sender','enabled'=>true,'headline'=>'Unsere Sender','intro'=>'Alle Sender im Überblick.','blocks_before'=>[],'blocks_after'=>[]]]]));
require_once __DIR__.'/wpe-render.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
$env=wpe_render_env(['news.json'=>(string)file_get_contents($tmp.'/cms/news.json'),'site.json'=>(string)file_get_contents($tmp.'/cms/site.json')]);
if(!$env){ echo "Hinweis: übersprungen (WPE_TEST_ZIP nicht gesetzt).\n";system('rm -rf '.escapeshellarg($tmp));echo "0 von 0 Prüfungen bestanden\n";exit(0); }
$body=<<<'BODY'
$data=dirname(ELVADO_WP_DATA);
t('Ohne eingerichtetes Widget: leer',do_shortcode('[lovable widget="news-grid"]')==='');
$db=\Elvado\Database\DatabaseConnection::fromCmsSettings($data);$db->migrateCore();$repo=new \Elvado\Repository\LovableWidgetRepository($db);
$repo->save(['project_id'=>'p1','component_name'=>'news-grid','config'=>['posts'=>['limit'=>4],'attributes'=>['data-theme'=>'dark'],'script_url'=>'https://cdn.lovable.example/p1.js']]);
$repo->save(['project_id'=>'p1','component_name'=>'aus-widget','enabled'=>false]);
$o=do_shortcode('[lovable widget="news-grid"]');
t('Mount-Punkt mit Konfiguration',str_contains($o,'data-elvado-lovable=')&&str_contains($o,'class="ep-lovable"')&&str_contains($o,'&quot;componentName&quot;:&quot;news-grid&quot;')&&str_contains($o,'api-lovable-provider.php?widget=news-grid&amp;project=p1')||str_contains($o,'api-lovable-provider.php?widget=news-grid&project=p1')||str_contains($o,'widget=news-grid'));
preg_match('/data-elvado-lovable="([^"]*)"/',$o,$m);$cfg=json_decode(html_entity_decode($m[1]??''),true);
t('Konfiguration gültig: Projekt, Daten-Adresse, Attribute als Objekt',is_array($cfg)&&$cfg['projectId']==='p1'&&str_starts_with($cfg['dataSourceUrl'],'/cms/api-lovable-provider.php?widget=news-grid')&&$cfg['attributes']==['data-theme'=>'dark']);
t('Skript-Adresse nur mit erlaubtem Host (zunächst leer)',$cfg['scriptUrl']===''&&$cfg['allowedHosts']===[]);
\Elvado\Lovable\LovableSettings::load($data)->save(['bridge'=>['script_hosts'=>['cdn.lovable.example']]]);
preg_match('/data-elvado-lovable="([^"]*)"/',do_shortcode('[lovable widget="news-grid"]'),$m2);$cfg2=json_decode(html_entity_decode($m2[1]??''),true);
t('Mit erlaubtem Host: Skript-Adresse gesetzt',$cfg2['scriptUrl']==='https://cdn.lovable.example/p1.js'&&$cfg2['allowedHosts']===['cdn.lovable.example']);
t('Abgeschaltetes, unbekanntes und ungültiges Widget bleibt leer',do_shortcode('[lovable widget="aus-widget"]')===''&&do_shortcode('[lovable widget="gibt-es-nicht"]')===''&&do_shortcode('[lovable widget="Böse Name"]')===''&&do_shortcode('[lovable]')==='');
t('Falsches Projekt → leer',do_shortcode('[lovable widget="news-grid" project="p9"]')==='');
t('Kein Zugriff auf den Schlüssel/Token im Ausgabetext',!str_contains($o,'token')&&!str_contains($o,'secret'));
t('Bündel wird für die Seite eingebunden',wp_script_is('elvado-react','enqueued'));
BODY;
foreach(wpe_render_suite($env,$body) as [$nm,$ok,$ex])t($nm,$ok,$ex);
system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

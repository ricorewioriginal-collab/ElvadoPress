<?php
declare(strict_types=1);

header('Content-Type: application/rss+xml; charset=UTF-8');
header('Cache-Control: public, max-age=300');

require_once __DIR__.'/lib/sites.php';elvado_sites_request_context();   // mehrere Websites: Feed der aufgerufenen Domain
require_once __DIR__.'/lib/system.php';elvado_system_apply_timezone();   // Zeitzone der Einrichtung auch für Website, Feed und REST (sonst weichen Veröffentlichungszeiten ab)
require_once __DIR__.'/lib/product.php';
require_once __DIR__.'/lib/pack.php';
function elvado_xml(string $s): string {
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}
function elvado_cdata(string $s): string {
    return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $s) . ']]>';
}
// Multi-Brand: Links im Feed zeigen auf die Domain, über die der Feed abonniert wurde; Inhalte sind für alle Marken dieselben.
$elvadoBase=rtrim(elvado_default_canonical_base(),'/');$elvadoBrandName='';
try{
    require_once __DIR__.'/lib/seo.php';
    require_once __DIR__.'/lib/brand.php';
    $elvadoBrandSite=elvado_read_json(elvado_site_dir('data').'/site.json',[]);
    $elvadoBrand=elvado_brand_resolve($elvadoBrandSite,(string)($_SERVER['HTTP_HOST']??''),null);
    $elvadoBase=rtrim($elvadoBrand['origin'],'/');$elvadoBrandName=(string)($elvadoBrand['name']??'');
}catch(Throwable $e){}
header('Vary: Host');
function elvado_cfg(): array {
    $f=elvado_site_dir('data').'/site.json';
    if(!is_file($f))return [];
    $j=json_decode((string)file_get_contents($f),true);
    return is_array($j)?$j:[];
}
// Beiträge direkt aus den eigenen Beiträgen plus den eingestellten externen Quellen.
function elvado_public_news(array $cfg): array {
    $f=elvado_site_dir('data').'/news.json';$rows=[];
    if(is_file($f)){$x=json_decode((string)file_get_contents($f),true);if(is_array($x))$rows=$x;}
    $rows=array_values(array_filter($rows,function($a){
        if(!is_array($a)||($a['status']??'draft')!=='published'||!empty($a['deleted_at']))return false;
        $publishedAt=trim((string)($a['published_at']??''));
        return $publishedAt===''||$publishedAt<=date('Y-m-d H:i:s');
    }));
    if(!empty($cfg['feed_sources'])){
        try{ require_once __DIR__.'/lib/feeds.php';$rows=array_merge($rows,elvado_external_feed_articles($cfg)); }catch(Throwable $e){}
    }
    return $rows;
}

$cfg=elvado_cfg();
$set=is_array($cfg['rss']??null)?$cfg['rss']:[];
if(array_key_exists('enabled',$set) && !$set['enabled']){
    http_response_code(404);
    echo '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>Feed deaktiviert</title></channel></rss>';
    exit;
}
$elvadoSiteName=trim((string)($cfg['portal']['site_name']??''))?:($elvadoBrandName!==''?$elvadoBrandName:elvado_product()['name']);
$title=trim((string)($set['title']??''))?:$elvadoSiteName.' – News & Magazin';
$description=(string)($set['description']??'Aktuelle Beiträge von '.$elvadoSiteName.'.');
$max=max(5,min(100,(int)($set['max_items']??50)));
$includeExternal=!array_key_exists('include_external',$set)||!empty($set['include_external']);
$rows=elvado_public_news($cfg);
if(!$includeExternal)$rows=array_values(array_filter($rows,fn($a)=>empty($a['is_external'])));
// neueste zuerst, über Zeitstempel (Zeichenkettenvergleich versagt bei gemischten Formaten wie „…T10:00“)
$elvadoTs=static fn($x)=>strtotime((string)(($x['published_at']??'')!==''?$x['published_at']:($x['created_at']??'')))?:0;
usort($rows,fn($a,$b)=>$elvadoTs($b)<=>$elvadoTs($a));
$rows=array_slice($rows,0,$max);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?xml-stylesheet type="text/xsl" href="/cms/rss.xsl"?>' . "\n";
?>
<rss version="2.0"
     xmlns:atom="http://www.w3.org/2005/Atom"
     xmlns:content="http://purl.org/rss/1.0/modules/content/"
     xmlns:media="http://search.yahoo.com/mrss/">
<channel>
  <title><?=elvado_xml($title)?></title>
  <link><?=elvado_xml($elvadoBase)?>/#news</link>
  <description><?=elvado_xml($description)?></description>
  <language>de-de</language>
  <lastBuildDate><?=gmdate(DATE_RSS)?></lastBuildDate>
  <generator><?=elvado_xml(elvado_product_generator())?></generator>
  <atom:link href="<?=elvado_xml($elvadoBase)?>/cms/rss.php" rel="self" type="application/rss+xml" />
<?php foreach($rows as $a):
    $external=!empty($a['is_external']);
    $slug=(string)($a['slug']??'');
    $link=$external?(string)($a['external_url']??''):$elvadoBase.'/#news/'.rawurlencode($slug!==''?$slug:(string)($a['id']??''));
    if($link==='')$link=$elvadoBase.'/#news';
    $pub=(string)($a['published_at']??$a['created_at']??'');
    $ts=$pub!==''?strtotime($pub):false;
    $desc=(string)($a['excerpt']??'');
    $body=(string)($a['body_html']??'');
    if(str_contains($body,'[')){if(!function_exists('elvado_expand_shortcodes'))require_once __DIR__.'/lib/publish.php';$body=elvado_expand_shortcodes($body);}
    if($body==='')$body='<p>'.htmlspecialchars($desc,ENT_QUOTES,'UTF-8').'</p>';
    $img=(string)($a['image_url']??'');
    if($img!==''&&str_starts_with($img,'/'))$img=$elvadoBase.$img;
?>
  <item>
    <title><?=elvado_xml((string)($a['title']??''))?></title>
    <link><?=elvado_xml($link)?></link>
    <guid isPermaLink="false"><?=elvado_xml('elvado-news-'.(string)($a['id']??md5($link)))?></guid>
    <pubDate><?=gmdate(DATE_RSS,$ts?:time())?></pubDate>
    <category><?=elvado_xml((string)($a['category']??'News'))?></category>
    <?php if(!empty($a['author'])):?><author><?=elvado_xml((string)$a['author'])?></author><?php endif; ?>
    <description><?=elvado_cdata($desc)?></description>
    <content:encoded><?=elvado_cdata($body)?></content:encoded>
    <?php if($img!==''):?><media:content url="<?=elvado_xml($img)?>" medium="image" /><?php endif; ?>
    <?php if($external && !empty($a['external_source'])):?><source url="<?=elvado_xml((string)($a['external_feed_url']??$link))?>"><?=elvado_xml((string)$a['external_source'])?></source><?php endif; ?>
  </item>
<?php endforeach; ?>
</channel>
</rss>

<?php
declare(strict_types=1);

header('Content-Type: application/rss+xml; charset=UTF-8');
header('Cache-Control: public, max-age=300');

require_once __DIR__.'/lib/sites.php';rrw_sites_request_context();   // mehrere Websites: Feed der aufgerufenen Domain
require_once __DIR__.'/lib/product.php';
require_once __DIR__.'/lib/pack.php';
function rrw_xml(string $s): string {
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}
function rrw_cdata(string $s): string {
    return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $s) . ']]>';
}
// Multi-Brand: Links im Feed zeigen auf die Domain, über die der Feed abonniert wurde; Inhalte sind für alle Marken dieselben.
$rrwBase=rtrim(rrw_default_canonical_base(),'/');$rrwBrandName='';
try{
    require_once __DIR__.'/lib/seo.php';
    require_once __DIR__.'/lib/brand.php';
    $rrwBrandSite=rrw_read_json(rrw_site_dir('data').'/site.json',[]);
    $rrwBrand=rrw_brand_resolve($rrwBrandSite,(string)($_SERVER['HTTP_HOST']??''),null);
    $rrwBase=rtrim($rrwBrand['origin'],'/');$rrwBrandName=(string)($rrwBrand['name']??'');
}catch(Throwable $e){}
header('Vary: Host');
function rrw_cfg(): array {
    $f=rrw_site_dir('data').'/site.json';
    if(!is_file($f))return [];
    $j=json_decode((string)file_get_contents($f),true);
    return is_array($j)?$j:[];
}
// Beiträge direkt aus den eigenen Beiträgen plus den eingestellten externen Quellen.
function rrw_public_news(array $cfg): array {
    $f=rrw_site_dir('data').'/news.json';$rows=[];
    if(is_file($f)){$x=json_decode((string)file_get_contents($f),true);if(is_array($x))$rows=$x;}
    $rows=array_values(array_filter($rows,function($a){
        if(!is_array($a)||($a['status']??'draft')!=='published'||!empty($a['deleted_at']))return false;
        $publishedAt=trim((string)($a['published_at']??''));
        return $publishedAt===''||$publishedAt<=date('Y-m-d H:i:s');
    }));
    if(!empty($cfg['feed_sources'])){
        try{ require_once __DIR__.'/lib/feeds.php';$rows=array_merge($rows,rrw_external_feed_articles($cfg)); }catch(Throwable $e){}
    }
    return $rows;
}

$cfg=rrw_cfg();
$set=is_array($cfg['rss']??null)?$cfg['rss']:[];
if(array_key_exists('enabled',$set) && !$set['enabled']){
    http_response_code(404);
    echo '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>Feed deaktiviert</title></channel></rss>';
    exit;
}
$rrwSiteName=trim((string)($cfg['portal']['site_name']??''))?:($rrwBrandName!==''?$rrwBrandName:rrw_product()['name']);
$title=trim((string)($set['title']??''))?:$rrwSiteName.' – News & Magazin';
$description=(string)($set['description']??'Aktuelle Beiträge von '.$rrwSiteName.'.');
$max=max(5,min(100,(int)($set['max_items']??50)));
$includeExternal=!array_key_exists('include_external',$set)||!empty($set['include_external']);
$rows=rrw_public_news($cfg);
if(!$includeExternal)$rows=array_values(array_filter($rows,fn($a)=>empty($a['is_external'])));
// neueste zuerst, über Zeitstempel (Zeichenkettenvergleich versagt bei gemischten Formaten wie „…T10:00“)
$rrwTs=static fn($x)=>strtotime((string)(($x['published_at']??'')!==''?$x['published_at']:($x['created_at']??'')))?:0;
usort($rows,fn($a,$b)=>$rrwTs($b)<=>$rrwTs($a));
$rows=array_slice($rows,0,$max);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?xml-stylesheet type="text/xsl" href="/cms/rss.xsl"?>' . "\n";
?>
<rss version="2.0"
     xmlns:atom="http://www.w3.org/2005/Atom"
     xmlns:content="http://purl.org/rss/1.0/modules/content/"
     xmlns:media="http://search.yahoo.com/mrss/">
<channel>
  <title><?=rrw_xml($title)?></title>
  <link><?=rrw_xml($rrwBase)?>/#news</link>
  <description><?=rrw_xml($description)?></description>
  <language>de-de</language>
  <lastBuildDate><?=gmdate(DATE_RSS)?></lastBuildDate>
  <generator><?=rrw_xml(rrw_product_generator())?></generator>
  <atom:link href="<?=rrw_xml($rrwBase)?>/cms/rss.php" rel="self" type="application/rss+xml" />
<?php foreach($rows as $a):
    $external=!empty($a['is_external']);
    $slug=(string)($a['slug']??'');
    $link=$external?(string)($a['external_url']??''):$rrwBase.'/#news/'.rawurlencode($slug!==''?$slug:(string)($a['id']??''));
    if($link==='')$link=$rrwBase.'/#news';
    $pub=(string)($a['published_at']??$a['created_at']??'');
    $ts=$pub!==''?strtotime($pub):false;
    $desc=(string)($a['excerpt']??'');
    $body=(string)($a['body_html']??'');
    if(str_contains($body,'[')){if(!function_exists('rrw_expand_shortcodes'))require_once __DIR__.'/lib/publish.php';$body=rrw_expand_shortcodes($body);}
    if($body==='')$body='<p>'.htmlspecialchars($desc,ENT_QUOTES,'UTF-8').'</p>';
    $img=(string)($a['image_url']??'');
    if($img!==''&&str_starts_with($img,'/'))$img=$rrwBase.$img;
?>
  <item>
    <title><?=rrw_xml((string)($a['title']??''))?></title>
    <link><?=rrw_xml($link)?></link>
    <guid isPermaLink="false"><?=rrw_xml('rrw-news-'.(string)($a['id']??md5($link)))?></guid>
    <pubDate><?=gmdate(DATE_RSS,$ts?:time())?></pubDate>
    <category><?=rrw_xml((string)($a['category']??'News'))?></category>
    <?php if(!empty($a['author'])):?><author><?=rrw_xml((string)$a['author'])?></author><?php endif; ?>
    <description><?=rrw_cdata($desc)?></description>
    <content:encoded><?=rrw_cdata($body)?></content:encoded>
    <?php if($img!==''):?><media:content url="<?=rrw_xml($img)?>" medium="image" /><?php endif; ?>
    <?php if($external && !empty($a['external_source'])):?><source url="<?=rrw_xml((string)($a['external_feed_url']??$link))?>"><?=rrw_xml((string)$a['external_source'])?></source><?php endif; ?>
  </item>
<?php endforeach; ?>
</channel>
</rss>

<?php
declare(strict_types=1);

require_once __DIR__.'/tools.php';
require_once __DIR__.'/pack.php';
require_once __DIR__.'/product.php';
function rrw_seo_defaults(array $site): array {
    $s=is_array($site['seo']??null)?$site['seo']:[];
    if(!rrw_pack_available()){   // eigenständiges CMS: keine RicoReWi-Vorgaben
        $nm=trim((string)($site['portal']['site_name']??''));
        return array_merge(['enabled'=>true,'site_title'=>$nm!==''?$nm:'Meine Website','description'=>'','canonical_base'=>rrw_default_canonical_base(),'index_custom_pages'=>true,'index_news'=>true,'robots'=>'index,follow','og_image'=>''], $s);
    }
    return array_merge(['enabled'=>true,'site_title'=>'RicoReWi Radioportal','description'=>'RicoReWi × AnMaCha Radioportal – Webradio, News, Podcasts, Voting und Community.','canonical_base'=>'https://www.ricorewi-radio.de','index_custom_pages'=>true,'index_news'=>true,'robots'=>'index,follow','og_image'=>'/icon-512.png'], $s);
}
function rrw_seo_system_map(): array {
    return ['sender'=>'sender','sendeplan'=>'sendeplan','voting'=>'voting','podcast'=>'podcast','news'=>'news','hilfe'=>'hilfe','apps'=>'apps','fanshop'=>'shops'];
}
function rrw_seo_generate_system_pages(array $site,string $root,array $seo): array {
    $made=[];$base=rtrim((string)$seo['canonical_base'],'/');$map=rrw_seo_system_map();$theme=(string)($site['theme']['active']??(rrw_pack_available()?'ricorewi-neon':'rrw-classic'));
    $siteName=trim((string)($site['portal']['site_name']??''))?:rrw_product_name();
    $suffix=rrw_pack_available()?'RicoReWi Radio':$siteName;
    $logoAlt=rrw_pack_available()?'RicoReWi Radio':$siteName;
    $backLabel=rrw_pack_available()?'Interaktive Seite im Radioportal öffnen':'Website öffnen';
    $footerLabel=rrw_pack_available()?'RicoReWi Radioportal':$siteName;
    foreach((array)($site['pages']??[]) as $p){
        if(($p['type']??'')!=='system'||empty($p['enabled']))continue;$target=(string)($p['system_target']??'');if(!isset($map[$target]))continue;
        $slug=$map[$target];$title=trim((string)($p['headline']??''))?:trim((string)($p['title']??ucfirst($slug)));$intro=trim((string)($p['intro']??''));
        $desc=$intro!==''?$intro:(string)($seo['description']??$siteName);
        $mt=trim((string)($p['meta_title']??''));$md=trim((string)($p['meta_description']??''));if($md!=='')$desc=$md;
        $robots=!empty($p['noindex'])?'noindex,follow':(string)($seo['robots']??'index,follow');
        $content=function_exists('rrw_render_blocks')?rrw_render_blocks(array_merge((array)($p['blocks_before']??[]),(array)($p['blocks_after']??[])),(array)($site['widgets']??[])):'';
        $href='/#'.rawurlencode($target);$canon=$base.'/'.$slug.'.html';$favicon=(string)($site['branding']['favicon']??'/icon-192.png');
        $themeLink=$theme!==''?'<link rel="stylesheet" href="/cms/themes/'.htmlspecialchars($theme,ENT_QUOTES,'UTF-8').'/theme.css">':'';
        $html='<!-- RRW-CMS-SEO-GENERATED -->'."\n".'<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.htmlspecialchars($mt!==''?$mt:$title.' – '.$suffix,ENT_QUOTES,'UTF-8').'</title><meta name="description" content="'.htmlspecialchars($desc,ENT_QUOTES,'UTF-8').'"><meta name="robots" content="'.htmlspecialchars($robots,ENT_QUOTES,'UTF-8').'"><link rel="canonical" href="'.htmlspecialchars($canon,ENT_QUOTES,'UTF-8').'"><link rel="icon" href="'.htmlspecialchars($favicon,ENT_QUOTES,'UTF-8').'"><link rel="stylesheet" href="/cms/generated/page.css">'.$themeLink.'</head><body><header><a class="brand" href="/"><img src="'.htmlspecialchars((string)($site['branding']['portal_logo']??'/logo-lockup.png'),ENT_QUOTES,'UTF-8').'" alt="'.htmlspecialchars($logoAlt,ENT_QUOTES,'UTF-8').'"></a></header><main><article><h1>'.htmlspecialchars($title,ENT_QUOTES,'UTF-8').'</h1>'.($intro!==''?'<p class="intro">'.htmlspecialchars($intro,ENT_QUOTES,'UTF-8').'</p>':'').$content.'<p><a class="rrw-btn" href="'.$href.'">'.htmlspecialchars($backLabel,ENT_QUOTES,'UTF-8').'</a></p></article></main><footer><a href="/">'.htmlspecialchars($footerLabel,ENT_QUOTES,'UTF-8').'</a></footer></body></html>';
        rrw_write_atomic($root.'/'.$slug.'.html',$html);$made[]=$slug;
    } return $made;
}
function rrw_seo_generate(array $site,array $news,string $root): void {
    $seo=rrw_seo_defaults($site);if(empty($seo['enabled']))return;$base=rtrim((string)$seo['canonical_base'],'/');$urls=[];
    $urls[]=['loc'=>$base.'/','lastmod'=>date('Y-m-d')];
    $map0=rrw_seo_system_map();$system=rrw_seo_generate_system_pages($site,$root,$seo);$noindexSlugs=[];foreach((array)($site['pages']??[]) as $np)if(!empty($np['noindex'])&&($np['type']??'')==='system')$noindexSlugs[$map0[(string)($np['system_target']??'')]??'']=1;
    foreach($system as $slug){if(isset($noindexSlugs[$slug]))continue;$urls[]=['loc'=>$base.'/'.$slug.'.html','lastmod'=>date('Y-m-d')];}
    foreach((array)($site['pages']??[]) as $p){if(empty($p['enabled'])||!empty($p['noindex'])||!rrw_page_is_live($p))continue;if(($p['type']??'')==='custom'&&!empty($seo['index_custom_pages']))$urls[]=['loc'=>$base.'/'.rawurlencode((string)$p['slug']).'.html','lastmod'=>date('Y-m-d')];}
    if(!empty($seo['index_news']))foreach($news as $a){if(empty($a['slug'])||!rrw_news_is_live($a)||!empty($a['noindex']))continue;$urls[]=['loc'=>$base.'/news.html#'.rawurlencode((string)$a['slug']),'lastmod'=>substr((string)($a['updated_at']??$a['published_at']??date('Y-m-d')),0,10)];}
    $xml='<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
    foreach($urls as $u)$xml.='  <url><loc>'.htmlspecialchars($u['loc'],ENT_XML1,'UTF-8').'</loc><lastmod>'.htmlspecialchars($u['lastmod'],ENT_XML1,'UTF-8').'</lastmod></url>'."\n";$xml.="</urlset>\n";
    rrw_write_atomic($root.'/sitemap.xml',$xml);
    $robots="User-agent: *\nAllow: /\nDisallow: /cms/data/\nDisallow: /cms/backups/\nDisallow: /cms/plugins/\nSitemap: ".$base."/sitemap.xml\n";rrw_write_atomic($root.'/robots.txt',$robots);
}

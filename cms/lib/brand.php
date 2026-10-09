<?php
declare(strict_types=1);
// ---------------------------------------------------------------------------------------------
// Multi-Domain / Multi-Brand: EINE Website, EIN CMS, EIN Datenbestand - mehrere Marken.
// Eine "Brand" (Marke) ist ein Satz Branding-Werte (Name, Logo, Favicon, Titel, Beschreibung,
// Social-Bild, Canonical-Verhalten, optionale Text-/Rechts-Overrides), der an Domains hängt.
// Welche Brand gilt, entscheidet der aufgerufene Hostname (z.B. senderwelt.de) - oder zur
// Vorschau im CMS der Parameter ?rrw_brand=<id>. Alle Inhalte (Sender, News, Widgets, Themes,
// Seiten, Menüs) bleiben gemeinsam; die Brand ändert nur, wie die Website "heißt" und aussieht.
// Themes bestimmen Layout/Design, Brands bestimmen Marke/Assets - zwei getrennte Ebenen.
// ---------------------------------------------------------------------------------------------
if(!function_exists('rrw_read_json')){
    function rrw_read_json(string $file,array $fallback=[]): array {
        if(!is_file($file))return $fallback;$d=json_decode((string)@file_get_contents($file),true);return is_array($d)?$d:$fallback;
    }
}
function rrw_brand_default_id(): string { return 'site'; }
// Standard-Registry: nur die eigene Hauptmarke, benannt nach der Website.
function rrw_brand_defaults(): array {
    $nm=trim((string)($GLOBALS['RRW_SITE']['portal']['site_name']??''));if($nm==='')$nm='Meine Website';
    return ['default'=>'site','items'=>[rrw_brand_blank(['id'=>'site','name'=>$nm,'short_name'=>$nm,'canonical_mode'=>'own','enabled'=>true,'builtin'=>true])]];
}
function rrw_brand_blank(array $o=[]): array {
    return array_merge([
        'id'=>'','name'=>'','short_name'=>'','claim'=>'','enabled'=>true,'builtin'=>false,
        'primary_domain'=>'','domains'=>[],
        'logo'=>'','logo_dark'=>'','logo_light'=>'','favicon'=>'','touch_icon'=>'','og_image'=>'','social_image'=>'',
        'title'=>'','title_suffix'=>'','description'=>'','manifest_name'=>'','manifest_short_name'=>'',
        'colors'=>['theme'=>'','accent'=>''],
        'canonical_mode'=>'own','canonical_base'=>'','app_prefix'=>'','directory'=>false,
        'legal'=>['imprint_mode'=>'shared','imprint_url'=>'','imprint_content'=>'','privacy_mode'=>'shared','privacy_url'=>'','privacy_content'=>''],
        'overrides'=>['portal'=>['site_name'=>'','hero_eyebrow'=>'','hero_title'=>'','hero_text'=>'','news_title'=>'','news_intro'=>'','footer_text'=>'','legal_notice'=>'']],
    ],$o);
}
function rrw_brand_id_clean(string $s): string { $s=strtolower(trim($s));$s=preg_replace('/[^a-z0-9-]+/','-',$s);return substr(trim((string)$s,'-'),0,40); }
function rrw_brand_domain_clean(string $d): string {
    $d=strtolower(trim($d));$d=preg_replace('#^https?://#','',$d);$d=explode('/',$d)[0];$d=explode(':',$d)[0];
    return preg_match('/^(?=.{1,253}$)([a-z0-9-]+\.)+[a-z]{2,}$/',$d)?$d:'';
}
function rrw_brand_asset_clean(string $v): string { $v=trim($v);if($v==='')return'';return preg_match('~^(https://|/)~i',$v)?mb_substr($v,0,1000):''; }
// Bereinigt die komplette brands-Sektion (CMS-Speichern). Die zwei eingebauten Marken können nicht
// gelöscht, aber frei bearbeitet werden; weitere Marken sind ohne Codeänderung möglich.
function rrw_brands_clean($value): array {
    $defaults=rrw_brand_defaults();$value=is_array($value)?$value:[];$items=[];$seen=[];
    $safe=function_exists('rrw_safe_html')?'rrw_safe_html':fn(string $h)=>strip_tags($h);
    foreach(array_slice((array)($value['items']??[]),0,20) as $b){
        if(!is_array($b))continue;$id=rrw_brand_id_clean((string)($b['id']??''));if($id===''||isset($seen[$id]))continue;$seen[$id]=true;
        $domains=[];foreach(array_slice((array)($b['domains']??[]),0,10) as $d){$d=rrw_brand_domain_clean((string)$d);if($d!=='')$domains[]=$d;}
        $primary=rrw_brand_domain_clean((string)($b['primary_domain']??''));
        $domains=array_values(array_unique(array_filter($domains,fn($d)=>$d!==$primary)));
        $legal=is_array($b['legal']??null)?$b['legal']:[];$ov=is_array($b['overrides']['portal']??null)?$b['overrides']['portal']:[];$colors=is_array($b['colors']??null)?$b['colors']:[];
        $col=fn($c)=>preg_match('/^#[0-9a-f]{3,8}$/i',(string)$c)?strtolower((string)$c):'';
        $items[]=['id'=>$id,'name'=>mb_substr(trim((string)($b['name']??$id)),0,80),'short_name'=>mb_substr(trim((string)($b['short_name']??'')),0,40),'claim'=>mb_substr(trim((string)($b['claim']??'')),0,160),
            'enabled'=>!array_key_exists('enabled',$b)||!empty($b['enabled']),'builtin'=>$id==='site',
            'primary_domain'=>$primary,'domains'=>$domains,
            'logo'=>rrw_brand_asset_clean((string)($b['logo']??'')),'logo_dark'=>rrw_brand_asset_clean((string)($b['logo_dark']??'')),'logo_light'=>rrw_brand_asset_clean((string)($b['logo_light']??'')),'favicon'=>rrw_brand_asset_clean((string)($b['favicon']??'')),'touch_icon'=>rrw_brand_asset_clean((string)($b['touch_icon']??'')),'og_image'=>rrw_brand_asset_clean((string)($b['og_image']??'')),'social_image'=>rrw_brand_asset_clean((string)($b['social_image']??'')),
            'title'=>mb_substr(trim((string)($b['title']??'')),0,120),'title_suffix'=>mb_substr(trim((string)($b['title_suffix']??'')),0,80),'description'=>mb_substr(trim((string)($b['description']??'')),0,400),'manifest_name'=>mb_substr(trim((string)($b['manifest_name']??'')),0,60),'manifest_short_name'=>mb_substr(trim((string)($b['manifest_short_name']??'')),0,30),
            'colors'=>['theme'=>$col($colors['theme']??''),'accent'=>$col($colors['accent']??'')],
            'app_prefix'=>preg_replace('/[^A-Za-z0-9-]/','',(string)($b['app_prefix']??'')),
            // Radioverzeichnis (Suchfeld im Header, Verzeichnis-Seite); SenderWelt hat es standardmäßig
            'directory'=>false,   // Radioverzeichnis ist nicht Teil von ElvadoPress
            'canonical_mode'=>in_array(($b['canonical_mode']??'own'),['own','main','custom'],true)?(string)($b['canonical_mode']??'own'):'own','canonical_base'=>preg_match('#^https://[a-z0-9.-]+$#i',(string)($b['canonical_base']??''))?strtolower((string)$b['canonical_base']):'',
            'legal'=>['imprint_mode'=>($legal['imprint_mode']??'shared')==='custom'?'custom':'shared','imprint_url'=>mb_substr(trim((string)($legal['imprint_url']??'')),0,1200),'imprint_content'=>$safe((string)($legal['imprint_content']??'')),'privacy_mode'=>($legal['privacy_mode']??'shared')==='custom'?'custom':'shared','privacy_url'=>mb_substr(trim((string)($legal['privacy_url']??'')),0,1200),'privacy_content'=>$safe((string)($legal['privacy_content']??''))],
            'overrides'=>['portal'=>['site_name'=>mb_substr(trim((string)($ov['site_name']??'')),0,80),'hero_eyebrow'=>mb_substr(trim((string)($ov['hero_eyebrow']??'')),0,80),'hero_title'=>mb_substr(trim((string)($ov['hero_title']??'')),0,220),'hero_text'=>mb_substr(trim((string)($ov['hero_text']??'')),0,1200),'news_title'=>mb_substr(trim((string)($ov['news_title']??'')),0,140),'news_intro'=>mb_substr(trim((string)($ov['news_intro']??'')),0,600),'footer_text'=>mb_substr(trim((string)($ov['footer_text']??'')),0,220),'legal_notice'=>mb_substr(trim((string)($ov['legal_notice']??'')),0,2400)]],
        ];
    }
    // Eingebaute Marken dürfen nicht fehlen (sonst Standard-Eintrag ergänzen).
    foreach($defaults['items'] as $d)if(!isset($seen[$d['id']]))$items[]=$d;
    $fallbackDefault=rrw_brand_default_id();
    $default=rrw_brand_id_clean((string)($value['default']??$fallbackDefault));
    if(!in_array($default,array_column($items,'id'),true))$default=$fallbackDefault;
    return ['default'=>$default,'items'=>$items];
}
function rrw_brands_registry(array $site): array { return rrw_brands_clean($site['brands']??rrw_brand_defaults()); }
function rrw_brand_host_normalize(string $host): string { $host=strtolower(trim($host));$host=explode(':',$host)[0];return $host; }
// Welche Brand gehört zu diesem Hostname? Vergleich mit und ohne "www."; unbekannte Hosts
// (z.B. Vorschau-Server, IP, localhost) landen bei der Standardmarke.
function rrw_brand_id_for_host(array $site,string $host): string {
    $reg=rrw_brands_registry($site);$h=rrw_brand_host_normalize($host);$hNoWww=preg_replace('/^www\./','',$h);
    foreach($reg['items'] as $b){
        if(empty($b['enabled']))continue;
        $all=array_merge([$b['primary_domain']],(array)$b['domains']);
        foreach($all as $d){if($d==='')continue;if($d===$h||$d===$hNoWww||preg_replace('/^www\./','',$d)===$hNoWww)return $b['id'];}
    }
    return $reg['default'];
}
// Die wirksame Brand: Werte der Marke, bei leeren Feldern Rückgriff auf die bisherigen globalen
// Einstellungen (branding/seo/portal) - so bleibt die Website exakt wie vorher.
function rrw_brand_resolve(array $site,string $host,?string $forced=null): array {
    $reg=rrw_brands_registry($site);$id=$forced!==null?rrw_brand_id_clean($forced):'';
    $by=[];foreach($reg['items'] as $b)$by[$b['id']]=$b;
    if($id===''||!isset($by[$id])||empty($by[$id]['enabled']))$id=rrw_brand_id_for_host($site,$host);
    $b=$by[$id]??$by[$reg['default']]??rrw_brand_defaults()['items'][0];
    $isDefault=$id===$reg['default'];
    $branding=(array)($site['branding']??[]);$seo=function_exists('rrw_seo_defaults')?rrw_seo_defaults($site):(array)($site['seo']??[]);$portal=(array)($site['portal']??[]);
    $g=fn(string $k,string $fallback='')=>trim((string)($b[$k]??''))!==''?(string)$b[$k]:$fallback;
    $logo=$g('logo',(string)($branding['portal_logo']??'/logo-lockup.png'));
    $favicon=$g('favicon',(string)($branding['favicon']??'/icon-192.png'));
    $touch=$g('touch_icon',$isDefault?'/apple-touch-icon.png':$favicon);
    $og=$g('og_image',(string)($seo['og_image']??$branding['portal_icon']??'/icon-512.png'));
    $title=$g('title',(string)($seo['site_title']??$portal['site_name']??$b['name']));
    $suffix=$g('title_suffix',$isDefault?$b['name']:$title);
    $desc=$g('description',(string)($seo['description']??$portal['news_intro']??''));
    $primary=$b['primary_domain']!==''?$b['primary_domain']:rrw_brand_host_normalize($host);
    $hostNorm=rrw_brand_host_normalize($host);
    $origin='https://'.($hostNorm!==''&&$hostNorm!=='localhost'&&!preg_match('/^(127\.|\d+\.\d+\.\d+\.\d+$)/',$hostNorm)?$hostNorm:$primary);
    $mainBase=rtrim((string)($seo['canonical_base']??'https://'.($primary!==''?$primary:($hostNorm!==''?$hostNorm:'localhost'))),'/');
    // Hauptmarke: Links (og:url, Share, RSS) bleiben wie bisher auf der Canonical-Basis
    // (www.ricorewi-radio.de), unabhängig davon, ob mit oder ohne www aufgerufen wurde.
    if($isDefault&&preg_match('#^https://[a-z0-9.-]+$#i',$mainBase)&&preg_replace('/^www\./','',(string)parse_url($mainBase,PHP_URL_HOST))===preg_replace('/^www\./','',$hostNorm))$origin=$mainBase;
    $canonicalBase=match($b['canonical_mode']){'main'=>$mainBase,'custom'=>$b['canonical_base']!==''?$b['canonical_base']:'https://'.$primary,default=>$isDefault?$mainBase:'https://'.$primary};
    $legal=(array)($b['legal']??[]);$sharedLegal=(array)($site['legal']??[]);
    return [
        'id'=>$id,'brand'=>$id,'name'=>$b['name'],'short_name'=>$b['short_name']?:$b['name'],'claim'=>$b['claim'],'is_default'=>$isDefault,'enabled'=>!empty($b['enabled']),
        'hostname'=>$hostNorm,'primary_domain'=>$primary,'domains'=>array_values(array_merge([$b['primary_domain']],(array)$b['domains'])),'origin'=>$origin,
        'logo'=>$logo,'logo_dark'=>$g('logo_dark',''),'logo_light'=>$g('logo_light',''),'favicon'=>$favicon,'touch_icon'=>$touch,'og_image'=>$og,'social_image'=>$g('social_image',$og),
        'title'=>$title,'title_suffix'=>$suffix,'description'=>$desc,
        'manifest_name'=>$g('manifest_name',$b['name']),'manifest_short_name'=>$g('manifest_short_name',$b['short_name']?:$b['name']),
        'colors'=>['theme'=>(string)($b['colors']['theme']??'')?:'#070a1c','accent'=>(string)($b['colors']['accent']??'')],
        'canonical_mode'=>$b['canonical_mode'],'canonical_base'=>$canonicalBase,'canonical_url'=>$canonicalBase.'/',
        'app_prefix'=>$g('app_prefix',preg_replace('/[^A-Za-z0-9-]/','',str_replace(' ','-',$b['name']))?:'App'),
        'legal'=>[
            'imprint_mode'=>($legal['imprint_mode']??'shared')==='custom'?'custom':'shared','imprint_url'=>(string)($legal['imprint_url']??''),'imprint_content'=>(string)($legal['imprint_content']??''),
            'privacy_mode'=>($legal['privacy_mode']??'shared')==='custom'?'custom':'shared','privacy_url'=>(string)($legal['privacy_url']??''),'privacy_content'=>(string)($legal['privacy_content']??''),
        ],
        'directory'=>false,
        'overrides'=>['portal'=>rrw_brand_portal_overrides($b,$isDefault)],
        'partners'=>rrw_brand_partners($reg,$id,$branding,$mainBase),
    ];
}
// Schwestermarken (alle anderen aktiven Marken) fuer den Partner-Hinweis im Willkommensbereich:
// RicoReWi zeigt das SenderWelt-Logo mit Link und umgekehrt. Link: eigene Domain, solange die
// Domain noch nicht freigeschaltet ist (Canonical 'main') die Markenansicht auf der Hauptdomain.
function rrw_brand_partners(array $reg,string $currentId,array $branding,string $mainBase): array {
    $out=[];
    foreach($reg['items'] as $o){
        if(($o['id']??'')===$currentId||empty($o['enabled']))continue;
        $isDef=($o['id']??'')===$reg['default'];
        $logo=trim((string)($o['logo']??''))!==''?(string)$o['logo']:(string)($branding['portal_logo']??'/logo-lockup.png');
        $primary=trim((string)($o['primary_domain']??''));
        if($isDef)$url=$mainBase.'/';
        elseif(($o['canonical_mode']??'own')==='own'&&$primary!=='')$url='https://'.$primary.'/';
        elseif(($o['canonical_mode']??'')==='custom'&&trim((string)($o['canonical_base']??''))!=='')$url=rtrim((string)$o['canonical_base'],'/').'/';
        else $url=$mainBase.'/?rrw_brand='.rawurlencode((string)$o['id']);
        $out[]=['id'=>(string)$o['id'],'name'=>(string)($o['name']??$o['id']),'short_name'=>(string)(($o['short_name']??'')?:($o['name']??$o['id'])),'claim'=>(string)($o['claim']??''),'logo'=>$logo,'url'=>$url];
    }
    return $out;
}
// Texte der Startseite/News/Footer je Marke: was im CMS (Domains & Branding → Texte überschreiben)
// leer bleibt, bekommt bei Zweitmarken einen markeneigenen Standard statt der RicoReWi-Texte;
// die Hauptmarke nutzt weiterhin die gemeinsamen Portal-Texte.
function rrw_brand_portal_overrides(array $b,bool $isDefault): array {
    $ov=array_filter((array)($b['overrides']['portal']??[]),fn($v)=>trim((string)$v)!=='');
    if($isDefault)return $ov;
    $name=trim((string)($b['name']??''))?:'Radio';$claim=trim((string)($b['claim']??''));
    $defaults=[
        'site_name'=>$name,
        'hero_title'=>'Willkommen bei '.$name,
        'hero_text'=>($claim!==''?$claim.' ':'').'Schön, dass du da bist! Hier findest du alle Streams von '.$name.' an einem Ort. Hör rein, stimm mit ab, wünsch dir deinen Song oder schick uns einen Gruß.',
        'news_title'=>'Aktuelles von '.$name,
        'footer_text'=>'© '.date('Y').' '.$name.' • Alle Rechte vorbehalten',
    ];
    if(!empty($b['directory'])){
        $defaults['hero_eyebrow']='Dein Radioverzeichnis';
        $defaults['hero_text']=($claim!==''?$claim.' ':'').'Finde deinen Lieblingssender in unserem Radioverzeichnis – mit Webradios von laut.fm und aus aller Welt. Im Fokus stehen unsere eigenen Sender von RicoReWi × AnMaCha. Suchen, reinhören, Favoriten speichern oder dich mit „Überrasch mich“ überraschen lassen.';
        $defaults['legal_notice']=rrw_brand_directory_notice($name);
    }
    return array_merge($defaults,$ov);
}
// Standard-Rechtshinweis für Marken mit Radioverzeichnis: laut.fm-Hinweis zu den eigenen Sendern plus Hinweis zum Verzeichnis.
// Auszeichnung: [Text](https://…) = Link (neues Fenster), Zeilenumbruch = <br>.
function rrw_brand_directory_notice(string $name): string {
    // Kurz gehalten: Lizenzhinweis zu den eigenen Sendern; Details zu Fremdsendern stehen im Impressum und im Verzeichnis selbst.
    return "Lizenzen (GEMA/GVL) für unsere eigenen Sender werden von [laut.fm](https://laut.fm) übernommen. Für Sender im Verzeichnis sind die jeweiligen Betreiber verantwortlich.";
}
function rrw_brand_notice_html(string $text): string {
    $e=htmlspecialchars($text,ENT_QUOTES,'UTF-8');
    $e=preg_replace_callback('~\[([^\]]{1,80})\]\((https://[^\s)"\'<>]+)\)~u',fn($m)=>'<a href="'.$m[2].'" target="_blank" rel="noopener nofollow" style="color:#7d86bd;">'.$m[1].'</a>',$e);
    return str_replace("\n",'<br>',$e);
}
// Absolute URL für Social-Vorschauen (Crawler verlangen absolute Bild-/Seiten-URLs).
function rrw_brand_abs(string $origin,string $path): string { if($path==='')return'';if(preg_match('~^https?://~i',$path))return $path;return rtrim($origin,'/').'/'.ltrim($path,'/'); }
function rrw_brand_forced_from_request(): ?string {
    $q=$_GET['rrw_brand']??$_GET['cms_brand_preview']??null;return is_string($q)&&$q!==''?$q:null;
}
// Baut die brandabhängigen <head>-Werte und Logo-Verweise in das statische index.html ein.
function rrw_brand_render_index(string $html,array $brand): string {
    $e=fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
    $ogAbs=rrw_brand_abs($brand['origin'],$brand['og_image']);$fav=$brand['favicon'];$ver='?bv='.substr(md5(json_encode([$brand['id'],$brand['logo'],$fav,$brand['touch_icon']])),0,8);
    $rep=function(string $pattern,string $replacement) use (&$html){ $n=0;$html=preg_replace($pattern,$replacement,$html,1,$n);return $n; };
    $rep('#<title id="page-title">.*?</title>#s','<title id="page-title">'.$e($brand['title']).'</title>');
    $rep('#<meta id="meta-description" name="description" content="[^"]*">#','<meta id="meta-description" name="description" content="'.$e($brand['description']).'">');
    $rep('#<meta property="og:site_name" content="[^"]*">#','<meta property="og:site_name" content="'.$e($brand['title']).'">');
    $rep('#<meta id="og-title" property="og:title" content="[^"]*">#','<meta id="og-title" property="og:title" content="'.$e($brand['title']).'">');
    $rep('#<meta id="og-description" property="og:description" content="[^"]*">#','<meta id="og-description" property="og:description" content="'.$e($brand['description']).'">');
    $rep('#<meta id="og-image" property="og:image" content="[^"]*">#','<meta id="og-image" property="og:image" content="'.$e($ogAbs).'">');
    $rep('#<meta id="twitter-title" name="twitter:title" content="[^"]*">#','<meta id="twitter-title" name="twitter:title" content="'.$e($brand['title']).'">');
    $rep('#<meta id="twitter-description" name="twitter:description" content="[^"]*">#','<meta id="twitter-description" name="twitter:description" content="'.$e($brand['description']).'">');
    $rep('#<meta id="twitter-image" name="twitter:image" content="[^"]*">#','<meta id="twitter-image" name="twitter:image" content="'.$e($ogAbs).'">');
    if(!$rep('#<link rel="canonical" href="[^"]*">#','<link rel="canonical" href="'.$e($brand['canonical_url']).'">'))$rep('#(<meta id="meta-description"[^>]+>)#','$1'."\n    ".'<link rel="canonical" href="'.$e($brand['canonical_url']).'">');
    if(!$rep('#<meta id="og-url" property="og:url" content="[^"]*">#','<meta id="og-url" property="og:url" content="'.$e($brand['origin'].'/').'">'))$rep('#(<meta id="og-title"[^>]+>)#','<meta id="og-url" property="og:url" content="'.$e($brand['origin'].'/').'">'."\n    ".'$1');
    $rep('#<link rel="manifest" href="[^"]*">#','<link rel="manifest" href="/manifest.php">');
    $rep('#<meta name="theme-color" content="[^"]*">#','<meta name="theme-color" content="'.$e($brand['colors']['theme']).'">');
    $rep('#<meta name="apple-mobile-web-app-title" content="[^"]*">#','<meta name="apple-mobile-web-app-title" content="'.$e($brand['manifest_short_name']).'">');
    $rep('#<link rel="icon" href="[^"]*"[^>]*>#','<link rel="icon" href="'.$e($fav.$ver).'">');
    $rep('#<link rel="apple-touch-icon" href="[^"]*">#','<link rel="apple-touch-icon" href="'.$e($brand['touch_icon'].$ver).'">');
    $rep('#<link rel="alternate" type="application/rss\+xml" title="[^"]*"#','<link rel="alternate" type="application/rss+xml" title="'.$e($brand['name'].' – News & Magazin').'"');
    // Hauptmarke: Logo-Pfad und Alt-Texte bleiben exakt wie im statischen index.html (Regression null);
    // andere Marken bekommen ihr Logo und ihren Namen.
    if(!$brand['is_default']){
        $alt=$e($brand['name'].' – Startseite');
        $html=preg_replace('#(<a class="brand"[^>]*aria-label=")[^"]*("[^>]*>\s*<img src=")[^"]*(" alt=")[^"]*(")#','$1'.$alt.'$2'.$e($brand['logo']).'$3'.$e($brand['name']).'$4',$html,1);
        $html=preg_replace('#(<div class="foot-brand"><img src=")[^"]*(" alt=")[^"]*(")#','$1'.$e($brand['logo']).'$2'.$e($brand['name']).'$3',$html,1);
        // Startseiten-Texte und Footer direkt serverseitig (kein Aufblitzen der RicoReWi-Texte, Crawler sehen die Marke)
        $ov=(array)($brand['overrides']['portal']??[]);
        if(!empty($ov['hero_eyebrow']))$rep('#(<span id="cms-hero-eyebrow" class="eyebrow">).*?(</span>)#s','${1}'.$e($ov['hero_eyebrow']).'${2}');
        if(!empty($ov['hero_title']))$rep('#(<h1 id="cms-hero-title">).*?(</h1>)#s','${1}'.$e($ov['hero_title']).'${2}');
        if(!empty($ov['hero_text']))$rep('#(<p id="cms-hero-text">).*?(</p>)#s','${1}'.$e($ov['hero_text']).'${2}');
        if(!empty($ov['footer_text']))$rep('#(<p id="cms-footer-text">).*?(</p>)#s','${1}'.$e($ov['footer_text']).'${2}');
        // Rechtshinweis im Footer (laut.fm-Hinweis; bei Verzeichnis-Marken um den Verzeichnis-Hinweis erweitert)
        if(!empty($ov['legal_notice'])){
            $notice=rrw_brand_notice_html((string)$ov['legal_notice']);
            $rep('#(<div class="legal-notice">\s*).*?(\s*<div style="margin-top:15px;">)#s','${1}'.str_replace(['\\','$'],['\\\\','\\$'],$notice).'${2}');
        }
    }
    // Partner-Hinweis im Willkommensbereich (Schwestermarke mit Logo und Link) fuer alle Marken
    $partner=$brand['partners'][0]??null;
    if($partner){
        $rep('#<a id="cms-hero-partner" class="hero-partner"[^>]*>.*?</a>#s','<a id="cms-hero-partner" class="hero-partner" href="'.$e($partner['url']).'" title="'.$e('Zu '.$partner['name']).'"><span class="hero-partner-label">Auch von uns</span><img src="'.$e($partner['logo']).'" alt="'.$e($partner['name']).'"></a>');
    }
    $public=rrw_brand_public_payload($brand);
    $inject='<script>window.__RRW_BRAND__ = '.json_encode($public,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG).';</script>';
    if(str_contains($html,'<!-- RRW-CMS-SNAPSHOT-END -->'))$html=str_replace('<!-- RRW-CMS-SNAPSHOT-END -->','<!-- RRW-CMS-SNAPSHOT-END -->'."\n".$inject,$html);
    else $html=str_replace('</head>',$inject."\n</head>",$html);
    return $html;
}
// Öffentliche Sicht auf eine Brand (keine Verwaltungsdaten - die Registry enthält ohnehin keine
// Geheimnisse, aber diese Form ist stabil für die API).
function rrw_brand_public_payload(array $brand): array {
    return ['brand'=>$brand['id'],'name'=>$brand['name'],'short_name'=>$brand['short_name'],'claim'=>$brand['claim'],'hostname'=>$brand['hostname'],'primary_domain'=>$brand['primary_domain'],'domains'=>$brand['domains'],'origin'=>$brand['origin'],'is_default'=>$brand['is_default'],
        'logo'=>$brand['logo'],'logo_dark'=>$brand['logo_dark'],'logo_light'=>$brand['logo_light'],'favicon'=>$brand['favicon'],'touch_icon'=>$brand['touch_icon'],'og_image'=>$brand['og_image'],'social_image'=>$brand['social_image'],
        'title'=>$brand['title'],'title_suffix'=>$brand['title_suffix'],'description'=>$brand['description'],'manifest_name'=>$brand['manifest_name'],'manifest_short_name'=>$brand['manifest_short_name'],'colors'=>$brand['colors'],
        'canonical_mode'=>$brand['canonical_mode'],'canonical_url'=>$brand['canonical_url'],'app_prefix'=>$brand['app_prefix'],'directory'=>!empty($brand['directory']),'legal'=>$brand['legal'],'overrides'=>$brand['overrides'],'partners'=>$brand['partners']??[]];
}
function rrw_brand_manifest(array $brand): array {
    $icons=[];
    if($brand['is_default']){
        $icons=[['src'=>'icon-192.png','sizes'=>'192x192','type'=>'image/png','purpose'=>'any'],['src'=>'icon-512.png','sizes'=>'512x512','type'=>'image/png','purpose'=>'any'],['src'=>'icon-512-maskable.png','sizes'=>'512x512','type'=>'image/png','purpose'=>'maskable']];
    } else {
        $src=$brand['touch_icon']?:$brand['favicon'];$type=str_ends_with(strtolower($src),'.svg')?'image/svg+xml':'image/png';
        $icons=[['src'=>$src,'sizes'=>$type==='image/svg+xml'?'any':'512x512','type'=>$type,'purpose'=>'any']];
        if($brand['favicon']!==''&&$brand['favicon']!==$src)$icons[]=['src'=>$brand['favicon'],'sizes'=>str_ends_with(strtolower($brand['favicon']),'.svg')?'any':'192x192','type'=>str_ends_with(strtolower($brand['favicon']),'.svg')?'image/svg+xml':'image/png','purpose'=>'any'];
    }
    $shortcuts=[['name'=>'Sendeplan','short_name'=>'Sendeplan','url'=>'./#sendeplan'],['name'=>'Podcast','short_name'=>'Podcast','url'=>'./#podcast']];
    return ['id'=>'/','name'=>$brand['manifest_name'],'short_name'=>$brand['manifest_short_name'],'description'=>$brand['description'],'start_url'=>'./','scope'=>'./','display'=>'standalone','display_override'=>['standalone','minimal-ui'],'orientation'=>'any','background_color'=>$brand['colors']['theme'],'theme_color'=>$brand['colors']['theme'],'lang'=>'de','dir'=>'ltr','categories'=>['music','entertainment','news'],'prefer_related_applications'=>false,'launch_handler'=>['client_mode'=>['navigate-existing','auto']],'icons'=>$icons,'shortcuts'=>$shortcuts];
}

// Statische, vom CMS erzeugte Seiten (news.html, sender.html, eigene Seiten) markenabhängig
// ausliefern (siehe brandpage.php + .htaccess). Die Hauptmarke bleibt bis auf ergänzte
// Social-Tags unverändert; andere Marken bekommen Titel-Zusatz, Logo, Favicon, Canonical, Footer.
function rrw_brand_render_static(string $html,array $brand,string $page): string {
    $e=fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
    $n=0;
    if(!$brand['is_default']){
        $html=preg_replace('#<title>(.*?) – RicoReWi Radio</title>#s','<title>$1 – '.$e($brand['title_suffix']).'</title>',$html,1,$n);
        $html=preg_replace('#<link rel="icon" href="[^"]*">#','<link rel="icon" href="'.$e($brand['favicon']).'">',$html,1);
        $html=preg_replace('#(<header><a class="brand" href="/"><img src=")[^"]*(" alt=")[^"]*(")#','$1'.$e($brand['logo']).'$2'.$e($brand['name']).'$3',$html,1);
        $html=preg_replace('#<footer><a href="/">RicoReWi Radioportal</a></footer>#','<footer><a href="/">'.$e($brand['title']).'</a></footer>',$html,1);
        $html=str_replace('>Interaktive Seite im Radioportal öffnen<','>Interaktive Seite auf '.$e($brand['name']).' öffnen<',$html);
    }
    $canon=rtrim($brand['canonical_base'],'/').'/'.$e($page).'.html';
    if(preg_match('#<link rel="canonical" href="[^"]*">#',$html))$html=preg_replace('#<link rel="canonical" href="[^"]*">#','<link rel="canonical" href="'.$e($canon).'">',$html,1);
    if(!str_contains($html,'property="og:title"')){
        $title='';if(preg_match('#<title>(.*?)</title>#s',$html,$m))$title=html_entity_decode($m[1],ENT_QUOTES,'UTF-8');
        $desc='';if(preg_match('#<meta name="description" content="([^"]*)">#',$html,$m))$desc=html_entity_decode($m[1],ENT_QUOTES,'UTF-8');
        if($desc==='')$desc=$brand['description'];
        $og='<meta property="og:type" content="article"><meta property="og:site_name" content="'.$e($brand['title']).'"><meta property="og:title" content="'.$e($title).'"><meta property="og:description" content="'.$e($desc).'"><meta property="og:image" content="'.$e(rrw_brand_abs($brand['origin'],$brand['og_image'])).'"><meta property="og:url" content="'.$e(rtrim($brand['origin'],'/').'/'.$page.'.html').'"><meta name="twitter:card" content="summary_large_image">';
        $html=preg_replace('#</head>#',$og.'</head>',$html,1);
    }
    return $html;
}

// ───────── Marken anlegen, Domains absichern ─────────
/** Jede Domain (mit/ohne „www.“) gehört genau einer Marke. @param list<array<string,mixed>> $items @return list<array{domain:string,brands:list<string>}> */
function rrw_brand_domain_conflicts(array $items): array {
    $seen=[];
    foreach($items as $b){
        foreach(array_filter(array_merge([(string)($b['primary_domain']??'')],(array)($b['domains']??[]))) as $d){$seen[preg_replace('/^www\./','',(string)$d)][(string)($b['id']??'')]=true;}
    }
    $out=[];foreach($seen as $d=>$ids)if(count($ids)>1)$out[]=['domain'=>(string)$d,'brands'=>array_map('strval',array_keys($ids))];
    return $out;
}
/** Teile einer Marke, die beim Anlegen „als Kopie von …“ übernommen werden können. */
const RRW_BRAND_COPY_GROUPS=[
    'design'=>['logo','logo_dark','logo_light','favicon','touch_icon','og_image','social_image','colors'],
    'seo'=>['title','title_suffix','description','manifest_name','manifest_short_name','canonical_mode','canonical_base'],
    'texts'=>['claim','overrides'],
    'legal'=>['legal'],
];
/**
 * Neue Marke anlegen, optional als Kopie einer vorhandenen (nur die gewählten Teile; Kennung, Name und Domains sind immer neu).
 * @param array{items:list<array<string,mixed>>} $reg bereinigte Markenliste @param array<string,mixed> $in id, name, short_name, primary_domain, domains[], enabled, copy_from, copy[]
 * @return list<array<string,mixed>> neue Markenliste (noch nicht bereinigt/gespeichert)
 * @throws InvalidArgumentException mit verständlicher Meldung
 */
function rrw_brand_create(array $reg,array $in): array {
    $items=array_values((array)($reg['items']??[]));
    $name=mb_substr(trim((string)($in['name']??'')),0,80);
    $id=rrw_brand_id_clean((string)($in['id']??'')!==''?(string)$in['id']:$name);
    if($id==='')throw new InvalidArgumentException('Bitte einen Namen oder eine Kennung angeben.');
    foreach($items as $x)if(($x['id']??'')===$id)throw new InvalidArgumentException('Die Kennung „'.$id.'“ gibt es schon.');
    if(count($items)>=20)throw new InvalidArgumentException('Es sind höchstens 20 Marken möglich.');
    if($name==='')$name=$id;
    $primaryRaw=trim((string)($in['primary_domain']??''));$primary=rrw_brand_domain_clean($primaryRaw);
    if($primaryRaw!==''&&$primary==='')throw new InvalidArgumentException('Die Hauptdomain „'.$primaryRaw.'“ ist ungültig (Beispiel: meine-marke.de).');
    $extra=[];
    foreach((array)($in['domains']??[]) as $d){$d=trim((string)$d);if($d==='')continue;$c=rrw_brand_domain_clean($d);if($c==='')throw new InvalidArgumentException('Die Domain „'.$d.'“ ist ungültig.');if($c!==$primary)$extra[]=$c;}
    $b=rrw_brand_blank(['id'=>$id,'name'=>$name,'short_name'=>mb_substr(trim((string)($in['short_name']??''))!==''?trim((string)$in['short_name']):$name,0,40),'primary_domain'=>$primary,'domains'=>array_values(array_unique($extra)),'enabled'=>!empty($in['enabled']),'builtin'=>false]);
    $from=rrw_brand_id_clean((string)($in['copy_from']??''));
    if($from!==''){
        $src=null;foreach($items as $x)if(($x['id']??'')===$from)$src=$x;
        if($src===null)throw new InvalidArgumentException('Die Vorlage-Marke „'.$from.'“ gibt es nicht.');
        $groups=array_values(array_intersect(array_keys(RRW_BRAND_COPY_GROUPS),array_map('strval',(array)($in['copy']??['design','texts','legal']))));
        foreach($groups as $g)foreach(RRW_BRAND_COPY_GROUPS[$g] as $k)if(array_key_exists($k,$src))$b[$k]=$src[$k];
        if(in_array('texts',$groups,true)&&isset($b['overrides']['portal']['site_name'])&&$b['overrides']['portal']['site_name']!=='')$b['overrides']['portal']['site_name']=$name;
    }
    $items[]=$b;
    $cf=rrw_brand_domain_conflicts($items);
    if($cf)throw new InvalidArgumentException(rrw_brand_conflict_message($cf,$items));
    return $items;
}
/** @param list<array{domain:string,brands:list<string>}> $cf @param list<array<string,mixed>> $items */
function rrw_brand_conflict_message(array $cf,array $items): string {
    $nm=[];foreach($items as $x)$nm[(string)($x['id']??'')]=(string)($x['name']??$x['id']??'');
    return 'Jede Domain darf nur zu einer Marke gehören: '.implode('; ',array_map(fn($c)=>$c['domain'].' → '.implode(' und ',array_map(fn($i)=>'„'.($nm[$i]??$i).'“',$c['brands'])),$cf)).'.';
}
/**
 * DNS-Prüfung einer Domain: zeigt sie auf denselben Server wie diese Verwaltung? (nur Namensauflösung, keine Anfrage an die Domain)
 * @return array{ok:bool,domain:string,ips:list<string>,server_ips:list<string>,match:bool,message:string}
 */
function rrw_brand_domain_dns(string $domain,?string $adminHost=null): array {
    $d=rrw_brand_domain_clean($domain);
    if($d==='')return ['ok'=>false,'domain'=>'','ips'=>[],'server_ips'=>[],'match'=>false,'message'=>'Ungültige Domain.'];
    $ips=array_values(array_unique(array_map('strval',(array)(@gethostbynamel($d)?:[]))));
    $host=rrw_brand_host_normalize((string)($adminHost??($_SERVER['HTTP_HOST']??'')));
    $mine=[];if(($sa=(string)($_SERVER['SERVER_ADDR']??''))!=='')$mine[]=$sa;
    if($host!==''&&!preg_match('/^[0-9.:]+$/',$host))foreach((array)(@gethostbynamel($host)?:[]) as $ip)$mine[]=(string)$ip;
    $mine=array_values(array_unique($mine));$match=(bool)array_intersect($ips,$mine);
    if(!$ips)$msg='Für „'.$d.'“ wurde kein DNS-Eintrag gefunden. Lege beim Domain-Anbieter einen A-Eintrag auf'.($mine?' '.implode(', ',$mine):' die IP-Adresse dieses Servers').' an (und für „www.'.$d.'“ einen CNAME auf '.$d.').';
    elseif($match)$msg='Die Domain zeigt auf diesen Server. Fehlt noch ein HTTPS-Zertifikat, richtest du es beim Hosting/Webserver für diese Domain ein.';
    else $msg='Die Domain zeigt auf '.implode(', ',$ips).', dieser Server hat '.($mine?implode(', ',$mine):'eine andere Adresse').'. Passe den A-Eintrag an (Änderungen brauchen bis zu 24 Stunden).';
    return ['ok'=>true,'domain'=>$d,'ips'=>$ips,'server_ips'=>$mine,'match'=>$match,'message'=>$msg];
}

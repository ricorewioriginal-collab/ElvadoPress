<?php
/* Creator-Theme: Daten aus dem CMS-Menü „Creator“ (cms/lib/creator.php + themeconf.php) und Bausteine der Startseite und der Link-Seite. */
if(!defined('ABSPATH'))exit;

function elvado_cr_lib(): bool {
    if(function_exists('elvado_tc_load'))return true;
    $d=(defined('ELVADO_WP_NATIVE_THEMES')?dirname(ELVADO_WP_NATIVE_THEMES):dirname(__DIR__,3)).'/lib';
    if(!is_file($d.'/themeconf.php')||!is_file($d.'/creator.php'))return false;
    require_once $d.'/creator.php';require_once $d.'/themeconf.php';return true;
}
function elvado_cr_data_dir(): string { return dirname(ELVADO_WP_DATA); }
function elvado_cr_cfg(bool $reset=false): array {
    static $c=null;if($reset)$c=null;
    if($c===null){
        $c=elvado_cr_lib()?elvado_tc_load(elvado_cr_data_dir(),'creator'):[];
        foreach(['profile','mediakit','contact','display'] as $k)$c[$k]=(array)($c[$k]??[]);
        foreach(['stats','platforms','links','highlights','feed','videos','favorites','drops','collabs','packages','faq'] as $k)$c[$k]=(array)($c[$k]??[]);
        foreach(['name','handle','tagline','bio','niches','location','avatar','cover','cta_label','cta_url'] as $k)$c['profile'][$k]=(string)($c['profile'][$k]??'');
    }
    return $c;
}
function elvado_cr_name(): string { $n=elvado_cr_cfg()['profile']['name'];return $n!==''?$n:(string)get_bloginfo('name'); }
function elvado_cr_on(string $k): bool { $d=elvado_cr_cfg()['display'];return !array_key_exists($k,$d)||!empty($d[$k]); }
function elvado_cr_color(string $k,string $d): string { $v=(string)get_theme_mod('cr_'.$k,$d);return preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i',$v)?$v:$d; }
function elvado_cr_contrast(string $hex): string {
    $h=ltrim($hex,'#');if(strlen($h)===3)$h=$h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
    return (0.299*hexdec(substr($h,0,2))+0.587*hexdec(substr($h,2,2))+0.114*hexdec(substr($h,4,2)))/255>0.62?'#111111':'#ffffff';
}
function elvado_cr_today(): string { return (string)wp_date('Y-m-d'); }
function elvado_cr_paragraphs(string $t): string { $o='';foreach(preg_split('/\n{2,}/',trim($t)) as $p)if(trim($p)!=='')$o.='<p>'.nl2br(esc_html(trim($p))).'</p>';return $o; }
function elvado_cr_disclosure(): string { return trim((string)(elvado_cr_cfg()['contact']['disclosure']??'')); }
/** Ist die angefragte Adresse die Link-Seite (/links/ ohne angelegte CMS-Seite)? Der Router meldet den Pfad über den Filter elvado_wp_404_status. */
function elvado_cr_is_links_request(): bool { return !empty($GLOBALS['elvado_cr_links_req'])&&is_404(); }
add_filter('elvado_wp_404_status',function($status,$path){
    $GLOBALS['elvado_cr_links_req']=trim((string)$path,'/')==='links';
    return $GLOBALS['elvado_cr_links_req']?200:$status;
},10,2);
add_filter('document_title_parts',function($t){ if(elvado_cr_is_links_request()){ $t['title']=elvado_cr_name().' – Links';$t['site']=''; }return $t; });

/* Symbole (eigene Strichsymbole, keine Marken-Logos) */
function elvado_cr_icon(string $n): string {
    $p=['link'=>'<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3A4 4 0 0 0 11 18.7l1-1"/>','star'=>'<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
        'shop'=>'<path d="M5 8h14l-1 12H6z"/><path d="M9 8a3 3 0 0 1 6 0"/>','cart'=>'<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M3 4h2l2.4 11h11L20 8H6.2"/>',
        'video'=>'<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M10 9.5v5l4.5-2.5z"/>','music'=>'<path d="M9 18V6l10-2v12"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="16" r="2"/>',
        'mail'=>'<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M3 7l9 6 9-6"/>','download'=>'<path d="M12 4v11"/><path d="M7 11l5 5 5-5"/><path d="M5 20h14"/>',
        'calendar'=>'<rect x="4" y="5" width="16" height="15" rx="3"/><path d="M4 10h16M8 3v4M16 3v4"/>','heart'=>'<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.5A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/>',
        'home'=>'<path d="M4 11l8-7 8 7"/><path d="M6 10v10h12V10"/>'];
    return '<svg viewBox="0 0 24 24" aria-hidden="true">'.($p[$n]??$p['link']).'</svg>';
}
function elvado_cr_abbr(string $t): string { return ['instagram'=>'IG','tiktok'=>'TT','youtube'=>'YT','twitch'=>'TV','x'=>'X','threads'=>'@','pinterest'=>'P','snapchat'=>'SC','linkedin'=>'in','facebook'=>'f','podcast'=>'♪','newsletter'=>'✉','website'=>'www'][$t]??'•'; }
function elvado_cr_plat_name(string $t): string { return defined('ELVADO_CREATOR_PLATFORMS')?(ELVADO_CREATOR_PLATFORMS[$t]??'Link'):'Link'; }
function elvado_cr_rel(bool $sponsored): string { return $sponsored?'sponsored nofollow noopener':'noopener'; }
/** Player-Adresse (nur YouTube nocookie und Vimeo; sonst null). */
function elvado_cr_embed_src(string $url): ?string {
    if(preg_match('~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~',$url,$m))return 'https://www.youtube-nocookie.com/embed/'.$m[1];
    if(preg_match('~^https?://(?:www\.)?vimeo\.com/(?:video/)?(\d{5,12})~',$url,$m))return 'https://player.vimeo.com/video/'.$m[1];
    return null;
}
function elvado_cr_embed_html(string $src,string $title): string {
    $h=(string)parse_url($src,PHP_URL_HOST);
    return '<div class="cr-embed"><button type="button" data-embed="'.esc_url($src).'" data-title="'.esc_attr($title).'">▶ '.esc_html($title).'<small>Zum Laden klicken – dabei werden Daten an '.esc_html($h).' übertragen.</small></button></div>';
}

/* ───────── Links ───────── */
/** Links im Zeitfenster; Hervorgehobene zuerst (Reihenfolge sonst wie im CMS). */
function elvado_cr_links_visible(): array {
    $t=elvado_cr_today();$o=[];
    foreach(elvado_cr_cfg()['links'] as $l){ if(($l['from']!==''&&$l['from']>$t)||($l['until']!==''&&$l['until']<$t))continue;$o[]=$l; }
    usort($o,fn($a,$b)=>(int)$b['highlight']<=>(int)$a['highlight']);   // stabil genug: usort ist ab PHP 8 stabil
    return $o;
}
function elvado_cr_link_html(array $l): string {
    $hot=!empty($l['highlight']);
    return '<a class="cr-link'.($hot?' hot':'').'" href="'.esc_url($l['url']).'" rel="'.esc_attr(elvado_cr_rel(!empty($l['sponsored']))).'"><span class="ic">'.elvado_cr_icon((string)$l['icon']).'</span><span class="tx">'.esc_html($l['title']).($l['subtitle']!==''?'<small>'.esc_html($l['subtitle']).'</small>':'').'</span>'
        .($l['badge']!==''?'<span class="bd">'.esc_html($l['badge']).'</span>':'').(!empty($l['sponsored'])?'<span class="cr-ad" style="color:inherit">Anzeige</span>':'').'</a>';
}
function elvado_cr_links_html(bool $disclosure=false): string {
    $ls=elvado_cr_links_visible();if(!$ls)return '';$h='<div class="cr-links">';$ad=false;
    foreach($ls as $l){ $h.=elvado_cr_link_html($l);if(!empty($l['sponsored']))$ad=true; }
    $h.='</div>';if($ad&&elvado_cr_disclosure()!=='')$h.='<p class="cr-disclosure">'.esc_html(elvado_cr_disclosure()).'</p>';
    return $h;
}
function elvado_cr_platforms_html(): string {
    $p=elvado_cr_cfg()['platforms'];if(!$p)return '';$h='<div class="cr-plats">';
    foreach($p as $x){ $n=$x['handle']!==''?$x['handle']:elvado_cr_plat_name($x['platform']);
        $h.='<a class="cr-plat" href="'.esc_url($x['url']).'" rel="me noopener"><i>'.esc_html(elvado_cr_abbr($x['platform'])).'</i><span>'.esc_html($n).($x['followers']!==''?' <small>'.esc_html($x['followers']).'</small>':'').'</span></a>'; }
    return $h.'</div>';
}

/* ───────── Abschnitte (liefern HTML oder '' wenn leer) ───────── */
function elvado_cr_title(string $t,string $small=''): string { return '<h2 class="cr-title">'.esc_html($t).($small!==''?' <small>'.esc_html($small).'</small>':'').'</h2>'; }
function elvado_cr_section_hero(): string {
    $c=elvado_cr_cfg();$p=$c['profile'];
    $h='<section class="card cr-hero" id="top"><div class="cr-cover"'.($p['cover']!==''?' style="background-image:url(\''.esc_url($p['cover']).'\')"':'').'></div><div class="cr-hero-in">';
    $h.=$p['avatar']!==''?'<img class="cr-avatar" src="'.esc_url($p['avatar']).'" alt="'.esc_attr(elvado_cr_name()).'">':'<div class="cr-avatar" aria-hidden="true"></div>';
    $h.='<div><h1 class="cr-name">'.esc_html(elvado_cr_name()).'</h1>'.($p['handle']!==''?'<div class="cr-handle">'.esc_html($p['handle']).($p['location']!==''?' · '.esc_html($p['location']):'').'</div>':'');
    if($p['tagline']!=='')$h.='<p class="cr-tagline">'.esc_html($p['tagline']).'</p>';
    if($p['niches']!==''){ $h.='<div class="cr-chips">';foreach(array_slice(array_filter(array_map('trim',explode(',',$p['niches']))),0,8) as $n)$h.='<span class="cr-chip">'.esc_html($n).'</span>';$h.='</div>'; }
    if(trim($p['bio'])!=='')$h.='<div class="cr-bio">'.elvado_cr_paragraphs($p['bio']).'</div>';
    $url=$p['cta_url']!==''?$p['cta_url']:(elvado_cr_section_contact()!==''?'#kontakt':'');
    if($url!==''&&($p['cta_label']!==''||$p['cta_url']!==''))$h.='<p><a class="btn" href="'.esc_url($url).'">'.esc_html($p['cta_label']!==''?$p['cta_label']:'Kontakt').'</a></p>';
    $h.='</div></div>';
    if($c['stats']){ $h.='<div class="cr-stats">';foreach($c['stats'] as $s)$h.='<div class="cr-stat"><b>'.esc_html($s['value']).'</b><span>'.esc_html($s['label']).'</span></div>';$h.='</div>'; }
    return $h.elvado_cr_platforms_html().'</section>';
}
function elvado_cr_section_highlights(): string {
    if(!elvado_cr_on('show_highlights')||!($hl=elvado_cr_cfg()['highlights']))return '';
    $h='<section class="cr-sec" id="highlights"><div class="cr-hl">';
    foreach($hl as $x){ $tag=$x['url']!==''?'a':'span';$h.='<'.$tag.($x['url']!==''?' href="'.esc_url($x['url']).'" rel="noopener"':'').'><img loading="lazy" src="'.esc_url($x['image']).'" alt=""><span>'.esc_html($x['title']).'</span></'.$tag.'>'; }
    return $h.'</div></section>';
}
function elvado_cr_section_links(): string {
    if(!elvado_cr_on('show_links')||!($l=elvado_cr_links_html()))return '';
    return '<section class="cr-sec" id="links">'.elvado_cr_title('Meine Links','Alles an einem Ort').$l.'</section>';
}
function elvado_cr_section_feed(): string {
    if(!elvado_cr_on('show_feed')||!($f=elvado_cr_cfg()['feed']))return '';
    $h='<section class="cr-sec" id="feed">'.elvado_cr_title('Feed','Aktuelles von mir').'<div class="cr-feed">';
    foreach($f as $x){ $k=['video'=>'Video','reel'=>'Reel'][$x['kind']]??'';$tag=$x['url']!==''?'a':'span';
        $h.='<'.$tag.' class="cr-post"'.($x['url']!==''?' href="'.esc_url($x['url']).'" rel="noopener"':'').' title="'.esc_attr($x['caption']).'"><img loading="lazy" src="'.esc_url($x['image']).'" alt="'.esc_attr($x['caption']).'">'.($k!==''?'<em>'.esc_html($k).'</em>':'').'</'.$tag.'>'; }
    return $h.'</div></section>';
}
function elvado_cr_section_videos(): string {
    if(!elvado_cr_on('show_videos'))return '';$v='';
    foreach(elvado_cr_cfg()['videos'] as $x){ $src=elvado_cr_embed_src($x['url']);if($src)$v.='<div>'.elvado_cr_embed_html($src,$x['title']!==''?$x['title']:'Video abspielen').($x['title']!==''?'<h3 style="margin-top:10px;font-size:1.1rem">'.esc_html($x['title']).'</h3>':'').'</div>'; }
    return $v!==''?'<section class="cr-sec" id="videos">'.elvado_cr_title('Videos').'<div class="cr-videos">'.$v.'</div></section>':'';
}
function elvado_cr_section_favorites(): string {
    if(!elvado_cr_on('show_favorites')||!($f=elvado_cr_cfg()['favorites']))return '';
    $h='<section class="cr-sec" id="favoriten">'.elvado_cr_title('Meine Empfehlungen','Das nutze ich wirklich').'<div class="cr-favs">';$ad=false;
    foreach($f as $x){ $s=!empty($x['sponsored']);$ad=$ad||$s;
        $h.='<article class="card cr-fav">'.($x['image']!==''?'<img loading="lazy" src="'.esc_url($x['image']).'" alt="'.esc_attr($x['title']).'">':'').'<div class="in">'.($x['brand']!==''?'<div class="br">'.esc_html($x['brand']).'</div>':'').'<h3>'.esc_html($x['title']).'</h3>'
            .($x['discount']!==''?'<div class="cr-disc">'.esc_html($x['discount']).'</div>':'').($x['note']!==''?'<div class="note">'.esc_html($x['note']).'</div>':'')
            .($x['code']!==''?'<div class="cr-code"><code>'.esc_html($x['code']).'</code><button type="button" data-copy="'.esc_attr($x['code']).'">Kopieren</button></div>':'')
            .'<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">'.($x['url']!==''?'<a class="btn" href="'.esc_url($x['url']).'" rel="'.esc_attr(elvado_cr_rel($s)).'">Ansehen</a>':'').($s?'<span class="cr-ad">Anzeige</span>':'').'</div></div></article>'; }
    $h.='</div>';if($ad&&elvado_cr_disclosure()!=='')$h.='<p class="cr-disclosure">'.esc_html(elvado_cr_disclosure()).'</p>';
    return $h.'</section>';
}
/** Zeitpunkt eines Drops (Zeitzone der Website). */
function elvado_cr_drop_time(array $d): ?DateTimeImmutable {
    try{ return new DateTimeImmutable($d['date'].' '.($d['time']!==''?$d['time']:'00:00'),function_exists('wp_timezone')?wp_timezone():new DateTimeZone('UTC')); }catch(Throwable $e){ return null; }
}
function elvado_cr_section_drops(): string {
    if(!elvado_cr_on('show_drops'))return '';$now=new DateTimeImmutable('now',function_exists('wp_timezone')?wp_timezone():new DateTimeZone('UTC'));$up=[];
    foreach(elvado_cr_cfg()['drops'] as $d){ $t=elvado_cr_drop_time($d);if($t&&$t>$now)$up[]=[$t,$d]; }
    if(!$up)return '';usort($up,fn($a,$b)=>$a[0]<=>$b[0]);[$t,$n]=$up[0];
    $h='<section class="cr-sec" id="drops">'.elvado_cr_title('Als Nächstes','Drops & Termine').'<div class="card cr-drop"><div><h3 style="font-size:1.6rem">'.esc_html($n['title']!==''?$n['title']:'Demnächst').'</h3><div class="cr-handle">'.esc_html($t->format('d.m.Y').($n['time']!==''?' · '.$n['time'].' Uhr':'')).'</div>'.($n['description']!==''?'<p>'.esc_html($n['description']).'</p>':'').'</div>'
        .'<div class="cr-count" data-countdown="'.esc_attr($t->format('c')).'" aria-label="Countdown"><div><b data-u="d">0</b><span>Tage</span></div><div><b data-u="h">00</b><span>Std</span></div><div><b data-u="m">00</b><span>Min</span></div><div><b data-u="s">00</b><span>Sek</span></div></div>'
        .($n['url']!==''?'<a class="btn" href="'.esc_url($n['url']).'" rel="noopener">Dabei sein</a>':'<span></span>').'</div>';
    if(count($up)>1){ $h.='<ul class="cr-dlist">';foreach(array_slice($up,1,6) as [$tt,$d])$h.='<li><time datetime="'.esc_attr($tt->format('Y-m-d')).'">'.esc_html($tt->format('d.m.Y')).'</time><span>'.esc_html($d['title']).'</span></li>';$h.='</ul>'; }
    return $h.'</section>';
}
function elvado_cr_section_collabs(): string {
    if(!elvado_cr_on('show_collabs')||!($c=elvado_cr_cfg()['collabs']))return '';
    $h='<section class="cr-sec" id="collabs">'.elvado_cr_title('Zusammengearbeitet mit').'<div class="cr-collabs">';
    foreach($c as $x){ $in=($x['logo']!==''?'<img loading="lazy" src="'.esc_url($x['logo']).'" alt="">':'').esc_html($x['name']);$h.=$x['url']!==''?'<a href="'.esc_url($x['url']).'" rel="noopener">'.$in.'</a>':'<span>'.$in.'</span>'; }
    return $h.'</div></section>';
}
function elvado_cr_section_mediakit(): string {
    if(!elvado_cr_on('show_mediakit'))return '';$c=elvado_cr_cfg();$m=$c['mediakit'];$aud=array_filter(array_map('trim',preg_split('/\n/',(string)($m['audience']??''))));
    if(!$aud&&!$c['packages']&&trim((string)($m['intro']??''))===''&&empty($m['download_url']))return '';
    $h='<section class="cr-sec" id="mediakit">'.elvado_cr_title((string)($m['heading']??'')?:'Mediakit','Für Marken & Agenturen');
    if(!empty($m['intro']))$h.='<p style="max-width:720px;color:var(--muted)">'.nl2br(esc_html((string)$m['intro'])).'</p>';
    $h.='<div class="cr-mk">';
    if($aud){ $h.='<div class="card"><h3>Zielgruppe</h3><ul class="cr-aud">';foreach($aud as $a)$h.='<li>'.esc_html($a).'</li>';$h.='</ul></div>'; }
    foreach($c['packages'] as $p)$h.='<div class="card cr-pkg"><h3>'.esc_html($p['title']).'</h3>'.($p['price']!==''?'<div class="cr-price">'.esc_html($p['price']).'</div>':'').'<p style="color:var(--muted);margin:0">'.nl2br(esc_html($p['description'])).'</p></div>';
    $h.='</div>';if(!empty($m['download_url']))$h.='<p><a class="btn" href="'.esc_url((string)$m['download_url']).'" rel="noopener">Mediakit herunterladen</a></p>';
    return $h.'</section>';
}
function elvado_cr_section_faq(): string {
    if(!elvado_cr_on('show_faq')||!($f=elvado_cr_cfg()['faq']))return '';
    $h='<section class="cr-sec cr-faq" id="faq">'.elvado_cr_title('Häufige Fragen');
    foreach($f as $x)$h.='<details><summary>'.esc_html($x['q']).'</summary><p>'.nl2br(esc_html($x['a'])).'</p></details>';
    return $h.'</section>';
}
function elvado_cr_section_news(): string {
    if(!elvado_cr_on('show_news'))return '';
    $q=new WP_Query(['post_type'=>'post','posts_per_page'=>max(1,min(12,(int)get_theme_mod('cr_news_count',3))),'ignore_sticky_posts'=>true]);
    if(!$q->have_posts()){ wp_reset_postdata();return ''; }
    $h='<section class="cr-sec" id="news">'.elvado_cr_title('Blog','Neues').'<div class="cr-news">';
    while($q->have_posts()){ $q->the_post();
        $h.='<article class="post-card">'.(has_post_thumbnail()?'<a class="post-thumbnail" href="'.esc_url(get_permalink()).'" aria-hidden="true" tabindex="-1">'.get_the_post_thumbnail(null,'medium_large').'</a>':'').'<div class="entry-meta">'.esc_html(get_the_date()).'</div><h3 class="entry-title" style="font-size:1.3rem"><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></h3><div>'.wp_kses_post(get_the_excerpt()).'</div></article>';
    }
    wp_reset_postdata();return $h.'</div></section>';
}
function elvado_cr_section_newsletter(): string {
    if(!elvado_cr_on('show_newsletter')||!shortcode_exists('newsletter'))return '';
    $o=do_shortcode('[newsletter]');if(trim(wp_strip_all_tags($o))==='')return '';
    return '<section class="cr-sec" id="newsletter">'.elvado_cr_title('Newsletter','Nichts verpassen').'<div class="card">'.$o.'</div></section>';
}
function elvado_cr_section_extra(): string {
    if(!is_active_sidebar('front-extra'))return '';ob_start();dynamic_sidebar('front-extra');return '<section class="cr-sec" id="extra">'.ob_get_clean().'</section>';
}
function elvado_cr_section_contact(): string {
    if(!elvado_cr_on('show_contact'))return '';$c=elvado_cr_cfg()['contact'];
    if(!array_filter([$c['text']??'',$c['email']??'']))return '';
    $h='<section class="cr-sec" id="kontakt"><div class="card cr-contact">'.elvado_cr_title((string)($c['heading']??'')?:'Zusammenarbeit & Kontakt');
    if(!empty($c['text']))$h.='<p style="max-width:620px;margin:0 auto 14px;color:var(--muted)">'.nl2br(esc_html((string)$c['text'])).'</p>';
    if(!empty($c['email']))$h.='<p><a class="cr-mail" href="mailto:'.esc_attr($c['email']).'">'.esc_html($c['email']).'</a></p>';
    if(!empty($c['agency']))$h.='<p class="cr-handle">'.esc_html($c['agency']).'</p>';
    return $h.'</div></section>';
}
/** Reihenfolge der Abschnitte; Plugins ergänzen eigene (Filter elvado_cr_sections + elvado_cr_section_<id> für das HTML). */
function elvado_cr_section_ids(): array {
    $ids=apply_filters('elvado_cr_sections',['hero','highlights','links','drops','feed','videos','favorites','collabs','mediakit','faq','news','newsletter','extra','contact']);
    return array_values(array_filter((array)$ids,fn($i)=>is_string($i)&&preg_match('/^[a-z0-9_]{1,40}$/',$i)));
}
function elvado_cr_render_front(): void {
    foreach(elvado_cr_section_ids() as $id){
        do_action('elvado_cr_before_section',$id);
        $fn='elvado_cr_section_'.$id;$html=function_exists($fn)?$fn():'';
        echo apply_filters('elvado_cr_section_'.$id,$html);
        do_action('elvado_cr_after_section',$id);
    }
}
function elvado_cr_fallback_menu(): void {
    $home=esc_url(home_url('/'));$map=['links'=>'Links','feed'=>'Feed','videos'=>'Videos','favoriten'=>'Empfehlungen','mediakit'=>'Mediakit','kontakt'=>'Kontakt'];$fn=['links'=>'links','feed'=>'feed','videos'=>'videos','favoriten'=>'favorites','mediakit'=>'mediakit','kontakt'=>'contact'];$o='';
    foreach($map as $id=>$l){ $f='elvado_cr_section_'.$fn[$id];if($f()!=='')$o.='<li><a href="'.$home.'#'.$id.'">'.esc_html($l).'</a></li>'; }
    echo '<ul>'.$o.'</ul>';
}
function elvado_cr_mobile_nav(): void {
    if(!elvado_cr_on('mobile_nav')||elvado_cr_is_links_request()||is_page('links'))return;$home=esc_url(home_url('/'));
    $items=[['',' Start','home',true]];
    foreach([['links','Links','link','links'],['favoriten','Shop','shop','favorites'],['kontakt','Kontakt','mail','contact']] as [$id,$l,$ic,$fn]){ $f='elvado_cr_section_'.$fn;$items[]=['#'.$id,$l,$ic,$f()!==''&&($fn!=='links'||elvado_cr_links_visible())]; }
    $o='';foreach($items as [$h,$l,$ic,$ok])if($ok)$o.='<a href="'.$home.$h.'">'.elvado_cr_icon($ic).'<span>'.esc_html(trim($l)).'</span></a>';
    if(substr_count($o,'<a ')>1)echo '<nav class="cr-mnav" aria-label="Schnellzugriff">'.$o.'</nav>';
}
/** Strukturierte Daten: Person (mit sameAs) und – falls vorhanden – FAQ. Nur auf der Startseite. */
function elvado_cr_jsonld(): string {
    if(!is_front_page())return '';$c=elvado_cr_cfg();$p=$c['profile'];$home=home_url('/');
    $g=['@type'=>'Person','name'=>elvado_cr_name(),'url'=>$home,'jobTitle'=>'Content Creator'];
    if($p['tagline']!=='')$g['description']=$p['tagline'];
    if($p['avatar']!=='')$g['image']=(strpos($p['avatar'],'/')===0?rtrim($home,'/'):'').$p['avatar'];
    $same=array_values(array_map(fn($x)=>$x['url'],$c['platforms']));if($same)$g['sameAs']=$same;
    $graph=[$g];
    if(elvado_cr_on('show_faq')&&$c['faq'])$graph[]=['@type'=>'FAQPage','mainEntity'=>array_map(fn($x)=>['@type'=>'Question','name'=>$x['q'],'acceptedAnswer'=>['@type'=>'Answer','text'=>$x['a']]],$c['faq'])];
    return '<script type="application/ld+json">'.json_encode(['@context'=>'https://schema.org','@graph'=>$graph],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP).'</script>'."\n";
}

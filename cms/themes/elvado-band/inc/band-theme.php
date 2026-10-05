<?php
/* Band-Theme: Daten aus dem CMS-Menü „Band“ (cms/lib/band.php + themeconf.php) und Bausteine der Startseite. */
if(!defined('ABSPATH'))exit;

function elvado_bd_lib(): bool {
    if(function_exists('rrw_tc_load'))return true;
    $d=(defined('RRW_WP_NATIVE_THEMES')?dirname(RRW_WP_NATIVE_THEMES):dirname(__DIR__,3)).'/lib';
    if(!is_file($d.'/themeconf.php')||!is_file($d.'/band.php'))return false;
    require_once $d.'/band.php';require_once $d.'/themeconf.php';return true;
}
function elvado_bd_data_dir(): string { return dirname(RRW_WP_DATA); }
/** Bereinigte Band-Konfiguration (Abschnitt → Daten); ohne Bibliothek/Datei leere Vorgaben. */
function elvado_bd_cfg(bool $reset=false): array {
    static $c=null;if($reset)$c=null;
    if($c===null){
        $c=elvado_bd_lib()?rrw_tc_load(elvado_bd_data_dir(),'band'):[];
        foreach(['band','booking','display'] as $k)$c[$k]=(array)($c[$k]??[]);
        foreach(['shows','releases','videos','members','gallery','links'] as $k)$c[$k]=(array)($c[$k]??[]);
    }
    return $c;
}
function elvado_bd_name(): string { $n=(string)(elvado_bd_cfg()['band']['name']??'');return $n!==''?$n:(string)get_bloginfo('name'); }
function elvado_bd_on(string $k): bool { $d=elvado_bd_cfg()['display'];return !array_key_exists($k,$d)||!empty($d[$k]); }
function elvado_bd_color(string $k,string $d): string { $v=(string)get_theme_mod('bd_'.$k,$d);return preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i',$v)?$v:$d; }
/** Textfarbe auf der Akzentfarbe: weiß oder schwarz je nach Helligkeit. */
function elvado_bd_contrast(string $hex): string {
    $h=ltrim($hex,'#');if(strlen($h)===3)$h=$h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
    $l=(0.299*hexdec(substr($h,0,2))+0.587*hexdec(substr($h,2,2))+0.114*hexdec(substr($h,4,2)))/255;return $l>0.62?'#111111':'#ffffff';
}
function elvado_bd_link_label(array $l): string {
    if(trim((string)$l['label'])!=='')return (string)$l['label'];
    return defined('RRW_BAND_LINK_TYPES')?(RRW_BAND_LINK_TYPES[$l['type']]??'Link'):'Link';
}
function elvado_bd_today(): string { return (string)wp_date('Y-m-d'); }
function elvado_bd_date_parts(string $date): array {
    $mon=['JAN','FEB','MÄR','APR','MAI','JUN','JUL','AUG','SEP','OKT','NOV','DEZ'];$wd=['SO','MO','DI','MI','DO','FR','SA'];
    $t=strtotime($date.' 12:00:00 UTC');
    return ['day'=>gmdate('j',$t),'mon'=>$mon[(int)gmdate('n',$t)-1],'wd'=>$wd[(int)gmdate('w',$t)],'year'=>gmdate('Y',$t),'de'=>gmdate('d.m.Y',$t)];
}
function elvado_bd_shows(bool $past=false): array {
    $today=elvado_bd_today();$out=[];
    foreach(elvado_bd_cfg()['shows'] as $s)if(($s['date']<$today)===$past)$out[]=$s;
    usort($out,fn($a,$b)=>$past?strcmp($b['date'].$b['time'],$a['date'].$a['time']):strcmp($a['date'].$a['time'],$b['date'].$b['time']));
    return $out;
}
function elvado_bd_next_show(): ?array { foreach(elvado_bd_shows() as $s)if($s['status']!=='cancelled')return $s;return null; }
function elvado_bd_releases(): array {
    $r=elvado_bd_cfg()['releases'];usort($r,fn($a,$b)=>strcmp((string)$b['date'],(string)$a['date']));return $r;
}
/** Player-Adresse für YouTube, Vimeo, Spotify (nur diese Anbieter; sonst null). */
function elvado_bd_embed_src(string $url): ?string {
    if(preg_match('~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~',$url,$m))return 'https://www.youtube-nocookie.com/embed/'.$m[1];
    if(preg_match('~^https?://(?:www\.)?vimeo\.com/(?:video/)?(\d{5,12})~',$url,$m))return 'https://player.vimeo.com/video/'.$m[1];
    if(preg_match('~^https?://open\.spotify\.com/(?:intl-[a-z]+/)?(album|track|playlist|artist|episode|show)/([A-Za-z0-9]{10,30})~',$url,$m))return 'https://open.spotify.com/embed/'.$m[1].'/'.$m[2];
    return null;
}
function elvado_bd_embed_html(string $src,string $title,string $hint='Zum Laden des Players klicken'): string {
    $host=(string)parse_url($src,PHP_URL_HOST);
    return '<div class="bd-embed"><button type="button" data-embed="'.esc_url($src).'" data-title="'.esc_attr($title).'">▶ '.esc_html($title).'<small>'.esc_html($hint).' – dabei werden Daten an '.esc_html(preg_replace('/^www\./','',$host)).' übertragen.</small></button></div>';
}
function elvado_bd_btn(string $label,string $url,string $cls='btn-sm btn-ghost'): string {
    return $url!==''?'<a class="btn '.esc_attr($cls).'" href="'.esc_url($url).'" rel="noopener">'.esc_html($label).'</a>':'';
}
function elvado_bd_title(string $t,string $small=''): string { return '<h2 class="bd-title">'.esc_html($t).($small!==''?' <small>'.esc_html($small).'</small>':'').'</h2>'; }
function elvado_bd_paragraphs(string $t): string {
    $o='';foreach(preg_split('/\n{2,}/',trim($t)) as $p){ if(trim($p)!=='')$o.='<p>'.nl2br(esc_html(trim($p))).'</p>'; }return $o;
}

/* ───────── Abschnitte (jeder liefert HTML oder '' wenn leer) ───────── */
function elvado_bd_section_hero(): string {
    $b=elvado_bd_cfg()['band'];$img=(string)($b['hero_image']??'');$next=elvado_bd_next_show();$rel=elvado_bd_releases()[0]??null;
    $meta=trim(implode(' · ',array_filter([(string)($b['genre']??''),(string)($b['origin']??'')])));
    $h='<section class="bd-hero'.($img!==''?' has-img':'').'" id="top"'.($img!==''?' style="background-image:url(\''.esc_url($img).'\')"':'').'><div class="wrap"><h1>'.esc_html(elvado_bd_name()).'</h1>';
    $tag=(string)($b['tagline']??'');if($tag!==''||$meta!=='')$h.='<p class="bd-tag">'.esc_html($tag!==''?$tag:$meta).'</p>';
    $h.='<div class="bd-cta">';
    if($next&&$next['ticket_url']!==''&&$next['status']==='onsale'){ $d=elvado_bd_date_parts($next['date']);$h.=elvado_bd_btn('Tickets '.$next['city'].' '.$d['day'].'.'.str_pad((string)(int)substr($next['date'],5,2),2,'0',STR_PAD_LEFT).'.',$next['ticket_url'],''); }
    elseif($next)$h.='<a class="btn" href="#shows">Live-Termine</a>';
    if($rel){ $go=$rel['spotify']?:($rel['apple']?:($rel['bandcamp']?:$rel['youtube']));$h.=$go!==''?elvado_bd_btn('Jetzt hören: '.$rel['title'],$go,'btn-ghost'):'<a class="btn btn-ghost" href="#music">Musik</a>'; }
    return $h.'</div></div></section>';
}
function elvado_bd_section_ticker(): string {
    $items=[];$n=elvado_bd_next_show();$r=elvado_bd_releases()[0]??null;$b=elvado_bd_cfg()['band'];
    if($n){ $d=elvado_bd_date_parts($n['date']);$items[]='<i>Nächste Show</i> '.esc_html($d['de'].' · '.trim($n['venue'].' '.$n['city'])); }
    if($r)$items[]='<i>Neu</i> '.esc_html($r['title']);
    if(!empty($b['tagline']))$items[]=esc_html($b['tagline']);
    if(!$items)return '';
    $row='';for($i=0;$i<6;$i++)foreach($items as $it)$row.='<span>'.$it.'</span>';
    return '<div class="bd-ticker" aria-label="Aktuelles"><div class="bd-ticker-in">'.$row.'</div></div>';
}
function elvado_bd_show_row(array $s,string $cls=''): string {
    $d=elvado_bd_date_parts($s['date']);$st=defined('RRW_BAND_SHOW_STATUS')?RRW_BAND_SHOW_STATUS:[];
    $h='<li class="bd-show '.esc_attr($s['status'].' '.$cls).'"><div class="bd-date"><span>'.esc_html($d['wd']).'</span><b>'.esc_html($d['day']).'</b><span>'.esc_html($d['mon']).'</span></div><div><div class="bd-venue">'.esc_html($s['venue']!==''?$s['venue']:$s['city']).'</div>'
        .'<div class="bd-city">'.esc_html(trim(($s['venue']!==''?$s['city']:'').($s['time']!==''?' · '.$s['time'].' Uhr':''),' ·')).'</div>'.($s['note']!==''?'<div class="bd-note">'.esc_html($s['note']).'</div>':'')
        .($s['status']!=='onsale'?'<span class="bd-badge '.esc_attr($s['status']).'">'.esc_html($st[$s['status']]??'').'</span>':'').'</div><div class="bd-act">';
    if($s['ticket_url']!==''&&in_array($s['status'],['onsale','announced'],true)&&$cls!=='past')$h.='<a class="btn" href="'.esc_url($s['ticket_url']).'" rel="noopener">Tickets</a>';
    return $h.'</div></li>';
}
function elvado_bd_section_shows(): string {
    if(!elvado_bd_on('show_shows'))return '';
    $up=elvado_bd_shows();$past=!empty(elvado_bd_cfg()['display']['past_shows'])?elvado_bd_shows(true):[];
    if(!$up&&!$past)return '';
    $lim=max(3,(int)get_theme_mod('bd_past',5));
    $h='<section class="bd-sec" id="shows"><div class="wrap">'.elvado_bd_title('Live','Konzerte');
    if($up){ $h.='<ul class="bd-shows">';foreach($up as $i=>$s)$h.=($i===$lim?'</ul><details class="bd-more"><summary class="btn btn-sm btn-ghost">Alle '.count($up).' Termine anzeigen</summary><ul class="bd-shows">':'').elvado_bd_show_row($s);
        $h.='</ul>'.(count($up)>$lim?'</details>':''); }
    else $h.='<p>Aktuell sind keine Konzerte geplant – schau bald wieder vorbei.</p>';
    if($past){ $h.='<details class="bd-more"><summary class="btn btn-sm btn-ghost">Vergangene Konzerte</summary><ul class="bd-shows">';foreach($past as $s)$h.=elvado_bd_show_row($s,'past');$h.='</ul></details>'; }
    return $h.'</div></section>';
}
function elvado_bd_section_music(): string {
    if(!elvado_bd_on('show_music')||!($rel=elvado_bd_releases()))return '';
    $h='<section class="bd-sec alt" id="music"><div class="wrap">'.elvado_bd_title('Musik','Veröffentlichungen').'<div class="bd-releases">';
    $types=['album'=>'Album','ep'=>'EP','single'=>'Single','live'=>'Live'];
    foreach($rel as $r){
        $h.='<article class="bd-release">'.($r['cover']!==''?'<img class="bd-cover" loading="lazy" src="'.esc_url($r['cover']).'" alt="Cover: '.esc_attr($r['title']).'">':'<div class="bd-cover"></div>');
        $date=$r['date']!==''?elvado_bd_date_parts($r['date'])['year']:'';
        $h.='<h3>'.esc_html($r['title']).'</h3><div class="bd-meta">'.esc_html(trim(($types[$r['type']]??'').' '.$date)).'</div>'.($r['description']!==''?'<p>'.nl2br(esc_html($r['description'])).'</p>':'');
        $h.='<div class="bd-links">'.elvado_bd_btn('Spotify',$r['spotify']).elvado_bd_btn('Apple Music',$r['apple']).elvado_bd_btn('Bandcamp',$r['bandcamp']).elvado_bd_btn('YouTube',$r['youtube']).'</div>';
        if($r['embed']!==''&&($src=elvado_bd_embed_src($r['embed'])))$h.=elvado_bd_embed_html($src,'Anhören: '.$r['title']);
        $h.='</article>';
    }
    return $h.'</div></div></section>';
}
function elvado_bd_section_videos(): string {
    if(!elvado_bd_on('show_videos'))return '';$v='';
    foreach(elvado_bd_cfg()['videos'] as $x){ $src=elvado_bd_embed_src($x['url']);if(!$src)continue;$v.='<div>'.elvado_bd_embed_html($src,$x['title']!==''?$x['title']:'Video abspielen').($x['title']!==''?'<h3>'.esc_html($x['title']).'</h3>':'').'</div>'; }
    return $v!==''?'<section class="bd-sec" id="videos"><div class="wrap">'.elvado_bd_title('Videos').'<div class="bd-videos">'.$v.'</div></div></section>':'';
}
function elvado_bd_section_band(): string {
    if(!elvado_bd_on('show_band'))return '';$c=elvado_bd_cfg();$b=$c['band'];$m=$c['members'];
    if(trim((string)($b['bio']??''))===''&&!$m)return '';
    $h='<section class="bd-sec alt" id="band"><div class="wrap">'.elvado_bd_title('Über uns','Die Band');
    $bio=elvado_bd_paragraphs((string)($b['bio']??''));$ph=(string)($b['press_photo']??'');
    if($bio!==''||$ph!=='')$h.='<div class="bd-about"><div class="bd-bio">'.$bio.'</div>'.($ph!==''?'<div><img loading="lazy" src="'.esc_url($ph).'" alt="'.esc_attr(elvado_bd_name()).'"></div>':'').'</div>';
    if($m){ $h.='<div class="bd-members">';foreach($m as $x)$h.='<div class="bd-member">'.($x['photo']!==''?'<img loading="lazy" src="'.esc_url($x['photo']).'" alt="'.esc_attr($x['name']).'">':'').'<h3>'.esc_html($x['name']).'</h3>'.($x['role']!==''?'<div class="bd-role">'.esc_html($x['role']).'</div>':'').($x['bio']!==''?'<p>'.esc_html($x['bio']).'</p>':'').'</div>';$h.='</div>'; }
    return $h.'</div></section>';
}
function elvado_bd_section_gallery(): string {
    if(!elvado_bd_on('show_gallery')||!($g=elvado_bd_cfg()['gallery']))return '';
    $h='<section class="bd-sec" id="gallery"><div class="wrap">'.elvado_bd_title('Bilder','Galerie').'<div class="bd-gallery">';
    foreach($g as $x)$h.='<figure><img loading="lazy" src="'.esc_url($x['image']).'" alt="'.esc_attr($x['caption']).'">'.($x['caption']!==''?'<figcaption>'.esc_html($x['caption']).'</figcaption>':'').'</figure>';
    return $h.'</div></div></section>';
}
function elvado_bd_section_news(): string {
    if(!elvado_bd_on('show_news'))return '';
    $q=new WP_Query(['post_type'=>'post','posts_per_page'=>max(1,min(12,(int)get_theme_mod('bd_news_count',3))),'ignore_sticky_posts'=>true]);
    if(!$q->have_posts()){ wp_reset_postdata();return ''; }
    $h='<section class="bd-sec alt" id="news"><div class="wrap">'.elvado_bd_title('News','Aktuelles').'<div class="bd-news">';
    while($q->have_posts()){ $q->the_post();
        $h.='<article class="bd-post">'.(has_post_thumbnail()?'<a class="post-thumbnail" href="'.esc_url(get_permalink()).'" aria-hidden="true" tabindex="-1">'.get_the_post_thumbnail(null,'medium_large').'</a>':'').'<div class="bd-meta">'.esc_html(get_the_date()).'</div><h3><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></h3><div>'.wp_kses_post(get_the_excerpt()).'</div></article>';
    }
    wp_reset_postdata();return $h.'</div></div></section>';
}
function elvado_bd_section_newsletter(): string {
    if(!elvado_bd_on('show_newsletter')||!shortcode_exists('newsletter'))return '';
    $o=do_shortcode('[newsletter]');if(trim(wp_strip_all_tags($o))==='')return '';
    return '<section class="bd-sec" id="newsletter"><div class="wrap">'.elvado_bd_title('Newsletter','Bleib auf dem Laufenden').'<div class="bd-news-form">'.$o.'</div></div></section>';
}
function elvado_bd_section_extra(): string {
    if(!is_active_sidebar('front-extra'))return '';
    ob_start();dynamic_sidebar('front-extra');return '<section class="bd-sec" id="extra"><div class="wrap">'.ob_get_clean().'</div></section>';
}
function elvado_bd_section_booking(): string {
    if(!elvado_bd_on('show_booking'))return '';$b=elvado_bd_cfg()['booking'];
    if(!array_filter([$b['text']??'',$b['email']??'',$b['presskit_url']??'',$b['rider_url']??'']))return '';
    $h='<section class="bd-sec bd-booking" id="booking"><div class="wrap">'.elvado_bd_title((string)($b['heading']??'')?:'Booking & Presse');
    if(!empty($b['text']))$h.=elvado_bd_paragraphs((string)$b['text']);
    if(!empty($b['email']))$h.='<p><a class="bd-mail" href="mailto:'.esc_attr($b['email']).'">'.esc_html($b['email']).'</a></p>';
    if(!empty($b['agency'])||!empty($b['phone']))$h.='<p>'.esc_html(trim(($b['agency']??'').(!empty($b['agency'])&&!empty($b['phone'])?' · ':'').($b['phone']??''))).'</p>';
    $h.='<div class="bd-cta">'.elvado_bd_btn('Presskit herunterladen',(string)($b['presskit_url']??''),'btn-ghost').elvado_bd_btn('Technical Rider',(string)($b['rider_url']??''),'btn-ghost').'</div>';
    return $h.'</div></section>';
}
/** Reihenfolge der Abschnitte; Plugins ergänzen eigene über den Filter (Abschnitts-ID, dazu add_filter('elvado_bd_section_<id>', …) liefert das HTML). */
function elvado_bd_section_ids(): array {
    $ids=apply_filters('elvado_bd_sections',['hero','ticker','shows','music','videos','band','gallery','news','newsletter','extra','booking']);
    return array_values(array_filter((array)$ids,fn($i)=>is_string($i)&&preg_match('/^[a-z0-9_]{1,40}$/',$i)));
}
function elvado_bd_render_front(): void {
    foreach(elvado_bd_section_ids() as $id){
        do_action('elvado_bd_before_section',$id);
        $fn='elvado_bd_section_'.$id;$html=function_exists($fn)?$fn():'';
        echo apply_filters('elvado_bd_section_'.$id,$html);
        do_action('elvado_bd_after_section',$id);
    }
}
/** Hauptmenü, solange keins zugewiesen ist: Anker auf die Abschnitte der Startseite, die Inhalt haben. */
function elvado_bd_fallback_menu(): void {
    $home=esc_url(home_url('/'));$map=['shows'=>'Live','music'=>'Musik','videos'=>'Videos','band'=>'Band','gallery'=>'Bilder','news'=>'News','booking'=>'Booking'];$o='';
    foreach($map as $id=>$l){ $fn='elvado_bd_section_'.$id;if($fn()!=='')$o.='<li><a href="'.$home.'#'.$id.'">'.esc_html($l).'</a></li>'; }
    echo '<ul>'.$o.'</ul>';
}
/** Strukturierte Daten für Suchmaschinen: Band (MusicGroup) und kommende Konzerte (MusicEvent). Nur auf der Startseite. */
function elvado_bd_jsonld(): string {
    if(!is_front_page())return '';$c=elvado_bd_cfg();$home=home_url('/');
    $g=['@type'=>'MusicGroup','name'=>elvado_bd_name(),'url'=>$home];
    if(!empty($c['band']['genre']))$g['genre']=$c['band']['genre'];
    if(!empty($c['band']['hero_image']))$g['image']=(strpos($c['band']['hero_image'],'/')===0?rtrim($home,'/'):'').$c['band']['hero_image'];
    $same=array_values(array_map(fn($l)=>$l['url'],$c['links']));if($same)$g['sameAs']=$same;
    $ev=[];
    foreach(array_slice(elvado_bd_shows(),0,20) as $s){
        if($s['status']==='cancelled')continue;
        $e=['@type'=>'MusicEvent','name'=>elvado_bd_name().($s['city']!==''?' – '.$s['city']:''),'startDate'=>$s['date'].($s['time']!==''?'T'.$s['time']:''),'eventStatus'=>'https://schema.org/EventScheduled',
            'location'=>['@type'=>'Place','name'=>$s['venue']?:$s['city'],'address'=>['@type'=>'PostalAddress','addressLocality'=>$s['city']]],'performer'=>['@type'=>'MusicGroup','name'=>elvado_bd_name()]];
        if($s['ticket_url']!=='')$e['offers']=['@type'=>'Offer','url'=>$s['ticket_url'],'availability'=>$s['status']==='soldout'?'https://schema.org/SoldOut':'https://schema.org/InStock'];
        $ev[]=$e;
    }
    $data=['@context'=>'https://schema.org','@graph'=>array_merge([$g],$ev)];
    return '<script type="application/ld+json">'.json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP).'</script>'."\n";
}

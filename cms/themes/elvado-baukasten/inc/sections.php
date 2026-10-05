<?php
/* Baukasten: Vorgaben, Werte und Ausgabe der Startseiten-Abschnitte. */
if(!defined('ABSPATH'))exit;

function elvado_bk_defaults(): array {
    return [
        'color_accent'=>'#2563eb','color_bg'=>'#f6f7fb','color_card'=>'#ffffff','color_text'=>'#1b2230','color_hero_bg'=>'#1e3a8a','color_hero_text'=>'#ffffff',
        'font_head'=>'system','font_body'=>'system','font_size'=>16,'radius'=>14,'content_width'=>1120,'sidebar_width'=>300,'section_pad'=>56,
        'header_layout'=>'split','header_sticky'=>false,'sidebar_pos'=>'right','footer_text'=>'','custom_css'=>'',
        'slot_1'=>'hero','slot_2'=>'features','slot_3'=>'posts','slot_4'=>'cta','slot_5'=>'none','slot_6'=>'none','slot_7'=>'none','slot_8'=>'none',
        'hero_title'=>'','hero_text'=>'','hero_image'=>'','hero_overlay'=>true,'hero_btn_label'=>'Mehr erfahren','hero_btn_url'=>'',
        'text_title'=>'Über uns','text_body'=>'','text_center'=>false,
        'feat_title'=>'Das bieten wir','feat_1_title'=>'Schnell','feat_1_text'=>'Kurze Ladezeiten ohne Ballast.','feat_2_title'=>'Flexibel','feat_2_text'=>'Alles lässt sich anpassen.','feat_3_title'=>'Eigenständig','feat_3_text'=>'Deine Inhalte, dein Design.',
        'split_title'=>'','split_text'=>'','split_image'=>'','split_reverse'=>false,
        'posts_title'=>'Neueste Beiträge','posts_count'=>3,'posts_all_label'=>'Alle Beiträge',
        'cta_title'=>'Bereit loszulegen?','cta_text'=>'','cta_btn_label'=>'Kontakt aufnehmen','cta_btn_url'=>'',
        'html_code'=>'',
    ];
}
function elvado_bk_mod(string $k){ $d=elvado_bk_defaults();return get_theme_mod($k,$d[$k]??''); }
function elvado_bk_types(): array {
    return ['none'=>'— leer —','hero'=>'Hero (Kopfbild)','text'=>'Textabschnitt','features'=>'Drei Vorteile','image_text'=>'Bild + Text','posts'=>'Neueste Beiträge','cta'=>'Aufruf (Call to Action)','html'=>'Eigenes HTML'];
}
function elvado_bk_slots(): array {
    $out=[];for($i=1;$i<=8;$i++){ $t=(string)elvado_bk_mod('slot_'.$i);if($t!=='none'&&isset(elvado_bk_types()[$t])&&!in_array($t,$out,true))$out[]=$t; }
    return $out;
}
function elvado_bk_fonts(): array {
    return ['system'=>['System-Schrift','system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif'],
        'serif'=>['Serifen (Georgia)','Georgia,"Times New Roman",Times,serif'],
        'humanist'=>['Humanistisch','"Trebuchet MS","Lucida Grande",Verdana,sans-serif'],
        'mono'=>['Monospace','ui-monospace,SFMono-Regular,Menlo,Consolas,monospace'],
        'rounded'=>['Rund','"Segoe UI Rounded","Arial Rounded MT Bold",Verdana,sans-serif']];
}
function elvado_bk_hex(string $v,string $fallback): string { return preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i',$v)?$v:$fallback; }
/** Einziger Ort, an dem Einstellungen in CSS übersetzt werden (Werte werden hier begrenzt/bereinigt). */
function elvado_bk_css(): string {
    $d=elvado_bk_defaults();$f=elvado_bk_fonts();$cl=fn($k,$min,$max)=>max($min,min($max,(int)elvado_bk_mod($k)?:(int)$d[$k]));
    $c=fn($k)=>elvado_bk_hex((string)elvado_bk_mod($k),$d[$k]);
    $hf=$f[elvado_bk_mod('font_head')][1]??$f['system'][1];$bf=$f[elvado_bk_mod('font_body')][1]??$f['system'][1];
    $acc=$c('color_accent');
    $css=':root{--accent:'.$acc.';--accent-d:'.$acc.';--bg:'.$c('color_bg').';--card:'.$c('color_card').';--text:'.$c('color_text').';--hero-bg:'.$c('color_hero_bg').';--hero-text:'.$c('color_hero_text').
        ';--head-font:'.$hf.';--body-font:'.$bf.';--fs:'.$cl('font_size',12,24).'px;--radius:'.$cl('radius',0,40).'px;--max:'.$cl('content_width',720,1600).'px;--sidebar:'.$cl('sidebar_width',200,500).'px;--bk-pad:'.$cl('section_pad',0,160).'px}';
    $css.='@media (prefers-color-scheme:dark){body.bk-auto-dark{--bg:#0d1220;--card:#141b2e;--text:#e8ecf5;--line:#243049;--muted:#93a0b8}}';
    $extra=trim((string)elvado_bk_mod('custom_css'));
    if($extra!=='')$css.=str_replace('</','<\/',wp_strip_all_tags($extra));
    return $css;
}
function elvado_bk_btn(string $label,string $url,string $cls=''): string {
    $label=trim($label);if($label==='')return '';$url=trim($url)!==''?$url:home_url('/');
    return '<a class="btn '.esc_attr($cls).'" href="'.esc_url($url).'">'.esc_html($label).'</a>';
}
function elvado_bk_section(string $type,int $n=0): void {
    $m='elvado_bk_mod';$alt=$n%2===1?' bk-alt':'';
    switch($type){
    case 'hero':
        $img=esc_url((string)$m('hero_image'));$title=(string)$m('hero_title')?:get_bloginfo('name');$text=(string)$m('hero_text')?:get_bloginfo('description');
        $style=$img!==''?' style="background-image:url(\''.$img.'\')"':'';$ov=$img!==''&&$m('hero_overlay')?' has-overlay':'';
        echo '<section class="bk-section bk-hero bk-center'.$ov.'"'.$style.'><div class="bk-wrap"><h1>'.esc_html($title).'</h1>'.($text!==''?'<p>'.esc_html($text).'</p>':'').elvado_bk_btn((string)$m('hero_btn_label'),(string)$m('hero_btn_url')).'</div></section>';break;
    case 'text':
        $body=(string)$m('text_body');if($body==='')return;
        echo '<section class="bk-section'.$alt.'"><div class="bk-wrap'.($m('text_center')?' bk-center':'').'">'.($m('text_title')?'<h2 class="bk-title">'.esc_html((string)$m('text_title')).'</h2>':'').wpautop(wp_kses_post($body)).'</div></section>';break;
    case 'features':
        $items='';for($i=1;$i<=3;$i++){ $t=(string)$m('feat_'.$i.'_title');$x=(string)$m('feat_'.$i.'_text');if($t===''&&$x==='')continue;$items.='<div class="bk-feature"><h3>'.esc_html($t).'</h3><p>'.esc_html($x).'</p></div>'; }
        if($items==='')return;
        echo '<section class="bk-section'.$alt.'"><div class="bk-wrap">'.($m('feat_title')?'<h2 class="bk-title bk-center">'.esc_html((string)$m('feat_title')).'</h2>':'').'<div class="bk-features">'.$items.'</div></div></section>';break;
    case 'image_text':
        $img=esc_url((string)$m('split_image'));$text=(string)$m('split_text');if($img===''&&$text==='')return;
        echo '<section class="bk-section'.$alt.'"><div class="bk-wrap bk-split'.($m('split_reverse')?' rev':'').'">'.($img!==''?'<div class="bk-split-img"><img src="'.$img.'" alt="" loading="lazy"></div>':'').'<div>'.($m('split_title')?'<h2 class="bk-title">'.esc_html((string)$m('split_title')).'</h2>':'').wpautop(wp_kses_post($text)).'</div></div></section>';break;
    case 'posts':
        $q=new WP_Query(['post_type'=>'post','posts_per_page'=>max(1,min(12,(int)$m('posts_count'))),'ignore_sticky_posts'=>true]);
        if(!$q->have_posts()){ wp_reset_postdata();return; }
        echo '<section class="bk-section'.$alt.'"><div class="bk-wrap">'.($m('posts_title')?'<h2 class="bk-title">'.esc_html((string)$m('posts_title')).'</h2>':'').'<div class="bk-posts">';
        while($q->have_posts()){ $q->the_post();
            echo '<article class="post-card">'.(has_post_thumbnail()?'<a class="post-thumbnail" href="'.esc_url(get_permalink()).'" aria-hidden="true" tabindex="-1">'.get_the_post_thumbnail(null,'medium_large').'</a>':'').'<h3 class="entry-title"><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></h3><div class="entry-meta">'.esc_html(get_the_date()).'</div><div class="entry-summary">'.wp_kses_post(get_the_excerpt()).'</div></article>';
        }
        wp_reset_postdata();
        $all=get_option('page_for_posts')?get_permalink((int)get_option('page_for_posts')):'';
        echo '</div>'.($all!==''&&$m('posts_all_label')?'<p class="bk-center" style="margin-top:20px">'.elvado_bk_btn((string)$m('posts_all_label'),$all).'</p>':'').'</div></section>';break;
    case 'cta':
        echo '<section class="bk-section bk-cta"><div class="bk-wrap"><h2 class="bk-title">'.esc_html((string)$m('cta_title')).'</h2>'.($m('cta_text')?'<p>'.esc_html((string)$m('cta_text')).'</p>':'').elvado_bk_btn((string)$m('cta_btn_label'),(string)$m('cta_btn_url')).'</div></section>';break;
    case 'html':
        $h=(string)$m('html_code');if($h==='')return;
        echo '<section class="bk-section'.$alt.'"><div class="bk-wrap">'.do_shortcode(wp_kses_post($h)).'</div></section>';break;
    }
}
function elvado_bk_render_front(): void {
    $i=0;foreach(elvado_bk_slots() as $t){ elvado_bk_section($t,$i++); }
}
function elvado_bk_is_builder_page(): bool { return is_front_page()&&!is_paged()&&elvado_bk_slots()!==[]; }

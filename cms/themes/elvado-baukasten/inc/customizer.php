<?php
/* Baukasten im WordPress-Customizer: jede Einstellung ist ein theme_mod, Standardwerte aus elvado_bk_defaults(). */
if(!defined('ABSPATH'))exit;
add_action('customize_register',function($wp){
    $d=elvado_bk_defaults();
    $sec=fn($id,$title,$prio,$desc='')=>$wp->add_section($id,['title'=>$title,'priority'=>$prio,'description'=>$desc]);
    $add=function($id,$section,$label,$type,$extra=[]) use($wp,$d){
        $san=['color'=>'sanitize_hex_color','text'=>'sanitize_text_field','textarea'=>'wp_kses_post','select'=>'sanitize_text_field','radio'=>'sanitize_text_field','number'=>'absint','range'=>'absint','image'=>'esc_url_raw','checkbox'=>'rest_sanitize_boolean'];
        $wp->add_setting($id,['default'=>$d[$id]??'','sanitize_callback'=>$san[$type]??'sanitize_text_field']);
        $wp->add_control($id,['label'=>$label,'section'=>$section,'type'=>$type]+$extra);
    };
    $fonts=array_map(fn($f)=>$f[0],elvado_bk_fonts());
    $sec('bk_layout','Baukasten: Startseite',20,'Wähle bis zu acht Abschnitte in der gewünschten Reihenfolge. Jeder Typ erscheint höchstens einmal.');
    for($i=1;$i<=8;$i++)$add('slot_'.$i,'bk_layout','Position '.$i,'select',['choices'=>elvado_bk_types()]);
    $sec('bk_colors','Baukasten: Farben',21);
    foreach(['color_accent'=>'Akzentfarbe','color_bg'=>'Hintergrund','color_card'=>'Karten/Flächen','color_text'=>'Textfarbe','color_hero_bg'=>'Hero-Hintergrund','color_hero_text'=>'Hero-Text'] as $k=>$l)$add($k,'bk_colors',$l,'color');
    $sec('bk_type','Baukasten: Schrift & Form',22);
    $add('font_head','bk_type','Überschriften','select',['choices'=>$fonts]);$add('font_body','bk_type','Fließtext','select',['choices'=>$fonts]);
    $add('font_size','bk_type','Schriftgröße (px)','range',['input_attrs'=>['min'=>12,'max'=>24,'step'=>1]]);
    $add('radius','bk_type','Eckenradius (px)','range',['input_attrs'=>['min'=>0,'max'=>40,'step'=>1]]);
    $add('content_width','bk_type','Inhaltsbreite (px)','range',['input_attrs'=>['min'=>720,'max'=>1600,'step'=>20]]);
    $add('sidebar_width','bk_type','Breite der Seitenleiste (px)','range',['input_attrs'=>['min'=>200,'max'=>500,'step'=>10]]);
    $add('section_pad','bk_type','Abstand der Abschnitte (px)','range',['input_attrs'=>['min'=>0,'max'=>160,'step'=>4]]);
    $sec('bk_head','Baukasten: Kopf & Fuß',23);
    $add('header_layout','bk_head','Kopfbereich','select',['choices'=>['split'=>'Logo links, Menü rechts','center'=>'Zentriert']]);
    $add('header_sticky','bk_head','Kopfbereich beim Scrollen fixieren','checkbox');
    $add('sidebar_pos','bk_head','Seitenleiste (Beiträge/Seiten)','select',['choices'=>['right'=>'Rechts','left'=>'Links']]);
    $add('footer_text','bk_head','Fußzeilentext (leer = © Jahr Name)','text');
    $sec('bk_hero','Baukasten: Hero',24);
    $add('hero_title','bk_hero','Überschrift (leer = Website-Titel)','text');$add('hero_text','bk_hero','Text (leer = Untertitel)','textarea');
    $add('hero_image','bk_hero','Hintergrundbild (Adresse)','image');$add('hero_overlay','bk_hero','Bild abdunkeln','checkbox');
    $add('hero_btn_label','bk_hero','Button-Text','text');$add('hero_btn_url','bk_hero','Button-Ziel (URL)','text');
    $sec('bk_text','Baukasten: Text & Vorteile',25);
    $add('text_title','bk_text','Text: Überschrift','text');$add('text_body','bk_text','Text: Inhalt (HTML erlaubt)','textarea');$add('text_center','bk_text','Text zentrieren','checkbox');
    $add('feat_title','bk_text','Vorteile: Überschrift','text');
    for($i=1;$i<=3;$i++){ $add('feat_'.$i.'_title','bk_text','Vorteil '.$i.': Titel','text');$add('feat_'.$i.'_text','bk_text','Vorteil '.$i.': Text','textarea'); }
    $sec('bk_more','Baukasten: Bild+Text, Beiträge, Aufruf, HTML',26);
    $add('split_title','bk_more','Bild+Text: Überschrift','text');$add('split_text','bk_more','Bild+Text: Inhalt','textarea');$add('split_image','bk_more','Bild+Text: Bild (Adresse)','image');$add('split_reverse','bk_more','Bild rechts statt links','checkbox');
    $add('posts_title','bk_more','Beiträge: Überschrift','text');$add('posts_count','bk_more','Beiträge: Anzahl','number',['input_attrs'=>['min'=>1,'max'=>12]]);$add('posts_all_label','bk_more','Beiträge: Button „alle“','text');
    $add('cta_title','bk_more','Aufruf: Überschrift','text');$add('cta_text','bk_more','Aufruf: Text','text');$add('cta_btn_label','bk_more','Aufruf: Button-Text','text');$add('cta_btn_url','bk_more','Aufruf: Button-Ziel (URL)','text');
    $add('html_code','bk_more','Eigenes HTML/Shortcodes','textarea');
    $sec('bk_css','Baukasten: Eigenes CSS',27);
    $add('custom_css','bk_css','Zusätzliches CSS','textarea');
});

<?php
if(!defined('ABSPATH'))exit;
require_once __DIR__.'/inc/creator-theme.php';
add_action('after_setup_theme',function(){
    add_theme_support('title-tag');add_theme_support('post-thumbnails');add_theme_support('automatic-feed-links');
    add_theme_support('html5',['search-form','comment-form','comment-list','gallery','caption','style','script']);
    add_theme_support('custom-logo');add_theme_support('responsive-embeds');
    register_nav_menus(['primary'=>'Hauptmenü','footer'=>'Fußmenü']);
});
add_action('widgets_init',function(){
    $w=['before_widget'=>'<section id="%1$s" class="widget %2$s">','after_widget'=>'</section>','before_title'=>'<h2 class="widget-title">','after_title'=>'</h2>'];
    register_sidebar(['name'=>'Seitenleiste','id'=>'sidebar-1','description'=>'Erscheint neben dem Inhalt.']+$w);
    register_sidebar(['name'=>'Startseite: Zusatzbereich','id'=>'front-extra','description'=>'Erscheint auf der Startseite vor dem Kontakt – für Plugins und Widgets.']+$w);
});
add_action('wp_enqueue_scripts',function(){
    $v=wp_get_theme()->get('Version');
    wp_enqueue_style('elvado-creator',get_stylesheet_uri(),[],$v);
    wp_enqueue_script('elvado-creator',get_stylesheet_directory_uri().'/assets/creator.js',[],$v,true);
});
add_action('wp_head',function(){
    $a=elvado_cr_color('accent','');
    if($a!=='')echo '<style id="elvado-creator-vars">:root{--accent:'.$a.';--on-accent:'.elvado_cr_contrast($a).'}</style>'."\n";
    echo elvado_cr_jsonld();
},20);
add_filter('body_class',function($c){
    $s=(string)get_theme_mod('cr_scheme','sunset');if(in_array($s,['ocean','lilac','mono','night'],true))$c[]='scheme-'.$s;
    $sh=(string)get_theme_mod('cr_shape','round');if(in_array($sh,['soft','sharp'],true))$c[]='shape-'.$sh;
    $f=(string)get_theme_mod('cr_font','modern');if(in_array($f,['editorial','rounded'],true))$c[]='font-'.$f;
    if(elvado_cr_on('mobile_nav'))$c[]='has-mnav';
    return $c;
});
add_action('customize_register',function($wp){
    $wp->add_section('cr_design',['title'=>'Creator: Design','priority'=>20,'description'=>'Profil, Links, Feed, Empfehlungen usw. pflegst du im CMS unter „Creator“.']);
    $wp->add_setting('cr_scheme',['default'=>'sunset','sanitize_callback'=>'sanitize_key']);$wp->add_control('cr_scheme',['label'=>'Farbwelt','section'=>'cr_design','type'=>'select','choices'=>['sunset'=>'Sunset (Pfirsich/Pink)','ocean'=>'Ocean (Blau/Mint)','lilac'=>'Lilac (Flieder)','mono'=>'Mono (Schwarz/Weiß)','night'=>'Night (dunkel)']]);
    $wp->add_setting('cr_accent',['default'=>'','sanitize_callback'=>'sanitize_hex_color']);$wp->add_control('cr_accent',['label'=>'Eigene Akzentfarbe (leer = passend zur Farbwelt)','section'=>'cr_design','type'=>'color']);
    $wp->add_setting('cr_shape',['default'=>'round','sanitize_callback'=>'sanitize_key']);$wp->add_control('cr_shape',['label'=>'Formen','section'=>'cr_design','type'=>'select','choices'=>['round'=>'Weich & rund','soft'=>'Dezent','sharp'=>'Kantig']]);
    $wp->add_setting('cr_font',['default'=>'modern','sanitize_callback'=>'sanitize_key']);$wp->add_control('cr_font',['label'=>'Schrift','section'=>'cr_design','type'=>'select','choices'=>['modern'=>'Modern (Sans)','editorial'=>'Magazin (Serifen)','rounded'=>'Freundlich (rund)']]);
    $wp->add_setting('cr_news_count',['default'=>3,'sanitize_callback'=>'absint']);$wp->add_control('cr_news_count',['label'=>'Anzahl News auf der Startseite','section'=>'cr_design','type'=>'number','input_attrs'=>['min'=>1,'max'=>12]]);
});

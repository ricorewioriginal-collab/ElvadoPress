<?php
if(!defined('ABSPATH'))exit;
require_once __DIR__.'/inc/radio-theme.php';
add_action('after_setup_theme',function(){
    add_theme_support('title-tag');add_theme_support('post-thumbnails');add_theme_support('automatic-feed-links');
    add_theme_support('html5',['search-form','comment-form','comment-list','gallery','caption','style','script']);
    add_theme_support('custom-logo');add_theme_support('responsive-embeds');
    register_nav_menus(['primary'=>'Hauptmenü','footer'=>'Fußmenü']);
});
add_action('widgets_init',function(){
    register_sidebar(['name'=>'Seitenleiste','id'=>'sidebar-1','description'=>'Erscheint neben dem Inhalt.','before_widget'=>'<section id="%1$s" class="widget %2$s">','after_widget'=>'</section>','before_title'=>'<h2 class="widget-title">','after_title'=>'</h2>']);
});
add_action('wp_enqueue_scripts',function(){
    $v=wp_get_theme()->get('Version');
    wp_enqueue_style('elvado-radio',get_stylesheet_uri(),[],$v);
    wp_enqueue_script('elvado-radio-player',get_stylesheet_directory_uri().'/assets/player.js',[],$v,true);
});
add_action('wp_head',function(){
    $a=elvado_rd_color('accent','#b57cff');$b=elvado_rd_color('accent2','#22d3ee');$bg=elvado_rd_color('bg','#070a1c');
    echo '<style id="elvado-radio-vars">:root{--accent:'.$a.';--accent2:'.$b.';--bg:'.$bg.'}</style>'."\n";
},20);
add_action('customize_register',function($wp){
    $wp->add_section('rd_design',['title'=>'Radio: Design','priority'=>20]);
    foreach(['accent'=>['Akzentfarbe','#b57cff'],'accent2'=>['Zweite Farbe','#22d3ee'],'bg'=>['Hintergrund','#070a1c']] as $k=>[$l,$d]){
        $wp->add_setting('rd_'.$k,['default'=>$d,'sanitize_callback'=>'sanitize_hex_color']);$wp->add_control('rd_'.$k,['label'=>$l,'section'=>'rd_design','type'=>'color']);
    }
    $wp->add_section('rd_home',['title'=>'Radio: Startseite','priority'=>21,'description'=>'Sender, Datenquelle und Sendeplan stellst du im CMS unter „Radio“ ein.']);
    $wp->add_setting('rd_eyebrow',['default'=>'Live Radio','sanitize_callback'=>'sanitize_text_field']);$wp->add_control('rd_eyebrow',['label'=>'Kleine Zeile über der Überschrift','section'=>'rd_home','type'=>'text']);
    $wp->add_setting('rd_title',['default'=>'','sanitize_callback'=>'sanitize_text_field']);$wp->add_control('rd_title',['label'=>'Überschrift (leer = Website-Titel)','section'=>'rd_home','type'=>'text']);
    $wp->add_setting('rd_text',['default'=>'','sanitize_callback'=>'sanitize_textarea_field']);$wp->add_control('rd_text',['label'=>'Text (leer = Untertitel/Sender-Slogan)','section'=>'rd_home','type'=>'textarea']);
    $wp->add_setting('rd_news_count',['default'=>3,'sanitize_callback'=>'absint']);$wp->add_control('rd_news_count',['label'=>'Anzahl News auf der Startseite','section'=>'rd_home','type'=>'number','input_attrs'=>['min'=>1,'max'=>12]]);
});

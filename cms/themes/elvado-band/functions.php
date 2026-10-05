<?php
if(!defined('ABSPATH'))exit;
require_once __DIR__.'/inc/band-theme.php';
add_action('after_setup_theme',function(){
    add_theme_support('title-tag');add_theme_support('post-thumbnails');add_theme_support('automatic-feed-links');
    add_theme_support('html5',['search-form','comment-form','comment-list','gallery','caption','style','script']);
    add_theme_support('custom-logo');add_theme_support('responsive-embeds');
    register_nav_menus(['primary'=>'Hauptmenü','footer'=>'Fußmenü']);
});
add_action('widgets_init',function(){
    $w=['before_widget'=>'<section id="%1$s" class="widget %2$s">','after_widget'=>'</section>','before_title'=>'<h2 class="widget-title">','after_title'=>'</h2>'];
    register_sidebar(['name'=>'Seitenleiste','id'=>'sidebar-1','description'=>'Erscheint neben dem Inhalt.']+$w);
    register_sidebar(['name'=>'Startseite: Zusatzbereich','id'=>'front-extra','description'=>'Erscheint auf der Startseite vor Booking und Fuß – für Plugins und Widgets.']+$w);
});
add_action('wp_enqueue_scripts',function(){
    $v=wp_get_theme()->get('Version');
    wp_enqueue_style('elvado-band',get_stylesheet_uri(),[],$v);
    wp_enqueue_script('elvado-band',get_stylesheet_directory_uri().'/assets/band.js',[],$v,true);
});
add_action('wp_head',function(){
    $a=elvado_bd_color('accent','#e4361b');
    echo '<style id="elvado-band-vars">:root{--accent:'.$a.';--on-accent:'.elvado_bd_contrast($a).'}</style>'."\n";
    echo elvado_bd_jsonld();
},20);
add_filter('body_class',function($c){
    $c[]='scheme-'.(get_theme_mod('bd_scheme','paper')==='ink'?'ink':'paper');
    $f=get_theme_mod('bd_font','condensed');if(in_array($f,['serif','mono'],true))$c[]='font-'.$f;return $c;
});
add_action('customize_register',function($wp){
    $wp->add_section('bd_design',['title'=>'Band: Design','priority'=>20,'description'=>'Texte, Termine, Veröffentlichungen usw. pflegst du im CMS unter „Band“.']);
    $wp->add_setting('bd_accent',['default'=>'#e4361b','sanitize_callback'=>'sanitize_hex_color']);$wp->add_control('bd_accent',['label'=>'Akzentfarbe','section'=>'bd_design','type'=>'color']);
    $wp->add_setting('bd_scheme',['default'=>'paper','sanitize_callback'=>'sanitize_key']);$wp->add_control('bd_scheme',['label'=>'Grundton','section'=>'bd_design','type'=>'select','choices'=>['paper'=>'Papier (hell)','ink'=>'Schwarz (dunkel)']]);
    $wp->add_setting('bd_font',['default'=>'condensed','sanitize_callback'=>'sanitize_key']);$wp->add_control('bd_font',['label'=>'Schlagzeilen-Schrift','section'=>'bd_design','type'=>'select','choices'=>['condensed'=>'Schmal & wuchtig','serif'=>'Serifen','mono'=>'Schreibmaschine']]);
    $wp->add_setting('bd_past',['default'=>5,'sanitize_callback'=>'absint']);$wp->add_control('bd_past',['label'=>'Konzerte: so viele zeigen, danach „alle anzeigen“','section'=>'bd_design','type'=>'number','input_attrs'=>['min'=>3,'max'=>50]]);
    $wp->add_setting('bd_news_count',['default'=>3,'sanitize_callback'=>'absint']);$wp->add_control('bd_news_count',['label'=>'Anzahl News auf der Startseite','section'=>'bd_design','type'=>'number','input_attrs'=>['min'=>1,'max'=>12]]);
});

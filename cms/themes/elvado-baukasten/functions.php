<?php
if(!defined('ABSPATH'))exit;
require_once __DIR__.'/inc/sections.php';
require_once __DIR__.'/inc/customizer.php';
add_action('after_setup_theme',function(){
    add_theme_support('title-tag');add_theme_support('post-thumbnails');add_theme_support('automatic-feed-links');
    add_theme_support('html5',['search-form','comment-form','comment-list','gallery','caption','style','script']);
    add_theme_support('custom-logo');add_theme_support('responsive-embeds');
    register_nav_menus(['primary'=>'Hauptmenü','footer'=>'Fußmenü']);
});
add_action('widgets_init',function(){
    register_sidebar(['name'=>'Seitenleiste','id'=>'sidebar-1','description'=>'Erscheint neben dem Inhalt.','before_widget'=>'<section id="%1$s" class="widget %2$s">','after_widget'=>'</section>','before_title'=>'<h2 class="widget-title">','after_title'=>'</h2>']);
});
add_action('wp_enqueue_scripts',function(){ wp_enqueue_style('elvado-baukasten',get_stylesheet_uri(),[],wp_get_theme()->get('Version')); });
add_action('wp_head',function(){ echo '<style id="elvado-baukasten-vars">'.elvado_bk_css()."</style>\n"; },20);
add_filter('body_class',function($c){
    $c[]='header-'.elvado_bk_mod('header_layout');if(elvado_bk_mod('header_sticky'))$c[]='header-sticky';
    $c[]='sidebar-'.elvado_bk_mod('sidebar_pos');return $c;
});

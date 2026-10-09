<?php
if(!defined('ABSPATH'))exit;
add_action('after_setup_theme',function(){
    add_theme_support('title-tag');add_theme_support('post-thumbnails');add_theme_support('automatic-feed-links');add_theme_support('html5',['search-form','comment-form','comment-list','gallery','caption','style','script']);
    add_theme_support('custom-logo');add_theme_support('responsive-embeds');
    register_nav_menus(['primary'=>'Hauptmenü','footer'=>'Fußmenü']);
});
add_action('widgets_init',function(){
    register_sidebar(['name'=>'Seitenleiste','id'=>'sidebar-1','description'=>'Erscheint neben dem Inhalt.','before_widget'=>'<section id="%1$s" class="widget %2$s">','after_widget'=>'</section>','before_title'=>'<h2 class="widget-title">','after_title'=>'</h2>']);
});
add_action('wp_enqueue_scripts',function(){ wp_enqueue_style('elvado-classic',get_stylesheet_uri(),[],wp_get_theme()->get('Version')); });

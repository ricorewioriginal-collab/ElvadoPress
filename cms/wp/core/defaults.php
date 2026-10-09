<?php
// Standard-Haken wie in WordPress (wp_head, wp_footer, the_content …).
function elvado_wp_add_default_filters(): void {
    // jQuery liegt lokal im CMS (kein CDN): Handle „jquery“ wie in WordPress
    wp_register_script('jquery-core','/cms/assets/vendor/jquery.min.js',[],'3.7.1');wp_register_script('jquery','',['jquery-core'],'3.7.1');
    // Inhalt: Absätze nur für Texte ohne HTML-Struktur; Beiträge/Seiten aus dem CMS sind bereits HTML.
    add_filter('the_content','do_blocks',9);
    add_filter('the_content',function($c){ $p=get_post();return !empty($GLOBALS['elvado_wp_raw_html'])||elvado_wp_is_cms_html($p)||str_contains((string)$c,'</p>')||str_contains((string)$c,'<div')?$c:wpautop((string)$c); },10);
    add_filter('the_excerpt',function($c){ return preg_match('/<(p|div|ul|ol|h[1-6])[\s>]/i',(string)$c)?$c:wpautop((string)$c); },10);   // Auszüge sind Klartext → Absatz wie in WordPress
    add_filter('widget_text','wpautop',10);
    foreach(['the_title','the_content','the_excerpt','comment_text'] as $f){ }
    // wp_head
    add_action('wp_head','_wp_render_title_tag',1);
    add_action('wp_head','elvado_wp_link_hash_script',1);
    add_action('wp_head','wp_enqueue_scripts_fire',1);
    add_action('wp_head','feed_links',2);
    add_action('wp_head','rel_canonical');
    add_action('wp_head','wp_print_styles',8);
    add_action('wp_head','wp_print_head_scripts',9);
    add_action('wp_head','wp_generator');
    add_action('wp_head','wp_custom_css_cb',101);
    add_action('wp_footer','wp_print_footer_scripts',20);
}
function wp_enqueue_scripts_fire() { static $done=false;if($done)return;$done=true;do_action('wp_enqueue_scripts'); }
/** Kern-Funktionen, die Editoren (Elementor) direkt an wp_head/wp_footer hängen. */
function wp_enqueue_scripts() { wp_enqueue_scripts_fire(); }
function wp_auth_check_html() {}
function wp_admin_bar_render() {}

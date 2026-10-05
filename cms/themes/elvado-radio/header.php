<?php if(!defined('ABSPATH'))exit; ?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#main">Zum Inhalt springen</a>
<header class="site-header"><div class="wrap header-inner">
  <a class="brand" href="<?php echo esc_url(home_url('/')); ?>" rel="home"><?php if(has_custom_logo()): echo wp_get_attachment_image((int)get_theme_mod('custom_logo'),'full'); endif; ?><span><?php bloginfo('name'); ?><?php $d=get_bloginfo('description'); if($d): ?><small><?php echo esc_html($d); ?></small><?php endif; ?></span></a>
  <nav class="main-navigation" aria-label="Hauptmenü"><?php wp_nav_menu(['theme_location'=>'primary','container'=>false,'fallback_cb'=>'wp_page_menu']); ?></nav>
  <div class="header-live" data-radio-live><i class="live-dot"></i><span data-np="line"></span></div>
</div></header>

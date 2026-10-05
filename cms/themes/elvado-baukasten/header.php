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
  <div class="site-branding">
    <?php if(has_custom_logo()) the_custom_logo(); ?>
    <div><p class="site-title"><a href="<?php echo esc_url(home_url('/')); ?>" rel="home"><?php bloginfo('name'); ?></a></p>
    <?php $d=get_bloginfo('description'); if($d): ?><p class="site-description"><?php echo esc_html($d); ?></p><?php endif; ?></div>
  </div>
  <nav class="main-navigation" aria-label="Hauptmenü"><?php wp_nav_menu(['theme_location'=>'primary','container'=>false,'fallback_cb'=>'wp_page_menu']); ?></nav>
</div></header>
<?php if(!elvado_bk_is_builder_page()): ?><div class="wrap"><div id="content" class="site-content<?php echo is_active_sidebar('sidebar-1')?'':' no-sidebar'; ?>"><?php endif; ?>

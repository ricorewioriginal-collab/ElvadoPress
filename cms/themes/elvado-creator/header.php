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
  <a class="brand" href="<?php echo esc_url(home_url('/')); ?>" rel="home"><?php if(has_custom_logo()): echo wp_get_attachment_image((int)get_theme_mod('custom_logo'),'full'); elseif(($av=(string)elvado_cr_cfg()['profile']['avatar'])!==''): ?><img src="<?php echo esc_url($av); ?>" alt=""><?php endif; ?><span><?php echo esc_html(elvado_cr_name()); ?></span></a>
  <nav class="main-navigation" aria-label="Hauptmenü"><?php wp_nav_menu(['theme_location'=>'primary','container'=>false,'fallback_cb'=>'elvado_cr_fallback_menu']); ?></nav>
  <?php if(elvado_cr_links_visible()): ?><a class="btn btn-ghost" href="<?php echo esc_url(home_url('/links/')); ?>">Links</a><?php endif; ?>
</div></header>

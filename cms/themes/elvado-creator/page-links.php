<?php if(!defined('ABSPATH'))exit;
/* Link-in-Bio-Seite (Adresse /links/): schmal, ohne Kopf, mit Profil, Plattformen und Knopfliste. */
$c=elvado_cr_cfg();$p=$c['profile'];
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class('link-page'); ?>>
<?php wp_body_open(); ?>
<main id="main" class="cr-lp">
  <?php if($p['avatar']!==''): ?><img class="cr-avatar" src="<?php echo esc_url($p['avatar']); ?>" alt="<?php echo esc_attr(elvado_cr_name()); ?>"><?php endif; ?>
  <h1 class="cr-name"><?php echo esc_html(elvado_cr_name()); ?></h1>
  <?php if($p['handle']!==''): ?><div class="cr-handle"><?php echo esc_html($p['handle']); ?></div><?php endif; ?>
  <?php if($p['tagline']!==''): ?><p class="cr-tagline"><?php echo esc_html($p['tagline']); ?></p><?php endif; ?>
  <?php echo elvado_cr_platforms_html(); ?>
  <?php echo elvado_cr_links_html(true); ?>
  <p class="cr-lp-foot"><a href="<?php echo esc_url(home_url('/')); ?>">Zur Website</a> · <button type="button" class="btn btn-ghost" data-share style="padding:.3em .9em;font-size:.8rem" hidden>Teilen</button></p>
</main>
<?php wp_footer(); ?>
</body>
</html>

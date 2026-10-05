<?php if(!defined('ABSPATH'))exit;
if(!elvado_bk_is_builder_page()){ include __DIR__.'/index.php';return; }
get_header(); ?>
<main id="main"><?php elvado_bk_render_front(); ?></main>
<?php get_footer(); ?>

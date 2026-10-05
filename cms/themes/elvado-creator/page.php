<?php if(!defined('ABSPATH'))exit; get_header(); ?>
<div class="wrap"><div id="content" class="site-content<?php echo is_active_sidebar("sidebar-1")?"":" no-sidebar"; ?>"><main id="main">
<?php while(have_posts()): the_post(); ?>
  <article id="post-<?php the_ID(); ?>" <?php post_class('entry'); ?>>
    <h1 class="entry-title"><?php the_title(); ?></h1>
    <div class="entry-content"><?php the_content(); ?></div>
  </article>
<?php endwhile; ?>
</main>
<?php get_sidebar(); ?></div></div>
<?php get_footer(); ?>

<?php if(!defined('ABSPATH'))exit; get_header(); ?>
<main id="main">
<?php while(have_posts()): the_post(); ?>
  <article id="post-<?php the_ID(); ?>" <?php post_class('entry'); ?>>
    <h1 class="entry-title"><?php the_title(); ?></h1>
    <div class="entry-content"><?php the_content(); ?></div>
  </article>
<?php endwhile; ?>
</main>
<?php get_sidebar(); get_footer(); ?>

<?php if(!defined('ABSPATH'))exit; get_header(); ?>
<div class="wrap"><div id="content" class="site-content<?php echo is_active_sidebar("sidebar-1")?"":" no-sidebar"; ?>"><main id="main">
<?php while(have_posts()): the_post(); ?>
  <article id="post-<?php the_ID(); ?>" <?php post_class('entry'); ?>>
    <h1 class="entry-title"><?php the_title(); ?></h1>
    <div class="entry-meta"><?php echo esc_html(get_the_date()); ?> · <?php echo esc_html(get_the_author()); ?><?php if(get_post_type()==='post'): ?> · <?php the_category(', '); ?><?php endif; ?></div>
    <?php if(has_post_thumbnail()): ?><div class="post-thumbnail"><?php the_post_thumbnail('large'); ?></div><?php endif; ?>
    <div class="entry-content"><?php the_content(); ?></div>
    <?php the_tags('<p class="tags">Schlagwörter: ',', ','</p>'); ?>
  </article>
  <?php the_post_navigation(); ?>
  <?php if(comments_open()||get_comments_number()) comments_template(); ?>
<?php endwhile; ?>
</main>
<?php get_sidebar(); ?></div></div>
<?php get_footer(); ?>

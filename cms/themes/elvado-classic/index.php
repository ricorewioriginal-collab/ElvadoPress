<?php if(!defined('ABSPATH'))exit; get_header(); ?>
<main id="main">
<?php if(is_home()&&!is_front_page()): ?><?php endif; ?>
<?php if(have_posts()): ?>
  <?php if(is_archive()||is_search()): ?><header class="page-header"><h1><?php echo is_search()?esc_html(sprintf('Suchergebnisse für „%s“',get_search_query(false))):wp_kses_post(get_the_archive_title()); ?></h1><?php the_archive_description('<div class="archive-description">','</div>'); ?></header><?php endif; ?>
  <?php while(have_posts()): the_post(); ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class('post-card'); ?>>
      <?php if(has_post_thumbnail()): ?><a class="post-thumbnail" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1"><?php the_post_thumbnail('large'); ?></a><?php endif; ?>
      <h2 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
      <div class="entry-meta"><?php echo esc_html(get_the_date()); ?><?php if(get_post_type()==='post'): ?> · <?php the_category(', '); ?><?php endif; ?></div>
      <div class="entry-summary"><?php the_excerpt(); ?></div>
      <a class="read-more" href="<?php the_permalink(); ?>">Weiterlesen &raquo;</a>
    </article>
  <?php endwhile; ?>
  <?php the_posts_pagination(['prev_text'=>'&laquo; Zurück','next_text'=>'Weiter &raquo;']); ?>
<?php else: ?>
  <article class="entry"><h1 class="entry-title">Nichts gefunden</h1><p>Hier gibt es noch keine Inhalte. Versuche eine Suche:</p><?php get_search_form(); ?></article>
<?php endif; ?>
</main>
<?php get_sidebar(); get_footer(); ?>

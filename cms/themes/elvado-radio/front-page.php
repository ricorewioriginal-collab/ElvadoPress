<?php if(!defined('ABSPATH'))exit;
get_header();
$c=elvado_rd_cfg();$s=elvado_rd_station();$show=$c['show'];$np=$s?elvado_rd_now($s):null;
$title=(string)get_theme_mod('rd_title','')?:get_bloginfo('name');$text=(string)get_theme_mod('rd_text','')?:($s&&$s['tagline']!==''?$s['tagline']:get_bloginfo('description'));
?>
<main id="main" class="wrap">
  <section class="rd-hero">
    <div><div class="rd-eyebrow"><?php echo esc_html((string)get_theme_mod('rd_eyebrow','Live Radio')); ?></div>
      <h1><?php echo esc_html($title); ?></h1><?php if($text!==''): ?><p><?php echo esc_html($text); ?></p><?php endif; ?>
      <?php echo elvado_rd_alexa_line(); ?>
      <?php if($c['links']): ?><div class="link-pills"><?php foreach($c['links'] as $l): ?><a class="btn btn-ghost" href="<?php echo esc_url($l['url']); ?>" rel="noopener"><?php echo esc_html($l['label']); ?></a><?php endforeach; ?></div><?php endif; ?></div>
    <div><?php echo $s?elvado_rd_np_card($s,$np):'<div class="card"><b>Noch kein Sender eingerichtet.</b><p style="margin:.4em 0 0;color:var(--muted)">Im CMS unter „Radio“ Sender und Datenquelle eintragen.</p></div>'; ?></div>
  </section>
  <?php if($s&&(!empty($show['history'])||!empty($show['schedule']))): ?>
  <div class="rd-cols">
    <?php if(!empty($show['history'])): ?><section><h2 class="section-title">Zuletzt gespielt</h2><?php echo elvado_rd_history(); ?></section><?php endif; ?>
    <?php if(!empty($show['schedule'])): ?><section><h2 class="section-title">Sendeplan heute</h2><?php echo elvado_rd_schedule('',true); ?><p><a href="#sendeplan" onclick="document.getElementById('rd-week').hidden=false;this.hidden=true;return false" id="sendeplan">Ganze Woche anzeigen</a></p><div id="rd-week" hidden><?php echo elvado_rd_schedule(); ?></div></section><?php endif; ?>
  </div>
  <?php endif; ?>
  <?php if(!empty($show['stations'])&&count($c['stations'])>1): ?><section><h2 class="section-title">Unsere Sender</h2><?php echo elvado_rd_stations(); ?></section><?php endif; ?>
  <?php if(!empty($show['news'])): $q=new WP_Query(['post_type'=>'post','posts_per_page'=>max(1,min(12,(int)get_theme_mod('rd_news_count',3))),'ignore_sticky_posts'=>true]); if($q->have_posts()): ?>
  <section><h2 class="section-title">Aktuelles</h2><div class="news-grid">
    <?php while($q->have_posts()): $q->the_post(); ?>
      <article class="post-card"><?php if(has_post_thumbnail()): ?><a class="post-thumbnail" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1"><?php the_post_thumbnail('medium_large'); ?></a><?php endif; ?>
        <h3 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3><div class="entry-meta"><?php echo esc_html(get_the_date()); ?></div><div class="entry-summary"><?php the_excerpt(); ?></div></article>
    <?php endwhile; wp_reset_postdata(); ?>
  </div></section><?php endif; endif; ?>
  <?php if(is_page()): while(have_posts()): the_post(); $ct=trim((string)get_the_content()); if($ct!==''): ?><section class="card" style="margin-top:30px"><div class="entry-content"><?php the_content(); ?></div></section><?php endif; endwhile; endif; ?>
</main>
<?php get_footer(); ?>

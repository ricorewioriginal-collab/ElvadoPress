<?php if(!defined('ABSPATH'))exit; $b=elvado_bd_cfg(); ?>
<footer class="site-footer"><div class="wrap">
  <p class="bd-foot-name"><?php echo esc_html(elvado_bd_name()); ?></p>
  <?php if($b['links']): ?><ul class="bd-social"><?php foreach($b['links'] as $l): ?><li><a href="<?php echo esc_url($l['url']); ?>" rel="me noopener"><?php echo esc_html(elvado_bd_link_label($l)); ?></a></li><?php endforeach; ?></ul><?php endif; ?>
  <?php if(has_nav_menu('footer')): ?><nav class="footer-navigation" aria-label="Fußmenü"><?php wp_nav_menu(['theme_location'=>'footer','container'=>false,'depth'=>1]); ?></nav><?php endif; ?>
  <div class="bd-copy">&copy; <?php echo esc_html(wp_date('Y')); ?> <?php echo esc_html(elvado_bd_name()); ?></div>
</div></footer>
<?php wp_footer(); ?>
</body>
</html>

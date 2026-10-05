<?php if(!defined('ABSPATH'))exit; ?>
<footer class="site-footer"><div class="wrap">
  <?php if(has_nav_menu('footer')): ?><nav class="footer-navigation" aria-label="Fußmenü"><?php wp_nav_menu(['theme_location'=>'footer','container'=>false,'depth'=>1]); ?></nav><?php endif; ?>
  <div>&copy; <?php echo esc_html(wp_date('Y')); ?> <?php echo esc_html(elvado_cr_name()); ?></div>
</div></footer>
<?php elvado_cr_mobile_nav(); wp_footer(); ?>
</body>
</html>

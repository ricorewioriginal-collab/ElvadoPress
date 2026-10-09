<?php if(!defined('ABSPATH'))exit; ?>
</div></div>
<footer class="site-footer"><div class="wrap">
  <?php if(has_nav_menu('footer')): ?><nav class="footer-navigation" aria-label="Fußmenü"><?php wp_nav_menu(['theme_location'=>'footer','container'=>false,'depth'=>1]); ?></nav><?php endif; ?>
  <div>&copy; <?php echo esc_html(wp_date('Y')); ?> <?php bloginfo('name'); ?></div>
</div></footer>
<?php wp_footer(); ?>
</body>
</html>

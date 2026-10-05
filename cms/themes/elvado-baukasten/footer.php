<?php if(!defined('ABSPATH'))exit; ?>
<?php if(!elvado_bk_is_builder_page()): ?></div></div><?php endif; ?>
<footer class="site-footer"><div class="wrap">
  <?php if(has_nav_menu('footer')): ?><nav class="footer-navigation" aria-label="Fußmenü"><?php wp_nav_menu(['theme_location'=>'footer','container'=>false,'depth'=>1]); ?></nav><?php endif; ?>
  <div><?php $ft=(string)elvado_bk_mod('footer_text'); echo $ft!==''?esc_html($ft):'&copy; '.esc_html(wp_date('Y')).' '.esc_html(get_bloginfo('name')); ?></div>
</div></footer>
<?php wp_footer(); ?>
</body>
</html>

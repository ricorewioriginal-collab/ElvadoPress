<?php if(!defined('ABSPATH'))exit;
/* Die Link-Seite „/links/“ funktioniert auch ohne angelegte CMS-Seite: Adresse erkennen und die Link-in-Bio-Vorlage zeigen. */
if(elvado_cr_is_links_request()){ status_header(200);include __DIR__.'/page-links.php';return; }
get_header(); ?>
<div class="wrap"><div id="content" class="site-content no-sidebar"><main id="main"><article class="entry"><h1 class="entry-title">Seite nicht gefunden</h1><p>Diese Adresse gibt es nicht (mehr). Vielleicht hilft die Suche:</p><?php get_search_form(); ?><p><a class="btn" href="<?php echo esc_url(home_url('/')); ?>">Zur Startseite</a></p></article></main></div></div>
<?php get_footer(); ?>

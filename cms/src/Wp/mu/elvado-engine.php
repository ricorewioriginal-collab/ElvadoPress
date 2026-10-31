<?php
/**
 * Plugin Name: ElvadoPress Engine
 * Description: Engine-Schicht von ElvadoPress (Absturzschutz, kein Update-/Telemetrie-Verkehr). Wird immer zuerst geladen; kein Plugin und nicht abschaltbar.
 */
if (!defined('ABSPATH')) {
    exit;
}
if (defined('ELVADO_ENGINE_SAFE') && ELVADO_ENGINE_SAFE) {
    // Abgesicherter Modus: keine Plugins, kein Theme (WordPress-Funktionen der Engine laufen weiter)
    // (benannte Funktionen, damit Bridge::recover() die Sperren für das Rückgängigmachen kurz aufheben kann)
    function elvado_engine_safe_theme() { return 'elvado-safe-mode'; }
    add_filter('pre_option_active_plugins', '__return_empty_array');
    add_filter('pre_option_active_sitewide_plugins', '__return_empty_array');
    add_filter('pre_option_template', 'elvado_engine_safe_theme');
    add_filter('pre_option_stylesheet', 'elvado_engine_safe_theme');
} elseif (defined('ELVADO_USER_MU_DIR') && is_dir(ELVADO_USER_MU_DIR)) {
    // Must-Use-Plugins des Betreibers (cms/wp-content/mu-plugins) laden wie bei WordPress: alphabetisch
    $__elvado_mu = glob(rtrim(ELVADO_USER_MU_DIR, '/') . '/*.php') ?: [];
    sort($__elvado_mu);
    foreach ($__elvado_mu as $__f) {
        if (is_file($__f)) {
            include_once $__f;
        }
    }
    unset($__elvado_mu, $__f);
}
// Keine Hintergrundabfragen an wordpress.org: Updates steuert ElvadoPress selbst
add_filter('pre_site_transient_update_core', '__return_null');
remove_action('admin_init', '_maybe_update_core');
remove_action('admin_init', '_maybe_update_plugins');
remove_action('admin_init', '_maybe_update_themes');
// Beitragsbilder aus ElvadoPress (Adresse statt Mediathek-Anhang, Meta _elvado_image_url): Themes zeigen sie wie ein normales Beitragsbild
add_filter('has_post_thumbnail', function ($has, $post = null) {
    return $has || ($post !== null && (string)get_post_meta(is_object($post) ? (int)$post->ID : (int)$post, '_elvado_image_url', true) !== '');
}, 10, 2);
add_filter('post_thumbnail_html', function ($html, $postId, $thumbId, $size, $attr) {
    if ($html !== '' || $thumbId) {
        return $html;
    }
    $url = (string)get_post_meta((int)$postId, '_elvado_image_url', true);
    if ($url === '' || !preg_match('~^(https://|/)~', $url)) {
        return $html;
    }
    $cls = is_array($attr) && isset($attr['class']) ? (string)$attr['class'] : 'wp-post-image';
    return '<img src="' . esc_url($url) . '" class="' . esc_attr($cls) . '" alt="' . esc_attr(get_the_title((int)$postId)) . '" loading="lazy" decoding="async">';
}, 10, 5);
// ElvadoPress-Themes (cms/themes: Classic, Baukasten, Band, Creator) stehen im echten WordPress wie installierte Themes zur Verfügung
if (defined('ELVADO_THEMES_DIR') && is_dir(ELVADO_THEMES_DIR)) {
    register_theme_directory(ELVADO_THEMES_DIR);
    add_filter('theme_root_uri', function ($uri, $siteurl = '', $stylesheet = '') {
        $root = rtrim(str_replace('\\', '/', (string)ELVADO_THEMES_DIR), '/');
        return rtrim(str_replace('\\', '/', (string)$uri), '/') === $root ? home_url('/cms/themes') : $uri;
    }, 10, 3);
}
// Mehrsprachige Website (Sprache aus dem Pfad /en/…, erkannt in cms/wp-engine-front.php): Sprache, Inhalte, Adressen, hreflang, Umschalter
if (!empty($GLOBALS['elvado_ml']) && defined('ELVADO_CMS_DIR') && is_file(ELVADO_CMS_DIR . '/lib/multilang.php')) {
    require_once ELVADO_CMS_DIR . '/lib/multilang.php';
    elvado_ml_wp_hooks();
}
// Keine Emoji-Grafiken vom Server s.w.org (externer Dienst, Datenschutz)
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('wp_enqueue_scripts', 'wp_enqueue_emoji_styles');
remove_filter('the_content_feed', 'wp_staticize_emoji');
remove_filter('comment_text_rss', 'wp_staticize_emoji');
remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

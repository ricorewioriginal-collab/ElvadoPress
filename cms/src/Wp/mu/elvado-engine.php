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

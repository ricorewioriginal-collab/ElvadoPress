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
// Kommentare von Besuchern der WordPress-Website gehören zu ElvadoPress (comments.json): dort freigeben, beantworten, löschen (Verwaltung → Kommentare).
// Der Kommentar bleibt in WordPress (wartend oder freigegeben, je nach Einstellung „Kommentare vor Veröffentlichung prüfen“) und wird über _elvado_comment_id zugeordnet.
add_action('wp_insert_comment', function ($id, $comment) {
    if (!empty($GLOBALS['elvado_livesync_comment']) || !defined('ELVADO_WP_DATA') || !defined('ELVADO_CMS_DIR') || !($comment instanceof WP_Comment)) {
        return;
    }
    $src = (string)get_post_meta((int)$comment->comment_post_ID, '_elvado_source_id', true);
    if (!preg_match('/^post:(\d+)$/', $src, $m) || !in_array((string)$comment->comment_type, ['', 'comment'], true)) {
        return;
    }
    $data = dirname(ELVADO_WP_DATA);
    require_once ELVADO_CMS_DIR . '/lib/publish.php';
    $site = elvado_read_json($data . '/site.json', []);
    $approve = array_key_exists('require_approval', (array)($site['comments'] ?? [])) && empty($site['comments']['require_approval']);
    $file = $data . '/comments.json';
    $h = @fopen($file . '.lock', 'c');
    if ($h) {
        flock($h, LOCK_EX);
    }
    try {
        $all = elvado_read_json($file, []);
        $next = 1;
        $parent = 0;
        foreach ($all as $c) {
            $next = max($next, (int)($c['id'] ?? 0) + 1);
        }
        if ((int)$comment->comment_parent > 0) {
            $p = (int)get_comment_meta((int)$comment->comment_parent, '_elvado_comment_id', true);
            $parent = elvado_comment_top_parent($all, $p, (int)$m[1]) ?? 0;
        }
        $name = mb_substr(trim((string)$comment->comment_author), 0, 80);
        $text = mb_substr(trim(wp_strip_all_tags((string)$comment->comment_content)), 0, 2000);
        $all[] = ['id' => $next, 'article_id' => (int)$m[1], 'parent_id' => $parent, 'name' => $name !== '' ? $name : 'Gast', 'text' => $text, 'is_staff' => false,
            'status' => $approve ? 'approved' : 'pending', 'created_at' => date('Y-m-d H:i:s', strtotime(get_date_from_gmt((string)$comment->comment_date_gmt)))];
        elvado_write_atomic($file, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    } finally {
        if ($h) {
            flock($h, LOCK_UN);
            fclose($h);
        }
    }
    add_comment_meta((int)$id, '_elvado_comment_id', $next, true);
    wp_set_comment_status((int)$id, $approve ? 'approve' : 'hold');
    $title = (string)get_the_title((int)$comment->comment_post_ID);
    elvado_add_notification($data . '/notifications.json', ['type' => 'comment', 'article_id' => (int)$m[1], 'article_title' => $title, 'target_author' => '', 'comment_name' => $name, 'comment_excerpt' => mb_substr($text, 0, 140)]);
}, 10, 2);
// Benachrichtigung über neue Kommentare macht ElvadoPress (Glocke in der Verwaltung) – keine zusätzlichen WordPress-Mails für ElvadoPress-Beiträge
$elvadoOwnComment = static function ($notify, $commentId) {
    $c = get_comment((int)$commentId);
    return $c && get_post_meta((int)$c->comment_post_ID, '_elvado_source_id', true) !== '' ? false : $notify;
};
add_filter('notify_moderator', $elvadoOwnComment, 10, 2);
add_filter('notify_post_author', $elvadoOwnComment, 10, 2);
// ElvadoPress-Shortcodes ([umfrage], [faq], [karte], [kontakt], [lovable] …) und das Chat-Fenster des KI-Assistenten (cms/lib/wp-shortcodes.php)
if (defined('ELVADO_CMS_DIR') && is_file(ELVADO_CMS_DIR . '/lib/wp-shortcodes.php')) {
    require_once ELVADO_CMS_DIR . '/lib/wp-shortcodes.php';
    add_action('init', 'elvado_sc_register_builtin');
    add_action('wp_footer', 'elvado_wp_assistant_widget', 90);
}
// Logo aus dem ElvadoPress-Branding (Anhang von LiveSync): gilt als Theme-Logo, solange im Customizer keines gewählt ist; Adresse zeigt auf die ElvadoPress-Datei
add_filter('theme_mod_custom_logo', static function ($v) {
    return $v ?: (int)get_option('elvado_logo_id', 0);
});
add_filter('wp_get_attachment_url', static function ($url, $id) {
    $u = (string)get_post_meta((int)$id, '_elvado_image_url', true);
    return $u === '' ? $url : (str_starts_with($u, '/') ? home_url($u) : $u);
}, 10, 2);
// Suchmaschinen-Felder aus dem ElvadoPress-Editor (LiveSync): noindex, kanonische Adresse (z. B. Zweitveröffentlichung), eigener Titel
add_filter('wp_robots', static function (array $robots): array {
    if (is_singular() && get_post_meta((int)get_queried_object_id(), '_elvado_seo_noindex', true) === '1') {
        $robots['noindex'] = true;
        $robots['follow'] = true;
        unset($robots['max-image-preview']);
    }
    return $robots;
});
add_filter('get_canonical_url', static function ($url, $post) {
    $c = (string)get_post_meta((int)$post->ID, '_elvado_canonical', true);
    return preg_match('~^https?://~i', $c) ? $c : $url;
}, 10, 2);
add_filter('pre_get_document_title', static function ($title) {
    $t = is_singular() ? (string)get_post_meta((int)get_queried_object_id(), '_elvado_seo_title', true) : '';
    return $t !== '' ? $t : $title;
});
// KI-Zentrale: Schlüssel der WordPress-Verbinder (connectors_ai_*_api_key) kommen aus der KI-Zentrale von ElvadoPress (keine zweite Kopie in WordPress)
if (defined('ELVADO_CMS_DIR') && is_file(ELVADO_CMS_DIR . '/lib/wp-ai-connectors.php')) {
    require_once ELVADO_CMS_DIR . '/lib/wp-ai-connectors.php';
}

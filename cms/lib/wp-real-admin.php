<?php
// Verwaltung auf echtem WordPress (Stufe E4): Liefert echtes WordPress die Website aus (System → WordPress-Engine, Schalter „Website mit WordPress ausliefern“),
// laufen die WordPress-Bereiche der Verwaltung (Plugins, Themes, Einstellungen, Lesen, Link-Struktur) direkt gegen den echten Core und seine Datenbank.
// cms/api.php startet dafür statt der bisherigen Schicht (cms/wp) echtes WordPress (elvado_wpra_boot) und nutzt die vorhandenen Helfer
// (wp/installer.php, wp/settings-api.php, wp/links-api.php), die nur WordPress-Funktionen verwenden. Die wenigen Helfer, die es nur in der bisherigen Schicht gab,
// stellt diese Datei für echtes WordPress bereit. Nicht für Sandbox, Demo und weitere Websites (Multisite) – dort bleibt alles wie bisher.
declare(strict_types=1);

/** Aktionen, die bei aktiver Website-Auslieferung durch WordPress gegen den echten Core laufen. */
const ELVADO_WPRA_ACTIONS = ['wp_plugins', 'wp_plugin_search', 'wp_plugin_install', 'wp_plugin_upload', 'wp_plugin_activate', 'wp_plugin_deactivate', 'wp_plugin_delete',
    'wp_themes', 'wp_theme_search', 'wp_theme_install', 'wp_theme_upload', 'wp_theme_unhide', 'wp_theme_activate', 'wp_theme_delete',
    'wp_settings', 'wp_settings_save', 'wp_reading', 'wp_reading_save', 'wp_permalinks', 'wp_permalinks_save', 'wp_news_links', 'wp_updates', 'wp_translations'];

/** Gilt die Verwaltung auf echtem WordPress für diese Anfrage? */
function elvado_wpra_applies(string $action, string $cmsDir): bool
{
    if (!in_array($action, ELVADO_WPRA_ACTIONS, true) || !empty($_GET['sandbox']) || defined('ELVADO_WP_SANDBOX') || is_file($cmsDir . '/lib/demo.json')
        || is_file($cmsDir . '/data/sites.json') && (string)($_GET['site'] ?? '') !== '') {
        return false;
    }
    if (function_exists('elvado_wp_boot')) {   // die bisherige Schicht ist schon geladen: beides geht nicht in einem Prozess
        return false;
    }
    try {
        require_once $cmsDir . '/lib/wpengine.php';
        $e = elvado_wpe();
        return $e->front() && $e->corePath() !== null;
    } catch (Throwable) {
        return false;
    }
}

/** Vor dem Start: Engine und Optionen setzen. Den Start selbst (cms/wp-engine-boot.php) bindet cms/api.php im globalen Gültigkeitsbereich ein, wie es WordPress verlangt. */
function elvado_wpra_prepare(): void
{
    $e = elvado_wpe();
    $GLOBALS['elvado_wpe_engine'] = $e;
    $GLOBALS['elvado_wpe_db'] = new \Elvado\Wp\DbConfig($e);
    $GLOBALS['elvado_wpe_opts'] = ['themes' => true];   // mit Theme: Theme-Funktionen und Menü-Orte sind bekannt
    if (!defined('ELVADO_WP_REAL')) {
        define('ELVADO_WP_REAL', true);
    }
}
/** Nach dem Start: Verwaltungs-Bausteine von WordPress und die Helfer für die Verwaltung laden. */
function elvado_wpra_after(): void
{
    elvado_wpe_after_boot($GLOBALS['elvado_wpe_engine']);
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    require_once ABSPATH . 'wp-admin/includes/theme.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once __DIR__ . '/wp-real-compat.php';
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int)($admins[0] ?? 0));   // Rechteprüfungen von WordPress und Plugins (die Verwaltung hat den Superadmin bereits geprüft)
    if (!defined('ELVADO_WP_VERSION')) {
        define('ELVADO_WP_VERSION', (string)$GLOBALS['wp_version']);
    }
    // Link-Struktur geändert → Umschreibregeln von WordPress neu erzeugen (einmal am Ende der Anfrage)
    foreach (['permalink_structure', 'category_base', 'tag_base'] as $o) {
        add_action('update_option_' . $o, 'elvado_wpra_flush_later');
        add_action('add_option_' . $o, 'elvado_wpra_flush_later');
    }
}
function elvado_wpra_flush_later(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    register_shutdown_function(static function (): void {
        global $wp_rewrite;
        $wp_rewrite->init();
        flush_rewrite_rules(false);
    });
}

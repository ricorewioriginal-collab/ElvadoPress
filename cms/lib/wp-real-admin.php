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
    'wp_settings', 'wp_settings_save', 'wp_reading', 'wp_reading_save', 'wp_permalinks', 'wp_permalinks_save', 'wp_news_links', 'wp_updates', 'wp_translations',
    'wp_theme_preview', 'wp_theme_customize', 'wp_theme_customize_draft', 'wp_theme_customize_save', 'wp_theme_customize_changeset',
    'wp_bk_get', 'wp_bk_save', 'wp_bk_draft', 'wp_bk_publish', 'wp_bk_discard', 'wp_bk_rollback', 'wp_bk_revisions'];

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

/** Theme-Ordner vor dem Start von WordPress (Themes aus wp-content/themes und cms/themes). */
function elvado_wpra_theme_dir(string $cmsDir, string $slug): string
{
    foreach ([$cmsDir . '/wp-content/themes/', $cmsDir . '/themes/'] as $root) {
        if (preg_match('/^[a-z0-9_-]{1,80}$/', $slug) && is_file($root . $slug . '/style.css')) {
            return $root . $slug;
        }
    }
    return '';
}
/** Theme für diese Anfrage einschalten, bevor WordPress startet (vorbelegte Filter, wie es WordPress für frühe Hooks vorsieht): Customizer, Baukasten, Vorschau.
    Die aktive Einstellung bleibt unverändert (elvado_wpc_real_option liest sie ohne diese Filter). false = Theme unbekannt. */
function elvado_wpra_use_theme(string $cmsDir, string $slug): bool
{
    $dir = elvado_wpra_theme_dir($cmsDir, $slug);
    if ($dir === '') {
        return false;
    }
    $parent = $slug;
    if (preg_match('/^[ \t\/*#@]*Template:\s*([a-z0-9_-]{1,80})\s*$/mi', (string)file_get_contents($dir . '/style.css', false, null, 0, 8192), $m)) {
        $parent = $m[1];
    }
    $ss = static fn() => $slug;
    $tp = static fn() => $parent;
    $GLOBALS['wp_filter']['pre_option_stylesheet'][10]['elvado_pre'] = ['function' => $ss, 'accepted_args' => 1];
    $GLOBALS['wp_filter']['pre_option_template'][10]['elvado_pre'] = ['function' => $tp, 'accepted_args' => 1];
    $GLOBALS['elvado_wpc_pre'] = ['stylesheet' => $ss, 'template' => $tp];
    return true;
}
/** Signierter Vorschau-Schlüssel für ein Theme (Website-Auslieferung durch WordPress): slug.ablauf.signatur mit dem Vorschau-Geheimnis von ElvadoPress. */
function elvado_wpra_preview_sig(string $dataDir, string $slug, int $exp): string
{
    require_once __DIR__ . '/components.php';
    return hash_hmac('sha256', 'theme|' . $slug . '|' . $exp, elvado_components_preview_secret($dataDir));
}
function elvado_wpra_preview_ok(string $dataDir, string $tok): string
{
    if (!preg_match('/^([a-z0-9_-]{1,80})\.(\d{9,11})\.([a-f0-9]{64})$/', $tok, $m) || (int)$m[2] <= time()) {
        return '';
    }
    return hash_equals(elvado_wpra_preview_sig($dataDir, $m[1], (int)$m[2]), $m[3]) ? $m[1] : '';
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

<?php
// Verwaltung auf echtem WordPress (Stufe E4): Liefert echtes WordPress die Website aus (System → WordPress-Engine, Schalter „Website mit WordPress ausliefern“),
// laufen die WordPress-Bereiche der Verwaltung (Plugins, Themes, Einstellungen, Lesen, Link-Struktur) direkt gegen den echten Core und seine Datenbank.
// cms/api.php startet dafür statt der bisherigen Schicht (cms/wp) echtes WordPress (elvado_wpra_boot) und nutzt die vorhandenen Helfer
// (wp/installer.php, wp/settings-api.php, wp/links-api.php), die nur WordPress-Funktionen verwenden. Die wenigen Helfer, die es nur in der bisherigen Schicht gab,
// stellt diese Datei für echtes WordPress bereit. Nicht für Sandbox, Demo und weitere Websites (Multisite) – dort bleibt alles wie bisher.
declare(strict_types=1);

/** Aktionen der WordPress-Bereiche (alle wp_*-Aktionen laufen gegen den echten Core; die Liste dient der Übersicht und den Tests). */
const ELVADO_WPRA_ACTIONS = ['wp_plugins', 'wp_plugin_search', 'wp_plugin_install', 'wp_plugin_upload', 'wp_plugin_activate', 'wp_plugin_deactivate', 'wp_plugin_delete',
    'wp_themes', 'wp_theme_search', 'wp_theme_install', 'wp_theme_upload', 'wp_theme_unhide', 'wp_theme_activate', 'wp_theme_delete',
    'wp_settings', 'wp_settings_save', 'wp_reading', 'wp_reading_save', 'wp_permalinks', 'wp_permalinks_save', 'wp_news_links', 'wp_updates', 'wp_translations',
    'wp_theme_preview', 'wp_theme_customize', 'wp_theme_customize_draft', 'wp_theme_customize_save', 'wp_theme_customize_changeset',
    'wp_bk_get', 'wp_bk_save', 'wp_bk_draft', 'wp_bk_publish', 'wp_bk_discard', 'wp_bk_rollback', 'wp_bk_revisions',
    'wp_session_open', 'wp_admin_menu', 'wp_admin_notices', 'wp_admin_page', 'wp_admin_ajax', 'wp_admin_rest', 'wp_core_status', 'wp_core_install',
    'wp_content_list', 'wp_bridge_set', 'wp_link_targets', 'wp_page_create', 'wp_update_apply', 'wp_autoupdate_get', 'wp_autoupdate_set', 'wp_autoupdate_run', 'wp_autoupdate_tick',
    'wp_autoupdate_rollback', 'wp_version_status', 'wp_version_update', 'wp_version_rollback', 'wp_theme_deactivate'];

/** Gilt die Verwaltung auf echtem WordPress für diese Anfrage? */
function elvado_wpra_applies(string $action, string $cmsDir): bool
{
    if (!str_starts_with($action, 'wp_') || (!empty($_GET['sandbox']) && empty($GLOBALS['elvado_wpe_db_override']))) {
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

/** Einstellungen für WordPress vormerken, wenn WordPress gerade nicht läuft (Einrichtung, Import, Demo): Ablage cms/data/.wp/options.json,
    die die Engine beim nächsten Seitenaufruf übernimmt (elvado_wpe_adopt_site, nur Geändertes). $front: Theme-Auswahl gilt (stylesheet wird übernommen). */
function elvado_wpra_inbox_set(string $dataDir, array $opts, bool $front = true): bool
{
    $dir = rtrim($dataDir, '/') . '/.wp';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return false;
    }
    $f = $dir . '/options.json';
    $h = @fopen($f . '.lock', 'c');
    if ($h) {
        flock($h, LOCK_EX);
    }
    try {
        $all = is_file($f) ? json_decode((string)file_get_contents($f), true) : [];
        $all = is_array($all) ? $all : [];
        foreach ($opts as $k => $v) {
            $all[(string)$k] = ['v' => serialize($v), 'a' => 'yes'];
        }
        $t = $f . '.' . bin2hex(random_bytes(3)) . '.tmp';
        $ok = @file_put_contents($t, json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false && @rename($t, $f);
    } finally {
        if ($h) {
            flock($h, LOCK_UN);
            fclose($h);
        }
    }
    if ($front) {
        @file_put_contents($dir . '/front-on', gmdate('c'));
    }
    return $ok;
}
/** Option der Live-Website direkt aus der WordPress-Datenbank (ohne WordPress zu starten), z. B. das aktive Theme; null = nicht verfügbar. */
function elvado_wpra_live_option(string $cmsDir, string $name): ?string
{
    try {
        if (!elvado_wpra_front_on($cmsDir)) {
            return null;
        }
        require_once __DIR__ . '/wp-real-sandbox.php';
        $live = (new \Elvado\Wp\DbConfig(elvado_wpe()))->get();
        if (!$live) {
            return null;
        }
        $c = elvado_wprs_db($live, dirname(elvado_wpe()->stateDir()), false);
        $st = $c['pdo']->prepare('SELECT option_value FROM ' . $c['prefix'] . 'options WHERE option_name = ?');
        $st->execute([$name]);
        $v = $st->fetchColumn();
        return $v === false ? null : (string)$v;
    } catch (Throwable) {
        return null;
    }
}
/** Liefert echtes WordPress die Website aus (Engine aktiv, Schalter an, Core vorhanden)? */
function elvado_wpra_front_on(string $cmsDir): bool
{
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

/** Einmal-Schlüssel für die Anmeldung in der echten WordPress-Verwaltung (wp-admin) aus ElvadoPress heraus: 2 Minuten gültig, einmal nutzbar. */
function elvado_wpra_sso_dir(string $dataDir): string
{
    return rtrim($dataDir, '/') . '/.wp-engine/sso';
}
function elvado_wpra_sso_issue(string $dataDir, string $user): string
{
    $dir = elvado_wpra_sso_dir($dataDir);
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    foreach (glob($dir . '/*.json') ?: [] as $f) {   // abgelaufene aufräumen
        if (filemtime($f) < time() - 300) {
            @unlink($f);
        }
    }
    $tok = bin2hex(random_bytes(24));
    file_put_contents($dir . '/' . hash('sha256', $tok) . '.json', json_encode(['user' => $user, 'exp' => time() + 120]), LOCK_EX);
    @chmod($dir . '/' . hash('sha256', $tok) . '.json', 0600);
    return $tok;
}
/** Benutzername oder '' (ungültig, abgelaufen oder schon benutzt). */
function elvado_wpra_sso_consume(string $dataDir, string $tok): string
{
    if (!preg_match('/^[a-f0-9]{48}$/', $tok)) {
        return '';
    }
    $f = elvado_wpra_sso_dir($dataDir) . '/' . hash('sha256', $tok) . '.json';
    $d = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
    @unlink($f);
    return is_array($d) && (int)($d['exp'] ?? 0) >= time() ? (string)($d['user'] ?? '') : '';
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
    if (function_exists('elvado_np_do')) {
        elvado_np_do('wp_ready');   // native Plugins registrieren ihre WordPress-Hooks (z. B. Seiten-Cache leeren bei Theme-Wechsel)
    }
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
    // Umschreibregeln beim nächsten Seitenaufruf neu erzeugen (dann mit den neuen Basen der Kategorien/Schlagwörter, wie WordPress selbst)
    register_shutdown_function(static fn() => delete_option('rewrite_rules'));
}

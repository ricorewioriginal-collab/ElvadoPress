<?php
// Helfer für die Verwaltung auf echtem WordPress (siehe lib/wp-real-admin.php): Gegenstücke zu Funktionen, die es bisher nur in der WordPress-Nachbildung (cms/wp/core) gab.
// Wird nur geladen, nachdem echtes WordPress gestartet ist – nie zusammen mit der Nachbildung.
declare(strict_types=1);

// ───────── Helfer, die die Verwaltungs-Bausteine von der bisherigen Schicht erwarten ─────────

/** Platzhalter der Link-Struktur, die WordPress versteht. */
if (!defined('ELVADO_WP_LINK_TOKENS')) {
    define('ELVADO_WP_LINK_TOKENS', ['%year%' => 'year', '%monthnum%' => 'monthnum', '%day%' => 'day', '%hour%' => 'hour', '%minute%' => 'minute', '%second%' => 'second',
        '%post_id%' => 'post_id', '%postname%' => 'postname', '%category%' => 'category', '%author%' => 'author']);
}
/** Einstellungen, deren Wert ElvadoPress führt (site.json bzw. Betrieb) – beim Speichern landen sie dort und zusätzlich in WordPress. */
if (!defined('ELVADO_WP_BRIDGE_OPTS')) {
    define('ELVADO_WP_BRIDGE_OPTS', ['blogname' => ['portal', 'site_name', 80], 'blogdescription' => ['portal', 'tagline', 200], 'admin_email' => ['legal', 'email', 200]]);
    define('ELVADO_WP_BRIDGE_TYPED', ['default_comment_status' => 1, 'comment_moderation' => 1, 'posts_per_rss' => 1, 'blog_public' => 1]);
    define('ELVADO_WP_BRIDGE_SYS', ['timezone_string' => 'timezone', 'WPLANG' => 'language']);
}

function get_option_active_plugins(): array
{
    return array_values(array_filter(array_map('strval', (array)get_option('active_plugins', []))));
}
/** Theme-Ordner: Themes aus wp-content/themes und die mitgelieferten ElvadoPress-Themes (cms/themes). */
function elvado_wp_theme_roots(): array
{
    $h = rtrim((string)home_url(), '/');
    return [['dir' => WP_CONTENT_DIR . '/themes', 'url' => $h . '/cms/wp-content/themes', 'rel' => 'wp-content/themes', 'kind' => 'live'],
        ['dir' => ELVADO_THEMES_DIR, 'url' => $h . '/cms/themes', 'rel' => 'themes', 'kind' => 'native']];
}
/** Ordner eines Plugins/Themes löschen – nur innerhalb von wp-content/plugins bzw. wp-content/themes. */
function elvado_wp_rmdir(string $dir): void
{
    $real = realpath($dir);
    $ok = false;
    foreach ([realpath(WP_PLUGIN_DIR), realpath(WP_CONTENT_DIR . '/themes')] as $r) {
        if ($r && $real && str_starts_with($real, $r . DIRECTORY_SEPARATOR)) {
            $ok = true;
        }
    }
    if (!$ok || is_link($dir)) {
        return;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($real);
}
function elvado_wp_lang_dir(): string
{
    return WP_LANG_DIR;
}
function elvado_wp_link_structure(): string
{
    return (string)get_option('permalink_structure', '');
}
function elvado_wp_link_hash(): bool
{
    return false;   // die Hash-Form (/#/seite) gibt es nur in der bisherigen Schicht
}
/** Beiträge für Beispiel-Adressen und Beitragslinks: die nach WordPress übernommenen ElvadoPress-Beiträge (echte WP_Post mit elvado_data und elvado_cms_id). */
function elvado_wp_cms_posts(): array
{
    $news = json_decode((string)@file_get_contents(dirname(ELVADO_WP_DATA) . '/news.json'), true);
    $byId = [];
    foreach (is_array($news) ? $news : [] as $a) {
        if (is_array($a) && isset($a['id'])) {
            $byId['post:' . $a['id']] = $a;
        }
    }
    $out = [];
    foreach (get_posts(['post_type' => 'post', 'post_status' => ['publish', 'future', 'draft', 'pending', 'private'], 'numberposts' => -1, 'meta_key' => '_elvado_source_id']) as $p) {
        $a = $byId[(string)get_post_meta($p->ID, '_elvado_source_id', true)] ?? null;
        if ($a !== null) {
            $p->elvado_data = $a;
            $p->elvado_cms_id = (string)$a['id'];
            $out[] = $p;
        }
    }
    return $out;
}
function elvado_wp_cms_data(): array
{
    $site = json_decode((string)@file_get_contents(dirname(ELVADO_WP_DATA) . '/site.json'), true);
    return ['site' => is_array($site) ? $site : []];
}
function elvado_wp_bridge_typed_get(string $option, array $site)
{
    switch ($option) {
        case 'default_comment_status': return !empty($site['comments']['enabled']) ? 'open' : 'closed';
        case 'comment_moderation': return !array_key_exists('require_approval', (array)($site['comments'] ?? [])) || !empty($site['comments']['require_approval']) ? 1 : 0;
        case 'posts_per_rss': return max(5, min(100, (int)($site['rss']['max_items'] ?? 50)));
        case 'blog_public': return str_starts_with(strtolower(trim((string)($site['seo']['robots'] ?? 'index,follow'))), 'noindex') ? 0 : 1;
    }
    return null;
}
function elvado_wp_bridge_sys_read(string $option): string
{
    require_once dirname(__DIR__) . '/lib/system.php';
    $v = (string)(elvado_system_config()[ELVADO_WP_BRIDGE_SYS[$option]] ?? '');
    if ($option === 'WPLANG' && $v !== '' && !str_contains($v, '_')) {
        $v = strtolower($v) . '_' . ($v === 'en' ? 'US' : strtoupper($v));
    }
    return $v;
}
/** Einstellung, die ElvadoPress führt: in site.json bzw. den Betrieb schreiben (wie die Verwaltung selbst) und in WordPress übernehmen. */
function elvado_wp_bridge_option_write(string $option, $value): bool
{
    $v = trim(wp_strip_all_tags((string)(is_scalar($value) ? $value : '')));
    if (isset(ELVADO_WP_BRIDGE_SYS[$option])) {
        if ($option === 'timezone_string' ? $v !== '' && !in_array($v, DateTimeZone::listIdentifiers(), true) : $v !== '' && !preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $v)) {
            return false;
        }
        require_once dirname(__DIR__) . '/lib/system.php';
        elvado_system_save([ELVADO_WP_BRIDGE_SYS[$option] => $option === 'WPLANG' ? strtolower(substr($v, 0, 2)) : $v]);
        if ($option === 'timezone_string') {
            update_option('timezone_string', $v);
        } elseif (function_exists('elvado_wpe_language')) {
            elvado_wpe_language(substr($v, 0, 2));
        }
        return true;
    }
    if (!isset($GLOBALS['site'], $GLOBALS['siteFile']) || !function_exists('elvado_publish')) {
        return false;
    }
    $site = (array)$GLOBALS['site'];
    if (isset(ELVADO_WP_BRIDGE_TYPED[$option])) {
        $v = trim((string)$value);
        switch ($option) {
            case 'default_comment_status':
                if (!in_array($v, ['open', 'closed'], true)) return false;
                $site['comments'] = (array)($site['comments'] ?? ['require_approval' => true]);
                $site['comments']['enabled'] = $v === 'open';
                break;
            case 'comment_moderation':
                if (!in_array($v, ['0', '1'], true)) return false;
                $site['comments'] = (array)($site['comments'] ?? ['enabled' => false]);
                $site['comments']['require_approval'] = $v === '1';
                break;
            case 'posts_per_rss':
                $site['rss'] = (array)($site['rss'] ?? []);
                $site['rss']['max_items'] = max(5, min(100, (int)$v));
                $v = (string)$site['rss']['max_items'];
                break;
            case 'blog_public':
                if (!in_array($v, ['0', '1'], true)) return false;
                $site['seo'] = (array)($site['seo'] ?? []);
                if ((string)elvado_wp_bridge_typed_get($option, $site) !== $v) {
                    $site['seo']['robots'] = $v === '1' ? 'index,follow' : 'noindex,nofollow';
                }
                break;
        }
    } else {
        if ($option === 'admin_email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        [$sec, $key, $max] = ELVADO_WP_BRIDGE_OPTS[$option];
        $v = mb_substr($v, 0, $max);
        $site[$sec] = (array)($site[$sec] ?? []);
        $site[$sec][$key] = $v;
    }
    $GLOBALS['site'] = $site;
    elvado_publish($GLOBALS['site'], (string)$GLOBALS['siteFile'], (string)($GLOBALS['genDir'] ?? ''), (string)($GLOBALS['root'] ?? ''));
    update_option($option, $v);
    return true;
}

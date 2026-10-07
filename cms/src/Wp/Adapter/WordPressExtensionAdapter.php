<?php
declare(strict_types=1);
// cms/src/Wp/Adapter/WordPressExtensionAdapter.php – Plugins/Themes über die echten WordPress-Funktionen (get_plugins, activate_plugin, wp_get_themes, switch_theme …).

namespace Elvado\Wp\Adapter;

use Elvado\Wp\Bridge;

final class WordPressExtensionAdapter implements ExtensionAdapter
{
    public function __construct()
    {
        if (!Bridge::booted()) {
            throw new \RuntimeException('WordPress ist in dieser Anfrage nicht gestartet.');
        }
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';
    }

    public function plugins(): array
    {
        wp_clean_plugins_cache(false);
        $out = [];
        foreach (get_plugins() as $file => $d) {
            $out[] = [
                'id' => (string)$file, 'slug' => str_contains((string)$file, '/') ? strstr((string)$file, '/', true) : basename((string)$file, '.php'),
                'name' => (string)$d['Name'], 'version' => (string)$d['Version'], 'author' => trim(strip_tags((string)$d['Author'])),
                'description' => mb_substr(trim(strip_tags((string)$d['Description'])), 0, 300), 'uri' => (string)$d['PluginURI'],
                'active' => is_plugin_active((string)$file), 'requires_php' => (string)($d['RequiresPHP'] ?? ''), 'requires_wp' => (string)($d['RequiresWP'] ?? ''),
            ];
        }
        return $out;
    }

    public function themes(): array
    {
        wp_clean_themes_cache(false);   // Dateien können sich in dieser Anfrage geändert haben
        $active = get_stylesheet();
        $out = [];
        foreach (wp_get_themes(['errors' => null]) as $slug => $t) {
            /** @var \WP_Theme $t */
            $err = $t->errors();
            $out[] = [
                'id' => (string)$slug, 'slug' => (string)$slug, 'name' => (string)$t->get('Name'), 'version' => (string)$t->get('Version'),
                'author' => trim(strip_tags((string)$t->get('Author'))), 'description' => mb_substr(trim(strip_tags((string)$t->get('Description'))), 0, 300),
                'active' => (string)$slug === $active, 'parent' => $t->parent() ? (string)$t->parent()->get_stylesheet() : '',
                'block_theme' => method_exists($t, 'is_block_theme') ? (bool)$t->is_block_theme() : false,
                'error' => $err ? mb_substr(strip_tags($err->get_error_message()), 0, 200) : '',
            ];
        }
        return $out;
    }

    public function activatePlugin(string $id): void
    {
        $r = activate_plugin($id, '', false, false);
        if (is_wp_error($r)) {
            throw new \RuntimeException(mb_substr(trim(strip_tags($r->get_error_message())), 0, 300));
        }
        if (!is_plugin_active($id)) {
            throw new \RuntimeException('Das Plugin konnte nicht aktiviert werden.');
        }
    }

    public function deactivatePlugin(string $id): void
    {
        deactivate_plugins($id, false, false);
    }

    public function uninstallPlugin(string $id): void
    {
        $r = uninstall_plugin($id);
        if (is_wp_error($r)) {
            throw new \RuntimeException($r->get_error_message());
        }
    }

    public function activeTheme(): array
    {
        return ['template' => (string)get_template(), 'stylesheet' => (string)get_stylesheet()];
    }

    public function switchTheme(string $slug): void
    {
        $t = wp_get_theme($slug);
        if (!$t->exists() || $t->errors()) {
            throw new \RuntimeException('Das Theme ist fehlerhaft oder unvollständig und lässt sich nicht aktivieren.');
        }
        switch_theme($slug);
        if (get_stylesheet() !== $slug) {
            throw new \RuntimeException('Das Theme konnte nicht aktiviert werden.');
        }
    }
}

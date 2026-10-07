<?php
declare(strict_types=1);
// cms/src/Wp/Adapter/WordPressAdapter.php – Inhalte aus dem echten WordPress (nur lesend in dieser Stufe). Setzt voraus, dass WordPress in dieser Anfrage gestartet wurde (Bridge::booted()).

namespace Elvado\Wp\Adapter;

use Elvado\Wp\Bridge;

final class WordPressAdapter implements ContentAdapter
{
    public function __construct()
    {
        if (!Bridge::booted()) {
            throw new \RuntimeException('WordPress ist in dieser Anfrage nicht gestartet.');
        }
    }

    public function name(): string { return 'WordPress ' . get_bloginfo('version'); }

    public function counts(): array
    {
        $post = wp_count_posts('post');
        $page = wp_count_posts('page');
        $att = wp_count_posts('attachment');
        $cm = wp_count_comments();
        $users = count_users();
        return [
            'posts' => (int)($post->publish ?? 0), 'drafts' => (int)($post->draft ?? 0) + (int)($post->pending ?? 0),
            'pages' => (int)($page->publish ?? 0), 'media' => (int)($att->inherit ?? 0) + (int)($att->private ?? 0),
            'comments' => (int)($cm->approved ?? 0), 'users' => (int)($users['total_users'] ?? 0),
            'categories' => (int)wp_count_terms(['taxonomy' => 'category', 'hide_empty' => false]),
            'tags' => (int)wp_count_terms(['taxonomy' => 'post_tag', 'hide_empty' => false]),
        ];
    }

    /** @return array{version:string,db_version:string,site_url:string,theme:string,plugins:list<string>,php:string} */
    public function info(): array
    {
        $plugins = array_map('strval', (array)get_option('active_plugins', []));
        return [
            'version' => (string)get_bloginfo('version'), 'db_version' => (string)get_option('db_version', ''),
            'site_url' => (string)get_option('siteurl', ''), 'theme' => (string)get_option('stylesheet', ''),
            'plugins' => array_values($plugins), 'php' => PHP_VERSION,
        ];
    }
}

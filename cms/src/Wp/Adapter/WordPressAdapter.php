<?php
declare(strict_types=1);
// cms/src/Wp/Adapter/WordPressAdapter.php – Inhalte im echten WordPress (lesen und schreiben über die WordPress-Funktionen). Setzt voraus, dass WordPress in dieser Anfrage gestartet wurde (Bridge::booted()).

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

    public function writable(): bool { return true; }

    private const TO_WP = ['published' => 'publish', 'draft' => 'draft', 'scheduled' => 'future', 'private' => 'private', 'trash' => 'trash'];
    private const FROM_WP = ['publish' => 'published', 'draft' => 'draft', 'pending' => 'draft', 'auto-draft' => 'draft', 'future' => 'scheduled', 'private' => 'private', 'trash' => 'trash'];

    public function items(string $type, array $q = []): array
    {
        $type = $this->type($type);
        $st = (string)($q['status'] ?? 'all');
        $args = [
            'post_type' => $type, 'posts_per_page' => max(1, min(100, (int)($q['per_page'] ?? 20))), 'paged' => max(1, (int)($q['page'] ?? 1)),
            'orderby' => 'date', 'order' => 'DESC', 'ignore_sticky_posts' => true, 'suppress_filters' => true,
            'post_status' => $st === 'all' ? ['publish', 'draft', 'pending', 'future', 'private'] : (self::TO_WP[$st] ?? 'publish'),
        ];
        if (($s = trim((string)($q['search'] ?? ''))) !== '') {
            $args['s'] = $s;
        }
        if ($type === 'post' && ($c = trim((string)($q['category'] ?? ''))) !== '') {
            $t = get_term_by('slug', $c, 'category') ?: get_term_by('name', $c, 'category');
            $args['cat'] = $t ? (int)$t->term_id : -1;
        }
        $query = new \WP_Query($args);
        return ['items' => array_map(fn($p) => $this->normalize($p), $query->posts), 'total' => (int)$query->found_posts];
    }

    public function item(string $type, string $id): ?array
    {
        $p = ctype_digit($id) ? get_post((int)$id) : null;
        return $p instanceof \WP_Post && $p->post_type === $this->type($type) ? $this->normalize($p) : null;
    }

    public function save(string $type, array $data, bool $unfiltered): array
    {
        $type = $this->type($type);
        // Eingaben wie ein Redakteur: ohne Recht auf ungefiltertes HTML bereinigt WordPress den Inhalt (kses)
        wp_set_current_user($unfiltered ? 1 : 0);
        kses_init();
        $id = (string)($data['id'] ?? '');
        $arr = ['post_type' => $type];
        if ($id !== '') {
            $old = $this->item($type, $id);
            if ($old === null) {
                throw new \RuntimeException('Der Inhalt wurde nicht gefunden.');
            }
            $arr['ID'] = (int)$id;
        }
        foreach (['title' => 'post_title', 'slug' => 'post_name', 'content' => 'post_content', 'excerpt' => 'post_excerpt'] as $k => $col) {
            if (array_key_exists($k, $data)) {
                $arr[$col] = (string)$data[$k];
            }
        }
        if (isset($data['status'])) {
            $arr['post_status'] = self::TO_WP[$data['status']] ?? 'draft';
        } elseif ($id === '') {
            $arr['post_status'] = 'draft';
        }
        if (!empty($data['date'])) {
            $arr['post_date'] = (string)$data['date'];
            $arr['post_date_gmt'] = get_gmt_from_date((string)$data['date']);
        }
        if (($arr['post_status'] ?? '') === 'future' && (strtotime((string)($arr['post_date_gmt'] ?? '1970-01-01') . ' UTC') ?: 0) <= time()) {
            throw new \RuntimeException('Für „geplant“ braucht der Inhalt ein Datum in der Zukunft.');
        }
        if ($type === 'page' && array_key_exists('parent', $data)) {
            $arr['post_parent'] = ctype_digit((string)$data['parent']) ? (int)$data['parent'] : 0;
        }
        $res = $id !== '' ? wp_update_post(wp_slash($arr), true) : wp_insert_post(wp_slash($arr), true);
        if (is_wp_error($res) || !$res) {
            throw new \RuntimeException(is_wp_error($res) ? $res->get_error_message() : 'Der Inhalt konnte nicht gespeichert werden.');
        }
        $pid = (int)$res;
        if ($type === 'post') {
            if (array_key_exists('categories', $data)) {
                $cats = array_values(array_filter(array_map('strval', (array)$data['categories']), 'strlen'));
                $r = wp_set_object_terms($pid, $cats ?: [(int)get_option('default_category')], 'category');
                if (is_wp_error($r)) {
                    throw new \RuntimeException($r->get_error_message());
                }
            }
            if (array_key_exists('tags', $data)) {
                wp_set_object_terms($pid, array_values(array_filter(array_map('strval', (array)$data['tags']), 'strlen')), 'post_tag');
            }
        }
        if (array_key_exists('author', $data)) {
            update_post_meta($pid, '_elvado_author', (string)$data['author']);
        }
        if (($data['owner'] ?? '') !== '') {   // Besitzer = anlegende Person; Autor in WordPress = der gespiegelte Benutzer
            update_post_meta($pid, '_elvado_owner', (string)$data['owner']);
            $wu = get_user_by('login', (string)$data['owner']);
            if ($wu instanceof \WP_User) {
                wp_update_post(['ID' => $pid, 'post_author' => $wu->ID]);
            }
        }
        if (array_key_exists('image', $data)) {
            update_post_meta($pid, '_elvado_image_url', (string)$data['image']);
        }
        return $this->item($type, (string)$pid) ?? [];
    }

    public function delete(string $type, string $id, bool $force): bool
    {
        $p = ctype_digit($id) ? get_post((int)$id) : null;
        if (!$p instanceof \WP_Post || $p->post_type !== $this->type($type)) {
            return false;
        }
        return (bool)($force ? wp_delete_post($p->ID, true) : wp_trash_post($p->ID));
    }

    public function terms(string $taxonomy): array
    {
        $r = get_terms(['taxonomy' => $this->tax($taxonomy), 'hide_empty' => false, 'orderby' => 'name']);
        if (is_wp_error($r)) {
            return [];
        }
        return array_map(static fn($t) => ['id' => (string)$t->term_id, 'name' => (string)$t->name, 'slug' => (string)$t->slug, 'count' => (int)$t->count, 'parent' => $t->parent ? (string)$t->parent : ''], $r);
    }

    public function saveTerm(string $taxonomy, array $data): array
    {
        $tax = $this->tax($taxonomy);
        $args = [];
        foreach (['slug', 'name'] as $k) {
            if (isset($data[$k]) && $data[$k] !== '') {
                $args[$k] = (string)$data[$k];
            }
        }
        if ($tax === 'category' && isset($data['parent'])) {
            $args['parent'] = ctype_digit((string)$data['parent']) ? (int)$data['parent'] : 0;
        }
        $id = (string)($data['id'] ?? '');
        if ($id !== '') {
            $r = wp_update_term((int)$id, $tax, $args);
        } else {
            $r = wp_insert_term((string)($args['name'] ?? ''), $tax, $args);
        }
        if (is_wp_error($r)) {
            throw new \RuntimeException($r->get_error_message());
        }
        $t = get_term((int)$r['term_id'], $tax);
        return ['id' => (string)$t->term_id, 'name' => (string)$t->name, 'slug' => (string)$t->slug, 'count' => (int)$t->count, 'parent' => $t->parent ? (string)$t->parent : ''];
    }

    public function deleteTerm(string $taxonomy, string $id): bool
    {
        $tax = $this->tax($taxonomy);
        if (!ctype_digit($id) || !get_term((int)$id, $tax) instanceof \WP_Term) {
            return false;
        }
        $r = wp_delete_term((int)$id, $tax);
        if (is_wp_error($r)) {
            throw new \RuntimeException($r->get_error_message());
        }
        return $r === true;
    }

    private function type(string $t): string
    {
        if ($t !== 'post' && $t !== 'page') {
            throw new \InvalidArgumentException('Unbekannter Inhaltstyp.');
        }
        return $t;
    }

    private function tax(string $t): string
    {
        if ($t !== 'category' && $t !== 'post_tag') {
            throw new \InvalidArgumentException('Unbekannte Taxonomie.');
        }
        return $t;
    }

    /** @return array<string,mixed> */
    private function normalize(\WP_Post $p): array
    {
        $isPost = $p->post_type === 'post';
        $img = (string)get_the_post_thumbnail_url($p, 'full') ?: (string)get_post_meta($p->ID, '_elvado_image_url', true);
        $author = (string)get_post_meta($p->ID, '_elvado_author', true);
        return [
            'id' => (string)$p->ID, 'type' => $p->post_type, 'title' => (string)$p->post_title, 'slug' => (string)$p->post_name,
            'content' => (string)$p->post_content, 'excerpt' => (string)$p->post_excerpt,
            'status' => self::FROM_WP[$p->post_status] ?? 'draft', 'date' => (string)$p->post_date, 'modified' => (string)$p->post_modified,
            'author' => $author !== '' ? $author : (string)get_the_author_meta('display_name', (int)$p->post_author),
            'categories' => $isPost ? array_values(array_map('strval', wp_get_post_categories($p->ID, ['fields' => 'names']))) : [],
            'tags' => $isPost ? array_values(array_map('strval', wp_get_post_tags($p->ID, ['fields' => 'names']))) : [],
            'parent' => $p->post_parent ? (string)$p->post_parent : '', 'image' => $img, 'owner' => (string)get_post_meta($p->ID, '_elvado_owner', true),
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

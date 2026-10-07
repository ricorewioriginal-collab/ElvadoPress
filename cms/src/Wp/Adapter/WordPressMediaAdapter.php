<?php
declare(strict_types=1);
// cms/src/Wp/Adapter/WordPressMediaAdapter.php – Mediathek im echten WordPress (Anhänge, Größen, Metadaten). Setzt voraus, dass WordPress in dieser Anfrage gestartet wurde.

namespace Elvado\Wp\Adapter;

use Elvado\Wp\Bridge;

final class WordPressMediaAdapter implements MediaAdapter
{
    public function __construct()
    {
        if (!Bridge::booted()) {
            throw new \RuntimeException('WordPress ist in dieser Anfrage nicht gestartet.');
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
    }

    public function writable(): bool { return true; }

    public function items(array $q = []): array
    {
        $args = ['post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => max(1, min(100, (int)($q['per_page'] ?? 24))),
            'paged' => max(1, (int)($q['page'] ?? 1)), 'orderby' => 'date', 'order' => 'DESC', 'suppress_filters' => true];
        if (($s = trim((string)($q['search'] ?? ''))) !== '') {
            $args['s'] = $s;
        }
        $k = (string)($q['kind'] ?? 'all');
        if (in_array($k, ['image', 'video', 'audio'], true)) {
            $args['post_mime_type'] = $k;
        } elseif ($k === 'document') {
            $args['post_mime_type'] = ['application', 'text'];
        }
        $query = new \WP_Query($args);
        return ['items' => array_map(fn($p) => $this->normalize($p), $query->posts), 'total' => (int)$query->found_posts];
    }

    public function item(string $id): ?array
    {
        $p = ctype_digit($id) ? get_post((int)$id) : null;
        return $p instanceof \WP_Post && $p->post_type === 'attachment' ? $this->normalize($p) : null;
    }

    public function add(string $file, string $name, string $mime, array $meta): array
    {
        if (!is_file($file)) {
            throw new \RuntimeException('Die Datei fehlt.');
        }
        $this->protectUploads();
        $upload = ['name' => $name, 'type' => $mime, 'tmp_name' => $file, 'error' => 0, 'size' => (int)filesize($file)];   // wp_handle_sideload verlangt eine Variable (Referenz)
        $r = wp_handle_sideload($upload, ['test_form' => false, 'test_type' => true, 'mimes' => $this->mimes()]);
        if (!is_array($r) || isset($r['error'])) {
            throw new \RuntimeException(is_array($r) ? (string)$r['error'] : 'Die Datei konnte nicht abgelegt werden.');
        }
        $title = trim((string)($meta['title'] ?? '')) !== '' ? (string)$meta['title'] : pathinfo($name, PATHINFO_FILENAME);
        $id = wp_insert_attachment(wp_slash(['post_mime_type' => $r['type'], 'post_title' => $title, 'post_content' => '', 'post_excerpt' => (string)($meta['caption'] ?? ''), 'post_status' => 'inherit']), $r['file'], 0, true);
        if (is_wp_error($id) || !$id) {
            @unlink($r['file']);
            throw new \RuntimeException(is_wp_error($id) ? $id->get_error_message() : 'Die Datei konnte nicht gespeichert werden.');
        }
        $md = wp_generate_attachment_metadata((int)$id, $r['file']);   // Größen (braucht GD oder Imagick; sonst nur das Original)
        if (is_array($md)) {
            wp_update_attachment_metadata((int)$id, $md);
        }
        if (($meta['alt'] ?? '') !== '') {
            update_post_meta((int)$id, '_wp_attachment_image_alt', wp_slash((string)$meta['alt']));
        }
        if (($meta['owner'] ?? '') !== '') {
            update_post_meta((int)$id, '_elvado_owner', (string)$meta['owner']);
        }
        return $this->item((string)$id) ?? [];
    }

    public function update(string $id, array $data): array
    {
        $p = $this->item($id) !== null ? get_post((int)$id) : null;
        if (!$p) {
            throw new \RuntimeException('Das Medium wurde nicht gefunden.');
        }
        $arr = ['ID' => $p->ID];
        if (array_key_exists('title', $data)) {
            $arr['post_title'] = (string)$data['title'];
        }
        if (array_key_exists('caption', $data)) {
            $arr['post_excerpt'] = (string)$data['caption'];
        }
        if (count($arr) > 1) {
            wp_update_post(wp_slash($arr));
        }
        if (array_key_exists('alt', $data)) {
            update_post_meta($p->ID, '_wp_attachment_image_alt', wp_slash((string)$data['alt']));
        }
        return $this->item($id) ?? [];
    }

    public function delete(string $id): bool
    {
        return $this->item($id) !== null && (bool)wp_delete_attachment((int)$id, true);
    }

    /** Erlaubte Dateitypen für den Upload (Endung ⇒ MIME-Typ). @return array<string,string> */
    public static function allowed(): array
    {
        return ['jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'avif' => 'image/avif', 'pdf' => 'application/pdf',
            'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav', 'm4a' => 'audio/mp4', 'mp4' => 'video/mp4', 'webm' => 'video/webm'];
    }

    private function mimes(): array { return self::allowed(); }

    /** PHP-Ausführung im Upload-Ordner ist durch cms/wp-content/.htaccess gesperrt; hier zusätzlich eine eigene Sperre und keine Ordnerliste. */
    private function protectUploads(): void
    {
        $dir = rtrim((string)WP_CONTENT_DIR, '/') . '/uploads';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (is_dir($dir) && !is_file($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "# Nur Medien: nie PHP ausführen\n<FilesMatch \"\\.(php[0-9]?|phtml|phar|pht|inc)$\">\n    Require all denied\n</FilesMatch>\nOptions -ExecCGI -Indexes\n");
            @file_put_contents($dir . '/index.html', '');
        }
    }

    /** @return array<string,mixed> */
    private function normalize(\WP_Post $p): array
    {
        $file = (string)get_attached_file($p->ID);
        $md = wp_get_attachment_metadata($p->ID);
        $mime = (string)$p->post_mime_type;
        return [
            'id' => (string)$p->ID, 'url' => (string)wp_get_attachment_url($p->ID), 'name' => basename($file), 'title' => (string)$p->post_title,
            'alt' => (string)get_post_meta($p->ID, '_wp_attachment_image_alt', true), 'caption' => (string)$p->post_excerpt, 'mime' => $mime,
            'kind' => NativeMediaAdapter::kind($mime), 'size' => is_file($file) ? (int)filesize($file) : 0,
            'width' => (int)($md['width'] ?? 0), 'height' => (int)($md['height'] ?? 0), 'date' => (string)$p->post_date,
            'owner' => (string)get_post_meta($p->ID, '_elvado_owner', true),
        ];
    }
}

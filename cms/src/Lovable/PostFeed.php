<?php
declare(strict_types=1);
// cms/src/Lovable/PostFeed.php
//
// Filtert CMS-Beiträge und bereitet sie als sauberes JSON für Lovable-Komponenten auf (kein Roh-HTML, absolute Adressen, feste Felder).
// Datenquellen: cms/data/news.json (Quelle der Wahrheit) oder der Spiegel in der Tabelle posts.

namespace Elvado\Lovable;

final class PostFeed
{
    public const MAX_LIMIT = 50;

    /** @param list<array<string,mixed>> $rows veröffentlichte Beiträge im news.json-Format */
    public function __construct(
        private readonly array $rows,
        private readonly string $origin,
        private readonly bool $wpFront = false,
    ) {
    }

    /** Veröffentlichte, nicht gelöschte, nicht zukünftige Beiträge aus news.json, neueste zuerst. @return list<array<string,mixed>> */
    public static function rowsFromNewsFile(string $file): array
    {
        $raw = is_file($file) ? json_decode((string)@file_get_contents($file), true) : null;
        $now = date('Y-m-d H:i:s');
        $rows = array_values(array_filter(is_array($raw) ? $raw : [], static function ($a) use ($now): bool {
            if (!is_array($a) || ($a['status'] ?? 'draft') !== 'published' || !empty($a['deleted_at'])) {
                return false;
            }
            $p = trim((string)($a['published_at'] ?? ''));
            return $p === '' || str_replace('T', ' ', $p) <= $now;
        }));
        usort($rows, static fn($a, $b) => strcmp((string)($b['published_at'] ?? $b['created_at'] ?? ''), (string)($a['published_at'] ?? $a['created_at'] ?? '')));
        return $rows;
    }

    /**
     * @param array{limit?:int,offset?:int,category?:string,tag?:string,q?:string,featured?:bool,include_body?:bool} $f
     * @param (callable(string):string)|null $sanitizeHtml Bereinigung des Beitragstexts (nur bei include_body)
     * @return array{total:int,count:int,items:list<array<string,mixed>>}
     */
    public function query(array $f, ?callable $sanitizeHtml = null): array
    {
        $limit = max(1, min(self::MAX_LIMIT, (int)($f['limit'] ?? 10)));
        $offset = max(0, min(10000, (int)($f['offset'] ?? 0)));
        $cat = mb_strtolower(trim((string)($f['category'] ?? '')));
        $tag = mb_strtolower(trim((string)($f['tag'] ?? '')));
        $q = mb_strtolower(trim((string)($f['q'] ?? '')));
        $hit = array_values(array_filter($this->rows, static function (array $a) use ($cat, $tag, $q, $f): bool {
            if ($cat !== '' && mb_strtolower((string)($a['category'] ?? '')) !== $cat) {
                return false;
            }
            if ($tag !== '' && !in_array($tag, array_map('mb_strtolower', self::tags($a)), true)) {
                return false;
            }
            if (!empty($f['featured']) && empty($a['featured'])) {
                return false;
            }
            return $q === '' || str_contains(mb_strtolower((string)($a['title'] ?? '') . ' ' . (string)($a['excerpt'] ?? '')), $q);
        }));
        $items = array_map(fn(array $a): array => $this->item($a, !empty($f['include_body']), $sanitizeHtml), array_slice($hit, $offset, $limit));
        return ['total' => count($hit), 'count' => count($items), 'items' => $items];
    }

    /** @return list<string> */
    private static function tags(array $a): array
    {
        $t = $a['tags'] ?? '';
        $list = is_array($t) ? $t : explode(',', (string)$t);
        return array_values(array_filter(array_map(static fn($x) => trim((string)$x), $list), static fn($x) => $x !== ''));
    }

    private function abs(string $u): string
    {
        $u = trim($u);
        if ($u === '') {
            return '';
        }
        if (str_starts_with($u, '/') && !str_starts_with($u, '//')) {
            return $this->origin . $u;
        }
        return preg_match('~^https://~i', $u) ? $u : '';   // nur https und eigene Pfade
    }

    /** @return array<string,mixed> */
    private function item(array $a, bool $body, ?callable $sanitize): array
    {
        $slug = (string)($a['slug'] ?? '');
        $key = $slug !== '' ? $slug : (string)($a['id'] ?? '');
        $external = !empty($a['is_external']) && trim((string)($a['external_url'] ?? '')) !== '';
        $url = $external ? $this->abs((string)$a['external_url']) : ($this->wpFront ? $this->origin . '/' . rawurlencode($key) . '/' : $this->origin . '/#news/' . rawurlencode($key));
        $excerpt = trim((string)($a['excerpt'] ?? ''));
        $plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string)($a['body_html'] ?? '')), ENT_QUOTES, 'UTF-8')) ?? '');
        if ($excerpt === '') {
            $excerpt = mb_strlen($plain) > 220 ? rtrim(mb_substr($plain, 0, 217)) . '…' : $plain;
        }
        $pub = (string)($a['published_at'] ?? $a['created_at'] ?? '');
        $ts = $pub !== '' ? strtotime($pub) : false;
        $item = [
            'id' => (int)($a['id'] ?? 0),
            'slug' => $slug,
            'title' => trim(strip_tags((string)($a['title'] ?? ''))),
            'excerpt' => trim(strip_tags($excerpt)),
            'category' => trim(strip_tags((string)($a['category'] ?? ''))),
            'tags' => array_map(static fn($t) => strip_tags($t), self::tags($a)),
            'image_url' => $this->abs((string)($a['image_url'] ?? '')),
            'url' => $url,
            'external' => $external,
            'author' => trim(strip_tags((string)($a['author'] ?? ''))),
            'featured' => !empty($a['featured']),
            'published_at' => $ts ? gmdate('c', $ts) : null,
        ];
        if ($body) {
            $html = (string)($a['body_html'] ?? '');
            $item['body_html'] = $sanitize !== null ? $sanitize($html) : htmlspecialchars($plain, ENT_QUOTES, 'UTF-8');
        }
        return $item;
    }
}

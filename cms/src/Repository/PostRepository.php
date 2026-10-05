<?php
declare(strict_types=1);
// cms/src/Repository/PostRepository.php
//
// Tabelle posts (cms/src/Database/schema.sql): Spiegel der CMS-Beiträge (Quelle der Wahrheit bleibt cms/data/news.json).
// Nur Prepared Statements. Wird vom Lovable-Provider als Datenquelle genutzt, wenn die Tabelle befüllt ist.

namespace Elvado\Repository;

use Elvado\Database\DatabaseConnection;

final class PostRepository
{
    public function __construct(private readonly DatabaseConnection $db)
    {
    }

    /** CMS-Beitrag (news.json-Format) → Tabellenzeile. @return array<string,mixed>|null null = unbrauchbar (keine ID/kein Titel) */
    public static function rowFromNews(array $a): ?array
    {
        $id = (int)($a['id'] ?? 0);
        $title = trim((string)($a['title'] ?? ''));
        if ($id <= 0 || $title === '') {
            return null;
        }
        $now = DatabaseConnection::now();
        $date = static function ($v): ?string {
            $v = trim((string)$v);
            return preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?/', $v) ? str_replace('T', ' ', substr($v, 0, 19)) . (strlen($v) === 16 ? ':00' : '') : null;
        };
        return [
            'id' => $id,
            'slug' => mb_substr((string)($a['slug'] ?? ''), 0, 190),
            'title' => mb_substr($title, 0, 255),
            'excerpt' => (string)($a['excerpt'] ?? ''),
            'body_html' => (string)($a['body_html'] ?? ''),
            'category' => mb_substr((string)($a['category'] ?? 'News'), 0, 80) ?: 'News',
            'tags' => mb_substr((string)($a['tags'] ?? ''), 0, 800),
            'image_url' => mb_substr((string)($a['image_url'] ?? ''), 0, 1200),
            'author' => mb_substr((string)($a['author'] ?? ''), 0, 120),
            'status' => in_array((string)($a['status'] ?? ''), ['published', 'draft'], true) ? (string)$a['status'] : 'draft',
            'featured' => !empty($a['featured']) ? 1 : 0,
            'published_at' => $date($a['published_at'] ?? ''),
            'created_at' => $date($a['created_at'] ?? '') ?? $now,
            'updated_at' => $date($a['updated_at'] ?? '') ?? $now,
            'deleted_at' => $date($a['deleted_at'] ?? ''),
            'meta_json' => json_encode(array_filter([
                'external_url' => (string)($a['external_url'] ?? ''),
                'is_external' => !empty($a['is_external']),
            ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    /** Ersetzt den Spiegel durch den aktuellen Stand der Beiträge (eine Transaktion). @return int Anzahl gespiegelter Beiträge */
    public function mirror(array $news): int
    {
        return (int)$this->db->transaction(function (DatabaseConnection $db) use ($news): int {
            $db->execute('DELETE FROM posts');
            $n = 0;
            foreach ($news as $a) {
                $row = is_array($a) ? self::rowFromNews($a) : null;
                if ($row === null) {
                    continue;
                }
                $db->insert('posts', $row);
                $n++;
            }
            return $n;
        });
    }

    public function count(): int
    {
        return (int)$this->db->fetchValue('SELECT COUNT(*) FROM posts');
    }

    /**
     * Veröffentlichte, nicht gelöschte Beiträge, neueste zuerst (Zeitpunkt in der Zukunft = noch nicht sichtbar).
     * @return list<array<string,mixed>> Zeilen im news.json-ähnlichen Format
     */
    public function published(int $limit = 500): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM posts WHERE status = ? AND deleted_at IS NULL AND (published_at IS NULL OR published_at <= ?) ORDER BY COALESCE(published_at, created_at) DESC, id DESC LIMIT ' . max(1, min(2000, $limit)),
            ['published', DatabaseConnection::now()]
        );
        return array_map(static function (array $r): array {
            $meta = json_decode((string)($r['meta_json'] ?? ''), true);
            return $r + ['external_url' => is_array($meta) ? (string)($meta['external_url'] ?? '') : '', 'is_external' => is_array($meta) && !empty($meta['is_external'])];
        }, $rows);
    }
}

<?php
declare(strict_types=1);
// cms/src/Wp/Adapter/NativeAdapter.php – Bestand der ElvadoPress-eigenen Datenhaltung (JSON/Dateien unter cms/data, cms/content, cms/media). Nur lesend (Quelle für die Migration und den Vergleich).

namespace Elvado\Wp\Adapter;

final class NativeAdapter implements ContentAdapter
{
    public function __construct(private readonly string $cmsDir, private readonly string $dataDir)
    {
    }

    public function name(): string { return 'ElvadoPress (eigene Daten)'; }

    public function counts(): array
    {
        $news = $this->json($this->dataDir . '/news.json');
        $posts = 0;
        $drafts = 0;
        $cats = [];
        $tags = [];
        foreach ($news as $a) {
            if (!is_array($a) || !empty($a['deleted_at'])) {
                continue;
            }
            if ((string)($a['status'] ?? 'published') === 'published') {
                $posts++;
            } else {
                $drafts++;
            }
            if (($c = trim((string)($a['category'] ?? ''))) !== '') {
                $cats[mb_strtolower($c)] = 1;
            }
            foreach (preg_split('/\s*,\s*/', (string)($a['tags'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $t) {
                $tags[mb_strtolower($t)] = 1;
            }
        }
        $pages = 0;
        foreach (glob($this->cmsDir . '/content/pages/*/page.md') ?: [] as $_) {
            $pages++;
        }
        $media = 0;
        $dir = $this->cmsDir . '/media';
        if (is_dir($dir)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
                if ($f->isFile() && !in_array($f->getBasename(), ['.htaccess', 'index.html', 'index.php'], true)) {
                    $media++;
                }
            }
        }
        $comments = 0;
        foreach ($this->json($this->dataDir . '/comments.json') as $c) {
            if (is_array($c) && (string)($c['status'] ?? 'approved') === 'approved') {
                $comments++;
            }
        }
        return ['posts' => $posts, 'drafts' => $drafts, 'pages' => $pages, 'media' => $media, 'comments' => $comments, 'users' => $this->users(), 'categories' => count($cats), 'tags' => count($tags)];
    }

    public function writable(): bool { return false; }

    public function items(string $type, array $q = []): array
    {
        $all = $type === 'page' ? $this->pages() : ($type === 'post' ? $this->posts() : []);
        $st = (string)($q['status'] ?? 'all');
        $search = mb_strtolower(trim((string)($q['search'] ?? '')));
        $cat = mb_strtolower(trim((string)($q['category'] ?? '')));
        $out = [];
        foreach ($all as $it) {
            if ($st === 'all' ? $it['status'] === 'trash' : $it['status'] !== $st) {
                continue;
            }
            if ($search !== '' && !str_contains(mb_strtolower($it['title'] . ' ' . $it['slug']), $search)) {
                continue;
            }
            if ($cat !== '' && !in_array($cat, array_map('mb_strtolower', $it['categories']), true)) {
                continue;
            }
            $out[] = $it;
        }
        usort($out, static fn($a, $b) => strcmp($b['date'], $a['date']));
        $per = max(1, min(100, (int)($q['per_page'] ?? 20)));
        $page = max(1, (int)($q['page'] ?? 1));
        return ['items' => array_slice($out, ($page - 1) * $per, $per), 'total' => count($out)];
    }

    public function item(string $type, string $id): ?array
    {
        foreach ($type === 'page' ? $this->pages() : $this->posts() as $it) {
            if ($it['id'] === $id) {
                return $it;
            }
        }
        return null;
    }

    public function save(string $type, array $data, bool $unfiltered): array
    {
        throw new \RuntimeException('Der Bestand von ElvadoPress wird weiter über die bisherige Verwaltung geändert; Schreiben über den Adapter gibt es nur mit aktiver WordPress-Engine.');
    }

    public function delete(string $type, string $id, bool $force): bool
    {
        throw new \RuntimeException('Löschen über den Adapter gibt es nur mit aktiver WordPress-Engine.');
    }

    public function terms(string $taxonomy): array
    {
        $map = [];
        foreach ($this->posts() as $p) {
            if ($p['status'] === 'trash') {
                continue;
            }
            foreach ($taxonomy === 'category' ? $p['categories'] : ($taxonomy === 'post_tag' ? $p['tags'] : []) as $n) {
                $k = mb_strtolower($n);
                $map[$k] = ($map[$k] ?? ['id' => self::slug($n), 'name' => $n, 'slug' => self::slug($n), 'count' => 0, 'parent' => '']);
                $map[$k]['count']++;
            }
        }
        $out = array_values($map);
        usort($out, static fn($a, $b) => strcasecmp($a['name'], $b['name']));
        return $out;
    }

    public function saveTerm(string $taxonomy, array $data): array
    {
        throw new \RuntimeException('Begriffe lassen sich nur mit aktiver WordPress-Engine über den Adapter ändern.');
    }

    public function deleteTerm(string $taxonomy, string $id): bool
    {
        throw new \RuntimeException('Begriffe lassen sich nur mit aktiver WordPress-Engine über den Adapter ändern.');
    }

    private static function slug(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        return trim((string)preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    }

    /** @return list<array<string,mixed>> */
    private function posts(): array
    {
        $out = [];
        $now = date('Y-m-d H:i:s');
        foreach ($this->json($this->dataDir . '/news.json') as $a) {
            if (!is_array($a)) {
                continue;
            }
            $date = (string)($a['published_at'] ?? $a['created_at'] ?? '');
            $status = !empty($a['deleted_at']) ? 'trash' : ((string)($a['status'] ?? 'published') === 'published' ? ($date > $now ? 'scheduled' : 'published') : 'draft');
            $out[] = [
                'id' => (string)($a['id'] ?? ''), 'type' => 'post', 'title' => (string)($a['title'] ?? ''), 'slug' => (string)($a['slug'] ?? ''),
                'content' => (string)($a['body_html'] ?? ''), 'excerpt' => (string)($a['excerpt'] ?? ''), 'status' => $status,
                'date' => $date, 'modified' => (string)($a['updated_at'] ?? $date), 'author' => (string)($a['author'] ?? ''),
                'categories' => ($c = trim((string)($a['category'] ?? ''))) !== '' ? [$c] : [],
                'tags' => preg_split('/\s*,\s*/', (string)($a['tags'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [],
                'parent' => '', 'image' => (string)($a['image_url'] ?? ''),
            ];
        }
        return $out;
    }

    /** Eigene Seiten: site.json (Typ custom, mit Blöcken) und Markdown-Dateien unter cms/content/pages. @return list<array<string,mixed>> */
    private function pages(): array
    {
        $out = [];
        $seen = [];
        $site = is_file($this->dataDir . '/site.json') ? json_decode((string)@file_get_contents($this->dataDir . '/site.json'), true) : null;
        foreach ((array)($site['pages'] ?? []) as $p) {
            if (!is_array($p) || ($p['type'] ?? '') !== 'custom') {
                continue;
            }
            $slug = self::slug((string)($p['slug'] ?? $p['title'] ?? ''));
            if ($slug === '' || isset($seen[$slug])) {
                continue;
            }
            $seen[$slug] = 1;
            $html = '';
            foreach (array_merge((array)($p['blocks_before'] ?? []), (array)($p['blocks_after'] ?? [])) as $b) {
                if (is_array($b) && !empty($b['enabled'])) {
                    $html .= self::blockHtml($b);
                }
            }
            $out[] = $this->page($slug, (string)($p['title'] ?? $slug), $html, !empty($p['enabled']), (string)($p['intro'] ?? ''), $this->mtime($slug));
        }
        foreach (glob($this->cmsDir . '/content/pages/*/page.md') ?: [] as $f) {
            $raw = str_replace("\r\n", "\n", (string)@file_get_contents($f));
            $title = '';
            $slug = basename(dirname($f));
            $enabled = true;
            $body = $raw;
            if (preg_match('/^---\s*\n(.*?)\n---\s*\n?/s', $raw, $m)) {
                $body = substr($raw, strlen($m[0]));
                foreach (explode("\n", $m[1]) as $line) {
                    if (!str_contains($line, ':')) {
                        continue;
                    }
                    [$k, $v] = array_map('trim', explode(':', $line, 2));
                    $v = trim($v, " \t\"'");
                    if ($k === 'title') {
                        $title = $v;
                    } elseif ($k === 'slug' && $v !== '') {
                        $slug = $v;
                    } elseif ($k === 'enabled') {
                        $enabled = $v !== 'false';
                    }
                }
            }
            $slug = self::slug($slug);
            if ($slug === '' || isset($seen[$slug])) {
                continue;
            }
            $seen[$slug] = 1;
            $out[] = $this->page($slug, $title !== '' ? $title : $slug, self::markdownHtml($body), $enabled, '', (int)@filemtime($f));
        }
        return $out;
    }

    private function mtime(string $slug): int
    {
        $f = $this->cmsDir . '/content/pages/' . $slug . '/page.md';
        return is_file($f) ? (int)@filemtime($f) : (int)@filemtime($this->dataDir . '/site.json');
    }

    /** @return array<string,mixed> */
    private function page(string $slug, string $title, string $html, bool $enabled, string $excerpt, int $mtime): array
    {
        $d = date('Y-m-d H:i:s', $mtime > 0 ? $mtime : time());
        return ['id' => $slug, 'type' => 'page', 'title' => $title, 'slug' => $slug, 'content' => $html, 'excerpt' => $excerpt, 'status' => $enabled ? 'published' : 'draft',
            'date' => $d, 'modified' => $d, 'author' => '', 'categories' => [], 'tags' => [], 'parent' => '', 'image' => ''];
    }

    private static function blockHtml(array $b): string
    {
        $e = static fn(string $t): string => htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $t = (string)($b['type'] ?? 'text');
        if ($t === 'heading') {
            $l = max(2, min(4, (int)($b['level'] ?? 2)));
            return "<h$l>" . $e((string)($b['text'] ?? '')) . "</h$l>\n";
        }
        if ($t === 'quote') {
            return '<blockquote><p>' . nl2br($e((string)($b['text'] ?? ''))) . "</p></blockquote>\n";
        }
        if ($t === 'divider') {
            return "<hr>\n";
        }
        if ($t === 'html') {
            return (string)($b['html'] ?? '') . "\n";
        }
        return '<p>' . nl2br($e((string)($b['text'] ?? ''))) . "</p>\n";
    }

    private static function markdownHtml(string $body): string
    {
        $html = '';
        $par = [];
        $flush = static function () use (&$par, &$html): void {
            if ($par) {
                $html .= self::blockHtml(['type' => 'text', 'text' => trim(implode("\n", $par))]);
                $par = [];
            }
        };
        foreach (explode("\n", str_replace("\r\n", "\n", $body)) as $line) {
            if (preg_match('/^(#{2,4})\s+(.+)$/', $line, $m)) {
                $flush();
                $html .= self::blockHtml(['type' => 'heading', 'level' => strlen($m[1]), 'text' => trim($m[2])]);
            } elseif (preg_match('/^>\s?(.*)$/', $line, $m)) {
                $flush();
                $html .= self::blockHtml(['type' => 'quote', 'text' => trim($m[1])]);
            } elseif (trim($line) === '---') {
                $flush();
                $html .= self::blockHtml(['type' => 'divider']);
            } elseif (trim($line) === '') {
                $flush();
            } else {
                $par[] = $line;
            }
        }
        $flush();
        return $html;
    }

    /** @return list<mixed> */
    private function json(string $file): array
    {
        $d = is_file($file) ? json_decode((string)@file_get_contents($file), true) : null;
        if (!is_array($d)) {
            return [];
        }
        return array_values($d['items'] ?? $d['comments'] ?? $d);
    }

    private function users(): int
    {
        $f = $this->dataDir . '/local-auth.local.php';
        if (!is_file($f)) {
            return 0;
        }
        $c = (static function (string $f) { return include $f; })($f);
        if (!is_array($c)) {
            return 0;
        }
        return isset($c['users']) && is_array($c['users']) ? count($c['users']) : (!empty($c['username']) ? 1 : 0);
    }
}

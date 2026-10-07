<?php
declare(strict_types=1);
// cms/src/Wp/Adapter/NativeAdapter.php – Bestand der ElvadoPress-eigenen Datenhaltung (JSON/Dateien unter cms/data, cms/content, cms/media). Nur lesend.

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

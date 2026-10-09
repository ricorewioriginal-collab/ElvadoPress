<?php
declare(strict_types=1);
// cms/src/Wp/Adapter/NativeMediaAdapter.php – Mediathek von ElvadoPress (cms/media). Nur lesend (Quelle für die Migration); Hochladen bleibt in der bisherigen Verwaltung.

namespace Elvado\Wp\Adapter;

final class NativeMediaAdapter implements MediaAdapter
{
    public function __construct(private readonly string $cmsDir)
    {
    }

    public function writable(): bool { return false; }

    public function items(array $q = []): array
    {
        $kind = (string)($q['kind'] ?? 'all');
        $search = mb_strtolower(trim((string)($q['search'] ?? '')));
        $out = [];
        foreach ($this->all() as $m) {
            if (($kind !== 'all' && $kind !== '' && $m['kind'] !== $kind) || ($search !== '' && !str_contains(mb_strtolower($m['name'] . ' ' . $m['title']), $search))) {
                continue;
            }
            $out[] = $m;
        }
        $per = max(1, min(100, (int)($q['per_page'] ?? 24)));
        $page = max(1, (int)($q['page'] ?? 1));
        return ['items' => array_slice($out, ($page - 1) * $per, $per), 'total' => count($out)];
    }

    public function item(string $id): ?array
    {
        foreach ($this->all() as $m) {
            if ($m['id'] === $id) {
                return $m;
            }
        }
        return null;
    }

    public function add(string $file, string $name, string $mime, array $meta): array
    {
        throw new \RuntimeException('Hochladen über den Adapter gibt es nur mit aktiver WordPress-Engine.');
    }

    public function update(string $id, array $data): array
    {
        throw new \RuntimeException('Ändern über den Adapter gibt es nur mit aktiver WordPress-Engine.');
    }

    public function delete(string $id): bool
    {
        throw new \RuntimeException('Löschen über den Adapter gibt es nur mit aktiver WordPress-Engine.');
    }

    /** @return list<array<string,mixed>> neueste zuerst */
    private function all(): array
    {
        $rows = [];
        if (function_exists('elvado_media_library_items')) {
            $rows = elvado_media_library_items([]);
        } else {   // ohne die Bibliothek der Verwaltung: Dateien einfach auflisten
            $base = $this->cmsDir . '/media';
            if (is_dir($base)) {
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)) as $f) {
                    /** @var \SplFileInfo $f */
                    if ($f->isFile() && !in_array($f->getBasename(), ['.htaccess', 'index.html', 'index.php', 'meta.json'], true)) {
                        $rel = substr($f->getPathname(), strlen($base) + 1);
                        $rows[] = ['id' => md5($rel), 'url' => '/cms/media/' . $rel, 'name' => $f->getBasename(), 'mime' => '', 'size' => $f->getSize(), 'mtime' => $f->getMTime()];
                    }
                }
            }
        }
        $out = [];
        foreach ($rows as $r) {
            $mime = (string)($r['mime'] ?? '');
            $name = (string)($r['name'] ?? '');
            if ($mime === '' && preg_match('/\.(png|jpe?g|gif|webp|avif|svg|ico)$/i', $name)) {
                $mime = 'image/' . strtolower((string)preg_replace('/^.*\./', '', $name));
            }
            $out[] = [
                'id' => (string)($r['id'] ?? ''), 'url' => (string)($r['url'] ?? ''), 'name' => $name, 'title' => $name, 'alt' => (string)($r['alt'] ?? ''), 'caption' => '',
                'mime' => $mime, 'kind' => self::kind($mime), 'size' => (int)($r['size'] ?? 0), 'width' => (int)($r['width'] ?? 0), 'height' => (int)($r['height'] ?? 0),
                'date' => date('Y-m-d H:i:s', (int)($r['mtime'] ?? 0) ?: time()), 'owner' => '',
            ];
        }
        return $out;
    }

    public static function kind(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default => 'document',
        };
    }
}

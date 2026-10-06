<?php
declare(strict_types=1);
// Leistung: Seiten-Cache, Lazy Loading, leichte HTML-Verkleinerung, AVIF, .htaccess-Block, Kennzahlen.

namespace ElvadoPlugin\Performance;

use Elvado\Plugin\Context;
use Elvado\Plugin\Fs;

final class Perf
{
    private const BYPASS_COOKIES = '/^(wordpress_logged_in_|wordpress_sec_|wp-postpass_|comment_author_|rrw_wp_sess|rrw_sbx|rrw_wp_preview|rrw_wp_draft|rrw_app$)/';
    private const IGNORE_PARAMS = '/^(utm_[a-z]+|fbclid|gclid|msclkid|mc_[a-z]+)$/i';
    private const MARK = '# BEGIN ElvadoPress Performance';
    private const MARK_END = '# END ElvadoPress Performance';

    private bool $bypass = false;

    public function __construct(private readonly Context $np) {}

    private function dir(string $sub = ''): string { return $this->np->dataDir($sub); }
    private function cacheDir(): string { return $this->np->dataDir('cache'); }

    // ---------------------------------------------------------------- Cache

    public function acceptsAvif(): bool { return $this->np->setting('avif') && str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'image/avif') && self::avifSupported(); }

    /** Darf diese Anfrage aus/in den Cache? */
    public function cacheable(string $path, string $method): bool
    {
        if (!$this->np->setting('cache') || $method !== 'GET') {
            return false;
        }
        foreach ($_COOKIE as $k => $v) {
            if (preg_match(self::BYPASS_COOKIES, (string)$k)) {
                return false;
            }
        }
        foreach (array_keys($_GET) as $k) {
            if (!preg_match(self::IGNORE_PARAMS, (string)$k)) {
                return false;
            }
        }
        if (preg_match('~^/(wp-admin|wp-json|cms|wp-login\.php|wp-cron\.php|xmlrpc\.php)(/|$)~', $path) || preg_match('~(/feed/?|\.xml|\.txt|\.json)$~i', $path) || str_contains($path, '/feed/')) {
            return false;
        }
        if (preg_match('/ElvadoPressApp\//', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''))) {
            return false;   // App-Modus liefert andere Seiten
        }
        foreach (preg_split('/\R/', (string)$this->np->setting('exclude_paths')) ?: [] as $ex) {
            $ex = trim($ex);
            if ($ex !== '' && $ex[0] === '/' && (str_starts_with($path, $ex))) {
                return false;
            }
        }
        return true;
    }

    private function key(string $path): string
    {
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        return hash('sha256', $host . '|' . (self::isHttps() ? 's' : 'p') . '|' . rtrim($path, '/') . '|' . ($this->acceptsAvif() ? 'avif' : ''));
    }

    private static function isHttps(): bool { return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'); }

    /** Aktion front_request: gültige Seite aus dem Cache ausliefern. */
    public function serve(string $uri, string $path, string $method = 'GET'): void
    {
        $this->bypass = !$this->cacheable($path, $method);
        if ($this->bypass) {
            return;
        }
        $f = $this->cacheDir() . '/' . $this->key($path) . '.html';
        $fh = @fopen($f, 'r');
        if (!$fh) {
            @file_put_contents($this->dir() . '/miss.cnt', '.', FILE_APPEND);
            return;
        }
        $meta = json_decode((string)fgets($fh), true);
        $ttl = max(1, (int)$this->np->setting('ttl_minutes')) * 60;
        if (!is_array($meta) || time() - (int)($meta['t'] ?? 0) > $ttl) {
            fclose($fh);
            @file_put_contents($this->dir() . '/miss.cnt', '.', FILE_APPEND);
            return;
        }
        $body = (string)stream_get_contents($fh);
        fclose($fh);
        $etag = '"' . substr(md5($body), 0, 16) . '"';
        @file_put_contents($this->dir() . '/hit.cnt', '.', FILE_APPEND);
        header('Content-Type: ' . ($meta['ct'] ?? 'text/html; charset=UTF-8'));
        header('X-Elvado-Cache: HIT');
        header('Cache-Control: no-cache, must-revalidate');
        header('ETag: ' . $etag);
        header('Vary: Accept-Encoding' . ($this->np->setting('avif') ? ', Accept' : ''), false);
        if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            http_response_code(304);
        } else {
            echo $body;
        }
        if (function_exists('rrw_np_do')) {
            rrw_np_do('front_response', 200, $path, 'text/html');   // Statistik zählt auch Cache-Treffer
        }
        exit;
    }

    /** Filter front_output (spät): optimierte Seite zwischenspeichern. */
    public function store(string $html, int $status): string
    {
        if ($status !== 200 || $this->bypass || stripos($html, '</html>') === false || str_contains($html, 'data-elvado-nocache')) {
            return $html;
        }
        foreach (headers_list() as $h) {
            if (stripos($h, 'Set-Cookie:') === 0 || stripos($h, 'Cache-Control: no-store') === 0 || stripos($h, 'Cache-Control: private') === 0) {
                return $html;
            }
        }
        $path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        if (!$this->cacheable($path, (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'))) {
            return $html;
        }
        $tmp = $this->cacheDir() . '/.t' . bin2hex(random_bytes(4));
        $ct = 'text/html; charset=UTF-8';
        foreach (headers_list() as $h) {
            if (stripos($h, 'Content-Type:') === 0) {
                $ct = trim(substr($h, 13));
            }
        }
        if (@file_put_contents($tmp, json_encode(['t' => time(), 'ct' => $ct]) . "\n" . $html) !== false) {
            @rename($tmp, $this->cacheDir() . '/' . $this->key($path) . '.html');
        } else {
            @unlink($tmp);
        }
        if (!headers_sent()) {
            header('X-Elvado-Cache: MISS');
        }
        return $html;
    }

    public function purge(): int
    {
        $n = 0;
        foreach (glob($this->cacheDir() . '/*.html') ?: [] as $f) {
            if (@unlink($f)) {
                $n++;
            }
        }
        return $n;
    }

    public function tick(): void
    {
        $ttl = max(1, (int)$this->np->setting('ttl_minutes')) * 60;
        $files = glob($this->cacheDir() . '/*.html') ?: [];
        foreach ($files as $f) {
            if (time() - (int)filemtime($f) > $ttl * 2) {
                @unlink($f);
            }
        }
        $files = glob($this->cacheDir() . '/*.html') ?: [];
        if (count($files) > 1500) {   // Obergrenze
            usort($files, static fn($a, $b) => filemtime($a) <=> filemtime($b));
            foreach (array_slice($files, 0, count($files) - 1500) as $f) {
                @unlink($f);
            }
        }
    }

    public function cacheStats(): array
    {
        $files = glob($this->cacheDir() . '/*.html') ?: [];
        $size = 0;
        foreach ($files as $f) {
            $size += (int)filesize($f);
        }
        $hit = (int)@filesize($this->dir() . '/hit.cnt');
        $miss = (int)@filesize($this->dir() . '/miss.cnt');
        return ['entries' => count($files), 'size' => $size, 'hits' => $hit, 'misses' => $miss, 'rate' => ($hit + $miss) ? (int)round(100 * $hit / ($hit + $miss)) : 0];
    }

    // ---------------------------------------------------------------- HTML-Optimierung

    /** Filter front_output (mittlere Priorität). */
    public function optimize(string $html, int $status): string
    {
        if ($status !== 200 || stripos($html, '<body') === false) {
            return $html;
        }
        if ($this->np->setting('lazy')) {
            $html = self::lazyLoad($html, max(0, (int)$this->np->setting('skip_first')));
        }
        if ($this->acceptsAvif()) {
            $html = $this->avifSwap($html);
        }
        if ($this->np->setting('minify')) {
            $html = self::minify($html);
        }
        return $html;
    }

    /** loading="lazy" und decoding="async" ergänzen (nur im <body>, nie in <noscript>, vorhandene Angaben bleiben). */
    public static function lazyLoad(string $html, int $skip): string
    {
        $pos = stripos($html, '<body');
        if ($pos === false) {
            return $html;
        }
        $head = substr($html, 0, $pos);
        $body = substr($html, $pos);
        $parts = preg_split('~(<noscript\b.*?</noscript>)~is', $body, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$body];
        $img = 0;
        foreach ($parts as $i => $part) {
            if ($i % 2 === 1) {
                continue;
            }
            $parts[$i] = preg_replace_callback('~<(img|iframe)\b([^>]*)>~i', static function (array $m) use (&$img, $skip): string {
                $tag = strtolower($m[1]);
                $attrs = $m[2];
                if ($tag === 'img') {
                    $img++;
                    if ($img <= $skip || preg_match('/\sloading\s*=|\sfetchpriority\s*=\s*["\']?high/i', $attrs)) {
                        return $m[0];
                    }
                    $self = str_ends_with(rtrim($attrs), '/');
                    $a = $self ? rtrim(substr(rtrim($attrs), 0, -1)) : $attrs;
                    return '<img' . $a . ' loading="lazy"' . (preg_match('/\sdecoding\s*=/i', $a) ? '' : ' decoding="async"') . ($self ? ' /' : '') . '>';
                }
                if (preg_match('/\sloading\s*=/i', $attrs)) {
                    return $m[0];
                }
                return '<iframe' . $attrs . ' loading="lazy">';
            }, $part) ?? $part;
        }
        return $head . implode('', $parts);
    }

    /** Bild-Adressen unter /cms/media/ auf vorhandene .avif-Dateien umstellen. */
    private function avifSwap(string $html): string
    {
        $root = $this->np->rootDir();
        return preg_replace_callback('~(/cms/media/[A-Za-z0-9_./-]+)\.(webp)(?=["\'\s,)])~', static function (array $m) use ($root): string {
            return is_file($root . $m[1] . '.avif') ? $m[1] . '.avif' : $m[0];
        }, $html) ?? $html;
    }

    public static function minify(string $html): string
    {
        $keep = [];
        $html = preg_replace_callback('~<(pre|textarea|script|style|code)\b.*?</\1>~is', static function (array $m) use (&$keep): string {
            $keep[] = $m[0];
            return "\x01" . (count($keep) - 1) . "\x02";
        }, $html) ?? $html;
        $html = preg_replace('~<!--(?!\[if|<!\[endif|\s*(noindex|/noindex)|\s*RRW).*?-->~s', '', $html) ?? $html;
        $html = preg_replace('~>\s+<~', '> <', $html) ?? $html;
        $html = preg_replace('~[ \t]*\R[ \t]*~', "\n", $html) ?? $html;
        $html = preg_replace("~\n{2,}~", "\n", $html) ?? $html;
        return preg_replace_callback("~\x01(\d+)\x02~", static fn($m) => $keep[(int)$m[1]] ?? '', $html) ?? $html;
    }

    // ---------------------------------------------------------------- AVIF

    public static function avifSupported(): bool { return function_exists('imageavif') && function_exists('imagecreatefromwebp'); }

    /** Aus einer WebP-Datei eine AVIF-Datei daneben erzeugen. */
    public function makeAvif(string $webp): bool
    {
        if (!self::avifSupported() || !is_file($webp) || is_file(preg_replace('/\.webp$/', '.avif', $webp))) {
            return false;
        }
        $im = @imagecreatefromwebp($webp);
        if (!$im) {
            return false;
        }
        imagepalettetotruecolor($im);
        imagealphablending($im, true);
        imagesavealpha($im, true);
        $out = preg_replace('/\.webp$/', '.avif', $webp);
        $ok = @imageavif($im, $out, 55, 6);
        imagedestroy($im);
        if (!$ok || !is_file($out) || filesize($out) < 100) {
            @unlink($out);
            return false;
        }
        return true;
    }

    /** Aktion media_uploaded: AVIF zu den neuen WebP-Varianten. */
    public function onUpload(array $meta, string $dir): void
    {
        if (!$this->np->setting('avif') || !self::avifSupported()) {
            return;
        }
        foreach ((array)($meta['variants'] ?? []) as $v) {
            if (is_array($v) && !empty($v['path']) && str_ends_with((string)$v['path'], '.webp')) {
                $this->makeAvif($this->np->cmsDir() . '/media/' . $v['path']);
            }
        }
    }

    /** @return array{done:int,left:int} */
    public function optimizeLibrary(int $max = 15): array
    {
        $done = 0;
        $left = 0;
        foreach (glob($this->np->cmsDir() . '/media/library/*/*.webp') ?: [] as $f) {
            if (is_file(preg_replace('/\.webp$/', '.avif', $f))) {
                continue;
            }
            if ($done >= $max) {
                $left++;
                continue;
            }
            if ($this->makeAvif($f)) {
                $done++;
            } else {
                @file_put_contents(preg_replace('/\.webp$/', '.avif', $f) . '.skip', '');   // nicht wiederholt versuchen
            }
        }
        return ['done' => $done, 'left' => $left];
    }

    // ---------------------------------------------------------------- .htaccess

    private function htFile(): string { return $this->np->rootDir() . '/.htaccess'; }

    private static function htBlock(): string
    {
        return self::MARK . "\n<IfModule mod_expires.c>\n  ExpiresActive On\n  ExpiresByType image/webp \"access plus 1 month\"\n  ExpiresByType image/avif \"access plus 1 month\"\n  ExpiresByType image/png \"access plus 1 month\"\n  ExpiresByType image/jpeg \"access plus 1 month\"\n  ExpiresByType image/gif \"access plus 1 month\"\n  ExpiresByType image/svg+xml \"access plus 1 month\"\n  ExpiresByType font/woff2 \"access plus 1 year\"\n  ExpiresByType text/css \"access plus 1 week\"\n  ExpiresByType application/javascript \"access plus 1 week\"\n</IfModule>\n<IfModule mod_deflate.c>\n  AddOutputFilterByType DEFLATE text/html text/css text/plain text/xml application/javascript application/json image/svg+xml\n</IfModule>\n" . self::MARK_END . "\n";
    }

    public function htaccessActive(): bool { return is_file($this->htFile()) && str_contains((string)file_get_contents($this->htFile()), self::MARK); }

    public function htaccessEnable(): array
    {
        $f = $this->htFile();
        $cur = is_file($f) ? (string)file_get_contents($f) : '';
        if (!is_writable(is_file($f) ? $f : dirname($f))) {
            return ['ok' => false, 'message' => 'Die .htaccess ist nicht beschreibbar.'];
        }
        if (str_contains($cur, self::MARK)) {
            return ['ok' => true, 'message' => 'Der Block ist bereits eingetragen.'];
        }
        if ($cur !== '') {
            @file_put_contents($this->dir() . '/htaccess.bak', $cur);
        }
        $new = self::htBlock() . ($cur !== '' ? "\n" . $cur : '');
        $tmp = $f . '.tmp' . bin2hex(random_bytes(3));
        if (@file_put_contents($tmp, $new) === false || !@rename($tmp, $f)) {
            @unlink($tmp);
            return ['ok' => false, 'message' => 'Die .htaccess konnte nicht geschrieben werden.'];
        }
        return ['ok' => true, 'message' => 'Block eingetragen. Die bisherige Datei liegt gesichert in den Plugin-Daten (htaccess.bak).'];
    }

    public function htaccessDisable(): array
    {
        $f = $this->htFile();
        if (!$this->htaccessActive()) {
            return ['ok' => true, 'message' => 'Kein Block vorhanden.'];
        }
        $cur = (string)file_get_contents($f);
        $new = preg_replace('~' . preg_quote(self::MARK, '~') . '.*?' . preg_quote(self::MARK_END, '~') . "\n?\n?~s", '', $cur);
        $tmp = $f . '.tmp' . bin2hex(random_bytes(3));
        if ($new === null || @file_put_contents($tmp, $new) === false || !@rename($tmp, $f)) {
            @unlink($tmp);
            return ['ok' => false, 'message' => 'Die .htaccess konnte nicht geschrieben werden.'];
        }
        return ['ok' => true, 'message' => 'Block entfernt.'];
    }

    // ---------------------------------------------------------------- Übersicht

    public function capabilities(): array
    {
        $gd = extension_loaded('gd') ? gd_info() : [];
        $mem = (string)ini_get('memory_limit');
        return [
            ['label' => 'PHP', 'status' => version_compare(PHP_VERSION, '8.2.0', '>=') ? 'ok' : 'info', 'text' => PHP_VERSION],
            ['label' => 'OPcache', 'status' => function_exists('opcache_get_status') && ini_get('opcache.enable') ? 'ok' : 'warn', 'text' => function_exists('opcache_get_status') && ini_get('opcache.enable') ? 'aktiv (beschleunigt PHP deutlich)' : 'nicht aktiv – beim Hoster einschalten, falls möglich'],
            ['label' => 'Kompression (zlib)', 'status' => extension_loaded('zlib') ? 'ok' : 'warn', 'text' => extension_loaded('zlib') ? 'verfügbar' : 'nicht verfügbar'],
            ['label' => 'Bildbibliothek (GD)', 'status' => $gd ? 'ok' : 'warn', 'text' => $gd ? 'GD ' . ($gd['GD Version'] ?? '') : 'fehlt – keine Bildvarianten möglich'],
            ['label' => 'WebP', 'status' => !empty($gd['WebP Support']) ? 'ok' : 'warn', 'text' => !empty($gd['WebP Support']) ? 'unterstützt' : 'nicht unterstützt'],
            ['label' => 'AVIF', 'status' => self::avifSupported() ? 'ok' : 'info', 'text' => self::avifSupported() ? 'unterstützt' : 'nicht unterstützt (WebP bleibt aktiv)'],
            ['label' => 'Speicherlimit', 'status' => 'info', 'text' => $mem],
            ['label' => 'Browser-Caching (.htaccess)', 'status' => $this->htaccessActive() ? 'ok' : 'info', 'text' => $this->htaccessActive() ? 'Block ist eingetragen' : 'nicht eingetragen (optional, Aktion unten)'],
        ];
    }

    public function mediaStats(): array
    {
        $webp = glob($this->np->cmsDir() . '/media/library/*/*.webp') ?: [];
        $avif = glob($this->np->cmsDir() . '/media/library/*/*.avif') ?: [];
        return ['webp' => count($webp), 'avif' => count($avif)];
    }
}

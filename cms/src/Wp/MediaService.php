<?php
declare(strict_types=1);
// cms/src/Wp/MediaService.php – Dienstschicht für Medien: prüft Datei (Endung, echter Inhalt, Größe, Name) und Metadaten, wendet die Rechte an und ruft den Adapter auf.
// Erlaubt sind Bilder (JPEG, PNG, GIF, WebP, AVIF), PDF, Audio (MP3, OGG, WAV, M4A) und Video (MP4, WebM). SVG, ZIP, HTML und alles Ausführbare werden abgelehnt.

namespace Elvado\Wp;

use Elvado\Wp\Adapter\MediaAdapter;
use Elvado\Wp\Adapter\WordPressMediaAdapter;

final class MediaService
{
    public const MAX_BYTES = 33_554_432;   // 32 MB

    /** Endung ⇒ zulässige, vom Inhalt erkannte MIME-Typen. */
    private const SNIFF = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'gif' => ['image/gif'], 'webp' => ['image/webp'], 'avif' => ['image/avif'],
        'pdf' => ['application/pdf'], 'mp3' => ['audio/mpeg', 'application/octet-stream'], 'ogg' => ['audio/ogg', 'application/ogg', 'video/ogg'],
        'wav' => ['audio/wav', 'audio/x-wav', 'audio/vnd.wave'], 'm4a' => ['audio/mp4', 'audio/x-m4a', 'video/mp4'], 'mp4' => ['video/mp4'], 'webm' => ['video/webm', 'audio/webm'],
    ];
    private const MIME = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'avif' => 'image/avif', 'pdf' => 'application/pdf',
        'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav', 'm4a' => 'audio/mp4', 'mp4' => 'video/mp4', 'webm' => 'video/webm'];

    public function __construct(private readonly MediaAdapter $adapter, private readonly ?Actor $actor = null)
    {
    }

    private function actor(): Actor { return $this->actor ?? new Actor('', 'admin'); }

    public function adapter(): MediaAdapter { return $this->adapter; }

    /** @param array<string,mixed> $q @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int} */
    public function list(array $q): array
    {
        $kind = (string)($q['kind'] ?? 'all');
        if (!in_array($kind, ['all', 'image', 'video', 'audio', 'document'], true)) {
            throw new \InvalidArgumentException('Unbekannte Medienart.');
        }
        $per = max(1, min(100, (int)($q['per_page'] ?? 24)));
        $page = max(1, (int)($q['page'] ?? 1));
        return $this->adapter->items(['kind' => $kind, 'search' => mb_substr(trim((string)($q['search'] ?? '')), 0, 100), 'per_page' => $per, 'page' => $page]) + ['page' => $page, 'per_page' => $per];
    }

    public function get(string $id): ?array
    {
        return preg_match('/^[A-Za-z0-9]{1,40}$/', $id) === 1 ? $this->adapter->item($id) : null;
    }

    /**
     * @param array{title?:string,alt?:string,caption?:string} $meta
     * @return array<string,mixed>
     */
    public function upload(string $tmpFile, string $origName, array $meta = []): array
    {
        if (!$this->adapter->writable()) {
            throw new \RuntimeException('Die Mediathek ist schreibgeschützt (WordPress-Engine nicht aktiv).');
        }
        if (!$this->actor()->can('media_write')) {
            throw new PermissionException('Dafür fehlt die Berechtigung.');
        }
        $name = self::cleanName($origName);
        $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
        if (!isset(self::SNIFF[$ext])) {
            throw new \InvalidArgumentException('Dieser Dateityp ist nicht erlaubt (erlaubt: Bilder, PDF, Audio, Video).');
        }
        if (!is_file($tmpFile) || ($size = (int)filesize($tmpFile)) <= 0) {
            throw new \InvalidArgumentException('Die Datei ist leer oder fehlt.');
        }
        if ($size > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Die Datei ist größer als ' . (self::MAX_BYTES / 1048576) . ' MB.');
        }
        $mime = (string)(function_exists('finfo_open') ? (new \finfo(FILEINFO_MIME_TYPE))->file($tmpFile) : '');
        if ($mime === '' || !in_array($mime, self::SNIFF[$ext], true)) {
            throw new \InvalidArgumentException('Der Inhalt der Datei passt nicht zur Endung .' . $ext . '.');
        }
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true) && @getimagesize($tmpFile) === false) {
            throw new \InvalidArgumentException('Die Bilddatei ist beschädigt oder kein Bild.');
        }
        if ($ext === 'pdf' && file_get_contents($tmpFile, false, null, 0, 5) !== '%PDF-') {
            throw new \InvalidArgumentException('Die PDF-Datei ist ungültig.');
        }
        if (self::hasPhp($tmpFile)) {
            throw new \InvalidArgumentException('Die Datei enthält ausführbaren Code und wird abgelehnt.');
        }
        $m = [];
        foreach (['title' => 200, 'alt' => 300, 'caption' => 500] as $k => $max) {
            if (isset($meta[$k])) {
                $m[$k] = mb_substr(trim(strip_tags((string)$meta[$k])), 0, $max);
            }
        }
        if ($this->actor()->login !== '') {
            $m['owner'] = $this->actor()->login;
        }
        return $this->adapter->add($tmpFile, $name, self::MIME[$ext], $m);
    }

    /** @param array<string,mixed> $in */
    public function update(string $id, array $in): array
    {
        $ex = $this->owned($id);
        $d = [];
        foreach (['title' => 200, 'alt' => 300, 'caption' => 500] as $k => $max) {
            if (array_key_exists($k, $in)) {
                $d[$k] = mb_substr(trim(strip_tags((string)$in[$k])), 0, $max);
            }
        }
        return $d === [] ? $ex : $this->adapter->update($id, $d);
    }

    public function delete(string $id): bool
    {
        if (!$this->adapter->writable()) {
            throw new \RuntimeException('Die Mediathek ist schreibgeschützt (WordPress-Engine nicht aktiv).');
        }
        if ($this->get($id) === null) {
            return false;
        }
        $this->owned($id);
        return $this->adapter->delete($id);
    }

    /** Eigenes Medium oder Administrator. @return array<string,mixed> */
    private function owned(string $id): array
    {
        if (!$this->adapter->writable()) {
            throw new \RuntimeException('Die Mediathek ist schreibgeschützt (WordPress-Engine nicht aktiv).');
        }
        $ex = $this->get($id);
        if ($ex === null) {
            throw new \RuntimeException('Das Medium wurde nicht gefunden.');
        }
        $a = $this->actor();
        if (!$a->can('media_any') && ($a->login === '' || strcasecmp((string)($ex['owner'] ?? ''), $a->login) !== 0)) {
            throw new PermissionException('Du darfst nur eigene Medien ändern.');
        }
        return $ex;
    }

    /** Dateiname ohne Pfad und Sonderzeichen; mehrfache Endungen (a.php.jpg) werden entschärft. */
    public static function cleanName(string $n): string
    {
        $n = basename(str_replace('\\', '/', $n));
        $n = (string)preg_replace('/[^\p{L}\p{N}._ -]+/u', '', $n);
        $n = trim((string)preg_replace('/\s+/', ' ', $n), " .-");
        $ext = strtolower((string)pathinfo($n, PATHINFO_EXTENSION));
        $base = (string)pathinfo($n, PATHINFO_FILENAME);
        $base = str_replace('.', '_', $base);   // keine versteckte Zweitendung
        $base = mb_substr($base, 0, 100);
        return $ext === '' ? $base : ($base !== '' ? $base : 'datei') . '.' . $ext;
    }

    private static function hasPhp(string $file): bool
    {
        $h = fopen($file, 'rb');
        if (!$h) {
            return true;
        }
        $tail = '';
        while (!feof($h)) {
            $chunk = (string)fread($h, 1048576);
            $buf = $tail . $chunk;
            if (stripos($buf, '<?php') !== false || strpos($buf, '<?=') !== false) {
                fclose($h);
                return true;
            }
            $tail = substr($buf, -8);
        }
        fclose($h);
        return false;
    }
}

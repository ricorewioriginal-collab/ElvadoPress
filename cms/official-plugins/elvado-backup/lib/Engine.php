<?php
declare(strict_types=1);
// Backup-Engine: erstellen (mit Prüfsummen, ohne Geheimnisse), prüfen, wiederherstellen (mit Sicherheitskopie und Rückgängig), rotieren.

namespace ElvadoPlugin\Backup;

use Elvado\Plugin\Context;
use Elvado\Plugin\Fs;

final class Engine
{
    public const PREFIX = 'elvado-backup_';
    private const SECRET_KEY = '/(api[_-]?key|secret|token|passwo?r?d|passwd|private[_-]?key|smtp_pass|client_secret|salt)/i';
    /** Dateien/Ordner unterhalb von cms/data, die nie ins Backup gehören (relativ zu cms/data/). */
    private const EXCLUDE = ['local-auth.local.php', 'local-sessions.local.php', 'database.local.php', 'system.local.json', '.site.lock', '.update', 'backups', '.plugins/backup', '.plugins/log.txt', '.plugins/log.txt.1', '.plugins/tick',
        '.plugins/data/elvado-security/salt.txt', '.plugins/data/elvado-security/lockouts.json', '.plugins/data/elvado-performance/cache', '.plugins/data/elvado-analytics/visitors', '.tools/forms-salt.json'];

    public function __construct(private readonly Context $np) {}

    private function cms(): string { return $this->np->cmsDir(); }
    private function root(): string { return $this->np->rootDir(); }

    public function dir(): string
    {
        $d = $this->cms() . '/backups';
        if (!is_dir($d)) {
            @mkdir($d, 0755, true);
        }
        require_once $this->cms() . '/lib/tools.php';
        rrw_protect_dir($d);
        return realpath($d) ?: $d;
    }

    private static function excluded(string $relToData): bool
    {
        $p = ltrim(str_replace('\\', '/', $relToData), '/');
        if (preg_match('/\.local\.(php|json)$/', $p) || str_ends_with($p, '.example')) {
            return true;
        }
        foreach (self::EXCLUDE as $e) {
            if ($p === $e || str_starts_with($p, $e . '/')) {
                return true;
            }
        }
        return false;
    }

    // ---------------------------------------------------------------- Geheimnisse

    public static function redact(mixed $v): mixed
    {
        if (!is_array($v)) {
            return $v;
        }
        $out = [];
        foreach ($v as $k => $x) {
            $out[$k] = is_string($k) && preg_match(self::SECRET_KEY, $k) && is_string($x) ? '' : self::redact($x);
        }
        return $out;
    }

    /** Beim Wiederherstellen: leere (entfernte) Geheimnisse behalten den Wert der laufenden Installation. */
    public static function mergeSecrets(mixed $new, mixed $live): mixed
    {
        if (!is_array($new) || !is_array($live)) {
            return $new;
        }
        foreach ($new as $k => $x) {
            if (is_string($k) && preg_match(self::SECRET_KEY, $k) && $x === '' && isset($live[$k]) && is_string($live[$k])) {
                $new[$k] = $live[$k];
            } elseif (array_key_exists($k, $live)) {
                $new[$k] = self::mergeSecrets($x, $live[$k]);
            }
        }
        return $new;
    }

    // ---------------------------------------------------------------- Erstellen

    /** @return list<array{0:string,1:string}> [Pfad im ZIP, Quelldatei] */
    private function collect(bool $media): array
    {
        $cms = $this->cms();
        $out = [];
        $addDir = function (string $abs, string $zipPrefix, callable $skip) use (&$out): void {
            foreach (Fs::files($abs) as $rel) {
                if (!$skip($rel)) {
                    $out[] = [$zipPrefix . '/' . $rel, $abs . '/' . $rel];
                }
            }
        };
        $addDir($cms . '/data', 'cms/data', fn($rel) => self::excluded($rel));
        foreach (['themes', 'plugins', 'content'] as $d) {
            $addDir($cms . '/' . $d, 'cms/' . $d, fn($rel) => str_starts_with(basename($rel), '.tmp-') || str_contains($rel, '/.tmp-'));
        }
        if ($media) {
            $addDir($cms . '/media', 'cms/media', fn($rel) => false);
        }
        foreach (glob($this->root() . '/*.html') ?: [] as $p) {
            if (str_contains((string)@file_get_contents($p, false, null, 0, 128), 'RRW-CMS-GENERATED')) {
                $out[] = [basename($p), $p];
            }
        }
        foreach (['rss.xml', 'sitemap.xml', 'robots.txt'] as $f) {
            if (is_file($this->root() . '/' . $f)) {
                $out[] = [$f, $this->root() . '/' . $f];
            }
        }
        return $out;
    }

    /** @return array{name:string,size:int,created_at:string,files:int} */
    public function create(bool $media, string $prefix = self::PREFIX): array
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('Die PHP-Erweiterung „zip“ fehlt auf diesem Server – Backups sind nicht möglich.');
        }
        $dir = $this->dir();
        if (!is_writable($dir)) {
            throw new \RuntimeException('Der Backup-Ordner ist nicht beschreibbar (' . basename(dirname($dir)) . '/backups).');
        }
        $free = @disk_free_space($dir);
        $name = $prefix . date('Y-m-d_H-i-s') . '.zip';
        $final = $dir . '/' . $name;
        $tmp = $dir . '/.partial-' . bin2hex(random_bytes(4)) . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Die Backup-Datei konnte nicht angelegt werden.');
        }
        $sums = [];
        $tmpFiles = [];
        $total = 0;
        try {
            foreach ($this->collect($media) as [$entry, $src]) {
                if (!is_readable($src)) {
                    continue;
                }
                $isJson = str_ends_with($src, '.json') && str_starts_with($entry, 'cms/data/');
                if ($isJson) {   // Geheimnisse entfernen
                    $d = json_decode((string)file_get_contents($src), true);
                    if (is_array($d)) {
                        $data = json_encode(self::redact($d), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
                        $zip->addFromString($entry, $data);
                        $sums[$entry] = sha1($data);
                        $total += strlen($data);
                        continue;
                    }
                }
                $zip->addFile($src, $entry);
                $sums[$entry] = (string)sha1_file($src);
                $total += (int)@filesize($src);
            }
            if ($free !== false && $free < $total * 0.5 + 1048576) {
                throw new \RuntimeException('Zu wenig freier Speicherplatz für das Backup.');
            }
            $manifest = ['version' => 2, 'created_at' => date(DATE_ATOM), 'cms_version' => $this->np->cmsVersion(), 'include_media' => $media, 'redacted' => true,
                'note' => 'Passwörter, Schlüssel, Zugangsdaten und serverlokale Dateien sind nicht enthalten und müssen nach einer Wiederherstellung auf einem neuen Server neu eingetragen werden.', 'files' => $sums];
            $zip->addFromString('backup.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            if (!$zip->close()) {
                throw new \RuntimeException('Die Backup-Datei konnte nicht geschrieben werden (Speicherplatz oder Rechte).');
            }
            if (!@rename($tmp, $final)) {
                throw new \RuntimeException('Die Backup-Datei konnte nicht abgelegt werden.');
            }
        } catch (\Throwable $e) {
            @$zip->close();
            @unlink($tmp);
            throw $e;
        }
        @chmod($final, 0640);
        return ['name' => $name, 'size' => (int)filesize($final), 'created_at' => $manifest['created_at'], 'files' => count($sums)];
    }

    // ---------------------------------------------------------------- Prüfen

    /** @return array{ok:bool,errors:list<string>,manifest:?array} */
    public function verify(string $name): array
    {
        $file = $this->dir() . '/' . basename($name);
        if (!is_file($file)) {
            return ['ok' => false, 'errors' => ['Das Backup wurde nicht gefunden.'], 'manifest' => null];
        }
        $zip = new \ZipArchive();
        if ($zip->open($file) !== true) {
            return ['ok' => false, 'errors' => ['Die Datei ist kein lesbares ZIP-Archiv (beschädigt?).'], 'manifest' => null];
        }
        $raw = $zip->getFromName('backup.json');
        $m = $raw !== false ? json_decode($raw, true) : null;
        if (!is_array($m)) {
            $zip->close();
            return ['ok' => false, 'errors' => ['backup.json fehlt oder ist ungültig – dies ist kein ElvadoPress-Backup.'], 'manifest' => null];
        }
        $errors = [];
        if ((int)($m['version'] ?? 0) >= 2) {
            foreach ((array)($m['files'] ?? []) as $entry => $sum) {
                $d = $zip->getFromName((string)$entry);
                if ($d === false) {
                    $errors[] = 'Fehlt im Archiv: ' . $entry;
                } elseif (sha1($d) !== $sum) {
                    $errors[] = 'Prüfsumme stimmt nicht: ' . $entry;
                }
                if (count($errors) >= 10) {
                    break;
                }
            }
        }
        $zip->close();
        return ['ok' => !$errors, 'errors' => $errors, 'manifest' => $m];
    }

    // ---------------------------------------------------------------- Wiederherstellen

    private static function safeEntry(string $e): bool
    {
        $e = str_replace('\\', '/', $e);
        return $e !== '' && !str_contains($e, '../') && !str_starts_with($e, '/') && !preg_match('/^[A-Za-z]:/', $e) && !str_contains($e, "\0");
    }

    /** @throws \RuntimeException */
    public function restore(string $name): void
    {
        $name = basename($name);
        $v = $this->verify($name);
        if (!$v['ok']) {
            throw new \RuntimeException('Das Backup ist beschädigt und wird nicht eingespielt: ' . implode(' ', array_slice($v['errors'], 0, 3)));
        }
        $m = $v['manifest'];
        $safety = $this->create(false, 'vor-wiederherstellung_');   // Rückfallpunkt
        $this->rotate(0, 'vor-wiederherstellung_', 3);
        $zip = new \ZipArchive();
        $zip->open($this->dir() . '/' . $name);
        require_once $this->cms() . '/lib/publish.php';
        $written = 0;
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = str_replace('\\', '/', (string)$zip->getNameIndex($i));
                if (!self::safeEntry($entry) || str_ends_with($entry, '/') || $entry === 'backup.json') {
                    continue;
                }
                $allowed = in_array($entry, ['rss.xml', 'sitemap.xml', 'robots.txt'], true) || (bool)preg_match('/^[a-z0-9][a-z0-9-]*\.html$/i', $entry);
                foreach (['cms/data/', 'cms/media/', 'cms/themes/', 'cms/plugins/', 'cms/content/'] as $p) {
                    $allowed = $allowed || str_starts_with($entry, $p);
                }
                if (!$allowed || (str_starts_with($entry, 'cms/data/') && self::excluded(substr($entry, 9)))) {
                    continue;
                }
                $data = $zip->getFromIndex($i);
                if ($data === false) {
                    throw new \RuntimeException('Eintrag nicht lesbar: ' . $entry);
                }
                $dest = $this->root() . '/' . $entry;
                if (!is_dir(dirname($dest)) && !@mkdir(dirname($dest), 0755, true)) {
                    throw new \RuntimeException('Ordner nicht beschreibbar: ' . dirname($entry));
                }
                if (!empty($m['redacted']) && str_starts_with($entry, 'cms/data/') && str_ends_with($entry, '.json') && is_file($dest)) {
                    $new = json_decode($data, true);
                    $live = json_decode((string)file_get_contents($dest), true);
                    if (is_array($new) && is_array($live)) {
                        $data = json_encode(self::mergeSecrets($new, $live), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
                    }
                }
                rrw_write_atomic($dest, $data);
                $written++;
            }
        } catch (\Throwable $e) {
            $zip->close();
            try {   // Rückgängig: Stand vor der Wiederherstellung einspielen
                $this->rawRestore($safety['name']);
            } catch (\Throwable) {
            }
            throw new \RuntimeException('Wiederherstellung fehlgeschlagen und zurückgenommen: ' . $e->getMessage());
        }
        $zip->close();
        $this->np->log('Backup ' . $name . ' wiederhergestellt (' . $written . ' Dateien), Rückfallpunkt ' . $safety['name']);
    }

    private function rawRestore(string $name): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($this->dir() . '/' . basename($name)) !== true) {
            throw new \RuntimeException('Rückfallpunkt nicht lesbar.');
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = str_replace('\\', '/', (string)$zip->getNameIndex($i));
            if (!self::safeEntry($entry) || str_ends_with($entry, '/') || $entry === 'backup.json' || (str_starts_with($entry, 'cms/data/') && self::excluded(substr($entry, 9)))) {
                continue;
            }
            $data = $zip->getFromIndex($i);
            if ($data !== false) {
                $dest = $this->root() . '/' . $entry;
                if (!is_dir(dirname($dest))) {
                    @mkdir(dirname($dest), 0755, true);
                }
                rrw_write_atomic($dest, $data);
            }
        }
        $zip->close();
    }

    // ---------------------------------------------------------------- Liste / Rotation

    /** @return list<array{name:string,size:int,created_at:string,kind:string}> neueste zuerst */
    public function list(): array
    {
        $out = [];
        foreach (glob($this->dir() . '/*.zip') ?: [] as $f) {
            $n = basename($f);
            $out[] = ['name' => $n, 'size' => (int)filesize($f), 'created_at' => date('Y-m-d H:i:s', (int)filemtime($f)), 'kind' => str_starts_with($n, 'vor-wiederherstellung_') ? 'Sicherheitskopie' : (str_starts_with($n, self::PREFIX) ? 'Backup' : 'Backup (Core)')];
        }
        usort($out, static fn($a, $b) => strcmp($b['created_at'], $a['created_at']) ?: strcmp($b['name'], $a['name']));
        return $out;
    }

    /** Älteste Backups löschen (nur Dateien mit diesem Namensanfang, '' = alle außer Sicherheitskopien). */
    public function rotate(int $keep, string $prefix = '', int $keepSafety = 3): int
    {
        $n = 0;
        $list = array_values(array_filter($this->list(), static fn($b) => $prefix === '' ? $b['kind'] !== 'Sicherheitskopie' : str_starts_with($b['name'], $prefix)));
        $limit = $prefix !== '' ? $keepSafety : $keep;
        foreach (array_slice($list, max(0, $limit)) as $b) {
            if (@unlink($this->dir() . '/' . $b['name'])) {
                $n++;
            }
        }
        return $n;
    }

    public function delete(string $name): bool
    {
        $f = $this->dir() . '/' . basename($name);
        return is_file($f) && preg_match('/\.zip$/', $f) === 1 && @unlink($f);
    }

    // ---------------------------------------------------------------- Zeitplan

    private function stateFile(): string { return $this->np->dataDir() . '/schedule.json'; }

    public function lastRun(): array { return Fs::readJson($this->stateFile()); }

    /** Nächster Zeitpunkt, ab dem ein automatisches Backup fällig ist (null = kein Zeitplan). */
    public function nextDue(?int $now = null): ?int
    {
        $sched = (string)$this->np->setting('schedule');
        if ($sched === 'off') {
            return null;
        }
        $now ??= time();
        $hour = max(0, min(23, (int)$this->np->setting('hour')));
        $last = (int)($this->lastRun()['last_ok_ts'] ?? 0);
        $due = null;
        for ($d = 0; $d <= 8; $d++) {   // erste passende Zeit nach dem letzten Lauf
            $t = strtotime(date('Y-m-d', $now - 86400 * 2 + 86400 * $d) . ' ' . sprintf('%02d:00:00', $hour));
            if ($t === false || $t <= $last) {
                continue;
            }
            if ($sched === 'weekly' && (int)date('N', $t) !== (int)$this->np->setting('weekday')) {
                continue;
            }
            $due = $t;
            break;
        }
        return $due;
    }

    /** Aktion tick: fälliges automatisches Backup erstellen. */
    public function tick(int $now): void
    {
        $due = $this->nextDue($now);
        if ($due === null || $due > $now) {
            return;
        }
        $lock = @fopen($this->np->dataDir() . '/run.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return;
        }
        try {
            $r = $this->create((bool)$this->np->setting('include_media'));
            $this->rotate((int)$this->np->setting('keep'));
            Fs::writeJson($this->stateFile(), ['last_ok_ts' => $now, 'last_ok' => date(DATE_ATOM, $now), 'last_name' => $r['name'], 'last_error' => '']);
            $this->np->log('automatisches Backup ' . $r['name']);
        } catch (\Throwable $e) {
            $prev = $this->lastRun();
            Fs::writeJson($this->stateFile(), ['last_ok_ts' => (int)($prev['last_ok_ts'] ?? 0), 'last_ok' => (string)($prev['last_ok'] ?? ''), 'last_error' => mb_substr($e->getMessage(), 0, 300), 'last_error_at' => date(DATE_ATOM, $now), 'retry_after' => $now + 3600]);
            $this->np->log('automatisches Backup fehlgeschlagen: ' . $e->getMessage());
            $to = (string)$this->np->setting('notify_email');
            if ($to !== '' && function_exists('rrw_send_mail')) {
                @rrw_send_mail($to, 'Automatisches Backup fehlgeschlagen', "Das automatische Backup konnte nicht erstellt werden:\n\n" . $e->getMessage() . "\n", 'noreply@' . preg_replace('/^www\./', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')));
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}

<?php
declare(strict_types=1);
// cms/src/Wp/CoreInstaller.php – WordPress-Core sicher entpacken und einspielen.
// Das Paket kommt aus dem Netz: Prüfsumme, keine Pfadtricks, keine Symlinks, keine versteckten Dateien (außer .htaccess), Endungs-Allowlist, Mengen-/Größenlimits,
// alle PHP-Dateien werden vor dem Einspielen geparst (Syntaxfehler → Abbruch, ohne etwas auszuführen). Eingespielt wird atomar nach cms/wp-engine/core-<Version>/;
// der Ordner wp-content/ des Pakets (Standard-Plugins/-Themes) wird NICHT übernommen – Inhalte bleiben in cms/wp-content.

namespace Elvado\Wp;

final class CoreInstaller
{
    public const EXT = ['php', 'js', 'css', 'json', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico', 'html', 'htm', 'txt', 'md', 'xml', 'xsl', 'scss', 'map', 'woff', 'woff2', 'ttf', 'eot', 'otf', 'crt', 'pem', 'mo', 'po', 'pot', 'htaccess'];
    public const NAMES = ['LICENSE', 'license', 'readme', 'README'];
    public const MAX_TOTAL = 314_572_800;   // 300 MB entpackt
    public const MAX_FILE = 31_457_280;     // 30 MB je Datei
    public const MAX_FILES = 12000;
    public const ESSENTIAL = ['wp-load.php', 'wp-settings.php', 'wp-includes/version.php', 'wp-includes/class-wpdb.php', 'wp-admin/includes/upgrade.php', 'wp-includes/plugin.php'];

    public function __construct(private readonly Engine $engine)
    {
    }

    /** @return array{ok:bool,message:string,core:string,files:int,skipped:int} */
    public function install(string $zipFile, string $version, string $sha1): array
    {
        $fail = fn(string $m): array => ['ok' => false, 'message' => $m, 'core' => '', 'files' => 0, 'skipped' => 0];
        if (!CoreSource::validVersion($version)) {
            return $fail('Ungültige Versionsnummer.');
        }
        if (!is_file($zipFile) || !hash_equals(strtolower($sha1), strtolower((string)sha1_file($zipFile)))) {
            return $fail('Die Prüfsumme der Datei stimmt nicht – es wird nichts eingespielt.');
        }
        if (!class_exists(\ZipArchive::class)) {
            return $fail('Die PHP-Erweiterung zip fehlt auf diesem Server.');
        }
        $core = 'core-' . $version;
        $root = $this->engine->coreRoot();
        $this->engine->protect();
        if (is_dir($root . '/' . $core)) {
            if ($this->engine->state()['core'] !== $core) {   // gemeinsamer Core (mehrere Websites): diese Engine übernimmt die vorhandene Version
                $s = $this->engine->state();
                $prev = $s['core'] !== '' ? array_values(array_unique(array_merge([$s['core']], $s['previous']))) : $s['previous'];
                $this->engine->save(['core' => $core, 'version' => $version, 'installed_at' => date('c'), 'previous' => array_slice($prev, 0, 1)]);
                $this->engine->log('WordPress ' . $version . ' übernommen (bereits vorhandener Core)');
            }
            return ['ok' => true, 'message' => 'Diese Version ist schon eingespielt.', 'core' => $core, 'files' => 0, 'skipped' => 0];
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipFile) !== true) {
            return $fail('Das Paket ist kein lesbares ZIP-Archiv.');
        }
        $stage = $root . '/.staging-' . bin2hex(random_bytes(6));
        try {
            [$entries, $skipped] = $this->plan($zip);
            if (!@mkdir($stage, 0750, true) && !is_dir($stage)) {
                throw new \RuntimeException('Der Zwischenordner kann nicht angelegt werden.');
            }
            $written = 0;
            $sum = 0;
            foreach ($entries as $idx => $rel) {
                $to = $stage . '/' . $rel;
                if (!is_dir(dirname($to)) && !@mkdir(dirname($to), 0750, true) && !is_dir(dirname($to))) {
                    throw new \RuntimeException('Ordner kann nicht angelegt werden: ' . dirname($rel));
                }
                $in = $zip->getStream($zip->getNameIndex($idx));
                $out = $in ? @fopen($to, 'wb') : false;
                if (!$in || !$out) {
                    throw new \RuntimeException('Datei kann nicht entpackt werden: ' . $rel);
                }
                $n = 0;
                while (!feof($in)) {
                    $chunk = (string)fread($in, 65536);
                    $n += strlen($chunk);
                    $sum += strlen($chunk);
                    if ($n > self::MAX_FILE || $sum > self::MAX_TOTAL) {
                        fclose($in);
                        fclose($out);
                        throw new \RuntimeException('Das Paket ist größer als angegeben und wurde abgebrochen.');
                    }
                    fwrite($out, $chunk);
                }
                fclose($in);
                fclose($out);
                $written++;
            }
            $zip->close();
            $this->verify($stage, $version);
            if (!@rename($stage, $root . '/' . $core)) {
                throw new \RuntimeException('Der Core konnte nicht an seinen Platz verschoben werden.');
            }
        } catch (\Throwable $e) {
            try {
                $zip->close();
            } catch (\Throwable) {
                // Archiv war schon geschlossen
            }
            $this->rmTree($stage);
            $this->engine->log('Core-Installation fehlgeschlagen: ' . $e->getMessage());
            return $fail($e->getMessage());
        }
        $s = $this->engine->state();
        $prev = $s['core'] !== '' && $s['core'] !== $core ? array_values(array_unique(array_merge([$s['core']], $s['previous']))) : $s['previous'];
        $this->engine->save(['core' => $core, 'version' => $version, 'installed_at' => date('c'), 'previous' => array_slice($prev, 0, 1)]);
        $this->prune($core, array_slice($prev, 0, 1));
        $this->engine->log('WordPress ' . $version . ' eingespielt (' . $written . ' Dateien, ' . $skipped . ' übersprungen)');
        return ['ok' => true, 'message' => '', 'core' => $core, 'files' => $written, 'skipped' => $skipped];
    }

    /** Prüft alle Einträge und liefert [Index => relativer Pfad unter wordpress/] sowie die Zahl der bewusst übersprungenen. */
    private function plan(\ZipArchive $zip): array
    {
        $out = [];
        $skipped = 0;
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $st = $zip->statIndex($i);
            if ($st === false) {
                throw new \RuntimeException('Das Paket ist beschädigt.');
            }
            $name = (string)$st['name'];
            if (str_ends_with($name, '/')) {
                continue;
            }
            if (str_contains($name, '\\') || str_contains($name, "\0") || preg_match('/[\x00-\x1f]/', $name) === 1 || !str_starts_with($name, 'wordpress/') || strlen($name) > 220) {
                throw new \RuntimeException('Das Paket enthält einen unzulässigen Pfad.');
            }
            $rel = substr($name, strlen('wordpress/'));
            if (str_starts_with($rel, 'wp-content/')) {
                $skipped++;
                continue;
            }
            foreach (explode('/', $rel) as $part) {
                if ($part === '' || $part === '.' || $part === '..' || ($part[0] === '.' && $part !== '.htaccess')) {
                    throw new \RuntimeException('Das Paket enthält einen unzulässigen Pfad: ' . $rel);
                }
            }
            $opsys = 0;
            $attr = 0;
            $zip->getExternalAttributesIndex($i, $opsys, $attr);
            if ($opsys === \ZipArchive::OPSYS_UNIX && (($attr >> 16) & 0170000) === 0120000) {
                throw new \RuntimeException('Das Paket enthält eine symbolische Verknüpfung: ' . $rel);
            }
            $base = basename($rel);
            $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
            if ($base === '.htaccess') {
                $ext = 'htaccess';
            }
            if (!in_array($ext, self::EXT, true) && !in_array($base, self::NAMES, true)) {
                throw new \RuntimeException('Das Paket enthält einen nicht erlaubten Dateityp: ' . $rel);
            }
            $size = (int)$st['size'];
            $total += $size;
            if ($size > self::MAX_FILE || $total > self::MAX_TOTAL || count($out) >= self::MAX_FILES) {
                throw new \RuntimeException('Das Paket überschreitet die erlaubte Größe oder Dateianzahl.');
            }
            $out[$i] = $rel;
        }
        if ($out === []) {
            throw new \RuntimeException('Das Paket enthält keine WordPress-Dateien.');
        }
        return [$out, $skipped];
    }

    private function verify(string $dir, string $version): void
    {
        foreach (self::ESSENTIAL as $f) {
            if (!is_file($dir . '/' . $f)) {
                throw new \RuntimeException('Im Paket fehlt eine wichtige Datei: ' . $f);
            }
        }
        $v = (string)file_get_contents($dir . '/wp-includes/version.php');
        if (!preg_match('/\$wp_version\s*=\s*\'([^\']+)\'/', $v, $m) || $m[1] !== $version) {
            throw new \RuntimeException('Die Version im Paket (' . ($m[1] ?? '?') . ') passt nicht zur erwarteten (' . $version . ').');
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            /** @var \SplFileInfo $f */
            if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
                try {
                    token_get_all((string)file_get_contents($f->getPathname()), TOKEN_PARSE);
                } catch (\Throwable $e) {
                    throw new \RuntimeException('Eine PHP-Datei im Paket ist fehlerhaft: ' . substr($f->getPathname(), strlen($dir) + 1));
                }
            }
        }
    }

    /** Alte Core-Ordner entfernen (behalten: der aktuelle und der letzte). */
    private function prune(string $current, array $keep): void
    {
        // Der Core-Ordner gehört allen Websites: Versionen, die eine andere Website (cms/sites/*/data/.wp-engine) noch nutzt, bleiben
        $files = array_merge([dirname($this->engine->stateDir()) . '/.wp-engine/state.json'], glob($this->engine->cmsDir() . '/sites/*/data/.wp-engine/state.json') ?: [], [$this->engine->cmsDir() . '/data/.wp-engine/state.json']);
        foreach (array_unique($files) as $f) {
            $d = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
            if (is_array($d)) {
                $keep = array_merge($keep, [(string)($d['core'] ?? '')], array_map('strval', (array)($d['previous'] ?? [])));
            }
        }
        foreach (glob($this->engine->coreRoot() . '/core-*', GLOB_ONLYDIR) ?: [] as $d) {
            $b = basename($d);
            if ($b !== $current && !in_array($b, $keep, true)) {
                $this->rmTree($d);
            }
        }
    }

    public function rmTree(string $dir): void
    {
        if ($dir === '' || !is_dir($dir) || strlen($dir) < 12) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}

<?php
declare(strict_types=1);
// cms/src/Wp/ExtensionInstaller.php – Plugin- und Theme-Pakete (ZIP) prüfen und installieren: von wordpress.org geladen oder hochgeladen.
// Geprüft wird vor dem Entpacken (Pfade, Verknüpfungen, Dateitypen, Größen) und danach (Kopfzeile des Plugins/Themes, PHP-Syntax jeder PHP-Datei). Installiert wird atomar;
// eine vorhandene Fassung wird als Sicherung zur Seite gelegt. Ein Plugin/Theme ist PHP-Code, der mit den Rechten von ElvadoPress läuft: Installieren dürfen nur Administratoren.

namespace Elvado\Wp;

final class ExtensionInstaller
{
    public const EXT = ['php', 'js', 'mjs', 'css', 'scss', 'less', 'json', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico', 'bmp', 'html', 'htm', 'txt', 'md', 'markdown', 'xml', 'xsl', 'xslt',
        'po', 'pot', 'mo', 'csv', 'yml', 'yaml', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'map', 'mp3', 'ogg', 'mp4', 'webm', 'pdf', 'twig', 'mustache', 'tpl', 'lock', 'dist'];
    public const NAMES = ['LICENSE', 'license', 'LICENCE', 'COPYING', 'readme', 'README', 'CHANGELOG', 'NOTICE', 'VERSION', 'Makefile'];
    public const MAX_TOTAL = 157_286_400;   // 150 MB entpackt
    public const MAX_FILE = 31_457_280;     // 30 MB je Datei
    public const MAX_FILES = 8000;

    public function __construct(private readonly Engine $engine)
    {
    }

    public function targetDir(string $kind): string { return $this->engine->contentDir() . '/' . ($kind === 'plugin' ? 'plugins' : 'themes'); }

    /**
     * @param bool $overwrite vorhandene Fassung ersetzen (Update); sonst Fehler, wenn schon installiert
     * @return array{ok:bool,message:string,slug:string,name:string,version:string,files:int,skipped:int,replaced:bool}
     */
    public function install(string $kind, string $zipFile, bool $overwrite = false): array
    {
        $fail = fn(string $m): array => ['ok' => false, 'message' => $m, 'slug' => '', 'name' => '', 'version' => '', 'files' => 0, 'skipped' => 0, 'replaced' => false];
        if (!ExtensionSource::validKind($kind)) {
            return $fail('Unbekannte Art.');
        }
        if (!class_exists(\ZipArchive::class)) {
            return $fail('Die PHP-Erweiterung zip fehlt auf diesem Server.');
        }
        if (!is_file($zipFile) || (int)filesize($zipFile) > ExtensionSource::MAX_ZIP_BYTES) {
            return $fail('Das Paket fehlt oder ist zu groß.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipFile) !== true) {
            return $fail('Das Paket ist kein lesbares ZIP-Archiv.');
        }
        $root = $this->targetDir($kind);
        $stage = $root . '/.staging-' . bin2hex(random_bytes(6));
        $closed = false;
        try {
            [$slug, $entries, $skipped] = $this->plan($zip);
            if (!is_dir($root) && !@mkdir($root, 0755, true) && !is_dir($root)) {
                throw new \RuntimeException('Der Zielordner kann nicht angelegt werden.');
            }
            if (!@mkdir($stage, 0755, true) && !is_dir($stage)) {
                throw new \RuntimeException('Der Zwischenordner kann nicht angelegt werden.');
            }
            $sum = 0;
            foreach ($entries as $idx => $rel) {
                $to = $stage . '/' . $rel;
                if (!is_dir(dirname($to)) && !@mkdir(dirname($to), 0755, true) && !is_dir(dirname($to))) {
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
            }
            $zip->close();
            $closed = true;
            $stageDir = $stage . '/' . $slug;
            $meta = $this->verify($kind, $stageDir, $slug);
            $final = $root . '/' . $slug;
            $replaced = false;
            if (is_dir($final)) {
                if (!$overwrite) {
                    throw new \RuntimeException('„' . $slug . '“ ist schon installiert.');
                }
                $bak = $this->backupDir($kind, $slug);
                if (!@rename($final, $bak)) {
                    throw new \RuntimeException('Die vorhandene Fassung konnte nicht gesichert werden.');
                }
                $replaced = true;
            }
            if (!@rename($stageDir, $final)) {
                if ($replaced) {
                    @rename($bak, $final);   // zurückrollen
                }
                throw new \RuntimeException('Das Paket konnte nicht an seinen Platz verschoben werden.');
            }
            $this->rmTree($stage);
            $this->engine->log(($kind === 'plugin' ? 'Plugin' : 'Theme') . ' installiert: ' . $slug . ' ' . $meta['version'] . ' (' . count($entries) . ' Dateien, sha256 ' . substr((string)hash_file('sha256', $zipFile), 0, 16) . ')');
            return ['ok' => true, 'message' => '', 'slug' => $slug, 'name' => $meta['name'], 'version' => $meta['version'], 'files' => count($entries), 'skipped' => $skipped, 'replaced' => $replaced];
        } catch (\Throwable $e) {
            if (!$closed) {
                try {
                    $zip->close();
                } catch (\Throwable) {
                }
            }
            $this->rmTree($stage);
            $this->engine->log('Installation fehlgeschlagen: ' . $e->getMessage());
            return $fail($e->getMessage());
        }
    }

    /** Fassung vor dem Ersetzen/Löschen sichern (nur die letzte je Eintrag bleibt). */
    public function backupDir(string $kind, string $slug): string
    {
        $dir = $this->engine->stateDir() . '/backups';
        $this->engine->protect();
        @mkdir($dir, 0750, true);
        foreach (glob($dir . '/' . $kind . '-' . $slug . '-*', GLOB_ONLYDIR) ?: [] as $old) {
            $this->rmTree($old);
        }
        return $dir . '/' . $kind . '-' . $slug . '-' . date('Ymd-His');
    }

    /** Eintrag entfernen (nach Sicherung). */
    public function remove(string $kind, string $slug): bool
    {
        if (!ExtensionSource::validKind($kind) || !ExtensionSource::validSlug($slug)) {
            return false;
        }
        $dir = $this->targetDir($kind) . '/' . $slug;
        if (!is_dir($dir) || is_link($dir) || realpath(dirname($dir)) !== realpath($this->targetDir($kind))) {
            return false;
        }
        $bak = $this->backupDir($kind, $slug);
        if (!@rename($dir, $bak)) {
            return false;
        }
        $this->engine->log(($kind === 'plugin' ? 'Plugin' : 'Theme') . ' entfernt: ' . $slug . ' (Sicherung im Zustandsordner)');
        return true;
    }

    /** @return array{0:string,1:array<int,string>,2:int} [Ordnername, Index ⇒ relativer Pfad unter dem Ordner, Zahl übersprungener versteckter Dateien] */
    private function plan(\ZipArchive $zip): array
    {
        $slug = null;
        $out = [];
        $skipped = 0;
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $st = $zip->statIndex($i);
            if ($st === false) {
                throw new \RuntimeException('Das Paket ist beschädigt.');
            }
            $name = (string)$st['name'];
            if (str_contains($name, '\\') || str_contains($name, "\0") || preg_match('/[\x00-\x1f]/', $name) === 1 || str_starts_with($name, '/') || strlen($name) > 240) {
                throw new \RuntimeException('Das Paket enthält einen unzulässigen Pfad.');
            }
            $parts = explode('/', rtrim($name, '/'));
            if (str_starts_with($name, '__MACOSX/') || $parts[0] === '') {
                $skipped++;
                continue;
            }
            foreach ($parts as $p) {
                if ($p === '.' || $p === '..' || $p === '') {
                    throw new \RuntimeException('Das Paket enthält einen unzulässigen Pfad: ' . $name);
                }
            }
            if ($slug === null) {
                $slug = $parts[0];
                if (!ExtensionSource::validSlug($slug)) {
                    throw new \RuntimeException('Der Ordnername im Paket ist ungültig (erlaubt: Kleinbuchstaben, Ziffern, - und _).');
                }
            } elseif ($parts[0] !== $slug) {
                throw new \RuntimeException('Das Paket muss genau einen Hauptordner enthalten.');
            }
            if (str_ends_with($name, '/')) {
                continue;
            }
            if (count($parts) < 2) {
                throw new \RuntimeException('Das Paket muss einen Hauptordner enthalten (Dateien liegen lose im Archiv).');
            }
            $rel = implode('/', array_slice($parts, 1));
            foreach (array_slice($parts, 1) as $p) {
                if ($p[0] === '.') {   // versteckte Dateien (.git, .env, .htaccess …) werden nicht übernommen
                    $skipped++;
                    continue 2;
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
            if (!in_array($ext, self::EXT, true) && !in_array($base, self::NAMES, true)) {
                throw new \RuntimeException('Das Paket enthält einen nicht erlaubten Dateityp: ' . $rel);
            }
            $size = (int)$st['size'];
            $total += $size;
            if ($size > self::MAX_FILE || $total > self::MAX_TOTAL || count($out) >= self::MAX_FILES) {
                throw new \RuntimeException('Das Paket überschreitet die erlaubte Größe oder Dateianzahl.');
            }
            $out[$i] = $slug . '/' . $rel;
        }
        if ($slug === null || $out === []) {
            throw new \RuntimeException('Das Paket enthält keine Dateien.');
        }
        return [$slug, $out, $skipped];
    }

    /** @return array{name:string,version:string} */
    private function verify(string $kind, string $dir, string $slug): array
    {
        if (!is_dir($dir)) {
            throw new \RuntimeException('Das Paket enthält keine Dateien.');
        }
        $name = '';
        $version = '';
        if ($kind === 'theme') {
            $css = $dir . '/style.css';
            $h = is_file($css) ? (string)file_get_contents($css, false, null, 0, 8192) : '';
            if (!preg_match('/^[ \t\/*#@]*Theme Name:(.*)$/mi', $h, $m)) {
                throw new \RuntimeException('Das ist kein Theme: style.css mit „Theme Name:“ fehlt.');
            }
            if (!is_file($dir . '/index.php') && !is_file($dir . '/templates/index.html') && !is_file($dir . '/index.html')) {
                throw new \RuntimeException('Dem Theme fehlt eine Vorlage (index.php oder templates/index.html).');
            }
            $name = trim($m[1]);
            $version = preg_match('/^[ \t\/*#@]*Version:(.*)$/mi', $h, $v) ? trim($v[1]) : '';
        } else {
            $found = false;
            foreach (glob($dir . '/*.php') ?: [] as $f) {
                $h = (string)file_get_contents($f, false, null, 0, 8192);
                if (preg_match('/^[ \t\/*#@]*Plugin Name:(.*)$/mi', $h, $m)) {
                    $found = true;
                    $name = trim($m[1]);
                    $version = preg_match('/^[ \t\/*#@]*Version:(.*)$/mi', $h, $v) ? trim($v[1]) : '';
                    break;
                }
            }
            if (!$found) {
                throw new \RuntimeException('Das ist kein Plugin: In keiner PHP-Datei im Hauptordner steht „Plugin Name:“.');
            }
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
        return ['name' => mb_substr(strip_tags($name), 0, 120), 'version' => mb_substr(strip_tags($version), 0, 30)];
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

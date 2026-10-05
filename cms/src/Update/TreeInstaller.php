<?php
declare(strict_types=1);
// cms/src/Update/TreeInstaller.php
//
// Dateiarbeit des Updates: Paket sicher entpacken und prüfen, Sicherung (Snapshot) anlegen, Dateibaum einspielen/zurückspielen, Gesundheitsprüfung.
// „Verwalteter Baum“ = der CMS-Ordner ohne Betreiberdaten. Nie angefasst werden: data/, backups/, media/, content/, plugins/, generated/, wp-content/,
// frontend/ (u. a. Lovable-Sync), demo-state/, *.local.php/json sowie – unter themes/ – alle Themes, die das Paket nicht mitbringt (eigene Themes).
// Sicherheit (das Paket kommt aus dem Netz): keine Pfadtricks, keine Symlinks, keine versteckten Dateien (außer .htaccess), Endungs-Allowlist,
// Mengen-/Größenlimits, alle PHP-Dateien werden vor dem Einspielen geparst (Syntaxfehler → Abbruch, ohne das Paket auszuführen).

namespace Elvado\Update;

final class TreeInstaller
{
    public const MAX_TOTAL_BYTES = 262_144_000;   // 250 MB entpackt
    public const MAX_FILE_BYTES = 31_457_280;     // 30 MB je Datei
    public const MAX_FILES = 8000;
    public const EXT = ['php', 'js', 'mjs', 'css', 'html', 'htm', 'json', 'md', 'txt', 'xml', 'xsl', 'sql', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico', 'woff', 'woff2', 'ttf', 'otf', 'map', 'webmanifest', 'example', 'mo', 'po', 'pot', 'csv', 'mp3', 'ogg'];
    public const NAMES = ['VERSION', 'LICENSE', 'README', '.htaccess'];
    public const PRESERVE = ['data', 'backups', 'media', 'content', 'plugins', 'generated', 'wp-content', 'frontend', 'demo-state', 'node_modules', '.git'];

    public function __construct(private string $cmsRoot, private readonly string $workDir)
    {
        $this->cmsRoot = rtrim($cmsRoot, '/');
    }

    // ───────── Paket entpacken ─────────

    /**
     * Archiv nach <stage>/tree entpacken (nur der Teil unter <subdir>/), prüfen.
     * @return array{tree:string,stage:string,files:array<string,string>,version:string,skipped:int} files: relativer Pfad → SHA-256
     */
    public function extractPackage(string $zipFile, string $subdir): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new UpdateException('Die PHP-Erweiterung zip fehlt auf diesem Server.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipFile) !== true) {
            throw new UpdateException('Das heruntergeladene Paket ist kein lesbares ZIP-Archiv.');
        }
        $stage = $this->workDir . '/stage-' . bin2hex(random_bytes(4));
        $tree = $stage . '/tree';
        @mkdir($tree, 0775, true);
        $files = [];
        $skipped = 0;
        $total = 0;
        try {
            $top = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $st = $zip->statIndex($i);
                if ($st === false) {
                    continue;
                }
                $name = str_replace('\\', '/', (string)$st['name']);
                if ($name === '' || str_ends_with($name, '/')) {
                    continue;
                }
                $slash = strpos($name, '/');
                if ($slash === false) {
                    $skipped++;
                    continue;
                }
                $top ??= substr($name, 0, $slash);
                if (substr($name, 0, $slash) !== $top) {
                    $skipped++;
                    continue;
                }
                $rel = substr($name, $slash + 1);
                $prefix = $subdir !== '' ? $subdir . '/' : '';
                if ($prefix !== '' && !str_starts_with($rel, $prefix)) {
                    continue;   // gehört nicht zum CMS-Ordner (z. B. Wurzeldateien des Repositories)
                }
                $rel = substr($rel, strlen($prefix));
                if (!self::pathOk($rel) || self::isSymlink($zip, $i) || self::isPreserved($rel) || self::isLocal($rel)) {
                    $skipped++;
                    continue;
                }
                $size = (int)$st['size'];
                if ($size > self::MAX_FILE_BYTES || $total + $size > self::MAX_TOTAL_BYTES || count($files) >= self::MAX_FILES) {
                    throw new UpdateException('Das Paket ist ungewöhnlich groß – Update abgebrochen.');
                }
                $content = $zip->getFromIndex($i);
                if ($content === false) {
                    throw new UpdateException('Eine Datei im Paket ist beschädigt: ' . $rel);
                }
                $total += strlen($content);
                $dest = $tree . '/' . $rel;
                if (!is_dir(dirname($dest))) {
                    @mkdir(dirname($dest), 0775, true);
                }
                if (@file_put_contents($dest, $content) === false) {
                    throw new UpdateException('Der Zwischenspeicher ist nicht beschreibbar (cms/data/.update).');
                }
                $files[$rel] = hash('sha256', $content);
                if (strtolower(pathinfo($rel, PATHINFO_EXTENSION)) === 'php') {
                    self::lint($content, $rel);
                }
            }
        } catch (\Throwable $e) {
            $zip->close();
            self::rmTree($stage);
            throw $e;
        }
        $zip->close();
        foreach (['VERSION', 'index.php', 'api.php'] as $must) {
            if (!isset($files[$must])) {
                self::rmTree($stage);
                throw new UpdateException('Das Paket enthält kein vollständiges CMS (Datei „' . $must . '“ fehlt im Ordner „' . ($subdir ?: '/') . '“).');
            }
        }
        $ver = trim((string)@file_get_contents($tree . '/VERSION'));
        if (!preg_match('/^[0-9A-Za-z][0-9A-Za-z.+_-]{0,31}$/', $ver)) {
            self::rmTree($stage);
            throw new UpdateException('Die Version im Paket ist ungültig.');
        }
        return ['tree' => $tree, 'stage' => $stage, 'files' => $files, 'version' => $ver, 'skipped' => $skipped];
    }

    public static function pathOk(string $rel): bool
    {
        if ($rel === '' || strlen($rel) > 200 || !preg_match('~^[A-Za-z0-9._@+/-]+$~', $rel) || str_contains($rel, '//')) {
            return false;
        }
        foreach (explode('/', $rel) as $p) {
            if ($p === '' || $p === '.' || $p === '..' || ($p[0] === '.' && $p !== '.htaccess') || $p === 'node_modules') {
                return false;
            }
        }
        $base = basename($rel);
        return in_array($base, self::NAMES, true) || in_array(strtolower(pathinfo($rel, PATHINFO_EXTENSION)), self::EXT, true);
    }

    public static function isPreserved(string $rel): bool
    {
        return in_array(explode('/', $rel, 2)[0], self::PRESERVE, true);
    }

    public static function isLocal(string $rel): bool
    {
        return (bool)preg_match('~(^|/)[^/]*\.local\.(php|json)$~', $rel);
    }

    private static function isSymlink(\ZipArchive $zip, int $i): bool
    {
        $os = 0;
        $attr = 0;
        return $zip->getExternalAttributesIndex($i, $os, $attr) && $os === \ZipArchive::OPSYS_UNIX && (($attr >> 16) & 0170000) === 0120000;
    }

    private static function lint(string $code, string $rel): void
    {
        try {
            token_get_all($code, TOKEN_PARSE);
        } catch (\ParseError $e) {
            throw new UpdateException('Das Paket enthält einen Syntaxfehler (' . $rel . ', Zeile ' . $e->getLine() . ') – Update abgebrochen, nichts wurde verändert.');
        }
    }

    // ───────── Verwalteter Baum ─────────

    /** @return array<string,string> relativer Pfad → absoluter Pfad aller verwalteten Dateien */
    public function managedFiles(): array
    {
        $out = [];
        if (!is_dir($this->cmsRoot)) {
            return $out;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->cmsRoot, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($it as $f) {
            /** @var \SplFileInfo $f */
            if (!$f->isFile() || $f->isLink()) {
                continue;
            }
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($this->cmsRoot) + 1));
            if (self::isPreserved($rel) || self::isLocal($rel)) {
                continue;
            }
            $out[$rel] = $f->getPathname();
        }
        ksort($out);
        return $out;
    }

    /** Sicherung des aktuellen Baums als ZIP. */
    public function snapshot(string $zipFile): int
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new UpdateException('Die PHP-Erweiterung zip fehlt auf diesem Server.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new UpdateException('Die Sicherung vor dem Update konnte nicht angelegt werden (cms/data/.update nicht beschreibbar).');
        }
        $n = 0;
        foreach ($this->managedFiles() as $rel => $abs) {
            if ($zip->addFile($abs, $rel)) {
                $n++;
            }
        }
        if (!$zip->close() || $n === 0) {
            @unlink($zipFile);
            throw new UpdateException('Die Sicherung vor dem Update ist fehlgeschlagen – es wurde nichts verändert.');
        }
        return $n;
    }

    /** Sicherungs-ZIP in einen Zwischenordner entpacken (eigenes Format, trotzdem mit Pfadprüfung). @return array{tree:string,stage:string,files:array<string,string>} */
    public function extractSnapshot(string $zipFile): array
    {
        $zip = new \ZipArchive();
        if (!is_file($zipFile) || $zip->open($zipFile) !== true) {
            throw new UpdateException('Die Sicherung ist nicht lesbar.');
        }
        $stage = $this->workDir . '/stage-' . bin2hex(random_bytes(4));
        $tree = $stage . '/tree';
        @mkdir($tree, 0775, true);
        $files = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string)$zip->getNameIndex($i));
            if ($name === '' || str_ends_with($name, '/') || !self::pathOk($name) || self::isPreserved($name)) {
                continue;
            }
            $c = $zip->getFromIndex($i);
            if ($c === false) {
                $zip->close();
                self::rmTree($stage);
                throw new UpdateException('Die Sicherung ist beschädigt: ' . $name);
            }
            $dest = $tree . '/' . $name;
            @mkdir(dirname($dest), 0775, true);
            file_put_contents($dest, $c);
            $files[$name] = hash('sha256', $c);
        }
        $zip->close();
        if (!isset($files['VERSION']) || !isset($files['index.php'])) {
            self::rmTree($stage);
            throw new UpdateException('Die Sicherung ist unvollständig.');
        }
        return ['tree' => $tree, 'stage' => $stage, 'files' => $files];
    }

    // ───────── Einspielen ─────────

    /** Sind alle Zieldateien/-ordner beschreibbar? Meldet höchstens fünf Pfade. @param array<string,string> $files */
    public function checkWritable(array $files): void
    {
        $bad = [];
        foreach (array_keys($files) as $rel) {
            $t = $this->cmsRoot . '/' . $rel;
            $p = is_file($t) ? $t : dirname($t);
            while (!file_exists($p) && $p !== dirname($p)) {
                $p = dirname($p);
            }
            if (!is_writable($p)) {
                $bad[] = $rel;
                if (count($bad) >= 5) {
                    break;
                }
            }
        }
        if ($bad !== []) {
            throw new UpdateException('Keine Schreibrechte für: ' . implode(', ', $bad) . ' – bitte Dateirechte des CMS-Ordners für den Webserver-Benutzer prüfen.');
        }
    }

    /**
     * Dateibaum <tree> in den CMS-Ordner übernehmen: erst alle neuen/geänderten Dateien (atomar je Datei), dann veraltete verwaltete Dateien entfernen.
     * @param array<string,string> $files relativer Pfad → SHA-256 (Inhalt des Baums)
     * @return array{added:int,updated:int,removed:int}
     */
    public function install(string $tree, array $files): array
    {
        $added = 0;
        $updated = 0;
        foreach ($files as $rel => $hash) {
            $dest = $this->cmsRoot . '/' . $rel;
            if (is_file($dest) && hash_file('sha256', $dest) === $hash) {
                continue;
            }
            if (!is_dir(dirname($dest)) && !@mkdir(dirname($dest), 0755, true) && !is_dir(dirname($dest))) {
                throw new UpdateException('Ordner nicht anlegbar: ' . dirname($rel));
            }
            $existed = is_file($dest);
            $tmp = $dest . '.upd' . bin2hex(random_bytes(2));
            if (!@copy($tree . '/' . $rel, $tmp)) {
                @unlink($tmp);
                throw new UpdateException('Datei nicht schreibbar: ' . $rel);
            }
            @chmod($tmp, 0644);
            if (!@rename($tmp, $dest)) {
                @unlink($tmp);
                throw new UpdateException('Datei nicht ersetzbar: ' . $rel);
            }
            $existed ? $updated++ : $added++;
        }
        // veraltete Dateien: nur im verwalteten Baum; Themes, die das Paket nicht mitbringt, sind eigene und bleiben
        $shipped = [];
        foreach (array_keys($files) as $rel) {
            if (str_starts_with($rel, 'themes/')) {
                $shipped[explode('/', $rel)[1] ?? ''] = true;
            }
        }
        $removed = 0;
        foreach ($this->managedFiles() as $rel => $abs) {
            if (isset($files[$rel])) {
                continue;
            }
            if (str_starts_with($rel, 'themes/') && !isset($shipped[explode('/', $rel)[1] ?? ''])) {
                continue;
            }
            if (@unlink($abs)) {
                $removed++;
            }
        }
        $this->pruneEmptyDirs($this->cmsRoot);
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        clearstatcache();
        return ['added' => $added, 'updated' => $updated, 'removed' => $removed];
    }

    private function pruneEmptyDirs(string $dir): void
    {
        foreach (glob($dir . '/*', GLOB_ONLYDIR) ?: [] as $d) {
            $rel = substr($d, strlen($this->cmsRoot) + 1);
            if (self::isPreserved($rel)) {
                continue;
            }
            $this->pruneEmptyDirs($d);
            if (count(scandir($d) ?: []) <= 2) {
                @rmdir($d);
            }
        }
    }

    // ───────── Gesundheitsprüfung ─────────

    /** Stimmen Version und (falls angegeben) Dateiinhalte? @param array<string,string> $files @return array{ok:bool,message:string} */
    public function verifyFiles(string $version, array $files): array
    {
        $v = trim((string)@file_get_contents($this->cmsRoot . '/VERSION'));
        if ($v !== $version) {
            return ['ok' => false, 'message' => 'Version nach dem Einspielen ' . $v . ' statt ' . $version];
        }
        foreach ($files as $rel => $hash) {
            $f = $this->cmsRoot . '/' . $rel;
            if (!is_file($f) || hash_file('sha256', $f) !== $hash) {
                return ['ok' => false, 'message' => 'Datei fehlt oder weicht ab: ' . $rel];
            }
        }
        return ['ok' => true, 'message' => ''];
    }

    /** Startet das neue CMS in einem eigenen PHP-Prozess (cms/update-health.php). @return array{state:string,message:string} state: ok|skipped|fail */
    public function cliCheck(string $expectedVersion): array
    {
        $script = $this->cmsRoot . '/update-health.php';
        $php = PHP_SAPI === 'cli' ? PHP_BINARY : (is_executable(PHP_BINDIR . '/php') ? PHP_BINDIR . '/php' : '');
        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        if ($php === '' || !is_file($script) || !function_exists('proc_open') || in_array('proc_open', $disabled, true)) {
            return ['state' => 'skipped', 'message' => 'PHP-Kommandozeile nicht verfügbar'];
        }
        $proc = @proc_open([$php, '-d', 'display_errors=0', $script, $expectedVersion], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->cmsRoot);
        if (!is_resource($proc)) {
            return ['state' => 'skipped', 'message' => 'Prozess nicht startbar'];
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = '';
        $err = '';
        $end = microtime(true) + 25;
        $timedOut = false;
        while (true) {
            $out .= (string)stream_get_contents($pipes[1]);
            $err .= (string)stream_get_contents($pipes[2]);
            $s = proc_get_status($proc);
            if (!$s['running']) {
                break;
            }
            if (microtime(true) > $end) {
                $timedOut = true;
                proc_terminate($proc, 9);
                break;
            }
            usleep(50_000);
        }
        $out .= (string)stream_get_contents($pipes[1]);
        $err .= (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        if ($timedOut) {
            return ['state' => 'skipped', 'message' => 'Zeitüberschreitung'];
        }
        $lines = array_values(array_filter(explode("\n", trim($out))));
        $line = $lines === [] ? '' : trim((string)end($lines));
        $j = json_decode($line, true);
        if (is_array($j) && !empty($j['ok'])) {
            return ['state' => 'ok', 'message' => ''];
        }
        $msg = is_array($j) ? (string)($j['message'] ?? '') : mb_substr(trim($err . ' ' . $out), 0, 200);
        return ['state' => 'fail', 'message' => $msg !== '' ? $msg : 'Prozess endete mit Code ' . $code];
    }

    /**
     * Ruft den öffentlichen Ping des CMS über die Website ab. 5xx oder PHP-Fehlerseite = Fehler; nicht erreichbar/Zugangsschutz = unklar (übersprungen).
     * @return array{state:string,message:string}
     */
    public function ping(string $baseUrl): array
    {
        if ($baseUrl === '' || !function_exists('curl_init')) {
            return ['state' => 'skipped', 'message' => 'keine Website-Adresse bekannt'];
        }
        $ch = curl_init($baseUrl . '/api.php?action=update_ping&_=' . time());
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_HTTPHEADER => ['Accept: application/json', 'Cache-Control: no-cache']]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $body = is_string($body) ? $body : '';
        if ($code === 0) {
            return ['state' => 'skipped', 'message' => 'Website von hier nicht erreichbar'];
        }
        $j = json_decode($body, true);
        if ($code === 200 && is_array($j) && ($j['status'] ?? '') === 'ok') {
            return ['state' => 'ok', 'message' => ''];
        }
        if ($code >= 500 || preg_match('/(Parse|Fatal) error/i', $body)) {
            return ['state' => 'fail', 'message' => 'Website antwortet mit HTTP ' . $code];
        }
        return ['state' => 'skipped', 'message' => 'Antwort nicht auswertbar (HTTP ' . $code . ')'];
    }

    public static function rmTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}

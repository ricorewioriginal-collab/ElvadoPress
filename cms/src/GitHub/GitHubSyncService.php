<?php
declare(strict_types=1);
// cms/src/GitHub/GitHubSyncService.php
//
// Synchronisation eines verknüpften GitHub-Repositories (z. B. von Lovable per „Publish“ befüllt) in das Frontend-Verzeichnis des CMS (cms/frontend/lovable/<projekt>/).
// Ablauf: GitHub-Webhook (push auf den eingestellten Branch, Signatur HMAC-SHA256) → neuesten Commit abfragen → ZIP des Commits per cURL laden →
// sicher entpacken (nur erlaubte Dateien) → Zielordner aktualisieren (veraltete Dateien aus dem letzten Stand werden entfernt) → Stand merken.
// Sicherheit (das Repository ist fremder Inhalt!):
//   • Webhook nur mit gültiger Signatur und passendem Repository/Branch; Wiederholungen derselben Lieferung werden ignoriert.
//   • Nur Hosts api.github.com (und dessen Weiterleitung auf codeload.github.com); Größen- und Mengenlimits.
//   • Entpacken ohne Pfad-Tricks (kein "..", keine absoluten Pfade, keine Symlinks); nur Quelltext-/Asset-Endungen. Es wird KEINE ausführbare Serverdatei
//     (php, phtml, phar, sh …) und keine versteckte Datei (.env, .htaccess …) übernommen; zusätzlich sperrt eine eigene .htaccess im Zielordner PHP.
//   • Es wird nichts ausgeführt, gebaut oder installiert – der Code liegt nur als Dateien bereit.

namespace Elvado\GitHub;

use Elvado\Lovable\LovableSettings;
use Elvado\Support\Http;

final class GitHubSyncService
{
    public const API = 'https://api.github.com';
    public const MAX_ZIP_BYTES = 62_914_560;      // 60 MB
    public const MAX_TOTAL_BYTES = 52_428_800;    // 50 MB entpackt
    public const MAX_FILE_BYTES = 5_242_880;      // 5 MB je Datei
    public const MAX_FILES = 3000;
    /** Erlaubte Dateiendungen (Quelltext und Web-Assets). */
    public const EXTENSIONS = ['ts', 'tsx', 'js', 'jsx', 'mjs', 'cjs', 'css', 'scss', 'json', 'html', 'svg', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'ico', 'avif', 'woff', 'woff2', 'ttf', 'otf', 'md', 'txt', 'map', 'webmanifest'];

    public function __construct(
        private readonly LovableSettings $settings,
        private readonly string $frontendRoot,
        private readonly string $dataDir,
    ) {
    }

    // ───────── Webhook ─────────

    public static function verifySignature(string $payload, string $header, string $secret): bool
    {
        if ($secret === '' || !preg_match('/^sha256=([a-f0-9]{64})$/i', trim($header), $m)) {
            return false;
        }
        return hash_equals(hash_hmac('sha256', $payload, $secret), strtolower($m[1]));
    }

    /**
     * Webhook prüfen. @param array<string,string> $headers Kopfzeilen mit Kleinbuchstaben-Namen
     * @return array{status:int,body:array<string,mixed>,run_sync:bool}
     */
    public function handleWebhook(array $headers, string $payload): array
    {
        $g = $this->settings->github();
        if ($g['repo'] === '' || $g['secret'] === '') {
            return ['status' => 503, 'body' => ['status' => 'error', 'message' => 'Der Webhook ist noch nicht eingerichtet.'], 'run_sync' => false];
        }
        if (strlen($payload) > 1_048_576) {
            return ['status' => 413, 'body' => ['status' => 'error', 'message' => 'Nachricht zu groß.'], 'run_sync' => false];
        }
        if (!self::verifySignature($payload, (string)($headers['x-hub-signature-256'] ?? ''), $g['secret'])) {
            return ['status' => 401, 'body' => ['status' => 'error', 'message' => 'Ungültige Signatur.'], 'run_sync' => false];
        }
        $event = (string)($headers['x-github-event'] ?? '');
        if ($event === 'ping') {
            return ['status' => 200, 'body' => ['status' => 'ok', 'message' => 'pong'], 'run_sync' => false];
        }
        $delivery = preg_replace('/[^a-f0-9-]/i', '', (string)($headers['x-github-delivery'] ?? '')) ?? '';
        if ($delivery !== '' && $this->seenDelivery($delivery)) {
            return ['status' => 200, 'body' => ['status' => 'ok', 'message' => 'Lieferung bereits verarbeitet.'], 'run_sync' => false];
        }
        $j = json_decode($payload, true);
        if ($event !== 'push' || !is_array($j)) {
            return ['status' => 202, 'body' => ['status' => 'ok', 'message' => 'Ereignis ignoriert.'], 'run_sync' => false];
        }
        $full = strtolower((string)($j['repository']['full_name'] ?? ''));
        if ($full !== strtolower($g['repo']) || ($j['ref'] ?? '') !== 'refs/heads/' . $g['branch'] || !empty($j['deleted'])) {
            return ['status' => 202, 'body' => ['status' => 'ok', 'message' => 'Anderes Repository oder anderer Branch – nichts zu tun.'], 'run_sync' => false];
        }
        if (!$g['auto_sync']) {
            return ['status' => 202, 'body' => ['status' => 'ok', 'message' => 'Automatische Synchronisation ist ausgeschaltet.'], 'run_sync' => false];
        }
        return ['status' => 202, 'body' => ['status' => 'ok', 'message' => 'Synchronisation gestartet.'], 'run_sync' => true];
    }

    private function seenDelivery(string $id): bool
    {
        $st = $this->readState();
        $list = array_values(array_filter((array)($st['deliveries'] ?? []), 'is_string'));
        if (in_array($id, $list, true)) {
            return true;
        }
        $list[] = $id;
        $st['deliveries'] = array_slice($list, -50);
        $this->writeState($st);
        return false;
    }

    // ───────── Synchronisation ─────────

    /**
     * Neuesten Stand des Branches holen und in den Zielordner übernehmen.
     * @return array{status:string,sha:string,message:string,added:int,updated:int,removed:int,skipped:int,files:int}
     * @throws GitHubSyncException
     */
    public function sync(bool $force = false): array
    {
        $g = $this->settings->github();
        if ($g['repo'] === '') {
            throw new GitHubSyncException('Es ist noch kein GitHub-Repository eingetragen.');
        }
        $lockFile = $this->stateDir() . '/sync.lock';
        $lock = @fopen($lockFile, 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            throw new GitHubSyncException('Eine Synchronisation läuft bereits.');
        }
        try {
            return $this->run($g, $force);
        } catch (GitHubSyncException $e) {
            $st = $this->readState();
            $st['last_error'] = ['at' => gmdate('c'), 'message' => $e->getMessage()];
            $this->writeState($st);
            throw $e;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @param array<string,mixed> $g @return array{status:string,sha:string,message:string,added:int,updated:int,removed:int,skipped:int,files:int} */
    private function run(array $g, bool $force): array
    {
        {
            $commit = $this->api('/repos/' . $g['repo'] . '/commits/' . rawurlencode($g['branch']), $g['token']);
            $sha = (string)($commit['sha'] ?? '');
            if (!preg_match('/^[a-f0-9]{40}$/', $sha)) {
                throw new GitHubSyncException('GitHub lieferte keinen gültigen Commit.');
            }
            $msg = mb_substr(strtok((string)($commit['commit']['message'] ?? ''), "\n") ?: '', 0, 120);
            $st = $this->readState();
            if (!$force && ($st['last_sha'] ?? '') === $sha) {
                return $this->done($st, 'unchanged', $sha, $msg, 0, 0, 0, 0, count((array)($st['files'] ?? [])), false);
            }
            $zip = tempnam(sys_get_temp_dir(), 'ghzip');
            if ($zip === false) {
                throw new GitHubSyncException('Temporäre Datei nicht möglich.');
            }
            try {
                $resp = Http::request('GET', self::API . '/repos/' . $g['repo'] . '/zipball/' . $sha, $this->headers($g['token']), null, ['hosts' => ['api.github.com'], 'save_to' => $zip, 'max_bytes' => self::MAX_ZIP_BYTES, 'timeout' => 90, 'max_redirects' => 3]);
                if (!$resp->ok() || !is_file($zip) || filesize($zip) === 0) {
                    throw new GitHubSyncException($this->httpMessage($resp->status, $resp->error));
                }
                [$files, $skipped] = $this->extract($zip, $g['include']);
            } finally {
                @unlink($zip);
            }
            return $this->install($files, $skipped, $g['project'], $sha, $msg, $st);
        }
    }

    /** @return array<string,mixed> */
    public function status(): array
    {
        $st = $this->readState();
        unset($st['deliveries']);
        return $st + ['last_sha' => '', 'last_sync' => '', 'files' => []];
    }

    // ───────── Entpacken ─────────

    /**
     * @param list<string> $include
     * @return array{0:array<string,string>,1:int} [relativer Pfad => Inhalt im Staging-Ordner, übersprungene Dateien]
     */
    private function extract(string $zipFile, array $include): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new GitHubSyncException('Die PHP-Erweiterung zip fehlt auf diesem Server.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipFile) !== true) {
            throw new GitHubSyncException('Das Archiv von GitHub ist nicht lesbar.');
        }
        $stage = $this->stateDir() . '/stage-' . bin2hex(random_bytes(4));
        @mkdir($stage, 0775, true);
        $out = [];
        $skipped = 0;
        $total = 0;
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    continue;
                }
                $name = str_replace('\\', '/', (string)$stat['name']);
                if (str_ends_with($name, '/')) {
                    continue;   // Ordner
                }
                $rel = ltrim((string)substr($name, (int)strpos($name, '/') + 1), '/');   // Kopfordner "<owner>-<repo>-<sha>/" entfernen
                if (!self::pathAllowed($rel, $include) || self::isSymlink($zip, $i)) {
                    $skipped++;
                    continue;
                }
                $size = (int)$stat['size'];
                if ($size > self::MAX_FILE_BYTES || $total + $size > self::MAX_TOTAL_BYTES || count($out) >= self::MAX_FILES) {
                    $skipped++;
                    continue;
                }
                $content = $zip->getFromIndex($i);
                if ($content === false || strlen($content) > self::MAX_FILE_BYTES) {
                    $skipped++;
                    continue;
                }
                $total += strlen($content);
                $dest = $stage . '/' . $rel;
                if (!is_dir(dirname($dest))) {
                    @mkdir(dirname($dest), 0775, true);
                }
                if (@file_put_contents($dest, $content) === false) {
                    throw new GitHubSyncException('Der Zwischenspeicher ist nicht beschreibbar.');
                }
                $out[$rel] = $dest;
            }
        } finally {
            $zip->close();
        }
        return [$out, $skipped];
    }

    private static function isSymlink(\ZipArchive $zip, int $i): bool
    {
        $os = 0;
        $attr = 0;
        if ($zip->getExternalAttributesIndex($i, $os, $attr) && $os === \ZipArchive::OPSYS_UNIX) {
            return (($attr >> 16) & 0170000) === 0120000;
        }
        return false;
    }

    /** Pfad zulässig? (sicher, erlaubte Endung, nicht versteckt, passt zu den Einschlussmustern). @param list<string> $include */
    public static function pathAllowed(string $rel, array $include): bool
    {
        if ($rel === '' || strlen($rel) > 200 || !preg_match('~^[A-Za-z0-9._@+/-]+$~', $rel) || str_starts_with($rel, '/') || str_contains($rel, '//')) {
            return false;
        }
        $parts = explode('/', $rel);
        foreach ($parts as $p) {
            if ($p === '' || $p === '.' || $p === '..' || $p[0] === '.' || $p === 'node_modules') {
                return false;   // versteckte Dateien/Ordner (.env, .git, .htaccess …) und node_modules nie
            }
        }
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTENSIONS, true)) {
            return false;
        }
        foreach ($include as $pat) {
            if (str_ends_with($pat, '/') ? str_starts_with($rel, $pat) : (str_contains($pat, '*') ? fnmatch($pat, $rel) : $rel === $pat)) {
                return true;
            }
        }
        return false;
    }

    // ───────── Installieren ─────────

    /** @param array<string,string> $files */
    private function install(array $files, int $skipped, string $project, string $sha, string $msg, array $state): array
    {
        $root = rtrim($this->frontendRoot, '/') . '/' . $project;
        if (!is_dir($root) && !@mkdir($root, 0775, true) && !is_dir($root)) {
            $this->cleanStage($files);
            throw new GitHubSyncException('Das Frontend-Verzeichnis ist nicht beschreibbar.');
        }
        // Zielordner: PHP und Skript-Ausführung sperren (Quellcode liegt dort nur als Dateien)
        @file_put_contents(rtrim($this->frontendRoot, '/') . '/.htaccess', "# Von Lovable/GitHub übernommene Dateien: nie als Serverprogramm ausführen\n<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh)$\">\n  <IfModule mod_authz_core.c>\n    Require all denied\n  </IfModule>\n  <IfModule !mod_authz_core.c>\n    Deny from all\n  </IfModule>\n</FilesMatch>\nOptions -ExecCGI -Indexes\n");
        $old = array_values(array_filter((array)($state['files'] ?? []), static fn($f) => is_string($f)));
        $added = $updated = 0;
        foreach ($files as $rel => $tmp) {
            $dest = $root . '/' . $rel;
            $existed = is_file($dest);
            if (!is_dir(dirname($dest))) {
                @mkdir(dirname($dest), 0775, true);
            }
            if (!@rename($tmp, $dest) && !@copy($tmp, $dest)) {
                $this->cleanStage($files);
                throw new GitHubSyncException('Datei konnte nicht übernommen werden: ' . $rel);
            }
            @chmod($dest, 0644);
            $existed ? $updated++ : $added++;
        }
        // Dateien des letzten Stands, die es nicht mehr gibt, entfernen (nur solche aus dem eigenen Manifest und nur innerhalb des Zielordners)
        $removed = 0;
        $new = array_keys($files);
        foreach (array_diff($old, $new) as $rel) {
            if (preg_match('~^[A-Za-z0-9._@+/-]+$~', $rel) && !str_contains($rel, '..') && !str_starts_with($rel, '/')) {
                $p = $root . '/' . $rel;
                if (is_file($p) && @unlink($p)) {
                    $removed++;
                }
            }
        }
        $this->pruneEmptyDirs($root);
        $this->cleanStage($files);
        sort($new);
        return $this->done($state, 'synced', $sha, $msg, $added, $updated, $removed, $skipped, count($new), true, $new);
    }

    /** @param array<string,string> $files */
    private function cleanStage(array $files): void
    {
        foreach ($files as $tmp) {
            @unlink($tmp);
        }
        foreach (glob($this->stateDir() . '/stage-*') ?: [] as $d) {
            self::rmTree($d);
        }
    }

    private static function rmTree(string $dir): void
    {
        foreach (glob($dir . '/{,.}*', GLOB_BRACE) ?: [] as $f) {
            if (in_array(basename($f), ['.', '..'], true)) {
                continue;
            }
            is_dir($f) && !is_link($f) ? self::rmTree($f) : @unlink($f);
        }
        @rmdir($dir);
    }

    private function pruneEmptyDirs(string $root): void
    {
        foreach (array_reverse(glob($root . '/*', GLOB_ONLYDIR) ?: []) as $d) {
            $this->pruneEmptyDirs($d);
            @rmdir($d);   // schlägt bei nicht leeren Ordnern still fehl
        }
    }

    // ───────── Hilfen ─────────

    /** @return array<string,mixed> */
    private function api(string $path, string $token): array
    {
        $r = Http::request('GET', self::API . $path, $this->headers($token), null, ['hosts' => ['api.github.com'], 'timeout' => 20, 'max_bytes' => 2_000_000]);
        if (!$r->ok()) {
            throw new GitHubSyncException($this->httpMessage($r->status, $r->error));
        }
        $j = $r->json();
        if ($j === null) {
            throw new GitHubSyncException('GitHub lieferte eine unlesbare Antwort.');
        }
        return $j;
    }

    /** @return list<string> */
    private function headers(string $token): array
    {
        return array_filter(['Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28', $token !== '' ? 'Authorization: Bearer ' . $token : '']);
    }

    private function httpMessage(int $status, string $err): string
    {
        return match (true) {
            $status === 0 => 'GitHub ist nicht erreichbar' . ($err !== '' ? ' (' . mb_substr($err, 0, 80) . ')' : '') . '.',
            $status === 401 => 'GitHub: Zugang abgelehnt – Token prüfen.',
            $status === 403 => 'GitHub: Zugriff verweigert oder Limit erreicht (Token mit Leserecht für „Contents“ eintragen).',
            $status === 404 => 'GitHub: Repository oder Branch nicht gefunden (bei privaten Repositories ist ein Token nötig).',
            default => 'GitHub antwortete mit HTTP ' . $status . '.',
        };
    }

    private function stateDir(): string
    {
        $d = rtrim($this->dataDir, '/') . '/.lovable';
        if (!is_dir($d)) {
            @mkdir($d, 0775, true);
        }
        if (!is_file($d . '/.htaccess')) {
            @file_put_contents($d . '/.htaccess', "# Laufzeitdaten: nie öffentlich abrufbar\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
        }
        return $d;
    }

    /** @return array<string,mixed> */
    private function readState(): array
    {
        $f = $this->stateDir() . '/sync-state.json';
        $j = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
        return is_array($j) ? $j : [];
    }

    /** @param array<string,mixed> $st */
    private function writeState(array $st): void
    {
        $f = $this->stateDir() . '/sync-state.json';
        $tmp = $f . '.' . bin2hex(random_bytes(3)) . '.tmp';
        if (@file_put_contents($tmp, json_encode($st, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false) {
            @rename($tmp, $f);
        }
    }

    /**
     * @param array<string,mixed> $st
     * @param list<string>|null   $manifest
     * @return array{status:string,sha:string,message:string,added:int,updated:int,removed:int,skipped:int,files:int}
     */
    private function done(array $st, string $status, string $sha, string $msg, int $added, int $updated, int $removed, int $skipped, int $files, bool $write, ?array $manifest = null): array
    {
        if ($write) {
            $st['last_sha'] = $sha;
            $st['last_sync'] = gmdate('c');
            $st['last_message'] = $msg;
            $st['files'] = $manifest ?? [];
            unset($st['last_error']);
            $st['last_result'] = ['added' => $added, 'updated' => $updated, 'removed' => $removed, 'skipped' => $skipped];
            $this->writeState($st);
        }
        return ['status' => $status, 'sha' => $sha, 'message' => $msg, 'added' => $added, 'updated' => $updated, 'removed' => $removed, 'skipped' => $skipped, 'files' => $files];
    }
}

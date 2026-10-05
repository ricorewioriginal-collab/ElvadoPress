<?php
declare(strict_types=1);
// cms/src/Update/UpdateService.php
//
// CMS-Aktualisierung über GitHub – Ablauf und Zustand.
//   Suchen    check(): neueste Version laut Kanal (Release/Beta nach SemVer, oder Branch-Stand), Ergebnis wird gemerkt; tick() sucht automatisch im eingestellten Abstand.
//   Einspielen apply(): Paket laden → entpacken/prüfen (alle PHP-Dateien geparst) → Sicherung (Snapshot) → Dateien einspielen → Gesundheitsprüfung
//              (Dateien/Version, neues CMS im eigenen PHP-Prozess, Ping der Website). Fehler = sofortiger automatischer Rückschritt auf die Sicherung.
//   Überwachung Nach jedem Update läuft ein Überwachungsfenster („pending“): watchdog() (Cron, Webhook, Verwaltung, update-rescue.php) prüft die Gesundheit
//              und nimmt das Update bei einem Fehler automatisch zurück; ein gesundes Update gilt nach der Frist als bestätigt (nie stilles Zurückrollen).
//   Downgrade  rollback(): Sicherung zurückspielen; apply($ref, true) installiert gezielt eine ältere Version.
// Daten: cms/data/.update/ (settings.json, state.json, snapshots/, Sperre, Notfall-Token). Betreiberdaten bleiben unangetastet (siehe TreeInstaller).

namespace Elvado\Update;

use Elvado\GitHub\GitHubSyncService;

final class UpdateService
{
    private const HISTORY_MAX = 30;

    private readonly string $cmsRoot;
    private readonly TreeInstaller $tree;
    private readonly GitHubSource $source;

    public function __construct(private readonly UpdateSettings $settings, string $cmsRoot, private readonly string $dataDir)
    {
        $this->cmsRoot = rtrim($cmsRoot, '/');
        $this->tree = new TreeInstaller($this->cmsRoot, $this->stateDir() . '/work');
        $this->source = new GitHubSource($settings);
    }

    /** Standard-Verdrahtung im CMS: Einstellungen aus cms/data/.update, Standard-Repository aus cms/lib/product.default.json (Schlüssel „update_repo“). */
    public static function forCms(string $cmsRoot, string $dataDir): self
    {
        $j = json_decode((string)@file_get_contents(rtrim($cmsRoot, '/') . '/lib/product.default.json'), true);
        $repo = is_array($j) ? (string)($j['update_repo'] ?? '') : '';
        return new self(UpdateSettings::load($dataDir, $repo), $cmsRoot, $dataDir);
    }

    public function settings(): UpdateSettings
    {
        return $this->settings;
    }

    public function installedVersion(): string
    {
        $v = trim((string)@file_get_contents($this->cmsRoot . '/VERSION'));
        return preg_match('/^[0-9A-Za-z][0-9A-Za-z.+_-]{0,31}$/', $v) ? $v : 'unbekannt';
    }

    // ───────── Zustand ─────────

    /** Vollständige Sicht für die Verwaltung (nur Superadmin). @return array<string,mixed> */
    public function status(): array
    {
        $st = $this->readState();
        $latest = is_array($st['latest'] ?? null) ? $st['latest'] : null;
        return [
            'installed' => ['version' => $this->installedVersion(), 'ref' => (string)($st['installed_ref'] ?? ''), 'installed_at' => (string)($st['installed_at'] ?? '')],
            'latest' => $latest,
            'update_available' => $latest !== null && $this->newer($latest, $st),
            'checked_at' => (string)($st['checked_at'] ?? ''),
            'check_error' => (string)($st['check_error'] ?? ''),
            'pending' => is_array($st['pending'] ?? null) ? $st['pending'] : null,
            'history' => array_slice(is_array($st['history'] ?? null) ? $st['history'] : [], 0, 15),
            'snapshots' => $this->snapshots(),
            'settings' => $this->settings->adminView(),
            'running' => $this->isLocked(),
            'tools' => ['zip' => class_exists(\ZipArchive::class), 'curl' => function_exists('curl_init'), 'writable' => is_writable($this->cmsRoot) && is_writable($this->cmsRoot . '/VERSION')],
        ];
    }

    /** Kurzform für das Versions-Abzeichen aller angemeldeten Benutzer. @return array{version:string,update_available:bool,latest:string} */
    public function badge(): array
    {
        $st = $this->readState();
        $latest = is_array($st['latest'] ?? null) ? $st['latest'] : null;
        return ['version' => $this->installedVersion(), 'update_available' => $latest !== null && $this->newer($latest, $st), 'latest' => $latest !== null ? (string)$latest['version'] : ''];
    }

    /** @param array<string,mixed> $latest @param array<string,mixed> $st */
    private function newer(array $latest, array $st): bool
    {
        $inst = $this->installedVersion();
        if (($latest['kind'] ?? '') === 'commit') {
            $ref = (string)($st['installed_ref'] ?? '');   // Commit-Stand bekannt → exakter Vergleich, sonst (Tag/unbekannt) Versionsvergleich
            return preg_match('/^[0-9a-f]{40}$/', $ref) ? $ref !== (string)$latest['ref'] : Semver::compare((string)$latest['version'], $inst) > 0;
        }
        return Semver::compare((string)$latest['version'], $inst) > 0;
    }

    // ───────── Suchen ─────────

    /** Beim GitHub nachsehen und das Ergebnis merken. @return array<string,mixed> status() */
    public function check(): array
    {
        $st = $this->readState();
        $st['checked_at'] = gmdate('c');
        try {
            $st['latest'] = $this->source->latest();
            $st['check_error'] = '';
        } catch (UpdateException $e) {
            $st['check_error'] = $e->getMessage();
        }
        $this->writeState($st);
        return $this->status();
    }

    /** Automatische Suche im eingestellten Abstand (billig, wenn nichts fällig ist). */
    public function tick(): void
    {
        $h = (int)$this->settings->get('check_hours');
        $st = $this->readState();
        $last = isset($st['checked_at']) ? (int)strtotime((string)$st['checked_at']) : 0;
        if ($h > 0 && (time() - $last) >= $h * 3600) {
            $this->check();
        }
    }

    /** @return list<array<string,mixed>> verfügbare Versionen (für Downgrade-Auswahl) */
    public function versions(): array
    {
        $out = [];
        foreach ($this->source->releases() as $r) {
            $out[] = ['version' => $r['version'], 'ref' => $r['ref'], 'published' => $r['published'], 'prerelease' => $r['prerelease'], 'label' => $r['label']];
        }
        return $out;
    }

    // ───────── Einspielen ─────────

    /**
     * Update einspielen. Ohne $ref: neueste Version laut Kanal (nur wenn sie neuer ist). Mit $ref: genau diese Version/dieser Commit; ältere nur mit $allowDowngrade.
     * @return array<string,mixed> {from,to,added,updated,removed,checks,snapshot}
     */
    public function apply(string $ref = '', bool $allowDowngrade = false): array
    {
        @set_time_limit(300);
        ignore_user_abort(true);
        $lock = $this->lock();
        $stage = null;
        $zip = $this->stateDir() . '/work/pkg-' . bin2hex(random_bytes(4)) . '.zip';
        try {
            $st = $this->readState();
            $from = $this->installedVersion();
            if ($ref === '') {
                $latest = $this->source->latest();
                $st['latest'] = $latest;
                $st['checked_at'] = gmdate('c');
                $this->writeState($st);
                if (!$this->newer($latest, $st)) {
                    throw new UpdateException('Das CMS ist bereits auf dem neuesten Stand (' . $from . ').');
                }
                $src = ['ref' => $latest['ref'], 'zip_url' => $latest['zip_url'], 'zip_accept' => $latest['zip_accept'], 'sha256_url' => $latest['sha256_url']];
            } else {
                $src = $this->source->forRef($ref);
            }
            $this->source->download($src, $zip);
            $pkg = $this->tree->extractPackage($zip, (string)$this->settings->get('subdir'));
            $stage = $pkg['stage'];
            @unlink($zip);
            $to = $pkg['version'];
            if (!$allowDowngrade && Semver::compare($to, $from) < 0) {
                throw new UpdateException('Das Paket (' . $to . ') ist älter als die installierte Version (' . $from . ') – für ein Downgrade die Version gezielt wählen.');
            }
            $this->tree->checkWritable($pkg['files']);
            $this->preload();

            $snap = $this->makeSnapshot($from, (string)($st['installed_ref'] ?? ''));
            $minutes = (int)$this->settings->get('health_minutes');
            $st = $this->readState();
            $st['pending'] = ['from_version' => $from, 'from_ref' => (string)($st['installed_ref'] ?? ''), 'to_version' => $to, 'to_ref' => $src['ref'], 'snapshot' => $snap, 'started_at' => gmdate('c'), 'deadline' => gmdate('c', time() + $minutes * 60)];
            $this->writeState($st);

            try {
                $r = $this->tree->install($pkg['tree'], $pkg['files']);
                $checks = $this->healthFull($to, $pkg['files']);
            } catch (\Throwable $e) {
                $checks = ['ok' => false, 'items' => ['Einspielen' => 'fail: ' . ($e instanceof UpdateException ? $e->getMessage() : 'unerwarteter Fehler')]];
                $r = ['added' => 0, 'updated' => 0, 'removed' => 0];
            }
            if (!$checks['ok']) {
                $why = implode('; ', array_map(static fn($k, $v) => $k . ': ' . $v, array_keys($checks['items']), $checks['items']));
                $this->restore($snap, 'auto_rollback', 'Update auf ' . $to . ' fehlgeschlagen und automatisch zurückgenommen (' . $why . ')');
                throw new UpdateException('Das Update auf ' . $to . ' hat die Gesundheitsprüfung nicht bestanden und wurde automatisch zurückgenommen. Grund: ' . $why);
            }
            $st = $this->readState();
            $st['installed_ref'] = $src['ref'];
            $st['installed_at'] = gmdate('c');
            $this->addHistory($st, ['action' => Semver::compare($to, $from) < 0 ? 'downgrade' : 'update', 'from' => $from, 'to' => $to, 'status' => 'ok', 'message' => $r['added'] . ' neu, ' . $r['updated'] . ' geändert, ' . $r['removed'] . ' entfernt']);
            $this->writeState($st);
            $this->pruneSnapshots();
            return ['from' => $from, 'to' => $to] + $r + ['checks' => $checks['items'], 'snapshot' => $snap];
        } finally {
            @unlink($zip);
            if ($stage !== null) {
                TreeInstaller::rmTree($stage);
            }
            $this->unlock($lock);
        }
    }

    /** Alle Klassen laden, die nach dem Austausch der Dateien noch gebraucht werden (kein Mischstand aus alter und neuer Version). */
    private function preload(): void
    {
        foreach ([\Elvado\Support\Http::class, \Elvado\Support\HttpResponse::class, Semver::class, UpdateException::class] as $c) {
            class_exists($c);
        }
    }

    // ───────── Rückschritt ─────────

    /** Sicherung zurückspielen (leer = die zuletzt vor einem Update angelegte). @return array<string,mixed> */
    public function rollback(string $snapshot = ''): array
    {
        @set_time_limit(300);
        ignore_user_abort(true);
        $lock = $this->lock();
        try {
            $list = $this->snapshots();
            if ($list === []) {
                throw new UpdateException('Es gibt keine Sicherung zum Zurückspielen.');
            }
            $name = $snapshot !== '' ? $snapshot : (string)$list[0]['name'];
            $from = $this->installedVersion();
            $res = $this->restore($name, 'rollback', 'Auf Sicherung ' . $name . ' zurückgesetzt');
            return ['from' => $from, 'to' => $res['version']] + $res['counts'];
        } finally {
            $this->unlock($lock);
        }
    }

    /** @return array{version:string,counts:array<string,int>} */
    private function restore(string $name, string $action, string $message): array
    {
        if (!preg_match('/^[0-9]{8}-[0-9]{6}_[A-Za-z0-9._+-]{1,40}\.zip$/', $name) || !is_file($this->snapDir() . '/' . $name)) {
            throw new UpdateException('Unbekannte Sicherung.');
        }
        $meta = $this->snapMeta($name);
        $before = $this->installedVersion();
        $snapPkg = $this->tree->extractSnapshot($this->snapDir() . '/' . $name);
        try {
            $this->preload();
            $counts = $this->tree->install($snapPkg['tree'], $snapPkg['files']);
            $v = $this->tree->verifyFiles((string)$meta['version'] !== '' ? (string)$meta['version'] : trim((string)@file_get_contents($snapPkg['tree'] . '/VERSION')), $snapPkg['files']);
            if (!$v['ok']) {
                throw new UpdateException('Die Wiederherstellung ist unvollständig: ' . $v['message']);
            }
        } finally {
            TreeInstaller::rmTree($snapPkg['stage']);
        }
        $st = $this->readState();
        $st['installed_ref'] = (string)($meta['ref'] ?? '');
        unset($st['pending']);
        $this->addHistory($st, ['action' => $action, 'from' => $before, 'to' => $this->installedVersion(), 'status' => 'ok', 'message' => $message]);
        $this->writeState($st);
        $this->pruneSnapshots();
        return ['version' => $this->installedVersion(), 'counts' => $counts];
    }

    // ───────── Überwachung ─────────

    /**
     * Prüft ein frisch eingespieltes Update: krank → automatisch zurück; gesund und Frist abgelaufen → bestätigt.
     * @return array{action:string,message:string}|null null = nichts zu tun
     */
    public function watchdog(): ?array
    {
        $st = $this->readState();
        $p = $st['pending'] ?? null;
        if (!is_array($p) || $this->isLocked()) {
            return null;
        }
        $touch = $this->stateDir() . '/watchdog.touch';
        if (is_file($touch) && time() - (int)@filemtime($touch) < 20) {
            return null;
        }
        @touch($touch);
        $h = $this->healthBasic((string)$p['to_version']);
        if (!$h['ok']) {
            try {
                $this->rollback((string)$p['snapshot']);
                return ['action' => 'auto_rollback', 'message' => 'Update auf ' . $p['to_version'] . ' war fehlerhaft und wurde zurückgenommen.'];
            } catch (UpdateException $e) {
                return ['action' => 'rollback_failed', 'message' => $e->getMessage()];
            }
        }
        if (time() >= (int)strtotime((string)$p['deadline'])) {
            $this->confirm();
            return ['action' => 'confirmed', 'message' => 'Update ' . $p['to_version'] . ' bestätigt.'];
        }
        return null;
    }

    /** Update als gut bestätigen (beendet die Überwachung). */
    public function confirm(): void
    {
        $st = $this->readState();
        if (isset($st['pending'])) {
            unset($st['pending']);
            $this->writeState($st);
        }
    }

    /** Vollständige Prüfung direkt nach dem Einspielen. @param array<string,string> $files @return array{ok:bool,items:array<string,string>} */
    private function healthFull(string $version, array $files): array
    {
        $items = [];
        $ok = true;
        $v = $this->tree->verifyFiles($version, $files);
        $items['Dateien'] = $v['ok'] ? 'ok' : 'fail: ' . $v['message'];
        $ok = $ok && $v['ok'];
        foreach (['Neustart' => $this->tree->cliCheck($version), 'Website' => $this->tree->ping((string)$this->settings->get('base_url'))] as $k => $r) {
            $items[$k] = $r['state'] === 'ok' ? 'ok' : ($r['state'] === 'skipped' ? 'übersprungen (' . $r['message'] . ')' : 'fail: ' . $r['message']);
            $ok = $ok && $r['state'] !== 'fail';
        }
        return ['ok' => $ok, 'items' => $items];
    }

    /** @return array{ok:bool,items:array<string,string>} */
    private function healthBasic(string $version): array
    {
        $items = [];
        $v = trim((string)@file_get_contents($this->cmsRoot . '/VERSION'));
        $items['Version'] = $v === $version ? 'ok' : 'fail: ' . $v;
        $ok = $v === $version;
        foreach (['Neustart' => $this->tree->cliCheck($version), 'Website' => $this->tree->ping((string)$this->settings->get('base_url'))] as $k => $r) {
            $items[$k] = $r['state'];
            $ok = $ok && $r['state'] !== 'fail';
        }
        return ['ok' => $ok, 'items' => $items];
    }

    // ───────── Automatik (Cron / Webhook) ─────────

    /** Suchen, bei aktivierter Automatik einspielen, Überwachung. @return array<string,mixed> */
    public function automatic(): array
    {
        $out = ['watchdog' => $this->watchdog()];
        $s = $this->check();
        $out['update_available'] = $s['update_available'];
        $out['check_error'] = $s['check_error'];
        if ($s['update_available'] && $this->settings->get('auto_apply') && ($s['pending'] ?? null) === null) {
            try {
                $out['applied'] = $this->apply();
            } catch (UpdateException $e) {
                $out['error'] = $e->getMessage();
                $st = $this->readState();
                $this->addHistory($st, ['action' => 'auto_update', 'from' => $this->installedVersion(), 'to' => (string)($s['latest']['version'] ?? ''), 'status' => 'error', 'message' => mb_substr($e->getMessage(), 0, 300)]);
                $this->writeState($st);
            }
        }
        return $out;
    }

    /**
     * Webhook von GitHub (Ereignis release oder push). @param array<string,mixed> $server $_SERVER
     * @return array{status:int,body:array<string,mixed>,run:bool}
     */
    public function handleWebhook(array $server, string $raw): array
    {
        $secret = (string)$this->settings->get('secret');
        if (strtoupper((string)($server['REQUEST_METHOD'] ?? '')) !== 'POST') {
            return ['status' => 405, 'body' => ['status' => 'error', 'message' => 'Nur POST ist erlaubt.'], 'run' => false];
        }
        if ($secret === '' || strlen($raw) > 1_048_576 || !GitHubSyncService::verifySignature($raw, (string)($server['HTTP_X_HUB_SIGNATURE_256'] ?? ''), $secret)) {
            return ['status' => 401, 'body' => ['status' => 'error', 'message' => 'Signatur ungültig.'], 'run' => false];
        }
        $event = (string)($server['HTTP_X_GITHUB_EVENT'] ?? '');
        $j = json_decode($raw, true);
        $j = is_array($j) ? $j : [];
        if (strcasecmp((string)($j['repository']['full_name'] ?? ''), (string)$this->settings->get('repo')) !== 0 && $event !== 'ping') {
            return ['status' => 202, 'body' => ['status' => 'ignored', 'message' => 'Anderes Repository.'], 'run' => false];
        }
        if ($event === 'ping') {
            return ['status' => 200, 'body' => ['status' => 'ok', 'message' => 'pong'], 'run' => false];
        }
        $run = ($event === 'release' && in_array((string)($j['action'] ?? ''), ['published', 'released', 'created', 'edited'], true))
            || ($event === 'push' && $this->settings->get('channel') === 'main' && ($j['ref'] ?? '') === 'refs/heads/' . $this->settings->get('branch'))
            || ($event === 'create' && ($j['ref_type'] ?? '') === 'tag');
        return ['status' => $run ? 200 : 202, 'body' => ['status' => $run ? 'ok' : 'ignored', 'message' => $run ? 'Suche läuft.' : 'Ereignis ohne Bedeutung.'], 'run' => $run];
    }

    // ───────── Sicherungen ─────────

    /** @return list<array{name:string,version:string,ref:string,created_at:string,size:int}> neueste zuerst */
    public function snapshots(): array
    {
        $out = [];
        foreach (glob($this->snapDir() . '/*.zip') ?: [] as $f) {
            $n = basename($f);
            $m = $this->snapMeta($n);
            $out[] = ['name' => $n, 'version' => (string)$m['version'], 'ref' => (string)$m['ref'], 'created_at' => (string)$m['created_at'], 'size' => (int)filesize($f)];
        }
        usort($out, static fn($a, $b) => strcmp($b['name'], $a['name']));
        return $out;
    }

    /** @return array{version:string,ref:string,created_at:string} */
    private function snapMeta(string $name): array
    {
        $j = json_decode((string)@file_get_contents($this->snapDir() . '/' . substr($name, 0, -4) . '.json'), true);
        $j = is_array($j) ? $j : [];
        return ['version' => (string)($j['version'] ?? ''), 'ref' => (string)($j['ref'] ?? ''), 'created_at' => (string)($j['created_at'] ?? '')];
    }

    private function makeSnapshot(string $version, string $ref): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._+-]/', '', $version) ?: 'x';
        $name = gmdate('Ymd-His') . '_' . substr($safe, 0, 40) . '.zip';
        $files = $this->tree->snapshot($this->snapDir() . '/' . $name);
        file_put_contents($this->snapDir() . '/' . substr($name, 0, -4) . '.json', json_encode(['version' => $version, 'ref' => $ref, 'created_at' => gmdate('c'), 'files' => $files], JSON_UNESCAPED_SLASHES));
        return $name;
    }

    private function pruneSnapshots(): void
    {
        $keep = (int)$this->settings->get('keep_snapshots');
        $pending = (string)(($this->readState()['pending']['snapshot'] ?? ''));
        foreach (array_slice($this->snapshots(), $keep) as $s) {
            if ($s['name'] !== $pending) {
                @unlink($this->snapDir() . '/' . $s['name']);
                @unlink($this->snapDir() . '/' . substr($s['name'], 0, -4) . '.json');
            }
        }
    }

    // ───────── Notfall-Token (update-rescue.php) ─────────

    public function rescueToken(): string
    {
        $f = $this->stateDir() . '/rescue.token';
        $t = is_file($f) ? trim((string)@file_get_contents($f)) : '';
        if (!preg_match('/^[a-f0-9]{32}$/', $t)) {
            $t = bin2hex(random_bytes(16));
            @file_put_contents($f, $t, LOCK_EX);
            @chmod($f, 0640);
        }
        return $t;
    }

    public function rescueTokenValid(string $given): bool
    {
        return $given !== '' && hash_equals($this->rescueToken(), $given);
    }

    // ───────── Hilfen ─────────

    private function snapDir(): string
    {
        $d = $this->stateDir() . '/snapshots';
        if (!is_dir($d)) {
            @mkdir($d, 0775, true);
        }
        return $d;
    }

    private function stateDir(): string
    {
        $d = rtrim($this->dataDir, '/') . '/.update';
        if (!is_dir($d . '/work')) {
            @mkdir($d . '/work', 0775, true);
        }
        if (!is_file($d . '/.htaccess')) {
            @file_put_contents($d . '/.htaccess', "# Laufzeitdaten und Sicherungen: nie öffentlich abrufbar\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
        }
        return $d;
    }

    /** @return array<string,mixed> */
    private function readState(): array
    {
        $f = $this->stateDir() . '/state.json';
        $j = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
        return is_array($j) ? $j : [];
    }

    /** @param array<string,mixed> $st */
    private function writeState(array $st): void
    {
        $f = $this->stateDir() . '/state.json';
        $tmp = $f . '.' . bin2hex(random_bytes(3)) . '.tmp';
        if (@file_put_contents($tmp, json_encode($st, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false) {
            @rename($tmp, $f);
        }
    }

    /** @param array<string,mixed> $st @param array<string,mixed> $entry */
    private function addHistory(array &$st, array $entry): void
    {
        $h = is_array($st['history'] ?? null) ? $st['history'] : [];
        array_unshift($h, ['at' => gmdate('c')] + $entry);
        $st['history'] = array_slice($h, 0, self::HISTORY_MAX);
    }

    /** @return resource */
    private function lock()
    {
        $fh = fopen($this->stateDir() . '/lock', 'c');
        if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) {
            throw new UpdateException('Es läuft bereits ein Update oder eine Wiederherstellung.');
        }
        return $fh;
    }

    /** @param resource $fh */
    private function unlock($fh): void
    {
        flock($fh, LOCK_UN);
        fclose($fh);
    }

    private function isLocked(): bool
    {
        $fh = @fopen($this->stateDir() . '/lock', 'c');
        if (!$fh) {
            return false;
        }
        $got = flock($fh, LOCK_EX | LOCK_NB);
        if ($got) {
            flock($fh, LOCK_UN);
        }
        fclose($fh);
        return !$got;
    }
}

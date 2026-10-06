<?php
declare(strict_types=1);
// Login-Schutz, Protokoll, Sicherheits-Header, Prüfungen und Dateiintegrität des Plugins „Elvado Security“.

namespace ElvadoPlugin\Security;

use Elvado\Plugin\Context;

final class Guard
{
    public function __construct(private readonly Context $np) {}

    private function dir(): string { return $this->np->dataDir(); }
    private function s(string $k): mixed { return $this->np->setting($k); }

    // ---------------------------------------------------------------- Hilfen

    private function salt(): string
    {
        $f = $this->dir() . '/salt.txt';
        $s = is_file($f) ? trim((string)file_get_contents($f)) : '';
        if (strlen($s) < 32) {
            $s = bin2hex(random_bytes(24));
            @file_put_contents($f, $s, LOCK_EX);
            @chmod($f, 0600);
        }
        return $s;
    }

    /** Gekürzte Adresse für das Protokoll: IPv4 ohne letzten Block, IPv6 nur die ersten drei Blöcke. */
    public static function maskIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $p = explode('.', $ip);
            return $p[0] . '.' . $p[1] . '.' . $p[2] . '.0';
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $b = explode(':', (string)inet_ntop((string)inet_pton($ip)));
            return implode(':', array_slice($b, 0, 3)) . '::';
        }
        return '';
    }

    private function key(string $kind, string $v): string { return substr(hash_hmac('sha256', $kind . '|' . mb_strtolower($v), $this->salt()), 0, 24); }

    public static function cleanUser(string $u): string
    {
        $u = trim($u);
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._@-]{2,59}$/', $u) ? $u : '';   // alles andere könnte ein versehentlich eingegebenes Passwort sein
    }

    /** Datei lesen/ändern/schreiben unter Sperre. */
    private function rmw(string $name, callable $fn): mixed
    {
        $f = $this->dir() . '/' . $name;
        $h = @fopen($f, 'c+');
        if (!$h) {
            return null;
        }
        flock($h, LOCK_EX);
        $d = json_decode((string)stream_get_contents($h), true);
        $d = is_array($d) ? $d : [];
        $res = $fn($d);
        ftruncate($h, 0);
        rewind($h);
        fwrite($h, json_encode($d, JSON_UNESCAPED_SLASHES));
        fflush($h);
        flock($h, LOCK_UN);
        fclose($h);
        @chmod($f, 0600);
        return $res;
    }

    // ---------------------------------------------------------------- Login-Schutz

    /** Adresse des Besuchers; hinter einem vertrauten Proxy die linke Adresse aus X-Forwarded-For. */
    private function clientIp(string $given): string
    {
        if ($this->s('trust_proxy')) {
            foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR'] as $h) {
                $v = trim(explode(',', (string)($_SERVER[$h] ?? ''))[0]);
                if ($v !== '' && filter_var($v, FILTER_VALIDATE_IP)) {
                    return $v;
                }
            }
        }
        return $given;
    }

    /** Filter login_check: Fehlertext, wenn für diese Adresse oder diesen Benutzer gesperrt ist. */
    public function loginCheck(mixed $prev, string $user, string $ip): mixed
    {
        if (!$this->s('login_limit')) {
            return $prev;
        }
        $ip = $this->clientIp($ip);
        $now = time();
        $keys = [$this->key('ip', $ip)];
        if (self::cleanUser($user) !== '') {
            $keys[] = $this->key('user', $user);
        }
        $until = (int)$this->rmw('lockouts.json', function (array &$d) use ($keys, $now): int {
            $max = 0;
            foreach ($keys as $k) {
                $max = max($max, (int)($d[$k]['until'] ?? 0));
            }
            return $max > $now ? $max : 0;
        });
        if ($until > 0) {
            $min = max(1, (int)ceil(($until - $now) / 60));
            return 'Zu viele Fehlversuche. Bitte in ' . $min . ' Minute' . ($min === 1 ? '' : 'n') . ' erneut versuchen.';
        }
        return $prev;
    }

    /** Aktion login_result: zählen, sperren, protokollieren. */
    public function loginResult(string $user, bool $ok, string $ip): void
    {
        $ip = $this->clientIp($ip);
        $now = time();
        if ($this->s('login_limit')) {
            $max = max(3, (int)$this->s('max_attempts'));
            $win = max(1, (int)$this->s('window_minutes')) * 60;
            $lock = max(1, (int)$this->s('lock_minutes')) * 60;
            $kIp = $this->key('ip', $ip);
            $kUser = self::cleanUser($user) !== '' ? $this->key('user', $user) : '';
            $this->rmw('lockouts.json', function (array &$d) use ($ok, $now, $max, $win, $lock, $kIp, $kUser): void {
                foreach ($d as $k => $e) {   // Altes aufräumen
                    if ((int)($e['until'] ?? 0) < $now && !array_filter((array)($e['fails'] ?? []), static fn($t) => $t > $now - 86400)) {
                        unset($d[$k]);
                    }
                }
                foreach ([[$kIp, $max], [$kUser, $max * 3]] as [$k, $limit]) {   // je Benutzername großzügiger, damit niemand fremde Konten dauerhaft aussperren kann
                    if ($k === '') {
                        continue;
                    }
                    if ($ok) {
                        unset($d[$k]);
                        continue;
                    }
                    $fails = array_values(array_filter((array)($d[$k]['fails'] ?? []), static fn($t) => $t > $now - $win));
                    $fails[] = $now;
                    $d[$k] = ['fails' => array_slice($fails, -100), 'until' => count($fails) >= $limit ? $now + $lock : (int)($d[$k]['until'] ?? 0)];
                }
            });
        }
        if ($this->s('login_log')) {
            $line = json_encode(['t' => date('Y-m-d H:i:s', $now), 'u' => self::cleanUser($user) ?: '(ungültig)', 'ok' => $ok, 'ip' => self::maskIp($ip)], JSON_UNESCAPED_UNICODE) . "\n";
            @file_put_contents($this->dir() . '/login.jsonl', $line, FILE_APPEND | LOCK_EX);
        }
    }

    /** Tick: Protokoll auf die Aufbewahrungsdauer kürzen. */
    public function tick(): void
    {
        $f = $this->dir() . '/login.jsonl';
        if (!is_file($f) || filesize($f) < 2000 && mt_rand(1, 20) !== 1) {
            return;
        }
        $cut = date('Y-m-d H:i:s', time() - max(1, (int)$this->s('log_days')) * 86400);
        $keep = [];
        foreach (file($f, FILE_IGNORE_NEW_LINES) ?: [] as $l) {
            $e = json_decode($l, true);
            if (is_array($e) && ($e['t'] ?? '') >= $cut) {
                $keep[] = $l;
            }
        }
        @file_put_contents($f, $keep ? implode("\n", array_slice($keep, -5000)) . "\n" : '', LOCK_EX);
    }

    public function logEntries(int $n = 50): array
    {
        $f = $this->dir() . '/login.jsonl';
        $out = [];
        foreach (array_reverse(is_file($f) ? (file($f, FILE_IGNORE_NEW_LINES) ?: []) : []) as $l) {
            $e = json_decode($l, true);
            if (is_array($e)) {
                $out[] = $e;
            }
            if (count($out) >= $n) {
                break;
            }
        }
        return $out;
    }

    public function lockoutCount(): int
    {
        $now = time();
        return (int)$this->rmw('lockouts.json', static function (array &$d) use ($now): int {
            $n = 0;
            foreach ($d as $e) {
                if ((int)($e['until'] ?? 0) > $now) {
                    $n++;
                }
            }
            return $n;
        });
    }

    public function clearLockouts(): void { @unlink($this->dir() . '/lockouts.json'); }
    public function clearLog(): void { @unlink($this->dir() . '/login.jsonl'); }

    // ---------------------------------------------------------------- Header

    /** Sicherheits-Header setzen (Aktion boot). */
    public function sendHeaders(): void
    {
        if (!$this->s('headers') || headers_sent() || PHP_SAPI === 'cli') {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
        if ($this->s('frame_protection') === 'self') {
            header("Content-Security-Policy: frame-ancestors 'self'");
            header('X-Frame-Options: SAMEORIGIN');
        }
        if ($this->s('hsts') && self::isHttps()) {
            header('Strict-Transport-Security: max-age=15552000');
        }
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }

    // ---------------------------------------------------------------- Prüfungen

    /** @return list<array{label:string,status:string,text:string}> */
    public function checks(): array
    {
        $root = $this->np->rootDir();
        $cms = $this->np->cmsDir();
        $c = [];
        $add = static function (string $label, string $status, string $text) use (&$c): void { $c[] = ['label' => $label, 'status' => $status, 'text' => $text]; };
        $add('HTTPS', self::isHttps() ? 'ok' : 'warn', self::isHttps() ? 'Diese Verbindung ist verschlüsselt.' : 'Diese Anfrage kam ohne HTTPS an. Zugangsdaten sollten nur verschlüsselt übertragen werden.');
        $add('PHP-Version', version_compare(PHP_VERSION, '8.1.0', '>=') ? 'ok' : 'bad', 'PHP ' . PHP_VERSION . (version_compare(PHP_VERSION, '8.2.0', '<') ? ' – bitte bei Gelegenheit auf eine aktuell unterstützte Version aktualisieren.' : '.'));
        $add('Fehlerausgabe', ini_get('display_errors') && !in_array(strtolower((string)ini_get('display_errors')), ['0', 'off', 'stderr'], true) ? 'warn' : 'ok', ini_get('display_errors') && !in_array(strtolower((string)ini_get('display_errors')), ['0', 'off', 'stderr'], true) ? 'PHP zeigt Fehler im Browser an (display_errors). Auf einer Live-Website sollte das aus sein.' : 'PHP-Fehler werden nicht im Browser angezeigt.');
        $dataProtected = is_file($cms . '/data/.htaccess') || is_file($cms . '/data/.plugins/.htaccess');
        $add('Datenordner geschützt', $dataProtected ? 'ok' : 'warn', $dataProtected ? 'Eine .htaccess sperrt cms/data für Browser. (Mit „Zugriffsschutz testen“ prüfst du es per Abruf.)' : 'Keine .htaccess in cms/data gefunden. Bei Nicht-Apache-Servern muss der Zugriff in der Serverkonfiguration gesperrt werden.');
        $auth = $cms . '/data/local-auth.local.php';
        if (is_file($auth)) {
            $perm = fileperms($auth) & 0777;
            $add('Zugangsdatei', ($perm & 0007) === 0 ? 'ok' : 'warn', ($perm & 0007) === 0 ? 'Die Zugangsdatei ist nicht für alle lesbar.' : 'Die Zugangsdatei ist für alle Benutzer des Servers lesbar (Rechte ' . decoct($perm) . ').');
        }
        $users = function_exists('rrw_local_users') ? rrw_local_users() : [];
        $names = array_map(static fn($u) => mb_strtolower((string)($u['username'] ?? '')), $users);
        $weak = array_values(array_intersect($names, ['admin', 'administrator', 'root', 'test']));
        $add('Benutzernamen', $weak ? 'warn' : 'ok', $weak ? 'Es gibt ein Konto mit leicht erratbarem Namen („' . $weak[0] . '“). Ein individueller Benutzername erschwert Angriffe.' : (count($users) . ' Benutzerkonto' . (count($users) === 1 ? '' : 'en') . ', keine leicht erratbaren Namen.'));
        $add('Login-Schutz', $this->s('login_limit') ? 'ok' : 'warn', $this->s('login_limit') ? 'Fehlversuche werden begrenzt (' . (int)$this->s('max_attempts') . ' in ' . (int)$this->s('window_minutes') . ' Minuten).' : 'Die Begrenzung von Fehlversuchen ist ausgeschaltet.');
        $add('Sicherheits-Header', $this->s('headers') ? 'ok' : 'info', $this->s('headers') ? 'Werden gesendet.' : 'Sind ausgeschaltet.');
        $add('Backups geschützt', is_file($cms . '/backups/.htaccess') || !is_dir($cms . '/backups') ? 'ok' : 'warn', is_file($cms . '/backups/.htaccess') || !is_dir($cms . '/backups') ? 'Der Backup-Ordner ist per .htaccess gesperrt oder noch nicht angelegt.' : 'Im Backup-Ordner fehlt die .htaccess.');
        foreach (['allow_url_include'] as $ini) {
            if (ini_get($ini)) {
                $add('PHP-Einstellung ' . $ini, 'bad', $ini . ' ist eingeschaltet und sollte ausgeschaltet sein.');
            }
        }
        $mod = [];
        foreach (\Elvado\Plugin\Fs::files($this->np->cmsDir() . '/plugins') as $rel) {
            // nur zur Anzeige: veränderte offizielle Plugins meldet die Plugin-Verwaltung selbst
        }
        $writableRoot = is_writable($root . '/index.php');
        $add('Programmdateien beschreibbar', $writableRoot ? 'info' : 'ok', $writableRoot ? 'index.php ist für PHP beschreibbar (für Updates über das CMS nötig). Wer Updates von Hand einspielt, kann die Programmdateien schreibschützen.' : 'Programmdateien sind nicht für PHP beschreibbar.');
        return $c;
    }

    /** Test per Abruf: Sind geschützte Dateien öffentlich lesbar? */
    public function accessTest(): array
    {
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '' || !preg_match('/^[A-Za-z0-9.-]+(:\d+)?$/', $host)) {
            return ['ok' => false, 'message' => 'Die Adresse dieser Website ist nicht bekannt (Aufruf ohne Browser).'];
        }
        $base = (self::isHttps() ? 'https://' : 'http://') . $host;
        $tests = ['/cms/data/site.json' => 'Website-Daten', '/cms/data/.plugins/state.json' => 'Plugin-Zustand', '/cms/backups/' => 'Backup-Ordner'];
        $res = [];
        $exposed = 0;
        foreach ($tests as $p => $label) {
            $code = $this->headCode($base . $p);
            $state = $code === 0 ? 'unbekannt' : ($code >= 200 && $code < 300 ? 'ÖFFENTLICH LESBAR' : 'geschützt (' . $code . ')');
            if ($code >= 200 && $code < 300) {
                $exposed++;
            }
            $res[] = $label . ' ' . $p . ': ' . $state;
        }
        return ['ok' => $exposed === 0, 'message' => $exposed ? 'Achtung: ' . $exposed . ' Pfad(e) sind öffentlich lesbar. ' . implode(' · ', $res) : 'Geprüft: ' . implode(' · ', $res)];
    }

    private function headCode(string $url): int
    {
        $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 4, 'ignore_errors' => true, 'follow_location' => 0, 'header' => "Range: bytes=0-0\r\n"], 'ssl' => ['verify_peer' => true]]);
        $h = @fopen($url, 'r', false, $ctx);
        if (!$h) {
            return 0;
        }
        $meta = stream_get_meta_data($h);
        fclose($h);
        foreach ((array)($meta['wrapper_data'] ?? []) as $l) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', (string)$l, $m)) {
                return (int)$m[1];
            }
        }
        return 0;
    }

    // ---------------------------------------------------------------- Dateiintegrität

    private function scan(): array
    {
        $root = $this->np->rootDir();
        $skip = ['cms/data/', 'cms/media/', 'cms/backups/', 'cms/generated/', 'cms/wp-content/', 'cms/content/'];
        $out = [];
        $add = static function (string $rel) use (&$out, $root): void { $p = $root . '/' . $rel; if (is_file($p)) { $out[$rel] = sha1_file($p) ?: ''; } };
        foreach (['index.php', '.htaccess'] as $f) {
            $add($f);
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/cms', \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile() || !preg_match('/\.(php|phtml|htaccess)$/i', $f->getFilename())) {
                continue;
            }
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
            foreach ($skip as $s) {
                if (str_starts_with($rel, $s)) {
                    continue 2;
                }
            }
            $out[$rel] = sha1_file($f->getPathname()) ?: '';
        }
        ksort($out);
        return $out;
    }

    public function baseline(): array
    {
        $files = $this->scan();
        @file_put_contents($this->dir() . '/integrity.json', json_encode(['created' => date(DATE_ATOM), 'version' => $this->np->cmsVersion(), 'files' => $files], JSON_UNESCAPED_SLASHES), LOCK_EX);
        return ['ok' => true, 'message' => 'Prüf-Basis erstellt (' . count($files) . ' Dateien, ElvadoPress ' . $this->np->cmsVersion() . ').', 'count' => count($files)];
    }

    public function baselineInfo(): ?array
    {
        $d = json_decode((string)@file_get_contents($this->dir() . '/integrity.json'), true);
        return is_array($d) ? ['created' => (string)($d['created'] ?? ''), 'version' => (string)($d['version'] ?? ''), 'count' => count((array)($d['files'] ?? []))] : null;
    }

    /** @return array{ok:bool,message:string,changed:list<string>,added:list<string>,removed:list<string>} */
    public function check(): array
    {
        $d = json_decode((string)@file_get_contents($this->dir() . '/integrity.json'), true);
        if (!is_array($d) || !is_array($d['files'] ?? null)) {
            return ['ok' => false, 'message' => 'Es gibt noch keine Prüf-Basis. Bitte zuerst „Prüf-Basis neu erstellen“ ausführen.', 'changed' => [], 'added' => [], 'removed' => []];
        }
        $now = $this->scan();
        $old = $d['files'];
        $changed = [];
        $added = [];
        foreach ($now as $f => $h) {
            if (!isset($old[$f])) {
                $added[] = $f;
            } elseif ($old[$f] !== $h) {
                $changed[] = $f;
            }
        }
        $removed = array_values(array_diff(array_keys($old), array_keys($now)));
        $n = count($changed) + count($added) + count($removed);
        $msg = $n === 0 ? 'Keine Änderungen seit der Prüf-Basis (' . count($now) . ' Dateien geprüft).' : $n . ' Abweichung(en): ' . count($changed) . ' geändert, ' . count($added) . ' neu, ' . count($removed) . ' fehlend. Nach einem Update oder einer eigenen Änderung ist das normal – sonst bitte prüfen.';
        if ($n && ($d['version'] ?? '') !== $this->np->cmsVersion()) {
            $msg .= ' (Die Basis stammt von Version ' . ($d['version'] ?? '?') . ', installiert ist ' . $this->np->cmsVersion() . '.)';
        }
        return ['ok' => $n === 0, 'message' => $msg, 'changed' => array_slice($changed, 0, 100), 'added' => array_slice($added, 0, 100), 'removed' => array_slice($removed, 0, 100)];
    }
}

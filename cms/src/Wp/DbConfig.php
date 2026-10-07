<?php
declare(strict_types=1);
// cms/src/Wp/DbConfig.php – Datenbank der WordPress-Engine: Angaben prüfen, Verbindung testen, geschützt speichern.
// EINE Datenbank für alles: Die Verbindung (Server, Name, Benutzer, Passwort) kommt aus den Datenbank-Einstellungen des CMS (cms/data/database.local.php, MySQL/MariaDB);
// die Engine speichert nur ihr eigenes Tabellenpräfix (db.json, nur für den Webserver lesbar; nie im Repository). Ältere Installationen mit vollständiger db.json laufen weiter,
// bis sie zusammengeführt werden (unify). Das Tabellenpräfix der Engine muss sich vom Präfix der WordPress-Schicht des CMS unterscheiden.
// Das Passwort verlässt den Server nie (nicht in API-Antworten, nicht im Log).

namespace Elvado\Wp;

final class DbConfig
{
    public function __construct(private readonly Engine $engine)
    {
    }

    /** Eingaben bereinigen. @return array{ok:bool,message:string,cfg:array{host:string,name:string,user:string,pass:string,prefix:string}} */
    public function validate(array $in): array
    {
        $host = trim((string)($in['host'] ?? 'localhost'));
        $name = trim((string)($in['name'] ?? ''));
        $user = trim((string)($in['user'] ?? ''));
        $pass = (string)($in['pass'] ?? '');
        $prefix = trim((string)($in['prefix'] ?? 'wp_'));
        $err = '';
        if (preg_match('~^([A-Za-z0-9._-]{1,253}(:\d{1,5})?|localhost:/[A-Za-z0-9._/-]{1,200})$~', $host) !== 1) {
            $err = 'Der Datenbank-Server ist ungültig (erlaubt: Name, Name:Port oder localhost:/Pfad/zum/Socket).';
        } elseif (preg_match('/^[A-Za-z0-9_$-]{1,64}$/', $name) !== 1) {
            $err = 'Der Datenbankname ist ungültig (Buchstaben, Ziffern, _ und $; höchstens 64 Zeichen).';
        } elseif ($user === '' || strlen($user) > 80 || preg_match('/[\x00-\x1f]/', $user) === 1) {
            $err = 'Der Datenbank-Benutzer fehlt oder ist ungültig.';
        } elseif (strlen($pass) > 200 || str_contains($pass, "\0")) {
            $err = 'Das Datenbank-Passwort ist ungültig.';
        } elseif (preg_match('/^[a-z][a-z0-9]{0,18}_$/', $prefix) !== 1) {
            $err = 'Das Tabellenpräfix ist ungültig (Kleinbuchstaben und Ziffern, endet mit „_“, z. B. wp_).';
        }
        return ['ok' => $err === '', 'message' => $err, 'cfg' => ['host' => $host, 'name' => $name, 'user' => $user, 'pass' => $pass, 'prefix' => $prefix]];
    }

    /**
     * Verbindung prüfen (ohne etwas zu verändern).
     * @return array{ok:bool,message:string,server:string,wp_tables:int,needs_empty:bool}
     */
    public function test(array $cfg, string $minMysql = '5.5.5'): array
    {
        $v = $this->validate($cfg);
        $res = fn(bool $ok, string $m, string $server = '', int $t = 0): array => ['ok' => $ok, 'message' => $m, 'server' => $server, 'wp_tables' => $t, 'needs_empty' => false];
        if (!$v['ok']) {
            return $res(false, $v['message']);
        }
        if (!class_exists(\mysqli::class)) {
            return $res(false, 'Die PHP-Erweiterung mysqli fehlt auf diesem Server.');
        }
        $c = $v['cfg'];
        [$host, $port, $socket] = self::hostParts($c['host']);
        mysqli_report(MYSQLI_REPORT_OFF);
        $m = mysqli_init();
        $m->options(MYSQLI_OPT_CONNECT_TIMEOUT, 6);
        if (!@$m->real_connect($host, $c['user'], $c['pass'], $c['name'], $port, $socket)) {
            $no = (int)$m->connect_errno;
            return $res(false, match (true) {
                $no === 1045 => 'Anmeldung abgelehnt: Benutzername oder Passwort stimmen nicht.',
                $no === 1049 => 'Die Datenbank „' . $c['name'] . '“ existiert nicht. Bitte zuerst beim Hoster anlegen.',
                $no === 1044 => 'Der Benutzer hat keinen Zugriff auf diese Datenbank.',
                in_array($no, [2002, 2003, 2006], true) => 'Der Datenbank-Server ist nicht erreichbar (Adresse und Port prüfen).',
                default => 'Die Verbindung ist fehlgeschlagen (Fehler ' . $no . ').',
            });
        }
        $server = (string)$m->server_info;
        $maria = stripos($server, 'mariadb') !== false;
        $ver = (string)preg_replace('/^5\.5\.5-/', '', $server);
        $okVer = $maria ? version_compare((string)preg_replace('/[^0-9.].*$/', '', $ver), '10.0', '>=') : version_compare((string)preg_replace('/[^0-9.].*$/', '', $ver), $minMysql, '>=');
        if (!$okVer) {
            $m->close();
            return $res(false, 'Die Datenbank-Version (' . $server . ') ist für WordPress zu alt.', $server);
        }
        $like = $m->real_escape_string(str_replace(['_', '%'], ['\\_', '\\%'], $c['prefix'])) . '%';
        $tables = 0;
        $hasOptions = false;
        if ($r = $m->query("SHOW TABLES LIKE '" . $like . "'")) {
            while ($row = $r->fetch_row()) {
                $tables++;
                if ($row[0] === $c['prefix'] . 'options') {
                    $hasOptions = true;
                }
            }
            $r->close();
        }
        $m->close();
        $out = $res(true, $hasOptions ? 'Verbindung steht. In dieser Datenbank gibt es mit dem Präfix „' . $c['prefix'] . '“ bereits WordPress-Tabellen – sie werden nicht überschrieben.' : 'Verbindung steht (' . $server . ').', $server, $tables);
        $out['needs_empty'] = $hasOptions;
        return $out;
    }

    /** @return array{0:string,1:int,2:?string} */
    public static function hostParts(string $host): array
    {
        if (str_starts_with($host, 'localhost:/')) {
            return ['localhost', 0, substr($host, 10)];
        }
        $p = explode(':', $host, 2);
        return [$p[0], isset($p[1]) ? (int)$p[1] : 3306, null];
    }

    /** Standard-Präfix für neue Installationen (unterscheidet sich vom Präfix der WordPress-Schicht des CMS „wp_“). */
    public const DEFAULT_PREFIX = 'wpk_';

    public function sharedFile(): string
    {
        return defined('RRW_DB_CONFIG_FILE') ? (string)RRW_DB_CONFIG_FILE : dirname($this->engine->stateDir()) . '/database.local.php';
    }

    /** Gemeinsame Datenbank-Einstellungen des CMS (nur MySQL/MariaDB). @return array{host:string,name:string,user:string,pass:string,cms_prefix:string}|null */
    public function shared(): ?array
    {
        $f = $this->sharedFile();
        $c = is_file($f) ? @include $f : null;
        if (!is_array($c) || !in_array((string)($c['driver'] ?? ''), ['mysql', 'mariadb'], true) || (string)($c['database'] ?? '') === '' || (string)($c['user'] ?? '') === '') {
            return null;
        }
        $sock = (string)($c['socket'] ?? '');
        $host = $sock !== '' ? 'localhost:' . $sock : (string)($c['host'] ?? '127.0.0.1') . (((int)($c['port'] ?? 3306)) !== 3306 && (int)($c['port'] ?? 0) > 0 ? ':' . (int)$c['port'] : '');
        return ['host' => $host, 'name' => (string)$c['database'], 'user' => (string)$c['user'], 'pass' => (string)($c['password'] ?? ''), 'cms_prefix' => (string)($c['prefix'] ?? 'wp_')];
    }

    /** Ältere, vollständige db.json (eigene Verbindung der Engine) oder null. @return array{host:string,name:string,user:string,pass:string,prefix:string}|null */
    public function legacy(): ?array
    {
        $f = $this->engine->stateDir() . '/db.json';
        $d = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
        if (!is_array($d) || !isset($d['host'], $d['name'])) {
            return null;
        }
        $v = $this->validate($d);
        return $v['ok'] ? $v['cfg'] : null;
    }

    /** Eigenes Präfix der Engine aus db.json (auch wenn dort nur das Präfix steht) oder null. */
    public function enginePrefix(): ?string
    {
        $f = $this->engine->stateDir() . '/db.json';
        $d = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
        $p = is_array($d) ? (string)($d['prefix'] ?? '') : '';
        return preg_match('/^[a-z][a-z0-9]{0,18}_$/', $p) === 1 ? $p : null;
    }

    /** Das Präfix der Engine darf nicht das der WordPress-Schicht des CMS sein, wenn beide dieselbe Datenbank nutzen. */
    public function prefixConflict(string $prefix): ?string
    {
        $sh = $this->shared();
        if ($sh !== null && strcasecmp($sh['cms_prefix'], $prefix) === 0) {
            return 'Das Tabellenpräfix „' . $prefix . '“ nutzt schon die WordPress-Schicht des CMS in dieser Datenbank. Bitte ein anderes wählen (Vorschlag: ' . self::DEFAULT_PREFIX . ').';
        }
        return null;
    }

    /** Schreibt die gemeinsamen Datenbank-Einstellungen (MySQL), wenn es sie noch nicht gibt. Vorhandene Einstellungen werden nie verändert. @param array{host:string,name:string,user:string,pass:string} $c */
    public function writeShared(array $c, string $cmsPrefix = 'wpl_'): bool
    {
        if ($this->shared() !== null || is_file($this->sharedFile())) {
            $old = is_file($this->sharedFile()) ? @include $this->sharedFile() : null;
            if (is_array($old) && (string)($old['driver'] ?? 'none') !== 'none') {
                return false;
            }
        }
        [$host, $port, $sock] = self::hostParts($c['host']);
        $cfg = ['driver' => 'mysql', 'host' => $host, 'port' => $port > 0 ? $port : 3306, 'socket' => (string)$sock, 'database' => $c['name'], 'user' => $c['user'], 'password' => $c['pass'], 'prefix' => $cmsPrefix, 'charset' => 'utf8mb4', 'sqlite_path' => 'cms.sqlite'];
        if ($sock !== null) {
            $cfg['host'] = '127.0.0.1';
        }
        $f = $this->sharedFile();
        $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, "<?php\nreturn " . var_export($cfg, true) . ";\n", LOCK_EX) === false) {
            return false;
        }
        @chmod($tmp, 0600);
        if (!@rename($tmp, $f)) {
            @unlink($tmp);
            return false;
        }
        return true;
    }

    /** Zusammenführen: die Verbindung einer älteren db.json wird zur gemeinsamen Datenbank, db.json behält nur das Präfix. @return array{ok:bool,message:string} */
    public function unify(): array
    {
        $l = $this->legacy();
        if ($l === null) {
            return ['ok' => false, 'message' => 'Es gibt keine eigene Datenbank-Verbindung der Engine, die zusammengeführt werden müsste.'];
        }
        $sh = $this->shared();
        if ($sh !== null && ($sh['name'] !== $l['name'] || $sh['user'] !== $l['user'] || strcasecmp($sh['host'], $l['host']) !== 0)) {
            return ['ok' => false, 'message' => 'Die Datenbank-Einstellungen des CMS verweisen auf eine andere Datenbank (' . $sh['name'] . '). Bitte dort dieselbe Datenbank eintragen oder die Engine neu einrichten.'];
        }
        if ($sh === null && !$this->writeShared($l, strcasecmp($l['prefix'], 'wp_') === 0 ? 'wpl_' : 'wp_')) {
            return ['ok' => false, 'message' => 'Die gemeinsamen Datenbank-Einstellungen konnten nicht gespeichert werden (Schreibrechte für cms/data prüfen) oder sind schon anders belegt.'];
        }
        $this->savePrefixOnly($l['prefix']);
        return ['ok' => true, 'message' => 'Die Datenbank wird jetzt gemeinsam genutzt (System → Datenbank). Die Engine behält ihr Präfix „' . $l['prefix'] . '“.'];
    }

    private function savePrefixOnly(string $prefix): void
    {
        $this->engine->protect();
        $f = $this->engine->stateDir() . '/db.json';
        $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, json_encode(['prefix' => $prefix]), LOCK_EX) === false) {
            throw new \RuntimeException('Die Datenbank-Angaben konnten nicht gespeichert werden.');
        }
        @chmod($tmp, 0600);
        if (!@rename($tmp, $f)) {
            @unlink($tmp);
            throw new \RuntimeException('Die Datenbank-Angaben konnten nicht gespeichert werden.');
        }
    }

    /** Eingaben der Verwaltung mit der gemeinsamen Datenbank zusammenführen: ist sie vorhanden, zählt nur das Präfix. @param array<string,mixed> $in @return array<string,mixed> */
    public function withShared(array $in): array
    {
        $sh = $this->shared();
        if ($sh === null) {
            return $in;
        }
        $prefix = trim((string)($in['prefix'] ?? '')) !== '' ? (string)$in['prefix'] : ($this->enginePrefix() ?? self::DEFAULT_PREFIX);
        return ['host' => $sh['host'], 'name' => $sh['name'], 'user' => $sh['user'], 'pass' => $sh['pass'], 'prefix' => $prefix];
    }

    public function save(array $cfg): void
    {
        $v = $this->validate($this->withShared($cfg));
        if (!$v['ok']) {
            throw new \RuntimeException($v['message']);
        }
        if ($c = $this->prefixConflict($v['cfg']['prefix'])) {
            throw new \RuntimeException($c);
        }
        if ($this->shared() === null && defined('RRW_DEMO')) {   // Demo: eigene, abgeschottete Verbindung wie bisher (die Demo räumt ihre Tabellen selbst auf)
            $this->engine->protect();
            $f = $this->engine->stateDir() . '/db.json';
            $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (@file_put_contents($tmp, json_encode($v['cfg'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
                throw new \RuntimeException('Die Datenbank-Angaben konnten nicht gespeichert werden.');
            }
            @chmod($tmp, 0600);
            if (!@rename($tmp, $f)) {
                @unlink($tmp);
                throw new \RuntimeException('Die Datenbank-Angaben konnten nicht gespeichert werden.');
            }
            return;
        }
        if ($this->shared() === null) {
            // eine Datenbank für alles: die erste Verbindung der Engine wird zur gemeinsamen Datenbank des CMS (Präfix der WordPress-Schicht abweichend)
            $this->writeShared($v['cfg'], strcasecmp($v['cfg']['prefix'], 'wp_') === 0 ? 'wpl_' : 'wp_');
        }
        $this->savePrefixOnly($v['cfg']['prefix']);
    }

    /** Vollständige Angaben (nur für die Engine selbst): gemeinsame Verbindung + eigenes Präfix; sonst die ältere, vollständige db.json. */
    public function get(): ?array
    {
        $sh = $this->shared();
        if ($sh !== null) {
            $v = $this->validate($this->withShared([]));
            return $v['ok'] ? $v['cfg'] : null;
        }
        return $this->legacy();
    }

    /** Für die Verwaltung: ohne Passwort. @return array{host:string,name:string,user:string,prefix:string,has_password:bool,shared:bool}|null */
    public function redacted(): ?array
    {
        $c = $this->get();
        return $c === null ? null : ['host' => $c['host'], 'name' => $c['name'], 'user' => $c['user'], 'prefix' => $c['prefix'], 'has_password' => $c['pass'] !== '', 'shared' => $this->shared() !== null];
    }

    /** Zustand der gemeinsamen Datenbank für die Verwaltung (ohne Passwort). @return array{shared:bool,legacy:bool,cms_prefix:string,default_prefix:string,suggest_prefix:string} */
    public function overview(): array
    {
        $sh = $this->shared();
        return ['shared' => $sh !== null, 'legacy' => $sh === null && $this->legacy() !== null, 'cms_prefix' => $sh['cms_prefix'] ?? '', 'default_prefix' => self::DEFAULT_PREFIX, 'suggest_prefix' => $this->enginePrefix() ?? self::DEFAULT_PREFIX];
    }

    /** Schlüssel und Salze von WordPress: einmal erzeugen, geschützt speichern. @return array<string,string> */
    public function keys(): array
    {
        $f = $this->engine->stateDir() . '/keys.json';
        $d = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
        $names = ['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'];
        if (is_array($d) && count(array_intersect_key($d, array_flip($names))) === count($names)) {
            return array_intersect_key($d, array_flip($names));
        }
        $k = [];
        foreach ($names as $n) {
            $k[$n] = bin2hex(random_bytes(32));
        }
        $this->engine->protect();
        $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
        @file_put_contents($tmp, json_encode($k), LOCK_EX);
        @chmod($tmp, 0600);
        @rename($tmp, $f);
        return $k;
    }
}

<?php
declare(strict_types=1);
// cms/src/Database/DatabaseConnection.php
//
// Flexible PDO-Verbindung für den CMS-Kern: MySQL, MariaDB oder eine lokale SQLite-Datei, ausgewählt per Konfigurations-Array.
// - PDO-Fehlermodus Exception, echte Prepared Statements (ATTR_EMULATE_PREPARES=false), Werte werden ausschließlich gebunden.
// - Bezeichner (Tabellen/Spalten) werden nie aus Eingaben übernommen, sondern gegen ein festes Muster geprüft.
// - Passwörter tauchen nie in Fehlermeldungen auf.
// Die ältere, prozedurale Datenbank-Spiegelung (cms/lib/database.php) bleibt unverändert; diese Klasse nutzt deren Konfiguration mit.

namespace Elvado\Database;

use PDO;
use PDOException;
use PDOStatement;

final class DatabaseConnection
{
    public const DRIVERS = ['mysql', 'mariadb', 'sqlite'];

    private function __construct(
        private readonly PDO $pdo,
        private readonly string $driver,
    ) {
    }

    /**
     * @param array{driver?:string,host?:string,port?:int,socket?:string,database?:string,user?:string,password?:string,charset?:string,sqlite_path?:string} $config
     *        sqlite_path: Dateiname (relativ zu cms/data) oder absoluter Pfad; ":memory:" für Tests.
     */
    public static function fromConfig(array $config): self
    {
        $driver = strtolower((string)($config['driver'] ?? 'sqlite'));
        if (!in_array($driver, self::DRIVERS, true)) {
            throw new DatabaseException('Unbekannter Datenbanktreiber „' . $driver . '“ (erlaubt: mysql, mariadb, sqlite).');
        }
        try {
            $pdo = $driver === 'sqlite' ? self::sqlite($config) : self::mysql($config);
        } catch (PDOException $e) {
            throw new DatabaseException(self::friendly($e, $config), 0, $e);
        }
        return new self($pdo, $driver === 'mariadb' ? 'mysql' : $driver);
    }

    /**
     * Verbindung aus den CMS-Einstellungen (Einstellungen → Datenbank); ohne eingerichtete Datenbank (oder bei PostgreSQL) dient eine
     * lokale SQLite-Datei cms/data/cms-core.sqlite als Kernspeicher.
     */
    public static function fromCmsSettings(?string $dataDir = null): self
    {
        $dataDir ??= dirname(__DIR__, 2) . '/data';
        $cfg = [];
        if (function_exists('elvado_db_config')) {
            $c = elvado_db_config();
            if (in_array((string)$c['driver'], ['mysql', 'mariadb', 'sqlite'], true) && $c['driver'] !== 'sqlite') {
                $cfg = $c;
            }
        }
        if ($cfg === []) {
            $cfg = ['driver' => 'sqlite', 'sqlite_path' => rtrim($dataDir, '/') . '/cms-core.sqlite'];
        }
        return self::fromConfig($cfg);
    }

    private static function sqlite(array $config): PDO
    {
        if (!extension_loaded('pdo_sqlite')) {
            throw new DatabaseException('Die PHP-Erweiterung pdo_sqlite ist auf diesem Server nicht aktiv.');
        }
        $path = (string)($config['sqlite_path'] ?? 'cms-core.sqlite');
        if ($path !== ':memory:') {
            if ($path === '' || str_contains($path, "\0") || str_contains($path, '..')) {
                throw new DatabaseException('Ungültiger SQLite-Dateiname.');
            }
            if ($path[0] !== '/') {
                $path = dirname(__DIR__, 2) . '/data/' . $path;
            }
            $dir = dirname($path);
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new DatabaseException('Der Ordner der SQLite-Datei lässt sich nicht anlegen.');
            }
            if (!is_writable($dir) && !is_file($path)) {
                throw new DatabaseException('Der Ordner der SQLite-Datei ist nicht beschreibbar.');
            }
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        if ($path !== ':memory:') {
            $pdo->exec('PRAGMA journal_mode = WAL');
        }
        return $pdo;
    }

    private static function mysql(array $config): PDO
    {
        if (!extension_loaded('pdo_mysql')) {
            throw new DatabaseException('Die PHP-Erweiterung pdo_mysql ist auf diesem Server nicht aktiv.');
        }
        $db = (string)($config['database'] ?? '');
        $user = (string)($config['user'] ?? '');
        if ($db === '' || !preg_match('/^[A-Za-z0-9_\-$.]{1,64}$/', $db)) {
            throw new DatabaseException('Bitte einen gültigen Datenbanknamen angeben.');
        }
        if ($user === '') {
            throw new DatabaseException('Bitte einen Datenbankbenutzer angeben.');
        }
        $charset = (string)($config['charset'] ?? 'utf8mb4');
        if (!preg_match('/^[A-Za-z0-9]{3,20}$/', $charset)) {
            $charset = 'utf8mb4';
        }
        $socket = (string)($config['socket'] ?? '');
        if ($socket !== '') {
            if ($socket[0] !== '/' || str_contains($socket, "\0")) {
                throw new DatabaseException('Der Socket-Pfad muss absolut sein.');
            }
            $dsn = 'mysql:unix_socket=' . $socket;
        } else {
            $host = (string)($config['host'] ?? '127.0.0.1');
            if (!preg_match('/^[A-Za-z0-9.\-:\[\]]{1,253}$/', $host)) {
                throw new DatabaseException('Bitte einen gültigen Host angeben.');
            }
            $port = (int)($config['port'] ?? 3306);
            $dsn = 'mysql:host=' . $host . ';port=' . ($port > 0 && $port < 65536 ? $port : 3306);
        }
        $dsn .= ';dbname=' . $db . ';charset=' . $charset;
        return new PDO($dsn, $user, (string)($config['password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 6,
        ]);
    }

    private static function friendly(PDOException $e, array $config): string
    {
        $m = $e->getMessage();
        $code = (string)$e->getCode();
        if (str_contains($m, 'Access denied') || $code === '1045') {
            return 'Zugang zur Datenbank abgelehnt – Benutzername oder Passwort stimmt nicht.';
        }
        if (str_contains($m, 'Unknown database') || $code === '1049') {
            return 'Die Datenbank „' . (string)($config['database'] ?? '') . '“ existiert nicht.';
        }
        if (str_contains($m, 'Connection refused') || str_contains($m, '2002') || str_contains($m, 'getaddrinfo') || str_contains($m, 'timed out')) {
            return 'Der Datenbankserver ist unter dieser Adresse nicht erreichbar.';
        }
        $pw = (string)($config['password'] ?? '');
        return 'Datenbankfehler: ' . preg_replace('/\s+/', ' ', mb_substr($pw !== '' ? str_replace($pw, '***', $m) : $m, 0, 200));
    }

    // ───────── Zugriff ─────────

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** 'mysql' (auch für MariaDB) oder 'sqlite'. */
    public function driver(): string
    {
        return $this->driver;
    }

    public function isSqlite(): bool
    {
        return $this->driver === 'sqlite';
    }

    /** Prepared Statement ausführen (Werte nur über $params). */
    public function run(string $sql, array $params = []): PDOStatement
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /** @return int betroffene Zeilen */
    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /** @return list<array<string,mixed>> */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $r = $this->run($sql, $params)->fetch();
        return is_array($r) ? $r : null;
    }

    public function fetchValue(string $sql, array $params = []): mixed
    {
        $v = $this->run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /** Zeile einfügen; Tabellen- und Spaltennamen werden geprüft, Werte gebunden. @return string letzte ID */
    public function insert(string $table, array $data): string
    {
        $cols = array_keys($data);
        foreach ([$table, ...$cols] as $ident) {
            self::ident((string)$ident);
        }
        $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')';
        $this->run($sql, array_values($data));
        return (string)$this->pdo->lastInsertId();
    }

    /** @template T @param callable(self):T $fn @return T */
    public function transaction(callable $fn): mixed
    {
        $own = !$this->pdo->inTransaction();
        if ($own) {
            $this->pdo->beginTransaction();
        }
        try {
            $r = $fn($this);
            if ($own) {
                $this->pdo->commit();
            }
            return $r;
        } catch (\Throwable $e) {
            if ($own && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function tableExists(string $table): bool
    {
        self::ident($table);
        if ($this->isSqlite()) {
            return $this->fetchValue("SELECT name FROM sqlite_master WHERE type='table' AND name = ?", [$table]) !== null;
        }
        return $this->fetchValue('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table]) !== null;
    }

    /** Datum/Uhrzeit im Format beider Datenbanken. */
    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private static function ident(string $name): void
    {
        if (!preg_match('/^[a-z_][a-z0-9_]{0,63}$/', $name)) {
            throw new DatabaseException('Ungültiger Bezeichner.');
        }
    }

    // ───────── Migration ─────────

    /**
     * Wendet eine SQL-Datei einmalig an (Version in schema_migrations). Platzhalter der Datei: {{PK}}, {{LONGTEXT}}, {{DATETIME}}, {{TABLE_OPTS}}.
     * Doppelte Indizes werden ignoriert. @return bool true, wenn die Datei neu angewendet wurde
     */
    public function migrate(string $schemaFile, string $version): bool
    {
        if (!preg_match('/^[A-Za-z0-9_.\-]{1,64}$/', $version)) {
            throw new DatabaseException('Ungültige Migrationsversion.');
        }
        $this->pdo->exec(strtr(
            'CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(64) NOT NULL PRIMARY KEY, applied_at {{DATETIME}} NOT NULL){{TABLE_OPTS}}',
            $this->tokens()
        ));
        if ($this->fetchValue('SELECT version FROM schema_migrations WHERE version = ?', [$version]) !== null) {
            return false;
        }
        $raw = @file_get_contents($schemaFile);
        if ($raw === false) {
            throw new DatabaseException('Die Schema-Datei fehlt: ' . basename($schemaFile));
        }
        $sql = strtr($raw, $this->tokens());
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;   // Zeilenkommentare entfernen
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            try {
                $this->pdo->exec($statement);
            } catch (PDOException $e) {
                // CREATE INDEX ist auf MySQL nicht mit IF NOT EXISTS möglich: ein vorhandener Index ist kein Fehler
                $dupIndex = stripos($statement, 'CREATE INDEX') === 0 && (str_contains($e->getMessage(), 'Duplicate key name') || str_contains($e->getMessage(), 'already exists'));
                if (!$dupIndex) {
                    throw new DatabaseException('Migration „' . $version . '“ fehlgeschlagen: ' . mb_substr($e->getMessage(), 0, 200), 0, $e);
                }
            }
        }
        $this->run('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)', [$version, self::now()]);
        return true;
    }

    /** @return array<string,string> */
    private function tokens(): array
    {
        return $this->isSqlite()
            ? ['{{PK}}' => 'INTEGER PRIMARY KEY AUTOINCREMENT', '{{LONGTEXT}}' => 'TEXT', '{{DATETIME}}' => 'TEXT', '{{TABLE_OPTS}}' => '']
            : ['{{PK}}' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', '{{LONGTEXT}}' => 'LONGTEXT', '{{DATETIME}}' => 'DATETIME', '{{TABLE_OPTS}}' => ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'];
    }

    /** Standard-Schema des CMS-Kerns (cms/src/Database/schema.sql) anwenden. */
    public function migrateCore(): bool
    {
        return $this->migrate(__DIR__ . '/schema.sql', '001_core');
    }
}

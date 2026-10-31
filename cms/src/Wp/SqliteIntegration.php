<?php
declare(strict_types=1);
// cms/src/Wp/SqliteIntegration.php – WordPress ohne Datenbankserver: offizielle „SQLite Database Integration“ (WordPress Performance Team, wordpress.org).
//
// Das Paket wird wie ein Plugin von downloads.wordpress.org geladen und mit denselben Prüfungen wie Plugins eingespielt (ExtensionInstaller: Struktur, Dateitypen,
// Größen, PHP-Syntax), aber NICHT nach wp-content/plugins, sondern nach cms/wp-engine/sqlite-database-integration/ (gesperrter Ordner, kein sichtbares Plugin).
// WordPress lädt eine Datenbank-Schicht nur über den Drop-in wp-content/db.php: ElvadoPress legt dort einen eigenen, kleinen Drop-in an, der ausschließlich wirkt,
// wenn die Engine DB_ENGINE = 'sqlite' setzt (Bridge::prepare). Mit MySQL/MariaDB (DB_ENGINE = 'mysql') tut er nichts.

namespace Elvado\Wp;

final class SqliteIntegration
{
    public const SLUG = 'sqlite-database-integration';
    public const MARKER = 'ELVADOPRESS-SQLITE-DROPIN';

    public function __construct(private readonly Engine $engine)
    {
    }

    public function dir(): string { return $this->engine->coreRoot() . '/' . self::SLUG; }
    public function dropinFile(): string { return $this->engine->contentDir() . '/db.php'; }
    public function present(): bool { return is_file($this->dir() . '/wp-includes/sqlite/db.php') && $this->dropinOk(); }

    /** Version des eingespielten Pakets ('' wenn keins da ist). */
    public function version(): string
    {
        $f = $this->dir() . '/load.php';
        if (!is_file($f)) {
            return '';
        }
        return preg_match('/^[ \t\/*#@]*Version:\s*([0-9A-Za-z._-]{1,30})/mi', (string)@file_get_contents($f, false, null, 0, 8192), $m) ? $m[1] : '';
    }

    /** Ist ein Drop-in vorhanden, der nicht von ElvadoPress stammt? Dann wird er nie überschrieben. */
    public function foreignDropin(): bool
    {
        $f = $this->dropinFile();
        return is_file($f) && !str_contains((string)@file_get_contents($f, false, null, 0, 4096), self::MARKER);
    }

    private function dropinOk(): bool
    {
        $f = $this->dropinFile();
        return is_file($f) && str_contains((string)@file_get_contents($f, false, null, 0, 4096), self::MARKER);
    }

    /** Drop-in schreiben (nur wenn keiner da ist oder er von ElvadoPress stammt). */
    public function writeDropin(): void
    {
        if ($this->foreignDropin()) {
            throw new \RuntimeException('In wp-content liegt bereits eine fremde db.php (Datenbank-Drop-in eines anderen Plugins). Sie wird nicht überschrieben – bitte entfernen oder MySQL/MariaDB verwenden.');
        }
        $code = "<?php\n// " . self::MARKER . " – von ElvadoPress angelegt. Wirkt nur für die WordPress-Engine mit SQLite (DB_ENGINE = 'sqlite'); mit MySQL/MariaDB tut diese Datei nichts.\n"
            . "if (defined('DB_ENGINE') && DB_ENGINE === 'sqlite' && defined('ELVADO_SQLITE_IMPL') && is_file(ELVADO_SQLITE_IMPL . '/wp-includes/sqlite/db.php')) {\n"
            . "    if (!defined('DATABASE_TYPE')) {\n        define('DATABASE_TYPE', 'sqlite');\n    }\n"
            . "    require_once ELVADO_SQLITE_IMPL . '/wp-includes/sqlite/db.php';\n}\n";
        $dir = $this->engine->contentDir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Der Ordner wp-content lässt sich nicht anlegen.');
        }
        $f = $this->dropinFile();
        $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $code, LOCK_EX) === false || !@rename($tmp, $f)) {
            @unlink($tmp);
            throw new \RuntimeException('Der Datenbank-Drop-in (wp-content/db.php) lässt sich nicht schreiben.');
        }
        @chmod($f, 0644);
    }

    /**
     * Aktuelle Fassung von wordpress.org laden, prüfen und einspielen (oder ein bereitgestelltes ZIP, z. B. für Tests ohne Internet), danach den Drop-in anlegen.
     * @return array{ok:bool,message:string,version:string}
     */
    public function install(string $localZip = ''): array
    {
        $fail = fn(string $m): array => ['ok' => false, 'message' => $m, 'version' => ''];
        if (!extension_loaded('pdo_sqlite')) {
            return $fail('Die PHP-Erweiterung pdo_sqlite fehlt auf diesem Server.');
        }
        if ($this->foreignDropin()) {
            return $fail('In wp-content liegt bereits eine fremde db.php (Datenbank-Drop-in eines anderen Plugins). Sie wird nicht überschrieben – bitte entfernen oder MySQL/MariaDB verwenden.');
        }
        $this->engine->protect();
        $zip = $localZip;
        $tmpZip = '';
        if ($zip === '') {
            $info = ExtensionSource::info('plugin', self::SLUG);
            if (!$info['ok']) {
                return $fail('Die SQLite-Integration ist bei wordpress.org gerade nicht abrufbar: ' . $info['message']);
            }
            if ($this->version() === $info['version'] && is_file($this->dir() . '/wp-includes/sqlite/db.php')) {
                try {
                    $this->writeDropin();
                } catch (\RuntimeException $e) {
                    return $fail($e->getMessage());
                }
                return ['ok' => true, 'message' => 'Die SQLite-Integration ' . $info['version'] . ' ist schon eingespielt.', 'version' => $info['version']];
            }
            $tmpZip = $zip = $this->engine->stateDir() . '/download-sqlite-' . bin2hex(random_bytes(4)) . '.zip';
            $d = ExtensionSource::download('plugin', self::SLUG, $info['version'], $zip);
            if (!$d['ok']) {
                return $fail($d['message']);
            }
        }
        try {
            $r = (new ExtensionInstaller($this->engine))->install('plugin', $zip, true, $this->engine->coreRoot());
        } finally {
            if ($tmpZip !== '') {
                @unlink($tmpZip);
            }
        }
        if (!$r['ok']) {
            return $fail($r['message']);
        }
        if ($r['slug'] !== self::SLUG || !is_file($this->dir() . '/wp-includes/sqlite/db.php')) {
            (new ExtensionInstaller($this->engine))->rmTree($this->engine->coreRoot() . '/' . $r['slug']);
            return $fail('Das Paket enthält nicht die erwartete SQLite-Integration.');
        }
        try {
            $this->writeDropin();
        } catch (\RuntimeException $e) {
            return $fail($e->getMessage());
        }
        $this->engine->log('SQLite-Integration ' . $r['version'] . ' eingespielt');
        return ['ok' => true, 'message' => 'SQLite-Integration ' . $r['version'] . ' eingespielt.', 'version' => $r['version']];
    }
}

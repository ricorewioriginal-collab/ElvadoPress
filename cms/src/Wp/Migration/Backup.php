<?php
declare(strict_types=1);
// cms/src/Wp/Migration/Backup.php – Sicherung vor der Migration: Kopie von cms/data (ohne Engine-Zustand und Sitzungen) und SQL-Auszug der WordPress-Tabellen.
// Die Medien werden nicht kopiert: die Migration verändert cms/media nie (sie liest nur).

namespace Elvado\Wp\Migration;

final class Backup
{
    public function __construct(private readonly string $dataDir, private readonly string $stateDir)
    {
    }

    /** @return array{dir:string,files:int,bytes:int,sql:string,tables:int} */
    public function create(string $runId): array
    {
        $dir = rtrim($this->stateDir, '/') . '/migration/backup-' . $runId;
        if (!@mkdir($dir . '/data', 0700, true) && !is_dir($dir . '/data')) {
            throw new \RuntimeException('Der Sicherungsordner lässt sich nicht anlegen.');
        }
        $files = 0;
        $bytes = 0;
        $base = rtrim($this->dataDir, '/');
        if (is_dir($base)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)) as $f) {
                /** @var \SplFileInfo $f */
                $rel = substr($f->getPathname(), strlen($base) + 1);
                if (!$f->isFile() || str_starts_with($rel, '.wp-engine/') || str_contains($rel, 'session')) {
                    continue;
                }
                $to = $dir . '/data/' . $rel;
                if (!is_dir(dirname($to))) {
                    @mkdir(dirname($to), 0700, true);
                }
                if (!@copy($f->getPathname(), $to)) {
                    throw new \RuntimeException('Sicherung fehlgeschlagen bei ' . $rel . '.');
                }
                @chmod($to, 0600);
                $files++;
                $bytes += (int)$f->getSize();
            }
        }
        [$sql, $tables] = $this->dump($dir . '/wordpress.sql');
        return ['dir' => $dir, 'files' => $files, 'bytes' => $bytes, 'sql' => $sql, 'tables' => $tables];
    }

    /** @return array{0:string,1:int} */
    private function dump(string $file): array
    {
        global $wpdb;
        $h = @fopen($file, 'wb');
        if (!$h) {
            throw new \RuntimeException('Die Datenbank-Sicherung lässt sich nicht anlegen.');
        }
        @chmod($file, 0600);
        $n = 0;
        foreach ((array)$wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->base_prefix) . '%')) as $t) {
            $n++;
            $create = $wpdb->get_row('SHOW CREATE TABLE `' . str_replace('`', '', (string)$t) . '`', ARRAY_N);
            fwrite($h, "DROP TABLE IF EXISTS `$t`;\n" . (string)($create[1] ?? '') . ";\n");
            for ($off = 0;; $off += 500) {
                $rows = (array)$wpdb->get_results('SELECT * FROM `' . $t . '` LIMIT 500 OFFSET ' . $off, ARRAY_A);
                if ($rows === []) {
                    break;
                }
                foreach ($rows as $r) {
                    $vals = array_map(static fn($v) => $v === null ? 'NULL' : "'" . $wpdb->_real_escape((string)$v) . "'", array_values($r));
                    fwrite($h, 'INSERT INTO `' . $t . '` VALUES (' . implode(',', $vals) . ");\n");
                }
            }
        }
        fclose($h);
        return [$file, $n];
    }
}

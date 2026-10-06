<?php
declare(strict_types=1);
// cms/src/Plugin/Fs.php – Dateihilfen für die Plugin-Verwaltung: Baum kopieren/löschen, Prüfsumme eines Plugin-Ordners, PHP-Syntaxprüfung.

namespace Elvado\Plugin;

final class Fs
{
    /** Alle Dateien eines Ordners (relativ, sortiert). */
    public static function files(string $dir): array
    {
        $out = [];
        if (!is_dir($dir)) {
            return $out;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && !$f->isLink()) {
                $out[] = str_replace('\\', '/', substr($f->getPathname(), strlen(rtrim($dir, '/\\')) + 1));
            }
        }
        sort($out, SORT_STRING);
        return $out;
    }

    /** SHA-256 über Dateinamen und -inhalte (unabhängig von Zeitstempeln und Rechten). */
    public static function treeHash(string $dir): string
    {
        $ctx = hash_init('sha256');
        foreach (self::files($dir) as $rel) {
            hash_update($ctx, $rel . "\0" . hash_file('sha256', $dir . '/' . $rel) . "\n");
        }
        return hash_final($ctx);
    }

    public static function copyTree(string $src, string $dst): void
    {
        if (!is_dir($dst) && !@mkdir($dst, 0755, true) && !is_dir($dst)) {
            throw new \RuntimeException('Ordner kann nicht angelegt werden.');
        }
        foreach (self::files($src) as $rel) {
            $to = $dst . '/' . $rel;
            if (!is_dir(dirname($to)) && !@mkdir(dirname($to), 0755, true) && !is_dir(dirname($to))) {
                throw new \RuntimeException('Ordner kann nicht angelegt werden.');
            }
            if (!@copy($src . '/' . $rel, $to)) {
                throw new \RuntimeException('Datei konnte nicht kopiert werden: ' . $rel);
            }
        }
    }

    public static function rmTree(string $dir): void
    {
        if (!is_dir($dir) || is_link($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $n) {
            if ($n === '.' || $n === '..') {
                continue;
            }
            $p = $dir . '/' . $n;
            is_dir($p) && !is_link($p) ? self::rmTree($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    /** PHP-Syntax aller .php-Dateien prüfen. @return list<string> Fehlertexte */
    public static function lint(string $dir): array
    {
        $errors = [];
        foreach (self::files($dir) as $rel) {
            if (!str_ends_with($rel, '.php')) {
                continue;
            }
            try {
                token_get_all((string)file_get_contents($dir . '/' . $rel), TOKEN_PARSE);
            } catch (\ParseError $e) {
                $errors[] = $rel . ': ' . $e->getMessage() . ' (Zeile ' . $e->getLine() . ')';
            }
        }
        return $errors;
    }

    public static function writeJson(string $file, array $data): void
    {
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Datenordner nicht beschreibbar.');
        }
        $tmp = $file . '.tmp' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX) === false || !@rename($tmp, $file)) {
            @unlink($tmp);
            throw new \RuntimeException('Datei konnte nicht gespeichert werden.');
        }
    }

    public static function readJson(string $file, array $fallback = []): array
    {
        $d = is_file($file) ? json_decode((string)@file_get_contents($file), true) : null;
        return is_array($d) ? $d : $fallback;
    }
}

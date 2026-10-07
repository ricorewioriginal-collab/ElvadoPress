<?php
declare(strict_types=1);
// cms/src/Wp/CoreSource.php – Offizielle WordPress-Version, Download und Prüfsumme (wordpress.org).
// Sicherheit: Die Download-Adresse wird selbst aus der Versionsnummer gebaut (nicht aus der Antwort übernommen), nur feste Hosts, SHA-1 der Veröffentlichung wird geprüft.

namespace Elvado\Wp;

use Elvado\Support\Http;

final class CoreSource
{
    public const API = 'https://api.wordpress.org/core/version-check/1.7/';
    public const HOSTS = ['api.wordpress.org', 'downloads.wordpress.org', 'wordpress.org'];
    public const MAX_ZIP_BYTES = 120_000_000;

    public static function validVersion(string $v): bool { return preg_match('/^\d{1,2}\.\d{1,2}(\.\d{1,2})?$/', $v) === 1; }
    public static function zipUrl(string $v): string { return 'https://downloads.wordpress.org/release/wordpress-' . $v . '.zip'; }

    /** Aktuelle stabile Version samt Mindestanforderungen. @return array{ok:bool,message:string,version:string,php:string,mysql:string} */
    public static function latest(): array
    {
        $r = Http::request('GET', self::API, [], null, ['timeout' => 15, 'hosts' => self::HOSTS, 'max_bytes' => 400_000]);
        if ($r->status !== 200) {
            return ['ok' => false, 'message' => 'Die aktuelle WordPress-Version konnte nicht abgefragt werden (' . ($r->error ?: 'HTTP ' . $r->status) . ').', 'version' => '', 'php' => '', 'mysql' => ''];
        }
        $d = json_decode($r->body, true);
        $o = is_array($d) ? ($d['offers'][0] ?? null) : null;
        $v = is_array($o) ? (string)($o['current'] ?? $o['version'] ?? '') : '';
        if (!self::validVersion($v)) {
            return ['ok' => false, 'message' => 'Die Antwort von wordpress.org enthält keine gültige Version.', 'version' => '', 'php' => '', 'mysql' => ''];
        }
        return ['ok' => true, 'message' => '', 'version' => $v, 'php' => (string)($o['php_version'] ?? '7.4'), 'mysql' => (string)($o['mysql_version'] ?? '5.5.5')];
    }

    /** SHA-1 der veröffentlichten ZIP-Datei. @return array{ok:bool,message:string,sha1:string} */
    public static function checksum(string $version): array
    {
        if (!self::validVersion($version)) {
            return ['ok' => false, 'message' => 'Ungültige Versionsnummer.', 'sha1' => ''];
        }
        $r = Http::request('GET', self::zipUrl($version) . '.sha1', [], null, ['timeout' => 15, 'hosts' => self::HOSTS, 'max_bytes' => 4000]);
        $s = strtolower(trim(explode(' ', trim($r->body))[0] ?? ''));
        if ($r->status !== 200 || preg_match('/^[a-f0-9]{40}$/', $s) !== 1) {
            return ['ok' => false, 'message' => 'Die Prüfsumme dieser WordPress-Version konnte nicht abgerufen werden.', 'sha1' => ''];
        }
        return ['ok' => true, 'message' => '', 'sha1' => $s];
    }

    /** ZIP laden und gegen die Prüfsumme prüfen. @return array{ok:bool,message:string} */
    public static function download(string $version, string $dest, string $sha1): array
    {
        if (!self::validVersion($version) || preg_match('/^[a-f0-9]{40}$/', $sha1) !== 1) {
            return ['ok' => false, 'message' => 'Ungültige Angaben für den Download.'];
        }
        $r = Http::request('GET', self::zipUrl($version), [], null, ['timeout' => 280, 'hosts' => self::HOSTS, 'max_bytes' => self::MAX_ZIP_BYTES, 'save_to' => $dest, 'max_redirects' => 3]);
        if ($r->status !== 200 || !is_file($dest)) {
            @unlink($dest);
            return ['ok' => false, 'message' => 'Der Download von WordPress ist fehlgeschlagen (' . ($r->error ?: 'HTTP ' . $r->status) . ').'];
        }
        if (!hash_equals($sha1, strtolower((string)sha1_file($dest)))) {
            @unlink($dest);
            return ['ok' => false, 'message' => 'Die Prüfsumme der heruntergeladenen Datei stimmt nicht mit der veröffentlichten überein – die Datei wurde verworfen.'];
        }
        return ['ok' => true, 'message' => ''];
    }
}

<?php
declare(strict_types=1);
// cms/src/Wp/ExtensionSource.php – Plugins und Themes von wordpress.org: Suche, Version, Download.
// Sicherheit: nur feste Hosts über HTTPS mit Zertifikatsprüfung; Kennung (Slug) und Version werden validiert; die Download-Adresse wird selbst gebaut, nicht aus der Antwort übernommen.
// wordpress.org veröffentlicht für Plugins/Themes keine Prüfsummen – Schutz sind TLS, Strukturprüfung und PHP-Syntaxprüfung im ExtensionInstaller; die SHA-256 des Pakets wird im Protokoll festgehalten.

namespace Elvado\Wp;

use Elvado\Support\Http;

final class ExtensionSource
{
    public const HOSTS = ['api.wordpress.org', 'downloads.wordpress.org'];
    public const MAX_ZIP_BYTES = 80_000_000;

    public static function validKind(string $k): bool { return $k === 'plugin' || $k === 'theme'; }
    public static function validSlug(string $s): bool { return preg_match('/^[a-z0-9][a-z0-9_-]{0,79}$/', $s) === 1; }
    public static function validVersion(string $v): bool { return preg_match('/^[0-9A-Za-z][0-9A-Za-z._-]{0,29}$/', $v) === 1; }
    public static function zipUrl(string $kind, string $slug, string $version): string
    {
        return 'https://downloads.wordpress.org/' . $kind . '/' . $slug . '.' . $version . '.zip';
    }

    /**
     * Suche im offiziellen Verzeichnis.
     * @return array{ok:bool,message:string,items:list<array{slug:string,name:string,version:string,author:string,description:string,rating:int,installs:int,requires_php:string}>,pages:int}
     */
    public static function search(string $kind, string $query, int $page = 1): array
    {
        $fail = fn(string $m): array => ['ok' => false, 'message' => $m, 'items' => [], 'pages' => 0];
        if (!self::validKind($kind)) {
            return $fail('Unbekannte Art.');
        }
        $q = mb_substr(trim($query), 0, 80);
        $page = max(1, min(50, $page));
        $base = $kind === 'plugin' ? 'https://api.wordpress.org/plugins/info/1.2/?action=query_plugins' : 'https://api.wordpress.org/themes/info/1.2/?action=query_themes';
        $params = ['request[page]' => $page, 'request[per_page]' => 12, 'request[locale]' => 'de_DE'];
        if ($q !== '') {
            $params['request[search]'] = $q;
        } else {
            $params['request[browse]'] = 'popular';
        }
        $r = Http::request('GET', $base . '&' . http_build_query($params), [], null, ['timeout' => 20, 'hosts' => self::HOSTS, 'max_bytes' => 2_000_000]);
        if ($r->status !== 200) {
            return $fail('Das Verzeichnis von wordpress.org ist nicht erreichbar (' . ($r->error ?: 'HTTP ' . $r->status) . ').');
        }
        $d = json_decode($r->body, true);
        $list = is_array($d) ? (array)($d[$kind === 'plugin' ? 'plugins' : 'themes'] ?? []) : [];
        $items = [];
        foreach ($list as $p) {
            $p = (array)$p;
            $slug = (string)($p['slug'] ?? '');
            $ver = (string)($p['version'] ?? '');
            if (!self::validSlug($slug) || !self::validVersion($ver)) {
                continue;
            }
            $author = $p['author'] ?? '';
            if (is_array($author)) {
                $author = $author['display_name'] ?? $author['user_nicename'] ?? '';
            }
            $items[] = [
                'slug' => $slug, 'name' => mb_substr(trim(strip_tags(html_entity_decode((string)($p['name'] ?? $slug)))), 0, 120), 'version' => $ver,
                'author' => mb_substr(trim(strip_tags((string)$author)), 0, 80),
                'description' => mb_substr(trim(strip_tags(html_entity_decode((string)($p['short_description'] ?? $p['description'] ?? '')))), 0, 300),
                'rating' => (int)round((float)($p['rating'] ?? 0)), 'installs' => (int)($p['active_installs'] ?? 0), 'requires_php' => mb_substr((string)($p['requires_php'] ?? ''), 0, 10),
            ];
        }
        $pages = (int)($d['info']['pages'] ?? 1);
        return ['ok' => true, 'message' => '', 'items' => $items, 'pages' => max(1, $pages)];
    }

    /** Aktuelle Version eines Eintrags. @return array{ok:bool,message:string,version:string,name:string} */
    public static function info(string $kind, string $slug): array
    {
        $fail = fn(string $m): array => ['ok' => false, 'message' => $m, 'version' => '', 'name' => ''];
        if (!self::validKind($kind) || !self::validSlug($slug)) {
            return $fail('Ungültige Angaben.');
        }
        $url = $kind === 'plugin'
            ? 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request%5Bslug%5D=' . rawurlencode($slug)
            : 'https://api.wordpress.org/themes/info/1.2/?action=theme_information&request%5Bslug%5D=' . rawurlencode($slug);
        $r = Http::request('GET', $url, [], null, ['timeout' => 20, 'hosts' => self::HOSTS, 'max_bytes' => 1_500_000]);
        $d = $r->status === 200 ? json_decode($r->body, true) : null;
        if (!is_array($d) || ($d['slug'] ?? '') !== $slug || !self::validVersion((string)($d['version'] ?? ''))) {
            return $fail($r->status === 404 || (is_array($d) && isset($d['error'])) ? 'Diesen Eintrag gibt es bei wordpress.org nicht.' : 'Die Angaben konnten nicht abgerufen werden (' . ($r->error ?: 'HTTP ' . $r->status) . ').');
        }
        return ['ok' => true, 'message' => '', 'version' => (string)$d['version'], 'name' => mb_substr(trim(strip_tags(html_entity_decode((string)($d['name'] ?? $slug)))), 0, 120)];
    }

    /** Paket laden. @return array{ok:bool,message:string,sha256:string} */
    public static function download(string $kind, string $slug, string $version, string $dest): array
    {
        if (!self::validKind($kind) || !self::validSlug($slug) || !self::validVersion($version)) {
            return ['ok' => false, 'message' => 'Ungültige Angaben für den Download.', 'sha256' => ''];
        }
        $r = Http::request('GET', self::zipUrl($kind, $slug, $version), [], null, ['timeout' => 120, 'hosts' => self::HOSTS, 'max_bytes' => self::MAX_ZIP_BYTES, 'save_to' => $dest, 'max_redirects' => 3]);
        if ($r->status !== 200 || !is_file($dest) || filesize($dest) < 100) {
            @unlink($dest);
            return ['ok' => false, 'message' => 'Der Download ist fehlgeschlagen (' . ($r->error ?: 'HTTP ' . $r->status) . ').', 'sha256' => ''];
        }
        return ['ok' => true, 'message' => '', 'sha256' => (string)hash_file('sha256', $dest)];
    }
}

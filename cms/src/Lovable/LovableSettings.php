<?php
declare(strict_types=1);
// cms/src/Lovable/LovableSettings.php
//
// Einstellungen der Lovable-Anbindung: Widget-Skripte (Adressvorlage + erlaubte Hosts), erlaubte Herkunft (CORS) für den Beitrags-Provider,
// Datenquelle der Beiträge und das verknüpfte GitHub-Repository (Token, Webhook-Geheimnis). Datei: cms/data/.lovable/settings.json (gesperrter Ordner).
// Geheimnisse (GitHub-Token, Webhook-Geheimnis) verlassen den Server nie; die Admin-Sicht meldet nur „gesetzt“.

namespace Elvado\Lovable;

final class LovableSettings
{
    /** @param array<string,mixed> $data */
    private function __construct(private array $data, private readonly string $file)
    {
    }

    public static function load(string $dataDir): self
    {
        $file = rtrim($dataDir, '/') . '/.lovable/settings.json';
        $raw = is_file($file) ? json_decode((string)@file_get_contents($file), true) : null;
        return new self(self::clean(is_array($raw) ? $raw : []), $file);
    }

    /** @return array<string,mixed> */
    private static function clean(array $in): array
    {
        $b = is_array($in['bridge'] ?? null) ? $in['bridge'] : [];
        $g = is_array($in['github'] ?? null) ? $in['github'] : [];
        $hosts = [];
        foreach ((array)($b['script_hosts'] ?? []) as $h) {
            $h = strtolower(trim((string)$h));
            if (preg_match('/^[a-z0-9]([a-z0-9.-]{0,251}[a-z0-9])?$/', $h) && str_contains($h, '.') && !in_array($h, $hosts, true) && count($hosts) < 20) {
                $hosts[] = $h;
            }
        }
        $origins = [];
        foreach ((array)($b['allowed_origins'] ?? []) as $o) {
            $o = rtrim(strtolower(trim((string)$o)), '/');
            $host = (string)parse_url($o, PHP_URL_HOST);
            if (preg_match('~^https?://[a-z0-9]([a-z0-9.-]{0,251}[a-z0-9])?(:\d{2,5})?$~', $o) && (str_contains($host, '.') || $host === 'localhost') && !in_array($o, $origins, true) && count($origins) < 20) {
                $origins[] = $o;
            }
        }
        $tpl = trim((string)($b['script_url_template'] ?? ''));
        $tplHost = strtolower((string)parse_url(str_replace('{projectId}', 'x', $tpl), PHP_URL_HOST));
        $src = (string)($b['post_source'] ?? 'auto');
        $repo = trim((string)($g['repo'] ?? ''));
        $branch = trim((string)($g['branch'] ?? 'main'));
        $inc = [];
        foreach ((array)($g['include'] ?? self::defaultInclude()) as $p) {
            $p = trim((string)$p);
            if (preg_match('~^[A-Za-z0-9_./*-]{1,100}$~', $p) && !str_contains($p, '..') && !str_starts_with($p, '/') && count($inc) < 30) {
                $inc[] = $p;
            }
        }
        return [
            'bridge' => [
                'script_url_template' => (preg_match('~^https://[^\s"\'<>]{4,300}$~', $tpl) && substr_count($tpl, '{projectId}') <= 1 && in_array($tplHost, $hosts, true)) ? $tpl : '',
                'script_hosts' => $hosts,
                'allowed_origins' => $origins,
                'post_source' => in_array($src, ['auto', 'file', 'db'], true) ? $src : 'auto',
            ],
            'github' => [
                'repo' => preg_match('~^[A-Za-z0-9_.-]{1,100}/[A-Za-z0-9_.-]{1,100}$~', $repo) ? $repo : '',
                'branch' => preg_match('~^[A-Za-z0-9_./-]{1,100}$~', $branch) && !str_contains($branch, '..') ? $branch : 'main',
                'token' => preg_match('/^[\x21-\x7E]{8,300}$/', trim((string)($g['token'] ?? ''))) ? trim((string)$g['token']) : '',
                'secret' => preg_match('/^[\x21-\x7E]{16,200}$/', trim((string)($g['secret'] ?? ''))) ? trim((string)$g['secret']) : '',
                'project' => preg_match('/^[a-z0-9][a-z0-9_-]{0,60}$/', (string)($g['project'] ?? '')) ? (string)$g['project'] : 'app',
                'include' => $inc ?: self::defaultInclude(),
                'auto_sync' => !array_key_exists('auto_sync', $g) || !empty($g['auto_sync']),
            ],
        ];
    }

    /** @return list<string> */
    public static function defaultInclude(): array
    {
        return ['src/', 'public/', 'dist/', 'index.html', 'package.json', 'tsconfig.json', 'vite.config.ts', 'tailwind.config.ts', 'components.json'];
    }

    /** @return array<string,mixed> */
    public function bridge(): array
    {
        return $this->data['bridge'];
    }

    /** @return array<string,mixed> */
    public function github(): array
    {
        return $this->data['github'];
    }

    /** Adresse des Widget-Skripts eines Projekts (leer = nicht konfiguriert/nicht erlaubt). */
    public function scriptUrl(string $projectId, string $override = ''): string
    {
        $url = $override !== '' ? $override : str_replace('{projectId}', rawurlencode($projectId), (string)$this->data['bridge']['script_url_template']);
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        return ($url !== '' && str_starts_with($url, 'https://') && in_array($host, $this->data['bridge']['script_hosts'], true)) ? $url : '';
    }

    public function originAllowed(string $origin): bool
    {
        return in_array(rtrim(strtolower($origin), '/'), $this->data['bridge']['allowed_origins'], true);
    }

    /**
     * Speichern. Geheimnisse: Text = setzen, '' oder fehlend = behalten, '__clear__' = entfernen.
     * @param array<string,mixed> $in
     */
    public function save(array $in): void
    {
        $new = $this->data;
        foreach (['bridge', 'github'] as $sec) {
            if (is_array($in[$sec] ?? null)) {
                $new[$sec] = array_merge($new[$sec], $in[$sec]);
            }
        }
        foreach (['token', 'secret'] as $k) {
            $v = is_array($in['github'] ?? null) ? trim((string)($in['github'][$k] ?? '')) : '';
            $new['github'][$k] = $v === '__clear__' ? '' : ($v === '' ? $this->data['github'][$k] : $v);
        }
        $this->write(self::clean($new));
    }

    /** Neues Webhook-Geheimnis erzeugen und speichern; der Klartext wird nur hier zurückgegeben. */
    public function rotateSecret(): string
    {
        $secret = bin2hex(random_bytes(24));
        $new = $this->data;
        $new['github']['secret'] = $secret;
        $this->write(self::clean($new));
        return $secret;
    }

    /** @param array<string,mixed> $d */
    private function write(array $d): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Der Einstellungsordner ist nicht beschreibbar.');
        }
        if (!is_file($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "# Laufzeitdaten: nie öffentlich abrufbar\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
        }
        $tmp = $this->file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false || !@rename($tmp, $this->file)) {
            @unlink($tmp);
            throw new \RuntimeException('Einstellungen konnten nicht gespeichert werden.');
        }
        @chmod($this->file, 0600);
        $this->data = $d;
    }

    /** Admin-Sicht ohne Geheimnisse. @return array<string,mixed> */
    public function adminView(): array
    {
        $g = $this->data['github'];
        $g['token_set'] = $g['token'] !== '';
        $g['secret_set'] = $g['secret'] !== '';
        unset($g['token'], $g['secret']);
        return ['bridge' => $this->data['bridge'], 'github' => $g];
    }
}

<?php
declare(strict_types=1);
// cms/src/Update/UpdateSettings.php
//
// Einstellungen der CMS-Aktualisierung (cms/data/.update/settings.json, gesperrter Ordner; bleibt bei Updates erhalten).
//   repo        GitHub-Repository „besitzer/name“ mit den CMS-Versionen (Standard: product.default.json → update_repo)
//   channel     release = höchste veröffentlichte Version (Tag/Release, SemVer), beta = auch Vorabversionen, main = jeweils neuester Stand des Branches
//   branch      Branch für channel=main
//   subdir      Ordner im Repository, der dem CMS-Ordner entspricht (Standard „cms“; leer = Repository-Wurzel)
//   token       optionaler GitHub-Token (Leserecht „Contents“, nötig bei privaten Repositories) – verlässt den Server nie
//   secret      Geheimnis für den Update-Webhook (HMAC-SHA256)
//   check_hours automatische Suche nach Updates im Abstand von n Stunden (0 = aus)
//   auto_apply  Updates über Webhook/Cron automatisch einspielen (aus = nur Hinweis)
//   health_minutes Frist, in der ein neues Update nach dem Einspielen überwacht und bei Fehlern automatisch zurückgenommen wird

namespace Elvado\Update;

final class UpdateSettings
{
    public const CHANNELS = ['release', 'beta', 'main'];

    /** @param array<string,mixed> $data */
    private function __construct(private array $data, private readonly string $file)
    {
    }

    public static function load(string $dataDir, string $defaultRepo = ''): self
    {
        $file = rtrim($dataDir, '/') . '/.update/settings.json';
        $raw = is_file($file) ? json_decode((string)@file_get_contents($file), true) : null;
        $raw = is_array($raw) ? $raw : [];
        if (!isset($raw['repo']) || trim((string)$raw['repo']) === '') {
            $raw['repo'] = $defaultRepo;
        }
        return new self(self::clean($raw), $file);
    }

    /** @return array<string,mixed> */
    private static function clean(array $in): array
    {
        $repo = trim((string)($in['repo'] ?? ''));
        $channel = (string)($in['channel'] ?? 'release');
        $branch = trim((string)($in['branch'] ?? 'main'));
        $subdir = trim(str_replace('\\', '/', (string)($in['subdir'] ?? 'cms')), '/');
        return [
            'repo' => preg_match('~^[A-Za-z0-9_.-]{1,100}/[A-Za-z0-9_.-]{1,100}$~', $repo) ? $repo : '',
            'channel' => in_array($channel, self::CHANNELS, true) ? $channel : 'release',
            'branch' => preg_match('~^[A-Za-z0-9._/-]{1,100}$~', $branch) && !str_contains($branch, '..') ? $branch : 'main',
            'subdir' => preg_match('~^[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+)*$~', $subdir) && !str_contains($subdir, '..') ? $subdir : '',
            'token' => preg_match('/^[A-Za-z0-9_\-.]{10,255}$/', (string)($in['token'] ?? '')) ? (string)$in['token'] : '',
            'secret' => preg_match('/^[A-Za-z0-9]{16,128}$/', (string)($in['secret'] ?? '')) ? (string)$in['secret'] : '',
            'check_hours' => max(0, min(168, (int)($in['check_hours'] ?? 6))),
            'auto_apply' => !empty($in['auto_apply']),
            'health_minutes' => max(2, min(120, (int)($in['health_minutes'] ?? 10))),
            'keep_snapshots' => max(1, min(10, (int)($in['keep_snapshots'] ?? 3))),
            'base_url' => preg_match('#^https?://[A-Za-z0-9.-]+(:\d+)?(/[A-Za-z0-9._~/-]*)?$#', (string)($in['base_url'] ?? '')) ? rtrim((string)$in['base_url'], '/') : '',
        ];
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $this->data;
    }

    /** Eingaben übernehmen: Token/Secret nur bei Änderung (leer = behalten, „__clear__“ = löschen). @param array<string,mixed> $in */
    public function save(array $in): void
    {
        $merged = array_merge($this->data, array_intersect_key($in, array_flip(['repo', 'channel', 'branch', 'subdir', 'check_hours', 'auto_apply', 'health_minutes', 'keep_snapshots', 'base_url'])));
        foreach (['token', 'secret'] as $k) {
            $v = isset($in[$k]) ? trim((string)$in[$k]) : '';
            if ($v === '__clear__') {
                $merged[$k] = '';
            } elseif ($v !== '') {
                $merged[$k] = $v;
            }
        }
        $this->data = self::clean($merged);
        $this->write();
    }

    /** Neues Webhook-Geheimnis erzeugen; Klartext nur im Rückgabewert. */
    public function rotateSecret(): string
    {
        $s = bin2hex(random_bytes(20));
        $this->data['secret'] = $s;
        $this->write();
        return $s;
    }

    public function rememberBaseUrl(string $url): void
    {
        $c = self::clean(array_merge($this->data, ['base_url' => $url]));
        if ($c['base_url'] !== '' && $c['base_url'] !== $this->data['base_url']) {
            $this->data['base_url'] = $c['base_url'];
            $this->write();
        }
    }

    /** Sicht für die Verwaltung (ohne Geheimnisse). @return array<string,mixed> */
    public function adminView(): array
    {
        $v = $this->data;
        $v['token_set'] = $v['token'] !== '';
        $v['secret_set'] = $v['secret'] !== '';
        unset($v['token'], $v['secret']);
        return $v;
    }

    private function write(): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $tmp = $this->file . '.' . bin2hex(random_bytes(3)) . '.tmp';
        if (@file_put_contents($tmp, json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
            throw new UpdateException('Die Update-Einstellungen konnten nicht gespeichert werden.');
        }
        @chmod($tmp, 0640);
        @rename($tmp, $this->file);
    }
}

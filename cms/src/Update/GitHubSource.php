<?php
declare(strict_types=1);
// cms/src/Update/GitHubSource.php
//
// Abfrage der CMS-Versionen auf GitHub (nur api.github.com, Weiterleitung auf codeload/objects erlaubt) – nur lesend.
//   channel release/beta → Releases (mit Tags als Rückfall), die nach SemVer höchste Version gewinnt (nicht „zuletzt veröffentlicht“)
//   channel main         → neuester Commit des Branches; Version aus <subdir>/VERSION dieses Commits
// Download: bevorzugt ein Release-Anhang „*.zip“ (mit optionaler „*.sha256“-Prüfsumme), sonst das Archiv des Tags/Commits.

namespace Elvado\Update;

use Elvado\Support\Http;

final class GitHubSource
{
    public const API = 'https://api.github.com';
    public const MAX_ZIP_BYTES = 125_829_120;   // 120 MB

    public function __construct(private readonly UpdateSettings $settings)
    {
    }

    /**
     * Neueste verfügbare Version laut Kanal.
     * @return array{version:string,ref:string,label:string,zip_url:string,zip_accept:string,sha256_url:string,published:string,notes:string,prerelease:bool,kind:string}
     */
    public function latest(): array
    {
        $this->needRepo();
        if ($this->settings->get('channel') === 'main') {
            return $this->mainHead();
        }
        $list = $this->releases();
        if ($list === []) {
            throw new UpdateException('In diesem Repository gibt es noch keine Version (Release oder Tag der Form v1.2.3).');
        }
        return $list[0];
    }

    /**
     * Alle verfügbaren Versionen (höchste zuerst) für Update und Downgrade.
     * @return list<array{version:string,ref:string,label:string,zip_url:string,zip_accept:string,sha256_url:string,published:string,notes:string,prerelease:bool,kind:string}>
     */
    public function releases(): array
    {
        $this->needRepo();
        $repo = (string)$this->settings->get('repo');
        $beta = $this->settings->get('channel') === 'beta';
        $out = [];
        $rel = $this->get('/repos/' . $repo . '/releases?per_page=50');
        foreach ($rel as $r) {
            if (!is_array($r) || !empty($r['draft'])) {
                continue;
            }
            $tag = (string)($r['tag_name'] ?? '');
            $ver = ltrim($tag, 'vV');
            $pre = !empty($r['prerelease']) || Semver::isPrerelease($ver);
            if (!Semver::valid($ver) || !preg_match('~^[A-Za-z0-9._-]{1,80}$~', $tag) || ($pre && !$beta)) {
                continue;
            }
            $zip = self::API . '/repos/' . $repo . '/zipball/' . rawurlencode($tag);
            $accept = 'application/vnd.github+json';
            $sha = '';
            foreach ((array)($r['assets'] ?? []) as $a) {
                $n = strtolower((string)($a['name'] ?? ''));
                $u = (string)($a['url'] ?? '');
                if (!str_starts_with($u, self::API . '/')) {
                    continue;
                }
                if (str_ends_with($n, '.zip') && $accept !== 'application/octet-stream') {
                    $zip = $u;
                    $accept = 'application/octet-stream';
                } elseif (str_ends_with($n, '.sha256')) {
                    $sha = $u;
                }
            }
            $out[$ver] = ['version' => $ver, 'ref' => $tag, 'label' => (string)($r['name'] ?? '') !== '' ? mb_substr((string)$r['name'], 0, 120) : $tag, 'zip_url' => $zip, 'zip_accept' => $accept, 'sha256_url' => $accept === 'application/octet-stream' ? $sha : '', 'published' => (string)($r['published_at'] ?? ''), 'notes' => mb_substr((string)($r['body'] ?? ''), 0, 2000), 'prerelease' => $pre, 'kind' => 'release'];
        }
        if ($out === []) {   // Rückfall: Tags ohne Release
            foreach ($this->get('/repos/' . $repo . '/tags?per_page=50') as $t) {
                $tag = is_array($t) ? (string)($t['name'] ?? '') : '';
                $ver = ltrim($tag, 'vV');
                if (Semver::valid($ver) && (!Semver::isPrerelease($ver) || $beta) && preg_match('~^[A-Za-z0-9._-]{1,80}$~', $tag)) {
                    $out[$ver] = ['version' => $ver, 'ref' => $tag, 'label' => $tag, 'zip_url' => self::API . '/repos/' . $repo . '/zipball/' . rawurlencode($tag), 'zip_accept' => 'application/vnd.github+json', 'sha256_url' => '', 'published' => '', 'notes' => '', 'prerelease' => Semver::isPrerelease($ver), 'kind' => 'tag'];
                }
            }
        }
        $list = array_values($out);
        usort($list, static fn(array $a, array $b): int => Semver::compare($b['version'], $a['version']));
        return $list;
    }

    /** @return array{version:string,ref:string,label:string,zip_url:string,zip_accept:string,sha256_url:string,published:string,notes:string,prerelease:bool,kind:string} */
    private function mainHead(): array
    {
        $repo = (string)$this->settings->get('repo');
        $branch = (string)$this->settings->get('branch');
        $c = $this->get('/repos/' . $repo . '/commits/' . rawurlencode($branch));
        $sha = (string)($c['sha'] ?? '');
        if (!preg_match('/^[0-9a-f]{40}$/', $sha)) {
            throw new UpdateException('GitHub lieferte keinen gültigen Commit für den Branch „' . $branch . '“.');
        }
        $sub = (string)$this->settings->get('subdir');
        $ver = '';
        try {
            $f = $this->get('/repos/' . $repo . '/contents/' . ($sub !== '' ? $sub . '/' : '') . 'VERSION?ref=' . $sha);
            $ver = trim((string)base64_decode((string)($f['content'] ?? ''), false));
        } catch (UpdateException) {
        }
        $ver = preg_match('/^[0-9A-Za-z][0-9A-Za-z.+_-]{0,31}$/', $ver) ? $ver : '0.0.0';
        $msg = (string)($c['commit']['message'] ?? '');
        return ['version' => $ver, 'ref' => $sha, 'label' => substr($sha, 0, 7) . ' · ' . mb_substr(strtok($msg, "\n") ?: '', 0, 100), 'zip_url' => self::API . '/repos/' . $repo . '/zipball/' . $sha, 'zip_accept' => 'application/vnd.github+json', 'sha256_url' => '', 'published' => (string)($c['commit']['committer']['date'] ?? ''), 'notes' => mb_substr($msg, 0, 2000), 'prerelease' => false, 'kind' => 'commit'];
    }

    /** Archiv für einen beliebigen Tag/Commit (Downgrade auf eine bestimmte Version). @return array{ref:string,zip_url:string,zip_accept:string,sha256_url:string} */
    public function forRef(string $ref): array
    {
        $this->needRepo();
        if (!preg_match('~^[A-Za-z0-9._-]{1,80}$~', $ref)) {
            throw new UpdateException('Ungültige Versionsangabe.');
        }
        foreach ($this->releases() as $r) {
            if ($r['ref'] === $ref || $r['version'] === ltrim($ref, 'vV')) {
                return ['ref' => $r['ref'], 'zip_url' => $r['zip_url'], 'zip_accept' => $r['zip_accept'], 'sha256_url' => $r['sha256_url']];
            }
        }
        return ['ref' => $ref, 'zip_url' => self::API . '/repos/' . $this->settings->get('repo') . '/zipball/' . rawurlencode($ref), 'zip_accept' => 'application/vnd.github+json', 'sha256_url' => ''];
    }

    /** Archiv in eine Datei laden; prüft optional die SHA-256-Summe. @param array{zip_url:string,zip_accept:string,sha256_url:string} $src */
    public function download(array $src, string $file): void
    {
        $r = Http::request('GET', $src['zip_url'], $this->headers($src['zip_accept']), null, ['hosts' => ['api.github.com'], 'save_to' => $file, 'max_bytes' => self::MAX_ZIP_BYTES, 'timeout' => 180, 'max_redirects' => 5]);
        if (!$r->ok()) {
            @unlink($file);
            throw new UpdateException($this->message($r->status, $r->error));
        }
        if (($src['sha256_url'] ?? '') !== '') {
            $s = Http::request('GET', $src['sha256_url'], $this->headers('application/octet-stream'), null, ['hosts' => ['api.github.com'], 'timeout' => 20, 'max_bytes' => 4096]);
            if ($s->ok() && preg_match('/\b([0-9a-f]{64})\b/i', $s->body, $m) && !hash_equals(strtolower($m[1]), (string)hash_file('sha256', $file))) {
                @unlink($file);
                throw new UpdateException('Die Prüfsumme des heruntergeladenen Pakets stimmt nicht – Update abgebrochen.');
            }
        }
    }

    private function needRepo(): void
    {
        if ((string)$this->settings->get('repo') === '') {
            throw new UpdateException('Es ist noch kein GitHub-Repository für Updates eingetragen („besitzer/name“).');
        }
    }

    /** @return array<mixed> */
    private function get(string $path): array
    {
        $r = Http::request('GET', self::API . $path, $this->headers('application/vnd.github+json'), null, ['hosts' => ['api.github.com'], 'timeout' => 20, 'max_bytes' => 3_000_000]);
        if (!$r->ok()) {
            throw new UpdateException($this->message($r->status, $r->error));
        }
        $j = $r->json();
        if ($j === null) {
            throw new UpdateException('GitHub lieferte eine unlesbare Antwort.');
        }
        return $j;
    }

    /** @return list<string> */
    private function headers(string $accept): array
    {
        $t = (string)$this->settings->get('token');
        return array_values(array_filter(['Accept: ' . $accept, 'X-GitHub-Api-Version: 2022-11-28', $t !== '' ? 'Authorization: Bearer ' . $t : '']));
    }

    private function message(int $status, string $err): string
    {
        return match (true) {
            $status === 0 => 'GitHub ist nicht erreichbar' . ($err !== '' ? ' (' . mb_substr($err, 0, 80) . ')' : '') . '.',
            $status === 401 => 'GitHub: Zugang abgelehnt – Token prüfen.',
            $status === 403 => 'GitHub: Zugriff verweigert oder Abruf-Limit erreicht (ein Token erhöht das Limit).',
            $status === 404 => 'GitHub: Repository, Version oder Branch nicht gefunden (bei privaten Repositories ist ein Token nötig).',
            default => 'GitHub antwortete mit HTTP ' . $status . '.',
        };
    }
}

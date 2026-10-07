<?php
declare(strict_types=1);
// cms/src/Components/LayoutStore.php – Layouts speichern: Entwurf, Veröffentlichen, Verwerfen, Revisionen, Rollback, zeitgesteuertes Veröffentlichen.
// Ein Layout hat einen „Bereich“ (scope): home, site:<name> (nur Administratoren) oder page:<slug> / post:<id> (auch Autoren; geschützte Komponenten bleiben dabei unverändert).
// Datei je Bereich unter <Datenordner>/layouts/, atomar geschrieben und gesperrt; höchstens 25 Revisionen, 1 MB je Layout.

namespace Elvado\Components;

use Elvado\Wp\Actor;
use Elvado\Wp\PermissionException;

final class LayoutStore
{
    public const MAX_REVISIONS = 25;
    public const MAX_BYTES = 1_048_576;

    public function __construct(private readonly string $dir, private readonly Registry $registry)
    {
    }

    public static function validScope(string $s): bool { return preg_match('/^(home|site:[a-z0-9_-]{1,40}|page:[a-z0-9_-]{1,80}|post:[0-9]{1,12})$/', $s) === 1; }

    private function file(string $scope): string
    {
        if (!self::validScope($scope)) {
            throw new \InvalidArgumentException('Ungültiger Layout-Bereich.');
        }
        return rtrim($this->dir, '/') . '/' . str_replace(':', '__', $scope) . '.json';
    }

    private function may(string $scope, Actor $a): bool
    {
        return str_starts_with($scope, 'page:') || str_starts_with($scope, 'post:') ? $a->can('content_write') : $a->can('content_any');
    }

    /** @return array{scope:string,published:?array<string,mixed>,draft:?array<string,mixed>,revisions:list<array<string,mixed>>,n:int} */
    private function read(string $scope): array
    {
        $f = $this->file($scope);
        $d = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
        $d = is_array($d) ? $d : [];
        return ['scope' => $scope, 'published' => is_array($d['published'] ?? null) ? $d['published'] : null, 'draft' => is_array($d['draft'] ?? null) ? $d['draft'] : null,
            'revisions' => array_values(array_filter((array)($d['revisions'] ?? []), 'is_array')), 'n' => (int)($d['n'] ?? 0)];
    }

    /** Lesen–ändern–schreiben unter Sperre. @param callable(array):array $fn */
    private function mutate(string $scope, callable $fn): array
    {
        $f = $this->file($scope);
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0750, true) && !is_dir($this->dir)) {
            throw new \RuntimeException('Der Layout-Ordner kann nicht angelegt werden.');
        }
        if (!is_file($this->dir . '/.htaccess')) {
            @file_put_contents($this->dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
        }
        $lock = fopen($f . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) {
            throw new \RuntimeException('Das Layout wird gerade von jemand anderem gespeichert.');
        }
        try {
            $new = $fn($this->read($scope));
            $json = json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            if ($json === false || strlen($json) > self::MAX_BYTES * 2) {
                throw new \RuntimeException('Das Layout ist zu groß.');
            }
            $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (@file_put_contents($tmp, $json . "\n") === false || !@rename($tmp, $f)) {
                @unlink($tmp);
                throw new \RuntimeException('Das Layout konnte nicht gespeichert werden.');
            }
            return $new;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Zustand eines Bereichs; ein fälliger Termin wird dabei veröffentlicht. @return array{scope:string,published:?array<string,mixed>,draft:?array<string,mixed>,revisions:list<array<string,mixed>>,n:int} */
    public function get(string $scope, ?int $now = null): array
    {
        $s = $this->read($scope);
        $now ??= time();
        if ($s['draft'] !== null && ($s['draft']['publish_at'] ?? '') !== '' && strtotime((string)$s['draft']['publish_at'] . ':00') <= $now) {
            $s = $this->mutate($scope, fn(array $c) => $this->doPublish($c, 'Termin', 'zeitgesteuert'));
        }
        return $s;
    }

    /** Layout, das die Website ausliefert (veröffentlicht), sonst null. @return list<array<string,mixed>>|null */
    public function published(string $scope, ?int $now = null): ?array
    {
        $p = $this->get($scope, $now)['published'];
        return $p === null ? null : (array)$p['layout'];
    }

    /**
     * Entwurf speichern. @param mixed $raw @param string $publishAt 'Y-m-d H:i' (leer = nicht planen) @param bool $alreadyClean Layout ist schon bereinigt (nur für vertrauenswürdige Aufrufer wie das Theme)
     * @return array{layout:list<array<string,mixed>>,issues:list<array<string,string>>}
     */
    public function saveDraft(string $scope, mixed $raw, Actor $a, string $publishAt = '', bool $alreadyClean = false): array
    {
        if (!$this->may($scope, $a)) {
            throw new PermissionException('Dafür fehlt die Berechtigung.');
        }
        $at = '';
        if ($publishAt !== '') {
            $dt = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', str_replace('T', ' ', substr($publishAt, 0, 16)));
            if (!$dt) {
                throw new \InvalidArgumentException('Ungültiger Veröffentlichungstermin (Format JJJJ-MM-TT HH:MM).');
            }
            $at = $dt->format('Y-m-d H:i');
        }
        $result = [];
        $this->mutate($scope, function (array $cur) use ($raw, $a, $at, $alreadyClean, &$result): array {
            $layout = new Layout($this->registry);
            $base = $cur['draft']['layout'] ?? $cur['published']['layout'] ?? [];
            // $alreadyClean: der Aufrufer (z. B. ein Theme mit eigener Bereinigung) hat das Layout schon bereinigt – nicht erneut anfassen
            $clean = $alreadyClean && is_array($raw) ? array_values($raw) : $layout->clean($raw, ['admin' => $a->isAdmin(), 'unfiltered' => $a->isAdmin()]);
            if (!$a->isAdmin()) {
                $clean = $this->restoreProtected((array)$base, $clean);
            }
            if (strlen((string)json_encode($clean)) > self::MAX_BYTES) {
                throw new \RuntimeException('Das Layout ist zu groß.');
            }
            $result = ['layout' => $clean, 'issues' => $layout->issues];
            $cur['draft'] = ['layout' => $clean, 'at' => date('c'), 'by' => $a->login, 'publish_at' => $at];
            return $cur;
        });
        return $result;
    }

    public function discard(string $scope, Actor $a): void
    {
        if (!$this->may($scope, $a)) {
            throw new PermissionException('Dafür fehlt die Berechtigung.');
        }
        $this->mutate($scope, function (array $cur): array {
            $cur['draft'] = null;
            return $cur;
        });
    }

    /** Entwurf veröffentlichen. @return array<string,mixed> der neue veröffentlichte Stand */
    public function publish(string $scope, Actor $a, string $label = ''): array
    {
        if (!$this->may($scope, $a)) {
            throw new PermissionException('Dafür fehlt die Berechtigung.');
        }
        $s = $this->mutate($scope, function (array $cur) use ($a, $label): array {
            if ($cur['draft'] === null) {
                throw new \RuntimeException('Es gibt keinen Entwurf zum Veröffentlichen.');
            }
            return $this->doPublish($cur, $a->login, $label);
        });
        return (array)$s['published'];
    }

    /** @param array<string,mixed> $cur @return array<string,mixed> */
    private function doPublish(array $cur, string $by, string $label): array
    {
        if ($cur['draft'] === null) {
            return $cur;
        }
        if ($cur['published'] !== null) {
            $cur['revisions'][] = ['n' => $cur['published']['n'], 'layout' => $cur['published']['layout'], 'at' => $cur['published']['at'], 'by' => $cur['published']['by'], 'label' => (string)($cur['published']['label'] ?? '')];
            $cur['revisions'] = array_slice($cur['revisions'], -self::MAX_REVISIONS);
        }
        $cur['n']++;
        $cur['published'] = ['n' => $cur['n'], 'layout' => $cur['draft']['layout'], 'at' => date('c'), 'by' => $by, 'label' => mb_substr($label, 0, 80)];
        $cur['draft'] = null;
        return $cur;
    }

    /** Frühere Fassung wieder veröffentlichen (als neue Fassung; nichts geht verloren). @return array<string,mixed> */
    public function rollback(string $scope, int $n, Actor $a): array
    {
        if (!$this->may($scope, $a)) {
            throw new PermissionException('Dafür fehlt die Berechtigung.');
        }
        $s = $this->mutate($scope, function (array $cur) use ($n, $a): array {
            $rev = null;
            foreach ($cur['revisions'] as $r) {
                if ((int)$r['n'] === $n) {
                    $rev = $r;
                }
            }
            if ($rev === null) {
                throw new \RuntimeException('Diese Fassung gibt es nicht (mehr).');
            }
            $cur['draft'] = ['layout' => $rev['layout'], 'at' => date('c'), 'by' => $a->login, 'publish_at' => ''];
            return $this->doPublish($cur, $a->login, 'Zurück auf Fassung ' . $n);
        });
        return (array)$s['published'];
    }

    /** Veröffentlichung zurücknehmen (die Website fällt auf ihre Vorgabe zurück); die bisherige Fassung bleibt als Revision erhalten. */
    public function unpublish(string $scope, Actor $a): void
    {
        if (!$this->may($scope, $a)) {
            throw new PermissionException('Dafür fehlt die Berechtigung.');
        }
        $this->mutate($scope, function (array $cur): array {
            if ($cur['published'] !== null) {
                $cur['revisions'][] = ['n' => $cur['published']['n'], 'layout' => $cur['published']['layout'], 'at' => $cur['published']['at'], 'by' => $cur['published']['by'], 'label' => (string)($cur['published']['label'] ?? '')];
                $cur['revisions'] = array_slice($cur['revisions'], -self::MAX_REVISIONS);
                $cur['published'] = null;
            }
            $cur['draft'] = null;
            return $cur;
        });
    }

    /** @return list<array{n:int,at:string,by:string,label:string,count:int}> neueste zuerst */
    public function revisions(string $scope): array
    {
        $s = $this->read($scope);
        $out = [];
        if ($s['published'] !== null) {
            $out[] = ['n' => (int)$s['published']['n'], 'at' => (string)$s['published']['at'], 'by' => (string)$s['published']['by'], 'label' => (string)($s['published']['label'] ?? 'Aktuell'), 'count' => count((array)$s['published']['layout'])];
        }
        foreach (array_reverse($s['revisions']) as $r) {
            $out[] = ['n' => (int)$r['n'], 'at' => (string)$r['at'], 'by' => (string)$r['by'], 'label' => (string)($r['label'] ?? ''), 'count' => count((array)$r['layout'])];
        }
        return $out;
    }

    /**
     * Geschützte Instanzen (fest verankert oder nur für Administratoren) behalten ihren bisherigen Stand und Platz, auch wenn jemand ohne Recht sie ändern oder entfernen will.
     * @param list<array<string,mixed>> $old @param list<array<string,mixed>> $new @return list<array<string,mixed>>
     */
    private function restoreProtected(array $old, array $new): array
    {
        $prot = [];
        foreach ($old as $i => $inst) {
            $c = $this->registry->get((string)($inst['type'] ?? ''));
            if (!empty($inst['locked']) || ($c && ($c->rules['use'] === 'admin' || !empty($c->rules['locked'])))) {
                $prot[$i] = $inst;
            }
        }
        if ($prot === []) {
            return $new;
        }
        $ids = array_column($prot, 'id');
        $rest = array_values(array_filter($new, static fn($s) => !in_array($s['id'] ?? '', $ids, true)));
        foreach ($prot as $i => $inst) {
            array_splice($rest, min($i, count($rest)), 0, [$inst]);
        }
        return $rest;
    }
}

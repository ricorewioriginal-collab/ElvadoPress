<?php
declare(strict_types=1);
// cms/src/Wp/Migration/RunStore.php – Protokolle der echten Migration (ein Lauf = eine Datei in cms/data/.wp-engine/migration/): Schritte, Ergebnisse und alle angelegten Objekte (für den Rückbau).

namespace Elvado\Wp\Migration;

final class RunStore
{
    public function __construct(private readonly string $stateDir)
    {
    }

    private function dir(): string { return rtrim($this->stateDir, '/') . '/migration'; }

    public static function newId(): string { return date('Ymd-His') . '-' . bin2hex(random_bytes(3)); }

    /** @param array<string,mixed> $run */
    public function save(array $run): void
    {
        $id = (string)($run['id'] ?? '');
        if (preg_match('/^[0-9]{8}-[0-9]{6}-[0-9a-f]{6}$/', $id) !== 1) {
            throw new \InvalidArgumentException('Ungültige Lauf-Kennung.');
        }
        if (!is_dir($this->dir()) && !@mkdir($this->dir(), 0700, true) && !is_dir($this->dir())) {
            throw new \RuntimeException('Der Protokollordner lässt sich nicht anlegen.');
        }
        $f = $this->dir() . '/run-' . $id . '.json';
        if (@file_put_contents($f, json_encode($run, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
            throw new \RuntimeException('Das Protokoll lässt sich nicht speichern.');
        }
        @chmod($f, 0600);
    }

    /** @return array<string,mixed>|null */
    public function load(string $id): ?array
    {
        if (preg_match('/^[0-9]{8}-[0-9]{6}-[0-9a-f]{6}$/', $id) !== 1) {
            return null;
        }
        $r = json_decode((string)@file_get_contents($this->dir() . '/run-' . $id . '.json'), true);
        return is_array($r) ? $r : null;
    }

    /** @return list<string> neueste zuerst */
    public function ids(): array
    {
        $o = [];
        foreach (glob($this->dir() . '/run-*.json') ?: [] as $f) {
            $o[] = substr(basename($f), 4, -5);
        }
        rsort($o);
        return $o;
    }
}

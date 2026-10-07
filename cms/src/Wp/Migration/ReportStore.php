<?php
declare(strict_types=1);
// cms/src/Wp/Migration/ReportStore.php – Speichert Trockenlauf-Berichte im geschützten Zustandsordner der Engine (cms/data/.wp-engine/migration/). Das ist die einzige Stelle, an die der Trockenlauf schreibt.

namespace Elvado\Wp\Migration;

final class ReportStore
{
    private const KEEP = 10;

    public function __construct(private readonly string $stateDir)
    {
    }

    private function dir(): string { return rtrim($this->stateDir, '/') . '/migration'; }

    /** @param array<string,mixed> $report @return string Dateiname */
    public function save(array $report): string
    {
        $d = $this->dir();
        if (!is_dir($d) && !@mkdir($d, 0700, true) && !is_dir($d)) {
            throw new \RuntimeException('Der Berichtsordner lässt sich nicht anlegen.');
        }
        $name = 'report-' . date('Ymd-His') . '-' . bin2hex(random_bytes(2)) . '.json';
        if (@file_put_contents($d . '/' . $name, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
            throw new \RuntimeException('Der Bericht lässt sich nicht speichern.');
        }
        @chmod($d . '/' . $name, 0600);
        $all = $this->names();
        foreach (array_slice($all, self::KEEP) as $old) {
            @unlink($d . '/' . $old);
        }
        return $name;
    }

    /** @return list<string> neueste zuerst */
    public function names(): array
    {
        $n = array_map('basename', glob($this->dir() . '/report-*.json') ?: []);
        rsort($n);
        return $n;
    }

    /** @return array<string,mixed>|null */
    public function load(string $name = ''): ?array
    {
        $names = $this->names();
        if ($name === '') {
            $name = $names[0] ?? '';
        }
        if ($name === '' || !in_array($name, $names, true)) {
            return null;
        }
        $r = json_decode((string)@file_get_contents($this->dir() . '/' . $name), true);
        return is_array($r) ? $r + ['file' => $name] : null;
    }
}

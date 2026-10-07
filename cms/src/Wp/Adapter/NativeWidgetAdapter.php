<?php
declare(strict_types=1);
// cms/src/Wp/Adapter/NativeWidgetAdapter.php – Widget-Bereiche und Widgets der bisherigen ElvadoPress-Verwaltung (site.json). Nur lesend.

namespace Elvado\Wp\Adapter;

final class NativeWidgetAdapter implements WidgetAdapter
{
    public function __construct(private readonly string $dataDir)
    {
    }

    public function writable(): bool { return false; }

    /** @return array<string,mixed> */
    private function site(): array
    {
        $f = $this->dataDir . '/site.json';
        $s = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
        return is_array($s) ? $s : [];
    }

    public function areas(): array
    {
        $s = $this->site();
        $byId = [];
        foreach ((array)($s['widgets'] ?? []) as $w) {
            if (is_array($w)) {
                $byId[(string)($w['id'] ?? '')] = $w;
            }
        }
        $out = [];
        foreach ((array)($s['widget_areas'] ?? []) as $a) {
            if (!is_array($a)) {
                continue;
            }
            $ws = [];
            foreach ((array)($a['widgets'] ?? $a['items'] ?? []) as $ref) {
                $id = is_array($ref) ? (string)($ref['widget'] ?? $ref['id'] ?? '') : (string)$ref;
                $w = $byId[$id] ?? null;
                $ws[] = ['id' => $id, 'id_base' => (string)($w['builtin'] ?? $w['type'] ?? 'widget'), 'number' => 0, 'title' => (string)($w['title'] ?? $w['name'] ?? $id), 'summary' => (string)($w['name'] ?? ''), 'settings' => []];
            }
            $out[] = ['id' => (string)($a['id'] ?? ''), 'name' => (string)($a['name'] ?? $a['id'] ?? ''), 'description' => '', 'inactive' => false, 'widgets' => $ws];
        }
        return $out;
    }

    public function types(): array
    {
        $out = [];
        foreach ((array)($this->site()['widgets'] ?? []) as $w) {
            if (is_array($w) && ($w['builtin'] ?? '') !== '') {
                $out[(string)$w['builtin']] = ['id_base' => (string)$w['builtin'], 'name' => (string)($w['name'] ?? $w['builtin']), 'description' => ''];
            }
        }
        return array_values($out);
    }

    public function add(string $area, string $idBase, array $settings, bool $unfiltered): array { throw new \RuntimeException('Widgets werden in ElvadoPress über die Widget-Verwaltung geändert.'); }
    public function update(string $widgetId, array $settings, bool $unfiltered): array { throw new \RuntimeException('Widgets werden in ElvadoPress über die Widget-Verwaltung geändert.'); }
    public function move(array $layout): void { throw new \RuntimeException('Widgets werden in ElvadoPress über die Widget-Verwaltung geändert.'); }
    public function delete(string $widgetId): bool { throw new \RuntimeException('Widgets werden in ElvadoPress über die Widget-Verwaltung geändert.'); }
}

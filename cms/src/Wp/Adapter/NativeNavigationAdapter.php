<?php
declare(strict_types=1);
// cms/src/Wp/Adapter/NativeNavigationAdapter.php – Menüs der bisherigen ElvadoPress-Verwaltung (site.json → menus.top / menus.bottom). Nur lesend (Quelle für die Migration).

namespace Elvado\Wp\Adapter;

final class NativeNavigationAdapter implements NavigationAdapter
{
    private const NAMES = ['top' => 'Top Navigation (Desktop)', 'bottom' => 'Bottom Navigation (Mobil)'];

    public function __construct(private readonly string $dataDir)
    {
    }

    public function writable(): bool { return false; }

    /** @return array<string,list<array<string,mixed>>> */
    private function raw(): array
    {
        $f = $this->dataDir . '/site.json';
        $s = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
        $m = is_array($s) && is_array($s['menus'] ?? null) ? $s['menus'] : [];
        return ['top' => array_values(array_filter((array)($m['top'] ?? []), 'is_array')), 'bottom' => array_values(array_filter((array)($m['bottom'] ?? []), 'is_array'))];
    }

    public function menus(): array
    {
        $out = [];
        foreach ($this->raw() as $k => $items) {
            $out[] = ['id' => $k, 'name' => self::NAMES[$k], 'slug' => $k, 'count' => count($items), 'locations' => []];
        }
        return $out;
    }

    public function tree(string $menuId): ?array
    {
        $raw = $this->raw();
        if (!isset($raw[$menuId])) {
            return null;
        }
        $by = [];
        foreach ($raw[$menuId] as $m) {
            if (array_key_exists('enabled', $m) && empty($m['enabled'])) {
                continue;
            }
            $by[(string)($m['id'] ?? '')] = ['id' => (string)($m['id'] ?? ''), 'type' => 'custom', 'object_id' => '', 'label' => (string)($m['label'] ?? ''), 'url' => (string)($m['target'] ?? ''), 'target' => '', 'rel' => '', 'classes' => '', 'children' => [], '_p' => (string)($m['parent_id'] ?? '')];
        }
        $root = [];
        foreach ($by as $id => &$i) {
            $p = $i['_p'];
            unset($i['_p']);
            if ($p !== '' && isset($by[$p]) && $p !== $id) {
                $by[$p]['children'][] = &$i;
            } else {
                $root[] = &$i;
            }
        }
        unset($i);
        return ['id' => $menuId, 'name' => self::NAMES[$menuId], 'items' => json_decode((string)json_encode($root), true)];
    }

    public function create(string $name): array { throw new \RuntimeException('Menüs werden in ElvadoPress über die Menü-Verwaltung geändert.'); }
    public function rename(string $menuId, string $name): void { throw new \RuntimeException('Menüs werden in ElvadoPress über die Menü-Verwaltung geändert.'); }
    public function delete(string $menuId): bool { throw new \RuntimeException('Menüs werden in ElvadoPress über die Menü-Verwaltung geändert.'); }
    public function saveTree(string $menuId, array $items): array { throw new \RuntimeException('Menüs werden in ElvadoPress über die Menü-Verwaltung geändert.'); }
    public function locations(): array { return [['id' => 'top', 'label' => 'Top Navigation (Desktop)', 'menu' => 'top'], ['id' => 'bottom', 'label' => 'Bottom Navigation (Mobil)', 'menu' => 'bottom']]; }
    public function assign(string $location, string $menuId): void { throw new \RuntimeException('Orte lassen sich nur mit aktiver WordPress-Engine zuweisen.'); }
}

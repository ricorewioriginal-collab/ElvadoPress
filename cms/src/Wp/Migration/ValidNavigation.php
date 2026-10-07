<?php
declare(strict_types=1);
// cms/src/Wp/Migration/ValidNavigation.php – Lesende Sicht auf ElvadoPress-Menüs ohne Einträge mit ungültigem Ziel (samt deren Unterpunkten), damit ein einzelner kaputter Eintrag die Menü-Übernahme nicht abbricht. Die Zahl der ausgelassenen Einträge steht in $dropped.

namespace Elvado\Wp\Migration;

use Elvado\Wp\Adapter\NavigationAdapter;

final class ValidNavigation implements NavigationAdapter
{
    /** @var list<string> */
    public array $dropped = [];

    public function __construct(private readonly NavigationAdapter $inner)
    {
    }

    public function writable(): bool { return false; }
    public function menus(): array { return $this->inner->menus(); }

    public function tree(string $menuId): ?array
    {
        $t = $this->inner->tree($menuId);
        if ($t === null) {
            return null;
        }
        $t['items'] = $this->clean($t['items'], $menuId);
        return $t;
    }

    /** @param list<array<string,mixed>> $items @return list<array<string,mixed>> */
    private function clean(array $items, string $menu): array
    {
        $out = [];
        foreach ($items as $i) {
            $url = (string)($i['url'] ?? '');
            if (trim((string)($i['label'] ?? '')) === '' || preg_match('#^(https?://|/|\#|mailto:|tel:)#i', $url) !== 1) {
                $this->dropped[] = $menu . ': ' . mb_substr((string)($i['label'] ?? ''), 0, 60);
                continue;
            }
            $i['children'] = $this->clean((array)($i['children'] ?? []), $menu);
            $out[] = $i;
        }
        return $out;
    }

    public function create(string $name): array { throw new \RuntimeException('Nur lesend.'); }
    public function rename(string $menuId, string $name): void { throw new \RuntimeException('Nur lesend.'); }
    public function delete(string $menuId): bool { throw new \RuntimeException('Nur lesend.'); }
    public function saveTree(string $menuId, array $items): array { throw new \RuntimeException('Nur lesend.'); }
    public function locations(): array { return []; }
    public function assign(string $location, string $menuId): void { throw new \RuntimeException('Nur lesend.'); }
}

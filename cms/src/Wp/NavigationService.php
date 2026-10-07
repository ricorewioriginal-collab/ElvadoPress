<?php
declare(strict_types=1);
// cms/src/Wp/NavigationService.php – Dienstschicht für Menüs: prüft und bereinigt den Menübaum serverseitig, wendet Rechte an (Ändern nur Administratoren) und ruft den Adapter auf.

namespace Elvado\Wp;

use Elvado\Components\Sanitizer;
use Elvado\Wp\Adapter\NavigationAdapter;

final class NavigationService
{
    public const MAX_ITEMS = 200;
    public const MAX_DEPTH = 4;
    private const TYPES = ['custom', 'page', 'post', 'category', 'tag'];

    public function __construct(private readonly NavigationAdapter $adapter, private readonly ?Actor $actor = null)
    {
    }

    public function adapter(): NavigationAdapter { return $this->adapter; }

    private function write(): void
    {
        if (!$this->adapter->writable()) {
            throw new \RuntimeException('Die Menüs sind schreibgeschützt (WordPress-Engine nicht aktiv).');
        }
        if (!($this->actor ?? new Actor('', 'admin'))->can('content_any')) {
            throw new PermissionException('Menüs ändern nur Administratoren.');
        }
    }

    private function id(string $v): string { return preg_match('/^[A-Za-z0-9_-]{1,40}$/', $v) === 1 ? $v : ''; }

    public function menus(): array { return $this->adapter->menus(); }

    public function tree(string $menuId): ?array
    {
        return $this->id($menuId) === '' ? null : $this->adapter->tree($menuId);
    }

    public function create(string $name): array
    {
        $this->write();
        return $this->adapter->create($this->name($name));
    }

    public function rename(string $menuId, string $name): void
    {
        $this->write();
        $this->adapter->rename($this->idOrFail($menuId), $this->name($name));
    }

    public function delete(string $menuId): bool
    {
        $this->write();
        return $this->id($menuId) !== '' && $this->adapter->delete($menuId);
    }

    /** @param mixed $raw Baum (unbekannte Eingabe) @return list<array<string,mixed>> gespeicherter Baum */
    public function save(string $menuId, mixed $raw): array
    {
        $this->write();
        $count = 0;
        $clean = $this->items(is_array($raw) ? array_values($raw) : [], 1, $count);
        return $this->adapter->saveTree($this->idOrFail($menuId), $clean);
    }

    public function locations(): array { return $this->adapter->locations(); }

    public function assign(string $location, string $menuId): void
    {
        $this->write();
        if (preg_match('/^[A-Za-z0-9_-]{1,60}$/', $location) !== 1) {
            throw new \InvalidArgumentException('Ungültiger Ort.');
        }
        $this->adapter->assign($location, $menuId === '' ? '' : $this->idOrFail($menuId));
    }

    /**
     * Ein Menü der bisherigen ElvadoPress-Verwaltung als neues Menü übernehmen (alle Einträge als eigene Links).
     * @return array{menu:array<string,mixed>,items:int}
     */
    public function importFrom(NavigationAdapter $source, string $sourceMenu, string $name = ''): array
    {
        $this->write();
        $t = $source->tree($sourceMenu);
        if ($t === null) {
            throw new \RuntimeException('Das Quell-Menü gibt es nicht.');
        }
        $count = 0;
        $items = $this->items($t['items'], 1, $count, true);
        $menu = $this->adapter->create($this->name($name !== '' ? $name : $t['name']));
        $this->adapter->saveTree($menu['id'], $items);
        return ['menu' => $menu, 'items' => $count];
    }

    private function idOrFail(string $id): string
    {
        $v = $this->id($id);
        if ($v === '') {
            throw new \InvalidArgumentException('Ungültige Menü-Kennung.');
        }
        return $v;
    }

    private function name(string $n): string
    {
        $n = Sanitizer::line($n);
        if ($n === '' || mb_strlen($n) > 80) {
            throw new \InvalidArgumentException('Der Menüname braucht 1 bis 80 Zeichen.');
        }
        return $n;
    }

    /** @param list<mixed> $list @return list<array<string,mixed>> */
    private function items(array $list, int $depth, int &$count, bool $forceCustom = false): array
    {
        if ($list !== [] && $depth > self::MAX_DEPTH) {
            throw new \InvalidArgumentException('Menüs dürfen höchstens ' . self::MAX_DEPTH . ' Ebenen tief sein.');
        }
        $out = [];
        foreach ($list as $it) {
            if (!is_array($it)) {
                continue;
            }
            if (++$count > self::MAX_ITEMS) {
                throw new \InvalidArgumentException('Ein Menü darf höchstens ' . self::MAX_ITEMS . ' Einträge haben.');
            }
            $type = $forceCustom ? 'custom' : (string)($it['type'] ?? 'custom');
            if (!in_array($type, self::TYPES, true)) {
                throw new \InvalidArgumentException('Unbekannte Eintragsart „' . mb_substr($type, 0, 20) . '“.');
            }
            $label = mb_substr(Sanitizer::line((string)($it['label'] ?? '')), 0, 200);
            $url = '';
            $oid = '';
            if ($type === 'custom') {
                $url = Sanitizer::url((string)($it['url'] ?? ''), false);
                if ($label === '' || $url === '') {
                    throw new \InvalidArgumentException('Ein eigener Link braucht Text und eine gültige Adresse (https://…, /pfad, #anker, mailto:, tel:).');
                }
            } else {
                $oid = (string)($it['object_id'] ?? '');
                if (preg_match('/^\d{1,12}$/', $oid) !== 1) {
                    throw new \InvalidArgumentException('Ein verlinkter Inhalt braucht eine gültige Kennung.');
                }
            }
            $out[] = [
                'id' => preg_match('/^\d{1,12}$/', (string)($it['id'] ?? '')) === 1 ? (string)$it['id'] : '', 'type' => $type, 'object_id' => $oid, 'label' => $label, 'url' => $url,
                'target' => ($it['target'] ?? '') === '_blank' ? '_blank' : '',
                'rel' => trim((string)preg_replace('/[^a-z ]+/', '', strtolower(mb_substr(strip_tags((string)($it['rel'] ?? '')), 0, 60)))),
                'classes' => trim((string)preg_replace('/[^A-Za-z0-9_ -]+/', '', mb_substr(strip_tags((string)($it['classes'] ?? '')), 0, 100))),
                'children' => $this->items(is_array($it['children'] ?? null) ? array_values($it['children']) : [], $depth + 1, $count, $forceCustom),
            ];
        }
        return $out;
    }
}

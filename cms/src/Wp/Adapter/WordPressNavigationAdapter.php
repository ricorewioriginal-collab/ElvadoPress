<?php
declare(strict_types=1);
// cms/src/Wp/Adapter/WordPressNavigationAdapter.php – Echte WordPress-Menüs (nav_menu, nav_menu_item). Setzt voraus, dass WordPress in dieser Anfrage gestartet wurde.

namespace Elvado\Wp\Adapter;

use Elvado\Wp\Bridge;

final class WordPressNavigationAdapter implements NavigationAdapter
{
    public function __construct()
    {
        if (!Bridge::booted()) {
            throw new \RuntimeException('WordPress ist in dieser Anfrage nicht gestartet.');
        }
        require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
        require_once ABSPATH . 'wp-admin/includes/taxonomy.php';
    }

    public function writable(): bool { return true; }

    /** @return array<int,string> Menü-ID ⇒ Orte */
    private function locationMap(): array
    {
        $map = [];
        foreach ((array)get_nav_menu_locations() as $loc => $id) {
            if ((int)$id > 0) {
                $map[(int)$id][] = (string)$loc;
            }
        }
        return $map;
    }

    public function menus(): array
    {
        $map = $this->locationMap();
        return array_map(static fn($m) => ['id' => (string)$m->term_id, 'name' => (string)$m->name, 'slug' => (string)$m->slug, 'count' => (int)$m->count, 'locations' => $map[(int)$m->term_id] ?? []], wp_get_nav_menus());
    }

    private function menu(string $id): ?\WP_Term
    {
        $m = ctype_digit($id) ? wp_get_nav_menu_object((int)$id) : false;
        return $m instanceof \WP_Term ? $m : null;
    }

    public function tree(string $menuId): ?array
    {
        $m = $this->menu($menuId);
        if (!$m) {
            return null;
        }
        $flat = wp_get_nav_menu_items($m->term_id, ['post_status' => 'any', 'update_post_term_cache' => false]) ?: [];
        $by = [];
        foreach ($flat as $i) {
            [$type, $oid] = self::typeOf($i);
            $by[(int)$i->ID] = ['id' => (string)$i->ID, 'type' => $type, 'object_id' => $oid, 'label' => (string)$i->title, 'url' => (string)$i->url, 'target' => (string)$i->target === '_blank' ? '_blank' : '', 'rel' => (string)$i->xfn,
                'classes' => implode(' ', array_filter((array)$i->classes)), 'children' => [], '_p' => (int)$i->menu_item_parent, '_o' => (int)$i->menu_order];
        }
        $root = [];
        foreach ($by as $id => &$it) {
            $p = $it['_p'];
            unset($it['_p']);
            if ($p > 0 && isset($by[$p])) {
                $by[$p]['children'][] = &$it;
            } else {
                $root[] = &$it;
            }
        }
        unset($it);
        $sort = static function (array &$list) use (&$sort): void {
            usort($list, static fn($a, $b) => $a['_o'] <=> $b['_o']);
            foreach ($list as &$x) {
                unset($x['_o']);
                $sort($x['children']);
            }
        };
        $sort($root);
        return ['id' => (string)$m->term_id, 'name' => (string)$m->name, 'items' => json_decode((string)json_encode($root), true)];
    }

    /** @return array{0:string,1:string} */
    private static function typeOf(object $i): array
    {
        if ($i->type === 'post_type') {
            return [$i->object === 'page' ? 'page' : 'post', (string)$i->object_id];
        }
        if ($i->type === 'taxonomy') {
            return [$i->object === 'post_tag' ? 'tag' : 'category', (string)$i->object_id];
        }
        return ['custom', ''];
    }

    public function create(string $name): array
    {
        $id = wp_create_nav_menu($name);
        if (is_wp_error($id)) {
            throw new \RuntimeException($id->get_error_message());
        }
        return ['id' => (string)$id, 'name' => $name, 'slug' => (string)wp_get_nav_menu_object($id)->slug, 'count' => 0, 'locations' => []];
    }

    public function rename(string $menuId, string $name): void
    {
        $m = $this->menu($menuId);
        if (!$m) {
            throw new \RuntimeException('Das Menü wurde nicht gefunden.');
        }
        $r = wp_update_nav_menu_object($m->term_id, ['menu-name' => $name]);
        if (is_wp_error($r)) {
            throw new \RuntimeException($r->get_error_message());
        }
    }

    public function delete(string $menuId): bool
    {
        $m = $this->menu($menuId);
        if (!$m) {
            return false;
        }
        $r = wp_delete_nav_menu($m->term_id);
        return $r === true;
    }

    public function saveTree(string $menuId, array $items): array
    {
        $m = $this->menu($menuId);
        if (!$m) {
            throw new \RuntimeException('Das Menü wurde nicht gefunden.');
        }
        $have = [];
        foreach (wp_get_nav_menu_items($m->term_id, ['post_status' => 'any']) ?: [] as $i) {
            $have[(int)$i->ID] = true;
        }
        $keep = [];
        $pos = 0;
        $walk = function (array $list, int $parent) use (&$walk, &$keep, &$pos, $have, $m): void {
            foreach ($list as $it) {
                $id = ctype_digit((string)($it['id'] ?? '')) && isset($have[(int)$it['id']]) ? (int)$it['id'] : 0;
                $type = (string)$it['type'];
                $args = [
                    'menu-item-title' => (string)$it['label'], 'menu-item-parent-id' => $parent, 'menu-item-position' => ++$pos, 'menu-item-status' => 'publish',
                    'menu-item-target' => (string)$it['target'], 'menu-item-xfn' => (string)$it['rel'], 'menu-item-classes' => (string)$it['classes'],
                ];
                if ($type === 'custom') {
                    $args += ['menu-item-type' => 'custom', 'menu-item-url' => (string)$it['url']];
                } elseif ($type === 'page' || $type === 'post') {
                    $p = ctype_digit((string)$it['object_id']) ? get_post((int)$it['object_id']) : null;
                    if (!$p instanceof \WP_Post || $p->post_type !== $type) {
                        throw new \RuntimeException('Die verlinkte ' . ($type === 'page' ? 'Seite' : 'der Beitrag') . ' gibt es nicht (#' . (string)$it['object_id'] . ').');
                    }
                    $args += ['menu-item-type' => 'post_type', 'menu-item-object' => $type, 'menu-item-object-id' => $p->ID];
                    if ($args['menu-item-title'] === '') {
                        $args['menu-item-title'] = (string)$p->post_title;
                    }
                } else {
                    $tax = $type === 'tag' ? 'post_tag' : 'category';
                    $t = ctype_digit((string)$it['object_id']) ? get_term((int)$it['object_id'], $tax) : null;
                    if (!$t instanceof \WP_Term) {
                        throw new \RuntimeException('Die verlinkte Kategorie bzw. das Schlagwort gibt es nicht (#' . (string)$it['object_id'] . ').');
                    }
                    $args += ['menu-item-type' => 'taxonomy', 'menu-item-object' => $tax, 'menu-item-object-id' => $t->term_id];
                    if ($args['menu-item-title'] === '') {
                        $args['menu-item-title'] = (string)$t->name;
                    }
                }
                $new = wp_update_nav_menu_item($m->term_id, $id, wp_slash($args));
                if (is_wp_error($new)) {
                    throw new \RuntimeException($new->get_error_message());
                }
                $keep[(int)$new] = true;
                $walk((array)($it['children'] ?? []), (int)$new);
            }
        };
        $walk($items, 0);
        foreach (array_keys($have) as $old) {   // nicht mehr vorhandene Einträge entfernen
            if (!isset($keep[$old])) {
                wp_delete_post($old, true);
            }
        }
        return $this->tree($menuId)['items'] ?? [];
    }

    public function locations(): array
    {
        $cur = get_nav_menu_locations();
        $out = [];
        foreach (get_registered_nav_menus() as $loc => $label) {
            $out[] = ['id' => (string)$loc, 'label' => (string)$label, 'menu' => ($cur[$loc] ?? 0) ? (string)$cur[$loc] : ''];
        }
        return $out;
    }

    public function assign(string $location, string $menuId): void
    {
        if (!array_key_exists($location, get_registered_nav_menus())) {
            throw new \RuntimeException('Diesen Ort kennt das aktive Theme nicht.');
        }
        if ($menuId !== '' && !$this->menu($menuId)) {
            throw new \RuntimeException('Das Menü wurde nicht gefunden.');
        }
        $locs = (array)get_nav_menu_locations();
        $locs[$location] = $menuId === '' ? 0 : (int)$menuId;
        set_theme_mod('nav_menu_locations', $locs);
    }
}

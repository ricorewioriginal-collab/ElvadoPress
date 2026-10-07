<?php
declare(strict_types=1);
// cms/src/Components/Registry.php – Zentrale Komponenten-Registry von ElvadoPress.
// Der Kern registriert nur neutrale Komponenten (CoreComponents); Produkt-/Projektspezifisches kommt über Erweiterungen (Hook „components_register“ bzw. direkt Registry::register()).
// Die Registry kennt Schema, Vorgaben, Rechte, Regeln (locked/sortable/droppable/repeatable), responsive Felder, Sichtbarkeit und – wo vorhanden – eine native Darstellung.

namespace Elvado\Components;

final class Registry
{
    /** @var array<string,Component> */
    private array $items = [];

    /** @param array<string,mixed> $def @param (callable(array,array,Registry):string)|null $render */
    public function register(array $def, string $source = 'core', ?callable $render = null): Component
    {
        $c = Component::from($def, $source, $render);
        if (isset($this->items[$c->id]) && $this->items[$c->id]->source !== $source) {
            throw new \InvalidArgumentException('Die Komponente „' . $c->id . '“ gehört schon zu „' . $this->items[$c->id]->source . '“.');
        }
        $this->items[$c->id] = $c;
        return $c;
    }

    public function unregister(string $id): void { unset($this->items[$id]); }
    public function has(string $id): bool { return isset($this->items[$id]); }
    public function get(string $id): ?Component { return $this->items[$id] ?? null; }

    /** @return array<string,Component> */
    public function all(): array { return $this->items; }

    /** @return array<string,list<Component>> nach Kategorie, in der Reihenfolge von Component::CATEGORIES */
    public function byCategory(): array
    {
        $out = [];
        foreach (Component::CATEGORIES as $cat => $_) {
            foreach ($this->items as $c) {
                if ($c->category === $cat) {
                    $out[$cat][] = $c;
                }
            }
        }
        return $out;
    }

    /**
     * Katalog für die Oberfläche. Komponenten, die mehr Rechte oder ein nicht aktives Merkmal („feature“) verlangen, fehlen.
     * @param array{admin?:bool,features?:list<string>} $who
     * @return array{categories:array<string,string>,groups:array<string,string>,components:list<array<string,mixed>>}
     */
    public function catalog(array $who = []): array
    {
        $admin = !empty($who['admin']);
        $features = (array)($who['features'] ?? []);
        $list = [];
        foreach ($this->byCategory() as $comps) {
            foreach ($comps as $c) {
                if (($c->rules['use'] === 'admin' && !$admin) || ($c->feature !== '' && !in_array($c->feature, $features, true))) {
                    continue;
                }
                $list[] = $c->toArray();
            }
        }
        return ['categories' => Component::CATEGORIES, 'groups' => Component::GROUPS, 'components' => $list];
    }

    /** Schema im älteren Baukasten-Format (k,label,type,options,default,min,max,max) für das Theme „ElvadoPress Baukasten“. @param list<string> $ids @return array<string,array<string,mixed>> */
    public function legacySchema(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $c = $this->get($id);
            if (!$c) {
                continue;
            }
            $fields = [];
            foreach ($c->fields as $f) {
                $o = ['k' => $f['k'], 'label' => $f['label'], 'type' => (string)($f['legacy_type'] ?? $f['type'])];
                foreach (['options', 'default', 'min', 'max', 'item'] as $key) {
                    if (array_key_exists($key, $f)) {
                        $o[$key] = $f[$key];
                    }
                }
                if (isset($f['max_items'])) {
                    $o['max'] = $f['max_items'];
                }
                $fields[] = $o;
            }
            $out[$id] = ['label' => $c->name, 'icon' => 'fa-' . $c->icon, 'fields' => $fields];
        }
        return $out;
    }
}

<?php
declare(strict_types=1);
// cms/src/Components/Component.php – Definition einer Komponente (unveränderlich): Kennung, Name, Kategorie, Schema, Vorgaben, Rechte, Regeln, Rendering-Art.

namespace Elvado\Components;

final class Component
{
    public const CATEGORIES = [
        'structure' => 'Struktur', 'content' => 'Inhalt', 'media' => 'Medien', 'data' => 'Beiträge & Daten', 'navigation' => 'Navigation',
        'widgets' => 'Widgets & WordPress', 'radio' => 'Radio', 'advanced' => 'Erweitert', 'extension' => 'Erweiterungen',
    ];
    public const GROUPS = ['content' => 'Inhalt', 'design' => 'Design', 'behavior' => 'Verhalten'];
    /** Rendering: native = PHP-Funktion der Registry (überall), theme = vom aktiven Theme gerendert (Baukasten), wp = nur mit WordPress-Laufzeit, runtime = Modul von ElvadoPress (z. B. Formulare), bound = bestehender Bereich der Website (CSS-Selektor in „bind“): die Website rendert weiter selbst, ElvadoPress steuert nur Gestaltung und Sichtbarkeit. */
    public const RENDERERS = ['native', 'theme', 'wp', 'runtime', 'bound'];
    /** Zulässige Bereichs-Selektoren: Kennung, Klasse oder ein Gliederungs-Element (auch für „*“-Komponenten pro Instanz). */
    public const BIND_RE = '/^([#.][a-z][a-z0-9_-]{0,60}|header|footer|nav|main|aside|section|article)$/i';
    /** „bind“ = „*“: der Selektor steht pro Instanz im Feld „selector“ (vom Live Builder erkannte Bereiche). */
    public const BIND_ANY = '*';

    /** Pfad-Selektor für Inhalts-Änderungen am echten Seiten-HTML: Start „#kennung“ oder „body“, danach bis zu 8 Schritte „ > tag:nth-of-type(n)“ oder „ > .klasse“. Keine anderen Zeichen. */
    public const PATH_RE = '/^(#[a-z][a-z0-9_-]{0,60}|body)((?: > (?:[a-z][a-z0-9]{0,9}(?::nth-of-type\\([0-9]{1,3}\\))?|\\.[a-z][a-z0-9_-]{0,40})){0,8})$/i';
    public static function validPath(string $s): bool { return strlen($s) <= 400 && preg_match(self::PATH_RE, $s) === 1; }
    public static function validSelector(string $s): bool { return preg_match(self::BIND_RE, $s) === 1; }

    /** @var list<array<string,mixed>> */
    public readonly array $fields;
    /** @var array<string,mixed> */
    public readonly array $defaults;

    /**
     * @param array<string,mixed> $d Definition (siehe docs/COMPONENTS.md)
     * @param (callable(array,array,Registry):string)|null $render native Darstellung fn(array $props, array $instance, Registry $r): string
     */
    private function __construct(
        public readonly string $id, public readonly string $name, public readonly string $category, public readonly string $icon, public readonly string $description,
        public readonly string $renderer, array $fields, public readonly array $rules, public readonly array $data, public readonly array $preview,
        public readonly string $source, public readonly string $feature, private readonly mixed $render, public readonly string $bind = ''
    ) {
        $this->fields = $fields;
        $def = [];
        foreach ($fields as $f) {
            $def[$f['k']] = $f['default'] ?? ($f['type'] === 'checkbox' ? false : ($f['type'] === 'items' ? [] : ($f['type'] === 'number' ? 0 : '')));
        }
        $this->defaults = $def;
    }

    /** @param array<string,mixed> $d */
    public static function from(array $d, string $source = 'core', ?callable $render = null): self
    {
        $id = (string)($d['id'] ?? '');
        if (preg_match('/^[a-z][a-z0-9_]{1,40}$/', $id) !== 1) {
            throw new \InvalidArgumentException('Ungültige Komponenten-Kennung: ' . $id);
        }
        $cat = (string)($d['category'] ?? 'extension');
        if (!isset(self::CATEGORIES[$cat])) {
            throw new \InvalidArgumentException('Unbekannte Kategorie bei ' . $id);
        }
        $bind = (string)($d['bind'] ?? '');
        if ($bind !== '' && $bind !== self::BIND_ANY && !self::validSelector($bind)) {   // Kennung, Klasse oder ein Gliederungs-Element
            throw new \InvalidArgumentException('Ungültiger Bereichs-Selektor bei ' . $id);
        }
        $renderer = (string)($d['renderer'] ?? ($bind !== '' ? 'bound' : ($render ? 'native' : 'runtime')));
        if (($renderer === 'bound') !== ($bind !== '')) {
            throw new \InvalidArgumentException('„bound“ und „bind“ gehören zusammen bei ' . $id);
        }
        if (!in_array($renderer, self::RENDERERS, true)) {
            throw new \InvalidArgumentException('Unbekannte Rendering-Art bei ' . $id);
        }
        if ($renderer === 'native' && $render === null) {
            throw new \InvalidArgumentException('Komponente „' . $id . '“ ist „native“, hat aber keine Darstellung.');
        }
        $fields = [];
        $seen = [];
        foreach ((array)($d['fields'] ?? []) as $f) {
            $f = self::field((array)$f, $id);
            if (isset($seen[$f['k']])) {
                throw new \InvalidArgumentException('Doppeltes Feld „' . $f['k'] . '“ bei ' . $id);
            }
            $seen[$f['k']] = 1;
            $fields[] = $f;
        }
        $rules = (array)($d['rules'] ?? []) + [
            'locked' => false, 'sortable' => true, 'droppable' => false, 'repeatable' => true, 'max_instances' => 0, 'slot' => '', 'accepts' => [], 'parents' => [], 'use' => 'autor',
        ];
        if (!in_array($rules['slot'], ['', 'first', 'last'], true) || !in_array($rules['use'], ['admin', 'autor'], true)) {
            throw new \InvalidArgumentException('Ungültige Regeln bei ' . $id);
        }
        $data = (array)($d['data'] ?? []) + ['source' => 'none'];
        $preview = (array)($d['preview'] ?? []) + ['selector' => '[data-ep-id="{id}"]'];
        return new self(
            $id, mb_substr((string)($d['name'] ?? $id), 0, 60), $cat, preg_match('/^[a-z0-9-]{1,40}$/', (string)($d['icon'] ?? '')) === 1 ? (string)$d['icon'] : 'cube',
            mb_substr((string)($d['description'] ?? ''), 0, 200), $renderer, $fields, $rules, $data, $preview, $source, (string)($d['feature'] ?? ''), $render, $bind
        );
    }

    /** @param array<string,mixed> $f @return array<string,mixed> */
    private static function field(array $f, string $cid): array
    {
        $k = (string)($f['k'] ?? '');
        if (preg_match('/^[a-z][a-z0-9_]{0,40}$/', $k) !== 1 || !in_array($f['type'] ?? '', Sanitizer::TYPES, true)) {
            throw new \InvalidArgumentException('Ungültiges Feld bei ' . $cid . ': ' . $k);
        }
        $f['group'] = isset(self::GROUPS[$f['group'] ?? '']) ? $f['group'] : 'content';
        $f['label'] = mb_substr((string)($f['label'] ?? $k), 0, 120);
        $f['responsive'] = !empty($f['responsive']);
        if ($f['type'] === 'select' && empty($f['options'])) {
            throw new \InvalidArgumentException('Auswahlfeld ohne Optionen: ' . $cid . '.' . $k);
        }
        if ($f['type'] === 'items') {
            $f['item'] = array_map(fn($x) => self::field((array)$x, $cid), (array)($f['item'] ?? []));
            if ($f['item'] === []) {
                throw new \InvalidArgumentException('Listenfeld ohne Unterfelder: ' . $cid . '.' . $k);
            }
        }
        return $f;
    }

    public function fieldDef(string $k): ?array
    {
        foreach ($this->fields as $f) {
            if ($f['k'] === $k) {
                return $f;
            }
        }
        return null;
    }

    public function hasRenderer(): bool { return $this->render !== null; }

    public function render(array $props, array $instance, Registry $r): string
    {
        return $this->render === null ? '' : (string)($this->render)($props, $instance, $r);
    }

    /** Öffentliche Beschreibung für die Oberfläche (ohne Funktionen). @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'category' => $this->category, 'icon' => $this->icon, 'description' => $this->description, 'renderer' => $this->renderer,
            'fields' => $this->fields, 'defaults' => $this->defaults, 'rules' => $this->rules, 'data' => $this->data, 'preview' => $this->preview, 'source' => $this->source, 'feature' => $this->feature,
            'native_render' => $this->render !== null, 'bind' => $this->bind,
        ];
    }
}

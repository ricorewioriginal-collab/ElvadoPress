<?php
declare(strict_types=1);
// cms/src/Wp/Adapter/WordPressWidgetAdapter.php – Echte WordPress-Widgets (Seitenleisten des Themes, Widget-Typen aus Core und Plugins, Einstellungen je Instanz).
// Die Einstellungen prüft das Widget selbst (WP_Widget::update), WordPress-Widgets von Plugins funktionieren damit unverändert.

namespace Elvado\Wp\Adapter;

use Elvado\Wp\Bridge;

final class WordPressWidgetAdapter implements WidgetAdapter
{
    public const INACTIVE = 'wp_inactive_widgets';

    public function __construct()
    {
        if (!Bridge::booted()) {
            throw new \RuntimeException('WordPress ist in dieser Anfrage nicht gestartet.');
        }
    }

    public function writable(): bool { return true; }

    private function widgetObject(string $idBase): ?\WP_Widget
    {
        global $wp_widget_factory;
        foreach ($wp_widget_factory->widgets as $w) {
            if ($w instanceof \WP_Widget && $w->id_base === $idBase) {
                return $w;
            }
        }
        return null;
    }

    /** @return array{0:string,1:int}|null */
    private static function split(string $id): ?array
    {
        return preg_match('/^([a-z0-9_-]+)-(\d+)$/', $id, $m) === 1 ? [$m[1], (int)$m[2]] : null;
    }

    /** @return array<string,list<string>> */
    private function sidebars(): array
    {
        $s = wp_get_sidebars_widgets();
        unset($s['array_version']);
        return array_map(static fn($l) => array_values(array_map('strval', (array)$l)), $s);
    }

    /** @return array<string,mixed>|null */
    private function describe(string $id): ?array
    {
        $sp = self::split($id);
        $w = $sp ? $this->widgetObject($sp[0]) : null;
        if (!$sp || !$w) {
            return null;
        }
        $all = $w->get_settings();
        if (!isset($all[$sp[1]]) || !is_array($all[$sp[1]])) {
            return null;
        }
        $s = $all[$sp[1]];
        $title = trim(strip_tags((string)($s['title'] ?? '')));
        $body = trim(strip_tags((string)($s['text'] ?? $s['content'] ?? $s['url'] ?? '')));
        return ['id' => $id, 'id_base' => $sp[0], 'number' => $sp[1], 'title' => $title, 'summary' => mb_substr($title !== '' ? $title : ($body !== '' ? $body : $w->name), 0, 80), 'settings' => $s];
    }

    public function areas(): array
    {
        global $wp_registered_sidebars;
        $side = $this->sidebars();
        $out = [];
        foreach ((array)$wp_registered_sidebars as $id => $sb) {
            $ws = [];
            foreach ($side[$id] ?? [] as $wid) {
                if (($d = $this->describe($wid)) !== null) {
                    $ws[] = $d;
                }
            }
            $out[] = ['id' => (string)$id, 'name' => (string)$sb['name'], 'description' => trim(strip_tags((string)($sb['description'] ?? ''))), 'inactive' => false, 'widgets' => $ws];
        }
        $in = [];
        foreach ($side[self::INACTIVE] ?? [] as $wid) {
            if (($d = $this->describe($wid)) !== null) {
                $in[] = $d;
            }
        }
        $out[] = ['id' => self::INACTIVE, 'name' => 'Nicht verwendet', 'description' => 'Ablage für Widgets, die in keinem Bereich stehen.', 'inactive' => true, 'widgets' => $in];
        return $out;
    }

    public function types(): array
    {
        global $wp_widget_factory;
        $out = [];
        foreach ($wp_widget_factory->widgets as $w) {
            if ($w instanceof \WP_Widget) {
                $out[$w->id_base] = ['id_base' => (string)$w->id_base, 'name' => (string)$w->name, 'description' => trim(strip_tags((string)($w->widget_options['description'] ?? '')))];
            }
        }
        ksort($out);
        return array_values($out);
    }

    private function user(bool $unfiltered): void
    {
        wp_set_current_user($unfiltered ? 1 : 0);
        kses_init();
    }

    public function add(string $area, string $idBase, array $settings, bool $unfiltered): array
    {
        global $wp_registered_sidebars;
        if ($area !== self::INACTIVE && !isset($wp_registered_sidebars[$area])) {
            throw new \RuntimeException('Diesen Widget-Bereich kennt das aktive Theme nicht.');
        }
        $w = $this->widgetObject($idBase);
        if (!$w) {
            throw new \RuntimeException('Diesen Widget-Typ gibt es nicht.');
        }
        $this->user($unfiltered);
        $all = $w->get_settings();
        $n = 1 + max([0] + array_filter(array_keys($all), 'is_int'));
        $all[$n] = $w->update($settings, []);
        $w->save_settings($all);
        $side = wp_get_sidebars_widgets();
        $side[$area][] = $idBase . '-' . $n;
        wp_set_sidebars_widgets($side);
        return $this->describe($idBase . '-' . $n) ?? [];
    }

    public function update(string $widgetId, array $settings, bool $unfiltered): array
    {
        $sp = self::split($widgetId);
        $w = $sp ? $this->widgetObject($sp[0]) : null;
        $all = $w ? $w->get_settings() : [];
        if (!$sp || !$w || !isset($all[$sp[1]])) {
            throw new \RuntimeException('Das Widget wurde nicht gefunden.');
        }
        $this->user($unfiltered);
        $all[$sp[1]] = $w->update($settings, (array)$all[$sp[1]]);
        $w->save_settings($all);
        return $this->describe($widgetId) ?? [];
    }

    public function move(array $layout): void
    {
        global $wp_registered_sidebars;
        $side = wp_get_sidebars_widgets();
        $placed = [];
        foreach ($layout as $area => $ids) {
            if ($area !== self::INACTIVE && !isset($wp_registered_sidebars[$area])) {
                throw new \RuntimeException('Der Widget-Bereich „' . $area . '“ ist nicht bekannt.');
            }
            $list = [];
            foreach ($ids as $id) {
                if ($this->describe($id) === null) {
                    throw new \RuntimeException('Das Widget „' . $id . '“ gibt es nicht.');
                }
                if (!isset($placed[$id])) {
                    $placed[$id] = true;
                    $list[] = $id;
                }
            }
            $side[$area] = $list;
        }
        foreach ($side as $area => $ids) {   // verschobene Widgets aus anderen Bereichen entfernen
            if (!isset($layout[$area]) && is_array($ids)) {
                $side[$area] = array_values(array_filter($ids, static fn($i) => !isset($placed[$i])));
            }
        }
        wp_set_sidebars_widgets($side);
    }

    public function delete(string $widgetId): bool
    {
        $sp = self::split($widgetId);
        $w = $sp ? $this->widgetObject($sp[0]) : null;
        $all = $w ? $w->get_settings() : [];
        if (!$sp || !$w || !isset($all[$sp[1]])) {
            return false;
        }
        $side = wp_get_sidebars_widgets();
        foreach ($side as $area => $ids) {
            if (is_array($ids)) {
                $side[$area] = array_values(array_filter($ids, static fn($i) => $i !== $widgetId));
            }
        }
        wp_set_sidebars_widgets($side);
        unset($all[$sp[1]]);
        $w->save_settings($all);
        return true;
    }
}

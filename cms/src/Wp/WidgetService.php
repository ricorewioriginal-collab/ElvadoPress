<?php
declare(strict_types=1);
// cms/src/Wp/WidgetService.php – Dienstschicht für Widgets: Rechte (Ändern nur Administratoren), Eingabeprüfung nach Schema (Kern-Widgets) bzw. als begrenztes JSON (Plugin-Widgets), Aufruf des Adapters.

namespace Elvado\Wp;

use Elvado\Components\Sanitizer;
use Elvado\Wp\Adapter\WidgetAdapter;

final class WidgetService
{
    public const MAX_JSON = 20000;

    public function __construct(private readonly WidgetAdapter $adapter, private readonly ?Actor $actor = null)
    {
    }

    private function actor(): Actor { return $this->actor ?? new Actor('', 'admin'); }

    private function write(): void
    {
        if (!$this->adapter->writable()) {
            throw new \RuntimeException('Die Widgets sind schreibgeschützt (WordPress-Engine nicht aktiv).');
        }
        if (!$this->actor()->can('content_any')) {
            throw new PermissionException('Widgets ändern nur Administratoren.');
        }
    }

    /** @return array{areas:list<array<string,mixed>>,types:list<array<string,mixed>>} Typen mit Schema (null = JSON) */
    public function overview(): array
    {
        $types = array_map(static fn($t) => $t + ['schema' => WidgetSchemas::for($t['id_base'])], $this->adapter->types());
        return ['areas' => $this->adapter->areas(), 'types' => $types];
    }

    /** @param mixed $settings */
    public function add(string $area, string $idBase, mixed $settings): array
    {
        $this->write();
        $this->check($area, '/^[A-Za-z0-9_-]{1,60}$/', 'Bereich');
        $this->check($idBase, '/^[a-z0-9_-]{1,60}$/', 'Widget-Typ');
        return $this->adapter->add($area, $idBase, $this->settings($idBase, $settings), $this->actor()->isAdmin());
    }

    /** @param mixed $settings */
    public function update(string $widgetId, mixed $settings): array
    {
        $this->write();
        $this->check($widgetId, '/^[a-z0-9_-]+-\d{1,9}$/', 'Widget');
        $base = (string)preg_replace('/-\d+$/', '', $widgetId);
        return $this->adapter->update($widgetId, $this->settings($base, $settings), $this->actor()->isAdmin());
    }

    /** @param mixed $layout Bereich ⇒ Liste von Widget-Kennungen */
    public function move(mixed $layout): void
    {
        $this->write();
        if (!is_array($layout) || count($layout) > 50) {
            throw new \InvalidArgumentException('Ungültige Anordnung.');
        }
        $clean = [];
        $total = 0;
        foreach ($layout as $area => $ids) {
            $this->check((string)$area, '/^[A-Za-z0-9_-]{1,60}$/', 'Bereich');
            $list = [];
            foreach (is_array($ids) ? $ids : [] as $id) {
                $this->check((string)$id, '/^[a-z0-9_-]+-\d{1,9}$/', 'Widget');
                $list[] = (string)$id;
                if (++$total > 500) {
                    throw new \InvalidArgumentException('Zu viele Widgets.');
                }
            }
            $clean[(string)$area] = $list;
        }
        $this->adapter->move($clean);
    }

    public function delete(string $widgetId): bool
    {
        $this->write();
        $this->check($widgetId, '/^[a-z0-9_-]+-\d{1,9}$/', 'Widget');
        return $this->adapter->delete($widgetId);
    }

    private function check(string $v, string $re, string $what): void
    {
        if (preg_match($re, $v) !== 1) {
            throw new \InvalidArgumentException('Ungültige Angabe: ' . $what . '.');
        }
    }

    /** @return array<string,mixed> */
    private function settings(string $idBase, mixed $in): array
    {
        $in = is_array($in) ? $in : [];
        $schema = WidgetSchemas::for($idBase);
        if ($schema === null) {   // Plugin-Widget: begrenztes JSON, das Widget bereinigt selbst
            if (strlen((string)json_encode($in)) > self::MAX_JSON || self::depth($in) > 5) {
                throw new \InvalidArgumentException('Die Einstellungen sind zu groß oder zu tief verschachtelt.');
            }
            return $in;
        }
        $out = [];
        $ctx = ['unfiltered' => $this->actor()->isAdmin()];
        foreach ($schema as $f) {
            $v = Sanitizer::value($f, $in[$f['k']] ?? null, $ctx);
            $out[$f['k']] = $f['type'] === 'checkbox' ? ($v ? 1 : 0) : $v;
        }
        return $out;
    }

    private static function depth(mixed $v): int
    {
        if (!is_array($v)) {
            return 0;
        }
        $m = 0;
        foreach ($v as $x) {
            $m = max($m, self::depth($x));
        }
        return 1 + $m;
    }
}

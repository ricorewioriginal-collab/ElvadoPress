<?php
declare(strict_types=1);
// cms/src/Wp/BlockService.php – Dienstschicht für WordPress-/Gutenberg-Blöcke: registrierte Block-Typen (Core und Plugins), Block-Markup lesen und erzeugen, prüfen, ausgeben, zwischen ElvadoPress- und WordPress-Format umwandeln.
// Unbekannte oder inkompatible Blöcke gehen nie verloren: sie bleiben unverändert erhalten (Fallback „nicht registriert“) und werden nur nicht gesondert bearbeitet.
// Setzt voraus, dass WordPress in dieser Anfrage gestartet wurde.

namespace Elvado\Wp;

use Elvado\Blocks\Converter;

final class BlockService
{
    public const MAX_BYTES = 400_000;
    public const MAX_BLOCKS = 500;
    public const MAX_DEPTH = 8;

    public function __construct(private readonly ?Actor $actor = null)
    {
        if (!Bridge::booted()) {
            throw new \RuntimeException('WordPress ist in dieser Anfrage nicht gestartet.');
        }
    }

    private function unfiltered(): bool { return ($this->actor ?? new Actor('', 'admin'))->isAdmin(); }

    /** @return list<array<string,mixed>> */
    public function registry(): array
    {
        $out = [];
        foreach (\WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $t) {
            $out[] = [
                'name' => (string)$name, 'title' => (string)($t->title ?: $name), 'category' => (string)($t->category ?? ''), 'description' => mb_substr(trim(strip_tags((string)($t->description ?? ''))), 0, 200),
                'core' => str_starts_with((string)$name, 'core/'), 'dynamic' => $t->render_callback !== null, 'attributes' => array_slice(array_keys((array)$t->attributes), 0, 40),
                'parent' => array_values(array_map('strval', (array)($t->parent ?? []))), 'supports' => array_slice(array_keys((array)($t->supports ?? [])), 0, 30), 'api_version' => (int)($t->api_version ?? 1),
            ];
        }
        usort($out, static fn($a, $b) => [!$a['core'], $a['name']] <=> [!$b['core'], $b['name']]);
        return $out;
    }

    private function limit(string $m): string
    {
        if (strlen($m) > self::MAX_BYTES || str_contains($m, "\0")) {
            throw new \InvalidArgumentException('Der Inhalt ist zu groß oder ungültig.');
        }
        return $m;
    }

    /** @return list<array<string,mixed>> */
    public function parse(string $markup): array
    {
        $count = 0;
        return $this->describe(parse_blocks($this->limit($markup)), $count);
    }

    /** @param list<array<string,mixed>> $blocks @return list<array<string,mixed>> */
    private function describe(array $blocks, int &$count): array
    {
        $reg = \WP_Block_Type_Registry::get_instance();
        $out = [];
        foreach ($blocks as $b) {
            if (++$count > self::MAX_BLOCKS) {
                throw new \InvalidArgumentException('Zu viele Blöcke (höchstens ' . self::MAX_BLOCKS . ').');
            }
            $name = (string)($b['blockName'] ?? '');
            $t = $name !== '' ? $reg->get_registered($name) : null;
            $out[] = [
                'blockName' => $b['blockName'], 'attrs' => (object)($b['attrs'] ?? []), 'innerBlocks' => $this->describe((array)($b['innerBlocks'] ?? []), $count), 'innerHTML' => (string)($b['innerHTML'] ?? ''),
                'innerContent' => array_values((array)($b['innerContent'] ?? [])),
                'known' => $name === '' || $t !== null, 'dynamic' => $t !== null && $t->render_callback !== null, 'freeform' => $name === '',
            ];
        }
        return $out;
    }

    /** Aus einem (vom Client bearbeiteten) Baum wieder Block-Markup erzeugen; alles wird geprüft und bereinigt. @param mixed $tree */
    public function serialize(mixed $tree): string
    {
        $count = 0;
        $blocks = $this->clean(is_array($tree) ? array_values($tree) : [], 1, $count);
        $out = serialize_blocks($blocks);
        return $this->limit($out);
    }

    /** @param list<mixed> $list @return list<array<string,mixed>> */
    private function clean(array $list, int $depth, int &$count): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw new \InvalidArgumentException('Blöcke sind zu tief verschachtelt.');
        }
        $out = [];
        foreach ($list as $b) {
            if (!is_array($b)) {
                continue;
            }
            if (++$count > self::MAX_BLOCKS) {
                throw new \InvalidArgumentException('Zu viele Blöcke (höchstens ' . self::MAX_BLOCKS . ').');
            }
            $name = $b['blockName'] ?? null;
            if ($name !== null && $name !== '' && preg_match('~^[a-z0-9][a-z0-9-]*/[a-z0-9][a-z0-9-]*$~', (string)$name) !== 1) {
                throw new \InvalidArgumentException('Ungültiger Blockname.');
            }
            $attrs = $b['attrs'] ?? [];
            $attrs = is_object($attrs) ? (array)$attrs : (is_array($attrs) ? $attrs : []);
            if (strlen((string)json_encode($attrs)) > 20000) {
                throw new \InvalidArgumentException('Die Block-Einstellungen sind zu groß.');
            }
            $inner = $this->clean(is_array($b['innerBlocks'] ?? null) ? array_values($b['innerBlocks']) : [], $depth + 1, $count);
            $content = [];
            $slots = 0;
            foreach (is_array($b['innerContent'] ?? null) ? $b['innerContent'] : [(string)($b['innerHTML'] ?? '')] as $c) {
                if ($c === null) {
                    $content[] = null;
                    $slots++;
                } else {
                    $content[] = $this->html((string)$c);
                }
            }
            if ($slots !== count($inner)) {   // Platzhalter und Kinder müssen zusammenpassen: sonst einfach hintereinander
                $content = array_values(array_filter($content, 'is_string'));
                $content = array_merge($content, array_fill(0, count($inner), null));
            }
            $out[] = ['blockName' => $name === '' ? null : $name, 'attrs' => $attrs, 'innerBlocks' => $inner, 'innerHTML' => implode('', array_filter($content, 'is_string')), 'innerContent' => $content];
        }
        return $out;
    }

    private function html(string $h): string
    {
        return $this->unfiltered() ? $h : wp_kses_post($h);
    }

    /** Block-Markup ausgeben (wie die Website es tut). Ohne Administratorrecht wird das Ergebnis bereinigt. */
    public function render(string $markup): string
    {
        $html = do_blocks($this->limit($markup));
        return $this->unfiltered() ? $html : wp_kses_post($html);
    }

    /** @return array{blocks:int,unknown:list<string>,dynamic:list<string>,freeform:bool} */
    public function check(string $markup): array
    {
        $unknown = [];
        $dyn = [];
        $free = false;
        $n = 0;
        $walk = function (array $list) use (&$walk, &$unknown, &$dyn, &$free, &$n): void {
            foreach ($list as $b) {
                $n++;
                if (!empty($b['freeform']) && trim((string)$b['innerHTML']) !== '') {
                    $free = true;
                }
                if (!$b['known']) {
                    $unknown[(string)$b['blockName']] = 1;
                }
                if ($b['dynamic']) {
                    $dyn[(string)$b['blockName']] = 1;
                }
                $walk($b['innerBlocks']);
            }
        };
        $walk($this->parse($markup));
        return ['blocks' => $n, 'unknown' => array_keys($unknown), 'dynamic' => array_keys($dyn), 'freeform' => $free];
    }

    /**
     * Zwischen den Formaten umwandeln. @param string $to 'wp' (ElvadoPress → WordPress) oder 'ep'
     * @return array{markup:string,converted:int,fallback:int,check:array{blocks:int,unknown:list<string>,dynamic:list<string>,freeform:bool}}
     */
    public function convert(string $markup, string $to): array
    {
        if ($to !== 'wp' && $to !== 'ep') {
            throw new \InvalidArgumentException('Ziel muss „wp“ oder „ep“ sein.');
        }
        $this->limit($markup);
        $r = $to === 'wp' ? Converter::toWp($markup) : Converter::toEp($markup);
        $r['markup'] = $this->html($r['markup']);
        return $r + ['check' => $this->check($r['markup'])];
    }
}

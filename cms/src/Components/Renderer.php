<?php
declare(strict_types=1);
// cms/src/Components/Renderer.php – Gibt Layouts aus: native Komponenten selbst, alle anderen über vom Host gelieferte Funktionen (Theme, WordPress, ElvadoPress-Module).
// Ergebnis: HTML und CSS getrennt (die Seite entscheidet, wie das CSS eingebunden wird). Geräteabhängige Werte werden zu @media-Regeln; Sichtbarkeit je Gerät zu Klassen.

namespace Elvado\Components;

final class Renderer
{
    public const BP_TABLET = 1024;
    public const BP_MOBILE = 640;
    /** Erlaubte CSS-Eigenschaften für „css“-Zuordnungen von Feldern. */
    private const CSS_PROPS = ['padding', 'margin-top', 'margin-bottom', 'min-height', 'height', 'gap', 'font-size', 'text-align', 'grid-template-columns', 'max-width', 'width', 'justify-content', 'background-color', 'color'];

    public function __construct(private readonly Registry $registry)
    {
    }

    /**
     * @param list<array<string,mixed>> $layout bereinigtes Layout
     * @param array{now?:int,member?:bool,placeholders?:bool,renderers?:array<string,callable>} $ctx renderers: Typ ⇒ fn(array $props, array $instance): string für nicht native Komponenten
     * @return array{html:string,css:string}
     */
    public function render(array $layout, array $ctx = []): array
    {
        $css = [];
        $html = $this->list($layout, $ctx, $css);
        return ['html' => $html, 'css' => self::baseCss() . implode('', $css)];
    }

    /** @param list<string> $css */
    private function list(array $layout, array $ctx, array &$css): string
    {
        $out = '';
        foreach ($layout as $inst) {
            if (!is_array($inst) || !Layout::visible($inst, $ctx)) {
                continue;
            }
            $c = $this->registry->get((string)$inst['type']);
            if (!$c) {
                continue;
            }
            $inst['children_html'] = isset($inst['children']) ? $this->list((array)$inst['children'], $ctx, $css) : '';
            $inner = '';
            if ($c->hasRenderer()) {
                $inner = $c->render((array)$inst['props'], $inst, $this->registry);
            } elseif (isset($ctx['renderers'][$c->id]) && is_callable($ctx['renderers'][$c->id])) {
                $inner = (string)$ctx['renderers'][$c->id]((array)$inst['props'], $inst);
            } elseif (!empty($ctx['placeholders'])) {
                $inner = '<!-- ep:' . $c->id . ' (' . $c->renderer . ') -->';
            } else {
                continue;
            }
            if ($inner === '') {
                continue;
            }
            $id = htmlspecialchars((string)$inst['id'], ENT_QUOTES);
            $cls = implode(' ', array_merge(['ep-c', 'ep-c-' . $c->id], Layout::hideClasses($inst)));
            $out .= '<div class="' . $cls . '" data-ep-id="' . $id . '" data-ep-type="' . $c->id . '">' . $inner . '</div>' . "\n";
            $rule = $this->css($c, $inst);
            if ($rule !== '') {
                $css[] = $rule;
            }
        }
        return $out;
    }

    /** CSS-Regeln einer Instanz aus den Feldern mit „css“-Zuordnung (Grundwert, dann Tablet/Mobil). */
    public function css(Component $c, array $inst, string $attr = 'data-ep-id'): string
    {
        $sel = '[' . (in_array($attr, ['data-ep-id', 'data-bk'], true) ? $attr : 'data-ep-id') . '="' . preg_replace('/[^a-z0-9_-]/', '', (string)$inst['id']) . '"]';
        $base = '';
        $dev = ['tablet' => '', 'mobile' => ''];
        foreach ($c->fields as $f) {
            $m = $f['css'] ?? null;
            if (!is_array($m) || !in_array($m['prop'] ?? '', self::CSS_PROPS, true)) {
                continue;
            }
            $target = isset($m['target']) && preg_match('/^[ .#a-z0-9_>-]{0,40}$/i', (string)$m['target']) === 1 ? (string)$m['target'] : '';
            $decl = fn(mixed $v): string => self::decl($m, $v);
            $bv = $decl($inst['props'][$f['k']] ?? null);
            if ($bv !== '') {
                $base .= $sel . $target . '{' . $bv . '}';
            }
            if ($f['responsive']) {
                foreach (['tablet', 'mobile'] as $d) {
                    if (array_key_exists($f['k'], (array)($inst['responsive'][$d] ?? []))) {
                        $dv = $decl($inst['responsive'][$d][$f['k']]);
                        if ($dv !== '') {
                            $dev[$d] .= $sel . $target . '{' . $dv . '}';
                        }
                    }
                }
            }
        }
        return $base . ($dev['tablet'] !== '' ? '@media (max-width:' . self::BP_TABLET . 'px){' . $dev['tablet'] . '}' : '') . ($dev['mobile'] !== '' ? '@media (max-width:' . self::BP_MOBILE . 'px){' . $dev['mobile'] . '}' : '');
    }

    /** @param array<string,mixed> $m */
    private static function decl(array $m, mixed $v): string
    {
        if ($v === null || $v === '' || $v === false || (isset($m['tpl']) && ($v === '0' || $v === 0)) || (!empty($m['skip_zero']) && (int)$v === 0)) {
            return '';
        }
        $val = is_int($v) || is_float($v) ? (string)$v : (string)$v;
        if (isset($m['tpl'])) {
            $val = str_replace('{v}', $val, (string)$m['tpl']);
        } elseif (is_numeric($v) && isset($m['unit'])) {
            $val .= (string)$m['unit'];
        }
        return preg_match('/^#?[a-z0-9 %.,()-]{1,60}$/i', $val) === 1 ? $m['prop'] . ':' . $val . ';' : '';
    }

    /** Sichtbarkeit je Gerät (Klassen aus Layout::hideClasses). */
    public static function baseCss(): string
    {
        return '@media (min-width:' . (self::BP_TABLET + 1) . 'px){.ep-hide-desktop{display:none!important}}'
            . '@media (min-width:' . (self::BP_MOBILE + 1) . 'px) and (max-width:' . self::BP_TABLET . 'px){.ep-hide-tablet{display:none!important}}'
            . '@media (max-width:' . self::BP_MOBILE . 'px){.ep-hide-mobile{display:none!important}}';
    }
}

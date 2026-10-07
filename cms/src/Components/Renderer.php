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
            if (!$c || $c->bind !== '') {   // Bereiche der bestehenden Website rendert die Website selbst (siehe boundCss)
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

    /**
     * CSS für Komponenten, die an bestehende Bereiche der Website gebunden sind („bind“): Gestaltung aus den Feldern, Ausblenden bei „versteckt“, Zeitplan oder Gerät.
     * Die Website behält ihr eigenes HTML; ohne Instanz im Layout entsteht kein CSS (Ausgabe bleibt unverändert).
     * @param list<array<string,mixed>> $layout bereinigtes Layout @param array{now?:int} $ctx
     */
    public function boundCss(array $layout, array $ctx = []): string
    {
        $out = '';
        foreach ($layout as $inst) {
            $c = is_array($inst) ? $this->registry->get((string)($inst['type'] ?? '')) : null;
            $bind = $c ? $c->bind : '';
            if ($c && in_array($c->id, ['ep_text', 'ep_order'], true)) {   // wirken über Skript (overrides), nicht über CSS
                continue;
            }
            if ($bind === Component::BIND_ANY) {   // vom Live Builder erkannter Bereich: Selektor der Instanz, streng geprüft
                $bind = Component::validSelector((string)($inst['props']['selector'] ?? '')) ? (string)$inst['props']['selector'] : '';
                if ($bind === '') {
                    $bind = null;
                }
            }
            if ($c && $bind !== null && $bind !== '') {
                $sel = 'html body ' . $bind;   // etwas mehr Gewicht als die Regeln der Website (Themes setzen oft #bereich{…!important})
                $v = Layout::visibility($inst['visibility'] ?? []);
                if (!empty($inst['hidden']) || !empty($inst['missing']) || $v['devices'] === []) {
                    $out .= $sel . '{display:none!important}';
                    continue;
                }
                $now = (int)($ctx['now'] ?? time());
                if (($v['from'] !== '' && $now < strtotime($v['from'] . ':00')) || ($v['until'] !== '' && $now >= strtotime($v['until'] . ':00'))) {
                    $out .= $sel . '{display:none!important}';
                    continue;
                }
                foreach (array_diff(Layout::DEVICES, $v['devices']) as $d) {
                    $out .= match ($d) {
                        'desktop' => '@media (min-width:' . (self::BP_TABLET + 1) . 'px){' . $sel . '{display:none!important}}',
                        'tablet' => '@media (min-width:' . (self::BP_MOBILE + 1) . 'px) and (max-width:' . self::BP_TABLET . 'px){' . $sel . '{display:none!important}}',
                        default => '@media (max-width:' . self::BP_MOBILE . 'px){' . $sel . '{display:none!important}}',
                    };
                }
                $inst['props'] = array_merge($c->defaults, (array)($inst['props'] ?? []));   // fehlende Werte (älteres Layout, neues Feld) gelten als Vorgabe
                $out .= $this->css($c, $inst, 'data-ep-id', $sel);
            }
            if (is_array($inst) && !empty($inst['children'])) {
                $out .= $this->boundCss((array)$inst['children'], $ctx);
            }
        }
        return $out;
    }

    /**
     * Inhalts-Änderungen am echten Seiten-HTML aus dem Layout (themeunabhängig): Texte (ep_text) und Reihenfolge (ep_order).
     * Nur geprüfte Pfad-Selektoren; Texte sind reiner Text (nie HTML). Ausgeblendete Instanzen entfallen.
     * @param list<array<string,mixed>> $layout @return array{texts:list<array{s:string,k:int,t:string}>,order:list<array{c:string,o:list<string>}>}
     */
    public function overrides(array $layout): array
    {
        $out = ['texts' => [], 'order' => []];
        foreach ($layout as $inst) {
            if (!is_array($inst) || !empty($inst['hidden']) || !empty($inst['missing'])) {
                continue;
            }
            $p = (array)($inst['props'] ?? []);
            if (($inst['type'] ?? '') === 'ep_text' && Component::validPath((string)($p['selector'] ?? '')) && count($out['texts']) < 300) {
                $out['texts'][] = ['s' => (string)$p['selector'], 'k' => max(0, min(200, (int)($p['node'] ?? 0))), 't' => mb_substr((string)($p['text'] ?? ''), 0, 4000)];
            } elseif (($inst['type'] ?? '') === 'ep_order' && Component::validPath((string)($p['container'] ?? '')) && count($out['order']) < 60) {
                $o = [];
                foreach ((array)($p['items'] ?? []) as $it) {
                    $q = is_array($it) ? (string)($it['selector'] ?? '') : (string)$it;
                    if (Component::validPath($q) || Component::validSelector($q)) {
                        $o[] = $q;
                    }
                }
                if (count($o) >= 2) {
                    $out['order'][] = ['c' => (string)$p['container'], 'o' => array_slice($o, 0, 40)];
                }
            }
            if (!empty($inst['children'])) {
                $c = $this->overrides((array)$inst['children']);
                $out['texts'] = array_merge($out['texts'], $c['texts']);
                $out['order'] = array_merge($out['order'], $c['order']);
            }
        }
        return $out;
    }

    /** CSS-Regeln einer Instanz aus den Feldern mit „css“-Zuordnung (Grundwert, dann Tablet/Mobil). */
    public function css(Component $c, array $inst, string $attr = 'data-ep-id', ?string $selector = null): string
    {
        $sel = $selector ?? '[' . (in_array($attr, ['data-ep-id', 'data-bk'], true) ? $attr : 'data-ep-id') . '="' . preg_replace('/[^a-z0-9_-]/', '', (string)$inst['id']) . '"]';
        $base = '';
        $dev = ['tablet' => '', 'mobile' => ''];
        foreach ($c->fields as $f) {
            $m = $f['css'] ?? null;
            if (is_array($m) && isset($m['hide'])) {   // Schalter: blendet einen Teil des Bereichs aus (when: off = bei „aus“, on = bei „an“)
                $on = !empty($inst['props'][$f['k']]);
                if (preg_match('/^[ .#a-z0-9_>-]{1,60}$/i', (string)$m['hide']) === 1 && (($m['when'] ?? 'off') === 'off' ? !$on : $on)) {
                    $base .= $sel . ' ' . $m['hide'] . '{display:none!important}';
                }
                continue;
            }
            if (!is_array($m) || !in_array($m['prop'] ?? '', self::CSS_PROPS, true)) {
                continue;
            }
            $target = isset($m['target']) && preg_match('/^[ .#a-z0-9_>-]{0,40}$/i', (string)$m['target']) === 1 ? (string)$m['target'] : '';
            $decl = fn(mixed $v): string => self::decl($m, $v, $selector !== null);   // gebundene Bereiche: !important – die Website setzt ihre eigenen Regeln oft später (z. B. per Skript)
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
    private static function decl(array $m, mixed $v, bool $important = false): string
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
        return preg_match('/^#?[a-z0-9 %.,()-]{1,60}$/i', $val) === 1 ? $m['prop'] . ':' . $val . ($important ? '!important' : '') . ';' : '';
    }

    /** Neutrale Grundgestaltung der nativen Komponenten (Farben über CSS-Variablen der Seite, mit Rückfall). */
    public static function defaultCss(): string
    {
        return '.ep-c{box-sizing:border-box}.ep-c img,.ep-c video,.ep-c iframe{max-width:100%;height:auto}'
            . '.ep-btn{display:inline-block;padding:.65em 1.3em;border-radius:8px;text-decoration:none;font-weight:700;border:2px solid transparent;background:var(--bk-accent,var(--accent,#2563eb));color:#fff}'
            . '.ep-btn-secondary{background:var(--bk-surface,#334155)}.ep-btn-outline{background:transparent;color:var(--bk-accent,var(--accent,#2563eb));border-color:currentColor}'
            . '.ep-container{padding:24px;margin:0 auto}.ep-bg-alt{background:rgba(127,127,127,.12)}.ep-bg-accent{background:var(--bk-accent,var(--accent,#2563eb));color:#fff}.ep-bg-dark{background:#111827;color:#fff}'
            . '.ep-columns{gap:24px}.ep-gallery img{width:100%;height:auto;display:block;border-radius:6px}.ep-image figcaption,.ep-audio figcaption{font-size:.85em;opacity:.75;margin-top:4px}'
            . '.ep-embed{position:relative;aspect-ratio:16/9}.ep-embed iframe{position:absolute;inset:0;width:100%;height:100%;border:0}.ep-video{width:100%}'
            . '.ep-menu{list-style:none;margin:0;padding:0;display:flex;gap:16px;flex-wrap:wrap}.ep-menu a{text-decoration:none;color:inherit}.ep-radio{display:flex;gap:12px;align-items:center}.ep-radio img{width:64px;height:64px;object-fit:cover;border-radius:8px}'
            . '.ep-podcast{list-style:none;margin:0;padding:0;display:grid;gap:10px}.ep-header,.ep-footer{display:flex;gap:16px;align-items:center;justify-content:space-between;flex-wrap:wrap;padding:12px 16px}';
    }

    /** Sichtbarkeit je Gerät (Klassen aus Layout::hideClasses). */
    public static function baseCss(): string
    {
        return '@media (min-width:' . (self::BP_TABLET + 1) . 'px){.ep-hide-desktop{display:none!important}}'
            . '@media (min-width:' . (self::BP_MOBILE + 1) . 'px) and (max-width:' . self::BP_TABLET . 'px){.ep-hide-tablet{display:none!important}}'
            . '@media (max-width:' . self::BP_MOBILE . 'px){.ep-hide-mobile{display:none!important}}';
    }
}

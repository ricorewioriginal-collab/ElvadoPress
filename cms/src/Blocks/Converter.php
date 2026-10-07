<?php
declare(strict_types=1);
// cms/src/Blocks/Converter.php – Wandelt Inhalte zwischen dem Block-Format des ElvadoPress-Editors (<!-- ep:name {…} -->…<!-- /ep:name -->) und echten WordPress-/Gutenberg-Blöcken (<!-- wp:name {…} -->…<!-- /wp:name -->).
// Reines PHP, ohne WordPress. Grundsatz: nichts geht verloren. Was sich nicht verlustfrei abbilden lässt (eigene Farben, Abstände, Stile, unbekannte Blöcke), wird zu einem „HTML“-Block (core/html bzw. ep:html)
// mit dem unveränderten HTML – die Darstellung bleibt, nur die Bearbeitbarkeit als Spezialblock entfällt.

namespace Elvado\Blocks;

final class Converter
{
    private const TOKEN = '~<!--\s*(/)?(ep|wp):([a-z0-9][a-z0-9_/\-]*)(?:\s+(\{.*?\}))?\s*(/)?-->~s';

    /** Standardwerte des ElvadoPress-Editors: nur wenn alle Attribute diesen entsprechen, ist ein Block „schlicht“ und lässt sich verlustfrei abbilden. */
    private const EP_DEFAULTS = [
        'paragraph' => ['align' => 'left'], 'heading' => ['level' => 2, 'align' => 'left'], 'list' => ['ordered' => false], 'quote' => ['align' => 'left'], 'code' => ['lang' => ''],
        'spacer' => ['height' => 40], 'divider' => ['style' => 'solid', 'thickness' => 2, 'width' => 100, 'color' => '#94a3b8'], 'image' => ['size' => 'f', 'align' => 'center', 'link' => '', 'newTab' => false],
        'columns' => ['gap' => 24], 'column' => [], 'shortcode' => [],
    ];

    /** Attribute, die sich verlustfrei abbilden lassen (beliebiger Wert) – alles andere muss dem Standard entsprechen. */
    private const FREE = ['paragraph' => ['align'], 'heading' => ['level', 'align'], 'list' => ['ordered'], 'spacer' => ['height']];

    /** @return array{markup:string,converted:int,fallback:int} */
    public static function toWp(string $markup): array
    {
        $conv = 0;
        $fb = 0;
        $out = self::epList($markup, $conv, $fb);
        return ['markup' => $out, 'converted' => $conv, 'fallback' => $fb];
    }

    /** @return array{markup:string,converted:int,fallback:int} */
    public static function toEp(string $markup): array
    {
        $conv = 0;
        $keep = 0;
        $out = '';
        foreach (self::scan($markup, 'wp') as $n) {
            if ($n['kind'] === 'text') {
                $out .= $n['text'];
                continue;
            }
            $r = self::wpBlock($n);
            if ($r === null) {
                $out .= $n['raw'];
                $keep++;
            } else {
                $out .= $r;
                $conv++;
            }
        }
        return ['markup' => $out, 'converted' => $conv, 'fallback' => $keep];
    }

    // ───────── Zerlegen ─────────

    /** @return list<array<string,mixed>> Knoten: kind=block (name, attrs, inner, raw) oder kind=text */
    public static function scan(string $s, string $ns): array
    {
        preg_match_all(self::TOKEN, $s, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $tok = [];
        foreach ($m as $t) {
            if ($t[2][0] !== $ns) {
                continue;
            }
            $tok[] = ['close' => $t[1][0] === '/', 'self' => isset($t[5]) && $t[5][0] === '/', 'name' => $t[3][0], 'json' => $t[4][0] ?? '', 'start' => $t[0][1], 'end' => $t[0][1] + strlen($t[0][0])];
        }
        $out = [];
        $pos = 0;
        $i = 0;
        $n = count($tok);
        while ($i < $n) {
            $t = $tok[$i];
            if ($t['close']) {
                $i++;
                continue;   // verwaister Schluss: bleibt Text
            }
            if ($t['start'] < $pos) {
                $i++;
                continue;
            }
            if ($t['self']) {
                $end = $t['end'];
                $inner = '';
                $j = $i;
            } else {
                $depth = 1;
                $j = $i + 1;
                while ($j < $n && $depth > 0) {
                    if ($tok[$j]['self']) {
                    } elseif ($tok[$j]['close']) {
                        $depth--;
                    } else {
                        $depth++;
                    }
                    if ($depth > 0) {
                        $j++;
                    }
                }
                if ($depth > 0) {
                    $i++;
                    continue;   // nicht geschlossen: Text
                }
                $end = $tok[$j]['end'];
                $inner = substr($s, $t['end'], $tok[$j]['start'] - $t['end']);
            }
            if ($t['start'] > $pos) {
                $out[] = ['kind' => 'text', 'text' => substr($s, $pos, $t['start'] - $pos)];
            }
            $a = $t['json'] !== '' ? json_decode($t['json'], true) : [];
            $out[] = ['kind' => 'block', 'name' => $t['name'], 'attrs' => is_array($a) ? $a : [], 'inner' => $inner, 'raw' => substr($s, $t['start'], $end - $t['start'])];
            $pos = $end;
            $i = $j + 1;
        }
        if ($pos < strlen($s)) {
            $out[] = ['kind' => 'text', 'text' => substr($s, $pos)];
        }
        return $out;
    }

    // ───────── ElvadoPress → WordPress ─────────

    private static function epList(string $markup, int &$conv, int &$fb): string
    {
        $out = '';
        foreach (self::scan($markup, 'ep') as $n) {
            if ($n['kind'] === 'text') {
                $out .= $n['text'];
                continue;
            }
            $r = self::epBlock($n, $conv, $fb);
            if ($r === null) {
                $out .= self::html($n['inner']);
                $fb++;
            } else {
                $out .= $r;
                $conv++;
            }
        }
        return $out;
    }

    private static function html(string $raw): string { return '<!-- wp:html -->' . $raw . '<!-- /wp:html -->'; }

    /** Nur Standardwerte (und leere Zusatzangaben)? @param array<string,mixed> $attrs */
    private static function plain(string $name, array $attrs): bool
    {
        $def = self::EP_DEFAULTS[$name] ?? [];
        foreach ($attrs as $k => $v) {
            if (in_array($k, self::FREE[$name] ?? [], true)) {
                continue;
            }
            if (in_array($v, ['', null, false, 0.0], true) && !array_key_exists($k, $def)) {
                continue;
            }
            if (!array_key_exists($k, $def) || $def[$k] !== $v) {
                return false;
            }
        }
        return true;
    }

    private static function attrJson(array $a): string { return $a === [] ? '' : ' ' . json_encode($a, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); }

    private static function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

    /** Inhalt des ersten passenden Tags oder null. */
    private static function inside(string $html, string $tag): ?string
    {
        return preg_match('~^\s*<' . $tag . '\b[^>]*>(.*)</' . $tag . '>\s*$~s', $html, $m) === 1 ? $m[1] : null;
    }

    /** @param array<string,mixed> $n */
    private static function epBlock(array $n, int &$conv, int &$fb): ?string
    {
        $name = (string)$n['name'];
        $a = (array)$n['attrs'];
        $inner = (string)$n['inner'];
        if ($name === 'html') {
            return self::html($inner);
        }
        if ($name === 'shortcode') {
            return preg_match('~^\s*(\[[^\]]+\])\s*$~', strip_tags($inner), $m) === 1 && self::plain($name, $a) ? '<!-- wp:shortcode -->' . $m[1] . '<!-- /wp:shortcode -->' : null;
        }
        if (!self::plain($name, $a)) {
            return null;
        }
        switch ($name) {
            case 'paragraph':
                $t = self::inside($inner, 'p');
                if ($t === null) {
                    return null;
                }
                $al = (string)($a['align'] ?? 'left');
                $al = in_array($al, ['center', 'right'], true) ? $al : '';
                return '<!-- wp:paragraph' . self::attrJson($al ? ['align' => $al] : []) . ' --><p' . ($al ? ' class="has-text-align-' . $al . '"' : '') . '>' . $t . '</p><!-- /wp:paragraph -->';
            case 'heading':
                if (preg_match('~^\s*<h([1-6])\b[^>]*>(.*)</h\1>\s*$~s', $inner, $m) !== 1) {
                    return null;
                }
                $lv = (int)$m[1];
                $al = in_array($a['align'] ?? '', ['center', 'right'], true) ? (string)$a['align'] : '';
                $attrs = ($al ? ['textAlign' => $al] : []) + ($lv !== 2 ? ['level' => $lv] : []);
                return '<!-- wp:heading' . self::attrJson($attrs) . ' --><h' . $lv . ' class="' . ($al ? 'has-text-align-' . $al . ' ' : '') . 'wp-block-heading">' . $m[2] . '</h' . $lv . '><!-- /wp:heading -->';
            case 'list':
                if (preg_match('~^\s*<(ul|ol)\b[^>]*>(.*)</\1>\s*$~s', $inner, $m) !== 1 || preg_match_all('~<li\b[^>]*>(.*?)</li>~s', $m[2], $li) < 1 || preg_match('~<(ul|ol)\b~i', $m[2])) {
                    return null;
                }
                $items = '';
                foreach ($li[1] as $x) {
                    $items .= '<!-- wp:list-item --><li>' . $x . '</li><!-- /wp:list-item -->';
                }
                return '<!-- wp:list' . self::attrJson($m[1] === 'ol' ? ['ordered' => true] : []) . ' --><' . $m[1] . ' class="wp-block-list">' . $items . '</' . $m[1] . '><!-- /wp:list -->';
            case 'quote':
                if (preg_match('~<blockquote\b[^>]*>\s*<p>(.*?)</p>\s*(?:<cite>(.*?)</cite>)?\s*</blockquote>~s', $inner, $m) !== 1) {
                    return null;
                }
                return '<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>' . $m[1] . '</p><!-- /wp:paragraph -->' . (($m[2] ?? '') !== '' ? '<cite>' . $m[2] . '</cite>' : '') . '</blockquote><!-- /wp:quote -->';
            case 'code':
                return preg_match('~^\s*<pre\b[^>]*><code\b[^>]*>(.*)</code></pre>\s*$~s', $inner, $m) === 1 ? '<!-- wp:code --><pre class="wp-block-code"><code>' . $m[1] . '</code></pre><!-- /wp:code -->' : null;
            case 'divider':
                return '<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->';
            case 'spacer':
                $h = max(0, min(400, (int)($a['height'] ?? 40)));
                return '<!-- wp:spacer' . self::attrJson(['height' => $h . 'px']) . ' --><div style="height:' . $h . 'px" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->';
            case 'image':
                if (preg_match('~<img\b[^>]*\bsrc="([^"]*)"~', $inner, $s) !== 1) {
                    return null;
                }
                $alt = preg_match('~<img\b[^>]*\balt="([^"]*)"~', $inner, $al) === 1 ? $al[1] : '';
                $cap = preg_match('~<figcaption\b[^>]*>(.*?)</figcaption>~s', $inner, $c) === 1 ? $c[1] : '';
                if (preg_match('~<a\b~', $inner)) {
                    return null;   // verlinkte Bilder: unverändert als HTML
                }
                return '<!-- wp:image {"sizeSlug":"full","linkDestination":"none"} --><figure class="wp-block-image size-full"><img src="' . $s[1] . '" alt="' . $alt . '"/>' . ($cap !== '' ? '<figcaption class="wp-element-caption">' . $cap . '</figcaption>' : '') . '</figure><!-- /wp:image -->';
            case 'columns':
                $cols = [];
                foreach (self::scan($inner, 'ep') as $c) {
                    if ($c['kind'] === 'block') {
                        if ($c['name'] !== 'column' || !self::plain('column', (array)$c['attrs'])) {
                            return null;
                        }
                        $kids = self::epList((string)$c['inner'], $conv, $fb);
                        $cols[] = '<!-- wp:column --><div class="wp-block-column">' . self::stripWrapper($kids) . '</div><!-- /wp:column -->';
                    } elseif (trim(preg_replace('~</?div\b[^>]*>~', '', (string)$c['text'])) !== '') {
                        return null;
                    }
                }
                return $cols ? '<!-- wp:columns --><div class="wp-block-columns">' . implode('', $cols) . '</div><!-- /wp:columns -->' : null;
        }
        return null;
    }

    /** Wrapper-Tags der Spalte (<div …>…</div> um die Kinder) entfernen: epList hat nur Kinder und lose Wrapper-Texte erzeugt. */
    private static function stripWrapper(string $s): string { return trim((string)preg_replace('~^\s*<div\b[^>]*>|</div>\s*$~', '', $s)); }

    // ───────── WordPress → ElvadoPress ─────────

    /** @param array<string,mixed> $n @return string|null null = unverändert lassen */
    private static function wpBlock(array $n): ?string
    {
        $name = (string)$n['name'];
        $a = (array)$n['attrs'];
        $in = (string)$n['inner'];
        $ep = static fn(string $nm, array $at, string $body): string => '<!-- ep:' . $nm . self::attrJson($at) . ' -->' . $body . '<!-- /ep:' . $nm . ' -->';
        switch ($name) {
            case 'html':
                return $ep('html', [], $in);
            case 'shortcode':
                return $ep('shortcode', [], '<p>' . trim($in) . '</p>');
            case 'paragraph':
                $t = self::inside($in, 'p');
                if ($t === null || array_diff(array_keys($a), ['align'])) {
                    return null;
                }
                $al = in_array($a['align'] ?? '', ['center', 'right'], true) ? (string)$a['align'] : '';
                return $ep('paragraph', $al ? ['align' => $al] : [], '<p' . ($al ? ' style="text-align:' . $al . '"' : '') . '>' . $t . '</p>');
            case 'heading':
                if (preg_match('~^\s*<h([1-6])\b[^>]*>(.*)</h\1>\s*$~s', $in, $m) !== 1 || array_diff(array_keys($a), ['level', 'textAlign'])) {
                    return null;
                }
                $al = in_array($a['textAlign'] ?? '', ['center', 'right'], true) ? (string)$a['textAlign'] : '';
                return $ep('heading', ['level' => (int)$m[1]] + ($al ? ['align' => $al] : []), '<h' . $m[1] . ($al ? ' style="text-align:' . $al . '"' : '') . '>' . $m[2] . '</h' . $m[1] . '>');
            case 'list':
                if (preg_match('~^\s*<(ul|ol)\b[^>]*>(.*)</\1>\s*$~s', $in, $m) !== 1 || array_diff(array_keys($a), ['ordered', 'values', 'start', 'reversed'])) {
                    return null;
                }
                preg_match_all('~<!--\s*wp:list-item[^>]*-->\s*<li\b[^>]*>(.*?)</li>\s*<!--\s*/wp:list-item\s*-->~s', $m[2], $li);
                if (!$li[1] || preg_replace('~<!--\s*/?wp:list-item[^>]*-->|<li\b[^>]*>.*?</li>|\s+~s', '', $m[2]) !== '') {
                    return null;
                }
                return $ep('list', $m[1] === 'ol' ? ['ordered' => true] : [], '<' . $m[1] . '>' . implode('', array_map(static fn($x) => '<li>' . $x . '</li>', $li[1])) . '</' . $m[1] . '>');
            case 'quote':
                if (preg_match('~<blockquote\b[^>]*>\s*<!--\s*wp:paragraph\s*-->\s*<p>(.*?)</p>\s*<!--\s*/wp:paragraph\s*-->\s*(?:<cite>(.*?)</cite>)?\s*</blockquote>~s', $in, $m) !== 1 || $a) {
                    return null;
                }
                return $ep('quote', [], '<blockquote><p>' . $m[1] . '</p>' . (($m[2] ?? '') !== '' ? '<cite>' . $m[2] . '</cite>' : '') . '</blockquote>');
            case 'code':
                return preg_match('~^\s*<pre\b[^>]*><code\b[^>]*>(.*)</code></pre>\s*$~s', $in, $m) === 1 && !$a ? $ep('code', [], '<pre><code>' . $m[1] . '</code></pre>') : null;
            case 'separator':
                return !array_diff(array_keys($a), ['opacity']) ? $ep('divider', [], '<hr>') : null;
            case 'spacer':
                if (array_diff(array_keys($a), ['height']) || preg_match('~^(\d{1,3})px$~', (string)($a['height'] ?? '100px'), $m) !== 1) {
                    return null;
                }
                return $ep('spacer', ['height' => (int)$m[1]], '<div style="height:' . (int)$m[1] . 'px" aria-hidden="true"></div>');
        }
        return null;
    }
}

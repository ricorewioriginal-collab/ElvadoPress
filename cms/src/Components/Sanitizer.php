<?php
declare(strict_types=1);
// cms/src/Components/Sanitizer.php – Bereinigt Feldwerte der Komponenten nach ihrem Schema (reines PHP, ohne WordPress).
// Feldtypen: text, textarea, html, url, image, checkbox, number, select, color, items (Liste von Unterfeldern).

namespace Elvado\Components;

final class Sanitizer
{
    public const TYPES = ['text', 'textarea', 'html', 'url', 'image', 'checkbox', 'number', 'select', 'color', 'items'];

    /** @param array<string,mixed> $f Feldschema @param array{unfiltered?:bool} $ctx */
    public static function value(array $f, mixed $v, array $ctx = []): mixed
    {
        $type = (string)($f['type'] ?? 'text');
        $def = $f['default'] ?? null;
        $v = $v ?? $def;
        switch ($type) {
            case 'text':
                return mb_substr(self::line((string)(is_scalar($v) ? $v : '')), 0, (int)($f['max'] ?? 300));
            case 'textarea':
                $t = trim(strip_tags((string)(is_scalar($v) ? $v : '')));
                return mb_substr((string)preg_replace('/[^\P{C}\n\t]+/u', '', $t), 0, (int)($f['max'] ?? 5000));
            case 'html':
                return self::html((string)(is_scalar($v) ? $v : ''), (int)($f['max'] ?? 20000), !empty($ctx['unfiltered']));
            case 'url':
                return self::url((string)(is_scalar($v) ? $v : ''), false);
            case 'image':
                return self::url((string)(is_scalar($v) ? $v : ''), true);
            case 'checkbox':
                return filter_var($v, FILTER_VALIDATE_BOOLEAN);
            case 'number':
                $min = (int)($f['min'] ?? 0);
                $max = (int)($f['max'] ?? 1000);
                return max($min, min($max, (int)(is_numeric($v) ? $v : 0)));
            case 'select':
                $o = (array)($f['options'] ?? []);
                $k = is_scalar($v) ? (string)$v : '';
                return array_key_exists($k, $o) ? $k : (string)($def ?? array_key_first($o) ?? '');
            case 'color':
                $c = trim((string)(is_scalar($v) ? $v : ''));
                return preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $c) === 1 ? strtolower($c) : (string)($def ?? '');
            case 'items':
                $out = [];
                $sub = (array)($f['item'] ?? []);
                foreach (is_array($v) ? $v : [] as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $item = [];
                    $filled = false;
                    foreach ($sub as $sf) {
                        $val = self::value($sf, $row[$sf['k']] ?? null, $ctx);
                        $item[$sf['k']] = $val;
                        $filled = $filled || ($val !== '' && $val !== false && $val !== 0 && $val !== null);
                    }
                    if ($filled) {
                        $out[] = $item;
                    }
                    if (count($out) >= (int)($f['max_items'] ?? 6)) {
                        break;
                    }
                }
                return $out;
        }
        return '';
    }

    public static function line(string $s): string
    {
        return trim((string)preg_replace('/\s+/u', ' ', (string)preg_replace('/[\p{C}]+/u', ' ', strip_tags($s))));
    }

    /** Nur harmlose Adressen: relativ (/…, #…), https://, http://, mailto:, tel:. Bilder: nur https:// oder /…. */
    public static function url(string $u, bool $imageOnly): string
    {
        $u = trim($u);
        if ($u === '' || strlen($u) > 1200 || preg_match('/[\s\x00-\x1f"<>\\\\]/', $u) === 1) {
            return '';
        }
        if (preg_match('~^(https://|/(?!/))~i', $u) === 1) {
            return $u;
        }
        if (!$imageOnly && (preg_match('~^(http://|mailto:|tel:)[^\s]+$~i', $u) === 1 || preg_match('/^#[A-Za-z0-9_-]{1,80}$/', $u) === 1)) {
            return $u;
        }
        return '';
    }

    public static function html(string $h, int $max, bool $unfiltered): string
    {
        $h = mb_substr($h, 0, $max);
        if (function_exists('elvado_html_sanitize')) {
            return elvado_html_sanitize($h, $unfiltered ? true : false);
        }
        return strip_tags($h, '<p><br><strong><b><em><i><u><ul><ol><li><a><h2><h3><h4><blockquote>');   // ohne die Bibliothek der Verwaltung: nur einfache Tags
    }
}

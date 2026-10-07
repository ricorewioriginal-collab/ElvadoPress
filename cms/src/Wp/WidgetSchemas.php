<?php
declare(strict_types=1);
// cms/src/Wp/WidgetSchemas.php – Eingabefelder der WordPress-Kern-Widgets (für eine echte Formularansicht). Widgets von Plugins haben kein Schema: ihre Einstellungen werden als JSON bearbeitet
// und vom Widget selbst bereinigt (WP_Widget::update).

namespace Elvado\Wp;

final class WidgetSchemas
{
    private static function t(string $k, string $l, int $max = 200): array { return ['k' => $k, 'label' => $l, 'type' => 'text', 'max' => $max, 'default' => '']; }
    private static function n(string $k, string $l, int $min, int $max, int $def): array { return ['k' => $k, 'label' => $l, 'type' => 'number', 'min' => $min, 'max' => $max, 'default' => $def]; }
    private static function c(string $k, string $l, bool $def = false): array { return ['k' => $k, 'label' => $l, 'type' => 'checkbox', 'default' => $def]; }

    /** @return list<array<string,mixed>>|null null = unbekannter Typ (JSON-Bearbeitung) */
    public static function for(string $idBase): ?array
    {
        $title = self::t('title', 'Titel');
        return match ($idBase) {
            'text' => [$title, ['k' => 'text', 'label' => 'Text (HTML)', 'type' => 'html', 'max' => 20000, 'default' => ''], self::c('filter', 'Absätze automatisch bilden')],
            'custom_html' => [$title, ['k' => 'content', 'label' => 'HTML', 'type' => 'html', 'max' => 20000, 'default' => '']],
            'block' => [['k' => 'content', 'label' => 'Block-Markup', 'type' => 'html', 'max' => 20000, 'default' => '']],
            'search', 'meta', 'calendar' => [$title],
            'recent-posts' => [$title, self::n('number', 'Anzahl', 1, 20, 5), self::c('show_date', 'Datum anzeigen')],
            'recent-comments' => [$title, self::n('number', 'Anzahl', 1, 20, 5)],
            'archives' => [$title, self::c('count', 'Anzahl anzeigen'), self::c('dropdown', 'Als Auswahlliste')],
            'categories' => [$title, self::c('count', 'Anzahl anzeigen'), self::c('hierarchical', 'Hierarchisch'), self::c('dropdown', 'Als Auswahlliste')],
            'pages' => [$title, ['k' => 'sortby', 'label' => 'Sortierung', 'type' => 'select', 'options' => ['post_title' => 'Titel', 'menu_order' => 'Reihenfolge', 'ID' => 'Kennung'], 'default' => 'menu_order'], self::t('exclude', 'Ausschließen (Kennungen, kommagetrennt)', 100)],
            'nav_menu' => [$title, self::t('nav_menu', 'Menü (Kennung)', 20)],
            'tag_cloud' => [$title, ['k' => 'taxonomy', 'label' => 'Art', 'type' => 'select', 'options' => ['post_tag' => 'Schlagwörter', 'category' => 'Kategorien'], 'default' => 'post_tag'], self::c('count', 'Anzahl anzeigen')],
            default => null,
        };
    }
}

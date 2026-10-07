<?php
declare(strict_types=1);
// cms/src/Wp/Migration/WordPressProbe.php – Lesende Abfragen gegen das gestartete echte WordPress (nur SELECT über $wpdb/WordPress-Lesefunktionen; verändert nichts).

namespace Elvado\Wp\Migration;

use Elvado\Wp\Bridge;

final class WordPressProbe implements TargetProbe
{
    public function available(): bool { return Bridge::booted(); }

    public function slugs(): array
    {
        global $wpdb;
        $out = [];
        foreach ((array)$wpdb->get_results("SELECT post_name, post_type FROM {$wpdb->posts} WHERE post_type IN ('post','page') AND post_status NOT IN ('trash','auto-draft') AND post_name <> ''", ARRAY_A) as $r) {
            $out[(string)$r['post_name']] = (string)$r['post_type'];
        }
        return $out;
    }

    public function users(): array
    {
        return array_map(static fn($u) => strtolower((string)$u->user_login), get_users(['fields' => ['user_login']]));
    }

    public function terms(string $taxonomy): array
    {
        $t = get_terms(['taxonomy' => in_array($taxonomy, ['tag', 'post_tag'], true) ? 'post_tag' : 'category', 'hide_empty' => false, 'fields' => 'names']);
        return is_wp_error($t) ? [] : array_map(static fn($n) => strtolower((string)$n), (array)$t);
    }

    public function migrated(): array
    {
        global $wpdb;
        $out = [];
        foreach ((array)$wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_elvado_source_id'") as $v) {
            $out[(string)$v] = true;
        }
        return $out;
    }

    public function mediaNames(): array
    {
        global $wpdb;
        return array_map(static fn($p) => strtolower(basename((string)$p)), (array)$wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file'"));
    }
}

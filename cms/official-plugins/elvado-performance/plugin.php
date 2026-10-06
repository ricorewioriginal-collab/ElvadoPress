<?php
declare(strict_types=1);
if (!isset($np) || !($np instanceof \Elvado\Plugin\Context)) {
    http_response_code(403);   // direkter Aufruf der Datei im Browser: nichts ausführen
    exit;
}
// Elvado Performance – Einstiegspunkt (nur als offizielles, unverändertes Plugin ausgeführt).
require_once __DIR__ . '/lib/Perf.php';

use ElvadoPlugin\Performance\Perf;

/** @var \Elvado\Plugin\Context $np */
$p = new Perf($np);

$np->on('front_request', [$p, 'serve'], 1);
$np->on('front_output', [$p, 'optimize'], 50);
$np->on('front_output', [$p, 'store'], 99);   // zuletzt: die fertig optimierte Seite zwischenspeichern
$np->on('content_saved', fn() => $p->purge());
$np->on('plugin_settings_saved', fn() => $p->purge());
$np->on('media_uploaded', [$p, 'onUpload']);
$np->on('tick', [$p, 'tick']);
// WordPress-Ereignisse, die den Cache ungültig machen (die Hooks stehen erst bereit, wenn die WordPress-Schicht geladen ist)
$np->on('wp_ready', function () use ($p): void {
    foreach (['save_post', 'deleted_post', 'transition_post_status', 'switch_theme', 'customize_save_after', 'wp_insert_comment', 'edit_comment', 'wp_set_comment_status', 'wp_update_nav_menu',
        'update_option_blogname', 'update_option_blogdescription', 'update_option_sidebars_widgets', 'update_option_page_on_front', 'update_option_show_on_front', 'update_option_permalink_structure', 'update_option_theme_mods_' . (function_exists('get_stylesheet') ? get_stylesheet() : '')] as $h) {
        add_action($h, static function () use ($p): void { $p->purge(); }, 99);
    }
});

$np->api('overview', function () use ($p): array {
    $s = $p->cacheStats();
    $m = $p->mediaStats();
    return ['blocks' => [
        ['type' => 'stats', 'items' => [
            ['label' => 'Seiten im Cache', 'value' => $s['entries']],
            ['label' => 'Cache-Größe', 'value' => number_format($s['size'] / 1024, 0, ',', '.') . ' KB'],
            ['label' => 'Trefferquote', 'value' => $s['rate'] . ' %'],
            ['label' => 'Bilder: WebP / AVIF', 'value' => $m['webp'] . ' / ' . $m['avif']],
        ]],
        ['type' => 'checks', 'title' => 'Server und Voraussetzungen', 'items' => $p->capabilities()],
        ['type' => 'text', 'text' => 'Gecachte Seiten tragen den Header „X-Elvado-Cache: HIT“. Angemeldete Personen sehen nie gecachte Seiten.'],
    ]];
});
$np->api('action_clear_cache', fn() => ['ok' => true, 'message' => $p->purge() . ' Seite(n) aus dem Cache entfernt.']);
$np->api('action_optimize_images', function () use ($p): array {
    if (!Perf::avifSupported()) {
        return ['ok' => false, 'message' => 'Dieser Server kann kein AVIF (PHP-Funktion imageavif fehlt). WebP-Varianten bleiben bestehen.'];
    }
    $r = $p->optimizeLibrary(15);
    return ['ok' => true, 'message' => $r['done'] . ' Bild(er) optimiert' . ($r['left'] ? ', noch ' . $r['left'] . ' offen – bitte erneut ausführen.' : '. Alles erledigt.')];
});
$np->api('action_htaccess_enable', fn() => $p->htaccessEnable());
$np->api('action_htaccess_disable', fn() => $p->htaccessDisable());

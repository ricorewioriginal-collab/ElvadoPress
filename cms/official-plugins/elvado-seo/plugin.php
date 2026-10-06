<?php
declare(strict_types=1);
// Elvado SEO – Einstiegspunkt (nur als offizielles, unverändertes Plugin ausgeführt).
require_once __DIR__ . '/lib/Seo.php';

use ElvadoPlugin\Seo\Seo;

/** @var \Elvado\Plugin\Context $np */
$seo = new Seo($np);

$np->on('front_output', [$seo, 'inject'], 30);
$np->on('content_saved', [$seo, 'onContentSaved']);
$np->on('tick', [$seo, 'tick']);
$np->on('wp_ready', function () use ($seo): void {   // Beiträge/Seiten, die in der WordPress-Schicht geändert werden
    foreach (['save_post', 'deleted_post', 'transition_post_status'] as $h) {
        add_action($h, static function () use ($seo): void { $seo->markDirty(); }, 98);
    }
});

$np->api('overview', function () use ($seo): array {
    $o = $seo->overview();
    return ['blocks' => [['type' => 'stats', 'items' => $o['stats']], ['type' => 'checks', 'title' => 'SEO-Zustand', 'items' => $o['checks']],
        ['type' => 'text', 'text' => 'SEO-Titel, Beschreibung, Kanonische Adresse und noindex pflegst du pro Beitrag im Beitragseditor („Suchmaschinen & Teilen“, mit Vorschau) und pro Seite unter Seiten. Website-weite Angaben stehen unter SEO.']]];
});
$np->api('action_sitemap_now', function () use ($seo): array { $r = $seo->generate(); return ['ok' => $r['ok'], 'message' => $r['message']]; });
$np->api('action_fill_descriptions', function () use ($seo): array { $r = $seo->fillDescriptions(); return ['ok' => true, 'message' => $r['news'] . ' Beitrag/Beiträge und ' . $r['pages'] . ' Seite(n) ergänzt.']; });
// SEO-Vorschau (Beitragseditor): bereinigt Texte genau wie die Ausgabe
$np->api('preview', function (array $a): array {
    $t = Seo::trim((string)($a['title'] ?? ''), 200);
    $d = Seo::trim((string)($a['description'] ?? ''), 400);
    return ['title' => $t, 'title_len' => mb_strlen($t), 'description' => Seo::trim($d, 160), 'description_len' => mb_strlen($d)];
}, 'editor');

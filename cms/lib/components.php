<?php
declare(strict_types=1);
// Klebstoff zur Komponenten-Registry (cms/src/Components/): gemeinsame Registry dieser Anfrage, Erweiterungen, Layout-Speicher.
// Doku: cms/docs/COMPONENTS.md

use Elvado\Components\{Registry, CoreComponents, LayoutStore};

require_once __DIR__ . '/../src/autoload.php';

/** Gemeinsame Registry: Kern-Komponenten, Radio-Komponenten (nur sichtbar mit Merkmal „radio“) und die Komponenten aktiver Erweiterungen (Hook „components_register“). */
function rrw_components(bool $fresh = false): Registry
{
    static $r = null;
    if ($r === null || $fresh) {
        $r = new Registry();
        CoreComponents::register($r);
        CoreComponents::registerRadio($r);
        if (function_exists('rrw_np_boot')) {
            try {
                rrw_np_boot();
                rrw_np_do('components_register', $r);
            } catch (\Throwable $e) {
                error_log('[ElvadoPress] Komponenten-Erweiterung: ' . $e->getMessage());
            }
        }
    }
    return $r;
}

function rrw_components_store(?string $dataDir = null): LayoutStore
{
    return new LayoutStore(($dataDir ?? (defined('RRW_DATA_DIR') ? (string)RRW_DATA_DIR : dirname(__DIR__) . '/data')) . '/layouts', rrw_components());
}

/** Aktive Merkmale für den Katalog (z. B. „radio“). @return list<string> */
function rrw_components_features(?string $dataDir = null): array
{
    $f = [];
    $dir = $dataDir ?? (defined('RRW_DATA_DIR') ? (string)RRW_DATA_DIR : dirname(__DIR__) . '/data');
    if (function_exists('rrw_radio_theme_active') && rrw_radio_theme_active($dir)) {
        $f[] = 'radio';
    }
    return $f;
}

<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

// CLI-Einstiegspunkt für den Deploy-Workflow: erzeugt aus dem auf dem Server
// liegenden cms/data/site.json erneut alle abgeleiteten Dateien (index.html-
// Snapshot, eigene Seiten, Markdown-Spiegel, SEO/Sitemap, RSS). Nutzt
// dieselbe elvado_publish()-Logik wie api.php, damit hier nichts abweicht.

require_once __DIR__.'/lib/publish.php';
require_once __DIR__.'/lib/feeds.php';
require_once __DIR__.'/lib/seo.php';
require_once __DIR__.'/lib/content.php';

$root = dirname(__DIR__);
$dataDir = __DIR__.'/data';
$genDir = __DIR__.'/generated';
$siteFile = $dataDir.'/site.json';

if (!is_file($siteFile)) { fwrite(STDERR, "cms/data/site.json fehlt\n"); exit(1); }
$site = elvado_read_json($siteFile, []);
if (!$site) { fwrite(STDERR, "cms/data/site.json ist ungültig\n"); exit(1); }
$site = elvado_ensure_site_defaults($site);

elvado_publish($site, $siteFile, $genDir, $root);

fwrite(STDOUT, elvado_product_title()." rebuild complete\n");

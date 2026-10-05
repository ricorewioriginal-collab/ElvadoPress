<?php
declare(strict_types=1);
// cms/update-cron.php – Update-Suche per Cron/Aufgabenplaner (nur Kommandozeile), z. B. stündlich:
//   0 * * * *  php /pfad/zu/cms/update-cron.php
// Sucht nach Updates, spielt sie bei aktivierter Automatik ein und überwacht ein frisches Update (automatischer Rückschritt bei Fehlern).
//   php update-cron.php --check     nur suchen (nie einspielen)
//   php update-cron.php --apply     suchen und einspielen, auch wenn die Automatik aus ist
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/src/autoload.php';

use Elvado\Update\UpdateException;
use Elvado\Update\UpdateService;

$svc = UpdateService::forCms(__DIR__, __DIR__ . '/data');
try {
    if (in_array('--check', $argv, true)) {
        $s = $svc->check();
        echo 'Installiert: ' . $s['installed']['version'] . ' · neueste: ' . ($s['latest']['version'] ?? '?') . ($s['update_available'] ? ' → Update verfügbar' : ' → aktuell') . ($s['check_error'] !== '' ? ' · Fehler: ' . $s['check_error'] : '') . "\n";
    } elseif (in_array('--apply', $argv, true)) {
        $svc->watchdog();
        $r = $svc->apply();
        echo 'Aktualisiert: ' . $r['from'] . ' → ' . $r['to'] . "\n";
    } else {
        echo json_encode($svc->automatic(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }
} catch (UpdateException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

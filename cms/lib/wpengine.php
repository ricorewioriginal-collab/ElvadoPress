<?php
declare(strict_types=1);
// Klebstoff zwischen API/Oberfläche und der WordPress-Engine (cms/src/Wp/): gemeinsame Engine, Status, Vorbereitung, Core-Installation, Entfernen.
// Doku: cms/docs/ARCHITECTURE-WORDPRESS.md, cms/docs/WORDPRESS-BRIDGE.md

use Elvado\Wp\{Engine, DbConfig, CoreSource, CoreInstaller, Requirements};
use Elvado\Wp\Adapter\NativeAdapter;

require_once __DIR__ . '/../src/autoload.php';

/** Gemeinsame Engine dieser Anfrage. $cmsDir/$dataDir nur in Tests setzen. */
function rrw_wpe(?string $cmsDir = null, ?string $dataDir = null, bool $fresh = false): Engine
{
    static $e = null;
    if ($e === null || $fresh) {
        $cms = $cmsDir ?? dirname(__DIR__);
        $e = new Engine($cms, $dataDir ?? (defined('RRW_DATA_DIR') ? (string)RRW_DATA_DIR : $cms . '/data'));
    }
    return $e;
}

/** Zustand für die Verwaltung (ohne Geheimnisse). */
function rrw_wpe_status(Engine $e, DbConfig $db): array
{
    $s = $e->state();
    $req = Requirements::check($e);
    $native = new NativeAdapter($e->cmsDir(), dirname($e->stateDir()));
    return [
        'engine' => ['mode' => $s['mode'], 'version' => $s['version'], 'installed_at' => $s['installed_at'], 'previous' => $s['previous'], 'core_present' => $e->corePath() !== null, 'safe' => $e->safe(), 'incident' => $e->incident(), 'guard' => ($g = $e->guard()) ? ['kind' => $g['kind'], 'id' => $g['id']] : null],
        'db' => ['configured' => $db->redacted(), 'ready' => !empty($s['db']['ready']), 'server' => (string)($s['db']['server'] ?? ''), 'checked_at' => (string)($s['db']['checked_at'] ?? '')],
        'requirements' => $req,
        'native' => ['name' => $native->name(), 'counts' => $native->counts()],
        'steps' => ['requirements' => $req['ok'], 'core' => $e->corePath() !== null, 'database' => !empty($s['db']['ready']), 'active' => $s['mode'] === 'active'],
    ];
}

/** Aktuelle WordPress-Version und Systemprüfung mit deren Mindestanforderungen (fragt wordpress.org). */
function rrw_wpe_prepare(Engine $e): array
{
    $l = CoreSource::latest();
    $req = Requirements::check($e, $l['ok'] ? ['php' => $l['php'], 'mysql' => $l['mysql']] : []);
    $inst = $e->state()['version'];
    return ['latest' => $l, 'requirements' => $req, 'installed_version' => $inst, 'update_available' => $l['ok'] && $inst !== '' && version_compare($l['version'], $inst, '>')];
}

/** Aktuellen WordPress-Core laden, prüfen und einspielen (Prüfsumme, Sicherheitsprüfung, atomar). */
function rrw_wpe_install_core(Engine $e, string $version): array
{
    $fail = fn(string $m): array => ['ok' => false, 'message' => $m, 'version' => ''];
    $l = CoreSource::latest();
    if (!$l['ok']) {
        return $fail($l['message']);
    }
    if ($version !== '' && $version !== $l['version']) {
        return $fail('Es lässt sich nur die aktuelle WordPress-Version (' . $l['version'] . ') einspielen.');
    }
    $req = Requirements::check($e, ['php' => $l['php'], 'mysql' => $l['mysql']]);
    if (!$req['ok']) {
        $bad = array_map(fn($i) => $i['label'], array_filter($req['items'], fn($i) => $i['status'] === 'fail'));
        return $fail('Die Systemprüfung ist nicht bestanden: ' . implode(', ', $bad) . '.');
    }
    $v = $l['version'];
    $sum = CoreSource::checksum($v);
    if (!$sum['ok']) {
        return $fail($sum['message']);
    }
    $e->protect();
    $zip = $e->stateDir() . '/download-' . $v . '.zip';
    $d = CoreSource::download($v, $zip, $sum['sha1']);
    if (!$d['ok']) {
        return $fail($d['message']);
    }
    try {
        $r = (new CoreInstaller($e))->install($zip, $v, $sum['sha1']);
    } finally {
        @unlink($zip);
    }
    return $r['ok'] ? ['ok' => true, 'message' => 'WordPress ' . $v . ' wurde geprüft und eingespielt (' . $r['files'] . ' Dateien).', 'version' => $v] : $fail($r['message']);
}

/** Vor dem Anlegen der Tabellen: Core vorhanden, Datenbank erreichbar und ohne vorhandene WordPress-Tabellen; Angaben speichern. */
function rrw_wpe_pre_install(Engine $e, DbConfig $db, array $cfg): array
{
    if ($e->corePath() === null) {
        return ['ok' => false, 'message' => 'WordPress ist noch nicht eingespielt.', 'prefix' => '', 'server' => ''];
    }
    $t = $db->test($cfg);
    if (!$t['ok']) {
        return ['ok' => false, 'message' => $t['message'], 'prefix' => '', 'server' => ''];
    }
    if ($t['needs_empty']) {
        return ['ok' => false, 'message' => 'In dieser Datenbank gibt es mit diesem Präfix bereits WordPress-Tabellen. Bitte ein anderes Präfix oder eine leere Datenbank verwenden – vorhandene Daten werden nie überschrieben.', 'prefix' => '', 'server' => ''];
    }
    $v = $db->validate($cfg);
    $db->save($cfg);
    return ['ok' => true, 'message' => '', 'prefix' => $v['cfg']['prefix'], 'server' => $t['server']];
}

/** Engine entfernen: Core-Dateien, Zugangsdaten und Zustand. Die Datenbank-Tabellen bleiben unangetastet. */
function rrw_wpe_remove(Engine $e): array
{
    $i = new CoreInstaller($e);
    foreach (glob($e->coreRoot() . '/core-*', GLOB_ONLYDIR) ?: [] as $d) {
        $i->rmTree($d);
    }
    foreach (['db.json', 'keys.json'] as $f) {
        @unlink($e->stateDir() . '/' . $f);
    }
    $e->save(['mode' => 'off', 'core' => '', 'version' => '', 'previous' => [], 'db' => []]);
    $e->log('Engine entfernt');
    return ['ok' => true, 'message' => 'Die WordPress-Engine wurde entfernt. Die Datenbank-Tabellen wurden nicht gelöscht und die Website nicht verändert.'];
}

/** Antworten, die WordPress selbst beendet (z. B. Datenbankfehler als HTML), als JSON ausgeben. */
function rrw_wpe_guard_output(): void
{
    // Schwerer Fehler (z. B. in einem Plugin): sauberer JSON-Fehler statt leerer oder HTML-Seite; der nächste Aufruf startet abgesichert (Bridge::onShutdown/recover)
    register_shutdown_function(static function (): void {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['status' => 'error', 'crashed' => true, 'message' => 'WordPress ist abgestürzt (ein Plugin oder Theme hat einen schweren Fehler ausgelöst). ElvadoPress deaktiviert den Verursacher beim nächsten Aufruf automatisch – bitte die Aktion danach wiederholen.', 'detail' => mb_substr(basename((string)$err['file']) . ': ' . (string)$err['message'], 0, 200)], JSON_UNESCAPED_UNICODE);
        }
    });
    ob_start(static function (string $buf): string {
        if ($buf === '' || json_decode($buf) !== null) {
            return $buf;
        }
        return json_encode(['status' => 'error', 'message' => 'WordPress hat die Anfrage mit einer eigenen Seite beendet (z. B. Datenbankfehler).', 'detail' => mb_substr(trim((string)preg_replace('/\s+/', ' ', strip_tags($buf))), 0, 200)], JSON_UNESCAPED_UNICODE);
    });
}

/** Nach dem Start von WordPress: ein zuvor abgestürzter Plugin-/Theme-Wechsel wird rückgängig gemacht. @return array{when:string,what:string}|null */
function rrw_wpe_after_boot(Engine $e): ?array
{
    return \Elvado\Wp\Bridge::recovering() ? \Elvado\Wp\Bridge::recover($e) : null;
}

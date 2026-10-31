<?php
declare(strict_types=1);
// Klebstoff zwischen API/Oberfläche und der WordPress-Engine (cms/src/Wp/): gemeinsame Engine, Status, Vorbereitung, Core-Installation, Entfernen.
// Doku: cms/docs/ARCHITECTURE-WORDPRESS.md, cms/docs/WORDPRESS-BRIDGE.md

use Elvado\Wp\{Engine, DbConfig, CoreSource, CoreInstaller, Requirements, SqliteIntegration};
use Elvado\Wp\Adapter\NativeAdapter;

require_once __DIR__ . '/../src/autoload.php';

/** Gemeinsame Engine dieser Anfrage. $cmsDir/$dataDir nur in Tests setzen. */
function elvado_wpe(?string $cmsDir = null, ?string $dataDir = null, bool $fresh = false): Engine
{
    static $e = null;
    if ($e === null || $fresh) {
        $cms = $cmsDir ?? dirname(__DIR__);
        if ($dataDir === null && !defined('ELVADO_DATA_DIR') && is_file($cms . '/lib/sites.php')) {   // weitere Website: eigener Engine-Zustand (state/db/keys) unter ihren Daten
            require_once $cms . '/lib/sites.php';
            $dataDir = elvado_site_current() !== '' ? elvado_site_dir('data') : null;
        }
        $e = new Engine($cms, $dataDir ?? (defined('ELVADO_DATA_DIR') ? (string)ELVADO_DATA_DIR : $cms . '/data'));
    }
    return $e;
}

/** Zustand für die Verwaltung (ohne Geheimnisse). */
function elvado_wpe_status(Engine $e, DbConfig $db): array
{
    $s = $e->state();
    $req = Requirements::check($e);
    $native = new NativeAdapter($e->cmsDir(), dirname($e->stateDir()));
    return [
        'engine' => ['front' => $e->front(), 'mode' => $s['mode'], 'version' => $s['version'], 'installed_at' => $s['installed_at'], 'previous' => $s['previous'], 'core_present' => $e->corePath() !== null, 'safe' => $e->safe(), 'incident' => $e->incident(), 'guard' => ($g = $e->guard()) ? ['kind' => $g['kind'], 'id' => $g['id']] : null],
        'db' => ['configured' => $db->redacted(), 'overview' => $db->overview(), 'ready' => !empty($s['db']['ready']), 'server' => (string)($s['db']['server'] ?? ''), 'checked_at' => (string)($s['db']['checked_at'] ?? '')],
        'requirements' => $req,
        'native' => ['name' => $native->name(), 'counts' => $native->counts()],
        'steps' => ['requirements' => $req['ok'], 'core' => $e->corePath() !== null, 'database' => !empty($s['db']['ready']), 'active' => $s['mode'] === 'active'],
    ];
}

/** Aktuelle WordPress-Version und Systemprüfung mit deren Mindestanforderungen (fragt wordpress.org). */
function elvado_wpe_prepare(Engine $e): array
{
    $l = CoreSource::latest();
    $req = Requirements::check($e, $l['ok'] ? ['php' => $l['php'], 'mysql' => $l['mysql']] : []);
    $inst = $e->state()['version'];
    return ['latest' => $l, 'requirements' => $req, 'installed_version' => $inst, 'update_available' => $l['ok'] && $inst !== '' && version_compare($l['version'], $inst, '>')];
}

/** Aktuellen WordPress-Core laden, prüfen und einspielen (Prüfsumme, Sicherheitsprüfung, atomar). */
function elvado_wpe_install_core(Engine $e, string $version, string $localZip = ''): array
{
    $fail = fn(string $m): array => ['ok' => false, 'message' => $m, 'version' => ''];
    if ($localZip !== '') {   // vom Betreiber bereitgestelltes ZIP (z. B. Demo ohne Internet): Version und Prüfsumme kommen aus dem Archiv; Sicherheitsprüfung und atomares Einspielen wie sonst
        $z = new \ZipArchive();
        if (!is_file($localZip) || $z->open($localZip) !== true) {
            return $fail('Das bereitgestellte WordPress-ZIP lässt sich nicht öffnen.');
        }
        preg_match('/\$wp_version\s*=\s*\'([^\']+)\'/', (string)$z->getFromName('wordpress/wp-includes/version.php'), $m);
        $z->close();
        if (empty($m[1]) || preg_match('/^\d+\.\d+(\.\d+)?$/', $m[1]) !== 1) {
            return $fail('Im bereitgestellten ZIP wurde keine WordPress-Version gefunden.');
        }
        $e->protect();
        $r = (new CoreInstaller($e))->install($localZip, $m[1], sha1_file($localZip));
        return $r['ok'] ? ['ok' => true, 'message' => 'WordPress ' . $m[1] . ' wurde geprüft und eingespielt.', 'version' => $m[1]] : $fail($r['message']);
    }
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
function elvado_wpe_pre_install(Engine $e, DbConfig $db, array $cfg): array
{
    if ($e->corePath() === null) {
        return ['ok' => false, 'message' => 'WordPress ist noch nicht eingespielt.', 'prefix' => '', 'server' => ''];
    }
    $sqlite = (string)($cfg['driver'] ?? '') === 'sqlite';
    if ($sqlite) {   // SQLite: zuerst die offizielle Integration einspielen (einmalig, danach nur noch der Drop-in)
        $si = new SqliteIntegration($e);
        if (!$si->present()) {
            $r = $si->install();
            if (!$r['ok']) {
                return ['ok' => false, 'message' => $r['message'], 'prefix' => '', 'server' => ''];
            }
        }
    }
    $t = $db->test($cfg);
    if (!$t['ok']) {
        return ['ok' => false, 'message' => $t['message'], 'prefix' => '', 'server' => ''];
    }
    if (!$sqlite && ($c = $db->prefixConflict((string)($cfg['prefix'] ?? '')))) {
        return ['ok' => false, 'message' => $c, 'prefix' => '', 'server' => ''];
    }
    if ($t['needs_empty']) {
        return ['ok' => false, 'message' => 'In dieser Datenbank gibt es mit diesem Präfix bereits WordPress-Tabellen. Bitte ein anderes Präfix oder eine leere Datenbank verwenden – vorhandene Daten werden nie überschrieben.', 'prefix' => '', 'server' => ''];
    }
    $v = $db->validate($cfg);
    $db->save($cfg);
    return ['ok' => true, 'message' => '', 'prefix' => $v['cfg']['prefix'], 'server' => $t['server']];
}

/** Engine entfernen: Core-Dateien, Zugangsdaten und Zustand. Die Datenbank-Tabellen bleiben unangetastet. */
function elvado_wpe_remove(Engine $e): array
{
    $i = new CoreInstaller($e);
    foreach (glob($e->coreRoot() . '/core-*', GLOB_ONLYDIR) ?: [] as $d) {
        $i->rmTree($d);
    }
    foreach (['db.json', 'keys.json'] as $f) {
        @unlink($e->stateDir() . '/' . $f);
    }
    $si = new SqliteIntegration($e);   // SQLite-Integration und eigener Drop-in gehen mit; die Datenbankdatei bleibt (wie Tabellen bei MySQL)
    if (is_dir($si->dir())) {
        $i->rmTree($si->dir());
    }
    if (is_file($si->dropinFile()) && !$si->foreignDropin()) {
        @unlink($si->dropinFile());
    }
    $e->save(['mode' => 'off', 'core' => '', 'version' => '', 'previous' => [], 'db' => []]);
    $e->log('Engine entfernt');
    return ['ok' => true, 'message' => 'Die WordPress-Engine wurde entfernt. Die Datenbank-Tabellen wurden nicht gelöscht und die Website nicht verändert.'];
}

/** Antworten, die WordPress selbst beendet (z. B. Datenbankfehler als HTML), als JSON ausgeben. */
function elvado_wpe_guard_output(): void
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
function elvado_wpe_after_boot(Engine $e): ?array
{
    return \Elvado\Wp\Bridge::recovering() ? \Elvado\Wp\Bridge::recover($e) : null;
}

/** Tatsachen für Systemstatus und Update-Übersicht sammeln (kein Start von WordPress; Ergebnisse der letzten Update-Suche kommen aus dem Zwischenspeicher). @return array<string,mixed> */
function elvado_wpe_facts(Engine $e, DbConfig $db, string $dataDir, string $cmsDir): array
{
    $ini = static function (string $v): int {
        $v = trim($v);
        if ($v === '-1') {
            return -1;
        }
        $n = (int)$v;
        return match (strtolower(substr($v, -1))) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
    };
    $st = $e->state();
    $cache = is_file($e->stateDir() . '/updates.json') ? json_decode((string)@file_get_contents($e->stateDir() . '/updates.json'), true) : null;
    $cache = is_array($cache) ? $cache : [];
    $f = [
        'cms_version' => function_exists('elvado_cms_version') ? elvado_cms_version() : '',
        'php' => PHP_VERSION, 'memory_limit' => $ini((string)ini_get('memory_limit')),
        'disk_free' => ($d = @disk_free_space($dataDir)) === false ? null : (int)$d,
        'data_writable' => is_dir($dataDir) && is_writable($dataDir),
        'content_writable' => !is_dir($e->contentDir()) || is_writable($e->contentDir()),
        'media_writable' => !is_dir($cmsDir . '/media') || is_writable($cmsDir . '/media'),
        'db_native' => 'JSON-/Markdown-Dateien',
        'rest_ok' => is_file($cmsDir . '/rest.php'),
        'cron' => ['wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON, 'server_cron' => false],
        'opcache' => function_exists('opcache_get_status') && (bool)ini_get('opcache.enable'),
        'engine' => ['mode' => $st['mode'], 'version' => $st['version'], 'safe' => $st['safe'], 'incident' => $st['incident'], 'latest' => (string)($cache['core']['version'] ?? ''), 'latest_checked' => (string)($cache['checked_at'] ?? '')],
        'wp_updates' => is_array($cache['wp'] ?? null) ? ['plugins' => (array)($cache['wp']['plugins'] ?? []), 'themes' => (array)($cache['wp']['themes'] ?? []), 'checked_at' => (string)($cache['wp']['checked_at'] ?? '')] : [],
        'packs' => [],
    ];
    // Zugangsdaten-Dateien der Engine: nicht für andere lesbar
    $bad = [];
    foreach (glob($e->stateDir() . '/*.json') ?: [] as $file) {
        if ((fileperms($file) & 0077) !== 0) {
            $bad[] = basename($file);
        }
    }
    if (is_dir($e->stateDir()) && (fileperms($e->stateDir()) & 0077) !== 0 && !is_file($e->stateDir() . '/.htaccess')) {
        $bad[] = 'Ordner .wp-engine';
    }
    $f['state_perms_bad'] = $bad;
    if ($st['mode'] !== 'off' && !empty($st['db']['ready'])) {   // Verbindung zur WordPress-Datenbank (nur Verbindungstest, kein WordPress-Start)
        $cfg = $db->get();
        if ($cfg !== null) {
            $t = $db->test($cfg);
            $f['engine']['db_ok'] = (bool)$t['ok'];
            $f['engine']['db_server'] = $t['server'];
            $f['engine']['db_message'] = $t['ok'] ? '' : $t['message'];
        }
    }
    // Migration, Layouts
    $jobs = ['migration_failed' => 0, 'migration_partial' => 0, 'overdue_layouts' => 0];
    foreach (glob($e->stateDir() . '/migration/run-*.json') ?: [] as $file) {
        $r = json_decode((string)@file_get_contents($file), true);
        if (is_array($r) && empty($r['rolled_back'])) {
            $jobs['migration_failed'] += ($r['status'] ?? '') === 'failed' ? 1 : 0;
            $jobs['migration_partial'] += ($r['status'] ?? '') === 'partial' ? 1 : 0;
        }
    }
    foreach (glob($dataDir . '/layouts/*.json') ?: [] as $file) {
        $l = json_decode((string)@file_get_contents($file), true);
        $at = is_array($l) ? (string)($l['draft']['publish_at'] ?? '') : '';
        if ($at !== '' && (strtotime($at . ':00') ?: PHP_INT_MAX) <= time()) {
            $jobs['overdue_layouts']++;
        }
    }
    $f['jobs'] = $jobs;
    return $f;
}

/**
 * Sprache des echten WordPress an ElvadoPress angleichen (nur bei gestartetem WordPress): offizielles Sprachpaket von wordpress.org laden und WPLANG setzen.
 * „de“ → de_DE, „en“ → Englisch (ohne Paket). Fehlt das Netz, bleibt WordPress englisch; die Website funktioniert trotzdem.
 * @return array{ok:bool,locale:string,message:string}
 */
function elvado_wpe_language(string $lang): array
{
    $map = ['de' => 'de_DE', 'en' => '', 'fr' => 'fr_FR', 'es' => 'es_ES', 'it' => 'it_IT', 'nl' => 'nl_NL', 'pl' => 'pl_PL', 'tr' => 'tr_TR'];
    $locale = $map[strtolower(substr($lang, 0, 2))] ?? '';
    if (!function_exists('update_option')) {
        return ['ok' => false, 'locale' => $locale, 'message' => 'WordPress läuft nicht.'];
    }
    if ($locale === '') {
        update_option('WPLANG', '');
        return ['ok' => true, 'locale' => 'en_US', 'message' => ''];
    }
    require_once ABSPATH . 'wp-admin/includes/translation-install.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    if (!in_array($locale, get_available_languages(), true)) {
        if (!defined('FS_METHOD')) {
            define('FS_METHOD', 'direct');   // Dateien schreibt der Webserver selbst (wp-content/languages)
        }
        $r = wp_download_language_pack($locale);
        if ($r === false) {
            return ['ok' => false, 'locale' => $locale, 'message' => 'Das Sprachpaket ' . $locale . ' ließ sich nicht laden (wordpress.org nicht erreichbar?). WordPress bleibt vorerst englisch.'];
        }
    }
    update_option('WPLANG', $locale);
    return ['ok' => true, 'locale' => $locale, 'message' => ''];
}

/**
 * Grundeinstellungen des echten WordPress an ElvadoPress angleichen (nur bei gestartetem WordPress): Sprache (mit Sprachpaket) und Zeitzone.
 * Ohne passende Zeitzone hielte WordPress Beiträge, die ElvadoPress in Ortszeit speichert, für „geplant“.
 * @return array{ok:bool,locale:string,message:string}
 */
function elvado_wpe_sync_site(): array
{
    if (!function_exists('update_option')) {
        return ['ok' => false, 'locale' => '', 'message' => 'WordPress läuft nicht.'];
    }
    $sys = function_exists('elvado_system_config') ? elvado_system_config() : [];
    $tz = (string)($sys['timezone'] ?? '');
    if ($tz === '' || !in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
        $tz = (string)(ini_get('date.timezone') ?: 'UTC');
    }
    if (in_array($tz, \DateTimeZone::listIdentifiers(), true) && (string)get_option('timezone_string') !== $tz) {
        update_option('timezone_string', $tz);
        update_option('gmt_offset', '');
    }
    return elvado_wpe_language((string)($sys['language'] ?? 'de') ?: 'de');
}

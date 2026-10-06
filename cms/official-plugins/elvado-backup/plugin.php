<?php
declare(strict_types=1);
// Elvado Backup – Einstiegspunkt (nur als offizielles, unverändertes Plugin ausgeführt).
require_once __DIR__ . '/lib/Engine.php';

use ElvadoPlugin\Backup\Engine;

/** @var \Elvado\Plugin\Context $np */
$e = new Engine($np);

// Die bestehende Backup-Verwaltung (Core) nutzt diese Engine, solange das Plugin aktiv ist
$np->on('backup_create', function ($prev, string $root, bool $media) use ($e, $np) {
    try {
        $r = $e->create($media);
        $e->rotate(max(1, (int)$np->setting('keep')));
        return $r;
    } catch (\Throwable $ex) {
        return $ex->getMessage();   // Text = Fehler: der Core bricht ab, statt mit dem alten Verfahren weiterzumachen
    }
});
$np->on('backup_restore', function ($prev, string $name) use ($e) {
    try {
        $e->restore($name);
        return true;
    } catch (\Throwable $ex) {
        return $ex->getMessage();
    }
});
$np->on('tick', [$e, 'tick']);

$np->api('overview', function () use ($e, $np): array {
    $list = $e->list();
    $total = array_sum(array_column($list, 'size'));
    $last = $e->lastRun();
    $next = $e->nextDue();
    $b = [['type' => 'stats', 'items' => [
        ['label' => 'Backups', 'value' => count($list)],
        ['label' => 'Gesamtgröße', 'value' => number_format($total / 1048576, 1, ',', '.') . ' MB'],
        ['label' => 'Letztes automatisches Backup', 'value' => !empty($last['last_ok']) ? date('d.m.Y H:i', strtotime($last['last_ok'])) : '–'],
        ['label' => 'Nächstes automatisches Backup', 'value' => $next ? date('d.m.Y H:i', $next) : 'kein Zeitplan'],
    ]]];
    if (!empty($last['last_error'])) {
        $b[] = ['type' => 'notice', 'level' => 'bad', 'text' => 'Das letzte automatische Backup ist fehlgeschlagen: ' . $last['last_error']];
    }
    if (!class_exists('ZipArchive')) {
        $b[] = ['type' => 'notice', 'level' => 'bad', 'text' => 'Die PHP-Erweiterung „zip“ fehlt – Backups sind auf diesem Server nicht möglich.'];
    }
    $b[] = ['type' => 'notice', 'level' => 'info', 'text' => 'Passwörter, API-Schlüssel, SMTP-Zugangsdaten und serverlokale Dateien (Zugangsdatei, Datenbank-Konfiguration) sind absichtlich nicht im Backup. Auf demselben Server bleiben sie bei der Wiederherstellung erhalten; auf einem neuen Server trägst du sie neu ein.'];
    $rows = [];
    foreach ($list as $x) {
        $rows[] = ['cells' => [$x['name'], $x['kind'], number_format($x['size'] / 1048576, 2, ',', '.') . ' MB', $x['created_at']], 'actions' => [
            ['label' => 'Herunterladen', 'download' => 'download_file', 'args' => ['name' => $x['name']]],
            ['label' => 'Prüfen', 'call' => 'verify', 'args' => ['name' => $x['name']]],
            ['label' => 'Wiederherstellen', 'call' => 'restore', 'args' => ['name' => $x['name']], 'confirm' => 'Dieses Backup jetzt einspielen? Vorher wird automatisch eine Sicherheitskopie des jetzigen Stands angelegt.'],
            ['label' => 'Löschen', 'call' => 'delete', 'args' => ['name' => $x['name']], 'confirm' => 'Dieses Backup endgültig löschen?'],
        ]];
    }
    $b[] = ['type' => 'table', 'title' => 'Backups', 'columns' => ['Datei', 'Art', 'Größe', 'Erstellt'], 'rows' => $rows, 'empty' => 'Noch keine Backups.'];
    return ['blocks' => $b];
});
$np->api('action_create', function () use ($e, $np): array {
    $r = $e->create((bool)$np->setting('include_media'));
    $e->rotate((int)$np->setting('keep'));
    return ['ok' => true, 'message' => 'Backup ' . $r['name'] . ' erstellt (' . $r['files'] . ' Dateien, ' . number_format($r['size'] / 1048576, 2, ',', '.') . ' MB).'];
});
$np->api('action_verify_all', function () use ($e): array {
    $bad = [];
    $n = 0;
    foreach ($e->list() as $x) {
        $n++;
        $v = $e->verify($x['name']);
        if (!$v['ok'] && $x['kind'] !== 'Backup (Core)') {
            $bad[] = $x['name'] . ': ' . ($v['errors'][0] ?? 'Fehler');
        }
    }
    return ['ok' => !$bad, 'message' => $bad ? count($bad) . ' von ' . $n . ' Backups fehlerhaft.' : 'Alle ' . $n . ' Backups sind in Ordnung (Core-Backups ohne Prüfsummen nur auf Lesbarkeit).', 'details' => $bad];
});
$np->api('verify', function (array $a) use ($e): array { $v = $e->verify((string)($a['name'] ?? '')); return ['ok' => $v['ok'], 'message' => $v['ok'] ? 'In Ordnung – alle Prüfsummen stimmen.' : 'Fehlerhaft: ' . implode(' ', $v['errors'])]; });
$np->api('restore', function (array $a) use ($e): array {
    try {
        $e->restore((string)($a['name'] ?? ''));
    } catch (\Throwable $ex) {
        return ['ok' => false, 'message' => $ex->getMessage()];
    }
    return ['ok' => true, 'message' => 'Backup wiederhergestellt. Eine Sicherheitskopie des vorherigen Stands liegt in der Liste.'];
});
$np->api('delete', fn(array $a) => ['ok' => $e->delete((string)($a['name'] ?? '')), 'message' => 'Gelöscht.']);
$np->api('download_file', function (array $a) use ($e): array {
    $n = basename((string)($a['name'] ?? ''));
    return is_file($e->dir() . '/' . $n) ? ['file' => $e->dir() . '/' . $n, 'name' => $n, 'mime' => 'application/zip'] : ['file' => ''];
});

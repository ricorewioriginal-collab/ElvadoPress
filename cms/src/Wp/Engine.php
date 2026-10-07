<?php
declare(strict_types=1);
// cms/src/Wp/Engine.php – Zustand und Pfade der WordPress-Engine (echter WordPress-Core, von ElvadoPress verwaltet).
//
// Betriebsarten: off (Standard: nichts ändert sich) → installed (Core und Datenbank sind eingerichtet, aber nicht in Gebrauch) → active (ElvadoPress nutzt WordPress als Engine).
// Der Zustand liegt in cms/data/.wp-engine/ (gesperrter Ordner, nicht versioniert): state.json, db.json (Zugangsdaten, nur für den Webserver lesbar), keys.json, log.txt.
// Der WordPress-Core liegt in cms/wp-engine/core-<Version>/ (nicht versioniert, per .htaccess vom Web abgeschirmt); ElvadoPress lädt ihn selbst, WordPress wird nie direkt aufgerufen.

namespace Elvado\Wp;

final class Engine
{
    public const MODES = ['off', 'installed', 'active'];

    private string $stateDir;
    private string $coreRoot;

    public function __construct(private readonly string $cmsDir, string $dataDir)
    {
        $this->stateDir = rtrim($dataDir, '/') . '/.wp-engine';
        $this->coreRoot = rtrim($cmsDir, '/') . '/wp-engine';
    }

    public function cmsDir(): string { return $this->cmsDir; }
    public function stateDir(): string { return $this->stateDir; }
    public function coreRoot(): string { return $this->coreRoot; }
    /** WordPress-Inhalte (Plugins, Themes, Uploads) bleiben im vorhandenen Ordner cms/wp-content. */
    public function contentDir(): string { return rtrim($this->cmsDir, '/') . '/wp-content'; }

    /** @return array{schema:int,mode:string,version:string,core:string,installed_at:string,previous:list<string>,db:array<string,mixed>,safe:bool,incident:?array} */
    public function state(): array
    {
        $raw = is_file($this->stateDir . '/state.json') ? json_decode((string)@file_get_contents($this->stateDir . '/state.json'), true) : null;
        $raw = is_array($raw) ? $raw : [];
        $mode = in_array($raw['mode'] ?? '', self::MODES, true) ? (string)$raw['mode'] : 'off';
        $core = preg_match('/^core-\d+\.\d+(\.\d+)?$/', (string)($raw['core'] ?? '')) ? (string)$raw['core'] : '';
        if ($core === '' || !is_dir($this->coreRoot . '/' . $core)) {
            $core = '';
            $mode = 'off';
        }
        return [
            'schema' => 1,
            'mode' => $mode,
            'version' => $core !== '' ? substr($core, 5) : '',
            'core' => $core,
            'installed_at' => (string)($raw['installed_at'] ?? ''),
            'previous' => array_values(array_filter(array_map('strval', (array)($raw['previous'] ?? [])), fn($c) => preg_match('/^core-\d+\.\d+(\.\d+)?$/', $c) === 1)),
            'db' => is_array($raw['db'] ?? null) ? $raw['db'] : [],
            'safe' => !empty($raw['safe']),
            'incident' => is_array($raw['incident'] ?? null) ? $raw['incident'] : null,
        ];
    }

    /** @param array<string,mixed> $patch */
    public function save(array $patch): void
    {
        $s = array_merge($this->state(), $patch);
        $this->protect();
        $json = json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        $tmp = $this->stateDir . '/state.json.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $json, LOCK_EX) === false || !@rename($tmp, $this->stateDir . '/state.json')) {
            @unlink($tmp);
            throw new \RuntimeException('Der Zustand der WordPress-Engine konnte nicht gespeichert werden.');
        }
    }

    public function mode(): string { return $this->state()['mode']; }
    public function isInstalled(): bool { return $this->mode() !== 'off'; }
    public function isActive(): bool { return $this->mode() === 'active'; }

    /** Ordner des eingerichteten Cores (mit abschließendem Schrägstrich, wie ABSPATH) oder null. */
    public function corePath(): ?string
    {
        $c = $this->state()['core'];
        return $c !== '' && is_file($this->coreRoot . '/' . $c . '/wp-settings.php') ? $this->coreRoot . '/' . $c . '/' : null;
    }

    /** @return array{ok:bool,message:string} */
    public function setMode(string $mode): array
    {
        if (!in_array($mode, self::MODES, true)) {
            return ['ok' => false, 'message' => 'Unbekannte Betriebsart.'];
        }
        $s = $this->state();
        if ($mode !== 'off' && $s['core'] === '') {
            return ['ok' => false, 'message' => 'Die WordPress-Engine ist noch nicht installiert.'];
        }
        if ($mode === 'active' && empty($s['db']['ready'])) {
            return ['ok' => false, 'message' => 'Die Datenbank der WordPress-Engine ist noch nicht eingerichtet.'];
        }
        $this->save(['mode' => $mode]);
        $this->log('Betriebsart: ' . $mode);
        return ['ok' => true, 'message' => ''];
    }

    // ───────── Absturzschutz ─────────
    // Vor einer riskanten Änderung (Plugin aktivieren, Theme wechseln) wird ein „Wächter“ geschrieben. Der nächste Start von WordPress ist ein Probelauf:
    // gelingt er, wird der Wächter gelöscht; stürzt er ab, startet der Folgelauf im abgesicherten Modus und die Änderung wird rückgängig gemacht (Bridge::recover).

    /** @return array{kind:string,id:string,previous:array<string,string>,probing:bool,at:string}|null */
    public function guard(): ?array
    {
        $f = $this->stateDir . '/guard.json';
        $g = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
        if (!is_array($g) || !in_array($g['kind'] ?? '', ['plugin', 'theme'], true)) {
            return null;
        }
        return ['kind' => (string)$g['kind'], 'id' => (string)($g['id'] ?? ''), 'previous' => array_map('strval', (array)($g['previous'] ?? [])), 'probing' => !empty($g['probing']), 'at' => (string)($g['at'] ?? '')];
    }

    /** @param array<string,string> $previous */
    public function guardSet(string $kind, string $id, array $previous = [], bool $probing = false): void
    {
        $this->protect();
        $tmp = $this->stateDir . '/guard.json.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, json_encode(['kind' => $kind, 'id' => $id, 'previous' => $previous, 'probing' => $probing, 'at' => date('c')]), LOCK_EX) === false || !@rename($tmp, $this->stateDir . '/guard.json')) {
            @unlink($tmp);
            throw new \RuntimeException('Der Absturzschutz konnte nicht vorbereitet werden.');
        }
    }

    public function guardClear(): void { @unlink($this->stateDir . '/guard.json'); }

    /** Abgesicherter Modus: WordPress startet ohne Plugins und ohne Theme-Funktionen (Verwaltung bleibt erreichbar). */
    public function safe(): bool { return !empty($this->state()['safe']); }

    public function setSafe(bool $on): void
    {
        $this->save(['safe' => $on]);
        $this->log('Abgesicherter Modus: ' . ($on ? 'an' : 'aus'));
    }

    /** Letzter automatisch behobener Absturz (für die Anzeige). @return array{when:string,what:string}|null */
    public function incident(): ?array
    {
        $i = $this->state()['incident'] ?? null;
        return is_array($i) && isset($i['what']) ? ['when' => (string)($i['when'] ?? ''), 'what' => (string)$i['what']] : null;
    }

    /** Ordner und Schutzdateien anlegen (Webzugriff gesperrt). */
    public function protect(): void
    {
        foreach ([$this->stateDir, $this->coreRoot] as $d) {
            if (!is_dir($d) && !@mkdir($d, 0750, true) && !is_dir($d)) {
                throw new \RuntimeException('Ordner kann nicht angelegt werden: ' . basename($d));
            }
            if (!is_file($d . '/.htaccess')) {
                @file_put_contents($d . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
            }
            if (!is_file($d . '/index.html')) {
                @file_put_contents($d . '/index.html', '');
            }
        }
    }

    public function log(string $line): void
    {
        try {
            $this->protect();
            @file_put_contents($this->stateDir . '/log.txt', date('Y-m-d H:i:s') . ' ' . mb_substr((string)preg_replace('/[\x00-\x1f]+/', ' ', $line), 0, 300) . "\n", FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
        }
    }
}

<?php
declare(strict_types=1);
// cms/src/Wp/SystemStatus.php – Systemstatus und Update-Übersicht der Verwaltung: aus gesammelten Tatsachen („Facts“) werden Einträge mit OK / Warnung / Fehler und verständlichen deutschen Erklärungen.
// Reine Logik ohne Zugriff auf System, Netz oder WordPress (die Tatsachen sammelt elvado_wpe_facts() in cms/lib/wpengine.php) – dadurch vollständig testbar.

namespace Elvado\Wp;

final class SystemStatus
{
    public const MIN_PHP = '8.1';

    /**
     * @param array<string,mixed> $f Tatsachen (fehlende Schlüssel gelten als „unbekannt“)
     * @return array{summary:array{ok:int,warn:int,fail:int,info:int},items:list<array{id:string,group:string,status:string,label:string,detail:string}>}
     */
    public static function build(array $f): array
    {
        $items = [];
        $add = function (string $id, string $group, string $status, string $label, string $detail) use (&$items): void {
            $items[] = ['id' => $id, 'group' => $group, 'status' => $status, 'label' => $label, 'detail' => $detail];
        };
        $mb = static fn(int $b): string => $b >= 1073741824 ? round($b / 1073741824, 1) . ' GB' : round($b / 1048576) . ' MB';

        // ElvadoPress
        $cms = (string)($f['cms_version'] ?? '');
        $u = (array)($f['cms_update'] ?? []);
        if ($cms === '') {
            $add('cms', 'ElvadoPress', 'warn', 'ElvadoPress-Version', 'Die Version ist nicht bekannt.');
        } elseif (!empty($u['available'])) {
            $add('cms', 'ElvadoPress', 'warn', 'ElvadoPress ' . $cms, 'Es gibt eine neuere Version (' . (string)($u['latest'] ?? '?') . '). Unter „Updates“ lässt sie sich einspielen.');
        } else {
            $add('cms', 'ElvadoPress', 'ok', 'ElvadoPress ' . $cms, !empty($u['checked_at']) ? 'Aktuell (zuletzt geprüft: ' . substr((string)$u['checked_at'], 0, 16) . ').' : 'Installiert. Die Suche nach Updates lief noch nicht.');
        }
        // PHP
        $php = (string)($f['php'] ?? PHP_VERSION);
        $add('php', 'System', version_compare($php, self::MIN_PHP, '>=') ? 'ok' : 'fail', 'PHP ' . $php, version_compare($php, self::MIN_PHP, '>=')
            ? 'Erfüllt die Mindestanforderung (PHP ' . self::MIN_PHP . ').' : 'ElvadoPress verlangt mindestens PHP ' . self::MIN_PHP . '. Bitte beim Hoster eine neuere Version einstellen.');
        // Speicher
        $mem = (int)($f['memory_limit'] ?? 0);
        if ($mem === 0) {
            $add('memory', 'System', 'info', 'PHP-Speicher', 'Das Speicherlimit ist nicht bekannt.');
        } elseif ($mem === -1 || $mem >= 134217728) {
            $add('memory', 'System', 'ok', 'PHP-Speicher', $mem === -1 ? 'Unbegrenzt.' : 'Limit ' . $mb($mem) . ' – ausreichend.');
        } else {
            $add('memory', 'System', 'warn', 'PHP-Speicher', 'Limit ' . $mb($mem) . '. Für WordPress und größere Bilder werden mindestens 128 MB empfohlen (memory_limit beim Hoster erhöhen).');
        }
        $free = $f['disk_free'] ?? null;
        if ($free === null) {
            $add('disk', 'System', 'info', 'Festplattenplatz', 'Der freie Platz ist nicht ermittelbar.');
        } else {
            $free = (int)$free;
            $add('disk', 'System', $free < 52428800 ? 'fail' : ($free < 209715200 ? 'warn' : 'ok'), 'Festplattenplatz', 'Frei: ' . $mb($free) . ($free < 209715200 ? '. Das wird knapp – Backups, Medien und Updates brauchen Platz.' : '.'));
        }
        // Dateirechte
        $bad = [];
        foreach (['data_writable' => 'Datenordner (cms/data)', 'content_writable' => 'wp-content', 'media_writable' => 'Medienordner'] as $k => $name) {
            if (array_key_exists($k, $f) && $f[$k] === false) {
                $bad[] = $name;
            }
        }
        $perm = (array)($f['state_perms_bad'] ?? []);
        if ($bad !== []) {
            $add('perms', 'Sicherheit', 'fail', 'Dateirechte', 'Nicht beschreibbar: ' . implode(', ', $bad) . '. Ohne Schreibrechte lässt sich nichts speichern.');
        } elseif ($perm !== []) {
            $add('perms', 'Sicherheit', 'warn', 'Dateirechte', 'Zu offene Rechte bei Zugangs-/Zustandsdateien der Engine: ' . implode(', ', array_slice($perm, 0, 5)) . '. Sie sollten nur für den Server lesbar sein (0600/0700).');
        } else {
            $add('perms', 'Sicherheit', 'ok', 'Dateirechte', 'Ordner sind beschreibbar, Zugangsdaten sind geschützt.');
        }
        // Datenbank (Engine) und Speicher der Website
        $e = (array)($f['engine'] ?? []);
        $mode = (string)($e['mode'] ?? 'off');
        if (!empty($f['db_native'])) {
            $add('storage', 'System', 'ok', 'Speicher der Website', 'Inhalte liegen als ' . (string)$f['db_native'] . ' (Datei-Speicher ist der Standard).');
        }
        if ($mode === 'off') {
            $add('engine', 'WordPress', 'info', 'WordPress-Engine', 'Nicht eingerichtet (Standard). ElvadoPress läuft ohne WordPress-Core; unter System → WordPress-Engine lässt sie sich einrichten.');
        } else {
            $ver = (string)($e['version'] ?? '');
            $lat = (string)($e['latest'] ?? '');
            $stale = $lat !== '' && $ver !== '' && version_compare($lat, $ver, '>');
            $status = !empty($e['incident']) || !empty($e['safe']) ? 'warn' : ($stale ? 'warn' : 'ok');
            $detail = 'Betriebsart: ' . (['installed' => 'eingerichtet (nicht in Gebrauch)', 'active' => 'aktiv'][$mode] ?? $mode);
            if ($stale) {
                $detail .= '. Neu verfügbar: WordPress ' . $lat . '.';
            }
            if (!empty($e['safe'])) {
                $detail .= '. Abgesicherter Modus ist an (nur Kern, keine Plugins) – nach einem Absturz; unter „Plugins & Themes“ wieder ausschalten, wenn die Ursache behoben ist.';
            }
            $add('engine', 'WordPress', $status, 'WordPress ' . $ver, $detail);
            if (array_key_exists('db_ok', $e)) {
                $add('db', 'WordPress', $e['db_ok'] ? 'ok' : 'fail', 'WordPress-Datenbank', $e['db_ok'] ? 'Verbindung steht' . (!empty($e['db_server']) ? ' (' . (string)$e['db_server'] . ')' : '') . '.' : 'Keine Verbindung: ' . (string)($e['db_message'] ?? 'unbekannter Fehler') . ' – Zugangsdaten und Datenbank-Server prüfen.');
            }
        }
        // REST-API und Cron
        if (array_key_exists('rest_ok', $f)) {
            $add('rest', 'System', $f['rest_ok'] ? 'ok' : 'fail', 'ElvadoPress-API (REST)', $f['rest_ok'] ? 'Der stabile Zugang für Apps und eigene Clients (cms/rest.php) ist vorhanden.' : 'cms/rest.php fehlt – Apps/Clients können die API nicht nutzen.');
        }
        $cron = (array)($f['cron'] ?? []);
        $add('cron', 'System', !empty($cron['wp_cron_disabled']) && empty($cron['server_cron']) ? 'warn' : 'info', 'Zeitgesteuerte Aufgaben',
            !empty($cron['wp_cron_disabled']) && empty($cron['server_cron'])
                ? 'WordPress-Cron ist abgeschaltet, aber kein Server-Cron gemeldet: geplante Beiträge/Layouts erscheinen dann nicht pünktlich.'
                : 'Geplante Veröffentlichungen laufen beim nächsten Aufruf der Website (kein Server-Cron nötig). Mit Server-Cron sind sie pünktlicher.');
        // Plugins, Themes
        $p = (array)($f['plugins'] ?? []);
        if ($p !== []) {
            $detail = (int)($p['native_active'] ?? 0) . ' von ' . (int)($p['native_total'] ?? 0) . ' ElvadoPress-Plugins aktiv';
            if (($p['wp_active'] ?? null) !== null) {
                $detail .= ', ' . (int)$p['wp_active'] . ' WordPress-Plugins aktiv';
            }
            $add('plugins', 'Erweiterungen', !empty($p['native_unverified']) ? 'warn' : 'ok', 'Plugins', $detail . (!empty($p['native_unverified']) ? '. Ein Plugin ist nicht mehr unverändert (Prüfsumme) und wird nicht ausgeführt.' : '.'));
        }
        if (!empty($f['theme'])) {
            $add('themes', 'Erweiterungen', 'ok', 'Theme', 'Aktiv: ' . (string)$f['theme'] . '.');
        }
        $packs = (array)($f['packs'] ?? []);
        if ($packs !== []) {
            $add('packs', 'Erweiterungen', 'info', 'Erweiterungspakete', 'Vorhanden: ' . implode(', ', array_map('strval', $packs)) . '.');
        }
        // KI, App Builder, Cache, Hintergrundaufgaben
        if (array_key_exists('ai_providers', $f)) {
            $n = (int)$f['ai_providers'];
            $add('ai', 'Dienste', $n > 0 ? 'ok' : 'info', 'KI-Anbieter', $n > 0 ? $n . ' Anbieter eingerichtet.' : 'Noch kein KI-Anbieter eingerichtet (optional) – unter KI → KI-Zentrale.');
        }
        if (array_key_exists('app_builder', $f)) {
            $add('apps', 'Dienste', $f['app_builder'] ? 'ok' : 'info', 'App-Baukasten', $f['app_builder'] ? 'Vorlagen vorhanden.' : 'Keine App-Vorlage im Paket.');
        }
        $add('cache', 'System', !empty($f['opcache']) ? 'ok' : 'info', 'Zwischenspeicher', !empty($f['opcache']) ? 'PHP-Opcache ist aktiv.' : 'Opcache ist aus – mit Opcache läuft ElvadoPress spürbar schneller (beim Hoster aktivieren).');
        $j = (array)($f['jobs'] ?? []);
        $bad = (int)($j['migration_failed'] ?? 0) + (int)($j['migration_partial'] ?? 0);
        if ($j !== []) {
            $add('jobs', 'System', $bad > 0 || (int)($j['overdue_layouts'] ?? 0) > 0 ? 'warn' : 'ok', 'Hintergrundaufgaben',
                $bad > 0 ? 'Ein Migrationslauf ist nicht abgeschlossen (teilweise/abgebrochen) – unter WordPress-Engine → Migration prüfen oder erneut starten.'
                    : ((int)($j['overdue_layouts'] ?? 0) > 0 ? (int)$j['overdue_layouts'] . ' geplante Layout-Veröffentlichung(en) sind überfällig (werden beim nächsten Aufruf nachgeholt).' : 'Keine offenen Aufgaben.'));
        }
        $sum = ['ok' => 0, 'warn' => 0, 'fail' => 0, 'info' => 0];
        foreach ($items as $i) {
            $sum[$i['status']]++;
        }
        return ['summary' => $sum, 'items' => $items];
    }

    /**
     * Update-Übersicht je Art. Quellen sind zwischengespeicherte Ergebnisse der letzten Suche (kein Netzzugriff hier).
     * @param array<string,mixed> $f
     * @return list<array{id:string,label:string,installed:string,latest:string,available:bool,checked_at:string,detail:string,items:list<array<string,string>>}>
     */
    public static function updates(array $f): array
    {
        $row = static fn(string $id, string $label, string $inst, string $latest, bool $avail, string $at, string $detail, array $items = []): array => [
            'id' => $id, 'label' => $label, 'installed' => $inst, 'latest' => $latest, 'available' => $avail, 'checked_at' => $at, 'detail' => $detail, 'items' => $items,
        ];
        $u = (array)($f['cms_update'] ?? []);
        $e = (array)($f['engine'] ?? []);
        $w = (array)($f['wp_updates'] ?? []);
        $out = [];
        $out[] = $row('cms', 'ElvadoPress', (string)($f['cms_version'] ?? ''), (string)($u['latest'] ?? ''), !empty($u['available']), (string)($u['checked_at'] ?? ''), !empty($u['available']) ? 'Neue Version verfügbar – Einspielen unter System → Version & Update.' : 'Aktuell.');
        $mode = (string)($e['mode'] ?? 'off');
        $lat = (string)($e['latest'] ?? '');
        $ver = (string)($e['version'] ?? '');
        $out[] = $row('wordpress', 'WordPress-Core', $ver, $lat, $mode !== 'off' && $lat !== '' && $ver !== '' && version_compare($lat, $ver, '>'), (string)($e['latest_checked'] ?? ''),
            $mode === 'off' ? 'Engine nicht eingerichtet.' : ($lat === '' ? 'Noch nicht geprüft.' : (version_compare($lat, $ver, '>') ? 'Neue Version bei wordpress.org (Core-Update über die Engine-Verwaltung).' : 'Aktuell.')));
        $np = (array)($f['native_updates'] ?? []);
        $out[] = $row('native_plugins', 'ElvadoPress-Plugins', '', '', $np !== [], '', $np === [] ? 'Alle installierten Plugins sind auf dem Stand der mitgelieferten Bibliothek.' : count($np) . ' Plugin(s) haben eine neuere Fassung in der Bibliothek.', array_map(fn($x) => ['name' => (string)($x['name'] ?? ''), 'installed' => (string)($x['installed'] ?? ''), 'latest' => (string)($x['latest'] ?? '')], $np));
        foreach (['plugins' => ['wp_plugins', 'WordPress-Plugins'], 'themes' => ['wp_themes', 'WordPress-Themes']] as $k => [$id, $label]) {
            $l = (array)($w[$k] ?? []);
            $out[] = $row($id, $label, '', '', $l !== [], (string)($w['checked_at'] ?? ''), $mode !== 'active' ? 'Nur mit aktiver Engine.' : (!isset($w['checked_at']) ? 'Noch nicht geprüft („Jetzt prüfen“).' : ($l === [] ? 'Alle auf dem neuesten Stand.' : count($l) . ' Update(s) verfügbar.')), array_map(fn($x) => ['name' => (string)($x['name'] ?? ''), 'installed' => (string)($x['installed'] ?? ''), 'latest' => (string)($x['latest'] ?? '')], $l));
        }
        $packs = (array)($f['packs'] ?? []);
        $out[] = $row('packs', 'Erweiterungspakete', '', '', false, '', $packs === [] ? 'Keine Pakete installiert.' : 'Pakete werden zusammen mit ElvadoPress aktualisiert: ' . implode(', ', array_map('strval', $packs)) . '.');
        return $out;
    }
}

<?php
declare(strict_types=1);
// Sendeplan-Dienst: holt die Sendungen/Sendezeiten der Sender von laut.fm, räumt sie auf, speichert sie kurz zwischen
// und berechnet "läuft jetzt" und "als Nächstes" serverseitig in der Zeitzone der Sender (Europe/Berlin).
//
// Vorteile gegenüber dem direkten Abruf aus dem Browser:
//  - richtige Zeitzone für alle Besucher (nicht die Uhr des Besuchers),
//  - ein gemeinsamer Zwischenspeicher für Portal, Apps, Assistent: weniger Abrufe bei laut.fm,
//    keine Besucher-IP-Adressen an laut.fm, bei Ausfall von laut.fm gibt es den letzten Stand,
//  - ungültige Einträge werden aussortiert, alle Clients sehen dieselbe Struktur.
// Nicht möglich: laut.fm selbst hält seine Antworten bis zu ca. 1 h 45 min im eigenen Zwischenspeicher; ändert sich dort ein
// Sendeplan, kommt die Änderung auch bei uns erst danach an.

const RRW_SCHEDULE_TTL = 300;          // Sekunden bis zum nächsten Abruf bei laut.fm
const RRW_SCHEDULE_TZ = 'Europe/Berlin';
const RRW_SCHEDULE_DAYS = ['sun','mon','tue','wed','thu','fri','sat'];

function rrw_schedule_dir(string $dataDir): string {
    $d = rtrim($dataDir, '/') . '/.schedule';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    return $d;
}

function rrw_schedule_valid_station(string $id): bool {
    return (bool)preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $id);
}

/** Aufgeräumte Playlists im laut.fm-Format: [{id,name,description,color,airtimes:[{day,hour,end_time}]}] */
function rrw_schedule_clean(array $raw): array {
    $out = [];
    foreach ($raw as $p) {
        if (!is_array($p)) continue;
        $name = trim(strip_tags((string)($p['name'] ?? '')));
        if ($name === '') continue;
        $air = [];
        foreach ((array)($p['airtimes'] ?? []) as $a) {
            if (!is_array($a)) continue;
            $day = strtolower((string)($a['day'] ?? ''));
            $h = $a['hour'] ?? null;
            $e = $a['end_time'] ?? null;
            if (!in_array($day, RRW_SCHEDULE_DAYS, true) || !is_numeric($h) || !is_numeric($e)) continue;
            $h = (int)$h; $e = (int)$e;
            if ($h < 0 || $h > 23 || $e < 0 || $e > 24) continue;
            $air[] = ['day' => $day, 'hour' => $h, 'end_time' => $e];
        }
        $color = (string)($p['color'] ?? '');
        $out[] = [
            'id' => (int)($p['id'] ?? 0),
            'name' => mb_substr($name, 0, 120),
            'description' => mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string)($p['description'] ?? '')))), 0, 400),
            'color' => preg_match('/^#[0-9a-fA-F]{3,8}$/', $color) ? $color : '',
            'airtimes' => $air,
        ];
    }
    return $out;
}

/**
 * Sendeplan eines Senders: ['updated'=>ts,'stale'=>bool,'playlists'=>[...]] oder null, wenn es weder frische noch alte Daten gibt.
 * Frisch bis RRW_SCHEDULE_TTL; ist laut.fm nicht erreichbar, wird der zuletzt gespeicherte Stand geliefert (stale=true).
 */
function rrw_schedule_station(string $id, string $dataDir, ?callable $fetch = null): ?array {
    $id = strtolower(trim($id));
    if (!rrw_schedule_valid_station($id)) return null;
    $file = rrw_schedule_dir($dataDir) . '/st_' . $id . '.json';
    $cached = null;
    if (is_file($file)) {
        $c = json_decode((string)@file_get_contents($file), true);
        if (is_array($c) && isset($c['playlists']) && is_array($c['playlists'])) $cached = $c;
    }
    $age = $cached ? time() - (int)($cached['updated'] ?? 0) : PHP_INT_MAX;
    if ($cached && $age <= RRW_SCHEDULE_TTL) return ['updated' => (int)$cached['updated'], 'stale' => false, 'playlists' => $cached['playlists']];
    // Nach einem Fehlschlag nicht bei jedem Aufruf erneut anfragen, sondern erst nach kurzer Pause
    if ($cached && (int)($cached['retry_after'] ?? 0) > time()) return ['updated' => (int)$cached['updated'], 'stale' => true, 'playlists' => $cached['playlists']];

    $body = $fetch
        ? $fetch($id)
        : (function_exists('rrw_dir_get') ? rrw_dir_get('https://api.laut.fm/station/' . rawurlencode($id) . '/playlists', 8) : null);
    $raw = $body !== null ? json_decode((string)$body, true) : null;
    if (is_array($raw) && array_is_list($raw)) {
        $playlists = rrw_schedule_clean($raw);
        $new = ['updated' => time(), 'playlists' => $playlists];
        @file_put_contents($file, json_encode($new, JSON_UNESCAPED_UNICODE), LOCK_EX);
        return ['updated' => $new['updated'], 'stale' => false, 'playlists' => $playlists];
    }
    if ($cached) {
        // laut.fm nicht erreichbar oder unbrauchbare Antwort: Stand von vorher, nächster Versuch in 60 Sekunden
        $cached['retry_after'] = time() + 60;
        @file_put_contents($file, json_encode($cached, JSON_UNESCAPED_UNICODE), LOCK_EX);
        return ['updated' => (int)($cached['updated'] ?? 0), 'stale' => true, 'playlists' => $cached['playlists']];
    }
    return null;
}

/** Sendungszeiträume als Minuten der Woche (Sonntag 00:00 = 0): [{start, duration, p}] */
function rrw_schedule_intervals(array $playlists): array {
    $iv = [];
    foreach ($playlists as $pi => $p) {
        foreach ((array)($p['airtimes'] ?? []) as $a) {
            $d = array_search($a['day'], RRW_SCHEDULE_DAYS, true);
            if ($d === false) continue;
            $dur = ((int)$a['end_time'] - (int)$a['hour'] + 24) % 24;   // Ende am Folgetag, wenn Ende <= Beginn
            if ($dur === 0) $dur = 24;
            $iv[] = ['start' => $d * 1440 + (int)$a['hour'] * 60, 'duration' => $dur * 60, 'p' => $pi];
        }
    }
    return $iv;
}

/** Start und Ende eines Zeitraums nach der Wanduhr der Sender-Zeitzone (richtig auch bei Sommerzeit-Umstellung). */
function rrw_schedule_slot_info(array $playlists, array $iv, DateTimeImmutable $now, int $m, int $offsetMin): array {
    $p = $playlists[$iv['p']];
    $dayDelta = (int)floor(($m + $offsetMin) / 1440) - (int)floor($m / 1440);   // Tage vor/nach heute (Wanduhr)
    $startMinOfDay = $iv['start'] % 1440;
    $start = $now->setTime(0, 0)->modify(sprintf('%+d days', $dayDelta))->setTime(intdiv($startMinOfDay, 60), $startMinOfDay % 60);
    $endMin = $startMinOfDay + $iv['duration'];
    $end = $start->setTime(0, 0)->modify(sprintf('+%d days', intdiv($endMin, 1440)))->setTime(intdiv($endMin % 1440, 60), $endMin % 60);
    return [
        'id' => (int)$p['id'], 'name' => $p['name'], 'description' => $p['description'], 'color' => $p['color'],
        'starts_at' => $start->format('c'), 'ends_at' => $end->format('c'),
    ];
}

/** Reine Berechnung für einen Zeitpunkt, ohne Zusammenführen: ['now'=>Zeitraum|null,'next'=>Zeitraum|null]. */
function rrw_schedule_raw_now_next(array $playlists, DateTimeImmutable $now): array {
    $week = 7 * 1440;
    $m = (int)$now->format('w') * 1440 + (int)$now->format('G') * 60 + (int)$now->format('i');
    $current = null;
    $next = null;
    $nextIn = PHP_INT_MAX;
    foreach (rrw_schedule_intervals($playlists) as $x) {
        $since = ($m - $x['start'] + $week) % $week;             // Minuten seit Beginn dieses Zeitraums
        if ($since < $x['duration']) {                           // dieser Zeitraum läuft gerade
            if ($current === null) $current = rrw_schedule_slot_info($playlists, $x, $now, $m, -$since);   // wie im Portal: erster passender Eintrag
            continue;                                            // ein laufender Zeitraum ist nie "als Nächstes"
        }
        $until = ($x['start'] - $m + $week) % $week;            // Minuten bis zum nächsten Beginn
        if ($until > 0 && $until < $nextIn) {
            $nextIn = $until;
            $next = rrw_schedule_slot_info($playlists, $x, $now, $m, $until);
        }
    }
    return ['now' => $current, 'next' => $next];
}

/**
 * "Läuft jetzt" und "als Nächstes" für den Zeitpunkt $at (Standard: jetzt) in der Sender-Zeitzone.
 * Dieselbe Sendung, die nahtlos weiterläuft (z. B. 23–0 Uhr und 0–11 Uhr), gilt als eine Sendung.
 */
function rrw_schedule_now_next(array $playlists, ?DateTimeImmutable $at = null): array {
    $tz = new DateTimeZone(RRW_SCHEDULE_TZ);
    $now = ($at ?? new DateTimeImmutable('now', $tz))->setTimezone($tz);
    $now = $now->setTime((int)$now->format('G'), (int)$now->format('i'), 0);
    $r = rrw_schedule_raw_now_next($playlists, $now);
    $current = $r['now'];
    $next = $r['next'];
    if (!$current) return ['now' => null, 'next' => $next];

    // vorwärts: läuft dieselbe Sendung direkt nach dem Ende weiter, verlängert sich die laufende
    for ($i = 0; $i < 8; $i++) {
        $after = rrw_schedule_raw_now_next($playlists, new DateTimeImmutable($current['ends_at'], $tz));
        if (!$after['now'] || $after['now']['id'] !== $current['id'] || $after['now']['ends_at'] <= $current['ends_at']) break;
        $current['ends_at'] = $after['now']['ends_at'];
    }
    $after = rrw_schedule_raw_now_next($playlists, new DateTimeImmutable($current['ends_at'], $tz));
    $next = $after['now'] && $after['now']['id'] !== $current['id'] ? $after['now'] : ($after['next'] && $after['next']['id'] !== $current['id'] ? $after['next'] : $next);

    // rückwärts: lief dieselbe Sendung schon davor, beginnt sie dort
    for ($i = 0; $i < 8; $i++) {
        $before = rrw_schedule_raw_now_next($playlists, (new DateTimeImmutable($current['starts_at'], $tz))->modify('-1 minute'));
        if (!$before['now'] || $before['now']['id'] !== $current['id'] || $before['now']['starts_at'] >= $current['starts_at']) break;
        $current['starts_at'] = $before['now']['starts_at'];
    }
    return ['now' => $current, 'next' => $next];
}

/** Öffentliche Antwort der Aktion "schedule". $stations: Sender-IDs. */
function rrw_schedule_payload(array $stations, string $dataDir): array {
    $tz = new DateTimeZone(RRW_SCHEDULE_TZ);
    $out = [];
    foreach ($stations as $id) {
        $s = rrw_schedule_station((string)$id, $dataDir);
        if ($s === null) continue;
        $out[strtolower((string)$id)] = ['updated' => $s['updated'], 'stale' => $s['stale'], 'playlists' => $s['playlists']] + rrw_schedule_now_next($s['playlists']);
    }
    return ['status' => 'ok', 'tz' => RRW_SCHEDULE_TZ, 'server_time' => (new DateTimeImmutable('now', $tz))->format('c'), 'stations' => $out];
}

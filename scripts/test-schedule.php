<?php
// Prüft cms/lib/schedule.php: Aufräumen, Zwischenspeicher, "jetzt/als Nächstes" in Europe/Berlin.
// Aufruf:  php scripts/test-schedule.php
declare(strict_types=1);
require __DIR__ . '/../cms/lib/schedule.php';

$fixture = json_decode(<<<'JSON'
[{"id":1,"name":"RicoReWi Music & Media","description":"Nonstop <b>24/7</b>","color":"#3498db","airtimes":[{"day":"mon","hour":0,"end_time":11},{"day":"mon","hour":15,"end_time":21},{"day":"mon","hour":23,"end_time":11},{"day":"tue","hour":15,"end_time":21},{"day":"tue","hour":23,"end_time":11},{"day":"sat","hour":23,"end_time":11},{"day":"sun","hour":15,"end_time":21},{"day":"sun","hour":23,"end_time":0}]},
{"id":2,"name":"RicoReWi Radio - Künstlerradio","description":"","color":"zzz","airtimes":[{"day":"mon","hour":11,"end_time":12},{"day":"tue","hour":11,"end_time":12},{"day":"sun","hour":11,"end_time":12}]},
{"id":3,"name":"Giftkind Radio","description":"","color":"","airtimes":[{"day":"mon","hour":12,"end_time":14},{"day":"sun","hour":12,"end_time":14}]},
{"id":4,"name":"SHOW ME YOUR VOICE","description":"","color":"#e91e63","airtimes":[{"day":"mon","hour":21,"end_time":22},{"day":"xxx","hour":1,"end_time":2},{"day":"mon","hour":99,"end_time":2}]},
{"id":5,"name":"Slytheris Radio","description":"","color":"","airtimes":[{"day":"mon","hour":22,"end_time":23}]},
{"id":6,"name":"   ","description":"","color":"","airtimes":[{"day":"mon","hour":3,"end_time":4}]}]
JSON, true);

$fails = 0;
function check(string $name, bool $ok, string $detail = ''): void { global $fails; echo ($ok ? '  ok  ' : 'FAIL  ') . $name . ($ok ? '' : "  -> $detail") . "\n"; if (!$ok) $fails++; }
$tz = new DateTimeZone('Europe/Berlin');
$at = fn(string $s) => new DateTimeImmutable($s, $tz);
$clean = rrw_schedule_clean($fixture);

check('Aufräumen: leere Namen, ungültige Tage/Stunden fliegen raus, Farbe/HTML bereinigt',
    count($clean) === 5 && count($clean[3]['airtimes']) === 1 && $clean[1]['color'] === '' && $clean[0]['description'] === 'Nonstop 24/7',
    json_encode($clean));

$r = rrw_schedule_now_next($clean, $at('2026-10-05 11:30'));   // Montag
check('Montag 11:30: Künstlerradio läuft, als Nächstes Giftkind um 12:00', $r['now']['name'] === 'RicoReWi Radio - Künstlerradio' && $r['next']['name'] === 'Giftkind Radio' && str_starts_with($r['next']['starts_at'], '2026-10-05T12:00:00'), json_encode($r));

$r = rrw_schedule_now_next($clean, $at('2026-10-06 02:00'));   // Dienstag früh: Fortsetzung der Sendung von Montag 23 Uhr
check('Dienstag 02:00: Sendung von Montag 23 Uhr läuft (über Mitternacht), Start Montag 23:00, Ende Dienstag 11:00',
    $r['now']['name'] === 'RicoReWi Music & Media' && str_starts_with($r['now']['starts_at'], '2026-10-05T23:00:00') && str_starts_with($r['now']['ends_at'], '2026-10-06T11:00:00') && $r['next']['name'] === 'RicoReWi Radio - Künstlerradio', json_encode($r));

$r = rrw_schedule_now_next($clean, $at('2026-10-05 21:59'));
check('Montag 21:59: noch SHOW ME YOUR VOICE, 22:00 Slytheris', $r['now']['name'] === 'SHOW ME YOUR VOICE' && $r['next']['name'] === 'Slytheris Radio');
$r = rrw_schedule_now_next($clean, $at('2026-10-05 22:00'));
check('Montag 22:00: Wechsel auf die Minute genau', $r['now']['name'] === 'Slytheris Radio');

$r = rrw_schedule_now_next($clean, $at('2026-10-04 23:30'));   // Sonntag 23:30, Sendung 23-0 geht nahtlos in Montag 0-11 über
check('Sonntag 23:30: nahtlose Fortsetzung zählt als eine Sendung bis Montag 11:00, danach das Künstlerradio',
    $r['now']['name'] === 'RicoReWi Music & Media' && str_starts_with($r['now']['ends_at'], '2026-10-05T11:00:00') && $r['next']['name'] === 'RicoReWi Radio - Künstlerradio', json_encode($r));

$r = rrw_schedule_now_next($clean, $at('2026-10-05 05:00'));
check('Montag 05:00: dieselbe Sendung läuft nahtlos seit Sonntag 23:00 (rückwärts zusammengeführt)', $r['now']['name'] === 'RicoReWi Music & Media' && str_starts_with($r['now']['starts_at'], '2026-10-04T23:00:00'), json_encode($r['now']));

// Sommerzeit-Ende: Sonntag 25.10.2026, 03:00 -> 02:00. Die Sendung von Samstag 23 Uhr beginnt um 23:00 und endet 11:00 (Wanduhr)
$r = rrw_schedule_now_next($clean, $at('2026-10-25 08:00'));
check('Sommerzeit-Ende: Start Samstag 23:00 und Ende Sonntag 11:00 nach der Wanduhr (Offsets +02:00 / +01:00)',
    $r['now']['name'] === 'RicoReWi Music & Media' && $r['now']['starts_at'] === '2026-10-24T23:00:00+02:00' && $r['now']['ends_at'] === '2026-10-25T11:00:00+01:00', json_encode($r['now']));

// Besucher in anderer Zeitzone: dieselbe Antwort, egal in welcher Zeitzone der Zeitpunkt angegeben wird
$utc = new DateTimeImmutable('2026-10-05 09:30:00', new DateTimeZone('UTC'));   // = 11:30 Berlin (Sommerzeit)
check('Zeitpunkt aus anderer Zeitzone (UTC 09:30 = Berlin 11:30) liefert dieselbe Sendung', rrw_schedule_now_next($clean, $utc)['now']['name'] === 'RicoReWi Radio - Künstlerradio');

// Zwischenspeicher und Ausfall von laut.fm
$dir = sys_get_temp_dir() . '/rrw-sched-test-' . bin2hex(random_bytes(4));
@mkdir($dir);
$calls = 0;
$ok = function () use (&$calls, $fixture) { $calls++; return json_encode($fixture); };
$a = rrw_schedule_station('ricorewi', $dir, $ok);
$b = rrw_schedule_station('ricorewi', $dir, $ok);
check('Zwischenspeicher: zweiter Aufruf holt nicht erneut bei laut.fm', $calls === 1 && !$a['stale'] && count($b['playlists']) === 5, "calls=$calls");
$old = function () use ($dir) { $f = $dir . '/.schedule/st_ricorewi.json'; $j = json_decode(file_get_contents($f), true); $j['updated'] = time() - 4000; unset($j['retry_after']); file_put_contents($f, json_encode($j)); };
$old();   // Eintrag veraltet lassen
$down = function () use (&$calls) { $calls++; return null; };
$c = rrw_schedule_station('ricorewi', $dir, $down);
check('laut.fm nicht erreichbar: letzter Stand mit stale=true', $c !== null && $c['stale'] === true && count($c['playlists']) === 5);
$garbage = fn() => '<html>301 Moved</html>';
$old();
$d = rrw_schedule_station('ricorewi', $dir, $garbage);
check('Fehlerseite statt JSON wird nicht gespeichert, alter Stand bleibt', $d !== null && $d['stale'] === true);
$calls0 = $calls;
$e = rrw_schedule_station('ricorewi', $dir, $down);
check('Nach einem Fehlschlag wird 60 Sekunden lang nicht erneut bei laut.fm angefragt', $e['stale'] === true && $calls === $calls0, "calls=$calls, vorher=$calls0");
check('Unbekannter Sender ohne Daten und ohne Stand: null', rrw_schedule_station('gibtesnicht', $dir, $down) === null);
check('Ungültige Sender-ID wird abgewiesen (Pfad-Tricks)', rrw_schedule_station('../x', $dir, $ok) === null && rrw_schedule_station('A b', $dir, $ok) === null);
array_map('unlink', glob($dir . '/schedule/*') ?: []); @rmdir($dir . '/schedule'); @rmdir($dir);

echo "\n" . ($fails ? "$fails Prüfung(en) fehlgeschlagen\n" : "Alle Prüfungen bestanden\n");
exit($fails ? 1 : 0);

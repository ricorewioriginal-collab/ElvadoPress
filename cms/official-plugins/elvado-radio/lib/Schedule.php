<?php
declare(strict_types=1);
// Sendeplan: wöchentliche Sendungen aus einer Textliste; „Jetzt läuft“ und „Danach“ werden aus der Serverzeit berechnet (keine externen Abfragen).

namespace ElvadoPlugin\Radio;

final class Schedule
{
    public const MAX_ENTRIES = 300;
    /** Tage: 1 = Montag … 7 = Sonntag */
    public const DAYS = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'];
    private const ABBR = ['mo' => 1, 'di' => 2, 'mi' => 3, 'do' => 4, 'fr' => 5, 'sa' => 6, 'so' => 7];

    /** "Mo", "Mo-Fr", "Sa,So", "täglich" → Liste der Tage, sonst []. */
    public static function parseDays(string $s): array
    {
        $s = mb_strtolower(trim($s));
        if (in_array($s, ['täglich', 'taeglich', 'tgl', 'tgl.', 'jeden tag'], true)) {
            return [1, 2, 3, 4, 5, 6, 7];
        }
        $out = [];
        foreach (explode(',', $s) as $part) {
            $part = trim($part);
            if (preg_match('/^([a-z]{2})\.?\s*-\s*([a-z]{2})\.?$/', $part, $m) && isset(self::ABBR[$m[1]], self::ABBR[$m[2]])) {
                $a = self::ABBR[$m[1]];
                $b = self::ABBR[$m[2]];
                for ($d = $a; ; $d = $d % 7 + 1) {
                    $out[$d] = true;
                    if ($d === $b) {
                        break;
                    }
                }
            } elseif (preg_match('/^([a-z]{2})\.?$/', $part, $m) && isset(self::ABBR[$m[1]])) {
                $out[self::ABBR[$m[1]]] = true;
            } else {
                return [];
            }
        }
        $days = array_keys($out);
        sort($days);
        return $days;
    }

    /** "18:00-20:00" → [Minute von, Minute bis] oder null (Ende ≤ Anfang = über Mitternacht; 00:00–24:00 erlaubt). */
    public static function parseTimes(string $s): ?array
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})\s*[-–]\s*(\d{1,2}):(\d{2})$/u', trim($s), $m)) {
            return null;
        }
        [$h1, $m1, $h2, $m2] = [(int)$m[1], (int)$m[2], (int)$m[3], (int)$m[4]];
        if ($h1 > 23 || $m1 > 59 || $m2 > 59 || $h2 > 24 || ($h2 === 24 && $m2 !== 0)) {
            return null;
        }
        $a = $h1 * 60 + $m1;
        $b = $h2 * 60 + $m2;
        return $a === $b ? null : [$a, $b];
    }

    /** @return array{0: list<array{days:list<int>,start:int,end:int,title:string,station:string}>, 1: string[]} */
    public static function parse(string $text): array
    {
        $out = [];
        $notes = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $i => $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $f = array_map('trim', explode('|', $line));
            $days = self::parseDays($f[0] ?? '');
            $times = self::parseTimes($f[1] ?? '');
            $title = mb_substr(trim(preg_replace('/[\x00-\x1f<>]/u', '', $f[2] ?? '') ?? ''), 0, 100);
            $station = strtolower($f[3] ?? '');
            if (!$days) {
                $notes[] = 'Zeile ' . ($i + 1) . ': Tag ungültig (z. B. Mo, Mo-Fr, Sa,So oder täglich).';
            } elseif ($times === null) {
                $notes[] = 'Zeile ' . ($i + 1) . ': Zeit ungültig (z. B. 18:00-20:00).';
            } elseif ($title === '') {
                $notes[] = 'Zeile ' . ($i + 1) . ': Titel fehlt.';
            } elseif ($station !== '' && !preg_match('/^[a-z0-9-]{2,30}$/', $station)) {
                $notes[] = 'Zeile ' . ($i + 1) . ': Sender-Kennung ungültig.';
            } elseif (count($out) >= self::MAX_ENTRIES) {
                $notes[] = 'Zeile ' . ($i + 1) . ': Höchstens ' . self::MAX_ENTRIES . ' Einträge.';
            } else {
                $out[] = ['days' => $days, 'start' => $times[0], 'end' => $times[1], 'title' => $title, 'station' => $station];
            }
        }
        return [$out, $notes];
    }

    public static function hhmm(int $min): string
    {
        return sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
    }

    /** Ende einer Sendung als Uhrzeit (über Mitternacht: am Folgetag; 24:00 bleibt 24:00). */
    private static function endClock(int $end): int
    {
        return $end > 1440 ? $end - 1440 : $end;
    }

    /** Einträge der Station (Einträge ohne Kennung gelten für alle) als Läufe: [Tag, von, bis (> 1440 bei Sendungen über Mitternacht), Eintrag]. */
    private static function runs(array $entries, string $station): array
    {
        $runs = [];
        foreach ($entries as $e) {
            if ($station !== '' && $e['station'] !== '' && $e['station'] !== $station) {
                continue;
            }
            foreach ($e['days'] as $d) {
                $runs[] = [$d, $e['start'], $e['end'] > $e['start'] ? $e['end'] : $e['end'] + 1440, $e];
            }
        }
        return $runs;
    }

    /** @return array{now: ?array, next: ?array} aktuelle und nächste Sendung ('from'/'to' in Minuten, 'day' = Starttag). */
    public static function at(array $entries, \DateTimeInterface $when, string $station = ''): array
    {
        $week = 10080;
        $nowAbs = ((int)$when->format('N') - 1) * 1440 + (int)$when->format('G') * 60 + (int)$when->format('i');
        $now = null;
        $nowStart = -PHP_INT_MAX;
        $nowEnd = 0;
        $instances = [];
        foreach (self::runs($entries, $station) as [$d, $a, $b, $e]) {
            foreach ([-1, 0, 1] as $k) {   // Vorwoche/Folgewoche decken den Wochenwechsel ab
                $start = ($d - 1) * 1440 + $a + $k * $week;
                $instances[] = [$start, $start + ($b - $a), $a, $b, $d, $e];
            }
        }
        foreach ($instances as [$st, $en, $a, $b, $d, $e]) {
            if ($st <= $nowAbs && $nowAbs < $en && $st > $nowStart) {
                $now = ['title' => $e['title'], 'station' => $e['station'], 'from' => $a, 'to' => self::endClock($b), 'day' => $d];
                $nowStart = $st;
                $nowEnd = $en;
            }
        }
        $next = null;
        $nextAt = PHP_INT_MAX;
        $after = $now !== null ? max($nowEnd, $nowAbs + 1) : $nowAbs + 1;
        foreach ($instances as [$st, $en, $a, $b, $d, $e]) {
            if ($st >= $after && $st < $nextAt) {
                $next = ['title' => $e['title'], 'station' => $e['station'], 'from' => $a, 'to' => self::endClock($b), 'day' => $d];
                $nextAt = $st;
            }
        }
        return ['now' => $now, 'next' => $next];
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function span(array $x): string
    {
        return self::hhmm($x['from']) . '–' . self::hhmm($x['to']);
    }

    /** Wochenübersicht als Tabelle. */
    public static function renderTable(array $entries, string $station = ''): string
    {
        $byDay = [];
        foreach (self::runs($entries, $station) as [$d, $a, $b, $e]) {
            $byDay[$d][] = [$a, $b, $e['title']];
        }
        if (!$byDay) {
            return '';
        }
        $h = '<table class="elvado-radio-schedule"><caption>Sendeplan</caption><tbody>';
        for ($d = 1; $d <= 7; $d++) {
            if (empty($byDay[$d])) {
                continue;
            }
            usort($byDay[$d], fn($x, $y) => $x[0] <=> $y[0]);
            $h .= '<tr><th scope="row">' . self::DAYS[$d] . '</th><td>';
            $items = [];
            foreach ($byDay[$d] as [$a, $b, $t]) {
                $items[] = '<span class="t">' . self::hhmm($a) . '–' . self::hhmm(self::endClock($b)) . '</span> ' . self::e($t);
            }
            $h .= implode('<br>', $items) . '</td></tr>';
        }
        return $h . '</tbody></table>';
    }

    /** „Jetzt läuft“ und „Danach“; leer, wenn nichts bekannt ist. */
    public static function renderNow(array $entries, \DateTimeInterface $when, string $station = ''): string
    {
        $r = self::at($entries, $when, $station);
        if ($r['now'] === null && $r['next'] === null) {
            return '';
        }
        $h = '<div class="elvado-radio-now">';
        if ($r['now'] !== null) {
            $h .= '<p><strong>Jetzt:</strong> ' . self::e($r['now']['title']) . ' <span class="t">(' . self::span($r['now']) . ')</span></p>';
        }
        if ($r['next'] !== null) {
            $h .= '<p><strong>Danach:</strong> ' . self::e($r['next']['title']) . ' <span class="t">(' . self::DAYS[$r['next']['day']] . ' ' . self::span($r['next']) . ')</span></p>';
        }
        return $h . '</div>';
    }
}

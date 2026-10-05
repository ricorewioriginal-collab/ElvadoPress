<?php
declare(strict_types=1);
// cms/src/Update/Semver.php
//
// Minimaler Versionsvergleich nach Semantic Versioning (MAJOR.MINOR.PATCH[-vorabversion][+build]); führendes „v“ wird ignoriert.
// Eine Vorabversion (1.2.0-beta.1) ist kleiner als die Version selbst (1.2.0).

namespace Elvado\Update;

final class Semver
{
    /** @return array{0:int,1:int,2:int,3:list<string>}|null */
    public static function parse(string $v): ?array
    {
        if (!preg_match('/^v?(\d{1,6})\.(\d{1,6})\.(\d{1,6})(?:-([0-9A-Za-z.-]{1,40}))?(?:\+[0-9A-Za-z.-]{1,40})?$/', trim($v), $m)) {
            return null;
        }
        return [(int)$m[1], (int)$m[2], (int)$m[3], isset($m[4]) && $m[4] !== '' ? explode('.', $m[4]) : []];
    }

    public static function valid(string $v): bool
    {
        return self::parse($v) !== null;
    }

    /** -1, 0, 1 (ungültige Versionen sind kleiner als jede gültige). */
    public static function compare(string $a, string $b): int
    {
        $pa = self::parse($a);
        $pb = self::parse($b);
        if ($pa === null || $pb === null) {
            return $pa === null && $pb === null ? 0 : ($pa === null ? -1 : 1);
        }
        for ($i = 0; $i < 3; $i++) {
            if ($pa[$i] !== $pb[$i]) {
                return $pa[$i] <=> $pb[$i];
            }
        }
        [$x, $y] = [$pa[3], $pb[3]];
        if ($x === [] || $y === []) {
            return $x === $y ? 0 : ($x === [] ? 1 : -1);   // ohne Vorabkennung ist größer
        }
        for ($i = 0, $n = max(count($x), count($y)); $i < $n; $i++) {
            if (!isset($x[$i])) {
                return -1;
            }
            if (!isset($y[$i])) {
                return 1;
            }
            $nx = ctype_digit($x[$i]);
            $ny = ctype_digit($y[$i]);
            $c = $nx && $ny ? (int)$x[$i] <=> (int)$y[$i] : ($nx !== $ny ? ($nx ? -1 : 1) : strcmp($x[$i], $y[$i]));
            if ($c !== 0) {
                return $c <=> 0;
            }
        }
        return 0;
    }

    public static function isPrerelease(string $v): bool
    {
        $p = self::parse($v);
        return $p !== null && $p[3] !== [];
    }
}

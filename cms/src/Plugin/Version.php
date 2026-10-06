<?php
declare(strict_types=1);
// cms/src/Plugin/Version.php – Versionsbedingungen der Plugin-Manifeste ("requires"): ">=1.0.0", "^1.2", "~1.2.3", "1.x", "*" und mit Leerzeichen kombinierte Bedingungen (">=1.0 <2.0").

namespace Elvado\Plugin;

use Elvado\Update\Semver;

final class Version
{
    /** Ist die Bedingung syntaktisch gültig? */
    public static function validConstraint(string $c): bool
    {
        return self::parts($c) !== null;
    }

    /** true, wenn $version die Bedingung erfüllt. Ungültige Bedingung oder Version => false. */
    public static function satisfies(string $version, string $constraint): bool
    {
        $parts = self::parts($constraint);
        if ($parts === null || !Semver::valid($version)) {
            return false;
        }
        foreach ($parts as [$op, $v]) {
            if ($op === '*') {
                continue;
            }
            $cmp = Semver::compare($version, $v);
            $ok = match ($op) {
                '>=' => $cmp >= 0,
                '>' => $cmp > 0,
                '<=' => $cmp <= 0,
                '<' => $cmp < 0,
                '=' => $cmp === 0,
                '^' => $cmp >= 0 && Semver::compare($version, self::bump($v, 'major')) < 0,
                '~' => $cmp >= 0 && Semver::compare($version, self::bump($v, 'minor')) < 0,
                default => false,
            };
            if (!$ok) {
                return false;
            }
        }
        return true;
    }

    /** @return list<array{0:string,1:string}>|null */
    private static function parts(string $constraint): ?array
    {
        $constraint = trim($constraint);
        if ($constraint === '' || $constraint === '*') {
            return [['*', '0.0.0']];
        }
        $out = [];
        foreach (preg_split('/\s+/', $constraint) ?: [] as $p) {
            if (!preg_match('/^(>=|<=|>|<|=|\^|~)?v?(\d{1,6})(?:\.(\d{1,6}|x|\*))?(?:\.(\d{1,6}|x|\*))?(-[0-9A-Za-z.-]{1,40})?$/', $p, $m)) {
                return null;
            }
            $op = $m[1] ?? '';
            $a = $m[2];
            $b = $m[3] ?? '';
            $c = $m[4] ?? '';
            $wild = $b === 'x' || $b === '*' || $c === 'x' || $c === '*';
            if ($b === 'x' || $b === '*') {
                $b = '';
                $c = '';
            }
            if ($c === 'x' || $c === '*') {
                $c = '';
            }
            if ($op === '' && ($wild || $b === '' || $c === '')) {   // "1.x", "1.2" => Bereich
                $op = $b === '' ? '^' : '~';
            }
            $v = $a . '.' . ($b === '' ? '0' : $b) . '.' . ($c === '' ? '0' : $c) . ($m[5] ?? '');
            $out[] = [$op === '' ? '=' : $op, $v];
        }
        return $out ?: null;
    }

    private static function bump(string $v, string $level): string
    {
        $p = Semver::parse($v);
        if ($p === null) {
            return $v;
        }
        return $level === 'major' ? ($p[0] + 1) . '.0.0' : $p[0] . '.' . ($p[1] + 1) . '.0';
    }
}

<?php
declare(strict_types=1);
// cms/src/Plugin/Hooks.php – Erweiterungspunkte (Aktionen und Filter) für native ElvadoPress-Plugins. Ein Fehler in einem Plugin bricht die Seite nie ab: Er wird protokolliert, die übrigen Plugins laufen weiter.

namespace Elvado\Plugin;

final class Hooks
{
    /** @var array<string,array<int,list<callable>>> */
    private static array $map = [];
    /** @var list<string> */
    public static array $errors = [];

    public static function on(string $hook, callable $cb, int $priority = 10): void
    {
        self::$map[$hook][$priority][] = $cb;
    }

    public static function has(string $hook): bool
    {
        return !empty(self::$map[$hook]);
    }

    /** Aktion ausführen (Rückgabewerte werden ignoriert). */
    public static function run(string $hook, mixed ...$args): void
    {
        foreach (self::callbacks($hook) as $cb) {
            try {
                $cb(...$args);
            } catch (\Throwable $e) {
                self::fail($hook, $e);
            }
        }
    }

    /** Filter ausführen: Jedes Plugin erhält den bisherigen Wert (und die Zusatzargumente) und gibt den neuen zurück. */
    public static function filter(string $hook, mixed $value, mixed ...$args): mixed
    {
        foreach (self::callbacks($hook) as $cb) {
            try {
                $value = $cb($value, ...$args);
            } catch (\Throwable $e) {
                self::fail($hook, $e);
            }
        }
        return $value;
    }

    /** Stand sichern/zurückspielen (z. B. um die Hooks eines fehlgeschlagenen Plugin-Ladeversuchs zu verwerfen). */
    public static function snapshot(): array { return self::$map; }

    public static function restore(array $snap): void { self::$map = $snap; }

    public static function reset(): void
    {
        self::$map = [];
        self::$errors = [];
    }

    /** @return list<callable> */
    private static function callbacks(string $hook): array
    {
        if (empty(self::$map[$hook])) {
            return [];
        }
        $by = self::$map[$hook];
        ksort($by);
        return array_merge(...array_values($by));
    }

    private static function fail(string $hook, \Throwable $e): void
    {
        $msg = 'Plugin-Hook ' . $hook . ': ' . get_class($e) . ': ' . mb_substr($e->getMessage(), 0, 200);
        self::$errors[] = $msg;
        if (count(self::$errors) > 50) {
            array_shift(self::$errors);
        }
        error_log('[ElvadoPress] ' . $msg);
    }
}

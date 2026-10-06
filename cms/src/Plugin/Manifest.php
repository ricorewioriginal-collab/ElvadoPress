<?php
declare(strict_types=1);
// cms/src/Plugin/Manifest.php – Prüfung und Bereinigung der plugin.json nativer Plugins.
// Erweitert das bestehende Manifest (id, name, version, author, license, description, hooks …); Plugins ohne "type": "native" bleiben die bisherigen reinen Frontend-/Admin-Plugins (JavaScript, siehe cms/docs/PLUGINS.md).
// Das Feld "official" ist nur eine Behauptung des Pakets – ob ein Plugin als offiziell gilt, entscheidet allein der Server (siehe Catalog).

namespace Elvado\Plugin;

use Elvado\Update\Semver;

final class Manifest
{
    public const ID_RE = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+){0,5}$/';
    private const SETTING_TYPES = ['toggle', 'text', 'textarea', 'number', 'select', 'secret', 'url', 'email'];
    private const UPDATE_SOURCES = ['bundled', 'github', 'manual'];

    /**
     * @return array{0:?array,1:list<string>} [bereinigtes Manifest oder null, Fehlertexte]
     */
    public static function normalize(mixed $raw, string $expectedId = ''): array
    {
        $e = [];
        if (!is_array($raw)) {
            return [null, ['plugin.json ist kein gültiges JSON-Objekt.']];
        }
        $str = static fn(string $k, int $max): string => mb_substr(trim(strip_tags((string)($raw[$k] ?? ''))), 0, $max);
        $id = (string)($raw['id'] ?? '');
        if (!preg_match(self::ID_RE, $id) || strlen($id) < 3 || strlen($id) > 40) {
            $e[] = 'Die Plugin-ID ist ungültig (3–40 Zeichen: Kleinbuchstaben, Ziffern, Bindestriche).';
        } elseif ($expectedId !== '' && $id !== $expectedId) {
            $e[] = 'Die Plugin-ID „' . $id . '“ passt nicht zum Ordner „' . $expectedId . '“.';
        }
        $name = $str('name', 60);
        if ($name === '') {
            $e[] = 'Der Name fehlt.';
        }
        $version = (string)($raw['version'] ?? '');
        if (!Semver::valid($version)) {
            $e[] = 'Die Version fehlt oder ist keine gültige Versionsnummer (z. B. 1.0.0).';
        }
        $homepage = trim((string)($raw['homepage'] ?? ''));
        if ($homepage !== '' && !preg_match('~^https://[^\s"\'<>]{3,200}$~', $homepage)) {
            $e[] = 'Die Homepage muss eine https-Adresse sein.';
            $homepage = '';
        }
        $req = is_array($raw['requires'] ?? null) ? $raw['requires'] : [];
        $core = (string)($req['elvadopress'] ?? '*');
        $php = (string)($req['php'] ?? '*');
        foreach (['elvadopress' => $core, 'php' => $php] as $k => $c) {
            if (!Version::validConstraint($c)) {
                $e[] = 'Die Bedingung requires.' . $k . ' ist ungültig.';
            }
        }
        $deps = self::deps($req['plugins'] ?? [], 'requires.plugins', $id, $e);
        $opt = is_array($raw['optional'] ?? null) ? $raw['optional'] : [];
        $optDeps = self::deps($opt['plugins'] ?? [], 'optional.plugins', $id, $e);
        $tested = trim((string)($raw['tested_up_to'] ?? ''));
        if ($tested !== '' && !preg_match('/^\d{1,6}(\.(\d{1,6}|x))?(\.(\d{1,6}|x))?$/', $tested)) {
            $e[] = 'tested_up_to ist ungültig.';
            $tested = '';
        }
        $caps = [];
        foreach ((array)($raw['capabilities'] ?? []) as $c) {
            if (is_string($c) && preg_match('/^[a-z][a-z0-9_.]{1,40}$/', $c)) {
                $caps[] = $c;
            } else {
                $e[] = 'Eine Berechtigung ("capabilities") ist ungültig.';
            }
        }
        $upd = is_array($raw['update'] ?? null) ? $raw['update'] : [];
        $src = (string)($upd['source'] ?? 'bundled');
        if (!in_array($src, self::UPDATE_SOURCES, true)) {
            $e[] = 'update.source muss bundled, github oder manual sein.';
            $src = 'manual';
        }
        $repo = trim((string)($upd['repo'] ?? ''));
        if ($src === 'github' && !preg_match('~^[A-Za-z0-9_.-]{1,60}/[A-Za-z0-9_.-]{1,100}$~', $repo)) {
            $e[] = 'update.repo fehlt oder ist ungültig (besitzer/repository).';
        }
        $entry = trim((string)($raw['entry'] ?? ''));
        if ($entry !== '' && !preg_match('~^[A-Za-z0-9_-]{1,40}\.php$~', $entry)) {
            $e[] = 'Die Einstiegsdatei ("entry") ist ungültig.';
            $entry = '';
        }
        $adminJs = trim((string)($raw['admin_js'] ?? ''));
        if ($adminJs !== '' && !preg_match('~^[A-Za-z0-9_-]{1,40}\.js$~', $adminJs)) {
            $e[] = 'admin_js ist ungültig.';
            $adminJs = '';
        }
        $icon = (string)($raw['icon'] ?? 'fa-plug');
        if (!preg_match('/^fa-[a-z0-9-]{2,40}$/', $icon)) {
            $icon = 'fa-plug';
        }
        $settings = self::settings($raw['settings'] ?? [], $e);
        $actions = [];
        foreach (array_slice((array)($raw['actions'] ?? []), 0, 12) as $a) {
            if (is_array($a) && preg_match('/^[a-z][a-z0-9_]{1,40}$/', (string)($a['id'] ?? ''))) {
                $actions[] = ['id' => (string)$a['id'], 'label' => mb_substr(trim(strip_tags((string)($a['label'] ?? $a['id']))), 0, 60),
                    'confirm' => mb_substr(trim(strip_tags((string)($a['confirm'] ?? ''))), 0, 200), 'icon' => preg_match('/^fa-[a-z0-9-]{2,40}$/', (string)($a['icon'] ?? '')) ? (string)$a['icon'] : 'fa-bolt'];
            }
        }
        if ($e) {
            return [null, $e];
        }
        return [[
            'id' => $id, 'name' => $name, 'description' => $str('description', 300), 'version' => $version,
            'author' => $str('author', 80), 'homepage' => $homepage, 'license' => $str('license', 40) ?: 'Unknown',
            'type' => 'native', 'official' => !empty($raw['official']), 'category' => $str('category', 30), 'icon' => $icon,
            'requires' => ['elvadopress' => $core, 'php' => $php, 'plugins' => $deps],
            'optional' => ['plugins' => $optDeps], 'tested_up_to' => $tested, 'capabilities' => array_values(array_unique($caps)),
            'update' => ['source' => $src, 'repo' => $repo], 'entry' => $entry,
            'settings' => $settings, 'actions' => $actions, 'admin_js' => $adminJs, 'admin_global' => !empty($raw['admin_global']),
            'settings_page' => !empty($raw['settings_page']) || $settings || $actions || $adminJs !== '',
        ], []];
    }

    /** @param list<string> $e */
    private static function deps(mixed $in, string $label, string $self, array &$e): array
    {
        $out = [];
        foreach (is_array($in) ? $in : [] as $id => $c) {
            $c = (string)$c;
            if (!is_string($id) || !preg_match(self::ID_RE, $id) || $id === $self || !Version::validConstraint($c)) {
                $e[] = 'Eine Abhängigkeit in ' . $label . ' ist ungültig' . (is_string($id) ? ' („' . $id . '“)' : '') . '.';
                continue;
            }
            $out[$id] = $c === '' ? '*' : $c;
        }
        return $out;
    }

    /** @param list<string> $e */
    private static function settings(mixed $in, array &$e): array
    {
        $out = [];
        foreach (array_slice(is_array($in) ? $in : [], 0, 60) as $s) {
            if (!is_array($s) || !preg_match('/^[a-z][a-z0-9_]{0,40}$/', (string)($s['key'] ?? ''))) {
                $e[] = 'Eine Einstellung hat keinen gültigen Schlüssel.';
                continue;
            }
            $type = (string)($s['type'] ?? 'text');
            if (!in_array($type, self::SETTING_TYPES, true)) {
                $e[] = 'Die Einstellung „' . $s['key'] . '“ hat einen unbekannten Typ.';
                continue;
            }
            $o = ['key' => (string)$s['key'], 'type' => $type, 'label' => mb_substr(trim(strip_tags((string)($s['label'] ?? $s['key']))), 0, 80),
                'help' => mb_substr(trim(strip_tags((string)($s['help'] ?? ''))), 0, 300), 'group' => mb_substr(trim(strip_tags((string)($s['group'] ?? ''))), 0, 40),
                'default' => $s['default'] ?? ($type === 'toggle' ? false : ($type === 'number' ? 0 : ''))];
            if ($type === 'select') {
                $o['options'] = [];
                foreach (array_slice((array)($s['options'] ?? []), 0, 40) as $op) {
                    if (is_array($op) && isset($op['value'])) {
                        $o['options'][] = ['value' => (string)$op['value'], 'label' => mb_substr(trim(strip_tags((string)($op['label'] ?? $op['value']))), 0, 80)];
                    }
                }
                if (!$o['options']) {
                    $e[] = 'Die Auswahl „' . $s['key'] . '“ hat keine Optionen.';
                }
            }
            if ($type === 'number') {
                $o['min'] = isset($s['min']) ? (float)$s['min'] : null;
                $o['max'] = isset($s['max']) ? (float)$s['max'] : null;
            }
            if (isset($s['show_if']) && is_string($s['show_if']) && preg_match('/^[a-z][a-z0-9_]{0,40}$/', $s['show_if'])) {
                $o['show_if'] = $s['show_if'];   // nur sichtbar, wenn dieser Schalter an ist
            }
            $out[] = $o;
        }
        return $out;
    }
}

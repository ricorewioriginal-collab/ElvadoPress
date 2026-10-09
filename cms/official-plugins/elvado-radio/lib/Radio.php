<?php
declare(strict_types=1);
// Sender-Verwaltung und Player-Ausgabe. Es werden nur https-Adressen akzeptiert; alle Ausgaben sind maskiert.

namespace ElvadoPlugin\Radio;

final class Radio
{
    public const MAX_STATIONS = 50;

    /** https-Adresse ohne Zugangsdaten und Steuerzeichen, sonst ''. */
    public static function httpsUrl(string $u): string
    {
        $u = trim($u);
        if ($u === '' || strlen($u) > 500 || preg_match('/[\x00-\x20"<>\\\\]/', $u)) {
            return '';
        }
        $p = parse_url($u);
        if (!is_array($p) || ($p['scheme'] ?? '') !== 'https' || ($p['host'] ?? '') === '' || isset($p['user']) || isset($p['pass'])) {
            return '';
        }
        return $u;
    }

    /** @return array{0: array<string, array{id:string,name:string,url:string,logo:string}>, 1: string[]} Sender und Hinweise zu fehlerhaften Zeilen */
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
            $id = strtolower($f[0] ?? '');
            $name = mb_substr(trim(preg_replace('/[\x00-\x1f<>]/u', '', $f[1] ?? '') ?? ''), 0, 80);
            $url = self::httpsUrl($f[2] ?? '');
            $logo = ($f[3] ?? '') !== '' ? self::httpsUrl($f[3]) : '';
            if (!preg_match('/^[a-z0-9-]{2,30}$/', $id)) {
                $notes[] = 'Zeile ' . ($i + 1) . ': Kennung ungültig (2–30 Zeichen: a–z, 0–9, Bindestrich).';
            } elseif ($name === '') {
                $notes[] = 'Zeile ' . ($i + 1) . ': Name fehlt.';
            } elseif ($url === '') {
                $notes[] = 'Zeile ' . ($i + 1) . ': Stream-Adresse muss mit https:// beginnen.';
            } elseif (isset($out[$id])) {
                $notes[] = 'Zeile ' . ($i + 1) . ': Kennung „' . $id . '“ kommt doppelt vor.';
            } elseif (count($out) >= self::MAX_STATIONS) {
                $notes[] = 'Zeile ' . ($i + 1) . ': Höchstens ' . self::MAX_STATIONS . ' Sender.';
            } else {
                $out[$id] = ['id' => $id, 'name' => $name, 'url' => $url, 'logo' => $logo];
                if (($f[3] ?? '') !== '' && $logo === '') {
                    $notes[] = 'Zeile ' . ($i + 1) . ': Logo-Adresse ignoriert (nur https).';
                }
            }
        }
        return [$out, $notes];
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Player-HTML; $a: station (Kennung) oder url + title (+ logo). Leere Zeichenkette, wenn nichts Gültiges angegeben ist. */
    public static function render(array $stations, array $a, bool $showTitle): string
    {
        $id = strtolower(trim((string)($a['station'] ?? '')));
        if ($id !== '' && isset($stations[$id])) {
            $s = $stations[$id];
        } else {
            $url = self::httpsUrl((string)($a['url'] ?? ''));
            if ($url === '') {
                return '';
            }
            $name = mb_substr(trim(preg_replace('/[\x00-\x1f<>]/u', '', (string)($a['title'] ?? '')) ?? ''), 0, 80);
            $s = ['id' => '', 'name' => $name !== '' ? $name : 'Audio-Stream', 'url' => $url, 'logo' => self::httpsUrl((string)($a['logo'] ?? ''))];
        }
        static $css = false;
        $style = '';
        if (!$css) {
            $css = true;
            $style = '<style>.elvado-radio{display:flex;align-items:center;gap:12px;max-width:520px;padding:12px 14px;border:1px solid rgba(128,128,128,.35);border-radius:12px}.elvado-radio img{width:56px;height:56px;object-fit:cover;border-radius:8px}.elvado-radio-body{flex:1;min-width:0}.elvado-radio-title{font-weight:700;margin:0 0 6px}.elvado-radio audio{width:100%}</style>';
        }
        $h = $style . '<div class="elvado-radio" role="group" aria-label="' . self::e($s['name']) . '">';
        if ($s['logo'] !== '') {
            $h .= '<img src="' . self::e($s['logo']) . '" alt="" loading="lazy" width="56" height="56">';
        }
        $h .= '<div class="elvado-radio-body">';
        if ($showTitle) {
            $h .= '<p class="elvado-radio-title">' . self::e($s['name']) . '</p>';
        }
        return $h . '<audio controls preload="none" src="' . self::e($s['url']) . '">Dein Browser unterstützt keine Audio-Wiedergabe.</audio></div></div>';
    }
}

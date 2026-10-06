<?php
declare(strict_types=1);
// Weiterleitungs-Regeln: Schleifen/Ketten erkennen und auflösen, Pfade für geänderte Beitrags-Adressen bilden. Die Regeln selbst liegen wie bisher in cms/data/.tools/redirects.json (Verwaltung unter Werkzeuge).

namespace ElvadoPlugin\Redirects;

use Elvado\Plugin\Context;

final class Rules
{
    public function __construct(private readonly Context $np) {}

    public function npCms(): string { return $this->np->cmsDir(); }

    private function dataDir(): string { return $this->np->cmsDir() . '/data'; }

    private function core(): void
    {
        require_once $this->np->cmsDir() . '/lib/publish.php';
        require_once $this->np->cmsDir() . '/lib/tools.php';
    }

    public function load(): array
    {
        $this->core();
        $d = rrw_tools_read(rrw_tools_dir($this->dataDir()) . '/redirects.json', ['rules' => []]);
        return is_array($d['rules'] ?? null) ? array_values($d['rules']) : [];
    }

    public function save(array $rules): void
    {
        $this->core();
        rrw_tools_write(rrw_tools_dir($this->dataDir()), 'redirects.json', ['rules' => array_values($rules)]);
    }

    /** Pfad, auf den eine Regel zielt (nur interne Ziele; sonst null). */
    private static function internalTarget(array $r): ?string
    {
        $to = (string)($r['to'] ?? '');
        return ($to !== '' && $to[0] === '/' && !str_starts_with($to, '//') && (int)($r['code'] ?? 301) !== 410) ? rrw_redirect_norm_path($to) : null;
    }

    /**
     * Endziel einer Kette und ob sie im Kreis läuft.
     * @return array{final:?string,loop:bool,hops:int}
     */
    public static function resolve(array $rules, string $from): array
    {
        $map = [];
        foreach ($rules as $r) {
            $f = (string)($r['from'] ?? '');
            if ($f !== '' && !str_ends_with($f, '*')) {
                $map[rrw_redirect_norm_path($f)] ??= $r;
            }
        }
        $cur = rrw_redirect_norm_path($from);
        $seen = [$cur => true];
        $hops = 0;
        while (isset($map[$cur])) {
            $t = self::internalTarget($map[$cur]);
            if ($t === null) {
                return ['final' => null, 'loop' => false, 'hops' => $hops];   // externes Ziel oder 410 beendet die Kette
            }
            $hops++;
            if (isset($seen[$t])) {
                return ['final' => $t, 'loop' => true, 'hops' => $hops];
            }
            $seen[$t] = true;
            $cur = $t;
            if ($hops > 50) {
                return ['final' => $cur, 'loop' => true, 'hops' => $hops];
            }
        }
        return ['final' => $cur, 'loop' => false, 'hops' => $hops];
    }

    /**
     * Regeln bereinigen: Schleifen entfernen (die zuletzt hinzugefügte Regel eines Kreises fliegt raus), Ketten auf das Endziel verkürzen.
     * @return array{0:array,1:list<string>} [Regeln, Hinweise]
     */
    public static function normalize(array $rules): array
    {
        $notes = [];
        $guard = 0;
        while ($guard++ < 200) {
            $bad = null;
            foreach ($rules as $i => $r) {
                $f = (string)($r['from'] ?? '');
                if ($f === '' || str_ends_with($f, '*') || self::internalTarget($r) === null) {
                    continue;
                }
                if (self::resolve($rules, $f)['loop']) {
                    $bad = $i;
                }
            }
            if ($bad === null) {
                break;
            }
            $notes[] = 'Schleife entfernt: ' . $rules[$bad]['from'] . ' → ' . $rules[$bad]['to'];
            array_splice($rules, $bad, 1);
        }
        foreach ($rules as $i => $r) {
            $f = (string)($r['from'] ?? '');
            $t = self::internalTarget($r);
            if ($f === '' || str_ends_with($f, '*') || $t === null) {
                continue;
            }
            $res = self::resolve($rules, $t);
            if ($res['hops'] > 0 && $res['final'] !== null && !$res['loop'] && $res['final'] !== rrw_redirect_norm_path($f)) {
                $notes[] = 'Kette verkürzt: ' . $f . ' → ' . $res['final'];
                $rules[$i]['to'] = $res['final'];
            }
        }
        return [array_values($rules), $notes];
    }

    /** Filter redirects_clean (beim Speichern über die Verwaltung). */
    public function guard(array $rules): array
    {
        if (!$this->np->setting('loop_guard')) {
            return $rules;
        }
        $this->core();
        return self::normalize($rules)[0];
    }

    // ---------------------------------------------------------------- geänderte Adressen

    /** Pfad eines Beitrags mit dem Slug nach der WordPress-Permalink-Struktur dieser Website. */
    public function postPath(string $slug, ?string $date): string
    {
        $st = '/%postname%/';
        try {
            if (function_exists('rrw_wp_boot')) {
                rrw_wp_boot(['theme' => false]);
            }
            if (function_exists('rrw_wp_link_structure')) {
                $st = rrw_wp_link_structure();
            }
        } catch (\Throwable) {
        }
        if ($st === '' || !str_contains($st, '%postname%')) {
            return '/' . rawurlencode($slug) . '/';
        }
        $t = strtotime((string)$date) ?: time();
        $path = strtr($st, ['%postname%' => rawurlencode($slug), '%year%' => date('Y', $t), '%monthnum%' => date('m', $t), '%day%' => date('d', $t), '%hour%' => date('H', $t), '%minute%' => date('i', $t), '%second%' => date('s', $t)]);
        if (preg_match('/%[a-z_]+%/', $path)) {   // %category%, %author%, %post_id% … sind hier nicht bekannt: einfache Adresse
            return '/' . rawurlencode($slug) . '/';
        }
        return '/' . trim((string)preg_replace('#/+#', '/', $path), '/') . '/';
    }

    /** Aktion slug_changed: Weiterleitung von der alten auf die neue Adresse anlegen und Ketten/Rückwege bereinigen. */
    public function slugChanged(string $kind, string $old, string $new): void
    {
        if (!$this->np->setting('auto_slug') || $old === '' || $new === '' || $old === $new || !in_array($kind, ['news', 'page'], true)) {
            return;
        }
        $date = null;
        if ($kind === 'news') {
            foreach ((array)json_decode((string)@file_get_contents($this->dataDir() . '/news.json'), true) as $a) {
                if (is_array($a) && ($a['slug'] ?? '') === $new) {
                    $date = (string)($a['published_at'] ?? '');
                    break;
                }
            }
        }
        $from = $kind === 'news' ? $this->postPath($old, $date) : '/' . rawurlencode($old) . '/';
        $to = $kind === 'news' ? $this->postPath($new, $date) : '/' . rawurlencode($new) . '/';
        $this->core();
        $rules = $this->load();
        $nf = rrw_redirect_norm_path($from);
        $nt = rrw_redirect_norm_path($to);
        $rules = array_values(array_filter($rules, static fn($r) => rrw_redirect_norm_path((string)($r['from'] ?? '')) !== $nt || str_ends_with((string)($r['from'] ?? ''), '*')));   // die neue Adresse ist wieder echt (z. B. Rückänderung)
        $rules[] = ['id' => bin2hex(random_bytes(4)), 'from' => $from, 'to' => $to, 'code' => (int)$this->np->setting('auto_code', 301), 'note' => 'automatisch (Adresse geändert)'];
        [$rules] = self::normalize($rules);
        $this->save(self::clean($rules));
        $this->np->log('Weiterleitung ' . $from . ' → ' . $to);
    }

    private static function clean(array $rules): array
    {
        return array_slice($rules, 0, 500);
    }

    /** Auswertung für die Übersicht. */
    public function analyse(): array
    {
        $this->core();
        $rules = $this->load();
        $loops = [];
        $chains = [];
        foreach ($rules as $r) {
            $f = (string)($r['from'] ?? '');
            if ($f === '' || str_ends_with($f, '*') || self::internalTarget($r) === null) {
                continue;
            }
            $res = self::resolve($rules, $f);
            if ($res['loop']) {
                $loops[] = $f;
            } elseif ($res['hops'] > 1) {
                $chains[] = $f . ' → … → ' . $res['final'] . ' (' . $res['hops'] . ' Schritte)';
            }
        }
        $log = rrw_tools_read(rrw_tools_dir($this->dataDir()) . '/404.json', ['items' => []]);
        $items = is_array($log['items'] ?? null) ? $log['items'] : [];
        usort($items, static fn($a, $b) => (int)($b['count'] ?? 0) <=> (int)($a['count'] ?? 0));
        return ['rules' => $rules, 'loops' => $loops, 'chains' => $chains, 'top404' => array_slice($items, 0, 20), 'count404' => count($items)];
    }

    /** Aus einem 404-Eintrag eine Weiterleitung machen. @return array{ok:bool,message:string} */
    public function createFrom404(string $from, string $to, int $code): array
    {
        $this->core();
        $from = trim($from);
        $to = trim($to);
        if ($from === '' || $from[0] !== '/' || str_starts_with($from, '//')) {
            return ['ok' => false, 'message' => 'Die alte Adresse muss mit / beginnen.'];
        }
        if ($to === '' || (($to[0] !== '/' || str_starts_with($to, '//')) && !preg_match('~^https://[^\s]+$~', $to))) {
            return ['ok' => false, 'message' => 'Das Ziel muss ein Pfad (/seite/) oder eine https-Adresse sein.'];
        }
        $code = in_array($code, [301, 302, 307], true) ? $code : 301;
        $rules = $this->load();
        $rules[] = ['id' => bin2hex(random_bytes(4)), 'from' => $from, 'to' => $to, 'code' => $code, 'note' => 'aus 404-Monitor'];
        $before = count($rules);
        $clean = rrw_redirects_clean($rules);   // prüft Form, Duplikate und (mit diesem Plugin) Schleifen
        if (count($clean) < $before) {
            return ['ok' => false, 'message' => 'Die Weiterleitung wurde nicht gespeichert: ungültig, doppelt oder würde eine Schleife erzeugen.'];
        }
        $this->save($clean);
        $this->removeFrom404($from);
        return ['ok' => true, 'message' => 'Weiterleitung ' . $from . ' → ' . $to . ' angelegt.'];
    }

    private function removeFrom404(string $path): void
    {
        $f = rrw_tools_dir($this->dataDir()) . '/404.json';
        $d = rrw_tools_read($f, ['items' => []]);
        $d['items'] = array_values(array_filter((array)($d['items'] ?? []), static fn($i) => ($i['path'] ?? '') !== $path));
        rrw_tools_write(rrw_tools_dir($this->dataDir()), '404.json', $d);
    }
}

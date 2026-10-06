<?php
declare(strict_types=1);
// Interne, datenschutzfreundliche Statistik und optionale externe Dienste (Matomo, Google Analytics).

namespace ElvadoPlugin\Analytics;

use Elvado\Plugin\Context;

final class Stats
{
    private const BOT = '/(bot|crawl|spider|slurp|bing|yandex|baidu|duckduck|facebookexternalhit|embedly|preview|monitor|uptime|lighthouse|headless|python-requests|curl|wget|httpclient|java\/|go-http|libwww|scrapy|pingdom|gtmetrix|semrush|ahrefs|mj12|dotbot)/i';
    private const NOCOUNT_COOKIES = '/^(wordpress_logged_in_|rrw_wp_sess|rrw_sbx|rrw_wp_preview)/';
    private const MAX_PATHS = 300;
    private const MAX_VISITORS = 30000;

    public function __construct(private readonly Context $np) {}

    private function s(string $k): mixed { return $this->np->setting($k); }
    private function dir(string $sub = ''): string { return $this->np->dataDir($sub); }

    // ---------------------------------------------------------------- Zählen

    public static function device(string $ua): string
    {
        if (str_contains($ua, 'ElvadoPressApp/')) {
            return 'app';
        }
        if (preg_match('/(ipad|tablet|kindle|silk|playbook)|(android(?!.*mobile))/i', $ua)) {
            return 'tablet';
        }
        return preg_match('/(mobi|iphone|ipod|android|windows phone|blackberry)/i', $ua) ? 'mobile' : 'desktop';
    }

    public static function isBot(string $ua): bool { return $ua === '' || (bool)preg_match(self::BOT, $ua); }

    private function salt(): string
    {
        $f = $this->dir() . '/salt.txt';
        $s = is_file($f) ? trim((string)file_get_contents($f)) : '';
        if (strlen($s) < 32) {
            $s = bin2hex(random_bytes(24));
            @file_put_contents($f, $s, LOCK_EX);
            @chmod($f, 0600);
        }
        return $s;
    }

    /** Aktion front_response: Seitenaufruf zählen (nur normale HTML-Seiten, nur Besucher). */
    public function count(int $status, string $path, string $contentType = 'text/html'): void
    {
        if (!$this->s('internal') || $status !== 200 || stripos($contentType, 'html') === false || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return;
        }
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (self::isBot($ua) || preg_match('~^/(cms|wp-admin|wp-json)(/|$)~', $path)) {
            return;
        }
        if ($this->s('honor_dnt') && (($_SERVER['HTTP_DNT'] ?? '') === '1' || ($_SERVER['HTTP_SEC_GPC'] ?? '') === '1')) {
            return;
        }
        foreach ($_COOKIE as $k => $v) {
            if (preg_match(self::NOCOUNT_COOKIES, (string)$k)) {
                return;   // angemeldete Redakteure zählen nicht mit
            }
        }
        $today = date('Y-m-d');
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $hash = substr(hash_hmac('sha256', $ip . '|' . $ua, hash_hmac('sha256', $today, $this->salt())), 0, 12);
        $path = mb_substr($path === '' ? '/' : $path, 0, 200);
        $ref = '(direkt)';
        $rh = strtolower((string)parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST));
        $own = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
        if ($rh !== '' && preg_replace('/^www\./', '', $rh) !== preg_replace('/^www\./', '', $own) && preg_match('/^[a-z0-9.-]{3,100}$/', $rh)) {
            $ref = preg_replace('/^www\./', '', $rh);
        } elseif ($rh !== '') {
            $ref = '(intern)';
        }
        $dev = self::device($ua);
        $unique = $this->unique($today, $hash);
        $f = $this->dir('days') . '/' . $today . '.json';
        $h = @fopen($f, 'c+');
        if (!$h) {
            return;
        }
        flock($h, LOCK_EX);
        $d = json_decode((string)stream_get_contents($h), true);
        $d = is_array($d) ? $d : ['v' => 0, 'u' => 0, 'p' => [], 'r' => [], 'd' => []];
        $d['v']++;
        if ($unique) {
            $d['u']++;
        }
        if (isset($d['p'][$path]) || count($d['p']) < self::MAX_PATHS) {
            $d['p'][$path] = ($d['p'][$path] ?? 0) + 1;
        } else {
            $d['p']['(weitere)'] = ($d['p']['(weitere)'] ?? 0) + 1;
        }
        if ($ref !== '(intern)') {
            if (isset($d['r'][$ref]) || count($d['r']) < 100) {
                $d['r'][$ref] = ($d['r'][$ref] ?? 0) + 1;
            }
        }
        $d['d'][$dev] = ($d['d'][$dev] ?? 0) + 1;
        ftruncate($h, 0);
        rewind($h);
        fwrite($h, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($h);
        flock($h, LOCK_UN);
        fclose($h);
    }

    /** true, wenn dieser Tages-Hash heute neu ist. Die Liste der Hashes wird nach dem Tag gelöscht (nur die Zahl bleibt). */
    private function unique(string $day, string $hash): bool
    {
        $f = $this->dir('visitors') . '/' . $day . '.txt';
        $cur = is_file($f) ? (string)file_get_contents($f) : '';
        if (str_contains("\n" . $cur, "\n" . $hash . "\n")) {
            return false;
        }
        if (strlen($cur) > self::MAX_VISITORS * 13) {
            return false;
        }
        @file_put_contents($f, $hash . "\n", FILE_APPEND | LOCK_EX);
        return true;
    }

    /** Tick: alte Tagesdateien und Besucher-Listen löschen. */
    public function tick(): void
    {
        $cut = date('Y-m-d', time() - max(7, (int)$this->s('retention_days')) * 86400);
        foreach (glob($this->dir('days') . '/*.json') ?: [] as $f) {
            if (basename($f, '.json') < $cut) {
                @unlink($f);
            }
        }
        $today = date('Y-m-d');
        foreach (glob($this->dir('visitors') . '/*.txt') ?: [] as $f) {
            if (basename($f, '.txt') < $today) {   // Besucher-Hashes nur für den laufenden Tag
                @unlink($f);
            }
        }
    }

    public function purge(): void
    {
        foreach (glob($this->dir('days') . '/*.json') ?: [] as $f) {
            @unlink($f);
        }
        foreach (glob($this->dir('visitors') . '/*.txt') ?: [] as $f) {
            @unlink($f);
        }
    }

    // ---------------------------------------------------------------- Auswerten

    /** @return array<string,array> Tag => Daten, die letzten $days Tage (heute eingeschlossen) */
    public function days(int $days): array
    {
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', time() - $i * 86400);
            $f = $this->dir('days') . '/' . $d . '.json';
            $x = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
            $out[$d] = is_array($x) ? $x : ['v' => 0, 'u' => 0, 'p' => [], 'r' => [], 'd' => []];
        }
        return $out;
    }

    public static function sum(array $days): array
    {
        $t = ['v' => 0, 'u' => 0, 'p' => [], 'r' => [], 'd' => []];
        foreach ($days as $d) {
            $t['v'] += (int)($d['v'] ?? 0);
            $t['u'] += (int)($d['u'] ?? 0);
            foreach (['p', 'r', 'd'] as $k) {
                foreach ((array)($d[$k] ?? []) as $name => $n) {
                    $t[$k][$name] = ($t[$k][$name] ?? 0) + (int)$n;
                }
            }
        }
        foreach (['p', 'r', 'd'] as $k) {
            arsort($t[$k]);
        }
        return $t;
    }

    // ---------------------------------------------------------------- externe Dienste

    /** Gültige, bestätigte externe Dienste. @return array{matomo:?array{url:string,id:int},ga:?string} */
    public function external(): array
    {
        $out = ['matomo' => null, 'ga' => null];
        if (!$this->s('ack')) {
            return $out;
        }
        $u = trim((string)$this->s('matomo_url'));
        $id = (int)$this->s('matomo_site_id');
        if ($id > 0 && preg_match('#^https://[A-Za-z0-9.-]+(:\d+)?(/[A-Za-z0-9._~/-]*)?$#', $u)) {
            $out['matomo'] = ['url' => rtrim($u, '/') . '/', 'id' => $id];
        }
        $g = trim((string)$this->s('ga_id'));
        if (preg_match('/^G-[A-Z0-9]{4,15}$/', $g)) {
            $out['ga'] = $g;
        }
        return $out;
    }

    /** Filter front_output: Skript der externen Dienste einfügen (clientseitig geschützt durch Einwilligungs-Cookie und DNT, damit der Seiten-Cache für alle gleich bleibt). */
    public function inject(string $html, int $status): string
    {
        if ($status !== 200 || ($p = stripos($html, '</body>')) === false) {
            return $html;
        }
        $ext = $this->external();
        if (!$ext['matomo'] && !$ext['ga']) {
            return $html;
        }
        foreach ($_COOKIE as $k => $v) {
            if (preg_match(self::NOCOUNT_COOKIES, (string)$k)) {
                return $html;   // Redakteure nicht messen
            }
        }
        $js = json_encode(['matomo' => $ext['matomo'], 'ga' => $ext['ga'], 'cookieless' => (bool)$this->s('matomo_cookieless'), 'anonymize' => (bool)$this->s('ga_anonymize'),
            'consent' => preg_match('/^[A-Za-z0-9_.-]{1,60}$/', (string)$this->s('consent_cookie')) ? (string)$this->s('consent_cookie') : '', 'dnt' => (bool)$this->s('honor_dnt')], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
        $script = "<script>(function(c){try{if(c.dnt&&(navigator.doNotTrack=='1'||window.doNotTrack=='1'||navigator.globalPrivacyControl))return;"
            . "if(c.consent){var m=document.cookie.match(new RegExp('(?:^|; )'+c.consent.replace(/[.*+?^\${}()|[\\]\\\\]/g,'\\\\$&')+'=([^;]*)'));if(!m||/^(0|false|no|denied|reject|rejected|none)$/i.test(decodeURIComponent(m[1])))return;}"
            . "if(c.matomo){var _paq=window._paq=window._paq||[];if(c.cookieless)_paq.push(['disableCookies']);_paq.push(['trackPageView']);_paq.push(['enableLinkTracking']);_paq.push(['setTrackerUrl',c.matomo.url+'matomo.php']);_paq.push(['setSiteId',String(c.matomo.id)]);var g=document.createElement('script');g.async=true;g.src=c.matomo.url+'matomo.js';document.head.appendChild(g);}"
            . "if(c.ga){var s=document.createElement('script');s.async=true;s.src='https://www.googletagmanager.com/gtag/js?id='+encodeURIComponent(c.ga);document.head.appendChild(s);window.dataLayer=window.dataLayer||[];window.gtag=function(){dataLayer.push(arguments);};gtag('js',new Date());gtag('config',c.ga,{anonymize_ip:!!c.anonymize});}"
            . "}catch(e){}})(" . $js . ");</script>\n";
        return substr($html, 0, $p) . '<!-- Elvado Analytics (extern, nach Bestätigung) -->' . "\n" . $script . substr($html, $p);
    }
}

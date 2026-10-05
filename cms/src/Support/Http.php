<?php
declare(strict_types=1);
// cms/src/Support/Http.php
//
// Schlanker HTTP-Client auf Basis von nativem cURL (keine externen Bibliotheken) für KI-Gateway, GitHub-Sync und weitere Dienste.
// - TLS-Prüfung immer an, nur http/https, begrenzte Weiterleitungen, Zeit- und Größenlimits.
// - SSRF-Schutz: Hosts müssen öffentlich auflösen (keine privaten/lokalen Adressen); optional zusätzlich eine Host-Allowlist.
// - Tests und Sonderfälle können einen Transport einhängen (Http::useTransport), dann findet kein Netzzugriff statt.

namespace Elvado\Support;

final class Http
{
    /** @var (callable(string,string,array,?string,array):HttpResponse)|null */
    private static $transport = null;

    /** Transport ersetzen (nur Tests): fn(string $method, string $url, array $headers, ?string $body, array $opts): HttpResponse. null = cURL. */
    public static function useTransport(?callable $fn): void
    {
        self::$transport = $fn;
    }

    /**
     * @param list<string>        $headers   Zeilen im Format "Name: Wert"
     * @param array{timeout?:int,max_bytes?:int,hosts?:list<string>,save_to?:string,max_redirects?:int} $opts
     */
    public static function request(string $method, string $url, array $headers = [], ?string $body = null, array $opts = []): HttpResponse
    {
        $method = strtoupper($method);
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if (!preg_match('~^https?://~i', $url) || $host === '') {
            return new HttpResponse(0, '', [], 'Ungültige Adresse');
        }
        $hosts = $opts['hosts'] ?? [];
        if ($hosts !== [] && !in_array($host, $hosts, true)) {
            return new HttpResponse(0, '', [], 'Host nicht erlaubt');
        }
        if (self::$transport !== null) {
            return (self::$transport)($method, $url, $headers, $body, $opts);
        }
        if (!self::publicHost($host)) {
            return new HttpResponse(0, '', [], 'Adresse nicht erlaubt');
        }
        if (!function_exists('curl_init')) {
            return new HttpResponse(0, '', [], 'cURL ist auf diesem Server nicht verfügbar');
        }
        $max = (int)($opts['max_bytes'] ?? 4_000_000);
        $got = 0;
        $respHeaders = [];
        $save = $opts['save_to'] ?? null;
        $fh = null;
        if ($save !== null) {
            $fh = @fopen($save, 'wb');
            if (!$fh) {
                return new HttpResponse(0, '', [], 'Zieldatei nicht beschreibbar');
            }
        }
        $buf = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => (int)($opts['max_redirects'] ?? 3),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => (int)($opts['timeout'] ?? 20),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => array_merge(['User-Agent: ElvadoPress/1.0', 'Accept: application/json'], $headers),
            CURLOPT_HEADERFUNCTION => static function ($c, string $line) use (&$respHeaders): int {
                $p = explode(':', $line, 2);
                if (count($p) === 2) {
                    $respHeaders[strtolower(trim($p[0]))] = trim($p[1]);
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($c, string $data) use (&$got, &$buf, $max, $fh): int {
                $got += strlen($data);
                if ($got > $max) {
                    return 0;   // Abbruch: Antwort zu groß
                }
                if ($fh) {
                    return (int)fwrite($fh, $data);
                }
                $buf .= $data;
                return strlen($data);
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $ok = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = $ok === false ? curl_error($ch) : '';
        $final = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        if ($fh) {
            fclose($fh);
        }
        // Weiterleitung auf eine nicht erlaubte Adresse (z. B. intern) verwerfen
        $fh2 = strtolower((string)parse_url($final, PHP_URL_HOST));
        if ($ok !== false && $fh2 !== '' && $fh2 !== $host && (!self::publicHost($fh2) || ($hosts !== [] && !in_array($fh2, self::redirectHosts($hosts), true)))) {
            if ($save !== null) {
                @unlink($save);
            }
            return new HttpResponse(0, '', [], 'Weiterleitung auf nicht erlaubte Adresse');
        }
        if ($got > $max) {
            $err = 'Antwort zu groß';
        }
        return new HttpResponse($code, $buf, $respHeaders, $err);
    }

    /** @return list<string> Hosts, auf die Weiterleitungen führen dürfen (z. B. codeload.github.com für api.github.com). */
    private static function redirectHosts(array $hosts): array
    {
        return array_merge($hosts, ['codeload.github.com', 'objects.githubusercontent.com']);
    }

    /** Löst der Host auf öffentliche Adressen auf? (Private, lokale und reservierte Bereiche sind gesperrt.) */
    public static function publicHost(string $host): bool
    {
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool)filter_var($host, FILTER_VALIDATE_IP, $flags);
        }
        $ips = @gethostbynamel($host) ?: [];
        if ($ips === []) {
            return true;   // nicht auflösbar: cURL meldet den Fehler selbst
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, $flags)) {
                return false;
            }
        }
        return true;
    }

    /** JSON-POST-Kurzform. */
    public static function postJson(string $url, array $payload, array $headers = [], array $opts = []): HttpResponse
    {
        return self::request('POST', $url, array_merge(['Content-Type: application/json'], $headers), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', $opts);
    }
}

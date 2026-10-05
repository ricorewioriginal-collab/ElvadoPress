<?php
declare(strict_types=1);
// cms/src/Ai/AiGatewayConfig.php
//
// Einstellungen des KI-Gateways: API-Schlüssel, gewählte Modelle, Basis-Adressen, Limits. Datei: cms/data/.ai/gateway.json (gesperrter Ordner).
// Schlüssel verlassen den Server nie (Admin-Sicht meldet nur "gesetzt"). Zusätzlich gelten Schlüssel des KI-Assistenten (site.json → assistant.providers),
// wenn dort derselbe Anbieter (openai, openrouter, gemini) eingerichtet ist – so muss man Schlüssel nicht doppelt eintragen.

namespace Elvado\Ai;

final class AiGatewayConfig
{
    /** @param array<string,mixed> $data */
    private function __construct(private array $data, private readonly string $file, private readonly array $assistantProviders)
    {
    }

    /** @param array<string,mixed> $site Inhalt von site.json (nur für Schlüssel des KI-Assistenten) */
    public static function load(string $dataDir, array $site = []): self
    {
        $file = rtrim($dataDir, '/') . '/.ai/gateway.json';
        $raw = is_file($file) ? json_decode((string)@file_get_contents($file), true) : null;
        $ap = [];
        foreach ((array)($site['assistant']['providers'] ?? []) as $p) {
            if (is_array($p) && ($p['id'] ?? '') !== '') {
                $ap[strtolower((string)$p['id'])] = trim((string)($p['api_key'] ?? ''));
            }
        }
        return new self(self::clean(is_array($raw) ? $raw : []), $file, $ap);
    }

    /** @return array<string,mixed> */
    private static function clean(array $in): array
    {
        $out = ['providers' => [], 'rate_limit' => max(5, min(1000, (int)($in['rate_limit'] ?? 60))), 'default_provider' => ''];
        $cat = AiGatewayService::catalog();
        foreach ($cat as $id => $def) {
            $p = is_array($in['providers'][$id] ?? null) ? $in['providers'][$id] : [];
            $key = trim((string)($p['api_key'] ?? ''));
            $base = rtrim(trim((string)($p['base_url'] ?? '')), '/');
            $model = trim((string)($p['model'] ?? ''));
            $out['providers'][$id] = [
                'api_key' => preg_match('/^[\x21-\x7E]{8,400}$/', $key) ? $key : '',
                'base_url' => ($def['base_editable'] && preg_match('~^https://[A-Za-z0-9.-]+(:\d+)?(/[^\s"\'<>]*)?$~', $base)) ? $base : '',
                'model' => preg_match('~^[\w.:/@+-]{1,120}$~u', $model) ? $model : '',
                'enabled' => !array_key_exists('enabled', $p) || !empty($p['enabled']),
            ];
        }
        $dp = (string)($in['default_provider'] ?? '');
        $out['default_provider'] = isset($cat[$dp]) ? $dp : '';
        return $out;
    }

    /** Schlüssel des Anbieters: Gateway-Einstellung, sonst gleichnamiger Anbieter des KI-Assistenten. */
    public function apiKey(string $id): string
    {
        $k = (string)($this->data['providers'][$id]['api_key'] ?? '');
        if ($k !== '') {
            return $k;
        }
        $map = ['google' => 'gemini'];
        $alt = $map[$id] ?? $id;
        return in_array($alt, ['openai', 'openrouter', 'gemini', 'deepseek'], true) ? ($this->assistantProviders[$alt] ?? '') : '';
    }

    public function baseUrl(string $id): string
    {
        $def = AiGatewayService::catalog()[$id];
        return ($this->data['providers'][$id]['base_url'] ?? '') ?: $def['base_url'];
    }

    public function model(string $id): string
    {
        return ($this->data['providers'][$id]['model'] ?? '') ?: AiGatewayService::catalog()[$id]['model'];
    }

    public function enabled(string $id): bool
    {
        return !empty($this->data['providers'][$id]['enabled']);
    }

    public function rateLimit(): int
    {
        return (int)$this->data['rate_limit'];
    }

    public function defaultProvider(): string
    {
        return (string)$this->data['default_provider'];
    }

    /**
     * Speichern. Schlüssel: Text = setzen, '' oder fehlend = behalten, '__clear__' = entfernen.
     * @param array<string,mixed> $in
     */
    public function save(array $in): void
    {
        $new = $this->data;
        foreach (AiGatewayService::catalog() as $id => $def) {
            $p = is_array($in['providers'][$id] ?? null) ? $in['providers'][$id] : null;
            if ($p === null) {
                continue;
            }
            $key = trim((string)($p['api_key'] ?? ''));
            if ($key === '__clear__') {
                $p['api_key'] = '';
            } elseif ($key === '') {
                $p['api_key'] = $new['providers'][$id]['api_key'] ?? '';
            } elseif (!preg_match('/^[\x21-\x7E]{8,400}$/', $key)) {
                throw new AiGatewayException('Der Schlüssel für ' . $def['label'] . ' enthält unzulässige Zeichen oder ist zu kurz.', 400);
            }
            $new['providers'][$id] = array_merge($new['providers'][$id] ?? [], $p);
        }
        foreach (['rate_limit', 'default_provider'] as $k) {
            if (array_key_exists($k, $in)) {
                $new[$k] = $in[$k];
            }
        }
        $new = self::clean($new);
        $dir = dirname($this->file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new AiGatewayException('Der Einstellungsordner ist nicht beschreibbar.', 500);
        }
        if (!is_file($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "# Laufzeitdaten: nie öffentlich abrufbar\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
        }
        $tmp = $this->file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false || !@rename($tmp, $this->file)) {
            @unlink($tmp);
            throw new AiGatewayException('Einstellungen konnten nicht gespeichert werden.', 500);
        }
        @chmod($this->file, 0600);
        $this->data = $new;
    }

    /** Admin-Sicht ohne Schlüssel. @return array<string,mixed> */
    public function adminView(): array
    {
        $out = ['rate_limit' => $this->rateLimit(), 'default_provider' => $this->defaultProvider(), 'providers' => []];
        foreach (AiGatewayService::catalog() as $id => $def) {
            $own = (string)($this->data['providers'][$id]['api_key'] ?? '') !== '';
            $out['providers'][] = [
                'id' => $id, 'label' => $def['label'], 'group' => $def['group'], 'note' => $def['note'], 'verified' => $def['verified'],
                'free' => $def['free'], 'base_editable' => $def['base_editable'],
                'base_url' => $this->baseUrl($id), 'model' => $this->model($id), 'models' => $def['models'],
                'enabled' => $this->enabled($id), 'has_key' => $this->apiKey($id) !== '', 'key_from_assistant' => !$own && $this->apiKey($id) !== '',
            ];
        }
        return $out;
    }
}

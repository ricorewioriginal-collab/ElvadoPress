<?php
declare(strict_types=1);
// cms/src/Ai/AiGatewayConfig.php
//
// Zentrale KI-Konfiguration („KI-Zentrale“): API-Schlüssel, Modelle, Basis-Adressen, eigene Anbieter, Einsatzzwecke, Limits. Datei: cms/data/.ai/gateway.json (gesperrter Ordner).
// Schlüssel verlassen den Server nie (Admin-Sicht meldet nur "gesetzt"). Der KI-Assistent (cms/lib/assistant.php) bezieht seine Schlüssel und eigenen Anbieter von hier;
// Schlüssel, die früher im Assistenten (site.json → assistant.providers) eingetragen wurden, gelten weiter, bis sie übernommen werden (migrateAssistantKeys()).

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

    public const PURPOSES = ['content' => 'Beiträge & Texte im Editor', 'builder' => 'Website-Generator', 'developer' => 'KI-Entwickler (Themes, Widgets, Plugins)', 'media' => 'Alt-Texte für Bilder (Anbieter mit Bildverständnis)'];

    /** @return list<array<string,mixed>> eigene OpenAI-kompatible Anbieter (ohne Schlüssel) */
    public function customProviders(): array
    {
        return $this->data['custom'];
    }

    /** Anbieter-Katalog inklusive eigener Anbieter. @return array<string,array<string,mixed>> */
    public function catalog(): array
    {
        return AiGatewayService::catalog($this->data['custom']);
    }

    /** @return array<string,mixed> */
    private static function clean(array $in): array
    {
        $out = ['providers' => [], 'custom' => [], 'purposes' => [], 'rate_limit' => max(5, min(1000, (int)($in['rate_limit'] ?? 60))), 'default_provider' => ''];
        $builtin = AiGatewayService::catalog();
        foreach (array_slice(is_array($in['custom'] ?? null) ? $in['custom'] : [], 0, 10) as $c) {
            if (!is_array($c)) {
                continue;
            }
            $id = preg_replace('/[^a-z0-9_-]/', '', strtolower((string)($c['id'] ?? '')));
            $base = rtrim(trim((string)($c['base_url'] ?? '')), '/');
            $model = trim((string)($c['model'] ?? ''));
            if (!preg_match('/^[a-z][a-z0-9_-]{1,30}$/', (string)$id) || isset($builtin[$id]) || isset($out['custom'][$id]) || !self::baseUrlOk($base) || !preg_match('~^[\w.:/@+-]{1,120}$~u', $model)) {
                continue;
            }
            $models = $c['models'] ?? [];
            if (is_string($models)) {
                $models = preg_split('/[\r\n,;]+/', $models) ?: [];
            }
            $out['custom'][$id] = ['id' => $id, 'label' => mb_substr(trim((string)($c['label'] ?? '')), 0, 80) ?: $id, 'base_url' => $base, 'model' => $model,
                'models' => array_slice(array_values(array_unique(array_filter(array_map(static fn($m) => trim((string)$m), (array)$models), static fn($m) => $m !== '' && $m !== $model && preg_match('~^[\w.:/@+-]{1,120}$~u', $m)))), 0, 20),
                'needs_key' => !array_key_exists('needs_key', $c) || !empty($c['needs_key']), 'free' => !empty($c['free'])];
        }
        $out['custom'] = array_values($out['custom']);
        $cat = AiGatewayService::catalog($out['custom']);
        foreach ($cat as $id => $def) {
            $p = is_array($in['providers'][$id] ?? null) ? $in['providers'][$id] : [];
            $key = trim((string)($p['api_key'] ?? ''));
            $base = rtrim(trim((string)($p['base_url'] ?? '')), '/');
            $model = trim((string)($p['model'] ?? ''));
            $out['providers'][$id] = [
                'api_key' => preg_match('/^[\x21-\x7E]{8,400}$/', $key) ? $key : '',
                'base_url' => ($def['base_editable'] && !$def['custom'] && self::baseUrlOk($base)) ? $base : '',
                'model' => preg_match('~^[\w.:/@+-]{1,120}$~u', $model) ? $model : '',
                'enabled' => array_key_exists('enabled', $p) ? !empty($p['enabled']) : (bool)$def['needs_key'],   // Dienste ohne Schlüssel (Community) sind erst nach ausdrücklichem Einschalten aktiv
            ];
        }
        $dp = (string)($in['default_provider'] ?? '');
        $out['default_provider'] = isset($cat[$dp]) ? $dp : '';
        foreach (self::PURPOSES as $k => $_) {
            $v = (string)($in['purposes'][$k] ?? '');
            $out['purposes'][$k] = isset($cat[$v]) ? $v : '';
        }
        return $out;
    }

    /** https, oder http nur für den eigenen Rechner (Ollama, LM Studio). */
    private static function baseUrlOk(string $u): bool
    {
        return (bool)(preg_match('~^https://[A-Za-z0-9.-]+(:\d+)?(/[^\s"\'<>]*)?$~', $u) || preg_match('~^http://(localhost|127\.0\.0\.1|\[::1\])(:\d{2,5})?(/[^\s"\'<>]*)?$~i', $u));
    }

    /** Schlüssel des Anbieters: zentrale Einstellung, sonst (Altbestand) gleicher Anbieter im KI-Assistenten. */
    public function apiKey(string $id): string
    {
        $k = (string)($this->data['providers'][$id]['api_key'] ?? '');
        return $k !== '' ? $k : (string)($this->assistantProviders[self::assistantId($id)] ?? '');
    }

    /** 'central' (hier eingetragen), 'assistant' (Altbestand im KI-Assistenten, noch nicht übernommen) oder ''. */
    public function keySource(string $id): string
    {
        if ((string)($this->data['providers'][$id]['api_key'] ?? '') !== '') {
            return 'central';
        }
        return ($this->assistantProviders[self::assistantId($id)] ?? '') !== '' ? 'assistant' : '';
    }

    /** Kennung des Anbieters im KI-Assistenten (Google heißt dort „gemini“). */
    public static function assistantId(string $id): string
    {
        return $id === 'google' ? 'gemini' : $id;
    }

    /** Zentrale Kennung zu einer Assistenten-Kennung. */
    public static function centralId(string $assistantId): string
    {
        return $assistantId === 'gemini' ? 'google' : $assistantId;
    }

    /** Schlüssel aus dem KI-Assistenten (Altbestand) in die zentrale Konfiguration übernehmen; vorhandene zentrale Schlüssel bleiben. @return list<string> übernommene Anbieter */
    public function migrateAssistantKeys(): array
    {
        $moved = [];
        $new = $this->data;
        $cat = $this->catalog();
        foreach ($this->assistantProviders as $aid => $key) {
            $id = self::centralId($aid);
            if ($key !== '' && isset($cat[$id]) && (string)($new['providers'][$id]['api_key'] ?? '') === '' && preg_match('/^[\x21-\x7E]{8,400}$/', $key)) {
                $new['providers'][$id]['api_key'] = $key;
                $moved[] = $id;
            }
        }
        if ($moved) {
            $this->write(self::clean($new));
        }
        return $moved;
    }

    /** Nur der zentral eingetragene Schlüssel (ohne Altbestand aus dem KI-Assistenten). */
    public function ownKey(string $id): string
    {
        return (string)($this->data['providers'][$id]['api_key'] ?? '');
    }

    public function baseUrl(string $id): string
    {
        $def = $this->catalog()[$id];
        return ($this->data['providers'][$id]['base_url'] ?? '') ?: $def['base_url'];
    }

    public function model(string $id): string
    {
        return ($this->data['providers'][$id]['model'] ?? '') ?: $this->catalog()[$id]['model'];
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

    /** Anbieter für einen Einsatzzweck (content | builder | developer); leer = Standard-Anbieter bzw. erster nutzbarer. */
    public function purposeProvider(string $purpose): string
    {
        return (string)($this->data['purposes'][$purpose] ?? '') ?: $this->defaultProvider();
    }

    /**
     * Speichern. Schlüssel: Text = setzen, '' oder fehlend = behalten, '__clear__' = entfernen.
     * @param array<string,mixed> $in
     */
    public function save(array $in): void
    {
        $new = $this->data;
        if (array_key_exists('custom', $in) && is_array($in['custom'])) {
            $new['custom'] = $in['custom'];   // Schlüssel eigener Anbieter stehen unter providers[<id>]
            $new = array_replace($new, ['custom' => self::clean(['custom' => $in['custom']])['custom']]);
        }
        foreach (AiGatewayService::catalog($new['custom']) as $id => $def) {
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
        if (is_array($in['purposes'] ?? null)) {
            $new['purposes'] = $in['purposes'];
        }
        $this->write(self::clean($new));
    }

    /** @param array<string,mixed> $new bereits bereinigt */
    private function write(array $new): void
    {
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
        $out = ['rate_limit' => $this->rateLimit(), 'default_provider' => $this->defaultProvider(), 'purposes' => $this->data['purposes'], 'purpose_labels' => self::PURPOSES, 'providers' => [], 'legacy_keys' => []];
        foreach ($this->catalog() as $id => $def) {
            $src = $this->keySource($id);
            $out['providers'][] = [
                'id' => $id, 'label' => $def['label'], 'group' => $def['group'], 'note' => $def['note'], 'verified' => $def['verified'], 'kind' => $def['kind'],
                'free' => $def['free'], 'base_editable' => $def['base_editable'], 'custom' => $def['custom'], 'needs_key' => $def['needs_key'], 'vision' => in_array($id, AiGatewayService::VISION, true),
                'base_url' => $this->baseUrl($id), 'model' => $this->model($id), 'models' => $def['models'],
                'enabled' => $this->enabled($id), 'has_key' => $src !== '', 'key_source' => $src, 'key_from_assistant' => $src === 'assistant',
                'usable' => $this->enabled($id) && ($src !== '' || !$def['needs_key']),
            ];
            if ($src === 'assistant') {
                $out['legacy_keys'][] = $id;
            }
        }
        return $out;
    }
}

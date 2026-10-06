<?php
declare(strict_types=1);
// cms/src/Ai/AiGatewayService.php
//
// Einheitliches KI-Gateway für Inhalte im CMS-Editor (Texte, Übersetzungen, strukturierte JSON-Layouts) über mehrere Anbieter – per nativem cURL
// (Elvado\Support\Http), ohne externe SDKs:
//   • EvoLink            OpenAI-kompatibel, intelligentes Routing ("evolink-auto")           [Basis-Adresse einstellbar, Doku des Anbieters prüfen]
//   • OpenAI             GPT-Serie            (Chat Completions)
//   • Anthropic          Claude-Serie         (Messages-API, eigenes Format)
//   • Google             Gemini-Serie         (generateContent, eigenes Format)
//   • OpenRouter         u. a. kostenlose Open-Source-Modelle (OpenAI-kompatibel)
//   • DeepSeek           günstig (OpenAI-kompatibel)
// Sicherheit: Schlüssel nur serverseitig, feste Basis-Adressen je Anbieter (nur EvoLink ist änderbar, nur https), Eingabelängen und Token-Obergrenzen,
// Zeit- und Größenlimits, Ratenbegrenzung je Benutzer, Protokoll ohne Prompt-Text (ai_logs). Fehlermeldungen enthalten nie Schlüssel oder Rohantworten.
// Der Code für die Oberfläche steht in frontend/src/components/AiContentAssistant.tsx, die API-Aktionen ai_* in cms/api.php.

namespace Elvado\Ai;

use Elvado\Repository\AiLogRepository;
use Elvado\Support\Http;
use Elvado\Support\RateLimiter;

final class AiGatewayService
{
    public const MAX_PROMPT_CHARS = 8000;
    public const MAX_TEXT_CHARS = 20000;
    public const MAX_OUTPUT_TOKENS = 4000;
    public const TASKS = ['text' => 'Text schreiben', 'rewrite' => 'Text überarbeiten', 'translate' => 'Übersetzen', 'summarize' => 'Zusammenfassen', 'json' => 'Strukturierte Daten (JSON)', 'layout' => 'Seitenlayout (JSON für den Homepage-Baukasten)'];
    /** Abschnittstypen des Homepage-Baukastens (cms/themes/elvado-baukasten/inc/layout.php) mit den wichtigsten Feldern für den Prompt. */
    public const LAYOUT_TYPES = [
        'hero' => 'title, text, image, btn_label, btn_url',
        'text' => 'title, body, align(left|center), bg(default|alt|accent|dark)',
        'features' => 'title, items[{title,text}] (bis 6), columns(0|2|3|4), bg',
        'image_text' => 'title, text, image, reverse(bool), btn_label, btn_url, bg',
        'posts' => 'title, count(1-12), category, all_label, bg',
        'cta' => 'title, text, btn_label, btn_url, bg',
        'html' => 'code, bg',
        'spacer' => 'height(0-400)',
    ];

    /**
     * Anbieter-Katalog. kind: openai | anthropic | gemini. verified=false: Basis-Adresse/Modellnamen bitte in der Anbieter-Dokumentation prüfen.
     * Die OpenAI-kompatiblen Anbieter (Groq, OpenRouter, Pollinations …) stehen in cms/lib/ai-providers.json und werden auch vom KI-Assistenten verwendet.
     * @param list<array<string,mixed>> $custom eigene Anbieter aus der KI-Zentrale (AiGatewayConfig::customProviders())
     * @return array<string,array<string,mixed>>
     */
    public static function catalog(array $custom = []): array
    {
        static $base = null;
        if ($base === null) {
            $pre = [];
            $raw = json_decode((string)@file_get_contents(dirname(__DIR__, 2) . '/lib/ai-providers.json'), true);
            foreach (is_array($raw) ? $raw : [] as $p) {
                if (!is_array($p) || ($p['id'] ?? '') === '' || $p['id'] === 'gemini') {   // Google läuft über die native Gemini-Schnittstelle (Eintrag „google“)
                    continue;
                }
                $parts = explode(' – ', (string)$p['label'], 2);
                $pre[(string)$p['id']] = ['label' => $parts[0], 'group' => !empty($p['free']) ? 'Kostenlos / günstig' : 'Direkt', 'kind' => 'openai', 'base_url' => (string)$p['base_url'], 'base_editable' => false,
                    'model' => (string)$p['model'], 'models' => array_values(array_unique(array_merge([(string)$p['model']], (array)($p['models'] ?? [])))), 'free' => !empty($p['free']), 'needs_key' => !empty($p['needs_key']),
                    'verified' => true, 'json_mode' => $p['id'] === 'openai', 'custom' => false,
                    'note' => ($parts[1] ?? '') !== '' ? ucfirst((string)$parts[1]) : ''];
            }
            $pre['openai']['note'] = 'Kostenpflichtig.';
            $pre['openrouter']['note'] = 'Viele Modelle über einen Schlüssel; Modelle mit „:free“ sind kostenlos (Verfügbarkeit wechselt).';
            $pre['openrouter']['model'] = 'openrouter/auto';
            foreach (['pollinations', 'llm7'] as $k) {
                if (isset($pre[$k])) {
                    $pre[$k]['group'] = 'Kostenlos ohne Schlüssel';
                    $pre[$k]['note'] = 'Community-Dienst ohne Anmeldung – Eingaben gehen an Dritte, Qualität und Verfügbarkeit schwanken. Zum Ausprobieren gedacht.';
                }
            }
            $own = [
                'evolink' => ['label' => 'EvoLink Smart Route', 'group' => 'Smart Routing', 'kind' => 'openai', 'base_url' => 'https://api.evolink.ai/v1', 'base_editable' => true, 'model' => 'evolink-auto',
                    'models' => ['evolink-auto'], 'free' => false, 'needs_key' => true, 'verified' => false, 'json_mode' => false, 'custom' => false,
                    'note' => 'OpenAI-kompatibel mit automatischer Modellwahl (Kosten/Latenz). Basis-Adresse und Modellnamen laut EvoLink-Dokumentation prüfen und hier anpassen.'],
                'anthropic' => ['label' => 'Anthropic (Claude)', 'group' => 'Direkt', 'kind' => 'anthropic', 'base_url' => 'https://api.anthropic.com/v1', 'base_editable' => false, 'model' => 'claude-sonnet-5-5',
                    'models' => ['claude-sonnet-5-5', 'claude-haiku-4-5-20251001', 'claude-opus-5-5'], 'free' => false, 'needs_key' => true, 'verified' => true, 'json_mode' => false, 'custom' => false, 'note' => 'Kostenpflichtig.'],
                'google' => ['label' => 'Google (Gemini)', 'group' => 'Direkt', 'kind' => 'gemini', 'base_url' => 'https://generativelanguage.googleapis.com/v1beta', 'base_editable' => false, 'model' => 'gemini-2.0-flash',
                    'models' => ['gemini-2.0-flash', 'gemini-2.0-flash-lite'], 'free' => true, 'needs_key' => true, 'verified' => true, 'json_mode' => true, 'custom' => false, 'note' => 'Kostenloses Kontingent mit API-Schlüssel (Google AI Studio).'],
                'deepseek' => ['label' => 'DeepSeek', 'group' => 'Kostenlos / günstig', 'kind' => 'openai', 'base_url' => 'https://api.deepseek.com', 'base_editable' => false, 'model' => 'deepseek-chat',
                    'models' => ['deepseek-chat', 'deepseek-reasoner'], 'free' => false, 'needs_key' => true, 'verified' => true, 'json_mode' => true, 'custom' => false, 'note' => 'Sehr günstig.'],
            ];
            $base = [];
            foreach (['evolink' => $own, 'openai' => $pre, 'anthropic' => $own, 'google' => $own, 'openrouter' => $pre, 'deepseek' => $own] as $id => $src) {
                if (isset($src[$id])) {
                    $base[$id] = $src[$id];
                }
            }
            foreach ($pre as $id => $def) {
                $base[$id] ??= $def;
            }
        }
        $out = $base;
        foreach ($custom as $c) {
            $id = (string)($c['id'] ?? '');
            if ($id === '' || isset($out[$id])) {
                continue;
            }
            $out[$id] = ['label' => (string)$c['label'], 'group' => 'Eigene Anbieter', 'kind' => 'openai', 'base_url' => (string)$c['base_url'], 'base_editable' => true, 'model' => (string)$c['model'],
                'models' => array_values(array_unique(array_merge([(string)$c['model']], (array)($c['models'] ?? [])))), 'free' => !empty($c['free']), 'needs_key' => !array_key_exists('needs_key', $c) || !empty($c['needs_key']),
                'verified' => false, 'json_mode' => false, 'custom' => true, 'note' => 'Eigener OpenAI-kompatibler Anbieter (z. B. Ollama oder LM Studio auf diesem Server).'];
        }
        return $out;
    }

    public function __construct(
        private readonly AiGatewayConfig $config,
        private readonly ?AiLogRepository $log = null,
        private readonly ?RateLimiter $limiter = null,
    ) {
    }

    /** Anbieter, die gerade nutzbar sind (aktiviert und mit Schlüssel bzw. ohne Schlüsselpflicht). Anbieter mit Schlüssel stehen vorn, Community-Dienste ohne Schlüssel zuletzt. @return list<array<string,mixed>> */
    public function usableProviders(): array
    {
        $out = [];
        foreach ($this->config->catalog() as $id => $def) {
            $keyed = $this->config->apiKey($id) !== '';
            if ($this->config->enabled($id) && ($keyed || !$def['needs_key'])) {
                $out[] = ['id' => $id, 'label' => $def['label'], 'group' => $def['group'], 'free' => $def['free'], 'model' => $this->config->model($id), 'models' => $def['models'], 'keyless' => !$keyed];
            }
        }
        usort($out, static fn(array $a, array $b): int => ((int)$a['keyless']) <=> ((int)$b['keyless']));
        return $out;
    }

    /**
     * KI-Aufruf.
     * @param array{provider:string,task?:string,prompt:string,text?:string,language?:string,model?:string,temperature?:float,max_tokens?:int,user?:string} $req
     * @throws AiGatewayException
     */
    public function generate(array $req): AiResult
    {
        $cat = $this->config->catalog();
        $pid = (string)($req['provider'] ?? '');
        if ($pid === '') {
            $purpose = (string)($req['purpose'] ?? '');
            $pid = ($purpose !== '' ? $this->config->purposeProvider($purpose) : $this->config->defaultProvider()) ?: ($this->usableProviders()[0]['id'] ?? '');
        }
        if (!isset($cat[$pid])) {
            throw new AiGatewayException('Bitte einen KI-Anbieter wählen.', 400);
        }
        $def = $cat[$pid];
        $task = (string)($req['task'] ?? 'text');
        if (!isset(self::TASKS[$task])) {
            throw new AiGatewayException('Unbekannte Aufgabe.', 400);
        }
        $prompt = trim((string)($req['prompt'] ?? ''));
        $text = trim((string)($req['text'] ?? ''));
        if ($prompt === '' && $text === '') {
            throw new AiGatewayException('Bitte einen Auftrag (Prompt) oder einen Text eingeben.', 400);
        }
        if (mb_strlen($prompt) > self::MAX_PROMPT_CHARS || mb_strlen($text) > self::MAX_TEXT_CHARS) {
            throw new AiGatewayException('Der Auftrag ist zu lang (Prompt höchstens ' . self::MAX_PROMPT_CHARS . ', Text höchstens ' . self::MAX_TEXT_CHARS . ' Zeichen).', 400);
        }
        if (!$this->config->enabled($pid)) {
            throw new AiGatewayException($def['label'] . ' ist ausgeschaltet.', 400);
        }
        $key = $this->config->apiKey($pid);
        if ($key === '' && $def['needs_key']) {
            throw new AiGatewayException('Für ' . $def['label'] . ' ist noch kein API-Schlüssel hinterlegt (Menü „KI-Zentrale“).', 400);
        }
        $user = (string)($req['user'] ?? '');
        if ($this->limiter !== null && !$this->limiter->hit('ai:' . ($user !== '' ? $user : 'anon'), $this->config->rateLimit(), 3600)) {
            throw new AiGatewayException('Zu viele KI-Anfragen in der letzten Stunde – bitte später erneut versuchen.', 429);
        }
        $model = (string)($req['model'] ?? '');
        if ($model === '' || !preg_match('~^[\w.:/@+-]{1,120}$~u', $model)) {
            $model = $this->config->model($pid);
        }
        $temperature = max(0.0, min(1.5, (float)($req['temperature'] ?? ($task === 'json' || $task === 'layout' ? 0.2 : 0.7))));
        $maxTokens = max(64, min(self::MAX_OUTPUT_TOKENS, (int)($req['max_tokens'] ?? 1200)));
        [$system, $userMsg] = $this->messages($task, $prompt, $text, (string)($req['language'] ?? ''));
        $wantJson = in_array($task, ['json', 'layout'], true);

        $t0 = microtime(true);
        $status = 0;
        try {
            $r = $this->call($def, $pid, $key, $model, $system, $userMsg, $temperature, $maxTokens, $wantJson);
            $status = 200;
            $data = null;
            if ($wantJson) {
                $data = self::extractJson($r['text']);
                if ($task === 'layout') {
                    $data = self::cleanLayout($data);
                }
            }
            $res = new AiResult($r['text'], $pid, $model, $data, $r['prompt_tokens'], $r['completion_tokens'], (int)round((microtime(true) - $t0) * 1000));
            $this->record($pid, $model, $task, $user, 'ok', 200, '', mb_strlen($prompt . $text), mb_strlen($r['text']), $r['prompt_tokens'], $r['completion_tokens'], $res->latencyMs);
            return $res;
        } catch (AiGatewayException $e) {
            $this->record($pid, $model, $task, $user, 'error', $e->httpStatus(), $e->getMessage(), mb_strlen($prompt . $text), 0, null, null, (int)round((microtime(true) - $t0) * 1000));
            throw $e;
        }
    }

    /** @return array{0:string,1:string} [Systemanweisung, Benutzernachricht] */
    private function messages(string $task, string $prompt, string $text, string $language): array
    {
        $lang = preg_match('/^[A-Za-z][A-Za-z -]{1,30}$/', $language) ? $language : 'Deutsch';
        $base = 'Du bist ein Redaktionsassistent für ein Website-CMS. Antworte präzise, ohne Einleitung und ohne Erklärungen zu deiner Arbeitsweise. Verwende reinen Text ohne HTML, außer wenn ausdrücklich Struktur verlangt ist.';
        switch ($task) {
            case 'rewrite':
                return [$base . ' Überarbeite den gegebenen Text nach der Anweisung; behalte Fakten bei und erfinde nichts.', ($prompt !== '' ? "Anweisung: $prompt\n\n" : '') . "Text:\n" . $text];
            case 'translate':
                return [$base . " Übersetze den Text vollständig und natürlich nach $lang. Gib nur die Übersetzung aus.", ($prompt !== '' ? "Hinweis: $prompt\n\n" : '') . "Text:\n" . $text];
            case 'summarize':
                return [$base . ' Fasse den Text knapp zusammen (3–5 Sätze oder Stichpunkte, wie angewiesen).', ($prompt !== '' ? "Anweisung: $prompt\n\n" : '') . "Text:\n" . $text];
            case 'json':
                return [$base . ' Antworte AUSSCHLIESSLICH mit gültigem JSON (ein Objekt oder Array), ohne Markdown-Zaun und ohne Text davor oder danach.', $prompt . ($text !== '' ? "\n\nAusgangstext:\n" . $text : '')];
            case 'layout':
                $types = '';
                foreach (self::LAYOUT_TYPES as $t => $f) {
                    $types .= "- $t: $f\n";
                }
                return [$base . " Du entwirfst Startseiten-Layouts als JSON-Array von Abschnitten der Form {\"type\":\"…\",\"props\":{…}}. Erlaubte Typen und Felder:\n$types" .
                    'Schreibe alle Texte in ' . $lang . ". Verwende nur diese Typen und Felder, keine Bild-Adressen erfinden (Felder image leer lassen), höchstens 12 Abschnitte. Antworte AUSSCHLIESSLICH mit dem JSON-Array.", $prompt . ($text !== '' ? "\n\nVorhandene Inhalte:\n" . $text : '')];
            default:
                return [$base, $prompt . ($text !== '' ? "\n\nKontext:\n" . $text : '')];
        }
    }

    /** @return array{text:string,prompt_tokens:?int,completion_tokens:?int} */
    private function call(array $def, string $pid, string $key, string $model, string $system, string $user, float $temp, int $maxTokens, bool $wantJson): array
    {
        $base = $this->config->baseUrl($pid);
        $opts = ['timeout' => 60, 'max_bytes' => 2_000_000, 'allow_local' => !empty($def['custom'])];
        switch ($def['kind']) {
            case 'anthropic':
                $resp = Http::postJson($base . '/messages', [
                    'model' => $model, 'max_tokens' => $maxTokens, 'temperature' => min(1.0, $temp), 'system' => $system,
                    'messages' => [['role' => 'user', 'content' => $user]],
                ], ['x-api-key: ' . $key, 'anthropic-version: 2023-06-01'], $opts);
                break;
            case 'gemini':
                $gen = ['temperature' => $temp, 'maxOutputTokens' => $maxTokens];
                if ($wantJson) {
                    $gen['responseMimeType'] = 'application/json';
                }
                $resp = Http::postJson($base . '/models/' . rawurlencode($model) . ':generateContent', [
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
                    'generationConfig' => $gen,
                ], ['x-goog-api-key: ' . $key], $opts);
                break;
            default:   // OpenAI-kompatibel
                $payload = [
                    'model' => $model, 'temperature' => $temp, 'max_tokens' => $maxTokens,
                    'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
                ];
                if ($wantJson && !empty($def['json_mode'])) {
                    $payload['response_format'] = ['type' => 'json_object'];
                }
                $headers = $key !== '' ? ['Authorization: Bearer ' . $key] : [];
                if ($pid === 'openrouter') {
                    $headers[] = 'X-Title: ElvadoPress';
                }
                $resp = Http::postJson($base . '/chat/completions', $payload, $headers, $opts);
        }
        if (!$resp->ok()) {
            throw new AiGatewayException(self::friendlyError($resp->status, $resp->error, $resp->json(), (string)$def['label']), $resp->status === 429 ? 429 : 502);
        }
        $j = $resp->json();
        if ($j === null) {
            throw new AiGatewayException('Die Antwort von ' . $def['label'] . ' war nicht lesbar.');
        }
        $text = '';
        $pt = $ct = null;
        if ($def['kind'] === 'anthropic') {
            foreach ((array)($j['content'] ?? []) as $b) {
                if (is_array($b) && ($b['type'] ?? '') === 'text') {
                    $text .= (string)($b['text'] ?? '');
                }
            }
            $pt = isset($j['usage']['input_tokens']) ? (int)$j['usage']['input_tokens'] : null;
            $ct = isset($j['usage']['output_tokens']) ? (int)$j['usage']['output_tokens'] : null;
        } elseif ($def['kind'] === 'gemini') {
            foreach ((array)($j['candidates'][0]['content']['parts'] ?? []) as $b) {
                $text .= is_array($b) ? (string)($b['text'] ?? '') : '';
            }
            $pt = isset($j['usageMetadata']['promptTokenCount']) ? (int)$j['usageMetadata']['promptTokenCount'] : null;
            $ct = isset($j['usageMetadata']['candidatesTokenCount']) ? (int)$j['usageMetadata']['candidatesTokenCount'] : null;
        } else {
            $m = $j['choices'][0]['message']['content'] ?? '';
            $text = is_string($m) ? $m : '';
            $pt = isset($j['usage']['prompt_tokens']) ? (int)$j['usage']['prompt_tokens'] : null;
            $ct = isset($j['usage']['completion_tokens']) ? (int)$j['usage']['completion_tokens'] : null;
        }
        $text = trim($text);
        if ($text === '') {
            throw new AiGatewayException($def['label'] . ' hat keine Antwort geliefert (Modell oder Auftrag prüfen).');
        }
        return ['text' => $text, 'prompt_tokens' => $pt, 'completion_tokens' => $ct];
    }

    private static function friendlyError(int $status, string $err, ?array $body, string $label): string
    {
        if ($status === 0) {
            return $label . ' ist nicht erreichbar' . ($err !== '' ? ' (' . mb_substr($err, 0, 80) . ')' : '') . '.';
        }
        $detail = '';
        $e = $body['error'] ?? null;
        if (is_array($e)) {
            $detail = (string)($e['message'] ?? '');
        } elseif (is_string($e)) {
            $detail = $e;
        }
        $detail = mb_substr(preg_replace('/\s+/', ' ', strip_tags($detail)) ?? '', 0, 160);
        return match (true) {
            $status === 401 || $status === 403 => $label . ': Zugang abgelehnt – API-Schlüssel prüfen.',
            $status === 402 => $label . ': Guthaben oder Kontingent aufgebraucht.',
            $status === 404 => $label . ': Modell oder Adresse nicht gefunden – Modellname prüfen.',
            $status === 429 => $label . ': Anbieter-Limit erreicht – später erneut versuchen.',
            $status >= 500 => $label . ' hat gerade ein Problem (HTTP ' . $status . ').',
            default => $label . ' lehnte die Anfrage ab (HTTP ' . $status . ')' . ($detail !== '' ? ': ' . $detail : '') . '.',
        };
    }

    private function record(string $p, string $m, string $task, string $user, string $status, int $code, string $err, int $pc, int $cc, ?int $pt, ?int $ct, int $ms): void
    {
        if ($this->log === null) {
            return;
        }
        try {
            $this->log->add(['provider' => $p, 'model' => $m, 'task' => $task, 'user' => $user, 'status' => $status, 'http_code' => $code, 'error' => $err,
                'prompt_chars' => $pc, 'completion_chars' => $cc, 'prompt_tokens' => $pt, 'completion_tokens' => $ct, 'latency_ms' => $ms]);
        } catch (\Throwable) {
            // Protokollfehler dürfen die KI-Funktion nie blockieren
        }
    }

    /** JSON aus einer Modellantwort holen (auch mit Markdown-Zaun oder Text drumherum). @throws AiGatewayException */
    public static function extractJson(string $text): mixed
    {
        $t = trim($text);
        if (preg_match('/```(?:json)?\s*(.*?)```/is', $t, $m)) {
            $t = trim($m[1]);
        }
        $j = json_decode($t, true);
        if ($j === null && json_last_error() !== JSON_ERROR_NONE) {
            $start = strcspn($t, '[{');
            $open = $t[$start] ?? '';
            $close = $open === '[' ? ']' : '}';
            $end = strrpos($t, $close);
            if ($open !== '' && $end !== false && $end > $start) {
                $j = json_decode(substr($t, $start, $end - $start + 1), true);
            }
        }
        if (!is_array($j)) {
            throw new AiGatewayException('Die KI hat kein gültiges JSON geliefert – bitte den Auftrag präziser formulieren oder erneut versuchen.', 502);
        }
        return $j;
    }

    /**
     * Layout-Antwort auf bekannte Abschnittstypen reduzieren (die endgültige Bereinigung geschieht beim Speichern des Baukastens).
     * Akzeptiert ein Array von Abschnitten oder ein Objekt mit "sections"/"layout". @return list<array{type:string,props:array<string,mixed>}>
     */
    public static function cleanLayout(mixed $data): array
    {
        if (is_array($data) && !array_is_list($data)) {
            $data = $data['sections'] ?? $data['layout'] ?? [];
        }
        $out = [];
        foreach (is_array($data) ? $data : [] as $s) {
            if (!is_array($s) || count($out) >= 12) {
                continue;
            }
            $type = (string)($s['type'] ?? '');
            if (!isset(self::LAYOUT_TYPES[$type])) {
                continue;
            }
            $out[] = ['type' => $type, 'props' => is_array($s['props'] ?? null) ? $s['props'] : []];
        }
        if ($out === []) {
            throw new AiGatewayException('Die KI hat kein brauchbares Layout geliefert.', 502);
        }
        return $out;
    }
}

<?php
declare(strict_types=1);
// cms/src/Ai/MediaGenerator.php
//
// KI-Bilder und -Videos für die Website: EvoLink (POST /v1/images|videos/generations, Abfrage GET /v1/tasks/{id}), fal.ai (Queue: POST queue.fal.run/{modell}, Status- und Ergebnis-Adresse der Antwort)
// und OpenAI (Bilder, sofortige Antwort). Aufträge laufen asynchron: start() liefert einen Job, status() wird von der Oberfläche abgefragt, bis das Ergebnis (Adresse oder Bilddaten) vorliegt.
// Schlüssel kommen aus der KI-Zentrale; es wird nichts gespeichert – die Übernahme in die Mediathek macht der Aufrufer.

namespace Elvado\Ai;

use Elvado\Support\Http;

final class MediaGenerator
{
    /** Vorschläge je Anbieter und Art (Modellnamen können im Feld frei eingegeben werden, die Anbieter ergänzen laufend neue). */
    public const MODELS = [
        'evolink' => [
            'image' => ['gpt-image-2.5-sunburst', 'gpt-image-2.5-flare', 'gemini-3.1-flash-image-preview'],
            'video' => ['seedance-2.0-text-to-video', 'seedance-2.0-image-to-video'],
        ],
        'fal' => [
            'image' => ['fal-ai/flux/schnell', 'fal-ai/flux/dev', 'fal-ai/flux-pro/v1.1', 'fal-ai/recraft/v3/text-to-image', 'fal-ai/nano-banana'],
            'video' => ['fal-ai/kling-video/v2.1/master/text-to-video', 'fal-ai/minimax/hailuo-02/standard/text-to-video', 'fal-ai/veo3'],
        ],
        'openai' => [
            'image' => ['gpt-image-1', 'dall-e-3'],
            'video' => [],
        ],
    ];

    public const RATIOS = ['16:9', '1:1', '9:16', '4:3', '3:4'];

    public function __construct(private readonly AiGatewayConfig $config)
    {
    }

    /** Anbieter, die Bilder und/oder Videos erzeugen können und gerade nutzbar sind. @return list<array{id:string,label:string,image:list<string>,video:list<string>}> */
    public function providers(): array
    {
        $out = [];
        $cat = $this->config->catalog();
        foreach (self::MODELS as $id => $kinds) {
            if (isset($cat[$id]) && $this->config->enabled($id) && $this->config->apiKey($id) !== '') {
                $out[] = ['id' => $id, 'label' => (string)$cat[$id]['label'], 'image' => $kinds['image'], 'video' => $kinds['video']];
            }
        }
        return $out;
    }

    /**
     * Auftrag starten.
     * @return array{job:string,status:string,url?:string,b64?:string,progress?:int}
     * @throws AiGatewayException
     */
    public function start(string $provider, string $kind, string $model, string $prompt, string $ratio = '16:9', string $imageUrl = ''): array
    {
        $prompt = trim($prompt);
        if (!in_array($kind, ['image', 'video'], true) || !isset(self::MODELS[$provider]) || !$this->config->enabled($provider)) {
            throw new AiGatewayException('Dieser Anbieter kann hierfür nicht genutzt werden.', 400);
        }
        $key = $this->config->apiKey($provider);
        if ($key === '') {
            throw new AiGatewayException('Für diesen Anbieter ist noch kein API-Schlüssel hinterlegt (KI-Zentrale).', 400);
        }
        if ($prompt === '' || mb_strlen($prompt) > 2000) {
            throw new AiGatewayException('Bitte eine Beschreibung (höchstens 2000 Zeichen) eingeben.', 400);
        }
        if ($kind === 'video' && !self::MODELS[$provider]['video']) {
            throw new AiGatewayException('Dieser Anbieter erzeugt hier nur Bilder.', 400);
        }
        if ($model === '') {
            $model = self::MODELS[$provider][$kind][0] ?? '';
        }
        if (!preg_match('~^[\w.:/@+-]{1,120}$~u', $model) || $model === '') {
            throw new AiGatewayException('Bitte ein Modell wählen.', 400);
        }
        $ratio = in_array($ratio, self::RATIOS, true) ? $ratio : '16:9';
        $imageUrl = preg_match('~^https://[^\s"\'<>]{4,1200}$~', $imageUrl) ? $imageUrl : '';
        $opts = ['timeout' => 40, 'max_bytes' => 4_000_000];
        if ($provider === 'openai') {
            if ($kind !== 'image') {
                throw new AiGatewayException('OpenAI erzeugt hier nur Bilder.', 400);
            }
            $size = ['1:1' => '1024x1024', '9:16' => '1024x1536', '3:4' => '1024x1536'][$ratio] ?? '1536x1024';
            $payload = ['model' => $model, 'prompt' => $prompt, 'n' => 1, 'size' => $model === 'dall-e-3' ? (['1:1' => '1024x1024', '9:16' => '1024x1792', '3:4' => '1024x1792'][$ratio] ?? '1792x1024') : $size];
            if ($model === 'dall-e-3') {
                $payload['response_format'] = 'b64_json';
            }
            $resp = Http::postJson(rtrim($this->config->baseUrl('openai'), '/') . '/images/generations', $payload, ['Authorization: Bearer ' . $key], ['timeout' => 150, 'max_bytes' => 12_000_000]);
            $j = $this->ok($resp, 'OpenAI');
            $b = (string)($j['data'][0]['b64_json'] ?? '');
            $u = (string)($j['data'][0]['url'] ?? '');
            if ($b === '' && $u === '') {
                throw new AiGatewayException('OpenAI hat kein Bild geliefert.');
            }
            return $b !== '' ? ['job' => 'openai:done', 'status' => 'completed', 'b64' => $b] : ['job' => 'openai:done', 'status' => 'completed', 'url' => $u];
        }
        if ($provider === 'evolink') {
            $payload = ['model' => $model, 'prompt' => $prompt];
            if ($kind === 'image') {
                $payload['size'] = $ratio;
            } else {
                $payload['aspect_ratio'] = $ratio;
                $payload['duration'] = 5;
                $payload['quality'] = '720p';
            }
            if ($imageUrl !== '') {
                $payload['image_urls'] = [$imageUrl];
            }
            $resp = Http::postJson(rtrim($this->config->baseUrl('evolink'), '/') . '/' . ($kind === 'image' ? 'images' : 'videos') . '/generations', $payload, ['Authorization: Bearer ' . $key], $opts);
            $j = $this->ok($resp, 'EvoLink');
            $id = (string)($j['id'] ?? '');
            if (!preg_match('/^[\w.-]{4,120}$/', $id)) {
                throw new AiGatewayException('EvoLink hat keine Auftragskennung geliefert.');
            }
            return ['job' => 'evolink:' . $id, 'status' => 'pending', 'progress' => (int)($j['progress'] ?? 0)];
        }
        // fal.ai: Queue
        $payload = ['prompt' => $prompt];
        if ($kind === 'image') {
            $payload['image_size'] = ['16:9' => 'landscape_16_9', '1:1' => 'square_hd', '9:16' => 'portrait_16_9', '4:3' => 'landscape_4_3', '3:4' => 'portrait_4_3'][$ratio];
        } else {
            $payload['aspect_ratio'] = $ratio;
        }
        if ($imageUrl !== '') {
            $payload['image_url'] = $imageUrl;
        }
        $resp = Http::postJson('https://queue.fal.run/' . $model, $payload, ['Authorization: Key ' . $key], $opts);
        $j = $this->ok($resp, 'fal.ai');
        $rid = (string)($j['request_id'] ?? '');
        if (!preg_match('/^[\w-]{8,80}$/', $rid)) {
            throw new AiGatewayException('fal.ai hat keine Auftragskennung geliefert.');
        }
        return ['job' => 'fal:' . $rid . '@' . $model, 'status' => 'pending'];
    }

    /**
     * Stand eines Auftrags abfragen.
     * @return array{status:string,progress?:int,url?:string,error?:string}
     * @throws AiGatewayException
     */
    public function status(string $job): array
    {
        if (!preg_match('/^(evolink|fal):([\w.@\/:+-]{4,200})$/', $job, $m) || !$this->config->enabled($m[1]) || ($key = $this->config->apiKey($m[1])) === '') {
            throw new AiGatewayException('Unbekannter Auftrag.', 400);
        }
        $opts = ['timeout' => 25, 'max_bytes' => 4_000_000];
        if ($m[1] === 'evolink') {
            if (!preg_match('/^[\w.-]{4,120}$/', $m[2])) {
                throw new AiGatewayException('Unbekannter Auftrag.', 400);
            }
            $j = $this->ok(Http::request('GET', rtrim($this->config->baseUrl('evolink'), '/') . '/tasks/' . rawurlencode($m[2]), ['Authorization: Bearer ' . $key], null, $opts), 'EvoLink');
            $st = (string)($j['status'] ?? '');
            if ($st === 'failed') {
                return ['status' => 'failed', 'error' => self::clip((string)($j['error']['message'] ?? 'Der Auftrag ist fehlgeschlagen.'))];
            }
            if ($st === 'completed') {
                $u = (string)($j['results'][0] ?? ($j['result_data'][0]['url'] ?? ''));
                return preg_match('~^https://~', $u) ? ['status' => 'completed', 'url' => $u] : ['status' => 'failed', 'error' => 'Der Anbieter hat kein Ergebnis geliefert.'];
            }
            return ['status' => 'pending', 'progress' => max(0, min(99, (int)($j['progress'] ?? 0)))];
        }
        if (!preg_match('~^([\w-]{8,80})@([\w.:/@+-]{1,120})$~', $m[2], $f)) {
            throw new AiGatewayException('Unbekannter Auftrag.', 400);
        }
        $base = 'https://queue.fal.run/' . $f[2] . '/requests/' . $f[1];
        $s = $this->ok(Http::request('GET', $base . '/status', ['Authorization: Key ' . $key], null, $opts), 'fal.ai');
        $st = strtoupper((string)($s['status'] ?? ''));
        if ($st !== 'COMPLETED') {
            return ['status' => 'pending'];
        }
        $resp = Http::request('GET', $base, ['Authorization: Key ' . $key], null, $opts);
        if (!$resp->ok()) {
            return ['status' => 'failed', 'error' => 'fal.ai: ' . self::clip((string)($resp->json()['detail'] ?? 'Der Auftrag ist fehlgeschlagen (HTTP ' . $resp->status . ').'))];
        }
        $j = $resp->json() ?? [];
        $u = (string)($j['images'][0]['url'] ?? ($j['image']['url'] ?? ($j['video']['url'] ?? '')));
        return preg_match('~^https://~', $u) ? ['status' => 'completed', 'url' => $u] : ['status' => 'failed', 'error' => 'Der Anbieter hat kein Ergebnis geliefert.'];
    }

    /** @return array<string,mixed> @throws AiGatewayException */
    private function ok(\Elvado\Support\HttpResponse $r, string $label): array
    {
        if (!$r->ok()) {
            $j = $r->json() ?? [];
            $d = is_array($j['error'] ?? null) ? (string)($j['error']['message'] ?? '') : (string)($j['error'] ?? ($j['detail'] ?? ''));
            $d = self::clip(is_array($j['detail'] ?? null) ? (string)json_encode($j['detail']) : $d);
            $msg = match (true) {
                $r->status === 0 => $label . ' ist nicht erreichbar.',
                $r->status === 401 || $r->status === 403 => $label . ': Zugang abgelehnt – API-Schlüssel prüfen.',
                $r->status === 402 => $label . ': Guthaben aufgebraucht.',
                $r->status === 404 => $label . ': Modell nicht gefunden – Modellname prüfen.',
                $r->status === 429 => $label . ': Limit erreicht – später erneut versuchen.',
                default => $label . ' lehnte die Anfrage ab (HTTP ' . $r->status . ')' . ($d !== '' ? ': ' . $d : '') . '.',
            };
            throw new AiGatewayException($msg, $r->status === 429 ? 429 : 502);
        }
        $j = $r->json();
        if (!is_array($j)) {
            throw new AiGatewayException('Die Antwort von ' . $label . ' war nicht lesbar.');
        }
        return $j;
    }

    private static function clip(string $s): string
    {
        return mb_substr(preg_replace('/\s+/', ' ', strip_tags($s)) ?? '', 0, 160);
    }
}

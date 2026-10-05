<?php
declare(strict_types=1);
// cms/src/Ai/AiResult.php – Ergebnis eines KI-Aufrufs (Text und – bei JSON-Aufgaben – die geparsten Daten).

namespace Elvado\Ai;

final class AiResult
{
    public function __construct(
        public readonly string $text,
        public readonly string $provider,
        public readonly string $model,
        public readonly mixed $data = null,
        public readonly ?int $promptTokens = null,
        public readonly ?int $completionTokens = null,
        public readonly int $latencyMs = 0,
    ) {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'data' => $this->data,
            'provider' => $this->provider,
            'model' => $this->model,
            'usage' => ['prompt_tokens' => $this->promptTokens, 'completion_tokens' => $this->completionTokens],
            'latency_ms' => $this->latencyMs,
        ];
    }
}

<?php
declare(strict_types=1);
// cms/src/Support/HttpResponse.php – Antwort des HTTP-Clients (Status, Rumpf, Kopfzeilen in Kleinbuchstaben, Fehlertext).

namespace Elvado\Support;

final class HttpResponse
{
    /** @param array<string,string> $headers Kopfzeilen in Kleinbuchstaben */
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $headers = [],
        public readonly string $error = '',
    ) {
    }

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /** @return array<string,mixed>|null */
    public function json(): ?array
    {
        $j = json_decode($this->body, true);
        return is_array($j) ? $j : null;
    }
}

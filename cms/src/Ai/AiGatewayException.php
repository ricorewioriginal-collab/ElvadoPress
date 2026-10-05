<?php
declare(strict_types=1);
// cms/src/Ai/AiGatewayException.php – Fehler des KI-Gateways; die Meldung ist für Benutzer gedacht (enthält nie Schlüssel oder Roh-Antworten).

namespace Elvado\Ai;

final class AiGatewayException extends \RuntimeException
{
    public function __construct(string $message, private readonly int $httpStatus = 502, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /** Passender HTTP-Status für die API-Antwort (400 Eingabe, 429 Limit, 502 Anbieter …). */
    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}

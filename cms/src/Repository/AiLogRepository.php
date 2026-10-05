<?php
declare(strict_types=1);
// cms/src/Repository/AiLogRepository.php
//
// Tabelle ai_logs: je KI-Aufruf eine Zeile mit Anbieter, Modell, Aufgabe, Ergebnis, Zeichen-/Token-Zahlen und Dauer.
// Datenschutz: Prompt und Antworttext werden NICHT gespeichert, nur Längen und Zähler.

namespace Elvado\Repository;

use Elvado\Database\DatabaseConnection;

final class AiLogRepository
{
    public function __construct(private readonly DatabaseConnection $db)
    {
    }

    public function add(array $e): void
    {
        $this->db->insert('ai_logs', [
            'provider' => mb_substr((string)($e['provider'] ?? ''), 0, 40),
            'model' => mb_substr((string)($e['model'] ?? ''), 0, 120),
            'task' => mb_substr((string)($e['task'] ?? 'text'), 0, 40),
            'user_name' => mb_substr((string)($e['user'] ?? ''), 0, 64),
            'status' => ($e['status'] ?? '') === 'ok' ? 'ok' : 'error',
            'http_code' => (int)($e['http_code'] ?? 0),
            'error_message' => mb_substr((string)($e['error'] ?? ''), 0, 300),
            'prompt_chars' => max(0, (int)($e['prompt_chars'] ?? 0)),
            'completion_chars' => max(0, (int)($e['completion_chars'] ?? 0)),
            'prompt_tokens' => isset($e['prompt_tokens']) ? (int)$e['prompt_tokens'] : null,
            'completion_tokens' => isset($e['completion_tokens']) ? (int)$e['completion_tokens'] : null,
            'latency_ms' => max(0, (int)($e['latency_ms'] ?? 0)),
            'created_at' => DatabaseConnection::now(),
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function recent(int $limit = 50): array
    {
        return $this->db->fetchAll('SELECT * FROM ai_logs ORDER BY id DESC LIMIT ' . max(1, min(500, $limit)));
    }

    /** Zusammenfassung der letzten Tage je Anbieter. @return list<array{provider:string,calls:int,errors:int,tokens:int,avg_ms:int}> */
    public function summary(int $days = 30): array
    {
        $since = date('Y-m-d H:i:s', time() - max(1, $days) * 86400);
        $rows = $this->db->fetchAll(
            "SELECT provider, COUNT(*) AS calls, SUM(CASE WHEN status = 'ok' THEN 0 ELSE 1 END) AS errors, COALESCE(SUM(COALESCE(prompt_tokens,0) + COALESCE(completion_tokens,0)),0) AS tokens, COALESCE(AVG(latency_ms),0) AS avg_ms FROM ai_logs WHERE created_at >= ? GROUP BY provider ORDER BY calls DESC",
            [$since]
        );
        return array_map(static fn(array $r): array => ['provider' => (string)$r['provider'], 'calls' => (int)$r['calls'], 'errors' => (int)$r['errors'], 'tokens' => (int)$r['tokens'], 'avg_ms' => (int)round((float)$r['avg_ms'])], $rows);
    }

    /** Einträge älter als $days Tage löschen. @return int gelöschte Zeilen */
    public function prune(int $days = 180): int
    {
        return $this->db->execute('DELETE FROM ai_logs WHERE created_at < ?', [date('Y-m-d H:i:s', time() - max(1, $days) * 86400)]);
    }
}

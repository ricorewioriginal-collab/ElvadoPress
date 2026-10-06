<?php
declare(strict_types=1);
// Minimaler SMTP-Client (ESMTP, STARTTLS/SSL, AUTH LOGIN/PLAIN). Kein Fremdcode, keine Abhängigkeiten.

namespace ElvadoPlugin\Forms;

final class Smtp
{
    /** @var resource|null */
    private $fp = null;

    public function __construct(private readonly string $host, private readonly int $port, private readonly string $security, private readonly string $user, private readonly string $pass, private readonly bool $verifyPeer = true, private readonly int $timeout = 12) {}

    private static function clean(string $s): string { return trim((string)preg_replace('/[\r\n\x00]+/', ' ', $s)); }

    private static function encodeHeader(string $s): string
    {
        $s = self::clean($s);
        return preg_match('/^[\x20-\x7e]*$/', $s) ? $s : '=?UTF-8?B?' . base64_encode($s) . '?=';
    }

    /** Antwort lesen. @return array{0:int,1:string} */
    private function read(): array
    {
        $text = '';
        $code = 0;
        while (($line = fgets($this->fp, 2048)) !== false) {
            $text .= $line;
            $code = (int)substr($line, 0, 3);
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        if ($code === 0) {
            throw new \RuntimeException('Keine Antwort vom Mailserver (Zeitüberschreitung oder Verbindung beendet).');
        }
        return [$code, trim($text)];
    }

    private function cmd(string $line, array $ok): string
    {
        fwrite($this->fp, $line . "\r\n");
        [$c, $t] = $this->read();
        if (!in_array($c, $ok, true)) {
            throw new \RuntimeException('Mailserver: ' . mb_substr($t, 0, 200));
        }
        return $t;
    }

    public function send(string $to, string $fromEmail, string $fromName, string $subject, string $body, string $replyTo = ''): void
    {
        foreach ([$to, $fromEmail] as $a) {
            if (!filter_var($a, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Ungültige E-Mail-Adresse: ' . mb_substr($a, 0, 60));
            }
        }
        $scheme = $this->security === 'ssl' ? 'ssl://' : 'tcp://';
        $ctx = stream_context_create(['ssl' => ['verify_peer' => $this->verifyPeer, 'verify_peer_name' => $this->verifyPeer, 'allow_self_signed' => !$this->verifyPeer]]);
        $this->fp = @stream_socket_client($scheme . $this->host . ':' . $this->port, $en, $es, $this->timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->fp) {
            throw new \RuntimeException('Keine Verbindung zum Mailserver ' . $this->host . ':' . $this->port . ' (' . mb_substr((string)$es, 0, 100) . ').');
        }
        stream_set_timeout($this->fp, $this->timeout);
        try {
            [$c, $t] = $this->read();
            if ($c !== 220) {
                throw new \RuntimeException('Mailserver: ' . mb_substr($t, 0, 200));
            }
            $helo = preg_replace('/[^A-Za-z0-9.-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
            $ehlo = $this->cmd('EHLO ' . $helo, [250]);
            if ($this->security === 'tls') {
                if (!str_contains(strtoupper($ehlo), 'STARTTLS')) {
                    throw new \RuntimeException('Der Mailserver bietet kein STARTTLS an.');
                }
                $this->cmd('STARTTLS', [220]);
                if (!@stream_socket_enable_crypto($this->fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                    throw new \RuntimeException('Die verschlüsselte Verbindung (TLS) konnte nicht aufgebaut werden.');
                }
                $ehlo = $this->cmd('EHLO ' . $helo, [250]);
            }
            if ($this->user !== '') {
                if ($this->security === 'none' && !in_array($this->host, ['127.0.0.1', 'localhost', '::1'], true)) {
                    // Zugangsdaten nie unverschlüsselt über das Netz senden
                    throw new \RuntimeException('Anmeldung ohne Verschlüsselung ist nur für lokale Server erlaubt.');
                }
                try {
                    $this->cmd('AUTH LOGIN', [334]);
                    $this->cmd(base64_encode($this->user), [334]);
                    $this->cmd(base64_encode($this->pass), [235]);
                } catch (\RuntimeException $e) {
                    throw new \RuntimeException('Anmeldung am Mailserver fehlgeschlagen (Benutzername/Passwort prüfen).');
                }
            }
            $this->cmd('MAIL FROM:<' . $fromEmail . '>', [250]);
            $this->cmd('RCPT TO:<' . $to . '>', [250, 251]);
            $this->cmd('DATA', [354]);
            $headers = [
                'Date: ' . date('r'),
                'From: ' . ($fromName !== '' ? self::encodeHeader($fromName) . ' <' . $fromEmail . '>' : $fromEmail),
                'To: <' . $to . '>',
                'Subject: ' . self::encodeHeader($subject),
                'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . $helo . '>',
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                'X-Mailer: ElvadoPress Forms',
            ];
            if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $headers[] = 'Reply-To: <' . $replyTo . '>';
            }
            $data = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body), 76, "\r\n");
            fwrite($this->fp, $data . ".\r\n");
            [$c, $t] = $this->read();
            if ($c !== 250) {
                throw new \RuntimeException('Mailserver hat die Nachricht abgelehnt: ' . mb_substr($t, 0, 200));
            }
            @fwrite($this->fp, "QUIT\r\n");
        } finally {
            @fclose($this->fp);
            $this->fp = null;
        }
    }
}

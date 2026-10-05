<?php
// Schlanker SMTP-Client (Namespace PHPMailer\PHPMailer), eigenständig aus dem dokumentierten Verhalten umgesetzt.
// Verbindung per stream_socket_client (SSL implizit oder STARTTLS), EHLO/HELO, AUTH LOGIN/PLAIN/CRAM-MD5/XOAUTH2, MAIL/RCPT/DATA mit Dot-Stuffing.
// Beim Laden passiert nichts.
namespace PHPMailer\PHPMailer;

class SMTP
{
    const VERSION = '6.9.1';
    const LE = "\r\n";
    const DEFAULT_PORT = 25;
    const DEFAULT_SECURE_PORT = 465;
    const MAX_LINE_LENGTH = 998;
    const MAX_REPLY_LENGTH = 512;
    const DEBUG_OFF = 0;
    const DEBUG_CLIENT = 1;
    const DEBUG_SERVER = 2;
    const DEBUG_CONNECTION = 3;
    const DEBUG_LOWLEVEL = 4;

    public $do_debug = self::DEBUG_OFF;
    public $Debugoutput = 'echo';
    public $do_verp = false;
    public $Timeout = 300;
    public $Timelimit = 300;
    protected $smtp_transaction_id_patterns = ['/[\d]{3} OK: queued as (.*)/', '/[\d]{3} Ok: queued as (.*)/', '/[\d]{3} 2\.0\.0 Ok: queued (.*)/', '/[\d]{3} Message accepted for delivery \((.*)\)/'];
    protected $last_smtp_transaction_id;
    protected $smtp_conn;
    protected $error = ['error' => '', 'detail' => '', 'smtp_code' => '', 'smtp_code_ex' => ''];
    protected $helo_rply;
    protected $server_caps;
    protected $last_reply = '';

    public function __construct() {}

    protected function edebug($str, $level = 0)
    {
        if ($level > $this->do_debug) {
            return;
        }
        if (is_callable($this->Debugoutput) && !in_array($this->Debugoutput, ['error_log', 'html', 'echo'], true)) {
            call_user_func($this->Debugoutput, $str, $level);
            return;
        }
        $str = preg_replace('/\r\n|\r/m', "\n", (string) $str);
        switch ($this->Debugoutput) {
            case 'error_log':
                error_log($str);
                break;
            case 'html':
                echo gmdate('Y-m-d H:i:s'), ' ', htmlentities(preg_replace('/[\r\n]+/', '', $str), ENT_QUOTES, 'UTF-8'), "<br>\n";
                break;
            default:
                echo gmdate('Y-m-d H:i:s'), "\t", str_replace("\n", "\n                   \t                  ", trim($str)), "\n";
        }
    }

    /** Verbindung aufbauen ($host darf ssl://, tls:// oder tcp:// vorangestellt haben). */
    public function connect($host, $port = null, $timeout = 30, $options = [])
    {
        $this->setError('');
        if ($this->connected()) {
            $this->setError('Bereits verbunden.');
            return false;
        }
        if (empty($port)) {
            $port = self::DEFAULT_PORT;
        }
        $this->edebug("Verbinde mit $host:$port (Zeitlimit $timeout s)", self::DEBUG_CONNECTION);
        $this->smtp_conn = $this->getSMTPConnection($host, $port, $timeout, (array) $options);
        if (!$this->smtp_conn) {
            return false;
        }
        $announce = $this->get_lines();
        $this->edebug('SERVER -> CLIENT: ' . $announce, self::DEBUG_SERVER);
        if (substr($announce, 0, 3) !== '220') {
            $this->setError('Server meldet sich nicht bereit: ' . trim($announce), '', substr($announce, 0, 3));
            $this->close();
            return false;
        }
        return true;
    }

    protected function getSMTPConnection($host, $port = null, $timeout = 30, $options = [])
    {
        $errno = 0;
        $errstr = '';
        if (!function_exists('stream_socket_client')) {
            $this->setError('stream_socket_client ist nicht verfügbar.');
            return false;
        }
        $socket_context = stream_context_create($options);
        set_error_handler(function () {});
        $connection = stream_socket_client($host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $socket_context);
        restore_error_handler();
        if (!is_resource($connection)) {
            $this->setError('Verbindung fehlgeschlagen.', (string) $errstr, (string) $errno);
            $this->edebug('SMTP FEHLER: ' . $this->error['error'] . ": $errstr ($errno)", self::DEBUG_CLIENT);
            return false;
        }
        stream_set_timeout($connection, (int) $timeout, 0);
        return $connection;
    }

    /** Verschlüsselung nach STARTTLS einschalten. */
    public function startTLS()
    {
        if (!$this->sendCommand('STARTTLS', 'STARTTLS', 220)) {
            return false;
        }
        $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        } elseif (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        set_error_handler(function () {});
        $ok = stream_socket_enable_crypto($this->smtp_conn, true, $crypto_method);
        restore_error_handler();
        if (!$ok) {
            $this->setError('TLS-Handshake fehlgeschlagen.');
        }
        return (bool) $ok;
    }

    /** Anmelden. $authtype leer = beste vom Server angebotene Methode. $OAuth: Objekt mit getOauth64(). */
    public function authenticate($username, $password, $authtype = null, $OAuth = null)
    {
        if (!$this->server_caps) {
            $this->setError('Anmeldung vor EHLO nicht möglich.');
            return false;
        }
        if (array_key_exists('EHLO', $this->server_caps)) {
            if (!array_key_exists('AUTH', $this->server_caps)) {
                $this->setError('Der Server bietet keine Anmeldung an.');
                return false;
            }
            $offered = (array) $this->server_caps['AUTH'];
            if (empty($authtype)) {
                foreach (['CRAM-MD5', 'LOGIN', 'PLAIN', 'XOAUTH2'] as $method) {
                    if ($method === 'XOAUTH2' && !$OAuth) {
                        continue;
                    }
                    if (in_array($method, $offered, true)) {
                        $authtype = $method;
                        break;
                    }
                }
                if (empty($authtype)) {
                    $this->setError('Keine unterstützte Anmeldemethode gefunden.');
                    return false;
                }
            } elseif (!in_array($authtype, $offered, true)) {
                $this->setError("Die Anmeldemethode $authtype wird vom Server nicht angeboten.");
                return false;
            }
        }
        switch ($authtype) {
            case 'PLAIN':
                if (!$this->sendCommand('AUTH', 'AUTH PLAIN', 334)) {
                    return false;
                }
                return $this->sendCommand('User & Password', base64_encode("\0" . $username . "\0" . $password), 235);
            case 'LOGIN':
                if (!$this->sendCommand('AUTH', 'AUTH LOGIN', 334)) {
                    return false;
                }
                if (!$this->sendCommand('Username', base64_encode($username), 334)) {
                    return false;
                }
                return $this->sendCommand('Password', base64_encode($password), 235);
            case 'CRAM-MD5':
                if (!$this->sendCommand('AUTH CRAM-MD5', 'AUTH CRAM-MD5', 334)) {
                    return false;
                }
                $challenge = base64_decode(substr($this->last_reply, 4));
                return $this->sendCommand('Username', base64_encode($username . ' ' . hash_hmac('md5', $challenge, $password)), 235);
            case 'XOAUTH2':
                if (!$OAuth || !is_object($OAuth) || !method_exists($OAuth, 'getOauth64')) {
                    $this->setError('XOAUTH2 ohne OAuth-Objekt.');
                    return false;
                }
                return $this->sendCommand('AUTH', 'AUTH XOAUTH2 ' . $OAuth->getOauth64(), 235);
            default:
                $this->setError('Unbekannte Anmeldemethode: ' . $authtype);
                return false;
        }
    }

    public function connected()
    {
        if (is_resource($this->smtp_conn)) {
            $sock_status = stream_get_meta_data($this->smtp_conn);
            if ($sock_status['eof']) {
                $this->edebug('SMTP NOTICE: EOF erkannt', self::DEBUG_CLIENT);
                $this->close();
                return false;
            }
            return true;
        }
        return false;
    }

    public function close()
    {
        $this->server_caps = null;
        $this->helo_rply = null;
        if (is_resource($this->smtp_conn)) {
            fclose($this->smtp_conn);
            $this->smtp_conn = null;
            $this->edebug('Verbindung geschlossen', self::DEBUG_CONNECTION);
        }
    }

    /** Nachrichtenkopf und -text senden; Zeilen mit Punkt am Anfang werden verdoppelt. */
    public function data($msg_data)
    {
        if (!$this->sendCommand('DATA', 'DATA', 354)) {
            return false;
        }
        $out = '';
        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", (string) $msg_data)) as $line) {
            $chunks = (strlen($line) > self::MAX_LINE_LENGTH) ? str_split($line, self::MAX_LINE_LENGTH) : [$line];
            foreach ($chunks as $chunk) {
                if (isset($chunk[0]) && $chunk[0] === '.') {
                    $chunk = '.' . $chunk;
                }
                $out .= $chunk . self::LE;
            }
        }
        $this->client_send($out . '.' . self::LE, 'DATA END');
        $reply = $this->get_lines();
        $this->edebug('SERVER -> CLIENT: ' . $reply, self::DEBUG_SERVER);
        $this->last_reply = $reply;
        $code = (int) substr($reply, 0, 3);
        if ($code !== 250) {
            $this->setError('Daten wurden nicht angenommen.', trim(substr($reply, 4)), (string) $code);
            return false;
        }
        $this->recordLastTransactionID();
        return true;
    }

    public function hello($host = '')
    {
        return $this->sendHello('EHLO', $host) || $this->sendHello('HELO', $host);
    }

    protected function sendHello($hello, $host)
    {
        $noerror = $this->sendCommand($hello, $hello . ' ' . $host, 250);
        $this->helo_rply = $this->last_reply;
        if ($noerror) {
            $this->parseHelloFields($hello);
        } else {
            $this->server_caps = null;
        }
        return $noerror;
    }

    protected function parseHelloFields($type)
    {
        $this->server_caps = [];
        foreach (explode("\n", (string) $this->helo_rply) as $n => $s) {
            $s = trim(substr($s, 4));
            if ($s === '') {
                continue;
            }
            $fields = explode(' ', $s);
            if ($n === 0) {
                $name = $type;
                $fields = $fields[0];
            } else {
                $name = array_shift($fields);
                if ($name === 'SIZE') {
                    $fields = $fields ? $fields[0] : 0;
                }
            }
            $this->server_caps[$name] = ($fields ?: true);
        }
    }

    public function mail($from)
    {
        return $this->sendCommand('MAIL FROM', 'MAIL FROM:<' . $from . '>' . ($this->do_verp ? ' XVERP' : ''), 250);
    }

    public function quit($close_on_error = true)
    {
        $noerror = $this->sendCommand('QUIT', 'QUIT', 221);
        $err = $this->error;
        if ($noerror || $close_on_error) {
            $this->close();
            $this->error = $err;
        }
        return $noerror;
    }

    public function recipient($address, $dsn = '')
    {
        return $this->sendCommand('RCPT TO', 'RCPT TO:<' . $address . '>', [250, 251]);
    }

    public function reset()
    {
        return $this->sendCommand('RSET', 'RSET', 250);
    }

    public function noop()
    {
        return $this->sendCommand('NOOP', 'NOOP', 250);
    }

    public function verify($name)
    {
        return $this->sendCommand('VRFY', "VRFY $name", [250, 251]);
    }

    /** Einzelnen Befehl senden und den erwarteten Antwortcode prüfen. */
    protected function sendCommand($command, $commandstring, $expect)
    {
        if (!$this->connected()) {
            $this->setError("Befehl $command ohne Verbindung nicht möglich.");
            return false;
        }
        if ((strpos($commandstring, "\n") !== false) || (strpos($commandstring, "\r") !== false)) {
            $this->setError("Zeilenumbruch im Befehl $command abgewiesen.");
            return false;
        }
        $this->client_send($commandstring . self::LE, $command);
        $this->last_reply = $this->get_lines();
        $code = 0;
        $code_ex = '';
        if (preg_match('/^([\d]{3})[ -](?:([\d]\.[\d]\.[\d]{1,2}) )?/', $this->last_reply, $m)) {
            $code = (int) $m[1];
            $code_ex = $m[2] ?? '';
        }
        $this->edebug('SERVER -> CLIENT: ' . $this->last_reply, self::DEBUG_SERVER);
        if (!in_array($code, (array) $expect, true)) {
            $this->setError("Befehl $command wurde abgelehnt", trim(substr($this->last_reply, 4)), (string) $code, (string) $code_ex);
            $this->edebug('SMTP FEHLER: ' . $this->error['error'] . ': ' . $this->last_reply, self::DEBUG_CLIENT);
            return false;
        }
        $this->setError('');
        return true;
    }

    public function client_send($data, $command = '')
    {
        if (in_array($command, ['DATA END', 'User & Password', 'Username', 'Password'], true)) {
            $this->edebug('CLIENT -> SERVER: [' . $command . ']', self::DEBUG_CLIENT);
        } else {
            $this->edebug('CLIENT -> SERVER: ' . $data, self::DEBUG_CLIENT);
        }
        set_error_handler(function () {});
        $result = fwrite($this->smtp_conn, $data);
        restore_error_handler();
        return $result;
    }

    public function getError() { return $this->error; }
    public function getServerExtList() { return $this->server_caps; }

    public function getServerExt($name)
    {
        if (!$this->server_caps) {
            $this->setError('Kein HELO/EHLO gesendet.');
            return null;
        }
        if (!array_key_exists($name, $this->server_caps)) {
            if ($name === 'HELO') {
                return $this->server_caps['EHLO'] ?? false;
            }
            if ($name === 'EHLO' || array_key_exists('EHLO', $this->server_caps)) {
                return false;
            }
            $this->setError('HELO erlaubt keine Server-Erweiterungen.');
            return null;
        }
        return $this->server_caps[$name];
    }

    public function getLastReply() { return $this->last_reply; }

    /** Antwort lesen (mehrzeilige Antworten "250-..." werden zusammengefasst); mit Zeitlimit. */
    protected function get_lines()
    {
        if (!is_resource($this->smtp_conn)) {
            return '';
        }
        $data = '';
        $endtime = $this->Timelimit > 0 ? time() + $this->Timelimit : 0;
        stream_set_timeout($this->smtp_conn, (int) $this->Timeout);
        while (is_resource($this->smtp_conn) && !feof($this->smtp_conn)) {
            $selR = [$this->smtp_conn];
            $selW = null;
            $selE = null;
            set_error_handler(function () {});
            $n = stream_select($selR, $selW, $selE, max(1, (int) $this->Timeout));
            restore_error_handler();
            if (!$n) {
                $this->edebug('SMTP NOTICE: Zeitüberschreitung beim Lesen', self::DEBUG_LOWLEVEL);
                break;
            }
            $str = @fgets($this->smtp_conn, self::MAX_REPLY_LENGTH);
            if ($str === false) {
                break;
            }
            $data .= $str;
            if (!isset($str[3]) || $str[3] === ' ' || $str[3] === "\r" || $str[3] === "\n") {
                break;
            }
            if ($endtime && time() > $endtime) {
                break;
            }
        }
        return $data;
    }

    protected function setError($message, $detail = '', $smtp_code = '', $smtp_code_ex = '')
    {
        $this->error = ['error' => $message, 'detail' => $detail, 'smtp_code' => $smtp_code, 'smtp_code_ex' => $smtp_code_ex];
    }

    public function setVerp($enabled = false) { $this->do_verp = $enabled; }
    public function getVerp() { return $this->do_verp; }
    public function setDebugOutput($method = 'echo') { $this->Debugoutput = $method; }
    public function getDebugOutput() { return $this->Debugoutput; }
    public function setDebugLevel($level = 0) { $this->do_debug = $level; }
    public function getDebugLevel() { return $this->do_debug; }
    public function setTimeout($timeout = 0) { $this->Timeout = $timeout; }
    public function getTimeout() { return $this->Timeout; }

    protected function recordLastTransactionID()
    {
        $this->last_smtp_transaction_id = false;
        foreach ($this->smtp_transaction_id_patterns as $pattern) {
            if (preg_match($pattern, $this->last_reply, $m)) {
                $this->last_smtp_transaction_id = trim($m[1]);
                break;
            }
        }
        return $this->last_smtp_transaction_id;
    }

    public function getLastTransactionID() { return $this->last_smtp_transaction_id; }
}

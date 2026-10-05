<?php
// Schlanke, PHPMailer-kompatible Mail-Klasse (Namespace PHPMailer\PHPMailer), eigenständig aus dem dokumentierten Verhalten umgesetzt.
// Mailer: mail (PHP mail()), smtp (Klasse SMTP), sendmail. Mehrteilige Nachrichten (alternative/related/mixed), Anhänge, Kopfzeilen-Schutz.
// Beim Laden passiert nichts (kein Versand, keine Verbindung). Nicht enthalten: DKIM, S/MIME, ICS, IDN-Umwandlung.
namespace PHPMailer\PHPMailer;

require_once __DIR__ . '/Exception.php';
require_once __DIR__ . '/SMTP.php';

class PHPMailer
{
    const CHARSET_ASCII = 'us-ascii';
    const CHARSET_ISO88591 = 'iso-8859-1';
    const CHARSET_UTF8 = 'utf-8';
    const CONTENT_TYPE_PLAINTEXT = 'text/plain';
    const CONTENT_TYPE_TEXT_CALENDAR = 'text/calendar';
    const CONTENT_TYPE_TEXT_HTML = 'text/html';
    const CONTENT_TYPE_MULTIPART_ALTERNATIVE = 'multipart/alternative';
    const CONTENT_TYPE_MULTIPART_MIXED = 'multipart/mixed';
    const CONTENT_TYPE_MULTIPART_RELATED = 'multipart/related';
    const ENCODING_7BIT = '7bit';
    const ENCODING_8BIT = '8bit';
    const ENCODING_BASE64 = 'base64';
    const ENCODING_BINARY = 'binary';
    const ENCODING_QUOTED_PRINTABLE = 'quoted-printable';
    const ENCRYPTION_STARTTLS = 'tls';
    const ENCRYPTION_SMTPS = 'ssl';
    const ICAL_METHOD_REQUEST = 'REQUEST';
    const MAX_LINE_LENGTH = 998;
    const STD_LINE_LENGTH = 76;
    const VERSION = '6.9.1';
    const STOP_MESSAGE = 0;
    const STOP_CONTINUE = 1;
    const STOP_CRITICAL = 2;

    public $Priority = null;
    public $CharSet = self::CHARSET_UTF8;
    public $ContentType = self::CONTENT_TYPE_PLAINTEXT;
    public $Encoding = self::ENCODING_8BIT;
    public $ErrorInfo = '';
    public $From = '';
    public $FromName = '';
    public $Sender = '';
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $Ical = '';
    public $WordWrap = 0;
    public $Mailer = 'mail';
    public $Sendmail = '/usr/sbin/sendmail';
    public $UseSendmailOptions = true;
    public $PluginDir = '';
    public $ConfirmReadingTo = '';
    public $Hostname = '';
    public $MessageID = '';
    public $MessageDate = '';
    public $Host = 'localhost';
    public $Port = 25;
    public $Helo = '';
    public $SMTPSecure = '';
    public $SMTPAutoTLS = true;
    public $SMTPAuth = false;
    public $SMTPOptions = [];
    public $Username = '';
    public $Password = '';
    public $AuthType = '';
    public $oauth;
    public $Timeout = 300;
    public $dsn = '';
    public $SMTPDebug = 0;
    public $Debugoutput = 'echo';
    public $SMTPKeepAlive = false;
    public $SingleTo = false;
    public $do_verp = false;
    public $AllowEmpty = false;
    public $DKIM_selector = '';
    public $DKIM_identity = '';
    public $DKIM_passphrase = '';
    public $DKIM_domain = '';
    public $DKIM_copyHeaderFields = true;
    public $DKIM_extraHeaders = [];
    public $DKIM_private = '';
    public $DKIM_private_string = '';
    public $action_function = '';
    public $XMailer = null;
    public static $validator = 'php';

    protected $smtp;
    protected $to = [];
    protected $cc = [];
    protected $bcc = [];
    protected $ReplyTo = [];
    protected $all_recipients = [];
    protected $RecipientsQueue = [];
    protected $ReplyToQueue = [];
    protected $attachment = [];
    protected $CustomHeader = [];
    protected $lastMessageID = '';
    protected $message_type = '';
    protected $boundary = [];
    protected $language = [];
    protected $error_count = 0;
    protected $mailHeader = '';
    protected $MIMEBody = '';
    protected $MIMEHeader = '';
    protected $exceptions = false;
    protected $uniqueid = '';

    public function __construct($exceptions = null)
    {
        if (null !== $exceptions) {
            $this->exceptions = (bool) $exceptions;
        }
        $this->setLanguage();
    }

    public function __destruct()
    {
        $this->smtpClose();
    }

    /* ───────── Einstellungen ───────── */
    public function isHTML($isHtml = true)
    {
        $this->ContentType = $isHtml ? static::CONTENT_TYPE_TEXT_HTML : static::CONTENT_TYPE_PLAINTEXT;
    }
    public function isSMTP() { $this->Mailer = 'smtp'; }
    public function isMail() { $this->Mailer = 'mail'; }
    public function isSendmail() { $this->Mailer = 'sendmail'; }
    public function isQmail() { $this->Mailer = 'qmail'; }

    public function setLanguage($langcode = 'de', $lang_path = '')
    {
        $this->language = [
            'authenticate' => 'SMTP-Fehler: Anmeldung fehlgeschlagen.',
            'buggy_php' => 'Fehlerhafte PHP-Version.',
            'connect_host' => 'SMTP-Fehler: Verbindung zum Server fehlgeschlagen.',
            'data_not_accepted' => 'SMTP-Fehler: Daten wurden nicht akzeptiert.',
            'empty_message' => 'Nachricht ist leer.',
            'encoding' => 'Unbekannte Kodierung: ',
            'execute' => 'Ausführung nicht möglich: ',
            'extension_missing' => 'Erweiterung fehlt: ',
            'file_access' => 'Zugriff auf Datei nicht möglich: ',
            'file_open' => 'Dateifehler: Datei kann nicht geöffnet werden: ',
            'from_failed' => 'Absenderadresse fehlgeschlagen: ',
            'instantiate' => 'Mail-Funktion nicht verfügbar.',
            'invalid_address' => 'Ungültige Adresse: ',
            'invalid_header' => 'Ungültiger Kopfzeilenname oder -wert.',
            'invalid_hostentry' => 'Ungültiger Hosteintrag: ',
            'invalid_host' => 'Ungültiger Host: ',
            'mailer_not_supported' => ' Mailer wird nicht unterstützt.',
            'provide_address' => 'Sie müssen mindestens einen Empfänger angeben.',
            'recipients_failed' => 'SMTP-Fehler: Folgende Empfänger sind fehlgeschlagen: ',
            'signing' => 'Signierfehler: ',
            'smtp_code' => 'SMTP-Code: ',
            'smtp_code_ex' => 'Weitere SMTP-Infos: ',
            'smtp_detail' => 'Detail: ',
            'smtp_error' => 'SMTP-Serverfehler: ',
            'variable_set' => 'Variable kann nicht gesetzt werden: ',
        ];
        return true;
    }
    public function getTranslations() { return $this->language; }
    public function exceptions($v = null) { if (null !== $v) { $this->exceptions = (bool) $v; } return $this->exceptions; }

    /* ───────── Adressen ───────── */
    public function setFrom($address, $name = '', $auto = true)
    {
        $address = trim((string) $address);
        $name = trim(preg_replace('/[\r\n]+/', '', (string) $name));
        if (!static::validateAddress($address)) {
            $err = $this->lang('invalid_address') . ' (From): ' . $address;
            $this->setError($err);
            $this->edebug($err);
            if ($this->exceptions) {
                throw new Exception($err);
            }
            return false;
        }
        $this->From = $address;
        $this->FromName = $name;
        if ($auto && empty($this->Sender)) {
            $this->Sender = $address;
        }
        return true;
    }

    public function addAddress($address, $name = '') { return $this->addOrEnqueueAnAddress('to', $address, $name); }
    public function addCC($address, $name = '') { return $this->addOrEnqueueAnAddress('cc', $address, $name); }
    public function addBCC($address, $name = '') { return $this->addOrEnqueueAnAddress('bcc', $address, $name); }
    public function addReplyTo($address, $name = '') { return $this->addOrEnqueueAnAddress('Reply-To', $address, $name); }

    protected function addOrEnqueueAnAddress($kind, $address, $name)
    {
        $address = trim((string) $address);
        $name = trim(preg_replace('/[\r\n]+/', '', (string) $name));
        if (!in_array($kind, ['to', 'cc', 'bcc', 'Reply-To'], true)) {
            $error_message = $this->lang('Invalid recipient kind: ') . $kind;
            $this->setError($error_message);
            $this->edebug($error_message);
            if ($this->exceptions) {
                throw new Exception($error_message);
            }
            return false;
        }
        if (!static::validateAddress($address)) {
            $error_message = sprintf('%s (%s): %s', $this->lang('invalid_address'), $kind, $address);
            $this->setError($error_message);
            $this->edebug($error_message);
            if ($this->exceptions) {
                throw new Exception($error_message);
            }
            return false;
        }
        if ($kind !== 'Reply-To') {
            if (!array_key_exists(strtolower($address), $this->all_recipients)) {
                $this->{$kind}[] = [$address, $name];
                $this->all_recipients[strtolower($address)] = true;
                return true;
            }
        } elseif (!array_key_exists(strtolower($address), $this->ReplyTo)) {
            $this->ReplyTo[strtolower($address)] = [$address, $name];
            return true;
        }
        return false;
    }

    /** Adressliste ("Name <a@b>, c@d") zerlegen: Liste aus ['name'=>…, 'address'=>…]. */
    public static function parseAddresses($addrstr, $useimap = true, $charset = self::CHARSET_ISO88591)
    {
        $addresses = [];
        $buf = '';
        $q = false;
        $d = 0;
        $parts = [];
        $s = (string) $addrstr;
        for ($i = 0, $l = strlen($s); $i < $l; $i++) {
            $c = $s[$i];
            if ($c === '"') {
                $q = !$q;
            } elseif (!$q && $c === '<') {
                $d++;
            } elseif (!$q && $c === '>') {
                $d = max(0, $d - 1);
            } elseif (!$q && !$d && ($c === ',' || $c === ';')) {
                $parts[] = $buf;
                $buf = '';
                continue;
            }
            $buf .= $c;
        }
        $parts[] = $buf;
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            if (preg_match('/^(.*)<([^<>]*)>\s*$/s', $p, $m)) {
                $name = trim(trim($m[1]), "\" \t");
                $addresses[] = ['name' => stripcslashes($name), 'address' => trim($m[2])];
            } else {
                $addresses[] = ['name' => '', 'address' => $p];
            }
        }
        return $addresses;
    }

    public static function validateAddress($address, $patternselect = null)
    {
        $address = (string) $address;
        if (null === $patternselect) {
            $patternselect = static::$validator;
        }
        if (is_callable($patternselect) && !is_string($patternselect)) {
            return call_user_func($patternselect, $address);
        }
        if (is_string($patternselect) && $patternselect !== '' && is_callable($patternselect) && !in_array($patternselect, ['php', 'pcre', 'pcre8', 'html5', 'noregex'], true)) {
            return (bool) call_user_func($patternselect, $address);
        }
        if (strpos($address, "\n") !== false || strpos($address, "\r") !== false) {
            return false;
        }
        switch ($patternselect) {
            case 'noregex':
                return strlen($address) >= 3 && strpos($address, '@') >= 1 && strpos($address, '@') !== strlen($address) - 1;
            case 'html5':
            case 'pcre':
            case 'pcre8':
                return (bool) preg_match('/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/', $address);
            default:
                return filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
        }
    }

    public static function idnSupported() { return function_exists('idn_to_ascii') && function_exists('mb_convert_encoding'); }
    public function punyencodeAddress($address) { return $address; }

    /* ───────── Abfragen und Zurücksetzen ───────── */
    public function getToAddresses() { return $this->to; }
    public function getCcAddresses() { return $this->cc; }
    public function getBccAddresses() { return $this->bcc; }
    public function getReplyToAddresses() { return $this->ReplyTo; }
    public function getAllRecipientAddresses() { return $this->all_recipients; }
    public function getAttachments() { return $this->attachment; }
    public function getCustomHeaders() { return $this->CustomHeader; }
    public function getLastMessageID() { return $this->lastMessageID; }
    public function getMailMIME() { return $this->mailHeader; }
    public function getSentMIMEMessage() { return static::stripTrailingWSP($this->MIMEHeader . $this->mailHeader) . static::$LE . static::$LE . $this->MIMEBody; }
    public function getBoundaries() { return $this->boundary; }
    public function isError() { return $this->error_count > 0; }
    public function alternativeExists() { return !empty($this->AltBody); }
    public function attachmentExists() { foreach ($this->attachment as $a) { if ($a[6] === 'attachment') { return true; } } return false; }
    public function inlineImageExists() { foreach ($this->attachment as $a) { if ($a[6] === 'inline') { return true; } } return false; }

    public function clearAddresses() { foreach ($this->to as $t) { unset($this->all_recipients[strtolower($t[0])]); } $this->to = []; $this->RecipientsQueue = []; }
    public function clearCCs() { foreach ($this->cc as $t) { unset($this->all_recipients[strtolower($t[0])]); } $this->cc = []; }
    public function clearBCCs() { foreach ($this->bcc as $t) { unset($this->all_recipients[strtolower($t[0])]); } $this->bcc = []; }
    public function clearReplyTos() { $this->ReplyTo = []; $this->ReplyToQueue = []; }
    public function clearAllRecipients() { $this->to = $this->cc = $this->bcc = $this->all_recipients = $this->RecipientsQueue = []; }
    public function clearAttachments() { $this->attachment = []; }
    public function clearCustomHeaders() { $this->CustomHeader = []; }

    protected function setError($msg)
    {
        ++$this->error_count;
        if ($this->Mailer === 'smtp' && null !== $this->smtp) {
            $lasterror = $this->smtp->getError();
            if (!empty($lasterror['error'])) {
                $msg .= ' ' . $this->lang('smtp_error') . $lasterror['error'];
                if (!empty($lasterror['detail'])) {
                    $msg .= ' ' . $this->lang('smtp_detail') . $lasterror['detail'];
                }
                if (!empty($lasterror['smtp_code'])) {
                    $msg .= ' ' . $this->lang('smtp_code') . $lasterror['smtp_code'];
                }
            }
        }
        $this->ErrorInfo = $msg;
    }

    protected function lang($key)
    {
        return $this->language[$key] ?? $key;
    }

    protected function edebug($str)
    {
        if ($this->SMTPDebug <= 0) {
            return;
        }
        if (is_callable($this->Debugoutput) && !in_array($this->Debugoutput, ['error_log', 'html', 'echo'], true)) {
            call_user_func($this->Debugoutput, $str, $this->SMTPDebug);
            return;
        }
        if ($this->Debugoutput === 'error_log') {
            error_log($str);
        } elseif ($this->Debugoutput === 'html') {
            echo htmlentities(preg_replace('/[\r\n]+/', '', $str), ENT_QUOTES, 'UTF-8'), "<br>\n";
        } else {
            echo gmdate('Y-m-d H:i:s'), "\t", trim($str), "\n";
        }
    }

    /* ───────── Senden ───────── */
    public function send()
    {
        try {
            if (!$this->preSend()) {
                return false;
            }
            return $this->postSend();
        } catch (Exception $exc) {
            $this->mailHeader = '';
            $this->setError($exc->getMessage());
            if ($this->exceptions) {
                throw $exc;
            }
            return false;
        }
    }

    public function preSend()
    {
        try {
            $this->error_count = 0;
            $this->mailHeader = '';
            if (count($this->to) + count($this->cc) + count($this->bcc) < 1) {
                throw new Exception($this->lang('provide_address'), self::STOP_CRITICAL);
            }
            foreach ([$this->From, $this->Sender] as $v) {
                if (strpbrk((string) $v, "\r\n\0") !== false) {
                    throw new Exception($this->lang('invalid_header'), self::STOP_CRITICAL);
                }
            }
            if (!static::validateAddress($this->From)) {
                throw new Exception($this->lang('invalid_address') . ' (From): ' . $this->From, self::STOP_CRITICAL);
            }
            if (!in_array($this->Mailer, ['mail', 'smtp', 'sendmail', 'qmail'], true)) {
                throw new Exception(sprintf('%s' . $this->lang('mailer_not_supported'), $this->Mailer), self::STOP_CRITICAL);
            }
            $this->MIMEHeader = '';
            $this->MIMEBody = $this->createBody();
            $tempheaders = $this->MIMEHeader;
            $this->MIMEHeader = $this->createHeader() . $tempheaders;
            return true;
        } catch (Exception $exc) {
            $this->setError($exc->getMessage());
            if ($this->exceptions) {
                throw $exc;
            }
            return false;
        }
    }

    private function count($a) { return is_array($a) ? count($a) : 0; }

    public function postSend()
    {
        try {
            switch ($this->Mailer) {
                case 'sendmail':
                case 'qmail':
                    return $this->sendmailSend($this->MIMEHeader, $this->MIMEBody);
                case 'smtp':
                    return $this->smtpSend($this->MIMEHeader, $this->MIMEBody);
                case 'mail':
                    return $this->mailSend($this->MIMEHeader, $this->MIMEBody);
                default:
                    throw new Exception(sprintf('%s' . $this->lang('mailer_not_supported'), $this->Mailer));
            }
        } catch (Exception $exc) {
            $this->setError($exc->getMessage());
            $this->edebug($exc->getMessage());
            if ($this->Mailer === 'smtp' && $this->SMTPKeepAlive == true && $this->smtp && $this->smtp->connected()) {
                $this->smtp->reset();
            }
            if ($this->exceptions) {
                throw $exc;
            }
        }
        return false;
    }

    /** Kopfzeilen für Transportwege, die den Betreff nicht als Argument führen. */
    protected function transportHeader($header)
    {
        $lines = [];
        $lines[] = ($this->count($this->to) > 0) ? trim($this->addrAppend('To', $this->to)) : 'To: undisclosed-recipients:;';
        if ($this->Mailer !== 'smtp' && $this->count($this->bcc) > 0) {
            $lines[] = trim($this->addrAppend('Bcc', $this->bcc));
        }
        $lines[] = 'Subject: ' . $this->encodeHeader($this->secureHeader($this->Subject));
        return $header . implode(static::$LE, $lines) . static::$LE;
    }

    protected function sendmailSend($header, $body)
    {
        $header = static::stripTrailingWSP($this->transportHeader($header)) . static::$LE . static::$LE;
        $sendmailFmt = '%s -oi -f%s -t';
        $sender = $this->Sender !== '' ? $this->Sender : $this->From;
        if ($this->Mailer === 'qmail') {
            $sendmailFmt = '%s';
        }
        if (!static::isShellSafe($this->Sendmail)) {
            $this->edebug('Sendmail-Pfad nicht sicher');
            throw new Exception($this->lang('execute') . $this->Sendmail, self::STOP_CRITICAL);
        }
        if (!static::isShellSafe($sender)) {
            $sender = '';
        }
        $sendmail = $this->Mailer === 'qmail' ? sprintf($sendmailFmt, escapeshellcmd($this->Sendmail)) : ($sender !== '' ? sprintf($sendmailFmt, escapeshellcmd($this->Sendmail), $sender) : escapeshellcmd($this->Sendmail) . ' -oi -t');
        $this->edebug('Sendmail-Befehl: ' . $sendmail);
        $mail = @popen($sendmail, 'w');
        if (!$mail) {
            throw new Exception($this->lang('execute') . $this->Sendmail, self::STOP_CRITICAL);
        }
        fwrite($mail, $header);
        fwrite($mail, $body);
        $result = pclose($mail);
        $this->doCallback(($result === 0), $this->to, $this->cc, $this->bcc, $this->Subject, $body, $this->From, []);
        if ($result !== 0) {
            throw new Exception($this->lang('execute') . $this->Sendmail, self::STOP_CRITICAL);
        }
        return true;
    }

    protected static function isShellSafe($string)
    {
        return $string !== '' && !preg_match('/[^A-Za-z0-9@._+\/\\\\:-]/', (string) $string) && strpos((string) $string, "\n") === false;
    }

    protected function mailSend($header, $body)
    {
        $toArr = [];
        foreach ($this->to as $toaddr) {
            $toArr[] = $this->addrFormat($toaddr);
        }
        $to = trim(implode(', ', $toArr));
        $sender = $this->Sender !== '' ? $this->Sender : '';
        $params = null;
        if ($sender !== '' && static::isShellSafe($sender)) {
            $params = sprintf('-f%s', $sender);
        }
        if ($to === '') {
            $to = 'undisclosed-recipients:;';
        }
        if ($this->count($this->bcc) > 0) {
            $header .= $this->addrAppend('Bcc', $this->bcc);
        }
        $header = static::stripTrailingWSP($header) . static::$LE . static::$LE;
        $subject = $this->encodeHeader($this->secureHeader($this->Subject));
        $result = false;
        if (function_exists('mail')) {
            set_error_handler(function () {});
            $result = $params !== null ? mail($to, $subject, $body, rtrim($header, "\r\n"), $params) : mail($to, $subject, $body, rtrim($header, "\r\n"));
            restore_error_handler();
        }
        $this->doCallback($result, $this->to, $this->cc, $this->bcc, $this->Subject, $body, $this->From, []);
        if (!$result) {
            throw new Exception($this->lang('instantiate'), self::STOP_CRITICAL);
        }
        return true;
    }

    protected function smtpSend($header, $body)
    {
        $header = static::stripTrailingWSP($this->transportHeader($header)) . static::$LE . static::$LE;
        $bad_rcpt = [];
        if (!$this->smtpConnect($this->SMTPOptions)) {
            throw new Exception($this->lang('connect_host'), self::STOP_CRITICAL);
        }
        $smtp_from = ($this->Sender === '') ? $this->From : $this->Sender;
        if (!$this->smtp->mail($smtp_from)) {
            $this->setError($this->lang('from_failed') . $smtp_from);
            throw new Exception($this->ErrorInfo, self::STOP_CRITICAL);
        }
        $callbacks = [];
        foreach (['to', 'cc', 'bcc'] as $togroup) {
            foreach ($this->$togroup as $to) {
                if (!$this->smtp->recipient($to[0], $this->dsn)) {
                    $error = $this->smtp->getError();
                    $bad_rcpt[] = ['to' => $to[0], 'error' => $error['detail']];
                    $isSent = false;
                } else {
                    $isSent = true;
                }
                $callbacks[] = ['issent' => $isSent, 'to' => $to[0], 'name' => $to[1]];
            }
        }
        if ((count($this->all_recipients) > count($bad_rcpt)) && !$this->smtp->data($header . $body)) {
            throw new Exception($this->lang('data_not_accepted'), self::STOP_CRITICAL);
        }
        $smtp_transaction_id = $this->smtp->getLastTransactionID();
        if ($this->SMTPKeepAlive) {
            $this->smtp->reset();
        } else {
            $this->smtp->quit();
            $this->smtp->close();
        }
        foreach ($callbacks as $cb) {
            $this->doCallback($cb['issent'], [[$cb['to'], $cb['name']]], [], [], $this->Subject, $body, $this->From, ['smtp_transaction_id' => $smtp_transaction_id]);
        }
        if (count($bad_rcpt) > 0) {
            $errstr = '';
            foreach ($bad_rcpt as $bad) {
                $errstr .= $bad['to'] . ': ' . $bad['error'];
            }
            throw new Exception($this->lang('recipients_failed') . $errstr, self::STOP_CONTINUE);
        }
        return true;
    }

    /** Verbindung zum SMTP-Server herstellen (ssl/tls/STARTTLS) und anmelden. */
    public function smtpConnect($options = null)
    {
        if (null === $this->smtp) {
            $this->smtp = $this->getSMTPInstance();
        }
        if (null === $options) {
            $options = $this->SMTPOptions;
        }
        if ($this->smtp->connected()) {
            return true;
        }
        $this->smtp->setTimeout($this->Timeout);
        $this->smtp->setDebugLevel($this->SMTPDebug);
        $this->smtp->setDebugOutput($this->Debugoutput);
        $this->smtp->setVerp($this->do_verp);
        $hosts = explode(';', (string) $this->Host);
        $lastexception = null;
        foreach ($hosts as $hostentry) {
            $hostinfo = [];
            if (!preg_match('/^(?:(ssl|tls):\/\/)?(.+?)(?::(\d+))?$/', trim($hostentry), $hostinfo)) {
                $this->edebug($this->lang('invalid_hostentry') . $hostentry);
                continue;
            }
            if (!static::isValidHost($hostinfo[2])) {
                $this->edebug($this->lang('invalid_host') . $hostinfo[2]);
                continue;
            }
            $prefix = '';
            $secure = $this->SMTPSecure;
            $tls = (static::ENCRYPTION_STARTTLS === $this->SMTPSecure);
            if ('ssl' === $hostinfo[1] || ('' === $hostinfo[1] && static::ENCRYPTION_SMTPS === $this->SMTPSecure)) {
                $prefix = 'ssl://';
                $tls = false;
                $secure = static::ENCRYPTION_SMTPS;
            } elseif ('tls' === $hostinfo[1]) {
                $tls = true;
                $secure = static::ENCRYPTION_STARTTLS;
            }
            $host = $hostinfo[2];
            $port = $this->Port;
            if (isset($hostinfo[3]) && is_numeric($hostinfo[3]) && $hostinfo[3] == (int) $hostinfo[3] && $hostinfo[3] > 0 && $hostinfo[3] < 65536) {
                $port = (int) $hostinfo[3];
            }
            try {
                if ($this->smtp->connect($prefix . $host, $port, $this->Timeout, $options)) {
                    if (!empty($this->Hostname)) {
                        $hello = $this->Hostname;
                    } else {
                        $hello = $this->serverHostname();
                    }
                    if (!empty($this->Helo)) {
                        $hello = $this->Helo;
                    }
                    $this->smtp->hello($hello);
                    if ($this->SMTPAutoTLS && $secure === '' && $this->smtp->getServerExt('STARTTLS')) {
                        $tls = true;
                    }
                    if ($tls) {
                        if (!$this->smtp->startTLS()) {
                            $message = $this->getSmtpErrorMessage('connect_host');
                            $this->smtp->close();
                            throw new Exception($message);
                        }
                        $this->smtp->hello($hello);
                    }
                    if ($this->SMTPAuth && !$this->smtp->authenticate($this->Username, $this->Password, $this->AuthType, $this->oauth)) {
                        throw new Exception($this->lang('authenticate'));
                    }
                    return true;
                }
                $this->setError($this->lang('connect_host'));
            } catch (Exception $exc) {
                $lastexception = $exc;
                $this->edebug($exc->getMessage());
                $this->smtp->quit(false);
                $this->smtp->close();
            }
        }
        $this->smtp->close();
        if ($this->exceptions && null !== $lastexception) {
            throw $lastexception;
        }
        if ($this->exceptions) {
            throw new Exception($this->ErrorInfo ?: $this->lang('connect_host'));
        }
        return false;
    }

    protected function getSmtpErrorMessage($base_key)
    {
        $message = $this->lang($base_key);
        $error = $this->smtp->getError();
        if (!empty($error['error'])) {
            $message .= ' ' . $error['error'];
            if (!empty($error['detail'])) {
                $message .= ' ' . $error['detail'];
            }
        }
        return $message;
    }

    public function smtpClose()
    {
        if ((null !== $this->smtp) && $this->smtp->connected()) {
            $this->smtp->quit();
            $this->smtp->close();
        }
    }

    public function getSMTPInstance()
    {
        if (!is_object($this->smtp)) {
            $this->smtp = new SMTP();
        }
        return $this->smtp;
    }

    public function setSMTPInstance(SMTP $smtp)
    {
        $this->smtp = $smtp;
        return $this->smtp;
    }

    public static function isValidHost($host)
    {
        if (empty($host) || !is_string($host) || strlen($host) > 256 || !preg_match('/^([0-9a-zA-Z.\-]*|\[[a-fA-F0-9:]+\])$/', $host)) {
            return false;
        }
        if (strlen($host) > 2 && substr($host, 0, 1) === '[' && substr($host, -1, 1) === ']') {
            return filter_var(substr($host, 1, -1), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        }
        if (is_numeric(str_replace('.', '', $host))) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        }
        return filter_var('http://' . $host, FILTER_VALIDATE_URL) !== false;
    }

    protected function doCallback($isSent, $to, $cc, $bcc, $subject, $body, $from, $extra)
    {
        if (!empty($this->action_function) && is_callable($this->action_function)) {
            call_user_func($this->action_function, $isSent, $to, $cc, $bcc, $subject, $body, $from, $extra);
        }
    }

    /* ───────── Aufbau der Nachricht ───────── */
    public static $LE = "\r\n";

    public function headerLine($name, $value) { return $name . ': ' . $value . static::$LE; }
    public function textLine($value) { return $value . static::$LE; }

    public function addrAppend($type, $addr)
    {
        $addresses = [];
        foreach ($addr as $address) {
            $addresses[] = $this->addrFormat($address);
        }
        return $type . ': ' . implode(', ', $addresses) . static::$LE;
    }

    public function addrFormat($addr)
    {
        if (!isset($addr[1]) || ($addr[1] === '')) {
            return $this->secureHeader($addr[0]);
        }
        return $this->encodeHeader($this->secureHeader($addr[1]), 'phrase') . ' <' . $this->secureHeader($addr[0]) . '>';
    }

    /** Zeilenumbrüche und Nullbytes aus einem Kopfzeilenwert entfernen (Schutz vor Header-Injektion). */
    public function secureHeader($str)
    {
        return trim(str_replace(["\r", "\n", "\0"], '', (string) $str));
    }

    public function encodeHeader($str, $position = 'text')
    {
        $str = (string) $str;
        if (!preg_match('/[^\x20-\x7E]/', $str)) {
            if ($position === 'phrase' && preg_match('/[()<>@,;:\\\\".\[\]]/', $str)) {
                return '"' . addcslashes($str, "\\\"") . '"';
            }
            return $str;
        }
        $charset = strtoupper($this->CharSet ?: 'UTF-8');
        if ($charset === 'UTF-8' && function_exists('mb_str_split')) {
            $chunks = [];
            $cur = '';
            foreach (mb_str_split($str, 1, 'UTF-8') as $ch) {
                if (strlen($cur) + strlen($ch) > 45) {
                    $chunks[] = $cur;
                    $cur = '';
                }
                $cur .= $ch;
            }
            if ($cur !== '') {
                $chunks[] = $cur;
            }
        } else {
            $chunks = str_split($str, 45);
        }
        $out = [];
        foreach ($chunks as $c) {
            $out[] = '=?' . $charset . '?B?' . base64_encode($c) . '?=';
        }
        return implode(static::$LE . ' ', $out);
    }

    public function encodeString($str, $encoding = self::ENCODING_BASE64)
    {
        switch (strtolower($encoding)) {
            case static::ENCODING_BASE64:
                return rtrim(chunk_split(base64_encode($str), static::STD_LINE_LENGTH, static::$LE), static::$LE);
            case static::ENCODING_7BIT:
            case static::ENCODING_8BIT:
                $encoded = static::normalizeBreaks($str);
                if (substr($encoded, -strlen(static::$LE)) !== static::$LE) {
                    $encoded .= static::$LE;
                }
                return $encoded;
            case static::ENCODING_BINARY:
                return $str;
            case static::ENCODING_QUOTED_PRINTABLE:
                return $this->encodeQP($str);
            default:
                $this->setError($this->lang('encoding') . $encoding);
                if ($this->exceptions) {
                    throw new Exception($this->lang('encoding') . $encoding);
                }
                return $str;
        }
    }

    public function encodeQP($string)
    {
        return static::normalizeBreaks(quoted_printable_encode($string));
    }

    public static function normalizeBreaks($text, $breaktype = null)
    {
        if (null === $breaktype) {
            $breaktype = static::$LE;
        }
        $text = str_replace([self::$LE, "\r\n", "\n", "\r"], "\n", (string) $text);
        return $breaktype === "\n" ? $text : str_replace("\n", $breaktype, $text);
    }

    public static function stripTrailingWSP($text) { return rtrim((string) $text, " \r\n\t"); }

    public function wrapText($message, $length, $qp_mode = false)
    {
        return wordwrap((string) $message, $length, static::$LE, false);
    }

    public function createHeader()
    {
        $result = '';
        $result .= $this->headerLine('Date', $this->MessageDate === '' ? date('D, j M Y H:i:s O') : $this->MessageDate);
        $result .= $this->addrAppend('From', [[trim($this->From), $this->FromName]]);
        if ($this->count($this->cc) > 0) {
            $result .= $this->addrAppend('Cc', $this->cc);
        }
        if ($this->count($this->ReplyTo) > 0) {
            $result .= $this->addrAppend('Reply-To', array_values($this->ReplyTo));
        }
        if ($this->MessageID !== '' && $this->MessageID !== null) {
            $this->lastMessageID = $this->MessageID;
        } else {
            $this->lastMessageID = sprintf('<%s@%s>', bin2hex(random_bytes(16)), $this->serverHostname());
        }
        $result .= $this->headerLine('Message-ID', $this->lastMessageID);
        if (null !== $this->Priority) {
            $result .= $this->headerLine('X-Priority', (string) $this->Priority);
        }
        if (null === $this->XMailer) {
            $result .= $this->headerLine('X-Mailer', 'PHPMailer ' . static::VERSION . ' (https://github.com/PHPMailer/PHPMailer)');
        } else {
            $myXmailer = trim((string) $this->XMailer);
            if ($myXmailer !== '') {
                $result .= $this->headerLine('X-Mailer', $myXmailer);
            }
        }
        if ($this->ConfirmReadingTo !== '') {
            $result .= $this->headerLine('Disposition-Notification-To', '<' . $this->secureHeader($this->ConfirmReadingTo) . '>');
        }
        foreach ($this->CustomHeader as $header) {
            $result .= $this->headerLine(trim($header[0]), $this->encodeHeader(trim($header[1])));
        }
        return $result . $this->headerLine('MIME-Version', '1.0');
    }

    public function addCustomHeader($name, $value = null)
    {
        if (null === $value && strpos((string) $name, ':') !== false) {
            [$name, $value] = explode(':', (string) $name, 2);
        }
        $name = trim((string) $name);
        $value = trim((string) $value);
        if (!preg_match('/^[!-9;-~]+$/', $name) || strpbrk($value, "\r\n\0") !== false) {
            $this->setError($this->lang('invalid_header'));
            if ($this->exceptions) {
                throw new Exception($this->lang('invalid_header'));
            }
            return false;
        }
        $this->CustomHeader[] = [$name, $value];
        return true;
    }

    protected function setMessageType()
    {
        $type = [];
        if ($this->alternativeExists()) {
            $type[] = 'alt';
        }
        if ($this->inlineImageExists()) {
            $type[] = 'inline';
        }
        if ($this->attachmentExists()) {
            $type[] = 'attach';
        }
        $this->message_type = implode('_', $type);
        if ($this->message_type === '') {
            $this->message_type = 'plain';
        }
    }

    private function bodyPart($contentType, $text, $encoding)
    {
        $charset = $this->CharSet;
        if (preg_match('/[^\x00-\x7F]/', $text) && $encoding === self::ENCODING_7BIT) {
            $encoding = self::ENCODING_8BIT;
        }
        // Zu lange Zeilen (> 998 Zeichen) verbieten das Format 8bit/7bit
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            if (strlen($line) > self::MAX_LINE_LENGTH && in_array($encoding, [self::ENCODING_7BIT, self::ENCODING_8BIT], true)) {
                $encoding = self::ENCODING_QUOTED_PRINTABLE;
                break;
            }
        }
        return [
            'Content-Type: ' . $contentType . '; charset=' . $charset . static::$LE . 'Content-Transfer-Encoding: ' . $encoding,
            $this->encodeString($text, $encoding),
        ];
    }

    private function join($subtype, array $parts)
    {
        $b = 'b' . count($this->boundary) . '_' . ($this->uniqueid ?: ($this->uniqueid = bin2hex(random_bytes(12))));
        $this->boundary[] = $b;
        $body = '';
        foreach ($parts as $p) {
            $body .= '--' . $b . static::$LE . $p[0] . static::$LE . static::$LE . $p[1] . static::$LE;
        }
        $body .= '--' . $b . '--' . static::$LE;
        return ['Content-Type: ' . $subtype . ';' . static::$LE . ' boundary="' . $b . '"', $body];
    }

    private function attachmentPart(array $a)
    {
        $type = $a[4] ?: static::filenameToType($a[1]);
        $name = $this->secureHeader($a[2] ?: $a[1]);
        $encName = $this->encodeHeader($name);
        $nameq = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $encName) . '"';
        $enc = $a[3] ?: self::ENCODING_BASE64;
        $h = 'Content-Type: ' . $type . '; name=' . $nameq . static::$LE . 'Content-Transfer-Encoding: ' . $enc . static::$LE;
        if ($a[6] === 'inline' && $a[7] !== '') {
            $h .= 'Content-ID: <' . $this->secureHeader($a[7]) . '>' . static::$LE;
        }
        $h .= 'Content-Disposition: ' . $a[6] . '; filename=' . $nameq;
        if ($a[5]) {
            $data = $a[0];
        } else {
            $data = @file_get_contents($a[0]);
            if ($data === false) {
                throw new Exception($this->lang('file_open') . $a[0], self::STOP_CONTINUE);
            }
        }
        return [$h, $this->encodeString($data, $enc)];
    }

    public function createBody()
    {
        $this->boundary = [];
        if (stripos($this->ContentType, 'multipart/') === 0) {   // fertig aufgebauter MIME-Text (Content-Type kommt als eigene Kopfzeile)
            $this->MIMEHeader = '';
            $has = false;
            foreach ($this->CustomHeader as $h) {
                $has = $has || strtolower($h[0]) === 'content-type';
            }
            if (!$has) {
                $this->MIMEHeader = 'Content-Type: ' . $this->ContentType . static::$LE;
            }
            return static::normalizeBreaks($this->Body);
        }
        $this->setMessageType();
        $html = (strtolower($this->ContentType) === self::CONTENT_TYPE_TEXT_HTML);
        $enc = $this->Encoding ?: self::ENCODING_8BIT;
        if ($this->Body === '' && !$this->AllowEmpty && $this->message_type === 'plain') {
            throw new Exception($this->lang('empty_message'), self::STOP_CRITICAL);
        }
        $bodyText = $this->WordWrap > 0 && !$html ? $this->wrapText($this->Body, $this->WordWrap) : $this->Body;
        $main = $this->bodyPart($this->ContentType, $bodyText, $enc);
        if ($this->alternativeExists()) {
            $main = $this->bodyPart('text/plain', $this->AltBody, $enc);
            $htmlPart = $this->bodyPart($this->ContentType, $bodyText, $enc);
            $inl = array_filter($this->attachment, fn($a) => $a[6] === 'inline');
            if ($inl) {
                $rel = [$htmlPart];
                foreach ($inl as $a) {
                    $rel[] = $this->attachmentPart($a);
                }
                $htmlPart = $this->join(self::CONTENT_TYPE_MULTIPART_RELATED, $rel);
            }
            $main = $this->join(self::CONTENT_TYPE_MULTIPART_ALTERNATIVE, [$main, $htmlPart]);
        } elseif ($this->inlineImageExists()) {
            $rel = [$main];
            foreach ($this->attachment as $a) {
                if ($a[6] === 'inline') {
                    $rel[] = $this->attachmentPart($a);
                }
            }
            $main = $this->join(self::CONTENT_TYPE_MULTIPART_RELATED, $rel);
        }
        if ($this->attachmentExists()) {
            $mixed = [$main];
            foreach ($this->attachment as $a) {
                if ($a[6] === 'attachment') {
                    $mixed[] = $this->attachmentPart($a);
                }
            }
            $main = $this->join(self::CONTENT_TYPE_MULTIPART_MIXED, $mixed);
        }
        $this->MIMEHeader = $main[0] . static::$LE;
        return $main[1];
    }

    /* ───────── Anhänge ───────── */
    public function addAttachment($path, $name = '', $encoding = self::ENCODING_BASE64, $type = '', $disposition = 'attachment')
    {
        try {
            if (!static::fileIsAccessible($path)) {
                throw new Exception($this->lang('file_access') . $path, self::STOP_CONTINUE);
            }
            if ($type === '') {
                $type = static::filenameToType($path);
            }
            $filename = (string) static::mb_pathinfo($path, PATHINFO_BASENAME);
            if ($name === '') {
                $name = $filename;
            }
            $this->attachment[] = [$path, $filename, $name, $encoding, $type, false, $disposition, $name];
        } catch (Exception $exc) {
            $this->setError($exc->getMessage());
            $this->edebug($exc->getMessage());
            if ($this->exceptions) {
                throw $exc;
            }
            return false;
        }
        return true;
    }

    public function addStringAttachment($string, $filename, $encoding = self::ENCODING_BASE64, $type = '', $disposition = 'attachment')
    {
        if ($type === '') {
            $type = static::filenameToType($filename);
        }
        $this->attachment[] = [$string, $filename, (string) static::mb_pathinfo($filename, PATHINFO_BASENAME), $encoding, $type, true, $disposition, 0];
        return true;
    }

    public function addEmbeddedImage($path, $cid, $name = '', $encoding = self::ENCODING_BASE64, $type = '', $disposition = 'inline')
    {
        if (!static::fileIsAccessible($path)) {
            $this->setError($this->lang('file_access') . $path);
            if ($this->exceptions) {
                throw new Exception($this->lang('file_access') . $path);
            }
            return false;
        }
        if ($type === '') {
            $type = static::filenameToType($path);
        }
        $filename = (string) static::mb_pathinfo($path, PATHINFO_BASENAME);
        if ($name === '') {
            $name = $filename;
        }
        $this->attachment[] = [$path, $filename, $name, $encoding, $type, false, $disposition, $cid];
        return true;
    }

    public function addStringEmbeddedImage($string, $cid, $name = '', $encoding = self::ENCODING_BASE64, $type = '', $disposition = 'inline')
    {
        if ($type === '') {
            $type = static::filenameToType($name);
        }
        $this->attachment[] = [$string, $name, $name, $encoding, $type, true, $disposition, $cid];
        return true;
    }

    protected static function fileIsAccessible($path)
    {
        $path = (string) $path;
        if ($path === '' || preg_match('#^[a-z][a-z0-9+.-]*://#i', $path)) {
            return false;   // keine Adressen mit Protokoll (Schutz vor fremden Quellen)
        }
        return is_file($path) && is_readable($path);
    }

    public function clearQueuedAddresses($kind) { }

    public static function mb_pathinfo($path, $options = null)
    {
        $ret = ['dirname' => '', 'basename' => '', 'extension' => '', 'filename' => ''];
        $pathinfo = [];
        if (preg_match('#^(.*?)[\\\\/]*(([^/\\\\]*?)(\.([^.\\\\/]+?)|))[\\\\/.]*$#m', (string) $path, $pathinfo)) {
            if (array_key_exists(1, $pathinfo)) {
                $ret['dirname'] = $pathinfo[1];
            }
            if (array_key_exists(2, $pathinfo)) {
                $ret['basename'] = $pathinfo[2];
            }
            if (array_key_exists(5, $pathinfo)) {
                $ret['extension'] = $pathinfo[5];
            }
            if (array_key_exists(3, $pathinfo)) {
                $ret['filename'] = $pathinfo[3];
            }
        }
        switch ($options) {
            case PATHINFO_DIRNAME:
            case 'dirname':
                return $ret['dirname'];
            case PATHINFO_BASENAME:
            case 'basename':
                return $ret['basename'];
            case PATHINFO_EXTENSION:
            case 'extension':
                return $ret['extension'];
            case PATHINFO_FILENAME:
            case 'filename':
                return $ret['filename'];
            default:
                return $ret;
        }
    }

    public static function _mime_types($ext = '')
    {
        $mimes = [
            'txt' => 'text/plain', 'html' => 'text/html', 'htm' => 'text/html', 'css' => 'text/css', 'csv' => 'text/csv', 'ics' => 'text/calendar',
            'xml' => 'application/xml', 'json' => 'application/json', 'js' => 'application/javascript', 'pdf' => 'application/pdf', 'zip' => 'application/zip',
            'gz' => 'application/gzip', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'odt' => 'application/vnd.oasis.opendocument.text', 'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpe' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp',
            'svg' => 'image/svg+xml', 'bmp' => 'image/bmp', 'ico' => 'image/x-icon', 'mp3' => 'audio/mpeg', 'wav' => 'audio/x-wav', 'ogg' => 'audio/ogg',
            'mp4' => 'video/mp4', 'mov' => 'video/quicktime', 'webm' => 'video/webm', 'eml' => 'message/rfc822',
        ];
        $ext = strtolower((string) $ext);
        return $mimes[$ext] ?? 'application/octet-stream';
    }

    public static function filenameToType($filename)
    {
        $qpos = strpos((string) $filename, '?');
        if (false !== $qpos) {
            $filename = substr($filename, 0, $qpos);
        }
        return static::_mime_types(static::mb_pathinfo($filename, PATHINFO_EXTENSION));
    }

    /* ───────── Sonstiges ───────── */
    public function serverHostname()
    {
        $result = '';
        if (!empty($this->Hostname)) {
            $result = $this->Hostname;
        } elseif (isset($_SERVER) && array_key_exists('SERVER_NAME', $_SERVER)) {
            $result = $_SERVER['SERVER_NAME'];
        } elseif (function_exists('gethostname') && gethostname() !== false) {
            $result = gethostname();
        } elseif (php_uname('n') !== false) {
            $result = php_uname('n');
        }
        if (!static::isValidHost($result)) {
            return 'localhost.localdomain';
        }
        return $result;
    }

    /** HTML als Nachricht setzen; AltBody wird aus dem Text abgeleitet (einfach). */
    public function msgHTML($message, $basedir = '', $advanced = false)
    {
        $this->isHTML();
        $this->Body = static::normalizeBreaks($message);
        $this->AltBody = static::normalizeBreaks(is_callable($advanced) ? call_user_func($advanced, $message) : $this->html2text($message));
        if ($this->Subject === '' && preg_match('/<title>(.*?)<\/title>/is', $message, $m)) {
            $this->Subject = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        }
        return $this->Body;
    }

    public function html2text($html, $advanced = false)
    {
        if (is_callable($advanced)) {
            return call_user_func($advanced, $html);
        }
        $html = preg_replace('/<(script|style|head|title)\b.*?<\/\1>/is', '', (string) $html);
        $html = preg_replace('/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', '$2 ($1)', $html);
        $html = preg_replace('/<\/?(p|div|br|li|tr|h[1-6])\b[^>]*>/i', "\n", $html);
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
    }

    public function set($name, $value = '')
    {
        if (property_exists($this, $name)) {
            $this->$name = $value;
            return true;
        }
        $this->setError($this->lang('variable_set') . $name);
        return false;
    }

    public function sign($cert_filename, $key_filename, $key_pass, $extracerts_filename = '') { }
    public function DKIM_QP($txt) { return $txt; }
    public function DKIM_Sign($signHeader) { return ''; }
}

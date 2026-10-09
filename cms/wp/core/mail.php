<?php
// E-Mail-Versand der WordPress-Schicht (wird von wp_mail() erst bei Bedarf geladen): globales $phpmailer
// (PHPMailer\PHPMailer\PHPMailer), Header-Auswertung, Filter/Aktionen wie dokumentiert (wp_mail, pre_wp_mail, wp_mail_from, wp_mail_from_name,
// wp_mail_content_type, wp_mail_charset, phpmailer_init, wp_mail_failed, wp_mail_succeeded). Standard-Mailer bleibt PHP mail().
require_once __DIR__ . '/PHPMailer/PHPMailer.php';

/** Anschrift-Liste ("Name <a@b>, c@d") als Liste aus [Adresse, Name]. */
function _elvado_mail_addr_list($v): array {
    $o = [];
    foreach ((array) $v as $item) {
        foreach (\PHPMailer\PHPMailer\PHPMailer::parseAddresses(str_replace(["\r", "\n", "\0"], '', (string) $item)) as $a) {
            if ($a['address'] !== '') $o[] = [$a['address'], $a['name']];
        }
    }
    return $o;
}

/** Eigentlicher Versand; Rückgabe true/false wie wp_mail(). */
function _elvado_wp_mail_run($to, $subject, $message, $headers, $attachments): bool {
    global $phpmailer;
    $mail_data = compact('to', 'subject', 'message', 'headers', 'attachments');
    if (!($phpmailer instanceof \PHPMailer\PHPMailer\PHPMailer)) {
        $phpmailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $phpmailer::$validator = static function ($email) { return function_exists('is_email') ? (bool) is_email($email) : filter_var($email, FILTER_VALIDATE_EMAIL) !== false; };
    }
    // Zurücksetzen (pro Aufruf frisch)
    $phpmailer->clearAllRecipients(); $phpmailer->clearAttachments(); $phpmailer->clearCustomHeaders(); $phpmailer->clearReplyTos();
    $phpmailer->Body = ''; $phpmailer->AltBody = ''; $phpmailer->Sender = ''; $phpmailer->ErrorInfo = '';

    // Kopfzeilen auswerten
    if (empty($headers)) $headers = [];
    elseif (!is_array($headers)) $headers = explode("\n", str_replace("\r\n", "\n", (string) $headers));
    else { $t = []; foreach ($headers as $h) foreach (preg_split('/\r\n|\r|\n/', (string) $h) as $l) $t[] = $l; $headers = $t; }
    $content_type = ''; $charset = ''; $boundary = ''; $from_list = null; $cc = $bcc = $reply = $custom = [];
    foreach ($headers as $h) {
        $h = trim((string) $h);
        if ($h === '') continue;
        if (!str_contains($h, ':')) {   // Fortsetzungszeile (boundary/charset)
            if (preg_match('/boundary=["\']?([^"\';\s]+)/i', $h, $m)) $boundary = $m[1];
            if (preg_match('/charset=["\']?([^"\';\s]+)/i', $h, $m)) $charset = $m[1];
            continue;
        }
        [$name, $content] = array_map('trim', explode(':', $h, 2));
        switch (strtolower($name)) {
            case 'content-type':
                if (str_contains($content, ';')) {
                    [$type, $rest] = explode(';', $content, 2);
                    $content_type = trim($type);
                    if (preg_match('/boundary=["\']?([^"\';\s]+)/i', $rest, $m)) $boundary = $m[1];
                    if (preg_match('/charset=["\']?([^"\';\s]+)/i', $rest, $m)) $charset = $m[1];
                } elseif ($content !== '') $content_type = $content;
                break;
            case 'from': $from_list = _elvado_mail_addr_list($content); break;
            case 'cc': $cc = array_merge($cc, _elvado_mail_addr_list($content)); break;
            case 'bcc': $bcc = array_merge($bcc, _elvado_mail_addr_list($content)); break;
            case 'reply-to': $reply = array_merge($reply, _elvado_mail_addr_list($content)); break;
            default: $custom[trim($name)] = trim($content);
        }
    }

    // Absender (Standard: wordpress@<Domain>, Name „WordPress“)
    $from_email = ''; $from_name = 'WordPress';
    if ($from_list) { $from_email = $from_list[0][0]; if ($from_list[0][1] !== '') $from_name = $from_list[0][1]; }
    if ($from_email === '') {
        $sitename = (string) (parse_url(function_exists('network_home_url') ? network_home_url() : home_url(), PHP_URL_HOST) ?: 'localhost');
        if (str_starts_with($sitename, 'www.')) $sitename = substr($sitename, 4);
        $from_email = 'wordpress@' . $sitename;
    }
    $from_email = apply_filters('wp_mail_from', $from_email);
    $from_name = apply_filters('wp_mail_from_name', $from_name);
    $fail = function ($e) use ($mail_data, &$phpmailer) {
        $d = $mail_data; $d['phpmailer_exception_code'] = $e->getCode();
        do_action('wp_mail_failed', new WP_Error('wp_mail_failed', $e->getMessage(), $d));
        return false;
    };
    try { $phpmailer->setFrom($from_email, $from_name, false); } catch (\PHPMailer\PHPMailer\Exception $e) { return $fail($e); }

    $phpmailer->Subject = (string) $subject;
    $phpmailer->Body = (string) $message;
    $phpmailer->isMail();

    // Empfänger: ungültige Adressen werden übersprungen
    $tos = is_array($to) ? $to : explode(',', (string) $to);
    foreach ([['addAddress', _elvado_mail_addr_list($tos)], ['addCC', $cc], ['addBCC', $bcc], ['addReplyTo', $reply]] as [$fn, $list]) {
        foreach ($list as [$addr, $nm]) { try { $phpmailer->$fn($addr, $nm); } catch (\PHPMailer\PHPMailer\Exception $e) { continue; } }
    }

    if ($content_type === '') $content_type = 'text/plain';
    $content_type = apply_filters('wp_mail_content_type', $content_type);
    $phpmailer->ContentType = $content_type;
    if ($content_type === 'text/html') $phpmailer->isHTML(true);
    if ($charset === '') $charset = function_exists('get_bloginfo') ? (string) get_bloginfo('charset') : 'UTF-8';
    $phpmailer->CharSet = apply_filters('wp_mail_charset', $charset ?: 'UTF-8');

    foreach ($custom as $n => $v) { try { $phpmailer->addCustomHeader($n, $v); } catch (\PHPMailer\PHPMailer\Exception $e) { continue; } }
    if (stripos($content_type, 'multipart') !== false && $boundary !== '') {
        try { $phpmailer->addCustomHeader('Content-Type', $content_type . "; boundary=\"" . $boundary . '"'); } catch (\PHPMailer\PHPMailer\Exception $e) {}
    }

    // Anhänge: Liste von Pfaden oder Name => Pfad
    if (!empty($attachments)) {
        if (!is_array($attachments)) $attachments = explode("\n", str_replace("\r\n", "\n", (string) $attachments));
        foreach ($attachments as $k => $path) {
            $path = trim((string) $path);
            if ($path === '') continue;
            try { $phpmailer->addAttachment($path, is_string($k) ? $k : ''); } catch (\PHPMailer\PHPMailer\Exception $e) { continue; }
        }
    }

    do_action_ref_array('phpmailer_init', [&$phpmailer]);
    $mail_data = compact('to', 'subject', 'message', 'headers', 'attachments');
    try {
        $sent = $phpmailer->send();
        if ($sent) { do_action('wp_mail_succeeded', $mail_data); return true; }
        return $fail(new \PHPMailer\PHPMailer\Exception($phpmailer->ErrorInfo ?: 'E-Mail konnte nicht gesendet werden.'));
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        return $fail($e);
    }
}

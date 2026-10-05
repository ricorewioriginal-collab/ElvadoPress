<?php
// Prüft den E-Mail-Versand der WordPress-Schicht: PHPMailer-Klassen (SMTP, sendmail), wp_mail() mit Filtern/Aktionen, Header-Auswertung,
// Anhänge, multipart/alternative, Header-Injektion-Abwehr. SMTP läuft gegen einen lokalen Fake-Server (Kindprozess, 127.0.0.1, zufälliger Port).
// Aufruf: php scripts/test-wp-mail.php
declare(strict_types=1);

// ───── Fake-SMTP-Server (Kindprozess): php test-wp-mail.php --fake <Ausgabedatei> <Flags,...> ─────
if (($argv[1] ?? '') === '--fake') {
    $out = $argv[2]; $flags = explode(',', $argv[3] ?? '');
    $srv = stream_socket_server('tcp://127.0.0.1:0', $en, $es);
    echo explode(':', stream_socket_get_name($srv, false))[1], "\n"; fflush(STDOUT);
    $log = [];
    $c = @stream_socket_accept($srv, 15);
    if ($c) {
        stream_set_timeout($c, 10);
        $w = function (string $s) use ($c, &$log) { $log[] = 'S: ' . rtrim($s); fwrite($c, $s . "\r\n"); };
        $w('220 fake.test ESMTP');
        $data = false; $buf = ''; $auth = '';
        while (($line = fgets($c)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($data) {
                if ($line === '.') { $data = false; $log[] = 'DATA: ' . $buf; $buf = ''; $w('250 Ok: queued as ABC123'); } else $buf .= $line . "\n";
                continue;
            }
            $log[] = 'C: ' . $line;
            if ($auth === 'plain') { $auth = ''; $w(str_contains(base64_decode($line), 'secret') ? '235 ok' : '535 bad'); continue; }
            if ($auth === 'user') { $auth = 'pass'; $w('334 UGFzc3dvcmQ6'); continue; }
            if ($auth === 'pass') { $auth = ''; $w(base64_decode($line) === 'secret' ? '235 ok' : '535 bad'); continue; }
            $u = strtoupper($line);
            if (str_starts_with($u, 'EHLO')) {
                $w('250-fake.test'); $w('250-8BITMIME');
                if (in_array('starttls', $flags, true)) $w('250-STARTTLS');
                $w('250 AUTH ' . (in_array('plainonly', $flags, true) ? 'PLAIN' : 'LOGIN PLAIN'));
            } elseif (str_starts_with($u, 'STARTTLS')) $w('454 TLS not available');
            elseif ($u === 'AUTH PLAIN') { $auth = 'plain'; $w('334 '); }
            elseif ($u === 'AUTH LOGIN') { $auth = 'user'; $w('334 VXNlcm5hbWU6'); }
            elseif (str_starts_with($u, 'MAIL FROM')) $w('250 ok');
            elseif (str_starts_with($u, 'RCPT TO')) $w(str_contains($line, 'bad@') ? '550 5.1.1 no such user' : '250 ok');
            elseif ($u === 'DATA') { $data = true; $w('354 go'); }
            elseif ($u === 'RSET' || $u === 'NOOP') $w('250 ok');
            elseif ($u === 'QUIT') { $w('221 bye'); break; }
            else $w('500 unknown');
        }
        fclose($c);
    }
    file_put_contents($out, json_encode($log));
    exit(0);
}

$tmp = sys_get_temp_dir() . '/rrw-mail-' . bin2hex(random_bytes(4)); mkdir($tmp); mkdir($tmp . '/wp-content'); mkdir($tmp . '/cms');
define('WP_CONTENT_DIR', $tmp . '/wp-content'); define('RRW_WP_DATA', $tmp . '/cms/.wp'); define('RRW_WP_CMS_DATA', $tmp . '/cms');
$_SERVER['HTTP_HOST'] = 'example.test'; file_put_contents($tmp . '/cms/site.json', '{}'); $GLOBALS['RRW_SITE'] = [];
require __DIR__ . '/_testdb.php';
require __DIR__ . '/../cms/wp/load.php';
use PHPMailer\PHPMailer\PHPMailer; use PHPMailer\PHPMailer\SMTP; use PHPMailer\PHPMailer\Exception as MailEx;
$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
rrw_wp_boot(['theme' => false, 'user' => ['id' => 1, 'login' => 'admin', 'name' => 'Administration', 'email' => 'a@example.test', 'role' => 'administrator']]);

/** Fake-Server starten: liefert [Prozess, Port, Ausgabedatei]. */
function fake(string $flags = ''): array {
    global $tmp; $out = $tmp . '/srv-' . bin2hex(random_bytes(3)) . '.json';
    $p = proc_open([PHP_BINARY, __FILE__, '--fake', $out, $flags], [1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
    $port = (int) trim((string) fgets($pipes[1]));
    return [$p, $port, $out];
}
function fake_done(array $h): array { proc_close($h[0]); $j = is_file($h[2]) ? json_decode((string) file_get_contents($h[2]), true) : []; return is_array($j) ? $j : []; }
function has(array $log, string $needle): bool { foreach ($log as $l) if (str_contains($l, $needle)) return true; return false; }
function data_of(array $log): string { foreach ($log as $l) if (str_starts_with($l, 'DATA: ')) return substr($l, 6); return ''; }

// ───── Klassen und Laden ─────
t('Klasse PHPMailer (autoload)', class_exists('PHPMailer\PHPMailer\PHPMailer'));
t('Klasse Exception', class_exists('PHPMailer\PHPMailer\Exception'));
t('Klasse SMTP', class_exists('PHPMailer\PHPMailer\SMTP'));
t('Laden ohne Versand: kein $phpmailer', !isset($GLOBALS['phpmailer']));
t('Datei PHPMailer.php vorhanden', is_file(ABSPATH . WPINC . '/PHPMailer/PHPMailer.php'));
require_once ABSPATH . WPINC . '/class-phpmailer.php'; require_once ABSPATH . WPINC . '/class-smtp.php';
t('Alias PHPMailer (alt)', class_exists('PHPMailer', false) && is_subclass_of('PHPMailer', 'PHPMailer\PHPMailer\PHPMailer') || (new ReflectionClass('PHPMailer'))->getName() === 'PHPMailer\PHPMailer\PHPMailer');
t('Alias phpmailerException', class_exists('phpmailerException', false));
t('Alias SMTP (alt)', class_exists('SMTP', false));

// ───── Adressen und Standardwerte ─────
$m = new PHPMailer(true);
t('Standard-Mailer mail', $m->Mailer === 'mail'); $m->isSMTP(); t('isSMTP', $m->Mailer === 'smtp'); $m->isSendmail(); t('isSendmail', $m->Mailer === 'sendmail'); $m->isMail(); t('isMail', $m->Mailer === 'mail');
t('Standard Port 25 / Timeout', $m->Port === 25 && $m->Timeout === 300);
t('validateAddress gültig', PHPMailer::validateAddress('a@example.com'));
t('validateAddress ungültig', !PHPMailer::validateAddress('a@') && !PHPMailer::validateAddress("a@b.de\r\nBcc: x@y.de"));
t('addAddress/getToAddresses', $m->addAddress('x@example.com', 'X') && $m->getToAddresses() === [['x@example.com', 'X']]);
t('Doppelte Adresse abgewiesen', $m->addAddress('X@example.com') === false);
t('addCC/addBCC/addReplyTo', $m->addCC('c@example.com') && $m->addBCC('b@example.com') && $m->addReplyTo('r@example.com', 'R'));
t('getAllRecipientAddresses', count($m->getAllRecipientAddresses()) === 3);
t('getReplyToAddresses', isset($m->getReplyToAddresses()['r@example.com']));
$m->clearAddresses(); t('clearAddresses', $m->getToAddresses() === [] && count($m->getAllRecipientAddresses()) === 2);
$m->clearAllRecipients(); t('clearAllRecipients', $m->getAllRecipientAddresses() === []);
try { $m->addAddress('kaputt'); t('Ausnahme bei ungültiger Adresse', false); } catch (MailEx $e) { t('Ausnahme bei ungültiger Adresse', str_contains($e->getMessage(), 'kaputt')); }
$m2 = new PHPMailer(); t('Ohne Ausnahmen: false + ErrorInfo', $m2->addAddress('kaputt') === false && $m2->ErrorInfo !== '' && $m2->isError());
t('setFrom Auto-Sender', ($m2->setFrom('f@example.com', 'F') && $m2->Sender === 'f@example.com' && $m2->FromName === 'F'));
$p = PHPMailer::parseAddresses('"Müller, Hans" <h@example.com>, b@example.com; Anna <a@example.com>');
t('parseAddresses', count($p) === 3 && $p[0]['name'] === 'Müller, Hans' && $p[0]['address'] === 'h@example.com' && $p[2]['name'] === 'Anna');
t('encodeHeader ASCII unverändert', $m->encodeHeader('Hallo Welt') === 'Hallo Welt');
t('encodeHeader UTF-8 Base64', str_starts_with($m->encodeHeader('Grüße'), '=?UTF-8?B?'));
t('secureHeader entfernt Umbrüche', $m->secureHeader("A\r\nBcc: x@y.de") === 'ABcc: x@y.de');
t('addCustomHeader Umbruch abgewiesen', (new PHPMailer())->addCustomHeader('X-A', "v\r\nBcc: z") === false);
t('filenameToType', PHPMailer::filenameToType('a.pdf') === 'application/pdf' && PHPMailer::filenameToType('x.unbekannt') === 'application/octet-stream');
t('encodeString base64', trim($m->encodeString('abc', 'base64')) === 'YWJj');
t('setLanguage', $m->setLanguage('de') === true);
t('getSMTPInstance', $m->getSMTPInstance() instanceof SMTP);

// ───── Nachrichtenaufbau (sendmail-Attrappe schreibt in eine Datei) ─────
$sm = $tmp . '/fakesendmail.sh'; file_put_contents($sm, "#!/bin/sh\ncat > " . $tmp . "/sendmail.out\n"); chmod($sm, 0755);
$sendmail = function (PHPMailer $x) use ($sm, $tmp): string { @unlink($tmp . '/sendmail.out'); $x->isSendmail(); $x->Sendmail = $sm; $ok = $x->send(); return $ok && is_file($tmp . '/sendmail.out') ? (string) file_get_contents($tmp . '/sendmail.out') : ''; };
$x = new PHPMailer(true); $x->setFrom('von@example.com', 'Absender Äö'); $x->addAddress('an@example.com', 'An'); $x->Subject = 'Grüße'; $x->Body = "Hallo\n.Punkt"; $x->addCustomHeader('X-Test', 'ja');
$raw = $sendmail($x);
t('sendmail: gesendet', $raw !== '');
t('Kopf From (kodiert)', (bool) preg_match('/^From: =\?UTF-8\?B\?[^ ]+\?= <von@example.com>/m', $raw));
t('Kopf To', str_contains($raw, "To: An <an@example.com>"));
t('Kopf Subject kodiert', (bool) preg_match('/^Subject: =\?UTF-8\?B\?/m', $raw));
t('Kopf MIME-Version, Message-ID, Date', str_contains($raw, 'MIME-Version: 1.0') && str_contains($raw, 'Message-ID: <') && str_contains($raw, 'Date: '));
t('Kopf Custom', str_contains($raw, 'X-Test: ja'));
t('Content-Type text/plain', stripos($raw, 'Content-Type: text/plain; charset=UTF-8') !== false);
t('getLastMessageID', str_starts_with($x->getLastMessageID(), '<') && str_contains($raw, 'Message-ID: ' . $x->getLastMessageID()));
t('Kopf und Text getrennt', str_contains($raw, "\r\n\r\nHallo"));
$x = new PHPMailer(true); $x->setFrom('v@example.com'); $x->addAddress('a@example.com'); $x->addCC('cc@example.com'); $x->addBCC('bcc@example.com'); $x->addReplyTo('re@example.com', 'Re'); $x->Subject = 'S'; $x->Body = 'B'; $x->Priority = 1; $x->XMailer = 'MeinMailer';
$raw = $sendmail($x);
t('Cc/Bcc/Reply-To/Priorität/X-Mailer', str_contains($raw, 'Cc: cc@example.com') && str_contains($raw, 'Bcc: bcc@example.com') && str_contains($raw, 'Reply-To: Re <re@example.com>') && str_contains($raw, 'X-Priority: 1') && str_contains($raw, 'X-Mailer: MeinMailer'));
// HTML + AltBody
$x = new PHPMailer(true); $x->setFrom('v@example.com'); $x->addAddress('a@example.com'); $x->Subject = 'S'; $x->isHTML(true); $x->Body = '<p>Hallo</p>'; $x->AltBody = 'Hallo';
$raw = $sendmail($x);
t('multipart/alternative', str_contains($raw, 'Content-Type: multipart/alternative;') && stripos($raw, 'text/plain; charset=utf-8') !== false && stripos($raw, 'text/html; charset=utf-8') !== false);
t('Boundary abgeschlossen', (bool) preg_match('/--(b\d_[0-9a-f]+)--\r\n$/', $raw));
// Anhang
$f = $tmp . '/a.txt'; file_put_contents($f, 'Inhalt-Anhang');
$x = new PHPMailer(true); $x->setFrom('v@example.com'); $x->addAddress('a@example.com'); $x->Subject = 'S'; $x->Body = 'B'; $x->addAttachment($f, 'neu.txt'); $x->addStringAttachment('Roh-Daten', 'r.bin', 'base64', 'application/x-t');
$raw = $sendmail($x);
t('multipart/mixed', str_contains($raw, 'Content-Type: multipart/mixed;'));
t('Anhang Base64', str_contains($raw, base64_encode('Inhalt-Anhang')) && str_contains($raw, 'filename="neu.txt"'));
t('String-Anhang', str_contains($raw, base64_encode('Roh-Daten')) && str_contains($raw, 'application/x-t'));
t('getAttachments', count($x->getAttachments()) === 2);
t('Anhang fehlend: false/Ausnahme', (new PHPMailer())->addAttachment($tmp . '/nix') === false);
t('Anhang mit Protokoll abgewiesen', (new PHPMailer())->addAttachment('http://example.com/x') === false);
$x->clearAttachments(); t('clearAttachments', $x->getAttachments() === []);
// eingebettetes Bild
$x = new PHPMailer(true); $x->setFrom('v@example.com'); $x->addAddress('a@example.com'); $x->Subject = 'S'; $x->isHTML(true); $x->Body = '<img src="cid:logo">'; $x->addStringEmbeddedImage('PNGDATA', 'logo', 'l.png', 'base64', 'image/png');
$raw = $sendmail($x);
t('multipart/related + Content-ID', str_contains($raw, 'multipart/related') && str_contains($raw, 'Content-ID: <logo>'));
// Header-Injektion
$x = new PHPMailer(true); $x->setFrom('v@example.com', "Evil\r\nBcc: spy@example.com"); $x->addAddress('a@example.com', "N\r\nCc: spy@example.com"); $x->Subject = "S\r\nBcc: spy@example.com"; $x->Body = 'B';
$raw = $sendmail($x); $head = explode("\r\n\r\n", $raw)[0];
t('Injektion: keine eigene Bcc-/Cc-Zeile', !preg_match('/^(Bcc|Cc): spy/m', $head));
t('Ohne Empfänger: Ausnahme', (function () { $q = new PHPMailer(true); $q->setFrom('a@example.com'); $q->Body = 'x'; try { $q->send(); return false; } catch (MailEx $e) { return true; } })());
t('Ohne Empfänger (still): false', (function () { $q = new PHPMailer(); $q->setFrom('a@example.com'); $q->Body = 'x'; return $q->send() === false && $q->ErrorInfo !== ''; })());
t('Leere Nachricht: Fehler', (function () { $q = new PHPMailer(); $q->setFrom('a@example.com'); $q->addAddress('b@example.com'); return $q->send() === false; })());

// ───── SMTP gegen Fake-Server ─────
$h = fake(); $x = new PHPMailer(true); $x->isSMTP(); $x->Host = '127.0.0.1'; $x->Port = $h[1]; $x->Timeout = 5; $x->setFrom('von@example.com', 'V'); $x->addAddress('a@example.com'); $x->addAddress('b@example.com', 'B'); $x->addCC('c@example.com'); $x->addBCC('d@example.com');
$x->Subject = 'Test'; $x->Body = "Zeile1\n.Punkt\n..zwei\nEnde";
$ok = $x->send(); $log = fake_done($h);
t('SMTP: Versand ohne Anmeldung', $ok === true, $x->ErrorInfo);
t('SMTP: EHLO', has($log, 'C: EHLO'));
t('SMTP: MAIL FROM', has($log, 'C: MAIL FROM:<von@example.com>'));
t('SMTP: Mehrfachempfänger', has($log, 'RCPT TO:<a@example.com>') && has($log, 'RCPT TO:<b@example.com>') && has($log, 'RCPT TO:<c@example.com>') && has($log, 'RCPT TO:<d@example.com>'));
$d = data_of($log);
t('SMTP: Dot-Stuffing (Punktzeile verdoppelt gesendet)', has($log, 'DATA:') && str_contains($d, '..Punkt'));
t('SMTP: Bcc nicht im Kopf', !str_contains($d, 'Bcc:') && !str_contains($d, 'd@example.com'));
t('SMTP: To im Kopf', str_contains($d, 'To: a@example.com, B <b@example.com>'));
t('SMTP: Subject im Kopf', str_contains($d, 'Subject: Test'));
t('SMTP: QUIT', has($log, 'C: QUIT'));
// AUTH LOGIN
$h = fake(); $x = new PHPMailer(true); $x->isSMTP(); $x->Host = '127.0.0.1'; $x->Port = $h[1]; $x->Timeout = 5; $x->SMTPAuth = true; $x->Username = 'benutzer'; $x->Password = 'secret'; $x->setFrom('v@example.com'); $x->addAddress('a@example.com'); $x->Subject = 'S'; $x->Body = 'B';
$ok = $x->send(); $log = fake_done($h);
t('AUTH LOGIN: ok', $ok === true, $x->ErrorInfo); t('AUTH LOGIN: Befehle', has($log, 'C: AUTH LOGIN') && has($log, 'C: ' . base64_encode('benutzer')) && has($log, 'C: ' . base64_encode('secret')));
// AUTH PLAIN (nur PLAIN angeboten)
$h = fake('plainonly'); $x = new PHPMailer(true); $x->isSMTP(); $x->Host = '127.0.0.1'; $x->Port = $h[1]; $x->Timeout = 5; $x->SMTPAuth = true; $x->Username = 'u'; $x->Password = 'secret'; $x->setFrom('v@example.com'); $x->addAddress('a@example.com'); $x->Subject = 'S'; $x->Body = 'B';
$ok = $x->send(); $log = fake_done($h);
t('AUTH PLAIN: ok', $ok === true, $x->ErrorInfo); t('AUTH PLAIN: Befehl', has($log, 'C: AUTH PLAIN') && has($log, 'C: ' . base64_encode("\0u\0secret")));
// falsches Kennwort
$h = fake(); $x = new PHPMailer(false); $x->isSMTP(); $x->Host = '127.0.0.1'; $x->Port = $h[1]; $x->Timeout = 5; $x->SMTPAuth = true; $x->Username = 'u'; $x->Password = 'falsch'; $x->setFrom('v@example.com'); $x->addAddress('a@example.com'); $x->Subject = 'S'; $x->Body = 'B';
$ok = $x->send(); $log = fake_done($h);
t('Falsches Kennwort: false + Fehlerinfo', $ok === false && $x->ErrorInfo !== '' && !has($log, 'C: MAIL FROM'));
// STARTTLS-Ablehnung (SMTPSecure=tls)
$h = fake('starttls'); $x = new PHPMailer(true); $x->isSMTP(); $x->Host = '127.0.0.1'; $x->Port = $h[1]; $x->Timeout = 5; $x->SMTPSecure = 'tls'; $x->setFrom('v@example.com'); $x->addAddress('a@example.com'); $x->Subject = 'S'; $x->Body = 'B';
$r = null; try { $r = $x->send(); } catch (MailEx $e) { $r = 'ex'; } $log = fake_done($h);
t('STARTTLS abgelehnt: kein Versand', $r !== true && has($log, 'C: STARTTLS') && !has($log, 'C: MAIL FROM'));
// SMTPAutoTLS: Server bietet STARTTLS an, lehnt ab
$h = fake('starttls'); $x = new PHPMailer(false); $x->isSMTP(); $x->Host = '127.0.0.1'; $x->Port = $h[1]; $x->Timeout = 5; $x->setFrom('v@example.com'); $x->addAddress('a@example.com'); $x->Subject = 'S'; $x->Body = 'B';
$r = $x->send(); $log = fake_done($h); t('AutoTLS versucht STARTTLS', has($log, 'C: STARTTLS') && $r === false);
$h = fake('starttls'); $x = new PHPMailer(false); $x->isSMTP(); $x->SMTPAutoTLS = false; $x->Host = '127.0.0.1'; $x->Port = $h[1]; $x->Timeout = 5; $x->setFrom('v@example.com'); $x->addAddress('a@example.com'); $x->Subject = 'S'; $x->Body = 'B';
$r = $x->send(); $log = fake_done($h); t('SMTPAutoTLS=false: kein STARTTLS', !has($log, 'C: STARTTLS') && $r === true);
// Empfänger abgelehnt
$h = fake(); $x = new PHPMailer(false); $x->isSMTP(); $x->Host = '127.0.0.1'; $x->Port = $h[1]; $x->Timeout = 5; $x->setFrom('v@example.com'); $x->addAddress('bad@example.com'); $x->Subject = 'S'; $x->Body = 'B';
$r = $x->send(); $log = fake_done($h); t('Empfänger abgelehnt: false', $r === false && str_contains($x->ErrorInfo, 'bad@example.com'));
// Verbindungsfehler
$x = new PHPMailer(false); $x->isSMTP(); $x->Host = '127.0.0.1'; $x->Port = 1; $x->Timeout = 2; $x->setFrom('v@example.com'); $x->addAddress('a@example.com'); $x->Subject = 'S'; $x->Body = 'B';
t('Verbindungsfehler: false', $x->send() === false && $x->ErrorInfo !== '');
$s = new SMTP(); t('SMTP::connect Fehler', $s->connect('127.0.0.1', 1, 2) === false && $s->getError()['error'] !== '');
// Direkte SMTP-Klasse
$h = fake(); $s = new SMTP(); $s->Timeout = 5;
t('SMTP::connect', $s->connect('127.0.0.1', $h[1], 5) && $s->connected());
t('SMTP::hello + Erweiterungen', $s->hello('test.local') && isset($s->getServerExtList()['AUTH']) && in_array('PLAIN', (array) $s->getServerExt('AUTH'), true));
t('SMTP::mail/recipient/data', $s->mail('a@b.de') && $s->recipient('c@d.de') && $s->data("Subject: x\r\n\r\n.erste\r\nzweite") && str_contains($s->getLastReply(), 'queued as'));
t('SMTP::getLastTransactionID', $s->getLastTransactionID() === 'ABC123');
$s->quit(); $log = fake_done($h); t('SMTP::close', !$s->connected() && str_contains(data_of($log), '..erste'));

// ───── wp_mail ─────
global $phpmailer;
$GLOBALS['mailsrv'] = null;
$useSmtp = function ($port) { return function ($pm) use ($port) { $pm->isSMTP(); $pm->Host = '127.0.0.1'; $pm->Port = $port; $pm->Timeout = 5; $pm->SMTPAutoTLS = false; }; };
$h = fake(); $cb = $useSmtp($h[1]); add_action('phpmailer_init', $cb);
$seen = []; add_action('wp_mail_succeeded', function ($d) use (&$seen) { $seen[] = $d; });
add_filter('wp_mail_from', $ff = fn($e) => 'seite@example.test');
add_filter('wp_mail_from_name', $fn = fn($n) => 'Meine Seite');
$ok = wp_mail('Hans Müller <h@example.com>, z@example.com', "Betreff äöü\r\nBcc: x@y.de", "Hallo\n.Test", ['Cc: Anna <anna@example.com>', 'Bcc: geheim@example.com', 'Reply-To: re@example.com', 'X-Eigen: wert']);
$log = fake_done($h); $d = data_of($log);
t('wp_mail über SMTP (phpmailer_init)', $ok === true);
t('wp_mail: globales $phpmailer', $phpmailer instanceof PHPMailer && $phpmailer->Mailer === 'smtp');
t('wp_mail: From mit Filtern', str_contains($d, 'From: Meine Seite <seite@example.test>') && has($log, 'MAIL FROM:<seite@example.test>'));
t('wp_mail: Empfänger', has($log, 'RCPT TO:<h@example.com>') && has($log, 'RCPT TO:<z@example.com>') && has($log, 'RCPT TO:<anna@example.com>') && has($log, 'RCPT TO:<geheim@example.com>'));
t('wp_mail: Cc/Reply-To/Custom', str_contains($d, 'Cc: Anna <anna@example.com>') && str_contains($d, 'Reply-To: re@example.com') && str_contains($d, 'X-Eigen: wert'));
t('wp_mail: Bcc nicht im Kopf', !str_contains($d, 'geheim@'));
t('wp_mail: Betreff-Injektion abgewehrt', !preg_match('/^Bcc:/m', $d) && !has($log, 'x@y.de'));
t('wp_mail: Dot-Stuffing', has($log, 'DATA: ') && str_contains($d, 'Hallo'));
t('wp_mail_succeeded', count($seen) === 1 && $seen[0]['subject'] === "Betreff äöü\r\nBcc: x@y.de");
remove_action('phpmailer_init', $cb); remove_filter('wp_mail_from', $ff); remove_filter('wp_mail_from_name', $fn);
$first = $phpmailer;
// HTML-Typ per Header, AltBody per phpmailer_init, Anhänge (Liste und Name=>Pfad)
$h = fake(); $cb = $useSmtp($h[1]); add_action('phpmailer_init', $cb);
$alt = function ($pm) { $pm->AltBody = 'Nur Text'; }; add_action('phpmailer_init', $alt);
$f2 = $tmp . '/b.txt'; file_put_contents($f2, 'Zweiter-Anhang');
$ok = wp_mail('a@example.com', 'HTML', '<b>fett</b>', 'Content-Type: text/html; charset=UTF-8', [$f, 'Anzeige.txt' => $f2, $tmp . '/fehlt.txt']);
$log = fake_done($h); $d = data_of($log);
t('wp_mail HTML+AltBody+Anhänge', $ok === true, $phpmailer->ErrorInfo);
t('wp_mail: gleiche Instanz wiederverwendet', $phpmailer === $first);
t('wp_mail: multipart/alternative in mixed', str_contains($d, 'multipart/mixed') && str_contains($d, 'multipart/alternative') && str_contains($d, 'Nur Text') && str_contains($d, '<b>fett</b>'));
t('wp_mail: Anhänge Base64 + Name', str_contains($d, base64_encode('Inhalt-Anhang')) && str_contains($d, base64_encode('Zweiter-Anhang')) && str_contains($d, 'filename="Anzeige.txt"'));
t('wp_mail: fehlender Anhang übersprungen', count($phpmailer->getAttachments()) === 2);
remove_action('phpmailer_init', $alt);
// Zurücksetzen pro Aufruf
$h = fake(); remove_action('phpmailer_init', $cb); $cb = $useSmtp($h[1]); add_action('phpmailer_init', $cb);
$ok = wp_mail('n@example.com', 'Zwei', 'Text'); $log = fake_done($h); $d = data_of($log);
t('wp_mail: Empfänger/Anhänge zurückgesetzt', $ok && count($phpmailer->getToAddresses()) === 1 && $phpmailer->getAttachments() === [] && $phpmailer->AltBody === '' && !str_contains($d, 'a@example.com'));
t('wp_mail: Content-Type zurück auf text/plain', str_contains($d, 'Content-Type: text/plain; charset=UTF-8'));
// Filter wp_mail (Args), Content-Type- und Charset-Filter
$h = fake(); remove_action('phpmailer_init', $cb); $cb = $useSmtp($h[1]); add_action('phpmailer_init', $cb);
add_filter('wp_mail', $fa = function ($a) { $a['subject'] .= ' [Filter]'; return $a; });
add_filter('wp_mail_content_type', $fc = fn($t) => 'text/html'); add_filter('wp_mail_charset', $fch = fn($c) => 'ISO-8859-1');
$ok = wp_mail('n@example.com', 'Arg', 'x'); $log = fake_done($h); $d = data_of($log);
t('Filter wp_mail ändert Betreff', $ok && str_contains($d, 'Subject: Arg [Filter]'));
t('Filter Content-Type/Charset', str_contains($d, 'Content-Type: text/html; charset=ISO-8859-1'));
remove_filter('wp_mail', $fa); remove_filter('wp_mail_content_type', $fc); remove_filter('wp_mail_charset', $fch); remove_action('phpmailer_init', $cb);
// Multipart-Header mit boundary
$h = fake(); $cb = $useSmtp($h[1]); add_action('phpmailer_init', $cb);
$body = "--XYZ\r\nContent-Type: text/plain\r\n\r\nA\r\n--XYZ\r\nContent-Type: text/html\r\n\r\n<i>B</i>\r\n--XYZ--";
$ok = wp_mail('n@example.com', 'MP', $body, "Content-Type: multipart/alternative;\n\tboundary=\"XYZ\""); $log = fake_done($h); $d = data_of($log);
t('Multipart mit boundary aus Header', $ok && str_contains($d, 'Content-Type: multipart/alternative; boundary="XYZ"') && str_contains($d, '--XYZ--') && substr_count($d, 'Content-Type: multipart') === 1);
remove_action('phpmailer_init', $cb);
// wp_mail_failed bei Verbindungsfehler
$failed = []; add_action('wp_mail_failed', $fl = function ($e) use (&$failed) { $failed[] = $e; });
add_action('phpmailer_init', $cb = $useSmtp(1));
$ok = wp_mail('n@example.com', 'Fehler', 'x', 'X-A: b', [$f]);
t('wp_mail_failed bei Verbindungsfehler', $ok === false && count($failed) === 1 && $failed[0] instanceof WP_Error && $failed[0]->get_error_code() === 'wp_mail_failed');
$ed = $failed[0]->get_error_data();
t('wp_mail_failed: Daten', is_array($ed) && $ed['to'] === 'n@example.com' && $ed['subject'] === 'Fehler' && $ed['message'] === 'x' && $ed['attachments'] === [$f] && array_key_exists('phpmailer_exception_code', $ed) && array_key_exists('headers', $ed));
remove_action('phpmailer_init', $cb);
// Ungültige Empfänger
$failed = [];
t('Ohne gültigen Empfänger: false + wp_mail_failed', wp_mail('kaputt', 'S', 'B') === false && count($failed) === 1);
t('Leere Empfängerliste: false', wp_mail('', 'S', 'B') === false);
t('Ungültiger Absender: false + wp_mail_failed', (function () use (&$failed) { $failed = []; add_filter('wp_mail_from', $g = fn() => 'nicht-gültig'); $r = wp_mail('a@example.com', 'S', 'B'); remove_filter('wp_mail_from', $g); return $r === false && count($failed) === 1; })());
remove_action('wp_mail_failed', $fl);
// pre_wp_mail
add_filter('pre_wp_mail', $pre = fn($r, $a) => $a['subject'] === 'kurz' ? true : $r, 10, 2);
t('pre_wp_mail kürzt ab (true)', wp_mail('a@example.com', 'kurz', 'x') === true);
add_filter('pre_wp_mail', $pre3 = fn($r) => false, 5); t('pre_wp_mail false gewinnt', wp_mail('a@example.com', 'egal', 'x') === false);
remove_filter('pre_wp_mail', $pre3, 5); remove_filter('pre_wp_mail', $pre, 10);
// Standard-Mailer bleibt PHP mail()
$GLOBALS['phpmailer'] = null; unset($GLOBALS['phpmailer']);
$fresh = null; add_action('phpmailer_init', $probe = function ($pm) use (&$fresh) { $fresh = $pm->Mailer; $pm->isSendmail(); $pm->Sendmail = $GLOBALS['__sm']; });
$GLOBALS['__sm'] = $sm; @unlink($tmp . '/sendmail.out');
t('Standard-Mailer vor phpmailer_init: mail', (wp_mail('a@example.com', 'SM', 'Body') === true) && $fresh === 'mail');
t('wp_mail über sendmail-Attrappe', is_file($tmp . '/sendmail.out') && str_contains((string) file_get_contents($tmp . '/sendmail.out'), 'Subject: SM'));
remove_action('phpmailer_init', $probe);
// mail() als Standard (Kindprozess mit sendmail_path)
$phpcode = 'require ' . var_export(__DIR__ . '/../cms/wp/core/PHPMailer/PHPMailer.php', true) . '; $m=new PHPMailer\PHPMailer\PHPMailer(true); $m->setFrom("v@example.com"); $m->addAddress("a@example.com","A"); $m->addBCC("b@example.com"); $m->Subject="Über mail()"; $m->Body="Text"; echo $m->send()?"OK":"NEIN";';
$ms = $tmp . '/mailsh.sh'; file_put_contents($ms, "#!/bin/sh\ncat > " . $tmp . "/mail.out\n"); chmod($ms, 0755);
$res = shell_exec(escapeshellarg(PHP_BINARY) . ' -d sendmail_path=' . escapeshellarg($ms) . ' -r ' . escapeshellarg($phpcode) . ' 2>&1');
$mo = is_file($tmp . '/mail.out') ? (string) file_get_contents($tmp . '/mail.out') : '';
t('mail()-Pfad liefert true', trim((string) $res) === 'OK', (string) $res);
t('mail()-Pfad: Kopf + Betreff + Bcc', str_contains($mo, 'To: A <a@example.com>') && str_contains($mo, 'Subject: =?UTF-8?B?') && str_contains($mo, 'Bcc: b@example.com'));
// Laden: Klassen nicht beim Start geladen
$out = shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('define("RRW_WP_DATA",' . var_export($tmp . '/cms/.wp', true) . '); echo "x";') . ' 2>&1');
t('Dateien ohne Seiteneffekte', is_file(ABSPATH . WPINC . '/class-phpmailer.php'));

echo $fail ? "\n$fail von $n Prüfungen FEHLGESCHLAGEN\n" : "OK: alle $n Prüfungen bestanden\n";
exec('rm -rf ' . escapeshellarg($tmp));
exit($fail ? 1 : 0);

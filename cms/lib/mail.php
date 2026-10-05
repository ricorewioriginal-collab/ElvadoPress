<?php
declare(strict_types=1);
require_once __DIR__.'/pack.php';

// Sehr einfacher E-Mail-Versand über die native PHP mail()-Funktion, bewusst ohne SMTP-Bibliothek:
// Der Produktionsserver läuft unter KeyHelp, dessen lokaler Mailstack (Postfix/Exim) PHP mail()
// bereits bedient (Absenderdomain, SPF/DKIM etc. sind dort serverseitig eingerichtet). Ein SMTP-
// Client mit eigenen Zugangsdaten ist deshalb nicht nötig. In dieser Entwicklungsumgebung gibt es
// keinen Mailserver, mail() liefert hier also immer false zurück – das ist erwartet und kein Fehler
// im Code, siehe rrw_mail_from() für den lokal getesteten Teil (Absenderadresse/Header-Aufbau).
function rrw_cms_admin_url(array $site): string {
    $base=(string)($site['seo']['canonical_base']??rrw_default_canonical_base());
    return rtrim($base,'/').'/cms/';
}
function rrw_mail_from(array $site): string {
    $base=(string)($site['seo']['canonical_base']??rrw_default_canonical_base());
    $host=parse_url($base,PHP_URL_HOST)?:(rrw_pack_available()?'ricorewi-radio.de':'localhost');
    $host=preg_replace('/^www\./','',$host);
    return 'noreply@'.$host;
}
function rrw_send_mail(string $to,string $subject,string $body,string $fromAddress,string $fromName=''): bool {
    if($fromName==='')$fromName=function_exists('rrw_product_title')?rrw_product_title():'RicoReWi Radio CMS';
    if(defined('RRW_DEMO')||!filter_var($to,FILTER_VALIDATE_EMAIL))return false;   // Demo: kein Mailversand
    $subjectEncoded='=?UTF-8?B?'.base64_encode($subject).'?=';
    $fromNameEncoded='=?UTF-8?B?'.base64_encode($fromName).'?=';
    $headers="From: {$fromNameEncoded} <{$fromAddress}>\r\n"
        ."Reply-To: {$fromAddress}\r\n"
        ."Content-Type: text/plain; charset=UTF-8\r\n"
        ."Content-Transfer-Encoding: 8bit\r\n";
    return @mail($to,$subjectEncoded,$body,$headers);
}
function rrw_send_comment_notification_email(array $site,string $toEmail,string $articleTitle,string $commentName,string $commentExcerpt,string $adminUrl): void {
    if($toEmail==='')return;
    $subject='Neuer Kommentar zu „'.$articleTitle.'"';
    $body="Hallo,\n\n{$commentName} hat einen Kommentar zu deinem Beitrag \"{$articleTitle}\" hinterlassen:\n\n\"{$commentExcerpt}\"\n\nIm CMS ansehen und ggf. freigeben: {$adminUrl}\n\n-- \n".(function_exists('rrw_product_title')?rrw_product_title():'RicoReWi Radio CMS')." (automatische Benachrichtigung)";
    rrw_send_mail($toEmail,$subject,$body,rrw_mail_from($site));
}

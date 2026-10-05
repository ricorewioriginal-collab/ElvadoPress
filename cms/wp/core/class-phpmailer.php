<?php
// Alte Einstiegsdatei (WordPress < 5.5): lädt PHPMailer und legt die alten Klassennamen als Alias an.
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
if (!class_exists('PHPMailer', false)) { class_alias('PHPMailer\PHPMailer\PHPMailer', 'PHPMailer'); }
if (!class_exists('phpmailerException', false)) { class_alias('PHPMailer\PHPMailer\Exception', 'phpmailerException'); }
if (!class_exists('SMTP', false)) { class_alias('PHPMailer\PHPMailer\SMTP', 'SMTP'); }

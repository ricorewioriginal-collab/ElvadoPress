<?php
// Alte Einstiegsdatei (WordPress < 5.5): SMTP-Klasse mit globalem Alias.
require_once __DIR__ . '/PHPMailer/SMTP.php';
if (!class_exists('SMTP', false)) { class_alias('PHPMailer\PHPMailer\SMTP', 'SMTP'); }

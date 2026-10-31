<?php
// Router für den PHP-Entwicklungsserver (php -S … scripts/e2e/router.php): bildet die Regeln aus .htaccess nach (gesperrte Ordner, alle unbekannten Pfade → cms/wp-front.php).
// Nur für Tests und lokale Entwicklung.
$root = $_SERVER['DOCUMENT_ROOT'];
$u = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('~^/cms/(data|backups|wp-engine)/~', $u)) {
    http_response_code(403);
    exit;
}
$f = $root . $u;
if (str_starts_with($u, '/cms/') || is_file($f) || is_dir($f)) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/cms/wp-front.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/cms/wp-front.php';
chdir($root . '/cms');
require $root . '/cms/wp-front.php';

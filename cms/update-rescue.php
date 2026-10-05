<?php
declare(strict_types=1);
// cms/update-rescue.php – Notfall-Rückschritt, falls nach einem Update die Verwaltung nicht mehr startet.
// Aufruf im Browser: https://<deine-domain>/cms/update-rescue.php?token=<Notfall-Token> (der Token steht in der Verwaltung unter System → Version & Update,
// bzw. in cms/data/.update/rescue.token). Die Seite zeigt die Sicherungen; ein Klick spielt die gewählte (Standard: neueste) zurück.
// Bewusst ohne Anmeldung und ohne api.php, damit sie auch bei einem defekten CMS funktioniert; geschützt nur durch den Token.
require_once __DIR__ . '/src/autoload.php';

use Elvado\Update\UpdateException;
use Elvado\Update\UpdateService;

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
$svc = UpdateService::forCms(__DIR__, __DIR__ . '/data');
$token = (string)($_POST['token'] ?? $_GET['token'] ?? '');
$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$msg = '';
$ok = $svc->rescueTokenValid($token);
if (!$ok) {
    http_response_code(403);
}
if ($ok && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        $r = $svc->rollback((string)($_POST['snapshot'] ?? ''));
        $msg = 'Zurückgesetzt auf Version ' . $r['to'] . '. Bitte die Verwaltung neu laden.';
    } catch (UpdateException $e) {
        $msg = 'Fehler: ' . $e->getMessage();
    }
}
echo '<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Update-Notfall</title>';
echo '<body style="font-family:system-ui,sans-serif;max-width:640px;margin:40px auto;padding:0 16px;line-height:1.5"><h1>Update-Notfall</h1>';
if (!$ok) {
    echo '<p>Zugriff verweigert. Den Token findest du in der Verwaltung (System → Version &amp; Update) oder in <code>cms/data/.update/rescue.token</code>.</p></body></html>';
    exit;
}
echo '<p>Installierte Version: <b>' . $h($svc->installedVersion()) . '</b></p>';
if ($msg !== '') {
    echo '<p style="padding:10px;background:#eef">' . $h($msg) . '</p>';
}
$snaps = $svc->snapshots();
if ($snaps === []) {
    echo '<p>Es gibt keine Sicherung.</p>';
} else {
    echo '<form method="post"><input type="hidden" name="token" value="' . $h($token) . '"><p>Sicherung zurückspielen:</p><select name="snapshot" style="padding:6px;width:100%">';
    foreach ($snaps as $s) {
        echo '<option value="' . $h($s['name']) . '">Version ' . $h($s['version']) . ' · ' . $h($s['created_at']) . '</option>';
    }
    echo '</select><p><button style="padding:8px 14px" onclick="return confirm(\'Wirklich zurückspielen?\')">Zurückspielen</button></p></form>';
}
echo '</body></html>';

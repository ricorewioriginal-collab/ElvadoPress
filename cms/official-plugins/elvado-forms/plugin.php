<?php
declare(strict_types=1);
if (!isset($np) || !($np instanceof \Elvado\Plugin\Context)) {
    http_response_code(403);   // direkter Aufruf der Datei im Browser: nichts ausführen
    exit;
}
// Elvado Forms – Einstiegspunkt (nur als offizielles, unverändertes Plugin ausgeführt).
require_once __DIR__ . '/lib/Smtp.php';
require_once __DIR__ . '/lib/Forms.php';

use ElvadoPlugin\Forms\Forms;

/** @var \Elvado\Plugin\Context $np */
$fm = new Forms($np);

// Shortcode für Seiten, Beiträge und Widgets: [elvado_form id="kontakt"]
$np->on('wp_ready', function () use ($fm): void {
    add_shortcode('elvado_form', static function ($atts) use ($fm): string {
        $a = shortcode_atts(['id' => ''], is_array($atts) ? $atts : []);
        return $fm->render((string)preg_replace('/[^a-z0-9-]/', '', strtolower((string)$a['id'])));
    });
});
$np->on('tick', [$fm, 'tick']);

// Öffentlich: Formular absenden (JSON oder multipart; Antwort immer JSON)
$np->api('submit', function (array $a) use ($fm): array {
    return $fm->submit($a, $_FILES, (string)($_SERVER['REMOTE_ADDR'] ?? ''));
}, 'public');

$np->api('list_forms', function () use ($fm, $np): array {
    $out = [];
    foreach ($fm->all() as $f) {
        $out[] = Forms::publicForm($f) + ['count' => $fm->count($f['id']), 'shortcode' => '[elvado_form id="' . $f['id'] . '"]'];
    }
    return ['forms' => $out, 'types' => Forms::TYPES, 'uploads' => (bool)$np->setting('allow_uploads')];
}, 'editor');
$np->api('save_form', fn(array $a) => $fm->save((array)($a['form'] ?? [])));
$np->api('delete_form', fn(array $a) => ['ok' => $fm->delete((string)preg_replace('/[^a-z0-9-]/', '', (string)($a['id'] ?? '')), !empty($a['with_data'])), 'message' => 'Formular gelöscht.']);
$np->api('submissions', fn(array $a) => ['items' => $fm->submissions((string)($a['form'] ?? ''), 200)]);
$np->api('delete_submission', fn(array $a) => ['ok' => $fm->deleteSubmission((string)($a['form'] ?? ''), (string)preg_replace('/[^a-f0-9]/', '', (string)($a['id'] ?? ''))), 'message' => 'Einsendung gelöscht.']);
$np->api('download_csv', function (array $a) use ($fm): array {
    $p = $fm->exportCsv((string)preg_replace('/[^a-z0-9-]/', '', (string)($a['form'] ?? '')));
    return $p ? ['file' => $p, 'name' => basename($p), 'mime' => 'text/csv; charset=utf-8'] : ['file' => ''];
});
$np->api('download_upload', function (array $a) use ($fm): array {
    $p = $fm->uploadPath((string)($a['form'] ?? ''), (string)($a['stored'] ?? ''));
    return $p ? ['file' => $p, 'name' => basename($p), 'mime' => 'application/octet-stream'] : ['file' => ''];
});
$np->api('action_test_mail', function () use ($fm, $np): array {
    $to = (string)$np->setting('default_to');
    if ($to === '' && function_exists('rrw_local_users')) {
        foreach (rrw_local_users() as $u) {
            if (!empty($u['email'])) {
                $to = (string)$u['email'];
                break;
            }
        }
    }
    if ($to === '') {
        return ['ok' => false, 'message' => 'Bitte zuerst einen Standard-Empfänger eintragen.'];
    }
    try {
        $fm->sendMail($to, 'Test-E-Mail von ElvadoPress Forms', "Wenn du diese Nachricht liest, funktioniert der E-Mail-Versand.\n", '');
    } catch (\Throwable $e) {
        return ['ok' => false, 'message' => 'Versand fehlgeschlagen: ' . $e->getMessage()];
    }
    return ['ok' => true, 'message' => 'Test-E-Mail an ' . $to . ' gesendet. Bitte Postfach (und Spam-Ordner) prüfen.'];
});
$np->api('overview', function () use ($fm, $np): array {
    $forms = $fm->all();
    $rows = [];
    $total = 0;
    foreach ($forms as $f) {
        $n = $fm->count($f['id']);
        $total += $n;
        $rows[] = [$f['title'], '[elvado_form id="' . $f['id'] . '"]', (string)count($f['fields']), $f['store'] ? (string)$n : 'nicht gespeichert'];
    }
    $smtp = trim((string)$np->setting('smtp_host')) !== '';
    return ['blocks' => [
        ['type' => 'stats', 'items' => [['label' => 'Formulare', 'value' => count($forms)], ['label' => 'Gespeicherte Einsendungen', 'value' => $total]]],
        ['type' => 'checks', 'title' => 'Versand und Schutz', 'items' => [
            ['label' => 'E-Mail-Versand', 'status' => $smtp ? 'ok' : 'info', 'text' => $smtp ? 'SMTP: ' . $np->setting('smtp_host') . ':' . $np->setting('smtp_port') : 'Über PHP mail() – für zuverlässigen Versand SMTP eintragen.'],
            ['label' => 'Datei-Uploads', 'status' => $np->setting('allow_uploads') ? 'info' : 'ok', 'text' => $np->setting('allow_uploads') ? 'Erlaubt (Typ-/Größenprüfung, geschützte Ablage).' : 'Ausgeschaltet.'],
            ['label' => 'Spam-Schutz', 'status' => 'ok', 'text' => 'Honeypot, Zeitfalle, Begrenzung je Besucher/10 Minuten, optionale Rechenfrage – ohne externe Dienste und ohne Speicherung von IP-Adressen.'],
        ]],
        ['type' => 'table', 'title' => 'Formulare', 'columns' => ['Titel', 'Shortcode', 'Felder', 'Einsendungen'], 'rows' => $rows, 'empty' => 'Noch kein Formular – unten „Formulare verwalten“.'],
    ]];
});

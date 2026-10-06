<?php
declare(strict_types=1);
// Elvado Redirects – Einstiegspunkt (nur als offizielles, unverändertes Plugin ausgeführt).
require_once __DIR__ . '/lib/Rules.php';

use ElvadoPlugin\Redirects\Rules;

/** @var \Elvado\Plugin\Context $np */
$r = new Rules($np);

$np->on('slug_changed', [$r, 'slugChanged']);
$np->on('redirects_clean', [$r, 'guard']);

$np->api('overview', function () use ($r): array {
    $a = $r->analyse();
    $b = [['type' => 'stats', 'items' => [
        ['label' => 'Weiterleitungen', 'value' => count($a['rules'])],
        ['label' => 'Schleifen', 'value' => count($a['loops']), 'level' => $a['loops'] ? 'bad' : 'ok'],
        ['label' => 'Ketten', 'value' => count($a['chains']), 'level' => $a['chains'] ? 'warn' : 'ok'],
        ['label' => 'Unbekannte Adressen (404)', 'value' => $a['count404']],
    ]]];
    foreach ($a['loops'] as $l) {
        $b[] = ['type' => 'notice', 'level' => 'bad', 'text' => 'Weiterleitungs-Schleife bei ' . $l];
    }
    foreach ($a['chains'] as $c) {
        $b[] = ['type' => 'notice', 'level' => 'warn', 'text' => 'Kette: ' . $c];
    }
    $rows = [];
    foreach ($a['top404'] as $i) {
        $rows[] = ['cells' => [(string)$i['path'], (string)($i['count'] ?? 0), (string)($i['last'] ?? ''), (string)($i['ref'] ?? '')],
            'actions' => [['label' => 'Weiterleiten …', 'call' => 'create', 'args' => ['from' => (string)$i['path']], 'prompt' => 'Ziel (z. B. /neue-seite/ oder https://…)', 'promptKey' => 'to']]];
    }
    $b[] = ['type' => 'table', 'title' => '404-Monitor (häufigste unbekannte Adressen)', 'columns' => ['Adresse', 'Aufrufe', 'Zuletzt', 'Verweis von'], 'rows' => $rows, 'empty' => 'Keine unbekannten Adressen protokolliert.'];
    $b[] = ['type' => 'text', 'text' => 'Regeln anlegen, bearbeiten und löschen: Werkzeuge › Weiterleitungen.'];
    return ['blocks' => $b];
});
$np->api('create', fn(array $a) => $r->createFrom404((string)($a['from'] ?? ''), (string)($a['to'] ?? ''), (int)($a['code'] ?? 301)));
$np->api('action_flatten', function () use ($r): array {
    require_once $r->npCms() . '/lib/tools.php';
    [$rules, $notes] = Rules::normalize($r->load());
    $r->save($rules);
    return ['ok' => true, 'message' => $notes ? count($notes) . ' Änderung(en) vorgenommen.' : 'Nichts zu verkürzen – keine Ketten oder Schleifen.', 'details' => $notes];
});

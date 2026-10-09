<?php
declare(strict_types=1);
/* Eigene Links im Menü der Verwaltung (unten in der Seitenleiste): gespeichert in data/admin-links.json, gilt für alle Benutzer.
   Ein Link öffnet sich im Rahmen der Verwaltung ("frame") oder in einem neuen Tab ("new"). Prüfung ausschließlich serverseitig. */

const ELVADO_ADMIN_LINKS_MAX = 20;

function elvado_admin_links_file(string $dataDir): string { return rtrim($dataDir, '/') . '/admin-links.json'; }

/** Nur http(s)-Adressen oder Pfade auf dieser Website ("/…"); keine javascript:-/data:-Adressen, keine "//host"-Pfade. */
function elvado_admin_links_valid_url(string $url): bool {
    if ($url === '' || strlen($url) > 500 || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) return false;
    if ($url[0] === '/') return !str_starts_with($url, '//');
    $p = parse_url($url);
    return is_array($p) && in_array(strtolower((string)($p['scheme'] ?? '')), ['http', 'https'], true) && (string)($p['host'] ?? '') !== '' && !isset($p['user']) && !isset($p['pass']);
}

/** @return list<array{id:string,label:string,url:string,icon:string,mode:string}> */
function elvado_admin_links_normalize($raw): array {
    if (!is_array($raw)) throw new InvalidArgumentException('Ungültige Linkliste');
    if (count($raw) > ELVADO_ADMIN_LINKS_MAX) throw new InvalidArgumentException('Höchstens ' . ELVADO_ADMIN_LINKS_MAX . ' eigene Links');
    $out = []; $ids = [];
    foreach (array_values($raw) as $i => $l) {
        if (!is_array($l)) throw new InvalidArgumentException('Ungültiger Link (Zeile ' . ($i + 1) . ')');
        $label = trim(strip_tags((string)($l['label'] ?? '')));
        if ($label === '' || mb_strlen($label) > 40) throw new InvalidArgumentException('Name fehlt oder ist zu lang, höchstens 40 Zeichen (Zeile ' . ($i + 1) . ')');
        $url = trim((string)($l['url'] ?? ''));
        if (!elvado_admin_links_valid_url($url)) throw new InvalidArgumentException('Adresse ungültig – erlaubt sind https://…, http://… oder ein Pfad wie /seite (Zeile ' . ($i + 1) . ')');
        $icon = strtolower(trim((string)($l['icon'] ?? '')));
        $icon = preg_match('/^[a-z0-9-]{2,40}$/', $icon) ? $icon : 'link';
        $mode = ($l['mode'] ?? '') === 'frame' ? 'frame' : 'new';
        $id = (string)($l['id'] ?? '');
        if (!preg_match('/^[a-z0-9_-]{3,40}$/', $id) || isset($ids[$id])) $id = 'l' . bin2hex(random_bytes(4));
        $ids[$id] = true;
        $out[] = ['id' => $id, 'label' => $label, 'url' => $url, 'icon' => $icon, 'mode' => $mode];
    }
    return $out;
}

/** @return list<array{id:string,label:string,url:string,icon:string,mode:string}> */
function elvado_admin_links_get(string $dataDir): array {
    $f = elvado_admin_links_file($dataDir);
    $j = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
    try { return elvado_admin_links_normalize(is_array($j) ? ($j['links'] ?? []) : []); } catch (InvalidArgumentException $e) { return []; }
}

function elvado_admin_links_save(string $dataDir, $raw): array {
    $links = elvado_admin_links_normalize($raw);
    elvado_write_atomic(elvado_admin_links_file($dataDir), json_encode(['links' => $links], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $links;
}

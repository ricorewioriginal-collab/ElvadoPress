<?php
declare(strict_types=1);
// cms/lib/sites.php – mehrere eigenständige Websites in einer Installation (Multisite). Doku: cms/docs/MULTISITE.md
//
// Hauptwebsite = bisheriges Verhalten (cms/data, cms/media, cms/generated), unverändert und ohne Registry.
// Weitere Websites liegen unter cms/sites/<id>/{data,media,generated} und haben eigene Inhalte, Einstellungen, Marken und Medien.
// Gemeinsam bleiben Code, Benutzer/Anmeldung, Plugins und Erweiterungen.
// Registry: cms/data/sites.json  {"items":[{"id","name","domains":[…],"enabled":true,"created":"…"}]}  (ohne Datei: keine weiteren Websites)
// Dieses Modul ist reine Bibliothek: Es verändert nichts, solange keine weitere Website angelegt ist.

const RRW_SITE_KINDS = ['data', 'media', 'generated'];
const RRW_SITE_MAX = 30;
/** Kennungen, die nicht als Website-Kennung vergeben werden dürfen (Ordner/Begriffe der Installation). */
const RRW_SITE_RESERVED = ['main', 'default', 'data', 'media', 'generated', 'cms', 'sites', 'admin', 'api', 'wp', 'tmp', 'backups', 'null', 'none'];

function rrw_sites_base(): string { return defined('RRW_SITES_BASE') ? rtrim((string)RRW_SITES_BASE, '/') : dirname(__DIR__); }   // cms/ (in Tests umlenkbar)
function rrw_sites_registry_file(): string { return rrw_sites_base() . '/data/sites.json'; }
function rrw_sites_dir(): string { return rrw_sites_base() . '/sites'; }

function rrw_site_id_clean(string $id): string
{
    $id = strtolower(trim($id));
    $id = (string)preg_replace('/[^a-z0-9-]+/', '-', $id);
    return substr(trim($id, '-'), 0, 30);
}
function rrw_site_domain_clean(string $d): string
{
    $d = strtolower(trim($d));
    $d = (string)preg_replace('#^https?://#', '', $d);
    $d = explode('/', $d)[0];
    $d = explode(':', $d)[0];
    return preg_match('/^(?=.{1,253}$)([a-z0-9-]+\.)+[a-z]{2,}$/', $d) === 1 ? $d : '';
}

/** @return list<array{id:string,name:string,domains:list<string>,enabled:bool,created:string}> */
function rrw_sites_registry(bool $fresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$fresh) {
        return $cache;
    }
    $raw = is_file(rrw_sites_registry_file()) ? json_decode((string)@file_get_contents(rrw_sites_registry_file()), true) : null;
    $out = [];
    $seen = [];
    foreach (array_slice((array)($raw['items'] ?? []), 0, RRW_SITE_MAX) as $s) {
        if (!is_array($s)) {
            continue;
        }
        $id = rrw_site_id_clean((string)($s['id'] ?? ''));
        if ($id === '' || isset($seen[$id]) || in_array($id, RRW_SITE_RESERVED, true)) {
            continue;
        }
        $seen[$id] = true;
        $dom = [];
        foreach (array_slice((array)($s['domains'] ?? []), 0, 12) as $d) {
            $c = rrw_site_domain_clean((string)$d);
            if ($c !== '' && !in_array($c, $dom, true)) {
                $dom[] = $c;
            }
        }
        $out[] = ['id' => $id, 'name' => mb_substr(trim((string)($s['name'] ?? $id)), 0, 80), 'domains' => $dom, 'enabled' => !array_key_exists('enabled', $s) || !empty($s['enabled']), 'created' => substr((string)($s['created'] ?? ''), 0, 25)];
    }
    return $cache = $out;
}

/** Website-Kennung zu einem Hostnamen ('' = Hauptwebsite). Vergleich mit und ohne „www.“; unbekannte Hosts gehören zur Hauptwebsite. */
function rrw_site_for_host(string $host): string
{
    $h = strtolower(trim(explode(':', $host)[0]));
    if ($h === '' || !rrw_sites_registry()) {
        return '';
    }
    $n = (string)preg_replace('/^www\./', '', $h);
    foreach (rrw_sites_registry() as $s) {
        if (!$s['enabled']) {
            continue;
        }
        foreach ($s['domains'] as $d) {
            if ($d === $h || preg_replace('/^www\./', '', $d) === $n) {
                return $s['id'];
            }
        }
    }
    return '';
}

/** Aktive Website dieser Anfrage ('' = Hauptwebsite): ausdrücklich gesetzt (z. B. Verwaltung) oder per Domain. */
function rrw_site_current(): string
{
    if (isset($GLOBALS['RRW_SITE_ID']) && is_string($GLOBALS['RRW_SITE_ID'])) {
        return $GLOBALS['RRW_SITE_ID'];
    }
    return $GLOBALS['RRW_SITE_ID'] = (PHP_SAPI === 'cli' ? '' : rrw_site_for_host((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '')));
}

/** Website für diese Anfrage festlegen (nur bekannte, aktive Websites; '' = Hauptwebsite). */
function rrw_site_use(string $id): bool
{
    $id = $id === '' ? '' : rrw_site_id_clean($id);
    if ($id !== '' && !rrw_site_exists($id)) {
        return false;
    }
    $GLOBALS['RRW_SITE_ID'] = $id;
    return true;
}
function rrw_site_exists(string $id): bool
{
    foreach (rrw_sites_registry() as $s) {
        if ($s['id'] === $id) {
            return true;
        }
    }
    return false;
}

/** Ordner einer Website für „data“, „media“ oder „generated“. Hauptwebsite: cms/<art> (unverändert). */
function rrw_site_dir(string $kind = 'data', ?string $id = null): string
{
    if (!in_array($kind, RRW_SITE_KINDS, true)) {
        throw new InvalidArgumentException('Unbekannte Ordner-Art: ' . $kind);
    }
    $id ??= rrw_site_current();
    if ($id === '' || !rrw_site_exists($id)) {
        return rrw_sites_base() . '/' . $kind;
    }
    return rrw_sites_dir() . '/' . $id . '/' . $kind;
}

/** @param list<array<string,mixed>> $items */
function rrw_sites_save(array $items): void
{
    $dir = dirname(rrw_sites_registry_file());
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $tmp = rrw_sites_registry_file() . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, json_encode(['items' => array_values($items)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) === false || !@rename($tmp, rrw_sites_registry_file())) {
        @unlink($tmp);
        throw new RuntimeException('Die Website-Liste konnte nicht gespeichert werden.');
    }
    rrw_sites_registry(true);
}

/** Jede Domain gehört genau einer Website (mit/ohne „www.“). @param list<array<string,mixed>> $items @return list<array{domain:string,sites:list<string>}> */
function rrw_sites_domain_conflicts(array $items): array
{
    $seen = [];
    foreach ($items as $s) {
        foreach ((array)($s['domains'] ?? []) as $d) {
            $seen[(string)preg_replace('/^www\./', '', (string)$d)][(string)($s['id'] ?? '')] = true;
        }
    }
    $out = [];
    foreach ($seen as $d => $ids) {
        if (count($ids) > 1) {
            $out[] = ['domain' => (string)$d, 'sites' => array_map('strval', array_keys($ids))];
        }
    }
    return $out;
}

/** Dateien/Ordner in „data“, die beim Kopieren einer Website NICHT mitgehen (Laufzeit-Zustand, Geheimnisse, Zwischenspeicher). */
function rrw_site_copy_skip(string $name): bool
{
    return $name === '' || $name[0] === '.' || str_ends_with($name, '.lock') || str_ends_with($name, '.tmp') || in_array($name, ['sites.json', 'install.lock', 'system.local.json', 'local-auth.local.php', 'local-sessions.local.json', 'activity-log.json', 'database.local.php', 'backups', 'cache'], true);
}
function rrw_site_copy_tree(string $from, string $to, bool $skipRuntime): int
{
    if (!is_dir($from)) {
        return 0;
    }
    @mkdir($to, 0775, true);
    $n = 0;
    foreach (scandir($from) ?: [] as $e) {
        if ($e === '.' || $e === '..' || ($skipRuntime && rrw_site_copy_skip($e)) || is_link($from . '/' . $e)) {
            continue;
        }
        if (is_dir($from . '/' . $e)) {
            $n += rrw_site_copy_tree($from . '/' . $e, $to . '/' . $e, false);
        } elseif (@copy($from . '/' . $e, $to . '/' . $e)) {
            $n++;
        }
    }
    return $n;
}

/**
 * Neue Website anlegen: Registry-Eintrag, Ordner, optional als Kopie einer vorhandenen Website (Einstellungen/Inhalte, optional Medien).
 * @param array{id?:string,name?:string,domains?:list<string>,enabled?:bool,copy_from?:string,copy_media?:bool} $in
 * @return array{site:array<string,mixed>,copied:int}
 * @throws InvalidArgumentException mit verständlicher Meldung
 */
function rrw_site_create(array $in): array
{
    $name = mb_substr(trim((string)($in['name'] ?? '')), 0, 80);
    $id = rrw_site_id_clean((string)($in['id'] ?? '') !== '' ? (string)$in['id'] : $name);
    if ($id === '') {
        throw new InvalidArgumentException('Bitte einen Namen oder eine Kennung angeben.');
    }
    if (in_array($id, RRW_SITE_RESERVED, true)) {
        throw new InvalidArgumentException('Die Kennung „' . $id . '“ ist reserviert.');
    }
    $items = rrw_sites_registry(true);
    if (count($items) >= RRW_SITE_MAX) {
        throw new InvalidArgumentException('Es sind höchstens ' . RRW_SITE_MAX . ' weitere Websites möglich.');
    }
    if (rrw_site_exists($id)) {
        throw new InvalidArgumentException('Die Kennung „' . $id . '“ gibt es schon.');
    }
    $domains = [];
    foreach ((array)($in['domains'] ?? []) as $d) {
        $d = trim((string)$d);
        if ($d === '') {
            continue;
        }
        $c = rrw_site_domain_clean($d);
        if ($c === '') {
            throw new InvalidArgumentException('Die Domain „' . $d . '“ ist ungültig (Beispiel: meine-seite.de).');
        }
        if (!in_array($c, $domains, true)) {
            $domains[] = $c;
        }
    }
    $site = ['id' => $id, 'name' => $name !== '' ? $name : $id, 'domains' => $domains, 'enabled' => !empty($in['enabled']), 'created' => gmdate('c')];
    $cf = rrw_sites_domain_conflicts(array_merge($items, [$site]));
    if ($cf) {
        throw new InvalidArgumentException('Jede Domain darf nur zu einer Website gehören: ' . implode('; ', array_map(fn($c) => $c['domain'] . ' → ' . implode(' und ', array_map(fn($i) => '„' . $i . '“', $c['sites'])), $cf)) . '.');
    }
    $from = (string)($in['copy_from'] ?? '');
    if ($from !== '' && $from !== 'main' && !rrw_site_exists(rrw_site_id_clean($from))) {
        throw new InvalidArgumentException('Die Vorlage-Website „' . $from . '“ gibt es nicht.');
    }
    $base = rrw_sites_dir() . '/' . $id;
    if (file_exists($base)) {
        throw new InvalidArgumentException('Der Ordner für „' . $id . '“ existiert bereits.');
    }
    $copied = 0;
    foreach (RRW_SITE_KINDS as $k) {
        if (!@mkdir($base . '/' . $k, 0775, true) && !is_dir($base . '/' . $k)) {
            throw new RuntimeException('Der Website-Ordner konnte nicht angelegt werden.');
        }
    }
    @file_put_contents($base . '/data/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
    if ($from !== '') {
        $src = $from === 'main' ? '' : rrw_site_id_clean($from);
        $copied += rrw_site_copy_tree(rrw_site_dir('data', $src), $base . '/data', true);
        $copied += rrw_site_copy_tree(rrw_site_dir('generated', $src), $base . '/generated', false);
        if (!empty($in['copy_media'])) {
            $copied += rrw_site_copy_tree(rrw_site_dir('media', $src), $base . '/media', false);
        }
    }
    $items[] = $site;
    rrw_sites_save($items);
    return ['site' => $site, 'copied' => $copied];
}

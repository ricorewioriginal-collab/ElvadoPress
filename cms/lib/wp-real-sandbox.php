<?php
// Sandbox auf echtem WordPress (Stufe E7c): ein abgetrennter Spielraum zwischen Live-Website und Deployment, wenn echtes WordPress die Website ausliefert.
// Die Sandbox ist eine Kopie der WordPress-Datenbank (SQLite: eigene Datei; MySQL/MariaDB: eigene Tabellen mit eigenem Präfix in derselben Datenbank).
// Theme, Anpassungen, Widgets, Link-Struktur und Startseite lassen sich dort ändern; Beiträge und Seiten kommen weiter aus ElvadoPress.
// Erreichbar über einen geheimen Link (Cookie), nicht indexierbar, ohne Schreibzugriffe für Besucher. „Live stellen“ übernimmt nur die Design-Optionen
// (wie bisher: Theme, theme_mods_*, widget_*, sidebars_widgets, Link-Struktur, Startseite) – mit Sicherung zum Zurückrollen.
// Reines PHP mit direktem Datenbankzugriff (kein Start von WordPress nötig): cms/wp-front.php, cms/api.php.
declare(strict_types=1);

const ELVADO_WPRS_KEYS = ['stylesheet', 'template', 'current_theme', 'sidebars_widgets', 'permalink_structure', 'category_base', 'tag_base', 'show_on_front', 'page_on_front', 'page_for_posts'];

function elvado_wprs_key_ok(string $k): bool
{
    return in_array($k, ELVADO_WPRS_KEYS, true) || (bool)preg_match('/^(theme_mods_[A-Za-z0-9_.-]+|widget_[A-Za-z0-9_-]+)$/', $k);
}
function elvado_wprs_dir(string $dataDir): string
{
    return rtrim($dataDir, '/') . '/.wp-sandbox-engine';
}
function elvado_wprs_meta(string $dataDir): array
{
    $d = json_decode((string)@file_get_contents(elvado_wprs_dir($dataDir) . '/sandbox.json'), true);
    return is_array($d) ? $d : [];
}
function elvado_wprs_exists(string $dataDir): bool
{
    return is_file(elvado_wprs_dir($dataDir) . '/sandbox.json');
}
function elvado_wprs_write_meta(string $dataDir, array $m): bool
{
    $dir = elvado_wprs_dir($dataDir);
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }
    $f = $dir . '/sandbox.json';
    $t = $f . '.' . bin2hex(random_bytes(3)) . '.tmp';
    return @file_put_contents($t, json_encode($m, JSON_UNESCAPED_UNICODE)) !== false && @rename($t, $f);
}
function elvado_wprs_token_ok(string $dataDir, string $t): bool
{
    $m = elvado_wprs_meta($dataDir);
    return preg_match('/^[a-f0-9]{32}$/', $t) === 1 && is_string($m['token'] ?? null) && hash_equals($m['token'], $t);
}
function elvado_wprs_url(string $dataDir): string
{
    return '/?elvado_sbx=' . rawurlencode((string)(elvado_wprs_meta($dataDir)['token'] ?? ''));
}

/** Datenbank-Ziel der Sandbox für den Start von WordPress (Bridge: $GLOBALS['elvado_wpe_db_override']). */
function elvado_wprs_override(array $live, string $dataDir): array
{
    return ($live['driver'] ?? '') === 'sqlite' ? ['path' => elvado_wprs_dir($dataDir) . '/wordpress.sqlite'] : ['prefix' => elvado_wprs_prefix((string)$live['prefix'])];
}
function elvado_wprs_prefix(string $livePrefix): string
{
    return substr(rtrim($livePrefix, '_'), 0, 14) . 'sbx_';
}

/** Verbindung zur Live- oder Sandbox-Datenbank: [query(sql, params) → rows, exec(sql, params), table(name)]. */
function elvado_wprs_db(array $live, string $dataDir, bool $sandbox): array
{
    if (($live['driver'] ?? '') === 'sqlite') {
        $pdo = new PDO('sqlite:' . ($sandbox ? elvado_wprs_dir($dataDir) . '/wordpress.sqlite' : (string)$live['path']), null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $prefix = (string)$live['prefix'];
    } else {
        $host = (string)($live['host'] ?: 'localhost');
        $port = 3306;
        if (str_contains($host, ':') && !str_starts_with($host, '/')) {
            [$host, $p] = explode(':', $host, 2);
            $port = ctype_digit($p) ? (int)$p : 3306;
        }
        $pdo = new PDO('mysql:host=' . $host . ';port=' . $port . ';dbname=' . $live['name'] . ';charset=utf8mb4', (string)$live['user'], (string)$live['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $prefix = $sandbox ? elvado_wprs_prefix((string)$live['prefix']) : (string)$live['prefix'];
    }
    return ['pdo' => $pdo, 'prefix' => $prefix];
}
/** Design-Optionen (Name → [Wert roh, autoload]). */
function elvado_wprs_options(array $c): array
{
    $out = [];
    foreach ($c['pdo']->query('SELECT option_name, option_value, autoload FROM ' . $c['prefix'] . 'options')->fetchAll(PDO::FETCH_NUM) as [$n, $v, $a]) {
        if (elvado_wprs_key_ok((string)$n)) {
            $out[(string)$n] = [(string)$v, (string)$a];
        }
    }
    return $out;
}

/** Sandbox anlegen bzw. auf den Live-Stand zurücksetzen (Kopie der Datenbank). Rückgabe Fehlertext oder null. */
function elvado_wprs_create(array $live, string $dataDir, bool $keepToken = false): ?string
{
    $dir = elvado_wprs_dir($dataDir);
    $m = elvado_wprs_meta($dataDir);
    if (!elvado_wprs_write_meta($dataDir, ['token' => $keepToken && isset($m['token']) ? $m['token'] : bin2hex(random_bytes(16)), 'created' => gmdate('c'), 'last_publish' => (string)($m['last_publish'] ?? '')])) {
        return 'Der Sandbox-Ordner ist nicht beschreibbar.';
    }
    try {
        if (($live['driver'] ?? '') === 'sqlite') {
            $f = $dir . '/wordpress.sqlite';
            @unlink($f);
            $pdo = new PDO('sqlite:' . $live['path'], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('VACUUM INTO ' . $pdo->quote($f));   // stimmige Kopie, auch während die Website läuft
            @chmod($f, 0600);
        } else {
            $c = elvado_wprs_db($live, $dataDir, false);
            $pdo = $c['pdo'];
            $sp = elvado_wprs_prefix((string)$live['prefix']);
            $like = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $sp) . '%';
            foreach ($pdo->query('SHOW TABLES LIKE ' . $pdo->quote($like))->fetchAll(PDO::FETCH_COLUMN) as $t) {
                $pdo->exec('DROP TABLE `' . str_replace('`', '``', (string)$t) . '`');
            }
            $like = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], (string)$live['prefix']) . '%';
            foreach ($pdo->query('SHOW TABLES LIKE ' . $pdo->quote($like))->fetchAll(PDO::FETCH_COLUMN) as $t) {
                $n = $sp . substr((string)$t, strlen((string)$live['prefix']));
                $pdo->exec('CREATE TABLE `' . $n . '` LIKE `' . $t . '`');
                $pdo->exec('INSERT INTO `' . $n . '` SELECT * FROM `' . $t . '`');
            }
            // Präfix-abhängige Optionen/Metadaten (Rollen, Benutzerrechte) umbenennen, sonst kennt WordPress in der Sandbox keine Rollen
            $pdo->exec('UPDATE `' . $sp . 'options` SET option_name = ' . $pdo->quote($sp . 'user_roles') . ' WHERE option_name = ' . $pdo->quote($live['prefix'] . 'user_roles'));
            $pdo->exec('UPDATE `' . $sp . 'usermeta` SET meta_key = CONCAT(' . $pdo->quote($sp) . ', SUBSTRING(meta_key, ' . (strlen((string)$live['prefix']) + 1) . ')) WHERE meta_key LIKE ' . $pdo->quote($like));
        }
    } catch (Throwable $e) {
        return 'Die Sandbox ließ sich nicht anlegen: ' . $e->getMessage();
    }
    return null;
}
function elvado_wprs_delete(array $live, string $dataDir): void
{
    if (($live['driver'] ?? '') !== 'sqlite') {
        try {
            $c = elvado_wprs_db($live, $dataDir, true);
            $like = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $c['prefix']) . '%';
            foreach ($c['pdo']->query('SHOW TABLES LIKE ' . $c['pdo']->quote($like))->fetchAll(PDO::FETCH_COLUMN) as $t) {
                $c['pdo']->exec('DROP TABLE `' . str_replace('`', '``', (string)$t) . '`');
            }
        } catch (Throwable) {
        }
    }
    $dir = elvado_wprs_dir($dataDir);
    foreach (['wordpress.sqlite', 'sandbox.json', '.htaccess'] as $f) {
        @unlink($dir . '/' . $f);
    }
    @rmdir($dir);
}
function elvado_wprs_rotate(string $dataDir): ?string
{
    $m = elvado_wprs_meta($dataDir);
    if ($m === []) {
        return 'Es gibt noch keine Sandbox.';
    }
    $m['token'] = bin2hex(random_bytes(16));
    return elvado_wprs_write_meta($dataDir, $m) ? null : 'Der Link ließ sich nicht erneuern.';
}
/** Unterschiede zur Live-Website (Format wie bisher: options, new_themes, sandbox_theme, live_theme, sandbox_front, live_front, changed). */
function elvado_wprs_diff(array $live, string $dataDir): array
{
    $s = elvado_wprs_options(elvado_wprs_db($live, $dataDir, true));
    $l = elvado_wprs_options(elvado_wprs_db($live, $dataDir, false));
    $keys = [];
    foreach (array_unique(array_merge(array_keys($s), array_keys($l))) as $k) {
        if (($s[$k][0] ?? null) !== ($l[$k][0] ?? null)) {
            $keys[] = (string)$k;
        }
    }
    sort($keys);
    return ['options' => $keys, 'new_themes' => [], 'sandbox_theme' => (string)($s['stylesheet'][0] ?? ''), 'live_theme' => (string)($l['stylesheet'][0] ?? ''),
        'sandbox_front' => true, 'live_front' => true, 'changed' => $keys !== []];
}
function elvado_wprs_backup_dir(string $dataDir): string
{
    return elvado_wprs_dir($dataDir) . '-backups';
}
/** Design-Optionen schreiben (Wert roh wie in der Datenbank; null = löschen). */
function elvado_wprs_apply(array $c, array $rows): void
{
    $pdo = $c['pdo'];
    $t = $c['prefix'] . 'options';
    $pdo->beginTransaction();
    try {
        foreach ($rows as $k => $row) {
            if (!elvado_wprs_key_ok((string)$k)) {
                continue;
            }
            $pdo->prepare('DELETE FROM ' . $t . ' WHERE option_name = ?')->execute([(string)$k]);
            if ($row !== null) {
                $pdo->prepare('INSERT INTO ' . $t . ' (option_name, option_value, autoload) VALUES (?, ?, ?)')->execute([(string)$k, (string)$row[0], (string)($row[1] ?? 'auto')]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
/** Live stellen: geänderte Design-Optionen übernehmen, vorher Sicherung (die letzten 10). */
function elvado_wprs_publish(array $live, string $dataDir): array
{
    if (!elvado_wprs_exists($dataDir)) {
        return ['ok' => false, 'message' => 'Es gibt noch keine Sandbox.'];
    }
    try {
        $diff = elvado_wprs_diff($live, $dataDir);
        if (!$diff['changed']) {
            return ['ok' => false, 'message' => 'Die Sandbox unterscheidet sich nicht von der Live-Seite.'];
        }
        $sc = elvado_wprs_db($live, $dataDir, true);
        $lc = elvado_wprs_db($live, $dataDir, false);
        $s = elvado_wprs_options($sc);
        $l = elvado_wprs_options($lc);
        $bk = ['created' => gmdate('c'), 'options' => []];
        $set = [];
        foreach ($diff['options'] as $k) {
            $bk['options'][$k] = $l[$k] ?? null;
            $set[$k] = $s[$k] ?? null;
        }
        $bd = elvado_wprs_backup_dir($dataDir);
        if (!is_dir($bd)) {
            @mkdir($bd, 0750, true);
            @file_put_contents($bd . '/.htaccess', "Require all denied\n");
        }
        $id = gmdate('Ymd-His');
        if (@file_put_contents($bd . '/' . $id . '.json', json_encode($bk, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
            return ['ok' => false, 'message' => 'Sicherung konnte nicht geschrieben werden. Es wurde nichts geändert.'];
        }
        foreach (array_slice(array_reverse(glob($bd . '/*.json') ?: []), 10) as $old) {
            @unlink($old);
        }
        elvado_wprs_apply($lc, $set);
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Die Sandbox ließ sich nicht live stellen: ' . $e->getMessage()];
    }
    $m = elvado_wprs_meta($dataDir);
    $m['last_publish'] = gmdate('c');
    elvado_wprs_write_meta($dataDir, $m);
    return ['ok' => true, 'message' => 'Die Sandbox ist jetzt live.', 'backup' => $id, 'themes' => [], 'options' => count($diff['options'])];
}
function elvado_wprs_backups(string $dataDir): array
{
    $o = [];
    foreach (array_reverse(glob(elvado_wprs_backup_dir($dataDir) . '/*.json') ?: []) as $f) {
        $d = json_decode((string)@file_get_contents($f), true);
        if (is_array($d)) {
            $o[] = ['id' => basename($f, '.json'), 'created' => (string)($d['created'] ?? ''), 'options' => count((array)($d['options'] ?? []))];
        }
    }
    return $o;
}
function elvado_wprs_rollback(array $live, string $dataDir, string $id): ?string
{
    if (!preg_match('/^\d{8}-\d{6}$/', $id)) {
        return 'Ungültige Sicherung.';
    }
    $f = elvado_wprs_backup_dir($dataDir) . '/' . $id . '.json';
    $d = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
    if (!is_array($d)) {
        return 'Sicherung nicht gefunden.';
    }
    try {
        elvado_wprs_apply(elvado_wprs_db($live, $dataDir, false), (array)$d['options']);
    } catch (Throwable $e) {
        return 'Die Live-Einstellungen konnten nicht zurückgesetzt werden: ' . $e->getMessage();
    }
    return null;
}

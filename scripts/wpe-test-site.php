<?php
// Gemeinsamer Helfer der HTTP-Tests: echtes WordPress in einer Testinstallation einrichten, wie es die erste Anmeldung eines Superadmins tut
// (Core aus dem Paket-Bündel, SQLite, Start, Website-Auslieferung). Braucht WPE_TEST_ZIP (WordPress-ZIP) und pdo_sqlite; WPE_TEST_SQLITE_ZIP optional
// (sonst lädt die Engine die SQLite-Integration von wordpress.org). Ohne WPE_TEST_ZIP: false – die Tests überspringen dann die Website-Prüfungen.
declare(strict_types=1);

function wpe_test_site_available(): bool
{
    $z = (string)getenv('WPE_TEST_ZIP');
    return $z !== '' && is_file($z) && extension_loaded('pdo_sqlite');
}

/** WordPress im Paketordner $pkg (Server unter $base, Anmelde-Token $tok) einrichten. $siteHeader: weitere Website (X-EP-SITE). @return array{ok:bool,message:string} */
function wpe_test_site_setup(string $base, string $tok, string $pkg, string $siteHeader = ''): array
{
    if (!wpe_test_site_available()) {
        return ['ok' => false, 'message' => 'WPE_TEST_ZIP nicht gesetzt'];
    }
    @mkdir($pkg . '/cms/wp-engine/bundle', 0755, true);
    if (!is_file($pkg . '/cms/wp-engine/bundle/wordpress-test.zip')) {
        copy((string)getenv('WPE_TEST_ZIP'), $pkg . '/cms/wp-engine/bundle/wordpress-test.zip');
    }
    $sq = (string)getenv('WPE_TEST_SQLITE_ZIP');
    if ($sq !== '' && is_file($sq) && !is_file($pkg . '/cms/wp-engine/bundle/sqlite-database-integration.zip')) {
        copy($sq, $pkg . '/cms/wp-engine/bundle/sqlite-database-integration.zip');
    }
    $hdr = "Content-Type: application/json\r\nX-ElvadoPress-Token: $tok\r\nOrigin: $base\r\n" . ($siteHeader !== '' ? "X-EP-SITE: $siteHeader\r\n" : '');
    $call = static function (string $a, array $b) use ($base, $hdr): array {
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => $hdr, 'content' => json_encode($b), 'ignore_errors' => true, 'timeout' => 300]]);
        $r = @file_get_contents($base . '/cms/engine-api.php?action=' . $a, false, $ctx);
        return json_decode((string)$r, true) ?: ['status' => 'error', 'message' => substr((string)$r, 0, 300)];
    };
    foreach ([['engine_core', ['version' => '', 'source' => 'bundle']], ['engine_db_install', ['db' => ['driver' => 'sqlite', 'prefix' => 'wpk_']]], ['engine_mode', ['mode' => 'active']], ['engine_front', ['on' => true]]] as [$a, $b]) {
        $x = $call($a, $b);
        if (($x['status'] ?? '') !== 'ok') {
            return ['ok' => false, 'message' => $a . ': ' . (string)($x['message'] ?? '')];
        }
    }
    return ['ok' => true, 'message' => ''];
}

<?php
// Test-Helfer: Website-Ausgabe mit echtem WordPress prüfen (statt der früheren Nachbildung). Legt eine Testinstallation an (Kopie von cms/ ohne Laufzeitdaten),
// spielt WordPress (WPE_TEST_ZIP) mit SQLite ein und führt jeden Schritt in einem eigenen PHP-Prozess aus – wie echte Seitenaufrufe.
// wpe_render_env(): Umgebung (oder null ohne WPE_TEST_ZIP/pdo_sqlite); wpe_render_php(): PHP-Code mit gestartetem WordPress ausführen (Einstellungen setzen, Werte lesen);
// wpe_render_get(): Adresse ausliefern lassen (Abgleich der ElvadoPress-Inhalte wie auf der Website). Die Daten liegen unter $env['data'] (news.json, site.json …).
declare(strict_types=1);
require_once __DIR__ . '/../cms/src/autoload.php';
require_once __DIR__ . '/wpe-render-lib.php';

/** @return array{tmp:string,cms:string,data:string}|null */
function wpe_render_env(array $files = []): ?array
{
    $zip = (string)getenv('WPE_TEST_ZIP');
    if ($zip === '' || !is_file($zip) || !extension_loaded('pdo_sqlite')) {
        return null;
    }
    $tmp = sys_get_temp_dir() . '/wpe-render-' . bin2hex(random_bytes(4));
    $cms = "$tmp/cms";
    $src = realpath(__DIR__ . '/../cms');
    exec('mkdir -p ' . escapeshellarg($cms) . ' && cd ' . escapeshellarg($src) . ' && tar cf - --exclude=./data --exclude=./wp-engine --exclude=./generated --exclude=./media --exclude=./sites --exclude=./backups . | (cd ' . escapeshellarg($cms) . ' && tar xf -)');
    @mkdir("$cms/data", 0755, true);
    @mkdir("$cms/media", 0755, true);
    register_shutdown_function(static fn() => wpe_render_rmrf($tmp));
    foreach ($files as $name => $content) {
        file_put_contents("$cms/data/$name", is_string($content) ? $content : json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    $e = new \Elvado\Wp\Engine($cms, "$cms/data");
    $z = new ZipArchive();
    $z->open($zip);
    preg_match('/\$wp_version\s*=\s*\'([^\']+)\'/', (string)$z->getFromName('wordpress/wp-includes/version.php'), $m);
    $z->close();
    $r = (new \Elvado\Wp\CoreInstaller($e))->install($zip, $m[1], sha1_file($zip));
    if (!$r['ok']) {
        echo "Hinweis: WordPress ließ sich nicht einspielen: {$r['message']}\n";
        return null;
    }
    $s = (new \Elvado\Wp\SqliteIntegration($e))->install((string)(getenv('WPE_TEST_SQLITE_ZIP') ?: ''));
    if (!$s['ok']) {
        echo "Hinweis: SQLite-Integration fehlt: {$s['message']}\n";
        return null;
    }
    (new \Elvado\Wp\DbConfig($e))->save(['driver' => 'sqlite', 'prefix' => 'wpk_']);
    $e->save(['db' => ['ready' => true], 'mode' => 'active', 'front' => true]);
    $env = ['tmp' => $tmp, 'cms' => $cms, 'data' => "$cms/data"];
    $res = wpe_render_child($env, 'install', '', '');
    if (($res['ok'] ?? false) !== true) {
        echo 'Hinweis: WordPress-Tabellen ließen sich nicht anlegen: ' . json_encode($res) . "\n";
        return null;
    }
    return $env;
}

/** PHP-Code mit gestartetem WordPress ausführen (eigener Prozess); Rückgabe: Wert von $out im Code. */
function wpe_render_php(array $env, string $php): mixed
{
    return wpe_render_child($env, 'php', '', $php)['out'] ?? null;
}



/** Testablauf mit gestartetem WordPress in einem Prozess ausführen; t() sammelt Ergebnisse, page() liefert Adressen in eigenen Prozessen aus.
    @return list<array{0:string,1:bool,2:string}> */
function wpe_render_suite(array $env, string $body): array
{
    $prefix = 'require_once ' . var_export(__DIR__ . '/wpe-render-lib.php', true) . ";\n"
        . '$GLOBALS[\'wpeEnv\'] = ' . var_export($env, true) . ";\n\$GLOBALS['wpeT'] = [];\n"
        . "function t(string \$name, bool \$ok, string \$extra = ''): void { \$GLOBALS['wpeT'][] = [\$name, \$ok, \$extra]; }\n"
        . "function page(string \$u, string \$php = ''): array { return wpe_render_get(\$GLOBALS['wpeEnv'], \$u, \$php); }\n";
    $r = wpe_render_child($env, 'php', '', $prefix . $body . "\n\$out = \$GLOBALS['wpeT'];");
    if (!is_array($r['out'] ?? null)) {
        return [['Testablauf mit echtem WordPress', false, (string)($r['error'] ?? 'ohne Ergebnis')]];
    }
    return $r['out'];
}

<?php
// Teil von scripts/wpe-render.php ohne Klassen-Autoload: auch in einem Kindprozess mit gestartetem WordPress nutzbar (Seite in einem weiteren Prozess ausliefern).
declare(strict_types=1);

function wpe_render_rmrf(string $d): void
{
    if (is_link($d) || is_file($d)) {
        @unlink($d);
        return;
    }
    if (!is_dir($d)) {
        return;
    }
    foreach (scandir($d) ?: [] as $f) {
        if ($f !== '.' && $f !== '..') {
            wpe_render_rmrf("$d/$f");
        }
    }
    @rmdir($d);
}

/** Adresse ausliefern lassen: ['status'=>int,'body'=>string]. Optional $php vor der Ausgabe (z. B. Filter für diesen Aufruf). */
function wpe_render_get(array $env, string $uri, string $php = ''): array
{
    $r = wpe_render_child($env, 'get', $uri, $php);
    return ['status' => (int)($r['status'] ?? 0), 'body' => (string)($r['body'] ?? ''), 'error' => (string)($r['error'] ?? '')];
}

function wpe_render_child(array $env, string $mode, string $uri, string $php): array
{
    $codeFile = $env['tmp'] . '/code-' . bin2hex(random_bytes(4)) . '.php';
    file_put_contents($codeFile, "<?php\n" . $php . "\n");
    $out = [];
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/wpe-render-child.php') . ' ' . escapeshellarg($env['cms']) . ' ' . escapeshellarg($mode) . ' ' . escapeshellarg($uri) . ' ' . escapeshellarg($codeFile) . ' 2>&1', $out, $rc);
    @unlink($codeFile);
    $all = implode("\n", $out);
    $p = strrpos($all, "\n@@WPE@@");
    $j = $p === false ? (str_starts_with($all, '@@WPE@@') ? json_decode(substr($all, 7), true) : null) : json_decode(substr($all, $p + 8), true);
    return is_array($j) ? $j : ['error' => substr($all, -800)];
}

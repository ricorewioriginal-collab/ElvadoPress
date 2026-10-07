<?php
declare(strict_types=1);
// cms/src/Wp/Requirements.php – Systemprüfung für die WordPress-Engine mit verständlichen deutschen Erklärungen (OK / Warnung / Fehler).

namespace Elvado\Wp;

final class Requirements
{
    /**
     * @param array{php?:string,mysql?:string} $wp Mindestanforderungen laut WordPress (api.wordpress.org); leer = Standardwerte
     * @return array{ok:bool,items:list<array{id:string,status:string,label:string,detail:string}>}
     */
    public static function check(Engine $e, array $wp = []): array
    {
        $items = [];
        $add = function (string $id, string $status, string $label, string $detail) use (&$items): void {
            $items[] = ['id' => $id, 'status' => $status, 'label' => $label, 'detail' => $detail];
        };
        $needPhp = (string)($wp['php'] ?? '7.4');
        $add('php', version_compare(PHP_VERSION, $needPhp, '>=') ? 'ok' : 'fail', 'PHP-Version', 'Installiert: ' . PHP_VERSION . ' · WordPress verlangt mindestens ' . $needPhp . (version_compare(PHP_VERSION, $needPhp, '>=') ? '.' : ' – bitte beim Hoster eine neuere PHP-Version einstellen.'));
        foreach ([
            'mysqli' => ['fail', 'Datenbank-Erweiterung (mysqli)', 'Ohne diese Erweiterung kann WordPress keine MySQL-/MariaDB-Datenbank nutzen.'],
            'json' => ['fail', 'JSON', 'Wird von WordPress und ElvadoPress benötigt.'],
            'mbstring' => ['fail', 'Zeichensätze (mbstring)', 'Wird für Umlaute und Sonderzeichen benötigt.'],
            'xml' => ['fail', 'XML', 'Wird für Feeds, Importe und Plugins benötigt.'],
            'zip' => ['fail', 'ZIP', 'Wird zum Entpacken von WordPress, Plugins und Themes benötigt.'],
            'curl' => ['warn', 'cURL', 'Für Downloads und Updates; ohne cURL sind Plugin-/Theme-Installation und Updates eingeschränkt.'],
            'openssl' => ['warn', 'OpenSSL', 'Für sichere Verbindungen (HTTPS) zu wordpress.org und Diensten.'],
            'gd' => ['warn', 'Bildbearbeitung (GD)', 'Für Vorschaubilder; ohne GD (oder Imagick) werden Bilder nicht verkleinert.'],
            'intl' => ['warn', 'Internationalisierung (intl)', 'Empfohlen für Sprachen und Zeitformate.'],
        ] as $ext => [$sev, $label, $why]) {
            $have = extension_loaded($ext) || ($ext === 'xml' && extension_loaded('dom'));
            $add('ext_' . $ext, $have ? 'ok' : $sev, $label, $have ? 'Vorhanden.' : $why);
        }
        $mem = self::bytes((string)ini_get('memory_limit'));
        $add('memory', $mem === -1 || $mem >= 128 * 1048576 ? 'ok' : 'warn', 'Arbeitsspeicher pro Anfrage', 'Eingestellt: ' . ((string)ini_get('memory_limit') ?: '?') . ' · empfohlen sind mindestens 128 MB' . ($mem !== -1 && $mem < 128 * 1048576 ? ' (zu wenig kann Fehlermeldungen beim Start von WordPress auslösen).' : '.'));
        $free = @disk_free_space($e->cmsDir());
        $add('disk', $free === false || $free > 300 * 1048576 ? 'ok' : ($free > 150 * 1048576 ? 'warn' : 'fail'), 'Freier Speicherplatz', $free === false ? 'Konnte nicht ermittelt werden.' : 'Frei: ' . round($free / 1048576) . ' MB · WordPress braucht etwa 110 MB beim Entpacken (ZIP und Entpacktes) – empfohlen sind 300 MB.');
        foreach (['Engine-Ordner' => dirname($e->coreRoot()) . '/wp-engine', 'Zustandsordner' => $e->stateDir(), 'Inhalte (wp-content)' => $e->contentDir()] as $name => $dir) {
            $probe = is_dir($dir) ? $dir : dirname($dir);
            $add('write_' . strtolower((string)preg_replace('/\W+/', '_', $name)), is_writable($probe) ? 'ok' : 'fail', 'Schreibrechte: ' . $name, is_writable($probe) ? 'Beschreibbar.' : 'Der Webserver darf hier nicht schreiben – bitte Dateirechte prüfen.');
        }
        $ok = true;
        foreach ($items as $i) {
            if ($i['status'] === 'fail') {
                $ok = false;
            }
        }
        return ['ok' => $ok, 'items' => $items];
    }

    public static function bytes(string $v): int
    {
        $v = trim($v);
        if ($v === '' || $v === '-1') {
            return -1;
        }
        $n = (int)$v;
        return match (strtolower(substr($v, -1))) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
    }
}

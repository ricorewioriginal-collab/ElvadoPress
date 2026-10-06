<?php
declare(strict_types=1);
// cms/src/Ai/CodeBuilder.php
//
// KI-Entwickler: aus einer Beschreibung entstehen WordPress-kompatible Plugins, Widgets und Themes (Dateien). Die KI liefert Text mit Dateiblöcken
// (=== DATEI: pfad === … === ENDE ===); hier werden Pfade, Größen und Inhalte streng geprüft (Dateiendungen, keine Pfadtricks, PHP-Syntax über den Tokenizer,
// statische Prüfung auf gefährliche Funktionen). Fehler verhindern die Installation, Warnungen muss die Administration ausdrücklich bestätigen.
// Installiert wird immer INAKTIV (Plugin/Theme wird erst über die vorhandene Aktivierung eingeschaltet). Kein Ersatz für eine Prüfung des Codes durch einen Menschen.

namespace Elvado\Ai;

final class CodeBuilder
{
    public const KINDS = ['plugin' => 'Plugin', 'widget' => 'Widget', 'theme' => 'Theme'];
    public const MAX_FILES = 12;
    public const MAX_FILE_BYTES = 120000;
    public const MAX_TOTAL_BYTES = 400000;
    private const EXT = ['php', 'css', 'js', 'json', 'txt', 'md', 'html'];
    /** Funktionen, die eine Installation verhindern (Programme starten, Code nachladen, Systemzugriff). */
    private const BLOCK_FUNCS = ['exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen', 'pcntl_exec', 'pcntl_fork', 'assert', 'create_function', 'dl', 'putenv', 'phpinfo', 'highlight_file', 'show_source',
        'fsockopen', 'pfsockopen', 'stream_socket_client', 'stream_socket_server', 'symlink', 'link', 'posix_kill', 'posix_setuid', 'proc_nice', 'ftp_connect', 'mail'];
    /** Funktionen, die eine Bestätigung verlangen. */
    private const WARN_FUNCS = [
        'file_put_contents' => 'schreibt Dateien', 'fwrite' => 'schreibt Dateien', 'fopen' => 'öffnet Dateien', 'unlink' => 'löscht Dateien', 'rename' => 'verschiebt Dateien', 'copy' => 'kopiert Dateien', 'mkdir' => 'legt Ordner an', 'rmdir' => 'löscht Ordner', 'chmod' => 'ändert Dateirechte',
        'curl_init' => 'greift auf das Netz zu', 'curl_exec' => 'greift auf das Netz zu', 'wp_remote_get' => 'greift auf das Netz zu', 'wp_remote_post' => 'greift auf das Netz zu', 'wp_remote_request' => 'greift auf das Netz zu',
        'file_get_contents' => 'liest Dateien oder Adressen', 'unserialize' => 'liest serialisierte Daten (Risiko bei fremden Eingaben)', 'extract' => 'überschreibt Variablen aus Arrays', 'base64_decode' => 'dekodiert Daten (kann Code verstecken)',
        'move_uploaded_file' => 'nimmt Datei-Uploads an', 'ini_set' => 'ändert PHP-Einstellungen', 'wp_mail' => 'versendet E-Mails', 'call_user_func' => 'ruft Funktionen dynamisch auf', 'call_user_func_array' => 'ruft Funktionen dynamisch auf',
        'wp_insert_user' => 'legt Benutzer an', 'wp_set_password' => 'ändert Passwörter',
    ];

    public function __construct(private readonly AiGatewayService $ai)
    {
    }

    /**
     * Entwurf erzeugen oder überarbeiten.
     * @param array{kind:string,prompt:string,base?:string,previous?:list<array{path:string,content:string}>,instruction?:string,existing?:list<string>,user?:string,provider?:string,model?:string} $in
     * @return array<string,mixed>
     * @throws AiGatewayException
     */
    public function plan(array $in): array
    {
        $kind = (string)($in['kind'] ?? '');
        if (!isset(self::KINDS[$kind])) {
            throw new AiGatewayException('Bitte Plugin, Widget oder Theme wählen.', 400);
        }
        $prompt = trim((string)($in['prompt'] ?? ''));
        if (mb_strlen($prompt) < 15) {
            throw new AiGatewayException('Bitte beschreibe genauer, was entstehen soll (mindestens ein bis zwei Sätze).', 400);
        }
        $base = (string)($in['base'] ?? 'child');
        $child = $kind === 'theme' && $base !== 'standalone';
        $prev = '';
        $instruction = trim((string)($in['instruction'] ?? ''));
        if ($instruction !== '' && is_array($in['previous'] ?? null)) {
            $parts = [];
            $total = 0;
            foreach (array_slice($in['previous'], 0, self::MAX_FILES) as $f) {
                if (is_array($f) && is_string($f['path'] ?? null) && is_string($f['content'] ?? null)) {
                    $total += strlen($f['content']);
                    if ($total <= self::MAX_TOTAL_BYTES) {
                        $parts[] = "=== DATEI: {$f['path']} ===\n{$f['content']}\n=== ENDE ===";
                    }
                }
            }
            $prev = "\n\nBisheriger Stand:\n" . implode("\n", $parts) . "\n\nÄnderungswunsch: " . mb_substr($instruction, 0, 2000) . "\nGib den vollständigen neuen Stand aller Dateien aus.";
        }
        $r = $this->ai->generate([
            'provider' => (string)($in['provider'] ?? ''), 'model' => (string)($in['model'] ?? ''), 'purpose' => 'developer', 'task' => 'code', 'internal' => true,
            'system' => $this->system($kind, $child), 'prompt' => 'Aufgabe: ' . mb_substr($prompt, 0, 3000) . $prev,
            'temperature' => 0.3, 'max_tokens' => 12000, 'user' => (string)($in['user'] ?? ''),
        ]);
        $keep = ($instruction !== '' && preg_match('/^[a-z][a-z0-9-]{2,40}$/', (string)($in['slug'] ?? ''))) ? (string)$in['slug'] : '';
        $plan = self::parse($r->text, $kind, $child, (array)($in['existing'] ?? []), $keep);
        $plan['meta'] = ['provider' => $r->provider, 'model' => $r->model, 'latency_ms' => $r->latencyMs];
        return $plan;
    }

    private function system(string $kind, bool $child): string
    {
        $common = "Du bist erfahrener WordPress-Entwickler und schreibst Code für ElvadoPress (WordPress-kompatibel, PHP 8.1+). Antworte NUR in diesem Format, ohne Markdown-Zäune und ohne weitere Erklärung:\n" .
            "TITEL: <kurzer Name>\nBESCHREIBUNG: <ein bis zwei Sätze>\n=== DATEI: <slug>/<datei> ===\n<vollständiger Dateiinhalt>\n=== ENDE ===\n(weitere Dateien im selben Muster)\nHINWEISE:\n- <Hinweis zur Benutzung>\n" .
            "Der <slug> ist ein kurzer Ordnername aus Kleinbuchstaben, Ziffern und Bindestrichen und derselbe in allen Pfaden. Erlaubte Dateien: .php .css .js .json .txt .md .html, höchstens " . self::MAX_FILES . " Dateien. " .
            "Schreibe vollständigen, lauffähigen, sicheren Code: jede Ausgabe mit esc_html/esc_attr/esc_url/wp_kses_post maskieren, Formulare mit Nonce und Rechteprüfung (current_user_can), Eingaben mit sanitize_* bereinigen, Datenbank nur über \$wpdb->prepare. " .
            "Keine Programmausführung (exec, system, shell_exec, passthru, popen, proc_open), kein eval/assert, keine Netzwerkzugriffe, kein Nachladen von Code, keine Datei-Schreibzugriffe, keine externen Skripte oder Schriften (alles lokal). Alle Texte der Oberfläche auf Deutsch.\n";
        return match (true) {
            $kind === 'plugin' => $common . "Aufgabe: ein WordPress-Plugin. Hauptdatei <slug>/<slug>.php mit Kopf (Plugin Name, Description, Version: 1.0.0, Author, License: GPL-2.0-or-later, Text Domain). Erste Zeile nach <?php und Kopf: if (!defined('ABSPATH')) { exit; }. " .
                "Alle Funktionen, Klassen und Optionsnamen mit einem eindeutigen Präfix aus dem Slug. Nutze Hooks (add_action/add_filter), Shortcodes (add_shortcode), die Settings API für Einstellungsseiten (add_options_page). Stile und Skripte als eigene Dateien über wp_enqueue_style/wp_enqueue_script mit plugins_url().\n",
            $kind === 'widget' => $common . "Aufgabe: ein Widget als kleines Plugin. Hauptdatei <slug>/<slug>.php mit Plugin-Kopf (Plugin Name, Description, Version: 1.0.0, Author, License: GPL-2.0-or-later). Erste Zeile nach dem Kopf: if (!defined('ABSPATH')) { exit; }. " .
                "Implementiere eine Klasse, die WP_Widget erweitert (widget(), form(), update()), registriere sie über add_action('widgets_init', …) UND biete zusätzlich einen Shortcode [<slug>] mit derselben Ausgabe an, damit sich das Widget auch in Seiten einfügen lässt. Eigene CSS-Datei nur mit eindeutigen Klassen (Präfix aus dem Slug).\n",
            $child => $common . "Aufgabe: eine Design-Variante des vorhandenen Themes „ElvadoPress Baukasten“ (Kindtheme). NUR eine Datei: <slug>/style.css. Kopf: /* Theme Name: <Name>\nTemplate: elvado-baukasten\nVersion: 1.0.0\nDescription: …\nLicense: GPL-2.0-or-later */. " .
                "Darunter nur CSS (kein PHP, kein JavaScript, kein @import, keine externen Adressen). Überschreibe CSS-Variablen und Klassen des Baukasten-Themes (z. B. :root{--accent:…}, Kopfbereich, Karten, Schaltflächen, Typografie, responsive Anpassungen bis 600 px).\n",
            default => $common . "Aufgabe: ein eigenständiges WordPress-Theme. Dateien: <slug>/style.css (Kopf: Theme Name, Version: 1.0.0, Description, License: GPL-2.0-or-later, Text Domain), <slug>/index.php, <slug>/header.php, <slug>/footer.php, <slug>/functions.php (Stile über wp_enqueue_style(get_stylesheet_uri()), add_theme_support für title-tag, post-thumbnails, menus), " .
                "bei Bedarf single.php, page.php, archive.php, 404.php. Nutze die Template-Tags (wp_head(), wp_footer(), have_posts(), the_title(), the_content(), wp_nav_menu()), responsive CSS ohne externe Ressourcen.\n",
        };
    }

    // ------------------------------------------------------------------ Parsen, Normalisieren, Prüfen

    /**
     * @param list<string> $existing vorhandene Plugin-/Theme-Ordner
     * @return array<string,mixed>
     * @throws AiGatewayException
     */
    public static function parse(string $text, string $kind, bool $child, array $existing = [], string $forceSlug = ''): array
    {
        $text = str_replace("\r\n", "\n", $text);
        preg_match('/^\s*TITEL:\s*(.+)$/mi', $text, $m1);
        preg_match('/^\s*BESCHREIBUNG:\s*(.+)$/mi', $text, $m2);
        $title = mb_substr(trim(strip_tags($m1[1] ?? '')), 0, 80);
        $desc = mb_substr(trim(strip_tags($m2[1] ?? '')), 0, 400);
        $files = [];
        if (preg_match_all('/^=== DATEI:\s*(.+?)\s*===\s*\n(.*?)\n=== ENDE ===/ms', $text, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $x) {
                $content = $x[2];
                if (preg_match('/^```[a-z]*\n(.*)\n```\s*$/s', $content, $fm)) {   // Markdown-Zaun entfernen, falls die KI ihn doch verwendet
                    $content = $fm[1];
                }
                $files[] = ['path' => trim($x[1]), 'content' => $content];
            }
        }
        if (!$files) {
            throw new AiGatewayException('Die KI hat keine Dateien geliefert. Bitte versuche es erneut oder formuliere die Aufgabe genauer.', 502);
        }
        $notes = [];
        if (preg_match('/^HINWEISE:\s*\n(.*)$/ms', $text, $nm)) {
            foreach (preg_split('/\n/', $nm[1]) ?: [] as $l) {
                $l = trim(preg_replace('/^[-*•]\s*/', '', $l) ?? '');
                if ($l !== '' && count($notes) < 8) {
                    $notes[] = mb_substr(strip_tags($l), 0, 300);
                }
            }
        }
        // Ordnername: gemeinsamer erster Pfadteil, sonst aus dem Titel
        $first = [];
        foreach ($files as $f) {
            $first[] = explode('/', str_replace('\\', '/', $f['path']))[0];
        }
        $slug = self::slug(count(array_unique($first)) === 1 && str_contains($files[0]['path'], '/') ? $first[0] : ($title ?: $kind));
        $taken = array_map('strtolower', $existing);
        if ($forceSlug !== '') {
            $slug = $forceSlug;   // Überarbeitung: derselbe Ordner
        } else {
            $base = $slug;
            for ($n = 2; in_array($slug, $taken, true); $n++) {
                $slug = $base . '-' . $n;
            }
        }
        $out = [];
        foreach ($files as $f) {
            $rel = str_replace('\\', '/', $f['path']);
            $rel = str_contains($rel, '/') ? substr($rel, strpos($rel, '/') + 1) : $rel;
            $out[] = ['path' => $rel, 'content' => $f['content']];
        }
        $plan = ['kind' => $kind, 'slug' => $slug, 'title' => $title !== '' ? $title : ucfirst(str_replace('-', ' ', $slug)), 'description' => $desc, 'notes' => $notes, 'files' => $out, 'child' => $child];
        $plan += self::check($plan);
        return $plan;
    }

    public static function slug(string $s): string
    {
        $s = strtr(mb_strtolower($s), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        $s = trim(preg_replace('/[^a-z0-9]+/', '-', $s) ?? '', '-');
        $s = mb_substr($s, 0, 40);
        return preg_match('/^[a-z][a-z0-9-]{2,}$/', $s) ? $s : 'ki-' . ($s !== '' ? $s : substr(bin2hex(random_bytes(3)), 0, 5));
    }

    /**
     * Prüfung eines Entwurfs. @param array<string,mixed> $plan
     * @return array{errors:list<string>,warnings:list<string>,ok:bool,files:list<array{path:string,content:string}>}
     */
    public static function check(array $plan): array
    {
        $errors = [];
        $warnings = [];
        $kind = (string)($plan['kind'] ?? '');
        $child = !empty($plan['child']);
        $clean = [];
        $total = 0;
        $seen = [];
        if (!isset(self::KINDS[$kind])) {
            $errors[] = 'Unbekannte Art.';
        }
        if (!preg_match('/^[a-z][a-z0-9-]{2,40}$/', (string)($plan['slug'] ?? ''))) {
            $errors[] = 'Der Ordnername ist ungültig (Kleinbuchstaben, Ziffern, Bindestriche).';
        }
        foreach (array_slice(is_array($plan['files'] ?? null) ? $plan['files'] : [], 0, 40) as $f) {
            $path = is_array($f) ? (string)($f['path'] ?? '') : '';
            $content = is_array($f) ? (string)($f['content'] ?? '') : '';
            if (!preg_match('~^[a-z0-9][a-z0-9._-]*(/[a-z0-9][a-z0-9._-]*)*$~i', $path) || str_contains($path, '..') || str_starts_with(basename($path), '.')) {
                $errors[] = 'Ungültiger Dateipfad: ' . mb_substr($path, 0, 80);
                continue;
            }
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($ext, self::EXT, true)) {
                $errors[] = 'Dateityp nicht erlaubt: ' . $path;
                continue;
            }
            if (isset($seen[strtolower($path)])) {
                $errors[] = 'Datei doppelt: ' . $path;
                continue;
            }
            $seen[strtolower($path)] = true;
            if (strlen($content) > self::MAX_FILE_BYTES) {
                $errors[] = 'Datei zu groß (höchstens ' . (int)(self::MAX_FILE_BYTES / 1000) . ' KB): ' . $path;
                continue;
            }
            $total += strlen($content);
            if (!mb_check_encoding($content, 'UTF-8') || str_contains($content, "\0")) {
                $errors[] = 'Datei enthält ungültige Zeichen: ' . $path;
                continue;
            }
            $clean[] = ['path' => $path, 'content' => $content];
            [$e, $w] = self::scanFile($path, $ext, $content);
            array_push($errors, ...$e);
            array_push($warnings, ...$w);
        }
        if (count($clean) > self::MAX_FILES || count($plan['files'] ?? []) > self::MAX_FILES) {
            $errors[] = 'Zu viele Dateien (höchstens ' . self::MAX_FILES . ').';
        }
        if ($total > self::MAX_TOTAL_BYTES) {
            $errors[] = 'Insgesamt zu viel Code (höchstens ' . (int)(self::MAX_TOTAL_BYTES / 1000) . ' KB).';
        }
        $paths = array_column($clean, 'path');
        if ($kind === 'plugin' || $kind === 'widget') {
            $main = (string)($plan['slug'] ?? '') . '.php';
            $hdr = false;
            foreach ($clean as $f) {
                if (!str_contains($f['path'], '/') && strtolower(pathinfo($f['path'], PATHINFO_EXTENSION)) === 'php' && preg_match('/^\s*(?:\/\*+|\/\/|#)?\s*\*?\s*Plugin Name:\s*\S/mi', substr($f['content'], 0, 4000))) {
                    $hdr = true;
                }
            }
            if (!$hdr) {
                $errors[] = 'Die Hauptdatei braucht den Plugin-Kopf („Plugin Name:“). Erwartet: ' . $main;
            }
        } elseif ($kind === 'theme') {
            $css = null;
            foreach ($clean as $f) {
                if ($f['path'] === 'style.css') {
                    $css = $f['content'];
                }
            }
            if ($css === null || !preg_match('/Theme Name:\s*\S/i', substr($css, 0, 4000))) {
                $errors[] = 'Die Datei style.css mit „Theme Name:“ fehlt.';
            }
            if ($child) {
                if ($css !== null && !preg_match('/^\s*Template:\s*elvado-baukasten\s*$/mi', $css)) {
                    $errors[] = 'Ein Kindtheme braucht „Template: elvado-baukasten“ im Kopf von style.css.';
                }
                foreach ($paths as $p) {
                    if ($p !== 'style.css') {
                        $errors[] = 'Ein Kindtheme enthält nur style.css (zusätzlich: ' . $p . ').';
                    }
                }
            } elseif (!in_array('index.php', $paths, true)) {
                $errors[] = 'Ein Theme braucht mindestens style.css und index.php.';
            }
        }
        return ['errors' => array_values(array_unique($errors)), 'warnings' => array_values(array_unique($warnings)), 'ok' => $errors === [], 'files' => $clean];
    }

    /** @return array{0:list<string>,1:list<string>} Fehler, Warnungen */
    private static function scanFile(string $path, string $ext, string $c): array
    {
        $e = [];
        $w = [];
        if ($ext !== 'php' && preg_match('/<\?(php|=)/i', $c)) {
            $e[] = $path . ': PHP-Code in einer Datei, die kein PHP sein darf.';
        }
        if ($ext === 'php') {
            try {
                $tokens = token_get_all($c, TOKEN_PARSE);
            } catch (\ParseError $pe) {
                return [[$path . ': PHP-Syntaxfehler in Zeile ' . $pe->getLine() . ': ' . mb_substr($pe->getMessage(), 0, 120)], []];
            }
            $sig = [];
            foreach ($tokens as $t) {
                if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $sig[] = $t;
            }
            $n = count($sig);
            for ($i = 0; $i < $n; $i++) {
                $t = $sig[$i];
                if ($t === '`') {
                    $e[] = $path . ': Backtick-Operator (Programmausführung) ist nicht erlaubt.';
                    continue;
                }
                if (!is_array($t)) {
                    continue;
                }
                $id = $t[0];
                $txt = strtolower($t[1]);
                if ($id === T_EVAL) {
                    $e[] = $path . ': eval() ist nicht erlaubt (Zeile ' . $t[2] . ').';
                } elseif ($id === T_STRING) {
                    $next = $sig[$i + 1] ?? null;
                    $prev = $sig[$i - 1] ?? null;
                    $isCall = $next === '(' && !(is_array($prev) && in_array($prev[0], [T_FUNCTION, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW], true));
                    if ($isCall && in_array($txt, self::BLOCK_FUNCS, true)) {
                        $e[] = $path . ': ' . $txt . '() ist nicht erlaubt (Zeile ' . $t[2] . ').';
                    } elseif ($isCall && isset(self::WARN_FUNCS[$txt])) {
                        $w[] = $path . ': ' . $txt . '() ' . self::WARN_FUNCS[$txt] . '.';
                    } elseif ($isCall && str_starts_with($txt, 'curl_')) {
                        $w[] = $path . ': ' . $txt . '() greift auf das Netz zu.';
                    }
                } elseif ($id === T_VARIABLE && ($sig[$i + 1] ?? null) === '(' && $t[1] !== '$this') {
                    $w[] = $path . ': dynamischer Funktionsaufruf ' . $t[1] . '() (Zeile ' . $t[2] . ').';
                } elseif (in_array($id, [T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE], true)) {
                    $arg = '';
                    for ($j = $i + 1; $j < min($n, $i + 14) && $sig[$j] !== ';'; $j++) {
                        $arg .= is_array($sig[$j]) ? $sig[$j][1] : $sig[$j];
                    }
                    if (preg_match('~https?://|\$_(GET|POST|REQUEST|COOKIE|SERVER)~i', $arg)) {
                        $e[] = $path . ': include/require mit Adresse oder Benutzereingabe ist nicht erlaubt.';
                    } elseif (preg_match('/\$/', $arg) && !preg_match('/__DIR__|plugin_dir_path|get_template_directory|get_stylesheet_directory|ABSPATH|\$this->/i', $arg)) {
                        $w[] = $path . ': include/require mit Variable (Zeile ' . $t[2] . ').';
                    }
                } elseif ($id === T_VARIABLE && $t[1] === '$' ) {
                    $w[] = $path . ': variable Variablen.';
                } elseif ($id === T_VARIABLE && in_array($t[1], ['$_FILES'], true)) {
                    $w[] = $path . ': nimmt Datei-Uploads an.';
                }
            }
            if (preg_match('/\$\$[a-z_]/i', $c)) {
                $w[] = $path . ': variable Variablen ($$).';
            }
            if (preg_match('/\b(DROP|TRUNCATE)\s+(TABLE|DATABASE)\b/i', $c)) {
                $w[] = $path . ': enthält SQL zum Löschen von Tabellen.';
            }
            if (preg_match('~https?://~i', $c) && preg_match('/(wp_remote_|curl_|file_get_contents|fopen|fsockopen)/i', $c)) {
                $w[] = $path . ': Adressen im Code zusammen mit Netzwerkfunktionen.';
            }
        } elseif ($ext === 'js') {
            if (preg_match('/\beval\s*\(|new\s+Function\s*\(|document\.write\s*\(/', $c)) {
                $e[] = $path . ': eval/new Function/document.write sind nicht erlaubt.';
            }
            if (preg_match('~(fetch|XMLHttpRequest|sendBeacon|importScripts|createElement\(\s*[\'"]script[\'"]\s*\))~', $c) && preg_match('~https?://~', $c)) {
                $w[] = $path . ': JavaScript mit Netzwerkzugriff oder externen Adressen.';
            }
        } elseif ($ext === 'css') {
            if (preg_match('/expression\s*\(|javascript:|behavior\s*:/i', $c)) {
                $e[] = $path . ': unzulässiger CSS-Ausdruck.';
            }
            if (preg_match('~@import|url\(\s*[\'"]?https?:~i', $c)) {
                $w[] = $path . ': lädt Ressourcen von außen (@import/url(http…)).';
            }
        } elseif ($ext === 'html' && preg_match('/<script\b[^>]*\bsrc\s*=\s*[\'"]?https?:/i', $c)) {
            $w[] = $path . ': bindet externe Skripte ein.';
        }
        return [$e, $w];
    }

    // ------------------------------------------------------------------ Installieren (inaktiv)

    /**
     * Prüft erneut und schreibt die Dateien. Vorhandene Ordner werden nur überschrieben, wenn sie von der KI-Entwicklung stammen (Markierungsdatei).
     * @param array<string,mixed> $plan
     * @return array{slug:string,kind:string,dir:string,files:int,updated:bool}
     * @throws AiGatewayException
     */
    public static function install(array $plan, string $pluginsDir, string $themesDir, string $user = '', bool $confirmWarnings = false): array
    {
        $chk = self::check($plan);
        if (!$chk['ok']) {
            throw new AiGatewayException('Installation abgelehnt: ' . implode(' ', array_slice($chk['errors'], 0, 3)), 422);
        }
        if ($chk['warnings'] && !$confirmWarnings) {
            throw new AiGatewayException('Der Code enthält Funktionen, die du bestätigen musst (' . count($chk['warnings']) . ' Hinweise).', 409);
        }
        $kind = (string)$plan['kind'];
        $slug = (string)$plan['slug'];
        $root = $kind === 'theme' ? $themesDir : $pluginsDir;
        if (!is_dir($root) && !@mkdir($root, 0775, true) && !is_dir($root)) {
            throw new AiGatewayException('Der Zielordner ist nicht beschreibbar.', 500);
        }
        $final = rtrim($root, '/') . '/' . $slug;
        $updated = false;
        if (file_exists($final)) {
            if (is_link($final) || !is_file($final . '/.ai-generated.json')) {
                throw new AiGatewayException('Es gibt schon ein „' . $slug . '“, das nicht von der KI-Entwicklung stammt. Bitte einen anderen Namen wählen.', 409);
            }
            $updated = true;
        }
        $tmp = rtrim($root, '/') . '/.tmp-ai-' . bin2hex(random_bytes(4));
        try {
            if (!@mkdir($tmp, 0775, true)) {
                throw new AiGatewayException('Der Zielordner ist nicht beschreibbar.', 500);
            }
            foreach ($chk['files'] as $f) {
                $dest = $tmp . '/' . $f['path'];
                $dir = dirname($dest);
                if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
                    throw new AiGatewayException('Ordner konnte nicht angelegt werden.', 500);
                }
                if (@file_put_contents($dest, $f['content']) === false) {
                    throw new AiGatewayException('Datei konnte nicht geschrieben werden: ' . $f['path'], 500);
                }
            }
            if (!is_file($tmp . '/index.php') && $kind !== 'theme') {
                @file_put_contents($tmp . '/index.php', "<?php\n// Silence is golden.\n");
            }
            @file_put_contents($tmp . '/.ai-generated.json', json_encode(['kind' => $kind, 'created' => date('c'), 'user' => $user, 'files' => array_column($chk['files'], 'path'), 'warnings' => $chk['warnings']], JSON_UNESCAPED_UNICODE));
            $old = null;
            if ($updated) {
                $old = $final . '.old-' . bin2hex(random_bytes(3));
                if (!@rename($final, $old)) {
                    throw new AiGatewayException('Der bisherige Stand konnte nicht ersetzt werden.', 500);
                }
            }
            if (!@rename($tmp, $final)) {
                if ($old !== null) {
                    @rename($old, $final);
                }
                throw new AiGatewayException('Installation fehlgeschlagen.', 500);
            }
            if ($old !== null) {
                self::rmTree($old);
            }
        } finally {
            if (is_dir($tmp)) {
                self::rmTree($tmp);
            }
        }
        return ['slug' => $slug, 'kind' => $kind, 'dir' => $final, 'files' => count($chk['files']), 'updated' => $updated];
    }

    private static function rmTree(string $dir): void
    {
        if (is_link($dir) || !is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}

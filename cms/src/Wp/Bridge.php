<?php
declare(strict_types=1);
// cms/src/Wp/Bridge.php – Brücke zum echten WordPress-Core: Konstanten setzen und WordPress im selben PHP-Prozess starten (ohne Theme, ohne Weitergabe an wp-admin).
//
// Der Start muss im globalen Gültigkeitsbereich passieren (wp-settings.php setzt globale Variablen) – darum die kleine Datei cms/wp-engine-boot.php, die von der API auf oberster Ebene
// eingebunden wird. Die Nachbildung (cms/wp) und der echte Core schließen sich in einer Anfrage aus (gleiche Funktionsnamen); der übrige ElvadoPress-Code kollidiert nicht.
// WordPress verändert beim Start Superglobale (Schrägstriche) und die Zeitzone (UTC): beides wird gesichert und danach wiederhergestellt, damit ElvadoPress unverändert weiterläuft.

namespace Elvado\Wp;

final class Bridge
{
    /** @var resource|null */
    private static $lock = null;
    private static bool $probe = false;
    private static bool $booting = false;
    /** @var array<string,mixed>|null */
    private static ?array $recover = null;

    /** Nach dem Start: Probelauf bestanden → Wächter löschen, Sperre lösen. */
    public static function done(Engine $e): void
    {
        if (self::$probe) {
            $e->guardClear();
            self::$probe = false;
        }
        self::$booting = false;
        if (self::$lock) {
            @flock(self::$lock, LOCK_UN);
            @fclose(self::$lock);
            self::$lock = null;
        }
    }

    /**
     * Schwerer Fehler (fatal) während des Starts: Liegt die Datei in einem Plugin oder Theme, wird ein Wächter im Probelauf-Zustand geschrieben –
     * der nächste Start ist abgesichert und deaktiviert den Verursacher (Plugin/Theme), auch wenn kein Wechsel vorausging (Update, geänderte Datei, Umgebung).
     */
    public static function onShutdown(Engine $e): void
    {
        if (!self::$booting) {
            return;
        }
        $err = error_get_last();
        if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
            return;
        }
        $file = str_replace('\\', '/', (string)$err['file']);
        $content = str_replace('\\', '/', rtrim($e->contentDir(), '/'));
        if (str_starts_with($file, $content . '/plugins/') && preg_match('~/plugins/([^/]+)~', $file, $m)) {
            $kind = 'plugin';
        } elseif (str_starts_with($file, $content . '/themes/') && preg_match('~/themes/([^/]+)~', $file, $m)) {
            $kind = 'theme';
        } else {
            return;
        }
        try {
            $g = $e->guard();
            if ($g === null) {
                $e->guardSet($kind, $m[1], [], true);
            } elseif (!$g['probing']) {
                $e->guardSet($g['kind'], $g['id'], $g['previous'], true);
            }
            $e->log('Absturz beim Start in ' . $kind . ' „' . $m[1] . '“: ' . mb_substr((string)$err['message'], 0, 160));
        } catch (\Throwable) {
        }
    }

    /** War der letzte Start abgestürzt? Dann ist dieser Lauf abgesichert; recover() macht die Änderung rückgängig. */
    public static function recovering(): bool { return self::$recover !== null; }

    /**
     * Macht die abgestürzte Änderung rückgängig (Plugin deaktivieren bzw. früheres Theme setzen) und löscht den Wächter. Nur nach einem Start im abgesicherten Modus.
     * @return array{when:string,what:string}|null
     */
    public static function recover(Engine $e): ?array
    {
        $g = self::$recover;
        if ($g === null || !self::booted()) {
            return null;
        }
        // Im abgesicherten Modus sind die Optionen gesperrt – für das Rückgängigmachen kurz freigeben (die Sperre bleibt danach aus; der Lauf endet ohnehin mit der Anfrage)
        foreach (['active_plugins' => '__return_empty_array', 'template' => 'elvado_engine_safe_theme', 'stylesheet' => 'elvado_engine_safe_theme'] as $opt => $cb) {
            remove_filter('pre_option_' . $opt, $cb);
        }
        if ($g['kind'] === 'plugin') {
            $act = array_values(array_filter((array)get_option('active_plugins', []), static fn($p) => (string)$p !== $g['id'] && !str_starts_with((string)$p, $g['id'] . '/')));
            update_option('active_plugins', $act);
            $what = 'Das Plugin „' . $g['id'] . '“ hat WordPress zum Absturz gebracht und wurde deaktiviert.';
        } else {
            $prev = $g['previous'];
            if (!empty($prev['stylesheet']) && is_dir(get_theme_root() . '/' . $prev['stylesheet'])) {
                update_option('template', $prev['template'] ?? $prev['stylesheet']);
                update_option('stylesheet', $prev['stylesheet']);
                $what = 'Das Theme „' . $g['id'] . '“ hat WordPress zum Absturz gebracht; das vorherige Theme ist wieder aktiv.';
            } else {
                $what = 'Das Theme „' . $g['id'] . '“ hat WordPress zum Absturz gebracht; es gab kein früheres Theme – WordPress läuft im abgesicherten Modus.';
                $e->save(['safe' => true]);
            }
        }
        foreach (['active_plugins', 'template', 'stylesheet'] as $opt) {   // danach wieder abgesichert weiterlaufen
            add_filter('pre_option_' . $opt, $opt === 'active_plugins' ? '__return_empty_array' : 'elvado_engine_safe_theme');
        }
        $e->guardClear();
        $inc = ['when' => date('c'), 'what' => $what];
        $e->save(['incident' => $inc]);
        $e->log('Absturzschutz: ' . $what);
        self::$recover = null;
        return $inc;
    }

    /** @return bool Läuft echtes WordPress bereits in dieser Anfrage? */
    public static function booted(): bool
    {
        return defined('ABSPATH') && function_exists('wp_count_posts') && function_exists('add_action') && !function_exists('elvado_wp_boot');
    }

    /**
     * Konstanten setzen. @param array{installing?:bool,shortinit?:bool,site_url?:string} $opts
     * @return array{settings:string,prefix:string,snapshot:array<string,mixed>}
     */
    public static function prepare(Engine $e, DbConfig $db, array $opts = []): array
    {
        if (function_exists('elvado_wp_boot')) {
            throw new \RuntimeException('Die WordPress-Nachbildung ist in dieser Anfrage bereits geladen – echtes WordPress kann nicht zusätzlich starten.');
        }
        $core = $e->corePath();
        $cfg = $db->get();
        if ($core === null || $cfg === null) {
            throw new \RuntimeException('Die WordPress-Engine ist nicht vollständig eingerichtet (Core oder Datenbank fehlt).');
        }
        $site = rtrim((string)($opts['site_url'] ?? self::siteUrl()), '/');
        $content = $e->contentDir();
        // Absturzschutz: offener Wächter ⇒ Probelauf (unter Sperre, damit gleichzeitige Anfragen nicht fälschlich eingreifen); schon im Probelauf abgestürzt ⇒ abgesicherter Modus + Rückgängig
        self::$recover = null;
        self::$booting = true;
        register_shutdown_function([self::class, 'onShutdown'], $e);
        $safe = $e->safe();
        $g = $e->guard();
        if ($g !== null && empty($opts['installing'])) {
            $e->protect();
            self::$lock = @fopen($e->stateDir() . '/probe.lock', 'c');
            if (self::$lock) {
                @flock(self::$lock, LOCK_EX);
                $g = $e->guard();   // inzwischen von einer anderen Anfrage bereinigt?
            }
            if ($g !== null && $g['probing']) {
                $safe = true;
                self::$recover = $g;
            } elseif ($g !== null) {
                $e->guardSet($g['kind'], $g['id'], $g['previous'], true);
                self::$probe = true;
            }
            if (self::$probe !== true && self::$lock) {
                @flock(self::$lock, LOCK_UN);
                @fclose(self::$lock);
                self::$lock = null;
            }
        }
        $def = static function (string $n, mixed $v): void {
            if (!defined($n)) {
                define($n, $v);
            }
        };
        $def('ABSPATH', $core);
        $def('WP_CONTENT_DIR', $content);
        $def('WP_CONTENT_URL', $site . '/cms/wp-content');
        $def('WP_PLUGIN_DIR', $content . '/plugins');
        $def('WP_PLUGIN_URL', $site . '/cms/wp-content/plugins');
        $def('WPMU_PLUGIN_DIR', dirname(__DIR__) . '/Wp/mu');   // eigene Engine-Schicht; lädt danach die mu-plugins aus wp-content
        $def('WPMU_PLUGIN_URL', $site . '/cms/src/Wp/mu');
        $def('ELVADO_USER_MU_DIR', $content . '/mu-plugins');
        $def('ELVADO_ENGINE_SAFE', $safe);
        $def('WP_HOME', $site);
        $def('WP_SITEURL', $site);
        $def('DB_NAME', $cfg['name']);
        $def('DB_USER', $cfg['user']);
        $def('DB_PASSWORD', $cfg['pass']);
        $def('DB_HOST', $cfg['host']);
        $def('DB_CHARSET', 'utf8mb4');
        $def('DB_COLLATE', '');
        foreach ($db->keys() as $k => $v) {
            $def($k, $v);
        }
        $def('WP_DEBUG', false);
        $def('DISALLOW_FILE_EDIT', true);
        $def('DISABLE_WP_CRON', true);               // geplante Aufgaben löst ElvadoPress selbst aus (kein Aufruf der eigenen Adresse bei jedem Seitenaufruf)
        $def('AUTOMATIC_UPDATER_DISABLED', true);    // Updates laufen über das Update-Center von ElvadoPress
        $def('WP_AUTO_UPDATE_CORE', false);
        $def('WP_MEMORY_LIMIT', '256M');
        $def('WP_USE_THEMES', false);
        if (!empty($opts['installing'])) {
            $def('WP_INSTALLING', true);
        }
        if (!empty($opts['shortinit'])) {
            $def('SHORTINIT', true);
        }
        return ['settings' => $core . 'wp-settings.php', 'prefix' => $cfg['prefix'], 'snapshot' => self::snapshot()];
    }

    /** @return array<string,mixed> */
    public static function snapshot(): array
    {
        return [
            'get' => $_GET, 'post' => $_POST, 'cookie' => $_COOKIE, 'request' => $_REQUEST,
            'tz' => date_default_timezone_get(),
            'er' => error_reporting(),
            'display' => ini_get('display_errors'),
            'mb' => function_exists('mb_internal_encoding') ? mb_internal_encoding() : null,
        ];
    }

    /** @param array<string,mixed> $s */
    public static function restore(array $s): void
    {
        $_GET = $s['get'];
        $_POST = $s['post'];
        $_COOKIE = $s['cookie'];
        $_REQUEST = $s['request'];
        @date_default_timezone_set((string)$s['tz']);
        error_reporting((int)$s['er']);
        @ini_set('display_errors', (string)$s['display']);
        if ($s['mb'] !== null) {
            @mb_internal_encoding((string)$s['mb']);
        }
    }

    public static function siteUrl(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        return ($https ? 'https' : 'http') . '://' . preg_replace('/[^A-Za-z0-9.:\[\]-]/', '', $host);
    }

    /**
     * Tabellen anlegen und Grunddaten erzeugen (nur im Installationsmodus gestartet). Die Beispielinhalte von WordPress werden gleich wieder entfernt:
     * Inhalte kommen aus ElvadoPress. Der technische Benutzer „elvadopress“ erhält ein Zufallspasswort, das nirgends gespeichert wird (ElvadoPress meldet Benutzer selbst an).
     * @return array{ok:bool,message:string,user_id:int}
     */
    public static function installSchema(string $title, string $email): array
    {
        if (!defined('WP_INSTALLING') || !function_exists('add_filter')) {
            return ['ok' => false, 'message' => 'WordPress ist nicht im Installationsmodus gestartet.', 'user_id' => 0];
        }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        if (function_exists('is_blog_installed') && is_blog_installed()) {
            return ['ok' => false, 'message' => 'In dieser Datenbank ist WordPress bereits installiert.', 'user_id' => 0];
        }
        add_filter('pre_wp_mail', '__return_true');
        $r = wp_install($title !== '' ? $title : 'ElvadoPress', 'elvadopress', $email, true, '', bin2hex(random_bytes(16)), '');
        if (!is_array($r) || empty($r['user_id'])) {
            return ['ok' => false, 'message' => 'Die WordPress-Tabellen konnten nicht angelegt werden.', 'user_id' => 0];
        }
        // Beispielinhalte der Installation (Beitrag, Seite, Datenschutz-Entwurf, Kommentar) entfernen – die Datenbank war vorher leer, es kann nichts anderes sein
        global $wpdb;
        foreach ((array)$wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('post','page')") as $pid) {
            wp_delete_post((int)$pid, true);
        }
        update_option('wp_page_for_privacy_policy', 0);
        wp_update_term((int)get_option('default_category'), 'category', ['name' => 'Allgemein', 'slug' => 'allgemein']);   // deutsche Standard-Kategorie statt „Uncategorized“
        foreach ((array)$wpdb->get_col("SELECT comment_ID FROM {$wpdb->comments}") as $cid) {
            wp_delete_comment((int)$cid, true);
        }
        return ['ok' => true, 'message' => '', 'user_id' => (int)$r['user_id']];
    }
}

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
    /** @return bool Läuft echtes WordPress bereits in dieser Anfrage? */
    public static function booted(): bool
    {
        return defined('ABSPATH') && function_exists('wp_count_posts') && function_exists('add_action') && !function_exists('rrw_wp_boot');
    }

    /**
     * Konstanten setzen. @param array{installing?:bool,shortinit?:bool,site_url?:string} $opts
     * @return array{settings:string,prefix:string,snapshot:array<string,mixed>}
     */
    public static function prepare(Engine $e, DbConfig $db, array $opts = []): array
    {
        if (function_exists('rrw_wp_boot')) {
            throw new \RuntimeException('Die WordPress-Nachbildung ist in dieser Anfrage bereits geladen – echtes WordPress kann nicht zusätzlich starten.');
        }
        $core = $e->corePath();
        $cfg = $db->get();
        if ($core === null || $cfg === null) {
            throw new \RuntimeException('Die WordPress-Engine ist nicht vollständig eingerichtet (Core oder Datenbank fehlt).');
        }
        $site = rtrim((string)($opts['site_url'] ?? self::siteUrl()), '/');
        $content = $e->contentDir();
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
        $def('WPMU_PLUGIN_DIR', $content . '/mu-plugins');
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
        foreach ((array)$wpdb->get_col("SELECT comment_ID FROM {$wpdb->comments}") as $cid) {
            wp_delete_comment((int)$cid, true);
        }
        return ['ok' => true, 'message' => '', 'user_id' => (int)$r['user_id']];
    }
}

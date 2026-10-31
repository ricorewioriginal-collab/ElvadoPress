<?php
// Mehrsprachige Website (Einstellungen → Mehrsprachigkeit): Beiträge tragen eine Sprache und sind über eine Übersetzungsgruppe verknüpft.
// Die Standardsprache liegt unter /, weitere Sprachen unter /<code>/ (z. B. /en/). Die Website zeigt je Sprache nur die passenden Beiträge,
// gibt hreflang-Verweise aus und bietet einen Sprachumschalter (Shortcode [elvado_languages] und optional unten rechts).
// Konfiguration: site.json → multilang {enabled, languages[], switcher}. Ohne Einschalten ändert sich nichts.
declare(strict_types=1);

const ELVADO_ML_LANGS = ['de' => 'Deutsch', 'en' => 'English', 'fr' => 'Français', 'es' => 'Español', 'it' => 'Italiano', 'nl' => 'Nederlands', 'pl' => 'Polski', 'tr' => 'Türkçe', 'pt' => 'Português', 'da' => 'Dansk', 'sv' => 'Svenska', 'cs' => 'Čeština'];
const ELVADO_ML_LOCALES = ['de' => 'de_DE', 'en' => 'en_US', 'fr' => 'fr_FR', 'es' => 'es_ES', 'it' => 'it_IT', 'nl' => 'nl_NL', 'pl' => 'pl_PL', 'tr' => 'tr_TR', 'pt' => 'pt_PT', 'da' => 'da_DK', 'sv' => 'sv_SE', 'cs' => 'cs_CZ'];

/** Standardsprache der Website (aus der Einrichtung, sonst Deutsch). */
function elvado_ml_default(): string
{
    $l = '';
    try {
        if (!function_exists('elvado_system_config')) {
            require_once __DIR__ . '/system.php';
        }
        $l = substr((string)(elvado_system_config()['language'] ?? ''), 0, 2);
    } catch (Throwable $e) {
    }
    return isset(ELVADO_ML_LANGS[$l]) ? $l : 'de';
}

/** @return array{enabled:bool,default:string,languages:list<string>,switcher:bool} */
function elvado_ml_config(array $site): array
{
    $c = is_array($site['multilang'] ?? null) ? $site['multilang'] : [];
    $def = elvado_ml_default();
    $langs = [$def];
    foreach ((array)($c['languages'] ?? []) as $l) {
        $l = strtolower(substr((string)$l, 0, 2));
        if (isset(ELVADO_ML_LANGS[$l]) && !in_array($l, $langs, true)) {
            $langs[] = $l;
        }
    }
    return ['enabled' => !empty($c['enabled']) && count($langs) > 1, 'default' => $def, 'languages' => array_slice($langs, 0, 8), 'switcher' => !isset($c['switcher']) || !empty($c['switcher'])];
}

/** Eingaben der Verwaltung bereinigen. */
function elvado_ml_clean(array $in): array
{
    $langs = [];
    foreach ((array)($in['languages'] ?? []) as $l) {
        $l = strtolower(substr((string)$l, 0, 2));
        if (isset(ELVADO_ML_LANGS[$l]) && !in_array($l, $langs, true)) {
            $langs[] = $l;
        }
    }
    return ['enabled' => !empty($in['enabled']), 'languages' => array_slice($langs, 0, 8), 'switcher' => !empty($in['switcher'])];
}

/** Sprache eines Beitrags ('' = Standardsprache). */
function elvado_ml_article_lang(array $a, string $default): string
{
    $l = strtolower(substr((string)($a['lang'] ?? ''), 0, 2));
    return isset(ELVADO_ML_LANGS[$l]) ? $l : $default;
}

/**
 * Sprache aus dem Pfad: /en/… → ['en', '/…']; Standardsprache ohne Präfix.
 * @return array{0:string,1:string}
 */
function elvado_ml_detect(string $path, array $cfg): array
{
    if ($cfg['enabled'] && preg_match('~^/([a-z]{2})(/.*)?$~', $path, $m) && $m[1] !== $cfg['default'] && in_array($m[1], $cfg['languages'], true)) {
        return [$m[1], ($m[2] ?? '') === '' ? '/' : $m[2]];
    }
    return [$cfg['default'], $path];
}

/** Übersetzungen eines Beitrags (gleiche Gruppe), Sprache → Beitrag. @return array<string,array<string,mixed>> */
function elvado_ml_translations(array $news, array $article, string $default): array
{
    $g = (string)($article['translation_group'] ?? '');
    $out = [elvado_ml_article_lang($article, $default) => $article];
    if ($g === '') {
        return $out;
    }
    foreach ($news as $a) {
        if (is_array($a) && (string)($a['translation_group'] ?? '') === $g && empty($a['deleted_at'])) {
            $out[elvado_ml_article_lang($a, $default)] ??= $a;
        }
    }
    return $out;
}

/** Adresse einer Seite in einer Sprache (Präfix nur für Nicht-Standardsprachen). */
function elvado_ml_url(string $absUrl, string $lang, array $cfg): string
{
    if (!$cfg['enabled'] || $lang === $cfg['default']) {
        return $absUrl;
    }
    $p = parse_url($absUrl);
    $base = (isset($p['scheme']) ? $p['scheme'] . '://' : '') . ($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : '');
    return $base . '/' . $lang . ($p['path'] ?? '/') . (isset($p['query']) ? '?' . $p['query'] : '');
}

/** Sprachumschalter als HTML (Links auf Übersetzung bzw. Startseite der Sprache). @param array<string,string> $links Sprache → Adresse */
function elvado_ml_switcher_html(array $links, string $current, bool $floating): string
{
    $h = '<nav class="elvado-langs' . ($floating ? ' elvado-langs-float' : '') . '" aria-label="Sprache / Language">';
    foreach ($links as $l => $u) {
        $h .= '<a href="' . htmlspecialchars($u, ENT_QUOTES, 'UTF-8') . '" hreflang="' . $l . '" lang="' . $l . '"' . ($l === $current ? ' aria-current="true" class="on"' : '') . '>' . strtoupper($l) . '</a>';
    }
    $h .= '</nav>';
    if ($floating) {
        $h .= '<style>.elvado-langs-float{position:fixed;right:14px;bottom:14px;z-index:9990;display:flex;gap:2px;padding:4px;border-radius:999px;background:rgba(20,20,28,.86);box-shadow:0 4px 18px rgba(0,0,0,.25);font:600 13px/1 system-ui,sans-serif}'
            . '.elvado-langs-float a{color:#fff;text-decoration:none;padding:7px 10px;border-radius:999px}.elvado-langs-float a.on,.elvado-langs-float a:hover{background:#fff;color:#111}</style>';
    }
    return $h;
}

/** Filter und Ausgaben der Website-Laufzeit für die aufgerufene Sprache (nach elvado_wp_boot, nur mit eingeschalteter Mehrsprachigkeit). */
function elvado_ml_hooks(): void
{
    $ml = $GLOBALS['elvado_ml'] ?? null;
    if (!is_array($ml) || !function_exists('add_filter')) {
        return;
    }
    $lang = $ml['lang'];
    $cfg = $ml['cfg'];
    add_filter('locale', fn() => ELVADO_ML_LOCALES[$lang] ?? 'en_US', 50);
    if ($lang !== $cfg['default']) {   // Links der Website bleiben in der gewählten Sprache (technische Pfade ausgenommen)
        add_filter('home_url', function ($url, $path = '') use ($lang, $cfg) {
            $p = ltrim((string)$path, '/');
            if (preg_match('~^(cms|wp-content|wp-includes|wp-json|wp-admin|wp-login|wp-comments-post|rss\.xml|sitemap|robots|feed|' . $lang . '(/|$))~', $p) || str_contains((string)$url, '/' . $lang . '/')) {
                return $url;
            }
            return elvado_ml_url((string)$url, $lang, $cfg);
        }, 20, 2);
    }
    $links = function () use ($lang, $cfg): array {
        $plain = function (string $u) use ($lang, $cfg): string {   // Adresse ohne Präfix der aktuellen Sprache
            return $lang === $cfg['default'] ? $u : (string)preg_replace('~^(https?://[^/]+)/' . $lang . '(/|$)~', '$1/', $u);
        };
        $home = $plain(home_url('/'));
        $out = [];
        foreach ($cfg['languages'] as $l) {
            $out[$l] = elvado_ml_url($home, $l, $cfg);
        }
        $obj = function_exists('get_queried_object') ? get_queried_object() : null;
        $data = is_object($obj) && isset($obj->elvado_data) && is_array($obj->elvado_data) && ($obj->elvado_source ?? '') === 'news' ? $obj->elvado_data : null;
        if ($data !== null) {
            $all = elvado_wp_cms_data()['news_all'] ?? [];
            $now = elvado_wp_cms_now();
            foreach (elvado_ml_translations((array)$all, $data, $cfg['default']) as $l => $a) {
                if (!in_array($l, $cfg['languages'], true) || ($a['status'] ?? '') !== 'published' || trim((string)($a['published_at'] ?? '')) > $now) {
                    continue;
                }
                $out[$l] = elvado_ml_url($plain((string)get_permalink(elvado_wp_cms_news_post($a))), $l, $cfg);
            }
        }
        return $out;
    };
    add_action('wp_head', function () use ($links, $cfg) {
        foreach ($links() as $l => $u) {
            echo '<link rel="alternate" hreflang="' . esc_attr($l) . '" href="' . esc_url($u) . '">' . "\n";
        }
        echo '<link rel="alternate" hreflang="x-default" href="' . esc_url($links()[$cfg['default']] ?? home_url('/')) . '">' . "\n";
    }, 2);
    add_action('template_redirect', function () use ($lang, $cfg) {   // Beitrag einer anderen Sprache ohne passendes Präfix → richtige Adresse (keine doppelten Inhalte)
        $obj = function_exists('get_queried_object') ? get_queried_object() : null;
        if (!is_object($obj) || ($obj->elvado_source ?? '') !== 'news' || !is_array($obj->elvado_data ?? null)) {
            return;
        }
        $al = elvado_ml_article_lang($obj->elvado_data, $cfg['default']);
        if ($al !== $lang && in_array($al, $cfg['languages'], true)) {
            $u = (string)get_permalink($obj);
            $u = $lang === $cfg['default'] ? $u : (string)preg_replace('~^(https?://[^/]+)/' . $lang . '(/|$)~', '$1/', $u);
            wp_redirect(elvado_ml_url($u, $al, $cfg), 301);
            exit;
        }
    });
    if (function_exists('add_shortcode')) {
        add_shortcode('elvado_languages', fn() => elvado_ml_switcher_html($links(), $lang, false));
    }
    if ($cfg['switcher']) {
        add_action('wp_footer', function () use ($links, $lang) { echo elvado_ml_switcher_html($links(), $lang, true); }, 30);
    }
}

/**
 * Mehrsprachigkeit im echten WordPress (Website-Auslieferung durch WordPress). Geladen von der Engine-Schicht (mu/elvado-engine.php), wenn
 * cms/wp-engine-front.php eine Sprache erkannt hat ($GLOBALS['elvado_ml']). Beiträge tragen die Meta _elvado_lang/_elvado_group (LiveSync).
 */
function elvado_ml_wp_hooks(): void
{
    $ml = $GLOBALS['elvado_ml'] ?? null;
    if (!is_array($ml) || !function_exists('add_filter')) {
        return;
    }
    $lang = $ml['lang'];
    $cfg = $ml['cfg'];
    $locale = ELVADO_ML_LOCALES[$lang] ?? 'en_US';
    add_filter('locale', fn() => $locale, 50);
    add_filter('pre_determine_locale', fn() => $locale, 50);
    // Nur Inhalte der aufgerufenen Sprache (Inhalte ohne Sprache gehören zur Standardsprache)
    add_action('pre_get_posts', function ($q) use ($lang, $cfg) {
        if (is_admin() || $q->get('elvado_ml_skip')) {
            return;
        }
        $pt = $q->get('post_type');
        $pts = is_array($pt) ? $pt : ($pt === '' || $pt === null ? ['post'] : [(string)$pt]);
        if (!array_intersect($pts, ['post', 'page', 'any'])) {   // Menüeinträge, Anhänge, Vorlagen usw. bleiben unberührt
            return;
        }
        $mq = (array)$q->get('meta_query');
        $mq[] = $lang === $cfg['default']
            ? ['relation' => 'OR', ['key' => '_elvado_lang', 'value' => $lang], ['key' => '_elvado_lang', 'compare' => 'NOT EXISTS']]
            : ['key' => '_elvado_lang', 'value' => $lang];
        $q->set('meta_query', $mq);
    });
    if ($lang !== $cfg['default']) {
        add_filter('home_url', function ($url, $path = '') use ($lang, $cfg) {
            $p = ltrim((string)$path, '/');
            if (preg_match('~^(cms|wp-content|wp-includes|wp-json|wp-admin|wp-login|wp-comments-post|wp-cron|xmlrpc|robots|' . $lang . '(/|$))~', $p) || str_contains((string)$url, '/' . $lang . '/')) {
                return $url;
            }
            return elvado_ml_url((string)$url, $lang, $cfg);
        }, 20, 2);
    }
    $plain = function (string $u) use ($lang, $cfg): string {
        return $lang === $cfg['default'] ? $u : (string)preg_replace('~^(https?://[^/]+)/' . $lang . '(/|$)~', '$1/', $u);
    };
    $links = function () use ($lang, $cfg, $plain): array {
        $out = [];
        $home = $plain(home_url('/'));
        foreach ($cfg['languages'] as $l) {
            $out[$l] = elvado_ml_url($home, $l, $cfg);
        }
        if (is_singular()) {
            $id = (int)get_queried_object_id();
            $g = (string)get_post_meta($id, '_elvado_group', true);
            if ($g !== '') {
                $sib = get_posts(['post_type' => 'any', 'post_status' => 'publish', 'numberposts' => 20, 'meta_key' => '_elvado_group', 'meta_value' => $g, 'elvado_ml_skip' => 1]);
                foreach ($sib as $p) {
                    $l = (string)get_post_meta($p->ID, '_elvado_lang', true) ?: $cfg['default'];
                    if (in_array($l, $cfg['languages'], true)) {
                        $out[$l] = elvado_ml_url($plain((string)get_permalink($p)), $l, $cfg);
                    }
                }
            }
        }
        return $out;
    };
    add_action('wp_head', function () use ($links, $cfg) {
        $ls = $links();
        foreach ($ls as $l => $u) {
            echo '<link rel="alternate" hreflang="' . esc_attr($l) . '" href="' . esc_url($u) . '">' . "\n";
        }
        echo '<link rel="alternate" hreflang="x-default" href="' . esc_url($ls[$cfg['default']] ?? home_url('/')) . '">' . "\n";
    }, 2);
    add_shortcode('elvado_languages', fn() => elvado_ml_switcher_html($links(), $lang, false));
    if ($cfg['switcher']) {
        add_action('wp_footer', function () use ($links, $lang) { echo elvado_ml_switcher_html($links(), $lang, true); }, 30);
    }
}

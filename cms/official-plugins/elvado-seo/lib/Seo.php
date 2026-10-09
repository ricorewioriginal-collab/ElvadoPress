<?php
declare(strict_types=1);
// SEO: Titel, Meta-Beschreibung, OpenGraph/Twitter, Schema.org, robots, XML-Sitemap. Arbeitet auf der fertig gerenderten Seite (front_output) und ergänzt nur, was das Theme noch nicht ausgibt.

namespace ElvadoPlugin\Seo;

use Elvado\Plugin\Context;

final class Seo
{
    public function __construct(private readonly Context $np) {}

    private function s(string $k): mixed { return $this->np->setting($k); }
    private static function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

    // ---------------------------------------------------------------- Hilfen

    public static function trim(string $text, int $max = 160): string
    {
        $t = trim((string)preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8')));
        if (mb_strlen($t) <= $max) {
            return $t;
        }
        $cut = mb_substr($t, 0, $max - 1);
        $sp = mb_strrpos($cut, ' ');
        return rtrim($sp !== false && $sp > $max * 0.6 ? mb_substr($cut, 0, $sp) : $cut, " ,.;:-") . '…';
    }

    private function abs(string $u): string
    {
        $u = trim($u);
        if ($u === '' || preg_match('~^https?://~i', $u)) {
            return $u;
        }
        return rtrim(home_url('/'), '/') . '/' . ltrim($u, '/');
    }

    private function siteDefaults(): array
    {
        $site = (array)($GLOBALS['ELVADO_SITE'] ?? []);
        $d = function_exists('elvado_seo_defaults') ? elvado_seo_defaults($site) : [];
        return ['title' => trim((string)($d['site_title'] ?? '')) ?: (string)get_bloginfo('name'), 'description' => trim((string)($d['description'] ?? '')), 'og_image' => trim((string)($d['og_image'] ?? '')), 'robots' => (string)($d['robots'] ?? 'index,follow'),
            'name' => (string)get_bloginfo('name') ?: (trim((string)($d['site_title'] ?? '')) ?: 'Website'), 'logo' => (string)($site['branding']['logo'] ?? $site['portal']['logo'] ?? '')];
    }

    /** Daten der aktuell ausgelieferten Seite. @return array{kind:string,title:string,description:string,image:string,noindex:bool,canonical:string,published:string,modified:string,author:string,category:string,seo_title:string,post:?\WP_Post} */
    public function context(): array
    {
        $c = ['kind' => 'other', 'title' => '', 'description' => '', 'image' => '', 'noindex' => false, 'canonical' => '', 'published' => '', 'modified' => '', 'author' => '', 'category' => '', 'seo_title' => '', 'post' => null];
        if (function_exists('is_404') && is_404()) {
            $c['kind'] = '404';
            $c['noindex'] = true;
            return $c;
        }
        if (is_front_page() || is_home() && !is_singular()) {
            $c['kind'] = is_front_page() ? 'front' : 'blog';
        }
        if (is_singular()) {
            $o = get_queried_object();
            if ($o instanceof \WP_Post) {
                return $this->fromPost($o, $c);
            }
        }
        if (is_search()) {
            $c['kind'] = 'search';
            $c['noindex'] = (bool)$this->s('noindex_search');
        } elseif (is_date() || is_author() || is_tag()) {
            $c['kind'] = 'archive';
            $c['noindex'] = (bool)$this->s('noindex_archives');
        } elseif (is_category()) {
            $c['kind'] = 'category';
            $t = get_queried_object();
            $c['title'] = is_object($t) ? (string)($t->name ?? '') : '';
            $c['description'] = is_object($t) ? self::trim((string)($t->description ?? ''), 160) : '';
        }
        return $c;
    }

    private function fromPost(\WP_Post $o, array $c): array
    {
        $d = is_array($o->elvado_data ?? null) ? $o->elvado_data : [];
        $src = (string)($o->elvado_source ?? 'db');
        $c['post'] = $o;
        $c['kind'] = $o->post_type === 'page' ? ($c['kind'] === 'front' ? 'front' : 'page') : 'post';
        $c['title'] = (string)$o->post_title;
        $content = (string)($o->post_content ?? '');
        if ($src === 'news') {
            $c['seo_title'] = trim((string)($d['seo_title'] ?? ''));
            $c['description'] = trim((string)($d['seo_description'] ?? '')) ?: self::trim((string)($d['excerpt'] ?? ''), 160);
            $c['image'] = trim((string)($d['image_url'] ?? ''));
            $c['noindex'] = !empty($d['noindex']);
            $c['canonical'] = trim((string)($d['canonical_url'] ?? ''));
            $c['author'] = trim((string)($d['author'] ?? ''));
            $c['category'] = trim((string)($d['category'] ?? ''));
        } elseif ($src === 'page') {
            $c['seo_title'] = trim((string)($d['meta_title'] ?? ''));
            $c['description'] = trim((string)($d['meta_description'] ?? '')) ?: self::trim((string)($d['intro'] ?? ''), 160);
            $c['noindex'] = !empty($d['noindex']);
        } else {
            $c['seo_title'] = trim((string)get_post_meta($o->ID, '_elvado_seo_title', true));
            $c['description'] = trim((string)get_post_meta($o->ID, '_elvado_seo_description', true));
            $c['noindex'] = (string)get_post_meta($o->ID, '_elvado_seo_noindex', true) === '1';
        }
        if ($c['description'] === '') {
            $c['description'] = self::trim((string)$o->post_excerpt, 160) ?: self::trim($content, 160);
        }
        if ($c['image'] === '' && function_exists('get_the_post_thumbnail_url')) {
            $c['image'] = (string)get_the_post_thumbnail_url($o, 'full');
        }
        if ($c['image'] === '' && preg_match('~<img[^>]+src=["\']([^"\']+)["\']~i', $content, $m)) {
            $c['image'] = $m[1];
        }
        $c['published'] = $o->post_date_gmt && $o->post_date_gmt !== '0000-00-00 00:00:00' ? gmdate('c', (int)strtotime($o->post_date_gmt . ' UTC')) : '';
        $c['modified'] = $o->post_modified_gmt && $o->post_modified_gmt !== '0000-00-00 00:00:00' ? gmdate('c', (int)strtotime($o->post_modified_gmt . ' UTC')) : $c['published'];
        if ($c['author'] === '') {
            $u = get_userdata((int)$o->post_author);
            $c['author'] = $u ? (string)($u->display_name ?? '') : '';
        }
        return $c;
    }

    // ---------------------------------------------------------------- Ausgabe

    /** Filter front_output: fehlende SEO-Angaben in den <head> einfügen. */
    public function inject(string $html, int $status): string
    {
        if ($status !== 200 && $status !== 404 || !function_exists('is_singular') || ($p = stripos($html, '</head>')) === false) {
            return $html;
        }
        $head = substr($html, 0, $p);
        $c = $this->context();
        $site = $this->siteDefaults();
        $has = static fn(string $re): bool => (bool)preg_match($re, $head);
        $add = '';

        // Titel
        $title = $this->title($c, $site);
        if ($title !== '') {
            $html = $this->setTitle($html, $title, $head, $add);
            $head = substr($html, 0, stripos($html, '</head>') ?: strlen($html));
        }
        $pageTitle = $c['seo_title'] ?: ($c['title'] ?: $site['title']);
        if ($c['kind'] === 'front' || $c['kind'] === 'blog') {
            $pageTitle = trim((string)$this->s('home_title')) ?: $site['title'];
        }
        // Beschreibung
        $desc = $c['description'];
        if ($c['kind'] === 'front') {
            $desc = trim((string)$this->s('home_description')) ?: $site['description'];
        }
        if ($desc === '') {
            $desc = $site['description'];
        }
        $desc = self::trim($desc, 200);
        if ($this->s('meta') && $desc !== '' && !$has('~<meta[^>]+name=["\']description["\']~i')) {
            $add .= '<meta name="description" content="' . self::h($desc) . "\" />\n";
        }
        // robots
        $robots = $this->robots($c, $site);
        if ($robots !== '' && !$has('~<meta[^>]+name=["\']robots["\']~i')) {
            $add .= '<meta name="robots" content="' . self::h($robots) . "\" />\n";
        } elseif ($robots !== '' && $this->s('robots_extras') && preg_match('~<meta[^>]+name=["\']robots["\'][^>]*content=["\']([^"\']*)["\'][^>]*>~i', $head, $m) && !str_contains($m[1], 'noindex') && !str_contains($m[1], 'max-image-preview')) {
            $html = str_replace($m[0], str_replace($m[1], $m[1] . ',max-image-preview:large,max-snippet:-1', $m[0]), $html);
        }
        // Kanonische Adresse (das Theme gibt sie für Einzelseiten selbst aus; hier nur Fehlendes)
        $canonical = $c['canonical'] ?: $this->currentUrl($c);
        if ($canonical !== '' && !$has('~<link[^>]+rel=["\']canonical["\']~i') && !in_array($c['kind'], ['404', 'search'], true)) {
            $add .= '<link rel="canonical" href="' . self::h($canonical) . "\" />\n";
        }
        // OpenGraph / Twitter
        if ($this->s('og') && !in_array($c['kind'], ['404'], true)) {
            $image = $this->abs($c['image'] ?: trim((string)$this->s('og_image')) ?: $site['og_image']);
            $og = ['og:type' => $c['kind'] === 'post' ? 'article' : 'website', 'og:site_name' => $site['name'], 'og:title' => $pageTitle, 'og:description' => $desc, 'og:url' => $canonical, 'og:locale' => str_replace('-', '_', (string)get_locale())];
            if ($image !== '') {
                $og['og:image'] = $image;
            }
            if ($c['kind'] === 'post') {
                $og['article:published_time'] = $c['published'];
                $og['article:modified_time'] = $c['modified'];
            }
            foreach ($og as $k => $v) {
                if ($v !== '' && !$has('~<meta[^>]+property=["\']' . preg_quote($k, '~') . '["\']~i')) {
                    $add .= '<meta property="' . $k . '" content="' . self::h((string)$v) . "\" />\n";
                }
            }
            $tw = ['twitter:card' => $image !== '' ? 'summary_large_image' : 'summary', 'twitter:title' => $pageTitle, 'twitter:description' => $desc];
            if ($image !== '') {
                $tw['twitter:image'] = $image;
            }
            if (($h = ltrim(trim((string)$this->s('twitter')), '@')) !== '' && preg_match('/^[A-Za-z0-9_]{1,15}$/', $h)) {
                $tw['twitter:site'] = '@' . $h;
            }
            foreach ($tw as $k => $v) {
                if ($v !== '' && !$has('~<meta[^>]+name=["\']' . preg_quote($k, '~') . '["\']~i')) {
                    $add .= '<meta name="' . $k . '" content="' . self::h((string)$v) . "\" />\n";
                }
            }
        }
        // Schema.org
        if ($this->s('schema') && !$has('~application/ld\+json~i')) {
            foreach ($this->schema($c, $site, $pageTitle, $desc, $canonical) as $node) {
                $add .= '<script type="application/ld+json">' . json_encode($node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . "</script>\n";
            }
        }
        if ($add === '') {
            return $html;
        }
        $p = stripos($html, '</head>');
        return substr($html, 0, $p) . "<!-- Elvado SEO -->\n" . $add . substr($html, $p);
    }

    private function title(array $c, array $site): string
    {
        if ($c['kind'] === 'front' || $c['kind'] === 'blog') {
            return trim((string)$this->s('home_title'));
        }
        if ($c['seo_title'] !== '') {
            return $c['seo_title'];
        }
        $tpl = trim((string)$this->s('title_template'));
        if ($tpl !== '' && $c['title'] !== '') {
            return str_replace(['%title%', '%site%'], [$c['title'], $site['name']], $tpl);
        }
        return '';
    }

    private function setTitle(string $html, string $title, string $head, string &$add): string
    {
        $t = '<title>' . self::h($title) . '</title>';
        if (preg_match('~<title\b[^>]*>.*?</title>~is', $head)) {
            return preg_replace_callback('~<title\b[^>]*>.*?</title>~is', static fn() => $t, $html, 1) ?? $html;
        }
        $add .= $t . "\n";
        return $html;
    }

    private function robots(array $c, array $site): string
    {
        $parts = [];
        if ($c['noindex']) {
            $parts = ['noindex', 'follow'];
        } elseif (str_starts_with(strtolower($site['robots']), 'noindex')) {
            $parts = ['noindex', 'nofollow'];   // Website insgesamt auf „nicht indexieren“ gestellt (SEO-Einstellungen)
        } elseif ($this->s('robots_extras')) {
            $parts = ['index', 'follow', 'max-image-preview:large', 'max-snippet:-1'];
        }
        return implode(',', $parts);
    }

    private function currentUrl(array $c): string
    {
        if ($c['post'] instanceof \WP_Post) {
            return (string)get_permalink($c['post']);
        }
        if ($c['kind'] === 'front') {
            return home_url('/');
        }
        $u = home_url((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
        return $u;
    }

    private function schema(array $c, array $site, string $title, string $desc, string $url): array
    {
        $out = [];
        $home = home_url('/');
        $org = ['@type' => 'Organization', '@id' => $home . '#organization', 'name' => trim((string)$this->s('org_name')) ?: $site['name'], 'url' => $home];
        $logo = $this->abs(trim((string)$this->s('org_logo')) ?: $site['logo']);
        if ($logo !== '') {
            $org['logo'] = $logo;
        }
        if ($c['kind'] === 'front') {
            $out[] = ['@context' => 'https://schema.org', '@type' => 'WebSite', '@id' => $home . '#website', 'url' => $home, 'name' => $site['name'], 'publisher' => ['@id' => $home . '#organization'],
                'potentialAction' => ['@type' => 'SearchAction', 'target' => $home . '?s={search_term_string}', 'query-input' => 'required name=search_term_string']];
            $out[] = ['@context' => 'https://schema.org'] + $org;
        }
        if ($c['kind'] === 'post' && $c['post'] instanceof \WP_Post) {
            $a = ['@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => mb_substr($c['title'], 0, 110), 'mainEntityOfPage' => $url, 'url' => $url, 'datePublished' => $c['published'], 'dateModified' => $c['modified'],
                'publisher' => $org];
            if ($desc !== '') {
                $a['description'] = $desc;
            }
            if ($c['author'] !== '') {
                $a['author'] = ['@type' => 'Person', 'name' => $c['author']];
            }
            $img = $this->abs($c['image']);
            if ($img !== '') {
                $a['image'] = [$img];
            }
            $out[] = $a;
        }
        if (in_array($c['kind'], ['post', 'page'], true) && $url !== '') {
            $items = [['@type' => 'ListItem', 'position' => 1, 'name' => $site['name'], 'item' => $home]];
            if ($c['kind'] === 'post' && $c['category'] !== '') {
                $items[] = ['@type' => 'ListItem', 'position' => 2, 'name' => $c['category']];
            }
            $items[] = ['@type' => 'ListItem', 'position' => count($items) + 1, 'name' => $c['title'], 'item' => $url];
            $out[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
        }
        return $out;
    }

    // ---------------------------------------------------------------- Sitemap

    private function flag(): string { return $this->np->dataDir() . '/sitemap.dirty'; }

    public function markDirty(string $what = ''): void
    {
        if ($this->s('sitemap')) {
            @touch($this->flag());
        }
    }

    /** Aktion content_saved('site'): Die Core-Sitemap wurde gerade geschrieben – sofort ersetzen. */
    public function onContentSaved(string $what = ''): void
    {
        if (!$this->s('sitemap')) {
            return;
        }
        if ($what === 'site') {
            $this->generate();
        } else {
            $this->markDirty();
        }
    }

    public function tick(): void
    {
        $f = $this->np->rootDir() . '/sitemap.xml';
        $ours = is_file($f) && str_contains((string)@file_get_contents($f, false, null, 0, 200), 'Elvado SEO');
        if ($this->s('sitemap') && !$ours && !is_file($this->flag())) {
            @touch($this->flag());   // erste Sitemap nach der Aktivierung (oder wenn eine fremde sitemap.xml im Weg liegt)
        }
        if (is_file($this->flag())) {
            @unlink($this->flag());
            $this->generate();
        }
    }

    /** @return array{ok:bool,message:string,count:int} */
    public function generate(): array
    {
        try {
            if (function_exists('elvado_wp_boot')) {
                elvado_wp_boot(['theme' => true]);   // mit Theme: ein früher Start ohne Theme würde ihn für den Rest der Anfrage festschreiben
            }
            if (!class_exists('WP_Query')) {
                return ['ok' => false, 'message' => 'Die WordPress-Schicht ist nicht verfügbar.', 'count' => 0];
            }
            $urls = [];
            $q = new \WP_Query(['post_type' => ['post', 'page'], 'post_status' => 'publish', 'posts_per_page' => 5000, 'no_found_rows' => true, 'orderby' => 'modified', 'order' => 'DESC', 'ignore_sticky_posts' => true]);
            foreach ((array)$q->posts as $p) {
                if (!($p instanceof \WP_Post) || !empty($p->post_password)) {
                    continue;
                }
                $d = is_array($p->elvado_data ?? null) ? $p->elvado_data : [];
                if (!empty($d['noindex']) || (string)get_post_meta($p->ID, '_elvado_seo_noindex', true) === '1') {
                    continue;
                }
                $loc = (string)get_permalink($p);
                if ($loc === '' || str_contains($loc, '?p=') || str_contains($loc, '?page_id=')) {
                    continue;
                }
                $urls[$loc] = substr((string)$p->post_modified_gmt, 0, 10) ?: gmdate('Y-m-d');
            }
            $home = home_url('/');
            $urls = [$home => gmdate('Y-m-d')] + $urls;
            $terms = function_exists('get_terms') ? get_terms(['taxonomy' => 'category', 'hide_empty' => true]) : [];
            foreach (is_array($terms) ? $terms : [] as $t) {
                $l = get_term_link($t);
                if (is_string($l) && $l !== '') {
                    $urls[$l] = gmdate('Y-m-d');
                }
            }
            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<!-- Elvado SEO -->\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
            foreach (array_slice($urls, 0, 50000, true) as $loc => $mod) {
                $xml .= '  <url><loc>' . htmlspecialchars((string)$loc, ENT_XML1, 'UTF-8') . '</loc><lastmod>' . htmlspecialchars((string)$mod, ENT_XML1, 'UTF-8') . "</lastmod></url>\n";
            }
            $xml .= "</urlset>\n";
            $f = $this->np->rootDir() . '/sitemap.xml';
            $tmp = $f . '.tmp' . bin2hex(random_bytes(3));
            if (@file_put_contents($tmp, $xml) === false || !@rename($tmp, $f)) {
                @unlink($tmp);
                return ['ok' => false, 'message' => 'sitemap.xml konnte nicht geschrieben werden (Rechte im Website-Ordner prüfen).', 'count' => 0];
            }
            return ['ok' => true, 'message' => 'Sitemap mit ' . count($urls) . ' Adressen geschrieben.', 'count' => count($urls)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Sitemap fehlgeschlagen: ' . $e->getMessage(), 'count' => 0];
        }
    }

    // ---------------------------------------------------------------- Beschreibungen ergänzen / Übersicht

    private function dataDir(): string { return $this->np->cmsDir() . '/data'; }

    /** Fehlende SEO-Beschreibungen (Beiträge, Seiten) aus Teaser bzw. Text ergänzen. @return array{news:int,pages:int} */
    public function fillDescriptions(): array
    {
        require_once $this->np->cmsDir() . '/lib/publish.php';
        $res = ['news' => 0, 'pages' => 0];
        $nf = $this->dataDir() . '/news.json';
        $news = elvado_read_json($nf, []);
        $chg = false;
        foreach ($news as &$a) {
            if (!is_array($a) || !empty($a['deleted_at']) || trim((string)($a['seo_description'] ?? '')) !== '') {
                continue;
            }
            $d = self::trim((string)($a['excerpt'] ?? '')) !== '' ? self::trim((string)$a['excerpt'], 160) : self::trim((string)($a['body_html'] ?? ''), 160);
            if ($d !== '') {
                $a['seo_description'] = $d;
                $res['news']++;
                $chg = true;
            }
        }
        unset($a);
        if ($chg) {
            elvado_write_atomic($nf, json_encode($news, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        }
        $sf = $this->dataDir() . '/site.json';
        $site = elvado_read_json($sf, []);
        $chg = false;
        foreach ((array)($site['pages'] ?? []) as $i => $p) {
            if (!is_array($p) || trim((string)($p['meta_description'] ?? '')) !== '') {
                continue;
            }
            $src = (string)($p['intro'] ?? '');
            foreach ((array)($p['blocks_before'] ?? []) as $b) {
                $src .= ' ' . (is_array($b) ? (string)($b['html'] ?? $b['text'] ?? '') : '');
            }
            $d = self::trim($src, 160);
            if ($d !== '') {
                $site['pages'][$i]['meta_description'] = $d;
                $res['pages']++;
                $chg = true;
            }
        }
        if ($chg) {
            elvado_write_atomic($sf, json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        }
        return $res;
    }

    /** Zahlen und Prüfungen für die Übersicht. */
    public function overview(): array
    {
        require_once $this->np->cmsDir() . '/lib/publish.php';
        $news = array_filter((array)elvado_read_json($this->dataDir() . '/news.json', []), static fn($a) => is_array($a) && empty($a['deleted_at']) && ($a['status'] ?? '') === 'published');
        $noDesc = count(array_filter($news, static fn($a) => trim((string)($a['seo_description'] ?? '')) === '' && trim((string)($a['excerpt'] ?? '')) === ''));
        $noIndex = count(array_filter($news, static fn($a) => !empty($a['noindex'])));
        $site = (array)elvado_read_json($this->dataDir() . '/site.json', []);
        $d = function_exists('elvado_seo_defaults') ? elvado_seo_defaults($site) : [];
        $sm = $this->np->rootDir() . '/sitemap.xml';
        $smOurs = is_file($sm) && str_contains((string)@file_get_contents($sm, false, null, 0, 200), 'Elvado SEO');
        $checks = [];
        $add = static function (string $l, string $s, string $t) use (&$checks): void { $checks[] = ['label' => $l, 'status' => $s, 'text' => $t]; };
        $add('Website indexierbar', str_starts_with(strtolower((string)($d['robots'] ?? 'index,follow')), 'noindex') ? 'bad' : 'ok', str_starts_with(strtolower((string)($d['robots'] ?? 'index,follow')), 'noindex') ? 'Die ganze Website steht auf „nicht indexieren“ (SEO-Einstellungen). Suchmaschinen ignorieren sie.' : 'Suchmaschinen dürfen die Website indexieren.');
        $add('Website-Titel', trim((string)($d['site_title'] ?? '')) !== '' ? 'ok' : 'warn', trim((string)($d['site_title'] ?? '')) !== '' ? (string)$d['site_title'] : 'Kein Website-Titel gesetzt.');
        $add('Website-Beschreibung', trim((string)($d['description'] ?? '')) !== '' || trim((string)$this->s('home_description')) !== '' ? 'ok' : 'warn', trim((string)($d['description'] ?? '')) !== '' || trim((string)$this->s('home_description')) !== '' ? 'Vorhanden.' : 'Fehlt – die Startseite hat sonst keine Meta-Beschreibung.');
        $base = (string)($d['canonical_base'] ?? '');
        $add('Kanonische Adresse', str_starts_with($base, 'https://') ? 'ok' : 'warn', $base !== '' ? $base . (str_starts_with($base, 'https://') ? '' : ' – besser mit https://') : 'Nicht gesetzt.');
        $add('Standard-Bild zum Teilen', ($this->s('og_image') !== '' || ($d['og_image'] ?? '') !== '') ? 'ok' : 'info', ($this->s('og_image') !== '' || ($d['og_image'] ?? '') !== '') ? 'Gesetzt.' : 'Keines gesetzt – Seiten ohne eigenes Bild erscheinen ohne Vorschaubild.');
        $add('Sitemap', is_file($sm) ? ($smOurs || !$this->s('sitemap') ? 'ok' : 'warn') : 'warn', is_file($sm) ? ($smOurs ? 'sitemap.xml wird von diesem Plugin gepflegt (' . date('d.m.Y H:i', (int)filemtime($sm)) . ').' : 'sitemap.xml stammt noch nicht von diesem Plugin – „Sitemap jetzt erzeugen“.') : 'Noch keine sitemap.xml – „Sitemap jetzt erzeugen“.');
        $add('Beiträge ohne Beschreibung', $noDesc ? 'warn' : 'ok', $noDesc ? $noDesc . ' veröffentlichte(r) Beitrag/Beiträge ohne SEO-Beschreibung und ohne Teaser.' : 'Alle veröffentlichten Beiträge haben eine Beschreibung oder einen Teaser.');
        return ['stats' => [['label' => 'Veröffentlichte Beiträge', 'value' => count($news)], ['label' => 'Ohne Beschreibung', 'value' => $noDesc, 'level' => $noDesc ? 'warn' : 'ok'], ['label' => 'noindex', 'value' => $noIndex]], 'checks' => $checks];
    }
}

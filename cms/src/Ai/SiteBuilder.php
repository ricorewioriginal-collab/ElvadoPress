<?php
declare(strict_types=1);
// cms/src/Ai/SiteBuilder.php
//
// KI-Website-Generator: aus einer freien Beschreibung („Schreinerei Weller: Handwerk, Qualität …“) entsteht ein Entwurf aus Website-Titel, Farbwelt,
// Startseite (Abschnitte des Homepage-Baukastens), Seiten und Beiträgen. Die KI liefert nur JSON; alles, was von ihr kommt, wird hier streng geprüft und auf
// die Datenformate des CMS abgebildet (keine fremden Adressen für Bilder, keine Skripte, Farben mit ausreichendem Kontrast, eindeutige Adressen).
// Der Entwurf wird NICHT gespeichert – das Übernehmen erledigt die Verwaltungsoberfläche mit den vorhandenen Speicher-Aktionen.

namespace Elvado\Ai;

final class SiteBuilder
{
    public const TONES = ['modern' => 'modern und klar', 'klassisch' => 'klassisch und seriös', 'freundlich' => 'freundlich und nahbar', 'minimal' => 'minimalistisch und knapp', 'kreativ' => 'kreativ und verspielt'];
    public const BG = ['default', 'alt', 'accent', 'dark'];
    private const DEFAULT_PALETTE = ['accent' => '#2563eb', 'bg' => '#f8fafc', 'card' => '#ffffff', 'text' => '#0f172a', 'hero_bg' => '#0f172a', 'hero_text' => '#ffffff'];

    public function __construct(private readonly AiGatewayService $ai)
    {
    }

    /**
     * Entwurf erzeugen.
     * @param array{description:string,name?:string,tone?:string,language?:string,parts?:array<string,bool>,existing_slugs?:list<string>,user?:string,provider?:string} $in
     * @return array<string,mixed> Entwurf (siehe normalize())
     * @throws AiGatewayException
     */
    public function plan(array $in): array
    {
        $desc = trim((string)($in['description'] ?? ''));
        if (mb_strlen($desc) < 12) {
            throw new AiGatewayException('Bitte beschreibe dein Vorhaben etwas genauer (mindestens ein bis zwei Sätze).', 400);
        }
        $parts = array_merge(['home' => true, 'pages' => true, 'posts' => true], (array)($in['parts'] ?? []));
        $tone = self::TONES[(string)($in['tone'] ?? '')] ?? self::TONES['modern'];
        $lang = preg_match('/^[A-Za-zÄÖÜäöüß -]{2,30}$/u', (string)($in['language'] ?? '')) ? (string)$in['language'] : 'Deutsch';
        $name = trim(strip_tags((string)($in['name'] ?? '')));
        $r = $this->ai->generate([
            'provider' => (string)($in['provider'] ?? ''), 'purpose' => 'builder', 'task' => 'site', 'internal' => true, 'json' => true,
            'system' => $this->system($lang, $tone, $parts),
            'prompt' => "Beschreibung des Vorhabens:\n" . mb_substr($desc, 0, 3000) . ($name !== '' ? "\n\nName der Website/Firma: " . mb_substr($name, 0, 80) : ''),
            'temperature' => 0.7, 'max_tokens' => 6000, 'user' => (string)($in['user'] ?? ''),
        ]);
        $plan = self::normalize($r->data, $name, (array)($in['existing_slugs'] ?? []), $parts);
        $plan['meta'] = ['provider' => $r->provider, 'model' => $r->model, 'latency_ms' => $r->latencyMs, 'tone' => (string)($in['tone'] ?? 'modern')];
        return $plan;
    }

    /** @param array<string,bool> $parts */
    private function system(string $lang, string $tone, array $parts): string
    {
        $types = '';
        foreach (['hero' => 'title, text, btn_label, btn_url, image_query', 'text' => 'title, body, align(left|center), bg', 'features' => 'title, items[{title,text}] (3 bis 6), columns(3|4), bg',
            'image_text' => 'title, text, reverse(bool), btn_label, btn_url, image_query, bg', 'posts' => 'title, count(3-6), all_label, bg', 'cta' => 'title, text, btn_label, btn_url, bg'] as $t => $f) {
            $types .= "- $t: $f\n";
        }
        return "Du bist Webdesigner und Texter und entwirfst komplette kleine Websites für ein Website-CMS. Schreibe alle Texte in $lang, im Ton: $tone. Erfinde keine konkreten Fakten " .
            "(keine Preise, Adressen, Telefonnummern, Jahreszahlen, Zertifikate, Kundennamen), schreibe stattdessen allgemeingültig und lade zum Kontakt ein. Keine Bild-Adressen, kein HTML, kein Markdown.\n" .
            "Antworte AUSSCHLIESSLICH mit einem JSON-Objekt dieser Form:\n" .
            '{"site":{"title":"kurzer Name","tagline":"Slogan bis 90 Zeichen"},' .
            '"palette":{"accent":"#rrggbb","bg":"#rrggbb","card":"#rrggbb","text":"#rrggbb","hero_bg":"#rrggbb","hero_text":"#rrggbb"},' .
            '"home":[{"type":"hero","props":{…}},…],' .
            '"pages":[{"title":"Über uns","blocks":[{"type":"heading","text":"…","level":2},{"type":"text","text":"Absatz"},{"type":"list","items":["…","…"]},{"type":"quote","text":"…"},{"type":"button","label":"Kontakt","url":"#"}]}],' .
            '"posts":[{"title":"…","excerpt":"1–2 Sätze","image_query":"…","paragraphs":["Absatz 1","Absatz 2","Absatz 3"]}]}' . "\n" .
            "image_query: 2 bis 4 englische Suchbegriffe für ein passendes freies Foto (z. B. \"wood workshop carpenter\"); keine Namen von Personen, Marken oder Orten.\n" .
            "Die Palette passt zur Branche und Stimmung; Text auf bg und card sowie hero_text auf hero_bg müssen gut lesbar sein (hoher Kontrast).\n" .
            "Erlaubte Abschnittstypen für home und ihre Felder:\n$types" .
            "Die Startseite beginnt mit hero und enthält 5 bis 8 Abschnitte (z. B. features, text, image_text, posts, cta). Verlinke Buttons nur mit \"#\" oder \"/seitenname.html\" zu deinen eigenen Seiten.\n" .
            ($parts['home'] ? '' : "Liefere für \"home\" ein leeres Array.\n") .
            ($parts['pages'] ? "Erzeuge 3 bis 5 sinnvolle Seiten (z. B. Über uns, Leistungen/Angebot, Kontakt); keine Impressum- und Datenschutzseiten.\n" : "Liefere für \"pages\" ein leeres Array.\n") .
            ($parts['posts'] ? "Erzeuge 2 bis 3 kurze Beiträge (Begrüßung, Wissenswertes) mit je 3 Absätzen.\n" : "Liefere für \"posts\" ein leeres Array.\n");
    }

    // ------------------------------------------------------------------ Prüfung und Abbildung auf CMS-Formate

    /**
     * @param mixed $raw Antwort der KI
     * @param list<string> $existingSlugs bereits vergebene Seitenadressen
     * @param array<string,bool> $parts
     * @return array<string,mixed>
     */
    public static function normalize(mixed $raw, string $name = '', array $existingSlugs = [], array $parts = []): array
    {
        $parts = array_merge(['home' => true, 'pages' => true, 'posts' => true], $parts);
        $raw = is_array($raw) ? $raw : [];
        $title = self::str($raw['site']['title'] ?? '', 60) ?: self::str($name, 60) ?: 'Meine Website';
        $tagline = self::str($raw['site']['tagline'] ?? '', 120);
        $palette = self::palette(is_array($raw['palette'] ?? null) ? $raw['palette'] : []);

        $taken = array_fill_keys(array_map('strtolower', $existingSlugs), true);
        foreach (['start', 'index', 'news', 'cms', 'wp-admin', 'wp-login', 'feed', 'search', 'impressum', 'datenschutz'] as $r) {
            $taken[$r] = true;
        }
        $pages = [];
        if ($parts['pages']) {
            foreach (array_slice(is_array($raw['pages'] ?? null) ? $raw['pages'] : [], 0, 6) as $p) {
                if (!is_array($p)) {
                    continue;
                }
                $pt = self::str($p['title'] ?? '', 60);
                $blocks = self::pageBlocks($p['blocks'] ?? []);
                if ($pt === '' || $blocks === []) {
                    continue;
                }
                $slug = self::slug($pt);
                $base = $slug;
                for ($n = 2; isset($taken[$slug]); $n++) {
                    $slug = $base . '-' . $n;
                }
                $taken[$slug] = true;
                $pages[] = ['title' => $pt, 'slug' => $slug, 'blocks' => $blocks];
            }
        }
        $home = $parts['home'] ? self::home($raw['home'] ?? [], $title, $tagline, array_merge(array_column($pages, 'slug'), array_map('strtolower', $existingSlugs))) : [];
        $posts = [];
        if ($parts['posts']) {
            foreach (array_slice(is_array($raw['posts'] ?? null) ? $raw['posts'] : [], 0, 4) as $p) {
                if (!is_array($p)) {
                    continue;
                }
                $pt = self::str($p['title'] ?? '', 120);
                $paras = [];
                foreach (array_slice(is_array($p['paragraphs'] ?? null) ? $p['paragraphs'] : (is_string($p['body'] ?? null) ? preg_split('/\n{2,}/', $p['body']) : []), 0, 8) as $t) {
                    $t = self::str($t, 1500);
                    if ($t !== '') {
                        $paras[] = $t;
                    }
                }
                if ($pt === '' || !$paras) {
                    continue;
                }
                $posts[] = ['title' => $pt, 'excerpt' => self::str($p['excerpt'] ?? '', 240) ?: mb_substr($paras[0], 0, 200), 'image_query' => self::query($p['image_query'] ?? ''),
                    'body_html' => implode("\n", array_map(static fn(string $t): string => '<p>' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '</p>', $paras))];
            }
        }
        if (!$home && !$pages && !$posts) {
            throw new AiGatewayException('Die KI hat keinen brauchbaren Entwurf geliefert. Bitte formuliere die Beschreibung etwas ausführlicher oder versuche es erneut.', 502);
        }
        $menu = [];
        foreach ($pages as $pg) {
            $menu[] = ['label' => mb_substr($pg['title'], 0, 40), 'slug' => $pg['slug']];
        }
        return ['site' => ['title' => $title, 'tagline' => $tagline], 'palette' => $palette, 'home' => $home, 'pages' => $pages, 'posts' => $posts, 'menu' => $menu];
    }

    private static function str(mixed $v, int $max): string
    {
        if (!is_string($v) && !is_numeric($v)) {
            return '';
        }
        $s = html_entity_decode(strip_tags((string)$v), ENT_QUOTES, 'UTF-8');
        $s = trim(preg_replace('/[ \t]+/', ' ', preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s) ?? '') ?? '');
        return mb_substr($s, 0, $max);
    }

    public static function slug(string $s): string
    {
        $s = strtr(mb_strtolower($s), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        $s = trim(preg_replace('/[^a-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s) ?? '', '-');
        return mb_substr($s !== '' ? $s : 'seite', 0, 60);
    }

    /** Suchbegriffe für ein Foto: Buchstaben, Ziffern, Leerzeichen, Bindestriche; höchstens 60 Zeichen. */
    public static function query(mixed $v): string
    {
        $q = is_string($v) ? trim(preg_replace('/[^\p{L}\p{N} -]+/u', ' ', strip_tags($v)) ?? '') : '';
        $q = trim(preg_replace('/\s+/u', ' ', $q) ?? '');
        return mb_strlen($q) >= 3 ? mb_substr($q, 0, 60) : '';
    }

    private static function url(mixed $v): string
    {
        $u = is_string($v) ? trim($v) : '';
        return ($u === '' || $u === '#' || preg_match('~^(#[\w-]*|/[\w./%-]*|https://[^\s"\'<>]+|mailto:[^\s"\'<>]+)$~', $u)) ? ($u === '' ? '#' : $u) : '#';
    }

    // ---- Farben

    private static function hex(mixed $v, string $fallback): string
    {
        $v = is_string($v) ? strtolower(trim($v)) : '';
        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $v, $m)) {
            $v = '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
        }
        return preg_match('/^#[0-9a-f]{6}$/', $v) ? $v : $fallback;
    }

    private static function lum(string $hex): float
    {
        $c = array_map(static function (string $h): float {
            $v = hexdec($h) / 255;
            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, [substr($hex, 1, 2), substr($hex, 3, 2), substr($hex, 5, 2)]);
        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    public static function contrast(string $a, string $b): float
    {
        $x = self::lum($a);
        $y = self::lum($b);
        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    }

    /** @param array<string,mixed> $in @return array<string,string> */
    private static function palette(array $in): array
    {
        $p = [];
        foreach (self::DEFAULT_PALETTE as $k => $d) {
            $p[$k] = self::hex($in[$k] ?? '', $d);
        }
        $readable = static function (string $bg, string $fg): string {
            if (self::contrast($bg, $fg) >= 4.5) {
                return $fg;
            }
            return self::contrast($bg, '#0f172a') >= self::contrast($bg, '#ffffff') ? '#0f172a' : '#ffffff';
        };
        $p['text'] = $readable($p['bg'], $p['text']);
        if (self::contrast($p['card'], $p['text']) < 4.5) {
            $p['card'] = $p['bg'];
        }
        $p['hero_text'] = $readable($p['hero_bg'], $p['hero_text']);
        if (self::contrast($p['bg'], $p['accent']) < 2.0) {   // Akzent muss sich von der Fläche abheben
            $p['accent'] = self::lum($p['bg']) > 0.4 ? '#2563eb' : '#60a5fa';
        }
        return $p;
    }

    // ---- Startseite

    /** @param list<string> $pageSlugs @return list<array{type:string,props:array<string,mixed>}> */
    private static function home(mixed $raw, string $title, string $tagline, array $pageSlugs): array
    {
        $out = [];
        $bg = static fn(mixed $v): string => in_array($v, self::BG, true) ? (string)$v : 'default';
        $link = static function (mixed $v) use ($pageSlugs): string {
            $u = self::url($v);
            if (preg_match('~^/([\w-]+)\.html$~', $u, $m) && !in_array($m[1], $pageSlugs, true)) {
                return '#';   // Verweis auf eine Seite, die es nicht gibt
            }
            return $u;
        };
        foreach (array_slice(is_array($raw) ? $raw : [], 0, 10) as $s) {
            $type = is_array($s) ? (string)($s['type'] ?? '') : '';
            $pr = is_array($s['props'] ?? null) ? $s['props'] : [];
            switch ($type) {
                case 'hero':
                    $out[] = ['type' => 'hero', 'props' => ['title' => self::str($pr['title'] ?? '', 120), 'text' => self::str($pr['text'] ?? '', 300), 'image' => '', 'image_query' => self::query($pr['image_query'] ?? ''), 'btn_label' => self::str($pr['btn_label'] ?? '', 40), 'btn_url' => $link($pr['btn_url'] ?? '#')]];
                    break;
                case 'text':
                    $paras = array_filter(array_map(static fn($t) => self::str($t, 1500), preg_split('/\n{1,}/', (string)($pr['body'] ?? '')) ?: []), static fn($t) => $t !== '');
                    if ($paras) {
                        $out[] = ['type' => 'text', 'props' => ['title' => self::str($pr['title'] ?? '', 120), 'body' => implode('', array_map(static fn(string $t): string => '<p>' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '</p>', $paras)),
                            'align' => (($pr['align'] ?? '') === 'center') ? 'center' : 'left', 'bg' => $bg($pr['bg'] ?? '')]];
                    }
                    break;
                case 'features':
                    $items = [];
                    foreach (array_slice(is_array($pr['items'] ?? null) ? $pr['items'] : [], 0, 6) as $it) {
                        if (is_array($it) && self::str($it['title'] ?? '', 80) !== '') {
                            $items[] = ['title' => self::str($it['title'], 80), 'text' => self::str($it['text'] ?? '', 240)];
                        }
                    }
                    if ($items) {
                        $c = (int)($pr['columns'] ?? 3);
                        $out[] = ['type' => 'features', 'props' => ['title' => self::str($pr['title'] ?? '', 120), 'items' => $items, 'columns' => in_array($c, [0, 2, 3, 4], true) ? $c : 3, 'bg' => $bg($pr['bg'] ?? '')]];
                    }
                    break;
                case 'image_text':
                    $out[] = ['type' => 'image_text', 'props' => ['title' => self::str($pr['title'] ?? '', 120), 'text' => self::str($pr['text'] ?? '', 800), 'image' => '', 'image_query' => self::query($pr['image_query'] ?? ''), 'reverse' => !empty($pr['reverse']),
                        'btn_label' => self::str($pr['btn_label'] ?? '', 40), 'btn_url' => $link($pr['btn_url'] ?? '#'), 'bg' => $bg($pr['bg'] ?? '')]];
                    break;
                case 'posts':
                    $out[] = ['type' => 'posts', 'props' => ['title' => self::str($pr['title'] ?? '', 120) ?: 'Neuigkeiten', 'count' => max(1, min(12, (int)($pr['count'] ?? 3))), 'category' => '', 'all_label' => self::str($pr['all_label'] ?? '', 40), 'bg' => $bg($pr['bg'] ?? '')]];
                    break;
                case 'cta':
                    $out[] = ['type' => 'cta', 'props' => ['title' => self::str($pr['title'] ?? '', 120), 'text' => self::str($pr['text'] ?? '', 300), 'btn_label' => self::str($pr['btn_label'] ?? '', 40) ?: 'Kontakt aufnehmen', 'btn_url' => $link($pr['btn_url'] ?? '#'), 'bg' => $bg($pr['bg'] ?? '')]];
                    break;
            }
        }
        if (!$out) {
            return [];
        }
        if ($out[0]['type'] !== 'hero') {
            array_unshift($out, ['type' => 'hero', 'props' => ['title' => $title, 'text' => $tagline, 'image' => '', 'btn_label' => '', 'btn_url' => '#']]);
        }
        return array_slice($out, 0, 10);
    }

    // ---- Seiten

    /** @return list<array<string,mixed>> Blöcke im Format der CMS-Seiten (rrw_clean_blocks) */
    private static function pageBlocks(mixed $raw): array
    {
        $out = [];
        foreach (array_slice(is_array($raw) ? $raw : [], 0, 14) as $b) {
            if (!is_array($b)) {
                continue;
            }
            $id = 'blk_' . bin2hex(random_bytes(4));
            switch ((string)($b['type'] ?? 'text')) {
                case 'heading':
                    $t = self::str($b['text'] ?? '', 160);
                    if ($t !== '') {
                        $out[] = ['id' => $id, 'type' => 'heading', 'enabled' => true, 'text' => $t, 'level' => in_array((int)($b['level'] ?? 2), [2, 3, 4], true) ? (int)$b['level'] : 2];
                    }
                    break;
                case 'text':
                    $t = implode("\n\n", array_filter(array_map(static fn($x) => self::str($x, 3000), preg_split('/\n{2,}/', (string)($b['text'] ?? '')) ?: []), static fn($x) => $x !== ''));
                    if ($t !== '') {
                        $out[] = ['id' => $id, 'type' => 'text', 'enabled' => true, 'text' => $t];
                    }
                    break;
                case 'quote':
                    $t = self::str($b['text'] ?? '', 500);
                    if ($t !== '') {
                        $out[] = ['id' => $id, 'type' => 'quote', 'enabled' => true, 'text' => $t];
                    }
                    break;
                case 'list':
                    $items = array_values(array_filter(array_map(static fn($x) => self::str($x, 200), array_slice(is_array($b['items'] ?? null) ? $b['items'] : [], 0, 12)), static fn($x) => $x !== ''));
                    if ($items) {
                        $out[] = ['id' => $id, 'type' => 'html', 'enabled' => true, 'html' => '<ul>' . implode('', array_map(static fn(string $x): string => '<li>' . htmlspecialchars($x, ENT_QUOTES, 'UTF-8') . '</li>', $items)) . '</ul>'];
                    }
                    break;
                case 'button':
                    $l = self::str($b['label'] ?? '', 60);
                    if ($l !== '') {
                        $out[] = ['id' => $id, 'type' => 'button', 'enabled' => true, 'label' => $l, 'url' => self::url($b['url'] ?? '#')];
                    }
                    break;
            }
        }
        return $out;
    }
}

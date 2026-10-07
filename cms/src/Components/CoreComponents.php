<?php
declare(strict_types=1);
// cms/src/Components/CoreComponents.php – Neutrale Komponenten des Kerns. Produkt-/Projektspezifisches (z. B. Partner, Social Wall, Sender) gehört in Erweiterungen (Hook „components_register“).
//
// Baukasten-Komponenten (hero, text, features, image_text, posts, cta, html, spacer): Schema hier, Darstellung durch das Theme „ElvadoPress Baukasten“ (renderer „theme“) – das bestehende Frontend bleibt unverändert.
// Neue Komponenten (header, footer, navigation, image, gallery, button, container, columns, audio, video, …): native Darstellung (reines PHP, überall nutzbar) oder – wenn sie WordPress/Module brauchen – „wp“/„runtime“.

namespace Elvado\Components;

final class CoreComponents
{
    private const BG = ['default' => 'Standard', 'alt' => 'Fläche (Karte)', 'accent' => 'Akzentfarbe', 'dark' => 'Dunkel'];

    public static function register(Registry $r): void
    {
        foreach (self::legacy() as $def) {
            $r->register($def, 'core');
        }
        self::native($r);
        self::detectedArea($r);
        foreach (self::hosted() as $def) {
            $r->register($def, 'core');
        }
    }

    private static function bg(string $default = 'default'): array { return ['k' => 'bg', 'label' => 'Hintergrund', 'type' => 'select', 'options' => self::BG, 'default' => $default, 'group' => 'design']; }
    private static function align(): array { return ['k' => 'align', 'label' => 'Ausrichtung', 'type' => 'select', 'options' => ['left' => 'Links', 'center' => 'Zentriert'], 'default' => 'left', 'group' => 'design', 'responsive' => true]; }

    /** @return list<array<string,mixed>> Baukasten-Komponenten (Schema wie bisher im Theme; Darstellung durch das Theme) */
    private static function legacy(): array
    {
        $t = ['renderer' => 'theme', 'rules' => ['sortable' => true, 'repeatable' => true]];
        return [
            $t + ['id' => 'hero', 'name' => 'Hero (Kopfbild)', 'category' => 'structure', 'icon' => 'image', 'description' => 'Großer Einstieg mit Bild, Überschrift und Button.', 'fields' => [
                ['k' => 'title', 'label' => 'Überschrift (leer = Website-Titel)', 'type' => 'text'], ['k' => 'text', 'label' => 'Text (leer = Untertitel)', 'type' => 'textarea'],
                ['k' => 'image', 'label' => 'Hintergrundbild', 'type' => 'image'], ['k' => 'image_alt', 'label' => 'Bildbeschreibung (Alt-Text, leer = dekorativ)', 'type' => 'text'],
                ['k' => 'overlay', 'label' => 'Bild abdunkeln', 'type' => 'checkbox', 'default' => true, 'group' => 'design'],
                ['k' => 'btn_label', 'label' => 'Button-Text', 'type' => 'text', 'default' => 'Mehr erfahren'], ['k' => 'btn_url', 'label' => 'Button-Ziel', 'type' => 'url'],
                ['k' => 'height', 'label' => 'Mindesthöhe (px)', 'type' => 'number', 'min' => 0, 'max' => 900, 'default' => 0, 'group' => 'design', 'responsive' => true, 'css' => ['prop' => 'min-height', 'unit' => 'px', 'skip_zero' => true]]]],
            $t + ['id' => 'text', 'name' => 'Textabschnitt', 'category' => 'content', 'icon' => 'paragraph', 'description' => 'Überschrift und Text.', 'fields' => [
                ['k' => 'title', 'label' => 'Überschrift', 'type' => 'text'], ['k' => 'body', 'label' => 'Inhalt (HTML erlaubt)', 'type' => 'html', 'legacy_type' => 'textarea'], self::align(), self::bg()]],
            $t + ['id' => 'features', 'name' => 'Vorteile / Karten', 'category' => 'content', 'icon' => 'table-cells-large', 'description' => 'Karten mit Titel und Text im Raster.', 'fields' => [
                ['k' => 'title', 'label' => 'Überschrift', 'type' => 'text'],
                ['k' => 'items', 'label' => 'Karten', 'type' => 'items', 'max_items' => 6, 'item' => [['k' => 'title', 'label' => 'Titel', 'type' => 'text', 'max' => 120], ['k' => 'text', 'label' => 'Text', 'type' => 'textarea', 'max' => 600]]],
                ['k' => 'columns', 'label' => 'Spalten', 'type' => 'select', 'options' => ['0' => 'Automatisch', '2' => '2', '3' => '3', '4' => '4'], 'default' => '0', 'group' => 'design', 'responsive' => true, 'css' => ['prop' => 'grid-template-columns', 'tpl' => 'repeat({v},minmax(0,1fr))', 'target' => ' .bk-features']],
                self::bg()]],
            $t + ['id' => 'image_text', 'name' => 'Bild + Text', 'category' => 'content', 'icon' => 'table-columns', 'description' => 'Bild neben Text, optional mit Button.', 'fields' => [
                ['k' => 'title', 'label' => 'Überschrift', 'type' => 'text'], ['k' => 'text', 'label' => 'Inhalt (HTML erlaubt)', 'type' => 'html', 'legacy_type' => 'textarea'],
                ['k' => 'image', 'label' => 'Bild', 'type' => 'image'], ['k' => 'image_alt', 'label' => 'Bildbeschreibung (Alt-Text, leer = dekorativ)', 'type' => 'text'],
                ['k' => 'reverse', 'label' => 'Bild rechts', 'type' => 'checkbox', 'group' => 'design'], ['k' => 'btn_label', 'label' => 'Button-Text', 'type' => 'text'], ['k' => 'btn_url', 'label' => 'Button-Ziel', 'type' => 'url'], self::bg()]],
            $t + ['id' => 'posts', 'name' => 'Neueste Beiträge', 'category' => 'data', 'icon' => 'newspaper', 'description' => 'Aktuelle Beiträge als Karten.', 'data' => ['source' => 'posts'], 'fields' => [
                ['k' => 'title', 'label' => 'Überschrift', 'type' => 'text', 'default' => 'Neueste Beiträge'],
                ['k' => 'count', 'label' => 'Anzahl', 'type' => 'number', 'min' => 1, 'max' => 12, 'default' => 3], ['k' => 'category', 'label' => 'Nur Kategorie (Name/Slug, leer = alle)', 'type' => 'text'],
                ['k' => 'all_label', 'label' => 'Button „alle Beiträge“', 'type' => 'text'], self::bg()]],
            $t + ['id' => 'cta', 'name' => 'Aufruf (Call to Action)', 'category' => 'content', 'icon' => 'bullhorn', 'description' => 'Auffälliger Aufruf mit Button.', 'fields' => [
                ['k' => 'title', 'label' => 'Überschrift', 'type' => 'text'], ['k' => 'text', 'label' => 'Text', 'type' => 'text'], ['k' => 'btn_label', 'label' => 'Button-Text', 'type' => 'text'],
                ['k' => 'btn_url', 'label' => 'Button-Ziel', 'type' => 'url'], self::bg('accent')]],
            $t + ['id' => 'html', 'name' => 'Eigenes HTML / Shortcodes', 'category' => 'advanced', 'icon' => 'code', 'description' => 'HTML oder Shortcodes. Ungefiltert nur für Administratoren.', 'fields' => [
                ['k' => 'code', 'label' => 'HTML oder Shortcodes', 'type' => 'html', 'legacy_type' => 'textarea'], self::bg()]],
            $t + ['id' => 'spacer', 'name' => 'Abstand', 'category' => 'structure', 'icon' => 'arrows-up-down', 'description' => 'Leerer Abstand.', 'fields' => [
                ['k' => 'height', 'label' => 'Höhe (px)', 'type' => 'number', 'min' => 0, 'max' => 400, 'default' => 40, 'group' => 'design', 'responsive' => true, 'css' => ['prop' => 'height', 'unit' => 'px']]]],
        ];
    }

    private static function e(mixed $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

    private static function links(array $items, string $cls): string
    {
        $h = '';
        foreach ($items as $i) {
            $l = (string)($i['label'] ?? '');
            if ($l !== '') {
                $u = (string)($i['url'] ?? '');
                $h .= '<li><a href="' . self::e($u !== '' ? $u : '#') . '">' . self::e($l) . '</a></li>';
            }
        }
        return $h === '' ? '' : '<ul class="' . $cls . '">' . $h . '</ul>';
    }

    private static function linkItems(): array
    {
        return ['k' => 'items', 'label' => 'Links', 'type' => 'items', 'max_items' => 12, 'item' => [['k' => 'label', 'label' => 'Text', 'type' => 'text', 'max' => 80], ['k' => 'url', 'label' => 'Ziel', 'type' => 'url']]];
    }

    /** Generische Komponente für Bereiche, die der Live Builder in der Vorschau erkennt: Gestaltung und Sichtbarkeit über einen Selektor (Kennung, Klasse oder Gliederungs-Element). */
    private static function detectedArea(Registry $r): void
    {
        $r->register(['id' => 'ep_area', 'name' => 'Erkannter Bereich', 'category' => 'advanced', 'icon' => 'object-group', 'bind' => Component::BIND_ANY, 'description' => 'Bereich der Website, den der Live Builder in der Vorschau erkannt hat (nur Gestaltung und Sichtbarkeit).',
            'rules' => ['repeatable' => true, 'use' => 'admin'], 'fields' => [
                ['k' => 'selector', 'label' => 'Bereich (Selektor, z. B. #kopf oder .karte)', 'type' => 'text', 'default' => '', 'group' => 'behavior'],
                ['k' => 'label', 'label' => 'Bezeichnung', 'type' => 'text', 'default' => '', 'group' => 'content'],
                ['k' => 'bg', 'label' => 'Hintergrundfarbe', 'type' => 'color', 'default' => '', 'group' => 'design', 'section' => 'Farben', 'css' => ['prop' => 'background-color']],
                ['k' => 'color', 'label' => 'Textfarbe', 'type' => 'color', 'default' => '', 'group' => 'design', 'section' => 'Farben', 'css' => ['prop' => 'color']],
                ['k' => 'height', 'label' => 'Mindesthöhe (px)', 'type' => 'number', 'min' => 0, 'max' => 1200, 'default' => 0, 'group' => 'design', 'section' => 'Abmessungen', 'responsive' => true, 'css' => ['prop' => 'min-height', 'unit' => 'px', 'skip_zero' => true]],
                ['k' => 'mt', 'label' => 'Abstand oben (px)', 'type' => 'number', 'min' => 0, 'max' => 300, 'default' => 0, 'group' => 'design', 'section' => 'Abstände', 'responsive' => true, 'css' => ['prop' => 'margin-top', 'unit' => 'px', 'skip_zero' => true]],
                ['k' => 'mb', 'label' => 'Abstand unten (px)', 'type' => 'number', 'min' => 0, 'max' => 300, 'default' => 0, 'group' => 'design', 'section' => 'Abstände', 'responsive' => true, 'css' => ['prop' => 'margin-bottom', 'unit' => 'px', 'skip_zero' => true]],
            ]], 'core');
    }

    private static function native(Registry $r): void
    {
        $e = fn($s) => self::e($s);
        $r->register(['id' => 'header', 'name' => 'Header', 'category' => 'structure', 'icon' => 'window-maximize', 'description' => 'Kopfzeile mit Logo und Navigation. Steht immer oben.',
            'rules' => ['slot' => 'first', 'repeatable' => false, 'locked' => true, 'use' => 'admin'], 'data' => ['source' => 'menu'], 'fields' => [
                ['k' => 'logo', 'label' => 'Logo', 'type' => 'image'], ['k' => 'logo_alt', 'label' => 'Logo-Beschreibung', 'type' => 'text'], ['k' => 'title', 'label' => 'Seitenname (ohne Logo)', 'type' => 'text'],
                self::linkItems(), ['k' => 'sticky', 'label' => 'Beim Scrollen fixieren', 'type' => 'checkbox', 'group' => 'behavior'],
                ['k' => 'bg', 'label' => 'Hintergrundfarbe', 'type' => 'color', 'default' => '', 'group' => 'design', 'css' => ['prop' => 'background-color']],
                ['k' => 'height', 'label' => 'Höhe (px)', 'type' => 'number', 'min' => 0, 'max' => 300, 'default' => 0, 'group' => 'design', 'responsive' => true, 'css' => ['prop' => 'min-height', 'unit' => 'px', 'skip_zero' => true]]]],
            'core', fn($p) => '<header class="ep-header' . (!empty($p['sticky']) ? ' is-sticky' : '') . '"><a class="ep-logo" href="/">' . ($p['logo'] !== '' ? '<img src="' . $e($p['logo']) . '" alt="' . $e($p['logo_alt']) . '">' : $e($p['title'])) . '</a>' . ($p['items'] ? '<nav aria-label="Hauptmenü">' . self::links($p['items'], 'ep-menu') . '</nav>' : '') . '</header>');
        $r->register(['id' => 'footer', 'name' => 'Footer', 'category' => 'structure', 'icon' => 'window-minimize', 'description' => 'Fußzeile mit Text und Links. Steht immer unten.',
            'rules' => ['slot' => 'last', 'repeatable' => false, 'locked' => true, 'use' => 'admin'], 'fields' => [
                ['k' => 'text', 'label' => 'Text (HTML erlaubt)', 'type' => 'html', 'max' => 4000], self::linkItems(),
                ['k' => 'bg', 'label' => 'Hintergrundfarbe', 'type' => 'color', 'default' => '', 'group' => 'design', 'css' => ['prop' => 'background-color']]]],
            'core', fn($p) => '<footer class="ep-footer">' . ($p['text'] !== '' ? '<div class="ep-footer-text">' . $p['text'] . '</div>' : '') . self::links($p['items'], 'ep-menu') . '</footer>');
        $r->register(['id' => 'navigation', 'name' => 'Navigation', 'category' => 'navigation', 'icon' => 'bars', 'description' => 'Menü aus eigenen Links.', 'data' => ['source' => 'menu'], 'fields' => [
            self::linkItems(), ['k' => 'align', 'label' => 'Ausrichtung', 'type' => 'select', 'options' => ['left' => 'Links', 'center' => 'Zentriert', 'right' => 'Rechts'], 'default' => 'left', 'group' => 'design', 'responsive' => true, 'css' => ['prop' => 'text-align']]]],
            'core', fn($p) => $p['items'] ? '<nav class="ep-nav" aria-label="Navigation">' . self::links($p['items'], 'ep-menu') . '</nav>' : '');
        $r->register(['id' => 'image', 'name' => 'Bild', 'category' => 'media', 'icon' => 'image', 'description' => 'Einzelnes Bild mit Beschreibung und optionalem Link.', 'fields' => [
            ['k' => 'image', 'label' => 'Bild', 'type' => 'image'], ['k' => 'alt', 'label' => 'Bildbeschreibung (Alt-Text)', 'type' => 'text'], ['k' => 'caption', 'label' => 'Bildunterschrift', 'type' => 'text'], ['k' => 'link', 'label' => 'Link', 'type' => 'url'],
            ['k' => 'width', 'label' => 'Breite (%)', 'type' => 'number', 'min' => 5, 'max' => 100, 'default' => 100, 'group' => 'design', 'responsive' => true, 'css' => ['prop' => 'max-width', 'unit' => '%']]]],
            'core', function ($p) use ($e) {
                if ($p['image'] === '') {
                    return '';
                }
                $img = '<img src="' . $e($p['image']) . '" alt="' . $e($p['alt']) . '" loading="lazy">';
                $img = $p['link'] !== '' ? '<a href="' . $e($p['link']) . '">' . $img . '</a>' : $img;
                return '<figure class="ep-image">' . $img . ($p['caption'] !== '' ? '<figcaption>' . $e($p['caption']) . '</figcaption>' : '') . '</figure>';
            });
        $r->register(['id' => 'gallery', 'name' => 'Galerie', 'category' => 'media', 'icon' => 'images', 'description' => 'Bilderraster.', 'fields' => [
            ['k' => 'items', 'label' => 'Bilder', 'type' => 'items', 'max_items' => 24, 'item' => [['k' => 'image', 'label' => 'Bild', 'type' => 'image'], ['k' => 'alt', 'label' => 'Beschreibung', 'type' => 'text', 'max' => 160]]],
            ['k' => 'columns', 'label' => 'Spalten', 'type' => 'number', 'min' => 1, 'max' => 6, 'default' => 3, 'group' => 'design', 'responsive' => true, 'css' => ['prop' => 'grid-template-columns', 'tpl' => 'repeat({v},minmax(0,1fr))', 'target' => ' .ep-gallery']]]],
            'core', function ($p) use ($e) {
                $h = '';
                foreach ($p['items'] as $i) {
                    $h .= $i['image'] !== '' ? '<figure><img src="' . $e($i['image']) . '" alt="' . $e($i['alt']) . '" loading="lazy"></figure>' : '';
                }
                return $h === '' ? '' : '<div class="ep-gallery" style="display:grid;gap:8px">' . $h . '</div>';
            });
        $r->register(['id' => 'button', 'name' => 'Button', 'category' => 'content', 'icon' => 'hand-pointer', 'description' => 'Einzelner Button.', 'fields' => [
            ['k' => 'label', 'label' => 'Text', 'type' => 'text', 'default' => 'Mehr erfahren'], ['k' => 'url', 'label' => 'Ziel', 'type' => 'url'],
            ['k' => 'style', 'label' => 'Stil', 'type' => 'select', 'options' => ['primary' => 'Hauptfarbe', 'secondary' => 'Zweitfarbe', 'outline' => 'Umrandet'], 'default' => 'primary', 'group' => 'design'],
            ['k' => 'align', 'label' => 'Ausrichtung', 'type' => 'select', 'options' => ['left' => 'Links', 'center' => 'Zentriert', 'right' => 'Rechts'], 'default' => 'left', 'group' => 'design', 'responsive' => true, 'css' => ['prop' => 'text-align']]]],
            'core', fn($p) => $p['label'] === '' ? '' : '<a class="ep-btn ep-btn-' . $e($p['style']) . '" href="' . $e($p['url'] !== '' ? $p['url'] : '#') . '">' . $e($p['label']) . '</a>');
        $r->register(['id' => 'container', 'name' => 'Container', 'category' => 'structure', 'icon' => 'square', 'description' => 'Gruppiert andere Komponenten mit Hintergrund und Abstand.',
            'rules' => ['droppable' => true], 'fields' => [self::bg(),
                ['k' => 'padding', 'label' => 'Innenabstand (px)', 'type' => 'number', 'min' => 0, 'max' => 200, 'default' => 24, 'group' => 'design', 'responsive' => true, 'css' => ['prop' => 'padding', 'unit' => 'px']],
                ['k' => 'max_width', 'label' => 'Maximale Breite (px, 0 = voll)', 'type' => 'number', 'min' => 0, 'max' => 2000, 'default' => 0, 'group' => 'design', 'responsive' => true, 'css' => ['prop' => 'max-width', 'unit' => 'px', 'skip_zero' => true]]]],
            'core', fn($p, $inst) => '<div class="ep-container ep-bg-' . $e($p['bg']) . '">' . $inst['children_html'] . '</div>');
        $r->register(['id' => 'columns', 'name' => 'Spalten', 'category' => 'structure', 'icon' => 'table-columns', 'description' => 'Ordnet Komponenten nebeneinander an (auf dem Handy untereinander).',
            'rules' => ['droppable' => true, 'accepts' => ['category:content', 'category:media', 'category:data', 'category:widgets', 'container']], 'fields' => [
                ['k' => 'columns', 'label' => 'Spalten', 'type' => 'number', 'min' => 1, 'max' => 4, 'default' => 2, 'group' => 'design', 'responsive' => true, 'css' => ['prop' => 'grid-template-columns', 'tpl' => 'repeat({v},minmax(0,1fr))', 'target' => ' .ep-columns']],
                ['k' => 'gap', 'label' => 'Abstand (px)', 'type' => 'number', 'min' => 0, 'max' => 80, 'default' => 24, 'group' => 'design', 'responsive' => true, 'css' => ['prop' => 'gap', 'unit' => 'px', 'target' => ' .ep-columns']]]],
            'core', fn($p, $inst) => '<div class="ep-columns" style="display:grid">' . $inst['children_html'] . '</div>');
        $r->register(['id' => 'audio', 'name' => 'Audio', 'category' => 'media', 'icon' => 'volume-high', 'description' => 'Audio-Datei mit Player.', 'fields' => [
            ['k' => 'src', 'label' => 'Audio-Adresse (https://… oder /…)', 'type' => 'image'], ['k' => 'title', 'label' => 'Titel', 'type' => 'text']]],
            'core', fn($p) => $p['src'] === '' ? '' : '<figure class="ep-audio">' . ($p['title'] !== '' ? '<figcaption>' . $e($p['title']) . '</figcaption>' : '') . '<audio controls preload="none" src="' . $e($p['src']) . '"></audio></figure>');
        $r->register(['id' => 'video', 'name' => 'Video', 'category' => 'media', 'icon' => 'video', 'description' => 'Video-Datei oder YouTube/Vimeo-Link.', 'fields' => [
            ['k' => 'src', 'label' => 'Video-Adresse (Datei, YouTube oder Vimeo)', 'type' => 'url'], ['k' => 'poster', 'label' => 'Vorschaubild', 'type' => 'image'], ['k' => 'title', 'label' => 'Titel', 'type' => 'text']]],
            'core', function ($p) use ($e) {
                $u = (string)$p['src'];
                if ($u === '') {
                    return '';
                }
                $t = $e($p['title'] !== '' ? $p['title'] : 'Video');
                if (preg_match('~^https://(?:www\.)?(?:youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_-]{11})~', $u, $m)) {
                    return '<div class="ep-video ep-embed"><iframe src="https://www.youtube-nocookie.com/embed/' . $m[1] . '" title="' . $t . '" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>';
                }
                if (preg_match('~^https://(?:www\.)?vimeo\.com/(\d{5,12})~', $u, $m)) {
                    return '<div class="ep-video ep-embed"><iframe src="https://player.vimeo.com/video/' . $m[1] . '" title="' . $t . '" loading="lazy" allowfullscreen></iframe></div>';
                }
                return preg_match('~\.(mp4|webm|ogv)(\?.*)?$~i', $u) === 1 ? '<video class="ep-video" controls preload="none" src="' . $e($u) . '"' . ($p['poster'] !== '' ? ' poster="' . $e($p['poster']) . '"' : '') . '></video>' : '';
            });
    }

    /** @return list<array<string,mixed>> Komponenten, die WordPress oder ElvadoPress-Module zum Ausgeben brauchen */
    private static function hosted(): array
    {
        return [
            ['id' => 'form', 'name' => 'Formular', 'category' => 'content', 'icon' => 'envelope', 'description' => 'Formular aus der Formularverwaltung.', 'renderer' => 'runtime', 'data' => ['source' => 'forms'],
                'fields' => [['k' => 'form_id', 'label' => 'Formular (Kennung)', 'type' => 'text', 'max' => 80]]],
            ['id' => 'newsletter', 'name' => 'Newsletter', 'category' => 'content', 'icon' => 'paper-plane', 'description' => 'Anmeldung zum Newsletter.', 'renderer' => 'runtime', 'fields' => [
                ['k' => 'title', 'label' => 'Überschrift', 'type' => 'text'], ['k' => 'text', 'label' => 'Text', 'type' => 'text'], ['k' => 'button', 'label' => 'Button-Text', 'type' => 'text', 'default' => 'Anmelden']]],
            ['id' => 'widget_area', 'name' => 'Widget-Bereich', 'category' => 'widgets', 'icon' => 'table-cells', 'description' => 'Zeigt die Widgets eines Bereichs (WordPress).', 'renderer' => 'wp', 'data' => ['source' => 'widgets'], 'fields' => [
                ['k' => 'area', 'label' => 'Bereich (Kennung)', 'type' => 'text', 'default' => 'sidebar-1', 'max' => 60]]],
            ['id' => 'wp_block', 'name' => 'WordPress-Block', 'category' => 'widgets', 'icon' => 'cubes', 'description' => 'Ein WordPress-Block (Block-Markup). Ungefiltert nur für Administratoren.', 'renderer' => 'wp', 'rules' => ['use' => 'admin'], 'fields' => [
                ['k' => 'markup', 'label' => 'Block-Markup', 'type' => 'html', 'max' => 20000]]],
            ['id' => 'wp_shortcode', 'name' => 'Shortcode', 'category' => 'widgets', 'icon' => 'code', 'description' => 'Ein WordPress-Shortcode, z. B. [name attribut="…"].', 'renderer' => 'wp', 'rules' => ['use' => 'admin'], 'fields' => [
                ['k' => 'shortcode', 'label' => 'Shortcode', 'type' => 'text', 'max' => 400]]],
            ['id' => 'plugin_widget', 'name' => 'Plugin-Widget', 'category' => 'widgets', 'icon' => 'plug', 'description' => 'Ein Widget eines Plugins (Widget-Kennung).', 'renderer' => 'wp', 'rules' => ['use' => 'admin'], 'fields' => [
                ['k' => 'widget', 'label' => 'Widget (Kennung)', 'type' => 'text', 'max' => 80], ['k' => 'title', 'label' => 'Titel', 'type' => 'text']]],
        ];
    }

    /** Radio-Erweiterung (neutral, ohne Markeninhalte): nur sichtbar, wenn das Merkmal „radio“ aktiv ist. */
    public static function registerRadio(Registry $r): void
    {
        $e = fn($s) => self::e($s);
        $r->register(['id' => 'radio_player', 'name' => 'Radio-Player', 'category' => 'radio', 'icon' => 'tower-broadcast', 'feature' => 'radio', 'description' => 'Livestream mit Player.', 'fields' => [
            ['k' => 'stream', 'label' => 'Stream-Adresse (https://…)', 'type' => 'image'], ['k' => 'title', 'label' => 'Sendername', 'type' => 'text'], ['k' => 'cover', 'label' => 'Logo/Cover', 'type' => 'image']]],
            'radio', fn($p) => $p['stream'] === '' ? '' : '<div class="ep-radio">' . ($p['cover'] !== '' ? '<img src="' . $e($p['cover']) . '" alt="">' : '') . '<div><b>' . $e($p['title']) . '</b><audio controls preload="none" src="' . $e($p['stream']) . '"></audio></div></div>');
        $r->register(['id' => 'podcast', 'name' => 'Podcast', 'category' => 'radio', 'icon' => 'podcast', 'feature' => 'radio', 'description' => 'Liste von Episoden mit Player.', 'fields' => [
            ['k' => 'title', 'label' => 'Überschrift', 'type' => 'text'], ['k' => 'items', 'label' => 'Episoden', 'type' => 'items', 'max_items' => 20, 'item' => [['k' => 'title', 'label' => 'Titel', 'type' => 'text', 'max' => 160], ['k' => 'src', 'label' => 'Audio-Adresse', 'type' => 'image']]]]],
            'radio', function ($p) use ($e) {
                $h = '';
                foreach ($p['items'] as $i) {
                    $h .= $i['src'] !== '' ? '<li><span>' . $e($i['title']) . '</span><audio controls preload="none" src="' . $e($i['src']) . '"></audio></li>' : '';
                }
                return $h === '' ? '' : ($p['title'] !== '' ? '<h3>' . $e($p['title']) . '</h3>' : '') . '<ul class="ep-podcast">' . $h . '</ul>';
            });
    }
}

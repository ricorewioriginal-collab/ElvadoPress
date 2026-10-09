<?php
declare(strict_types=1);
// KI-Werkzeuge für Inhalte und Apps. Nutzt ausschließlich den vorhandenen KI-Gateway des Core (Anbieter, Schlüssel, Modelle, Limits aus der KI-Zentrale).

namespace ElvadoPlugin\Ai;

use Elvado\Ai\AiGatewayConfig;
use Elvado\Ai\AiGatewayException;
use Elvado\Ai\AiGatewayService;
use Elvado\Plugin\Context;

final class Ai
{
    public const TEXT_ACTIONS = ['improve' => 'Verbessern', 'shorten' => 'Kürzen', 'expand' => 'Ausbauen', 'summarize' => 'Zusammenfassen', 'translate' => 'Übersetzen', 'titles' => 'Titel-Ideen', 'proofread' => 'Rechtschreibung korrigieren'];
    public const TAB_ICONS = ['home', 'news', 'info', 'shop', 'calendar', 'phone', 'map', 'mail', 'user', 'star', 'play', 'menu'];

    public function __construct(private readonly Context $np, private readonly ?AiGatewayService $inject = null) {}

    private function dataDir(): string { return $this->np->cmsDir() . '/data'; }

    private function service(): AiGatewayService
    {
        if ($this->inject !== null) {
            return $this->inject;
        }
        $site = (array)($GLOBALS['ELVADO_SITE'] ?? json_decode((string)@file_get_contents($this->dataDir() . '/site.json'), true));   // Konfiguration bei jedem Aufruf frisch lesen (Änderungen in der KI-Zentrale gelten sofort)
        return new AiGatewayService(AiGatewayConfig::load($this->dataDir(), $site), null, new \Elvado\Support\RateLimiter($this->dataDir() . '/.ai/ratelimit'));
    }

    /** Welche Anbieter sind nutzbar? (aus der KI-Zentrale) */
    public function status(): array
    {
        try {
            $u = $this->service()->usableProviders();
        } catch (\Throwable) {
            $u = [];
        }
        return ['usable' => (bool)$u, 'providers' => array_map(static fn($p) => ['id' => $p['id'], 'label' => $p['label'], 'model' => $p['model'], 'free' => !empty($p['free'])], $u)];
    }

    private function lang(): string { return $this->np->setting('language') === 'en' ? 'Englisch' : 'Deutsch'; }

    private function tone(): string
    {
        return match ((string)$this->np->setting('tone')) { 'locker' => 'locker und persönlich', 'formell' => 'formell und professionell', default => 'sachlich und freundlich' };
    }

    /** @return array{ok:bool,message?:string,text?:string,data?:mixed,provider?:string,model?:string} */
    private function run(string $purpose, string $task, string $prompt, string $text, string $user, int $max = 900): array
    {
        try {
            $r = $this->service()->generate(['purpose' => $purpose, 'task' => $task, 'prompt' => $prompt, 'text' => $text, 'language' => $this->np->setting('language') === 'en' ? 'en' : 'de', 'max_tokens' => $max, 'user' => $user]);
        } catch (AiGatewayException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'KI-Anfrage fehlgeschlagen: ' . mb_substr($e->getMessage(), 0, 160)];
        }
        return ['ok' => true, 'text' => $r->text, 'data' => $r->data, 'provider' => $r->provider, 'model' => $r->model];
    }

    // ---------------------------------------------------------------- Texte

    /** Textwerkzeug im Editor. */
    public function text(string $action, string $text, string $extra, string $user): array
    {
        if (!isset(self::TEXT_ACTIONS[$action])) {
            return ['ok' => false, 'message' => 'Unbekannte Aktion.'];
        }
        $text = trim($text);
        if ($text === '' && $action !== 'titles') {
            return ['ok' => false, 'message' => 'Bitte zuerst einen Text eingeben oder markieren.'];
        }
        $max = (int)min(12000, mb_strlen($text) * 2 + 400);
        $base = 'Sprache der Antwort: ' . $this->lang() . '. Tonfall: ' . $this->tone() . '. Gib nur das Ergebnis aus, ohne Einleitung, ohne Anführungszeichen drumherum, ohne Markdown-Formatierung. Behalte HTML-Tags, die im Text vorkommen, unverändert bei. Erfinde keine Fakten, Zahlen oder Zitate.';
        $prompt = match ($action) {
            'improve' => 'Überarbeite den folgenden Text: klarer, flüssiger, fehlerfrei – Inhalt und Aussage bleiben gleich. ' . $base,
            'shorten' => 'Kürze den folgenden Text auf etwa die Hälfte, ohne Wichtiges zu verlieren. ' . $base,
            'expand' => 'Baue den folgenden Text aus: etwas ausführlicher und anschaulicher, aber ohne neue Behauptungen zu erfinden. ' . $base,
            'summarize' => 'Fasse den folgenden Text in 2 bis 3 Sätzen als Teaser zusammen (maximal 280 Zeichen). ' . $base,
            'translate' => 'Übersetze den folgenden Text in diese Sprache: ' . ($extra !== '' ? mb_substr(strip_tags($extra), 0, 30) : ($this->np->setting('language') === 'en' ? 'Deutsch' : 'Englisch')) . '. Gib nur die Übersetzung aus. Behalte HTML-Tags unverändert bei.',
            'titles' => 'Schlage fünf passende Überschriften für den folgenden Beitrag vor (jeweils höchstens 70 Zeichen), eine pro Zeile, ohne Nummerierung. ' . $base,
            'proofread' => 'Korrigiere nur Rechtschreibung, Grammatik und Zeichensetzung des folgenden Textes. Ändere sonst nichts. ' . $base,
        };
        $r = $this->run('content', $action === 'translate' ? 'translate' : ($action === 'summarize' ? 'summarize' : 'rewrite'), $prompt, mb_substr($text, 0, 20000), $user, $max);
        if (!$r['ok']) {
            return $r;
        }
        return ['ok' => true, 'text' => trim($r['text']), 'provider' => $r['provider'], 'model' => $r['model']];
    }

    /** SEO-Titel (≤ 60) und Beschreibung (≤ 155) für einen Beitrag oder eine Seite. */
    public function seo(string $title, string $text, string $user): array
    {
        $r = $this->run('content', 'json', 'Erstelle SEO-Angaben für die folgende Seite. Sprache: ' . $this->lang() . '. Antworte ausschließlich mit JSON der Form {"title":"…","description":"…"}. title: höchstens 60 Zeichen, prägnant, mit dem wichtigsten Begriff vorn. description: 120 bis 155 Zeichen, informativ, zum Klicken einladend, kein Werbe-Superlativ. Erfinde nichts, was nicht im Text steht.', 'Titel: ' . mb_substr($title, 0, 200) . "\n\nText:\n" . mb_substr(strip_tags($text), 0, 6000), $user, 400);
        if (!$r['ok']) {
            return $r;
        }
        $d = is_array($r['data']) ? $r['data'] : (AiGatewayService::extractJson($r['text']) ?? []);
        $t = trim(strip_tags((string)($d['title'] ?? '')));
        $desc = trim(strip_tags((string)($d['description'] ?? '')));
        if ($t === '' && $desc === '') {
            return ['ok' => false, 'message' => 'Die KI hat keine verwertbaren Vorschläge geliefert. Bitte erneut versuchen.'];
        }
        return ['ok' => true, 'title' => mb_substr($t, 0, 70), 'description' => mb_substr($desc, 0, 200), 'provider' => $r['provider'], 'model' => $r['model']];
    }

    // ---------------------------------------------------------------- Apps

    /**
     * Vorschlag für die Tab-Leiste einer Baukasten-App.
     * @param list<array{title:string,path:string}> $pages vorhandene Seiten der Website (nur daraus darf gewählt werden)
     */
    public function appTabs(string $appName, string $brief, array $pages, string $user): array
    {
        $clean = [];
        foreach (array_slice($pages, 0, 60) as $p) {
            if (is_array($p) && isset($p['path'], $p['title']) && preg_match('~^/[^\s"\'<>]{0,200}$~', (string)$p['path'])) {
                $clean[(string)$p['path']] = mb_substr(trim(strip_tags((string)$p['title'])), 0, 80);
            }
        }
        if (!$clean) {
            return ['ok' => false, 'message' => 'Es gibt noch keine Seiten, aus denen Tabs gewählt werden könnten.'];
        }
        $list = '';
        foreach ($clean as $path => $title) {
            $list .= $path . ' | ' . $title . "\n";
        }
        $r = $this->run('builder', 'json', 'Du planst die untere Tab-Leiste einer Smartphone-App für diese Website. Wähle 3 bis 5 Tabs. Verwende AUSSCHLIESSLICH Adressen aus der folgenden Liste (Format „Adresse | Titel“). Tab-Titel höchstens 14 Zeichen, in ' . $this->lang() . '. Symbol je Tab aus: ' . implode(', ', self::TAB_ICONS) . '. Der erste Tab ist meist die Startseite (/). Antworte nur mit JSON: {"tabs":[{"title":"…","icon":"…","url":"/…"}]}.',
            'App: ' . mb_substr($appName, 0, 60) . "\nZweck/Thema: " . mb_substr($brief, 0, 500) . "\n\nVerfügbare Seiten:\n" . $list, $user, 600);
        if (!$r['ok']) {
            return $r;
        }
        $d = is_array($r['data']) ? $r['data'] : (AiGatewayService::extractJson($r['text']) ?? []);
        $tabs = [];
        foreach ((array)($d['tabs'] ?? []) as $t) {
            if (!is_array($t) || count($tabs) >= 5) {
                continue;
            }
            $url = (string)($t['url'] ?? '');
            if (!isset($clean[$url])) {
                continue;   // nur echte Seiten der Website, nichts Erfundenes
            }
            $icon = in_array((string)($t['icon'] ?? ''), self::TAB_ICONS, true) ? (string)$t['icon'] : 'star';
            $title = mb_substr(trim(strip_tags((string)($t['title'] ?? ''))), 0, 16) ?: mb_substr($clean[$url], 0, 16);
            $tabs[] = ['title' => $title, 'icon' => $icon, 'url' => $url];
        }
        if (count($tabs) < 2) {
            return ['ok' => false, 'message' => 'Die KI hat keine brauchbare Tab-Leiste vorgeschlagen. Bitte die Beschreibung genauer fassen und erneut versuchen.'];
        }
        return ['ok' => true, 'tabs' => $tabs, 'provider' => $r['provider'], 'model' => $r['model']];
    }

    /** Hinweistext (Titel ≤ 80, Text ≤ 600) für Nutzer der App. */
    public function appNotice(string $appName, string $brief, string $user): array
    {
        $r = $this->run('builder', 'json', 'Schreibe einen kurzen Hinweis, der den Nutzern einer App beim Öffnen angezeigt wird. Sprache: ' . $this->lang() . ', Tonfall: ' . $this->tone() . '. Antworte nur mit JSON {"title":"…","text":"…"}; title höchstens 60 Zeichen, text höchstens 300 Zeichen. Erfinde keine Termine, Preise oder Zusagen, die nicht in der Beschreibung stehen.', 'App: ' . mb_substr($appName, 0, 60) . "\nWorum geht es: " . mb_substr($brief, 0, 600), $user, 300);
        if (!$r['ok']) {
            return $r;
        }
        $d = is_array($r['data']) ? $r['data'] : (AiGatewayService::extractJson($r['text']) ?? []);
        $t = mb_substr(trim(strip_tags((string)($d['title'] ?? ''))), 0, 80);
        $x = mb_substr(trim(strip_tags((string)($d['text'] ?? ''))), 0, 600);
        return $t === '' && $x === '' ? ['ok' => false, 'message' => 'Kein verwertbarer Vorschlag. Bitte erneut versuchen.'] : ['ok' => true, 'title' => $t, 'text' => $x, 'provider' => $r['provider'], 'model' => $r['model']];
    }

    /** Store-Texte: Kurzbeschreibung (≤ 80) und Langbeschreibung (≤ 4000). */
    public function appStoreTexts(string $appName, string $brief, string $user): array
    {
        $r = $this->run('builder', 'json', 'Schreibe Store-Texte für eine App (Google Play / Microsoft Store). Sprache: ' . $this->lang() . ', Tonfall: ' . $this->tone() . '. Antworte nur mit JSON {"short":"…","long":"…"}. short: höchstens 80 Zeichen. long: 600 bis 1500 Zeichen mit kurzen Absätzen und einer Aufzählung der Funktionen (mit „• “). Erfinde keine Funktionen, die nicht in der Beschreibung stehen.', 'App: ' . mb_substr($appName, 0, 60) . "\nBeschreibung: " . mb_substr($brief, 0, 1200), $user, 1800);
        if (!$r['ok']) {
            return $r;
        }
        $d = is_array($r['data']) ? $r['data'] : (AiGatewayService::extractJson($r['text']) ?? []);
        $s = mb_substr(trim(strip_tags((string)($d['short'] ?? ''))), 0, 80);
        $l = mb_substr(trim(strip_tags((string)($d['long'] ?? ''))), 0, 4000);
        return $s === '' && $l === '' ? ['ok' => false, 'message' => 'Kein verwertbarer Vorschlag. Bitte erneut versuchen.'] : ['ok' => true, 'short' => $s, 'long' => $l, 'provider' => $r['provider'], 'model' => $r['model']];
    }
}

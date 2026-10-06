<?php
declare(strict_types=1);
// cms/src/Ai/AltTexter.php
//
// KI-Vorschläge für Alternativtexte (Alt-Texte) von Bildern. Mit einem Anbieter mit Bildverständnis (OpenAI, Claude, Gemini) beschreibt die KI das Bild selbst;
// sonst wird – falls vorhanden – aus den Angaben zum Bild (Titel der Bildquelle, Dateiname) ein Text formuliert. Nichts wird gespeichert: der Vorschlag geht zur Prüfung an die Oberfläche.

namespace Elvado\Ai;

final class AltTexter
{
    public const MAX_LEN = 125;

    public function __construct(private readonly AiGatewayService $ai)
    {
    }

    /**
     * @param array<string,mixed> $meta meta.json des Bildes (name, credit, width/height …)
     * @param string $file kleine Bilddatei für die KI (leer = nur Text)
     * @return array{alt:string,mode:string,provider:string}
     * @throws AiGatewayException
     */
    public function suggest(array $meta, string $file = '', string $user = '', string $context = ''): array
    {
        $vision = $file !== '' && is_file($file) ? $this->ai->visionProvider() : '';
        $name = self::readableName((string)($meta['name'] ?? ''));
        $title = self::clip((string)($meta['credit']['title'] ?? ''), 160);
        $hint = trim(($title !== '' ? "Titel laut Bildquelle: $title\n" : '') . ($name !== '' ? "Dateiname: $name\n" : '') . ($context !== '' ? 'Verwendung: ' . self::clip($context, 200) . "\n" : ''));
        $system = 'Du schreibst Alternativtexte (Alt-Texte) für Bilder auf Websites, auf Deutsch. Regeln: ein einziger Satz oder eine Wortgruppe, höchstens ' . self::MAX_LEN .
            ' Zeichen, beschreibt knapp und sachlich, was auf dem Bild zu sehen ist; beginne nicht mit „Bild von“, „Foto von“ oder „Grafik“; keine Anführungszeichen, keine Erklärungen, kein Punkt am Ende nötig. Erfinde nichts, was nicht erkennbar oder angegeben ist. Gib nur den Alt-Text aus.';
        if ($vision !== '') {
            $raw = (string)file_get_contents($file);
            $mime = (string)(new \finfo(FILEINFO_MIME_TYPE))->file($file);
            $r = $this->ai->generate(['provider' => $vision, 'task' => 'alt', 'internal' => true, 'system' => $system, 'prompt' => 'Beschreibe dieses Bild als Alt-Text.' . ($hint !== '' ? "\n\nZusätzliche Angaben:\n$hint" : ''),
                'images' => [['mime' => $mime, 'data' => base64_encode($raw)]], 'temperature' => 0.2, 'max_tokens' => 120, 'user' => $user]);
            return ['alt' => self::clean($r->text), 'mode' => 'vision', 'provider' => $r->provider];
        }
        if ($title === '' && ($name === '' || mb_strlen($name) < 4)) {
            throw new AiGatewayException('Für diese Bildbeschreibung braucht die KI ein Bildverständnis: Lege in der KI-Zentrale einen Anbieter wie OpenAI, Claude oder Gemini an – oder trage den Alt-Text selbst ein.', 400);
        }
        $r = $this->ai->generate(['purpose' => 'media', 'task' => 'alt', 'internal' => true, 'system' => $system,
            'prompt' => "Formuliere aus diesen Angaben einen Alt-Text (das Bild selbst liegt dir nicht vor):\n$hint", 'temperature' => 0.2, 'max_tokens' => 120, 'user' => $user]);
        return ['alt' => self::clean($r->text), 'mode' => 'text', 'provider' => $r->provider];
    }

    /** Aus einem Dateinamen wie „holz-tisch-pixabay-123456“ lesbare Wörter machen (Anbieter-Kürzel und Ziffern entfernen). */
    public static function readableName(string $n): string
    {
        $n = pathinfo($n, PATHINFO_FILENAME);
        $n = preg_replace('/\b(pixabay|pexels|unsplash|openverse|wikimedia|img|dsc|image|bild|photo|foto|upload|original)\b/i', ' ', str_replace(['-', '_', '.'], ' ', $n)) ?? '';
        $n = preg_replace('/\b\d[\d a-f]*\b/i', ' ', $n) ?? '';
        $n = trim(preg_replace('/\s+/', ' ', $n) ?? '');
        return preg_match('/\p{L}{3,}/u', $n) ? mb_substr($n, 0, 120) : '';
    }

    private static function clip(string $s, int $max): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($s)) ?? ''), 0, $max);
    }

    /** Antwort der KI zu einem brauchbaren Alt-Text bereinigen. */
    public static function clean(string $t): string
    {
        $t = strip_tags($t);
        $t = trim(explode("\n", trim($t))[0] ?? '');
        $t = trim($t, " \t\"'„“”‚‘’«»`*");
        $t = preg_replace('/^(alt[- ]?text|beschreibung)\s*:\s*/iu', '', $t) ?? $t;
        $t = trim(preg_replace('/\s+/u', ' ', $t) ?? '');
        if (mb_strlen($t) > self::MAX_LEN) {
            $cut = mb_substr($t, 0, self::MAX_LEN);
            $sp = mb_strrpos($cut, ' ');
            $t = rtrim($sp !== false && $sp > 60 ? mb_substr($cut, 0, $sp) : $cut, " ,;:–-");
        }
        $t = rtrim($t, '.');
        return $t !== '' ? mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1) : '';
    }
}

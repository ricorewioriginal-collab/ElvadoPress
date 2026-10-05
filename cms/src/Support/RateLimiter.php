<?php
declare(strict_types=1);
// cms/src/Support/RateLimiter.php – einfacher dateibasierter Zähler (Fenster in Sekunden) gegen Missbrauch von KI- und Webhook-Endpunkten.

namespace Elvado\Support;

final class RateLimiter
{
    public function __construct(private readonly string $dir)
    {
    }

    /** true = erlaubt (und gezählt), false = Limit erreicht. */
    public function hit(string $key, int $limit, int $windowSeconds): bool
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
            return true;   // ohne beschreibbaren Ordner nicht blockieren
        }
        $file = $this->dir . '/' . hash('sha256', $key) . '.json';
        $fh = @fopen($file, 'c+');
        if (!$fh) {
            return true;
        }
        flock($fh, LOCK_EX);
        $raw = stream_get_contents($fh);
        $d = json_decode($raw ?: '', true);
        $now = time();
        $hits = array_values(array_filter(is_array($d) ? $d : [], static fn($t) => is_int($t) && $t > $now - $windowSeconds));
        $ok = count($hits) < $limit;
        if ($ok) {
            $hits[] = $now;
        }
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($hits));
        flock($fh, LOCK_UN);
        fclose($fh);
        return $ok;
    }
}

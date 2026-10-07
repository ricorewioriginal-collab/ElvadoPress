<?php
declare(strict_types=1);
// cms/src/Wp/Migration/NullProbe.php – Kein Ziel: der Trockenlauf analysiert nur die Quelle.

namespace Elvado\Wp\Migration;

final class NullProbe implements TargetProbe
{
    public function available(): bool { return false; }
    public function slugs(): array { return []; }
    public function users(): array { return []; }
    public function terms(string $taxonomy): array { return []; }
    public function migrated(): array { return []; }
    public function mediaNames(): array { return []; }
}

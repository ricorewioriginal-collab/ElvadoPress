<?php
declare(strict_types=1);
// cms/src/Wp/Adapter/ContentAdapter.php – Schnittstelle für Inhalte: ElvadoPress-Daten (NativeAdapter) und echtes WordPress (WordPressAdapter).
// ElvadoPress-Oberfläche → Dienste → Adapter → Datenquelle. Clients hängen nie direkt an WordPress.

namespace Elvado\Wp\Adapter;

interface ContentAdapter
{
    public function name(): string;

    /** @return array{posts:int,drafts:int,pages:int,media:int,comments:int,users:int,categories:int,tags:int} */
    public function counts(): array;
}

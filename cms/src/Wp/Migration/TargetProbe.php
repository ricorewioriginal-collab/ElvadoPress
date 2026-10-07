<?php
declare(strict_types=1);
// cms/src/Wp/Migration/TargetProbe.php – Nur lesende Sicht auf das Ziel (WordPress) für den Trockenlauf: vorhandene Adressen (Slugs), Benutzer, Begriffe, bereits übernommene Inhalte.

namespace Elvado\Wp\Migration;

interface TargetProbe
{
    /** Ist ein Ziel vorhanden (Engine aktiv und gestartet)? */
    public function available(): bool;

    /** @return array<string,string> Slug ⇒ Typ (post|page) vorhandener, nicht gelöschter Inhalte */
    public function slugs(): array;

    /** @return list<string> Anmeldenamen (klein geschrieben) */
    public function users(): array;

    /** @return list<string> Namen vorhandener Kategorien/Schlagwörter (klein geschrieben), je Taxonomie */
    public function terms(string $taxonomy): array;

    /** @return array<string,true> bereits übernommene Quellen, Schlüssel „post:12“, „page:slug“, „media:abc“ */
    public function migrated(): array;

    /** @return list<string> Dateinamen vorhandener Medien (klein geschrieben) */
    public function mediaNames(): array;
}

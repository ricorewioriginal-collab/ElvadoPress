<?php
declare(strict_types=1);
// cms/src/Wp/Adapter/ContentAdapter.php – Schnittstelle für Inhalte: ElvadoPress-Daten (NativeAdapter) und echtes WordPress (WordPressAdapter).
// ElvadoPress-Oberfläche → Dienste (ContentService) → Adapter → Datenquelle. Clients hängen nie direkt an WordPress.
//
// Einheitliches Inhaltsmodell (Beitrag/Seite):
//   id (string), type ('post'|'page'), title, slug, content (HTML), excerpt, status ('published'|'draft'|'scheduled'|'private'|'trash'),
//   date ('Y-m-d H:i:s', Ortszeit), modified, author (Anzeigename), categories (list<string>), tags (list<string>), parent (id oder ''), image (Adresse oder '')
// Begriff (Kategorie/Schlagwort): id (string), name, slug, count (int), parent (id oder '')

namespace Elvado\Wp\Adapter;

interface ContentAdapter
{
    public const STATUSES = ['published', 'draft', 'scheduled', 'private', 'trash'];

    public function name(): string;

    /** Ob dieser Adapter Inhalte ändern kann (NativeAdapter: nein – dort gilt weiter die bisherige Verwaltung). */
    public function writable(): bool;

    /** @return array{posts:int,drafts:int,pages:int,media:int,comments:int,users:int,categories:int,tags:int} */
    public function counts(): array;

    /**
     * @param array{status?:string,search?:string,page?:int,per_page?:int,category?:string} $q
     * @return array{items:list<array<string,mixed>>,total:int}
     */
    public function items(string $type, array $q = []): array;

    /** @return array<string,mixed>|null */
    public function item(string $type, string $id): ?array;

    /**
     * Anlegen (ohne id) oder ändern (mit id).
     * @param array<string,mixed> $data Felder des einheitlichen Modells (nur gesetzte Felder werden geändert)
     * @return array<string,mixed> der gespeicherte Inhalt
     */
    public function save(string $type, array $data, bool $unfiltered): array;

    /** Endgültig löschen ($force) oder in den Papierkorb legen. */
    public function delete(string $type, string $id, bool $force): bool;

    /** @return list<array{id:string,name:string,slug:string,count:int,parent:string}> */
    public function terms(string $taxonomy): array;

    /** @param array{id?:string,name?:string,slug?:string,parent?:string} $data @return array{id:string,name:string,slug:string,count:int,parent:string} */
    public function saveTerm(string $taxonomy, array $data): array;

    public function deleteTerm(string $taxonomy, string $id): bool;
}

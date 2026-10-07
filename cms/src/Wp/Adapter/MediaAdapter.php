<?php
declare(strict_types=1);
// cms/src/Wp/Adapter/MediaAdapter.php – Schnittstelle für Medien (Mediathek).
// Einheitliches Modell: id, url, name (Dateiname), title, alt, caption, mime, kind ('image'|'video'|'audio'|'document'), size (Bytes), width, height, date, owner

namespace Elvado\Wp\Adapter;

interface MediaAdapter
{
    public function writable(): bool;

    /** @param array{search?:string,kind?:string,page?:int,per_page?:int} $q @return array{items:list<array<string,mixed>>,total:int} */
    public function items(array $q = []): array;

    /** @return array<string,mixed>|null */
    public function item(string $id): ?array;

    /**
     * Eine bereits geprüfte Datei aufnehmen (der Dienst hat Typ, Größe und Namen kontrolliert).
     * @param array{title?:string,alt?:string,caption?:string,owner?:string} $meta
     * @return array<string,mixed>
     */
    public function add(string $file, string $name, string $mime, array $meta): array;

    /** @param array{title?:string,alt?:string,caption?:string} $data @return array<string,mixed> */
    public function update(string $id, array $data): array;

    public function delete(string $id): bool;
}

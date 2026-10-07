<?php
declare(strict_types=1);
// cms/src/Wp/Adapter/WidgetAdapter.php – Schnittstelle für Widgets.
// Bereich: id, name, description, inactive (Ablage „nicht verwendet“), widgets[]
// Widget:  id (z. B. „text-2“), id_base, number, title, summary, settings{…}
// Typ:     id_base, name, description

namespace Elvado\Wp\Adapter;

interface WidgetAdapter
{
    public function writable(): bool;

    /** @return list<array{id:string,name:string,description:string,inactive:bool,widgets:list<array<string,mixed>>}> */
    public function areas(): array;

    /** @return list<array{id_base:string,name:string,description:string}> */
    public function types(): array;

    /** Neues Widget am Ende eines Bereichs. @param array<string,mixed> $settings @return array<string,mixed> */
    public function add(string $area, string $idBase, array $settings, bool $unfiltered): array;

    /** @param array<string,mixed> $settings @return array<string,mixed> */
    public function update(string $widgetId, array $settings, bool $unfiltered): array;

    /** Reihenfolge und Zuordnung setzen: Bereich ⇒ Widget-Kennungen. @param array<string,list<string>> $layout */
    public function move(array $layout): void;

    public function delete(string $widgetId): bool;
}

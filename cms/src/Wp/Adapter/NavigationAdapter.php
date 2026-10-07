<?php
declare(strict_types=1);
// cms/src/Wp/Adapter/NavigationAdapter.php – Schnittstelle für Menüs (Navigation).
// Menü: id, name, slug, count, locations (Orte des Themes, an denen es hängt). Eintrag (Baum): id, type (custom|page|post|category|tag), object_id, label, url, target ('' oder '_blank'), rel, classes, children[]

namespace Elvado\Wp\Adapter;

interface NavigationAdapter
{
    public function writable(): bool;

    /** @return list<array{id:string,name:string,slug:string,count:int,locations:list<string>}> */
    public function menus(): array;

    /** @return array{id:string,name:string,items:list<array<string,mixed>>}|null */
    public function tree(string $menuId): ?array;

    /** @return array{id:string,name:string,slug:string,count:int,locations:list<string>} */
    public function create(string $name): array;

    public function rename(string $menuId, string $name): void;

    public function delete(string $menuId): bool;

    /**
     * Ganzen Baum speichern (neue Einträge anlegen, vorhandene ändern/verschieben, fehlende entfernen).
     * @param list<array<string,mixed>> $items bereinigter Baum
     * @return list<array<string,mixed>> gespeicherter Baum (mit Kennungen)
     */
    public function saveTree(string $menuId, array $items): array;

    /** @return list<array{id:string,label:string,menu:string}> Orte des aktiven Themes; menu = Kennung des zugewiesenen Menüs oder '' */
    public function locations(): array;

    public function assign(string $location, string $menuId): void;
}

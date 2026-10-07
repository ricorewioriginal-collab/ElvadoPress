<?php
declare(strict_types=1);
// cms/src/Wp/Adapter/UserAdapter.php – Schnittstelle für Benutzer.
// Einheitliches Modell: id, login, display_name, email, role ('admin'|'autor'), registered
// Anmeldung und Rechte bestimmt ElvadoPress (lokale Benutzer); die WordPress-Benutzer sind ein Spiegel dazu.

namespace Elvado\Wp\Adapter;

interface UserAdapter
{
    public function writable(): bool;

    /** @return list<array<string,mixed>> */
    public function all(): array;

    /** @return array<string,mixed>|null */
    public function byLogin(string $login): ?array;

    /**
     * Anlegen oder aktualisieren (nach Anmeldename). Das Passwort wird nie übernommen: WordPress-Benutzer bekommen ein zufälliges, unbenutztes Passwort.
     * @param array{login:string,display_name:string,email:string,role:string} $u
     * @return array{action:'created'|'updated'|'unchanged',user:array<string,mixed>}
     */
    public function upsert(array $u): array;
}

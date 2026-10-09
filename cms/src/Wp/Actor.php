<?php
declare(strict_types=1);
// cms/src/Wp/Actor.php – Wer handelt? Anmeldename und ElvadoPress-Rolle (admin|autor) der aufrufenden Person; Grundlage der Rechteprüfung in den Diensten.

namespace Elvado\Wp;

final class Actor
{
    public const ROLES = ['admin', 'autor'];

    public function __construct(public readonly string $login, public readonly string $role)
    {
        if (!in_array($role, self::ROLES, true)) {
            throw new \InvalidArgumentException('Unbekannte Rolle.');
        }
    }

    public function isAdmin(): bool { return $this->role === 'admin'; }

    /** Aus dem Ergebnis von elvado_auth(): ['user'=>…, 'role'=>…]. Unbekannte Rollen gelten als Autor (kleinste Rechte). */
    public static function fromAuth(array $u): self
    {
        $role = (string)($u['role'] ?? 'autor');
        return new self(mb_substr((string)($u['user'] ?? ''), 0, 100), $role === 'admin' ? 'admin' : 'autor');
    }

    /** Rechte je Art: content_write (eigene Beiträge), content_any (fremde Inhalte, Seiten), terms_write, media_write, media_any, users. */
    public function can(string $what): bool
    {
        return match ($what) {
            'content_write', 'media_write' => true,
            'content_any', 'terms_write', 'media_any', 'users', 'engine' => $this->isAdmin(),
            default => false,
        };
    }
}

<?php
declare(strict_types=1);
// cms/src/Wp/Adapter/NativeUserAdapter.php – Lokale ElvadoPress-Benutzer (cms/data/local-auth.local.php). Nur lesend; Anlegen/Ändern bleibt in der Benutzerverwaltung. Passwort-Hashes verlassen diese Klasse nie.

namespace Elvado\Wp\Adapter;

final class NativeUserAdapter implements UserAdapter
{
    public function __construct(private readonly string $dataDir)
    {
    }

    public function writable(): bool { return false; }

    public function all(): array
    {
        $f = $this->dataDir . '/local-auth.local.php';
        $c = is_file($f) ? (static function (string $f) { return include $f; })($f) : null;
        $rows = is_array($c) ? (isset($c['users']) && is_array($c['users']) ? $c['users'] : (!empty($c['username']) ? [['username' => $c['username'], 'role' => 'admin']] : [])) : [];
        $out = [];
        foreach ($rows as $u) {
            if (!is_array($u) || (string)($u['username'] ?? '') === '') {
                continue;
            }
            $out[] = [
                'id' => (string)$u['username'], 'login' => (string)$u['username'], 'display_name' => (string)($u['display_name'] ?? $u['username']),
                'email' => (string)($u['email'] ?? ''), 'role' => ($u['role'] ?? '') === 'admin' ? 'admin' : 'autor', 'registered' => (string)($u['created_at'] ?? ''),
            ];
        }
        return $out;
    }

    public function byLogin(string $login): ?array
    {
        foreach ($this->all() as $u) {
            if (strcasecmp($u['login'], $login) === 0) {
                return $u;
            }
        }
        return null;
    }

    public function upsert(array $u): array
    {
        throw new \RuntimeException('Benutzer werden in ElvadoPress über die Benutzerverwaltung geändert.');
    }
}

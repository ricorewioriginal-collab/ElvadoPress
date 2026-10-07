<?php
declare(strict_types=1);
// cms/src/Wp/Adapter/WordPressUserAdapter.php – WordPress-Benutzer als Spiegel der ElvadoPress-Benutzer (Autorenschaft, Kompatibilität). Eine WordPress-Anmeldung wird nicht genutzt.

namespace Elvado\Wp\Adapter;

use Elvado\Wp\{Bridge, Roles};

final class WordPressUserAdapter implements UserAdapter
{
    public function __construct()
    {
        if (!Bridge::booted()) {
            throw new \RuntimeException('WordPress ist in dieser Anfrage nicht gestartet.');
        }
    }

    public function writable(): bool { return true; }

    public function all(): array
    {
        return array_map(fn($u) => $this->normalize($u), get_users(['orderby' => 'login', 'order' => 'ASC']));
    }

    public function byLogin(string $login): ?array
    {
        $u = get_user_by('login', $login);
        return $u instanceof \WP_User ? $this->normalize($u) : null;
    }

    public function upsert(array $u): array
    {
        $login = (string)$u['login'];
        $role = Roles::toWp((string)$u['role']);
        $ex = get_user_by('login', $login);
        if (!$ex instanceof \WP_User) {
            $id = wp_insert_user(['user_login' => $login, 'user_pass' => bin2hex(random_bytes(24)), 'user_email' => (string)$u['email'], 'display_name' => (string)$u['display_name'], 'role' => $role]);
            if (is_wp_error($id)) {
                throw new \RuntimeException($id->get_error_message());
            }
            return ['action' => 'created', 'user' => $this->byLogin($login) ?? []];
        }
        $cur = $this->normalize($ex);
        $arr = ['ID' => $ex->ID];
        if ($cur['display_name'] !== (string)$u['display_name']) {
            $arr['display_name'] = (string)$u['display_name'];
        }
        if ((string)$u['email'] !== '' && $cur['email'] !== (string)$u['email']) {
            $arr['user_email'] = (string)$u['email'];
        }
        if ($cur['role'] !== (string)$u['role']) {
            $arr['role'] = $role;
        }
        if (count($arr) === 1) {
            return ['action' => 'unchanged', 'user' => $cur];
        }
        $r = wp_update_user($arr);
        if (is_wp_error($r)) {
            throw new \RuntimeException($r->get_error_message());
        }
        return ['action' => 'updated', 'user' => $this->byLogin($login) ?? []];
    }

    /** @return array<string,mixed> */
    private function normalize(\WP_User $u): array
    {
        return ['id' => (string)$u->ID, 'login' => (string)$u->user_login, 'display_name' => (string)$u->display_name, 'email' => (string)$u->user_email,
            'role' => Roles::fromWp(array_values((array)$u->roles)), 'registered' => (string)$u->user_registered];
    }
}

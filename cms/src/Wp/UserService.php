<?php
declare(strict_types=1);
// cms/src/Wp/UserService.php – Dienstschicht für Benutzer: Anzeige und Abgleich der ElvadoPress-Benutzer mit den WordPress-Benutzern (Spiegel). Nur Administratoren.

namespace Elvado\Wp;

use Elvado\Wp\Adapter\UserAdapter;

final class UserService
{
    public function __construct(private readonly UserAdapter $adapter, private readonly ?Actor $actor = null)
    {
    }

    private function guard(): void
    {
        if (!($this->actor ?? new Actor('', 'admin'))->can('users')) {
            throw new PermissionException('Benutzer sehen und abgleichen dürfen nur Administratoren.');
        }
    }

    /** @return list<array<string,mixed>> */
    public function list(): array
    {
        $this->guard();
        return $this->adapter->all();
    }

    /**
     * Gleicht die Quelle (ElvadoPress-Benutzer) mit dem Ziel (WordPress) ab: fehlende anlegen, geänderte aktualisieren – nie löschen.
     * @return array{created:list<string>,updated:list<string>,unchanged:int,skipped:list<array{login:string,reason:string}>,only_in_target:list<string>}
     */
    public function sync(UserAdapter $source): array
    {
        $this->guard();
        if (!$this->adapter->writable()) {
            throw new \RuntimeException('Das Ziel ist schreibgeschützt.');
        }
        $res = ['created' => [], 'updated' => [], 'unchanged' => 0, 'skipped' => [], 'only_in_target' => []];
        $seen = [];
        foreach ($source->all() as $u) {
            $login = (string)$u['login'];
            if (preg_match('/^[A-Za-z0-9._@-]{1,60}$/', $login) !== 1) {
                $res['skipped'][] = ['login' => mb_substr($login, 0, 60), 'reason' => 'Anmeldename enthält Zeichen, die WordPress nicht annimmt.'];
                continue;
            }
            $email = filter_var((string)$u['email'], FILTER_VALIDATE_EMAIL) ? (string)$u['email'] : '';
            $name = mb_substr(trim(strip_tags((string)($u['display_name'] !== '' ? $u['display_name'] : $login))), 0, 100);
            $seen[strtolower($login)] = 1;
            try {
                $r = $this->adapter->upsert(['login' => $login, 'display_name' => $name, 'email' => $email, 'role' => ($u['role'] ?? '') === 'admin' ? 'admin' : 'autor']);
            } catch (\RuntimeException $e) {
                $res['skipped'][] = ['login' => $login, 'reason' => mb_substr($e->getMessage(), 0, 200)];
                continue;
            }
            if ($r['action'] === 'created') {
                $res['created'][] = $login;
            } elseif ($r['action'] === 'updated') {
                $res['updated'][] = $login;
            } else {
                $res['unchanged']++;
            }
        }
        foreach ($this->adapter->all() as $t) {
            if (!isset($seen[strtolower($t['login'])])) {
                $res['only_in_target'][] = $t['login'];
            }
        }
        return $res;
    }
}

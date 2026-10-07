<?php
declare(strict_types=1);
// cms/src/Wp/Roles.php – Zuordnung der ElvadoPress-Rollen zu WordPress-Rollen. Anmeldung und Rechte bestimmt ElvadoPress; die WordPress-Benutzer dienen als Spiegel (Autorenschaft, Kompatibilität).

namespace Elvado\Wp;

final class Roles
{
    private const TO_WP = ['admin' => 'administrator', 'autor' => 'author'];

    public static function toWp(string $role): string { return self::TO_WP[$role] ?? 'author'; }

    /** @param list<string> $wpRoles */
    public static function fromWp(array $wpRoles): string { return in_array('administrator', $wpRoles, true) ? 'admin' : 'autor'; }
}

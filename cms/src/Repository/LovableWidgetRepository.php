<?php
declare(strict_types=1);
// cms/src/Repository/LovableWidgetRepository.php
//
// Tabelle lovable_widgets: je Widget project_id (Lovable-Projekt), component_name (Name des Custom Elements, z. B. "news-grid") und eine
// Konfiguration (Filter für die Beitragsdaten, feste Attribute, optionale Skript-Adresse). Eindeutig je (project_id, component_name).

namespace Elvado\Repository;

use Elvado\Database\DatabaseConnection;
use Elvado\Database\DatabaseException;

final class LovableWidgetRepository
{
    public function __construct(private readonly DatabaseConnection $db)
    {
    }

    public static function validProject(string $id): bool
    {
        return (bool)preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,119}$/', $id);
    }

    /** Gültiger Name eines Custom Elements (Kleinbuchstaben mit mindestens einem Bindestrich); reservierte HTML-Namen sind ausgeschlossen. */
    public static function validComponent(string $name): bool
    {
        $reserved = ['annotation-xml', 'color-profile', 'font-face', 'font-face-src', 'font-face-uri', 'font-face-format', 'font-face-name', 'missing-glyph'];
        return (bool)preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)+$/', $name) && strlen($name) <= 120 && !in_array($name, $reserved, true);
    }

    /**
     * Konfiguration bereinigen: posts{limit 1–50, category, tag, search}, attributes{name=>Text} (nur data- und aria-Attribute), script_url (https).
     * @return array<string,mixed>
     */
    public static function cleanConfig(mixed $in): array
    {
        $in = is_array($in) ? $in : [];
        $p = is_array($in['posts'] ?? null) ? $in['posts'] : [];
        $out = ['posts' => [
            'limit' => max(1, min(50, (int)($p['limit'] ?? 10))),
            'category' => mb_substr(trim(strip_tags((string)($p['category'] ?? ''))), 0, 80),
            'tag' => mb_substr(trim(strip_tags((string)($p['tag'] ?? ''))), 0, 60),
            'search' => mb_substr(trim(strip_tags((string)($p['search'] ?? ''))), 0, 100),
        ], 'attributes' => []];
        foreach (is_array($in['attributes'] ?? null) ? $in['attributes'] : [] as $k => $v) {
            if (count($out['attributes']) >= 20 || !is_string($k) || !preg_match('/^(data|aria)-[a-z0-9-]{1,40}$/', $k) || !is_scalar($v)) {
                continue;
            }
            $out['attributes'][$k] = mb_substr(trim(strip_tags((string)$v)), 0, 200);
        }
        $u = trim((string)($in['script_url'] ?? ''));
        $out['script_url'] = preg_match('~^https://[A-Za-z0-9.-]+(:\d+)?/[^\s"\'<>]{0,500}$~', $u) ? $u : '';
        return $out;
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return array_map([$this, 'hydrate'], $this->db->fetchAll('SELECT * FROM lovable_widgets ORDER BY project_id, component_name'));
    }

    public function find(int $id): ?array
    {
        $r = $this->db->fetchOne('SELECT * FROM lovable_widgets WHERE id = ?', [$id]);
        return $r ? $this->hydrate($r) : null;
    }

    public function findByComponent(string $component, ?string $projectId = null): ?array
    {
        $r = $projectId !== null
            ? $this->db->fetchOne('SELECT * FROM lovable_widgets WHERE component_name = ? AND project_id = ?', [$component, $projectId])
            : $this->db->fetchOne('SELECT * FROM lovable_widgets WHERE component_name = ? ORDER BY id LIMIT 1', [$component]);
        return $r ? $this->hydrate($r) : null;
    }

    /** Anlegen oder (bei gleicher project_id + component_name) aktualisieren. @return array<string,mixed> gespeicherte Zeile */
    public function save(array $in): array
    {
        $project = trim((string)($in['project_id'] ?? ''));
        $component = strtolower(trim((string)($in['component_name'] ?? '')));
        if (!self::validProject($project)) {
            throw new DatabaseException('Ungültige Projekt-ID (Buchstaben, Ziffern, _ und -).');
        }
        if (!self::validComponent($component)) {
            throw new DatabaseException('Ungültiger Komponentenname: ein Custom Element braucht Kleinbuchstaben und mindestens einen Bindestrich (z. B. news-grid).');
        }
        $label = mb_substr(trim(strip_tags((string)($in['label'] ?? ''))), 0, 160);
        $config = json_encode(self::cleanConfig($in['config'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $enabled = array_key_exists('enabled', $in) ? (filter_var($in['enabled'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 1;
        $now = DatabaseConnection::now();
        $existing = $this->db->fetchOne('SELECT id FROM lovable_widgets WHERE project_id = ? AND component_name = ?', [$project, $component]);
        if ($existing) {
            $this->db->execute('UPDATE lovable_widgets SET label = ?, config_json = ?, enabled = ?, updated_at = ? WHERE id = ?', [$label, $config, $enabled, $now, (int)$existing['id']]);
            $id = (int)$existing['id'];
        } else {
            $id = (int)$this->db->insert('lovable_widgets', ['project_id' => $project, 'component_name' => $component, 'label' => $label, 'config_json' => $config, 'enabled' => $enabled, 'created_at' => $now, 'updated_at' => $now]);
        }
        return (array)$this->find($id);
    }

    public function delete(int $id): bool
    {
        return $this->db->execute('DELETE FROM lovable_widgets WHERE id = ?', [$id]) > 0;
    }

    /** @return array<string,mixed> */
    private function hydrate(array $r): array
    {
        $cfg = json_decode((string)($r['config_json'] ?? ''), true);
        return [
            'id' => (int)$r['id'],
            'project_id' => (string)$r['project_id'],
            'component_name' => (string)$r['component_name'],
            'label' => (string)$r['label'],
            'config' => self::cleanConfig($cfg),
            'enabled' => (int)$r['enabled'] === 1,
            'created_at' => (string)$r['created_at'],
            'updated_at' => (string)$r['updated_at'],
        ];
    }
}

<?php
declare(strict_types=1);
// cms/src/Components/Layout.php – Bereinigung und Regelprüfung von Layouts (Liste von Komponenten-Instanzen), Sichtbarkeit und responsive Werte.
//
// Instanz: { id, type, hidden, locked, props{…}, responsive{tablet{…},mobile{…}}, visibility{devices[…],audience,from,until}, children[…] }
// Unbekannte Typen (z. B. Erweiterung abgeschaltet) bleiben als „missing“ erhalten, damit nichts verloren geht, und werden nicht ausgegeben.

namespace Elvado\Components;

final class Layout
{
    public const DEVICES = ['desktop', 'tablet', 'mobile'];
    public const MAX_TOTAL = 120;
    public const MAX_DEPTH = 3;
    public const MAX_MISSING_BYTES = 20000;

    /** @var list<array{id:string,type:string,issue:string}> */
    public array $issues = [];

    public function __construct(private readonly Registry $registry)
    {
    }

    /**
     * @param mixed $raw Liste von Instanzen (unbekannte Eingabe)
     * @param array{unfiltered?:bool,admin?:bool} $ctx admin = darf Komponenten mit Rechtestufe „admin“ nutzen
     * @return list<array<string,mixed>>
     */
    public function clean(mixed $raw, array $ctx = []): array
    {
        $this->issues = [];
        $ids = [];
        $count = 0;
        $out = $this->cleanList(is_array($raw) ? array_values($raw) : [], null, 0, $ctx, $ids, $count);
        return $this->arrange($out);
    }

    /** @param list<mixed> $list @param array<string,int> $ids @return list<array<string,mixed>> */
    private function cleanList(array $list, ?Component $parent, int $depth, array $ctx, array &$ids, int &$count): array
    {
        $out = [];
        foreach ($list as $s) {
            if (!is_array($s) || $count >= self::MAX_TOTAL) {
                continue;
            }
            $type = (string)($s['type'] ?? '');
            $id = $this->id((string)($s['id'] ?? ''), $ids);
            $c = $this->registry->get($type);
            if ($c === null) {
                if (preg_match('/^[a-z][a-z0-9_]{1,40}$/', $type) === 1 && strlen((string)json_encode($s['props'] ?? [])) <= self::MAX_MISSING_BYTES) {
                    $count++;
                    $out[] = ['id' => $id, 'type' => $type, 'hidden' => !empty($s['hidden']), 'missing' => true, 'props' => is_array($s['props'] ?? null) ? $s['props'] : []];
                    $this->issues[] = ['id' => $id, 'type' => $type, 'issue' => 'missing'];
                }
                continue;
            }
            if ($c->rules['use'] === 'admin' && empty($ctx['admin'])) {
                $this->issues[] = ['id' => $id, 'type' => $type, 'issue' => 'forbidden'];
                continue;
            }
            if ($parent !== null && !$this->accepts($parent, $c)) {
                $this->issues[] = ['id' => $id, 'type' => $type, 'issue' => 'not_accepted'];
                continue;
            }
            if ($parent === null && $c->rules['parents'] !== [] && !in_array('root', $c->rules['parents'], true)) {
                $this->issues[] = ['id' => $id, 'type' => $type, 'issue' => 'needs_parent'];
                continue;
            }
            $count++;
            $inst = [
                'id' => $id, 'type' => $type, 'hidden' => !empty($s['hidden']), 'locked' => !empty($s['locked']) || !empty($c->rules['locked']),
                'props' => $this->props($c, $s['props'] ?? [], $ctx), 'responsive' => $this->responsive($c, $s['responsive'] ?? [], $ctx), 'visibility' => self::visibility($s['visibility'] ?? []),
            ];
            if ($c->rules['droppable']) {
                $inst['children'] = $depth + 1 >= self::MAX_DEPTH ? [] : $this->cleanList(is_array($s['children'] ?? null) ? array_values($s['children']) : [], $c, $depth + 1, $ctx, $ids, $count);
            }
            $out[] = $inst;
        }
        return $out;
    }

    private function accepts(Component $parent, Component $child): bool
    {
        if (!$parent->rules['droppable']) {
            return false;
        }
        $acc = (array)$parent->rules['accepts'];
        if ($acc !== [] && !in_array($child->id, $acc, true) && !in_array('category:' . $child->category, $acc, true)) {
            return false;
        }
        $par = (array)$child->rules['parents'];
        return $par === [] || in_array($parent->id, $par, true);
    }

    /** @param array<string,int> $ids */
    private function id(string $raw, array &$ids): string
    {
        $id = (string)preg_replace('/[^a-z0-9_-]/', '', strtolower($raw));
        if ($id === '' || strlen($id) > 24 || isset($ids[$id])) {
            $id = 's' . substr(md5(uniqid('', true) . count($ids)), 0, 8);
        }
        $ids[$id] = 1;
        return $id;
    }

    /** @return array<string,mixed> */
    public function props(Component $c, mixed $raw, array $ctx = []): array
    {
        $raw = is_array($raw) ? $raw : [];
        $out = [];
        foreach ($c->fields as $f) {
            $out[$f['k']] = Sanitizer::value($f, $raw[$f['k']] ?? null, $ctx);
        }
        return $out;
    }

    /** Nur Felder mit „responsive“, nur Tablet/Mobil. @return array<string,array<string,mixed>> */
    public function responsive(Component $c, mixed $raw, array $ctx = []): array
    {
        $out = [];
        foreach (['tablet', 'mobile'] as $dev) {
            $r = is_array($raw) && is_array($raw[$dev] ?? null) ? $raw[$dev] : [];
            foreach ($c->fields as $f) {
                if ($f['responsive'] && array_key_exists($f['k'], $r)) {
                    $out[$dev][$f['k']] = Sanitizer::value($f, $r[$f['k']], $ctx);
                }
            }
        }
        return $out;
    }

    /** @return array{devices:list<string>,audience:string,from:string,until:string} */
    public static function visibility(mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $dev = array_values(array_intersect(self::DEVICES, array_map('strval', (array)($raw['devices'] ?? self::DEVICES))));
        $date = static function (mixed $v): string {
            $d = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', str_replace('T', ' ', substr((string)$v, 0, 16)));
            return $d ? $d->format('Y-m-d H:i') : '';
        };
        return ['devices' => $dev, 'audience' => in_array($raw['audience'] ?? '', ['guests', 'members'], true) ? (string)$raw['audience'] : 'all', 'from' => $date($raw['from'] ?? ''), 'until' => $date($raw['until'] ?? '')];
    }

    /** Server-seitige Sichtbarkeit: Ausblenden, Zeitfenster, Zielgruppe. Geräte regelt CSS (hideClasses). @param array{now?:int,member?:bool} $ctx */
    public static function visible(array $inst, array $ctx = []): bool
    {
        if (!empty($inst['hidden']) || !empty($inst['missing'])) {
            return false;
        }
        $v = self::visibility($inst['visibility'] ?? []);
        if ($v['devices'] === []) {
            return false;
        }
        $now = (int)($ctx['now'] ?? time());
        if (($v['from'] !== '' && $now < strtotime($v['from'] . ':00')) || ($v['until'] !== '' && $now >= strtotime($v['until'] . ':00'))) {
            return false;
        }
        $member = !empty($ctx['member']);
        return !(($v['audience'] === 'guests' && $member) || ($v['audience'] === 'members' && !$member));
    }

    /** CSS-Klassen für Geräte, auf denen die Komponente nicht erscheint. @return list<string> */
    public static function hideClasses(array $inst): array
    {
        $v = self::visibility($inst['visibility'] ?? []);
        return array_map(static fn($d) => 'ep-hide-' . $d, array_values(array_diff(self::DEVICES, $v['devices'])));
    }

    /** Werte für ein Gerät: Grundwerte, darüber die Überschreibungen (Tablet/Mobil; Mobil erbt von Tablet). @return array<string,mixed> */
    public static function resolve(array $inst, string $device): array
    {
        $p = (array)($inst['props'] ?? []);
        $r = (array)($inst['responsive'] ?? []);
        if ($device === 'tablet' || $device === 'mobile') {
            $p = (array)($r['tablet'] ?? []) + $p;
        }
        if ($device === 'mobile') {
            $p = (array)($r['mobile'] ?? []) + $p;
        }
        return $p;
    }

    /** Feste Plätze („first“/„last“) einhalten, nicht wiederholbare/zu viele Instanzen entfernen. @param list<array<string,mixed>> $list @return list<array<string,mixed>> */
    private function arrange(array $list): array
    {
        $n = [];
        $keep = [];
        foreach ($list as $s) {
            $c = $this->registry->get((string)$s['type']);
            if ($c) {
                $n[$c->id] = ($n[$c->id] ?? 0) + 1;
                $max = !$c->rules['repeatable'] ? 1 : (int)$c->rules['max_instances'];
                if ($max > 0 && $n[$c->id] > $max) {
                    $this->issues[] = ['id' => (string)$s['id'], 'type' => (string)$s['type'], 'issue' => $c->rules['repeatable'] ? 'too_many' : 'not_repeatable'];
                    continue;
                }
                if (isset($s['children'])) {
                    $s['children'] = $this->arrange($s['children']);
                }
            }
            $keep[] = $s;
        }
        $first = $last = $mid = [];
        foreach ($keep as $s) {
            $slot = ($c = $this->registry->get((string)$s['type'])) ? (string)$c->rules['slot'] : '';
            if ($slot === 'first') {
                $first[] = $s;
            } elseif ($slot === 'last') {
                $last[] = $s;
            } else {
                $mid[] = $s;
            }
        }
        return array_merge($first, $mid, $last);
    }
}

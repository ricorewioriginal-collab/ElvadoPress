<?php
declare(strict_types=1);
// cms/src/Wp/ContentService.php – Dienstschicht für Inhalte: prüft und bereinigt alle Eingaben serverseitig und ruft den Adapter auf.
// Die Oberfläche und die API sprechen nur mit diesem Dienst; welcher Adapter dahinter steht (eigene Daten oder echtes WordPress), ist ihnen egal.

namespace Elvado\Wp;

use Elvado\Wp\Adapter\ContentAdapter;

final class ContentService
{
    public const MAX_CONTENT = 2_000_000;

    /** Ohne Actor (Tests, Wartung) gelten Administratorrechte. */
    public function __construct(private readonly ContentAdapter $adapter, private readonly ?Actor $actor = null)
    {
    }

    private function actor(): Actor { return $this->actor ?? new Actor('', 'admin'); }

    /** Seiten und fremde Beiträge darf nur ändern, wer „content_any“ hat; Autoren nur eigene Beiträge (Besitzer = Anmeldename). */
    private function authorize(string $type, ?array $existing): void
    {
        $a = $this->actor();
        if ($a->can('content_any')) {
            return;
        }
        if ($type !== 'post' || !$a->can('content_write')) {
            throw new PermissionException('Dafür fehlt die Berechtigung (Seiten ändern nur Administratoren).');
        }
        if ($existing !== null && ($a->login === '' || strcasecmp((string)($existing['owner'] ?? ''), $a->login) !== 0)) {
            throw new PermissionException('Du darfst nur eigene Beiträge ändern.');
        }
    }

    public function adapter(): ContentAdapter { return $this->adapter; }

    /** @param array<string,mixed> $q @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int} */
    public function list(string $type, array $q): array
    {
        $this->type($type);
        $st = (string)($q['status'] ?? 'all');
        if ($st !== 'all' && !in_array($st, ContentAdapter::STATUSES, true)) {
            throw new \InvalidArgumentException('Unbekannter Status.');
        }
        $per = max(1, min(100, (int)($q['per_page'] ?? 20)));
        $page = max(1, (int)($q['page'] ?? 1));
        $r = $this->adapter->items($type, [
            'status' => $st, 'search' => mb_substr(trim((string)($q['search'] ?? '')), 0, 100), 'category' => mb_substr(trim((string)($q['category'] ?? '')), 0, 100),
            'per_page' => $per, 'page' => $page,
        ]);
        return $r + ['page' => $page, 'per_page' => $per];
    }

    /** @return array<string,mixed>|null */
    public function get(string $type, string $id): ?array
    {
        $this->type($type);
        return $this->id($id) === '' ? null : $this->adapter->item($type, $id);
    }

    /** @param array<string,mixed> $in @return array<string,mixed> */
    public function save(string $type, array $in, bool $unfiltered): array
    {
        $this->type($type);
        if (!$this->adapter->writable()) {
            throw new \RuntimeException('Dieser Datenbestand ist schreibgeschützt (WordPress-Engine nicht aktiv).');
        }
        $d = [];
        $existing = null;
        if (isset($in['id']) && (string)$in['id'] !== '') {
            $d['id'] = $this->id((string)$in['id']);
            if ($d['id'] === '') {
                throw new \InvalidArgumentException('Ungültige Kennung.');
            }
            $existing = $this->adapter->item($type, $d['id']);
            if ($existing === null) {
                throw new \RuntimeException('Der Inhalt wurde nicht gefunden.');
            }
        }
        $this->authorize($type, $existing);
        if ($existing === null && $this->actor()->login !== '') {
            $d['owner'] = $this->actor()->login;   // Besitzer = anlegende Person
        }
        if (array_key_exists('title', $in)) {
            $t = trim(strip_tags((string)$in['title']));
            if (mb_strlen($t) > 200) {
                throw new \InvalidArgumentException('Der Titel darf höchstens 200 Zeichen lang sein.');
            }
            $d['title'] = $t;
        }
        if (!isset($d['id']) && trim((string)($d['title'] ?? '')) === '') {
            throw new \InvalidArgumentException('Ein Titel ist nötig.');
        }
        if (array_key_exists('slug', $in)) {
            $d['slug'] = self::slug((string)$in['slug']);
        }
        if (array_key_exists('content', $in)) {
            $c = (string)$in['content'];
            if (strlen($c) > self::MAX_CONTENT || str_contains($c, "\0")) {
                throw new \InvalidArgumentException('Der Inhalt ist zu groß oder ungültig.');
            }
            $d['content'] = $c;
        }
        if (array_key_exists('excerpt', $in)) {
            $d['excerpt'] = mb_substr(strip_tags((string)$in['excerpt']), 0, 2000);
        }
        if (isset($in['status'])) {
            if (!in_array((string)$in['status'], ContentAdapter::STATUSES, true) || $in['status'] === 'trash') {
                throw new \InvalidArgumentException('Unzulässiger Status (zum Löschen die Löschaktion nutzen).');
            }
            $d['status'] = (string)$in['status'];
        }
        if (isset($in['date']) && (string)$in['date'] !== '') {
            $dt = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', str_replace('T', ' ', substr((string)$in['date'], 0, 19)));
            if (!$dt) {
                $dt = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', str_replace('T', ' ', substr((string)$in['date'], 0, 16)));
            }
            if (!$dt) {
                throw new \InvalidArgumentException('Ungültiges Datum (Format JJJJ-MM-TT HH:MM).');
            }
            $d['date'] = $dt->format('Y-m-d H:i:s');
        }
        if ($type === 'post') {
            foreach (['categories', 'tags'] as $k) {
                if (array_key_exists($k, $in)) {
                    $d[$k] = $this->names($in[$k], $k === 'categories' ? 'Kategorien' : 'Schlagwörter');
                }
            }
        } elseif (array_key_exists('parent', $in)) {
            $d['parent'] = $this->id((string)$in['parent']);
        }
        if (array_key_exists('author', $in)) {
            $d['author'] = mb_substr(trim(strip_tags((string)$in['author'])), 0, 100);
        }
        if (array_key_exists('image', $in)) {
            $u = trim((string)$in['image']);
            if ($u !== '' && preg_match('#^(https://|/)[^\s"<>]{1,1200}$#', $u) !== 1) {
                throw new \InvalidArgumentException('Die Bildadresse muss mit https:// oder / beginnen.');
            }
            $d['image'] = $u;
        }
        return $this->adapter->save($type, $d, $unfiltered && $this->actor()->isAdmin());
    }

    public function delete(string $type, string $id, bool $force): bool
    {
        $this->type($type);
        if (!$this->adapter->writable()) {
            throw new \RuntimeException('Dieser Datenbestand ist schreibgeschützt (WordPress-Engine nicht aktiv).');
        }
        if ($this->id($id) === '') {
            return false;
        }
        $ex = $this->adapter->item($type, $id);
        if ($ex === null) {
            return false;
        }
        $this->authorize($type, $ex);
        return $this->adapter->delete($type, $id, $force);
    }

    /** @return list<array{id:string,name:string,slug:string,count:int,parent:string}> */
    public function terms(string $taxonomy): array
    {
        return $this->adapter->terms($this->tax($taxonomy));
    }

    /** @param array<string,mixed> $in */
    public function saveTerm(string $taxonomy, array $in): array
    {
        $tax = $this->tax($taxonomy);
        if (!$this->adapter->writable()) {
            throw new \RuntimeException('Dieser Datenbestand ist schreibgeschützt (WordPress-Engine nicht aktiv).');
        }
        if (!$this->actor()->can('terms_write')) {
            throw new PermissionException('Kategorien und Schlagwörter verwalten nur Administratoren.');
        }
        $d = [];
        if (isset($in['id']) && (string)$in['id'] !== '') {
            $d['id'] = $this->id((string)$in['id']);
        }
        $name = trim(strip_tags((string)($in['name'] ?? '')));
        if ($name === '' && !isset($d['id'])) {
            throw new \InvalidArgumentException('Ein Name ist nötig.');
        }
        if (mb_strlen($name) > 80) {
            throw new \InvalidArgumentException('Der Name darf höchstens 80 Zeichen lang sein.');
        }
        if ($name !== '') {
            $d['name'] = $name;
        }
        if (isset($in['slug']) && (string)$in['slug'] !== '') {
            $d['slug'] = self::slug((string)$in['slug']);
        }
        if ($tax === 'category' && isset($in['parent'])) {
            $d['parent'] = $this->id((string)$in['parent']);
        }
        return $this->adapter->saveTerm($tax, $d);
    }

    public function deleteTerm(string $taxonomy, string $id): bool
    {
        $tax = $this->tax($taxonomy);
        if (!$this->adapter->writable()) {
            throw new \RuntimeException('Dieser Datenbestand ist schreibgeschützt (WordPress-Engine nicht aktiv).');
        }
        if (!$this->actor()->can('terms_write')) {
            throw new PermissionException('Kategorien und Schlagwörter verwalten nur Administratoren.');
        }
        return $this->id($id) !== '' && $this->adapter->deleteTerm($tax, $id);
    }

    private function type(string $t): void
    {
        if ($t !== 'post' && $t !== 'page') {
            throw new \InvalidArgumentException('Unbekannter Inhaltstyp.');
        }
    }

    private function tax(string $t): string
    {
        $m = ['category' => 'category', 'tag' => 'post_tag', 'post_tag' => 'post_tag'];
        if (!isset($m[$t])) {
            throw new \InvalidArgumentException('Unbekannte Taxonomie (category oder tag).');
        }
        return $m[$t];
    }

    /** Nur Ziffern (WordPress-Kennung); alles andere wird zu ''. */
    private function id(string $v): string
    {
        return preg_match('/^\d{1,18}$/', $v) === 1 ? $v : '';
    }

    public static function slug(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        return mb_substr(trim((string)preg_replace('/[^a-z0-9]+/', '-', $s), '-'), 0, 190);
    }

    /** @return list<string> */
    private function names(mixed $v, string $label): array
    {
        if (is_string($v)) {
            $v = preg_split('/\s*,\s*/', $v, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        if (!is_array($v) || count($v) > 30) {
            throw new \InvalidArgumentException($label . ': höchstens 30 Einträge.');
        }
        $out = [];
        foreach ($v as $n) {
            $n = trim(strip_tags((string)$n));
            if ($n === '') {
                continue;
            }
            if (mb_strlen($n) > 80) {
                throw new \InvalidArgumentException($label . ': Einträge dürfen höchstens 80 Zeichen lang sein.');
            }
            $out[mb_strtolower($n)] ??= $n;
        }
        return array_values($out);
    }
}

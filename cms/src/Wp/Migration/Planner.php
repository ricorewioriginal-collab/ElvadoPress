<?php
declare(strict_types=1);
// cms/src/Wp/Migration/Planner.php – Trockenlauf der Migration ElvadoPress → echtes WordPress.
// Liest die bisherigen Daten (nur lesend über die Native-Adapter) und das Ziel (nur lesend über TargetProbe) und erstellt einen Bericht:
// was würde angelegt, übersprungen, umbenannt oder abgelehnt; welche Probleme und Risiken gibt es; welche Schritte folgen.
// Schreibt NICHTS in WordPress und nichts in die bisherigen Daten.

namespace Elvado\Wp\Migration;

use Elvado\Wp\Adapter\NativeAdapter;
use Elvado\Wp\Adapter\NativeMediaAdapter;
use Elvado\Wp\Adapter\NativeNavigationAdapter;
use Elvado\Wp\Adapter\NativeUserAdapter;
use Elvado\Wp\Adapter\NativeWidgetAdapter;
use Elvado\Blocks\Converter;

final class Planner
{
    public const MAX_NOTES = 200;
    /** Erlaubte Medienendungen (wie MediaService). */
    private const MEDIA_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'pdf', 'mp3', 'ogg', 'wav', 'm4a', 'mp4', 'webm'];
    private const MAX_MEDIA = 33_554_432;

    /** @var list<array{level:string,area:string,message:string}> */
    private array $issues = [];

    public function __construct(private readonly string $cmsDir, private readonly string $dataDir, private readonly TargetProbe $probe, private readonly string $engineMode = 'off')
    {
    }

    /** @return array<string,mixed> */
    public function plan(): array
    {
        $this->issues = [];
        $t0 = microtime(true);
        $slugs = $this->probe->available() ? $this->probe->slugs() : [];
        $migrated = $this->probe->available() ? $this->probe->migrated() : [];
        $content = new NativeAdapter($this->cmsDir, $this->dataDir);

        $users = $this->users();
        $media = $this->media($migrated);
        $terms = $this->terms($content);
        $posts = $this->content($content, 'post', $slugs, $migrated, $users['logins']);
        $pages = $this->content($content, 'page', $slugs, $migrated, $users['logins']);
        $menus = $this->menus($content);
        $widgets = $this->widgets();
        $backup = $this->backup($media['bytes']);

        if (!$this->probe->available()) {
            $this->add('block', 'ziel', 'Die WordPress-Engine ist nicht aktiv (Betriebsart „' . $this->engineMode . '“). Die echte Migration setzt sie voraus; Konflikte mit vorhandenen WordPress-Inhalten konnten nicht geprüft werden – der Bericht gilt nur für ein leeres Ziel.');
        }
        $blockers = array_values(array_filter($this->issues, static fn($i) => $i['level'] === 'block'));
        $warnings = array_values(array_filter($this->issues, static fn($i) => $i['level'] === 'warn'));
        $verdict = $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'ready_with_warnings' : 'ready');

        return [
            'generated_at' => date('c'),
            'dry_run' => true,
            'wrote_anything' => false,
            'engine' => ['mode' => $this->engineMode, 'target_checked' => $this->probe->available()],
            'verdict' => $verdict,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'summary' => ['posts' => $posts['sum'], 'pages' => $pages['sum'], 'media' => $media['sum'], 'users' => $users['sum'], 'terms' => $terms, 'menus' => $menus['sum'], 'widgets' => $widgets['sum']],
            'content' => [
                'blocks_converted' => $posts['conv'] + $pages['conv'], 'blocks_fallback' => $posts['fb'] + $pages['fb'],
                'media_refs' => $posts['refs'] + $pages['refs'], 'media_refs_broken' => $posts['broken'] + $pages['broken'],
            ],
            'notes' => ['posts' => $posts['notes'], 'pages' => $pages['notes'], 'media' => $media['notes'], 'users' => $users['notes'], 'menus' => $menus['notes'], 'widgets' => $widgets['notes']],
            'redirects_sample' => array_slice(array_merge($posts['redirects'], $pages['redirects']), 0, 20),
            'backup' => $backup,
            'steps' => self::steps(),
            'risks' => self::risks(),
            'duration_ms' => (int)round((microtime(true) - $t0) * 1000),
        ];
    }

    /** @return list<array{nr:int,title:string,detail:string,writes:string}> */
    public static function steps(): array
    {
        return [
            ['nr' => 1, 'title' => 'Sicherung', 'detail' => 'Vollständige Kopie von cms/data (ohne Zugangsdaten im Bericht) und cms/media; Datenbank-Sicherung der WordPress-Tabellen. Ohne Sicherung beginnt nichts.', 'writes' => 'Sicherungsordner'],
            ['nr' => 2, 'title' => 'Benutzer', 'detail' => 'WordPress-Benutzer als Spiegel der lokalen Benutzer (zufälliges, unbenutztes Passwort; Anmeldung bleibt bei ElvadoPress).', 'writes' => 'WordPress'],
            ['nr' => 3, 'title' => 'Medien', 'detail' => 'Zulässige Dateien in die WordPress-Mediathek übernehmen; Originale bleiben unverändert in cms/media.', 'writes' => 'WordPress'],
            ['nr' => 4, 'title' => 'Kategorien und Schlagwörter', 'detail' => 'Fehlende Begriffe anlegen, vorhandene wiederverwenden.', 'writes' => 'WordPress'],
            ['nr' => 5, 'title' => 'Beiträge', 'detail' => 'Inhalt über den Block-Konverter (ep: → wp:, verlustfrei mit HTML-Rückfall); jeder Eintrag erhält die Quellkennung (_elvado_source_id) – dadurch wiederholbar ohne Dubletten.', 'writes' => 'WordPress'],
            ['nr' => 6, 'title' => 'Seiten', 'detail' => 'Wie Beiträge; Elternseiten zuerst.', 'writes' => 'WordPress'],
            ['nr' => 7, 'title' => 'Menüs', 'detail' => 'Menübäume in WordPress-Menüs; Ziele auf neue Inhalte abbilden.', 'writes' => 'WordPress'],
            ['nr' => 8, 'title' => 'Prüfung', 'detail' => 'Zählen und Stichproben vergleichen (Anzahl, Titel, Adressen); Abweichungen stoppen den Ablauf.', 'writes' => '–'],
            ['nr' => 9, 'title' => 'Umschalten', 'detail' => 'Erst nach ausdrücklicher Freigabe: Verwaltung und Website lesen aus WordPress. Rückweg: Sicherung bzw. Umschalter zurück; die bisherigen Daten bleiben bis dahin unverändert.', 'writes' => 'Einstellung'],
        ];
    }

    /** @return list<string> */
    public static function risks(): array
    {
        return [
            'Ändern sich Inhalte zwischen Trockenlauf und echter Migration, weicht der Bericht ab – die echte Migration prüft deshalb vorher erneut.',
            'Widgets und Layout-Zuweisungen der bisherigen Verwaltung haben kein 1:1-Gegenstück in WordPress und werden nicht automatisch übernommen.',
            'Eingebettete Skripte/Formulare in Inhalten bleiben als HTML-Block erhalten; ihr Verhalten im echten Theme ist nach der Migration zu prüfen.',
            'Benutzer-Passwörter werden nie übertragen; die Anmeldung läuft weiter über ElvadoPress.',
        ];
    }

    private function add(string $level, string $area, string $msg): void
    {
        $this->issues[] = ['level' => $level, 'area' => $area, 'message' => $msg];
    }

    /** @param list<array<string,mixed>> $notes @param array<string,mixed> $n */
    private function note(array &$notes, array $n): void
    {
        if (count($notes) < self::MAX_NOTES) {
            $notes[] = $n;
        }
    }

    /** @return array{sum:array<string,int>,notes:list<array<string,mixed>>,logins:array<string,true>} */
    private function users(): array
    {
        $existing = array_flip($this->probe->available() ? $this->probe->users() : []);
        $sum = ['total' => 0, 'create' => 0, 'skip' => 0, 'rejected' => 0, 'no_email' => 0];
        $notes = [];
        $logins = [];
        $seen = [];
        foreach ((new NativeUserAdapter($this->dataDir))->all() as $u) {
            $sum['total']++;
            $login = (string)$u['login'];
            if (preg_match('/^[A-Za-z0-9._@-]{1,60}$/', $login) !== 1) {
                $sum['rejected']++;
                $this->note($notes, ['login' => mb_substr($login, 0, 60), 'result' => 'rejected', 'reason' => 'Anmeldename enthält Zeichen, die WordPress nicht annimmt.']);
                continue;
            }
            $k = strtolower($login);
            if (isset($seen[$k])) {
                $sum['rejected']++;
                $this->note($notes, ['login' => $login, 'result' => 'rejected', 'reason' => 'Anmeldename kommt (unabhängig von der Schreibweise) doppelt vor.']);
                continue;
            }
            $seen[$k] = 1;
            $logins[$k] = true;
            if ((string)$u['email'] === '' || filter_var($u['email'], FILTER_VALIDATE_EMAIL) === false) {
                $sum['no_email']++;
                $this->note($notes, ['login' => $login, 'result' => 'warn', 'reason' => 'Keine gültige E-Mail-Adresse – es wird eine Platzhalteradresse (.invalid) eingetragen.']);
            }
            if (isset($existing[$k])) {
                $sum['skip']++;
                $this->note($notes, ['login' => $login, 'result' => 'skip', 'reason' => 'Benutzer existiert bereits in WordPress (wird abgeglichen, nicht neu angelegt).']);
            } else {
                $sum['create']++;
            }
        }
        if ($sum['total'] === 0) {
            $this->add('warn', 'benutzer', 'Es wurden keine lokalen Benutzer gefunden.');
        }
        if ($sum['rejected'] > 0) {
            $this->add('warn', 'benutzer', $sum['rejected'] . ' Benutzer können nicht übernommen werden (siehe Hinweise).');
        }
        return ['sum' => $sum, 'notes' => $notes, 'logins' => $logins];
    }

    /** @param array<string,true> $migrated @return array{sum:array<string,int>,notes:list<array<string,mixed>>,bytes:int} */
    private function media(array $migrated): array
    {
        $names = array_flip($this->probe->available() ? $this->probe->mediaNames() : []);
        $sum = ['total' => 0, 'create' => 0, 'skip' => 0, 'rejected' => 0, 'missing' => 0, 'duplicates' => 0];
        $notes = [];
        $bytes = 0;
        $hashes = [];
        $adapter = new NativeMediaAdapter($this->cmsDir);
        for ($p = 1; $p <= 500; $p++) {
            $r = $adapter->items(['per_page' => 100, 'page' => $p]);
            foreach ($r['items'] as $m) {
                $sum['total']++;
                $name = (string)$m['name'];
                $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
                $file = $this->mediaFile((string)$m['url']);
                if (isset($migrated['media:' . $m['id']])) {
                    $sum['skip']++;
                    continue;
                }
                if (!in_array($ext, self::MEDIA_EXT, true)) {
                    $sum['rejected']++;
                    $this->note($notes, ['name' => $name, 'result' => 'rejected', 'reason' => 'Dateityp .' . $ext . ' ist in der WordPress-Mediathek nicht erlaubt (z. B. SVG, ZIP).']);
                    continue;
                }
                if ($file === null || !is_file($file)) {
                    $sum['missing']++;
                    $this->note($notes, ['name' => $name, 'result' => 'missing', 'reason' => 'Datei fehlt im Dateisystem.']);
                    continue;
                }
                $size = (int)filesize($file);
                if ($size <= 0 || $size > self::MAX_MEDIA) {
                    $sum['rejected']++;
                    $this->note($notes, ['name' => $name, 'result' => 'rejected', 'reason' => $size <= 0 ? 'Datei ist leer.' : 'Datei ist größer als 32 MB.']);
                    continue;
                }
                $h = $size . ':' . md5_file($file);
                if (isset($hashes[$h])) {
                    $sum['duplicates']++;
                    $this->note($notes, ['name' => $name, 'result' => 'warn', 'reason' => 'Inhaltsgleiche Datei wie „' . $hashes[$h] . '“ (wird trotzdem eigenständig übernommen).']);
                }
                $hashes[$h] = $name;
                if (isset($names[strtolower($name)])) {
                    $sum['skip']++;
                    $this->note($notes, ['name' => $name, 'result' => 'skip', 'reason' => 'Gleichnamiges Medium existiert bereits in WordPress.']);
                    continue;
                }
                $sum['create']++;
                $bytes += $size;
            }
            if ($p * 100 >= $r['total']) {
                break;
            }
        }
        if ($sum['missing'] > 0) {
            $this->add('warn', 'medien', $sum['missing'] . ' Medien verweisen auf fehlende Dateien.');
        }
        if ($sum['rejected'] > 0) {
            $this->add('warn', 'medien', $sum['rejected'] . ' Medien werden von WordPress nicht angenommen und bleiben als Datei in cms/media erreichbar.');
        }
        $sum['bytes'] = $bytes;
        return ['sum' => $sum, 'notes' => $notes, 'bytes' => $bytes];
    }

    public function mediaFile(string $url): ?string
    {
        $path = (string)parse_url($url, PHP_URL_PATH);
        $pos = strpos($path, '/media/');
        if ($pos === false) {
            return null;
        }
        $rel = rawurldecode(substr($path, $pos + 7));
        if ($rel === '' || str_contains($rel, '..') || str_contains($rel, "\0")) {
            return null;
        }
        return rtrim($this->cmsDir, '/') . '/media/' . $rel;
    }

    /** @return array{categories:array<string,int>,tags:array<string,int>} */
    private function terms(NativeAdapter $content): array
    {
        $res = [];
        foreach (['category' => 'categories', 'post_tag' => 'tags'] as $tax => $key) {
            $have = array_flip($this->probe->available() ? $this->probe->terms($tax) : []);
            $create = 0;
            $reuse = 0;
            foreach ($content->terms($tax) as $t) {
                isset($have[mb_strtolower($t['name'])]) ? $reuse++ : $create++;
            }
            $res[$key] = ['create' => $create, 'reuse' => $reuse];
        }
        return $res;
    }

    /**
     * @param array<string,string> $slugs @param array<string,true> $migrated @param array<string,true> $logins
     * @return array{sum:array<string,int>,notes:list<array<string,mixed>>,conv:int,fb:int,refs:int,broken:int,redirects:list<array{from:string,to:string}>}
     */
    private function content(NativeAdapter $adapter, string $type, array $slugs, array $migrated, array $logins): array
    {
        $sum = ['total' => 0, 'create' => 0, 'skip' => 0, 'rename' => 0, 'trash' => 0, 'scheduled' => 0, 'drafts' => 0, 'empty_title' => 0, 'unknown_owner' => 0];
        $notes = [];
        $conv = $fb = $refs = $broken = 0;
        $redirects = [];
        $used = $slugs;
        foreach (['all', 'trash'] as $status) {
            for ($p = 1; $p <= 500; $p++) {
                $r = $adapter->items($type, ['status' => $status, 'per_page' => 100, 'page' => $p]);
                foreach ($r['items'] as $it) {
                    $sum['total']++;
                    $label = $type . ':' . $it['id'];
                    $title = trim((string)$it['title']);
                    $slug = (string)$it['slug'];
                    if ($it['status'] === 'trash') {
                        $sum['trash']++;
                    } elseif ($it['status'] === 'scheduled') {
                        $sum['scheduled']++;
                    } elseif ($it['status'] === 'draft') {
                        $sum['drafts']++;
                    }
                    if (isset($migrated[$label])) {
                        $sum['skip']++;
                        continue;
                    }
                    if ($it['status'] === 'trash') {   // Papierkorb wird nicht übernommen
                        continue;
                    }
                    if ($title === '') {
                        $sum['empty_title']++;
                        $this->note($notes, ['id' => (string)$it['id'], 'slug' => $slug, 'result' => 'warn', 'reason' => 'Leerer Titel – es wird „(ohne Titel)“ gesetzt.']);
                    }
                    $owner = (string)($it['owner'] ?? '');
                    if ($owner !== '' && !isset($logins[strtolower($owner)])) {
                        $sum['unknown_owner']++;
                        $this->note($notes, ['id' => (string)$it['id'], 'slug' => $slug, 'result' => 'warn', 'reason' => 'Besitzer „' . $owner . '“ ist kein lokaler Benutzer – der Inhalt wird dem Administrator zugeordnet.']);
                    }
                    $final = $slug === '' ? 'inhalt-' . $it['id'] : $slug;
                    if ($it['status'] !== 'trash' && isset($used[$final])) {
                        $n = 2;
                        while (isset($used[$final . '-' . $n])) {
                            $n++;
                        }
                        $this->note($notes, ['id' => (string)$it['id'], 'slug' => $slug, 'result' => 'rename', 'reason' => 'Adresse „' . $final . '“ ist in WordPress schon vergeben – neue Adresse „' . $final . '-' . $n . '“.']);
                        $final .= '-' . $n;
                        $sum['rename']++;
                    }
                    if ($it['status'] !== 'trash') {
                        $used[$final] = $type;
                    }
                    $sum['create']++;
                    if ($final !== $slug) {
                        $redirects[] = ['from' => '/' . $slug, 'to' => '/' . $final . '/'];
                    }
                    $html = (string)($it['content'] ?? '');
                    $c = Converter::toWp($html);
                    $conv += $c['converted'];
                    $fb += $c['fallback'];
                    if (preg_match_all('#/cms/media/[^"\'\s)<>]+#', $html, $mm) > 0) {
                        foreach (array_unique($mm[0]) as $u) {
                            $refs++;
                            $f = $this->mediaFile($u);
                            if ($f === null || !is_file($f)) {
                                $broken++;
                                $this->note($notes, ['id' => (string)$it['id'], 'slug' => $slug, 'result' => 'warn', 'reason' => 'Verweist auf fehlende Mediendatei ' . $u . '.']);
                            }
                        }
                    }
                }
                if ($p * 100 >= $r['total']) {
                    break;
                }
            }
        }
        if ($broken > 0) {
            $this->add('warn', $type === 'page' ? 'seiten' : 'beitraege', $broken . ' Medienverweise in Inhalten zeigen auf fehlende Dateien.');
        }
        if ($sum['rename'] > 0) {
            $this->add('warn', $type === 'page' ? 'seiten' : 'beitraege', $sum['rename'] . ' Adressen sind in WordPress belegt und werden umbenannt (Weiterleitungen werden eingeplant).');
        }
        return ['sum' => $sum, 'notes' => $notes, 'conv' => $conv, 'fb' => $fb, 'refs' => $refs, 'broken' => $broken, 'redirects' => $redirects];
    }

    /** @return array{sum:array<string,int>,notes:list<array<string,mixed>>} */
    private function menus(NativeAdapter $content): array
    {
        $nav = new NativeNavigationAdapter($this->dataDir);
        $sum = ['menus' => 0, 'items' => 0, 'invalid' => 0, 'too_deep' => 0];
        $notes = [];
        foreach ($nav->menus() as $m) {
            $sum['menus']++;
            $tree = $nav->tree($m['id']);
            $walk = function (array $items, int $depth) use (&$walk, &$sum, &$notes, $m): void {
                foreach ($items as $i) {
                    $sum['items']++;
                    $url = (string)($i['url'] ?? '');
                    if ($depth > 4) {
                        $sum['too_deep']++;
                        $this->note($notes, ['menu' => $m['id'], 'label' => (string)$i['label'], 'result' => 'warn', 'reason' => 'Verschachtelung tiefer als 4 Ebenen – wird auf Ebene 4 abgeflacht.']);
                    }
                    if ($url === '' || preg_match('#^(https?://|/|\#|mailto:|tel:)#i', $url) !== 1) {
                        $sum['invalid']++;
                        $this->note($notes, ['menu' => $m['id'], 'label' => (string)$i['label'], 'result' => 'warn', 'reason' => 'Ziel „' . mb_substr($url, 0, 80) . '“ ist keine gültige Adresse.']);
                    }
                    $walk((array)($i['children'] ?? []), $depth + 1);
                }
            };
            $walk($tree['items'] ?? [], 1);
        }
        if ($sum['invalid'] > 0) {
            $this->add('warn', 'menues', $sum['invalid'] . ' Menüeinträge haben kein gültiges Ziel.');
        }
        return ['sum' => $sum, 'notes' => $notes];
    }

    /** @return array{sum:array<string,int>,notes:list<array<string,mixed>>} */
    private function widgets(): array
    {
        $w = new NativeWidgetAdapter($this->dataDir);
        $sum = ['areas' => 0, 'widgets' => 0, 'manual' => 0];
        $notes = [];
        foreach ($w->areas() as $a) {
            $sum['areas']++;
            foreach ($a['widgets'] as $x) {
                $sum['widgets']++;
                $sum['manual']++;
                $this->note($notes, ['area' => (string)$a['name'], 'widget' => (string)$x['title'], 'result' => 'manual', 'reason' => 'Wird nicht automatisch übernommen; in WordPress als Widget „' . (string)$x['id_base'] . '“ bzw. als Block neu anlegen.']);
            }
        }
        if ($sum['manual'] > 0) {
            $this->add('warn', 'widgets', $sum['manual'] . ' Widgets müssen nach der Migration von Hand eingerichtet werden.');
        }
        return ['sum' => $sum, 'notes' => $notes];
    }

    /** @return array{estimate_bytes:int,data_bytes:int,disk_free:int|null,enough_space:bool} */
    private function backup(int $mediaBytes): array
    {
        $data = 0;
        if (is_dir($this->dataDir)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dataDir, \FilesystemIterator::SKIP_DOTS)) as $f) {
                /** @var \SplFileInfo $f */
                if ($f->isFile() && !str_contains($f->getPathname(), '/.wp-engine/')) {
                    $data += (int)$f->getSize();
                }
            }
        }
        $free = @disk_free_space($this->dataDir);
        $free = $free === false ? null : (int)$free;
        $need = ($data + $mediaBytes) * 2 + 50 * 1048576;   // Sicherung + Kopien in der Mediathek + Puffer
        $enough = $free === null || $free >= $need;
        if (!$enough) {
            $this->add('block', 'speicher', 'Zu wenig freier Speicherplatz: benötigt etwa ' . round($need / 1048576) . ' MB, frei sind ' . round(($free ?? 0) / 1048576) . ' MB.');
        }
        return ['estimate_bytes' => $need, 'data_bytes' => $data, 'disk_free' => $free, 'enough_space' => $enough];
    }
}

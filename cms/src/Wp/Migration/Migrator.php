<?php
declare(strict_types=1);
// cms/src/Wp/Migration/Migrator.php – Echte Migration ElvadoPress → WordPress (läuft im gestarteten WordPress, nur mit ausdrücklicher Freigabe über die API).
// Die bisherigen Daten (cms/data, cms/media) werden NIE verändert – nur gelesen. Alles Angelegte trägt die Lauf-Kennung (_elvado_migration_run) und lässt sich über rollback() entfernen.
// Wiederholbar: Quellkennung (_elvado_source_id: post:ID, page:SLUG, media:ID) verhindert Dubletten. Abbrechbar: Zeitbudget und Fehler stoppen kontrolliert, ein neuer Lauf macht weiter.
// Reihenfolge: Sicherung → Benutzer → Medien → Begriffe → Beiträge → Seiten → Menüs → Prüfung. „Umschalten“ (Verwaltung/Website lesen aus WordPress) gehört NICHT dazu.

namespace Elvado\Wp\Migration;

use Elvado\Wp\Actor;
use Elvado\Wp\Adapter\{NativeAdapter, NativeMediaAdapter, NativeNavigationAdapter, NativeUserAdapter, WordPressAdapter, WordPressMediaAdapter, WordPressNavigationAdapter, WordPressUserAdapter};
use Elvado\Wp\{ContentService, MediaService, NavigationService, UserService};

final class Migrator
{
    public function __construct(
        private readonly string $cmsDir,
        private readonly string $dataDir,
        private readonly string $stateDir,
        private readonly Actor $actor,
        private readonly int $timeBudget = 240,
    ) {
    }

    /** @return array<string,mixed> Protokoll des Laufs */
    public function run(): array
    {
        $t0 = time();
        $probe = new WordPressProbe();
        if (!$probe->available()) {
            throw new \RuntimeException('WordPress ist nicht gestartet.');
        }
        $plan = (new Planner($this->cmsDir, $this->dataDir, $probe, 'active'))->plan();
        if ($plan['verdict'] === 'blocked') {
            throw new \RuntimeException('Die Migration ist blockiert: ' . ($plan['blockers'][0]['message'] ?? 'siehe Trockenlauf'));
        }
        $store = new RunStore($this->stateDir);
        $run = ['id' => RunStore::newId(), 'started_at' => date('c'), 'finished_at' => '', 'status' => 'running', 'actor' => $this->actor->login, 'steps' => [], 'created' => ['users' => [], 'media' => [], 'terms' => [], 'posts' => [], 'pages' => [], 'menus' => []], 'errors' => [], 'backup' => null, 'rolled_back' => false];
        $store->save($run);
        $step = function (string $name, callable $fn) use (&$run, $store, $t0): bool {
            if ($run['status'] !== 'running') {
                return false;
            }
            $r = ['name' => $name, 'status' => 'ok', 'detail' => []];
            try {
                $r['detail'] = $fn($run);
            } catch (\Throwable $e) {
                $r['status'] = 'error';
                $r['detail'] = ['message' => mb_substr($e->getMessage(), 0, 300)];
                $run['status'] = 'failed';
                $run['errors'][] = $name . ': ' . $r['detail']['message'];
            }
            if (isset($r['detail']['partial'])) {
                $run['status'] = 'partial';
                $r['status'] = 'partial';
            }
            $run['steps'][] = $r;
            $store->save($run);
            return $r['status'] === 'ok';
        };
        $deadline = $t0 + $this->timeBudget;
        $content = new ContentService(new WordPressAdapter(), $this->actor);

        $step('Sicherung', function (array &$run): array {
            $b = (new Backup($this->dataDir, $this->stateDir))->create($run['id']);
            $run['backup'] = $b;
            return $b;
        });
        $step('Benutzer', fn(array &$run) => $this->users($run));
        $step('Medien', fn(array &$run) => $this->media($run, $deadline));
        $step('Begriffe', fn(array &$run) => $this->terms($run, $content));
        $step('Beiträge', fn(array &$run) => $this->content($run, $content, 'post', $deadline));
        $step('Seiten', fn(array &$run) => $this->content($run, $content, 'page', $deadline));
        $step('Menüs', fn(array &$run) => $this->menus($run));
        if ($run['status'] === 'running') {
            $step('Prüfung', fn(array &$run) => $this->verify($run));
        }
        if ($run['status'] === 'running') {
            $run['status'] = 'done';
        }
        $run['finished_at'] = date('c');
        $store->save($run);
        return $run;
    }

    /** @param array<string,mixed> $run @return array<string,mixed> */
    private function users(array &$run): array
    {
        $svc = new UserService(new WordPressUserAdapter(), $this->actor);
        $res = $svc->sync(new NativeUserAdapter($this->dataDir));
        foreach ($res['created'] as $login) {
            $u = get_user_by('login', $login);
            if ($u instanceof \WP_User) {
                update_user_meta($u->ID, '_elvado_migration_run', $run['id']);
                $run['created']['users'][] = $u->ID;
            }
        }
        return ['created' => count($res['created']), 'updated' => count($res['updated']), 'unchanged' => $res['unchanged'], 'skipped' => $res['skipped']];
    }

    /** @param array<string,mixed> $run @return array<string,mixed> */
    private function media(array &$run, int $deadline): array
    {
        $svc = new MediaService(new WordPressMediaAdapter(), $this->actor);
        $probe = new WordPressProbe();
        $done = $probe->migrated();
        $names = array_flip($probe->mediaNames());
        $src = new NativeMediaAdapter($this->cmsDir);
        $planner = new Planner($this->cmsDir, $this->dataDir, $probe, 'active');
        $c = ['created' => 0, 'skipped' => 0, 'rejected' => []];
        for ($p = 1; $p <= 500; $p++) {
            $r = $src->items(['per_page' => 100, 'page' => $p]);
            foreach ($r['items'] as $m) {
                $key = 'media:' . $m['id'];
                if (isset($done[$key]) || isset($names[strtolower((string)$m['name'])])) {
                    $c['skipped']++;
                    continue;
                }
                if (time() > $deadline) {
                    return $c + ['partial' => true];
                }
                $file = $planner->mediaFile((string)$m['url']);
                if ($file === null || !is_file($file)) {
                    $c['rejected'][] = ['name' => $m['name'], 'reason' => 'Datei fehlt'];
                    continue;
                }
                $tmp = tempnam(sys_get_temp_dir(), 'epm');   // Kopie: WordPress verschiebt die Datei – das Original bleibt unberührt
                if ($tmp === false || !@copy($file, $tmp)) {
                    $c['rejected'][] = ['name' => $m['name'], 'reason' => 'Datei nicht lesbar'];
                    continue;
                }
                try {
                    $it = $svc->upload($tmp, (string)$m['name'], ['title' => (string)$m['title'], 'alt' => (string)$m['alt']]);
                    update_post_meta((int)$it['id'], '_elvado_source_id', $key);
                    update_post_meta((int)$it['id'], '_elvado_migration_run', $run['id']);
                    $run['created']['media'][] = (int)$it['id'];
                    $names[strtolower((string)$m['name'])] = 1;
                    $c['created']++;
                } catch (\InvalidArgumentException | \RuntimeException $e) {
                    $c['rejected'][] = ['name' => $m['name'], 'reason' => mb_substr($e->getMessage(), 0, 120)];
                } finally {
                    @unlink($tmp);
                }
            }
            if ($p * 100 >= $r['total']) {
                break;
            }
        }
        return $c;
    }

    /** @param array<string,mixed> $run @return array<string,mixed> */
    private function terms(array &$run, ContentService $content): array
    {
        $src = new NativeAdapter($this->cmsDir, $this->dataDir);
        $n = 0;
        foreach (['category', 'post_tag'] as $tax) {
            $have = array_flip(array_map(static fn($t) => mb_strtolower($t['name']), $content->terms($tax === 'post_tag' ? 'tag' : 'category')));
            foreach ($src->terms($tax) as $t) {
                if (isset($have[mb_strtolower($t['name'])])) {
                    continue;
                }
                $new = $content->saveTerm($tax === 'post_tag' ? 'tag' : 'category', ['name' => $t['name']]);
                update_term_meta((int)$new['id'], '_elvado_migration_run', $run['id']);
                $run['created']['terms'][] = [(int)$new['id'], $tax === 'post_tag' ? 'post_tag' : 'category'];
                $n++;
            }
        }
        return ['created' => $n];
    }

    /** @param array<string,mixed> $run @return array<string,mixed> */
    private function content(array &$run, ContentService $content, string $type, int $deadline): array
    {
        $src = new NativeAdapter($this->cmsDir, $this->dataDir);
        $probe = new WordPressProbe();
        $done = $probe->migrated();
        $used = $probe->slugs();
        $c = ['created' => 0, 'skipped' => 0, 'renamed' => 0, 'trash_ignored' => 0];
        $users = array_flip(array_map('strtolower', $probe->users()));
        for ($p = 1; $p <= 500; $p++) {
            $r = $src->items($type, ['status' => 'all', 'per_page' => 100, 'page' => $p]);
            foreach ($r['items'] as $it) {
                $key = $type . ':' . $it['id'];
                if (isset($done[$key])) {
                    $c['skipped']++;
                    continue;
                }
                if (time() > $deadline) {
                    return $c + ['partial' => true];
                }
                $slug = (string)$it['slug'] === '' ? 'inhalt-' . $it['id'] : (string)$it['slug'];
                if (isset($used[$slug])) {
                    $n = 2;
                    while (isset($used[$slug . '-' . $n])) {
                        $n++;
                    }
                    $slug .= '-' . $n;
                    $c['renamed']++;
                }
                $status = (string)$it['status'];
                if ($status === 'scheduled' && strtotime((string)$it['date']) <= time()) {
                    $status = 'published';
                }
                $in = ['title' => trim((string)$it['title']) !== '' ? (string)$it['title'] : '(ohne Titel)', 'slug' => $slug, 'content' => (string)$it['content'], 'excerpt' => (string)$it['excerpt'],
                    'status' => $status, 'date' => (string)$it['date'], 'author' => (string)$it['author']];
                if ($type === 'post') {
                    $in['categories'] = $it['categories'];
                    $in['tags'] = $it['tags'];
                }
                if ((string)$it['owner'] !== '' && isset($users[strtolower((string)$it['owner'])])) {
                    $in['owner'] = (string)$it['owner'];
                }
                $saved = $content->save($type, $in, true);   // Inhalte stammen von Administratoren der bisherigen Verwaltung und wurden dort schon so ausgeliefert
                update_post_meta((int)$saved['id'], '_elvado_source_id', $key);
                update_post_meta((int)$saved['id'], '_elvado_migration_run', $run['id']);
                $run['created'][$type === 'page' ? 'pages' : 'posts'][] = (int)$saved['id'];
                $used[$slug] = $type;
                $c['created']++;
            }
            if ($p * 100 >= $r['total']) {
                break;
            }
        }
        return $c;
    }

    /** @param array<string,mixed> $run @return array<string,mixed> */
    private function menus(array &$run): array
    {
        $nav = new NavigationService(new WordPressNavigationAdapter(), $this->actor);
        $src = new ValidNavigation(new NativeNavigationAdapter($this->dataDir));
        $have = array_flip(array_map(static fn($m) => mb_strtolower($m['name']), $nav->menus()));
        $c = ['created' => 0, 'skipped' => 0, 'items' => 0, 'dropped' => []];
        foreach ($src->menus() as $m) {
            if ($m['count'] === 0 || isset($have[mb_strtolower($m['name'])])) {
                $c['skipped']++;
                continue;
            }
            $r = $nav->importFrom($src, $m['id']);
            $run['created']['menus'][] = (int)$r['menu']['id'];
            $c['created']++;
            $c['items'] += $r['items'];
        }
        $c['dropped'] = $src->dropped;
        return $c;
    }

    /** Prüfung: alles Übernommene ist in WordPress vorhanden. @param array<string,mixed> $run @return array<string,mixed> */
    private function verify(array &$run): array
    {
        $migrated = (new WordPressProbe())->migrated();
        $missing = [];
        $src = new NativeAdapter($this->cmsDir, $this->dataDir);
        foreach (['post', 'page'] as $type) {
            for ($p = 1; $p <= 500; $p++) {
                $r = $src->items($type, ['status' => 'all', 'per_page' => 100, 'page' => $p]);
                foreach ($r['items'] as $it) {
                    if (!isset($migrated[$type . ':' . $it['id']])) {
                        $missing[] = $type . ':' . $it['id'];
                    }
                }
                if ($p * 100 >= $r['total']) {
                    break;
                }
            }
        }
        if ($missing !== []) {
            throw new \RuntimeException(count($missing) . ' Inhalte fehlen in WordPress (z. B. ' . implode(', ', array_slice($missing, 0, 3)) . ').');
        }
        return ['posts_pages_checked' => true, 'missing' => 0];
    }

    /** Alles entfernen, was dieser Lauf angelegt hat (nur Objekte mit passender Lauf-Kennung). @return array<string,int> */
    public function rollback(string $runId): array
    {
        $store = new RunStore($this->stateDir);
        $run = $store->load($runId);
        if ($run === null) {
            throw new \RuntimeException('Diesen Lauf gibt es nicht.');
        }
        if (!empty($run['rolled_back'])) {
            throw new \RuntimeException('Dieser Lauf wurde schon zurückgebaut.');
        }
        $n = ['posts' => 0, 'media' => 0, 'terms' => 0, 'users' => 0, 'menus' => 0];
        foreach (['posts', 'pages', 'media'] as $k) {
            foreach ((array)$run['created'][$k] as $id) {
                if (get_post_meta((int)$id, '_elvado_migration_run', true) === $runId && wp_delete_post((int)$id, true)) {
                    $n[$k === 'media' ? 'media' : 'posts']++;
                }
            }
        }
        foreach ((array)$run['created']['terms'] as [$id, $tax]) {
            if (get_term_meta((int)$id, '_elvado_migration_run', true) === $runId && wp_delete_term((int)$id, (string)$tax) === true) {
                $n['terms']++;
            }
        }
        require_once ABSPATH . 'wp-admin/includes/user.php';
        foreach ((array)$run['created']['users'] as $id) {
            if ((int)$id > 1 && get_user_meta((int)$id, '_elvado_migration_run', true) === $runId && wp_delete_user((int)$id)) {
                $n['users']++;
            }
        }
        foreach ((array)$run['created']['menus'] as $id) {
            if (wp_delete_nav_menu((int)$id) === true) {
                $n['menus']++;
            }
        }
        $run['rolled_back'] = true;
        $run['rolled_back_at'] = date('c');
        $store->save($run);
        return $n;
    }
}

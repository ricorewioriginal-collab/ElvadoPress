<?php
declare(strict_types=1);
// cms/src/Wp/Migration/LiveSync.php – Laufender Abgleich ElvadoPress → WordPress, solange echtes WordPress die Website ausliefert.
//
// Beiträge und Seiten werden weiter in der gewohnten ElvadoPress-Verwaltung bearbeitet (news.json, site.json, cms/content/pages). Ändern sich diese Daten,
// übernimmt der nächste Seitenaufruf die Änderungen in WordPress: neue Einträge anlegen, geänderte aktualisieren, gelöschte in den Papierkorb legen.
// Zuordnung über die Meta _elvado_source_id (wie die Migration), Änderungserkennung über _elvado_source_hash. Inhalte, die nicht aus ElvadoPress stammen
// (z. B. direkt in WordPress angelegt), bleiben unberührt. Nur bei gestartetem WordPress aufrufen.

namespace Elvado\Wp\Migration;

use Elvado\Wp\{Actor, ContentService};
use Elvado\Wp\Adapter\{NativeAdapter, WordPressAdapter};

final class LiveSync
{
    public const OPTION = 'elvado_live_sync';

    public function __construct(private readonly string $cmsDir, private readonly string $dataDir)
    {
    }

    /** Fingerabdruck der Quelldaten (Änderungszeit und Größe) – billig, ohne die Dateien zu lesen. */
    public function fingerprint(): string
    {
        $parts = [];
        foreach ([$this->dataDir . '/news.json', $this->dataDir . '/site.json'] as $f) {
            clearstatcache(true, $f);
            $parts[] = is_file($f) ? filemtime($f) . ':' . filesize($f) : '-';
        }
        foreach (glob($this->cmsDir . '/content/pages/*.md') ?: [] as $f) {
            $parts[] = basename($f) . ':' . filemtime($f) . ':' . filesize($f);
        }
        return md5(implode('|', $parts));
    }

    /** Abgleich nur, wenn sich die Quelldaten seit dem letzten Mal geändert haben. @return array{changed:bool,created:int,updated:int,trashed:int} */
    public function maybeSync(): array
    {
        $fp = $this->fingerprint();
        if ((string)get_option(self::OPTION, '') === $fp) {
            return ['changed' => false, 'created' => 0, 'updated' => 0, 'trashed' => 0];
        }
        $r = $this->sync();
        update_option(self::OPTION, $fp, false);
        return ['changed' => true] + $r;
    }

    /** @return array{created:int,updated:int,trashed:int} */
    public function sync(): array
    {
        // ElvadoPress speichert Zeiten in Ortszeit, WordPress läuft intern in UTC: die Quelldaten in der Zeitzone der Website lesen (sonst gälten Beiträge als „geplant“)
        $prevTz = date_default_timezone_get();
        $tz = (string)get_option('timezone_string');
        if ($tz !== '' && in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            date_default_timezone_set($tz);
        }
        try {
            return $this->run();
        } finally {
            date_default_timezone_set($prevTz);
        }
    }

    /** @return array{created:int,updated:int,trashed:int} */
    private function run(): array
    {
        global $wpdb;
        $map = [];   // source_id → WordPress-ID
        foreach ((array)$wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_elvado_source_id'", ARRAY_A) as $row) {
            $map[(string)$row['meta_value']] = (int)$row['post_id'];
        }
        $svc = new ContentService(new WordPressAdapter(), new Actor('elvadopress', 'admin'));
        $src = new NativeAdapter($this->cmsDir, $this->dataDir);
        $seen = [];
        $c = ['created' => 0, 'updated' => 0, 'trashed' => 0];
        foreach (['post', 'page'] as $type) {
            for ($p = 1; $p <= 500; $p++) {
                $r = $src->items($type, ['status' => 'all', 'per_page' => 100, 'page' => $p]);
                foreach ($r['items'] as $it) {
                    $key = $type . ':' . $it['id'];
                    $seen[$key] = true;
                    $status = (string)$it['status'] === 'scheduled' && strtotime((string)$it['date']) <= time() ? 'published' : (string)$it['status'];
                    $in = ['title' => trim((string)$it['title']) !== '' ? (string)$it['title'] : '(ohne Titel)', 'slug' => (string)$it['slug'] !== '' ? (string)$it['slug'] : 'inhalt-' . $it['id'],
                        'content' => (string)$it['content'], 'excerpt' => (string)$it['excerpt'], 'status' => $status, 'date' => (string)$it['date'], 'author' => (string)$it['author'],
                        'image' => preg_match('~^(https://|/)~', (string)($it['image'] ?? '')) ? (string)$it['image'] : ''];
                    if ($type === 'post') {
                        $in['categories'] = $it['categories'];
                        $in['tags'] = $it['tags'];
                    }
                    $hash = md5((string)json_encode($in));
                    $wpId = $map[$key] ?? 0;
                    if ($wpId > 0 && get_post($wpId) !== null) {
                        if ((string)get_post_meta($wpId, '_elvado_source_hash', true) === $hash && get_post_status($wpId) !== 'trash') {
                            continue;
                        }
                        if (get_post_status($wpId) === 'trash') {
                            wp_untrash_post($wpId);
                        }
                        $svc->save($type, ['id' => (string)$wpId] + $in, true);
                        $c['updated']++;
                    } else {
                        $saved = $svc->save($type, $in, true);
                        $wpId = (int)$saved['id'];
                        update_post_meta($wpId, '_elvado_source_id', $key);
                        $c['created']++;
                    }
                    update_post_meta($wpId, '_elvado_source_hash', $hash);
                }
                if ($p * 100 >= $r['total']) {
                    break;
                }
            }
        }
        // In ElvadoPress gelöscht oder im Papierkorb: in WordPress ebenfalls in den Papierkorb (nicht endgültig löschen)
        foreach ($map as $key => $wpId) {
            if (!isset($seen[$key]) && preg_match('/^(post|page):/', $key) && get_post_status($wpId) && get_post_status($wpId) !== 'trash') {
                wp_trash_post($wpId);
                $c['trashed']++;
            }
        }
        return $c;
    }
}

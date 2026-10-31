<?php
declare(strict_types=1);
// cms/src/Wp/Migration/LiveSync.php – Laufender Abgleich ElvadoPress → WordPress, solange echtes WordPress die Website ausliefert.
//
// Beiträge und Seiten werden weiter in der gewohnten ElvadoPress-Verwaltung bearbeitet (news.json, site.json, cms/content/pages). Ändern sich diese Daten,
// übernimmt der nächste Seitenaufruf die Änderungen in WordPress: neue Einträge anlegen, geänderte aktualisieren, gelöschte in den Papierkorb legen.
// Zuordnung über die Meta _elvado_source_id (wie die Migration), Änderungserkennung über _elvado_source_hash. Inhalte, die nicht aus ElvadoPress stammen
// (z. B. direkt in WordPress angelegt), bleiben unberührt. Nur bei gestartetem WordPress aufrufen.
// Kommentare: ElvadoPress führt sie (comments.json, Freigabe in der Verwaltung). Freigegebene und wartende Kommentare stehen in WordPress (Meta _elvado_comment_id);
// neue Kommentare von Besuchern der WordPress-Website schreibt die Engine-Schicht (mu/elvado-engine.php) nach comments.json. Offen/geschlossen folgt den
// Einstellungen von ElvadoPress (Kommentare an/aus, je Beitrag Standard/offen/geschlossen).

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
        foreach ([$this->dataDir . '/news.json', $this->dataDir . '/site.json', $this->dataDir . '/comments.json'] as $f) {
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

    /** @return array{created:int,updated:int,trashed:int,comments:int} */
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
        $defLang = 'de';   // mehrsprachige Website: Sprache und Übersetzungsgruppe als Meta (_elvado_lang, _elvado_group), ausgewertet in lib/multilang.php
        if (is_file($this->cmsDir . '/lib/multilang.php')) {
            require_once $this->cmsDir . '/lib/multilang.php';
            $site = is_file($this->dataDir . '/site.json') ? json_decode((string)@file_get_contents($this->dataDir . '/site.json'), true) : [];
            $mc = elvado_ml_config(is_array($site) ? $site : []);
            $defLang = $mc['default'];
        }
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
                    $lang = (string)($it['lang'] ?? '') !== '' ? (string)$it['lang'] : $defLang;
                    $group = (string)($it['group'] ?? '');
                    $hash = md5((string)json_encode($in) . '|' . $lang . '|' . $group);
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
                    update_post_meta($wpId, '_elvado_lang', $lang);
                    $group !== '' ? update_post_meta($wpId, '_elvado_group', $group) : delete_post_meta($wpId, '_elvado_group');
                }
                if ($p * 100 >= $r['total']) {
                    break;
                }
            }
        }
        $c['comments'] = $this->syncComments($this->createdMap($seen));
        $this->syncLogo();
        // In ElvadoPress gelöscht oder im Papierkorb: in WordPress ebenfalls in den Papierkorb (nicht endgültig löschen)
        foreach ($map as $key => $wpId) {
            if (!isset($seen[$key]) && preg_match('/^(post|page):/', $key) && get_post_status($wpId) && get_post_status($wpId) !== 'trash') {
                wp_trash_post($wpId);
                $c['trashed']++;
            }
        }
        return $c;
    }

    /** Logo der Website (ElvadoPress → Branding) als Anhang, den Themes über the_custom_logo() zeigen (Filter in mu/elvado-engine.php). */
    private function syncLogo(): void
    {
        $site = is_file($this->dataDir . '/site.json') ? json_decode((string)@file_get_contents($this->dataDir . '/site.json'), true) : [];
        $url = (string)(is_array($site) ? ($site['branding']['portal_logo'] ?? '') : '');
        $id = (int)get_option('elvado_logo_id', 0);
        if ($url === '' || !preg_match('~^(https://|/)[^\s"<>]+\.(png|jpe?g|gif|webp|svg)(\?.*)?$~i', $url)) {
            if ($id > 0) {
                wp_delete_post($id, true);
                delete_option('elvado_logo_id');
            }
            return;
        }
        if ($id > 0 && get_post($id) !== null && (string)get_post_meta($id, '_elvado_image_url', true) === $url) {
            return;
        }
        $ext = strtolower((string)pathinfo((string)parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        $mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml'][$ext] ?? 'image/png';
        if ($id <= 0 || get_post($id) === null) {
            $id = (int)wp_insert_attachment(['post_title' => 'Logo', 'post_mime_type' => $mime, 'post_status' => 'inherit', 'guid' => $url]);
            update_option('elvado_logo_id', $id, false);
        } else {
            wp_update_post(['ID' => $id, 'post_mime_type' => $mime, 'guid' => $url]);
        }
        update_post_meta($id, '_wp_attached_file', 'elvado-logo.' . $ext);
        update_post_meta($id, '_elvado_image_url', $url);
    }

    /** source_id → WordPress-ID für alle abgeglichenen Inhalte (auch die eben angelegten). @param array<string,bool> $seen */
    private function createdMap(array $seen): array
    {
        global $wpdb;
        $m = [];
        foreach ((array)$wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_elvado_source_id'", ARRAY_A) as $row) {
            if (isset($seen[(string)$row['meta_value']])) {
                $m[(string)$row['meta_value']] = (int)$row['post_id'];
            }
        }
        return $m;
    }

    /** Kommentare, Kommentar-Status und Suchmaschinen-Felder der Beiträge abgleichen. @param array<string,int> $map @return int geänderte Kommentare */
    private function syncComments(array $map): int
    {
        $read = fn(string $f) => is_file($f) ? (json_decode((string)@file_get_contents($f), true) ?: []) : [];
        $site = $read($this->dataDir . '/site.json');
        $enabled = !empty($site['comments']['enabled']);
        $n = 0;
        foreach ($read($this->dataDir . '/news.json') as $a) {
            $wpId = $map['post:' . (int)($a['id'] ?? 0)] ?? 0;
            if ($wpId > 0) {   // Suchmaschinen-Felder des Beitrags (noindex, kanonische Adresse, SEO-Titel/-Beschreibung) – ausgegeben über Filter in mu/elvado-engine.php
                foreach (['_elvado_seo_noindex' => !empty($a['noindex']) ? '1' : '', '_elvado_canonical' => (string)($a['canonical_url'] ?? ''), '_elvado_seo_title' => (string)($a['seo_title'] ?? ''), '_elvado_seo_description' => (string)($a['seo_description'] ?? '')] as $mk => $mv) {
                    $mv !== '' ? update_post_meta($wpId, $mk, $mv) : delete_post_meta($wpId, $mk);
                }
                $mode = (string)($a['comments'] ?? 'default');
                $open = $mode === 'open' || ($mode === 'default' && $enabled) ? 'open' : 'closed';
                if (get_post_field('comment_status', $wpId) !== $open) {
                    global $wpdb;
                    $wpdb->update($wpdb->posts, ['comment_status' => $open], ['ID' => $wpId]);
                    clean_post_cache($wpId);
                }
            }
        }
        $have = [];   // ElvadoPress-ID → WordPress-Kommentar
        foreach (get_comments(['meta_key' => '_elvado_comment_id', 'status' => 'all', 'number' => 0, 'type' => 'comment']) as $wc) {
            $have[(int)get_comment_meta((int)$wc->comment_ID, '_elvado_comment_id', true)] = $wc;
        }
        $src = array_values(array_filter((array)$read($this->dataDir . '/comments.json'), 'is_array'));
        usort($src, fn($x, $y) => (int)($x['parent_id'] ?? 0) <=> (int)($y['parent_id'] ?? 0));   // Antworten nach ihren Eltern
        $seen = [];
        foreach ($src as $cm) {
            $id = (int)($cm['id'] ?? 0);
            $wpPost = $map['post:' . (int)($cm['article_id'] ?? 0)] ?? 0;
            if ($id <= 0 || $wpPost <= 0) {
                continue;
            }
            $seen[$id] = true;
            $parent = (int)($cm['parent_id'] ?? 0);
            $data = ['comment_post_ID' => $wpPost, 'comment_author' => (string)($cm['name'] ?? ''), 'comment_content' => (string)($cm['text'] ?? ''),
                'comment_approved' => ($cm['status'] ?? '') === 'approved' ? 1 : 0, 'comment_parent' => $parent > 0 && isset($have[$parent]) ? (int)$have[$parent]->comment_ID : 0,
                'comment_date' => (string)($cm['created_at'] ?? current_time('mysql')), 'user_id' => 0, 'comment_type' => 'comment'];
            $data['comment_date_gmt'] = get_gmt_from_date($data['comment_date']);
            $wc = $have[$id] ?? null;
            if ($wc === null) {
                $GLOBALS['elvado_livesync_comment'] = true;   // kein Rückweg nach comments.json
                $new = wp_insert_comment($data);
                unset($GLOBALS['elvado_livesync_comment']);
                if ($new) {
                    add_comment_meta((int)$new, '_elvado_comment_id', $id, true);
                    $have[$id] = get_comment((int)$new);
                    $n++;
                }
            } elseif ((string)$wc->comment_content !== $data['comment_content'] || (int)$wc->comment_approved !== $data['comment_approved'] || (int)$wc->comment_post_ID !== $wpPost
                || (string)$wc->comment_author !== $data['comment_author'] || (int)$wc->comment_parent !== $data['comment_parent']) {
                wp_update_comment(['comment_ID' => (int)$wc->comment_ID] + $data);
                $n++;
            }
        }
        foreach ($have as $id => $wc) {   // in ElvadoPress gelöscht
            if (!isset($seen[$id]) && $wc) {
                wp_delete_comment((int)$wc->comment_ID, true);
                $n++;
            }
        }
        return $n;
    }
}

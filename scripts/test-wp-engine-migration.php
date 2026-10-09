<?php
// Prüft den Trockenlauf der Migration (Planner, ReportStore, Probes): Bericht korrekt, wiederholbar und – vor allem – es wird nichts verändert.
// Aufruf: php scripts/test-wp-engine-migration.php
declare(strict_types=1);
require __DIR__ . '/../cms/src/autoload.php';
use Elvado\Wp\{Engine, CoreInstaller, DbConfig, Bridge, ContentService};
use Elvado\Wp\Adapter\WordPressAdapter;
use Elvado\Wp\Migration\{Planner, ReportStore, NullProbe, TargetProbe, WordPressProbe};

$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
function rmrf(string $d): void { if (!is_dir($d)) return; foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') continue; $p = "$d/$f"; is_dir($p) && !is_link($p) ? rmrf($p) : @unlink($p); } @rmdir($d); }
/** Prüfsumme aller Dateien (Pfad + Inhalt), ohne den Zustandsordner der Engine. */
function throws(callable $f, string $cls = \Throwable::class): bool { try { $f(); } catch (\Throwable $e) { return $e instanceof $cls; } return false; }
function snap(string $d): string { $h = []; $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS)); foreach ($it as $f) { if ($f->isFile() && !str_contains($f->getPathname(), '/.wp-engine/')) { $h[$f->getPathname()] = md5_file($f->getPathname()); } } ksort($h); return md5(json_encode($h)); }

const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
function mkFixture(string $tmp): void
{
    mkdir("$tmp/cms/data/.wp-engine", 0700, true); mkdir("$tmp/cms/media/library", 0755, true); mkdir("$tmp/cms/content/pages", 0755, true);
    file_put_contents("$tmp/cms/media/library/a.png", base64_decode(PNG));
    file_put_contents("$tmp/cms/media/library/b.png", base64_decode(PNG));   // inhaltsgleich
    file_put_contents("$tmp/cms/media/library/logo.svg", '<svg xmlns="http://www.w3.org/2000/svg"/>');
    file_put_contents("$tmp/cms/media/library/leer.pdf", '');
    $now = date('Y-m-d H:i:s');
    file_put_contents("$tmp/cms/data/news.json", json_encode([
        ['id' => 1, 'slug' => 'eins', 'title' => 'Eins', 'category' => 'Radio', 'tags' => 'Pop', 'body_html' => '<p>A</p><img src="/cms/media/library/a.png"><img src="/cms/media/library/weg.png">', 'status' => 'published', 'published_at' => '2020-01-01 10:00:00', 'author_user' => 'rico'],
        ['id' => 2, 'slug' => 'zwei', 'title' => '', 'category' => 'Neu', 'status' => 'draft', 'published_at' => $now, 'author_user' => 'unbekannt'],
        ['id' => 3, 'slug' => 'belegt', 'title' => 'Belegt', 'status' => 'published', 'published_at' => '2021-01-01 10:00:00'],
        ['id' => 4, 'slug' => 'alt', 'title' => 'Alt', 'status' => 'published', 'published_at' => '2019-01-01 10:00:00', 'deleted_at' => '2022-01-01'],
        ['id' => 5, 'slug' => 'schon', 'title' => 'Schon da', 'status' => 'published', 'published_at' => '2018-01-01 10:00:00'],
    ]));
    file_put_contents("$tmp/cms/data/site.json", json_encode([
        'pages' => [['type' => 'custom', 'slug' => 'ueber-uns', 'title' => 'Über uns', 'enabled' => true, 'blocks_before' => [['type' => 'heading', 'level' => 2, 'text' => 'Wir', 'enabled' => true]], 'blocks_after' => []]],
        'menus' => ['top' => [['id' => 'a', 'label' => 'Start', 'target' => '/'], ['id' => 'b', 'label' => 'Kaputt', 'target' => 'javascript:x'], ['id' => 'c', 'label' => 'Kind', 'target' => '/k', 'parent_id' => 'a']], 'bottom' => []],
        'widgets' => [['id' => 'w1', 'name' => 'Letzte News', 'builtin' => 'news-latest', 'title' => 'News']], 'widget_areas' => [['id' => 'sb', 'name' => 'Seitenleiste', 'widgets' => ['w1']]],
    ]));
    file_put_contents("$tmp/cms/data/local-auth.local.php", '<?php return ' . var_export(['users' => [
        ['username' => 'rico', 'role' => 'admin', 'email' => 'r@example.com', 'password_hash' => 'GEHEIM-HASH'],
        ['username' => 'Rico', 'role' => 'autor', 'email' => 'x@example.com'],
        ['username' => 'bad name!', 'role' => 'autor'],
        ['username' => 'anna', 'role' => 'autor', 'email' => 'kaputt'],
    ]], true) . ';');
}

// ───────── Kindprozess: echtes WordPress, Trockenlauf gegen die echte Datenbank ─────────
if (($argv[1] ?? '') === '--child') {
    $tmp = (string)$argv[2];
    $GLOBALS['elvado_wpe_engine'] = new Engine("$tmp/wp/cms", "$tmp/wp/cms/data");
    $GLOBALS['elvado_wpe_db'] = new DbConfig($GLOBALS['elvado_wpe_engine']);
    $GLOBALS['elvado_wpe_opts'] = ['installing' => true];
    $_SERVER['HTTP_HOST'] = 'example.test';
    require __DIR__ . '/../cms/wp-engine-boot.php';
    $r = Bridge::installSchema('Test', 'test@example.invalid');
    t('WordPress eingerichtet', $r['ok'], $r['message'] ?? '');
    mkFixture("$tmp/nat");
    // Vorhandene WordPress-Inhalte, die Konflikte erzeugen
    $svc = new ContentService(new WordPressAdapter());
    $svc->save('post', ['title' => 'Belegt', 'slug' => 'belegt', 'status' => 'published', 'categories' => ['Radio']], true);
    $svc->save('page', ['title' => 'Über uns', 'slug' => 'ueber-uns', 'status' => 'published'], true);
    $done = $svc->save('post', ['title' => 'Schon da', 'slug' => 'schon-da', 'status' => 'published'], true);
    update_post_meta((int)$done['id'], '_elvado_source_id', 'post:5');
    wp_insert_user(['user_login' => 'anna', 'user_pass' => wp_generate_password(), 'user_email' => 'anna@example.invalid']);
    $probe = new WordPressProbe();
    t('Probe: Ziel verfügbar', $probe->available());
    t('Probe: Adressen', ($probe->slugs()['belegt'] ?? '') === 'post' && ($probe->slugs()['ueber-uns'] ?? '') === 'page', json_encode($probe->slugs()));
    t('Probe: Benutzer', in_array('anna', $probe->users(), true));
    t('Probe: Begriffe', in_array('radio', $probe->terms('category'), true));
    t('Probe: bereits übernommen', ($probe->migrated()['post:5'] ?? false) === true, json_encode($probe->migrated()));
    $count = static function (): string {
        global $wpdb;
        $o = [];
        foreach (['posts', 'postmeta', 'users', 'usermeta', 'terms', 'term_taxonomy', 'term_relationships', 'comments'] as $tb) { $o[$tb] = (string)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->$tb}"); }
        $o['opt'] = (string)$wpdb->get_var("SELECT MD5(GROUP_CONCAT(option_name, option_value ORDER BY option_name)) FROM {$wpdb->options} WHERE option_name NOT LIKE '\\_transient%' AND option_name NOT LIKE '\\_site\\_transient%' AND option_name <> 'cron'");
        return json_encode($o);
    };
    $before = $count();
    $nsnap = snap("$tmp/nat");
    $rep = (new Planner("$tmp/nat/cms", "$tmp/nat/cms/data", $probe, 'active'))->plan();
    t('Echt: nichts in WordPress verändert', $count() === $before, $before . ' ≠ ' . $count());
    t('Echt: native Daten unverändert', snap("$tmp/nat") === $nsnap);
    t('Echt: Ziel wurde geprüft', $rep['engine']['target_checked'] === true);
    t('Echt: Konflikte erkannt (belegt, ueber-uns)', $rep['summary']['posts']['rename'] === 1 && $rep['summary']['pages']['rename'] === 1, json_encode([$rep['summary']['posts'], $rep['summary']['pages']]));
    t('Echt: übernommener Beitrag übersprungen', $rep['summary']['posts']['skip'] === 1);
    t('Echt: vorhandener Benutzer übersprungen, Kategorie wiederverwendet', $rep['summary']['users']['skip'] === 1 && $rep['summary']['terms']['categories']['reuse'] === 1, json_encode([$rep['summary']['users'], $rep['summary']['terms']]));
    // ───── Echte Migration ─────
    $admin = new \Elvado\Wp\Actor('admin', 'admin');
    $state = "$tmp/wp/cms/data/.wp-engine";
    $mig = new \Elvado\Wp\Migration\Migrator("$tmp/nat/cms", "$tmp/nat/cms/data", $state, $admin);
    $tabs = static function (): array { global $wpdb; $o = []; foreach (['posts', 'postmeta', 'users', 'usermeta', 'terms', 'term_taxonomy', 'term_relationships'] as $tb) { $o[$tb] = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->$tb}"); } return $o; };
    $b4 = $tabs();
    $nsnap = snap("$tmp/nat");
    $run = $mig->run();
    t('Lauf abgeschlossen', $run['status'] === 'done', json_encode($run['errors']) . json_encode(array_column($run['steps'], 'status', 'name')));
    t('Sicherung zuerst, mit Daten und SQL', $run['steps'][0]['name'] === 'Sicherung' && is_file($run['backup']['dir'] . '/data/news.json') && str_contains((string)file_get_contents($run['backup']['sql']), 'wptest_posts') && !str_contains((string)file_get_contents($run['backup']['sql']), 'GEHEIM-HASH'));
    t('Native Daten und Medien unverändert', snap("$tmp/nat") === $nsnap);
    $bySrc = static function (string $k): ?\WP_Post { $q = get_posts(['post_type' => 'any', 'post_status' => 'any', 'meta_key' => '_elvado_source_id', 'meta_value' => $k, 'numberposts' => 1]); return $q[0] ?? null; };
    $p1 = $bySrc('post:1'); $p2 = $bySrc('post:2'); $p3 = $bySrc('post:3');
    t('Beiträge übernommen', $p1 && $p2 && $p3 && $p1->post_title === 'Eins' && $p1->post_name === 'eins' && $p1->post_status === 'publish' && $p2->post_status === 'draft', json_encode([$p1 ? $p1->post_name : null]));
    t('Leerer Titel ersetzt', $p2 && $p2->post_title === '(ohne Titel)');
    t('Belegte Adresse umbenannt', $p3 && $p3->post_name === 'belegt-2');
    t('Papierkorb nicht übernommen', $bySrc('post:4') === null);
    t('Bereits übernommener Beitrag nicht doppelt', count(get_posts(['post_type' => 'post', 'post_status' => 'any', 'meta_key' => '_elvado_source_id', 'meta_value' => 'post:5', 'numberposts' => -1])) === 1);
    t('Datum und Kategorien', $p1 && substr($p1->post_date, 0, 10) === '2020-01-01' && in_array('Radio', wp_get_post_terms($p1->ID, 'category', ['fields' => 'names']), true) && in_array('Pop', wp_get_post_terms($p1->ID, 'post_tag', ['fields' => 'names']), true));
    $pg = $bySrc('page:ueber-uns');
    t('Seite übernommen und umbenannt', $pg && $pg->post_type === 'page' && $pg->post_name === 'ueber-uns-2');
    $media = get_posts(['post_type' => 'attachment', 'post_status' => 'any', 'meta_key' => '_elvado_migration_run', 'meta_value' => $run['id'], 'numberposts' => -1]);
    t('Medien: nur gültige Dateien', count($media) === 2 && count(array_filter($run['steps'], fn($x) => $x['name'] === 'Medien' && count($x['detail']['rejected']) === 2)) === 1, json_encode(array_column($media, 'post_title')));
    t('Medien-Original bleibt in cms/media', is_file("$tmp/nat/cms/media/library/a.png"));
    t('Ungültiger Menüeintrag ausgelassen, Rest übernommen', count(array_filter($run['steps'], fn($x) => $x['name'] === 'Menüs' && count($x['detail']['dropped']) === 1 && $x['detail']['items'] === 2)) === 1, json_encode(array_column($run['steps'], 'detail', 'name')['Menüs'] ?? null));
    t('Menü angelegt', in_array('Top Navigation (Desktop)', array_column((new \Elvado\Wp\NavigationService(new \Elvado\Wp\Adapter\WordPressNavigationAdapter(), $admin))->menus(), 'name'), true));
    t('Neuer Benutzer markiert', count($run['created']['users']) === 1 && get_user_meta($run['created']['users'][0], '_elvado_migration_run', true) === $run['id']);
    // Wiederholen: keine Dubletten
    $c1 = $tabs();
    $run2 = $mig->run();
    $d = array_column($run2['steps'], 'detail', 'name');
    t('Wiederholung: nichts doppelt', $run2['status'] === 'done' && $d['Beiträge']['created'] === 0 && $d['Seiten']['created'] === 0 && $d['Medien']['created'] === 0 && $d['Menüs']['created'] === 0, json_encode($d));
    t('Wiederholung: Tabellen unverändert', $tabs() === $c1, json_encode([$c1, $tabs()]));
    // Rückbau des ersten Laufs
    $rb = $mig->rollback($run['id']);
    t('Rückbau entfernt Beiträge/Seiten, Medien, Begriffe, Benutzer, Menü', $rb['posts'] === 3 + 1 && $rb['media'] === 2 && $rb['terms'] >= 1 && $rb['users'] === 1 && $rb['menus'] === 1, json_encode($rb));
    t('Nach Rückbau wie vorher (Zeilenzahlen)', $tabs() === $b4, json_encode([$b4, $tabs()]));
    t('Vorhandene Inhalte bleiben', $bySrc('post:5') !== null && get_page_by_path('belegt', OBJECT, 'post') !== null);
    t('Zweiter Rückbau abgelehnt', throws(fn() => $mig->rollback($run['id']), RuntimeException::class));
    t('Ungültige Lauf-Kennung', throws(fn() => $mig->rollback('../x'), RuntimeException::class));
    // Blockiert ohne Speicher/Engine: Planner-Urteil bleibt maßgeblich
    echo "Kindprozess: {$GLOBALS['n']} Prüfungen, {$GLOBALS['fail']} Fehler\n";
    exit($GLOBALS['fail'] === 0 ? 0 : 1);
}

$tmp = sys_get_temp_dir() . '/wpe-mig-' . bin2hex(random_bytes(4));
mkFixture($tmp);
$probe = new class implements TargetProbe {
    public function available(): bool { return true; }
    public function slugs(): array { return ['belegt' => 'post', 'ueber-uns' => 'page']; }
    public function users(): array { return ['anna']; }
    public function terms(string $taxonomy): array { return $taxonomy === 'category' ? ['radio'] : []; }
    public function migrated(): array { return ['post:5' => true]; }
    public function mediaNames(): array { return ['b.png']; }
};

$before = snap("$tmp/cms");
$rep = (new Planner("$tmp/cms", "$tmp/cms/data", $probe, 'active'))->plan();
t('Dry-Run schreibt nichts (Dateien unverändert)', snap("$tmp/cms") === $before);
t('Bericht markiert Trockenlauf', $rep['dry_run'] === true && $rep['wrote_anything'] === false);
t('Beiträge: Summe', $rep['summary']['posts']['total'] === 5 && $rep['summary']['posts']['trash'] === 1 && $rep['summary']['posts']['drafts'] === 1, json_encode($rep['summary']['posts']));
t('Beiträge: schon übernommen wird übersprungen', $rep['summary']['posts']['skip'] === 1 && $rep['summary']['posts']['create'] === 3);
t('Beiträge: belegte Adresse wird umbenannt', $rep['summary']['posts']['rename'] === 1 && in_array('belegt-2', array_map(fn($x) => preg_match('/„(belegt-2)“/u', $x['reason'], $m) ? $m[1] : '', $rep['notes']['posts']), true));
t('Weiterleitung geplant', in_array(['from' => '/belegt', 'to' => '/belegt-2/'], $rep['redirects_sample'], true), json_encode($rep['redirects_sample']));
t('Leerer Titel und unbekannter Besitzer gemeldet', $rep['summary']['posts']['empty_title'] === 1 && $rep['summary']['posts']['unknown_owner'] === 1);
t('Seite mit belegter Adresse umbenannt', $rep['summary']['pages']['rename'] === 1 && $rep['summary']['pages']['create'] === 1, json_encode($rep['summary']['pages']));
t('Medienverweise: 2 gefunden, 1 defekt', $rep['content']['media_refs'] === 2 && $rep['content']['media_refs_broken'] === 1, json_encode($rep['content']));
t('Block-Zähler vorhanden (native Inhalte sind HTML, ohne ep:-Blöcke)', $rep['content']['blocks_converted'] === 0 && $rep['content']['blocks_fallback'] === 0);
$m = $rep['summary']['media'];
t('Medien: SVG abgelehnt, leere Datei, Duplikat, vorhandener Name', $m['total'] === 4 && $m['rejected'] >= 2 && $m['duplicates'] === 1 && $m['skip'] === 1 && $m['create'] === 1, json_encode($m));
$u = $rep['summary']['users'];
t('Benutzer: doppelt/ungültig abgelehnt, vorhandener übersprungen', $u['total'] === 4 && $u['rejected'] === 2 && $u['skip'] === 1 && $u['create'] === 1 && $u['no_email'] === 1, json_encode($u));
t('Kategorien/Schlagwörter geplant', $rep['summary']['terms']['categories'] === ['create' => 1, 'reuse' => 1] && $rep['summary']['terms']['tags']['create'] === 1, json_encode($rep['summary']['terms']));
t('Menüs: ungültiges Ziel gemeldet', $rep['summary']['menus']['menus'] === 2 && $rep['summary']['menus']['items'] === 3 && $rep['summary']['menus']['invalid'] === 1, json_encode($rep['summary']['menus']));
t('Widgets: manuell', $rep['summary']['widgets']['manual'] === 1);
t('Urteil mit Hinweisen, keine Blocker', $rep['verdict'] === 'ready_with_warnings' && $rep['blockers'] === [], $rep['verdict']);
t('Schritte und Risiken vorhanden, Sicherung zuerst', count($rep['steps']) === 9 && $rep['steps'][0]['title'] === 'Sicherung' && count($rep['risks']) >= 3);
t('Passwort-Hash steht nicht im Bericht', !str_contains(json_encode($rep), 'GEHEIM-HASH'));
t('Speicherabschätzung', $rep['backup']['estimate_bytes'] > 0 && is_bool($rep['backup']['enough_space']));

// Ohne Ziel (Engine nicht aktiv) → blockiert mit klarem Grund
$rep2 = (new Planner("$tmp/cms", "$tmp/cms/data", new NullProbe(), 'off'))->plan();
t('Ohne aktive Engine: blockiert', $rep2['verdict'] === 'blocked' && $rep2['engine']['target_checked'] === false && str_contains($rep2['blockers'][0]['message'], 'nicht aktiv'));
t('Ohne Ziel: nichts schon übernommen/umbenannt', $rep2['summary']['posts']['skip'] === 0 && $rep2['summary']['posts']['rename'] === 0);

// Bericht speichern
$store = new ReportStore("$tmp/cms/data/.wp-engine");
$f = $store->save($rep);
$dir = "$tmp/cms/data/.wp-engine/migration";
t('Bericht gespeichert (0600, Ordner 0700)', is_file("$dir/$f") && (fileperms("$dir/$f") & 0777) === 0600 && (fileperms($dir) & 0777) === 0700);
t('Bericht lesbar', ($store->load())['verdict'] === 'ready_with_warnings' && $store->load($f)['file'] === $f);
t('Pfad außerhalb wird nicht gelesen', $store->load('../../../site.json') === null && $store->load('nope.json') === null);
for ($i = 0; $i < 12; $i++) { $store->save($rep2); }
t('Nur die letzten 10 Berichte bleiben', count($store->names()) === 10);
t('Nativen Daten unverändert (ohne Zustandsordner)', snap("$tmp/cms") === $before);

// Wiederholbar: gleiches Ergebnis
$again = (new Planner("$tmp/cms", "$tmp/cms/data", $probe, 'active'))->plan();
t('Wiederholbar: gleiche Zahlen', $again['summary'] === $rep['summary'] && $again['verdict'] === $rep['verdict']);

// Leeres System: kein Fehler
$e = sys_get_temp_dir() . '/wpe-mig-empty-' . bin2hex(random_bytes(4)); mkdir("$e/cms/data", 0755, true);
$rep3 = (new Planner("$e/cms", "$e/cms/data", new NullProbe(), 'active'))->plan();
t('Leeres System ohne Fehler', $rep3['summary']['posts']['total'] === 0 && $rep3['summary']['media']['total'] === 0);

// ───────── Integration mit echtem WordPress (optional) ─────────
$zip = (string)getenv('WPE_TEST_ZIP'); $dbs = (string)getenv('WPE_TEST_DB');
if ($zip === '' || $dbs === '' || !is_file($zip)) {
    echo "Hinweis: Integrationsteil mit echtem WordPress übersprungen (WPE_TEST_ZIP/WPE_TEST_DB nicht gesetzt).\n";
} else {
    [$h, $name, $u, $pw] = array_pad(explode('|', $dbs), 4, '');
    $z = new ZipArchive(); $z->open($zip);
    preg_match('/\$wp_version\s*=\s*\'([^\']+)\'/', (string)$z->getFromName('wordpress/wp-includes/version.php'), $m); $z->close();
    @mkdir("$tmp/wp/cms/data", 0755, true); @mkdir("$tmp/wp/cms/wp-content", 0755, true);
    $eng = new Engine("$tmp/wp/cms", "$tmp/wp/cms/data");
    $r = (new CoreInstaller($eng))->install($zip, $m[1], sha1_file($zip));
    t('Echter Core eingespielt', $r['ok'], $r['message']);
    $db = new DbConfig($eng);
    $cfg = ['host' => $h, 'name' => $name, 'user' => $u, 'pass' => $pw, 'prefix' => 'wptest_'];
    $tr = $db->test($cfg);
    t('Datenbank erreichbar und leer', $tr['ok'] && !$tr['needs_empty'], $tr['message']);
    if ($r['ok'] && $tr['ok']) {
        $db->save($cfg);
        $eng->save(['db' => ['ready' => true]]);
        $out = []; exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --child ' . escapeshellarg($tmp) . ' 2>&1', $out, $rc);
        foreach ($out as $l) { echo $l . "\n"; }
        t('Kindprozess (echtes WordPress) erfolgreich', $rc === 0);
        $mysqli = @new mysqli($h === 'localhost' ? '127.0.0.1' : explode(':', $h)[0], $u, $pw, $name, (int)(explode(':', $h)[1] ?? 3306));
        if (!$mysqli->connect_errno) {
            $res = $mysqli->query("SHOW TABLES LIKE 'wptest\\_%'");
            while ($res && ($row = $res->fetch_row())) { $mysqli->query('DROP TABLE `' . $mysqli->real_escape_string($row[0]) . '`'); }
        }
    }
}

rmrf($tmp); rmrf($e);
echo $fail === 0 ? "OK: $n Prüfungen bestanden\n" : "$fail von $n Prüfungen fehlgeschlagen\n";
exit($fail === 0 ? 0 : 1);

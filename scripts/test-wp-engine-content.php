<?php
// Prüft die Inhalts-Schicht der WordPress-Engine: ContentService (Eingabeprüfung), NativeAdapter (lesen) und – wenn eine echte WordPress-Umgebung angegeben ist –
// den WordPressAdapter gegen den echten Core. Aufruf: php scripts/test-wp-engine-content.php
// Integrationsteil nur mit: WPE_TEST_ZIP=<wordpress-X.Y.Z.zip> WPE_TEST_DB="host|name|benutzer|passwort" (leere Datenbank, Präfix wptest_); sonst wird er übersprungen.
declare(strict_types=1);
require __DIR__ . '/../cms/src/autoload.php';
use Elvado\Wp\{Engine, ContentService, CoreInstaller, DbConfig, Bridge};
use Elvado\Wp\Adapter\{NativeAdapter, WordPressAdapter};

$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
function rmrf(string $d): void { if (!is_dir($d)) return; foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') continue; $p = "$d/$f"; is_dir($p) && !is_link($p) ? rmrf($p) : @unlink($p); } @rmdir($d); }
function throws(callable $f, string $cls = \Throwable::class): bool { try { $f(); } catch (\Throwable $e) { return $e instanceof $cls; } return false; }

// ───────── Kindprozess: echtes WordPress im globalen Gültigkeitsbereich ─────────
if (($argv[1] ?? '') === '--child') {
    $tmp = (string)$argv[2];
    $GLOBALS['rrw_wpe_engine'] = new Engine("$tmp/cms", "$tmp/cms/data");
    $GLOBALS['rrw_wpe_db'] = new DbConfig($GLOBALS['rrw_wpe_engine']);
    $GLOBALS['rrw_wpe_opts'] = ['installing' => true];
    $_SERVER['HTTP_HOST'] = 'example.test';
    require __DIR__ . '/../cms/wp-engine-boot.php';
    $r = Bridge::installSchema('Test', 'test@example.invalid');
    t('WordPress eingerichtet', $r['ok'], $r['message'] ?? '');
    $wp = new WordPressAdapter();
    $svc = new ContentService($wp);
    t('schreibbar', $wp->writable());
    t('Standard-Kategorie heißt Allgemein', get_term((int)get_option('default_category'), 'category')->name === 'Allgemein');
    t('Nach der Einrichtung keine Beispielinhalte', $svc->list('post', [])['total'] === 0 && $svc->list('page', [])['total'] === 0);

    // Beitrag anlegen (Entwurf ist Standard)
    $p = $svc->save('post', ['title' => 'Erster <b>Beitrag</b>', 'content' => '<p>Hallo <script>alert(1)</script>Welt</p>', 'categories' => ['Radio', 'News'], 'tags' => 'a, b, A', 'author' => 'Rico'], true);
    t('Beitrag angelegt als Entwurf', ctype_digit($p['id']) && $p['status'] === 'draft' && $p['title'] === 'Erster Beitrag', json_encode($p));
    t('Administrator darf HTML (ungefiltert)', str_contains($p['content'], '<script>'));
    t('Kategorien und Schlagwörter', $p['categories'] === ['News', 'Radio'] || $p['categories'] === ['Radio', 'News'], json_encode($p['categories']));
    t('Schlagwörter ohne Doppelte', count($p['tags']) === 2, json_encode($p['tags']));
    t('Autor-Anzeige bleibt erhalten', $p['author'] === 'Rico');
    $pid = $p['id'];
    // Redakteur ohne Recht auf ungefiltertes HTML: Skripte werden entfernt
    $q = $svc->save('post', ['id' => $pid, 'content' => '<p>Neu <script>alert(1)</script><strong>fett</strong></p>'], false);
    t('Ohne Recht wird gefiltert (kses)', !str_contains($q['content'], '<script') && str_contains($q['content'], '<strong>fett</strong>'), $q['content']);
    // Ändern lässt andere Felder unberührt
    $q = $svc->save('post', ['id' => $pid, 'title' => 'Geändert', 'status' => 'published'], true);
    t('Teiländerung: Titel/Status neu, Inhalt und Kategorien bleiben', $q['title'] === 'Geändert' && $q['status'] === 'published' && str_contains($q['content'], 'fett') && count($q['categories']) === 2);
    t('Slug aus Titel', $q['slug'] === 'geaendert' || $q['slug'] === 'geandert', $q['slug']);
    // Planen
    t('Geplant braucht Zukunftsdatum', throws(fn() => $svc->save('post', ['title' => 'x', 'status' => 'scheduled', 'date' => '2001-01-01 10:00'], true), RuntimeException::class));
    $f = $svc->save('post', ['title' => 'Später', 'status' => 'scheduled', 'date' => date('Y-m-d H:i:s', time() + 86400 * 3)], true);
    t('Geplanter Beitrag', $f['status'] === 'scheduled');
    // Seiten mit Elternseite
    $pg = $svc->save('page', ['title' => 'Über uns', 'content' => '<p>Wir</p>', 'status' => 'published'], true);
    $ch = $svc->save('page', ['title' => 'Team', 'status' => 'published', 'parent' => $pg['id']], true);
    t('Seite mit Elternseite', $ch['parent'] === $pg['id'] && $ch['categories'] === []);
    // Listen, Filter, Suche, Seitenweise
    t('Liste Beiträge', $svc->list('post', [])['total'] === 2);
    t('Filter Status', $svc->list('post', ['status' => 'scheduled'])['total'] === 1 && $svc->list('post', ['status' => 'published'])['total'] === 1);
    t('Suche', $svc->list('post', ['search' => 'Geändert'])['total'] === 1 && $svc->list('post', ['search' => 'gibtesnicht'])['total'] === 0);
    t('Filter Kategorie', $svc->list('post', ['category' => 'radio'])['total'] === 1);
    t('Seitenweise', count($svc->list('post', ['per_page' => 1])['items']) === 1 && $svc->list('post', ['per_page' => 1, 'page' => 2])['items'][0]['id'] !== $svc->list('post', ['per_page' => 1])['items'][0]['id']);
    t('Beiträge und Seiten getrennt', $svc->get('page', $pid) === null && $svc->get('post', $pg['id']) === null);
    // Begriffe
    $cats = $svc->terms('category');
    t('Kategorien samt Zähler', count(array_filter($cats, fn($c) => $c['name'] === 'Radio' && $c['count'] === 1)) === 1, json_encode($cats));
    $c = $svc->saveTerm('category', ['name' => 'Musik']);
    $c2 = $svc->saveTerm('category', ['name' => 'Pop', 'parent' => $c['id']]);
    t('Kategorie mit Eltern', $c2['parent'] === $c['id']);
    $c3 = $svc->saveTerm('category', ['id' => $c2['id'], 'name' => 'Pop & Rock']);
    t('Kategorie umbenennen', $c3['name'] === 'Pop &amp; Rock' || $c3['name'] === 'Pop & Rock', $c3['name']);
    t('Standard-Kategorie nicht löschbar', !$svc->deleteTerm('category', (string)get_option('default_category')) || false);
    t('Kategorie löschen', $svc->deleteTerm('category', $c2['id']) === true);
    $tg = $svc->terms('tag');
    t('Schlagwörter', count($tg) === 2);
    // Löschen
    t('In den Papierkorb', $svc->delete('post', $pid, false) && $svc->get('post', $pid)['status'] === 'trash' && $svc->list('post', ['status' => 'trash'])['total'] === 1);
    t('Papierkorb nicht in „alle“', $svc->list('post', [])['total'] === 1);
    t('Endgültig löschen', $svc->delete('post', $pid, true) && $svc->get('post', $pid) === null);
    t('Fremder Typ wird nicht gelöscht', $svc->delete('post', $pg['id'], true) === false && $svc->get('page', $pg['id']) !== null);
    echo $fail ? "KIND: $fail von $n fehlgeschlagen\n" : "KIND: $n von $n bestanden\n";
    exit($fail ? 1 : 0);
}

// ───────── 1) ContentService: Eingabeprüfung (mit Dummy-Adapter) ─────────
$tmp = sys_get_temp_dir() . '/wpc-test-' . bin2hex(random_bytes(4));
mkdir("$tmp/cms/data", 0755, true);
register_shutdown_function(fn() => rmrf($tmp));
final class RecAdapter implements Elvado\Wp\Adapter\ContentAdapter {
    public array $last = [];
    public function name(): string { return 'rec'; }
    public function writable(): bool { return true; }
    public function counts(): array { return []; }
    public function items(string $type, array $q = []): array { $this->last = $q; return ['items' => [], 'total' => 0]; }
    public function item(string $type, string $id): ?array { return null; }
    public function save(string $type, array $data, bool $unfiltered): array { $this->last = $data; return $data; }
    public function delete(string $type, string $id, bool $force): bool { $this->last = [$id, $force]; return true; }
    public function terms(string $taxonomy): array { $this->last = [$taxonomy]; return []; }
    public function saveTerm(string $taxonomy, array $data): array { $this->last = $data; return $data + ['id' => '1', 'name' => '', 'slug' => '', 'count' => 0, 'parent' => '']; }
    public function deleteTerm(string $taxonomy, string $id): bool { return true; }
}
$rec = new RecAdapter(); $svc = new ContentService($rec);
t('Titel nötig', throws(fn() => $svc->save('post', ['content' => 'x'], true), InvalidArgumentException::class));
t('Unbekannter Typ', throws(fn() => $svc->save('menu', ['title' => 'x'], true), InvalidArgumentException::class) && throws(fn() => $svc->list('attachment', []), InvalidArgumentException::class));
t('Titel zu lang', throws(fn() => $svc->save('post', ['title' => str_repeat('a', 201)], true), InvalidArgumentException::class));
t('Status Papierkorb nur über Löschen', throws(fn() => $svc->save('post', ['title' => 'x', 'status' => 'trash'], true), InvalidArgumentException::class) && throws(fn() => $svc->save('post', ['title' => 'x', 'status' => 'foo'], true), InvalidArgumentException::class));
t('Ungültiges Datum', throws(fn() => $svc->save('post', ['title' => 'x', 'date' => 'morgen'], true), InvalidArgumentException::class));
t('Ungültige Kennung', throws(fn() => $svc->save('post', ['id' => '1; DROP', 'title' => 'x'], true), InvalidArgumentException::class));
t('Bildadresse nur https oder /', throws(fn() => $svc->save('post', ['title' => 'x', 'image' => 'javascript:alert(1)'], true), InvalidArgumentException::class) && throws(fn() => $svc->save('post', ['title' => 'x', 'image' => 'http://a.b/c.png'], true), InvalidArgumentException::class));
t('Zu viele Kategorien', throws(fn() => $svc->save('post', ['title' => 'x', 'categories' => array_map('strval', range(1, 31))], true), InvalidArgumentException::class));
$svc->save('post', ['title' => ' <i>Hallo</i> ', 'slug' => 'Größe & Maß!', 'categories' => 'A, a, B', 'date' => '2030-05-01T10:30', 'image' => '/media/a.png', 'excerpt' => '<b>kurz</b>'], true);
t('Bereinigung: Titel, Slug, Datum, Namen', $rec->last['title'] === 'Hallo' && $rec->last['slug'] === 'groesse-mass' && $rec->last['date'] === '2030-05-01 10:30:00' && $rec->last['categories'] === ['A', 'B'] && $rec->last['excerpt'] === 'kurz', json_encode($rec->last));
$svc->save('page', ['title' => 'S', 'categories' => ['X'], 'parent' => '12'], true);
t('Seiten haben keine Kategorien, aber Elternseite', !isset($rec->last['categories']) && $rec->last['parent'] === '12');
$svc->list('post', ['per_page' => 9999, 'page' => -3, 'search' => str_repeat('s', 300)]);
t('Listenparameter begrenzt', $rec->last['per_page'] === 100 && $rec->last['page'] === 1 && mb_strlen($rec->last['search']) === 100);
t('Unbekannter Statusfilter', throws(fn() => $svc->list('post', ['status' => 'x']), InvalidArgumentException::class));
t('Taxonomie-Namen', $svc->terms('tag') === [] && $rec->last === ['post_tag'] && throws(fn() => $svc->terms('nav_menu'), InvalidArgumentException::class));
t('Begriff braucht Namen', throws(fn() => $svc->saveTerm('category', ['slug' => 'x']), InvalidArgumentException::class));
t('Löschen mit ungültiger Kennung', $svc->delete('post', 'abc', true) === false);
// schreibgeschützt
$ro = new ContentService(new NativeAdapter("$tmp/cms", "$tmp/cms/data"));
t('NativeAdapter schreibgeschützt', !$ro->adapter()->writable() && throws(fn() => $ro->save('post', ['title' => 'x'], true), RuntimeException::class) && throws(fn() => $ro->delete('post', '1', true), RuntimeException::class) && throws(fn() => $ro->saveTerm('category', ['name' => 'x']), RuntimeException::class));

// ───────── 2) NativeAdapter: lesen ─────────
$now = date('Y-m-d H:i:s'); $future = date('Y-m-d H:i:s', time() + 86400 * 5);
file_put_contents("$tmp/cms/data/news.json", json_encode([
    ['id' => 1, 'slug' => 'eins', 'title' => 'Eins', 'category' => 'Radio', 'tags' => 'Pop, Rock', 'body_html' => '<p>A</p>', 'status' => 'published', 'published_at' => '2020-01-01 10:00:00', 'author' => 'Rico', 'image_url' => '/m/a.png'],
    ['id' => 2, 'slug' => 'zwei', 'title' => 'Zwei', 'category' => 'Radio', 'status' => 'draft', 'published_at' => $now],
    ['id' => 3, 'slug' => 'drei', 'title' => 'Drei', 'category' => 'News', 'tags' => 'pop', 'status' => 'published', 'published_at' => $future],
    ['id' => 4, 'slug' => 'vier', 'title' => 'Vier', 'status' => 'published', 'published_at' => '2021-01-01 10:00:00', 'deleted_at' => '2022-01-01'],
]));
file_put_contents("$tmp/cms/data/site.json", json_encode(['pages' => [
    ['type' => 'custom', 'slug' => 'ueber-uns', 'title' => 'Über uns', 'enabled' => true, 'blocks_before' => [['type' => 'heading', 'level' => 2, 'text' => 'Wir <b>sind</b>', 'enabled' => true], ['type' => 'text', 'text' => "Zeile1\nZeile2", 'enabled' => true], ['type' => 'text', 'text' => 'aus', 'enabled' => false]], 'blocks_after' => [['type' => 'divider', 'enabled' => true]]],
    ['type' => 'home', 'slug' => 'start', 'title' => 'Start'],
]]));
mkdir("$tmp/cms/content/pages/kontakt", 0755, true);
file_put_contents("$tmp/cms/content/pages/kontakt/page.md", "---\ntitle: \"Kontakt\"\nslug: \"kontakt\"\nenabled: false\n---\n\n## Schreib uns\n\nHallo\nWelt\n\n> Zitat\n");
$na = new NativeAdapter("$tmp/cms", "$tmp/cms/data"); $ns = new ContentService($na);
t('Native: Status-Zuordnung', array_column($ns->list('post', [])['items'], 'status', 'id') === ['3' => 'scheduled', '2' => 'draft', '1' => 'published'] || array_column($ns->list('post', [])['items'], 'status', 'id') == ['1' => 'published', '2' => 'draft', '3' => 'scheduled'], json_encode($ns->list('post', [])));
t('Native: Papierkorb getrennt', $ns->list('post', ['status' => 'trash'])['total'] === 1 && $ns->list('post', [])['total'] === 3);
t('Native: Reihenfolge neu → alt', array_column($ns->list('post', [])['items'], 'id') === ['3', '2', '1']);
$one = $ns->get('post', '1');
t('Native: Felder', $one['categories'] === ['Radio'] && $one['tags'] === ['Pop', 'Rock'] && $one['author'] === 'Rico' && $one['image'] === '/m/a.png' && $one['content'] === '<p>A</p>');
t('Native: Suche und Kategorie', $ns->list('post', ['search' => 'zwei'])['total'] === 1 && $ns->list('post', ['category' => 'radio'])['total'] === 2);
t('Native: Begriffe mit Zähler', $ns->terms('category') === [['id' => 'radio', 'name' => 'Radio', 'slug' => 'radio', 'count' => 2, 'parent' => ''], ['id' => 'news', 'name' => 'News', 'slug' => 'news', 'count' => 1, 'parent' => '']] || count($ns->terms('category')) === 2, json_encode($ns->terms('category')));
t('Native: Schlagwörter ohne Doppelte (Pop/pop)', count($ns->terms('tag')) === 2);
$pgs = array_column($ns->list('page', [])['items'], null, 'id');
t('Native: Seiten aus site.json und Markdown, ohne Startseite', array_keys($pgs) === ['ueber-uns', 'kontakt'] || (isset($pgs['ueber-uns'], $pgs['kontakt']) && count($pgs) === 2), json_encode(array_keys($pgs)));
t('Native: Blöcke → HTML (escaped, ohne ausgeschaltete)', $pgs['ueber-uns']['content'] === "<h2>Wir &lt;b&gt;sind&lt;/b&gt;</h2>\n<p>Zeile1<br />\nZeile2</p>\n<hr>\n", $pgs['ueber-uns']['content']);
t('Native: Markdown → HTML, deaktivierte Seite = Entwurf', $pgs['kontakt']['status'] === 'draft' && str_contains($pgs['kontakt']['content'], '<h2>Schreib uns</h2>') && str_contains($pgs['kontakt']['content'], '<blockquote>'), $pgs['kontakt']['content']);

// ───────── 3) Integration mit echtem WordPress (optional) ─────────
$zip = (string)getenv('WPE_TEST_ZIP'); $dbs = (string)getenv('WPE_TEST_DB');
if ($zip === '' || $dbs === '' || !is_file($zip)) {
    echo "Hinweis: Integrationsteil mit echtem WordPress übersprungen (WPE_TEST_ZIP/WPE_TEST_DB nicht gesetzt).\n";
} else {
    [$h, $name, $u, $pw] = array_pad(explode('|', $dbs), 4, '');
    $z = new ZipArchive(); $z->open($zip);
    preg_match('/\$wp_version\s*=\s*\'([^\']+)\'/', (string)$z->getFromName('wordpress/wp-includes/version.php'), $m); $z->close();
    $eng = new Engine("$tmp/wp/cms", "$tmp/wp/cms/data"); @mkdir("$tmp/wp/cms/data", 0755, true); @mkdir("$tmp/wp/cms/wp-content", 0755, true);
    $r = (new CoreInstaller($eng))->install($zip, $m[1], sha1_file($zip));
    t('Echter Core eingespielt', $r['ok'], $r['message']);
    $db = new DbConfig($eng);
    $cfg = ['host' => $h, 'name' => $name, 'user' => $u, 'pass' => $pw, 'prefix' => 'wptest_'];
    $tr = $db->test($cfg);
    t('Datenbank erreichbar und leer', $tr['ok'] && !$tr['needs_empty'], $tr['message']);
    if ($r['ok'] && $tr['ok']) {
        $db->save($cfg);
        $eng->save(['db' => ['ready' => true]]);
        $out = []; exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --child ' . escapeshellarg("$tmp/wp") . ' 2>&1', $out, $rc);
        foreach ($out as $l) { echo $l . "\n"; }
        t('Kindprozess (echtes WordPress) erfolgreich', $rc === 0);
        $mysqli = @new mysqli($h === 'localhost' ? '127.0.0.1' : explode(':', $h)[0], $u, $pw, $name, (int)(explode(':', $h)[1] ?? 3306));
        if (!$mysqli->connect_errno) {   // Aufräumen: Testtabellen entfernen
            $res = $mysqli->query("SHOW TABLES LIKE 'wptest\\_%'");
            while ($res && ($row = $res->fetch_row())) { $mysqli->query('DROP TABLE `' . $mysqli->real_escape_string($row[0]) . '`'); }
        }
    }
}
echo $fail ? "$fail von $n Prüfungen fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n";
exit($fail ? 1 : 0);

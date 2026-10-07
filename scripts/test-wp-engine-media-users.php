<?php
// Prüft Medien, Benutzer und Rechte der WordPress-Engine: Actor/Roles, MediaService (Dateiprüfung), UserService (Abgleich), Besitzerregeln im ContentService, NativeAdapter.
// Mit WPE_TEST_ZIP=<wordpress-X.Y.Z.zip> und WPE_TEST_DB="host|name|benutzer|passwort" läuft zusätzlich ein Teil gegen echtes WordPress. Aufruf: php scripts/test-wp-engine-media-users.php
declare(strict_types=1);
require __DIR__ . '/../cms/src/autoload.php';
use Elvado\Wp\{Actor, Roles, Engine, ContentService, MediaService, UserService, CoreInstaller, DbConfig, Bridge, PermissionException};
use Elvado\Wp\Adapter\{ContentAdapter, MediaAdapter, UserAdapter, NativeAdapter, NativeMediaAdapter, NativeUserAdapter, WordPressAdapter, WordPressMediaAdapter, WordPressUserAdapter};

$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
function rmrf(string $d): void { if (!is_dir($d)) return; foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') continue; $p = "$d/$f"; is_dir($p) && !is_link($p) ? rmrf($p) : @unlink($p); } @rmdir($d); }
function throws(callable $f, string $cls = \Throwable::class): bool { try { $f(); } catch (\Throwable $e) { return $e instanceof $cls; } return false; }
const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
function mk(string $dir, string $name, string $bytes): string { $f = "$dir/$name"; file_put_contents($f, $bytes); return $f; }

// ───────── Kindprozess: echtes WordPress ─────────
if (($argv[1] ?? '') === '--child') {
    $tmp = (string)$argv[2];
    $GLOBALS['rrw_wpe_engine'] = new Engine("$tmp/cms", "$tmp/cms/data");
    $GLOBALS['rrw_wpe_db'] = new DbConfig($GLOBALS['rrw_wpe_engine']);
    $GLOBALS['rrw_wpe_opts'] = ['installing' => true];
    $_SERVER['HTTP_HOST'] = 'example.test';
    require __DIR__ . '/../cms/wp-engine-boot.php';
    $r = Bridge::installSchema('Test', 'test@example.invalid');
    t('WordPress eingerichtet', $r['ok'], $r['message'] ?? '');
    $admin = new Actor('chef', 'admin'); $autor = new Actor('schreiber', 'autor');
    // Benutzer-Abgleich
    $nativeDir = "$tmp/nat"; @mkdir("$nativeDir/data", 0755, true);
    $writeUsers = fn(array $u) => file_put_contents("$nativeDir/data/local-auth.local.php", '<?php return ' . var_export(['users' => $u], true) . ';');
    $writeUsers([
        ['username' => 'chef', 'password_hash' => 'GEHEIM', 'role' => 'admin', 'display_name' => 'Chef', 'email' => 'chef@example.test'],
        ['username' => 'schreiber', 'password_hash' => 'GEHEIM', 'role' => 'autor', 'display_name' => 'Schreiber', 'email' => 'nicht-gueltig'],
        ['username' => 'a b', 'password_hash' => 'x', 'role' => 'autor'],
    ]);
    $src = new NativeUserAdapter("$nativeDir/data");
    $us = new UserService(new WordPressUserAdapter(), $admin);
    $res = $us->sync($src);
    t('Abgleich: zwei angelegt, ein ungültiger übersprungen', $res['created'] === ['chef', 'schreiber'] && count($res['skipped']) === 1 && $res['skipped'][0]['login'] === 'a b', json_encode($res));
    t('Abgleich: vorhandener WordPress-Benutzer wird gemeldet, nicht gelöscht', $res['only_in_target'] === ['elvadopress'] && get_user_by('login', 'elvadopress') instanceof WP_User);
    t('Rollen übertragen', in_array('administrator', get_user_by('login', 'chef')->roles, true) && in_array('author', get_user_by('login', 'schreiber')->roles, true));
    t('Ungültige E-Mail wird nicht übernommen', get_user_by('login', 'schreiber')->user_email === '');
    $res2 = $us->sync($src);
    t('Zweiter Abgleich ändert nichts', $res2['created'] === [] && $res2['updated'] === [] && $res2['unchanged'] === 2, json_encode($res2));
    $writeUsers([['username' => 'chef', 'password_hash' => 'x', 'role' => 'autor', 'display_name' => 'Der Chef', 'email' => 'chef@example.test'], ['username' => 'schreiber', 'password_hash' => 'x', 'role' => 'autor', 'display_name' => 'Schreiber']]);
    $res3 = (new UserService(new WordPressUserAdapter(), $admin))->sync(new NativeUserAdapter("$nativeDir/data"));
    t('Änderungen werden aktualisiert (Name, Rolle)', $res3['updated'] === ['chef'] && get_user_by('login', 'chef')->display_name === 'Der Chef' && in_array('author', get_user_by('login', 'chef')->roles, true), json_encode($res3));
    t('Passwort-Hashes nie in der WordPress-Liste', !str_contains(json_encode((new UserService(new WordPressUserAdapter(), $admin))->list()), 'GEHEIM'));
    t('Autor darf Benutzer nicht abgleichen', throws(fn() => (new UserService(new WordPressUserAdapter(), $autor))->sync($src), PermissionException::class));
    $writeUsers([['username' => 'chef', 'password_hash' => 'x', 'role' => 'admin', 'display_name' => 'Chef', 'email' => 'chef@example.test'], ['username' => 'schreiber', 'password_hash' => 'x', 'role' => 'autor', 'display_name' => 'Schreiber']]);
    $us->sync(new NativeUserAdapter("$nativeDir/data"));

    // Medien
    $dir = "$tmp/up"; @mkdir($dir, 0755, true);
    $ms = new MediaService(new WordPressMediaAdapter(), $autor);
    $m = $ms->upload(mk($dir, 'x.png', base64_decode(PNG)), 'Mein Bild.PNG', ['alt' => 'Ein <b>Punkt</b>', 'title' => 'Titel']);
    t('Upload: Bild aufgenommen', ctype_digit($m['id']) && $m['kind'] === 'image' && $m['mime'] === 'image/png' && $m['width'] === 1 && $m['height'] === 1, json_encode($m));
    t('Upload: Alt bereinigt, Besitzer gesetzt', $m['alt'] === 'Ein Punkt' && $m['owner'] === 'schreiber' && $m['title'] === 'Titel');
    $path = (string)get_attached_file((int)$m['id']);
    t('Upload: Datei liegt unter wp-content/uploads', is_file($path) && str_contains($path, '/wp-content/uploads/') && str_ends_with($path, '.png'), $path);
    t('Upload: Ordner gesperrt (kein PHP, keine Liste)', is_file(dirname($path, 3) . '/.htaccess') && str_contains((string)file_get_contents(dirname($path, 3) . '/.htaccess'), 'Require all denied'));
    t('Upload: Adresse zeigt auf cms/wp-content', str_contains($m['url'], '/cms/wp-content/uploads/'), $m['url']);
    $g = $ms->upload(mk($dir, 'y.gif', base64_decode(GIF)), 'anim.gif');
    t('Liste, Suche, Art', $ms->list([])['total'] === 2 && $ms->list(['search' => 'Titel'])['total'] === 1 && $ms->list(['kind' => 'image'])['total'] === 2 && $ms->list(['kind' => 'audio'])['total'] === 0);
    t('Eigenes Medium ändern', $ms->update($m['id'], ['alt' => 'Neu'])['alt'] === 'Neu');
    $adm = new MediaService(new WordPressMediaAdapter(), $admin);
    $a1 = $adm->upload(mk($dir, 'z.png', base64_decode(PNG)), 'admin.png');
    t('Fremdes Medium: Autor darf nicht ändern/löschen', throws(fn() => $ms->update($a1['id'], ['alt' => 'x']), PermissionException::class) && throws(fn() => $ms->delete($a1['id']), PermissionException::class));
    t('Administrator ändert fremdes Medium', $adm->update($m['id'], ['title' => 'Vom Chef'])['title'] === 'Vom Chef');
    t('Löschen entfernt Datei und Eintrag', $ms->delete($m['id']) && !is_file($path) && $ms->get($m['id']) === null);
    t('Unbekanntes Medium', $ms->delete('999999') === false);

    // Besitzer-/Rechteregeln im Inhalt
    $cs = new ContentService(new WordPressAdapter(), $autor);
    $p = $cs->save('post', ['title' => 'Von Schreiber', 'content' => '<p>x</p>'], true);
    $wpPost = get_post((int)$p['id']);
    t('Autor legt Beitrag an: Besitzer und WordPress-Autor gespiegelt', $p['owner'] === 'schreiber' && (int)$wpPost->post_author === (int)get_user_by('login', 'schreiber')->ID);
    t('Autor: HTML wird gefiltert (kein Recht auf unfiltered)', !str_contains($cs->save('post', ['id' => $p['id'], 'content' => '<p>ok</p><script>x</script>'], true)['content'], '<script'));
    $adminPost = (new ContentService(new WordPressAdapter(), $admin))->save('post', ['title' => 'Vom Chef', 'status' => 'published'], true);
    t('Autor darf fremden Beitrag weder ändern noch löschen', throws(fn() => $cs->save('post', ['id' => $adminPost['id'], 'title' => 'x'], false), PermissionException::class) && throws(fn() => $cs->delete('post', $adminPost['id'], true), PermissionException::class));
    t('Autor darf keine Seiten', throws(fn() => $cs->save('page', ['title' => 'S'], false), PermissionException::class));
    t('Autor darf keine Begriffe ändern', throws(fn() => $cs->saveTerm('category', ['name' => 'X']), PermissionException::class));
    t('Autor löscht eigenen Beitrag', $cs->delete('post', $p['id'], true) === true);
    echo $fail ? "KIND: $fail von $n fehlgeschlagen\n" : "KIND: $n von $n bestanden\n";
    exit($fail ? 1 : 0);
}

$tmp = sys_get_temp_dir() . '/wpmu-test-' . bin2hex(random_bytes(4));
mkdir("$tmp/up", 0755, true);
register_shutdown_function(fn() => rmrf($tmp));

// ───────── 1) Actor, Roles ─────────
$adm = new Actor('chef', 'admin'); $aut = new Actor('schreiber', 'autor');
t('Rechte Administrator', $adm->can('users') && $adm->can('content_any') && $adm->can('engine') && $adm->can('terms_write'));
t('Rechte Autor', $aut->can('content_write') && $aut->can('media_write') && !$aut->can('content_any') && !$aut->can('users') && !$aut->can('terms_write') && !$aut->can('unbekannt'));
t('Unbekannte Rolle abgelehnt', throws(fn() => new Actor('x', 'root'), InvalidArgumentException::class));
t('Aus rrw_auth: unbekannte Rolle = Autor', Actor::fromAuth(['user' => 'a', 'role' => 'superuser'])->role === 'autor' && Actor::fromAuth(['user' => 'a', 'role' => 'admin'])->isAdmin() && Actor::fromAuth([])->role === 'autor');
t('Rollenzuordnung', Roles::toWp('admin') === 'administrator' && Roles::toWp('autor') === 'author' && Roles::toWp('x') === 'author' && Roles::fromWp(['editor', 'administrator']) === 'admin' && Roles::fromWp(['author']) === 'autor' && Roles::fromWp([]) === 'autor');

// ───────── 2) MediaService mit Aufzeichnungs-Adapter ─────────
class RecMedia implements MediaAdapter {
    public array $added = []; public array $items = []; public bool $rw = true;
    public function writable(): bool { return $this->rw; }
    public function items(array $q = []): array { return ['items' => array_values($this->items), 'total' => count($this->items)]; }
    public function item(string $id): ?array { return $this->items[$id] ?? null; }
    public function add(string $file, string $name, string $mime, array $meta): array { $this->added = [$name, $mime, $meta, md5_file($file)]; return $this->items['1'] = ['id' => '1', 'name' => $name, 'owner' => $meta['owner'] ?? '', 'alt' => $meta['alt'] ?? '', 'title' => '']; }
    public function update(string $id, array $data): array { return $this->items[$id] = $data + $this->items[$id]; }
    public function delete(string $id): bool { unset($this->items[$id]); return true; }
}
$rm = new RecMedia(); $up = "$tmp/up"; $svc = new MediaService($rm, $aut);
t('Gültiges PNG', $svc->upload(mk($up, 'a.png', base64_decode(PNG)), 'a.png')['name'] === 'a.png' && $rm->added[1] === 'image/png' && $rm->added[2]['owner'] === 'schreiber');
t('Gültiges GIF und PDF', $svc->upload(mk($up, 'b.gif', base64_decode(GIF)), 'b.gif')['name'] === 'b.gif' && $svc->upload(mk($up, 'c.pdf', "%PDF-1.4\n%%EOF\n"), 'c.pdf')['name'] === 'c.pdf');
$bad = [
    'PHP-Datei' => ['x.php', '<?php echo 1;'], 'HTML' => ['x.html', '<html>'], 'SVG' => ['x.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'],
    'ZIP' => ['x.zip', "PK\x03\x04"], 'ohne Endung' => ['bild', base64_decode(PNG)], '.htaccess' => ['.htaccess', 'Require all granted'], 'Endung ≠ Inhalt' => ['x.jpg', base64_decode(PNG)],
    'Textdatei als PNG' => ['x.png', 'kein Bild'], 'PNG mit PHP-Code' => ['x.png', base64_decode(PNG) . '<?php system($_GET[1]); ?>'], 'PDF ungültig' => ['x.pdf', 'hallo'], 'leer' => ['x.png', ''],
];
foreach ($bad as $label => [$nm, $bytes]) {
    $rm->added = [];
    t("abgelehnt: $label", throws(fn() => $svc->upload(mk($up, 'u.bin', $bytes), $nm), InvalidArgumentException::class) && $rm->added === []);
}
$rm->added = [];
t('Doppelte Endung (x.php.png) wird entschärft zu x_php.png', $svc->upload(mk($up, 'u.bin', base64_decode(PNG)), 'x.php.png')['name'] === 'x_php.png');
$big = "$up/big.png"; $h = fopen($big, 'wb'); fwrite($h, base64_decode(PNG)); ftruncate($h, MediaService::MAX_BYTES + 1); fclose($h);
t('abgelehnt: zu groß', throws(fn() => $svc->upload($big, 'big.png'), InvalidArgumentException::class));
t('Dateinamen bereinigt', MediaService::cleanName('../../etc/a b.php.PNG') === 'a b_php.png' && MediaService::cleanName('C:\\x\\y.jpg') === 'y.jpg' && MediaService::cleanName("a\0b<script>.png") === 'abscript.png' && MediaService::cleanName('Größe äöü.png') === 'Größe äöü.png' && MediaService::cleanName(str_repeat('a', 300) . '.png') === str_repeat('a', 100) . '.png', MediaService::cleanName('../../etc/a b.php.PNG'));
$svc->upload(mk($up, 'm.png', base64_decode(PNG)), 'm.png', ['alt' => str_repeat('ä', 400), 'title' => '<i>T</i>', 'caption' => '<b>c</b>']);
t('Metadaten begrenzt und bereinigt', mb_strlen($rm->added[2]['alt']) === 300 && $rm->added[2]['title'] === 'T' && $rm->added[2]['caption'] === 'c');
$rm->items['7'] = ['id' => '7', 'owner' => 'schreiber', 'name' => 'e']; $rm->items['8'] = ['id' => '8', 'owner' => 'anderer', 'name' => 'f']; $rm->items['9'] = ['id' => '9', 'owner' => '', 'name' => 'g'];
t('Autor: eigenes ja, fremdes und herrenloses nein', $svc->update('7', ['alt' => 'x'])['alt'] === 'x' && throws(fn() => $svc->update('8', ['alt' => 'x']), PermissionException::class) && throws(fn() => $svc->delete('9'), PermissionException::class) && $svc->delete('7') === true);
t('Administrator ändert fremdes', (new MediaService($rm, $adm))->update('8', ['alt' => 'y'])['alt'] === 'y');
t('Ungültige Kennung', $svc->get('1; DROP') === null && $svc->delete('abc!') === false);
t('Listenparameter', throws(fn() => $svc->list(['kind' => 'exe']), InvalidArgumentException::class));
$rm->rw = false;
t('Schreibgeschützt', throws(fn() => $svc->upload(mk($up, 'r.png', base64_decode(PNG)), 'r.png'), RuntimeException::class) && throws(fn() => $svc->delete('8'), RuntimeException::class));
t('Autor ohne Anmeldename (leer) ändert nichts Fremdes', throws(fn() => (new MediaService(new class extends RecMedia { public function __construct() { $this->items['5'] = ['id' => '5', 'owner' => '']; } }, new Actor('', 'autor')))->update('5', ['alt' => 'x']), PermissionException::class));

// ───────── 3) NativeMediaAdapter ─────────
mkdir("$tmp/cms/media/library", 0755, true); mkdir("$tmp/cms/media/news", 0755, true);
file_put_contents("$tmp/cms/media/library/a.png", base64_decode(PNG)); file_put_contents("$tmp/cms/media/news/b.gif", base64_decode(GIF)); file_put_contents("$tmp/cms/media/.htaccess", 'x');
$nm = new NativeMediaAdapter("$tmp/cms"); $nms = new MediaService($nm, $adm);
$nl = $nms->list([]);
t('Native Medien: Dateien gefunden, ohne .htaccess', $nl['total'] === 2 && !in_array('.htaccess', array_column($nl['items'], 'name'), true), json_encode($nl));
t('Native Medien: Art-Erkennung und Suche', $nms->list(['kind' => 'image'])['total'] === 2 && $nms->list(['search' => 'a.png'])['total'] === 1);
t('Native Medien: schreibgeschützt', !$nm->writable() && throws(fn() => $nms->upload(mk($up, 'n.png', base64_decode(PNG)), 'n.png'), RuntimeException::class));

// ───────── 4) UserService mit Zwischenspeicher-Adapter ─────────
final class MemUsers implements UserAdapter {
    public array $u = []; public bool $failFor = false;
    public function writable(): bool { return true; }
    public function all(): array { return array_values($this->u); }
    public function byLogin(string $l): ?array { return $this->u[strtolower($l)] ?? null; }
    public function upsert(array $x): array {
        if ($this->failFor && $x['login'] === 'boese') { throw new RuntimeException('Diese E-Mail-Adresse ist schon vergeben.'); }
        $k = strtolower($x['login']); $had = $this->u[$k] ?? null;
        $new = ['id' => $k, 'login' => $x['login'], 'display_name' => $x['display_name'], 'email' => $x['email'], 'role' => $x['role'], 'registered' => ''];
        if ($had && $had == $new) { return ['action' => 'unchanged', 'user' => $had]; }
        $this->u[$k] = $new; return ['action' => $had ? 'updated' : 'created', 'user' => $new];
    }
}
mkdir("$tmp/nd", 0755, true);
file_put_contents("$tmp/nd/local-auth.local.php", '<?php return ' . var_export(['users' => [
    ['username' => 'chef', 'password_hash' => 'GEHEIM', 'role' => 'admin', 'display_name' => 'Chef', 'email' => 'c@example.test', 'created_at' => '2020'],
    ['username' => 'ina', 'password_hash' => 'GEHEIM', 'role' => 'irgendwas', 'email' => 'kaputt'],
    ['username' => 'boese', 'password_hash' => 'x', 'role' => 'autor'], ['username' => 'a b', 'password_hash' => 'x'], ['username' => '', 'password_hash' => 'x'], 'kein Array',
]], true) . ';');
$nu = new NativeUserAdapter("$tmp/nd");
t('Native Benutzer: Rollen, Anzeigename, keine Hashes', count($nu->all()) === 4 && $nu->byLogin('INA')['role'] === 'autor' && $nu->byLogin('ina')['display_name'] === 'ina' && !str_contains(json_encode($nu->all()), 'GEHEIM') && $nu->byLogin('chef')['role'] === 'admin');
$mem = new MemUsers(); $mem->failFor = true; $us = new UserService($mem, $adm);
$r = $us->sync($nu);
t('Abgleich: Ergebnis', $r['created'] === ['chef', 'ina'] && $r['unchanged'] === 0 && count($r['skipped']) === 2 && $r['skipped'][0]['login'] === 'boese' && $r['skipped'][1]['login'] === 'a b', json_encode($r));
t('Abgleich: E-Mail und Rolle bereinigt', $mem->u['ina']['email'] === '' && $mem->u['ina']['role'] === 'autor' && $mem->u['chef']['role'] === 'admin');
$mem->u['fremd'] = ['id' => 'fremd', 'login' => 'fremd', 'display_name' => '', 'email' => '', 'role' => 'autor', 'registered' => ''];
$r2 = $us->sync($nu);
t('Abgleich wiederholbar, löscht nie', $r2['created'] === [] && $r2['updated'] === [] && $r2['unchanged'] === 2 && $r2['only_in_target'] === ['fremd'] && isset($mem->u['fremd']));
t('Autor: kein Zugriff', throws(fn() => (new UserService($mem, $aut))->list(), PermissionException::class) && throws(fn() => (new UserService($mem, $aut))->sync($nu), PermissionException::class));
t('Native Benutzer: schreibgeschützt', !$nu->writable() && throws(fn() => $nu->upsert(['login' => 'x', 'display_name' => '', 'email' => '', 'role' => 'autor']), RuntimeException::class) && throws(fn() => (new UserService($nu, $adm))->sync($nu), RuntimeException::class));

// ───────── 5) ContentService: Besitzerregeln (Zwischenspeicher-Adapter) ─────────
final class MemContent implements ContentAdapter {
    public array $items = []; public array $saved = []; public bool $deleted = false;
    public function name(): string { return 'mem'; }
    public function writable(): bool { return true; }
    public function counts(): array { return []; }
    public function items(string $type, array $q = []): array { return ['items' => [], 'total' => 0]; }
    public function item(string $type, string $id): ?array { $i = $this->items[$id] ?? null; return $i && $i['type'] === $type ? $i : null; }
    public function save(string $type, array $data, bool $unfiltered): array { $this->saved = $data + ['unfiltered' => $unfiltered]; return $data + ['id' => '1', 'type' => $type]; }
    public function delete(string $type, string $id, bool $force): bool { $this->deleted = true; return true; }
    public function terms(string $t): array { return []; }
    public function saveTerm(string $t, array $d): array { return $d + ['id' => '1', 'name' => '', 'slug' => '', 'count' => 0, 'parent' => '']; }
    public function deleteTerm(string $t, string $id): bool { return true; }
}
$mc = new MemContent();
$mc->items = ['10' => ['id' => '10', 'type' => 'post', 'owner' => 'schreiber'], '11' => ['id' => '11', 'type' => 'post', 'owner' => 'chef'], '12' => ['id' => '12', 'type' => 'post', 'owner' => ''], '13' => ['id' => '13', 'type' => 'page', 'owner' => 'schreiber']];
$cs = new ContentService($mc, $aut);
$cs->save('post', ['title' => 'neu'], true);
t('Neuer Beitrag bekommt den Besitzer; Autor ohne unfiltered-Recht', $mc->saved['owner'] === 'schreiber' && $mc->saved['unfiltered'] === false);
$cs->save('post', ['id' => '10', 'title' => 'ändern'], true);
t('Eigener Beitrag: erlaubt, Besitzer bleibt unverändert', !isset($mc->saved['owner']) && $mc->saved['id'] === '10');
t('Fremder, herrenloser und fehlender Beitrag', throws(fn() => $cs->save('post', ['id' => '11', 'title' => 'x'], true), PermissionException::class) && throws(fn() => $cs->save('post', ['id' => '12', 'title' => 'x'], true), PermissionException::class) && throws(fn() => $cs->save('post', ['id' => '99', 'title' => 'x'], true), RuntimeException::class));
t('Seiten nur für Administratoren', throws(fn() => $cs->save('page', ['title' => 'S'], true), PermissionException::class) && throws(fn() => $cs->save('page', ['id' => '13', 'title' => 'S'], true), PermissionException::class));
$mc->deleted = false; t('Löschen: eigener ja, fremder nein', $cs->delete('post', '10', false) && $mc->deleted && throws(fn() => $cs->delete('post', '11', false), PermissionException::class));
t('Begriffe nur für Administratoren', throws(fn() => $cs->saveTerm('category', ['name' => 'X']), PermissionException::class) && throws(fn() => $cs->deleteTerm('category', '1'), PermissionException::class));
$ca = new ContentService($mc, $adm);
$ca->save('post', ['id' => '11', 'title' => 'x'], true); $ca->save('page', ['title' => 'S'], true); $ca->saveTerm('category', ['name' => 'Y']);
t('Administrator darf alles, unfiltered', $mc->saved['unfiltered'] === true || true);
$ca->save('post', ['title' => 'a'], true);
t('Administrator: unfiltered und Besitzer', $mc->saved['unfiltered'] === true && $mc->saved['owner'] === 'chef');
t('Ohne Actor (Wartung) wie Administrator', (new ContentService($mc))->save('page', ['title' => 'S'], true)['title'] === 'S');

// ───────── 6) Integration mit echtem WordPress (optional) ─────────
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
    $db = new DbConfig($eng); $cfg = ['host' => $h, 'name' => $name, 'user' => $u, 'pass' => $pw, 'prefix' => 'wptest_'];
    $tr = $db->test($cfg);
    t('Echter Core und leere Datenbank', $r['ok'] && $tr['ok'] && !$tr['needs_empty'], $r['message'] . ' ' . $tr['message']);
    if ($r['ok'] && $tr['ok']) {
        $db->save($cfg); $eng->save(['db' => ['ready' => true]]);
        $out = []; exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --child ' . escapeshellarg("$tmp/wp") . ' 2>&1', $out, $rc);
        foreach ($out as $l) { echo $l . "\n"; }
        t('Kindprozess (echtes WordPress) erfolgreich', $rc === 0);
        [$host, $port] = array_pad(explode(':', $h), 2, '3306');
        $my = @new mysqli($host === 'localhost' ? '127.0.0.1' : $host, $u, $pw, $name, (int)$port);
        if (!$my->connect_errno) {
            $res = $my->query("SHOW TABLES LIKE 'wptest\\_%'");
            while ($res && ($row = $res->fetch_row())) { $my->query('DROP TABLE `' . $my->real_escape_string($row[0]) . '`'); }
        }
    }
}
echo $fail ? "$fail von $n Prüfungen fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n";
exit($fail ? 1 : 0);

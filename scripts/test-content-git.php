<?php
// Inhalte über Git: cms/content/pages und cms/content/posts werden in die Website übernommen und nicht mehr überschrieben. Aufruf: php scripts/test-content-git.php
declare(strict_types=1);
$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
$tmp = sys_get_temp_dir() . '/content-git-' . bin2hex(random_bytes(4));
mkdir("$tmp/content/pages", 0775, true); mkdir("$tmp/content/posts", 0775, true); mkdir("$tmp/data", 0775, true);
define('RRW_CONTENT_DIR', "$tmp/content/pages"); define('RRW_CONTENT_POSTS_DIR', "$tmp/content/posts"); define('RRW_CONTENT_MANIFEST', "$tmp/data/content-sync.json");
register_shutdown_function(function () use ($tmp) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); } @rmdir($tmp); });
foreach (['publish', 'content'] as $l) { require __DIR__ . '/../cms/lib/' . $l . '.php'; }
$newsFile = "$tmp/data/news.json";
$page = fn(string $slug, string $title, string $body, bool $on = true) => ['id' => 'p_' . $slug, 'type' => 'custom', 'slug' => $slug, 'title' => $title, 'enabled' => $on, 'system_target' => '', 'native_enabled' => false, 'text_overrides' => [], 'blocks_before' => rrw_content_markdown_blocks($body), 'blocks_after' => []];
$site = ['pages' => [$page('alt', 'Alte Seite', 'Alter Text')]];
// Altbestand: veraltete Spiegeldatei im Repository – der erste Lauf merkt sich nur den Stand (nichts wird übernommen)
@mkdir(RRW_CONTENT_DIR . '/alt', 0775, true); file_put_contents(RRW_CONTENT_DIR . '/alt/page.md', "---\ntitle: \"Alte Seite\"\nslug: \"alt\"\nenabled: true\n---\n\nVeralteter Stand aus Git\n");
$x = rrw_content_pull($site, $newsFile);
t('Erster Lauf: nur merken, Website-Stand bleibt', $x['baseline'] && $x['imported'] === [] && ($x['site']['pages'][0]['blocks_before'][0]['text'] ?? '') === 'Alter Text');
// 1) Neue Seite per Git
@mkdir(RRW_CONTENT_DIR . '/neu', 0775, true); file_put_contents(RRW_CONTENT_DIR . '/neu/page.md', "---\ntitle: \"Neue Seite\"\nslug: \"neu\"\nenabled: true\nheadline: \"Hallo\"\n---\n\n## Abschnitt\n\nText aus Git\n");
$x = rrw_content_pull($site, $newsFile); $site = $x['site'];
$neu = array_values(array_filter($site['pages'], fn($p) => $p['slug'] === 'neu'))[0] ?? null;
t('Neue Seite aus Git wird angelegt und ist aktiv', $x['imported'] === ['page:neu'] && $neu !== null && $neu['enabled'] === true && $neu['headline'] === 'Hallo');
// 2) Änderung an bestehender Seite per Git (z. B. aktiviert/deaktiviert, Text)
file_put_contents(RRW_CONTENT_DIR . '/neu/page.md', "---\ntitle: \"Neue Seite\"\nslug: \"neu\"\nenabled: false\n---\n\nNur noch ein Satz\n");
$x = rrw_content_pull($site, $newsFile); $site = $x['site'];
$neu = array_values(array_filter($site['pages'], fn($p) => $p['slug'] === 'neu'))[0];
t('Geänderte Datei wird übernommen (deaktiviert, neuer Text)', $x['imported'] === ['page:neu'] && $neu['enabled'] === false && ($neu['blocks_before'][0]['text'] ?? '') === 'Nur noch ein Satz');
t('Unveränderte Datei: nichts zu tun', rrw_content_pull($site, $newsFile)['imported'] === []);
// 3) Der Spiegel Website → Datei überschreibt keine ungeprüfte Änderung aus Git
file_put_contents(RRW_CONTENT_DIR . '/neu/page.md', "---\ntitle: \"Neue Seite\"\nslug: \"neu\"\nenabled: true\n---\n\nGit-Änderung, noch nicht übernommen\n");
rrw_content_sync_from_site($site);
t('Export überschreibt eine von außen geänderte Datei nicht', str_contains((string)file_get_contents(RRW_CONTENT_DIR . '/neu/page.md'), 'Git-Änderung, noch nicht übernommen'));
$x = rrw_content_pull($site, $newsFile); $site = $x['site'];
t('…sie wird beim nächsten Abgleich übernommen', $x['imported'] === ['page:neu'] && array_values(array_filter($site['pages'], fn($p) => $p['slug'] === 'neu'))[0]['enabled'] === true);
rrw_content_sync_from_site($site);
t('Danach darf der Export schreiben (Datei im Soll-Format, Stand gemerkt)', str_contains((string)file_get_contents(RRW_CONTENT_DIR . '/neu/page.md'), 'Git-Änderung, noch nicht übernommen') && rrw_content_pull($site, $newsFile)['imported'] === []);
// 4) Beiträge per Git
@mkdir(RRW_CONTENT_POSTS_DIR . '/mein-beitrag', 0775, true);
file_put_contents(RRW_CONTENT_POSTS_DIR . '/mein-beitrag/post.md', "---\ntitle: \"Mein Beitrag\"\nstatus: published\ncategory: \"Musik\"\ntags: \"a, b\"\nexcerpt: \"Kurz\"\npublished_at: \"2026-01-02 10:00\"\n---\n\n## Zwischentitel\n\nText mit **fett** und [Link](https://example.org).\n\n- Eins\n- Zwei\n");
file_put_contents($newsFile, json_encode([['id' => 7, 'slug' => 'vorhanden', 'title' => 'Vorhanden', 'status' => 'published', 'body_html' => '<p>x</p>']]));
$x = rrw_content_pull($site, $newsFile);
$news = json_decode((string)file_get_contents($newsFile), true); $mb = array_values(array_filter($news, fn($a) => $a['slug'] === 'mein-beitrag'))[0] ?? null;
t('Beitrag aus Git wird angelegt (neue ID, veröffentlicht, Datum, HTML)', in_array('post:mein-beitrag', $x['imported'], true) && $mb !== null && $mb['id'] === 8 && $mb['status'] === 'published' && $mb['published_at'] === '2026-01-02 10:00:00' && str_contains($mb['body_html'], '<h2>Zwischentitel</h2>') && str_contains($mb['body_html'], '<strong>fett</strong>') && str_contains($mb['body_html'], '<a href="https://example.org">Link</a>') && str_contains($mb['body_html'], '<ul><li>Eins</li><li>Zwei</li></ul>'));
t('Vorhandene Beiträge bleiben unberührt', count($news) === 2 && $news[0]['slug'] === 'vorhanden');
t('Beitrag ohne Status bleibt Entwurf (nie versehentlich öffentlich)', (function () use ($newsFile, $site) { @mkdir(RRW_CONTENT_POSTS_DIR . '/entwurf', 0775, true); file_put_contents(RRW_CONTENT_POSTS_DIR . '/entwurf/post.md', "---\ntitle: \"E\"\n---\n\nText\n"); rrw_content_pull($site, $newsFile); $a = array_values(array_filter(json_decode((string)file_get_contents($newsFile), true), fn($a) => $a['slug'] === 'entwurf'))[0]; return $a['status'] === 'draft'; })());
t('Gleiche Datei erneut: keine Doppelung', (function () use ($newsFile, $site) { rrw_content_pull($site, $newsFile); return count(array_filter(json_decode((string)file_get_contents($newsFile), true), fn($a) => $a['slug'] === 'mein-beitrag')) === 1; })());
t('HTML im Beitrag wird bereinigt', str_contains(rrw_content_md_to_html('<p>Hallo</p><script>alert(1)</script>'), '<p>Hallo</p>') && !str_contains(rrw_content_md_to_html('<p>Hallo</p><script>alert(1)</script>'), '<script'));
t('Verdrahtung: Veröffentlichen, Neuaufbau (rebuild.php) und API übernehmen Dateien', str_contains((string)file_get_contents(__DIR__ . '/../cms/lib/publish.php'), 'rrw_content_pull($site)') && str_contains((string)file_get_contents(__DIR__ . '/../cms/api.php'), "'content_pull'"));
echo $fail ? "$fail von $n Prüfungen fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n";
exit($fail ? 1 : 0);

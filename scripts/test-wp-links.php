<?php
// Prüft die Link-Struktur und die Startseite der Verwaltung (cms/wp/links-api.php) auf echtem WordPress: Vorlagen, eigene Struktur, Prüfungen,
// Kategorie-/Schlagwort-Basis, Erreichbarkeit der Beiträge nach dem Umstellen (Umschreibregeln), Startseite/Beitragsseite. Aufruf: php scripts/test-wp-links.php (mit WPE_TEST_ZIP)
declare(strict_types=1);
require_once __DIR__ . '/wpe-render.php';
$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
$news = [['id' => 1, 'slug' => 'erster', 'title' => 'Erster', 'category' => 'News', 'tags' => '', 'excerpt' => 'A', 'body_html' => '<p>Inhalt eins</p>', 'status' => 'published', 'published_at' => '2026-03-10 10:00:00', 'author' => 'Anna']];
$env = wpe_render_env(['news.json' => $news, 'site.json' => ['portal' => ['site_name' => 'Linktest']]]);
if (!$env) { echo "Hinweis: übersprungen (WPE_TEST_ZIP nicht gesetzt).\n0 von 0 Prüfungen bestanden\n"; exit(0); }
$save = fn(array $in) => (array)wpe_render_php($env, 'require_once ELVADO_CMS_DIR."/lib/wp-real-compat.php";require_once ELVADO_CMS_DIR."/wp/links-api.php";'
    . 'require_once ELVADO_CMS_DIR."/lib/wp-real-admin.php";foreach(["permalink_structure","category_base","tag_base"] as $o){ add_action("update_option_".$o,"elvado_wpra_flush_later");add_action("add_option_".$o,"elvado_wpra_flush_later"); }'
    . '$out=elvado_wpl_save(' . var_export($in, true) . ');');

$r = $save(['preset' => 'day']);
t('Vorlage „Tag und Name“ gespeichert', ($r['ok'] ?? false) && $r['structure'] === '/%year%/%monthnum%/%day%/%postname%/' && str_contains((string)$r['sample'], '/2026/03/10/erster/'), json_encode($r));
$g = wpe_render_get($env, '/2026/03/10/erster/');
t('Beitrag unter der neuen Adresse erreichbar', $g['status'] === 200 && str_contains($g['body'], 'Inhalt eins'), (string)$g['status']);
$r = $save(['structure' => '/blog/%postname%', 'category_base' => 'thema', 'tag_base' => 'stichwort']);
t('Eigene Struktur bekommt den abschließenden Schrägstrich, Basen gespeichert', ($r['ok'] ?? false) && $r['structure'] === '/blog/%postname%/' && $r['category_base'] === 'thema' && $r['tag_base'] === 'stichwort', json_encode($r));
t('Beitrag unter /blog/ erreichbar', wpe_render_get($env, '/blog/erster/')['status'] === 200);
t('Kategorie unter der eigenen Basis erreichbar', wpe_render_get($env, '/thema/news/')['status'] === 200);
t('Reservierter erster Teil wird abgelehnt', !($save(['structure' => '/wp-admin/%postname%/'])['ok'] ?? true));
t('Struktur ohne %postname%/%post_id% wird abgelehnt', !($save(['structure' => '/%year%/'])['ok'] ?? true));
t('Unbekannter Platzhalter wird abgelehnt', !($save(['structure' => '/%gibtsnicht%/%postname%/'])['ok'] ?? true));
t('Gleiche Kategorie- und Schlagwort-Basis wird abgelehnt', !($save(['preset' => 'post', 'category_base' => 'x', 'tag_base' => 'x'])['ok'] ?? true));
$r = $save(['preset' => 'plain']);
t('Einfach: ?p=', ($r['ok'] ?? false) && $r['structure'] === '' && str_contains((string)$r['sample'], '?p='));
$r = $save(['preset' => 'post']);
t('Zurück auf Beitragsname', ($r['ok'] ?? false) && wpe_render_get($env, '/erster/')['status'] === 200);
// Startseite/Beitragsseite
$rd = (array)wpe_render_php($env, 'require_once ELVADO_CMS_DIR."/lib/wp-real-compat.php";require_once ELVADO_CMS_DIR."/wp/links-api.php";$a=wp_insert_post(["post_type"=>"page","post_title"=>"Start","post_status"=>"publish"]);$b=wp_insert_post(["post_type"=>"page","post_title"=>"Blog","post_status"=>"publish"]);'
    . '$e1=elvado_wpl_reading_save(["show_on_front"=>"page","page_on_front"=>$a,"page_for_posts"=>$a]);$ok=elvado_wpl_reading_save(["show_on_front"=>"page","page_on_front"=>$a,"page_for_posts"=>$b]);$out=[$e1["ok"],$ok["ok"],get_option("show_on_front"),(int)get_option("page_on_front")===$a];');
t('Startseite: gleiche Seite für Start und Beiträge abgelehnt, gültige Auswahl gespeichert', ($rd[0] ?? true) === false && ($rd[1] ?? false) === true && ($rd[2] ?? '') === 'page' && ($rd[3] ?? false) === true, json_encode($rd));
echo $fail ? "$fail von $n Prüfungen fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n";
exit($fail ? 1 : 0);

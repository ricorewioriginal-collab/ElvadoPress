<?php
// Mehrsprachigkeit: Website-Inhalte (lib/multilang.php, news_translate, Auslieferung /en/…) und Sprache der Verwaltung (assets/i18n.js + Wörterbuch).
declare(strict_types=1);
$root = dirname(__DIR__);
$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
define('ELVADO_SYSTEM_FILE', sys_get_temp_dir() . '/ml-sys-' . bin2hex(random_bytes(3)) . '.json');
file_put_contents(ELVADO_SYSTEM_FILE, json_encode(['language' => 'de', 'timezone' => 'Europe/Berlin']));
require $root . '/cms/lib/system.php';
require $root . '/cms/lib/multilang.php';

// Konfiguration
$off = elvado_ml_config([]);
t('Ohne Einstellung aus, Standardsprache aus der Einrichtung', !$off['enabled'] && $off['default'] === 'de' && $off['languages'] === ['de']);
$cfg = elvado_ml_config(['multilang' => ['enabled' => true, 'languages' => ['en', 'xx', 'EN', 'fr'], 'switcher' => false]]);
t('Sprachen bereinigt, Standard zuerst, Unbekanntes verworfen', $cfg['enabled'] && $cfg['languages'] === ['de', 'en', 'fr'] && !$cfg['switcher'], json_encode($cfg));
t('Nur Standardsprache = aus', !elvado_ml_config(['multilang' => ['enabled' => true, 'languages' => ['de']]])['enabled']);
t('Eingaben der Verwaltung bereinigt', elvado_ml_clean(['enabled' => 1, 'languages' => ['en', '<x>', 'en'], 'switcher' => 0]) === ['enabled' => true, 'languages' => ['en'], 'switcher' => false]);

// Pfade und Adressen
t('/en/… erkennt Englisch und kürzt den Pfad', elvado_ml_detect('/en/hallo/', $cfg) === ['en', '/hallo/']);
t('/en ohne Schrägstrich = Startseite', elvado_ml_detect('/en', $cfg) === ['en', '/']);
t('Standardsprache hat kein Präfix (/de/ bleibt ein normaler Pfad)', elvado_ml_detect('/de/x/', $cfg) === ['de', '/de/x/']);
t('Nicht eingeschaltete Sprache bleibt normaler Pfad', elvado_ml_detect('/es/x/', $cfg) === ['de', '/es/x/']);
t('Ausgeschaltet: nie umleiten', elvado_ml_detect('/en/x/', $off) === ['de', '/en/x/']);
t('Adresse in anderer Sprache', elvado_ml_url('https://a.test/beitrag/?x=1', 'en', $cfg) === 'https://a.test/en/beitrag/?x=1' && elvado_ml_url('https://a.test/beitrag/', 'de', $cfg) === 'https://a.test/beitrag/');

// Übersetzungsgruppen
$news = [['id' => 1, 'title' => 'A', 'translation_group' => 'g1'], ['id' => 2, 'title' => 'A en', 'lang' => 'en', 'translation_group' => 'g1'], ['id' => 3, 'title' => 'B', 'lang' => 'en'], ['id' => 4, 'lang' => 'fr', 'translation_group' => 'g1', 'deleted_at' => 'x']];
$tr = elvado_ml_translations($news, $news[0], 'de');
t('Übersetzungen der Gruppe (ohne Gelöschte)', array_keys($tr) === ['de', 'en'] && $tr['en']['id'] === 2);
t('Beitrag ohne Gruppe hat nur sich selbst', array_keys(elvado_ml_translations($news, $news[2], 'de')) === ['en']);
t('Sprache eines Beitrags (leer = Standard, Unsinn = Standard)', elvado_ml_article_lang(['lang' => ''], 'de') === 'de' && elvado_ml_article_lang(['lang' => 'en'], 'de') === 'en' && elvado_ml_article_lang(['lang' => '<s'], 'de') === 'de');
$sw = elvado_ml_switcher_html(['de' => 'https://a.test/', 'en' => 'https://a.test/en/"x'], 'en', true);
t('Sprachumschalter: aktuelle Sprache markiert, Adressen maskiert', str_contains($sw, 'hreflang="en" lang="en" aria-current="true"') && str_contains($sw, '&quot;x') && str_contains($sw, 'elvado-langs-float'));

// Speichern im Beitrag
require_once $root . '/cms/lib/publish.php';
t('news_save übernimmt Sprache, behält Gruppe', elvado_news_ml_fields(['lang' => 'en'], ['translation_group' => 'g1']) === ['lang' => 'en', 'translation_group' => 'g1']);
t('ohne Angabe bleibt die bisherige Sprache', elvado_news_ml_fields([], ['lang' => 'fr']) === ['lang' => 'fr']);
t('ungültige Sprache wird verworfen', elvado_news_ml_fields(['lang' => '1x'], null) === []);

// Verwaltung auf Englisch: Wörterbuch
$dict = json_decode((string)file_get_contents($root . '/cms/assets/i18n/en.json'), true);
t('Wörterbuch ist gültiges JSON mit vielen Einträgen', is_array($dict) && count($dict) > 1500, (string)count((array)$dict));
$bad = array_filter((array)$dict, fn($v, $k) => !is_string($v) || trim($v) === '' || substr_count($k, "\n") !== substr_count($v, "\n"), ARRAY_FILTER_USE_BOTH);
t('Keine leeren Übersetzungen, Zeilenzahl passt', $bad === [], json_encode(array_slice($bad, 0, 3, true), JSON_UNESCAPED_UNICODE));
foreach (['Speichern' => 'Save', 'Beiträge' => 'Posts', 'Einstellungen' => 'Settings', 'Abmelden' => 'Log out', 'Mehrsprachige Website' => 'Multilingual website'] as $de => $en) {
    t("Übersetzung „{$de}“", ($dict[$de] ?? '') === $en, (string)($dict[$de] ?? '–'));
}
$idx = (string)file_get_contents($root . '/cms/index.php');
t('Verwaltung lädt das Sprachskript früh im Kopf', preg_match('~<head>.*assets/i18n\.js.*admin-theme\.js~s', $idx) === 1 && str_contains($idx, 'data-site-lang'));
$js = (string)file_get_contents($root . '/cms/assets/i18n.js');
t('Sprachskript lädt das Wörterbuch nur für Englisch und versteckt nie dauerhaft', str_contains($js, "if(L!=='en')return;") && str_contains($js, "setTimeout(function(){root.classList.remove('ep-i18n-wait')},2500)"));

@unlink(ELVADO_SYSTEM_FILE);
echo $fail ? "$fail von $n Prüfungen fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n";
exit($fail ? 1 : 0);

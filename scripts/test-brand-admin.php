<?php
declare(strict_types=1);
// scripts/test-brand-admin.php – Marken anlegen/duplizieren, Domain-Absicherung, DNS-Prüfung, Verwaltungsoberfläche (Aufruf: php scripts/test-brand-admin.php)
$root = dirname(__DIR__);
require_once $root . '/cms/lib/pack.php';
require_once $root . '/cms/lib/brand.php';
$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
function last(array $a): array { return $a[count($a) - 1]; }
function thrown(callable $f): string { try { $f(); } catch (InvalidArgumentException $e) { return $e->getMessage(); } return ''; }

$a = rrw_brand_blank(['id' => 'alt', 'name' => 'Alt', 'primary_domain' => 'alt.de', 'domains' => ['www.alt.de'], 'logo' => '/l.png', 'title' => 'Alt-Titel', 'claim' => 'Alt-Claim',
    'colors' => ['theme' => '#112233', 'accent' => ''], 'legal' => ['imprint_mode' => 'custom', 'imprint_url' => 'https://alt.de/impressum', 'imprint_content' => '', 'privacy_mode' => 'shared', 'privacy_url' => '', 'privacy_content' => ''],
    'overrides' => ['portal' => ['site_name' => 'Alt', 'hero_eyebrow' => '', 'hero_title' => 'Hallo Alt', 'hero_text' => '', 'news_title' => '', 'news_intro' => '', 'footer_text' => '', 'legal_notice' => '']]]);
$reg = ['default' => 'alt', 'items' => [$a]];

// 1) Domain-Konflikte
t('Keine Konflikte bei getrennten Domains', rrw_brand_domain_conflicts([$a, rrw_brand_blank(['id' => 'b', 'primary_domain' => 'b.de'])]) === []);
$cf = rrw_brand_domain_conflicts([$a, rrw_brand_blank(['id' => 'b', 'primary_domain' => 'www.alt.de'])]);
t('Doppelte Domain (auch mit/ohne www) wird erkannt', count($cf) === 1 && $cf[0]['domain'] === 'alt.de' && $cf[0]['brands'] === ['alt', 'b']);

// 2) Marke anlegen
$items = rrw_brand_create($reg, ['name' => 'Mein Radio', 'primary_domain' => 'https://Mein-Radio.de/', 'domains' => ['www.mein-radio.de'], 'enabled' => true]);
$new = end($items);
t('Neue Marke: Kennung aus dem Namen, Domains bereinigt, aktiv', $new['id'] === 'mein-radio' && $new['primary_domain'] === 'mein-radio.de' && $new['domains'] === ['www.mein-radio.de'] && $new['enabled'] === true && count($items) === 2);
t('Ohne Vorlage: leer gestartet', $new['logo'] === '' && $new['title'] === '' && $new['overrides']['portal']['hero_title'] === '');
t('Neue Marke ist nicht „builtin“ und deaktiviert, wenn nicht verlangt', rrw_brand_create($reg, ['id' => 'x1', 'name' => 'X'])[1]['enabled'] === false && end($items)['builtin'] === false);

// 3) Kopie mit Teilen
$c = last(rrw_brand_create($reg, ['name' => 'Kopie', 'copy_from' => 'alt', 'copy' => ['design', 'legal']]));
t('Kopie: Gestaltung und Rechtliches übernommen', $c['logo'] === '/l.png' && $c['colors']['theme'] === '#112233' && $c['legal']['imprint_mode'] === 'custom');
t('Kopie: nicht gewählte Teile (Texte, SEO) bleiben leer', $c['claim'] === '' && $c['overrides']['portal']['hero_title'] === '' && $c['title'] === '');
t('Kopie: Kennung, Name und Domains sind neu', $c['id'] === 'kopie' && $c['name'] === 'Kopie' && $c['primary_domain'] === '' && $c['domains'] === []);
$c2 = last(rrw_brand_create($reg, ['name' => 'Neu Zwei', 'copy_from' => 'alt', 'copy' => ['texts', 'seo']]));
t('Kopie: Texte/SEO übernommen, Website-Name auf neuen Namen gesetzt', $c2['claim'] === 'Alt-Claim' && $c2['title'] === 'Alt-Titel' && $c2['overrides']['portal']['hero_title'] === 'Hallo Alt' && $c2['overrides']['portal']['site_name'] === 'Neu Zwei' && $c2['logo'] === '');
t('Kopie: unbekannte Gruppen werden ignoriert', last(rrw_brand_create($reg, ['name' => 'Q', 'copy_from' => 'alt', 'copy' => ['evil', 'design']]))['logo'] === '/l.png');

// 4) Fehlerfälle
t('Doppelte Kennung abgelehnt', str_contains(thrown(fn() => rrw_brand_create($reg, ['id' => 'alt', 'name' => 'x'])), 'gibt es schon'));
t('Leere Eingabe abgelehnt', thrown(fn() => rrw_brand_create($reg, [])) !== '');
t('Ungültige Hauptdomain abgelehnt', str_contains(thrown(fn() => rrw_brand_create($reg, ['name' => 'D', 'primary_domain' => 'kein domain'])), 'ungültig'));
t('Ungültige Zusatzdomain abgelehnt', str_contains(thrown(fn() => rrw_brand_create($reg, ['name' => 'D', 'primary_domain' => 'd.de', 'domains' => ['a b']])), 'ungültig'));
t('Domain einer anderen Marke abgelehnt (mit Markenname)', str_contains($m = thrown(fn() => rrw_brand_create($reg, ['name' => 'E', 'primary_domain' => 'www.alt.de'])), 'nur zu einer Marke') && str_contains($m, '„Alt“'), $m);
t('Unbekannte Vorlage abgelehnt', str_contains(thrown(fn() => rrw_brand_create($reg, ['name' => 'F', 'copy_from' => 'gibt-es-nicht'])), 'gibt es nicht'));
$many = ['items' => array_map(fn($i) => rrw_brand_blank(['id' => "m$i"]), range(1, 20))];
t('Höchstens 20 Marken', str_contains(thrown(fn() => rrw_brand_create($many, ['name' => 'Zu viel'])), '20'));

// 5) Bereinigung der gespeicherten Liste behält die neue Marke
$clean = rrw_brands_clean(['default' => 'alt', 'items' => $items]);
$byId = array_column($clean['items'], null, 'id');
t('Gespeicherte Liste enthält die neue Marke mit Domain', ($byId['mein-radio']['primary_domain'] ?? '') === 'mein-radio.de' && isset($byId['alt']));

// 6) DNS-Prüfung (ohne Netz nur Formprüfung und localhost)
t('DNS: ungültige Domain', rrw_brand_domain_dns('kein domain')['ok'] === false);
$d = rrw_brand_domain_dns('localhost.invalid', 'example.invalid');
t('DNS: unbekannte Domain liefert Anleitung (A-Eintrag)', $d['ok'] === true && $d['ips'] === [] && $d['match'] === false && str_contains($d['message'], 'A-Eintrag'));

// 7) Schnittstelle und Oberfläche
$api = (string)file_get_contents($root . '/cms/api.php');
$js = (string)file_get_contents($root . '/cms/assets/brands-manager.js');
t('API: brand_create und brand_domain_check nur für Administratoren', preg_match("/action==='brand_create'\\)\\{[^\\n]*\\n\\s*\\\$au=rrw_auth\\(false\\);if\\(empty\\(\\\$au\\['superadmin'\\]\\)\\)/", $api) === 1 && preg_match("/action==='brand_domain_check'\\)\\{[^\\n]*\\n\\s*\\\$au=rrw_auth\\(false\\);if\\(empty\\(\\\$au\\['superadmin'\\]\\)\\)/", $api) === 1);
t('API: Speichern der Marken prüft Domain-Konflikte', str_contains($api, "\$section==='brands'") && str_contains($api, 'rrw_brand_domain_conflicts'));
t('Oberfläche: Assistent, Duplizieren, DNS-Prüfung, Umschalter aktualisieren', str_contains($js, "id=\"bwOk\"") && str_contains($js, 'duplicate(id)') && str_contains($js, "'brand_domain_check'") && str_contains($js, 'CMS_BRANDS_REFRESH'));
t('Oberfläche: keine prompt()-Eingabe mehr', !str_contains($js, 'prompt('));
echo $fail ? "$fail von $n fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n"; exit($fail ? 1 : 0);

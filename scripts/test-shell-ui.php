<?php
// Prüft die Verwaltungs-Oberfläche (Seitenleiste, Kopfleiste, Live Builder) statisch: jeder Menüpunkt führt zu einem vorhandenen Bereich, die Reihenfolge entspricht dem Entwurf,
// „weitere“ Einträge bleiben erreichbar, Kopfleisten-Elemente und Bedienlogik sind vorhanden. Aufruf: php scripts/test-shell-ui.php
declare(strict_types=1);
$cms = __DIR__ . '/../cms';
$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
$side = (string)file_get_contents("$cms/views/sidebar.php");
$all = '';
foreach (glob("$cms/views/panel-*.php") ?: [] as $f) { $all .= file_get_contents($f); }
$all .= (string)file_get_contents("$cms/index.php");

preg_match_all('/data-tab="([a-z-]+)"/', $side, $m);
$tabs = array_unique($m[1]);
$missing = [];
foreach ($tabs as $tab) { if ($tab === 'plugins-upd') { continue; } if (!str_contains($all, 'id="panel-' . $tab . '"')) { $missing[] = $tab; } }
t('Jeder Menüpunkt hat seinen Bereich', $missing === [], json_encode($missing));

// Reihenfolge der Hauptpunkte wie im Entwurf
preg_match_all('/<span>([^<]+)<\/span>/', $side, $g);
$titles = array_map(fn($x) => html_entity_decode($x), $g[1]);
$want = ['Dashboard', 'Website', 'Medien', 'Design', 'Plugins', 'Benutzer', 'Werkzeuge', 'AI Studio', 'App Builder'];
$pos = -1; $okOrder = true; foreach ($want as $w) { $p = array_search($w, $titles, true); if ($p === false || $p <= $pos) { $okOrder = false; } else { $pos = $p; } }
t('Hauptpunkte in der Reihenfolge des Entwurfs', $okOrder, json_encode($titles));
t('Updates als eigener Eintrag vor „System“; Plugins/Menüs je ein Eintrag mit Reitern (shell.js), Unterbereiche bleiben erreichbar', strpos($side, '>Updates') !== false && strpos($side, '>Updates') < strpos($side, '<span>System</span>') && substr_count($side, 'Plugins</button>') >= 1 && preg_match('/hidden[^>]*class="tab" data-tab="extensions"/', $side) === 1 && preg_match('/hidden[^>]*class="tab" data-tab="headerbuilder"/', $side) === 1 && str_contains((string)file_get_contents("$cms/assets/shell.js"), 'function subtabs'));
// Website-Gruppe: erst die fünf Hauptpunkte, der Rest „weitere“
$web = substr($side, (int)strpos($side, '<span>Website</span>'), 6000);
$first = array_slice(preg_split('/<button/', $web), 1, 5);
$labels = array_map(fn($b) => trim(strip_tags(substr($b, (int)strpos($b, '</i>')))), $first);
t('Website: Live Builder, Seiten, Beiträge, Kategorien, Kommentare zuerst', count($first) === 5 && !str_contains($first[4], 'tab-more') && preg_match('/Live Builder/', $labels[0]) && preg_match('/Seiten/', $labels[1]) && preg_match('/Beiträge/', $labels[2]) && preg_match('/Kategorien/', $labels[3]) && preg_match('/Kommentare/', $labels[4]), json_encode($labels));
t('Weitere Einträge vorhanden (nichts ging verloren)', substr_count($side, 'tab-more') >= 8 && str_contains($side, 'Schlagwörter') && str_contains($side, 'Feeds') && str_contains($side, 'Alle Inhalte') && str_contains($side, 'Datei hinzufügen'));
t('Design: Themes, Customizer, Navigation, Widgets, Header & Footer', preg_match('/Themes.*Customizer.*Navigation.*Widgets.*Header &amp; Footer/s', $side) === 1);

$appb = substr($side, (int)strpos($side, '<span>App Builder</span>'), 2500);
t('App Builder: Alexa-Skill ist immer sichtbar (nicht an das Paket gebunden) und steht neben Apps', preg_match('/<button class="tab" data-tab="alexa"[^>]*>.*?Alexa-Skill/s', $appb) === 1 && strpos($appb, 'data-tab="apps"') < strpos($appb, 'data-tab="alexa"'));

// Kopfleiste
$app = (string)file_get_contents("$cms/assets/cms-app.js");
foreach (['epTopSearch' => 'Suche', 'epSiteChip' => 'Website-Name', 'epDevs' => 'Gerätewahl', 'cmsNotifBadge' => 'Benachrichtigungen', 'epUser' => 'Profil', 'cmsUserIdentity' => 'Identität (für bestehende Skripte)', 'cmsLogout()' => 'Abmelden', 'toggleNotifications()' => 'Glocke'] as $k => $what) {
    t('Kopfleiste: ' . $what, str_contains($app, $k));
}
$js = (string)file_get_contents("$cms/assets/shell.js");
t('Suche öffnet die vorhandene Strg+K-Suche', str_contains($js, 'CmsSearch.open()'));
t('Gerätewahl steuert den Live Builder und bleibt synchron', str_contains($js, 'LiveBuilder.device') && str_contains($js, "'ep-device'") && str_contains((string)file_get_contents("$cms/assets/live-builder.js"), "CustomEvent('ep-device'"));
t('Profil-Menü ohne Inline-Skripte (außer vorhandenem Abmelden)', str_contains($js, 'epUserPop'));
// Live Builder
$lb = (string)file_get_contents("$cms/assets/live-builder.js");
$pv = (string)file_get_contents("$cms/views/panel-livebuilder.php");
t('Live Builder: Reiter Inhalt/Design/Verhalten', str_contains($pv, '>Inhalt<') && str_contains($pv, '>Design<') && str_contains($pv, '>Verhalten<'));
t('Live Builder: geteilter Veröffentlichen-Knopf mit Planen/Verlauf', str_contains($pv, 'id="lbPubMore"') && str_contains($pv, 'id="lbPubPop"') && str_contains($lb, "'lbPubPop'"));
t('Live Builder: Schalterzeilen, Regler mit Zahlenfeld, Abschnitte', str_contains($lb, 'lb-tg') && str_contains($lb, 'data-slider') && str_contains($lb, 'lb-sec'));
$css = (string)file_get_contents("$cms/assets/shell.css");
t('Stile: feste Seitenleiste, Kopfleiste, Schalter', str_contains($css, '#cmsApp>.tabs{position:fixed') && str_contains($css, '.ep-search') && str_contains($css, '.lb-tg'));
t('Stile: helle Verwaltung behält lesbare Seitenleiste', str_contains($css, 'html[data-admin-theme="light"] .tabs'));
t('Stile: versteckte Einträge bleiben versteckt', str_contains($css, '.tabs .tab[hidden]'));
t('Cache-Version erhöht', preg_match('/shell\.css\?v=([2-9]|\d{2,})/', (string)file_get_contents("$cms/index.php")) === 1);
$alx = (string)file_get_contents("$cms/assets/alexa-manager.js");
t('Alexa-Verwaltung: Zusatzabschnitte für Pakete (ohne Projektinhalte im Kern)', str_contains($alx, 'registerSection') && !str_contains($alx, 'Amazon Store-Auftritt') && !str_contains($alx, 'Senderwelt'));
t('Paket-Skripte der Verwaltung nur für vorhandene Pakete', str_contains((string)file_get_contents("$cms/index.php"), "packs/*/admin.js") && str_contains((string)file_get_contents("$cms/index.php"), 'elvado_pack_available($pk)'));
t('Systemstatus-Panel vorhanden und im Menü (System, Updates)', is_file("$cms/views/panel-sysstatus.php") && str_contains($side, "cmsTab('sysstatus'") && substr_count($side, 'data-tab="sysstatus"') === 2 && str_contains((string)file_get_contents("$cms/index.php"), 'panel-sysstatus.php'));
$shJs=(string)file_get_contents(__DIR__.'/../cms/assets/shell.js');$lbJs=(string)file_get_contents(__DIR__.'/../cms/assets/live-builder.js');
t('Marken-Umschalter: nur bei mehreren Marken, Auswahl gemerkt, Ereignis cms:brand',str_contains($shJs,'brands_public')&&str_contains($shJs,'list.length<2')&&str_contains($shJs,"localStorage.setItem('cms.brand'")&&str_contains($shJs,"new CustomEvent('cms:brand'"));
t('Live Editor: Ziele je Marke und Wechsel bei cms:brand',str_contains($lbJs,"addEventListener('cms:brand'")&&str_contains($lbJs,'t.brand===cb'));
$cssAll = (string)file_get_contents(__DIR__ . '/../cms/assets/cms.css');
$tmJs = (string)file_get_contents(__DIR__ . '/../cms/assets/theme-manager.js'); $wtJs = (string)file_get_contents(__DIR__ . '/../cms/assets/wpthemes-manager.js');
t('Customizer: Abschnittsliste folgt der Textfarbe des Designs (lesbar auch im hellen Design)', preg_match('/\.cz-list button\{[^}]*color:var\(--text/', $cssAll) === 1 && preg_match('/\.cz-back\{[^}]*color:var\(--text/', $cssAll) === 1 && preg_match('/\.cz-list button\{[^}]*color:#f3f4fa/', $cssAll) === 0);
t('WordPress-Customizer: Seitenstruktur der Vorschau wird live erkannt und angezeigt (Bridge-Handshake, Liste, Sprung)', str_contains($wtJs, "m.type==='structure'") && str_contains($wtJs, 'czStructHtml') && str_contains($wtJs, "data-czst") && str_contains($wtJs, "type:'select'") && str_contains($wtJs, "m.type==='hello'"));
t('Markenwechsel: Customizer und WordPress-Customizer folgen dem Kopf-Umschalter', str_contains($tmJs, "addEventListener('cms:brand'") && str_contains($tmJs, 'CMS_BRANDS.set(id)') && str_contains($wtJs, "addEventListener('cms:brand'") && str_contains($wtJs, 'czBrandUrl'));
t('Markenwechsel: Live Builder sichert Entwurf statt zu verwerfen, holt verfrühte Wechsel nach, verwirft späte Antworten', str_contains($lbJs, 'pendingBrand') && str_contains($lbJs, 'function applyBrand') && str_contains($lbJs, 'saveDraft():0') && str_contains($lbJs, 'loadSeq'));
echo $fail ? "$fail von $n Prüfungen fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n";
exit($fail ? 1 : 0);

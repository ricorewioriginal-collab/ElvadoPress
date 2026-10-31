<?php
// Prüft den Live-Customizer der Verwaltung (cms/wp/customizer-api.php) auf echtem WordPress (WP_Customize_Manager des Themes):
// Abschnitte und Steuerelemente, Prüfung/Bereinigung, Speichern als Theme-Einstellung, Entwürfe nur in der Vorschau. Aufruf: php scripts/test-wp-customizer.php (mit WPE_TEST_ZIP)
declare(strict_types=1);
require_once __DIR__ . '/wpe-render.php';
$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
$env = wpe_render_env(['site.json' => ['portal' => ['site_name' => 'Customizer-Test']], 'news.json' => []]);
if (!$env) { echo "Hinweis: übersprungen (WPE_TEST_ZIP nicht gesetzt).\n0 von 0 Prüfungen bestanden\n"; exit(0); }
wpe_render_php($env, "switch_theme('elvado-baukasten');");
$body = <<<'BODY'
require_once ELVADO_CMS_DIR.'/lib/wp-real-compat.php';require_once ELVADO_CMS_DIR.'/wp/customizer-api.php';
$d=elvado_wpc_describe('elvado-baukasten');
$sec=array_column($d['sections'],null,'id');
t('Theme-Name und erster Abschnitt Website-Informationen',($d['theme']['slug']??'')==='elvado-baukasten'&&($d['sections'][0]['id']??'')==='title_tagline');
$ctl=[];foreach($d['sections'] as $s)foreach($s['controls'] as $c)$ctl[$c['id']]=$c;
t('Titel und Untertitel bearbeitbar',isset($ctl['blogname'],$ctl['blogdescription'])&&$ctl['blogname']['value']==='Customizer-Test');
t('Theme-Einstellungen des Baukastens (Farbe, Abschnittspositionen, Breite)',($ctl['color_accent']['type']??'')==='color'&&isset($ctl['slot_1'])&&isset($ctl['content_width']),implode(',',array_slice(array_keys($ctl),0,12)));
t('Auswahlfelder bringen ihre Optionen mit',count($ctl['slot_1']['choices']??[])>3);
$v=elvado_wpc_validate(['color_accent'=>'#abc','slot_1'=>'gibt-es-nicht','unbekannt'=>'x','blogname'=>'<b>Neu</b>']);
t('Kurzfarbe wird normalisiert',($v['ok']['color_accent']['v']??$v['ok']['color_accent']??'')==='#aabbcc'||in_array('#aabbcc',array_map(fn($e)=>is_array($e)?($e['v']??''):$e,(array)$v['ok']),true),json_encode($v['ok']));
t('Auswahl außerhalb der Liste wird abgelehnt',isset($v['errors']['slot_1']));
t('Unbekannte Einstellung wird abgelehnt',isset($v['errors']['unbekannt']));
$id=elvado_wpc_new_id();elvado_wpc_draft_save($id,'elvado-baukasten',elvado_wpc_validate(['color_accent'=>'#123456'])['ok']);
t('Entwurf wird abgelegt und nur für das gleiche Theme gelesen',elvado_wpc_draft_load($id,'elvado-baukasten')!==null&&elvado_wpc_draft_load($id,'elvado-classic')===null);
t('Entwurf speichert nichts',get_theme_mod('color_accent','#2563eb')!=='#123456');
$GLOBALS['draft']=$id;
$r=elvado_wpc_save(['color_accent'=>'#00aa55','content_width'=>'900']);
t('Speichern schreibt Theme-Einstellungen',empty($r['errors'])&&get_theme_mod('color_accent')==='#00aa55',json_encode($r));
$out2=elvado_wpc_save(['slot_1'=>'boese']);
t('Ein Fehler → nichts gespeichert',!empty($out2['errors'])&&get_theme_mod('slot_1','hero')!=='boese');
t('Live-Skript prüft den Ursprung der Nachricht',str_contains(elvado_wpc_live_script(),'origin'));
BODY;
foreach (wpe_render_suite($env, $body) as [$nm, $ok, $ex]) t($nm, $ok, $ex);
$home = wpe_render_get($env, '/')['body'];
t('Gespeicherter Wert wirkt auf der Website', str_contains($home, '--accent:#00aa55'));
// Vorschau eines Entwurfs: nur mit Entwurf sichtbar (wie cms/wp-engine-front.php)
$id = (string)wpe_render_php($env, 'require_once ELVADO_CMS_DIR."/lib/wp-real-compat.php";require_once ELVADO_CMS_DIR."/wp/customizer-api.php";$i=elvado_wpc_new_id();elvado_wpc_draft_save($i,"elvado-baukasten",elvado_wpc_validate(["color_accent"=>"#654321"])["ok"]);$out=$i;');
$pv = wpe_render_get($env, '/', 'require_once ELVADO_CMS_DIR."/lib/wp-real-compat.php";require_once ELVADO_CMS_DIR."/wp/customizer-api.php";$d=elvado_wpc_draft_load(' . var_export($id, true) . ',"elvado-baukasten");if($d)elvado_wpc_draft_apply($d);')['body'];
t('Vorschau zeigt den Entwurf', str_contains($pv, '--accent:#654321'));
t('Öffentliche Seite bleibt beim gespeicherten Wert', str_contains(wpe_render_get($env, '/')['body'], '--accent:#00aa55'));
echo $fail ? "$fail von $n Prüfungen fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n";
exit($fail ? 1 : 0);

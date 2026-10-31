<?php
// Prüft die Verbindung der WordPress-Verbinder (Einstellungen → Verbinder) mit der KI-Zentrale auf echtem WordPress (cms/lib/wp-ai-connectors.php):
// Speichern landet in der KI-Zentrale (keine Kopie in den WordPress-Optionen), Lesen liefert den zentralen Schlüssel. Aufruf: php scripts/test-wp-ai-connectors.php (mit WPE_TEST_ZIP)
declare(strict_types=1);
require_once __DIR__ . '/wpe-render.php';
$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FEHLER: $name $extra\n"; } }
$env = wpe_render_env(['site.json' => ['portal' => ['site_name' => 'KI-Verbinder']]]);
if (!$env) { echo "Hinweis: übersprungen (WPE_TEST_ZIP nicht gesetzt).\n0 von 0 Prüfungen bestanden\n"; exit(0); }
$body = <<<'BODY'
t('Verbindung zur KI-Zentrale geladen', function_exists('elvado_wp_ai_central'));
update_option('connectors_ai_openai_api_key', 'sk-test-123');
global $wpdb;
t('Kein Schlüssel in den WordPress-Optionen', $wpdb->get_var("SELECT option_value FROM $wpdb->options WHERE option_name='connectors_ai_openai_api_key'") === null);
t('Schlüssel in der KI-Zentrale gespeichert', str_contains((string)@file_get_contents(dirname(ELVADO_WP_DATA) . '/.ai/gateway.json'), 'sk-test-123'));
t('Lesen liefert den zentralen Schlüssel', get_option('connectors_ai_openai_api_key') === 'sk-test-123');
BODY;
foreach (wpe_render_suite($env, $body) as [$nm, $ok, $ex]) t($nm, $ok, $ex);
t('Schlüssel bleibt nach neuem Seitenaufruf erhalten', wpe_render_php($env, '$out=get_option("connectors_ai_openai_api_key");') === 'sk-test-123');
echo $fail ? "$fail von $n Prüfungen fehlgeschlagen\n" : "$n von $n Prüfungen bestanden\n";
exit($fail ? 1 : 0);

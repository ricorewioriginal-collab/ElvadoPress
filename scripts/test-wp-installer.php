<?php
// Prüft das sichere Entpacken von Plugin-ZIPs (cms/wp/installer.php) auf echtem WordPress. Aufruf: php scripts/test-wp-installer.php (mit WPE_TEST_ZIP)
declare(strict_types=1);
require_once __DIR__.'/wpe-render.php';
$fail=0;$n=0;
function t(string $name,bool $ok): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name\n";} }
$env=wpe_render_env();
if(!$env){ echo "Hinweis: übersprungen (WPE_TEST_ZIP nicht gesetzt).\n0 von 0 Prüfungen bestanden\n";exit(0); }
$body=<<<'BODY'
require_once ABSPATH.'wp-admin/includes/plugin.php';require_once ELVADO_CMS_DIR.'/lib/wp-real-compat.php';require_once ELVADO_CMS_DIR.'/wp/installer.php';
$tmp=sys_get_temp_dir().'/elvado-wpi-'.bin2hex(random_bytes(4));mkdir($tmp);
function mkzip(string $path,array $files): void { $z=new ZipArchive();$z->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE);foreach($files as $k=>$v)$z->addFromString($k,$v);$z->close(); }
$hdr="<?php\n/*\nPlugin Name: Test\nVersion: 1\n*/\n";
// Normales Paket mit Top-Level-Ordner
mkzip($tmp.'/a.zip',['mein-plugin/mein.php'=>$hdr,'mein-plugin/assets/a.js'=>'1','mein-plugin/.htaccess'=>'AddHandler x .jpg','mein-plugin/sub/.user.ini'=>'x','__MACOSX/x'=>'y']);
$r=elvado_wpi_install_plugin_zip($tmp.'/a.zip');
t('Slug aus Ordner',$r['slug']==='mein-plugin');
t('Dateien entpackt',is_file(WP_PLUGIN_DIR.'/mein-plugin/mein.php')&&is_file(WP_PLUGIN_DIR.'/mein-plugin/assets/a.js'));
t('.htaccess und .user.ini übersprungen',!is_file(WP_PLUGIN_DIR.'/mein-plugin/.htaccess')&&!is_file(WP_PLUGIN_DIR.'/mein-plugin/sub/.user.ini'));
t('von get_plugins erkannt',isset(get_plugins()['mein-plugin/mein.php']));
// Pfad-Tricks
mkzip($tmp.'/b.zip',['evil/evil.php'=>$hdr,'evil/../../escape.php'=>'x','/abs.php'=>'x','evil/ok.txt'=>'ok']);
elvado_wpi_install_plugin_zip($tmp.'/b.zip');
t('kein Ausbruch aus dem Plugin-Ordner',!is_file(WP_CONTENT_DIR.'/escape.php')&&!is_file($tmp.'/escape.php')&&!is_file('/abs.php')&&is_file(WP_PLUGIN_DIR.'/evil/ok.txt'));
// Kein Plugin-Header
mkzip($tmp.'/c.zip',['nix/readme.txt'=>'x','nix/a.php'=>'<?php // kein Header']);
$ok=false;try{elvado_wpi_install_plugin_zip($tmp.'/c.zip');}catch(RuntimeException $e){$ok=true;}
t('ZIP ohne Plugin-Header wird abgelehnt',$ok&&!is_dir(WP_PLUGIN_DIR.'/nix'));
t('Temp-Ordner aufgeräumt',!glob(WP_PLUGIN_DIR.'/.tmp-*'));
// Update ersetzt
mkzip($tmp.'/d.zip',['mein-plugin/mein.php'=>$hdr."// v2\n",'mein-plugin/neu.txt'=>'n']);
elvado_wpi_install_plugin_zip($tmp.'/d.zip');
t('Update ersetzt Dateien',is_file(WP_PLUGIN_DIR.'/mein-plugin/neu.txt')&&!is_file(WP_PLUGIN_DIR.'/mein-plugin/assets/a.js')&&!glob(WP_PLUGIN_DIR.'/*.old-*'));
// Einzeldatei im Wurzelverzeichnis
mkzip($tmp.'/e.zip',['solo.php'=>$hdr]);
$r=elvado_wpi_install_plugin_zip($tmp.'/e.zip','solo-plugin');t('Dateien im Wurzelverzeichnis → Slug aus Hinweis',$r['slug']==='solo-plugin'&&is_file(WP_PLUGIN_DIR.'/solo-plugin/solo.php'));
// Ungültige Eingaben
$ok=false;try{elvado_wpi_install_plugin_zip($tmp.'/nichtda.zip','x');}catch(Throwable $e){$ok=true;}t('fehlendes ZIP',$ok);
t('Namenssäuberung',elvado_wpi_clean_name('a/../b')===null&&elvado_wpi_clean_name('/x')===null&&elvado_wpi_clean_name('C:\\x')===null&&elvado_wpi_clean_name('a/b.php')==='a/b.php');
t('blockierte Dateien',elvado_wpi_blocked_file('.htaccess')&&elvado_wpi_blocked_file('.HTPASSWD')&&elvado_wpi_blocked_file('php.ini')&&!elvado_wpi_blocked_file('plugin.php'));
t('Slug-Säuberung',elvado_wpi_slug('../Evil Plugin!')==='evil-plugin');
t('download nur von wordpress.org',(function(){ try{ elvado_wpi_download_plugin('../x'); }catch(RuntimeException $e){ return true; } return false; })());
system('rm -rf '.escapeshellarg($tmp));
BODY;
foreach(wpe_render_suite($env,$body) as [$nm,$ok,$ex]){ t($nm,$ok);if(!$ok&&$ex!=="")echo "  $ex\n"; }

echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

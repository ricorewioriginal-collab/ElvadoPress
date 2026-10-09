<?php
// Kopplung KI-Zentrale ↔ Plugin „Elvado AI“: Das Plugin schaltet sich beim Einrichten eines nutzbaren Anbieters einmalig ein. Aufruf: php scripts/test-ai-coupling.php
declare(strict_types=1);
$real=dirname(__DIR__).'/cms';
require_once $real.'/src/autoload.php';
require_once $real.'/lib/nplugins.php';
use Elvado\Plugin\{Fs,PluginManager};
$n=0;$fail=0;
function t(string $name,bool $ok,string $info=''): void { global $n,$fail;$n++;if(!$ok){$fail++;echo "FAIL  $name".($info!==''?": $info":'')."\n";}else echo "  ok  $name\n"; }
$ver=trim((string)file_get_contents($real.'/VERSION'));
$tmp=sys_get_temp_dir().'/elvado-aic-'.bin2hex(random_bytes(4));$cms=$tmp.'/cms';
foreach(['data','plugins','backups','media','themes','content'] as $d)mkdir($cms.'/'.$d,0755,true);
foreach(['lib','src','official-plugins'] as $l)symlink($real.'/'.$l,$cms.'/'.$l);
file_put_contents($cms.'/VERSION',$ver."\n");
$m=new PluginManager($cms,$cms.'/data',$ver);
t('ohne nutzbaren Anbieter passiert nichts',elvado_np_ai_autoactivate(false,$m)===''&&!$m->isActive('elvado-ai')&&!$m->isInstalled('elvado-ai'));
$msg=elvado_np_ai_autoactivate(true,$m);
t('mit Anbieter: Plugin installiert und aktiviert, Meldung für die Verwaltung',$m->isActive('elvado-ai')&&str_contains($msg,'Elvado AI')&&is_file($m->stateDir().'/ai-autoactivated'),$msg);
t('bereits aktiv: keine weitere Meldung',elvado_np_ai_autoactivate(true,$m)==='');
$m->deactivate('elvado-ai');
t('bewusst abgeschaltet bleibt aus (kein erneutes Aktivieren)',elvado_np_ai_autoactivate(true,$m)===''&&!$m->isActive('elvado-ai'));
// Regression (Demo fror beim Anmelden ein): Plugin-Oberflächen dürfen sich nicht über ihren eigenen MutationObserver endlos neu auslösen
$ai=(string)file_get_contents($real.'/official-plugins/elvado-ai/admin.js');$seo=(string)file_get_contents($real.'/official-plugins/elvado-seo/admin.js');
t('Elvado AI (Oberfläche): Status-Anfrage wird als Promise gemerkt und der Observer gebündelt',str_contains($ai,'if(!st)st=call(')&&str_contains($ai,'setTimeout(function(){tm=0;build();'));
t('Elvado SEO (Oberfläche): schreibt nur bei Änderung, ignoriert eigene Änderungen, gebündelt',str_contains($seo,'box.__h===html')&&str_contains($seo,'box.contains(m.target)')&&str_contains($seo,'setTimeout(function(){tm=0;build();update();'));
Fs::rmTree($tmp);
echo $fail?"$fail von $n Prüfungen fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

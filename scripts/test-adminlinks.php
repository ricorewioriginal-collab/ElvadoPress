<?php
// Eigene Links im Verwaltungsmenü: Prüfung, Speicherung und Einbindung in Seitenleiste und Verwaltung.
declare(strict_types=1);
$root=dirname(__DIR__);$n=0;$fail=0;
function t(string $name,bool $ok,string $info=''): void { global $n,$fail;$n++;if(!$ok){$fail++;echo "FAIL  $name".($info!==''?": $info":'')."\n";}else echo "  ok  $name\n"; }
require_once $root.'/cms/lib/publish.php';
require_once $root.'/cms/lib/admin-links.php';
function bad(array $links): bool { try{elvado_admin_links_normalize($links);return false;}catch(InvalidArgumentException $e){return true;} }
$ok=['label'=>'Statistik','url'=>'https://stats.example.org/dashboard','icon'=>'chart-line','mode'=>'frame'];

foreach(['javascript:alert(1)','JaVaScRiPt:alert(1)','data:text/html,<script>1</script>','//evil.example/x','ftp://example.org/x','https://','https://user:pw@example.org/','/pfad mit leerzeichen','/x\\y',"/x\ny",'',str_repeat('a',501)] as $u)
    t('Adresse abgelehnt: '.substr(json_encode($u),0,40),bad([['label'=>'X','url'=>$u]]));
foreach(['https://example.org/a?b=1#c','http://127.0.0.1:8080/','/statistik/','/'] as $u)
    t('Adresse erlaubt: '.$u,!bad([['label'=>'X','url'=>$u]]));
t('Name fehlt → abgelehnt',bad([['label'=>'  ','url'=>'/a']]));
t('Name über 40 Zeichen → abgelehnt',bad([['label'=>str_repeat('ä',41),'url'=>'/a']]));
t('Mehr als 20 Links → abgelehnt',bad(array_fill(0,21,$ok)));

$r=elvado_admin_links_normalize([['label'=>'<b>Shop</b>','url'=>' https://shop.example.org ','icon'=>'Cart Shopping!','mode'=>'unsinn'],$ok+['id'=>'abc'],$ok+['id'=>'abc']]);
t('HTML im Namen wird entfernt, Leerraum in der Adresse getrimmt',$r[0]['label']==='Shop'&&$r[0]['url']==='https://shop.example.org');
t('Ungültiges Symbol fällt auf „link“, ungültiger Modus auf „new“',$r[0]['icon']==='link'&&$r[0]['mode']==='new');
t('Gültige Werte bleiben, doppelte Kennungen werden neu vergeben',$r[1]['id']==='abc'&&$r[1]['icon']==='chart-line'&&$r[1]['mode']==='frame'&&$r[2]['id']!=='abc'&&preg_match('/^[a-z0-9_-]{3,40}$/',$r[2]['id'])===1);
t('Reihenfolge bleibt erhalten',array_column($r,'label')===['Shop','Statistik','Statistik']);

$d=sys_get_temp_dir().'/elvado-al-'.bin2hex(random_bytes(4));mkdir($d);
t('Ohne Datei: leere Liste',elvado_admin_links_get($d)===[]);
$saved=elvado_admin_links_save($d,[$ok]);
t('Speichern und Lesen ergeben dieselben Links',elvado_admin_links_get($d)===$saved&&count($saved)===1&&$saved[0]['url']===$ok['url']);
file_put_contents($d.'/admin-links.json','{kaputt');
t('Beschädigte Datei: leere Liste statt Fehler',elvado_admin_links_get($d)===[]);
file_put_contents($d.'/admin-links.json',json_encode(['links'=>[['label'=>'Böse','url'=>'javascript:alert(1)']]]));
t('Von Hand eingetragene unsichere Adresse wird nie ausgeliefert',elvado_admin_links_get($d)===[]);
@unlink($d.'/admin-links.json');@rmdir($d);

$side=(string)file_get_contents($root.'/cms/views/sidebar.php');$idx=(string)file_get_contents($root.'/cms/index.php');$js=(string)file_get_contents($root.'/cms/assets/admin-links.js');
t('Seitenleiste: Platz für eigene Links über „Menü einklappen“ und Reiter nur für Administratoren',(strpos($side,'id="cmsCustomLinks"')??false)<strpos($side,'id="cmsFoldBtn"')&&str_contains($side,'class="tab sa-only" data-tab="adminlinks" hidden'));
t('Verwaltung bindet Bereiche und Skript ein',str_contains($idx,'panel-adminlinks.php')&&str_contains($idx,'assets/admin-links.js'));
t('Rahmen: eigene Seite ohne allow-same-origin, Links mit noopener',str_contains($js,"'allow-scripts allow-forms allow-popups allow-downloads'+(sameOrigin(l.url)?'':' allow-same-origin')")&&str_contains($js,'rel="noopener noreferrer"'));
t('Skript-Syntax (node)',(function() use($root){ $o=[];$c=-1;@exec('node --check '.escapeshellarg($root.'/cms/assets/admin-links.js').' 2>&1',$o,$c);return $c===0||$c===127; })());
echo "\n".($n-$fail)." von $n Prüfungen bestanden\n";
exit($fail?1:0);

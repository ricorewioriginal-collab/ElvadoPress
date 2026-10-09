<?php
// Prüft die Meldungen der WordPress-Plugins (admin_notices) in der Verwaltung, z. B. „Hello Dolly“: Dokument für den abgeschotteten Rahmen, Fehlerfälle und Verdrahtung. Aufruf: php scripts/test-wp-notices.php
// Die Plugins werden beim Start der WordPress-Schicht geladen; jedes Szenario läuft deshalb in einem eigenen PHP-Prozess.
declare(strict_types=1);
$sc=(string)($argv[1]??'');
function rmrf(string $d): void { if(!is_dir($d))return; foreach(scandir($d) as $f){if($f==='.'||$f==='..')continue;$p=$d.'/'.$f;is_dir($p)&&!is_link($p)?rmrf($p):@unlink($p);} @rmdir($d); }
$fail=0;$n=0;function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
if($sc===''){
    foreach(['keine','spruch','kaputt'] as $x){
        $out=[];$rc=0;exec('php '.escapeshellarg(__FILE__).' '.$x.' 2>&1',$out,$rc);
        $last=(string)end($out);foreach($out as $l)if(str_starts_with($l,'FEHLER'))echo $l."\n";
        if(!preg_match('/^ZAHL (\d+) (\d+)$/',$last,$m)){ $fail++;$n++;echo "FEHLER: Szenario $x lief nicht durch: ".implode(' | ',array_slice($out,-3))."\n";continue; }
        $n+=(int)$m[1];$fail+=(int)$m[2];
    }
    /* Verdrahtung: API, Rahmen im CMS, Aktualisierung nach Plugin-Aktionen */
    $root=dirname(__DIR__).'/cms';
    $api=(string)file_get_contents($root.'/api.php');$idx=(string)file_get_contents($root.'/index.php');$js=(string)file_get_contents($root.'/assets/wp-notices.js');
    t('API-Aktion wp_admin_notices ist angebunden (nur angemeldet, innerhalb des wp_-Blocks)',str_contains($api,"'wp_admin_notices'")&&str_contains($api,'elvado_wp_admin_notices_doc()')&&str_contains($api,"str_starts_with(\$action,'wp_')"));
    t('Verwaltung enthält Container und Skript',str_contains($idx,'id="wpNotices"')&&str_contains($idx,'assets/wp-notices.js'));
    t('Rahmen ist abgeschottet (sandbox ohne allow-same-origin) und prüft die Herkunft der Höhenmeldung',preg_match("/setAttribute\\('sandbox','([^']*)'\\)/",$js,$sb)&&$sb[1]==='allow-scripts allow-popups'&&str_contains($js,'e.source!==frame.contentWindow'));
    t('Nach Aktivieren/Deaktivieren eines WordPress-Plugins und nach der Anmeldung werden die Meldungen neu geladen',str_contains((string)file_get_contents($root.'/assets/wpplugins-manager.js'),'WpNotices.refresh()')&&str_contains((string)file_get_contents($root.'/assets/cms-app.js'),'WpNotices.refresh()'));
    t('Kein Beobachter und keine Zeitschleife im Skript (Schutz vor Endlosschleifen in der Verwaltung)',!str_contains($js,'MutationObserver')&&!str_contains($js,'setInterval'));
    echo $fail?"$fail von $n Prüfungen fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);
}
$tmp=sys_get_temp_dir().'/elvado-wpn-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/wp-content/plugins');mkdir($tmp.'/wp-content/plugins/spruch');mkdir($tmp.'/wp-content/plugins/leise');mkdir($tmp.'/wp-content/plugins/kaputt');mkdir($tmp.'/cms');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('ELVADO_WP_DATA',$tmp.'/cms/.wp');define('ELVADO_WP_CMS_DATA',$tmp.'/cms');$_SERVER['HTTP_HOST']='example.test';$_SERVER['REMOTE_ADDR']='203.0.113.5';
file_put_contents($tmp.'/cms/news.json','[]');file_put_contents($tmp.'/cms/site.json',json_encode(['portal'=>['site_name'=>'Test']]));
// Wie „Hello Dolly“: nur Meldung (admin_notices) und Kopfzeilen-Stil (admin_head), keine eigene Seite
file_put_contents($tmp.'/wp-content/plugins/spruch/spruch.php',<<<'PHP'
<?php
/* Plugin Name: Spruch */
add_action('admin_notices', function(){ echo '<p id="spruch">Hallo, Test-Spruch</p>'; });
add_action('admin_head', function(){ echo '<style>#spruch{float:right;margin:0}</style>'; });
PHP);
file_put_contents($tmp.'/wp-content/plugins/leise/leise.php',"<?php\n/* Plugin Name: Leise */\nadd_action('admin_menu', function(){});\n");
file_put_contents($tmp.'/wp-content/plugins/kaputt/kaputt.php',<<<'PHP'
<?php
/* Plugin Name: Kaputt */
add_action('admin_notices', function(){ throw new RuntimeException('Meldung kaputt'); });
PHP);
$GLOBALS['ELVADO_SITE']=json_decode((string)file_get_contents($tmp.'/cms/site.json'),true);
require __DIR__."/_testdb.php";
require __DIR__.'/../cms/wp/load.php';require __DIR__.'/../cms/wp/router.php';require __DIR__.'/../cms/wp/admin.php';
update_option('active_plugins',$sc==='keine'?['leise/leise.php']:($sc==='spruch'?['spruch/spruch.php','leise/leise.php']:['kaputt/kaputt.php']));
elvado_wp_boot(['user'=>['id'=>1,'login'=>'admin','name'=>'Admin','role'=>'administrator'],'admin'=>true]);
$lv=ob_get_level();
if($sc==='keine'){
    $doc=elvado_wp_admin_notices_doc();
    t('Plugin ohne admin_notices: kein Rahmen',$doc==='');
    t('Nicht aktive Plugins zeigen nichts',strpos($doc,'Test-Spruch')===false);
}
if($sc==='spruch'){
    $doc=elvado_wp_admin_notices_doc();
    t('Meldung wird als vollständiges Dokument geliefert',str_starts_with($doc,'<!doctype html>')&&str_contains($doc,'<p id="spruch">Hallo, Test-Spruch</p>'));
    t('Kopfzeilen-Ausgabe (admin_head) des Plugins ist enthalten',str_contains($doc,'#spruch{float:right;margin:0}'));
    t('Rahmen meldet seine Höhe an das Elternfenster (postMessage)',str_contains($doc,'elvadoWpNotices')&&str_contains($doc,'parent.postMessage'));
    t('Clearfix für schwebende Meldungen (Höhe stimmt)',str_contains($doc,'clear:both'));
    t('Meldung steht genau einmal im Dokument',substr_count($doc,'id="spruch"')===1);
    t('Wiederholter Aufruf liefert dasselbe und hinterlässt keinen Ausgabepuffer',elvado_wp_admin_notices_doc()===$doc&&ob_get_level()===$lv);
}
if($sc==='kaputt'){
    $ok=true;$d2='x';try{ $d2=elvado_wp_admin_notices_doc(); }catch(Throwable $e){ $ok=false; }
    t('Wirft ein Plugin in admin_notices, wird nichts angezeigt, aber auch nichts abgebrochen',$ok&&$d2==='');
    t('Nach einem Fehler bleibt kein Ausgabepuffer offen',ob_get_level()===$lv);
}
rmrf($tmp);
echo "ZAHL $n $fail\n";exit($fail?1:0);

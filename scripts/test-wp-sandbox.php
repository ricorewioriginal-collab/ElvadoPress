<?php
// Prüft die Sandbox: Anlegen, Trennung von Live (Optionen, Themes), Zugriffsschlüssel, „Live stellen“ nur mit Design-Optionen und neuen Themes,
// Sicherung und Zurückrollen, Schutz der CMS-Daten. Aufruf: php scripts/test-wp-sandbox.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/rrw-sbx-'.bin2hex(random_bytes(4));$cms=$tmp.'/cms';
foreach([$tmp,$cms,$cms.'/wp',$cms.'/data',$cms.'/data/.wp',$cms.'/wp-content',$cms.'/wp-content/themes',$cms.'/wp-content/themes/live-a'] as $d)mkdir($d,0775,true);
// sandbox.php verwendet dirname(__DIR__): Kopie in eine Wegwerf-Struktur legen
copy(__DIR__.'/../cms/wp/sandbox.php',$cms.'/wp/sandbox.php');
file_put_contents($cms.'/wp-content/themes/live-a/style.css',"/*\nTheme Name: Live A\n*/");
$ser=fn($v)=>['v'=>serialize($v),'a'=>'yes'];
$opts=['stylesheet'=>$ser('live-a'),'template'=>$ser('live-a'),'theme_mods_live-a'=>$ser(['color'=>'#111111']),'blogname'=>$ser('Mein Radio'),'active_plugins'=>$ser(['x/x.php']),'rrw_cms_bridge'=>$ser(['posts'])];
file_put_contents($cms.'/data/.wp/options.json',json_encode($opts));file_put_contents($cms.'/data/.wp/front-on','x');file_put_contents($cms.'/data/.wp/salt.json','{}');
file_put_contents($cms.'/data/site.json','{"portal":{"site_name":"CMS"}}');$siteBefore=file_get_contents($cms.'/data/site.json');
require $cms.'/wp/sandbox.php';
$fail=0;$n=0;function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }

t('Zu Beginn keine Sandbox',!rrw_sbx_exists()&&!rrw_sbx_token_ok(str_repeat('a',32)));
t('Reset/Veröffentlichen ohne Sandbox abgewiesen',rrw_sbx_reset()!==null&&!rrw_sbx_publish()['ok']);
t('Anlegen',rrw_sbx_create()===null&&rrw_sbx_exists());
t('Zweites Anlegen abgewiesen',rrw_sbx_create()!==null);
$m=rrw_sbx_meta();$tok=$m['token'];
t('Zugangsschlüssel: 32 Hex, richtig ok, falsch/leer/kurz nicht',strlen($tok)===32&&rrw_sbx_token_ok($tok)&&!rrw_sbx_token_ok(str_repeat('0',32))&&!rrw_sbx_token_ok('')&&!rrw_sbx_token_ok(substr($tok,0,31)));
t('Link enthält den Schlüssel',rrw_sbx_url()==='/?rrw_sbx='.$tok);
t('Optionen und Auslieferung wurden übernommen',rrw_sbx_read_opts(rrw_sbx_data())==$opts&&is_file(rrw_sbx_data().'/front-on'));
t('Daten-Ordner geschützt',is_file(rrw_sbx_data().'/.htaccess'));
t('Direkt nach dem Anlegen kein Unterschied',!rrw_sbx_diff()['changed']);
t('Veröffentlichen ohne Unterschied abgewiesen',!rrw_sbx_publish()['ok']);

/* Änderungen in der Sandbox */
rrw_sbx_write_opts(rrw_sbx_data(),function(array &$a) use($ser){ $a['stylesheet']=$ser('sbx-b');$a['template']=$ser('sbx-b');$a['theme_mods_sbx-b']=$ser(['color'=>'#ff0000']);$a['blogname']=$ser('GEÄNDERT');$a['active_plugins']=$ser(['evil/evil.php']);$a['sidebars_widgets']=$ser(['sidebar-1'=>['text-2']]);$a['widget_text']=$ser([2=>['title'=>'x']]);$a['rrw_cms_bridge']=$ser(['posts','pages']); });
mkdir(rrw_sbx_themes().'/sbx-b/img',0775,true);file_put_contents(rrw_sbx_themes().'/sbx-b/style.css',"/*\nTheme Name: Sandbox B\n*/");file_put_contents(rrw_sbx_themes().'/sbx-b/img/a.png','png');
$live=rrw_sbx_read_opts(rrw_sbx_live_data());
t('Live-Optionen blieben unberührt',$live==$opts);
t('Live-Themes unberührt (kein sbx-b)',!is_dir($cms.'/wp-content/themes/sbx-b'));
$d=rrw_sbx_diff();
t('Unterschiede: nur Design-Optionen',$d['options']==['sidebars_widgets','stylesheet','template','theme_mods_sbx-b','widget_text']&&$d['changed']);
t('Unterschiede: neue Themes und Themenwechsel',$d['new_themes']===['sbx-b']&&$d['sandbox_theme']==='sbx-b'&&$d['live_theme']==='live-a');
t('Schlüsselprüfung: Plugins, Titel, Bridge, Fremdes sind nicht übernehmbar',!rrw_sbx_key_ok('active_plugins')&&!rrw_sbx_key_ok('blogname')&&!rrw_sbx_key_ok('rrw_cms_bridge')&&!rrw_sbx_key_ok('home')&&rrw_sbx_key_ok('theme_mods_x')&&rrw_sbx_key_ok('widget_text')&&!rrw_sbx_key_ok('theme_mods_../x'));

/* Live stellen */
$r=rrw_sbx_publish();
t('Live stellen gelingt',$r['ok']&&$r['themes']===['sbx-b']&&preg_match('/^\d{8}-\d{6}$/',$r['backup']),json_encode($r));
$live=rrw_sbx_read_opts(rrw_sbx_live_data());
t('Live: Theme und Anpassungen übernommen',unserialize($live['stylesheet']['v'])==='sbx-b'&&unserialize($live['theme_mods_sbx-b']['v'])['color']==='#ff0000'&&isset($live['sidebars_widgets']));
t('Live: Plugins, Titel und Bridge NICHT verändert',$live['active_plugins']==$opts['active_plugins']&&$live['blogname']==$opts['blogname']&&$live['rrw_cms_bridge']==$opts['rrw_cms_bridge']);
t('Live: Theme-Dateien samt Unterordnern kopiert',is_file($cms.'/wp-content/themes/sbx-b/style.css')&&is_file($cms.'/wp-content/themes/sbx-b/img/a.png')&&is_dir($cms.'/wp-content/themes/live-a'));
t('CMS-Daten (site.json) unberührt',file_get_contents($cms.'/data/site.json')===$siteBefore);
t('Danach kein Unterschied mehr',!rrw_sbx_diff()['changed']);
t('Sicherung angelegt und gelistet',count(rrw_sbx_backups())===1&&rrw_sbx_backups()[0]['id']===$r['backup']);

/* Zurückrollen */
t('Ungültige/unbekannte Sicherung abgewiesen',rrw_sbx_rollback('../x')!==null&&rrw_sbx_rollback('20200101-000000')!==null);
t('Zurückrollen gelingt',rrw_sbx_rollback($r['backup'])===null);
$live=rrw_sbx_read_opts(rrw_sbx_live_data());
t('Live wieder wie vorher (Theme, Anpassungen, Widgets entfernt)',unserialize($live['stylesheet']['v'])==='live-a'&&!isset($live['theme_mods_sbx-b'])&&!isset($live['sidebars_widgets'])&&$live['blogname']==$opts['blogname']);

/* Auslieferung: Sandbox ohne WordPress-Theme → live wieder Portal-Design */
unlink(rrw_sbx_data().'/front-on');
t('Unterschied bei der Auslieferung (Portal-Design) erkannt',rrw_sbx_diff()['changed']&&rrw_sbx_diff()['live_front']&&!rrw_sbx_diff()['sandbox_front']);
$r=rrw_sbx_publish();t('Live stellen schaltet die Theme-Auslieferung aus',$r['ok']&&!is_file(rrw_sbx_live_data().'/front-on'));
rrw_sbx_rollback($r['backup']);t('Zurückrollen schaltet sie wieder ein',is_file(rrw_sbx_live_data().'/front-on'));

/* Link erneuern, Zurücksetzen, Löschen */
t('Link erneuern ändert den Schlüssel, der alte gilt nicht mehr',rrw_sbx_rotate()===null&&!rrw_sbx_token_ok($tok)&&rrw_sbx_token_ok(rrw_sbx_meta()['token']));
$tok2=rrw_sbx_meta()['token'];
rrw_sbx_write_opts(rrw_sbx_data(),function(array &$a) use($ser){ $a['stylesheet']=$ser('zzz'); });
t('Zurücksetzen: wieder wie Live, Themes der Sandbox weg, Schlüssel bleibt',rrw_sbx_reset()===null&&rrw_sbx_read_opts(rrw_sbx_data())==rrw_sbx_read_opts(rrw_sbx_live_data())&&!is_dir(rrw_sbx_themes().'/sbx-b')&&rrw_sbx_meta()['token']===$tok2);
rrw_sbx_delete();
t('Löschen entfernt Optionen und Themes, Live bleibt',!rrw_sbx_exists()&&!is_dir(rrw_sbx_data())&&!is_dir(rrw_sbx_themes())&&is_dir($cms.'/wp-content/themes/live-a'));

/* Einbindung: Quelltext-Prüfungen */
$front=file_get_contents(__DIR__.'/../cms/wp-front.php');$api=file_get_contents(__DIR__.'/../cms/api.php');
t('Front-Controller: Sandbox nur lesend (405), noindex, Cookie',str_contains($front,'405')&&str_contains($front,'noindex, nofollow')&&str_contains($front,"setcookie('rrw_sbx'"));
t('Front-Controller lädt die Sandbox vor der Laufzeit',strpos($front,'rrw_sbx_enter()')<strpos($front,"require_once \$cmsDir.'/wp/load.php'"));
t('API: Sandbox-Modus vor dem Laden der Laufzeit und hinter der Administrator-Prüfung',strpos($api,'$wpUser=rrw_auth(true);')<strpos($api,'rrw_sbx_enter();')&&strpos($api,'rrw_sbx_enter();')<strpos($api,"require_once __DIR__.'/wp/load.php';require_once __DIR__.'/wp/installer.php'"));
t('Schreib-Brücke, Versand und Cron sind in der Sandbox aus',str_contains(file_get_contents(__DIR__.'/../cms/wp/core/cms-write.php'),"defined('RRW_WP_SANDBOX')")&&str_contains(file_get_contents(__DIR__.'/../cms/wp/core/cron.php'),"defined('RRW_WP_SANDBOX')"));

/* Laufzeit in der Sandbox: Theme-Suche über Sandbox, Live und mitgelieferte Themes; URLs je Herkunft; Installation landet in der Sandbox */
$rt=sys_get_temp_dir().'/rrw-sbx-rt-'.bin2hex(random_bytes(4));
foreach([$rt.'/cms/wp-content/themes/live-a',$rt.'/cms/wp-sandbox/themes/sbx-b',$rt.'/cms/data/.wp-sandbox',$rt.'/native/nat-c'] as $d)mkdir($d,0775,true);
foreach(['cms/wp-content/themes/live-a'=>'Live A','cms/wp-sandbox/themes/sbx-b'=>'Sandbox B','native/nat-c'=>'Nativ C'] as $d=>$nm){ file_put_contents($rt.'/'.$d.'/style.css',"/*\nTheme Name: $nm\n*/");file_put_contents($rt.'/'.$d.'/index.php','<?php'); }
file_put_contents($rt.'/cms/data/.wp-sandbox/options.json',json_encode(['stylesheet'=>['v'=>serialize('sbx-b')],'template'=>['v'=>serialize('sbx-b')]]));
$code='<?php define("WP_CONTENT_DIR","'.$rt.'/cms/wp-content");define("RRW_WP_NATIVE_THEMES","'.$rt.'/native");define("RRW_WP_DATA","'.$rt.'/cms/data/.wp-sandbox");define("RRW_WP_SANDBOX",true);define("RRW_WP_SANDBOX_THEMES","'.$rt.'/cms/wp-sandbox/themes");$_SERVER["HTTP_HOST"]="example.test";'
 .'require "'.__DIR__.'/_testdb.php";require "'.__DIR__.'/../cms/wp/load.php";require "'.__DIR__.'/../cms/wp/installer.php";rrw_wp_boot(["theme"=>true]);'
 .'$l=array_column(rrw_wpi_list_themes(),null,"slug");'
 .'echo json_encode(["o"=>array_map(fn($t)=>$t["origin"],$l),"shot"=>$l["sbx-b"]["screenshot"]??"","root"=>get_theme_root(),"dirB"=>get_stylesheet_directory(),"uriB"=>get_stylesheet_directory_uri(),"uriLive"=>get_stylesheet_directory_uri("live-a"),"dirLive"=>get_stylesheet_directory("live-a"),"uriNat"=>get_stylesheet_directory_uri("nat-c"),"themes"=>array_keys(wp_get_themes()),"mail"=>wp_mail("a@b.de","x","y"),"cron"=>rrw_wp_run_cron(true)]);';
file_put_contents($rt.'/run.php',$code);
$j=json_decode((string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($rt.'/run.php').' 2>&1'),true);
t('Sandbox-Laufzeit: Themes aus Sandbox, Live und mitgeliefert mit Herkunft',is_array($j)&&($j['o']['sbx-b']??'')==='sandbox'&&($j['o']['live-a']??'')==='live'&&($j['o']['nat-c']??'')==='native',json_encode($j));
t('Sandbox-Laufzeit: Wurzel ist das Sandbox-Verzeichnis; aktives Theme liegt dort',is_array($j)&&str_ends_with($j['root'],'/cms/wp-sandbox/themes')&&str_ends_with($j['dirB'],'/wp-sandbox/themes/sbx-b'));
t('Sandbox-Laufzeit: URLs zeigen auf das jeweilige Verzeichnis',is_array($j)&&str_ends_with($j['uriB'],'/cms/wp-sandbox/themes/sbx-b')&&str_ends_with($j['uriLive'],'/cms/wp-content/themes/live-a')&&str_ends_with($j['uriNat'],'/cms/themes/nat-c')&&str_ends_with($j['dirLive'],'/wp-content/themes/live-a'));
t('Sandbox-Laufzeit: Vorschaubild-Pfad und Theme-Liste',is_array($j)&&$j['shot']===''||str_starts_with((string)$j['shot'],'wp-sandbox/themes/'));
t('Sandbox-Laufzeit: wp_themes() kennt alle drei Verzeichnisse',is_array($j)&&count(array_intersect(['sbx-b','live-a','nat-c'],$j['themes']))===3);
t('Sandbox-Laufzeit: kein Mailversand, kein Cron',is_array($j)&&$j['mail']===false&&$j['cron']===0);
system('rm -rf '.escapeshellarg($rt));

system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

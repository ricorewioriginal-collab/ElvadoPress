<?php
// Prüft das Vorinstallieren bekannter WordPress-Themes/-Plugins in der Demo (cms/lib/demo.php: rrw_demo_extras) ohne Netz, mit nachgebauten ZIP-Dateien: php scripts/test-demo-extras.php
declare(strict_types=1);
require_once __DIR__.'/../cms/lib/demo.php';
$fail=0;$n=0;
function t(string $name,callable $fn){global $fail,$n;$n++;try{$fn();echo "  ok  $name\n";}catch(Throwable $e){$fail++;echo "FAIL  $name: ".$e->getMessage()."\n";}}
function eq($a,$b,string $m=''){if($a!==$b)throw new RuntimeException(($m?$m.': ':'').'erwartet '.json_encode($b).', war '.json_encode($a));}
function mkzip(array $files): string { $f=tempnam(sys_get_temp_dir(),'z');$z=new ZipArchive();$z->open($f,ZipArchive::CREATE|ZipArchive::OVERWRITE);foreach($files as $k=>$v)$z->addFromString($k,$v);$z->close();$b=(string)file_get_contents($f);@unlink($f);return $b; }
function rmrf(string $d): void { rrw_demo_extras_rmdir($d); }
function pkg(): string { $d=sys_get_temp_dir().'/dx-'.bin2hex(random_bytes(4));mkdir($d.'/demo',0777,true);file_put_contents($d.'/demo/index.html','<ul><li>A</li><!--demo-extras--></ul>');return $d; }
$pluginZip=fn($slug)=>mkzip(["$slug/$slug.php"=>"<?php\n/* Plugin Name: Test $slug */\n","$slug/readme.txt"=>'x',"$slug/inc/"=>'',"$slug/inc/a.php"=>'<?php']);
$themeZip=fn($slug)=>mkzip(["$slug/style.css"=>"/*\nTheme Name: Test $slug\n*/","$slug/functions.php"=>'<?php',"$slug/parts/header.html"=>'h']);

t('Theme und Plugin werden entpackt, Startseite der Demo wird ergänzt',function() use($pluginZip,$themeZip){
    $d=pkg();$asked=[];
    $r=rrw_demo_extras($d,function(string $u) use(&$asked,$pluginZip,$themeZip){ $asked[]=$u;return str_contains($u,'/theme/')?$themeZip('astra'):$pluginZip('contact-form-7'); },['themes'=>['astra'=>'Astra'],'plugins'=>['contact-form-7'=>'Contact Form 7']]);
    eq($r,['ok'=>['Astra','Contact Form 7'],'failed'=>[]]);
    eq($asked,['https://downloads.wordpress.org/theme/astra.zip','https://downloads.wordpress.org/plugin/contact-form-7.zip']);
    foreach(['cms/wp-content/themes/astra/style.css','cms/wp-content/themes/astra/parts/header.html','cms/wp-content/plugins/contact-form-7/contact-form-7.php','cms/wp-content/plugins/contact-form-7/inc/a.php'] as $f)if(!is_file("$d/$f"))throw new RuntimeException("fehlt: $f");
    $h=(string)file_get_contents($d.'/demo/index.html');
    if(!str_contains($h,'Themes Astra')||!str_contains($h,'Plugins Contact Form 7')||str_contains($h,'<!--demo-extras-->'))throw new RuntimeException($h);
    rmrf($d);
});
t('Zweiter Lauf ersetzt vorhandene Ordner sauber (keine Altdateien)',function() use($pluginZip){
    $d=pkg();mkdir($d.'/cms/wp-content/plugins/contact-form-7',0777,true);file_put_contents($d.'/cms/wp-content/plugins/contact-form-7/alt.php','x');
    rrw_demo_extras($d,fn($u)=>$pluginZip('contact-form-7'),['plugins'=>['contact-form-7'=>'CF7']]);
    eq(is_file($d.'/cms/wp-content/plugins/contact-form-7/alt.php'),false);eq(is_file($d.'/cms/wp-content/plugins/contact-form-7/contact-form-7.php'),true);rmrf($d);
});
t('Fehler stoppen nichts: Download, ungültige Kennung, fehlende Hauptdatei, unsicheres ZIP',function() use($pluginZip){
    $d=pkg();
    $r=rrw_demo_extras($d,function(string $u) use($pluginZip){
        if(str_contains($u,'/ok.zip'))return $pluginZip('ok');
        if(str_contains($u,'/leer.zip'))return mkzip(['leer/readme.txt'=>'x']);
        if(str_contains($u,'/evil.zip'))return mkzip(['evil/evil.php'=>"<?php\n/* Plugin Name: E */",'../../ausserhalb.php'=>'x']);
        if(str_contains($u,'/falsch.zip'))return $pluginZip('anderer-ordner');
        return null;
    },['plugins'=>['ok'=>'Gut','fehlt'=>'Offline','leer'=>'Ohne Datei','evil'=>'Böse','falsch'=>'Falscher Ordner','../x'=>'Unsicher']]);
    eq($r['ok'],['Gut']);eq(array_keys($r['failed']),['Offline','Ohne Datei','Böse','Falscher Ordner','Unsicher']);
    eq($r['failed']['Offline'],'Download fehlgeschlagen');eq($r['failed']['Ohne Datei'],'Hauptdatei fehlt');eq($r['failed']['Unsicher'],'ungültige Kennung');
    eq(is_file(dirname($d).'/ausserhalb.php'),false);eq(is_dir($d.'/cms/wp-content/plugins/evil'),false,'unsicheres ZIP hinterlässt nichts');
    eq(glob($d.'/cms/wp-content/plugins/.stage-*')?:[],[],'keine Zwischenordner');rmrf($d);
});
t('Ohne Erfolg bleibt die Startseite unverändert',function(){
    $d=pkg();$r=rrw_demo_extras($d,fn($u)=>null,['themes'=>['astra'=>'Astra']]);eq($r['ok'],[]);
    eq(str_contains((string)file_get_contents($d.'/demo/index.html'),'<!--demo-extras-->'),true);rmrf($d);
});
t('Liste der Demo: bekannte Themes und Plugins mit gültigen Kennungen',function(){
    foreach(['themes','plugins'] as $g){ if(count(RRW_DEMO_EXTRAS[$g])<2)throw new RuntimeException("zu wenige $g");foreach(RRW_DEMO_EXTRAS[$g] as $slug=>$name)if(!preg_match('/^[a-z0-9][a-z0-9-]{1,60}$/',(string)$slug)||$name==='')throw new RuntimeException($slug); }
});
t('Aktivieren in der Demo bleibt erlaubt, Installieren und Löschen gesperrt',function(){
    foreach(['wp_theme_activate','wp_plugin_activate'] as $a)eq(in_array($a,RRW_DEMO_BLOCKED,true),false,$a);
    foreach(['wp_theme_install','wp_plugin_install','wp_plugin_delete','wp_theme_delete','wp_plugin_upload'] as $a)eq(in_array($a,RRW_DEMO_BLOCKED,true),true,$a);
});
echo "\n".($n-$fail)." von $n Prüfungen bestanden\n";
exit($fail?1:0);

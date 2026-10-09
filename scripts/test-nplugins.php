<?php
// Tests des Plugin-Systems (cms/src/Plugin/): Versionsbedingungen, Manifest, Vertrauen, Installieren/Aktivieren/Deaktivieren/Aktualisieren/Deinstallieren,
// Abhängigkeiten, Kompatibilität, Fehlerfälle mit Rückgängig, Einstellungen, Plugin-API. Arbeitet mit einer eigenen Paketbibliothek in einem temporären Ordner.
declare(strict_types=1);
require_once __DIR__.'/../cms/src/autoload.php';
use Elvado\Plugin\{Fs,Hooks,Manifest,PluginManager,Version};

$n=0;$fail=0;
function t(string $name,bool $ok,string $info=''): void { global $n,$fail;$n++;if(!$ok){$fail++;echo "FAIL  $name".($info!==''?": $info":'')."\n";}else echo "  ok  $name\n"; }

// ---------- Versionsbedingungen
t('Version: >=',Version::satisfies('1.2.0','>=1.0.0')&&!Version::satisfies('0.9.0','>=1.0.0'));
t('Version: ^ und ~',Version::satisfies('1.9.9','^1.2')&&!Version::satisfies('2.0.0','^1.2')&&Version::satisfies('1.2.9','~1.2.3')&&!Version::satisfies('1.3.0','~1.2.3'));
t('Version: Bereich, Joker, *',Version::satisfies('1.5.0','>=1.0 <2.0')&&!Version::satisfies('2.0.0','>=1.0 <2.0')&&Version::satisfies('1.4.0','1.x')&&Version::satisfies('9.9.9','*'));
t('Version: ungültige Bedingung',!Version::validConstraint('>>1')&&!Version::satisfies('1.0.0','abc'));

// ---------- Paketbibliothek für die Tests
$tmp=sys_get_temp_dir().'/elvado-np-'.bin2hex(random_bytes(4));$lib=$tmp.'/lib';$plug=$tmp.'/plugins';$data=$tmp.'/data';
foreach([$lib,$plug,$data] as $d)mkdir($d,0755,true);
function mk(string $lib,string $id,array $m,string $php='',array $extra=[]): void {
    $d=$lib.'/'.$id;if(!is_dir($d))mkdir($d,0755,true);
    file_put_contents($d.'/plugin.json',json_encode($m+['id'=>$id,'name'=>ucfirst($id),'version'=>'1.0.0','author'=>'Test','license'=>'MIT','description'=>'Testplugin'],JSON_UNESCAPED_UNICODE));
    if($php!=='')file_put_contents($d.'/plugin.php',$php);
    foreach($extra as $f=>$c)file_put_contents($d.'/'.$f,$c);
}
function catalog(string $lib,array $planned=[]): void {
    $e=[];foreach(glob($lib.'/*',GLOB_ONLYDIR) as $d){$m=json_decode(file_get_contents($d.'/plugin.json'),true);$e[]=['id'=>basename($d),'name'=>$m['name']??basename($d),'description'=>'','category'=>'t','status'=>'available','hash'=>Fs::treeHash($d)];}
    foreach($planned as $p)$e[]=['id'=>$p,'name'=>$p,'status'=>'planned'];
    file_put_contents($lib.'/catalog.json',json_encode(['plugins'=>$e]));
}
mk($lib,'alpha',['settings'=>[['key'=>'greeting','type'=>'text','default'=>'hallo'],['key'=>'on','type'=>'toggle','default'=>true],['key'=>'mode','type'=>'select','options'=>[['value'=>'a'],['value'=>'b']],'default'=>'a'],['key'=>'token','type'=>'secret'],['key'=>'limit','type'=>'number','default'=>5,'min'=>1,'max'=>10],['key'=>'site','type'=>'url']],'actions'=>[['id'=>'ping','label'=>'Ping']]],
    "<?php\n\$np->on('t_filter',fn(\$v)=>\$v.'+alpha');\n\$np->api('hello',fn(\$a)=>['msg'=>'hi '.(\$a['name']??'')]);\n\$np->api('secret',fn()=>['v'=>\$np->setting('token')],'admin');\n\$np->api('open',fn()=>['ok'=>1],'public');\n\$np->api('ed',fn()=>['ok'=>1],'editor');\n");
mk($lib,'beta',['requires'=>['plugins'=>['alpha'=>'>=1.0.0']]],"<?php\n\$np->on('t_filter',fn(\$v)=>\$v.'+beta');\n");
mk($lib,'gamma',['requires'=>['elvadopress'=>'>=99.0.0']],"<?php\n");
mk($lib,'delta',['version'=>'kaputt'],"<?php\n");
mk($lib,'eps',[],"<?php\nthrow new RuntimeException('Boom beim Laden');\n");
mk($lib,'zeta',[],"<?php\n\$x = ;\n");
mk($lib,'eta',['requires'=>['plugins'=>['nichtda'=>'*']]],"<?php\n");
mk($lib,'theta',['requires'=>['plugins'=>['iota'=>'*']]],"<?php\n");
mk($lib,'iota',['requires'=>['plugins'=>['theta'=>'*']]],"<?php\n");
mk($lib,'kappa',[],"<?php\n\$np->on('t_filter',fn(\$v)=>\$v.'+kappa1');\n");
catalog($lib,['elvado-plan']);
$mgr=new PluginManager($tmp.'/cms',$data,'1.1.0',$lib,$plug);
$st=fn()=>$mgr->state();

// ---------- Katalog und Vertrauen
t('Katalog: verfügbare und geplante Einträge',isset($mgr->catalog()['alpha'])&&$mgr->catalog()['elvado-plan']['status']==='planned');
t('Katalog: ID ist für Dritt-Pakete reserviert',$mgr->reservedId('alpha')&&!$mgr->reservedId('fremd'));
t('Geplantes Plugin lässt sich nicht installieren (ehrliche Meldung)',!$mgr->install('elvado-plan')['ok']&&str_contains($mgr->install('elvado-plan')['message'],'noch nicht verfügbar'));
[$m,$e]=Manifest::normalize(json_decode(file_get_contents($lib.'/delta/plugin.json'),true),'delta');
t('Manifest: ungültige Version wird abgelehnt',$m===null&&$e!==[]);
t('Manifest: Pflichtfelder, Bereinigung und Standardwerte',(function() use($lib){ [$m]=Manifest::normalize(json_decode(file_get_contents($lib.'/alpha/plugin.json'),true),'alpha');return $m!==null&&$m['requires']['elvadopress']==='*'&&$m['update']['source']==='bundled'&&count($m['settings'])===6&&$m['type']==='native'; })());
t('Manifest: ID muss zum Ordner passen',Manifest::normalize(['id'=>'abc','name'=>'x','version'=>'1.0.0'],'xyz')[0]===null);
t('Manifest: ungültige Abhängigkeit/URL/Berechtigung',Manifest::normalize(['id'=>'abc','name'=>'x','version'=>'1.0.0','homepage'=>'http://x','requires'=>['plugins'=>['Bad Id'=>'1']],'capabilities'=>['A B']])[0]===null);

// ---------- Installieren, Abhängigkeiten
$r=$mgr->install('beta');
t('Abhängigkeit: fehlendes Plugin wird angeboten, nicht stillschweigend installiert',!$r['ok']&&str_contains($r['message'],'Alpha')&&$r['needs']===['alpha']&&!$mgr->isInstalled('beta'));
t('Plan: Reihenfolge Abhängigkeit zuerst',$mgr->plan(['beta'])['order']===['alpha','beta']);
$r=$mgr->install('beta',true);
t('Installieren mit Abhängigkeiten',$r['ok']&&$r['installed']===['alpha','beta']&&is_file($plug.'/alpha/plugin.php')&&is_file($plug.'/beta/plugin.json'));
t('Installiert ist noch nicht aktiv',!$mgr->isActive('alpha'));
$rows=array_column($mgr->rows(),null,'id');
t('Übersicht: Status, offiziell, Abhängigkeiten',$rows['alpha']['status']==='installed'&&$rows['alpha']['official']===true&&$rows['beta']['dependencies'][0]['id']==='alpha'&&$rows['elvado-plan']['status']==='planned'&&$rows['gamma']['compat']['ok']===false);
t('Inkompatibles Plugin (ElvadoPress-Version) wird nicht installiert',(function() use($mgr){ $r=$mgr->install('gamma');return !$r['ok']&&str_contains($r['message'],'nicht kompatibel')&&!$mgr->isInstalled('gamma'); })());
t('Ungültiges Manifest: Installation abgelehnt',(function() use($mgr){ $r=$mgr->install('delta');return !$r['ok']&&!$mgr->isInstalled('delta'); })());
t('PHP-Syntaxfehler im Paket: Installation abgelehnt, nichts bleibt zurück',(function() use($mgr,$plug){ $r=$mgr->install('zeta');return !$r['ok']&&str_contains($r['message'],'PHP-Fehler')&&!is_dir($plug.'/zeta')&&!glob($plug.'/.tmp-*'); })());
t('Unbekannte Abhängigkeit wird gemeldet',(function() use($mgr){ $r=$mgr->install('eta');return !$r['ok']&&str_contains($r['message'],'nichtda'); })());
t('Zyklische Abhängigkeit wird erkannt',str_contains(implode(' ',$mgr->plan(['theta'])['errors']),'Zyklische'));
// manipuliertes Paket
file_put_contents($lib.'/kappa/plugin.php',"<?php\n// manipuliert\n");
t('Manipuliertes Paket (Prüfsumme weicht ab) wird nicht installiert',(function() use($mgr){ $r=$mgr->install('kappa');return !$r['ok']&&str_contains($r['message'],'beschädigt'); })());
mk($lib,'kappa',[],"<?php\n\$np->on('t_filter',fn(\$v)=>\$v.'+kappa1');\n");

// ---------- Aktivieren / Deaktivieren
t('Aktivieren ohne aktive Abhängigkeit scheitert verständlich',(function() use($mgr){ $r=$mgr->activate('beta');return !$r['ok']&&str_contains($r['message'],'Alpha'); })());
$r=$mgr->activate('beta',true);
t('Aktivieren mit Abhängigkeiten (Reihenfolge)',$r['ok']&&$r['activated']===['alpha','beta']&&$mgr->isActive('beta'));
Hooks::reset();$mgr2=new PluginManager($tmp.'/cms',$data,'1.1.0',$lib,$plug);$mgr2->boot();
t('Boot lädt aktive Plugins und registriert Hooks',Hooks::filter('t_filter','x')==='x+alpha+beta');
t('Deaktivieren verweigert, solange ein aktives Plugin davon abhängt',(function() use($mgr){ $r=$mgr->deactivate('alpha');return !$r['ok']&&str_contains($r['message'],'Beta')&&$mgr->isActive('alpha'); })());
t('Deinstallieren verweigert: aktiv bzw. von Installiertem benötigt',!$mgr->uninstall('alpha')['ok']&&!$mgr->uninstall('beta')['ok']);
$r=$mgr->deactivate('alpha',true);
t('Deaktivieren mit Kaskade (erst Abhängige)',$r['ok']&&$r['deactivated']===['beta','alpha']&&!$mgr->isActive('beta')&&!$mgr->isActive('alpha'));
Hooks::reset();$mgr3=new PluginManager($tmp.'/cms',$data,'1.1.0',$lib,$plug);$mgr3->boot();
t('Inaktive Plugins registrieren nichts',Hooks::filter('t_filter','x')==='x');

// ---------- Fehlerfälle bei der Aktivierung
$mgr->install('eps');$h=Hooks::snapshot();
$r=$mgr->activate('eps');
t('Aktivierung mit Ausnahme im Plugin schlägt sauber fehl (nicht aktiv, Fehler gemerkt, Hooks verworfen)',!$r['ok']&&str_contains($r['message'],'Boom')&&!$mgr->isActive('eps')&&str_contains($st()['errors']['eps'],'Boom')&&Hooks::snapshot()==$h);
// verändertes installiertes Plugin wird nicht ausgeführt
$mgr->install('kappa');file_put_contents($plug.'/kappa/plugin.php',"<?php\nexit('boese');\n");
$r=$mgr->activate('kappa');
t('Nachträglich verändertes Plugin wird nicht aktiviert/ausgeführt',!$r['ok']&&str_contains($r['message'],'verändert'));
t('Übersicht: verändertes Plugin ist nicht mehr offiziell',array_column($mgr->rows(),null,'id')['kappa']['official']===false);
$mgr->uninstall('kappa');mk($lib,'kappa',[],"<?php\n\$np->on('t_filter',fn(\$v)=>\$v.'+kappa1');\n");

// ---------- Einstellungen
$mgr->activate('alpha');
t('Einstellungen: Vorgaben',$mgr->settings('alpha')['greeting']==='hallo'&&$mgr->settings('alpha')['limit']===5&&$mgr->settings('alpha')['on']===true);
$r=$mgr->saveSettings('alpha',['greeting'=>'Servus','on'=>'0','mode'=>'b','limit'=>99,'token'=>'geheim123','site'=>'https://x.example/','unbekannt'=>'x']);
t('Einstellungen: speichern, bereinigen, begrenzen',$r['ok']&&$mgr->settings('alpha')['greeting']==='Servus'&&$mgr->settings('alpha')['on']===false&&$mgr->settings('alpha')['limit']===10&&!isset($mgr->settings('alpha')['unbekannt']));
t('Einstellungen: Geheimnis wird dem Browser nie geliefert',$mgr->settings('alpha')['token']===''&&$mgr->settings('alpha')['token_set']===true&&$mgr->settings('alpha',true)['token']==='geheim123'&&!str_contains(json_encode($r),'geheim123'));
$mgr->saveSettings('alpha',['token'=>'']);
t('Einstellungen: leeres Geheimnis lässt den Wert unverändert, __clear__ löscht',$mgr->settings('alpha',true)['token']==='geheim123'&&($mgr->saveSettings('alpha',['token'=>'__clear__'])['ok'])&&$mgr->settings('alpha',true)['token']==='');
t('Einstellungen: ungültige Werte werden abgelehnt',!$mgr->saveSettings('alpha',['mode'=>'z'])['ok']&&!$mgr->saveSettings('alpha',['site'=>'http://unsicher.example'])['ok']&&!$mgr->saveSettings('alpha',['limit'=>'abc'])['ok']);
t('Einstellungsdatei nur für den Besitzer lesbar',(fileperms($data.'/.plugins/settings/alpha.json')&0077)===0);

// ---------- Plugin-API
t('API: Aufruf als Admin',$mgr->callApi('alpha','hello',['name'=>'Rico'],'admin')['msg']==='hi Rico');
t('API: Rechte (editor/öffentlich)',$mgr->callApi('alpha','hello',[],'editor')['code']===403&&$mgr->callApi('alpha','secret',[],'editor')['code']===403&&$mgr->callApi('alpha','hello',[],'public')['code']===403&&$mgr->callApi('alpha','open',[],'public')['status']==='ok'&&$mgr->callApi('alpha','ed',[],'editor')['status']==='ok');
t('API: unbekannte Aktion / inaktives Plugin',$mgr->callApi('alpha','nix',[],'admin')['code']===404&&$mgr->callApi('beta','x',[],'admin')['code']===404);
t('Hooks: Fehler in einem Plugin bricht nichts ab',(function(){ Hooks::reset();Hooks::on('x',fn()=>throw new RuntimeException('kaputt'));Hooks::on('x',fn($v)=>$v.'!');return Hooks::filter('x','a')==='a!'&&Hooks::$errors!==[]; })());

// ---------- Aktualisieren
mk($lib,'upd',['version'=>'1.0.0'],"<?php\n\$np->on('t_filter',fn(\$v)=>\$v.'+u1');\n");catalog($lib,['elvado-plan']);
$mgr=new PluginManager($tmp.'/cms',$data,'1.1.0',$lib,$plug);$mgr->install('upd');$mgr->activate('upd');
t('Update: nichts zu tun, wenn aktuell',$mgr->update('upd')['ok']&&str_contains($mgr->update('upd')['message'],'aktuell'));
mk($lib,'upd',['version'=>'1.1.0'],"<?php\n\$np->on('t_filter',fn(\$v)=>\$v.'+u2');\n");catalog($lib,['elvado-plan']);
t('Update: verfügbar wird angezeigt',array_column($mgr->rows(),null,'id')['upd']['update_available']===true);
$r=$mgr->update('upd');
t('Update erfolgreich (Version, Sicherung, aktiv)',$r['ok']&&$mgr->state()['installed']['upd']['version']==='1.1.0'&&$mgr->isActive('upd')&&glob($data.'/.plugins/backup/upd-1.0.0-*')!==[]&&!glob($plug.'/.old-*')&&!glob($plug.'/.tmp-*'));
mk($lib,'upd',['version'=>'1.2.0'],"<?php\nthrow new RuntimeException('neu kaputt');\n");catalog($lib,['elvado-plan']);
$r=$mgr->update('upd');
t('Update mit Fehler beim Laden wird zurückgenommen, alter Stand läuft weiter',!$r['ok']&&str_contains($r['message'],'zurückgenommen')&&$mgr->state()['installed']['upd']['version']==='1.1.0'&&str_contains(file_get_contents($plug.'/upd/plugin.php'),'u2')&&$mgr->isActive('upd')&&!glob($plug.'/.old-*'));
mk($lib,'upd',['version'=>'1.3.0','requires'=>['plugins'=>['alpha'=>'>=5.0.0']]],"<?php\n");catalog($lib,['elvado-plan']);
$r=$mgr->update('upd');
t('Update mit unerfüllter Abhängigkeit wird nicht durchgeführt',!$r['ok']&&str_contains($r['message'],'Alpha')&&$mgr->state()['installed']['upd']['version']==='1.1.0');
mk($lib,'upd',['version'=>'1.4.0'],"<?php\n\$x = ;\n");catalog($lib,['elvado-plan']);
t('Update mit Syntaxfehler wird abgelehnt',!$mgr->update('upd')['ok']&&$mgr->state()['installed']['upd']['version']==='1.1.0');
// Update, das ein abhängiges Plugin unbrauchbar machen würde
mk($lib,'dep1',['requires'=>['plugins'=>['upd'=>'<2.0.0']]],"<?php\n");mk($lib,'upd',['version'=>'2.0.0'],"<?php\n");catalog($lib,['elvado-plan']);
$mgr->install('dep1');
t('Update, das ein abhängiges Plugin unauflösbar machen würde, wird verweigert',(function() use($mgr){ $r=$mgr->update('upd');return !$r['ok']&&str_contains($r['message'],'Dep1'); })());

// ---------- Auswahl aus dem Installer
$r=$mgr->installSelection(['beta','eps','gamma','kappa'],true);
t('Installer-Auswahl: fehlerhafte Plugins überspringen nur sich selbst',in_array('kappa',$r['activated'],true)&&in_array('beta',$r['activated'],true)&&isset($r['failed']['eps'])&&isset($r['failed']['gamma']));

// ---------- Deinstallieren
$mgr->deactivate('kappa');
$r=$mgr->uninstall('kappa');
t('Deinstallieren: Ordner weg, Zustand bereinigt, Sicherung angelegt',$r['ok']&&!is_dir($plug.'/kappa')&&!$mgr->isInstalled('kappa')&&glob($data.'/.plugins/backup/kappa-*')!==[]);
$mgr->saveSettings('alpha',['greeting'=>'x']);$mgr->deactivate('alpha',true);$mgr->uninstall('dep1');$mgr->uninstall('upd');$mgr->uninstall('beta');
$mgr->uninstall('alpha',false);
t('Deinstallieren ohne Löschen behält Einstellungen, mit Löschen nicht',is_file($data.'/.plugins/settings/alpha.json'));
$mgr->install('alpha');$mgr->uninstall('alpha',true);
t('Deinstallieren mit Datenlöschung',!is_file($data.'/.plugins/settings/alpha.json'));

// ---------- Schutz der Plugin-Daten
t('Plugin-Datenordner ist per .htaccess gesperrt',is_file($data.'/.plugins/.htaccess')&&str_contains(file_get_contents($data.'/.plugins/.htaccess'),'denied'));

// ---------- echter Katalog des Releases
$real=new PluginManager(dirname(__DIR__).'/cms',$tmp.'/data2','1.1.0');
t('Release: catalog.json ist aktuell (scripts/build-official-plugins.php --check; im Entwicklungsprojekt ohne dieses Skript übersprungen)',!is_file(__DIR__.'/build-official-plugins.php')||(function(){ exec('php '.escapeshellarg(__DIR__.'/build-official-plugins.php').' --check 2>&1',$o,$c);return $c===0; })());
t('Release: geplante Plugins sind ehrlich als „noch nicht verfügbar“ geführt',(function() use($real){ foreach(['elvado-newsletter','elvado-podcast','elvado-radio','elvado-events','elvado-shop','elvado-social','elvado-consent','elvado-maintenance','elvado-wp-tools'] as $id)if(($real->catalog()[$id]['status']??'')!=='planned')return false;return true; })());

Fs::rmTree($tmp);
echo $fail?"$fail von $n Prüfungen fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

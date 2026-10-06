<?php
// Prüft die App-Vorlage (app-template/) statisch: neutral (keine Hersteller-Inhalte), vollständig, stimmig zum Build-Assistenten im CMS (cms/lib/appbuild.php).
// Das eigentliche Bauen und die Tests der Apps macht scripts/verify-app-template.sh (braucht Android-SDK und .NET). Aufruf: php scripts/test-app-template.php
declare(strict_types=1);
require_once __DIR__.'/../cms/lib/publish.php';
require_once __DIR__.'/../cms/lib/apps.php';
require_once __DIR__.'/../cms/lib/appbuild.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
$T=__DIR__.'/../app-template';
if(!is_dir($T)){ echo "übersprungen: In diesem Repository gibt es keine App-Vorlage (app-template/).\n";exit(0); }   // z. B. in ricorewi-radio (geteilte Tests)
function files(string $dir): array { $o=[];$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS));foreach($it as $f)if($f->isFile())$o[]=$f->getPathname();sort($o);return $o; }
$all=files($T);$rel=fn(string $p)=>substr($p,strlen($T)+1);
$text=array_values(array_filter($all,fn($p)=>!preg_match('/\.(png|jpg|jpeg|webp|ico)$/i',$p)));

t('Vorlage vorhanden und nicht übermäßig groß (ohne Build-Ausgaben)',count($all)>80&&array_sum(array_map('filesize',$all))<3_000_000,count($all).' Dateien');
// Neutralität
$bad=[];foreach($text as $p){ $c=(string)file_get_contents($p);if(preg_match('/ricorewi|senderwelt|anmacha/i',$c))$bad[]=$rel($p); }
t('Keine Hersteller-Inhalte (RicoReWi, SenderWelt, AnMaCha) in der Vorlage',$bad===[],implode(', ',array_slice($bad,0,5)));
t('Keine Schlüssel, Build-Ausgaben oder lokalen Dateien',array_filter($all,fn($p)=>preg_match('~\.(jks|keystore|apk|aab|exe)$|/build/|/bin/|/obj/|/\.gradle/|local\.properties$~',$p))===[]);
t('Marken-Liste ist leer (die Apps trägt das CMS ein)',json_decode((string)file_get_contents($T.'/android/brands.json'),true)===[]);
// Vollständigkeit
foreach(['README.md','ANLEITUNG.md','.gitignore','icon-512.png','android/build.gradle','android/settings.gradle','android/app/build.gradle','android/app/src/main/AndroidManifest.xml',
         'android-app/assets/config/app-config.json','android-app/assets/config/app_icon.png','android-app/assets/config/logo-lockup.png','android-app/assets/config/startscreen.png',
         'windows-native/ElvadoPress.App.Windows.csproj','windows-native/installer.iss','windows-native/WebRuntime.cs','windows-native/WebShellWindow.cs',
         'scripts/create-developer-keystore.sh','scripts/create-developer-keystore.ps1','brands/.gitkeep'] as $f)
    t("Datei vorhanden: $f",is_file($T.'/'.$f));
// Zusammenspiel mit dem Build-Assistenten
t('Workflows des Build-Assistenten sind enthalten',is_file($T.'/.github/workflows/'.RRW_AB_WORKFLOW)&&is_file($T.'/.github/workflows/'.RRW_AB_WORKFLOW_WIN));
t('Windows-Projektdatei liegt dort, wo das CMS sie prüft',is_file($T.'/'.RRW_AB_WIN_PROJECT)&&!is_file($T.'/'.RRW_AB_WIN_PROJECT_OLD));
$wa=(string)file_get_contents($T.'/.github/workflows/'.RRW_AB_WORKFLOW);$ww=(string)file_get_contents($T.'/.github/workflows/'.RRW_AB_WORKFLOW_WIN);
t('Workflows nehmen die Marken-ID als Eingabe „brand“ und lesen android/brands.json',str_contains($wa,'brand:')&&str_contains($ww,'brand:')&&str_contains($wa,'android/brands.json')&&str_contains($ww,'android/brands.json'));
t('Pre-Release-Namen passen zu dem, was das CMS sucht (app-<id>-<nr>, app-<id>-win-<nr>)',str_contains($wa,'TAG="app-$BRAND-$GITHUB_RUN_NUMBER"')&&(bool)preg_match('/app-\$env:BRAND-win-\$env:GITHUB_RUN_NUMBER|app-\$\(\$env:BRAND\)-win-/',$ww)
    &&preg_match(sprintf(RRW_AB_PLATFORMS['android']['tagre'],'meinapp'),'app-meinapp-12')===1&&preg_match(sprintf(RRW_AB_PLATFORMS['windows']['tagre'],'meinapp'),'app-meinapp-win-12')===1);
t('Windows-Workflow verweist auf die Projektdatei der Vorlage',str_contains($ww,basename(RRW_AB_WIN_PROJECT)));
foreach(['icon-512.png','android-app/assets/config/app_icon.png','android-app/assets/config/logo-lockup.png','android-app/assets/config/startscreen.png'] as $f)
    t("Standardbild wird von den Workflows benutzt und existiert: $f",str_contains($wa.$ww,basename($f))&&is_file($T.'/'.$f));
// Felder, die das CMS in android/brands.json schreibt, kennt der Build
$gr=(string)file_get_contents($T.'/android/app/build.gradle');preg_match_all('/\bb\.(\w+)/',$gr,$m1);preg_match_all('/\$b\.(\w+)/',$ww,$m2);$known=array_unique(array_merge($m1[1],$m2[1]));
$cmsKeys=['id','applicationId','appName','launchUrl','site','filePrefix','type','themeColor'];
t('Der Build wertet alle Felder aus, die das CMS schreibt',array_diff($cmsKeys,$known)===[],implode(',',array_diff($cmsKeys,$known)));
t('Zusatzangaben der Radio-App ("radio") werden von Android und Windows gelesen',in_array('radio',$m1[1],true)&&str_contains($ww,'$bb.radio'));
// Quelltext-Struktur Android
$ns='app.elvadopress.client';$jdir=$T.'/android/app/src/main/java/app/elvadopress/client';$java=glob($jdir.'/*.java')?:[];
t('Android: Namensraum in build.gradle und alle Quelldateien stimmen überein',str_contains($gr,"namespace '$ns'")&&count($java)>20&&array_filter($java,fn($f)=>!str_contains((string)file_get_contents($f),"package $ns;"))===[]);
$man=(string)file_get_contents($T.'/android/app/src/main/AndroidManifest.xml');preg_match_all('/android:(?:name|value)="(?:app\.elvadopress\.client)?\.?([A-Z]\w+)"/',$man,$mm);
$missing=array_filter(array_unique($mm[1]),fn($c)=>!is_file($jdir.'/'.$c.'.java')&&!in_array($c,['Material','Theme'],true));
t('Android: Klassen im Manifest existieren',$missing===[],implode(',',$missing));
t('Android: Tests der Website-App sind enthalten',is_file($T.'/android/app/src/test/java/app/elvadopress/client/WebRuntimeTest.java')&&is_file($T.'/android/app/src/test/java/app/elvadopress/client/RadioConfigTest.java'));
// Windows
$cs=(string)file_get_contents($T.'/windows-native/ElvadoPress.App.Windows.csproj');
t('Windows: Namensraum in allen Quelldateien einheitlich',str_contains($cs,'<RootNamespace>ElvadoPress.App.Windows</RootNamespace>')&&array_filter(glob($T.'/windows-native/*.cs')?:[],fn($f)=>!str_contains((string)file_get_contents($f),'namespace ElvadoPress.App.Windows;'))===[]);
t('Windows: Tests (tests/) sind aus dem App-Projekt ausgeschlossen',str_contains($cs,'<Compile Remove="tests\\**" />')&&is_file($T.'/windows-native/tests/RuntimeCheck/Program.cs'));
// Vom Release-ZIP: Export-Skript und Doku
t('Export-Skript und Prüfskript vorhanden',is_file(__DIR__.'/export-app-template.sh')&&is_file(__DIR__.'/verify-app-template.sh'));
$rel=(string)file_get_contents(__DIR__.'/../.github/workflows/release.yml');
t('Release-Workflow hängt app-template.zip an (mit Prüfsumme)',str_contains($rel,'export-app-template.sh --zip=dist/app-template.zip')&&str_contains($rel,'app-template.zip.sha256'));
$doc=(string)file_get_contents($T.'/ANLEITUNG.md');
foreach(['Apps → Eigene App bauen','Apps verwalten','Wartungsmodus','brands.json','create-developer-keystore','Fehlersuche','Website-App','Radio-App'] as $w)t("Anleitung behandelt: $w",str_contains($doc,$w));
t('Anleitung nennt jedes Feld des Formulars (CMS: app-build.js)',(function() use($doc){ $js=(string)file_get_contents(__DIR__.'/../cms/assets/app-build.js');foreach(['App-Typ','Plattformen','App-Name','Marken-ID','Paketname','Website','Dateiname-Anfang','Farbe','App-Icon','Startbild','Kopfzeile','Screenshots'] as $l)if(!str_contains($js,$l)||!str_contains($doc,$l))return false;return true; })());
// Katalog der Vorlagen
$cat=json_decode((string)file_get_contents($T.'/templates.json'),true);$ctypes=array_column($cat['vorlagen']??[],'type');sort($ctypes);$ktypes=array_keys(RRW_AB_TYPES);sort($ktypes);
t('templates.json listet genau die App-Typen des CMS',$ctypes===$ktypes,implode(',',$ctypes).' vs '.implode(',',$ktypes));
$vor=(string)file_get_contents($T.'/VORLAGEN.md');
t('VORLAGEN.md und README nennen jede Vorlage',(function() use($cat,$vor){ $rd=(string)file_get_contents(__DIR__.'/../app-template/README.md');foreach($cat['vorlagen'] as $v)if(!str_contains($vor,$v['name'])||!str_contains($rd,$v['name']))return false;return true; })());
t('Android und Windows kennen den Typ „content“ (Baukasten-App)',str_contains($gr,"'content'")&&str_contains((string)file_get_contents($T.'/windows-native/Brand.cs'),'"content"')&&str_contains($ww,"'content'"));
t('Baukasten-App: Tab-Leiste in Android und Windows, Tests vorhanden',str_contains((string)file_get_contents($jdir.'/WebShellActivity.java'),'setTabs')&&str_contains((string)file_get_contents($T.'/windows-native/WebShellWindow.cs'),'SetTabs')&&str_contains((string)file_get_contents($T.'/windows-native/tests/RuntimeCheck/Program.cs'),'tabs'));
t('Tab-Vorlagen des Katalogs gibt es in der Oberfläche',(function() use($cat){ $js=(string)file_get_contents(__DIR__.'/../cms/assets/apps-manager.js');foreach($cat['vorlagen'] as $v)foreach($v['vorlagen_tabs']??[] as $p)if(!str_contains($js,$p.':{label'))return false;return true; })());
t('Apps melden sich der Website mit dem User-Agent des App-Modus (Format passt zu cms/lib/appmode.php)',(function() use($T,$jdir){ require_once __DIR__.'/../cms/lib/appmode.php';
    $ua='ElvadoPressApp/1.0 (brand=demo; platform=android)';$_GET=[];$_COOKIE=[];$_SERVER['HTTP_USER_AGENT']=$ua;$a=rrw_appmode_detect();
    return $a!==null&&str_contains((string)file_get_contents($jdir.'/WebShellActivity.java'),'" ElvadoPressApp/1.0 (brand=" + BuildConfig.FLAVOR + "; platform=android)"')&&str_contains((string)file_get_contents($T.'/windows-native/WebShellWindow.cs'),'" ElvadoPressApp/1.0 (brand=" + Brand.Id + "; platform=windows)"'); })());
echo $fail?"$fail von $n Prüfungen fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

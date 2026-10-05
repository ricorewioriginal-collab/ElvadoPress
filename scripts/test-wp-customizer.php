<?php
// Prüft den Live-Customizer für WordPress-Themes: Einstellungen eines Test-Themes (customize_register), Prüfen/Bereinigen/Speichern,
// Entwurf ohne Speichern (Vorschau), Menü-Standorte, Rechte. Aufruf: php scripts/test-wp-customizer.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/rrw-wpcz-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/wp-content/themes');mkdir($tmp.'/wp-content/themes/cztest');mkdir($tmp.'/wp-content/themes/czblock');mkdir($tmp.'/wp-content/themes/czblock/templates');mkdir($tmp.'/cms');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('RRW_WP_DATA',$tmp.'/cms/.wp');define('RRW_WP_CMS_DATA',$tmp.'/cms');$_SERVER['HTTP_HOST']='example.test';$_SERVER['REMOTE_ADDR']='203.0.113.5';
file_put_contents($tmp.'/cms/news.json','[]');
file_put_contents($tmp.'/cms/site.json',json_encode(['portal'=>['site_name'=>'Mein Radio','tagline'=>'Hör rein'],
 'menus'=>['top'=>[['id'=>'m1','label'=>'Start','target'=>'system:start','enabled'=>true,'parent_id'=>'']],'bottom'=>[['id'=>'b1','label'=>'Impressum','target'=>'url:https://example.com/i','enabled'=>true,'parent_id'=>'']]],
 'pages'=>[['id'=>'ueber','slug'=>'ueber-uns','title'=>'Über uns','type'=>'custom','enabled'=>true,'blocks_before'=>[['type'=>'html','html'=>'<p>Wir.</p>']],'blocks_after'=>[]]]]));
$siteBefore=file_get_contents($tmp.'/cms/site.json');
$th=$tmp.'/wp-content/themes/cztest';
file_put_contents($th.'/style.css',"/*\nTheme Name: Customizer-Test\nVersion: 1.0\n*/\n");
file_put_contents($th.'/index.php','<?php /* Ausgabe */ ?><html><head><?php wp_head(); ?></head><body class="<?php echo esc_attr(implode(" ",get_body_class())); ?>"><h1 class="site-title"><?php bloginfo("name"); ?></h1><p class="site-desc"><?php bloginfo("description"); ?></p><div id="slogan"><?php echo esc_html(get_theme_mod("cz_slogan","Hallo")); ?></div><div id="acc"><?php echo esc_html(get_theme_mod("cz_accent","#ff0000")); ?></div><div id="box"><?php $o=get_option("cz_opts",[]);echo esc_html((string)($o["boxed"]??"nein")); ?></div><nav><?php wp_nav_menu(["theme_location"=>"primary","fallback_cb"=>false]); ?></nav></body></html>');
file_put_contents($th.'/functions.php',<<<'PHP'
<?php
add_theme_support('custom-background',['default-color'=>'f5f5f5']);
add_theme_support('custom-header',['default-text-color'=>'112233','width'=>1000,'height'=>200]);
add_action('after_setup_theme',function(){ register_nav_menus(['primary'=>'Hauptmenü','footer'=>'Fußbereich']); });
function cztest_sanitize_code($v){ return preg_match('/^[A-Z]{3}$/',(string)$v)?$v:null; }
add_action('customize_register',function($wp){
    $wp->add_section('cz_layout',['title'=>'Layout','priority'=>30]);
    $wp->add_section('cz_zwei',['title'=>'Weiteres','priority'=>40]);
    $wp->add_setting('cz_slogan',['default'=>'Hallo','sanitize_callback'=>'sanitize_text_field']);
    $wp->add_control('cz_slogan',['label'=>'Spruch','section'=>'cz_layout','type'=>'text']);
    $wp->add_setting('cz_about',['default'=>'','sanitize_callback'=>'sanitize_textarea_field']);
    $wp->add_control('cz_about',['label'=>'Über','section'=>'cz_layout','type'=>'textarea']);
    $wp->add_setting('cz_sidebar',['default'=>true]);
    $wp->add_control('cz_sidebar',['label'=>'Seitenleiste','section'=>'cz_layout','type'=>'checkbox']);
    $wp->add_setting('cz_align',['default'=>'left','sanitize_callback'=>'sanitize_key']);
    $wp->add_control('cz_align',['label'=>'Ausrichtung','section'=>'cz_layout','type'=>'radio','choices'=>['left'=>'Links','right'=>'Rechts']]);
    $wp->add_setting('cz_font',['default'=>'serif']);
    $wp->add_control('cz_font',['label'=>'Schrift','section'=>'cz_layout','type'=>'select','choices'=>['serif'=>'Serif','sans'=>'Sans']]);
    $wp->add_setting('cz_accent',['default'=>'#ff0000','sanitize_callback'=>'sanitize_hex_color','transport'=>'postMessage']);
    $wp->add_control(new WP_Customize_Color_Control($wp,'cz_accent',['label'=>'Akzent','section'=>'colors','settings'=>'cz_accent']));
    $wp->add_setting('cz_width',['default'=>20]);
    $wp->add_control('cz_width',['label'=>'Breite','section'=>'cz_layout','type'=>'range','input_attrs'=>['min'=>10,'max'=>50,'step'=>5]]);
    $wp->add_setting('cz_count',['default'=>3,'sanitize_callback'=>'absint']);
    $wp->add_control('cz_count',['label'=>'Anzahl','section'=>'cz_zwei','type'=>'number','input_attrs'=>['min'=>1,'max'=>9]]);
    $wp->add_setting('cz_hero',['default'=>'']);
    $wp->add_control('cz_hero',['label'=>'Heldenbild','section'=>'cz_zwei','type'=>'image']);
    $wp->add_setting('cz_page',['default'=>0,'sanitize_callback'=>'absint']);
    $wp->add_control('cz_page',['label'=>'Seite','section'=>'cz_zwei','type'=>'dropdown-pages']);
    $wp->add_setting('cz_opts[boxed]',['type'=>'option','default'=>'nein']);
    $wp->add_control('cz_opts[boxed]',['label'=>'Kasten','section'=>'cz_zwei','type'=>'select','choices'=>['ja'=>'Ja','nein'=>'Nein']]);
    $wp->add_setting('cz_code',['default'=>'ABC','sanitize_callback'=>'cztest_sanitize_code']);
    $wp->add_control('cz_code',['label'=>'Kürzel','section'=>'cz_zwei','type'=>'text']);
    $wp->add_setting('cz_weird',['default'=>'']);
    $wp->add_control('cz_weird',['label'=>'Seltsam','section'=>'cz_zwei','type'=>'cz-weird']);
    $wp->add_setting('cz_url',['default'=>'','sanitize_callback'=>'esc_url_raw']);
    $wp->add_control('cz_url',['label'=>'Adresse','section'=>'cz_zwei','type'=>'url']);
});
PHP);
file_put_contents($tmp.'/wp-content/themes/czblock/style.css',"/*\nTheme Name: Block-Test\nVersion: 1.0\n*/\n");
file_put_contents($tmp.'/wp-content/themes/czblock/templates/index.html','<!-- wp:paragraph --><p>Block</p><!-- /wp:paragraph -->');
define('RRW_WP_TEST_NATIVE',dirname(__DIR__).'/cms/themes');
// Zweiter Lauf im Unterprozess: Block-Theme (functions.php wird nur beim Start geladen)
$child=($argv[1]??'')==='--block';
require __DIR__.'/_testdb.php';
require __DIR__.'/../cms/wp/load.php';require __DIR__.'/../cms/wp/router.php';require __DIR__.'/../cms/wp/installer.php';require __DIR__.'/../cms/wp/customizer-api.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
$GLOBALS['RRW_SITE']=json_decode($siteBefore,true);
function boot_user(): array { return ['id'=>1,'login'=>'admin','name'=>'Admin','role'=>'administrator']; }

if($child){
    t('Block-Theme wählbar',rrw_wpc_use_theme('czblock'));
    rrw_wp_boot(['theme'=>true,'admin'=>true,'user'=>boot_user()]);
    $d=rrw_wpc_describe('czblock');
    t('Block-Theme erkannt (Hinweis auf Website-Editor)',$d['theme']['block_theme']===true);
    t('Block-Theme: Website-Informationen vorhanden',$d['sections'][0]['title']==='Website-Informationen');
    system('rm -rf '.escapeshellarg($tmp));
    echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);
}

/* Theme wählen (vor dem Start) */
t('Unbekanntes Theme wird abgelehnt',rrw_wpc_use_theme('gibtsnicht')===false&&rrw_wpc_use_theme('../x')===false);
t('Bekanntes Theme einschaltbar',rrw_wpc_use_theme('cztest')===true);
rrw_wp_boot(['theme'=>true,'admin'=>true,'user'=>boot_user()]);
t('Vorschau-Theme ist das aktive Stylesheet dieser Anfrage',get_stylesheet()==='cztest'&&get_template()==='cztest');

/* Einstellungen auflisten */
$d=rrw_wpc_describe('cztest');
$byId=[];foreach($d['sections'] as $s)foreach($s['controls'] as $c)$byId[$c['id']]=$c+['_sec'=>$s['title']];
t('Theme-Name',$d['theme']['name']==='Customizer-Test'&&$d['theme']['slug']==='cztest'&&$d['theme']['block_theme']===false);
t('Erster Abschnitt: Website-Informationen mit Titel und Untertitel',$d['sections'][0]['title']==='Website-Informationen'&&$byId['blogname']['value']==='Mein Radio'&&$byId['blogdescription']['value']==='Hör rein');
t('Titel und Untertitel sind live änderbar',!empty($byId['blogname']['live'])&&!empty($byId['blogdescription']['live']));
t('Hinweis zu Logo/Icon statt Steuerelement',isset($byId['_note_logo'])&&$byId['_note_logo']['type']==='note'&&!isset($byId['custom_logo'])&&!isset($byId['site_icon']));
t('Text',($byId['cz_slogan']['type']??'')==='text'&&$byId['cz_slogan']['value']==='Hallo');
t('Textfeld',($byId['cz_about']['type']??'')==='textarea');
t('Checkbox mit Standardwert true',($byId['cz_sidebar']['type']??'')==='checkbox'&&$byId['cz_sidebar']['value']===true);
t('Radio mit Auswahl',($byId['cz_align']['type']??'')==='radio'&&array_column($byId['cz_align']['choices'],0)===['left','right']);
t('Auswahl',($byId['cz_font']['type']??'')==='select'&&count($byId['cz_font']['choices'])===2);
t('Farbe im Abschnitt Farben mit Standardwert',($byId['cz_accent']['type']??'')==='color'&&$byId['cz_accent']['value']==='#ff0000'&&$byId['cz_accent']['_sec']==='Farben');
t('Bereich mit Grenzen',($byId['cz_width']['type']??'')==='range'&&$byId['cz_width']['min']===10.0&&$byId['cz_width']['max']===50.0&&$byId['cz_width']['step']===5.0);
t('Zahl',($byId['cz_count']['type']??'')==='number'&&(int)$byId['cz_count']['value']===3);
t('Bild-Adresse',($byId['cz_hero']['type']??'')==='image');
t('Seitenauswahl: „keine Seite“ und die CMS-Seite',count($byId['cz_page']['choices']??[])>=2&&$byId['cz_page']['choices'][0][0]==='0'&&in_array('Über uns',array_column($byId['cz_page']['choices'],1),true));
t('Verschachtelte Option (name[schlüssel])',($byId['cz_opts[boxed]']['value']??'')==='nein');
t('Url-Feld wird als Text geführt',($byId['cz_url']['type']??'')==='text');
t('Nicht unterstütztes Steuerelement zählt als „nicht bearbeitbar“',!isset($byId['cz_weird'])&&$d['unsupported']===1);
t('Hintergrundfarbe (custom-background) mit Standard des Themes',($byId['background_color']['value']??'')==='#f5f5f5');
t('Kopf-Textfarbe (custom-header) mit Standard des Themes',($byId['header_textcolor']['value']??'')==='#112233');
t('Hintergrundbild/Kopfbild als Bild-Steuerelemente',($byId['background_image']['type']??'')==='image'&&($byId['header_image']['type']??'')==='image');
$titles=array_column($d['sections'],'title');
t('Abschnitt Layout vor Weiteres (Priorität)',array_search('Layout',$titles)<array_search('Weiteres',$titles));
t('Reihenfolge wie in WordPress: Menüs, Widgets, Startseite … Zusätzliches CSS',array_search('Menüs',$titles)<array_search('Widgets',$titles)&&array_search('Widgets',$titles)<array_search('Startseiten-Einstellungen',$titles)&&end($titles)==='Zusätzliches CSS');
t('Menü-Standorte: zwei Auswahlfelder',($byId['nav_menu_locations[primary]']['type']??'')==='select'&&isset($byId['nav_menu_locations[footer]']));
t('Menü-Auswahl: vorhandene CMS-Menüs',array_column($byId['nav_menu_locations[primary]']['choices'],0)===['','top','bottom']);
t('Menü-Standorte mit Standardzuordnung',$byId['nav_menu_locations[primary]']['value']==='top'&&$byId['nav_menu_locations[footer]']['value']==='bottom');

/* Prüfen */
$v=rrw_wpc_validate(['cz_accent'=>'#ABC','cz_width'=>'99','cz_count'=>'x','cz_align'=>'mitte','cz_code'=>'abcd','nein_nicht'=>'1','cz_page'=>'99999','cz_hero'=>'javascript:alert(1)','cz_url'=>'javascript:alert(1)']);
t('Kurzfarbe wird zu #aabbcc',($v['ok']['cz_accent']??'')==='#aabbcc');
t('Bereich wird auf Maximum begrenzt',($v['ok']['cz_width']??null)===50);
t('Keine Zahl wird abgelehnt',isset($v['errors']['cz_count']));
t('Auswahl außerhalb der Liste wird abgelehnt',isset($v['errors']['cz_align']));
t('sanitize_callback des Themes (null) lehnt ab',isset($v['errors']['cz_code']));
t('Unbekannte Einstellung wird abgelehnt (keine freie Schreibweise)',isset($v['errors']['nein_nicht']));
t('Unbekannte Seite wird abgelehnt',isset($v['errors']['cz_page']));
t('Gefährliche Bildadresse wird abgelehnt',isset($v['errors']['cz_hero']));
t('Gefährliche Link-Adresse wird abgelehnt',isset($v['errors']['cz_url']));
$v2=rrw_wpc_validate(['cz_slogan'=>'<b>Hi</b><script>x</script>','cz_about'=>"Zeile1\n<i>Zeile2</i>",'cz_sidebar'=>false,'cz_code'=>'XYZ','blogname'=>'  <i>Neu</i> Radio ','blogdescription'=>str_repeat('x',300)]);
t('Text wird von Tags befreit',($v2['ok']['cz_slogan']??'')==='Hi');
t('Textfeld behält Zeilenumbruch, Tags entfernt',($v2['ok']['cz_about']??'')==="Zeile1\nZeile2");
t('Checkbox aus → 0',($v2['ok']['cz_sidebar']??null)===0);
t('Gültiges Kürzel passiert die Theme-Prüfung',($v2['ok']['cz_code']??'')==='XYZ');
t('Titel wird bereinigt',($v2['ok']['blogname']??'')==='Neu Radio');
t('Untertitel wird auf 200 Zeichen gekürzt',mb_strlen((string)($v2['ok']['blogdescription']??''))===200);
t('Leerer Titel wird abgelehnt',isset(rrw_wpc_validate(['blogname'=>'  '])['errors']['blogname']));
t('Menü-Auswahl außerhalb der CMS-Menüs wird abgelehnt',isset(rrw_wpc_validate(['nav_menu_locations[primary]'=>'geheim'])['errors']['nav_menu_locations[primary]']));
t('Menü-Standort, den das Theme nicht kennt, wird abgelehnt',isset(rrw_wpc_validate(['nav_menu_locations[xyz]'=>'top'])['errors']['nav_menu_locations[xyz]']));

/* Entwurf: ändert die Vorschau, nicht die Speicherung */
$optsFile=RRW_WP_DATA.'/options.json';$snap=function() use($tmp){ $o='';foreach(glob(RRW_WP_DATA.'/*')?:[] as $f)if(is_file($f)&&!str_ends_with($f,'.log'))$o.=basename($f).md5_file($f);return $o; };
$optsBefore=$snap();
$id=rrw_wpc_new_id();
$r=rrw_wpc_validate(['cz_slogan'=>'Entwurf!','cz_accent'=>'#00ff00','cz_opts[boxed]'=>'ja','blogname'=>'Entwurfs-Radio','nav_menu_locations[primary]'=>'bottom']);
t('Entwurf ohne Fehler',!$r['errors']);
t('Entwurf wird abgelegt',rrw_wpc_draft_save($id,'cztest',$r['ok'])===5&&is_file(rrw_wpc_draft_dir().'/'.$id.'.json'));
t('Entwurf nur für das gleiche Theme lesbar',rrw_wpc_draft_load($id,'cztest')!==null&&rrw_wpc_draft_load($id,'czblock')===null&&rrw_wpc_draft_load('zz','cztest')===null);
t('Ungültige Entwurfs-ID wird nicht geschrieben',rrw_wpc_draft_save('../../x','cztest',[])===0);
t('Vor dem Anwenden: gespeicherte Werte',get_theme_mod('cz_slogan','Hallo')==='Hallo'&&get_option('blogname')==='Mein Radio');
rrw_wpc_draft_apply(rrw_wpc_draft_load($id,'cztest'));
$page=rrw_wp_dispatch('/','GET',[],[]);$body=$page['body'];
t('Vorschau zeigt Entwurfs-Spruch',str_contains($body,'<div id="slogan">Entwurf!</div>'),substr($body,0,300));
t('Vorschau zeigt Entwurfs-Farbe',str_contains($body,'<div id="acc">#00ff00</div>'));
t('Vorschau zeigt Entwurfs-Option (verschachtelt)',str_contains($body,'<div id="box">ja</div>'));
t('Vorschau zeigt Entwurfs-Titel',str_contains($body,'Entwurfs-Radio'));
t('Vorschau nutzt das Entwurfs-Menü (Fußmenü statt Hauptmenü)',str_contains($body,'Impressum')&&!str_contains($body,'>Start<'));
t('Live-Skript prüft den Ursprung der Nachricht',str_contains(rrw_wpc_live_script(),'e.origin!==location.origin'));
t('Entwurf speichert nichts (Optionen unverändert)',$optsBefore===$snap());
t('Entwurf speichert nichts (site.json unverändert)',file_get_contents($tmp.'/cms/site.json')===$siteBefore);
t('Entwurf abgelaufen (älter als 15 Minuten) → nicht mehr lesbar',(function() use($id){ $f=rrw_wpc_draft_dir().'/'.$id.'.json';touch($f,time()-1000);$ok=rrw_wpc_draft_load($id,'cztest')===null;touch($f);return $ok; })());

/* Speichern: Fehler → nichts gespeichert; sonst alles; nur Geändertes */
$bad=rrw_wpc_save(['cz_slogan'=>'Neu','cz_align'=>'mitte']);
t('Ein Fehler → nichts wird gespeichert',$bad['saved']===[]&&isset($bad['errors']['cz_align'])&&$bad['unchanged']===[]);
// Der Entwurfs-Filter dieser Anfrage würde Lesewerte verfälschen: Speichertests laufen deshalb in einem frischen Unterprozess (siehe unten)
t('Entwurfs-Dateien liegen im geschützten Datenordner',str_starts_with(rrw_wpc_draft_dir(),RRW_WP_DATA));

/* Rechte: ohne Anmeldung liefert die API 401; die Aktionen liegen im Administrator-Zweig (rrw_auth(true)) */
$api=file_get_contents(__DIR__.'/../cms/api.php');
$posAuth=strpos($api,'$wpUser=rrw_auth(true);');$posCz=strpos($api,"'wp_theme_customize_save'");
t('Customizer-Aktionen stehen hinter der Administrator-Prüfung',$posAuth!==false&&$posCz!==false&&$posAuth<$posCz&&strpos($api,"if(str_starts_with(\$action,'wp_')){")<$posAuth);
$code='$_GET["action"]="wp_theme_customize";$_SERVER["REQUEST_METHOD"]="POST";$_SERVER["HTTP_HOST"]="localhost";chdir(getenv("T_CMS"));include getenv("T_CMS")."/api.php";';
foreach(['wp_theme_customize','wp_theme_customize_draft','wp_theme_customize_save','wp_theme_customize_changeset'] as $act){
    $p=proc_open([PHP_BINARY,'-d','display_errors=0','-r',str_replace('"wp_theme_customize"','"'.$act.'"',$code)],[1=>['pipe','w'],2=>['pipe','w']],$pipes,null,['T_CMS'=>realpath(__DIR__.'/../cms'),'PATH'=>getenv('PATH')?:'/usr/bin']);
    $out=stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);proc_close($p);$j=json_decode($out,true);
    t("Ohne Anmeldung abgewiesen: $act",is_array($j)&&($j['status']??'')==='error'&&!isset($j['sections']));
}

/* Speichern in frischen Unterprozessen (ohne Entwurfs-Filter) */
$run=function(string $php) use($tmp): string {
    $f=$tmp.'/run-'.bin2hex(random_bytes(3)).'.php';
    file_put_contents($f,"<?php\ndeclare(strict_types=1);define('WP_CONTENT_DIR','$tmp/wp-content');define('RRW_WP_DATA','$tmp/cms/.wp');define('RRW_WP_CMS_DATA','$tmp/cms');\$_SERVER['HTTP_HOST']='example.test';\$_SERVER['REMOTE_ADDR']='203.0.113.5';\n"
        ."require '".__DIR__."/../cms/wp/load.php';require '".__DIR__."/../cms/wp/router.php';require '".__DIR__."/../cms/wp/installer.php';require '".__DIR__."/../cms/wp/customizer-api.php';\n"
        .'$GLOBALS["RRW_SITE"]=json_decode((string)file_get_contents("'.$tmp.'/cms/site.json"),true);rrw_wpc_use_theme("cztest");rrw_wp_boot(["theme"=>true,"admin"=>true,"user"=>["id"=>1,"login"=>"admin","name"=>"A","role"=>"administrator"]]);'."\n".$php);
    $o=(string)shell_exec(escapeshellarg(PHP_BINARY).' -d display_errors=1 '.escapeshellarg($f).' 2>&1');@unlink($f);return $o;
};
if(getenv('RRW_TEST_MYSQL'))$run=fn($p)=>'SKIP';
$out=$run('$r=rrw_wpc_save(["cz_slogan"=>"Gespeichert","cz_accent"=>"#123456","cz_width"=>"35","cz_sidebar"=>false,"cz_opts[boxed]"=>"ja","nav_menu_locations[primary]"=>"bottom","cz_page"=>"0","blogdescription"=>"Neuer Untertitel","background_color"=>"#eeeeee"]);echo json_encode($r);');
$sv=json_decode($out,true);
t('Speichern ohne Fehler',is_array($sv)&&$sv['errors']===[]&&count($sv['saved'])>=8,$out);
$out2=$run('echo json_encode(["s"=>get_theme_mod("cz_slogan"),"a"=>get_theme_mod("cz_accent"),"w"=>get_theme_mod("cz_width"),"sb"=>get_theme_mod("cz_sidebar","d"),"o"=>get_option("cz_opts"),"m"=>get_nav_menu_locations(),"d"=>get_option("blogdescription"),"bg"=>get_theme_mod("background_color"),"n"=>get_option("blogname"),"mods"=>array_keys(get_theme_mods())]);');
$g=json_decode($out2,true);
t('Gespeicherter Text (theme_mod)',($g['s']??'')==='Gespeichert');
t('Gespeicherte Farbe',($g['a']??'')==='#123456');
t('Gespeicherter Bereich (Zahl)',($g['w']??null)===35);
t('Gespeicherte Checkbox (aus = 0, nicht „Standard“)',($g['sb']??'d')===0);
t('Verschachtelte Option gespeichert',($g['o']['boxed']??'')==='ja');
t('Menü-Standort gespeichert, anderer Standort unverändert',($g['m']['primary']??'')==='bottom'&&($g['m']['footer']??'')==='bottom');
t('Untertitel gespeichert',($g['d']??'')==='Neuer Untertitel');
t('Hintergrundfarbe ohne „#“ gespeichert (WordPress-Schreibweise)',($g['bg']??'')==='eeeeee');
t('Titel unverändert, wenn nicht geändert',($g['n']??'')==='Mein Radio');
$out3=$run('$r=rrw_wpc_save(["cz_slogan"=>"Gespeichert","cz_width"=>35]);echo json_encode($r);');
$s3=json_decode($out3,true);
t('Gleiche Werte erneut → „unverändert“, nichts geschrieben',is_array($s3)&&$s3['saved']===[]&&in_array('cz_slogan',$s3['unchanged'],true),$out3);
$out4=$run('$r=rrw_wpc_save(["cz_code"=>"nope"]);echo json_encode([$r,get_theme_mod("cz_code","ABC")]);');
t('Theme-Prüfung (sanitize_callback) verhindert Speichern',str_contains($out4,'"saved":[]')&&str_contains($out4,'"ABC"'),$out4);
// Entwurf nach dem Speichern: Gespeichertes bleibt unverändert, Seite zeigt die gespeicherten Werte
$out5=$run('$p=rrw_wp_dispatch("/","GET",[],[]);echo str_contains($p["body"],"Gespeichert")?"JA":"NEIN";');
t('Website zeigt nach dem Veröffentlichen die gespeicherten Werte',trim($out5)==='JA',$out5);
t('Titel in site.json (Schreibbrücke aus) unverändert',file_get_contents($tmp.'/cms/site.json')===$siteBefore);

/* Block-Theme im Unterprozess */
$o=(string)shell_exec('RRW_X=1 '.escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' --block 2>&1');
// Das Kind legt eigene Testdaten an; Ausgabe auswerten
/* Startseiten-Einstellungen, Zusätzliches CSS, gespeicherte Entwürfe und geplante Änderungen */
$out5=$run('$d=rrw_wpc_describe("cztest");$ids=[];foreach($d["sections"] as $s)foreach($s["controls"] as $c)$ids[]=$c["id"];
$r=rrw_wpc_save(["show_on_front"=>"page","page_on_front"=>"0","custom_css"=>"body{color:red}</style><script>alert(1)</script>"]);
$h=rrw_wp_dispatch("/");
echo json_encode(["ids"=>$ids,"r"=>$r,"sof"=>get_option("show_on_front"),"css"=>get_theme_mod("custom_css_post_id_text"),"html"=>str_contains((string)$h["body"],"wp-custom-css")&&str_contains((string)$h["body"],"body{color:red}")&&!str_contains((string)$h["body"],"<script>alert")]);');
$j5=json_decode($out5,true);
t('Startseiten-Einstellungen und CSS-Feld werden angeboten',is_array($j5)&&count(array_intersect(['show_on_front','page_on_front','page_for_posts','custom_css','_go_widgets','_go_menus'],$j5['ids']))===6,$out5);
t('Startseiten-Einstellung gespeichert',($j5['sof']??'')==='page'&&($j5['r']['errors']??1)===[]);
t('Zusätzliches CSS: <style>/<script> entfernt, Ausgabe im Seitenkopf',($j5['css']??'')==='body{color:red}alert(1)'&&($j5['html']??false)===true,$out5);
$out6=$run('$id=str_repeat("ab",16);
$e1=rrw_wpc_cs_save($id,"cztest",["cz_slogan"=>"Plan"],"future",time()-5);
$e2=rrw_wpc_cs_save($id,"cztest",[],"draft");
$e3=rrw_wpc_cs_save($id,"cztest",["cz_slogan"=>"Entwurf"],"draft");
$lat=rrw_wpc_cs_latest("cztest");$none=rrw_wpc_cs_latest("anders");
$tmp=rrw_wpc_draft_dir()."/".$id.".json";@mkdir(rrw_wpc_draft_dir(),0775,true);file_put_contents($tmp,"{}");touch($tmp,time()-5000);
$fb=rrw_wpc_draft_load($id,"cztest");$fbOther=rrw_wpc_draft_load($id,"anders");
$e4=rrw_wpc_cs_save($id,"cztest",["cz_slogan"=>"Geplant"],"future",time()+3600);
$ix1=is_file(rrw_wpc_cs_dir()."/future.idx");$n0=rrw_wpc_cs_run_due();$still=get_theme_mod("cz_slogan","-");
$f=rrw_wpc_cs_dir()."/".$id.".json";$d=json_decode(file_get_contents($f),true);$d["date"]=time()-10;file_put_contents($f,json_encode($d));rrw_wpc_cs_reindex();
$n1=rrw_wpc_cs_run_due();$n2=rrw_wpc_cs_run_due();
echo json_encode(["e1"=>$e1,"e2"=>$e2,"e3"=>$e3,"lat"=>$lat["status"]??null,"none"=>$none,"fb"=>$fb!==null,"fbo"=>$fbOther,"e4"=>$e4,"ix"=>$ix1,"n0"=>$n0,"still"=>$still,"n1"=>$n1,"n2"=>$n2,"now"=>get_theme_mod("cz_slogan","-"),"gone"=>!is_file($f),"ix2"=>is_file(rrw_wpc_cs_dir()."/future.idx"),"bad"=>rrw_wpc_cs_save("zzz","cztest",["a"=>1],"draft")]);');
$j6=json_decode($out6,true);
t('Planen in der Vergangenheit, leere Änderungen, ungültige ID werden abgewiesen',is_array($j6)&&is_string($j6['e1']??null)&&is_string($j6['e2']??null)&&is_string($j6['bad']??null),$out6);
t('Entwurf speichern und für das Theme wiederfinden',is_array($j6)&&array_key_exists('e3',$j6)&&$j6['e3']===null&&($j6['lat']??'')==='draft'&&array_key_exists('none',$j6)&&$j6['none']===null,$out6);
t('Abgelaufene Vorschau-Datei → gespeicherter Entwurf bleibt für das Theme lesbar',($j6['fb']??false)===true&&array_key_exists('fbo',$j6)&&$j6['fbo']===null);
t('Geplant: noch nicht fällig → nichts veröffentlicht',array_key_exists('e4',$j6)&&$j6['e4']===null&&($j6['ix']??false)===true&&($j6['n0']??1)===0&&($j6['still']??'')==='Gespeichert',$out6);
t('Fällige Planung wird einmal veröffentlicht, danach entfernt',($j6['n1']??0)===1&&($j6['n2']??1)===0&&($j6['now']??'')==='Geplant'&&($j6['gone']??false)===true&&($j6['ix2']??true)===false,$out6);
$tk=rrw_wpi_preview_token('cztest',1000,7*86400);[$ts,$te]=explode('.',$tk);
t('Teilbarer Vorschau-Link gilt 7 Tage, Standard bleibt 15 Minuten',(int)$te===1000+7*86400&&(int)explode('.',rrw_wpi_preview_token('cztest',1000))[1]===1900);
t('Block-Theme-Lauf (Unterprozess)',preg_match('/(\d+) von (\d+) Prüfungen bestanden/',$o,$mm)&&$mm[1]===$mm[2],$o);
$n+=isset($mm[2])?(int)$mm[2]:0;

system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

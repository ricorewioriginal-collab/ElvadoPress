<?php
// Prüft die Grundlagen für React-Verwaltungsseiten: Kernressourcen-Installer (mit kleinem Test-ZIP), Skript-Registrierung, Abhängigkeiten im Head,
// Rahmen-Dokument, REST-Brücke. Aufruf: php scripts/test-wp-react.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/rrw-react-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/cms');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('RRW_WP_DATA',$tmp.'/cms/.wp');define('RRW_WP_CMS_DATA',$tmp.'/cms');define('RRW_WP_CORE_DIR',$tmp.'/core');define('RRW_WP_CORE_URL','http://example.test/cms/wp-core');
$_SERVER['HTTP_HOST']='example.test';file_put_contents($tmp.'/cms/site.json','{}');$GLOBALS['RRW_SITE']=[];
require __DIR__.'/_testdb.php';
require __DIR__.'/../cms/wp/load.php';require_once __DIR__.'/../cms/wp/coreassets.php';require __DIR__.'/../cms/wp/admin.php';require __DIR__.'/../cms/wp/rest.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
// Test-ZIP wie wordpress.org/latest.zip (nur die benötigten Pfade)
$zip=$tmp.'/wp.zip';$z=new ZipArchive();$z->open($zip,ZipArchive::CREATE);
$z->addFromString('wordpress/wp-includes/version.php','<?php $wp_version = \'9.9.9\';');
$pk="<?php return array('element.js'=>array('dependencies'=>array('react','react-dom'),'version'=>'abc'),'api-fetch.js'=>array('dependencies'=>array('wp-i18n'),'version'=>'def'),'i18n.js'=>array('dependencies'=>array(),'version'=>'ghi'),'date.js'=>array('dependencies'=>array('moment'),'version'=>'j'),'core-data.js'=>array('dependencies'=>array(),'version'=>'k'));";
$z->addFromString('wordpress/wp-includes/assets/script-loader-packages.php',$pk);
foreach(['element','api-fetch','i18n','date','core-data'] as $f)$z->addFromString("wordpress/wp-includes/js/dist/$f.min.js","/*$f*/");
foreach(['react','react-dom','moment','lodash','wp-polyfill','regenerator-runtime'] as $f)$z->addFromString("wordpress/wp-includes/js/dist/vendor/$f.min.js","/*$f*/");
$z->addFromString('wordpress/wp-includes/css/dist/components/style.min.css','.a{}');
$z->addFromString('wordpress/wp-includes/js/jquery/ui/core.min.js','/*core*/');$z->addFromString('wordpress/wp-includes/js/jquery/ui/mouse.min.js','/*mouse*/');
$z->addFromString('wordpress/wp-includes/js/jquery/jquery.min.js','/*jq*/');
$z->addFromString('wordpress/wp-includes/js/dist/development/react-refresh.js','/*böse*/');
$z->addFromString('wordpress/wp-includes/js/../../evil.min.js','/*evil*/');
$z->addFromString('wordpress/wp-config-sample.php','<?php');
$z->close();
t('Vor der Installation nicht bereit',!rrw_wp_core_ready());
$r=rrw_wp_core_install($zip);
t('Installation erfolgreich',!empty($r['ok']),json_encode($r));
t('Version gelesen',($r['version']??'')==='9.9.9');
t('Bereit',rrw_wp_core_ready());
t('Nur Erlaubtes entpackt',!file_exists(RRW_WP_CORE_DIR.'/js/dist/development')&&!file_exists($tmp.'/evil.min.js')&&!file_exists(RRW_WP_CORE_DIR.'/../wp-config-sample.php'));
t('.htaccess sperrt PHP',str_contains((string)@file_get_contents(RRW_WP_CORE_DIR.'/.htaccess'),'Require all denied'));
rrw_wp_boot(['theme'=>true,'admin'=>true,'user'=>['id'=>1,'login'=>'admin','name'=>'a','role'=>'administrator']]);
rrw_wp_admin_init();
t('wp-element registriert',wp_script_is('wp-element','registered'));
t('Abhängigkeiten aus Paketliste',in_array('react-dom',$GLOBALS['rrw_wp_scripts']['reg']['wp-element']['deps'],true));
t('jQuery-UI-Widget-Alias',wp_script_is('jquery-ui-widget','registered'));
t('Stil registriert',wp_style_is('wp-components','registered'));
// Abhängigkeiten gelten als „eingereiht“
wp_enqueue_script('meine-seite','http://example.test/x.js',['wp-element'],'1',true);
t('Abhängigkeit zählt als eingereiht',wp_script_is('wp-element','enqueued')&&wp_script_is('react','enqueued')&&!wp_script_is('wp-date','enqueued'));
// do_action ohne Argumente übergibt wie WordPress einen leeren String
$got=null;add_action('rrw_test_action',function($a) use(&$got){ $got=$a; });do_action('rrw_test_action');
t('do_action ohne Argumente → leerer String',$got==='');
// Rahmen-Dokument
$GLOBALS['rrw_wp_menu']['rrw-test']=['title'=>'Test','menu'=>'Test','cap'=>'manage_options','cb'=>function(){ echo '<div id="root">Hallo</div>'; },'parent'=>'','plugin'=>'','hook'=>'toplevel_page_rrw-test'];
$res=rrw_wp_admin_page(['page'=>'rrw-test']);
t('Seite gerendert',!empty($res['ok'])&&str_contains($res['html'],'id="root"'));
t('Skripte im Dokument',str_contains($res['html'],'wp-core/js/dist/element.min.js'));
t('Abgeschotteter Speicher + REST-Brücke',str_contains($res['html'],'rrwRestFetch')&&str_contains($res['html'],'sessionStorage'));
t('Skip-Link und Menü-Gerüst',str_contains($res['html'],'href="#wpbody-content"')&&str_contains($res['html'],'id="adminmenu"'));
t('Query für die Rahmen-Adresse',($res['frame_query']??'')==='page=rrw-test');
$u=rrw_wp_admin_frame_store('<p>x</p>');
t('Rahmen-Adresse gespeichert',(bool)preg_match('#^wp/frame\.php\?f=[a-f0-9]{32}$#',$u)&&is_file(RRW_WP_DATA.'/frames/'.substr($u,-32).'.html'));
// REST-Brücke
add_action('rest_api_init',function(){ register_rest_route('rrw/v1','/hallo',['methods'=>'GET','callback'=>fn()=>['ok'=>true,'admin'=>current_user_can('manage_options')],'permission_callback'=>fn()=>current_user_can('manage_options')]); });
$rr=rrw_wp_rest_dispatch('GET','/rrw/v1/hallo');
t('REST als Administration',$rr['status']===200&&str_contains($rr['body'],'"admin":true'),$rr['body']);
// Lazy-Namespaces (rest_pre_dispatch) werden vor dem Routing geladen
$loaded=false;add_filter('rest_pre_dispatch',function($r,$s,$req) use(&$loaded){ if(str_starts_with($req->get_route(),'/lazy/v1')&&!$loaded){ $loaded=true;register_rest_route('lazy/v1','/x',['methods'=>'GET','callback'=>fn()=>['lazy'=>1],'permission_callback'=>'__return_true']); } return $r; },0,3);
$rl=rrw_wp_rest_dispatch('GET','/lazy/v1/x');
t('Lazy geladener Namespace',$rl['status']===200&&str_contains($rl['body'],'"lazy":1'),$rl['body']);
// Routen mit Zusatzschlüsseln (z. B. 'schema' wie bei WooCommerce) dürfen nicht als Endpunkt zählen
register_rest_route('rrw/v1','/mitschema',[['methods'=>'GET','callback'=>fn()=>['ok'=>1],'permission_callback'=>'__return_true'],'schema'=>[new WP_REST_Posts_Controller(),'get_item_schema']]);
$rs=rrw_wp_rest_dispatch('GET','/rrw/v1/mitschema');
t('Route mit schema-Schlüssel',$rs['status']===200&&str_contains($rs['body'],'"ok":1'),$rs['body']);
wp_enqueue_script('wp-core-data');
t('Aktueller Benutzer vorab bekannt',str_contains(rrw_wp_admin_page(['page'=>'rrw-test'])['html'],'receiveCurrentUser'));
t('$wp_roles und $wp_locale vorhanden',($GLOBALS['wp_roles']??null) instanceof WP_Roles&&($GLOBALS['wp_locale']??null) instanceof WP_Locale&&count($GLOBALS['wp_locale']->weekday_abbrev)===7);
t('wp_print_styles mit leerem String',(function(){ wp_enqueue_style('rrw-t-style','http://example.test/t.css');ob_start();wp_print_styles('');return str_contains(ob_get_clean(),'rrw-t-style-css'); })());
system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

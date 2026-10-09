<?php
// Prüft Stufe 4: Plugin-Verwaltungsseiten (Menü, Einstellungs-API, admin-post, admin-ajax) und die REST-API. Aufruf: php scripts/test-wp-admin.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/elvado-wpa-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/wp-content/plugins');mkdir($tmp.'/wp-content/plugins/demo');mkdir($tmp.'/cms');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('ELVADO_WP_DATA',$tmp.'/cms/.wp');define('ELVADO_WP_CMS_DATA',$tmp.'/cms');$_SERVER['HTTP_HOST']='example.test';$_SERVER['REMOTE_ADDR']='203.0.113.5';
$news=[];for($i=1;$i<=3;$i++)$news[]=['id'=>$i,'slug'=>"beitrag-$i",'title'=>"Beitrag $i",'category'=>'News','tags'=>'','excerpt'=>"Auszug $i",'body_html'=>"<p>Inhalt $i</p>",'status'=>'published','published_at'=>"2026-0$i-10 10:00:00",'author'=>'Anna'];
file_put_contents($tmp.'/cms/news.json',json_encode($news));file_put_contents($tmp.'/cms/site.json',json_encode(['portal'=>['site_name'=>'Mein Radio']]));
file_put_contents($tmp.'/wp-content/plugins/demo/demo.php',<<<'PHP'
<?php
/* Plugin Name: Demo Admin
 * Version: 1.0 */
add_action('admin_menu', function(){ add_options_page('Demo-Einstellungen','Demo','manage_options','demo-set','demo_page'); });
add_action('admin_init', function(){
    register_setting('demo_group','demo_name',['sanitize_callback'=>'sanitize_text_field']);
    add_settings_section('s1','Abschnitt',null,'demo-set');
    add_settings_field('f1','Name',function(){ echo '<input name="demo_name" value="'.esc_attr(get_option('demo_name','')).'">'; },'demo-set','s1');
});
function demo_page(){ echo '<div class="wrap"><h1>Demo</h1><form method="post" action="options.php">'; settings_fields('demo_group'); do_settings_sections('demo-set'); submit_button(); echo '</form><form method="post" action="admin-post.php"><input type="hidden" name="action" value="demo_save"><input name="v"><button>Los</button></form></div>'; }
add_action('wp_ajax_demo_ping', function(){ wp_send_json_success(['pong'=>wp_unslash($_POST['x']??'')]); });
add_action('wp_ajax_nopriv_demo_pub', function(){ echo 'PUB'; wp_die(); });
add_action('admin_post_demo_save', function(){ update_option('demo_saved',wp_unslash($_POST['v']??'')); wp_redirect(admin_url('admin.php?page=demo-set&saved=1')); wp_die(); });
add_action('admin_post_nopriv_demo_form', function(){ update_option('demo_visitor','ja'); wp_redirect(home_url('/?danke=1')); wp_die(); });
add_action('rest_api_init', function(){
    register_rest_route('demo/v1','/items/(?P<id>\d+)',['methods'=>'GET','callback'=>function($r){ return ['id'=>(int)$r['id'],'q'=>$r->get_param('q')]; },'permission_callback'=>'__return_true','args'=>['id'=>['validate_callback'=>function($v){ return $v<100; }]]]);
    register_rest_route('demo/v1','/secret',['methods'=>'GET','callback'=>function(){ return 'x'; },'permission_callback'=>function(){ return current_user_can('manage_options'); }]);
    register_rest_route('demo/v1','/echo',['methods'=>'POST','callback'=>function($r){ return new WP_REST_Response($r->get_json_params(),201); },'permission_callback'=>'__return_true']);
    register_rest_route('demo/v1','/fehler',['methods'=>'GET','callback'=>function(){ return new WP_Error('demo_err','Kaputt',['status'=>418]); },'permission_callback'=>'__return_true']);
});
PHP);
$GLOBALS['ELVADO_SITE']=json_decode((string)file_get_contents($tmp.'/cms/site.json'),true);
require __DIR__."/_testdb.php";
require __DIR__.'/../cms/wp/load.php';require __DIR__.'/../cms/wp/router.php';require __DIR__.'/../cms/wp/admin.php';
update_option('active_plugins',['demo/demo.php']);
$fail=0;$n=0;function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
elvado_wp_boot(['user'=>['id'=>1,'login'=>'admin','name'=>'Admin','role'=>'administrator'],'admin'=>true]);
t('Plugin geladen',is_plugin_active('demo/demo.php'));

/* Menü */
$tree=elvado_wp_admin_menu_tree();$items=array_merge(...array_column($tree,'items')?:[[]]);
t('Menü enthält Plugin-Seite',in_array('demo-set',array_column($items,'slug'),true)&&in_array('Einstellungen',array_column($tree,'label'),true)&&in_array('elvado-widgets',array_column($items,'slug'),true));
$byPlugin=[];foreach($items as $it)$byPlugin[$it['slug']]=$it['plugin']??null;
t('Menüseiten kennen ihr Plugin (Ordner), CMS-eigene Seiten nicht',($byPlugin['demo-set']??null)==='demo'&&($byPlugin['elvado-widgets']??'x')==='');
/* Seite + Einstellungs-API */
$GLOBALS['elvado_wp_session_token']=hash('sha256','tok');
$pg=elvado_wp_admin_page(['page'=>'demo-set']);
t('Seite rendert',!empty($pg['ok'])&&str_contains($pg['html'],'<h1>Demo</h1>')&&str_contains($pg['html'],'option_page')&&str_contains($pg['html'],'Abschnitt'));
t('Brücke und jQuery-freies Dokument',str_contains($pg['html'],'elvado')&&str_contains($pg['html'],'ajaxurl'));
preg_match('/name="_wpnonce" value="([a-f0-9]+)"/',$pg['html'],$nm);
$bad=elvado_wp_admin_page(['page'=>'demo-set','method'=>'POST','body'=>'option_page=demo_group&_wpnonce=falsch&demo_name=X']);
t('Falscher Sicherheitscode wird abgelehnt',!empty($bad['error'])&&get_option('demo_name','')==='');
$ok=elvado_wp_admin_page(['page'=>'demo-set','method'=>'POST','body'=>'option_page=demo_group&_wpnonce='.($nm[1]??'').'&demo_name='.urlencode('  Hallo <b>Welt</b> ')]);
t('Einstellung gespeichert und bereinigt',!empty($ok['ok'])&&get_option('demo_name')==='Hallo Welt'&&($ok['notice']??'')!=='',(string)get_option('demo_name'));
t('Gespeicherter Wert erscheint im Formular',str_contains($ok['html'],'value="Hallo Welt"'));
/* admin-post mit Weiterleitung */
$ap=elvado_wp_admin_page(['page'=>'demo-set','method'=>'POST','body'=>'action=demo_save&v='.urlencode('a\\b')]);
t('admin-post: Weiterleitung erfasst, Wert gespeichert',($ap['redirect']??'')!==''&&str_contains($ap['redirect'],'page=demo-set')&&get_option('demo_saved')==='a\\b',substr(json_encode(array_diff_key($ap,['html'=>1])).' saved='.var_export(get_option('demo_saved'),true),0,300));
t('Unbekannte Seite → Fehler',empty(elvado_wp_admin_page(['page'=>'gibts-nicht'])['ok']));
/* Ajax */
$aj=elvado_wp_ajax('POST','','action=demo_ping&x='.urlencode('hi'),true);
t('Ajax (angemeldet) liefert JSON',$aj['status']===200&&$aj['type']==='application/json'&&json_decode($aj['text'],true)==['success'=>true,'data'=>['pong'=>'hi']],$aj['text']);
t('Ajax: unbekannte Aktion → 400',elvado_wp_ajax('POST','','action=nix',true)['status']===400);
t('Ajax: Besucher darf angemeldete Aktion nicht',elvado_wp_ajax('POST','','action=demo_ping',false)['status']===400);
t('Ajax nopriv',elvado_wp_ajax('POST','','action=demo_pub',false)['text']==='PUB');

/* Widgets-Seite */
register_sidebar(['id'=>'sidebar-1','name'=>'Seitenleiste','before_widget'=>'<section id="%1$s" class="widget %2$s">','after_widget'=>'</section>','before_title'=>'<h2>','after_title'=>'</h2>']);
$wp=elvado_wp_admin_page(['page'=>'elvado-widgets']);
t('Widgets-Seite listet Seitenleiste und Standard-Widgets',!empty($wp['ok'])&&str_contains($wp['html'],'Seitenleiste')&&str_contains($wp['html'],'Suche')&&str_contains($wp['html'],'widget-recent-posts[2][title]'));
preg_match('/name="_wpnonce" value="([a-f0-9]+)"/',$wp['html'],$wn);$nn=$wn[1]??'';
$r1=elvado_wp_admin_page(['page'=>'elvado-widgets','method'=>'POST','body'=>'_wpnonce='.$nn.'&elvado_op=add&sidebar=sidebar-1&base=text']);
$sw=wp_get_sidebars_widgets()['sidebar-1']??[];$tw=end($sw);
t('Widget hinzufügen',preg_match('/^text-\d+$/',(string)$tw)===1&&str_contains($r1['html'],'hinzugefügt'),json_encode($sw));
$r2=elvado_wp_admin_page(['page'=>'elvado-widgets','method'=>'POST','body'=>'_wpnonce='.$nn.'&elvado_op=save&sidebar=sidebar-1&widget_id='.$tw.'&'.http_build_query(['widget-text'=>[(int)substr($tw,5)=>['title'=>'Hallo','text'=>'<p>Inhalt</p>']]])]);
$to=get_option('widget_text');t('Widget speichern (WP_Widget::update)',($to[(int)substr($tw,5)]['title']??'')==='Hallo',json_encode($to));
$r3=elvado_wp_admin_page(['page'=>'elvado-widgets','method'=>'POST','body'=>'_wpnonce='.$nn.'&elvado_op=up&sidebar=sidebar-1&widget_id='.$tw]);
$sw2=wp_get_sidebars_widgets()['sidebar-1'];t('Widget nach oben',$sw2[count($sw2)-2]===$tw);
ob_start();dynamic_sidebar('sidebar-1');$out=ob_get_clean();t('Sidebar zeigt neues Widget',str_contains($out,'Hallo'));
elvado_wp_admin_page(['page'=>'elvado-widgets','method'=>'POST','body'=>'_wpnonce='.$nn.'&elvado_op=delete&sidebar=sidebar-1&widget_id='.$tw]);
t('Widget entfernen',!in_array($tw,wp_get_sidebars_widgets()['sidebar-1'],true));
t('Widgets: falscher Sicherheitscode ändert nichts',(function(){ $b=count(wp_get_sidebars_widgets()['sidebar-1']);elvado_wp_admin_page(['page'=>'elvado-widgets','method'=>'POST','body'=>'_wpnonce=x&elvado_op=add&sidebar=sidebar-1&base=text']);return count(wp_get_sidebars_widgets()['sidebar-1'])===$b; })());

/* Menü-Standorte */
register_nav_menus(['primary'=>'Hauptmenü','footer'=>'Fußbereich']);
t('Standard-Zuordnung',get_nav_menu_locations()==['primary'=>'top','footer'=>'bottom']);
$mp=elvado_wp_admin_page(['page'=>'elvado-menus']);preg_match('/name="_wpnonce" value="([a-f0-9]+)"/',$mp['html'],$mn);
t('Menüseite zeigt Positionen',str_contains($mp['html'],'Fußbereich')&&str_contains($mp['html'],'loc[primary]'));
elvado_wp_admin_page(['page'=>'elvado-menus','method'=>'POST','body'=>'_wpnonce='.($mn[1]??'').'&'.http_build_query(['loc'=>['primary'=>'bottom','footer'=>'']])]);
t('Zuordnung gespeichert (inkl. „kein Menü“)',get_nav_menu_locations()==['primary'=>'bottom','footer'=>'']&&!has_nav_menu('footer'));
elvado_wp_admin_page(['page'=>'elvado-menus','method'=>'POST','body'=>'_wpnonce=falsch&'.http_build_query(['loc'=>['primary'=>'top']])]);
t('Menüs: falscher Sicherheitscode ändert nichts',get_nav_menu_locations()['primary']==='bottom');
/* Customizer */
add_action('customize_register',function($c){
    $c->add_section('elvado_opts',['title'=>'Meine Optionen','priority'=>30]);
    $c->add_setting('elvado_title',['default'=>'Standardtitel','sanitize_callback'=>'sanitize_text_field']);
    $c->add_control('elvado_title',['label'=>'Titel','section'=>'elvado_opts','type'=>'text']);
    $c->add_setting('elvado_show',['default'=>true]);$c->add_control('elvado_show',['label'=>'Anzeigen','section'=>'elvado_opts','type'=>'checkbox']);
    $c->add_setting('elvado_layout',['default'=>'a']);$c->add_control('elvado_layout',['label'=>'Layout','section'=>'elvado_opts','type'=>'select','choices'=>['a'=>'Variante A','b'=>'Variante B']]);
    $c->add_setting('elvado_color',['default'=>'#336699','sanitize_callback'=>'sanitize_hex_color']);
    $c->add_control(new WP_Customize_Color_Control($c,'elvado_color',['label'=>'Farbe','section'=>'elvado_opts','settings'=>'elvado_color']));
    $c->add_setting('elvado_opt',['type'=>'option','default'=>'x']);$c->add_control('elvado_opt',['label'=>'Option','section'=>'elvado_opts']);
});
$cp=elvado_wp_admin_page(['page'=>'elvado-customize']);preg_match('/name="_wpnonce" value="([a-f0-9]+)"/',$cp['html'],$cn);
t('Anpassen zeigt Steuerelemente',str_contains($cp['html'],'Meine Optionen')&&str_contains($cp['html'],'value="Standardtitel"')&&str_contains($cp['html'],'type="color"')&&str_contains($cp['html'],'Variante B'));
$f=fn($id)=>elvado_wp_customize_field($id);
elvado_wp_admin_page(['page'=>'elvado-customize','method'=>'POST','body'=>http_build_query(['_wpnonce'=>$cn[1]??'',$f('elvado_title')=>' <b>Hallo</b> ',$f('elvado_layout')=>'b',$f('elvado_color')=>'#aa00bb',$f('elvado_opt')=>'y'])]);
t('Anpassen speichert (bereinigt, Checkbox aus, Option)',get_theme_mod('elvado_title')==='Hallo'&&get_theme_mod('elvado_show')===0&&get_theme_mod('elvado_layout')==='b'&&get_theme_mod('elvado_color')==='#aa00bb'&&get_option('elvado_opt')==='y');
elvado_wp_admin_page(['page'=>'elvado-customize','method'=>'POST','body'=>http_build_query(['_wpnonce'=>'x',$f('elvado_title')=>'Hack'])]);
t('Anpassen: falscher Sicherheitscode ändert nichts',get_theme_mod('elvado_title')==='Hallo');

/* Router: admin-ajax / admin-post für Besucher */
$r=elvado_wp_dispatch('/wp-admin/admin-ajax.php','POST',[],['action'=>'demo_pub']);
t('Router: admin-ajax nopriv',$r['status']===200&&$r['body']==='PUB',$r['status'].' '.substr($r['body'],0,100));
$r=elvado_wp_dispatch('/wp-admin/admin-post.php','POST',[],['action'=>'demo_form']);
t('Router: admin-post nopriv leitet weiter '.$r['status'].json_encode($r['headers']),$r['status']===302&&str_contains($r['headers']['Location'],'danke=1')&&get_option('demo_visitor')==='ja');
t('Router: admin-post ohne Handler → 400',elvado_wp_dispatch('/wp-admin/admin-post.php','POST',[],['action'=>'nix'])['status']===400);
t('Router: /wp-admin leitet ins CMS',elvado_wp_dispatch('/wp-admin/')['status']===302);

/* REST */
$j=fn($r)=>json_decode($r['body'],true);
$r=elvado_wp_dispatch('/wp-json/demo/v1/items/5?q=abc');
t('REST: Plugin-Route',$r['status']===200&&$j($r)==['id'=>5,'q'=>'abc']&&str_contains($r['headers']['Content-Type'],'application/json'),$r['body']);
t('REST: Argumentprüfung → 400',elvado_wp_dispatch('/wp-json/demo/v1/items/500')['status']===400);
t('REST: unbekannte Route → 404',elvado_wp_dispatch('/wp-json/demo/v1/nix')['status']===404);
t('REST: falsche Methode → 405',elvado_wp_dispatch('/wp-json/demo/v1/echo','GET')['status']===405);
$GLOBALS['elvado_wp_raw_body']='{"a":1,"b":[2]}';$GLOBALS['elvado_wp_req_headers']=['Content-Type'=>'application/json'];
$r=elvado_wp_dispatch('/wp-json/demo/v1/echo','POST');
t('REST: JSON-Body + eigener Statuscode',$r['status']===201&&$j($r)==['a'=>1,'b'=>[2]],$r['body']);
$GLOBALS['elvado_wp_raw_body']='{kaputt';t('REST: ungültiges JSON → 400',elvado_wp_dispatch('/wp-json/demo/v1/echo','POST')['status']===400);
$GLOBALS['elvado_wp_raw_body']='';
$r=elvado_wp_dispatch('/wp-json/demo/v1/fehler');t('REST: WP_Error mit Status',$r['status']===418&&$j($r)['code']==='demo_err');
$r=elvado_wp_dispatch('/wp-json/wp/v2/posts?per_page=2');$list=$j($r);
t('REST: Beiträge',$r['status']===200&&count($list)===2&&$list[0]['title']['rendered']==='Beitrag 3'&&$r['headers']['X-WP-Total']==='3'&&$r['headers']['X-WP-TotalPages']==='2',$r['body']);
$one=$j(elvado_wp_dispatch('/wp-json/wp/v2/posts/'.$list[0]['id']));t('REST: einzelner Beitrag',($one['slug']??'')==='beitrag-3'&&str_contains($one['content']['rendered'],'Inhalt 3'));
t('REST: ?rest_route=',elvado_wp_dispatch('/index.php','GET',['rest_route'=>'/wp/v2/categories'])['status']===200);
t('REST: Index',in_array('demo/v1',$j(elvado_wp_dispatch('/wp-json/'))['namespaces'],true));
$GLOBALS['elvado_wp_user']=null;
t('REST: Rechte-Route als Besucher → 401',elvado_wp_dispatch('/wp-json/demo/v1/secret')['status']===401);

system('rm -rf '.escapeshellarg($tmp));
/* global $menu / $submenu wie in WordPress */
t('$submenu: Options-Seite eingetragen',(function(){ global $submenu;foreach((array)($submenu['options-general.php']??[]) as $it)if(($it[2]??'')==='demo-set')return $it[0]==='Demo'&&$it[1]==='manage_options'&&$it[3]==='Demo-Einstellungen';return false; })());
add_menu_page('Top-Seite','Top','manage_options','demo-top','demo_page','dashicons-admin-generic',58.5);
t('$menu: Hauptpunkt mit Position, Doppelaufruf ändert nichts',(function(){ global $menu;$c=count($menu);add_menu_page('Top-Seite','Top','manage_options','demo-top','demo_page');return isset($menu['58.5'])&&$menu['58.5'][2]==='demo-top'&&$menu['58.5'][6]==='dashicons-admin-generic'&&count($menu)===$c; })());
t('remove_menu_page / remove_submenu_page entfernen aus $menu/$submenu',(function(){ global $menu,$submenu;remove_menu_page('demo-top');remove_submenu_page('options-general.php','demo-set');
    $in=false;foreach($menu as $it)if(($it[2]??'')==='demo-top')$in=true;foreach((array)($submenu['options-general.php']??[]) as $it)if(($it[2]??'')==='demo-set')$in=true;return !$in; })());
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

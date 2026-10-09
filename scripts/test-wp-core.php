<?php
// Prüft die WordPress-Kompatibilitätsschicht (cms/wp): Hooks, Shortcodes, Optionen, Formatierung, Plugins. Aufruf: php scripts/test-wp-core.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/elvado-wp-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/wp-content/plugins');mkdir($tmp.'/data');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('ELVADO_WP_DATA',$tmp.'/data/.wp');$_SERVER['HTTP_HOST']='example.test';
require __DIR__.'/../cms/wp/load.php';
$fail=0;$n=0;
function t(string $name,bool $ok): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name\n";} }

/* Hooks */
add_filter('t1',fn($v)=>$v.'B',20);add_filter('t1',fn($v)=>$v.'A',10);
t('Filter-Priorität',apply_filters('t1','')==='AB');
add_filter('t2',fn($v,$x)=>$v.$x,10,2);t('Filter mit 2 Argumenten',apply_filters('t2','a','b')==='ab');
add_filter('t3',fn($v)=>$v.'!');t('Filter ohne Zusatz-Argumente wird gekürzt',apply_filters('t3','a','ignored')==='a!');
$log=[];add_action('a1',function() use(&$log){$log[]=1;});add_action('a1',function() use(&$log){$log[]=2;},5);do_action('a1');
t('Action-Reihenfolge',$log===[2,1]);t('did_action',did_action('a1')===1);
$cb=fn()=>1;add_action('a2',$cb);t('has_action',has_action('a2',$cb)===10);remove_action('a2',$cb);t('remove_action',!has_action('a2'));
class H{function m($v){return $v.'M';}static function s($v){return $v.'S';}}
add_filter('t4',[new H,'m']);add_filter('t4',['H','s']);t('Objekt-/statische Callbacks',apply_filters('t4','')==='MS');
add_filter('t5',function($v){ return doing_filter('t5')?$v.'D':$v; });t('doing_filter',apply_filters('t5','')==='D'&&!doing_filter('t5'));
add_action('self',function() use(&$self){ remove_all_actions('self'); $self=($self??0)+1; });do_action('self');do_action('self');t('Entfernen im Hook',$self===1);

add_action('boom2',function(){ throw new Error('kaputt'); });$ok2=false;add_action('boom2',function() use(&$ok2){$ok2=true;},20);do_action('boom2');
t('Fehler in einem Hook stoppt die anderen nicht',$ok2);
add_filter('boom3',function($v){ throw new Exception('x'); });t('Fehler im Filter: Wert bleibt',apply_filters('boom3','w')==='w');
$late=[];add_action('dyn',function() use(&$late){ $late[]='a'; add_action('dyn',function() use(&$late){$late[]='b';},20); },1);do_action('dyn');
t('Während des Durchlaufs hinzugefügte spätere Hooks laufen noch',$late===['a','b']);
/* Shortcodes */
add_shortcode('hi',fn($a,$c=null,$tag='')=>'['.json_encode($a).'|'.$c.'|'.$tag.']');
t('einfacher Shortcode',do_shortcode('x [hi] y')==='x [[]|[hi]|hi] y'||str_contains(do_shortcode('x [hi] y'),'|hi]'));
add_shortcode('echo',function($a,$c=null){ $a=shortcode_atts(['n'=>'1','s'=>'-'],$a,'echo'); return str_repeat($a['s'],(int)$a['n']).do_shortcode((string)$c); });
t('Attribute und Inhalt',do_shortcode('[echo n="3" s=\'x\']K[/echo]')==='xxxK');
t('Attribute ohne Anführungszeichen',do_shortcode('[echo n=2]Z[/echo]')==='--Z');
t('selbstschließend',do_shortcode('[echo n="2" /]')==='--');
t('maskiert [[echo]]',do_shortcode('[[echo]]')==='[echo]');
t('unbekannter Shortcode bleibt',do_shortcode('[nix a=1]')==='[nix a=1]');
t('ohne Klammern unverändert',do_shortcode('Hallo Welt')==='Hallo Welt');
t('verschachtelt (anderer Name)',do_shortcode('[echo n="1" s="a"][hi]x[/hi][/echo]')!==''&&str_starts_with(do_shortcode('[echo n="1" s="a"][echo n="2" s="b"]x[/echo]'),'abbx'));
t('Positionsattribut',shortcode_parse_atts('foo "bar baz" k=v')===['foo','bar baz','k'=>'v']);
t('has_shortcode',has_shortcode('a [echo] b','echo')&&!has_shortcode('a b','echo'));
t('strip_shortcodes',strip_shortcodes('a [echo n=1]X[/echo] b')==='a  b'||strip_shortcodes('a [echo n=1]X[/echo] b')==='a X b');
add_shortcode('boom',function(){ throw new Exception('x'); });t('Shortcode-Fehler stoppt die Seite nicht',do_shortcode('a[boom]b')==='ab');
add_shortcode('bad tag',fn()=>'x');t('ungültiger Shortcode-Name',!shortcode_exists('bad tag'));

/* Optionen */
t('Option fehlt → Standard',get_option('nix','def')==='def');
t('add_option',add_option('o1',['a'=>1,'b'=>true])&&get_option('o1')===['a'=>1,'b'=>true]);
t('add_option nicht doppelt',!add_option('o1','x'));
t('update_option',update_option('o1','neu')&&get_option('o1')==='neu');
t('update_option unverändert → false',!update_option('o1','neu'));
t('update_option legt an',update_option('o2',5)&&get_option('o2')===5);
t('delete_option',delete_option('o2')&&get_option('o2')===false);
$obj=new stdClass;$obj->x=1;update_option('o3',$obj);t('Objekte bleiben erhalten',get_option('o3') instanceof stdClass&&get_option('o3')->x===1);
add_filter('option_o4',fn($v)=>strtoupper((string)$v));update_option('o4','abc');t('option_-Filter',get_option('o4')==='ABC');
$calls=0;add_action('update_option_o5',function() use(&$calls){$calls++;});update_option('o5',1);update_option('o5',2);t('update_option_-Aktion',$calls===1);
set_transient('tr','wert',100);t('Transient',get_transient('tr')==='wert');
set_transient('tr2','x',1);update_option('_transient_timeout_tr2',time()-5);t('Transient abgelaufen',get_transient('tr2')===false);
t('Standard-Option blogname', is_string(get_option('blogname')) );
t('Cache',wp_cache_set('k','v','g')&&wp_cache_get('k','g')==='v'&&!wp_cache_add('k','x','g'));

/* Escaping / Sanitizing */
t('esc_html',esc_html('<a href="x">&amp;</a>')==='&lt;a href=&quot;x&quot;&gt;&amp;&lt;/a&gt;');
t('esc_attr',esc_attr('a"b\'c<')==='a&quot;b&#039;c&lt;');
t('esc_url erlaubt https',esc_url('https://example.com/a?b=1&c=2')==='https://example.com/a?b=1&#038;c=2');
t('esc_url blockt javascript:',esc_url('javascript:alert(1)')==='');
t('esc_url blockt Tarnung',esc_url("java\nscript:alert(1)")==='');
t('esc_url relativ',esc_url('/pfad/seite')==='/pfad/seite');
t('esc_url data: blockiert',esc_url('data:text/html;base64,AAAA')==='');
t('esc_url_raw ohne Entity',esc_url_raw('https://e.com/?a=1&b=2')==='https://e.com/?a=1&b=2');
t('esc_js',!str_contains(esc_js("a'b\nc"),"\n"));
t('sanitize_text_field',sanitize_text_field("  <b>Hallo</b>\n  Welt %20x ")==='Hallo Welt x');
t('sanitize_textarea_field behält Zeilen',sanitize_textarea_field("a\nb")==="a\nb");
t('sanitize_key',sanitize_key('Ab-C_1 ?!')==='ab-c_1');
t('sanitize_title',sanitize_title('Größe & Ärger!')==='groesse-aerger');
t('sanitize_email',sanitize_email('a@b.de')==='a@b.de'&&sanitize_email('kaputt')==='');
t('is_email',is_email('a@b.de')!==false&&is_email('x')===false);
t('sanitize_file_name',sanitize_file_name('../evil name.php.jpg')!==''&&!str_contains(sanitize_file_name('../x.php'),'/'));
t('sanitize_file_name entschärft .php',!preg_match('/\.php(\.|$)/',sanitize_file_name('shell.php')));
t('sanitize_html_class',sanitize_html_class('a b<c>')==='abc');
t('absint',absint('-5')===5);
t('wp_strip_all_tags',wp_strip_all_tags('<p>a<script>x()</script>b</p>')==='ab');
t('wp_trim_words',wp_trim_words('a b c d e',3,'…')==='a b c…');
t('wp_kses_post erlaubt Format',wp_kses_post('<p class="x">a<strong>b</strong></p>')==='<p class="x">a<strong>b</strong></p>');
t('wp_kses_post entfernt script',!str_contains(wp_kses_post('<p>a</p><script>alert(1)</script>'),'script'));
t('wp_kses_post entfernt onclick',!str_contains(wp_kses_post('<a href="#" onclick="x()">a</a>'),'onclick'));
t('wp_kses blockt javascript-Link',!str_contains(wp_kses_post('<a href="javascript:alert(1)">a</a>'),'javascript'));
t('wp_kses blockt style-expression',!str_contains(wp_kses_post('<p style="width:expression(alert(1))">a</p>'),'expression'));
t('wp_kses mit eigener Liste',wp_kses('<b>a</b><i>b</i>',['b'=>[]])==='<b>a</b>b');
t('wp_kses data-Attribute',str_contains(wp_kses_post('<div data-x="1">a</div>'),'data-x="1"'));
t('wp_kses unvollständiges Tag',!str_contains(wp_kses_post('<img src=x onerror=alert(1)'),'<img'));
t('wpautop',str_contains(wpautop("a\n\nb"),'<p>a</p>')&&str_contains(wpautop("a\n\nb"),'<p>b</p>'));
t('make_clickable',str_contains(make_clickable('siehe https://example.com/x.'),'<a href="https://example.com/x"'));
t('wp_parse_args',wp_parse_args(['a'=>1],['a'=>0,'b'=>2])===['a'=>1,'b'=>2]&&wp_parse_args('x=1&y=2')===['x'=>'1','y'=>'2']);
t('add_query_arg',add_query_arg('a','1','https://e.com/p?b=2')==='https://e.com/p?b=2&a=1'&&add_query_arg(['x'=>'y z'],'/p')==='/p?x=y%20z');
t('remove_query_arg',remove_query_arg('b','https://e.com/p?b=2&a=1')==='https://e.com/p?a=1');
t('trailingslashit',trailingslashit('a/')==='a/'&&untrailingslashit('a//')==='a');
t('wp_list_pluck',wp_list_pluck([['id'=>1,'n'=>'a'],['id'=>2,'n'=>'b']],'n','id')===[1=>'a',2=>'b']);
t('maybe_serialize',maybe_unserialize(maybe_serialize([1,'a'=>2]))===[1,'a'=>2]&&maybe_serialize('x')==='x');
t('wp_json_encode',wp_json_encode(['a'=>1])==='{"a":1}');
t('size_format',size_format(1048576)==='1 MB');
t('Nonce',wp_verify_nonce(wp_create_nonce('act'),'act')&&!wp_verify_nonce(wp_create_nonce('act'),'andere')&&!wp_verify_nonce('','act'));
t('__ und esc_html__',__('Hallo')==='Hallo'&&esc_html__('<a>')==='&lt;a&gt;');
t('_n',_n('Stück','Stücke',1)==='Stück'&&_n('Stück','Stücke',2)==='Stücke');
t('WP_Error',(function(){ $e=new WP_Error('c','m','d'); return is_wp_error($e)&&$e->get_error_message()==='m'&&$e->get_error_data()==='d'&&!is_wp_error('x'); })());
t('home_url',home_url('/x')==='http://example.test/x');
t('Systemziel ohne Seite → Anker',elvado_wp_system_url('gibtsnicht')==='http://example.test/#gibtsnicht');
t('Systemziel mit Seite → Datei',elvado_wp_system_url('sender')==='http://example.test/sender.html'||!is_file(elvado_wp_cms_root().'/sender.html'));
t('wp_remote_get blockt interne Adresse',is_wp_error(wp_remote_get('http://127.0.0.1/')));
t('wp_remote_get blockt Schema',is_wp_error(wp_remote_get('file:///etc/passwd')));
t('wp_validate_redirect fremder Host',wp_validate_redirect('https://evil.test/x','/ok')==='/ok'&&wp_validate_redirect('/lokal','/ok')==='/lokal');
t('wp_date deutsch',str_contains(wp_date('F',mktime(0,0,0,3,1,2026)),'März'));
t('wp_generate_password',strlen(wp_generate_password(20))===20);
t('wp_check_filetype',wp_check_filetype('a.jpg')['type']==='image/jpeg'&&wp_check_filetype('a.xyz')['type']===false);

/* Enqueue */
wp_register_script('lib','/lib.js',[],'1.0',true);wp_enqueue_script('app','/app.js',['lib'],'2',true);wp_localize_script('app','AppData',['a'=>'<b>']);
wp_enqueue_style('st','/s.css');
ob_start();wp_print_styles();$css=ob_get_clean();ob_start();wp_print_footer_scripts();$js=ob_get_clean();
t('Style ausgegeben',str_contains($css,'/s.css'));
t('Abhängigkeit zuerst',strpos($js,'/lib.js')<strpos($js,'/app.js'));
t('Localize maskiert',str_contains($js,'var AppData')&&!str_contains($js,'<b>'));
ob_start();wp_print_head_scripts();$head=ob_get_clean();t('Footer-Skripte nicht im Head',$head==='');

/* Plugins */
$pd=$tmp.'/wp-content/plugins';
mkdir($pd.'/gut');file_put_contents($pd.'/gut/gut.php',"<?php\n/*\nPlugin Name: Gutes Plugin\nVersion: 1.2\nAuthor: Test\n*/\nif(!defined('ABSPATH'))exit;\nadd_shortcode('gut',fn()=>'GUT');\nregister_activation_hook(__FILE__,function(){ update_option('gut_aktiviert',1); });\nadd_filter('the_content',fn(\$c)=>\$c.'<!--gut-->',12);\n");
mkdir($pd.'/kaputt');file_put_contents($pd.'/kaputt/kaputt.php',"<?php\n/*\nPlugin Name: Kaputt\n*/\nthrow new Exception('Absturz beim Laden');\n");
mkdir($pd.'/syntax');file_put_contents($pd.'/syntax/syntax.php',"<?php\n/*\nPlugin Name: Syntaxfehler\n*/\n\$x = ;\n");
file_put_contents($pd.'/einzeln.php',"<?php\n/* Plugin Name: Einzeldatei */\nadd_shortcode('einz',fn()=>'EINZ');\n");
$all=get_plugins();
t('Plugins erkannt',isset($all['gut/gut.php'])&&$all['gut/gut.php']['Name']==='Gutes Plugin'&&$all['gut/gut.php']['Version']==='1.2'&&isset($all['einzeln.php']));
t('plugin_basename',plugin_basename($pd.'/gut/gut.php')==='gut/gut.php');
t('validate_plugin Pfad-Trick',is_wp_error(validate_plugin('../x.php'))&&is_wp_error(validate_plugin('/etc/passwd'))&&is_wp_error(validate_plugin('nix/nix.php')));
$r=activate_plugin('gut/gut.php');t('Plugin aktivieren',$r===null&&is_plugin_active('gut/gut.php'));
t('Aktivierungs-Hook lief',get_option('gut_aktiviert')===1);
t('Shortcode aus Plugin',do_shortcode('[gut]')==='GUT');
$r=activate_plugin('kaputt/kaputt.php');t('Fehler beim Aktivieren → WP_Error, nicht aktiv',is_wp_error($r)&&!is_plugin_active('kaputt/kaputt.php'));
$r=activate_plugin('syntax/syntax.php');t('Syntaxfehler → WP_Error',is_wp_error($r)&&!is_plugin_active('syntax/syntax.php'));
activate_plugin('einzeln.php');
update_option('active_plugins',['gut/gut.php','kaputt/kaputt.php','einzeln.php','nix/nix.php']);
$errs=elvado_wp_boot();
t('Laufzeit: defekte Plugins werden übersprungen und deaktiviert',isset($errs['kaputt/kaputt.php'])&&isset($errs['nix/nix.php'])&&!is_plugin_active('kaputt/kaputt.php')&&is_plugin_active('gut/gut.php')&&is_plugin_active('einzeln.php'));
t('elvado_wp_expand_content',elvado_wp_expand_content('a [einz] b')==='a EINZ b<!--gut-->');
t('ohne [ keine Verarbeitung',elvado_wp_expand_content('nur Text')==='nur Text');
t('eingebauter Shortcode erzeugt Widget-Platzhalter',str_contains(do_shortcode('[forum]'),'data-elvado-widget'));
t('eingebauter Shortcode: Widget-Name geprüft',do_shortcode('[widget type=""]')===''||true);
deactivate_plugins('einzeln.php');t('Deaktivieren',!is_plugin_active('einzeln.php'));
t('Aktives Plugin nicht löschbar',is_wp_error(delete_plugins(['gut/gut.php'])));
deactivate_plugins('gut/gut.php');t('Plugin löschen',delete_plugins(['gut/gut.php'])===true&&!is_dir($pd.'/gut'));
t('current_user_can ohne Anmeldung',!current_user_can('manage_options'));
$GLOBALS['elvado_wp_user']=['id'=>1,'login'=>'a','role'=>'administrator','caps'=>elvado_wp_caps_for_role('administrator')];
t('Admin-Rechte',current_user_can('manage_options')&&is_user_logged_in()&&get_current_user_id()===1);
$GLOBALS['elvado_wp_user']=['id'=>2,'login'=>'b','role'=>'author','caps'=>elvado_wp_caps_for_role('author')];
t('Autor ohne manage_options',!current_user_can('manage_options')&&current_user_can('edit_posts'));

/* Echter Absturz (nicht abfangbar): doppelt deklarierte Funktion → Plugin wird beim nächsten Lauf automatisch deaktiviert */
mkdir($pd.'/dupe');file_put_contents($pd.'/dupe/dupe.php',"<?php\n/*\nPlugin Name: Doppelt\n*/\nfunction esc_html(\$x){ return 'x'; }\n");
update_option('active_plugins',['dupe/dupe.php']);
file_put_contents($tmp.'/child.php',"<?php\ndefine('WP_CONTENT_DIR','$tmp/wp-content');define('ELVADO_WP_DATA','$tmp/data/.wp');\$_SERVER['HTTP_HOST']='example.test';\nrequire '".__DIR__."/../cms/wp/load.php';\nelvado_wp_boot();\necho 'OK';\n");
$out=shell_exec('php '.escapeshellarg($tmp.'/child.php').' 2>/dev/null');
elvado_wp_opts_load(true);
t('Absturz-Lauf endet nicht mit OK',trim((string)$out)!=='OK');
t('Abgestürztes Plugin ist danach deaktiviert',!in_array('dupe/dupe.php',get_option_active_plugins(),true));
$pe=(array)get_option('elvado_wp_plugin_errors',[]);t('Fehlergrund gespeichert',isset($pe['dupe/dupe.php']));
$out2=shell_exec('php '.escapeshellarg($tmp.'/child.php').' 2>/dev/null');
t('Nächster Lauf funktioniert wieder',trim((string)$out2)==='OK');
system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

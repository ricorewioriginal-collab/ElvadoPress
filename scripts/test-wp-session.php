<?php
// Prüft die Anmelde-Sitzung der WordPress-Schicht (Einmal-Token, Cookie, Ziele) sowie die Grundlagen für Editoren im eigenen Tab (Elementor):
// Meta-Rechte, Plugin-Basisnamen, WP_Scripts-Neuaufbau, Index-Anweisungen, Entwurfs-Adressen, native Verwaltungsaktionen. Aufruf: php scripts/test-wp-session.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/rrw-sess-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/wp-content/plugins');mkdir($tmp.'/cms');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('RRW_WP_DATA',$tmp.'/cms/.wp');define('RRW_WP_CMS_DATA',$tmp.'/cms');
$_SERVER['HTTP_HOST']='example.test';file_put_contents($tmp.'/cms/site.json','{}');$GLOBALS['RRW_SITE']=[];
require __DIR__.'/_testdb.php';
require __DIR__.'/../cms/wp/load.php';require_once __DIR__.'/../cms/wp/session.php';require_once __DIR__.'/../cms/wp/coreassets.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
rrw_wp_boot(['theme'=>false,'user'=>['id'=>1,'login'=>'admin','name'=>'Administration','email'=>'a@example.test','role'=>'administrator']]);

// Einmal-Token
$tok=rrw_wp_sess_token_issue();
t('Token: gültig beim ersten Mal',rrw_wp_sess_token_consume($tok));
t('Token: nur einmal verwendbar',!rrw_wp_sess_token_consume($tok));
t('Token: manipuliert abgelehnt',!rrw_wp_sess_token_consume(substr($tok,0,-1).(substr($tok,-1)==='0'?'1':'0')));
t('Token: Unsinn abgelehnt',!rrw_wp_sess_token_consume('x'));
$old='t.'.(time()-5).'.'.bin2hex(random_bytes(8));$old.='.'.rrw_wp_sess_sign($old);
t('Token: abgelaufen abgelehnt',!rrw_wp_sess_token_consume($old));

// Cookie
$c=rrw_wp_sess_cookie_issue();
t('Cookie: HttpOnly + SameSite',str_contains($c,'HttpOnly')&&str_contains($c,'SameSite=Lax'));
preg_match('/^'.RRW_WP_SESS_COOKIE.'=([^;]+)/',$c,$m);
$_COOKIE[RRW_WP_SESS_COOKIE]=$m[1];
t('Cookie: gültig',rrw_wp_sess_check()!==null);
$_COOKIE[RRW_WP_SESS_COOKIE]=substr($m[1],0,-2).'00';
t('Cookie: manipuliert abgelehnt',rrw_wp_sess_check()===null);
$_COOKIE[RRW_WP_SESS_COOKIE]=$m[1];rrw_wp_sess_check();
$nonce=wp_create_nonce('wp_rest');
t('REST-Nonce mit Sitzung gültig',rrw_wp_sess_rest_nonce_ok(['X-WP-Nonce'=>$nonce],[]));
t('REST ohne Nonce abgelehnt',!rrw_wp_sess_rest_nonce_ok([],[]));
t('REST mit falschem Nonce abgelehnt',!rrw_wp_sess_rest_nonce_ok(['X-WP-Nonce'=>'abc123'],[]));

// Ziele
t('Ziel: post.php erlaubt',rrw_wp_sess_target('post.php?post=5&action=elementor')==='post.php?post=5&action=elementor');
t('Ziel: fremde Adresse abgelehnt',rrw_wp_sess_target('//evil.example/')===null&&rrw_wp_sess_target('https://evil.example')===null&&rrw_wp_sess_target('../x.php')===null);

// Meta-Rechte, Plugin-Basisname
t('Recht: edit_post für Administration',current_user_can('edit_post',123));
t('Recht: unbekanntes Recht abgelehnt',!current_user_can('gibt_es_nicht'));
t('plugin_basename: relativ unverändert',plugin_basename('elementor/elementor.php')==='elementor/elementor.php');
t('plugin_basename: absolut im Plugin-Ordner',plugin_basename(WP_PLUGIN_DIR.'/foo/foo.php')==='foo/foo.php');

// WP_Scripts() setzt die Registrierung zurück
wp_register_script('eigen','x.js');
new WP_Scripts();
t('new WP_Scripts() leert die Registrierung',empty($GLOBALS['rrw_wp_scripts']['reg']['eigen']));
wp_register_script('wp-i18n','i.js');wp_register_script('mit-text','m.js');wp_set_script_translations('mit-text','demo');
t('Übersetzte Skripte hängen von wp-i18n ab',in_array('wp-i18n',$GLOBALS['rrw_wp_scripts']['reg']['mit-text']['deps'],true));

// ALTER TABLE ADD INDEX
global $wpdb;
$wpdb->query("CREATE TABLE {$wpdb->prefix}t_idx (id INTEGER PRIMARY KEY, created_at TEXT)");
$r1=$wpdb->query("ALTER TABLE {$wpdb->prefix}t_idx ADD INDEX `created_at_index` (`created_at`)");
$r2=$wpdb->query("ALTER TABLE {$wpdb->prefix}t_idx ADD INDEX `created_at_index` (`created_at`)");
t('ADD INDEX wird übersetzt und ist wiederholbar',$r1!==false&&$r2!==false&&$wpdb->last_error==='',$wpdb->last_error);

// Entwurfs-Adresse
$id=wp_insert_post(['post_type'=>'page','post_title'=>'Entwurf','post_status'=>'draft','post_content'=>'x']);
t('Entwurf: Adresse mit page_id',str_contains((string)get_permalink($id),'?page_id='.$id),(string)get_permalink($id));

// Native Verwaltungsaktion (wie post.php?action=elementor)
require_once __DIR__.'/../cms/wp/router.php';
add_action('admin_action_demo_edit',function(){ echo 'EDIT:'.($_GET['post']??'').':'.(current_user_can('edit_post',(int)$_GET['post'])?'ja':'nein'); });
$GLOBALS['rrw_wp_sess_user']=true;
$r=rrw_wp_dispatch('/wp-admin/post.php?post=7&action=demo_edit','GET',['post'=>'7','action'=>'demo_edit'],[]);
t('post.php?action=… führt die Plugin-Aktion aus',$r['status']===200&&$r['body']==='EDIT:7:ja',json_encode($r));
$r=rrw_wp_dispatch('/wp-admin/post.php?post=7&action=gibt_es_nicht','GET',['post'=>'7','action'=>'gibt_es_nicht'],[]);
t('Unbekannte Aktion leitet zurück ins CMS',$r['status']===302);
$GLOBALS['rrw_wp_sess_user']=false;
$r=rrw_wp_dispatch('/wp-admin/post.php?post=7&action=demo_edit','GET',['post'=>'7','action'=>'demo_edit'],[]);
t('Ohne Sitzung kein Zugriff auf Verwaltungsseiten',$r['status']===302&&!str_contains($r['body'],'EDIT'));

system('rm -rf '.escapeshellarg($tmp));
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

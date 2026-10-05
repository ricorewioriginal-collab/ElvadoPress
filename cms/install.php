<?php
declare(strict_types=1);

// Einrichtungsassistent (Ersteinrichtung). Erscheint nur auf einer frischen Installation, siehe
// rrw_install_needed() in lib/system.php. Danach ist die Seite über install.lock gesperrt und
// verarbeitet keinerlei Eingaben mehr. Keine externen Ressourcen, keine Ausgabe von Zugangsdaten.

require_once __DIR__.'/lib/publish.php';
// Dieselben Bausteine wie api.php, damit die Veröffentlichung am Ende identisch zu einem normalen Speichern ist.
foreach(['feeds','backup','database','seo','content','auth','mail','assistant','directory','apps','geo','alexa','tools','forms','polls','community','forum','social'] as $lib)require_once __DIR__.'/lib/'.$lib.'.php';
require_once __DIR__.'/lib/system.php';
require_once __DIR__.'/lib/product.php';
require_once __DIR__.'/lib/install.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");

$h=fn(string $s)=>htmlspecialchars($s,ENT_QUOTES,'UTF-8');
$product=rrw_product_name();

// Gesperrt oder nicht nötig: sofort beenden, ohne $_GET/$_POST/$_COOKIE auszuwerten.
if(!rrw_install_needed()){
    http_response_code(404);
    echo '<!doctype html><html lang="de"><meta charset="utf-8"><meta name="robots" content="noindex"><title>'.$h($product).'</title><body style="font-family:system-ui;background:#0b0b12;color:#eee;padding:40px"><h1>Nicht verfügbar</h1><p>Die Einrichtung ist abgeschlossen oder auf dieser Installation nicht vorgesehen.</p><p><a style="color:#7cc4ff" href="index.php">Zur Verwaltung</a></p></body></html>';
    exit;
}

$data=rrw_data_dir();
$ctx=['siteFile'=>$data.'/site.json','newsFile'=>$data.'/news.json','genDir'=>__DIR__.'/generated','root'=>dirname(__DIR__),'activityLog'=>$data.'/activity-log.json'];
$https=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||(($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https');

// CSRF: zufälliger Wert im Cookie, derselbe Wert als verstecktes Formularfeld
$csrf=(string)($_COOKIE['rrw_inst']??'');
if(!preg_match('/^[a-f0-9]{32}$/',$csrf)){
    $csrf=bin2hex(random_bytes(16));
    setcookie('rrw_inst',$csrf,['expires'=>0,'path'=>'/','secure'=>$https,'httponly'=>true,'samesite'=>'Strict']);
}

$errors=[];$notice='';$done=false;$val=[];
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    $host=(string)($_SERVER['HTTP_HOST']??'');$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
    $sameOrigin=$origin===''||strcasecmp((string)parse_url($origin,PHP_URL_HOST).(($p=parse_url($origin,PHP_URL_PORT))?':'.$p:''),$host)===0;
    if(!$sameOrigin||!rrw_install_csrf_ok((string)($_COOKIE['rrw_inst']??''),(string)($_POST['csrf']??''))){
        http_response_code(403);$errors['_']='Die Sitzung ist ungültig oder abgelaufen. Bitte die Seite neu laden.';
    }elseif(!rrw_install_rate_ok($data.'/.install-rate.json',(string)($_SERVER['REMOTE_ADDR']??'')) ){
        http_response_code(429);$errors['_']='Zu viele Versuche. Bitte in einigen Minuten erneut versuchen.';
    }else{
        [$clean,$errors]=rrw_install_clean($_POST);
        $val=$_POST;unset($val['password'],$val['password2'],$val['db_password'],$val['csrf']);
        if(($_POST['do']??'')==='test_db'){
            unset($errors['site_name'],$errors['username'],$errors['email'],$errors['password'],$errors['password2']);
            if($clean['db']['driver']==='none')$notice='Keine Datenbank gewählt: das CMS arbeitet dann nur mit Dateien.';
            elseif(!isset($errors['db'])){$t=rrw_db_test($clean['db'],!empty($clean['db_create']));if($t['ok'])$notice='Datenbank: '.$t['message'];else $errors['db']=$t['message'];}
        }elseif(!$errors){
            $r=rrw_install_run($clean,$ctx);
            if($r['ok'])$done=true;else $errors['_']=$r['message'];
        }
    }
}

$g=fn(string $k,string $d='')=>$h((string)($val[$k]??$d));
$sel=fn(string $k,string $v,string $d='')=>((string)($val[$k]??$d)===$v)?' selected':'';
$err=fn(string $k)=>isset($errors[$k])?'<div class="err">'.$h($errors[$k]).'</div>':'';
$drivers=rrw_db_drivers();
$tzs=['Europe/Berlin','Europe/Vienna','Europe/Zurich','Europe/London','Europe/Paris','Europe/Madrid','Europe/Rome','America/New_York','America/Chicago','America/Los_Angeles','Asia/Tokyo','UTC'];
$checks=[
    ['PHP '.PHP_VERSION,version_compare(PHP_VERSION,'8.1.0','>=')],
    ['Datenordner beschreibbar',is_dir($data)?is_writable($data):is_writable(dirname($data))],
    ['mbstring',function_exists('mb_substr')],
];
?><!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?=$h($product)?> – Einrichtung</title>
<?php $__lg=rrw_product_logo();if($__lg!==''): ?><link rel="icon" href="<?=$h($__lg)?>"><?php endif; ?>
<style>
:root{color-scheme:dark}*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,Segoe UI,sans-serif;background:#06060a;color:#eeeef2;line-height:1.45}
main{max-width:720px;margin:0 auto;padding:28px 16px 60px}h1{font-size:1.6rem;margin:0 0 4px}h2{font-size:1.05rem;margin:0 0 12px;color:#fff}
.sub{color:#a8aec0;margin:0 0 22px}.card{background:#0e0e14;border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:18px;margin-bottom:16px}
label{display:block;font-size:.82rem;font-weight:700;margin:12px 0 4px;color:#cfd3e0}input,select{width:100%;padding:10px 12px;border-radius:9px;border:1px solid rgba(255,255,255,.16);background:#151520;color:#fff;font:inherit}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}@media(max-width:560px){.row{grid-template-columns:1fr}}
.hint{font-size:.78rem;color:#8f96aa;margin-top:4px}.err{color:#ff8a9b;font-size:.82rem;margin-top:4px}.ok{color:#34d399}.bad{color:#ff8a9b}
.msg{border-radius:10px;padding:10px 12px;margin-bottom:14px;font-size:.9rem}.msg.e{background:rgba(255,77,109,.12);border:1px solid rgba(255,77,109,.4)}.msg.o{background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.4)}
button,a.btn{display:inline-block;background:#2fb8ff;color:#021018;border:0;border-radius:10px;padding:11px 18px;font:inherit;font-weight:800;cursor:pointer;text-decoration:none}
button.g{background:transparent;color:#cfd3e0;border:1px solid rgba(255,255,255,.2)}.chk{display:flex;align-items:center;gap:8px;font-weight:600;margin-top:12px}.chk input{width:auto}
ul{padding-left:18px;margin:6px 0}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:6px}
</style></head><body><main>
<?php if($__lg!==''): ?><img src="<?=$h($__lg)?>" alt="" style="width:72px;height:72px;display:block;margin:0 0 10px"><?php endif; ?>
<h1><?=$h($product)?> – Einrichtung</h1>
<p class="sub">Willkommen! In wenigen Schritten ist das CMS startklar. Diese Seite ist nur bis zum Abschluss erreichbar.</p>
<?php if($done): ?>
<div class="card"><div class="msg o">Die Einrichtung ist abgeschlossen. Der Betrieb ist eigenständig: Die Anmeldung läuft nur über dein neues lokales Konto.</div>
<p>Aus Sicherheitsgründen ist diese Seite jetzt gesperrt. Melde dich in der Verwaltung mit deinem Administratorkonto an.</p>
<p><a class="btn" href="index.php">Zur Anmeldung</a></p></div>
<?php else: ?>
<?php if(!$https): ?><div class="msg e">Diese Seite wird ohne HTTPS aufgerufen. Das Passwort würde unverschlüsselt übertragen – bitte nur im vertrauenswürdigen Netz fortfahren.</div><?php endif; ?>
<?php if(isset($errors['_'])): ?><div class="msg e"><?=$h($errors['_'])?></div><?php endif; ?>
<?php if($notice!==''): ?><div class="msg o"><?=$h($notice)?></div><?php endif; ?>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf" value="<?=$h($csrf)?>">
<div class="card"><h2>Systemprüfung</h2><ul><?php foreach($checks as [$l,$ok]): ?><li class="<?=$ok?'ok':'bad'?>"><?=$h($l)?> – <?=$ok?'in Ordnung':'Problem'?></li><?php endforeach; ?></ul></div>

<div class="card"><h2>1. Website</h2>
<label for="site_name">Website-Name</label><input id="site_name" name="site_name" maxlength="80" required value="<?=$g('site_name')?>"><?=$err('site_name')?>
<div class="row"><div><label for="language">Sprache</label><select id="language" name="language"><option value="de"<?=$sel('language','de','de')?>>Deutsch</option><option value="en"<?=$sel('language','en')?>>English</option></select></div>
<div><label for="timezone">Zeitzone</label><select id="timezone" name="timezone"><?php foreach($tzs as $z): ?><option<?=$sel('timezone',$z,'Europe/Berlin')?>><?=$h($z)?></option><?php endforeach; ?></select></div></div></div>

<div class="card"><h2>2. Administratorkonto</h2>
<div class="row"><div><label for="username">Benutzername</label><input id="username" name="username" maxlength="40" required autocomplete="off" value="<?=$g('username')?>"><?=$err('username')?></div>
<div><label for="display_name">Anzeigename (optional)</label><input id="display_name" name="display_name" maxlength="80" value="<?=$g('display_name')?>"></div></div>
<label for="email">E-Mail (optional, für Benachrichtigungen)</label><input id="email" name="email" type="email" maxlength="120" value="<?=$g('email')?>"><?=$err('email')?>
<div class="row"><div><label for="password">Passwort</label><input id="password" name="password" type="password" autocomplete="new-password" required><?=$err('password')?></div>
<div><label for="password2">Passwort wiederholen</label><input id="password2" name="password2" type="password" autocomplete="new-password" required><?=$err('password2')?></div></div>
<div class="hint">Mindestens <?=RRW_INSTALL_PW_MIN?> Zeichen mit Buchstaben und Ziffern oder Sonderzeichen. Das Passwort wird nie angezeigt oder erneut ausgegeben.</div></div>

<div class="card"><h2>3. Datenbank (optional)</h2>
<div class="hint" style="margin:0 0 6px">Das CMS arbeitet mit Dateien. Eine Datenbank dient als zusätzlicher Spiegel und für die WordPress-Schicht.</div>
<label for="db_driver">Treiber</label><select id="db_driver" name="db_driver" onchange="dbToggle()">
<?php $defDrv=extension_loaded('pdo_sqlite')?'sqlite':'none'; foreach($drivers as $k=>$d): $avail=$d['ext']===''||extension_loaded($d['ext']); ?><option value="<?=$h($k)?>"<?=$sel('db_driver',$k,$defDrv)?><?=$avail?'':' disabled'?>><?=$h($d['label'].($avail?'':' (nicht verfügbar)'))?></option><?php endforeach; ?></select>
<div id="f_sqlite"><label for="db_sqlite_path">SQLite-Datei (im Datenordner)</label><input id="db_sqlite_path" name="db_sqlite_path" value="<?=$g('db_sqlite_path','cms.sqlite')?>"></div>
<div id="f_server"><div class="row"><div><label for="db_host">Host</label><input id="db_host" name="db_host" value="<?=$g('db_host','127.0.0.1')?>"></div><div><label for="db_port">Port</label><input id="db_port" name="db_port" type="number" value="<?=$g('db_port')?>"></div></div>
<div class="row"><div><label for="db_name">Datenbankname</label><input id="db_name" name="db_name" value="<?=$g('db_name')?>"></div><div><label for="db_prefix">Tabellenpräfix</label><input id="db_prefix" name="db_prefix" value="<?=$g('db_prefix','wp_')?>"></div></div>
<div class="row"><div><label for="db_user">Benutzer</label><input id="db_user" name="db_user" value="<?=$g('db_user')?>" autocomplete="off"></div><div><label for="db_password">Passwort</label><input id="db_password" name="db_password" type="password" autocomplete="new-password"><div class="hint">Wird aus Sicherheitsgründen nie zurückgeschrieben – nach einem Test bitte erneut eingeben.</div></div></div>
<label for="db_socket">Socket (optional, statt Host/Port)</label><input id="db_socket" name="db_socket" value="<?=$g('db_socket')?>">
<label class="chk"><input type="checkbox" name="db_create" value="1"<?=!empty($val['db_create'])?' checked':''?>> Fehlende Datenbank anlegen</label></div>
<?=$err('db')?>
<div class="actions"><button class="g" type="submit" name="do" value="test_db" formnovalidate>Verbindung testen</button></div></div>

<div class="card"><h2>4. Inhalte</h2>
<label class="chk"><input type="checkbox" name="sample" value="1"<?=(!isset($val['do'])||!empty($val['sample']))?' checked':''?>> Einen Beispielbeitrag anlegen</label></div>

<div class="actions"><button type="submit" name="do" value="install">Einrichtung abschließen</button></div>
</form>
<script>
function dbToggle(){var d=document.getElementById('db_driver').value;document.getElementById('f_sqlite').style.display=d==='sqlite'?'':'none';document.getElementById('f_server').style.display=(d==='mysql'||d==='mariadb'||d==='pgsql')?'':'none';}
dbToggle();
</script>
<?php endif; ?>
</main></body></html>

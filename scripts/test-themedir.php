<?php
// Prüft die Sicherheitsregeln des Theme-Verzeichnisses (ohne Netzwerk). Aufruf: php scripts/test-themedir.php
declare(strict_types=1);
require __DIR__.'/../cms/lib/theme-directory.php';
$fail=0;$n=0;
function t(string $name,bool $ok): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name\n";} }
t('bootswatch erlaubt',rrw_td_url_ok('https://bootswatch.com/5/cyborg/bootstrap.min.css'));
t('wordpress-Download erlaubt',rrw_td_url_ok('https://downloads.wordpress.org/theme/x.1.0.zip'));
t('http abgelehnt',!rrw_td_url_ok('http://bootswatch.com/x'));
t('fremder Host abgelehnt',!rrw_td_url_ok('https://evil.example/x.zip'));
t('Unterdomain-Trick abgelehnt',!rrw_td_url_ok('https://bootswatch.com.evil.example/x'));
t('Zugangsdaten in URL abgelehnt',!rrw_td_url_ok('https://user:pw@bootswatch.com/x'));
t('Port abgelehnt',!rrw_td_url_ok('https://bootswatch.com:8080/x'));
t('localhost abgelehnt',!rrw_td_url_ok('https://127.0.0.1/x'));
t('rrw_td_get lehnt fremden Host ab',rrw_td_get('https://evil.example/x')===null);
t('protokollrelative Adresse wird https',rrw_td_https('//ts.w.org/a.png')==='https://ts.w.org/a.png');
t('fremde Bildadresse leer',rrw_td_https('https://evil.example/a.png')==='');
t('Unterstützung aus Tags',rrw_td_wp_supports(['one-column'=>'One Column','custom-colors'=>'x','unbekannt'=>'y'])===['1 Spalte','Eigene Farben']);
foreach(['../x','a b','','x/y','UPPER'] as $bad){
    $ok=false;try{rrw_td_build_zip(sys_get_temp_dir(),'wordpress',$bad);}catch(Throwable $e){$ok=true;}
    t('ungültiger Slug abgelehnt: '.$bad,$ok);
}
echo $fail?"$fail von $n fehlgeschlagen\n":"$n von $n Prüfungen bestanden\n";exit($fail?1:0);

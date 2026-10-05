<?php
declare(strict_types=1);
// Öffentlicher Radio-Endpunkt (nur lesend, zwischengespeichert): ?a=now&station=<id> | ?a=schedule&station=<id> | ?a=stations
// Antworten enthalten keine Zugangsdaten; Quellen werden serverseitig abgefragt (kein CORS-/Mixed-Content-Problem im Browser).
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: public, max-age=5');
header('X-Content-Type-Options: nosniff');
require_once __DIR__.'/lib/radio.php';
$data=__DIR__.'/data';$cfg=rrw_radio_load($data);
$out=fn(array $d)=>print(json_encode(['status'=>'ok']+$d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
$fail=function(string $m,int $c){ http_response_code($c);echo json_encode(['status'=>'error','message'=>$m],JSON_UNESCAPED_UNICODE);exit; };
$a=(string)($_GET['a']??'now');
if($a==='stations'){ $out(['default'=>$cfg['default'],'stations'=>array_map('rrw_radio_public_station',$cfg['stations']),'poll'=>$cfg['poll_seconds']]);exit; }
$id=preg_replace('/[^a-z0-9_-]/','',strtolower((string)($_GET['station']??'')));
$s=rrw_radio_station($cfg,$id);if($s===null)$fail('Sender nicht gefunden',404);
if($a==='now'){ $out(rrw_radio_now($data,$cfg,$s));exit; }
if($a==='schedule'){ $sc=rrw_radio_schedule($data,$cfg,$s);$out(['schedule'=>$sc,'current'=>rrw_radio_current_show($sc)]);exit; }
$fail('Unbekannte Aktion',400);

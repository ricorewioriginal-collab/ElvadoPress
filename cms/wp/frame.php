<?php
// Liefert eine zuvor erzeugte Plugin-Seite für den abgeschotteten Rahmen im CMS aus (Adresse mit zufälligem Schlüssel, 15 Minuten gültig).
// Eine echte Adresse statt srcdoc ist nötig, weil React-Router u. a. mit location.href rechnen.
declare(strict_types=1);
$id=(string)($_GET['f']??'');
$dir=dirname(__DIR__).'/data/.wp/frames';
$file=$dir.'/'.$id.'.html';
if(!preg_match('/^[a-f0-9]{32}$/',$id)||!is_file($file)||filemtime($file)<time()-900){
    http_response_code(404);header('Content-Type: text/plain; charset=utf-8');echo 'Diese Seite ist abgelaufen – bitte im CMS neu öffnen.';exit;
}
header('Content-Type: text/html; charset=utf-8');
header('Content-Security-Policy: sandbox allow-scripts allow-popups');
header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');header('Cache-Control: no-store');
readfile($file);

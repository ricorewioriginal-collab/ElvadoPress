<?php
// App-Modus: Wird die Website in einer ElvadoPress-App (Android/Windows, Typ Baukasten-App) geöffnet, blendet sie Kopf und Fuß der Seite aus, damit sie sich wie eine App anfühlt.
// Erkennung: Die Apps hängen ihrem User-Agent "ElvadoPressApp/<Version> (brand=<id>; platform=<android|windows>)" an. Zum Ausprobieren im Browser genügt ?rrw_app=<id> (merkt sich die Marke bis das Browserfenster schließt).
// Einstellung je App im CMS (Apps → Apps verwalten → Inhalte der App): "auto" (Baukasten-App: ausblenden, Website-App: unverändert), "hide" oder "keep".
// Eigene Elemente: Klasse "elvado-hide-in-app" blendet in der App aus, "elvado-only-app" zeigt nur in der App. Das <body>-Element bekommt zusätzlich die Klasse "elvado-app".
declare(strict_types=1);

/** Marke und Plattform der aufrufenden App oder null. */
function rrw_appmode_detect(): ?array {
    $ua=(string)($_SERVER['HTTP_USER_AGENT']??'');
    if(preg_match('/ElvadoPressApp\/[\d.]+ \(brand=([a-z][a-z0-9]{2,19}); platform=(android|windows)\)/',$ua,$m))return ['brand'=>$m[1],'platform'=>$m[2],'via'=>'ua'];
    $p=(string)($_GET['rrw_app']??'');
    if($p==='off'){ if(!headers_sent())setcookie('rrw_app','',['expires'=>1,'path'=>'/']);return null; }
    if(preg_match('/^[a-z][a-z0-9]{2,19}$/',$p)){ if(!headers_sent())setcookie('rrw_app',$p,['expires'=>0,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);return ['brand'=>$p,'platform'=>'android','via'=>'param']; }
    $c=(string)($_COOKIE['rrw_app']??'');
    if(preg_match('/^[a-z][a-z0-9]{2,19}$/',$c))return ['brand'=>$c,'platform'=>'android','via'=>'cookie'];
    return null;
}

/** Soll für diese App der Kopf/Fuß der Website ausgeblendet werden? $own = rrw_apps_own(); $site = site.json. */
function rrw_appmode_hide(array $app,array $own,array $site): bool {
    $b=$own[$app['brand']]??null;if($b===null)return false;   // nur Apps des Build-Assistenten dieser Website
    $c=(string)($site['apps']['managed'][$app['brand'].':'.$app['platform']]['builder']['chrome']??'auto');
    if($c==='hide')return true;
    if($c==='keep')return false;
    return ($b['type']??'')==='content';
}

/** Eigene Stile in den Kopf der Seite einfügen und die Body-Klasse setzen. */
function rrw_appmode_inject(string $html,bool $hide): string {
    $css='<style id="elvado-app-css">.elvado-only-app{display:revert!important}'
        .($hide?'body.elvado-app .site-header,body.elvado-app #masthead,body.elvado-app .site-footer,body.elvado-app #colophon,body.elvado-app .wp-site-blocks>header,body.elvado-app .wp-site-blocks>footer,body.elvado-app .elvado-hide-in-app,body.elvado-app #wpadminbar{display:none!important}body.elvado-app{padding-top:0!important;margin-top:0!important}html{margin-top:0!important}':'.elvado-hide-in-app{display:revert!important}')
        .'</style>';
    if(!$hide)return $html;
    $html=preg_replace_callback('/<body\b([^>]*)>/i',function($m){
        $attrs=$m[1];
        if(preg_match('/\sclass=(["\'])(.*?)\1/is',$attrs)){ $attrs=preg_replace_callback('/\sclass=(["\'])(.*?)\1/is',fn($c)=>' class='.$c[1].trim($c[2].' elvado-app').$c[1],$attrs,1); }
        else $attrs.=' class="elvado-app"';
        return '<body'.$attrs.'>';
    },$html,1)??$html;
    $p=stripos($html,'</head>');
    return $p!==false?substr($html,0,$p).$css.substr($html,$p):$css.$html;
}

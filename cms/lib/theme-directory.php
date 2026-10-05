<?php
// Theme-Verzeichnis: kostenlose Themes suchen und mit einem Klick installieren.
// Quellen (nur diese, fest hinterlegt – keine frei wählbaren Adressen):
//  - Bootswatch (Bootstrap 5, MIT)        https://bootswatch.com
//  - WordPress.org Theme-Verzeichnis (GPL) https://api.wordpress.org
// Installiert wird ausschließlich das Design (CSS + Bilder) über rrw_import_theme_zip();
// WordPress-PHP und -Skripte werden nie ausgeführt.

const RRW_TD_HOSTS=['bootswatch.com','api.wordpress.org','downloads.wordpress.org','ts.w.org','ps.w.org','s.w.org','cdn.jsdelivr.net'];

function rrw_td_url_ok(string $url): bool {
    $p=parse_url($url);
    return is_array($p)&&($p['scheme']??'')==='https'&&in_array(strtolower((string)($p['host']??'')),RRW_TD_HOSTS,true)&&empty($p['user'])&&empty($p['port']);
}

// Lädt eine erlaubte HTTPS-Adresse (Weiterleitungen werden einzeln geprüft), mit Größenlimit.
function rrw_td_get(string $url, int $maxBytes=2097152, int $timeout=20): ?string {
    if(!function_exists('curl_init'))return null;
    for($hop=0;$hop<4;$hop++){
        if(!rrw_td_url_ok($url))return null;
        $body='';$ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>false,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>$timeout,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_USERAGENT=>'RicoReWi-CMS-ThemeDirectory/1.0',CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION=>function($c,$d) use(&$body,$maxBytes){ $body.=$d; return strlen($body)>$maxBytes?-1:strlen($d); }]);
        $ok=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$loc=(string)curl_getinfo($ch,CURLINFO_REDIRECT_URL);curl_close($ch);
        if($code>=300&&$code<400&&$loc!==''){$url=$loc;continue;}
        return ($ok!==false&&$code===200)?$body:null;
    }
    return null;
}

function rrw_td_cache(string $dataDir, string $key, int $ttl, callable $make): ?array {
    $dir=$dataDir.'/.tools';$file=$dir.'/themedir-'.preg_replace('/[^a-z0-9]/','',strtolower($key)).'.json';
    if(is_file($file)&&filemtime($file)>time()-$ttl){$d=json_decode((string)file_get_contents($file),true);if(is_array($d))return $d;}
    $d=$make();
    if(is_array($d)&&$d){if(!is_dir($dir))@mkdir($dir,0755,true);@file_put_contents($file,json_encode($d,JSON_UNESCAPED_UNICODE),LOCK_EX);return $d;}
    if(is_file($file)){$old=json_decode((string)file_get_contents($file),true);if(is_array($old))return $old;}
    return null;
}

function rrw_td_https(string $u): string { $u=trim($u); if(str_starts_with($u,'//'))$u='https:'.$u; return rrw_td_url_ok($u)?$u:''; }

// --- Bootswatch ---
function rrw_td_bootswatch_all(string $dataDir): array {
    $d=rrw_td_cache($dataDir,'bootswatch',86400,function(){
        $raw=rrw_td_get('https://bootswatch.com/api/5.json');$j=$raw?json_decode($raw,true):null;
        return is_array($j['themes']??null)?['themes'=>$j['themes']]:null;
    });
    $out=[];
    foreach(($d['themes']??[]) as $t){
        $slug=strtolower((string)($t['name']??''));if(!preg_match('/^[a-z]{2,30}$/',$slug))continue;
        $out[]=['source'=>'bootswatch','slug'=>$slug,'name'=>(string)$t['name'],'description'=>mb_substr((string)($t['description']??''),0,200),
            'author'=>'Thomas Park (Bootswatch)','license'=>'MIT','version'=>'5','rating'=>null,'installs'=>null,
            'thumbnail'=>rrw_td_https((string)($t['thumbnail']??'')),'preview'=>rrw_td_https((string)($t['preview']??'')),
            'supports'=>['Bootstrap 5','Farben & Schriften','Responsive','Dark/Light je nach Theme']];
    }
    return $out;
}

// --- WordPress.org ---
function rrw_td_wp_supports(array $tags): array {
    $map=['one-column'=>'1 Spalte','two-columns'=>'2 Spalten','three-columns'=>'3 Spalten','four-columns'=>'4 Spalten','left-sidebar'=>'Sidebar links','right-sidebar'=>'Sidebar rechts','grid-layout'=>'Raster-Layout',
        'custom-colors'=>'Eigene Farben','custom-logo'=>'Logo','custom-menu'=>'Menüs','featured-images'=>'Beitragsbilder','translation-ready'=>'Übersetzbar','accessibility-ready'=>'Barrierefrei','blog'=>'Blog','news'=>'News','e-commerce'=>'Shop','portfolio'=>'Portfolio','full-site-editing'=>'Block-Theme','block-styles'=>'Block-Stile','dark'=>'Dunkel','light'=>'Hell'];
    $out=[];foreach($map as $k=>$label)if(isset($tags[$k]))$out[]=$label;
    return $out;
}
function rrw_td_wp_search(string $q, int $page): ?array {
    $url='https://api.wordpress.org/themes/info/1.2/?action=query_themes&request%5Bper_page%5D=12&request%5Bpage%5D='.max(1,$page)
        .($q!==''?'&request%5Bsearch%5D='.rawurlencode($q):'&request%5Bbrowse%5D=popular')
        .'&request%5Bfields%5D%5Btags%5D=1&request%5Bfields%5D%5Bdescription%5D=1&request%5Bfields%5D%5Bpreview_url%5D=1&request%5Bfields%5D%5Bsections%5D=0&request%5Bfields%5D%5Bscreenshot_url%5D=1&request%5Bfields%5D%5Bactive_installs%5D=1&request%5Bfields%5D%5Brating%5D=1';
    $raw=rrw_td_get($url,1048576);$j=$raw?json_decode($raw,true):null;if(!is_array($j['themes']??null))return null;
    $items=[];
    foreach($j['themes'] as $t){
        $slug=(string)($t['slug']??'');if(!preg_match('/^[a-z0-9-]{2,80}$/',$slug))continue;
        $a=$t['author']??[];$author=is_array($a)?(string)($a['display_name']??$a['author']??''):(string)$a;
        $items[]=['source'=>'wordpress','slug'=>$slug,'name'=>(string)($t['name']??$slug),'description'=>mb_substr(trim(strip_tags((string)($t['description']??''))),0,200),
            'author'=>$author,'license'=>'GPL','version'=>(string)($t['version']??''),'rating'=>isset($t['rating'])?round(((float)$t['rating'])/20,1):null,'installs'=>isset($t['active_installs'])?(int)$t['active_installs']:null,
            'thumbnail'=>rrw_td_https((string)($t['screenshot_url']??'')),'preview'=>rrw_td_https((string)($t['preview_url']??'')),
            'supports'=>rrw_td_wp_supports(is_array($t['tags']??null)?$t['tags']:[])];
    }
    return ['items'=>$items,'pages'=>(int)($j['info']['pages']??1)];
}

function rrw_td_search(string $dataDir, string $source, string $q, int $page): array {
    $q=trim(mb_substr($q,0,60));
    if($source==='bootswatch'){
        $all=rrw_td_bootswatch_all($dataDir);if(!$all)return ['ok'=>false,'message'=>'Das Bootswatch-Verzeichnis ist gerade nicht erreichbar.'];
        if($q!=='')$all=array_values(array_filter($all,fn($t)=>stripos($t['name'].' '.$t['description'],$q)!==false));
        $per=12;$pages=max(1,(int)ceil(count($all)/$per));$page=min(max(1,$page),$pages);
        return ['ok'=>true,'items'=>array_slice($all,($page-1)*$per,$per),'pages'=>$pages,'page'=>$page];
    }
    if($source==='wordpress'){
        $r=rrw_td_cache($dataDir,'wp'.md5($q.'|'.$page),3600,fn()=>rrw_td_wp_search($q,$page));
        if(!$r)return ['ok'=>false,'message'=>'Das WordPress-Theme-Verzeichnis ist gerade nicht erreichbar.'];
        return ['ok'=>true,'items'=>$r['items'],'pages'=>$r['pages'],'page'=>max(1,$page)];
    }
    return ['ok'=>false,'message'=>'Unbekannte Quelle.'];
}

// Installiert ein Theme aus einer Quelle. Gibt die ZIP-Datei (temporär) zurück oder wirft.
function rrw_td_build_zip(string $dataDir, string $source, string $slug): string {
    if(!class_exists('ZipArchive'))throw new RuntimeException('ZIP-Unterstützung fehlt auf dem Server');
    $tmp=tempnam(sys_get_temp_dir(),'rrwtd');
    if($source==='wordpress'){
        if(!preg_match('/^[a-z0-9-]{2,80}$/',$slug))throw new RuntimeException('Ungültiges Theme');
        $raw=rrw_td_get('https://api.wordpress.org/themes/info/1.2/?action=theme_information&request%5Bslug%5D='.rawurlencode($slug).'&request%5Bfields%5D%5Bdownload_link%5D=1',524288);
        $j=$raw?json_decode($raw,true):null;$dl=is_array($j)?(string)($j['download_link']??''):'';
        if($dl===''||!str_starts_with($dl,'https://downloads.wordpress.org/theme/'))throw new RuntimeException('Theme nicht gefunden oder nicht erreichbar');
        $zipData=rrw_td_get($dl,15728640,60);if($zipData===null||strlen($zipData)<100)throw new RuntimeException('Download fehlgeschlagen');
        file_put_contents($tmp,$zipData);return $tmp;
    }
    if($source==='bootswatch'){
        $found=null;foreach(rrw_td_bootswatch_all($dataDir) as $t)if($t['slug']===$slug){$found=$t;break;}
        if(!$found)throw new RuntimeException('Theme nicht gefunden');
        $css=rrw_td_get('https://bootswatch.com/5/'.$slug.'/bootstrap.min.css',1048576);if($css===null||strlen($css)<1000)throw new RuntimeException('Download fehlgeschlagen');
        $shot=$found['thumbnail']!==''?rrw_td_get($found['thumbnail'],1048576):null;
        $meta=['id'=>'bootswatch-'.$slug,'name'=>'Bootswatch '.$found['name'],'version'=>'5','author'=>$found['author'],'license'=>'MIT','description'=>$found['description'].' – Bootstrap-5-Design von Bootswatch.','bootstrap'=>true,'compatibility'=>'bootstrap-css'];
        $z=new ZipArchive();if($z->open($tmp,ZipArchive::OVERWRITE)!==true)throw new RuntimeException('ZIP konnte nicht erstellt werden');
        $z->addFromString('theme.json',json_encode($meta,JSON_UNESCAPED_UNICODE));$z->addFromString('theme.css',$css);
        if($shot!==null&&strncmp($shot,"\x89PNG",4)===0)$z->addFromString('screenshot.png',$shot);
        $z->close();return $tmp;
    }
    throw new RuntimeException('Unbekannte Quelle');
}

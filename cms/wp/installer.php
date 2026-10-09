<?php
// Installation von WordPress-Plugins (und später -Themes): Suche im WordPress.org-Verzeichnis, Download, sicheres Entpacken.
// Entpackt werden alle Dateien einschließlich PHP (das ist der Zweck), aber: keine Pfad-Tricks, keine Symlinks, keine .htaccess/.user.ini,
// Größen- und Anzahl-Grenzen. Ausführbar ist PHP in wp-content nur über die CMS-Laufzeit (cms/wp-content/.htaccess sperrt direkte Aufrufe).
require_once __DIR__.'/../lib/theme-directory.php';

function elvado_wpi_clean_name(string $n): ?string {
    $n=str_replace('\\','/',$n);
    if($n===''||str_contains($n,"\0")||str_starts_with($n,'/')||preg_match('#^[a-zA-Z]:#',$n))return null;
    foreach(explode('/',$n) as $seg)if($seg==='..')return null;
    if(substr_count($n,'/')>14||strlen($n)>240)return null;
    return $n;
}
function elvado_wpi_blocked_file(string $base): bool {
    $b=strtolower($base);
    return in_array($b,['.htaccess','.htpasswd','.user.ini','php.ini','web.config','.env','.git','.gitignore'],true)||str_starts_with($b,'.ht');
}
/** Entpackt ein ZIP nach $dest (neuer, leerer Ordner). Gibt die Anzahl Dateien zurück oder wirft. */
function elvado_wpi_extract(string $zipPath, string $dest, string $stripPrefix=''): int {
    if(!class_exists('ZipArchive'))throw new RuntimeException('ZIP-Unterstützung fehlt auf dem Server');
    $z=new ZipArchive();if($z->open($zipPath)!==true)throw new RuntimeException('ZIP konnte nicht geöffnet werden');
    if($z->numFiles>20000){$z->close();throw new RuntimeException('Das Paket enthält zu viele Dateien');}
    $total=0;$count=0;
    for($i=0;$i<$z->numFiles;$i++){$st=$z->statIndex($i);$total+=(int)($st['size']??0);}
    if($total>300*1048576){$z->close();throw new RuntimeException('Das Paket ist entpackt zu groß (über 300 MB)');}
    if(!is_dir($dest)&&!@mkdir($dest,0775,true)){$z->close();throw new RuntimeException('Zielordner konnte nicht angelegt werden');}
    $destReal=realpath($dest);
    for($i=0;$i<$z->numFiles;$i++){
        $name=elvado_wpi_clean_name((string)$z->getNameIndex($i));if($name===null)continue;
        if($stripPrefix!==''){if(!str_starts_with($name,$stripPrefix))continue;$name=substr($name,strlen($stripPrefix));}
        if($name===''||str_ends_with($name,'/'))continue;
        if(elvado_wpi_blocked_file(basename($name)))continue;
        // Symlink-Einträge (Unix-Modus 0120000) überspringen
        $attr=0;$op=0;$z->getExternalAttributesIndex($i,$op,$attr);if($op===ZipArchive::OPSYS_UNIX&&(($attr>>16)&0170000)===0120000)continue;
        $data=$z->getFromIndex($i);if(!is_string($data))continue;
        if(strlen($data)>25*1048576)continue;
        $target=$destReal.'/'.$name;$dir=dirname($target);
        if(!is_dir($dir)&&!@mkdir($dir,0775,true))continue;
        $dr=realpath($dir);if(!$dr||!str_starts_with($dr.'/',$destReal.'/'))continue;     // nie aus dem Zielordner herausschreiben
        if(@file_put_contents($target,$data)!==false)$count++;
    }
    $z->close();
    return $count;
}
/** Gemeinsamen Top-Level-Ordner des ZIPs finden („plugin-slug/“) – leer, wenn Dateien direkt im Wurzelverzeichnis liegen. */
function elvado_wpi_zip_root(string $zipPath): string {
    $z=new ZipArchive();if($z->open($zipPath)!==true)return '';
    $roots=[];for($i=0;$i<$z->numFiles;$i++){$n=elvado_wpi_clean_name((string)$z->getNameIndex($i));if($n===null)continue;if(str_starts_with($n,'__MACOSX/'))continue;$p=explode('/',$n,2);$roots[$p[0].(count($p)>1?'/':'')]=1;}
    $z->close();
    $dirs=array_filter(array_keys($roots),fn($r)=>str_ends_with($r,'/'));
    return count($roots)===1&&count($dirs)===1?(string)array_key_first($roots):'';
}
function elvado_wpi_slug(string $s): string { $s=strtolower(trim($s));$s=preg_replace('/[^a-z0-9_-]+/','-',$s);return trim(substr((string)$s,0,80),'-'); }

/** Installiert ein Plugin-ZIP in WP_PLUGIN_DIR. Gibt ['slug'=>…, 'plugins'=>[Dateien mit Header]] zurück. */
function elvado_wpi_install_plugin_zip(string $zipPath, string $slugHint=''): array {
    $root=elvado_wpi_zip_root($zipPath);
    $slug=elvado_wpi_slug($slugHint!==''?$slugHint:rtrim($root,'/'));
    if($slug==='')throw new RuntimeException('Der Plugin-Name konnte nicht ermittelt werden');
    if(!is_dir(WP_PLUGIN_DIR))@mkdir(WP_PLUGIN_DIR,0775,true);
    $final=WP_PLUGIN_DIR.'/'.$slug;$tmp=WP_PLUGIN_DIR.'/.tmp-'.bin2hex(random_bytes(4));
    try{
        $n=elvado_wpi_extract($zipPath,$tmp,$root);
        if($n<1)throw new RuntimeException('Das Paket enthält keine Dateien');
        // Es muss mindestens eine PHP-Datei mit Plugin-Header geben
        $found=[];foreach(glob($tmp.'/*.php')?:[] as $f){if(get_plugin_data($f)['Name']!=='')$found[]=basename($f);}
        if(!$found)throw new RuntimeException('Kein WordPress-Plugin: Die Hauptdatei mit „Plugin Name:“ fehlt');
        $wasActive=false;$old=null;
        if(is_dir($final)){ $old=$final.'.old-'.bin2hex(random_bytes(3));@rename($final,$old); }
        if(!@rename($tmp,$final)){ if($old)@rename($old,$final); throw new RuntimeException('Plugin konnte nicht installiert werden'); }
        if($old)elvado_wp_rmdir($old);
    } finally { if(is_dir($tmp))elvado_wpi_rm_tmp($tmp); }
    return ['slug'=>$slug,'files'=>$found];
}
function elvado_wpi_rm_tmp(string $dir): void {
    if(!str_contains(basename($dir),'.tmp-')||is_link($dir))return;
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $f){$f->isDir()&&!$f->isLink()?@rmdir($f->getPathname()):@unlink($f->getPathname());}
    @rmdir($dir);
}

/* ───────── WordPress.org: Plugin-Suche ───────── */
function elvado_wpi_search_plugins(string $dataDir, string $q, int $page): array {
    $q=trim(mb_substr($q,0,60));$page=max(1,$page);
    $r=elvado_td_cache($dataDir,'wpplug'.md5($q.'|'.$page),3600,function() use($q,$page){
        $url='https://api.wordpress.org/plugins/info/1.2/?action=query_plugins&request%5Bper_page%5D=12&request%5Bpage%5D='.$page.($q!==''?'&request%5Bsearch%5D='.rawurlencode($q):'&request%5Bbrowse%5D=popular')
            .'&request%5Bfields%5D%5Bshort_description%5D=1&request%5Bfields%5D%5Bicons%5D=1&request%5Bfields%5D%5Bactive_installs%5D=1&request%5Bfields%5D%5Brating%5D=1&request%5Bfields%5D%5Btested%5D=1&request%5Bfields%5D%5Brequires%5D=1&request%5Bfields%5D%5Brequires_php%5D=1&request%5Bfields%5D%5Bdescription%5D=0&request%5Bfields%5D%5Bsections%5D=0&request%5Bfields%5D%5Bbanners%5D=0&request%5Bfields%5D%5Bdownload_link%5D=0';
        $raw=elvado_td_get($url,1572864);$j=$raw?json_decode($raw,true):null;if(!is_array($j['plugins']??null))return null;
        $items=[];
        foreach($j['plugins'] as $p){
            $slug=(string)($p['slug']??'');if(!preg_match('/^[a-z0-9_-]{2,80}$/',$slug))continue;
            $ic=is_array($p['icons']??null)?$p['icons']:[];$icon=elvado_td_https((string)($ic['2x']??$ic['1x']??$ic['default']??''));
            $items[]=['slug'=>$slug,'name'=>html_entity_decode(strip_tags((string)($p['name']??$slug)),ENT_QUOTES,'UTF-8'),'version'=>(string)($p['version']??''),'author'=>html_entity_decode(strip_tags((string)($p['author']??'')),ENT_QUOTES,'UTF-8'),
                'description'=>mb_substr(html_entity_decode(strip_tags((string)($p['short_description']??'')),ENT_QUOTES,'UTF-8'),0,220),'rating'=>isset($p['rating'])?round(((float)$p['rating'])/20,1):null,'num_ratings'=>(int)($p['num_ratings']??0),
                'installs'=>(int)($p['active_installs']??0),'tested'=>(string)($p['tested']??''),'requires'=>(string)($p['requires']??''),'requires_php'=>is_string($p['requires_php']??null)?$p['requires_php']:'','icon'=>$icon];
        }
        return ['items'=>$items,'pages'=>(int)($j['info']['pages']??1)];
    });
    if(!$r)return ['ok'=>false,'message'=>'Das WordPress-Plugin-Verzeichnis ist gerade nicht erreichbar.'];
    return ['ok'=>true,'items'=>$r['items'],'pages'=>$r['pages'],'page'=>$page];
}
function elvado_wpi_download_plugin(string $slug): string {
    if(!preg_match('/^[a-z0-9_-]{2,80}$/',$slug))throw new RuntimeException('Ungültiges Plugin');
    $raw=elvado_td_get('https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request%5Bslug%5D='.rawurlencode($slug).'&request%5Bfields%5D%5Bsections%5D=0&request%5Bfields%5D%5Bdescription%5D=0&request%5Bfields%5D%5Bbanners%5D=0&request%5Bfields%5D%5Bicons%5D=0',1048576);
    $j=$raw?json_decode($raw,true):null;$dl=is_array($j)?(string)($j['download_link']??''):'';
    if($dl===''||!str_starts_with($dl,'https://downloads.wordpress.org/plugin/'))throw new RuntimeException('Plugin nicht gefunden oder nicht erreichbar');
    $data=elvado_td_get($dl,41943040,90);if($data===null||strlen($data)<100)throw new RuntimeException('Download fehlgeschlagen');
    $tmp=tempnam(sys_get_temp_dir(),'elvadowp');file_put_contents($tmp,$data);return $tmp;
}

/* ───────── Themes (PHP) ───────── */
/** Installiert ein WordPress-Theme-ZIP nach cms/wp-content/themes/<slug>. Erwartet eine style.css mit „Theme Name:“. */
function elvado_wpi_install_theme_zip(string $zipPath, string $slugHint=''): array {
    $root=elvado_wpi_zip_root($zipPath);
    $slug=elvado_wpi_slug($slugHint!==''?$slugHint:rtrim($root,'/'));
    if($slug==='')throw new RuntimeException('Der Theme-Name konnte nicht ermittelt werden');
    $themes=get_theme_root();if(!is_dir($themes))@mkdir($themes,0775,true);
    $final=$themes.'/'.$slug;$tmp=$themes.'/.tmp-'.bin2hex(random_bytes(4));
    try{
        $n=elvado_wpi_extract($zipPath,$tmp,$root);
        if($n<1)throw new RuntimeException('Das Paket enthält keine Dateien');
        $css=$tmp.'/style.css';
        if(!is_file($css)||get_file_data($css,['Name'=>'Theme Name'])['Name']==='')throw new RuntimeException('Kein WordPress-Theme: style.css mit „Theme Name:“ fehlt');
        if(!is_file($tmp.'/index.php')&&!is_file($tmp.'/templates/index.html'))throw new RuntimeException('Das Theme enthält keine index.php');
        $old=null;if(is_dir($final)){$old=$final.'.old-'.bin2hex(random_bytes(3));@rename($final,$old);}
        if(!@rename($tmp,$final)){if($old)@rename($old,$final);throw new RuntimeException('Theme konnte nicht installiert werden');}
        if($old)elvado_wp_rmdir($old);
    } finally { if(is_dir($tmp))elvado_wpi_rm_tmp($tmp); }
    return ['slug'=>$slug];
}
function elvado_wpi_download_theme(string $slug): string {
    if(!preg_match('/^[a-z0-9_-]{2,80}$/',$slug))throw new RuntimeException('Ungültiges Theme');
    $raw=elvado_td_get('https://api.wordpress.org/themes/info/1.2/?action=theme_information&request%5Bslug%5D='.rawurlencode($slug).'&request%5Bfields%5D%5Bdownload_link%5D=1',1048576);
    $j=$raw?json_decode($raw,true):null;$dl=is_array($j)?(string)($j['download_link']??''):'';
    if($dl===''||!str_starts_with($dl,'https://downloads.wordpress.org/theme/'))throw new RuntimeException('Theme nicht gefunden oder nicht erreichbar');
    $data=elvado_td_get($dl,41943040,90);if($data===null||strlen($data)<100)throw new RuntimeException('Download fehlgeschlagen');
    $tmp=tempnam(sys_get_temp_dir(),'elvadowt');file_put_contents($tmp,$data);return $tmp;
}

/** Alle installierten Themes (wp-content/themes + mitgeliefertes Standard-Theme) mit Kopfdaten und Fähigkeiten. */
function elvado_wpi_list_themes(): array {
    $out=[];
    foreach(elvado_wp_theme_roots() as $root){
        $base=$root['dir'];
        foreach(glob($base.'/*/style.css')?:[] as $css){
            $slug=basename(dirname($css));if(!preg_match('/^[a-z0-9_-]{1,80}$/',$slug)||isset($out[$slug]))continue;
            if(is_file(dirname($css).'/theme.css')&&!is_file(dirname($css).'/index.php'))continue;   // CMS-eigene CSS-Themes gehören nicht hierher
            $h=get_file_data($css,['Name'=>'Theme Name','Version'=>'Version','Author'=>'Author','Description'=>'Description','Template'=>'Template','ThemeURI'=>'Theme URI','RequiresPHP'=>'Requires PHP','Tags'=>'Tags']);
            if($h['Name']==='')continue;
            $dir=dirname($css);$shot='';foreach(['screenshot.png','screenshot.jpg','screenshot.jpeg','screenshot.webp'] as $s)if(is_file($dir.'/'.$s)){$shot=$s;break;}
            $block=is_file($dir.'/templates/index.html')&&!is_file($dir.'/index.php');
            $out[$slug]=['slug'=>$slug,'name'=>$h['Name'],'version'=>$h['Version'],'author'=>strip_tags($h['Author']),'description'=>mb_substr(strip_tags($h['Description']),0,300),'parent'=>$h['Template'],
                'tags'=>array_values(array_filter(array_map('trim',explode(',',$h['Tags'])))),'bundled'=>$root['kind']==='native','origin'=>$root['kind'],'block_theme'=>$block,'screenshot'=>$shot!==''?$root['rel'].'/'.$slug.'/'.$shot:''];
            if($h['Template']!==''&&!array_filter(elvado_wp_theme_roots(),fn($rt)=>is_dir($rt['dir'].'/'.$h['Template'])))$out[$slug]['error']='Das Eltern-Theme „'.$h['Template'].'“ fehlt.';
        }
    }
    ksort($out);return array_values($out);
}
function elvado_wpi_search_themes(string $dataDir, string $q, int $page): array {
    $q=trim(mb_substr($q,0,60));$page=max(1,$page);
    $r=elvado_td_cache($dataDir,'wptheme'.md5($q.'|'.$page),3600,function() use($q,$page){
        $url='https://api.wordpress.org/themes/info/1.2/?action=query_themes&request%5Bper_page%5D=12&request%5Bpage%5D='.$page.($q!==''?'&request%5Bsearch%5D='.rawurlencode($q):'&request%5Bbrowse%5D=popular')
            .'&request%5Bfields%5D%5Bscreenshot_url%5D=1&request%5Bfields%5D%5Bdescription%5D=1&request%5Bfields%5D%5Bratings%5D=1&request%5Bfields%5D%5Brating%5D=1&request%5Bfields%5D%5Bactive_installs%5D=1&request%5Bfields%5D%5Btags%5D=1&request%5Bfields%5D%5Bversions%5D=0';
        $raw=elvado_td_get($url,1572864);$j=$raw?json_decode($raw,true):null;if(!is_array($j['themes']??null))return null;
        $items=[];
        foreach($j['themes'] as $t){
            $slug=(string)($t['slug']??'');if(!preg_match('/^[a-z0-9_-]{2,80}$/',$slug))continue;
            $tags=is_array($t['tags']??null)?array_values($t['tags']):[];
            $items[]=['slug'=>$slug,'name'=>html_entity_decode(strip_tags((string)($t['name']??$slug)),ENT_QUOTES,'UTF-8'),'version'=>(string)($t['version']??''),'author'=>html_entity_decode(strip_tags((string)(is_array($t['author']??null)?($t['author']['display_name']??$t['author']['user_nicename']??''):($t['author']??''))),ENT_QUOTES,'UTF-8'),
                'description'=>mb_substr(html_entity_decode(strip_tags((string)($t['description']??'')),ENT_QUOTES,'UTF-8'),0,220),'rating'=>isset($t['rating'])?round(((float)$t['rating'])/20,1):null,'installs'=>(int)($t['active_installs']??0),
                'screenshot'=>elvado_td_https((string)($t['screenshot_url']??'')),'tags'=>array_slice(array_map(fn($x)=>mb_substr(strip_tags((string)$x),0,30),$tags),0,8),'preview_url'=>elvado_td_https((string)($t['preview_url']??''))];
        }
        return ['items'=>$items,'pages'=>(int)($j['info']['pages']??1)];
    });
    if(!$r)return ['ok'=>false,'message'=>'Das WordPress-Theme-Verzeichnis ist gerade nicht erreichbar.'];
    return ['ok'=>true,'items'=>$r['items'],'pages'=>$r['pages'],'page'=>$page];
}
/** Theme aktivieren: Optionen setzen und die Auslieferung über die WordPress-Theme-Laufzeit einschalten. */
function elvado_wpi_activate_theme(string $slug): ?string {
    $themes=array_column(elvado_wpi_list_themes(),null,'slug');
    if(!isset($themes[$slug]))return 'Theme nicht gefunden';
    if(!empty($themes[$slug]['error']))return $themes[$slug]['error'];
    update_option('stylesheet',$slug);update_option('template',$themes[$slug]['parent']?:$slug);
    $f=ELVADO_WP_DATA.'/front-on';if(!is_dir(ELVADO_WP_DATA))@mkdir(ELVADO_WP_DATA,0775,true);
    if(@file_put_contents($f,gmdate('c'))===false)return 'Aktivierung konnte nicht gespeichert werden (Schreibrechte für cms/data/.wp prüfen).';
    return null;
}
function elvado_wpi_deactivate_theme(): void { $f=ELVADO_WP_DATA.'/front-on';if(is_file($f))@unlink($f); }
/** Signierter Vorschau-Schlüssel (15 Minuten) für wp-front.php. */
function elvado_wpi_preview_token(string $slug, ?int $now=null, int $ttl=900): string { $exp=($now??time())+$ttl;return $slug.'.'.$exp.'.'.hash_hmac('sha256',$slug.'|'.$exp,wp_salt('preview')); }

/* ───────── Updates (WordPress.org) ───────── */
/** Neueste Version eines Plugins/Themes im WordPress-Verzeichnis (6 Stunden zwischengespeichert); null = nicht gefunden/nicht erreichbar. */
function elvado_wpi_latest_version(string $dataDir, string $type, string $slug): ?string {
    if(!preg_match('/^[a-z0-9_-]{2,80}$/',$slug)||!in_array($type,['plugin','theme'],true))return null;
    $r=elvado_td_cache($dataDir,'wpver'.$type.md5($slug),21600,function() use($type,$slug){
        $url=$type==='plugin'
            ?'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request%5Bslug%5D='.rawurlencode($slug).'&request%5Bfields%5D%5Bsections%5D=0&request%5Bfields%5D%5Bdescription%5D=0&request%5Bfields%5D%5Bbanners%5D=0&request%5Bfields%5D%5Bicons%5D=0&request%5Bfields%5D%5Bcontributors%5D=0&request%5Bfields%5D%5Bratings%5D=0&request%5Bfields%5D%5Bversions%5D=0&request%5Bfields%5D%5Breviews%5D=0&request%5Bfields%5D%5Bscreenshots%5D=0&request%5Bfields%5D%5Btags%5D=0&request%5Bfields%5D%5Bfaq%5D=0'
            :'https://api.wordpress.org/themes/info/1.2/?action=theme_information&request%5Bslug%5D='.rawurlencode($slug);
        $raw=elvado_td_get($url,1048576);$j=$raw?json_decode($raw,true):null;
        return is_array($j)&&is_string($j['version']??null)&&$j['version']!==''?['version'=>$j['version']]:['version'=>''];
    });
    $v=(string)($r['version']??'');return $v!==''?$v:null;
}
/** Verfügbare Updates für installierte (nicht mitgelieferte) Plugins und Themes. */
function elvado_wpi_updates(string $dataDir): array {
    $out=[];$n=0;
    foreach(get_plugins() as $file=>$d){
        $slug=explode('/',$file)[0];if(str_ends_with($slug,'.php')||$n++>=40)continue;
        $latest=elvado_wpi_latest_version($dataDir,'plugin',$slug);
        if($latest&&version_compare($latest,(string)$d['Version'],'>'))$out[]=['type'=>'plugin','slug'=>$slug,'name'=>$d['Name'],'current'=>(string)$d['Version'],'latest'=>$latest];
    }
    foreach(elvado_wpi_list_themes() as $t){
        if($t['bundled']||$n++>=40)continue;
        $latest=elvado_wpi_latest_version($dataDir,'theme',$t['slug']);
        if($latest&&version_compare($latest,(string)$t['version'],'>'))$out[]=['type'=>'theme','slug'=>$t['slug'],'name'=>$t['name'],'current'=>(string)$t['version'],'latest'=>$latest];
    }
    return $out;
}

/* ───────── Sprachpakete (translate.wordpress.org) ───────── */
/** Lädt das .mo-Sprachpaket für Core ('core'), ein Theme oder Plugin und legt es unter cms/wp-content/languages/ ab. */
function elvado_wpi_fetch_translation(string $type, string $slug, string $version, string $locale='de_DE'): bool {
    if(!in_array($type,['core','theme','plugin'],true)||!preg_match('/^[a-z]{2,3}(_[A-Z]{2})?$/',$locale)||($type!=='core'&&!preg_match('/^[a-z0-9_-]{2,80}$/',$slug)))return false;
    $ver=rawurlencode($type==='core'?implode('.',array_slice(explode('.',$version),0,2)):$version);
    $url=$type==='core'?'https://api.wordpress.org/translations/core/1.0/?version='.$ver:'https://api.wordpress.org/translations/'.$type.'s/1.0/?slug='.rawurlencode($slug).'&version='.$ver;
    $raw=elvado_td_get($url,3145728);$j=$raw?json_decode($raw,true):null;if(!is_array($j['translations']??null))return false;
    $pkg='';foreach($j['translations'] as $t)if(($t['language']??'')===$locale){ $pkg=(string)($t['package']??'');break; }
    if($pkg===''||!str_starts_with($pkg,'https://downloads.wordpress.org/translation/'))return false;
    $zipData=elvado_td_get($pkg,15728640,90);if($zipData===null||strlen($zipData)<100||!class_exists('ZipArchive'))return false;
    $tmp=tempnam(sys_get_temp_dir(),'elvadotr');file_put_contents($tmp,$zipData);$ok=false;
    $zip=new ZipArchive();
    if($zip->open($tmp)===true){
        $name=$type==='core'?$locale.'.mo':$slug.'-'.$locale.'.mo';$dir=elvado_wp_lang_dir().($type==='core'?'':'/'.$type.'s');
        $st=$zip->statName($name);
        if($st&&$st['size']>0&&$st['size']<=8388608){
            $data=$zip->getFromName($name);
            if($data!==false){ if(!is_dir($dir))@mkdir($dir,0775,true);$f=$dir.'/'.$name;$t2=$f.'.tmp'.bin2hex(random_bytes(3));if(@file_put_contents($t2,$data)!==false&&@rename($t2,$f))$ok=true;else @unlink($t2); }
        }
        $zip->close();
    }
    @unlink($tmp);
    if($ok){ unset($GLOBALS['elvado_wp_mo'][$type==='core'?'default':$slug],$GLOBALS['elvado_wp_mo_tried'][$type==='core'?'default':$slug]); }
    return $ok;
}
/** Sprachpakete für Core und alle installierten Plugins/Themes (best effort). @return array{ok:int,fail:int} */
function elvado_wpi_fetch_all_translations(string $locale='de_DE'): array {
    $ok=0;$fail=0;$n=0;
    $one=function(bool $r) use(&$ok,&$fail){ $r?$ok++:$fail++; };
    $one(elvado_wpi_fetch_translation('core','',ELVADO_WP_VERSION,$locale));
    foreach(get_plugins() as $file=>$d){ $slug=explode('/',$file)[0];if(str_ends_with($slug,'.php')||$n++>=40)continue;$one(elvado_wpi_fetch_translation('plugin',$slug,(string)$d['Version'],$locale)); }
    foreach(elvado_wpi_list_themes() as $t){ if($t['bundled']||$n++>=40)continue;$one(elvado_wpi_fetch_translation('theme',$t['slug'],(string)$t['version'],$locale)); }
    return ['ok'=>$ok,'fail'=>$fail];
}

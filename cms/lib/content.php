<?php
declare(strict_types=1);

// ELVADO_CONTENT_DIR (Konstante) verlegt den Ordner – nur für Tests.
function elvado_content_root(): string { $d=defined('ELVADO_CONTENT_DIR')?(string)ELVADO_CONTENT_DIR:__DIR__.'/../content/pages';if(!is_dir($d))@mkdir($d,0755,true);return $d; }
function elvado_content_frontmatter(array $p): string {
    $data=['title'=>(string)($p['title']??''),'slug'=>(string)($p['slug']??''),'enabled'=>!empty($p['enabled']),'headline'=>(string)($p['headline']??''),'intro'=>(string)($p['intro']??'')];
    $yaml="---\n";foreach($data as $k=>$v){if(is_bool($v))$yaml.=$k.': '.($v?'true':'false')."\n";else $yaml.=$k.': '.json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";}return $yaml."---\n\n";
}
function elvado_content_markdown_body(array $p): string {
    $out='';foreach(array_merge((array)($p['blocks_before']??[]),(array)($p['blocks_after']??[])) as $b){if(empty($b['enabled']))continue;$t=$b['type']??'text';if($t==='heading')$out.=str_repeat('#',max(2,min(4,(int)($b['level']??2)))).' '.($b['text']??'')."\n\n";elseif($t==='text')$out.=($b['text']??'')."\n\n";elseif($t==='quote')$out.='> '.str_replace("\n","\n> ",(string)($b['text']??''))."\n\n";elseif($t==='divider')$out.="---\n\n";elseif($t==='html')$out.=($b['html']??'')."\n\n";}return $out;
}
function elvado_content_sync_from_site(array $site): array {
    // Spiegel Website → Dateien. Eine Datei, die sich seit dem letzten Abgleich von außen geändert hat (Git, Deploy, Entwickler-Werkzeug), wird NICHT überschrieben –
    // elvado_content_pull() übernimmt sie vorher in die Website; bleibt sie liegen (Konflikt), bleibt die Datei unverändert.
    $root=elvado_content_root();$written=[];$man=elvado_content_manifest();
    foreach((array)($site['pages']??[]) as $p){if(($p['type']??'')!=='custom')continue;$slug=elvado_slug((string)($p['slug']??$p['title']??'seite'));$dir=$root.'/'.$slug;if(!is_dir($dir))@mkdir($dir,0755,true);$file=$dir.'/page.md';
        if(isset($man['pages'][$slug])&&is_file($file)&&elvado_content_hash((string)file_get_contents($file))!==$man['pages'][$slug])continue;
        $txt=elvado_content_frontmatter($p).elvado_content_markdown_body($p);elvado_write_atomic($file,$txt);$man['pages'][$slug]=elvado_content_hash($txt);$written[]='cms/content/pages/'.$slug.'/page.md';}
    $man=elvado_gitconfig_export($site,$man);
    elvado_content_manifest_save($man);
    return $written;
}
function elvado_content_scan(): array {
    $out=[];foreach(glob(elvado_content_root().'/*/page.md')?:[] as $file){$raw=(string)file_get_contents($file);$title='';$slug=basename(dirname($file));if(preg_match('/^---\s*(.*?)\s*---/s',$raw,$m)){foreach(preg_split('/\R/',$m[1]) as $line){if(!str_contains($line,':'))continue;[$k,$v]=array_map('trim',explode(':',$line,2));$v=trim($v," \t\n\r\0\x0B\"'");if($k==='title')$title=$v;if($k==='slug'&&$v!=='')$slug=$v;}}$out[]=['slug'=>$slug,'title'=>$title?:$slug,'file'=>'cms/content/pages/'.basename(dirname($file)).'/page.md','mtime'=>date(DATE_ATOM,filemtime($file)?:time())];}return $out;
}

function elvado_content_parse_frontmatter(string $raw): array {
    $meta=[];$body=$raw;
    if(preg_match('/^---\s*\R(.*?)\R---\s*\R?/s',$raw,$m)){
        $body=substr($raw,strlen($m[0]));
        foreach(preg_split('/\R/',$m[1]) as $line){
            if(!str_contains($line,':'))continue;
            [$k,$v]=array_map('trim',explode(':',$line,2));
            if($k==='')continue;
            if($v==='true'||$v==='false')$meta[$k]=$v==='true';
            else {
                $j=json_decode($v,true);
                $meta[$k]=$j===null&&strtolower($v)!=='null'?trim($v," \t\n\r\0\x0B\"'"):$j;
            }
        }
    }
    return ['meta'=>$meta,'body'=>$body];
}
function elvado_content_markdown_blocks(string $body): array {
    $body=str_replace(["\r\n","\r"],"\n",$body);$lines=explode("\n",$body);$blocks=[];$paragraph=[];
    $flush=function() use (&$paragraph,&$blocks){
        $text=trim(implode("\n",$paragraph));$paragraph=[];
        if($text!=='')$blocks[]=['id'=>'md_'.bin2hex(random_bytes(4)),'type'=>'text','enabled'=>true,'text'=>$text];
    };
    foreach($lines as $line){
        if(preg_match('/^(#{2,4})\s+(.+)$/',$line,$m)){
            $flush();$blocks[]=['id'=>'md_'.bin2hex(random_bytes(4)),'type'=>'heading','enabled'=>true,'level'=>strlen($m[1]),'text'=>trim($m[2])];continue;
        }
        if(preg_match('/^>\s?(.*)$/',$line,$m)){
            $flush();$blocks[]=['id'=>'md_'.bin2hex(random_bytes(4)),'type'=>'quote','enabled'=>true,'text'=>trim($m[1])];continue;
        }
        if(trim($line)==='---'){$flush();$blocks[]=['id'=>'md_'.bin2hex(random_bytes(4)),'type'=>'divider','enabled'=>true];continue;}
        if(trim($line)===''){$flush();continue;}
        $paragraph[]=$line;
    }
    $flush();return $blocks;
}
function elvado_content_import_to_site(array $site,?string $onlySlug=null): array {
    $pages=is_array($site['pages']??null)?$site['pages']:[];
    $imported=[];
    foreach(glob(elvado_content_root().'/*/page.md')?:[] as $file){
        $folder=basename(dirname($file));if($onlySlug!==null&&$onlySlug!==''&&$folder!==$onlySlug)continue;
        $parsed=elvado_content_parse_frontmatter((string)file_get_contents($file));$m=$parsed['meta'];
        $slug=elvado_slug((string)($m['slug']??$folder));$title=trim((string)($m['title']??$slug));if($title==='')$title=$slug;
        $idx=null;foreach($pages as $i=>$p)if(($p['type']??'')==='custom'&&($p['slug']??'')===$slug){$idx=$i;break;}
        $page=$idx!==null?$pages[$idx]:['id'=>'page_'.bin2hex(random_bytes(5)),'type'=>'custom','system_target'=>'','native_enabled'=>false,'text_overrides'=>[],'blocks_after'=>[]];
        $page['slug']=$slug;$page['title']=$title;$page['enabled']=!array_key_exists('enabled',$m)||!empty($m['enabled']);
        $page['headline']=trim((string)($m['headline']??$page['headline']??''));$page['intro']=trim((string)($m['intro']??$page['intro']??''));
        $page['blocks_before']=elvado_content_markdown_blocks((string)$parsed['body']);
        if($idx!==null)$pages[$idx]=$page;else $pages[]=$page;
        $imported[]=['slug'=>$slug,'title'=>$title,'file'=>'cms/content/pages/'.$folder.'/page.md'];
    }
    $site['pages']=$pages;return ['site'=>$site,'imported'=>$imported];
}

/* ───────── Dateien (Git) → Website: Seiten und Beiträge aus cms/content werden automatisch übernommen ─────────
   Inhalte dürfen als Dateien im Repository gepflegt werden (Entwickler-Werkzeuge arbeiten über Git): cms/content/pages/<slug>/page.md und cms/content/posts/<slug>/post.md.
   Das Manifest cms/data/content-sync.json merkt sich je Datei den Stand des letzten Abgleichs; geänderte oder neue Dateien werden beim Veröffentlichen/Neuaufbau
   (elvado_publish, cms/rebuild.php im Deploy) und über api.php?action=content_pull in die Website übernommen. Beim allerersten Lauf wird nur der Ist-Stand gemerkt (nichts wird überschrieben). */
function elvado_content_manifest_file(): string { return defined('ELVADO_CONTENT_MANIFEST')?(string)ELVADO_CONTENT_MANIFEST:(defined('ELVADO_CONTENT_DIR')?dirname((string)ELVADO_CONTENT_DIR).'/content-sync.json':__DIR__.'/../data/content-sync.json'); }   // Tests (ELVADO_CONTENT_DIR) schreiben nie in die echten Daten
function elvado_content_manifest(): array { $f=elvado_content_manifest_file();$j=is_file($f)?json_decode((string)file_get_contents($f),true):null;return is_array($j)?$j+['pages'=>[],'posts'=>[],'config'=>[]]:['pages'=>[],'posts'=>[],'config'=>[],'fresh'=>true]; }
function elvado_content_manifest_save(array $m): void { unset($m['fresh']);$d=dirname(elvado_content_manifest_file());if(!is_dir($d))return;elvado_write_atomic(elvado_content_manifest_file(),json_encode($m,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n"); }
function elvado_content_hash(string $txt): string { return sha1(str_replace(["\r\n","\r"],"\n",$txt)); }
function elvado_content_posts_root(): string { return defined('ELVADO_CONTENT_POSTS_DIR')?(string)ELVADO_CONTENT_POSTS_DIR:dirname(elvado_content_root()).'/posts'; }
/** Einfaches Markdown → HTML (Überschriften, Absätze, Listen, Zitate, **fett**, *kursiv*, `Code`, Links, Bilder); fertiges HTML wird bereinigt übernommen. */
function elvado_content_md_to_html(string $md): string {
    $md=str_replace(["\r\n","\r"],"\n",trim($md));if($md==='')return '';
    if(preg_match('/<(p|div|h[1-6]|ul|ol|figure|img|a|table|blockquote)\b/i',$md))return function_exists('elvado_safe_html')?elvado_safe_html($md):strip_tags($md,'<p><a><strong><em><ul><ol><li><h2><h3><h4><img><blockquote><br>');
    $inl=function(string $t):string{ $t=htmlspecialchars($t,ENT_QUOTES,'UTF-8');
        $t=preg_replace_callback('/!\[([^\]]*)\]\(((?:https?:\/\/|\/)[^\s)]+)\)/',fn($m)=>'<img src="'.$m[2].'" alt="'.$m[1].'">',$t);
        $t=preg_replace_callback('/\[([^\]]+)\]\(((?:https?:\/\/|\/|#|mailto:)[^\s)]*)\)/',fn($m)=>'<a href="'.$m[2].'">'.$m[1].'</a>',$t);
        $t=preg_replace('/\*\*(.+?)\*\*/s','<strong>$1</strong>',$t);$t=preg_replace('/(?<!\*)\*(?!\s)(.+?)(?<!\s)\*(?!\*)/s','<em>$1</em>',$t);return preg_replace('/`([^`]+)`/','<code>$1</code>',$t); };
    $out='';$par=[];$list=[];$fl=function() use (&$out,&$par,&$list,$inl){ if($par){$out.='<p>'.$inl(implode("\n",$par)).'</p>';$par=[];} if($list){$out.='<ul>'.implode('',array_map(fn($i)=>'<li>'.$inl($i).'</li>',$list)).'</ul>';$list=[];} };
    foreach(explode("\n",$md) as $ln){
        if(preg_match('/^(#{1,4})\s+(.+)$/',$ln,$m)){$fl();$lv=max(2,min(4,strlen($m[1])+(strlen($m[1])===1?1:0)));$out.="<h$lv>".$inl($m[2])."</h$lv>";continue;}
        if(preg_match('/^[-*]\s+(.+)$/',$ln,$m)){if($par){$out.='<p>'.$inl(implode("\n",$par)).'</p>';$par=[];}$list[]=$m[1];continue;}
        if(preg_match('/^>\s?(.*)$/',$ln,$m)){$fl();$out.='<blockquote>'.$inl($m[1]).'</blockquote>';continue;}
        if(trim($ln)===''){$fl();continue;}
        if($list){$out.='<ul>'.implode('',array_map(fn($i)=>'<li>'.$inl($i).'</li>',$list)).'</ul>';$list=[];}
        $par[]=$ln;
    }
    $fl();return $out;
}
/** Beiträge aus cms/content/posts/<slug>/post.md in die Beitragsliste übernehmen. Front Matter: title, slug, status (published|draft, Vorgabe draft), category, tags, excerpt, published_at. @return array{news:array,imported:list<string>} */
function elvado_content_pull_posts(array $news,array &$man,bool $baseline): array {
    $imported=[];
    foreach(glob(elvado_content_posts_root().'/*/post.md')?:[] as $file){
        $folder=basename(dirname($file));$raw=(string)file_get_contents($file);$h=elvado_content_hash($raw);
        if(($man['posts'][$folder]??'')===$h)continue;
        $slug=elvado_slug($folder);$idx=null;foreach($news as $i=>$a)if(is_array($a)&&($a['slug']??'')===$slug){$idx=$i;break;}
        if($baseline&&$idx!==null){$man['posts'][$folder]=$h;continue;}
        $pr=elvado_content_parse_frontmatter($raw);$m=$pr['meta'];$now=date('Y-m-d H:i:s');
        $title=trim((string)($m['title']??$slug));if($title==='')$title=$slug;
        $st=in_array(($m['status']??'draft'),['published','draft'],true)?(string)($m['status']??'draft'):'draft';
        $row=$idx!==null?$news[$idx]:['id'=>(int)max(0,...array_map(fn($a)=>(int)($a['id']??0),array_filter($news,'is_array'))?:[0])+1,'slug'=>$slug,'created_at'=>$now,'author'=>'Dateien (Git)'];
        $row['title']=mb_substr($title,0,200);$row['status']=$st;$row['category']=mb_substr(trim((string)($m['category']??$row['category']??'News')),0,80);
        $row['tags']=mb_substr(trim(is_array($m['tags']??null)?implode(', ',$m['tags']):(string)($m['tags']??$row['tags']??'')),0,300);$row['excerpt']=mb_substr(trim((string)($m['excerpt']??$row['excerpt']??'')),0,500);
        $row['body_html']=elvado_content_md_to_html((string)$pr['body']);
        $pa=trim((string)($m['published_at']??''));$row['published_at']=$st==='published'?($pa!==''?(date('Y-m-d H:i:s',strtotime($pa)?:time())):($row['published_at']??$now)):($row['published_at']??'');
        $row['updated_at']=$now;unset($row['deleted_at']);
        if($idx!==null)$news[$idx]=$row;else $news[]=$row;
        $man['posts'][$folder]=$h;$imported[]='post:'.$slug;
    }
    return ['news'=>array_values($news),'imported'=>$imported];
}
/** Alles aus den Dateien übernehmen, was sich seit dem letzten Abgleich geändert hat. @return array{site:array,imported:list<string>,baseline:bool} */
function elvado_content_pull(array $site,?string $newsFile=null): array {
    if(function_exists('elvado_site_current')&&elvado_site_current()!=='')return ['site'=>$site,'imported'=>[],'baseline'=>false];   // weitere Websites: eigene Dateien noch nicht angebunden
    $man=elvado_content_manifest();$baseline=!empty($man['fresh']);$imported=[];$pages=glob(elvado_content_root().'/*/page.md')?:[];
    foreach($pages as $file){
        $folder=basename(dirname($file));$h=elvado_content_hash((string)file_get_contents($file));if(($man['pages'][$folder]??'')===$h)continue;
        $exists=false;foreach((array)($site['pages']??[]) as $p)if(($p['type']??'')==='custom'&&elvado_slug((string)($p['slug']??$p['title']??''))===elvado_slug($folder)){$exists=true;break;}
        if($baseline&&$exists){$man['pages'][$folder]=$h;continue;}
        $x=elvado_content_import_to_site($site,$folder);$site=$x['site'];$man['pages'][$folder]=$h;foreach($x['imported'] as $i)$imported[]='page:'.$i['slug'];
    }
    $newsFile??=__DIR__.'/../data/news.json';
    if(glob(elvado_content_posts_root().'/*/post.md')){
        $news=is_file($newsFile)?json_decode((string)file_get_contents($newsFile),true):[];$news=is_array($news)?$news:[];
        $r=elvado_content_pull_posts($news,$man,$baseline);
        if($r['imported']){elvado_write_atomic($newsFile,json_encode($r['news'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");$imported=array_merge($imported,$r['imported']);}
    }
    $cf=elvado_gitconfig_pull($site,$man,$baseline);$site=$cf['site'];$imported=array_merge($imported,$cf['applied']);
    elvado_content_manifest_save($man);
    return ['site'=>$site,'imported'=>$imported,'baseline'=>$baseline,'errors'=>$cf['errors']];
}

/* ───────── Konfiguration über Git: Menüs, Widgets, Theme, Plugins ─────────
   cms/content/config/menus.json, widgets.json, widget_areas.json, theme.json, plugins.json. Dieselbe Regel wie bei Seiten und Beiträgen: geänderte Dateien werden übernommen,
   der Spiegel Website → Datei (menus, widgets, widget_areas, theme) überschreibt keine ungeprüfte Änderung von außen.
   theme.json: {"active":"<theme-id>","variant":"default","settings":{…}} (Portal-Theme mit cms/themes/<id>/theme.json) oder {"wordpress":"<theme-slug>"} (WordPress-Theme, Ordner cms/themes/<slug>/ mit style.css; das Theme selbst liegt im Repository).
   plugins.json: {"enable":["<plugin-id>",…],"disable":["<plugin-id>",…]} (offizielle ElvadoPress-Plugins; Abhängigkeiten werden mitinstalliert). */
const ELVADO_GITCONFIG_SECTIONS=['menus','widgets','widget_areas','theme'];
function elvado_gitconfig_root(): string { return defined('ELVADO_CONTENT_CONFIG_DIR')?(string)ELVADO_CONTENT_CONFIG_DIR:dirname(elvado_content_root()).'/config'; }
function elvado_gitconfig_value(array $site,string $sec): mixed {
    if($sec==='theme'){$t=is_array($site['theme']??null)?$site['theme']:[];return ['active'=>(string)($t['active']??''),'variant'=>(string)($t['variant']??'default'),'settings'=>is_array($t['settings']??null)?$t['settings']:new stdClass];}
    return $site[$sec]??($sec==='menus'?['top'=>[],'bottom'=>[]]:[]);
}
function elvado_gitconfig_text(mixed $v): string { return json_encode($v,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n"; }
/** Website → Dateien: nur schreiben, wenn die Datei nicht von außen geändert wurde. */
function elvado_gitconfig_export(array $site,array $man): array {
    $root=elvado_gitconfig_root();
    foreach(ELVADO_GITCONFIG_SECTIONS as $sec){
        $file=$root.'/'.$sec.'.json';$txt=elvado_gitconfig_text(elvado_gitconfig_value($site,$sec));
        if($sec==='theme'&&(string)(elvado_gitconfig_value($site,'theme')['active']??'')==='')continue;   // WordPress-Theme-Betrieb: kein Portal-Theme gesetzt
        if(isset($man['config'][$sec])&&is_file($file)&&elvado_content_hash((string)file_get_contents($file))!==$man['config'][$sec])continue;
        if(is_file($file)&&(string)file_get_contents($file)===$txt){$man['config'][$sec]=elvado_content_hash($txt);continue;}
        if(!is_dir($root)&&!@mkdir($root,0755,true))continue;
        elvado_write_atomic($file,$txt);$man['config'][$sec]=elvado_content_hash($txt);
    }
    return $man;
}
/** Dateien → Website. @return array{site:array,applied:list<string>,errors:list<string>} */
function elvado_gitconfig_pull(array $site,array &$man,bool $baseline): array {
    $root=elvado_gitconfig_root();$applied=[];$errors=[];
    foreach(array_merge(ELVADO_GITCONFIG_SECTIONS,['plugins']) as $sec){
        $file=$root.'/'.$sec.'.json';if(!is_file($file))continue;$raw=(string)file_get_contents($file);$h=elvado_content_hash($raw);
        if(($man['config'][$sec]??'')===$h)continue;
        if($baseline){$man['config'][$sec]=$h;continue;}
        $d=json_decode($raw,true);if(!is_array($d)){$errors[]=$sec.'.json: kein gültiges JSON – nicht übernommen';$man['config'][$sec]=$h;continue;}
        if($sec==='plugins'){
            if(!function_exists('elvado_np')){$errors[]='plugins.json: Plugin-System nicht geladen';continue;}
            $mgr=elvado_np();
            foreach((array)($d['enable']??[]) as $id){$id=preg_replace('/[^a-z0-9_-]/','',(string)$id);if($id==='')continue;if($mgr->isActive($id))continue;$r=$mgr->activate($id,true);if(!empty($r['ok']))$applied[]='plugin:'.$id.' aktiviert';else $errors[]='Plugin '.$id.': '.($r['message']??'Aktivierung fehlgeschlagen');}
            foreach((array)($d['disable']??[]) as $id){$id=preg_replace('/[^a-z0-9_-]/','',(string)$id);if($id===''||!$mgr->isActive($id))continue;$r=$mgr->deactivate($id,false);if(!empty($r['ok']))$applied[]='plugin:'.$id.' deaktiviert';else $errors[]='Plugin '.$id.': '.($r['message']??'Deaktivierung fehlgeschlagen');}
            $man['config'][$sec]=$h;continue;
        }
        if($sec==='theme'&&is_string($d['wordpress']??null)&&$d['wordpress']!==''){   // WordPress-Theme (Ordner cms/themes/<slug>/ mit style.css) aktivieren
            $slug=preg_replace('/[^a-z0-9_-]/','',strtolower((string)$d['wordpress']));
            try{
                if(!function_exists('elvado_wpi_activate_theme')){require_once dirname(__DIR__).'/wp/load.php';require_once dirname(__DIR__).'/wp/installer.php';}
                $e=elvado_wpi_activate_theme($slug);
                if($e===null)$applied[]='wordpress-theme:'.$slug;else $errors[]='theme.json: WordPress-Theme „'.$slug.'“: '.$e;
            }catch(\Throwable $ex){$errors[]='theme.json: WordPress-Theme „'.$slug.'“ konnte nicht aktiviert werden';}
            $man['config'][$sec]=$h;continue;
        }
        if($sec==='theme'){
            $id=function_exists('elvado_theme_id')?elvado_theme_id((string)($d['active']??'')):'';$tf=(defined('ELVADO_THEMES_DIR')?(string)ELVADO_THEMES_DIR:dirname(__DIR__).'/themes').'/'.$id.'/theme.json';
            if($id===''||!is_file($tf)){$errors[]='theme.json: Theme „'.($d['active']??'').'“ ist nicht installiert (Ordner cms/themes/<id>/ fehlt) – nicht übernommen';$man['config'][$sec]=$h;continue;}
            $m=json_decode((string)file_get_contents($tf),true);$m=is_array($m)?$m:[];
            if((string)($site['theme']['active']??'')!==$id){
                $site=elvado_theme_remember_mods($site);$prev=$site['theme']['mods'][$id]??null;
                if(is_array($prev['widget_areas']??null))$site['widget_areas']=elvado_clean_section('widget_areas',$prev['widget_areas']);elseif(is_array($m['widget_areas']??null))$site['widget_areas']=elvado_clean_section('widget_areas',$m['widget_areas']);
            }
            $site['theme']=elvado_clean_section('theme',['active'=>$id,'variant'=>$d['variant']??'default','settings'=>is_array($d['settings']??null)?$d['settings']:[],'layout'=>elvado_theme_layout_clean($m['layout']??[]),'mods'=>$site['theme']['mods']??[]]);
            $site=elvado_theme_remember_mods($site);$applied[]='theme:'.$id;$man['config'][$sec]=$h;continue;
        }
        $clean=elvado_clean_section($sec,$d);if($clean===null){$errors[]=$sec.'.json: ungültig – nicht übernommen';$man['config'][$sec]=$h;continue;}
        $site[$sec]=$clean;$applied[]='config:'.$sec;$man['config'][$sec]=$h;
    }
    return ['site'=>$site,'applied'=>$applied,'errors'=>$errors];
}

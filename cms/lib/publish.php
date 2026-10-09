<?php
declare(strict_types=1);
require_once __DIR__.'/pack.php';
require_once __DIR__.'/brand.php';
require_once __DIR__.'/product.php';
require_once __DIR__.'/system.php';
require_once __DIR__.'/htmlsafe.php';

// Kernlogik zum Speichern und Veröffentlichen der CMS-Konfiguration:
// site.json schreiben, index.html-Snapshot, eigene Seiten, RSS.
// Wird sowohl von api.php (HTTP) als auch von rebuild.php (CLI) genutzt,
// damit beide garantiert dasselbe Ergebnis erzeugen.

const RRW_CMS_MARKER = '<!-- RRW-CMS-GENERATED -->';

function rrw_ensure_dirs(): void {
    $cms=dirname(__DIR__);   // cms/ (nicht cms/lib/): hier liegen Daten, Veröffentlichtes und Medien
    foreach([$cms.'/data',$cms.'/generated',$cms.'/media',$cms.'/media/content',$cms.'/media/branding',$cms.'/media/news',$cms.'/media/library',$cms.'/themes'] as $d){
        if(!is_dir($d))@mkdir($d,0755,true);
    }
    // Einstellungen (inkl. API-Schlüssel), Beiträge, Protokolle und Backups gehören nie in den öffentlichen Abruf
    rrw_protect_dir(dirname(__DIR__).'/data');rrw_protect_dir(dirname(__DIR__).'/backups');
}
require_once __DIR__.'/tools.php';
function rrw_read_json(string $file, array $fallback=[]): array {
    if(!is_file($file))return $fallback;
    $j=json_decode((string)file_get_contents($file),true); return is_array($j)?$j:$fallback;
}
function rrw_write_atomic(string $file, string $content): void {
    $dir=dirname($file); if(!is_dir($dir)&&!@mkdir($dir,0755,true))throw new RuntimeException('Ordner nicht beschreibbar: '.$dir);
    $tmp=$file.'.tmp.'.bin2hex(random_bytes(4));
    if(file_put_contents($tmp,$content,LOCK_EX)===false)throw new RuntimeException('Datei konnte nicht geschrieben werden');
    @chmod($tmp,0644);
    if(!@rename($tmp,$file)){@unlink($tmp);throw new RuntimeException('Datei konnte nicht ersetzt werden');}
}
/**
 * Zeitpunkt eines Beitrags als Unix-Zeit. Tolerant gegenüber den Formaten, die vorkommen ("2026-10-06 10:00:00", "2026-10-06T10:00" aus dem
 * Datumsfeld des Editors, ISO 8601 mit Zeitzone); null bei leerem oder unlesbarem Wert. Nie Zeichenketten vergleichen: "T" sortiert hinter " ",
 * ein Beitrag mit "…T10:00" wäre sonst bis zum nächsten Tag unsichtbar.
 */
function rrw_news_ts($v): ?int {
    $s=trim((string)$v);if($s==='')return null;
    $t=strtotime($s);return $t===false?null:$t;
}
/** Einheitliches Speicherformat "Y-m-d H:i:s" (Serverzeit); leer oder unlesbar → $fallback. */
function rrw_news_date($v,string $fallback=''): string { $t=rrw_news_ts($v);return $t===null?$fallback:date('Y-m-d H:i:s',$t); }
/** Sortierzeit eines Beitrags (Veröffentlichung, sonst Anlage). */
function rrw_news_sort_ts(array $a): int { return rrw_news_ts($a['published_at']??'')??rrw_news_ts($a['created_at']??'')??0; }
/** Vergleich "neueste zuerst" für usort. */
function rrw_news_cmp_desc(array $a,array $b): int { return rrw_news_sort_ts($b)<=>rrw_news_sort_ts($a); }
function rrw_news_is_live(array $a): bool {
    if (($a['status']??'draft')!=='published' || !empty($a['deleted_at'])) return false;
    $publishedAt=trim((string)($a['published_at']??''));
    if($publishedAt==='')return true;
    $timestamp=rrw_news_ts($publishedAt);
    if($timestamp===null)return false;
    return $timestamp<=time();
}
// Rechteprüfung für Mehrfach-Redakteure: Admins dürfen alles, die Rolle 'autor'
// nur eigene Beiträge bearbeiten/löschen/wiederherstellen. Beiträge ohne gespeicherten
// Besitzer (author_user leer, z.B. Altbestand vor Einführung der Rollen) bleiben für
// alle eingeloggten Redakteure bearbeitbar, damit bestehende Inhalte nicht ausgesperrt werden.
function rrw_news_can_edit(array $article, array $user): bool {
    if(($user['role']??'admin')==='admin')return true;
    $owner=(string)($article['author_user']??'');
    if($owner==='')return true;
    return strcasecmp($owner,(string)($user['user']??''))===0;
}
// Kommentare werden nur eine Ebene tief angezeigt (wie bei WordPress): Antwortet jemand auf
// eine Antwort, wird sie automatisch unter deren ursprünglichem Top-Level-Kommentar einsortiert,
// statt in der Anzeige zu verschwinden. Gibt null zurück, wenn der Zielkommentar nicht existiert.
function rrw_comment_top_parent(array $comments, int $parentId, int $articleId): ?int {
    if($parentId<=0)return 0;
    foreach($comments as $c){
        if((int)($c['id']??0)===$parentId&&(int)($c['article_id']??0)===$articleId){
            $grandParent=(int)($c['parent_id']??0);
            return $grandParent>0?$grandParent:$parentId;
        }
    }
    return null;
}
// Einfacher Aufruf-Zähler pro Beitrag (kein Referrer/UA-Tracking, keine Cookies nötig): die
// Website entprellt clientseitig per sessionStorage, sodass ein Refresh/Zurück-Navigieren
// innerhalb derselben Browser-Sitzung denselben Beitrag nicht mehrfach zählt. Read-modify-write
// ohne Locking ist für die zu erwartende Last einer kleinen Radioseite ausreichend; im
// Extremfall (zwei zeitgleiche Requests) kann höchstens ein Zähler-Increment verloren gehen.
function rrw_news_track_view(string $viewsFile, int $articleId): void {
    $views=rrw_read_json($viewsFile,[]);
    $key=(string)$articleId;
    $views[$key]=(int)($views[$key]??0)+1;
    rrw_write_atomic($viewsFile,json_encode($views,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
}
function rrw_news_view_count(array $views, int $articleId): int {
    return (int)($views[(string)$articleId]??0);
}
// Redakteurs-Aktivitätslog: eine einfache, chronologische Historie wichtiger Änderungen
// (Beiträge, Kommentare, Redakteurverwaltung). Kein Ersatz für echte Revisionen – dafür gibt
// es bereits rrw_news_save_revision() –, sondern ein Überblick "wer hat wann was gemacht".
function rrw_log_activity(string $logFile, ?array $user, string $action, string $summary): void {
    $all=rrw_read_json($logFile,[]);
    $all[]=[
        'id'=>bin2hex(random_bytes(6)),
        'created_at'=>date('Y-m-d H:i:s'),
        'user'=>(string)($user['display_name']??$user['user']??'System'),
        'role'=>(string)($user['role']??''),
        'action'=>$action,
        'summary'=>$summary,
    ];
    if(count($all)>500){
        usort($all,fn($a,$b)=>strcmp((string)($a['created_at']??''),(string)($b['created_at']??'')));
        $all=array_slice($all,-500);
    }
    rrw_write_atomic($logFile,json_encode($all,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
}
function rrw_add_notification(string $notificationsFile, array $data): void {
    $all=rrw_read_json($notificationsFile,[]);
    $all[]=array_merge(['id'=>bin2hex(random_bytes(6)),'created_at'=>date('Y-m-d H:i:s'),'read'=>false],$data);
    if(count($all)>200){
        usort($all,fn($a,$b)=>strcmp((string)($a['created_at']??''),(string)($b['created_at']??'')));
        $all=array_slice($all,-200);
    }
    rrw_write_atomic($notificationsFile,json_encode($all,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
}
function rrw_news_save_revision(string $revisionsFile, int $articleId, array $articleSnapshot): void {
    $all=rrw_read_json($revisionsFile,[]);
    $all[]=['revision_id'=>bin2hex(random_bytes(6)),'article_id'=>$articleId,'saved_at'=>date('Y-m-d H:i:s'),'article'=>$articleSnapshot];
    $mine=array_values(array_filter($all,fn($r)=>(int)($r['article_id']??0)===$articleId));
    if(count($mine)>15){
        usort($mine,fn($a,$b)=>strcmp((string)($a['saved_at']??''),(string)($b['saved_at']??'')));
        $drop=array_slice($mine,0,count($mine)-15);
        $dropIds=array_column($drop,'revision_id');
        $all=array_values(array_filter($all,fn($r)=>(int)($r['article_id']??0)!==$articleId||!in_array($r['revision_id']??'',$dropIds,true)));
    }
    rrw_write_atomic($revisionsFile,json_encode($all,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
}
function rrw_slug(string $s): string {
    $s=mb_strtolower(trim($s),'UTF-8');$s=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s)?:$s;
    $s=preg_replace('/[^a-z0-9]+/','-',$s);$s=trim((string)$s,'-');return substr($s!==''?$s:'seite',0,70);
}
/** „a, b,, A“ → ['a','b','A'] (getrimmt, ohne Leere; Groß-/Kleinschreibung bleibt erhalten). */
/** Adresse (Slug) eines Beitrags eindeutig halten: bei Doppelung „-2“, „-3“ … anhängen (der Beitrag selbst zählt nicht mit). */
function rrw_news_unique_slug(array $news,int $id,string $slug): string {
    $base=$slug;for($sn=2;$sn<200;$sn++){ $taken=false;foreach($news as $o)if((int)($o['id']??0)!==$id&&($o['slug']??'')===$slug){$taken=true;break;} if(!$taken)break;$slug=$base.'-'.$sn; }
    return $slug;
}
/** SEO- und Kommentarfelder eines Beitrags aus der Eingabe: Kommentare default|open|closed, noindex (bool), kanonische Adresse nur als gültige http(s)-Adresse. */
function rrw_news_seo_fields(array $b): array {
    $canon=trim((string)($b['canonical_url']??''));
    return ['seo_title'=>mb_substr(trim((string)($b['seo_title']??'')),0,70),'seo_description'=>mb_substr(trim((string)($b['seo_description']??'')),0,200),
        'comments'=>in_array(($b['comments']??'default'),['open','closed'],true)?$b['comments']:'default','noindex'=>!empty($b['noindex']),
        'canonical_url'=>(filter_var($canon,FILTER_VALIDATE_URL)&&preg_match('~^https?://~i',$canon))?mb_substr($canon,0,1200):''];
}
function rrw_news_tag_list(string $tags): array {
    $out=[];foreach(explode(',',$tags) as $t){$t=trim($t);if($t!=='')$out[]=$t;}return $out;
}
function rrw_safe_html(string $html): string {
    // DOM-basierte Positivliste (cms/lib/htmlsafe.php): Block-Editor-Inhalt (Überschriften, Tabellen, Embeds, Block-Kommentare) bleibt erhalten, alles Aktive fliegt raus
    return rrw_html_sanitize($html);
}
function rrw_clean_blocks($blocks): array {
    $out=[];if(!is_array($blocks))return $out;
    foreach(array_slice($blocks,0,120) as $b){
        if(!is_array($b))continue;$type=strtolower(trim((string)($b['type']??'text')));
        if(!in_array($type,['heading','text','html','image','button','widget','spacer','divider','quote'],true))continue;
        $id=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($b['id']??''));if($id==='')$id='blk_'.bin2hex(random_bytes(4));
        $x=['id'=>$id,'type'=>$type,'enabled'=>!array_key_exists('enabled',$b)||!empty($b['enabled'])];
        if($type==='heading'){$x['text']=mb_substr(trim((string)($b['text']??'')),0,500);$x['level']=in_array((int)($b['level']??2),[2,3,4],true)?(int)$b['level']:2;}
        elseif(in_array($type,['text','quote'],true))$x['text']=mb_substr((string)($b['text']??''),0,15000);
        elseif($type==='html')$x['html']=rrw_safe_html((string)($b['html']??''));
        elseif($type==='image'){$x['url']=mb_substr(trim((string)($b['url']??'')),0,1200);$x['alt']=mb_substr((string)($b['alt']??''),0,250);$x['caption']=mb_substr((string)($b['caption']??''),0,600);}
        elseif($type==='button'){$x['label']=mb_substr((string)($b['label']??'Mehr erfahren'),0,120);$x['url']=mb_substr(trim((string)($b['url']??'#')),0,1200);}
        elseif($type==='widget')$x['widget_id']=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($b['widget_id']??''));
        elseif($type==='spacer')$x['size']=max(8,min(160,(int)($b['size']??32)));
        $out[]=$x;
    } return $out;
}

// ---------------------------------------------------------------------------------------------
// Widget-System wie bei WordPress: Es gibt WIDGET-TYPEN (Code, unten) und WIDGET-INSTANZEN
// (Daten). Eine Instanz liegt in genau einem Widget-Bereich (oder bei "Inaktive Widgets"),
// hat einen eigenen Titel, eigene Einstellungen nach dem Schema ihres Typs und eine eigene
// Geräte-Sichtbarkeit. Alte Daten (Bereiche mit reinen Widget-IDs aus site.widgets, Theme-
// Manifeste mit "w-news" usw.) werden beim Bereinigen automatisch in Instanzen überführt.
// ---------------------------------------------------------------------------------------------
function rrw_widget_types(): array {
    static $t=null; if($t!==null)return $t;
    $int=fn(int $min,int $max,int $def)=>['type'=>'int','min'=>$min,'max'=>$max,'default'=>$def];
    $bool=fn(bool $def)=>['type'=>'bool','default'=>$def];
    $text=fn(int $max=200,string $def='')=>['type'=>'text','max'=>$max,'default'=>$def];
    $sel=fn(array $opts,string $def)=>['type'=>'select','options'=>$opts,'default'=>$def];
    $t=[
        'recent-posts'=>['name'=>'Letzte Beiträge','category'=>'Inhalte','settings'=>['count'=>$int(1,10,5),'show_date'=>$bool(true),'show_thumb'=>$bool(true),'category'=>$text(80)]],
        'categories'=>['name'=>'Kategorien','category'=>'Inhalte','settings'=>['show_counts'=>$bool(true)]],
        'tags'=>['name'=>'Schlagwörter','category'=>'Inhalte','settings'=>['max'=>$int(5,40,20)]],
        'archives'=>['name'=>'Archiv','category'=>'Inhalte','settings'=>['months'=>$int(3,24,12),'show_counts'=>$bool(true)]],
        'calendar'=>['name'=>'Kalender','category'=>'Inhalte','settings'=>[]],
        'search'=>['name'=>'Suche','category'=>'Inhalte','settings'=>['placeholder'=>$text(80,'News durchsuchen …')]],
        'recent-comments'=>['name'=>'Letzte Kommentare','category'=>'Inhalte','settings'=>['count'=>$int(1,10,5)]],
        'text'=>['name'=>'Text','category'=>'Eigene','settings'=>['text'=>['type'=>'textarea','max'=>5000,'default'=>'']]],
        'html'=>['name'=>'Eigenes HTML','category'=>'Eigene','settings'=>['html'=>['type'=>'html','max'=>50000,'default'=>'']]],
        'image'=>['name'=>'Bild','category'=>'Medien','settings'=>['url'=>['type'=>'url','default'=>''],'alt'=>$text(160),'link'=>['type'=>'url','default'=>''],'caption'=>$text(200)]],
        'gallery'=>['name'=>'Galerie','category'=>'Medien','settings'=>['urls'=>['type'=>'textarea','max'=>6000,'default'=>''],'columns'=>$int(2,4,3)]],
        'audio'=>['name'=>'Audio','category'=>'Medien','settings'=>['url'=>['type'=>'url','default'=>''],'caption'=>$text(200)]],
        'embed'=>['name'=>'Einbettung (iframe)','category'=>'Medien','settings'=>['url'=>['type'=>'url','default'=>''],'height'=>$int(120,1400,520)]],
        'button'=>['name'=>'Button','category'=>'Eigene','settings'=>['label'=>$text(80,'Mehr erfahren'),'url'=>$text(1200,'#'),'style'=>$sel(['primary','ghost'],'primary'),'icon'=>$text(40)]],
        'menu'=>['name'=>'Navigationsmenü','category'=>'Eigene','settings'=>['menu'=>$sel(['top','bottom'],'top'),'style'=>$sel(['list','chips'],'list')]],
        // Allgemeine Widgets, unabhängig vom Radio (für das eigenständige CMS)
        'community-account'=>['name'=>'Mitgliederbereich','category'=>'Community','settings'=>[]],
        'community-forum'=>['name'=>'Forum','category'=>'Community','settings'=>[]],
        'community-social'=>['name'=>'Soziales Netzwerk','category'=>'Community','settings'=>[]],
        'cms-poll'=>['name'=>'Umfrage (CMS)','category'=>'Interaktion','settings'=>['poll_id'=>$text(8)]],
        'faq'=>['name'=>'FAQ / Akkordeon','category'=>'Eigene','settings'=>['items'=>['type'=>'textarea','max'=>8000,'default'=>''],'open_first'=>$bool(false)]],
        'countdown'=>['name'=>'Countdown','category'=>'Eigene','settings'=>['target'=>$text(20),'label'=>$text(120),'done'=>$text(120,'Es ist so weit!')]],
        'video'=>['name'=>'Video','category'=>'Medien','settings'=>['url'=>['type'=>'url','default'=>''],'caption'=>$text(200)]],
        'map'=>['name'=>'Karte (OpenStreetMap)','category'=>'Medien','settings'=>['lat'=>$text(20),'lon'=>$text(20),'zoom'=>$int(3,19,14),'height'=>$int(150,800,320),'label'=>$text(120)]],
        'social-links'=>['name'=>'Social-Links','category'=>'Social','settings'=>['items'=>['type'=>'textarea','max'=>3000,'default'=>''],'style'=>$sel(['list','icons'],'icons')]],
        'contact-form'=>['name'=>'Kontaktformular','category'=>'Interaktion','settings'=>['intro'=>['type'=>'textarea','max'=>500,'default'=>''],'notify'=>$text(120),'button'=>$text(40,'Senden'),'success'=>$text(200,'Danke, deine Nachricht ist angekommen.'),'consent'=>$text(300,'Ich stimme der Verarbeitung meiner Angaben zur Beantwortung meiner Anfrage zu (siehe Datenschutzerklärung).'),'subject'=>$bool(true)]],
        'newsletter'=>['name'=>'Newsletter-Anmeldung','category'=>'Interaktion','settings'=>['intro'=>['type'=>'textarea','max'=>500,'default'=>''],'notify'=>$text(120),'button'=>$text(40,'Anmelden'),'success'=>$text(200,'Danke für deine Anmeldung!'),'consent'=>$text(300,'Ich möchte den Newsletter erhalten und stimme der Speicherung meiner E-Mail-Adresse zu (siehe Datenschutzerklärung).')]],
    ];
    return $t;
}
// Alte Widget-Definitionen (site.widgets, Typ builtin) -> Widget-Typ + Voreinstellungen
function rrw_widget_legacy_type(string $builtin): array {
    $map=['news-latest'=>['recent-posts',[]]];
    return $map[$builtin]??['html',[]];
}
function rrw_widget_legacy_defs(): array {
    return ['w-news'=>['id'=>'w-news','type'=>'builtin','builtin'=>'news-latest','title'=>'Aktuelle News','name'=>'Aktuelle News']];
}
function rrw_widget_settings_clean(string $type,$settings): array {
    $schema=rrw_widget_types()[$type]['settings']??[];$settings=is_array($settings)?$settings:[];$out=[];
    foreach($schema as $k=>$f){
        $v=$settings[$k]??$f['default'];
        switch($f['type']){
            case 'int':$out[$k]=max($f['min'],min($f['max'],(int)$v));break;
            case 'bool':$out[$k]=is_string($v)?in_array(strtolower($v),['1','true','on','yes'],true):(bool)$v;break;
            case 'select':$out[$k]=in_array((string)$v,$f['options'],true)?(string)$v:$f['default'];break;
            case 'url':$v=trim((string)$v);$out[$k]=preg_match('~^(https?://|/|#|data:image/)~i',$v)||$v===''?mb_substr($v,0,1500):'';break;
            case 'html':$out[$k]=rrw_safe_html((string)$v);break;
            case 'textarea':$out[$k]=mb_substr((string)$v,0,$f['max']);break;
            default:$out[$k]=mb_substr(trim((string)$v),0,$f['max']??200);
        }
    }
    return $out;
}
// Eine Widget-Instanz bereinigen. $w darf eine alte Widget-ID (String) sein - dann wird aus der
// Definition in site.widgets bzw. aus den eingebauten Standard-Widgets eine Instanz abgeleitet.
function rrw_widget_instance_clean($w,string $areaId=''): ?array {
    $site=$GLOBALS['RRW_SITE']??[];
    if(is_string($w)){
        $legacyId=preg_replace('/[^a-zA-Z0-9_-]/','',$w);if($legacyId==='')return null;
        $def=null;foreach((array)($site['widgets']??[]) as $d)if((string)($d['id']??'')===$legacyId){$def=$d;break;}
        if($def===null)$def=rrw_widget_legacy_defs()[$legacyId]??null;
        if($def===null)return null;
        if(($def['enabled']??true)===false)return null;
        $dtype=(string)($def['type']??'builtin');
        if($dtype==='html'){$type='html';$settings=['html'=>(string)($def['html']??'')];}
        elseif($dtype==='iframe'){$type='embed';$settings=['url'=>(string)($def['url']??'')];}
        else{[$type,$settings]=rrw_widget_legacy_type((string)($def['builtin']??''));}
        $w=['id'=>'wi_'.$legacyId.($areaId!==''?'_'.substr(md5($areaId),0,4):''),'type'=>$type,'title'=>(string)($def['title']??$def['name']??''),'settings'=>$settings];
    }
    if(!is_array($w))return null;
    $type=(string)($w['type']??'');if(!isset(rrw_widget_types()[$type]))return null;
    $id=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($w['id']??''));if($id==='')$id='wi_'.bin2hex(random_bytes(4));
    $vis=is_array($w['visibility']??null)?$w['visibility']:[];
    return ['id'=>$id,'type'=>$type,'title'=>mb_substr(trim((string)($w['title']??'')),0,160),'settings'=>rrw_widget_settings_clean($type,$w['settings']??[]),'visibility'=>['mode'=>(($vis['mode']??'hide')==='show')?'show':'hide','desktop'=>!empty($vis['desktop']),'tablet'=>!empty($vis['tablet']),'mobile'=>!empty($vis['mobile'])]];
}
function rrw_clean_section(string $section,$value){
    if($section==='portal'){
        $keys=['site_name'=>80,'news_title'=>140,'news_intro'=>600,'hero_eyebrow'=>80,'hero_title'=>220,'hero_text'=>1200,'footer_text'=>220,'notice_text'=>800];
        $o=[];foreach($keys as $k=>$n)$o[$k]=mb_substr(trim((string)($value[$k]??'')),0,$n);$o['notice_enabled']=!empty($value['notice_enabled']);
        if(isset($value['tagline']))$o['tagline']=mb_substr(trim((string)$value['tagline']),0,200);   // optional, von der WordPress-Schicht (blogdescription) geschrieben
        return $o;
    }
    if($section==='apps')return function_exists('rrw_apps_clean')?rrw_apps_clean($value):['android_enabled'=>!empty($value['android_enabled']),'windows_enabled'=>!empty($value['windows_enabled'])];
    if($section==='assistant')return function_exists('rrw_assistant_clean')?rrw_assistant_clean($value):(array)$value;
    if($section==='alexa')return function_exists('rrw_alexa_clean')?rrw_alexa_clean($value):(array)$value;
    if($section==='comments')return ['enabled'=>!empty($value['enabled']),'require_approval'=>!array_key_exists('require_approval',(array)$value)||!empty($value['require_approval'])];
    if($section==='news_categories'){
        $out=[];foreach((array)$value as $c){$c=mb_substr(trim((string)$c),0,40);if($c!=='')$out[]=$c;}
        $out=array_values(array_unique($out));
        return array_slice($out,0,40);
    }
    if($section==='header_builder'){
        $v=is_array($value)?$value:[];$out=['enabled'=>!empty($v['enabled']),'items'=>[]];
        $types=['brand','navigation','search','live','social','assistant','link','whatsapp','phone','email'];
        foreach(array_slice((array)($v['items']??[]),0,40) as $n=>$it){
            if(!is_array($it))continue;
            $type=in_array((string)($it['type']??''),$types,true)?(string)$it['type']:'link';
            $id=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($it['id']??''));if($id==='')$id='header_'.bin2hex(random_bytes(4));
            $url=mb_substr(trim((string)($it['url']??'')),0,1500);
            $out['items'][]=['id'=>$id,'type'=>$type,'label'=>mb_substr(trim((string)($it['label']??'')),0,100),'url'=>$url,'icon'=>preg_replace('/[^a-zA-Z0-9 _-]/','',(string)($it['icon']??'')),'tooltip'=>mb_substr(trim((string)($it['tooltip']??'')),0,180),'enabled'=>!array_key_exists('enabled',$it)||!empty($it['enabled']),'desktop'=>!array_key_exists('desktop',$it)||!empty($it['desktop']),'mobile'=>!array_key_exists('mobile',$it)||!empty($it['mobile']),'order'=>max(0,min(999,(int)($it['order']??$n)))];
        }
        usort($out['items'],fn($x,$y)=>$x['order']<=>$y['order']);return $out;
    }
    if($section==='branding'){foreach(['portal_logo','portal_icon','favicon','android_inapp_logo','android_startscreen','android_app_icon','windows_logo'] as $k)$o[$k]=mb_substr(trim((string)($value[$k]??'')),0,1000);return $o??[];}
    if($section==='core_network'){foreach((array)($value['stations']??[]) as $s){$s=strtolower(trim((string)$s));if(preg_match('/^[a-z0-9][a-z0-9_-]{1,62}$/',$s))$o[]=$s;}$o=array_values(array_unique($o??[]));return ['stations'=>$o];}
    if($section==='services'){ if(!function_exists('rrw_services_clean'))require_once __DIR__.'/services.php';return rrw_services_clean($value); }
    if($section==='pages'){
        $out=[];$sys=['start','sender','senderdetail','sendeplan','voting','podcast','news','hilfe','apps','fanshop'];
        foreach(array_slice((array)$value,0,300) as $p){
            if(!is_array($p))continue;$type=($p['type']??'custom')==='system'?'system':'custom';$id=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($p['id']??''));if($id==='')$id='page_'.bin2hex(random_bytes(4));
            $slug=rrw_slug((string)($p['slug']??$p['title']??$id));$target=$type==='system'?strtolower(trim((string)($p['system_target']??$slug))):'';if($type==='system'&&!in_array($target,$sys,true)){if(preg_match('/^[a-z0-9_-]{1,40}$/',$target)!==1)continue;}   // unbekannte Systemseiten bleiben erhalten (nie stillschweigend löschen)
            $ovs=[];foreach(array_slice((array)($p['text_overrides']??[]),0,150) as $ov){if(!is_array($ov))continue;$sel=trim((string)($ov['selector']??''));if($sel===''||strlen($sel)>600||preg_match('/[{};]/',$sel))continue;$ovs[]=['selector'=>$sel,'text'=>mb_substr((string)($ov['text']??''),0,5000),'original'=>mb_substr((string)($ov['original']??''),0,5000)];}
            $out[]=['id'=>$id,'slug'=>$slug,'title'=>mb_substr(trim((string)($p['title']??$slug)),0,160),'type'=>$type,'system_target'=>$target,'enabled'=>!array_key_exists('enabled',$p)||!empty($p['enabled']),'native_enabled'=>!array_key_exists('native_enabled',$p)||!empty($p['native_enabled']),'headline'=>mb_substr(trim((string)($p['headline']??'')),0,260),'intro'=>mb_substr(trim((string)($p['intro']??'')),0,1500),'text_overrides'=>$ovs,'blocks_before'=>rrw_clean_blocks($p['blocks_before']??[]),'blocks_after'=>rrw_clean_blocks($p['blocks_after']??[]),'meta_title'=>mb_substr(trim(strip_tags((string)($p['meta_title']??''))),0,160),'meta_description'=>mb_substr(trim(strip_tags((string)($p['meta_description']??''))),0,300),'noindex'=>!empty($p['noindex']),'publish_at'=>$type==='custom'?rrw_page_publish_at($p['publish_at']??''):''];
        } return $out;
    }
    if($section==='menus'){
        $out=['top'=>[],'bottom'=>[]];
        foreach(['top','bottom'] as $menu)foreach(array_slice((array)($value[$menu]??[]),0,100) as $m){
            if(!is_array($m))continue;$id=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($m['id']??''));if($id==='')$id=$menu.'_'.bin2hex(random_bytes(4));
            $out[$menu][]=['id'=>$id,'label'=>mb_substr(trim((string)($m['label']??'Menüpunkt')),0,100),'target'=>mb_substr(trim((string)($m['target']??'')),0,1200),'icon'=>preg_replace('/[^a-zA-Z0-9_-]/','',(string)($m['icon']??'fa-circle')),'parent_id'=>preg_replace('/[^a-zA-Z0-9_-]/','',(string)($m['parent_id']??'')),'enabled'=>!array_key_exists('enabled',$m)||!empty($m['enabled'])];
        } return $out;
    }
    if($section==='widgets'){
        $out=[];$allowed=['news-latest'];
        foreach(array_slice((array)$value,0,100) as $w){if(!is_array($w))continue;$id=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($w['id']??''));if($id==='')$id='widget_'.bin2hex(random_bytes(4));$type=in_array(($w['type']??'builtin'),['builtin','html','iframe'],true)?$w['type']:'builtin';$builtin=in_array(($w['builtin']??''),$allowed,true)?$w['builtin']:'';
            $cat=mb_substr(trim((string)($w['category']??'Eigene')),0,80);
            $out[]=['id'=>$id,'name'=>mb_substr((string)($w['name']??'Widget'),0,120),'type'=>$type,'builtin'=>$builtin,'category'=>$cat,'enabled'=>!array_key_exists('enabled',$w)||!empty($w['enabled']),'title'=>mb_substr((string)($w['title']??''),0,160),'config'=>is_array($w['config']??null)?$w['config']:[],'html'=>$type==='html'?rrw_safe_html((string)($w['html']??'')):'','url'=>$type==='iframe'?mb_substr(trim((string)($w['url']??'')),0,1200):''];
        } return $out;
    }
    if($section==='widget_areas'){
        $out=[];$validKinds=['sidebar','footer','content'];$validScopes=['global','page'];$validPos=['left','right','top','bottom'];
        foreach(array_slice((array)$value,0,50) as $a){
            if(!is_array($a))continue;$id=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($a['id']??''));if($id==='')$id='area_'.bin2hex(random_bytes(4));
            $widgets=[];$seen=[];foreach(array_slice((array)($a['widgets']??[]),0,50) as $wid){$inst=rrw_widget_instance_clean($wid,$id);if($inst===null||isset($seen[$inst['id']]))continue;$seen[$inst['id']]=true;$widgets[]=$inst;}
            $out[]=['id'=>$id,'name'=>mb_substr(trim((string)($a['name']??'Widget-Bereich')),0,120),'kind'=>in_array(($a['kind']??'sidebar'),$validKinds,true)?$a['kind']:'sidebar','scope'=>in_array(($a['scope']??'global'),$validScopes,true)?$a['scope']:'global','page_id'=>preg_replace('/[^a-zA-Z0-9_-]/','',(string)($a['page_id']??'')),'position'=>in_array(($a['position']??'right'),$validPos,true)?$a['position']:'right','enabled'=>!empty($a['enabled']),'widgets'=>$widgets];
        } return $out;
    }
    if($section==='brands')return rrw_brands_clean($value);
    if($section==='widget_inactive'){
        $out=[];$seen=[];foreach(array_slice((array)$value,0,60) as $w){$inst=rrw_widget_instance_clean($w,'inactive');if($inst===null||isset($seen[$inst['id']]))continue;$seen[$inst['id']]=true;$out[]=$inst;}
        return $out;
    }
    if($section==='feed_sources'){
        $out=[];
        foreach(array_slice((array)$value,0,30) as $s){
            if(!is_array($s))continue;
            $id=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($s['id']??''));if($id==='')$id='feed_'.bin2hex(random_bytes(4));
            $url=trim((string)($s['url']??''));
            if(!filter_var($url,FILTER_VALIDATE_URL))continue;
            $scheme=strtolower((string)(parse_url($url,PHP_URL_SCHEME)??''));if(!in_array($scheme,['http','https'],true))continue;
            $out[]=[
                'id'=>$id,
                'name'=>mb_substr(trim((string)($s['name']??'Externer Feed')),0,120),
                'url'=>mb_substr($url,0,1500),
                'category'=>mb_substr(trim((string)($s['category']??'Extern')),0,80),
                'enabled'=>!array_key_exists('enabled',$s)||!empty($s['enabled']),
                'max_items'=>max(1,min(25,(int)($s['max_items']??5)))
            ];
        }
        return $out;
    }
    if($section==='rss'){
        return [
            'enabled'=>!array_key_exists('enabled',$value)||!empty($value['enabled']),
            'title'=>mb_substr(trim((string)($value['title']??trim((string)(($GLOBALS['RRW_SITE']['portal']['site_name']??'')).' – News & Magazin'))),0,180),
            'description'=>mb_substr(trim((string)($value['description']??'')),0,500),
            'max_items'=>max(5,min(100,(int)($value['max_items']??50))),
            'include_external'=>!empty($value['include_external'])
        ];
    }
    if($section==='plugins'){
        $out=[];foreach(array_slice((array)$value,0,100) as $id){$id=rrw_plugin_id((string)$id);if($id!=='')$out[]=$id;}return array_values(array_unique($out));
    }
    if($section==='theme'){
        $allowedKeys=['accent','accent2','background','surface','surface2','text','muted','radius','content_width','header_height','sidebar_width','font_scale','glass_strength','nav_style','card_style','hero_style','footer_background','button_radius','button_style','custom_css','variant'];
        $cleanSettings=function($raw)use($allowedKeys):array{$settings=[];foreach((array)$raw as $k=>$v){if(!in_array((string)$k,$allowedKeys,true))continue;$vv=trim((string)$v);if($k==='custom_css'){$vv=preg_replace('/<\/?style\b[^>]*>/i','',$vv);$vv=preg_replace('/@import\s+[^;]+;/i','',$vv);$settings[$k]=mb_substr($vv,0,12000);}else{$settings[(string)$k]=mb_substr($vv,0,120);}}return $settings;};
        $cleanVariant=fn($v)=>preg_replace('/[^a-zA-Z0-9_-]/','',(string)($v??'default'));
        $out=['active'=>rrw_theme_id((string)($value['active']??rrw_default_theme_id())),'variant'=>$cleanVariant($value['variant']??'default'),'settings'=>$cleanSettings($value['settings']??[])];
        if(is_array($value['layout']??null))$out['layout']=rrw_theme_layout_clean($value['layout']);
        // Pro-Theme-Anpassungen (wie WordPress' theme_mods / sidebars_widgets): Einstellungen,
        // Variante und Widget-Anordnung je Theme, damit ein Theme-Wechsel nichts überschreibt.
        $mods=[];
        foreach(array_slice((array)($value['mods']??[]),0,50,true) as $tid=>$m){
            $tid=rrw_theme_id((string)$tid);if($tid===''||!is_array($m))continue;
            $mods[$tid]=['variant'=>$cleanVariant($m['variant']??'default'),'settings'=>$cleanSettings($m['settings']??[]),'widget_areas'=>rrw_clean_section('widget_areas',$m['widget_areas']??[]),'saved_at'=>mb_substr(trim((string)($m['saved_at']??'')),0,32)];
        }
        $out['mods']=$mods;
        return $out;
    }
    if($section==='seo'){
        $base=trim((string)($value['canonical_base']??rrw_default_canonical_base()));
        if(!preg_match('#^https://[a-z0-9.-]+(:\d{2,5})?$#i',$base))$base=rrw_default_canonical_base();
        return ['enabled'=>!array_key_exists('enabled',$value)||!empty($value['enabled']),'site_title'=>mb_substr(trim((string)($value['site_title']??(($GLOBALS['RRW_SITE']['portal']['site_name']??'')?:rrw_product_name()))),0,180),'description'=>mb_substr(trim((string)($value['description']??'')),0,500),'canonical_base'=>rtrim($base,'/'),'index_custom_pages'=>!array_key_exists('index_custom_pages',$value)||!empty($value['index_custom_pages']),'index_news'=>!array_key_exists('index_news',$value)||!empty($value['index_news']),'robots'=>in_array(($value['robots']??'index,follow'),['index,follow','noindex,nofollow'],true)?$value['robots']:'index,follow','og_image'=>mb_substr(trim((string)($value['og_image']??'/icon-512.png')),0,1000)];
    }
    if($section==='storage'){
        return ['mode'=>in_array(($value['mode']??'files'),['files','files+database'],true)?$value['mode']:'files','database_mirror'=>!empty($value['database_mirror'])];
    }
    if($section==='backup'){
        return ['include_media'=>!array_key_exists('include_media',$value)||!empty($value['include_media']),'keep'=>max(1,min(50,(int)($value['keep']??10)))];
    }
    if($section==='legal')return ['imprint_mode'=>($value['imprint_mode']??'link')==='custom'?'custom':'link','imprint_url'=>mb_substr(trim((string)($value['imprint_url']??'')),0,1200),'imprint_title'=>mb_substr((string)($value['imprint_title']??'Impressum'),0,160),'imprint_content'=>rrw_safe_html((string)($value['imprint_content']??'')),'privacy_mode'=>($value['privacy_mode']??'link')==='custom'?'custom':'link','privacy_url'=>mb_substr(trim((string)($value['privacy_url']??'')),0,1200),'privacy_title'=>mb_substr((string)($value['privacy_title']??'Datenschutz'),0,160),'privacy_content'=>rrw_safe_html((string)($value['privacy_content']??''))]+(isset($value['email'])&&filter_var((string)$value['email'],FILTER_VALIDATE_EMAIL)?['email'=>mb_substr(trim((string)$value['email']),0,200)]:[]);   // email: optional, von der WordPress-Schicht (admin_email) geschrieben
    return null;
}
function rrw_render_blocks(array $blocks,array $widgets): string {
    $by=[];foreach($widgets as $w)$by[$w['id']??'']=$w;$html='';
    foreach($blocks as $b){if(empty($b['enabled']))continue;$t=$b['type']??'text';
        if($t==='heading'){$l=in_array((int)($b['level']??2),[2,3,4],true)?(int)$b['level']:2;$html.="<h$l>".htmlspecialchars((string)($b['text']??''),ENT_QUOTES,'UTF-8')."</h$l>";}
        elseif($t==='text')$html.='<p>'.nl2br(htmlspecialchars((string)($b['text']??''),ENT_QUOTES,'UTF-8')).'</p>';
        elseif($t==='quote')$html.='<blockquote>'.nl2br(htmlspecialchars((string)($b['text']??''),ENT_QUOTES,'UTF-8')).'</blockquote>';
        elseif($t==='html')$html.=(string)($b['html']??'');
        elseif($t==='image')$html.='<figure><img src="'.htmlspecialchars((string)($b['url']??''),ENT_QUOTES,'UTF-8').'" alt="'.htmlspecialchars((string)($b['alt']??''),ENT_QUOTES,'UTF-8').'"><figcaption>'.htmlspecialchars((string)($b['caption']??''),ENT_QUOTES,'UTF-8').'</figcaption></figure>';
        elseif($t==='button')$html.='<p><a class="rrw-btn" href="'.htmlspecialchars((string)($b['url']??'#'),ENT_QUOTES,'UTF-8').'">'.htmlspecialchars((string)($b['label']??'Mehr erfahren'),ENT_QUOTES,'UTF-8').'</a></p>';
        elseif($t==='divider')$html.='<hr>';
        elseif($t==='spacer')$html.='<div style="height:'.max(8,min(160,(int)($b['size']??32))).'px"></div>';
        elseif($t==='widget'){$w=$by[$b['widget_id']??'']??null;if($w)$html.='<section class="rrw-widget"><h3>'.htmlspecialchars((string)($w['title']??$w['name']??''),ENT_QUOTES,'UTF-8').'</h3><p>Dieses Widget wird auf der Hauptseite interaktiv dargestellt.</p></section>';}
    } return $html;
}
function rrw_generate_custom_pages(array $site,string $root): void {
    $wanted=[];$widgets=(array)($site['widgets']??[]);$menus=(array)($site['menus']['top']??[]);
    $nav='';foreach($menus as $m){if(empty($m['enabled'])||!empty($m['parent_id']))continue;$target=(string)($m['target']??'');$href='#';if(str_starts_with($target,'page:'))$href='/'.rrw_slug(substr($target,5)).'.html';elseif(str_starts_with($target,'system:'))$href='/#'.substr($target,7);elseif(str_starts_with($target,'http')||str_starts_with($target,'/'))$href=$target;$nav.='<a href="'.htmlspecialchars($href,ENT_QUOTES,'UTF-8').'">'.htmlspecialchars((string)($m['label']??''),ENT_QUOTES,'UTF-8').'</a>';}
    rrw_page_schedule_write($root.'/cms/data',(array)($site['pages']??[]));
    foreach((array)($site['pages']??[]) as $p){if(($p['type']??'')!=='custom'||empty($p['enabled']))continue;$slug=rrw_slug((string)($p['slug']??$p['title']??'seite'));$file=$root.'/'.$slug.'.html';$wanted[$file]=true;$title=htmlspecialchars((string)($p['headline']?:$p['title']??$slug),ENT_QUOTES,'UTF-8');$intro=htmlspecialchars((string)($p['intro']??''),ENT_QUOTES,'UTF-8');$body=rrw_render_blocks(array_merge((array)($p['blocks_before']??[]),(array)($p['blocks_after']??[])),$widgets);
        $siteName=trim((string)($site['portal']['site_name']??''))?:rrw_product_name();$pageSuffix=$siteName;
        $mt=trim((string)($p['meta_title']??''));$metaTitle=htmlspecialchars($mt!==''?$mt:(string)($p['headline']?:$p['title']??$slug).' – '.$pageSuffix,ENT_QUOTES,'UTF-8');
        $md=trim((string)($p['meta_description']??''));if($md==='')$md=trim((string)($p['intro']??''));
        $metaTags=($md!==''?'<meta name="description" content="'.htmlspecialchars(mb_substr($md,0,300),ENT_QUOTES,'UTF-8').'">':'').(!empty($p['noindex'])?'<meta name="robots" content="noindex,follow">':'');
        $activeTheme=rrw_theme_id((string)($site['theme']['active']??'rrw-classic'));
        $themeLink=is_file(dirname(__DIR__).'/themes/'.$activeTheme.'/theme.css')?'<link rel="stylesheet" href="/cms/themes/'.htmlspecialchars($activeTheme,ENT_QUOTES,'UTF-8').'/theme.css">':'';
        $ts=is_array($site['theme']['settings']??null)?$site['theme']['settings']:[];
        $safeColor=function($v,$fallback){$v=trim((string)$v);return preg_match('/^#[0-9a-fA-F]{6}$/',$v)?$v:$fallback;};
        $themeVars='<style>:root{--rrw-theme-accent:'.$safeColor($ts['accent']??'','#b57cff').';--rrw-theme-accent2:'.$safeColor($ts['accent2']??'','#22d3ee').';--rrw-theme-background:'.$safeColor($ts['background']??'','#06060a').';--rrw-theme-surface:'.$safeColor($ts['surface']??'','#101522').';--rrw-theme-text:'.$safeColor($ts['text']??'','#f4f6ff').';--rrw-theme-radius:'.max(0,min(40,(float)($ts['radius']??16))).';--rrw-theme-content-width:'.max(800,min(1900,(float)($ts['content_width']??1320))).';--rrw-theme-font-scale:'.max(.75,min(1.4,(float)($ts['font_scale']??1))).';}</style>';
        $customCss=trim((string)($ts['custom_css']??''));if($customCss!=='')$themeVars.='<style>'.$customCss.'</style>';
        $pageLogo=(string)($site['branding']['portal_logo']??rrw_product_logo());if($pageLogo==='')$pageLogo='/cms/assets/brand/icon-192.png';
        $pageBrand=htmlspecialchars($siteName,ENT_QUOTES,'UTF-8');$pageLogoEsc=htmlspecialchars($pageLogo,ENT_QUOTES,'UTF-8');
        $footerLabel=$siteName;
        $html=RRW_CMS_MARKER."\n<!doctype html><html lang=\"de\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><title>$metaTitle</title>$metaTags<link rel=\"icon\" href=\"/icon-192.png\"><link rel=\"stylesheet\" href=\"/cms/generated/page.css\">".$themeLink.$themeVars."</head><body data-rrw-theme=\"".htmlspecialchars($activeTheme,ENT_QUOTES,'UTF-8')."\"><header><a class=\"brand\" href=\"/\"><img src=\"$pageLogoEsc\" alt=\"$pageBrand\"></a><nav>$nav</nav></header><main><article><h1>$title</h1>".($intro!==''?"<p class=\"intro\">$intro</p>":'').$body."</article></main><footer><a href=\"/\">".htmlspecialchars($footerLabel,ENT_QUOTES,'UTF-8')."</a></footer></body></html>";
        rrw_write_atomic($file,$html);
    }
    foreach(glob($root.'/*.html')?:[] as $file){if(isset($wanted[$file]))continue;$head=(string)@file_get_contents($file,false,null,0,128);if(str_contains($head,RRW_CMS_MARKER))@unlink($file);}
}
function rrw_update_index_snapshot(array $site,string $root): void {
    $file=$root.'/index.html';
    if(!is_file($file))throw new RuntimeException('index.html wurde nicht gefunden');
    $html=(string)file_get_contents($file);
    $start='<!-- RRW-CMS-SNAPSHOT-START -->';
    $end='<!-- RRW-CMS-SNAPSHOT-END -->';
    $payload=$start."\n<script>window.__RRW_CMS_FILE__ = ".json_encode(rrw_site_public($site)+(rrw_standalone()?['standalone'=>true]:[]),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).";</script>\n".$end;
    $pattern='#'.preg_quote($start,'#').'.*?'.preg_quote($end,'#').'#s';
    if(preg_match($pattern,$html))$html=preg_replace($pattern,$payload,$html,1);
    else {
        $pos=strpos($html,'</head>');
        if($pos===false)throw new RuntimeException('index.html enthält kein </head>');
        $html=substr($html,0,$pos)."\n".$payload."\n".substr($html,$pos);
    }
    $seo=function_exists('rrw_seo_defaults')?rrw_seo_defaults($site):[];
    $title=htmlspecialchars((string)($seo['site_title']??$site['portal']['site_name']??rrw_product_name()),ENT_QUOTES,'UTF-8');
    $desc=htmlspecialchars((string)($seo['description']??$site['portal']['news_intro']??''),ENT_QUOTES,'UTF-8');
    $canon=htmlspecialchars(rtrim((string)($seo['canonical_base']??rrw_default_canonical_base()),'/').'/',ENT_QUOTES,'UTF-8');
    $robots=htmlspecialchars((string)($seo['robots']??'index,follow'),ENT_QUOTES,'UTF-8');
    $og=htmlspecialchars((string)($seo['og_image']??$site['branding']['portal_icon']??'/icon-512.png'),ENT_QUOTES,'UTF-8');
    $favicon=htmlspecialchars((string)($site['branding']['favicon']??'/icon-192.png'),ENT_QUOTES,'UTF-8');
    $html=preg_replace('#<title id="page-title">.*?</title>#s','<title id="page-title">'.$title.'</title>',$html,1);
    $html=preg_replace('#<meta id="meta-description" name="description" content="[^"]*">#','<meta id="meta-description" name="description" content="'.$desc.'">',$html,1);
    if(preg_match('#<meta name="robots" content="[^"]*">#',$html))$html=preg_replace('#<meta name="robots" content="[^"]*">#','<meta name="robots" content="'.$robots.'">',$html,1);
    else $html=preg_replace('#(<meta id="meta-description"[^>]+>)#','$1'."\n    ".'<meta name="robots" content="'.$robots.'">',$html,1);
    if(preg_match('#<link rel="canonical" href="[^"]*">#',$html))$html=preg_replace('#<link rel="canonical" href="[^"]*">#','<link rel="canonical" href="'.$canon.'">',$html,1);
    else $html=preg_replace('#(<meta name="robots"[^>]+>)#','$1'."\n    ".'<link rel="canonical" href="'.$canon.'">',$html,1);
    $html=preg_replace('#<meta id="og-title" property="og:title" content="[^"]*">#','<meta id="og-title" property="og:title" content="'.$title.'">',$html,1);
    $html=preg_replace('#<meta id="og-description" property="og:description" content="[^"]*">#','<meta id="og-description" property="og:description" content="'.$desc.'">',$html,1);
    $html=preg_replace('#<meta id="og-image" property="og:image" content="[^"]*">#','<meta id="og-image" property="og:image" content="'.$og.'">',$html,1);
    $html=preg_replace('#<meta id="twitter-title" name="twitter:title" content="[^"]*">#','<meta id="twitter-title" name="twitter:title" content="'.$title.'">',$html,1);
    $html=preg_replace('#<meta id="twitter-description" name="twitter:description" content="[^"]*">#','<meta id="twitter-description" name="twitter:description" content="'.$desc.'">',$html,1);
    $html=preg_replace('#<meta id="twitter-image" name="twitter:image" content="[^"]*">#','<meta id="twitter-image" name="twitter:image" content="'.$og.'">',$html,1);
    $html=preg_replace('#<link rel="icon" href="[^"]*" type="image/png">#','<link rel="icon" href="'.$favicon.'">',$html,1);
    rrw_write_atomic($file,$html);
}
function rrw_rss_xml(array $site): string {
    $news=rrw_read_json(__DIR__.'/../data/news.json',[]);
    $published=array_values(array_filter($news,'rrw_news_is_live'));
    if(!empty($site['rss']['include_external']))$published=array_merge($published,rrw_external_feed_articles($site));
    usort($published,'rrw_news_cmp_desc');
    $max=max(5,min(100,(int)($site['rss']['max_items']??50)));$published=array_slice($published,0,$max);
    $x=fn($s)=>htmlspecialchars((string)$s,ENT_XML1|ENT_QUOTES,'UTF-8');
    $cdata=fn($s)=>'<![CDATA['.str_replace(']]>',']]]]><![CDATA[>',(string)$s).']]>';
    $rb=rtrim((string)($site['seo']['canonical_base']??rrw_default_canonical_base()),'/');
    $title=(string)($site['rss']['title']??'News');
    $desc=(string)($site['rss']['description']??'News, Magazin, Musik, Radio und Community.');
    $out="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $out.="<?xml-stylesheet type=\"text/xsl\" href=\"/cms/rss.xsl\"?>\n";
    $out.="<rss version=\"2.0\" xmlns:atom=\"http://www.w3.org/2005/Atom\" xmlns:content=\"http://purl.org/rss/1.0/modules/content/\" xmlns:media=\"http://search.yahoo.com/mrss/\">\n<channel>\n";
    $self=$rb.'/rss.xml';
    $out.="<title>".$x($title)."</title><link>".$x($rb.'/')."</link><description>".$x($desc)."</description><language>de-de</language><lastBuildDate>".gmdate(DATE_RSS)."</lastBuildDate><generator>".htmlspecialchars(rrw_product_generator(),ENT_XML1|ENT_QUOTES,'UTF-8')."</generator><atom:link href=\"".$x($self)."\" rel=\"self\" type=\"application/rss+xml\" />\n";
    foreach($published as $a){
        $external=!empty($a['is_external']);$slug=(string)($a['slug']??'');
        $key=rawurlencode($slug!==''?$slug:(string)($a['id']??''));
        $link=$external?(string)($a['external_url']??''):$rb.'/'.$key.'/';
        if($link==='')$link=$rb.'/';
        $guid=$external?('ext-'.md5((string)($a['external_feed_url']??'').'|'.$link)):'rrw-news-'.(string)($a['id']??md5($link));
        $date=(string)($a['published_at']??$a['created_at']??'');$ts=$date!==''?strtotime($date):false;
        $html=(string)($a['body_html']??'');if($html==='')$html='<p>'.htmlspecialchars((string)($a['excerpt']??''),ENT_QUOTES,'UTF-8').'</p>';
        $out.="<item><title>".$x($a['title']??'')."</title><link>".$x($link)."</link><guid isPermaLink=\"false\">".$x($guid)."</guid><pubDate>".gmdate(DATE_RSS,$ts?:time())."</pubDate>".(trim((string)($a['category']??''))!==''?"<category>".$x($a['category'])."</category>":"")."<description>".$cdata((string)($a['excerpt']??''))."</description><content:encoded>".$cdata($html)."</content:encoded>";
        if(!empty($a['image_url']))$out.="<media:content url=\"".$x($a['image_url'])."\" medium=\"image\" />";
        $out.="</item>\n";
    }
    return $out."</channel></rss>\n";
}
function rrw_publish(array $site,string $siteFile,string $genDir,string $root): void {
    if(function_exists('rrw_content_pull')){ try{ $pull=rrw_content_pull($site);$site=$pull['site']; }catch(\Throwable $e){} }   // Dateien (Git) → Website, bevor der Spiegel Website → Dateien schreibt
    rrw_write_atomic($siteFile,json_encode($site,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    // Startseiten-Snapshot und statische Seiten gehören zum Portal (index.html); das eigenständige CMS liefert über ein WordPress-Theme aus und hat keine
    if(is_file($root.'/index.html')){ rrw_update_index_snapshot($site,$root);rrw_generate_custom_pages($site,$root); }
    if(function_exists('rrw_content_sync_from_site'))rrw_content_sync_from_site($site);
    $news=rrw_read_json(__DIR__.'/../data/news.json',[]);
    if(function_exists('rrw_seo_generate'))rrw_seo_generate($site,$news,$root);
    if(!isset($site['rss']['enabled'])||!empty($site['rss']['enabled']))rrw_write_atomic($root.'/rss.xml',rrw_rss_xml($site));
    if(!empty($site['storage']['database_mirror'])&&function_exists('rrw_db_push')){
        try{rrw_db_push($site,$news);}catch(Throwable $e){}
    }
    if(function_exists('rrw_np_do'))rrw_np_do('content_saved','site');   // Plugins: z. B. Seiten-Cache leeren
}

function rrw_ensure_site_defaults(array $site): array {
    if(function_exists('rrw_assistant_clean'))$site['assistant']=rrw_assistant_clean($site['assistant']??[]);
    $GLOBALS['RRW_SITE']=$site;
    if(function_exists('rrw_alexa_clean'))$site['alexa']=rrw_alexa_clean($site['alexa']??[]);
    if(!function_exists('rrw_services_clean'))require_once __DIR__.'/services.php';$site['services']=rrw_services_clean(is_array($site['services']??null)?$site['services']:[]);   // eigene Dienste
    $site['menus']=is_array($site['menus']??null)?$site['menus']:[];
    $site['menus']['top']=is_array($site['menus']['top']??null)?$site['menus']['top']:[];
    $site['menus']['bottom']=is_array($site['menus']['bottom']??null)?$site['menus']['bottom']:[];
    $site['_meta']=is_array($site['_meta']??null)?$site['_meta']:[];

    $site['branding']=is_array($site['branding']??null)?$site['branding']:[];
    // Marken-Registry (Multi-Domain): fehlt sie, entsteht sie mit der eigenen Hauptmarke.
    $GLOBALS['RRW_SITE']=$site;
    $site['brands']=rrw_brands_clean($site['brands']??rrw_brand_defaults());
    $site['branding_media']=is_array($site['branding_media']??null)?$site['branding_media']:[];

    if(!isset($site['rss'])||!is_array($site['rss']))$site['rss']=[
        'enabled'=>true,
        'title'=>trim((string)($site['portal']['site_name']??'')).' – News',
        'description'=>'',
        'max_items'=>50,
        'include_external'=>true
    ];
    if(!isset($site['feed_sources'])||!is_array($site['feed_sources']))$site['feed_sources']=[];
    if(!isset($site['plugins'])||!is_array($site['plugins']))$site['plugins']=[];
    if(!isset($site['seo'])||!is_array($site['seo']))$site['seo']=rrw_seo_defaults($site);
    else $site['seo']=rrw_seo_defaults($site);
    if(!isset($site['storage'])||!is_array($site['storage']))$site['storage']=['mode'=>'files','database_mirror'=>false];
    if(!isset($site['backup'])||!is_array($site['backup']))$site['backup']=['include_media'=>true,'keep'=>10];
    if(!isset($site['comments'])||!is_array($site['comments']))$site['comments']=['enabled'=>false,'require_approval'=>true];

    $site['widgets']=is_array($site['widgets']??null)?$site['widgets']:[];
    $required=[
        ['id'=>'w-news','name'=>'Aktuelle News','type'=>'builtin','builtin'=>'news-latest','category'=>'Inhalte','enabled'=>true,'title'=>'Aktuelle News','config'=>[]],
    ];
    $ids=[];foreach($site['widgets'] as $w){$ids[(string)($w['id']??'')]=true;}
    foreach($required as $w)if(empty($ids[$w['id']]))$site['widgets'][]=$w;

    if(!isset($site['widget_areas'])||!is_array($site['widget_areas']))$site['widget_areas']=[
        ['id'=>'sidebar-global','name'=>'Globale Sidebar','kind'=>'sidebar','scope'=>'global','page_id'=>'','position'=>'right','enabled'=>false,'widgets'=>[]],
        ['id'=>'sidebar-start','name'=>'Startseite Sidebar','kind'=>'sidebar','scope'=>'page','page_id'=>'start','position'=>'right','enabled'=>false,'widgets'=>[]],
        ['id'=>'sidebar-news','name'=>'News Sidebar','kind'=>'sidebar','scope'=>'page','page_id'=>'news','position'=>'right','enabled'=>false,'widgets'=>[]],
        ['id'=>'footer-global','name'=>'Footer Widgets','kind'=>'footer','scope'=>'global','page_id'=>'','position'=>'bottom','enabled'=>false,'widgets'=>[]]
    ];
    // Widget-Instanzen: alte Bereiche mit reinen Widget-IDs werden hier einmalig in Instanzen
    // mit eigenem Titel/Einstellungen überführt (siehe rrw_widget_instance_clean()).
    $GLOBALS['RRW_SITE']=$site;
    $site['widget_areas']=rrw_clean_section('widget_areas',$site['widget_areas']);
    $site['widget_inactive']=rrw_clean_section('widget_inactive',$site['widget_inactive']??[]);
    $GLOBALS['RRW_SITE']=$site;
    return $site;
}

// Strukturelles Layout eines Themes aus theme.json: Sidebar-Seite und -Breite, Header-, Hero-,
// News- und Container-Variante. Nur bekannte Werte; fehlt der Block, entspricht das Ergebnis
// exakt dem heutigen Standard-Layout (Sidebar rechts, 320px, Balken-Header, geteilter Hero).
function rrw_theme_layout_clean($layout): array {
    $layout=is_array($layout)?$layout:[];
    $pick=function(string $k,array $allowed,string $def)use($layout):string{$v=strtolower(trim((string)($layout[$k]??'')));return in_array($v,$allowed,true)?$v:$def;};
    return [
        'sidebar'=>$pick('sidebar',['right','left','none'],'right'),
        'sidebar_width'=>max(220,min(480,(int)($layout['sidebar_width']??320))),
        'header'=>$pick('header',['bar','stacked','centered'],'bar'),
        'hero'=>$pick('hero',['split','full','compact'],'split'),
        'news'=>$pick('news',['cards','list','magazine'],'cards'),
        'container'=>$pick('container',['wide','boxed','narrow'],'wide'),
    ];
}
// Merkt sich Variante, Einstellungen und Widget-Anordnung des aktiven Themes unter
// theme.mods[<id>], damit sie beim Wechsel zu einem anderen Theme erhalten bleiben.
function rrw_theme_remember_mods(array $site): array {
    $active=rrw_theme_id((string)($site['theme']['active']??rrw_default_theme_id()));if($active==='')return $site;
    $mods=is_array($site['theme']['mods']??null)?$site['theme']['mods']:[];
    $mods[$active]=['variant'=>(string)($site['theme']['variant']??'default'),'settings'=>is_array($site['theme']['settings']??null)?$site['theme']['settings']:[],'widget_areas'=>array_values(is_array($site['widget_areas']??null)?$site['widget_areas']:[]),'saved_at'=>date('Y-m-d H:i:s')];
    $site['theme']['mods']=$mods;
    return $site;
}
// Öffentliche Sicht auf die Konfiguration (Snapshot in index.html, action=public): die pro Theme
// gespeicherten Anpassungen/Anordnungen nicht aktiver Themes sind nur für die Verwaltung relevant.
/** WordPress-Shortcodes ([forum], [umfrage id=…], Plugin-Shortcodes …) in HTML auflösen. Ohne „[“ oder ohne Laufzeit bleibt der Text unverändert. */
function rrw_expand_shortcodes(string $html): string {
    static $busy=false;
    if($busy||!str_contains($html,'[')||!is_file(dirname(__DIR__).'/wp/load.php'))return $html;
    $busy=true;
    try{
        require_once dirname(__DIR__).'/wp/load.php';
        if(!isset($GLOBALS['RRW_SITE'])&&isset($GLOBALS['site']))$GLOBALS['RRW_SITE']=$GLOBALS['site'];
        ob_start();$out=rrw_wp_expand_content($html);ob_end_clean();
        return $out;
    }catch(Throwable $e){ while(ob_get_level()>($GLOBALS['rrw_ob_base']??0)&&false)ob_end_clean(); return $html; }
    finally{ $busy=false; }
}
function rrw_site_public(array $site): array {
    // Shortcodes in Seiten-Blöcken und HTML-Widgets auflösen (beim Veröffentlichen)
    foreach(['pages'] as $k)if(isset($site[$k])&&is_array($site[$k]))foreach($site[$k] as &$pg){
        if(!is_array($pg))continue;
        foreach(['blocks_before','blocks_after'] as $bk)if(isset($pg[$bk])&&is_array($pg[$bk]))foreach($pg[$bk] as &$bl)if(is_array($bl)&&isset($bl['html'])&&is_string($bl['html']))$bl['html']=rrw_expand_shortcodes($bl['html']);unset($bl);
    }unset($pg);
    $expandW=function($w){ if(is_array($w)&&($w['type']??'')==='html'&&isset($w['settings']['html'])&&is_string($w['settings']['html']))$w['settings']['html']=rrw_expand_shortcodes($w['settings']['html']);return $w; };
    if(isset($site['widget_areas'])&&is_array($site['widget_areas']))foreach($site['widget_areas'] as &$ar0)if(is_array($ar0)&&isset($ar0['widgets'])&&is_array($ar0['widgets']))$ar0['widgets']=array_map($expandW,$ar0['widgets']);unset($ar0);

    if(isset($site['theme']['mods']))unset($site['theme']['mods']);
    // KI-Assistent: API-Keys und interne Prompts bleiben serverseitig
    if(function_exists('rrw_assistant_public'))$site['assistant']=rrw_assistant_public((array)($site['assistant']??[]));
    else unset($site['assistant']);
    // Empfänger-Adresse der Formular-Widgets (Kontakt/Newsletter) ist nicht für Besucher gedacht
    $strip=function(array $w): array {if(in_array($w['type']??'',['contact-form','newsletter'],true)&&isset($w['settings']['notify']))unset($w['settings']['notify']);return $w;};
    if(isset($site['widget_inactive'])&&is_array($site['widget_inactive']))$site['widget_inactive']=array_map(fn($w)=>is_array($w)?$strip($w):$w,$site['widget_inactive']);
    if(isset($site['widget_areas'])&&is_array($site['widget_areas']))foreach($site['widget_areas'] as &$ar){if(is_array($ar)&&isset($ar['widgets'])&&is_array($ar['widgets']))$ar['widgets']=array_map(fn($w)=>is_array($w)?$strip($w):$w,$ar['widgets']);}unset($ar);
    return $site;
}
function rrw_theme_id(string $s): string {
    $s=strtolower(trim($s));$s=preg_replace('/[^a-z0-9_-]+/','-',$s);$s=trim((string)$s,'-');return substr($s!==''?$s:'theme',0,64);
}

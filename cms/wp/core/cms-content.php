<?php
// Brücke zwischen den CMS-Inhalten (Beiträge in news.json, eigene Seiten in site.json) und dem WordPress-Inhaltsmodell.
// CMS-Beiträge erscheinen als WP_Post vom Typ „post“, CMS-Seiten als „page“ – lesend, mit stabilen IDs:
//  Beiträge: ID = News-ID   Seiten: 20 000 000 + Hash   Kategorien 70 000 000 + Hash   Schlagwörter 71 000 000 + Hash   Anhänge 60 000 000 + Hash
//  Von WordPress-Code selbst angelegte Inhalte stehen in der Tabelle wp_posts (IDs ab 100 000 000).
const ELVADO_WP_ID_DB_MIN=100000000;
const ELVADO_WP_ID_PAGE_BASE=20000000;
const ELVADO_WP_ID_ATT_BASE=60000000;
const ELVADO_WP_ID_CAT_BASE=70000000;
const ELVADO_WP_ID_TAG_BASE=71000000;

function elvado_wp_cms_dir(): string { return defined('ELVADO_WP_CMS_DATA')?(string)ELVADO_WP_CMS_DATA:dirname(ELVADO_WP_DATA); }
function elvado_wp_cms_reset(): void { $GLOBALS['elvado_wp_cms_cache']=null; }
function elvado_wp_hash_id(string $s, int $base): int { return $base+(crc32($s)%900000); }
function elvado_wp_read_json(string $f, $fallback) { $r=is_file($f)?json_decode((string)@file_get_contents($f),true):null;return is_array($r)?$r:$fallback; }
function elvado_wp_cms_data(): array {
    if(isset($GLOBALS['elvado_wp_cms_cache']))return $GLOBALS['elvado_wp_cms_cache'];
    $dir=elvado_wp_cms_dir();$news=elvado_wp_read_json($dir.'/news.json',[]);$site=elvado_wp_read_json($dir.'/site.json',[]);
    $now=date('Y-m-d H:i:s');$live=[];
    foreach($news as $a){ if(!is_array($a)||($a['status']??'draft')!=='published'||!empty($a['deleted_at']))continue; $pa=trim((string)($a['published_at']??''));if($pa!==''&&$pa>$now)continue; $live[]=$a; }
    return $GLOBALS['elvado_wp_cms_cache']=['news'=>$live,'site'=>$site,'news_all'=>$news];
}
function elvado_wp_cms_author_id(string $name): int { return $name===''?1:(1+(crc32(mb_strtolower($name))%90000)); }
function elvado_wp_cms_term_slug(string $s): string { return sanitize_title($s); }
/** WordPress-Status eines CMS-Beitrags: Papierkorb (deleted_at) > Entwurf (ggf. wp_status pending/private) > geplant > veröffentlicht. */
function elvado_wp_cms_news_status(array $a, string $date): string {
    if(!empty($a['deleted_at']))return 'trash';
    if(($a['status']??'draft')!=='published'){ $w=(string)($a['wp_status']??'');return in_array($w,['pending','private'],true)?$w:'draft'; }
    return $date>date('Y-m-d H:i:s')?'future':'publish';
}
function elvado_wp_cms_news_post(array $a): WP_Post {
    $date=trim((string)($a['published_at']??$a['created_at']??date('Y-m-d H:i:s')));if(strlen($date)<19)$date=date('Y-m-d H:i:s',strtotime($date)?:time());
    $mod=trim((string)($a['updated_at']??$date));if(strlen($mod)<19)$mod=$date;
    $id=(int)($a['id']??0);$slug=(string)($a['slug']??'');
    $p=new WP_Post((object)['ID'=>$id,'post_author'=>(string)elvado_wp_cms_author_id((string)($a['author']??'')),'post_date'=>$date,'post_date_gmt'=>get_gmt_from_date($date)?:$date,'post_content'=>(string)($a['body_html']??''),'post_title'=>esc_html((string)($a['title']??'')),
        'post_excerpt'=>esc_html((string)($a['excerpt']??'')),'post_status'=>elvado_wp_cms_news_status($a,$date),'comment_status'=>(($a['comments']??'default')==='closed'?'closed':'open'),'ping_status'=>'closed','post_name'=>$slug,'post_modified'=>$mod,'post_modified_gmt'=>get_gmt_from_date($mod)?:$mod,'post_parent'=>0,'guid'=>home_url('/#news/'.rawurlencode($slug)),'menu_order'=>0,'post_type'=>'post','filter'=>'raw']);
    $p->elvado_source='news';$p->elvado_data=$a;return $p;
}
/** Inhaltsblöcke einer CMS-Seite (html/text) in Anzeigereihenfolge: [Liste, Index, Block, Marker-ID]. */
function elvado_wp_cms_page_slots(array $pg): array {
    $o=[];
    foreach(['blocks_before','blocks_after'] as $k)foreach((array)($pg[$k]??[]) as $i=>$b){
        if(!is_array($b)||!in_array($b['type']??'',['html','text'],true))continue;
        $o[]=[$k,$i,$b,preg_replace('/[^A-Za-z0-9_-]/','',(string)($b['id']??''))?:$k.'-'.$i];
    }
    return $o;
}
function elvado_wp_cms_page_html(array $pg): string {
    $slots=elvado_wp_cms_page_slots($pg);
    $mark=count($slots)>1&&function_exists('elvado_wp_bridge')&&elvado_wp_bridge('pages')&&(is_admin()||wp_is_serving_rest_request());   // Block-Marker nur mit Schreibbrücke, bei mehrteiligen Seiten und nur für Editoren (Verwaltung, REST) – Besucher sehen unverändertes HTML
    $h='';foreach($slots as [,,$b,$mid]){
        $x=($b['type']??'')==='html'?(string)($b['html']??''):'<p>'.esc_html((string)($b['text']??'')).'</p>';
        $h.=$mark?'<!--elvado:block '.$mid.'-->'.$x.'<!--/elvado:block-->':$x;
    }
    if(($pg['intro']??'')!=='')$h='<p>'.esc_html((string)$pg['intro']).'</p>'.$h;
    if(($pg['type']??'')==='system')$h.='<p class="elvado-portal-link"><a class="wp-block-button__link wp-element-button button" href="'.esc_url(home_url('/#'.rawurlencode((string)($pg['system_target']??'')))).'">'.esc_html__('Interaktiv im Radioportal öffnen','default').'</a></p>';   // der Player und die Live-Daten laufen im Portal
    return $h;
}
function elvado_wp_cms_page_post(array $pg, string $status='publish'): WP_Post {
    $slug=(string)($pg['slug']??$pg['id']??'');$id=elvado_wp_hash_id((string)($pg['id']??$slug),ELVADO_WP_ID_PAGE_BASE);$d=date('Y-m-d H:i:s',(int)@filemtime(elvado_wp_cms_dir().'/site.json')?:time());
    $p=new WP_Post((object)['ID'=>$id,'post_author'=>'1','post_date'=>$d,'post_date_gmt'=>$d,'post_content'=>elvado_wp_cms_page_html($pg),'post_title'=>esc_html((string)($pg['headline']??'')!==''?(string)$pg['headline']:(string)($pg['title']??'')),'post_excerpt'=>'','post_status'=>$status,'comment_status'=>'closed','ping_status'=>'closed',
        'post_name'=>$slug,'post_modified'=>$d,'post_modified_gmt'=>$d,'post_parent'=>0,'guid'=>home_url('/'.$slug.'.html'),'menu_order'=>0,'post_type'=>'page','filter'=>'raw']);
    $p->elvado_source='page';$p->elvado_data=$pg;return $p;
}
/** Alle CMS-Beiträge (live) als WP_Post, neueste zuerst. */
function elvado_wp_cms_posts(): array {
    elvado_wp_cms_data();
    if(!isset($GLOBALS['elvado_wp_cms_cache']['posts'])){
        $out=array_map('elvado_wp_cms_news_post',$GLOBALS['elvado_wp_cms_cache']['news']);
        usort($out,fn($a,$b)=>strcmp($b->post_date,$a->post_date)?:($b->ID<=>$a->ID));
        $GLOBALS['elvado_wp_cms_cache']['posts']=$out;
    }
    return $GLOBALS['elvado_wp_cms_cache']['posts'];
}
/** Eigene (aktivierte) CMS-Seiten als WP_Post. */
function elvado_wp_cms_pages(): array {
    $d=elvado_wp_cms_data();$out=[];
    foreach((array)($d['site']['pages']??[]) as $pg){ if(!is_array($pg)||($pg['type']??'')!=='custom'||empty($pg['enabled']))continue; $out[]=elvado_wp_cms_page_post($pg); }
    // Portal-Bereiche (Sender, Sendeplan, Voting, Podcast, Hilfe, Apps, Shops) als Seiten des Themes – gleiche Texte wie die statischen Seiten, aber im gewählten Theme
    $have=array_column($out,'post_name');
    foreach((array)($d['site']['pages']??[]) as $pg){
        if(!is_array($pg)||($pg['type']??'')!=='system'||empty($pg['enabled']))continue;
        $slug=elvado_wp_system_slug((string)($pg['system_target']??''));if($slug===''||in_array($slug,$have,true))continue;
        $out[]=elvado_wp_cms_page_post(['slug'=>$slug,'id'=>'system-'.$slug]+$pg);$have[]=$slug;
    }
    return $out;
}
/** Seiten-Adresse eines Portal-Bereichs im Theme („sender“ → /sender/); '' für Bereiche ohne eigene Seite (Start, News, Detailseiten, Aktionen). */
function elvado_wp_system_slug(string $target): string {
    static $map=null;if($map===null){ $f=dirname(__DIR__,2).'/lib/seo.php';$map=[];if(is_file($f)){ if(!function_exists('elvado_seo_system_map'))require_once $f;$map=elvado_seo_system_map(); } }
    $s=(string)($map[$target]??'');return in_array($target,['start','news','senderdetail'],true)||!preg_match('/^[a-z0-9_-]+$/',$s)?'':$s;
}
function elvado_wp_cms_find_post(int $id): ?WP_Post {
    if($id<=0||$id>=ELVADO_WP_ID_DB_MIN)return null;
    if($id>=ELVADO_WP_ID_PAGE_BASE&&$id<ELVADO_WP_ID_PAGE_BASE+900000){ foreach(elvado_wp_cms_pages() as $p)if($p->ID===$id)return $p;return elvado_wp_cms_find_any($id); }
    if($id<10000000){ foreach(elvado_wp_cms_posts() as $p)if($p->ID===$id)return $p;return elvado_wp_cms_find_any($id); }
    if($id>=ELVADO_WP_ID_ATT_BASE&&$id<ELVADO_WP_ID_ATT_BASE+900000&&function_exists('elvado_wp_cms_media_post')&&elvado_wp_bridge('media'))return elvado_wp_cms_media_post($id);
    return null;
}
/* Mit eingeschalteter Schreibbrücke (cms-write.php) sind auch Entwürfe, geplante und gelöschte CMS-Beiträge sowie deaktivierte Seiten per ID erreichbar. */
function elvado_wp_cms_id_is_news(int $id): bool { return $id>0&&$id<10000000; }
function elvado_wp_cms_id_is_page(int $id): bool { return $id>=ELVADO_WP_ID_PAGE_BASE&&$id<ELVADO_WP_ID_PAGE_BASE+900000; }
/** Alle CMS-Beiträge unabhängig vom Status (auch Entwurf, geplant, Papierkorb), neueste zuerst. */
function elvado_wp_cms_posts_any(): array {
    elvado_wp_cms_data();
    if(!isset($GLOBALS['elvado_wp_cms_cache']['posts_any'])){
        $out=[];foreach((array)$GLOBALS['elvado_wp_cms_cache']['news_all'] as $a)if(is_array($a)&&(int)($a['id']??0)>0)$out[]=elvado_wp_cms_news_post($a);
        usort($out,fn($a,$b)=>strcmp($b->post_date,$a->post_date)?:($b->ID<=>$a->ID));
        $GLOBALS['elvado_wp_cms_cache']['posts_any']=$out;
    }
    return $GLOBALS['elvado_wp_cms_cache']['posts_any'];
}
/** Alle eigenen CMS-Seiten (auch deaktivierte = Entwurf). */
function elvado_wp_cms_pages_any(): array {
    $d=elvado_wp_cms_data();$out=[];
    foreach((array)($d['site']['pages']??[]) as $pg){ if(is_array($pg)&&($pg['type']??'')==='custom')$out[]=elvado_wp_cms_page_post($pg,!empty($pg['enabled'])?'publish':'draft'); }
    return $out;
}
function elvado_wp_cms_find_any(int $id): ?WP_Post {
    if(!function_exists('elvado_wp_bridge'))return null;
    if(elvado_wp_cms_id_is_news($id)&&elvado_wp_bridge('posts')){ foreach(elvado_wp_cms_posts_any() as $p)if($p->ID===$id)return $p; }
    elseif(elvado_wp_cms_id_is_page($id)&&elvado_wp_bridge('pages')){ foreach(elvado_wp_cms_pages_any() as $p)if($p->ID===$id)return $p; }
    return null;
}
function elvado_wp_is_cms_id(int $id): bool { return $id>0&&$id<ELVADO_WP_ID_DB_MIN; }

/* Virtuelle Begriffe aus den CMS-Beiträgen: Kategorie (ein Name je Beitrag) und Schlagwörter (kommagetrennt) */
function elvado_wp_cms_term(string $name, string $taxonomy): WP_Term {
    $slug=elvado_wp_cms_term_slug($name);$base=$taxonomy==='category'?ELVADO_WP_ID_CAT_BASE:ELVADO_WP_ID_TAG_BASE;$id=elvado_wp_hash_id($taxonomy.':'.$slug,$base);
    return new WP_Term((object)['term_id'=>$id,'name'=>$name,'slug'=>$slug,'term_group'=>0,'term_taxonomy_id'=>$id,'taxonomy'=>$taxonomy,'description'=>'','parent'=>0,'count'=>0,'filter'=>'raw']);
}
function elvado_wp_cms_terms(string $taxonomy): array {
    $d=elvado_wp_cms_data();$map=[];
    foreach($d['news'] as $a){
        $names=$taxonomy==='category'?[trim((string)($a['category']??''))]:array_filter(array_map('trim',is_array($a['tags']??null)?array_map('strval',$a['tags']):explode(',',(string)($a['tags']??''))));
        foreach($names as $n){ if($n==='')continue; $t=elvado_wp_cms_term($n,$taxonomy); if(!isset($map[$t->slug]))$map[$t->slug]=$t; $map[$t->slug]->count++; }
    }
    ksort($map);return array_values($map);
}
function elvado_wp_cms_post_terms(WP_Post $p, string $taxonomy): array {
    if($p->elvado_source!=='news')return [];$a=$p->elvado_data??[];
    $names=$taxonomy==='category'?[trim((string)($a['category']??''))]:array_filter(array_map('trim',is_array($a['tags']??null)?array_map('strval',$a['tags']):explode(',',(string)($a['tags']??''))));
    $out=[];foreach($names as $n)if($n!=='')$out[]=elvado_wp_cms_term($n,$taxonomy);
    return $out;
}
/** Titelbild: virtueller Anhang pro Bild-URL */
function elvado_wp_cms_attachment_id(string $url): int { $id=elvado_wp_hash_id('att:'.$url,ELVADO_WP_ID_ATT_BASE);$GLOBALS['elvado_wp_cms_attachments'][$id]=$url;return $id; }
function elvado_wp_cms_attachment_url(int $id): ?string {
    $u=$GLOBALS['elvado_wp_cms_attachments'][$id]??null;
    if($u===null&&$id>=ELVADO_WP_ID_ATT_BASE&&$id<ELVADO_WP_ID_ATT_BASE+900000&&function_exists('elvado_wp_cms_media_map')&&elvado_wp_bridge('media')){ $it=elvado_wp_cms_media_map()[$id]??null;$u=$it?elvado_wp_cms_media_url($it):null; }   // Schreibbrücke: Medien der CMS-Bibliothek
    return $u;
}

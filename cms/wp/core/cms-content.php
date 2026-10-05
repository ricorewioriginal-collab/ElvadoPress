<?php
// Brücke zwischen den CMS-Inhalten (Beiträge in news.json, eigene Seiten in site.json) und dem WordPress-Inhaltsmodell.
// CMS-Beiträge erscheinen als WP_Post vom Typ „post“, CMS-Seiten als „page“ – lesend, mit stabilen IDs:
//  Beiträge: ID = News-ID   Seiten: 20 000 000 + Hash   Kategorien 70 000 000 + Hash   Schlagwörter 71 000 000 + Hash   Anhänge 60 000 000 + Hash
//  Von WordPress-Code selbst angelegte Inhalte stehen in der Tabelle wp_posts (IDs ab 100 000 000).
const RRW_WP_ID_DB_MIN=100000000;
const RRW_WP_ID_PAGE_BASE=20000000;
const RRW_WP_ID_ATT_BASE=60000000;
const RRW_WP_ID_CAT_BASE=70000000;
const RRW_WP_ID_TAG_BASE=71000000;

function rrw_wp_cms_dir(): string { return defined('RRW_WP_CMS_DATA')?(string)RRW_WP_CMS_DATA:dirname(RRW_WP_DATA); }
function rrw_wp_cms_reset(): void { $GLOBALS['rrw_wp_cms_cache']=null; }
function rrw_wp_hash_id(string $s, int $base): int { return $base+(crc32($s)%900000); }
function rrw_wp_read_json(string $f, $fallback) { $r=is_file($f)?json_decode((string)@file_get_contents($f),true):null;return is_array($r)?$r:$fallback; }
function rrw_wp_cms_data(): array {
    if(isset($GLOBALS['rrw_wp_cms_cache']))return $GLOBALS['rrw_wp_cms_cache'];
    $dir=rrw_wp_cms_dir();$news=rrw_wp_read_json($dir.'/news.json',[]);$site=rrw_wp_read_json($dir.'/site.json',[]);
    $now=date('Y-m-d H:i:s');$live=[];
    foreach($news as $a){ if(!is_array($a)||($a['status']??'draft')!=='published'||!empty($a['deleted_at']))continue; $pa=trim((string)($a['published_at']??''));if($pa!==''&&$pa>$now)continue; $live[]=$a; }
    return $GLOBALS['rrw_wp_cms_cache']=['news'=>$live,'site'=>$site,'news_all'=>$news];
}
function rrw_wp_cms_author_id(string $name): int { return $name===''?1:(1+(crc32(mb_strtolower($name))%90000)); }
function rrw_wp_cms_term_slug(string $s): string { return sanitize_title($s); }
/** WordPress-Status eines CMS-Beitrags: Papierkorb (deleted_at) > Entwurf (ggf. wp_status pending/private) > geplant > veröffentlicht. */
function rrw_wp_cms_news_status(array $a, string $date): string {
    if(!empty($a['deleted_at']))return 'trash';
    if(($a['status']??'draft')!=='published'){ $w=(string)($a['wp_status']??'');return in_array($w,['pending','private'],true)?$w:'draft'; }
    return $date>date('Y-m-d H:i:s')?'future':'publish';
}
function rrw_wp_cms_news_post(array $a): WP_Post {
    $date=trim((string)($a['published_at']??$a['created_at']??date('Y-m-d H:i:s')));if(strlen($date)<19)$date=date('Y-m-d H:i:s',strtotime($date)?:time());
    $mod=trim((string)($a['updated_at']??$date));if(strlen($mod)<19)$mod=$date;
    $id=(int)($a['id']??0);$slug=(string)($a['slug']??'');
    $p=new WP_Post((object)['ID'=>$id,'post_author'=>(string)rrw_wp_cms_author_id((string)($a['author']??'')),'post_date'=>$date,'post_date_gmt'=>get_gmt_from_date($date)?:$date,'post_content'=>(string)($a['body_html']??''),'post_title'=>esc_html((string)($a['title']??'')),
        'post_excerpt'=>esc_html((string)($a['excerpt']??'')),'post_status'=>rrw_wp_cms_news_status($a,$date),'comment_status'=>'open','ping_status'=>'closed','post_name'=>$slug,'post_modified'=>$mod,'post_modified_gmt'=>get_gmt_from_date($mod)?:$mod,'post_parent'=>0,'guid'=>home_url('/#news/'.rawurlencode($slug)),'menu_order'=>0,'post_type'=>'post','filter'=>'raw']);
    $p->rrw_source='news';$p->rrw_data=$a;return $p;
}
/** Inhaltsblöcke einer CMS-Seite (html/text) in Anzeigereihenfolge: [Liste, Index, Block, Marker-ID]. */
function rrw_wp_cms_page_slots(array $pg): array {
    $o=[];
    foreach(['blocks_before','blocks_after'] as $k)foreach((array)($pg[$k]??[]) as $i=>$b){
        if(!is_array($b)||!in_array($b['type']??'',['html','text'],true))continue;
        $o[]=[$k,$i,$b,preg_replace('/[^A-Za-z0-9_-]/','',(string)($b['id']??''))?:$k.'-'.$i];
    }
    return $o;
}
function rrw_wp_cms_page_html(array $pg): string {
    $slots=rrw_wp_cms_page_slots($pg);
    $mark=count($slots)>1&&function_exists('rrw_wp_bridge')&&rrw_wp_bridge('pages')&&(is_admin()||wp_is_serving_rest_request());   // Block-Marker nur mit Schreibbrücke, bei mehrteiligen Seiten und nur für Editoren (Verwaltung, REST) – Besucher sehen unverändertes HTML
    $h='';foreach($slots as [,,$b,$mid]){
        $x=($b['type']??'')==='html'?(string)($b['html']??''):'<p>'.esc_html((string)($b['text']??'')).'</p>';
        $h.=$mark?'<!--rrw:block '.$mid.'-->'.$x.'<!--/rrw:block-->':$x;
    }
    if(($pg['intro']??'')!=='')$h='<p>'.esc_html((string)$pg['intro']).'</p>'.$h;
    if(($pg['type']??'')==='system')$h.='<p class="rrw-portal-link"><a class="wp-block-button__link wp-element-button button" href="'.esc_url(home_url('/#'.rawurlencode((string)($pg['system_target']??'')))).'">'.esc_html__('Interaktiv im Radioportal öffnen','default').'</a></p>';   // der Player und die Live-Daten laufen im Portal
    return $h;
}
function rrw_wp_cms_page_post(array $pg, string $status='publish'): WP_Post {
    $slug=(string)($pg['slug']??$pg['id']??'');$id=rrw_wp_hash_id((string)($pg['id']??$slug),RRW_WP_ID_PAGE_BASE);$d=date('Y-m-d H:i:s',(int)@filemtime(rrw_wp_cms_dir().'/site.json')?:time());
    $p=new WP_Post((object)['ID'=>$id,'post_author'=>'1','post_date'=>$d,'post_date_gmt'=>$d,'post_content'=>rrw_wp_cms_page_html($pg),'post_title'=>esc_html((string)($pg['headline']??'')!==''?(string)$pg['headline']:(string)($pg['title']??'')),'post_excerpt'=>'','post_status'=>$status,'comment_status'=>'closed','ping_status'=>'closed',
        'post_name'=>$slug,'post_modified'=>$d,'post_modified_gmt'=>$d,'post_parent'=>0,'guid'=>home_url('/'.$slug.'.html'),'menu_order'=>0,'post_type'=>'page','filter'=>'raw']);
    $p->rrw_source='page';$p->rrw_data=$pg;return $p;
}
/** Alle CMS-Beiträge (live) als WP_Post, neueste zuerst. */
function rrw_wp_cms_posts(): array {
    rrw_wp_cms_data();
    if(!isset($GLOBALS['rrw_wp_cms_cache']['posts'])){
        $out=array_map('rrw_wp_cms_news_post',$GLOBALS['rrw_wp_cms_cache']['news']);
        usort($out,fn($a,$b)=>strcmp($b->post_date,$a->post_date)?:($b->ID<=>$a->ID));
        $GLOBALS['rrw_wp_cms_cache']['posts']=$out;
    }
    return $GLOBALS['rrw_wp_cms_cache']['posts'];
}
/** Eigene (aktivierte) CMS-Seiten als WP_Post. */
function rrw_wp_cms_pages(): array {
    $d=rrw_wp_cms_data();$out=[];
    foreach((array)($d['site']['pages']??[]) as $pg){ if(!is_array($pg)||($pg['type']??'')!=='custom'||empty($pg['enabled']))continue; $out[]=rrw_wp_cms_page_post($pg); }
    // Portal-Bereiche (Sender, Sendeplan, Voting, Podcast, Hilfe, Apps, Shops) als Seiten des Themes – gleiche Texte wie die statischen Seiten, aber im gewählten Theme
    $have=array_column($out,'post_name');
    foreach((array)($d['site']['pages']??[]) as $pg){
        if(!is_array($pg)||($pg['type']??'')!=='system'||empty($pg['enabled']))continue;
        $slug=rrw_wp_system_slug((string)($pg['system_target']??''));if($slug===''||in_array($slug,$have,true))continue;
        $out[]=rrw_wp_cms_page_post(['slug'=>$slug,'id'=>'system-'.$slug]+$pg);$have[]=$slug;
    }
    return $out;
}
/** Seiten-Adresse eines Portal-Bereichs im Theme („sender“ → /sender/); '' für Bereiche ohne eigene Seite (Start, News, Detailseiten, Aktionen). */
function rrw_wp_system_slug(string $target): string {
    static $map=null;if($map===null){ $f=dirname(__DIR__,2).'/lib/seo.php';$map=[];if(is_file($f)){ if(!function_exists('rrw_seo_system_map'))require_once $f;$map=rrw_seo_system_map(); } }
    $s=(string)($map[$target]??'');return in_array($target,['start','news','senderdetail'],true)||!preg_match('/^[a-z0-9_-]+$/',$s)?'':$s;
}
function rrw_wp_cms_find_post(int $id): ?WP_Post {
    if($id<=0||$id>=RRW_WP_ID_DB_MIN)return null;
    if($id>=RRW_WP_ID_PAGE_BASE&&$id<RRW_WP_ID_PAGE_BASE+900000){ foreach(rrw_wp_cms_pages() as $p)if($p->ID===$id)return $p;return rrw_wp_cms_find_any($id); }
    if($id<10000000){ foreach(rrw_wp_cms_posts() as $p)if($p->ID===$id)return $p;return rrw_wp_cms_find_any($id); }
    if($id>=RRW_WP_ID_ATT_BASE&&$id<RRW_WP_ID_ATT_BASE+900000&&function_exists('rrw_wp_cms_media_post')&&rrw_wp_bridge('media'))return rrw_wp_cms_media_post($id);
    return null;
}
/* Mit eingeschalteter Schreibbrücke (cms-write.php) sind auch Entwürfe, geplante und gelöschte CMS-Beiträge sowie deaktivierte Seiten per ID erreichbar. */
function rrw_wp_cms_id_is_news(int $id): bool { return $id>0&&$id<10000000; }
function rrw_wp_cms_id_is_page(int $id): bool { return $id>=RRW_WP_ID_PAGE_BASE&&$id<RRW_WP_ID_PAGE_BASE+900000; }
/** Alle CMS-Beiträge unabhängig vom Status (auch Entwurf, geplant, Papierkorb), neueste zuerst. */
function rrw_wp_cms_posts_any(): array {
    rrw_wp_cms_data();
    if(!isset($GLOBALS['rrw_wp_cms_cache']['posts_any'])){
        $out=[];foreach((array)$GLOBALS['rrw_wp_cms_cache']['news_all'] as $a)if(is_array($a)&&(int)($a['id']??0)>0)$out[]=rrw_wp_cms_news_post($a);
        usort($out,fn($a,$b)=>strcmp($b->post_date,$a->post_date)?:($b->ID<=>$a->ID));
        $GLOBALS['rrw_wp_cms_cache']['posts_any']=$out;
    }
    return $GLOBALS['rrw_wp_cms_cache']['posts_any'];
}
/** Alle eigenen CMS-Seiten (auch deaktivierte = Entwurf). */
function rrw_wp_cms_pages_any(): array {
    $d=rrw_wp_cms_data();$out=[];
    foreach((array)($d['site']['pages']??[]) as $pg){ if(is_array($pg)&&($pg['type']??'')==='custom')$out[]=rrw_wp_cms_page_post($pg,!empty($pg['enabled'])?'publish':'draft'); }
    return $out;
}
function rrw_wp_cms_find_any(int $id): ?WP_Post {
    if(!function_exists('rrw_wp_bridge'))return null;
    if(rrw_wp_cms_id_is_news($id)&&rrw_wp_bridge('posts')){ foreach(rrw_wp_cms_posts_any() as $p)if($p->ID===$id)return $p; }
    elseif(rrw_wp_cms_id_is_page($id)&&rrw_wp_bridge('pages')){ foreach(rrw_wp_cms_pages_any() as $p)if($p->ID===$id)return $p; }
    return null;
}
function rrw_wp_is_cms_id(int $id): bool { return $id>0&&$id<RRW_WP_ID_DB_MIN; }

/* Virtuelle Begriffe aus den CMS-Beiträgen: Kategorie (ein Name je Beitrag) und Schlagwörter (kommagetrennt) */
function rrw_wp_cms_term(string $name, string $taxonomy): WP_Term {
    $slug=rrw_wp_cms_term_slug($name);$base=$taxonomy==='category'?RRW_WP_ID_CAT_BASE:RRW_WP_ID_TAG_BASE;$id=rrw_wp_hash_id($taxonomy.':'.$slug,$base);
    return new WP_Term((object)['term_id'=>$id,'name'=>$name,'slug'=>$slug,'term_group'=>0,'term_taxonomy_id'=>$id,'taxonomy'=>$taxonomy,'description'=>'','parent'=>0,'count'=>0,'filter'=>'raw']);
}
function rrw_wp_cms_terms(string $taxonomy): array {
    $d=rrw_wp_cms_data();$map=[];
    foreach($d['news'] as $a){
        $names=$taxonomy==='category'?[trim((string)($a['category']??''))]:array_filter(array_map('trim',explode(',',(string)($a['tags']??''))));
        foreach($names as $n){ if($n==='')continue; $t=rrw_wp_cms_term($n,$taxonomy); if(!isset($map[$t->slug]))$map[$t->slug]=$t; $map[$t->slug]->count++; }
    }
    ksort($map);return array_values($map);
}
function rrw_wp_cms_post_terms(WP_Post $p, string $taxonomy): array {
    if($p->rrw_source!=='news')return [];$a=$p->rrw_data??[];
    $names=$taxonomy==='category'?[trim((string)($a['category']??''))]:array_filter(array_map('trim',explode(',',(string)($a['tags']??''))));
    $out=[];foreach($names as $n)if($n!=='')$out[]=rrw_wp_cms_term($n,$taxonomy);
    return $out;
}
/** Titelbild: virtueller Anhang pro Bild-URL */
function rrw_wp_cms_attachment_id(string $url): int { $id=rrw_wp_hash_id('att:'.$url,RRW_WP_ID_ATT_BASE);$GLOBALS['rrw_wp_cms_attachments'][$id]=$url;return $id; }
function rrw_wp_cms_attachment_url(int $id): ?string {
    $u=$GLOBALS['rrw_wp_cms_attachments'][$id]??null;
    if($u===null&&$id>=RRW_WP_ID_ATT_BASE&&$id<RRW_WP_ID_ATT_BASE+900000&&function_exists('rrw_wp_cms_media_map')&&rrw_wp_bridge('media')){ $it=rrw_wp_cms_media_map()[$id]??null;$u=$it?rrw_wp_cms_media_url($it):null; }   // Schreibbrücke: Medien der CMS-Bibliothek
    return $u;
}

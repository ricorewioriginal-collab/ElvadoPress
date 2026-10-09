<?php
// Link-Formen der Website (CMS → Design → Link-Struktur): Beitrags-Adressen (einfach ?p=123, Datum, Beitragsname, eigene Struktur mit Platzhaltern),
// Kategorie-/Schlagwort-Basis und Hash-Form (/#/seite). Gespeichert werden nur WordPress-Optionen (permalink_structure, category_base, tag_base, elvado_link_hash);
// CMS-Daten (site.json, news.json) bleiben unberührt. Aufruf aus cms/api.php (wp_permalinks, wp_permalinks_save).

const ELVADO_WPL_PRESETS=[
    'plain'=>['label'=>'Einfach','structure'=>'','example'=>'/?p=123'],
    'day'=>['label'=>'Tag und Name','structure'=>'/%year%/%monthnum%/%day%/%postname%/','example'=>'/2026/10/05/beispiel-beitrag/'],
    'month'=>['label'=>'Monat und Name','structure'=>'/%year%/%monthnum%/%postname%/','example'=>'/2026/10/beispiel-beitrag/'],
    'numeric'=>['label'=>'Numerisch','structure'=>'/archives/%post_id%','example'=>'/archives/123'],
    'post'=>['label'=>'Beitragsname','structure'=>'/%postname%/','example'=>'/beispiel-beitrag/'],
];
const ELVADO_WPL_RESERVED=['wp-admin','wp-content','wp-includes','wp-json','cms','admin','feed','page','search','author','img','assets','api'];

/** Aktuelle Einstellung mit Beispiel-Adressen. */
function elvado_wpl_get(): array {
    $st=elvado_wp_link_structure();$preset='custom';
    foreach(ELVADO_WPL_PRESETS as $k=>$p)if($p['structure']===$st){ $preset=$k;break; }
    $sample='';$posts=elvado_wp_cms_posts();if($posts)$sample=(string)get_permalink($posts[0]);
    return ['structure'=>$st,'preset'=>$preset,'presets'=>ELVADO_WPL_PRESETS,'hash'=>elvado_wp_link_hash(),'category_base'=>(string)get_option('category_base',''),'tag_base'=>(string)get_option('tag_base',''),'sample'=>$sample,
        'tokens'=>array_keys(ELVADO_WP_LINK_TOKENS)];
}
/** Struktur prüfen. Rückgabe Fehlertext oder null. */
function elvado_wpl_check_structure(string $st): ?string {
    if($st==='')return null;   // einfach
    if(strlen($st)>200||!preg_match('~^/[A-Za-z0-9%_\-/.]*$~',$st)||str_contains($st,'..')||str_contains($st,'//'))return 'Die Struktur darf nur Buchstaben, Ziffern, - _ . / und Platzhalter wie %postname% enthalten und muss mit / beginnen.';
    preg_match_all('/%[a-z_]+%/',$st,$m);foreach($m[0] as $tok)if(!isset(ELVADO_WP_LINK_TOKENS[$tok]))return 'Unbekannter Platzhalter '.$tok.'.';
    if(str_contains(preg_replace('/%[a-z_]+%/','',$st),'%'))return 'Ungültige Platzhalter in der Struktur.';
    if(!str_contains($st,'%postname%')&&!str_contains($st,'%post_id%'))return 'Die Struktur braucht %postname% oder %post_id%, damit jeder Beitrag eine eigene Adresse hat.';
    $first=explode('/',trim($st,'/'))[0];if(in_array(strtolower($first),ELVADO_WPL_RESERVED,true))return '„'.$first.'“ ist als erster Teil der Adresse reserviert.';
    return null;
}
function elvado_wpl_check_base(string $b, string $what): ?string {
    if($b==='')return null;
    if(strlen($b)>40||!preg_match('~^[a-z0-9][a-z0-9_\-/]*$~',$b)||str_contains($b,'//')||str_ends_with($b,'/'))return $what.': nur Kleinbuchstaben, Ziffern, - _ und / (ohne Schrägstrich am Ende).';
    if(in_array(explode('/',$b)[0],ELVADO_WPL_RESERVED,true))return $what.': „'.explode('/',$b)[0].'“ ist reserviert.';
    return null;
}
/** Speichern. Rückgabe ['ok'=>true]+elvado_wpl_get() oder ['ok'=>false,'message'=>…]. */
function elvado_wpl_save(array $in): array {
    $st=(string)($in['structure']??'/%postname%/');$st=trim($st)==='' ?'':trim($st);
    if(isset($in['preset'])&&isset(ELVADO_WPL_PRESETS[(string)$in['preset']]))$st=ELVADO_WPL_PRESETS[(string)$in['preset']]['structure'];
    if($st!==''&&!str_ends_with($st,'/')&&!str_contains(basename($st),'%post_id%'))$st.='/';   // schöne Adressen enden mit /
    $cb=trim((string)($in['category_base']??''),'/ ');$tb=trim((string)($in['tag_base']??''),'/ ');
    foreach([elvado_wpl_check_structure($st),elvado_wpl_check_base($cb,'Kategorie-Basis'),elvado_wpl_check_base($tb,'Schlagwort-Basis')] as $e)if($e!==null)return ['ok'=>false,'message'=>$e];
    if($cb!==''&&$cb===$tb)return ['ok'=>false,'message'=>'Kategorie- und Schlagwort-Basis müssen verschieden sein.'];
    update_option('permalink_structure',$st);update_option('category_base',$cb);update_option('tag_base',$tb);update_option('elvado_link_hash',!empty($in['hash'])?1:0);
    return ['ok'=>true]+elvado_wpl_get();
}

/* ───────── Startseite und Beitragsseite (Einstellungen → Lesen) ───────── */
function elvado_wpl_reading_get(): array {
    $pages=[];foreach(get_pages(['sort_column'=>'post_title'])?:[] as $p)$pages[]=['id'=>(int)$p->ID,'title'=>html_entity_decode((string)$p->post_title,ENT_QUOTES,'UTF-8')?:(string)$p->post_name];
    $mode=get_option('show_on_front')==='page'?'page':'posts';
    return ['show_on_front'=>$mode,'page_on_front'=>(int)get_option('page_on_front'),'page_for_posts'=>(int)get_option('page_for_posts'),'pages'=>$pages];
}
function elvado_wpl_reading_save(array $in): array {
    $mode=(string)($in['show_on_front']??'posts');if(!in_array($mode,['posts','page'],true))return ['ok'=>false,'message'=>'Ungültige Auswahl.'];
    $front=(int)($in['page_on_front']??0);$posts=(int)($in['page_for_posts']??0);$ids=array_column(elvado_wpl_reading_get()['pages'],'id');
    if($mode==='page'){
        if(!$front||!in_array($front,$ids,true))return ['ok'=>false,'message'=>'Bitte eine Startseite wählen.'];
        if($posts&&!in_array($posts,$ids,true))return ['ok'=>false,'message'=>'Diese Beitragsseite gibt es nicht.'];
        if($posts&&$posts===$front)return ['ok'=>false,'message'=>'Startseite und Beitragsseite müssen verschieden sein.'];
    }else{ $front=(int)get_option('page_on_front');$posts=(int)get_option('page_for_posts'); }   // gewählte Seiten bleiben gemerkt
    update_option('show_on_front',$mode);update_option('page_on_front',$front);update_option('page_for_posts',$posts);
    return ['ok'=>true]+elvado_wpl_reading_get();
}

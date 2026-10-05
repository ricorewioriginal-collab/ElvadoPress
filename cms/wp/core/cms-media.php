<?php
// Medien: Die CMS-Medienbibliothek (cms/media, Ordner library/ u. a.) erscheint mit eingeschalteter Schreibbrücke („media“) als Anhänge
// (post_type attachment, IDs 60 000 000 + Hash der Bild-Adresse – dieselben wie beim Titelbild). Uploads über WordPress
// (media_handle_upload, REST /wp/v2/media) werden über rrw_media_library_store() in dieselbe Bibliothek gelegt, es gibt keine zweite Kopie
// in wp-content/uploads und keine Zeile in wp_posts.

function rrw_wp_cms_media_url(array $it): string { return (string)($it['original']['url']??$it['url']??''); }
/** Alle Medien der Bibliothek als [Anhang-ID => Eintrag] (einmal je Anfrage). */
function rrw_wp_cms_media_map(): array {
    if(isset($GLOBALS['rrw_wp_cms_media_map']))return $GLOBALS['rrw_wp_cms_media_map'];
    $map=[];
    if(rrw_wp_bridge('media')){
        rrw_wp_cms_lib();
        foreach(rrw_media_library_items(rrw_wp_cms_data()['site']) as $it){ $u=rrw_wp_cms_media_url($it);if($u==='')continue;$id=rrw_wp_cms_attachment_id($u);$map[$id]=$it; }
    }
    return $GLOBALS['rrw_wp_cms_media_map']=$map;
}
function rrw_wp_cms_media_reset(): void { unset($GLOBALS['rrw_wp_cms_media_map']); }
function rrw_wp_cms_media_post(int $id): ?WP_Post {
    $it=rrw_wp_cms_media_map()[$id]??null;if(!$it)return null;
    $url=rrw_wp_cms_media_url($it);$title=(string)pathinfo((string)($it['name']??basename($url)),PATHINFO_FILENAME);$d=date('Y-m-d H:i:s',(int)($it['mtime']??time()));
    $p=new WP_Post((object)['ID'=>$id,'post_author'=>'1','post_date'=>$d,'post_date_gmt'=>$d,'post_content'=>'','post_title'=>esc_html($title),'post_excerpt'=>esc_html((string)($it['credit']['text']??'')),'post_status'=>'inherit','comment_status'=>'closed','ping_status'=>'closed',
        'post_name'=>sanitize_title($title),'post_modified'=>$d,'post_modified_gmt'=>$d,'post_parent'=>0,'guid'=>home_url($url),'menu_order'=>0,'post_type'=>'attachment','post_mime_type'=>(string)($it['mime']??''),'filter'=>'raw']);
    $p->rrw_source='media';$p->rrw_data=$it;return $p;
}
function rrw_wp_cms_media_posts(): array {
    if(!rrw_wp_bridge('media'))return [];
    $out=[];foreach(rrw_wp_cms_media_map() as $id=>$it)if($p=rrw_wp_cms_media_post($id))$out[]=$p;return $out;
}
/** Breite je WordPress-Bildgröße (Namen oder [Breite,Höhe]); 'full' = Original. */
function rrw_wp_cms_media_target_width($size): int {
    if(is_array($size))return max(1,(int)($size[0]??0));
    return ['thumbnail'=>150,'medium'=>300,'medium_large'=>768,'large'=>1024,'post-thumbnail'=>1200][(string)$size]??0;
}
/** wp_get_attachment_image_src für CMS-Medien: kleinste Variante, die mindestens die Zielbreite hat. null = kein CMS-Medium. */
function rrw_wp_cms_media_image(int $id, $size) {
    if($id<RRW_WP_ID_ATT_BASE||$id>=RRW_WP_ID_ATT_BASE+900000||!rrw_wp_bridge('media'))return null;
    $it=rrw_wp_cms_media_map()[$id]??null;if(!$it)return null;
    $w=(int)($it['width']??0);$h=(int)($it['height']??0);$url=rrw_wp_cms_media_url($it);$tw=rrw_wp_cms_media_target_width($size);
    if($tw>0&&$w>0&&$tw<$w){
        $best=null;foreach((array)($it['variants']??[]) as $v){ $vw=(int)($v['width']??0);if($vw>=$tw&&($best===null||$vw<(int)$best['width']))$best=$v; }
        if($best){ $bw=(int)$best['width'];return [(string)$best['url'],$bw,$h>0?(int)round($h*$bw/$w):0,true]; }
    }
    return [$url,$w,$h,false];
}
function rrw_wp_cms_media_meta(int $id) {
    if($id<RRW_WP_ID_ATT_BASE||$id>=RRW_WP_ID_ATT_BASE+900000||!rrw_wp_bridge('media'))return null;
    $it=rrw_wp_cms_media_map()[$id]??null;if(!$it)return null;
    $w=(int)($it['width']??0);$h=(int)($it['height']??0);$sizes=[];
    foreach((array)($it['variants']??[]) as $v){ $vw=(int)($v['width']??0);$sizes['w'.$vw]=['file'=>basename((string)($v['url']??'')),'width'=>$vw,'height'=>$w>0?(int)round($h*$vw/$w):0,'mime-type'=>'image/webp','source_url'=>(string)($v['url']??'')]; }
    return ['width'=>$w,'height'=>$h,'file'=>(string)($it['original']['path']??$it['path']??''),'filesize'=>(int)($it['original']['size']??$it['size']??0),'sizes'=>$sizes,'image_meta'=>[]];
}
function rrw_wp_cms_media_file(int $id) {
    if($id<RRW_WP_ID_ATT_BASE||$id>=RRW_WP_ID_ATT_BASE+900000||!rrw_wp_bridge('media'))return null;
    $it=rrw_wp_cms_media_map()[$id]??null;if(!$it)return null;
    rrw_wp_cms_lib();$rel=(string)($it['original']['path']??$it['path']??'');return $rel!==''?rrw_media_dir().'/'.$rel:null;
}

/**
 * Datei in die CMS-Medienbibliothek legen. $file = Eintrag aus $_FILES. Nur echte Uploads (is_uploaded_file) – außer $overrides['rrw_mover']='rename'
 * für Skripte/Tests. Rückgabe: Anhang-ID oder WP_Error.
 */
function rrw_wp_cms_media_store(array $file, array $overrides=[]) {
    rrw_wp_cms_lib();
    $tmp=(string)($file['tmp_name']??'');
    $mover=$tmp!==''&&is_uploaded_file($tmp)?'move_uploaded_file':(($overrides['rrw_mover']??'')==='rename'?'rename':'');
    if($mover==='')return new WP_Error('upload_error','Keine hochgeladene Datei.',['status'=>400]);
    if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK)return new WP_Error('upload_error','Der Upload ist fehlgeschlagen.',['status'=>400]);
    try{ $r=rrw_media_library_store($file,rrw_media_sizes('64,128,192,256,512,1024,1600'),86,$mover); }
    catch(RuntimeException $e){ return new WP_Error('upload_error',$e->getMessage(),['status'=>$e->getCode()>=400?$e->getCode():400]); }
    rrw_wp_cms_media_reset();
    $url=(string)($r['item']['original']['url']??'');$id=rrw_wp_cms_attachment_id($url);
    rrw_wp_cms_log('media_upload','Medium „'.(string)($r['item']['name']??'').'“ über WordPress hochgeladen');
    do_action('add_attachment',$id);
    return $id;
}
function rrw_wp_cms_media_handle_upload(string $file_id, int $post_id, array $post_data, array $overrides) {
    $f=$_FILES[$file_id]??null;if(!is_array($f))return new WP_Error('upload_error','Keine Datei übermittelt.');
    return rrw_wp_cms_media_store($f,$overrides);
}
/** REST-Antwort für ein CMS-Medium (wie /wp/v2/media). */
function rrw_wp_cms_media_rest(int $id): array {
    $p=rrw_wp_cms_media_post($id);$it=$p?$p->rrw_data:[];$m=rrw_wp_cms_media_meta($id)?:[];$mime=(string)($it['mime']??'');
    return ['id'=>$id,'date'=>$p?str_replace(' ','T',$p->post_date):'','slug'=>$p?$p->post_name:'','type'=>'attachment','link'=>$p?$p->guid:'','title'=>['rendered'=>$p?$p->post_title:''],'author'=>1,'status'=>'inherit',
        'media_type'=>str_starts_with($mime,'image/')?'image':'file','mime_type'=>$mime,'media_details'=>$m,'source_url'=>rrw_wp_cms_media_url($it),'alt_text'=>''];
}

<?php
/* Baukasten-Layout: Schema der Abschnittstypen, Bereinigung und Speicherung (Option elvado_bk_layout).
   Wird vom Theme und von der CMS-Verwaltung (cms/api.php, Aktionen wp_bk_*) gemeinsam genutzt. */
if(!defined('ABSPATH'))exit;

function elvado_bk_schema(): array {
    $bg=['k'=>'bg','label'=>'Hintergrund','type'=>'select','options'=>['default'=>'Standard','alt'=>'Fläche (Karte)','accent'=>'Akzentfarbe','dark'=>'Dunkel'],'default'=>'default'];
    $al=['k'=>'align','label'=>'Ausrichtung','type'=>'select','options'=>['left'=>'Links','center'=>'Zentriert'],'default'=>'left'];
    return [
        'hero'=>['label'=>'Hero (Kopfbild)','icon'=>'fa-image','fields'=>[
            ['k'=>'title','label'=>'Überschrift (leer = Website-Titel)','type'=>'text'],['k'=>'text','label'=>'Text (leer = Untertitel)','type'=>'textarea'],
            ['k'=>'image','label'=>'Hintergrundbild','type'=>'image'],['k'=>'image_alt','label'=>'Bildbeschreibung (Alt-Text, leer = dekorativ)','type'=>'text'],['k'=>'overlay','label'=>'Bild abdunkeln','type'=>'checkbox','default'=>true],
            ['k'=>'btn_label','label'=>'Button-Text','type'=>'text','default'=>'Mehr erfahren'],['k'=>'btn_url','label'=>'Button-Ziel','type'=>'url'],
            ['k'=>'height','label'=>'Mindesthöhe (px)','type'=>'number','min'=>0,'max'=>900,'default'=>0]]],
        'text'=>['label'=>'Textabschnitt','icon'=>'fa-paragraph','fields'=>[['k'=>'title','label'=>'Überschrift','type'=>'text'],['k'=>'body','label'=>'Inhalt (HTML erlaubt)','type'=>'textarea'],$al,$bg]],
        'features'=>['label'=>'Vorteile / Karten','icon'=>'fa-table-cells-large','fields'=>[['k'=>'title','label'=>'Überschrift','type'=>'text'],
            ['k'=>'items','label'=>'Karten','type'=>'items','max'=>6],['k'=>'columns','label'=>'Spalten','type'=>'select','options'=>['0'=>'Automatisch','2'=>'2','3'=>'3','4'=>'4'],'default'=>'0'],$bg]],
        'image_text'=>['label'=>'Bild + Text','icon'=>'fa-table-columns','fields'=>[['k'=>'title','label'=>'Überschrift','type'=>'text'],['k'=>'text','label'=>'Inhalt (HTML erlaubt)','type'=>'textarea'],
            ['k'=>'image','label'=>'Bild','type'=>'image'],['k'=>'image_alt','label'=>'Bildbeschreibung (Alt-Text, leer = dekorativ)','type'=>'text'],['k'=>'reverse','label'=>'Bild rechts','type'=>'checkbox'],['k'=>'btn_label','label'=>'Button-Text','type'=>'text'],['k'=>'btn_url','label'=>'Button-Ziel','type'=>'url'],$bg]],
        'posts'=>['label'=>'Neueste Beiträge','icon'=>'fa-newspaper','fields'=>[['k'=>'title','label'=>'Überschrift','type'=>'text','default'=>'Neueste Beiträge'],
            ['k'=>'count','label'=>'Anzahl','type'=>'number','min'=>1,'max'=>12,'default'=>3],['k'=>'category','label'=>'Nur Kategorie (Name/Slug, leer = alle)','type'=>'text'],['k'=>'all_label','label'=>'Button „alle Beiträge“','type'=>'text'],$bg]],
        'cta'=>['label'=>'Aufruf (Call to Action)','icon'=>'fa-bullhorn','fields'=>[['k'=>'title','label'=>'Überschrift','type'=>'text'],['k'=>'text','label'=>'Text','type'=>'text'],
            ['k'=>'btn_label','label'=>'Button-Text','type'=>'text'],['k'=>'btn_url','label'=>'Button-Ziel','type'=>'url'],['k'=>'bg','label'=>'Hintergrund','type'=>'select','options'=>$bg['options'],'default'=>'accent']]],
        'html'=>['label'=>'Eigenes HTML / Shortcodes','icon'=>'fa-code','fields'=>[['k'=>'code','label'=>'HTML oder Shortcodes','type'=>'textarea'],$bg]],
        'spacer'=>['label'=>'Abstand','icon'=>'fa-arrows-up-down','fields'=>[['k'=>'height','label'=>'Höhe (px)','type'=>'number','min'=>0,'max'=>400,'default'=>40]]],
    ];
}
function elvado_bk_default_props(string $type): array {
    $s=elvado_bk_schema();$o=[];foreach(($s[$type]['fields']??[]) as $f){ $o[$f['k']]=$f['default']??($f['type']==='checkbox'?false:($f['type']==='items'?[]:($f['type']==='number'?0:''))); }
    if($type==='features')$o['items']=[['title'=>'Schnell','text'=>'Kurze Ladezeiten ohne Ballast.'],['title'=>'Flexibel','text'=>'Alles lässt sich anpassen.'],['title'=>'Eigenständig','text'=>'Deine Inhalte, dein Design.']];
    return $o;
}
function elvado_bk_clean_props(string $type, $raw): array {
    $raw=is_array($raw)?$raw:[];$out=[];
    foreach((elvado_bk_schema()[$type]['fields']??[]) as $f){
        $k=$f['k'];$v=$raw[$k]??($f['default']??null);
        switch($f['type']){
            case 'text': $out[$k]=mb_substr(sanitize_text_field((string)$v),0,300);break;
            case 'textarea': $out[$k]=mb_substr(wp_kses_post((string)$v),0,20000);break;
            case 'url': case 'image': $u=trim((string)$v);$out[$k]=$u===''?'':mb_substr((string)esc_url_raw($u),0,1200);break;
            case 'checkbox': $out[$k]=filter_var($v,FILTER_VALIDATE_BOOLEAN);break;
            case 'number': $out[$k]=max((int)($f['min']??0),min((int)($f['max']??1000),(int)$v));break;
            case 'select': $v=(string)$v;$out[$k]=isset($f['options'][$v])?$v:(string)($f['default']??array_key_first($f['options']));break;
            case 'items': $it=[];foreach(is_array($v)?$v:[] as $x){ if(!is_array($x))continue;$t=mb_substr(sanitize_text_field((string)($x['title']??'')),0,120);$tx=mb_substr(sanitize_textarea_field((string)($x['text']??'')),0,600);if($t===''&&$tx==='')continue;$it[]=['title'=>$t,'text'=>$tx];if(count($it)>=(int)($f['max']??6))break; }$out[$k]=$it;break;
        }
    }
    return $out;
}
/** Liste von Abschnitten → bereinigte Liste (höchstens 40, IDs eindeutig). */
function elvado_bk_clean_layout($raw): array {
    $out=[];$ids=[];$schema=elvado_bk_schema();
    foreach(is_array($raw)?$raw:[] as $s){
        if(!is_array($s)||count($out)>=40)continue;$type=(string)($s['type']??'');if(!isset($schema[$type]))continue;
        $id=preg_replace('/[^a-z0-9_-]/','',strtolower((string)($s['id']??'')));if($id===''||strlen($id)>24||isset($ids[$id]))$id='s'.substr(md5(uniqid('',true).count($out)),0,8);
        $ids[$id]=1;$out[]=['id'=>$id,'type'=>$type,'hidden'=>!empty($s['hidden']),'props'=>elvado_bk_clean_props($type,$s['props']??[])];
    }
    return $out;
}
/** Gespeichertes Layout; null = nicht gesetzt (dann gelten die Positionen aus dem Customizer). */
function elvado_bk_saved_layout(): ?array { $l=get_option('elvado_bk_layout',null);return is_array($l)?$l:null; }
function elvado_bk_save_layout($raw): array { $l=elvado_bk_clean_layout($raw);update_option('elvado_bk_layout',$l,false);return $l; }
/** Layout aus den Customizer-Positionen (Altverhalten, Vorgabe für den Editor). */
function elvado_bk_layout_from_mods(): array {
    $m='elvado_bk_mod';$out=[];$map=['hero'=>['title'=>'hero_title','text'=>'hero_text','image'=>'hero_image','overlay'=>'hero_overlay','btn_label'=>'hero_btn_label','btn_url'=>'hero_btn_url'],
        'text'=>['title'=>'text_title','body'=>'text_body'],'image_text'=>['title'=>'split_title','text'=>'split_text','image'=>'split_image','reverse'=>'split_reverse'],
        'posts'=>['title'=>'posts_title','count'=>'posts_count','all_label'=>'posts_all_label'],'cta'=>['title'=>'cta_title','text'=>'cta_text','btn_label'=>'cta_btn_label','btn_url'=>'cta_btn_url'],'html'=>['code'=>'html_code']];
    foreach(elvado_bk_slots() as $t){
        $p=elvado_bk_default_props($t);
        if($t==='features'){ $p['title']=(string)$m('feat_title');$p['items']=[];for($i=1;$i<=3;$i++)$p['items'][]=['title'=>(string)$m('feat_'.$i.'_title'),'text'=>(string)$m('feat_'.$i.'_text')]; }
        foreach($map[$t]??[] as $k=>$mod)$p[$k]=$m($mod);
        if($t==='text')$p['align']=$m('text_center')?'center':'left';
        $out[]=['id'=>'m'.count($out),'type'=>$t,'hidden'=>false,'props'=>$p];
    }
    return elvado_bk_clean_layout($out)?:[];
}
function elvado_bk_active_layout(): array { $l=elvado_bk_saved_layout();return $l!==null?$l:elvado_bk_layout_from_mods(); }

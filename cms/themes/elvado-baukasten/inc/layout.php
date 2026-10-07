<?php
/* Baukasten-Layout: Schema der Abschnittstypen, Bereinigung und Speicherung (Option elvado_bk_layout).
   Wird vom Theme und von der CMS-Verwaltung (cms/api.php, Aktionen wp_bk_*) gemeinsam genutzt. */
if(!defined('ABSPATH'))exit;

/** Komponenten-Registry (nur die Kern-Komponenten; unabhängig von Erweiterungen). Das Schema der Abschnitte kommt von dort (eine Quelle für Theme und Verwaltung). */
function elvado_bk_registry(): \Elvado\Components\Registry {
    static $r=null;
    if($r===null){ require_once dirname(__DIR__,3).'/src/autoload.php';$r=new \Elvado\Components\Registry();\Elvado\Components\CoreComponents::register($r); }
    return $r;
}
const ELVADO_BK_TYPES=['hero','text','features','image_text','posts','cta','html','spacer'];
function elvado_bk_schema(): array { return elvado_bk_registry()->legacySchema(ELVADO_BK_TYPES); }
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
        $ids[$id]=1;$o=['id'=>$id,'type'=>$type,'hidden'=>!empty($s['hidden']),'props'=>elvado_bk_clean_props($type,$s['props']??[])];
        $comp=elvado_bk_registry()->get($type);$lay=new \Elvado\Components\Layout(elvado_bk_registry());
        $vis=\Elvado\Components\Layout::visibility($s['visibility']??[]);if($vis!==\Elvado\Components\Layout::visibility([]))$o['visibility']=$vis;   // nur wenn von der Vorgabe abweichend (bestehende Layouts bleiben unverändert)
        $resp=$comp?$lay->responsive($comp,$s['responsive']??[]):[];if($resp!==[])$o['responsive']=$resp;
        $out[]=$o;
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
/** Entwurf der Startseite (Live Builder): wird nur in der geprüften Vorschau ausgeliefert, nie auf der öffentlichen Seite. */
function elvado_bk_draft_layout(): ?array { $l=get_option('elvado_bk_layout_draft',null);return is_array($l)?$l:null; }
function elvado_bk_save_draft($raw): array { $l=elvado_bk_clean_layout($raw);update_option('elvado_bk_layout_draft',$l,false);return $l; }
function elvado_bk_discard_draft(): void { delete_option('elvado_bk_layout_draft'); }
function elvado_bk_active_layout(): array {
    if((string)($GLOBALS['rrw_wp_preview_theme']??'')==='elvado-baukasten'&&($d=elvado_bk_draft_layout())!==null)return $d;
    $l=elvado_bk_saved_layout();return $l!==null?$l:elvado_bk_layout_from_mods();
}

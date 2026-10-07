<?php
/* Baukasten-Layout: Schema der Abschnittstypen, Bereinigung und Speicherung (Option elvado_bk_layout).
   Wird vom Theme und von der CMS-Verwaltung (cms/api.php, Aktionen wp_bk_*) gemeinsam genutzt. */
if(!defined('ABSPATH'))exit;

/** Komponenten-Registry von ElvadoPress (Kern + aktive Erweiterungen). Das Schema der Abschnitte kommt von dort (eine Quelle für Theme und Verwaltung). */
function elvado_bk_registry(): \Elvado\Components\Registry {
    require_once dirname(__DIR__,3).'/lib/components.php';
    return rrw_components();
}
const ELVADO_BK_TYPES=['hero','text','features','image_text','posts','cta','html','spacer'];
/** WordPress-Komponenten, die dieses Theme selbst ausgibt (Shortcode, Block, Widget-Bereich). */
const ELVADO_BK_WP_TYPES=['widget_area','wp_shortcode','wp_block'];
/** Alle Komponenten, die das Theme ausgeben kann: die acht eigenen, alle nativen (ohne festen Platz wie Header/Footer) und die WordPress-Komponenten. @return list<string> */
function elvado_bk_supported(): array {
    $r=elvado_bk_registry();$feat=function_exists('rrw_components_features')?rrw_components_features(function_exists('rrw_wp_cms_dir')?rrw_wp_cms_dir():null):[];
    $ids=[];foreach(ELVADO_BK_TYPES as $t)if($r->has($t))$ids[]=$t;
    foreach($r->all() as $c){ if(in_array($c->id,$ids,true))continue;
        if($c->feature!==''&&!in_array($c->feature,$feat,true))continue;
        if(($c->hasRenderer()&&$c->rules['slot']==='')||in_array($c->id,ELVADO_BK_WP_TYPES,true))$ids[]=$c->id; }
    return $ids;
}
function elvado_bk_schema(): array { return elvado_bk_registry()->legacySchema(elvado_bk_supported()); }
/** Layout-Speicher (Entwurf, Veröffentlichen, Revisionen, Termin): Dateien unter cms/data/layouts, Bereich „home“. */
function elvado_bk_store(): \Elvado\Components\LayoutStore {
    require_once dirname(__DIR__,3).'/lib/components.php';
    return rrw_components_store(function_exists('rrw_wp_cms_dir')?rrw_wp_cms_dir():null);
}
function elvado_bk_actor(): \Elvado\Wp\Actor { return $GLOBALS['elvado_bk_actor']??new \Elvado\Wp\Actor('system','admin'); }
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
/** Liste von Abschnitten → bereinigte Liste (höchstens 120, IDs eindeutig). Die acht Baukasten-Abschnitte bereinigt das Theme mit den WordPress-Funktionen, alle anderen die Registry. */
function elvado_bk_clean_layout($raw): array {
    $out=[];$ids=[];$sup=array_flip(elvado_bk_supported());$bk=array_flip(ELVADO_BK_TYPES);
    foreach(is_array($raw)?$raw:[] as $s){
        if(!is_array($s)||count($out)>=\Elvado\Components\Layout::MAX_TOTAL)continue;$type=(string)($s['type']??'');if(!isset($sup[$type]))continue;
        if(isset($bk[$type])){
            $id=preg_replace('/[^a-z0-9_-]/','',strtolower((string)($s['id']??'')));if($id===''||strlen($id)>24||isset($ids[$id]))$id='s'.substr(md5(uniqid('',true).count($out)),0,8);
            $ids[$id]=1;$o=['id'=>$id,'type'=>$type,'hidden'=>!empty($s['hidden']),'props'=>elvado_bk_clean_props($type,$s['props']??[])];
            $comp=elvado_bk_registry()->get($type);$lay=new \Elvado\Components\Layout(elvado_bk_registry());
            $vis=\Elvado\Components\Layout::visibility($s['visibility']??[]);if($vis!==\Elvado\Components\Layout::visibility([]))$o['visibility']=$vis;   // nur wenn von der Vorgabe abweichend (bestehende Layouts bleiben unverändert)
            $resp=$comp?$lay->responsive($comp,$s['responsive']??[]):[];if($resp!==[])$o['responsive']=$resp;
            $out[]=$o;continue;
        }
        $one=(new \Elvado\Components\Layout(elvado_bk_registry()))->clean([$s],['admin'=>true,'unfiltered'=>false]);
        if(!$one||!empty($one[0]['missing']))continue;$o=elvado_bk_prune($one[0],$sup);
        if(isset($ids[$o['id']]))$o['id']='s'.substr(md5(uniqid('',true).count($out)),0,8);$ids[$o['id']]=1;$out[]=$o;
    }
    return $out;
}
/** Entfernt Kinder, die dieses Theme nicht ausgeben kann. @param array<string,int> $sup */
function elvado_bk_prune(array $inst,array $sup): array {
    if(isset($inst['children']))$inst['children']=array_values(array_filter(array_map(fn($c)=>elvado_bk_prune($c,$sup),$inst['children']),fn($c)=>isset($sup[$c['type']??''])&&empty($c['missing'])));
    return $inst;
}
/** Veröffentlichtes Layout; null = nicht gesetzt (dann gelten die Positionen aus dem Customizer). Ältere Installationen (Option elvado_bk_layout) werden weiter gelesen, bis das erste Mal über den Speicher veröffentlicht wird. */
function elvado_bk_saved_layout(): ?array {
    $p=elvado_bk_store()->published('home');if($p!==null)return $p;
    $l=get_option('elvado_bk_layout',null);return is_array($l)?$l:null;
}
/** Direkt veröffentlichen (Entwurf + Veröffentlichen in einem Schritt). */
function elvado_bk_save_layout($raw,string $label=''): array {
    $l=elvado_bk_clean_layout($raw);$st=elvado_bk_store();$a=elvado_bk_actor();
    $st->saveDraft('home',$l,$a,'',true);$st->publish('home',$a,$label);return $l;
}
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
function elvado_bk_draft_layout(): ?array { $d=elvado_bk_store()->get('home')['draft'];return $d!==null?(array)$d['layout']:null; }
function elvado_bk_save_draft($raw,string $publishAt=''): array { $l=elvado_bk_clean_layout($raw);elvado_bk_store()->saveDraft('home',$l,elvado_bk_actor(),$publishAt,true);return $l; }
function elvado_bk_discard_draft(): void { elvado_bk_store()->discard('home',elvado_bk_actor()); }
function elvado_bk_active_layout(): array {
    if((string)($GLOBALS['rrw_wp_preview_theme']??'')==='elvado-baukasten'&&($d=elvado_bk_draft_layout())!==null)return $d;
    $l=elvado_bk_saved_layout();return $l!==null?$l:elvado_bk_layout_from_mods();
}

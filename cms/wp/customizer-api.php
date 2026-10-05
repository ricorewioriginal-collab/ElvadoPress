<?php
// Live-Customizer für WordPress-Themes (CMS → Design → Themes → „Anpassen“): liefert die anpassbaren Einstellungen eines Themes
// (Kerneinstellungen + alles, was das Theme über customize_register anbietet), prüft/bereinigt Eingaben über die Steuerelement-Typen und die
// sanitize_callback des Themes und speichert sie als theme_mod bzw. Option. Entwürfe liegen nur für die Vorschau-Sitzung in cms/data/.wp/customize-drafts
// (15 Minuten gültig, nur zusammen mit einem gültigen Vorschau-Schlüssel wirksam) – gespeichert wird erst beim Veröffentlichen.
// Aufruf aus cms/api.php (wp_theme_customize*) und cms/wp-front.php (Entwurf in der Vorschau anwenden).

/** Das gewählte Theme für diese Anfrage einschalten (Filter vor rrw_wp_boot, wie in wp-front.php bei der Vorschau). false = unbekannt/defekt. */
function rrw_wpc_use_theme(string $slug): bool {
    if(!preg_match('/^[a-z0-9_-]{1,80}$/',$slug))return false;
    $parent=$slug;$found=false;
    foreach(array_map(fn($rt)=>$rt['dir'].'/'.$slug,rrw_wp_theme_roots()) as $d)if(is_file($d.'/style.css')){
        $found=true;$h=get_file_data($d.'/style.css',['Template'=>'Template']);
        if($h['Template']!==''&&preg_match('/^[a-z0-9_-]{1,80}$/',$h['Template']))$parent=$h['Template'];break;
    }
    if(!$found)return false;
    add_filter('pre_option_stylesheet',fn()=>$slug);add_filter('pre_option_template',fn()=>$parent);
    return true;
}

/** „name[a][b]“ → ['name',['a','b']]. */
function rrw_wpc_id_parts(string $id): array {
    if(preg_match('/^([^\[\]]+)((?:\[[^\[\]]+\])+)$/',$id,$m)){ preg_match_all('/\[([^\[\]]+)\]/',$m[2],$k);return [$m[1],$k[1]]; }
    return [$id,[]];
}
function rrw_wpc_nested_get($arr, array $keys, &$found=null) {
    foreach($keys as $k){ if(!is_array($arr)||!array_key_exists($k,$arr)){ $found=false;return null; } $arr=$arr[$k]; }
    $found=true;return $arr;
}
function rrw_wpc_nested_set($arr, array $keys, $v) {
    if(!$keys)return $v;$arr=is_array($arr)?$arr:[];$k=array_shift($keys);$arr[$k]=rrw_wpc_nested_set($arr[$k]??null,$keys,$v);return $arr;
}
/** Roh gespeicherter Wert der Wurzel (ohne Entwurfs-Filter). */
function rrw_wpc_root_raw(string $store, string $root) {
    if($store==='option'){ [$f,$v]=rrw_wp_opts_get_raw($root);return $f?$v:null; }
    $m=get_theme_mods();return $m[$root]??null;
}

const RRW_WPC_CORE=['blogname','blogdescription','custom_logo','site_icon','header_image','header_textcolor','background_color','background_image','nav_menu_locations'];
const RRW_WPC_TYPES=['text','textarea','checkbox','radio','select','dropdown-pages','color','range','number','url','email','date','image','tel','search'];

/** Beschriftung als reiner Text (Tags raus, &amp; usw. aufgelöst). */
function rrw_wpc_text(string $s): string { return trim(html_entity_decode(wp_strip_all_tags($s),ENT_QUOTES,'UTF-8')); }
/** Steuerelement-Typ für die Oberfläche; Themes ersetzen Kern-Steuerelemente oft durch eigene Klassen → nach Klasse/Namen zuordnen. '' = nicht bearbeitbar. */
function rrw_wpc_type(WP_Customize_Control $c, string $id): string {
    $t=(string)$c->type;
    if($c instanceof WP_Customize_Cropped_Image_Control||$c instanceof WP_Customize_Media_Control)return '';   // speichern Medien-IDs → braucht die Mediathek
    if(in_array($t,RRW_WPC_TYPES,true))return $t;
    if(in_array($id,['background_color','header_textcolor'],true))return 'color';
    if($c instanceof WP_Customize_Color_Control||preg_match('/colou?r/i',$t))return 'color';
    if($c instanceof WP_Customize_Image_Control||preg_match('/image|upload/i',$t))return 'image';
    if(preg_match('/toggle|switch|checkbox/i',$t))return 'checkbox';
    if(preg_match('/range|slider/i',$t))return 'range';
    if(preg_match('/textarea/i',$t))return 'textarea';
    if(preg_match('/select|radio|dropdown/i',$t)&&$c->choices)return 'select';
    return '';
}

/** Alle bearbeitbaren Einstellungen des (per rrw_wpc_use_theme gewählten) Themes, nach ID. Ergebnis wird je Anfrage gemerkt. */
function rrw_wpc_items(): array {
    static $cache=null;if($cache!==null)return $cache;
    $m=rrw_wp_customizer();$out=[];$unsupported=0;
    $bgSup=get_theme_support('custom-background');$hdSup=get_theme_support('custom-header');
    $bgDef=is_array($bgSup)?(string)($bgSup[0]['default-color']??''):'';$hdDef=is_array($hdSup)?(string)($hdSup[0]['default-text-color']??''):'';
    $coreOk=['blogname'=>true,'blogdescription'=>true,'background_color'=>(bool)$bgSup,'background_image'=>(bool)$bgSup,'header_image'=>(bool)$hdSup,'header_textcolor'=>$hdSup&&(!is_array($hdSup)||($hdSup[0]['header-text']??true))];
    foreach($m->controls as $c){
        $s=$c->setting_obj();if(!$s)continue;$id=(string)$s->id;
        if(in_array($id,['custom_logo','site_icon'],true)||str_starts_with($id,'nav_menu')||$c->type==='hidden')continue;   // Logo/Icon pflegt das CMS (Branding), Menüs unten
        if(in_array($id,RRW_WPC_CORE,true)&&empty($coreOk[$id]))continue;
        $type=rrw_wpc_type($c,$id);
        if($type===''){ $unsupported++;continue; }
        [$root,$keys]=rrw_wpc_id_parts($id);$store=$s->type==='option'?'option':'theme_mod';
        $choices=[];foreach((array)$c->choices as $k=>$t)$choices[]=[(string)$k,is_array($t)?(string)($t['label']??$k):(string)$t];
        if($type==='dropdown-pages'){ $choices=[['0','— Seite wählen —']];foreach(get_pages()?:[] as $p)$choices[]=[(string)$p->ID,(string)$p->post_title]; }
        if(in_array($type,['select','radio'],true)&&!$choices){ $unsupported++;continue; }
        $ia=(array)$c->input_attrs;
        $def=$s->default;if($id==='background_color'&&$def===''||$id==='background_color'&&$def==='ffffff')$def=$bgDef!==''?$bgDef:$def;if($id==='header_textcolor'&&($def===''||$def==='#000000'))$def=$hdDef!==''?$hdDef:$def;
        $ent=['id'=>$id,'type'=>in_array($type,['url','email','tel','search','date'],true)?'text':$type,'input'=>$type,'label'=>rrw_wpc_text((string)$c->label)?:ucfirst(trim(preg_replace('/[_\-\[\]]+/',' ',$id))),
            'description'=>rrw_wpc_text((string)$c->description),'section'=>(string)$c->section,'priority'=>(int)$c->priority,'store'=>$store,'root'=>$root,'keys'=>$keys,'setting'=>$s,
            'choices'=>$choices,'min'=>isset($ia['min'])?(float)$ia['min']:null,'max'=>isset($ia['max'])?(float)$ia['max']:null,'step'=>isset($ia['step'])?(float)$ia['step']:null,'default'=>$def,'transport'=>(string)$s->transport];
        if($type==='image'&&ctype_digit((string)rrw_wpc_current($ent))){ $unsupported++;continue; }   // Medien-ID statt Adresse
        $out[$id]=$ent;
    }
    // Menü-Standorte: je Standort ein Auswahlfeld (gespeichert in theme_mod nav_menu_locations[<standort>])
    $locs=get_registered_nav_menus();
    if($locs){
        $ch=[['','Kein Menü']];foreach((array)wp_get_nav_menus() as $mn)if($mn)$ch[]=[(string)$mn->slug,(string)$mn->name.' (CMS)'];
        foreach($locs as $loc=>$label){
            $id='nav_menu_locations['.$loc.']';
            $out[$id]=['id'=>$id,'type'=>'select','input'=>'select','label'=>rrw_wpc_text((string)$label),'description'=>'','section'=>'rrw_menus','priority'=>10,'store'=>'theme_mod','root'=>'nav_menu_locations','keys'=>[(string)$loc],'setting'=>null,
                'choices'=>$ch,'min'=>null,'max'=>null,'step'=>null,'default'=>'','transport'=>'refresh','menu_location'=>true];
        }
    }
    // Startseiten-Einstellungen und Zusätzliches CSS (wie im WordPress-Customizer)
    $pg=[['0','— Seite wählen —']];foreach(get_pages()?:[] as $p)$pg[]=[(string)$p->ID,(string)$p->post_title];
    $mk=fn(string $id,string $type,string $label,string $sec,int $prio,string $store,string $root,array $choices,$def,string $desc='')=>['id'=>$id,'type'=>$type,'input'=>$type,'label'=>$label,'description'=>$desc,'section'=>$sec,'priority'=>$prio,'store'=>$store,'root'=>$root,'keys'=>[],'setting'=>null,'choices'=>$choices,'min'=>null,'max'=>null,'step'=>null,'default'=>$def,'transport'=>'refresh'];
    $out['show_on_front']=$mk('show_on_front','radio','Deine Startseite zeigt','static_front_page',10,'option','show_on_front',[['posts','Deine neuesten Beiträge'],['page','Eine statische Seite']],'posts');
    $out['page_on_front']=$mk('page_on_front','dropdown-pages','Startseite','static_front_page',20,'option','page_on_front',$pg,'0');
    $out['page_for_posts']=$mk('page_for_posts','dropdown-pages','Beitragsseite','static_front_page',30,'option','page_for_posts',$pg,'0');
    $out['custom_css']=$mk('custom_css','textarea','CSS-Code','custom_css',10,'theme_mod','custom_css_post_id_text',[],'','Eigene Stile für dieses Theme; sie gelten zusätzlich zum Theme und überschreiben dessen Regeln.');
    $cache=['items'=>$out,'unsupported'=>$unsupported,'manager'=>$m];
    return $cache;
}

/** Aktuell gespeicherter Wert einer Einstellung (mit Standardwert). */
function rrw_wpc_current(array $it) {
    if(!empty($it['menu_location'])){ $l=get_nav_menu_locations();return (string)($l[$it['keys'][0]]??''); }
    $raw=rrw_wpc_root_raw($it['store'],$it['root']);
    if($it['keys']){ $v=rrw_wpc_nested_get($raw,$it['keys'],$f);return $f?$v:$it['default']; }
    if($raw===null){ return $it['store']==='option'?get_option($it['root'],$it['default']):$it['default']; }
    return $raw;
}
/** Wert für die Oberfläche: Farben als #rrggbb, Checkbox als bool. */
function rrw_wpc_display(array $it, $v) {
    if($it['type']==='checkbox')return (bool)$v;
    if(is_array($v)||is_object($v))return '';
    $v=(string)$v;
    if($it['type']==='color'){ if(preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i',$v,$mm)){ $h=strtolower($mm[1]);if(strlen($h)===3)$h=$h[0].$h[0].$h[1].$h[1].$h[2].$h[2];return '#'.$h; } return mb_substr($v,0,40); }
    return $v;
}

/** Beschreibung für die Oberfläche: Abschnitte mit Steuerelementen. */
function rrw_wpc_describe(string $slug): array {
    $r=rrw_wpc_items();$m=$r['manager'];$secs=[];
    // Website-Informationen: Titel und Untertitel der Website
    $secs['title_tagline']=['id'=>'title_tagline','title'=>'Website-Informationen','description'=>'','controls'=>[
        ['id'=>'blogname','type'=>'text','label'=>'Titel der Website','description'=>'','value'=>(string)get_option('blogname',''),'live'=>true,'max_length'=>80],
        ['id'=>'blogdescription','type'=>'text','label'=>'Untertitel','description'=>'Ein kurzer Satz, der zur Website passt.','value'=>(string)get_option('blogdescription',''),'live'=>true,'max_length'=>200],
        ['id'=>'_note_logo','type'=>'note','label'=>'Logo und Website-Icon pflegst du im CMS unter „Branding & Medien“ – das Theme übernimmt sie von dort.'],
    ]];
    $items=$r['items'];uasort($items,fn($a,$b)=>$a['priority']<=>$b['priority']);
    foreach($items as $it){
        $sid=$it['section']!==''?$it['section']:'rrw_other';
        if($sid==='title_tagline'&&in_array($it['id'],['blogname','blogdescription'],true))continue;
        if(!isset($secs[$sid])){
            $sec=$m->sections[$sid]??null;
            $title=['rrw_menus'=>'Menüs','static_front_page'=>'Startseiten-Einstellungen','custom_css'=>'Zusätzliches CSS','colors'=>'Farben','header_image'=>'Header-Medien','background_image'=>'Hintergrundbild'][$sid]??($sec?rrw_wpc_text((string)$sec->title):($sid==='rrw_other'?'Weitere Einstellungen':ucfirst($sid)));
            $secs[$sid]=['id'=>$sid,'title'=>$title!==''?$title:ucfirst($sid),'description'=>$sec?rrw_wpc_text((string)$sec->description):($sid==='rrw_menus'?'Welches CMS-Menü an welcher Stelle des Themes erscheint. Die Einträge pflegst du im CMS unter „Menüs“.':''),'priority'=>$sec?(int)$sec->priority:160,'controls'=>[]];
        }
        $c=['id'=>$it['id'],'type'=>$it['type'],'label'=>$it['label'],'description'=>$it['description'],'value'=>rrw_wpc_display($it,rrw_wpc_current($it)),'live'=>false];
        if($it['choices'])$c['choices']=$it['choices'];
        if($it['type']==='range'||$it['type']==='number'){ foreach(['min','max','step'] as $k)if($it[$k]!==null)$c[$k]=$it[$k]; }
        if($it['type']==='color'){ $c['default']=rrw_wpc_display($it,$it['default']); }
        $secs[$sid]['controls'][]=$c;
    }
    // Verweise auf die CMS-Verwaltung von Menüs und Widgets (wie die Bereiche „Menüs“/„Widgets“ im WordPress-Customizer)
    if(isset($secs['rrw_menus']))$secs['rrw_menus']['controls'][]=['id'=>'_go_menus','type'=>'go','label'=>'Menüs im CMS bearbeiten','to'=>'menus'];
    $secs['rrw_widgets']=['id'=>'rrw_widgets','title'=>'Widgets','description'=>'Welche Widgets in den Bereichen des Themes erscheinen, legst du im CMS unter „Widgets“ fest.','controls'=>[['id'=>'_go_widgets','type'=>'go','label'=>'Widgets im CMS bearbeiten','to'=>'widgets']]];
    $order=['title_tagline'=>0,'colors'=>1,'header_image'=>2,'background_image'=>3,'rrw_menus'=>4,'rrw_widgets'=>5,'static_front_page'=>6,'custom_css'=>90,'rrw_other'=>99];
    $list=array_values(array_filter($secs,fn($s)=>$s['controls']));
    usort($list,fn($a,$b)=>[$order[$a['id']]??10,$a['priority']??160]<=>[$order[$b['id']]??10,$b['priority']??160]);
    foreach($list as &$s)unset($s['priority']);unset($s);
    $t=wp_get_theme($slug);
    return ['theme'=>['slug'=>$slug,'name'=>(string)$t->get('Name')?:$slug,'block_theme'=>function_exists('wp_is_block_theme')&&wp_is_block_theme()],'sections'=>$list,'unsupported'=>$r['unsupported']];
}

/** Eine Eingabe prüfen und bereinigen. Rückgabe [true, Wert] oder [false, Fehlertext]. */
function rrw_wpc_clean(array $it, $raw): array {
    $t=$it['type'];$id=$it['id'];$v=$raw;
    if(is_array($v)||is_object($v))return [false,'Ungültiger Wert'];
    if($id==='custom_css'){ $v=(string)$v;$v=preg_replace('~</?\s*(style|script)[^>]*>~i','',$v);return [true,mb_substr(str_replace("\0",'',$v),0,60000)]; }
    switch($t){
        case 'checkbox': $v=($v===true||$v===1||in_array(strtolower((string)$v),['1','true','on','ja','yes'],true))?1:0;break;
        case 'number': case 'range':
            if(!is_numeric($v))return [false,'Bitte eine Zahl angeben'];
            $v=$v+0;if($it['min']!==null&&$v<$it['min'])$v=$it['min'];if($it['max']!==null&&$v>$it['max'])$v=$it['max'];
            $step=$it['step']??1.0;$v=(floor($step)==$step&&floor((float)$v)==(float)$v)?(int)$v:(float)$v;break;
        case 'color':
            $s=trim((string)$v);if($s==='')break;
            if(!preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i',$s,$mm))return [false,'Bitte eine Farbe wie #336699 angeben'];
            $h=strtolower($mm[1]);if(strlen($h)===3)$h=$h[0].$h[0].$h[1].$h[1].$h[2].$h[2];$v='#'.$h;break;
        case 'select': case 'radio':
            if(!in_array((string)$v,array_column($it['choices'],0),true))return [false,'Diese Auswahl gibt es nicht'];$v=(string)$v;break;
        case 'dropdown-pages':
            if(!in_array((string)(int)$v,array_column($it['choices'],0),true))return [false,'Diese Seite gibt es nicht'];$v=(int)$v;break;
        case 'image':
            $s=trim((string)$v);if($s==='')break;
            if(!preg_match('~^(https?://|/)~i',$s)||str_contains($s,'..'))return [false,'Bitte eine Bildadresse (https://… oder /pfad) angeben'];
            $v=esc_url_raw($s);if($v==='')return [false,'Diese Bildadresse ist nicht zulässig'];break;
        case 'textarea': $v=mb_substr(sanitize_textarea_field((string)$v),0,20000);break;
        default:
            $v=(string)$v;
            if($it['input']==='url'){ $v=trim($v);if($v!==''){ $v=esc_url_raw($v);if($v==='')return [false,'Bitte eine gültige Adresse angeben']; } }
            elseif($it['input']==='email'){ $v=trim($v);if($v!==''&&!is_email($v))return [false,'Bitte eine gültige E-Mail-Adresse angeben']; }
            else $v=mb_substr(sanitize_text_field($v),0,1000);
    }
    $s=$it['setting'];
    if($s){
        $cb=$s->sanitize_callback;
        if($cb===''&&in_array($id,['background_color','header_textcolor'],true)&&$v!=='')$v=ltrim((string)$v,'#');   // WordPress speichert diese Farben ohne „#“
        if($cb!==''&&$cb!==null&&is_callable($cb)){
            try{ $v=call_user_func($cb,$v,$s); }catch(Throwable $e){ return [false,'Wert nicht zulässig']; }
            if($v===null||is_wp_error($v))return [false,'Dieser Wert ist nicht zulässig'];
        }
        $v=apply_filters('customize_sanitize_'.$id,$v,$s);if($v===null||is_wp_error($v))return [false,'Dieser Wert ist nicht zulässig'];
        if($t==='checkbox')$v=$v?1:0;
    }
    if($id==='blogname'||$id==='blogdescription')$v=trim((string)$v);
    return [true,$v];
}

/** Titel/Untertitel als Pseudo-Einstellungen (Optionen), damit Speichern/Entwurf einheitlich laufen. */
function rrw_wpc_all_items(): array {
    $items=rrw_wpc_items()['items'];
    foreach(['blogname'=>['Titel der Website',80],'blogdescription'=>['Untertitel',200]] as $id=>[$lb,$max])
        if(isset($items[$id]))$items[$id]['max_len']=$max;else $items[$id]=['id'=>$id,'type'=>'text','input'=>'text','label'=>$lb,'description'=>'','section'=>'title_tagline','priority'=>0,'store'=>'option','root'=>$id,'keys'=>[],'setting'=>null,'choices'=>[],'min'=>null,'max'=>null,'step'=>null,'default'=>'','transport'=>'postMessage','max_len'=>$max];
    return $items;
}
/** Eingaben (id → Wert) prüfen. Rückgabe ['ok'=>[id=>Wert], 'errors'=>[id=>Text]]. */
function rrw_wpc_validate(array $values): array {
    $items=rrw_wpc_all_items();$ok=[];$err=[];
    foreach($values as $id=>$raw){
        $id=(string)$id;
        if(!isset($items[$id])){ $err[$id]='Diese Einstellung gibt es nicht';continue; }
        $it=$items[$id];
        [$good,$v]=rrw_wpc_clean($it,$raw);
        if($good&&isset($it['max_len'])&&mb_strlen((string)$v)>$it['max_len'])$v=mb_substr((string)$v,0,$it['max_len']);
        if($good&&$id==='blogname'&&$v==='')[$good,$v]=[false,'Der Titel darf nicht leer sein'];
        if($good)$ok[$id]=$v;else $err[$id]=(string)$v;
    }
    return ['ok'=>$ok,'errors'=>$err];
}

/** Veröffentlichen: alles prüfen, bei einem Fehler nichts speichern. Rückgabe ['saved'=>[ids],'unchanged'=>[ids],'errors'=>[id=>Text]]. */
function rrw_wpc_save(array $values): array {
    $r=rrw_wpc_validate($values);
    if($r['errors'])return ['saved'=>[],'unchanged'=>[],'errors'=>$r['errors']];
    $items=rrw_wpc_all_items();$saved=[];$same=[];
    foreach($r['ok'] as $id=>$v){
        $it=$items[$id];$cur=rrw_wpc_current($it);
        if((string)(is_scalar($cur)?$cur:json_encode($cur))===(string)(is_scalar($v)?$v:json_encode($v))){ $same[]=$id;continue; }
        if($it['keys']){
            $base=rrw_wpc_root_raw($it['store'],$it['root']);$new=rrw_wpc_nested_set($base,$it['keys'],$v);
        }else $new=$v;
        if($it['store']==='option')update_option($it['root'],$new);else set_theme_mod($it['root'],$new);
        $saved[]=$id;
    }
    if($saved)do_action('customize_save_after',rrw_wpc_items()['manager']);
    return ['saved'=>$saved,'unchanged'=>$same,'errors'=>[]];
}

/* ───────── Entwürfe für die Vorschau ───────── */
function rrw_wpc_draft_dir(): string { return RRW_WP_DATA.'/customize-drafts'; }
function rrw_wpc_new_id(): string { return bin2hex(random_bytes(16)); }
/** Entwurf schreiben (gültige Werte); gibt die Zahl der Einträge zurück. Alte Entwürfe (> 1 Stunde) werden aufgeräumt. */
function rrw_wpc_draft_save(string $id, string $slug, array $clean): int {
    if(!preg_match('/^[a-f0-9]{32}$/',$id))return 0;
    $dir=rrw_wpc_draft_dir();if(!is_dir($dir))@mkdir($dir,0775,true);
    foreach(glob($dir.'/*.json')?:[] as $f)if(@filemtime($f)<time()-3600)@unlink($f);
    $items=rrw_wpc_all_items();$entries=[];
    foreach($clean as $cid=>$v){ $it=$items[$cid]??null;if(!$it)continue;$entries[]=['store'=>$it['store'],'root'=>$it['root'],'keys'=>$it['keys'],'v'=>$v]; }
    $f=$dir.'/'.$id.'.json';$tmp=$f.'.'.bin2hex(random_bytes(3)).'.tmp';
    if(@file_put_contents($tmp,json_encode(['theme'=>$slug,'entries'=>$entries],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))===false)return 0;
    @rename($tmp,$f);return count($entries);
}
/** Entwurf lesen: nur für das Vorschau-Theme und nicht älter als 15 Minuten. */
function rrw_wpc_draft_load(string $id, string $slug): ?array {
    if(!preg_match('/^[a-f0-9]{32}$/',$id))return null;
    $f=rrw_wpc_draft_dir().'/'.$id.'.json';
    if(!is_file($f)||@filemtime($f)<time()-900){ $cs=rrw_wpc_cs_read($id);return $cs&&($cs['theme']??'')===$slug?$cs:null; }   // abgelaufen → gespeicherter Entwurf (teilbarer Vorschau-Link)
    $d=json_decode((string)@file_get_contents($f),true);
    return is_array($d)&&($d['theme']??'')===$slug&&is_array($d['entries']??null)?$d:null;
}
/** Entwurfswerte über Filter in die laufende Anfrage legen (nichts wird gespeichert). */
function rrw_wpc_draft_apply(array $d): void {
    $groups=[];
    foreach($d['entries'] as $e){ $groups[$e['store']][$e['root']][]=$e; }
    foreach($groups['option']??[] as $root=>$list){
        add_filter("pre_option_{$root}",function($pre) use($root,$list){
            $val=null;foreach($list as $e){ if($e['keys']){ if($val===null){ [$f,$b]=rrw_wp_opts_get_raw($root);$val=$f&&is_array($b)?$b:[]; }$val=rrw_wpc_nested_set($val,$e['keys'],$e['v']); }else $val=$e['v']; }
            return $val===false?$pre:$val;
        },1,3);
    }
    foreach($groups['theme_mod']??[] as $root=>$list){
        add_filter("theme_mod_{$root}",function($cur) use($list){
            $val=$cur;foreach($list as $e){ $val=$e['keys']?rrw_wpc_nested_set(is_array($val)?$val:[],$e['keys'],$e['v']):$e['v']; }
            return $val;
        },99);
    }
}
/** Kleines Skript für die Vorschau im Customizer: ändert Titel/Untertitel sofort per postMessage; sonst meldet es „nicht möglich“ und die Oberfläche lädt neu. */
function rrw_wpc_live_script(): string {
    return '<script>(function(){window.addEventListener("message",function(e){var d=e.data;if(e.origin!==location.origin||!d||d.type!=="rrw-wpc-live")return;'
        .'var n=0;(d.changes||[]).forEach(function(c){if(!c.from||c.from===c.to)return;var w=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT),t,list=[];'
        .'while(t=w.nextNode()){if(t.nodeValue.trim()===c.from&&!/^(SCRIPT|STYLE|NOSCRIPT)$/.test(t.parentNode.nodeName))list.push(t)}'
        .'list.forEach(function(x){x.nodeValue=x.nodeValue.replace(c.from,c.to);n++})});'
        .'parent.postMessage({type:"rrw-wpc-ack",id:d.id,count:n},location.origin)})})();</script>';
}


/* ───────── Gespeicherte Entwürfe und geplante Änderungen („Änderungssätze“) ───────── */
function rrw_wpc_cs_dir(): string { return RRW_WP_DATA.'/customize-changesets'; }
function rrw_wpc_cs_ok(string $id): bool { return (bool)preg_match('/^[a-f0-9]{32}$/',$id); }
/** Werte (id → bereinigter Wert) in speicherbare Einträge übersetzen. */
function rrw_wpc_entries(array $clean): array {
    $items=rrw_wpc_all_items();$e=[];
    foreach($clean as $cid=>$v){ $it=$items[$cid]??null;if($it)$e[]=['store'=>$it['store'],'root'=>$it['root'],'keys'=>$it['keys'],'v'=>$v]; }
    return $e;
}
/** Index der geplanten Änderungen neu schreiben (eine kleine Datei, damit die Website nicht jedes Mal das Verzeichnis lesen muss). */
function rrw_wpc_cs_reindex(): void {
    $dir=rrw_wpc_cs_dir();$due=[];
    foreach(glob($dir.'/*.json')?:[] as $f){ $d=json_decode((string)@file_get_contents($f),true);if(is_array($d)&&($d['status']??'')==='future')$due[]=['id'=>basename($f,'.json'),'date'=>(int)($d['date']??0)]; }
    $ix=$dir.'/future.idx';
    if(!$due){ @unlink($ix);return; }
    @file_put_contents($ix,json_encode($due),LOCK_EX);
}
/** Entwurf (status draft) oder Planung (status future, $when = Zeitstempel) speichern. Rückgabe: Fehlertext oder null. */
function rrw_wpc_cs_save(string $id, string $slug, array $clean, string $status, int $when=0, bool $activate=false): ?string {
    if(!rrw_wpc_cs_ok($id)||!in_array($status,['draft','future'],true))return 'Ungültiger Entwurf';
    if(!$clean)return 'Es gibt keine Änderungen zu speichern';
    if($status==='future'&&$when<=time())return 'Bitte einen Zeitpunkt in der Zukunft wählen';
    $dir=rrw_wpc_cs_dir();if(!is_dir($dir)&&!@mkdir($dir,0775,true))return 'Entwurf konnte nicht gespeichert werden';
    if(count(glob($dir.'/*.json')?:[])>=200&&!is_file($dir.'/'.$id.'.json'))return 'Zu viele gespeicherte Entwürfe – bitte alte verwerfen';
    $f=$dir.'/'.$id.'.json';$tmp=$f.'.'.bin2hex(random_bytes(3)).'.tmp';
    $doc=['theme'=>$slug,'status'=>$status,'date'=>$status==='future'?$when:0,'activate'=>$activate,'saved'=>time(),'values'=>$clean,'entries'=>rrw_wpc_entries($clean)];
    if(@file_put_contents($tmp,json_encode($doc,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))===false)return 'Entwurf konnte nicht gespeichert werden';
    if(!@rename($tmp,$f)){ @unlink($tmp);return 'Entwurf konnte nicht gespeichert werden'; }
    rrw_wpc_cs_reindex();return null;
}
function rrw_wpc_cs_read(string $id): ?array {
    if(!rrw_wpc_cs_ok($id))return null;
    $f=rrw_wpc_cs_dir().'/'.$id.'.json';if(!is_file($f))return null;
    $d=json_decode((string)@file_get_contents($f),true);return is_array($d)&&is_array($d['entries']??null)?$d:null;
}
/** Neuester gespeicherter Entwurf/geplanter Satz für ein Theme (zum Fortsetzen beim Öffnen des Customizers). */
function rrw_wpc_cs_latest(string $slug): ?array {
    $best=null;
    foreach(glob(rrw_wpc_cs_dir().'/*.json')?:[] as $f){ $d=json_decode((string)@file_get_contents($f),true);if(!is_array($d)||($d['theme']??'')!==$slug||!in_array($d['status']??'',['draft','future'],true))continue;if(!$best||($d['saved']??0)>($best['saved']??0)){ $d['id']=basename($f,'.json');$best=$d; } }
    return $best;
}
function rrw_wpc_cs_discard(string $id): bool {
    if(!rrw_wpc_cs_ok($id))return false;$f=rrw_wpc_cs_dir().'/'.$id.'.json';$ok=is_file($f)&&@unlink($f);rrw_wpc_cs_reindex();return $ok;
}
/** Einträge dauerhaft übernehmen (für ein bestimmtes Theme; die Werte wurden beim Speichern bereits geprüft). */
function rrw_wpc_cs_apply(array $entries, string $theme): int {
    $n=0;$mods=get_option('theme_mods_'.$theme,[]);$mods=is_array($mods)?$mods:[];
    foreach($entries as $e){
        $root=(string)($e['root']??'');if($root==='')continue;$keys=is_array($e['keys']??null)?$e['keys']:[];
        if(($e['store']??'')==='option'){ $cur=$keys?(function($r){ [$f,$v]=rrw_wp_opts_get_raw($r);return $f?$v:null; })($root):null;update_option($root,$keys?rrw_wpc_nested_set($cur,$keys,$e['v']):$e['v']); }
        else $mods[$root]=$keys?rrw_wpc_nested_set($mods[$root]??null,$keys,$e['v']):$e['v'];
        $n++;
    }
    update_option('theme_mods_'.$theme,$mods);return $n;
}
/** Fällige geplante Änderungen veröffentlichen. Gibt die Zahl der veröffentlichten Sätze zurück. */
function rrw_wpc_cs_run_due(): int {
    $ix=rrw_wpc_cs_dir().'/future.idx';if(!is_file($ix))return 0;
    $list=json_decode((string)@file_get_contents($ix),true);if(!is_array($list))return 0;$n=0;
    foreach($list as $row){
        if((int)($row['date']??0)>time())continue;
        $id=(string)($row['id']??'');$d=rrw_wpc_cs_read($id);
        if(!$d||($d['status']??'')!=='future'){ rrw_wpc_cs_discard($id);continue; }
        if(!@unlink(rrw_wpc_cs_dir().'/'.$id.'.json'))continue;   // wer die Datei entfernt, veröffentlicht (kein doppeltes Anwenden bei gleichzeitigen Aufrufen)
        rrw_wpc_cs_apply($d['entries'],(string)$d['theme']);
        if(!empty($d['activate'])&&(string)get_option('stylesheet')!==(string)$d['theme']){ try{ if(!function_exists('rrw_wpi_activate_theme'))require_once __DIR__.'/installer.php';rrw_wpi_activate_theme((string)$d['theme']); }catch(Throwable $e){} }
        $n++;
    }
    rrw_wpc_cs_reindex();return $n;
}
